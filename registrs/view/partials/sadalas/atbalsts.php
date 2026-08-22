<?php
/**
 * Sadaļa "Valsts atbalsts (de minimis)". Avotā ir burtiski atkārtoti ieraksti
 * (102 grupas / 157 liekas rindas), tāpēc deduplicējam pēc pilnas atslēgas,
 * ieskaitot projekta hešu — bez tā septiņi atsevišķi piešķīrumi saplūst trijos.
 * GDPR: fizisko personu rindas izmestas jau būvē; project_title ir hešs.
 */
/** @var array $page_data */
$dm_rows = $page_data['results']['deminimis'] ?? [];
if (!is_array($dm_rows) || !$dm_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';

$dm_total = 0.0; $dm_by_giver = []; $dm_by_instr = []; $dm_list = [];
$dm_min = $dm_max = ''; $dm_dubli = 0; $dm_redzets = [];
foreach ($dm_rows as $r) {
    $atsl = implode('|', [
        trim((string)($r['assign_date'] ?? '')), trim((string)($r['assigner_title'] ?? '')),
        trim((string)($r['program_title'] ?? '')), trim((string)($r['instrument_title'] ?? '')),
        trim((string)($r['assigned_amount'] ?? '')), trim((string)($r['project_title'] ?? '')),
    ]);
    if (isset($dm_redzets[$atsl])) { $dm_dubli++; continue; }
    $dm_redzets[$atsl] = true;
    $amt = (float)($r['assigned_amount'] ?? 0);
    $dm_total += $amt;
    $giver = trim((string)($r['assigner_title'] ?? '')) ?: 'Nav norādīts';
    if (!isset($dm_by_giver[$giver])) $dm_by_giver[$giver] = [0.0, 0];
    $dm_by_giver[$giver][0] += $amt; $dm_by_giver[$giver][1]++;
    $instr = trim(preg_replace('/^\d+\s+/', '', (string)($r['instrument_title'] ?? '')));
    if ($instr !== '') $dm_by_instr[$instr] = ($dm_by_instr[$instr] ?? 0) + $amt;
    $d = substr(trim((string)($r['assign_date'] ?? '')), 0, 10);
    if ($d !== '') {
        if ($dm_min === '' || $d < $dm_min) $dm_min = $d;
        if ($dm_max === '' || $d > $dm_max) $dm_max = $d;
    }
    $dm_list[] = ['d' => $d, 'amt' => $amt, 'giver' => $giver,
                  'prog' => trim((string)($r['program_title'] ?? ''))];
}
if (!$dm_list) return;
arsort($dm_by_instr);
uasort($dm_by_giver, static fn($a, $b) => $b[0] <=> $a[0]);
usort($dm_list, static fn($a, $b) => $b['amt'] <=> $a['amt']);

$dm_pilns = $pd_pilns ?? false;
$dm_lim = pd_limits($dm_pilns, 6);
$pd_nos = 'Valsts atbalsts (de minimis)';
$pd_n   = count($dm_list);
$pd_kops = pd_eur($dm_total);
?>
<div class="pd-body">
    <div class="pd-sec">
    <h4>Lielākie piešķīrumi — kopā <?= h(pd_eur($dm_total)) ?></h4>
    <ul>
<?php foreach (array_slice($dm_list, 0, $dm_lim) as $x): ?>
        <li><strong><?= h(pd_eur($x['amt'])) ?></strong>
            <?php if ($x['d'] !== ''): ?><span class="pd-muted"><?= h($x['d']) ?></span><?php endif; ?>
            <br><span class="pd-muted"><?= h($x['giver']) ?><?php if ($x['prog'] !== ''): ?>: <?= h($x['prog']) ?><?php endif; ?></span></li>
<?php endforeach; ?>
<?= pd_vairak(count($dm_list) - $dm_lim, 'atbalsts', $dm_pilns) ?>
    </ul>
    </div>
<?php if (count($dm_by_instr) > 1): ?>
    <div class="pd-sec">
    <h4>Pa atbalsta veidiem</h4>
    <ul>
<?php foreach (array_slice($dm_by_instr, 0, 5, true) as $instr => $sum): ?>
        <li><?= h($instr) ?> <span class="pd-muted"><?= h(pd_eur((float)$sum)) ?></span></li>
<?php endforeach; ?>
    </ul>
    </div>
<?php endif; ?>
</div>
<p class="pd-note"><?php if ($dm_dubli > 0): ?>Avotā šim uzņēmumam ir <?= (int)$dm_dubli ?> atkārtoti identiski ieraksti — tos skaitām vienreiz. <?php endif; ?>
<em>De minimis</em> ir neliela apjoma valsts atbalsts bez Eiropas Komisijas atļaujas (parasti līdz 300 000 € trīs gados).
Rādītie <?= $dm_min !== '' && $dm_max !== '' ? h($dm_min) . ' – ' . h($dm_max) : 'pēdējo gadu' ?> piešķīrumi <strong>nav pilna vēsture</strong>:
uzskaites sistēma publisko tikai kārtējo un divus iepriekšējos fiskālos gadus. Aizdevumi un galvojumi ir jāatmaksā.
Avots: <a href="https://data.gov.lv/dati/lv/dataset/pieskirtais-de-minimis-atbalsts-latvija" rel="noopener">FM de minimis uzskaite</a>.</p>
