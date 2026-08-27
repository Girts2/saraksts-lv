<?php
/**
 * Sadaļa "Publiskie iepirkumi" (EIS). Aprēķins — lib/iepirkumi_kopsavilkums.php,
 * to pašu lieto MI prompts. Kopsummā tikai VIENA uzvarētāja līgumi: vienošanās ar
 * vairākiem uzvarētājiem avotā atkārto pilnu summu katram (sk. build/convert.php).
 */
/** @var array $page_data */
$ip_rows = $page_data['results']['iepirkumi'] ?? [];
if (!is_array($ip_rows) || !$ip_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/iepirkumi_kopsavilkums.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';
$k = reg_iepirkumi_kopsavilkums($ip_rows);
if ($k['n'] === 0 && !$k['kopigi']) return;

$pd_nos = 'Publiskie iepirkumi';
$pd_n   = $k['n'] + count($k['kopigi']);
$pd_kops = $k['kopsumma'] > 0 ? pd_eur($k['kopsumma']) : '';
$ip_pilns = $pd_pilns ?? false;
$ip_lim = pd_limits($ip_pilns, 6);
$ip_lim_p = pd_limits($ip_pilns, 5);
$ip_lim_k = pd_limits($ip_pilns, 3);
$eis = static function (string $iid, string $teksts): string {
    $iid = preg_replace('/\D/', '', $iid);
    if ($iid === '') return h($teksts);
    return '<a href="https://www.eis.gov.lv/EKEIS/Supplier/Procurement/' . h($iid)
        . '" rel="nofollow noopener" target="_blank">' . h($teksts) . '</a>';
};
?>
<div class="pd-body">
<?php if ($k['solo']): ?>
    <div class="pd-sec">
    <h4>Lielākie līgumi<?= $k['kopsumma'] > 0 ? ' — kopā ' . h(pd_eur($k['kopsumma'])) : '' ?></h4>
    <ul>
<?php foreach (array_slice($k['solo'], 0, $ip_lim) as $x): ?>
        <li><strong><?= $x['zin'] ? h(pd_eur($x['amt'])) : 'summa nav publicēta' ?></strong>
            <?php if ($x['d'] !== ''): ?><span class="pd-muted"><?= h(substr($x['d'], 0, 7)) ?></span><?php endif; ?>
            <?php if ($x['izb']): ?><span class="pd-tag pd-tag-bad">izbeigts</span><?php endif; ?>
            <br><?= $eis($x['iid'], $x['nos']) ?>
            <span class="pd-muted">· <?= h($x['pas']) ?></span></li>
<?php endforeach; ?>
<?= pd_vairak($k['n'] - $ip_lim, 'iepirkumi', $ip_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
<?php if (count($k['pasutitaji']) > 1): ?>
    <div class="pd-sec">
    <h4>Pasūtītāji</h4>
    <ul>
<?php foreach (array_slice($k['pasutitaji'], 0, $ip_lim_p, true) as $g => [$sum, $cnt, $rn]): ?>
        <li><?= h($g) ?> <span class="pd-muted"><?= $sum > 0 ? h(pd_eur($sum)) . ', ' : '' ?><?= (int)$cnt ?>×</span></li>
<?php endforeach; ?>
<?= pd_vairak(count($k['pasutitaji']) - $ip_lim_p, 'iepirkumi', $ip_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
<?php if (count($k['gadi']) > 1): ?>
    <div class="pd-sec">
    <h4>Pa gadiem</h4>
    <ul class="pd-gadi">
<?php $mx = 0.0; foreach ($k['gadi'] as [$sg, $cg]) if ($sg > $mx) $mx = $sg; ?>
<?php foreach ($k['gadi'] as $y => [$sum, $cnt]): ?>
        <li><span class="pd-gads"><?= h($y) ?></span><span class="pd-bar" style="width:<?= $mx > 0 ? max(2, (int)round(100 * $sum / $mx)) : 2 ?>%"></span><span class="pd-muted"><?= $sum > 0 ? h(pd_eur($sum)) : '—' ?> <?= (int)$cnt ?>×</span></li>
<?php endforeach; ?>
    </ul>
    </div>
<?php endif; ?>
<?php if ($k['kopigi']): ?>
    <div class="pd-sec">
    <h4>Kopīgas vienošanās (<?= count($k['kopigi']) ?>)</h4>
    <ul>
<?php foreach (array_slice($k['kopigi'], 0, $ip_lim_k) as $x): ?>
        <li><?= $x['zin'] ? '<strong>' . h(pd_eur($x['amt'])) . '</strong> ' : '' ?><span class="pd-muted">kopā ar <?= (int)$x['uzv'] - 1 ?> <?= pd_dsk((int)$x['uzv'] - 1, 'citu piegādātāju', 'citiem piegādātājiem') ?></span><?php if ($x['izb']): ?> <span class="pd-tag pd-tag-bad">izbeigts</span><?php endif; ?>
            <br><?= $eis($x['iid'], $x['nos']) ?></li>
<?php endforeach; ?>
<?= pd_vairak(count($k['kopigi']) - $ip_lim_k, 'iepirkumi', $ip_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
</div>
<p class="pd-note">Summa ir līgumos nolīgtā, ne izmaksātā.
<?php if ($k['kopigi']): ?>Kopīgās vienošanās kopsummā <strong>nav ieskaitītas</strong> — tur apjoms ir maksimālais un dalīts ar pārējiem uzvarētājiem.<?php endif; ?>
<?php if ($k['bez_summas'] > 0): ?> <?= (int)$k['bez_summas'] ?> <?= pd_dsk($k['bez_summas'], 'līgumam', 'līgumiem') ?> summa avotā nav publicēta.<?php endif; ?>
<?php if ($k['izbeigti'] > 0): ?> <?= (int)$k['izbeigti'] ?> <?= pd_dsk($k['izbeigti'], 'līgums izbeigts', 'līgumi izbeigti') ?> pirms termiņa.<?php endif; ?>
<?php if ($k['sapludinatas'] > 0): ?> <?= (int)$k['sapludinatas'] ?> <?= pd_dsk($k['sapludinatas'], 'dokuments ar identisku summu vienā iepirkumā sapludināts', 'dokumenti ar identisku summu vienā iepirkumā sapludināti') ?> — avots to pašu līgumu publicē vairākkārt.<?php endif; ?>
<?php if ($k['cita_val'] > 0): ?> <?= (int)$k['cita_val'] ?> <?= pd_dsk($k['cita_val'], 'vecākam līgumam summa ir latos', 'vecākiem līgumiem summa ir latos') ?> — kopsummā tie nav ieskaitīti.<?php endif; ?>
 Dati: EIS publicētie dokumenti kopš 2018. gada; 2018.–2020. gads nav pilnīgs.
 Avots: <a href="https://data.gov.lv/dati/lv/dataset/iepirkumu-rezultatu-datu-grupa" rel="noopener">EIS iepirkumu rezultāti</a>.</p>
