<?php
/** @var array $page_data */
$company_data = $page_data['dati_php_rowData'] ?? [];
if (!is_array($company_data)) $company_data = [];

$regcodeValue = dget($company_data, 'regcode', 'N/A');
// pyor, ne tikai dget noklusējums: kolonna DB pastāv, bet vērtība mēdz būt NULL
// (sepa ~110k, type_text ~1,4k rindām) — dget tad atdod null un jinja_e null
// renderē burtiski kā "None" (2026-08-05 audita atradums uz 1000 lapām).
$typeTextValue = pyor(dget($company_data, 'type_text'), '—');
$typeCodeValue = pyor(dget($company_data, 'type'), 'N/A');
$regTypeTextValue = pyor(dget($company_data, 'regtype_text'), '—');
$registeredDate = pyor(dget($company_data, 'registered'), '—');
$sepaValue = pyor(dget($company_data, 'sepa'), '—');
?>
<?php
// "Atbilde vispirms" kopsavilkums zem H1: galvenie fakti un jaunākie skaitļi vienā
// rindkopā lapas sākumā — MI/rāpuļi citātus visbiežāk ņem no dokumenta sākuma.
// Etiķešu stils (statuss: X, apgrozījums Y) apzināti izvairās no dzimtes locījumiem.
$lede_parts = [];
$lede_head = trim((string)($page_data['companyTitleForHtml'] ?? ''));
if ($lede_head !== '') {
    $lede_first = $lede_head . ' (reģ. Nr. ' . (string)($page_data['search_reg_nr'] ?? '') . ')';
    $lede_attrs = [];
    if (py_truthy($typeTextValue) && $typeTextValue !== '—') $lede_attrs[] = mb_strtolower((string)$typeTextValue, 'UTF-8');
    if (py_truthy($page_data['statusText'] ?? null)) $lede_attrs[] = 'statuss: ' . (string)$page_data['statusText'];
    if (py_truthy($registeredDate) && $registeredDate !== '—') $lede_attrs[] = 'reģ. datums: ' . (string)$registeredDate;
    if (!empty($lede_attrs)) $lede_first .= ' — ' . implode(', ', $lede_attrs);
    $lede_parts[] = $lede_first . '.';

    $lede_ugp = $page_data['summary_table_data_for_js']['UGP'] ?? [];
    $lede_last = !empty($lede_ugp) ? $lede_ugp[0] : null;
    if (is_array($lede_last) && ($lede_last['year'] ?? null) !== null) {
        $lede_cur = (string)($lede_last['currency'] ?? 'EUR');
        $lede_fin = [];
        if (($lede_last['turnover'] ?? null) !== null) $lede_fin[] = 'apgrozījums ' . tpl_num($lede_last['turnover'], $lede_cur);
        $lede_profit = $lede_last['profit'] ?? null;
        if ($lede_profit !== null) {
            $lede_fin[] = ($lede_profit < 0 ? 'zaudējumi ' : 'peļņa ') . tpl_num(abs((float)$lede_profit), $lede_cur);
        }
        if (($lede_last['employees'] ?? null) !== null) $lede_fin[] = 'darbinieku skaits: ' . tpl_num($lede_last['employees']);
        if (!empty($lede_fin)) {
            $lede_parts[] = 'Jaunākais gada pārskats (' . (string)$lede_last['year'] . '): ' . implode(', ', $lede_fin) . '.';
        }
    }

    $lede_cr = $page_data['ratios_history']['current_ratio'] ?? [];
    $lede_cr_last = !empty($lede_cr) ? end($lede_cr) : null;
    if (is_array($lede_cr_last) && isset($lede_cr_last['value']) && (is_int($lede_cr_last['value']) || is_float($lede_cr_last['value']))) {
        $lede_parts[] = 'Kopējais likviditātes koeficients: ' . number_format((float)$lede_cr_last['value'], 2, ',', ' ')
            . ' (' . (string)($lede_cr_last['year'] ?? '') . ').';
    }

    $lede_rating = $page_data['vid_panel_data']['rating'] ?? null;
    if (is_array($lede_rating) && py_truthy(dget($lede_rating, 'Reitings'))) {
        $lede_parts[] = 'VID nodokļu maksātāja reitings: ' . (string)dget($lede_rating, 'Reitings') . '.';
    }
}
?>
<div class="company-facts">
    <h1 class="company-main-title"><?= h($page_data['companyTitleForHtml'] ?? '') ?></h1>
<?php if (!empty($lede_parts)): ?>
    <?php /* Rindkopa ir domāta TIKAI lasītājiem bez JS (meklētāji, MI aģenti) — cilvēkam
             tie paši fakti jau redzami faktu tabulā un paneļos, tāpēc inline skripts to
             paslēpj parsēšanas laikā (bez uzmirgošanas). Bez JS teksts paliek redzams. */ ?>
    <p class="company-lede" id="company_lede" style="font-size: 14.5px; color: #374151; margin: -4px 0 12px 0; text-align: left; line-height: 1.5;">
        <?= h(implode(' ', $lede_parts)) ?>
    </p>
    <script>document.getElementById('company_lede').style.display='none';</script>
<?php endif; ?>
<?php
/* NACE kodu atvasina no VID gada nodokļu datiem, kas sedz tikai komersantus; kad
   koda nav, get_company_nace_info() atdod '0000' + "Nenoteikta nozare", un šeit
   iznāca rinda "Pamatdarbība: Nenoteikta nozare" — apgalvojums bez satura.
   Rādām tikai tad, kad nozare tiešām zināma (tas pats nosacījums, kas BUJ
   jautājumam page_builder.php un schema.org naceCode īpašībai). */
$cf_nace_code = trim((string)($page_data['nace_code'] ?? ''));
$cf_nace_zinama = $cf_nace_code !== '' && $cf_nace_code !== '0000';
?>
<?php if (py_truthy($page_data['nace_description'] ?? null) && $cf_nace_zinama): ?>
        <p style="font-size: 15px; color: #1f2937; font-style: italic; font-weight: normal; margin: -8px 0 15px 0; text-align: left;">
            Pamatdarbība:
<?php if (py_truthy($page_data['nace_link_code'] ?? null)): ?>
            <a href="/nozare/<?= h($page_data['nace_link_code']) ?>" style="color: #1f2937; text-decoration: underline; text-decoration-color: #9ca3af; text-underline-offset: 2px;"
               title="Nozares <?= h($page_data['nace_link_code']) ?> pārskats: top uzņēmumi, apgrozījums, algas"><?= h($page_data['nace_description']) ?></a>
<?php else: ?>
            <?= h($page_data['nace_description']) ?>
<?php endif; ?>
        </p>
<?php endif; ?>
    <table>
        <colgroup>
            <col style="width: 35%;">
            <col style="width: 65%;">
        </colgroup>
        <tbody>
            <tr><td class="label">Reģistrācijas Nr.</td><td class="value"><?= h($regcodeValue) ?></td></tr>
            <tr class="thick-separator status-row-highlight">
                <td class="label">Statuss</td>
                <td class="value">
                    <span class="status-base <?= h($page_data['statusClass'] ?? '') ?> status-value-text"><?= h($page_data['statusText'] ?? '') ?></span>
                </td>
            </tr>
            <tr><td class="label">Juridiskā Forma</td><td class="value"><?= h($typeTextValue) ?> <span class="details">(<?= h($typeCodeValue) ?>)</span></td></tr>
            <tr><td class="label">Reģistrs</td><td class="value"><?= h($regTypeTextValue) ?></td></tr>
            <tr><td class="label">Reģistrēts</td><td class="value"><?= h($registeredDate) ?></td></tr>

            <tr class="<?= (($page_data['terminatedDisplay'] ?? null) == '—' && ($page_data['statusText'] ?? null) == 'Aktīvs') ? 'inactive-field' : '' ?>">
                <td class="label">Darbība izbeigta</td>
                <td class="value">
                    <span class="<?= (($page_data['terminatedDisplay'] ?? null) != '—' && ($page_data['terminatedDisplay'] ?? null) != 'nan') ? 'date-terminated' : 'value-no-data-black' ?>">
                        <?= h((($page_data['terminatedDisplay'] ?? null) != 'nan') ? ($page_data['terminatedDisplay'] ?? '') : '—') ?>
                    </span>
                </td>
            </tr>

            <tr class="<?= (($page_data['closedDisplay'] ?? null) == '—' && ($page_data['statusText'] ?? null) == 'Aktīvs') ? 'inactive-field' : '' ?>">
                <td class="label">Slēgts</td>
                <td class="value">
                    <span class="<?= h($page_data['closedClassModifier'] ?? '') ?>">
                        <?= h($page_data['closedDisplay'] ?? '') ?>
                    </span>
                </td>
            </tr>

            <tr class="<?= ($sepaValue == '—') ? 'inactive-field' : '' ?>"><td class="label">SEPA ID</td><td class="value"><span class="<?= ($sepaValue == '—') ? 'value-no-data' : '' ?>"><?= h($sepaValue) ?></span></td></tr>

            <tr>
                <td class="label">Juridiskā adrese</td>
                <td class="value address-value-cell-inline"><?= h($page_data['formattedAddressForHtml'] ?? '') ?></td>
            </tr>
        </tbody>
    </table>
<?php if (py_truthy($page_data['data_updated'] ?? null)): ?>
    <p class="data-updated-note" style="font-size: 12.5px; color: #6b7280; margin: 10px 0 0 0; text-align: left;">
        UR datu kopija atjaunota: <?= h($page_data['data_updated']) ?>
    </p>
<?php endif; ?>
</div>
