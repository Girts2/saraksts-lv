<?php
/** @var array $page_data */
$nace = $page_data['nace_code'] ?? null;
?>
<div class="balance-facts industry-position-facts">
    <div class="panel-header">
        <h2>Pozīcija nozarē</h2>
<?php if (py_truthy($nace) && mb_strlen((string)$nace) == 4): ?>
            <a href="https://saraksts.lv/nozare.php#<?= h(mb_substr((string)$nace, 0, 2)) ?>.<?= h(mb_substr((string)$nace, 2)) ?>" class="btn btn-details-red" target="_blank" rel="noopener noreferrer">Detalizēti</a>
<?php endif; ?>
    </div>

    <div class="industry-tabs">
        <button class="industry-tab-btn active" data-target="view-ranks">
            Peļņa, Apgrozījums, Darbinieki
        </button>
        <button class="industry-tab-btn" data-target="view-scatter">
            Peļņa / Apgrozījums
        </button>
    </div>

    <div id="view-ranks" class="industry-tab-content active">
        <div id="industry_chart_profit" class="industry-chart-container">
            <p class="no-data">Ielādē peļņas datus...</p>
        </div>
        <div id="industry_chart_turnover" class="industry-chart-container">
            <p class="no-data">Ielādē apgrozījuma datus...</p>
        </div>
        <div id="industry_chart_employees" class="industry-chart-container">
            <p class="no-data">Ielādē darbinieku skaita datus...</p>
        </div>
    </div>

    <div id="view-scatter" class="industry-tab-content">
        <div id="industry_chart_scatter" class="industry-chart-container" style="height: 500px;">
            <p class="no-data">Ielādē salīdzinošos datus...</p>
        </div>
    </div>
</div>
