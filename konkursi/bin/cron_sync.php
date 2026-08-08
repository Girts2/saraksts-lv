<?php
/**
 * konkursi/bin/cron_sync.php — cron ieejas punkts TED sinhronizācijai.
 *
 * hPanel cron (ieteicams reizi dienā, piem. 10:15 pēc Rīgas laika — TED dienas
 * pakete parādās darbadienas rītā; ja dienā paketes nav, nākamā reize paņems abas):
 *   15 10 * * *  php /path/to/docroot/konkursi/bin/cron_sync.php
 *
 * Darbojas TIKAI tad, ja admin panelī (konkursi_admin.php) ieslēgts cron slēdzis
 * (data/cron_enabled.flag) — tas pats princips kā registrs/build/cron_build.php.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/sync_engine.php';

if (!is_file(konkursi_cron_flag())) {
    echo '[' . date('Y-m-d H:i:s') . "] Konkursu cron IZSLĒGTS (nav " . konkursi_cron_flag() . ") — neko nedaru.\n";
    exit(0);
}

set_time_limit(0);
ini_set('memory_limit', '512M');

ks_run_sync();
