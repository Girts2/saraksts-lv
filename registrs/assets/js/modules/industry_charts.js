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

// === Klikšķis grafikā → uzņēmuma profils ===
// Ar peli: kamēr redzams uzņēmuma uznirstošais logs, klikšķis atver TĀ profilu (⌘ uz Mac / Ctrl citur, Shift vai
// vidējā poga — jaunā cilnē; Girta lēmums 2026-10-15). Mērķis ir logā pašā (data-reg no tā paša ieraksta, no kura
// nosaukums), nevis pēc mūsu aprēķina: Google logu rāda arī līdz ~19 px no punkta un blīvā grafikā izvēlas citu
// punktu, nekā tuvākais (recenzija 2026-10-15: ar 12 px attāluma sargu 58 % šādu klikšķu atvēra kartīti, kaut
// logs solīja profilu). Logs DOM ir tikai, kamēr tas redzams. Pirkstam un pildspalvai — kā līdz šim: klikšķis
// izvēlas TUVĀKO punktu un zem grafika parāda kartīti ar to un kaimiņiem kā parastām saitēm (skārienā Google
// logu parāda PĒC pointerup, un nākamajā pieskārienā redzams iepriekšējais). Googlebot neklikšķina —
// meklētājam tās pašas saites dod servera renderētais rangu navigators lapas beigās (rangu_navigators_panel.php).

// Juridiskās formas īsāk (kā lib/nozares_tabula.php reg_nozares_isa_forma) — kartītē tikai attēlošanai.
const FORMAS = [
    [/sabiedrība ar ierobežotu atbildību/iu, 'SIA'], [/akciju sabiedrība/iu, 'AS'],
    [/individuālais komersants/iu, 'IK'], [/individuālais uzņēmums/iu, 'IU'],
    [/zemnieku saimniecība/iu, 'ZS'], [/zvejnieku saimniecība/iu, 'ZvS'],
    [/pilnsabiedrība/iu, 'PS'], [/komandītsabiedrība/iu, 'KS'],
    [/kooperatīvā sabiedrība/iu, 'Koop. sab.'], [/ārvalsts komersanta filiāle/iu, 'filiāle']
];
function isaForma(nosaukums) {
    let n = String(nosaukums ?? '');
    for (const [re, isi] of FORMAS) n = n.replace(re, isi);
    return n.trim();
}

/** Teksts HTML saturā (uznirstošie logi ir HTML: isHtml) — nosaukumos ir pēdiņas un &, var būt arī < >. */
function ekr(t) {
    return String(t ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/** Uzņēmums, uz kura profilu var aiziet: numurs ar lapu (11 cipari) un ne pati lapa. */
function arProfilu(reg, lapasReg) {
    const r = String(reg ?? '');
    return /^\d{11}$/.test(r) && r !== String(lapasReg ?? '');
}

/**
 * Uznirstošā loga ietvars: data-reg — klikšķa mērķis (tas pats ieraksts, no kura nosaukums logā) un norāde.
 * Norādi CSS rāda tikai tad, kad grafikam pēdējais rādītājs bija pele (.ic-pele, industry_position_panel.php),
 * — tieši tad klikšķis to arī izpilda (pildspalvai un pirkstam — kartīte).
 */
function logaIetvars(comp, lapasReg, saturs) {
    const reg = String(comp[1] ?? '');
    return arProfilu(reg, lapasReg)
        ? `<div class="google-chart-tooltip-content" data-reg="${reg}">${saturs}<div class="ic-norade">Klikšķis — atvērt profilu</div></div>`
        : `<div class="google-chart-tooltip-content">${saturs}</div>`;
}

/** Rangu grafika uznirstošais logs ("… (Rangs)"). Eksportēts testam (test_tools/registrs/rangu_navigatora_testi.php). */
export function rangaLogs(company, rank, label, vertiba, lapasReg) {
    return logaIetvars(company, lapasReg, `<b>${rank}. ${ekr(company[0])}</b><br>Reģ. nr.: ${ekr(company[1])}<br>${label}: ${vertiba}`);
}

/** Izkliedes grafika uznirstošais logs (apgrozījums pret peļņu). Eksportēts testam. */
export function izkliedesLogs(comp, apgrozijums, pelna, lapasReg) {
    return logaIetvars(comp, lapasReg, `<strong style="font-size:1.1em;">${ekr(comp[0])}</strong><br><hr style="margin:5px 0; border:0; border-top:1px solid #ddd;">Apgrozījums: <b>${apgrozijums}</b><br>Peļņa: <b style="color:${comp[2] < 0 ? 'red' : 'green'}">${pelna}</b>`);
}

const MAC = typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/.test(navigator.platform || navigator.userAgent || '');

/**
 * Klikšķa lēmums grafikā. Eksportēts testam.
 * k: { logaReg — redzamā loga data-reg (vai null), lapasReg, ierice — pointerType, norade — vai norāde «Klikšķis —
 *      atvērt profilu» bija redzama PIRMS nospiešanas (.ic-pele), poga — 0/1, ctrl, meta, shift, mac }
 * → { reg, jaunaCilne } — atvērt profilu; 'kartite' — rādīt tuvāko punktu kartīti; null — neko nedarīt.
 * norade: pēc pieskāriena Google logs paliek, un peles klikšķis bez kustības to atvērtu, kaut norāde bija paslēpta
 * (recenzija 2026-10-15) — profils tikai tad, ja to solīja.
 */
export function klikaLemums(k) {
    if (k.mac && k.ctrl && k.poga === 0) return null;   // Mac: Ctrl+klikšķis = konteksta izvēlne, ne klikšķis
    if (k.ierice === 'mouse' && k.norade && arProfilu(k.logaReg, k.lapasReg)) {
        return { reg: String(k.logaReg), jaunaCilne: k.poga === 1 || k.shift || (k.mac ? k.meta : k.ctrl) };
    }
    return k.poga === 0 ? 'kartite' : null;   // vidējā poga tukšā vietā — neko (kartīte ir parastajam klikšķim)
}

// Vietas kartītē: grupēt arī 4 ciparus ("3 019"), kā rangu navigators lapā (lv-LV to pats nedara).
const vietasFmt = new Intl.NumberFormat('lv-LV', { maximumFractionDigits: 0, useGrouping: 'always' });

/**
 * Grafika "… (Rangs)" secība: uzņēmumi ar vērtību, dilstoši (Array.sort ir stabils — vienādām vērtībām
 * paliek JSON secība, t. i., peļņas). Eksportēta, lai test_tools/registrs/rangu_navigatora_testi.php
 * salīdzinātu nace_rangs (registrs/build/prepare.php) ar TO PAŠU funkciju, ko lieto grafiks.
 */
export function rangaSeciba(companies, dataIndex) {
    const validData = companies.filter(c => c[dataIndex] !== null && c[dataIndex] !== undefined);
    validData.sort((a, b) => b[dataIndex] - a[dataIndex]);
    return validData;
}

function el(tag, klase, teksts) {
    const e = document.createElement(tag);
    if (klase) e.className = klase;
    if (teksts !== undefined) e.textContent = teksts;
    return e;
}

/**
 * Pieliek grafikam klikšķi: redzams uzņēmuma logs un pele → profils, citādi kartīte (klikLemums).
 * izvele(x, y, cli) → { virsraksts, rindas: [{ vieta?, comp, vertiba, izv }] } | null — x, y ir pikseļi
 * grafika SVG koordinātās (tās pašas, ko lieto getChartLayoutInterface()).
 * talummaina — grafikam ir Google tālummaiņa (explorer dragToZoom).
 */
function pievienotKarti(container, chart, izvele, talummaina = false) {
    if (!container || container.dataset.karte === '1') return;
    container.dataset.karte = '1';

    // Redzamā loga klikšķa mērķis (logaIetvars). Kursors-rādītājs un norāde — CSS no tā paša loga un .ic-pele.
    const logaReg = () => container.querySelector('.google-chart-tooltip-content[data-reg]')?.dataset.reg ?? null;
    const ierice = (e) => container.classList.toggle('ic-pele', e.pointerType === 'mouse');
    container.addEventListener('pointermove', ierice);
    // Vidējā poga uz punkta: bez Windows automātiskās ritināšanas (tā sākas mousedown noklusējumā).
    container.addEventListener('mousedown', (e) => { if (e.button === 1 && logaReg()) e.preventDefault(); });
    const karte = el('div', 'ic-karte');
    karte.hidden = true;
    karte.setAttribute('aria-live', 'polite');
    container.insertAdjacentElement('afterend', karte);

    if (talummaina) {
        // Tālummaiņa sākas jau no 1 px vilkšanas, un trīcoša roka klikšķī izgrieza 5 % šķēli (recenzija
        // 2026-10-14; tā nostrādā asinhroni, pēc ~50 ms, — atcelt to vēlāk nozīmētu minēt laiku). Tāpēc kustības
        // līdz 6 px no nospiešanas vietas tai nerāda (capture fāzē, pirms tā tās redz). Garāka vilkšana iet cauri,
        // un taisnstūris sākas no nospiešanas vietas — tālummaiņa strādā kā agrāk.
        let m0 = null;
        container.addEventListener('mousedown', (e) => { m0 = e.button === 0 ? [e.clientX, e.clientY] : null; }, true);
        container.addEventListener('mousemove', (e) => {
            if (m0 && Math.hypot(e.clientX - m0[0], e.clientY - m0[1]) <= 6) e.stopPropagation();
            else m0 = null;   // reiz pārsniegts — tā ir vilkšana, turpmākās kustības vairs neaiztur
        }, true);
        container.addEventListener('mouseup', () => { m0 = null; }, true);
    }

    let sakums = null;
    container.addEventListener('pointerdown', (e) => {
        const norade = container.classList.contains('ic-pele');   // PIRMS ierice(): ko cilvēks redzēja
        ierice(e);
        // Kreisā un vidējā poga; labā izkliedē atiestata tālummaiņu.
        sakums = e.button === 0 || e.button === 1 ? { x: e.clientX, y: e.clientY, poga: e.button, norade } : null;
    });
    container.addEventListener('pointercancel', () => { sakums = null; });   // pirksts sāka ritināt lapu
    // pointerup, nevis click: ja tālummaiņa paspēj sākt vilkšanu, atlaišanu paņem tās taisnstūris, un click
    // vispār nenotiek (mērīts pārlūkā 2026-10-14).
    container.addEventListener('pointerup', (e) => {
        if (!sakums || e.button !== sakums.poga) { sakums = null; return; }
        const vilkts = Math.hypot(e.clientX - sakums.x, e.clientY - sakums.y);
        const norade = sakums.norade;
        sakums = null;
        if (vilkts > 6) return;   // vilkšana (izkliedes grafikā — tālummaiņa ar peli) nav izvēle
        const lemums = klikaLemums({ logaReg: logaReg(), lapasReg: companyRegcode, ierice: e.pointerType, norade, poga: e.button,
                                     ctrl: e.ctrlKey, meta: e.metaKey, shift: e.shiftKey, mac: MAC });
        if (lemums && lemums.reg) {
            const url = '/' + lemums.reg;
            if (lemums.jaunaCilne) window.open(url, '_blank', 'noopener');
            else window.location.href = url;
            return;
        }
        if (lemums !== 'kartite') return;
        const svg = container.querySelector('svg');
        if (!svg) return;
        let cli;
        try { cli = chart.getChartLayoutInterface(); } catch (err) { return; }
        const r = svg.getBoundingClientRect();
        const x = e.clientX - r.left, y = e.clientY - r.top;
        const lauks = cli.getChartAreaBoundingBox();
        const pielaide = 8;
        if (x < lauks.left - pielaide || x > lauks.left + lauks.width + pielaide
            || y < lauks.top - pielaide || y > lauks.top + lauks.height + pielaide) return;
        const sat = izvele(x, y, cli);
        if (!sat || !sat.rindas.length) return;
        zimetKarti(karte, sat);
    });
}

function zimetKarti(karte, sat) {
    karte.replaceChildren();
    const galva = el('div', 'ic-galva');
    galva.append(el('span', '', sat.virsraksts));
    const aizvert = el('button', 'ic-aizvert', '×');
    aizvert.type = 'button';
    aizvert.setAttribute('aria-label', 'Aizvērt');
    aizvert.addEventListener('click', () => { karte.hidden = true; });
    galva.append(aizvert);
    karte.append(galva);

    const ul = el('ul');
    sat.rindas.forEach(rinda => {
        const li = el('li', rinda.izv ? 'ic-izv' : '');
        const reg = String(rinda.comp[1] ?? '');
        const pats = reg === String(companyRegcode);
        // Saite tikai uz numuru ar lapu (11 cipari); pats uzņēmums — bez saites, kā citos sarakstos.
        const ietvars = (!pats && /^\d{11}$/.test(reg)) ? el('a') : el('span');
        if (ietvars.tagName === 'A') ietvars.href = '/' + reg;
        if (rinda.vieta !== undefined) ietvars.append(el('span', 'ic-v', vietasFmt.format(rinda.vieta) + '.'));
        ietvars.append(el('span', 'ic-n', isaForma(rinda.comp[0]) + (pats ? ' (šis uzņēmums)' : '')));
        ietvars.append(el('span', 'ic-r', 'Reģ. nr. ' + reg));
        ietvars.append(el('span', 'ic-s', rinda.vertiba));
        li.append(ietvars);
        ul.append(li);
    });
    karte.append(ul);
    karte.hidden = false;
}

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

    const validData = rangaSeciba(industryData.companies, config.dataIndex);

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
        const tooltip = rangaLogs(company, rank, config.label, valFormatted, companyRegcode);
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

    // Horizontālā ass ir rangs — klikšķa X dod vietu; kartītē tā un ±2 blakus.
    pievienotKarti(container, chart, (x, y, cli) => {
        const n = validData.length;
        const vieta = Math.min(n, Math.max(1, Math.round(cli.getHAxisValue(x))));
        if (!Number.isFinite(vieta)) return null;
        const rindas = [];
        for (let v = Math.max(1, vieta - 2); v <= Math.min(n, vieta + 2); v++) {
            const comp = validData[v - 1];
            const val = comp[config.dataIndex];
            rindas.push({ vieta: v, comp, izv: v === vieta,
                vertiba: config.formatter ? config.formatter.format(val) : String(val) });
        }
        return { virsraksts: `${config.label}: ${vietasFmt.format(vieta)}. vieta no ${vietasFmt.format(n)}`, rindas };
    });

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
    const punkti = [];   // kartītei: tie paši punkti ar savām koordinātām
    let myCompanyFound = false;

    industryData.companies.forEach(comp => {
        const profit = comp[2];
        const turnover = comp[3];

        if (turnover > 0 && profit !== null) {
            const logTurnover = transformPositiveLog(turnover);
            const logProfit = transformSymmetricLog(profit);
            punkti.push({ comp, lx: logTurnover, ly: logProfit });

            let style = 'point { size: 4; fill-color: #cccccc; stroke-color: #aaaaaa; }';
            if (String(comp[1]) === String(companyRegcode)) {
                style = 'point { size: 14; shape-type: star; fill-color: #d9534f; stroke-color: #000000; stroke-width: 1; }';
                myCompanyFound = true;
            }

            const tooltip = izkliedesLogs(comp, formatters.currency.format(turnover), formatters.currency.format(profit), companyRegcode);
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

    // Divas asis — tuvākais punkts pikseļos (līdz 40 px), redzamajā laukumā (arī pēc tālummaiņas).
    pievienotKarti(container, chart, (x, y, cli) => {
        const lauks = cli.getChartAreaBoundingBox();
        const tuvie = [];
        for (const p of punkti) {
            const px = cli.getXLocation(p.lx), py = cli.getYLocation(p.ly);
            if (px < lauks.left || px > lauks.left + lauks.width || py < lauks.top || py > lauks.top + lauks.height) continue;
            const d = (px - x) ** 2 + (py - y) ** 2;
            if (d <= 1600) tuvie.push([d, p]);
        }
        if (!tuvie.length) return null;
        tuvie.sort((a, b) => a[0] - b[0]);
        const rindas = tuvie.slice(0, 5).map(([, p], i) => ({ comp: p.comp, izv: i === 0,
            vertiba: `apgrozījums ${formatters.currency.format(p.comp[3])} · peļņa ${formatters.currency.format(p.comp[2])}` }));
        return { virsraksts: rindas.length > 1 ? `Tuvākie punkti: ${rindas.length}` : 'Izvēlētais punkts', rindas };
    }, true);

    if (container.offsetParent !== null) {
        chart.draw(data, options);
    }
}