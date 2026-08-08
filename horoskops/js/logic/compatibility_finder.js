// horoskops/js/logic/compatibility_finder.js
import { getJulianDay, getLahiriAyanamsa } from '../core_astro.js?v=9';
import { swisseph } from '../../swisseph/dist/swisseph-browser.js?v=2.4';
import { getNakshatraData } from '../vedic_kp.js?v=10';
import { calculateCompatibility } from './compatibility.js?v=15';

const NAK_W = 360 / 27;

// Mēness siderālais garums — IDENTISKS getAstronomicalData (tropiskais − Lahiri ajanamsa).
function moonSidereal(jd, ayanamsa) {
    const trop = swisseph.calculatePosition(jd, 1, 260).longitude;
    let sid = (trop - ayanamsa) % 360;
    if (sid < 0) sid += 360;
    return sid;
}

// parihara: jāatbilst lietotāja izvēlētajai metodei (Stingrā/Tradicionālā).
//
// SADERĪBA AR GALVENO ANALĪZI: pie "nezinu laiku" partneris saņem 24h LOKĀLĀ laika vidējo
// (calculateCompatibility + timeSweep). Finderis dara TO PAŠU katram datumam — vairs ne dienas
// maksimumu. Atziņa: computeTimeSweep Mēness sadalījums ir TIKAI datuma UTC dienas sadalījums
// (localToUtc uzspiež UTC laiku uz to pašu datumu, tāpēc laika josla histogrammu nemaina).
//
// Tā kā expectKuta ir LINEĀRS pār partnera svariem:
//   datuma 24h vidējais  =  Σ_b  dist_date(b) · f[b],
// kur f[b] = gaidāmais Ashta Kuta kopskaits pret partneri Mēness pozīcijā b=(ni,ri), jau ņemot
// vērā p1 24h vidējošanu (jointStates(p1)). f[b] = calculateCompatibility(p1, viena-stāvokļa-p2-b).
// Tas dod TIEŠI to pašu skaitli, ko galvenā analīze, bet bez 5113 pilniem aprēķiniem.
export async function findCompatibleDates(p1, gender1, targetGender, parihara = false) {
    if (!p1 || !p1.birth_info || !p1.birth_info.date) return { excellent: [], medium: [] };

    const birthDateStr = p1.birth_info.date;
    const birthTimeMs = new Date(birthDateStr + "T12:00:00Z").getTime();
    const startMs = birthTimeMs - (7 * 365.25 * 86400000);
    const endMs   = birthTimeMs + (7 * 365.25 * 86400000);

    // ── Uzmeklēšanas tabula f[ni*12+ri] (324 ieraksti) — vienreiz uz finder izsaukumu. ──
    const f = new Float64Array(27 * 12);
    for (let ni = 0; ni < 27; ni++) {
        for (let ri = 0; ri < 12; ri++) {
            const p2 = { birth_info: { isTimeUnknown: false },
                         vedic: { nakshatra: { index: ni, rashi_index: ri } } };
            const comp = calculateCompatibility(p1, p2, gender1, targetGender, parihara, { scoreOnly: true });
            f[ni * 12 + ri] = comp ? comp.kutaScore : 0;
        }
    }

    // ── Katram datumam: Mēness 24h UTC sadalījums → 24h vidējais caur f. ──
    const N = 96;                          // paraugi pār datuma UTC dienu (ik 15 min)
    const all = [];
    let iter = 0;
    for (let tMs = startMs; tMs <= endMs; tMs += 86400000) {
        if ((++iter % 60) === 0) await new Promise(r => setTimeout(r, 0));
        const dateStr = new Date(tMs).toISOString().split('T')[0];

        let jd0, ayan;
        try {
            jd0  = getJulianDay(dateStr + "T00:00:00Z");   // datuma sākums; JD ir lineārs laikā
            ayan = getLahiriAyanamsa(getJulianDay(dateStr + "T12:00:00Z")); // ~konstanta dienas laikā
        } catch (e) { continue; }

        const counts = new Float64Array(27 * 12);
        let n = 0;
        for (let s = 0; s < N; s++) {
            const jd = jd0 + s / N;                        // +s/N dienas (ik 1440/N min)
            let sid;
            try { sid = moonSidereal(jd, ayan); } catch (e) { continue; }
            const ni = Math.min(26, Math.floor(sid / NAK_W));
            const ri = Math.min(11, Math.floor(sid / 30));
            counts[ni * 12 + ri]++;
            n++;
        }
        if (!n) continue;

        // 24h vidējais + modālais (visticamākais) bins → nakšatras nosaukums tabulai
        let score = 0, modalIdx = 0, modalC = -1;
        for (let i = 0; i < counts.length; i++) {
            if (counts[i] > 0) {
                score += (counts[i] / n) * f[i];
                if (counts[i] > modalC) { modalC = counts[i]; modalIdx = i; }
            }
        }

        const nakName = getNakshatraData((Math.floor(modalIdx / 12) + 0.5) * NAK_W).nakshatra;
        all.push({ date: dateStr, score, rounded: Math.round(score), nakshatra: nakName });
    }

    // Visi datumi hronoloģiski (vecums aug); joslas saskaņotas ar Ashta Kuta verdiktu (>28 / 18–28).
    all.sort((a, b) => a.date.localeCompare(b.date));
    const excellent = all.filter(d => d.rounded > 28);
    const medium    = all.filter(d => d.rounded >= 18 && d.rounded <= 28);
    return { excellent, medium };
}
