// Stratēģiskā Laika Plānošana — Levinson + Biznesa dzīves cikls
// ──────────────────────────────────────────────────────────────
// Tāpat kā 1. sadaļā Junga arhetipi savieno personības elementus, šeit
// Daniela Levinsona dzīves gadalaiku teorija (1978) un klasiskais biznesa
// dzīves cikls (Introduction → Growth → Maturity → Pivot) ietērpj BaZi
// Luck Pillars un Vēdu Dasha datus stratēģiskā lēmumu pieņemšanas valodā.
//
// Astroloģiskie cikli šeit kalpo kā dzinējs, kas nosaka, kurā fāzē cilvēka
// iekšējais algoritms šobrīd atrodas. Sistēma izvada:
//
//   1) Makro horizonts — pašreizējais 10g BaZi pīlārs → biznesa fāze
//   2) Taktiskais logs — Liu Nian (šī gada stumbrs) → operatīvais fokuss
//   3) Lēmumu matrica — konkrēti DOs/DON'Ts
//   4) Turbulences zona — ±1 gads no pīlāra robežas (Jiao Yun pāreja)

import { calculateTenGods } from '../bazi.js?v=12';

// ── 10 stumbri (replicēti, lai izvairītos no cikliskās atkarības) ────────────

const STEMS = [
    { name: "Gui",  element: "Ūdens",  polarity: "Iņ"  }, // 0
    { name: "Jia",  element: "Koks",   polarity: "Jaņ" }, // 1
    { name: "Yi",   element: "Koks",   polarity: "Iņ"  }, // 2
    { name: "Bing", element: "Uguns",  polarity: "Jaņ" }, // 3
    { name: "Ding", element: "Uguns",  polarity: "Iņ"  }, // 4
    { name: "Wu",   element: "Zeme",   polarity: "Jaņ" }, // 5
    { name: "Ji",   element: "Zeme",   polarity: "Iņ"  }, // 6
    { name: "Geng", element: "Metāls", polarity: "Jaņ" }, // 7
    { name: "Xin",  element: "Metāls", polarity: "Iņ"  }, // 8
    { name: "Ren",  element: "Ūdens",  polarity: "Jaņ" }  // 9
];

// ── 5 biznesa fāzes — meta dati ──────────────────────────────────────────────

export const PHASE_META = {
    accumulation: {
        lv:      'Akumulācijas un Konsolidācijas fāze',
        en:      'Introduction · R&D',
        color:   '#38bdf8',
        icon:    '📚',
        summary: 'Iekšējās infrastruktūras, sistēmu un kompetences celšanas periods. Tirgus vēl negaida skaļu izpausmi — tas gaida sagatavotību',
        depth:   'Šajā dzīves gadalaikā iekšējais dzinējs ir vērsts uz kompetences dziļumu, datu un satura uzkrāšanu, sistēmu sakārtošanu fonā. Jebkurš mēģinājums forsēt ātru, virspusēju peļņu šobrīd atduras pret pretestību — kapacitāte vēl nav nogatavojusies publiskam izlaidumam. Stratēģiskais uzdevums ir būvēt savu neatkarīgo cietoksni no iekšienes'
    },
    growth: {
        lv:      'Agresīvas tirgus ekspansijas fāze',
        en:      'Growth · Scaling',
        color:   '#22c55e',
        icon:    '📈',
        summary: 'Augstākais monetizācijas, mērogošanas un tiešas materiālās realizācijas potenciāls. Resursi plūst pie tā, kurš tos prasa skaļi un ar struktūru',
        depth:   'Šajā fāzē biznesa loģika prevalē pār iekšējo perfekcionismu. Resursi atbalsta agresīvu pārdošanu, jaunu struktūru dibināšanu un kapitāla piesaisti. Pārmērīgi ilga "vēl viens slīpējums" pieeja šajā logā ir lielākais zaudējums — tirgus loga ekspansijas periods ir ierobežots, un nākamie 10 gadi prasīs citu fokusu'
    },
    maturity: {
        lv:      'Institucionālās vadības un Atbildības fāze',
        en:      'Maturity · Authority',
        color:   '#a855f7',
        icon:    '🏛',
        summary: 'Karjeras vai sabiedriskās ietekmes virsotne — valdes amati, korporatīvā vai strukturālā vara, reputācijas nostiprināšana',
        depth:   'Šajā gadalaikā uzkrātās zināšanas un resursi konvertējas par formālu autoritāti. Sistēma atbalsta amatu pacēlumu, juridisko un strukturālo lietu sakārtošanu, ilgtermiņa komandu vadību. Pretošanās formalitātēm vai "solo" eksperimentiem šajā fāzē atstāj resursus uz galda — institucionālā loma ir tas, ko fāze sūta'
    },
    pivot: {
        lv:      'Radošā pivota un Inovāciju fāze',
        en:      'Pivot · Renewal',
        color:   '#f59e0b',
        icon:    '🔄',
        summary: 'Brīvības, nestandarta ideju un autorprojektu laiks — bieži lauž vecos rāmjus, bet rada jaunu identitāti',
        depth:   'Šī fāze sūta enerģiju radošumam, eksperimentiem un savu projektu radīšanai. Vecās struktūras (lielas korporācijas, hierarhiskas lomas) sāk justies kā šaurs apavs. Iespēja ir izveidot autorprojektu, frīlanss praksi vai pilnīgi jaunu nišu. Pretestība pivotam šajā fāzē izpaužas kā vidējās vecuma krīze vai dziļš profesionālais "burnout"'
    },
    alliance: {
        lv:      'Sadarbības un Sāncensības fāze',
        en:      'Alliance · Joint Ventures',
        color:   '#ec4899',
        icon:    '🤝',
        summary: 'Periods, kad rezultāti rodas caur citiem — kopdarbībā, partnerībās, līdzdibinātāju attiecībās; solo cīņa nogurdina',
        depth:   'Šajā fāzē individuālā kapacitāte ir paaugstināta, bet visdziļāk realizējas caur aliansēm un partnerībām. Resursi piesaista konkurentus un sabiedrotos vienlīdz — tas ir laiks veidot komandas, dalīt kopējus īpašumus un piedalīties sportiskā konkurencē. Pārmērīgs individuālisms šajā fāzē strādā pret viņu pašu'
    }
};

// ── 10 Dievs → biznesa fāze ──────────────────────────────────────────────────

const GOD_TO_PHASE = {
    Direct_Resource:   'accumulation',
    Indirect_Resource: 'accumulation',
    Direct_Wealth:     'growth',
    Indirect_Wealth:   'growth',
    Direct_Officer:    'maturity',
    Seven_Killings:    'maturity',
    Eating_God:        'pivot',
    Hurting_Officer:   'pivot',
    Friend:            'alliance',
    Rob_Wealth:        'alliance'
};

// ── DO / DON'T matrica katrai fāzei ──────────────────────────────────────────

export const PHASE_MATRIX = {
    accumulation: {
        dos: [
            'Investīcijas zināšanās, sertifikācijās un padziļinātās apmācībās',
            'Sistēmu, datu bāzu un procesu automatizācija "fonā"',
            'Mentora vai konsultanta atrašana augstā līmenī',
            'Ilgtermiņa pētniecības, satura un metodikas izstrāde',
            'Klusa neatkarīgā cietokšņa uzbūve — savs zīmols, savas zināšanas'
        ],
        donts: [
            'Agresīva publiska ekspansija bez gatava piedāvājuma',
            'Skaļa monetizācija no idejām, kas vēl atrodas R&D fāzē',
            'Spekulatīvas investīcijas, ko motivē FOMO',
            'Vairāku jaunu projektu sākšana paralēli — fokuss izšķīst',
            'Ātri pārdošanas projekti, kas izsmeļ enerģiju no būvniecības'
        ]
    },
    growth: {
        dos: [
            'Aktīva pārdošana, mērogošana un komandas paplašināšana',
            'Jaunu uzņēmumu, produktu vai struktūru dibināšana',
            'Kapitāla piesaiste — investori, kredīti vai stratēģiski partneri',
            'Tiešas monetizācijas projekti — augstas peļņas piedāvājumi',
            'Tirgus pozīcijas stiprināšana caur tiešu konfrontāciju ar konkurentiem'
        ],
        donts: [
            'Pārmērīgi ilga plānošana un perfektionisma slazdi',
            'Resursu izšķiešana teorētiskos pētījumos vai bezgalīgos R&D ciklos',
            'Emocionāla iesaiste klientos vai nodalīšanās no biznesa loģikas',
            '"Gaidīt īstos apstākļus" — šis logs ir ierobežots',
            'Pārmērīgi konservatīva pieeja, kas baidās izmantot atbalstošo fonu'
        ]
    },
    maturity: {
        dos: [
            'Vadošu amatu un valdes pozīciju pieņemšana',
            'Sistēmu, kvalitātes kontroles un kompliances nostiprināšana',
            'Reputācijas un autoritātes formāla atzīšana — apbalvojumi, publikācijas',
            'Juridiskā, strukturālā un korporatīvās pārvaldības sakārtošana',
            'Komandu vadīšana, mentorēšana un nākamās paaudzes audzināšana'
        ],
        donts: [
            'Solo radoši eksperimenti, kas atklāj nestabilitāti',
            'Autoritāšu izaicināšana bez gatava plāna vai sabiedrotajiem',
            'Korporatīvo protokolu apiešana — reputācija ir uz spēles',
            'Iesaiste jaunās, nepārbaudītās nišās bez stratēģiskā atbalsta',
            'Personīgās emocionālās ievainojamības publiska atklāšana'
        ]
    },
    pivot: {
        dos: [
            'Neatkarīgi autorprojekti — grāmatas, kursi, savs zīmols',
            'Frīlanss prakse vai konsultāciju biznesa modeļa testēšana',
            'Jaunu nišu un nekonvencionālu risinājumu izstrāde',
            'Eksperimenti ar formātu, tehnoloģijām un produkta uztveri',
            'Karjeras maiņa vai būtisks pivots, ja iekšējais signāls ir skaidrs'
        ],
        donts: [
            'Korporatīvās hierarhijas spēlēšana caur konformitāti',
            'Garas ilgtermiņa saistības (10+ gadu kontrakti, hipotēkas)',
            'Rutīnas darbs, kas pieprasa identitātes apspiešanu',
            'Pretošanās radošajam impulsam ar "drošības" izvēli',
            'Loma, kur radošumu pieprasa lielas iekšējās politikas, ne pats produkts'
        ]
    },
    alliance: {
        dos: [
            'Stratēģisko alianses, partnerības un līdzdibinātāju meklēšana',
            'Aktīva tīklošana un industriju kopienu veidošana',
            'Kopējo resursu un kopīpašuma struktūru izstrāde',
            'Sportiska konkurence kā motivācijas mehānisms',
            'Investīcijas attiecībās ar līdziniekiem — viņi būs nākotnes vārtu sargi'
        ],
        donts: [
            'Solo darbs bez atbalsta sistēmas',
            'Finansiālie konflikti ar tuviniekiem, brāļiem vai biznesa partneriem',
            'Pārmērīga azartspēle ar kopējiem resursiem',
            'Atklāta sāncensība ar līdziniekiem, kas atbaida sabiedrotos',
            'Sevis pārvērtēšana, kas atgrūž tos, kam šis cilvēks būtu vajadzīgs'
        ]
    }
};

// ── Liu Nian — šī gada BaZi stumbra aprēķins ─────────────────────────────────

function getYearStem(year) {
    // BaZi gada nomaiņa notiek pie Lichun (~Feb 4). Pirms šī datuma jāņem
    // iepriekšējais gads.
    const now = new Date();
    const month = now.getMonth() + 1;
    const day   = now.getDate();
    const isPreLichun = (month === 1) || (month === 2 && day < 4);
    const baziYear = isPreLichun ? year - 1 : year;
    let idx = (baziYear - 3) % 10;
    if (idx < 0) idx += 10;
    return STEMS[idx];
}

// ── Pīlāra meklēšana pēc vecuma + transīta zonas detekcija ───────────────────

function findCurrentPillar(pillars, age) {
    if (!Array.isArray(pillars) || age == null) return null;
    return pillars.find(lp => age >= (lp.ageStart ?? 0) && age <= (lp.ageEnd ?? 0)) || null;
}

function detectTransitionZone(pillar, age) {
    if (!pillar) return { active: false };
    const yearsSinceStart = age - (pillar.ageStart ?? age);
    const yearsToEnd      = (pillar.ageEnd ?? age) - age;
    if (yearsSinceStart <= 1) {
        return {
            active: true,
            kind: 'entering',
            description: 'Profils tikko ieiet jaunajā 10 gadu fāzē. Vecais algoritms vairs nenes gandarījumu, jaunais vēl nav nostabilizējies'
        };
    }
    if (yearsToEnd <= 1) {
        return {
            active: true,
            kind: 'exiting',
            description: 'Profils tuvojas pašreizējās fāzes beigām. Jaunās fāzes signāli jau parādās, bet vecā loģika joprojām velk pretī'
        };
    }
    return { active: false };
}

// ── Šeina enkurs × Biznesa fāze: saskaņojuma un konflikta matrica ────────────

const ANCHOR_PREFERRED_PHASES = {
    technical:       ['accumulation', 'pivot'],
    management:      ['maturity', 'growth'],
    autonomy:        ['pivot', 'alliance'],
    security:        ['accumulation', 'maturity'],
    entrepreneurial: ['growth', 'pivot'],
    service:         ['maturity', 'alliance'],
    challenge:       ['pivot', 'growth'],
    lifestyle:       ['accumulation', 'maturity']
};

const ANCHOR_CONFLICT_PHASES = {
    technical:       [],
    management:      ['pivot'],
    autonomy:        ['maturity'],
    security:        ['pivot'],
    entrepreneurial: ['accumulation'],
    service:         [],
    challenge:       ['accumulation'],
    lifestyle:       ['pivot']
};

// Specifiski stratēģiskie brīdinājumi katram konfliktējošam pārim
const ANCHOR_PHASE_CONFLICTS = {
    'entrepreneurial:accumulation': {
        instinct:    'nepārtraukti inovēt un riskēt jaunās tirgus zonās',
        demand:      'iekšējo infrastruktūru un mācīšanos — sistēmu, satura un kompetences celšanu fonā',
        warning:     'Ja viņš mēģinās realizēt uzņēmēja dzinēju tieši, neuzbūvējot pamatus, sekos masīva pretestība',
        alternative: 'inovēt "savā garāžā" — testēt prototipus un būvēt zināšanu bāzi — nevis iekarot tirgu skaļi'
    },
    'autonomy:maturity': {
        instinct:    'darboties pēc saviem noteikumiem, ārpus hierarhijas',
        demand:      'institucionālu lomu, atbildību par citiem un formālu autoritāti',
        warning:     'Ja viņš turpinās izvairīties no struktūras un valdes lomas, šīs fāzes resursi paliks neizmantoti',
        alternative: 'veidot ietekmi caur ekspertīzi institūcijā — pieņemt vadošu amatu ar saglabātu rīcības brīvību — nevis bēgt no struktūras vispār'
    },
    'security:pivot': {
        instinct:    'turēties pie stabilitātes, paredzamības un esošās drošības zonas',
        demand:      'radikālu pārmaiņu, identitātes pārkārtošanu un veco struktūru izaicināšanu',
        warning:     'Ja viņš turpinās turēties pie vecās drošības, šis cikls to tik un tā izjauks — bet jau bez viņa kontroles',
        alternative: 'izmantot pārmaiņu laiku, lai pārdomātu, kas tieši ir viņa jaunā "drošā osta" — pievērsties iekšējai drošībai, nevis ārējām struktūrām'
    },
    'management:pivot': {
        instinct:    'uzturēt hierarhiju, procesus un institucionālo kārtību',
        demand:      'eksperimentus, jaunas pieejas un iekšēju identitātes pārkārtošanu',
        warning:     'Ja viņš turpinās vadīt pa vecam, jaunās paaudzes komandas viņu vairs neuztvers kā autoritāti',
        alternative: 'vadīt pivotu pats — kļūt par tās institūcijas reformētāju, ne sargātāju — eksperimentu un inovāciju vadītāju'
    },
    'challenge:accumulation': {
        instinct:    'meklēt ārējos izaicinājumus, krīzes un konfrontācijas',
        demand:      'iekšējo darbu — mācīšanos, sistēmu būvi un kompetences padziļināšanu',
        warning:     'Ja viņš meklēs konfliktus ārpusē, šī fāze to pašu konfliktu parādīs iekšienē',
        alternative: 'pieņemt izaicinājumu kā padziļinātu meistarību — uzsākt grādu, sertifikāciju vai 1000 stundu praksi — nevis ārējas cīņas'
    },
    'lifestyle:pivot': {
        instinct:    'saglabāt esošo darba-dzīves balansu un izvairīties no pārslodzes',
        demand:      'identitātes pārkārtošanu, kas neizbēgami sajauks darbu un personīgo dzīvi',
        warning:     'Ja viņš mēģinās noturēt balansu neskartu, fāzes spiediens to tik un tā izjauks — bet jau bez viņa vadības',
        alternative: 'apzināti pieņemt pivota intensitāti uz 12–18 mēnešiem ar skaidru beigu datumu — laika robežotais haoss ir vadāmāks par nekontrolētu'
    }
};

function detectAnchorAlignment(careerAnchors, phaseKey) {
    if (!careerAnchors?.primary?.key || !phaseKey) return null;
    const anchorKey = careerAnchors.primary.key;
    const preferred = ANCHOR_PREFERRED_PHASES[anchorKey] || [];
    const conflict  = ANCHOR_CONFLICT_PHASES[anchorKey] || [];

    if (conflict.includes(phaseKey)) {
        const detail = ANCHOR_PHASE_CONFLICTS[`${anchorKey}:${phaseKey}`] || null;
        return {
            status:    'conflict',
            anchorKey,
            phaseKey,
            anchorLv:  careerAnchors.primary.lv,
            detail
        };
    }
    if (preferred.includes(phaseKey)) {
        return {
            status:    'aligned',
            anchorKey,
            phaseKey,
            anchorLv:  careerAnchors.primary.lv,
            narrative: `Šī ir ideāla saskaņa — profila Šeina enkurs <b>${careerAnchors.primary.lv}</b> dabiski plūst kopā ar pašreizējo fāzi. Šajā logā vislabāk realizēt primāro dzinēju, jo gan iekšējais kompass, gan ārējais konteksts rāda vienā virzienā`
        };
    }
    return {
        status:    'neutral',
        anchorKey,
        phaseKey,
        anchorLv:  careerAnchors.primary.lv,
        narrative: `Profila Šeina enkurs <b>${careerAnchors.primary.lv}</b> ne pilnībā saskan, ne pilnībā konfliktē ar pašreizējo fāzi. Šajā logā jāstrādā ar abām dimensijām paralēli — enkurs paliek galvenais virziens, fāze nodrošina kontekstu`
    };
}

// ── Galvenā eksportējamā funkcija ────────────────────────────────────────────

export function calculateTimingStrategy(profile, careerAnchors = null) {
    const bazi       = profile?.bazi || {};
    const pillars    = bazi.luck_pillars || [];
    const daymaster  = bazi.Daymaster;
    const birthDateStr = profile?.birth_info?.date;

    // Pašreizējais vecums
    const age = birthDateStr
        ? Math.floor((Date.now() - new Date(birthDateStr).getTime()) / (365.25 * 86400000))
        : null;

    // ── Makro horizonts ──────────────────────────────────────────────────────
    const currentPillar = findCurrentPillar(pillars, age);
    const pillarGod     = currentPillar?.interpretation?.godKey || null;
    const phaseKey      = pillarGod ? GOD_TO_PHASE[pillarGod] : null;
    const phaseMeta     = phaseKey ? PHASE_META[phaseKey] : null;
    const transition    = detectTransitionZone(currentPillar, age);

    // ── Visu 5 fāžu distribūcijas skori (HR salīdzinājuma datu nesēji) ─────
    // Aprēķins balstās uz BaZi 10 dievu klātbūtnes profilā pa visiem pīlāriem
    // (kā tendences svari), kas tiek agregēti pa fāzes kategorijām.
    const allPhaseScores = (() => {
        const counts = { accumulation: 0, growth: 0, maturity: 0, pivot: 0, alliance: 0 };
        const godCounts = {};
        for (const p of pillars) {
            const g = p?.interpretation?.godKey;
            if (g) godCounts[g] = (godCounts[g] || 0) + 1;
        }
        // Pamatdiens: pilāru kopa atspoguļo cilvēka kopējo fāžu raksturu
        const total = Object.values(godCounts).reduce((s, n) => s + n, 0) || 1;
        for (const [g, n] of Object.entries(godCounts)) {
            const phase = GOD_TO_PHASE[g];
            if (phase) counts[phase] += n;
        }
        // Pārveido uz % un piešķir pašreizējam pīlāram bonus
        const scores = {};
        for (const [phase, n] of Object.entries(counts)) {
            let pct = Math.round((n / total) * 100);
            if (phase === phaseKey) pct = Math.min(100, pct + 25); // pašreizējās fāzes bonus
            scores[phase] = pct;
        }
        return scores;
    })();

    const macro = currentPillar && phaseMeta ? {
        phaseKey,
        phaseMeta,
        pillarGod,
        pillarLabel:   `${currentPillar.stem?.name || '—'} ${(typeof currentPillar.branch === 'string' ? currentPillar.branch.split(' ')[0] : (currentPillar.branch?.name || '—'))}`,
        ageStart:      currentPillar.ageStart,
        ageEnd:        currentPillar.ageEnd,
        currentAge:    age,
        transition,
        allPhaseScores,
        allPhaseMeta:  PHASE_META
    } : null;

    // ── Taktiskais logs — Liu Nian ───────────────────────────────────────────
    const currentYear = new Date().getFullYear();
    const yearStem    = getYearStem(currentYear);
    const liuNianGod  = daymaster ? calculateTenGods(daymaster, yearStem) : null;
    const liuNianPhaseKey = liuNianGod ? GOD_TO_PHASE[liuNianGod] : null;
    const liuNianPhase    = liuNianPhaseKey ? PHASE_META[liuNianPhaseKey] : null;

    const isAlignedWithMacro = phaseKey && liuNianPhaseKey && phaseKey === liuNianPhaseKey;

    const tactical = liuNianGod && liuNianPhase ? {
        year:           currentYear,
        yearStem:       yearStem.name,
        polarity:       yearStem.polarity,
        element:        yearStem.element,
        liuNianGod,
        phaseKey:       liuNianPhaseKey,
        phaseMeta:      liuNianPhase,
        alignedWithMacro: isAlignedWithMacro,
        narrative: isAlignedWithMacro
            ? `Šis gads pastiprina 10 gadu fāzes loģiku — abas sistēmas vienlaicīgi rāda atbalstošu fonu vienam un tam pašam virzienam. Šaurā loga iespējas ir augstas, ja rīkojas tagad`
            : `Šis gads iedod īslaicīgu, bet jaudīgu operatīvo logu ar citu fokusu nekā lielais 10 gadu cikls. Šie 12 mēneši izmantojami kā taktisks ieguldījums, nezaudējot makro stratēģiju`
    } : null;

    // ── Lēmumu matrica ───────────────────────────────────────────────────────
    const matrix = phaseKey ? PHASE_MATRIX[phaseKey] : null;

    // ── Šeina enkurs × pašreizējā fāze: saskaņojuma audits ───────────────────
    const anchorAlignment = detectAnchorAlignment(careerAnchors, phaseKey);

    return { macro, tactical, matrix, anchorAlignment };
}
