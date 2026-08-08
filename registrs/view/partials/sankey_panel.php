<?php /** @var array $page_data */
// Servera renderēts peļņas vai zaudējumu aprēķina (PZA) kopsavilkums pa gadiem —
// skaitļi lasāmi bez JS (Sankey diagramma tos pašus rāda vizuāli). Rindas, kas
// konkrētā pārskata shēmā (by_nature / by_function) nav aizpildītas, rāda "—".
$pza_all = $page_data['allProcessedData'] ?? [];
$pza_defs = [
    'net_turnover'               => 'Neto apgrozījums',
    'by_function_gross_profit'   => 'Bruto peļņa',
    'by_nature_labour_expenses'  => 'Personāla izmaksas',
    'income_before_income_taxes' => 'Peļņa pirms nodokļiem',
    'provision_for_income_taxes' => 'UIN nodoklis',
    'net_income'                 => 'Tīrā peļņa',
];
$pza_years = [];
$pza_vals = []; // year => ['cur' =>, lauks => v]
foreach ($pza_all as $pza_y => $pza_types) {
    $pza_d = $pza_types['UGP'] ?? null;
    if (!is_array($pza_d) || !is_array($pza_d['income_data'] ?? null)) continue;
    $pza_fs = is_array($pza_d['fs_data'] ?? null) ? $pza_d['fs_data'] : [];
    $pza_round = strtoupper((string)($pza_fs['rounded_to_nearest'] ?? 'ONES'));
    $pza_factor = $pza_round === 'THOUSANDS' ? 1000 : ($pza_round === 'MILLIONS' ? 1000000 : 1);
    $pza_row = ['cur' => (string)($pza_fs['currency'] ?? 'EUR')];
    $pza_has = false;
    foreach ($pza_defs as $pza_k => $_) {
        $pza_v = prepare_for_chart(get_raw_value($pza_d['income_data'], $pza_k));
        $pza_row[$pza_k] = $pza_v !== null ? $pza_v * $pza_factor : null;
        if ($pza_v !== null) $pza_has = true;
    }
    if ($pza_has) { $pza_vals[(string)$pza_y] = $pza_row; $pza_years[] = (string)$pza_y; }
}
rsort($pza_years, SORT_NUMERIC);
$pza_years = array_slice($pza_years, 0, 5);
?>
<div class="sankey-facts">
    <h2 id="sankey_heading">Ieņēmumu un Izdevumu Plūsma</h2>
<?php if (!empty($pza_years)): ?>
    <?php /* Kopsavilkums pa gadiem ir domāts TIKAI lasītājiem bez JS (meklētāji, MI aģenti).
             Kad JS uzzīmē Sankey diagrammu, šo bloku paslēpj (sankey_chart.js), lai panelis
             vizuāli paliek kā agrāk — diagramma ar gadu pārslēgu. */ ?>
    <div class="table-responsive-wrapper" id="pza_summary_ssr">
        <table class="data-panel-table pza-summary-table">
            <thead>
                <tr>
                    <th>Peļņas vai zaudējumu aprēķins</th>
<?php foreach ($pza_years as $pza_y): ?>
                    <th class="text-right"><?= h($pza_y) ?></th>
<?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($pza_defs as $pza_k => $pza_label): ?>
<?php
                // Rindu izlaiž, ja tā tukša VISOS gados (piem., Bruto peļņa by_nature shēmā).
                $pza_any = false;
                foreach ($pza_years as $pza_y) { if (($pza_vals[$pza_y][$pza_k] ?? null) !== null) { $pza_any = true; break; } }
                if (!$pza_any) continue;
?>
                <tr>
                    <td><?= h($pza_label) ?></td>
<?php foreach ($pza_years as $pza_y): ?>
                    <td class="text-right"><?= h(tpl_num($pza_vals[$pza_y][$pza_k] ?? null, $pza_vals[$pza_y]['cur'] ?? 'EUR')) ?></td>
<?php endforeach; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (py_truthy($page_data['sankeyAvailableYears'] ?? null)): ?>
        <div class="sankey-type-toggle">
            <label id="label_ugp"><input type="radio" name="reportType" value="UGP" checked> Individuālais</label>
            <label id="label_ukgp" style="margin-left: 15px;"><input type="radio" name="reportType" value="UKGP"> Konsolidētais</label>
        </div>
        <div class="sankey-controls">
            <button id="sankey_prev_year" title="Iepriekšējais gads">&lt;</button>
            <span id="sankey_year_display">Ielādē...</span>
            <button id="sankey_next_year" title="Nākamais gads">&gt;</button>
        </div>
        <div id="d3_sankey_chart_area">
        </div>
        <div id="d3SankeyErrorDisplay" class="error-message" style="text-align:left; padding-left:10px; font-size:0.9em;"></div>
<?php else: ?>
        <p class="no-data">Dati plūsmas diagrammai nav pieejami.</p>
<?php endif; ?>
</div>
