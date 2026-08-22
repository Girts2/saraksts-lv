// projects/python_generator/templates/assets/js/main.js

import { initializeAllRatioCharts } from './modules/financial_ratios_module.js';
import { init as initIndustryCharts } from './modules/industry_charts.js';
import { initializeTooltips } from './tooltip.js';
import { initChartsController } from './charts_controller.js';
import { initLiveSearch } from './live_search.js';
import { init as initAutocomplete } from './autocomplete.js';
import { init as initPapilduDati } from './modules/papildu_dati.js';

/**
 * Dinamiski ielādē skriptu un atgriež Promise.
 * @param {string} src Skripta URL
 * @returns {Promise<void>}
 */
function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Script load error for ${src}`));
        document.head.appendChild(script);
    });
}

document.addEventListener('DOMContentLoaded', async function() {
    try {
        // "Papildu reģistri un dati": pogas "… un vēl N" pilnā satura ielādei.
        initPapilduDati();
        // --- HAMBURGER IZVĒLNE ---
        const menuToggle = document.getElementById('menu-toggle');
        const mainNav = document.getElementById('main-nav');
        
        if (menuToggle && mainNav) {
            const menuIcon = menuToggle.querySelector('i') || menuToggle; // Fallback, ja ikonas nav
            
            menuToggle.addEventListener('click', function() {
                mainNav.classList.toggle('is-open');
                this.classList.toggle('is-open');
                
                const isExpanded = mainNav.classList.contains('is-open');
                this.setAttribute('aria-expanded', isExpanded);
                
                // Ja izmanto FontAwesome ikonas:
                if (menuToggle.querySelector('i')) {
                    if (isExpanded) {
                        menuIcon.classList.remove('fa-bars');
                        menuIcon.classList.add('fa-times');
                    } else {
                        menuIcon.classList.remove('fa-times');
                        menuIcon.classList.add('fa-bars');
                    }
                }
                this.setAttribute('aria-label', isExpanded ? 'Aizvērt izvēlni' : 'Atvērt izvēlni');
            });
        }

        // --- OPTIMIZĀCIJA: LOKĀLĀ D3 IELĀDE ---
        // Mēs tagad ielādējam no /registrs/assets/js/lib/, ko Python skripts lejupielādēja
        await loadScript('/registrs/assets/js/lib/d3.min.js');
        await loadScript('/registrs/assets/js/lib/d3-sankey.min.js');

        // Pārbauda konfigurāciju
        if (typeof config === 'undefined') {
            console.error("Kļūda: Globālais konfigurācijas objekts 'config' nav definēts!");
            return;
        }

        // Inicializējam moduļus
        if (config.ratiosHistory) {
            initializeAllRatioCharts(config);
        }

        if (document.getElementById('industry_chart_scatter')) {
            initIndustryCharts(config);
        }
        
        initializeTooltips();

        if (config.sankeyAvailableYears) {
            initChartsController();
        }

        if (document.getElementById('searchInput')) {
            initLiveSearch();
        }

        if (document.getElementById('search_term')) {
            const autocompleteConfig = {
                 baseActionUrl: config.baseActionUrl || '',
                 minAutocompleteChars: config.minAutocompleteChars || 2,
                 maxSearchTermLength: config.maxSearchTermLength || 100
            };
            initAutocomplete(autocompleteConfig);
        }

        // Fallback, ja nav datu.
        // dataAvailableForCharts nozīmē TIKAI to, ka nav peļņas/zaudējumu aprēķina
        // (sankeyAvailableYears tukšs). Biedrībām un nodibinājumiem PZA nav nekad, bet
        // serveris to panelī jau ir uzrenderējis reālas rindas (aktīvi, darbinieku
        // skaits) — tās aizsegt nedrīkst. Turklāt Pārskata panelī .no-data atrodas
        // IEKŠ .table-responsive-wrapper, tāpēc vecais kods vienlaikus rādīja ziņojumu
        // un paslēpa tā vecāku: apmeklētājs redzēja tukšu virsrakstu bez teksta.
        if (typeof config !== 'undefined' && !config.dataAvailableForCharts) {
            const panelsToHide = ['.sankey-facts', '.balance-facts', '.summary-panel-facts'];

            panelsToHide.forEach(panelSelector => {
                const panel = document.querySelector(panelSelector);
                if (!panel) return;

                // Serveris jau ir uzrenderējis rindas -> panelī IR saturs, neaiztiekam.
                if (panel.querySelector('table tbody tr')) return;

                const controls = panel.querySelector('.sankey-controls, .chart-container, .table-responsive-wrapper');
                if (controls) controls.style.display = 'none';

                // Ziņojumu rādām tikai tad, ja tas nav paslēptā elementa iekšienē.
                const noDataEl = panel.querySelector('.no-data');
                if (noDataEl && !(controls && controls.contains(noDataEl))) {
                    noDataEl.style.display = 'block';
                }

                const typeToggle = panel.querySelector('.sankey-type-toggle');
                if (typeToggle) typeToggle.style.display = 'none';
            });
        }

    } catch (error) {
        console.error("Kļūda ielādējot bibliotēkas vai inicializējot moduļus:", error);
    }
});