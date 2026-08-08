<?php
/**
 * konkursi_admin.php — SLĒPTAIS Konkursu (TED) sinhronizācijas panelis.
 * Piekļuve: /konkursi_admin.php?k=<token no admin_token.php> (tas pats, kas data_admin.php).
 *
 * Darbības: manuāla sinhronizācija fonā, STOP, cron slēdzis, statuss + žurnāls (AJAX).
 * Uzbūve apzināti atkārto data_admin.php principus (flock, PRG, fona palaišana ar setsid/nohup).
 */
declare(strict_types=1);

require_once __DIR__ . '/konkursi/lib/sync_engine.php';

// --- Autentifikācija ---
$TOKEN = require __DIR__ . '/admin_token.php';
$k = $_GET['k'] ?? $_POST['k'] ?? '';
if (!is_string($k) || !hash_equals($TOKEN, $k)) {
    http_response_code(404); // slēpjamies kā 404
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

@mkdir(konkursi_data_dir(), 0775, true);
$FLASH = konkursi_data_dir() . '/admin_flash.json';
$PHPBIN_FILE = __DIR__ . '/registrs/build_state/php_bin.txt'; // koplieto ar data_admin

function e_(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/** Vai funkcija ir izsaucama (eksistē UN nav disable_functions sarakstā)? */
function fn_ok(string $f): bool {
    if (!function_exists($f)) return false;
    static $disabled = null;
    if ($disabled === null) {
        $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
    }
    return !in_array(strtolower($f), $disabled, true);
}

function sh_arg(string $s): string {
    if (fn_ok('escapeshellarg')) return escapeshellarg($s);
    return "'" . str_replace("'", "'\\''", $s) . "'";
}

function php_bin(string $file): string {
    if (is_file($file)) {
        $b = trim((string)@file_get_contents($file));
        if ($b !== '') return $b;
    }
    $env = getenv('REG_PHP_BIN');
    return ($env !== false && $env !== '') ? $env : 'php';
}

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

/** Palaiž komandu FONĀ (izdzīvo pēc web pieprasījuma). */
function launch_background(string $cmd, string $log): array {
    $redir = ' >> ' . sh_arg($log) . ' 2>&1 < /dev/null &';
    $inner = 'if command -v setsid >/dev/null 2>&1; then setsid ' . $cmd . $redir
           . ' elif command -v nohup >/dev/null 2>&1; then nohup ' . $cmd . $redir
           . ' else ' . $cmd . $redir . ' fi';
    $sh = '/bin/sh -c ' . sh_arg($inner);

    if (fn_ok('proc_open')) {
        $p = @proc_open($sh, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            foreach ($pipes as $pp) { if (is_resource($pp)) @fclose($pp); }
            @proc_close($p);
            return ['proc_open', null];
        }
    }
    if (fn_ok('popen')) { $h = @popen($sh, 'r'); if ($h !== false) { @pclose($h); return ['popen', null]; } }
    if (fn_ok('exec')) { @exec($sh); return ['exec', null]; }
    if (fn_ok('shell_exec')) { @shell_exec($sh); return ['shell_exec', null]; }
    return [null, 'Visas fona-izpildes funkcijas (proc_open/popen/exec/shell_exec) ir liegtas.'];
}

/** DB kopsavilkums panelim. */
function konkursi_db_summary(): array {
    $path = konkursi_db_path();
    $sum = ['exists' => is_file($path), 'size' => 0, 'counts' => [], 'last_sync' => null, 'packages' => 0];
    if (!$sum['exists']) return $sum;
    $sum['size'] = (int)filesize($path);
    try {
        $pdo = konkursi_db();
        $sum['counts'] = json_decode(konkursi_meta_get($pdo, 'counts') ?? '{}', true) ?: [];
        $sum['last_sync'] = konkursi_meta_get($pdo, 'last_sync');
        $sum['packages'] = (int)$pdo->query('SELECT COUNT(*) FROM imported_files')->fetchColumn();
    } catch (Throwable $e) { /* rāda, cik var */ }
    return $sum;
}

/** Avotu ūdenszīmes + nemainīgā versiju žurnāla statistika panelim. */
function konkursi_versions_summary(): array {
    $out = ['sources' => [], 'versions_total' => 0, 'versions_ids' => 0, 'versioned' => 0];
    try {
        $pdo = konkursi_db();
        $nc = [];
        foreach ($pdo->query("SELECT source, COUNT(*) c FROM notices GROUP BY source") as $r) {
            $nc[$r['source']] = (int)$r['c'];
        }
        foreach ($pdo->query("SELECT source, watermark_date, last_run_at, last_new, last_versions, last_unchanged
                              FROM source_state ORDER BY source") as $r) {
            $r['notices'] = $nc[$r['source']] ?? 0;
            $out['sources'][] = $r;
        }
        $out['versions_total'] = (int)$pdo->query("SELECT COUNT(*) FROM notice_versions")->fetchColumn();
        $out['versions_ids']   = (int)$pdo->query("SELECT COUNT(DISTINCT id) FROM notice_versions")->fetchColumn();
        $out['versioned']      = (int)$pdo->query(
            "SELECT COUNT(*) FROM (SELECT id FROM notice_versions GROUP BY id HAVING MAX(version_no) > 1)"
        )->fetchColumn();
    } catch (Throwable $e) { /* rāda cik var */ }
    return $out;
}

// --- AJAX statuss ---
if (($_GET['ajax'] ?? '') === 'status') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    $state = is_file(konkursi_state_path())
        ? (json_decode((string)file_get_contents(konkursi_state_path()), true) ?: []) : [];
    $log = '';
    if (is_file(konkursi_log_path())) {
        $ls = file(konkursi_log_path(), FILE_IGNORE_NEW_LINES) ?: [];
        $log = implode("\n", array_reverse(array_slice($ls, -60)));
    }
    $sum = konkursi_db_summary();
    echo json_encode([
        'running'      => ks_is_running(),
        'stop_pending' => is_file(konkursi_stop_flag()),
        'stage'        => $state['stage'] ?? '—',
        'status'       => $state['status'] ?? '—',
        'updated'      => $state['updated'] ?? '—',
        'current'      => $state['current'] ?? '',
        'imported'     => $state['imported'] ?? 0,
        'error'        => (string)($state['error'] ?? ''),
        'db'           => $sum,
        'log'          => $log,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$msg = ''; $msg_type = 'info';
$just_launched = false;

// --- Darbības ---
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'run_sync') {
            if (ks_is_running()) {
                $msg = '⚠️ Sinhronizācija jau darbojas — jauna netika palaista.'; $msg_type = 'warn';
            } else {
                @unlink(konkursi_lock_path());
                $php = php_bin($PHPBIN_FILE);
                $script = __DIR__ . '/konkursi/bin/sync.php';
                $max = max(1, min(20, (int)($_POST['max_packages'] ?? TED_MAX_PACKAGES_PER_RUN)));
                $prefix = '';
                if (($v = run_capture('command -v nice')) !== null && trim($v) !== '') $prefix .= 'nice -n 10 ';
                $cmd = $prefix . sh_arg($php) . ' ' . sh_arg($script) . ' --max=' . $max;
                [$method, $err] = launch_background($cmd, konkursi_data_dir() . '/exec.log');
                if ($method !== null) {
                    $just_launched = true;
                    $msg = "✅ Sinhronizācija palaista fonā (metode: $method, līdz $max paketēm). Lapu vari droši aizvērt.";
                } else {
                    $msg = "❌ $err Izmanto cron vai CLI: php konkursi/bin/sync.php"; $msg_type = 'warn';
                }
            }
        } elseif ($action === 'stop_sync') {
            if (!ks_is_running()) {
                @unlink(konkursi_lock_path());
                @unlink(konkursi_stop_flag());
                $msg = 'ℹ️ Sinhronizācija nedarbojas. Novecojusī slēdzene (ja bija) notīrīta.';
            } elseif (!is_file(konkursi_stop_flag())) {
                @file_put_contents(konkursi_stop_flag(), date('Y-m-d H:i:s'));
                $msg = '🛑 STOP pieprasīts — process apstāsies tuvākajā pārbaudes punktā. Ja pēc minūtes vēl darbojas, spied STOP vēlreiz.';
            } else {
                $state = is_file(konkursi_state_path())
                    ? (json_decode((string)file_get_contents(konkursi_state_path()), true) ?: []) : [];
                $pid = (int)($state['pid'] ?? 0);
                if ($pid <= 1 && is_file(konkursi_lock_path())) $pid = (int)trim((string)@file_get_contents(konkursi_lock_path()));
                if ($pid > 1 && (fn_ok('shell_exec') || fn_ok('exec') || fn_ok('proc_open'))) {
                    run_capture('kill -TERM ' . $pid . ' 2>/dev/null; sleep 1; kill -KILL ' . $pid . ' 2>/dev/null');
                    @unlink(konkursi_lock_path());
                    @unlink(konkursi_stop_flag());
                    $msg = "🛑 Process (pid $pid) apturēts piespiedu kārtā.";
                } else {
                    $msg = '❌ Nevar apturēt procesu (nav pid vai exec liegts). STOP karogs paliek.'; $msg_type = 'warn';
                }
            }
        } elseif ($action === 'cron_on') {
            if (@file_put_contents(konkursi_cron_flag(), date('Y-m-d H:i:s')) === false) {
                throw new RuntimeException('Nevar rakstīt ' . konkursi_cron_flag() . ' — pārbaudi mapes tiesības.');
            }
            $msg = '✅ Konkursu cron sinhronizācija IESLĒGTA.';
        } elseif ($action === 'cron_off') {
            @unlink(konkursi_cron_flag());
            $msg = '✅ Konkursu cron sinhronizācija IZSLĒGTA.';
        } elseif ($action === 'translate_on') {
            konkursi_meta_set(konkursi_db(), 'translate_on_sync', '1');
            $msg = '✅ Virsrakstu tulkošana pie datu ielādes IESLĒGTA.';
        } elseif ($action === 'translate_off') {
            konkursi_meta_set(konkursi_db(), 'translate_on_sync', '0');
            $msg = '✅ Virsrakstu tulkošana pie datu ielādes IZSLĒGTA.';
        }
    }
} catch (Throwable $ex) {
    $msg = '❌ KĻŪDA: ' . $ex->getMessage() . ' @ ' . basename($ex->getFile()) . ':' . $ex->getLine();
    $msg_type = 'warn';
}

// --- POST/Redirect/GET ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @file_put_contents($FLASH, json_encode(['type' => $msg_type, 'msg' => $msg, 'just' => $just_launched], JSON_UNESCAPED_UNICODE));
    $base = strtok((string)$_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $base . '?k=' . urlencode($k), true, 303);
    exit;
}
if (is_file($FLASH)) {
    $fl = json_decode((string)@file_get_contents($FLASH), true);
    @unlink($FLASH);
    if (is_array($fl)) {
        $msg = (string)($fl['msg'] ?? '');
        $msg_type = (string)($fl['type'] ?? 'info');
        $just_launched = !empty($fl['just']);
    }
}

// --- Statuss ---
$cron_on = is_file(konkursi_cron_flag());
$running = ks_is_running() || $just_launched;
if (!$running && is_file(konkursi_lock_path())) @unlink(konkursi_lock_path());
$stop_pending = is_file(konkursi_stop_flag());
$state = is_file(konkursi_state_path())
    ? (json_decode((string)file_get_contents(konkursi_state_path()), true) ?: []) : [];
$log_tail = '';
if (is_file(konkursi_log_path())) {
    $lines = file(konkursi_log_path(), FILE_IGNORE_NEW_LINES) ?: [];
    $log_tail = implode("\n", array_reverse(array_slice($lines, -60)));
}
$sum = konkursi_db_summary();
$exec_can = fn_ok('popen') || fn_ok('exec') || fn_ok('shell_exec') || fn_ok('proc_open');
$counts_line = $sum['counts']
    ? implode(' · ', array_map(fn($k2, $v) => "$k2: " . number_format((float)$v, 0, '.', ' '), array_keys($sum['counts']), $sum['counts']))
    : '—';
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Konkursu datu pārvaldība</title>
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
.btn-on { background:#16a34a; color:#fff; }
.btn-off { background:#dc2626; color:#fff; }
.badge { display:inline-block; padding:4px 10px; border-radius:6px; font-size:13px; font-weight:700; }
.b-ok { background:#14532d; color:#86efac; } .b-bad { background:#450a0a; color:#fca5a5; } .b-run { background:#1e3a5f; color:#93c5fd; }
.muted { color:var(--muted); font-size:13px; }
pre { background:#0b1220; border:1px solid var(--border); border-radius:8px; padding:14px; font-size:12px; overflow-x:auto; max-height:420px; overflow-y:auto; white-space:pre-wrap; }
.msg { border-radius:8px; padding:12px 16px; margin-bottom:20px; font-weight:600; }
.msg.info { background:#1e3a5f; border:1px solid var(--accent); }
.msg.warn { background:#422006; border:1px solid #d97706; }
table.info td { padding:4px 12px 4px 0; font-size:13.5px; vertical-align:top; }
table.info td:first-child { color:var(--muted); white-space:nowrap; }
select, input[type=number] { background:#0b1220; border:1px solid var(--border); color:var(--text); padding:8px 10px; border-radius:6px; font-size:13px; }
code { background:#0b1220; padding:2px 6px; border-radius:4px; }
label { font-size:13px; color:var(--muted); display:flex; align-items:center; gap:6px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>🇪🇺 Konkursu (TED) datu pārvaldība</h1>

    <?php if ($msg !== ''): ?><div class="msg <?= $msg_type ?>"><?= e_($msg) ?></div><?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0;">Statuss</h3>
        <table class="info">
            <tr><td>Sinhronizācija šobrīd:</td><td><span id="sync-badge" class="badge <?= $running ? 'b-run' : 'b-ok' ?>"><?= $running ? ('DARBOJAS' . ($stop_pending ? ' (STOP…)' : '')) : 'brīvs' ?></span></td></tr>
            <tr><td>Pēdējais stāvoklis:</td><td><span id="sync-state-line"><?= e_(($state['stage'] ?? '—') . ' / ' . ($state['status'] ?? '—') . ' (' . ($state['updated'] ?? '—') . ')') ?></span>
                <?php if (!empty($state['error'])): ?><span class="badge b-bad"><?= e_((string)$state['error']) ?></span><?php endif; ?></td></tr>
            <tr><td>Datubāze:</td><td id="db-line"><?= $sum['exists'] ? number_format($sum['size'] / 1048576, 1) . ' MB · ' . e_($counts_line) : 'NAV IZVEIDOTA (izveidosies pirmajā sinhronizācijā)' ?></td></tr>
            <tr><td>Importētās TED paketes:</td><td id="pkg-line"><?= (int)$sum['packages'] ?></td></tr>
            <tr><td>Pēdējā sinhronizācija:</td><td id="sync-line"><?= e_((string)($sum['last_sync'] ?? '—')) ?></td></tr>
            <tr><td>Cron sinhronizācija:</td><td><?= $cron_on ? '<span class="badge b-ok">IESLĒGTA</span>' : '<span class="badge b-bad">IZSLĒGTA</span>' ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Manuālā sinhronizācija</h3>
        <form method="POST" class="row" style="margin-bottom:6px;">
            <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
            <input type="hidden" name="action" value="run_sync">
            <button type="submit" class="btn-run" <?= ($running || !$exec_can) ? 'disabled' : '' ?>>▶️ Sinhronizēt fonā</button>
            <label>maks. paketes: <input type="number" name="max_packages" value="<?= (int)TED_MAX_PACKAGES_PER_RUN ?>" min="1" max="20" style="width:70px;"></label>
        </form>
        <p class="muted">Viena TED pakete = viena publikācijas diena (~20 MB, ~3–4 tūkst. paziņojumu par visu ES).
            Process darbojas fonā atsevišķā procesā — lapu vari aizvērt. Pirmajā reizē tiks paņemtas tikai pēdējās <?= (int)TED_INITIAL_BACKFILL ?> paketes.</p>
        <?php if (!$exec_can): ?>
            <p class="muted" style="color:#fca5a5;">⚠️ Fona izpilde nav pieejama. Izmanto cron vai SSH: <code>php konkursi/bin/sync.php</code></p>
        <?php endif; ?>
        <?php if ($running): ?>
        <form method="POST" class="row" style="margin-top:12px;">
            <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
            <input type="hidden" name="action" value="stop_sync">
            <button type="submit" class="btn-off">🛑 STOP<?= $stop_pending ? ' (piespiedu apturēšana)' : '' ?></button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Cron sinhronizācija</h3>
        <form method="POST" class="row">
            <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
            <?php if ($cron_on): ?>
                <input type="hidden" name="action" value="cron_off"><button type="submit" class="btn-off">⏸ Izslēgt cron</button>
            <?php else: ?>
                <input type="hidden" name="action" value="cron_on"><button type="submit" class="btn-on">▶ Ieslēgt cron</button>
            <?php endif; ?>
        </form>
        <p class="muted">hPanel cron (ieteicams reizi dienā, piem. 10:15):<br>
            <code>15 10 * * * php <?= e_(__DIR__) ?>/konkursi/bin/cron_sync.php</code><br>
            Strādā tikai tad, ja slēdzis IESLĒGTS. TED publicē vienu paketi katrā darbadienā — biežāka palaišana nedod jaunus datus, tikai lieki noslogo serverus.</p>
    </div>

    <?php
    $translate_on = false; $paid_today = 0.0;
    try {
        $translate_on = konkursi_meta_get(konkursi_db(), 'translate_on_sync') === '1';
        $paid_today = (float)(konkursi_meta_get(konkursi_db(), 'translate_paid_spend_' . konkursi_today()) ?? '0');
    } catch (Throwable $e) { /* rāda OFF */ }
    ?>
    <div class="card">
        <h3 style="margin-top:0;">Virsrakstu tulkošana (latviešu valodā)</h3>
        <form method="POST" class="row">
            <input type="hidden" name="k" value="<?= e_($TOKEN) ?>">
            <?php if ($translate_on): ?>
                <input type="hidden" name="action" value="translate_off"><button type="submit" class="btn-off">⏸ Ielādēt BEZ tulkošanas</button>
                <span class="badge b-ok">IESLĒGTA</span>
            <?php else: ?>
                <input type="hidden" name="action" value="translate_on"><button type="submit" class="btn-on">▶ Ielādēt AR tulkošanu</button>
                <span class="badge b-bad">IZSLĒGTA</span>
            <?php endif; ?>
        </form>
        <p class="muted">Ja IESLĒGTA, katras sinhronizācijas beigās jaunie konkursu virsraksti tiek pārtulkoti
            uz latviešu valodu ar Gemini (<?= defined('REG_GEMINI_MODEL') ? e_(REG_GEMINI_MODEL) : 'gemini-3-flash-preview' ?>; API kods un atslēga — Reģistra sadaļā,
            <code>registrs/mi/gemini_client.php</code>). Limits <?= (int)KONKURSI_TRANSLATE_MAX_RUN ?> virsraksti/palaišanā.
            Tulko ar bezmaksas atslēgu; ja tā neatbild — ar maksas atslēgu līdz
            €<?= number_format(KONKURSI_TRANSLATE_PAID_DAILY_EUR, 2) ?> dienā
            (šodien iztērēts: <b>€<?= number_format($paid_today, 4) ?></b>).
            Vēsturisko virsrakstu vienreizējai aizpildei: <code>php konkursi/bin/translate_titles.php</code> (sausā palaišana rāda apjomu un izmaksas; <code>--apply</code> tulko).</p>
    </div>

    <?php $vsum = konkursi_versions_summary(); ?>
    <div class="card">
        <h3 style="margin-top:0;">Avotu ūdenszīmes un versiju žurnāls</h3>
        <p class="muted" style="margin-top:0;">
            Nemainīgais versiju žurnāls: <strong><?= number_format($vsum['versions_total']) ?></strong> versijas
            (<?= number_format($vsum['versions_ids']) ?> unikāli paziņojumi, no tiem
            <strong><?= number_format($vsum['versioned']) ?></strong> mainījušies ≥1 reizi).
            Ūdenszīme = jaunākais no avota savāktais publikācijas datums; nākamā ievākšana sākas no tās
            mīnus <?= (int)KONKURSI_OVERLAP_DAYS ?> dienu pārklājums.
        </p>
        <div style="overflow-x:auto;">
        <table class="info" style="width:100%;">
            <tr style="color:var(--muted);border-bottom:1px solid var(--border);">
                <td>Avots</td><td>Ūdenszīme</td><td>Pēdējā palaišana</td>
                <td style="text-align:right;">Ieraksti (skatā)</td></tr>
            <?php foreach ($vsum['sources'] as $s): ?>
            <tr>
                <td><strong><?= e_((string)$s['source']) ?></strong></td>
                <td><?= e_((string)($s['watermark_date'] ?? '—')) ?></td>
                <td class="muted"><?= e_($s['last_run_at'] ? substr((string)$s['last_run_at'], 0, 16) : '—') ?></td>
                <td style="text-align:right;"><?= number_format((int)$s['notices']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Žurnāls (pēdējās 60 rindas, jaunākās augšā)</h3>
        <pre id="sync-log"><?= $log_tail !== '' ? e_($log_tail) : 'Žurnāls vēl nav izveidots.' ?></pre>
    </div>
</div>
<script>
(function () {
    var TOKEN = <?= json_encode($TOKEN) ?>;
    var wasRunning = <?= $running ? 'true' : 'false' ?>;
    var badge = document.getElementById('sync-badge');
    var stateLine = document.getElementById('sync-state-line');
    var logEl = document.getElementById('sync-log');
    var dbLine = document.getElementById('db-line');
    var pkgLine = document.getElementById('pkg-line');
    var syncLine = document.getElementById('sync-line');
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
                    if (d.current) line += ' · pakete ' + d.current;
                    if (d.imported) line += ' · importēti ' + d.imported;
                    stateLine.textContent = line;
                }
                if (logEl && typeof d.log === 'string') {
                    logEl.textContent = d.log !== '' ? d.log : 'Žurnāls vēl nav izveidots.';
                }
                if (d.db && dbLine) {
                    var c = d.db.counts || {};
                    var parts = Object.keys(c).map(function (k2) { return k2 + ': ' + c[k2]; });
                    dbLine.textContent = d.db.exists
                        ? (d.db.size / 1048576).toFixed(1) + ' MB · ' + (parts.join(' · ') || '—')
                        : 'NAV IZVEIDOTA';
                    if (pkgLine) pkgLine.textContent = d.db.packages || 0;
                    if (syncLine) syncLine.textContent = d.db.last_sync || '—';
                }
                if (wasRunning && !d.running) {
                    setTimeout(function () { location.replace('?k=' + encodeURIComponent(TOKEN)); }, 900);
                    return;
                }
                wasRunning = d.running;
                schedule(d.running ? 4000 : 15000);
            })
            .catch(function () { schedule(4000); });
    }
    schedule(<?= $running ? 2000 : 15000 ?>);
})();
</script>
</body>
</html>
