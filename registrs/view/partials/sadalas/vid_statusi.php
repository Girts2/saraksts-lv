<?php
/**
 * Sadaļa "VID statusi un reģistri": sabiedriskā labuma organizācijas statuss,
 * PVN grupas dalība un iekļaušana minimālās algas maksātāju sarakstā.
 */
/** @var array $page_data */
$vs_rows = $page_data['results']['vid_statusi'] ?? [];
if (!is_array($vs_rows) || !$vs_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';

$vs_slo = []; $vs_pvn = []; $vs_min = []; $vs_redzets = [];
foreach ($vs_rows as $r) {
    $v = trim((string)($r['veids'] ?? ''));
    $poz = ['st' => trim((string)($r['statuss'] ?? '')),
            'no' => substr(trim((string)($r['no_dat'] ?? '')), 0, 10),
            'lidz' => substr(trim((string)($r['lidz_dat'] ?? '')), 0, 10),
            'pap' => trim((string)($r['papildus'] ?? ''))];
    // Avotā viens un tas pats SLO lēmums atkārtojas katrai darbības jomai, tāpēc
    // bez deduplikācijas skaits bija pārspīlēts līdz 4× (audits 2026-08-19).
    $atsl = $v . '|' . implode('|', $poz);
    if (isset($vs_redzets[$atsl])) continue;
    $vs_redzets[$atsl] = true;
    if ($v === 'slo') $vs_slo[] = $poz;
    elseif ($v === 'pvn_grupa') $vs_pvn[] = $poz;
    elseif ($v === 'minalga') $vs_min[] = $poz;
}
if (!$vs_slo && !$vs_pvn && !$vs_min) return;
usort($vs_slo, static fn($a, $b) => strcmp($b['no'], $a['no']));
usort($vs_min, static fn($a, $b) => strcmp($b['no'], $a['no']));
$vs_slo_speka = false;
foreach ($vs_slo as $x) if (str_starts_with($x['st'], 'Spēkā')) { $vs_slo_speka = true; break; }

$vs_pilns = $pd_pilns ?? false;
$vs_l1 = pd_limits($vs_pilns, 6);
$vs_l2 = pd_limits($vs_pilns, 4);
$pd_nos = 'VID statusi un reģistri';
$pd_n   = count($vs_slo) + count($vs_pvn) + count($vs_min);
$pd_kops = $vs_slo_speka ? 'SLO statuss'
    : ($vs_pvn ? 'PVN grupa'
    : ($vs_min ? 'min. algas saraksts'
    : ($vs_slo ? 'SLO statuss (izbeigts)' : '')));
?>
<div class="pd-body">
<?php if ($vs_slo): ?>
    <div class="pd-sec">
    <h4>Sabiedriskā labuma organizācijas statuss</h4>
    <ul>
<?php foreach (array_slice($vs_slo, 0, $vs_l1) as $x): ?>
        <li><span class="pd-tag<?= str_starts_with($x['st'], 'Spēkā') ? ' pd-tag-ok' : ' pd-tag-bad' ?>"><?= h($x['st']) ?></span>
            <span class="pd-muted"><?php
                $d = [];
                if ($x['no'] !== '') $d[] = 'no ' . $x['no'];
                if ($x['lidz'] !== '') $d[] = 'līdz ' . $x['lidz'];
                echo h(implode(' · ', $d));
                if ($x['pap'] !== '') echo ($d ? ' · ' : '') . h($x['pap']);
            ?></span></li>
<?php endforeach; ?>
<?= pd_vairak(count($vs_slo) - $vs_l1, 'vid_statusi', $vs_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
<?php if ($vs_pvn): ?>
    <div class="pd-sec">
    <h4>PVN grupa</h4>
    <ul>
<?php foreach ($vs_pvn as $x): ?>
<?php // grupas nosaukums avotā sākas ar "PVN grupa ..." — zem tāda paša <h4> tas dublējas
      $vs_g = trim((string)preg_replace('/^PVN\s+grupa\s*/u', '', $x['pap'])); ?>
        <li><?= h($vs_g !== '' ? $vs_g : 'PVN grupa') ?> <span class="pd-tag"><?= h($x['st']) ?></span></li>
<?php endforeach; ?>
    </ul>
    </div>
<?php endif; ?>
<?php if ($vs_min): ?>
    <div class="pd-sec">
    <h4>Minimālās algas maksātāju saraksts</h4>
    <ul>
<?php foreach (array_slice($vs_min, 0, $vs_l2) as $x): ?>
        <li><span class="pd-muted"><?= h(trim($x['no'] . ' – ' . $x['lidz'], ' –')) ?></span></li>
<?php endforeach; ?>
<?= pd_vairak(count($vs_min) - $vs_l2, 'vid_statusi', $vs_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
</div>
<p class="pd-note"><?php if ($vs_slo): ?>Sabiedriskā labuma statuss dod ziedotājiem nodokļu atlaidi un uzliek atskaitīšanās pienākumu. <?php endif; ?>
<?php if ($vs_min): ?>VID publicē darba devējus, kuru darbinieku vidējais atalgojums ir valstī noteiktās minimālās algas apmērā — tas ir novērojums, ne pārkāpums. <?php endif; ?>
Avots: <a href="https://data.gov.lv/dati/lv/organization/valsts-ienemumu-dienests" rel="noopener">VID atvērtie dati</a>.</p>
