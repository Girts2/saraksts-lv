// horoskops/js/logic/bazi_synastry.js
// ─────────────────────────────────────────────────────────────────────────────
// BaZi (Ķīniešu) SADERĪBA — balsotājs #4. Pilnīgi NEATKARĪGS no Rietumu/Vēdiskās
// sistēmas, tāpēc dod īstu trešo viedokli konsensā. Galvenais: dienas valdnieku
// (Day Master) elementu attiecība — tā balstās tikai DATUMĀ, ne dzimšanas laikā,
// tāpēc šis balsotājs ir PILNĪBĀ robusts nezināmam laikam (atšķirībā no pārējiem).
//
// Mēra: temperamenta plūsmu, savstarpējo atbalstu un dominanci.
//   • Ražojošais cikls (viens baro otru)  → atbalsts, kopšana (augsta harmonija)
//   • Kontrolējošais cikls (viens valda)  → spēka nelīdzsvars (kaisle vai dominance)
//   • Vienāds elements + pretēja polaritāte → komplementāra draudzība
//   • Elementu bilances komplementaritāte → vai partneris dod to, kā tev trūkst
//   • Gada zara (Chinese zodiac) San He / Liu He / Chong → klasiskā dzīvnieku saderība
// ─────────────────────────────────────────────────────────────────────────────

const ELEMENTS = ['Koks', 'Uguns', 'Zeme', 'Metāls', 'Ūdens'];
const clamp100 = v => Math.max(0, Math.min(100, v));

// Ražojošais cikls (Sheng): index → ko tas baro.
const GENERATES = { 'Koks':'Uguns', 'Uguns':'Zeme', 'Zeme':'Metāls', 'Metāls':'Ūdens', 'Ūdens':'Koks' };
// Kontrolējošais cikls (Ke): index → ko tas valda.
const CONTROLS  = { 'Koks':'Zeme', 'Zeme':'Ūdens', 'Ūdens':'Uguns', 'Uguns':'Metāls', 'Metāls':'Koks' };

// Attiecība no e1 (1. cilvēks) uz e2 (2. cilvēks).
function elementRelation(e1, e2) {
    if (e1 === e2) return 'same';
    if (GENERATES[e1] === e2) return 'gen_12';     // 1 baro 2
    if (GENERATES[e2] === e1) return 'gen_21';     // 2 baro 1
    if (CONTROLS[e1] === e2)  return 'ctrl_12';    // 1 valda 2
    if (CONTROLS[e2] === e1)  return 'ctrl_21';    // 2 valda 1
    return 'neutral';
}

// Temperamenta harmonija (0–100) no DM attiecības + polaritātes.
function temperamentScore(e1, pol1, e2, pol2) {
    const rel = elementRelation(e1, e2);
    // Bāze pēc cikla
    const BASE = {
        gen_12: 80, gen_21: 80,   // savstarpējs atbalsts (viena puse baro otru)
        same:   68,               // līdzība — komforts, bet arī konkurence
        ctrl_12: 50, ctrl_21: 50, // kontrole — spēka nelīdzsvars
        neutral: 60
    };
    let s = BASE[rel] ?? 60;

    // Polaritāte: pretēja (Jaņ↔Iņ) = komplementāra pievilkšanās (+8); vienāda = berze (−6).
    const opp = pol1 && pol2 && pol1 !== pol2;
    if (opp) s += 8; else if (pol1 && pol2) s -= 6;

    // Vienāds elements + vienāda polaritāte = visstiprākā konkurence.
    if (rel === 'same' && pol1 === pol2) s -= 6;

    return { score: clamp100(s), rel, opp };
}

// Bilances komplementaritāte: vai partneris ir stiprs elementā, kura man trūkst.
// balance = { Koks:%, Uguns:%, ... }. Atgriež 0–100.
function balanceComplement(bal1, bal2) {
    if (!bal1 || !bal2) return null;
    const deficit = el => Math.max(0, 20 - (bal1[el] ?? 20));   // <20% = trūkums (5 elementi → vidēji 20%)
    let relief = 0, need = 0;
    for (const el of ELEMENTS) {
        const d = deficit(el);
        need += d;
        relief += d * Math.min(1, (bal2[el] ?? 0) / 25);        // partneris piegādā, ja >25% tajā elementā
    }
    // Abpusēji: arī cik 1. piegādā 2. trūkumam.
    const deficit2 = el => Math.max(0, 20 - (bal2[el] ?? 20));
    let relief2 = 0, need2 = 0;
    for (const el of ELEMENTS) {
        const d = deficit2(el);
        need2 += d;
        relief2 += d * Math.min(1, (bal1[el] ?? 0) / 25);
    }
    const r1 = need > 0 ? relief / need : 0.5;
    const r2 = need2 > 0 ? relief2 / need2 : 0.5;
    return clamp100(Math.round(40 + 50 * ((r1 + r2) / 2)));      // 40..90
}

// ── Gada zars (Chinese zodiac) saderība — datumā balstīts ────────────────────
const SAN_HE = [ ['Zi','Shen','Chen'], ['Hai','Mao','Wei'], ['Yin','Wu','Xu'], ['Si','You','Chou'] ];
const LIU_HE = [ ['Zi','Chou'], ['Yin','Hai'], ['Mao','Xu'], ['Chen','You'], ['Si','Shen'], ['Wu','Wei'] ];
const CHONG  = [ ['Zi','Wu'], ['Chou','Wei'], ['Yin','Shen'], ['Mao','You'], ['Chen','Xu'], ['Si','Hai'] ];

function branchKey(b) { return (b || '').split(' ')[0]; }

function branchHarmony(b1, b2) {
    const k1 = branchKey(b1), k2 = branchKey(b2);
    if (!k1 || !k2) return { score: null, type: null };
    if (k1 === k2) return { score: 62, type: 'tā pati zīme' };
    const inPair = (arr) => arr.some(p => (p[0] === k1 && p[1] === k2) || (p[1] === k1 && p[0] === k2));
    const inTrine = SAN_HE.some(g => g.includes(k1) && g.includes(k2));
    if (LIU_HE.some(p => (p[0] === k1 && p[1] === k2) || (p[1] === k1 && p[0] === k2)))
        return { score: 90, type: 'Liu He (sešu harmoniju pāris)' };
    if (inTrine) return { score: 85, type: 'San He (trijstūra saskaņa)' };
    if (inPair(CHONG)) return { score: 28, type: 'Chong (zīmju sadursme)' };
    return { score: 58, type: 'neitrāla' };
}

// ── Galvenā eksporta funkcija ────────────────────────────────────────────────
export function calculateBaziSynastry(p1, p2) {
    const dm1 = p1?.bazi?.Daymaster, dm2 = p2?.bazi?.Daymaster;
    if (!dm1 || !dm2) return null;

    const temp = temperamentScore(dm1.element, dm1.polarity, dm2.element, dm2.polarity);
    const balance = balanceComplement(p1?.bazi?.elements, p2?.bazi?.elements);
    const branch = branchHarmony(p1?.bazi?.Year?.Branch, p2?.bazi?.Year?.Branch);

    // Asu kartējums:
    //   interaction (ikdienas temperaments) = DM temperaments (galvenais)
    //   values (savstarpējs atbalsts/ilgtspēja) = bilances komplements + gada zars
    //   emotional — BaZi tieši nemēra (atstāj null, lai citi balsotāji to nosaka)
    const valuesParts = [balance, branch.score].filter(v => v != null);
    const values = valuesParts.length
        ? clamp100(Math.round(valuesParts.reduce((s, v) => s + v, 0) / valuesParts.length))
        : null;

    const axes = {
        emotional:   null,
        values,
        interaction: temp.score
    };

    return {
        system: 'bazi',
        axes,
        detail: {
            dm1: `${dm1.element} (${dm1.polarity})`,
            dm2: `${dm2.element} (${dm2.polarity})`,
            relation: temp.rel,
            polarityComplement: temp.opp,
            balanceComplement: balance,
            branch: branch.type
        },
        // BaZi DM ir datumā balstīts → robusts arī bez laika. Augsta ticamība.
        confidence: 0.75
    };
}
