<?php
/**
 * granti/bin/cron_build.php — grantu DIENAS darbs vienā failā (TIKAI CLI).
 *
 * Divi ceļi uz vienu un to pašu darbu, apzināti:
 *   1) PAMATA CEĻŠ ir admin panelis: `cron/dispatch.php` ik 5 min lasa
 *      admin_state/schedule.json un palaiž darbu ķēdi `granti.diena`
 *      (granti.build && granti.tulko). Grafiku maina panelī, ne serverī.
 *   2) ŠIS FAILS ir tiešā crontab rinda tiem hostingiem, kur dispečers nav ielikts:
 *          10 5 * * *  php /ceļš/uz/docroot/granti/bin/cron_build.php
 *
 * KARODZIŅŠ. Bez granti/data/cron_enabled.flag šis fails neko nedara. Tā cron var
 * apturēt, neaiztiekot servera konfigurāciju — tas pats slēdzis, kas konkursu sadaļā.
 * Karodziņu izveido: touch granti/data/cron_enabled.flag
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/config.php';

if (!is_file(granti_cron_flag())) {
    echo '[' . date('Y-m-d H:i:s') . '] Grantu cron IZSLĒGTS (nav ' . granti_cron_flag() . ") — neko nedaru.\n";
    exit(0);
}

set_time_limit(0);
$php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
$bin = __DIR__;

/** Palaiž vienu soli un atdod izejas kodu; izvadi laiž cauri, lai tā nonāk cron žurnālā. */
function gc_solis(string $nosaukums, string $cmd): int {
    echo '[' . date('Y-m-d H:i:s') . "] $nosaukums: $cmd\n";
    $rc = 0;
    // passthru var būt liegts (disable_functions); tad exec ar izvades pārsūtīšanu, un ja arī
    // tā nav — skaidra kļūda, ne fatāls "undefined function".
    if (function_exists('passthru')) passthru($cmd, $rc);
    elseif (function_exists('exec')) { $izv = []; exec($cmd, $izv, $rc); echo implode("\n", $izv), "\n"; }
    else { fwrite(STDERR, "Ne passthru, ne exec nav atļauts — palaid darbu caur admin paneli.\n"); $rc = 1; }
    echo '[' . date('Y-m-d H:i:s') . "] $nosaukums beidzies ar kodu $rc.\n";
    return $rc;
}

// 1. Būve. Ja tā krīt, tulkošanu NEPALAIŽAM: tulkot vecu datubāzi nozīmētu maksāt par
// tekstiem, kas nākamajā veiksmīgajā būvē tik un tā tiks pārrēķināti.
$rc = gc_solis('Būve', escapeshellarg($php) . ' ' . escapeshellarg($bin . '/build.php'));
if ($rc !== 0) { fwrite(STDERR, "Būve neizdevās (kods $rc) — tulkošana izlaista.\n"); exit($rc); }

// 2. Tulkošana caur Batch API (puse cenas). Dienas griesti un slēdzene ir pašā skriptā.
$rc = gc_solis('Tulkošana', escapeshellarg($php) . ' ' . escapeshellarg($bin . '/tulko.php'));
exit($rc);
