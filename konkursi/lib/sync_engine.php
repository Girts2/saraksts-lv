<?php
/**
 * konkursi/lib/sync_engine.php — TED dienas pakešu sinhronizācijas dzinējs.
 *
 * Darbības princips (saudzīgs pret TED serveriem):
 *   1. No imported_files nosaka pēdējo importēto OJ S numuru (paketes numurējas
 *      secīgi pa publikācijas dienām: GGGG + 5 cipari, piem. 202600123).
 *   2. Ar HEAD pārbauda nākamos numurus; pēc TED_PROBE_MISS_STOP secīgiem 404
 *      pieņem, ka jaunāku vēl nav (nākotne), un apstājas.
 *   3. Lejupielādē ne vairāk kā TED_MAX_PACKAGES_PER_RUN paketes vienā palaišanā,
 *      ar TED_REQUEST_DELAY_S pauzēm; arhīvu pēc importa dzēš (diska taupīšana).
 *   4. Pēc importa izpilda glabāšanas politiku (prune) — vecie ieraksti tiek
 *      dzēsti, DB paliek ierobežota izmēra.
 *
 * Stāvoklis: data/sync_state.json, žurnāls: data/sync.log, slēdzene: data/sync.lock.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/ted_parser.php';
require_once __DIR__ . '/iub_parser.php';
require_once __DIR__ . '/cvpis_parser.php';
require_once __DIR__ . '/nordics_parser.php';
require_once __DIR__ . '/bzp_parser.php';
require_once __DIR__ . '/west_parser.php';
require_once __DIR__ . '/placsp_parser.php';
require_once __DIR__ . '/central_parser.php';
require_once __DIR__ . '/east_parser.php';
require_once __DIR__ . '/south_parser.php';
require_once __DIR__ . '/ocds_parser.php';
require_once __DIR__ . '/balkan_parser.php';
require_once __DIR__ . '/ifi_parser.php';
require_once __DIR__ . '/modti_parser.php';
require_once __DIR__ . '/lvti_parser.php';

// ── Žurnāls un stāvoklis ──────────────────────────────────────────────────────

/** Cik ks_log rindu izvadītas šajā procesā (redzamības pārbaudei: vai posms kaut ko teica). */
function ks_log_count(bool $increment = false): int {
    static $n = 0;
    if ($increment) $n++;
    return $n;
}

function ks_log(string $msg): void {
    ks_log_count(true); // palielina skaitītāju
    @mkdir(konkursi_data_dir(), 0775, true);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents(konkursi_log_path(), $line, FILE_APPEND | LOCK_EX);
    if (PHP_SAPI === 'cli') echo $line;
    // Neļauj žurnālam augt bezgalīgi
    clearstatcache(true, konkursi_log_path());
    if (@filesize(konkursi_log_path()) > 500 * 1024) {
        $txt = (string)@file_get_contents(konkursi_log_path());
        @file_put_contents(konkursi_log_path(), substr($txt, -200 * 1024));
    }
}

function ks_state(array $patch): void {
    if (ks_state_disabled()) return; // strādnieku procesi stāvokli neraksta
    $p = konkursi_state_path();
    $cur = is_file($p) ? (json_decode((string)@file_get_contents($p), true) ?: []) : [];
    $cur = array_merge($cur, $patch, ['updated' => date('Y-m-d H:i:s')]);
    @file_put_contents($p, json_encode($cur, JSON_UNESCAPED_UNICODE));
}

/** Strādnieku (--fetch-worker) procesos sync_state.json NERAKSTA — citādi bērni
 *  cits citam un vecākprocesam pārrakstītu stage/imported laukus. */
function ks_state_disabled(?bool $set = null): bool {
    static $d = false;
    if ($set !== null) $d = $set;
    return $d;
}

function ks_stop_requested(): bool {
    return is_file(konkursi_stop_flag());
}

// ── Slēdzene (flock — tāds pats princips kā registrs/build) ───────────────────

/** @return resource|null tur atvērtu rokturi, kamēr process dzīvs */
function ks_acquire_lock() {
    @mkdir(konkursi_data_dir(), 0775, true);
    $fp = @fopen(konkursi_lock_path(), 'c+');
    if ($fp === false) return null;
    if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return null; }
    ftruncate($fp, 0);
    fwrite($fp, (string)getmypid());
    fflush($fp);
    return $fp;
}

function ks_is_running(): bool {
    $p = konkursi_lock_path();
    if (!is_file($p)) return false;
    $fp = @fopen($p, 'r');
    if ($fp === false) return true;
    $free = flock($fp, LOCK_EX | LOCK_NB);
    if ($free) { flock($fp, LOCK_UN); fclose($fp); return false; }
    fclose($fp);
    return true;
}

// ── HTTP (curl ar stream atkāpi) ──────────────────────────────────────────────

/** HEAD pieprasījums; atgriež HTTP statusu (0 = tīkla kļūda). */
function ks_http_head(string $url): int {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => TED_PROBE_TIMEOUT_S,
            CURLOPT_USERAGENT      => TED_USER_AGENT,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch); // curl_close kopš PHP 8.0 nav vajadzīgs (8.5 — deprecated)
        return $code;
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'HEAD', 'timeout' => TED_PROBE_TIMEOUT_S,
        'user_agent' => TED_USER_AGENT, 'ignore_errors' => true, 'follow_location' => 1,
    ]]);
    $h = @get_headers($url, true, $ctx);
    if ($h === false || !isset($h[0])) return 0;
    // Redirect gadījumā statusa rindas ir vairākas — ņem pēdējo
    $status = $h[0];
    foreach ($h as $k => $v) {
        if (is_int($k) && is_string($v) && str_starts_with($v, 'HTTP/')) $status = $v;
    }
    return preg_match('#HTTP/\S+\s+(\d{3})#', (string)$status, $m) ? (int)$m[1] : 0;
}

/**
 * Pieklājības aizture pret VIENU serveri (host), pirms katra pieprasījuma.
 *
 * Iemesls: 2026-07-20 divi avoti mūs nobloķēja — eojn.hr atdeva HTTP 429, bet
 * base.gov.pt ieslēdza WebKnight ugunsmūri (HTTP 999) uz visu domēnu, tāpēc
 * apstājās arī pilnīgi nesaistīti posmi. Atsevišķu cilpu sleep() te nepalīdz:
 * dienā pret vienu avotu iet vairākas sinhronizācijas + kārtējais posms, un
 * kopējais temps neviena vietā netiek skaitīts. Šī ir vienīgā vieta, kur to var
 * izdarīt centralizēti — visi HTTP palīgi iet caur to.
 *
 * Minimālā atstarpe uz hostu: KS_HOST_MIN_INTERVAL_MS (noklusējums), atsevišķiem
 * jutīgiem avotiem lielāka (sk. KS_HOST_INTERVAL_MS).
 */
/**
 * CA saišķis pieprasījumiem: sistēmas saknes + data/ca/*.pem starpsertifikāti.
 *
 * Daži avoti sūta TIKAI lapas sertifikātu, bez starpsertifikāta ķēdes. Sistēmas
 * curl to nemana (macOS pats pievelk trūkstošo pa AIA), bet PHP OpenSSL to
 * nedara, un pieprasījums klusi krīt ar "unable to get local issuer certificate"
 * — tieši tā 2026-07-20 Skotijas PCS API atdeva 0 ierakstu bez kļūdas žurnālā.
 * Risinājums ir pievienot trūkstošo starpsertifikātu, NEVIS izslēgt pārbaudi.
 * @return string|null ceļš uz saišķi vai null, ja papildu sertifikātu nav
 */
function ks_ca_bundle(): ?string {
    static $path = false;
    if ($path !== false) return $path;
    $path = null;
    $extra = glob(konkursi_data_dir() . '/ca/*.pem') ?: [];
    if (!$extra) return $path;
    $out = konkursi_data_dir() . '/ca-bundle.pem';
    $newest = 0;
    foreach ($extra as $f) $newest = max($newest, (int)@filemtime($f));
    $sys = ini_get('openssl.cafile') ?: (openssl_get_cert_locations()['default_cert_file'] ?? '');
    if ($sys !== '' && is_readable($sys)) $newest = max($newest, (int)@filemtime($sys));
    if (!is_file($out) || (int)@filemtime($out) < $newest) {
        $buf = ($sys !== '' && is_readable($sys)) ? (string)file_get_contents($sys) : '';
        foreach ($extra as $f) $buf .= "\n" . (string)file_get_contents($f);
        if (@file_put_contents($out, $buf) === false) return $path;
    }
    $path = $out;
    return $path;
}

function ks_http_host(string $url): string {
    return strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
}

/**
 * Bloķēšanas riska stāvoklis vienai palaišanai (per-host):
 *   'signals'  — cik bloka signālu (429/403/503/999) šajā palaišanā,
 *   'penalty'  — pauzes reizinātājs (×2 pēc katra signāla, līdz ×8),
 *   'tripped'  — ķēde atvērta: hostam vairs nesūtām neko.
 * @return array{signals:array<string,int>, penalty:array<string,int>, tripped:array<string,bool>}
 */
function &ks_http_block_state(): array {
    static $s = ['signals' => [], 'penalty' => [], 'tripped' => []];
    return $s;
}

/**
 * Vai hosts šobrīd ir "atdzišanā" (ķēde atvērta šajā palaišanā VAI meta glabāta
 * atdzišana no iepriekšējās). Beidzies termiņš tiek notīrīts lazily.
 */
function ks_http_host_blocked(string $host): bool {
    if ($host === '') return false;
    $st = &ks_http_block_state();
    if (!empty($st['tripped'][$host])) return true;
    static $cool = null; // meta atdzišanas kešs uz palaišanu
    if ($cool === null) {
        $cool = [];
        try {
            $pdo = konkursi_db();
            $q = $pdo->query("SELECT k, v FROM meta WHERE k LIKE 'host_cooldown_%'");
            foreach ($q as $r) $cool[substr((string)$r['k'], strlen('host_cooldown_'))] = (int)$r['v'];
        } catch (Throwable $e) { /* bez DB — strādā tikai šīs palaišanas ķēde */ }
    }
    if (isset($cool[$host])) {
        if ($cool[$host] > time()) return true;
        unset($cool[$host]); // beidzies — notīra meta, lai admin panelī nekaras vecs ieraksts
        try { konkursi_db()->prepare("DELETE FROM meta WHERE k = ?")->execute(['host_cooldown_' . $host]); } catch (Throwable $e) { /* nav kritiski */ }
    }
    return false;
}

/**
 * Reģistrē bloka signālu (429/403/503/999). Eskalē hosta pauzi ×2 (līdz ×8);
 * pēc KS_HOST_BLOCK_TRIP signāliem atver ķēdi šai palaišanai UN ieraksta meta
 * atdzišanu (KS_HOST_COOLDOWN_S vai avota Retry-After, ja garāks), lai arī
 * nākamās palaišanas hostu neaiztiek, kamēr tas nav atdzisis.
 */
function ks_http_note_block(string $url, int $code, int $retryAfterS = 0): void {
    $host = ks_http_host($url);
    if ($host === '' || !in_array($code, KS_BLOCK_CODES, true)) return;
    $st = &ks_http_block_state();
    $st['signals'][$host] = ($st['signals'][$host] ?? 0) + 1;
    $st['penalty'][$host] = min(8, max(1, ($st['penalty'][$host] ?? 1)) * 2);
    if ($st['signals'][$host] >= KS_HOST_BLOCK_TRIP && empty($st['tripped'][$host])) {
        $st['tripped'][$host] = true;
        $cooldown = max(KS_HOST_COOLDOWN_S, $retryAfterS);
        $until = time() + $cooldown;
        try { konkursi_meta_set(konkursi_db(), 'host_cooldown_' . $host, (string)$until); } catch (Throwable $e) { /* nav kritiski */ }
        ks_log("  ⛔ $host atdeva " . $st['signals'][$host] . "× HTTP $code — pārtraucu sūtīt šim hostam (atdzišana līdz "
            . (new DateTimeImmutable('@' . $until))->setTimezone(new DateTimeZone('Europe/Riga'))->format('d.m. H:i') . ' Rīgas laikā).');
    }
}

/**
 * Veiksmīgs pieprasījums — nolīdzina hosta taimautu skaitītāju.
 * Skaitām taimautus PĒC KĀRTAS, nevis kopā: viens nejaušs taimauts starp
 * simts veiksmīgiem izsaukumiem nav pamats atslēgt avotu.
 */
function ks_http_note_ok(string $url): void {
    $host = ks_http_host($url);
    if ($host === '') return;
    $st = &ks_http_block_state();
    unset($st['timeouts'][$host]);
}

/**
 * Reģistrē taimautu (hosts neatbild). Pēc KS_HOST_TIMEOUT_TRIP taimautiem pēc
 * kārtas atver ķēdi tāpat kā WAF blokam un ieraksta īsāku atdzišanu — hosts, kas
 * neatbild, parasti atgriežas pats, atšķirībā no IP bloka.
 */
function ks_http_note_timeout(string $url): void {
    $host = ks_http_host($url);
    if ($host === '') return;
    $st = &ks_http_block_state();
    $st['timeouts'][$host] = ($st['timeouts'][$host] ?? 0) + 1;
    $n = $st['timeouts'][$host];
    if ($n < KS_HOST_TIMEOUT_TRIP || !empty($st['tripped'][$host])) return;

    $st['tripped'][$host] = true;
    $until = time() + KS_HOST_TIMEOUT_COOLDOWN_S;
    try { konkursi_meta_set(konkursi_db(), 'host_cooldown_' . $host, (string)$until); } catch (Throwable $e) { /* nav kritiski */ }
    ks_log("  ⛔ $host neatbildēja {$n}× pēc kārtas (taimauts) — pārtraucu sūtīt šim hostam (atdzišana līdz "
        . (new DateTimeImmutable('@' . $until))->setTimezone(new DateTimeZone('Europe/Riga'))->format('d.m. H:i') . ' Rīgas laikā).');
}

/**
 * Posma laika budžets. ks_sync_all to iestata pirms katra avota; ks_http_get pēc
 * tā pārstāj sūtīt pieprasījumus, tāpēc budžets darbojas VISOS avotos, arī tajos,
 * kuru cikli paši laiku nepārbauda.
 */
function ks_stage_begin(string $stage): void {
    $s = &ks_stage_state();
    $s = ['stage' => $stage, 'started' => microtime(true),
          'max' => KS_STAGE_MAX_S_BY_SOURCE[$stage] ?? KS_STAGE_MAX_S, 'warned' => false];
}

function &ks_stage_state(): array {
    static $s = ['stage' => '', 'started' => 0.0, 'max' => 0, 'warned' => false];
    return $s;
}

/** Vai aktīvais posms ir pārsniedzis savu laika budžetu (žurnalē vienu reizi). */
function ks_stage_over_budget(): bool {
    $s = &ks_stage_state();
    if ($s['stage'] === '' || $s['max'] <= 0) return false;
    $elapsed = microtime(true) - $s['started'];
    if ($elapsed < $s['max']) return false;
    if (!$s['warned']) {
        $s['warned'] = true;
        ks_log(sprintf('  ⏱ %s pārsniedza %d s budžetu — pārtraucu šo avotu un eju tālāk (dati, kas paspēja, ir saglabāti).',
            $s['stage'], (int)$s['max']));
    }
    return true;
}

function ks_http_throttle(string $url): void {
    static $last = [];
    $host = ks_http_host($url);
    if ($host === '') return;
    $minMs = KS_HOST_INTERVAL_MS[$host] ?? KS_HOST_MIN_INTERVAL_MS;
    // Soda reizinātājs pēc bloka signāliem (429/403/...) — atkāpjamies, pirms
    // serveris atver īstu IP bloku.
    $st = &ks_http_block_state();
    $minMs *= max(1, $st['penalty'][$host] ?? 1);
    // Jitter: +0..KS_HTTP_JITTER_PCT% — vienmērīga kadence izskatās robotiska;
    // nejauša piedeva (vidēji LĒNĀK, nekad ātrāk) ir pieklājīgāka pret WAF.
    $minMs += random_int(0, (int)($minMs * KS_HTTP_JITTER_PCT / 100));
    $now = microtime(true);
    if (isset($last[$host])) {
        $waitS = ($minMs / 1000) - ($now - $last[$host]);
        if ($waitS > 0) usleep((int)($waitS * 1_000_000));
    }
    $last[$host] = microtime(true);
}

/** Lejupielādē failu (straumējot uz disku). $quiet — nerakstīt kļūdu žurnālā (gaidāms 404). */
function ks_http_download(string $url, string $dest, bool $quiet = false, ?string $jar = null): bool {
    if (ks_http_host_blocked(ks_http_host($url))) return false; // ķēde atvērta / atdzišana
    ks_http_throttle($url);
    $tmp = $dest . '.part';
    $out = @fopen($tmp, 'wb');
    if ($out === false) return false;
    $ok = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $out,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => TED_HTTP_TIMEOUT_S,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_USERAGENT      => TED_USER_AGENT,
            CURLOPT_FAILONERROR    => true,
        ]);
        $ca = ks_ca_bundle();
        if ($ca !== null) curl_setopt($ch, CURLOPT_CAINFO, $ca);
        if ($jar !== null) {
            // Sesijas cepumi (eTenders lapošana strādā tikai vienas sesijas ietvaros)
            curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
        }
        $ok = curl_exec($ch) !== false;
        if (!$ok) {
            // FAILONERROR gadījumā HTTP kods tomēr ir pieejams — bloka signāls
            // (429/403/999) jāreģistrē arī lejupielāžu ceļā, ne tikai GET.
            ks_http_note_block($url, (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
            if (!$quiet) ks_log('  ✗ curl: ' . curl_error($ch));
        }
        unset($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'timeout' => TED_HTTP_TIMEOUT_S, 'user_agent' => TED_USER_AGENT, 'follow_location' => 1,
        ]]);
        $in = @fopen($url, 'rb', false, $ctx);
        if ($in !== false) {
            $ok = stream_copy_to_stream($in, $out) > 0;
            fclose($in);
        }
    }
    fclose($out);
    // Minimālais izmērs TIKAI arhīviem (aizsargs pret apcirstu tar/zip); JSON/CSV
    // galapunktiem tukša-bet-derīga atbilde ('[]', 0 rezultātu logā) ir < 1 KB,
    // un vienots 1024 slieksnis to kļūdaini rādīja kā "avots neatbild".
    $min = preg_match('/\.(tar\.gz|tgz|zip|gz)$/i', $dest) ? 1024 : 1;
    if ($ok && filesize($tmp) >= $min) {
        @rename($tmp, $dest);
        return true;
    }
    @unlink($tmp);
    return false;
}

/** POST JSON pieprasījums; atgriež atbildes ķermeni vai null. */
function ks_http_post_json(string $url, array $body, array $headers = []): ?string {
    if (ks_http_host_blocked(ks_http_host($url))) return null;
    if (ks_stage_over_budget()) return null; // posma laiks beidzies
    ks_http_throttle($url);
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    $hdrs = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_USERAGENT      => TED_USER_AGENT,
    ]);
    $ca = ks_ca_bundle();
    if ($ca !== null) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch); // JĀNOLASA PIRMS unset — bez šī taimauts bija pilnīgi kluss
    unset($ch);
    if ($res !== false && $code >= 200 && $code < 300) { ks_http_note_ok($url); return (string)$res; }

    // Taimauts / savienojuma kļūme: HTTP koda nav ($code === 0), tāpēc agrāk šis
    // gadījums izkrita cauri GAN bloka signāliem, GAN žurnālam (`if ($code !== 0)`).
    // Tieši tāpēc 2026-07-26 sinhronizācija 86 min klusi karājās uz MTender.
    if (in_array($errno, KS_TIMEOUT_ERRNOS, true)) {
        ks_log('  ⏱ ' . (parse_url($url, PHP_URL_HOST) ?: '?') . ' neatbildēja (' . curl_strerror($errno) . ').');
        ks_http_note_timeout($url);
        return null;
    }
    ks_http_note_block($url, $code);
    if ($code !== 0) ks_log_http_fail($url, $code);
    return null;
}

/**
 * Vienots ziņojums par neveiksmīgu HTTP atbildi.
 *
 * Bez šī posms klusi atgriež 0 ierakstu un žurnālā izskatās, ka AVOTS IR TUKŠS,
 * nevis ka mēs sitām par ātru vai adrese ir mainījusies. Tieši šī klusēšana
 * 2026-07-20 lika nepareizi secināt, ka Find a Tender "nav pieejams", kamēr
 * patiesībā tas atdeva 429 ar precīzu gaidīšanas laiku.
 */
function ks_log_http_fail(string $url, int $code): void {
    $host = parse_url($url, PHP_URL_HOST) ?: '?';
    ks_log("  ⚠ HTTP $code no $host");
}

/** GET pieprasījums ar pielāgotām galvenēm; atgriež ķermeni vai null.
 *  $jar — cepumu faila ceļš (sesijām, piem., EOJN). */
function ks_http_get(string $url, array $headers = [], int $timeout = 60, ?string $jar = null, bool $retry = true): ?string {
    if (ks_http_host_blocked(ks_http_host($url))) return null; // ķēde atvērta / atdzišana
    if (ks_stage_over_budget()) return null;                   // posma laiks beidzies
    ks_http_throttle($url);
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    // Retry-After galvene (standarta veids, kā serveris pasaka gaidīšanas laiku)
    $retryAfter = 0;
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_USERAGENT      => TED_USER_AGENT,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$retryAfter) {
            if (preg_match('/^Retry-After:\s*(\d+)/i', $line, $m)) $retryAfter = (int)$m[1];
            return strlen($line);
        },
    ]);
    $ca = ks_ca_bundle();
    if ($ca !== null) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    if ($jar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
    }
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch); // JĀNOLASA PIRMS unset — bez šī taimauts bija pilnīgi kluss
    unset($ch);
    if ($res !== false && $code >= 200 && $code < 300) { ks_http_note_ok($url); return (string)$res; }

    // Taimauts / savienojuma kļūme: HTTP koda nav ($code === 0), tāpēc agrāk šis
    // gadījums izkrita cauri GAN bloka signāliem, GAN žurnālam (`if ($code !== 0)`).
    // Tieši tāpēc 2026-07-26 sinhronizācija 86 min klusi karājās uz MTender.
    if (in_array($errno, KS_TIMEOUT_ERRNOS, true)) {
        ks_log('  ⏱ ' . (parse_url($url, PHP_URL_HOST) ?: '?') . ' neatbildēja (' . curl_strerror($errno) . ').');
        ks_http_note_timeout($url);
        return null;
    }

    // 429: avots pats pasaka, cik ilgi jāgaida (Retry-After galvene vai teksts
    // "retry after N seconds" — Find a Tender). Pagaidām un mēģinām VIENU reizi;
    // atkārtotais mēģinājums iet ar $retry=false, lai neveidotos bezgalīga ķēde.
    if ($code === 429 && $retry) {
        $wait = $retryAfter > 0 ? $retryAfter : 60;
        if (is_string($res) && preg_match('/retry after (\d+) second/i', $res, $m)) $wait = (int)$m[1];
        $wait = max(5, min(180, $wait));
        ks_log('  ⏳ 429 no ' . (parse_url($url, PHP_URL_HOST) ?: '?') . " — gaidu {$wait}s un mēģinu vēlreiz.");
        sleep($wait);
        return ks_http_get($url, $headers, $timeout, $jar, false);
    }
    // Bloka signāls (429 pēc neizdevušās atkārtošanas, 403/503/999) — eskalē
    // pauzi un pēc KS_HOST_BLOCK_TRIP signāliem pārtrauc sūtīt šim hostam.
    ks_http_note_block($url, $code, $retryAfter);
    if ($code !== 0) ks_log_http_fail($url, $code);
    return null;
}

/** POST application/x-www-form-urlencoded; atgriež ķermeni vai null. */
function ks_http_post_form(string $url, array $fields, array $headers = [], ?string $jar = null): ?string {
    if (ks_http_host_blocked(ks_http_host($url))) return null;
    if (ks_stage_over_budget()) return null; // posma laiks beidzies
    ks_http_throttle($url);
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_USERAGENT      => TED_USER_AGENT,
    ]);
    $ca = ks_ca_bundle();
    if ($ca !== null) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    // Sīkdatņu krātuve: KommersAnnons lapošana bez sesijas atgriež to pašu 1. lapu
    if ($jar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch); // JĀNOLASA PIRMS unset — bez šī taimauts bija pilnīgi kluss
    unset($ch);
    if ($res !== false && $code >= 200 && $code < 300) { ks_http_note_ok($url); return (string)$res; }

    // Taimauts / savienojuma kļūme: HTTP koda nav ($code === 0), tāpēc agrāk šis
    // gadījums izkrita cauri GAN bloka signāliem, GAN žurnālam (`if ($code !== 0)`).
    // Tieši tāpēc 2026-07-26 sinhronizācija 86 min klusi karājās uz MTender.
    if (in_array($errno, KS_TIMEOUT_ERRNOS, true)) {
        ks_log('  ⏱ ' . (parse_url($url, PHP_URL_HOST) ?: '?') . ' neatbildēja (' . curl_strerror($errno) . ').');
        ks_http_note_timeout($url);
        return null;
    }
    ks_http_note_block($url, $code);
    return null;
}

// ── OJ S numuru loģika ────────────────────────────────────────────────────────

function ks_issue_code(int $year, int $issue): string {
    return sprintf('%d%05d', $year, $issue);
}

function ks_package_url(int $year, int $issue): string {
    return sprintf(TED_PACKAGE_URL_FMT, ks_issue_code($year, $issue));
}

/** Darbadienu skaits no 1. janvāra līdz $untilYmd (noklusēti šodienai) — OJ S numura aplēse. */
function ks_estimate_issue(int $year, ?string $untilYmd = null): int {
    $tz = new DateTimeZone('Europe/Riga');
    $d = new DateTime("$year-01-01", $tz);
    $today = $untilYmd !== null ? new DateTime($untilYmd, $tz) : new DateTime('today', $tz);
    if ((int)$today->format('Y') > $year) $today = new DateTime("$year-12-31", $tz);
    $n = 0;
    while ($d <= $today) {
        if ((int)$d->format('N') <= 5) $n++;
        $d->modify('+1 day');
    }
    return max(1, $n);
}

/** Pēdējais importētais OJ S numurs šim gadam (0, ja nav). */
function ks_last_imported_issue(PDO $pdo, int $year): int {
    // file_key = 'TED:GGGGNNNNN' → OJ S numurs sākas 9. pozīcijā (1-indeksēts)
    $st = $pdo->prepare("SELECT MAX(CAST(substr(file_key, 9) AS INTEGER)) FROM imported_files WHERE file_key LIKE ?");
    $st->execute(['TED:' . $year . '%']);
    $v = $st->fetchColumn();
    return $v === null || $v === false ? 0 : (int)$v;
}

/**
 * Atrod jaunāko TED serverī eksistējošo OJ S numuru (bootstrap gadījumam).
 * Izmanto dažus HEAD pieprasījumus ap darbadienu aplēsi.
 */
function ks_find_latest_issue(int $year): int {
    $est = ks_estimate_issue($year);
    ks_log("🔎 Meklēju jaunāko TED paketi (aplēse: OJ S $est)...");
    // Ja aplēse eksistē — kāpj uz augšu; ja ne — uz leju.
    if (ks_http_head(ks_package_url($year, $est)) === 200) {
        $latest = $est;
        for ($i = $est + 1; $i <= $est + 6; $i++) {
            sleep(1);
            if (ks_http_head(ks_package_url($year, $i)) === 200) $latest = $i; else break;
        }
        return $latest;
    }
    for ($i = $est - 1; $i >= max(1, $est - 15); $i--) {
        sleep(1);
        if (ks_http_head(ks_package_url($year, $i)) === 200) return $i;
    }
    return 0;
}

// ── Paketes izvilkšana un imports ─────────────────────────────────────────────

/** Rekursīvi izdzēš mapi. */
function ks_rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

/** Izvelk tar.gz uz mapi; atgriež true/false. */
function ks_extract_targz(string $tarGz, string $destDir): bool {
    @mkdir($destDir, 0775, true);
    // 1. mēģinājums: PharData (bez ārējām komandām)
    try {
        $tarPath = $destDir . '/_pkg.tar';
        $gz = gzopen($tarGz, 'rb');
        if ($gz === false) throw new RuntimeException('gzopen neizdevās');
        $out = fopen($tarPath, 'wb');
        if ($out === false) { gzclose($gz); throw new RuntimeException('tmp tar nav rakstāms'); }
        while (!gzeof($gz)) {
            $chunk = gzread($gz, 1024 * 1024);
            if ($chunk === false) break;
            fwrite($out, $chunk);
        }
        gzclose($gz); fclose($out);
        $phar = new PharData($tarPath);
        $phar->extractTo($destDir, null, true);
        @unlink($tarPath);
        return true;
    } catch (Throwable $e) {
        ks_log('  ⚠ PharData neizdevās (' . $e->getMessage() . '), mēģinu tar komandu...');
    }
    // 2. mēģinājums: sistēmas tar
    if (function_exists('exec')) {
        $cmd = 'tar -xzf ' . escapeshellarg($tarGz) . ' -C ' . escapeshellarg($destDir) . ' 2>&1';
        @exec($cmd, $o, $rc);
        return $rc === 0;
    }
    return false;
}

/**
 * Kopīgais ierakstu rakstītājs, ko lieto visi ks_sync_* posmi.
 *
 * Atgriež KsWriter (lib/store.php), nevis jēlu PDOStatement: tas pievieno versiju
 * NEMAINĪGAJAM notice_versions žurnālam (ja saturs jauns/mainīts) UN atjaunina
 * `notices` pašreizējo skatu. Saskarne (->execute($n)) ir tā pati, tāpēc visi
 * esošie izsaukumi paliek nemainīgi.
 */
function ks_upsert_stmt(PDO $pdo): KsWriter {
    return new KsWriter($pdo);
}

/** Importē vienu TED paketi DB; atgriež importēto paziņojumu skaitu (-1 = kļūda).
 *  $historic — atpakaļejošā aizpilde: importē tikai to, kas izdzīvos glabāšanas
 *  politiku (aktīvos + svaigos), lai DB nepiebriest ar seniem rezultātiem. */
function ks_import_package(PDO $pdo, string $tarGz, int $year, int $issue, bool $historic = false): int {
    $issueCode = ks_issue_code($year, $issue);
    $extractDir = konkursi_tmp_dir() . '/x_' . $issueCode;
    ks_rrmdir($extractDir);
    if (!ks_extract_targz($tarGz, $extractDir)) {
        ks_rrmdir($extractDir);
        return -1;
    }

    $stmt = ks_upsert_stmt($pdo);

    $count = 0; $files = 0; $stopped = false;
    $pdo->beginTransaction();
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (!$f->isFile() || strtolower($f->getExtension()) !== 'xml') continue;
            $files++;
            if ($files % 500 === 0 && ks_stop_requested()) { $stopped = true; break; }
            $xml = @file_get_contents($f->getPathname());
            if ($xml === false || $xml === '') continue;
            $n = ted_parse_xml($xml, $issueCode);
            if ($n === null) continue;
            if ($historic && (!ks_within_retention($n) || !ks_backfill_keep($n))) continue;
            $stmt->execute($n);
            $count++;
        }
        if (!$stopped) {
            $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)')
                ->execute(['TED:' . $issueCode, date('c'), $count]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        ks_rrmdir($extractDir);
        throw $e;
    }
    ks_rrmdir($extractDir);
    if ($stopped) {
        ks_log("  🛑 STOP pieprasīts — pakete $issueCode netiek atzīmēta kā pabeigta (nākamreiz importēs no jauna).");
        return -1;
    }
    return $count;
}

/**
 * Resursa versijas atzīme (Last-Modified) vai null, ja resursa nav / kļūda.
 *
 * Vispirms HEAD, tad diapazona GET. ANAC serveris uz HEAD atbild ar 269 baitu
 * pseido-atbildi BEZ Last-Modified, bet uz 'Range: 0-0' dod 206 ar pareizu
 * galveni un vienu baitu ķermeņa — tāpēc otrais mēģinājums ir obligāts.
 *
 * Ja galvenes nav nemaz, atgriež garumu vai šodienas datumu, NEVIS nemainīgu
 * 'unknown': konstante padarītu versiju pārbaudi inertu — izsaucējs faila
 * atslēgu atrastu 'imported_files' un mūžīgi izlaistu resursu, arī pēc tam,
 * kad avots to pārpublicējis (tieši tā ANAC iestrēga uz vienu mēneša versiju).
 */
function ks_http_last_modified(string $url): ?string {
    ks_http_throttle($url);
    if (!function_exists('curl_init')) return null;

    $probe = function (bool $ranged) use ($url): array {
        $ch = curl_init($url);
        $lm = null; $len = null;
        $opts = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => TED_USER_AGENT,
            CURLOPT_CAINFO         => ks_ca_bundle() ?? (ini_get('openssl.cafile') ?: null),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$lm, &$len) {
                if (stripos($line, 'Last-Modified:') === 0)  $lm  = trim(substr($line, 14));
                if (stripos($line, 'Content-Range:') === 0)  $len = trim(substr($line, 14));
                elseif (stripos($line, 'Content-Length:') === 0 && $len === null) {
                    $len = trim(substr($line, 15));
                }
                return strlen($line);
            },
        ];
        if ($ranged) $opts[CURLOPT_RANGE] = '0-0';
        else         $opts[CURLOPT_NOBODY] = true;
        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch);
        return [$code, $lm, $len];
    };

    [$code, $lm, $len] = $probe(false);
    if ($code !== 200 && $code !== 206) return null;
    if ($lm !== null) return $lm;

    [$code2, $lm2, $len2] = $probe(true);
    if ($code2 === 200 || $code2 === 206) {
        if ($lm2 !== null) return $lm2;
        $len = $len2 ?? $len;
    }
    // Content-Range ('bytes 0-0/45644853') satur pilno garumu — der par versiju.
    $total = null;
    if (is_string($len) && preg_match('#/(\d+)\s*$#', $len, $m)) $total = (int)$m[1];
    elseif (is_string($len) && ctype_digit(trim($len)))          $total = (int)trim($len);

    // ANAC neeksistējošam mēnesim atdod HTTP 200 ar ~269 baitu stub, nevis 404.
    // Bez Last-Modified UN ar sīku ķermeni resurss īstenībā nav publicēts.
    if ($total !== null && $total < 1024) return null;

    if ($total !== null) return 'len:' . $total;
    return 'day:' . konkursi_today();
}

/**
 * TED atpakaļejošā aizpilde (tikai dziļajā režīmā): lejupielādē paketes PIRMS
 * senākās importētās, līdz sasniegts aktīvā loga sākums (~43 darbadienas).
 * Importē tikai retention/backfill_keep izturošos (aktīvos + svaigos).
 * @return int importēto paziņojumu skaits
 */
function ks_sync_ted_back(PDO $pdo): int {
    if (!konkursi_deep()) return 0;
    $tz = new DateTimeZone('Europe/Riga');
    $year = (int)(new DateTimeImmutable('now', $tz))->format('Y');

    $st = $pdo->prepare("SELECT MIN(CAST(substr(file_key, 9) AS INTEGER)) FROM imported_files WHERE file_key LIKE ?");
    $st->execute(['TED:' . $year . '%']);
    $min = (int)($st->fetchColumn() ?: 0);
    if ($min <= 1) return 0;

    $cutDate = (new DateTimeImmutable(konkursi_today(), $tz))->modify('-' . konkursi_deep_days() . ' days');
    $target = ((int)$cutDate->format('Y') < $year) ? 1
        : max(1, ks_estimate_issue($year, $cutDate->format('Y-m-d')));
    if ($min - 1 < $target) return 0;

    $cap = ks_cap(TED_MAX_PACKAGES_PER_RUN, 60);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $imported = 0; $done = 0;
    ks_log("⏪ TED atpakaļejošā aizpilde: no OJ S " . ($min - 1) . " līdz ~$target (aktīvā loga sākums).");

    for ($i = $min - 1; $i >= $target && $done < $cap; $i--) {
        if (ks_stop_requested()) { ks_log('🛑 STOP pieprasīts — aizpilde pārtraukta.'); break; }
        $issueCode = ks_issue_code($year, $i);
        $chk->execute(['TED:' . $issueCode]);
        if ($chk->fetchColumn() !== false) continue;

        $tarPath = konkursi_tmp_dir() . '/' . $issueCode . '.tar.gz';
        if (!ks_http_download(ks_package_url($year, $i), $tarPath, true)) {
            ks_log("  · OJ S $i nav pieejama — izlaižu.");
            $done++;
            sleep(1);
            continue;
        }
        $c = ks_import_package($pdo, $tarPath, $year, $i, true);
        @unlink($tarPath);
        if ($c < 0) break;
        $imported += $c;
        $done++;
        ks_log("  ✓ Pakete $issueCode (vēsture) → $c aktīvie/svaigie.");
        sleep(TED_REQUEST_DELAY_S);
    }
    if ($imported > 0) ks_log("  ✓ TED vēsture kopā: $imported paziņojumi ($done paketes).");
    return $imported;
}

// ── IUB (LV nacionālie) sinhronizācija ────────────────────────────────────────

/**
 * Lejupielādē un importē trūkstošos IUB dienas failus (pēdējās IUB_BACKFILL_DAYS dienas).
 * Stabilajā režīmā tas ir 1 pieprasījums dienā (tikai šodienas fails);
 * 404 senākām dienām atzīmē kā tukšas, lai tās nezondētu atkārtoti.
 * @return int importēto paziņojumu skaits
 */
function ks_sync_iub(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $today = new DateTimeImmutable('today', $tz);
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    $requests = 0;

    for ($back = 0; $back < IUB_BACKFILL_DAYS; $back++) {
        if (ks_stop_requested()) { ks_log('🛑 STOP pieprasīts — IUB posms pārtraukts.'); break; }
        $d = $today->modify("-$back days");
        $dateStr = $d->format('d-m-Y');           // faila nosaukuma formāts
        $fileKey = 'IUB:' . $dateStr . '.json';

        $cur = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
        $cur->execute([$fileKey]);
        if ($cur->fetchColumn()) continue;        // jau importēts vai atzīmēts kā tukšs

        $url = sprintf(IUB_URL_FMT, $d->format('Y'), $d->format('m'), $dateStr);
        if ($requests++ > 0) sleep(IUB_REQUEST_DELAY_S);

        $tmp = konkursi_tmp_dir() . '/iub_' . $dateStr . '.json';
        $data = null;
        if (ks_http_download($url, $tmp, true)) {
            $data = json_decode((string)file_get_contents($tmp), true);
            @unlink($tmp);
        }
        if (!is_array($data)) {
            // Nav faila: 404, tīkla kļūda VAI IUB soft-404 (HTML lapa ar HTTP 200 statusu).
            // Senas dienas atzīmē kā tukšas, lai nezondē mūžīgi; svaigās (šodien/vakar)
            // atstāj — fails parādās dienas gaitā.
            if ($back >= IUB_SKIP_404_AFTER_DAYS) {
                $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,0)')
                    ->execute([$fileKey, date('c')]);
            }
            ks_log("  · IUB $dateStr — faila (vēl) nav.");
            continue;
        }

        $pubDate = $d->format('Y-m-d');
        $count = 0;
        $skippedOver = 0;
        $pdo->beginTransaction();
        try {
            foreach ($data as $item) {
                if (!is_array($item)) continue;
                $n = iub_parse_item($item, $dateStr . '.json', $pubDate);
                if ($n === null) { $skippedOver++; continue; }
                $stmt->execute($n);
                $count++;
            }
            $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)')
                ->execute([$fileKey, date('c'), $count]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $imported += $count;
        ks_log("  ✓ IUB $dateStr → $count nacionālie paziņojumi ($skippedOver virs-sliekšņa izlaisti — tie ir TED).");
    }
    return $imported;
}

// ── CVP IS API (LT nacionālie) sinhronizācija ─────────────────────────────────

/** Nolasa vienu API lapu; atgriež ierakstu masīvu, [] beigām vai null kļūdai. */
function ks_cvpis_api_page(int $pageNum): ?array {
    $raw = ks_http_post_json(CVPIS_API_URL,
        ['pageSize' => CVPIS_API_PAGE, 'pageNum' => $pageNum],
        ['apiKey: ' . CVPIS_API_KEY]);
    if ($raw === null) return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

/**
 * TED LT ierakstu atslēgas (nosaukums|pasūtītājs|kategorija) atmiņā.
 * aboveThreshold karogs neaizķer visu: daļa pirkumu (īpaši DPS un sabiedrisko
 * pakalpojumu sektorā) nonāk TED, lai gan nacionāli skaitās zem sliekšņa.
 * @return array<string,true>
 */
function ks_cvpis_ted_keys(PDO $pdo): array {
    $keys = [];
    $q = $pdo->query("SELECT title, buyer_name, category FROM notices WHERE source='TED' AND buyer_country='LT'");
    foreach ($q as $r) {
        $keys[ks_cvpis_dedup_key((string)$r['title'], (string)$r['buyer_name'], (string)$r['category'])] = true;
    }
    return $keys;
}

/** Normalizēta dedup atslēga (mazie burti, saspiestas atstarpes). */
function ks_cvpis_dedup_key(string $title, string $buyer, string $category): string {
    $n = fn(string $s) => preg_replace('/\s+/u', ' ', mb_strtolower(trim($s), 'UTF-8'));
    return $n($title) . '|' . $n($buyer) . '|' . $category;
}

/** Lapas jaunākais publikācijas datums (YYYY-MM-DD) vai null. */
function ks_cvpis_page_max_date(array $rows): ?string {
    $max = null;
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        [$d, ] = cvpis_api_date(is_string($r['datePublished'] ?? null) ? $r['datePublished'] : null);
        if ($d !== null && ($max === null || $d > $max)) $max = $d;
    }
    return $max;
}

/**
 * Importē LT paziņojumus no CVP IS integrācijas API.
 *
 * Ieraksti sakārtoti augoši pēc publikācijas datuma, tāpēc jaunākie ir pēdējās
 * lapās: vispirms ar dubultošanu+bisekciju atrod pēdējo nepilno lapu, tad iet
 * atpakaļ, līdz lapas datumi izkrīt no glabāšanas loga. Tā vienā palaišanā
 * atsvaidzinās arī jau esošo ierakstu statusi (Awarded/Concluded/Cancelled) —
 * tieši no tā rodas rezultātu un "citu" kategorijas, kuru CSV plūsmā nebija.
 * @return int importēto paziņojumu skaits
 */
function ks_sync_cvpis_api(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0; $skipped = 0; $dupTed = 0; $pagesRead = 0;
    $cut = ks_window_start(ks_active_cutoff()); // cik dziļi atpakaļ ejam
    $tedKeys = ks_cvpis_ted_keys($pdo);

    // 1. Pēdējā nepilnā lapa: dubulto, līdz tukša, tad bisekcija.
    $lo = 1; $hi = 1;
    while (true) {
        $rows = ks_cvpis_api_page($hi);
        $pagesRead++;
        if ($rows === null) { ks_log('  ⚠ CVP IS API nesasniedzams (lapa ' . $hi . ').'); return 0; }
        if ($rows === []) break;
        $lo = $hi;
        $hi *= 2;
        if ($hi > 10000) break; // drošinātājs
        sleep(CVPIS_API_DELAY_S);
    }
    while ($lo + 1 < $hi) {
        $mid = intdiv($lo + $hi, 2);
        sleep(CVPIS_API_DELAY_S);
        $rows = ks_cvpis_api_page($mid);
        $pagesRead++;
        if ($rows === null) break;
        if ($rows === []) $hi = $mid; else $lo = $mid;
    }
    $last = $lo;
    ks_log("  · CVP IS API: pēdējā lapa $last (nolasītas $pagesRead zondes).");

    // 2. No beigām atpakaļ, līdz izkrīt no loga.
    for ($i = 0; $i < CVPIS_API_MAX_PAGES; $i++) {
        if (ks_stop_requested()) { ks_log('🛑 STOP pieprasīts — CVP IS posms pārtraukts.'); break; }
        $page = $last - $i;
        if ($page < 1) break;
        sleep(CVPIS_API_DELAY_S);
        $rows = ks_cvpis_api_page($page);
        if ($rows === null) { ks_log("  ⚠ CVP IS API lapa $page neizdevās — pārtraucu."); break; }
        if ($rows === []) continue;

        $pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $n = cvpis_api_parse_item($r);
                if ($n === null) { $skipped++; continue; }
                if (!ks_within_retention($n)) { $skipped++; continue; }
                $k = ks_cvpis_dedup_key((string)$n['title'], (string)$n['buyer_name'], (string)$n['category']);
                if (isset($tedKeys[$k])) { $dupTed++; continue; } // jau TED plūsmā
                $stmt->execute($n);
                $imported++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $maxDate = ks_cvpis_page_max_date($rows);
        if ($maxDate !== null && $maxDate < $cut) {
            ks_log("  · CVP IS API: lapa $page jau ārpus loga ($maxDate < $cut) — apstājos.");
            break;
        }
    }
    ks_log("  ✓ CVP IS API: $imported importēti, $skipped izlaisti (virs sliekšņa / DPS sistēmas / ārpus loga), $dupTed jau TED plūsmā.");
    return $imported;
}

// ── CVP IS (LT nacionālie) sinhronizācija ─────────────────────────────────────

/**
 * Nolasa CVP IS "Naujausi pirkimai" CSV (jaunākie pirmie) un jauniem pirkumiem
 * lejupielādē publisko detaļu lapu. Virs-sliekšņa pirkumus ("Virš" vai ar TED
 * atsauci) izlaiž un atzīmē imported_files (0), lai detaļas nezondētu atkārtoti.
 * Jau importētajiem no saraksta atsvaidzina statusu (kategoriju) un termiņu.
 * @return int importēto paziņojumu skaits
 */
function ks_sync_cvpis(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    $detailsFetched = 0;
    $skippedOver = 0;
    $maxPages   = 1; // CSV eksports nelapo — dziļajā aizpildē ņem VISU sarakstu ar lielu ps
    $maxDetails = ks_cap(CVPIS_MAX_DETAILS_PER_RUN, 4000);
    $freshCut   = ks_backfill_fresh_cut();
    $cutExpired = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days')->format('Y-m-d');

    $chk = $pdo->prepare('SELECT notice_count FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $upd = $pdo->prepare('UPDATE notices SET category = ?, deadline_date = COALESCE(?, deadline_date) WHERE id = ?');

    for ($page = 1; $page <= $maxPages; $page++) {
        if (ks_stop_requested()) break;

        $tmp = konkursi_tmp_dir() . '/cvpis_p' . $page . '.csv';
        $url = sprintf(CVPIS_LIST_URL_FMT, $page);
        if (konkursi_deep()) $url = str_replace('T01_ps=100', 'T01_ps=3000', $url);
        if (!ks_http_download($url, $tmp, true)) {
            ks_log('  ⚠ CVP IS saraksta lejupielāde neizdevās.');
            break;
        }
        $rows = cvpis_parse_csv((string)file_get_contents($tmp));
        @unlink($tmp);
        if (!$rows) {
            ks_log('  ⚠ CVP IS CSV tukšs vai neparsējams (mainīts formāts?).');
            break;
        }

        $newOnPage = 0; $freshOnPage = 0;
        foreach ($rows as $row) {
            if ($row['pub_date'] === null || $row['pub_date'] >= ks_active_cutoff()) $freshOnPage++;
            if (ks_stop_requested()) break 2;
            $key = 'CVPIS:' . $row['resource_id'];
            $chk->execute([$key]);
            $known = $chk->fetchColumn();
            if ($known !== false) {
                // Jau apstrādāts: importētajiem atsvaidzina statusu/termiņu no saraksta
                if ((int)$known === 1) {
                    $upd->execute([cvpis_category($row['status']), $row['deadline_date'], 'CVPIS-' . $row['resource_id']]);
                }
                continue;
            }
            if ($row['kind'] !== 'cft') {
                // DPS (dinamiskās pirkumu sistēmas) stubi — izlaiž uz visiem laikiem
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $newOnPage++;
            // Dziļajā aizpildē: vecas rindas ar sen beigušos termiņu neko nedos
            // (retention/backfill_keep tās izmestu) — netērē detaļas pieprasījumu.
            if (konkursi_deep() && $row['pub_date'] !== null && $row['pub_date'] < $freshCut
                && $row['deadline_date'] !== null && $row['deadline_date'] < $cutExpired) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            if ($detailsFetched >= $maxDetails) continue; // paliks nākamajai palaišanai

            sleep(CVPIS_REQUEST_DELAY_S);
            $dtmp = konkursi_tmp_dir() . '/cvpis_d.html';
            if (!ks_http_download(sprintf(CVPIS_DETAIL_URL_FMT, $row['resource_id']), $dtmp, true)) {
                ks_log('  ⚠ CVP IS detaļas neizdevās: ' . $row['resource_id']);
                continue; // bez atzīmes — mēģinās nākamreiz
            }
            $detailsFetched++;
            $d = cvpis_parse_detail((string)file_get_contents($dtmp));
            @unlink($dtmp);
            if (!$d) {
                ks_log('  ⚠ CVP IS detaļu lapa neparsējama: ' . $row['resource_id']);
                continue;
            }
            $n = cvpis_build_notice($row, $d);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                $skippedOver++;
                $rec->execute([$key, date('c'), 0]); // virs sliekšņa / ārpus loga
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
        // Dziļajā aizpildē 1. lapa mēdz būt pilnībā zināma (ikdienas palaišanas) —
        // tur apstājas tikai pie datuma horizonta.
        if (konkursi_deep() ? $freshOnPage === 0 : $newOnPage === 0) break;
    }
    if ($imported > 0 || $skippedOver > 0) {
        ks_log("  ✓ CVP IS: $imported nacionālie importēti, $skippedOver virs-sliekšņa izlaisti (tie ir TED).");
    }
    return $imported;
}

/**
 * Vai rinda vispār izdzīvotu glabāšanas politiku? Ja ne — nav jēgas importēt
 * (prune to izdzēstu, un nākamajā reizē tā atkal izskatītos "jauna" → mūžīgs cikls).
 */
/**
 * Vecs bezdatuma "General Procurement Notice" / pieteikums (EBRD/WB/UDBUD): aktīvs
 * iepirkums BEZ termiņa un ar publikāciju vecāku par KONKURSI_KEEP_NODEADLINE_DAYS =
 * neaktuāls, tāpēc neimportējam (tas pats logs, ko displejs tāpat piemēro).
 * Bezdatuma avoti ar NULL publikāciju (Comdia/BKMS) šeit NEIETILPST — tos nefiltrē.
 */
function ks_stale_nodeadline(array $n): bool {
    static $cut = null;
    if ($cut === null) {
        $cut = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
            ->modify('-' . KONKURSI_KEEP_NODEADLINE_DAYS . ' days')->format('Y-m-d');
    }
    return ($n['category'] ?? '') === 'iepirkumi'
        && ($n['deadline_date'] ?? null) === null
        && ($n['publication_date'] ?? null) !== null
        && $n['publication_date'] < $cut;
}

function ks_within_retention(array $n): bool {
    static $cutResults = null, $cutExpired = null;
    if ($cutResults === null) {
        $today = new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga'));
        $cutResults = $today->modify('-' . KONKURSI_KEEP_RESULTS_DAYS . ' days')->format('Y-m-d');
        $cutExpired = $today->modify('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days')->format('Y-m-d');
    }
    if ($n['category'] === 'iepirkumi') {
        return $n['deadline_date'] === null || $n['deadline_date'] >= $cutExpired;
    }
    // Avota izņēmums (Īrijas nacionālie rezultāti — 180 d, sk. config)
    $days = KONKURSI_KEEP_RESULTS_BY_SOURCE[$n['source'] ?? ''] ?? null;
    if ($days !== null) {
        $cut = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
            ->modify('-' . $days . ' days')->format('Y-m-d');
        return $n['publication_date'] === null || $n['publication_date'] >= $cut;
    }
    return $n['publication_date'] === null || $n['publication_date'] >= $cutResults;
}

/**
 * Dziļās aizpildes politika: aktīvos konkursus ņem visā logā, bet vecus
 * rezultātus/grozījumus (>14 d) neimportē — tie ir arhīvs, kas pildās dabiski
 * uz priekšu, un bez šī filtra DB izaugtu vairākkārt (īpaši TED/BKMS/BZP).
 * Parastajā režīmā vienmēr true (nulle izmaiņu ikdienas uzvedībā).
 */
function ks_backfill_keep(array $n): bool {
    if (!konkursi_deep()) return true;
    if ($n['category'] === 'iepirkumi') return true;
    // REZULTĀTI: 14 d svaiguma robeža noņemta 2026-07-20 (lietotāja lēmums).
    // Tā radās, pirms pastāvēja KONKURSI_RESULTS_CAP, lai arhīvs nepūstu; tagad
    // apjomu noturē griesti (1000/valsts) + ks_within_retention (60 d), un
    // robeža tikai neļāva pusei valstu tos 1000 vispār sasniegt (SI 222/1014,
    // NL 68, NO 21 utt.). Glabāšanas logu piemēro ks_within_retention.
    if ($n['category'] === 'rezultati') return true;
    // Grozījumi/citi paliek pie svaiguma robežas — tiem griestu nav un
    // vēsturiskā vērtība maza (grozījums bez dzīva termiņa ir arhīvs).
    $days = KONKURSI_KEEP_RESULTS_BY_SOURCE[$n['source'] ?? ''] ?? null;
    if ($days !== null) {
        $cut = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
            ->modify('-' . $days . ' days')->format('Y-m-d');
        return $n['publication_date'] === null || $n['publication_date'] >= $cut;
    }
    static $cut2 = null;
    if ($cut2 === null) {
        $cut2 = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
            ->modify('-' . KONKURSI_BACKFILL_FRESH_DAYS . ' days')->format('Y-m-d');
    }
    return $n['publication_date'] === null || $n['publication_date'] >= $cut2;
}

/** Dziļās aizpildes svaiguma robeža (YYYY-MM-DD) ne-iepirkumu kategorijām. */
function ks_backfill_fresh_cut(): string {
    return (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . KONKURSI_BACKFILL_FRESH_DAYS . ' days')->format('Y-m-d');
}

/** Aktīvā loga sākuma datums (YYYY-MM-DD): šodiena mīnus 60 d (vai dziļuma dienas). */
function ks_active_cutoff(): string {
    $days = konkursi_deep() ? konkursi_deep_days() : KONKURSI_ACTIVE_WINDOW_DAYS;
    return (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . $days . ' days')->format('Y-m-d');
}

// ── RHR (EE) sinhronizācija ───────────────────────────────────────────────────

/**
 * Lejupielādē Igaunijas reģistra mēneša eForms XML dumpus un importē caur
 * esošo TED parseri. Divas atsevišķas plūsmas ar identisku formātu:
 *   notice       → izsludinājumi un grozījumi (ContractNotice)
 *   notice_award → procedūru rezultāti un līgumi (ContractAwardNotice)
 * Mēneša fails aug dienas gaitā — pārimportē katru reizi (upsert idempotents);
 * mēneša sākumā vienreiz paņem arī iepriekšējo mēnesi.
 * Dedup: ja UUID jau ir DB ar source='TED', izlaiž (ES līmeņa paziņojums).
 * @return int importēto skaits
 */
function ks_sync_rhr(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $now = new DateTimeImmutable('now', $tz);
    $months = [[(int)$now->format('Y'), (int)$now->format('n')]];
    if ((int)$now->format('j') <= 3) {
        $prev = $now->modify('first day of previous month');
        $months[] = [(int)$prev->format('Y'), (int)$prev->format('n')];
    }
    if (konkursi_deep()) {
        // Dziļā aizpilde: + iepriekšējie mēneši, līdz nosegts aktīvais logs
        $d = $now->modify('first day of previous month');
        $edge = $now->modify('-' . konkursi_deep_days() . ' days');
        while ($d >= $edge->modify('first day of this month')) {
            $ym = [(int)$d->format('Y'), (int)$d->format('n')];
            if (!in_array($ym, $months, true)) $months[] = $ym;
            $d = $d->modify('first day of previous month');
        }
    }

    $imported = 0;
    // Rezultātus ņem PĒC izsludinājumiem: rezultāta paziņojumā nav reģistra
    // iepirkuma numura, to atrod pēc contract_folder_id no jau importētās rindas.
    foreach ([
        ['notice',       RHR_MONTH_URL_FMT,       'RHR',  'nacionālie'],
        ['notice_award', RHR_AWARD_MONTH_URL_FMT, 'RHRA', 'rezultāti'],
    ] as [$stream, $urlFmt, $fileKey, $label]) {
        $imported += ks_rhr_import_stream($pdo, $months, $now, $stream, $urlFmt, $fileKey, $label);
    }
    return $imported;
}

/**
 * Importē vienu RHR plūsmu (notice vai notice_award) par norādītajiem mēnešiem.
 * @param array<array{0:int,1:int}> $months
 * @return int importēto skaits
 */
function ks_rhr_import_stream(PDO $pdo, array $months, DateTimeImmutable $now,
                              string $stream, string $urlFmt, string $fileKey,
                              string $label): int {
    $stmt = ks_upsert_stmt($pdo);
    $srcQ = $pdo->prepare('SELECT source FROM notices WHERE id = ?');
    $chkM = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $curYm = $now->format('Y-m');
    $isAward = ($stream === 'notice_award');
    $folders = $isAward ? ks_rhr_folder_map($pdo) : [];
    $imported = 0;

    foreach ($months as [$y, $m]) {
        if (ks_stop_requested()) break;
        $ym = sprintf('%d-%02d', $y, $m);
        if (konkursi_deep() && $ym !== $curYm) {
            // Atkārtota dziļā palaišana: pabeigtus mēnešus nelādē vēlreiz
            $chkM->execute([$fileKey . ':' . $ym]);
            if ($chkM->fetchColumn() !== false) continue;
        }
        $tmp = konkursi_tmp_dir() . "/{$stream}_$ym.xml";
        if (!ks_http_download(sprintf($urlFmt, $y, $m), $tmp, true)) {
            ks_log("  ⚠ RHR $stream $ym dumps nav pieejams.");
            continue;
        }

        $count = 0; $skippedTed = 0;
        $reader = new XMLReader();
        if (!$reader->open($tmp)) { @unlink($tmp); continue; }
        $pdo->beginTransaction();
        try {
            // Straumējošā apstrāde: elements 1. dziļumā → readOuterXml + next() (uz nākamo māsu);
            // viss pārējais (teksti, atstarpes, sakne) → read().
            while (true) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === 1) {
                    $xml = $reader->readOuterXml();
                    if ($xml !== '') {
                        $n = ted_parse_xml($xml, 'RHR:' . $ym, 'EE');
                        if ($n !== null && (!ks_within_retention($n) || !ks_backfill_keep($n))) $n = null;
                        if ($n !== null) {
                            // ES līmeņa paziņojums ar to pašu UUID jau ir no TED → izlaiž
                            $srcQ->execute([$n['id']]);
                            if ($srcQ->fetchColumn() === 'TED') {
                                $skippedTed++;
                            } else {
                                $n['source'] = 'RHR';
                                // Reģistra iepirkuma numurs no dokumentu saites (…/procurement/9480424/…)
                                if ($n['publication_number'] === null && is_string($n['document_url'] ?? null)
                                    && preg_match('#/procurement/(\d+)#', $n['document_url'], $mm)) {
                                    $n['publication_number'] = $mm[1];
                                }
                                if ($isAward) ks_rhr_link_award($n, $folders);
                                $stmt->execute($n);
                                $count++;
                            }
                        }
                    }
                    if (!$reader->next()) break;
                } elseif (!$reader->read()) {
                    break;
                }
            }
            $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)')
                ->execute([$fileKey . ':' . $ym, date('c'), $count]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $reader->close();
            @unlink($tmp);
            throw $e;
        }
        $reader->close();
        @unlink($tmp);
        $imported += $count;
        ks_log("  ✓ RHR $ym → $count $label ($skippedTed jau ir TED plūsmā).");
    }
    return $imported;
}

/**
 * contract_folder_id → reģistra iepirkuma numurs no jau importētajām EE rindām.
 * Vaicājums ir sīks (daži simti rindu), tāpēc karti veido atmiņā, nevis pievieno
 * indeksu 485 MB tabulai.
 * @return array<string,string>
 */
function ks_rhr_folder_map(PDO $pdo): array {
    $map = [];
    $q = $pdo->query("SELECT contract_folder_id, publication_number FROM notices
                      WHERE source = 'RHR' AND contract_folder_id IS NOT NULL
                        AND publication_number IS NOT NULL");
    foreach ($q->fetchAll(PDO::FETCH_NUM) as [$folder, $num]) $map[$folder] = $num;
    return $map;
}

/**
 * Rezultāta paziņojumā nav ne reģistra numura, ne saites — XML nes tikai UUID.
 * Ja tā paša iepirkuma izsludinājums ir DB, numuru un saiti pārmanto no tā;
 * ja nav (rezultāts par iepirkumu ārpus glabāšanas loga), paliek bez saites —
 * labāk nekāda saite nekā uzminēta.
 * @param array<string,mixed> $n
 * @param array<string,string> $folders
 */
function ks_rhr_link_award(array &$n, array $folders): void {
    $folder = $n['contract_folder_id'] ?? null;
    if (!is_string($folder) || !isset($folders[$folder])) return;
    $num = $folders[$folder];
    if ($n['publication_number'] === null) $n['publication_number'] = $num;
    if (empty($n['document_url'])) $n['document_url'] = sprintf(RHR_PROCUREMENT_URL_FMT, $num);
}

// ── Hilma (FI) sinhronizācija ─────────────────────────────────────────────────

/** @return int importēto skaits */
function ks_sync_hilma(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $since = konkursi_meta_get($pdo, 'hilma_since');
    if ($since === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
        $since = (new DateTimeImmutable('now', $tz))->modify('-' . HILMA_BACKFILL_DAYS . ' days')->format('Y-m-d');
    }
    $since = ks_window_start($since);
    $maxPages = ks_cap(HILMA_MAX_PAGES, 60);
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    $complete = false;
    $lastPub = null;   // pēdējā redzētā datePublished (augošā secībā = tālākā)
    $total = null;     // @odata.count — cik ierakstu logā patiesībā ir
    $fetched = 0;

    // Secība AUGOŠA: lapu limits tad nogriež loga JAUNO galu, ko nākamā palaišana
    // tāpat paņem. Ar dilstošu secību limits nogrieza VECO galu, un, ja logā ir
    // vairāk par maxPages*100, tā aste kļuva mūžīgi neaizsniedzama (kursors nekustas
    // → nākamreiz logs vēl garāks → badināšanās, kas pati sevi pasliktina).
    for ($page = 0; $page < $maxPages; $page++) {
        if (ks_stop_requested()) break;
        $res = ks_http_post_json(HILMA_SEARCH_URL, [
            'filter'  => "isNationalProcurement eq true and datePublished ge {$since}T00:00:00Z",
            'orderby' => 'datePublished asc',
            'top'     => 100,
            'skip'    => $page * 100,
            'count'   => true,
        ], ['Ocp-Apim-Subscription-Key: ' . HILMA_API_KEY]);
        if ($res === null) { ks_log('  ⚠ Hilma API neatbild.'); break; }
        $data = json_decode($res, true);
        $items = is_array($data['value'] ?? null) ? $data['value'] : [];
        if ($total === null && isset($data['@odata.count'])) $total = (int)$data['@odata.count'];
        $fetched += count($items);
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (is_string($it['datePublished'] ?? null)) $lastPub = $it['datePublished'];
            $n = hilma_parse_item($it);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
            $stmt->execute($n);
            ks_hilma_supersede($pdo, $it);
            $imported++;
        }
        // Pabeigtību spriež pēc @odata.count, nevis pēc lapas garuma: nopūsta
        // (throttled) atbilde ar 200 OK un īsu value citādi izskatītos pēc
        // "logs izsmelts" un pārsviestu kursoru pāri vēl neizlasītiem ierakstiem.
        if (!$items) break;
        if ($total !== null) {
            if ($fetched >= $total) { $complete = true; break; }
        } elseif (count($items) < 100) { $complete = true; break; }
        sleep(1);
    }
    // Kursors: pabeigtam logam — vakardiena; nepabeigtam — reāli sasniegtā diena.
    // Robežas dienu pārlasa no sākuma (upsert ir idempotents), lai nepaliek caurums.
    $yesterday = (new DateTimeImmutable('now', $tz))->modify('-1 day')->format('Y-m-d');
    if ($complete) {
        konkursi_meta_set($pdo, 'hilma_since', $yesterday);
    } elseif ($lastPub !== null) {
        $reached = substr($lastPub, 0, 10);
        if ($reached > $since) {
            konkursi_meta_set($pdo, 'hilma_since', min($reached, $yesterday));
        } else {
            // Viena diena nesatilpst lapu limitā — kustēt kursoru nozīmētu zaudēt datus.
            ks_log("  ⚠ Hilma: $since nesatilpst $maxPages lapās — palielini HILMA_MAX_PAGES.");
        }
    }
    $imported += ks_sync_hilma_tail($pdo, $stmt);
    if ($imported > 0) ks_log("  ✓ Hilma → $imported nacionālie paziņojumi.");
    return $imported;
}

/**
 * Grozījums aizstāj priekšteci. Hilma grozījuma paziņojumā ir PILNI dati (termiņš,
 * CPV, apraksts), tāpēc vecā rinda ir novecojusi un radītu vienu un to pašu
 * iepirkumu sarakstā divreiz. Attiecas tikai uz izsludinājumu grozījumiem —
 * piešķīruma paziņojums priekšteci NEDZĒŠ (izsludinājums un rezultāts ir
 * atsevišķi ieraksti dažādās cilnēs).
 */
function ks_hilma_supersede(PDO $pdo, array $it): void {
    if (($it['isCorrigendum'] ?? false) !== true) return;
    if ((string)($it['mainType'] ?? '') !== 'ContractNotices') return;
    $prev = $it['previousSearchIds'] ?? null;
    if (!is_array($prev) || !$prev) return;
    static $del = null;
    if ($del === null) $del = $pdo->prepare("DELETE FROM notices WHERE id = ? AND source = 'HILMA'");
    foreach ($prev as $p) {
        if (is_string($p) && $p !== '') $del->execute(['HILMA-' . $p]);
    }
}

/**
 * Ilgtermiņa aste: atvērti konkursi, kas publicēti PIRMS publikācijas loga.
 * Kursors iet pa datePublished, tāpēc ietvarvienošanās/DPS ar gadu garu termiņu
 * citādi neienāk nemaz. Logs pēc termiņa, ne pēc publikācijas; apjoms mazs (~25).
 *
 * @return int importēto skaits
 */
function ks_sync_hilma_tail(PDO $pdo, KsWriter $stmt): int {
    $today = konkursi_today();
    // Robeža ir AKTĪVAIS logs (šodiena -60 d), nevis dienas kursors: kursors pēc
    // veiksmīgas palaišanas ir ~vakardiena, un tad aste ķertu gandrīz visu vēsturi.
    $windowStart = ks_active_cutoff();
    $imported = 0;
    for ($page = 0; $page < HILMA_TAIL_MAX_PAGES; $page++) {
        if (ks_stop_requested()) break;
        $res = ks_http_post_json(HILMA_SEARCH_URL, [
            'filter'  => "isNationalProcurement eq true and mainType eq 'ContractNotices'"
                       . " and deadline ge {$today}T00:00:00Z"
                       . " and datePublished lt {$windowStart}T00:00:00Z",
            'orderby' => 'datePublished asc',
            'top'     => 100,
            'skip'    => $page * 100,
        ], ['Ocp-Apim-Subscription-Key: ' . HILMA_API_KEY]);
        if ($res === null) { ks_log('  ⚠ Hilma (ilgtermiņa aste) neatbild.'); break; }
        $items = json_decode($res, true)['value'] ?? [];
        if (!is_array($items) || !$items) break;
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $n = hilma_parse_item($it);
            // ks_backfill_keep neattiecas: šie pēc definīcijas ir aktīvi 'iepirkumi'.
            if ($n === null || !ks_within_retention($n)) continue;
            $stmt->execute($n);
            ks_hilma_supersede($pdo, $it);
            $imported++;
        }
        if (count($items) < 100) break;
        sleep(1);
    }
    return $imported;
}

// ── Doffin (NO) sinhronizācija ────────────────────────────────────────────────

/**
 * Doffin meklēšanas pieprasījuma ķermenis. Filtri iet TIKAI zem
 * facets.<lauks>.checkedItems — plakans {"status":[...]} tiek klusi ignorēts,
 * un atbilde izskatās derīga, tikai nefiltrēta. sortBy jānorāda skaidri, jo
 * noklusējums ir RELEVANCE, nevis hronoloģija.
 */
function doffin_payload(int $page, array $facets = []): array {
    return [
        'numHitsPerPage' => 100,
        'page'           => $page,
        'searchString'   => '',
        'sortBy'         => 'PUBLICATION_DATE_DESC',
        'facets'         => $facets ?: new stdClass(),
    ];
}

/**
 * Viena Doffin šķēle: lapo, parsē, importē.
 *
 * Apstājas TIKAI pie īsas/tukšas lapas vai lapu limita, nevis pie
 * "savākts >= numHitsTotal". Doffin lapošana nav stabila — ierakstu kārtība
 * starp lapām mainās, tāpēc lapas pārklājas: šķēlē ar numHitsTotal=470 piecas
 * lapas deva 443 UNIKĀLUS ierakstus. Skaitot neapstrādātus hitus, cikls
 * pārtrūka pāragri un ~6% ierakstu palika neievākti līdz nākamajai palaišanai.
 *
 * @return int importēto skaits
 */
function ks_doffin_slice(PDO $pdo, KsWriter $stmt, array $facets, int &$skippedTed): int {
    $imported = 0;
    for ($page = 1; $page <= ks_cap(DOFFIN_ACTIVE_MAX_PAGES, 10); $page++) {
        if (ks_stop_requested()) break;
        $res = ks_http_post_json(DOFFIN_SEARCH_URL, doffin_payload($page, $facets),
                                 ['Origin: https://www.doffin.no']);
        if ($res === null) { ks_log('  ⚠ Doffin (aktīvie) neatbild.'); break; }
        $data = json_decode($res, true);
        $hits = is_array($data['hits'] ?? null) ? $data['hits'] : [];
        if (!$hits) break;
        foreach ($hits as $hit) {
            if (!is_array($hit)) continue;
            $n = doffin_parse_item($hit);
            if ($n === null) { $skippedTed++; continue; }   // sentToTed → nāk caur TED
            if (!ks_within_retention($n)) continue;
            $stmt->execute($n);
            $imported++;
        }
        if (count($hits) < 100) break;
        sleep(1);
    }
    return $imported;
}

/**
 * Atvērtie Norvēģijas konkursi visā logā.
 *
 * Kāpēc atsevišķs solis: Doffin arhīvā ir ~157k paziņojumu, lapošana beidzas pie
 * 1000, un svaigā lente 75% aizpildās ar sentToTed ierakstiem — 3 lapas deva
 * tikai ~200 nacionālo konkursu no ~364 reāli atvērtajiem.
 *
 * Filtrē pēc TIPA, nevis pēc status=ACTIVE. Statuss šim neder: ~15 atvērtiem
 * konkursiem Doffin statusu vispār nav uzstādījis (piem. "Restaurering Narvik
 * kirke", termiņš 19.08.), un tie pazustu. Tips ANNOUNCEMENT_OF_COMPETITION
 * viens pats sedz visus 363 no 364; COMPETITION pievienošana neko nedod.
 * Vai konkurss vēl ir atvērts, izšķir mūsu pašu ks_within_retention pēc termiņa.
 *
 * Šķēles ir PUSMĒNESIS (~500 ierakstu): mēneša šķēle jau ir ~1000, t.i. tieši
 * uz lapošanas griestiem, un klusi nogrieztu asti.
 *
 * Ilgtermiņa astei (publicēts pirms loga, bet vēl atvērts — ietvari/DPS līdz pat
 * 2021. gadam) tips neder: tas atgrieztu simtiem tūkstošu vecu paziņojumu.
 * Tur der tieši status=ACTIVE, jo tas kopums ir mazs un ierobežots.
 *
 * @return int importēto skaits
 */
function ks_sync_doffin_active(PDO $pdo, KsWriter $stmt, PDOStatement $exists, int &$skippedTed): int {
    $tz = new DateTimeZone('Europe/Riga');
    $cut = ks_active_cutoff();
    $imported = 0;

    // Pusmēneša šķēles no loga sākuma līdz šodienai
    $m = new DateTimeImmutable(substr($cut, 0, 8) . '01', $tz);
    $end = new DateTimeImmutable(konkursi_today(), $tz);
    while ($m <= $end) {
        $mid  = $m->modify('+14 days');
        $last = $m->modify('last day of this month');
        foreach ([[$m, $mid], [$mid->modify('+1 day'), $last]] as [$a, $b]) {
            if ($a > $end) break;
            $imported += ks_doffin_slice($pdo, $stmt, [
                'type'            => ['checkedItems' => ['ANNOUNCEMENT_OF_COMPETITION']],
                'publicationDate' => ['from' => $a->format('Y-m-d'), 'to' => $b->format('Y-m-d')],
            ], $skippedTed);
        }
        $m = $m->modify('first day of next month');
    }

    // Ilgtermiņa aste — te statuss ir īstais rīks, ne tips
    $imported += ks_doffin_slice($pdo, $stmt, [
        'status'          => ['checkedItems' => ['ACTIVE']],
        'publicationDate' => ['from' => null,
                              'to' => (new DateTimeImmutable($cut, $tz))->modify('-1 day')->format('Y-m-d')],
    ], $skippedTed);

    return $imported;
}

/** @return int importēto skaits */
function ks_sync_doffin(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT 1 FROM notices WHERE id = ?');
    $imported = 0; $skippedTed = 0;
    $cutoff = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . KONKURSI_KEEP_RESULTS_DAYS . ' days')->format('Y-m-d');

    // 1. Aktīvo konkursu pilnais logs — galvenais nacionālo iepirkumu avots.
    $imported += ks_sync_doffin_active($pdo, $stmt, $exists, $skippedTed);

    // 2. Svaigā lente bez statusa filtra: paņem rezultātus, atcelšanas un
    //    grozījumus, ko 1. solis (tikai ACTIVE) pēc definīcijas neredz.
    for ($page = 1; $page <= ks_cap(DOFFIN_MAX_PAGES, 30); $page++) {
        if (ks_stop_requested()) break;
        $res = ks_http_post_json(DOFFIN_SEARCH_URL, doffin_payload($page), ['Origin: https://www.doffin.no']);
        if ($res === null) { ks_log('  ⚠ Doffin API neatbild.'); break; }
        $data = json_decode($res, true);
        $hits = is_array($data['hits'] ?? null) ? $data['hits'] : [];
        if (!$hits) break;

        $newOnPage = 0; $freshOnPage = 0;
        foreach ($hits as $hit) {
            if (!is_array($hit)) continue;
            $rawPub = substr((string)($hit['publicationDate'] ?? $hit['issueDate'] ?? ''), 0, 10);
            if ($rawPub === '' || $rawPub >= ks_active_cutoff()) $freshOnPage++;
            $n = doffin_parse_item($hit);
            if ($n === null) { $skippedTed++; continue; }
            if (!ks_within_retention($n) || !ks_backfill_keep($n)) continue; // ārpus loga
            $exists->execute([$n['id']]);
            if ($exists->fetchColumn() === false) $newOnPage++;
            $stmt->execute($n); // atsvaidzina arī statusu/termiņu esošajiem
            $imported++;
        }
        // Dziļajā aizpildē zināmā zona nav apstāšanās iemesls (lapa var būt 100% TED
        // ierakstu) — tur apstājas pie aktīvā loga datuma horizonta.
        if (konkursi_deep() ? $freshOnPage === 0 : $newOnPage === 0) break;
        sleep(1);
    }
    if ($imported > 0 || $skippedTed > 0) {
        ks_log("  ✓ Doffin → $imported nacionālie ($skippedTed sūtīti uz TED — izlaisti).");
    }
    return $imported;
}

// ── udbud.dk (DK) sinhronizācija ──────────────────────────────────────────────

/** @return int importēto skaits */
function ks_sync_udbud(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT 1 FROM notices WHERE id = ?');
    $imported = 0;
    // Vecāki par glabāšanas logu ieraksti nav jāimportē — prune tos tāpat izdzēstu
    // (citādi rodas mūžīgs imports→prune→imports cikls ar seniem rezultātiem).
    $cutoff = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . KONKURSI_KEEP_RESULTS_DAYS . ' days')->format('Y-m-d');

    for ($page = 1; $page <= ks_cap(UDBUD_MAX_PAGES, 12); $page++) {
        if (ks_stop_requested()) break;
        $res = ks_http_post_json(UDBUD_SEARCH_URL, [
            'pagineringDto'    => ['aktuelSide' => $page, 'maksElementer' => 100,
                                   'sorteringFelt' => 'PUBLIKATION_DATO', 'retning' => 'Desc'],
            'filterDto'        => ['cpvKoder' => [], 'formularType' => ['NATIONALE_UDBUD']],
            'udbudStatusFilter'=> 'ALLE',
        ]);
        if ($res === null) { ks_log('  ⚠ udbud.dk API neatbild.'); break; }
        $data = json_decode($res, true);
        $items = is_array($data['resultatElementDtoList'] ?? null) ? $data['resultatElementDtoList'] : [];
        if (!$items) break;

        // Vecuma pārtraukuma te NAV ar nodomu. Nacionālā plūsma ir maza (~870
        // ierakstu kopā = ~9 lapas), un pārtraukums pēc publicēšanas datuma
        // nogrieza ietvarvienošanās, kas publicētas sen, bet joprojām atvērtas
        // (piem. publicēts 2025-05, termiņš 2027-03). Vecos ierakstus nepielaiž
        // ks_within_retention, tāpēc imports→prune cikls nerodas: tie pat netiek
        // ievietoti. $cutoff paliek tikai kā atsauce citiem posmiem.
        foreach ($items as $el) {
            if (!is_array($el)) continue;
            $n = udbud_parse_item($el);
            if ($n === null) continue;
            if (ks_stale_nodeadline($n)) continue;  // vecie bezdatuma iepirkumi — neimportē
            if (!ks_within_retention($n)) continue; // beidzies konkurss / vecs arhīvs
            $stmt->execute($n);
            $imported++;
        }
        sleep(1);
    }
    if ($imported > 0) ks_log("  ✓ udbud.dk → $imported nacionālie paziņojumi.");
    return $imported;
}

// ── Comdia (DK) sinhronizācija ────────────────────────────────────────────────

/**
 * Comdia organizāciju saraksts. Nav atsevišķa saraksta lapa, bet KATRAS lapas
 * sānu izvēlnē ir visas ~89 organizācijas ar to ceļa daļām, tāpēc pietiek ar
 * vienu pieprasījumu. Dinamiski, lai jaunas pašvaldības parādās pašas.
 *
 * @return array<string,string> slug => nosaukums
 */
function ks_comdia_orgs(): array {
    $h = ks_http_get(COMDIA_BASE_URL . COMDIA_SEED_PATH, [], 45);
    if ($h === null) return [];
    // <a href='/slug' ...><span class="kt-menu__link-text">Nosaukums</span>
    if (!preg_match_all(
        '#href=\'/([a-z0-9-]+)\'[^>]*>.*?<span class="kt-menu__link-text">([^<]+)</span>#s',
        $h, $m, PREG_SET_ORDER
    )) return [];
    $out = [];
    foreach ($m as $x) {
        $slug = $x[1];
        $name = trim(html_entity_decode($x[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($name !== '' && !isset($out[$slug])) $out[$slug] = $name;
    }
    return $out;
}

/**
 * Vienas organizācijas "Aktuelle udbud" saraksts.
 * @return array<string,string> Comdia Id => nosaukums
 */
function ks_comdia_list(string $slug): ?array {
    $h = ks_http_get(sprintf(COMDIA_LIST_FMT, $slug), [], 45);
    // null = neizdevās nolasīt; [] = nolasīts, bet konkursu nav. Atšķirība ir
    // kritiska: pēc tā izšķiras, vai drīkst dzēst šīs organizācijas rindas.
    if ($h === null || !preg_match('#<table.*?</table>#si', $h, $t)) return null;
    $out = [];
    foreach (preg_split('#<tr#i', $t[0]) ?: [] as $row) {
        if (!preg_match('#Id=(\d+)#', $row, $idm)) continue;
        if (!preg_match_all('#<td[^>]*>(.*?)</td>#si', $row, $cm)) continue;
        $title = trim(html_entity_decode(
            preg_replace('#<[^>]+>#', ' ', $cm[1][1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = trim(preg_replace('#\s+#u', ' ', $title));
        if ($title !== '') $out[$idm[1]] = $title;
    }
    return $out;
}

/**
 * Vai konkurss vēl ir atvērts. Comdia datumu nerāda, bet detaļu lapa slēgtajiem
 * pievieno ziņu "The deadline for participation in the tender has been exceeded."
 * Tā ir vienīgā ticamā pazīme — un tā nāk no avota, atšķirībā no jebkura
 * termiņa minējuma.
 *
 * @return bool|null null = nevarēja pārbaudīt (tīkla kļūme)
 */
function ks_comdia_is_open(string $slug, string $id): ?bool {
    $h = ks_http_get(sprintf(COMDIA_DETAIL_FMT, $slug, $id), [], 45);
    if ($h === null) return null;
    return stripos($h, 'deadline for participation') === false
        && stripos($h, 'fristen for deltagelse') === false;
}

/**
 * Dānijas pašvaldību konkursi no Comdia.
 *
 * Divi apļi. Saraksta aplis (~52 pieprasījumi) atklāj, kas vispār pastāv, un
 * novāc rindas, kas no Comdia pazudušas. Detaļu aplis pārbauda atvērts/slēgts —
 * tas maksā vienu pieprasījumu uz konkursu (~690), tāpēc ir ierobežots ar
 * COMDIA_MAX_DETAILS un atkārtojas ne biežāk kā COMDIA_RECHECK_HOURS.
 * Jaunie tiek pārbaudīti vispirms (tiem pēdējās pārbaudes nav).
 *
 * Statuss ir vienīgais dzīvības rādītājs: bez datumiem ks_prune šīs rindas
 * neaiztiktu, tāpēc slēgtos dzēš šis posms.
 *
 * @return int importēto skaits
 */
function ks_sync_comdia(PDO $pdo): int {
    $orgs = ks_comdia_orgs();
    if (!$orgs) { ks_log('  ⚠ Comdia organizāciju saraksts nav nolasāms.'); return 0; }

    $stmt = ks_upsert_stmt($pdo);
    $chk  = $pdo->prepare('SELECT imported_at FROM imported_files WHERE file_key = ?');
    $rec  = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $del  = $pdo->prepare("DELETE FROM notices WHERE id = ? AND source = 'COMDIA'");
    // first_seen raksta TIKAI vienreiz: upsert pārraksta visas kolonnas katrā
    // palaišanā, tāpēc datums nedrīkst būt upsert sarakstā — citādi tas ik reizi
    // pārlēktu uz šodienu un kārtošana zaudētu jēgu.
    $seen = $pdo->prepare("UPDATE notices SET first_seen = ? WHERE id = ? AND first_seen IS NULL");

    // 1. aplis: saraksti
    $listed = [];          // comdiaId => [slug, title, org]
    $okOrgs = [];          // organizācijas, kuru sarakstu TIEŠĀM izdevās nolasīt
    $failed = 0;
    foreach ($orgs as $slug => $org) {
        if (ks_stop_requested()) break;
        $rows = ks_comdia_list($slug);
        if ($rows === null) { $failed++; usleep(COMDIA_DELAY_MS * 1000); continue; }
        $okOrgs[$slug] = true;
        foreach ($rows as $id => $title) $listed[$id] = [$slug, $title, $org];
        usleep(COMDIA_DELAY_MS * 1000);
    }
    if (!$okOrgs) { ks_log('  ⚠ Comdia sarakstus neizdevās nolasīt.'); return 0; }
    if ($failed > 0) ks_log("  · Comdia: $failed organizāciju saraksts nebija pieejams.");

    // Konkursi, kas no pašvaldības lapas pazuduši → ārā.
    //
    // Dzēš TIKAI to organizāciju rindas, kuru saraksts šajā palaišanā tiešām tika
    // nolasīts. Citādi viena neveiksmīga pieprasījuma vai pārtraukta cikla dēļ
    // tiktu iztīrīti visi attiecīgās pašvaldības konkursi, un tie atgrieztos tikai
    // nākamajā palaišanā. Piederību nosaka source_file ('comdia:<slug>').
    $gone = 0;
    // fetchAll AIZVER lasīšanas kursoru pirms DELETE cilpā: rakstīšana atvērta
    // kursora laikā paralēlajā fāzē krīt ar tūlītēju SQLITE_BUSY_SNAPSHOT (cits
    // strādnieks pa vidu komitējis → mūsu lasīšanas momentuzņēmums vecs), un
    // busy_timeout tur NEpalīdz (2026-08-04 mācība — comdia krita abās palaišanās).
    $q = $pdo->query("SELECT id, source_file FROM notices WHERE source = 'COMDIA'")->fetchAll();
    foreach ($q as $r) {
        $slug = substr((string)$r['source_file'], 7); // 'comdia:'
        if (!isset($okOrgs[$slug])) continue;         // šo org. šoreiz nepārbaudījām
        $cid = substr((string)$r['id'], 7);           // 'COMDIA-'
        if (!isset($listed[$cid])) { $del->execute([$r['id']]); $gone++; }
    }

    // 2. aplis: statuss (jaunie vispirms)
    $cut = (new DateTimeImmutable('now'))->modify('-' . COMDIA_RECHECK_HOURS . ' hours')->format('c');
    $due = [];
    foreach ($listed as $id => $meta) {
        $chk->execute(['COMDIA:' . $id]);
        $last = $chk->fetchColumn();
        if ($last === false)            $due[] = [$id, $meta, 0];   // nekad nav pārbaudīts
        elseif ((string)$last < $cut)   $due[] = [$id, $meta, 1];
    }
    usort($due, fn($a, $b) => $a[2] <=> $b[2]);

    $imported = 0; $closed = 0; $checked = 0;
    $max = ks_cap(COMDIA_MAX_DETAILS, 2000);
    foreach ($due as [$id, [$slug, $title, $org], $_]) {
        if (ks_stop_requested() || $checked >= $max) break;
        // PHP skaitliskas masīva atslēgas pārvērš par int — atpakaļ uz string
        $id = (string)$id;
        $open = ks_comdia_is_open($slug, $id);
        $checked++;
        usleep(COMDIA_DELAY_MS * 1000);
        if ($open === null) continue;                 // tīkla kļūme — nemainām stāvokli
        $rec->execute(['COMDIA:' . $id, date('c'), $open ? 1 : 0]);
        if (!$open) { $del->execute(['COMDIA-' . $id]); $closed++; continue; }
        $n = comdia_parse_item($slug, (string)$id, $title, $org);
        if ($n === null) continue;
        $stmt->execute($n);
        $seen->execute([konkursi_today(), $n['id']]);
        $imported++;
    }

    ks_log(sprintf('  ✓ Comdia → %d atvērti (%d org., %d sarakstā, %d pārbaudīti, %d slēgti, %d pazuduši).',
        $imported, count($orgs), count($listed), $checked, $closed, $gone));
    return $imported;
}

// ── KommersAnnons (SE) sinhronizācija ─────────────────────────────────────────

/**
 * KommersAnnons saraksta lapa. POST lapošana ar __RequestVerificationToken, ko
 * jāizvelk no IEPRIEKŠĒJĀS lapas; sesijas sīkdatne obligāta.
 *
 * @return array{items:array<int,array{kind:string,id:string,title:string,pub:?string}>, token:string, html:string}
 */
function ks_kommers_page(?string $prevHtml, int $page, string $jar): array {
    if ($page <= 1 || $prevHtml === null) {
        $h = ks_http_get(KOMMERS_LIST_URL, [], 45, $jar);
    } else {
        $tok = preg_match('#name="__RequestVerificationToken"[^>]*value="([^"]+)"#', $prevHtml, $tm) ? $tm[1] : '';
        $h = ks_http_post_form(KOMMERS_LIST_URL, [
            'SearchString' => '', 'PageIndex' => $page, 'SelectedNutsCode' => '', 'SelectedCpvCode' => '',
            'SearchOldNotices' => 'False', 'SelectedProcuringEntity' => '', 'DPSType' => '',
            'SelectedContractType' => '', 'SelectedPocedureCode' => '', 'FrameworkAgreement' => '',
            'NameSort' => '', 'SortAscending' => 'True', '__RequestVerificationToken' => $tok,
        ], [], $jar);
    }
    if ($h === null) return ['items' => [], 'token' => '', 'html' => ''];

    $items = [];
    // Katrs ieraksts: saite uz paziņojumu, tad nosaukums, tad publicēšanas datums
    if (preg_match_all(
        '#<a[^>]*href="/Notices/(\w+)/(\d+)"[^>]*>(.*?)</a>(.{0,800}?)Datum då annonsen skickades för publicering\s*(\d{4}-\d{2}-\d{2})#s',
        $h, $m, PREG_SET_ORDER
    )) {
        foreach ($m as $x) {
            $title = trim(preg_replace('#\s+#u', ' ',
                html_entity_decode(strip_tags($x[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($title === '') continue;
            $items[] = ['kind' => $x[1], 'id' => $x[2], 'title' => $title, 'pub' => $x[5]];
        }
    }
    return ['items' => $items, 'token' => '', 'html' => $h];
}

/**
 * Zviedrijas nacionālie konkursi no KommersAnnons.
 *
 * Detaļu lapu ņem VIENREIZ uz paziņojumu (tur ir pasūtītājs, termiņš, CPV un
 * vērtība). Atkārtota aptauja nav vajadzīga — atšķirībā no Comdia te ir īsts
 * termiņš, tāpēc beigušos izmet ks_prune.
 *
 * @return int importēto skaits
 */
function ks_sync_kommers(PDO $pdo): int {
    $jar = konkursi_tmp_dir() . '/kommers.jar';
    @unlink($jar);
    $stmt = ks_upsert_stmt($pdo);
    $chk  = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec  = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');

    // 1. aplis: saraksti
    $listed = []; $prev = null;
    for ($page = 1; $page <= ks_cap(KOMMERS_MAX_PAGES, 40); $page++) {
        if (ks_stop_requested()) break;
        $r = ks_kommers_page($prev, $page, $jar);
        if (!$r['items']) break;
        $prev = $r['html'];
        $before = count($listed);
        foreach ($r['items'] as $it) $listed[$it['id']] = $it;
        if (count($listed) === $before) break;   // lapas atkārtojas — beigas
        usleep(KOMMERS_DELAY_MS * 1000);
    }
    if (!$listed) { ks_log('  ⚠ KommersAnnons saraksts nav pieejams.'); return 0; }

    // 2. aplis: detaļas tikai JAUNAJIEM
    $imported = 0; $details = 0;
    $max = ks_cap(KOMMERS_MAX_DETAILS, 2000);
    foreach ($listed as $id => $it) {
        if (ks_stop_requested() || $details >= $max) break;
        $key = 'KOMMERS:' . $id;
        $chk->execute([$key]);
        if ($chk->fetchColumn() !== false) continue;   // jau ir
        $d = ks_http_get(sprintf(KOMMERS_DETAIL_FMT, $it['kind'], (string)$id), [], 45, $jar);
        $details++;
        usleep(KOMMERS_DELAY_MS * 1000);
        if ($d === null) continue;
        $n = kommers_parse_item($it['kind'], (string)$id, $it['title'], $it['pub'], $d);
        if ($n === null) { $rec->execute([$key, date('c'), 0]); continue; }
        if (!ks_within_retention($n)) { $rec->execute([$key, date('c'), 0]); continue; }
        $stmt->execute($n);
        $rec->execute([$key, date('c'), 1]);
        $imported++;
    }
    @unlink($jar);

    // Dedup pret TED. Zviedrija virs-sliekšņa konkursus publicē ABĀS vietās, un
    // ~49% KommersAnnons ierakstu sakrīt ar jau esošu TED rindu. Centrālais
    // ks_dedupe_notices tos neredz, jo KommersAnnons nedod contract_folder_id.
    // Sakritību nosaka nosaukums + pasūtītāja valsts; pārbaudīts uz 512 rindām —
    // no 249 nosaukumu sakritībām 247 sakrita arī pasūtītājs, tāpēc kļūdaini
    // dzēsto risks ir zems. Slaucīšana katrā palaišanā sedz ABAS secības: gan ja
    // TED bija pirmais, gan ja tas ienāk vēlāk.
    $sweep = $pdo->exec(
        "DELETE FROM notices WHERE source = 'KOMMERS' AND EXISTS (
             SELECT 1 FROM notices t
             WHERE t.source = 'TED' AND t.buyer_country = 'SE'
               AND lower(trim(t.title)) = lower(trim(notices.title)))"
    );
    if ($sweep > 0) $imported = max(0, $imported - $sweep);

    // Izdrukā VIENMĒR, arī ar nullēm — kā Comdia. Klusējošs posms žurnālā izskatās
    // tāpat kā izlaists, un tad nevar atšķirt 'nekas jauns' no 'nenostrādāja'.
    ks_log("  ✓ KommersAnnons → $imported nacionālie (" . count($listed)
         . " sarakstā, $details detaļas, $sweep jau TED plūsmā).");
    return $imported;
}

// ── Útboðsvefur (IS) sinhronizācija ───────────────────────────────────────────

/**
 * Islandes nacionālie konkursi no utbodsvefur.is.
 *
 * Aktīvo kopu dod SĀKUMLAPA (~80 saites) — WP API to nederētu, jo tas atgriež
 * visus 6280 vēsturiskos ierakstus bez pazīmes, kurš vēl ir atvērts. Katram
 * konkursam lapā ir tīra lauku tabula ar pasūtītāju, veidu un termiņu.
 *
 * Detaļu ņem tikai JAUNAJIEM; esošos atsvaidzina tikai tad, ja tie vēl ir
 * sākumlapā. Kas no sākumlapas pazudis, to izmet — tāpat kā Comdia.
 *
 * @return int importēto skaits
 */
function ks_sync_isutb(PDO $pdo): int {
    $home = ks_http_get(ISUTB_BASE_URL . '/', [], 45);
    if ($home === null) { ks_log('  ⚠ Útboðsvefur nav pieejams.'); return 0; }

    // Aktīvo konkursu saites (vietnes iekšējās, ar slug)
    $RE = '#href="(https://utbodsvefur\.is/[a-z0-9-]{6,}/)"#i';
    $urls = preg_match_all($RE, $home, $m) ? $m[1] : [];
    if (!$urls) {
        ks_log('  ⚠ Útboðsvefur: sākumlapā nav konkursu saišu.');
        return 0;
    }
    // Tipu aplis: noklusējuma sākumlapa IZLAIŽ saliktos tipus (piem. "Vörukaup,
    // Markaðskönnun (RFI)") — tie tur neparādās, kaut termiņš vēl nav pagājis.
    foreach (ISUTB_TYPES as $t) {
        if (ks_stop_requested()) break;
        $h = ks_http_get(ISUTB_BASE_URL . '/?flokkur=' . rawurlencode($t) . '&filter=1', [], 45);
        if ($h !== null && preg_match_all($RE, $h, $tm)) $urls = array_merge($urls, $tm[1]);
        usleep(ISUTB_DELAY_MS * 1000);
    }
    $urls = array_values(array_unique($urls));
    if (count($urls) > ISUTB_MAX_ITEMS) $urls = array_slice($urls, 0, ISUTB_MAX_ITEMS);

    $stmt = ks_upsert_stmt($pdo);
    $del  = $pdo->prepare("DELETE FROM notices WHERE id = ? AND source = 'ISUTB'");
    $imported = 0; $fetched = 0;
    $listed = [];

    foreach ($urls as $u) {
        if (ks_stop_requested()) break;
        $slug = trim(parse_url($u, PHP_URL_PATH) ?? '', '/');
        $listed['ISUTB-' . $slug] = true;
        // Detaļu-lapu nepārlādē, ja tā pārbaudīta pēdējās ISUTB_RECHECK_HOURS —
        // saraksts (gone-detection) tāpat notiek katrreiz, tikai HTTP tiek taupīts.
        if (!ks_detail_due($pdo, 'ISUTB:' . $slug, ISUTB_RECHECK_HOURS)) continue;
        $h = ks_http_get($u, [], 45);
        $fetched++;
        usleep(ISUTB_DELAY_MS * 1000);
        if ($h === null) continue;
        $n = isutb_parse_item($u, $h);
        if ($n === null || !ks_within_retention($n)) continue;
        $stmt->execute($n);
        ks_mark_detail($pdo, 'ISUTB:' . $slug);
        $imported++;
    }

    // Kas no sākumlapas pazudis, tas vairs nav aktīvs. Dzēš TIKAI tad, ja
    // sākumlapu tiešām izlasījām (citādi viena kļūme iztīrītu visu Islandi).
    $gone = 0;
    if ($listed) {
        // fetchAll — DELETE atvērta kursora laikā paralēlajā fāzē dotu SQLITE_BUSY_SNAPSHOT
        foreach ($pdo->query("SELECT id FROM notices WHERE source = 'ISUTB'")->fetchAll() as $r) {
            if (!isset($listed[$r['id']])) { $del->execute([$r['id']]); $gone++; }
        }
    }

    if ($imported > 0 || $gone > 0) {
        ks_log("  ✓ Útboðsvefur → $imported nacionālie ($fetched lapas, $gone pazuduši).");
    }
    return $imported;
}

// ── BZP (PL) sinhronizācija ───────────────────────────────────────────────────

/** @return int importēto skaits */
function ks_sync_bzp(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $since = konkursi_meta_get($pdo, 'bzp_since');
    if ($since === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
        $since = (new DateTimeImmutable('now', $tz))->modify('-' . BZP_BACKFILL_DAYS . ' days')->format('Y-m-d');
    }
    $since = ks_window_start($since);
    $until = (new DateTimeImmutable('now', $tz))->modify('+1 day')->format('Y-m-d');
    $maxPages = ks_cap(BZP_MAX_PAGES, 700);
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    $minReached = null;   // vismazāk pavirzījies tips nosaka nākamo kursoru
    $allComplete = true;

    foreach (['ContractNotice' => 'iepirkumi', 'TenderResultNotice' => 'rezultati'] as $type => $category) {
        // Datuma kursors: API lapošanas nav, bet rezultāti kārtoti augoši pēc
        // publicationDate → nākamā "lapa" sākas no pēdējā ieraksta laika.
        // Dziļajā aizpildē rezultātus ņem tikai svaigos (arhīvs pildās uz priekšu).
        // Rezultātiem svaiguma robeža noņemta (sk. ks_backfill_keep) — logu
        // nosaka glabāšana (60 d) un KONKURSI_RESULTS_CAP.
        $typeSince = $since;
        $cursor = $typeSince . 'T00:00:00';
        $complete = false;
        for ($page = 1; $page <= $maxPages; $page++) {
            if (ks_stop_requested()) break 2;
            $url = sprintf(BZP_API_URL_FMT, $type, $cursor, $until . 'T00:00:00');
            $tmp = konkursi_tmp_dir() . '/bzp.json';
            if (!ks_http_download($url, $tmp, true)) { ks_log("  ⚠ BZP API neatbild ($type)."); break; }
            $items = json_decode((string)file_get_contents($tmp), true);
            @unlink($tmp);
            if (!is_array($items) || !$items) { $complete = true; break; }
            $lastPub = null;
            foreach ($items as $it) {
                if (!is_array($it)) continue;
                if (is_string($it['publicationDate'] ?? null)) $lastPub = $it['publicationDate'];
                $n = bzp_parse_item($it, $category);
                if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
                $stmt->execute($n);
                $imported++;
            }
            if (count($items) < 100) { $complete = true; break; }
            $next = $lastPub !== null ? substr($lastPub, 0, 19) : null;
            if ($next === null) { $complete = true; break; }
            if ($next === $cursor) {
                // Visiem 100 ierakstiem sakrita sekunde. Tas NAV loga beigas, bet
                // IESTRĒGUMS. Agrāk te bija 'complete = true', un kursors pēc tam
                // pārlēca uz vakardienu, atstājot vēsturē caurumu, kas pats vairs
                // neaizpildījās. Tāpēc pabīda par sekundi un turpina.
                $next = date('Y-m-d\TH:i:s', strtotime($cursor) + 1);
            }
            $cursor = $next;
            sleep(1);
        }
        if (!$complete) $allComplete = false;
        $reached = substr($cursor, 0, 10);
        if ($minReached === null || $reached < $minReached) $minReached = $reached;
    }
    // Kursors: pabeigtam logam — vakardiena; nepabeigtam — reāli sasniegtais
    // datums (lapu limits nedrīkst radīt caurumu vēsturē).
    $yesterday = (new DateTimeImmutable('now', $tz))->modify('-1 day')->format('Y-m-d');
    konkursi_meta_set($pdo, 'bzp_since', $allComplete ? $yesterday : min($minReached ?? $yesterday, $yesterday));
    if ($imported > 0) ks_log("  ✓ BZP → $imported nacionālie paziņojumi.");
    return $imported;
}

// ── BKMS (DE) sinhronizācija ──────────────────────────────────────────────────

/**
 * Vācijas dienas eForms ZIP: importē nacionālos (RegulatoryDomain de-*);
 * ES līmeņa paziņojumus (ar TED notice-id shēmu) izlaiž — tie nāk caur TED.
 * Šodienas failu pārimportē katru reizi (aug dienas gaitā); pabeigtās dienas
 * atzīmē imported_files un vairs neaiztiek.
 * @return int importēto skaits
 */
function ks_sync_bkms(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $today = new DateTimeImmutable('today', $tz);
    $stmt = ks_upsert_stmt($pdo);
    $srcQ = $pdo->prepare('SELECT source FROM notices WHERE id = ?');
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $imported = 0;

    for ($back = ks_cap(BKMS_LOOKBACK_DAYS, konkursi_deep_days()) - 1; $back >= 0; $back--) {
        if (ks_stop_requested()) break;
        $day = $today->modify("-$back days")->format('Y-m-d');
        $fileKey = 'BKMS:' . $day;
        $chk->execute([$fileKey]);
        if ($chk->fetchColumn() !== false) continue; // diena jau pabeigta

        $tmp = konkursi_tmp_dir() . '/bkms.zip';
        if (!ks_http_download(sprintf(BKMS_EXPORT_URL_FMT, $day), $tmp, true)) {
            ks_log("  · BKMS $day eksporta (vēl) nav.");
            continue;
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) { @unlink($tmp); ks_log("  ⚠ BKMS $day ZIP neatveras."); continue; }

        $count = 0; $skippedEu = 0;
        $pdo->beginTransaction();
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name) || !str_ends_with(strtolower($name), '.xml')) continue;
                $xml = $zip->getFromIndex($i);
                if ($xml === false || $xml === '') continue;
                // ES līmeņa paziņojums (standarta TED notice-id) → nāk caur TED plūsmu
                if (str_contains($xml, 'schemeName="notice-id"')) { $skippedEu++; continue; }
                $n = ted_parse_xml($xml, 'BKMS:' . $day, 'DE');
                if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
                $srcQ->execute([$n['id']]);
                if ($srcQ->fetchColumn() === 'TED') { $skippedEu++; continue; }
                $n['source'] = 'BKMS';
                $stmt->execute($n);
                $count++;
            }
            // Tikai pabeigtas dienas (ne šodienu) atzīmē kā galīgas
            if ($day < $today->format('Y-m-d')) {
                $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)')
                    ->execute([$fileKey, date('c'), $count]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $zip->close();
            @unlink($tmp);
            throw $e;
        }
        $zip->close();
        @unlink($tmp);
        $imported += $count;
        ks_log("  ✓ BKMS $day → $count nacionālie ($skippedEu ES līmeņa — tie ir TED).");
        sleep(1);
    }
    return $imported;
}

// ── BOAMP (FR) sinhronizācija ─────────────────────────────────────────────────

/** @return int importēto skaits */
function ks_sync_boamp(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $since = konkursi_meta_get($pdo, 'boamp_since');
    if ($since === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
        $since = (new DateTimeImmutable('now', $tz))->modify('-' . BOAMP_BACKFILL_DAYS . ' days')->format('Y-m-d');
    }
    $since = ks_window_start($since);
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    $complete = true;

    // ODS offset limits ir 10 000, un 60 dienās ir ~9-13k ierakstu — dziļajā
    // aizpildē logu dala ~15 dienu šķēlēs (katra droši zem limita).
    $windows = [];
    if (konkursi_deep()) {
        $d = new DateTimeImmutable($since, $tz);
        $end = new DateTimeImmutable('tomorrow', $tz);
        while ($d < $end) {
            $to = min($d->modify('+15 days'), $end);
            $windows[] = [$d->format('Y-m-d'), $to->format('Y-m-d')];
            $d = $to;
        }
    } else {
        $windows[] = [$since, null];
    }

    foreach ($windows as [$from, $to]) {
        if (ks_stop_requested()) break;
        $cond = "dateparution>=date'$from'" . ($to !== null ? " and dateparution<date'$to'" : '') . ' and famille!="JOUE"';
        $where = rawurlencode($cond);
        $maxPages = ks_cap(BOAMP_MAX_PAGES, 60);
        $exhausted = true; // vai logs tika izsmelts (nevis apcirsts ar lapu griestiem)
        for ($page = 0; $page < $maxPages; $page++) {
            if (ks_stop_requested()) break 2;
            $url = BOAMP_API_URL . '?where=' . $where . '&order_by=' . rawurlencode('dateparution desc')
                 . '&limit=100&offset=' . ($page * 100);
            $tmp = konkursi_tmp_dir() . '/boamp.json';
            if (!ks_http_download($url, $tmp, true)) { ks_log('  ⚠ BOAMP API neatbild.'); $complete = false; break 2; }
            $data = json_decode((string)file_get_contents($tmp), true);
            @unlink($tmp);
            $items = is_array($data['results'] ?? null) ? $data['results'] : [];
            if (!$items) break;
            foreach ($items as $it) {
                if (!is_array($it)) continue;
                $n = boamp_parse_item($it);
                if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
                $stmt->execute($n);
                $imported++;
            }
            if (count($items) < 100) break;
            // Pēdējā atļautajā lapā vēl bija pilns komplekts → logs apcirsts.
            if ($page === $maxPages - 1) $exhausted = false;
            sleep(1);
        }
        // Kārtošana ir 'dateparution desc', tāpēc griesti nogriež loga VECĀKO galu.
        // Ja kursoru tomēr pavirzītu uz priekšu, tie paziņojumi nekad neatgrieztos
        // (tā pati badināšanās kā Hilma). Nepavirzām — nākamais gājiens atkārto logu.
        if (!$exhausted) {
            ks_log('  ⚠ BOAMP: logs pārsniedz ' . $maxPages . ' lapas — kursors nepavirzās, atkārtos nākamreiz.');
            $complete = false;
        }
    }
    if ($complete) {
        konkursi_meta_set($pdo, 'boamp_since', (new DateTimeImmutable('now', $tz))->modify('-1 day')->format('Y-m-d'));
    }
    if ($imported > 0) ks_log("  ✓ BOAMP → $imported nacionālie paziņojumi.");
    return $imported;
}

// ── eTenders (IE) sinhronizācija — tas pats e-PPS modelis kā CVP IS ───────────

/** @return int importēto skaits */
function ks_sync_etenders(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    $detailsFetched = 0;
    $skippedOver = 0;
    $maxPages   = 1; // CSV eksports nelapo — dziļajā aizpildē ņem VISU sarakstu ar lielu ps
    $maxDetails = ks_cap(ETENDERS_MAX_DETAILS_PER_RUN, 1500);
    $freshCut   = ks_backfill_fresh_cut();
    $cutExpired = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days')->format('Y-m-d');

    $chk = $pdo->prepare('SELECT notice_count FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $upd = $pdo->prepare('UPDATE notices SET category = ?, deadline_date = COALESCE(?, deadline_date) WHERE id = ?');
    $tedChk = $pdo->prepare("SELECT 1 FROM notices WHERE source = 'TED' AND publication_number = ?");
    // TED kopija ienāk dažu dienu laikā pēc publicēšanas — svaigam paziņojumam
    // ar TED atsauci pagaida to; vecam (atsauce ārpus mūsu TED loga) tā
    // neatnāks nekad, tāpēc importē no eTenders. Citādi ilgtermiņa paneļi ar
    // 2027.+ gada termiņiem nebūtu redzami nevienā plūsmā.
    $recentCut = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-7 days')->format('Y-m-d');

    for ($page = 1; $page <= $maxPages; $page++) {
        if (ks_stop_requested()) break;
        $tmp = konkursi_tmp_dir() . '/etenders.csv';
        $url = sprintf(ETENDERS_LIST_URL_FMT, $page);
        if (konkursi_deep()) $url = str_replace('T01_ps=300', 'T01_ps=3000', $url);
        if (!ks_http_download($url, $tmp, true)) {
            ks_log('  ⚠ eTenders saraksta lejupielāde neizdevās.');
            break;
        }
        $rows = cvpis_parse_csv((string)file_get_contents($tmp));
        @unlink($tmp);
        if (!$rows) { ks_log('  ⚠ eTenders CSV tukšs vai neparsējams.'); break; }

        $newOnPage = 0; $freshOnPage = 0;
        foreach ($rows as $row) {
            if ($row['pub_date'] === null || $row['pub_date'] >= ks_active_cutoff()) $freshOnPage++;
            if (ks_stop_requested()) break 2;
            $key = 'ETENDERS:' . $row['resource_id'];
            $chk->execute([$key]);
            $known = $chk->fetchColumn();
            if ($known !== false) {
                if ((int)$known === 1) {
                    $upd->execute([etenders_category($row['status']), $row['deadline_date'], 'ETENDERS-' . $row['resource_id']]);
                }
                continue;
            }
            if ($row['kind'] !== 'cft') { $rec->execute([$key, date('c'), 0]); continue; }
            $newOnPage++;
            if (konkursi_deep() && $row['pub_date'] !== null && $row['pub_date'] < $freshCut
                && $row['deadline_date'] !== null && $row['deadline_date'] < $cutExpired) {
                $rec->execute([$key, date('c'), 0]); // sen beidzies — retention to izmestu
                continue;
            }
            if ($detailsFetched >= $maxDetails) continue;

            sleep(ETENDERS_REQUEST_DELAY_S);
            $dtmp = konkursi_tmp_dir() . '/etenders_d.html';
            if (!ks_http_download(sprintf(ETENDERS_DETAIL_URL_FMT, $row['resource_id']), $dtmp, true)) {
                ks_log('  ⚠ eTenders detaļas neizdevās: ' . $row['resource_id']);
                continue;
            }
            $detailsFetched++;
            $html = (string)file_get_contents($dtmp);
            $d = cvpis_parse_detail($html);
            @unlink($dtmp);
            if (!$d) continue;

            // Dedup pret TED: izlaiž tikai tad, ja atsauktais TED paziņojums
            // TIEŠĀM ir mūsu DB (nosaukumu salīdzināt nevar — eForms virsraksts
            // mēdz atšķirties no eTenders virsraksta).
            $refs = etenders_ted_refs($html);
            $tedInDb = false;
            foreach ($refs as $ref) {
                $tedChk->execute([$ref]);
                if ($tedChk->fetchColumn() !== false) { $tedInDb = true; break; }
            }
            if ($tedInDb) { $rec->execute([$key, date('c'), 0]); continue; }
            $thr = (string)(epps_field($d, 'Above or Below threshold') ?? '');
            $tedExpected = $refs !== [] || stripos($thr, 'above') !== false;
            if ($tedExpected && ($row['pub_date'] === null || $row['pub_date'] >= $recentCut)) {
                // TED kopija vēl ceļā — atstāj nereģistrētu, pārbaudīs nākamreiz
                continue;
            }

            $n = etenders_build_notice($row, $d);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                $skippedOver++;
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
        if (konkursi_deep() ? $freshOnPage === 0 : $newOnPage === 0) break;
    }

    // ── 2. fāze: nacionālie piešķīrumi no paziņojumu reģistra (noticeFTS) ──
    // CfT saraksts piešķīrumus nerāda ('latest' tur ir tikai Tender Submission
    // + Established), tāpēc rezultātus ņem no atsevišķā reģistra. Importē tikai
    // '(no TED publication)' — TED publicētie piešķīrumi nāk TED plūsmā.
    $resCut = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . (KONKURSI_KEEP_RESULTS_BY_SOURCE['ETENDERS'] ?? KONKURSI_KEEP_RESULTS_DAYS) . ' days')
        ->format('Y-m-d');
    $resPages = konkursi_deep() ? ETENDERS_NOTICES_PAGES_DEEP : 2;
    $resPs    = konkursi_deep() ? 500 : 300;
    $results  = 0;
    $jar = konkursi_tmp_dir() . '/etenders_jar.txt';
    @unlink($jar); // svaiga sesija — lapošana dzīvo tikai tās ietvaros
    for ($page = 1; $page <= $resPages; $page++) {
        if (ks_stop_requested()) break;
        $tmp = konkursi_tmp_dir() . '/etenders_n.html';
        if (!ks_http_download(sprintf(ETENDERS_NOTICES_URL_FMT, $page, $resPs), $tmp, true, $jar)) {
            ks_log('  ⚠ eTenders paziņojumu reģistrs neatbild.');
            break;
        }
        $nrows = etenders_parse_notices_html((string)file_get_contents($tmp));
        @unlink($tmp);
        if (!$nrows) break;
        $pastCut = false;
        foreach ($nrows as $nr) {
            if ($nr['pub_date'] !== null && $nr['pub_date'] < $resCut) { $pastCut = true; continue; }
            if (!etenders_is_national_award($nr['type'])) continue;
            $n = etenders_award_notice($nr);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
            $stmt->execute($n);
            $results++;
        }
        if ($pastCut || count($nrows) < $resPs) break;
        sleep(ETENDERS_REQUEST_DELAY_S);
    }

    if ($imported > 0 || $skippedOver > 0 || $results > 0) {
        ks_log("  ✓ eTenders → $imported nacionālie, $results piešķīrumi, $skippedOver virs-sliekšņa/veci izlaisti.");
    }
    return $imported + $results;
}

// ── TenderNed (NL) sinhronizācija ─────────────────────────────────────────────

/** @return int importēto skaits */
function ks_sync_tenderned(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT 1 FROM notices WHERE id = ?');
    $imported = 0; $skippedEu = 0;

    for ($page = 0; $page < ks_cap(TENDERNED_MAX_PAGES, 90); $page++) {
        if (ks_stop_requested()) break;
        $tmp = konkursi_tmp_dir() . '/tenderned.json';
        if (!ks_http_download(sprintf(TENDERNED_API_URL_FMT, $page), $tmp, true)) {
            ks_log('  ⚠ TenderNed API neatbild.');
            break;
        }
        $data = json_decode((string)file_get_contents($tmp), true);
        @unlink($tmp);
        $items = is_array($data['content'] ?? null) ? $data['content'] : [];
        if (!$items) break;

        $newOnPage = 0; $freshOnPage = 0;
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $rawPub = substr((string)($it['publicatieDatum'] ?? ''), 0, 10);
            if ($rawPub === '' || $rawPub >= ks_active_cutoff()) $freshOnPage++;
            $n = tenderned_parse_item($it);
            if ($n === null) { $skippedEu++; continue; }
            if (!ks_within_retention($n) || !ks_backfill_keep($n)) continue;
            $exists->execute([$n['id']]);
            if ($exists->fetchColumn() === false) $newOnPage++;
            $stmt->execute($n);
            $imported++;
        }
        if (konkursi_deep() ? $freshOnPage === 0 : $newOnPage === 0) break;
        sleep(1);
    }

    // AKTĪVO PILNĀ KOPA pa servera termiņa filtru (sluitingsDatumVanaf=šodiena) —
    // NEATKARĪGI no publicēšanas datuma. Agrāk TIKAI dziļajā režīmā; tagad ARĪ parastajā,
    // jo publicēšanas loga cilpa nesasniedz atvērtos nacionālos ar vecāku publicēšanas
    // datumu (DAS/groslijsten/open-house — termiņi līdz 2030). Lēts: tikai saraksta lapas
    // (tenderned_parse_item strādā ar saraksta ierakstu, bez atsevišķas detaļas).
    $openOld = 0;
    $openPages = konkursi_deep() ? 20 : 15;
    $todayNl = konkursi_today();
    for ($page = 0; $page < $openPages; $page++) {
        if (ks_stop_requested()) break;
        $tmp = konkursi_tmp_dir() . '/tenderned.json';
        if (!ks_http_download(sprintf(TENDERNED_OPEN_URL_FMT, $page, $todayNl), $tmp, true)) break;
        $data = json_decode((string)file_get_contents($tmp), true);
        @unlink($tmp);
        $items = is_array($data['content'] ?? null) ? $data['content'] : [];
        if (!$items) break;
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (($it['isVroegtijdigeBeeindiging'] ?? false) === true) continue; // pārtraukts
            $n = tenderned_parse_item($it);
            // izmainas ar dzīvu termiņu ks_recategorize_open tāpat pārliks uz
            // 'iepirkumi'; dedupe (kenmerk+nosaukums) noņem, ja oriģināls jau ir.
            if ($n === null || !in_array($n['category'], ['iepirkumi', 'izmainas'], true)) continue;
            if ($n['deadline_date'] === null) continue; // bez termiņa = mūžīgi karātos
            $stmt->execute($n);
            $openOld++;
        }
        if (count($items) < 100) break;
        sleep(1);
    }

    if ($imported > 0 || $skippedEu > 0 || $openOld > 0) {
        ks_log("  ✓ TenderNed → $imported nacionālie, $openOld vecie atvērtie ($skippedEu ES līmeņa — tie ir TED).");
    }
    return $imported + $openOld;
}

// ── PLACSP (ES) sinhronizācija ────────────────────────────────────────────────

/**
 * Spānijas ritošā ATOM plūsma: galvenais fails + 'next' saites atpakaļ vēsturē.
 * Apstājas, kad lapa vairs nedod jaunus ierakstus (vai sasniegts lapu limits).
 * @return int importēto skaits
 */
function ks_sync_placsp(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT 1 FROM notices WHERE id = ?');
    $imported = 0;

    // Divas neatkarīgas plūsmas: PLACSP mītošie profili + reģionu platformu
    // agregāts. Pasūtītājs mīt tieši vienā no tām, tāpēc pārklāšanās nav.
    foreach ([PLACSP_FEED_URL, PLACSP_AGG_FEED_URL] as $url) {
        $base = dirname($url) . '/';
        // Dziļie griesti 90 (bija 400): ~330 ieraksti lapā → 60 d aktīvo logs
        // ietilpst ar rezervi, un rezultātu arhīvs tāpat cērtas pie
        // KONKURSI_RESULTS_CAP — 400 lapu staigāšana vilkās stundām.
        for ($page = 0; $page < ks_cap(PLACSP_MAX_PAGES, 90); $page++) {
            if (ks_stop_requested() || $url === null) break;
            $tmp = konkursi_tmp_dir() . '/placsp.atom';
            if (!ks_http_download($url, $tmp, true)) {
                ks_log('  ⚠ PLACSP plūsma nav pieejama' . ($page > 0 ? " ($page lapas apstrādātas)" : '') . '.');
                break;
            }
            [$rows, $next] = placsp_parse_atom((string)file_get_contents($tmp));
            @unlink($tmp);
            if (!$rows) break;

            $newOnPage = 0; $freshOnPage = 0;
            foreach ($rows as $n) {
                if ($n['publication_date'] === null || $n['publication_date'] >= ks_active_cutoff()) $freshOnPage++;
                if (!ks_within_retention($n) || !ks_backfill_keep($n)) continue;
                $exists->execute([$n['id']]);
                if ($exists->fetchColumn() === false) $newOnPage++;
                $stmt->execute($n);
                $imported++;
            }
            // Dziļajā aizpildē staigā ritošo arhīvu līdz aktīvā loga horizontam
            if (konkursi_deep() ? $freshOnPage === 0 : $newOnPage === 0) break;
            $url = $next !== null ? (str_starts_with($next, 'http') ? $next : $base . $next) : null;
            sleep(1);
        }
    }
    // Dedup pret TED pēc nosaukuma+pircēja: virs sliekšņa esošie (arī tie, kam
    // budžets plūsmā tukšs) un brīvprātīgi TED publicētie parādās abās pusēs,
    // bet lietas numuri nav savienojami (TED eForms cfid ir UUID, PLACSP —
    // lietvedības numurs). TED rinda paliek — strukturētāka, tā pati konvencija
    // kā ks_dedupe_notices.
    $del = ks_dedupe_vs_ted($pdo, 'PLACSP', 'ES');
    if ($del > 0) ks_log("  ⧉ PLACSP: $del dublējās ar TED — noņemti (paliek TED rinda).");

    if ($imported > 0) ks_log("  ✓ PLACSP → $imported nacionālie paziņojumi.");
    return $imported;
}

// ── VVZ NIPEZ (CZ) sinhronizācija ─────────────────────────────────────────────

/**
 * Čehijas nacionālie formulāri: saraksts ar uverejnitTed=false (ES līmeņa nāk
 * caur TED), kārtots pēc publicēšanas dilstoši; katram jaunajam formulāram
 * bērna iesniegums (children/search) ar pilnajiem eForms BT datiem.
 * @return int importēto skaits
 */
function ks_sync_vvz(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0; $details = 0;

    $since = konkursi_meta_get($pdo, 'vvz_since');
    if ($since === null) {
        $since = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
            ->modify('-' . VVZ_BACKFILL_DAYS . ' days')->format('Y-m-d') . 'T00:00:00';
    }
    if (konkursi_deep()) $since = ks_window_start($since) . 'T00:00:00';
    $maxSeen = $since;
    $maxPages   = ks_cap(VVZ_MAX_PAGES, 160);
    $maxDetails = ks_cap(VVZ_MAX_DETAILS_PER_RUN, 9000);
    $freshCut   = ks_backfill_fresh_cut();

    for ($page = 1; $page <= $maxPages; $page++) {
        if (ks_stop_requested()) break;
        $q = http_build_query([
            'formGroup' => 'vz', 'form' => 'vz', 'page' => $page, 'limit' => 100,
            'workflowPlace' => 'UVEREJNENO_VVZ',
            'data.formularZneplatnen' => 'false',
            'order[data.datumUverejneniVvz]' => 'DESC',
        ]);
        $body = ks_http_get(VVZ_SEARCH_URL . '?' . $q, ['Accept: application/json']);
        $rows = $body !== null ? json_decode($body, true) : null;
        if (!is_array($rows) || !$rows) {
            if ($page === 1) ks_log('  ⚠ VVZ saraksts nav pieejams.');
            break;
        }

        $reachedOld = false;
        foreach ($rows as $sub) {
            if (ks_stop_requested()) break 2;
            $d = $sub['data'] ?? [];
            $pub = (string)($d['datumUverejneniVvz'] ?? '');
            if ($pub !== '' && substr($pub, 0, 19) <= $since) { $reachedOld = true; break; }
            if ($pub !== '' && substr($pub, 0, 19) > $maxSeen) $maxSeen = substr($pub, 0, 19);

            $formNr = (string)($sub['variableId'] ?? '');
            if ($formNr === '') continue;
            $key = 'VVZ:' . $formNr;
            $chk->execute([$key]);
            if ($chk->fetchColumn() !== false) continue;

            // TED plūsmā ejošie formulāri: API vaicājuma filtru 'data.uverejnitTed'
            // serveris KLUSI IGNORĒ (pārbaudīts 2026-07-20: true/false/bez filtra —
            // vienāds x-total-count), tāpēc šķiro klienta pusē. Šie paziņojumi
            // ienāk caur TED dienas paketēm — imports te tos dublētu.
            if (($d['uverejnitTed'] ?? false) === true || !empty($d['evCisloTed'])) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }

            // Dziļajā aizpildē: veci rezultātu/grozījumu formulāri (>14 d) tiks
            // izmesti tāpat (ks_backfill_keep) — netērē bērna pieprasījumu.
            $vvzCat = vvz_category((string)($d['druhFormulare'] ?? ''), $d);
            if (konkursi_deep() && substr($pub, 0, 10) < $freshCut
                && $vvzCat !== 'iepirkumi' && $vvzCat !== 'rezultati') {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }

            $child = null;
            if ($details < $maxDetails) {
                usleep(400000);
                $cb = ks_http_get(VVZ_CHILDREN_URL . '?' . http_build_query(['submission' => (string)($sub['id'] ?? '')]), ['Accept: application/json']);
                $details++;
                $cd = $cb !== null ? json_decode($cb, true) : null;
                if (is_array($cd) && isset($cd[0]['data']['ND-Root']) && is_array($cd[0]['data']['ND-Root'])) {
                    $child = $cd[0]['data']['ND-Root'];
                }
            } else {
                continue; // limits sasniegts — paliks nākamajai palaišanai (bez atzīmes)
            }

            $n = vvz_build_notice($sub, $child);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
        if ($reachedOld) break;
        sleep(1);
    }
    if ($details < $maxDetails) {
        // Viss logs apstrādāts — kursoru drīkst pārcelt
        konkursi_meta_set($pdo, 'vvz_since', $maxSeen);
    }

    // Dziļajā aizpildē: atvērtie nacionālie neatkarīgi no publicēšanas datuma
    // (DNS/kvalifikācijas sistēmas ar termiņiem līdz pat 2060 + korekcijas ar
    // pagarinātu termiņu, ko augšējā cilpa izlaiž kā "vecas izmaiņas").
    // Saraksta lauks lhutaNabidkyZadosti ļauj filtrēt servera pusē.
    // AKTĪVO PILNĀ KOPA pa servera termiņa filtru (lhutaNabidkyZadosti[gte]=šodiena) —
    // NEATKARĪGI no publicēšanas datuma. Agrāk TIKAI dziļajā režīmā; tagad ARĪ parastajā,
    // jo tā ir efektīvākā metode pilnam aktīvo nacionālo sarakstam (~194): publicēšanas
    // loga cilpa noķer tikai svaigos, bet atvērts konkurss ar vecāku publicēšanas datumu
    // (vai pagarinātu termiņu) tā tiktu palaists garām. Bērna (detaļu) pieprasījumus
    // ierobežo kopīgais $maxDetails budžets.
    $openOld = 0;
    $openPages = konkursi_deep() ? 40 : 25;
    $todayCz = konkursi_today();
    $inDb = $pdo->prepare('SELECT 1 FROM notices WHERE id = ?');
    for ($page = 1; $page <= $openPages; $page++) {
        if (ks_stop_requested() || $details >= $maxDetails) break;
        $q = http_build_query([
            'formGroup' => 'vz', 'form' => 'vz', 'page' => $page, 'limit' => 100,
            'workflowPlace' => 'UVEREJNENO_VVZ',
            'data.formularZneplatnen' => 'false',
            'data.lhutaNabidkyZadosti[gte]' => $todayCz,
            'order[data.datumUverejneniVvz]' => 'DESC',
        ]);
        $body = ks_http_get(VVZ_SEARCH_URL . '?' . $q, ['Accept: application/json']);
        $rows = $body !== null ? json_decode($body, true) : null;
        if (!is_array($rows) || !$rows) break;
        foreach ($rows as $sub) {
            if (ks_stop_requested() || $details >= $maxDetails) break 2;
            $formNr = (string)($sub['variableId'] ?? '');
            if ($formNr === '') continue;
            $dOpen = $sub['data'] ?? [];
            // Klienta puses TED filtrs (serveris uverejnitTed IGNORĒ): TED plūsmā ejošie
            // ienāk caur TED paketēm — te tos dublētu.
            if (($dOpen['uverejnitTed'] ?? false) === true || !empty($dOpen['evCisloTed'])) continue;
            // Izšķir tikai tas, vai rinda jau ir notices (imported_files=0 atzīmi ignorē).
            $inDb->execute(['VVZ-' . $formNr]);
            if ($inDb->fetchColumn() !== false) continue;
            usleep(400000);
            $cb = ks_http_get(VVZ_CHILDREN_URL . '?' . http_build_query(['submission' => (string)($sub['id'] ?? '')]), ['Accept: application/json']);
            $details++;
            $cd = $cb !== null ? json_decode($cb, true) : null;
            $child = (is_array($cd) && isset($cd[0]['data']['ND-Root']) && is_array($cd[0]['data']['ND-Root']))
                ? $cd[0]['data']['ND-Root'] : null;
            $n = vvz_build_notice($sub, $child);
            if ($n === null) continue;
            // Korekcija ar dzīvu termiņu IR piesakāms konkurss (tāpat kā ks_recategorize_open).
            if ($n['category'] === 'izmainas' && $n['deadline_date'] !== null && $n['deadline_date'] >= $todayCz) {
                $n['category'] = 'iepirkumi';
            }
            if ($n['category'] !== 'iepirkumi' || !ks_within_retention($n)) continue;
            $stmt->execute($n);
            $rec->execute(['VVZ:' . $formNr, date('c'), 1]);
            $openOld++;
        }
        if (count($rows) < 100) break;
        sleep(1);
    }

    if ($imported > 0 || $openOld > 0) {
        ks_log("  ✓ VVZ → $imported nacionālie formulāri, $openOld vecie atvērtie ($details detaļas).");
    }
    return $imported + $openOld;
}

// ── ÚVO Vestník (SK) sinhronizācija ───────────────────────────────────────────

/**
 * Slovākijas vestníka dienas lapas (pēdējās UVO_LOOKBACK_DAYS dienas): nacionālās
 * sekcijas WY (výzvy) / IP (podlimitné rezultāti) / DO+S (grozījumi, opravy);
 * katram jaunajam ierakstam detaļu lapa. Diena, kurā visi ieraksti apstrādāti,
 * tiek atzīmēta un vairs netiek vaicāta.
 * @return int importēto skaits
 */
function ks_sync_uvo(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0; $details = 0;
    $tz = new DateTimeZone('Europe/Riga');
    $today = new DateTimeImmutable(konkursi_today(), $tz);
    $lookback   = ks_cap(UVO_LOOKBACK_DAYS, konkursi_deep_days());
    $maxDetails = ks_cap(UVO_MAX_DETAILS_PER_RUN, 3000);
    $freshCut   = ks_backfill_fresh_cut();

    for ($back = 0; $back < $lookback; $back++) {
        if (ks_stop_requested()) break;
        $day = $today->modify("-$back days");
        $iso = $day->format('Y-m-d');
        $dayKey = 'UVOD:' . $iso;
        if ($back > 0) { // šodienu vienmēr pārbauda vēlreiz (vestníks papildinās)
            $chk->execute([$dayKey]);
            if ($chk->fetchColumn() !== false) continue;
        }

        $html = ks_http_get(sprintf(UVO_DAY_URL_FMT, $day->format('d.m.Y')), [], 45);
        if ($html === null) { ks_log("  ⚠ ÚVO diena $iso nav pieejama."); continue; }
        $rows = uvo_parse_day($html);
        $dayComplete = true;

        foreach ($rows as $row) {
            if (ks_stop_requested()) break 2;
            $key = 'UVO:' . $row['id'];
            $chk->execute([$key]);
            if ($chk->fetchColumn() !== false) continue;
            // Dziļajā aizpildē: vecas ne-iepirkumu sekcijas (IP/DO/I) tiks izmestas
            // tāpat (ks_backfill_keep) — netērē detaļas pieprasījumu.
            if (konkursi_deep() && $iso < $freshCut
                && $row['category'] !== 'iepirkumi' && $row['category'] !== 'rezultati') {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            if ($details >= $maxDetails) { $dayComplete = false; break; }

            sleep(UVO_REQUEST_DELAY_S);
            $dh = ks_http_get(sprintf(UVO_DETAIL_URL_FMT, $row['id']), [], 30);
            $details++;
            if ($dh === null) { $dayComplete = false; continue; }
            $det = uvo_parse_detail($dh);
            $n = uvo_build_notice($row, $det, $iso);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
        if ($dayComplete && $back > 0) $rec->execute([$dayKey, date('c'), count($rows)]);
    }
    if ($imported > 0) ks_log("  ✓ ÚVO → $imported nacionālie paziņojumi ($details detaļas).");
    return $imported;
}

// ── BOSA (BE) sinhronizācija ──────────────────────────────────────────────────

/** Anonīmais Keycloak tokens (kešots meta tabulā ~50 min). */
function ks_bosa_token(PDO $pdo): ?string {
    $tok = konkursi_meta_get($pdo, 'bosa_token');
    $exp = (int)(konkursi_meta_get($pdo, 'bosa_token_exp') ?? '0');
    if ($tok !== null && $exp > time() + 120) return $tok;
    $body = ks_http_post_form(BOSA_TOKEN_URL, [
        'grant_type' => 'client_credentials',
        'client_id' => BOSA_CLIENT_ID,
        'client_secret' => BOSA_CLIENT_SECRET,
    ]);
    $d = $body !== null ? json_decode($body, true) : null;
    if (!is_array($d) || empty($d['access_token'])) return null;
    konkursi_meta_set($pdo, 'bosa_token', (string)$d['access_token']);
    konkursi_meta_set($pdo, 'bosa_token_exp', (string)(time() + (int)($d['expires_in'] ?? 3600) - 600));
    return (string)$d['access_token'];
}

/** BOSA galvenes: katram pieprasījumam OBLIGĀTI unikāls BelGov-Trace-Id UUID. */
function ks_bosa_headers(string $tok): array {
    $u = bin2hex(random_bytes(16));
    $trace = sprintf('%s-%s-%s-%s-%s', substr($u, 0, 8), substr($u, 8, 4), substr($u, 12, 4), substr($u, 16, 4), substr($u, 20, 12));
    return [
        'Authorization: Bearer ' . $tok,
        'Account-Type: public',
        'BelGov-Trace-Id: ' . $trace,
        'Accept-Language: en',
    ];
}

/**
 * Beļģijas publikācijas: meklēšanas API lapas (jaunākās pirmās), tedPublished
 * dedup parserī; aktīvajiem konkursiem termiņu paņem no workspace (1 pieprasījums,
 * atzīmē imported_files, lai neatkārtotos).
 * @return int importēto skaits
 */
function ks_sync_bosa(PDO $pdo): int {
    $tok = ks_bosa_token($pdo);
    if ($tok === null) { ks_log('  ⚠ BOSA tokens nav iegūstams.'); return 0; }
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT 1 FROM notices WHERE id = ?');
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0; $wsFetched = 0;
    $maxWs = ks_cap(BOSA_MAX_WS_PER_RUN, 4000);

    for ($page = 1; $page <= ks_cap(BOSA_MAX_PAGES, 90); $page++) {
        if (ks_stop_requested()) break;
        $body = ks_http_post_json(BOSA_SEARCH_URL,
            ['includeOrganisationChildren' => true, 'page' => $page, 'pageSize' => 100],
            ks_bosa_headers($tok));
        $d = $body !== null ? json_decode($body, true) : null;
        $pubs = is_array($d) ? ($d['publications'] ?? null) : null;
        if (!is_array($pubs) || !$pubs) {
            if ($page === 1) ks_log('  ⚠ BOSA meklēšana nav pieejama.');
            break;
        }

        $newOnPage = 0; $freshOnPage = 0;
        foreach ($pubs as $p) {
            if (!is_array($p)) continue;
            $rawPub = substr((string)($p['publicationDate'] ?? ''), 0, 10);
            if ($rawPub === '' || $rawPub >= ks_active_cutoff()) $freshOnPage++;
            $n = bosa_parse_publication($p);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;

            // Termiņš aktīvajiem — parasti jau sarakstā (vaultSubmissionDeadline);
            // workspace vaicā tikai atkāpei, ja saraksts to nedeva.
            if ($n['category'] === 'iepirkumi' && $n['deadline_date'] === null && $wsFetched < $maxWs) {
                $wsId = (string)$p['publicationWorkspaceId'];
                $wsKey = 'BOSAWS:' . $wsId;
                $chk->execute([$wsKey]);
                if ($chk->fetchColumn() === false) {
                    usleep(400000);
                    $wb = ks_http_get(sprintf(BOSA_WS_URL_FMT, $wsId), ks_bosa_headers($tok), 30);
                    $wsFetched++;
                    $wd = $wb !== null ? json_decode($wb, true) : null;
                    if (is_array($wd)) {
                        $dl = $wd['vault']['submissionDeadline'] ?? null;
                        [$n['deadline_date'], $n['deadline_time']] = nord_iso_dt(is_string($dl) ? $dl : null);
                        $rec->execute([$wsKey, date('c'), 1]);
                    }
                }
            }

            $exists->execute([$n['id']]);
            if ($exists->fetchColumn() === false) $newOnPage++;
            $stmt->execute($n);
            $imported++;
        }
        // Dziļajā aizpildē lapa var būt 100% TED — apstājas tikai pie datuma horizonta
        if (konkursi_deep() ? $freshOnPage === 0 : $newOnPage === 0) break;
        sleep(1);
    }

    // Termiņu piepilde: aktīvie bez termiņa, kuriem workspace vēl nav vaicāts
    // (lapu skenēšana apstājas zināmajā zonā, tāpēc vecāki ieraksti citādi paliktu tukši)
    if ($wsFetched < $maxWs && !ks_stop_requested()) {
        $updDl = $pdo->prepare('UPDATE notices SET deadline_date = ?, deadline_time = ? WHERE id = ?');
        // fetchAll — cilpā ir UPDATE/INSERT; rakstīšana atvērta kursora laikā
        // paralēlajā fāzē dotu SQLITE_BUSY_SNAPSHOT (busy_timeout nepalīdz).
        $q = $pdo->query('SELECT id FROM notices WHERE source=\'BOSA\' AND category=\'iepirkumi\' AND deadline_date IS NULL ORDER BY publication_date DESC LIMIT ' . ks_cap(300, 4000))->fetchAll();
        foreach ($q as $r) {
            if ($wsFetched >= $maxWs || ks_stop_requested()) break;
            $wsId = substr((string)$r['id'], 5);
            $wsKey = 'BOSAWS:' . $wsId;
            $chk->execute([$wsKey]);
            if ($chk->fetchColumn() !== false) continue;
            usleep(400000);
            $wb = ks_http_get(sprintf(BOSA_WS_URL_FMT, $wsId), ks_bosa_headers($tok), 30);
            $wsFetched++;
            $wd = $wb !== null ? json_decode($wb, true) : null;
            if (is_array($wd)) {
                $dl = $wd['vault']['submissionDeadline'] ?? null;
                [$dd, $dt] = nord_iso_dt(is_string($dl) ? $dl : null);
                if ($dd !== null) $updDl->execute([$dd, $dt, (string)$r['id']]);
                $rec->execute([$wsKey, date('c'), 1]); // atbildēja — vairs nevaicā (arī ja termiņa nav)
            }
        }
    }
    // AKTĪVIE ar vecāku publicēšanas datumu (DPS/ilgie ietvari — termiņi līdz 2027+):
    // dienas plūsma tos nekad nesasniedz. Servera puses termiņa filtra un kārtošanas
    // NAV (parametrus klusi ignorē — tas pats slazds kā VVZ), tāpēc staigā publicēšanas
    // mēnešu šķēlēs un šķiro pēc vaultSubmissionDeadline klienta pusē. Agrāk TIKAI
    // dziļajā; tagad ARĪ parastajā ar īsāku logu (4 mēn.) — jau importētos izlaiž
    // ($exists), tāpēc slodze ir tikai saraksta lapas.
    $openOld = 0;
    if (!ks_stop_requested()) {
        $tz = new DateTimeZone('Europe/Riga');
        $tomorrow = (new DateTimeImmutable(konkursi_today(), $tz))->modify('+1 day')->format('Y-m-d');
        $winEnd = (new DateTimeImmutable(konkursi_today(), $tz))->modify('-' . konkursi_deep_days() . ' days');
        $monthsBack = konkursi_deep() ? 12 : 4;
        $pageCap    = konkursi_deep() ? 100 : 30;
        $mStart = $winEnd->modify("-$monthsBack months");
        for ($m = new DateTimeImmutable($mStart->format('Y-m-01'), $tz); $m < $winEnd; $m = $m->modify('+1 month')) {
            $from = $m->format('Y-m-d');
            $to = min($m->modify('+1 month'), $winEnd)->format('Y-m-d');
            for ($page = 1; $page <= $pageCap; $page++) {
                if (ks_stop_requested()) break 2;
                $body = ks_http_post_json(BOSA_SEARCH_URL,
                    ['page' => $page, 'pageSize' => 100, 'publicationDateFrom' => $from, 'publicationDateTo' => $to],
                    ks_bosa_headers($tok));
                $d = $body !== null ? json_decode($body, true) : null;
                $pubs = is_array($d) ? ($d['publications'] ?? null) : null;
                if (!is_array($pubs) || !$pubs) break;
                foreach ($pubs as $p) {
                    if (!is_array($p)) continue;
                    // Darbvieta ar svaigu pārpublicējumu jau nāk pa dienas plūsmu
                    if (substr((string)($p['publicationDate'] ?? ''), 0, 10) >= ks_active_cutoff()) continue;
                    $dl = substr((string)($p['vaultSubmissionDeadline'] ?? ''), 0, 10);
                    if ($dl === '' || $dl < $tomorrow) continue;
                    $n = bosa_parse_publication($p);
                    if ($n === null || $n['category'] !== 'iepirkumi') continue;
                    $exists->execute([$n['id']]);
                    if ($exists->fetchColumn() !== false) continue;
                    $stmt->execute($n);
                    $openOld++;
                }
                if (count($pubs) < 100) break;
                usleep(300000);
            }
        }
    }

    if ($imported > 0 || $openOld > 0) {
        ks_log("  ✓ BOSA → $imported nacionālie paziņojumi, $openOld vecie atvērtie ($wsFetched termiņi).");
    }
    return $imported + $openOld;
}

// ── Kerndaten KDQ (AT) sinhronizācija ─────────────────────────────────────────

/**
 * Austrijas platformu KDQ rindas: katrai plūsmai indekss (id+lastmod+url),
 * jaunos/mainītos dokumentus lejupielādē atsevišķi. imported_files atslēgā
 * iekļauts lastmod — grozīts dokuments tiek pārimportēts.
 * @return int importēto skaits
 */
function ks_sync_atkd(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0; $fetched = 0;
    $maxItems = ks_cap(ATKD_MAX_ITEMS_PER_RUN, 6000);
    $cutoff = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . KONKURSI_KEEP_RESULTS_DAYS . ' days')->format('Y-m-d');

    foreach (ATKD_FEEDS as $feed => $indexUrl) {
        if (ks_stop_requested() || $fetched >= $maxItems) break;
        $idx = ks_http_get($indexUrl, [], 120);
        if ($idx === null) { ks_log("  ⚠ KDQ indekss '$feed' nav pieejams."); continue; }
        $items = atkd_parse_index($idx);
        unset($idx);
        if (!$items) continue;
        // Jaunākie vispirms (ANKÖ jau ir DESC, pārējie var būt ASC)
        usort($items, fn($a, $b) => strcmp($b[1], $a[1]));

        // Dziļajā aizpildē: vemap paziņojumu formas (VIII-1 failu vārdā) skenē
        // 12 mēnešus atpakaļ — atvērts konkurss ar tālu termiņu var būt bez
        // izmaiņām (lastmod vecs) un citādi paliktu aiz 60 d robežas. Citām
        // plūsmām dokumentos termiņa tāpat nav, tāpēc vecos neņem.
        $deepCut = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
            ->modify('-12 months')->format('Y-m-d');

        foreach ($items as [$id, $lastmod, $url]) {
            if (ks_stop_requested() || $fetched >= $maxItems) break;
            if (substr($lastmod, 0, 10) < $cutoff) { // vecāki par glabāšanas logu
                if (!konkursi_deep()) break;
                if (substr($lastmod, 0, 10) < $deepCut) break;
                if (!str_contains($url, 'VIII-1')) continue;
            }
            $key = 'ATKD:' . $feed . ':' . $id . ':' . substr($lastmod, 0, 19);
            $chk->execute([$key]);
            if ($chk->fetchColumn() !== false) continue;

            usleep(ATKD_ITEM_DELAY_MS * 1000);
            $xml = ks_http_get($url, [], 30);
            $fetched++;
            if ($xml === null || trim($xml) === '') continue; // bez atzīmes — mēģinās nākamreiz
            $n = atkd_parse_item($xml, $feed, $id, $url, $lastmod);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
    }
    // Dedup pret TED pēc nosaukuma+pircēja: ABOVETHRESHOLD karogs KDQ dokumentos
    // nav uzticams (2026-07-20 mērījums: 454/1065 aktīvo dublēja TED) — pircēji
    // publicē Kerndaten arī ES līmeņa procedūrām bez karoga. TED rinda paliek.
    $del = ks_dedupe_vs_ted($pdo, 'ATKD', 'AT');
    if ($del > 0) ks_log("  ⧉ Kerndaten (AT): $del dublējās ar TED — noņemti (paliek TED rinda).");

    if ($imported > 0) ks_log("  ✓ Kerndaten (AT) → $imported paziņojumi ($fetched dokumenti).");
    return $imported;
}

// ── SEAP (RO) sinhronizācija ──────────────────────────────────────────────────

/**
 * Rumānijas nacionālie paziņojumi + rezultāti no api-pub sarakstiem (viss ir
 * sarakstā — bez detaļu pieprasījumiem). Datuma logs no meta kursora; jau
 * apstrādātie izlaižami pēc imported_files atzīmes.
 * @return int importēto skaits
 */
function ks_sync_seap(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0;
    $tz = new DateTimeZone('Europe/Riga');
    $since = konkursi_meta_get($pdo, 'seap_since')
        ?? (new DateTimeImmutable(konkursi_today(), $tz))->modify('-' . SEAP_BACKFILL_DAYS . ' days')->format('Y-m-d');
    $since = ks_window_start($since);
    $maxPages = ks_cap(SEAP_MAX_PAGES, 15); // 15 × 200 = 3000 = API griesti vienam vaicājumam
    $complete = true;

    foreach ([[SEAP_CN_URL, false, 'CN saraksts'], [SEAP_CAN_URL, true, 'CAN saraksts']] as [$url, $isAward, $label]) {
        if (ks_stop_requested()) break;
        // Loga sākums: kursors mīnus 1 diena (laika zonu/kavējumu rezerve).
        // Rezultātu (CAN) dziļajā aizpildē ņem tikai svaigos — kā visur.
        $winStart = $since; // rezultātiem svaiguma robeža noņemta
        // API atdod ne vairāk kā 3000 ierakstus vienam vaicājumam → dziļajā
        // aizpildē logu dala ~10 dienu šķēlēs (RO nacionālie ~200/dienā).
        $slices = [];
        if (konkursi_deep()) {
            $d = new DateTimeImmutable($winStart, $tz);
            $end = new DateTimeImmutable(konkursi_today(), $tz);
            while ($d <= $end) {
                $slices[] = $d->modify('-1 day')->format('Y-m-d');
                $d = $d->modify('+10 days');
            }
        } else {
            $slices[] = (new DateTimeImmutable($winStart, $tz))->modify('-1 day')->format('Y-m-d');
        }

        foreach ($slices as $sliceFrom) {
            if (ks_stop_requested()) break 2;
            $req = [
                'sysNoticeTypeIds' => [], 'sortProperties' => [],
                'pageIndex' => 0, 'pageSize' => 200,
                'startPublicationDate' => $sliceFrom . 'T00:00:00.000Z',
            ];
            if (konkursi_deep()) {
                // Šķēles beigas ar pārklāšanos (dublikātus izķer atzīmes)
                $req['endPublicationDate'] = (new DateTimeImmutable($sliceFrom, $tz))
                    ->modify('+12 days')->format('Y-m-d') . 'T00:00:00.000Z';
            }
            for ($page = 0; $page < $maxPages; $page++) {
                if (ks_stop_requested()) break 3;
                $req['pageIndex'] = $page;
                $body = ks_http_post_json($url, $req, ['Referer: ' . SEAP_REFERER]);
                $d = $body !== null ? json_decode($body, true) : null;
                $items = is_array($d) ? ($d['items'] ?? null) : null;
                // Tukšs saraksts NAV kļūda (sk. enarocanje): kad kursors sasniedzis
                // šodienu, nedēļas nogales logā Rumānijā nav ko publicēt, un API
                // atdod 0 vienumus par 0,4 s. Brīdinājums tur maldinātu.
                if (!is_array($items)) {
                    if ($page === 0 && count($slices) === 1) ks_log("  ⚠ SEAP $label nav pieejams.");
                    break;
                }
                if (!$items) break;
                $newOnPage = 0;
                foreach ($items as $it) {
                    if (!is_array($it)) continue;
                    $no = (string)($it['noticeNo'] ?? '');
                    if ($no === '') continue;
                    $key = 'SEAP:' . $no;
                    $chk->execute([$key]);
                    if ($chk->fetchColumn() !== false) continue;
                    $newOnPage++;
                    $n = seap_parse_item($it, $isAward);
                    if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                        $rec->execute([$key, date('c'), 0]);
                        continue;
                    }
                    $stmt->execute($n);
                    $rec->execute([$key, date('c'), 1]);
                    $imported++;
                }
                if (count($items) < 200) break;
                if (!konkursi_deep() && $newOnPage === 0) break;
                if ($page === $maxPages - 1) $complete = false; // lapu limits izsmelts
                sleep(1);
            }
            sleep(1);
        }
    }
    // Kursoru pārceļ tikai tad, ja logs izsmelts (citādi caurums vēsturē)
    if ($complete) konkursi_meta_set($pdo, 'seap_since', konkursi_today());

    // Dedup pret TED: noticeNo prefikss šķir CN/CAN (ES līmenis), bet PC
    // (koncesijas) virs ES koncesiju sliekšņa iet uz TED ar to pašu nosaukumu —
    // prefiksā tas neparādās (2026-07-20: 7 no 7 pārklājumiem bija PC).
    $del = ks_dedupe_vs_ted($pdo, 'SEAP', 'RO');
    if ($del > 0) ks_log("  ⧉ SEAP: $del dublējās ar TED — noņemti (paliek TED rinda).");

    if ($imported > 0) ks_log("  ✓ SEAP → $imported nacionālie paziņojumi.");
    return $imported;
}

// ── CAIS EOP (BG) sinhronizācija ──────────────────────────────────────────────

/**
 * Bulgārijas atvērtie nacionālie konkursi (Status=1, publicēšanas dilstoši).
 * Lapo līdz zināmajai zonai; ES līmeņa procedūras izlaiž parseris.
 * @return int importēto skaits
 */
function ks_sync_eop(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT 1 FROM notices WHERE id = ?');
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0;

    for ($page = 0; $page < ks_cap(EOP_MAX_PAGES, 40); $page++) {
        if (ks_stop_requested()) break;
        $body = ks_http_post_json(EOP_SEARCH_URL, ['searchParameters' => [
            'StartIndex' => $page * 100 + 1, 'EndIndex' => ($page + 1) * 100,
            'PropertyFilters' => [], 'SearchText' => '', 'Keywords' => [],
            'SearchProperty' => ['PropertyDisplayName' => 'str_Today_opened', 'PropertyName' => 'Status', 'PropertyValue' => '1'],
            'OrderAscending' => false, 'OrderColumn' => 'PublicationDate',
        ]], ['Origin: https://app.eop.bg', 'Referer: https://app.eop.bg/today']);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['CurrentPageResults'] ?? null) : null;
        if (!is_array($rows) || !$rows) {
            if ($page === 0) ks_log('  ⚠ EOP saraksts nav pieejams.');
            break;
        }
        $newOnPage = 0;
        foreach ($rows as $it) {
            if (!is_array($it)) continue;
            $tid = (int)($it['TenderId'] ?? 0);
            if ($tid <= 0) continue;
            $key = 'EOP:' . $tid;
            $chk->execute([$key]);
            if ($chk->fetchColumn() !== false) continue;
            $newOnPage++;
            $n = eop_parse_item($it);
            if ($n === null || !ks_within_retention($n)) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
        // Saraksts = tikai atvērtie konkursi → dziļajā aizpildē iet līdz beigām
        if (!konkursi_deep() && $newOnPage === 0) break;
        if (count($rows) < 100) break;
        sleep(1);
    }
    // 2. fāze: slēgtās procedūras (Status=2) — nacionālie rezultāti. Saraksts
    // kārtots pēc publicēšanas dilstoši, tāpēc pietiek ar dažām pirmajām lapām:
    // ~27 nacionālie dienā, un rezultātus tāpat cērt KONKURSI_RESULTS_CAP.
    $results = 0;
    $resPages = konkursi_deep() ? 40 : 3;
    for ($page = 0; $page < $resPages; $page++) {
        if (ks_stop_requested()) break;
        $body = ks_http_post_json(EOP_SEARCH_URL, ['searchParameters' => [
            'StartIndex' => $page * 100 + 1, 'EndIndex' => ($page + 1) * 100,
            'PropertyFilters' => [], 'SearchText' => '', 'Keywords' => [],
            'SearchProperty' => ['PropertyDisplayName' => 'str_Today_closed', 'PropertyName' => 'Status', 'PropertyValue' => '2'],
            'OrderAscending' => false, 'OrderColumn' => 'PublicationDate',
        ]], ['Origin: https://app.eop.bg', 'Referer: https://app.eop.bg/today']);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['CurrentPageResults'] ?? null) : null;
        if (!is_array($rows) || !$rows) break;
        $oldOnPage = 0;
        foreach ($rows as $it) {
            if (!is_array($it)) continue;
            $tid = (int)($it['TenderId'] ?? 0);
            if ($tid <= 0) continue;
            // Slēgtajiem sava atslēga: to pašu procedūru vispirms redzējām atvērtu
            $key = 'EOPC:' . $tid;
            $chk->execute([$key]);
            if ($chk->fetchColumn() !== false) continue;
            $n = eop_parse_item($it);
            if ($n === null) { $rec->execute([$key, date('c'), 0]); continue; }
            // ks_backfill_keep (14 d) te apzināti NETIEK piemērots: tas sargā no
            // arhīva pūšanas avotiem, kuriem vēsture jau ir, bet EOP rezultāti ir
            // jauns avots ar nulles vēsturi — ar 14 d tas sāktu ar ~40 rindām.
            // Apjomu ierobežo ks_within_retention (60 d) + KONKURSI_RESULTS_CAP.
            if (!ks_within_retention($n)) {
                $oldOnPage++;
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $results++;
        }
        if ($oldOnPage === count($rows)) break; // visa lapa jau ārpus glabāšanas loga
        if (count($rows) < 100) break;
        sleep(1);
    }

    // Atlikušais pārklājums pēc ProcedureType šķirošanas (PT 17/18 daļa tomēr
    // aiziet arī uz TED — 4-9% mērījums 2026-07-20)
    $del = ks_dedupe_vs_ted($pdo, 'EOP', 'BG');
    if ($del > 0) ks_log("  ⧉ EOP: $del dublējās ar TED — noņemti (paliek TED rinda).");

    if ($imported > 0 || $results > 0) {
        ks_log("  ✓ EOP → $imported nacionālie konkursi, $results slēgtie (rezultāti).");
    }
    return $imported + $results;
}

// ── ΚΗΜΔΗΣ KIMDIS (GR) sinhronizācija ─────────────────────────────────────────

/**
 * Grieķijas izsludinājumi (/notice) + līgumi (/contract) datuma logā; tiešos
 * piešķīrumus un virs-sliekšņa (TED) izlaiž parseris.
 * @return int importēto skaits
 */
function ks_sync_kimdis(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0;
    $tz = new DateTimeZone('Europe/Riga');
    $today = konkursi_today();
    $since = konkursi_meta_get($pdo, 'kimdis_since')
        ?? (new DateTimeImmutable($today, $tz))->modify('-' . KIMDIS_BACKFILL_DAYS . ' days')->format('Y-m-d');
    $since = ks_window_start($since);
    $maxPages = ks_cap(KIMDIS_MAX_PAGES, 1200); // 60 d logā ir ~1000 lapas pa 50 (90% — tiešie piešķīrumi, tos izmet parseris)
    $complete = true;

    foreach ([[KIMDIS_NOTICE_URL, 'notice'], [KIMDIS_CONTRACT_URL, 'contract']] as [$url, $kind]) {
        if (ks_stop_requested()) break;
        // Līgumi (rezultāti) dziļajā aizpildē — tikai svaigie (kā visur)
        $kindSince = $since; // rezultātiem svaiguma robeža noņemta
        $from = (new DateTimeImmutable($kindSince, $tz))->modify('-1 day')->format('Y-m-d');
        $sawLast = false;
        for ($page = 0; $page < $maxPages; $page++) {
            if (ks_stop_requested()) break;
            $body = ks_http_post_json($url . '?page=' . $page, ['dateFrom' => $from, 'dateTo' => $today]);
            $d = $body !== null ? json_decode($body, true) : null;
            $rows = is_array($d) ? ($d['content'] ?? null) : null;
            // Tukšs saraksts NAV kļūda (sk. enarocanje/SEAP): vaicājumam ir datuma
            // logs dateFrom..dateTo, un, kad kursors sasniedzis šodienu, klusā dienā
            // tas var būt tukšs. Šeit tikai HTTP/formāta kļūme ir brīdinājuma vērta.
            if (!is_array($rows)) {
                if ($page === 0) ks_log("  ⚠ KIMDIS /$kind nav pieejams.");
                break;
            }
            if (!$rows) break;
            $newOnPage = 0;
            foreach ($rows as $it) {
                if (!is_array($it)) continue;
                $ref = (string)($it['referenceNumber'] ?? '');
                if ($ref === '') continue;
                $key = 'KIMDIS:' . $ref;
                $chk->execute([$key]);
                if ($chk->fetchColumn() !== false) continue;
                $newOnPage++;
                $n = kimdis_parse_item($it, $kind);
                if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                    $rec->execute([$key, date('c'), 0]);
                    continue;
                }
                $stmt->execute($n);
                $rec->execute([$key, date('c'), 1]);
                $imported++;
            }
            $last = (bool)($d['last'] ?? true);
            if ($last) { $sawLast = true; break; }
            if (!konkursi_deep() && $newOnPage === 0) { $sawLast = true; break; }
            usleep(400000);
        }
        if (!$sawLast) $complete = false; // lapu limits izsmelts — logs nav pabeigts
        sleep(1);
    }
    if ($complete) konkursi_meta_set($pdo, 'kimdis_since', $today);

    // Grieķu iestādes (īpaši slimnīcas) katram atsevišķam mazajam iepirkumam
    // lieto vienu un to pašu veidnes nosaukumu ("Πρόσκληση Υποβολής Προσφορών
    // για την προμήθεια..."), un īstais priekšmets ir aprakstā. Sarakstā tas
    // izskatās pēc 21 dublikāta, lai gan CPV, budžets un apraksts atšķiras
    // (pārbaudīts 2026-07-20). Tāpēc atkārtotiem nosaukumiem pieliek priekšmetu.
    // Idempotents: pieliek tikai tad, ja tā vēl nav; separators ' · '.
    $titled = (int)$pdo->exec("
        UPDATE notices SET title = substr(title || ' · ' || replace(description, char(10), ' '), 1, 400)
        WHERE source = 'KIMDIS' AND description IS NOT NULL AND trim(description) <> ''
          AND instr(title, ' · ') = 0
          AND EXISTS (SELECT 1 FROM notices o WHERE o.source = 'KIMDIS' AND o.id <> notices.id
                      AND o.category = notices.category
                      AND lower(trim(o.title)) = lower(trim(notices.title))
                      AND lower(trim(o.buyer_name)) = lower(trim(notices.buyer_name)))");
    if ($titled > 0) ks_log("  ✎ KIMDIS: $titled veidnes nosaukumiem pievienots priekšmets no apraksta.");

    if ($imported > 0) ks_log("  ✓ KIMDIS → $imported paziņojumi.");
    return $imported;
}

// ── enarocanje.si (SI) sinhronizācija ─────────────────────────────────────────

/**
 * Slovēnijas grid saraksts datuma logā + detaļu GET katram jaunajam (termiņš,
 * CPV, vērtība, uzvarētāji); CPV šifrants vienreiz palaišanā. ES formas izlaiž.
 * @return int importēto skaits
 */
function ks_sync_enar(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0; $details = 0;
    $tz = new DateTimeZone('Europe/Riga');
    $since = konkursi_meta_get($pdo, 'enar_since')
        ?? (new DateTimeImmutable(konkursi_today(), $tz))->modify('-' . ENAR_BACKFILL_DAYS . ' days')->format('Y-m-d');
    $since = ks_window_start($since);
    $from = (new DateTimeImmutable($since, $tz))->modify('-1 day')->format('Y-m-d') . 'T00:00:00.000Z';
    $maxRows    = ks_cap(ENAR_MAX_ROWS, 8000);
    $maxDetails = ks_cap(ENAR_MAX_DETAILS_PER_RUN, 6000);

    $cpvMap = [];
    $cb = ks_http_get(ENAR_CPV_URL, ['Accept: application/json'], 60);
    $ct = $cb !== null ? json_decode($cb, true) : null;
    if (is_array($ct)) $cpvMap = enar_cpv_map($ct);

    $allDone = true;
    for ($start = 0; $start < $maxRows; $start += 100) {
        if (ks_stop_requested()) break;
        $body = ks_http_post_json(ENAR_GRID_URL, [
            'page' => intdiv($start, 100) + 1, 'idSifCpv' => [], 'idSifPostopekFaza' => [],
            'podrejeniCpv' => true, 'datumDd' => 3, 'objavaDejanskaDatumOd' => $from,
            'startRow' => $start, 'endRow' => $start + 100,
            'sortModel' => [['colId' => 'objavaDejanskaDatum', 'sort' => 'desc']],
            'datumPonudbaDo' => null, 'datumPonudbaOd' => null,
        ]);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['data'] ?? null) : null;
        // Tukšs rezultāts NAV kļūda: nedēļas nogalē logs sedz dienas, kad Slovēnijā
        // neko nepublicē, un API godīgi atdod 0 rindu. Brīdinājums par to maldina —
        // logā izskatās pēc avota avārijas, kaut avots strādā (0,2 s, HTTP 200).
        if (!is_array($rows)) {
            if ($start === 0) ks_log('  ⚠ enarocanje grid nav pieejams.');
            break;
        }
        if (!$rows) break;
        $newOnPage = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $idObrazec = (int)($row['idObrazec'] ?? 0);
            if ($idObrazec <= 0) continue;
            $key = 'ENAR:' . $idObrazec;
            $chk->execute([$key]);
            if ($chk->fetchColumn() !== false) continue;
            $newOnPage++;

            $oznaka = (string)($row['sifObrazecOznaka'] ?? '');
            if ($oznaka === '' || str_starts_with($oznaka, 'EU')) {
                $rec->execute([$key, date('c'), 0]); // ES forma → TED
                continue;
            }
            // Dziļajā aizpildē: vecas rezultātu formas (SL2/SL4) tiks izmestas
            // tāpat (ks_backfill_keep) — netērē detaļas pieprasījumu.
            $rowDate = substr((string)($row['objavaDejanskaDatum'] ?? ''), 0, 10);
            // SL2/SL4 (rezultāti) vairs netiek izlaisti — svaiguma robeža noņemta
            $det = null;
            if ($details < $maxDetails) {
                usleep(400000);
                $db = ks_http_get(sprintf(ENAR_DETAIL_URL, $idObrazec), ['Accept: application/json'], 30);
                $details++;
                $det = $db !== null ? json_decode($db, true) : null;
                if (!is_array($det)) $det = null;
            } else {
                $allDone = false;
                continue; // limits — paliks nākamajai palaišanai bez atzīmes
            }
            $n = enar_build_notice($row, $det, $cpvMap);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
        if (count($rows) < 100) break;
        if (!konkursi_deep() && $newOnPage === 0) break;
        sleep(1);
    }
    if ($allDone && $details < $maxDetails) {
        konkursi_meta_set($pdo, 'enar_since', konkursi_today());
    }
    if ($imported > 0) ks_log("  ✓ enarocanje → $imported nacionālie paziņojumi ($details detaļas).");
    return $imported;
}

// ── EOJN RH (HR) sinhronizācija ───────────────────────────────────────────────

/**
 * Horvātijas gridi: sesijas sāknēšana (cepumi + uiUserToken), tad
 * TendersPublic + TendersSimple (iepirkumi; AboveThreshold=true → TED) un
 * VAwardDecisions (rezultāti tikai mūsu importētajiem konkursiem).
 * @return int importēto skaits
 */
function ks_sync_eojn(PDO $pdo): int {
    $jar = konkursi_tmp_dir() . '/eojn_cookies.txt';
    @unlink($jar);
    $boot = ks_http_get(EOJN_BOOT_URL, [], 45, $jar);
    $tok = null;
    if (is_string($boot) && preg_match('/id="uiUserToken" value="([0-9a-f-]{36})"/', $boot, $m)) $tok = $m[1];
    if ($tok === null) { ks_log('  ⚠ EOJN sesijas tokens nav iegūstams.'); return 0; }
    $hdrs = ['UserToken: ' . $tok, 'X-Requested-With: XMLHttpRequest', 'Accept: application/json', 'Referer: ' . EOJN_BOOT_URL];

    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $tender = $pdo->prepare("SELECT 1 FROM notices WHERE id = ? AND source = 'EOJN'");
    $imported = 0;

    // Grids NAV tikai aktīvie — tas ir viss arhīvs (TendersPublic 43846,
    // TendersSimple 7384), kārtots pēc Id dilstoši (jaunākie pirmie). Termiņa
    // filtrus un sort parametrus API klusi ignorē (pārbaudīts 2026-07-20), tāpēc
    // vienīgā robeža ir lapu skaits. Mērījums: atvērtie beidzas ap ~2900. rindu,
    // t.i. vecie 30 lapu griesti bija tieši uz robežas. Tagad iet dziļāk un
    // apstājas, kad vairākas lapas pēc kārtas bez neviena dzīva termiņa.
    $tomorrow = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('+1 day')->format('Y-m-d');
    foreach (['TendersPublic', 'TendersSimple'] as $grid) {
        if (ks_stop_requested()) break;
        $dryPages = 0;
        for ($p = 0; $p < ks_cap(EOJN_MAX_PAGES, 80); $p++) {
            if (ks_stop_requested()) break;
            $body = ks_http_get(sprintf(EOJN_GRID_URL_FMT, $grid, $p * 100), $hdrs, 45, $jar);
            $d = $body !== null ? json_decode($body, true) : null;
            $rows = is_array($d) ? ($d['data'] ?? null) : null;
            if (!is_array($rows) || !$rows) {
                if ($p === 0) ks_log("  ⚠ EOJN $grid nav pieejams.");
                break;
            }
            $newOnPage = 0; $liveOnPage = 0;
            foreach ($rows as $it) {
                if (!is_array($it)) continue;
                $rid = (int)($it['Id'] ?? 0);
                if ($rid <= 0) continue;
                if (substr((string)($it['SubmissionDeadline'] ?? ''), 0, 10) >= $tomorrow) $liveOnPage++;
                $key = 'EOJN:' . $rid;
                $chk->execute([$key]);
                if ($chk->fetchColumn() !== false) continue;
                $newOnPage++;
                $n = eojn_parse_tender($it);
                if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                    $rec->execute([$key, date('c'), 0]);
                    continue;
                }
                $stmt->execute($n);
                $rec->execute([$key, date('c'), 1]);
                $imported++;
            }
            if (count($rows) < 100) break;
            if (!konkursi_deep() && $newOnPage === 0) break;
            // Dziļajā: 5 lapas pēc kārtas (500 ierakstu) bez dzīva termiņa =
            // arhīva zona. Skaita pa lapām, ne pa vienu, jo kārtojums ir pēc
            // publicēšanas — atsevišķa lapa var būt tukša arī agrāk.
            $dryPages = $liveOnPage === 0 ? $dryPages + 1 : 0;
            if (konkursi_deep() && $dryPages >= 5) break;
            sleep(1);
        }
        sleep(1);
    }

    // Lēmumi (rezultāti) — tikai konkursiem, kas mums jau ir (zem-sliekšņa)
    if (!ks_stop_requested()) {
        for ($p = 0; $p < ks_cap(EOJN_MAX_PAGES, 30); $p++) {
            $body = ks_http_get(sprintf(EOJN_GRID_URL_FMT, 'VAwardDecisions', $p * 100), $hdrs, 45, $jar);
            $d = $body !== null ? json_decode($body, true) : null;
            $rows = is_array($d) ? ($d['data'] ?? null) : null;
            if (!is_array($rows) || !$rows) break;
            $newOnPage = 0;
            foreach ($rows as $it) {
                if (!is_array($it)) continue;
                $decId = (int)($it['TenderDecisionId'] ?? 0);
                if ($decId <= 0) continue;
                $key = 'EOJND:' . $decId;
                $chk->execute([$key]);
                if ($chk->fetchColumn() !== false) continue;
                $newOnPage++;
                $tid = (int)($it['MainTenderId'] ?? ($it['TenderId'] ?? 0));
                $tender->execute(['EOJN-' . $tid]);
                if ($tid <= 0 || $tender->fetchColumn() === false) {
                    $rec->execute([$key, date('c'), 0]); // ES līmeņa vai svešs konkurss
                    continue;
                }
                $n = eojn_parse_decision($it, $tid);
                if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                    $rec->execute([$key, date('c'), 0]);
                    continue;
                }
                $stmt->execute($n);
                $rec->execute([$key, date('c'), 1]);
                $imported++;
            }
            if (count($rows) < 100) break;
            if (!konkursi_deep() && $newOnPage === 0) break;
            sleep(1);
        }
    }
    @unlink($jar);
    if ($imported > 0) ks_log("  ✓ EOJN → $imported nacionālie paziņojumi.");
    return $imported;
}

// ── EKR (HU) sinhronizācija ───────────────────────────────────────────────────

/**
 * Ungārijas publiskais saraksts (publicēšanas dilstoši) līdz zināmajai zonai;
 * nacionālajiem detaļa (CPV, uzvarētājs, veids). ES formas izlaiž parseris.
 * @return int importēto skaits
 */
function ks_sync_ekr(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0; $details = 0;
    $maxPages   = ks_cap(EKR_MAX_PAGES, 130);
    $maxDetails = ks_cap(EKR_MAX_DETAILS_PER_RUN, 5000);
    $freshCut   = ks_backfill_fresh_cut();

    for ($page = 1; $page <= $maxPages; $page++) {
        if (ks_stop_requested()) break;
        $body = ks_http_get(EKR_LIST_URL . '?' . http_build_query([
            'oldal' => $page, 'elemszam' => 100,
            'rendezes' => 'hirdetmenyKozzetetelDatuma', 'novekvo' => 'false',
        ]), ['Accept: application/json']);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['lista'] ?? null) : null;
        if (!is_array($rows) || !$rows) {
            if ($page === 1) ks_log('  ⚠ EKR saraksts nav pieejams.');
            break;
        }
        $newOnPage = 0; $freshOnPage = 0;
        foreach ($rows as $it) {
            if (!is_array($it)) continue;
            $id = (string)($it['id'] ?? '');
            if ($id === '') continue;
            $rawPub = substr((string)($it['hirdetmenyKozzetetelDatuma'] ?? ''), 0, 10);
            if ($rawPub === '' || $rawPub >= ks_active_cutoff()) $freshOnPage++;
            $key = 'EKR:' . $id;
            $chk->execute([$key]);
            if ($chk->fetchColumn() !== false) continue;
            $newOnPage++;

            // ES formas izlaiž bez detaļas pieprasījuma
            if (!empty($it['tedAzonosito']) || str_starts_with((string)($it['hirdetmenyTipusa'] ?? ''), 'Uniós')) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            // Dziļajā aizpildē: veci rezultāti/grozījumi tiks izmesti tāpat — bez detaļas
            $ekrCat = ekr_category((string)($it['hirdetmenyTipusa'] ?? ''));
            if (konkursi_deep() && $rawPub !== '' && $rawPub < $freshCut
                && $ekrCat !== 'iepirkumi' && $ekrCat !== 'rezultati') {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $det = null;
            if ($details < $maxDetails) {
                usleep(400000);
                $db = ks_http_get(sprintf(EKR_DETAIL_URL_FMT, $id), ['Accept: application/json'], 30);
                $details++;
                $det = $db !== null ? json_decode($db, true) : null;
                if (!is_array($det)) $det = null;
            } else {
                continue; // limits — paliks nākamajai palaišanai
            }
            $n = ekr_build_notice($it, $det);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
        // Dziļajā aizpildē apstājas pie datuma horizonta (lapa var būt 100% ES formu)
        if (konkursi_deep() ? $freshOnPage === 0 : $newOnPage === 0) break;
        sleep(1);
    }
    if ($imported > 0) ks_log("  ✓ EKR → $imported nacionālie paziņojumi ($details detaļas).");
    return $imported;
}

// ── BASE (PT) sinhronizācija ──────────────────────────────────────────────────

/**
 * Portugāles anúncios DR (publicēšanas dilstoši) līdz zināmajai zonai;
 * detaļa katram jaunajam (CPV, NIF, DRE PDF). Virs-sliekšņa izlaiž parseris.
 * @return int importēto skaits
 */
/**
 * Apvienotā Karaliste — Contracts Finder (Anglija + AK mēroga) un Public
 * Contracts Scotland. Abi dod OCDS 1.1, tāpēc viens parseris (uk_ocds_notice).
 *
 * Skotija ir atsevišķs avots pēc nepieciešamības, nevis ērtības: tās
 * zem-sliekšņa konkursi uz AK centrālo platformu NEPLŪST vispār.
 * Dedup starp portāliem: rindas atslēga ir ocid.
 * @return int importēto skaits
 */
function ks_sync_uk(PDO $pdo): int {
    // Viens OCID = vairāki OCDS releases (konkurss + award + labojumi), katrs sava rinda
    // ar savu document_url/kategoriju. Agrēgējam pa id un paturam JAUNĀKO release (pēc
    // release datuma) — citādi tie pārrakstītu viens otru katrā palaišanā (~162 lieku
    // versiju/palaišanā, versija sasniedza 799!). Jaunākais = pareizais stāvoklis
    // (award > tender → kartiņa dabiski pāriet uz 'Rezultāti').
    $acc = []; // id => notice (ar lielāko _rdate)
    $collect = function (?array $n) use (&$acc): void {
        if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) return;
        $id = $n['id'];
        if (!isset($acc[$id]) || (string)($n['_rdate'] ?? '') > (string)($acc[$id]['_rdate'] ?? '')) {
            $acc[$id] = $n;
        }
    };
    $tz = new DateTimeZone('Europe/Riga');
    $today = new DateTimeImmutable(konkursi_today(), $tz);
    // Inkrementāli: šaurs logs no VECĀKĀS 3 UK apakšavotu ūdenszīmes (− pārklājums),
    // nevis fiksētas 60 dienas katrreiz. Vecākā = droši (nevienam nav šaurāks par tā
    // pašu drošo logu; ja apakšavotam ūdenszīmes vēl nav, ks_window_from dod pilno
    // logu → min to izvēlas). Dziļais režīms patur pilno vēstures logu.
    $from = konkursi_deep()
        ? $today->modify('-' . konkursi_deep_days() . ' days')
        : new DateTimeImmutable(min(
            ks_window_from($pdo, 'UKFTS'),
            ks_window_from($pdo, 'UKCF'),
            ks_window_from($pdo, 'UKPCS')
        ), $tz);

    // ── Find a Tender: centrālā platforma (arī Velsa) ──
    // Plūsma ir JAUNĀKIE PIRMIE, tāpēc lapu limits nogriež logu no vecās puses,
    // nevis izlaiž atsevišķus ierakstus. Bez stage filtra konkursi ir tikai ~16%
    // no plūsmas (pārējais — rezultāti/līgumi), un 60 dienu logam vajadzētu ~400
    // lapu. Tāpēc ejam DIVAS mērķtiecīgas reizes: stages=tender aktīvajiem un
    // stages=award rezultātiem. Abi filtri pārbaudīti, ka serveris tos tiešām
    // ņem vērā (n=100 ar attiecīgo tagu), nevis klusi ignorē.
    //
    // Kolonas laika zīmogā NEDRĪKST kodēt (http_build_query taisa %3A, ko FTS
    // nepieņem) — tāpēc vaicājumu veido manuāli.
    $ftsPass = function (string $stage, int $maxPages) use ($collect, $from): void {
        $url = UK_FTS_URL . '?updatedFrom=' . $from->format('Y-m-d') . 'T00:00:00'
             . '&limit=100&stages=' . $stage;
        $page = 0;
        for (; $page < $maxPages; $page++) {
            if (ks_stop_requested() || $url === null) break;
            $body = ks_http_get($url, ['Accept: application/json'], 60);
            $d = $body !== null ? json_decode($body, true) : null;
            $rows = is_array($d) ? ($d['releases'] ?? null) : null;
            if (!is_array($rows)) {
                // FTS kursors dziļumā mēdz atteikt — tas nav pilnīga kļūme, jo
                // svaigākie ieraksti jau ievākti; brīdina tikai par 1. lapu.
                if ($page === 0) ks_log("  ⚠ Find a Tender ($stage) nav pieejams.");
                return;
            }
            if (!$rows) return;
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $collect(uk_ocds_notice($r, 'UKFTS'));
            }
            $next = $d['links']['next'] ?? null;
            $url = (is_string($next) && $next !== '' && $next !== $url) ? $next : null;
        }
        // Kursors vēl nav beidzies, bet lapu limits sasniegts → logs ir nogriezts.
        // Par to JĀZIŅO: citādi nepilnīgs vēsturiskais logs izskatās pēc pilna.
        if ($url !== null && $page >= $maxPages) {
            ks_log("  ⚠ Find a Tender ($stage): sasniegts $maxPages lapu limits — vecākie ieraksti nav ievākti.");
        }
    };
    $ftsPass('tender', ks_cap(4, UK_FTS_MAX_PAGES));
    $ftsPass('award', ks_cap(3, UK_FTS_AWARD_MAX_PAGES));

    // ── Contracts Finder: kursora lapošana pa OCDS meklēšanu ──
    $url = UK_CF_SEARCH_URL . '?' . http_build_query([
        'publishedFrom' => $from->format('Y-m-d'),
        'publishedTo'   => $today->modify('+1 day')->format('Y-m-d'),
        'limit'         => 100,
    ]);
    for ($page = 0; $page < ks_cap(6, UK_CF_MAX_PAGES); $page++) {
        if (ks_stop_requested() || $url === null) break;
        $body = ks_http_get($url, ['Accept: application/json'], 60);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['releases'] ?? null) : null;
        if (!is_array($rows)) {
            if ($page === 0) ks_log('  ⚠ Contracts Finder nav pieejams.');
            break;
        }
        if (!$rows) break;
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $collect(uk_ocds_notice($r, 'UKCF'));
        }
        $next = $d['links']['next'] ?? null;
        $url = (is_string($next) && $next !== '' && $next !== $url) ? $next : null;
    }

    // ── Public Contracts Scotland: pa mēnešiem × paziņojumu tipiem ──
    $months = [];
    for ($m = new DateTimeImmutable($from->format('Y-m-01'), $tz); $m <= $today; $m = $m->modify('+1 month')) {
        $months[] = $m->format('m-Y');
    }
    foreach ($months as $mm) {
        foreach (UK_PCS_NOTICE_TYPES as $nt) {
            if (ks_stop_requested()) break 2;
            $body = ks_http_get(sprintf(UK_PCS_URL_FMT, $mm, $nt), ['Accept: application/json'], 60);
            $d = $body !== null ? json_decode($body, true) : null;
            $rows = is_array($d) ? ($d['releases'] ?? null) : null;
            if (!is_array($rows) || !$rows) continue;
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $collect(uk_ocds_notice($r, 'UKPCS'));
            }
        }
    }

    // Viens ieraksts uz OCID (jaunākais release) — raksta pēc visu plūsmu savākšanas.
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    foreach ($acc as $n) {
        if (ks_stop_requested()) break;
        unset($n['_rdate']);
        $stmt->execute($n);
        $imported++;
    }

    // Trīs portāli pārklājas: FTS un Contracts Finder vienu un to pašu iepirkumu
    // publicē ar DAŽĀDIEM ocid prefiksiem (ocds-h6vhtk- pret ocds-b5fd17-), tāpēc
    // ocid kā atslēga te nepietiek — dublikātus šķiro nosaukums+pircējs.
    $del = 0;
    $ids = $pdo->query("SELECT id FROM notices n WHERE n.source IN ('UKCF','UKPCS')
             AND EXISTS (SELECT 1 FROM notices f WHERE f.source='UKFTS' AND f.category=n.category
                         AND lower(trim(f.title))=lower(trim(n.title))
                         AND lower(trim(f.buyer_name))=lower(trim(n.buyer_name)))")
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_chunk($ids, 400) as $chunk) {
        $q = $pdo->prepare('DELETE FROM notices WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $q->execute($chunk);
        $del += $q->rowCount();
    }
    if ($del > 0) ks_log("  ⧉ AK: $del dublējās starp portāliem — noņemti (paliek Find a Tender).");

    if ($imported > 0) ks_log("  ✓ Apvienotā Karaliste → $imported paziņojumi (FTS + Contracts Finder + PCS).");
    return $imported;
}

/**
 * Būvē dedup kopu: simap projectId, kas JAU ir TED (Šveices virs-sliekšņa).
 *
 * TED CH paziņojuma document_url ir simap.ch redirect saite ar base64-kodētu
 * context objektu {"projectId":"<uuid>",...}. No tā izvelkam projectId un
 * neimportējam nevienu tādu simap projektu — tas novērš dublēšanos ar TED bez
 * jebkādas minēšanas (deterministiska UUID sakritība).
 */
function ks_simap_ted_ids(PDO $pdo): array {
    $set = [];
    $rows = $pdo->query("SELECT document_url FROM notices
        WHERE buyer_country='CH' AND document_url LIKE '%redirect?context=%'")
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $u) {
        $q = parse_url((string)$u, PHP_URL_QUERY);
        if (!is_string($q)) continue;
        parse_str($q, $qs);
        $j = json_decode(base64_decode((string)($qs['context'] ?? '')) ?: '', true);
        if (is_array($j) && !empty($j['projectId'])) $set[(string)$j['projectId']] = true;
    }
    return $set;
}

/**
 * Šveice (simap.ch) — TIKAI mazie/nacionālie konkursi + rezultāti.
 *
 * Virs-sliekšņa iepirkumi dublējas uz TED; tos izlaižam pēc ks_simap_ted_ids()
 * kopas UN pēc detaļas 'ted' lauka (otrā drošība svaigiem, ko TED imports vēl
 * nav paspējis). Termiņu + CPV dod detaļas pieprasījums, tāpēc to sūtām TIKAI
 * jauniem aktīviem konkursiem (jau importētiem neatkārto) ar griestu, lai
 * nepārslogotu serveri. Rezultātiem detaļu nesūtām — pietiek saraksta laukiem.
 * Lapošana ir "rolling": pagination.lastItem → nākamās lapas &lastItem.
 * @return int importēto skaits
 */
function ks_sync_simap(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    // Aktīvajiem vajag zināt ne tikai "vai rinda ir", bet arī vai tai jau ir
    // termiņš: esošu rindu ar termiņu NEDRĪKST pārrakstīt ar saraksta datiem
    // (detaļu neprasa → upsert noliktu deadline/CPV/aprakstu atpakaļ uz NULL).
    $exists = $pdo->prepare('SELECT category, deadline_date FROM notices WHERE id = ? LIMIT 1');
    // Rezultātu gājienā esošai rindai maina TIKAI kategoriju (tender → award) —
    // pilnais upsert izdzēstu detaļā iegūto aprakstu/CPV.
    $toAward = $pdo->prepare("UPDATE notices SET category = 'rezultati', deadline_date = NULL, deadline_time = NULL WHERE id = ?");
    $tedIds = ks_simap_ted_ids($pdo);
    ks_log('  ℹ ' . count($tedIds) . ' simap projekti jau ir TED — tie tiks izlaisti.');

    $deep = konkursi_deep();
    $tz = new DateTimeZone('Europe/Riga');
    $today = new DateTimeImmutable(konkursi_today(), $tz);
    // Inkrementāli: pēc pirmās palaišanas logs = ūdenszīme − pārklājums (šaurs), nevis
    // fiksētas 60 dienas katrreiz. Dziļais režīms patur pilno vēstures logu.
    $from = $deep
        ? $today->modify('-' . konkursi_deep_days() . ' days')->format('Y-m-d')
        : ks_window_from($pdo, 'SIMAP');
    $detailBudget = $deep ? SIMAP_DETAIL_CAP_DEEP : SIMAP_DETAIL_CAP_NORMAL;

    $imported = 0; $skippedTed = 0;

    // Viens gājiens pa rolling-lapošanu; $withDetail=true → aktīviem prasa termiņu.
    $pass = function (string $pubTypes, string $category, int $maxPages, bool $withDetail)
        use ($stmt, $exists, $toAward, $tedIds, $from, &$imported, &$skippedTed, &$detailBudget): void {
        $base = SIMAP_SEARCH_URL
              . '?newestPubTypes=' . $pubTypes
              . '&orderAddressCountryOnlySwitzerland=true'
              . '&newestPublicationFrom=' . $from;
        $last = null;
        for ($page = 0; $page < $maxPages; $page++) {
            if (ks_stop_requested()) break;
            $url = $base . ($last !== null ? '&lastItem=' . rawurlencode($last) : '');
            $body = ks_http_get($url, ['Accept: application/json'], 60);
            $d = $body !== null ? json_decode($body, true) : null;
            $projects = is_array($d) ? ($d['projects'] ?? null) : null;
            if (!is_array($projects)) {
                if ($page === 0) ks_log("  ⚠ simap.ch ($category) nav pieejams.");
                break;
            }
            if (!$projects) break;
            foreach ($projects as $p) {
                if (!is_array($p)) continue;
                $pid = (string)($p['id'] ?? '');
                if ($pid === '' || isset($tedIds[$pid])) { if ($pid !== '') $skippedTed++; continue; }

                $exists->execute(['SIMAP-' . $pid]);
                $row = $exists->fetch();

                if (!$withDetail) {
                    // Rezultātu gājiens. Esošai rindai (parasti agrāk importēts
                    // konkurss) pārliek tikai kategoriju — detaļā iegūtais
                    // apraksts/CPV paliek. Jaunu ievieto no saraksta laukiem.
                    if ($row !== false) {
                        if ($row['category'] !== 'rezultati') { $toAward->execute(['SIMAP-' . $pid]); $imported++; }
                        continue;
                    }
                    $n = simap_notice($p, null, $category);
                    if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
                    $stmt->execute($n);
                    $imported++;
                    continue;
                }

                // Aktīvo gājiens. Esoša rinda AR termiņu ir pilnīga — atkārtots
                // upsert bez detaļas to tikai sabojātu (deadline/CPV → NULL).
                if ($row !== false && $row['deadline_date'] !== null) continue;

                $detail = null;
                if ($detailBudget > 0) {
                    // Detaļu prasa jauniem UN esošiem bez termiņa (agrāk izsmelts
                    // budžets) — tā caurums pats aizpildās nākamajās palaišanās.
                    $pub = (string)($p['publicationId'] ?? '');
                    if ($pub !== '') {
                        $db = ks_http_get(sprintf(SIMAP_DETAIL_FMT, rawurlencode($pid), rawurlencode($pub)),
                                          ['Accept: application/json'], 45);
                        $detail = $db !== null ? json_decode($db, true) : null;
                        $detailBudget--;
                        // Otrā dedup drošība: ja detaļā ir TED atsauce, izlaiž.
                        if (is_array($detail) && !empty($detail['ted'])) { $skippedTed++; continue; }
                    }
                } elseif ($row !== false) {
                    continue; // budžets tukšs — esošo bez termiņa neaiztiek (nepārraksta ar NULL)
                }

                $n = simap_notice($p, $detail, $category);
                if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
                $stmt->execute($n);
                $imported++;
            }
            $last = $d['pagination']['lastItem'] ?? null;
            if (!is_string($last) || $last === '') break;
        }
    };

    $pass('tender', 'iepirkumi', SIMAP_MAX_PAGES, true);
    $pass('award_tender,award_study_contract,award_competition', 'rezultati', SIMAP_AWARD_MAX_PAGES, false);

    // Rezultātu gājienam detaļu (un tās 'ted' lauka) nav, un ks_simap_ted_ids
    // sedz tikai TED CH rindas ar simap redirect saiti — virs-sliekšņa piešķīrumi
    // bez tās ienāca dubulti (2026-07-21 audits: 263). Tas pats nosaukuma+pircēja
    // slauķis kā AT/RO/BG/PT; TED rinda paliek.
    $del = ks_dedupe_vs_ted($pdo, 'SIMAP', 'CH');
    if ($del > 0) ks_log("  ⧉ Šveice: $del dublējās ar TED — noņemti (paliek TED rinda).");

    if ($skippedTed > 0) ks_log("  ⧉ Šveice: $skippedTed virs-sliekšņa konkursi izlaisti (jau TED).");
    if ($imported > 0) ks_log("  ✓ Šveice → $imported paziņojumi (simap.ch mazie konkursi + rezultāti).");
    return $imported;
}

/**
 * Ukraina (Prozorro) — AKTĪVIE konkurētspējīgie konkursi (arī virs-sliekšņa) + mazie rezultāti.
 *
 * Prozorro publicē VISUS iepirkumus, un Ukraina NAV ES/TED (TED satur tikai ~12 UA
 * ierakstus), tāpēc dedup pret TED nav vajadzīgs — aktīvos ņemam no VISĀM konkurētspējīgām
 * procedūrām (PROZORRO_ACTIVE_TYPES, arī aboveThreshold). Rezultātus ņemam tikai no
 * mazajām/tiešajām (PROZORRO_SMALL_TYPES), lai arhīvs neuzpūstos.
 *
 * Plūsma /tenders atgriež TIKAI id+dateModified+status+tipu (title opt_fields
 * klusi ignorē), tāpēc: (1) skenējam plūsmu descending, atlasām mazos tipus,
 * (2) pilnu objektu (nosaukums/vērtība/CPV) prasām TIKAI kandidātiem un TIKAI
 * jauniem (jau importētiem neatkārto). Detaļu budžets ierobežo slodzi.
 * @return int importēto skaits
 */
function ks_sync_prozorro(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT 1 FROM notices WHERE id = ? LIMIT 1');

    $deep = konkursi_deep();
    $maxPages = $deep ? PROZORRO_MAX_PAGES_DEEP : PROZORRO_MAX_PAGES_NORMAL;
    $detailBudget = $deep ? PROZORRO_DETAIL_CAP_DEEP : PROZORRO_DETAIL_CAP_NORMAL;
    $tz = new DateTimeZone('Europe/Riga');
    $fromDate = (new DateTimeImmutable(konkursi_today(), $tz))
        ->modify('-' . ($deep ? konkursi_deep_days() : KONKURSI_ACTIVE_WINDOW_DAYS) . ' days')->format('Y-m-d');

    // Saraksts: descending=1 OBLIGĀTS (noklusējums ir augošs no 2015!). opt_fields
    // ticami atgriež status+tipu+tenderPeriod (title/value/CPV — nē, tos ņem detaļā).
    $feed = PROZORRO_FEED_URL . '?descending=1&limit=100'
          . '&opt_fields=status,procurementMethodType,tenderPeriod,dateModified';
    $offset = null;
    $imported = 0; $scanned = 0;

    for ($page = 0; $page < $maxPages; $page++) {
        if (ks_stop_requested() || $detailBudget <= 0) break;
        $url = $feed . ($offset !== null ? '&offset=' . rawurlencode($offset) : '');
        $body = ks_http_get($url, ['Accept: application/json'], 60);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['data'] ?? null) : null;
        if (!is_array($rows)) { if ($page === 0) ks_log('  ⚠ Prozorro nav pieejams.'); break; }
        if (!$rows) break;

        // Plūsma descending pēc dateModified — kad tā aiziet aiz loga un vairs nav
        // neviena kandidāta, ejam vēl dažas lapas rezervei, tad stop.
        $pageHadRecent = false;
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $scanned++;
            [$modDate] = ocds_dt($r['dateModified'] ?? null);
            if ($modDate !== null && $modDate >= $fromDate) $pageHadRecent = true;

            $mt = (string)($r['procurementMethodType'] ?? '');
            $st = (string)($r['status'] ?? '');
            $isActive = ($st === 'active.tendering' || $st === 'active.enquiries');

            // AKTĪVIE = visas konkurētspējīgās procedūras (arī virs-sliekšņa; UA nav TED).
            // REZULTĀTI = tikai mazie/tiešie (kā agrāk) — virs-sliekšņa rezultātus (kas
            // varētu būt TED) neimportējam, lai nedublētos un neuzpūstu arhīvu.
            if ($isActive && in_array($mt, PROZORRO_ACTIVE_TYPES, true)) {
                $category = 'iepirkumi';
            } elseif (in_array($mt, PROZORRO_SMALL_TYPES, true)
                   && ($st === 'complete' || $mt === 'reporting' || $st === 'active.awarded' || $st === 'active.qualification')) {
                $category = 'rezultati';
            } else {
                continue; // ne-aktīvie virs-sliekšņa + cancelled/unsuccessful — izlaižam
            }

            $tid = (string)($r['id'] ?? '');
            if ($tid === '') continue;
            // Jau DB? Tad detaļu neatkārtojam (steady-state lēts).
            $exists->execute(['PROZORRO-' . $tid]);
            if ($exists->fetchColumn()) continue;
            if ($detailBudget <= 0) continue;

            $tb = ks_http_get(sprintf(PROZORRO_TENDER_FMT, rawurlencode($tid)), ['Accept: application/json'], 45);
            $detailBudget--;
            $tobj = $tb !== null ? (json_decode($tb, true)['data'] ?? null) : null;
            if (!is_array($tobj)) continue;

            $n = prozorro_notice($tobj, $category);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
            $stmt->execute($n);
            $imported++;
        }

        $offset = $d['next_page']['offset'] ?? null;
        if (!is_string($offset) || $offset === '') break;
        // Kad esam pilnībā aiz loga (visa lapa vecāka) un budžets vēl ir — stop:
        // vecāki ieraksti mūs neinteresē (tos tāpat nogrieztu ks_within_retention).
        if (!$pageHadRecent && $page > 2) break;
    }

    if ($imported > 0) ks_log("  ✓ Ukraina → $imported paziņojumi (Prozorro aktīvie konkursi + mazie rezultāti; skenēti $scanned).");
    return $imported;
}

/**
 * Lihtenšteina (vergaben.llv.li) — TIKAI USB (zem-sliekšņa) mazie konkursi.
 *
 * Aiz Angular SPA ir tīrs JSON: POST Find/ ar tukšu {} atgriež visu publisko
 * sarakstu (valsts sīka, ~29 paziņojumi, lapošana nav vajadzīga). Importējam
 * tikai USB — tie uz TED NEnonāk, tāpēc dedup pret TED nav vajadzīgs.
 * @return int importēto skaits
 */
function ks_sync_livergabe(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $body = ks_http_post_json(LIVERG_FIND_URL, [], ['Accept: application/json']);
    $d = $body !== null ? json_decode($body, true) : null;
    $rows = is_array($d) ? ($d['result'] ?? null) : null;
    if (!is_array($rows)) { ks_log('  ⚠ vergaben.llv.li nav pieejams.'); return 0; }

    $imported = 0; $usb = 0;
    foreach ($rows as $x) {
        if (!is_array($x)) continue;
        if ((int)($x['tresholdTypeId'] ?? 0) === 1) $usb++;
        $n = liverg_notice($x);
        if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
        $stmt->execute($n);
        $imported++;
    }
    if ($imported > 0) ks_log("  ✓ Lihtenšteina → $imported USB paziņojumi (no " . count($rows) . " publiskajiem; USB kopā $usb).");
    return $imported;
}

/**
 * MTender API GET ar atkārtojumiem. public.mtender.gov.md brīžiem atgriež HTTP 200
 * ar TUKŠU ķermeni vai {"name":"Error", …} (avota EHOSTUNREACH) — ks_http_get to
 * neatkārto, tāpēc pārbaudām paši un mēģinām līdz $tries reizēm. Atgriež dekodētu
 * masīvu vai null.
 */
function ks_mtender_get(string $url, ?int $tries = null): ?array {
    $tries = $tries ?? MTENDER_HTTP_TRIES;
    for ($i = 0; $i < $tries; $i++) {
        if (ks_stop_requested() || ks_stage_over_budget()) break;
        // Ja ķēde jau atvērta (hosts neatbild), ks_http_get atgriež null uzreiz —
        // nav jēgas dauzīt atlikušos mēģinājumus.
        if (ks_http_host_blocked(ks_http_host($url))) break;
        $body = ks_http_get($url, ['Accept: application/json'], MTENDER_HTTP_TIMEOUT_S);
        if ($body !== null && $body !== '') {
            $d = json_decode($body, true);
            if (is_array($d) && ($d['name'] ?? null) !== 'Error') return $d;
        }
        if ($i < $tries - 1) usleep(600000); // 0,6 s starp mēģinājumiem
    }
    return null;
}

/**
 * Moldova (MTender) — AKTĪVIE konkurētspējīgie iepirkumi caur portāla meklēšanas API.
 *
 * Agrākā /tenders/?offset= plūsma bija 89% directAward (tiešie līgumi BEZ termiņa) un
 * termiņu lasīja tikai no records[0] (kur tā nav) → rādīja 0 aktīvo. Tagad:
 * (1) mtender.gov.md/search/tenders ar servera-puses filtriem — proceduresTypes (bez
 *     directAward), periodOffer=[tagad,nākotne] (atvērts iesniegšanas termiņš),
 *     proceduresOwnerships=government — atgriež bagātu sarakstu (title/amount/buyer/ocid);
 * (2) detaļu (compiledRelease) prasa TIKAI jaunajiem → izvelk termiņu no apakšierakstiem.
 * Vērtību slieksni nelietojam (Moldova nav TED → nav dublēšanās).
 * @return int importēto skaits
 */
function ks_sync_mtender(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT deadline_date FROM notices WHERE id = ? LIMIT 1');
    $detailBudget = konkursi_deep() ? MTENDER_DETAIL_CAP_DEEP : MTENDER_DETAIL_CAP_NORMAL;

    // periodOffer=[tagad, +2 gadi] → tikai konkursi ar vēl atvērtu iesniegšanas termiņu.
    $now = gmdate('Y-m-d\TH:i:s.000\Z');
    $far = (gmdate('Y') + 2) . '-12-31T21:59:59.999Z';
    $periodOffer = rawurlencode('["' . $now . '","' . $far . '"]');
    $owners = rawurlencode('["government"]');
    $types  = rawurlencode(MTENDER_COMPETITIVE_TYPES);

    // (1) Saraksta lapas (jaunākie pirmie); vācam meklēšanas vienumus pa ocid.
    $items = []; // ocid => search item
    for ($page = 1; $page <= MTENDER_LIST_MAX_PAGES; $page++) {
        if (ks_stop_requested()) break;
        $url = MTENDER_SEARCH_URL . '?periodOffer=' . $periodOffer
             . '&page=' . $page . '&pageSize=' . MTENDER_SEARCH_PAGE_SIZE
             . '&proceduresOwnerships=' . $owners . '&proceduresTypes=' . $types;
        $d = ks_mtender_get($url);
        $data = is_array($d) ? ($d['data'] ?? null) : null;
        if (!is_array($data) || !$data) { if ($page === 1) ks_log('  ⚠ MTender meklēšana nav pieejama.'); break; }
        foreach ($data as $row) {
            if (is_array($row) && !empty($row['id'])) $items[(string)$row['id']] = $row;
        }
        $meta = is_array($d['_meta'] ?? null) ? $d['_meta'] : [];
        if ($page >= (int)($meta['pageCount'] ?? 1)) break;
    }
    // NEATGRIEŽAMIES uzreiz, ja meklēšana neatbildēja: rindā var būt iepriekšējās
    // palaišanas nepabeigtie ocid, un tieši Moldovas gadījumā meklēšana un detaļas
    // ir DIVI DAŽĀDI hosti (mtender.gov.md / public.mtender.gov.md) — viens var būt
    // nost, otrs strādāt.
    $active = count($items);

    // (2) Detaļu (ar termiņu) prasa jaunajiem UN tiem jau importētajiem, kuriem termiņa
    // vēl nav (importēti agrīnā stadijā / MTender datu novēlošanās dēļ, kad detaļā vēl
    // nebija tenderPeriod) — tos pārprasa un atjaunina. Ar termiņu jau esošos izlaiž.
    $imported = 0; $updated = 0; $scanned = 0; $failed = 0;
    $done = [];   // ocid, kuriem detaļas šoreiz izdevās (vai kuri vairs nav vajadzīgi)
    $order = ks_mtender_queue($items); // agrāk nepabeigtie PIRMS jaunajiem
    $total = count($order);

    foreach ($order as $i => $ocid) {
        if (ks_stop_requested() || ks_stage_over_budget() || $detailBudget <= 0) break;
        $item = $items[$ocid] ?? []; // atsāktajiem meklēšanas ieraksta nav — mtender_notice tiek galā
        $exists->execute(['MTENDER-' . $ocid]);
        $row = $exists->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && ($row['deadline_date'] ?? null) !== null && $row['deadline_date'] !== '') {
            $done[] = $ocid; // termiņš jau ir — no rindas ārā
            continue;
        }
        $scanned++;
        $detailBudget--;
        $d = ks_mtender_get(sprintf(MTENDER_DETAIL_FMT, rawurlencode($ocid)));
        $records = is_array($d['records'] ?? null) ? $d['records'] : null;
        if (!$records) { $failed++; continue; } // paliek rindā nākamajai palaišanai
        $done[] = $ocid;
        $n = mtender_notice($records, $ocid, $item);
        if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
        $res = $stmt->execute($n);
        if ($res === 'new') $imported++; elseif ($res === 'version') $updated++;

        // Progresa atzīme: bez tās garš cikls klusē, un klusums izskatās
        // tieši tāpat kā iekāršanās.
        if ($scanned > 0 && $scanned % 25 === 0) {
            ks_log("  · Moldova: apstrādāti $scanned no $total (jauni $imported, atjaunināti $updated).");
        }
    }

    $left = ks_mtender_queue_save($order, $done);
    ks_log("  ✓ Moldova → $imported jauni + $updated atjaunināti ($active atvērti sarakstā, detaļas $scanned"
        . ($failed > 0 ? ", neatbildēja $failed" : '')
        . ($left > 0 ? "; rindā paliek $left — turpinās nākamajā palaišanā" : '') . ').');
    return $imported;
}

/**
 * Moldovas detaļu rinda. MTender mēdz strādāt, pazust uz ~stundu un atgriezties,
 * tāpēc nepabeigtos ocid glabājam meta un nākamajā palaišanā sākam TIEŠI no tiem,
 * nevis no gala — citādi pie regulāriem pārtraukumiem saraksta aste nekad netiktu
 * apstrādāta. Vecie (nepabeigtie) iet pirmie, tad jaunie no meklēšanas.
 */
function ks_mtender_queue(array $items): array {
    $pending = [];
    try {
        $raw = konkursi_meta_get(konkursi_db(), 'mtender_pending');
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d)) $pending = array_values(array_filter($d, 'is_string'));
        }
    } catch (Throwable $e) { /* bez rindas — vienkārši sākam no jaunajiem */ }

    $fresh = array_keys($items);
    $order = array_values(array_unique(array_merge($pending, $fresh)));
    if (count($order) > MTENDER_PENDING_MAX) $order = array_slice($order, 0, MTENDER_PENDING_MAX);
    return $order;
}

/** Saglabā neapstrādātos ocid nākamajai palaišanai. @return int cik palika rindā */
function ks_mtender_queue_save(array $order, array $done): int {
    $left = array_values(array_diff($order, $done));
    if (count($left) > MTENDER_PENDING_MAX) $left = array_slice($left, 0, MTENDER_PENDING_MAX);
    try {
        konkursi_meta_set(konkursi_db(), 'mtender_pending', $left ? json_encode($left) : '');
    } catch (Throwable $e) { /* nav kritiski — nākamreiz sāksim no meklēšanas saraksta */ }
    return count($left);
}

/**
 * Bosnija un Hercegovina (open.ejn.gov.ba) — TIKAI NPS mazie iepirkumi.
 *
 * Oficiāls OData v4 bez atslēgas. Servera-puses $filter pēc Announced datuma +
 * $orderby desc + $top/$skip lapošana → ņemam tikai loga ierakstus, bez detaļu
 * pieprasījumiem. NPS = zem-sliekšņa (nekad TED), tāpēc dedup nav vajadzīgs.
 * @return int importēto skaits
 */
function ks_sync_bosnia(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $tz = new DateTimeZone('Europe/Riga');
    $days = konkursi_deep() ? konkursi_deep_days() : KONKURSI_ACTIVE_WINDOW_DAYS;
    $from = (new DateTimeImmutable(konkursi_today(), $tz))->modify("-$days days")->format('Y-m-d\T00:00:00\Z');

    $imported = 0;
    for ($page = 0; $page < EJN_MAX_PAGES; $page++) {
        if (ks_stop_requested()) break;
        $skip = $page * EJN_PAGE_SIZE;
        $url = EJN_BASE_URL . '/NpsProcurementNotices?'
             . '$filter=' . rawurlencode("Announced gt $from")
             . '&$orderby=' . rawurlencode('Announced desc')
             . '&$top=' . EJN_PAGE_SIZE . '&$skip=' . $skip;
        $body = ks_http_get($url, ['Accept: application/json'], 45);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['value'] ?? null) : null;
        if (!is_array($rows)) { if ($page === 0) ks_log('  ⚠ open.ejn.gov.ba nav pieejams.'); break; }
        if (!$rows) break;
        foreach ($rows as $x) {
            if (!is_array($x)) continue;
            $n = bosnia_nps_notice($x);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
            $stmt->execute($n);
            $imported++;
        }
        if (count($rows) < EJN_PAGE_SIZE) break;
    }

    // (2) VIRS-SLIEKŠŅA regulārie konkursi (ProcurementNotices) — AKTĪVIE (termiņš
    // nākotnē). Termiņš (ApplicationDeadlineDateTime) ir tieši sarakstā → bez detaļām.
    // Bosnija nav TED, tāpēc dedup nav vajadzīgs (ID prefikss EJN-PN- ≠ NPS EJN-).
    $nowIso = gmdate('Y-m-d\TH:i:s\Z');
    $open = 0;
    for ($page = 0; $page < EJN_OPEN_MAX_PAGES; $page++) {
        if (ks_stop_requested()) break;
        $skip = $page * EJN_PAGE_SIZE;
        $url = EJN_BASE_URL . '/' . EJN_OPEN_ENTITY . '?'
             . '$filter=' . rawurlencode("ApplicationDeadlineDateTime gt $nowIso")
             . '&$orderby=' . rawurlencode('Announced desc')
             . '&$top=' . EJN_PAGE_SIZE . '&$skip=' . $skip;
        $body = ks_http_get($url, ['Accept: application/json'], 45);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['value'] ?? null) : null;
        if (!is_array($rows) || !$rows) break;
        foreach ($rows as $x) {
            if (!is_array($x)) continue;
            $n = bosnia_open_notice($x);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
            $stmt->execute($n);
            $open++;
        }
        if (count($rows) < EJN_PAGE_SIZE) break;
    }

    if ($imported > 0 || $open > 0) {
        ks_log("  ✓ Bosnija un Hercegovina → $imported NPS + $open virs-sliekšņa konkursi (open.ejn.gov.ba).");
    }
    return $imported + $open;
}

/**
 * Būvē ESJN GetGridData form-body (DataTables v10 + JSON Discriminator).
 * $status 1=aktīvie, 2=pabeigtie; $procType = '' (VISAS procedūras) vai konkrēts kods.
 */
function ks_esjn_body(int|string $procType, int $status, int $start, int $length): array {
    $cols = ['ProcessNumber', 'ContractingInstitutionName', 'Subject', 'GoodsWorksServices',
             'EntityProcedureType', 'AnnouncementDate', 'FinalDay', 'Documents'];
    $p = [
        'draw' => '1', 'order[0][column]' => '5', 'order[0][dir]' => 'desc',
        'start' => (string)$start, 'length' => (string)$length,
        'search[value]' => '', 'search[regex]' => 'false',
    ];
    foreach ($cols as $i => $c) {
        $p["columns[$i][data]"] = $c;
        $p["columns[$i][name]"] = '';
        $p["columns[$i][searchable]"] = 'true';
        $p["columns[$i][orderable]"] = 'true';
        $p["columns[$i][search][value]"] = '';
        $p["columns[$i][search][regex]"] = 'false';
    }
    $p['Discriminator'] = json_encode([
        'ContractingInstitution' => '', 'EauctionOnly' => false, 'TypeOfPublicContract' => '',
        'Status' => $status, 'OngoingComplitedStatus' => '', 'TypeOfProcedure' => $procType,
        'ProcessNumber' => '', 'IsSmallPublicProcurement' => false, 'EprocurementOnly' => false,
        'PrivatePartnershipOnly' => false, 'ContractingInstitutionName' => null, 'Subject' => '',
        'PeriodFrom' => '', 'PeriodTo' => '', 'SmallOnly' => false, 'BigOnly' => false,
        'LotSubject' => '', 'OfferType' => '', 'IsFrameworkAgreement' => '', 'HasTechnicalDialog' => '',
    ]);
    return $p;
}

/**
 * Ziemeļmaķedonija (e-nabavki.gov.mk) — VISAS procedūras (mazās + atklātās).
 *
 * Publiskais DataTables ASMX endpoints, bez atslēgas. Servera-puses filtrs pēc statusa
 * + kārtošana desc; TypeOfProcedure='' → visas procedūras (Low/SimplifiedOpen + Open u.c.).
 * Ziemeļmaķedonija NAV ES/TED, tāpēc dedup nav vajadzīgs (agrāk tikai 13/14 → izmeta Open).
 * Termiņš (FinalDay) un tips (EntityProcedureType) ir tieši režģī.
 * @return int importēto skaits
 */
function ks_sync_macedonia(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $hdr = ['Accept: application/json']; // ks_http_post_form pati pievieno Content-Type
    $imported = 0;

    // VISAS procedūras (TypeOfProcedure=''): mazās (Low/SimplifiedOpen) UN atklātās
    // (Open) u.c. Ziemeļmaķedonija NAV ES/TED, tāpēc agrākais tikai-13/14 filtrs izmeta
    // ~12% aktīvo (Open procedūras). Termiņš (FinalDay) un tips (EntityProcedureType) ir
    // tieši režģī, tāpēc detaļu neprasa.
    // [status, kategorija, max lapas]: aktīvie visi; rezultāti jaunākie (RESULTS_CAP)
    $passes = [
        [1, 'iepirkumi', ESJN_ACTIVE_MAX_PAGES],
        [2, 'rezultati', ESJN_RESULT_MAX_PAGES],
    ];
    foreach ($passes as [$status, $category, $maxPages]) {
        for ($page = 0; $page < $maxPages; $page++) {
            if (ks_stop_requested()) break;
            $body = ks_http_post_form(ESJN_URL, ks_esjn_body('', $status, $page * ESJN_PAGE_SIZE, ESJN_PAGE_SIZE), $hdr);
            $d = $body !== null ? json_decode($body, true) : null;
            $rows = is_array($d) ? ($d['data'] ?? null) : null;
            if (!is_array($rows)) { if ($page === 0 && $status === 1) ks_log('  ⚠ e-nabavki.gov.mk nav pieejams.'); break; }
            if (!$rows) break;
            $kept = 0;
            foreach ($rows as $x) {
                if (!is_array($x)) continue;
                $n = macedonia_notice($x, $category);
                if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
                $stmt->execute($n);
                $imported++; $kept++;
            }
            // Rezultāti ir dilstoši pēc datuma — kad krītam ārā no loga, stop.
            if ($category === 'rezultati' && $kept === 0) break;
            if (count($rows) < ESJN_PAGE_SIZE) break;
        }
    }

    if ($imported > 0) ks_log("  ✓ Ziemeļmaķedonija → $imported paziņojumi (e-nabavki.gov.mk visas procedūras).");
    return $imported;
}

/**
 * Serbija (jnportal.ujn.gov.rs) — nacionālie konkursi + rezultāti.
 *
 * DevExpress searchgrid endpoints prasa `UserToken` galveni (no lapas #uiUserToken
 * lauka) + ASP.NET sesijas cepumu. reCAPTCHA nav → parasta sesijas-token plūsma.
 * Kārto PublishDate desc, filtrē pēc datuma loga, lapo skip/take. Dokumentu tipus
 * (Ф-kodus) kategorijās sadala serbia_notice/serbia_category. Termiņa/vērtības/CPV
 * režģī nav. 11 TED RS = niecīga pārklāšanās (nefiltrējam pēc vērtības, jo tās nav).
 * @return int importēto skaits
 */
function ks_sync_serbia(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $jar = konkursi_tmp_dir() . '/jnrs_cookies.txt';
    @unlink($jar);

    // 1. solis: lapa (sesijas cepums + #uiUserToken).
    $html = ks_http_get(JNRS_PAGE_URL, ['Accept: text/html'], 40, $jar);
    if ($html === null || !preg_match('/uiUserToken[^>]*value="([^"]*)"/', $html, $m)) {
        ks_log('  ⚠ jnportal.ujn.gov.rs: sesijas token nav pieejams.');
        return 0;
    }
    $token = $m[1];

    $deep = konkursi_deep();
    $maxPages = $deep ? JNRS_MAX_PAGES_DEEP : JNRS_MAX_PAGES_NORMAL;
    $days = $deep ? konkursi_deep_days() : KONKURSI_ACTIVE_WINDOW_DAYS;
    $from = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify("-$days days")->format('Y-m-d\T00:00:00.000');
    $sort = rawurlencode(json_encode([['selector' => 'PublishDate', 'desc' => true]]));
    $filter = rawurlencode(json_encode(['PublishDate', '>=', $from], JSON_UNESCAPED_UNICODE));
    $hdr = ['Accept: application/json', 'UserToken: ' . $token];

    $imported = 0;
    for ($page = 0; $page < $maxPages; $page++) {
        if (ks_stop_requested()) break;
        $url = JNRS_API_URL . '?sort=' . $sort . '&filter=' . $filter
             . '&skip=' . ($page * JNRS_PAGE_SIZE) . '&take=' . JNRS_PAGE_SIZE;
        $body = ks_http_get($url, $hdr, 45, $jar);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['data'] ?? null) : null;
        if (!is_array($rows)) { if ($page === 0) ks_log('  ⚠ jnportal.ujn.gov.rs searchgrid nav pieejams.'); break; }
        if (!$rows) break;
        foreach ($rows as $x) {
            if (!is_array($x)) continue;
            $n = serbia_notice($x);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
            $stmt->execute($n);
            $imported++;
        }
        if (count($rows) < JNRS_PAGE_SIZE) break;
    }

    @unlink($jar);
    if ($imported > 0) ks_log("  ✓ Serbija → $imported paziņojumi (jnportal.ujn.gov.rs).");
    return $imported;
}

/**
 * Melnkalne (cejn.gov.me) — TIKAI "Small procurement" (Jednostavna nabavka).
 *
 * Publiskais CeJN JSON API POST /api/cadocuments/GetTenders, bez atslēgas.
 * Noklusējuma kārtība = jaunākie pirmie; lapo skip/top. Filtrē "Small procurement"
 * (zem ES sliekšņa → nekad TED), kategorizē pēc lifecycle. Termiņa/vērtības/CPV nav.
 * @return int importēto skaits
 */
function ks_sync_montenegro(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $exists = $pdo->prepare('SELECT deadline_date FROM notices WHERE id = ? LIMIT 1');
    $maxPages = konkursi_deep() ? CEJN_MAX_PAGES_DEEP : CEJN_MAX_PAGES_NORMAL;
    $roundsBudget = konkursi_deep() ? CEJN_ROUNDS_CAP_DEEP : CEJN_ROUNDS_CAP_NORMAL;
    $imported = 0; $withDl = 0;

    for ($page = 0; $page < $maxPages; $page++) {
        if (ks_stop_requested()) break;
        $body = ks_http_post_json(CEJN_URL, [
            'pageSize' => CEJN_PAGE_SIZE, 'tenderStatuses' => explode(',', CEJN_STATUSES),
            'skip' => $page * CEJN_PAGE_SIZE, 'top' => CEJN_PAGE_SIZE,
            'procedureType' => 0, 'subjectType' => 0, 'justCanApply' => false, 'sort' => null,
            'myTenders' => false, 'useAdditionalCaSearch' => false, 'caType' => 0, 'caStateId' => 0,
            'pageIndex' => $page, 'shouldCheckPageIsExist' => false, 'statuses' => CEJN_STATUSES,
        ]);
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['value'] ?? null) : null;
        if (!is_array($rows)) { if ($page === 0) ks_log('  ⚠ cejn.gov.me nav pieejams.'); break; }
        if (!$rows) break;
        foreach ($rows as $x) {
            if (!is_array($x)) continue;
            $id = (int)($x['id'] ?? 0);
            $caption = (string)($x['typeOfProcedureCaption'] ?? '');
            // Konkurētspējīgajām (ne-Small) procedūrām ielādē ĪSTO iesniegšanas termiņu
            // (getTenderRounds → endOfSubmissions) — tikai jaunajām (kam DB nav termiņa)
            // un budžeta robežās. Small procurement termiņa nav (90-d heiristika).
            $rounds = null;
            if ($id > 0 && $caption !== CEJN_SMALL_CAPTION) {
                $exists->execute(['CEJN-' . $id]);
                $row = $exists->fetch(PDO::FETCH_ASSOC);
                // Jau ir ĪSTS termiņš DB → NEAIZTIEKAM (citādi montenegro_notice ar
                // rounds=null pārrakstītu to ar null). Tikai bez-termiņa ielādē rounds.
                if ($row !== false && ($row['deadline_date'] ?? null) !== null && $row['deadline_date'] !== '') continue;
                if ($roundsBudget > 0) {
                    $rb = ks_http_get(sprintf(CEJN_ROUNDS_FMT, $id), ['Accept: application/json'], 30);
                    $roundsBudget--;
                    $rounds = $rb !== null ? json_decode($rb, true) : null;
                }
            }
            $n = montenegro_notice($x, $rounds);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) continue;
            if ($n['deadline_date'] !== null) $withDl++;
            $stmt->execute($n);
            $imported++;
        }
        if (count($rows) < CEJN_PAGE_SIZE) break;
    }

    if ($imported > 0) ks_log("  ✓ Melnkalne → $imported paziņojumi (cejn.gov.me visas procedūras; $withDl ar īstu termiņu).");
    return $imported;
}

/**
 * Albānija (app.gov.al) — APP mazo iepirkumu portāls (Umbraco form-POST HTML).
 *
 * Nav JSON API: GET lapa → __RequestVerificationToken → POST ar DateFrom/DateTo
 * (dd-mm-yyyy!) → HTML rezultāti → albania_parse. Publisks, parasts pieprasījums.
 * Tikai mazie iepirkumi (zem ES sliekšņa → nekad TED). Portāls rāda ~jaunākos 24.
 * @return int importēto skaits
 */
function ks_sync_albania(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $jar = konkursi_tmp_dir() . '/appal_cookies.txt';
    @unlink($jar);

    // 1. solis: lapa → __RequestVerificationToken.
    $html = ks_http_get(APPAL_URL, ['Accept: text/html'], 40, $jar);
    if ($html === null || !preg_match('/__RequestVerificationToken[^>]*value="([^"]*)"/', $html, $m)) {
        ks_log('  ⚠ app.gov.al: meklēšanas token nav pieejams.');
        return 0;
    }
    $tz = new DateTimeZone('Europe/Riga');
    $days = konkursi_deep() ? konkursi_deep_days() : KONKURSI_ACTIVE_WINDOW_DAYS;
    $from = (new DateTimeImmutable(konkursi_today(), $tz))->modify("-$days days")->format('d-m-Y');
    $to = (new DateTimeImmutable(konkursi_today(), $tz))->format('d-m-Y');

    // 2. solis: POST meklēšana (dd-mm-yyyy datumi obligāti).
    $body = ks_http_post_form(APPAL_URL, [
        '__RequestVerificationToken' => $m[1],
        'criteria' => '', 'TenderSubject' => '', 'ContractAuthorityName' => '',
        'ContractAuthorityID' => '', 'ReferenceNumber' => '', 'ContractTypeID' => '0',
        'DateFrom' => $from, 'DateTo' => $to, 'cpvCode' => '', 'search_submit' => 'Kërko',
    ], ['Referer: ' . APPAL_URL], $jar);
    @unlink($jar);
    if ($body === null) { ks_log('  ⚠ app.gov.al meklēšana neizdevās.'); return 0; }

    $imported = 0;
    foreach (albania_parse($body) as $n) {
        if (!ks_within_retention($n) || !ks_backfill_keep($n)) continue;
        $stmt->execute($n);
        $imported++;
    }

    if ($imported > 0) ks_log("  ✓ Albānija → $imported mazie iepirkumi (app.gov.al).");
    return $imported;
}

/**
 * Kipra (data.gov.cy) — TIKAI piešķirtie līgumi jeb 'Rezultāti'.
 *
 * Aktīvo konkursu meklēšana eprocurement.gov.cy ir aiz CAPTCHA, ko apzināti
 * neapejam, tāpēc Kiprai 'Aktīvie' paliek tukši. Valsts kase piešķirtos līgumus
 * publicē atvērto datu portālā kā pusgada CSV; faila nosaukums mainās, tāpēc
 * saiti meklē datukopas lapā (viens HTML pieprasījums + viens CSV).
 * @return int importēto skaits
 */
function ks_sync_cyprus(PDO $pdo): int {
    $html = ks_http_get(CYPRUS_DATASET_URL, ['Accept: text/html'], 60);
    if ($html === null) { ks_log('  ⚠ data.gov.cy datukopas lapa nav pieejama.'); return 0; }

    // Resursu saites ir /sites/default/files/...CfTsAwarded....csv; ņem visas,
    // jaunākā gada faili beigās — importē visus, retention nogriež vecos.
    if (!preg_match_all('#href="(/sites/default/files/[^"]*CfTsAwarded[^"]*\.csv)"#i', $html, $mm)) {
        ks_log('  ⚠ data.gov.cy: CSV resursi nav atrasti.');
        return 0;
    }
    $urls = array_values(array_unique($mm[1]));
    // Jaunākie faili nes tekošo gadu — pietiek ar diviem jaunākajiem (60 d logs).
    // Gada SĀKUMĀ (līdz martam) logs sniedzas iepriekšējā gadā → vajag arī tā
    // failus; fallback ņem saraksta PĒDĒJO (jaunākie ir beigās, ne sākumā).
    $now = new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga'));
    $years = [$now->format('Y')];
    if ((int)$now->format('n') <= 3) $years[] = (string)((int)$now->format('Y') - 1);
    $pick = array_values(array_filter($urls, function ($u) use ($years) {
        foreach ($years as $y) { if (str_contains(rawurldecode($u), $y)) return true; }
        return false;
    }));
    if (!$pick) $pick = array_slice($urls, -1);

    // Viens CFTID bieži ir VAIRĀKI piešķīrumi (loti/piegādātāji), katrs sava CSV rinda.
    // Agregējam pa id, citādi katra rinda pārrakstītu iepriekšējo → versiju churn katrā
    // palaišanā (bija ~168 lieku versiju/palaišanā Kiprai vien). Agregāts deterministisks
    // (summa + sakārtoti uzvarētāji + max datums), tāpēc hash stabils → nulle churn.
    $acc = []; // id => ['n'=>bāze, 'budget'=>?float, 'winners'=>[name=>true], 'lines'=>int, 'pub'=>?string]
    foreach ($pick as $path) {
        if (ks_stop_requested()) break;
        $tmp = konkursi_tmp_dir() . '/cyprus.csv';
        if (!ks_http_download('https://www.data.gov.cy' . $path, $tmp, true)) {
            ks_log('  ⚠ Kipras CSV lejupielāde neizdevās.');
            continue;
        }
        $fh = @fopen($tmp, 'r');
        if ($fh === false) { @unlink($tmp); continue; }
        $hdr = fgetcsv($fh, 0, ',', '"', '\\');
        if (!is_array($hdr)) { fclose($fh); @unlink($tmp); continue; }
        $hdr[0] = trim((string)$hdr[0], "\xEF\xBB\xBF"); // BOM pirmajā kolonnā
        $ix = [];
        foreach ($hdr as $i => $h) $ix[trim((string)$h)] = $i;
        while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (ks_stop_requested()) break;
            if (!is_array($r) || count($r) < 5) continue;
            $n = cyprus_award_notice($r, $ix);
            if ($n === null || !ks_within_retention($n)) continue;
            $id = $n['id'];
            if (!isset($acc[$id])) {
                $acc[$id] = ['n' => $n, 'budget' => null, 'winners' => [], 'lines' => 0, 'pub' => null];
            }
            $a = &$acc[$id];
            $a['lines']++;
            if ($n['budget'] !== null) $a['budget'] = ($a['budget'] ?? 0.0) + (float)$n['budget'];
            $w = trim((string)($n['_winner'] ?? ''));
            if ($w !== '') $a['winners'][$w] = true;
            if ($n['publication_date'] !== null && ($a['pub'] === null || $n['publication_date'] > $a['pub'])) {
                $a['pub'] = $n['publication_date']; // jaunākais piešķiršanas datums (secību-neatkarīgs)
            }
            unset($a);
        }
        fclose($fh);
        @unlink($tmp);
    }

    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    foreach ($acc as $a) {
        if (ks_stop_requested()) break;
        $n = $a['n'];
        unset($n['_winner']);
        $n['budget'] = $a['budget'];
        if ($a['pub'] !== null) $n['publication_date'] = $a['pub'];
        $winners = array_keys($a['winners']);
        sort($winners, SORT_STRING); // stabila secība → stabils hash
        $orgs = [array_filter(['name' => $n['buyer_name'], 'country' => 'CY'])];
        foreach ($winners as $w) $orgs[] = ['name' => $w . ' (uzvarētājs)', 'country' => 'CY'];
        $n['organizations'] = json_encode($orgs, JSON_UNESCAPED_UNICODE);
        if ($a['lines'] > 1) {
            $n['description'] = 'Piešķirti ' . $a['lines'] . ' līgumi ' . count($winners)
                . ' piegādātājiem; norādīta kopējā piešķirtā vērtība.';
        }
        $stmt->execute($n);
        $imported++;
    }
    // Drošības tīkls tiem 26 ierakstiem, kuriem sliekšņa lauks ir tukšs
    $del = ks_dedupe_vs_ted($pdo, 'CYPRUS', 'CY');
    if ($del > 0) ks_log("  ⧉ Kipra: $del dublējās ar TED — noņemti (paliek TED rinda).");

    if ($imported > 0) ks_log("  ✓ Kipra → $imported piešķirtie līgumi (rezultāti).");
    return $imported;
}

/**
 * dados.gov.pt gada lielapjoma fails (anuncios{gads}.json) → atvērtie konkursi.
 *
 * Resursa URL satur versijas mapi, kas mainās ik nedēļu, tāpēc to meklē caur
 * datasets API, nevis kodē cieti. Importē tikai tos, kuru termiņš vēl nav
 * pagājis — pārējais ir arhīvs, ko tāpat izmestu ks_prune.
 * @return int importēto skaits
 */
function ks_sync_base_bulk(PDO $pdo, KsWriter $stmt): int {
    $meta = ks_http_get(BASE_BULK_DATASET_URL, ['Accept: application/json'], 60);
    $md = $meta !== null ? json_decode($meta, true) : null;
    if (!is_array($md)) { ks_log('  ⚠ dados.gov.pt katalogs nav pieejams.'); return 0; }

    $year = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))->format('Y');
    $url = null;
    foreach ((array)($md['data'] ?? []) as $ds) {
        if (!is_array($ds) || !str_contains(mb_strtolower((string)($ds['title'] ?? ''), 'UTF-8'), 'anúncios')) continue;
        foreach ((array)($ds['resources'] ?? []) as $r) {
            if (!is_array($r)) continue;
            if (strtolower((string)($r['format'] ?? '')) !== 'json') continue;
            if (!str_contains((string)($r['title'] ?? ''), $year)) continue;
            $url = (string)($r['url'] ?? '');
            break 2;
        }
    }
    if ($url === '' || $url === null) { ks_log("  ⚠ dados.gov.pt: $year. gada JSON resurss nav atrasts."); return 0; }

    $tmp = konkursi_tmp_dir() . '/base_bulk.json';
    if (!ks_http_download($url, $tmp, true)) { ks_log('  ⚠ BASE lielapjoma faila lejupielāde neizdevās.'); return 0; }
    $raw = (string)file_get_contents($tmp);
    @unlink($tmp);
    $rows = json_decode($raw, true);
    unset($raw);
    if (!is_array($rows)) { ks_log('  ⚠ BASE lielapjoma fails nav derīgs JSON.'); return 0; }

    $today = konkursi_today();
    $imported = 0;
    foreach ($rows as $x) {
        if (!is_array($x)) continue;
        if (ks_stop_requested()) break;
        // Tikai vēl atvērtie — vecos tāpat izmestu retention/prune
        $dl = base_bulk_date($x['DataLimitePropostas'] ?? null);
        if ($dl === null || $dl < $today) continue;
        $n = base_bulk_notice($x);
        if ($n === null || !ks_within_retention($n)) continue;
        $stmt->execute($n);
        $imported++;
    }
    return $imported;
}

function ks_sync_base(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $rec = $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)');
    $imported = 0; $details = 0;
    $hdrs = ['X-Requested-With: XMLHttpRequest', 'Referer: https://www.base.gov.pt/Base4/pt/pesquisa/'];
    // Dziļajā aizpildē lapošana vairs nav galvenais ceļš — 60 d logu nosedz
    // lielapjoma fails (ks_sync_base_bulk). Lapošana paliek tikai svaigākajām
    // dienām (fails atjaunojas reizi nedēļā), un ierobežotais pieprasījumu
    // budžets aiziet līgumu fāzei, kurai cita avota nav.
    $maxPages   = ks_cap(BASE_MAX_PAGES, 12); // serveris dod pa 20 rindām lapā (size ignorē)
    $maxDetails = ks_cap(BASE_MAX_DETAILS_PER_RUN, 8000);
    $cutExpired = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days')->format('Y-m-d');

    // Dziļajā aizpildē sāk tieši aiz zināmās zonas: rate-limiters dod ~240
    // pieprasījumus palaišanā, tos visus jātērē jaunajā teritorijā (atkārtotas
    // palaišanas virzās arvien dziļāk). -3 lapas = rezerve jaunpublicētajiem.
    $startPage = 0;
    if (konkursi_deep()) {
        $marked = (int)$pdo->query("SELECT COUNT(*) FROM imported_files WHERE file_key LIKE 'BASE:%'")->fetchColumn();
        $startPage = max(0, intdiv($marked, 20) - 3);
    }

    // SECĪBA IR SVARĪGA — tas, kas sākas pirmais, patērē pieprasījumu budžetu:
    //   1) lielapjoma fails (dados.gov.pt) — 1 pieprasījums, VISI atvērtie konkursi;
    //   2) līgumi (rezultāti) — base.gov.pt ir vienīgais avots (gada lielapjoma
    //      fails šim neder: JSON ZIP ir bojāts avotā, XLSX = 163 MB XML uz 1000
    //      vajadzīgajām rindām — pārbaudīts 2026-07-20);
    //   3) anúncios lapošana — pēdējā, tikai svaigākajām dienām virs faila.
    // Tempu pret base.gov.pt uztur ks_http_throttle (4 s/pieprasījums), tāpēc
    // atsevišķi sleep() te vairs nav vajadzīgi.
    $bulk = ks_sync_base_bulk($pdo, $stmt);
    if ($bulk > 0) { ks_log("  ✓ BASE lielapjoma fails → $bulk atvērtie konkursi."); $imported += $bulk; }

    $contracts = 0;
    if (!ks_stop_requested()) {
        // Serveris dod pa 20 rindām lapā → ~55 lapas sasniedz KONKURSI_RESULTS_CAP
        $cPages = ks_cap(5, 55);
        for ($page = 0; $page < $cPages; $page++) {
            if (ks_stop_requested()) break;
            $body = ks_http_post_form(BASE_RESULTS_URL, [
                'type' => 'search_contratos', 'version' => '91.0',
                'query' => 'texto=&tipo=0&tipocontrato=0',
                'sort' => '-publicationDate', 'page' => $page, 'size' => 100,
            ], $hdrs);
            $d = $body !== null ? json_decode($body, true) : null;
            $rows = is_array($d) ? ($d['items'] ?? null) : null;
            if (!is_array($rows) || !$rows) {
                if ($page === 0) ks_log('  ⚠ BASE līgumu saraksts nav pieejams.');
                break;
            }
            $oldOnPage = 0;
            foreach ($rows as $it) {
                if (!is_array($it)) continue;
                $n = base_contract_notice($it);
                if ($n === null) continue;
                if (!ks_within_retention($n)) { $oldOnPage++; continue; }
                $stmt->execute($n);
                $contracts++;
            }
            if ($oldOnPage === count($rows)) break; // aiz glabāšanas loga
        }
    }
    if ($contracts > 0) { ks_log("  ✓ BASE → $contracts noslēgtie līgumi (rezultāti)."); $imported += $contracts; }

    for ($page = $startPage; $page < $startPage + $maxPages; $page++) {
        if (ks_stop_requested()) break;
        // PIEZĪME: serveris ignorē size un dod pa 20 rindām lapā; pēc daudzām
        // ātrām lapām mēdz atbildēt tukši (rate-limits) → viens atkārtojums ar pauzi.
        $rows = null;
        foreach ([0, 20] as $backoff) {
            if ($backoff > 0) sleep($backoff);
            $body = ks_http_post_form(BASE_RESULTS_URL, [
                'type' => 'search_anuncios', 'version' => '91.0',
                'query' => 'texto=&tipo=0&tipocontrato=0',
                'sort' => '-drPublicationDate', 'page' => $page, 'size' => 100,
            ], $hdrs);
            $d = $body !== null ? json_decode($body, true) : null;
            $rows = is_array($d) ? ($d['items'] ?? null) : null;
            if (is_array($rows) && $rows) break;
        }
        if (!is_array($rows) || !$rows) {
            ks_log("  ⚠ BASE saraksts neatbild (lapa $page) — pārtraucu.");
            break;
        }
        $newOnPage = 0; $freshOnPage = 0;
        foreach ($rows as $it) {
            if (!is_array($it)) continue;
            $rid = (int)($it['id'] ?? 0);
            if ($rid <= 0) continue;
            $rawPub = base_date(is_string($it['drPublicationDate'] ?? null) ? $it['drPublicationDate'] : null);
            if ($rawPub === null || $rawPub >= ks_active_cutoff()) $freshOnPage++;
            $key = 'BASE:' . $rid;
            $chk->execute([$key]);
            if ($chk->fetchColumn() !== false) continue;
            $newOnPage++;
            // Dziļajā aizpildē: sen beidzies termiņš jau sarakstā → bez detaļas
            $rawDl = base_date(is_string($it['proposalDeadline'] ?? null) ? $it['proposalDeadline'] : null);
            if (konkursi_deep() && $rawDl !== null && $rawDl < $cutExpired) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $det = null;
            if ($details < $maxDetails) {
                usleep(400000);
                $db = ks_http_post_form(BASE_RESULTS_URL,
                    ['type' => 'detail_anuncios', 'version' => '91.0', 'id' => $rid], $hdrs);
                $details++;
                $det = $db !== null ? json_decode($db, true) : null;
                if (!is_array($det)) $det = null;
            } else {
                continue; // limits — paliks nākamajai palaišanai
            }
            $n = base_build_notice($it, $det);
            if ($n === null || !ks_within_retention($n) || !ks_backfill_keep($n)) {
                $rec->execute([$key, date('c'), 0]);
                continue;
            }
            $stmt->execute($n);
            $rec->execute([$key, date('c'), 1]);
            $imported++;
        }
        // Dziļajā aizpildē apstājas pie datuma horizonta
        if (konkursi_deep() ? $freshOnPage === 0 : $newOnPage === 0) break;
        // (tempu uztur ks_http_throttle — 4 s/pieprasījums pret base.gov.pt)
    }


    // Dedup pret TED: vērtības slieksnis te nepalīdz — portugāļu pircēji sūta uz
    // TED arī krietni zem sliekšņa esošos (2026-07-20 mērījums: 23 pārklājumi,
    // budžeti €38k–105k pie 221k sliekšņa). Tas pats risinājums kā ES/AT/RO/BG.
    $del = ks_dedupe_vs_ted($pdo, 'BASE', 'PT');
    if ($del > 0) ks_log("  ⧉ BASE: $del dublējās ar TED — noņemti (paliek TED rinda).");

    if ($imported > 0) ks_log("  ✓ BASE → $imported nacionālie paziņojumi ($details detaļas).");
    return $imported;
}

// ── Pasaules Banka (procnotices API) — AZ/AM/GE/TR ────────────────────────────

/**
 * WB procnotices API, per-valsts vaicājums (project_ctry_name), jaunākie vispirms.
 * Sedz 4 valstis, kur nacionālā plūsma nav pieejama. Dedup pret TED (ja ES-finansēts).
 * @return int importēto skaits
 */
function ks_sync_worldbank(PDO $pdo): int {
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0;
    foreach (WB_COUNTRIES as $cc => $name) {
        if (ks_stop_requested()) break;
        $url = WB_API_URL . '?format=json&rows=' . WB_ROWS
             . '&srt=noticedate&order=desc&project_ctry_name=' . rawurlencode($name);
        $body = ks_http_get($url, ['Accept: application/json'], 60);
        // WB serveris mēdz dot pārejošu 500 — atkārto 1×, lai neizlaistu valsti.
        if ($body === null) { usleep(600000); $body = ks_http_get($url, ['Accept: application/json'], 60); }
        $d = $body !== null ? json_decode($body, true) : null;
        $rows = is_array($d) ? ($d['procnotices'] ?? null) : null;
        if (!is_array($rows)) { ks_log("  ⚠ Pasaules Banka ($cc) nav pieejama."); usleep(WB_DELAY_MS * 1000); continue; }
        $c = 0;
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $n = wb_parse_notice($r, $cc);
            if ($n === null || ks_stale_nodeadline($n) || !ks_within_retention($n)) continue;
            $stmt->execute($n);
            $c++; $imported++;
        }
        if ($c > 0) ks_log("  ✓ Pasaules Banka $cc → $c paziņojumi.");
        usleep(WB_DELAY_MS * 1000);
    }
    foreach (array_keys(WB_COUNTRIES) as $cc) {
        $del = ks_dedupe_vs_ted($pdo, 'WB', $cc);
        if ($del > 0) ks_log("  ⧉ WB $cc: $del dublējās ar TED — noņemti.");
    }
    return $imported;
}

// ── EBRD ECEPP (viena HTML lapa) — AZ/AM/GE/TR ────────────────────────────────

/**
 * EBRD ECEPP publiskā meklēšanas lapa satur VISUS ~4000 paziņojumus ar pilniem
 * laukiem → viens pieprasījums, filtrē AZ/AM/GE/TR klienta pusē, detaļu lapas
 * nevajag. EBRD plūst uz TED → dedup pret TED (svarīgi Turcijai).
 * @return int importēto skaits
 */
function ks_sync_ebrd(PDO $pdo): int {
    $body = ks_http_get(EBRD_SEARCH_URL, ['Accept: text/html'], 90);
    if ($body === null || strlen($body) < 10000) { ks_log('  ⚠ EBRD ECEPP nav pieejams.'); return 0; }
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0; $byc = [];
    foreach (ebrd_parse_all($body) as $n) {
        if (ks_stale_nodeadline($n)) continue;   // vecie bezdatuma GPN/addendi — neimportē
        if (!ks_within_retention($n)) continue;
        $stmt->execute($n);
        $imported++;
        $byc[$n['buyer_country']] = ($byc[$n['buyer_country']] ?? 0) + 1;
    }
    if ($imported > 0) {
        $parts = [];
        foreach ($byc as $cc => $c) $parts[] = "$cc:$c";
        ks_log('  ✓ EBRD ECEPP → ' . $imported . ' paziņojumi (' . implode(' ', $parts) . ').');
    }
    foreach (array_values(EBRD_COUNTRIES) as $cc) {
        $del = ks_dedupe_vs_ted($pdo, 'EBRD', $cc);
        if ($del > 0) ks_log("  ⧉ EBRD $cc: $del dublējās ar TED — noņemti.");
    }
    return $imported;
}

// ── UNDP (Europe & CIS RSS) — ANO attīstības iepirkumi ────────────────────────

/**
 * UNDP RER (Europe & CIS) RSS: viens fails, parseris filtrē ES-perspektīvas valstis
 * (izslēdz RU/BY + Centrālāziju). ANO aģentūras PAŠU iepirkums — nav TED dedup (cita
 * garša nekā nacionālie/banku). ANO slānis gap valstīm + Balkāniem/UA/MD.
 * @return int importēto skaits
 */
function ks_sync_undp(PDO $pdo): int {
    // BEZ Accept galvenes: UNDP WAF uz mūsu UA + 'Accept: application/xml' dod 406,
    // bet uz curl noklusējuma '*/*' atbild normāli (pārbaudīts 2026-07-21).
    $body = ks_http_get(UNDP_FEED_URL, [], 60);
    if ($body === null || strlen($body) < 2000) { ks_log('  ⚠ UNDP RSS nav pieejams.'); return 0; }
    $stmt = ks_upsert_stmt($pdo);
    $imported = 0; $byc = [];
    foreach (undp_parse_all($body) as $n) {
        if (!ks_within_retention($n)) continue;
        $stmt->execute($n);
        $imported++;
        $byc[$n['buyer_country']] = ($byc[$n['buyer_country']] ?? 0) + 1;
    }
    if ($imported > 0) {
        arsort($byc);
        $parts = [];
        foreach (array_slice($byc, 0, 8, true) as $cc => $c) $parts[] = "$cc:$c";
        ks_log('  ✓ UNDP (Europe & CIS) → ' . $imported . ' paziņojumi (' . implode(' ', $parts) . ').');
    }
    return $imported;
}

// ── ANAC Open Data (IT) sinhronizācija ────────────────────────────────────────

/**
 * Itālijas CIG mēneša delta ZIP CSV: ņem tikai aktīvos (termiņš nākotnē)
 * konkurences iepirkumus, lotes grupē pa numero_gara. Failu pārimportē tikai
 * tad, ja mainījies Last-Modified (atzīmes atslēgā). Ikdienā: tekošais mēnesis
 * (+iepriekšējais līdz 10. datumam — jaunā faila vēl var nebūt); dziļajā
 * režīmā + 2 iepriekšējie mēneši.
 * @return int importēto skaits
 */
function ks_sync_anac(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $m0 = (new DateTimeImmutable('now', $tz))->modify('first day of this month');
    $months = [$m0];
    if ((int)(new DateTimeImmutable('now', $tz))->format('j') <= 10 || konkursi_deep()) {
        $months[] = $m0->modify('-1 month');
    }
    if (konkursi_deep()) $months[] = $m0->modify('-2 months');
    usort($months, fn($a, $b) => $a <=> $b); // vecākais vispirms — jaunākie dati pārraksta

    $stmt = ks_upsert_stmt($pdo);
    $chk = $pdo->prepare('SELECT 1 FROM imported_files WHERE file_key = ?');
    $today = konkursi_today();
    $imported = 0;

    foreach ($months as $m) {
        if (ks_stop_requested()) break;
        $ymd = $m->format('Ym') . '01';
        $url = sprintf(ANAC_ZIP_URL_FMT, $ymd);
        $lm = ks_http_last_modified($url);
        if ($lm === null) { ks_log("  · ANAC $ymd delta (vēl) nav publicēta."); continue; }
        $fileKey = 'ANAC:' . $ymd . ':' . $lm;
        $chk->execute([$fileKey]);
        if ($chk->fetchColumn() !== false) continue; // šī versija jau importēta

        $tmp = konkursi_tmp_dir() . "/anac_$ymd.zip";
        ks_log("⬇ ANAC $ymd delta (~45 MB)...");
        if (!ks_http_download($url, $tmp, true)) { ks_log("  ⚠ ANAC $ymd lejupielāde neizdevās."); continue; }

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) { @unlink($tmp); ks_log("  ⚠ ANAC $ymd ZIP neatveras."); continue; }
        $csvName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nm = $zip->getNameIndex($i);
            if (is_string($nm) && str_ends_with(strtolower($nm), '.csv')) { $csvName = $nm; break; }
        }
        $fh = $csvName !== null ? $zip->getStream($csvName) : false;
        if ($fh === false) { $zip->close(); @unlink($tmp); ks_log("  ⚠ ANAC $ymd CSV nav atrodams."); continue; }

        // 1. piegājiens: straumējot agregē aktīvās gares (visas lotes kopā)
        $hdr = fgetcsv($fh, 0, ';', '"', '\\');
        $ix = [];
        foreach (is_array($hdr) ? $hdr : [] as $i => $h) $ix[trim((string)$h, "\" \xEF\xBB\xBF")] = $i;
        $need = ['cig','numero_gara','oggetto_gara','data_scadenza_offerta','tipo_scelta_contraente'];
        $ok = !array_diff($need, array_keys($ix));
        $gare = [];
        $rows = 0;
        $col = fn(array $r, string $k): string => isset($ix[$k], $r[$ix[$k]]) ? trim((string)$r[$ix[$k]]) : '';
        while ($ok && ($r = fgetcsv($fh, 0, ';', '"', '\\')) !== false) {
            if ((++$rows % 20000) === 0 && ks_stop_requested()) break;
            if (!is_array($r)) continue;
            $scad = substr($col($r, 'data_scadenza_offerta'), 0, 10);
            if ($scad < $today) continue;                                  // tikai aktīvie
            if ($col($r, 'DATA_CANCELLAZIONE') !== '') continue;           // atcelts
            $tipo = $col($r, 'tipo_scelta_contraente');
            if (str_starts_with($tipo, 'AFFIDAMENTO')) continue;           // tiešie piešķīrumi
            $ng = $col($r, 'numero_gara');
            if ($ng === '') continue;

            if (!isset($gare[$ng])) {
                $gare[$ng] = [
                    'gara' => $ng,
                    'title' => $col($r, 'oggetto_gara'),
                    'desc' => null,
                    'buyer' => $col($r, 'denominazione_amministrazione_appaltante'),
                    'buyer_id' => $col($r, 'cf_amministrazione_appaltante'),
                    'nature' => $col($r, 'oggetto_principale_contratto'),
                    'pub' => substr($col($r, 'data_pubblicazione'), 0, 10) ?: null,
                    'deadline' => $scad,
                    'budget' => anac_num($col($r, 'importo_complessivo_gara')),
                    'cpv' => [], 'main_cpv' => null, 'lots' => [],
                    'cig' => $col($r, 'cig'),
                    'provincia' => $col($r, 'provincia'),
                    'procedure' => $tipo,
                ];
            }
            $g = &$gare[$ng];
            if ($scad > $g['deadline']) $g['deadline'] = $scad;
            $pub = substr($col($r, 'data_pubblicazione'), 0, 10);
            if ($pub !== '' && ($g['pub'] === null || $pub < $g['pub'])) $g['pub'] = $pub;
            if (preg_match('/^(\d{8})/', $col($r, 'cod_cpv'), $mm)) {
                $g['cpv'][] = $mm[1];
                if ($g['main_cpv'] === null || $col($r, 'flag_prevalente') === 'S') $g['main_cpv'] = $mm[1];
            }
            if ($col($r, 'flag_prevalente') === 'S' || $g['desc'] === null) {
                $lotto = $col($r, 'oggetto_lotto');
                if ($lotto !== '') $g['desc'] = $lotto;
            }
            if (count($g['lots']) < KONKURSI_MAX_LOTS) {
                $g['lots'][] = array_filter([
                    'title' => mb_substr($col($r, 'oggetto_lotto'), 0, KONKURSI_LOT_DESC_MAX),
                    'value' => anac_num($col($r, 'importo_lotto')),
                    'id'    => $col($r, 'cig'),
                ], fn($v) => $v !== null && $v !== '');
            }
            unset($g);
        }
        fclose($fh);
        $zip->close();
        @unlink($tmp);
        if (!$ok) { ks_log("  ⚠ ANAC $ymd CSV galvene neatbilst (mainīts formāts?)."); continue; }

        // 2. piegājiens: būvē un raksta
        $count = 0; $skippedOver = 0;
        $pdo->beginTransaction();
        try {
            foreach ($gare as $g) {
                $n = anac_build_notice($g);
                if ($n === null || !ks_within_retention($n)) { $skippedOver++; continue; }
                $stmt->execute($n);
                $count++;
            }
            // Vecās šī mēneša faila versiju atzīmes vairs nevajag
            $pdo->prepare("DELETE FROM imported_files WHERE file_key LIKE ?")->execute(['ANAC:' . $ymd . ':%']);
            $pdo->prepare('INSERT OR REPLACE INTO imported_files (file_key, imported_at, notice_count) VALUES (?,?,?)')
                ->execute([$fileKey, date('c'), $count]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $imported += $count;
        ks_log("  ✓ ANAC $ymd → $count aktīvās gares ($skippedOver virs sliekšņa — tās ir TED; $rows CSV rindas).");
        sleep(1);
    }
    return $imported;
}

// ── Glabāšanas politika ───────────────────────────────────────────────────────

/**
 * Dublikātu atslēga: viens iepirkums = viena rinda.
 *
 * Pamatā ir contract_folder_id (eForms procedūras identifikators) — tas ir
 * stabils pret grozījumiem, kas maina nosaukumu, CPV vai termiņu. Nosaukuma
 * salīdzināšana šim neder: grozījums maina gan nosaukumu, gan CPV, un tad
 * dublikāts paliek nepamanīts.
 *
 * BET cfid nav globāli unikāls. TED/eForms lieto UUID (36 rakstzīmes), kas der
 * arī starp avotiem — Igaunijas RHR paziņojumam UUID sakrīt ar TED. Turpretī
 * PLACSP un BOSA lieto īsus vietējos numurus ("01/2026", "000599/2026"), kas
 * atkārtojas gan starp valstīm, gan starp pasūtītājiem vienas valsts iekšienē:
 * PLACSP 160 no 160 grupām ir DAŽĀDI pasūtītāji, t.i. sadursmes, ne dublikāti.
 * Tāpēc ne-UUID gadījumā atslēgā iekļauj avotu un pasūtītāju.
 */

/**
 * Avoti, kuru contract_folder_id NAV procedūras identifikators.
 *
 * KIMDIS (Grieķija) te bija līdz 2026-07-20. Tā cfid (aaht) tiešām ir iestādes
 * kods — zem '1015.E00226.0001' ir 144 rindas ar 109 DAŽĀDIEM nosaukumiem —
 * tāpēc dedup pēc cfid VIEN izdzēstu īstus konkursus. Bet atslēgā ir arī
 * nosaukums, un kopš veidnes nosaukumiem pievieno priekšmetu no apraksta
 * (sk. ks_sync_kimdis), atsevišķie iepirkumi vairs nesakrīt. Mērījums
 * 2026-07-20: visas 133 atlikušās vienādo nosaukumu grupas dalās VIENU cfid
 * (= viena procedūra, vairāki dokumenti), 0 grupu ar dažādiem cfid.
 * Tāpēc izņemts — dedup noņem 170 rindas, visas īsti dublējošas.
 */
const KS_DEDUPE_SKIP_SOURCES = [];
function ks_dupe_key_sql(string $t = 'n'): string {
    $folder = "CASE WHEN length($t.contract_folder_id) = 36"
            . "       AND $t.contract_folder_id LIKE '________-____-____-____-____________'"
            . "     THEN $t.contract_folder_id"
            . "     ELSE $t.source || '|' || ifnull($t.buyer_name,'') || '|' || $t.contract_folder_id END";
    // Nosaukums atslēgā ir OBLIGĀTS: viens cfid nesedz vienu iepirkumu. Dinamiskā
    // iepirkumu sistēmā zem viena procedūras ID ir vairākas patstāvīgas kategorijas
    // (piem. 16 TED rindas — 'Grant consultancy', 'Communicatieadviesdiensten',
    // 'Financiële adviesdiensten' — dažādi CPV, katra atsevišķi piesakāma).
    // Pārbaudītajos īstajos dublikātos nosaukums sakrīt vienmēr, arī tad, kad
    // grozījums nomainījis CPV vai termiņu, tāpēc tas šķir abus gadījumus.
    return "$folder || '|' || lower(trim(ifnull($t.title,'')))";
}

/**
 * Atrod dublējošos iepirkumus un atstāj no katras grupas tikai vienu rindu.
 *
 * Patur JAUNĀKO paziņojumu: 1796 no 3125 grupām termiņi atšķiras, jo grozījums
 * termiņu pagarinājis — vecajā rindā tas ir novecojis. Starp avotiem priekšroka
 * TED (strukturētāki dati), kas sakrīt ar pārējo adapteru dedup konvenciju.
 *
 * Tikai 'iepirkumi': piešķīrumiem viens cfid ar vairākiem paziņojumiem ir
 * normāli (viens uz katru daļu/uzvarētāju), tur tās nav dublikāti.
 *
 * @param bool $dryRun true — neko nedzēš, tikai saskaita
 * @return array{groups:int, removed:int}
 */
/**
 * Nacionālā avota rindas, kas pēc nosaukuma+pircēja dublē TED rindas tajā pašā
 * kategorijā, dzēš (paliek TED). Divi soļi — id atlase ar fetchAll (kursors
 * pilnībā aizvērts) un tad DELETE pa gabaliem: DELETE ar EXISTS uz temp tabulu
 * vienā savienojumā ar iepriekšēju CREATE TEMP ... AS SELECT no notices metās
 * 'database table is locked' (SQLITE_LOCKED; nogāza placsp un atkd posmus).
 * @return int izdzēsto skaits
 */
function ks_dedupe_vs_ted(PDO $pdo, string $source, string $country): int {
    $st = $pdo->prepare(
        "SELECT n.id FROM notices n
         WHERE n.source = ? AND n.category IN ('iepirkumi','rezultati')
           AND EXISTS (SELECT 1 FROM notices t
                       WHERE t.source = 'TED' AND t.buyer_country = ? AND t.category = n.category
                         AND lower(trim(t.title)) = lower(trim(n.title))
                         AND lower(trim(t.buyer_name)) = lower(trim(n.buyer_name)))");
    $st->execute([$source, $country]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    $st->closeCursor();
    $deleted = 0;
    foreach (array_chunk($ids, 400) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $q = $pdo->prepare("DELETE FROM notices WHERE id IN ($ph)");
        $q->execute($chunk);
        $deleted += $q->rowCount();
    }
    return $deleted;
}

/**
 * Avoti, kuriem TED sedz to pašu valsti (virs-sliekšņa dublējas). Katrs
 * ks_sync_* pats izsauc ks_dedupe_vs_ted savā ceļā, bet tas ķer tikai TED
 * rindas, kas jau bija DB, kad nacionālais avots skrēja. Tā kā TED posms ir
 * pirmais, pilnā sinhronizācijā ar to pietiek. Bet daļējā palaišanā (--only=ted
 * vai inkrementāla noķeršanās, kas pievieno TED rindas, bet nacionālo avotu
 * nepārstartē) TED rindas ienāk PĒC nacionālā dedup → dublikāti uzkrājas.
 *
 * Tāpēc: šī pāreja skrien sinhronizācijas beigās (aiz visiem posmiem), kur tā
 * ir secības-neatkarīga — vienalga, vai pēdējais skrēja TED vai nacionālais.
 */
const KS_TED_OVERLAP_PAIRS = [
    ['PLACSP', 'ES'], ['ATKD', 'AT'], ['SEAP', 'RO'], ['EOP', 'BG'],
    ['SIMAP', 'CH'], ['CYPRUS', 'CY'], ['BASE', 'PT'],
];

function ks_dedupe_all_vs_ted(PDO $pdo): int {
    $total = 0;
    foreach (KS_TED_OVERLAP_PAIRS as [$src, $cc]) {
        $total += ks_dedupe_vs_ted($pdo, $src, $cc);
    }
    return $total;
}

function ks_dedupe_notices(PDO $pdo, bool $dryRun = false): array {
    $key = ks_dupe_key_sql('n');
    $skip = "'" . implode("','", KS_DEDUPE_SKIP_SOURCES) . "'";
    // Rindas, kas NAV grupas pārstāvis (rn > 1)
    $sub = "SELECT n.id, ROW_NUMBER() OVER (
                       PARTITION BY $key
                       ORDER BY (n.source <> 'TED'), n.publication_date DESC, n.id DESC
                   ) AS rn
            FROM notices n
            WHERE n.category = 'iepirkumi'
              AND n.contract_folder_id IS NOT NULL AND n.contract_folder_id <> ''
              AND n.source NOT IN ($skip)";

    $groups = (int)$pdo->query(
        "SELECT COUNT(*) FROM (SELECT 1 FROM ($sub) WHERE rn = 2)"
    )->fetchColumn();

    if ($dryRun) {
        $removed = (int)$pdo->query("SELECT COUNT(*) FROM ($sub) WHERE rn > 1")->fetchColumn();
        return ['groups' => $groups, 'removed' => $removed];
    }

    $q = $pdo->prepare("DELETE FROM notices WHERE id IN (SELECT id FROM ($sub) WHERE rn > 1)");
    $q->execute();
    return ['groups' => $groups, 'removed' => $q->rowCount()];
}

/**
 * Grozījums, kam termiņš vēl nav pagājis, ir piesakāms konkurss, nevis arhīva
 * ieraksts — pieteikties var joprojām, tikai nosacījumi ir precizēti. Visi
 * parsētāji to liek 'izmainas' (eForms efac:Changes, IUB cont-modif, BZP u.c.),
 * tāpēc pārliek centrāli: viena vieta visiem avotiem, nevis 25 atsevišķi likumi.
 *
 * Līguma grozījumi (subtype 38-40) šeit neiekļūst — tiem tendera termiņa nav,
 * tāpēc deadline_date ir NULL.
 *
 * Jāizpilda PIRMS ks_prune(): pēc pārlikšanas rindu dzīvu tur deadline_date, ne
 * publication_date, un citādi vecāks, bet vēl atvērts konkurss tiktu izmests.
 *
 * @return int pārlikto skaits
 */
function ks_recategorize_open(PDO $pdo): int {
    $q = $pdo->prepare("UPDATE notices SET category = 'iepirkumi'
                        WHERE category = 'izmainas'
                          AND deadline_date IS NOT NULL AND deadline_date >= ?");
    $q->execute([konkursi_today()]);
    return $q->rowCount();
}

/**
 * CIETĀ arhīva dzēšana (ikdienas — lai DB neaug bezgalīgi). Izdzēš 'Rezultāti',
 * 'Grozījumi' un 'Cits' (rezultati/izmainas/citi) ierakstus, kas vecāki par
 * KONKURSI_KEEP_RESULTS_HARD_DAYS (90 d) pēc publikācijas — GAN no `notices` (displeja
 * skats; FTS iztīrās pati caur notices_fts_ad trigeri), GAN no `notice_versions`
 * (žurnāls), lai vieta tiešām atbrīvojas.
 *
 * Visām trim kategorijām publikācijas datums ir stabils pa versijām (nav termiņa, kas
 * mainītos kā iepirkumiem), tāpēc žurnālu drīkst tīrīt tieši pēc (category, publication_date).
 *
 * Kāpēc droši (nav dzēst→ielādēt→pārtulkot cikla): šīs kategorijas, kas vecākas par 60 d,
 * ks_within_retention noraida jau ievākšanā, un avotu ievākšanas logs ir ~60 d —
 * tātad 90 d izdzēstu ierakstu neviens avots vairs nepiedāvā ievākšanai.
 * Avotiem ar garāku logu (KONKURSI_KEEP_RESULTS_BY_SOURCE, piem. ETENDERS 180 d) cietais
 * slieksnis = tas pats garākais logs, lai nedzēstu vēl ievācamo/rādāmo.
 *
 * @return int izdzēsto `notices` rindu skaits
 */
function ks_prune_archive(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $today = new DateTimeImmutable(konkursi_today(), $tz);
    $cut = $today->modify('-' . KONKURSI_KEEP_RESULTS_HARD_DAYS . ' days')->format('Y-m-d');
    $cats = "category IN ('rezultati','izmainas','citi')";

    // Avoti ar garāku logu — tos dzēš tikai pēc viņu loga (ne agrāk par 90 d).
    $longer = KONKURSI_KEEP_RESULTS_BY_SOURCE;
    $skip = $longer ? " AND source NOT IN ('" . implode("','", array_keys($longer)) . "')" : '';

    $deleted = 0;
    // (1) Noklusējuma avoti — 90 d.
    $qn = $pdo->prepare("DELETE FROM notices WHERE $cats AND publication_date IS NOT NULL AND publication_date < ?$skip");
    $qn->execute([$cut]);
    $deleted += $qn->rowCount();
    $pdo->prepare("DELETE FROM notice_versions WHERE $cats AND publication_date IS NOT NULL AND publication_date < ?$skip")
        ->execute([$cut]);

    // (2) Garāka loga avoti — max(90 d, viņu logs).
    foreach ($longer as $src => $days) {
        $srcCut = $today->modify('-' . max(KONKURSI_KEEP_RESULTS_HARD_DAYS, (int)$days) . ' days')->format('Y-m-d');
        $q = $pdo->prepare("DELETE FROM notices WHERE source=? AND $cats AND publication_date IS NOT NULL AND publication_date < ?");
        $q->execute([$src, $srcCut]);
        $deleted += $q->rowCount();
        $pdo->prepare("DELETE FROM notice_versions WHERE source=? AND $cats AND publication_date IS NOT NULL AND publication_date < ?")
            ->execute([$src, $srcCut]);
    }

    if ($deleted > 0) {
        try { $pdo->exec('PRAGMA incremental_vacuum(4000)'); } catch (Throwable $e) { /* nav kritiski */ }
        try { $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (Throwable $e) { /* nav kritiski */ }
    }
    return $deleted;
}

/**
 * CIETĀ iepirkumu dzēšana (ikdienas — lai DB neaug). Izdzēš 'iepirkumi', kam:
 *   (a) beidzies termiņš vairāk par KONKURSI_KEEP_EXPIRED_DAYS (14 d) atpakaļ, VAI
 *   (b) nav termiņa un publicēts vairāk par KONKURSI_KEEP_NODEADLINE_DAYS (90 d) atpakaļ.
 * Aktīvie (termiņš nākotnē) NETIEK skarti.
 *
 * Droši (nav dzēst→ielādēt→pārtulkot cikla): (a) 14 d = tas pats slieksnis, kur
 * ievākšana (ks_within_retention) pārstāj iepirkumu pievienot, un TED ikdienas paketēs
 * iepirkumu termiņi ir nākotnē; (b) 90 d ir aiz avotu ~60 d ievākšanas loga.
 *
 * Žurnālu (notice_versions) tīra PĒC id (visas ieraksta versijas), izvēloties ierakstus
 * pēc PAŠREIZĒJĀ notices stāvokļa — tā aktīva, bet pagarināta iepirkuma vecās versijas
 * (ar seniem termiņiem) netiek kļūdaini izdzēstas.
 *
 * @return int izdzēsto `notices` rindu skaits
 */
function ks_prune_tenders(PDO $pdo): int {
    $tz = new DateTimeZone('Europe/Riga');
    $today = new DateTimeImmutable(konkursi_today(), $tz);
    $cutExpired = $today->modify('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days')->format('Y-m-d');
    $cutNoDl    = $today->modify('-' . KONKURSI_KEEP_NODEADLINE_DAYS . ' days')->format('Y-m-d');

    $cond = "category='iepirkumi' AND ("
          . "(deadline_date IS NOT NULL AND deadline_date < :exp) OR "
          . "(deadline_date IS NULL AND publication_date IS NOT NULL AND publication_date < :nodl))";

    // 1) Žurnāls PIRMS notices (vajag notices, lai atrastu izdzēšamos id; dzēš visas to versijas).
    $jv = $pdo->prepare("DELETE FROM notice_versions WHERE id IN (SELECT id FROM notices WHERE $cond)");
    $jv->execute([':exp' => $cutExpired, ':nodl' => $cutNoDl]);

    // 2) notices (FTS iztīrās pati caur notices_fts_ad trigeri).
    $nn = $pdo->prepare("DELETE FROM notices WHERE $cond");
    $nn->execute([':exp' => $cutExpired, ':nodl' => $cutNoDl]);
    $deleted = $nn->rowCount();

    if ($deleted > 0) {
        try { $pdo->exec('PRAGMA incremental_vacuum(4000)'); } catch (Throwable $e) { /* nav kritiski */ }
        try { $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (Throwable $e) { /* nav kritiski */ }
    }
    return $deleted;
}

function ks_prune(PDO $pdo): int {
    $today = konkursi_today();
    $tz = new DateTimeZone('Europe/Riga');
    $cutExpired  = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days')->format('Y-m-d');
    $cutNoDl     = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_NODEADLINE_DAYS . ' days')->format('Y-m-d');
    $cutResults  = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_RESULTS_DAYS . ' days')->format('Y-m-d');

    $total = 0;
    $q = $pdo->prepare("DELETE FROM notices WHERE category='iepirkumi' AND deadline_date IS NOT NULL AND deadline_date < ?");
    $q->execute([$cutExpired]);
    $total += $q->rowCount();

    $q = $pdo->prepare("DELETE FROM notices WHERE category='iepirkumi' AND deadline_date IS NULL AND publication_date IS NOT NULL AND publication_date < ?");
    $q->execute([$cutNoDl]);
    $total += $q->rowCount();

    // Avoti ar savu rezultātu glabāšanas termiņu (sk. KONKURSI_KEEP_RESULTS_BY_SOURCE)
    $ownKeep = array_keys(KONKURSI_KEEP_RESULTS_BY_SOURCE);
    $skip = $ownKeep ? " AND source NOT IN ('" . implode("','", $ownKeep) . "')" : '';
    $q = $pdo->prepare("DELETE FROM notices WHERE category IN ('rezultati','izmainas','citi') AND publication_date IS NOT NULL AND publication_date < ?$skip");
    $q->execute([$cutResults]);
    $total += $q->rowCount();
    foreach (KONKURSI_KEEP_RESULTS_BY_SOURCE as $src => $days) {
        $cut = (new DateTimeImmutable($today, $tz))->modify('-' . $days . ' days')->format('Y-m-d');
        $q = $pdo->prepare("DELETE FROM notices WHERE source = ? AND category IN ('rezultati','izmainas','citi') AND publication_date IS NOT NULL AND publication_date < ?");
        $q->execute([$src, $cut]);
        $total += $q->rowCount();
    }

    // Rezultātu griesti: katrā valsts sadaļā tikai jaunākie KONKURSI_RESULTS_CAP.
    $keepSql = "SELECT id FROM notices WHERE source = ? AND category='rezultati'
                ORDER BY COALESCE(publication_date, first_seen) DESC, id DESC LIMIT " . KONKURSI_RESULTS_CAP;
    $qDel = $pdo->prepare("DELETE FROM notices WHERE source = ? AND category='rezultati' AND id NOT IN ($keepSql)");
    foreach ($pdo->query("SELECT DISTINCT source FROM notices WHERE category='rezultati' AND source <> 'TED'")->fetchAll(PDO::FETCH_COLUMN) as $src) {
        $qDel->execute([$src, $src]);
        $total += $qDel->rowCount();
    }
    // TED ir viena sadaļa ar 27 valstīm — griesti uz valsti
    $keepTed = "SELECT id FROM notices WHERE source='TED' AND category='rezultati' AND COALESCE(buyer_country,'') = ?
                ORDER BY COALESCE(publication_date, first_seen) DESC, id DESC LIMIT " . KONKURSI_RESULTS_CAP;
    $qDelTed = $pdo->prepare("DELETE FROM notices WHERE source='TED' AND category='rezultati' AND COALESCE(buyer_country,'') = ? AND id NOT IN ($keepTed)");
    foreach ($pdo->query("SELECT DISTINCT COALESCE(buyer_country,'') FROM notices WHERE source='TED' AND category='rezultati'")->fetchAll(PDO::FETCH_COLUMN) as $cc) {
        $qDelTed->execute([$cc, $cc]);
        $total += $qDelTed->rowCount();
    }

    if ($total > 0) {
        try { $pdo->exec('PRAGMA incremental_vacuum(4000)'); } catch (Throwable $e) { /* nav kritiski */ }
    }
    try { $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (Throwable $e) { /* nav kritiski */ }
    return $total;
}

/** Pārrēķina un noglabā kopsavilkuma skaitītājus (lapas statistikai bez smagiem COUNT). */
function ks_refresh_meta(PDO $pdo): void {
    $counts = ['iepirkumi' => 0, 'rezultati' => 0, 'izmainas' => 0, 'citi' => 0];
    foreach ($pdo->query('SELECT category, COUNT(*) c FROM notices GROUP BY category') as $r) {
        $counts[$r['category']] = (int)$r['c'];
    }
    $countries = (int)$pdo->query('SELECT COUNT(DISTINCT buyer_country) FROM notices')->fetchColumn();
    // Distinktās valstis ar nacionālo (ne-TED) avotu — īstais valstu skaits (nevis
    // avotu atslēgu skaits: UK=3, DK=2 avoti; IFI WB/EBRD/UNDP nav valstis).
    $natCountries = (int)$pdo->query(
        "SELECT COUNT(DISTINCT buyer_country) FROM notices
         WHERE source <> 'TED' AND buyer_country IS NOT NULL AND buyer_country <> ''"
    )->fetchColumn();
    $sources = [];
    foreach ($pdo->query('SELECT source, COUNT(*) c FROM notices GROUP BY source') as $r) {
        $sources[$r['source']] = (int)$r['c'];
    }
    konkursi_meta_set($pdo, 'counts', json_encode($counts));
    konkursi_meta_set($pdo, 'sources', json_encode($sources));
    konkursi_meta_set($pdo, 'countries', (string)$countries);
    konkursi_meta_set($pdo, 'national_countries', (string)$natCountries);
    // Rīgas laiks (Europe/Riga, DST-aware) — serveris/CLI darbojas UTC, tāpēc date()
    // deva par 2–3 h atpaliekošu laiku; lietotājam rādām vietējo Rīgas laiku.
    konkursi_meta_set($pdo, 'last_sync', (new DateTimeImmutable('now', new DateTimeZone('Europe/Riga')))->format('Y-m-d H:i'));
}

// ── Galvenā izpilde ───────────────────────────────────────────────────────────

/**
 * @param array{max_packages?:int, backfill?:int, prune?:bool, iub?:bool, lt?:bool, skip?:string[], only?:string[]} $opts
 * @return int importēto paziņojumu skaits
 */
/**
 * Pārtulko virsrakstus, kam vēl nav LV tulkojuma (title_lv IS NULL), izmantojot
 * CENTRALIZĒTO Gemini klientu no Reģistra sadaļas (registrs/mi/gemini_client.php —
 * API kods/atslēga dzīvo TIKAI tur). Unikālos virsrakstus tulko vienreiz un
 * atjaunina visas rindas ar to pašu virsrakstu. Limits KONKURSI_TRANSLATE_MAX_RUN
 * ierobežo izmaksas vienā palaišanā; atlikušais tiek pabeigts nākamajās.
 * LV avotu virsrakstus kopē bez API; pārējos tulko viļņos pa
 * KONKURSI_TRANSLATE_PARALLEL paralēliem izsaukumiem ar maksas atslēgu.
 * @return int pārtulkoto UNIKĀLO virsrakstu skaits (bez LV kopijām)
 */
function ks_translate_new_titles(PDO $pdo, ?int $maxTitles = null): int {
    $client = __DIR__ . '/../../registrs/mi/gemini_client.php';
    if (!is_file($client)) { ks_log('  ⚠ Tulkošana: nav atrasts registrs/mi/gemini_client.php.'); return 0; }
    require_once $client;

    // Latvisko avotu (IUB + tirgus izpētes) virsraksti jau IR latviski — kopē bez
    // API. Sedz arī vēsturiskās rindas un tās, kam KsWriter nodzēsa title_lv pēc
    // title maiņas. Agrāk tie gāja caur Gemini, kas tos atsūtīja nemainītus —
    // ~15–25% no apjoma bija maksa par kopēšanu.
    $lvCopied = (int)$pdo->exec(
        "UPDATE notices SET title_lv = title
         WHERE title_lv IS NULL AND source IN ('IUB','MODTI','RSTI','ASTI','LDZ')");
    if ($lvCopied > 0) ks_log("  ⧉ Tulkošana: $lvCopied LV avotu virsraksti nokopēti bez API.");

    $max = $maxTitles ?? KONKURSI_TRANSLATE_MAX_RUN;
    // Unikālie netulkotie virsraksti (jaunākie vispirms — aktuālie prioritāri)
    $rows = $pdo->prepare(
        "SELECT title, MAX(COALESCE(publication_date, first_seen, '')) mp
         FROM notices WHERE title_lv IS NULL AND title IS NOT NULL AND title != ''
         GROUP BY title ORDER BY mp DESC LIMIT " . (int)$max);
    $rows->execute();
    $titles = $rows->fetchAll(PDO::FETCH_COLUMN);
    if (!$titles) return 0;

    // Tulko ar MAKSAS atslēgu, nepārsniedzot KONKURSI_TRANSLATE_PAID_DAILY_EUR
    // dienā (€ pēc API usageMetadata tokeniem, meta 'translate_paid_spend_*').
    // Bezmaksas atslēgas mēģinājums izņemts 2026-08-04 — sk. config.php piezīmi.
    $metaK = 'translate_paid_spend_' . konkursi_today();
    $cut = (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-30 days')->format('Y-m-d');
    $pdo->exec("DELETE FROM meta WHERE k LIKE 'translate_paid_spend_%' AND k < 'translate_paid_spend_$cut'");

    $upd = $pdo->prepare('UPDATE notices SET title_lv = ? WHERE title = ? AND title_lv IS NULL');
    $done = 0; $failed = 0; $skippedDone = 0; $budgetOut = false;

    // Viļņi pa KONKURSI_TRANSLATE_PARALLEL paketēm: visas viļņa paketes iet uz API
    // VIENLAIKUS (curl_multi) — viļņa sienas laiks ≈ lēnākais izsaukums, ne summa.
    $chunks = array_chunk($titles, KONKURSI_TRANSLATE_BATCH);
    foreach (array_chunk($chunks, max(1, KONKURSI_TRANSLATE_PARALLEL)) as $wave) {
        if (ks_stop_requested()) break;
        $spent = (float)(konkursi_meta_get($pdo, $metaK) ?? '0');
        if ($spent >= KONKURSI_TRANSLATE_PAID_DAILY_EUR) { $budgetOut = true; break; }

        // Paralēlās tulkošanas sardze: pirms API izsaukuma izmet virsrakstus, ko pa
        // šo laiku jau pārtulkojis cits process (translate_titles.php / cits sync) —
        // citādi API tiktu saukts un MAKSĀTS par jau padarītu darbu (2026-08-02
        // dubultās tulkošanas mācība). Viss vilnis vienā vaicājumā.
        $flat = array_merge(...$wave);
        $st = $pdo->prepare(
            "SELECT DISTINCT title FROM notices WHERE title_lv IS NULL AND title IN ("
            . implode(',', array_fill(0, count($flat), '?')) . ')');
        $st->execute($flat);
        $still = $st->fetchAll(PDO::FETCH_COLUMN);
        $skippedDone += count($flat) - count($still);
        if (!$still) continue;
        $waveChunks = array_chunk($still, KONKURSI_TRANSLATE_BATCH);

        $before = reg_gemini_usage_total();   // kopija (masīvs) — atsauce nesaglabājas
        $res = reg_gemini_translate_titles_multi($waveChunks, KONKURSI_TRANSLATE_PARALLEL);
        // Neveiksmīgās paketes vienreiz atkārto pēc īsas pauzes (kā agrāk pa vienai).
        $retry = [];
        foreach ($waveChunks as $i => $c) if (($res[$i] ?? null) === null) $retry[$i] = $c;
        if ($retry) {
            usleep(1500000);
            foreach (reg_gemini_translate_titles_multi($retry, KONKURSI_TRANSLATE_PARALLEL) as $i => $r) {
                if ($r !== null) $res[$i] = $r;
            }
        }
        $after = reg_gemini_usage_total();
        $eur = (($after['in'] - $before['in']) * KONKURSI_GEMINI_IN_USD_1M
              + ($after['out'] + $after['thoughts'] - $before['out'] - $before['thoughts']) * KONKURSI_GEMINI_OUT_USD_1M)
             / 1e6 * KONKURSI_USD_TO_EUR;
        if ($eur > 0) konkursi_meta_set($pdo, $metaK, sprintf('%.6f', $spent + $eur));

        // Visa viļņa UPDATE vienā transakcijā (viens WAL commits ~160 rindām).
        $pdo->beginTransaction();
        try {
            foreach ($waveChunks as $i => $chunk) {
                $lv = $res[$i] ?? null;
                if ($lv === null) { $failed += count($chunk); continue; }
                foreach ($chunk as $j => $orig) {
                    $t = trim((string)$lv[$j]);
                    if ($t !== '') { $upd->execute([$t, $orig]); $done++; }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        usleep(KONKURSI_TRANSLATE_DELAY_MS * 1000);
    }
    if ($budgetOut) ks_log(sprintf('  ⛔ Tulkošana: maksas dienas budžets €%.2f iztērēts — atlikums nākamreiz.',
        KONKURSI_TRANSLATE_PAID_DAILY_EUR));
    if ($failed > 0) {
        $why = reg_gemini_error_brief();
        ks_log("  ⚠ Tulkošana: $failed virsrakstiem API neatbildēja" . ($why !== '' ? " [$why]" : '') . ' (mēģinās nākamreiz).');
    }
    if ($skippedDone > 0) ks_log("  ⧉ Tulkošana: $skippedDone virsraksti izlaisti — cits process tos jau pārtulkoja.");
    return $done;
}

/**
 * Nacionālo/starptautisko avotu posmi: atslēga → [žurnāla galvene, kolektora
 * funkcija]. Vienuviet, lai to pašu sarakstu lieto gan ks_run_sync (secīgi vai
 * grupējot paralēlajiem strādniekiem), gan strādnieka process (ks_run_fetch_worker).
 * @return array<string, array{0:string,1:string}>
 */
function ks_extra_stages(): array {
    return [
        'modti'  => ['🇱🇻 AM tirgus izpētes (mod.gov.lv) pārbaude...', 'ks_sync_modti'],
        'rsti'   => ['🇱🇻 Rīgas satiksmes tirgus izpētes pārbaude...', 'ks_sync_rigassatiksme'],
        'asti'   => ['🇱🇻 Austrumu slimnīcas tirgus izpētes pārbaude...', 'ks_sync_aslimnica'],
        'ldzti'  => ['🇱🇻 LDz tirgus izpētes/apspriedes pārbaude...', 'ks_sync_ldz'],
        'rhr'    => ['🇪🇪 RHR (Igaunija) pārbaude...',   'ks_sync_rhr'],
        'hilma'  => ['🇫🇮 Hilma (Somija) pārbaude...',    'ks_sync_hilma'],
        'doffin' => ['🇳🇴 Doffin (Norvēģija) pārbaude...', 'ks_sync_doffin'],
        'udbud'  => ['🇩🇰 udbud.dk (Dānija) pārbaude...',  'ks_sync_udbud'],
        'comdia' => ['🇩🇰 Comdia (DK pašvaldības) pārbaude...', 'ks_sync_comdia'],
        'kommers'=> ['🇸🇪 KommersAnnons (Zviedrija) pārbaude...', 'ks_sync_kommers'],
        'isutb'  => ['🇮🇸 Útboðsvefur (Islande) pārbaude...',  'ks_sync_isutb'],
        'bzp'    => ['🇵🇱 BZP (Polija) pārbaude...',       'ks_sync_bzp'],
        'bkms'   => ['🇩🇪 BKMS (Vācija) pārbaude...',      'ks_sync_bkms'],
        'boamp'  => ['🇫🇷 BOAMP (Francija) pārbaude...',   'ks_sync_boamp'],
        'etenders' => ['🇮🇪 eTenders (Īrija) pārbaude...', 'ks_sync_etenders'],
        'tenderned' => ['🇳🇱 TenderNed (Nīderlande) pārbaude...', 'ks_sync_tenderned'],
        'placsp' => ['🇪🇸 PLACSP (Spānija) pārbaude...',  'ks_sync_placsp'],
        'vvz'    => ['🇨🇿 VVZ (Čehija) pārbaude...',      'ks_sync_vvz'],
        'uvo'    => ['🇸🇰 ÚVO (Slovākija) pārbaude...',   'ks_sync_uvo'],
        'bosa'   => ['🇧🇪 BOSA (Beļģija) pārbaude...',    'ks_sync_bosa'],
        'atkd'   => ['🇦🇹 Kerndaten (Austrija) pārbaude...', 'ks_sync_atkd'],
        'seap'   => ['🇷🇴 SEAP (Rumānija) pārbaude...',     'ks_sync_seap'],
        'eop'    => ['🇧🇬 CAIS EOP (Bulgārija) pārbaude...', 'ks_sync_eop'],
        'kimdis' => ['🇬🇷 KIMDIS (Grieķija) pārbaude...',   'ks_sync_kimdis'],
        'enar'   => ['🇸🇮 enarocanje (Slovēnija) pārbaude...', 'ks_sync_enar'],
        'eojn'   => ['🇭🇷 EOJN (Horvātija) pārbaude...',    'ks_sync_eojn'],
        'ekr'    => ['🇭🇺 EKR (Ungārija) pārbaude...',      'ks_sync_ekr'],
        'base'   => ['🇵🇹 BASE (Portugāle) pārbaude...',    'ks_sync_base'],
        'anac'   => ['🇮🇹 ANAC (Itālija) pārbaude...',      'ks_sync_anac'],
        'cyprus' => ['🇨🇾 data.gov.cy (Kipra) pārbaude...', 'ks_sync_cyprus'],
        'uk'     => ['🇬🇧 Contracts Finder + PCS (AK) pārbaude...', 'ks_sync_uk'],
        'simap'  => ['🇨🇭 simap.ch (Šveice) pārbaude...', 'ks_sync_simap'],
        'prozorro' => ['🇺🇦 Prozorro (Ukraina) pārbaude...', 'ks_sync_prozorro'],
        'livergabe' => ['🇱🇮 vergaben.llv.li (Lihtenšteina) pārbaude...', 'ks_sync_livergabe'],
        'mtender' => ['🇲🇩 MTender (Moldova) pārbaude...', 'ks_sync_mtender'],
        'bosnia' => ['🇧🇦 open.ejn.gov.ba (Bosnija) pārbaude...', 'ks_sync_bosnia'],
        'macedonia' => ['🇲🇰 e-nabavki.gov.mk (Ziemeļmaķedonija) pārbaude...', 'ks_sync_macedonia'],
        'serbia' => ['🇷🇸 jnportal.ujn.gov.rs (Serbija) pārbaude...', 'ks_sync_serbia'],
        'montenegro' => ['🇲🇪 cejn.gov.me (Melnkalne) pārbaude...', 'ks_sync_montenegro'],
        'albania' => ['🇦🇱 app.gov.al (Albānija) pārbaude...', 'ks_sync_albania'],
        'worldbank' => ['🏦 Pasaules Banka (AZ/AM/GE/TR) pārbaude...', 'ks_sync_worldbank'],
        'ebrd'      => ['🏦 EBRD ECEPP (AZ/AM/GE/TR) pārbaude...', 'ks_sync_ebrd'],
        'undp'      => ['🇺🇳 UNDP (Europe & CIS) pārbaude...', 'ks_sync_undp'],
    ];
}

/** Strādnieku skaits: env KONKURSI_SYNC_WORKERS pārraksta konstanti (testiem
 *  un servera regulēšanai bez koda maiņas). */
function ks_sync_workers(): int {
    $v = getenv('KONKURSI_SYNC_WORKERS');
    return max(1, min(8, $v === false ? KONKURSI_SYNC_WORKERS : (int)$v));
}

/** Izpilda VIENU avota posmu: žurnāla galvene, laika budžets, kļūdu tvērums,
 *  ilguma pieraksts meta (stage_secs_<posms> — paralēlās grupēšanas balansam;
 *  atslēga per-posms, tāpēc paralēlie strādnieki cits citam neko nepārraksta). */
function ks_run_stage(PDO $pdo, string $stage, string $label, string $fn): int {
    ks_state(['stage' => $stage]);
    ks_stage_begin($stage); // laika budžets; ks_http_get to ievēro visos avotos
    ks_log($label);
    $t0 = microtime(true);
    $logBefore = ks_log_count(); // rindu skaits PĒC galvenes
    $c = 0;
    $ok = false;
    try {
        $c = $fn($pdo);
        $ok = true;
    } catch (Throwable $e) {
        // SQLITE_BUSY sacensība paralēlajā fāzē: kolektori ir idempotenti (upsert
        // pēc id + ūdenszīmes), tāpēc posmu vienreiz atkārtot ir droši. Pauze ļauj
        // slēdzes turētājam (piem., masveida DELETE ar FTS trigeriem) pabeigt.
        if (stripos($e->getMessage(), 'database is locked') !== false && !ks_stop_requested()) {
            ks_log("  ↻ $stage: datubāze aizņemta — atkārtoju posmu pēc 15 s.");
            sleep(15);
            try {
                $c = $fn($pdo);
                $ok = true;
            } catch (Throwable $e2) {
                ks_log("  ⚠ $stage posma kļūda (turpinu): " . $e2->getMessage());
            }
        } else {
            ks_log("  ⚠ $stage posma kļūda (turpinu): " . $e->getMessage());
        }
    }
    // Redzamība: ja posms nekā nelodžoja (ne ✓, ne ⚠, ne ·), klusums ir
    // divdomīgs — "nekā jauna" izskatās tāpat kā "avots neatbildēja". Skaidrs
    // 0-marķieris to novērš (turpmāk klusums = īsta problēma).
    if ($ok && ks_log_count() === $logBefore) {
        ks_log('  · nekā jauna (0 jaunu paziņojumu).');
    }
    try {
        konkursi_meta_set($pdo, 'stage_secs_' . $stage, (string)max(1, (int)round(microtime(true) - $t0)));
    } catch (Throwable $e) { /* ilguma pieraksts nav kritisks */ }
    return $c;
}

/**
 * Avotu posmi paralēli N strādnieku procesos (bin/sync.php --fetch-worker=...).
 * Kāpēc tas ir droši: (1) pieklājības pauzes, ķēdes pārtraucēji un atdzišanas
 * visi ir PER-HOST, un grupās hosti nepārklājas — avoti slogoti tieši kā agrāk;
 * (2) store.php raksta rindas līmenī bez garām transakcijām, tāpēc SQLite WAL
 * vairākus rakstītājus iztur ar busy_timeout. Grupas balansē pēc pēdējo
 * palaišanu ilgumiem (meta stage_secs_*, garākie vispirms — greedy LPT).
 * Bērnu žurnāls iet tieši failā (ks_log ar LOCK_EX); sync_state.json raksta
 * tikai vecākprocess. Strādnieka kļūme nozaudē tikai viņa grupas palaišanu —
 * nākamā sinhronizācija to paņems no ūdenszīmes.
 * @param array<string, array{0:string,1:string}> $stages
 */
function ks_run_stages_parallel(PDO $pdo, array $stages, int $workers): int {
    $durs = [];
    foreach ($pdo->query("SELECT k, v FROM meta WHERE k LIKE 'stage_secs_%'") as $r) {
        $durs[substr((string)$r['k'], strlen('stage_secs_'))] = (float)$r['v'];
    }
    $keys = array_keys($stages);
    usort($keys, fn($a, $b) => ($durs[$b] ?? 60.0) <=> ($durs[$a] ?? 60.0));
    $n = min($workers, count($keys));
    $groups = array_fill(0, $n, []);
    $loads  = array_fill(0, $n, 0.0);
    foreach ($keys as $k) {
        $i = (int)array_search(min($loads), $loads);
        $groups[$i][] = $k;
        $loads[$i] += $durs[$k] ?? 60.0;
    }
    ks_log("⛓ Paralēlā avotu ielāde: $n strādnieki — " . implode(' | ',
        array_map(fn($g, $l) => count($g) . ' avoti ~' . (int)$l . ' s', $groups, $loads)) . '.');
    ks_state(['stage' => 'parallel-fetch']);

    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $script = __DIR__ . '/../bin/sync.php';
    $tmp = konkursi_tmp_dir();
    @mkdir($tmp, 0775, true);
    $imported = 0;
    $procs = [];
    foreach ($groups as $gi => $g) {
        $countFile = $tmp . '/worker_' . $gi . '.count';
        $errFile   = $tmp . '/worker_' . $gi . '.err';
        @unlink($countFile);
        $p = @proc_open(
            [$php, $script, '--fetch-worker=' . implode(',', $g), '--fetch-count-file=' . $countFile],
            [0 => ['file', '/dev/null', 'r'],
             // stdout izmetam: ks_log tāpat visu raksta žurnāla failā (LOCK_EX);
             // pipe šeit būtu bīstams — nedrenēts tas pēc 64 KB bloķētu bērnu.
             1 => ['file', '/dev/null', 'w'],
             2 => ['file', $errFile, 'w']],
            $pipes);
        if (!is_resource($p)) {
            // proc_open liegts/neizdevās — šo grupu izpilda pats (pārējās var jau ritēt).
            ks_log("  ⚠ Strādnieku #$gi neizdevās palaist — grupu izpildu secīgi.");
            foreach ($g as $stage) {
                if (ks_stop_requested()) break;
                $imported += ks_run_stage($pdo, $stage, $stages[$stage][0], $stages[$stage][1]);
            }
            continue;
        }
        $procs[] = ['p' => $p, 'gi' => $gi, 'stages' => $g, 'count' => $countFile, 'err' => $errFile];
    }

    // Gaida visus. Cietais griests sargā pret karājošos bērnu; katru atsevišķu
    // avotu bērna iekšienē tāpat ierobežo KS_STAGE_MAX_S* budžeti.
    $deadline = time() + KONKURSI_SYNC_WORKER_MAX_S;
    foreach ($procs as $pr) {
        while (true) {
            $st = proc_get_status($pr['p']);
            if (!$st['running']) break;
            if (time() > $deadline) {
                ks_log('  ⏱ Strādnieks #' . $pr['gi'] . ' (' . implode(',', $pr['stages'])
                    . ') pārsniedza ' . KONKURSI_SYNC_WORKER_MAX_S . ' s griestus — pārtraucu.');
                proc_terminate($pr['p']);
                break;
            }
            usleep(300000);
        }
        proc_close($pr['p']);
        $imported += is_file($pr['count']) ? (int)@file_get_contents($pr['count']) : 0;
        @unlink($pr['count']);
        // PHP fatālās kļūdas bērnā nonāk stderr failā, ne žurnālā — paceļam tās šeit.
        if (is_file($pr['err']) && filesize($pr['err']) > 0) {
            $tail = trim(substr((string)@file_get_contents($pr['err']), -500));
            if ($tail !== '') ks_log('  ⚠ Strādnieka #' . $pr['gi'] . ' stderr: ' . $tail);
        }
        @unlink($pr['err']);
    }
    return $imported;
}

/**
 * Strādnieka process (bin/sync.php --fetch-worker=a,b,c): TIKAI norādīto avotu
 * ielāde. Bez slēdzenes (to tur vecākprocess), bez TED/dedup/prune/tulkošanas/
 * meta — tas viss paliek vecākprocesā pēc visu strādnieku beigām. Importēto
 * skaitu atdod caur $countFile (exit kods 0–255 tam par šauru).
 */
function ks_run_fetch_worker(array $stageKeys, ?string $countFile = null): int {
    ks_state_disabled(true);
    $pdo = konkursi_db();
    // Vairāki strādnieki raksta vienlaikus: WAL rindā liek rakstītājus citu aiz
    // cita, un viens masveida DELETE/UPDATE ar FTS trigeriem var turēt rakstīšanas
    // slēdzi ilgi (2026-08-04 pirmajā palaišanā 30 s nepietika — 7 posmi nokrita
    // ar SQLITE_BUSY). Te labāk pagaidīt pat 2 min nekā zaudēt visu avota posmu;
    // otrs aizsargslānis ir posma atkārtojums ks_run_stage.
    $pdo->exec('PRAGMA busy_timeout=120000');
    $defs = ks_extra_stages();
    $imported = 0;
    foreach ($stageKeys as $stage) {
        if (!isset($defs[$stage]) || ks_stop_requested()) continue;
        $imported += ks_run_stage($pdo, $stage, $defs[$stage][0], $defs[$stage][1]);
    }
    if ($countFile !== null) @file_put_contents($countFile, (string)$imported);
    return $imported;
}

function ks_run_sync(array $opts = []): int {
    $maxPackages = max(1, (int)($opts['max_packages'] ?? TED_MAX_PACKAGES_PER_RUN));
    $backfill    = max(1, (int)($opts['backfill'] ?? TED_INITIAL_BACKFILL));
    $doPrune     = (bool)($opts['prune'] ?? true);
    $doIub       = (bool)($opts['iub'] ?? true);
    $doLt        = (bool)($opts['lt'] ?? true);
    $skip        = array_map('strtolower', (array)($opts['skip'] ?? []));
    $only        = array_map('strtolower', (array)($opts['only'] ?? []));
    // --only=a,b = palaist tikai šos posmus (ērti dziļajai aizpildei pa vienam)
    $runStage    = fn(string $s): bool => !in_array($s, $skip, true) && ($only === [] || in_array($s, $only, true));
    $runTed      = $runStage('ted');
    $doIub       = $doIub && $runStage('iub');
    $doLt        = $doLt && $runStage('lt');
    if (konkursi_deep()) ks_log('🌊 DZIĻĀ AIZPILDE: logs ' . konkursi_deep_days() . ' dienas (KONKURSI_DEEP).');

    @mkdir(konkursi_tmp_dir(), 0775, true);
    @unlink(konkursi_stop_flag());

    $lock = ks_acquire_lock();
    if ($lock === null) {
        ks_log('⚠ Sinhronizācija jau darbojas — jauna netiek sākta.');
        return 0;
    }

    $t0 = microtime(true);
    $imported = 0;
    ks_state(['stage' => 'probe', 'status' => 'run', 'pid' => getmypid(), 'error' => '', 'imported' => 0, 'packages_done' => 0]);
    ks_log('🚀 TED sinhronizācija sākta (max ' . $maxPackages . ' paketes).');

    try {
        $pdo = konkursi_db();
        $year = (int)(new DateTimeImmutable('now', new DateTimeZone('Europe/Riga')))->format('Y');

        if ($runTed) { // TED posms (izlaižams ar --skip=ted / --only bez 'ted')

        $last = ks_last_imported_issue($pdo, $year);
        if ($last === 0) {
            // Pirmā palaišana šogad — atrod jaunāko un paņem tikai pēdējās $backfill paketes
            $latest = ks_find_latest_issue($year);
            if ($latest === 0) {
                ks_log('✗ Neizdevās atrast nevienu TED paketi (tīkls? gads tikko sācies?).');
                ks_state(['stage' => 'error', 'status' => 'error', 'error' => 'Nav atrasta neviena pakete']);
                return 0;
            }
            $next = max(1, $latest - $backfill + 1);
            ks_log("ℹ Pirmā palaišana: jaunākā pakete OJ S $latest, sāku no " . $next . '.');
        } else {
            $next = $last + 1;
            ks_log("ℹ Pēdējā importētā pakete: OJ S $last, turpinu no $next.");
        }

        $misses = 0;
        $done = 0;
        while ($done < $maxPackages && $misses < TED_PROBE_MISS_STOP) {
            if (ks_stop_requested()) { ks_log('🛑 STOP pieprasīts — pārtraucu.'); break; }

            $issueCode = ks_issue_code($year, $next);
            $url = ks_package_url($year, $next);
            ks_state(['stage' => 'download', 'current' => $issueCode, 'packages_done' => $done, 'imported' => $imported]);

            $status = ks_http_head($url);
            if ($status !== 200) {
                $misses++;
                ks_log("  · OJ S $next vēl nav publicēta (HTTP $status).");
                $next++;
                sleep(1);
                continue;
            }
            $misses = 0;

            $tarPath = konkursi_tmp_dir() . '/' . $issueCode . '.tar.gz';
            ks_log("⬇ Lejupielādēju TED paketi $issueCode ...");
            if (!ks_http_download($url, $tarPath)) {
                ks_log("  ✗ Lejupielāde neizdevās: $url");
                break;
            }
            ks_log('  ✓ Lejupielādēts (' . number_format(filesize($tarPath) / 1048576, 1) . ' MB). Importēju...');
            ks_state(['stage' => 'import', 'current' => $issueCode]);

            $c = ks_import_package($pdo, $tarPath, $year, $next);
            @unlink($tarPath);
            if ($c < 0) break; // STOP vai izvilkšanas kļūda
            $imported += $c;
            $done++;
            ks_log("  ✓ Pakete $issueCode → $c paziņojumi.");
            ks_state(['packages_done' => $done, 'imported' => $imported]);

            $next++;
            if ($done < $maxPackages) sleep(TED_REQUEST_DELAY_S);
        }

        // Dziļajā režīmā: vēsture atpakaļ līdz aktīvā loga sākumam
        if (konkursi_deep() && !ks_stop_requested()) {
            ks_state(['stage' => 'ted_back']);
            $imported += ks_sync_ted_back($pdo);
            ks_state(['imported' => $imported]);
        }

        } // $runTed beigas

        // ── IUB (LV nacionālie) posms — kļūda šeit nedrīkst apturēt visu sinhronizāciju ──
        if ($doIub && !ks_stop_requested()) {
            ks_state(['stage' => 'iub']);
            ks_log('🇱🇻 IUB nacionālo paziņojumu pārbaude...');
            try {
                $c = ks_sync_iub($pdo);
                $imported += $c;
                if ($c > 0) ks_log("  ✓ IUB kopā: $c paziņojumi.");
                ks_state(['imported' => $imported]);
            } catch (Throwable $e) {
                ks_log('  ⚠ IUB posma kļūda (turpinu): ' . $e->getMessage());
            }
        }

        // ── CVP IS (LT nacionālie) posms — kļūda šeit arī neaptur pārējo ──
        if ($doLt && !in_array('lt', $skip, true) && !ks_stop_requested()) {
            ks_state(['stage' => 'cvpis']);
            ks_log('🇱🇹 CVP IS nacionālo pirkumu pārbaude...');
            try {
                // Oficiālais API aizstāj CSV+HTML skrāpēšanu (ks_sync_cvpis) —
                // tas dod arī rezultātus, atceltos un sliekšņa karogu bez detaļu lapām.
                $c = ks_sync_cvpis_api($pdo);
                $imported += $c;
                ks_state(['imported' => $imported]);
            } catch (Throwable $e) {
                ks_log('  ⚠ CVP IS posma kļūda (turpinu): ' . $e->getMessage());
            }
        }

        // ── Pārējie nacionālie/starptautiskie posmi (katrs neatkarīgs) ──
        $extraStages = array_filter(ks_extra_stages(), $runStage, ARRAY_FILTER_USE_KEY);
        // Paralēlā ielāde: laiks šeit ir tīkla gaidīšana + per-host pieklājības
        // pauzes, tāpēc N strādnieki pret DAŽĀDIEM hostiem ir droši un dod ~N×.
        // Izslēgta manuālām --only palaišanām un dziļajai aizpildei (tur vajag
        // vienu prognozējamu procesu) un ja hostings liedz proc_open.
        // TIKAI CLI: web (FPM) kontekstā PHP_BINARY ir php-fpm, kas skriptus
        // nepalaiž — strādnieki klusi nomirtu un avoti paliktu neielādēti.
        $workers = min(ks_sync_workers(), count($extraStages));
        if ($workers > 1 && $only === [] && !konkursi_deep() && PHP_SAPI === 'cli'
                && function_exists('proc_open') && !ks_stop_requested()) {
            $imported += ks_run_stages_parallel($pdo, $extraStages, $workers);
            ks_state(['imported' => $imported]);
        } else {
            foreach ($extraStages as $stage => [$label, $fn]) {
                if (ks_stop_requested()) break;
                $imported += ks_run_stage($pdo, $stage, $label, $fn);
                ks_state(['imported' => $imported]);
            }
        }
        // Secība: vispirms pārliek grozījumus uz 'Aktīvie', tad novāc dublikātus
        // (tikko pārliktā rinda mēdz būt tā paša iepirkuma vecāka versija), un
        // tikai pēc tam prune — tam jāstrādā ar jau sakārtotām kategorijām.
        $moved = ks_recategorize_open($pdo);
        if ($moved > 0) ks_log("↪ $moved grozītu, bet vēl atvērtu konkursu → 'Aktīvie'.");
        $dedup = ks_dedupe_notices($pdo);
        if ($dedup['removed'] > 0) {
            ks_log("⧉ Novākti {$dedup['removed']} dublējoši iepirkumi ({$dedup['groups']} grupas).");
        }
        // Secības-neatkarīga TED↔nacionālo dedup pāreja: noķer dublikātus, ko
        // daļēja/--only=ted noķeršanās pievienoja pēc nacionālā avota dedup.
        $tedDup = ks_dedupe_all_vs_ted($pdo);
        if ($tedDup > 0) ks_log("⧉ Novākti $tedDup nacionālie ieraksti, kas dublējas ar TED.");
        // Glabāšanas politika: AKTĪVIE iepirkumi tiek KRĀTI (nemainīgais notice_versions
        // žurnāls + `notices` skats). Vēsturiskais fiziski izdzēsts, lai DB neaug bezgalīgi:
        //   ks_prune_archive — 'Rezultāti'/'Grozījumi'/'Cits' >90 d pēc publikācijas,
        //   ks_prune_tenders — beigušies iepirkumi (>14 d) + bez-termiņa (>90 d).
        // Abi dzēš arī žurnālu; droši, jo tik vecus ierakstus ievākšana tik un tā noraida.
        ks_state(['stage' => 'prune']);
        $prunedRes = ks_prune_archive($pdo);
        if ($prunedRes > 0) ks_log("🧹 Izdzēsti $prunedRes arhīva ieraksti (rezultāti/grozījumi/citi), vecāki par " . KONKURSI_KEEP_RESULTS_HARD_DAYS . " dienām.");
        $prunedTen = ks_prune_tenders($pdo);
        if ($prunedTen > 0) ks_log("🧹 Izdzēsti $prunedTen iepirkumi (beidzies termiņš >" . KONKURSI_KEEP_EXPIRED_DAYS . " d vai bez termiņa >" . KONKURSI_KEEP_NODEADLINE_DAYS . " d).");
        // Pilnā ks_prune() (iepirkumi+griesti) palikta manuālai/avārijas lietošanai.
        if ($doPrune && (getenv('KONKURSI_PRUNE') === '1')) {
            $pruned = ks_prune($pdo);
            if ($pruned > 0) ks_log("🧹 Notīrīti $pruned novecojuši ieraksti (KONKURSI_PRUNE=1).");
        }
        ks_refresh_watermarks($pdo);   // per-avots ūdenszīmes = jaunākais savāktais
        // Virsrakstu LV tulkošana (ja ieslēgta admin panelī; noklusējumā IZSLĒGTA).
        // Iet ar MAKSAS atslēgu (dienas griesti KONKURSI_TRANSLATE_PAID_DAILY_EUR) —
        // bezmaksas atslēgas mēģinājums izņemts 2026-08-04, sk. config.php piezīmi.
        if (konkursi_meta_get($pdo, 'translate_on_sync') === '1' && !ks_stop_requested()) {
            ks_state(['stage' => 'translate']);
            try {
                $tr = ks_translate_new_titles($pdo);
                if ($tr > 0) ks_log("🌐 Pārtulkoti $tr virsraksti uz latviešu valodu.");
            } catch (Throwable $e) {
                ks_log('  ⚠ Tulkošanas kļūda (turpinu): ' . $e->getMessage());
            }
        }
        ks_refresh_meta($pdo);

        // Starta momentuzņēmums (mazais fails lapas momentānai pirmajai zīmēšanai).
        // Jāpārbūvē KATRĀ sinhronizācijā — arī bez jauniem datiem displeja logi
        // slīd līdzi datumam. Kļūda nav kritiska: paliek iepriekšējais fails.
        try {
            require_once __DIR__ . '/snapshot.php';
            $snapPath = konkursi_snapshot_write($pdo);
            ks_log('⚡ Atjaunots starta momentuzņēmums (' . basename($snapPath) . ', ' . round(filesize($snapPath) / 1024) . ' KB).');
        } catch (Throwable $e) {
            ks_log('  ⚠ Momentuzņēmuma kļūda (turpinu): ' . $e->getMessage());
        }

        $dur = (int)round(microtime(true) - $t0);
        ks_state(['stage' => 'done', 'status' => 'ok', 'imported' => $imported, 'duration_s' => $dur]);
        ks_log("✅ Pabeigts: $imported paziņojumi, {$dur}s.");
    } catch (Throwable $e) {
        ks_state(['stage' => 'error', 'status' => 'error', 'error' => $e->getMessage()]);
        ks_log('✗ KĻŪDA: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    } finally {
        @unlink(konkursi_stop_flag());
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink(konkursi_lock_path());
    }
    return $imported;
}
