<?php
/**
 * inner_content.php — ports of kods/templates/inner_content.html
 * Sagaida $page_data scope'ā (kā PHP masīvs no build_page_data).
 * ai_panel: testa režīmā (REG_AIPANEL_PLACEHOLDER=1) izvada vietturi; citādi iekļauj ai_panel.php.
 */
/** @var array $page_data */
require_once __DIR__ . '/_tpl.php';

$P = __DIR__ . '/partials/';
$aipanel_placeholder = getenv('REG_AIPANEL_PLACEHOLDER') === '1';
?>
<?php if (py_truthy($page_data['search_reg_nr'] ?? null) && py_truthy($page_data['dati_php_rowData'] ?? null)): ?>
        <div class="fixed-width-container">
<?php if (py_truthy($page_data['is_liquidated'] ?? null)): ?>
                <div class="notice-liquidated">
                    <strong>Uzmanību!</strong> Uzņēmuma darbība ir izbeigta (Statuss: <?= h($page_data['statusText'] ?? '') ?>). Dati ir tikai informatīviem nolūkiem.
                </div>
<?php endif; ?>

            <div class="single-column-layout">
<?php include $P . 'company_facts_panel.php'; ?>
<?php if (py_truthy($page_data['show_summary_panel'] ?? null) && py_truthy($page_data['has_summary_data'] ?? null)): ?>
<?php include $P . 'summary_panel.php'; ?>
<?php endif; ?>
<?php if (py_truthy($page_data['vid_panel_data'] ?? null) && py_truthy(($page_data['vid_panel_data']['has_data'] ?? false))): ?>
<?php include $P . 'vid_data_panel.php'; ?>
<?php endif; ?>
<?php if (py_truthy($page_data['show_financial_charts'] ?? null) && py_truthy($page_data['has_financial_charts'] ?? null)): ?>
<?php include $P . 'sankey_panel.php'; ?>
<?php include $P . 'balance_panel.php'; ?>
<?php include $P . 'industry_position_panel.php'; ?>
<?php include $P . 'financial_ratios_panel.php'; ?>
<?php include $P . 'description_panel.php'; ?>
<?php endif; ?>
<?php /* Datu izpēte un MI panelis DOM beigās, lai UI teksts nenonāk pirms faktiem
         (rāpuļi/MI citē dokumenta sākumu). Vizuālā secība paliek CSS order: 6 —
         starp VID datiem (order 6, DOM pirms) un MI paneli DOM secība izšķir:
         vid → test → ai. */ ?>
<?php if (is_file($P . 'test_panel.php')): ?><?php include $P . 'test_panel.php'; ?><?php endif; ?>
<?php if (py_truthy($page_data['show_financial_charts'] ?? null) && py_truthy($page_data['has_financial_charts'] ?? null)): ?>
<?php if ($aipanel_placeholder): ?><!--AIPANEL_PLACEHOLDER--><?php elseif (is_file($P . 'ai_panel.php')): ?><?php include $P . 'ai_panel.php'; ?><?php endif; ?>
<?php endif; ?>
            </div>

<?php include $P . 'results_tables_section.php'; ?>
<?php include $P . 'faq_section.php'; ?>
        </div>

<?php elseif (py_truthy($page_data['search_reg_nr'] ?? null) && !py_truthy($page_data['errors'] ?? null)): ?>
        <div class="container">
            <p class='no-results'>Reģistrācijas numurs '<?= h($page_data['search_reg_nr'] ?? '') ?>' netika atrasts.</p>
        </div>
<?php endif; ?>

    <div id="custom-tooltip" role="tooltip"></div>
