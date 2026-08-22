<?php
/**
 * Sadaļa "Atkritumu plūsmas" (APUS, VVD gada pārskata A3 B daļa).
 *
 * Kvantitatīva vides pēda: cik tonnu atkritumu uzņēmums gadā RADA, cik pārstrādā
 * un cik nodod tālāk. Avotā dati ir tikai par kārtējo gadu — vecākie gadi API
 * atdod tukšu failu (sk. build_dinamiskie_urli config.php).
 */
/** @var array $page_data */
$at_rows = $page_data['results']['atkritumi'] ?? [];
if (!is_array($at_rows) || !$at_rows) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';

$at_radits = 0.0; $at_apsaimn = 0.0; $at_nodots = 0.0; $at_uzkr = 0.0;
$at_veidi = []; $at_gadi = []; $at_rindas = [];
foreach ($at_rows as $r) {
    $rad = (float)($r['radits'] ?? 0);
    // APSAIMNIEKOTS = pārstrādāts + apglabāts uz vietas. Agrāk lasījām tikai
    // pārstrādāto, un apglabātais (izgāztuvē) pazuda pavisam (audits 2026-08-19).
    $apsaimn = (float)($r['parstradats'] ?? 0) + (float)($r['apglabats'] ?? 0);
    // B9 izvestie/eksportētie ir tā pati "atdots tālāk" plūsma, tikai pāri robežai —
    // bez tiem 23 uzņēmumiem sadaļa nerādījās vispār (audits 2026-08-20).
    $nod = (float)($r['nodots'] ?? 0) + (float)($r['nodots_apglabasanai'] ?? 0)
         + (float)($r['izvests_parstradei'] ?? 0) + (float)($r['izvests_apglabasanai'] ?? 0);
    $at_radits  += $rad;
    $at_apsaimn += $apsaimn;
    $at_nodots  += $nod;
    $at_uzkr    += (float)($r['uzkrats'] ?? 0);
    $at_rindas[] = ['nos' => trim((string)($r['nosaukums'] ?? '')), 'rad' => $rad, 'nod' => $nod];
    $g = trim((string)($r['gads'] ?? ''));
    if ($g !== '') $at_gadi[$g] = ($at_gadi[$g] ?? 0) + $rad;
}
// Grupu stabiņiem VIENA mērvienība uz uzņēmumu, ne sajaukta: citādi stabiņu summa
// neatbilst nevienam virsraksta skaitlim (audits 2026-08-19).
$at_grupu_veids = $at_radits > 0 ? 'rad' : 'nod';
foreach ($at_rindas as $x) {
    if ($x['nos'] === '' || $x[$at_grupu_veids] <= 0) continue;
    $at_veidi[$x['nos']] = ($at_veidi[$x['nos']] ?? 0) + $x[$at_grupu_veids];
}
// UZMANĪBU (mērīts uz 300 uzņēmumiem): 536 no 618 uzņēmumiem RADĪTAIS daudzums ir 0
// un vienīgais aizpildītais lauks ir "nodots pārstrādei" — tie ir atkritumu radītāji,
// kas visu nodod tālāk. Bez $at_nodots šajā nosacījumā sadaļa nerādījās 87 % gadījumu.
if ($at_radits <= 0 && $at_apsaimn <= 0 && $at_nodots <= 0 && $at_uzkr <= 0) return;
arsort($at_veidi);
krsort($at_gadi);
$at_t = static function (float $v): string {
    if ($v >= 1000) return number_format($v, 0, ',', ' ') . ' t';
    if ($v >= 10)   return number_format($v, 1, ',', ' ') . ' t';
    return number_format($v, 3, ',', ' ') . ' t';
};

$at_pilns = $pd_pilns ?? false;
$at_lim = pd_limits($at_pilns, 8);
$pd_nos = 'Atkritumu plūsmas';
$pd_n   = count($at_rows);
// Kopsavilkumā rādām lielāko jēgpilno skaitli — radītājiem tas ir radītais,
// bet lielākajai daļai vienīgais aizpildītais ir nodotais.
$pd_kops = $at_radits > 0 ? $at_t($at_radits) . ' radīts'
    : ($at_nodots > 0 ? $at_t($at_nodots) . ' nodots' : ($at_apsaimn > 0 ? $at_t($at_apsaimn) . ' apsaimniekots' : ''));
?>
<div class="pd-body">
    <div class="pd-sec">
    <h4>Kopā<?= count($at_gadi) === 1 ? ' ' . h((string)array_key_first($at_gadi)) . '. gadā' : '' ?></h4>
    <ul>
<?php if ($at_radits > 0): ?>
        <li>Radīts <strong><?= h($at_t($at_radits)) ?></strong></li>
<?php endif; ?>
<?php if ($at_apsaimn > 0): ?>
        <li>Apsaimniekots uz vietas <span class="pd-muted"><?= h($at_t($at_apsaimn)) ?></span> <span class="pd-muted">(pārstrādāts un apglabāts)</span></li>
<?php endif; ?>
<?php if ($at_nodots > 0): ?>
        <li>Nodots vai izvests tālākai apstrādei <?= $at_radits > 0 ? '<span class="pd-muted">' . h($at_t($at_nodots)) . '</span>' : '<strong>' . h($at_t($at_nodots)) . '</strong>' ?></li>
<?php endif; ?>
<?php /* "Uzkrātais daudzums gada beigās" (B10) NETIEK RĀDĪTS: 2 617 rindās tas ir
         NEGATĪVS (līdz -11 465 t), t.i. avotā to lieto kā krājuma izmaiņu, ne atlikumu.
         Skaitli, kura nozīmi nevaru pierādīt, labāk nerādīt nekā rādīt nepareizi. */ ?>
    </ul>
    </div>
<?php if ($at_veidi): ?>
    <div class="pd-sec">
    <h4>Lielākās <?= $at_grupu_veids === 'rad' ? 'radīto' : 'nodoto' ?> atkritumu grupas</h4>
    <ul class="pd-gadi pd-joslas">
<?php $at_mx = max($at_veidi); ?>
<?php foreach (array_slice($at_veidi, 0, $at_lim, true) as $nos => $v): ?>
        <li><span class="pd-bar" style="width:<?= $at_mx > 0 ? max(2, (int)round(100 * $v / $at_mx)) : 2 ?>%"></span><span class="pd-muted"><?= h($at_t($v)) ?> · <?= h(mb_substr($nos, 0, 60, 'UTF-8')) ?></span></li>
<?php endforeach; ?>
<?= pd_vairak(count($at_veidi) - $at_lim, 'atkritumi', $at_pilns) ?>
    </ul>
    </div>
<?php endif; ?>
</div>
<p class="pd-note">Dati ir no uzņēmuma paša iesniegtā gada pārskata par atkritumiem (veidlapa Nr. 3).
Atkritumu radīšana pati par sevi nav pārkāpums — pārskats jāiesniedz visiem, kas pārsniedz noteiktos apjomus.
Avots: <a href="https://data.gov.lv/dati/lv/dataset/apus-dati-gada-parskatam-par-atkritumiem-nr-3-atkritumi" rel="noopener">VVD APUS gada pārskati</a>.</p>
