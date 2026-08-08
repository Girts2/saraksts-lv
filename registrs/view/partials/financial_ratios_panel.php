<?php /** @var array $page_data */
// Servera renderēta rādītāju vērtību tabula: precīzie skaitļi lasāmi bez JS
// (grafiki zemāk tos pašus rāda vizuāli). Dati no prepare_ratios_history_for_charts
// (page_builder $final_d['ratios_history']): margin/roa/roe/roce jau procentos.
$fr_hist = $page_data['ratios_history'] ?? [];
// [nosaukums, vai procenti, formula] — formulas sakrīt ar financial_engine aprēķiniem.
$fr_defs = [
    'current_ratio'     => ['Kopējais likviditātes koeficients (Current Ratio)', false, 'Apgrozāmie līdzekļi / Īstermiņa saistības'],
    'quick_ratio'       => ['Ātrās likviditātes koeficients (Quick Ratio)', false, '(Apgrozāmie līdzekļi − Krājumi) / Īstermiņa saistības'],
    'net_profit_margin' => ['Tīrās peļņas rentabilitāte', true, 'Tīrā peļņa / Neto apgrozījums'],
    'roa'               => ['Aktīvu rentabilitāte (ROA)', true, 'Tīrā peļņa / Aktīvi kopā'],
    'roe'               => ['Pašu kapitāla rentabilitāte (ROE)', true, 'Tīrā peļņa / Pašu kapitāls'],
    'debt_to_equity'    => ['Saistību / pašu kapitāla attiecība (D/E)', false, 'Saistības kopā / Pašu kapitāls'],
    'interest_coverage' => ['Procentu seguma koeficients (IC)', false, 'EBIT / Procentu izmaksas'],
    'asset_turnover'    => ['Aktīvu aprites koeficients', false, 'Neto apgrozījums / Aktīvi kopā'],
    'roce'              => ['Kapitāla efektivitāte (ROCE)', true, 'EBIT / Ieguldītais kapitāls'],
    'altman_z_score'    => ["Altmana Z'-indekss (maksātnespējas risks)", false, "Z' (1983) = 0,717·A + 0,847·B + 3,107·C + 0,420·D + 0,998·E"],
];
$fr_years = [];
$fr_vals = []; // key => year => value
foreach ($fr_defs as $fr_k => $_) {
    foreach (($fr_hist[$fr_k] ?? []) as $fr_p) {
        $fr_y = (string)($fr_p['year'] ?? '');
        if ($fr_y === '' || !isset($fr_p['value'])) continue;
        $fr_years[$fr_y] = true;
        $fr_vals[$fr_k][$fr_y] = $fr_p['value'];
    }
}
$fr_years = array_keys($fr_years);
rsort($fr_years, SORT_NUMERIC);
$fr_fmt = function ($v, bool $pct): string {
    if (!is_int($v) && !is_float($v)) return '—';
    $s = number_format((float)$v, 2, ',', ' ');
    return $pct ? $s . ' %' : $s;
};
?>
<div class="balance-facts financial-ratios-panel">
    <h2>Finanšu Rādītāji un Riska Analīze</h2>

<?php if (!empty($fr_years)): ?>
    <?php /* Vērtību tabula ir domāta TIKAI lasītājiem bez JS (meklētāji, MI aģenti).
             Kad JS uzzīmē rādītāju grafikus, šo bloku paslēpj (financial_ratios_module.js),
             lai panelis vizuāli paliek kā agrāk — tikai grafiki ar aprakstiem. */ ?>
    <div class="table-responsive-wrapper" id="ratios_summary_ssr">
        <table class="data-panel-table ratios-values-table">
            <thead>
                <tr>
                    <th>Rādītājs</th>
<?php foreach ($fr_years as $fr_y): ?>
                    <th class="text-right"><?= h($fr_y) ?></th>
<?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($fr_defs as $fr_k => [$fr_label, $fr_pct, $fr_formula]): if (empty($fr_vals[$fr_k])) continue; ?>
                <tr>
                    <td><?= h($fr_label) ?><br><span style="font-size: 11.5px; color: #94a3b8; font-weight: normal;"><?= h($fr_formula) ?></span></td>
<?php foreach ($fr_years as $fr_y): ?>
                    <td class="text-right"><?= h($fr_fmt($fr_vals[$fr_k][$fr_y] ?? null, $fr_pct)) ?></td>
<?php endforeach; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

    <div class="charts-grid-container" id="ratios_container">
        <div class="ratio-chart-wrapper">
            <div id="chart_div_current_ratio" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_current_ratio"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_quick_ratio" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_quick_ratio"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_net_profit_margin" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_net_profit_margin"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_roa" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_roa"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_roe" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_roe"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_debt_to_equity" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_debt_to_equity"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_interest_coverage" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_interest_coverage"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_asset_turnover" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_asset_turnover"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_roce" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_roce"></div>
        </div>
        <div class="ratio-chart-wrapper">
            <div id="chart_div_altman_z_score" class="ratio-chart-container"></div>
            <div class="ratio-chart-description" id="desc_div_altman_z_score"></div>
            <p style="font-size: 0.8em; color: #666; margin-top: 5px; padding-left: 10px;">* Aprēķins veikts pēc Altmana 1983. gada modeļa privātiem uzņēmumiem.</p>
        </div>
    </div>

    <p class="no-data" id="ratios_no_data_msg" style="display: none;">Dati rādītāju aprēķinam nav pieejami vismaz divus gadus, lai attēlotu dinamiku.</p>
</div>
