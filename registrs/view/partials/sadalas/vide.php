<?php
/**
 * Sadaļa "Vides atļaujas un licences" (VVD). Piecas kopas: A/B kategorijas
 * piesārņojošās darbības atļaujas, C kategorijas reģistrācijas, atkritumu
 * apsaimniekošana, ūdens resursi, zemes dzīles.
 */
/** @var array $page_data */
$vd_rows = $page_data['results']['vide'] ?? [];
if (!is_array($vd_rows) || !$vd_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';

$VD_NOS = [
    'ab' => 'A/B kategorijas piesārņojošas darbības atļauja',
    'c' => 'C kategorijas piesārņojošas darbības reģistrācija',
    'atkritumi' => 'Atkritumu apsaimniekošanas atļauja',
    'udens' => 'Ūdens resursu lietošanas atļauja',
    'zemes' => 'Zemes dzīļu izmantošanas licence',
];
// "Apturēta" un "Apturēta daļā" NAV spēkā esoša atļauja — agrāk tās skaitījās
// spēkā un tika krāsotas zaļas (audits 2026-08-19).
$VD_BEIGUSAS = ['Atcelta', 'Beidzies termiņš', 'Anulēta', 'Izslēgts no reģistra',
                'Apturēta', 'Apturēta daļā'];
// VIENA ATĻAUJA = viena rinda. Avotā atkritumu apsaimniekošanas atļaujai ir
// atsevišķa rinda katram darbības veidam (savākšana, pārvadāšana, šķirošana…),
// tāpēc skaits un "N spēkā" bija pārspīlēts līdz 3,5× (audits 2026-08-19).
// Grupējam pēc veids+numurs un darbību aprakstus apvienojam vienā rindā.
$vd_grupas = [];
foreach ($vd_rows as $r) {
    $v = trim((string)($r['veids'] ?? ''));
    if (!isset($VD_NOS[$v])) continue;
    $st = trim((string)($r['statuss'] ?? ''));
    $nr = trim((string)($r['numurs'] ?? ''));
    $atsl = $nr !== '' ? $v . '|' . $nr : $v . '|#' . count($vd_grupas);
    $apr = trim((string)($r['apraksts'] ?? ''));
    if (!isset($vd_grupas[$atsl])) {
        $vd_grupas[$atsl] = ['v' => $v, 'nos' => $VD_NOS[$v], 'st' => $st,
            'speka' => !in_array($st, $VD_BEIGUSAS, true), 'nr' => $nr,
            'no' => substr(trim((string)($r['no_dat'] ?? '')), 0, 10),
            'lidz' => substr(trim((string)($r['lidz_dat'] ?? '')), 0, 10),
            'iek' => trim((string)($r['nosaukums'] ?? '')), 'apr' => []];
    } else {
        // Pārlicencēšana: tai pašai atļaujai avotā ir gan vecās (beigušās), gan
        // jaunā (spēkā) rinda — grupai jārāda AKTUĀLĀKĀ, ne pirmā pēc kārtas
        // (audits 2026-08-20: CS19ZD0111 rādījās "Beidzies termiņš", kaut pārlicencēta).
        $vd_g =& $vd_grupas[$atsl];
        $vd_sp = !in_array($st, $VD_BEIGUSAS, true);
        $vd_no = substr(trim((string)($r['no_dat'] ?? '')), 0, 10);
        if (($vd_sp && !$vd_g['speka']) || ($vd_sp === $vd_g['speka'] && $vd_no > $vd_g['no'])) {
            $vd_g['st'] = $st; $vd_g['speka'] = $vd_sp; $vd_g['no'] = $vd_no;
            $vd_g['lidz'] = substr(trim((string)($r['lidz_dat'] ?? '')), 0, 10);
            $vd_iek = trim((string)($r['nosaukums'] ?? ''));
            if ($vd_iek !== '') $vd_g['iek'] = $vd_iek;
        }
        unset($vd_g);
    }
    if ($apr !== '' && !in_array($apr, $vd_grupas[$atsl]['apr'], true)) $vd_grupas[$atsl]['apr'][] = $apr;
}
if (!$vd_grupas) return;
$vd = []; $vd_speka = 0;
foreach ($vd_grupas as $g) {
    $g['apr'] = implode('; ', $g['apr']);
    if ($g['speka']) $vd_speka++;
    $vd[] = $g;
}
usort($vd, static fn($a, $b) => ($b['speka'] <=> $a['speka']) ?: strcmp($b['no'], $a['no']));

$vd_pilns = $pd_pilns ?? false;
$vd_lim = pd_limits($vd_pilns, 10);
$pd_nos = 'Vides atļaujas un licences';
$pd_n   = count($vd);
$pd_kops = $vd_speka > 0 ? $vd_speka . ' spēkā' : 'beigušās';
?>
<div class="pd-body">
    <div class="pd-sec">
    <ul>
<?php foreach (array_slice($vd, 0, $vd_lim) as $x): ?>
        <li><strong><?= h($x['nos']) ?></strong><?php if ($x['nr'] !== ''): ?> <span class="pd-muted">Nr. <?= h($x['nr']) ?></span><?php endif; ?>
            <?php if ($x['st'] !== ''): ?><span class="pd-tag<?= $x['speka'] ? ' pd-tag-ok' : '' ?>"><?= h($x['st']) ?></span><?php endif; ?>
            <br><span class="pd-muted"><?php
                $d = [];
                if ($x['no'] !== '') $d[] = 'no ' . $x['no'];
                if ($x['lidz'] !== '') $d[] = 'līdz ' . $x['lidz'];
                echo h(implode(' · ', $d));
                $papild = $x['iek'] !== '' ? $x['iek'] : $x['apr'];
                if ($papild !== '') echo ($d ? ' · ' : '') . h(mb_substr($papild, 0, 90, 'UTF-8'));
            ?></span></li>
<?php endforeach; ?>
<?= pd_vairak(count($vd) - $vd_lim, 'vide', $vd_pilns) ?>
    </ul>
    </div>
</div>
<p class="pd-note">A un B kategorijas atļauja ir nepieciešama piesārņojošām darbībām ar lielāko ietekmi,
C kategorijā pietiek ar reģistrāciju. Atļauja pati par sevi nav pārkāpums — tā nozīmē, ka darbība ir
uzraudzīta. Avots: <a href="https://data.gov.lv/dati/lv/dataset/izsniegtas-atlaujas-un-licences" rel="noopener">VVD izsniegtās atļaujas un licences</a>.</p>
