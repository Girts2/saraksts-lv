<?php
/**
 * savienojumi_admin.php — ar vienu pogu: vai serveris sasniedz datu avotus, ko lieto vietnes kods.
 *
 * Piekļuve: /savienojumi_admin.php?k=<token no admin_token.php> (tas pats token, kas admin.php;
 * nepareizs → 404). No čaulas: `php savienojumi_admin.php` — tā pati pārbaude bez token, izdrukā
 * tabulu, saglabā rezultātu lapai; izejas kods 1, ja kāds avots nav sasniedzams.
 *
 * KĀPĒC: sk. lib/savienojumi.php. No datora to pārbaudīt nevar — bloks ir pret servera IP, tāpēc
 * zondēm jāiet no paša servera. Rezultāts paliek admin_state/savienojumi_pedeja.json, lai lapa,
 * atverot to vēlāk, rāda pēdējo pārbaudi un tās laiku.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/savienojumi.php';
require_once __DIR__ . '/lib/admin_jobs.php';
require_once __DIR__ . '/lib/applog.php';

function sav_rez_cels(): string { return admin_state_dir() . '/savienojumi_pedeja.json'; }

/** Pēc pārbaudes nākamo no lapas var palaist ne ātrāk kā pēc tik sekundēm (mūsu IP jau ir reputācijas sarakstā). */
const SAV_ATDZISANA_S = 60;

/**
 * Palaiž pārbaudi zem slēdzenes un saglabā rezultātu.
 * ['ok', dati] | ['aiznemts', null] | ['kluda', teksts] — slēdzenes vai rezultāta faila kļūda nav "aizņemts".
 */
function sav_palaist(): array {
    @mkdir(admin_state_dir(), 0775, true);
    $fh = @fopen(admin_state_dir() . '/savienojumi.lock', 'c');
    if ($fh === false) return ['kluda', 'mapē admin_state/ nevar izveidot slēdzenes failu (tiesības?)'];
    if (!flock($fh, LOCK_EX | LOCK_NB)) { fclose($fh); return ['aiznemts', null]; }
    @set_time_limit(180);
    $t0 = microtime(true);
    $rez = sav_zonde(sav_merki(__DIR__));
    $cv = curl_version();
    $dati = [
        'laiks' => date('Y-m-d H:i:s'), 'ilgums_s' => round(microtime(true) - $t0, 1),
        'servera_ip' => sav_servera_ip($rez),
        // Vide: būve iet CLI PHP 8.3, poga — tīmekļa PHP; curl/OpenSSL versija ietekmē TLS uzvedību.
        'php' => PHP_SAPI . ' PHP ' . PHP_VERSION . ', curl ' . $cv['version'] . ', ' . $cv['ssl_version'],
        // Atbildes paraugu neglabājam: tas der tikai vērtējumam, kas jau izdarīts.
        'rezultati' => array_map(fn($r) => array_diff_key($r, ['paraugs' => true]), $rez),
    ];
    // Avota Content-Type var nebūt UTF-8; bez SUBSTITUTE json_encode atdotu false un rezultāts pazustu.
    $json = json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    $ierakstits = $json !== false && @file_put_contents(sav_rez_cels(), $json, LOCK_EX) !== false;
    flock($fh, LOCK_UN);
    fclose($fh);
    return $ierakstits ? ['ok', $dati] : ['kluda', 'pārbaude notika, bet rezultātu neizdevās ierakstīt admin_state/'];
}

/** Žurnālam: secinājums + nestrādājošie saimnieki. */
function sav_zurnalam(array $d): string {
    [, $t] = sav_secinajums($d['rezultati']);
    $nav = array_column(array_filter($d['rezultati'], fn($r) => $r['statuss'] === 'kluda'), 'saimnieks');
    return $t . ($nav ? ' Nestrādā: ' . implode(', ', $nav) : '') . " ({$d['ilgums_s']} s, IP {$d['servera_ip']})";
}

// ── Čaula ───────────────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    [$st, $d] = sav_palaist();
    if ($st === 'aiznemts') { fwrite(STDERR, "Cita pārbaude jau notiek.\n"); exit(3); }
    if ($st === 'kluda') { fwrite(STDERR, "Kļūda: $d\n"); exit(2); }
    [$vst, $teik] = sav_secinajums($d['rezultati']);
    echo "Savienojumu pārbaude {$d['laiks']} ({$d['ilgums_s']} s), servera IPv4: " . ($d['servera_ip'] ?: '?') . ", {$d['php']}\n$teik\n\n";
    $zime = ['ok' => 'OK   ', 'bridinajums' => 'BRĪD.', 'kluda' => 'KĻŪDA'];
    foreach ($d['rezultati'] as $r) {
        echo $zime[$r['statuss']] . '  ' . str_pad($r['sadala'], 9) . str_pad($r['saimnieks'], 38) . str_pad($r['ip'], 17) . $r['teksts'] . "\n";
    }
    exit($vst === 'kluda' ? 1 : 0);
}

// ── Autentifikācija (kā admin.php) ──────────────────────────────────────────
$TOKEN = require __DIR__ . '/admin_token.php';
$k = $_GET['k'] ?? $_POST['k'] ?? '';
if (!is_string($k) || !hash_equals($TOKEN, $k)) {
    applog_boot('admin');
    applog_event('WARN', 'admin', 'piekluve-liegta',
        'nederīgs token (savienojumi), tīkls=' . applog_anon_ip(applog_client_ip()), true);
    http_response_code(404);
    exit;
}
applog_boot('admin');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$self = 'savienojumi_admin.php?k=' . rawurlencode($k);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'parbaudit') {
    $pirms = is_file(sav_rez_cels()) ? time() - (int)filemtime(sav_rez_cels()) : PHP_INT_MAX;
    if ($pirms < SAV_ATDZISANA_S) {
        $q = '&agri=' . (SAV_ATDZISANA_S - $pirms);
    } else {
        [$st, $d] = sav_palaist();
        if ($st === 'ok') applog_event('INFO', 'admin', 'savienojumi', sav_zurnalam($d), true);
        if ($st === 'kluda') applog_event('WARN', 'admin', 'savienojumi', $d, true);
        $q = ['ok' => '', 'aiznemts' => '&aiznemts=1', 'kluda' => '&kluda=1'][$st];
    }
    // Pēc POST — pāradresācija uz GET, lai lapas pārlāde nepalaistu pārbaudi vēlreiz.
    header('Location: ' . $self . $q, true, 303);
    exit;
}

$d = is_file(sav_rez_cels()) ? json_decode((string)file_get_contents(sav_rez_cels()), true) : null;
if (!is_array($d) || !is_array($d['rezultati'] ?? null)) $d = null;
$merki = $d === null ? sav_merki(__DIR__) : [];

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$NOS = ['kontrole' => 'Kontrole — vispārējs internets'] + array_map(fn($s) => $s['nosaukums'], SAV_SADALAS);
$IKONA = ['ok' => '✅', 'bridinajums' => '⚠️', 'kluda' => '❌'];
$SVARS = ['kluda' => 0, 'bridinajums' => 1, 'ok' => 2];
$grupas = [];
foreach ($d['rezultati'] ?? [] as $r) $grupas[$r['sadala']][] = $r;
foreach ($grupas as &$g) {
    usort($g, fn($a, $b) => [$SVARS[$a['statuss']], $a['saimnieks']] <=> [$SVARS[$b['statuss']], $b['saimnieks']]);
}
unset($g);
$grupas = array_replace(array_intersect_key(array_fill_keys(array_keys($NOS), []), $grupas), $grupas);
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Savienojumu pārbaude · saraksts.lv</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--line:#334155;--tx:#e2e8f0;--dim:#94a3b8;
      --ok:#22c55e;--warn:#f59e0b;--err:#ef4444;--acc:#38bdf8}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--tx);font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
.wrap{max-width:1180px;margin:0 auto;padding:24px 16px 60px}
h1{font-size:22px;margin:0 0 4px}
.sub{color:var(--dim);margin:0 0 18px}
a{color:var(--acc)}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px 18px;margin-bottom:16px}
.card h2{font-size:16px;margin:0 0 10px}
.verdict{border-left:5px solid var(--line);font-size:15px}
.verdict.ok{border-left-color:var(--ok)}.verdict.bridinajums{border-left-color:var(--warn)}.verdict.kluda{border-left-color:var(--err)}
.verdict b{display:block;font-size:17px;margin-bottom:4px}
.meta{color:var(--dim);font-size:13px;margin-top:6px}
.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.chip{background:#0b1220;border:1px solid var(--line);border-radius:20px;padding:3px 11px;font-size:12.5px}
.msg{padding:11px 14px;border-radius:8px;margin-bottom:16px;border:1px solid var(--warn);background:#78350f33}
button{background:var(--acc);color:#06283d;border:0;border-radius:7px;padding:11px 20px;font-weight:700;cursor:pointer;font-size:15px}
button[disabled]{opacity:.6;cursor:wait}
.tw{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:720px}
td,th{padding:7px 6px;border-bottom:1px solid var(--line);vertical-align:top;text-align:left}
th{color:var(--dim);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
tr:last-child td{border-bottom:none}
.h{font-weight:600;word-break:break-all}
.d{color:var(--dim);font-size:12px}
.u{color:var(--dim);font-size:11.5px;word-break:break-all;max-width:280px}
.t{font:12px/1.4 ui-monospace,Menlo,Consolas,monospace;color:var(--dim);white-space:nowrap}
tr.kluda .r{color:#fca5a5}tr.bridinajums .r{color:#fcd34d}
.note{color:var(--dim);font-size:13px;margin:0}
</style>
</head>
<body>
<div class="wrap">

<h1>Savienojumu pārbaude</h1>
<p class="sub">Vai serveris sasniedz datu avotus, ko lieto vietnes kods. Zondes iet no paša servera — tā redz arī bloku pret servera IP.
  <a href="admin.php?k=<?= e($k) ?>">← administratīvais panelis</a></p>

<?php if (isset($_GET['aiznemts'])): ?>
  <div class="msg">Cita pārbaude jau notiek — pagaidi dažas sekundes un pārlādē lapu.</div>
<?php elseif (isset($_GET['agri'])): ?>
  <div class="msg">Pēdējā pārbaude bija tikko — nākamo var palaist pēc <?= (int)$_GET['agri'] ?> s (lai nenoslogotu avotus).</div>
<?php elseif (isset($_GET['kluda'])): ?>
  <div class="msg">Pārbaudi neizdevās pabeigt vai saglabāt: serverī nav rakstāma mape admin_state/ (sīkāk — notikumu žurnālā).</div>
<?php endif; ?>

<form method="post" action="<?= e($self) ?>" class="card" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Pārbauda… (parasti ~20 s, ilgākais ~1 min)'">
  <input type="hidden" name="k" value="<?= e($k) ?>">
  <input type="hidden" name="action" value="parbaudit">
  <button type="submit">Pārbaudīt tagad</button>
  <p class="note" style="margin-top:10px">Katram saimniekam viens pieprasījums (pirmie 64 KB), līdz <?= SAV_PARALELI ?> vienlaikus.
    Reģistram pārbauda, vai būve failu tiešām iegūtu (kur faila adresi kods saliek pats — saknes lapu);
    citām sadaļām — vai serveris sasniedzams (saknes lapas vai API bez atslēgas HTTP 404 ir normāls).
    Ja savienojums neizveidojas, otra zonde pārbauda tīru TCP: tā atšķir IP bloku no ugunsmūra, kas nomet TLS.</p>
</form>

<?php if ($d === null): ?>
  <div class="card">
    <h2>Vēl nav pārbaudīts</h2>
    <p class="note">Pārbaudīs <?= count($merki) ?> saimniekus:
      <?php $sk = array_count_values(array_column($merki, 'sadala'));
            echo e(implode(', ', array_map(fn($s, $n) => ($NOS[$s] ?? $s) . " — $n", array_keys($sk), $sk))); ?>.</p>
  </div>
<?php else:
  [$vst, $vteik] = sav_secinajums($d['rezultati']); ?>
  <div class="card verdict <?= e($vst) ?>">
    <b><?= $IKONA[$vst] ?> <?= e($vteik) ?></b>
    <div class="meta">Pārbaudīts <?= e($d['laiks']) ?> · <?= e(number_format((float)$d['ilgums_s'], 1, ',', '')) ?> s
      · servera ārējā IPv4 <?= e($d['servera_ip'] !== '' ? $d['servera_ip'] : 'nezināma') ?> · <?= e($d['php'] ?? '') ?></div>
    <div class="chips">
      <?php foreach ($grupas as $sid => $g):
            $st = array_column($g, 'statuss');
            $ok = count(array_filter($st, fn($x) => $x !== 'kluda'));
            $ik = in_array('kluda', $st, true) ? 'kluda' : (in_array('bridinajums', $st, true) ? 'bridinajums' : 'ok'); ?>
        <span class="chip"><?= $IKONA[$ik] ?> <?= e($NOS[$sid] ?? $sid) ?>: strādā <?= $ok ?>/<?= count($g) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <?php foreach ($grupas as $sid => $g): ?>
  <div class="card">
    <h2><?= e($NOS[$sid] ?? $sid) ?></h2>
    <div class="tw"><table>
      <tr><th style="width:28px"></th><th>Saimnieks</th><th>IP</th><th>Rezultāts</th><th>DNS / TCP / TLS / kopā, s</th><th>Pārbaudītā adrese</th></tr>
      <?php foreach ($g as $r): ?>
      <tr class="<?= e($r['statuss']) ?>">
        <td><?= $IKONA[$r['statuss']] ?></td>
        <td><div class="h"><?= e($r['saimnieks']) ?></div>
          <?php if (($r['adreses'] ?? 1) > 1): ?><div class="d"><?= (int)$r['adreses'] ?> adreses kodā</div><?php endif; ?></td>
        <td class="t"><?= e($r['ip'] !== '' ? $r['ip'] : '—') ?></td>
        <td class="r"><?= e($r['teksts']) ?></td>
        <td class="t"><?= e(implode(' / ', array_map(fn($x) => number_format((float)$x, 2, ',', ''), [$r['dns_s'], $r['tcp_s'], $r['tls_s'], $r['kopa_s']]))) ?></td>
        <td class="u" title="<?= e($r['url']) ?>"><?= e(mb_strimwidth($r['url'], 0, 90, '…')) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

</div>
</body>
</html>
