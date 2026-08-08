<?php
/**
 * konkursi/bin/sync.php — manuālais/fona TED sinhronizācijas ieejas punkts (CLI).
 *
 * Izmantojums:
 *   php konkursi/bin/sync.php                    # noklusējumi no config.php
 *   php konkursi/bin/sync.php --max=5            # līdz 5 paketēm šajā palaišanā
 *   php konkursi/bin/sync.php --backfill=10      # pirmajā reizē paņem 10 pēdējās paketes
 *   php konkursi/bin/sync.php --no-prune         # neizpildīt glabāšanas politiku
 *   php konkursi/bin/sync.php --no-iub           # izlaist IUB (LV nacionālo) posmu
 *   php konkursi/bin/sync.php --no-lt            # izlaist CVP IS (LT nacionālo) posmu
 *   php konkursi/bin/sync.php --skip=rhr,hilma   # izlaist posmus (rhr|hilma|doffin|udbud|bzp|bkms|lt)
 *   php konkursi/bin/sync.php --only=hilma,bzp   # palaist TIKAI šos posmus (ted|iub|lt|...|anac)
 *   php konkursi/bin/sync.php --deep=60 --only=vvz  # dziļā vēstures aizpilde (60 d logs)
 *
 * Dziļā aizpilde (--deep) ir paredzēta vienreizējai manuālai palaišanai lokāli:
 * paceļ lapu/detaļu limitus, atver logus līdz N dienām un TED aizpilda atpakaļ;
 * vecos rezultātus (>14 d) neimportē. Ikdienas cron to nekad neizmanto.
 *
 * To pašu skriptu fonā palaiž admin panelis (konkursi_admin.php).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/sync_engine.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$opts = ['prune' => true];
$fetchWorker = null;      // iekšējais: ks_run_stages_parallel bērnprocess
$fetchCountFile = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--max=(\d+)$/', $arg, $m)) $opts['max_packages'] = (int)$m[1];
    elseif (preg_match('/^--backfill=(\d+)$/', $arg, $m)) $opts['backfill'] = (int)$m[1];
    elseif ($arg === '--no-prune') $opts['prune'] = false;
    elseif ($arg === '--no-iub') $opts['iub'] = false;
    elseif ($arg === '--no-lt') $opts['lt'] = false;
    elseif (preg_match('/^--skip=([a-z,_]+)$/', $arg, $m)) $opts['skip'] = explode(',', $m[1]);
    elseif (preg_match('/^--only=([a-z,_]+)$/', $arg, $m)) $opts['only'] = explode(',', $m[1]);
    elseif (preg_match('/^--deep(?:=(\d+))?$/', $arg, $m)) putenv('KONKURSI_DEEP=' . (int)($m[1] ?? KONKURSI_ACTIVE_WINDOW_DAYS));
    // IEKŠĒJAIS (nelaist manuāli): paralēlās ielādes strādnieks — tikai norādīto
    // avotu ielāde bez slēdzenes/TED/prune; palaiž un uzrauga ks_run_stages_parallel.
    elseif (preg_match('/^--fetch-worker=([a-z,_]+)$/', $arg, $m)) $fetchWorker = explode(',', $m[1]);
    elseif (preg_match('/^--fetch-count-file=(.+)$/', $arg, $m)) $fetchCountFile = $m[1];
    else { fwrite(STDERR, "Nezināms arguments: $arg\n"); exit(2); }
}

if ($fetchWorker !== null) {
    ks_run_fetch_worker($fetchWorker, $fetchCountFile);
    exit(0);
}

ks_run_sync($opts);
