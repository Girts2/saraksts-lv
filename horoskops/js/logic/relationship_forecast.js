// horoskops/js/logic/relationship_forecast.js
// ─────────────────────────────────────────────────────────────────────────────
// KOPDZĪVES SIMULĀCIJA LAIKĀ — attiecību prognoze 0–25 gadiem uz priekšu.
//
// Metode (godīga): aprēķina abu KOMPOZĪTKARTI (attiecības kā veselumu) un tad ar
// īstu efemerīdu (swisseph) projicē, KAD lēnās planētas (Saturns, Plūtons, Urāns,
// Neptūns, Jupiters) nākotnē veidos aspektus ar šo kompozītu. Tā ir klasiskā
// Rietumu attiecību laika noteikšana:
//   • Saturns → briduma/apņemšanās testi (~ik 7 gadi cikla ceturkšņos)
//   • Plūtons → varas cīņas, dziļa transformācija vai sabrukums
//   • Urāns  → brīvības vajadzība, pēkšņas pārmaiņas, šķiršanās risks
//   • Neptūns→ ilūziju izgaišana, migla, idealizācija pret realitāti
//   • Jupiters→ izaugsmes/prieka logi (kāzas, bērni, kopdzīves sākums)
//
// SVARĪGI: tā NAV deterministiska nākotnes pareģošana, bet TĒMU un STRESA LOGU
// laika karte — kad attiecības tiks pārbaudītas un kad tām būs vējš mugurā.
// ─────────────────────────────────────────────────────────────────────────────

import { getJulianDay } from '../core_astro.js?v=9';
import { swisseph } from '../../swisseph/dist/swisseph-browser.js?v=2.4';
import { aspectFalloff } from './aspect_utils.js?v=1';

const PID = { Saule: 0, Meness: 1, Venera: 3, Marss: 4, Jupiters: 5, Saturns: 6, Urans: 7, Neptuns: 8, Plutons: 9 };
const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

function lon(p, name) {
    const v = p?.western?.planets?.[name]?.longitude;
    return typeof v === 'number' ? ((v % 360) + 360) % 360 : null;
}
function midpoint(a, b) {
    if (a == null || b == null) return null;
    let m = (a + b) / 2;
    if (Math.abs(a - b) > 180) m = (m + 180) % 360;
    return ((m % 360) + 360) % 360;
}
function sep(a, b) { let d = Math.abs(a - b) % 360; return d > 180 ? 360 - d : d; }

// Aspekti, ko ņemam vērā tranzītos (ciešāki orbi nekā natālā kartē).
const ASPECTS = [
    { name: 'conj', ang: 0,   orb: 3 },
    { name: 'sext', ang: 60,  orb: 2.5 },
    { name: 'sq',   ang: 90,  orb: 3 },
    { name: 'tri',  ang: 120, orb: 3 },
    { name: 'opp',  ang: 180, orb: 3 }
];

// Tranzītplanētu svars un kompozīta punktu svars.
const TRANSIT_W = { Jupiters: 0.8, Saturns: 1.0, Urans: 1.05, Neptuns: 0.9, Plutons: 1.2 };
const POINT_W   = { Saule: 1.0, Meness: 1.0, Venera: 1.1, Marss: 0.8, Saturns: 0.6 };

// Aspekta valence pēc planētas tipa. Jupiters = labvēlīgs; pārējie (malefiķi) — cietie
// aspekti = stress, harmoniskie = konstruktīvi.
function aspectValence(planet, aspName) {
    if (planet === 'Jupiters') {
        return { conj: 1.0, tri: 0.85, sext: 0.55, sq: 0.3, opp: 0.2 }[aspName] ?? 0;
    }
    return { conj: -0.9, sq: -0.85, opp: -0.9, tri: 0.55, sext: 0.4 }[aspName] ?? 0;
}

// ── Tēmu apraksti (krīze = ciets aspekts, soft = harmonisks) ─────────────────
const THEMES = {
    Saturns: {
        Saule:  { label: 'Apņemšanās un lomu pārbaude',
                  crisis: 'Attiecības iziet nopietnu brieduma testu — lomas, pienākumi un kopīgais mērķis tiek pārvērtēti. Pāri, kas to iztur, kļūst daudz stiprāki; trauslās saites šeit var pārtrūkt.',
                  soft: 'Stabils nobriešanas posms — attiecības iegūst struktūru, drošību un skaidru kopīgu virzienu.' },
        Meness: { label: 'Emocionālā vēsuma posms',
                  crisis: 'Emocionāli vēss un attālināts periods — viens vai abi var justies vientuļi vai nesaprasti. Vajadzīga apzināta siltuma un klātbūtnes uzturēšana, citādi aug atsvešināšanās.',
                  soft: 'Emocionālā stabilitāte un nopietnība — drošs laiks ilgtermiņa lēmumiem un saknēm.' },
        Venera: { label: 'Mīlestības nopietnības tests',
                  crisis: 'Mīlestība jūtas smaga vai pienākuma pilna — sākotnējai kaislei jāpārtop dziļākā uzticībā, citādi tā atdziest. Bieži pārbaudījums par to, vai jūtas ir īstas.',
                  soft: 'Uzticības nostiprināšanās — bieži saderināšanās, kopīga apņemšanās vai laulība.' }
    },
    Plutons: {
        Saule:  { label: 'Spēka un identitātes transformācija',
                  crisis: 'Intensīvs varas un kontroles posms — dominances cīņas, greizsirdība vai dziļa pārkārtošanās. Attiecības vai nu pārdzimst spēcīgākas, vai sabrūk zem spiediena.',
                  soft: 'Dziļa, transformējoša saikne — attiecības kļūst patiesākas un intensīvākas, vecais nokrīt nost.' },
        Venera: { label: 'Kaislības un greizsirdības posms',
                  crisis: 'Mīlestība kļūst intensīva, apsēstīga, dažkārt destruktīva — greizsirdība, kontrole vai dziļa atkarība. Spēcīgs pievilkšanās, bet ar risku zaudēt sevi.',
                  soft: 'Dziļa erotiska un emocionāla saplūšana — saikne nostiprinās līdz dvēseles līmenim.' }
    },
    Urans: {
        Saule:  { label: 'Brīvības un pārmaiņu spiediens',
                  crisis: 'Stipra vajadzība pēc brīvības un telpas — pēkšņas pārmaiņas, nemiers vai šķiršanās risks. Rutīna kļūst nepanesama; attiecībām jāatrod jauna forma vai tās lūst.',
                  soft: 'Atsvaidzinošas pārmaiņas — jauni piedzīvojumi un brīvība atdzīvina attiecības.' },
        Venera: { label: 'Negaidītu jūtu pārmaiņu posms',
                  crisis: 'Jūtas kļūst neparedzamas — pievilkšanās uzliesmo un atdziest pēkšņi, var parādīties kārdinājumi vai vēlme pēc neatkarības. Stabilitāte tiek pārbaudīta.',
                  soft: 'Aizraujošs, spontāns posms — attiecības iegūst svaigumu un sajūsmu.' }
    },
    Neptuns: {
        Saule:  { label: 'Ilūziju un skaidrības pārbaude',
                  crisis: 'Idealizācija saskaras ar realitāti — iespējama vilšanās, neskaidrība vai uzticības migla. Svarīgi atšķirt sapni no patiesā cilvēka blakus.',
                  soft: 'Garīga un radoša saplūšana — iejūtīgs, romantisks un iedvesmojošs posms.' },
        Venera: { label: 'Romantikas un maldu posms',
                  crisis: 'Mīlestība top miglaina — vai nu pārāk idealizēta, vai ar slēptiem pārpratumiem. Risks pievērt acis problēmām vai zaudēt robežas.',
                  soft: 'Maiga, beznosacījumu mīlestība un dziļa empātija — gandrīz mistiska tuvība.' }
    },
    Jupiters: {
        Saule:  { label: 'Izaugsmes un paplašināšanās logs',
                  crisis: 'Strauja izaugsme ar pārmērības risku — daudz enerģijas un optimisma, bet jāuzmanās no pārsolīšanās vai izšķērdības.',
                  soft: 'Plašs labvēlīgs logs — bieži kopdzīves sākums, kāzas, bērni vai pārcelšanās. Attiecības aug un plaukst.' },
        Venera: { label: 'Prieka un mīlestības svētība',
                  crisis: 'Mīlestība pārpilna un dāsna, bet jāsargās no pašapmierinātības vai izlaidības.',
                  soft: 'Viens no laimīgākajiem posmiem — siltums, prieks, dāsnums un kopīga svinēšana.' }
    }
};
function themeFor(planet, point, isHard) {
    const node = THEMES[planet]?.[point] || THEMES[planet]?.Saule || null;
    if (!node) return { label: `${planet} ietekme`, text: '' };
    return { label: node.label, text: isHard ? node.crisis : node.soft };
}

// Intensitāte 1–5 no maksimālā stresa.
function intensityOf(peak) {
    if (peak >= 2.2) return 5;
    if (peak >= 1.5) return 4;
    if (peak >= 1.0) return 3;
    if (peak >= 0.6) return 2;
    return 1;
}
const stars = n => '★'.repeat(n) + '☆'.repeat(5 - n);

const timeUnknown = p => !!p?.birth_info?.isTimeUnknown;

// ── Tipiskie pāru attiecību posmi (normatīvā attīstības līkne) ───────────────
// No attiecību VECUMA (gadiem kopā), neatkarīgi no astroloģijas. Avoti: attiecību
// psiholoģija (Gotmans, attīstības posmu modeļi). Grafiks uzliek tos uz astroloģiskās
// kartes un izceļ, KUR tipiskais dzīves posms SAKRĪT ar astroloģisku pārbaudi.
const NORMATIVE = [
    { age: [0, 1.5],  icon: '💞', label: 'Medusmēnesis',          text: 'Aizraušanās un idealizācija — viss šķiet viegli, hormoni dara savu.' },
    { age: [1.5, 3],  icon: '🌫️', label: 'Pirmā vilšanās',        text: 'Idealizācija krīt, parādās reālais cilvēks — pirmā nopietnā pārbaude.' },
    { age: [3, 5],    icon: '⚖️', label: 'Lomu un varas sadale',  text: 'Cīņa par to, kā dalīt pienākumus, naudu un telpu; veidojas ikdienas struktūra.' },
    { age: [6, 8],    icon: '🌀', label: '7 gadu nieze',          text: 'Klasiskais nemiers un atkārtota izvēle — vai apzināti turpinām kopā?' },
    { age: [5, 12],   icon: '👶', label: 'Bērnu slodzes gadi',    text: 'Ja ir mazi bērni — statistiski zemākā partneru apmierinātība, lielākā slodze un mazākais laiks pārim.' },
    { age: [13, 17],  icon: '🔍', label: 'Pusmūža pārvērtēšana',  text: 'Dzīves vidus jautājumi, pusaudžu bērni — attiecības tiek pārskatītas no jauna.' },
    { age: [20, 26],  icon: '🪺', label: 'Tukšā ligzda',          text: 'Bērni aiziet — pāris vai nu atklāj viens otru no jauna, vai pamana, ka sveši.' }
];

// Decimāls kalendārais gads (Jan 1 = vesels skaitlis; ½ gada = .5).
function decYear(ms) {
    const d = new Date(ms), y = d.getUTCFullYear();
    const s = Date.UTC(y, 0, 1), e = Date.UTC(y + 1, 0, 1);
    return y + (ms - s) / (e - s);
}

// ── Galvenā eksporta funkcija (async — efemerīda) ────────────────────────────
export async function calculateRelationshipForecast(p1, p2, baselinePct = 60, opts = {}) {
    if (!p1 || !p2 || typeof swisseph?.calculatePosition !== 'function') return null;

    const yrMs = 365.25 * 86400000, msPerDay = 86400000;
    const nowAge = clamp(opts.anchorYearsAgo || 0, 0, 30);   // cik gadus pāris JAU ir kopā
    const panYears = clamp(opts.panYears || 0, -40, 60);     // skalas pabīdīšana (kalendārs)
    const futureYears = opts.futureYears || 25;
    const viewYears = nowAge + futureYears;                   // redzamā loga platums gados
    const stepDays = opts.stepDays || 45;                     // paraugs ik ~1.5 mēneši
    const todayMs = Date.now();
    const relStartMs = todayMs - nowAge * yrMs;               // attiecību sākums (age 0)
    const viewStartMs = relStartMs + panYears * yrMs;         // redzamā loga sākums (pan pārbīda)
    const viewEndMs = viewStartMs + viewYears * yrMs;

    // Kompozīta punkti (tropiskie garumi).
    const COMP = {};
    for (const pl of ['Saule', 'Meness', 'Venera', 'Marss', 'Saturns']) {
        COMP[pl] = midpoint(lon(p1, pl), lon(p2, pl));
    }
    const compPoints = Object.entries(COMP).filter(([, v]) => v != null);
    if (!compPoints.length) return null;

    // Paraugu cilpa pa KALENDĀRO laiku.
    const samples = [];   // { age, cal, climate, net, stress, boost, hits }
    for (let ms = viewStartMs; ms <= viewEndMs; ms += stepDays * msPerDay) {
        const dateStr = new Date(ms).toISOString().split('T')[0];
        let jd;
        try { jd = getJulianDay(dateStr + 'T12:00:00Z'); } catch (e) { continue; }

        const tlon = {};
        for (const pl of ['Jupiters', 'Saturns', 'Urans', 'Neptuns', 'Plutons']) {
            try { tlon[pl] = ((swisseph.calculatePosition(jd, PID[pl], 260).longitude % 360) + 360) % 360; }
            catch (e) { /* izlaiž */ }
        }

        let stress = 0, boost = 0; const hits = [];
        for (const [tp, tl] of Object.entries(tlon)) {
            if (tl == null) continue;
            for (const [cp, cl] of compPoints) {
                const dsep = sep(tl, cl);
                let chosen = null, chosenOrb = Infinity;
                for (const a of ASPECTS) {
                    const od = Math.abs(dsep - a.ang);
                    if (od <= a.orb && od < chosenOrb) { chosenOrb = od; chosen = a; }
                }
                if (!chosen) continue;
                const tight = aspectFalloff(chosenOrb, chosen.orb);
                const val = aspectValence(tp, chosen.name) * (TRANSIT_W[tp] || 1) * (POINT_W[cp] || 1) * tight;
                if (val < 0) stress += -val; else boost += val;
                hits.push({ planet: tp, point: cp, asp: chosen.name, val: Math.round(val * 100) / 100, hard: chosen.name === 'conj' || chosen.name === 'sq' || chosen.name === 'opp' });
            }
        }

        const climate = clamp(Math.round(baselinePct + (boost - stress) * 11), 10, 95);
        samples.push({
            age: Math.round(((ms - relStartMs) / yrMs) * 100) / 100,
            cal: Math.round(decYear(ms) * 1000) / 1000,
            climate, net: Math.round((boost - stress) * 100) / 100,
            stress: Math.round(stress * 100) / 100, boost: Math.round(boost * 100) / 100, hits
        });
    }
    if (!samples.length) return null;

    // Krīžu/izaugsmes logi (sapludina tā paša tranzīta fragmentus). Intensitāte (★) ir
    // RELATĪVA pret šī pāra stiprāko posmu, lai zvaigznes atšķiras (citādi visi būtu ★5).
    const crises = selectWindows(relativeIntensity(mergeWindows(detectWindows(samples, 'stress', 0.55), 0.9)), 6, 2);
    const growth = selectWindows(relativeIntensity(mergeWindows(detectWindows(samples, 'boost', 0.55), 0.9)), 5, 2);

    // Snapshoti: pēc 5/10/20 gadiem NO ŠODIENAS (kalendārs).
    const fromNowList = opts.snapFromNow || [5, 10, 20];
    const snapshots = {};
    for (const fn of fromNowList) {
        const targetCal = decYear(todayMs + fn * yrMs);
        const s = samples.reduce((best, x) => Math.abs(x.cal - targetCal) < Math.abs(best.cal - targetCal) ? x : best, samples[0]);
        snapshots[fn] = snapshotNarrative(fn, s, crises, growth);
    }

    // Normatīvie posmi (pēc attiecību VECUMA) redzamajā logā + sakritība ar krīzi.
    const visStartAge = (viewStartMs - relStartMs) / yrMs, visEndAge = (viewEndMs - relStartMs) / yrMs;
    const normative = NORMATIVE
        .filter(m => m.age[1] >= visStartAge && m.age[0] <= visEndAge)
        .map(m => {
            const overlap = crises.find(c => c.startYear <= m.age[1] && c.endYear >= m.age[0]);
            return { ...m, midAge: (m.age[0] + m.age[1]) / 2,
                     calStart: Math.round(decYear(relStartMs + m.age[0] * yrMs) * 100) / 100,
                     calEnd: Math.round(decYear(relStartMs + m.age[1] * yrMs) * 100) / 100,
                     astroOverlap: overlap || null };
        });

    return {
        relStartCal: decYear(relStartMs), todayCal: decYear(todayMs),
        viewStartCal: decYear(viewStartMs), viewEndCal: decYear(viewEndMs),
        startDate: new Date(relStartMs).toISOString().split('T')[0],
        anchorDate: new Date(todayMs).toISOString().split('T')[0],
        nowAge, panYears, futureYears, viewYears, baselinePct,
        samples: samples.map(s => ({ cal: s.cal, age: s.age, climate: s.climate, net: s.net })),
        crises, growth, snapshots, normative,
        timeUnknown: timeUnknown(p1) || timeUnknown(p2),
        composite: Object.fromEntries(compPoints.map(([k, v]) => [k, Math.round(v)]))
    };
}

// Atrod blakus paraugu logus, kur metrika ≥ slieksnis; apraksta katru ar dominējošo tēmu.
function detectWindows(samples, key, threshold) {
    const out = [];
    let run = null;
    for (const s of samples) {
        if (s[key] >= threshold) {
            if (!run) run = { start: s.age, end: s.age, startCal: s.cal, endCal: s.cal, peak: s, peakVal: s[key] };
            else { run.end = s.age; run.endCal = s.cal; if (s[key] > run.peakVal) { run.peakVal = s[key]; run.peak = s; } }
        } else if (run) { out.push(finalizeWindow(run, key)); run = null; }
    }
    if (run) out.push(finalizeWindow(run, key));
    return out;
}

function finalizeWindow(run, key) {
    // Dominējošais hits maksimuma paraugā.
    const isStress = key === 'stress';
    const peakHits = (run.peak.hits || []).filter(h => isStress ? h.val < 0 : h.val > 0);
    peakHits.sort((a, b) => Math.abs(b.val) - Math.abs(a.val));
    const dom = peakHits[0];
    const theme = dom ? themeFor(dom.planet, dom.point, dom.hard) : { label: isStress ? 'Spriedzes posms' : 'Labvēlīgs posms', text: '' };
    const intensity = intensityOf(run.peakVal);
    return {
        startYear: run.start, endYear: run.end, peakYear: run.peak.age, peakVal: run.peakVal,
        startCal: Math.round(run.startCal), endCal: Math.round(run.endCal), peakCal: run.peak.cal,
        intensity, stars: stars(intensity),
        planet: dom?.planet || null, point: dom?.point || null,
        label: theme.label, text: theme.text,
        type: isStress ? 'crisis' : 'growth'
    };
}

// Sapludina tuvus logus, kas ir TĀ PAŠA tranzīta (planēta+punkts) fragmenti (sprauga ≤ gap).
// Tikai vienāds planēta+punkts → nesalīp dažādu planētu notikumi vienā mega-logā.
function mergeWindows(wins, gap) {
    const sorted = [...wins].sort((a, b) => a.startYear - b.startYear);
    const out = [];
    for (const w of sorted) {
        const last = out[out.length - 1];
        if (last && w.planet === last.planet && w.point === last.point && w.startYear - last.endYear <= gap) {
            last.endYear = Math.max(last.endYear, w.endYear);
            last.endCal = Math.max(last.endCal, w.endCal);
            if (w.peakVal > last.peakVal) {        // pārņem stiprākā loga tēmu/intensitāti
                Object.assign(last, {
                    peakVal: w.peakVal, peakYear: w.peakYear, peakCal: w.peakCal, intensity: w.intensity, stars: w.stars,
                    planet: w.planet, point: w.point, label: w.label, text: w.text
                });
            }
        } else {
            out.push({ ...w });
        }
    }
    return out;
}

// Relatīvā intensitāte: stiprākais posms = ★5, pārējie mērogoti pret to (vairāk spread).
function relativeIntensity(wins) {
    const max = Math.max(0.001, ...wins.map(w => w.peakVal));
    for (const w of wins) {
        w.intensity = clamp(Math.round(1 + 4 * (w.peakVal / max)), 1, 5);
        w.stars = stars(w.intensity);
    }
    return wins;
}

// Patur tikai nozīmīgos (intensitāte ≥ minInt), top maxN pēc intensitātes, atpakaļ pēc gada.
function selectWindows(wins, maxN, minInt) {
    return wins.filter(w => w.intensity >= minInt)
        .sort((a, b) => b.peakVal - a.peakVal)
        .slice(0, maxN)
        .sort((a, b) => a.startYear - b.startYear);
}

function levelWord(v) {
    if (v >= 72) return 'ļoti laba';
    if (v >= 58) return 'laba';
    if (v >= 45) return 'viduvēja';
    if (v >= 32) return 'sasprindzināta';
    return 'kritiska';
}

// fromNow = pēc cik gadiem no šodienas; sample nes .age (attiecību vecums) un .cal (kalendārs).
function snapshotNarrative(fromNow, sample, crises, growth) {
    const age = sample.age;
    const inCrisis = crises.find(c => age >= c.startYear - 0.6 && age <= c.endYear + 0.6);
    const inGrowth = growth.find(g => age >= g.startYear - 0.6 && age <= g.endYear + 0.6);
    const lvl = levelWord(sample.climate);
    const yr = Math.round(sample.cal);
    const ctx = `<span style="color:#94a3b8;">(${yr}. gadā${age >= 0.5 ? `, ${Math.round(age)}. attiecību gads` : ''})</span>`;
    let text;
    if (inCrisis && (!inGrowth || inCrisis.intensity >= 3)) {
        text = `Pēc ${fromNow} gadiem ${ctx} attiecības būs <b>${lvl}</b> stāvoklī, pārbaudījuma posmā: <b>${inCrisis.label}</b>. ${inCrisis.text}`;
    } else if (inGrowth) {
        text = `Pēc ${fromNow} gadiem ${ctx} attiecības būs <b>${lvl}</b> stāvoklī, labvēlīgā posmā: <b>${inGrowth.label}</b>. ${inGrowth.text}`;
    } else {
        text = `Pēc ${fromNow} gadiem ${ctx} attiecības būs <b>${lvl}</b>, samērā mierīgā fāzē bez lielām ārējām pārbaudēm — ikdiena un abu ieguldītais darbs noteiks toni.`;
    }
    return { fromNow, year: yr, age: Math.round(age * 10) / 10, cal: sample.cal, climate: sample.climate, level: lvl, text };
}
