// ── VIZUĀLAIS ŠĀVIENS ─────────────────────────────────────────────────────────
// 1. slānis: "10 sekundes" — momentāns priekšstats par profilu.
// Krāsu palete: #7FB9E6 #D6BEEA #F4D77A #B7C96A #F98BA9 #FF8F45

import { buildWorkFitSpectrum } from '../../logic/work_fit_spectrum.js?v=4';

// ── Krāsu konstantes ──────────────────────────────────────────────────────────
const C = {
    blue:    '#7FB9E6',
    lilac:   '#D6BEEA',
    gold:    '#F4D77A',
    sage:    '#B7C96A',
    pink:    '#F98BA9',
    orange:  '#FF8F45',
    text:    '#3D4A5C',
    subtext: '#8896A8',
    bg:      '#FAFBFD',
    card:    '#FFFFFF',
};

// Lietotāja ievadīts teksts (dzimšanas/šībrīža vieta — brīvs teksta lauks, nav
// garantēts, ka izvēlēts no autopabeigšanas saraksta) drīkst nonākt innerHTML
// tikai pēc escapošanas — citādi ievade tips "Rīga<img src=x onerror=...>" izpildās.
function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

// ── Latviešu mēnešu saīsinājumi datumam ───────────────────────────────────────
const LV_MONTHS = ['janv', 'febr', 'marts', 'apr', 'maijs', 'jūn', 'jūl', 'aug', 'sept', 'okt', 'nov', 'dec'];
function formatBirthDate(str) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(str || '');
    if (!m) return str || '';
    const mon = LV_MONTHS[parseInt(m[2], 10) - 1] || m[2];
    return `${m[1]}-${mon}-${m[3]}`;
}

// ── Arhetipa nosaukuma ģenerēšana ─────────────────────────────────────────────
// Nosaukums balstās DIVOS reālos, jau parādītos parametros (nevis rezerves vārdā):
//   Lietvārds = līderības arhetips (leadership.primary)
//   Īpašības vārds = dominējošā Darba stila spektra pola (tie paši 6 pīrāgi)
// Tā headline vienmēr sakrīt ar datiem, ko lietotājs redz zemāk.
// Lietvārds — līderības arhetips (reāla klasifikācija)
const NOUN_BY_LEADER = {
    charismatic:   'Līderis',
    authoritarian: 'Komandieris',
    expert:        'Eksperts',
    mentor:        'Mentors',
    visionary:     'Vizionārs',
    admin:         'Administrators',
    specialist:    'Speciālists',
    solo:          'Neatkarīgais eksperts',
};
// Ko nozīmē katra loma (fits "dabiskā loma ir ___")
const NOUN_MEANING = {
    charismatic:   'iedvesmot un vest cilvēkus aiz sevis',
    authoritarian: 'skaidri vadīt, izvirzīt mērķus un uzņemties atbildību',
    expert:        'balstīties uz dziļām, specializētām zināšanām savā jomā',
    mentor:        'attīstīt un atbalstīt citus cilvēkus',
    visionary:     'radīt jaunas idejas un nākotnes redzējumu',
    admin:         'uzturēt kārtību, sistēmas un procesus',
    specialist:    'kvalitatīvi un rūpīgi izpildīt konkrētus uzdevumus',
    solo:          'strādāt patstāvīgi ar augstu kompetenci, bez ārējas vadības',
};
// Īpašības vārds — dominējošā pola no spēcīgākā Darba stila spektra
const POLE_ADJ = {
    'hands_head:left':   'Praktiskais',    'hands_head:right':   'Teorētiskais',
    'exec_strat:left':   'Sistemātiskais', 'exec_strat:right':   'Stratēģiskais',
    'solo_team:left':    'Patstāvīgais',   'solo_team:right':    'Sadarbīgais',
    'struct_adapt:left': 'Strukturētais',  'struct_adapt:right': 'Elastīgais',
    'people_data:left':  'Sabiedriskais',  'people_data:right':  'Analītiskais',
    'intu_anal:left':    'Intuitīvais',    'intu_anal:right':    'Analītiskais',
};
// Ko nozīmē katrs pols (fits "un to dara ___")
const POLE_MEANING = {
    'hands_head:left':   'praktiski un taustāmi, risinot reālus uzdevumus',
    'hands_head:right':  'teorētiski — ar idejām, teoriju un abstraktu domāšanu',
    'exec_strat:left':   'sistemātiski, soli pa solim pēc skaidras metodes',
    'exec_strat:right':  'stratēģiski, domājot uz priekšu un plānojot kopainu',
    'solo_team:left':    'patstāvīgi, paļaujoties uz sevi',
    'solo_team:right':   'sadarbībā, kopā ar citiem',
    'struct_adapt:left': 'strukturēti, pēc skaidra plāna un noteikumiem',
    'struct_adapt:right':'elastīgi, pielāgojoties situācijai',
    'people_data:left':  'kopā ar cilvēkiem un caur attiecībām',
    'people_data:right': 'analītiski, balstoties uz datiem un faktiem',
    'intu_anal:left':    'intuitīvi, paļaujoties uz nojautu',
    'intu_anal:right':   'analītiski, loģiski izsverot iespējas',
};

// Atgriež { name, meaning } — abi balstīti uz tiem pašiem 2 komponentiem,
// tāpēc skaidrojums vienmēr sakrīt ar nosaukumu.
function generateArchetype(profile) {
    const lt = profile.leadership || {};
    const leaderKey = lt.primary?.key || 'specialist';
    const noun = NOUN_BY_LEADER[leaderKey] || 'Speciālists';

    let adj = 'Daudzpusīgais';
    let bestKey = null;
    try {
        const spectrums = buildWorkFitSpectrum(profile);
        let bestDev = -1;
        for (const sp of spectrums) {
            const dev = Math.abs(sp.pos - 50);
            if (dev > bestDev) {
                bestDev = dev;
                bestKey = `${sp.key}:${sp.pos > 50 ? 'right' : 'left'}`;
            }
        }
        if (bestKey && POLE_ADJ[bestKey]) adj = POLE_ADJ[bestKey];
        else bestKey = null;
    } catch (e) { bestKey = null; /* fallback paliek 'Daudzpusīgais' */ }

    // Skaidrojums vienkāršā valodā — ko šis apzīmējums nozīmē
    const nounM = NOUN_MEANING[leaderKey] || 'kvalitatīvi un rūpīgi izpildīt konkrētus uzdevumus';
    const poleM = bestKey ? POLE_MEANING[bestKey] : null;

    // ── Ticamības vārteja ─────────────────────────────────────────────────────
    // Kad top-2 līderības arhetipi ir tuvu (≤5 p.) VAI dzimšanas laiks nav zināms,
    // viena pārliecināta etiķete ir nepatiesa precizitāte: šādos gadījumos leadership
    // skori saspiežas ap 50 un uzvarētājs ir 1–2 punktu artefakts (verificēts: solo 53
    // vs visionary 51, zināmam laikam uzvar specialist). Tāpēc hedžojam — rādām top-2
    // un ticamības piezīmi (konsekventi ar maskas sintēzi / time_sweep).
    const types = Array.isArray(lt.allTypes) ? lt.allTypes : [];
    const isTimeUnknown = profile.isTimeUnknown ?? profile.birth_info?.isTimeUnknown ?? false;
    const margin = (types.length >= 2) ? (types[0].score - types[1].score) : 99;
    const secondKey = (types.length >= 2) ? types[1].key : null;

    if (secondKey && (margin <= 5 || isTimeUnknown)) {
        const noun2 = NOUN_BY_LEADER[secondKey] || '';
        const tieNote = `Darba arhetips nav izteikti viens — profils atrodas starp diviem tuviem tipiem (${noun} ${types[0].score}% · ${noun2} ${types[1].score}%).`;
        const timeNote = isTimeUnknown
            ? ' Bez precīza dzimšanas laika daļa rādītāju (statuss, mājas) ir vidējoti, tāpēc šis tips ir orientējošs, ne galīgs.'
            : '';
        return { name: `${noun} / ${noun2}`, meaning: `${tieNote}${timeNote}`, weak: true };
    }

    const name = `${adj} ${noun}`;
    const meaning = poleM
        ? `Tas nozīmē, ka dabiskā loma ir ${nounM}, un to dara ${poleM}.`
        : `Tas nozīmē, ka dabiskā loma ir ${nounM}.`;

    return { name, meaning, weak: false };
}

// ── Arhetipa apraksta ģenerēšana (vienkārša valoda, bez astro terminiem) ──────
// BaZi elements → pamata rakstura tips; temperaments no REĀLĀM personības
// dimensijām (ne hellēnistiskā humora); Dasha → dzīves posms.
//   (Maiju zīmogs apzināti izlaists — tas paliek EXPERIMENT cilnē.)

// Pamata raksturs no BaZi elementa (nominatīvs, saskan ar "raksturs")
const CORE_NATURE = {
    'Koks':   'uz izaugsmi tendēts un ideālistisks',
    'Uguns':  'enerģisks un iedvesmojošs',
    'Zeme':   'stabils, praktisks un uzticams',
    'Metāls': 'precīzs, strukturēts un uz kvalitāti orientēts',
    'Ūdens':  'elastīgs, dziļdomīgs un pielāgoties spējīgs',
};
// Temperaments (akuzatīvs, seko "ar") — izvēlas pēc Big Five (sk. zemāk)
const CORE_TEMPERAMENT = {
    Choleric:    'dedzīgu, mērķtiecīgu enerģiju',
    Sanguine:    'dzīvespriecīgu, sabiedrisku enerģiju',
    Melancholic: 'pārdomātu, dziļu jūtīgumu',
    Phlegmatic:  'mierīgu, līdzsvarotu noskaņu',
};
// Pašreizējais dzīves posms no Dasha valdnieka (kas posms "dara")
const LIFE_PHASE = {
    'Saule':    'izceļ pašapliecināšanos, atpazīstamību un vadību',
    'Meness':   'pievērš uzmanību emocijām, tuvībai un mājām',
    'Marss':    'prasa rīcību, enerģiju un drosmīgus lēmumus',
    'Merkurs':  'veicina mācīšanos, saziņu un praktiskus darījumus',
    'Jupiters': 'paver izaugsmi, jaunas zināšanas un iespējas',
    'Venera':   'akcentē attiecības, harmoniju un radošumu',
    'Saturns':  'prasa disciplīnu, atbildību un nopietnu darbu',
    'Rahu':     'virza uz jauniem, ambicioziem mērķiem',
    'Ketu':     'aicina atlaist lieko un pievērsties iekšējam',
};

function generateArchetypeDesc(profile) {
    const dm    = profile.bazi?.Daymaster || {};
    const dasha = profile.vedic?.current_dasha?.lord || '';

    // Temperaments no REĀLĀM personības dimensijām (Eizenka 2 asis:
    // ekstraversija × neirotisms → 4 temperamenti) — saskan ar pārējiem datiem.
    const p = {};
    for (const cat of (profile.personality || []))
        for (const t of (cat.traits || [])) p[t.id] = t.pct ?? 50;
    const extravert = (p.extraversion ?? 50) >= 50;
    const stable    = (p.neuroticism  ?? 50) < 50;
    const tempKey = extravert
        ? (stable ? 'Sanguine'   : 'Choleric')
        : (stable ? 'Phlegmatic' : 'Melancholic');

    const nature      = CORE_NATURE[dm.element] || 'daudzpusīgs un pielāgojams';
    const temperament = CORE_TEMPERAMENT[tempKey];
    const phase       = LIFE_PHASE[dasha] || 'nes jaunas tēmas un pārmaiņas';

    return `Pamatā ${nature} raksturs ar ${temperament}. Šobrīd dzīves posmā, kas ${phase}.`;
}

// ── Luksofora krāsa pēc vērtības ──────────────────────────────────────────────
// inverse=true: augsta vērtība ir SLIKTA (piem. Riski) — tad krāsu skala apgriežas
const TRAFFIC = { green: '#22c55e', amber: '#f59e0b', red: '#ef4444' };
function trafficColor(pct, inverse = false) {
    const v = inverse ? 100 - pct : pct;
    if (v >= 67) return TRAFFIC.green;   // 🟢 spēcīga / labvēlīga zona
    if (v >= 40) return TRAFFIC.amber;   // 🟡 vidēja / situatīva zona
    return TRAFFIC.red;                  // 🔴 vāja / riskanta zona
}

// ── SVG Radara Diagramma (6 asis) ─────────────────────────────────────────────
function buildRadarSVG(values) {
    // values secībā: [Līderība, Noturība, Motivācija, Sadarbības spēja, Izpilde, Riski]
    // — tieši atbilst callout secībai! ("Komanda" → "Sadarbības spēja": radars mēra
    // SPĒJU, atšķirībā no spektra "Komandā", kas mēra tieksmi)
    const labels = ['Līderība', 'Noturība', 'Motivācija', 'Sadarbības spēja', 'Izpilde', 'Riski'];

    // Liels viewBox ar 90px padding, lai garākie vārdi pilnībā ietilpst
    const pad = 90;
    const maxR = 130;
    const cx = pad + maxR, cy = pad + maxR;
    const svgW = (pad + maxR) * 2;
    const svgH = svgW;
    const n = 6;

    const angle = (i) => (Math.PI * 2 * i / n) - Math.PI / 2;
    const pt = (i, r) => {
        const a = angle(i);
        return { x: cx + r * Math.cos(a), y: cy + r * Math.sin(a) };
    };

    // Fona tīklojums + skalas vērtības (25 / 50 / 75 / 100)
    let gridLines = '';
    let gridValues = '';
    [0.25, 0.5, 0.75, 1.0].forEach(frac => {
        const r = maxR * frac;
        let pts = [];
        for (let i = 0; i < n; i++) pts.push(pt(i, r));
        gridLines += `<polygon points="${pts.map(p => `${p.x},${p.y}`).join(' ')}"
            fill="none" stroke="${C.blue}" stroke-opacity="0.15" stroke-width="1"/>`;
        // Skalas skaitlis pie augšējās ass (nedaudz pa labi no spices)
        gridValues += `<text x="${cx + 5}" y="${cy - r + 3}" font-size="9.5"
            fill="${C.subtext}" font-family="'Outfit', sans-serif">${Math.round(frac * 100)}</text>`;
    });

    // Asu līnijas
    let axisLines = '';
    for (let i = 0; i < n; i++) {
        const p = pt(i, maxR);
        axisLines += `<line x1="${cx}" y1="${cy}" x2="${p.x}" y2="${p.y}" 
            stroke="${C.blue}" stroke-opacity="0.12" stroke-width="1"/>`;
    }

    // Luksofora krāsa katrai asij (indekss 5 = Riski → invertēts)
    const statusColors = values.map((v, i) => trafficColor(v, i === 5));

    // Datu poligons
    const dataPoints = values.map((v, i) => pt(i, maxR * (v / 100)));
    const dataPolygon = `<polygon points="${dataPoints.map(p => `${p.x},${p.y}`).join(' ')}"
        fill="${C.gold}" fill-opacity="0.18" stroke="${C.sage}" stroke-width="2.5" stroke-linejoin="round"/>`;

    // Datu punkti (luksofora krāsa pēc vērtības)
    let dots = '';
    dataPoints.forEach((p, i) => {
        dots += `<circle cx="${p.x}" cy="${p.y}" r="6" fill="${statusColors[i]}" stroke="${C.card}" stroke-width="2"/>`;
    });

    // Uzraksti — lieli, ar lielu attālumu no centra
    let labelTexts = '';
    const labelOffsets = [
        { dx: 0, dy: -14, anchor: 'middle' },   // top
        { dx: 14, dy: -3, anchor: 'start' },     // top-right
        { dx: 14, dy: 10, anchor: 'start' },     // bottom-right
        { dx: 0, dy: 22, anchor: 'middle' },     // bottom
        { dx: -14, dy: 10, anchor: 'end' },      // bottom-left
        { dx: -14, dy: -3, anchor: 'end' },      // top-left
    ];
    labels.forEach((lbl, i) => {
        const p = pt(i, maxR + 24);
        const off = labelOffsets[i];
        const val = values[i];
        labelTexts += `<text x="${p.x + off.dx}" y="${p.y + off.dy}" 
            text-anchor="${off.anchor}" font-size="15" font-weight="600" 
            fill="${C.text}" font-family="'Outfit', sans-serif">${lbl}</text>`;
        labelTexts += `<text x="${p.x + off.dx}" y="${p.y + off.dy + 17}"
            text-anchor="${off.anchor}" font-size="14" font-weight="700"
            fill="${statusColors[i]}" font-family="'Outfit', sans-serif">${val}%</text>`;
    });

    return `<svg viewBox="0 0 ${svgW} ${svgH}" width="100%" style="max-width:${svgW}px;">
        ${gridLines}${axisLines}${dataPolygon}${dots}${gridValues}${labelTexts}
    </svg>`;
}

// ── SVG Gauge Loks ────────────────────────────────────────────────────────────
// color = metrikas identitātes pasteļkrāsa (fona loks); statusColor = luksofors (aizpildījums + skaitlis)
function buildGaugeSVG(pct, color, label, desc, statusColor) {
    const r = 38;
    const stroke = 8;
    const circumference = 2 * Math.PI * r;
    const arcLen = circumference * 0.75; // 270° loks
    const filled = arcLen * (pct / 100);
    const arcColor = statusColor || color;

    return `
    <div class="vs-gauge-card" title="${desc}">
        <svg viewBox="0 0 100 100" width="105" height="105">
            <!-- Fona loks -->
            <circle cx="50" cy="50" r="${r}" fill="none"
                stroke="${color}" stroke-opacity="0.18" stroke-width="${stroke}"
                stroke-dasharray="${arcLen} ${circumference}"
                stroke-linecap="round"
                transform="rotate(135 50 50)"/>
            <!-- Aizpildījuma loks (luksofora krāsa) -->
            <circle cx="50" cy="50" r="${r}" fill="none"
                stroke="${arcColor}" stroke-width="${stroke}"
                stroke-dasharray="${filled} ${circumference}"
                stroke-linecap="round"
                transform="rotate(135 50 50)"
                class="vs-gauge-arc"/>
        </svg>
        <div class="vs-gauge-value" style="color: ${arcColor};">${pct}%</div>
        <div class="vs-gauge-label">${label}</div>
        <div class="vs-gauge-desc">${desc}</div>
    </div>`;
}

// ── Darba stila spektrs — bipolārs slīdnis ────────────────────────────────────
// pos: 0 = pilnībā kreisais polis, 100 = labais. Vērtību-neitrāls.
// Pasteļkrāsas pataisa tumšākas teksta lasāmībai (uz gaiša/smilšu fona).
// Šķēles paliek gaišajā krāsā; tikai teksts izmanto tumšāko variantu.
function darken(hex, f = 0.58) {
    const h = (hex || '#888888').replace('#', '');
    const c = i => Math.round(parseInt(h.slice(i, i + 2), 16) * f);
    return `rgb(${c(0)},${c(2)},${c(4)})`;
}

// Pusapļa PĪRĀGS vienam bipolāram spektram.
// Pusaplis sadalīts divās proporcionālās šķēlēs (kreisā = leftPct, labā = rightPct),
// katra sava pola krāsā. Lielākā šķēle = dominējošā funkcija — pēc krāsas vien
// (bez skaitļiem) redzams, uz kuru funkciju cilvēks vairāk piemērots.
function buildSpectrumPie(sp) {
    const pos      = Math.max(0, Math.min(100, sp.pos));
    const leftPct  = 100 - pos;
    const rightPct = pos;
    const leftColor  = sp.left.color  || '#7FB9E6';
    const rightColor = sp.right.color || '#FF8F45';

    const isBalanced = pos >= 45 && pos <= 55;
    const domSide    = pos > 50 ? 'right' : 'left';
    const domClass   = side => isBalanced ? '' : (domSide === side ? ' is-dom' : ' is-dim');

    // Pusaplis: centrs (90,96), rādiuss 70; loks no L(20,96) pār augšu uz R(160,96).
    // Šķēļu robeža pie matemātiskā leņķa θ = 1.8·pos (pos 0→labais gals, 100→kreisais gals).
    const r = 70, cx = 90, cy = 96;
    const tB = 1.8 * pos * Math.PI / 180;
    const Bx = (cx + r * Math.cos(tB)).toFixed(2);
    const By = (cy - r * Math.sin(tB)).toFixed(2);

    // Filled wedge ceļi (sweep=1 = pār augšu; slieksnis vienmēr ≤180° → large-arc=0)
    const leftWedge  = `M${cx},${cy} L20,96 A${r},${r} 0 0 1 ${Bx},${By} Z`;
    const rightWedge = `M${cx},${cy} L${Bx},${By} A${r},${r} 0 0 1 160,96 Z`;

    return `
    <div class="vs-spectrum-cell">
        <svg viewBox="0 0 180 110" class="vs-gauge-svg">
            <path d="${leftWedge}"  fill="${leftColor}"  stroke="#FFFFFF" stroke-width="2" stroke-linejoin="round"/>
            <path d="${rightWedge}" fill="${rightColor}" stroke="#FFFFFF" stroke-width="2" stroke-linejoin="round"/>
            <!-- Fiksēta atskaites skala (neatkarīga no datiem) -->
            <!-- 25% un 75% robežas — īsas svītras uz malas (θ=45° un θ=135°), bez līnijas no centra -->
            <line x1="135.3" y1="50.8" x2="143.7" y2="42.3" stroke="#1F2937" stroke-width="1.4" opacity="0.5"/>
            <line x1="44.7"  y1="50.8" x2="36.3"  y2="42.3" stroke="#1F2937" stroke-width="1.4" opacity="0.5"/>
            <!-- 50% robeža — raustīta līnija no centra, lai redzams pusapļa centrs -->
            <line x1="90" y1="96" x2="90" y2="26" stroke="#1F2937" stroke-width="1.4" stroke-dasharray="4 3" opacity="0.65"/>
            <text x="90" y="18" text-anchor="middle" font-size="9.5" font-weight="700" fill="#1F2937" font-family="'Outfit', sans-serif">50%</text>
        </svg>
        <div class="vs-gauge-ends">
            <span class="vs-gauge-end${domClass('left')}" style="color:${darken(leftColor)};">
                ${sp.left.icon} ${sp.left.label}<br><b>${leftPct}%</b>
            </span>
            <span class="vs-gauge-end vs-gauge-end--right${domClass('right')}" style="color:${darken(rightColor)};">
                ${sp.right.icon} ${sp.right.label}<br><b>${rightPct}%</b>
            </span>
        </div>
        <div class="vs-spectrum-verdict">${sp.verdict}</div>
    </div>`;
}

function buildSpectrumPanel(profile) {
    let spectrums;
    try {
        spectrums = buildWorkFitSpectrum(profile);
    } catch (e) {
        return `<div class="vs-spectrum-panel"><div class="vs-spectrum-heading">🧭 Darba stila spektrs</div>
            <div style="color:#ef4444;padding:0.5rem;">Spektra kļūda: ${e.message}</div></div>`;
    }
    if (!spectrums || !spectrums.length) return '';

    return `
    <div class="vs-spectrum-panel">
        <div class="vs-spectrum-heading">🧭 Darba stila spektrs
            <span class="vs-spectrum-sub">kurai lomai cilvēks der — dabiskais virziens</span>
        </div>
        <div class="vs-spectrum-grid">
            ${spectrums.map(buildSpectrumPie).join('')}
        </div>
        <div class="vs-spectrum-foot">Šie rādītāji domāti sarunai intervijā, nevis kandidātu atlasei. Neviens no abiem virzieniem nav labāks par otru — tie tikai parāda atšķirīgu darba stilu.</div>
    </div>`;
}

// ── Detaļu josla — kompakts paskaidrojums zem gauge ───────────────────────────
function buildDetailsStrip(synergy, metricDesc, leaderPctFinal, charismaInfo) {
    const s = synergy || {};
    const ci = charismaInfo || {};

    function detailRow(color, label, pct, descText, details) {
        // details = [{name, element, score, weight}, ...]
        let factorChips = '';
        if (details && details.length) {
            factorChips = details.map(d =>
                `<span class="vs-detail-factor">
                    <b>${d.name}:</b> ${d.element} → ${d.score}/10 (×${d.weight})
                </span>`
            ).join('');
        }
        return `
        <div class="vs-detail-row">
            <div class="vs-detail-header">
                <span class="vs-detail-dot" style="background:${color};"></span>
                <span class="vs-detail-title">${label} — ${pct}%</span>
                <span class="vs-detail-what">${descText}</span>
            </div>
            <div class="vs-detail-factors">${factorChips}</div>
        </div>`;
    }

    // Ārkārtas harizmas čipi līderības rindā
    const leaderDetails = [...(s.leadership?.details || [])];
    if (ci.warm != null) {
        leaderDetails.push({ name: '☀️ Siltā harizma',  element: `${ci.warm}%`, score: Math.round(ci.warm/10), weight: '50%' });
    }
    if (ci.cold != null) {
        leaderDetails.push({ name: '❄️ Aukstā harizma', element: `${ci.cold}%`, score: Math.round(ci.cold/10), weight: '50%' });
    }

    return `
    <div class="vs-details-panel">
        <div class="vs-details-heading vs-details-toggle" onclick="this.parentElement.classList.toggle('vs-expanded')">
            <span class="vs-toggle-icon">▶</span> 📊 Kā veidojas šie skaitļi?
        </div>
        <div class="vs-details-body">
            ${detailRow(C.blue,   'Līderība',   leaderPctFinal,  metricDesc.leadership,  leaderDetails)}
            ${detailRow(C.lilac,  'Noturība',   s.stress?.pct || 0,      metricDesc.stress,      s.stress?.details)}
            ${detailRow(C.gold,   'Motivācija', Math.round((Number(s.motivation?.['Intelektuālā']||0) + Number(s.motivation?.['Statusa']||0) + Number(s.motivation?.['Materiālā']||0)) / 3), metricDesc.motivation, null)}
            ${detailRow(C.sage,   'Sadarbības spēja', s.teamwork?.pct || 0, metricDesc.teamwork,  s.teamwork?.details)}
            ${detailRow(C.pink,   'Izpilde',    s.performance?.pct || 0, metricDesc.performance, s.performance?.details)}
            ${detailRow(C.orange, 'Riski',      s.risks?.pct || 0,       metricDesc.risks,       s.risks?.details)}

            <div class="vs-method-sub">🧭 Darba stila spektrs — kā aprēķināts</div>
            ${[
                ['🔧 Praktiķis ↔ 🧠 Teorētiķis',   'RIASEC praktiskā (R) pret pētniecisko (I) interesi; galvenais nošķīrums — atvērtība pieredzei'],
                ['⚙️ Izpildītājs ↔ 🗺️ Stratēģis', 'Speciālista loma + kārtības vide pret vizionāra lomu + komplekso vidi (Cynefin)'],
                ['🧍 Patstāvīgs ↔ 👥 Komandā',     'Neatkarības tieksme pret sabiedriskumu, labvēlību un silto harizmu'],
                ['📐 Strukturēts ↔ 🌊 Elastīgs',   'Apzinīgums + tradicionālisms + zema riska tieksme pret atvērtību + risku + radošumu'],
                ['🤝 Cilvēki ↔ 📊 Dati',           'RIASEC sociālā + uzņēmīgā pret pētniecisko + praktisko interesi'],
                ['🧭 Intuitīvs ↔ 🔬 Analītisks',   'Kānemana ātrā (S1) pret lēno (S2) domāšanas sistēmu'],
            ].map(([n, d]) => `
            <div class="vs-detail-row">
                <div class="vs-detail-header">
                    <span class="vs-detail-title">${n}</span>
                    <span class="vs-detail-what">${d}</span>
                </div>
            </div>`).join('')}
        </div>
    </div>`;
}

// ── GALVENĀ EKSPORTA FUNKCIJA ─────────────────────────────────────────────────
export function renderVisualShot(profile, birthLoc = '', currentLoc = '') {
    // 1. Arhetipa karte
    const { name: archetypeName, meaning: archetypeMeaning } = generateArchetype(profile);
    const archetypeDesc = generateArchetypeDesc(profile);
    const displayDate   = formatBirthDate(profile.birth_info?.date);

    // Meta rinda zem datuma: laiks (aprēķiniem) + dzimšanas vieta + šī brīža vieta
    const bi = profile.birth_info || {};
    const timeStr = bi.isTimeUnknown
        ? `${bi.time} UTC · vietējais pusdienlaiks (precīzs laiks nezināms)`
        : (bi.time ? `${bi.time} UTC` : '');
    const metaLine = [
        displayDate,
        timeStr,
        birthLoc  ? `📍 ${escapeHtml(birthLoc)}`        : null,
        currentLoc ? `🌍 Šobrīd: ${escapeHtml(currentLoc)}` : null,
    ].filter(Boolean).join('  ·  ');

    // 1b. Sānu paneļi — aizpilda tukšo telpu ap centrēto arhetipa tekstu.
    //     Stiprās puses + riski no līderības arhetipa (nedublē radaru zem kartes).
    const prim = profile.leadership?.primary || {};
    const sideItems = (arr) => (Array.isArray(arr) ? arr : [])
        .map(t => `<li class="vs-arch-side-item">${t}</li>`).join('');
    const strengthsHtml = (prim.strengths && prim.strengths.length) ? `
        <div class="vs-archetype-side vs-archetype-side--str">
            <div class="vs-arch-side-head">🟢 Stiprās puses</div>
            <ul class="vs-arch-side-list">${sideItems(prim.strengths)}</ul>
        </div>` : '';
    const risksHtml = (prim.risks && prim.risks.length) ? `
        <div class="vs-archetype-side vs-archetype-side--risk">
            <div class="vs-arch-side-head">⚠️ Jāuzmanās</div>
            <ul class="vs-arch-side-list">${sideItems(prim.risks)}</ul>
        </div>` : '';

    // 2. Sinerģijas dati radaram un gauge
    const s = profile.synergy || {};

    // Līderības integrācija: astro bāze + harizmas dati no leadership_type.js
    // Bez harizmas: Iņ Metāls dabū 4/10, bet reālajā dzīvē var būt aukstās harizmas līderis
    const astroLeaderPct = Number(s.leadership?.pct) || 0;
    const lt = profile.leadership || {};
    const warmPct = lt.warmCharisma || 0;   // Siltā harizma: charisma + social + expressiveness
    const coldPct = lt.coldCharisma || 0;   // Aukstā harizma: authority + analytical + locus + status
    const bestCharisma = Math.max(warmPct, coldPct); // Ņemam labāko — cilvēks var vadīt ar jebkuru tipu
    // Kombinējam: 50% astro bāze + 50% harizmas indikators
    const leaderPct = Math.round(astroLeaderPct * 0.50 + bestCharisma * 0.50);

    const stressPct = Number(s.stress?.pct) || 0;
    const teamPct   = Number(s.teamwork?.pct) || 0;
    const perfPct   = Number(s.performance?.pct) || 0;
    const riskPct   = Number(s.risks?.pct) || 0;

    // Motivācijas vidējais
    const mot = s.motivation || {};
    const motAvg = Math.round((
        Number(mot['Intelektuālā'] || 0) +
        Number(mot['Statusa'] || 0) +
        Number(mot['Materiālā'] || 0)
    ) / 3);

    // Radara vērtības — tieši atbilst gauge secībai un nosaukumiem
    const radarValues = [leaderPct, stressPct, motAvg, teamPct, perfPct, riskPct];
    const radarSVG = buildRadarSVG(radarValues);

    // Detaļu apraksti katrai metrikai
    const METRIC_DESC = {
        leadership: 'Astro bāze (50%) + Harizma (50%)',
        stress:     'Maiju krāsa + Tatva + BaZi Daymaster + Mēness stab.',
        motivation: 'Intelektuālā + Statusa + Materiālā (vidējais)',
        teamwork:   'Maiju lojalitāte + Diplomātija + Empātija + BaZi',
        performance:'BaZi + Maiju struktūra + Gara Daļa + Vēdiskais cikls',
        risks:      'Aizvainojums + Analīzes paralīze + Perfekcionisms + Ego',
    };
    // Dinamiski apraksti — pielāgojas % līmenim
    function descLeadership(p) {
        if (p >= 75) return 'Dabisks līderis — pārliecina ar klātbūtni, uzņemas iniciatīvu';
        if (p >= 45) return 'Vadīšana iespējama, bet labāk komandā ar skaidru struktūru';
        return 'Labāk darbojas kā speciālists vai padomdevējs, nevis vadītājs';
    }
    function descStress(p) {
        if (p >= 75) return 'Augsta stresa noturība — saglabā skaidru prātu krīzē';
        if (p >= 45) return 'Vidēja noturība — tiek galā, bet ilgstošs spiediens nogurdina';
        return 'Jūtīgs pret stresu — nepieciešama apzināta atpūta un robežas';
    }
    function descMotivation(p) {
        if (p >= 75) return 'Spēcīgi iekšējie dzinējspēki — ambīcijas, zinātkāre, mērķtiecība';
        if (p >= 45) return 'Motivācija ir, bet nepieciešams ārējs stimuls ilgtermiņā';
        return 'Motivācija uzliesmo īslaicīgi — vajag personīgi nozīmīgu mērķi';
    }
    function descTeamwork(p) {
        if (p >= 75) return 'Dabisks komandas cilvēks — empātija, lojalitāte, diplomātija';
        if (p >= 45) return 'Spēj sadarboties, bet periodiski vajag individuālu telpu';
        return 'Neatkarīgs darbības stils — labākos rezultātus sniedz viens';
    }
    function descPerformance(p) {
        if (p >= 75) return 'Augsta izpildspēja — sistemātiski pabeidz iesākto kvalitatīvi';
        if (p >= 45) return 'Laba izpilde pazīstamās jomās, jaunās prasa papildu piepūli';
        return 'Ideju ģenerators — izpilde labāka ar ārēju struktūru un termiņiem';
    }
    function descRisks(p) {
        if (p >= 75) return 'Izteiktas ēnu puses — aizvainojums, perfekcionisms, pārmērīga paškritika';
        if (p >= 45) return 'Mērenas ēnu tendences — apzināta pašvadība tās neitralizē';
        return 'Zems iekšējo risku līmenis — emocionāli līdzsvarots un elastīgs';
    }

    // 6 metriku apraksti ("callout" kastītes) ap radaru — grid-area atbilst
    // radara virsotnēm: lead=augša, stab=augš-labā, moti=apakš-labā,
    // team=apakša, perf=apakš-kreisā, risk=augš-kreisā (Riski = invertēts).
    const calloutData = [
        { area:'lead', name:'Līderība',   pct:leaderPct, color:C.blue,   desc:descLeadership(leaderPct), inverse:false },
        { area:'stab', name:'Noturība',   pct:stressPct, color:C.lilac,  desc:descStress(stressPct),     inverse:false },
        { area:'moti', name:'Motivācija', pct:motAvg,    color:C.gold,   desc:descMotivation(motAvg),    inverse:false },
        { area:'team', name:'Sadarbības spēja', pct:teamPct, color:C.sage, desc:descTeamwork(teamPct),    inverse:false },
        { area:'perf', name:'Izpilde',    pct:perfPct,   color:C.pink,   desc:descPerformance(perfPct),  inverse:false },
        { area:'risk', name:'Riski',      pct:riskPct,   color:C.orange, desc:descRisks(riskPct),        inverse:true  },
    ];
    const callouts = calloutData.map(c => {
        const tc = trafficColor(c.pct, c.inverse);
        return `
        <div class="vs-rc" style="grid-area:${c.area}; --rc-color:${c.color};">
            <div class="vs-rc-head">
                <span class="vs-rc-name">${c.name}</span>
                <span class="vs-rc-pct" style="color:${tc};">${c.pct}%</span>
            </div>
            <div class="vs-rc-desc">${c.desc}</div>
        </div>`;
    }).join('');

    // Detaļu josla — paskaidrojums, ko nozīmē katrs skaitlis
    const detailsHtml = buildDetailsStrip(s, METRIC_DESC, leaderPct, {
        warm: warmPct,
        cold: coldPct
    });
    // 3. "Šobrīd" tilts — laika dati (dzīves posms, šodienas kvalitāte, emocionālā
    //    tēma) tagad dzīvo "Kas notiek tagad?" cilnē ar pilnu kontekstu. Šeit tikai
    //    aicinājums uz to sadaļu, lai neradītu laika trokšņa pārklājumu virsū profila
    //    "kas šis cilvēks ir" lēmuma slānim.

    // Atgriežam DIVAS daļas:
    //  • profileHtml — arhetips + radars/spektrs + "Kā veidojas šie skaitļi?" (→ Profils cilne)
    //  • nowCtaHtml  — "ŠOBRĪD" tilts uz "Kas notiek tagad?" (→ 'x' cilne, kur atrodas q3)
    const profileHtml = `
    <div class="vs-container">
        <!-- A. ARHETIPA KARTE -->
        <div class="vs-archetype">
            ${strengthsHtml}
            <div class="vs-archetype-center">
                <div class="vs-archetype-date">${metaLine}</div>
                <div class="vs-archetype-title">${archetypeName}</div>
                <div class="vs-archetype-meaning">${archetypeMeaning}</div>
                <div class="vs-archetype-desc">${archetypeDesc}</div>
            </div>
            ${risksHtml}
        </div>

        <!-- B. VIDUS: kreisā = radars + 6 metriku apraksti; labā = darba stila pīrāgi -->
        <div class="vs-middle">
            <div class="vs-radar-zone">
                <div class="vs-radar-title" style="grid-area:title;">🎯 Spēju spektrs
                    <span class="vs-radar-sub">cik izteikta ir katra dabiskā nosliece — potenciāls, ne pierādīta prasme</span>
                </div>
                ${callouts}
                <div class="vs-radar" style="grid-area:radar;">
                    ${radarSVG}
                </div>
            </div>
            ${buildSpectrumPanel(profile)}
        </div>

        <!-- B2. DETAĻU PANELIS — kā veidojas skaitļi -->
        ${detailsHtml}
    </div>`;

    const nowCtaHtml = `
        <!-- C. "ŠOBRĪD" TILTS uz "Kas notiek tagad?" cilni -->
        <button type="button" class="vs-now-cta" onclick="window._goToMegaTab && window._goToMegaTab('q3-now')">
            <span class="vs-now-cta-icon">⏱️</span>
            <span class="vs-now-cta-text">
                <span class="vs-now-cta-title">Kur šis cilvēks ir tieši šobrīd?</span>
                <span class="vs-now-cta-sub">Pašreizējais dzīves posms, šodienas kvalitāte un emocionālā tēma — laika logs lēmumiem.</span>
            </span>
            <span class="vs-now-cta-arrow">Kas notiek tagad? →</span>
        </button>`;

    return { profileHtml, nowCtaHtml };
}
