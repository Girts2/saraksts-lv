<?php
/**
 * konkursi/lib/store.php — Glabāšanas slānis (kolektora → glabātuves šuve).
 *
 * Divi datu slāņi vienā SQLite failā:
 *   notice_versions — NEMAINĪGS (append-only) patiesības žurnāls. Katra novērotā
 *                     ieraksta versija; pēc ievietošanas rindu NEKAD neaiztiek.
 *                     Rezultāti/labojumi ienāk kā jaunas versijas ar to pašu id.
 *   notices         — atvasināts PAŠREIZĒJAIS skats (1 rinda/id = jaunākā versija),
 *                     ko lasa web lapa + FTS. Drīkst upsertot — patiesība ir žurnālā.
 *
 * KsWriter ir vienīgais DB rakstītājs sinhronizācijā. Tas saglabā to pašu
 * ->execute($n) saskarni kā vecais PDOStatement, tāpēc esošie ks_sync_* izsaukumi
 * (`$stmt = ks_upsert_stmt($pdo); $stmt->execute($n);`) nemainās.
 */
declare(strict_types=1);

// `notices` upsert kolonnas kārtībā — vienota definīcija (ks_upsert_stmt to lieto).
const KS_NOTICE_COLS = [
    'id','source','category','title','description','buyer_name','buyer_id','buyer_country',
    'buyer_activity','buyer_type','procure_nature','publication_date','deadline_date',
    'deadline_time','publication_number','budget','currency','document_url',
    'buyer_profile_url','procedure_type','notice_sub_type','notice_lang','issue_date',
    'main_nuts','main_country','funding_program','prev_notice_ref','contract_folder_id',
    'main_cpv','cpv_codes','lots','organizations','notice_contact','source_file',
];

// Satura hash IZSLĒGTIE lauki: id/source = identitāte; source_file mainās katrā TED
// paketē (provenience, ne saturs). Pārējie nosaka, vai saturs tiešām mainījies.
const KS_HASH_EXCLUDE = ['id','source','source_file'];

/** Deterministisks satura hash: identisks saturs → identisks hash → nekas netiek rakstīts. */
function ks_content_hash(array $n): string {
    $parts = [];
    foreach (KS_NOTICE_COLS as $c) {
        if (in_array($c, KS_HASH_EXCLUDE, true)) continue;
        $v = $n[$c] ?? null;
        $parts[] = $v === null ? '' : (string)$v;
    }
    return hash('sha256', implode("\x1f", $parts));
}

/**
 * Novēro ierakstu: pievieno versiju žurnālam TIKAI ja jauns vai saturs mainījies,
 * un attiecīgi atjaunina `notices` pašreizējo skatu.
 *   'new'       — pirmoreiz redzēts id → v1
 *   'version'   — id jau bija, saturs mainījies → v(n+1)
 *   'unchanged' — id jau bija, tas pats saturs → nekas netiek rakstīts
 */
final class KsWriter
{
    private PDO $pdo;
    private PDOStatement $cur;     // notices upsert (pašreizējais skats)
    private PDOStatement $lookup;  // jaunākā versija konkrētam id
    private PDOStatement $ins;     // notice_versions pievienošana (append)
    private PDOStatement $clearLv; // title_lv NULL, ja mainījies virsraksts

    public int $new = 0;
    public int $versions = 0;
    public int $unchanged = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $cols = KS_NOTICE_COLS;
        $upd  = implode(',', array_map(static fn($c) => "$c=excluded.$c", array_slice($cols, 1)));
        $this->cur = $pdo->prepare(
            'INSERT INTO notices (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ') '
            . 'ON CONFLICT(id) DO UPDATE SET ' . $upd
        );
        $this->lookup = $pdo->prepare(
            'SELECT version_no, content_hash, title FROM notice_versions WHERE id = ? ORDER BY version_no DESC LIMIT 1'
        );
        // Tulkojums (title_lv) nav upsert kolonnās, tāpēc pārdzīvo versijas maiņu —
        // bet, ja mainījies pats virsraksts, vecais tulkojums vairs neder → NULL.
        $this->clearLv = $pdo->prepare('UPDATE notices SET title_lv = NULL WHERE id = ?');
        $vcols = array_merge($cols, ['version_no', 'observed_at', 'content_hash']);
        $this->ins = $pdo->prepare(
            'INSERT INTO notice_versions (' . implode(',', $vcols) . ') VALUES (:' . implode(',:', $vcols) . ')'
        );
    }

    /** @return 'new'|'version'|'unchanged' */
    public function execute(array $n): string
    {
        // Centrāls budžeta sargs (visiem avotiem): summa <= 0 nav īsta vērtība — tā ir
        // sentinels "vērtība nav atklāta" (TED lieto −1, PLACSP/ETENDERS u.c. 0). Glabājam
        // null, lai kartiņā/detaļās neparādās "−1 €"/"0 €". Pirms hash, lai būtu konsistents.
        if (isset($n['budget']) && $n['budget'] !== null && (float)$n['budget'] <= 0) $n['budget'] = null;

        $hash = ks_content_hash($n);
        $this->lookup->execute([$n['id'] ?? '']);
        $prev = $this->lookup->fetch(PDO::FETCH_ASSOC);
        $this->lookup->closeCursor();

        if ($prev === false) {
            $this->append($n, 1, $hash);
            $this->cur->execute($n);
            $this->new++;
            return 'new';
        }
        if ((string)($prev['content_hash'] ?? '') === $hash) {
            $this->unchanged++;
            return 'unchanged';   // nekas netiek rakstīts — dati paliek fiksēti
        }
        $this->append($n, ((int)$prev['version_no']) + 1, $hash);
        $this->cur->execute($n);
        if ((string)($prev['title'] ?? '') !== (string)($n['title'] ?? '')) {
            $this->clearLv->execute([$n['id'] ?? '']);   // virsraksts mainījies → tulkojums novecojis
        }
        $this->versions++;
        return 'version';
    }

    /** Kopējie skaitītāji (žurnālam/atskaitei). */
    public function summary(): string
    {
        return "jauni={$this->new}, versijas={$this->versions}, nemainīti={$this->unchanged}";
    }

    private function append(array $n, int $ver, string $hash): void
    {
        // Tikai zināmās kolonnas — parseri var pievienot papildu atslēgas, un tās
        // sabojātu nosaukto parametru sasaisti.
        $row = [];
        foreach (KS_NOTICE_COLS as $c) {
            $row[$c] = $n[$c] ?? null;
        }
        $row['version_no']   = $ver;
        $row['observed_at']  = date('c');
        $row['content_hash'] = $hash;
        $this->ins->execute($row);
    }
}

/**
 * Pēc sinhronizācijas atsvaidzina visu avotu ūdenszīmes no faktiski savāktā
 * (max publication_date / avots). Universāls — nav jāpieskaras 41 ks_sync_*
 * funkcijai. Per-avota loga logika (F3-D) šīs ūdenszīmes tad lasa.
 */
function ks_refresh_watermarks(PDO $pdo): void
{
    foreach ($pdo->query("SELECT source, MAX(publication_date) w FROM notices
                          WHERE publication_date IS NOT NULL GROUP BY source") as $r) {
        ks_set_source_state($pdo, (string)$r['source'], (string)$r['w']);
    }
}

// ── Ūdenszīmes (per-avots inkrementalitāte; lieto ks_sync_* fāzē F3) ──────────

/** Pēdējais sekmīgi ievāktais publication_date avotam, vai null (pirmā palaišana). */
function ks_source_watermark(PDO $pdo, string $source): ?string
{
    $st = $pdo->prepare('SELECT watermark_date FROM source_state WHERE source = ?');
    $st->execute([$source]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null || $v === '') ? null : (string)$v;
}

/**
 * Inkrementālā loga sākuma datums (YYYY-MM-DD) avotam, kas API atbalsta datuma
 * filtru. Pirmā palaišana (nav ūdenszīmes) → sākotnējais aizpildes logs. Vēlāk →
 * ūdenszīme mīnus KONKURSI_OVERLAP_DAYS (šaurs logs; 3d pārklājums noķer novēlotos).
 * Ūdenszīmi klampē uz šodienu, lai kļūdaini nākotnes datumi (piem. ATKD avota
 * ievadīts 2026-08-20) nepārlēktu logu uz priekšu un nepalaistu garām visu.
 */
function ks_window_from(PDO $pdo, string $source, ?int $initialDays = null): string
{
    $tz = new DateTimeZone('Europe/Riga');
    $todayStr = konkursi_today();
    $today = new DateTimeImmutable($todayStr, $tz);
    $initial = $today->modify('-' . ($initialDays ?? KONKURSI_ACTIVE_WINDOW_DAYS) . ' days')->format('Y-m-d');

    $wm = ks_source_watermark($pdo, $source);
    if ($wm === null) return $initial;                       // pirmā palaišana

    if ($wm > $todayStr) $wm = $todayStr;                    // klampē nākotnes ūdenszīmes
    return (new DateTimeImmutable($wm, $tz))
        ->modify('-' . KONKURSI_OVERLAP_DAYS . ' days')->format('Y-m-d');
}

/**
 * Detaļu-lapas droseles palīgs saraksta-tikai avotiem, kas ielādē detaļu uz katru
 * paziņojumu (Comdia paraugs). Atgriež TRUE, ja detaļu vajag: jauns id VAI pēdējā
 * pārbaude senāka par $recheckHours. $key = per-paziņojuma imported_files atslēga
 * (piem. 'ISUTB:slug'). Pēc sekmīgas ielādes izsauc ks_mark_detail($pdo, $key).
 */
function ks_detail_due(PDO $pdo, string $key, int $recheckHours): bool
{
    static $sel = null;
    $sel ??= $pdo->prepare('SELECT imported_at FROM imported_files WHERE file_key = ?');
    $sel->execute([$key]);
    $at = $sel->fetchColumn();
    $sel->closeCursor();
    if ($at === false) return true;                          // nekad nav ielādēts
    $cut = (new DateTimeImmutable('now'))->modify("-$recheckHours hours")->format('c');
    return ((string)$at) < $cut;
}

/** Atzīmē detaļu-lapu kā tikko ielādētu (recheck droselei). */
function ks_mark_detail(PDO $pdo, string $key): void
{
    static $ins = null;
    $ins ??= $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,1)');
    $ins->execute([$key, date('c')]);
}

/** Avota-specifiskais kursors (ja avotam tāds ir), vai null. */
function ks_source_cursor(PDO $pdo, string $source): ?string
{
    $st = $pdo->prepare('SELECT last_cursor FROM source_state WHERE source = ?');
    $st->execute([$source]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null || $v === '') ? null : (string)$v;
}

/**
 * Fiksē avota stāvokli pēc sekmīgas palaišanas. watermark_date ir MONOTONS — tikai
 * uz priekšu (ISO datumi salīdzinās leksikogrāfiski), lai kļūdains agrāks datums
 * nepavērstu logu atpakaļ. $counts var būt KsWriter (ņem new/versions/unchanged).
 */
function ks_set_source_state(PDO $pdo, string $source, ?string $watermark = null,
                             ?string $cursor = null, $counts = null): void
{
    $new = $ver = $unch = 0;
    if ($counts instanceof KsWriter) {
        $new = $counts->new; $ver = $counts->versions; $unch = $counts->unchanged;
    } elseif (is_array($counts)) {
        $new = (int)($counts['new'] ?? 0);
        $ver = (int)($counts['versions'] ?? 0);
        $unch = (int)($counts['unchanged'] ?? 0);
    }
    $st = $pdo->prepare(
        'INSERT INTO source_state (source, watermark_date, last_cursor, last_run_at, last_new, last_versions, last_unchanged)
         VALUES (:s, :w, :c, :t, :n, :v, :u)
         ON CONFLICT(source) DO UPDATE SET
            watermark_date = CASE
                WHEN excluded.watermark_date IS NULL THEN source_state.watermark_date
                WHEN source_state.watermark_date IS NULL THEN excluded.watermark_date
                WHEN excluded.watermark_date > source_state.watermark_date THEN excluded.watermark_date
                ELSE source_state.watermark_date END,
            last_cursor    = COALESCE(excluded.last_cursor, source_state.last_cursor),
            last_run_at    = excluded.last_run_at,
            last_new       = excluded.last_new,
            last_versions  = excluded.last_versions,
            last_unchanged = excluded.last_unchanged'
    );
    $st->execute([
        ':s' => $source, ':w' => $watermark, ':c' => $cursor, ':t' => date('c'),
        ':n' => $new, ':v' => $ver, ':u' => $unch,
    ]);
}
