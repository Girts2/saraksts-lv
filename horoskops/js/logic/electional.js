// ─────────────────────────────────────────────────────────────────────────────
// ELEKCIONĀLAIS DZINĒJS — "Kad rīkoties"
// ─────────────────────────────────────────────────────────────────────────────
// Pārvērš jau esošo dienas konsensu (buildDayConsensus) par lēmumu rīku: lietotājs
// izvēlas NODOMU (parakstīt darījumu, sarunas, palaist, …), dzinējs skenē nākamās
// N dienas × 24 stundas un atgriež RANGOTUS laika logus ar paskaidrojumu "kāpēc".
//
// Signāli, kas der visām 30 dienām (rēķināti astronomiski jebkuram datumam):
//   • Planētu stundas (haldeju secība) — katra stunda valda kāda planēta
//   • Muhurta logi — Abhijit (zelta logs), Rahu Kaal (izvairies)
//   • BaZi Liu Ri — dienas valence (neatkarīgs no Mēness ass, der jebkuram datumam)
// Tuvākās ~7 dienas papildus bagātina dienas konsenss (≥2 sistēmas) un Tara Bala
// stundu skala (~48h) → tāpēc tuvākiem logiem ir augstāka "ticamība".
//
// Datu avots: buildDayConsensus(profile, dateStr) no weekly_consensus.js — viss
// jau aprēķināts, šeit tikai svaram pret nodomu un sameklē logus.
// ─────────────────────────────────────────────────────────────────────────────
import { buildDayConsensus } from './weekly_consensus.js?v=11';

// ── Planētu loma (planētu stundu nozīme neastrologa valodā) ──────────────────
const PLANET_LV   = { Sun:'Saules', Venus:'Veneras', Mercury:'Merkura', Moon:'Mēness', Saturn:'Saturna', Jupiter:'Jupitera', Mars:'Marsa' };
const PLANET_ROLE = {
    Sun:     'autoritāte, redzamība',
    Venus:   'vērtība, harmonija, nauda',
    Mercury: 'sarunas, līgumi, vārdi',
    Moon:    'emocijas, sabiedrība',
    Saturn:  'disciplīna, pacietība',
    Jupiter: 'izaugsme, veiksme',
    Mars:    'enerģija, iniciatīva',
};

// ── Nodomu definīcijas ───────────────────────────────────────────────────────
// planets.good / planets.bad — planētu stundas, kas atbalsta / kavē šo darbību.
// domains — kuras dienas konsensa jomas vēlamas (saskan → bonuss).
export const INTENTS = {
    deal:      { icon:'🤝', label:'Parakstīt darījumu', tag:'līgumi, vienošanās',
                 planets:{ good:['Jupiter','Mercury','Sun'], bad:['Mars','Saturn'] }, domains:['nauda','darbs','komunikācija'],
                 blurb:'Līguma, partnerības vai darījuma parakstīšanai — kad vārds tiek dots.' },
    negotiate: { icon:'💬', label:'Sarunas / alga', tag:'cena, nosacījumi',
                 planets:{ good:['Mercury','Venus','Jupiter'], bad:['Mars','Saturn'] }, domains:['nauda','komunikācija','attiecības'],
                 blurb:'Algas, cenas vai nosacījumu pārrunām, kur svarīga vārdu plūsma un labvēlība.' },
    launch:    { icon:'🚀', label:'Palaist / uzsākt', tag:'starts, kampaņa',
                 planets:{ good:['Sun','Jupiter','Mars'], bad:['Saturn'] }, domains:['darbs','komunikācija','risks'],
                 blurb:'Produkta, biznesa vai kampaņas startam — kad vajag iniciatīvu un redzamību.' },
    pitch:     { icon:'🎤', label:'Prezentēt / pārdot', tag:'uzstāšanās',
                 planets:{ good:['Sun','Mercury','Jupiter'], bad:['Saturn','Mars'] }, domains:['komunikācija','darbs'],
                 blurb:'Prezentācijai, pārdošanas zvanam vai uzstāšanās — kad jāspīd un jāpārliecina.' },
    invest:    { icon:'💰', label:'Investēt', tag:'finanšu lēmums',
                 planets:{ good:['Jupiter','Venus','Mercury'], bad:['Mars'] }, domains:['nauda','risks'],
                 blurb:'Investīcijai vai lielam finanšu lēmumam — kad jāvairās no impulsa un steigas.' },
    hire:      { icon:'👥', label:'Pieņemt darbā', tag:'komanda, intervija',
                 planets:{ good:['Jupiter','Sun','Venus'], bad:['Mars','Saturn'] }, domains:['darbs','attiecības'],
                 blurb:'Darbinieka pieņemšanai vai svarīgai komandas tikšanās — kad meklē īstos cilvēkus.' },
    hard_talk: { icon:'🫱', label:'Grūta saruna', tag:'konflikts, atgriezeniskā saite',
                 planets:{ good:['Mercury','Moon','Venus'], bad:['Mars','Saturn'] }, domains:['komunikācija','attiecības'],
                 blurb:'Sarežģītai sarunai vai konflikta risināšanai — kad vajag skaidrību un mieru, ne uguni.' },
    rest:      { icon:'🌙', label:'Atpūsties', tag:'atjaunošanās',
                 planets:{ good:['Moon','Venus','Jupiter'], bad:['Mars','Saturn'] }, domains:['veselība'],
                 blurb:'Atpūtai un atjaunošanās — kad apzināti NEpalaist svarīgu darbu.' },
};

// ── Sliekšņi un parametri (regulējami) ───────────────────────────────────────
const GOOD = 62;          // slots ≥ → labvēlīgs logs
const BAD  = 30;          // slots ≤ → izvairāmais logs
const WAKE_START = 6;     // biznesa darbības notiek nomodā
const WAKE_END   = 22;
const DEFAULT_HORIZON = 30;

const clamp = (lo, hi, v) => Math.max(lo, Math.min(hi, v));
const overlap = (h, w) => w && (h + 1) > w.start && h < w.end; // vai lokālā stunda h pārklājas ar logu w

function addDays(dateStr, n) {
    const d = new Date(dateStr + 'T12:00:00Z');
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().split('T')[0];
}

function localTodayStr(tz) {
    if (tz && window.moment) { try { return window.moment.tz(tz).format('YYYY-MM-DD'); } catch (e) {} }
    return new Date().toISOString().split('T')[0];
}

const WEEKDAY_LV = ['Svētdiena','Pirmdiena','Otrdiena','Trešdiena','Ceturtdiena','Piektdiena','Sestdiena'];
export function weekdayLV(dateStr) {
    const d = new Date(dateStr + 'T12:00:00Z');
    return WEEKDAY_LV[d.getUTCDay()];
}
export function fmtDateLV(dateStr) {
    const [, m, dd] = dateStr.split('-');
    const MONTHS = ['','janv.','febr.','martā','apr.','maijā','jūn.','jūl.','aug.','sept.','okt.','nov.','dec.'];
    return `${parseInt(dd,10)}. ${MONTHS[parseInt(m,10)]}`;
}
export function fmtHour(val) {
    const h = Math.floor(val), m = Math.round((val - h) * 60);
    return `${String(((h % 24) + 24) % 24).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
}

// ── Stundas BĀZES vērtējums (nodoma-AGNOSTisks) ──────────────────────────────
// Nosaka loga vispārējo kvalitāti (cik diena/stunda ir auspiciāla). Nodomu specifika
// (kam tieši piemērots) tiek klasificēta atsevišķi — sk. classifyWindowIntents.
// Atgriež { score 0..100, reasons:[{t, good, golden?, block?}], planet }
export function scoreHourBase(day, h) {
    let score = 50;
    const reasons = [];

    // 1) Mēness Tara Bala stundu skala (jēga tikai ~48h; citur neitrāls 50 → 0 ieguldījums)
    const tb = (day.hourly && day.hourly[h] !== undefined) ? day.hourly[h] : 50;
    score += (tb - 50) * 0.30; // ±15
    if (tb >= 80)      reasons.push({ t:'Mēness atbalsts (Tara Bala augsta)', good:true });
    else if (tb <= 20) reasons.push({ t:'Mēness pretestība (Tara Bala zema)', good:false });

    // 2) Daudzsistēmu dienas konsenss (≥2 sistēmas saskan)
    const c = day.consensus;
    if (c && c.confirmed) {
        if (c.direction === 'supportive')      { score += 12; reasons.push({ t:`${c.agree} sistēmas saskan — atbalstoša diena`, good:true }); }
        else if (c.direction === 'challenging'){ score -= 16; reasons.push({ t:`${c.agree} sistēmas saskan — saspringta diena`, good:false }); }
    }

    // 3) BaZi dienas valence (neatkarīgs balsotājs, der jebkuram datumam)
    if (day.bazi) {
        if (day.bazi.valence === 'supportive')       score += 6;
        else if (day.bazi.valence === 'challenging') score -= 8;
    }

    // 4) Planētu stunda — vispārējs benefic/malefic svars (sadala dienu šaurākos logos
    //    ap labvēlīgajām stundām; specifiku pret nodomiem dod classifyWindowIntents).
    //    Bez šī atbalstošā diena būtu plakana → 8–12h "logi". Malefiķi (Marss/Saturns)
    //    iegrimst zem sliekšņa un dabiski pārtrauc logu.
    const planet = day.planetaryHours && day.planetaryHours[h];
    const BENEFIC = { Jupiter:7, Venus:6, Mercury:3, Sun:3, Moon:1, Saturn:-8, Mars:-10 };
    if (planet) {
        score += BENEFIC[planet] ?? 0;
        reasons.push({ t:`${PLANET_LV[planet]} stunda — ${PLANET_ROLE[planet]}`, good:(BENEFIC[planet] ?? 0) >= 0, astro:true });
    }

    // 5) Abhijit Muhurta — dienas zelta logs (mērens +12, lai nedominē katru dienu)
    if (overlap(h, day.abhijit)) { score += 12; reasons.push({ t:'Abhijit Muhurta — dienas zelta logs', good:true, golden:true }); }
    // 6) Rahu Kaal — izvairies (praktiski izslēdz)
    if (overlap(h, day.rahuKaal)) { score -= 45; reasons.push({ t:'Rahu Kaal — nelabvēlīgas stundas', good:false, block:true }); }

    // 7) Nomoda filtrs — biznesa darbības notiek dienā/vakarā, ne dziļā naktī
    if (h < WAKE_START || h >= WAKE_END) score -= 35;

    return { score: clamp(0, 100, score), reasons, planet };
}

// ── Nodomu klasifikācija logam ───────────────────────────────────────────────
// Katram no 8 nodomiem nosaka, vai logs tam ir piemērots / nepiemērots / neitrāls,
// balstoties uz loga planētu stundām + dienas jomām (NE uz vienmērīgo dienas valenci,
// lai nodomi tiešām atšķirtos savā starpā). hours = loga stundu indeksi.
function classifyWindowIntents(day, hours) {
    const c = day.consensus;
    const domains = (c && c.domains) || [];
    const tot = Math.max(1, hours.length);

    const scored = Object.entries(INTENTS).map(([key, intent]) => {
        let s = 0;
        // jomu sakritība (nodoma jomas ∩ dienas jomas) — diferencē pēc tēmas
        const dm = domains.filter(d => intent.domains.includes(d));
        s += dm.length * 8;
        // planētu stundas logā — galvenais diferencētājs
        let pg = 0, pb = 0;
        for (const h of hours) {
            const p = day.planetaryHours && day.planetaryHours[h];
            if (p && intent.planets.good.includes(p)) pg++;
            else if (p && intent.planets.bad.includes(p)) pb++;
        }
        s += (pg / tot) * 20 - (pb / tot) * 20;
        return { key, label:intent.label, icon:intent.icon, score: Math.round(s) };
    });

    scored.sort((a, b) => b.score - a.score);
    const suitable   = scored.filter(x => x.score >= 8);
    const unsuitable = scored.filter(x => x.score <= -8).sort((a, b) => a.score - b.score);

    let verdict;
    if (suitable.length)        verdict = { state:'good',    label:'Piemērota',        color:'#16a34a' };
    else if (unsuitable.length) verdict = { state:'avoid',   label:'Mazāk piemērota',  color:'#d97706' };
    else                        verdict = { state:'neutral', label:'Neitrāls logs',    color:'#64748b' };

    return { verdict, suitable: suitable.slice(0, 3), unsuitable: unsuitable.slice(0, 2) };
}

// ── Ticamība pēc dienu attāluma ──────────────────────────────────────────────
function confidenceOf(offset) {
    if (offset <= 1) return { key:'high', label:'Augsta', note:'Mēness stundu dati + tranzīti dzīvi' };
    if (offset <= 6) return { key:'mid',  label:'Laba',   note:'dienas konsenss dzīvs' };
    return { key:'base', label:'Pamata', note:'planētu stundas + BaZi' };
}

// ── Logu sapludināšana dienā pēc predikāta ───────────────────────────────────
function mergeWindows(cell, slots, predicate) {
    const wins = [];
    let cur = null;
    for (let h = 0; h < 24; h++) {
        if (predicate(slots[h].score)) {
            if (!cur) cur = { startH:h, endH:h, slots:[] };
            cur.endH = h; cur.slots.push({ h, ...slots[h] });
        } else if (cur) { wins.push(cur); cur = null; }
    }
    if (cur) wins.push(cur);

    return wins.map(w => {
        const scores = w.slots.map(s => s.score);
        const peak = Math.max(...scores);
        const avg  = scores.reduce((a, b) => a + b, 0) / scores.length;
        const hours = w.slots.map(s => s.h);
        // unikāli iemesli (dedup pēc teksta), zelta priekšā
        const seen = new Set();
        const reasons = [];
        for (const s of w.slots) for (const r of s.reasons) {
            if (seen.has(r.t)) continue; seen.add(r.t); reasons.push(r);
        }
        reasons.sort((a, b) => (b.golden ? 1 : 0) - (a.golden ? 1 : 0));

        const cls = classifyWindowIntents(cell.day, hours);
        return {
            date: cell.dateStr,
            offset: cell.offset,
            startHour: w.startH,
            endHour: w.endH + 1,
            peak: Math.round(peak),
            avg: Math.round(avg),
            golden: w.slots.some(s => s.reasons.some(r => r.golden)),
            confidence: confidenceOf(cell.offset),
            // astro "kāpēc" iemesli (Mēness/konsenss/Abhijit/planētu stunda)
            reasonsGood: reasons.filter(r => r.good).slice(0, 4),
            reasonsBad:  reasons.filter(r => !r.good).slice(0, 3),
            // nodomu klasifikācija → kartītes virsraksts
            verdict:    cls.verdict,
            suitable:   cls.suitable,
            unsuitable: cls.unsuitable,
        };
    });
}

// ── Dienu šūnu kešs (neatkarīgs no nodoma — skaitļojam vienreiz, rangojam lēti) ─
let _cache = { key:null, cells:null };
export function clearElectionalCache() { _cache = { key:null, cells:null }; }

function getCells(profile, horizon) {
    const tz = profile?.current_loc?.timezone ?? profile?.birth_info?.timezone ?? 'Europe/Riga';
    const lat = profile?.current_loc?.lat ?? profile?.birth_info?.lat ?? 0;
    const lon = profile?.current_loc?.lon ?? profile?.birth_info?.lon ?? 0;
    const today = localTodayStr(tz);
    const key = `${lat}|${lon}|${horizon}|${today}`;
    if (_cache.key === key) return _cache.cells;

    const cells = [];
    for (let i = 0; i < horizon; i++) {
        const dateStr = addDays(today, i);
        let day = null;
        try { day = buildDayConsensus(profile, dateStr); } catch (e) { /* izlaižam bojātu dienu */ }
        if (day) cells.push({ offset:i, dateStr, day });
    }
    _cache = { key, cells };
    return cells;
}

// ── Galvenais: rangoti laika logi (nodoma-agnostiski; katrs logs pats novērtē nodomus) ─
export function buildElectionalWindows(profile, opts = {}) {
    const horizon = opts.horizon || DEFAULT_HORIZON;
    if (!profile) return { best:[], avoid:[], meta:{ horizon, scanned:0 } };

    const cells = getCells(profile, horizon);

    let best = [];
    let avoid = [];
    for (const cell of cells) {
        const slots = [];
        for (let h = 0; h < 24; h++) slots.push(scoreHourBase(cell.day, h));

        best = best.concat(mergeWindows(cell, slots, s => s >= GOOD));
        // izvairāmos atrodam atsevišķi, ar nomoda filtru pa stundu (citādi "ir nakts" nav padoms)
        let cur = null;
        for (let h = WAKE_START; h < WAKE_END; h++) {
            if (slots[h].score <= BAD) {
                if (!cur) cur = { startH:h, endH:h, slots:[] };
                cur.endH = h; cur.slots.push({ h, ...slots[h] });
            } else if (cur) { avoid.push(...mergeOne(cell, cur)); cur = null; }
        }
        if (cur) avoid.push(...mergeOne(cell, cur));
    }

    // Rangs ar DAUDZVEIDĪBU (MMR): pirmais = globāli labākais; tālāk sodām logus, kas
    // dublē jau izvēlēto pēc diennakts laika vai dienas → saraksts piedāvā reālas alternatīvas,
    // ne 8× "12:00–13:00". (Sk. selectDiverse.)
    const bestTop = selectDiverse(best, 8, horizon);

    // Izvairāmie: tuvākie pirmie (soonest), tikai pirmās 2 nedēļas
    avoid = avoid.filter(w => w.offset < 14).sort((a, b) => (a.offset - b.offset) || (a.startHour - b.startHour)).slice(0, 4);

    return { best: bestTop, avoid, meta:{ horizon, scanned: cells.length } };
}

function rankScore(w, horizon) {
    return w.avg * 0.6 + w.peak * 0.4 + (w.golden ? 8 : 0) + ((horizon - w.offset) / horizon) * 6;
}

// Daudzveidīga top-N atlase (MMR): mantkārīgi izvēlas labāko, tad katram nākamajam
// piemēro sodu par līdzību ar JAU izvēlētajiem (tuvs diennakts laiks / tā pati diena).
// Tā saraksts dod dažādus laika logus, nevis vienu un to pašu stundu atkārtoti.
function selectDiverse(windows, n, horizon) {
    const pool = [...windows];
    const picked = [];
    while (picked.length < n && pool.length) {
        let bestIdx = 0, bestVal = -Infinity;
        for (let i = 0; i < pool.length; i++) {
            const w = pool[i];
            let val = rankScore(w, horizon);
            for (const p of picked) {
                const dh = Math.abs(w.startHour - p.startHour);
                if (dh <= 1)      val -= 14;  // gandrīz tas pats diennakts laiks
                else if (dh <= 3) val -= 6;
                if (w.date === p.date) val -= 20; // tā pati diena → stipri attur dublikātus
            }
            if (val > bestVal) { bestVal = val; bestIdx = i; }
        }
        picked.push(pool.splice(bestIdx, 1)[0]);
    }
    return picked;
}

// ─────────────────────────────────────────────────────────────────────────────
// BIZNESA KALENDĀRS — bīdāms 9 dienu logs ("Kad rīkoties" cilnē "Karte")
// ─────────────────────────────────────────────────────────────────────────────
// Tas pats dzinējs (scoreHourBase + classifyWindowIntents), bet sakārtots KALENDĀRĀ:
// katra no 9 dienām pati par sevi — datums, dienas notikumu intensitāte, zelta logs
// ar laiku un intensitāti, kam diena piemērota / nepiemērota, 24h profils. Logu var
// pabīdīt ±3 dienas. Visi 24h aprēķini ir pēc LOKĀLĀ laika (sk. weekly_consensus.js).
// ─────────────────────────────────────────────────────────────────────────────

// Dienas notikumu intensitātes joslas (cik diena ir astroloģiski "piesātināta")
const INTENSITY_BANDS = [
    { min: 72, key:'high',   label:'Intensīva' },
    { min: 50, key:'strong', label:'Spēcīga'  },
    { min: 25, key:'mid',    label:'Mērena'   },
    { min: 0,  key:'low',    label:'Klusa'    },
];
function intensityBand(v) { return INTENSITY_BANDS.find(b => v >= b.min) || INTENSITY_BANDS[INTENSITY_BANDS.length - 1]; }

// Zelta loga intensitāte pēc vidējā vērtējuma
function windowIntensity(avg) {
    if (avg >= 80) return { key:'high', label:'augsta' };
    if (avg >= 68) return { key:'mid',  label:'vidēja' };
    return { key:'low', label:'mērena' };
}

// Labākais nepārtrauktais dienas logs (≥GOOD). Ja tāda nav — mīkstais labākais nomoda logs.
function dayGoldenWindow(slots) {
    const wins = []; let cur = null;
    for (let h = 0; h < 24; h++) {
        if (slots[h].score >= GOOD) { if (!cur) cur = { s:h, e:h, sc:[] }; cur.e = h; cur.sc.push(slots[h].score); }
        else if (cur) { wins.push(cur); cur = null; }
    }
    if (cur) wins.push(cur);

    if (wins.length) {
        wins.forEach(w => w.avg = w.sc.reduce((a, b) => a + b, 0) / w.sc.length);
        wins.sort((a, b) => (b.avg - a.avg) || ((b.e - b.s) - (a.e - a.s)));
        const w = wins[0];
        const hours = []; for (let h = w.s; h <= w.e; h++) hours.push(h);
        const avg = Math.round(w.avg);
        return { startHour: w.s, endHour: w.e + 1, avg, hours, soft:false, intensity: windowIntensity(avg) };
    }
    // mīkstais: labākā nomoda stunda (diena bez izteikta loga — piedāvājam vislabāko, kas ir)
    let bh = -1, bs = -Infinity;
    for (let h = WAKE_START; h < WAKE_END; h++) { if (slots[h].score > bs) { bs = slots[h].score; bh = h; } }
    if (bh < 0) return null;
    const avg = Math.round(bs);
    return { startHour: bh, endHour: bh + 1, avg, hours:[bh], soft:true, intensity: windowIntensity(avg) };
}

// Izvairāmie nomoda logi (≤BAD) — kad svarīgu nedarīt
function dayAvoidWindows(slots) {
    const wins = []; let cur = null;
    for (let h = WAKE_START; h < WAKE_END; h++) {
        if (slots[h].score <= BAD) { if (!cur) cur = { s:h, e:h }; cur.e = h; }
        else if (cur) { wins.push(cur); cur = null; }
    }
    if (cur) wins.push(cur);
    return wins.map(w => ({ startHour: w.s, endHour: w.e + 1 }));
}

// Dienas notikumu intensitāte 0..100 — cik daudz un cik spēcīgi ietekmējoši signāli
function computeDayIntensity(day) {
    let I = 0;
    const c = day.consensus;
    if (c && c.confirmed) I += c.agree * 16;                       // saskanošas sistēmas = reāli notikumi
    const wc = day.western?.count || 0; I += Math.min(30, wc * 9);  // rietumu tranzītu aspekti
    const tb = day.hourly || []; let amp = 0;
    for (const v of tb) amp = Math.max(amp, Math.abs(v - 50));      // Tara Bala amplitūda dienā
    I += (amp / 50) * 20;
    if (day.bazi && day.bazi.valence && day.bazi.valence !== 'neutral') I += 10;
    if (day.moon && day.moon.valence && day.moon.valence !== 'neutral') I += 6;
    I = clamp(0, 100, Math.round(I));
    const band = intensityBand(I);
    const dir = (c && c.confirmed) ? c.direction
              : (day.bazi && day.bazi.valence !== 'neutral') ? day.bazi.valence : 'neutral';
    return { score: I, key: band.key, label: band.label, direction: dir || 'neutral' };
}

// Galvenais: 9 (vai span) dienu biznesa kalendārs, sākot ar startOffset (var būt negatīvs)
export function buildBusinessCalendar(profile, startOffset = 0, span = 9) {
    if (!profile) return { days: [], meta: { startOffset, span } };
    const tz = profile?.current_loc?.timezone ?? profile?.birth_info?.timezone ?? 'Europe/Riga';
    const today = localTodayStr(tz);

    const days = [];
    for (let i = 0; i < span; i++) {
        const offset = startOffset + i;
        const dateStr = addDays(today, offset);
        let day = null;
        try { day = buildDayConsensus(profile, dateStr); } catch (e) { continue; }

        const slots = [];
        for (let h = 0; h < 24; h++) slots.push(scoreHourBase(day, h));

        const golden = dayGoldenWindow(slots);
        const avoid  = dayAvoidWindows(slots);
        const cls    = classifyWindowIntents(day, golden ? golden.hours : []);
        const intensity = computeDayIntensity(day);

        days.push({
            date: dateStr,
            offset,
            isToday: offset === 0,
            weekday: weekdayLV(dateStr),
            dateLabel: fmtDateLV(dateStr),
            consensus: day.consensus,
            moon: day.moon, bazi: day.bazi, western: day.western,
            hourlyScore: slots.map(s => Math.round(s.score)),     // 24h profils siltumkartei
            planetaryHours: day.planetaryHours,
            golden, avoid,
            abhijit: day.abhijit, rahuKaal: day.rahuKaal,
            sunriseHour: day.sunriseHour, sunsetHour: day.sunsetHour,
            verdict: cls.verdict, suitable: cls.suitable, unsuitable: cls.unsuitable,
            intensity,
            confidence: confidenceOf(offset >= 0 ? offset : 99),    // pagātnes dienām pamata ticamība
        });
    }
    return { days, meta: { startOffset, span, today } };
}

// mergeOne — viens kandidāta logs → standartizēts (izvairāmajiem). Tāds pats forma kā
// labajiem logiem, lai to varētu renderēt kā kartīti, bet verdikts fiksēts "avoid".
function mergeOne(cell, w) {
    const scores = w.slots.map(s => s.score);
    const seen = new Set(); const reasons = [];
    for (const s of w.slots) for (const r of s.reasons) { if (seen.has(r.t)) continue; seen.add(r.t); reasons.push(r); }
    return [{
        date: cell.dateStr,
        offset: cell.offset,
        startHour: w.startH,
        endHour: w.endH + 1,
        peak: Math.round(Math.min(...scores)),
        avg: Math.round(scores.reduce((a, b) => a + b, 0) / scores.length),
        golden: false,
        confidence: confidenceOf(cell.offset),
        verdict: { state:'avoid', label:'Nepiemērota', color:'#dc2626' },
        suitable: [],
        unsuitable: [],
        reasonsGood: [],
        reasonsBad: reasons.filter(r => !r.good).slice(0, 3),
    }];
}
