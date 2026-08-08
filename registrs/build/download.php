<?php
// server/build/download.php — CSV lejupielāde no data.gov.lv (ports of "0 download data.py").
// Saudzīgs režīms: pauze starp failiem + atkārtojumi ar pieaugošu gaidīšanu, ja neizdodas.
// Vides mainīgie: REG_DL_DELAY_S (pauze starp failiem, noklusējums 2 s),
//                 REG_DL_ATTEMPTS (mēģinājumi failam, noklusējums 3),
//                 REG_DL_RETRY_S (bāzes gaidīšana pirms atkārtojuma, noklusējums 15 s).
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Lejupielādē vienu URL uz failu (straumējot, ne atmiņā).
 * Raksta uz .part un atomiski pārsauc tikai pēc veiksmes — pusfails nekad neaizstāj veco.
 * @return bool true, ja izdevās (HTTP 200 un fails > 0 baiti)
 */
function download_one(string $url, string $dest_path, int $timeout = 300): bool {
    $tmp = $dest_path . '.part';
    $fp = fopen($tmp, 'wb');
    if ($fp === false) return false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FAILONERROR => true,           // HTTP >= 400 -> kļūda
        CURLOPT_USERAGENT => 'saraksts.lv data updater (PHP)',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ok = curl_exec($ch);
    $err = curl_error($ch);
    unset($ch); // PHP >= 8.0 rokturi aizver automātiski (curl_close ir deprecated 8.5+)
    fclose($fp);

    if ($ok === false || !is_file($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);
        if ($err !== '') error_log("Lejupielādes kļūda $url: $err");
        return false;
    }
    // Atomiski aizvieto veco failu tikai pēc veiksmīgas lejupielādes
    return rename($tmp, $dest_path);
}

/**
 * Lejupielādē vienu URL ar atkārtojumiem (backoff: retry_s, 2*retry_s, ...).
 * @return array [bool ok, int used_attempts]
 */
function download_with_retries(string $url, string $dest, int $attempts, int $retry_s, ?callable $log = null): array {
    for ($try = 1; $try <= $attempts; $try++) {
        build_abort_if_stopped();
        if (download_one($url, $dest)) return [true, $try];
        if ($try < $attempts) {
            $wait = $retry_s * $try; // pieaugoša pauze: 15s, 30s, ...
            if ($log) $log("      ! {$try}. mēģinājums neizdevās — gaida {$wait}s un atkārto ...");
            for ($w = 0; $w < $wait; $w++) { build_abort_if_stopped(); sleep(1); }
        }
    }
    return [false, $attempts];
}

/**
 * Lejupielādē visus CSV uz $csv_dir. Atgriež [ok_count, fail_count, fail_names[]].
 */
function download_all_csvs(string $csv_dir, ?callable $log = null): array {
    if (!is_dir($csv_dir)) @mkdir($csv_dir, 0775, true);

    $delay_s = max(0, (int)(getenv('REG_DL_DELAY_S') ?: 2));      // pauze starp failiem (saudzē data.gov.lv)
    $attempts = max(1, (int)(getenv('REG_DL_ATTEMPTS') ?: 3));    // mēģinājumi vienam failam
    $retry_s = max(1, (int)(getenv('REG_DL_RETRY_S') ?: 15));     // bāzes pauze pirms atkārtojuma

    // Deduplicē URL (saglabā secību)
    $seen = []; $urls = [];
    foreach (CSV_URLS as $u) {
        if (!isset($seen[$u])) { $seen[$u] = 1; $urls[] = $u; }
    }
    $total = count($urls);
    if ($log) $log("   Kopā $total faili; pauze starp failiem {$delay_s}s, līdz $attempts mēģinājumiem failam.");

    $ok = 0; $fail = 0; $failed = []; $i = 0;
    foreach ($urls as $url) {
        build_abort_if_stopped();
        $i++;
        $filename = basename(parse_url($url, PHP_URL_PATH) ?? '');
        if ($filename === '' || !str_ends_with(strtolower($filename), '.csv')) {
            if ($log) $log("   - Izlaists (nav .csv): $url");
            continue;
        }
        $dest = $csv_dir . '/' . $filename;
        if ($log) $log("   -> [$i/$total] Lejupielādē: $filename ...");
        [$success, $used] = download_with_retries($url, $dest, $attempts, $retry_s, $log);
        if ($success) {
            $ok++;
            $tries = $used > 1 ? " ({$used}. mēģinājumā)" : '';
            if ($log) $log("      OK (" . number_format((float)filesize($dest), 0, '.', ' ') . " b)$tries");
        } else {
            $fail++; $failed[] = $filename;
            if ($log) $log("      ! NEIZDEVĀS: $filename pēc $attempts mēģinājumiem — paliek iepriekšējā versija.");
        }
        // Saudzīga pauze starp failiem (izņemot pēc pēdējā)
        if ($delay_s > 0 && $i < $total) {
            for ($w = 0; $w < $delay_s; $w++) { build_abort_if_stopped(); sleep(1); }
        }
    }
    if ($log) $log("   Lejupielāde pabeigta: $ok OK, $fail kļūdas" . ($failed ? ' (' . implode(', ', $failed) . ')' : ''));
    return [$ok, $fail, $failed];
}
