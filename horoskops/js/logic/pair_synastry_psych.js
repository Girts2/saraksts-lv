// horoskops/js/logic/pair_synastry_psych.js
// ─────────────────────────────────────────────────────────────────────────────
// PA-PĀRI PSIHOLOĢIJA — balsotājs #5. Labo veco "fake hibrīdu", kas vienkārši
// VIDĒJOJA abu solo attiecību-gatavību. Šeit modelē ĪSTU atbilstību starp diviem:
//   • Piesaistes stilu atbilstība (Bowlby) — pilna sadalījuma matrica, ne tikai top
//   • Vajadzību↔piedāvājuma komplementaritāte (viena sinerģija sedz otra berzi)
//   • Gotmana kopējais konfliktu risks (vai ABIEM aktīvs tas pats jātnieks)
//
// Spēcīgā puse konsensā: komunikācija un emocionālā atbilstība — klīniski balstīta,
// dzimumneitrāla. Robusta nezināmam laikam (Big-Five nāk no daudzsistēmu personības).
// ─────────────────────────────────────────────────────────────────────────────

const clamp100 = v => Math.max(0, Math.min(100, v));

// Piesaistes stilu atbilstības matrica (simetriska). Klasiskā "trauksmains↔izvairīgs"
// lamatas = zems; jebkurš ar drošo = augsts; izvairīgs+izvairīgs = stabili-distancēts.
const ATTACH_FIT = {
    secure:       { secure: 90, anxious: 78, avoidant: 75, disorganized: 62 },
    anxious:      { secure: 78, anxious: 58, avoidant: 36, disorganized: 44 },
    avoidant:     { secure: 75, anxious: 36, avoidant: 60, disorganized: 47 },
    disorganized: { secure: 62, anxious: 44, avoidant: 47, disorganized: 38 }
};

// Pārvērš allScores [{key,score}] par varbūtību sadalījumu (normalizē uz summu 1).
function distribution(allScores) {
    if (!Array.isArray(allScores) || !allScores.length) return null;
    const total = allScores.reduce((s, a) => s + (a.score || 0), 0);
    if (total <= 0) return null;
    const d = {};
    for (const a of allScores) d[a.key] = (a.score || 0) / total;
    return d;
}

// Gaidāmā piesaistes atbilstība pār abu sadalījumu krustojumu.
function expectedAttachFit(d1, d2) {
    if (!d1 || !d2) return null;
    let sum = 0, w = 0;
    for (const k1 of Object.keys(d1)) {
        for (const k2 of Object.keys(d2)) {
            const fit = ATTACH_FIT[k1]?.[k2];
            if (fit == null) continue;
            const p = d1[k1] * d2[k2];
            sum += fit * p; w += p;
        }
    }
    return w > 0 ? sum / w : null;
}

// Helperi solo dinamikas izvilkšanai.
const syn  = (dyn, key) => dyn?.synergyAll?.find(s => s.key === key)?.score ?? null;
const fric = (dyn, key) => dyn?.frictionAll?.find(f => f.key === key)?.score ?? null;
const hors = (dyn, key) => dyn?.horsemen?.allScores?.find(h => h.key === key)?.score ?? null;
const g = (v, d = 50) => (v == null ? d : v);

// Komplementaritāte: cik A piedāvājums (offer) atvieglo B vajadzību (need).
// Augsts atvieglojums, ja offer augsts UN need augsts; atgriež 0–100.
function relief(offer, need) {
    return (g(offer) / 100) * g(need);   // 0..100
}

export function calculatePairPsych(p1, p2) {
    const dyn1 = p1?.relationshipDynamics, dyn2 = p2?.relationshipDynamics;
    if (!dyn1 && !dyn2) return null;

    // ── Piesaistes atbilstība ────────────────────────────────────────────────
    const attachFit = expectedAttachFit(
        distribution(dyn1?.attachment?.allScores),
        distribution(dyn2?.attachment?.allScores)
    );

    // ── Emocionālā atbilstība ────────────────────────────────────────────────
    // A emocionālā drošība sedz B emocionālo nestabilitāti/distanci (un otrādi) +
    // abpusējs jutekliskums + piesaistes atbilstība.
    const safe1 = syn(dyn1, 'emotional_safety'), safe2 = syn(dyn2, 'emotional_safety');
    const volat1 = fric(dyn1, 'emotional_volatility'), volat2 = fric(dyn2, 'emotional_volatility');
    const dist1 = fric(dyn1, 'emotional_distance'), dist2 = fric(dyn2, 'emotional_distance');
    const reliefA = relief(safe1, Math.max(g(volat2), g(dist2)));   // A nomierina B
    const reliefB = relief(safe2, Math.max(g(volat1), g(dist1)));   // B nomierina A
    const intimacy = (g(syn(dyn1, 'physical_intimacy')) + g(syn(dyn2, 'physical_intimacy'))) / 2;
    const emotional = clamp100(Math.round(
        0.40 * g(attachFit) +
        0.30 * ((reliefA + reliefB) / 2) +
        0.30 * intimacy
    ));

    // ── Komunikācija / konflikti (Gotmans) ───────────────────────────────────
    // Kopējais jātnieku risks: ja ABIEM augsts tas pats jātnieks → eskalācija.
    // pairRisk = sqrt(A·B) katram jātniekam → augsts tikai ja abi augsti.
    const HK = ['criticism', 'contempt', 'defensiveness', 'stonewalling'];
    let worstPair = 0;
    for (const k of HK) {
        const r = Math.sqrt(g(hors(dyn1, k)) * g(hors(dyn2, k)));   // ģeometriskais vidējais
        if (r > worstPair) worstPair = r;
    }
    const intel = (g(syn(dyn1, 'intellectual_partnership')) + g(syn(dyn2, 'intellectual_partnership'))) / 2;
    const interaction = clamp100(Math.round(
        0.55 * (100 - worstPair) +
        0.25 * intel +
        0.20 * g(attachFit)
    ));

    // ── Ģimenes vērtības ─────────────────────────────────────────────────────
    // Lojalitāte/dziļums abiem + piesaistes drošība + lomu komplementaritāte.
    const loyalty = (g(syn(dyn1, 'loyalty_depth')) + g(syn(dyn2, 'loyalty_depth'))) / 2;
    const secure = ((dyn1?.attachment?.allScores?.find(a => a.key === 'secure')?.score ?? 50) +
                    (dyn2?.attachment?.allScores?.find(a => a.key === 'secure')?.score ?? 50)) / 2;
    const values = clamp100(Math.round(
        0.45 * loyalty +
        0.30 * secure +
        0.25 * g(attachFit)
    ));

    return {
        system: 'psych',
        axes: { emotional, values, interaction },
        detail: {
            attachFit: attachFit != null ? Math.round(attachFit) : null,
            primary1: dyn1?.attachment?.primary?.lv ?? null,
            primary2: dyn2?.attachment?.primary?.lv ?? null,
            worstSharedHorseman: Math.round(worstPair),
            mutualSoothing: Math.round((reliefA + reliefB) / 2)
        },
        // Big-Five nāk no daudzsistēmu personības (galvenokārt datumā balstīta) → vidēja-augsta.
        confidence: (dyn1 && dyn2) ? 0.7 : 0.5
    };
}
