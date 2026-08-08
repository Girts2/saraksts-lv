<?php
/**
 * data_admin.php — SLĒPTAIS datu pārvaldības panelis.
 * Piekļuve: /data_admin.php?k=<token no admin_token.php>
 */
declare(strict_types=1);

require_once __DIR__ . '/registrs/build/config.php';

// --- Autentifikācija ---
$TOKEN = require __DIR__ . '/admin_token.php';
$k = $_GET['k'] ?? $_POST['k'] ?? '';
if (!is_string($k) || !hash_equals($TOKEN, $k)) {
    http_response_code(404); // slēpjamies kā 404
    exit;
}

// Autentificētam adminam RĀDĀM kļūdas (citādi HTTP 500 bez detaļām).
error_reporting(E_ALL);
ini_set('display_errors', '1');

$ROOT = build_root();
$STATE_DIR = $ROOT . '/build_state';
$FLAG = $STATE_DIR . '/cron_enabled.flag';
$LOCK = $STATE_DIR . '/build.lock';
$LOG = $STATE_DIR . '/build.log';
$STATE = $STATE_DIR . '/build_state.json';
$PHPBIN_FILE = $STATE_DIR . '/php_bin.txt';
$EXEC_LOG = $STATE_DIR . '/exec.log';
$FLASH = $STATE_DIR . '/admin_flash.json'; // PRG "flash" ziņa (pārdzīvo redirect)
@mkdir($STATE_DIR, 0775, true);

/**
 * Būves progress (%) + cilvēklasāma etiķete pēc build_state.json 'stage'/'status'.
 * % ir aptuvens (pēc tipiskā posmu ilguma) — lejupielāde+konversija aizņem lielāko daļu.
 * @return array{percent:int,label:string,phase:string}
 */
function build_progress(array $state): array {
    $stage = (string)($state['stage'] ?? '');
    $status = (string)($state['status'] ?? '');
    $map = [
        'download' => [15, '1/5 · Lejupielāde no data.gov.lv'],
        'convert'  => [45, '2/5 · CSV → SQLite konversija'],
        'prepare'  => [68, '3/5 · Sagatave (meklēšana + NACE)'],
        'sections' => [82, '3.5 · Sadaļas (Nozare / Struktūra / Pensionārs)'],
        'swap'     => [92, '4/5 · Datubāzu atomiskā nomaiņa'],
        'sitemap'  => [96, '5/5 · Sitemap + gada-atskaišu izsekošana'],
        'done'     => [100, 'Pabeigts ✔'],
        'stopped'  => [0, 'Apturēts'],
        'error'    => [0, 'Kļūda'],
    ];
    if ($stage === 'done' || $status === 'ok') return ['percent' => 100, 'label' => 'Pabeigts ✔', 'phase' => 'done'];
    if ($stage === 'error' || $status === 'error') return ['percent' => 0, 'label' => 'Kļūda — skat. žurnālu', 'phase' => 'err'];
    if ($stage === 'stopped' || $status === 'stopped') return ['percent' => 0, 'label' => 'Apturēts', 'phase' => 'err'];
    if (isset($map[$stage])) return ['percent' => $map[$stage][0], 'label' => $map[$stage][1], 'phase' => 'run'];
    return ['percent' => 0, 'label' => 'Gaida sākumu', 'phase' => 'idle'];
}

// --- VIEGLAIS AJAX statusa gala punkts (aizstāj smago pilnas lapas meta-refresh) ---
// Būves laikā serveris ir noslogots; pilna lapas pārlāde ik 5 s mēdz iekrist 503.
// Šis atgriež tikai JSON (statuss + žurnāls), un JS 503 gadījumā vienkārši mēģina vēlreiz.
if (($_GET['ajax'] ?? '') === 'status') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    $st_running = build_is_running($LOCK);
    $st_state = is_file($STATE) ? (json_decode((string)file_get_contents($STATE), true) ?: []) : [];
    $st_log = '';
    if (is_file($LOG)) {
        $ls = file($LOG, FILE_IGNORE_NEW_LINES) ?: [];
        $st_log = implode("\n", array_reverse(array_slice($ls, -60)));
    }
    $st_db = is_file(getenv('UR_DB_PATH') ?: ($ROOT . '/csv/SQLite/ur_data.db'));
    $st_prog = build_progress($st_state);
    echo json_encode([
        'running' => $st_running,
        'stop_pending' => is_file(build_stop_flag()),
        'stage' => $st_state['stage'] ?? '—',
        'status' => $st_state['status'] ?? '—',
        'updated' => $st_state['updated'] ?? '—',
        'error' => (string)($st_state['error'] ?? ''),
        'duration_s' => (string)($st_state['duration_s'] ?? ''),
        'db_ok' => $st_db,
        'progress' => $st_prog['percent'],
        'progress_label' => $st_prog['label'],
        'progress_phase' => $st_prog['phase'],
        'log' => $st_log,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Palīgfunkcijas ---
/**
 * Vai būve TIEŠĀM darbojas? Pārbauda flock, nevis tikai faila esamību —
 * ja procesu nokāva (piem., LiteSpeed nokauj garu sinhrono pieprasījumu),
 * lock FAILS paliek, bet flock ir atbrīvots -> novecojis lock, nevis dzīvs process.
 */
function build_is_running(string $lock): bool {
    if (!is_file($lock)) return false;
    $fp = @fopen($lock, 'r');
    if ($fp === false) return true; // nevar pārbaudīt — pieņem, ka strādā
    $free = flock($fp, LOCK_EX | LOCK_NB);
    if ($free) { flock($fp, LOCK_UN); fclose($fp); return false; } // neviens netur -> miris
    fclose($fp);
    return true;
}

/** Vai funkcija ir izsaucama (eksistē UN nav disable_functions sarakstā)? */
function fn_ok(string $f): bool {
    if (!function_exists($f)) return false;
    static $disabled = null;
    if ($disabled === null) {
        $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
    }
    return !in_array(strtolower($f), $disabled, true);
}

/** PHP CLI bināra ceļš (saglabāts fails > vide > 'php'). */
function php_bin(string $file): string {
    if (is_file($file)) {
        $b = trim((string)@file_get_contents($file));
        if ($b !== '') return $b;
    }
    $env = getenv('REG_PHP_BIN');
    return ($env !== false && $env !== '') ? $env : 'php';
}

/** Droša shell-argumenta citēšana (ar escapeshellarg fallback, ja tas liegts). */
function sh_arg(string $s): string {
    if (fn_ok('escapeshellarg')) return escapeshellarg($s);
    return "'" . str_replace("'", "'\\''", $s) . "'";
}

/** Palaiž komandu SINHRONI (gaida) ar pieejamo metodi; atgriež stdout+stderr vai null. */
function run_capture(string $cmd): ?string {
    if (fn_ok('shell_exec')) return @shell_exec($cmd . ' 2>&1');
    if (fn_ok('exec')) { $o = []; @exec($cmd . ' 2>&1', $o); return implode("\n", $o); }
    if (fn_ok('proc_open')) {
        $p = @proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            @fclose($pipes[0]);
            $out = (string)@stream_get_contents($pipes[1]) . (string)@stream_get_contents($pipes[2]);
            @fclose($pipes[1]); @fclose($pipes[2]);
            @proc_close($p);
            return $out;
        }
    }
    return null;
}

/**
 * Palaiž komandu FONĀ (atdalīti, izdzīvo pēc web pieprasījuma) ar pieejamo metodi.
 * setsid/nohup nodrošina, ka LiteSpeed nenogalina procesu, kad pieprasījums beidzas.
 */
function launch_background(string $cmd, string $log): array {
    $redir = ' >> ' . sh_arg($log) . ' 2>&1 < /dev/null &';
    // Ja pieejams setsid vai nohup — izmanto (atdalīšana); citādi vienkārši &.
    $inner = 'if command -v setsid >/dev/null 2>&1; then setsid ' . $cmd . $redir
           . ' elif command -v nohup >/dev/null 2>&1; then nohup ' . $cmd . $redir
           . ' else ' . $cmd . $redir . ' fi';
    $sh = '/bin/sh -c ' . sh_arg($inner);

    if (fn_ok('proc_open')) {
        $p = @proc_open($sh, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            foreach ($pipes as $pp) { if (is_resource($pp)) @fclose($pp); }
            @proc_close($p); // gaida SH (kas tūlīt iziet, jo & atdalīja bērnu)
            return ['proc_open', null];
        }
    }
    if (fn_ok('popen')) { $h = @popen($sh, 'r'); if ($h !== false) { @pclose($h); return ['popen', null]; } }
    if (fn_ok('exec')) { @exec($sh); return ['exec', null]; }
    if (fn_ok('shell_exec')) { @shell_exec($sh); return ['shell_exec', null]; }
    return [null, 'Visas fona-izpildes funkcijas (proc_open/popen/exec/shell_exec) ir liegtas.'];
}

/** Automātiski atrod strādājošu PHP CLI bināru. */
function detect_php_bin(): ?string {
    $cands = [];
    if (defined('PHP_BINARY') && PHP_BINARY) $cands[] = PHP_BINARY;
    $cands = array_merge($cands, [
        'php', '/usr/bin/php', '/usr/local/bin/php', '/usr/bin/php-cli',
        '/opt/alt/php83/usr/bin/php', '/opt/alt/php82/usr/bin/php', '/opt/alt/php81/usr/bin/php',
        '/opt/cpanel/ea-php83/root/usr/bin/php', '/opt/cpanel/ea-php81/root/usr/bin/php',
    ]);
    foreach (array_unique($cands) as $c) {
        $v = run_capture(sh_arg($c) . ' -v');
        if ($v && stripos($v, 'PHP ') !== false && stripos($v, 'cli') !== false) return $c;
    }
    return null;
}

/**
 * Būvēšanas komandas env priedēklis (nodod web konteksta konfigurāciju CLI procesam).
 * SVARĪGI: lieto 'env VAR=val ...', nevis tikai 'VAR=val ...' — jo komandu ietin
 * nohup/setsid/nice, un tie NEinterpretē VAR=val sintaksi (mēģinātu palaist "VAR=val"
 * kā komandu → "No such file or directory"). 'env' to izdara korekti.
 */
function build_env_prefix(): string {
    $parts = [];
    foreach (['REG_DATA_DIR', 'UR_DB_PATH', 'REG_SEARCH_DIR', 'REG_HISTORY_DB'] as $key) {
        $v = getenv($key);
        if ($v !== false && $v !== '') $parts[] = $key . '=' . sh_arg($v);
    }
    return empty($parts) ? '' : 'env ' . implode(' ', $parts) . ' ';
}

$msg = ''; $msg_type = 'info';
$just_launched = false; // tikko palaista fona būve — UI uzreiz rāda DARBOJAS

// --- Darbības (try/catch, lai kļūda parādās, nevis 500) ---
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $skip = !empty($_POST['skip_download']);

        if ($action === 'run_build') {
            if (build_is_running($LOCK)) {
                $msg = '⚠️ Būvēšana jau darbojas — jauna netika palaista.'; $msg_type = 'warn';
            } else {
                @unlink($LOCK); // notīra novecojušu lock (nomiris process)
                $php = php_bin($PHPBIN_FILE);
                $script = __DIR__ . '/registrs/build/build_all.php';
                // Pazemināta prioritāte (nice + ionice), lai būve neizspiestu web procesus → 503 novēršana.
                $prefix = '';
                if (($v = run_capture('command -v nice')) !== null && trim($v) !== '') $prefix .= 'nice -n 10 ';
                if (($v = run_capture('command -v ionice')) !== null && trim($v) !== '') $prefix .= 'ionice -c2 -n7 ';
                $cmd = build_env_prefix() . $prefix . sh_arg($php) . ' ' . sh_arg($script) . ($skip ? ' --skip-download' : '');
                [$method, $err] = launch_background($cmd, $EXEC_LOG);
                if ($method !== null) {
                    $just_launched = true; // UI uzreiz rāda DARBOJAS (fona process lock izveido ar nelielu aizturi)
                    $msg = "✅ Būvēšana palaista fonā (metode: $method)." . ($skip ? ' Lejupielāde izlaista.' : '')
                         . ' Šo lapu vari droši aizvērt — process turpinās serverī.';
                } else {
                    $msg = "❌ $err Izmanto cron (skat. zemāk). (exec.log: " . e_short(@file_get_contents($EXEC_LOG)) . ")";
                    $msg_type = 'warn';
                }
            }
        } elseif ($action === 'stop_build') {
            if (!build_is_running($LOCK)) {
                @unlink($LOCK);
                @unlink(build_stop_flag());
                $msg = 'ℹ️ Būvēšana nedarbojas. Novecojis lock (ja bija) notīrīts.';
            } elseif (!is_file(build_stop_flag())) {
                // 1. klikšķis — saudzīga apturēšana: būve apstājas tuvākajā pārbaudes punktā
                @file_put_contents(build_stop_flag(), date('Y-m-d H:i:s'));
                $msg = '🛑 STOP pieprasīts — būve apstāsies tuvākajā pārbaudes punktā (parasti dažu sekunžu laikā). '
                     . 'Ja pēc minūtes vēl darbojas, spied STOP vēlreiz — tad process tiks nokauts piespiedu kārtā.';
            } else {
                // 2. klikšķis — piespiedu nokaušana (kill TERM pēc pid)
                $st = is_file($STATE) ? (json_decode((string)file_get_contents($STATE), true) ?: []) : [];
                $pid = (int)($st['pid'] ?? 0);
                if ($pid <= 1 && is_file($LOCK)) $pid = (int)trim((string)@file_get_contents($LOCK));
                if ($pid > 1 && (fn_ok('shell_exec') || fn_ok('exec') || fn_ok('proc_open'))) {
                    run_capture('kill -TERM ' . $pid . ' 2>/dev/null; sleep 1; kill -KILL ' . $pid . ' 2>/dev/null');
                    @unlink($LOCK);
                    @unlink(build_stop_flag());
                    $msg = "🛑 Process (pid $pid) nokauts piespiedu kārtā. Lock notīrīts — vari palaist no jauna.";
                } else {
                    $msg = '❌ Nevar nokaut procesu (nav pid vai exec liegts). STOP karogs paliek — būve apstāsies pati.';
                    $msg_type = 'warn';
                }
            }
        } elseif ($action === 'cron_on') {
            if (@file_put_contents($FLAG, date('Y-m-d H:i:s')) === false) {
                throw new RuntimeException("Nevar rakstīt $FLAG — pārbaudi mapes tiesības (build_state/).");
            }
            $msg = '✅ Cron ieplānotā lejupielāde IESLĒGTA.';
        } elseif ($action === 'cron_off') {
            @unlink($FLAG);
            $msg = '✅ Cron ieplānotā lejupielāde IZSLĒGTA.';
        } elseif ($action === 'set_php_bin') {
            $bin = trim((string)($_POST['php_bin'] ?? ''));
            if ($bin === '') { @unlink($PHPBIN_FILE); $msg = 'PHP bināra ceļš notīrīts (izmanto "php").'; }
            else { @file_put_contents($PHPBIN_FILE, $bin); $msg = "PHP bināra ceļš saglabāts: $bin"; }
        } elseif ($action === 'detect_php') {
            $found = detect_php_bin();
            if ($found !== null) {
                @file_put_contents($PHPBIN_FILE, $found);
                $msg = "✅ Atrasts un saglabāts PHP CLI: $found";
            } else {
                $msg = '❌ Neizdevās automātiski atrast PHP CLI. Ieraksti ceļu manuāli (hPanel → PHP informācija).';
                $msg_type = 'warn';
            }
        }
    }
} catch (Throwable $ex) {
    $msg = '❌ KĻŪDA: ' . $ex->getMessage() . ' @ ' . basename($ex->getFile()) . ':' . $ex->getLine();
    $msg_type = 'warn';
}

// --- POST/Redirect/GET (PRG) ---
// KRITISKI: pēc POST apstrādes NOVIRZĀM uz tīru GET URL. Bez tā lapas pārlāde (F5 vai
// JS location.reload) ATKĀRTOTU POST ar action=run_build → būve palaistos atkal un atkal
// (1.→5. posms, tad atkal 1. posms — bezgalīgs cikls). Redirect padara pārlādi drošu.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @file_put_contents($FLASH, json_encode(['type' => $msg_type, 'msg' => $msg, 'just' => $just_launched], JSON_UNESCAPED_UNICODE));
    $base = strtok((string)$_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $base . '?k=' . urlencode($k), true, 303); // 303 = See Other (GET)
    exit;
}

// GET: paņemam un notīrām flash ziņu (parādās vienreiz pēc redirect).
if (is_file($FLASH)) {
    $fl = json_decode((string)@file_get_contents($FLASH), true);
    @unlink($FLASH);
    if (is_array($fl)) {
        $msg = (string)($fl['msg'] ?? '');
        $msg_type = (string)($fl['type'] ?? 'info');
        $just_launched = !empty($fl['just']);
    }
}

function e_short($s): string { $s = trim((string)$s); return $s === '' ? '(tukšs)' : substr($s, -300); }

// --- Statuss ---
$cron_on = is_file($FLAG);
$running = build_is_running($LOCK) || $just_launched; // tikko palaistu rādām kā DARBOJAS
$stale_cleaned = false;
if (!$running && is_file($LOCK)) {
    // Lock fails palicis no nokauta procesa — notīra, lai poga nepaliek bloķēta mūžīgi
    @unlink($LOCK);
    $stale_cleaned = true;
}
$stop_pending = is_file(build_stop_flag());
$state = is_file($STATE) ? (json_decode((string)file_get_contents($STATE), true) ?: []) : [];
$log_tail = '';
if (is_file($LOG)) {
    $lines = file($LOG, FILE_IGNORE_NEW_LINES) ?: [];
    // Jaunākie ieraksti AUGŠĀ (pēdējās 60 rindas apgrieztā secībā)
    $log_tail = implode("\n", array_reverse(array_slice($lines, -60)));
}

// --- Diagnostika ---
$php_bin_cur = php_bin($PHPBIN_FILE);
$live_db = getenv('UR_DB_PATH') ?: ($ROOT . '/csv/SQLite/ur_data.db');
$diag = [
    'PHP versija' => PHP_VERSION,
    'SAPI' => PHP_SAPI,
    'disable_functions' => (string)ini_get('disable_functions') ?: '(nav)',
    'popen' => fn_ok('popen') ? '✅' : '❌',
    'exec' => fn_ok('exec') ? '✅' : '❌',
    'shell_exec' => fn_ok('shell_exec') ? '✅' : '❌',
    'proc_open' => fn_ok('proc_open') ? '✅' : '❌',
    'set_time_limit' => fn_ok('set_time_limit') ? '✅' : '❌',
    'curl' => function_exists('curl_init') ? '✅' : '❌',
    'pdo_sqlite' => extension_loaded('pdo_sqlite') ? '✅' : '❌',
    'PHP CLI bināra (config)' => $php_bin_cur,
    'build_state rakstāms' => is_writable($STATE_DIR) ? '✅ ' . $STATE_DIR : '❌ ' . $STATE_DIR,
    'REG_DATA_DIR' => (getenv('REG_DATA_DIR') ?: '(noklusējums) ' . $ROOT),
    'Dzīvā ur_data.db' => (is_file($live_db) ? '✅ ' : '❌ NAV: ') . $live_db,
];
// PHP bināra tests (izmanto run_capture → strādā arī tikai ar proc_open)
$bin_v = run_capture(sh_arg($php_bin_cur) . ' -v');
$bin_test = ($bin_v !== null && trim($bin_v) !== '')
    ? substr(trim($bin_v), 0, 60)
    : '(bez izvades — nepareizs ceļš? Spied "Automātiski atrast PHP")';
$diag['PHP CLI tests (bin -v)'] = $bin_test;

$db_info = is_file($live_db)
    ? number_format((float)filesize($live_db), 0, '.', ' ') . ' b, ' . date('Y-m-d H:i', (int)filemtime($live_db))
    : 'NAV ATRASTA';

function e_(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$exec_can = fn_ok('popen') || fn_ok('exec') || fn_ok('shell_exec') || fn_ok('proc_open');
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<?php /* Pilnas lapas meta-refresh NOŅEMTS — būves laikā tas iekrita 503.
        Statusu tagad atsvaidzina viegls JS AJAX (?ajax=status), kas 503 vienkārši ignorē un mēģina vēlreiz. */ ?>
<title>Datu pārvaldība</title>
<style>
:root { --bg:#0f172a; --panel:#1e293b; --text:#f8fafc; --muted:#94a3b8; --border:#334155; --accent:#3b82f6; }
body { margin:0; padding:20px; font-family:ui-sans-serif,system-ui,sans-serif; background:var(--bg); color:var(--text); }
.wrap { max-width:900px; margin:0 auto; }
h1 { font-size:22px; border-bottom:2px solid var(--border); padding-bottom:14px; }
.card { background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:20px; }
.row { display:flex; gap:14px; flex-wrap:wrap; align-items:center; }
button { border:none; border-radius:8px; padding:12px 18px; font-weight:700; font-size:14px; cursor:pointer; }
.btn-run { background:var(--accent); color:#fff; }
.btn-run:disabled { background:#475569; cursor:not-allowed; }
.btn-sync { background:#7c3aed; color:#fff; }
.btn-on { background:#16a34a; color:#fff; }
.btn-off { background:#dc2626; color:#fff; }
.btn-sm { background:#334155; color:#fff; padding:8px 12px; font-size:13px; }
.badge { display:inline-block; padding:4px 10px; border-radius:6px; font-size:13px; font-weight:700; }
.b-ok { background:#14532d; color:#86efac; } .b-bad { background:#450a0a; color:#fca5a5; } .b-run { background:#1e3a5f; color:#93c5fd; }
.muted { color:var(--muted); font-size:13px; }
pre { background:#0b1220; border:1px solid var(--border); border-radius:8px; padding:14px; font-size:12px; overflow-x:auto; max-height:420px; overflow-y:auto; white-space:pre-wrap; }
.progress-wrap { background:#0b1220; border:1px solid var(--border); border-radius:8px; height:24px; overflow:hidden; position:relative; margin-bottom:12px; }
.progress-fill { height:100%; width:0; transition:width .6s ease; background:linear-gradient(90deg,#2563eb,#3b82f6); }
.progress-fill.done { background:linear-gradient(90deg,#16a34a,#22c55e); }
.progress-fill.err  { background:linear-gradient(90deg,#b91c1c,#ef4444); }
.progress-fill.run  { background-image:linear-gradient(90deg,#2563eb,#3b82f6,#2563eb); background-size:200% 100%; animation:progpulse 2s linear infinite; }
@keyframes progpulse { 0%{background-position:0 0;} 100%{background-position:-200% 0;} }
.progress-label { position:absolute; inset:0; line-height:24px; text-align:center; font-size:12px; font-weight:700; color:#eaf2ff; text-shadow:0 1px 2px rgba(0,0,0,.7); }
.msg { border-radius:8px; padding:12px 16px; margin-bottom:20px; font-weight:600; }
.msg.info { background:#1e3a5f; border:1px solid var(--accent); }
.msg.warn { background:#422006; border:1px solid #d97706; }
label { font-size:13px; color:var(--muted); display:flex; align-items:center; gap:6px; }
table.info td { padding:4px 12px 4px 0; font-size:13.5px; vertical-align:top; }
table.info td:first-child { color:var(--muted); white-space:nowrap; }
input[type=text] { background:#0b1220; border:1px solid var(--border); color:var(--text); padding:8px 10px; border-radius:6px; font-size:13px; min-width:280px; }
code { background:#0b1220; padding:2px 6px; border-radius:4px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>🗄️ Datu pārvaldība (saraksts.lv)</h1>

    <?php if ($msg !== ''): ?><div class="msg <?= $msg_type ?>"><?= e_($msg) ?></div><?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0;">Statuss</h3>
        <table class="info">
            <tr><td>Būvēšana šobrīd:</td><td><span id="build-badge" class="badge <?= $running ? 'b-run' : 'b-ok' ?>"><?= $running ? ('DARBOJAS' . ($stop_pending ? ' (STOP…)' : '')) : 'brīvs' ?></span>
                <?php if ($stale_cleaned): ?><span class="badge b-bad">iepriekšējais process bija nomiris — novecojušais lock notīrīts</span><?php endif; ?></td></tr>
            <tr><td>Pēdējais stāvoklis:</td><td><span id="build-state-line"><?= e_(($state['stage'] ?? '—') . ' / ' . ($state['status'] ?? '—') . ' (' . ($state['updated'] ?? '—') . ')' . (!empty($state['duration_s']) ? '  ilgums ' . $state['duration_s'] . 's' : '')) ?></span>
                <?php if (!empty($state['error'])): ?><span class="badge b-bad"><?= e_((string)$state['error']) ?></span><?php endif; ?></td></tr>
            <tr><td>Dzīvā ur_data.db:</td><td><?= e_($db_info) ?></td></tr>
            <tr><td>Cron ieplānotā lejupielāde:</td><td><?= $cron_on ? '<span class="badge b-ok">IESLĒGTA</span>' : '<span class="badge b-bad">IZSLĒGTA</span>' ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Manuālā datu ielāde</h3>
        <form method="POST" class="row" style="margin-bottom:6px;">
            <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
            <input type="hidden" name="action" value="run_build">
            <button type="submit" class="btn-run" <?= ($running || !$exec_can) ? 'disabled' : '' ?>>▶️ Palaist fonā</button>
            <label><input type="checkbox" name="skip_download" value="1"> izlaist lejupielādi</label>
        </form>
        <p class="muted">Būve darbojas serverī atsevišķā (pazeminātas prioritātes) procesā — šo lapu vari droši aizvērt. Statuss un žurnāls zemāk atjauninās paši.</p>
        <?php if (!$exec_can): ?>
            <p class="muted" style="color:#fca5a5;">⚠️ Fona izpilde nav pieejama (skat. diagnostiku). Izmanto cron (skat. zemāk).</p>
        <?php endif; ?>
        <?php if ($running): ?>
        <form method="POST" class="row" style="margin-top:12px;">
            <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
            <input type="hidden" name="action" value="stop_build">
            <button type="submit" class="btn-off">🛑 STOP<?= $stop_pending ? ' (piespiedu nokaušana)' : '' ?></button>
            <span class="muted"><?= $stop_pending ? 'STOP jau pieprasīts — atkārtots klikšķis nokauj procesu piespiedu kārtā.' : 'aptur būvi tuvākajā pārbaudes punktā (dažas sekundes)' ?></span>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Cron ieplānotā lejupielāde</h3>
        <form method="POST" class="row">
            <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
            <?php if ($cron_on): ?>
                <input type="hidden" name="action" value="cron_off"><button type="submit" class="btn-off">⏸ Izslēgt cron</button>
            <?php else: ?>
                <input type="hidden" name="action" value="cron_on"><button type="submit" class="btn-on">▶ Ieslēgt cron</button>
            <?php endif; ?>
        </form>
        <p class="muted">hPanel cron izsauc <code>php build/cron_build.php</code>; strādā tikai ja slēdzis IESLĒGTS. Cron NEIZMANTO web exec, tāpēc strādā arī ja fona poga liegta.</p>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">🔧 Diagnostika</h3>
        <table class="info">
            <?php foreach ($diag as $key => $val): ?>
            <tr><td><?= e_($key) ?>:</td><td><?= e_((string)$val) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <div class="row" style="margin-top:14px;">
            <form method="POST" class="row" style="gap:8px;">
                <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
                <input type="hidden" name="action" value="set_php_bin">
                <input type="text" name="php_bin" placeholder="PHP CLI ceļš, piem. /usr/bin/php" value="<?= is_file($PHPBIN_FILE) ? e_($php_bin_cur) : '' ?>">
                <button type="submit" class="btn-sm">Saglabāt</button>
            </form>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
                <input type="hidden" name="action" value="detect_php">
                <button type="submit" class="btn-sm">🔍 Automātiski atrast PHP</button>
            </form>
        </div>
        <p class="muted">Ja fona poga nedod rezultātu, iestati pareizo PHP CLI ceļu (hPanel → parasti <code>/usr/bin/php</code> vai versijas ceļš) un pārbaudi "PHP CLI tests" rindu augšā.</p>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Būvēšanas žurnāls (pēdējās 60 rindas, jaunākās augšā)</h3>
        <?php $prog = build_progress($state); ?>
        <div class="progress-wrap">
            <div id="build-progress-fill" class="progress-fill <?= e_($running ? 'run' : $prog['phase']) ?>" style="width:<?= (int)$prog['percent'] ?>%"></div>
            <div id="build-progress-label" class="progress-label"><?= e_($prog['label'] . ' · ' . $prog['percent'] . '%') ?></div>
        </div>
        <pre id="build-log"><?= $log_tail !== '' ? e_($log_tail) : 'Žurnāls vēl nav izveidots.' ?></pre>
    </div>
</div>
<script>
(function () {
    // Viegla statusa aptauja (aizstāj pilnas lapas meta-refresh). Būves laikā serveris ir
    // noslogots un pilna pārlāde mēdz iekrist 503 — šeit 503/tīkla kļūme vienkārši tiek ignorēta
    // un aptauja mēģina vēlreiz, tāpēc lietotājs kļūdas lapu neredz.
    var TOKEN = <?= json_encode($TOKEN) ?>;
    var wasRunning = <?= $running ? 'true' : 'false' ?>;
    var badge = document.getElementById('build-badge');
    var stateLine = document.getElementById('build-state-line');
    var logEl = document.getElementById('build-log');
    var progFill = document.getElementById('build-progress-fill');
    var progLabel = document.getElementById('build-progress-label');
    var timer = null;
    if (!badge) return;

    function schedule(ms) { clearTimeout(timer); timer = setTimeout(poll, ms); }

    function poll() {
        fetch('?ajax=status&k=' + encodeURIComponent(TOKEN), { cache: 'no-store' })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (d) {
                if (d.running) {
                    badge.className = 'badge b-run';
                    badge.textContent = 'DARBOJAS' + (d.stop_pending ? ' (STOP…)' : '');
                } else {
                    badge.className = 'badge b-ok';
                    badge.textContent = 'brīvs';
                }
                if (stateLine) {
                    var line = (d.stage || '—') + ' / ' + (d.status || '—') + ' (' + (d.updated || '—') + ')';
                    if (d.duration_s) line += '  ilgums ' + d.duration_s + 's';
                    stateLine.textContent = line;
                }
                if (logEl && typeof d.log === 'string') {
                    logEl.textContent = d.log !== '' ? d.log : 'Žurnāls vēl nav izveidots.';
                }
                // Progress bar
                if (progFill && typeof d.progress === 'number') {
                    progFill.style.width = d.progress + '%';
                    var phase = d.running ? 'run' : (d.progress_phase || 'idle');
                    progFill.className = 'progress-fill ' + phase;
                }
                if (progLabel) progLabel.textContent = (d.progress_label || '') + ' · ' + (d.progress || 0) + '%';
                // Būve tikko pabeidzās — pāriet uz TĪRU GET URL (nevis reload, kas atkārtotu POST).
                if (wasRunning && !d.running) {
                    setTimeout(function () {
                        location.replace('?k=' + encodeURIComponent(TOKEN));
                    }, 900);
                    return;
                }
                wasRunning = d.running;
                schedule(d.running ? 4000 : 15000); // darbojas → biežāk; dīkstāvē → retāk
            })
            .catch(function () {
                // 503 / īslaicīga servera slodze būves laikā — NErāda kļūdu, tikai mēģina vēlreiz.
                schedule(4000);
            });
    }
    // Pēc "Palaist fonā" fona process lock izveido ar nelielu aizturi — sākam ātri, lai to notveram.
    schedule(<?= $running ? 2000 : 15000 ?>);
})();
</script>
</body>
</html>
