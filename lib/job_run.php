<?php
/**
 * lib/job_run.php — palaiž VIENU darbu ar slēdzeni, izvades uztveršanu un žurnalēšanu.
 *
 * KĀPĒC ŠIS FAILS VISPĀR IR. Sākotnēji slēdzeni turēja čaulas `flock -n 9 … 9>lock`.
 * Tas ir Linux util-linux rīks, kura macOS nav vispār, un daļā koplietoto hostingu tas
 * arī nav — tad `flock` neizdodas, `||` zars nostrādā, un darbs KLUSI izlaižas ar
 * ziņojumu "jau darbojas", lai gan patiesais iemesls ir trūkstoša utilīta. Tieši tā
 * notika pirmajā pārbaudē. PHP flock() ir iebūvēts un uzvedas vienādi visur.
 *
 * Lietošana (to ģenerē admin_launch(), bet der arī manuāli):
 *     php lib/job_run.php <darba-id>
 * Izejas kods = darba izejas kods (0 = labi), 0 arī tad, ja darbs izlaists.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/applog.php';
require_once __DIR__ . '/admin_jobs.php';

set_time_limit(0);

$id  = (string)($argv[1] ?? '');
$job = $id !== '' ? admin_job($id) : null;
if ($job === null) {
    fwrite(STDERR, "Nezināms darbs: $id\n");
    exit(2);
}

@mkdir(admin_state_dir(), 0775, true);
$logFile = admin_job_log($id);

// Darba izvades fails nedrīkst augt bezgalīgi — atstājam pēdējos ~200 KB.
if (is_file($logFile) && (int)@filesize($logFile) > 400 * 1024) {
    $keep = (string)@file_get_contents($logFile, false, null, -200 * 1024);
    @file_put_contents($logFile, "…(vecākā daļa nogriezta " . date('Y-m-d H:i') . ")\n" . $keep, LOCK_EX);
}

// ── Slēdzene ────────────────────────────────────────────────────────────────
// Darbiem ar SAVU slēdzeni (build_all, sync) mēs to NEAIZŅEMAM — citādi bērna
// process nevarētu paņemt to pašu failu un uzskatītu, ka tas jau iet. Tiem tikai
// pārbaudām un atkāpjamies.
$ownLock = $job['own_lock'] ?? null;
$fh = null;
if ($ownLock !== null) {
    if (admin_job_running($job)) {
        applog_job($id, 'ok', ['piezime' => 'izlaists-jau-iet']);
        echo "[$id] jau darbojas — izlaists.\n";
        exit(0);
    }
} else {
    $lockPath = admin_job_lock($id);
    $fh = @fopen($lockPath, 'c');
    if ($fh === false) {
        applog_job($id, 'error', ['iemesls' => 'nevar-atvert-sledzeni']);
        fwrite(STDERR, "Nevar atvērt slēdzeni: $lockPath\n");
        exit(1);
    }
    if (!flock($fh, LOCK_EX | LOCK_NB)) {
        fclose($fh);
        applog_job($id, 'ok', ['piezime' => 'izlaists-jau-iet']);
        echo "[$id] jau darbojas — izlaists.\n";
        exit(0);
    }
}

// ── Izpilde ─────────────────────────────────────────────────────────────────
$cmd = admin_job_command($job);
if ($cmd === null) {
    applog_job($id, 'error', ['iemesls' => 'nav-komandas']);
    exit(2);
}

$header = "\n=== " . date('Y-m-d H:i:s') . "  $id  ===\n";
@file_put_contents($logFile, $header, FILE_APPEND | LOCK_EX);

applog_job($id, 'start');
$t0 = microtime(true);

$full = $cmd . ' >> ' . admin_sh_arg($logFile) . ' 2>&1';
$rc = 127;
if (admin_fn_ok('proc_open')) {
    $p = @proc_open($full, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (is_resource($p)) {
        foreach ($pipes as $pp) if (is_resource($pp)) @fclose($pp);
        $rc = proc_close($p);
    }
} elseif (admin_fn_ok('exec')) {
    $o = []; @exec($full, $o, $rc);
} elseif (admin_fn_ok('system')) {
    @system($full, $rc);
} elseif (admin_fn_ok('shell_exec')) {
    @shell_exec($full); $rc = 0;   // izejas kods nav pieejams
} else {
    applog_job($id, 'error', ['iemesls' => 'nav-izpildes-funkciju']);
    if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
    exit(1);
}

$ms = (int)round((microtime(true) - $t0) * 1000);

// ── Izvades sakārtošana ─────────────────────────────────────────────────────
// Progresa joslas (Python `print(..., end="\r")`) raksta tūkstošiem stāvokļu VIENĀ
// rindā bez jaunrindas. Iespējas 1. solis, lejupielādējot 132 MB OSM failu, tā
// uzģenerēja 1,2 MB vienu rindu — žurnāls kļuva nelasāms un neizmantojams kļūdu
// meklēšanai. Atstājam katras rindas GALA stāvokli, pārējos pārrakstījumus izmetam.
// Griestus piemēro arī PĒC izpildes, ne tikai pirms — citāda viena palaišana varēja
// uzrakstīt neierobežota izmēra failu.
if (is_file($logFile)) {
    $raw = (string)@file_get_contents($logFile);
    if (strpos($raw, "\r") !== false) {
        $clean = preg_replace('/[^\n\r]*\r(?!\n)/', '', $raw);
        if (is_string($clean)) { $raw = $clean; @file_put_contents($logFile, $raw, LOCK_EX); }
    }
    if (strlen($raw) > 200 * 1024) {
        @file_put_contents($logFile,
            "…(vecākā daļa nogriezta " . date('Y-m-d H:i') . ")\n" . substr($raw, -200 * 1024), LOCK_EX);
    }
}

// Kļūmes gadījumā izvades aste nonāk arī kopīgajā žurnālā — citādi tur būtu tikai
// "error rc=1" bez norādes, kas tieši nogāja greizi.
$extra = ['rc' => $rc, 's' => (int)round($ms / 1000)];
if ($rc !== 0 && is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $tail = trim(implode(' | ', array_slice($lines, -3)));
    if ($tail !== '') $extra['aste'] = $tail;
}
applog_job($id, $rc === 0 ? 'ok' : 'error', $extra);

@file_put_contents($logFile, '=== beigas rc=' . $rc . ' (' . round($ms / 1000, 1) . "s) ===\n",
    FILE_APPEND | LOCK_EX);

if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
exit($rc);
