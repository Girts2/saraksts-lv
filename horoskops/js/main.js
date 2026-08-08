import { generateFullProfile } from './horoskops.js?v=51';
import { setupAutocomplete } from './ui/autocomplete.js?v=9';
import { generateAntardashaReading, showAntarReading } from './ui/readings.js?v=9';
import { initAstroMap } from './ui/astro_map.js?v=9';
import { drawYearlySparklines } from './ui/sparklines.js?v=15';
import { renderDashboard } from './ui/render_dashboard.js?v=217';
import { initSkyChart } from './ui/sky_chart.js?v=25';
import { initAstroLocator } from './ui/astro_locator.js?v=20';
import { localToUtc } from './timezone/local_to_utc.js?v=1';

// Assign to window for inline onclick handlers
window.generateAntardashaReading = generateAntardashaReading;
window.showAntarReading = showAntarReading;
window.drawYearlySparklines = drawYearlySparklines;
window.initAstroMap = initAstroMap;
window.initSkyChart = initSkyChart;
window.initAstroLocator = initAstroLocator;

// ── UTC palīgfunkcijas ─────────────────────────────────────────────────────
// localToUtc pārcelta uz ./timezone/local_to_utc.js (koplietota ar time_sweep.js)

function refreshUtcHint(dateInputId, timeInputId, locInputId, displayId, unknownCheckId) {
    const dateEl    = document.getElementById(dateInputId);
    const timeEl    = document.getElementById(timeInputId);
    const locEl     = document.getElementById(locInputId);
    const dispEl    = document.getElementById(displayId);
    const unknownEl = document.getElementById(unknownCheckId);
    if (!timeEl || !locEl || !dispEl || !dateEl) return;

    const lon = parseFloat(locEl.getAttribute('data-lon') || '24.1052');
    const timezone = locEl.getAttribute('data-timezone');

    // Dienas pāreja (piem. Tokijā 00:30 = UTC iepriekšējā dienā 15:30) — parāda lietotājam,
    // lai nav pārsteigums, kāpēc aprēķinos redzams cits datums.
    const dayShiftNote = utcDateStr => (utcDateStr && utcDateStr !== dateEl.value)
        ? ` <span style="opacity:0.65">· UTC datums: ${utcDateStr}</span>` : '';

    if (unknownEl?.checked) {
        // Nezināms laiks → laukā fiksēts 12:00 (vietējais pusdienlaiks aprēķiniem), lauks
        // aizsvītrots un nerediģējams; UTC padomu NErāda, lai nemulsinātu skatītāju.
        if (!timeEl.classList.contains('time-struck')) {
            timeEl.dataset.prevTime = timeEl.value || '12:00';
            timeEl.classList.add('time-struck');
        }
        timeEl.value = '12:00';
        timeEl.disabled = true;
        dispEl.innerHTML = '';
        return;
    }
    if (timeEl.classList.contains('time-struck')) {
        timeEl.classList.remove('time-struck');
        timeEl.disabled = false;
        if (timeEl.dataset.prevTime) timeEl.value = timeEl.dataset.prevTime;
    }
    if (!dateEl.value) { dispEl.innerHTML = ''; return; }
    const { utcStr, offset, timezoneStr, utcDateStr } = localToUtc(dateEl.value, timeEl.value, lon, timezone);
    const sign = offset >= 0 ? '+' : '';
    dispEl.innerHTML = `→ UTC: <b style="color:#334155">${utcStr}</b> <span style="opacity:0.65">(UTC${sign}${offset}h, aprēķināts pēc ${timezoneStr})</span>${dayShiftNote(utcDateStr)}`;
}

function setupUtcListeners(dateInputId, timeInputId, locInputId, displayId, unknownCheckId) {
    const dateEl    = document.getElementById(dateInputId);
    const timeEl    = document.getElementById(timeInputId);
    const locEl     = document.getElementById(locInputId);
    const unknownEl = document.getElementById(unknownCheckId);
    if (!timeEl || !locEl || !dateEl) return;

    const update = () => refreshUtcHint(dateInputId, timeInputId, locInputId, displayId, unknownCheckId);
    
    dateEl.addEventListener('input', update);
    dateEl.addEventListener('change', update);
    timeEl.addEventListener('input', update);
    unknownEl?.addEventListener('change', update);

    new MutationObserver(update).observe(locEl, { attributes: true, attributeFilter: ['data-lon', 'data-timezone'] });

    update();
}
// ─────────────────────────────────────────────────────────────────────────────

function updateSystemTime() {
    const timeEl = document.getElementById('current-system-time');
    if (timeEl) {
        const now = new Date();
        const dOpts = { year: 'numeric', month: '2-digit', day: '2-digit' };
        const tOpts = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        timeEl.innerHTML = `${now.toLocaleDateString('lv-LV', dOpts)} plkst. ${now.toLocaleTimeString('lv-LV', tOpts)}`;
    }
}
setInterval(updateSystemTime, 1000);
updateSystemTime();

window.calculateMatrix = async function() {
    const dateStr = document.getElementById('ui-date').value;
    const timeStr = document.getElementById('ui-time').value;
    const locInput = document.getElementById('ui-location');
    const curLocInput = document.getElementById('ui-current-location');
    
    const targetLat = parseFloat(locInput.getAttribute('data-lat'));
    const targetLon = parseFloat(locInput.getAttribute('data-lon'));
    
    const curLat = parseFloat(curLocInput.getAttribute('data-lat'));
    const curLon = parseFloat(curLocInput.getAttribute('data-lon'));

    if (!dateStr || !timeStr || isNaN(targetLat) || isNaN(targetLon) || isNaN(curLat) || isNaN(curLon)) {
        alert("Lūdzu, aizpildiet visus laukus pareizi un izvēlieties pilsētu no norādītā saraksta!");
        return;
    }

    document.getElementById('intro-description').style.display = 'none';
    document.getElementById('dashboard').style.display = 'none';
    document.getElementById('loading').style.display = 'block';

    try {
        const isTimeUnknown = document.getElementById('ui-time-unknown')?.checked || false;
        
        const genderInput = document.querySelector('input[name="gender-1"]:checked');
        if (!genderInput) {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('intro-description').style.display = 'block';
            const container = document.getElementById('gender-container-1');
            if (container) {
                let count = 0;
                const flashInterval = setInterval(() => {
                    container.style.backgroundColor = count % 2 === 0 ? 'rgba(239,68,68,0.15)' : 'transparent';
                    container.style.boxShadow = count % 2 === 0 ? '0 0 12px rgba(239,68,68,0.5)' : 'none';
                    count++;
                    if (count > 5) {
                        container.style.backgroundColor = 'transparent';
                        container.style.boxShadow = 'none';
                        clearInterval(flashInterval);
                    }
                }, 250);
            }
            return;
        }
        const gender1 = genderInput.value;
        window.currentGender1 = gender1;

        // Konvertē lokālo laiku uz UTC, izmantojot IANA laika joslu un moment-timezone
        const timezone = locInput.getAttribute('data-timezone');
        // Nezināms laiks → vietējais pusdienlaiks (12:00 LOKĀLAIS) konvertēts uz UTC,
        // NEVIS UTC 12:00 (dzimšanas laiks vienmēr ir vietējais, ne UTC).
        const localConv = localToUtc(dateStr, isTimeUnknown ? '12:00' : timeStr, targetLon, timezone);
        const utcTimeStr = localConv.utcStr;

        // Atrašanās vietas timezone — aprēķina pirms generateFullProfile, lai to padotu
        const currentTimezone = curLocInput.getAttribute('data-timezone') || timezone || "Europe/Riga";
        const profile = await generateFullProfile(dateStr, utcTimeStr, targetLat, targetLon, curLat, curLon, isTimeUnknown, timezone, currentTimezone, gender1, localConv.utcDateStr);
        if (profile && profile.current_loc) {
            profile.current_loc.timezone = currentTimezone;
        }
        window.currentProfile1 = profile;


        renderDashboard(profile, dateStr, locInput.value, curLocInput.value);
        
        // Piesaistām autocomplete Partneris tabā izveidotajiem laukiem
        const locInput2 = document.getElementById('ui-location-2');
        const listDiv2 = document.getElementById('autocomplete-list-2');
        if (locInput2 && listDiv2) {
            setupAutocomplete(locInput2, listDiv2);
        }

        window._redrawSparklines = () => {
            if (window.lastUzticamibaScores && window.drawYearlySparklines) {
                window.drawYearlySparklines(dateStr, timeStr, ...window.lastUzticamibaScores, targetLat, targetLon, isTimeUnknown);
            }
        };

        setTimeout(() => {
            let todayDateStr = new Date().toISOString().split('T')[0];
            initAstroMap(todayDateStr);
            window._redrawSparklines();
        }, 100);
    } catch (error) {
        document.getElementById('loading').innerHTML = `<span style="color:red">Kļūda: ${error.message}</span>`;
        console.error(error);
    }
};

window.calculatePartnerMatrix = async function() {
    if (!window.currentProfile1) return;

    const dateStr2 = document.getElementById('ui-date-2').value;
    const timeStr2 = document.getElementById('ui-time-2').value;
    const locInput2 = document.getElementById('ui-location-2');
    const targetLat2 = parseFloat(locInput2.getAttribute('data-lat'));
    const targetLon2 = parseFloat(locInput2.getAttribute('data-lon'));
    const isTimeUnknown2 = document.getElementById('ui-time-unknown-2')?.checked || false;
    
    const gender2Input = document.querySelector('input[name="gender-2"]:checked');
    if (!gender2Input) {
        const container2 = document.getElementById('gender-container-2');
        if (container2) {
            let count = 0;
            const flashInterval = setInterval(() => {
                container2.style.backgroundColor = count % 2 === 0 ? 'rgba(239,68,68,0.15)' : 'transparent';
                container2.style.boxShadow = count % 2 === 0 ? '0 0 12px rgba(239,68,68,0.5)' : 'none';
                count++;
                if (count > 5) {
                    container2.style.backgroundColor = 'transparent';
                    container2.style.boxShadow = 'none';
                    clearInterval(flashInterval);
                }
            }, 250);
        }
        return;
    }
    const gender2 = gender2Input.value;
    window.currentGender2 = gender2;

    if (!dateStr2 || (!isTimeUnknown2 && !timeStr2) || isNaN(targetLat2) || isNaN(targetLon2)) {
        alert("Lūdzu, aizpildiet visus partnera laukus pareizi un izvēlieties pilsētu!");
        return;
    }

    const curLocInput = document.getElementById('ui-current-location');
    const curLat = parseFloat(curLocInput.getAttribute('data-lat'));
    const curLon = parseFloat(curLocInput.getAttribute('data-lon'));

    const btn = document.querySelector('button[onclick="calculatePartnerMatrix()"]');
    if(btn) btn.innerText = "Aprēķina...";

    try {
        // Konvertē lokālo dzimšanas laiku uz UTC
        const timezone2 = locInput2.getAttribute('data-timezone');
        const localConv2 = localToUtc(dateStr2, isTimeUnknown2 ? '12:00' : timeStr2, targetLon2, timezone2);
        const utcTimeStr2 = localConv2.utcStr;

        // Partnera "pašreizējā vieta" = viņa PAŠA dzimšanas vieta (nevis 1. personas dzīvesvieta),
        // lai p2 tranzīti/dasha būtu pašsakonsekventi. Ashta Kuta saderībai (tikai Mēness) tas
        // rezultātu nemaina, bet novērš klusu p1 dzīvesvietas iesēšanos partnera profilā.
        const currentTimezone2 = timezone2 || "Europe/Riga";
        const profile2 = await generateFullProfile(dateStr2, utcTimeStr2, targetLat2, targetLon2, targetLat2, targetLon2, isTimeUnknown2, timezone2, currentTimezone2, gender2, localConv2.utcDateStr);
        if (profile2 && profile2.current_loc) {
            profile2.current_loc.timezone = currentTimezone2;
        }
        window.currentProfile2 = profile2;


        // Jauns partneris → notīra kešoto kopdzīves simulāciju (lai rāda placeholder + pārrēķina)
        window._relForecastHtml = null;

        import('./ui/tabs/tab_compatibility.js?v=56').then(module => {
            // Atjauninām gauge paneli (neatkarīga metode, default Stingrā)
            const gaugeEl = document.getElementById('compatibility-gauge-container');
            if (gaugeEl) gaugeEl.innerHTML = module.renderGaugePanel(window.currentProfile1, profile2);

            // Atjauninām rezultātu konteineru
            const resultsHtml = module.renderTabCompatibilityResults(window.currentProfile1, profile2);
            const container = document.getElementById('compatibility-results-container');
            if (container) container.innerHTML = resultsHtml;

            // Kopdzīves simulācija (async — efemerīda); aizpilda #relationship-forecast-container
            const g1 = window.currentGender1 || 'M';
            const g2 = window.currentGender2 || gender2 || 'F';
            const parihara = window.currentPariharaMode !== false;
            if (module.populateForecast) module.populateForecast(window.currentProfile1, profile2, g1, g2, parihara);

            // Atjauninām _tabContent un _megaTabContent lai nākamā pārslēgšana rāda jaunus datus
            const freshCompatHtml = module.renderTabCompatibility(window.currentProfile1, profile2);
            if (window._tabContent)    window._tabContent.partneris       = freshCompatHtml;
            if (window._megaTabContent) window._megaTabContent['saderiba'] = freshCompatHtml;

            if (btn) btn.innerText = 'Aprēķināt Saderību';
        });
    } catch (error) {
        alert('Kļūda aprēķinot partnera matricu: ' + error.message);
        if (btn) btn.innerText = 'Aprēķināt Saderību';
    }
};


window.generatePotentialPartners = async function() {
    if (!window.currentProfile1) return;
    const btn = document.getElementById('btn-find-partners');
    const container = document.getElementById('potential-partners-list');
    if (!btn || !container) return;

    btn.innerText = "Meklē... (tas var aizņemt dažas sekundes)";
    btn.disabled = true;
    container.innerHTML = `<div style="text-align:center; padding: 20px; color: #64748b;">🔄 Skeneris analizē ~5000 dienas... Lūdzu uzgaidiet.</div>`;

    try {
        const g1Radio = document.querySelector('input[name="gender-1"]:checked');
        const gender1 = g1Radio ? g1Radio.value : 'M';
        const targetGender = gender1 === 'M' ? 'F' : 'M'; // Default to opposite gender for search

        import('./ui/tabs/tab_compatibility.js?v=56').then(async module => {
            if (module.generatePotentialPartnersHTML) {
                // Metode no finder slēdža (noklusējums Tradicionālā ar Parihara). Stingrais režīms dažām
                // nakšatru/rashi pozīcijām strukturāli neļauj sasniegt izcilu saderību — tad "Izcili" josla
                // var būt tukša, bet "Vidējā saderība" josla joprojām rāda rezultātus.
                const parihara = window.currentFinderPariharaMode !== false;
                const html = await module.generatePotentialPartnersHTML(window.currentProfile1, gender1, targetGender, parihara);
                container.innerHTML = html;
            }
            btn.innerText = "Meklēt vēlreiz";
            btn.disabled = false;
        });
    } catch(err) {
        container.innerHTML = `<div style="color:red; padding:10px;">Kļūda: ${err.message}</div>`;
        btn.innerText = "Meklēt Potenciālos Partnerus";
        btn.disabled = false;
    }
};


// Noklusējuma saderības metode = Tradicionālā (ar Dosha Parihara), ja lietotājs nav izvēlējies citu.
if (window.currentPariharaMode === undefined) window.currentPariharaMode = true;

// VIENOTS saderības metodes slēdzis (Stingrā ↔ Tradicionālā ar Parihara).
// Gauge paneļa pārslēdzis UN "Dziļā Saderības Analīze" pārslēdzis ir SINHRONI — abi izsauc šo
// pašu funkciju, abi atjaunojas vienlaikus. Pārrēķina no jau aprēķinātajiem profiliem.
window.applyCompatMethod = function(parihara) {
    window.currentPariharaMode = !!parihara;
    if (!window.currentProfile1 || !window.currentProfile2) return;
    import('./ui/tabs/tab_compatibility.js?v=56').then(module => {
        // 1) Gauge panelis — dzīvā animācija (adata + skaitlis + kreisās kolonnas sadalījums + pogas).
        //    Ja gauge SVG nav DOM, pārrenderē visu paneli.
        const animated = module.applyGaugeState && module.applyGaugeState(window.currentProfile1, window.currentProfile2);
        if (!animated) {
            const gaugeEl = document.getElementById('compatibility-gauge-container');
            if (gaugeEl) gaugeEl.innerHTML = module.renderGaugePanel(window.currentProfile1, window.currentProfile2);
        }

        // 2) "Dziļā Saderības Analīze" rezultātu konteiners (satur savu pārslēdzi pareizā stāvoklī).
        const resultsEl = document.getElementById('compatibility-results-container');
        if (resultsEl) resultsEl.innerHTML = module.renderTabCompatibilityResults(window.currentProfile1, window.currentProfile2);

        // 3) Kešs, lai pārslēgšanās starp cilnēm rāda pareizo režīmu.
        const fresh = module.renderTabCompatibility(window.currentProfile1, window.currentProfile2);
        if (window._tabContent)     window._tabContent.partneris       = fresh;
        if (window._megaTabContent) window._megaTabContent['saderiba'] = fresh;
    });
};
// Abi pārslēdži (gauge + dziļā analīze) izsauc vienu un to pašu sinhronizēto funkciju.
window.setCompatibilityMethod = window.applyCompatMethod;

// ── Partneru meklētāja metodes slēdzis (atsevišķs no galvenās analīzes) ──────
// Noklusējums = Tradicionālā (ar Parihara), jo stingrais režīms dažām pozīcijām neļauj
// sasniegt izcilu saderību. Pārslēdzot — pārmeklē atkārtoti, ja rezultāti jau ir parādīti.
if (window.currentFinderPariharaMode === undefined) window.currentFinderPariharaMode = true;

window.setFinderMethod = function(parihara) {
    window.currentFinderPariharaMode = !!parihara;
    // Atjaunina kešoto cilnes saturu, lai radio stāvoklis saglabājas pēc cilnes pārslēgšanas
    import('./ui/tabs/tab_compatibility.js?v=56').then(module => {
        if (window.currentProfile1 && window.currentProfile2) {
            const fresh = module.renderTabCompatibility(window.currentProfile1, window.currentProfile2);
            if (window._tabContent)     window._tabContent.partneris       = fresh;
            if (window._megaTabContent) window._megaTabContent['saderiba'] = fresh;
        }
    });
    // Ja rezultāti jau ir parādīti, pārmeklē ar jauno metodi (citādi gaida manuālu pogas klikšķi)
    const list = document.getElementById('potential-partners-list');
    if (list && list.innerHTML.trim() && window.currentProfile1) {
        window.generatePotentialPartners();
    }
};

// Gauge paneļa pārslēdzis = TĀ PATI sinhronizētā funkcija kā "Dziļā Saderības Analīze".
// Vairs nav atsevišķa currentGaugePariharaMode — abi lasa currentPariharaMode (default Tradicionālā).
window.setGaugeMethod = window.applyCompatMethod;

const locInput = document.getElementById('ui-location');
const curLocInput = document.getElementById('ui-current-location');
const locInput2 = document.getElementById('ui-location-2');
const listDiv = document.getElementById('autocomplete-list');
const curListDiv = document.getElementById('autocomplete-current-list');
const listDiv2 = document.getElementById('autocomplete-list-2');

setupAutocomplete(locInput, listDiv);
setupAutocomplete(curLocInput, curListDiv);
if (locInput2 && listDiv2) {
    setupAutocomplete(locInput2, listDiv2);
}

// ── Dzimšanas datuma atcerēšanās ─────────────────────────────────────────────
// Ievadītais datums tiek saglabāts localStorage un atjaunots gan pēc lapas
// pārlādes, gan pēc formas DOM pārrakstīšanas (partnera forma renderējas no keša).
function persistDateInput(inputId, storageKey) {
    const el = document.getElementById(inputId);
    if (!el) return;
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved && !el.value) el.value = saved;
    } catch (e) { /* privātais režīms bez localStorage — datums vienkārši netiek atcerēts */ }
    el.addEventListener('change', () => {
        // Tukša vērtība (daļēji izdzēsts lauks, starpstāvoklis) NEDRĪKST izdzēst
        // atcerēto datumu — saglabā tikai pilnu, derīgu vērtību.
        if (!el.value) return;
        try { localStorage.setItem(storageKey, el.value); } catch (e) {}
    });
}
persistDateInput('ui-date', 'astroUiDate1');

// UTC padoms galvenajai formai (lapas ielādes brīdī)
setupUtcListeners('ui-date', 'ui-time', 'ui-location', 'utc-display-1', 'ui-time-unknown');

// ── Precizitātes panelis ────────────────────────────────────────────────────
// Bāze ~40%; norādot dzimšanas laiku (rūtiņa NEatzīmēta) → ~50%.
window.toggleAccuracy = function() {
    document.getElementById('accuracy-panel')?.classList.toggle('accuracy-open');
};
function setupAccuracyPanel() {
    const unknownEl = document.getElementById('ui-time-unknown');
    const pctEl = document.getElementById('accuracy-pct');
    if (!pctEl) return;
    const update = () => {
        const timeKnown = unknownEl && !unknownEl.checked;
        // Bez laika ~30%, ar laiku ~40% (40 + 60% dzīves faktoru = 100%).
        // acc-val-horoscope rāda pastāvīgo diapazonu "~30% – ~40%" (HTML, netiek mainīts).
        pctEl.textContent = timeKnown ? '~40%' : '~30%';
        const bar = document.getElementById('acc-bar-horoscope');
        if (bar) bar.style.width = timeKnown ? '40%' : '30%';
    };
    unknownEl?.addEventListener('change', update);
    update();
}
setupAccuracyPanel();

// UTC padoms partnera formai — tiek inicializēts tabs.js, kad partneris cilne tiek atvērta.
// Katrā atvēršanā cilnes DOM tiek pārrakstīts no keša, tāpēc te arī atjauno saglabāto
// partnera datumu un piesien saglabāšanas klausītāju jaunajam elementam.
window._setupPartnerUtcHint = function() {
    persistDateInput('ui-date-2', 'astroUiDate2');
    setupUtcListeners('ui-date-2', 'ui-time-2', 'ui-location-2', 'utc-display-2', 'ui-time-unknown-2');
};

// NEAUTOMĀTISKI: sākumā rāda ievada apraksta logu (intro-description); horoskopu ģenerē
// TIKAI pēc pogas 'Ģenerēt Matricu' nospiešanas. (Agrāk auto-izsauca calculateMatrix uz ielādes.)
