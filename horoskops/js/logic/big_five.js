// ── PERSONĪBAS PIECINIEKS (OCEAN / Big Five) ──────────────────────────────────
// Katrs faktors tiek novērtēts skalā 1–10.
// Svērtais vidējais tiek normalizēts uz 0–100% ar kalibrētiem min/max.

// ── ATVĒRTĪBA (Openness) ─────────────────────────────────────────────────────
const MERCURY_OPEN = {
    'Dvīņi': 9, 'Ūdensvīrs': 8, 'Zivis': 7, 'Svari': 7,
    'Strēlnieks': 6, 'Lauva': 5, 'Vēzis': 5, 'Auns': 5,
    'Skorpions': 4, 'Vērsis': 4, 'Jaunava': 3, 'Mežāzis': 3,
};
const JUPITER_OPEN = {
    'Strēlnieks': 9, 'Zivis': 8, 'Vēzis': 8, 'Lauva': 6, 'Auns': 6, 'Svari': 6,
    'Dvīņi': 5, 'Ūdensvīrs': 5, 'Vērsis': 5, 'Skorpions': 4, 'Jaunava': 3, 'Mežāzis': 3,
};
const TONE_OPEN = {
    1: 5, 2: 7, 3: 7, 4: 3, 5: 6, 6: 6, 7: 8, 8: 3, 9: 6, 10: 6, 11: 8, 12: 8, 13: 6,
};
// Nakšatras ar augstu radošumu/zinātkāri
const NAK_OPEN = [
    { match: 'Ardra',           val: 8 }, { match: 'Swati',           val: 8 },
    { match: 'Mrigashira',      val: 7 }, { match: 'Chitra',          val: 7 },
    { match: 'Purva Phalguni',  val: 7 }, { match: 'Purva Bhadrapada', val: 7 },
    { match: 'Revati',          val: 7 }, { match: 'Shatabhisha',     val: 6 },
    { match: 'Punarvasu',       val: 6 }, { match: 'Ashwini',         val: 6 },
    { match: 'Dhanishta',       val: 5 }, { match: 'Vishakha',        val: 5 },
];

// ── APZINĪGUMS (Conscientiousness) ───────────────────────────────────────────
const SATURN_CONS = {
    'Mežāzis': 10, 'Ūdensvīrs': 9, 'Jaunava': 8, 'Svari': 8, 'Vērsis': 7,
    'Dvīņi': 5, 'Auns': 5, 'Skorpions': 4, 'Lauva': 4, 'Strēlnieks': 4,
    'Vēzis': 3, 'Zivis': 3,
};
const SUN_CONS = {
    'Mežāzis': 9, 'Jaunava': 8, 'Vērsis': 7, 'Ūdensvīrs': 6, 'Svari': 5,
    'Vēzis': 5, 'Skorpions': 5, 'Lauva': 4, 'Dvīņi': 4,
    'Auns': 3, 'Strēlnieks': 3, 'Zivis': 3,
};
// BaZi Daymaster → Apzinīgums
const DM_CONS = {
    'Jaņ Metāls': 9, 'Iņ Metāls': 8, 'Jaņ Zeme': 8, 'Iņ Zeme': 7,
    'Jaņ Koks': 5, 'Iņ Koks': 4, 'Jaņ Uguns': 4, 'Iņ Uguns': 3,
    'Jaņ Ūdens': 4, 'Iņ Ūdens': 3,
};

// ── EKSTRAVERSIJA (Extraversion) ─────────────────────────────────────────────
const SUN_EXTRA = {
    'Lauva': 10, 'Auns': 9, 'Strēlnieks': 8, 'Dvīņi': 7, 'Svari': 7,
    'Ūdensvīrs': 6, 'Vērsis': 5, 'Mežāzis': 4, 'Vēzis': 3, 'Jaunava': 3,
    'Zivis': 3, 'Skorpions': 2,
};
const MOON_EXTRA = {
    'Lauva': 9, 'Auns': 8, 'Strēlnieks': 7, 'Dvīņi': 7, 'Svari': 6, 'Ūdensvīrs': 6,
    'Vērsis': 5, 'Vēzis': 4, 'Mežāzis': 3, 'Jaunava': 3, 'Zivis': 3, 'Skorpions': 2,
};
const COLOR_EXTRA = {
    'Sarkans (Austrumi)': 9, 'Dzeltens (Dienvidi)': 8,
    'Balts (Ziemeļi)': 5, 'Zils/Melns (Rietumi)': 4,
};

// ── PRETIMNĀKŠANA (Agreeableness) ────────────────────────────────────────────
const VENUS_SIGN_AGRE = {
    'Vērsis': 10, 'Svari': 10, 'Zivis': 9, 'Vēzis': 8, 'Dvīņi': 7, 'Ūdensvīrs': 6,
    'Lauva': 5, 'Strēlnieks': 5, 'Jaunava': 5, 'Mežāzis': 4, 'Auns': 4, 'Skorpions': 3,
};
const VENUS_DIG_AGRE = {
    'Eksaltācija': 10, 'Valdījums': 9, 'Peregrīns': 6, 'Trimda': 3, 'Kritums': 2,
};
const MOON_AGRE = {
    'Vēzis': 10, 'Vērsis': 9, 'Zivis': 9, 'Jaunava': 7, 'Svari': 7, 'Dvīņi': 6,
    'Lauva': 5, 'Ūdensvīrs': 5, 'Strēlnieks': 5, 'Auns': 3, 'Mežāzis': 3, 'Skorpions': 2,
};
// Nakšatras ar augstu kooperāciju/empātiju
const NAK_AGRE = [
    { match: 'Rohini',    val: 9 }, { match: 'Pushya',    val: 9 }, { match: 'Hasta',    val: 8 },
    { match: 'Revati',    val: 8 }, { match: 'Anuradha',  val: 8 }, { match: 'Uttara Phalguni', val: 7 },
    { match: 'Shravana',  val: 7 }, { match: 'Punarvasu', val: 7 }, { match: 'Uttara Ashadha', val: 6 },
    { match: 'Uttara Bhadrapada', val: 6 },
];

// ── NEIROTISMS (Neuroticism) ──────────────────────────────────────────────────
const MOON_NEURO = {
    'Skorpions': 9, 'Vēzis': 8, 'Zivis': 8, 'Auns': 7, 'Dvīņi': 6,
    'Lauva': 5, 'Svari': 5, 'Vērsis': 4, 'Jaunava': 4,
    'Mežāzis': 3, 'Ūdensvīrs': 3, 'Strēlnieks': 2,
};
const RAHU_NEURO = {
    'Skorpions': 8, 'Vēzis': 7, 'Auns': 7, 'Zivis': 6, 'Mežāzis': 6,
    'Dvīņi': 5, 'Vērsis': 5, 'Jaunava': 5, 'Lauva': 5,
    'Svari': 4, 'Ūdensvīrs': 4, 'Strēlnieks': 3,
};
// Nakšatras ar augstu emocionālo nestabilitāti
const NAK_NEURO = [
    { match: 'Ardra',    val: 9 }, { match: 'Ashlesha',  val: 8 }, { match: 'Jyeshtha', val: 8 },
    { match: 'Mula',     val: 7 }, { match: 'Bharani',   val: 7 }, { match: 'Vishakha',  val: 6 },
    { match: 'Krittika', val: 6 }, { match: 'Purva Bhadrapada', val: 6 },
    { match: 'Magha',    val: 5 }, { match: 'Chitra',    val: 5 },
];

// ── TEMPERAMENTA SKORI (Hellēnistiskais) ─────────────────────────────────────
// [extra, conscient, agre, neuro]
const TEMP_SCORES = {
    'Choleric':    { extra: 8, cons: 5, agre: 2, neuro: 7 },
    'Sanguine':    { extra: 9, cons: 4, agre: 7, neuro: 4 },
    'Phlegmatic':  { extra: 4, cons: 7, agre: 9, neuro: 2 },
    'Melancholic': { extra: 3, cons: 8, agre: 5, neuro: 9 },
};

// ── PALĪGFUNKCIJAS ────────────────────────────────────────────────────────────

function safeGet(dict, key, def = 5) {
    return (dict && key in dict) ? dict[key] : def;
}

// Sigmoīds ar TO PAŠU slīpumu centrā ((min+max)/2), kāda bija vecajai lineārajai
// kartei — tipiskam (iekš min..max) rezultātam pāreja gandrīz nemainās. Atšķirība
// parādās tikai astēs: vecā formula pie robežas atgrieza cietu 5%/95% "plato" ar
// asu stūri (visi ekstrēmie profili iekrita VIENĀ skaitlī); sigmoīds tā vietā
// gludi (asimptotiski) turpina atšķirt arvien ekstrēmākus profilus.
function normalizePct(avg, min, max) {
    const mid = (min + max) / 2;
    const k = 4 / (max - min);
    const p = 100 / (1 + Math.exp(-k * (avg - mid)));
    return Math.round(Math.min(Math.max(p, 1), 99));
}

function lookupNak(nakName, table) {
    const hit = table.find(n => nakName.includes(n.match));
    return hit ? hit.val : 5;
}

function lookupTemp(humor, dim) {
    if (!humor) return 5;
    const keys = Object.keys(TEMP_SCORES);
    const matches = keys.filter(k => humor.includes(k));
    if (matches.length === 0) return 5;
    const sum = matches.reduce((s, k) => s + TEMP_SCORES[k][dim], 0);
    return sum / matches.length;
}

function elemRatio(elements, ...keys) {
    const total = Object.values(elements).reduce((s, v) => s + (typeof v === 'number' ? v : 0), 0) || 100;
    const part  = keys.reduce((s, k) => s + (elements[k] || 0), 0);
    return (part / total) * 10;
}

// ── GALVENĀ FUNKCIJA ──────────────────────────────────────────────────────────

export function calculateBigFive(profile) {
    const wp      = profile.western?.planets || {};
    const bazi    = profile.bazi || {};
    const hell    = profile.hellenistic?.data || {};
    const maya    = profile.maya_profile?.basic || {};
    const vedic   = profile.vedic || {};

    const dm      = bazi.Daymaster || {};
    const dmKey   = `${dm.polarity || ''} ${dm.element || ''}`.trim();
    const elems   = bazi.elements || {};
    const conflicts = (bazi.conflicts || []).length;
    const humor   = hell.humor || '';
    const sect    = hell.sect || '';
    const nakName = vedic.nakshatra?.nakshatra || '';
    const yogas   = vedic.yogas || [];

    const sunSign  = wp.Saule?.sign || '';
    const moonSign = wp.Meness?.sign || '';
    const mercSign = wp.Merkurs?.sign || '';
    const venSign  = wp.Venera?.sign || '';
    const venDig   = wp.Venera?.dignity || '';
    const jupSign  = wp.Jupiters?.sign || '';
    const satSign  = wp.Saturns?.sign || '';
    const satHouse = wp.Saturns?.house;
    const rahuSign = wp.Rahu?.sign || '';
    const mColor   = maya.color || '';
    const mTone    = Number(maya.tone) || 0;

    const critAspects = Math.min((profile.western?.psychology?.criticalAspectsBlocks || []).length, 3);

    // ── O: Atvērtība (Openness) ─────────────────────────────────────────────
    const o_merc  = safeGet(MERCURY_OPEN, mercSign);
    const o_jup   = safeGet(JUPITER_OPEN, jupSign);
    const o_nak   = lookupNak(nakName, NAK_OPEN);
    const o_elem  = elemRatio(elems, 'Ūdens', 'Koks');
    const o_tone  = TONE_OPEN[mTone] ?? 5;
    const o_yoga  = yogas.some(y => y.name?.includes('Saraswati') || y.name?.includes('Budha')) ? 8 : 5;

    const avg_O = o_merc*0.25 + o_jup*0.20 + o_nak*0.15 + o_elem*0.15 + o_tone*0.15 + o_yoga*0.10;
    const pct_O = normalizePct(avg_O, 3.0, 8.5);

    // ── C: Apzinīgums (Conscientiousness) ───────────────────────────────────
    const c_sat   = safeGet(SATURN_CONS, satSign);
    const c_satH  = [1,4,7,10].includes(satHouse) ? 8 : [2,5,8,11].includes(satHouse) ? 6 : 4;
    const c_dm    = DM_CONS[dmKey] ?? 5;
    const c_temp  = lookupTemp(humor, 'cons');
    const c_conf  = conflicts === 0 ? 8 : conflicts === 1 ? 5 : 3;
    const c_sun   = safeGet(SUN_CONS, sunSign);

    const avg_C = c_sat*0.20 + c_satH*0.10 + c_dm*0.25 + c_temp*0.15 + c_conf*0.15 + c_sun*0.15;
    const pct_C = normalizePct(avg_C, 3.0, 9.0);

    // ── E: Ekstraversija (Extraversion) ─────────────────────────────────────
    const e_sun   = safeGet(SUN_EXTRA, sunSign);
    const e_sect  = sect === 'Diena' ? 8 : 4;
    const e_elem  = elemRatio(elems, 'Uguns', 'Koks');
    const e_temp  = lookupTemp(humor, 'extra');
    const e_moon  = safeGet(MOON_EXTRA, moonSign);
    const e_color = safeGet(COLOR_EXTRA, mColor);

    const avg_E = e_sun*0.25 + e_sect*0.15 + e_elem*0.15 + e_temp*0.20 + e_moon*0.15 + e_color*0.10;
    const pct_E = normalizePct(avg_E, 2.5, 9.0);

    // ── A: Pretimnākšana (Agreeableness) ────────────────────────────────────
    const a_venS  = safeGet(VENUS_SIGN_AGRE, venSign);
    const a_venD  = VENUS_DIG_AGRE[venDig] ?? 6;
    const a_moon  = safeGet(MOON_AGRE, moonSign);
    const a_elem  = elemRatio(elems, 'Koks', 'Ūdens');
    const a_temp  = lookupTemp(humor, 'agre');
    const a_nak   = lookupNak(nakName, NAK_AGRE);

    const avg_A = a_venS*0.20 + a_venD*0.10 + a_moon*0.20 + a_elem*0.15 + a_temp*0.20 + a_nak*0.15;
    const pct_A = normalizePct(avg_A, 2.5, 9.5);

    // ── N: Neirotisms (Neuroticism) ──────────────────────────────────────────
    const n_conf  = conflicts === 0 ? 2 : conflicts === 1 ? 5 : 9;
    const n_temp  = lookupTemp(humor, 'neuro');
    const n_moon  = safeGet(MOON_NEURO, moonSign);
    const n_nak   = lookupNak(nakName, NAK_NEURO);
    const n_asp   = critAspects * 3 + 2;
    const n_rahu  = safeGet(RAHU_NEURO, rahuSign);

    const avg_N = n_conf*0.20 + n_temp*0.20 + n_moon*0.20 + n_nak*0.15 + n_asp*0.15 + n_rahu*0.10;
    const pct_N = normalizePct(avg_N, 2.0, 9.0);

    // ── Teksta apraksti ──────────────────────────────────────────────────────
    function desc(pct, high, low) {
        return pct >= 60 ? high : pct >= 40 ? `Vidējs — ${high.split(',')[0].toLowerCase()}, bet ${low.split(',')[0].toLowerCase()}` : low;
    }

    return {
        O: {
            pct: pct_O, label: 'Atvērtība',
            desc: desc(pct_O, 'Radošs, zinātkārs, eksperimentē ar idejām', 'Praktisks, tradicionāls, strukturēts'),
            details: [
                { name: 'Merkurs', val: `${mercSign || '—'} (${o_merc})`, w: '25%' },
                { name: 'Jupiters', val: `${jupSign || '—'} (${o_jup})`, w: '20%' },
                { name: 'Nakšatra', val: `${nakName || '—'} (${o_nak})`, w: '15%' },
                { name: 'Ūdens+Koks', val: `${o_elem.toFixed(1)}`, w: '15%' },
                { name: 'Maiju tonis', val: `${mTone} (${o_tone})`, w: '15%' },
                { name: 'Yogas', val: `(${o_yoga})`, w: '10%' },
            ],
        },
        C: {
            pct: pct_C, label: 'Apzinīgums',
            desc: desc(pct_C, 'Disciplinēts, organizēts, uzticams un mērķtiecīgs', 'Brīvs, spontāns, mainīgs darba ritms'),
            details: [
                { name: 'Saturns', val: `${satSign || '—'} (${c_sat})`, w: '20%' },
                { name: 'Sat. māja', val: `${satHouse || '?'}. māja (${c_satH})`, w: '10%' },
                { name: 'Daymaster', val: `${dmKey || '—'} (${c_dm})`, w: '25%' },
                { name: 'Temperaments', val: `(${c_temp.toFixed(1)})`, w: '15%' },
                { name: 'BaZi konflikti', val: `${conflicts} (${c_conf})`, w: '15%' },
                { name: 'Saule', val: `${sunSign || '—'} (${c_sun})`, w: '15%' },
            ],
        },
        E: {
            pct: pct_E, label: 'Ekstraversija',
            desc: desc(pct_E, 'Sabiedrisks, energisks, meklē sociālu stimulāciju', 'Introspektīvs, neatkarīgs, dod priekšroku klusumam'),
            details: [
                { name: 'Saule', val: `${sunSign || '—'} (${e_sun})`, w: '25%' },
                { name: 'Sekta', val: `${sect || '—'} (${e_sect})`, w: '15%' },
                { name: 'Uguns+Koks', val: `${e_elem.toFixed(1)}`, w: '15%' },
                { name: 'Temperaments', val: `(${e_temp.toFixed(1)})`, w: '20%' },
                { name: 'Mēness', val: `${moonSign || '—'} (${e_moon})`, w: '15%' },
                { name: 'Maiju krāsa', val: `${mColor.split(' ')[0] || '—'} (${e_color})`, w: '10%' },
            ],
        },
        A: {
            pct: pct_A, label: 'Pretimnākšana',
            desc: desc(pct_A, 'Empātisks, kooperatīvs, uzticošs un altruistisks', 'Kritisks, neatkarīgs, skeptisks pret citiem'),
            details: [
                { name: 'Venera (zīme)', val: `${venSign || '—'} (${a_venS})`, w: '20%' },
                { name: 'Venera (dig.)', val: `${venDig || '—'} (${a_venD})`, w: '10%' },
                { name: 'Mēness', val: `${moonSign || '—'} (${a_moon})`, w: '20%' },
                { name: 'Koks+Ūdens', val: `${a_elem.toFixed(1)}`, w: '15%' },
                { name: 'Temperaments', val: `(${a_temp.toFixed(1)})`, w: '20%' },
                { name: 'Nakšatra', val: `${nakName || '—'} (${a_nak})`, w: '15%' },
            ],
        },
        N: {
            pct: pct_N, label: 'Neirotisms',
            desc: desc(pct_N, 'Emocionāli reaktīvs, jutīgs, augsta stresa uzņēmība', 'Emocionāli stabils, aukstasinīgs zem spiediena'),
            details: [
                { name: 'BaZi konflikti', val: `${conflicts} (${n_conf})`, w: '20%' },
                { name: 'Temperaments', val: `(${n_temp.toFixed(1)})`, w: '20%' },
                { name: 'Mēness', val: `${moonSign || '—'} (${n_moon})`, w: '20%' },
                { name: 'Nakšatra', val: `${nakName || '—'} (${n_nak})`, w: '15%' },
                { name: 'Kritiskie aspekti', val: `${critAspects} (${n_asp})`, w: '15%' },
                { name: 'Rahu', val: `${rahuSign || '—'} (${n_rahu})`, w: '10%' },
            ],
        },
    };
}
