// horoskops/js/logic/compat_consensus.js
// ─────────────────────────────────────────────────────────────────────────────
// DAUDZSISTĒMU SADERĪBAS KONSENSS — agregators.
// Saliek 6 balsotājus (lielākoties neatkarīgus, skat. #6 piezīmi), katru tikai
// tajās dimensijās, kur tas ir spēcīgs, ar ticamības svaru. Sekojot pētījuma
// "makro filtrs + mikro analīze" loģikai:
//   • Vēdiskā Ashta Kuta (Mēness) = bioenerģētiskais MAKRO karkass
//   • Rietumu sinastrija          = emocionālā ķīmija, kaisle, ikdienas saskaņa
//   • Kompozītkarte               = attiecību ilgtspējas KONTEINERS
//   • BaZi                        = temperaments (datumā balstīts, robusts)
//   • Pa-pāri psiholoģija         = komunikācija, piesaistes atbilstība (klīniskā)
//   • Big Five (OCEAN)           = tiešs personības-vektora Eiklīda attālums
//
// Galvenā vērtība: "augsta pārliecība" tikai tad, ja ≥2 neatkarīgas sistēmas saskan,
// un atklāti SIGNĀLI, kad tās NESASKAN (piem. augsts Kuta, bet Ascendenti konfliktē).
// ─────────────────────────────────────────────────────────────────────────────

import { calculateCompatibility } from './compatibility.js?v=15';
import { calculateSynastry }       from './synastry.js?v=2';
import { calculateComposite }      from './composite.js?v=2';
import { calculateBaziSynastry }   from './bazi_synastry.js?v=1';
import { calculatePairPsych }      from './pair_synastry_psych.js?v=1';
import { calculateBigFiveSynastry } from './bigfive_synastry.js?v=2';

const clamp100 = v => Math.max(0, Math.min(100, v));
const AXES = ['emotional', 'values', 'interaction'];

// Balsotāju metadati: cilvēciskais nosaukums, ko tas mēra, un cik katra sistēma
// "sver" katrā asī (pirms ticamības reizinātāja). 0 = sistēma šo asi nemēra.
const VOTERS = {
    vedic:     { label: 'Vēdiskā (Ashta Kuta)', icon: '🌙', short: 'Mēness',
                 name: 'Mēness saderība', what: 'Indiešu sistēma — instinktīvā un emocionālā saskaņa pēc abu Mēness stāvokļa',
                 w: { emotional: 0.9, values: 0.9, interaction: 0.6 } },
    synastry:  { label: 'Rietumu sinastrija',   icon: '💫', short: 'Planētas',
                 name: 'Planētu ķīmija', what: 'Rietumu sistēma — pievilkšanās un ikdienas ķīmija pēc abu planētu (Venēra, Marss, Saule) leņķiem',
                 w: { emotional: 1.0, values: 0.7, interaction: 0.9 } },
    composite: { label: 'Kompozītkarte',        icon: '🔗', short: 'Kopkarte',
                 name: 'Attiecību kopkarte', what: 'Rietumu sistēma — pašu attiecību kā veseluma struktūra un ilgtspēja',
                 w: { emotional: 0.7, values: 1.0, interaction: 0.0 } },
    bazi:      { label: 'BaZi (ķīniešu)',       icon: '☯️', short: 'Enerģijas',
                 name: 'Enerģijas tipi', what: 'Ķīniešu sistēma — temperaments un savstarpējais atbalsts pēc abu dzimšanas elementiem',
                 w: { emotional: 0.0, values: 0.7, interaction: 0.8 } },
    psych:     { label: 'Pa-pāri psiholoģija',  icon: '🧠', short: 'Psiholoģija',
                 name: 'Psiholoģiskā atbilstība', what: 'Klīniskā psiholoģija — piesaistes stilu atbilstība un komunikācija (Bowlby, Gotmans)',
                 w: { emotional: 0.8, values: 0.7, interaction: 1.0 } },
    bigfive:   { label: 'Big Five (OCEAN)',      icon: '🧬', short: 'Personība',
                 name: 'Personības līdzība', what: 'Psihometrija — Eiklīda attālums starp abu OCEAN (Lielā piecinieka) profiliem',
                 w: { emotional: 0.5, values: 0.5, interaction: 0.5 } }
};

// Asu metadati cilvēciskiem aprakstiem.
const AXIS_META = {
    emotional:   { title: 'Dvēseles rezonanse', q: 'Cik dziļi jūs jutīsiet un sapratīsiet viens otru?' },
    values:      { title: 'Ģimenes vērtības',   q: 'Vai jums ir kopīgs dzīves virziens un ilgtermiņa pamats?' },
    interaction: { title: 'Komunikācija',       q: 'Cik viegli jūs runāsiet un risināsiet konfliktus?' }
};

// Līmeņa vārds pēc procenta (cilvēkam saprotams).
function levelWord(pct) {
    if (pct == null) return '—';
    if (pct >= 80) return 'ļoti spēcīga';
    if (pct >= 65) return 'spēcīga';
    if (pct >= 50) return 'laba';
    if (pct >= 35) return 'vidēja';
    return 'vāja';
}
function levelColor(pct) {
    if (pct == null) return '#94a3b8';
    if (pct >= 65) return '#10b981';
    if (pct >= 50) return '#22c55e';
    if (pct >= 35) return '#f59e0b';
    return '#ef4444';
}

// Ass stāstnieks: cilvēciska teikuma ģenerators, kas nosauc, kura sistēma ceļ un kura
// velk uz leju, plus interpretē atšķirību. Bez žargona.
function axisNarrative(axis, pct, voters) {
    if (pct == null || !voters.length) return '';
    const meta = AXIS_META[axis];
    const sorted = [...voters].sort((a, b) => b.pct - a.pct);
    const top = sorted[0], bottom = sorted[sorted.length - 1];
    const lvl = levelWord(pct);
    let s = `<b>${meta.title}</b> ir <b style="color:${levelColor(pct)};">${lvl}</b> (${pct}%). `;

    if (sorted.length >= 2 && top.pct - bottom.pct >= 18) {
        s += `Visvairāk to ceļ <b>${top.name}</b> (${top.pct}%), savukārt <b>${bottom.name}</b> ir vājāka (${bottom.pct}%) — `;
        s += axisGapNote(axis, top.system, bottom.system);
    } else if (sorted.length >= 2) {
        s += `Visas sistēmas šeit saskan diezgan vienoti (${bottom.pct}–${top.pct}%), tāpēc šim vērtējumam var uzticēties vairāk.`;
    } else {
        s += `Šo asi novērtē tikai viena sistēma (${top.name}), tāpēc pārliecība ir zemāka.`;
    }
    return s;
}

// Interpretē, ko nozīmē atšķirība starp augstāko un zemāko sistēmu konkrētā asī.
function axisGapNote(axis, topSys, botSys) {
    if (axis === 'emotional') {
        if ((topSys === 'synastry') && (botSys === 'vedic'))
            return 'sākotnējā pievilkšanās un ķīmija ir spēcīgāka nekā dziļā emocionālā saplūšana. Aizraušanās nāks viegli, bet emocionālo tuvību būs jāveido apzināti.';
        if ((topSys === 'vedic') && (botSys === 'synastry'))
            return 'dziļā emocionālā saderība ir laba, bet "dzirksteles" un ķīmijas ir mazāk. Tuvība veidosies lēni un stabili, ne uzreiz.';
        return 'emocionālajā plaknē sistēmas saskata atšķirīgas stiprās puses — gan ķīmija, gan dziļums attīstīsies dažādā tempā.';
    }
    if (axis === 'values') {
        if (botSys === 'composite')
            return 'jūs labi saderat kā cilvēki, bet pašai attiecību struktūrai pietrūkst ilgtermiņa "stūrakmens". Kopīgi mērķi būs jāizrunā un jāuzbūvē apzināti.';
        if (topSys === 'composite')
            return 'pašām attiecībām ir spēcīgs ilgtermiņa karkass, pat ja atsevišķās jomās ir atšķirības.';
        return 'kopīgo vērtību pamats ir, bet dažas jomas prasīs vienošanos.';
    }
    // interaction
    if (botSys === 'psych')
        return 'astroloģiski jūs labi saderat, bet komunikācijas stilā (kā runājat un strīdaties) ir jāstrādā — vārdi var sāpināt vairāk nekā iecerēts.';
    if (topSys === 'psych')
        return 'jūsu komunikācijas stili labi sader; pat ja citur ir berze, jūs spēsiet to izrunāt.';
    return 'saziņa kopumā darbosies, bet temperamentu atšķirības laiku pa laikam radīs berzi.';
}

// Asu galīgais svars kopvērtējumā (kopdzīvei values+interaction ir tikpat svarīgi kā ķīmija).
const AXIS_WEIGHT = { emotional: 0.36, values: 0.34, interaction: 0.30 };

const TIERS = [
    { min: 80, name: 'Izcila',    color: '#8b5cf6', mark: '✨' },
    { min: 68, name: 'Ļoti laba', color: '#0ea5e9', mark: '🔵' },
    { min: 56, name: 'Laba',      color: '#10b981', mark: '🟢' },
    { min: 45, name: 'Vidēja',    color: '#f59e0b', mark: '🟡' },
    { min: 0,  name: 'Zema',      color: '#ef4444', mark: '🔴' }
];
const tierFor = s => TIERS.find(t => s >= t.min) || TIERS[TIERS.length - 1];

const AXIS_DESC = {
    emotional: [[35,'Emocionāla distance — grūti just otru.'],[50,'Vēss miers — vairāk racionāls nekā jūtu līmenis.'],[65,'Silta simpātija — labs emocionālais atbalsts.'],[80,'Dziļa rezonanse — saprašanās bez vārdiem.'],[101,'Vienota emocionālā plūsma.']],
    values:    [[35,'Pretpoli — atšķirīgi dzīves virzieni.'],[50,'Kompromiss — iespējams ar apzinātu darbu.'],[65,'Līdzsvarots — galvenajā sakrīt.'],[80,'Vienots ceļš — kopīgs ilgtermiņa karkass.'],[101,'Viens vesels — kopīga dvēsele.']],
    interaction:[[35,'Sadursme — konflikti eskalē.'],[50,'Aizsardzība — bieži jāskaidrojas.'],[65,'Darba režīms — konstruktīva saziņa.'],[80,'Aktīva ieklausīšanās — ātri atrisina.'],[101,'Gandrīz telepātiska saziņa.']]
};
const descFor = (axis, v) => {
    const arr = AXIS_DESC[axis];
    return arr.find(([t]) => v <= t)?.[1] || arr[arr.length - 1][1];
};

// ── Galvenā eksporta funkcija ────────────────────────────────────────────────
export function calculateCompatConsensus(p1, p2, gender1 = 'M', gender2 = 'F', parihara = true) {
    if (!p1 || !p2) return null;

    // 1) Vēdiskā Ashta Kuta (makro) — atkārtoti izmanto esošo dzinēju.
    const kuta = calculateCompatibility(p1, p2, gender1, gender2, parihara);
    const vedic = kuta ? {
        system: 'vedic',
        axes: { emotional: kuta.emotional.moon, values: kuta.values.moon, interaction: kuta.interaction.moon },
        confidence: kuta.timeAveraged ? 0.7 : 0.8
    } : null;

    // 2–6) Pārējie balsotāji.
    const voters = {
        vedic,
        synastry:  calculateSynastry(p1, p2),
        composite: calculateComposite(p1, p2),
        bazi:      calculateBaziSynastry(p1, p2),
        psych:     calculatePairPsych(p1, p2),
        bigfive:   calculateBigFiveSynastry(p1, p2)
    };

    // ── Asu konsensa apvienošana ─────────────────────────────────────────────
    const axisResults = {};
    for (const axis of AXES) {
        let sum = 0, wsum = 0;
        const contribs = [];
        const vals = [];
        for (const [key, meta] of Object.entries(VOTERS)) {
            const v = voters[key];
            const pct = v?.axes?.[axis];
            const baseW = meta.w[axis];
            if (pct == null || baseW <= 0 || v?.confidence == null) continue;
            const effW = baseW * v.confidence;
            sum += pct * effW; wsum += effW;
            contribs.push({ system: key, icon: meta.icon, label: meta.label, name: meta.name, what: meta.what, short: meta.short, pct, weight: Math.round(effW * 100) / 100 });
            vals.push(pct);
        }
        const pct = wsum > 0 ? clamp100(Math.round(sum / wsum)) : null;

        // Vienprātība: zema standartnovirze starp balsotājiem → augsta pārliecība.
        let agreement = 1;
        if (vals.length >= 2) {
            const mean = vals.reduce((s, x) => s + x, 0) / vals.length;
            const sd = Math.sqrt(vals.reduce((s, x) => s + (x - mean) ** 2, 0) / vals.length);
            agreement = clamp100(100 - sd * 2.2) / 100;     // sd 0→1.0, sd~30→0.34
        } else if (vals.length === 1) {
            agreement = 0.6;
        }
        const confidence = pct == null ? 0 : Math.round(Math.min(0.95, (0.5 + 0.45 * agreement) * (vals.length >= 2 ? 1 : 0.8)) * 100) / 100;

        const sortedVoters = contribs.sort((a, b) => b.weight - a.weight);
        axisResults[axis] = {
            pct,
            title: AXIS_META[axis].title,
            question: AXIS_META[axis].q,
            level: levelWord(pct),
            color: levelColor(pct),
            desc: pct == null ? '—' : descFor(axis, pct),
            narrative: axisNarrative(axis, pct, sortedVoters),
            confidence,
            agreement: Math.round(agreement * 100) / 100,
            voters: sortedVoters,
            count: vals.length
        };
    }

    // ── Kopvērtējums ─────────────────────────────────────────────────────────
    let oSum = 0, oW = 0;
    for (const axis of AXES) {
        const r = axisResults[axis];
        if (r.pct == null) continue;
        oSum += r.pct * AXIS_WEIGHT[axis]; oW += AXIS_WEIGHT[axis];
    }
    const overallPct = oW > 0 ? clamp100(Math.round(oSum / oW)) : null;
    const tier = overallPct == null ? TIERS[TIERS.length - 1] : tierFor(overallPct);
    const overallConf = Math.round((AXES.reduce((s, a) => s + axisResults[a].confidence, 0) / AXES.length) * 100) / 100;

    // Makro (Vēdiskā Kuta) pret mikro (pārējās 4) — diverģences detektēšanai.
    const macroPct = kuta ? clamp100(Math.round((kuta.kutaScore / 36) * 100)) : null;
    const microVals = [];
    for (const key of ['synastry', 'composite', 'bazi', 'psych', 'bigfive']) {
        const v = voters[key];
        if (!v?.axes) continue;
        const avail = AXES.map(a => v.axes[a]).filter(x => x != null);
        if (avail.length) microVals.push(avail.reduce((s, x) => s + x, 0) / avail.length);
    }
    const microPct = microVals.length ? clamp100(Math.round(microVals.reduce((s, x) => s + x, 0) / microVals.length)) : null;

    const signals = buildSignals({ kuta, voters, macroPct, microPct, axisResults });

    // Kopvērtējuma cilvēciskais skaidrojums (bez žargona).
    const confWord = overallConf >= 0.75 ? 'augstu pārliecību' : overallConf >= 0.55 ? 'vidēju pārliecību' : 'piesardzīgu pārliecību';
    const overallPlain = overallPct == null ? '' :
        `Kopvērtējums apvieno 6 sistēmas. Ar <b>${confWord}</b> (jo vairāk sistēmu saskan, jo drošāks skaitlis) tās vērtē šo saderību kā <b style="color:${tier.color};">${tier.name.toLowerCase()}</b>. ` +
        (overallConf >= 0.7
            ? 'Sistēmas lielā mērā vienojas — uz šo vērtējumu var paļauties.'
            : 'Sistēmas daļēji nesaskan, tāpēc skatieties uz atsevišķajām asīm zemāk, ne tikai uz vienu skaitli.');

    return {
        overall: { pct: overallPct, tier: tier.name, color: tier.color, mark: tier.mark, confidence: overallConf, plain: overallPlain },
        axes: axisResults,
        macroPct, microPct,
        kutaScore: kuta?.kutaScore ?? null,
        timeAveraged: kuta?.timeAveraged ?? false,
        voters,
        signals,
        parihara
    };
}

// ── Signāli: saskaņa / nesaskaņa starp neatkarīgām sistēmām ───────────────────
function buildSignals({ kuta, voters, macroPct, microPct, axisResults }) {
    const out = [];
    const syn = voters.synastry, comp = voters.composite, bz = voters.bazi, ps = voters.psych;

    // 1) Spēcīgs Kuta makro, bet vāja sinastrijas ikdiena / Ascendentu berze.
    if (macroPct != null && macroPct >= 69 && syn) {
        const overlay = syn.overlay;
        const synEmo = syn.axes?.emotional;
        const friction = overlay && overlay.friction > overlay.bond;
        if (friction || (synEmo != null && synEmo < 45)) {
            out.push({ type: 'warn', icon: '⚠️',
                text: 'Spēcīgs enerģētiskais pamats (augsts Ashta Kuta), bet Rietumu sinastrija rāda ikdienas berzi — Ascendentu/planētu kontakti nesaskan ar Mēness saderību. Sadzīvē gaidāmi konflikti, ko makro-skaitlis neparāda.' });
        }
    }

    // 2) Spēcīga pievilkšanās (sinastrija), bet vājš kompozīta konteiners.
    if (syn?.axes?.emotional != null && syn.axes.emotional >= 65 &&
        comp?.axes?.values != null && comp.axes.values < 45) {
        out.push({ type: 'warn', icon: '🔥',
            text: 'Spēcīga sākotnējā ķīmija un pievilkšanās, bet vāja kompozīta struktūra — kaislei trūkst ilgtermiņa "konteinera". Pēc sākuma posma attiecībām vajadzēs apzinātu kopīgu karkasu, citādi tās izsīkst.' });
    }

    // 3) Piesaistes lamatas (trauksmains ↔ izvairīgs).
    if (ps?.detail?.attachFit != null && ps.detail.attachFit < 45) {
        out.push({ type: 'warn', icon: '🪢',
            text: `Piesaistes stilu atbilstība zema (${ps.detail.attachFit}%) — iespējamas "trauksmains↔izvairīgs" lamatas (${ps.detail.primary1 || '?'} ↔ ${ps.detail.primary2 || '?'}). Viens meklē tuvumu, otrs telpu; vajadzīgs apzināts komunikācijas darbs.` });
    }

    // 4) BaZi kontroles cikls — viens enerģētiski dominē.
    if (bz?.detail?.relation === 'ctrl_12' || bz?.detail?.relation === 'ctrl_21') {
        const who = bz.detail.relation === 'ctrl_12' ? '1. persona' : '2. persona';
        out.push({ type: 'info', icon: '☯',
            text: `BaZi kontroles cikls: ${who} enerģētiski dominē (${bz.detail.dm1} ↔ ${bz.detail.dm2}). Tas var izpausties kā kaisle un vilkme, bet arī kā spēka nelīdzsvarotība — apzināti jāuztur līdztiesība.` });
    }

    // 5) Nadi doša (tradicionāls brīdinājums) — no Kuta sadalījuma.
    const nadi = (kuta?.kutaBreakdown || []).find(k => k.name === 'Nadi');
    if (nadi && nadi.score === 0 && !nadi.neutralized) {
        out.push({ type: 'warn', icon: '🧬',
            text: 'Nadi doša (Vēdiskā tradīcija) — abiem vienāds enerģētiskais (Ādi/Madhya/Antya) tips. Klasiski uzskatīta par nopietnāko brīdinājumu (veselība, pēcnācēji). Mūsdienu praksē — norāde apzināti rūpēties par atjaunošanos un veselību, ne lāsts.' });
    }

    // 6) Big Five personības profils ievērojami atšķiras, kaut arī astroloģija saskan.
    const bf = voters.bigfive;
    if (bf?.detail?.overallSimilarity != null && bf.detail.overallSimilarity < 40 && macroPct != null && macroPct >= 60) {
        out.push({ type: 'info', icon: '🧬',
            text: `Personības profili (Big Five) ir diezgan atšķirīgi (${bf.detail.overallSimilarity}% līdzība), kaut arī astroloģiskā saderība ir laba — temperamenti var būt ļoti dažādi, kas prasa apzinātu pielāgošanos, nevis dabisku "sader kā atslēga uz slēdzeni".` });
    }

    // 7) Makro/mikro diverģence (sistēmas globāli nesaskan).
    if (macroPct != null && microPct != null && Math.abs(macroPct - microPct) > 22) {
        const hi = macroPct > microPct ? 'Vēdiskā (bioenerģētiskā bāze)' : 'Rietumu/psiholoģiskā (reālā dinamika)';
        out.push({ type: 'info', icon: '⚖️',
            text: `Sistēmas globāli nesaskan: ${hi} dod augstāku vērtējumu. Makro ${macroPct}% vs mikro ${microPct}%. Skatieties uz konkrētajām asīm zemāk, ne uz vienu kopskaitli.` });
    }

    // 8) Spēcīgs daudzsistēmu konsenss (visas asis labas + augsta vienprātība).
    const allGood = AXES.every(a => (axisResults[a]?.pct ?? 0) >= 62);
    const allAgree = AXES.every(a => (axisResults[a]?.agreement ?? 0) >= 0.7 && (axisResults[a]?.count ?? 0) >= 2);
    if (allGood && allAgree) {
        out.push({ type: 'good', icon: '🤝',
            text: 'Vairākas neatkarīgas sistēmas (Vēdiskā, Rietumu, BaZi, psiholoģija) saskan visās asīs — augsta pārliecība. Šis ir reti sastopams, stabils pamats.' });
    }

    if (!out.length) {
        out.push({ type: 'info', icon: 'ℹ️',
            text: 'Nav izteiktu nesaskaņu starp sistēmām — vērtējums ir līdzsvarots. Skatieties uz katras ass detaļām zemāk.' });
    }
    return out;
}
