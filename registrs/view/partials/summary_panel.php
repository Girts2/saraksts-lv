<?php /** @var array $page_data */
// tbody renderē serverī (rāpuļi/MI bez JS citādi redz tikai "Dati pārskatam nav
// pieejami"); JS updateSummaryTable() ielādē to pašu saturu pa virsu ar krāsu skalu.
$sum_all = $page_data['summary_table_data_for_js'] ?? [];
$sum_type = !empty($sum_all['UGP']) ? 'UGP' : (!empty($sum_all['UKGP']) ? 'UKGP' : null);
$sum_rows = $sum_type !== null ? $sum_all[$sum_type] : [];
?>
<div class="summary-panel-facts">
    <h2 id="summary_panel_heading">Pārskats<?= $sum_type !== null ? ($sum_type === 'UGP' ? ' (Individuālais)' : ' (Konsolidētais)') : '' ?></h2>
    <div class="table-responsive-wrapper" id="summary_table_wrapper">
        <table id="summaryDataTable" class="data-panel-table">
    <colgroup>
        <col style="width: 60px;">
        <col style="width: 142px;">
        <col style="width: 142px;">
        <col style="width: 142px;">
        <col style="width: 87px;">
    </colgroup>
    <thead>
        <tr>
            <th>Gads</th>
            <th>Apgrozījums</th>
            <th>Peļņa</th>
            <th>Aktīvi</th>
            <th>Darbinieki</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($sum_rows as $sum_r): $sum_cur = (string)($sum_r['currency'] ?? 'EUR'); ?>
        <tr>
            <td><?= h($sum_r['year'] ?? '') ?></td>
            <td class="text-right"><?= h(tpl_num($sum_r['turnover'] ?? null, $sum_cur)) ?></td>
            <td class="text-right"><?= h(tpl_num($sum_r['profit'] ?? null, $sum_cur)) ?></td>
            <td class="text-right"><?= h(tpl_num($sum_r['assets'] ?? null, $sum_cur)) ?></td>
            <td class="text-right"><?= h(tpl_num($sum_r['employees'] ?? null)) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>        <p class="no-data" id="summary_no_data_msg" style="display: none;">
            Dati pārskatam nav pieejami.
        </p>
    </div>
</div>
