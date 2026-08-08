// ── SINTEZĒTĀ MASKA (STIHIJU SVĒRTAIS BALSOJUMS) ─────────────────────────────
// Kad dzimšanas laiks nav zināms, Ascendents pa ZĪMĒM ir nenosakāms (24h laikā
// iziet cauri 4–5 līdzvērtīgām zīmēm, katra ~13%). To godīgi nevar salocīt vienā.
//
// BET pa STIHIJĀM (Uguns/Zeme/Gaiss/Ūdens) tā pati informācija lasāma stabilāk:
// vairākas no-laika-neatkarīgas sistēmas BALSO par vienu dominējošo stihiju, un
// tās kopējā daļa godīgi sanāk krietni virs 13%. Tas ir "vidējais ticamākais
// rezultāts" — viena atbilde ("Ūdens maska"), godīgs augsts %.
//
// Mērvienība šeit ir STIHIJA, NE zīme — tieši tāpēc % drīkst būt augsts.
// Nekādi izdomāti "+5% bonusi" netiek pievienoti: katrs skaitlis ir reāla
// varbūtību summa, kas dekomponējas atpakaļ sastāvdaļās (sk. signals[]).

// Stihiju un modalitāšu vārdnīcas (zīmju indeksi atbilst dist_bar.SIGN_NAMES).
// 0 Auns,1 Vērsis,2 Dvīņi,3 Vēzis,4 Lauva,5 Jaunava,6 Svari,7 Skorpions,
// 8 Strēlnieks,9 Mežāzis,10 Ūdensvīrs,11 Zivis
const SIGN_ELEMENT = ["Uguns","Zeme","Gaiss","Ūdens","Uguns","Zeme","Gaiss","Ūdens","Uguns","Zeme","Gaiss","Ūdens"];
const SIGN_MODALITY = ["Kardinālā","Fiksētā","Mainīgā","Kardinālā","Fiksētā","Mainīgā","Kardinālā","Fiksētā","Mainīgā","Kardinālā","Fiksētā","Mainīgā"];

export const ELEMENTS = ["Uguns", "Zeme", "Gaiss", "Ūdens"];
export const MODALITIES = ["Kardinālā", "Fiksētā", "Mainīgā"];

export const ELEMENT_META = {
    "Uguns": { color: "#dc2626", icon: "🔥",
        mask: "dedzīgs, tiešs un pārliecināts — pirmais iespaids ir enerģisks un vadošs",
        persona: {
            demo: "enerģisku, tiešu un pārliecinātu klātbūtni — dabiski uzņemas vadību un aizņem telpu",
            defense: "Aktīva rīcība un drosme sargā no bailēm izrādīties vājam, pasīvam vai atkarīgam.",
            risk: "Ja identitāte pilnībā saaug ar “vienmēr stiprā un vadošā” lomu, apstāšanās vai paļaušanās uz citiem tiek izjusta kā sakāve.",
        } },
    "Zeme":  { color: "#b45309", icon: "🪨",
        mask: "nosvērts, praktisks un stabils — pirmais iespaids ir mierīgs, lietišķs un uzticams",
        persona: {
            demo: "nosvērtu, praktisku un stabilu klātbūtni — mierīgu, lietišķu un uzticamu pirmo iespaidu",
            defense: "Kontrole, kārtība un praktiskums sargā no nedrošības, haosa un neparedzamības sajūtas.",
            risk: "Ja identitāte pilnībā saaug ar “noderīgā un stabilā” lomu, spontanitāte un emocijas tiek noliegtas kā drauds kārtībai.",
        } },
    "Gaiss": { color: "#0891b2", icon: "💨",
        mask: "komunikabls, intelektuāls un sociāls — pirmais iespaids ir atvērts, runīgs un zinātkārs",
        persona: {
            demo: "komunikablu, intelektuālu un sociālu klātbūtni — atvērtu, runīgu un zinātkāru pirmo iespaidu",
            defense: "Vārdi, humors un distancēta analīze sargā no emocionālas pārņemtības un tuvības spiediena.",
            risk: "Ja identitāte pilnībā saaug ar “gudrā un viegli pieejamā” lomu, dziļas jūtas tiek racionalizētas un no tām izvairās.",
        } },
    "Ūdens": { color: "#2563eb", icon: "🌊",
        mask: "jūtīgs, intuitīvs un aizsargājošs — pirmais iespaids ir empātisks, dziļš un nedaudz rezervēts",
        persona: {
            demo: "jūtīgu, intuitīvu un aizsargājošu klātbūtni — empātisku, dziļu un nedaudz rezervētu pirmo iespaidu",
            defense: "Rezervētība un emocionālā “antena” sargā no ievainojamības, atraidījuma un pārslodzes.",
            risk: "Ja identitāte pilnībā saaug ar “jūtīgā un rūpējošā” lomu, personīgās robežas un pašu vajadzības tiek upurētas citu labā.",
        } },
};

export const MODALITY_META = {
    "Kardinālā": { label: "iniciators", desc: "sāk lietas, dod impulsu, dabiski uzņemas vadību" },
    "Fiksētā":   { label: "noturīgs",  desc: "stabils, neatlaidīgs, tur pozīciju līdz galam" },
    "Mainīgā":   { label: "elastīgs",  desc: "pielāgojas, daudzpusīgs, viegli maina virzienu" },
};

// Junga-PERSONA stila maskas teksts no stihijas (demo + aizsardzība + risks).
// Dalīts avots: t3 panelis UN horoskops.js (Vēdu/Rietumu socialMask override pie
// nezināma laika), lai visur ir IDENTISKS teksts un nav pretrunu.
export function elementPersonaText(element, { note = null } = {}) {
    const ep = ELEMENT_META[element]?.persona;
    if (!ep) return "";
    const lines = [
        `Ārēji demonstrē ${ep.demo}.`,
        `<span style="color:#94a3b8; font-size:0.85rem;">Aizsardzības mehānisms: </span>${ep.defense}`,
        `<span style="color:#f87171; font-size:0.85rem;">Junga risks (pāridentifikācija): </span>${ep.risk}`,
    ];
    if (note) lines.push(`<span style="color:#94a3b8; font-size:0.8rem; font-style:italic;">${note}</span>`);
    return lines.join("<br>");
}

// BaZi 5 elementu → Rietumu 4 stihiju (aptuvens, lossy kartējums).
// Tāpēc BaZi balsam ir VISMAZĀKAIS svars — pat ja kartējums diskutabls, ietekme maza.
const BAZI_TO_ELEMENT = {
    "Uguns": "Uguns",   // Fire → Uguns
    "Ūdens": "Ūdens",   // Water → Ūdens
    "Zeme":  "Zeme",    // Earth → Zeme
    "Koks":  "Gaiss",   // Wood → Gaiss (augšana, vējš, elastība)
    "Metāls":"Zeme",    // Metal → Zeme (struktūra, materialitāte)
};

// Agregē zīmju sadalījumu { signIdx: frac } → stihiju sadalījumu { element: frac }.
function signDistToElements(signDist) {
    const out = { "Uguns": 0, "Zeme": 0, "Gaiss": 0, "Ūdens": 0 };
    if (!signDist) return null;
    let total = 0;
    for (const [idx, frac] of Object.entries(signDist)) {
        const el = SIGN_ELEMENT[+idx];
        if (el) { out[el] += frac; total += frac; }
    }
    if (total <= 0) return null;
    for (const k of ELEMENTS) out[k] /= total; // re-normalizē (drošinātājs)
    return out;
}

function signDistToModalities(signDist) {
    const out = { "Kardinālā": 0, "Fiksētā": 0, "Mainīgā": 0 };
    if (!signDist) return null;
    let total = 0;
    for (const [idx, frac] of Object.entries(signDist)) {
        const m = SIGN_MODALITY[+idx];
        if (m) { out[m] += frac; total += frac; }
    }
    if (total <= 0) return null;
    for (const k of MODALITIES) out[k] /= total;
    return out;
}

const topKey = (dist) => Object.entries(dist).sort((a, b) => b[1] - a[1])[0]?.[0] ?? null;

/**
 * Sintezē dominējošo maskas stihiju no vairākām no-laika-neatkarīgām sistēmām.
 * @param {Object} profile  — gaida profile.timeSweep, profile.bazi, profile.birth_info
 * @returns {{applicable, elementDist, modalityDist, topElement, topElementPct,
 *            topModality, topModalityPct, signals, agreeCount, totalSignals,
 *            agreementFrac, confidencePct}}
 */
export function computeMaskSynthesis(profile = {}) {
    const sweep = profile?.timeSweep || null;
    const timeUnknown = !!profile?.birth_info?.isTimeUnknown;
    // Sintēze ir jēdzīga tikai tad, kad laiks NAV zināms (citādi Ascendents = 1 zīme).
    if (!sweep || !timeUnknown) return { applicable: false };

    const ascSignDist  = sweep.vedic?.ascSignDist;
    const moonSignDist = sweep.vedic?.planets?.Meness?.signDist;
    const sunSignDist  = sweep.vedic?.planets?.Saule?.signDist;
    const baziElement  = profile?.bazi?.Daymaster?.element;

    // Katrs signāls: { key, label, weight, elementDist }. Svars = relevance + uzticamība.
    const signals = [];
    const ascEl = signDistToElements(ascSignDist);
    if (ascEl) signals.push({ key: "asc", label: "Ascendents (24h)", weight: 3.5, elementDist: ascEl,
        note: "tieši maskas zīme, lasīta pa stihijām — dominējošais signāls" });
    const moonEl = signDistToElements(moonSignDist);
    if (moonEl) signals.push({ key: "moon", label: "Mēness zīme (Chandra)", weight: 1.5, elementDist: moonEl,
        note: "emocionālā daba — klasiskā aizvietotājmaska nezināmam laikam" });
    const sunEl = signDistToElements(sunSignDist);
    if (sunEl) signals.push({ key: "sun", label: "Saules zīme (Surya)", weight: 1.0, elementDist: sunEl,
        note: "pamata vitalitāte un ārējais starojums" });
    if (baziElement && BAZI_TO_ELEMENT[baziElement]) {
        const be = { "Uguns": 0, "Zeme": 0, "Gaiss": 0, "Ūdens": 0 };
        be[BAZI_TO_ELEMENT[baziElement]] = 1;
        signals.push({ key: "bazi", label: "BaZi Dienas stumbrs", weight: 0.5, elementDist: be,
            note: `${baziElement} → ${BAZI_TO_ELEMENT[baziElement]} (aptuvens 5→4 kartējums)` });
    }

    if (!signals.length) return { applicable: false };

    // Svērtā stihiju summa (galvenais skaitlis — reāla varbūtību summa, ne bonuss).
    const elementDist = { "Uguns": 0, "Zeme": 0, "Gaiss": 0, "Ūdens": 0 };
    let wsum = 0;
    for (const s of signals) {
        for (const k of ELEMENTS) elementDist[k] += (s.elementDist[k] || 0) * s.weight;
        wsum += s.weight;
    }
    for (const k of ELEMENTS) elementDist[k] /= wsum;

    const topElement = topKey(elementDist);
    const topElementPct = Math.round(elementDist[topElement] * 100);

    // Konverģence: cik daudz (svērti) signālu pats par sevi norāda uz to pašu stihiju.
    let agreeW = 0, agreeCount = 0;
    for (const s of signals) {
        if (topKey(s.elementDist) === topElement) { agreeW += s.weight; agreeCount++; }
    }
    const agreementFrac = agreeW / wsum;

    // Modalitāte — tikai no Ascendanta sweep (modalitāte ir zīmes, ne planētas, īpašība).
    const modalityDist = signDistToModalities(ascSignDist) || { "Kardinālā": 0, "Fiksētā": 0, "Mainīgā": 0 };
    const topModality = topKey(modalityDist);
    const topModalityPct = Math.round((modalityDist[topModality] || 0) * 100);

    // Godīgs ticamības skaitlis = TĪRĀ svērtā stihijas daļa, BEZ jebkāda "konverģences
    // pūtuma" (apzināti neatkārtojam to, ko kritizējām pie "+5% bonusiem"). Konverģenci
    // rādām atsevišķi (agreeCount + zvaigznes), neuzpūšot pašu skaitli.
    const confidencePct = topElementPct;

    return {
        applicable: true,
        elementDist, modalityDist,
        topElement, topElementPct,
        topModality, topModalityPct,
        signals,
        agreeCount, totalSignals: signals.length,
        agreementFrac, confidencePct,
    };
}

// ── VISPĀRĪGĀ ARHETIPA SINTĒZE (Anima/Patība/Rahu/Ketu) ──────────────────────
// Atšķirībā no maskas, šiem arhetipiem ZĪME/stihija parasti jau ir nosakāma
// (datuma-fiksēta vai gandrīz) — nenoteikta ir tikai MĀJA. Un māju NEVAR
// krusteniski validēt (visu sistēmu mājas izriet no tā paša nezināmā Ascendenta).
// Tāpēc:
//   • KVALITĀTE (stihija): agregē zīmju sadalījumu(-us) → dominējošā stihija + %.
//     determinate=true, ja % ≥ 90 (t.i. zīme praktiski viena → laiks neietekmē).
//   • JOMA (māja): rāda māja-līderi TIKAI, ja tāds tiešām izceļas (decisive);
//     citādi godīgi atzīstam, ka bez laika joma nav nosakāma.
// Māju grupas (joma TIPS) — māju "modalitāte". Kad atsevišķa māja nav izšķirama,
// grupa dažkārt koncentrējas (atkarīgs no Placidus māju izmēriem konkrētā platumā).
const HOUSE_GROUP = {
    1: "Stūra", 4: "Stūra", 7: "Stūra", 10: "Stūra",
    2: "Sekojošās", 5: "Sekojošās", 8: "Sekojošās", 11: "Sekojošās",
    3: "Krītošās", 6: "Krītošās", 9: "Krītošās", 12: "Krītošās",
};
export const HOUSE_GROUP_META = {
    "Stūra":     { desc: "aktīvās, redzamās dzīves jomas — rīcība, identitāte, attiecības, karjera" },
    "Sekojošās": { desc: "resursu un stabilitātes jomas — vērtības, radīšana, kopīgie resursi, mērķi" },
    "Krītošās":  { desc: "mācīšanās un pielāgošanās jomas — saziņa, ikdiena, jēga, zemapziņa" },
};

export function computeArchetypeSynthesis({ signDists = [], houseDist = null } = {}) {
    // ── Kvalitāte (stihija) ──
    const elementDist = { "Uguns": 0, "Zeme": 0, "Gaiss": 0, "Ūdens": 0 };
    let wsum = 0;
    const signals = [];
    for (const sd of signDists) {
        const el = signDistToElements(sd.dist);
        if (!el) continue;
        for (const k of ELEMENTS) elementDist[k] += el[k] * sd.weight;
        wsum += sd.weight;
        signals.push({ label: sd.label, weight: sd.weight, topElement: topKey(el),
            topPct: Math.round(Math.max(...Object.values(el)) * 100) });
    }
    let quality = null;
    if (wsum > 0) {
        for (const k of ELEMENTS) elementDist[k] /= wsum;
        const topElement = topKey(elementDist);
        const topElementPct = Math.round(elementDist[topElement] * 100);
        quality = { elementDist, topElement, topElementPct, determinate: topElementPct >= 90, signals };
    }

    // ── Joma (māja) ──
    let domain = null;
    if (houseDist && Object.keys(houseDist).length) {
        const rows = Object.entries(houseDist)
            .map(([k, v]) => ({ house: +k + 1, frac: v }))
            .sort((a, b) => b.frac - a.frac);
        const leader = rows[0];
        const second = rows[1]?.frac ?? 0;
        // decisive: līderis ≥20% UN vismaz 5 procentpunkti virs otra (citādi — vienmērīgs)
        const decisive = leader.frac >= 0.20 && (leader.frac - second) >= 0.05;

        // Māju grupas (joma tips) — fallback, kad atsevišķa māja nav izšķirama
        const groupDist = { "Stūra": 0, "Sekojošās": 0, "Krītošās": 0 };
        for (const [k, v] of Object.entries(houseDist)) {
            const g = HOUSE_GROUP[+k + 1];
            if (g) groupDist[g] += v;
        }
        const grows = Object.entries(groupDist).sort((a, b) => b[1] - a[1]);
        const gLead = grows[0], gSecond = grows[1]?.[1] ?? 0;
        // 3 grupas → bāze 33%; izšķirams, ja ≥45% UN ≥5pp virs otras
        const groupDecisive = gLead[1] >= 0.45 && (gLead[1] - gSecond) >= 0.05;

        domain = {
            topHouse: leader.house, topHousePct: Math.round(leader.frac * 100), decisive, rows,
            groupDist, topGroup: gLead[0], topGroupPct: Math.round(gLead[1] * 100), groupDecisive,
        };
    }

    return { applicable: !!(quality || domain), quality, domain };
}
