// projects/python_generator/templates/assets/js/modules/financial_ratios_module.js

// projects/python_generator/templates/assets/js/modules/financial_ratios_module.js

let chartsToRedraw = [];

// =============================================================================
// KONFIGURĀCIJA
// =============================================================================
const ratioConfigs = {
    current_ratio: {
        title: 'Likviditātes koef. (Current Ratio)',
        vAxis: { title: 'Koeficients', viewWindow: { min: 0 } },
        zones: [
            { value: 1.0, color: '#ffcdd2' },
            { value: 1.5, color: '#ffe0b2' },
            { value: 3.0, color: '#c8e6c9' },
            { value: Infinity, color: '#fff9c4' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Šis ir uzņēmuma \"finanšu veselības\" ātrais tests. Tas atbild uz jautājumu: \"Vai uzņēmums varētu nomaksāt visus savus īstermiņa parādus, ja tas būtu jādara rīt?\".</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Bīstamā zona (&lt; 1.0):</b></div><p class=\"legend-description\">Sarkanais signāls! Uzņēmumam ir mazāk brīvo līdzekļu nekā īstermiņa parādu.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffe0b2;\"></span><b>Piesardzības zona (1.0 – 1.5):</b></div><p class=\"legend-description\">Uzņēmums spēj segt saistības, bet ar minimālu rezervi. Negaidītas problēmas var radīt grūtības.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Optimālā zona (1.5 – 3.0):</b></div><p class=\"legend-description\">Viss kārtībā! Uzņēmumam ir stabila \"drošības rezerve\", lai droši segtu saistības.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Pārmērīga likviditāte (&gt; 3.0):</b></div><p class=\"legend-description\">Droši, bet, iespējams, liela nauda stāv bez pielietojuma, tā vietā, lai tiktu ieguldīta attīstībā.</p></li></ul>`
    },
    quick_ratio: {
        title: 'Ātrās likviditātes koef. (Quick Ratio)',
        vAxis: { title: 'Koeficients', viewWindow: { min: 0 } },
        zones: [
            { value: 0.8, color: '#ffcdd2' },
            { value: 1.0, color: '#ffe0b2' },
            { value: 2.0, color: '#c8e6c9' },
            { value: Infinity, color: '#fff9c4' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Šis ir finanšu \"stresa tests\". Tas neņem vērā krājumus un atbild: \"Vai uzņēmums varētu samaksāt parādus, izmantojot tikai naudu un to, ko tam drīzumā samaksās klienti?\"</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Bīstamā zona (&lt; 0.8):</b></div><p class=\"legend-description\">Uzņēmums ir stipri atkarīgs no krājumu pārdošanas, lai segtu parādus.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ef9a9a;\"></span><b>Robežzona (0.8 – 1.0):</b></div><p class=\"legend-description\">Uzņēmums balansē uz naža asmens. Likviditāte ir pietiekama, bet bez jebkādas rezerves.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Optimālā zona (1.0 – 2.0):</b></div><p class=\"legend-description\">Veselīgs finanšu stāvoklis. Pietiekami daudz \"ātro\" aktīvu.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Neefektīva zona (&gt; 2.0):</b></div><p class=\"legend-description\">Var liecināt par neefektivitāti – pārāk daudz naudas \"stāv\" kontos bez pielietojuma.</p></li></ul>`
    },
    net_profit_margin: {
        title: 'Tīrās peļņas rentabilitāte (%)',
        vAxis: { title: 'Procenti (%)', viewWindow: { min: -15 } },
        zones: [
            { value: 0, color: '#ffcdd2' },
            { value: 10, color: '#fff9c4' },
            { value: Infinity, color: '#c8e6c9' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Šis rādītājs parāda, cik daudz tīrās peļņas paliek pāri no katra apgrozījuma eiro pēc visu izmaksu nomaksas.</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Negatīvs (&lt; 0%):</b></div><p class=\"legend-description\">Uzņēmums strādā ar zaudējumiem.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Zems (0% – 10%):</b></div><p class=\"legend-description\">Uzņēmums pelna, bet peļņu \"apēd\" lielas izmaksas vai zema efektivitāte.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Augsts (&gt; 10%):</b></div><p class=\"legend-description\">Lieliska zīme! Uzņēmums efektīvi kontrolē izmaksas un tam ir spēcīgas tirgus pozīcijas.</p></li></ul>`
    },
    roa: {
        title: 'Aktīvu rentabilitāte (ROA, %)',
        vAxis: { title: 'Procenti (%)', viewWindow: { min: -10 } },
        zones: [
            { value: 2, color: '#ffcdd2' },
            { value: 5, color: '#fff9c4' },
            { value: Infinity, color: '#c8e6c9' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Šis rādītājs ir kā uzņēmuma \"dzinēja efektivitāte\". Tas parāda, cik labi visi tā aktīvi (ēkas, iekārtas, nauda) tiek izmantoti, lai radītu peļņu.</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Zems (&lt; 2%):</b></div><p class=\"legend-description\">Neefektīva aktīvu izmantošana. Aktīvi nenes pietiekamu peļņu.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Apmierinoša zona (2% – 5%):</b></div><p class=\"legend-description\">Resursi tiek izmantoti apmierinoši, bet pastāv iespējas uzlabot efektivitāti.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Augsts (&gt; 5%):</b></div><p class=\"legend-description\">Uzņēmuma aktīvi strādā smagi un efektīvi.</p></li></ul>`
    },
    roe: {
        title: 'Pašu kapitāla rentabilitāte (ROE, %)',
        vAxis: { title: 'Procenti (%)', viewWindow: { min: -10 } },
        zones: [
            { value: 5, color: '#ffcdd2' },
            { value: 15, color: '#fff9c4' },
            { value: Infinity, color: '#c8e6c9' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Šis ir \"investora termometrs\". Tas mēra peļņu attiecībā pret katru eiro, ko ieguldījuši īpašnieki. Ja ROE ir 20%, uz katru ieguldīto eiro īpašnieki gadā nopelna 20 centus.</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Zems (&lt; 5%):</b></div><p class=\"legend-description\">Vāja atdeve īpašniekiem. Kapitāls \"strādā\" vāji.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Vidēja atdeve (5% – 15%):</b></div><p class=\"legend-description\">Kapitāla atdeve ir pieņemamā līmenī, bet neizcila.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Augsts (&gt; 15%):</b></div><p class=\"legend-description\">Īpašnieku ieguldījumi atmaksājas lieliski.</p></li></ul>`
    },
    debt_to_equity: {
        title: 'Saistību / pašu kapitāla attiecība',
        vAxis: { title: 'Koeficients', viewWindow: { min: 0 } },
        zones: [
            { value: 1.5, color: '#c8e6c9' },
            { value: 2.0, color: '#fff9c4' },
            { value: Infinity, color: '#ffcdd2' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Rādītājs parāda, cik daudz no darbības tiek finansēts ar parādiem un cik ar īpašnieku naudu. Ja rādītājs ir 2.0, uz katru īpašnieku ieguldīto eiro ir 2 eiro parāda.</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Zems risks (&lt; 1.5):</b></div><p class=\"legend-description\">Zems risks, finansiāla stabilitāte. Uzņēmums pārsvarā paļaujas uz saviem līdzekļiem.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Paaugstināts risks (1.5 – 2.0):</b></div><p class=\"legend-description\">Uzņēmums sāk paļauties uz aizņemto kapitālu, kas palielina finanšu riskus.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Augsts risks (&gt; 2.0):</b></div><p class=\"legend-description\">Liels risks. Uzņēmums ir stipri atkarīgs no kreditoriem.</p></li></ul>`
    },
    interest_coverage: {
        title: 'Procentu seguma koeficients (IC)',
        vAxis: { title: 'Koeficients', viewWindow: { min: 0 } },
        zones: [
            { value: 1.5, color: '#ffcdd2' },
            { value: 3.0, color: '#fff9c4' },
            { value: Infinity, color: '#c8e6c9' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Šis rādītājs parāda, cik reizes uzņēmuma peļņa (pirms procentiem un nodokļiem) pārsniedz procentu maksājumus. Tas ir "drošības spilvens", kas parāda spēju apkalpot parādus.</p><strong>Rādītāji:</strong><ul><li><div class="legend-title"><span class="color-box" style="background:#ffcdd2;"></span><b>Augsts risks (&lt; 1.5):</b></div><p class="legend-description">Peļņa tikai nedaudz pārsniedz procentu maksājumus. Jebkādas peļņas svārstības var radīt problēmas.</p></li><li><div class="legend-title"><span class="color-box" style="background:#fff9c4;"></span><b>Pietiekams (1.5 – 3.0):</b></div><p class="legend-description">Uzņēmums stabili sedz savus procentu maksājumus, bet rezerve nav liela.</p></li><li><div class="legend-title"><span class="color-box" style="background:#c8e6c9;"></span><b>Stabils (&gt; 3.0):</b></div><p class="legend-description">Uzņēmumam ir ļoti stabila spēja apkalpot savas saistības, ar lielu drošības rezervi.</p></li></ul>`
    },
    asset_turnover: {
        title: 'Aktīvu aprites koeficients',
        vAxis: { title: 'Koeficients', viewWindow: { min: 0 } },
        zones: [
            { value: 0.5, color: '#ffcdd2' },
            { value: 1.0, color: '#fff9c4' },
            { value: Infinity, color: '#c8e6c9' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Mēra uzņēmuma \"darba ātrumu\" – cik efektīvi tas izmanto savus aktīvus, lai radītu apgrozījumu. Ja koeficients ir 2.0, katrs aktīvos ieguldītais eiro gadā ir radījis 2 eiro ieņēmumu.</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Zems (&lt; 0.5):</b></div><p class=\"legend-description\">Resursi tiek izmantoti neefektīvi, iespējams, ir pārāk daudz aktīvu.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Vidējs aprites ātrums (0.5 – 1.0):</b></div><p class=\"legend-description\">Aktīvu izmantošanas efektivitāte ir viduvēja. Ir potenciāls augt.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Augsts (&gt; 1.0):</b></div><p class=\"legend-description\">Uzņēmums intensīvi un efektīvi izmanto savus resursus.</p></li></ul>`
    },
    roce: {
        title: 'Kapitāla efektivitāte (ROCE, %)',
        vAxis: { title: 'Procenti (%)', viewWindow: { min: -10 } },
        zones: [
            { value: 5, color: '#ffcdd2' },
            { value: 15, color: '#fff9c4' },
            { value: Infinity, color: '#c8e6c9' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Viens no svarīgākajiem rādītājiem, kas parāda, cik efektīvi uzņēmums pelna no visa iesaistītā kapitāla – gan īpašnieku, gan aizņemtā.</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Zema efektivitāte (&lt; 5%):</b></div><p class=\"legend-description\">Zema kapitāla efektivitāte.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Vidēja efektivitāte (5% – 15%):</b></div><p class=\"legend-description\">Kapitāls tiek izmantots ar pieņemamu efektivitāti, atbilstoši vidējiem tirgus rādītājiem.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Augsta efektivitāte (&gt; 15%):</b></div><p class=\"legend-description\">Augsta kapitāla efektivitāte, vadība prasmīgi izmanto resursus.</p></li></ul>`
    },
    altman_z_score: {
        title: 'Altmana Z-indekss (Maksātnespējas risks)',
        vAxis: { title: 'Indekss', viewWindow: { min: 0 } },
        zones: [
            { value: 1.8, color: '#ffcdd2' },
            { value: 3.0, color: '#fff9c4' },
            { value: Infinity, color: '#c8e6c9' }
        ],
        legend: `<strong>Ietekme uz uzņēmumu:</strong><p>Šī ir \"bankrota prognozes\" formula, kas apvieno piecus rādītājus, lai novērtētu maksātnespējas risku tuvāko divu gadu laikā.</p><strong>Rādītāji:</strong><ul><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#ffcdd2;\"></span><b>Bīstamā zona (&lt; 1.8):</b></div><p class=\"legend-description\">Augsts bankrota risks.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#fff9c4;\"></span><b>Pelēkā zona (1.8 – 2.99):</b></div><p class=\"legend-description\">Nenoteiktības zona, uzņēmums jāvēro uzmanīgi.</p></li><li><div class=\"legend-title\"><span class=\"color-box\" style=\"background:#c8e6c9;\"></span><b>Drošā zona (&gt; 2.99):</b></div><p class=\"legend-description\">Zems bankrota risks, finansiāli stabils.</p></li></ul>`
    }
};

// =============================================================================
// JAUNĀ, PRECĪZĀ ZĪMĒŠANAS FUNKCIJA
// =============================================================================
function drawRatioChart(containerId, ratioData, specificChartConfig, mainConfig) {
    const descriptionId = containerId.replace('chart_div_', 'desc_div_');
    const chartContainer = document.getElementById(containerId);
    const descContainer = document.getElementById(descriptionId);

    if (!ratioData || ratioData.length < 2 || !chartContainer || !descContainer) {
        const wrapper = chartContainer ? chartContainer.parentElement : null;
        if (wrapper) wrapper.style.display = 'none';
        return;
    }

    // --- 1. Datu un ass robežu aprēķins ---
    const dataValues = ratioData.map(item => item.value);
    const dataMin = Math.min(...dataValues);
    const dataMax = Math.max(...dataValues);
    const sortedZones = [...specificChartConfig.zones].sort((a, b) => a.value - b.value);
    const zoneBoundaries = sortedZones.map(z => z.value).filter(v => v !== Infinity);

    let viewMin = specificChartConfig.vAxis.viewWindow.min;
    if (dataMin < viewMin) {
        viewMin = dataMin;
    }
    let viewMax = Math.max(dataMax, ...zoneBoundaries);
    viewMax = viewMax * 1.1;
    if (viewMin < 0) {
        viewMin = viewMin * 1.1;
    }

    // --- 2. Datu nobīdes loģika (ja ass sākas ar negatīvu vērtību) ---
    const offset = viewMin < 0 ? -viewMin : 0;
    const shiftedRatioData = ratioData.map(item => ({ ...item, value: item.value + offset }));
    const shiftedZones = sortedZones.map(zone => ({ ...zone, value: zone.value === Infinity ? Infinity : zone.value + offset }));
    const shiftedViewMin = viewMin + offset;
    const shiftedViewMax = viewMax + offset;

    // --- 3. Datu sagatavošana `steppedArea` sērijām ---
    const zoneHeaders = shiftedZones.map((_, i) => `Zona ${i + 1}`);
    const dataArray = [['Gads', specificChartConfig.title, { role: 'annotation' }, ...zoneHeaders]];

    shiftedRatioData.forEach(item => {
        const row = [item.year, item.value, (item.value - offset).toFixed(2)];
        let lastBoundary = shiftedViewMin;
        shiftedZones.forEach(zone => {
            const segmentTop = zone.value === Infinity ? shiftedViewMax : Math.min(zone.value, shiftedViewMax);
            const segmentSize = Math.max(0, segmentTop - lastBoundary);
            row.push(segmentSize);
            lastBoundary = segmentTop;
        });
        dataArray.push(row);
    });

    const data = google.visualization.arrayToDataTable(dataArray);

    // --- 4. Grafika opciju definēšana ---
    const seriesOptions = {
        0: { type: 'line', curveType: 'function', color: '#212121', lineWidth: 3, pointSize: 5 }
    };
    shiftedZones.forEach((zone, i) => {
        seriesOptions[i + 1] = { type: 'steppedArea', color: zone.color, enableInteractivity: false, areaOpacity: 0.2, lineWidth: 0 };
    });

    // Izveidojam precīzas iedaļas, kas sakrīt ar zonu robežām
    let ticks = [viewMin, ...zoneBoundaries].filter(v => v <= viewMax);
    if (!ticks.includes(0)) ticks.push(0);
    ticks = [...new Set(ticks)].sort((a, b) => a - b); // Unikālas un sakārtotas

    const formattedTicks = ticks.map(t => ({
        v: t + offset,
        f: t.toFixed(specificChartConfig.vAxis.title.includes('%') ? 1 : 2)
    }));

    const options = {
        title: specificChartConfig.title,
        legend: { position: 'none' },
        width: '100%',
        height: '100%',
        isStacked: true,
        seriesType: 'steppedArea',
        series: seriesOptions,
        hAxis: { title: 'Gads' },
        vAxis: {
            ...specificChartConfig.vAxis,
            viewWindow: { min: shiftedViewMin, max: shiftedViewMax },
            ticks: formattedTicks
        },
        chartArea: { left: 70, top: 40, width: '80%', height: '70%' },
        tooltip: { isHtml: false },
        annotations: {
            textStyle: { fontSize: 10, color: '#333', auraColor: 'white' }
        }
    };

    // --- 5. Grafika zīmēšana ---
    const chart = new google.visualization.ComboChart(chartContainer);
    chart.draw(data, options);
    
    chartsToRedraw.push({
        chart: chart,
        data: data,
        options: options
    });

    // --- 6. Leģendas un aprēķina attēlošana ---
    descContainer.innerHTML = specificChartConfig.legend;
    try {
        const ratioKey = containerId.replace('chart_div_', '');
        const latestYearData = ratioData[ratioData.length - 1];
        const year = latestYearData.year;

        if (mainConfig && mainConfig.allProcessedData && mainConfig.allProcessedData[year]) {
            const allYearData = mainConfig.allProcessedData[year];
            const reportData = allYearData.UGP || allYearData.UKGP;

            if (reportData && reportData.financial_ratios) {
                const ratioDetails = reportData.financial_ratios[ratioKey];
                
                if (ratioDetails && ratioDetails.formula && ratioDetails.calculation) {
                    const finalValue = latestYearData.value;
                    let calculationStr = ratioDetails.calculation;

                    if (['net_profit_margin', 'roa', 'roe', 'roce'].includes(ratioKey)) {
                        calculationStr = `(${calculationStr}) * 100`;
                    }

                    const calculationHtml = `
                        <div class="latest-year-calculation">
                            <strong>Pēdējā gada aprēķins (${year})</strong>
                            <div class="formula">
                                <span class="formula-title">Formula:</span>
                                <span class="formula-text">${ratioDetails.formula}</span>
                            </div>
                            <div class="calculation">
                                <span class="calculation-title">Aprēķins:</span>
                                <span class="calculation-text">${calculationStr} = <strong>${finalValue.toFixed(2)}</strong></span>
                            </div>
                        </div>
                    `;
                    descContainer.innerHTML += calculationHtml;
                }
            }
        }
    } catch (e) {
        console.error(`[Ratios] Error adding formula for ${containerId}:`, e);
    }
}

// =============================================================================
// INICIALIZĀCIJA
// =============================================================================
export function initializeAllRatioCharts(mainConfig) {
    if (typeof google === 'undefined' || typeof google.charts === 'undefined') {
        console.error("Google Charts nav ielādēts.");
        return;
    }
    
    google.charts.load('current', { packages: ['corechart'] });
    google.charts.setOnLoadCallback(() => {
        if (!mainConfig) {
            console.error("[Ratios] Main config object was not provided.");
            return;
        }
        
        chartsToRedraw = [];

        const ratiosHistory = mainConfig.ratiosHistory;
        let chartsDrawn = 0;

        if (!ratiosHistory || Object.keys(ratiosHistory).length === 0) {
             document.getElementById('ratios_no_data_msg').style.display = 'block';
            return;
        }

        for (const ratioKey in ratioConfigs) {
            if (ratiosHistory[ratioKey] && ratiosHistory[ratioKey].length >= 2) {
                const chartId = `chart_div_${ratioKey}`;
                const specificChartConfig = ratioConfigs[ratioKey];
                const chartData = ratiosHistory[ratioKey];
                drawRatioChart(chartId, chartData, specificChartConfig, mainConfig);
                chartsDrawn++;
            } else {
                const chartWrapper = document.getElementById(`chart_div_${ratioKey}`)?.parentElement;
                if (chartWrapper) chartWrapper.style.display = 'none';
            }
        }
        
        if(chartsDrawn > 0) {
             document.getElementById('ratios_no_data_msg').style.display = 'none';
             // Servera renderētā vērtību tabula ir domāta lasītājiem bez JS;
             // kad grafiki ir uzzīmēti, to paslēpjam (skat. financial_ratios_panel.php).
             const ssrSummary = document.getElementById('ratios_summary_ssr');
             if (ssrSummary) ssrSummary.style.display = 'none';
        } else {
             document.getElementById('ratios_no_data_msg').style.display = 'block';
        }

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                chartsToRedraw.forEach(chartInfo => {
                    chartInfo.chart.draw(chartInfo.data, chartInfo.options);
                });
            }, 250);
        });
    });
}

