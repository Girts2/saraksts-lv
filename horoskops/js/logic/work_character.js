// ── UZMEKLĒŠANAS TABULAS ───────────────────────────────────────────────────────

const DAYMASTER_SCORES = {
    'Jaņ Metāls': { ax1: +9, ax2: +7 },
    'Jaņ Uguns':  { ax1: +7, ax2: -4 },
    'Jaņ Zeme':   { ax1: +6, ax2: +8 },
    'Jaņ Koks':   { ax1: +5, ax2: +2 },
    'Jaņ Ūdens':  { ax1: +3, ax2: +5 },
    'Iņ Metāls':  { ax1: +4, ax2: +5 },
    'Iņ Zeme':    { ax1: +2, ax2: +6 },
    'Iņ Uguns':   { ax1: -2, ax2: -3 },
    'Iņ Koks':    { ax1: -5, ax2: +1 },
    'Iņ Ūdens':   { ax1: -7, ax2: +4 },
};

const TEMPERAMENT_SCORES = [
    { key: 'Choleric',    ax1: +8, ax2: -6 },
    { key: 'Sanguine',    ax1: +5, ax2: -2 },
    { key: 'Melancholic', ax1: -3, ax2: +6 },
    { key: 'Phlegmatic',  ax1: -4, ax2: +9 },
];

const CARDINAL_SIGNS = new Set(['Auns', 'Vēzis', 'Svari', 'Mežāzis']);
const FIXED_SIGNS    = new Set(['Vērsis', 'Lauva', 'Skorpions', 'Ūdensvīrs']);
const SATURN_MODALITY_SCORE = (sign) => {
    if (!sign)                    return { ax1:  0, ax2:  0 };
    if (CARDINAL_SIGNS.has(sign)) return { ax1: +6, ax2: -1 };
    if (FIXED_SIGNS.has(sign))    return { ax1: +5, ax2: +4 };
    return                               { ax1: -5, ax2: +2 };
};

const DASHA_SCORES = {
    'Saule':    { ax1: +3, ax2: +2 },
    'Marss':    { ax1: +5, ax2: -4 },
    'Saturns':  { ax1: +1, ax2: +6 },
    'Jupiters': { ax1: +2, ax2: +3 },
    'Rahu':     { ax1: +4, ax2: -5 },
    'Venera':   { ax1: -3, ax2: +1 },
    'Meness':   { ax1: -4, ax2: -2 },
    'Merkurs':  { ax1:  0, ax2:  0 },
    'Ketu':     { ax1: -2, ax2: +3 },
};

const NAKSHATRA_AX2 = [
    { match: 'Rohini',    val: +7 }, { match: 'Pushya',    val: +7 }, { match: 'Anuradha', val: +7 },
    { match: 'Uttara Phalguni', val: +6 }, { match: 'Uttara Ashadha', val: +6 }, { match: 'Uttara Bhadrapada', val: +6 },
    { match: 'Hasta',     val: +5 }, { match: 'Revati',    val: +5 }, { match: 'Shravana',  val: +5 },
    { match: 'Punarvasu', val: +3 }, { match: 'Swati',     val: +2 }, { match: 'Ashwini',   val: +2 },
    { match: 'Shatabhisha', val: +2 }, { match: 'Mrigashira', val: +1 },
    { match: 'Purva Phalguni', val: -1 }, { match: 'Dhanishta', val: -1 },
    { match: 'Magha',     val: -2 }, { match: 'Chitra',    val: -2 }, { match: 'Purva Ashadha', val: -2 },
    { match: 'Vishakha',  val: -3 }, { match: 'Krittika',  val: -3 }, { match: 'Purva Bhadrapada', val: -3 },
    { match: 'Bharani',   val: -4 }, { match: 'Ashlesha',  val: -4 },
    { match: 'Jyeshtha',  val: -5 }, { match: 'Mula',      val: -5 },
    { match: 'Ardra',     val: -6 },
];

const MARS_AX2 = {
    'Mežāzis': +6, 'Vērsis': +5, 'Jaunava': +4,
    'Skorpions': +2, 'Svari': 0, 'Ūdensvīrs': -1,
    'Lauva': -2, 'Dvīņi': -3,
    'Vēzis': -4, 'Zivis': -4,
    'Strēlnieks': -5,
    'Auns': -7,
};

const RAHU_AX2 = {
    'Vērsis': +3, 'Dvīņi': +2, 'Jaunava': +2, 'Mežāzis': +1,
    'Svari': 0, 'Ūdensvīrs': 0, 'Lauva': -1, 'Strēlnieks': -1,
    'Zivis': -2, 'Auns': -2, 'Vēzis': -3, 'Skorpions': -4,
};

// Saules zīme → Ass 1 bāzes skore (dominances potenciāls)
const SUN_SIGN_AX1 = {
    'Mežāzis':    +8, 'Lauva':      +7,
    'Auns':       +6, 'Skorpions':  +5,
    'Strēlnieks': +4, 'Vērsis':     +3,
    'Dvīņi':      +2, 'Ūdensvīrs':  +2,
    'Jaunava':    +1, 'Svari':      -1,
    'Vēzis':      -2, 'Zivis':      -3,
};

// Jupitera zīme → ax1 (paplašinājums/autoritāte) + ax2 (stabilitāte)
const JUPITER_SCORES = {
    'Strēlnieks': { ax1: +5, ax2: +3 },
    'Zivis':      { ax1: +4, ax2: +4 },
    'Vēzis':      { ax1: +3, ax2: +5 },
    'Lauva':      { ax1: +3, ax2: +2 },
    'Auns':       { ax1: +3, ax2: -1 },
    'Vērsis':     { ax1: +1, ax2: +3 },
    'Skorpions':  { ax1: +1, ax2: -2 },
    'Svari':      { ax1:  0, ax2: +2 },
    'Ūdensvīrs':  { ax1: -1, ax2: +1 },
    'Dvīņi':      { ax1: -1, ax2: +1 },
    'Jaunava':    { ax1: -2, ax2: +2 },
    'Mežāzis':    { ax1: -2, ax2: +1 },
};

// BaZi Desmit Dievu ietekme uz Ass 1 (Dominance)
const TEN_GOD_AX1 = {
    'Seven_Killings':    +6,
    'Rob_Wealth':        +4,
    'Indirect_Wealth':   +3,
    'Direct_Officer':    +3,
    'Direct_Wealth':     +2,
    'Eating_God':        +1,
    'Friend':             0,
    'Indirect_Resource': -1,
    'Direct_Resource':   -1,
    'Hurting_Officer':   -2,
};

// Veneras dignitas → Ass 2 (Stabilitāte)
const VENUS_DIGNITY_AX2 = {
    'Eksaltācija': +4,
    'Valdījums':   +3,
    'Peregrīns':    0,
    'Trimda':      -2,
    'Kritums':     -3,
};

// ── KVADRANTU DEFINĪCIJAS ──────────────────────────────────────────────────────

const QUADRANT_DEFS = [
    {
        id: 'strategis',
        name: 'Stratēģis',
        dom_min: 50, stab_min: 50,
        color: '#fefce8', border: '#ca8a04', textColor: '#713f12',
        icon: '♟',
        tagline: 'Dominants · Stabils',
        strengths: ['Sistēmu veidošana', 'Ilgtermiņa plānošana', 'Lēmumu pieņemšana', 'Komandas vadīšana'],
        risks: ['Autoritārisms', 'Pretestība ātrai pārmaiņai', 'Mikropārvaldība'],
        ideal_roles: ['COO', 'Projektu vadītājs', 'Operāciju direktors', 'Vadošais speciālists'],
        base_desc: 'Dabiski orientēts uz struktūru, hierarhiju un ilgtermiņa rezultātu. Labi jūtas ar skaidriem pienākumiem un mērāmiem mērķiem. Zem spiediena kļūst kontrolētāks, nevis haotiskāks.',
    },
    {
        id: 'pionieris',
        name: 'Pionieris',
        dom_min: 50, stab_min: 0, dom_max: 100, stab_max: 50,
        color: '#fff1f2', border: '#dc2626', textColor: '#7f1d1d',
        icon: '⚡',
        tagline: 'Dominants · Straujš',
        strengths: ['Krīžu menedžments', 'Inovācijas', 'Ātras iniciatīvas', 'Sistēmu laušana'],
        risks: ['Ātra izdegšana', 'Nepabeigtie projekti', 'Konflikti ar vadību'],
        ideal_roles: ['Uzņēmējs', 'Krīzes menedžeris', 'Start-up vadītājs', 'Pārdošanas vadītājs'],
        base_desc: 'Ātra rīcība, augsta enerģija un spēja sākt lietas no nulles. Labākie rezultāti dinamiskā, mainīgā vidē. Nepieciešama komandā stabilizējoša figūra ilgtermiņa projektu īstenošanai.',
    },
    {
        id: 'diplomats',
        name: 'Diplomāts',
        dom_min: 0, stab_min: 50, dom_max: 50, stab_max: 100,
        color: '#f0fdf4', border: '#16a34a', textColor: '#14532d',
        icon: '🤝',
        tagline: 'Pielāgojams · Stabils',
        strengths: ['Komandas kohēzija', 'Klientu attiecības', 'Konfliktu risināšana', 'Ilgtermiņa uzticamība'],
        risks: ['Pārāk piekāpīgs', 'Lēna lēmumu pieņemšana', 'Izvairīšanās no konfliktiem'],
        ideal_roles: ['HR direktors', 'Account Manager', 'Projektu koordinators', 'Komandas līderis'],
        base_desc: 'Augsta empātija un sociālais intelekts. Vērtīgs jebkurā komandā kā "saiknes veidotājs". Zem spiediena paliek mierīgs un meklē kompromisu, kas var būt gan spēks, gan vājums.',
    },
    {
        id: 'katalizators',
        name: 'Katalizators',
        dom_min: 0, stab_min: 0, dom_max: 50, stab_max: 50,
        color: '#eff6ff', border: '#2563eb', textColor: '#1e3a8a',
        icon: '✦',
        tagline: 'Pielāgojams · Straujš',
        strengths: ['Radošums', 'Komandas noskaņojuma uztvere', 'Inovācija caur empātiju', 'Ātra adaptācija'],
        risks: ['Nekonsekvence ilgstošā slodzē', 'Lēmumu atlikšana', 'Emocionāla pārkraušanās'],
        ideal_roles: ['Radošais direktors', 'UX dizainers', 'Psihologs / Coach', 'PR speciālists'],
        base_desc: 'Augsta jutība pret vides signāliem un spēja ātri mainīt pieeju. Radošums un empātija ir galvenie instrumenti. Nepieciešama strukturēta vide un skaidri termiņi, lai spēks netiktu izkliedēts.',
    },
];

// ── PALĪGFUNKCIJAS ─────────────────────────────────────────────────────────────

function lookupNakshatra(name = '') {
    const match = NAKSHATRA_AX2.find(n => name.includes(n.match));
    return match ? match.val : 0;
}

function lookupTemperament(humor = '') {
    const found = TEMPERAMENT_SCORES.filter(t => humor.includes(t.key));
    if (found.length === 0) return { ax1: 0, ax2: 0 };
    const ax1 = found.reduce((s, t) => s + t.ax1, 0) / found.length;
    const ax2 = found.reduce((s, t) => s + t.ax2, 0) / found.length;
    return { ax1, ax2 };
}

function elementBalance(elements = {}, dmElement = '') {
    const vals = Object.values(elements).filter(v => typeof v === 'number');
    if (vals.length === 0) return 0;
    const avg = vals.reduce((s, v) => s + v, 0) / vals.length;
    const dmVal = elements[dmElement] || 0;
    if (dmVal >= avg * 1.4) return +2;
    if (dmVal <= avg * 0.6) return -2;
    return 0;
}

function conflictsAx2(conflicts = []) {
    if (conflicts.length === 0) return +3;
    if (conflicts.length === 1) return  0;
    return -4;
}

// Saules zīme + anarētiskais/kritiskais grāds → Ass 1 skore
function sunAx1(sign = '', longitude = 0) {
    const signScore = SUN_SIGN_AX1[sign] ?? 0;
    const deg = longitude % 30;
    // 29°+ = anarētiskais grāds: intensificē zīmes īpašības līdz galējai
    // 0°-1° = kritiskais grāds: svaiga, neapstrādāta enerģija
    const degMod = deg >= 28 ? +3 : deg <= 1 ? +2 : 0;
    return signScore + degMod;
}

function jupiterScore(sign = '') {
    return JUPITER_SCORES[sign] || { ax1: 0, ax2: 0 };
}

function tenGodAx1(godName = '') {
    return TEN_GOD_AX1[godName] ?? 0;
}

// Vēdiskie yogas → Ass 1 bonuss (valdīšanas/varas kombinācijas)
function yogasAx1(yogas = []) {
    let score = 0;
    for (const y of yogas) {
        if (y.type?.includes('Statuss') || y.type?.includes('Vara') || y.name?.includes('Raja')) score += 4;
        else if (y.type?.includes('Izcils') || y.name?.includes('Pancha')) score += 3;
        else if (y.type?.includes('Bagātība')) score += 2;
    }
    return Math.min(score, 8);
}

// BaZi harmoniskās kombinācijas → Ass 2 bonuss (samazina konfliktu efektu)
function combinationsAx2(combinations = []) {
    let score = 0;
    for (const c of combinations) {
        if (c.type?.includes('Trīskāršā Alianse')) score += 4;
        else if (c.type?.includes('Neitralizē'))   score += 3;
        else if (c.type?.includes('Harmonija'))    score += 2;
    }
    return Math.min(score, 6);
}

function venusDignityAx2(dignity = '') {
    return VENUS_DIGNITY_AX2[dignity] ?? 0;
}

function getQuadrant(dom, stab) {
    if (dom >= 50 && stab >= 50) return QUADRANT_DEFS[0]; // Stratēģis
    if (dom >= 50 && stab <  50) return QUADRANT_DEFS[1]; // Pionieris
    if (dom <  50 && stab >= 50) return QUADRANT_DEFS[2]; // Diplomāts
    return QUADRANT_DEFS[3];                               // Katalizators
}

// ── NORMALIZĀCIJA (kalibrēta ar faktisko tabulu min/max) ──────────────────────
// Ass 1: min=-4.28 (Iņ Ūdens+Phlegmatic+Mutable+MoonDasha+ZivisSaule+JaunavJup+HurtOff×2)
// Ass 1: max=+7.06 (JaņMetāls+Choleric+Cardinal+MarsDasha+LauvaAnaretic+StrēlJup+7Kill×2+Yogas8)
// Ass 2: min=-4.60 (Choleric+2xConflict+Ardra+AunsMars+SkorpRahu+0comb+KritumsVen+AunsJup)
// Ass 2: max=+5.88 (Phlegmatic+0conf+Rohini+MežāzisMars+VērsisRahu+6comb+EksaltVen+VēzisJup)
const AXIS_X = { min: -4.28, max: 7.06 };
const AXIS_Y = { min: -4.60, max: 5.88 };

// Simetriska ap 0: raw=0 → 50% (neitrāls), pozitīvā puse mērogota ar max, negatīvā ar |min|.
// Tas saskaņo skalu ar getQuadrant (kas pieņem 50% = neitrāls) un ar dominantPole (raw zīmi).
function normalize_x(raw) {
    const pct = raw >= 0 ? 50 + (raw / AXIS_X.max) * 50
                         : 50 - (raw / AXIS_X.min) * 50;   // AXIS_X.min < 0, raw < 0 → attiecība > 0
    return Math.round(Math.min(Math.max(pct, 5), 95));
}

function normalize_y(raw) {
    const pct = raw >= 0 ? 50 + (raw / AXIS_Y.max) * 50
                         : 50 - (raw / AXIS_Y.min) * 50;
    return Math.round(Math.min(Math.max(pct, 5), 95));
}

// ── GALVENĀ FUNKCIJA ───────────────────────────────────────────────────────────

export function calculateWorkCharacter(profile) {

    // ── Datu ieguve ────────────────────────────────────────────────────────────
    const dm         = profile.bazi?.Daymaster || {};
    const dmKey      = `${dm.polarity || ''} ${dm.element || ''}`.trim();
    const elements   = profile.bazi?.elements || {};
    const conflicts  = profile.bazi?.conflicts || [];
    const combinations = profile.bazi?.combinations || [];
    const humor      = profile.hellenistic?.data?.humor || '';
    const satSign    = profile.western?.planets?.Saturns?.sign || '';
    const marsSign   = profile.western?.planets?.Marss?.sign || '';
    const rahuSign   = profile.western?.planets?.Rahu?.sign || '';
    const dashaLord  = profile.vedic?.current_dasha?.lord || 'Merkurs';
    const nakName    = profile.vedic?.nakshatra?.nakshatra || '';

    // Jauni parametri (no dzimšanas datuma, nav nepieciešams laiks)
    const sunSign    = profile.western?.planets?.Saule?.sign || '';
    const sunLon     = profile.western?.planets?.Saule?.longitude ?? 0;
    const jupSign    = profile.western?.planets?.Jupiters?.sign || '';
    const venDig     = profile.western?.planets?.Venera?.dignity || '';
    const yearGod    = profile.bazi?.gods?.Year || '';
    const monthGod   = profile.bazi?.gods?.Month || '';
    const yogas      = profile.vedic?.yogas || [];

    // ── Skorji ────────────────────────────────────────────────────────────────
    const dm_s    = DAYMASTER_SCORES[dmKey]    || { ax1: 0, ax2: 0 };
    const elem_m  = elementBalance(elements, dm.element || '');
    const temp_s  = lookupTemperament(humor);
    const sat_s   = SATURN_MODALITY_SCORE(satSign);
    const dasha_s = DASHA_SCORES[dashaLord]   || { ax1: 0, ax2: 0 };
    const nak_v   = lookupNakshatra(nakName);
    const mars_v  = MARS_AX2[marsSign]        ?? 0;
    const rahu_v  = RAHU_AX2[rahuSign]        ?? 0;
    const conf_v  = conflictsAx2(conflicts);

    const sun_v   = sunAx1(sunSign, sunLon);
    const jup_s   = jupiterScore(jupSign);
    const year_v  = tenGodAx1(yearGod);
    const month_v = tenGodAx1(monthGod);
    const yogas_v = yogasAx1(yogas);
    const comb_v  = combinationsAx2(combinations);
    const ven_v   = venusDignityAx2(venDig);

    // ── Ass 1: Pielāgojams ↔ Dominants (svari kopā 100%) ──────────────────────
    const raw_x =
        dm_s.ax1    * 0.28 +   // BaZi Daymaster
        elem_m      * 0.10 +   // Elementu balanss
        temp_s.ax1  * 0.20 +   // Temperaments
        sat_s.ax1   * 0.10 +   // Saturna modalitāte
        dasha_s.ax1 * 0.07 +   // Dasha lords
        sun_v       * 0.08 +   // Saule (zīme + anarētiskais grāds)
        jup_s.ax1   * 0.07 +   // Jupiters
        year_v      * 0.05 +   // BaZi Gada Dievs
        month_v     * 0.03 +   // BaZi Mēneša Dievs
        yogas_v     * 0.02;    // Vēdiskie yogas

    // ── Ass 2: Straujš ↔ Stabils (svari kopā 100%) ───────────────────────────
    const raw_y =
        temp_s.ax2  * 0.25 +   // Temperaments
        conf_v      * 0.20 +   // BaZi konflikti
        nak_v       * 0.15 +   // Nakšatra
        mars_v      * 0.12 +   // Marss
        rahu_v      * 0.08 +   // Rahu
        comb_v      * 0.08 +   // BaZi harmoniskās kombinācijas
        ven_v       * 0.06 +   // Veneras dignitas
        jup_s.ax2   * 0.06;    // Jupiters (stabilitātes puse)

    const dominance_pct = normalize_x(raw_x);
    const stability_pct = normalize_y(raw_y);
    const quadrant      = getQuadrant(dominance_pct, stability_pct);

    const dashaDynamic  = buildDashaComment(dashaLord, quadrant.id);
    const stressComment = buildStressComment(conflicts, marsSign, nakName);

    // ── Iekšējo pretrunu detektors ─────────────────────────────────────────────
    // Katra faktora SVĒRTAIS pienesums (raw × svars) uz savu asi. Pretruna rodas,
    // kad nozīmīgi faktori vienu asi velk pretējos virzienos.
    const ax1Contribs = [
        { key:'daymaster',  name:'BaZi kodols',        label: dmKey,                              raw: dm_s.ax1,    w: 0.28 },
        { key:'temperament',name:'Temperaments',       label: humor,                              raw: temp_s.ax1,  w: 0.20 },
        { key:'elements',   name:'Elementu balanss',   label: `${elem_m >= 0 ? '+' : ''}${elem_m}`, raw: elem_m,    w: 0.10 },
        { key:'saturn',     name:'Saturna modalitāte', label: satSign,                            raw: sat_s.ax1,   w: 0.10 },
        { key:'sun',        name:'Saule',              label: sunSign,                            raw: sun_v,       w: 0.08 },
        { key:'dasha',      name:'Dašas periods',      label: dashaLord,                          raw: dasha_s.ax1, w: 0.07 },
        { key:'jupiter',    name:'Jupiters',           label: jupSign,                            raw: jup_s.ax1,   w: 0.07 },
        { key:'yearGod',    name:'BaZi Gada Dievs',    label: yearGod,                            raw: year_v,      w: 0.05 },
        { key:'monthGod',   name:'BaZi Mēneša Dievs',  label: monthGod,                           raw: month_v,     w: 0.03 },
        { key:'yogas',      name:'Vēdiskie yogas',     label: yogas.length ? `${yogas.length} yogas` : '', raw: yogas_v, w: 0.02 },
    ].map(f => ({ ...f, contrib: +(f.raw * f.w).toFixed(3) }));

    const ax2Contribs = [
        { key:'temperament', name:'Temperaments',      label: humor,                              raw: temp_s.ax2,  w: 0.25 },
        { key:'conflicts',   name:'BaZi konflikti',    label: conflicts.length ? `${conflicts.length} konflikti` : 'bez konfliktiem', raw: conf_v, w: 0.20 },
        { key:'nakshatra',   name:'Nakšatra',          label: nakName,                            raw: nak_v,       w: 0.15 },
        { key:'mars',        name:'Marss',             label: marsSign,                           raw: mars_v,      w: 0.12 },
        { key:'rahu',        name:'Rahu',              label: rahuSign,                           raw: rahu_v,      w: 0.08 },
        { key:'combinations',name:'BaZi harmonijas',   label: combinations.length ? `${combinations.length} harm.` : '', raw: comb_v, w: 0.08 },
        { key:'venus',       name:'Venera',            label: venDig,                             raw: ven_v,       w: 0.06 },
        { key:'jupiter',     name:'Jupiters',          label: jupSign,                            raw: jup_s.ax2,   w: 0.06 },
    ].map(f => ({ ...f, contrib: +(f.raw * f.w).toFixed(3) }));

    const innerTensions = detectInnerTensions(ax1Contribs, ax2Contribs);

    const sunDegInSign = (sunLon % 30).toFixed(1);
    const sunLabel     = sunSign ? `${sunSign} ${sunDegInSign}°${Number(sunDegInSign) >= 28 ? ' ⚠' : ''}` : '—';

    return {
        dominance_pct,
        stability_pct,
        quadrant,
        raw_x: +raw_x.toFixed(2),
        raw_y: +raw_y.toFixed(2),
        factors: {
            // Ass 1 faktori
            daymaster:    { label: dmKey || '—',                                    ax1: dm_s.ax1 },
            elements:     { label: `${elem_m >= 0 ? '+' : ''}${elem_m}`,            ax1: elem_m },
            temperament:  { label: humor || '—',                                    ax1: +temp_s.ax1.toFixed(1), ax2: +temp_s.ax2.toFixed(1) },
            saturn:       { label: satSign || '—',                                  ax1: sat_s.ax1 },
            dasha:        { label: dashaLord,                                       ax1: dasha_s.ax1 },
            sun:          { label: sunLabel,                                        ax1: sun_v },
            jupiter:      { label: jupSign || '—',                                  ax1: jup_s.ax1, ax2: jup_s.ax2 },
            yearGod:      { label: yearGod || '—',                                  ax1: year_v },
            monthGod:     { label: monthGod || '—',                                 ax1: month_v },
            yogas:        { label: yogas.length ? `${yogas.length} yogas` : '—',    ax1: yogas_v },
            // Ass 2 faktori
            nakshatra:    { label: nakName || '—',                                  ax2: nak_v },
            mars:         { label: marsSign || '—',                                 ax2: mars_v },
            rahu:         { label: rahuSign || '—',                                  ax2: rahu_v },
            conflicts:    { label: `${conflicts.length} konflikti`,                 ax2: conf_v },
            combinations: { label: combinations.length ? `${combinations.length} harm.` : '—', ax2: comb_v },
            venus:        { label: venDig || '—',                                   ax2: ven_v },
        },
        dashaDynamic,
        stressComment,
        innerTensions,
        forceMap: { ax1: ax1Contribs, ax2: ax2Contribs },
    };
}

// ── IEKŠĒJO PRETRUNU DETEKTORS ─────────────────────────────────────────────────
// Atrod, kur cilvēka spēki vienu asi velk pretējos virzienos. Tas paskaidro, KĀPĒC
// pozīcija ir tāda, kāda tā ir (piem., dominance 51% = spēki cīnās, ne neitralitāte),
// un atklāj iekšējo spriedzi, ko cilvēks par sevi, iespējams, nezina.

// posPole/negPole = nominatīvs joslas etiķetēm (sakrīt ar spēku karti).
const TENSION_AXES = {
    dominance: {
        label: 'Ietekme ↔ Pielāgošanās',
        posPole: 'Dominants',  negPole: 'Pielāgojams',   // raw > 0 / raw < 0
    },
    stability: {
        label: 'Stabilitāte ↔ Straujums',
        posPole: 'Stabils',    negPole: 'Straujš',
    },
};

// Interpretācija pa 10% pretspēka soļiem (indekss = floor(ratioPct/10), 0=0-9% … 9=90-100%).
// B2C terapeitisks tonis, 3. persona. Katrs solis = cita, precīzāk pieskaņota atbilde.
const STEP_MEANING = {
    dominance: {
        pos: [   // galvenais virziens: Dominants (vadība); pretpols: pielāgošanās
            'Vadība šai personai ir dabiska un nepārprotama — gandrīz nekas personu nevelk uz pielāgošanos. Virzienu persona uzņemas bez vilcināšanās.',
            'Šī persona dabiski uzņemas vadību. Tieksme pielāgoties ir tikko jaušama un lēmumus praktiski neietekmē.',
            'Šī persona pamatā vada un noteic virzienu, taču reizēm personā pamostas vēlme piekāpties — viegls pretsvars, kas nemaina kopējo stilu.',
            'Vadība paliek galvenā, bet manāma tieksme uz saskaņu dažkārt liek personai mīkstināt nostāju, pirms persona atgriežas pie noteiktības.',
            'Lai gan vadība dominē, vēlme pielāgoties ir pietiekami stipra, lai persona regulāri svārstītos starp savu viedokli un citu vajadzībām.',
            'Vadīšanas tieksme un vēlme piekāpties ir teju līdzvērtīgas — vai persona uzstās vai padosies, bieži izšķir konkrētā situācija, ne raksturs.',
            'Spēcīga iekšēja pretvilkme: persona grib vadīt, bet tikpat ļoti negrib radīt berzi — tāpēc rīcība stipri mainās atkarībā no apkārtējiem.',
            'Pretvilkme ir ļoti spēcīga — reizēm persona rīkojas tieši pretēji savai vadošajai dabai, piekāpjoties brīžos, kad patiesībā gribētu noteikt.',
            'Vadīšana un pielāgošanās personā cīnās gandrīz vienlīdzīgi; iekšējais konflikts ir teju pastāvīgs un prasa daudz enerģijas.',
            'Spēki velk gandrīz vienādi stipri — personu plēš starp vēlmi vadīt un vēlmi piekāpties, un virziens var mainīties no dienas uz dienu.',
        ],
        neg: [   // galvenais virziens: Pielāgojams (pielāgošanās); pretpols: dominēšana
            'Pielāgošanās šai personai ir dabiska — persona meklē saskaņu, un gandrīz nekas personu nevelk uz dominēšanu.',
            'Šī persona dabiski pielāgojas un sadarbojas. Vēlme noteikt virzienu ir tikko jaušama un reti izpaužas.',
            'Pamatā persona piekāpjas un meklē kopīgu valodu, taču reizēm uzplaiksnī klusa vēlme pateikt savu — viegls pretsvars.',
            'Pielāgošanās paliek galvenā, bet manāma vēlme noteikt virzienu dažkārt liek personai pārņemt vadību, pirms persona atkal atkāpjas.',
            'Lai gan persona pamatā pielāgojas, vēlme dominēt ir pietiekami stipra, lai regulāri raisītu svārstīšanos starp sekošanu un vadīšanu.',
            'Vēlme piekāpties un vēlme vadīt ir teju līdzvērtīgas — vai persona sekos vai pārņems vadību, bieži izšķir situācija.',
            'Spēcīga pretvilkme: persona grib saglabāt saskaņu, bet tikpat ļoti grib noteikt — tāpēc rīcība stipri mainās atkarībā no konteksta.',
            'Pretvilkme ir ļoti spēcīga — reizēm persona pārsteidzoši uzņemas komandu, lai gan personas pamatdaba ir pielāgoties.',
            'Pielāgošanās un dominēšana personā cīnās gandrīz vienlīdzīgi; iekšējais konflikts ir teju pastāvīgs.',
            'Spēki velk gandrīz vienādi stipri — personu plēš starp vēlmi sekot un vēlmi vadīt, un nostāja var mainīties no dienas uz dienu.',
        ],
    },
    stability: {
        pos: [   // galvenais virziens: Stabils (noturība); pretpols: straujums
            'Noturība šai personai ir dabiska — persona darbojas paredzami un mierīgi, gandrīz bez tieksmes pēc straujām pārmaiņām.',
            'Šī persona dabiski tiecas pēc stabilitātes. Vēlme pēc straujas kustības ir tikko jaušama.',
            'Pamatā persona darbojas noturīgi, taču reizēm pamostas vēlme pēc kustības un pārmaiņām — viegls pretsvars.',
            'Stabilitāte paliek galvenā, bet manāms nemiers dažkārt mudina personu meklēt jaunu tempu, pirms persona atgriežas pie ierastā.',
            'Lai gan noturība dominē, tieksme pēc straujuma ir pietiekami stipra, lai regulāri raisītu svārstīšanos starp mieru un steigu.',
            'Vēlme pēc stabilitātes un pēc kustības ir teju līdzvērtīgas — vai persona paliks pie ierastā vai meklēs pārmaiņas, bieži izšķir situācija.',
            'Spēcīga pretvilkme: personai vajag drošu pamatu, bet tikpat ļoti vajag kustību — tāpēc temps stipri mainās atkarībā no apstākļiem.',
            'Pretvilkme ir ļoti spēcīga — reizēm persona pēkšņi met visu apkārt un meklē jaunu, lai gan personas pamatdaba ir noturība.',
            'Noturība un straujums personā cīnās gandrīz vienlīdzīgi; iekšējais nemiers ir teju pastāvīgs.',
            'Spēki velk gandrīz vienādi stipri — personu plēš starp vajadzību pēc miera un vajadzību pēc kustības, un temps var mainīties no dienas uz dienu.',
        ],
        neg: [   // galvenais virziens: Straujš (kustība); pretpols: stabilitāte
            'Straujums šai personai ir dabisks — persona darbojas ātri un mainīgi, gandrīz bez tieksmes pēc nemainīga pamata.',
            'Šī persona dabiski darbojas ātri un kustīgi. Vēlme pēc stabilitātes ir tikko jaušama.',
            'Pamatā persona darbojas strauji, taču reizēm pamostas vēlme pēc drošības un noturības — viegls pretsvars.',
            'Straujums paliek galvenais, bet manāma vajadzība pēc stabilitātes dažkārt liek personai apstāties un nostiprināties, pirms persona atkal paātrinās.',
            'Lai gan straujums dominē, tieksme pēc noturības ir pietiekami stipra, lai regulāri raisītu svārstīšanos starp steigu un mieru.',
            'Vēlme pēc ātruma un pēc stabilitātes ir teju līdzvērtīgas — vai persona drāzīsies uz priekšu vai meklēs drošību, bieži izšķir situācija.',
            'Spēcīga pretvilkme: personai vajag kustību, bet tikpat ļoti vajag drošu pamatu — tāpēc temps stipri mainās atkarībā no apstākļiem.',
            'Pretvilkme ir ļoti spēcīga — reizēm persona pēkšņi pieprasa stabilitāti un rutīnu, lai gan personas pamatdaba ir straujums.',
            'Straujums un noturība personā cīnās gandrīz vienlīdzīgi; iekšējā spriedze ir teju pastāvīga.',
            'Spēki velk gandrīz vienādi stipri — personu plēš starp vajadzību pēc kustības un vajadzību pēc drošības, un temps var mainīties no dienas uz dienu.',
        ],
    },
};

// Pretspēka % → 10% solis (indekss 0-9). Saskaņotām asīm (bez spriedzes) ierobežo līdz 0-1 solim,
// pretrunām — sākot no 2. soļa (20%), lai teksts vienmēr saskan ar birku.
function stepIndex(ratioPct, hasTension) {
    let i = Math.min(Math.floor(ratioPct / 10), 9);
    return hasTension ? Math.max(i, 2) : Math.min(i, 1);
}

// Vienmēr atgriež rezultātu (abas asis tiek rādītas). hasTension=false → spēki saskaņoti.
function tensionForAxis(axisKey, contribs) {
    const ax  = TENSION_AXES[axisKey];
    const pos = contribs.filter(f => f.contrib >  0.04).sort((a, b) => b.contrib - a.contrib);
    const neg = contribs.filter(f => f.contrib < -0.04).sort((a, b) => a.contrib - b.contrib);

    const posSum = pos.reduce((s, f) => s + f.contrib, 0);
    const negSum = -neg.reduce((s, f) => s + f.contrib, 0);   // pozitīva magnitūda
    const minor  = Math.min(posSum, negSum);
    const major  = Math.max(posSum, negSum);
    const ratio  = major > 0 ? minor / major : 0;             // 0..1 — cik sašķelta ass

    // Reāla pretruna: pretsvars abās pusēs, pietiekami stiprs (citādi spēki saskaņoti).
    const hasTension = pos.length > 0 && neg.length > 0 && minor >= 0.15 && ratio >= 0.20;

    // Neto virziens: kura puse virsrokā (ja viena tukša — uzvar otra; ja abas tukšas — pēc kopsummas zīmes).
    const netIsPos = (posSum - negSum) >= 0;

    const intensity = !hasTension      ? 'none'
                    : ratio >= 0.55    ? 'strong'
                    : ratio >= 0.32    ? 'moderate' : 'mild';
    const intensityLabel = intensity === 'strong'   ? 'Spēcīga iekšējā pretruna'
                         : intensity === 'moderate' ? 'Mērena iekšējā spriedze'
                         : intensity === 'mild'     ? 'Viegls pretsvars'
                         : 'Saskaņots';

    // Naratīvs — pa 10% pretspēka soļiem. Konkrētie faktori redzami Spēku kartē izvērstajā skatā.
    const ratioPct = Math.round(ratio * 100);
    const meaning = STEP_MEANING[axisKey][netIsPos ? 'pos' : 'neg'][stepIndex(ratioPct, hasTension)];

    return {
        axis: axisKey,
        axisLabel: ax.label,
        posPole: ax.posPole, negPole: ax.negPole,
        dominantPole: netIsPos ? ax.posPole : ax.negPole,
        hasTension,
        intensity, intensityLabel,
        ratioPct,
        narrative: meaning,
    };
}

// Vienmēr abas asis FIKSĒTĀ secībā (Ietekme, tad Stabilitāte) — bez sakārtošanas pēc stipruma,
// lai grafiki vienmēr ir vienā un tajā pašā vietā.
function detectInnerTensions(ax1Contribs, ax2Contribs) {
    return [
        tensionForAxis('dominance', ax1Contribs),
        tensionForAxis('stability', ax2Contribs),
    ];
}

// ── TEKSTA ĢENERATORI ─────────────────────────────────────────────────────────

function buildDashaComment(lord, quadrantId) {
    const map = {
        'Marss':    { strategis: 'Marsa Dasha pastiprina izlēmību un ātrumu — risks ir pārāk agresīvs lēmumu stils.', pionieris: 'Marsa Dasha dod papildu degvielu — labākais periods iniciēt jaunus projektus vai pārdošanu.', diplomats: 'Marsa Dasha rada iekšēju spriedzi. Dabiskā diplomātija var saskarties ar nepacietību.', katalizators: 'Marsa Dasha var izgaismot jūsu enerģiju, bet arī radīt emocionālu nestabilitāti.' },
        'Saturns':  { strategis: 'Saturna Dasha ir jūsu mājasdarbs — struktūra un disciplīna šobrīd ir augstākajā punktā.', pionieris: 'Saturna Dasha bremzē jūsu dabisko dinamiku. Periods piemērots konsolidācijai, nevis riskam.', diplomats: 'Saturna Dasha stiprina uzticamību un atbildību — vislabākais periods ilgtermiņa saistībām.', katalizators: 'Saturna Dasha ienes struktūru. Izmantojiet šo laiku, lai nostiprinātu radošos projektus.' },
        'Jupiters': { strategis: 'Jupitera Dasha paplašina ietekmes loku — labs laiks vadošam amatam vai mentorēšanai.', pionieris: 'Jupitera Dasha dod gudrību bez bremzēm — augstas iespējas izaugsmei ar saprātīgu risku.', diplomats: 'Jupitera Dasha ir jūsu labākais periods — augsta atzinība un uzticamība no apkārtējiem.', katalizators: 'Jupitera Dasha nes paplašināšanos — radošie projekti šobrīd var augt straujāk nekā parasti.' },
        'Rahu':     { strategis: 'Rahu Dasha provocē uz neraksturīgu risku. Esiet uzmanīgi ar negaidītiem solījumiem.', pionieris: 'Rahu Dasha pastiprināt ambīcijas, bet arī nestabilitāti. Augstas iespējas, augsts risks.', diplomats: 'Rahu Dasha ievieš neparedzamu dinamiku. Attiecībās var rasties pārpratumi.', katalizators: 'Rahu Dasha vairo jutīgumu un radošumu, bet arī emocionālo neprognozējamību.' },
        'Venera':   { strategis: 'Veneras Dasha mīkstina lēmumu stilu. Labs laiks komandas veidošanai un attiecību nostiprināšanai.', pionieris: 'Veneras Dasha nedaudz bremzē agresivitāti — periods piemērots partnerattiecību un tīklu veidošanai.', diplomats: 'Veneras Dasha ir jūsu dabiskais elements — maksimāla pievilcība un sociālā ietekme.', katalizators: 'Veneras Dasha vairo jūtu intensitāti un radošumu — labākais periods mākslinieciskiem projektiem.' },
        'Saule':    { strategis: 'Saules Dasha pastiprina autoritāti un statusu — vadošas pozīcijas ir dabiskā vieta.', pionieris: 'Saules Dasha iedod fokusu un virzienu jūsu dinamiskajam raksturam.', diplomats: 'Saules Dasha var radīt vēlmi izcelties — neparasti jūsu diplomātiskajam stilam.', katalizators: 'Saules Dasha palielina pašapziņu — labs periods prezentācijām un vizibilitātei.' },
        'Meness':   { strategis: 'Mēness Dasha ienes emocionālu dimensiju. Intuīcija šobrīd ir tikpat svarīga kā stratēģija.', pionieris: 'Mēness Dasha var radīt svārstīgumu. Vēlme rīkoties saduras ar pieaugošām šaubām.', diplomats: 'Mēness Dasha ir labvēlīga — empātija un intuīcija šobrīd ir augstākajā punktā.', katalizators: 'Mēness Dasha pastiprināt jutīgumu un emocionālo uztveri — risks ir pārsātinājums.' },
        'Merkurs':  { strategis: 'Merkura Dasha ievieš analītisko skatījumu — labs laiks datu bāzētiem lēmumiem.', pionieris: 'Merkura Dasha nedaudz bremzē — periods piemērots plānošanai un komunikācijas uzlabošanai.', diplomats: 'Merkura Dasha uzlabo verbālo diplomātiju — sarunu vešana un prezentācijas ir stiprajos punktos.', katalizators: 'Merkura Dasha palīdz strukturēt radošās idejas — komunikācija ir efektīvāka nekā parasti.' },
        'Ketu':     { strategis: 'Ketu Dasha ievieš šaubas par struktūru — periods piemērots introspekcijā un sistēmu pārskatīšanai.', pionieris: 'Ketu Dasha var izkliedēt fokusu — izvairieties no lieliem riskiem šajā periodā.', diplomats: 'Ketu Dasha ievieš iekšēju meklēšanu — dabiskā empātija var padziļināties.', katalizators: 'Ketu Dasha ir jūsu filozofijas periods — radošums kļūst dziļāks, bet mazāk produktīvs.' },
    };
    return map[lord]?.[quadrantId] || `${lord} Dasha periods šobrīd ietekmē jūsu dabisko darba stilu.`;
}

function buildStressComment(conflicts, marsSign, nakName) {
    const parts = [];
    if (conflicts.length === 0) {
        parts.push('BaZi kartē nav strukturālu konfliktu — augsta iekšējā stabilitāte zem ilgstoša spiediena.');
    } else if (conflicts.length === 1) {
        parts.push(`Viens BaZi konflikts (${conflicts[0]?.pair?.join('–') || 'zars'}) rada periodisku iekšējo spriedzi. Stresa situācijās var rasties neparedzamas reakcijas.`);
    } else {
        parts.push(`${conflicts.length} BaZi konflikti norāda uz strukturālu iekšējo spriedzi. Zem spiediena uzvedība var būt grūti paredzama.`);
    }
    const reactMars = ['Auns', 'Strēlnieks', 'Vēzis', 'Zivis'];
    const stableMars = ['Mežāzis', 'Vērsis', 'Jaunava', 'Skorpions'];
    if (stableMars.includes(marsSign)) {
        parts.push(`Marss ${marsSign} nodrošina kontrolētu, mērķtiecīgu rīcību krīzē.`);
    } else if (reactMars.includes(marsSign)) {
        parts.push(`Marss ${marsSign} rada ātras, bet dažkārt impulsīvas reakcijas spriedzes brīžos.`);
    }
    const highEnergy = NAKSHATRA_AX2.find(n => nakName.includes(n.match) && n.val <= -4);
    if (highEnergy) {
        parts.push(`Nakšatra (${nakName}) pastiprina spēju darboties krīzē un pārveidot sistēmas.`);
    }
    return parts.join(' ');
}
