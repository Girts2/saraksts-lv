import { renderVisualShot } from './sections/visual_shot.js?v=28';
// Pārcelti no cilnes 'x' (q1) — atbild uz "KAS", dzīvo cilnē 'Profils'
import { renderLeadershipPanel, renderPersonalityMatrix, renderBipolarProfile } from './tabs/profile_panels.js?v=19';
import { renderWorkCharacterPanel } from './tabs/work_character_panel.js?v=1';
import { renderEnergyTalentsCards } from './sections/synergy_panel.js?v=17';
import { renderCareersPanel } from './tabs/tab_profils.js?v=27';
import { renderTabMotivacija } from './tabs/tab_motivacija.js?v=8';
import { renderContradictionRadar } from './sections/contradiction_radar.js?v=1';
import { renderInvestorMemoPanel } from './sections/investor_memo_panel.js?v=17';
import { renderPsychOverviewMap } from './sections/psych_overview_map.js?v=33';
import { renderSpecialistPanel } from './sections/specialist_panel.js?v=3';
import { renderResourcesSection } from './sections/resources.js?v=5';
// Rakstura sekcijas (KAS) — pārceltas uz 'Profils' (agrāk tikai test_panel.js)
import { renderCommunicationSection } from './sections/communication.js?v=6';
import { renderStressSection } from './sections/stress.js?v=5';
import { renderReliabilitySection } from './sections/reliability.js?v=3';
import { renderAnalyticsSection } from './sections/analytics.js?v=5';
import { renderCreativitySection } from './sections/creativity.js?v=6';
import { renderTabKopsavilkums } from './tabs/tab_kopsavilkums.js';

// ── Cilņu saturs ──
import { renderTabInfluence }  from './tabs/tab_influence.js?v=9';
import { renderTabExperiment } from './tabs/tab_experiment.js?v=44';
// 'Prognoze' (t4) saliek finest→broadest render_dashboard'ā no atsevišķiem gabaliem:
//   koridors (bios/kalendārs/explorer/radars/lielie cikli) + q4 (7 dienas + ilgtermiņš).
// Cilnes pašā augšā — 'Dienas' izpildošais panelis (ŠODIENAS biznesa pārskats).
import { renderDayBusinessPanel } from './sections/day_business_view.js?v=9';
import { renderVedicCorridor } from './sections/vedic_corridor.js?v=14';
import { renderLongTermPanels } from './tabs/tab_q4_future.js?v=19';
import { renderTabQ5Relate }   from './tabs/tab_q5_relate.js?v=35';
import { renderArudhaCard, renderMidpointsCard, renderShadowCard }  from './tabs/tab_y_insights.js?v=32';

import { confidenceBadge }  from './components/confidence.js?v=2';

export function renderDashboard(profile, displayDate, displayLoc, displayCurLoc, profile2 = null) {
    console.log('=== renderDashboard STARTS ===', { displayDate, hasProfile: !!profile });
    document.getElementById('loading').style.display = 'none';
    const dash = document.getElementById('dashboard');
    dash.style.display = 'grid';

    const nowMs = new Date().getTime();

    // ── PRE-COMPUTE TAB CONTENT ─────────────────────────────────────────────
    // 'Prognoze' (t4) gabali (sk. nakotnePane — sakārtoti finest→broadest):
    const dayBizHtml   = (() => { try { return renderDayBusinessPanel(profile); }  catch(e) { return `<div style="color:red;padding:1rem"><b>Dienas panelis kļūda:</b> ${e.message}</div>`; } })();
    const corridor     = (() => { try { return renderVedicCorridor(profile, nowMs); } catch(e) { return { error: e.message }; } })();
    // '7 dienas' (renderWeek7Panel) NOŅEMTS 2026-07-08 — dublējās ar 'Dienas · biznesa pārskatu'.
    const longTermHtml = (() => { try { return renderLongTermPanels(profile); } catch(e) { return `<div style="color:red;padding:1rem"><b>Ilgtermiņš kļūda:</b> ${e.message}</div>`; } })();
    const q5Html = (() => { try { return renderTabQ5Relate(profile, profile2); }  catch(e) { return `<div style="color:red;padding:2rem"><b>Q5 kļūda:</b> ${e.message}</div>`; } })();

    // Drošs renderis + paneļa-līmeņa ticamības nozīmīte (visām cilnēm). cfb(arhetips, opts)
    const safe = (fn, name) => { try { return fn(); } catch (e) { return `<div style="color:red;padding:2rem"><b>${name} kļūda:</b> ${e.message}</div>`; } };
    const cfb = (arch, opts) => confidenceBadge(arch, profile, opts || {});

    // KRITISKI PAR SECĪBU (2026-07-09 audits): renderReliabilitySection sinhroni uzstāda
    // window.lastUzticamibaScores (5 sistēmu skorus), ko VISAS cfb('multi-system') nozīmītes
    // lieto konverģences (C) faktoram. Tāpēc Uzticamība jāizrēķina PIRMS pārējo paneļu
    // būvēšanas — citādi paneļi virs tās pirmajā ģenerācijā rāda ticamību bez saskaņas
    // faktora (100%), bet atkārtotā — IEPRIEKŠĒJĀS personas skorus.
    const reliabilityHtml = safe(() => renderReliabilitySection(profile), 'Uzticamība');

    // ── DASHBOARD HTML ────────────────────────────────────────────────────────
    // Vizuālais šāviens dod DIVAS daļas: profila saturs (→ Profils) un "Šobrīd" CTA (→ 'x').
    const { profileHtml, nowCtaHtml } = renderVisualShot(profile, displayLoc, displayCurLoc);

    // ── 'PROFILS' PANE ─ arhetips + radars/spektrs + "Kā veidojas šie skaitļi?" ──
    //   + [pārcelts no 'x'] Vadīšanas stils, un beigās — pilnā Personības Matrica.
    // Rakstura sekciju papildu argumenti (kā test_panel.js)
    const baziBaseStr = JSON.stringify({ year: profile.bazi?.gods?.Year, month: profile.bazi?.gods?.Month, day_hidden: profile.bazi?.hiddenGod });
    const mayanStr    = JSON.stringify(profile.maya_profile || {});
    // maya_profile glabā person_data zem atslēgas 'basic' (horoskops.js:372) — NE 'person_data'
    const mColor      = profile.maya_profile?.basic?.color || 'N/A';
    const mTone       = profile.maya_profile?.basic?.tone || 1;
    const mSign       = profile.maya_profile?.basic?.sign || '';   // GLIFS (SIGNS masīvs): "Ik"/"Ok"/"Eb"/"Men"... — precīzai zīmes pārbaudei

    const profilsPane = profileHtml + `
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderBipolarProfile(profile, { riasec: false }), 'Personības profils')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderWorkCharacterPanel(profile), 'Darba stila pozīcija')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderEnergyTalentsCards(profile), 'Enerģijas kapacitāte un talanti')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${reliabilityHtml}
            ${cfb('multi-system', { crossSources: 'reliability' })}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderCommunicationSection(profile, baziBaseStr, mayanStr, mColor, mSign), 'Komunikācijas stils')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderStressSection(profile, baziBaseStr, mayanStr, mColor, mSign), 'Stresa noturība')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderAnalyticsSection(profile, baziBaseStr, mayanStr, mColor, mTone, mSign), 'Analītika un mācīšanās')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderResourcesSection(profile, baziBaseStr, mayanStr, mColor, mSign), 'Resursi')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderCreativitySection(profile, baziBaseStr, mayanStr, mColor, mTone, mSign), 'Radošums un pašizpausme')}
            ${cfb('date-fixed')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderPersonalityMatrix(profile), 'Personības matrica')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            <button onclick="var x=document.getElementById('profils-kopsav-extra'),o=x.style.display==='none';x.style.display=o?'':'none';this.innerHTML=o?'▲ Sakļaut Personības Kopsavilkumu':'▼ Rādīt Personības Kopsavilkumu (sistēmu pārskats + Sinerģijas Metrika)';" style="width:100%; background:#f1f5f9; border:1px solid #e2e8f0; color:#475569; font-size:0.85rem; font-weight:700; padding:11px; border-radius:10px; cursor:pointer;">▼ Rādīt Personības Kopsavilkumu (sistēmu pārskats + Sinerģijas Metrika)</button>
        </div>
        <div id="profils-kopsav-extra" style="display:none;">
            <div style="margin:1.5rem auto 0;">
                ${safe(() => renderTabKopsavilkums(profile), 'Kopsavilkums')}
                ${cfb('multi-system')}
            </div>
        </div>`;

    // ── 'KARJERA' PANE (cilne 2) — "Darba & Vadīšanas Stils" + Personība & Intereses (Big Five + RIASEC) ──
    const karjeraPane = `
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderLeadershipPanel(profile), 'Vadīšanas stils')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderBipolarProfile(profile, { riasec: true }), 'Personība un intereses')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 2rem;">
            ${safe(() => renderCareersPanel(profile), 'Piemērotās profesijas')}
            ${cfb('multi-system')}
        </div>`;

    // Laika horizonta josla (sekcijas virsraksts) un sakļaujams bloks (⌄, noklusējumā aizvērts).
    // Definēti PIRMS psihologijaPane, jo arī tā tos lieto (sakļautie paneļi aiz konsīlija).
    const horizonBar = (icon, title, sub) => `
        <div style="display:flex; align-items:baseline; gap:0.6rem; flex-wrap:wrap; margin:2.4rem 0 1rem; padding-bottom:0.5rem; border-bottom:2px solid #e2e8f0;">
            <span style="font-size:1.15rem;">${icon}</span>
            <h3 style="margin:0; font-size:1.1rem; font-weight:900; color:#0f172a;">${title}</h3>
            <span style="font-size:0.75rem; color:#94a3b8;">${sub}</span>
        </div>`;
    const collapsible = (summary, inner, extraAttr = '') => {
        const isOpen = extraAttr.includes(' open');   // izvērsts pēc noklusējuma → pareizs pavediens
        return `
        <details style="margin-top:1rem; border:1px solid #e2e8f0; border-radius:12px; background:#fff;"${extraAttr}>
            <summary style="cursor:pointer; padding:0.9rem 1.2rem; font-size:0.9rem; font-weight:700; color:#475569; list-style:none; display:flex; align-items:center; gap:0.5rem;">
                <span style="color:#94a3b8; font-size:1.1rem;">⌄</span> ${summary}
                <span style="margin-left:auto; font-size:0.72rem; color:#cbd5e1; font-weight:600;">${isOpen ? 'klikšķini, lai sakļautu' : 'klikšķini, lai izvērstu'}</span>
            </summary>
            <div style="padding:0.5rem 1.2rem 1.2rem;">${inner}</div>
        </details>`;
    };

    // ── AUGŠĒJO CILŅU SATURA KARTE ──────────────────────────────────────────────
    // Jaunās cilnes pēc jautājuma-vārda: Tagad(q3) · Nākotne(q4) · Attiecības(q5) · Misija(placeholder)
    // 'Tagad' saturs (q3Html) PĀRCELTS uz 'Nākotne' (t4); cilne t3 pārsaukta par 'Psiholoģija'.
    // STRUKTŪRA (2026-07-10 pēc lietotāja lūguma): cilnes augšā paliek izvērsti tikai
    // Psiholoģiskā kopaina + 4 speciālistu konsīlijs; VISS AIZ konsīlija (memorands, radars,
    // Arudha, motivācija, klupšanas akmeņi, viduspunkti, ceļvedis, dziļā analīze, misija) ir
    // MINIMIZĒTS sakļautos ⌄ blokos, jo detalizētie paneļi dublē kopainas kopējo apskatu.
    // Enkuru id (t3-memo, t3-radars, …) dzīvo uz pašiem <details> elementiem — kopainas
    // flīzes (__psyFocus atver closest('details')) un memoranda ceļveža pogas tos izvērš.
    const psihologijaPane = `
        <div style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderPsychOverviewMap(profile), 'Psiholoģiskā kopaina')}
            ${cfb('multi-system')}
        </div>
        <div id="t3-specialisti" style="max-width:1286px; margin:1.5rem auto 0;">
            ${safe(() => renderSpecialistPanel(profile), 'Speciālista ieteikums')}
            ${cfb('multi-system')}
        </div>
        <div style="max-width:1286px; margin:1.5rem auto 2rem;">
            ${collapsible('🎯 Vadītāja memorands — personības lietošanas instrukcija',
                safe(() => renderInvestorMemoPanel(profile), 'Vadītāja memorands') + cfb('multi-system'),
                ' id="t3-memo"')}
            ${collapsible('📡 Sistēmu saskaņas radars',
                safe(() => renderContradictionRadar(profile), 'Sistēmu saskaņas radars') + cfb('multi-system'),
                ' id="t3-radars"')}
            ${collapsible('👁️ Arudha Lagna — iekšējais Es pret publisko tēlu',
                safe(() => renderArudhaCard(profile), 'Arudha Lagna') + cfb('ascendant'),
                ' id="t3-arudha"')}
            ${collapsible('🕹️ Motivācija un vadības sviras',
                safe(() => renderTabMotivacija(profile), 'Motivācija') + cfb('multi-system'),
                ' id="t3-motivacija"')}
            ${collapsible('🌑 Klupšanas akmeņi',
                safe(() => renderShadowCard(profile), 'Klupšanas akmeņi') + cfb('mixed-sign-house', { planet: 'Rahu' }),
                ' id="t3-klupsana"')}
            ${collapsible('📐 Viduspunkti — krīzes uzvedība',
                safe(() => renderMidpointsCard(profile), 'Viduspunkti') + cfb('date-fixed'),
                ' id="t3-viduspunkti"')}
            ${collapsible('🧭 Komunikācijas ceļvedis — kā ar šo cilvēku sadarboties',
                safe(() => renderTabInfluence(profile), 'Ietekmēšanas metodes') + cfb('date-fixed'),
                ' id="t3-celvedis"')}
            ${collapsible('🔬 Dziļā Analīze — Premium audita matrica',
                safe(() => renderTabExperiment(profile, { sections: ['1', '2', '3', '4'] }), 'Dziļā Analīze'),
                ' id="t3-dzila"')}
            ${collapsible('🩺 Misija — dharma, psihosomatika un praktiskie scenāriji', `
                <div style="background:linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border:1px solid #ddd6fe; border-left:5px solid #7c3aed; border-radius:16px; padding:2rem 2.4rem; margin:0.8rem 0 1.5rem; position:relative; overflow:hidden; box-shadow:0 2px 10px rgba(124,58,237,0.08);">
                    <div style="position:absolute; top:-30px; right:-10px; font-size:6rem; opacity:0.07; pointer-events:none;">🧭</div>
                    <div style="font-size:0.7rem; font-weight:800; color:#6d28d9; text-transform:uppercase; letter-spacing:3px; margin-bottom:0.5rem;">Kāpēc</div>
                    <h2 style="margin:0 0 0.4rem 0; font-size:1.6rem; font-weight:900; color:#1e293b; letter-spacing:-0.5px;">Misija</h2>
                    <p style="margin:0; color:#475569; font-size:0.92rem; line-height:1.6; max-width:700px;">
                        Dzīves mērķis un enerģija (dharma), psihosomatika un izdegšanas audits, un praktiskie scenāriji — kāpēc šī persona dara to, ko dara.
                    </p>
                </div>
                ${safe(() => renderTabExperiment(profile, { sections: ['5', '6', '7'], showExec: false, showHeader: false }), 'Misija')}
                ${cfb('multi-system')}`,
                ' id="t3-misija"')}
        </div>`;
    // ── 'PROGNOZE' (t4) — sakārtots pēc laika horizonta: FINEST (stundas) augšā → BROADEST (120 g.) lejā ──
    // Saliek no atsevišķiem gabaliem: sky_chart (karte-root), q4 (7 dienas + ilgtermiņš) un
    // koridors ({bios, explorer, radar, lifeCycles}). Vimshottari Dašas dublējums
    // novērsts — q4 saraksts izmests, koridora "Lielie Dzīves Cikli" (120 g.) = kanoniskā versija cilnes lejā.
    // 'Karte' saturs PĀRCELTS šeit 2026-07-06; karte-root inicializē initSkyChart (sk. switchTop t4).
    const timeUnknownT4 = !!profile?.birth_info?.isTimeUnknown;
    const prognozeTimeNote = timeUnknownT4 ? `
        <div style="background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b; border-radius:10px; padding:0.85rem 1.1rem; margin-bottom:1.2rem; font-size:0.83rem; color:#92400e; line-height:1.5;">
            ⏳ <b>Dzimšanas laiks nezināms.</b> Zīmju un tranzītu paneļi šajā cilnē (Dienas biznesa pārskats, makro fāze, muhurtas, radars) ir pilnvērtīgi. Bet paneļi, kas balstās uz dzimšanas <b>mājām un Ascendantu</b> — <b>Lielie Dzīves Cikli (Daša)</b> un <b>Gada virziens (Muntha)</b> — ir tikai orientējoši: precīzi periodu datumi prasa precīzu dzimšanas laiku.
        </div>` : '';
    // Laika brīdinājums Lielo Ciklu (Vimshottari Daša) sekcijai — pārcelts no q4 uz cilnes leju.
    const lielieCikliWarn = timeUnknownT4 ? `
        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:0.6rem 0.85rem; margin-bottom:0.8rem; font-size:0.78rem; color:#9a3412; line-height:1.45;">
            ⚠️ <b>Dzimšanas laiks nezināms.</b> Šie cikli (Vimshottari Daša) rēķinās no Mēness precīzā grāda nakšatrā, tāpēc periodu <b>robežas var nobīdīties par gadiem</b>. Secība ir ticama; konkrētie gadi — orientējoši.
        </div>` : '';

    const corr    = (corridor && !corridor.error) ? corridor : {};
    const corrErr = corridor?.error ? `<div style="color:red;padding:1rem"><b>Koridora kļūda:</b> ${corridor.error}</div>` : '';
    // Radars sakļauts → pie atvēršanas pārbūvē Leaflet karti (citādi tā inicializējas 0px izmērā).
    const radarToggle = ` ontoggle="if(this.open){setTimeout(()=>window.initAstroMap&&window.initAstroMap(new Date().toISOString().split('T')[0]),60);}"`;

    // NB: prognozeHero ("No tuvākajām stundām…") NOŅEMTS 2026-07-08 pēc lietotāja lūguma —
    // 'Dienas · biznesa pārskats' jau ir cilnes izpildošais augšgals.

    const nakotnePane = `<div style="max-width:1286px; margin:1.5rem auto 2rem;">
        <!-- 🎯 DIENAS izpildošais panelis — ŠODIENAS biznesa pārskats (pašā augšā) -->
        ${dayBizHtml}
        ${cfb('transit', { limits: 'Dienas pārskats apvieno šodienas tranzītus, Mēness jaudu (Tara Bala) un muhurtas — rezultāts mainās katru dienu. Muhurtas un saullēkts rēķinās pēc šībrīža lokācijas, tāpēc tai jābūt pareizai; dzimšanas laiks ietekmē tikai Mēness jaudas personalizāciju.' })}
        ${prognozeTimeNote}
        ${corrErr}

        <!-- ⏱ Šodiena un tuvākais laiks (Klasiskais Ritenis) — MINIMIZĒTS (sakļauts) 2026-07-08.
             sky_chart nelasa konteinera izmērus (tīrs viewBox SVG), tāpēc sakļautā <details>
             renderējas korekti bez re-init (atšķirībā no Leaflet radara). -->
        ${collapsible('⏱️ Šodiena un tuvākais laiks — klasiskais astroloģiskais ritenis', '<div id="karte-root" class="karte-root"></div>' + cfb('transit'))}

        <!-- NB: '📅 Nedēļa · 7 dienas' un '📆 Mēnesis · 38 dienu emocionālais kalendārs' NOŅEMTI
             2026-07-08 — dublējās ar 'Dienas · biznesa pārskatu' cilnes augšā. -->

        <!-- 🔬 Detalizētais laika koridors — IZVĒRSTS pēc noklusējuma (2. Gada Virziens + 3. BIORITMS) 2026-07-08 -->
        ${collapsible('Detalizētais laika koridors — gads → mēness → diena → muhurta', (corr.explorer || '') + cfb('transit'), ' open')}
        ${collapsible('Pasaules astroloģiskais radars (globālais mērogs)', (corr.radar || '') + cfb('transit'), radarToggle)}

        <!-- 🔮 BROADEST: Lielie Dzīves Cikli (120 gadi) -->
        ${horizonBar('🔮', 'Lielie Dzīves Cikli (120 gadi)', 'plašākais dzīves pārskats')}
        ${lielieCikliWarn}
        <div class="panel" style="grid-column:1/-1;">${corr.lifeCycles || ''}</div>
        ${cfb('moon')}

        <!-- 🧬 Nakšatras BIOS — MINIMIZĒTS + cilnes beigās 2026-07-08 (tīrs HTML/SVG, drošs sakļaut) -->
        ${collapsible('🧬 Nakšatras BIOS — mūža pamatprogramma', (corr.bios || '') + cfb('moon'))}

        <!-- 🔭 Gadu desmiti (makro fāze + veiksmes pīlāri) — MINIMIZĒTS + cilnes beigās 2026-07-08 -->
        ${collapsible('🔭 Gadu desmiti — makro fāze un BaZi veiksmes pīlāri', `${longTermHtml}${cfb('moon')}`)}
    </div>`;
    const attiecibasPane = `<div style="max-width:1286px; margin:1.5rem auto 2rem;">${q5Html}${cfb('multi-system')}</div>`;
    // 'Misija' (t6) saturs (hero + renderTabExperiment s5-7) PĀRCELTS uz 'Psiholoģija' (t3) 2026-06-08; cilne paliek tukša.
    // 'Ieskati' (t7) un 'Kalibrācija' cilnes NOŅEMTAS 2026-07-08 pēc lietotāja lūguma.

    window._topTabContent = {
        'profils':     profilsPane,
        't2':          karjeraPane,
        't3':          psihologijaPane,
        't4':          nakotnePane,
        't5':          attiecibasPane,
        // 'Atvaļinājums' — Atvaļinājumu karte + TOP galamērķi (pārcelts no 'Karte' 2026-07-05)
        'atvalinajums': `<div id="astro-locator-root"></div>
            <div style="max-width:1286px; margin:0.5rem auto 2rem;">${cfb('ascendant', { limits: 'Kartes personīgais slānis balstās relokācijas leņķos (ASC/MC), kas prasa precīzu dzimšanas laiku. Bez laika tas rāda 24 stundu vidējo (vērtības atzīmētas ar ≈) — virziens paliek informatīvs, bet izlīdzināts. Vispārīgais slānis apraksta tikai šodienas debess fonu un nav personalizēts.' })}</div>`,
        // 't6' ('Karte') PĀRCELTS uz 't4' ('Prognoze') 2026-07-06 — karte-root tagad dzīvo nakotnePane.
    };

    dash.innerHTML = `
        <!-- AUGŠĒJĀ CILŅU JOSLA -->
        <div id="top-nav" class="top-nav" style="grid-column: 1 / -1;">
            <button class="top-nav-btn top-nav-btn--active" data-toptab="profils">Profils</button>
            <button class="top-nav-btn" data-toptab="t2">Karjera</button>
            <button class="top-nav-btn" data-toptab="t3">Psiholoģija</button>
            <button class="top-nav-btn" data-toptab="t4">Prognoze</button>
            <button class="top-nav-btn" data-toptab="t5">Attiecības</button>
            <button class="top-nav-btn" data-toptab="atvalinajums">Atvaļinājums</button>
        </div>

        <!-- AUGŠĒJO CILŅU DISPLEJA ZONA -->
        <div id="top-nav-display" style="grid-column: 1 / -1; width: 100%;">
            ${profilsPane}
        </div>`;

    console.log('[RENDER] dash.innerHTML SET');

    setupTopTabs();
    console.log('[RENDER] setupTopTabs() izsaukts');
    // NB: partnera lauku init (autocomplete + UTC padoms) notiek switchTop() pie t5,
    // jo Attiecību saturs nonāk DOM tikai pēc cilnes atvēršanas.
}

// ── AUGŠĒJO CILŅU NAVIGĀCIJA (Profils · Karjera · Psiholoģija · Prognoze · Attiecības · Atvaļinājums) ──
// Saturs glabājas window._topTabContent; pārslēdzot — tiešā innerHTML nomaiņa.
function setupTopTabs() {
    const btns = Array.from(document.querySelectorAll('.top-nav-btn'));
    const display = document.getElementById('top-nav-display');
    if (!btns.length || !display) { console.warn('[TOPNAV] nav pogu vai displeja zonas'); return; }

    function switchTop(key) {
        const content = window._topTabContent?.[key];
        if (content == null) { console.error('[TOPNAV] nav satura:', key); return; }

        display.innerHTML = content;
        btns.forEach(b => b.classList.toggle('top-nav-btn--active', b.dataset.toptab === key));

        // 'Prognoze' (t4) — pēc satura ievietošanas DOM inicializē astroloģisko riteni +
        // biznesa kalendāru (sky_chart.js, augšā "Šodiena un tuvākais laiks"). Pasaules radars
        // inicializējas SLINKI, kad lietotājs izvērš tā sakļauto ⌄ bloku (sk. radarToggle nakotnePane).
        if (key === 't4') {
            setTimeout(() => {
                try { window.initSkyChart && window.initSkyChart(window.currentProfile1); }
                catch (e) { console.error('[TOPNAV] initSkyChart kļūda:', e); }
            }, 60);
        }

        // 'Atvaļinājums' — lokatora karte (Leaflet) jāinicializē pēc satura ievietošanas DOM
        if (key === 'atvalinajums') {
            setTimeout(() => {
                try { window.initAstroLocator && window.initAstroLocator(window.currentProfile1); }
                catch (e) { console.error('[TOPNAV] initAstroLocator kļūda:', e); }
            }, 60);
        }

        // 'Attiecības' — partnera lauku init (autocomplete + UTC padoms); saturs DOM tikai tagad.
        // (Agrāk to darīja dzēstā tabs.js, kad cilne tika atvērta.)
        if (key === 't5') {
            setTimeout(() => {
                try {
                    const li2 = document.getElementById('ui-location-2');
                    const ld2 = document.getElementById('autocomplete-list-2');
                    if (li2 && ld2) import('./autocomplete.js?v=9').then(m => m.setupAutocomplete(li2, ld2));
                    if (window._setupPartnerUtcHint) window._setupPartnerUtcHint();
                } catch (e) { console.error('[TOPNAV] partnera init kļūda:', e); }
            }, 60);
        }

        setTimeout(() => display.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    }

    btns.forEach(btn => btn.addEventListener('click', () => switchTop(btn.dataset.toptab)));
    console.log('[TOPNAV] setupTopTabs() gatavs, pogas:', btns.length);
}

// ── Mega-tabu navigācija no citiem elementiem (piem. vizuālā šāviena CTA) ────
window._goToMegaTab = function(key) {
    const btn = document.querySelector(`.q-tab-btn[data-mtab="${key}"]`);
    if (btn) btn.click();
    document.getElementById('mega-tab-container')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
