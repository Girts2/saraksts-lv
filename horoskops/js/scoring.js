const RESILIENCE_SCORES = {
    "Color_Resilience": {"Balts (Ziemeļi)": 10, "Sarkans (Austrumi)": 7, "Dzeltens (Dienvidi)": 6, "Zils/Melns (Rietumi)": 3},
    "Tatva_Resilience": {"Metāls (Akaša)": 9, "Uguns (Tedžas)": 7, "Koks (Prithivi)": 8, "Augsne (Vaiju)": 5, "Ūdens (Apas)": 4},
    "Bazi_Resilience": {"Jaņ Metāls": 10, "Jaņ Zeme": 9, "Jaņ Uguns": 8, "Iņ Metāls": 6, "Iņ Zeme": 6, "Jaņ Koks": 7, "Iņ Uguns": 5, "Jaņ Ūdens": 5, "Iņ Koks": 4, "Iņ Ūdens": 3},
    "Moon_Resilience": {0: 8, 1: 9, 2: 5, 3: 3, 4: 10, 5: 6, 6: 5, 7: 7, 8: 8, 9: 9, 10: 7, 11: 4}
};

const LEADERSHIP_SCORES = {
    "Vedic": {"Saule": 10, "Marss": 9, "Jupiters": 8, "Rahu": 7, "Saturns": 6, "Merkurs": 5, "Venera": 4, "Meness": 3, "Ketu": 2},
    "Bazi": {"Jaņ Uguns": 10, "Jaņ Metāls": 9, "Jaņ Koks": 8, "Jaņ Ūdens": 8, "Jaņ Zeme": 7, "Iņ Uguns": 5, "Iņ Metāls": 4, "Iņ Zeme": 4, "Iņ Koks": 3, "Iņ Ūdens": 3},
    "Maya_Color": {"Sarkans (Austrumi)": 9, "Dzeltens (Dienvidi)": 8, "Balts (Ziemeļi)": 7, "Zils/Melns (Rietumi)": 4},
    "Maya_Sign": {"Ben": 10, "Ahav (Ahau)": 9, "Men": 8, "Kaban": 7, "Čikčan (Chikchan)": 8, "Imiš (Imix)": 7, "Kan": 6, "Kavak (Cauac)": 6, "Čuven (Chuwen)": 5, "Lamat": 5, "Ecnab / Cnab": 5, "Hiš (Hix / Ix)": 4, "Akbaļ (Akbal)": 4, "Kib": 6, "Ik": 5, "Maņik (Manik)": 6, "Muļuk (Muluk)": 4, "Ok": 3, "Kimi / Ķimi": 4, "Eb": 2},
    "Tatva": {"Uguns (Tedžas)": 9, "Koks (Prithivi)": 8, "Augsne (Vaiju)": 6, "Metāls (Akaša)": 5, "Ūdens (Apas)": 3},
    "Wave": {"Sarkans": 4, "Balts": 5, "Zils": 7, "Dzeltens": 8},
    "Moon_Sign": {0: 9, 1: 6, 2: 5, 3: 3, 4: 9, 5: 5, 6: 4, 7: 8, 8: 7, 9: 10, 10: 6, 11: 2}
};

const TEAMWORK_SCORES = {
    "Maya_Lojalitate": {"Ok": 10, "Maņik (Manik)": 9, "Eb": 9, "Imiš (Imix)": 8, "Kib": 8, "Ben": 7, "Men": 6, "Kaban": 5, "Hiš (Hix / Ix)": 4, "Čuven (Chuwen)": 3},
    "Moon_Diplomatija": {6: 10, 1: 9, 10: 8, 11: 7, 2: 6, 5: 5, 0: 4, 9: 3, 4: 2},
    "Tatva_Empatija": {"Ūdens (Apas)": 10, "Koks (Prithivi)": 8, "Augsne (Vaiju)": 7, "Metāls (Akaša)": 4, "Uguns (Tedžas)": 2},
    "Bazi_Asums": {"Iņ Metāls": 3, "Jaņ Metāls": 2, "Jaņ Uguns": 4, "Iņ Uguns": 6, "Iņ Zeme": 9, "Jaņ Zeme": 8, "Iņ Koks": 10, "Jaņ Koks": 7},
    "Maya_Prats": {"Balts (Ziemeļi)": 5, "Dzeltens (Dienvidi)": 7, "Sarkans (Austrumi)": 6, "Zils/Melns (Rietumi)": 9}
};

// BUG FIX: Paplašinātas tabulas ar visiem iespējamajiem atslēgu tipiem
// Iepriekš trūka: Ketu/Rahu/Marss kā Dasha lordi; Dzeltens/Sarkans/Zils krāsas; Uguns/Koks/Ūdens elementi
const MOTIVATION_SCORES = {
    // Uzmeklē: dLord (Dasha lords) un mColor (Maiju krāsa)
    "Intellectual": {
        "Jupiters": 10, "Merkurs": 8, "Ketu": 8, "Saturns": 7,
        "Saule": 6,     "Rahu": 6,   "Venera": 5, "Meness": 4, "Marss": 3,
        "Balts (Ziemeļi)": 9, "Zils/Melns (Rietumi)": 7,
        "Dzeltens (Dienvidi)": 6, "Sarkans (Austrumi)": 5,
        "Kaban": 7
    },
    // Uzmeklē: nLord (nakšatras lords — TIKAI planētu nosaukumi)
    // BUG FIX: Noņemti "Ben" un "Ahav (Ahau)" (Maiju zīmes, nevis nakšatras lordi)
    "Status": {
        "Saule": 10, "Marss": 8, "Rahu": 7,    "Jupiters": 7,
        "Saturns": 6, "Merkurs": 5, "Venera": 4, "Meness": 4, "Ketu": 3
    },
    // Konstante 7.5 — tabula saglabāta tikai atsaucei
    "Social": {"Meness": 9, "Svari": 8, "Ūdens (Apas)": 7, "Ok": 10},
    // Uzmeklē: dmE (BaZi elements — TIKAI 5 elementi)
    // BUG FIX: Noņemti "Venera" un "Dzeltens (Dienvidi)" (nepareizi tipi); pievienoti Uguns/Koks/Ūdens
    "Material": {
        "Zeme": 8, "Metāls": 7, "Koks": 6, "Uguns": 5, "Ūdens": 4
    }
};

const PERFORMANCE_SCORES = {
    "Bazi_Finish": {"Iņ Metāls": 9, "Jaņ Metāls": 8, "Iņ Zeme": 8, "Jaņ Zeme": 7, "Iņ Koks": 6, "Jaņ Koks": 5},
    "Maya_Finish": {"Balts (Ziemeļi)": 8, "Dzeltens (Dienvidi)": 9, "Sarkans (Austrumi)": 4, "Zils/Melns (Rietumi)": 5},
    "Spirit_Sign": {"Zivis": 3, "Auns": 2, "Strēlnieks": 4, "Mežāzis": 9, "Jaunava": 10, "Vērsis": 8},
    "Vedic_Cycle": {"Jupiters": 6, "Saturns": 9, "Saule": 5, "Marss": 4, "Merkurs": 7}
};

// BUG FIX: Visas tabulas tagad satur tikai pareiza tipa atslēgas
const RISK_SCORES = {
    // Uzmeklē: mSign (Maiju zīme) — paplašināts ar visām 20 Maiju zīmēm
    "Elephant_Memory": {
        "Ok": 9, "Kaban": 8, "Akbaļ (Akbal)": 8, "Hiš (Hix / Ix)": 7,
        "Kimi / Ķimi": 7, "Ben": 6, "Kib": 6, "Imiš (Imix)": 6,
        "Čikčan (Chikchan)": 5, "Kan": 5, "Eb": 5,
        "Men": 5, "Ahav (Ahau)": 5,
        "Čuven (Chuwen)": 4, "Maņik (Manik)": 4, "Kavak (Cauac)": 4, "Ecnab / Cnab": 4,
        "Lamat": 3, "Muļuk (Muluk)": 3, "Ik": 3
    },
    // BUG FIX: Iepriekš hardkodēts "Svari"; tagad uzmeklē pēc ZODIAC_SIGNS[Math.floor(moonDeg/30)]
    // Noņemta "Balts (Ziemeļi)" (Maiju krāsa); pievienotas visas 12 zodiaka zīmes
    "Paralysis": {
        "Svari": 9, "Zivis": 8, "Dvīņi": 7, "Jaunava": 6,
        "Vērsis": 6, "Vēzis": 5, "Mežāzis": 5, "Skorpions": 5,
        "Ūdensvīrs": 4, "Strēlnieks": 4, "Lauva": 3, "Auns": 2
    },
    // BUG FIX: Noņemti "Mežāzis" un "Saule" (zodiak/planēta); pievienoti visi 10 BaZi tipi
    // Uzmeklē: `${dmP} ${dmE}` (BaZi Daymaster)
    "Pressure": {
        "Jaņ Metāls": 9, "Iņ Metāls": 8,
        "Jaņ Zeme": 8,  "Iņ Zeme": 7,
        "Jaņ Uguns": 7, "Iņ Uguns": 6,
        "Jaņ Koks": 5,  "Iņ Koks": 4,
        "Jaņ Ūdens": 5, "Iņ Ūdens": 4
    },
    // BUG FIX: Noņemti "Vēzis", "Zivis", "Meness" (zodiak/planēta); pievienotas visas 5 Tatvas
    // Uzmeklē: tatva (Maiju Tatva vērtība)
    "Fragility": {
        "Ūdens (Apas)": 7, "Uguns (Tedžas)": 6, "Koks (Prithivi)": 5,
        "Augsne (Vaiju)": 4, "Metāls (Akaša)": 3
    },
    // BUG FIX: Noņemti "Lauva" (zodiak) un "Ben" (Maiju zīme); pievienoti visi 9 nakšatras lordi
    // Uzmeklē: nLord (nakšatras lords — planētu nosaukumi)
    "Ego": {
        "Saule": 9, "Rahu": 8, "Marss": 7,    "Jupiters": 7,
        "Saturns": 5, "Merkurs": 4, "Venera": 4, "Meness": 4, "Ketu": 3
    }
};

const ZODIAC_SIGNS = [
    "Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava",
    "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"
];

function safeGet(dict, key, defaultVal = 5) {
    if (dict && key in dict) {
        return dict[key];
    }
    // Opt-in dev instrumentācija (invariantu testiem): reģistrē trūkstošās atslēgas, kas klusi
    // nokrīt uz noklusējumu 5. Prod vidē izslēgts (karogs nav uzstādīts) → nulle ietekmes.
    if (typeof globalThis !== 'undefined' && globalThis.__trackSafeGet) {
        (globalThis.__safeGetMisses = globalThis.__safeGetMisses || []).push(key);
    }
    return defaultVal;
}

function normalizePct(score, min, max) {
    let p = ((score - min) / (max - min)) * 100;
    if (p < 0) p = 0;
    if (p > 100) p = 100;
    return p.toFixed(0);
}

export function calculateLeadership(dashaLord, dmP, dmE, mColor, mSign, tatva, moonDeg, waveScoreValue) {
    const s_v = safeGet(LEADERSHIP_SCORES["Vedic"], dashaLord, 5);
    const s_b = safeGet(LEADERSHIP_SCORES["Bazi"], `${dmP} ${dmE}`, 5);
    const s_mc = safeGet(LEADERSHIP_SCORES["Maya_Color"], mColor, 5);
    const s_ms = safeGet(LEADERSHIP_SCORES["Maya_Sign"], mSign, 5);
    const s_t = safeGet(LEADERSHIP_SCORES["Tatva"], tatva, 5);
    const s_w = safeGet(LEADERSHIP_SCORES["Wave"], waveScoreValue, 5);
    const s_m = safeGet(LEADERSHIP_SCORES["Moon_Sign"], Math.floor(moonDeg / 30), 5);

    // SVARU KOREKCIJA: Vēdiskā ×3→×2 (temporāls — Dasha mainās ik 6-20g, bet līderība ne),
    //                  Mēness ×1→×2 (emocionālā autoritāte ir būtiskāks līderības indikators)
    const w_v = 2, w_b = 3, w_mc = 2, w_ms = 2, w_t = 1, w_m = 2, w_w = 1;
    const t_s = (s_v*w_v) + (s_b*w_b) + (s_mc*w_mc) + (s_ms*w_ms) + (s_t*w_t) + (s_m*w_m) + (s_w*w_w);
    const avg = t_s / (w_v + w_b + w_mc + w_ms + w_t + w_m + w_w);

    return {
        score: avg.toFixed(2),
        // min/max pārrēķināts ar jaunajiem svariem:
        // min: (2*2+3*3+4*2+2*2+3*1+2*2+4*1)/13 = 2.77
        // max: (10*2+10*3+9*2+10*2+9*1+10*2+8*1)/13 = 9.62
        pct: normalizePct(avg, 2.77, 9.62),
        details: [
            { name: "Vēdiskā (Statuss)", element: dashaLord, score: s_v, weight: w_v },
            { name: "BaZi (Iekšējais)", element: `${dmP} ${dmE}`, score: s_b, weight: w_b },
            { name: "Maiji (Temp)", element: mColor.split(" ")[0], score: s_mc, weight: w_mc },
            { name: "Maiji (Dvēsele)", element: mSign, score: s_ms, weight: w_ms },
            { name: "Tatva", element: tatva.split(" ")[0], score: s_t, weight: w_t },
            { name: "Rietumu", element: `Mēness ${ZODIAC_SIGNS[Math.floor(moonDeg / 30)]}`, score: s_m, weight: w_m },
            { name: "Briedums", element: waveScoreValue, score: s_w, weight: w_w }
        ]
    };
}

export function calculateStressResilience(mColor, tatva, dmP, dmE, moonDeg) {
    const s_c = safeGet(RESILIENCE_SCORES["Color_Resilience"], mColor, 5);
    const s_t = safeGet(RESILIENCE_SCORES["Tatva_Resilience"], tatva, 5);
    const s_b = safeGet(RESILIENCE_SCORES["Bazi_Resilience"], `${dmP} ${dmE}`, 5);
    const s_m = safeGet(RESILIENCE_SCORES["Moon_Resilience"], Math.floor(moonDeg / 30), 5);

    // SVARU KOREKCIJA: BaZi ×2→×3 (10 tipi, precīzākais kodola mērījums),
    //                  Krāsa ×3→×2 (tikai 4 vērtības — pārāk rupji priekš ×3),
    //                  Tatva ×2→×1 (pārklājas ar BaZi elementu),
    //                  Mēness ×1→×2 (emocionālā bāze ir tieši stresa funkcija)
    const w_c = 2, w_t = 1, w_b = 3, w_m = 2;
    const avg = (s_c*w_c + s_t*w_t + s_b*w_b + s_m*w_m) / (w_c + w_t + w_b + w_m);

    return {
        score: avg.toFixed(2),
        // min/max pārrēķināts: min=(3*2+4*1+3*3+3*2)/8=3.13, max=(10*2+9*1+10*3+10*2)/8=9.88
        pct: normalizePct(avg, 3.13, 9.88),
        details: [
            { name: "Kognitīvais filtrs", element: mColor.split(" ")[0], score: s_c, weight: w_c },
            { name: "Iekšējā stihija", element: tatva.split(" ")[0], score: s_t, weight: w_t },
            { name: "Dienas valdnieks", element: `${dmP} ${dmE}`, score: s_b, weight: w_b },
            { name: "Emocionālā bāze", element: `Mēness ${ZODIAC_SIGNS[Math.floor(moonDeg / 30)]}`, score: s_m, weight: w_m }
        ]
    };
}

export function calculateTeamwork(mSign, moonDeg, tatva, dmP, dmE, mColor) {
    const s_loj = safeGet(TEAMWORK_SCORES["Maya_Lojalitate"], mSign, 5);
    const s_dip = safeGet(TEAMWORK_SCORES["Moon_Diplomatija"], Math.floor(moonDeg / 30), 5);
    const s_emp = safeGet(TEAMWORK_SCORES["Tatva_Empatija"], tatva, 5);
    const s_asu = safeGet(TEAMWORK_SCORES["Bazi_Asums"], `${dmP} ${dmE}`, 5);
    const s_pra = safeGet(TEAMWORK_SCORES["Maya_Prats"], mColor, 5);

    const w_loj = 3, w_dip = 2, w_emp = 2, w_asu = 2, w_pra = 1;
    const avg = (s_loj*w_loj + s_dip*w_dip + s_emp*w_emp + s_asu*w_asu + s_pra*w_pra) / (w_loj + w_dip + w_emp + w_asu + w_pra);

    return {
        score: avg.toFixed(2),
        pct: normalizePct(avg, 2.60, 9.90),
        details: [
            { name: "Lojalitāte (Maiji)", element: mSign, score: s_loj, weight: w_loj },
            { name: "Diplomātija (Rietumi)", element: "Mēness", score: s_dip, weight: w_dip },
            { name: "Empātija (Tatva)", element: tatva.split(" ")[0], score: s_emp, weight: w_emp },
            { name: "Asums (BaZi)", element: `${dmP} ${dmE}`, score: s_asu, weight: w_asu },
            { name: "Intelekts (Maiji)", element: mColor.split(" ")[0], score: s_pra, weight: w_pra }
        ]
    };
}

// Cieņa (dignity) → planētas relāciju spēks 0..10 (cik labi tā funkcionē sociāli/emocionāli)
const DIGNITY_SCORE = {
    "Eksaltācija (Spēks)": 10, "Valdījums (Trijumfs)": 9,
    "Peregrīns (Neitrāls)": 5, "Trimda (Vājums)": 3, "Kritums (Grūtības)": 2
};

export function calculateMotivation(dLord, nLord, dmE, mColor, venusDignity = null, moonDignity = null, hasCompanionStar = false) {
    // Intelektuālā: 2 faktori (Dasha lords + Maiju krāsa), vidējais
    const intel = (safeGet(MOTIVATION_SCORES["Intellectual"], dLord, 5) + safeGet(MOTIVATION_SCORES["Intellectual"], mColor, 5)) / 2;
    // KOREKCIJA: noņemta konstante +8 — tieša nLord vērtība ar paplašinātu normalizācijas diapazonu
    const stat  = safeGet(MOTIVATION_SCORES["Status"], nLord, 5);
    // Sociālā: DATU-DRIVEN laika-robusts proxy — Venera (galvenā attiecību planēta, ~50%),
    // Mēness (emocionālā saite, ~30%), BaZi pavadoņu zvaigzne Friend/Rob_Wealth (~20%).
    // Īstie sociālie rādītāji (7./11. māja) bez dzimšanas laika nav pieejami → proxy.
    // Bez ievada (sparklines / vēsturiskais ceļš) atkrīt uz iepriekšējo 7.5.
    let soc = 7.5;
    if (venusDignity || moonDignity) {
        const vd = DIGNITY_SCORE[venusDignity] ?? 5;
        const md = DIGNITY_SCORE[moonDignity] ?? 5;
        soc = vd * 0.5 + md * 0.3 + (hasCompanionStar ? 8 : 4) * 0.2;
    }
    // KOREKCIJA: noņemta konstante +6 — tieša dmE vērtība
    const mat   = safeGet(MOTIVATION_SCORES["Material"], dmE, 5);

    return {
        "Intelektuālā": normalizePct(intel, 4.0, 9.5),
        "Statusa":      normalizePct(stat,  3, 10),     // bija (5.5, 9.0) ar +8 — tagad tiešais diapazons
        "Sociālā":      normalizePct(soc,   0, 10),     // tagad data-driven (Venera+Mēness+pavadonis), ne konstante
        "Materiālā":    normalizePct(mat,   4, 8),      // bija (5.0, 7.0) ar +6 — tagad tiešais diapazons
    };
}

export function calculatePerformanceStyle(dmP, dmE, mColor, spiritDeg, dashaLord) {
    const s_b = safeGet(PERFORMANCE_SCORES["Bazi_Finish"], `${dmP} ${dmE}`, 5);
    const s_m = safeGet(PERFORMANCE_SCORES["Maya_Finish"], mColor, 5);
    const s_sign = ZODIAC_SIGNS[Math.floor(spiritDeg / 30)] || "Nezināms";
    const s_s = safeGet(PERFORMANCE_SCORES["Spirit_Sign"], s_sign, 5);
    const s_d = safeGet(PERFORMANCE_SCORES["Vedic_Cycle"], dashaLord, 5);

    // SVARU KOREKCIJA: Maiju krāsa ×3→×2 (tikai 4 vērtības — pārāk rupji priekš ×3),
    //                  Vēdiskais cikls ×2→×1 (temporāls Dasha lords)
    const w_b = 3, w_m = 2, w_s = 2, w_d = 1;
    const avg = (s_b*w_b + s_m*w_m + s_s*w_s + s_d*w_d) / (w_b + w_m + w_s + w_d);

    return {
        score: avg.toFixed(2),
        // min/max: min=(5*3+4*2+2*2+4*1)/8=3.88, max=(9*3+9*2+10*2+9*1)/8=9.25
        pct: normalizePct(avg, 3.88, 9.25),
        details: [
            { name: "Iekšējais kodols (BaZi)", element: `${dmP} ${dmE}`, score: s_b, weight: w_b },
            { name: "Loģiskā struktūra (Maiji)", element: mColor.split(" ")[0], score: s_m, weight: w_m },
            { name: "Darbības dzinulis (Spirit)", element: s_sign, score: s_s, weight: w_s },
            { name: "Dzīves periods (Vēdiskā)", element: dashaLord, score: s_d, weight: w_d }
        ]
    };
}

export function calculatePotentialRisks(mSign, moonDeg, dmP, dmE, tatva, nLord, dLord) {
    const mem = safeGet(RISK_SCORES["Elephant_Memory"], mSign, 5);

    const moonSign = ZODIAC_SIGNS[Math.floor(moonDeg / 30)] || "Svari";
    const par = safeGet(RISK_SCORES["Paralysis"], moonSign, 5);

    const prs = safeGet(RISK_SCORES["Pressure"],         `${dmP} ${dmE}`, 5);
    const fra = safeGet(RISK_SCORES["Fragility"],         tatva,          5);
    const ego = safeGet(RISK_SCORES["Ego"],               nLord,          5);

    // SVARU KOREKCIJA: Aizvainojums ×3→×2 (interpretīvs, ne tiešs mērījums),
    //                  Ego ×1→×2 (ego ir tikpat nozīmīgs riska faktors kā pārējie)
    const w_mem = 2, w_par = 2, w_prs = 2, w_fra = 2, w_ego = 2;
    const avg = (mem*w_mem + par*w_par + prs*w_prs + fra*w_fra + ego*w_ego) / (w_mem + w_par + w_prs + w_fra + w_ego);

    return {
        score: avg.toFixed(2),
        // min/max: min=(3*2+2*2+4*2+3*2+3*2)/10=3.0, max=(9*2+9*2+9*2+7*2+9*2)/10=8.6
        pct: normalizePct(avg, 3.0, 8.6),
        details: [
            { name: "Ilgtermiņa aizvainojums", element: mSign,          score: mem, weight: w_mem },
            { name: "Analīzes paralīze",        element: moonSign,       score: par, weight: w_par },
            { name: "Perfekcionisma spiediens", element: `${dmP} ${dmE}`, score: prs, weight: w_prs },
            { name: "Emocionāls trauslums",     element: tatva,          score: fra, weight: w_fra },
            { name: "Iedomība / Ego",           element: nLord,          score: ego, weight: w_ego }
        ]
    };
}
