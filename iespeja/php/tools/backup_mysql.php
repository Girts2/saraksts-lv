<?php
/**
 * Iespējas MySQL tabulu dublējums lokālā mapē.
 *
 * Katrs konveijera solis (5.–9.) sākas ar TRUNCATE, tāpēc pirms palaišanas nav
 * neviena atpakaļceļa. Šis rīks nolasa pašreizējo saturu un saglabā to lokāli
 * kā CSV (ģeometrija kā WKT), lai neveiksmīgas palaišanas gadījumā datus varētu
 * atjaunot, nepārbūvējot visu konveijeru no data.gov.lv.
 *
 *   php tools/backup_mysql.php /kāda/mape
 */
declare(strict_types=1);
require_once __DIR__ . '/../common.php';

$dir = $argv[1] ?? null;
if ($dir === null) { fwrite(STDERR, "Lietošana: php backup_mysql.php <mape>\n"); exit(2); }
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) ie_fail("nevar izveidot mapi: $dir");

$t0  = ie_start('Iespējas MySQL tabulu dublējums');
$pdo = ie_db();
$c   = ie_config();
ie_say("Avots: {$c['host']}:{$c['port']} / {$c['name']}");
ie_say("Mērķis: $dir");

// Tabulu saraksts nāk no shēmas, nevis no ierakstīta saraksta šeit. Agrāk šis
// bija trešā vieta, kas jāatceras atjaunināt, pievienojot POI tipu — tieši tāda
// aizmāršība mēdz beigties ar dublējumu, kurā vienas tabulas nav.
$tables = ie_all_tables();

$total = 0;
$missing = [];
foreach ($tables as $tbl) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `$tbl`")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        ie_say(sprintf('   %-24s nav tabulas', $tbl));
        $missing[] = $tbl;
        continue;
    }

    // POINT kolonnas nolasām kā WKT, citādi tās nāktu binārā formātā
    $sel = [];
    foreach ($cols as $col) {
        $type = (string)$pdo->query("SHOW COLUMNS FROM `$tbl` LIKE " . $pdo->quote($col))
                            ->fetch(PDO::FETCH_ASSOC)['Type'];
        $sel[] = stripos($type, 'point') !== false
            ? "ST_AsText(`$col`) AS `$col`"
            : "`$col`";
    }

    $path = "$dir/$tbl.csv";
    $fh = fopen($path, 'w');
    if ($fh === false) ie_fail("nevar rakstīt: $path");
    ie_fputcsv_py($fh, $cols, "\n");

    $q = $pdo->query('SELECT ' . implode(',', $sel) . " FROM `$tbl`");
    $n = 0;
    while ($row = $q->fetch(PDO::FETCH_NUM)) { ie_fputcsv_py($fh, $row, "\n"); $n++; }
    fclose($fh);

    $total += $n;
    ie_say(sprintf('   %-24s %9s rindas  (%.1f MB)', $tbl, number_format($n, 0, '.', ' '),
        filesize($path) / 1048576));
}

ie_say(sprintf('Kopā %s rindas %d tabulās.', number_format($total, 0, '.', ' '),
    count($tables) - count($missing)));
if ($missing) ie_say('Nav tabulu: ' . implode(', ', $missing));
ie_done($t0);
