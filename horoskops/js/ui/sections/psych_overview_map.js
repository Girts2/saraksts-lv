// 🧠 Psiholoģiskā kopaina — t3 'Psiholoģija' cilnes satura rādītājs (2026-06-16).
// Viens augšējais "logs", kas savāc visas cilnes sadaļas vienā loģiskā kartē: katrs
// bloks ir sava SVG glifa + tēmas reprezentācija, sagrupēta 5 psiholoģiskos klasteros
// (kodols · dzinējs · darbība · ēna · saites/sintēze). Klikšķis uz bloka → window.__s1Focus
// aizritina un izceļ attiecīgo padziļināto paneli zemāk (tie paši enkuri, ko lieto mandalas).
// Katrs bloks rāda īsu, no DATIEM atvasinātu personas īpašību (secinājumu), lai lasītājs
// saprastu psiholoģiju, neatverot izvērstos paneļus. Loģika netiek dublēta — atkārtoti
// izmantotas tās pašas funkcijas/lauki, ko paneļi (buildInvestorMemo, computeArudhaLagna,
// dharma/enkuri/piesaiste/psihosomatika u.c.). Fail-safe: ja dati trūkst, bloks rāda
// aprakstošo tēmu, NEVIS fabricētu vērtību (sk. [[audit-t3-fail-confident]]).
import { buildInvestorMemo } from '../../logic/investor_memo.js?v=11';
import { computeArudhaLagna } from '../../logic/hidden_insights.js?v=10';
import { DRIVERS, pickDriverKey, WARN_ARCHETYPES, keyTension, deriveDIV } from './hacker_panel.js?v=7';
import { DERAILERS, MIDPOINT_DICT } from '../tabs/tab_y_insights.js?v=32';
import { computeMaskSynthesis, elementPersonaText } from '../../logic/mask_synthesis.js?v=6';
import { buildInfluenceReport } from '../../logic/influence.js?v=8';

// ── ĶELTU KOKU KALKULATORS (kopēts no tab_experiment.js) ───────────────────
// export: lieto arī logic/specialist_review.js (Ēnas arhetips + bioritma sadaļa).
export function getCelticTree(dateStr) {
    if (!dateStr) return null;
    const parts = dateStr.split('-');
    const month = parseInt(parts[1], 10);
    const day   = parseInt(parts[2], 10);
    const md    = month * 100 + day;

    const trees = [
        { from: 1224, to: 1231, name: 'Bērzs',      en: 'Birch',     type: 'Sasniedzējs',    color: '#15803d',
          traits: 'Ambiciozi, drosmīgi, izturīgi un mērķtiecīgi. Dabiski aug jebkādos apstākļos un uzņemas līdera lomu. Bērza mēness ir ideāls laiks jaunu izaicinājumu sākšanai.',
          shadow: 'Sasniegumi kļūst par identitāti — neveiksme tiek uztverta kā pašvērtīguma sabrukums. Nespēja apstāties un atpūsties ved uz hronisko izdegšanu.' },
        { from:  101, to:  120, name: 'Bērzs',      en: 'Birch',     type: 'Sasniedzējs',    color: '#15803d',
          traits: 'Ambiciozi, drosmīgi, izturīgi un mērķtiecīgi. Dabiski aug jebkādos apstākļos un uzņemas līdera lomu. Bērza mēness ir ideāls laiks jaunu izaicinājumu sākšanai.',
          shadow: 'Sasniegumi kļūst par identitāti — neveiksme tiek uztverta kā pašvērtīguma sabrukums. Nespēja apstāties un atpūsties ved uz hronisko izdegšanu.' },
        { from:  121, to:  217, name: 'Pīlādzis',   en: 'Rowan',     type: 'Domātājs',       color: '#4f46e5',
          traits: 'Dziļi domātāji ar augstu intelektu un lielu ietekmi uz apkārtējiem. Pīlādzis simbolizē izteiksmi, aizsardzību un filozofiju.',
          shadow: 'Intelektuāls augstprātīgums un analīzes paralīze. Citu idejas tiek noraidītas kā nepietiekami dziļas, savukārt paša domas paliek teorijā.' },
        { from:  218, to:  317, name: 'Osis',        en: 'Ash',       type: 'Vizionārs',      color: '#7c3aed',
          traits: 'Vīzionāri un radoši gari, kas piesaistīti mākslai un dabai. Viņi dzīvo starp divām pasaulēm — sapni un realitāti.',
          shadow: 'Eskapisms un nespēja piezemēties. Skaistie sapņi kalpo kā bēgšana no ikdienas atbildības un piezemētu lēmumu pieņemšanas.' },
        { from:  318, to:  414, name: 'Alksnis',     en: 'Alder',     type: 'Ceļš rādītājs',  color: '#b91c1c',
          traits: 'Drosmīgi, pārliecinoši un entuziastiski vadītāji. Alksnis ir pirmais, kas uzdrošinās doties pa jaunu ceļu, un viņi rāda to citiem.',
          shadow: 'Neapdomāts risks un nespēja pieņemt citu brīdinājumus. Vadīšana bez iesakņošanās var novest pie komandas izdegšanas.' },
        { from:  415, to:  512, name: 'Vītols',      en: 'Willow',    type: 'Novērotājs',     color: '#0369a1',
          traits: 'Intuitīvi, rūpīgi un cieši saistīti ar Mēness enerģiju. Vītoli uzkrāj dziļu emocionālo atmiņu un redz to, ko citi neievēro.',
          shadow: 'Pasivitāte un apvainošanās uzkrāšana. Iekšējā sāpe tiek glabāta, nevis izteikta — ilglaicīgi var izpausties kā netieša ietekmēšana caur vainas sajūtu.' },
        { from:  513, to:  609, name: 'Vilkābele',   en: 'Hawthorn',  type: 'Iluzionists',    color: '#b45309',
          traits: 'Bieži pārprasti, jo ārējais neatspoguļo iekšējo. Ļoti radoši un pieradināti pārmaiņām — aiz rāmā izskata slēpjas uguns.',
          shadow: 'Pastāvīga maskēšanās — pat tuvākajiem netiek atklāts patiesais Es. Bailes no ievainojamības veido dziļu emocionālu izolāciju.' },
        { from:  610, to:  707, name: 'Ozols',       en: 'Oak',       type: 'Aizstāvis',      color: '#854d0e',
          traits: 'Spēcīgi, droši un augstsirdīgi. Ozoli ir dabiskās tradīcijas sargātāji un tie, pie kuriem citi meklē atbalstu krīzes brīžos.',
          shadow: 'Neelastīga dogmatika un pretestība pārmaiņām. "Tas vienmēr tā bijis" kā aizsardzība pret nezināmā draudu — slogs kļūst par identitāti.' },
        { from:  708, to:  804, name: 'Briedoga',    en: 'Holly',     type: 'Dižciltīgais',   color: '#b91c1c',
          traits: 'Ambiciozi, izteiksmīgi un ar dabisko majestātiskumu. Briedogas uzvar pārbaudījumos un bieži ieņem vadošās pozīcijas.',
          shadow: 'Augstprātība un pārmērīga konkurēšana. Statusa un uzvaras kāre var smagi kaitēt tuvākajām attiecībām.' },
        { from:  805, to:  901, name: 'Lazda',       en: 'Hazel',     type: 'Analītiķis',     color: '#64748b',
          traits: 'Izslāpuši pēc zināšanām un informācijas. Izcili analītiķi un perfekcionisti, kuri saprot sistēmas un modeļus ātrāk par citiem.',
          shadow: 'Obsesīva informācijas uzkrāšana bez pielietojuma. Perfekcionisms un nespēja pieņemt to, ko nevar izmērīt vai izskaidrot.' },
        { from:  902, to:  929, name: 'Vīnogulājs',  en: 'Vine',      type: 'Izlīdzinātājs',  color: '#7e22ce',
          traits: 'Dāsni indivīdi ar spēju redzēt visus situācijas leņķus. Mīl izsmalcinātību un harmoniju, kļūstot par sabiedrības izlīdzinātājiem.',
          shadow: 'Hronisks neizlēmīgums — redzot visus leņķus, ir grūti izvēlēties un rīkoties. Harmonijas meklēšana pārvēršas konfliktu izvairīšanā.' },
        { from:  930, to: 1027, name: 'Efeja',       en: 'Ivy',       type: 'Izdzīvotājs',    color: '#047857',
          traits: 'Sadarbīgi, optimistiski un ārkārtīgi izturīgi neatkarīgi no apstākļiem. Efeja zied arī tur, kur citi neiztur.',
          shadow: 'Atkarīga izdzīvošana uz citu enerģijas rēķina. Hronisks optimisms var kalpot kā aizsardzība pret situācijas reālu novērtēšanu.' },
        { from: 1028, to: 1124, name: 'Niedre',      en: 'Reed',      type: 'Izaicinātājs',   color: '#1d4ed8',
          traits: 'Pētnieki un stratēģi ar viltīgu prātu. Niedre atklāj patiesību jebkuros apstākļos un neuzdod tikai ērtus jautājumus.',
          shadow: 'Ietekmēšana un kontrole — patiesības meklēšana var pārvērsties citu ievainošanā. Stratēģiskā domāšana bez empātijas.' },
        { from: 1125, to: 1222, name: 'Plūškoks',    en: 'Elder',     type: 'Zinātnieks',     color: '#64748b',
          traits: 'Brīvdomīgi, erudīti zinātnieki ar stingru personīgo viedokli un augstu noturību pret grūtībām. Bieži ir tie, kas redz to, ko laikmets vēl nav gatavs pieņemt.',
          shadow: 'Cinisms un intelektuāla izolācija. Pārāk daudz redzēts, lai kaut kam ticētu — zināšanas kalpo kā barjera, nevis kā tilts uz cilvēkiem.' },
    ];

    // Dec 23 — Nameless Day
    if (md === 1223) {
        return { name: 'Bez Vārda Diena', en: 'Nameless Day', type: 'Pārejas cilvēks', color: '#475569',
                 traits: 'Dzimuši Saulgriežu pārejas mirklī — īpaša kosmiskā sliekšņa zīme. Pieder gan vecajam, gan jaunajam ciklam.',
                 shadow: 'Identitātes nestabilitāte — piederot abiem cikliem, var rasties nepiederēšanas sajūta nevienam no tiem.' };
    }

    for (const t of trees) {
        if (t.from > t.to) {
            if (md >= t.from || md <= t.to) return t;
        } else {
            if (md >= t.from && md <= t.to) return t;
        }
    }
    return null;
}

const INK = {
    '#06b6d4': '#0e7490', '#0ea5e9': '#0369a1', '#38bdf8': '#0369a1', '#7dd3fc': '#0369a1',
    '#3b82f6': '#1d4ed8', '#60a5fa': '#1d4ed8', '#93c5fd': '#1d4ed8', '#2563eb': '#1d4ed8',
    '#22c55e': '#15803d', '#4ade80': '#15803d', '#86efac': '#166534',
    '#10b981': '#047857', '#34d399': '#047857', '#6ee7b7': '#047857', '#14b8a6': '#0f766e',
    '#84cc16': '#4d7c0f', '#a3e635': '#4d7c0f', '#eab308': '#a16207',
    '#f59e0b': '#b45309', '#fbbf24': '#b45309', '#fcd34d': '#b45309',
    '#f97316': '#c2410c', '#fb923c': '#c2410c', '#fdba74': '#c2410c',
    '#dc2626': '#b91c1c', '#ef4444': '#b91c1c', '#f87171': '#b91c1c', '#fca5a5': '#b91c1c',
    '#ec4899': '#be185d', '#f472b6': '#be185d', '#f9a8d4': '#be185d',
    '#a855f7': '#7e22ce', '#c084fc': '#7e22ce', '#8b5cf6': '#7c3aed',
    '#a78bfa': '#6d28d9', '#6366f1': '#4f46e5', '#a5b4fc': '#4f46e5',
    '#94a3b8': '#64748b', '#cbd5e1': '#64748b', '#e2e8f0': '#475569',
};
const ink = (c) => INK[(c || '').toLowerCase()] || c;

// ── JUNGA PSIHES KARTES SVG APRAKSTS ────────────────────────────────────────
function buildJungMandalaSVG(profile) {
    const wp   = profile?.western?.psychology  || {};
    const hyb  = profile?.hybrid_intelligence  || {};
    const tr   = profile?.western?.transits    || [];
    const progMoon   = profile?.progressions?.moon_prog || '—';
    const birthDate  = profile?.birth_info?.date || '';
    const celticTree = getCelticTree(birthDate);

    const vPsych = profile?.vedic?.psychology || {};

    const attachStyle = [
        wp.loveLanguage || null,
        wp.emotionalNeeds ? `<span style="color:#64748b; font-size:0.85rem;">Emocionālā drošība: </span>${wp.emotionalNeeds}` : null,
    ].filter(Boolean).join('<br>');

    const maskSynth = computeMaskSynthesis(profile);
    const jungPersona = maskSynth.applicable
        ? elementPersonaText(maskSynth.topElement, {
            note: `Bez precīza dzimšanas laika maska dota pa dominējošo stihiju (${maskSynth.topElement}), ne vienu zīmi.`,
        })
        : [
            vPsych.socialMask || null,
            wp.socialMask ? `<span style="color:#94a3b8; font-size:0.85rem;">Rietumu Ego struktūra: </span>${wp.socialMask}` : null,
        ].filter(Boolean).join('<br>');

    const moonSignIdx = profile?.vedic?.moonSignIdx ?? -1;
    const moonIsSuppressive = [5, 7, 9, 10].includes(moonSignIdx);
    const attachHasSecurity = /mājas|drošīb|nostalģij/i.test(wp.loveLanguage || '') ||
                              /mājas|drošīb|nostalģij/i.test(wp.emotionalNeeds || '');
    const contradictionNote = (moonIsSuppressive && attachHasSecurity)
        ? `Polārā pretruna: spēcīga nepieciešamība pēc drošības pret emocionālu kontroli.`
        : null;

    const jungAnima = [
        vPsych.emotionalBase,
        vPsych.animaProjection ? `Projekcija: ${vPsych.animaProjection}` : null,
        contradictionNote,
    ].filter(Boolean).join(' ');

    const jungSelf = [
        vPsych.egoStructure || null,
        vPsych.selfRealization || vPsych.heroPath || null
    ].filter(Boolean).join(' ');

    const jungShadowRahu = [
        vPsych.hiddenAmbitions || null,
        vPsych.rahuRisk ? `Risks: ${vPsych.rahuRisk}` : null
    ].filter(Boolean).join(' ');

    const jungShadowKetu = [
        vPsych.ketuTrap || null,
        vPsych.karmicFootprints || null,
        vPsych.ketuTalent ? `Resurss: ${vPsych.ketuTalent}` : null
    ].filter(Boolean).join(' ');

    const critBlocksText = Array.isArray(wp.criticalAspectsBlocks) && wp.criticalAspectsBlocks.length
        ? wp.criticalAspectsBlocks.map(c => c?.profile || '').filter(Boolean).join(' ')
        : null;

    const jungShadowLilith = [
        wp.blackmailPoints || null,
        critBlocksText || null,
        wp.innerConflicts || null,
        wp.trauma || null,
        hyb.jungVulnerability || null,
    ].filter(Boolean).join(' ');

    // Optimizēts, VIENMĒRĪGA garuma teksts: atgriež veselus teikumus līdz mērķim (BEZ '…') —
    // fiksētie vienāda izmēra mandalas bloki tiek piepildīti līdzīgi, bez aprāvuma un bez liela
    // tukšuma. Aizstāj veco clip(…115), kas grieza pa vidu (vairāk par to, kas ietilpa 4 rindās).
    const balance = (html, target = 108, hardMax = 126) => {
        const plain = String(html == null ? '' : html).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        if (plain.length <= hardMax) return plain;
        const sentences = plain.match(/[^.!?]+[.!?]+/g) || [plain];
        let out = '';
        for (const s of sentences) {
            const cand = out ? `${out} ${s.trim()}` : s.trim();
            if (out && cand.length > hardMax) break;
            out = cand;
            if (out.length >= target) break;
        }
        return (out || plain.slice(0, hardMax)).trim();
    };

    const dPersona = balance(jungPersona);
    const dAnima   = balance(jungAnima);
    const dSelf    = balance(jungSelf);
    const dRahu    = balance(jungShadowRahu);
    const dKetu    = balance(jungShadowKetu);
    const dLilita  = balance(jungShadowLilith);

    const celticAccent = celticTree ? celticTree.color : '#4d7c0f';
    const celticLabel  = celticTree ? `🌳 ${celticTree.name}` : '🌳 Ķeltu koks';
    const dCeltic      = celticTree ? balance(celticTree.traits) : 'Dzimšanas datums nav pieejams.';

    const critTr = tr.filter(t => t.nature === 'critical');
    const harmTr = tr.filter(t => t.nature === 'harmonic');
    let phaseLabel = 'Neitrāls';
    let phaseColor = '#64748b';
    let phaseExpl  = 'Konsolidācijas periods.';
    if (critTr.length > 0 && critTr.length > harmTr.length) {
        phaseLabel = 'Saspringts';
        phaseColor = '#b91c1c';
        phaseExpl  = `Saspringts fona periods.`;
    } else if (harmTr.length > 0) {
        phaseLabel = 'Atvērts';
        phaseColor = '#15803d';
        phaseExpl  = `Atvērts fona periods.`;
    }
    const dTransit = balance(`${phaseExpl} Progr. Mēness: ${progMoon}.`);

    // Mezgls = klikšķināma SVG kaste. Teksts NETIEK apgriezts ar line-clamp — `balance` jau
    // dod tādu garumu, kas ietilpst kastē (augstums 190 → ~5 rindas), tāpēc bez '…'.
    const mNode = (x, y, w, h, accent, label, gloss, desc, targetId) => `
        <g onclick="event.stopPropagation(); window.__s1Focus&&window.__s1Focus('${targetId}')" style="cursor:pointer;">
            <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="13" fill="${accent}10" stroke="${accent}55" stroke-width="1.5"/>
            <foreignObject x="${x}" y="${y}" width="${w}" height="${h}">
                <div xmlns="http://www.w3.org/1999/xhtml" style="height:100%; box-sizing:border-box; padding:10px 14px; font-family:'Outfit',sans-serif; overflow:hidden; display:flex; flex-direction:column; justify-content:center;">
                    <div style="font-size:20px; font-weight:800; color:${ink(accent)}; line-height:1.2;">${label}</div>
                    <div style="font-size:13px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.3px; margin:3px 0 5px;">${gloss}</div>
                    ${desc ? `<div style="font-size:15px; color:#334155; line-height:1.35;">${desc}</div>` : ''}
                </div>
            </foreignObject>
        </g>`;

    // Junga psihes karte — blokshēma, kas rāda psiholoģisko procesu loģisko secību pa
    // apzinātā ↔ neapzinātā asi: Persona (augšā) → Anima · Patība · Ķeltu koks (vidū) →
    // Ēna → Rahu · Ketu · Lilita (apakšā) → pašreizējā fāze. Savienojuma līnijas = plūsma.
    return `
    <svg viewBox="0 0 780 940" width="100%" style="font-family:'Outfit',sans-serif; display:block; margin:0 auto;">
        <!-- Jung ass (Persona↔Patība↔Ēna) un Anima/Ķeltu — savienojuma līnijas (plūsma) -->
        <line x1="390" y1="226" x2="390" y2="256" stroke="#cbd5e1" stroke-width="2"/>
        <line x1="248" y1="351" x2="274" y2="351" stroke="#cbd5e1" stroke-width="2"/>
        <line x1="506" y1="351" x2="532" y2="351" stroke="#cbd5e1" stroke-width="2"/>
        <line x1="390" y1="446" x2="390" y2="478" stroke="#cbd5e1" stroke-width="2"/>
        <line x1="390" y1="548" x2="132" y2="574" stroke="#cbd5e1" stroke-width="2"/>
        <line x1="390" y1="548" x2="390" y2="574" stroke="#cbd5e1" stroke-width="2"/>
        <line x1="390" y1="548" x2="648" y2="574" stroke="#cbd5e1" stroke-width="2"/>
        <text x="390" y="22" text-anchor="middle" font-size="15" fill="#94a3b8" font-weight="700">↑ APZINĀTAIS — SOCIĀLI REDZAMAIS</text>
        <text x="390" y="790" text-anchor="middle" font-size="15" fill="#94a3b8" font-weight="700">↓ NEAPZINĀTAIS — APSPIESTAIS, INSTINKTI</text>
        ${mNode(274, 36, 232, 190, '#1d4ed8', '🎭 Persona', 'Kā mani redz citi', dPersona, 's1-persona')}
        ${mNode(16, 256, 232, 190, '#7e22ce', '🌙 Anima / Animus', 'Iekšējā jūtu pasaule', dAnima, 's1-anima')}
        ${mNode(274, 256, 232, 190, '#b45309', '☀ Patība', 'Apzinātais Es · centrs', dSelf, 's1-self')}
        ${mNode(532, 256, 232, 190, celticAccent, celticLabel, 'Dabiskais raksturs · ķeltu koks', dCeltic, 's1-celtic')}
        ${mNode(288, 478, 204, 70, '#b91c1c', '🌑 Ēna', 'Noliegtā puse', '', 's1-rahu')}
        ${mNode(16, 574, 232, 190, '#b91c1c', '◉ Rahu', 'slēptais dzinulis', dRahu, 's1-rahu')}
        ${mNode(274, 574, 232, 190, '#64748b', '● Ketu', 'aklās zonas', dKetu, 's1-ketu')}
        ${mNode(532, 574, 232, 190, '#6d28d9', '✦ Lilita', 'pirmatnējās dziņas', dLilita, 's1-lilita')}
        ${mNode(16, 806, 748, 118, phaseColor, `🌀 Pašreizējā fāze: ${phaseLabel}`, 'Kur cilvēks ir tagad · Rietumu tranzīti', dTransit, 's1-transit')}
    </svg>`;
}

// Mazie SVG glifi (24×24, līniju stils) — katrai sadaļai savs motīvs.
const G = (inner, color) => `<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="${color}" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${inner}</svg>`;
const GLYPH = {
    mask:       (c) => G(`<circle cx="9" cy="12" r="5.5"/><circle cx="15" cy="12" r="5.5"/>`, c),
    quaternity: (c) => G(`<circle cx="12" cy="12" r="8"/><path d="M12 4v16M4 12h16"/>`, c),
    triangle:   (c) => G(`<path d="M12 4 20 19 4 19Z"/><circle cx="12" cy="14.5" r="1.4" fill="${c}" stroke="none"/>`, c),
    compass:    (c) => G(`<circle cx="12" cy="12" r="8"/><path d="M12 7.5 14 12 12 16.5 10 12Z" fill="${c}" stroke="none"/>`, c),
    bars:       (c) => G(`<rect x="4" y="12" width="3.6" height="7" rx="1" fill="${c}" stroke="none"/><rect x="10.2" y="6" width="3.6" height="13" rx="1" fill="${c}" stroke="none"/><rect x="16.4" y="14" width="3.6" height="5" rx="1" fill="${c}" stroke="none"/>`, c),
    timeline:   (c) => G(`<path d="M3 12h18"/><circle cx="6" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="18" cy="12" r="2"/>`, c),
    chat:       (c) => G(`<path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H9l-4 4z"/>`, c),
    moon:       (c) => G(`<path d="M16 4a8 8 0 1 0 4 11A7 7 0 0 1 16 4z"/>`, c),
    bolt:       (c) => G(`<path d="M13 2 4 14h6l-1 8 9-12h-6z"/>`, c),
    battery:    (c) => G(`<rect x="3" y="8" width="15" height="9" rx="2"/><path d="M20.5 11v3"/><rect x="5" y="10" width="6" height="5" rx="0.5" fill="${c}" stroke="none"/>`, c),
    link:       (c) => G(`<rect x="2.5" y="9" width="11" height="6" rx="3"/><rect x="10.5" y="9" width="11" height="6" rx="3"/>`, c),
    radar:      (c) => G(`<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1.3" fill="${c}" stroke="none"/>`, c),
    target:     (c) => G(`<circle cx="12" cy="12" r="7"/><path d="M12 2v3.5M12 18.5V22M2 12h3.5M18.5 12H22"/><circle cx="12" cy="12" r="2" fill="${c}" stroke="none"/>`, c),
};

// ─────────────────────────────────────────────────────────────────────────────
// HTML TEKSTA-BLOKU KARKASS — aizstāj fiksētos SVG foreignObject (kas aprāva tekstu).
// Auto-augstums → teksts vienmēr ietilpst: bez aprāvuma, pārklāšanās vai tukšuma.
// VIENOTS fonts visiem blokiem (bāze = 'Junga psihes karte' bloks): klases .psy-*.
// ─────────────────────────────────────────────────────────────────────────────
const psyCols = (cols, n = 3) =>
    `<div class="psy-cols psy-cols-${n}">${cols.join('')}</div>`;
// Viena kolonna ar augšējo sistēmas etiķeti; izvēles fokusa-klikšķis.
const psyCol = (label, inner, focusId) =>
    `<div class="psy-col"${focusId ? ` onclick="event.stopPropagation(); window.__s1Focus&&window.__s1Focus('${focusId}')" style="cursor:pointer;"` : ''}>`
    + (label ? `<div class="psy-collabel">${label}</div>` : '') + inner + `</div>`;
// Krāsaina identitātes virsraksta kaste (nosaukums, izvēles %, apakšteksts).
const psyChip = (accent, title, sub, score) =>
    `<div class="psy-chip" style="--c:${accent}; background:${accent}15;">`
    + `<div class="psy-chip-row"><span class="psy-chip-title">${title}</span>`
    + `${(score != null && score !== '') ? `<span class="psy-chip-score">${score}%</span>` : ''}</div>`
    + `${sub ? `<div class="psy-chip-sub">${sub}</div>` : ''}</div>`;
// Ievadrindkopa (slīpraksts) — konteksts blokam.
const psyLede = (txt) => `<div class="psy-lede">${txt}</div>`;
// Piezīme ar treknu etiķeti; ar `accent` → krāsaina kreisā mala.
const psyNote = (label, body, accent) =>
    `<div class="psy-note${accent ? ' psy-note-box' : ''}"${accent ? ` style="border-left-color:${accent};"` : ''}>`
    + `${label ? `<b>${label}</b> ` : ''}${body}</div>`;
// Horizontāls josla (etiķete + % + krāsa) — aizstāj SVG bar.
const psyBar = (label, pct, color) =>
    `<div class="psy-bar"><div class="psy-bar-top"><span>${label}</span>`
    + `<span class="psy-bar-pct" style="color:${color};">${Math.round(pct)}%</span></div>`
    + `<div class="psy-bar-track"><div class="psy-bar-fill" style="width:${Math.max(0, Math.min(100, pct))}%; background:${color};"></div></div></div>`;
// Iekrāsota satura kaste (gaiša fona variants) ar treknu virsrakstu.
const psyBox = (accent, title, body) =>
    `<div class="psy-box" style="--c:${accent}; background:${accent}0d; border-color:${accent}40;">`
    + `${title ? `<div class="psy-box-title" style="color:${accent};">${title}</div>` : ''}${body}</div>`;

// Loģiskā struktūra — 5 klasteri, kas kopā veido vienu psiholoģisko ainu.
// Flīžu secība veidota tā, lai 3 kolonnu gridā katrs klasteris aizpilda pilnu rindu:
// Klasteris 2 tiles → [d2-t1] + [d1-t1] = 3 kolonnas.
// Klasteris 3 tiles → [d2-t1] + [d1-t1] + [d2-t1] + ... (ar dense flow aizpilda)
const CLUSTERS = [
    { label: 'Kas viņš ir · kodols', color: '#6d28d9', tiles: [
        { id: 's1-persona',              g: 'quaternity', title: 'Junga psihes karte',   essence: 'K. G. Junga 4 arhetipi uz apzinātā ↔ neapzinātā ass' },
        { id: 't3-arudha',               g: 'mask',       title: 'Iekšējais Es & tēls',  essence: 'Kāds viņš ir iekšēji pret to, kā viņu redz citi' },
    ]},
    { label: 'Kas viņu dzen · dzinējs', color: '#b45309', tiles: [
        { id: 't3-motivacija',           g: 'triangle',   title: 'Motivācija & sviras',  essence: 'D/I/V — kas viņu kustina un kā ar viņu strādāt' },
        { id: 'existential-dharma',      g: 'compass',    title: 'Dzīves misija',        essence: 'Dharma — kāpēc viņš dara to, ko dara' },
    ]},
    { label: 'Kā viņš strādā · darbība', color: '#0369a1', tiles: [
        { id: 'timing-pillars',          g: 'timeline',   title: 'Stratēģiskais laiks',  essence: 'Dzīves fāzes un veiksmes logi' },
        { id: 'capacity-anchors',        g: 'bars',       title: 'Karjeras kapacitāte',  essence: 'Šeina enkuri un lēmumu pieņemšanas stils' },
        { id: 't3-celvedis',             g: 'chat',       title: 'Komunikācija',         essence: 'Kā viņu pārliecināt un veidot uzticību' },
    ]},
    { label: 'Riski & spiediens · ēna', color: '#b91c1c', tiles: [
        { id: 't3-klupsana',             g: 'moon',       title: 'Klupšanas akmeņi',     essence: 'Kā stiprās puses zem spiediena kļūst par riskiem' },
        { id: 'burnout-stress',          g: 'battery',    title: 'Psihosomatika',        essence: 'Kur ķermenis uzkrāj stresu un izdegšanu' },
        { id: 't3-viduspunkti',          g: 'bolt',       title: 'Krīzes uzvedība',      essence: 'Kā viņš reaģē spiedienā un krīzē' },
    ]},
    { label: 'Saites & sintēze', color: '#15803d', tiles: [
        { id: 't3-memo',                 g: 'target',     title: 'Kopvērtējums',         essence: 'Vadītāja kopsavilkums: verdikti, sviras, riski' },
        { id: 't3-radars',               g: 'radar',      title: 'Sistēmu saskaņa',      essence: 'Kur sistēmas vienojas un kur nesaskan' },
        { id: 'relationship-attachment', g: 'link',       title: 'Attiecību dinamika',   essence: 'Piesaiste, konfliktu paterni un sinerģija' },
    ]},
    // Pievienots pēdējais → masonry balansētājs to novieto īsākajā (labajā) kolonnā, aizpildot
    // brīvo laukumu blakus 'Komunikācija'. Saturs: papildu kapacitātes sadaļa (sk. buildDecisionDesignSVG).
    { label: 'Kā pieņem lēmumus · prakse', color: '#1d4ed8', tiles: [
        { id: 'capacity-decisions',      g: 'compass',    title: 'Lēmumu dizains',       essence: 'Kognitīvais stils un kā šim profilam pieņemt lēmumus' },
    ]},
];

// Zodiaka zīmes pēc grādiem — lieto buildMidpointsSVG viduspunktu zīmes aprēķinā.
const SIGNS_LV = ['Auns','Vērsis','Dvīņi','Vēzis','Lauva','Jaunava','Svari','Skorpions','Strēlnieks','Mežāzis','Ūdensvīrs','Zivis'];
function buildCapacitySVG(profile) {
    const careerAnchors = profile?.careerAnchors || null;
    if (!careerAnchors) return '';

    const anchors = careerAnchors.allAnchors || [];
    const top1 = anchors[0] || { lv: 'Nav enkura', score: 0, color: '#64748b', icon: '⚓', description: '', thrives: '', risks: '' };
    const top2 = anchors[1] || { lv: 'Nav enkura', score: 0, color: '#64748b', icon: '⚓' };
    const top3 = anchors[2] || { lv: 'Nav enkura', score: 0, color: '#64748b', icon: '⚓' };

    const s1 = careerAnchors.kahneman?.s1pct || 50;
    const s2 = careerAnchors.kahneman?.s2pct || 50;
    const domK = careerAnchors.kahneman?.dominantMeta?.lv || 'Neitrāls';
    const domColor = careerAnchors.kahneman?.dominant === 's1' ? '#f59e0b' : careerAnchors.kahneman?.dominant === 's2' ? '#3b82f6' : '#8b5cf6';

    const cy = careerAnchors.cynefin || {};
    const cyPrimary = cy.primary || 'complex';
    const cyMeta = cy.meta || {};
    const cyComplex = cyMeta.complex || { lv: 'Komplicēts', color: '#8b5cf6', icon: '🌀' };
    const cyComplicated = cyMeta.complicated || { lv: 'Sarežģīts', color: '#3b82f6', icon: '🧮' };
    const cyChaotic = cyMeta.chaotic || { lv: 'Haotisks', color: '#ef4444', icon: '⚡' };
    const cySimple = cyMeta.simple || { lv: 'Vienkāršs', color: '#10b981', icon: '⚙️' };
    const cyScores = cy.profile || { complex: 25, complicated: 25, chaotic: 25, simple: 25 };

    const b = careerAnchors.boundary || { lv: '—', score: 50, color: '#64748b' };
    const riskVal = Math.max(0, Math.min(100, 100 - b.score));
    const band = riskVal >= 70 ? { lv: 'Augsts', color: '#b91c1c' }
               : riskVal >= 55 ? { lv: 'Paaugstināts', color: '#dc2626' }
               : riskVal >= 40 ? { lv: 'Mērens', color: '#d97706' }
               :                 { lv: 'Zems', color: '#16a34a' };
    const riskX = 539 + (riskVal / 100) * 206;

    const bazi = profile?.bazi || {};
    const dm = bazi.Daymaster || {};
    const mainGod = bazi.mainGod || '—';
    const dmKey = `${dm.polarity || ''}_${dm.element || ''}`.trim();
    const dmTranslations = {
        'Jaņ_Koks': 'Izaugsmes un iniciatīvas orientācija',
        'Iņ_Koks': 'Diplomātijas un tīklošanās orientācija',
        'Jaņ_Uguns': 'Harizmātiskas un publiskas ekspresijas orientācija',
        'Iņ_Uguns': 'Detaļu un analītiskā fokusa orientācija',
        'Jaņ_Zeme': 'Stabilitātes un nemainīgu vērtību orientācija',
        'Iņ_Zeme': 'Rūpju un resursu vadības orientācija',
        'Jaņ_Metāls': 'Disciplīnas un izlēmīgas rīcības orientācija',
        'Iņ_Metāls': 'Kvalitātes, estētikas un statusa orientācija',
        'Jaņ_Ūdens': 'Mēroga, enerģijas un pārmaiņu orientācija',
        'Iņ_Ūdens': 'Intuitīvas ietekmes un vides nolasīšanas orientācija'
    };
    const dmName = dmTranslations[dmKey] || `${dm.polarity || ''} ${dm.element || '—'}`;

    const godMeanings = {
        'Friend': { role: 'Sadarbība un partnerība', color: '#1d4ed8' },
        'Rob_Wealth': { role: 'Mēroga un resursu apguve', color: '#047857' },
        'Direct_Officer': { role: 'Procesu pārvaldība un kārtība', color: '#6d28d9' },
        'Seven_Killings': { role: 'Krīžu vadība un operativitāte', color: '#b91c1c' },
        'Eating_God': { role: 'Dziļās meistarības eksperts', color: '#0369a1' },
        'Hurting_Officer': { role: 'Ideju prezentēšana un inovācijas', color: '#166534' },
        'Direct_Wealth': { role: 'Operacionālais pragmātiķis', color: '#854d0e' },
        'Indirect_Wealth': { role: 'Iespēju stratēģis', color: '#b45309' },
        'Indirect_Resource': { role: 'Netradicionālas zināšanas', color: '#7c3aed' },
        'Direct_Resource': { role: 'Sistēmas un resursu audits', color: '#4f46e5' },
    };
    const GOD_LV = {
        'Friend': 'Komandas partneris',
        'Rob_Wealth': 'Tirgus konkurents',
        'Eating_God': 'Dziļās meistarības eksperts',
        'Hurting_Officer': 'Ideju virzītājs / Inovators',
        'Direct_Wealth': 'Operacionālais pragmātiķis',
        'Indirect_Wealth': 'Iespēju stratēģis',
        'Direct_Officer': 'Sistēmas un procesu vadītājs',
        'Seven_Killings': 'Krīžu vadītājs / Taktiskais līderis',
        'Indirect_Resource': 'Stratēģiskais analītiķis',
        'Direct_Resource': 'Zināšanu pārvaldnieks',
    };
    const godLv = (g) => GOD_LV[g] || String(g || '—').replace(/_/g, ' ');
    const godInfo = godMeanings[mainGod] || { role: mainGod, color: '#64748b' };
    const baziColor = godInfo.color || '#15803d';

    const cyCell = (name, meta, score) => {
        const active = cyPrimary === (name.toLowerCase());
        return `<div class="psy-cyn" style="${active ? `background:${meta.color}15; border-color:${meta.color};` : ''}">`
            + `<div class="psy-cyn-name" style="color:${meta.color};">${meta.icon} ${name}</div>`
            + `<div class="psy-cyn-pct" style="color:${score > 0 ? meta.color : '#94a3b8'};">${score}%</div></div>`;
    };
    const cynGrid = `<div class="psy-cyn-grid">`
        + cyCell('Complex', cyComplex, cyScores.complex)
        + cyCell('Complicated', cyComplicated, cyScores.complicated)
        + cyCell('Chaotic', cyChaotic, cyScores.chaotic)
        + cyCell('Simple', cySimple, cyScores.simple)
        + `</div>`;

    return psyCols([
        psyCol('Karjeras enkuri (MIT Sloan)',
            psyChip(top1.color, `${top1.icon} ${top1.lv}`, 'Primārais vērtību enkurs', top1.score)
            + psyBar(`${top2.icon} ${top2.lv}`, top2.score, top2.color)
            + psyBar(`${top3.icon} ${top3.lv}`, top3.score, top3.color)
            + psyNote('', `Profila dominējošais enkurs ir <b>${top1.lv}</b>. Tas nosaka prioritātes, izvēloties jaunas lomas un projektus. Ja šis enkurs tiek apspiests, rodas gandarījuma trūkums.`),
            'capacity-anchors'),
        psyCol('Lēmumu pieņemšanas arhitektūra',
            `<div class="psy-collabel">Kānemana S1 / S2 sistēmas</div>`
            + `<div class="psy-s1s2"><div class="psy-s1s2-fill" style="width:${s1}%;"></div></div>`
            + `<div class="psy-row" style="font-size:0.74rem;font-weight:800;"><span style="color:#d97706;">S1 (ātrā): ${s1}%</span><span style="color:#2563eb;">S2 (lēnā): ${s2}%</span></div>`
            + `<div style="text-align:center;font-weight:800;color:${domColor};font-size:0.84rem;margin-top:1px;">${domK}</div>`
            + `<div class="psy-collabel" style="margin-top:2px;">Lēmumu vide (Cynefin domēni)</div>`
            + cynGrid
            + psyNote('', `Lēmumu vide ir <b>${cy.primaryMeta?.lv || 'Komplicēta'}</b>. Profils vislabāk spēj darboties situācijās, kas atbilst šim domēnam.`),
            'capacity-cynefin'),
        psyCol('Robežas &amp; bāzes audits',
            `<div class="psy-collabel">Izdegšanas robeža (${band.lv})</div>`
            + `<div class="psy-gauge"><div class="psy-gauge-caret" style="left:${riskVal}%;"></div></div>`
            + psyNote('Kritiskais trigeris:', b.lv)
            + `<div class="psy-collabel" style="margin-top:2px;">Bāzes dzinējspēks (BaZi)</div>`
            + psyBox(baziColor, dmName, `<b style="color:#1e293b;">${godLv(mainGod)}</b> — ${godInfo.role}`)
            + psyNote('', 'Šis rīcības dzinējs nosaka dabisko pieeju darbam un lēmumiem. Atbalsta resurss zemapziņā palīdz atgūt fokusu.'),
            'capacity-boundary'),
    ], 3);
}

// Lēmumu dizains — PAPILDU kapacitātes sadaļa, kas aizpilda brīvo laukumu labās kolonnas
// apakšā (blakus 'Komunikācija'). Apvieno divus 'Karjeras kapacitāte' paneļa blokus, kuru
// kopainā vēl nebija un kas nedublē capacity-anchors flīzi: Kapacitātes kopsavilkums
// (narrative.paragraph) + Lēmumu dizaina špikeris (loģika no tab_experiment.js cheatSheet:
// dom × isAligned × Cynefin vide). Fail-safe: ja kapacitātes datu nav → tukšs (flīze rāda essence).
function buildDecisionDesignSVG(profile) {
    const ca = profile?.careerAnchors || null;
    if (!ca || !ca.kahneman) return '';

    const dom     = ca.kahneman.dominant;                 // 's1' | 's2' | 'adaptive'
    const envLv   = ca.cynefin?.primaryMeta?.lv || 'tā primārā vide';
    const aligned = ca.isAligned;
    const domLv   = ca.kahneman.dominantMeta?.lv || (dom === 's1' ? 'Ātrā sistēma' : dom === 's2' ? 'Lēnā sistēma' : 'Adaptīvs stils');
    const kColor  = dom === 's1' ? '#d97706' : dom === 's2' ? '#1d4ed8' : '#7c3aed';

    let strengthText, actionTip;
    if (dom === 'adaptive') {
        strengthText = `Kognitīvais stils ir adaptīvs — nav viena dominējoša režīma, bet elastīga pārslēgšanās starp ātro (intuitīvo) un lēno (analītisko) sistēmu atkarībā no konteksta. Primārā vide: ${envLv}.`;
        actionTip    = `Krīzē vai haosā — uzticēties pirmajam impulsam un rīkoties ātri. Stabilā vidē — apzināti ieslēgt analīzi, pieprasīt datus un ieturēt pauzi pirms gala lēmuma.`;
    } else if (dom === 's1') {
        strengthText = aligned
            ? `Ātrā, intuitīvā domāšana ir saskaņā ar dabisko vidi (${envLv}). Profils izcili reaģē neparedzamībā un pieņem lēmumus ar minimālu datu apjomu.`
            : `Dabiskais stils ir ātrs un intuitīvs, taču primārā vide (${envLv}) prasa kārtību un analīzi — kompensējošs konfigurējums, kur intuīcija balansē vides prasību.`;
        actionTip    = aligned
            ? `Krīzē — uzticēties pirmajam impulsam. Reglamentētā vidē iebūvēt drošinātāju (ārējs viedoklis vai īsa pauze), lai novērstu sasteigtību.`
            : `Strukturētās situācijās balstīties uz kontrolsarakstiem. Intuīciju izmantot likumsakarību pamanīšanai, bet pirms gala lēmuma formāli pārbaudīt faktus.`;
    } else {
        strengthText = aligned
            ? `Lēnā, analītiskā domāšana saskan ar primāro vidi (${envLv}). Profils izcili analizē datus, būvē struktūras un atrod precīzāko risinājumu.`
            : `Dabiskais stils ir analītisks un strukturēts, taču primārā vide (${envLv}) prasa ātru eksperimentēšanu — kompensējošs konfigurējums, kur analīze neļauj haosam absorbēt.`;
        actionTip    = aligned
            ? `Neļaut sevi sasteigt — stiprums ir laiks un pārbaude. Haosā paļauties uz iepriekš sagatavotiem "ja–tad" protokoliem, lai izvairītos no analīzes paralīzes.`
            : `Lielos lēmumus sadalīt mazos, kontrolētos eksperimentos. Haosā nemeklēt vienīgo pareizo lēmumu — noteikt maksimālo pieļaujamo zaudējumu un rīkoties ātri.`;
    }

    const narrative = ca.narrative?.paragraph
        ? String(ca.narrative.paragraph).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
        : '';

    return psyCols([
        psyCol('Kapacitātes kopsavilkums',
            psyChip(kColor, `🧭 ${domLv}`, `Vide: ${envLv} · ${aligned ? 'dabiskā saskaņa' : 'kompensē vidi'}`)
            + (narrative ? psyNote('', narrative) : '<div class="psy-lede">Kopsavilkums nav pieejams.</div>'),
            'capacity-summary'),
        psyCol('Lēmumu dizaina špikeris',
            psyNote('', strengthText)
            + psyBox(kColor, '💡 Rīcības ieteikums', actionTip),
            'capacity-cheatsheet'),
    ], 1);
}

function buildTimingSVG(profile) {
    const timing = profile?.timing || null;
    if (!timing || !timing.macro) return '';
    const m = timing.macro;
    const t = timing.tactical || {};
    const al = timing.anchorAlignment || {};
    const trans = m.transition || {};

    const birthDate = profile?.birth_info?.date || '';
    const luckPillars = profile?.bazi?.luck_pillars || [];
    const currentAgeYrs = birthDate
        ? Math.floor((Date.now() - new Date(birthDate).getTime()) / (365.25 * 24 * 3600 * 1000))
        : null;
    const relevantPillars = currentAgeYrs !== null
        ? luckPillars.filter(lp => (lp.ageEnd ?? 0) > currentAgeYrs)
        : luckPillars;

    const currentDasha = profile?.vedic?.current_dasha;
    const allDashas    = profile?.vedic?.all_dashas || [];
    const now = new Date();
    const futureDashas = allDashas.filter(d => {
        const end = (d.end instanceof Date) ? d.end : new Date(d.end);
        return end > now;
    }).slice(0, 5);

    const elemColors = { 'Koks':'#15803d','Uguns':'#b91c1c','Zeme':'#b45309','Metāls':'#64748b','Ūdens':'#1d4ed8' };
    const dashaColors = {
        'Saule':    '#b45309', 'Mēness':   '#475569', 'Marss':    '#b91c1c',
        'Rahu':     '#7e22ce', 'Jupiters': '#b45309', 'Saturns':  '#64748b',
        'Merkurs':  '#15803d', 'Ketū':     '#64748b', 'Venera':   '#be185d',
    };
    const GOD_LV = {
        'Friend': 'Komandas partneris',
        'Rob_Wealth': 'Tirgus konkurents',
        'Eating_God': 'Dziļās meistarības eksperts',
        'Hurting_Officer': 'Ideju virzītājs / Inovators',
        'Direct_Wealth': 'Operacionālais pragmātiķis',
        'Indirect_Wealth': 'Iespēju stratēģis',
        'Direct_Officer': 'Sistēmas un procesu vadītājs',
        'Seven_Killings': 'Krīžu vadītājs / Taktiskais līderis',
        'Indirect_Resource': 'Stratēģiskais analītiķis',
        'Direct_Resource': 'Zināšanu pārvaldnieks',
    };
    const godLv = (g) => GOD_LV[g] || String(g || '—').replace(/_/g, ' ');

    const pillarsHtml = `<div class="psy-timeline">` + relevantPillars.slice(0, 5).map((lp, idx) => {
        const isCurrent = idx === 0;
        const stemObj = (typeof lp.stem === 'object') ? lp.stem : { name: lp.stem || '—', element: '' };
        const elemColor = elemColors[stemObj.element] || '#64748b';
        const label = lp.interpretation ? lp.interpretation.godLabel : `${stemObj.polarity || ''} ${stemObj.element || ''}`;
        const ageRange = `${lp.ageStart ?? '?'}–${lp.ageEnd ?? '?'} g.`;
        return `<div class="psy-tl-cell" style="--c:${elemColor};${isCurrent ? `background:${elemColor}15;border-color:${elemColor};` : ''}">`
            + `${isCurrent ? `<div class="psy-tl-tag" style="background:${elemColor};">AKTĪVS</div>` : ''}`
            + `<div class="psy-tl-age">${ageRange}</div><div class="psy-tl-label" style="color:${ink(elemColor)};">${label}</div></div>`;
    }).join('') + `</div>`;

    const dashaHtml = futureDashas.length
        ? `<div class="psy-timeline">` + futureDashas.slice(0, 4).map(d => {
            const lordLv = (d.lord || '—').replace(/^Meness$/, 'Mēness');
            const col = dashaColors[lordLv] || '#64748b';
            const isCurrent = d === currentDasha;
            return `<div class="psy-tl-cell" style="--c:${col};${isCurrent ? `background:${col}18;border-color:${col};` : ''}">`
                + `<div class="psy-tl-label" style="color:${ink(col)};">${lordLv}</div></div>`;
        }).join('') + `</div>`
        : `<div class="psy-lede">Nav nākotnes dašu datu.</div>`;

    const tColor = t.phaseMeta?.color || '#b45309';
    const tLabel = t.phaseMeta?.lv || 'Nav fokusa';
    const tGodName = t.liuNianGod ? godLv(t.liuNianGod) : '—';

    let alColor = '#64748b', alLabel = 'Neitrāls', alDesc = 'Enkurs nekonfliktē ar fāzi.';
    if (al.status === 'aligned') { alColor = '#15803d'; alLabel = 'Saskaņa'; alDesc = 'Primārais enkurs dabiski plūst kopā ar 10 gadu cikla fāzi.'; }
    else if (al.status === 'conflict') { alColor = '#b91c1c'; alLabel = 'Konflikts'; alDesc = 'Enkurs un fāze prasa atšķirīgas pieejas. Vajadzīgs alternatīvs ceļš.'; }

    let cColor = '#15803d', cLabel = 'Stabils fāzes vidus', cDesc = 'Šobrīd nav pārejas fāzu turbulences. Cikls stabils.';
    if (trans.active) {
        cColor = '#b91c1c';
        cLabel = trans.kind === 'entering' ? 'Pārejas sākums' : 'Pārejas noslēgums';
        cDesc = 'Turbulences logs. Nav ieteicams pieņemt lielus, neatgriezeniskus lēmumus.';
    }
    const targetRiskId = trans.active ? 'timing-transition' : 'timing-macro';

    return psyCols([psyCol('10 gadu stratēģiskās fāzes (dekādes)', pillarsHtml, 'timing-pillars')], 1)
    + psyCols([psyCol('Dzīves un karjeras fona cikli', dashaHtml, 'timing-dasha')], 1)
    + psyCols([
        psyCol(`🎯 Operatīvais gads (${t.year || ''})`,
            psyChip(tColor, `Fokuss: ${tLabel}`, t.alignedWithMacro ? '⇄ Sakrīt ar makro' : '↔ Operatīvais logs')
            + psyNote('Dzinējspēks:', tGodName),
            'timing-tactical'),
        psyCol('⚖ Enkura saskaņa',
            psyChip(alColor, `Statuss: ${alLabel}`, al.anchorLv || 'Nav enkura')
            + psyNote('', alDesc),
            'timing-alignment'),
        psyCol('⚠️ Pāreja &amp; riski',
            psyChip(cColor, cLabel, trans.active ? 'Turbulences logs' : 'Stabils periods')
            + psyNote('', cDesc),
            targetRiskId),
    ], 3);
}

function buildRelationshipSVG(profile) {
    const relDyn = profile?.relationshipDynamics || null;
    if (!relDyn) return '';
    const nak = profile?.vedic?.nakshatra || {};

    const a = relDyn.attachment?.primary || { lv: 'Nav datu', score: 50, color: '#64748b', icon: '💞', summary: '', partnerNeeds: '' };
    const primaryHorseman = relDyn.horsemen?.top?.[0] || { lv: 'Nav riska', score: 0, color: '#16a34a', icon: '✓', pattern: '', antidote: '' };
    const nakshatraName = nak.nakshatra || '—';
    const nakshatraLord = String(nak.lord || '—').replace(/^Meness$/, 'Mēness');

    const nadiMap = {
        'Ashwini':1,'Ardra':1,'Punarvasu':1,'Uttara Phalguni':1,'Hasta':1,'Jyeshtha':1,'Mula':1,'Shatabhisha':1,'Purva Bhadrapada':1,
        'Bharani':2,'Mrigashira':2,'Pushya':2,'Purva Phalguni':2,'Chitra':2,'Anuradha':2,'Purva Ashadha':2,'Dhanishta':2,'Uttara Bhadrapada':2,
        'Krittika':3,'Rohini':3,'Ashlesha':3,'Magha':3,'Swati':3,'Vishakha':3,'Uttara Ashadha':3,'Shravana':3,'Revati':3,
    };
    const nadiLabels = { 1: 'Vata (Gaiss)', 2: 'Pitta (Uguns)', 3: 'Kapha (Ūdens)' };
    const nadiColors = { 1: '#0369a1', 2: '#b91c1c', 3: '#047857' };
    const myNadi = nadiMap[nak.nakshatra] || 0;
    const nadiLabel = nadiLabels[myNadi] || '—';
    const nadiColor = nadiColors[myNadi] || '#64748b';

    const ganaMap = {
        'Ashwini':'Deva','Mrigashira':'Deva','Punarvasu':'Deva','Pushya':'Deva','Hasta':'Deva','Swati':'Deva','Anuradha':'Deva','Shravana':'Deva','Revati':'Deva',
        'Bharani':'Manushya','Rohini':'Manushya','Ardra':'Manushya','Purva Phalguni':'Manushya','Uttara Phalguni':'Manushya','Purva Ashadha':'Manushya','Uttara Ashadha':'Manushya','Purva Bhadrapada':'Manushya','Uttara Bhadrapada':'Manushya',
        'Krittika':'Rakshasa','Ashlesha':'Rakshasa','Magha':'Rakshasa','Chitra':'Rakshasa','Vishakha':'Rakshasa','Jyeshtha':'Rakshasa','Mula':'Rakshasa','Dhanishta':'Rakshasa','Shatabhisha':'Rakshasa',
    };
    const ganaColors = { 'Deva': '#b45309', 'Manushya': '#1d4ed8', 'Rakshasa': '#b91c1c' };
    const myGana = ganaMap[nak.nakshatra] || '—';
    const ganaColor = ganaColors[myGana] || '#64748b';
    const myGanaLv = myGana === 'Deva' ? 'Dievišķais (Deva)' : myGana === 'Manushya' ? 'Cilvēciskais (Manushya)' : myGana === 'Rakshasa' ? 'Dinamiskais (Rakshasa)' : myGana;

    const ganaDesc = myGana === 'Deva' ? 'Tiecas uz harmoniju, mieru, iejūtību un augstiem garīgiem principiem.'
        : myGana === 'Manushya' ? 'Pragmatisks, reālistisks un tendēts uz praktisku sadarbību.'
        : 'Spēcīga, aizrautīga, intensīva un gatava aktīvai rīcībai.';

    return psyCols([
        psyCol('Emocionālā drošība (Bowlby)',
            psyChip(a.color, `${a.icon} ${a.lv}`, 'Tavs piesaistes stils', a.score)
            + psyLede('Piesaistes modelis parāda, kā tu reaģē uz emocionālu tuvību un bailēm zaudēt saikni.')
            + psyNote('Uzvedība attiecībās:', a.summary || '—')
            + psyNote('Attiecību vajadzība:', a.partnerNeeds || '—', a.color),
            'relationship-attachment'),
        psyCol('Strīdu uzvedība (Gottman)',
            psyChip(primaryHorseman.color, `${primaryHorseman.icon} ${primaryHorseman.lv}`, 'Galvenais strīdu risks', primaryHorseman.score)
            + psyLede('Gotmana tests parāda graujošos strīdu riskus, kas aktivizējas krīzes brīžos.')
            + psyNote('Tavs risks stresa brīžos:', primaryHorseman.pattern || '—')
            + psyNote('Risinājums (Antidots):', primaryHorseman.antidote || '—', '#047857'),
            'relationship-horsemen'),
        psyCol('Vēdu saderības audits',
            psyChip(nadiColor, nakshatraName, 'Mēness fona zvaigznājs')
            + psyNote('Valda:', `${nakshatraLord}. Enerģijas stihija (Nadi): ${nadiLabel}.`)
            + psyChip(ganaColor, myGanaLv, 'Dabas temperaments')
            + psyNote('', ganaDesc)
            + psyNote('Vēdu saderības tests (Ashtakoot):', 'Salīdzina abu partneru zvaigznājus 8 dzīves līmeņos (raksturs, biolauks, ģenētika u.c.). Skatīt 8 līmeņu aprakstu ➜'),
            'relationship-vedic'),
    ], 3);
}

function buildExistentialSVG(profile) {
    const existential = profile?.existentialAudit || null;
    if (!existential) return '';
    const d = existential.macro?.dharma || { lv: 'Nav datu', color: '#64748b', icon: '🧭', mission: '', deepSatisfaction: '' };
    const r = existential.mezo?.primary || { lv: 'Nav datu', color: '#64748b', icon: '🔄', biotope: '', burnoutTrigger: '' };
    const f = existential.micro || { emoji: '🔋', element: '—', morning: '', restart: '', tithiModulator: '' };
    const atmakarakaLord = String(existential.macro?.atmakarakaPlanet || '—').replace(/^Meness$/, 'Mēness');

    return psyCols([
        psyCol('Dvēseles misija (Dharma)',
            psyChip(d.color, `${d.icon} ${d.lv}`, `Ceļvedis: ${atmakarakaLord}`)
            + psyLede('Garīgā misija parāda tavu galveno ceļu, dzīves jēgu un to, kas sniedz patiesu piepildījumu.')
            + psyNote('Dzīves jēga:', d.mission || '—')
            + psyNote('Gandarījums:', d.deepSatisfaction || '—', d.color),
            'existential-dharma'),
        psyCol('Dabas ritmi (Bioritmi)',
            psyChip(r.color, `${r.icon} ${r.lv}`, 'Bioritma pamats')
            + psyLede('Dabas bioritms nosaka tavu ideālo vidi, lai saglabātu enerģiju un izvairītos no spēku izsīkuma.')
            + psyNote('Tavs biotops:', r.biotope || '—')
            + psyNote('Izdegšanas trigeris:', r.burnoutTrigger || '—', '#b91c1c'),
            'existential-rhythm'),
        psyCol('Ikdienas plūsmas dizains',
            psyChip('#4f46e5', `${f.emoji} ${f.element}`, 'Dzimšanas elements')
            + psyNote('Rīts:', f.morning || '—')
            + psyNote('Restartēšanās:', f.restart || '—')
            + psyNote('Mēness dienas korekcija:', f.tithiModulator || 'Šodien nav aktīvu Mēness dienas modifikāciju.'),
            'existential-flow'),
    ], 3);
}

function buildBurnoutSVG(profile) {
    const psyAudit = profile?.psychosomaticAudit || null;
    if (!psyAudit) return '';

    const somScore = psyAudit.psychosomMeter?.psychosomScore || 0;
    const chironScore = psyAudit.psychosomMeter?.chironActScore || 0;
    const meterNarrative = psyAudit.psychosomMeter?.narrative || '';
    
    const firstAcute = psyAudit.acute?.signals?.[0] || { soma: 'Nav akūtu marķieru', signal: 'Viss mierīgi' };
    const firstChronic = psyAudit.chronic?.patterns?.[0] || { soma: 'Nav hronisku marķieru', pattern: 'Viss mierīgi' };
    const hironWound = psyAudit.acute?.hironWound || '';
    
    const comp = psyAudit.compensation || {};
    const primary = comp.primary || { element: 'Koks', compensation: '' };
    
    const baziElements = profile?.bazi?.elements || {};
    const elementColors = { Koks:'#15803d', Uguns:'#b91c1c', Zeme:'#b45309', Metāls:'#64748b', Ūdens:'#1d4ed8' };
    const elementIcons  = { Koks:'🌱', Uguns:'🔥', Zeme:'🗿', Metāls:'⚙', Ūdens:'🌊' };
    const elementItems = Object.entries(baziElements).map(([el, pct]) => ({
        key: el, label: el, color: elementColors[el] || '#64748b', icon: elementIcons[el] || '', score: pct
    }));
    const dominantEl = elementItems.length ? elementItems.reduce((a, b) => (a.score > b.score ? a : b)).key : '—';
    const dominantIcon = elementIcons[dominantEl] || '';

    const compColor = elementColors[primary.element] || '#15803d';

    return psyCols([
        psyCol('Stresa &amp; somatiskie riski',
            psyChip('#b91c1c', '⚠️ Stresa signāli', 'Ķermeņa check-engine')
            + psyLede('Ķermenis uzkrāj spriedzi somatiskajos punktos pirms prāts apzinās spēku izsīkumu.')
            + psyNote('Akūtais punkts:', firstAcute.soma || '—')
            + psyNote('Hroniskā zona:', firstChronic.soma || '—', '#b91c1c')
            + (hironWound ? psyNote('Fona brūce (Hirons):', hironWound, '#6d28d9') : ''),
            'burnout-stress'),
        psyCol('Psihosomatiskais mērītājs',
            psyChip('#4f46e5', '📊 Savienojums', 'Somatiskā jūtība', somScore)
            + psyLede('Mēra, cik izteikti emocionālās trauksmes un mentālā pārslodze izpaužas kā fiziski simptomi.')
            + psyNote('Jūtības audits:', meterNarrative || '—')
            + psyNote('Hirona brūces aktivitāte:', `${chironScore}%`, '#6d28d9'),
            'burnout-psychosomatic'),
        psyCol('Atjaunošanās &amp; balanss',
            psyChip('#15803d', `${dominantIcon} ${dominantEl}`, 'Dominējošais elements')
            + psyNote('', 'Rāda stresa pārslodzes elementāro virzienu.')
            + psyChip(compColor, `${elementIcons[primary.element] || ''} ${primary.element}`, 'Kompensācijas elements')
            + psyNote('Atjaunošanās ieteikums:', `${primary.compensation || 'Atjaunošanās ieteikumi nav pieejami.'} Skatīt pilnu protokolu ➜`, '#7e22ce'),
            'burnout-compensation'),
    ], 3);
}

function buildMotivationSVG(profile) {
    const s = profile?.synergy || {};
    const present = (x) => x !== null && x !== undefined && x !== '' && !isNaN(Number(x));
    if (!present(s.leadership?.pct) && !present(s.performance?.pct) && !present(s.risks?.pct)) {
        return `
        <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-height:350px; margin:0 auto;">
            <rect x="20" y="35" width="740" height="375" rx="12" fill="#fffbeb" stroke="#f59e0b" stroke-width="1.5" />
            <text x="390" y="190" text-anchor="middle" font-size="20" font-weight="800" fill="#b45309">Dati nav pieejami</text>
            <text x="390" y="230" text-anchor="middle" font-size="16.5" fill="#92400e">Šim profilam vēl nav aprēķinātas motivācijas asis un arhetipi.</text>
        </svg>`;
    }

    const num = (x, def) => {
        if (x === null || x === undefined || x === '') return def;
        const n = Number(x);
        return isNaN(n) ? def : n;
    };

    // D/I/V no nosauktajiem personības trait (sk. hacker_panel.deriveDIV) — konsekventi ar
    // badge un hakera paneli; NE no synergy radara skoriem (leadership/performance/risks).
    const { D, I, V } = deriveDIV(profile);

    const driverKey = pickDriverKey(D, I, V, s.motivation);
    const driver = DRIVERS[driverKey] || {};
    const dr = driver.pro || { title: driverKey, butiba: '—', valuta: '—', rez: '—', balva: '—' };

    const MAXD = Math.sqrt(3) * 100;
    const ranked = WARN_ARCHETYPES
        .map(a => ({ a, sim: Math.max(0, Math.round(100 * (1 - Math.sqrt((D - a.d) ** 2 + (I - a.i) ** 2 + (V - a.v) ** 2) / MAXD))) }))
        .sort((x, y) => y.sim - x.sim)
        .slice(0, 3);
    
    const tension = keyTension(D, I, V) || { title: 'Līdzsvarots profils', pro: '—' };

    return psyCols([
        psyCol('Motivācijas prioritāte',
            psyChip('#d946ef', `🕹️ ${dr.title}`, 'Galvenais dzinējs')
            + psyNote('Būtība:', dr.butiba)
            + psyNote('Galvenā valūta:', dr.valuta)
            + psyBox('#d946ef', 'Piemērs sarunā', `"${dr.rez}"`)
            + psyBox('#15803d', '💡 Atalgojuma ieteikums', dr.balva),
            't3-motivacija'),
        psyCol('Tuvākie arhetipi (sajaukums)',
            ranked.map((rk, idx) => psyBar(`${idx + 1}. ${rk.a['pro'].title}`, rk.sim, idx === 0 ? '#d946ef' : '#e9a8f5')).join('')
            + `<div class="psy-lede">Profils nav viens tips — tas ir šo arhetipu sajaukums.</div>`
            + psyBox('#86198f', `⚖️ Secinājums: ${tension.title}`, tension.pro),
            't3-motivacija'),
    ], 2);
}

function buildArudhaSVG(profile) {
    const ar = computeArudhaLagna(profile);
    if (!ar) return '';
    
    // Gap indicators
    const gapVal = ar.gap; // 2, 3, or 4
    let caretX = 70;
    let gapLabel = 'Tuvs tēls';
    let gapColor = '#15803d';
    if (gapVal === 3) {
        caretX = 145;
        gapLabel = 'Mērena plaisa';
        gapColor = '#b45309';
    } else if (gapVal === 4) {
        caretX = 220;
        gapLabel = 'Liela plaisa';
        gapColor = '#b91c1c';
    }

    const gapIdx = gapVal === 4 ? 2 : gapVal === 3 ? 1 : 0;
    const segHtml = ['#15803d', '#b45309', '#b91c1c']
        .map((c, i) => `<span style="background:${c}; opacity:${i === gapIdx ? 1 : 0.25};"></span>`).join('');

    return psyCols([
        psyCol('Būtība (Lagna)',
            psyChip('#6d28d9', `🎭 Iekšējais Es: ${ar.lagna}`, 'Kāds tu esi iekšēji')
            + psyNote('', ar.lagnaSelf),
            't3-arudha'),
        psyCol('Uztvere (Arudha Lagna)',
            psyChip('#4f46e5', `👥 Ārējais tēls: ${ar.arudha}`, 'Kā tevi redz citi')
            + psyNote('', ar.meaning),
            't3-arudha'),
    ], 2)
    + psyCols([
        psyCol('Būtības ↔ tēla plaisa',
            `<div class="psy-row"><span class="psy-chip-title" style="color:${gapColor};">${gapLabel}</span></div>`
            + `<div class="psy-seg">${segHtml}</div>`
            + `<div class="psy-seg-labels"><span>Sakrīt</span><span>Mērena</span><span>Plaisa</span></div>`
            + psyNote('', ar.gapText),
            't3-arudha'),
    ], 1);
}

function buildCommunicationSVG(profile) {
    const infl = profile.influence || (profile.personality ? buildInfluenceReport(profile.personality) : null);
    if (!infl || infl.length < 4) {
        return `
        <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-height:350px; margin:0 auto;">
            <rect x="20" y="35" width="740" height="375" rx="12" fill="#fffbeb" stroke="#f59e0b" stroke-width="1.5" />
            <text x="390" y="215" text-anchor="middle" font-size="19" font-weight="800" fill="#b45309">Saziņas dati nav pieejami</text>
        </svg>`;
    }
    const [s1, s2, s3, s4] = infl;

    const quad = (s, icon) => psyCol('',
        psyChip(s.color, `${icon} ${s.title}`)
        + psyNote('', s.summary)
        + (s.items[1]?.do ? `<div class="psy-do"><b>✓ JĀ:</b> ${s.items[1].do}</div>` : '')
        + (s.items[1]?.dont ? `<div class="psy-dont"><b>✗ NĒ:</b> ${s.items[1].dont}</div>` : ''),
        't3-celvedis');

    return psyCols([quad(s1, '💬'), quad(s2, '🚀'), quad(s3, '🛡️'), quad(s4, '📅')], 2);
}

function localRiskConsensus(details) {
    const sc = (details || []).map(d => Number(d.score)).filter(n => !isNaN(n));
    if (sc.length < 2) return null;
    const mean = sc.reduce((a, b) => a + b, 0) / sc.length;
    const sd = Math.sqrt(sc.reduce((a, b) => a + (b - mean) ** 2, 0) / sc.length);
    if (sd < 1.4) return { label: 'Sistēmas vienojas', icon: '✓', col: '#15803d', bg: '#f0fdf4' };
    if (sd < 2.6) return { label: 'Daļēja saskaņa', icon: '≈', col: '#b45309', bg: '#fffbeb' };
    return { label: 'Sistēmas nesaskan', icon: '⚡', col: '#b91c1c', bg: '#fef2f2' };
}

function buildShadowSVG(profile) {
    const lead = profile.leadership?.primary;
    const drObj = DERAILERS[lead?.key] || { asEmployee: [], asLeader: [] };
    const emp = drObj.asEmployee || [];
    const ldr = drObj.asLeader || [];

    // NULL, nevis 50: trūkstoša risku intensitāte nedrīkst fabricēt "vidēju 50%" joslu (kā renderShadowCard).
    const rawPct = profile.synergy?.risks?.pct;
    const intensity = (rawPct !== undefined && rawPct !== null && rawPct !== '' && !isNaN(Number(rawPct))) ? Math.round(Number(rawPct)) : null;

    // Bez derailera datiem UN bez intensitātes — nerādīt fabricētu lasījumu (kā renderShadowCard).
    if (!emp.length && !ldr.length && intensity == null) return '';

    const renderDerailersList = (list) => {
        if (!list.length) return `<div class="psy-lede">Šim tipam nav reģistrētu klupšanas akmeņu.</div>`;
        return list.slice(0, 2).map(d =>
            `<div class="psy-derail"><div class="psy-derail-name">${d.name}</div>`
            + `<div class="psy-note"><b style="color:#b91c1c;">Trigers:</b> ${d.trigger}</div>`
            + `<div class="psy-note"><b style="color:#b91c1c;">Izpausme:</b> ${d.shows}</div></div>`
        ).join('');
    };

    let html = psyCols([
        psyCol('Klupšanas akmeņi: darbinieks', renderDerailersList(emp), 't3-klupsana'),
        psyCol('Klupšanas akmeņi: vadītājs', renderDerailersList(ldr), 't3-klupsana'),
    ], 2);

    // Intensitātes/saskaņas josla — tikai ja ir reāli risku dati.
    if (intensity != null) {
        const lvl = intensity > 65 ? { t: 'Augsta', col: '#b91c1c' } : intensity >= 40 ? { t: 'Vidēja', col: '#b45309' } : { t: 'Zema', col: '#15803d' };
        const cons = localRiskConsensus(profile.synergy?.risks?.details) || { label: 'Nav datu', icon: '?', col: '#64748b', bg: '#f8fafc' };
        html += psyCols([
            psyCol('Ēnas intensitāte &amp; sistēmu saskaņa',
                psyBar(`Ēnas intensitāte — ${lvl.t}`, intensity, lvl.col)
                + psyBox(cons.col, `${cons.icon} Sistēmu saskaņa (konsenss)`,
                    `5 astro-psiholoģiskās skolas par šo risku intensitāti uzrāda: <b>${cons.label}</b>.`),
                't3-klupsana'),
        ], 1);
    }
    return html;
}

function buildMidpointsSVG(profile) {
    const midpoints = profile.western?.midpoints || [];
    if (!midpoints.length) {
        return `
        <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-height:350px; margin:0 auto;">
            <rect x="20" y="35" width="740" height="375" rx="12" fill="#fffbeb" stroke="#f59e0b" stroke-width="1.5" />
            <text x="390" y="215" text-anchor="middle" font-size="19" font-weight="800" fill="#b45309">Midpointu dati nav pieejami</text>
        </svg>`;
    }

    const findMp = (pair) => midpoints.find(m => m.pair === pair);
    const getMpData = (m) => {
        if (!m || m.degree == null) return null;
        const sign = SIGNS_LV[Math.floor(m.degree / 30) % 12];
        const data = MIDPOINT_DICT[m.pair]?.[sign];
        return data ? { sign, title: MIDPOINT_DICT[m.pair].title, ...data } : null;
    };

    const sunMoon = getMpData(findMp('Sun/Moon'));
    const others = ['Sun/Mars', 'Mercury/Mars', 'Mars/Saturn', 'Jupiter/Uranus']
        .map(pair => ({ pair, data: getMpData(findMp(pair)) }))
        .filter(x => x.data);

    const PAIR_LABELS = {
        'Sun/Mars': '⚔️ Saule/Marss (Uzbrukums)',
        'Mercury/Mars': '📣 Merkurs/Marss (Strīdi)',
        'Mars/Saturn': '⛓️ Marss/Saturns (Spītība)',
        'Jupiter/Uranus': '⚡ Jupiters/Urāns (Lūzumi)'
    };

    const othersHtml = others.map(item =>
        `<div class="psy-mp"><div class="psy-row"><span class="psy-mp-name">${PAIR_LABELS[item.pair] || item.pair}</span>`
        + `<span class="psy-mp-sign">${item.data.sign}</span></div>`
        + `<div class="psy-note"><b>Būtība:</b> ${item.data.g}</div>`
        + `<div class="psy-note"><b style="color:#b91c1c;">Risks:</b> ${item.data.r}</div></div>`
    ).join('') || `<div class="psy-lede">Nav papildu viduspunktu datu.</div>`;

    return psyCols([
        psyCol('Krīzes kodols: Saule / Mēness',
            psyChip('#7c3aed', `⚖️ Sun/Moon: ${sunMoon?.sign || '—'}`, 'Krīzes reakcijas kodols')
            + psyNote('Krīzes reakcija:', sunMoon?.g || '—')
            + psyNote('Lielākais risks:', sunMoon?.r || '—', '#b91c1c')
            + psyBox('#7c3aed', 'Atbalsta atslēga', sunMoon?.h || '—'),
            't3-viduspunkti'),
        psyCol('Papildu strēles / viduspunkti', othersHtml, 't3-viduspunkti'),
    ], 2);
}

function buildRadarSVG(profile) {
    const num = (x, def) => {
        if (x === null || x === undefined || x === '') return def;
        const n = Number(x);
        return isNaN(n) ? def : n;
    };
    const p = {};
    for (const cat of (profile?.personality || [])) {
        for (const t of (cat.traits || [])) p[t.id] = t.pct;
    }
    const g = (id) => num(p[id], NaN);

    // NaN, nevis 50: trūkstoši dati nedrīkst fabricēt "vidēju" lasījumu — klātneesošās lēcas
    // tiek izlaistas, nevis rādītas kā 50% (kā contradiction_radar.js pilnais panelis).
    const D    = num(profile?.synergy?.leadership?.pct, NaN);
    const V    = num(profile?.synergy?.risks?.pct, NaN);
    const apc  = (() => {
        const a = g('ambition'), b = g('perseverance'), c = g('conscient');
        return [a, b, c].some(isNaN) ? NaN : Math.round((a + b + c) / 3);
    })();
    const init = g('initiative');
    const strS = g('fightresponse');
    const horseman = profile?.relationshipDynamics?.horsemen?.top?.[0] || null;

    const lvl = (v) => (v > 60 ? 'hi' : v < 40 ? 'lo' : 'mid');

    // Tikai klātesošās lēcas (NaN izfiltrētas).
    const driveLenses = [
        { label: 'Vadības ambīcija', v: D },
        { label: 'Ikdienas pašmotivācija', v: apc },
        { label: 'Starta iniciatīva', v: init },
    ].filter(l => !isNaN(l.v));

    // Synth A — krusta-dimensiju secinājums tikai ja ≥2 lēcas klāt.
    const synthA = (() => {
        if (driveLenses.length < 2) return '';
        if (!isNaN(D) && !isNaN(apc) && D < 40 && apc > 60)
            return 'Netiecas pēc varas, bet uzticētu darbu velk izcili. Uzticams dziļuma cilvēks, ne karjerists.';
        if (!isNaN(D) && !isNaN(apc) && D > 60 && apc < 40)
            return 'Grib vadīt un redz lielo bildi, bet ikdienas rutīna nogurdina. Vajag komandu ikdienas izpildei.';
        if (!isNaN(apc) && !isNaN(init) && apc > 60 && init < 40)
            return 'Spēcīga pašmotivācija, bet kūtrs starts — pirmajam solim vajag ārēju rāmi un termiņu.';
        if (!isNaN(apc) && !isNaN(init) && apc < 40 && init > 60)
            return 'Viegls starts, bet grūta noturēšana — vajadzīgi īsi posmi un atzinība ceļā.';
        return 'Lēcas mēra dažādus slāņus: ambīcija rāda virzienu; pašmotivācija — patstāvību; iniciatīva — startu.';
    })();

    // Spiediena konteksti — tikai klātesošie (bez fabricētiem noklusējumiem).
    const ctxNotes = [];
    if (!isNaN(strS)) ctxNotes.push(psyNote('⚡ Akūts spiediens:', strS > 60 ? 'Spiediens mobilizē, aktivizē.' : (strS < 40 ? 'Akūtā brīdī sastingst vai bēg.' : 'Mērens spiediens aktivizē.')));
    if (!isNaN(V)) ctxNotes.push(psyNote('🌊 Ilgtermiņa slodze:', V > 60 ? 'Ilgstošā spriedzē meklē atbalstu.' : (V < 40 ? 'Ilgstoši satricinājumi skar maz.' : 'Mērena ilgtermiņa noturība.')));
    if (horseman) ctxNotes.push(psyNote('💬 Strīdu risks (Gotman):', `${horseman.lv}${horseman.pattern ? ': ' + String(horseman.pattern).split('—')[0].trim().toLowerCase() : ''}`));

    // Synth B — tikai ja abas spiediena lēcas klāt.
    const synthB = (() => {
        if (isNaN(strS) || isNaN(V)) return '';
        if (strS > 60 && V > 60)
            return 'Akūtā brīdī mobilizējas, bet ilgstoša spriedze izdedzina. Sprinteris, nevis maratonists.';
        if (strS < 40 && V < 40)
            return 'Akūtā brīdī izvairās no konfrontācijas, bet kopumā emocionāli noturīgs. Nav trausls, bet izvēlas nekonfrontēt.';
        if (strS < 40 && V > 60)
            return 'Gan akūtā brīdī, gan ilgtermiņā spiediens ir smags — psiholoģiskā drošība ir galvenais darba apstāklis.';
        if (strS > 60 && V < 40)
            return 'Spiediens mobilizē un ilgstoša spriedze nesagāž — dabisks krīžu risinātājs un vadītājs.';
        return 'Pirmā reakcija, ilgstoša slodze un attiecību konflikti atšķiras — tieši atšķirība parāda krīzes uzvedību.';
    })();

    // Pilnīgi bez datu — nerādīt fabricētu paneli (flīze rāda essence).
    if (!driveLenses.length && !ctxNotes.length) return '';

    const barColor = (v) => lvl(v) === 'hi' ? '#6d28d9' : lvl(v) === 'lo' ? '#94a3b8' : '#a78bfa';

    const cols = [];
    if (driveLenses.length) {
        cols.push(psyCol('Dzinēja saskaņa (lēcas)',
            psyChip('#6d28d9', '🚀 Iekšējais dzinulis', 'Neatkarīgas lēcas')
            + driveLenses.map(l => psyBar(l.label, l.v, barColor(l.v))).join('')
            + (synthA ? psyBox('#6d28d9', 'Dzinēja sintēze', synthA) : ''),
            't3-radars'));
    }
    if (ctxNotes.length) {
        cols.push(psyCol('Spiediena uzvedība (konteksti)',
            psyChip('#1d4ed8', '🌊 Uzvedība zem spiediena', 'Konteksti')
            + ctxNotes.join('')
            + (synthB ? psyBox('#1d4ed8', 'Spiediena sintēze', synthB) : ''),
            't3-radars'));
    }
    return psyCols(cols, cols.length === 1 ? 1 : 2);
}

function buildMemoSVG(profile) {
    const memo = buildInvestorMemo(profile);
    if (!memo) {
        return `
        <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-height:350px; margin:0 auto;">
            <rect x="20" y="35" width="740" height="375" rx="12" fill="#fffbeb" stroke="#f59e0b" stroke-width="1.5" />
            <text x="390" y="215" text-anchor="middle" font-size="19" font-weight="800" fill="#b45309">Kopvērtējuma dati nav pieejami</text>
        </svg>`;
    }

    const cards = memo.cards || [];
    const earns = memo.earns || [];
    const costs = memo.costs || [];
    const levers = memo.levers || {};

    const renderCardHtml = (c) => {
        let badgeColor = '#b45309';
        if (c.verdict === 'PIRKT') badgeColor = '#15803d';
        else if (c.verdict === 'NELIKT') badgeColor = '#b91c1c';
        return `<div class="psy-verdict" style="border-left-color:${badgeColor};">`
            + `<div class="psy-row"><span class="psy-verdict-label">${c.label}</span>`
            + `<span class="psy-verdict-badge" style="color:${badgeColor}; background:${badgeColor}14;">${c.verdict} (${c.score}%)</span></div>`
            + `<div class="psy-note" style="margin-top:2px;">${c.reason}</div></div>`;
    };

    return psyCols([
        psyCol('Lomu verdikti (vadītājam)', cards.map(renderCardHtml).join(''), 't3-memo'),
        psyCol('Sviras, peļņa &amp; izmaksas',
            psyBox('#15803d', '➕ Ko iegūsti (peļņa)', earns.slice(0, 2).map(e => `• ${e}`).join('<br>') || '—')
            + psyBox('#b91c1c', '➖ Uzturēšanas izmaksas (maksā)', costs.slice(0, 2).map(c => `• ${c}`).join('<br>') || '—')
            + psyBox('#86198f', `🎯 Galvenā svira: ${levers.main?.title || '—'}`, levers.main?.text || '—'),
            't3-memo'),
    ], 2);
}

const SVG_TILES = {
    't3-arudha':               { subtitle: 'Iekšējais Es pret publisko tēlu (Arudha Lagna)', fn: buildArudhaSVG },
    's1-persona':              { subtitle: 'K. G. Junga psihes karte — apzinātā ↔ neapzinātā ass', fn: buildJungMandalaSVG },
    't3-motivacija':           { subtitle: 'Motivācijas prioritāte un arhetipu sajaukums', fn: buildMotivationSVG },
    'capacity-anchors':        { subtitle: 'Karjeras struktūras modelēšana un lēmumu dizains (Kapacitāte)', fn: buildCapacitySVG },
    'capacity-decisions':      { subtitle: 'Kognitīvais stils un lēmumu dizaina špikeris (Kapacitāte)', fn: buildDecisionDesignSVG },
    'timing-pillars':          { subtitle: 'Stratēģiska laika plānošana un dzīves fāžu (Timing) noteikšana', fn: buildTimingSVG },
    'relationship-attachment': { subtitle: 'Partnerattiecību un attiecību dinamikas saderība (Sinastrija)', fn: buildRelationshipSVG },
    'existential-dharma':      { subtitle: 'Ikdienas enerģētika, dabas ritmi un garīgais dzīves mērķis', fn: buildExistentialSVG },
    'burnout-stress':          { subtitle: 'Psihosomatika un Izdegšanas Audits', fn: buildBurnoutSVG },
    't3-celvedis':             { subtitle: 'Vadības, pārliecināšanas un uzticības celvedis', fn: buildCommunicationSVG },
    't3-klupsana':             { subtitle: 'Klupšanas akmeņi un uzvedības riski zem spiediena', fn: buildShadowSVG },
    't3-viduspunkti':          { subtitle: 'Krīzes uzvedība un rīcība ekstremālos apstākļos (Midpointi)', fn: buildMidpointsSVG },
    't3-radars':               { subtitle: 'Saskaņas radara kopsavilkums un slāņu sintēze', fn: buildRadarSVG },
    't3-memo':                 { subtitle: 'Vadītāja kopsavilkums un lomu verdikti', fn: buildMemoSVG },
};

// Kartāl mapē uz atbilstošo paneļa ID — nepieciešams, jo daži SVG flīžu IDs (s1-persona,
// capacity-anchors, timing-pillars, relationship-attachment, existential-dharma, burnout-stress)
// ir iekšēji id sekcijās, ko renderē tab_experiment.js (t3-dzila bloka iekšienē).
// Pārpārikšana: ja el nav atrodams kā tiešs #id, mēģinām atrast parent div ar t3- prefix.
function s1FocusOrFallback(id) {
    if (typeof window === 'undefined') return;
    const el = document.getElementById(id);
    if (el) {
        const det = el.closest('details');
        if (det && !det.open) det.open = true;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.transition = 'box-shadow .25s ease';
        el.style.borderRadius = '10px';
        el.style.boxShadow = '0 0 0 3px rgba(124,58,237,0.55)';
        setTimeout(() => { el.style.boxShadow = 'none'; }, 1600);
        return;
    }
    // Fallback: meklē tuvāko t3- prefix konteineru vai t3-dzila
    const fallbacks = { 'capacity-anchors': 't3-dzila', 'timing-pillars': 't3-dzila',
        'relationship-attachment': 't3-dzila', 'existential-dharma': 't3-misija',
        'burnout-stress': 't3-misija', 's1-persona': 't3-dzila' };
    const fbId = fallbacks[id] || 't3-dzila';
    const fb = document.getElementById(fbId);
    if (fb) {
        // Kopš 2026-07-10 mērķi ir sakļauti <details> — atver pirms ritināšanas.
        const det = fb.closest('details');
        if (det && !det.open) det.open = true;
        fb.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    // Vēl mēģi ar __s1Focus ja definēts
    if (window.__s1Focus) window.__s1Focus(id);
}
if (typeof window !== 'undefined') window.__psyFocus = s1FocusOrFallback;

export function renderPsychOverviewMap(profile) {
    // Personas vērtējuma atziņa (arhetips) — pārcelta šurp no 'Personības lietošanas
    // instrukcija' galvenes. Rādās tikai tad, ja memorandam ir aprēķināts arhetips.
    let archetype = null;
    try { archetype = buildInvestorMemo(profile)?.archetype || null; } catch { archetype = null; }
    const archetypeHtml = archetype?.title
        ? `<div class="psymap-archetype"><span class="psymap-archetype-badge" title="Galvenais profila novērtējums">${archetype.title}</span>${archetype.weak ? `<div class="psymap-archetype-note">vāji izteikts — profils līdzsvarots, tuvākais no daudziem tipiem</div>` : ''}</div>`
        : '';
    const tile = (t, color) => {
        const svgTile = SVG_TILES[t.id];
        const focusCall = `window.__psyFocus&&window.__psyFocus('${t.id}')`;
        if (svgTile) {
            const svgHtml = svgTile.fn(profile);
            return `
            <div class="psymap-tile-card"
                 style="--accent:${color};">
                <div class="psymap-card-header">
                    <div class="psymap-card-header-left">
                        <span class="psymap-ico" style="background:${color}12;">${GLYPH[t.g](color)}</span>
                        <span class="psymap-txt">
                            <span class="psymap-title">${t.title}</span>
                            <span class="psymap-card-subtitle">${svgTile.subtitle}</span>
                        </span>
                    </div>
                </div>
                <div class="psymap-svg-container">
                    ${svgHtml}
                </div>
            </div>`;
        }

        return `
        <div class="psymap-tile"
             onclick="${focusCall}"
             role="button" tabindex="0"
             style="--accent:${color}; border-top:3px solid ${color};"
             aria-label="Pāriet uz: ${t.title}">
            <span class="psymap-ico" style="background:${color}12;">${GLYPH[t.g](color)}</span>
            <span class="psymap-txt">
                <span class="psymap-title">${t.title}</span>
                <span class="psymap-essence">${t.essence}</span>
            </span>
            <span class="psymap-arrow" style="color:${color};">&#x2192;</span>
        </div>`;
    };

    // ── Bloki sagrupēti pa tēmām (5 klasteri) ──────────────────────────────────
    // Katrs klasteris = viena vesela kartiņa, kas 2-kolonnu masonry plūsmā NETIEK
    // sadalīta (break-inside:avoid) → tēmas paliek satuvinātas. Flīzes klasterī stāv
    // viena zem otras (bez fiksētām rindām) → kolonnas aizpildās blīvi, bez tukšiem
    // laukumiem. Visas flīzes ir vienā kolonnas platumā → identisks SVG mērogs
    // (visiem viewBox platums 780) → VIENĀDS fonta izmērs visiem blokiem.
    const clustersHtml = CLUSTERS.map(cl => {
        const tilesHtml = cl.tiles.map(t => tile(t, cl.color)).join('');
        return `
        <section class="psymap-cluster" style="--cluster:${cl.color};">
            <div class="psymap-cluster-head">
                <span class="psymap-cluster-label">${cl.label}</span>
            </div>
            <div class="psymap-cluster-body">${tilesHtml}</div>
        </section>`;
    }).join('');

    return `
    <style>
        /* ── 'Psiholoģiskā kopaina' — tēmu kartiņas 2-kolonnu masonry izkārtojumā ── */
        .psymap-wrap{
            background:#fff;
            border-radius:16px;
            border-left:5px solid #7c3aed;
            box-shadow:0 2px 10px rgba(124,58,237,0.08);
            padding:1.4rem 1.6rem;
        }
        .psymap-head{display:flex;align-items:baseline;gap:0.7rem;flex-wrap:wrap;margin-bottom:0.2rem;}
        .psymap-head h2{margin:0;font-size:1.2rem;font-weight:900;color:#1e293b;letter-spacing:-0.3px;}
        .psymap-sub{color:#64748b;font-size:0.86rem;line-height:1.55;margin:0.3rem 0 1.2rem;max-width:760px;}

        /* Personas vērtējuma atziņa (arhetips) — centrēts paneļa augšā */
        .psymap-archetype{display:flex;flex-direction:column;align-items:center;justify-content:center;margin:0.2rem 0 1.2rem;}
        .psymap-archetype-badge{font-size:2.2rem;font-weight:900;line-height:1.2;color:#5b21b6;background:#ede9fe;border:1.5px solid #c4b5fd;border-radius:16px;padding:8px 28px;text-align:center;}
        .psymap-archetype-note{font-size:0.72rem;color:#94a3b8;margin-top:5px;font-style:italic;text-align:center;}

        /* 2 kolonnas; katrs klasteris paliek vesels un plūst tuvākajā kolonnā (masonry) */
        .psymap-cols{column-count:2;column-gap:1.1rem;}
        @media(max-width:820px){ .psymap-cols{column-count:1;} }

        .psymap-cluster{
            break-inside:avoid;-webkit-column-break-inside:avoid;page-break-inside:avoid;
            background:#fff;
            border:1px solid #e8eaf0;
            border-top:3px solid var(--cluster);
            border-radius:12px;
            overflow:hidden;
            margin:0 0 1.1rem;
        }
        .psymap-cluster-head{
            padding:0.5rem 1rem;
            background:#f8fafc;
            border-bottom:1px solid #eef1f6;
        }
        .psymap-cluster-label{
            font-size:0.72rem;font-weight:900;letter-spacing:0.8px;text-transform:uppercase;color:var(--cluster);
        }
        .psymap-cluster-body{display:flex;flex-direction:column;}
        .psymap-cluster-body > * + *{border-top:1px solid #eef1f6;}

        /* SVG flīze — pilns kolonnas platums → identisks mērogs visiem → vienāds fonts */
        .psymap-tile-card{display:flex;flex-direction:column;background:#fff;padding:0.7rem 0.95rem 0.85rem;}
        .psymap-card-header{display:flex;align-items:center;gap:0.65rem;width:100%;margin-bottom:0.25rem;}
        .psymap-card-header-left{display:flex;align-items:center;gap:0.65rem;min-width:0;flex:1;}
        .psymap-ico{flex-shrink:0;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;}
        .psymap-txt{display:flex;flex-direction:column;min-width:0;flex:1;}
        .psymap-title{font-size:0.92rem;font-weight:800;color:#1e293b;line-height:1.25;}
        .psymap-card-subtitle{font-size:0.72rem;color:#94a3b8;line-height:1.3;margin-top:1px;display:block;}

        /* SVG aizpilda platumu bez max-height/centrēšanas → bez tukšām malām/joslām */
        .psymap-svg-container{width:100%;margin-top:0.25rem;overflow:hidden;}
        .psymap-svg-container svg{width:100%!important;height:auto!important;max-height:none!important;margin:0!important;display:block;}

        /* ── HTML teksta-bloki: VIENOTS fonts (bāze = Junga bloks), auto-augstums ── */
        .psy-cols{display:grid;gap:0.55rem;width:100%;font-family:'Outfit',sans-serif;margin-top:0.25rem;}
        .psy-cols-3{grid-template-columns:repeat(3,1fr);}
        .psy-cols-2{grid-template-columns:repeat(2,1fr);}
        .psy-cols-1{grid-template-columns:1fr;}
        @media(max-width:600px){.psy-cols-3,.psy-cols-2{grid-template-columns:1fr;}}
        .psy-seg{display:flex;gap:3px;margin:4px 0 2px;}
        .psy-seg span{flex:1;height:9px;border-radius:3px;}
        .psy-seg-labels{display:flex;justify-content:space-between;font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.3px;color:#94a3b8;margin-bottom:2px;}
        .psy-col{background:#f8fafc;border:1px solid #e8eaf0;border-radius:11px;padding:0.55rem 0.6rem;display:flex;flex-direction:column;gap:0.42rem;}
        .psy-collabel{font-size:0.62rem;font-weight:800;letter-spacing:0.5px;text-transform:uppercase;color:#94a3b8;}
        .psy-chip{border:2px solid var(--c);border-radius:9px;padding:0.4rem 0.55rem;}
        .psy-chip-row{display:flex;align-items:baseline;justify-content:space-between;gap:0.4rem;}
        .psy-chip-title{font-size:0.92rem;font-weight:800;color:var(--c);line-height:1.2;}
        .psy-chip-score{font-size:1.0rem;font-weight:900;color:var(--c);white-space:nowrap;}
        .psy-chip-sub{font-size:0.62rem;font-weight:800;letter-spacing:0.4px;text-transform:uppercase;color:#64748b;margin-top:2px;}
        .psy-lede{font-size:0.8rem;color:#64748b;font-style:italic;line-height:1.4;}
        .psy-note{font-size:0.82rem;color:#334155;line-height:1.42;}
        .psy-note b{color:#1e293b;}
        .psy-note-box{background:#fff;border-left:3px solid #cbd5e1;padding:5px 8px;border-radius:0 6px 6px 0;}
        .psy-bar{margin:1px 0;}
        .psy-bar-top{display:flex;justify-content:space-between;gap:0.4rem;font-size:0.8rem;font-weight:700;color:#1e293b;margin-bottom:3px;}
        .psy-bar-pct{font-weight:900;}
        .psy-bar-track{height:7px;background:#e2e8f0;border-radius:4px;overflow:hidden;}
        .psy-bar-fill{height:100%;border-radius:4px;}
        .psy-box{border:1px solid;border-radius:8px;padding:6px 9px;font-size:0.82rem;color:#334155;line-height:1.42;}
        .psy-box-title{font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:3px;}
        .psy-row{display:flex;justify-content:space-between;align-items:baseline;gap:0.5rem;}
        .psy-do,.psy-dont{font-size:0.8rem;color:#334155;line-height:1.35;}
        .psy-do b{color:#16a34a;} .psy-dont b{color:#dc2626;}
        .psy-derail-name{font-size:0.85rem;font-weight:800;color:#1e293b;margin-bottom:2px;}
        .psy-derail-name::before{content:"";display:inline-block;width:6px;height:6px;border-radius:50%;background:#b91c1c;margin-right:6px;vertical-align:middle;}
        .psy-mp-name{font-size:0.82rem;font-weight:800;color:#1e293b;}
        .psy-mp-sign{font-size:0.8rem;font-weight:800;color:#7c3aed;white-space:nowrap;}
        .psy-verdict{background:#fff;border:1px solid #e2e8f0;border-left:4px solid #cbd5e1;border-radius:8px;padding:6px 9px;}
        .psy-verdict-label{font-size:0.84rem;font-weight:800;color:#1e293b;}
        .psy-verdict-badge{font-size:0.66rem;font-weight:900;padding:1px 6px;border-radius:4px;white-space:nowrap;}
        .psy-timeline{display:flex;gap:5px;}
        .psy-tl-cell{flex:1;min-width:0;border:1px solid #e2e8f0;border-radius:9px;padding:7px 4px 6px;text-align:center;position:relative;}
        .psy-tl-tag{position:absolute;top:-7px;left:50%;transform:translateX(-50%);font-size:0.5rem;font-weight:900;color:#fff;padding:1px 6px;border-radius:4px;white-space:nowrap;letter-spacing:0.3px;}
        .psy-tl-age{font-size:0.62rem;font-weight:800;color:#64748b;margin-bottom:2px;}
        .psy-tl-label{font-size:0.78rem;font-weight:800;line-height:1.2;}
        .psy-s1s2{height:12px;border-radius:6px;background:#3b82f6;overflow:hidden;margin:1px 0 2px;}
        .psy-s1s2-fill{height:100%;background:#f59e0b;}
        .psy-gauge{height:10px;border-radius:5px;background:linear-gradient(90deg,#16a34a,#facc15,#dc2626);position:relative;margin:4px 0 5px;}
        .psy-gauge-caret{position:absolute;top:-3px;width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-top:7px solid #1e293b;transform:translateX(-50%);}
        .psy-cyn-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;}
        .psy-cyn{border:1px solid #e2e8f0;border-radius:7px;padding:5px 4px;text-align:center;}
        .psy-cyn-name{font-size:0.68rem;font-weight:800;line-height:1.15;}
        .psy-cyn-pct{font-size:0.84rem;font-weight:900;margin-top:1px;}

        /* Teksta fallback flīze (ja konkrētai sadaļai nav SVG) */
        .psymap-tile{display:flex;align-items:flex-start;gap:0.7rem;text-align:left;width:100%;background:#fff;padding:0.7rem 0.95rem;cursor:pointer;font-family:inherit;transition:background .13s ease;border:none;}
        .psymap-tile:hover{background:#f8fafc;}
        .psymap-essence{font-size:0.78rem;color:#64748b;line-height:1.35;margin-top:2px;}
        .psymap-arrow{flex-shrink:0;font-weight:900;opacity:0.3;font-size:1rem;}
    </style>
    <div class="psymap-wrap">
        <div class="psymap-head">
            <span style="font-size:1.5rem;">🧠</span>
            <h2>Psiholoģiskā kopaina</h2>
            <span style="font-size:0.74rem;font-weight:800;color:#7c3aed;background:#7c3aed14;border-radius:6px;padding:3px 9px;letter-spacing:0.5px;">satura karte</span>
        </div>
        ${archetypeHtml}
        <p class="psymap-sub">Vienas personas psiholoģija no visām pusēm — katrā blokā galvenais secinājums par šo cilvēku, lai aina kļūtu skaidra arī neatverot izvērstos paneļus. Klikšķini uz bloka, lai dotos dziļāk.</p>
        <div class="psymap-cols">
            ${clustersHtml}
        </div>
    </div>`;
}
