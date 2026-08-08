<?php
/**
 * konkursi/bin/build_start.php — manuāli pārbūvē starta momentuzņēmumu
 * (data/start.json; sk. lib/snapshot.php). Parasti to dara katra sinhronizācija
 * pati; šis noder pirmajai izveidei un pārbaudei pēc koda izmaiņām:
 *   php konkursi/bin/build_start.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/snapshot.php';

$t0 = microtime(true);
$path = konkursi_snapshot_write(konkursi_db());
printf("✅ %s — %d KB, %.1f s\n", $path, (int)round(filesize($path) / 1024), microtime(true) - $t0);
