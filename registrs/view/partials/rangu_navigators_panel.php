<?php
/**
 * Vieta nozarē pēc apgrozījuma — rangu navigators lapas beigās, tūlīt zem "Tuvākie uzņēmumi".
 *
 * Dati un pamatojums: registrs/lib/rangu_navigators.php. Īsi: grafiku "Pozīcija nozarē" uzņēmumu nosaukumi ir
 * tikai JS uznirstošajos logos, un Googlebot neklikšķina — šis saraksts dod tās pašas saites statiskā HTML.
 * Cilvēkiem to pašu rāda grafiki (klikšķis uz punkta atver profilu), tāpēc bloks ir nevis grafiku panelī,
 * bet pie pārējām iekšējām saitēm (Girta lēmums 2026-10-15; 10-14 tas bija "Pozīcija nozarē" apakšā).
 *
 * Panelis pats klusi iziet, ja bibliotēkas nav vai uzņēmums nozares sarakstā nav atrodams.
 *
 * @var array $page_data
 */
// is_file: ja panelis serverī nonāk pirms bibliotēkas, require_once nogāztu VISU lapu (recenzija 2026-10-14).
$ip_nav = null;
$ip_lib = dirname(__DIR__, 2) . '/lib/rangu_navigators.php';
if (is_file($ip_lib)) {
    require_once $ip_lib;
    $ip_nav = reg_rangu_navigators((string)($page_data['search_reg_nr'] ?? ''), (string)($page_data['nace_code'] ?? ''));
}

if ($ip_nav !== null):
    // Tūkstošu atdalītājs — nedalāmā atstarpe: ar parasto virsraksts telefonā pārlūza "3" / "019".
    $ip_sk = static fn(int $n): string => number_format($n, 0, ',', "\u{00A0}");
    // Piezīme — tikai par tiem attālumiem, kas sarakstā tiešām ir (mazā nozarē nav ne ±125, ne ±3 125).
    $ip_att = [];
    foreach ($ip_nav['rindas'] as $ip_r) if (!$ip_r['pats']) $ip_att[abs($ip_r['rangs'] - $ip_nav['rangs'])] = true;
    $ip_att = array_keys($ip_att);
    sort($ip_att);
    $ip_tali = array_map($ip_sk, array_values(array_filter($ip_att, static fn(int $a): bool => $a > 1)));
    $ip_dalas = [];
    if (in_array(1, $ip_att, true)) $ip_dalas[] = 'stāv tieši blakus';
    if ($ip_tali) $ip_dalas[] = 'ir ' . (count($ip_tali) > 1 ? implode(', ', array_slice($ip_tali, 0, -1)) . ' un ' . end($ip_tali) : $ip_tali[0])
        . ' vietas augstāk vai zemāk';
    $ip_piez = 'Uzņēmumi, kas šajā sarakstā ' . implode(', un tie, kas ', $ip_dalas) . '.';
?>
<style>
/* .balance-facts ir flex konteiners — bez display: block virsraksts, piezīme un saraksts sakārtojas RINDĀ.
   margin-top — tā pati atstarpe kā starp paneļiem .single-column-layout (gap 20 px); te tā ārpus tā. */
.ip-rangi { display: block; margin-top: 20px; }
.ip-rangi .ip-piezime { color: #6b7280; font-size: 13px; margin: 0 0 8px; }
.ip-rangi ul { list-style: none; margin: 0; padding: 0; }
.ip-rangi li { display: flex; gap: 10px; align-items: baseline; padding: 4px 0; border-bottom: 1px solid #f0f1f5; }
.ip-rangi li:last-child { border-bottom: 0; }
.ip-rangi .ip-vieta { color: #6b7280; font-size: 13px; min-width: 4.2em; text-align: right; white-space: nowrap; }
.ip-rangi .ip-nos { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; }
.ip-rangi li a { text-decoration: none; }
.ip-rangi li a:hover { text-decoration: underline; }
.ip-rangi .ip-apgroz { color: #6b7280; font-size: 13px; white-space: nowrap; }
.ip-rangi li.ip-pats { font-weight: 600; }
.ip-rangi .ip-sis { font-weight: 400; color: #6b7280; font-size: 13px; }
</style>
<div class="balance-facts ip-rangi">
    <h2><?= $ip_nav['kods'] === '0000' ? 'Vieta pēc apgrozījuma starp uzņēmumiem bez norādītas nozares' : 'Vieta nozarē pēc apgrozījuma' ?>:
        <?= $ip_sk($ip_nav['rangs']) ?>. no <?= $ip_sk($ip_nav['kopa']) ?></h2>
    <p class="ip-piezime"><?= h($ip_piez) ?></p>
    <ul>
<?php foreach ($ip_nav['rindas'] as $ip_r): ?>
<?php if ($ip_r['pats']): ?>
        <li class="ip-pats" aria-current="true"><span class="ip-vieta"><?= $ip_sk($ip_r['rangs']) ?>.</span><span class="ip-nos"><?= h($ip_r['name']) ?> <span class="ip-sis">(šis uzņēmums)</span></span><span class="ip-apgroz"><?= tpl_num($ip_r['turnover'], '€') ?></span></li>
<?php else: ?>
        <li><span class="ip-vieta"><?= $ip_sk($ip_r['rangs']) ?>.</span><span class="ip-nos"><a href="/<?= h($ip_r['regcode']) ?>"><?= h($ip_r['name']) ?></a></span><span class="ip-apgroz"><?= tpl_num($ip_r['turnover'], '€') ?></span></li>
<?php endif; ?>
<?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
