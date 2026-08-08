// projects/python_generator/templates/assets/js/modules/summary_table.js

/**
 * Summary Table Module
 * Atbild par finanšu kopsavilkuma tabulas datu attēlošanu.
 */
// projects/python_generator/templates/assets/js/modules/summary_table.js

/**
 * Summary Table Module
 * Atbild par finanšu kopsavilkuma tabulas datu attēlošanu.
 */

const tableBodyId = 'summaryDataTable';
const noDataMsgId = 'summary_no_data_msg';
const headingId = 'summary_panel_heading';

export function updateSummaryTable(summaryData, reportType) {
    const table = document.getElementById(tableBodyId);
    const tableBody = table ? table.querySelector('tbody') : null;
    const noDataMsg = document.getElementById(noDataMsgId);
    const tableHeading = document.getElementById(headingId);

    if (!tableBody || !noDataMsg || !tableHeading) {
        console.error("Kopsavilkuma tabulas HTML elementi nav atrasti.");
        return;
    }

    const dataForType = summaryData ? summaryData[reportType] : [];
    tableHeading.innerText = `Pārskats (${reportType === 'UGP' ? 'Individuālais' : 'Konsolidētais'})`;

    // Paslēpj vai parāda tabulu atkarībā no datu pieejamības
    table.style.display = 'table';
    noDataMsg.style.display = 'none';

    if (!dataForType || dataForType.length === 0) {
        tableBody.innerHTML = '';
        noDataMsg.style.display = 'block';
        table.style.display = 'none';
        return;
    }

    let tableHtml = '';
    const formatNum = (val, currency) => {
        if (val === null || val === undefined) return '—';
        // Izmanto toLocaleString, lai automātiski formatētu ar atstarpēm kā tūkstošu atdalītāju
        return `${val.toLocaleString('lv-LV')} ${currency}`;
    };
    const formatPlainNum = (val) => {
        if (val === null || val === undefined) return '—';
        return val.toLocaleString('lv-LV');
    };

    // Excel stila krāsu skala pa kolonnām: minimums = sarkans (0°), maksimums = zaļš (120°),
    // starpvērtības — toņa pāreja caur dzelteno. Gaišums galos 82%, vidū 90%, lai teksts paliek lasāms.
    const shadeCols = ['turnover', 'profit', 'assets', 'employees'];
    const colRange = {};
    shadeCols.forEach(c => {
        const vals = dataForType.map(r => r[c]).filter(v => typeof v === 'number' && isFinite(v));
        colRange[c] = vals.length ? { min: Math.min(...vals), max: Math.max(...vals) } : { min: 0, max: 0 };
    });
    const cellShade = (val, col) => {
        const { min, max } = colRange[col];
        if (typeof val !== 'number' || !isFinite(val) || !(max > min)) return '';
        const t = (val - min) / (max - min);
        const hue = Math.round(t * 120);
        const light = Math.round(90 - 8 * Math.abs(t - 0.5) * 2);
        return ` style="background-color:hsl(${hue},75%,${light}%)"`;
    };

    dataForType.forEach(row => {
        const currency = row.currency || 'EUR';
        tableHtml += `
            <tr>
                <td>${row.year}</td>
                <td class="text-right"${cellShade(row.turnover, 'turnover')}>${formatNum(row.turnover, currency)}</td>
                <td class="text-right"${cellShade(row.profit, 'profit')}>${formatNum(row.profit, currency)}</td>
                <td class="text-right"${cellShade(row.assets, 'assets')}>${formatNum(row.assets, currency)}</td>
                <td class="text-right"${cellShade(row.employees, 'employees')}>${formatPlainNum(row.employees)}</td>
            </tr>
        `;
    });
    tableBody.innerHTML = tableHtml;
}