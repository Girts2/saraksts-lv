<?php
/**
 * konkursi/bin/migrate_display_index.php — displeja un avota skatu indeksu būve ESOŠĀ datubāzē.
 *
 * Indeksi ($TARGET zemāk): idx_notices_display, idx_notices_cat_country, idx_notices_cat_cpv (shēmā
 * KONKURSI_SCHEMA_SQL; divus pirmajā versijā bija šaurāki → pārbūve) un idx_notices_source_display,
 * idx_notices_source_sortdate (2026-11-11; tikai KONKURSI_SCHEMA_NEW_DB_SQL — esošā DB tos iegūst TIKAI
 * no šī skripta). Uz 3 GB faila katrs indekss nolasa visu tabulu — serverī minūtes. Ja to darītu tīmekļa
 * pieprasījums, pārējie pēc busy_timeout saņemtu «database is locked», tāpēc tas ir atsevišķs CLI solis.
 *
 * Avota indeksus palaid TŪLĪT PĒC konkursi.php/config.php/snapshot.php izvietošanas (bez jaunā koda tie
 * LV skatu palēnina) un ārpus sinhronizācijas (04:00). Ar --apply skripts PIRMS plāna aprēķina paņem
 * sync.lock (ieraksta savu PID) — sinhronizācija tikmēr nesāksies, un paralēls otrs gājiens apstājas.
 * Lasītāji (WAL) būves laikā strādā; kešu ieraksti var gaidīt busy_timeout.
 *
 * Idempotents: katru indeksu salīdzina ar mērķa definīciju (ok / create / rebuild) un būvē tikai vajadzīgo;
 * pārbūve (DROP+CREATE) vienā transakcijā. Zondes: skats bez filtriem + noklusējuma LV avota skats
 * (agrāk mērīja TIKAI bez filtriem, tāpēc LV valstu izvēlnes ~75 000 lapu palika nepamanītas).
 *
 * Lietošana:
 *   php konkursi/bin/migrate_display_index.php           # sausā palaišana (mēra, neko nebūvē)
 *   php konkursi/bin/migrate_display_index.php --apply   # būvē trūkstošos / pārbūvē novecojušos
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

set_time_limit(0);

$apply = in_array('--apply', $argv, true);

// Savienojums TIEŠI, apejot konkursi_db(): tā izpilda KONKURSI_SCHEMA_SQL (CREATE INDEX IF NOT EXISTS) un
// sausajā palaišanā izmainītu DB, kurai jāizmēra "pirms".
$path = konkursi_db_path();
if (!is_file($path)) {
    fwrite(STDERR, "Datubāze nav atrasta: $path\n");
    exit(1);
}
$pdo = new PDO('sqlite:' . $path, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA busy_timeout=60000');   // būves laikā sync var turēt slēdzeni
$pdo->exec('PRAGMA mmap_size=268435456');
$pdo->exec('PRAGMA cache_size=-65536');

// --apply: sync.lock PIRMS plāna aprēķina (citādi otrs paralēls gājiens ar novecojušu plānu mēģinātu
// būvēt jau uzbūvētu indeksu). Tā pati slēdzene, ko ks_acquire_lock (sync_engine.php) — sinhronizācija
// tikmēr nesāksies (tās raksti būves laikā tik un tā kristu pēc busy_timeout). PID failā — tāpat kā
// sinhronizācija ieraksta savējo, lai konkursi_admin.php STOP nenogalinātu svešu procesu.
$syncLock = null;
if ($apply) {
    $lockPath = konkursi_lock_path();
    $syncLock = @fopen($lockPath, 'c');
    if ($syncLock === false) {
        fwrite(STDERR, "STOP: nevar atvērt slēdzenes failu $lockPath (vai mape eksistē?)\n");
        exit(1);
    }
    if (!flock($syncLock, LOCK_EX | LOCK_NB)) {
        fwrite(STDERR, "STOP: konkursu sinhronizācija vai cits šī skripta gājiens šobrīd iet (sync.lock aizņemts) — palaid pēc tā.\n");
        exit(1);
    }
    ftruncate($syncLock, 0);
    fwrite($syncLock, (string)getmypid());
    fflush($syncLock);
}

/**
 * Mērķa indeksi — TĀM PAŠĀM definīcijām jābūt lib/db.php (KONKURSI_SCHEMA_SQL / KONKURSI_SCHEMA_NEW_DB_SQL).
 * Esošu indeksu ar vecu definīciju CREATE INDEX IF NOT EXISTS nemaina — tāpēc plāns 'rebuild'.
 */
$TARGET = [
    'idx_notices_display' =>
        'CREATE INDEX idx_notices_display ON notices('
        . 'category, deadline_date, publication_date, first_seen, '
        . 'source, buyer_country, main_cpv, buyer_name)',
    'idx_notices_cat_country' =>
        'CREATE INDEX idx_notices_cat_country ON notices('
        . 'category, buyer_country, deadline_date, publication_date, first_seen)',
    'idx_notices_cat_cpv' =>
        'CREATE INDEX idx_notices_cat_cpv ON notices('
        . 'category, main_cpv, deadline_date, publication_date, first_seen)',
    'idx_notices_source_display' =>
        'CREATE INDEX idx_notices_source_display ON notices('
        . 'source, category, deadline_date, publication_date, first_seen, '
        . 'buyer_country, main_cpv, buyer_name)',
    'idx_notices_source_sortdate' =>
        'CREATE INDEX idx_notices_source_sortdate ON notices('
        . 'source, category, COALESCE(publication_date, first_seen) DESC, id DESC)',
];

/** Esošā indeksa definīcija (NULL, ja indeksa nav). */
$currentSql = static function (PDO $pdo, string $name): ?string {
    $st = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='index' AND name = ?");
    $st->execute([$name]);
    $v = $st->fetchColumn();
    return $v === false || $v === null ? null : (string)$v;
};

/**
 * Salīdzināšanai: atstarpes un reģistrs nav būtiski. Atstarpes izmet PILNĪBĀ, nevis
 * tikai saspiež — db.php shēmā kolonnu saraksts ir pārnests jaunā rindā, tāpēc
 * saspiešana atstātu 'notices( category' pret 'notices(category' un šis skripts
 * mūžīgi ziņotu par pārbūvējamu indeksu. Indeksa DDL nav virkņu literāļu, tāpēc
 * atstarpju izmešana ir droša.
 */
$norm = static fn(string $s): string => strtolower(preg_replace('/\s+/', '', trim($s)) ?? '');

/** Ko vajag darīt katram indeksam: 'ok' | 'create' | 'rebuild'. */
$plan = [];
foreach ($TARGET as $name => $sql) {
    $cur = $currentSql($pdo, $name);
    $plan[$name] = $cur === null ? 'create' : ($norm($cur) === $norm($sql) ? 'ok' : 'rebuild');
}

// ── Displeja logi — TIE PAŠI, ko lieto konkursi.php ──────────────────────────
$today = konkursi_today();
$tz = new DateTimeZone('Europe/Riga');
$cutNoDl   = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_NODEADLINE_DAYS . ' days')->format('Y-m-d');
$cutResult = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_RESULTS_DAYS . ' days')->format('Y-m-d');
$activeCond = "(n.deadline_date >= '$today' OR (n.deadline_date IS NULL AND COALESCE(n.publication_date, n.first_seen) >= '$cutNoDl'))";
$resultCond = "(n.publication_date IS NULL OR n.publication_date >= '$cutResult')";
// Noklusējuma skats (konkursi.js DEFAULT_SOURCE) — tieši tā, kā to sūta konkursi.php avota skatā
[$lvActive] = konkursi_display_conds(false, 'n.', true);
$lvSrc = "n.source IN ('IUB','MODTI','RSTI','ASTI','LDZ')";

/** Starta karstā ceļa vaicājumi — tie, kas pirms indeksa skenēja visu tabulu. */
$PROBES = [
    'Aktīvie skaitītājs (list)' =>
        "SELECT COUNT(*) FROM notices n WHERE n.category='iepirkumi' AND $activeCond",
    'Rezultātu skaitītājs (list)' =>
        "SELECT COUNT(*) FROM notices n WHERE n.category='rezultati' AND $resultCond",
    'Avotu panelis (sources)' =>
        "SELECT n.source, COUNT(*) c FROM notices n WHERE n.category='iepirkumi' AND $activeCond GROUP BY n.source",
    'Valstu izvēlne (countries)' =>
        "SELECT n.buyer_country, COUNT(*) c, MIN(n.publication_date), MAX(n.publication_date)
           FROM notices n WHERE n.category='iepirkumi' AND $activeCond GROUP BY n.buyer_country",
    'CPV izvēlne (cpv)' =>
        "SELECT substr(n.main_cpv,1,2) d, COUNT(*) c FROM notices n
          WHERE n.category='iepirkumi' AND n.main_cpv IS NOT NULL AND $activeCond GROUP BY d",
    'Pasūtītāju izvēlne (buyers)' =>
        "SELECT n.buyer_name, COUNT(*) c FROM notices n
          WHERE n.category='iepirkumi' AND n.buyer_name IS NOT NULL AND n.buyer_name <> ''
            AND $activeCond GROUP BY n.buyer_name ORDER BY c DESC LIMIT " . KONKURSI_BUYER_SUGGEST_MAX,
    'LV: Aktīvie skaitītājs' =>
        "SELECT COUNT(*) FROM notices n WHERE $lvSrc AND n.category='iepirkumi' AND $lvActive",
    'LV: Valstu izvēlne' =>
        "SELECT n.buyer_country, COUNT(*) c, MIN(n.publication_date), MAX(n.publication_date)
           FROM notices n WHERE n.category='iepirkumi' AND $lvSrc AND $lvActive GROUP BY +n.buyer_country",
    'LV: CPV izvēlne' =>
        "SELECT substr(n.main_cpv,1,2) d, COUNT(*) c FROM notices n
          WHERE n.category='iepirkumi' AND +n.main_cpv IS NOT NULL AND $lvSrc AND $lvActive GROUP BY d",
    'LV: Pasūtītāju izvēlne' =>
        "SELECT n.buyer_name, COUNT(*) c FROM notices n
          WHERE n.category='iepirkumi' AND n.buyer_name IS NOT NULL AND n.buyer_name <> ''
            AND $lvSrc AND $lvActive GROUP BY n.buyer_name ORDER BY c DESC LIMIT " . KONKURSI_BUYER_SUGGEST_MAX,
];

/** printf %-30s skaita baitus, tāpēc garumzīmes izjauktu kolonnas — papildina pēc rakstzīmēm. */
$pad = static function (string $s, int $w): string {
    $n = mb_strlen($s, 'UTF-8');
    return $n >= $w ? $s : $s . str_repeat(' ', $w - $n);
};

/**
 * Izpilda vaicājumu; atgriež [ms, vai plāns iztiek BEZ tabulas lasījumiem].
 * Mērķis nav konkrēts indeksa nosaukums, bet gan tas, ka KATRS solis plānā ir
 * pārklājošs — tieši rindu lasījumi 1 GB tabulā ir tas, kas maksā sekundes.
 */
$probe = static function (PDO $pdo, string $sql): array {
    $t = microtime(true);
    $pdo->query($sql)->fetchAll();
    $ms = (microtime(true) - $t) * 1000;
    $covered = true;
    foreach ($pdo->query('EXPLAIN QUERY PLAN ' . $sql) as $r) {
        $d = (string)($r['detail'] ?? '');
        // Interesē tikai tabulas piekļuves soļi; MULTI-INDEX OR / TEMP B-TREE nav.
        if (!preg_match('/\b(SEARCH|SCAN)\b/', $d)) continue;
        if (!str_contains($d, 'COVERING INDEX')) { $covered = false; break; }
    }
    // LV (avota) zondēm ar to vien nepietiek: pārklājošs idx_notices_display (category=?) arī ir
    // «pārklājošs», bet nolasa visu kategoriju (~3 500 lapu). Avota skatam jāiet pa avota indeksu.
    $plan = implode(' ', array_column($pdo->query('EXPLAIN QUERY PLAN ' . $sql)->fetchAll(), 'detail'));
    return [$ms, $covered, str_contains($plan, 'idx_notices_source_')];
};
$avotaZonde = static fn(string $label): bool => str_starts_with($label, 'LV:');

/** Faila izmērs MB (stat kešs jānotīra, citādi pēc būves rāda veco). */
$sizeMb = static function (string $p): int {
    clearstatcache(true, $p);
    return (int)round((int)@filesize($p) / 1048576);
};

$dbMb = $sizeMb($path);
$rows = (int)$pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();
$act  = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE category='iepirkumi'")->fetchColumn();

$STATUS = ['ok' => 'jau atbilst', 'create' => 'JĀIZVEIDO', 'rebuild' => 'JĀPĀRBŪVĒ (veca definīcija)'];
echo "Datubāze:       $path ({$dbMb} MB)\n";
echo "notices rindas: $rows (no tām 'iepirkumi': $act)\n\n";
echo "Indeksi:\n";
foreach ($plan as $name => $what) printf("  %s %s\n", $pad($name, 30), $STATUS[$what]);
echo "\n";
echo $apply ? "REŽĪMS: --apply (būvē indeksus)\n\n"
            : "REŽĪMS: sausā palaišana (neko nebūvē; --apply lai izpildītu)\n\n";

// ── "Pirms" mērījums ────────────────────────────────────────────────────────
echo "Starta vaicājumu laiki ŠOBRĪD:\n";
$before = [];
foreach ($PROBES as $label => $sql) {
    [$ms, $cov, $avIdx] = $probe($pdo, $sql);
    $before[$label] = $ms;
    printf("  %s %8.0f ms%s\n", $pad($label, 30), $ms, !$cov ? '  ← lasa tabulu'
        : ($avotaZonde($label) && !$avIdx ? '  ← skenē visu kategoriju (nav avota indeksa)' : '  (jau pārklājošs)'));
}
printf("  %s %8.0f ms\n", $pad('KOPĀ', 30), array_sum($before));

if (!$apply) {
    echo "\nSausā palaišana pabeigta. Nekas netika rakstīts.\n";
    echo "Palaid ar --apply, lai izveidotu indeksus.\n";
    exit(0);
}

// ── Būve ────────────────────────────────────────────────────────────────────
// (sync.lock jau paņemts augšā, pirms plāna aprēķina)
// Apzināti BEZ ANALYZE: ar sqlite_stat4 plānotājs valstu izvēlnei izvēlējās šauro
// indeksu un atgriezās pie rindu lasījumiem (106 ms vietā 7 ms). Bez tā izvēle ir
// stabila un pareiza.
if (!in_array('create', $plan, true) && !in_array('rebuild', $plan, true)) {
    echo "\nVisi indeksi jau atbilst — būve izlaista.\n";
} else {
    foreach ($plan as $name => $what) {
        if ($what === 'ok') continue;
        $t = microtime(true);
        // DROP+CREATE vienā transakcijā: ja CREATE neizdodas, vecais indekss paliek (recenzija 2026-11-11)
        $pdo->beginTransaction();
        try {
            if ($what === 'rebuild') {
                echo "\nPārbūvē $name (veca definīcija) …\n";
                $pdo->exec("DROP INDEX IF EXISTS $name");
            } else {
                echo "\nBūvē $name …\n";
            }
            $pdo->exec($TARGET[$name]);
            $pdo->commit();
        } catch (Throwable $e) {
            // SQLite pats var būt atritinājis (IOERR/NOMEM) — tad rollBack() mestu un aizsegtu īsto kļūdu
            if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $r) {} }
            fwrite(STDERR, "KĻŪDA, $name nav mainīts: " . $e->getMessage() . "\n");
            exit(1);
        }
        printf("  gatavs %.1f s laikā.\n", microtime(true) - $t);
    }
}

$newMb = $sizeMb($path);
printf("\nDatubāzes izmērs: %d MB → %d MB (%+d MB)\n\n", $dbMb, $newMb, $newMb - $dbMb);

// ── "Pēc" mērījums ──────────────────────────────────────────────────────────
echo "Starta vaicājumu laiki PĒC:\n";
$after = [];
$allCovered = true;
foreach ($PROBES as $label => $sql) {
    [$ms, $cov, $avIdx] = $probe($pdo, $sql);
    $after[$label] = $ms;
    $ok = $cov && (!$avotaZonde($label) || $avIdx);
    if (!$ok) $allCovered = false;
    printf("  %s %8.0f ms  (bija %.0f ms)%s\n", $pad($label, 30), $ms, $before[$label],
        !$cov ? '  ⚠ lasa tabulu' : ($ok ? '  ✓ pārklājošs' : '  ⚠ skenē visu kategoriju (nav avota indeksa)'));
}
printf("  %s %8.0f ms  (bija %.0f ms)\n", $pad('KOPĀ', 30), array_sum($after), array_sum($before));

echo "\n";
echo $allCovered
    ? "✓ Visi starta vaicājumi iet pa pārklājošiem indeksiem — tabula netiek aiztikta.\n"
    : "⚠ Daļa vaicājumu joprojām lasa tabulu. Pārbaudi, vai displeja logi lib/config.php\n"
    . "  (KONKURSI_KEEP_*) un konkursi.php nosacījumi joprojām sakrīt ar indeksa kolonnām.\n";
