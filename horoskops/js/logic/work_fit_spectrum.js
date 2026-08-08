// ── DARBA STILA SPEKTRS ───────────────────────────────────────────────────────
// Bipolāru "piemērotības asu" modelis darba devējam.
//
// Atšķirībā no vizuālā šāviena radara (kas mēra STIPRUMU — "cik daudz"),
// šis modelis mēra TIPU — "kurai lomai cilvēks der". Neviens polis nav
// "labāks"; tie ir vērtību-neitrāli piemērotības rādītāji intervijas sarunai.
//
// Datu avoti (visi jau aprēķināti pirms renderēšanas):
//   profile.personality   — Big Five + ~70 dimensijas
//   profile.leadership     — warmCharisma, allTypes (solo/specialist/visionary)
//   profile.careerAnchors  — kahneman.s1pct/s2pct, cynefin.profile
//
// RIASEC tiek pārrēķināts no Big Five (Larson et al. 2002), tāpat kā
// career_anchors.js un career_suggestions.js — vienota zinātniskā bāze.

// ── Big Five → RIASEC (replicēts, lai modulis būtu pašpietiekams) ────────────
function bigFiveToRiasec(O, C, E, A) {
    const clamp = v => Math.max(0, Math.min(100, Math.round(v)));
    return {
        R: clamp(C * 0.35 + (100 - O) * 0.35 + (100 - A) * 0.20 + (100 - E) * 0.10),
        I: clamp(O * 0.55 + C * 0.30 + (100 - E) * 0.15),
        A: clamp(O * 0.60 + (100 - C) * 0.30 + E * 0.10),
        S: clamp(E * 0.40 + A * 0.45 + (100 - C) * 0.15),
        E: clamp(E * 0.50 + C * 0.30 + (100 - A) * 0.20),
        C: clamp(C * 0.50 + (100 - O) * 0.35 + (100 - E) * 0.15)
    };
}

// Marķiera pozīcija starp diviem poliem: 0 = pilnībā kreisais, 100 = labais.
// Degenerātajā gadījumā (abi 0) → 50 (līdzsvars).
function split(leftScore, rightScore) {
    const total = leftScore + rightScore;
    return total > 0 ? Math.round((rightScore / total) * 100) : 50;
}

const avg = (...xs) => xs.reduce((a, b) => a + b, 0) / xs.length;
const cap = s => (s || '').charAt(0).toUpperCase() + (s || '').slice(1);

// Vērtību-neitrāls verdikta teikums atkarībā no marķiera pozīcijas.
// Izmanto darba piemērotības aprakstu kā pilnu predikātu — tā polu nosaukumi
// (UI etiķetes) var būt īsi un konkrēti, neietekmējot verdikta gramatiku.
function verdict(pos, leftWork, rightWork) {
    if (pos <= 28) return `${cap(leftWork)}.`;
    if (pos <= 44) return `${cap(leftWork)} — ar zināmu elastību.`;
    if (pos < 56)  return `Līdzsvars starp abiem poliem — pārslēdzas atkarībā no situācijas.`;
    if (pos < 72)  return `${cap(rightWork)} — ar zināmu elastību.`;
    return `${cap(rightWork)}.`;
}

// ── Galvenā eksportētā funkcija ───────────────────────────────────────────────

export function buildWorkFitSpectrum(profile) {
    const personality = profile?.personality || [];

    // Flatten visus traits → id → pct (tāpat kā career_anchors.js)
    const p = Object.fromEntries(
        personality.flatMap(cat => (cat.traits || []).map(t => [t.id, t.pct]))
    );
    const g = id => p[id] ?? 50;

    // Big Five no 'ocean' kategorijas (identiski career_anchors.js)
    const ocean = personality.find(cat => cat.id === 'ocean');
    const bf = {};
    if (ocean?.traits) for (const t of ocean.traits) bf[t.id] = t.pct;
    const O = bf.openness      ?? 50;
    const C = bf.conscient     ?? 50;
    const E = bf.extraversion  ?? 50;
    const A = bf.agreeableness ?? 50;

    const riasec = bigFiveToRiasec(O, C, E, A);

    // Augstāka līmeņa objekti ar drošiem fallback
    const leadership   = profile?.leadership || {};
    const warmCharisma = leadership.warmCharisma ?? 50;
    const allTypes     = leadership.allTypes || [];
    const ltScore      = key => allTypes.find(t => t.key === key)?.score ?? 50;

    const ca       = profile?.careerAnchors || {};
    const kahneman = ca.kahneman || {};
    const cynefin  = ca.cynefin?.profile || {};
    const s2pct    = kahneman.s2pct ?? 50;   // analītiskā sistēma
    const cyn      = key => cynefin[key] ?? 50;

    // ── 6 bipolāri spektri ────────────────────────────────────────────────────

    const spectrums = [];

    // 1. Praktiķis (lietišķs) ↔ Teorētiķis (konceptuāls)
    //    R (Realistic) pret I (Investigative); galvenais diferenciators = Openness
    spectrums.push({
        key: 'hands_head',
        left:  { icon: '🔧', label: 'Praktiķis',  color: '#FF8F45' },
        right: { icon: '🧠', label: 'Teorētiķis', color: '#7FB9E6' },
        pos: split(riasec.R, riasec.I),
        leftWork:  'vislabāk strādā ar taustāmu, lietišķu rezultātu — praktisku, uz reālu pielietojumu vērstu pieeju',
        rightWork: 'vislabāk strādā ar idejām, teoriju un analīzi — abstraktu, pētniecisku domāšanu'
    });

    // 2. Izpildītājs ↔ Stratēģis
    //    leadership specialist + Cynefin "kārtība" pret visionary + Cynefin "komplekss"
    spectrums.push({
        key: 'exec_strat',
        left:  { icon: '⚙️', label: 'Izpildītājs', color: '#B7C96A' },
        right: { icon: '🗺️', label: 'Stratēģis',   color: '#D6BEEA' },
        pos: split(avg(ltScore('specialist'), cyn('simple')), avg(ltScore('visionary'), cyn('complex'))),
        leftWork:  'uzplaukst skaidri definētos uzdevumos ar zināmu mērķi un drošu izpildi',
        rightWork: 'uzplaukst, redzot lielo bildi un veidojot virzienu, ne tikai izpildot'
    });

    // 3. Patstāvīgs ↔ Komandspēlētājs
    //    solo arhetips + independent iezīme pret sociālā/empātijas + siltā harizma
    spectrums.push({
        key: 'solo_team',
        left:  { icon: '🧍', label: 'Patstāvīgs', color: '#7FB9E6' },
        right: { icon: '👥', label: 'Komandā',    color: '#F98BA9' },
        pos: split(avg(ltScore('solo'), g('independent')), avg(g('social'), A, warmCharisma)),
        leftWork:  'sniedz labākos rezultātus, strādājot patstāvīgi un ar rīcības brīvību',
        rightWork: 'uzplaukst kopīgā darbā, kur enerģiju dod cilvēki un komandas mijiedarbība'
    });

    // 4. Strukturēts ↔ Adaptīvs
    //    apzinīgums + tradicionālisms + zems risks pret atvērtība + risks + radošums
    spectrums.push({
        key: 'struct_adapt',
        left:  { icon: '📐', label: 'Strukturēts', color: '#7FB9E6' },
        right: { icon: '🌊', label: 'Elastīgs',    color: '#FF8F45' },
        pos: split(avg(C, g('traditional'), 100 - g('risk')), avg(O, g('risk'), g('creativity'))),
        leftWork:  'vislabāk jūtas paredzamā vidē ar skaidriem procesiem un noteikumiem',
        rightWork: 'vislabāk jūtas mainīgā, dinamiskā vidē, kur var improvizēt un pielāgoties'
    });

    // 5. Cilvēki ↔ Dati & lietas (klasiskā People–Data–Things ass)
    //    RIASEC S+E + siltā harizma pret RIASEC I+R
    spectrums.push({
        key: 'people_data',
        left:  { icon: '🤝', label: 'Cilvēki', color: '#F98BA9' },
        right: { icon: '📊', label: 'Dati',    color: '#7FB9E6' },
        pos: split(avg(riasec.S, riasec.E, warmCharisma), avg(riasec.I, riasec.R)),
        leftWork:  'enerģiju gūst tiešā darbā ar cilvēkiem — klientiem, komandu un attiecībām',
        rightWork: 'enerģiju gūst darbā ar datiem, sistēmām un konkrētiem objektiem'
    });

    // 6. Intuitīvs ↔ Analītisks (Kahneman S1/S2 — jau aprēķināts)
    spectrums.push({
        key: 'intu_anal',
        left:  { icon: '🧭', label: 'Intuitīvs',  color: '#F4D77A' },
        right: { icon: '🔬', label: 'Analītisks', color: '#7FB9E6' },
        pos: s2pct,
        leftWork:  'lēmumus pieņem ātri, paļaujoties uz instinktu un pieredzi',
        rightWork: 'lēmumus pieņem pārdomāti, balstoties uz datiem un analīzi'
    });

    // Pievieno verdikta teikumu katram spektram
    for (const sp of spectrums) {
        sp.verdict = verdict(sp.pos, sp.leftWork, sp.rightWork);
    }

    return spectrums;
}
