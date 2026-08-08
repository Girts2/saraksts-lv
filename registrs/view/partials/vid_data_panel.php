<?php
/** @var array $page_data */
$vid_data = $page_data['vid_panel_data'] ?? [];
if (!is_array($vid_data)) $vid_data = [];
$quarterly = $vid_data['quarterly_taxes'] ?? [];
$tax_table = $vid_data['tax_table'] ?? [];
?>
<div class="balance-facts vid-facts">

    <h2>VID dati</h2>

    <div class="vid-status-wrapper">
<?php if (py_truthy($vid_data['rating'] ?? null)): ?>
        <div class="data-point">
            <span>Nodokļu maksātāja reitings:</span>
<?php $vid_rating_letter = strtolower(preg_replace('/[^a-z]/i', '', (string)dget($vid_data['rating'], 'Reitings'))); ?>
            <span class="rating-value<?= $vid_rating_letter !== '' ? ' rating-' . h($vid_rating_letter) : '' ?>"><?= h(dget($vid_data['rating'], 'Reitings')) ?></span>
            <span><?= h(dget($vid_data['rating'], 'Skaidrojums')) ?></span>
        </div>
<?php endif; ?>
<?php if (py_truthy($vid_data['pvn'] ?? null)): ?>
        <div class="data-point">
            <span>PVN maksātājs:</span>
            <strong style="margin-left: 5px;"><?= h(dget($vid_data['pvn'], 'Aktivs')) ?></strong>
        </div>
<?php endif; ?>
    </div>

<?php if (py_truthy($tax_table) || py_truthy($quarterly)): ?>
    <h4>Uzņēmuma nodokļu maksājumi</h4>
    <div class="table-responsive-wrapper">
        <table class="data-panel-table">
            <thead>
                <tr>
                    <th>Periods</th>
                    <th>Nodokļi kopā <span class="th-note">(tūkst. EUR)</span></th>
                    <th>IIN <span class="th-note">(tūkst. EUR)</span></th>
                    <th>VSAOI <span class="th-note">(tūkst. EUR)</span></th>
                    <th>Darbinieki</th>
                </tr>
            </thead>
            <tbody>
<?php if (py_truthy($quarterly)): ?>
<?php foreach ($quarterly as $q_tax): ?>
                <tr class="quarterly-data" style="background-color: #f8f9fa; font-weight: 500; border-bottom: 2px solid #ccc;">
                    <td><?= h($q_tax['Taksacijas_gads_ceturksnis'] ?? null) ?></td>
                    <td><?= h($q_tax['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? null) ?></td>
                    <td><?= h($q_tax['Taja_skaita_IIN'] ?? null) ?></td>
                    <td><?= h($q_tax['Taja_skaita_VSAOI'] ?? null) ?></td>
                    <td><?= h($q_tax['Videjais_nodarbinato_personu_skaits_cilv'] ?? null) ?></td>
                </tr>
<?php endforeach; ?>
<?php endif; ?>
<?php foreach ($tax_table as $row): ?>
                <tr>
                    <td><?= h($row['Taksacijas_gads'] ?? null) ?></td>
                    <td><?= h($row['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? null) ?></td>
                    <td><?= h($row['Taja_skaita_IIN'] ?? null) ?></td>
                    <td><?= h($row['Taja_skaita_VSAOI'] ?? null) ?></td>
                    <td><?= h($row['Videjais_nodarbinato_personu_skaits_cilv'] ?? null) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h4>Uzņēmuma vidējās algas aprēķins</h4>
    <div class="table-responsive-wrapper">
        <table class="data-panel-table">
            <thead>
                <tr>
                    <th>Periods</th>
                    <th>Vidējā bruto alga (EUR) <span class="th-note">(alga uz papīra)</span></th>
                    <th>Aptuvenā neto alga (EUR) <span class="th-note">(alga uz rokas)</span></th>
                </tr>
            </thead>
            <tbody>
<?php if (py_truthy($quarterly)): ?>
<?php foreach ($quarterly as $q_tax): ?>
                <tr class="quarterly-data" style="background-color: #f8f9fa; font-weight: 500; border-bottom: 2px solid #ccc;">
                    <td><?= h($q_tax['Taksacijas_gads_ceturksnis'] ?? null) ?></td>
                    <td><strong><?= h($q_tax['avg_gross_salary'] ?? null) ?></strong></td>
                    <td><strong><?= h($q_tax['avg_net_salary'] ?? null) ?></strong></td>
                </tr>
<?php endforeach; ?>
<?php endif; ?>
<?php foreach ($tax_table as $row): ?>
                <tr>
                    <td><?= h($row['Taksacijas_gads'] ?? null) ?></td>
                    <td><strong><?= h($row['avg_gross_salary'] ?? null) ?></strong></td>
                    <td><strong><?= h($row['avg_net_salary'] ?? null) ?></strong></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php
    // Privātuma piezīme: rāda tad, ja tabulā tiešām ir kāda '***' rinda (alga slēpta
    // <3 darbinieku dēļ) — pārbauda pašu attēloto vērtību, ne tikai darbinieku skaitu.
    $vid_salary_hidden = false;
    foreach (array_merge($quarterly, $tax_table) as $vid_r) {
        if (($vid_r['avg_gross_salary'] ?? '') === '***') { $vid_salary_hidden = true; break; }
    }
?>
<?php if ($vid_salary_hidden): ?>
    <p class="disclaimer" style="font-size: 12.5px; color: #6b7280;"><em>* *** — periodos, kad uzņēmumā ir mazāk nekā 3 darbinieki, algas aprēķins tiek slēpts privātuma aizsardzībai: dati VID publikācijā ir, bet vidējā alga atklātu konkrētu personu atalgojumu.</em></p>
<?php endif; ?>

<?php if (py_truthy($vid_data['salary_calculation_example'] ?? null)): $ex = $vid_data['salary_calculation_example']; ?>
    <div class="formula-explanation">
        <p><strong>Bruto algas aprēķina formula <?= h($ex['year'] ?? null) ?>. gadam:</strong></p>
        <p class="formula-step"><code>( (VSAOI summa * 1000) / VSAOI likme ) / Darbinieku skaits / 12 mēneši</code></p>
        <p class="formula-step"><code>( (<?= h($ex['vsaoi_sum'] ?? null) ?> * 1000) / 0.3409 ) / <?= h($ex['employees'] ?? null) ?> / 12 = <strong><?= h($ex['gross_salary_formatted'] ?? null) ?> EUR</strong></code></p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
        <p><strong>Aptuvenās neto algas ("uz rokas") aprēķins <?= h($ex['year'] ?? null) ?>. gadam:</strong></p>
        <p>1. VSAOI darba ņēmēja daļa (10.5%): <code><?= h($ex['gross_salary_formatted'] ?? null) ?> * 0.105 = <?= h($ex['vsaoi_employee_part'] ?? null) ?> EUR</code></p>
        <p>2. Piemērojamais neapliekamais minimums: <code><?= h($ex['non_taxable_minimum'] ?? null) ?> EUR</code></p>
        <p>3. Iedzīvotāju ienākuma nodoklis (IIN <?= h($ex['iin_rate_percentage'] ?? null) ?>%): <code>(<?= h($ex['gross_salary_formatted'] ?? null) ?> - <?= h($ex['vsaoi_employee_part'] ?? null) ?> - <?= h($ex['non_taxable_minimum'] ?? null) ?>) * <?= h($ex['iin_rate_decimal'] ?? null) ?> = <?= h($ex['iin_part'] ?? null) ?> EUR</code></p>
        <p>4. Neto alga: <code><?= h($ex['gross_salary_formatted'] ?? null) ?> - <?= h($ex['vsaoi_employee_part'] ?? null) ?> - <?= h($ex['iin_part'] ?? null) ?> = <strong><?= h($ex['net_salary_formatted'] ?? null) ?> EUR</strong></code></p>
        <p class="disclaimer"><em>* Šis ir aptuvens aprēķins. Tas pieņem, ka darbiniekam nav apgādājamo vai citu atvieglojumu. Reālā vidējā neto alga var atšķirties.</em></p>
    </div>
<?php endif; ?>
<?php endif; ?>
</div>
