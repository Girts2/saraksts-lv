// ── 24h LOKĀLĀ LAIKA SADALĪJUMA DZINĒJS ──────────────────────────────────────
// Kad dzimšanas laiks NAV zināms, nav pareizi pieņemt vienu laiku (pusdienlaiku):
// Ascendants un visu planētu mājas 24h laikā iziet cauri visām 12 zīmēm/mājām.
// Šis modulis izlaiž 24h LOKĀLĀ laika (vienmērīga izlase → lokālā-laika svērts
// sadalījums) un atgriež varbūtību sadalījumus katram dienas-mainīgam lielumam.
//
// Datuma-fiksētie lielumi (Saules zīme, lēno planētu zīmes) sanāk kā viens punkts
// (sadalījums ≈ {sign: 1.0}) — tas ir korekti un gaidīts.

import { getJulianDay, getAstronomicalData } from "../core_astro.js?v=9";
import { getNakshatraData } from "../vedic_kp.js?v=10";
import { BRANCHES } from "../bazi.js?v=12";
import { localToUtc } from "../timezone/local_to_utc.js?v=1";

// Planētas, kurām sekojam zīmei + mājai (atbilst astroData atslēgām)
const SIDEREAL_BODIES = ["Saule", "Meness", "Marss", "Merkurs", "Jupiters", "Venera", "Saturns", "Rahu", "Ketu"];
const TROPICAL_BODIES = ["Saule", "Meness", "Merkurs", "Venera", "Marss", "Jupiters", "Saturns", "Urans", "Neptuns", "Plutons", "Rahu", "Ketu"];

const newCounts = (n) => new Array(n).fill(0);
const norm = (counts, total) => {
    const out = {};
    for (let i = 0; i < counts.length; i++) {
        if (counts[i] > 0) out[i] = counts[i] / total;
    }
    return out;
};
// Reto (map) skaitļu normalizācija — piem. joint (nakšatra×zīme) atslēga → svars
const normSparse = (map, total) => {
    const out = {};
    for (const k in map) if (map[k] > 0) out[k] = map[k] / total;
    return out;
};
const argmax = (counts) => {
    let best = 0, bi = 0;
    for (let i = 0; i < counts.length; i++) if (counts[i] > best) { best = counts[i]; bi = i; }
    return bi;
};

// Placidus māja no kuspijām (replicēta no western.getHouseFromCusps, lai izvairītos
// no liekām importu atkarībām; tā pati loģika). cusps ir 1-INDEKSĒTS 13-elementu masīvs
// (cusps[0] neizmantots, cusps[1..12] = māju sākumi) — sk. western.getHouseFromCusps.
function houseFromCusps(deg, cusps) {
    if (!cusps || cusps.length < 13) return null;
    for (let i = 1; i <= 12; i++) {
        const start = cusps[i], end = cusps[i === 12 ? 1 : i + 1];
        if (start < end) { if (deg >= start && deg < end) return i; }
        else { if (deg >= start || deg < end) return i; }
    }
    return 12;
}

/**
 * Aprēķina 24h lokālā laika sadalījumu.
 * @param {Object} p
 * @param {string} p.dateStr   - "YYYY-MM-DD"
 * @param {number} p.lat
 * @param {number} p.lon
 * @param {string} [p.timezone] - IANA zona (moment-timezone)
 * @param {number} [p.samples=1440] - cik paraugu pār 24h (1440 = ik minūti)
 * @returns {Object} sweep
 */
export function computeTimeSweep({ dateStr, lat, lon, timezone, samples = 1440 }) {
    // Akumulatori
    const vedicAscSign = newCounts(12);
    const tropAscSign  = newCounts(12);
    const vedic = {}; // name -> { sign:[12], house:[12], nak?:[27] }
    const trop  = {}; // name -> { sign:[12], house:[12] }
    SIDEREAL_BODIES.forEach(n => { vedic[n] = { sign: newCounts(12), house: newCounts(12) }; });
    vedic["Meness"].nak = newCounts(27);
    vedic["Meness"].nakSign = {};   // patiess joint: (nakIdx*12 + zīme) → skaits
    TROPICAL_BODIES.forEach(n => { trop[n] = { sign: newCounts(12), house: newCounts(12) }; });
    const baziHour = newCounts(12);

    const stepMin = 1440 / samples;
    let counted = 0;

    for (let s = 0; s < samples; s++) {
        const totalMin = Math.round(s * stepMin) % 1440;
        const hh = String(Math.floor(totalMin / 60)).padStart(2, "0");
        const mm = String(totalMin % 60).padStart(2, "0");
        const { utcStr, utcDateStr } = localToUtc(dateStr, `${hh}:${mm}`, lon, timezone);
        const dateTimeStr = `${utcDateStr || dateStr}T${utcStr}:00Z`;

        let astro;
        try {
            const jd = getJulianDay(dateTimeStr);
            astro = getAstronomicalData(jd, lat, lon);
        } catch (e) {
            continue; // izlaižam bojātu paraugu
        }
        if (!astro || !astro.sidereal) continue;

        // Ketu siderālajā (tāpat kā horoskops.js dara pēc getAstronomicalData)
        if (astro.sidereal["Rahu"] !== undefined && astro.sidereal["Ketu"] === undefined) {
            astro.sidereal["Ketu"] = (astro.sidereal["Rahu"] + 180) % 360;
        }

        // ── Vēdu (siderālā): veselo-zīmju mājas no siderālā Ascendanta ──
        const sidAsc = astro.sidereal["Ascendant"];
        if (sidAsc !== undefined) {
            const ascSignIdx = Math.floor(sidAsc / 30) % 12;
            vedicAscSign[ascSignIdx]++;
            for (const n of SIDEREAL_BODIES) {
                const deg = astro.sidereal[n];
                if (deg === undefined) continue;
                const sign = Math.floor(deg / 30) % 12;
                const house = ((sign - ascSignIdx + 12) % 12) + 1;
                vedic[n].sign[sign]++;
                vedic[n].house[house - 1]++;
            }
            // Mēness nakšatra
            const moonDeg = astro.sidereal["Meness"];
            if (moonDeg !== undefined) {
                const nak = getNakshatraData(moonDeg);
                if (nak && nak.index >= 0 && nak.index < 27) {
                    vedic["Meness"].nak[nak.index]++;
                    // Patiess joint (nakšatra × Mēness zīme) no TĀS PAŠAS pozīcijas — lai Ashta Kuta
                    // nezināmam laikam izmantotu reālo kopskaitli, ne neatkarīgu marginālu reizinājumu.
                    const moonSign = Math.floor(moonDeg / 30) % 12;
                    const key = nak.index * 12 + moonSign;
                    vedic["Meness"].nakSign[key] = (vedic["Meness"].nakSign[key] || 0) + 1;
                }
            }
        }

        // ── Rietumu (tropiskā): Placidus mājas no kuspijām ──
        const tropAsc = astro.tropical?.["Ascendant"];
        if (tropAsc !== undefined) tropAscSign[Math.floor(tropAsc / 30) % 12]++;
        const cusps = astro.houses_tropical;
        if (astro.tropical) {
            for (const n of TROPICAL_BODIES) {
                const deg = astro.tropical[n];
                if (deg === undefined) continue;
                const sign = Math.floor(deg / 30) % 12;
                trop[n].sign[sign]++;
                const h = houseFromCusps(deg, cusps);
                if (h) trop[n].house[h - 1]++;
            }
        }

        // ── Bazi stundas zars (no LMT stundas) ──
        const utcDate = new Date(dateTimeStr);
        const lmtMs = utcDate.getTime() + (lon / 15) * 3600 * 1000;
        const lmtHour = new Date(lmtMs).getUTCHours();
        // +1: BRANCHES masīvs sākas ar Hai, bet formula dod 0=Zi secīgu indeksu (sk. bazi.js).
        const branchIdx = (Math.floor((lmtHour + 1) / 2) + 1) % 12;
        baziHour[branchIdx]++;

        counted++;
    }

    const total = counted || 1;

    // Normalizē + modālās vērtības
    const packBodies = (acc, withNak) => {
        const out = {};
        for (const [name, c] of Object.entries(acc)) {
            out[name] = {
                signDist: norm(c.sign, total),
                houseDist: norm(c.house, total),
                modalSign: argmax(c.sign),
                modalHouse: argmax(c.house) + 1,
            };
            if (withNak && c.nak) {
                out[name].nakDist = norm(c.nak, total);
                out[name].modalNak = argmax(c.nak);
            }
            if (withNak && c.nakSign) {
                out[name].nakSignDist = normSparse(c.nakSign, total);
            }
        }
        return out;
    };

    return {
        samples: total,
        local: true,
        vedic: {
            ascSignDist: norm(vedicAscSign, total),
            modalAscSign: argmax(vedicAscSign),
            planets: packBodies(vedic, true),
        },
        western: {
            ascSignDist: norm(tropAscSign, total),
            modalAscSign: argmax(tropAscSign),
            planets: packBodies(trop, false),
        },
        baziHour: {
            branchDist: norm(baziHour, total),
            modalBranch: BRANCHES[argmax(baziHour)],
        },
    };
}
