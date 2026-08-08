<?php
/**
 * server/build/sections_cli.php — manuāla sadaļu būve no dzīvās ur_data.db (bez pilnā cikla).
 *
 * Lietošana:
 *   php sections_cli.php all|nozare|struktura|pensionars [izvades_mape]
 *
 * Noklusējumā raksta TIEŠI docroot dzīvajās vietās (nozare/katalogs.sqlite, struktura.php,
 * pensionars/pensionari.sqlite). Ja norādīta izvades mape — raksta tur (piem., verifikācijai).
 * ur_data.db ceļu var pārrakstīt ar UR_DB_PATH vidi.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // tikai CLI

set_time_limit(0);
ini_set('memory_limit', '2048M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/section_nozare.php';
require_once __DIR__ . '/section_struktura.php';
require_once __DIR__ . '/section_pensionars.php';

$what = $argv[1] ?? 'all';
$out_dir = isset($argv[2]) ? rtrim($argv[2], '/') : null;

$db_path = reg_ur_db_path();
if (!is_file($db_path)) {
    fwrite(STDERR, "KĻŪDA: nav atrasta ur_data.db: $db_path (iestati UR_DB_PATH)\n");
    exit(1);
}
$ur = new PDO('sqlite:' . $db_path);
$ur->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$log = function (string $m) { echo '[' . date('H:i:s') . '] ' . $m . "\n"; };
$nace_csv = __DIR__ . '/NACE.csv';
$template = __DIR__ . '/templates/struktura_template.html';

$targets = [
    'nozare' => $out_dir ? "$out_dir/katalogs.sqlite" : reg_docroot() . '/nozare/katalogs.sqlite',
    'struktura' => $out_dir ? "$out_dir/struktura.php" : reg_docroot() . '/struktura.php',
    'struktura_data' => $out_dir ? "$out_dir/struktura_data" : reg_docroot() . '/struktura/data',
    'pensionars' => $out_dir ? "$out_dir/pensionari.sqlite" : reg_docroot() . '/pensionars/pensionari.sqlite',
];

$t0 = microtime(true);
try {
    if ($what === 'all' || $what === 'nozare') build_section_nozare($ur, $nace_csv, $targets['nozare'], $log);
    if ($what === 'all' || $what === 'struktura') build_section_struktura($ur, $nace_csv, $template, $targets['struktura'], $targets['struktura_data'], $log);
    if ($what === 'all' || $what === 'pensionars') build_section_pensionars($ur, $nace_csv, $targets['pensionars'], $log);
} catch (Throwable $e) {
    fwrite(STDERR, 'KĻŪDA: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
$log('Gatavs (' . round(microtime(true) - $t0) . 's)');
