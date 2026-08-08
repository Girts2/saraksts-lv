<?php /** @var array $page_data */
// Servera renderēts bilances kopsavilkums pa gadiem: galvenās pozīcijas lasāmas
// bez JS (detalizētās Aktīvu/Pasīvu tabulas jaunākajam gadam renderē JS pa virsu).
// Vērtībām piemēro pārskata noapaļošanas reizinātāju (THOUSANDS/MILLIONS) kā JS pusē.
$bal_all = $page_data['allProcessedData'] ?? [];
$bal_defs = [
    'total_assets'            => 'Aktīvi kopā',
    'total_current_assets'    => 'Apgrozāmie līdzekļi',
    'equity'                  => 'Pašu kapitāls',
    'current_liabilities'     => 'Īstermiņa saistības',
    'non_current_liabilities' => 'Ilgtermiņa saistības',
];
$bal_years = [];
$bal_vals = []; // year => ['cur' =>, laukums => v]
foreach ($bal_all as $bal_y => $bal_types) {
    $bal_d = $bal_types['UGP'] ?? null;
    if (!is_array($bal_d) || !is_array($bal_d['balance_data'] ?? null)) continue;
    $bal_fs = is_array($bal_d['fs_data'] ?? null) ? $bal_d['fs_data'] : [];
    $bal_round = strtoupper((string)($bal_fs['rounded_to_nearest'] ?? 'ONES'));
    $bal_factor = $bal_round === 'THOUSANDS' ? 1000 : ($bal_round === 'MILLIONS' ? 1000000 : 1);
    $bal_row = ['cur' => (string)($bal_fs['currency'] ?? 'EUR')];
    $bal_has = false;
    foreach ($bal_defs as $bal_k => $_) {
        $bal_v = prepare_for_chart(get_raw_value($bal_d['balance_data'], $bal_k));
        $bal_row[$bal_k] = $bal_v !== null ? $bal_v * $bal_factor : null;
        if ($bal_v !== null) $bal_has = true;
    }
    if ($bal_has) { $bal_vals[(string)$bal_y] = $bal_row; $bal_years[] = (string)$bal_y; }
}
rsort($bal_years, SORT_NUMERIC);
$bal_years = array_slice($bal_years, 0, 5);
?>
<div class="balance-facts">
    <div class="balance-head">
        <h2 id="balance_heading">Bilance</h2>
        <?php /* Neatkarīgs gadu pārslēgs (JS rāda, ja ir dati vairākiem gadiem).
                 Noklusējums — jaunākais gads, individuālais (UGP) pārskats;
                 pārslēgšana neietekmē pārējos paneļus. */ ?>
        <div class="balance-controls" id="balance_controls" style="display:none;">
            <button id="balance_prev_year" title="Iepriekšējais gads">&lt;</button>
            <span id="balance_year_display"></span>
            <button id="balance_next_year" title="Nākamais gads">&gt;</button>
        </div>
    </div>
<?php if (!empty($bal_years)): ?>
    <?php /* Kopsavilkums pa gadiem ir domāts TIKAI lasītājiem bez JS (meklētāji, MI aģenti).
             Kad JS uzzīmē detalizētās viena gada tabulas ar gadu pārslēgu, šo bloku paslēpj
             (balance_table.js), lai panelis vizuāli paliek kā agrāk — viens gads vienlaikus. */ ?>
    <div class="table-responsive-wrapper" id="balance_summary_ssr">
        <table class="data-panel-table balance-summary-table">
            <thead>
                <tr>
                    <th>Pozīcija</th>
<?php foreach ($bal_years as $bal_y): ?>
                    <th class="text-right"><?= h($bal_y) ?></th>
<?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($bal_defs as $bal_k => $bal_label): ?>
                <tr>
                    <td><?= h($bal_label) ?></td>
<?php foreach ($bal_years as $bal_y): ?>
                    <td class="text-right"><?= h(tpl_num($bal_vals[$bal_y][$bal_k] ?? null, $bal_vals[$bal_y]['cur'] ?? 'EUR')) ?></td>
<?php endforeach; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
    <div class="chart-container" id="balance_chart_container">
        <div id="balance_tables_container" class="balance-tables-grid" style="display: none; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));"></div>
        <p class="no-data" id="balance_no_data_msg" style="display: block;">
            Dati nav pieejami.
        </p>
    </div>
</div>
