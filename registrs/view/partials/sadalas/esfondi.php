<?php
/**
 * Sadaļa "ES fondu projekti" (CFLA). Trīs lomas ar trīs dažādām naudām, ko
 * NEDRĪKST saskaitīt — sk. lib/esfondi_kopsavilkums.php.
 */
/** @var array $page_data */
$ef_rows = $page_data['results']['es_fondi'] ?? [];
if (!is_array($ef_rows) || !$ef_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/esfondi_kopsavilkums.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';
$ef = reg_esfondi_kopsavilkums($ef_rows);
$san = $ef['sanemejs']; $par = $ef['partneris']; $izp = $ef['izpilditajs'];
if ($san['n'] + $par['n'] + $izp['n'] === 0) return;

$pd_nos = 'ES fondu projekti';
$pd_n   = $san['n'] + $par['n'] + $izp['n'];
$ef_pilns = $pd_pilns ?? false;
$ef_l1 = pd_limits($ef_pilns, 5);
$ef_l2 = pd_limits($ef_pilns, 5);
$ef_l3 = pd_limits($ef_pilns, 4);
$pd_kops = $san['es_fin'] > 0 ? pd_eur($san['es_fin']) . ' atbalsts'
    : ($izp['summa'] > 0 ? pd_eur($izp['summa']) . ' līgumos'
    : ($par['n'] > 0 ? $par['n'] . ' kā partnerim' : ''));
?>
<div class="pd-body">
<?php if ($san['projekti']): ?>
    <div class="pd-sec">
    <h4>Kā finansējuma saņēmējam (<?= (int)$san['n'] ?>)</h4>
    <ul>
<?php foreach (array_slice($san['projekti'], 0, $ef_l1) as $p): ?>
        <li><strong><?= $p['es'] > 0 ? h(pd_eur($p['es'])) : 'ES daļa nav norādīta' ?></strong>
            <?php if ($p['izmaksas'] > 0): ?><span class="pd-muted">no <?= h(pd_eur($p['izmaksas'])) ?></span><?php endif; ?>
            <?php if ($p['statuss'] !== ''): ?><span class="pd-tag<?= $p['statuss'] === 'Pabeigts' ? ' pd-tag-ok' : '' ?>"><?= h($p['statuss']) ?></span><?php endif; ?>
            <br><?= h($p['nos']) ?>
            <span class="pd-muted">· <?= h($p['fonds']) ?><?php $a = pd_men($p['sakums']); $b = pd_men($p['beigas']); if ($a || $b): ?> · <?= h(trim($a . ' – ' . $b, ' –')) ?><?php endif; ?></span></li>
<?php endforeach; ?>
<?= pd_vairak($san['n'] - $ef_l1, 'esfondi', $ef_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
<?php if ($izp['ligumi']): ?>
    <div class="pd-sec">
    <h4>Kā izpildītājam projektos (<?= (int)$izp['n'] ?><?= $izp['summa'] > 0 ? ', ' . h(pd_eur($izp['summa'])) : '' ?>)</h4>
    <ul>
<?php foreach (array_slice($izp['ligumi'], 0, $ef_l2) as $p): ?>
        <li><strong><?= $p['summa'] > 0 ? h(pd_eur($p['summa'])) : 'summa nav norādīta' ?></strong>
            <?php if ($p['datums'] !== ''): ?><span class="pd-muted"><?= h(pd_men($p['datums'])) ?></span><?php endif; ?>
            <br><?= h($p['nos']) ?><?php if ($p['sanem'] !== ''): ?> <span class="pd-muted">· <?= h($p['sanem']) ?></span><?php endif; ?></li>
<?php endforeach; ?>
<?= pd_vairak($izp['n'] - $ef_l2, 'esfondi', $ef_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
<?php if ($par['projekti']): ?>
    <div class="pd-sec">
    <h4>Kā sadarbības partnerim (<?= (int)$par['n'] ?>)</h4>
    <ul>
<?php foreach (array_slice($par['projekti'], 0, $ef_l3) as $p): ?>
        <li><?= h($p['nos']) ?>
            <span class="pd-muted">· <?= h($p['fonds']) ?><?php if ($p['sanem'] !== ''): ?> · <?= h($p['sanem']) ?><?php endif; ?></span></li>
<?php endforeach; ?>
<?= pd_vairak($par['n'] - $ef_l3, 'esfondi', $ef_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
</div>
<p class="pd-note">Trīs lomas, kuru naudu nedrīkst saskaitīt: saņēmējam tas ir saņemtais atbalsts,
partnerim avotā naudas datu nav, izpildītājam — nopelnīts piegādes līgums (bez PVN).
<?php if ($san['izmaksats'] > 0): ?> Faktiski izmaksāti <?= h(pd_eur($san['izmaksats'])) ?> — tas ir <em>viss</em> attiecināmais finansējums, ne tikai ES daļa,
 un tikai par <?= (int)$san['izmaksats_n'] ?> no <?= (int)$san['n'] ?> <?= pd_dsk($san['n'], 'projekta', 'projektiem') ?>, par kuriem maksājumu dati ir publicēti.<?php endif; ?>
 Avots: <a href="https://data.gov.lv/dati/lv/dataset/es-lidzfinansetie-projekti" rel="noopener">CFLA ES fondu projekti</a>.</p>
