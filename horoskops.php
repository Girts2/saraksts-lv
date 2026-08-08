<?php require_once __DIR__ . '/lib/applog.php';
applog_boot('horoskops');   // kopīgais notikumu žurnāls (lib/applog.php)
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1350">
    <title>Daudzdimensiju Astroloģijas Matrica</title>
    <meta name="description" content="Bezmaksas astroloģijas matrica: Rietumu horoskops, BaZi, Maiju kalendārs, numeroloģija un saderība vienā pārskatā. Aprēķini pārlūkā, dati nekur netiek sūtīti.">
    <link rel="canonical" href="https://saraksts.lv/horoskops.php">
    <link rel="icon" href="data:;base64,iVBORw0KGgo=">
    <!-- Modern Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <!-- Polyfill: Piemānām kodu WASM bibliotēkai, kas paģērē Node.js moduli -->
    <script>
        var exports = {};
        var module = { exports: exports };
    </script>
    
    <!-- Leaflet priekš Astroloģiskās Kartes -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <link rel="stylesheet" href="horoskops/css/style.css?v=41">

    <!-- Google Analytics (cookie-consent vārtots — aktivizējas tikai pēc piekrišanas; tāpat kā citās sadaļās) -->
    <script type="text/plain" data-category="tracking" async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
    <script type="text/plain" data-category="tracking">
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-XXXXXXXXXX');
    </script>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/header.php'; ?>

    
    <div class="dashboard" style="display: block; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; justify-content: space-between;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #334155;">Ievadiet savus datus</h3>
        </div>

        <div class="input-panel">
            <div class="input-group">
                <label for="ui-date">Dzimšanas datums</label>
                <input type="date" id="ui-date" class="input-control">
                <div class="input-subhint">Diena · mēnesis · gads</div>
            </div>

            <div class="input-group">
                <label for="ui-time">Dzimšanas laiks <span class="label-note">(vietējais)</span></label>
                <input type="time" id="ui-time" class="input-control" value="12:00">
                <label class="input-check" for="ui-time-unknown">
                    <input type="checkbox" id="ui-time-unknown" checked> Nezinu laiku
                </label>
                <div id="utc-display-1" class="input-subhint"></div>
            </div>

            <div class="input-group">
                <label for="ui-location">Dzimšanas vieta</label>
                <div class="input-field">
                    <input type="text" id="ui-location" class="input-control" value="Rīga, Latvija" data-lat="56.9496" data-lon="24.1052" data-timezone="Europe/Riga" autocomplete="off" placeholder="Sāc rakstīt pilsētu…">
                    <div id="autocomplete-list" class="autocomplete-items"></div>
                </div>
                <div class="input-subhint">Kur tu piedzimi</div>
            </div>

            <div class="input-group">
                <label for="ui-current-location">Šībrīža lokācija</label>
                <div class="input-field">
                    <input type="text" id="ui-current-location" class="input-control" value="Rīga, Latvija" data-lat="56.9496" data-lon="24.1052" data-timezone="Europe/Riga" autocomplete="off" placeholder="Sāc rakstīt pilsētu…">
                    <div id="autocomplete-current-list" class="autocomplete-items"></div>
                </div>
                <div class="input-subhint">Kur atrodies tagad</div>
            </div>

            <div class="input-group">
                <label>Dzimums</label>
                <div id="gender-container-1" class="gender-toggle">
                    <label class="gender-opt" id="lbl-gender-m">
                        <input type="radio" name="gender-1" id="ui-gender-m" value="M" checked>
                        <span class="gender-icon" id="icon-gender-m" style="color:#1d4ed8;">♂</span> Vīrietis
                    </label>
                    <label class="gender-opt" id="lbl-gender-f">
                        <input type="radio" name="gender-1" id="ui-gender-f" value="F">
                        <span class="gender-icon" id="icon-gender-f" style="color:#be185d;">♀</span> Sieviete
                    </label>
                </div>
            </div>

            <!-- Precizitātes sadaļa — apvienota ar ievades paneli (viens panelis) -->
            <div id="accuracy-panel" class="accuracy-panel">
            <div class="accuracy-head" onclick="window.toggleAccuracy && window.toggleAccuracy()">
                <span class="accuracy-badge" id="accuracy-pct">~30%</span>
                <span class="accuracy-lead"><b>Cik patiesi ir šie dati?</b> Horoskops parāda tikai tavus iedzimtos “rūpnīcas iestatījumus” — temperamentu, nervu sistēmas tipu un dabisko enerģijas kapacitāti. Pārējo veido tava dzīve, tāpēc rezultāts nav absolūta patiesība.</span>
                <span class="accuracy-toggle">Sīkāk <span class="accuracy-caret">▾</span></span>
            </div>
            <div class="accuracy-body">
                <p class="accuracy-note">Norādot precīzu <b>dzimšanas laiku</b>, precizitāte pieaug par ~10% (no ~30% uz ~40%), jo tas noskaidro iedzimto iestatījumu nolasījumu. Atlikušo personības daļu veido faktori, ko horoskops neredz:</p>
                <div class="accuracy-bars">
                    <div class="acc-row">
                        <span class="acc-lbl">🧬 Iedzimtie “rūpnīcas iestatījumi” (horoskops)</span>
                        <span class="acc-track"><span id="acc-bar-horoscope" class="acc-fill acc-fill--hs" style="width:30%"></span></span>
                        <span id="acc-val-horoscope" class="acc-val acc-val--range">~30% – ~40%</span>
                    </div>
                    <div class="acc-row">
                        <span class="acc-lbl">👶 Agrīnā piesaiste un ģimenes sistēma</span>
                        <span class="acc-track"><span class="acc-fill" style="width:20%"></span></span>
                        <span class="acc-val">~20%</span>
                    </div>
                    <div class="acc-row">
                        <span class="acc-lbl">🌍 Sociokulturālā programmēšana un vide</span>
                        <span class="acc-track"><span class="acc-fill" style="width:15%"></span></span>
                        <span class="acc-val">~15%</span>
                    </div>
                    <div class="acc-row">
                        <span class="acc-lbl">🔄 Neiroplastiskums un dzīves lūzuma punkti</span>
                        <span class="acc-track"><span class="acc-fill" style="width:10%"></span></span>
                        <span class="acc-val">~10%</span>
                    </div>
                    <div class="acc-row">
                        <span class="acc-lbl">🧭 Apzinātība un brīvā griba</span>
                        <span class="acc-track"><span class="acc-fill" style="width:15%"></span></span>
                        <span class="acc-val">~15%</span>
                    </div>
                </div>
            </div>
            </div>
        </div><!-- /input-panel (ievade + precizitāte apvienoti) -->

        <div style="display: flex; justify-content: center;">
            <button class="btn-calc btn-calc--hero" onclick="calculateMatrix()">
                <span class="btn-hero-label">✨ Ģenerēt Matricu</span>
                <span class="btn-hero-sub">Startē pilno izpēti — 6 sistēmas · 6 sadaļas</span>
            </button>
        </div>
        <div id="system-time-display" style="text-align: center; margin-top: 15px; font-size: 0.85rem; color: #64748b; font-family: 'Outfit', sans-serif;">
            Sistēmas laiks, ko izmanto aprēķinos: <span id="current-system-time" style="font-weight: 600; color: #334155;">--:--</span>
        </div>
    </div>

    <div id="intro-description" class="intro-panel">
        <div class="intro-hero">
            <div class="intro-hero-title">Viena dzimšanas karte. Sešas neatkarīgas sistēmas. Viens kopīgs spriedums.</div>
            <div class="intro-hero-sub">Algoritms liek sešām atšķirīgas izcelsmes tradīcijām balsot par katru tava rakstura īpašību — un pie katra secinājuma parāda, cik stipri tās saskan.</div>
        </div>

        <div class="intro-title">📚 Sešas sistēmas — katra nosedz savu dzīves jomu:</div>
        <ul class="intro-list">
            <li><span style="color:#3b82f6;">🌍</span> <b>Rietumu astroloģija un progresijas:</b> iekšējā psiholoģija un šī brīža motivācija — “kas man ir aktuāli tieši tagad?”.</li>
            <li><span style="color:#8b5cf6;">🌌</span> <b>Vēdiskā astroloģija (Vimshottari Dasha):</b> Indijas tradīcijas laika plānošanas sistēma — iezīmē veiksmes un pārbaudījumu koridorus gadu desmitiem uz priekšu.</li>
            <li><span style="color:#ef4444;">🐉</span> <b>Ķīniešu BaZi:</b> pragmatisks skats uz darbu — darba raksturs, stresa noturība un karjeras cikli.</li>
            <li><span style="color:#10b981;">☀️</span> <b>Maiju kalendārs (Dreamspell):</b> garīgais kompass — dvēseles uzdevumi un ēnas puses; senā 260 dienu Tzolkin cikla mūsdienu lasījums.</li>
            <li><span style="color:#f59e0b;">🏛️</span> <b>Sengrieķu (helēnisma) astroloģija:</b> klasiskais skats uz likteni — palīdz nošķirt, ko vari mainīt ar savu gribu un kas ir jāpieņem.</li>
            <li><span style="color:#65a30d;">🌳</span> <b>Ķeltu koku horoskops:</b> enerģijas bioritms — kā tu uzkrāj un atjauno spēkus, izdegšanas un atveseļošanās ritms; 20. gs. sistēma, iedvesmota ķeltu koku simbolikas.</li>
        </ul>

        <div class="intro-tabs-head">🧭 Kas tevi sagaida — sešas sadaļas:</div>
        <div class="intro-tabs-grid">
            <div class="intro-tab-card" style="--tabc:#3b82f6; --tabc-bg:rgba(59,130,246,0.12);">
                <div class="intro-tab-name"><span class="intro-tab-ico">👤</span> Profils</div>
                <div class="intro-tab-hook">Tavs raksturs vienā kadrā.</div>
                <div class="intro-tab-desc">Arhetipa portrets un personības matrica: enerģijas kapacitāte, komunikācijas stils, stresa noturība, analītika, radošums. Pie katras īpašības redzams, cik sistēmu par to balso vienādi.</div>
                <div class="intro-tab-meta">Metode: visu 6 sistēmu krusteniskais balsojums + ticamības rādītājs</div>
            </div>
            <div class="intro-tab-card" style="--tabc:#f59e0b; --tabc-bg:rgba(245,158,11,0.14);">
                <div class="intro-tab-name"><span class="intro-tab-ico">💼</span> Karjera</div>
                <div class="intro-tab-hook">Kur tavas dabiskās dotības atmaksājas visvairāk?</div>
                <div class="intro-tab-desc">Tavs vadīšanas stils, personības un interešu spektrs un konkrētu profesiju saraksts, kas atbilst iedzimtajam darba raksturam — no komandas līdera līdz vientuļajam ekspertam.</div>
                <div class="intro-tab-meta">Metode: astro-profils, tulkots Big Five un RIASEC interešu valodā</div>
            </div>
            <div class="intro-tab-card" style="--tabc:#8b5cf6; --tabc-bg:rgba(139,92,246,0.12);">
                <div class="intro-tab-name"><span class="intro-tab-ico">🧠</span> Psiholoģija</div>
                <div class="intro-tab-hook">Dziļākā sadaļa — tava iekšējā mehānika.</div>
                <div class="intro-tab-desc">Psiholoģiskā kopaina un četru virtuālu speciālistu konsīlijs: motivācijas sviras, klupšanas akmeņi, ēnas puses un psihosomatikas signāli — ar praktiskiem scenārijiem, ko ar to darīt.</div>
                <div class="intro-tab-meta">Metode: Junga arhetipu karte + sistēmu pretrunu radars</div>
            </div>
            <div class="intro-tab-card" style="--tabc:#10b981; --tabc-bg:rgba(16,185,129,0.12);">
                <div class="intro-tab-name"><span class="intro-tab-ico">📅</span> Prognoze</div>
                <div class="intro-tab-hook">No šīs stundas līdz gadu desmitiem.</div>
                <div class="intro-tab-desc">Dienas panelis ar 24 stundu ritmu un rīcības plānu, 7 dienu horoskops, kurā krāsa parādās tikai tad, ja vismaz divas sistēmas saskan, biznesa kalendārs “kad rīkoties” un dzīves cikli līdz pat 120 gadiem.</div>
                <div class="intro-tab-meta">Metode: 3 neatkarīgu sistēmu konsenss + Dashas dzīves koridori</div>
            </div>
            <div class="intro-tab-card" style="--tabc:#e11d48; --tabc-bg:rgba(225,29,72,0.10);">
                <div class="intro-tab-name"><span class="intro-tab-ico">❤️</span> Attiecības</div>
                <div class="intro-tab-hook">Divi cilvēki, sešas metodes, viena aina.</div>
                <div class="intro-tab-desc">Saderības spidometrs, ģimenes psihologa ieteikumi katram partnerim atsevišķi un kopdzīves simulācija 25 gadiem uz priekšu — ar iespējamiem krīžu un izaugsmes logiem.</div>
                <div class="intro-tab-meta">Metode: 6 balsotāju konsenss — sinastrija, kompozīts, BaZi, Ashta Kuta, pāru psiholoģija, Big Five</div>
            </div>
            <div class="intro-tab-card" style="--tabc:#0ea5e9; --tabc-bg:rgba(14,165,233,0.12);">
                <div class="intro-tab-name"><span class="intro-tab-ico">🌴</span> Atvaļinājums</div>
                <div class="intro-tab-hook">Kur pasaulē tev iet vislabāk?</div>
                <div class="intro-tab-desc">Personalizēta pasaules karte: izvēlies ceļojuma datumus, un algoritms iekrāso valstis pēc tā, kā tur “skan” tavas planētas. TOP galamērķi pa kontinentiem un tuvināšana līdz pat 10 km rūtiņām.</div>
                <div class="intro-tab-meta">Metode: relokācijas astroloģija + izvēlētā perioda tranzīti</div>
            </div>
        </div>

        <div class="intro-foot">* Horoskops ir pašrefleksijas rīks, nevis diagnoze — prāts mēdz vispārīgus apgalvojumus uztvert kā unikāli precīzus (Barnuma efekts). Sistēmas nav vienlīdz senas: Dreamspell un koku horoskops ir 20. gadsimta interpretācijas.</div>
    </div>

    <div id="loading" style="display: none;">🚀 Izsaucam Kosmisko Sinerģiju (WASM)...</div>
    <div id="dashboard" class="dashboard" style="display: none;"></div>

    <script src="horoskops/js/timezone/moment.min.js"></script>
    <script>
        // moment.min.js nokrita uz CommonJS ceļu (WASM polyfill definēja window.module).
        // Izglābam moment no module.exports uz window.moment.
        if (!window.moment && typeof module !== 'undefined' && module.exports && module.exports.isMoment) {
            window.moment = module.exports;
        }
    </script>
    <script src="horoskops/js/timezone/moment-timezone-with-data.js"></script>
    <script type="module" src="horoskops/js/main.js?v=289"></script>

    <?php // AGPL-3.0 13. pants: lietotājiem, kas darbojas ar programmu pa tīklu, REDZAMI jāpiedāvā
          // pirmkods. Šī lapa piegādā pārlūkam Swiss Ephemeris (WASM), tāpēc arī Astrodienst
          // autortiesību paziņojums ir jāsaglabā. Bez šī bloka būtu jāpērk Professional licence. ?>
    <footer style="margin: 60px 20px 30px; padding-top: 20px; border-top: 1px solid #ddd;
                   text-align: center; font-size: 0.82rem; line-height: 1.7; color: #6b7280;">
        Aprēķinus veic <strong>Swiss Ephemeris</strong> — © Astrodienst AG, Šveice.
        Izmantots saskaņā ar <a href="horoskops/swisseph/LICENSE" style="color: #4f46e5;">GNU AGPL v3</a>.<br>
        Šīs lapas pilnais pirmkods ir brīvi pieejams:
        <a href="lejupielade.php" style="color: #4f46e5;">lejupielādēt pirmkodu</a>.
    </footer>

    <!-- Šai lapai augšā ir CommonJS polyfill (var exports/module priekš Swiss Ephemeris WASM + moment).
         UMD bibliotēka cookieconsent to redz un piesaistās 'exports', NEVIS window.CookieConsent —
         tāpēc banneris un Google Analytics nestrādātu. Uz cookie ielādes brīdi pagaidām noņemam
         'exports', lai UMD izvēlas browser-global zaru (window.CookieConsent). -->
    <script>
        window.__hs_exports = window.exports;
        try { window.exports = undefined; } catch (e) {}
    </script>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/cookie/cookie.php'; ?>
    <script>
        try { window.exports = window.__hs_exports; delete window.__hs_exports; } catch (e) {}
    </script>
</body>
</html>
