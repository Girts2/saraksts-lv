<?php
/**
 * konkursi/lib/translate_batch.php — virsrakstu tulkošana caur Gemini Batch API.
 *
 * KĀPĒC: tā pati uzvedne un tas pats modelis, bet 50 % no cenas. Tulkošana ir
 * lielākā MI izmaksu pozīcija, un tā jau tāpat notiek naktī cron'ā.
 *
 * KĀPĒC NE 24 h NOBĪDE: dokumentācija sola "līdz 24 h", bet mērījums 2026-09-03/04:
 * 2 pieprasījumi → 2 min 10 s; 100 pieprasījumi (4 000 virsraksti) → 2 min 18 s;
 * 216 pieprasījumi (8 631) → 3 min 46 s. Tāpēc konveijers pēc iesniegšanas pats
 * pagaida (ks_translate_batch_run $wait) un savāc TAJĀ PAŠĀ palaišanā; ja nepaspēj,
 * darbs paliek DB un to savāc nākamā palaišana.
 *
 * STĀVOKĻU MAŠĪNA (translate_batches.state):
 *   SUBMITTING ─POST ok─► BATCH_STATE_PENDING/RUNNING ─► BATCH_STATE_SUCCEEDED ─► COLLECTED
 *      │ POST 4xx/5xx → REJECTED (virsraksti atbrīvoti)
 *      │ POST taimauts → paliek SUBMITTING; nākamā savākšana saskaņo ar API sarakstu
 *      │   pēc display_name (darbs Google pusē var būt izveidots!) → pārņem vai
 *      │   pēc 30 min → RELEASED
 *   PENDING/RUNNING ─API FAILED/EXPIRED/CANCELLED─► tas pats stāvoklis, virsraksti atbrīvoti
 *   PENDING/RUNNING ─GET 404─► RELEASED; ─GET cita kļūda ilgāk par 30 h─► cancel + RELEASED
 *   SUCCEEDED bez lasāmām atbildēm ─► UNREADABLE (atbrīvoti)
 *   Termināls = katrs stāvoklis, kurā virsraksti ir atbrīvoti vai pierakstīti. Papildus
 *   savākšana ņem KATRU darbu, kam translate_batch_titles vēl ir rindas — lai neviens
 *   virsraksts nepaliek bloķēts tikai tāpēc, ka stāvoklis ierakstīts pirms atbrīvošanas.
 *
 * DROŠĪBAS PUNKTI (katrs no reālas mācības, sk. audita 2026-09-04 atradumus):
 *  - Virsraksti atvērtā darbā netiek iesniegti otrreiz (ne šeit, ne tūlītējā ceļā).
 *  - Nodoma ieraksts DB ir PIRMS POST: ja process nomirst starp POST un DB, darbs
 *    nav "pazudis un apmaksāts" — to saskaņo nākamā palaišana.
 *  - Paketes atbilde tiek pierakstīta TIKAI tad, ja skaits sakrīt UN atšķirīgiem
 *    virsrakstiem nav vienādu tulkojumu (nobīdes / saplūšanas pazīme).
 *  - Dienas budžets skaita arī iesniegtos, vēl nesavāktos darbus (est_eur).
 *  - Atbrīvošana un stāvokļa ieraksts ir viena transakcija; tēriņa pieskaitīšana ir
 *    atomāra (konkursi_meta_add), ne lasi-pieskaiti-raksti.
 *  - Kļūmju skaitītājs kāpj pa (virsraksts, darbs) vienreiz — arī tad, ja savākšana
 *    tiek atkārtota pēc krišanas.
 */
declare(strict_types=1);

const KS_TB_TERMINAL = ['COLLECTED', 'BATCH_STATE_FAILED', 'BATCH_STATE_EXPIRED', 'BATCH_STATE_CANCELLED',
                        'UNREADABLE', 'RELEASED', 'REJECTED'];
const KS_TB_LIVE     = ['BATCH_STATE_PENDING', 'BATCH_STATE_RUNNING'];

/** Batch cena = puse no parastās (ai.google.dev/gemini-api/docs/batch-api). */
function ks_tb_prices(): array
{
    return [KONKURSI_GEMINI_IN_USD_1M * KONKURSI_GEMINI_BATCH_MULT,
            KONKURSI_GEMINI_OUT_USD_1M * KONKURSI_GEMINI_BATCH_MULT];
}

function ks_tb_log(string $msg): void
{
    if (function_exists('ks_log')) { ks_log($msg); return; }
    echo $msg, "\n";
}

/** Tabulas un kolonnas top pēc vajadzības — izvietošanai nav atsevišķa migrācijas soļa. */
function ks_tb_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS translate_batches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job TEXT NOT NULL UNIQUE,
        state TEXT NOT NULL,
        model TEXT NOT NULL,
        requests INTEGER NOT NULL DEFAULT 0,
        titles INTEGER NOT NULL DEFAULT 0,
        written INTEGER NOT NULL DEFAULT 0,
        in_tokens INTEGER NOT NULL DEFAULT 0,
        out_tokens INTEGER NOT NULL DEFAULT 0,
        eur REAL NOT NULL DEFAULT 0,
        created TEXT NOT NULL,
        updated TEXT,
        note TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS translate_batch_titles (
        batch_id INTEGER NOT NULL,
        chunk INTEGER NOT NULL,
        pos INTEGER NOT NULL,
        title TEXT NOT NULL,
        PRIMARY KEY (batch_id, chunk, pos)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tbt_title ON translate_batch_titles(title)");
    // Kļūmju uzskaite pa virsrakstiem: modelis 40 virsrakstu paketē reizēm atdod 39
    // elementus. Pierakstīt nedrīkst (sabīdītos), atmest arī ne (kristu atkal) →
    // kritušos sūtām mazākās paketēs 40 → 10 → 1.
    $pdo->exec("CREATE TABLE IF NOT EXISTS translate_batch_fails (
        title TEXT PRIMARY KEY,
        fails INTEGER NOT NULL DEFAULT 0,
        updated TEXT
    )");
    // Kolonnas, kas pievienotas pēc pirmās versijas (ALTER, jo CREATE IF NOT EXISTS
    // esošu tabulu nepapildina).
    $cols = array_column($pdo->query("PRAGMA table_info(translate_batches)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('est_eur', $cols, true))      $pdo->exec("ALTER TABLE translate_batches ADD COLUMN est_eur REAL NOT NULL DEFAULT 0");
    if (!in_array('display_name', $cols, true)) $pdo->exec("ALTER TABLE translate_batches ADD COLUMN display_name TEXT");
}

/**
 * Viena procesa sardze. Atgriež rokturi, null ja aizņemts. Ja failu nevar atvērt
 * vispār (tiesības), to saka atsevišķi — citādi "cits process strādā" slēptu to,
 * ka tulkošana klusi nenotiek nekad.
 */
function ks_tb_lock(?string &$why = null)
{
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $fp = @fopen($dir . '/translate_batch.lock', 'c');
    if (!$fp) { $why = 'slēdzenes failu nevar atvērt (tiesības?) ' . $dir; return null; }
    if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); $why = 'cits process jau strādā'; return null; }
    return $fp;
}

function ks_tb_api(string $path, ?array $body = null, int $timeout = 300, string $method = ''): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($path, '/')
         . '?key=' . urlencode(reg_gemini_key());
    $ch = curl_init($url);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 20];
    if ($body !== null || $method === 'POST') {
        $opt[CURLOPT_POST]       = true;
        $opt[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        $opt[CURLOPT_POSTFIELDS] = json_encode($body ?? new stdClass(), JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opt);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    $j    = $raw === false ? null : json_decode((string)$raw, true);
    // Nolasīšana ir uzticama TIKAI tad, ja HTTP 200, bez curl kļūdas UN korpuss ir JSON.
    // Taimauts ķermeņa vidū atstāj kodu 200 ar false ķermeni — tā nav atbilde.
    $ok = $code === 200 && $err === '' && is_array($j);
    return ['ok' => $ok, 'code' => $code, 'json' => is_array($j) ? $j : [], 'err' => $err, 'raw' => (string)$raw];
}

/** Aplēstā cena virsrakstu kopai ar Batch cenām (tā pati formula kā translate_titles.php). */
function ks_tb_estimate(array $titles): float
{
    if (!$titles) return 0.0;
    [$pin, $pout] = ks_tb_prices();
    $chars = array_sum(array_map('mb_strlen', $titles));
    return (($chars / 4 + 1.5 * count($titles)) * $pin + 27 * count($titles) * $pout) / 1e6 * KONKURSI_USD_TO_EUR;
}

/**
 * Atbrīvo darba virsrakstus atpakaļ rindā un pieraksta stāvokli — VIENĀ transakcijā,
 * stāvoklis pēdējais. Ja procesu nokauj pusceļā, darbs paliek ne-termināls un
 * nākamā savākšana to pabeidz. Pēc izvēles kāpina kļūmju skaitītāju (sistemātiska
 * kļūme tad eskalē 40 → 10 → 1 → stop) un pieskaita aplēsto tēriņu budžetam
 * (Google par pabeigtiem pieprasījumiem var rēķināt arī neizdevušā darbā —
 * pārvērtēt ir drošāk nekā nepamanīt).
 */
function ks_tb_release(PDO $pdo, int $bid, string $state, ?string $note = null,
                       bool $bump = false, bool $chargeEstimate = false): int
{
    $titles = $pdo->query("SELECT title FROM translate_batch_titles WHERE batch_id=" . (int)$bid)->fetchAll(PDO::FETCH_COLUMN);
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        if ($bump && $titles) {
            $b = $pdo->prepare("INSERT INTO translate_batch_fails (title, fails, updated) VALUES (?, 1, ?)
                                ON CONFLICT(title) DO UPDATE SET fails = fails + 1, updated = excluded.updated");
            foreach ($titles as $t) $b->execute([$t, date('c')]);
        }
        if ($chargeEstimate && $titles) {
            $est = (float)$pdo->query("SELECT est_eur FROM translate_batches WHERE id=" . (int)$bid)->fetchColumn();
            if ($est > 0) konkursi_meta_add($pdo, 'translate_paid_spend_' . konkursi_today(), $est);
        }
        $pdo->prepare("DELETE FROM translate_batch_titles WHERE batch_id=?")->execute([$bid]);
        $pdo->prepare("UPDATE translate_batches SET state=?, note=COALESCE(?, note), updated=? WHERE id=?")
            ->execute([$state, $note, date('c'), $bid]);
        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    return count($titles);
}

/** Viena darba stāvoklis no API. 'ok' = nolasījums uzticams. */
function ks_tb_state(string $job): array
{
    $r = ks_tb_api($job, null, 120);
    $state = $r['ok'] ? (string)($r['json']['metadata']['state'] ?? '') : '';
    return ['ok' => $r['ok'] && $state !== '', 'code' => $r['code'], 'state' => $state, 'json' => $r['json'], 'err' => $r['err']];
}

/**
 * Iesniedz vienu darbu. Atgriež ['job'=>…, 'requests'=>…, 'titles'=>…] vai
 * ['job'=>null, 'why'=>…], ja nav ko sūtīt vai budžets neļauj.
 */
function ks_translate_batch_submit(PDO $pdo, ?int $max = null): array
{
    ks_tb_schema($pdo);

    // Cenu konstantes ir par KONKRĒTU modeli. Ja klients pārslēgts uz citu (vides
    // mainīgais REG_GEMINI_MODEL), budžeta sargs rēķina ar nepareizām cenām — sakām
    // skaļi, bet nebloķējam (A/B mehānisms to dara apzināti).
    if (defined('KONKURSI_GEMINI_PRICED_MODEL') && reg_gemini_model() !== KONKURSI_GEMINI_PRICED_MODEL) {
        ks_tb_log(sprintf('  ⚠ Batch: modelis %s, bet cenu konstantes ir par %s — budžeta aplēse var būt nepareiza.',
            reg_gemini_model(), KONKURSI_GEMINI_PRICED_MODEL));
    }

    // Latviskie avoti jau IR latviski — kopē bez API (tas pats, ko dara tūlītējais ceļš).
    $lvCopied = (int)$pdo->exec(
        "UPDATE notices SET title_lv = title
         WHERE title_lv IS NULL AND source IN ('IUB','MODTI','RSTI','ASTI','LDZ')");
    if ($lvCopied > 0) ks_tb_log("  ⧉ Batch: $lvCopied LV avotu virsraksti nokopēti bez API.");

    $max = $max ?? KONKURSI_TRANSLATE_MAX_RUN;
    $st = $pdo->prepare(
        "SELECT title, MAX(COALESCE(publication_date, first_seen, '')) mp
           FROM notices
          WHERE title_lv IS NULL AND title IS NOT NULL AND title != ''
            AND title NOT IN (SELECT title FROM translate_batch_titles)
          GROUP BY title ORDER BY mp DESC LIMIT " . (int)$max);
    $st->execute();
    $titles = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!$titles) return ['job' => null, 'why' => 'nav netulkotu virsrakstu'];

    // Dienas budžets = savāktais (meta) + iesniegtais, vēl nesavāktais (est_eur).
    // Bez otrā saskaitāmā divas iesniegšanas pēc kārtas katra redzētu pilnu atlikumu.
    $metaK = 'translate_paid_spend_' . konkursi_today();
    $cut = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-30 days')->format('Y-m-d');
    $pdo->exec("DELETE FROM meta WHERE k LIKE 'translate_paid_spend_%' AND k < 'translate_paid_spend_$cut'");
    $spent = (float)(konkursi_meta_get($pdo, $metaK) ?? '0');
    $termQ = "'" . implode("','", KS_TB_TERMINAL) . "'";
    $inFlight = (float)$pdo->query("SELECT COALESCE(SUM(est_eur),0) FROM translate_batches WHERE state NOT IN ($termQ)")->fetchColumn();
    $budgetLeft = KONKURSI_TRANSLATE_PAID_DAILY_EUR - $spent - $inFlight;
    if ($budgetLeft <= 0) {
        return ['job' => null, 'why' => sprintf('dienas budžets €%.2f jau iztērēts (savākts €%.2f, ceļā €%.2f)',
            KONKURSI_TRANSLATE_PAID_DAILY_EUR, $spent, $inFlight)];
    }
    $perTitle = ks_tb_estimate($titles) / count($titles);
    $fit = (int)floor($budgetLeft / max(1e-9, $perTitle));
    if ($fit < count($titles)) {
        $titles = array_slice($titles, 0, max(0, $fit));
        if (!$titles) return ['job' => null, 'why' => 'budžeta atlikums nesedz nevienu virsrakstu'];
        ks_tb_log(sprintf('  ⛔ Batch: budžeta atlikums €%.2f — šoreiz sūtu %d virsrakstus.', $budgetLeft, count($titles)));
    }

    // Paketes lielums pēc iepriekšējām kļūmēm: 0 → 40, 1 → 10, 2+ → 1.
    $failMap = [];
    $inQ = implode(',', array_fill(0, count($titles), '?'));
    $fq = $pdo->prepare("SELECT title, fails FROM translate_batch_fails WHERE title IN ($inQ)");
    $fq->execute($titles);
    foreach ($fq->fetchAll(PDO::FETCH_ASSOC) as $r) $failMap[(string)$r['title']] = (int)$r['fails'];

    $buckets = [0 => [], 1 => [], 2 => []]; $overLimit = 0;
    foreach ($titles as $t) {
        $f = $failMap[$t] ?? 0;
        if ($f >= KONKURSI_TRANSLATE_BATCH_MAX_FAILS) { $overLimit++; continue; }   // pārtraucam dedzināt naudu
        $buckets[$f >= 2 ? 2 : $f][] = $t;
    }
    if ($overLimit > 0) ks_tb_log("  ⧉ Batch: $overLimit virsraksti pārsnieguši kļūmju limitu — netiek sūtīti (sk. --status).");
    $sizes  = [0 => KONKURSI_TRANSLATE_BATCH, 1 => 10, 2 => 1];
    $chunks = [];
    foreach ($buckets as $lvl => $list) {
        if (!$list) continue;
        foreach (array_chunk($list, $sizes[$lvl]) as $c) $chunks[] = $c;
    }
    if (!$chunks) return ['job' => null, 'why' => 'visi atlikušie virsraksti pārsnieguši kļūmju limitu'];
    $titles = array_merge(...$chunks);
    $requests = [];
    foreach ($chunks as $i => $chunk) {
        $requests[] = [
            'request'  => [
                'contents'         => [['parts' => [['text' => reg_gemini_titles_prompt($chunk)]]]],
                'generationConfig' => reg_gemini_gen_config(reg_gemini_titles_opts()),
            ],
            // Atslēga IEKŠ metadata, ne blakus 'request' — citādi HTTP 400 (mērīts 2026-09-03).
            'metadata' => ['key' => 'c' . $i],
        ];
    }

    // NODOMA IERAKSTS PIRMS POST. Unikāls display_name ļauj darbu atpazīt API sarakstā,
    // ja POST beidzas neskaidri (taimauts pēc tam, kad Google darbu jau izveidojis).
    $display = 'konkursi-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8);
    $est     = ks_tb_estimate($titles);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO translate_batches (job, state, model, requests, titles, created, est_eur, display_name)
                       VALUES (?, 'SUBMITTING', ?, ?, ?, ?, ?, ?)")
            ->execute(['pending:' . $display, reg_gemini_model(), count($requests), count($titles), date('c'), $est, $display]);
        $bid = (int)$pdo->lastInsertId();
        $it = $pdo->prepare("INSERT OR IGNORE INTO translate_batch_titles (batch_id, chunk, pos, title) VALUES (?,?,?,?)");
        foreach ($chunks as $ci => $chunk) {
            foreach ($chunk as $pi => $t) $it->execute([$bid, $ci, $pi, $t]);
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

    $r = ks_tb_api('models/' . reg_gemini_model() . ':batchGenerateContent', [
        'batch' => ['display_name' => $display, 'input_config' => ['requests' => ['requests' => $requests]]],
    ]);
    if ($r['ok'] && !empty($r['json']['name'])) {
        $job = (string)$r['json']['name'];
        // Žurnālā PIRMS DB — ja UPDATE krīt, darba vārds tomēr ir kaut kur pierakstīts.
        ks_tb_log(sprintf('  📦 Batch: iesniegts %s (%s) — %d paketes, %d virsraksti, aplēse €%.4f.',
            $job, $display, count($requests), count($titles), $est));
        $pdo->prepare("UPDATE translate_batches SET job=?, state=?, updated=? WHERE id=?")
            ->execute([$job, (string)($r['json']['metadata']['state'] ?? 'BATCH_STATE_PENDING'), date('c'), $bid]);
        return ['job' => $job, 'requests' => count($requests), 'titles' => count($titles)];
    }
    if ($r['code'] >= 400) {
        // Serveris pieprasījumu noraidīja — darba nav, virsrakstus atbrīvojam.
        ks_tb_release($pdo, $bid, 'REJECTED', 'HTTP ' . $r['code'] . ': ' . mb_substr(preg_replace('/\s+/', ' ', $r['raw']), 0, 160));
        ks_tb_log('  ⚠ Batch: iesniegšana noraidīta (HTTP ' . $r['code'] . ') ' . mb_substr(preg_replace('/\s+/', ' ', $r['raw']), 0, 200));
        return ['job' => null, 'why' => 'HTTP ' . $r['code']];
    }
    // Neskaidrs iznākums (taimauts, tīkls): darbs Google pusē VAR būt izveidots. Rindu
    // atstājam SUBMITTING — nākamā savākšana to meklēs API sarakstā pēc display_name.
    ks_tb_log("  ⚠ Batch: iesniegšanas iznākums neskaidrs ($display, curl: {$r['err']}) — saskaņošu nākamajā palaišanā.");
    return ['job' => null, 'why' => 'neskaidrs iznākums: ' . $r['err']];
}

/**
 * Saskaņo SUBMITTING rindas ar API: ja darbs ar tādu display_name eksistē — pārņem;
 * ja pēc 30 min nav — atbrīvo. Bez tā darbs, ko izveidoja taimautā beidzies POST,
 * būtu apmaksāts, nesavākts, un tā virsraksti iesniegti vēlreiz.
 */
function ks_tb_reconcile(PDO $pdo): void
{
    $rows = $pdo->query("SELECT id, display_name, created FROM translate_batches WHERE state='SUBMITTING'")->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return;
    $byName = null;
    foreach ($rows as $b) {
        $age = time() - strtotime((string)$b['created']);
        if ($byName === null) {
            $byName = [];
            $r = ks_tb_api('batches?pageSize=100', null, 60);
            if ($r['ok']) foreach ((array)($r['json']['operations'] ?? []) as $op) {
                $dn = (string)($op['metadata']['displayName'] ?? $op['metadata']['display_name'] ?? '');
                if ($dn !== '') $byName[$dn] = ['name' => (string)($op['name'] ?? ''), 'state' => (string)($op['metadata']['state'] ?? '')];
            } else {
                ks_tb_log('  ⚠ Batch: darbu sarakstu no API neizdevās nolasīt (HTTP ' . $r['code'] . ') — SUBMITTING rindas pagaidām atstāju.');
                return;
            }
        }
        $hit = $byName[(string)$b['display_name']] ?? null;
        if ($hit && $hit['name'] !== '') {
            $pdo->prepare("UPDATE translate_batches SET job=?, state=?, updated=?, note='pārņemts pēc saskaņošanas' WHERE id=?")
                ->execute([$hit['name'], $hit['state'] ?: 'BATCH_STATE_PENDING', date('c'), (int)$b['id']]);
            ks_tb_log("  ↻ Batch: darbs {$hit['name']} atrasts API pēc {$b['display_name']} — pārņemts.");
        } elseif ($age > 1800) {
            $n = ks_tb_release($pdo, (int)$b['id'], 'RELEASED', 'SUBMITTING bez darba API pusē pēc 30 min');
            ks_tb_log("  ⚠ Batch: {$b['display_name']} API sarakstā nav — $n virsraksti atgriezti rindā.");
        }
    }
}

/**
 * Savāc visus pabeigtos darbus. Atgriež ['written'=>…, 'jobs'=>…, 'eur'=>…].
 */
function ks_translate_batch_collect(PDO $pdo): array
{
    ks_tb_schema($pdo);
    $termQ = "'" . implode("','", KS_TB_TERMINAL) . "'";

    // Pašdziedināšana: virsraksti bez dzīva vecāka (darbs termināls vai izdzēsts)
    // nedrīkst palikt "atvērtā darbā" — tos vairs nekas netulkotu.
    $orph = $pdo->exec("DELETE FROM translate_batch_titles WHERE batch_id NOT IN
                        (SELECT id FROM translate_batches WHERE state NOT IN ($termQ))");
    if ($orph > 0) ks_tb_log("  ↻ Batch: $orph bāreņu virsraksti atbrīvoti (darbs jau bija noslēgts).");

    ks_tb_reconcile($pdo);

    $open = $pdo->query("SELECT * FROM translate_batches
                          WHERE state NOT IN ($termQ) AND state != 'SUBMITTING'
                          ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $totWritten = 0; $totEur = 0.0; $jobs = 0;

    foreach ($open as $b) {
        try {
            $res = ks_tb_collect_one($pdo, $b);
            if ($res !== null) { $totWritten += $res['written']; $totEur += $res['eur']; $jobs++; }
        } catch (Throwable $e) {
            // Viena darba kļūme nedrīkst apturēt pārējo savākšanu.
            ks_tb_log("  ⚠ Batch: darba {$b['job']} savākšana krita: " . $e->getMessage());
        }
    }
    return ['written' => $totWritten, 'jobs' => $jobs, 'eur' => $totEur];
}

/**
 * Izlemj, ko ar vienas paketes atbildi darīt. Atgriež:
 *  write     — [[oriģināls, tulkojums], …] pozīcijas, ko rakstīt;
 *  bump      — oriģināli, kam pieskaitīt kļūmi (tukšs tulkojums, sadursme, skaita
 *              nesakritība vai kļūdaina atbilde);
 *  conflicts — cik no bump ir saskanības sadursmes.
 * Sadursme (vienāds tulkojums diviem dažādiem oriģināliem) ir NOBĪDES pazīme: modelis
 * 6. pozīcijā atkārtojis 5., tālāk viss +1 un beigās viens izlaists — skaits sakrīt,
 * bet 7.–40. dabūtu kaimiņa tulkojumu, un WHERE title_lv IS NULL to vairs nelabotu.
 * Tāpēc pakete krīt visa (mazākā izdosies). Naktī 2026-09-05 12 no 107 paketēm krita
 * par gandrīz identiskiem oriģināliem — to labo reg_gemini_titles_alike pielaide, ne
 * lemšana pa virsrakstiem. $chunk atslēgas ir pozīcijas — tās sakārto.
 */
function ks_tb_decide(array $chunk, ?array $lv): array
{
    ksort($chunk);
    $orig = array_values($chunk);
    if ($lv === null || count($lv) !== count($orig)) {
        return ['write' => [], 'bump' => $orig, 'conflicts' => 0];
    }
    $lv = array_values($lv);
    $conf = reg_gemini_titles_conflicts($orig, $lv);
    if ($conf !== []) return ['write' => [], 'bump' => $orig, 'conflicts' => count($conf)];
    $write = []; $bump = [];
    foreach ($orig as $i => $o) {
        $t = trim((string)$lv[$i]);
        if ($t === '') { $bump[] = $o; continue; }          // tukšs = kļūme šim virsrakstam
        $write[] = [$o, $t];
    }
    return ['write' => $write, 'bump' => $bump, 'conflicts' => 0];
}

/** Viena darba savākšana. null = vēl nav ko savākt (rit, vai nolasīšana neizdevās). */
function ks_tb_collect_one(PDO $pdo, array $b): ?array
{
    $bid = (int)$b['id'];
    $age = time() - strtotime((string)$b['created']);
    $s = ks_tb_state((string)$b['job']);

    if (!$s['ok']) {
        // 404 = darbs vairs neeksistē; 400 INVALID_ARGUMENT = mūsu pierakstītais darba
        // vārds ir nederīgs (mērīts: nepareizs formāts dod 400, nevis 404). Abos
        // gadījumos nolasīt nekad neizdosies — atbrīvojam, ne gaidām 30 h.
        $apiStatus = (string)($s['json']['error']['status'] ?? '');
        if ($s['code'] === 404 || ($s['code'] === 400 && $apiStatus === 'INVALID_ARGUMENT')) {
            $n = ks_tb_release($pdo, $bid, 'RELEASED', "API {$s['code']} $apiStatus — darbs nav sasniedzams", true, true);
            ks_tb_log("  ⚠ Batch: darbs {$b['job']} API pusē nav sasniedzams ({$s['code']} $apiStatus) — $n virsraksti atgriezti rindā.");
            return null;
        }
        // Pārejoša vai pastāvīga nolasīšanas kļūda: NEatbrīvojam (darbs var būt gatavs,
        // un tā rezultāti tad tiktu izmesti, bet virsraksti apmaksāti otrreiz). Pēc 30 h
        // gan pārtraucam — atceļam un atbrīvojam.
        if ($age > 30 * 3600) {
            ks_tb_api($b['job'] . ':cancel', null, 60, 'POST');
            $n = ks_tb_release($pdo, $bid, 'RELEASED', sprintf('stāvokli nevarēja nolasīt %.0f h (HTTP %d %s)', $age / 3600, $s['code'], $s['err']), true, true);
            ks_tb_log("  ⚠ Batch: darbs {$b['job']} nav nolasāms >30 h — atcelts, $n virsraksti atgriezti rindā.");
        } else {
            ks_tb_log(sprintf('  ⚠ Batch: stāvokli neizdevās nolasīt (HTTP %d %s) %s — %d virsraksti pagaidām bloķēti.',
                $s['code'], $s['err'], $b['job'], (int)$b['titles']));
        }
        return null;
    }

    $state = $s['state'];
    if (in_array($state, KS_TB_LIVE, true)) {
        $pdo->prepare("UPDATE translate_batches SET state=?, updated=? WHERE id=?")->execute([$state, date('c'), $bid]);
        if ($age > 30 * 3600) {
            // Dokumentētais mērķis ir 24 h. Ilgāk nav jēgas gaidīt: atceļam un atbrīvojam.
            ks_tb_api($b['job'] . ':cancel', null, 60, 'POST');
            $n = ks_tb_release($pdo, $bid, 'BATCH_STATE_CANCELLED', sprintf('atcelts pēc %.0f h %s', $age / 3600, $state), true, true);
            ks_tb_log("  ⚠ Batch: darbs {$b['job']} joprojām $state pēc 30 h — atcelts, $n virsraksti atgriezti rindā.");
        } elseif ($age > 26 * 3600) {
            ks_tb_log(sprintf('  ⚠ Batch: darbs %s joprojām %s pēc %.0f h — %d virsraksti bloķēti.', $b['job'], $state, $age / 3600, (int)$b['titles']));
        }
        return null;
    }

    if ($state !== 'BATCH_STATE_SUCCEEDED') {
        // FAILED / EXPIRED / CANCELLED: rezultātu nav, virsraksti atpakaļ rindā ar kļūmes
        // atzīmi (sistemātiska kļūme eskalē līdz apstāšanās), aplēstais tēriņš budžetā.
        $n = ks_tb_release($pdo, $bid, $state, null, true, true);
        ks_tb_log("  ⚠ Batch: darbs {$b['job']} beidzās ar $state — $n virsraksti atgriezti rindā.");
        return null;
    }

    $out = $s['json']['metadata']['output'] ?? [];
    $responses = $out['inlinedResponses']['inlinedResponses'] ?? null;
    if (!is_array($responses)) {
        $why = !empty($out['responsesFile']) ? 'atbildes atdotas kā fails (' . $out['responsesFile'] . ')' : 'atbildes nav atrodamas';
        $n = ks_tb_release($pdo, $bid, 'UNREADABLE', $why, true, true);
        ks_tb_log("  ⚠ Batch: darbs {$b['job']} SUCCEEDED, bet $why — $n virsraksti atgriezti rindā.");
        return null;
    }

    $rows = $pdo->prepare("SELECT chunk, pos, title FROM translate_batch_titles WHERE batch_id=? ORDER BY chunk, pos");
    $rows->execute([$bid]);
    $byChunk = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) $byChunk[(int)$r['chunk']][(int)$r['pos']] = (string)$r['title'];
    if (!$byChunk) {
        // Virsrakstu vairs nav (atbrīvoti agrāk) — rezultātus lietot nevar, bet tēriņš ir īsts.
        $in = 0; $outTok = 0;
        foreach ($responses as $resp) { $u = $resp['response']['usageMetadata'] ?? []; $in += (int)($u['promptTokenCount'] ?? 0); $outTok += (int)($u['candidatesTokenCount'] ?? 0); }
        [$pin, $pout] = ks_tb_prices();
        $eur = ($in / 1e6 * $pin + $outTok / 1e6 * $pout) * KONKURSI_USD_TO_EUR;
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE translate_batches SET state='COLLECTED', written=0, in_tokens=?, out_tokens=?, eur=?, updated=?, note='virsraksti jau bija atbrīvoti — rezultāti neizmantoti' WHERE id=?")
                ->execute([$in, $outTok, $eur, date('c'), $bid]);
            konkursi_meta_add($pdo, 'translate_paid_spend_' . konkursi_today(), $eur);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        ks_tb_log("  ⚠ Batch: darbs {$b['job']} SUCCEEDED, bet virsraksti jau bija atbrīvoti — €" . sprintf('%.4f', $eur) . ' pierakstīti, rezultāti neizmantoti.');
        return ['written' => 0, 'eur' => $eur];
    }

    $upd = $pdo->prepare('UPDATE notices SET title_lv = ? WHERE title = ? AND title_lv IS NULL');
    $written = 0; $skipped = 0; $conflicts = 0; $in = 0; $outTok = 0;
    $toBump = [];    // kļūmes pierakstām VIENREIZ gala transakcijā (ne pa ceļam)
    $toClear = [];

    foreach ($responses as $resp) {
        $key = (string)($resp['metadata']['key'] ?? '');
        if (!preg_match('/^c(\d+)$/', $key, $m)) { $skipped++; continue; }
        $chunk = $byChunk[(int)$m[1]] ?? null;
        if ($chunk === null) { $skipped++; continue; }

        $u = $resp['response']['usageMetadata'] ?? [];
        $in     += (int)($u['promptTokenCount'] ?? 0);
        $outTok += (int)($u['candidatesTokenCount'] ?? 0) + (int)($u['thoughtsTokenCount'] ?? 0);

        $fin = (string)($resp['response']['candidates'][0]['finishReason'] ?? '');
        $txt = (string)($resp['response']['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $lv  = (isset($resp['error']) || ($fin !== '' && $fin !== 'STOP')) ? null : reg_gemini_titles_parse($txt, count($chunk));
        // Skaits UN saskanība: 39 no 40 sabīda tulkojumus; vienāds tulkojums diviem
        // atšķirīgiem virsrakstiem ir nobīdes pazīme — abos gadījumos krīt visa pakete
        // (sk. ks_tb_decide); gandrīz identiskus oriģinālus pielaiž.
        $d = ks_tb_decide($chunk, $lv);
        foreach ($d['bump'] as $orig) $toBump[$orig] = true;
        $skipped += count($d['bump']); $conflicts += $d['conflicts'];
        if (!$d['write']) continue;
        $pdo->beginTransaction();
        try {
            foreach ($d['write'] as [$orig, $t]) {
                $upd->execute([$t, $orig]);
                $toClear[$orig] = true;
                $written++;
            }
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    [$pin, $pout] = ks_tb_prices();
    $eur = ($in / 1e6 * $pin + $outTok / 1e6 * $pout) * KONKURSI_USD_TO_EUR;
    $pdo->beginTransaction();
    try {
        $bump = $pdo->prepare("INSERT INTO translate_batch_fails (title, fails, updated) VALUES (?, 1, ?)
                               ON CONFLICT(title) DO UPDATE SET fails = fails + 1, updated = excluded.updated");
        foreach (array_keys($toBump) as $t) $bump->execute([$t, date('c')]);
        $clear = $pdo->prepare("DELETE FROM translate_batch_fails WHERE title = ?");
        foreach (array_keys($toClear) as $t) $clear->execute([$t]);
        $pdo->prepare("DELETE FROM translate_batch_titles WHERE batch_id=?")->execute([$bid]);
        $pdo->prepare("UPDATE translate_batches SET state='COLLECTED', written=?, in_tokens=?, out_tokens=?, eur=?, updated=?, note=? WHERE id=?")
            ->execute([$written, $in, $outTok, $eur, date('c'), $skipped > 0 ? "izlaisti: $skipped" . ($conflicts > 0 ? " (sadursmes $conflicts)" : '') : null, $bid]);
        // Tēriņš tajā pašā transakcijā, kur COLLECTED, un atomāri (ne lasi-raksti).
        konkursi_meta_add($pdo, 'translate_paid_spend_' . konkursi_today(), $eur);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

    ks_tb_log(sprintf('  ✅ Batch: %s savākts — %d tulkojumi%s, €%.4f (ievade %d, izvade %d).',
        $b['job'], $written, $skipped > 0 ? ", izlaisti $skipped" . ($conflicts > 0 ? " (sadursmes $conflicts)" : '') : '', $eur, $in, $outTok));
    return ['written' => $written, 'eur' => $eur];
}

/**
 * Pilnais gājiens sinhronizācijai: savāc iepriekšējos → iesniedz jaunu →
 * pagaida līdz $wait sekundēm → savāc. Atgriež pierakstīto tulkojumu skaitu.
 */
function ks_translate_batch_run(PDO $pdo, int $wait = 600, ?int $max = null): int
{
    $client = __DIR__ . '/../../registrs/mi/gemini_client.php';
    if (!is_file($client)) { ks_tb_log('  ⚠ Batch: nav atrasts registrs/mi/gemini_client.php.'); return 0; }
    require_once $client;

    $why = null;
    $lock = ks_tb_lock($why);
    if ($lock === null) { ks_tb_log("  ⧉ Batch: $why — izlaižu."); return 0; }

    try {
        $written = ks_translate_batch_collect($pdo)['written'];
        $sub = ks_translate_batch_submit($pdo, $max);
        if ($sub['job'] === null) {
            if (($sub['why'] ?? '') !== 'nav netulkotu virsrakstu') ks_tb_log('  ⧉ Batch: ' . $sub['why']);
            return $written;
        }
        // Gaidīšana: nolasīšanas kļūda (429, tīkls) nozīmē "vēl nezinām", ne "gatavs" —
        // gaidām tālāk līdz $wait. Cilpa var pārsniegt $wait par vienu GET (≤120 s).
        $t0 = time();
        while (time() - $t0 < $wait) {
            sleep(15);
            $s = ks_tb_state($sub['job']);
            if ($s['ok'] && !in_array($s['state'], KS_TB_LIVE, true)) break;
            if (function_exists('ks_stop_requested') && ks_stop_requested()) break;
        }
        $written += ks_translate_batch_collect($pdo)['written'];
        return $written;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
