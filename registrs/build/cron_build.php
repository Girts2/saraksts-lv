<?php
/**
 * server/build/cron_build.php — cron ieejas punkts.
 * hPanel cron: php /path/to/build/cron_build.php  (piem., reizi nedēļā naktī)
 *
 * Palaiž pilno būvēšanu TIKAI tad, ja cron slēdzis ir ieslēgts
 * (admin panelī — "Cron ieslēgts/izslēgts" poga = cron_enabled.flag fails).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$STATE_DIR = build_root() . '/build_state';
$FLAG = $STATE_DIR . '/cron_enabled.flag';

if (!is_file($FLAG)) {
    echo "[" . date('Y-m-d H:i:s') . "] Cron IZSLĒGTS (nav $FLAG) — neko nedaru.\n";
    exit(0);
}

// Palaiž orķestratoru šajā pašā procesā (cron jau ir fons).
require __DIR__ . '/build_all.php';
