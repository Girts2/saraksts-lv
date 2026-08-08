// opts.sections: kuras sadaļas renderēt (['1'..'7']); opts.showExec: rādīt Personības instrukciju;
// opts.showHeader: rādīt "Premium audita matrica" virsrakstu. Noklusējums = pilns audits.

import { signDistBar, houseDistBar, timeUnknownBanner } from "../components/dist_bar.js?v=3";
import { confidenceBadge, precisionMeta } from "../components/confidence.js?v=2";
import { maskSynthesisBlock, archetypeSynthesisBlock } from "../components/mask_synthesis_ui.js?v=7";
import { computeMaskSynthesis, computeArchetypeSynthesis, ELEMENT_META, elementPersonaText } from "../../logic/mask_synthesis.js?v=6";

// SVG teksta iekļaušana joslā: ja aptuvenais platums pārsniedz maxPx, pievieno textLength
// saspiešanu (spacingAndGlyphs), lai garie dinamiskie nosaukumi (piem. "Uzņēmējdarbība un
// radīšana") nepārklātos ar % skaitli / neizietu ārpus bloka. Aptuvenais platums Outfit bold:
// burts ≈ 0.62×fontPx, emoji (2 UTF-16 vienības) ≈ 1.15×fontPx.
const svgFit = (str, maxPx, fontPx) => {
    const s = String(str || '');
    const emoji = (s.match(/\p{Extended_Pictographic}/gu) || []).length;
    const est = emoji * fontPx * 1.15 + Math.max(0, s.length - emoji * 2) * fontPx * 0.62;
    return est > maxPx ? ` textLength="${maxPx}" lengthAdjust="spacingAndGlyphs"` : '';
};

// Sadala virkni divās pēc garuma līdzsvarotās rindās pie vārda robežas — gariem
// dinamiskiem virsrakstiem SVG rāmjos (dharma/enkuru/piesaistes nosaukumi), kur
// viena rinda ar textLength saspiešanu kļūtu nesalasāma.
const svgSplit2 = (str) => {
    const words = String(str || '').trim().split(/\s+/);
    if (words.length < 2) return [str, ''];
    let best = 1, bestDiff = Infinity;
    for (let i = 1; i < words.length; i++) {
        const diff = Math.abs(words.slice(0, i).join(' ').length - words.slice(i).join(' ').length);
        if (diff < bestDiff) { bestDiff = diff; best = i; }
    }
    return [words.slice(0, best).join(' '), words.slice(best).join(' ')];
};

// Ierāmēta virsraksta bloks (rāmis x..x+212, teksts iekšpusē): īss nosaukums = viena
// rinda (y=75) + apakšvirsraksts (y=96); garš = divas rindas (y=70/88) + apakšvirsraksts
// zemāk (y=112, joprojām rāmī, kas beidzas y=130). maxLine1 ļauj 1. rindai atstāt vietu
// labajā pusē novietotam % skaitlim; maxLine2 = pilnais rāmja platums.
const svgFramedTitle = (x, name, sub, fontPx, fill, maxLine1, maxLine2) => {
    const oneLineFits = !svgFit(name, maxLine1, fontPx);
    if (oneLineFits) return `
                    <text x="${x}" y="75" font-size="${fontPx}" font-weight="900" fill="${fill}" font-family="'Outfit',sans-serif">${name}</text>
                    <text x="${x}" y="96" font-size="11" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">${sub}</text>`;
    const [l1, l2] = svgSplit2(name);
    const f = Math.min(fontPx, 13.5);
    return `
                    <text x="${x}" y="70" font-size="${f}" font-weight="900" fill="${fill}" font-family="'Outfit',sans-serif"${svgFit(l1, maxLine1, f)}>${l1}</text>
                    <text x="${x}" y="88" font-size="${f}" font-weight="900" fill="${fill}" font-family="'Outfit',sans-serif"${svgFit(l2, maxLine2, f)}>${l2}</text>
                    <text x="${x}" y="112" font-size="11" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">${sub}</text>`;
};

// s1 Junga mandalas mezglu klikšķis → aizritina un īslaicīgi izceļ attiecīgo teksta bloku
// (orientācija augšā, dziļums tekstā zemāk). Definēts vienreiz, kā hacker_panel __hkSetMode.
if (typeof window !== 'undefined' && !window.__s1Focus) {
    window.__s1Focus = function (id) {
        const el = document.getElementById(id);
        if (!el) return;
        // Ja bloks ir sakļautā <details> (padziļinātais apraksts) — atver to vispirms
        const det = el.closest('details');
        if (det && !det.open) det.open = true;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.transition = 'box-shadow .25s ease';
        el.style.borderRadius = '10px';
        el.style.boxShadow = '0 0 0 3px rgba(124,58,237,0.55)';
        setTimeout(() => { el.style.boxShadow = 'none'; }, 1500);
    };
}

export function renderTabExperiment(profile, opts = {}) {

    // ── ĶELTU KOKU KALKULATORS ────────────────────────────────────────────────
    function getCelticTree(dateStr) {
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

    // ── PALĪG-FUNKCIJAS ───────────────────────────────────────────────────────
    // GAIŠĀ TĒMA (2026-06-11): datu failu akcentu krāsas ir gaišas (domātas tumšajam fonam) —
    // ink() pārvērš tās AA-tumšās TEKSTA versijās uz balta (ceļveža colorText mācība).
    // Joslu/rāmju aizpildiem oriģinālā krāsa paliek; nezināmas krāsas atgriež nemainītas.
    // UZMANĪBU: atslēgas šeit ir DATU failu oriģinālās (gaišās) krāsas — tās NEDRĪKST
    // pārrakstīt ar failā veiktajām globālajām krāsu nomaiņām (atslēgu avots ir logic/*.js).
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

    // precisionMeta importēts no components/confidence.js (viens avots)

    // confArch — arhetipa atslēga vai descriptors aprēķinātajai kartes ticamībai.
    const card = (num, title, precisionKey, systems, body, confArch = null, confOpts = {}) => {
        const m = precisionMeta[precisionKey] || { color: '#64748b', stars: '?', bg: '#f8fafc' };
        const sysBadges = systems.map(s =>
            `<span style="display:inline-block; background:${m.color}18; color:${ink(m.color)}; border:1px solid ${m.color}40; border-radius:6px; padding:2px 10px; font-size:0.78rem; font-weight:700; margin:2px 4px 2px 0;">${s}</span>`
        ).join('');
        const confHtml = confArch ? confidenceBadge(confArch, profile, confOpts) : '';
        return `
        <div style="background:#fff; border-radius:16px; margin-bottom:1.5rem; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
            <div style="background:${m.bg}; border-left:5px solid ${m.color}; padding:1.4rem 1.8rem;">
                <div style="display:flex; align-items:flex-start; gap:1rem; flex-wrap:wrap;">
                    <span style="background:${m.color}; color:#fff; min-width:2.2rem; height:2.2rem; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:1rem; flex-shrink:0; margin-top:2px;">${num}</span>
                    <div style="flex:1; min-width:0;">
                        <h2 style="color:#1e293b; font-size:1.1rem; font-weight:800; margin:0 0 0.5rem 0; line-height:1.35;">${title}</h2>
                        <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                            ${sysBadges}
                        </div>
                        ${confHtml}
                    </div>
                </div>
            </div>
            <div style="padding:1.6rem 1.8rem; color:#334155; font-size:0.93rem; line-height:1.78;">
                ${body}
            </div>
        </div>`;
    };

    const q = (text) =>
        `<div style="background:#f8fafc; border-left:3px solid #cbd5e1; border-radius:0 8px 8px 0; padding:0.7rem 1rem; margin:0.6rem 0; font-style:italic; color:#64748b; font-size:0.9rem;">${text}</div>`;

    const warn = (text) =>
        `<div style="background:#fef2f2; border:1px solid #b91c1c40; border-radius:10px; padding:1rem 1.2rem; margin-top:1rem; color:#b91c1c; font-size:0.9rem;">${text}</div>`;

    const sectionTitle = (label, color = '#64748b') => {
        const c = ink(color);
        return `<div style="font-size:0.72rem; font-weight:800; color:${c}; text-transform:uppercase; letter-spacing:2px; margin:1.4rem 0 0.75rem 0; display:flex; align-items:center; gap:0.5rem;"><span style="flex:1; height:1px; background:${c}30;"></span>${label}<span style="flex:1; height:1px; background:${c}30;"></span></div>`;
    };

    // ── Distribūcijas josla (pilns spektrs ar primāro highlight) ─────────────
    // Items: array of { key, label, color, score, icon? }
    // Lieto, lai HR speciālists redz pilnu skoru spektru, ne tikai dominanto
    const distributionBar = (items, primaryKey = null, options = {}) => {
        const title = options.title || null;
        const desc  = options.desc  || null;
        const sorted = [...items].sort((a, b) => (b.score ?? 0) - (a.score ?? 0));
        const rows = sorted.map(it => {
            const isPrim = it.key === primaryKey;
            const score = Math.max(0, Math.min(100, it.score ?? 0));
            return `
                <div style="background:#f8fafc; border-left:${isPrim ? '4px' : '2px'} solid ${it.color}${isPrim ? 'cc' : '55'}; border-radius:6px; padding:0.55rem 0.85rem; margin-bottom:4px; ${isPrim ? '' : 'opacity:0.78;'}">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.3rem;">
                        <span style="font-weight:${isPrim ? '800' : '600'}; color:${ink(it.color)}; font-size:0.85rem;">${it.icon || ''} ${it.label}${isPrim ? ` <span style="background:${it.color}25; color:${ink(it.color)}; border-radius:4px; padding:1px 6px; font-size:0.6rem; font-weight:800; letter-spacing:0.5px; margin-left:5px;">PRIMĀRS</span>` : ''}</span>
                        <span style="color:${ink(it.color)}; font-weight:700; font-size:0.82rem; font-variant-numeric:tabular-nums;">${score}%</span>
                    </div>
                    <div style="background:#f1f5f9; border-radius:3px; height:5px; overflow:hidden;">
                        <div style="height:100%; width:${score}%; background:${it.color}; transition:width 0.4s;"></div>
                    </div>
                </div>
            `;
        }).join('');
        const header = title ? `<div style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:0.5rem;">${title}</div>` : '';
        const subdesc = desc ? `<div style="color:#64748b; font-size:0.78rem; font-style:italic; margin-bottom:0.5rem;">${desc}</div>` : '';
        return `${header}${subdesc}<div style="display:flex; flex-direction:column;">${rows}</div>`;
    };

    // ── 1: PSIHOLOĢISKĀ PAŠIZPRATNE (PROFILA DATI) ───────────────────────────
    const wp   = profile?.western?.psychology  || {};
    const hyb  = profile?.hybrid_intelligence  || {};
    const tr   = profile?.western?.transits    || [];
    const progMoon   = profile?.progressions?.moon_prog || '—';
    const birthDate  = profile?.birth_info?.date || '';
    const celticTree = getCelticTree(birthDate);

    // Vēdu psiholoģija — Junga arhetipa kartējums
    const vPsych = profile?.vedic?.psychology || {};

    // Attiecību piesaistes stils: loveLanguage + emotionalNeeds
    const attachStyle = [
        wp.loveLanguage || null,
        wp.emotionalNeeds ? `<span style="color:#64748b; font-size:0.85rem;">Emocionālā drošība: </span>${wp.emotionalNeeds}` : null,
    ].filter(Boolean).join('<br>');

    // Junga arhetipa karte — Vēdu Ascendanta 3 slāņi + Rietumu Ego struktūra.
    // Kad laiks nezināms, gan Vēdu socialMask, gan Rietumu Ego balstās uz PIEŅEMTU
    // Ascendentu (viena viltus zīme) → tas runātu pretī sintezētajai maskai zemāk.
    // Tāpēc tad masku aprakstu veidojam no sintezētās dominējošās stihijas (saskaņoti).
    const maskSynth = computeMaskSynthesis(profile);
    const jungPersona = maskSynth.applicable
        ? elementPersonaText(maskSynth.topElement, {
            note: `Bez precīza dzimšanas laika maska dota pa dominējošo stihiju (${maskSynth.topElement}), ne vienu zīmi — sk. aprēķinu zemāk.`,
        })
        : [
            vPsych.socialMask || null,
            wp.socialMask ? `<span style="color:#64748b; font-size:0.85rem;">Rietumu Ego struktūra: </span>${wp.socialMask}` : null,
        ].filter(Boolean).join('<br>');

    // Pretrunas detekcija: izmanto sidereal indeksu (0-11), nevis lokalizētu tekstu
    // Apspiešanas zīmes: Jaunava=5, Skorpions=7, Mežāzis=9, Ūdensvīrs=10
    const moonSignIdx = profile?.vedic?.moonSignIdx ?? -1;
    const moonIsSuppressive = [5, 7, 9, 10].includes(moonSignIdx);
    const attachHasSecurity = /mājas|drošīb|nostalģij/i.test(wp.loveLanguage || '') ||
                              /mājas|drošīb|nostalģij/i.test(wp.emotionalNeeds || '');
    const contradictionNote = (moonIsSuppressive && attachHasSecurity)
        ? `<div style="margin-top:0.7rem; background:#eff6ff; border-radius:8px; padding:0.75rem 0.9rem; border-left:3px solid #0369a1;"><div style="font-size:0.7rem; font-weight:800; color:#0369a1; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Polārā pretruna — Junga integrācijas zona</div><div style="color:#64748b; font-size:0.87rem; line-height:1.6;">Lai gan Mēness zīme norāda uz emocionālu kontroli un aprēķinu, piesaistes stilā parādās spēcīga nepieciešamība pēc drošības un piederības. Šī polaritāte ir klasiska Ēnas dinamika — apspiestas emocionālas vajadzības darbojas kā zemapziņas kompensācijas mehānisms.</div></div>`
        : null;

    const jungAnima = [
        vPsych.emotionalBase,
        vPsych.animaProjection ? `<span style="color:#64748b; font-size:0.85rem;">Projekcija uz partneri: </span>${vPsych.animaProjection}` : null,
        attachStyle ? `<span style="color:#64748b; font-size:0.85rem;">Piesaistes stils: </span>${attachStyle}` : null,
        contradictionNote,
    ].filter(Boolean).join('<br>');
    const jungSelf = [
        vPsych.egoStructure || null,
        vPsych.coreTalents  ? `<span style="color:#64748b; font-size:0.85rem;">Dziļākais dvēseles dzinējspēks: </span>${vPsych.coreTalents}` : null,
        vPsych.heroPath     ? `<span style="color:#b45309; font-size:0.85rem;">Varoņa ceļš — dzīves pārbaudījums: </span>${vPsych.heroPath}` : null,
        vPsych.selfRealization ? `<span style="color:#4d7c0f; font-size:0.85rem;">Individuācijas mērķis: </span>${vPsych.selfRealization}` : null,
    ].filter(Boolean).join('<br>') || '—';
    const jungShadowRahu = [
        vPsych.hiddenAmbitions || null,
        vPsych.rahuRisk        ? `<span style="color:#b91c1c; font-size:0.85rem;">Destruktīvais risks: </span>${vPsych.rahuRisk}` : null,
        vPsych.rahuIntegration ? `<span style="color:#4d7c0f; font-size:0.85rem;">Ēnas integrācija: </span>${vPsych.rahuIntegration}` : null,
    ].filter(Boolean).join('<br>') || '—';
    const jungShadowKetu = [
        vPsych.ketuTrap         ? `<span style="color:#64748b; font-size:0.85rem;">Komforta zona — Ketu regress: </span>${vPsych.ketuTrap}` : null,
        vPsych.karmicFootprints || null,
        vPsych.ketuTalent       ? `<span style="color:#4d7c0f; font-size:0.85rem;">Karmiskais resurss: </span>${vPsych.ketuTalent}` : null,
    ].filter(Boolean).join('<br>') || '—';

    // criticalAspectsBlocks ir objektu masīvs — iegūstam .profile tekstu no katra objekta
    const critBlocksText = Array.isArray(wp.criticalAspectsBlocks) && wp.criticalAspectsBlocks.length
        ? wp.criticalAspectsBlocks.map(c => c?.profile || '').filter(Boolean).join(' ')
        : null;

    const jungShadowLilith = [
        wp.blackmailPoints ? `<span style="color:#6d28d9; font-size:0.85rem;">Instinktīvā dziņa un tabu (Lilita): </span>${wp.blackmailPoints}` : null,
        critBlocksText ? `<span style="color:#64748b; font-size:0.85rem;">Iekšējais karš — pašsabotāža: </span>${critBlocksText}` : null,
        wp.innerConflicts ? `<span style="color:#64748b; font-size:0.85rem;">Konfliktu dinamika: </span>${wp.innerConflicts}` : null,
        wp.trauma ? `<span style="color:#b91c1c; font-size:0.85rem;">Hirona trauma: </span>${wp.trauma}` : null,
        hyb.jungVulnerability ? `<span style="color:#b91c1c; font-size:0.85rem;">Ievainojamības punkts: </span>${hyb.jungVulnerability}` : null,
    ].filter(Boolean).join('<br>');

    // Tranzītu analīze
    const critTr = tr.filter(t => t.nature === 'critical');
    const harmTr = tr.filter(t => t.nature === 'harmonic');
    let phaseLabel = 'Neitrāls';
    let phaseColor = '#64748b';
    let phaseExpl  = 'Konsolidācijas un pārskatīšanas periods — nav izteiktu spiediena vai atbalsta viļņu.';
    if (critTr.length > 0 && critTr.length > harmTr.length) {
        phaseLabel = 'Saspringts';
        phaseColor = '#b91c1c';
        phaseExpl  = `${critTr.length} kritiski tranzīti rada saspiedumu un iekšējas spriedzi. Šis ir periods, kurā cilvēks var justies iestrēdzis — taču tieši spriedze noved pie izaugsmes.`;
    } else if (harmTr.length > 0) {
        phaseLabel = 'Atvērts';
        phaseColor = '#15803d';
        phaseExpl  = `${harmTr.length} harmoniski tranzīti rada atbalstošu fonu. Cilvēks šajā periodā jūtas radoši atvērts — piemērots laiks iniciatīvai un sadarbībai.`;
    }

    const trChips = tr.slice(0, 8).map(t => {
        const col = t.nature === 'critical' ? '#b91c1c' : (t.nature === 'harmonic' ? '#047857' : '#1d4ed8');
        return `<span style="display:inline-flex; align-items:center; gap:3px; background:#f8fafc; border:1px solid ${col}35; border-radius:6px; padding:3px 9px; font-size:0.79rem; color:${ink(col)}; margin:2px;">${t.transitingPlanet} ${t.aspect} ${t.natalPlanet}</span>`;
    }).join('') || `<span style="color:#475569; font-size:0.85rem;">Šobrīd nav nozīmīgu lēno planētu tranzītu</span>`;

    const cleanText = (s) => (typeof s === 'string' ? s.replace(/\.{2,}/g, '.').replace(/\s+\./g, '.') : s);
    const qBlock = (question, answer, accentColor = '#15803d', subtitle = null, astroDriver = null) => `
        <div style="background:#f8fafc; border-radius:10px; padding:1.1rem 1.3rem; margin-bottom:0.6rem; border-left:3px solid ${accentColor}50;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:0.6rem; flex-wrap:wrap; margin-bottom:${subtitle ? '0.25rem' : '0.5rem'};">
                <div style="font-size:0.85rem; font-weight:800; color:${ink(accentColor)}; letter-spacing:0.3px;">${question}</div>
                ${astroDriver ? `<span style="background:${accentColor}15; color:${ink(accentColor)}; border:1px solid ${accentColor}40; border-radius:6px; padding:2px 9px; font-size:0.66rem; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; font-family:ui-monospace, monospace;">⚙ ${astroDriver}</span>` : ''}
            </div>
            ${subtitle ? `<div style="font-size:0.78rem; color:#64748b; font-style:italic; margin-bottom:0.5rem;">${subtitle}</div>` : ''}
            <div style="color:#1e293b; font-size:0.92rem; line-height:1.65;">${cleanText(answer) || '<span style="color:#475569;">Dati nav pieejami</span>'}</div>
        </div>`;

    // Avīžu kolonnu izkārtojums (2026-06-13): saturs plūst 3 vienādās kolonnās; katra sekcija
    // paliek vesela ar break-inside:avoid. Sekciju virsraksts salikts kopā ar tā pirmo bloku,
    // lai kolonnas robežā virsraksts nepaliek bez satura. Ievads = pilna platuma "lead" virs kolonnām.
    const col = (html, id) => `<div ${id ? `id="${id}"` : ''} style="break-inside:avoid; -webkit-column-break-inside:avoid; page-break-inside:avoid; margin:0 0 0.9rem 0;">${html}</div>`;

    const celticBlock = celticTree ? `
        <div style="background:${celticTree.color}14; border:1px solid ${celticTree.color}35; border-radius:12px; padding:1.2rem 1.4rem;">
            <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; margin-bottom:0.85rem;">
                <span style="background:${celticTree.color}; color:#fff; border-radius:10px; padding:6px 16px; font-size:1rem; font-weight:900;">🌳 ${celticTree.name}</span>
                <span style="background:${celticTree.color}22; color:${ink(celticTree.color)}; border:1px solid ${celticTree.color}50; border-radius:20px; padding:4px 14px; font-size:0.85rem; font-weight:700;">${celticTree.type}</span>
                <span style="color:#64748b; font-size:0.82rem;">${celticTree.en}</span>
            </div>
            <p style="margin:0; color:#334155; font-size:0.92rem; line-height:1.7;">${celticTree.traits}</p>
            ${celticTree.shadow ? `<div style="margin-top:0.8rem; padding-top:0.8rem; border-top:1px solid ${celticTree.color}25;">
                <div style="font-size:0.7rem; font-weight:800; color:#b91c1c; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">ĒNA — Junga Ēnas arhetips šim kokam</div>
                <p style="margin:0; color:#64748b; font-size:0.88rem; line-height:1.65;">${celticTree.shadow}</p>
            </div>` : ''}
        </div>` : `<div style="color:#475569; font-size:0.88rem;">Dzimšanas datums nav pieejams</div>`;

    const transitBlock = `
        <div style="background:#f8fafc; border-radius:12px; padding:1.2rem 1.4rem;">
            <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                <span style="font-size:0.75rem; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Pašreizējais periods:</span>
                <span style="background:${phaseColor}22; color:${ink(phaseColor)}; border:1px solid ${phaseColor}40; border-radius:8px; padding:2px 12px; font-size:0.85rem; font-weight:800;">${phaseLabel}</span>
            </div>
            <p style="margin:0 0 0.9rem 0; color:#64748b; font-size:0.9rem; line-height:1.65;">${phaseExpl}</p>
            <div style="margin-bottom:0.85rem; flex-wrap:wrap; display:flex; gap:2px;">${trChips}</div>
            <div style="border-top:1px solid #e2e8f0; padding-top:0.75rem; font-size:0.88rem;">
                <span style="color:#64748b;">Progresīvais Mēness:</span>
                <b style="color:#1e293b; margin-left:6px;">${progMoon}</b>
                <span style="color:#64748b; font-size:0.83rem; margin-left:6px;">— norāda uz dominējošo emocionālo tēmu šajā dzīves posmā</span>
            </div>
        </div>`;

    // ── Junga kvaternitātes mandala — orientācijas vizuāls ar tekstu virs sīkā apraksta ──
    // Apzinātais–neapzinātais ass (vertikāle): Persona augšā ↔ Ēna apakšā (pretpoli pēc Junga).
    // Klikšķis uz bloka → window.__s1Focus aizritina un izceļ attiecīgo aprakstu zemāk.
    // Katrs bloks: arhetipa NOSAUKUMS + vispārīgā NOZĪME + šī cilvēka RAKSTUROJUMS (clip() no profila).

    // Saīsina garu (HTML) tekstu uz veseliem teikumiem mandalas blokam (plain valodā).
    const clip = (html, maxChars) => {
        const plain = String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        if (plain.length <= maxChars) return plain;
        const cut = plain.slice(0, maxChars);
        const dot = cut.lastIndexOf('.');
        if (dot > maxChars * 0.55) return cut.slice(0, dot + 1);
        return cut.slice(0, cut.lastIndexOf(' ')).trim() + '…';
    };
    const firstSentence = (html) => {
        const plain = String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        const m = plain.match(/^[^.]*\./);
        return m ? m[0].trim() : plain.slice(0, 160);
    };
    const impressionLine = firstSentence(jungPersona);
    // Noņem pirmo <br>-segmentu (virsraksta teikumu, kas jau redzams mandalā) —
    // kolonna rāda tikai PADZIĻINĀJUMU, lai nedublētu mandalu. Ja paliek tukšs → oriģināls.
    const dropLead = (html) => { const s = String(html || ''); const i = s.indexOf('<br>'); const rest = i >= 0 ? s.slice(i + 4).trim() : ''; return rest || s; };

    // Mandalas mezgls ar aplaužamu tekstu (foreignObject): nosaukums + nozīme + raksturojums.
    // Fonti tuvināti pārējās lapas izmēram (~14px korpuss). Bloki attiecīgi lielāki.
    const mNode = (x, y, w, h, accent, label, gloss, desc, targetId) => `
        <g onclick="window.__s1Focus&&window.__s1Focus('${targetId}')" style="cursor:pointer;">
            <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="13" fill="${accent}10" stroke="${accent}55" stroke-width="1.5"/>
            <foreignObject x="${x}" y="${y}" width="${w}" height="${h}">
                <div xmlns="http://www.w3.org/1999/xhtml" style="height:100%; box-sizing:border-box; padding:11px 14px; font-family:'Outfit',sans-serif; overflow:hidden;">
                    <div style="font-size:16.5px; font-weight:800; color:${ink(accent)}; line-height:1.2;">${label}</div>
                    <div style="font-size:10.5px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.3px; margin:2px 0 5px;">${gloss}</div>
                    <div style="font-size:13.5px; color:#334155; line-height:1.45;">${desc}</div>
                </div>
            </foreignObject>
        </g>`;
    const mPill = (x, y, w, h, accent, name, gloss, desc, targetId) => `
        <g onclick="window.__s1Focus&&window.__s1Focus('${targetId}')" style="cursor:pointer;">
            <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="11" fill="${accent}10" stroke="${accent}50" stroke-width="1.5"/>
            <foreignObject x="${x}" y="${y}" width="${w}" height="${h}">
                <div xmlns="http://www.w3.org/1999/xhtml" style="height:100%; box-sizing:border-box; padding:10px 13px; font-family:'Outfit',sans-serif; overflow:hidden;">
                    <div style="font-size:14.5px; font-weight:800; color:${ink(accent)};">${name}</div>
                    <div style="font-size:9.5px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.3px; margin:1px 0 4px;">${gloss}</div>
                    <div style="font-size:13px; color:#334155; line-height:1.4;">${desc}</div>
                </div>
            </foreignObject>
        </g>`;

    const lilithSrc = wp.blackmailPoints || wp.innerConflicts || wp.trauma || hyb.jungVulnerability || vPsych.fearsAndComplexes;

    // Mandalas bloku raksturojumi — viens teikums plain valodā par šo personu.
    // Clip limiti konservatīvi ar rezervi (R2) — lai dažādiem ievaddatiem teksts ietilpst.
    const mPersonaDesc = clip(impressionLine, 135);
    const mAnimaDesc   = clip(vPsych.emotionalBase, 135);
    const mSelfDesc    = clip(`${vPsych.egoStructure || ''} ${vPsych.selfRealization || vPsych.heroPath || ''}`.trim(), 135);
    const mRahuDesc    = clip(vPsych.hiddenAmbitions, 130);
    const mKetuDesc    = clip(vPsych.ketuTrap || vPsych.karmicFootprints, 130);
    const mLilitaDesc  = clip(lilithSrc, 130);
    // Papildu slāņi grafikā: Ķeltu koks (dabiskais raksturs) + Rietumu tranzīti (pašreizējā fāze)
    const celticAccent = celticTree ? celticTree.color : '#4d7c0f';
    const celticLabel  = celticTree ? `🌳 ${celticTree.name}` : '🌳 Ķeltu koks';
    const mCelticDesc  = celticTree ? clip(celticTree.traits, 135) : 'Dzimšanas datums nav pieejams.';
    const mTransitDesc = clip(phaseExpl, 185) + (progMoon && progMoon !== '—' ? ` Progr. Mēness: ${progMoon}.` : '');

    const mandala = `
        <div style="max-width:820px; margin:0.6rem auto 0.2rem;">
            <div style="text-align:center; font-size:0.92rem; color:#475569; font-weight:700; margin-bottom:0.2rem;">🧭 Tava psihes karte pēc K. G. Junga — 4 arhetipi uz apzinātā ↔ neapzinātā ass</div>
            <div style="text-align:center; font-size:0.82rem; color:#94a3b8; margin-bottom:0.25rem;">Katrā blokā: arhetips · ko tas nozīmē · šī cilvēka raksturojums. Labajā pusē un apakšā — papildu slāņi.</div>
            <svg viewBox="0 0 780 894" width="100%" style="font-family:'Outfit',sans-serif; display:block;">
                <!-- Jung ass (Persona↔Patība↔Ēna) un Anima — nepārtrauktas līnijas -->
                <line x1="390" y1="208" x2="390" y2="268" stroke="#cbd5e1" stroke-width="2"/>
                <line x1="390" y1="436" x2="390" y2="486" stroke="#cbd5e1" stroke-width="2"/>
                <line x1="248" y1="352" x2="274" y2="352" stroke="#cbd5e1" stroke-width="2"/>
                <line x1="390" y1="556" x2="132" y2="576" stroke="#e0b4b4" stroke-width="2"/>
                <line x1="390" y1="556" x2="390" y2="576" stroke="#e0b4b4" stroke-width="2"/>
                <line x1="390" y1="556" x2="648" y2="576" stroke="#e0b4b4" stroke-width="2"/>
                <text x="390" y="24" text-anchor="middle" font-size="12" fill="#94a3b8">↑ apzinātais — sociāli redzamais</text>
                <text x="390" y="750" text-anchor="middle" font-size="12" fill="#94a3b8">↓ neapzinātais — apspiestais, instinkti</text>
                ${mNode(274, 40, 232, 168, '#1d4ed8', '🎭 Persona', 'Kā mani redz citi', mPersonaDesc, 's1-persona')}
                ${mNode(16, 268, 232, 168, '#7e22ce', '🌙 Anima / Animus', 'Iekšējā jūtu pasaule', mAnimaDesc, 's1-anima')}
                ${mNode(274, 268, 232, 168, '#b45309', '☀ Patība', 'Apzinātais Es · centrs', mSelfDesc, 's1-self')}
                ${mNode(532, 268, 232, 168, celticAccent, celticLabel, 'Dabiskais raksturs · ķeltu koks', mCelticDesc, 's1-celtic')}
                ${mNode(300, 486, 180, 70, '#b91c1c', '🌑 Ēna', 'Noliegtā puse — 3 avoti', '', 's1-rahu')}
                ${mPill(16, 576, 232, 160, '#b91c1c', '◉ Rahu', 'slēptais dzinulis', mRahuDesc, 's1-rahu')}
                ${mPill(274, 576, 232, 160, '#64748b', '● Ketu', 'aklās zonas', mKetuDesc, 's1-ketu')}
                ${mPill(532, 576, 232, 160, '#6d28d9', '✦ Lilita', 'pirmatnējās dziņas', mLilitaDesc, 's1-lilita')}
                ${mNode(16, 758, 748, 116, phaseColor, `🌀 Pašreizējā fāze: ${phaseLabel}`, 'Kur cilvēks ir tagad · Rietumu tranzīti', mTransitDesc, 's1-transit')}
            </svg>
            <div style="text-align:center; font-size:0.82rem; color:#94a3b8; margin-top:0.2rem;">Klikšķini uz bloka, lai izceltu tā pilno aprakstu zemāk</div>
        </div>`;

    // 24h sintēze (kad dzimšanas laiks nav zināms) — Persona=Ascendants (stihiju balsojums),
    // pārējiem zīme/stihija nosakāma, joma (māja) — līderis vai godīgi nenosakāma.
    const sweep = profile?.timeSweep || null;
    const vp = sweep?.vedic?.planets || {};
    const wpl = sweep?.western?.planets || {};

    // Palīgs: viens arhetipa sintēzes bloks ar raw 24h joslām <details>.
    const synthArch = (name, o) => {
        if (!sweep) return '';
        const vP = vp[name] || {};
        const raw = (signDistBar(vP.signDist, { color: o.color, title: `${o.signTitle} · 24h sadalījums` }) || '')
                  + houseDistBar(vP.houseDist, { color: o.color, title: `${o.houseTitle} · 24h sadalījums` });
        const synth = computeArchetypeSynthesis({
            signDists: o.cross
                ? [{ dist: vP.signDist, weight: 1.5, label: `${o.label} (Vēdu)` }, { dist: (wpl[name] || {}).signDist, weight: 1.0, label: `${o.label} (Rietumu)` }]
                : [{ dist: vP.signDist, weight: 1.0, label: o.label }],
            houseDist: vP.houseDist,
        });
        return archetypeSynthesisBlock(synth, { color: o.color, icon: o.icon, title: o.title, qualityNoun: o.qualityNoun, domainNoun: o.domainNoun, rawDetailsHtml: raw });
    };

    const sw = {
        banner:  sweep ? timeUnknownBanner() : '',
        // persona: maskSynthesisBlock() (stihiju balsojums); pārējie — synthArch():
        anima:   synthArch('Meness', { color: '#7e22ce', icon: '🌙', title: 'Sintezētā emocionālā daba', label: 'Mēness', signTitle: 'Mēness zīme', houseTitle: 'Mēness māja', qualityNoun: 'emocionālā stihija', domainNoun: 'emocionālā joma', cross: false }),
        self:    synthArch('Saule',  { color: '#b45309', icon: '☀', title: 'Sintezētais apzinātais Es', label: 'Saule', signTitle: 'Saules zīme', houseTitle: 'Saules māja', qualityNoun: 'pamata stihija', domainNoun: 'dzīves joma', cross: false }),
        rahu:    synthArch('Rahu',   { color: '#b91c1c', icon: '◉', title: 'Sintezētais slēptais dzinējs', label: 'Rahu', signTitle: 'Rahu zīme', houseTitle: 'Rahu māja', qualityNoun: 'dziņas stihija', domainNoun: 'izpausmes joma', cross: false }),
        ketu:    synthArch('Ketu',   { color: '#64748b', icon: '●', title: 'Sintezētā aklā zona', label: 'Ketu', signTitle: 'Ketu zīme', houseTitle: 'Ketu māja', qualityNoun: 'instinkta stihija', domainNoun: 'joma', cross: false }),
    };

    // Ticamības rādītāji (per-sadaļa) — aprēķināts kompozīts ar "kā paaugstināt"
    const cf = {
        persona: confidenceBadge('ascendant', profile),
        anima:   confidenceBadge('moon', profile),
        self:    confidenceBadge('mixed-sign-house', profile, { planet: 'Saule' }),
        rahu:    confidenceBadge('mixed-sign-house', profile, { planet: 'Rahu' }),
        ketu:    confidenceBadge('mixed-sign-house', profile, { planet: 'Ketu' }),
        lilita:  confidenceBadge('date-fixed', profile),
    };

    // ── Plain-language kopsavilkums lajam (D1) — bez astroloģijas žargona ──
    // (firstSentence/impressionLine definēti augstāk, pirms mandalas)
    const leadSummary = `
        <div style="background:linear-gradient(135deg,#eef2ff,#f8fafc); border:1px solid #c7d2fe; border-left:4px solid #4f46e5; border-radius:12px; padding:1rem 1.3rem; margin-bottom:1.1rem;">
            <div style="font-size:0.72rem; font-weight:800; color:#4338ca; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">📌 Īsumā — kā šo cilvēku, visticamāk, uztver citi</div>
            <p style="margin:0 0 0.7rem 0; color:#1e293b; font-size:0.98rem; line-height:1.6;"><b>${impressionLine}</b></p>
            <div style="font-size:0.86rem; color:#475569; margin-bottom:4px;">Karte zemāk parāda personību <b>4 slāņos</b> (pēc psihologa K. G. Junga):</div>
            <ul style="margin:0 0 0.6rem 0; padding-left:1.2rem; color:#334155; font-size:0.86rem; line-height:1.7;">
                <li>🎭 <b>Persona</b> — kā cilvēks izskatās uz āru, pirmais iespaids.</li>
                <li>🌙 <b>Anima / Animus</b> — iekšējā jūtu pasaule un kādu partneri neapzināti meklē.</li>
                <li>☀ <b>Patība</b> — apzinātais “es” un dzīves galvenais mērķis.</li>
                <li>🌑 <b>Ēna</b> — noliegtā, slēptā puse, kas parādās stresā.</li>
            </ul>
            <p style="margin:0 0 0.5rem 0; color:#64748b; font-size:0.83rem; font-style:italic; line-height:1.55;">💡 Apzinātā un slēptā (ēnas) puse mēdz būt pretējas — tas ir normāli, ne pretruna: ārēji savaldīgs cilvēks iekšēji var ilgoties tieši pēc tā, ko nerāda. Šī nav prognoze, bet pašrefleksijas rīks.</p>
            <p style="margin:0; color:#94a3b8; font-size:0.8rem; line-height:1.5;">ℹ️ Augšā redzamais <b>ticamības %</b> rāda, cik <b>pilnīgi ir ievaddati</b> (galvenokārt — vai zināms precīzs dzimšanas laiks), nevis cik patiess ir saturs. Zems % = trūkst laika, tāpēc daļa “jomu” nav nosakāmas.</p>
        </div>`;

    const s1Body = `
        ${sw.banner}
        ${leadSummary}

        ${mandala}

        <details style="margin-top:1.1rem;">
          <summary style="cursor:pointer; font-size:0.9rem; font-weight:700; color:#475569; padding:0.6rem 0.9rem; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; list-style:none;">▾ Padziļināts apraksts — aizsardzības mehānismi, riski, apakšslāņi (klikšķini, lai izvērstu)</summary>
          <div style="column-count:3; column-gap:1.4rem; column-rule:1px solid #eef2f7; margin-top:1.1rem;">
            ${col(sectionTitle('Personības arhetipu karte — Junga analītiskā psiholoģija', '#1d4ed8') + qBlock('🎭 Sociālā maska un pirmais iespaids', dropLead(jungPersona), '#1d4ed8', 'Loma, ko spēlē sabiedrībā — pirmais iespaids un aizsargmehānisms pret ārējo pasauli.', 'Junga PERSONA · Ascendants') + maskSynthesisBlock(profile, maskSynth) + (maskSynth.applicable ? '' : cf.persona), 's1-persona')}
            ${col(qBlock('🌙 Emocionālā daba un romantiskā projekcija', dropLead(jungAnima), '#7e22ce', 'Iekšējā sajūtu pasaule, emocionālās vajadzības un tas, ko neapzināti meklē un projicē uz romantisko partneri.', 'Junga ANIMA/ANIMUS · Mēness') + sw.anima + (sweep ? '' : cf.anima), 's1-anima')}
            ${col(qBlock('☀ Apzinātais Es un dzīves galvenais uzdevums', dropLead(jungSelf), '#b45309', 'Apzinātais "Es", galvenais dzīves uzdevums un galamērķis, uz kuru tiecas personība, lai sasniegtu iekšējo veselumu.', 'Junga PATĪBA · Saule') + sw.self + (sweep ? '' : cf.self), 's1-self')}
            ${col(qBlock('◉ Slēptais dzinējspēks un neapzinātās ambīcijas', dropLead(jungShadowRahu), '#b91c1c', 'Neapzinātais izsalkums, kas dzen uz priekšu — un kas var vest gan uz sabrukumu, gan virsotni — atkarībā no apzinātības pakāpes.', 'Junga ĒNA · Rahu') + sw.rahu + (sweep ? '' : cf.rahu), 's1-rahu')}
            ${col(qBlock('● Aklās zonas un karmiskais instinkta talants', dropLead(jungShadowKetu), '#64748b', 'Sfēra, pret kuru izjūtama apātija un kura tiek ignorēta — un kurā vienlaikus slēpjas karmiskais resurss un instinktīvs talants.', 'Junga ĒNA · Ketu') + sw.ketu + (sweep ? '' : cf.ketu), 's1-ketu')}
            ${col(qBlock('✦ Pirmatnējās dziņas un iekšējie konflikti', dropLead(jungShadowLilith), '#6d28d9', 'Psihes neapstrādātā, pirmatnējā daļa — sāpes, instinkti un pretrunas, kas aktivizējas augsta stresa vai izdzīvošanas apstākļos.', 'Junga ĒNA · Lilita') + cf.lilita, 's1-lilita')}
            ${col(sectionTitle('Ķeltu koku identitāte', '#4d7c0f') + celticBlock, 's1-celtic')}
            ${col(sectionTitle('Rietumu tranzītu stāsts — pašreizējā fāze', '#1d4ed8') + transitBlock, 's1-transit')}
          </div>
        </details>`;

    const s1 = card('1',
        'Psiholoģiskā pašizpratne un personības arhetipu analīze',
        'Visaugstākā',
        ['Junga psiholoģija', 'Rietumu astroloģija', 'Ķeltu koku astroloģija', 'Vēdu Rahu/Ketu'],
        s1Body,
        { drivers: [
            { path: 'vedic.ascSignDist', label: 'Ascendants', weight: 2 },
            { path: 'vedic.planets.Saule.houseDist', label: 'Saules māja', weight: 1 },
            { path: 'vedic.planets.Meness.houseDist', label: 'Mēness māja', weight: 1 },
            { path: 'vedic.planets.Rahu.houseDist', label: 'Rahu/Ketu māja', weight: 1 },
        ], requires: ['time', 'birthplace'],
          limits: 'Junga arhetipu karte balstās Ascendantā un planētu mājās — abi laika-atkarīgi, tāpēc precīzs dzimšanas laiks (±15–30 min) ir vienīgais būtiskais uzlabojums. Datuma-fiksētās daļas (Ķeltu koks, planētu zīmes) laiks neietekmē; metode apraksta tendences, ne diagnozes.' }
    );

    // ── 2: KARJERAS STRUKTŪRA (BAZI + VEDIC) ─────────────────────────────────
    const bazi    = profile?.bazi    || {};
    const dm      = bazi.Daymaster   || {};
    const mainGod = bazi.mainGod     || '—';
    const hiddenGod = bazi.hiddenGod || null;
    const baziPsych = bazi.psychology || {};
    const luckPillars = bazi.luck_pillars || [];
    const symStars    = bazi.symbolic_stars || [];
    const vedicYogas  = profile?.vedic?.yogas || [];
    const careers     = profile?.careers || {};
    const zonesData   = careers.zones   || {};
    const careerZone23 = [...(zonesData[2] || []), ...(zonesData[3] || [])].sort((a, b) => b.score - a.score);
    const careerZone45 = [...(zonesData[4] || []), ...(zonesData[5] || [])].sort((a, b) => b.score - a.score);

    // BaZi dievs → karjeras struktūra (atslēgas = calculateTenGods() angļu enum vērtības)
    const godMeanings = {
        'Friend': {
            role: 'Sadarbība un partnerība',
            style: 'Efektīvākā darbība ir kopā ar līdzvērtīgiem partneriem, izvairoties no izteiktas hierarhijas.',
            color: '#1d4ed8',
            coreDrive: 'Sinerģija un kopīgu mērķu sasniegšana sabiedroto lokā.',
            shadow: 'Grūtības pieņemt lēmumus vienpersoniski un nevēlēšanās izcelties uz citu fona.'
        },
        'Rob_Wealth': {
            role: 'Mēroga un resursu apguve',
            style: 'Izteikta vēlme paplašināties, uzņemties risku un piesaistīt resursus jauniem biznesa projektiem.',
            color: '#047857',
            coreDrive: 'Tirgus daļas un resursu apguve caur proaktīvu konkurenci.',
            shadow: 'Tendence pārvērtēt spēkus un grūtības deleģēt patieso kontroli citiem.'
        },
        'Direct_Officer': {
            role: 'Procesu pārvaldība un kvalitāte',
            style: 'Līderība caur noteikumiem, skaidru atbildību sadali un procesu administratīvo kontroli.',
            color: '#6d28d9',
            coreDrive: 'Sistēmas stabilitātes, kārtības un organizācijas reputācijas aizsardzība.',
            shadow: 'Pārlieku stingra turēšanās pie instrukcijām pat situācijās, kad nepieciešama tūlītēja elastība.'
        },
        'Seven_Killings': {
            role: 'Krīžu vadība un operativitāte',
            style: 'Efektīva vadība augsta stresa situācijās, spēja pieņemt nepopulārus un ātrus lēmumus.',
            color: '#be185d',
            coreDrive: 'Mērķu sasniegšana spiediena apstākļos un kritisku šķēršļu pārvarēšana.',
            shadow: 'Augsts izdegšanas risks pārmērīgas paškritikas un nevēlēšanās piekāpties dēļ.'
        },
        'Indirect_Resource': {
            role: 'Stratēģiskā analīze un pētniecība',
            style: 'Netiešās ietekmes plānošana, sarežģītu kopsakarību saskatīšana un konsultēšana.',
            color: '#c2410c',
            coreDrive: 'Netradicionālu kopsakarību saskatīšana un stratēģiskā intuīcija.',
            shadow: 'Tendence izolēties, paturēt informāciju pie sevis un neuzticēties ierastajām metodēm.'
        },
        'Direct_Resource': {
            role: 'Sistemātiskā drošība un fakti',
            style: 'Uz zināšanām un pārbaudītu informāciju balstīta darbība. Zinātniskā vai akadēmiskā pieeja.',
            color: '#0369a1',
            coreDrive: 'Drošība caur padziļinātām zināšanām un faktu analīzi.',
            shadow: 'Lēmumu pieņemšanas bremzēšana ("analīzes paralīze"), meklējot pārāk daudz papildu datu.'
        },
        'Indirect_Wealth': {
            role: 'Iespēju vadība un investīcijas',
            style: 'Finanšu iespēju saskatīšana, gatavība iesaistīties jaunos tirgos un vadīt kapitāla riskus.',
            color: '#7e22ce',
            coreDrive: 'Plašāka mēroga biznesa iespēju saskatīšana un kapitāla spēle.',
            shadow: 'Impulsivitāte, fokusa zaudēšana un pārlieku liela paļaušanās uz spekulatīvām iespējām.'
        },
        'Direct_Wealth': {
            role: 'Operacionālais pragmatisms un finanses',
            style: 'Sistemātiska, konsekventa un disciplinēta finanšu un materiālo resursu pārvaldība.',
            color: '#b45309',
            coreDrive: 'Reāli, taustāmi rezultāti caur disciplinētu un strukturētu darbu.',
            shadow: 'Pārlieka piesardzība un nevēlēšanās uzņemties pat minimālu risku izaugsmes vārdā.'
        },
        'Eating_God': {
            role: 'Padziļinātā ekspertīze un radošums',
            style: 'Uzmanība vērsta uz maksimāli augstu darba kvalitāti, izpēti un individuālo meistarību.',
            color: '#15803d',
            coreDrive: 'Brīvība radīt un iedziļināties meistarībā bez ārēja spiediena.',
            shadow: 'Perfekcionisma radīta vilcināšanās un grūtības iekļauties komerciālos termiņos.'
        },
        'Hurting_Officer': {
            role: 'Ideju prezentēšana un inovācijas',
            style: 'Netradicionālu risinājumu virzīšana tirgū, spēja pārliecināt un iedvesmot auditoriju.',
            color: '#166534',
            coreDrive: 'Ideju prezentēšana un harizmātiska ietekme uz citiem.',
            shadow: 'Konfrontācija ar vadību, iekšējo tiltu dedzināšana un grūtības darboties stingrā hierarhijā.'
        }
    };
    const godInfo = godMeanings[mainGod] || { role: mainGod, style: '—', color: '#64748b', coreDrive: '', shadow: '' };

    // BaZi 10 Dievu enum → latviskie nosaukumi (UI līmenī; dati paliek angļu enum)
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

    // Filtrē pagātnes pīlārus — rāda tikai aktuālos un nākamos
    const currentAgeYrs = birthDate
        ? Math.floor((Date.now() - new Date(birthDate).getTime()) / (365.25 * 24 * 3600 * 1000))
        : null;
    const relevantPillars = currentAgeYrs !== null
        ? luckPillars.filter(lp => (lp.ageEnd ?? 0) > currentAgeYrs)
        : luckPillars;

    const elemColors = { 'Koks':'#15803d','Uguns':'#b91c1c','Zeme':'#b45309','Metāls':'#64748b','Ūdens':'#1d4ed8' };
    const lpHtml = relevantPillars.length ? relevantPillars.slice(0, 5).map((lp, idx) => {
        const stemObj = (typeof lp.stem === 'object') ? lp.stem : { name: lp.stem || '—', element: '' };
        const elemColor = elemColors[stemObj.element] || '#64748b';
        const ip = lp.interpretation;
        const isCurrent = idx === 0;

        return `<div style="background:#f8fafc; border-radius:12px; padding:1rem 1.15rem; border-left:${isCurrent ? '4px' : '3px'} solid ${elemColor}${isCurrent ? 'cc' : '60'}; ${isCurrent ? 'box-shadow:0 0 0 1px ' + elemColor + '30;' : ''}">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.5rem;">
                <span style="font-weight:800; color:${ink(elemColor)}; font-size:0.98rem;">${ip ? ip.godLabel : `${stemObj.polarity || ''} ${stemObj.element || ''}`}</span>
                <div style="display:flex; gap:6px; align-items:center;">
                    ${isCurrent ? `<span style="background:${elemColor}; color:#fff; border-radius:5px; padding:1px 7px; font-size:0.66rem; font-weight:800; letter-spacing:0.5px;">AKTĪVS</span>` : ''}
                    <span style="background:#f1f5f9; color:#334155; border-radius:6px; padding:2px 10px; font-size:0.78rem;">${lp.ageStart ?? '?'}–${lp.ageEnd ?? '?'} gadi</span>
                </div>
            </div>

            ${ip ? `<div style="color:#64748b; font-size:0.78rem; margin-bottom:0.7rem;">Izpausme: ${ip.stemCharacter}</div>` : ''}

            ${ip ? `
                <div style="background:#f1f5f9; border-radius:8px; padding:0.7rem 0.9rem; margin-bottom:0.5rem; border-left:2px solid ${elemColor}80;">
                    <div style="font-size:0.7rem; font-weight:800; color:${ink(elemColor)}; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.3rem;">⚡ Cikla fokuss</div>
                    <div style="color:#1e293b; font-size:0.85rem; line-height:1.55; font-style:italic;">${ip.phase}</div>
                </div>

                <div style="color:#334155; font-size:0.83rem; line-height:1.6; margin-bottom:0.35rem;">
                    <span style="color:#047857; font-weight:700;">📈 Fokuss:</span> ${ip.focus}.
                </div>
                <div style="color:#334155; font-size:0.83rem; line-height:1.6; margin-bottom:0.35rem;">
                    <span style="color:#b91c1c; font-weight:700;">⚠ Izvairies:</span> ${ip.avoid}.
                </div>
                <div style="color:#64748b; font-size:0.76rem; line-height:1.5; margin-top:0.5rem; padding-top:0.5rem; border-top:1px solid #e2e8f0;">
                    <b style="color:#64748b;">Darbības vide:</b> ${ip.branchCharacter}.
                </div>
            ` : ''}
        </div>`;
    }).join('') : `<div style="color:#475569; font-size:0.87rem;">${luckPillars.length ? 'Visi pīlāri ir pagātnē' : 'Veiksmes pīlāri nav aprēķināti'}</div>`;

    const yogaCareer = vedicYogas.filter(y =>
        /raja|dhana|karjera|vara|kūta|lakshmi|gajakesari|budha|amala|mahapurusha/i.test(y.name || '') ||
        /vara|pandit|strength|10|dasa/i.test(y.name || '')
    ).slice(0, 4);
    const yogaHtml = yogaCareer.length
        ? yogaCareer.map(y => `<div style="background:#f8fafc; border-radius:8px; padding:0.65rem 0.9rem; margin-bottom:4px; border-left:3px solid #6d28d950;">
            <b style="color:#6d28d9; font-size:0.88rem;">${y.name}</b>
            <span style="color:#64748b; font-size:0.82rem; margin-left:8px;">${y.description || y.type || ''}</span>
          </div>`).join('')
        : `<div style="color:#475569; font-size:0.85rem;">Nav identificētu karjeras yoga</div>`;

    const careerHtml = (careerZone23.length || careerZone45.length) ? [
        ...careerZone23.slice(0, 4).map(c => `<span style="display:inline-block; background:#f1f5f9; border:1px solid #04785740; border-radius:8px; padding:4px 12px; font-size:0.82rem; color:#047857; margin:3px;" title="Zone 2-3 · ${c.area || ''}">${c.lv || '—'}</span>`),
        ...careerZone45.slice(0, 3).map(c => `<span style="display:inline-block; background:#f1f5f9; border:1px solid #7c3aed40; border-radius:8px; padding:4px 12px; font-size:0.82rem; color:#6d28d9; margin:3px;" title="Zone 4-5 · ${c.area || ''}">${c.lv || '—'}</span>`),
    ].join('') : `<span style="color:#475569; font-size:0.85rem;">Karjeras dati nav aprēķināti</span>`;

    const dmStrengthObj = bazi.dm_strength || {};
    const dmStrengthLabel = dmStrengthObj.isStrong 
        ? "Autonoms dzinējspēks (pašpietiekamība)" 
        : "Sinerģisks dzinējspēks (receptivitāte)";

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

    // ── Karjeras Enkuri (Schein) + Cynefin + Kahneman ────────────────────────
    const careerAnchors = profile?.careerAnchors || null;

    const anchorBar = (a, isPrimary) => `
        <div style="background:#f8fafc; border-radius:10px; padding:0.95rem 1.15rem; margin-bottom:0.55rem; border-left:${isPrimary ? '4px' : '3px'} solid ${a.color}${isPrimary ? 'cc' : '60'};">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:0.6rem; flex-wrap:wrap; margin-bottom:${isPrimary ? '0.5rem' : '0.3rem'};">
                <div style="display:flex; align-items:center; gap:0.55rem;">
                    <span style="font-size:1.05rem;">${a.icon}</span>
                    <span style="font-weight:800; color:${ink(a.color)}; font-size:${isPrimary ? '1rem' : '0.92rem'};">${a.lv}</span>
                    ${isPrimary ? `<span style="background:${a.color}25; color:${ink(a.color)}; border:1px solid ${a.color}55; border-radius:6px; padding:1px 8px; font-size:0.7rem; font-weight:700;">PRIMĀRS ENKURS</span>` : ''}
                </div>
                <span style="color:${ink(a.color)}; font-weight:700; font-size:0.95rem;">${a.score}%</span>
            </div>
            <div style="background:#f1f5f9; border-radius:4px; height:5px; overflow:hidden; margin-bottom:${isPrimary ? '0.6rem' : '0'};">
                <div style="height:100%; width:${a.score}%; background:${a.color};"></div>
            </div>
            ${isPrimary ? `
                <div style="color:#1e293b; font-size:0.9rem; line-height:1.65; margin-bottom:0.4rem;">${a.description}.</div>
                <div style="color:#64748b; font-size:0.83rem; line-height:1.55;"><span style="color:#047857;">Uzplaukst:</span> ${a.thrives}.</div>
                <div style="color:#64748b; font-size:0.83rem; line-height:1.55;"><span style="color:#b91c1c;">Risks:</span> ${a.risks}.</div>
            ` : ''}
        </div>`;

    const anchorsHtml = careerAnchors?.allAnchors?.length ? (() => {
        const anchors = careerAnchors.allAnchors;
        // TOP 3 vienmēr redzami; pārējie "fona" enkuri sakļauti zem pogas.
        // Neizšķirtu-apziņa: ja aiz griezuma skors sakrīt ar pēdējo redzamo,
        // paplašinām redzamo kopu — neslēpjam enkuru ar tādu pašu skoru.
        let visN = Math.min(3, anchors.length);
        while (visN < anchors.length && anchors[visN].score === anchors[visN - 1].score) visN++;
        const visible = anchors.slice(0, visN).map((a, i) => anchorBar(a, i === 0)).join('');
        const rest = anchors.slice(visN);
        if (!rest.length) return visible;
        const restHtml = rest.map(a => anchorBar(a, false)).join('');
        return `
            ${visible}
            <div id="anchor-rest" style="display:none;">${restHtml}</div>
            <button type="button" onclick="(function(b){var d=document.getElementById('anchor-rest');var o=d.style.display==='none';d.style.display=o?'block':'none';b.textContent=o?'▲ Sakļaut fona enkurus':'▼ Rādīt pārējos ${rest.length} enkurus';})(this)"
                style="width:100%; background:#fff; border:1px dashed #cbd5e1; border-radius:9px; padding:0.6rem; margin-top:0.5rem; font-size:0.82rem; color:#64748b; font-weight:700; cursor:pointer; font-family:inherit;">▼ Rādīt pārējos ${rest.length} enkurus</button>`;
    })() : `<div style="color:#475569; font-size:0.87rem;">Karjeras enkuru dati nav aprēķināti</div>`;

    const cynefinHtml = careerAnchors?.cynefin ? (() => {
        const { primary, primaryMeta, profile: cyScores, meta } = careerAnchors.cynefin;
        // Kanoniskā Snoudena 2×2 shēma: Complex/Complicated augšā, Chaotic/Simple apakšā
        const order = ['complex', 'complicated', 'chaotic', 'simple'];
        const tiles = order.map(key => {
            const m = meta[key];
            const score = cyScores[key];
            const isPrim = key === primary;
            return `
                <div style="background:${isPrim ? m.color + '0d' : '#f8fafc'}; border-radius:10px; padding:0.85rem 1rem; border:${isPrim ? '2px' : '1px'} solid ${m.color}${isPrim ? '99' : '20'}; position:relative; display:flex; flex-direction:column; justify-content:space-between; min-height:72px;">
                    ${isPrim ? `<span style="position:absolute; top:-9px; right:10px; background:${ink(m.color)}; color:#fff; border-radius:5px; padding:1px 7px; font-size:0.65rem; font-weight:800; letter-spacing:0.5px;">PRIMĀRS</span>` : ''}
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem; margin-bottom:0.45rem;">
                        <span style="font-weight:800; color:${ink(m.color)}; font-size:0.9rem;">${m.icon} ${m.lv}</span>
                        <span style="color:${ink(m.color)}; font-weight:700; font-size:0.86rem; font-variant-numeric:tabular-nums;">${score}%</span>
                    </div>
                    <div style="background:#f1f5f9; border-radius:3px; height:4px; overflow:hidden;">
                        <div style="height:100%; width:${score}%; background:${m.color};"></div>
                    </div>
                </div>`;
        }).join('');
        return `
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; margin-bottom:0.8rem;">${tiles}</div>
            <div style="background:#f8fafc; border-left:4px solid ${primaryMeta.color}; border-radius:0 8px 8px 0; padding:0.95rem 1.15rem; color:#334155; font-size:0.88rem; line-height:1.6;">
                <b style="color:${ink(primaryMeta.color)}; font-size:0.92rem;">${primaryMeta.icon} ${primaryMeta.lv} · dominējošā vide:</b> ${primaryMeta.description}.
            </div>`;
    })() : `<div style="color:#475569; font-size:0.87rem;">Lēmumu konteksta dati nav aprēķināti</div>`;

    // Naratīva hero bloks — stratēģiskais sintēzes paragrafs
    const narrativeHtml = careerAnchors?.narrative?.paragraph ? `
        <div style="background:linear-gradient(135deg, #eef2ff 0%, #ffffff 100%); border:1px solid #4f46e540; border-radius:14px; padding:1.5rem 1.7rem; margin-bottom:1.2rem; box-shadow:0 2px 10px rgba(99,102,241,0.10);">
            <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.85rem;">
                <span style="font-size:1.3rem;">🧭</span>
                <div style="font-size:0.72rem; font-weight:800; color:#4f46e5; text-transform:uppercase; letter-spacing:2px;">Kapacitātes kopsavilkums</div>
            </div>
            <div style="color:#334155; font-size:1rem; line-height:1.8;">${careerAnchors.narrative.paragraph}</div>
        </div>
    ` : '';

    // Kapacitātes robeža — vājākais enkurs kā izdegšanas trigeris.
    // Barometrs vizualizē drenāžas intensitāti KVALITATĪVI (zaļš→sarkans + zona),
    // BEZ izdomātas "varbūtības %" — marķiera pozīcija atvasināta no enkura skora,
    // bet netiek pasniegta kā statistisks rādītājs (sk. "tendences, ne diagnozes").
    const boundaryHtml = careerAnchors?.boundary ? (() => {
        const b = careerAnchors.boundary;
        const riskVal = Math.max(0, Math.min(100, 100 - b.score)); // zemāks enkurs → tālāk sarkanajā
        const band = riskVal >= 70 ? { lv: 'Augsts',       color: '#b91c1c' }
                   : riskVal >= 55 ? { lv: 'Paaugstināts', color: '#dc2626' }
                   : riskVal >= 40 ? { lv: 'Mērens',       color: '#d97706' }
                   :                 { lv: 'Zems',         color: '#16a34a' };
        return `
            <div style="background:#fef2f2; border:1px solid #b91c1c40; border-left:4px solid #b91c1c; border-radius:12px; padding:1.2rem 1.4rem; margin-top:0.8rem;">
                <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.7rem;">
                    <span style="font-size:1.1rem;">⚠️</span>
                    <div style="font-size:0.72rem; font-weight:800; color:#b91c1c; text-transform:uppercase; letter-spacing:1.5px;">Kapacitātes robeža · izdegšanas trigeris</div>
                </div>
                <div style="margin-bottom:0.9rem;">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.4rem;">
                        <span style="font-size:0.78rem; color:#64748b; font-weight:600;">Izdegšanas risks šajā lomā</span>
                        <span style="font-size:0.8rem; font-weight:800; color:${ink(band.color)};">${band.lv} risks</span>
                    </div>
                    <div style="position:relative; height:10px; border-radius:6px; background:linear-gradient(90deg,#16a34a 0%,#facc15 50%,#dc2626 100%);">
                        <div style="position:absolute; top:-6px; left:${riskVal}%; transform:translateX(-50%); width:0; height:0; border-left:6px solid transparent; border-right:6px solid transparent; border-top:10px solid #1e293b;" title="${band.lv} risks"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.66rem; color:#94a3b8; font-weight:600; margin-top:0.3rem;">
                        <span>Zems</span><span>Mērens</span><span>Paaugstināts</span><span>Augsts</span>
                    </div>
                </div>
                <div style="color:#334155; font-size:0.92rem; line-height:1.7; margin-bottom:0.5rem;">
                    Vājākais Šeina enkurs šim profilam ir <b style="color:${ink(b.color)};">${b.lv}</b>. Tieši tāpēc, ka šī dimensija ir profila zemākā, ilgstoša ekspozīcija šajā vidē izsmeļ resursus neproporcionāli ātri — izdegšanas risks parādās ${b.burnoutTrigger}.
                </div>
                <div style="color:#64748b; font-size:0.83rem; line-height:1.55; font-style:italic;">Šis nav vājuma novērtējums — tas ir profilaktisks lēmumu dizaina rīks. Šādu lomu uzliekot ilgtermiņā, demotivācija un izdegšana ir paredzama, ne nejauša.</div>
            </div>`;
    })() : '';

    const kahnemanHtml = careerAnchors?.kahneman ? (() => {
        const { s1pct, s2pct, dominantMeta, meta } = careerAnchors.kahneman;
        const s1m = meta.s1, s2m = meta.s2;
        return `
            <div style="background:#f8fafc; border-radius:10px; padding:1rem 1.2rem;">
                <div style="display:flex; height:14px; border-radius:7px; overflow:hidden; margin-bottom:0.6rem;">
                    <div style="width:${s1pct}%; background:${ink(s1m.color)}; display:flex; align-items:center; justify-content:flex-start; padding-left:8px; color:#fff; font-size:0.7rem; font-weight:800;">${s1pct >= 12 ? s1pct + '%' : ''}</div>
                    <div style="width:${s2pct}%; background:${ink(s2m.color)}; display:flex; align-items:center; justify-content:flex-end; padding-right:8px; color:#fff; font-size:0.7rem; font-weight:800;">${s2pct >= 12 ? s2pct + '%' : ''}</div>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:0.78rem; color:#64748b; margin-bottom:0.7rem;">
                    <span style="color:${s1m.color}; font-weight:700;">⚡ ${s1m.lv}</span>
                    <span style="color:${s2m.color}; font-weight:700;">🧮 ${s2m.lv}</span>
                </div>
                <div style="color:#334155; font-size:0.88rem; line-height:1.6;"><b style="color:${ink(dominantMeta.color)};">Dominantā: ${dominantMeta.lv}.</b> ${dominantMeta.description}.</div>
            </div>`;
    })() : `<div style="color:#475569; font-size:0.87rem;">Kognitīvā stila dati nav aprēķināti</div>`;

    // Lēmumu dizaina špikeris — atvasināts no isAligned × dominantā Kānemana stila
    // (Ideja E). Tonis: 3. persona par profilu; rīcības ieteikums bezpersonisks.
    // Ja Kānemana stāvoklis ir 'adaptive' (46–54%), dod ABU sistēmu ieteikumus.
    const cheatSheetHtml = careerAnchors?.kahneman ? (() => {
        const isAligned = careerAnchors.isAligned;
        const dom       = careerAnchors.kahneman.dominant;
        const envLv     = careerAnchors.cynefin?.primaryMeta?.lv || 'tā primārā vide';
        const kColor    = dom === 's1' ? '#f59e0b' : dom === 's2' ? '#3b82f6' : '#8b5cf6';
        let strengthText, actionTip;
        if (dom === 'adaptive') {
            strengthText = `Šī profila kognitīvais stils ir adaptīvs — tas nepiedāvā vienu dominējošu režīmu, bet elastīgi pārslēdzas starp ātro (intuitīvo) un lēno (analītisko) sistēmu atkarībā no situācijas konteksta. Primārā vide: ${envLv}.`;
            actionTip    = `<b>Krīzē vai haosā</b> — uzticēties pirmajam impulsam un rīkoties ātri; pārmērīga analīze šajā brīdī rada paralīzi. <b>Stabilā vai strukturētā vidē</b> — apzināti ieslēgt analītisko režīmu, pieprasīt datus un ieturēt pauzi pirms gala lēmuma. Šī profila stiprums ir spēja atpazīt, kurš režīms ir vajadzīgs tagad.`;
        } else if (dom === 's1') {
            if (isAligned) {
                strengthText = `Šī profila kognitīvais stils — ātrā, intuitīvā domāšana — ir pilnā saskaņā ar tā dabisko vidi (${envLv}). Tas izcili reaģē neparedzamībā un spēj pieņemt lēmumus ar minimālu datu apjomu.`;
                actionTip    = `Krīzē — uzticēties pirmajam impulsam. Stabilā vai reglamentētā vidē apzināti iebūvēt drošinātāju (ārējs viedoklis vai īsa pauze pirms rīcības), lai novērstu sasteigtību.`;
            } else {
                strengthText = `Šī profila dabiskais stils ir ātrs un intuitīvs, taču tā primārā vide (${envLv}) prasa kārtību un dziļāku analīzi. Tas ir kompensējošs konfigurējums — intuīcija balansē vides prasību.`;
                actionTip    = `Strukturētās situācijās balstīties uz kontrolsarakstiem. Intuīciju izmantot jaunu likumsakarību pamanīšanai, bet pirms gala lēmuma formāli pārbaudīt faktus.`;
            }
        } else {
            if (isAligned) {
                strengthText = `Šī profila kognitīvais stils — lēnā, analītiskā domāšana — saskan ar tā primāro vidi (${envLv}). Tas izcili analizē datus, būvē struktūras un atrod precīzāko risinājumu.`;
                actionTip    = `Neļaut sevi sasteigt — stiprums ir laiks un pārbaude. Haosa brīžos, kad datu trūkst, paļauties uz iepriekš sagatavotiem "ja–tad" protokoliem, lai izvairītos no analīzes paralīzes.`;
            } else {
                strengthText = `Šī profila dabiskais stils ir analītisks un strukturēts, taču tā primārā vide (${envLv}) prasa ātru eksperimentēšanu. Tas ir kompensējošs konfigurējums — analīze neļauj haosam pilnībā absorbēt.`;
                actionTip    = `Lielos lēmumus sadalīt mazos, kontrolētos eksperimentos. Haosā nemeklēt vienīgo pareizo lēmumu (tā nav) — noteikt maksimālo pieļaujamo zaudējuma slieksni un rīkoties ātri.`;
            }
        }
        return `
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.1rem 1.3rem; margin-top:0.8rem;">
                <div style="display:flex; align-items:center; gap:0.55rem; margin-bottom:0.65rem;">
                    <span style="font-size:1.15rem;">🎯</span>
                    <div style="font-size:0.72rem; font-weight:800; color:#3b82f6; text-transform:uppercase; letter-spacing:1.5px;">Lēmumu dizaina špikeris</div>
                </div>
                <div style="color:#334155; font-size:0.88rem; line-height:1.6; margin-bottom:0.6rem;">${strengthText}</div>
                <div style="background:${kColor}10; border-left:3px solid ${kColor}; border-radius:0 8px 8px 0; padding:0.75rem 0.95rem; color:${ink(kColor)}; font-size:0.85rem; line-height:1.6;">
                    <span style="font-weight:700;">💡 Rīcības ieteikums:</span> ${actionTip}
                </div>
            </div>`;
    })() : '';

    // Datu pārklājuma ticamības karogs — godīgi parāda, ja skori daļēji balstās
    // uz neitrāliem 50% noklusējumiem (trūkstoši ieejas dati), nevis reāliem.
    const confNoteHtml = careerAnchors?.confidence ? (() => {
        const c = careerAnchors.confidence;
        const map = {
            high:   { color: '#047857', bg: '#ecfdf5', label: 'Augsts datu pārklājums', icon: '✓' },
            medium: { color: '#b45309', bg: '#fffbeb', label: 'Daļējs datu pārklājums', icon: '◐' },
            low:    { color: '#b91c1c', bg: '#fef2f2', label: 'Zems datu pārklājums', icon: '⚠' }
        };
        const m = map[c.level] || map.medium;
        const missing = [];
        if (!c.inputs.bigFive)    missing.push('Big Five');
        if (!c.inputs.facets)     missing.push('rakstura fasetes');
        if (!c.inputs.bazi)       missing.push('BaZi');
        if (!c.inputs.leadership) missing.push('līderības tips');
        const detail = c.level === 'high'
            ? 'Visi galvenie ieejas signāli ir pieejami.'
            : `Trūkstošie signāli (${missing.join(', ')}) aizstāti ar neitrālu vidējo (50%) — šo sadaļu lasiet kā tendenci, ne mērījumu.`;
        return `
            <div style="display:flex; align-items:flex-start; gap:0.55rem; background:${m.bg}; border:1px solid ${m.color}35; border-radius:10px; padding:0.7rem 0.95rem; margin:0.4rem 0 1rem 0;">
                <span style="font-size:0.95rem; line-height:1.4;">${m.icon}</span>
                <div style="font-size:0.8rem; line-height:1.55; color:#334155;"><b style="color:${m.color};">${m.label} (${c.coverage}%).</b> ${detail}</div>
            </div>`;
    })() : '';

    // ── Stratēģiskā Kapacitātes modeļa SVG shēma ──────────────────────────────
    const capacitySvgHtml = (() => {
        if (!careerAnchors) return '';
        const anchors = careerAnchors.allAnchors || [];
        const top1 = anchors[0] || { lv: 'Nav enkura', score: 0, color: '#64748b', icon: '⚓' };
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
        const baziColor = godInfo.color || '#15803d';

        return `
        <div style="max-width:820px; margin:1rem auto 1.5rem;">
            <div style="text-align:center; font-size:0.92rem; color:#475569; font-weight:700; margin-bottom:0.25rem;">🧭 Profesionālās Kapacitātes &amp; Lēmumu karte</div>
            <div style="text-align:center; font-size:0.8rem; color:#94a3b8; margin-bottom:0.8rem;">Dinamiska arhitektūra: karjeras dzinējspēki, kognitīvais stils un izdegšanas barometrs.</div>
            <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-width:820px; margin:0 auto;">
                <defs>
                    <linearGradient id="riskGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#16a34a" />
                        <stop offset="50%" stop-color="#facc15" />
                        <stop offset="100%" stop-color="#dc2626" />
                    </linearGradient>
                </defs>

                <!-- Labels -->
                <text x="20" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">KARJERAS ENKURI (MIT SLOAN)</text>
                <!-- textLength ierobežo līdz kolonnas platumam — pilnais nosaukums 13px izgāja
                     virs nākamās kolonnas galvenes (≈10px pārklāšanās). -->
                <text x="272" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif" textLength="240" lengthAdjust="spacingAndGlyphs">LĒMUMU PIEŅEMŠANAS ARHITEKTŪRA</text>
                <text x="524" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">ROBEŽAS &amp; BĀZES AUDITS</text>

                <!-- 1. Enkuri (Block A) -->
                <g onclick="window.__s1Focus&&window.__s1Focus('capacity-anchors')" style="cursor:pointer;">
                    <rect x="20" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    
                    <!-- Primary Anchor (Top 1) -->
                    <rect x="32" y="50" width="212" height="80" rx="10" fill="${top1.color}15" stroke="${top1.color}" stroke-width="2" />
                    ${svgFramedTitle(44, `${top1.icon} ${top1.lv}`, 'PRIMĀRAIS VĒRTĪBU ENKURS', 15, ink(top1.color), 140, 192)}
                    <text x="232" y="76" font-size="18" font-weight="900" fill="${ink(top1.color)}" text-anchor="end" font-family="'Outfit',sans-serif">${top1.score}%</text>
                    
                    <!-- Top 2 Anchor -->
                    <rect x="32" y="145" width="212" height="46" rx="8" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" />
                    <text x="44" y="166" font-size="13" font-weight="800" fill="#334155" font-family="'Outfit',sans-serif"${svgFit(`${top2.icon} ${top2.lv}`, 158, 13)}>${top2.icon} ${top2.lv}</text>
                    <text x="232" y="166" font-size="13" font-weight="900" fill="#64748b" text-anchor="end" font-family="'Outfit',sans-serif">${top2.score}%</text>
                    <rect x="44" y="174" width="188" height="5" rx="2" fill="#f1f5f9" />
                    <rect x="44" y="174" width="${(top2.score / 100) * 188}" height="5" rx="2" fill="${top2.color}" />

                    <!-- Top 3 Anchor -->
                    <rect x="32" y="203" width="212" height="46" rx="8" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" />
                    <text x="44" y="224" font-size="13" font-weight="800" fill="#334155" font-family="'Outfit',sans-serif"${svgFit(`${top3.icon} ${top3.lv}`, 158, 13)}>${top3.icon} ${top3.lv}</text>
                    <text x="232" y="224" font-size="13" font-weight="900" fill="#64748b" text-anchor="end" font-family="'Outfit',sans-serif">${top3.score}%</text>
                    <rect x="44" y="232" width="188" height="5" rx="2" fill="#f1f5f9" />
                    <rect x="44" y="232" width="${(top3.score / 100) * 188}" height="5" rx="2" fill="${top3.color}" />

                    <!-- Description/Subtext -->
                    <foreignObject x="32" y="262" width="212" height="135">
                        <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:13px; line-height:1.45; text-align:left;">
                            Profila dominējošais enkurs ir <b>${top1.lv}</b>. Tas nosaka prioritātes, izvēloties jaunas lomas un projektus. Ja šis enkurs tiek apspiests, rodas gandarījuma trūkums.
                        </div>
                    </foreignObject>
                </g>

                <!-- 2. Lēmumi (Block B) -->
                <g>
                    <!-- Base rectangle -->
                    <rect x="272" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />

                    <!-- Kahneman Sub-Block (Click to capacity-kahneman) -->
                    <g onclick="window.__s1Focus&&window.__s1Focus('capacity-kahneman')" style="cursor:pointer;">
                        <text x="287" y="52" font-size="11.5" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">KĀNEMANA S1 / S2 SISTĒMAS</text>
                        
                        <!-- Slider bar -->
                        <rect x="287" y="58" width="206" height="14" rx="7" fill="#3b82f6" />
                        <rect x="287" y="58" width="${(s1 / 100) * 206}" height="14" rx="7" fill="#f59e0b" />
                        
                        <!-- Text labels -->
                        <text x="292" y="86" font-size="12" font-weight="800" fill="#d97706" font-family="'Outfit',sans-serif">S1 (Ātrā): ${s1}%</text>
                        <text x="488" y="86" font-size="12" font-weight="800" fill="#2563eb" text-anchor="end" font-family="'Outfit',sans-serif">S2 (Lēnā): ${s2}%</text>

                        <text x="390" y="102" font-size="13" font-weight="800" fill="${domColor}" text-anchor="middle" font-family="'Outfit',sans-serif">${domK}</text>
                    </g>

                    <!-- Divider line -->
                    <line x1="287" y1="112" x2="493" y2="112" stroke="#e2e8f0" stroke-width="1" />

                    <!-- Cynefin Sub-Block (Click to capacity-cynefin) -->
                    <g onclick="window.__s1Focus&&window.__s1Focus('capacity-cynefin')" style="cursor:pointer;">
                        <text x="287" y="129" font-size="11.5" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">LĒMUMU VIDE (CYNEFIN DOMĒNI)</text>

                        <!-- 2x2 Grid representing Cynefin quadrants -->
                        <!-- Top Left: Complex -->
                        <rect x="287" y="138" width="100" height="58" rx="6" fill="${cyPrimary === 'complex' ? cyComplex.color + '15' : '#ffffff'}" stroke="${cyPrimary === 'complex' ? cyComplex.color : '#e2e8f0'}" stroke-width="${cyPrimary === 'complex' ? 2 : 1}" />
                        <text x="337" y="160" font-size="12" font-weight="800" fill="${cyComplex.color}" text-anchor="middle" font-family="'Outfit',sans-serif">${cyComplex.icon} Complex</text>
                        <text x="337" y="178" font-size="13" font-weight="900" fill="${cyComplex.color}" text-anchor="middle" font-family="'Outfit',sans-serif">${cyScores.complex}%</text>

                        <!-- Top Right: Complicated -->
                        <rect x="393" y="138" width="100" height="58" rx="6" fill="${cyPrimary === 'complicated' ? cyComplicated.color + '15' : '#ffffff'}" stroke="${cyPrimary === 'complicated' ? cyComplicated.color : '#e2e8f0'}" stroke-width="${cyPrimary === 'complicated' ? 2 : 1}" />
                        <text x="443" y="160" font-size="12" font-weight="800" fill="${cyComplicated.color}" text-anchor="middle" font-family="'Outfit',sans-serif">${cyComplicated.icon} Complicated</text>
                        <text x="443" y="178" font-size="13" font-weight="900" fill="${cyComplicated.color}" text-anchor="middle" font-family="'Outfit',sans-serif">${cyScores.complicated}%</text>

                        <!-- Bottom Left: Chaotic -->
                        <rect x="287" y="202" width="100" height="58" rx="6" fill="${cyPrimary === 'chaotic' ? cyChaotic.color + '15' : '#ffffff'}" stroke="${cyPrimary === 'chaotic' ? cyChaotic.color : '#e2e8f0'}" stroke-width="${cyPrimary === 'chaotic' ? 2 : 1}" />
                        <text x="337" y="224" font-size="12" font-weight="800" fill="${cyChaotic.color}" text-anchor="middle" font-family="'Outfit',sans-serif">${cyChaotic.icon} Chaotic</text>
                        <text x="337" y="242" font-size="13" font-weight="900" fill="${cyChaotic.color}" text-anchor="middle" font-family="'Outfit',sans-serif">${cyScores.chaotic}%</text>

                        <!-- Bottom Right: Simple -->
                        <rect x="393" y="202" width="100" height="58" rx="6" fill="${cyPrimary === 'simple' ? cySimple.color + '15' : '#ffffff'}" stroke="${cyPrimary === 'simple' ? cySimple.color : '#e2e8f0'}" stroke-width="${cyPrimary === 'simple' ? 2 : 1}" />
                        <text x="443" y="224" font-size="12" font-weight="800" fill="${cySimple.color}" text-anchor="middle" font-family="'Outfit',sans-serif">${cySimple.icon} Simple</text>
                        <text x="443" y="242" font-size="13" font-weight="900" fill="${cySimple.color}" text-anchor="middle" font-family="'Outfit',sans-serif">${cyScores.simple}%</text>

                        <!-- Subtext under Cynefin -->
                        <foreignObject x="287" y="272" width="206" height="125">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:13px; line-height:1.45; text-align:left;">
                                Lēmumu vide ir <b>${cy.primaryMeta?.lv || 'Komplicēta'}</b>. Profils vislabāk spēj darboties situācijās, kas atbilst šim domēnam.
                            </div>
                        </foreignObject>
                    </g>
                </g>

                <!-- 3. Audits (Block C) -->
                <g>
                    <!-- Base rectangle -->
                    <rect x="524" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />

                    <!-- Risk Sub-Block (Click to capacity-boundary) -->
                    <g onclick="window.__s1Focus&&window.__s1Focus('capacity-boundary')" style="cursor:pointer;">
                        <text x="539" y="52" font-size="11.5" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">IZDEGŠANAS ROBEŽA (${band.lv})</text>
                        <rect x="539" y="58" width="206" height="12" rx="6" fill="url(#riskGrad)" />
                        <!-- Pointer -->
                        <polygon points="${riskX},73 ${riskX - 5},80 ${riskX + 5},80" fill="#1e293b" />
                        <!-- Divās rindās: garie enkuru nosaukumi (piem. "Uzņēmējdarbība un radīšana")
                             vienā rindā izgāja ārpus bloka (SVG overflow:hidden → teksts apgriezās). -->
                        <text x="539" y="91" font-size="10.5" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">Kritiskais trigeris:</text>
                        <text x="539" y="107" font-size="13" font-weight="800" fill="#334155" font-family="'Outfit',sans-serif"${svgFit(b.lv, 206, 13)}>${b.lv}</text>
                    </g>

                    <!-- Divider line -->
                    <line x1="539" y1="112" x2="745" y2="112" stroke="#e2e8f0" stroke-width="1" />

                    <!-- Bazi Sub-Block (Click to capacity-drive) -->
                    <g onclick="window.__s1Focus&&window.__s1Focus('capacity-drive')" style="cursor:pointer;">
                        <text x="539" y="129" font-size="11.5" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">BĀZES DZINĒJSPĒKS (BAZI)</text>
                        
                        <!-- Inner card -->
                        <rect x="539" y="138" width="206" height="124" rx="10" fill="${baziColor}10" stroke="${baziColor}" stroke-width="1.5" />
                        
                        <foreignObject x="549" y="148" width="186" height="104">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.35; color:#1e293b;">
                                <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Identitāte:</div>
                                <div style="font-size:13px; font-weight:800; color:${ink(baziColor)}; margin-bottom:6px;">${dmName}</div>
                                
                                <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Stils &amp; Loma:</div>
                                <div style="font-size:12px; font-weight:800; color:#1e293b; margin-bottom:2px;">${godLv(mainGod)}</div>
                                <div style="font-size:11px; color:#475569;">${godInfo.role}</div>
                            </div>
                        </foreignObject>

                        <!-- Subtext under Bazi -->
                        <foreignObject x="539" y="272" width="206" height="125">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:13px; line-height:1.4; text-align:left;">
                                Šis rīcības dzinējs nosaka dabisko pieeju darbam un lēmumiem. Atbalsta resurss zemapziņā palīdz atgūt fokusu.
                            </div>
                        </foreignObject>
                    </g>
                </g>
            </svg>
            <div style="text-align:center; font-size:0.82rem; color:#94a3b8; margin-top:0.3rem;">Klikšķini uz bloka, lai izceltu tā pilno aprakstu zemāk</div>
        </div>`;
    })();

    const s2Body = `
        <p>Šī sadaļa nav profesiju saraksts — tā ir <b>lēmumu pieņemšanas dinamikas</b> karte. Apvienojot Edgara Šeina <b>Karjeras enkurus</b> (MIT Sloan), Deivida Snoudena <b>Cynefin</b> domēnus un Daniela Kānemana <b>Sistēma 1/2</b> dalījumu, sistēma atklāj, kā šī profila iekšējais dzinējs sadarbojas ar darba vidi un izpildes mehānismu. Big Five / RIASEC (Holland · O*NET) un BaZi (10 Dievi + Daymaster stiprums) dati šeit kalpo kā matemātiskā bāze biznesa inteliģences izvadam.</p>
        ${confNoteHtml}
        ${q('Kāds ir šī cilvēka karjeras dzinējs? · Kādā vidē tas neuzkaras? · Kāda loma izpildei dabiska? · Kas izraisīs izdegšanu?')}

        ${capacitySvgHtml}

        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.9rem; align-items:start;">

        <div id="capacity-summary" style="min-width:0;">${narrativeHtml}</div>

        <div id="capacity-anchors" style="min-width:0;">
            ${sectionTitle('Karjeras enkuri — Šeina kapacitātes spektrs', '#6d28d9')}
            ${anchorsHtml}
        </div>

        <div id="capacity-boundary" style="min-width:0;">${boundaryHtml}</div>

        <div id="capacity-cynefin" style="min-width:0;">
            ${sectionTitle('Lēmumu konteksts — Cynefin domēni', '#1d4ed8')}
            ${cynefinHtml}
        </div>

        <div id="capacity-kahneman" style="min-width:0;">
            ${sectionTitle('Kognitīvais stils — Kānemana polaritāte', '#b45309')}
            ${kahnemanHtml}
        </div>

        <div id="capacity-cheatsheet" style="min-width:0;">${cheatSheetHtml}</div>

        <div id="capacity-drive" style="min-width:0;">
            ${sectionTitle('Karjeras dzinējspēks un profesionālā bāze', godInfo.color)}
            <div style="background:${godInfo.color}12; border:1px solid ${godInfo.color}35; border-radius:12px; padding:1.1rem 1.2rem; margin-bottom:0.5rem; display:flex; flex-direction:column; justify-content:space-between; min-height:300px;">
                <div>
                    <!-- Iekšējā identitāte -->
                    <div style="margin-bottom:0.75rem;">
                        <div style="font-size:0.68rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">Iekšējā darba identitāte</div>
                        <div style="font-size:0.95rem; font-weight:800; color:#1e293b;">${dmName}</div>
                        <div style="font-size:0.78rem; color:#64748b; margin-top:2px;">Rīcības dzinējs: <b style="color:#1e293b;">${dmStrengthLabel}</b></div>
                    </div>
                    
                    <!-- Primārais darbības stils -->
                    <div style="margin-bottom:0.75rem; padding-top:0.6rem; border-top:1px solid ${godInfo.color}20;">
                        <div style="font-size:0.68rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">Darbības stils</div>
                        <div style="font-size:0.92rem; font-weight:800; color:${ink(godInfo.color)};">${godLv(mainGod)}</div>
                        <div style="font-size:0.8rem; color:#334155; margin-top:2px; font-weight:600;">${godInfo.role}</div>
                        ${hiddenGod ? `<div style="font-size:0.75rem; color:#64748b; margin-top:2px;">Atbalsta resurss zemapziņā: <b style="color:#1e293b;">${godLv(hiddenGod)}</b></div>` : ''}
                    </div>

                    <!-- Stils un Dzinulis -->
                    <div style="padding-top:0.6rem; border-top:1px solid ${godInfo.color}20; color:#475569; font-size:0.82rem; line-height:1.55;">
                        ${godInfo.style}
                    </div>
                    ${godInfo.coreDrive ? `
                        <div style="margin-top:0.6rem; color:#0f172a; font-size:0.82rem; line-height:1.55; font-style:italic; border-left:2px solid ${godInfo.color}60; padding-left:8px;">
                            "${godInfo.coreDrive}"
                        </div>
                    ` : ''}
                </div>

                <!-- Riski / Ahileja papēdis -->
                ${godInfo.shadow ? `
                    <div style="margin-top:0.8rem; font-size:0.78rem; color:#991b1b; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; padding:0.5rem 0.75rem;">
                        <b style="color:#b91c1c;">⚠️ Profesionālais risks:</b> ${godInfo.shadow}
                    </div>
                ` : ''}
            </div>
        </div>

        <div id="capacity-reputation" style="min-width:0;">
            ${sectionTitle('Reputācijas un tirgus uztveres signāli', '#b45309')}
            <p style="color:#64748b; font-size:0.82rem; line-height:1.5; margin:0 0 0.6rem 0;">Profesionālie reputācijas enkuri — slēptās priekšrocības un "tagi", ko tirgus uztver un novērtē sadarbībā.</p>
            <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:0.9rem;">
                ${(() => {
                    const starTranslations = {
                        'Noble Man (Dižciltīgais)': {
                            name: 'Sociālais kapitāls un mentoru atbalsts',
                            desc: 'Spēja krīzes situācijās piesaistīt spēcīgus sabiedrotos, padomdevējus vai mentorus. Dabiska aizsardzība pret krasām reputācijas krīzēm.'
                        },
                        'Peach Blossom (Persiku Zieds)': {
                            name: 'Personīgais magnētisms un harizma',
                            desc: 'Dabiska pievilcība un spēja veidot uzticību. Izcila piemērotība mārketingam, sarunām, publiskajām uzstāšanām un komandas iedvesmošanai.'
                        },
                        'Traveling Horse (Ceļojošais Zirgs)': {
                            name: 'Globālā mobilitāte un ekspansija',
                            desc: 'Augsta gatavība pārmaiņām un ekspansijai. Piemērotība ceļojumiem, starptautiskiem projektiem un darbam straujā ritmā.'
                        }
                    };
                    return symStars.length ? symStars.map(ss => {
                        const trans = starTranslations[ss.star] || { name: ss.star, desc: ss.desc || '' };
                        return `
                            <div style="background:#fffbeb; border:1px solid #b4530930; border-radius:10px; padding:0.65rem 0.85rem; border-left:3px solid #b45309;">
                                <div style="font-weight:800; color:#b45309; font-size:0.82rem; margin-bottom:2px;">✨ ${trans.name}</div>
                                <div style="color:#475569; font-size:0.78rem; line-height:1.45;">${trans.desc}</div>
                            </div>
                        `;
                    }).join('') : `<div style="color:#64748b; font-size:0.8rem; font-style:italic;">Nav identificētu ārējās reputācijas enkuru.</div>`;
                })()}
            </div>

            ${sectionTitle('Slēptie stratēģiskie resursi — Vēdu yogas', '#6d28d9')}
            <p style="color:#64748b; font-size:0.82rem; line-height:1.5; margin:0 0 0.5rem 0;">Vēdu yogas kā dabiskie pārsvari tirgū — talanti, ko izmantot kā konkurences priekšrocību.</p>
            ${yogaHtml}
        </div>

        <div id="capacity-niches" style="min-width:0;">
            ${sectionTitle('Dabiskās tirgus nišas — fokusa nozares', '#15803d')}
            <p style="color:#64748b; font-size:0.82rem; line-height:1.5; margin:0 0 0.5rem 0;">Vides, kur kapacitāte un izpildes stils sastopas dabiski.</p>
            <div style="margin-bottom:0.3rem; display:flex; gap:0.7rem; flex-wrap:wrap;">
                <span style="font-size:0.68rem; color:#047857;">● Zone 2-3 (ieejas)</span>
                <span style="font-size:0.68rem; color:#6d28d9;">● Zone 4-5 (virsotnes)</span>
            </div>
            <div style="margin-bottom:0.5rem;">${careerHtml}</div>

            ${sectionTitle('Stratēģiskais laika logs — BaZi veiksmes pīlāri', '#1d4ed8')}
            <p style="color:#64748b; font-size:0.82rem; line-height:1.5; margin:0;">Katrā dekādē mainās optimālā stratēģija. Pilnais kalendārs apskatāms <b>3. sadaļā "Stratēģiska laika plānošana"</b>.</p>
        </div>

        </div>`;

    const s2 = card('2',
        'Karjeras struktūras modelēšana un lēmumu dizains (Kapacitāte)',
        'Augsta',
        ['Šeina Karjeras enkuri', 'Cynefin', 'Kānemans S1/S2', 'Big Five / RIASEC', 'Ķīnas BaZi', 'Vēdu Yoga'],
        s2Body,
        'multi-system'
    );

    // ── 3: LAIKA PLĀNOŠANA (DASHA + VEIKSMES PĪLĀRI) ────────────────────────
    const currentDasha = profile?.vedic?.current_dasha;
    const allDashas    = profile?.vedic?.all_dashas || [];

    const fmtDate = (d) => {
        if (!d) return '—';
        try {
            const dt = (d instanceof Date) ? d : new Date(d);
            return dt.toLocaleDateString('lv-LV', { year: 'numeric', month: 'short' });
        } catch { return '—'; }
    };

    // Dasha timeline — show current + next 4
    const now = new Date();
    const futureDashas = allDashas.filter(d => {
        const end = (d.end instanceof Date) ? d.end : new Date(d.end);
        return end > now;
    }).slice(0, 5);

    const dashaColors = {
        'Saule':    '#b45309', 'Mēness':   '#475569', 'Marss':    '#b91c1c',
        'Rahu':     '#7e22ce', 'Jupiters': '#b45309', 'Saturns':  '#64748b',
        'Merkurs':  '#15803d', 'Ketū':     '#64748b', 'Venera':   '#be185d',
    };
    const dashaTimelineHtml = futureDashas.length ? futureDashas.map((d, i) => {
        const isCurrent = d === currentDasha;
        const lordLv = (d.lord || '—').replace(/^Meness$/, 'Mēness');
        const col = dashaColors[lordLv] || '#64748b';
        const startD = (d.start instanceof Date) ? d.start : new Date(d.start);
        const endD   = (d.end   instanceof Date) ? d.end   : new Date(d.end);
        const totalMs  = endD.getTime() - startD.getTime();
        const passedMs = Math.max(0, now.getTime() - startD.getTime());
        const pct = totalMs > 0 ? Math.min(100, Math.round(passedMs / totalMs * 100)) : 0;
        return `
        <div style="background:#f8fafc; border-radius:10px; padding:0.9rem 1.1rem; border-left:4px solid ${isCurrent ? col : col + '50'};">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.4rem;">
                <div>
                    <span style="font-weight:800; color:${ink(col)}; font-size:0.95rem;">${lordLv} Daša</span>
                    ${isCurrent ? `<span style="background:${col}25; color:${ink(col)}; border:1px solid ${col}50; border-radius:6px; padding:1px 8px; font-size:0.72rem; font-weight:700; margin-left:8px;">AKTĪVS</span>` : ''}
                </div>
                <span style="color:#64748b; font-size:0.82rem;">${fmtDate(d.start)} – ${fmtDate(d.end)}</span>
            </div>
            ${isCurrent ? `<div style="margin-top:6px; background:#f1f5f9; border-radius:4px; height:4px; overflow:hidden;"><div style="height:100%; width:${pct}%; background:${col};"></div></div>` : ''}
        </div>`;
    }).join('') : `<div style="color:#475569; font-size:0.87rem;">Dašas periodi nav aprēķināti</div>`;

    // ── Stratēģiskā Timing modeļa dati ───────────────────────────────────────
    const timing = profile?.timing || null;

    const timingSvgHtml = (() => {
        if (!timing || !timing.macro) return '';
        const m = timing.macro;
        const t = timing.tactical || {};
        const al = timing.anchorAlignment || {};
        const trans = m.transition || {};
        
        // 1. Dekāžu fāzes (Luck Pillars)
        const pillars = relevantPillars.slice(0, 5);
        const pillarBlocks = pillars.map((lp, idx) => {
            const isCurrent = idx === 0;
            const stemObj = (typeof lp.stem === 'object') ? lp.stem : { name: lp.stem || '—', element: '' };
            const elemColor = elemColors[stemObj.element] || '#64748b';
            const x = 20 + idx * 148;
            const y = 35;
            const label = lp.interpretation ? lp.interpretation.godLabel : `${stemObj.polarity || ''} ${stemObj.element || ''}`;
            const ageRange = `${lp.ageStart ?? '?'}–${lp.ageEnd ?? '?'} gadi`;
            
            return `
                <g onclick="window.__s1Focus&&window.__s1Focus('timing-pillars')" style="cursor:pointer;">
                    <!-- Savienojuma bulta -->
                    ${idx > 0 ? `<path d="M ${x - 8} ${y + 40} L ${x - 2} ${y + 40} M ${x - 5} ${y + 36} L ${x - 2} ${y + 40} L ${x - 5} ${y + 44}" stroke="#cbd5e1" stroke-width="2" fill="none" />` : ''}
                    
                    <rect x="${x}" y="${y}" width="140" height="80" rx="12" fill="${elemColor}${isCurrent ? '15' : '08'}" stroke="${elemColor}" stroke-width="${isCurrent ? '2.5' : '1'}" />
                    <text x="${x + 70}" y="${y + 22}" text-anchor="middle" font-size="12" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">${ageRange}</text>
                    <foreignObject x="${x + 6}" y="${y + 28}" width="128" height="46">
                        <div xmlns="http://www.w3.org/1999/xhtml" style="height:100%; display:flex; align-items:center; justify-content:center; text-align:center; font-family:'Outfit',sans-serif; overflow:hidden; line-height:1.25;">
                            <div style="font-size:13px; font-weight:800; color:${ink(elemColor)};">${label}</div>
                        </div>
                    </foreignObject>
                    ${isCurrent ? `
                        <rect x="${x + 35}" y="${y - 8}" width="70" height="16" rx="4" fill="${elemColor}" />
                        <text x="${x + 70}" y="${y + 4}" text-anchor="middle" font-size="10" font-weight="900" fill="#ffffff" font-family="'Outfit',sans-serif">AKTĪVS</text>
                    ` : ''}
                </g>
            `;
        }).join('');

        // 2. Vimshottari Dashas (Dzīves fona cikli) ar proporcionālu platumu
        const dashaBlocks = (() => {
            if (!futureDashas.length) return '';
            const totalW = 740; // 760 - 20
            const startT = new Date(futureDashas[0].start).getTime();
            const lastD = futureDashas[Math.min(3, futureDashas.length - 1)];
            const endT = new Date(lastD.end).getTime();
            const spanT = endT - startT;
            if (spanT <= 0) return '';
            
            return futureDashas.slice(0, 4).map((d, idx) => {
                const lordLv = (d.lord || '—').replace(/^Meness$/, 'Mēness');
                const col = dashaColors[lordLv] || '#64748b';
                const sT = new Date(d.start).getTime();
                const eT = new Date(d.end).getTime();
                const x = 20 + ((sT - startT) / spanT) * totalW;
                const w = ((eT - sT) / spanT) * totalW - 4; // gap 4px
                const isCurrent = d === currentDasha;
                
                return `
                    <g onclick="window.__s1Focus&&window.__s1Focus('timing-dasha')" style="cursor:pointer;">
                        <rect x="${x}" y="158" width="${Math.max(45, w)}" height="36" rx="6" fill="${col}${isCurrent ? '20' : '0a'}" stroke="${col}" stroke-width="${isCurrent ? '2' : '1'}" />
                        <text x="${x + Math.max(45, w)/2}" y="181" text-anchor="middle" font-size="13" font-weight="${isCurrent ? '800' : '600'}" fill="${ink(col)}" font-family="'Outfit',sans-serif">${lordLv}</text>
                    </g>
                `;
            }).join('');
        })();

        // 3. Apakšējās 3 kartītes
        // A. Taktiskais gads
        const tColor = t.phaseMeta?.color || '#b45309';
        const tLabel = t.phaseMeta?.lv || 'Nav fokusa';
        const tGodName = t.liuNianGod ? godLv(t.liuNianGod) : '—';
        const cardA = `
            <g onclick="window.__s1Focus&&window.__s1Focus('timing-tactical')" style="cursor:pointer;">
                <rect x="20" y="240" width="236" height="160" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                <text x="35" y="264" font-size="13" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">🎯 OPERATĪVAIS GADS (${t.year || ''})</text>
                
                <foreignObject x="35" y="274" width="206" height="85">
                    <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#1e293b; line-height:1.35;">
                        <div style="font-size:13px; font-weight:800; color:${ink(tColor)}; margin-bottom:4px;">Fokuss: ${tLabel}</div>
                        <div style="font-size:12px; color:#475569;">Dzinējspēks: <b style="color:#0f172a;">${tGodName}</b></div>
                    </div>
                </foreignObject>
                ${t.alignedWithMacro 
                    ? `<rect x="35" y="368" width="125" height="20" rx="4" fill="${tColor}25" stroke="${tColor}60" stroke-width="1"/>
                       <text x="97.5" y="382" text-anchor="middle" font-size="10" font-weight="800" fill="${ink(tColor)}" font-family="'Outfit',sans-serif">⇄ SAKRĪT AR MAKRO</text>`
                    : `<rect x="35" y="368" width="125" height="20" rx="4" fill="#fffbeb" stroke="#b4530960" stroke-width="1"/>
                       <text x="97.5" y="382" text-anchor="middle" font-size="10" font-weight="800" fill="#b45309" font-family="'Outfit',sans-serif">↔ OPERATĪVAIS LOGS</text>`}
            </g>
        `;

        // B. Enkuru saskaņa
        let alColor = '#64748b';
        let alBg = '#f8fafc';
        let alLabel = 'Neitrāls';
        let alDesc = 'Enkurs nekonfliktē ar fāzi.';
        if (al.status === 'aligned') {
            alColor = '#15803d';
            alBg = '#f0fdf4';
            alLabel = 'Saskaņa';
            alDesc = 'Primārais enkurs dabiski plūst kopā ar 10 gadu cikla fāzi.';
        } else if (al.status === 'conflict') {
            alColor = '#b91c1c';
            alBg = '#fef2f2';
            alLabel = 'Konflikts';
            alDesc = 'Enkurs un fāze prasa atšķirīgas pieejas. Vajadzīgs alternatīvs ceļš.';
        }
        
        const cardB = `
            <g onclick="window.__s1Focus&&window.__s1Focus('timing-alignment')" style="cursor:pointer;">
                <rect x="272" y="240" width="236" height="160" rx="12" fill="${alBg}" stroke="${alColor}40" stroke-width="1" />
                <text x="287" y="264" font-size="13" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">⚖ ENKURA SASKAŅA</text>
                
                <foreignObject x="287" y="274" width="206" height="85">
                    <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#334155; line-height:1.35;">
                        <div style="font-size:13px; font-weight:800; color:${alColor}; margin-bottom:4px;">Statuss: ${alLabel}</div>
                        <div style="font-size:12px; color:#475569;">${alDesc}</div>
                    </div>
                </foreignObject>
                
                <rect x="287" y="368" width="115" height="20" rx="4" fill="${alColor}15" />
                <text x="344.5" y="382" text-anchor="middle" font-size="10" font-weight="800" fill="${alColor}" font-family="'Outfit',sans-serif">${al.anchorLv || 'Nav enkura'}</text>
            </g>
        `;

        // C. Pārejas zona & Riski
        let cColor = '#15803d';
        let cBg = '#f0fdf4';
        let cLabel = 'Stabils fāzes vidus';
        let cDesc = 'Šobrīd nav pārejas fāzu turbulences. Drošs logs ilgtermiņa investīcijām.';
        if (trans.active) {
            cColor = '#b91c1c';
            cBg = '#fef2f2';
            cLabel = trans.kind === 'entering' ? 'Pārejas sākums' : 'Pārejas noslēgums';
            cDesc = 'Turbulences logs. Nav ieteicams pieņemt lielus, neatgriezeniskus lēmumus.';
        }

        const targetRiskId = trans.active ? 'timing-transition' : 'timing-macro';

        const cardC = `
            <g onclick="window.__s1Focus&&window.__s1Focus('${targetRiskId}')" style="cursor:pointer;">
                <rect x="524" y="240" width="236" height="160" rx="12" fill="${cBg}" stroke="${cColor}40" stroke-width="1" />
                <text x="539" y="264" font-size="13" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">⚠️ PĀREJA & RISKI</text>
                
                <foreignObject x="539" y="274" width="206" height="85">
                    <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#334155; line-height:1.35;">
                        <div style="font-size:13px; font-weight:800; color:${cColor}; margin-bottom:4px;">${cLabel}</div>
                        <div style="font-size:12px; color:#475569;">${cDesc}</div>
                    </div>
                </foreignObject>
                
                <rect x="539" y="368" width="125" height="20" rx="4" fill="${cColor}15" />
                <text x="601.5" y="382" text-anchor="middle" font-size="10" font-weight="800" fill="${cColor}" font-family="'Outfit',sans-serif">${trans.active ? 'TURBULENCES LOGS' : 'STABILS PERIODS'}</text>
            </g>
        `;

        return `
        <div style="max-width:820px; margin:1rem auto 1.5rem;">
            <div style="text-align:center; font-size:0.92rem; color:#475569; font-weight:700; margin-bottom:0.25rem;">🧭 Stratēģiskā laika karte</div>
            <div style="text-align:center; font-size:0.8rem; color:#94a3b8; margin-bottom:0.8rem;">Vizuāls kopsavilkums: 10 gadu makro fāzes, dzīves fona cikli un pašreizējais audits.</div>
            <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-width:820px; margin:0 auto;">
                <!-- Labels -->
                <text x="20" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">10 GADU STRATĒĢISKĀS FĀZES (DEKĀDES)</text>
                ${pillarBlocks}

                <text x="20" y="148" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">DZĪVES UN KARJERAS FONA CIKLI</text>
                ${dashaBlocks}

                <text x="20" y="224" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">PAŠREIZĒJĀ BRĪŽA STRATĒĢISKAIS AUDITS</text>
                ${cardA}
                ${cardB}
                ${cardC}
            </svg>
            <div style="text-align:center; font-size:0.82rem; color:#94a3b8; margin-top:0.3rem;">Klikšķini uz bloka, lai izceltu tā pilno aprakstu zemāk</div>
        </div>`;
    })();

    const macroHtml = timing?.macro ? (() => {
        const m = timing.macro;
        const pm = m.phaseMeta;
        // Distribūcijas joslas dati — visi 5 fāžu skori
        const phaseItems = Object.entries(m.allPhaseScores || {}).map(([k, score]) => {
            const meta = m.allPhaseMeta?.[k] || {};
            return { key: k, label: meta.lv || k, color: meta.color || '#64748b', icon: meta.icon, score };
        });
        const distribution = phaseItems.length ? `
            <div style="background:#f8fafc; border-radius:10px; padding:0.85rem 1.05rem; margin-top:1rem;">
                ${distributionBar(phaseItems, m.phaseKey, {
                    title: 'Visu 5 biznesa fāžu skoru spektrs',
                    desc:  'Pilns pīlāru kopskora sadalījums — kā pašreizējais fāzes intensitāte salīdzinās ar pārējām piecām'
                })}
            </div>
        ` : '';
        return `
            <div id="timing-macro" style="background:linear-gradient(135deg, ${pm.color}14 0%, #ffffff 100%); border:1px solid ${pm.color}40; border-radius:14px; padding:1.5rem 1.7rem; margin-bottom:1.2rem; box-shadow:0 2px 10px ${pm.color}15;">
                <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.5rem;">
                    <span style="font-size:1.4rem;">${pm.icon}</span>
                    <div style="font-size:0.72rem; font-weight:800; color:${ink(pm.color)}; text-transform:uppercase; letter-spacing:2px;">Makro horizonts · 10 gadu fāze</div>
                </div>
                <h3 style="margin:0 0 0.3rem 0; color:#1e293b; font-size:1.25rem; font-weight:900;">${pm.lv}</h3>
                <div style="color:#64748b; font-size:0.85rem; margin-bottom:0.9rem;">${pm.en} · Vecuma logs: <b style="color:${ink(pm.color)};">${m.ageStart}–${m.ageEnd} gadi</b> <span style="color:#475569;">(šobrīd ${m.currentAge} gadi)</span></div>
                <div style="color:#334155; font-size:0.95rem; line-height:1.75; margin-bottom:0.7rem;">${pm.summary}.</div>
                <div style="color:#334155; font-size:0.88rem; line-height:1.7; font-style:italic;">${pm.depth}.</div>
                ${distribution}
                ${m.transition.active ? `
                    <div id="timing-transition" style="margin-top:1rem; background:#fef2f2; border:1px solid #b91c1c60; border-left:4px solid #b91c1c; border-radius:10px; padding:0.85rem 1.1rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                            <span>⚠️</span>
                            <div style="font-size:0.7rem; font-weight:800; color:#b91c1c; text-transform:uppercase; letter-spacing:1.5px;">Stratēģiskās pārejas un turbulences logs</div>
                        </div>
                        <div style="color:#991b1b; font-size:0.85rem; line-height:1.6; margin-bottom:0.35rem;">${m.transition.description}.</div>
                        <div style="color:#b91c1c; font-size:0.8rem; line-height:1.55; font-style:italic;">Šajā logā nav ieteicams pieņemt neatgriezeniskus lielus lēmumus — ilgtermiņa saistības, milzu investīcijas vai 10+ gadu kontraktus — jo pēc pārejas beigām uz tiem tiks skatīts ar pilnīgi citām acīm.</div>
                    </div>
                ` : ''}
            </div>
        `;
    })() : '';

    const tacticalHtml = timing?.tactical ? (() => {
        const t = timing.tactical;
        const tm = t.phaseMeta;
        return `
            <div id="timing-tactical" style="background:#f8fafc; border:1px solid ${tm.color}35; border-left:4px solid ${tm.color}; border-radius:12px; padding:1.2rem 1.4rem; margin-bottom:1.2rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                    <span style="font-size:1.1rem;">🎯</span>
                    <div style="font-size:0.7rem; font-weight:800; color:${ink(tm.color)}; text-transform:uppercase; letter-spacing:1.5px;">Taktiskais operatīvais logs · ${t.year}</div>
                </div>
                <div style="color:#1e293b; font-size:1rem; font-weight:800; margin-bottom:0.3rem;">${t.year}. gada fokuss: ${tm.lv}</div>
                <div style="color:#64748b; font-size:0.82rem; margin-bottom:0.65rem;">Gada taktiskais dzinējspēks: <b style="color:${ink(tm.color)};">${godLv(t.liuNianGod)}</b> ${t.alignedWithMacro ? `<span style="background:${tm.color}30; color:${ink(tm.color)}; border-radius:5px; padding:1px 7px; font-size:0.68rem; font-weight:700; margin-left:6px;">⇄ SAKRĪT AR MAKRO</span>` : `<span style="background:#fffbeb; color:#b45309; border:1px solid #b4530940; border-radius:5px; padding:1px 7px; font-size:0.68rem; font-weight:700; margin-left:6px;">↔ TAKTISKAIS LOGS</span>`}</div>
                <div style="color:#334155; font-size:0.88rem; line-height:1.7;">${t.narrative}.</div>
            </div>
        `;
    })() : '';

    // Šeina enkurs × Fāze: saskaņojuma audits (stratēģiskais brīdinājums vai apstiprinājums)
    const alignmentHtml = timing?.anchorAlignment ? (() => {
        const al = timing.anchorAlignment;
        if (al.status === 'conflict' && al.detail) {
            const d = al.detail;
            const phaseLv = timing.macro?.phaseMeta?.lv || '—';
            const ageEnd = timing.macro?.ageEnd || '—';
            return `
                <div id="timing-alignment" style="background:#fef2f2; border:2px solid #b91c1c60; border-left:5px solid #b91c1c; border-radius:14px; padding:1.3rem 1.5rem; margin-bottom:1.2rem; box-shadow:0 4px 20px rgba(239,68,68,0.15);">
                    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.7rem;">
                        <span style="font-size:1.4rem;">⚠️</span>
                        <div style="font-size:0.72rem; font-weight:800; color:#b91c1c; text-transform:uppercase; letter-spacing:2px;">Stratēģiskais brīdinājums · enkurs konfliktē ar fāzi</div>
                    </div>
                    <div style="color:#7f1d1d; font-size:0.95rem; line-height:1.75; margin-bottom:0.7rem;">
                        Profila primārais Šeina enkurs ir <b>${al.anchorLv}</b> — dabiskais instinkts ir ${d.instinct}. Taču pašreizējais 10 gadu cikls (līdz aptuveni ${ageEnd} gadu vecumam) — <b>${phaseLv}</b>; tas pieprasa ${d.demand}.
                    </div>
                    <div style="color:#991b1b; font-size:0.9rem; line-height:1.7; margin-bottom:0.6rem;"><b style="color:#b91c1c;">Risks:</b> ${d.warning}.</div>
                    <div style="background:#fffbeb; border-left:3px solid #b45309; border-radius:0 8px 8px 0; padding:0.7rem 0.95rem; color:#92400e; font-size:0.88rem; line-height:1.65;">
                        <b style="color:#b45309;">Stratēģiskais alternatīvais ceļš:</b> ${d.alternative}.
                    </div>
                </div>
            `;
        }
        if (al.status === 'aligned') {
            return `
                <div id="timing-alignment" style="background:#f0fdf4; border:1px solid #15803d40; border-left:4px solid #15803d; border-radius:12px; padding:1.1rem 1.3rem; margin-bottom:1.2rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                        <span style="font-size:1.1rem;">✅</span>
                        <div style="font-size:0.7rem; font-weight:800; color:#047857; text-transform:uppercase; letter-spacing:1.5px;">Stratēģiskais saskaņojums · enkurs un fāze plūst kopā</div>
                    </div>
                    <div style="color:#334155; font-size:0.9rem; line-height:1.7;">${al.narrative}.</div>
                </div>
            `;
        }
        if (al.status === 'neutral') {
            return `
                <div id="timing-alignment" style="background:#f8fafc; border:1px solid #64748b40; border-left:4px solid #64748b; border-radius:12px; padding:1.1rem 1.3rem; margin-bottom:1.2rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                        <span style="font-size:1.1rem;">⚖</span>
                        <div style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:1.5px;">Stratēģiskā balanss · paralēla stratēģija</div>
                    </div>
                    <div style="color:#334155; font-size:0.88rem; line-height:1.65;">${al.narrative}.</div>
                </div>
            `;
        }
        return '';
    })() : '';

    const matrixHtml = timing?.matrix ? (() => {
        const mx = timing.matrix;
        const doItems = mx.dos.map(d => `<li style="margin-bottom:0.4rem; color:#14532d; line-height:1.5;">${d}</li>`).join('');
        const dontItems = mx.donts.map(d => `<li style="margin-bottom:0.4rem; color:#b91c1c; line-height:1.5;">${d}</li>`).join('');
        return `
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:0.8rem; margin-bottom:1.2rem;">
                <div style="background:#f0fdf4; border:1px solid #04785740; border-left:4px solid #15803d; border-radius:12px; padding:1.1rem 1.3rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.7rem;">
                        <span style="font-size:1.1rem;">✅</span>
                        <div style="font-size:0.7rem; font-weight:800; color:#047857; text-transform:uppercase; letter-spacing:1.5px;">Ieteicams · DO</div>
                    </div>
                    <ul style="margin:0; padding-left:1.2rem; font-size:0.86rem;">${doItems}</ul>
                </div>
                <div style="background:#fef2f2; border:1px solid #b91c1c40; border-left:4px solid #b91c1c; border-radius:12px; padding:1.1rem 1.3rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.7rem;">
                        <span style="font-size:1.1rem;">🛑</span>
                        <div style="font-size:0.7rem; font-weight:800; color:#b91c1c; text-transform:uppercase; letter-spacing:1.5px;">Nav ieteicams · DON'T</div>
                    </div>
                    <ul style="margin:0; padding-left:1.2rem; font-size:0.86rem;">${dontItems}</ul>
                </div>
            </div>
        `;
    })() : '';

    const s3Body = `
        <p>Šī sadaļa nav nākotnes prognozes horoskops — tā ir <b>stratēģiskā laika menedžmenta</b> karte. Apvienojot Daniela Levinsona dzīves gadalaiku teoriju (1978) ar klasisko biznesa dzīves ciklu (Introduction → Growth → Maturity → Pivot), dekāžu fāzes un ikgadējie operatīvie cikli šeit tiek pārtulkoti par <i>kurā fāzē šī profila kapacitāte atrodas tagad</i>, nevis par mistiskām prognozēm.</p>
        ${q('Kurā 10 gadu biznesa fāzē šis profils atrodas? · Vai šis gads sakrīt ar makro stratēģiju vai dod atsevišķu taktisko logu? · Kad ir pārejas turbulences zona, kurā nedrīkst pieņemt neatgriezeniskus lēmumus?')}

        ${timingSvgHtml}

        ${macroHtml}

        ${tacticalHtml}

        ${alignmentHtml}

        ${matrixHtml}

        ${sectionTitle('Dzīves un karjeras fona cikli (laika ass)', '#0369a1')}

        <div id="timing-dasha">
            ${currentDasha ? `
            <div style="background:#0369a120; border:1px solid #0369a140; border-radius:12px; padding:1.1rem 1.3rem; margin-bottom:1rem;">
                <div style="font-size:0.72rem; color:#0369a1; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">Pašreizējais stratēģiskais periods</div>
                <div style="font-size:1.3rem; font-weight:900; color:#1e293b;">${String(currentDasha.lord).replace(/^Meness$/, 'Mēness')} ietekmes cikls</div>
                <div style="color:#64748b; font-size:0.87rem; margin-top:4px;">${fmtDate(currentDasha.start)} → ${fmtDate(currentDasha.end)}</div>
                <p style="margin:0.75rem 0 0 0; color:#64748b; font-size:0.9rem; line-height:1.65;">Katrs cikls aktivizē noteiktu tematisko fonu: ${String(currentDasha.lord).replace(/^Meness$/, 'Mēness')} periods ienes attiecīgā arhetipa tēmas — profesionālās pārmaiņas, attiecību dinamiku un iekšējo izaugsmi — kā dominanto dzīves fona frekvenci.</p>
            </div>` : `<div style="color:#475569; font-size:0.87rem; margin-bottom:1rem;">Aktīvais stratēģiskais periods nav aprēķināts</div>`}

            <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.5rem;">${dashaTimelineHtml}</div>
        </div>

        ${sectionTitle('Dzīves stratēģiskās fāzes (dekāžu kalendārs)', '#b45309')}
        <div id="timing-pillars" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:0.6rem;">${lpHtml}</div>
        <p style="margin-top:1rem; color:#475569; font-size:0.82rem; line-height:1.6; font-style:italic;">Katras dekādes <b>aktīvais stils</b> definē galveno uzvedības modeli — vai šajā fāzē cilvēks ir partneris, eksperts, vadītājs vai pētnieks. <b>Darbības vide</b> nosaka fona kontekstu, kurā šis stils realizēsies visveiksmīgāk.</p>
        <p style="margin-top:1rem; color:#475569; font-size:0.85rem; line-height:1.6;">Kamēr vienkāršoti personības testi sniedz tikai statisku momentuzņēmumu, šis laika modelis analizē dinamiku. Apvienojot divas neatkarīgas laika fāžu aprēķinu sistēmas (dekāžu fāzes un ikgadējos operatīvos ciklus), sistēma spēj identificēt šauros logus (2–3 gadus), kad abu sistēmu rādītāji sakrīt — tieši šādos periodos stratēģisko lēmumu un investīciju atdeve ir visaugstākā.</p>`;

    const s3 = card('3',
        'Stratēģiska laika plānošana un dzīves fāžu (Timing) noteikšana',
        'Augsta',
        ['Levinsona dzīves gadalaiki', 'Biznesa dzīves cikls', 'Operatīvie cikli', 'Dzīves fona cikli'],
        s3Body,
        'moon'
    );

    // ── 4: SADERĪBA (NAKSHATRA + PARTNERA INDIKATORI) ────────────────────────
    const nak    = profile?.vedic?.nakshatra || {};
    const navamsha = profile?.vedic?.navamsha || {};

    // Nakshatra compatibility groups (Nadi classification)
    // Spellings match vedic_kp.js NAKSHATRA_NAMES array exactly
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

    // Gana (nature) mapping — spellings match vedic_kp.js NAKSHATRA_NAMES exactly
    const ganaMap = {
        'Ashwini':'Deva','Mrigashira':'Deva','Punarvasu':'Deva','Pushya':'Deva','Hasta':'Deva','Swati':'Deva','Anuradha':'Deva','Shravana':'Deva','Revati':'Deva',
        'Bharani':'Manushya','Rohini':'Manushya','Ardra':'Manushya','Purva Phalguni':'Manushya','Uttara Phalguni':'Manushya','Purva Ashadha':'Manushya','Uttara Ashadha':'Manushya','Purva Bhadrapada':'Manushya','Uttara Bhadrapada':'Manushya',
        'Krittika':'Rakshasa','Ashlesha':'Rakshasa','Magha':'Rakshasa','Chitra':'Rakshasa','Vishakha':'Rakshasa','Jyeshtha':'Rakshasa','Mula':'Rakshasa','Dhanishta':'Rakshasa','Shatabhisha':'Rakshasa',
    };
    const ganaColors = { 'Deva': '#b45309', 'Manushya': '#1d4ed8', 'Rakshasa': '#b91c1c' };
    const ganaDesc  = {
        'Deva':     'Dievišķā Gana — meklē harmoniju, principus un garīgumu attiecībās. Vislabāk sader ar citiem Deva vai Manushya Gana partneriem.',
        'Manushya': 'Cilvēciskā Gana — pragmatisks un reāls attiecībās. Sader ar jebkuru Gana, ja vērtības sakrīt.',
        'Rakshasa': 'Raksasa Gana — spēcīga, intensīva enerģija. Labākā saderība ar Rakshasa vai stipru Deva partneri.',
    };
    const myGana = ganaMap[nak.nakshatra] || '—';
    const ganaColor = ganaColors[myGana] || '#64748b';
    const myGanaLv = myGana === 'Deva' ? 'Dievišķais (Deva)' : myGana === 'Manushya' ? 'Cilvēciskais (Manushya)' : myGana === 'Rakshasa' ? 'Dinamiskais (Rakshasa)' : myGana;

    const navamshaKeys = Object.keys(navamsha).slice(0, 6);
    const navamshaHtml = navamshaKeys.length ? navamshaKeys.map(p =>
        `<div style="display:flex; justify-content:space-between; align-items:center; padding:5px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-size:0.85rem;">${p}</span>
            <b style="color:#1e293b; font-size:0.88rem;">${navamsha[p]}</b>
        </div>`
    ).join('') : `<div style="color:#475569; font-size:0.85rem;">Nav D9 datu</div>`;

    const relDyn = profile?.relationshipDynamics || null;

    const verdictHtml = relDyn?.verdict ? `
        <div style="background:linear-gradient(135deg, #fdf2f8 0%, #ffffff 100%); border:1px solid #ec489940; border-radius:14px; padding:1.5rem 1.7rem; margin-bottom:1.2rem; box-shadow:0 2px 10px rgba(236,72,153,0.10);">
            <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.85rem;">
                <span style="font-size:1.3rem;">💞</span>
                <div style="font-size:0.72rem; font-weight:800; color:#be185d; text-transform:uppercase; letter-spacing:2px;">Attiecību audita verdikts</div>
            </div>
            <div style="color:#334155; font-size:1rem; line-height:1.8;">${relDyn.verdict}</div>
        </div>
    ` : '';

    const attachmentHtml = relDyn?.attachment?.primary ? (() => {
        const a = relDyn.attachment.primary;
        const allItems = (relDyn.attachment.allScores || []).map(o => ({
            key: o.key, label: o.lv, color: o.color, icon: o.icon, score: o.score
        }));
        return `
            <div style="background:#f8fafc; border:1px solid ${a.color}40; border-left:4px solid ${a.color}; border-radius:12px; padding:1.3rem 1.5rem; margin-bottom:1.2rem;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.85rem;">
                    <div style="display:flex; align-items:center; gap:0.6rem;">
                        <span style="font-size:1.4rem;">${a.icon}</span>
                        <div>
                            <div style="font-size:0.7rem; font-weight:800; color:${ink(a.color)}; text-transform:uppercase; letter-spacing:1.5px;">Piesaistes bāze (Bowlby)</div>
                            <h3 style="margin:2px 0 0 0; color:#1e293b; font-size:1.15rem; font-weight:900;">${a.lv}</h3>
                        </div>
                    </div>
                    <span style="background:${a.color}25; color:${ink(a.color)}; border:1px solid ${a.color}55; border-radius:6px; padding:3px 10px; font-size:0.78rem; font-weight:800;">${a.score}%</span>
                </div>
                <div style="color:#1e293b; font-size:0.92rem; line-height:1.7; margin-bottom:0.7rem;">${a.summary}.</div>
                <div style="color:#334155; font-size:0.86rem; line-height:1.65; margin-bottom:0.6rem;">${a.operatingMode}.</div>
                <div style="background:#f1f5f9; border-left:3px solid ${a.color}80; border-radius:0 8px 8px 0; padding:0.6rem 0.9rem; color:#64748b; font-size:0.83rem; line-height:1.6;"><b style="color:#334155;">Partnera tipa vajadzība:</b> ${a.partnerNeeds}.</div>
                <div style="background:#f8fafc; border-radius:8px; padding:0.85rem 1rem; margin-top:0.85rem; border:1px solid #e2e8f0;">
                    ${distributionBar(allItems, a.key, {
                        title: 'Visu 4 piesaistes stilu spektrs',
                        desc:  'Pilns Boulbija stilu skoru sadalījums — primārais nedominē totāli, citi stili arī aktivizējas dažādās situācijās'
                    })}
                </div>
            </div>
        `;
    })() : '';

    const horsemenHtml = relDyn?.horsemen?.top?.length ? (() => {
        const items = relDyn.horsemen.top.map((h, i) => `
            <div style="background:#f8fafc; border:1px solid ${h.color}40; border-radius:10px; padding:1rem 1.2rem; margin-bottom:0.6rem;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.5rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:1.1rem;">${h.icon}</span>
                        <span style="font-weight:800; color:${ink(h.color)}; font-size:0.98rem;">${h.lv}</span>
                        ${i === 0 ? `<span style="background:${h.color}25; color:${ink(h.color)}; border-radius:5px; padding:1px 7px; font-size:0.65rem; font-weight:800; letter-spacing:0.5px;">PRIMĀRS RISKS</span>` : ''}
                    </div>
                    <span style="color:${ink(h.color)}; font-weight:700; font-size:0.88rem;">${h.score}%</span>
                </div>
                <div style="color:#334155; font-size:0.85rem; line-height:1.6; margin-bottom:0.45rem;">${h.pattern}.</div>
                <div style="background:#f0fdf4; border-left:3px solid #047857; border-radius:0 6px 6px 0; padding:0.55rem 0.85rem; color:#14532d; font-size:0.83rem; line-height:1.55;"><b style="color:#047857;">Gotmana antidots:</b> ${h.antidote}.</div>
            </div>
        `).join('');
        return `<div style="display:flex; flex-direction:column; gap:0.6rem; margin-bottom:1.2rem;">${items}</div>`;
    })() : '';

    const synergyHtml = relDyn?.synergy?.length ? (() => {
        const items = relDyn.synergy.map(s => `
            <div style="background:#f8fafc; border:1px solid ${s.color}30; border-left:3px solid ${s.color}; border-radius:10px; padding:0.85rem 1rem; margin-bottom:0.4rem;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.4rem;">
                    <span style="font-weight:800; color:${ink(s.color)}; font-size:0.92rem;">${s.icon} ${s.lv}</span>
                    <span style="color:${ink(s.color)}; font-weight:700; font-size:0.82rem;">${s.score}%</span>
                </div>
                <div style="color:#334155; font-size:0.83rem; line-height:1.6;">${s.description}.</div>
            </div>
        `).join('');
        return `<div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.2rem;">${items}</div>`;
    })() : '';

    const frictionHtml = relDyn?.friction?.length ? (() => {
        const items = relDyn.friction.map(f => `
            <div style="background:#fef2f2; border:1px solid ${f.color}40; border-left:3px solid ${f.color}; border-radius:10px; padding:0.85rem 1rem; margin-bottom:0.4rem;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.4rem;">
                    <span style="font-weight:800; color:${ink(f.color)}; font-size:0.92rem;">${f.icon} ${f.lv}</span>
                    <span style="color:${ink(f.color)}; font-weight:700; font-size:0.82rem;">${f.score}%</span>
                </div>
                <div style="color:#334155; font-size:0.83rem; line-height:1.6;">${f.description}.</div>
            </div>
        `).join('');
        return `<div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.2rem;">${items}</div>`;
    })() : '';

    const shadowHtml = relDyn?.shadow ? `
        <div id="relationship-shadow" style="background:#faf5ff; border:1px solid #6d28d940; border-left:4px solid #6d28d9; border-radius:12px; padding:1.1rem 1.3rem; margin-bottom:1.2rem;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.55rem;">
                <span style="font-size:1.1rem;">🌑</span>
                <div style="font-size:0.7rem; font-weight:800; color:#6d28d9; text-transform:uppercase; letter-spacing:1.5px;">Ēnas slānis · Lilitas spiediena punkts</div>
            </div>
            <div style="color:#334155; font-size:0.88rem; line-height:1.7; margin-bottom:0.5rem;">${relDyn.shadow}</div>
            <div style="color:#64748b; font-size:0.8rem; line-height:1.55; font-style:italic;">Šis ir slēptais, neapzinātais spiediena paterns, kas aktivizējas augsta stresa vai zaudētas kontroles brīžos. Apzinoties to, var ar to strādāt apzināti, nevis ļaut tam vadīt attiecības no aizkulisēm.</div>
        </div>
    ` : '';

    const relationshipSvgHtml = (() => {
        if (!relDyn) return '';
        const a = relDyn.attachment?.primary || { lv: 'Nav datu', score: 50, color: '#64748b', icon: '💞', summary: '', partnerNeeds: '' };
        const primaryHorseman = relDyn.horsemen?.top?.[0] || { lv: 'Nav riska', score: 0, color: '#16a34a', icon: '✓', pattern: '', antidote: '' };
        const nakshatraName = nak.nakshatra || '—';
        const nakshatraLord = String(nak.lord || '—').replace(/^Meness$/, 'Mēness');
        
        return `
        <div style="max-width:820px; margin:1rem auto 1.5rem;">
            <div style="text-align:center; font-size:0.92rem; color:#475569; font-weight:700; margin-bottom:0.25rem;">🧭 Attiecību dinamikas &amp; Saderības karte</div>
            <div style="text-align:center; font-size:0.8rem; color:#94a3b8; margin-bottom:0.8rem;">Vizuāls kopsavilkums: piesaistes stils, konfliktu trigeri un Vēdu saderības ievaddati.</div>
            <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-width:820px; margin:0 auto;">
                <text x="20" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">EMOCIONĀLĀ DROŠĪBA (Bowlby)</text>
                <text x="272" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">STRĪDU UZVEDĪBA (Gottman)</text>
                <text x="524" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">VĒDU SADERĪBAS AUDITS</text>
                <g onclick="window.__s1Focus&&window.__s1Focus('relationship-attachment')" style="cursor:pointer;">
                    <rect x="20" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="32" y="50" width="212" height="80" rx="10" fill="${a.color}15" stroke="${a.color}" stroke-width="2" />
                    ${svgFramedTitle(44, `${a.icon} ${a.lv}`, 'TAVS PIESAISTES STILS', 15, ink(a.color), 140, 192)}
                    <text x="232" y="76" font-size="18" font-weight="900" fill="${ink(a.color)}" text-anchor="end" font-family="'Outfit',sans-serif">${a.score}%</text>
                    <foreignObject x="32" y="145" width="212" height="250">
                        <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:12px; line-height:1.42; text-align:left; overflow-y:auto; height:245px; padding-right:4px;">
                            <div style="margin-bottom:8px; color:#64748b; font-style:italic;">Piesaistes modelis parāda, kā tu reaģē uz emocionālu tuvību un bailēm zaudēt saikni ar otru cilvēku.</div>
                            <div style="margin-bottom:8px; color:#334155;"><b>Uzvedība attiecībās:</b> ${a.summary}</div>
                            <div style="background:#ffffff; border-left:3px solid ${a.color}80; padding:6px 8px; border-radius:0 6px 6px 0; font-size:11.5px; color:#475569;">
                                <b>Attiecību vajadzība:</b> ${a.partnerNeeds}
                            </div>
                        </div>
                    </foreignObject>
                </g>
                <g onclick="window.__s1Focus&&window.__s1Focus('relationship-horsemen')" style="cursor:pointer;">
                    <rect x="272" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="284" y="50" width="212" height="80" rx="10" fill="${primaryHorseman.color}15" stroke="${primaryHorseman.color}" stroke-width="2" />
                    <text x="296" y="75" font-size="15" font-weight="900" fill="${ink(primaryHorseman.color)}" font-family="'Outfit',sans-serif">${primaryHorseman.icon} ${primaryHorseman.lv}</text>
                    <text x="296" y="96" font-size="11" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">GALVENAIS STRĪDU RISKS</text>
                    <text x="484" y="76" font-size="18" font-weight="900" fill="${ink(primaryHorseman.color)}" text-anchor="end" font-family="'Outfit',sans-serif">${primaryHorseman.score}%</text>
                    <foreignObject x="284" y="145" width="212" height="250">
                        <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:12px; line-height:1.42; text-align:left; overflow-y:auto; height:245px; padding-right:4px;">
                            <div style="margin-bottom:8px; color:#64748b; font-style:italic;">Gotmana tests parāda, kurš no graujošajiem strīdu riskiem ir tava neapzinātā vājā vieta krīzes brīžos.</div>
                            <div style="margin-bottom:8px; color:#334155;"><b>Tavs risks stresa brīžos:</b> ${primaryHorseman.pattern}</div>
                            <div style="background:#f0fdf4; border-left:3px solid #047857; padding:6px 8px; border-radius:0 6px 6px 0; font-size:11.5px; color:#14532d;">
                                <b>Risinājums (Antidots):</b> ${primaryHorseman.antidote}
                            </div>
                        </div>
                    </foreignObject>
                </g>
                <g>
                    <rect x="524" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <g onclick="window.__s1Focus&&window.__s1Focus('relationship-vedic')" style="cursor:pointer;">
                        <rect x="536" y="50" width="212" height="110" rx="10" fill="${nadiColor}10" stroke="${nadiColor}" stroke-width="1.5" />
                        <foreignObject x="546" y="60" width="192" height="90">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#1e293b; font-size:11px;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1px;">Mēness fona zvaigznājs:</div>
                                <div style="font-size:12.5px; font-weight:900; color:${ink(nadiColor)}; margin-bottom:2px;">${nakshatraName}</div>
                                <div style="font-size:10px; color:#475569; margin-bottom:3px; line-height:1.3;">Šis zvaigžņu apgabals (Nakšatra) nosaka tavu emocionālo zemapziņu. Valdošā planēta: <b>${nakshatraLord}</b>.</div>
                                <div style="font-size:10px; font-weight:700; background:${nadiColor}20; color:${ink(nadiColor)}; border-radius:4px; padding:1px 5px; display:inline-block;">Enerģijas stihija (Nadi): ${nadiLabel}</div>
                            </div>
                        </foreignObject>
                    </g>
                    <g onclick="window.__s1Focus&&window.__s1Focus('relationship-vedic')" style="cursor:pointer;">
                        <rect x="536" y="175" width="212" height="90" rx="10" fill="${ganaColor}10" stroke="${ganaColor}" stroke-width="1.5" />
                        <foreignObject x="546" y="185" width="192" height="70">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#1e293b; font-size:11px;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1px;">Dabas temperaments:</div>
                                <div style="font-size:12.5px; font-weight:900; color:${ink(ganaColor)}; margin-bottom:2px;">${myGanaLv}</div>
                                <div style="font-size:10px; color:#475569; line-height:1.3;">
                                    ${myGana === 'Deva' ? 'Dievišķais tips tiecas uz harmoniju, mieru, iejūtību un augstiem garīgiem principiem.' : myGana === 'Manushya' ? 'Cilvēciskais tips ir pragmatisks, reālistisks un tendēts uz praktisku sadarbību.' : 'Dinamiskais tips ir spēcīgs, aizrautīgs, intensīvs un gatavs aktīvai rīcībai.'}
                                </div>
                            </div>
                        </foreignObject>
                    </g>
                    <g onclick="window.__s1Focus&&window.__s1Focus('relationship-ashtakoot')" style="cursor:pointer;">
                        <rect x="536" y="280" width="212" height="115" rx="10" fill="#ffffff" stroke="#cbd5e1" stroke-width="1" />
                        <foreignObject x="546" y="290" width="192" height="95">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#475569; font-size:10.5px; text-align:left;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Vēdu saderības tests (Ashtakoot)</div>
                                Salīdzina abu partneru zvaigznājus 8 dzīves līmeņos (no rakstura saderības līdz ģenētiskajam un biolauka līdzsvaram).
                                <div style="font-weight:700; color:#7e22ce; margin-top:3px;">Skatīt 8 līmeņu aprakstu ➜</div>
                            </div>
                        </foreignObject>
                    </g>
                </g>
            </svg>
            <div style="text-align:center; font-size:0.82rem; color:#94a3b8; margin-top:0.3rem;">Klikšķini uz bloka, lai izceltu tā pilno aprakstu zemāk</div>
        </div>`;
    })();

    const s4Body = `
        <p>Šī sadaļa nav "saderības zīlēšana" — tā ir <b>attiecību dinamikas audits</b>. Apvienojot Džona Boulbija Piesaistes teoriju (1969) ar Džona Gotmana attiecību pētījumiem (Four Horsemen, 1994), astroloģiskie dati šeit tiek pārtulkoti klīniski validētā valodā par to, kādu attiecību režīmu šis profils ienes un kuros mezglos jāstrādā apzināti.</p>
        ${q('Kāds ir šī profila attiecību sistēmas "rūpnīcas iestatījums"? · Kā viņš strīdas krīzes brīžos? · Ko viņš dabiski var sniegt partnerim? · Kāds ir viņa slēptais ēnas paterns?')}

        ${verdictHtml}

        ${relationshipSvgHtml}

        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.9rem; align-items:start;">
            <div style="min-width:0;">
                <div id="relationship-attachment">
                    ${sectionTitle('Piesaistes bāze — Boulbija operatīvais režīms', '#be185d')}
                    ${attachmentHtml}
                </div>
                ${shadowHtml}
            </div>

            <div style="min-width:0;">
                <div id="relationship-horsemen">
                    ${sectionTitle('Konfliktu dizains — Gotmana 4 jātnieki', '#b91c1c')}
                    ${horsemenHtml}
                </div>
                <div id="relationship-synergy">
                    ${sectionTitle('Sinerģijas zonas — dabiskā dāvana attiecībās', '#15803d')}
                    ${synergyHtml}
                </div>
            </div>

            <div style="min-width:0;">
                <div id="relationship-friction">
                    ${sectionTitle('Berzes punkti — kur nepieciešama apzināta pieeja', '#b45309')}
                    ${frictionHtml}
                </div>
                
                <div id="relationship-vedic" style="margin-top:1.2rem;">
                    ${sectionTitle('Vēdu datu validācijas matrica — zvaigznājs & temperamenta parametri', '#be185d')}
                    <p style="color:#64748b; font-size:0.85rem; line-height:1.55; margin:0 0 0.8rem 0;">Pilna 2-personu Ashta Kuta 36 punktu saderība (sinastrija) pieejama atsevišķā <i>Compatibility</i> cilnē. Zemāk redzami šī profila individuālie parametri, kas tur kalpo kā ievades dati.</p>
                    
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.8rem; margin-bottom:1.2rem;">
                        <div style="background:#f8fafc; border-radius:10px; padding:1rem; border-left:3px solid ${nadiColor};">
                            <div style="font-size:0.7rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Mēness zvaigznājs (Nakšatra)</div>
                            <div style="font-weight:800; color:#1e293b; font-size:1rem;">${nak.nakshatra || '—'}</div>
                            <div style="color:#64748b; font-size:0.82rem; margin-top:2px;">Mēness stāvvieta zvaigžņu debesīs dzimšanas brīdī. Lords (planēta): <b style="color:#1e293b;">${String(nak.lord || '—').replace(/^Meness$/, 'Mēness')}</b></div>
                            <div style="margin-top:6px; font-size:0.72rem; background:${nadiColor}20; color:${ink(nadiColor)}; border-radius:6px; padding:2px 8px; display:inline-block; font-weight:700;">Konstitūcija (Nadi): ${nadiLabel}</div>
                        </div>
                        <div style="background:#f8fafc; border-radius:10px; padding:1rem; border-left:3px solid ${ganaColor};">
                            <div style="font-size:0.7rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Temperaments (Gana)</div>
                            <div style="font-weight:800; color:${ink(ganaColor)}; font-size:1.05rem;">${myGanaLv}</div>
                            <div style="color:#64748b; font-size:0.82rem; margin-top:6px; line-height:1.55;">${ganaDesc[myGana] || '—'}</div>
                        </div>
                    </div>
                </div>

                <div id="relationship-navamsha" style="margin-top:1.2rem;">
                    ${sectionTitle('D9 Navamsha — partnera kartes indikators', '#7e22ce')}
                    <div style="background:#f8fafc; border-radius:10px; padding:1rem 1.2rem;">${navamshaHtml}</div>
                </div>

                <div id="relationship-ashtakoot" style="margin-top:1.2rem;">
                    ${sectionTitle('Saderības audits (Ashtakoot) — ko tas mēra', '#64748b')}
                    <p>Vēdu tradīcija salīdzina partneru dzimšanas zvaigznājus (Nakšatras) 8 dažādās kategorijās (saukta par <i>Ashtakoot</i> sistēmu). Tā mēra visu attiecību spektru — no kopīgiem dzīves mērķiem līdz biolauku harmonijai. Šī profila zvaigznājam (${nak.nakshatra || '—'}) ir jāsalīdzina ar partnera zvaigznāju šādos 8 līmeņos:</p>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.5rem; margin:0.8rem 0;">
                        ${[
                            ['Varna (1 punkts)', 'Kopīgā dzīves misija un ego saderība'],
                            ['Vashya (2 punkti)', 'Savstarpējā pievilcība un ietekme'],
                            ['Tara (3 punkti)', 'Labklājības, veselības un drošības rezonanse'],
                            ['Yoni (4 punkti)', 'Fiziskā, bioloģiskā un seksuālā harmonija'],
                            ['Graha Maitri (5 punkti)', 'Draudzība, prāta un uzskatu saderība'],
                            ['Gana (6 punkti)', 'Temperamenta un dabas saderība'],
                            ['Rashi (7 punkti)', 'Mēness zīmju (emocionālā) saderība'],
                            ['Nadi (8 punkti)', 'Enerģētiskā un biolauku harmonija'],
                        ].map(([cat, desc]) => `<div style="background:#f8fafc; border-radius:8px; padding:0.65rem 0.85rem; border-left:3px solid #475569;">
                            <b style="color:#1e293b; font-size:0.85rem;">${cat}</b>
                            <div style="color:#64748b; font-size:0.79rem; margin-top:2px;">${desc}</div>
                        </div>`).join('')}
                    </div>
                    <p style="color:#475569; font-size:0.85rem; line-height:1.6;">Kaut arī sistēma izcili norāda uz potenciālajiem berzes punktiem, tās spēja prognozēt reālu iznākumu ir stipri ierobežota — maksimālais punktu skaits (36/36) nereti iezīmē tikai Mēness emocionālo rezonansi, ignorējot lagnas un fundamentālas ego pretestības. Pat astroloģiski ideāliem pāriem ir iespējama dramatiska šķiršanās.</p>
                </div>
            </div>
        </div>`;

    const s4 = card('4',
        'Partnerattiecību un attiecību dinamikas saderība (Sinastrija)',
        'Augsta',
        ['Boulbija Piesaistes teorija', 'Gotmana 4 jātnieki', 'Vēdu Ashtakoot', 'BaZi sadursmes'],
        s4Body,
        'moon'
    );

    // ── 5: MAIJU TZOLK'IN — DVĒSELES MISIJA ──────────────────────────────────
    const maya     = profile?.maya_profile?.basic     || {};
    const mayaPsych = profile?.maya_profile?.psychology || {};

    const mayaColors = {
        'Sarkanā': '#b91c1c', 'Baltā': '#64748b', 'Zilā': '#1d4ed8', 'Dzeltenā': '#b45309',
    };
    const mayaColor = mayaColors[maya.color] || '#64748b';

    const psychKeys = Object.keys(mayaPsych);
    const psychHtml = psychKeys.length ? psychKeys.map(key => {
        const val = mayaPsych[key];
        const isLong = (val || '').length > 80;
        return `<div style="background:#f8fafc; border-radius:10px; padding:0.85rem 1rem; border-left:3px solid ${mayaColor}40;">
            <div style="font-size:0.7rem; font-weight:800; color:${ink(mayaColor)}; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:5px;">${key}</div>
            <div style="color:#334155; font-size:${isLong ? '0.87rem' : '0.92rem'}; line-height:1.65;">${val || '—'}</div>
        </div>`;
    }).join('') : `<div style="color:#475569; font-size:0.87rem;">Maiju profils nav aprēķināts</div>`;

    // ── Eksistenciālā audita dati (Logoterapija + Plūsma + Bioritms) ─────────
    const existential = profile?.existentialAudit || null;

    const dharmaHtml = existential?.macro?.dharma ? (() => {
        const d = existential.macro.dharma;
        const mg = existential.macro.mayanGroup;
        return `
            <div style="background:linear-gradient(135deg, ${d.color}14 0%, #ffffff 100%); border:1px solid ${d.color}45; border-radius:14px; padding:1.6rem 1.8rem; margin-bottom:1.2rem; box-shadow:0 2px 10px ${d.color}15;">
                <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.5rem;">
                    <span style="font-size:1.4rem;">${d.icon}</span>
                    <div style="font-size:0.72rem; font-weight:800; color:${ink(d.color)}; text-transform:uppercase; letter-spacing:2px;">Eksistenciālā misija · Frankla Logoterapija</div>
                </div>
                <h3 style="margin:0 0 0.4rem 0; color:#1e293b; font-size:1.3rem; font-weight:900;">${d.lv}</h3>
                <div style="color:#64748b; font-size:0.85rem; margin-bottom:0.9rem;">${d.en} · Atmakaraka: <b style="color:${ink(d.color)};">${existential.macro.atmakarakaPlanet}</b>${mg ? ` · ${mg}` : ''}</div>
                <div style="color:#334155; font-size:1rem; line-height:1.8; margin-bottom:0.7rem;"><b>Dzīves jēga:</b> ${d.mission}.</div>
                <div style="color:#334155; font-size:0.9rem; line-height:1.7; margin-bottom:0.5rem;"><b style="color:${ink(d.color)};">Nospiedums:</b> ${d.signature}.</div>
                <div style="color:#334155; font-size:0.88rem; line-height:1.65; margin-bottom:0.5rem;"><b style="color:#047857;">Dziļākā gandarījuma signāls:</b> ${d.deepSatisfaction}.</div>
                <div style="color:#334155; font-size:0.86rem; line-height:1.6;"><b style="color:#b91c1c;">Tukšuma signāls:</b> ${d.emptySignal}.</div>
            </div>
        `;
    })() : '';

    const rhythmHtml = existential?.mezo?.primary ? (() => {
        const r = existential.mezo.primary;
        const sec = existential.mezo.secondary;
        const hybridBadge = existential.mezo.isHybrid && sec ? `
            <span style="background:${sec.color}25; color:${ink(sec.color)}; border:1px solid ${sec.color}55; border-radius:6px; padding:2px 9px; font-size:0.72rem; font-weight:700; margin-left:8px;">+ ${sec.icon} ${sec.lv} hibrīds</span>
        ` : '';
        // 4-ritmu distribūcijas joslas dati
        const rhythmItems = Object.entries(existential.mezo.allScores || {}).map(([k, score]) => {
            const meta = existential.mezo.allMeta?.[k] || {};
            return { key: k, label: meta.lv || k, color: meta.color || '#64748b', icon: meta.icon, score };
        });
        const distribution = rhythmItems.length ? `
            <div style="background:#f8fafc; border-radius:10px; padding:0.85rem 1.05rem; margin-top:0.85rem;">
                ${distributionBar(rhythmItems, r.key, {
                    title: 'Visu 4 ritma tipu spektrs',
                    desc:  'Ķeltu koka un Maiju toņa sintēze — hibrīdā gadījumā divi tipi var saņemt vienādu skoru'
                })}
            </div>
        ` : '';
        return `
            <div style="background:#f8fafc; border:1px solid ${r.color}40; border-left:4px solid ${r.color}; border-radius:12px; padding:1.3rem 1.5rem; margin-bottom:1.2rem;">
                <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.6rem; flex-wrap:wrap;">
                    <span style="font-size:1.3rem;">${r.icon}</span>
                    <div>
                        <div style="font-size:0.7rem; font-weight:800; color:${ink(r.color)}; text-transform:uppercase; letter-spacing:1.5px;">Bioritma tips · Ķeltu un Maiju matrices sintēze</div>
                        <h3 style="margin:2px 0 0 0; color:#1e293b; font-size:1.15rem; font-weight:900;">${r.lv}${hybridBadge}</h3>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:0.6rem; margin-top:0.85rem;">
                    <div style="background:#f0fdf4; border-left:3px solid #15803d; border-radius:0 8px 8px 0; padding:0.7rem 0.95rem;">
                        <div style="font-size:0.7rem; font-weight:800; color:#047857; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.35rem;">✅ Ideālais biotops</div>
                        <div style="color:#334155; font-size:0.85rem; line-height:1.6;">${r.biotope}.</div>
                    </div>
                    <div style="background:#fef2f2; border-left:3px solid #b91c1c; border-radius:0 8px 8px 0; padding:0.7rem 0.95rem;">
                        <div style="font-size:0.7rem; font-weight:800; color:#b91c1c; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.35rem;">⚠ Izdegšanas trigeris</div>
                        <div style="color:#334155; font-size:0.85rem; line-height:1.6;">${r.burnoutTrigger}.</div>
                    </div>
                </div>
                <div style="background:#f1f5f9; border-left:3px solid ${r.color}80; border-radius:0 8px 8px 0; padding:0.7rem 0.95rem; margin-top:0.6rem;">
                    <div style="font-size:0.7rem; font-weight:800; color:${ink(r.color)}; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.35rem;">🔄 Atjaunošanās paterns</div>
                    <div style="color:#334155; font-size:0.85rem; line-height:1.6;">${r.recovery}.</div>
                </div>
                ${distribution}
            </div>
        `;
    })() : '';

    const flowHtml = existential?.micro?.morning ? (() => {
        const f = existential.micro;
        return `
            <div style="background:#f8fafc; border:1px solid #4f46e540; border-left:4px solid #4f46e5; border-radius:12px; padding:1.3rem 1.5rem; margin-bottom:1.2rem;">
                <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.85rem;">
                    <span style="font-size:1.3rem;">🔋</span>
                    <div>
                        <div style="font-size:0.7rem; font-weight:800; color:#4f46e5; text-transform:uppercase; letter-spacing:1.5px;">Ikdienas Plūsmas dizains · Csikszentmihalyi modelis</div>
                        <h3 style="margin:2px 0 0 0; color:#1e293b; font-size:1.1rem; font-weight:900;">${f.emoji} ${f.element} Daymaster · Plūsmas atslēga</h3>
                    </div>
                </div>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.65rem;">
                    <li style="display:flex; gap:0.7rem; align-items:flex-start;">
                        <span style="background:#fef3c7; color:#b45309; border-radius:5px; padding:3px 9px; font-size:0.68rem; font-weight:800; letter-spacing:0.5px; flex-shrink:0; margin-top:2px;">RĪTS</span>
                        <span style="color:#334155; font-size:0.88rem; line-height:1.65;">${f.morning}.</span>
                    </li>
                    <li style="display:flex; gap:0.7rem; align-items:flex-start;">
                        <span style="background:#e0e7ff; color:#4f46e5; border-radius:5px; padding:3px 9px; font-size:0.68rem; font-weight:800; letter-spacing:0.5px; flex-shrink:0; margin-top:2px;">FOKUSS</span>
                        <span style="color:#334155; font-size:0.88rem; line-height:1.65;">${f.flow}.</span>
                    </li>
                    <li style="display:flex; gap:0.7rem; align-items:flex-start;">
                        <span style="background:#dcfce7; color:#047857; border-radius:5px; padding:3px 9px; font-size:0.68rem; font-weight:800; letter-spacing:0.5px; flex-shrink:0; margin-top:2px;">RESTART</span>
                        <span style="color:#334155; font-size:0.88rem; line-height:1.65;">${f.restart}.</span>
                    </li>
                    <li style="display:flex; gap:0.7rem; align-items:flex-start;">
                        <span style="background:#fee2e2; color:#b91c1c; border-radius:5px; padding:3px 9px; font-size:0.68rem; font-weight:800; letter-spacing:0.5px; flex-shrink:0; margin-top:2px;">IZVAIRIES</span>
                        <span style="color:#334155; font-size:0.88rem; line-height:1.65;">${f.avoidance}.</span>
                    </li>
                </ul>
                ${f.tithiModulator ? `
                    <div style="margin-top:0.85rem; padding-top:0.7rem; border-top:1px solid #e2e8f0; color:#64748b; font-size:0.83rem; line-height:1.6; font-style:italic;">
                        <b style="color:#334155;">Šodienas Tithi modulators:</b> ${f.tithiModulator}.
                    </div>
                ` : ''}
            </div>
        `;
    })() : '';

    const existentialSvgHtml = (() => {
        if (!existential) return '';
        const d = existential.macro?.dharma || { lv: 'Nav datu', color: '#64748b', icon: '🧭', mission: '', signature: '', deepSatisfaction: '', emptySignal: '', en: '' };
        const r = existential.mezo?.primary || { lv: 'Nav datu', color: '#64748b', icon: '🔄', biotope: '', burnoutTrigger: '', recovery: '' };
        const f = existential.micro || { emoji: '🔋', element: '—', morning: '', flow: '', restart: '', avoidance: '', tithiModulator: '' };
        const atmakaraka = existential.macro?.atmakarakaPlanet || '—';
        const atmakarakaLord = String(atmakaraka).replace(/^Meness$/, 'Mēness');
        
        return `
        <div style="max-width:820px; margin:1rem auto 1.5rem;">
            <div style="text-align:center; font-size:0.92rem; color:#475569; font-weight:700; margin-bottom:0.25rem;">🧭 Dzīves misijas &amp; Ikdienas plūsmas karte</div>
            <div style="text-align:center; font-size:0.8rem; color:#94a3b8; margin-bottom:0.8rem;">Vizuāls kopsavilkums: eksistenciālā misija, dabas ritmi un ikdienas plūsmas atslēgas.</div>
            <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-width:820px; margin:0 auto;">
                <text x="20" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">DVĒSELES MISIJA (Dharma)</text>
                <text x="272" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">DABAS RITMI (Bioritmi)</text>
                <text x="524" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">IKDIENAS PLŪSMAS DIZAINS</text>
                <g onclick="window.__s1Focus&&window.__s1Focus('existential-dharma')" style="cursor:pointer;">
                    <rect x="20" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="32" y="50" width="212" height="80" rx="10" fill="${d.color}15" stroke="${d.color}" stroke-width="2" />
                    ${svgFramedTitle(44, `${d.icon} ${d.lv}`, `CEĻVEDIS: ${atmakarakaLord}`, 14, ink(d.color), 192, 192)}
                    <foreignObject x="32" y="145" width="212" height="250">
                        <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:12px; line-height:1.42; text-align:left; overflow:hidden; height:245px; padding-right:4px;">
                            <div style="margin-bottom:8px; color:#64748b; font-style:italic;">Garīgā misija parāda tavu galveno ceļu, dzīves jēgu un to, kas sniedz patiesu piepildījumu.</div>
                            <div style="margin-bottom:8px; color:#334155;"><b>Dzīves jēga:</b> ${clip(d.mission, 105)}</div>
                            <div style="background:#ffffff; border-left:3px solid ${d.color}80; padding:6px 8px; border-radius:0 6px 6px 0; font-size:11px; color:#475569;">
                                <b>Gandarījums:</b> ${clip(d.deepSatisfaction, 90)}
                            </div>
                        </div>
                    </foreignObject>
                </g>
                <g onclick="window.__s1Focus&&window.__s1Focus('existential-rhythm')" style="cursor:pointer;">
                    <rect x="272" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="284" y="50" width="212" height="80" rx="10" fill="${r.color}15" stroke="${r.color}" stroke-width="2" />
                    <text x="296" y="75" font-size="14" font-weight="900" fill="${ink(r.color)}" font-family="'Outfit',sans-serif">${r.icon} ${r.lv}</text>
                    <text x="296" y="96" font-size="11" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">BIORITMA PAMATS</text>
                    <foreignObject x="284" y="145" width="212" height="250">
                        <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:12px; line-height:1.42; text-align:left; overflow:hidden; height:245px; padding-right:4px;">
                            <div style="margin-bottom:8px; color:#64748b; font-style:italic;">Dabas bioritms nosaka tavu ideālo vidi, lai saglabātu enerģiju un izvairītos no spēku izsīkuma.</div>
                            <div style="margin-bottom:8px; color:#334155;"><b>Tavs biotops:</b> ${clip(r.biotope, 105)}</div>
                            <div style="background:#fef2f2; border-left:3px solid #b91c1c; padding:6px 8px; border-radius:0 6px 6px 0; font-size:11px; color:#991b1b; margin-top:4px;">
                                <b>Izdegšanas trigeris:</b> ${clip(r.burnoutTrigger, 90)}
                            </div>
                        </div>
                    </foreignObject>
                </g>
                <g>
                    <rect x="524" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <g onclick="window.__s1Focus&&window.__s1Focus('existential-flow')" style="cursor:pointer;">
                        <rect x="536" y="50" width="212" height="110" rx="10" fill="#4f46e510" stroke="#4f46e5" stroke-width="1.5" />
                        <foreignObject x="546" y="60" width="192" height="90">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#1e293b; font-size:11px;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1px;">Dzimšanas elements:</div>
                                <div style="font-size:13px; font-weight:900; color:#4f46e5; margin-bottom:2px;">${f.emoji} ${f.element}</div>
                                <div style="font-size:10px; color:#475569; margin-bottom:1px; line-height:1.3;">Plūsmas atslēga ikdienas produktivitātei.</div>
                                <div style="font-size:10px; font-weight:700; background:#e0e7ff; color:#4f46e5; border-radius:4px; padding:1px 5px; display:inline-block;">Rīts: ${f.morning ? clip(f.morning, 24) : '—'}</div>
                            </div>
                        </foreignObject>
                    </g>
                    <g onclick="window.__s1Focus&&window.__s1Focus('existential-flow')" style="cursor:pointer;">
                        <rect x="536" y="175" width="212" height="90" rx="10" fill="#15803d10" stroke="#15803d" stroke-width="1.5" />
                        <foreignObject x="546" y="185" width="192" height="70">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#1e293b; font-size:11px;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1px;">Restartēšanās (Restart):</div>
                                <div style="font-size:10.5px; color:#475569; line-height:1.3; margin-top:2px;">
                                    ${f.restart ? clip(f.restart, 75) : '—'}
                                </div>
                            </div>
                        </foreignObject>
                    </g>
                    <g onclick="window.__s1Focus&&window.__s1Focus('existential-flow')" style="cursor:pointer;">
                        <rect x="536" y="280" width="212" height="115" rx="10" fill="#ffffff" stroke="#cbd5e1" stroke-width="1" />
                        <foreignObject x="546" y="290" width="192" height="95">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#475569; font-size:10.5px; text-align:left;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Mēness dienas korekcija</div>
                                ${f.tithiModulator ? clip(f.tithiModulator, 80) : 'Šodien nav aktīvu Mēness dienas modifikāciju plūsmā'}
                            </div>
                        </foreignObject>
                    </g>
                </g>
            </svg>
            <div style="text-align:center; font-size:0.82rem; color:#94a3b8; margin-top:0.3rem;">Klikšķini uz bloka, lai izceltu tā pilno aprakstu zemāk</div>
        </div>`;
    })();

    const s5Body = `
        <p>Šī sadaļa nav "karmas zīlēšana" — tā ir <b>ikdienas enerģijas un eksistenciālās jēgas audits</b>. Apvienojot Viktora Frankla Logoterapiju (1946) ar Mihāja Čīksentmihāji Plūsmas teoriju (1990) un hronobioloģijas principiem, Atmakarakas, Maiju Nahuala, Ķeltu koku un BaZi Daymaster dati tiek pārtulkoti praktiskā laika un enerģijas menedžmenta valodā.</p>
        ${q('Kāpēc šis cilvēks ir šeit? · Kāds ir viņa dabiskais enerģijas ritms? · Kā viņš ieiet plūsmā? · Kā viņam pareizi atjaunoties?')}

        ${existentialSvgHtml}

        <div id="existential-dharma">${dharmaHtml}</div>

        <div id="existential-rhythm">${rhythmHtml}</div>

        <div id="existential-flow">${flowHtml}</div>

        ${sectionTitle('Maiju Tzolk\'in dzinējs — strukturālā bāze', mayaColor)}

        ${(maya.sign || maya.tone) ? `
        <div style="background:${mayaColor}12; border:1px solid ${mayaColor}35; border-radius:12px; padding:1.2rem 1.4rem; margin-bottom:1.2rem;">
            <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:0.85rem;">
                <span style="background:${mayaColor}; color:#fff; border-radius:10px; padding:6px 18px; font-size:1rem; font-weight:900;">${maya.tone || '?'} Tonis · ${maya.sign || '?'}</span>
                <span style="background:${mayaColor}20; color:${ink(mayaColor)}; border:1px solid ${mayaColor}50; border-radius:20px; padding:4px 14px; font-size:0.85rem; font-weight:700;">${maya.color || '—'} vilnis</span>
                ${maya.kin ? `<span style="color:#64748b; font-size:0.85rem;">KIN ${maya.kin}</span>` : ''}
            </div>
        </div>` : `<div style="color:#475569; font-size:0.87rem; margin-bottom:1rem;">Maiju zīmogs nav aprēķināts</div>`}

        ${sectionTitle('12 Dvēseles dimensijas — šī profila lasījums', mayaColor)}
        <div style="display:flex; flex-direction:column; gap:0.5rem;">${psychHtml}</div>

        <p style="margin-top:1.2rem; color:#475569; font-size:0.85rem; line-height:1.6;">Maiju Tzolk'in tradīcija operē abstraktos teoloģiskos konceptos — tās precizitāti nav iespējams objektīvi izmērīt. Taču kā simboliski instrumenti dvēseles jautājumu formulēšanai šīs arhetipiskās kategorijas var palīdzēt noformulēt eksistenciālās bažas, ko citādi ir grūti verbalizēt. Jāatceras: Barnuma efekts ir reāls — vērtīgi izvērtēt, cik daudz no lasījuma tiešām atbilst šai personai, un cik daudz būtu piemērojams jebkuram.</p>`;

    const s5 = card('5',
        'Ikdienas enerģētika, dabas ritmi un garīgais dzīves mērķis',
        'Augsta',
        ['Frankla Logoterapija', 'Csikszentmihalyi Plūsma', 'Ķeltu bioritms', 'BaZi Daymaster', 'Maiju Tzolk\'in'],
        s5Body,
        'date-fixed'
    );

    // ── 6: PSIHOSOMATIKAS UN IZDEGŠANAS AUDITS ───────────────────────────────
    const psyAudit = profile?.psychosomaticAudit || null;

    // A. Akūtais stresa indikators (dzeltens — sarkanie karogi)
    const acuteHtml = psyAudit?.acute?.signals?.length ? (() => {
        const cards = psyAudit.acute.signals.map(s => `
            <div style="background:#f8fafc; border:1px solid #b4530940; border-left:4px solid #b45309; border-radius:10px; padding:0.95rem 1.15rem; margin-bottom:0.55rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem; flex-wrap:wrap;">
                    <span style="background:#fef3c7; color:#b45309; border-radius:5px; padding:1px 7px; font-size:0.66rem; font-weight:800;">${s.planet} · 6. māja</span>
                    <span style="font-weight:800; color:#b45309; font-size:0.95rem;">${s.signal}</span>
                </div>
                <div style="color:#334155; font-size:0.86rem; line-height:1.65; margin-bottom:0.35rem;"><b style="color:#92400e;">Somatiskais signāls:</b> ${s.soma}.</div>
                <div style="color:#64748b; font-size:0.83rem; line-height:1.55; font-style:italic;">${s.trigger}.</div>
            </div>
        `).join('');
        return `<div style="display:flex; flex-direction:column; gap:0.3rem;">${cards}</div>`;
    })() : `<div style="color:#475569; font-size:0.87rem;">6. mājā nav planētu — akūtie stresa marķieri šim profilam parādās caur citām sistēmām (skat. C bloku zemāk)</div>`;

    // B. Hroniskās izdegšanas profils (sarkans — kritisks līmenis)
    const chronicHtml = psyAudit?.chronic?.patterns?.length || psyAudit?.acute?.hironWound ? (() => {
        const patterns = (psyAudit.chronic.patterns || []).map(c => `
            <div style="background:#fef2f2; border:1px solid #b91c1c40; border-left:4px solid #b91c1c; border-radius:10px; padding:0.95rem 1.15rem; margin-bottom:0.55rem;">
                <div style="display:flex; align-items:center; gap:0.55rem; margin-bottom:0.4rem; flex-wrap:wrap;">
                    <span style="background:#fee2e2; color:#b91c1c; border-radius:5px; padding:1px 7px; font-size:0.66rem; font-weight:800;">${c.planet} · 8. māja</span>
                    <span style="font-weight:800; color:#b91c1c; font-size:0.95rem;">${c.pattern}</span>
                </div>
                <div style="color:#334155; font-size:0.86rem; line-height:1.65; margin-bottom:0.35rem;"><b style="color:#991b1b;">Somatizācijas zonā:</b> ${c.soma}.</div>
                <div style="color:#64748b; font-size:0.83rem; line-height:1.55; font-style:italic;">${c.depth}.</div>
            </div>
        `).join('');
        const hiron = psyAudit.acute.hironWound ? `
            <div style="background:#faf5ff; border:1px solid #6d28d940; border-left:4px solid #6d28d9; border-radius:10px; padding:0.95rem 1.15rem; margin-top:0.3rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                    <span style="background:#f3e8ff; color:#6d28d9; border-radius:5px; padding:1px 7px; font-size:0.66rem; font-weight:800;">Hirons · ${psyAudit.acute.hironHouse}. māja</span>
                    <span style="font-weight:800; color:#6d28d9; font-size:0.95rem;">Fonā strādājošā primārā brūce</span>
                </div>
                <div style="color:#334155; font-size:0.86rem; line-height:1.65;">${psyAudit.acute.hironWound}.</div>
            </div>
        ` : '';
        return `<div style="display:flex; flex-direction:column; gap:0.3rem;">${patterns}${hiron}</div>`;
    })() : `<div style="color:#475569; font-size:0.87rem;">8. mājā un Hirona zonā nav nozīmīgu marķieru — hroniskā izdegšanas tendence šim profilam ir zemāka</div>`;

    // C. Enerģijas noplūdes zonas (oranžs)
    const leaksHtml = psyAudit?.energyLeaks?.length ? (() => {
        const cards = psyAudit.energyLeaks.map(l => `
            <div style="background:#f8fafc; border:1px solid #c2410c40; border-left:3px solid #c2410c; border-radius:10px; padding:0.85rem 1.05rem; margin-bottom:0.45rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                    <span style="background:#ffedd5; color:#c2410c; border-radius:5px; padding:1px 7px; font-size:0.65rem; font-weight:800;">${l.aspect}</span>
                </div>
                <div style="color:#334155; font-size:0.85rem; line-height:1.6; margin-bottom:0.3rem;">${l.profile}</div>
                ${l.impact && l.impact !== '—' ? `<div style="color:#64748b; font-size:0.8rem; line-height:1.55; font-style:italic;">${l.impact}</div>` : ''}
            </div>
        `).join('');
        return `<div style="display:flex; flex-direction:column; gap:0.3rem;">${cards}</div>`;
    })() : `<div style="color:#475569; font-size:0.87rem;">Nav identificētu kritisku aspektu — fonā strādājošā mentālā enerģijas noplūde šim profilam ir minimāla</div>`;

    // D. Atjaunošanās un kompensācijas protokols (zaļš — risinājums)
    const compHtml = psyAudit?.compensation ? (() => {
        const comp = psyAudit.compensation;
        const primary = comp.primary;
        // 5-elementu pilna distribūcija no profile.bazi.elements
        const baziElements = profile?.bazi?.elements || {};
        const elementColors = { Koks:'#15803d', Uguns:'#b91c1c', Zeme:'#b45309', Metāls:'#64748b', Ūdens:'#1d4ed8' };
        const elementIcons  = { Koks:'🌱', Uguns:'🔥', Zeme:'🗿', Metāls:'⚙', Ūdens:'🌊' };
        const elementItems = Object.entries(baziElements).map(([el, pct]) => ({
            key: el, label: el, color: elementColors[el] || '#64748b', icon: elementIcons[el] || '', score: pct
        }));
        const dominantEl = elementItems.length ? elementItems.reduce((a, b) => (a.score > b.score ? a : b)).key : null;
        const elementsDistribution = elementItems.length ? `
            <div style="background:#f8fafc; border-radius:10px; padding:0.95rem 1.1rem; margin-bottom:0.85rem; border:1px solid #e2e8f0;">
                ${distributionBar(elementItems, dominantEl, {
                    title: 'BaZi 5 elementu enerģijas balanss',
                    desc:  'Pilns elementu sadalījums no 4 stumbriem un 4 zariem — dominējošais elements rāda stresa pārpilduma virzienu, trūkstošais — sistēmas ievainojamību'
                })}
            </div>
        ` : '';
        const excessList = (comp.excessElements || []).map(e => `
            <div style="background:#f8fafc; border:1px solid ${e.color}40; border-left:3px solid ${e.color}; border-radius:10px; padding:0.85rem 1.05rem; margin-bottom:0.4rem;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.35rem;">
                    <span style="font-weight:800; color:${ink(e.color)}; font-size:0.92rem;">⚠ Pārpalikums · ${e.element}</span>
                    <span style="color:${ink(e.color)}; font-weight:700; font-size:0.85rem;">${e.pct}%</span>
                </div>
                <div style="color:#334155; font-size:0.85rem; line-height:1.6;">${e.excess}.</div>
            </div>
        `).join('');
        const defList = (comp.deficiencyElements || []).map(d => `
            <div style="background:#f8fafc; border:1px solid ${d.color}40; border-left:3px solid ${d.color}; border-radius:10px; padding:0.85rem 1.05rem; margin-bottom:0.4rem;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.35rem;">
                    <span style="font-weight:800; color:${ink(d.color)}; font-size:0.92rem;">▿ Trūkums · ${d.element}</span>
                    <span style="color:${ink(d.color)}; font-weight:700; font-size:0.85rem;">${d.pct}%</span>
                </div>
                <div style="color:#334155; font-size:0.85rem; line-height:1.6;">${d.deficiency}.</div>
            </div>
        `).join('');
        const compensation = primary ? `
            <div style="background:#f0fdf4; border:2px solid #04785760; border-left:5px solid #15803d; border-radius:12px; padding:1.1rem 1.3rem; margin-top:0.7rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.6rem;">
                    <span style="font-size:1.2rem;">🔋</span>
                    <div style="font-size:0.7rem; font-weight:800; color:#047857; text-transform:uppercase; letter-spacing:1.5px;">Atjaunošanās protokols · ${primary.element}</div>
                </div>
                <div style="color:#14532d; font-size:0.92rem; line-height:1.7;">${primary.compensation}.</div>
            </div>
        ` : '';
        return `${elementsDistribution}${excessList}${defList}${compensation}`;
    })() : `<div style="color:#475569; font-size:0.87rem;">BaZi elementu balanss nav aprēķināts</div>`;

    // Psihosomatiskā mērītāja paskaidrojums (apakšā kā meta-līmenis)
    const meterHtml = psyAudit?.psychosomMeter ? `
        <div style="background:#f8fafc; border:1px solid #4f46e540; border-left:4px solid #4f46e5; border-radius:12px; padding:1.1rem 1.3rem; margin-bottom:1.2rem;">
            <div style="display:flex; align-items:center; gap:0.55rem; margin-bottom:0.6rem;">
                <span style="font-size:1.1rem;">📊</span>
                <div style="font-size:0.7rem; font-weight:800; color:#4f46e5; text-transform:uppercase; letter-spacing:1.5px;">Psihosomatiskā mērītāja kalibrācija</div>
            </div>
            <div style="color:#334155; font-size:0.88rem; line-height:1.7; margin-bottom:0.5rem;">
                Psihosomatiskais savienojums: <b style="color:#4f46e5;">${psyAudit.psychosomMeter.psychosomScore}%</b> · Hirona aktivitāte: <b style="color:#6d28d9;">${psyAudit.psychosomMeter.chironActScore}%</b>
            </div>
            <div style="color:#334155; font-size:0.86rem; line-height:1.65;">${psyAudit.psychosomMeter.narrative}.</div>
        </div>
    ` : '';

    const burnoutSvgHtml = (() => {
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
        
        return `
        <div style="max-width:820px; margin:1rem auto 1.5rem;">
            <div style="text-align:center; font-size:0.92rem; color:#475569; font-weight:700; margin-bottom:0.25rem;">🧭 Psihosomatikas &amp; Izdegšanas audits</div>
            <div style="text-align:center; font-size:0.8rem; color:#94a3b8; margin-bottom:0.8rem;">Vizuāls kopsavilkums: stresa riski, ķermeņa jūtība un atjaunošanās elementi.</div>
            <svg viewBox="0 0 780 430" width="100%" style="font-family:'Outfit',sans-serif; display:block; max-width:820px; margin:0 auto;">
                <text x="20" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">STRESA &amp; SOMATISKIE RISKI</text>
                <text x="272" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">PSIHOSOMATISKAIS MĒRĪTĀJS</text>
                <text x="524" y="24" font-size="13" font-weight="800" fill="#94a3b8" letter-spacing="0.5px" font-family="'Outfit',sans-serif">ATJAUNOŠANĀS &amp; BALANSS</text>
                
                <g onclick="window.__s1Focus&&window.__s1Focus('burnout-stress')" style="cursor:pointer;">
                    <rect x="20" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="32" y="50" width="212" height="80" rx="10" fill="#b91c1c15" stroke="#b91c1c" stroke-width="2" />
                    <text x="44" y="75" font-size="15" font-weight="900" fill="#b91c1c" font-family="'Outfit',sans-serif">⚠️ Stresa signāli</text>
                    <text x="44" y="96" font-size="11" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">ĶERMEŅA CHECK-ENGINE</text>
                    <foreignObject x="32" y="145" width="212" height="250">
                        <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:12px; line-height:1.42; text-align:left; overflow:hidden; height:245px; padding-right:4px;">
                            <div style="margin-bottom:8px; color:#64748b; font-style:italic;">Ķermenis uzkrāj spriedzi somatiskajos punktos pirms prāts apzinās spēku izsīkumu.</div>
                            <div style="margin-bottom:8px; color:#334155;"><b>Akūtais punkts:</b> ${clip(firstAcute.soma, 80)}</div>
                            <div style="background:#ffffff; border-left:3px solid #b91c1c80; padding:6px 8px; border-radius:0 6px 6px 0; font-size:11px; color:#475569;">
                                <b>Hroniskā zona:</b> ${clip(firstChronic.soma, 80)}
                            </div>
                            ${hironWound ? `<div style="margin-top:8px; font-size:11px; color:#6d28d9; line-height:1.3;"><b>Fona brūce (Hirons):</b> ${clip(hironWound, 60)}</div>` : ''}
                        </div>
                    </foreignObject>
                </g>
                
                <g onclick="window.__s1Focus&&window.__s1Focus('burnout-psychosomatic')" style="cursor:pointer;">
                    <rect x="272" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="284" y="50" width="212" height="80" rx="10" fill="#4f46e515" stroke="#4f46e5" stroke-width="2" />
                    <text x="296" y="75" font-size="15" font-weight="900" fill="#4f46e5" font-family="'Outfit',sans-serif">📊 Savienojums</text>
                    <text x="296" y="96" font-size="11" font-weight="800" fill="#64748b" font-family="'Outfit',sans-serif">SOMATISKĀ JŪTĪBA</text>
                    <text x="484" y="76" font-size="18" font-weight="900" fill="#4f46e5" text-anchor="end" font-family="'Outfit',sans-serif">${somScore}%</text>
                    <foreignObject x="284" y="145" width="212" height="250">
                        <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; color:#475569; font-size:12px; line-height:1.42; text-align:left; overflow:hidden; height:245px; padding-right:4px;">
                            <div style="margin-bottom:8px; color:#64748b; font-style:italic;">Mēra, cik izteikti emocionālās trauksmes un mentālā pārslodze izpaužas kā fiziski simptomi ķermenī.</div>
                            <div style="margin-bottom:8px; color:#334155;"><b>Jūtības audits:</b> ${clip(meterNarrative, 105)}</div>
                            <div style="background:#ffffff; border-left:3px solid #6d28d980; padding:6px 8px; border-radius:0 6px 6px 0; font-size:11px; color:#475569;">
                                <b>Hirona brūces aktivitāte:</b> ${chironScore}%
                            </div>
                        </div>
                    </foreignObject>
                </g>
                
                <g>
                    <rect x="524" y="35" width="236" height="375" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <g onclick="window.__s1Focus&&window.__s1Focus('burnout-compensation')" style="cursor:pointer;">
                        <rect x="536" y="50" width="212" height="110" rx="10" fill="#15803d10" stroke="#15803d" stroke-width="1.5" />
                        <foreignObject x="546" y="60" width="192" height="90">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#1e293b; font-size:11px;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1px;">Dominējošais elements:</div>
                                <div style="font-size:13px; font-weight:900; color:#15803d; margin-bottom:2px;">${dominantIcon} ${dominantEl}</div>
                                <div style="font-size:10px; color:#475569; margin-bottom:1px; line-height:1.3;">Rāda stresa pārslodzes elementāro virzienu.</div>
                            </div>
                        </foreignObject>
                    </g>
                    <g onclick="window.__s1Focus&&window.__s1Focus('burnout-compensation')" style="cursor:pointer;">
                        <rect x="536" y="175" width="212" height="90" rx="10" fill="${elementColors[primary.element] || '#15803d'}10" stroke="${elementColors[primary.element] || '#15803d'}" stroke-width="1.5" />
                        <foreignObject x="546" y="185" width="192" height="70">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#1e293b; font-size:11px;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1px;">Kompensācijas elements:</div>
                                <div style="font-size:13px; font-weight:900; color:${elementColors[primary.element] || '#15803d'}; margin-bottom:2px;">
                                    ${elementIcons[primary.element] || ''} ${primary.element}
                                </div>
                            </div>
                        </foreignObject>
                    </g>
                    <g onclick="window.__s1Focus&&window.__s1Focus('burnout-compensation')" style="cursor:pointer;">
                        <rect x="536" y="280" width="212" height="115" rx="10" fill="#ffffff" stroke="#cbd5e1" stroke-width="1" />
                        <foreignObject x="546" y="290" width="192" height="95">
                            <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:'Outfit',sans-serif; line-height:1.25; color:#475569; font-size:10.5px; text-align:left;">
                                <div style="font-size:9.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Atjaunošanās ieteikums</div>
                                ${primary.compensation ? clip(primary.compensation, 80) : 'Atjaunošanās ieteikumi nav pieejami.'}
                                <div style="font-weight:700; color:#7e22ce; margin-top:3px;">Skatīt pilnu protokolu ➜</div>
                            </div>
                        </foreignObject>
                    </g>
                </g>
            </svg>
            <div style="text-align:center; font-size:0.82rem; color:#94a3b8; margin-top:0.3rem;">Klikšķini uz bloka, lai izceltu tā pilno aprakstu zemāk</div>
        </div>`;
    })();

    const s6Body = `
        <p>Šī sadaļa nav medicīniska diagnostika. Tā ir <b>stresa uzkrāšanās arhitektūras audits</b>, balstīts Besela van der Kolka principā "The Body Keeps the Score" (2014) — ķermenis atceras to, ko prāts vēlas aizmirst, un nepārstrādātā psiholoģiskā slodze izpaužas fiziski. Astroloģiskie dati šeit netiek izmantoti, lai identificētu slimības, bet gan lai nolasītu individuālo somatizācijas paternu un kompensācijas protokolu.</p>
        ${q('Kā viņa ķermenis brīdina pirms izdegšanas? · Kur uzkrājas hroniskā psiholoģiskā slodze? · Kas nemanāmi izsūc dienas enerģiju? · Kāds ir viņa atjaunošanās protokols?')}

        ${burnoutSvgHtml}

        <div id="burnout-psychosomatic">${meterHtml}</div>

        <div id="burnout-stress">
        ${sectionTitle('A · Akūtais stresa indikators — sistēmas "sarkanie karogi"', '#b45309')}
        <p style="color:#64748b; font-size:0.85rem; line-height:1.55; margin:0 0 0.6rem 0;">Kā ķermenis kliedz "Stop" pirms iestājas pilna izdegšana — 6. mājas planetārie iemītnieki nodrošina personīgo "Check-engine" lampiņu.</p>
        ${acuteHtml}

        ${sectionTitle('B · Hroniskā izdegšanas profils — kur uzkrājas ilgtermiņa slodze', '#b91c1c')}
        <p style="color:#64748b; font-size:0.85rem; line-height:1.55; margin:0.8rem 0 0.6rem 0;">Kas notiek, ja sarkanie karogi tiek ilgstoši ignorēti — 8. mājas un Hirona dati identificē somatizācijas dziļākos paternus.</p>
        ${chronicHtml}

        ${sectionTitle('C · Enerģijas noplūdes zonas — mentālā iztukšošanās', '#c2410c')}
        <p style="color:#64748b; font-size:0.85rem; line-height:1.55; margin:0.8rem 0 0.6rem 0;">Kas nemanāmi "apēd" viņa enerģiju ikdienā — iekšējie konflikti starp pretēji vērstām pamatvajadzībām, kas pastāvīgi tērē fonā strādājošos resursus.</p>
        ${leaksHtml}
        </div>

        <div id="burnout-compensation">
        ${sectionTitle('D · Atjaunošanās un Stabilitātes protokols', '#15803d')}
        <p style="color:#64748b; font-size:0.85rem; line-height:1.55; margin:0.8rem 0 0.6rem 0;">BaZi elementu disbalanss atklāj sistēmiskās izdegšanas mehāniku un piedāvā konkrētu atjaunošanās protokolu — kompensāciju, kas tieši izriet no kartes matemātikas.</p>
        ${compHtml}
        </div>

        <p style="margin-top:1.2rem; color:#64748b; font-size:0.82rem; line-height:1.6; font-style:italic;">Saikne ar 2. sadaļu: ja Šeina karjeras enkuri netiek apmierināti, tieši caur šo psihosomatisko profilu tas visātrāk izpaudīsies fiziski. Audita izmantošanas konteksts ir stresa pārvaldība un izdegšanas profilakse, ne medicīniskā diagnoze — par fiziskās veselības jautājumiem jākonsultējas ar kvalificētu veselības speciālistu.</p>
    `;

    const s6 = card('6',
        'Psihosomatika un Izdegšanas Audits',
        'Augsta',
        ['Van der Kolka "The Body Keeps the Score"', 'Stresa reakciju arhitektūra', 'BaZi 5 elementu disbalanss'],
        s6Body,
        { drivers: [
            { path: 'baziHour.branchDist', label: 'BaZi stundas stabs', weight: 1 },
            { path: '__fixed', label: 'Gads/mēnesis/diena stabi', weight: 3 },
        ], requires: ['time', 'birthplace'],
          limits: 'Trīs no četriem BaZi stabiem (gads, mēnesis, diena) ir datuma-fiksēti; ceturtais — stundas stabs — prasa dzimšanas laiku ar ±1 h precizitāti. Somatiskie mājokļu (H6/H8) marķieri bez precīza laika ir orientējoši; psihosomatika kopumā apraksta noslieces, ne diagnozes.' }
    );

    // ── 7: PRAKTISKIE SCENĀRIJI (cross-module sintēze) ───────────────────────
    const scenarios = profile?.scenarios || null;

    const scenarioCard = (key, sc) => {
        if (!sc) return '';
        const dosList = (sc.dos || []).map(d => `<li style="margin-bottom:0.4rem; color:#14532d; line-height:1.55;">${d}</li>`).join('');
        const dontList = (sc.donts || []).map(d => `<li style="margin-bottom:0.4rem; color:#b91c1c; line-height:1.55;">${d}</li>`).join('');
        return `
            <div style="background:linear-gradient(135deg, ${sc.color}10 0%, #ffffff 100%); border:1px solid ${sc.color}40; border-left:5px solid ${sc.color}; border-radius:14px; padding:1.4rem 1.6rem; margin-bottom:1.2rem;">
                <div style="display:flex; align-items:center; gap:0.7rem; margin-bottom:0.5rem;">
                    <span style="font-size:1.5rem;">${sc.icon}</span>
                    <div>
                        <div style="font-size:0.68rem; font-weight:800; color:${ink(sc.color)}; text-transform:uppercase; letter-spacing:2px;">Scenārijs</div>
                        <h3 style="margin:2px 0 0 0; color:#1e293b; font-size:1.15rem; font-weight:900;">${sc.title}</h3>
                    </div>
                </div>
                <div style="color:#64748b; font-size:0.82rem; font-style:italic; margin-bottom:0.85rem;">${sc.hook}.</div>
                <div style="color:#1e293b; font-size:0.92rem; line-height:1.75; margin-bottom:1rem;">${sc.narrative}</div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:0.6rem; margin-bottom:1rem;">
                    <div style="background:#f0fdf4; border:1px solid #04785740; border-left:3px solid #15803d; border-radius:10px; padding:0.85rem 1.05rem;">
                        <div style="font-size:0.68rem; font-weight:800; color:#047857; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:0.5rem;">✅ Ieteicams · DO</div>
                        <ul style="margin:0; padding-left:1.15rem; font-size:0.85rem;">${dosList}</ul>
                    </div>
                    <div style="background:#fef2f2; border:1px solid #b91c1c40; border-left:3px solid #b91c1c; border-radius:10px; padding:0.85rem 1.05rem;">
                        <div style="font-size:0.68rem; font-weight:800; color:#b91c1c; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:0.5rem;">🛑 Nav ieteicams · DON'T</div>
                        <ul style="margin:0; padding-left:1.15rem; font-size:0.85rem;">${dontList}</ul>
                    </div>
                </div>
                <div style="background:#f1f5f9; border-left:3px solid ${sc.color}cc; border-radius:0 8px 8px 0; padding:0.75rem 1rem; color:#334155; font-size:0.88rem; line-height:1.65;">
                    <b style="color:${ink(sc.color)};">💡 Galvenā atziņa:</b> ${sc.keyInsight}.
                </div>
            </div>
        `;
    };

    const s7Body = scenarios ? `
        <p>Iepriekšējās 6 sadaļas strukturētas pēc <b>psiholoģijas skolām</b> (Jungs, Šeins, Boulbijs, Frankls). Šī sadaļa strukturēta pēc <b>reālām dzīves situācijām</b> — apvieno konkrētus laukus no visiem moduļiem 4 instrumentāli pielietojamos scenārijos, kas nodrošina tiešu atbildi uz "Kā ar šo cilvēku rīkoties, kad notiek X?" bez nepieciešamības savienot 6 dažādu sadaļu izvilkumus.</p>
        ${q('Kā šis cilvēks reaģēs krīzē? · Kas viņu motivē? · Kāda komandas loma viņam dabiska? · Kā viņš pieņem lielos lēmumus?')}

        ${scenarioCard('crisis',     scenarios.crisis)}
        ${scenarioCard('motivation', scenarios.motivation)}
        ${scenarioCard('team',       scenarios.team)}
        ${scenarioCard('decisions',  scenarios.decisions)}
    ` : `<p style="color:#64748b;">Scenāriju dati vēl nav aprēķināti — daži centrālie moduļi nav pieejami.</p>`;

    const s7 = card('7',
        'Praktiskie scenāriji un situācijas pielietojums',
        'Augsta',
        ['Krīzes vadība', 'Motivācija', 'Komandas integrācija', 'Lēmumu dizains'],
        s7Body,
        'multi-system'
    );

    // Exec summary bloks DZĒSTS (2026-06-12): t3 pirmais slots tagad ir Investora memorands
    // (investor_memo_panel.js); opts.showExec paliek pieņemts, bet ignorēts — vecie izsaukumi nelūst.

    const sectionMap = { '1': s1, '2': s2, '3': s3, '4': s4, '5': s5, '6': s6, '7': s7 };
    const sections   = opts.sections || ['1', '2', '3', '4', '5', '6', '7'];
    const showHeader = opts.showHeader ?? true;

    return `
        <div style="max-width:1286px; margin:0 auto; padding-bottom:3rem;">
            ${showHeader ? `
            <div style="background:#fff; border-radius:16px; padding:1.8rem 2rem; margin-bottom:2rem; border-left:5px solid #4f46e5; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <h1 style="color:#1e293b; margin:0 0 0.4rem 0; font-size:1.3rem; font-weight:900;">Premium psiholoģiskā un biznesa audita matrica</h1>
                <p style="color:#64748b; font-size:0.88rem; margin:0;">Akadēmiski validēti ietvari (Jungs, Šeins, Boulbijs, Frankls, Gottman, van der Kolks) apvienoti ar astroloģiskās datu matemātiku — strukturēts personības, karjeras, attiecību un izdegšanas audits</p>
            </div>` : ''}
            ${sections.map(id => sectionMap[id] || '').join('')}
        </div>`;
}
