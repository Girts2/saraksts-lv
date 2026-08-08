<?php
/**
 * admin.php — VIENOTAIS administratīvais panelis visām sadaļām.
 *
 * Piekļuve: /admin.php?k=<token no admin_token.php> (tas pats token, kas
 * data_admin.php un konkursi_admin.php; nepareizs → 404, lai panelis neatklātos).
 *
 * KO DARA:
 *   · palaiž jebkuras sadaļas datu būvi/kodu — visu reizē, pa sadaļām vai pa daļām
 *   · pārvalda cron grafiku katram darbam (izpilda cron/dispatch.php)
 *   · rāda kopīgo notikumu žurnālu (log/YYYY-MM-DD.log)
 *
 * KO NEDARA: neaizstāj esošos dziļos paneļus. data_admin.php (būves progress, STOP,
 * PHP bināra atrašana) un konkursi_admin.php (sinhronizācijas detaļas, tulkošanas
 * slēdzis) paliek — šis uz tiem ved. Tā vietā, lai pārrakstītu nostrādājušu kodu.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_jobs.php';
require_once __DIR__ . '/lib/applog.php';

// --- Autentifikācija ---
$TOKEN = require __DIR__ . '/admin_token.php';
$k = $_GET['k'] ?? $_POST['k'] ?? '';
if (!is_string($k) || !hash_equals($TOKEN, $k)) {
    // Nesekmīgs mēģinājums ir drošības notikums — tas žurnālā ir vienmēr.
    applog_boot('admin');
    applog_event('WARN', 'admin', 'piekluve-liegta',
        'nederīgs token, tīkls=' . applog_anon_ip(applog_client_ip()), true);
    http_response_code(404);
    exit;
}

applog_boot('admin');
error_reporting(E_ALL);
ini_set('display_errors', '1');

@mkdir(admin_state_dir(), 0775, true);
@mkdir(applog_dir(), 0775, true);

$SECTIONS = admin_sections();
$ALL      = admin_all_jobs();
$SCHED    = admin_schedule_load();

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function human_bytes(int $b): string {
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B');
}

// ── AJAX: darbu statuss (panelis atsvaidzina bez pilnas pārlādes) ───────────
if (($_GET['ajax'] ?? '') === 'status') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $out = [];
    foreach ($ALL as $id => $job) {
        $last = admin_job_last($job);
        $out[$id] = [
            'running' => admin_job_running($job),
            'when'    => $last['when'],
            'tail'    => $last['tail'],
        ];
    }
    echo json_encode(['jobs' => $out, 'time' => date('H:i:s')], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX: žurnāla saturs ───────────────────────────────────────────────────
if (($_GET['ajax'] ?? '') === 'log') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    $day = (string)($_GET['day'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
    $f = applog_file($day);
    echo is_file($f) ? (string)@file_get_contents($f) : "(šai dienai ierakstu nav)";
    exit;
}

// ── Darbības ───────────────────────────────────────────────────────────────
$msg = ''; $msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'run') {
        $ids = array_values(array_filter((array)($_POST['jobs'] ?? []),
            fn($id) => is_string($id) && isset($ALL[$id])));
        if (!$ids) {
            $msg = 'Nav atzīmēts neviens darbs.'; $msgType = 'warn';
        } else {
            // Maksas darbiem vajag atsevišķu apstiprinājumu — nejaušs klikšķis uz
            // "palaist visu" nedrīkst iztērēt Gemini kvotu.
            $costs = array_filter($ids, fn($id) => !empty($ALL[$id]['costs']));
            if ($costs && empty($_POST['confirm_costs'])) {
                $msg = 'Atzīmēti maksas darbi (' . e(implode(', ', $costs))
                     . '). Atzīmē arī "Apstiprinu maksas darbus" un mēģini vēlreiz.';
                $msgType = 'warn';
            } else {
                $busy = array_values(array_filter($ids, fn($id) => admin_job_running($ALL[$id])));
                [$method, $err] = admin_launch($ids);
                if ($method === null) {
                    $msg = "Neizdevās palaist: $err"; $msgType = 'warn';
                    applog_event('ERROR', 'admin', 'palaisana', (string)$err, true);
                } else {
                    $msg = 'Palaists fonā (' . count($ids) . ' darbs/-i, metode: ' . e($method) . '). '
                         . 'Lapu var aizvērt — process turpinās serverī.'
                         . ($busy ? ' Jau darbojošies izlaisti: ' . e(implode(', ', $busy)) . '.' : '');
                    applog_event('INFO', 'admin', 'palaists', implode(',', $ids));
                }
            }
        }
    } elseif ($action === 'cron_save') {
        $new = [];
        foreach ($ALL as $id => $job) {
            $on   = !empty($_POST['cron_on'][$id]);
            $time = (string)($_POST['cron_time'][$id] ?? '03:00');
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) $time = '03:00';
            $days = array_values(array_filter(
                array_map('intval', (array)($_POST['cron_days'][$id] ?? [])),
                fn($d) => $d >= 0 && $d <= 6));
            if (!$on && !isset($SCHED[$id])) continue;   // neuzkrāj tukšus ierakstus
            $new[$id] = [
                'enabled'  => $on,
                'time'     => $time,
                'days'     => $days,
                'last_run' => $SCHED[$id]['last_run'] ?? null,
            ];
        }
        if (admin_schedule_save($new)) {
            $SCHED = $new;
            $on = count(array_filter($new, fn($e) => !empty($e['enabled'])));
            $msg = "Grafiks saglabāts ($on ieplānots).";
            applog_event('INFO', 'admin', 'grafiks', "saglabāts, aktīvi=$on");
        } else {
            $msg = 'Neizdevās saglabāt grafiku — pārbaudi admin_state/ tiesības.'; $msgType = 'warn';
        }
    } elseif ($action === 'prune_logs') {
        $n = applog_prune();
        $msg = "Žurnālu tīrīšana: izdzēsti $n faili (vecāki par " . APPLOG_KEEP_DAYS . " dienām).";
        applog_event('INFO', 'admin', 'zurnali', "prune n=$n");
    }
}

// ── Konteksta dati UI ──────────────────────────────────────────────────────
$logDays = [];
foreach ((array)@glob(applog_dir() . '/*.log') as $p) {
    $d = basename((string)$p, '.log');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $logDays[$d] = (int)@filesize($p);
}
krsort($logDays);
$todayFile  = applog_file();
$todaySize  = is_file($todayFile) ? (int)@filesize($todayFile) : 0;
$dispatchAt = @file_get_contents(admin_state_dir() . '/dispatch_last.txt') ?: null;
$dispatchOld = $dispatchAt && (time() - strtotime((string)$dispatchAt) > 3600);
$DAYNAMES = [1 => 'P', 2 => 'O', 3 => 'T', 4 => 'C', 5 => 'Pk', 6 => 'S', 0 => 'Sv'];
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Administratīvais panelis · saraksts.lv</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--line:#334155;--tx:#e2e8f0;--dim:#94a3b8;
      --ok:#22c55e;--warn:#f59e0b;--err:#ef4444;--acc:#38bdf8}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--tx);font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
.wrap{max-width:1180px;margin:0 auto;padding:24px 18px 60px}
h1{font-size:22px;margin:0 0 4px}
.sub{color:var(--dim);margin:0 0 22px}
.msg{padding:11px 14px;border-radius:8px;margin-bottom:20px;border:1px solid}
.msg.info{background:#0c4a6e33;border-color:#0ea5e9}
.msg.warn{background:#78350f33;border-color:var(--warn)}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px 18px;margin-bottom:16px}
.card h2{font-size:16px;margin:0 0 3px}
.card .note{color:var(--dim);font-size:13px;margin:0 0 14px}
table{width:100%;border-collapse:collapse}
td,th{padding:8px 6px;border-bottom:1px solid var(--line);vertical-align:top;text-align:left}
th{color:var(--dim);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
tr:last-child td{border-bottom:none}
.jl{font-weight:600}
.jd{color:var(--dim);font-size:12.5px;margin-top:2px}
.part{padding-left:22px;position:relative}
.part::before{content:"└";position:absolute;left:6px;color:var(--line)}
.tag{display:inline-block;font-size:11px;padding:1px 7px;border-radius:20px;margin-left:6px;vertical-align:1px}
.tag.heavy{background:#78350f;color:#fcd34d}
.tag.costs{background:#7f1d1d;color:#fca5a5}
.st{font-size:12px;white-space:nowrap}
.st.run{color:var(--acc)}
.st.idle{color:var(--dim)}
button{background:var(--acc);color:#06283d;border:0;border-radius:7px;padding:9px 16px;font-weight:700;cursor:pointer;font-size:14px}
button.sec{background:#475569;color:var(--tx)}
button:hover{filter:brightness(1.08)}
.bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:14px}
input[type=time]{background:#0f172a;color:var(--tx);border:1px solid var(--line);border-radius:6px;padding:4px 7px}
.days{display:inline-flex;gap:3px}
.days label{font-size:11px;background:#0f172a;border:1px solid var(--line);border-radius:5px;padding:2px 6px;cursor:pointer;user-select:none}
.days input{display:none}
.days input:checked+span{color:var(--acc);font-weight:700}
pre{background:#0b1220;border:1px solid var(--line);border-radius:8px;padding:12px;overflow:auto;max-height:440px;
    font:12px/1.45 ui-monospace,Menlo,Consolas,monospace;white-space:pre-wrap;word-break:break-word;margin:0}
a{color:var(--acc)}
.meter{height:6px;background:#0b1220;border-radius:4px;overflow:hidden;margin-top:6px}
.meter i{display:block;height:100%;background:var(--ok)}
.meter i.hot{background:var(--warn)}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.kv{font-size:13px}.kv b{display:block;font-size:19px;margin-top:2px}
select{background:#0f172a;color:var(--tx);border:1px solid var(--line);border-radius:6px;padding:5px 8px}
</style>
</head>
<body>
<div class="wrap">

<h1>Administratīvais panelis</h1>
<p class="sub">Visu sadaļu datu būve, koda izpilde, cron grafiks un notikumu žurnāls.</p>

<?php if ($msg !== ''): ?>
  <div class="msg <?= e($msgType) ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- ══ DARBI ══ -->
<form method="post">
<input type="hidden" name="k" value="<?= e($k) ?>">
<input type="hidden" name="action" value="run">

<?php foreach ($SECTIONS as $sid => $sec): if (!$sec['jobs']) continue; ?>
  <div class="card">
    <h2><?= e($sec['name']) ?>
      <?php if (!empty($sec['panel'])): ?>
        <a style="font-size:12px;font-weight:400;margin-left:8px"
           href="<?= e($sec['panel']) ?>?k=<?= e($k) ?>">dziļais panelis →</a>
      <?php endif; ?>
    </h2>
    <?php if (!empty($sec['note'])): ?><p class="note"><?= e($sec['note']) ?></p><?php endif; ?>
    <table>
      <tr><th style="width:26px"></th><th>Darbs</th><th style="width:120px">Stāvoklis</th><th style="width:130px">Pēdējoreiz</th></tr>
      <?php foreach ($sec['jobs'] as $jid => $job):
            $running = admin_job_running($job); $last = admin_job_last($job); ?>
      <tr>
        <td><input type="checkbox" name="jobs[]" value="<?= e($jid) ?>" data-section="<?= e($sid) ?>"></td>
        <td class="<?= empty($job['part_of']) ? '' : 'part' ?>">
          <div class="jl"><?= e($job['label']) ?>
            <?php if (!empty($job['heavy'])): ?><span class="tag heavy">ilgs</span><?php endif; ?>
            <?php if (!empty($job['costs'])): ?><span class="tag costs">maksas</span><?php endif; ?>
          </div>
          <?php if (!empty($job['desc'])): ?><div class="jd"><?= e($job['desc']) ?></div><?php endif; ?>
        </td>
        <td class="st <?= $running ? 'run' : 'idle' ?>" data-st="<?= e($jid) ?>">
          <?= $running ? '● darbojas' : '○ gaida' ?>
        </td>
        <td class="st idle" data-when="<?= e($jid) ?>"><?= e($last['when'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="bar">
      <button type="button" class="sec" data-pick="<?= e($sid) ?>">Atzīmēt visu sadaļu</button>
      <button type="button" class="sec" data-clear="<?= e($sid) ?>">Noņemt</button>
    </div>
  </div>
<?php endforeach; ?>

  <div class="card">
    <div class="bar">
      <button type="submit">▶ Palaist atzīmētos</button>
      <button type="button" class="sec" id="pick-all">Atzīmēt VISU</button>
      <button type="button" class="sec" id="clear-all">Notīrīt</button>
      <label style="margin-left:auto;color:var(--warn)">
        <input type="checkbox" name="confirm_costs" value="1"> Apstiprinu maksas darbus
      </label>
    </div>
    <p class="note" style="margin:12px 0 0">
      Darbi iet fonā <b>pēc kārtas</b>, tajā secībā, kādā tie uzskaitīti augstāk — nevis
      vienlaikus. Tas ir apzināti: pilnā būve prasa ~2 GB atmiņas un smagi noslogo disku, un
      vienlaicīga Konkursu sinhronizācija (45 ārējie avoti) koplietotā serverī radītu
      noildzes un 503 apmeklētājiem. Kamēr darbs gaida rindā, tas rāda “gaida”.
      Slēdzene neļauj vienu un to pašu darbu palaist divreiz. Ķēdes darbos pirmā kļūme
      aptur pārējos soļus; atsevišķi izvēlēti darbi ir neatkarīgi — viena kļūme pārējos neatceļ.
    </p>
  </div>
</form>

<!-- ══ CRON ══ -->
<form method="post">
<input type="hidden" name="k" value="<?= e($k) ?>">
<input type="hidden" name="action" value="cron_save">
  <div class="card">
    <h2>Cron grafiks</h2>
    <p class="note">
      Laiks Rīgas laikā. Dienas neatzīmētas = katru dienu. Katrs darbs dienā izpildās vienreiz.
      Grafiku izpilda <code>cron/dispatch.php</code>, ko sistēmas cron sauc ik 5 minūtes —
      hPanel jāieliek <b>viena</b> rinda:
      <code>*/5 * * * * php <?= e(admin_root()) ?>/cron/dispatch.php</code>
    </p>
    <?php if ($dispatchAt === null): ?>
      <div class="msg warn" style="margin:0 0 14px">
        Dispečers vēl nekad nav palaists — sistēmas cron rinda, visticamāk, nav ieliktas.
        Kamēr tā nav, ieplānotie darbi neizpildīsies.
      </div>
    <?php elseif ($dispatchOld): ?>
      <div class="msg warn" style="margin:0 0 14px">
        Dispečers pēdējoreiz strādāja <?= e($dispatchAt) ?> — vairāk nekā pirms stundas.
        Pārbaudi, vai cron rinda joprojām pastāv.
      </div>
    <?php else: ?>
      <p class="note" style="margin:-8px 0 14px">Dispečers pēdējoreiz: <b><?= e($dispatchAt) ?></b> ✔</p>
    <?php endif; ?>

    <table>
      <tr><th>Darbs</th><th style="width:70px">Ieslēgts</th><th style="width:90px">Laiks</th>
          <th style="width:190px">Dienas</th><th style="width:130px">Pēdējā izpilde</th></tr>
      <?php foreach ($ALL as $jid => $job):
            $en = $SCHED[$jid] ?? ['enabled' => false, 'time' => '03:00', 'days' => [], 'last_run' => null]; ?>
      <tr>
        <td><div class="jl" style="font-size:13px"><?= e($job['label']) ?></div>
            <div class="jd"><?= e($job['section_name']) ?></div></td>
        <td><input type="checkbox" name="cron_on[<?= e($jid) ?>]" value="1" <?= !empty($en['enabled']) ? 'checked' : '' ?>></td>
        <td><input type="time" name="cron_time[<?= e($jid) ?>]" value="<?= e($en['time'] ?? '03:00') ?>"></td>
        <td><div class="days">
          <?php foreach ($DAYNAMES as $d => $lbl): ?>
            <label><input type="checkbox" name="cron_days[<?= e($jid) ?>][]" value="<?= (int)$d ?>"
                   <?= in_array((int)$d, array_map('intval', (array)($en['days'] ?? [])), true) ? 'checked' : '' ?>><span><?= e($lbl) ?></span></label>
          <?php endforeach; ?>
        </div></td>
        <td class="st idle"><?= e($en['last_run'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="bar"><button type="submit">Saglabāt grafiku</button></div>
  </div>
</form>

<!-- ══ ŽURNĀLS ══ -->
<div class="card">
  <h2>Notikumu žurnāls</h2>
  <p class="note">
    Katru dienu savs fails <code>log/GGGG-MM-DD.log</code>, glabājas <?= APPLOG_KEEP_DAYS ?> dienas (3 gadi),
    pēc tam dzēšas automātiski. Parastā satiksme ir stundu apkopojumos (<code>STAT</code>),
    pilnā rindā — kļūdas, lēni pieprasījumi, darbu izpilde, admin darbības un liegtas piekļuves.
  </p>

  <div class="grid2" style="margin-bottom:14px">
    <div class="kv">Šodienas fails<b><?= human_bytes($todaySize) ?> / <?= human_bytes(APPLOG_MAX_BYTES) ?></b>
      <div class="meter"><i class="<?= $todaySize > APPLOG_MAX_BYTES * 0.8 ? 'hot' : '' ?>"
           style="width:<?= min(100, (int)round($todaySize / APPLOG_MAX_BYTES * 100)) ?>%"></i></div>
    </div>
    <div class="kv">Saglabātās dienas<b><?= count($logDays) ?></b></div>
    <div class="kv">Kopējais apjoms<b><?= human_bytes(array_sum($logDays)) ?></b></div>
  </div>

  <div class="bar" style="margin:0 0 12px">
    <select id="logday">
      <?php foreach ($logDays as $d => $sz): ?>
        <option value="<?= e($d) ?>"><?= e($d) ?> · <?= human_bytes($sz) ?></option>
      <?php endforeach; ?>
      <?php if (!$logDays): ?><option value="<?= e(date('Y-m-d')) ?>"><?= e(date('Y-m-d')) ?></option><?php endif; ?>
    </select>
    <button type="button" class="sec" id="logreload">Atsvaidzināt</button>
  </div>
  <pre id="logbox">(ielādē…)</pre>

  <form method="post" class="bar" style="margin-top:12px">
    <input type="hidden" name="k" value="<?= e($k) ?>">
    <input type="hidden" name="action" value="prune_logs">
    <button type="submit" class="sec">Iztīrīt vecākus par 3 gadiem</button>
  </form>
</div>

</div>

<script>
const K = <?= json_encode($k) ?>;

// Atzīmēšanas palīgi
document.querySelectorAll('[data-pick]').forEach(b => b.onclick = () =>
  document.querySelectorAll(`input[data-section="${b.dataset.pick}"]`).forEach(c => c.checked = true));
document.querySelectorAll('[data-clear]').forEach(b => b.onclick = () =>
  document.querySelectorAll(`input[data-section="${b.dataset.clear}"]`).forEach(c => c.checked = false));
document.getElementById('pick-all').onclick = () =>
  document.querySelectorAll('input[name="jobs[]"]').forEach(c => c.checked = true);
document.getElementById('clear-all').onclick = () =>
  document.querySelectorAll('input[name="jobs[]"]').forEach(c => c.checked = false);

// Darbu stāvokļa atsvaidzināšana (bez pilnas pārlādes — būves laikā serveris ir noslogots)
async function poll() {
  try {
    const r = await fetch(`admin.php?k=${encodeURIComponent(K)}&ajax=status`, {cache:'no-store'});
    if (!r.ok) return;
    const d = await r.json();
    for (const [id, s] of Object.entries(d.jobs)) {
      const st = document.querySelector(`[data-st="${CSS.escape(id)}"]`);
      if (st) { st.textContent = s.running ? '● darbojas' : '○ gaida';
                st.className = 'st ' + (s.running ? 'run' : 'idle'); }
      const wh = document.querySelector(`[data-when="${CSS.escape(id)}"]`);
      if (wh && s.when) wh.textContent = s.when;
    }
  } catch (e) { /* tīkla mirklis — nākamais mēģinājums */ }
}
// Aptaujā TIKAI tad, kad cilne ir redzama. Aizmirsta atvērta cilne citādi rada 720
// pieprasījumus stundā bezgalīgi (redzēts žurnālā: admin.php n=720 vienā stundā) —
// tas noslogo serveri tieši tad, kad fonā iet smaga būve, un aizsedz īsto satiksmi
// statistikā. Atgriežoties pie cilnes stāvoklis atsvaidzinās uzreiz.
let pollTimer = null;
function pollOn()  { if (!pollTimer) { poll(); pollTimer = setInterval(poll, 5000); } }
function pollOff() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }
document.addEventListener('visibilitychange', () => document.hidden ? pollOff() : pollOn());
if (!document.hidden) pollOn();

// Žurnāla skatītājs
const box = document.getElementById('logbox'), sel = document.getElementById('logday');
async function loadLog() {
  box.textContent = '(ielādē…)';
  try {
    const r = await fetch(`admin.php?k=${encodeURIComponent(K)}&ajax=log&day=${encodeURIComponent(sel.value)}`, {cache:'no-store'});
    box.textContent = await r.text();
    box.scrollTop = box.scrollHeight;
  } catch (e) { box.textContent = '(neizdevās ielādēt)'; }
}
sel.onchange = loadLog;
document.getElementById('logreload').onclick = loadLog;
loadLog();
</script>
</body>
</html>
