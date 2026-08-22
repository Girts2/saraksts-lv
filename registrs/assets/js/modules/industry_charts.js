// projects/python_generator/templates/assets/js/modules/industry_charts.js

if (typeof google === 'undefined' || typeof google.charts === 'undefined') {
    console.error("Industry Charts: Google Charts nav ielādēts.");
}

let industryData = null;
let companyRegcode = null;
let chartsToRedraw = [];

const formatters = {
    currency: new Intl.NumberFormat('lv-LV', { 
        style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 0 
    }),
    number: new Intl.NumberFormat('lv-LV', { 
        minimumFractionDigits: 0, maximumFractionDigits: 0 
    })
};

function transformSymmetricLog(value) {
    if (value === 0 || value === null || value === undefined) return 0;
    const sign = Math.sign(value);
    const minAbsValue = 1;
    if (Math.abs(value) < minAbsValue) return 0;
    return sign * Math.log10(Math.abs(value));
}

function transformPositiveLog(value) {
    if (!value || value <= 0) return 0;
    const minAbsValue = 1;
    if (value < minAbsValue) return 0;
    return Math.log10(value);
}

// === 1. NACE koda meklēšana DOMā (Fallback) ===
function getNaceCodeFromDom() {
    const tableDiv = document.querySelector('div[data-table-name="pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata"]');
    if (!tableDiv) return null;
    
    const headers = Array.from(tableDiv.querySelectorAll('th'));
    const naceIndex = headers.findIndex(th => th.getAttribute('data-original-name') === 'Pamatdarbibas_NACE_kods');
    
    if (naceIndex === -1) return null;

    const rows = tableDiv.querySelectorAll('tbody tr');

    for (let i = 0; i < rows.length; i++) {
        const cell = rows[i].querySelector(`td:nth-child(${naceIndex + 1})`);
        if (cell) {
            let code = cell.textContent.trim().replace('.', '');
            if (code && code !== '?' && code !== 'UNDEFINED' && code !== '-') {
                return code;
            }
        }
    }
    return null;
}

// === 2. Pogas "Detalizēti" atjaunošana (LABOTS) ===
function updateDetailsButton(naceCode) {
    if (!naceCode || naceCode.length < 3) {
        const existingBtn = document.querySelector('.industry-position-facts .panel-header .btn-details-red');
        if (existingBtn) existingBtn.style.display = 'none';
        return;
    }

    const panelHeader = document.querySelector('.industry-position-facts .panel-header');
    if (!panelHeader) return;

    let anchor = naceCode;
    if (naceCode === '0000') {
        anchor = 'UNDEFINED';
    } else if (naceCode.length === 4 && !naceCode.includes('.')) {
        anchor = naceCode.substring(0, 2) + '.' + naceCode.substring(2);
    }
    
    const correctUrl = `https://saraksts.lv/nozare.php#${anchor}`;

    let btn = panelHeader.querySelector('.btn-details-red');

    if (btn) {
        btn.href = correctUrl;
        btn.style.display = 'inline-block';
    } else {
        btn = document.createElement('a');
        btn.href = correctUrl;
        btn.className = 'btn btn-details-red';
        btn.target = '_blank';
        btn.rel = 'noopener noreferrer';
        btn.textContent = 'Detalizēti';
        panelHeader.appendChild(btn);
    }
}

function setupTabs() {
    const tabButtons = document.querySelectorAll('.industry-tab-btn');
    if (!tabButtons.length) return;

    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.industry-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.industry-tab-content').forEach(c => c.classList.remove('active'));
            
            this.classList.add('active');
            const targetId = this.getAttribute('data-target');
            const targetEl = document.getElementById(targetId);
            if (targetEl) targetEl.classList.add('active');
            
            setTimeout(() => {
                chartsToRedraw.forEach(chartInfo => {
                     const container = chartInfo.chart.getContainer();
                     if (container && container.offsetParent !== null) {
                        chartInfo.chart.draw(chartInfo.data, chartInfo.options);
                    }
                });
            }, 50);
        });
    });
}

export function init(config) {
    if (typeof google === 'undefined' || typeof google.charts === 'undefined') {
        return;
    }
    try {
        companyRegcode = config.searchRegNr;
        setupTabs();
        
        google.charts.load('current', { packages: ['corechart'] });
        google.charts.setOnLoadCallback(loadIndustryData);
        
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                chartsToRedraw.forEach(chartInfo => {
                    const container = chartInfo.chart.getContainer();
                    if (container && container.offsetParent !== null) {
                        chartInfo.chart.draw(chartInfo.data, chartInfo.options);
                    }
                });
            }, 250);
        });
    } catch (e) {
        console.error("Industry Charts Init Error:", e);
    }
}

async function loadIndustryData() {
    const containersIds = ['industry_chart_profit', 'industry_chart_turnover', 'industry_chart_employees', 'industry_chart_scatter'];
    
    containersIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = 'block';
            if (id === 'industry_chart_scatter') el.style.height = '500px';
            else el.style.height = '320px';
        }
    });

    try {
        let naceCode = null;
        
        if (typeof config !== 'undefined' && config.naceCode) {
            naceCode = String(config.naceCode).replace('.', '').trim();
        }
        
        if (!naceCode) {
            naceCode = getNaceCodeFromDom();
        }
        
        if (!naceCode || naceCode === '?' || naceCode === 'UNDEFINED' || naceCode === 'None') {
            naceCode = '0000';
        }

        updateDetailsButton(naceCode);

        const dataPath = `/registrs/assets/search/nace_stats.php?code=${naceCode}`;
        
        const response = await fetch(dataPath);
        if (!response.ok) {
            if (response.status === 404) {
                throw new Error(`Statistika šai nozarei vai grupai (${naceCode}) nav pieejama.`);
            }
            throw new Error("Kļūda ielādējot nozares datus.");
        }
        
        industryData = await response.json();
        
        if (!industryData.companies || industryData.companies.length === 0) {
             throw new Error("Šajā grupā nav pietiekamu datu salīdzināšanai.");
        }

        drawAllCharts();

    } catch (error) {
        console.warn("Industry Charts:", error);
        containersIds.forEach(id => {
            const el = document.getElementById(id);
            if(el) el.innerHTML = `<p class="no-data" style="padding:20px; color:#777;">Dati nav pieejami (${error.message})</p>`;
        });
    }
}

function drawAllCharts() {
    chartsToRedraw = []; 
    try { drawRankCharts(); } catch(e) { console.error(e); }
    if (document.getElementById('industry_chart_scatter')) {
        try { drawScatterPlot(); } catch(e) { console.error(e); }
    }
}

function drawRankCharts() {
    // --- LABOJUMS: Nomainīts trigger uz 'focus' ---
    const commonOptions = {
        trigger: 'focus', // Lai rādītu uzbraucot ar peli
        isHtml: true
    };

    drawSingleRankChart({
        containerId: 'industry_chart_profit', dataIndex: 2, title: 'Peļņa / Zaudējumi (Rangs)',
        transformFunc: transformSymmetricLog, label: 'Peļņa',
        axisTicks: [{v: 7, f: '10M'}, {v: 6, f: '1M'}, {v: 5, f: '100k'}, {v: 4, f: '10k'}, {v: 3, f: '1k'}, {v: 0, f: '0 €'}, {v: -3, f: '-1k'}, {v: -4, f: '-10k'}, {v: -5, f: '-100k'}, {v: -6, f: '-1M'}],
        formatter: formatters.currency,
        extraOptions: commonOptions
    });
    drawSingleRankChart({
        containerId: 'industry_chart_turnover', dataIndex: 3, title: 'Apgrozījums (Rangs)',
        transformFunc: transformPositiveLog, label: 'Apgrozījums',
        axisTicks: [{v: 9, f: '1 mljrd.'}, {v: 8, f: '100M'}, {v: 7, f: '10M'}, {v: 6, f: '1M'}, {v: 5, f: '100k'}, {v: 4, f: '10k'}, {v: 3, f: '1k'}, {v: 0, f: '0 €'}],
        formatter: formatters.currency,
        extraOptions: commonOptions
    });
    drawSingleRankChart({
        containerId: 'industry_chart_employees', dataIndex: 4, title: 'Darbinieku skaits (Rangs)',
        transformFunc: transformPositiveLog, label: 'Darbinieki',
        axisTicks: [{v: 4, f: '10k'}, {v: 3, f: '1k'}, {v: 2, f: '100'}, {v: 1, f: '10'}, {v: 0, f: '0'}],
        formatter: formatters.number,
        extraOptions: commonOptions
    });
}

function drawSingleRankChart(config) {
    const container = document.getElementById(config.containerId);
    if (!container || !industryData || !industryData.companies) return;

    let validData = industryData.companies.filter(c => c[config.dataIndex] !== null && c[config.dataIndex] !== undefined);
    validData.sort((a, b) => b[config.dataIndex] - a[config.dataIndex]);

    if (validData.length === 0) {
         container.innerHTML = '<p class="no-data">Nav datu.</p>';
         return;
    }

    const dataTable = new google.visualization.DataTable();
    dataTable.addColumn('number', 'Rangs');
    dataTable.addColumn('number', 'Vērtība (Log)');
    dataTable.addColumn({type: 'string', role: 'tooltip', 'p': {'html': true}});
    dataTable.addColumn({type: 'string', role: 'style'});

    let myRank = -1;
    const totalCompanies = validData.length;
    const rows = [];
    
    validData.forEach((company, index) => {
        const rank = index + 1;
        const regNr = String(company[1]);
        const originalValue = company[config.dataIndex];
        const transformedValue = config.transformFunc(originalValue);

        let style;
        if (regNr === String(companyRegcode)) {
            myRank = rank;
            style = 'point { fill-color: #d9534f; stroke-color: #a94442; size: 10; shape-type: star; }';
        } 
        else if (config.dataIndex === 2 && originalValue < 0) {
            style = 'point { fill-color: #d9534f; size: 2; stroke-color: transparent; }';
        } 
        else {
            style = 'point { fill-color: #007bff; size: 2; stroke-color: transparent; }';
        }

        const valFormatted = config.formatter ? config.formatter.format(originalValue) : originalValue;
        // --- LABOJUMS: Pievienota klase 'google-chart-tooltip-content' ---
        const tooltip = `<div class="google-chart-tooltip-content"><b>${rank}. ${company[0]}</b><br>Reģ. nr.: ${regNr}<br>${config.label}: ${valFormatted}</div>`;
        rows.push([rank, transformedValue, tooltip, style]);
    });

    dataTable.addRows(rows);

    // --- LABOJUMS: trigger: 'focus' opcijās ---
    const options = {
        title: config.title,
        titleTextStyle: { fontSize: 14, color: '#333' },
        hAxis: { title: 'Vieta nozarē (Rangs)', gridlines: { color: 'transparent' }, minValue: 1, viewWindow: { min: 1 } },
        vAxis: { title: config.label + ' (Log)', baseline: 0, baselineColor: '#ccc', gridlines: { count: -1 }, ticks: config.axisTicks },
        legend: 'none',
        tooltip: { isHtml: true, trigger: 'focus' }, // Šis nodrošina hover
        // left:72 — lai ass atzīme "1 mljrd." (platākā) ietilpst vienā rindā;
        // ar auto atkāpi Google Charts to aplauza vai apgrieza ar daudzpunkti.
        chartArea: { left: 72, width: '78%', height: '70%', top: 40 },
        pointSize: 2,
        backgroundColor: 'transparent'
    };

    const chart = new google.visualization.ScatterChart(container);
    
    const msgId = 'msg_' + config.containerId;
    const oldMsg = document.getElementById(msgId); if(oldMsg) oldMsg.remove();
    const msgDiv = document.createElement('div');
    msgDiv.id = msgId;
    msgDiv.className = 'rank-info-msg';
    msgDiv.style.textAlign = 'center';
    msgDiv.style.fontSize = '0.9em'; msgDiv.style.marginBottom = '5px'; msgDiv.style.marginTop = '5px'; msgDiv.style.color = '#333';
    msgDiv.innerHTML = (myRank !== -1) 
        ? `Uzņēmuma pozīcija: <b>${myRank}.</b> no ${totalCompanies} uzņēmumiem.` 
        : `Dati nav atrasti.`;
    
    if(container.parentNode) container.parentNode.insertBefore(msgDiv, container);
    chartsToRedraw.push({ chart, data: dataTable, options });
    
    if (container.offsetParent !== null) chart.draw(dataTable, options);
}

function drawScatterPlot() {
    const container = document.getElementById('industry_chart_scatter');
    if (!container || !industryData) return;

    const data = new google.visualization.DataTable();
    data.addColumn('number', 'Apgrozījums (Log)');
    data.addColumn('number', 'Peļņa (Log)');
    data.addColumn({type: 'string', role: 'tooltip', 'p': {'html': true}});
    data.addColumn({type: 'string', role: 'style'});

    const rows = [];
    let myCompanyFound = false;

    industryData.companies.forEach(comp => {
        const profit = comp[2];
        const turnover = comp[3];
        
        if (turnover > 0 && profit !== null) {
            const logTurnover = transformPositiveLog(turnover);
            const logProfit = transformSymmetricLog(profit);

            let style = 'point { size: 4; fill-color: #cccccc; stroke-color: #aaaaaa; }';
            if (String(comp[1]) === String(companyRegcode)) {
                style = 'point { size: 14; shape-type: star; fill-color: #d9534f; stroke-color: #000000; stroke-width: 1; }';
                myCompanyFound = true;
            }

            // --- LABOJUMS: Pievienota klase 'google-chart-tooltip-content' ---
            const tooltip = `<div class="google-chart-tooltip-content"><strong style="font-size:1.1em;">${comp[0]}</strong><br><hr style="margin:5px 0; border:0; border-top:1px solid #ddd;">Apgrozījums: <b>${formatters.currency.format(turnover)}</b><br>Peļņa: <b style="color:${profit < 0 ? 'red' : 'green'}">${formatters.currency.format(profit)}</b></div>`;
            rows.push([logTurnover, logProfit, tooltip, style]);
        }
    });
    
    if (rows.length === 0) {
        container.innerHTML = '<p class="no-data">Nav pietiekamu datu izkliedes grafikam.</p>';
        return;
    }

    data.addRows(rows);

    // --- LABOJUMS: trigger: 'focus' opcijās ---
    const options = {
        title: 'Apgrozījums vs Peļņa (Logaritmiskā skala)',
        titleTextStyle: { fontSize: 16, color: '#333' },
        hAxis: { 
            title: 'Apgrozījums (EUR, Log skala)', 
            gridlines: { count: -1 }, 
            ticks: [{v: 3, f: '1k'}, {v: 4, f: '10k'}, {v: 5, f: '100k'}, {v: 6, f: '1M'}, {v: 7, f: '10M'}, {v: 8, f: '100M'}, {v: 9, f: '1 mljrd.'}]
        },
        vAxis: { 
            title: 'Peļņa (EUR, Log skala)', 
            baseline: 0,
            ticks: [
                {v: 7, f: '10M'}, {v: 6, f: '1M'}, {v: 5, f: '100k'}, {v: 4, f: '10k'}, {v: 3, f: '1k'}, 
                {v: 0, f: '0'}, 
                {v: -3, f: '-1k'}, {v: -4, f: '-10k'}, {v: -5, f: '-100k'}, {v: -6, f: '-1M'}
            ] 
        },
        legend: 'none',
        tooltip: { isHtml: true, trigger: 'focus' }, // Šis nodrošina hover
        chartArea: { left: 90, top: 50, width: '75%', height: '70%' },
        explorer: { actions: ['dragToZoom', 'rightClickToReset'], axis: 'horizontal', keepInBounds: true, maxZoomIn: 0.05 }
    };

    const chart = new google.visualization.ScatterChart(container);
    
    const msgId = 'msg_scatter_info';
    const oldMsg = document.getElementById(msgId); if(oldMsg) oldMsg.remove();
    const msgDiv = document.createElement('div');
    msgDiv.id = msgId;
    msgDiv.style.textAlign = 'center'; msgDiv.style.fontSize = '0.9em'; msgDiv.style.color = '#666'; msgDiv.style.marginTop = '5px';
    msgDiv.innerHTML = myCompanyFound 
        ? 'Uzņēmums ir atzīmēts ar <span style="color:#d9534f; font-weight:bold;">sarkanu zvaigzni</span>.' 
        : 'Aplūkotais uzņēmums šajos datos nav atrasts.';
    
    if(container.parentNode) container.parentNode.appendChild(msgDiv);

    chartsToRedraw.push({ chart, data, options });

    if (container.offsetParent !== null) {
        chart.draw(data, options);
    }
}