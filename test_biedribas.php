<?php
/**
 * test_biedribas.php — "Test Biedrības" adaptīvā pārskata lapa (TIKAI lokālā testa vide).
 *
 * Atšķirībā no komercsabiedrību lapas šī nerāda fiksētu veidni: paneļu reģistrs
 * (registrs/nvo/nvo_panels.php) pārbauda, kādi dati par konkrēto biedrību vispār
 * eksistē, un rāda tikai tos. Lapa arī nosauc, KO par biedrību nav zināms.
 *
 * Bez ?reg= parametra — ievadlapa ar meklēšanu un datu seguma kopainu.
 * Ar ?reg=<11 cipari> — konkrētās biedrības adaptīvais pārskats.
 *
 * PERSONAS DATI: netiek rādīti nekādi (ne amatpersonu vārdi, ne PLG).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/test_env.php';
reg_test_gate();   // TIKAI lokālā testa vide — publiskā hostā 404
ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/registrs/lib/db.php';
require_once __DIR__ . '/registrs/nvo/nvo_panels.php';

$reg   = preg_replace('/\D/', '', (string)($_GET['reg'] ?? ''));
$error = null;
$ur    = null;
try { $ur = get_ur_db(); } catch (Throwable $e) { $error = 'UR datubāze nav pieejama šajā vidē.'; }

$meta = nvo_meta();
$nvo_ready = nvo_db() !== null;

// ─── Detaļlapa ──────────────────────────────────────────────────────────────
$ctx = null; $atrasti = []; $iztrukst = [];
if ($reg !== '' && $ur !== null) {
    $ctx = nvo_collect($ur, $reg);
    if ($ctx['pamatdati'] === null) { $error = 'Reģistrācijas numurs ' . nv_e($reg) . ' Uzņēmumu reģistrā nav atrasts.'; $ctx = null; }
    else [$atrasti, $iztrukst] = nvo_run_panels($ctx);
}

// ─── Ievadlapas dati ────────────────────────────────────────────────────────
$stats = []; $piemeri = [];
if ($ctx === null && $ur !== null && $error === null) {
    try {
        $stats['aktivas'] = (int)$ur->query("SELECT COUNT(*) FROM register WHERE type='BDR' AND (terminated IS NULL OR terminated='')")->fetchColumn();
        $stats['ar_bilanci'] = (int)$ur->query("SELECT COUNT(DISTINCT f.legal_entity_registration_number)
            FROM financial_statements f JOIN register r ON r.regcode=f.legal_entity_registration_number
            JOIN balance_sheets b ON b.statement_id=f.id
            WHERE r.type='BDR' AND (r.terminated IS NULL OR r.terminated='')")->fetchColumn();
        // Piemēri, kas parāda dažādu paneļu skaitu: no lielas līdz mazai biedrībai.
        $piemeri = $ur->query("SELECT r.regcode, r.name, b.total_assets
            FROM register r
            JOIN financial_statements f ON f.legal_entity_registration_number=r.regcode AND f.year=(
                SELECT MAX(year) FROM financial_statements WHERE legal_entity_registration_number=r.regcode)
            JOIN balance_sheets b ON b.statement_id=f.id
            WHERE r.type='BDR' AND (r.terminated IS NULL OR r.terminated='')
            ORDER BY b.total_assets DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* statistika nav kritiska */ }
}

$pageTitle  = ($ctx !== null ? $ctx['pamatdati']['nosaukums'] . ' — ' : '') . 'Test Biedrības | Saraksts.lv';
$pageDesc   = 'Adaptīvs biedrību pārskats: rāda tikai tos datus, kas par konkrēto biedrību ir pieejami.';

ob_start(); ?>
    <meta name="robots" content="noindex,nofollow">
    <style>
      .nv-wrap{max-width:1080px;margin:20px auto 60px;padding:0 15px}
      .nv-head{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 22px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
      .nv-head h1{margin:0 0 6px;font-size:24px;line-height:1.25}
      .nv-head .nv-sub2{color:#666;font-size:14px}
      .nv-testbadge{display:inline-block;background:#fff3cd;border:1px solid #ffe08a;color:#7a5d00;border-radius:6px;padding:3px 10px;font-size:13px;margin-bottom:10px}
      .nv-meter{margin:16px 0 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
      .nv-meter-bar{flex:1;min-width:180px;height:10px;background:#eef0f2;border-radius:6px;overflow:hidden}
      .nv-meter-bar span{display:block;height:100%;background:linear-gradient(90deg,#5b8c5a,#7fb069)}
      .nv-meter-txt{font-size:13.5px;color:#444;font-variant-numeric:tabular-nums}
      .nv-grp{margin:26px 0 8px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#7a7f87;font-weight:600}
      .nv-panel{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
      .nv-panel h2{margin:0 0 10px;font-size:17px;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
      .nv-rare{font-size:11.5px;font-weight:400;color:#8a6d3b;background:#fdf6e3;border:1px solid #f0e2bd;border-radius:4px;padding:1px 7px}
      .nv-src{color:#8a8f97;font-size:12px;margin:10px 0 0;padding-top:8px;border-top:1px dashed #eceef0}
      .nv-note{color:#777;font-size:12.5px;margin:10px 0 0}
      .nv-text{font-size:14.5px;line-height:1.6;margin:0}
      .nv-sub{font-weight:600;font-size:13.5px;margin:14px 0 6px;color:#444}
      .nv-highlight{font-size:14.5px;margin:0 0 10px}
      .nv-warn{background:#fff8e6;border:1px solid #f2e3bd;border-radius:6px;padding:10px 12px;font-size:13.5px;line-height:1.55;margin:0 0 12px}
      .nv-dim{color:#8a8f97}.nv-na{color:#a0a4a9;font-style:italic}
      .nv-pos{color:#2f7d32;font-weight:600}.nv-neg{color:#b3261e;font-weight:600}
      ul.nv-list{margin:0;padding-left:20px;font-size:14.5px;line-height:1.6}
      ul.nv-list li{margin-bottom:5px}
      table.nv-kv{width:100%;border-collapse:collapse;font-size:14px}
      table.nv-kv th{text-align:left;font-weight:500;color:#666;padding:6px 14px 6px 0;width:220px;vertical-align:top}
      table.nv-kv td{padding:6px 0;vertical-align:top}
      .nv-scroll{overflow-x:auto;margin:8px 0}
      table.nv-table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:420px}
      table.nv-table th,table.nv-table td{padding:6px 10px;border-bottom:1px solid #eef0f2;text-align:left;vertical-align:top}
      table.nv-table th{background:#f8f9fa;font-weight:600;white-space:nowrap}
      table.nv-table td.num,table.nv-table th.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
      .nv-badge{display:inline-block;border-radius:4px;padding:2px 8px;font-size:12.5px;border:1px solid}
      .nv-ok{background:#eaf5ea;border-color:#c6e2c6;color:#2f6b32}
      .nv-brid{background:#fff6e5;border-color:#f2ddb0;color:#8a5d00}
      .nv-risks{background:#fdeceb;border-color:#f3c9c6;color:#a52720}
      .nv-info{background:#eef2f7;border-color:#d4dce6;color:#40566f}
      .nv-chips{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0}
      .nv-chip{background:#f2f5f1;border:1px solid #dde5db;border-radius:5px;padding:3px 10px;font-size:13px}
      .nv-chip-more{background:#eef0f2;border-color:#e0e3e6;color:#777}
      .nv-bar{flex:1;min-width:80px;height:9px;background:#eef0f2;border-radius:5px;overflow:hidden}
      .nv-bar span{display:block;height:100%;background:#9bb8a0}
      .nv-cmp{margin:10px 0}
      .nv-cmp-row{display:flex;align-items:center;gap:10px;margin-bottom:6px;font-size:13.5px}
      .nv-cmp-lab{width:150px;color:#555}
      .nv-cmp-val{width:120px;text-align:right;font-variant-numeric:tabular-nums}
      .nv-cmp-self .nv-cmp-lab{font-weight:600;color:#2f6b32}
      .nv-cmp-self .nv-bar span{background:#5b8c5a}
      .nv-det{margin:8px 0;border:1px solid #eef0f2;border-radius:6px;padding:8px 12px}
      .nv-det summary{cursor:pointer;font-size:13.5px;font-weight:500}
      .nv-missing{background:#fbfbfc;border:1px dashed #dfe3e7;border-radius:8px;padding:14px 18px;margin-top:18px}
      .nv-missing h2{margin:0 0 8px;font-size:15px;color:#5a6068}
      .nv-missing ul{margin:0;padding-left:18px;font-size:13.5px;color:#6b7078;line-height:1.65}
      .nv-search{margin:16px 0}
      .nv-search input{width:100%;max-width:420px;padding:9px 12px;border:1px solid #ccc;border-radius:6px;font-size:14.5px}
      .nv-search button{padding:9px 18px;border:1px solid #5b8c5a;background:#5b8c5a;color:#fff;border-radius:6px;font-size:14.5px;cursor:pointer}
      .nv-err{background:#fdeceb;border:1px solid #f3c9c6;color:#a52720;border-radius:6px;padding:12px 14px;font-size:14px}
      @media(max-width:640px){table.nv-kv th{width:auto;display:block;padding-bottom:0}table.nv-kv td{display:block;padding-top:2px}.nv-cmp-lab{width:96px}.nv-cmp-val{width:88px}}
    </style>
<?php $extraHeadContent = ob_get_clean(); ?>
<!DOCTYPE html>
<html lang="lv">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/head/head.php'; ?>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/header.php'; ?>

<div class="nv-wrap">
  <div class="nv-head">
    <div class="nv-testbadge">Test sadaļa — tikai lokālā izstrādes vidē</div>
<?php if ($ctx !== null):
    $pd = $ctx['pamatdati'];
    $kopa = count($atrasti) + count($iztrukst);
    $proc = $kopa > 0 ? count($atrasti) / $kopa : 0; ?>
    <h1><?= nv_e($pd['nosaukums']) ?></h1>
    <div class="nv-sub2"><?= nv_e($pd['tips_teksts']) ?> · reģ. nr. <?= nv_e($pd['regcode']) ?>
      · <a href="/<?= nv_e($pd['regcode']) ?>">standarta lapa</a></div>
    <div class="nv-meter">
      <div class="nv-meter-bar"><span style="width:<?= number_format($proc * 100, 1, '.', '') ?>%"></span></div>
      <div class="nv-meter-txt">Pieejami <strong><?= count($atrasti) ?></strong> no <?= $kopa ?> datu paneļiem</div>
    </div>
<?php else: ?>
    <h1>Test Biedrības — adaptīvais pārskats</h1>
    <p class="nv-text">Biedrībām nav apgrozījuma un peļņas datu, tāpēc komercsabiedrību atskaite tām neder.
      Šī lapa katrai biedrībai pārbauda <strong><?= count(nvo_panel_registry()) ?> datu paneļus</strong> un rāda tikai tos,
      kuriem dati tiešām ir — no statūtu mērķiem līdz saņemtajam de minimis atbalstam.</p>
<?php endif; ?>
  </div>

<?php if ($error !== null): ?>
  <p class="nv-err"><?= $error ?></p>
<?php endif; ?>

<?php if ($ctx === null): ?>
  <div class="nv-panel">
    <h2>Atvērt biedrības pārskatu</h2>
    <form class="nv-search" method="get">
      <input type="text" name="reg" inputmode="numeric" placeholder="Reģistrācijas numurs, piem. 40008002279" value="">
      <button type="submit">Rādīt</button>
    </form>
<?php if ($stats): ?>
    <p class="nv-note">Uzņēmumu reģistrā ir <strong><?= nv_num($stats['aktivas']) ?></strong> aktīvu biedrību;
      no tām <strong><?= nv_num($stats['ar_bilanci']) ?></strong>
      (<?= nv_num(100 * $stats['ar_bilanci'] / max(1, $stats['aktivas']), 1) ?> %) ir vismaz viens gads ar publicētu bilanci.</p>
<?php endif; ?>
<?php if (!$nvo_ready): ?>
    <p class="nv-err">Papildu avotu datubāze nav uzbūvēta. Palaid:
      <code>php registrs/nvo/build_nvo.php</code></p>
<?php endif; ?>
  </div>

<?php if ($piemeri): ?>
  <div class="nv-panel">
    <h2>Piemēri</h2>
    <div class="nv-scroll"><table class="nv-table">
      <thead><tr><th>Biedrība</th><th class="num">Aktīvi</th></tr></thead><tbody>
<?php foreach ($piemeri as $p): ?>
      <tr><td><a href="?reg=<?= nv_e($p['regcode']) ?>"><?= nv_e(str_replace('""', '"', (string)$p['name'])) ?></a></td>
          <td class="num"><?= nv_eur($p['total_assets']) ?></td></tr>
<?php endforeach; ?>
    </tbody></table></div>
  </div>
<?php endif; ?>

<?php if ($meta): ?>
  <div class="nv-panel">
    <h2>Papildu datu avoti</h2>
    <ul class="nv-list">
<?php foreach (json_decode($meta['avoti'] ?? '[]', true) ?: [] as $k => $v): ?>
      <li><?= nv_e($v) ?> — <?= nv_num($meta['rows_' . $k] ?? 0) ?> ierakstu</li>
<?php endforeach; ?>
    </ul>
    <p class="nv-note">Dati sagatavoti <?= nv_e(substr((string)($meta['built_at'] ?? ''), 0, 10)) ?>.
      Visos avotos importētas tikai juridiskās personas; fizisko personu ieraksti netiek ielasīti.</p>
  </div>
<?php endif; ?>

<?php else: /* ── Detaļlapa: paneļi pa grupām ── */
    $pasreiz = null;
    foreach ($atrasti as $p):
        if ($p['grupa'] !== $pasreiz) { $pasreiz = $p['grupa']; ?>
  <div class="nv-grp"><?= nv_e(NVO_GRUPAS[$p['grupa']] ?? $p['grupa']) ?></div>
<?php   } ?>
  <div class="nv-panel" id="nv-<?= nv_e($p['id']) ?>">
    <h2><?= nv_e($p['virsraksts']) ?>
<?php if ($p['segums'] < 10 && $p['grupa'] !== 'beigas'): ?>
      <span class="nv-rare">ir tikai <?= nv_num($p['segums'], 1) ?> % biedrību</span>
<?php endif; ?>
    </h2>
    <?= ($p['render'])($p['dati'], $ctx) ?>
<?php if (!empty($p['avots'])): ?>
    <p class="nv-src">Avots: <?= nv_e($p['avots']) ?></p>
<?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ($iztrukst): ?>
  <div class="nv-missing">
    <h2>Šie paneļi netiek rādīti, jo datu nav</h2>
    <ul>
<?php foreach ($iztrukst as $p): ?>
      <li><?= nv_e($p['virsraksts']) ?><?= $p['segums'] < 100 ? ' <span class="nv-dim">(ir ' . nv_num($p['segums'], 1) . ' % biedrību)</span>' : '' ?></li>
<?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php endif; ?>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/footer/footer.php'; ?>
</body>
</html>
