<?php
/**
 * konkursi/bin/translate_titles.php — vēsturisko virsrakstu LV tulkošanas aizpilde.
 *
 * Gemini API kods/atslēga dzīvo TIKAI Reģistra sadaļā (registrs/mi/gemini_client.php);
 * šis skripts to tikai izsauc. Unikālos virsrakstus tulko vienreiz; atsākams (tulko
 * tikai title_lv IS NULL). Ikdienas jaunos tulko sync (ja slēdzis ieslēgts) — šis ir
 * vienreizējai vēstures aizpildei.
 *
 * Lietošana:
 *   php konkursi/bin/translate_titles.php               # sausā: apjoms + izmaksu aplēse
 *   php konkursi/bin/translate_titles.php --apply       # tulko VISU atlikušo
 *   php konkursi/bin/translate_titles.php --apply --limit=200   # tests: tikai 200 virsraksti
 *   php konkursi/bin/translate_titles.php --apply --immediate   # tūlītējais ceļš arī batch režīmā
 *
 * Ja meta 'translate_mode' = 'batch', tulko caur Gemini Batch API (−50 %) — tas pats
 * ceļš, ko lieto sinhronizācija. Šo skriptu palaiž arī admin panelis («konkursi.translate»),
 * un līdz 2026-09-04 tas vienmēr gāja pa tūlītējo ceļu pilnā cenā, apejot slēdzi.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/sync_engine.php';
// Klients vajadzīgs jau aplēsei (REG_GEMINI_MODEL, reg_gemini_thinking_min) — bet
// ar savu kļūdas ziņu, nevis PHP fatal, ja fails pazudis.
$client = __DIR__ . '/../../registrs/mi/gemini_client.php';
if (!is_file($client)) { fwrite(STDERR, "✗ Nav atrasts $client\n"); exit(1); }
require_once $client;

set_time_limit(0);

$apply = in_array('--apply', $argv, true);
$immediate = in_array('--immediate', $argv, true);
$limit = null;
foreach ($argv as $a) if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int)$m[1];

$pdo = konkursi_db();

$stat = $pdo->query(
    "SELECT COUNT(DISTINCT title) uniq, COUNT(*) rows_all, SUM(LENGTH(title)) chars_all
     FROM notices WHERE title_lv IS NULL AND title IS NOT NULL AND title != ''"
)->fetch();
$uniq = (int)$stat['uniq'];
$translated = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE title_lv IS NOT NULL")->fetchColumn();

echo "Netulkotas rindas:      " . (int)$stat['rows_all'] . "\n";
echo "Unikāli virsraksti:     $uniq  (tulko tikai unikālos — vienādos kopē)\n";
echo "Jau iztulkotas rindas:  $translated\n";

// Izmaksu aplēse: ~4 rakstz./tokens latīņu, ~2 ne-latīņu (pieņem 89/11 sadalījumu);
// izvade ~27 tokeni/virsraksts. Cenas nāk no konstantēm, lai tās nevar atpalikt no
// REG_GEMINI_MODEL (agrāk te bija iekodēti $0.50/$3.00).
$uniqChars = (int)$pdo->query(
    "SELECT COALESCE(SUM(len),0) FROM (SELECT LENGTH(MIN(title)) len FROM notices
     WHERE title_lv IS NULL AND title IS NOT NULL AND title != '' GROUP BY title)"
)->fetchColumn();
$inTok  = $uniqChars * 0.89 / 4 + $uniqChars * 0.11 / 2 + $uniq * 1.5;
$outTok = $uniq * 27;
$useBatch = !$immediate && konkursi_meta_get($pdo, 'translate_mode') === 'batch';
$mult = $useBatch ? KONKURSI_GEMINI_BATCH_MULT : 1.0;
$cost   = ($inTok / 1e6 * KONKURSI_GEMINI_IN_USD_1M + $outTok / 1e6 * KONKURSI_GEMINI_OUT_USD_1M) * $mult;
printf("Aplēse: ~%.1fM ievades + ~%.1fM izvades tokeni → ~USD %.2f (modelis %s, %s, %s)\n",
    $inTok / 1e6, $outTok / 1e6, $cost, reg_gemini_model(),
    array_key_first(reg_gemini_thinking_min()) === 'thinkingBudget'
        ? 'domāšana izslēgta' : 'domāšana zemākajā līmenī — nedaudz vairāk',
    $useBatch ? 'Batch API −50 %' : 'tūlītējais ceļš, pilna cena');

if (!$apply) {
    echo "\nSausā palaišana — nekas netiek tulkots. Palaid ar --apply, lai tulkotu.\n";
    exit(0);
}

// Dubultās tulkošanas sardze (2026-08-02 mācība): ja sinhronizācija šobrīd rit UN
// translate_on_sync=1, sync beigās tulkos pats — paralēla palaišana API izmaksas
// par pārklājumu dubultotu. Apzinātai paralēlai palaišanai lieto --force.
$sync_lock = __DIR__ . '/../data/sync.lock';
$sync_running = false;
if (is_file($sync_lock)) {
    $lfp = @fopen($sync_lock, 'r');
    if ($lfp) {
        if (!flock($lfp, LOCK_EX | LOCK_NB)) { $sync_running = true; }
        else { flock($lfp, LOCK_UN); }
        fclose($lfp);
    }
}
if ($sync_running && konkursi_meta_get($pdo, 'translate_on_sync') === '1'
        && !in_array('--force', $argv, true)) {
    echo "\n⚠ Sinhronizācija šobrīd rit UN translate_on_sync=1 — sync beigās jaunos pārtulkos pats.\n";
    echo "  Paralēla palaišana maksātu dubulti par pārklājumu. Ja tiešām vajag paralēli: --force.\n";
    exit(1);
}

echo "\nSāku tulkošanu" . ($limit !== null ? " (limits: $limit virsraksti)" : ' (VISS atlikušais)')
    . ($useBatch ? ' caur Batch API' : '') . "…\n";
if ($useBatch) require_once __DIR__ . '/../lib/translate_batch.php';
$t0 = microtime(true);
$total = 0;
while (true) {
    $batch = $limit !== null ? min(KONKURSI_TRANSLATE_MAX_RUN, $limit - $total) : KONKURSI_TRANSLATE_MAX_RUN;
    if ($batch <= 0) break;
    // Batch ceļš pats savāc iepriekšējos darbus, iesniedz, pagaida un savāc; ja darbs
    // nepaspēj, tas paliek DB un $done ir mazāks — cilpa tad apstājas (0) un atlikumu
    // savāc nākamā palaišana vai sinhronizācija.
    $done = $useBatch ? ks_translate_batch_run($pdo, 900, $batch) : ks_translate_new_titles($pdo, $batch);
    $total += $done;
    $left = (int)$pdo->query("SELECT COUNT(DISTINCT title) FROM notices
                              WHERE title_lv IS NULL AND title IS NOT NULL AND title != ''"
                              . ($useBatch ? " AND title NOT IN (SELECT title FROM translate_batch_titles)" : ''))->fetchColumn();
    printf("  … pārtulkoti %d (kopā %d), atlikuši %d unikāli — %.0fs\n",
        $done, $total, $left, microtime(true) - $t0);
    if ($done === 0 || $left === 0) break;   // 0 = viss gatavs vai API pastāvīgi klūp
}

$rows = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE title_lv IS NOT NULL")->fetchColumn();
printf("\nPabeigts: %d unikāli virsraksti šajā palaišanā; DB rindas ar tulkojumu: %d. Ilgums %.0fs\n",
    $total, $rows, microtime(true) - $t0);

// Precīzā tokenu uzskaite no API usageMetadata (reg_gemini_usage_total — klienta uzkrājums)
if (function_exists('reg_gemini_usage_total')) {
    $u = reg_gemini_usage_total();
    if ($u['calls'] > 0) {
        printf("Tokeni (no API usageMetadata): ievade=%d, izvade=%d, domāšana=%d, pieprasījumi=%d\n",
            $u['in'], $u['out'], $u['thoughts'], $u['calls']);
    }
}
