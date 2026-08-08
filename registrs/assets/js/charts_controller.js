import { initializeSVG, drawD3Sankey } from './modules/sankey_chart.js';
import { updateBalanceTable } from './modules/balance_table.js';
import { updateSummaryTable } from './modules/summary_table.js';

// projects/python_generator/templates/assets/js/charts_controller.js

function clearHighlights() {
    document.querySelectorAll('tr.highlighted-source, tr.highlighted-main-register-entry').forEach(el => {
        el.classList.remove('highlighted-source', 'highlighted-main-register-entry');
    });
    document.querySelectorAll('div.table-container.highlighted-table').forEach(el => {
        el.classList.remove('highlighted-table');
    });
}

function highlightSourceData(year, reportType) {
    clearHighlights();
    // Pārbauda vai dati eksistē pirms mēģina tiem piekļūt
    if (!config || !config.allProcessedData || !config.allProcessedData[year] || !config.allProcessedData[year][reportType]) {
        return;
    }

    const sourceIds = config.allProcessedData[year][reportType].source_ids;
    if (!sourceIds) return;

    const highlightRowAndTable = (tableName, rowId) => {
        if (!rowId) return;
        const tableContainer = document.querySelector(`div.table-container[data-table-name="${tableName}"]`);
        if (tableContainer) {
            const row = tableContainer.querySelector(`tr[data-row-id="${rowId}"]`);
            if (row) {
                row.classList.add('highlighted-source');
                tableContainer.classList.add('highlighted-table');
            }
        }
    };

    highlightRowAndTable('financial_statements', sourceIds.financial_statement_id);
    highlightRowAndTable('income_statements', sourceIds.income_statement_id);
    highlightRowAndTable('balance_sheets', sourceIds.balance_sheet_id);
    highlightRowAndTable('cash_flow_statements', sourceIds.cash_flow_statement_id);
}

// Droša piekļuve konfigurācijai
const availableYears = (typeof config !== 'undefined' && config.sankeyAvailableYears) ? config.sankeyAvailableYears : [];
let currentYearIndex = availableYears.length > 0 ? availableYears.length - 1 : -1;

function updateChartsForYearAndType() {
    if (typeof config === 'undefined') return;

    const yearDisplay = document.getElementById('sankey_year_display');
    const prevYearButton = document.getElementById('sankey_prev_year');
    const nextYearButton = document.getElementById('sankey_next_year');
    const ugpRadio = document.querySelector('input[name="reportType"][value="UGP"]');
    const ukgpRadio = document.querySelector('input[name="reportType"][value="UKGP"]');
    const ugpLabel = document.getElementById('label_ugp');
    const ukgpLabel = document.getElementById('label_ukgp');

    if (!yearDisplay) return;

    // === DROŠA MODUĻU IZMANTOŠANA ===
    // Pārbauda, vai moduļi ir definēti pirms to izsaukšanas
    const safeDrawSankey = (data, curr) => {
        if (typeof drawD3Sankey !== 'undefined') drawD3Sankey(data, curr);
    };
    const safeUpdateSummary = (data, type) => {
        if (typeof updateSummaryTable !== 'undefined') updateSummaryTable(data, type);
    };

    if (currentYearIndex === -1) {
        yearDisplay.innerText = 'N/A';
        if(prevYearButton) prevYearButton.disabled = true;
        if(nextYearButton) nextYearButton.disabled = true;

        safeDrawSankey(null, 'EUR');
        safeUpdateSummary(config.summaryTableData, 'UGP');
        return;
    }

    const currentYear = availableYears[currentYearIndex];
    yearDisplay.innerText = currentYear;
    if(prevYearButton) prevYearButton.disabled = currentYearIndex === 0;
    if(nextYearButton) nextYearButton.disabled = currentYearIndex === availableYears.length - 1;

    const yearData = config.allProcessedData[currentYear];
    const hasUgpData = yearData && yearData.UGP;
    const hasUkgpData = yearData && yearData.UKGP;

    if(ugpRadio) {
        ugpRadio.disabled = !hasUgpData;
        if(ugpLabel) ugpLabel.classList.toggle('disabled', !hasUgpData);
    }
    if(ukgpRadio) {
        ukgpRadio.disabled = !hasUkgpData;
        if(ukgpLabel) ukgpLabel.classList.toggle('disabled', !hasUkgpData);
    }

    if (ukgpRadio && ukgpRadio.checked && !hasUkgpData && hasUgpData) {
        ugpRadio.checked = true;
    } else if (ugpRadio && ugpRadio.checked && !hasUgpData && hasUkgpData) {
        ukgpRadio.checked = true;
    }

    const activeRadio = document.querySelector('input[name="reportType"]:checked');
    const finalSelectedType = activeRadio ? activeRadio.value : 'UGP';
    const reportData = yearData ? yearData[finalSelectedType] : null;

    const currency = (reportData && reportData.fs_data && reportData.fs_data.currency) ? reportData.fs_data.currency : 'EUR';
    let roundingFactor = 1;
    if (reportData && reportData.fs_data && reportData.fs_data.rounded_to_nearest) {
        const roundedTo = String(reportData.fs_data.rounded_to_nearest).toUpperCase();
        if (roundedTo === 'THOUSANDS') roundingFactor = 1000;
        else if (roundedTo === 'MILLIONS') roundingFactor = 1000000;
    }

    // Zīmējam grafikus. Bilancei ir SAVS neatkarīgs gadu pārslēgs
    // (updateBalancePanel) — Sankey vadīklas to vairs neietekmē.
    safeDrawSankey(reportData ? reportData.sankey_prepared : null, currency);
    safeUpdateSummary(config.summaryTableData, finalSelectedType);

    highlightSourceData(currentYear, finalSelectedType);
}

// ==== Bilance: neatkarīgs gadu pārslēgs ====
// Noklusējums — jaunākais gads, individuālais (UGP) pārskats; ja gadam UGP
// bilances nav, rāda konsolidēto ar atzīmi "(kons.)". Pārslēgšana neietekmē
// Sankey un kopsavilkuma paneļus.
const balanceYears = (typeof config !== 'undefined' && config.allProcessedData)
    ? Object.keys(config.allProcessedData)
        .filter(y => {
            const d = config.allProcessedData[y];
            return (d && d.UGP && d.UGP.balance_data) || (d && d.UKGP && d.UKGP.balance_data);
        })
        .sort((a, b) => Number(a) - Number(b))
    : [];
let balanceYearIndex = balanceYears.length - 1;

function updateBalancePanel() {
    if (typeof updateBalanceTable === 'undefined') return;
    const controls = document.getElementById('balance_controls');
    const display = document.getElementById('balance_year_display');
    const prevBtn = document.getElementById('balance_prev_year');
    const nextBtn = document.getElementById('balance_next_year');

    if (balanceYearIndex < 0) {
        updateBalanceTable(null, 'EUR', 'N/A');
        return;
    }
    const year = balanceYears[balanceYearIndex];
    const yearData = config.allProcessedData[year];
    const useUgp = !!(yearData.UGP && yearData.UGP.balance_data);
    const reportData = useUgp ? yearData.UGP : yearData.UKGP;

    const currency = (reportData.fs_data && reportData.fs_data.currency) ? reportData.fs_data.currency : 'EUR';
    let factor = 1;
    if (reportData.fs_data && reportData.fs_data.rounded_to_nearest) {
        const r = String(reportData.fs_data.rounded_to_nearest).toUpperCase();
        if (r === 'THOUSANDS') factor = 1000;
        else if (r === 'MILLIONS') factor = 1000000;
    }
    updateBalanceTable(reportData.balance_data, currency, year, factor);

    if (controls) controls.style.display = balanceYears.length > 1 ? 'flex' : 'none';
    if (display) display.innerText = useUgp ? year : year + ' (kons.)';
    if (prevBtn) prevBtn.disabled = balanceYearIndex === 0;
    if (nextBtn) nextBtn.disabled = balanceYearIndex === balanceYears.length - 1;
}

export function initChartsController() {
    // Inicializācija
    if (typeof initializeSVG !== 'undefined') {
        initializeSVG();
    }

    updateChartsForYearAndType();

    const radioButtons = document.querySelectorAll('input[name="reportType"]');
    if(radioButtons) {
        radioButtons.forEach(el => el.addEventListener('change', updateChartsForYearAndType));
    }

    const prevYearButton = document.getElementById('sankey_prev_year');
    const nextYearButton = document.getElementById('sankey_next_year');

    if (prevYearButton) {
        prevYearButton.addEventListener('click', () => {
            if (currentYearIndex > 0) {
                currentYearIndex--;
                updateChartsForYearAndType();
            }
        });
    }
    if (nextYearButton) {
        nextYearButton.addEventListener('click', () => {
            if (currentYearIndex < availableYears.length - 1) {
                currentYearIndex++;
                updateChartsForYearAndType();
            }
        });
    }

    // Bilances neatkarīgais gadu pārslēgs
    updateBalancePanel();
    const balPrev = document.getElementById('balance_prev_year');
    const balNext = document.getElementById('balance_next_year');
    if (balPrev) {
        balPrev.addEventListener('click', () => {
            if (balanceYearIndex > 0) {
                balanceYearIndex--;
                updateBalancePanel();
            }
        });
    }
    if (balNext) {
        balNext.addEventListener('click', () => {
            if (balanceYearIndex < balanceYears.length - 1) {
                balanceYearIndex++;
                updateBalancePanel();
            }
        });
    }
}