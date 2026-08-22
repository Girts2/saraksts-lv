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
<?php
/* Vārti sadalīti divos: sankey (naudas plūsma), nozares pozīcija un apraksts
   prasa peļņas/zaudējumu aprēķinu; bilance un tās rādītāji — nē. Biedrībām un
   nodibinājumiem UR PZA nepublicē, tāpēc agrāk kopīgie vārti noslēdza arī tos
   paneļus, kuriem dati bija. $fin_pza paliek tieši tāds kā agrāk — uzņēmumiem
   ar PZA abi zari ir patiesi un izvade nemainās. */
$fin_pza = py_truthy($page_data['show_financial_charts'] ?? null) && py_truthy($page_data['has_financial_charts'] ?? null);
$fin_bil = py_truthy($page_data['show_financial_charts'] ?? null)
    && ($fin_pza || py_truthy($page_data['has_balance_data'] ?? null));
?>
<?php if ($fin_pza): ?>
<?php include $P . 'sankey_panel.php'; ?>
<?php endif; ?>
<?php if ($fin_bil): ?>
<?php include $P . 'balance_panel.php'; ?>
<?php endif; ?>
<?php /* PAPILDU REĢISTRI UN DATI (Girta 2026-08-19 lēmums): agrāk katra jaunā
         datu kopa bija atsevišķs pilna platuma panelis — de minimis, publiskie
         iepirkumi, ES fondi, PTAC licences, BIS, vide, ZVA, VID statusi. Astoņas
         tādas sadaļas izstiepa lapu, un vairumam uzņēmumu dati ir tikai vienā vai
         divās, tāpēc pārējās deva tukšus laukumus. Tagad visas ir VIENĀ blokā,
         sakļautas, ar ierakstu skaitu blakus nosaukumam. Sadaļu faili:
         view/partials/sadalas/*.php; konteiners pats iziet, ja neviena nav. */ ?>
<?php if (is_file($P . 'papildu_dati_panel.php')): ?><?php include $P . 'papildu_dati_panel.php'; ?><?php endif; ?>

<?php if ($fin_pza): ?>
<?php include $P . 'industry_position_panel.php'; ?>
<?php endif; ?>
<?php if ($fin_bil): ?>
<?php include $P . 'financial_ratios_panel.php'; ?>
<?php endif; ?>
<?php if ($fin_pza): ?>
<?php include $P . 'description_panel.php'; ?>
<?php endif; ?>
<?php /* Datu izpēte un MI panelis DOM beigās, lai UI teksts nenonāk pirms faktiem
         (rāpuļi/MI citē dokumenta sākumu). Vizuālā secība paliek CSS order: 6 —
         starp VID datiem (order 6, DOM pirms) un MI paneli DOM secība izšķir:
         vid → test → ai. */ ?>
<?php /* Konkurentu salīdzinājums (test_panel.php) ir PUBLISKS panelis (Girta
         2026-08-13 precizējums — tikai agregāti, personas datu nav). Pārējie
         "Test ..." paneļi TIKAI lokālajā testa vidē (Girta 2026-08-12 lēmums) —
         tie paši vārti, kas testa lapām (lib/test_env.php). Failu esamības
         pārbaude paliek, jo lejupielādes pakotnē daļa partial failu nav iekļauta. */ ?>
<?php if (is_file($P . 'test_panel.php')): ?><?php include $P . 'test_panel.php'; ?><?php endif; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/test_env.php'; ?>
<?php if (reg_test_env()): ?>
<?php /* Test Atbalsts: partial pats klusi iziet arī tad, ja nav ../test_data DB
         vai uzņēmums nav LAD saņēmējs. */ ?>
<?php if (is_file($P . 'test_atbalsts_panel.php')): ?><?php include $P . 'test_atbalsts_panel.php'; ?><?php endif; ?>
<?php /* Tiesiskais statuss vairs NAV šeit — kopš 2026-08-18 tas ir publisks
         panelis augstāk, tūlīt aiz company_facts_panel. */ ?>
<?php endif; ?>
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
