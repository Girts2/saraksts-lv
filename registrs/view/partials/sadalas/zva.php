<?php
/**
 * Sadaļa "Farmaceitiskās darbības licences" (ZVA). Aptiekas, lieltirgotavas,
 * ražotnes. GDPR: aptiekas vadītāja un atbildīgās personas vārdi, e-pasti un
 * tālruņi netiek glabāti (sk. build_papildu_tabulas).
 */
/** @var array $page_data */
$zv_rows = $page_data['results']['zva'] ?? [];
if (!is_array($zv_rows) || !$zv_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';

$zv = []; $zv_veidi = []; $zv_lic = []; $zv_lic_apt = [];
foreach ($zv_rows as $zv_i => $r) {
    $st = trim((string)($r['statuss'] ?? ''));
    $v = trim((string)($r['veids'] ?? ''));
    if ($v !== '') $zv_veidi[$v] = ($zv_veidi[$v] ?? 0) + 1;
    $kods = trim((string)($r['kods'] ?? ''));
    // VIENA licence sedz vairākus objektus (aptieka + filiāles) — skaitītājā
    // skaitām LICENCES, ne objektus: "6" pie vienas licences izskatījās pēc
    // sešām licencēm (audits 2026-08-20).
    $zv_k = $kods !== '' ? $kods : 'rinda#' . $zv_i;
    $zv_lic[$zv_k] = 1;
    if ($st === 'Apturēta') $zv_lic_apt[$zv_k] = 1;
    $zv[] = ['v' => $v, 'st' => $st, 'kods' => $kods,
             'no' => substr(trim((string)($r['no_dat'] ?? '')), 0, 10),
             'obj' => trim((string)($r['objekts'] ?? ''))];
}
if (!$zv) return;
$zv_apt = count($zv_lic_apt);
arsort($zv_veidi);
usort($zv, static fn($a, $b) => strcmp($b['no'], $a['no']));

$zv_pilns = $pd_pilns ?? false;
$zv_lim = pd_limits($zv_pilns, 10);
$pd_nos = 'Farmaceitiskās darbības licences';
$pd_n   = count($zv_lic);
$pd_kops = $zv_veidi ? array_key_first($zv_veidi) : '';
if (count($zv) > count($zv_lic)) {
    $pd_kops = trim($pd_kops . ' · ' . count($zv) . ' objekti');
}
?>
<div class="pd-body">
<?php if (count($zv_veidi) > 1): ?>
    <div class="pd-sec">
    <h4>Objektu veidi</h4>
    <ul>
<?php foreach ($zv_veidi as $v => $c): ?>
        <li><?= h($v) ?> <span class="pd-muted"><?= (int)$c ?>×</span></li>
<?php endforeach; ?>
    </ul>
    </div>
<?php endif; ?>
    <div class="pd-sec">
    <h4>Licencētie objekti</h4>
    <ul>
<?php foreach (array_slice($zv, 0, $zv_lim) as $x): ?>
        <li><?= h($x['obj'] !== '' ? $x['obj'] : $x['v']) ?>
            <?php if ($x['st'] !== ''): ?><span class="pd-tag<?= $x['st'] === 'Spēkā' ? ' pd-tag-ok' : ' pd-tag-bad' ?>"><?= h($x['st']) ?></span><?php endif; ?>
            <br><span class="pd-muted"><?= h($x['v']) ?><?php if ($x['kods'] !== ''): ?> · <?= h($x['kods']) ?><?php endif; ?><?php if ($x['no'] !== ''): ?> · no <?= h($x['no']) ?><?php endif; ?></span></li>
<?php endforeach; ?>
<?= pd_vairak(count($zv) - $zv_lim, 'zva', $zv_pilns) ?>
    </ul>
    </div>
</div>
<p class="pd-note">Aptiekām, zāļu lieltirgotavām un ražotnēm nepieciešama Zāļu valsts aģentūras licence.
<?php if ($zv_apt > 0): ?><?= (int)$zv_apt ?> <?= pd_dsk($zv_apt, 'licence ir apturēta', 'licences ir apturētas') ?>. <?php endif; ?>
Avots: <a href="https://data.gov.lv/dati/lv/dataset/farmaceitiskas-darbibas-uznemumu-registrs" rel="noopener">ZVA farmaceitiskās darbības uzņēmumu reģistrs</a>.</p>
