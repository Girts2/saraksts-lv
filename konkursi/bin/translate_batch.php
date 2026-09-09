<?php
/**
 * konkursi/bin/translate_batch.php — CLI Batch tulkošanas konveijeram.
 *
 * Loģika dzīvo konkursi/lib/translate_batch.php; šis ir tikai ieeja rokas
 * palaišanai, testiem un cron'am. Sinhronizācija to pašu bibliotēku sauc pati,
 * ja meta 'translate_mode' = 'batch'.
 *
 *   php konkursi/bin/translate_batch.php --status            # atvērtie darbi
 *   php konkursi/bin/translate_batch.php --submit [--n=200]  # iesniedz vienu darbu
 *   php konkursi/bin/translate_batch.php --collect           # savāc pabeigtos
 *   php konkursi/bin/translate_batch.php --run [--wait=600]  # savāc → iesniedz → gaida → savāc
 *   php konkursi/bin/translate_batch.php --ieslegt|--izslegt # translate_mode slēdzis
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_time_limit(0);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/translate_batch.php';
$client = __DIR__ . '/../../registrs/mi/gemini_client.php';
if (!is_file($client)) { fwrite(STDERR, "✗ Nav atrasts $client\n"); exit(1); }
require_once $client;

$pdo = konkursi_db();
$n = null; $wait = 600;
foreach ($argv as $a) {
    if (preg_match('/^--n=(\d+)$/', $a, $m))    $n = max(1, (int)$m[1]);
    if (preg_match('/^--wait=(\d+)$/', $a, $m)) $wait = (int)$m[1];
}
$has = fn(string $f): bool => in_array($f, $argv, true);

if ($has('--ieslegt') || $has('--izslegt')) {
    $on = $has('--ieslegt');
    konkursi_meta_set($pdo, 'translate_mode', $on ? 'batch' : 'immediate');
    echo 'translate_mode = ', $on ? 'batch' : 'immediate', "\n";
    if ($on) echo "Sinhronizācija turpmāk tulkos caur Batch API (−50 %).\n";
    exit(0);
}

if ($has('--status') || $argc === 1) {
    ks_tb_schema($pdo);
    $mode = konkursi_meta_get($pdo, 'translate_mode') ?: 'immediate';
    $onSync = konkursi_meta_get($pdo, 'translate_on_sync') === '1' ? 'jā' : 'nē';
    echo "translate_mode:    $mode\n";
    echo "tulko sinhronizācijā: $onSync\n";
    echo "modelis:           ", reg_gemini_model(), "\n";
    printf("cena (batch):      \$%.3f / \$%.3f par 1M tokenu\n", ...ks_tb_prices());
    $today = 'translate_paid_spend_' . konkursi_today();
    printf("šodien iztērēts:   €%.4f no €%.2f\n",
        (float)(konkursi_meta_get($pdo, $today) ?? '0'), KONKURSI_TRANSLATE_PAID_DAILY_EUR);
    $left = (int)$pdo->query("SELECT COUNT(DISTINCT title) FROM notices
                              WHERE title_lv IS NULL AND title IS NOT NULL AND title != ''")->fetchColumn();
    $inflight = (int)$pdo->query("SELECT COUNT(*) FROM translate_batch_titles")->fetchColumn();
    echo "netulkoti unikāli: $left (no tiem atvērtos darbos: $inflight)\n";
    $f = $pdo->query("SELECT fails, COUNT(*) n FROM translate_batch_fails GROUP BY fails ORDER BY fails")->fetchAll(PDO::FETCH_ASSOC);
    if ($f) {
        $parts = [];
        foreach ($f as $r) $parts[] = $r['fails'] . '× kritušas: ' . $r['n'];
        echo 'kļūmju vēsture:    ', implode(' · ', $parts),
             ' (limits ' . KONKURSI_TRANSLATE_BATCH_MAX_FAILS . ")\n";
    }
    echo "\n";
    $rows = $pdo->query("SELECT * FROM translate_batches ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) { echo "Darbu vēl nav.\n"; exit(0); }
    printf("%-42s %-24s %5s %6s %7s %9s\n", 'Darbs', 'Stāvoklis', 'Pak.', 'Virsr.', 'Ierakst.', 'EUR');
    foreach ($rows as $r) {
        printf("%-42s %-24s %5d %6d %7d %9.4f%s\n", $r['job'], $r['state'],
            $r['requests'], $r['titles'], $r['written'], $r['eur'],
            $r['note'] ? '  (' . $r['note'] . ')' : '');
    }
    exit(0);
}

// --submit un --collect bez slēdzenes varētu sadurties ar sinhronizāciju: tie paši
// virsraksti divos darbos, vai viens darbs savākts divreiz (tēriņš divreiz).
if ($has('--submit') || $has('--collect')) {
    $why = null;
    $lock = ks_tb_lock($why);
    if ($lock === null) { fwrite(STDERR, "✗ $why\n"); exit(1); }
}
if ($has('--submit')) {
    $r = ks_translate_batch_submit($pdo, $n);
    if ($r['job'] === null) { echo "Nekas netika iesniegts: ", $r['why'] ?? '?', "\n"; exit(0); }
    printf("Iesniegts: %s (%d paketes, %d virsraksti)\n", $r['job'], $r['requests'], $r['titles']);
    exit(0);
}

if ($has('--collect')) {
    $r = ks_translate_batch_collect($pdo);
    printf("Savākti %d darbi, pierakstīti %d tulkojumi, €%.4f\n", $r['jobs'], $r['written'], $r['eur']);
    exit(0);
}

if ($has('--run')) {
    $t0 = microtime(true);
    $w = ks_translate_batch_run($pdo, $wait, $n);
    printf("Gatavs: %d tulkojumi %.0f s laikā\n", $w, microtime(true) - $t0);
    exit(0);
}

fwrite(STDERR, "Nezināma komanda. Sk. faila galvu.\n");
exit(1);
