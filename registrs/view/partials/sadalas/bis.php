<?php
/**
 * Sadaļa "Būvkomersanta reģistrs" (BIS). Divi datu veidi vienā tabulā:
 *   darbiba — gada ieņēmumi būvniecībā un nodarbināto skaits (paša komersanta
 *             iesniegti dati, NE gada pārskats — tie var atšķirties);
 *   statuss — reģistra statusa izmaiņu vēsture (Aktīvs / Apturēts / Izslēgts).
 */
/** @var array $page_data */
$bs_rows = $page_data['results']['bis'] ?? [];
if (!is_array($bs_rows) || !$bs_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';

$bs_gadi = []; $bs_st = []; $bs_nr = '';
foreach ($bs_rows as $r) {
    $v = trim((string)($r['veids'] ?? ''));
    if ($bs_nr === '') $bs_nr = trim((string)($r['bis_nr'] ?? ''));
    if ($v === 'darbiba') {
        $g = trim((string)($r['gads'] ?? ''));
        if ($g === '') continue;
        // Par vienu gadu komersants mēdz iesniegt VAIRĀKUS pārskatus (labojumus).
        // Bez šī nosacījuma pēdējā rinda pārrakstīja iepriekšējo neatkarīgi no
        // datuma, un lapā nokļuva nejaušs — bieži vecāks vai tukšs — iesniegums
        // (audits 2026-08-19). Ņemam jaunāko pēc iesniegšanas datuma.
        $dt = substr(trim((string)($r['datums'] ?? '')), 0, 10);
        if (isset($bs_gadi[$g]) && $dt < $bs_gadi[$g]['dt']) continue;
        $bs_gadi[$g] = [
            'ien' => (float)($r['ienemumi'] ?? 0),
            'lv'  => (float)($r['apjoms_lv'] ?? 0),
            'arp' => (float)($r['apjoms_arpus'] ?? 0),
            'nod' => (int)($r['nodarb'] ?? 0),
            'dt'  => $dt,
        ];
    } elseif ($v === 'statuss') {
        $bs_st[] = ['d' => substr(trim((string)($r['datums'] ?? '')), 0, 10),
                    's' => trim((string)($r['statuss'] ?? ''))];
    }
}
if (!$bs_gadi && !$bs_st) return;
krsort($bs_gadi);
usort($bs_st, static fn($a, $b) => strcmp($b['d'], $a['d']));
// Kopsavilkumā vajag REĀLO reģistra statusu, ne tehnisko "Informatīvs" ierakstu,
// kas datu kopā mēdz būt jaunākais (audits 2026-08-19).
$bs_pedejais = '';
foreach ($bs_st as $x) {
    if ($x['s'] !== '' && $x['s'] !== 'Informatīvs') { $bs_pedejais = $x['s']; break; }
}
if ($bs_pedejais === '') $bs_pedejais = $bs_st[0]['s'] ?? '';

$bs_pilns = $pd_pilns ?? false;
$bs_lg = pd_limits($bs_pilns, 12);
$bs_ls = pd_limits($bs_pilns, 8);
$pd_nos = 'Būvkomersanta reģistrs';
$pd_n   = count($bs_gadi) + count($bs_st);
$pd_kops = $bs_pedejais !== '' ? $bs_pedejais : (count($bs_gadi) . ' gadi');
$bs_mx = 0.0;
foreach ($bs_gadi as $g) if ($g['ien'] > $bs_mx) $bs_mx = $g['ien'];
?>
<div class="pd-body">
<?php if ($bs_gadi): ?>
    <div class="pd-sec">
    <h4>Ieņēmumi būvniecībā un nodarbinātie</h4>
    <ul class="pd-gadi">
<?php foreach (array_slice($bs_gadi, 0, $bs_lg, true) as $g => $d): ?>
        <li><span class="pd-gads"><?= h($g) ?></span><span class="pd-bar" style="width:<?= $bs_mx > 0 ? max(2, (int)round(100 * $d['ien'] / $bs_mx)) : 2 ?>%"></span><span class="pd-muted"><?= h(pd_eur($d['ien'])) ?><?= $d['nod'] > 0 ? ' · ' . (int)$d['nod'] . ' nod.' : '' ?><?= $d['arp'] > 0 ? ' · ārpus LV ' . h(pd_eur($d['arp'])) : '' ?></span></li>
<?php endforeach; ?>
<?= pd_vairak(count($bs_gadi) - $bs_lg, 'bis', $bs_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
<?php if ($bs_st): ?>
    <div class="pd-sec">
    <h4>Statusa vēsture<?= $bs_nr !== '' ? ' · reģ. Nr. ' . h($bs_nr) : '' ?></h4>
    <ul>
<?php foreach (array_slice($bs_st, 0, $bs_ls) as $x): ?>
        <li><span class="pd-tag<?= $x['s'] === 'Aktīvs' ? ' pd-tag-ok' : ($x['s'] === 'Izslēgts' ? ' pd-tag-bad' : '') ?>"><?= h($x['s']) ?></span>
            <span class="pd-muted"><?= h($x['d']) ?></span></li>
<?php endforeach; ?>
<?= pd_vairak(count($bs_st) - $bs_ls, 'bis', $bs_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
</div>
<p class="pd-note">Būvkomersantu reģistrā jāreģistrējas visiem, kas sniedz būvniecības pakalpojumus.
Ieņēmumi un nodarbināto skaits ir <strong>paša komersanta iesniegtie</strong> dati par būvniecības darbību —
tie var atšķirties no gada pārskata apgrozījuma, jo aptver tikai būvniecību.
Avots: <a href="https://data.gov.lv/dati/lv/dataset/bis_trsffj40aqhi52ch4mctsw" rel="noopener">BIS būvkomersantu dati</a>.</p>
