// horoskops/js/logic/bigfive_synastry.js
// ─────────────────────────────────────────────────────────────────────────────
// BIG FIVE (OCEAN) SADERĪBA — balsotājs #6. Eiklīda attālums starp abu partneru
// jau aprēķinātajiem OCEAN profiliem (calculateBigFive), sadalīts pa asīm pēc tā,
// kuras iezīmes katrai asij ir saturiski relevantas.
//
// SVARĪGI: šis balsotājs NAV pilnīgi neatkarīgs no "psych" (#5) balsotāja —
// relationship_dynamics.js piesaistes stilu vērtējumi jau paši izmanto OCEAN
// (skat. relationship_dynamics.js:180-187), tāpēc daļa signāla pārklājas.
// Tomēr matemātika ir cita: šeit tiešs Eiklīda attālums starp iezīmju vektoriem,
// nevis piesaistes-stilu varbūtību sadalījuma krustojums. Tāpēc konsensā dabū
// mērenu (ne maksimālu) svaru un ticamību — papildu leņķis, ne dublikāts.
//
// Metrika (pēc pētījuma D_OCEAN formulas, piemērota pa asīm relevantām iezīmēm):
//   D(A,B) = sqrt( Σ ((a_i-b_i)/100)² / n )   →   līdzība = round((1-D)*100)
// ─────────────────────────────────────────────────────────────────────────────

import { calculateBigFive } from './big_five.js?v=2';

const clamp100 = v => Math.max(0, Math.min(100, v));

// Katrai asij relevantās OCEAN dimensijas (saturiski, ne tikai formulas dēļ).
const AXIS_TRAITS = {
    emotional:   ['N', 'A'],   // emocionālā reaktivitāte + siltums/empātija
    values:      ['O', 'C'],   // pasaules uzskats + dzīves struktūra/disciplīna
    interaction: ['E', 'A']    // sociālais ritms + sadarbības/konflikta stils
};

// Eiklīda līdzība [0,100] normalizētiem (0-100) vektoriem, vidēji pa n dimensijām.
function euclidSim(a, b, traits) {
    let sumSq = 0, n = 0;
    for (const t of traits) {
        const av = a?.[t]?.pct, bv = b?.[t]?.pct;
        if (typeof av !== 'number' || typeof bv !== 'number') continue;
        sumSq += ((av - bv) / 100) ** 2;
        n++;
    }
    if (n === 0) return null;
    const dist = Math.sqrt(sumSq / n);
    return clamp100(Math.round((1 - dist) * 100));
}

export function calculateBigFiveSynastry(p1, p2) {
    if (!p1 || !p2) return null;
    let bfA, bfB;
    try {
        bfA = calculateBigFive(p1);
        bfB = calculateBigFive(p2);
    } catch {
        return null;
    }
    if (!bfA || !bfB) return null;

    const axes = {};
    for (const [axis, traits] of Object.entries(AXIS_TRAITS)) {
        axes[axis] = euclidSim(bfA, bfB, traits);
    }

    // Kopējā (visu 5 dimensiju) līdzība — caurspīdīgumam un detaļu skatam.
    const overallSimilarity = euclidSim(bfA, bfB, ['O', 'C', 'E', 'A', 'N']);

    return {
        system: 'bigfive',
        axes,
        detail: {
            overallSimilarity,
            traitsA: { O: bfA.O?.pct, C: bfA.C?.pct, E: bfA.E?.pct, A: bfA.A?.pct, N: bfA.N?.pct },
            traitsB: { O: bfB.O?.pct, C: bfB.C?.pct, E: bfB.E?.pct, A: bfB.A?.pct, N: bfB.N?.pct }
        },
        // Vidēja: derīgs papildu signāls, bet daļēji korelē ar "psych" balsotāju (tā pati OCEAN bāze).
        confidence: 0.6
    };
}
