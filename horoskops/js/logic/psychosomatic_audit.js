// Psihosomatikas un Izdegšanas Audits
// ─────────────────────────────────────
// Tāpat kā citas akadēmiskās sadaļas, šeit astroloģiskie dati netiek
// izmantoti, lai diagnosticētu vīrusus vai audzējus, bet gan lai
// nolasītu individuālo STRESA UZKRĀŠANĀS ARHITEKTŪRU.
//
// Pamata ietvars — Besela van der Kolka princips "The Body Keeps the
// Score" (2014): ķermenis atceras to, ko prāts vēlas aizmirst, un
// uzkrātā nepārstrādātā psiholoģiskā slodze izpaužas fiziski.
//
// Šajā audita rīkā nav medicīnisku diagnožu — tikai stresa reakciju
// arhitektūra, somatizācijas marķieri un atjaunošanās protokols.
//
// Output ir 4 secīgi bloki:
//   A. Akūtais stresa indikators  — kā ķermenis šodien kliedz "Stop"
//   B. Hroniskās izdegšanas profils — kur somatizējas ilgtermiņa slodze
//   C. Enerģijas noplūdes zonas    — kur dienas resursi nemanāmi iztek
//   D. Atjaunošanās un Stabilitātes protokols — kā atjaunot sistēmu

// ── BaZi 5 elementu psihosomatika (galvenā kompensācijas vārdnīca) ──────────

export const BAZI_PSYCHOSOMATICS = {
    Koks: {
        element: 'Koks · Dzinējs un Robežas',
        color:   '#22c55e',
        excess:      'Stresa apstākļos ieslēdzas "hiperfokusa spazma". Cilvēks nespēj apstāties, kļūst pārmērīgi kontrolējošs un izrāda asu neiecietību pret citu lēnumu. Somatizējas kā muskuļu saspringums, īpaši kakla un plecu daļā, kā arī žokļa sakošana (bruksisms)',
        deficiency:  'Kritiskas problēmas ar personīgo robežu nospraušanu. Cilvēks zem spiediena izsīkst, nespējot pateikt "Nē". Somatizējas kā hronisks nogurums no citu cilvēku problēmu risināšanas un iekšējas, neizpaustas dusmas',
        compensation:'Fiziska kustība bez mērķa (piemēram, pastaiga, nevis sacensību sports), lai atbrīvotu uzkrāto agresiju un atjaunotu elastību lēmumu pieņemšanā'
    },
    Uguns: {
        element: 'Uguns · Aizrautība un Nervu Sistēma',
        color:   '#ef4444',
        excess:      'Izdegšana caur māniju. Stresa brīžos cilvēks uzņemas arvien jaunus projektus, mēģinot "aizbēgt" no spriedzes caur pārmērīgu aktivitāti un socializēšanos. Somatizējas kā sirdsklauves, trauksme, bezmiegs un pilnīga nervu sistēmas pārslodze',
        deficiency:  'Apātija un emocionālais "vakuums". Zūd jebkāda motivācija un dzīvesprieks, iestājas emocionāls vēsums. Somatizējas kā pazemināts asinsspiediens, pastāvīga salšana un nespēja "aizdegties" par jauniem mērķiem',
        compensation:'Stingra informatīvā un sociālā diēta. Atjaunošanās notiek pilnīgā klusumā un distancē no citu cilvēku drāmām, ļaujot nervu sistēmai atdzist'
    },
    Zeme: {
        element: 'Zeme · Stabilitāte un Informācijas Apstrāde',
        color:   '#f59e0b',
        excess:      'Ruminācija jeb mentālā iestrēgšana. Stresa brīžos prāts nepārtraukti maļ vienu un to pašu problēmu bez risinājuma. Somatizējas gremošanas traktā — ķermenis burtiski "nesagremo" informāciju un vidi, radot smaguma sajūtu un letarģiju',
        deficiency:  'Bāzes drošības zudums. Cilvēks jūtas "atrauts no zemes", izkliedēts un nespēj novest lietas līdz galam. Somatizējas kā trauksmaina skraidīšana no viena uzdevuma pie cita un nespēja nostiprināt pamatus',
        compensation:'Rutīnas un mikrokontroles ieviešana. Atjaunošanās prasa fizisku saikni ar realitāti — darbs ar rokām, kulinārija, dārzkopība vai precīzs dienas režīms, kas atgriež kontroles sajūtu'
    },
    Metāls: {
        element: 'Metāls · Struktūra un Sēras',
        color:   '#94a3b8',
        excess:      'Perfekcionisma paralīze un stīvums. Krīzes brīžos kļūst ārkārtīgi kategorisks un neelastīgs, mēģinot kontrolēt haosu caur dzelžainiem noteikumiem. Somatizējas kā elpošanas seklums (aizturēta elpa), ādas problēmas un nespēja adaptēties pārmaiņām',
        deficiency:  'Iekšējās struktūras un disciplīnas sabrukums. Grūtības uzturēt kārtību un novest projektus līdz finišam. Cilvēks kļūst pārāk padevīgs un nespēj "atgriezt" to, kas vairs nestrādā',
        compensation:'Atbrīvošanās tehnikas. Enerģija atjaunojas, kad tiek apzināti "izmests" vai pārtraukts tas, kas vairs nekalpo — sākot no telpas sakārtošanas līdz nomācošu saistību laušanai'
    },
    Ūdens: {
        element: 'Ūdens · Griba un Eksistenciālā Drošība',
        color:   '#3b82f6',
        excess:      'Eksistenciālas bailes un pārmērīga piesardzība. Cilvēks paralizē pats sevi ar scenārijiem "kas būtu, ja". Somatizējas kā nieru un virsnieru pārslodze (adrenalin fatigue), hroniskas bailes un vēlme pilnībā izolēties no pasaules',
        deficiency:  'Izdzīvošanas dziņas un gribasspēka trūkums. Tiek pazaudēts dziļākais dzinējspēks un drosme stāties pretī nezināmajam. Somatizējas kā dziļš, kaulos jūtamams nogurums un nespēja atjaunot resursus pat pēc ilgstoša miega',
        compensation:'Dziļa, nepārtraukta miera stāvokļa nodrošināšana. Atjaunošanās prasa atteikšanos no jebkādiem termiņiem un ilgstošu uzturēšanos plūstošā, nenoslogotā vidē (ideāli pie dabas vai ūdens)'
    }
};

// ── A. Akūtais stresa indikators (6. mājas iemītnieki) ──────────────────────

const HOUSE_6_ACUTE_SIGNALS = {
    Saule: {
        signal:   'Ego-stress un identitātes pārpilde',
        soma:     'Galvassāpes (it sevišķi pie pieres), acu spiediens, vajadzība pēc pilnīgas atzīšanas pirms atpūtas',
        trigger:  'Sistēma signalizē "Stop", kad viņš pārāk ilgi bijis publiskās uzmanības centrā vai atkārtoti pierādījis sevi'
    },
    Mēness: {
        signal:   'Emocionālā pārplūšana un miega fragmentācija',
        soma:     'Pamosties nakts vidū ar nemierīgām domām, neapstrādātas emocijas izpaužas gremošanas traktā vai apetītes svārstībās',
        trigger:  'Sistēmas brīdinājuma signāls — kad viņš emocionāli ielogojas citu cilvēku stāvokļos un zaudē pats sevi'
    },
    Marss: {
        signal:   'Adrenalin-vadīts darba režīms un muskuļu spriedze',
        soma:     'Žokļa sakošana (bruksisms), muguras lejasdaļas saspringums, augstas sirdsklauves arī bez fiziskās slodzes',
        trigger:  'Brīdinājuma signāls — kad viņš paralēli uztur konfliktu vai konkurenci vairākos virzienos, neatlaižot nevienu'
    },
    Merkurs: {
        signal:   'Nervu sistēmas pārslodze — kognitīvā saspringšana',
        soma:     'Raustīgas domas, miega fragmentācija, nespēja izslēgt iekšējo monologu, acu nogurums, paaugstināts jutīgums pret ekrāniem',
        trigger:  'Sistēmas signāls — kad informācijas plūsma pārsniedz viņa apstrādes kapacitāti, pat ja prāts saka "es varu vēl"'
    },
    Jupiters: {
        signal:   'Pārmērīga uzņemšanās — "pārāk daudz jā" sindroms',
        soma:     'Svara pieaugums, gremošanas saspringums, miegainība pēc maltītēm, vēlme pēc cukura un alkohola',
        trigger:  'Brīdinājuma signāls — kad viņš apņēmies lielāku darbu, nekā viņa resursi spēj uzturēt'
    },
    Venera: {
        signal:   'Sociālo un attiecību stresu somatizācija',
        soma:     'Ādas reakcijas (izsitumi, sausa āda), apetītes svārstības, vēlme pēc komforta caur ēdienu vai vīnu',
        trigger:  'Sistēmas signāls — kad attiecības tiek pārāk ilgi uzturētas bez patiesas savstarpējības'
    },
    Saturns: {
        signal:   'Hroniska aizturēšanās — strukturāls saspringums',
        soma:     'Muguras un kakla saspringums, locītavu nogurums, miega fāzu deficīts, kaulu un zobu jutīgums',
        trigger:  'Brīdinājuma signāls — kad viņš ilgstoši nes atbildību bez atbalsta un bez gala datuma'
    },
    Rahu: {
        signal:   'Obsesīvā pārslodze — kortizola pārpalikums',
        soma:     'Augsts sirdsritms naktī, virsnieru izsmelšanās simptomi, neapzināta steiga arī mierīgās situācijās',
        trigger:  'Sistēmas signāls — kad viņš dzenas pēc kaut kā tāla, neapzinoties, ka paša sistēma jau ir pārslogota'
    },
    Ketu: {
        signal:   'Disociācija un izslēgšanās signāli',
        soma:     '"Brain fog", distancēšanās no sava ķermeņa, nogurums bez identificējama avota, vēlme pēc izolācijas',
        trigger:  'Brīdinājuma signāls — kad viņš pārāk ilgi atrodas kontekstā, kas viņam šķiet garīgi tukšs vai bezmērķīgs'
    }
};

// ── B. Hroniskās izdegšanas profils (8. mājas iemītnieki) ───────────────────

const HOUSE_8_CHRONIC_PATTERNS = {
    Saule: {
        pattern: 'Identitātes erozija caur hronisku pielāgošanos',
        soma:    'Aiztur galvenā ego signāli — iniciatīvas zudums, sajūta "es vairs nezinu, kas esmu", autoimūnas tendences',
        depth:   'Ilgstoša pakļaušanās ārējiem standartiem (priekšnieks, ģimene, sabiedrība) bez savas balss aizstāvēšanas vada uz dziļu identitātes krīzi'
    },
    Mēness: {
        pattern: 'Emocionālā transformācija caur ciklisku iekšēju krīzi',
        soma:    'Ciklisks nogurums, hormonālie spārni, hroniskas miega kvalitātes problēmas, ēdiena ritma traucējumi',
        depth:   'Neapstrādāta emocionāla slodze (it sevišķi no agrīnās bērnības vai mātes attiecībām) uzkrājas un periodiski "izverd" caur ķermeni'
    },
    Marss: {
        pattern: 'Agresijas iekšiena — pavērsta uz sevi',
        soma:    'Autoimūnas reakcijas, hroniskas iekaisuma tendences, pārmērīga adrenalīna izdalīšana, panikas epizodi',
        depth:   'Neizpaustā dusma, kas gadiem tiek apspiesta, sāk uzbrukt paša ķermenim — sistēma sāk uzskatīt sevi par ienaidnieku'
    },
    Merkurs: {
        pattern: 'Mentālā fiksācija un kognitīvā nogurums',
        soma:    'Hronisks nervu nogurums, atmiņas pasliktināšanās, valodas izpausmes traucējumi stresa brīžos',
        depth:   'Pārmērīga mentālā darba bez pauzes uztverošā perioda vada uz kognitīvās baterijas dziļu izsmelšanos'
    },
    Jupiters: {
        pattern: 'Filozofiska krīze — zaudētas pārliecības',
        soma:    'Svara pieaugums (pretēji metabolismam), aknu pārslodzes signāli, vispārējs "uzpūsts" stāvoklis',
        depth:   'Hronisks "es zinu labāk" sindroms, kas neļauj pieņemt jaunu informāciju, beidzas ar dziļu eksistenciālu vilšanos'
    },
    Venera: {
        pattern: 'Attiecību transformācija caur dziļiem zaudējumiem',
        soma:    'Sirds-cirkulācijas reakcijas, ādas problēmas, hormonālie traucējumi, hroniska tukšuma sajūta',
        depth:   'Atkārtots tuvības zaudējuma cikls (kompulsīva atkārtošana) raksturīga attiecību traumai, kas nav apzināti pārstrādāta'
    },
    Saturns: {
        pattern: 'Strukturāla apātija — depresijas spirāle',
        soma:    'Depresīvi epizodi, hroniskā nespēka cikls, kaulu un locītavu spēka zudums, smaguma sajūta visā ķermenī',
        depth:   'Ilgstoši nesta atbildības slodze bez pauzes vai prieka momenta vada uz dziļu eksistenciālu apātiju, kas izskatās kā depresija'
    },
    Rahu: {
        pattern: 'Apsēstības zona un kompulsīvi cikli',
        soma:    'Atkarību tendences (vielu, attiecību, darba), hormonālie pīķi, hroniskas trauksmes epizodi',
        depth:   'Neapzinātā kāre pēc kaut kā tālu noslāpē sevis-uzturēšanas pamatfunkcijas — sistēma izmaina prioritātes'
    },
    Ketu: {
        pattern: 'Karmiskā izsmelšanās — eksistenciālais nogurums',
        soma:    'Hroniska imūnsistēmas vājināšanās, nogurums bez identificējama avota, atkārtotas vīrusu epizodi',
        depth:   'Apātija pret jomu, kurā Ketu atrodas, beidzas ar dziļu, kaulos jūtamu izsmelšanos pat pēc miega'
    }
};

// ── Hirona mājas brūces (12 mājas) — primārā stresa aktivētājā ──────────────

const HIRON_HOUSE_WOUNDS = {
    1:  'Identitātes un fiziskā tēla brūce — pārmērīga pašnoliegšana vai pretrunīgs paštēls. Stresā ieslēdzas šaubas par savu pamata vērtību',
    2:  'Pašvērtības un resursu brūce — apziņa par "pietiekamību" stresā kļūst kritiski zema. Reakcija: kompulsīva pārpelnīšana vai naudas izšķiešana',
    3:  'Komunikācijas un brāļu/māsu brūce — stresā ieslēdzas balss zudums, nespēja izteikt to, kas ir svarīgi. Iespējama disleksija vai grūtības rakstot',
    4:  'Mājas un bērnības brūce — visdziļākā un visgrūtāk pieejamā. Stresā ieslēdzas sajūta "man nav īstas drošas vietas", arī materiālā stabilitātē',
    5:  'Radošās izpausmes un romantikas brūce — stresā tiek bloķēta spēja spēlēties, radīt vai mīlēt brīvi. Iespējamas problēmas ar bērnu attiecībām',
    6:  'Ikdienas un veselības brūce — stresā ieslēdzas hroniska "neviena cita to neizdarīs" sajūta, vada uz pārslodzi un fizisku izsmelšanos',
    7:  'Partnerības brūce — stresā tuvības attiecības atver senas atstātības baiļas. Reakcija: izšķirties vai pārmērīgi pieturēties',
    8:  'Transformācijas brūce — stresā aktivizējas dziļa transformācija caur krīzi, dažreiz caur veselības epizodi. Visdziļākais izdzīvošanas darbs',
    9:  'Pārliecības un izglītības brūce — stresā ieslēdzas zaudēta ticība, garīgs vai filozofisks tukšuma sajūta. Reakcija: dogmatisms vai cinisms',
    10: 'Karjeras un publiskā statusa brūce — stresā ieslēdzas sajūta "es nekad nesasniegšu pietiekoši augstu vietu", paranojas par savu publisko tēlu',
    11: 'Draudzības un sabiedrības brūce — stresā tiek atkārtota sajūta "es nepiederu". Reakcija: izolēšanās vai pārmērīga grupas pielāgošanās',
    12: 'Slēpto un karmas brūce — visneapzinātākā. Stresā ieslēdzas sapņi, intuīcijas pārpilde vai garīgā eskapisma vajadzība, dažreiz atkarību tendences'
};

// ── Galvenā eksportējamā funkcija ────────────────────────────────────────────

export function calculatePsychosomaticAudit(profile) {
    const bazi      = profile?.bazi || {};
    const vedic     = profile?.vedic || {};
    const wp        = profile?.western?.psychology || {};
    const personality = profile?.personality || [];

    // ── Trait izvilkšana: psychosom + chironact ──────────────────────────────
    const p = Object.fromEntries(
        personality.flatMap(cat => (cat.traits || []).map(t => [t.id, t.pct]))
    );
    const psychosomScore = p.psychosom ?? 50;
    const chironActScore = p.chironact ?? 50;

    // ── A. Akūtais stresa indikators — 6. mājas iemītnieki ──────────────────
    const planetsInH6 = [];
    const planetsInH8 = [];
    const planetsIgnore = new Set(['Meness', 'Hirons', 'Urans', 'Neptuns']); // dubli/Hirons atsevišķi
    for (const [planetName, data] of Object.entries(vedic?.planets || {})) {
        if (planetsIgnore.has(planetName)) continue;
        if (data?.house === 6) planetsInH6.push(planetName);
        if (data?.house === 8) planetsInH8.push(planetName);
    }

    const acuteSignals = planetsInH6
        .map(p => ({ planet: p, ...(HOUSE_6_ACUTE_SIGNALS[p] || null) }))
        .filter(s => s.signal);

    const chronicPatterns = planetsInH8
        .map(p => ({ planet: p, ...(HOUSE_8_CHRONIC_PATTERNS[p] || null) }))
        .filter(s => s.pattern);

    // ── Hirona māja kā fonā strādājošā primārā brūce ────────────────────────
    const hironHouse = vedic?.planets?.Hirons?.house || null;
    const hironWound = hironHouse ? HIRON_HOUSE_WOUNDS[hironHouse] : null;

    // ── C. Enerģijas noplūdes zonas — Rietumu kritiskie aspekti ─────────────
    const criticalAspects = wp?.criticalAspectsBlocks || [];
    const energyLeaks = criticalAspects.slice(0, 3).map(a => ({
        aspect:   a.aspect || a.type || '—',
        profile:  a.profile || '—',
        impact:   a.impact || a.summary || a.description || '—'
    }));

    // ── D. BaZi elementu disbalanss un kompensācijas protokols ──────────────
    const elements = bazi?.elements || {};
    const elemEntries = Object.entries(elements);

    const excessElements = elemEntries
        .filter(([_, pct]) => pct > 35)
        .map(([el, pct]) => ({ element: el, pct, ...BAZI_PSYCHOSOMATICS[el] }));

    const deficiencyElements = elemEntries
        .filter(([_, pct]) => pct < 10)
        .map(([el, pct]) => ({ element: el, pct, ...BAZI_PSYCHOSOMATICS[el] }));

    // Kompensācija — no dominējošā vai no trūkstošā elementa, ja nav dominanta
    const dominantEl = elemEntries.sort((a, b) => b[1] - a[1])[0];
    const compensationSource = excessElements[0] || (dominantEl ? { element: dominantEl[0], ...BAZI_PSYCHOSOMATICS[dominantEl[0]] } : null);

    // ── Psihosomatiskā mērītāja interpretācija ──────────────────────────────
    let psychosomNarrative;
    if (psychosomScore >= 70) {
        psychosomNarrative = 'Šis profils ir augsti somatizējošs — ķermenis reaģēs uz stresu zibens ātrumā un ar redzamiem fiziskiem signāliem. Tas ir priekšrocība: agrīnā brīdinājuma sistēma strādā ļoti labi, ja vien tiek klausīta';
    } else if (psychosomScore <= 30) {
        psychosomNarrative = 'Šis profils ir maz somatizējošs — ķermenis var izturēt daudz, bet pastāv risks, ka garīgā izdegšana iestāsies bez fiziskiem brīdinājuma signāliem. Sistēma var nokļūt krīzē, pirms tā signalizē "Stop"';
    } else {
        psychosomNarrative = 'Šis profils ir vidēji somatizējošs — ķermenis daļēji signalizē stresu, bet daļa slodzes uzkrājas neapzināti. Apzināts pašmonitorings ir kritiski svarīgs, jo ne visi signāli būs acīmredzami';
    }

    return {
        acute: {
            planetsInH6,
            signals: acuteSignals,
            hironWound,
            hironHouse
        },
        chronic: {
            planetsInH8,
            patterns: chronicPatterns
        },
        energyLeaks,
        compensation: {
            excessElements,
            deficiencyElements,
            primary: compensationSource
        },
        psychosomMeter: {
            psychosomScore,
            chironActScore,
            narrative: psychosomNarrative
        }
    };
}
