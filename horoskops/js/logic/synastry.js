// horoskops/js/logic/synastry.js
// ─────────────────────────────────────────────────────────────────────────────
// RIETUMU SINASTRIJA — balsotājs #2 (papildus Vēdiskajai Ashta Kuta).
// Salīdzina abu partneru TROPISKO planētu garumus (western.planets[...].longitude),
// aprēķina inter-aspektus (konjunkcija/sekstils/kvadrāts/trigons/opozīcija) un
// Tarvainena māju pārklāšanos (viena Saule/Mēness/Venēra otra 1./5./7. mājā).
//
// Spēcīgā puse, ko šis balsotājs ienes konsensā: emocionālā ķīmija, kaisle un
// ikdienas saskaņa — tieši tas, ko Mēness-tikai Ashta Kuta NEMĒRA.
//
// Dati jau ir profilā (generateFullProfile): nav vajadzīga jauna efemerīdu matemātika.
// ─────────────────────────────────────────────────────────────────────────────

import { aspectFalloff } from './aspect_utils.js?v=1';

// Aspektu definīcijas: leņķis, orbis (pielaide grādos), bāzes kvalitāte [-1..+1].
// conj kvalitāte ir atkarīga no planētu pāra (intensificē — labvēlīgu pāri uzlabo,
// nelabvēlīgu pasliktina), tāpēc to risina perPair, ne šeit.
const ASPECTS = [
    { name: 'conj',     angle: 0,   orb: 8 },
    { name: 'sextile',  angle: 60,  orb: 5,  q:  0.6 },
    { name: 'square',   angle: 90,  orb: 7,  q: -0.8 },
    { name: 'trine',    angle: 120, orb: 8,  q:  1.0 },
    { name: 'opp',      angle: 180, orb: 8,  q: -0.3 }  // polarizē, bet arī pievelk
];

// Sinastrijas pāri: [planētaA, planētaB, kategorija, svars, conjKvalitāte, hardPastiprinātājs].
// hard>1 → kvadrāts/opozīcija šim pārim skaitās vēl negatīvāk (Saturns, Marss-Marss).
// Pāri tiek vērtēti ABOS virzienos (p1.A↔p2.B un p1.B↔p2.A), izņemot kad A===B.
const PAIRS = [
    ['Saule',  'Meness',   'bond',          1.0, 0.9, 1.0],
    ['Meness', 'Meness',   'emotional',     0.8, 0.9, 1.0],
    ['Venera', 'Marss',    'chemistry',     1.0, 0.9, 1.0],
    ['Venera', 'Venera',   'love',          0.5, 0.8, 1.0],
    ['Meness', 'Venera',   'affection',     0.7, 0.9, 1.0],
    ['Saule',  'Venera',   'attraction',    0.6, 0.9, 1.0],
    ['Meness', 'Saturns',  'commitment',    0.7, 0.3, 1.25],
    ['Venera', 'Saturns',  'commitment',    0.7, 0.4, 1.25],
    ['Marss',  'Marss',    'conflict',      0.6, 0.3, 1.3],
    ['Merkurs','Merkurs',  'communication', 0.7, 0.7, 1.0],
    ['Meness', 'Merkurs',  'understanding', 0.6, 0.7, 1.0],
    ['Saule',  'Saturns',  'respect',       0.4, 0.4, 1.15]
];

// Kategorija → kura ass to saņem (emotional / values / interaction).
const CAT_AXIS = {
    bond: 'emotional', emotional: 'emotional', chemistry: 'emotional',
    love: 'emotional', affection: 'emotional', attraction: 'emotional',
    commitment: 'values', respect: 'values',
    communication: 'interaction', understanding: 'interaction', conflict: 'interaction'
};

const clamp100 = v => Math.max(0, Math.min(100, v));

function lon(p, name) {
    const v = p?.western?.planets?.[name]?.longitude;
    return typeof v === 'number' ? ((v % 360) + 360) % 360 : null;
}

// Leņķiskā distance starp diviem garumiem (0..180).
function sep(a, b) {
    let d = Math.abs(a - b) % 360;
    return d > 180 ? 360 - d : d;
}

// Atrod ciešāko aspektu (vai null). Atgriež { name, q, exact } — q jau ar conj risināšanu.
function findAspect(a, b, conjQ, hardMul) {
    if (a == null || b == null) return null;
    const d = sep(a, b);
    let best = null, bestOrb = Infinity;
    for (const asp of ASPECTS) {
        const orbDiff = Math.abs(d - asp.angle);
        if (orbDiff <= asp.orb && orbDiff < bestOrb) {
            bestOrb = orbDiff;
            let q = asp.name === 'conj' ? conjQ : asp.q;
            if (q < 0) q *= hardMul;                  // pastiprina cietos kontaktus grūtiem pāriem
            // Tuvāks eksaktam = stiprāks — gluds (raised-cosine) kritiens līdz 0 pie orba malas.
            const tight = aspectFalloff(bestOrb, asp.orb);
            best = { name: asp.name, q: q * tight };
        }
    }
    return best;
}

// ── Tarvainena māju pārklāšanās (tikai zināmam laikam) ───────────────────────
// Whole-sign mājas no partnera Ascendenta. Saule/Mēness/Venēra otra 1./5./7. mājā
// = tūlītēja atpazīstamība (Tarvainen 2011); 6./8./12. = berze.
function signIdx(p, name) {
    const l = lon(p, name);
    return l == null ? null : Math.floor(l / 30);
}

function houseOverlay(fromP, toP) {
    const asc = signIdx(toP, 'Ascendant');
    if (asc == null) return { bond: 0, friction: 0, hits: [] };
    let bond = 0, friction = 0; const hits = [];
    for (const pl of ['Saule', 'Meness', 'Venera']) {
        const si = signIdx(fromP, pl);
        if (si == null) continue;
        const house = ((si - asc + 12) % 12) + 1;
        if ([1, 5, 7].includes(house)) { bond += 1; hits.push(`${pl}→${house}`); }
        else if ([6, 8, 12].includes(house)) { friction += 1; }
    }
    return { bond, friction, hits };
}

const timeUnknown = p => !!p?.birth_info?.isTimeUnknown;

// ── Galvenā eksporta funkcija ────────────────────────────────────────────────
export function calculateSynastry(p1, p2) {
    if (!p1 || !p2) return null;

    // Asu akumulatori: weighted quality summa + svaru summa (tikai pāriem ar aspektu).
    const acc = {
        emotional:   { qs: 0, w: 0 },
        values:      { qs: 0, w: 0 },
        interaction: { qs: 0, w: 0 }
    };
    const aspects = [];      // caurspīdīgumam: atrastie kontakti
    let evaluated = 0, found = 0;

    for (const [A, B, cat, weight, conjQ, hardMul] of PAIRS) {
        const axis = CAT_AXIS[cat];
        // Virzieni: p1.A↔p2.B un (ja A≠B) p1.B↔p2.A
        const contacts = [[lon(p1, A), lon(p2, B), `${A}–${B}`]];
        if (A !== B) contacts.push([lon(p1, B), lon(p2, A), `${B}–${A}`]);

        for (const [la, lb, label] of contacts) {
            evaluated++;
            const asp = findAspect(la, lb, conjQ, hardMul);
            if (!asp) continue;
            found++;
            acc[axis].qs += weight * asp.q;
            acc[axis].w  += weight;
            aspects.push({ pair: label, cat, aspect: asp.name, q: Math.round(asp.q * 100) / 100, axis });
        }
    }

    // Māju pārklāšanās (tikai ja ABIEM zināms laiks) — pieskaita emocionālajai/values asij.
    let overlay = null;
    if (!timeUnknown(p1) && !timeUnknown(p2)) {
        const o12 = houseOverlay(p1, p2), o21 = houseOverlay(p2, p1);
        const bond = o12.bond + o21.bond, friction = o12.friction + o21.friction;
        overlay = { bond, friction, hits: [...o12.hits, ...o21.hits] };
        // Katrs 1/5/7 trāpījums ≈ +0.5 emocionālajai ar svaru 0.8; berze −0.4.
        const net = bond * 0.5 - friction * 0.4;
        acc.emotional.qs += net * 0.8; acc.emotional.w += 0.8;
        acc.values.qs    += net * 0.5; acc.values.w    += 0.5;
    }

    // Asu pct: vidējā kvalitāte [-1..+1] → [10..90] (50=neitrāls; konservatīvi, bez pārpārliecības).
    const axisPct = a => a.w > 0 ? clamp100(Math.round(50 + 40 * (a.qs / a.w))) : null;
    const axes = {
        emotional:   axisPct(acc.emotional),
        values:      axisPct(acc.values),
        interaction: axisPct(acc.interaction)
    };

    // Ticamība: pārklājums (cik aspekti atrasti) + laika zināšana (Asc/mājas pieejamas).
    const coverage = evaluated > 0 ? found / evaluated : 0;
    let confidence = 0.5 + 0.35 * coverage;             // 0.5..0.85
    if (timeUnknown(p1) || timeUnknown(p2)) confidence *= 0.8;  // bez Asc — mazāk signāla
    confidence = Math.round(Math.min(0.9, confidence) * 100) / 100;

    return { system: 'synastry', axes, aspects, overlay, confidence };
}
