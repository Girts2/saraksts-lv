// horoskops/js/logic/composite.js
// ─────────────────────────────────────────────────────────────────────────────
// KOMPOZĪTKARTE — balsotājs #3. Attiecības kā patstāvīga "trešā entītija".
// Aprēķina viduspunktu (midpoint) kartes Saulei/Mēnesim/Venērai/Marsam/Saturnam
// un to iekšējos aspektus. Kamēr sinastrija rāda "saturu" (kā viens ietekmē otru),
// kompozīts rāda "konteineru" — vai pašam pārim ir ilgtermiņa struktūra.
//
// Spēcīgā puse konsensā: ilgtspēja / struktūra. Saules–Venēras konjunkcija =
// "mīlestības svētais grāls"; smags Mēness–Saturns/Plūtons = emocionāls vēsums;
// Marss–Urāns = nepastāvība. Sign-balstīts → robusts arī nezināmam laikam (izņemot
// Mēnesi, kas kustas ~13°/dienā — tā ticamība pazeminās).
// ─────────────────────────────────────────────────────────────────────────────

import { aspectFalloff } from './aspect_utils.js?v=1';

const RASI = ['Auns','Vērsis','Dvīņi','Vēzis','Lauva','Jaunava',
              'Svari','Skorpions','Strēlnieks','Mežāzis','Ūdensvīrs','Zivis'];
// Zīmju elements: stabilitātes signāls kompozīta Mēnesim.
const SIGN_ELEM = ['Uguns','Zeme','Gaiss','Ūdens','Uguns','Zeme',
                   'Gaiss','Ūdens','Uguns','Zeme','Gaiss','Ūdens'];
const clamp100 = v => Math.max(0, Math.min(100, v));

function lon(p, name) {
    const v = p?.western?.planets?.[name]?.longitude;
    return typeof v === 'number' ? ((v % 360) + 360) % 360 : null;
}

// Tuvākā loka viduspunkts (standarta kompozīta konvencija).
function midpoint(a, b) {
    if (a == null || b == null) return null;
    let m = (a + b) / 2;
    if (Math.abs(a - b) > 180) m = (m + 180) % 360;
    return ((m % 360) + 360) % 360;
}

function sep(a, b) {
    let d = Math.abs(a - b) % 360;
    return d > 180 ? 360 - d : d;
}

// Aspekta kvalitāte starp diviem kompozīta punktiem (vienkāršots — bez conj nianses;
// conjQ padod izsaucējs atkarībā no pāra).
function aspectQ(a, b, conjQ, hardMul = 1) {
    if (a == null || b == null) return null;
    const d = sep(a, b);
    const TBL = [
        { ang: 0,   orb: 8,  q: conjQ },
        { ang: 60,  orb: 5,  q: 0.6 },
        { ang: 90,  orb: 7,  q: -0.8 },
        { ang: 120, orb: 8,  q: 1.0 },
        { ang: 180, orb: 8,  q: -0.3 }
    ];
    let best = null, bestOrb = Infinity;
    for (const t of TBL) {
        const od = Math.abs(d - t.ang);
        if (od <= t.orb && od < bestOrb) {
            bestOrb = od;
            let q = t.q < 0 ? t.q * hardMul : t.q;
            best = q * aspectFalloff(od, t.orb);
        }
    }
    return best;
}

const timeUnknown = p => !!p?.birth_info?.isTimeUnknown;

export function calculateComposite(p1, p2) {
    if (!p1 || !p2) return null;

    // Kompozīta punkti (garumi).
    const C = {};
    for (const pl of ['Saule','Meness','Venera','Marss','Saturns','Urans','Plutons']) {
        C[pl] = midpoint(lon(p1, pl), lon(p2, pl));
    }

    const emo = { qs: 0, w: 0 };       // emocionālā harmonija
    const cont = { qs: 0, w: 0 };      // konteiners / struktūra / ilgtspēja
    const notes = [];

    const add = (acc, q, w, label, good) => {
        if (q == null) return;
        acc.qs += q * w; acc.w += w;
        if (good != null && Math.abs(q) > 0.4) notes.push({ label, good: q > 0 });
    };

    // Saule–Venēra: "mīlestības svētais grāls" (konj/trigons) → emocionālā + konteiners.
    const sv = aspectQ(C.Saule, C.Venera, 0.95);
    add(emo, sv, 1.0, 'Saule–Venēra (kodola mīlestība)', true);
    add(cont, sv, 0.6, 'Saule–Venēra', true);

    // Saule–Mēness: identitātes + emociju integrācija → konteiners.
    add(cont, aspectQ(C.Saule, C.Meness, 0.8), 0.9, 'Saule–Mēness (integrācija)', true);

    // Mēness–Saturns: stabilitāte (trigons) vai vēsums (kvadrāts/opoz) → emocionālā.
    add(emo, aspectQ(C.Meness, C.Saturns, 0.3, 1.25), 0.8, 'Mēness–Saturns (vēsums/stabilitāte)', true);
    // Mēness–Plūtons: intensitāte / zemapziņas cīņas → emocionālā.
    add(emo, aspectQ(C.Meness, C.Plutons, 0.3, 1.2), 0.6, 'Mēness–Plūtons (intensitāte)', true);
    // Marss–Urāns: pēkšņa agresija / nepastāvība → konteiners (struktūras risks).
    add(cont, aspectQ(C.Marss, C.Urans, 0.3, 1.3), 0.7, 'Marss–Urāns (nepastāvība)', true);
    // Venēra–Saturns: ilgtermiņa saistība (trigons) vai ierobežojums → konteiners.
    add(cont, aspectQ(C.Venera, C.Saturns, 0.5, 1.1), 0.7, 'Venēra–Saturns (saistība)', true);

    // Kompozīta Mēness zīmes elements: Zeme/Ūdens = stabila emocionālā bāze (+),
    // robežgadījums nezināmam laikam (Mēness var pārlēkt zīmi → ticamība zemāka).
    let moonSignBonus = 0;
    if (C.Meness != null) {
        const el = SIGN_ELEM[Math.floor(C.Meness / 30)];
        moonSignBonus = (el === 'Zeme' || el === 'Ūdens') ? 0.3 : (el === 'Gaiss' ? -0.05 : 0.05);
        add(emo, moonSignBonus, 0.5, `Kompozīta Mēness ${RASI[Math.floor(C.Meness/30)]}`, false);
    }

    const axisPct = a => a.w > 0 ? clamp100(Math.round(50 + 40 * (a.qs / a.w))) : null;
    const axes = {
        emotional:   axisPct(emo),
        values:      axisPct(cont),     // konteiners ↔ ģimenes vērtības/ilgtspēja
        interaction: null               // kompozīts nemēra ikdienas komunikāciju tieši
    };

    // Ticamība: Sun/Venus/Mars/Saturn kompozīts ir laika-robusts; Mēness daļa nav.
    let confidence = 0.6;
    if (timeUnknown(p1) || timeUnknown(p2)) confidence = 0.45;  // Mēness komponente nedroša
    confidence = Math.round(confidence * 100) / 100;

    return {
        system: 'composite',
        axes,
        signSummary: {
            sun:   C.Saule  != null ? RASI[Math.floor(C.Saule/30)]  : null,
            moon:  C.Meness != null ? RASI[Math.floor(C.Meness/30)] : null,
            venus: C.Venera != null ? RASI[Math.floor(C.Venera/30)] : null
        },
        notes,
        confidence
    };
}
