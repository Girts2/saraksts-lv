// projects/python_generator/templates/assets/js/modules/balance_table.js

export function updateBalanceTable(balanceData, currency, year, displayRoundingFactor = 1) {
    const container = document.getElementById('balance_tables_container');
    const noDataMsg = document.getElementById('balance_no_data_msg');

    if (!container || !noDataMsg) {
        console.error("Bilances tabulas HTML elementi nav atrasti.");
        return;
    }

    if (!balanceData) {
        noDataMsg.style.display = 'block';
        container.style.display = 'none';
        return;
    }

    noDataMsg.style.display = 'none';
    container.style.display = 'grid';

    // Servera renderētais daudzgadu kopsavilkums ir domāts lasītājiem bez JS;
    // tiklīdz interaktīvās tabulas ir gatavas, to paslēpjam, lai gadu rāda pārslēgs.
    const ssrSummary = document.getElementById('balance_summary_ssr');
    if (ssrSummary) ssrSummary.style.display = 'none';

    // Formatēšanas palīgfunkcija
    const formatValue = (val) => {
        const num = (parseFloat(val) || 0) * displayRoundingFactor;
        // Lietojam lv-LV lokāli, lai būtu "1 234,00"
        return new Intl.NumberFormat('lv-LV', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        }).format(num).replace(',', '.') + ' ' + currency; // Lai pilnībā atbilstu norādītajam "1 234.00 EUR" formātam, vai varam atstāt native lv-LV (1 234,00 eur). Izmantosim lv-LV pamatstandartu.
    };
    
    const formatValueLV = (val) => {
        const num = (parseFloat(val) || 0) * displayRoundingFactor;
        // Native lv-LV: "1 234" (bez centiem)
        let formatted = new Intl.NumberFormat('lv-LV', { 
            style: 'decimal',
            minimumFractionDigits: 0, 
            maximumFractionDigits: 0 
        }).format(num);
        return formatted + ' EUR';
    };

    // Aktīvu aprēķini
    const ilgtermina_ieguldijumi = formatValueLV(balanceData.total_non_current_assets);
    const nematerialie_ieguldijumi = formatValueLV(balanceData.intangible_assets);
    const pamatlidzekli = formatValueLV(balanceData.fixed_assets);
    const finansu_ieguldijumi = formatValueLV(balanceData.investments);
    const apgrozamie_lidzekli = formatValueLV(balanceData.total_current_assets);
    const krajumi = formatValueLV(balanceData.inventories);
    const debitori = formatValueLV(balanceData.accounts_receivable);
    const vertspapiri = formatValueLV(balanceData.marketable_securities);
    const nauda = formatValueLV(balanceData.cash);
    const aktivi_kopa = formatValueLV(balanceData.total_assets);

    // Pasīvu aprēķini
    const pasu_kapitals = formatValueLV(balanceData.equity);
    const uzkrajumi = formatValueLV(balanceData.provisions);
    const ilgtermina_saistibas = formatValueLV(balanceData.non_current_liabilities);
    const istermina_saistibas = formatValueLV(balanceData.current_liabilities);
    const nakamo_periodu_ienemumi = formatValueLV(balanceData.future_housing_repairs_payments);
    
    const currentLiab = parseFloat(balanceData.current_liabilities) || 0;
    const nonCurrentLiab = parseFloat(balanceData.non_current_liabilities) || 0;
    const futurePayments = parseFloat(balanceData.future_housing_repairs_payments) || 0;
    const saistibas_kreditori = formatValueLV(currentLiab + nonCurrentLiab + futurePayments);
    
    // total_equities eksistē CSV struktūrā, kas norāda "Pasīvi kopā". Ja tā nav, tad izmantojam total_assets jo balancē Aktīvi=Pasīvi.
    const pasivi_kopa = balanceData.total_equities ? formatValueLV(balanceData.total_equities) : aktivi_kopa;

    // Uztaisa smuku CSS tieši šim panelim
    const customStyles = `
        <style>
            .acc-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-family: var(--font-table, 'Inter', -apple-system, sans-serif); font-size: 0.9em; margin-bottom: 0px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; background: #fff;}
            .acc-table thead { background-color: var(--color-table-header-bg, #f8f9fa); color: var(--color-text-secondary, #6c757d); }
            .acc-table th { padding: 8px 10px; font-weight: 600; text-transform: uppercase; font-size: 0.8em; letter-spacing: 0.05em; border: 1px solid #e5e7eb; border-bottom: 2px solid var(--color-border, #dee2e6); text-align: left; }
            .acc-table th.right { text-align: right; }
            .acc-table td { padding: 8px 10px; border: 1px solid #e5e7eb; color: var(--color-text, #1e293b); }
            .acc-table .sub-row td { padding-left: 24px; font-style: italic; color: #64748b; font-size: 0.8rem;}
            .acc-table .main-row td { font-weight: 600; color: var(--color-heading, #0f172a); }
            .acc-table .main-row.border-top td { border-top: 2px solid #e2e8f0; }
            .acc-table .total-row td { font-weight: 700; color: #000; border-top: 2px solid #94a3b8; border-bottom: 3px double #475569; background-color: #f8fafc; font-size: 0.9rem; }
            .acc-table .text-right { text-align: right; white-space: nowrap; }
            
            /* Tabulas blakus viena otrai ar mazu atstarpi */
            .acc-wrapper { display: grid; gap: 16px; grid-template-columns: 1fr 1fr; align-items: start; }
            
            /* Uz mobilajiem lai iet viena zem otras */
            @media (max-width: 900px) {
                .acc-wrapper { grid-template-columns: 1fr; }
            }
        </style>
    `;

    // Ģenerē ASSETS HTML
    let assetsHtml = `
        <table class="acc-table">
            <thead>
                <tr><th>Aktīvi (${year})</th><th class="right">Summa</th></tr>
            </thead>
            <tbody>
                <tr class="main-row"><td>Ilgtermiņa ieguldījumi</td><td class="text-right">${ilgtermina_ieguldijumi}</td></tr>
                <tr class="sub-row"><td>Nemateriālie ieguldījumi</td><td class="text-right">${nematerialie_ieguldijumi}</td></tr>
                <tr class="sub-row"><td>Pamatlīdzekļi</td><td class="text-right">${pamatlidzekli}</td></tr>
                <tr class="sub-row"><td>Finanšu ieguldījumi</td><td class="text-right">${finansu_ieguldijumi}</td></tr>
                
                <tr class="main-row border-top"><td>Apgrozāmie līdzekļi</td><td class="text-right">${apgrozamie_lidzekli}</td></tr>
                <tr class="sub-row"><td>Krājumi</td><td class="text-right">${krajumi}</td></tr>
                <tr class="sub-row"><td>Debitori</td><td class="text-right">${debitori}</td></tr>
                <tr class="sub-row"><td>Vērtspapīri</td><td class="text-right">${vertspapiri}</td></tr>
                <tr class="sub-row"><td>Nauda</td><td class="text-right">${nauda}</td></tr>
            </tbody>
            <tfoot>
                <tr class="total-row"><td>Aktīvi kopā</td><td class="text-right">${aktivi_kopa}</td></tr>
            </tfoot>
        </table>
    `;

    // Ģenerē LIABILITIES HTML
    let liabilitiesHtml = `
        <table class="acc-table">
            <thead>
                <tr><th>Pasīvi (${year})</th><th class="right">Summa</th></tr>
            </thead>
            <tbody>
                <tr class="main-row"><td>Pašu kapitāls</td><td class="text-right">${pasu_kapitals}</td></tr>
                <tr class="main-row border-top"><td>Uzkrājumi</td><td class="text-right">${uzkrajumi}</td></tr>
                
                <tr class="main-row border-top"><td>Saistības (Kreditori)</td><td class="text-right">${saistibas_kreditori}</td></tr>
                <tr class="sub-row"><td>Ilgtermiņa saistības</td><td class="text-right">${ilgtermina_saistibas}</td></tr>
                <tr class="sub-row"><td>Īstermiņa saistības</td><td class="text-right">${istermina_saistibas}</td></tr>
                <tr class="sub-row"><td>Nākamo periodu ieņēmumi</td><td class="text-right">${nakamo_periodu_ienemumi}</td></tr>
            </tbody>
            <tfoot>
                <tr class="total-row"><td>Pasīvi kopā</td><td class="text-right">${pasivi_kopa}</td></tr>
            </tfoot>
        </table>
    `;

    // Ievietojam HTML.
    container.innerHTML = customStyles + `
        <div class="acc-wrapper">
            <div class="balance-col">${assetsHtml}</div>
            <div class="balance-col">${liabilitiesHtml}</div>
        </div>
    `;
}
