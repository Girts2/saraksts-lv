<?php
/** @var array $page_data */
$nace = $page_data['nace_code'] ?? null;
?>
<style>
/* Klikšķis grafikā tukšā vietā vai ar pirkstu → kartīte zem tā (industry_charts.js); rangu navigators —
   rangu_navigators_panel.php lapas beigās. */
.ic-karte { margin: 6px 0 14px; padding: 8px 12px; border: 1px solid #dbe3ef; border-radius: 6px; background: #f8fafc; font-size: 14px; }
.ic-karte[hidden] { display: none; }
.ic-karte .ic-galva { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; color: #6b7280; font-size: 13px; margin-bottom: 4px; }
.ic-karte .ic-aizvert { border: 0; background: transparent; font-size: 18px; line-height: 1; color: #6b7280; cursor: pointer; padding: 0 2px; }
.ic-karte ul { list-style: none; margin: 0; padding: 0; }
.ic-karte li a, .ic-karte li > span { display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: baseline; padding: 4px 2px; text-decoration: none; }
.ic-karte li a:hover .ic-n { text-decoration: underline; }
.ic-karte li + li { border-top: 1px solid #edf0f5; }
.ic-karte .ic-v { color: #6b7280; min-width: 3.5em; text-align: right; }
.ic-karte .ic-n { flex: 1 1 12em; min-width: 0; overflow-wrap: anywhere; }
.ic-karte .ic-r, .ic-karte .ic-s { color: #6b7280; font-size: 13px; white-space: nowrap; }
.ic-karte li.ic-izv .ic-n { font-weight: 600; }
.ic-karte .ic-paris { color: #6b7280; font-size: 13px; }
/* Uznirstošā loga norāde un kursors (industry_charts.js logaIetvars): tikai tad, kad pēdējais rādītājs bija pele
   (.ic-pele) — tieši tad klikšķis atver profilu; pildspalvai un pirkstam klikšķis rāda kartīti. */
.ic-norade { display: none; margin-top: 4px; color: #6b7280; font-size: 12px; }
.ic-pele .ic-norade { display: block; }
.ic-pele:has(.google-chart-tooltip-content[data-reg]) { cursor: pointer; }
</style>
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
