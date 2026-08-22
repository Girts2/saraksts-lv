<?php
/**
 * Sadaļa "PTAC licences un reģistri". TIKAI licences — PTAC uzraudzības ieraksti
 * (melnais saraksts, lēmumi ar soda naudu, apņemšanās) ir sarkanajā "Tiesiskais
 * statuss" panelī lapas augšā, kur apmeklētājs meklē riska faktus.
 */
/** @var array $page_data */
$pt_rows = $page_data['results']['ptac'] ?? [];
if (!is_array($pt_rows) || !$pt_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';

$PT_VEIDI = [
    'kredits' => 'Patērētāju kreditēšanas licence',
    'paradi'  => 'Parāda atgūšanas pakalpojumu licence',
    'hipotek' => 'Hipotekārā kredīta starpnieks',
    'stridi'  => 'Ārpustiesas patērētāju strīdu risinātājs',
];
$pt = []; $pt_akt = 0;
foreach ($pt_rows as $r) {
    $v = trim((string)($r['veids'] ?? ''));
    if (!isset($PT_VEIDI[$v])) continue;
    $st = trim((string)($r['statuss'] ?? ''));
    $an = substr(trim((string)($r['anulets'] ?? '')), 0, 10);
    $lidz = substr(trim((string)($r['lidz_dat'] ?? '')), 0, 10);
    $speka = in_array($st, ['Aktīva', 'Reģistrā'], true) || ($st === '' && $lidz === '' && $an === '');
    if ($speka) $pt_akt++;
    $pt[] = ['nos' => $PT_VEIDI[$v], 'st' => $st, 'speka' => $speka,
             'nr' => trim((string)($r['numurs'] ?? '')),
             'no' => substr(trim((string)($r['no_dat'] ?? '')), 0, 10), 'lidz' => $lidz,
             'ap' => substr(trim((string)($r['apturets'] ?? '')), 0, 10), 'an' => $an,
             'pap' => trim((string)($r['papildus'] ?? ''))];
}
if (!$pt) return;
usort($pt, static fn($a, $b) => ($b['speka'] <=> $a['speka']) ?: strcmp($b['no'], $a['no']));

$pd_nos = 'PTAC licences un reģistri';
$pd_n   = count($pt);
$pd_kops = $pt_akt > 0 ? $pt_akt . ' spēkā' : 'nav spēkā';
?>
<div class="pd-body">
    <div class="pd-sec">
    <ul>
<?php foreach ($pt as $l): ?>
        <li><strong><?= h($l['nos']) ?></strong><?php if ($l['nr'] !== ''): ?> <span class="pd-muted">Nr. <?= h($l['nr']) ?></span><?php endif; ?>
            <?php if ($l['st'] !== ''): ?><span class="pd-tag<?= $l['speka'] ? ' pd-tag-ok' : '' ?>"><?= h($l['st']) ?></span><?php endif; ?>
            <br><span class="pd-muted"><?php
                $d = [];
                if ($l['no'] !== '')   $d[] = 'no ' . $l['no'];
                if ($l['lidz'] !== '') $d[] = 'līdz ' . $l['lidz'];
                if ($l['ap'] !== '')   $d[] = 'apturēta ' . $l['ap'];
                if ($l['an'] !== '')   $d[] = 'anulēta ' . $l['an'];
                echo h(implode(' · ', $d));
                if ($l['pap'] !== '') echo ($d ? ' · ' : '') . h($l['pap']);
            ?></span></li>
<?php endforeach; ?>
    </ul>
    </div>
</div>
<p class="pd-note">Patērētāju kreditēšana, parāda atgūšana un hipotekārā kredīta starpniecība ir
licencējamas darbības — bez PTAC licences tās veikt nedrīkst.
<?php if ($pt_akt === 0): ?>Šim uzņēmumam <strong>spēkā esošas licences nav</strong>. <?php endif; ?>
Avots: <a href="https://data.gov.lv/dati/lv/organization/patuztiesaizscentrs" rel="noopener">PTAC atvērtie dati</a>.</p>
