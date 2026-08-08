// Vadītāja / darba stila klasifikācija — 8 arhetipa modelis

const LEADER_META = {
    charismatic: {
        lv: 'Harizmātiskais Vadītājs',
        archClass: 'leader',
        color: '#f59e0b',
        icon: '✨',
        strengths: ['Motivē komandu ar personīgo piemēru', 'Rada lojalitāti un sekotājus', 'Efektīvs krīzēs un pārmaiņu brīžos'],
        risks: ['Atkarīgs no personīgā statusa', 'Grūti deleģēt kontroli', 'Pārmērīga emocionālā iesaiste'],
        roles: ['Komandas līderis', 'Pārdošanas vadītājs', 'Pārmaiņu vadītājs', 'Uzņēmuma seja / runasvīrs']
    },
    authoritarian: {
        lv: 'Autoritārais Vadītājs',
        archClass: 'leader',
        color: '#ef4444',
        icon: '⚔️',
        strengths: ['Ātri pieņem lēmumus', 'Uztur augstu disciplīnu', 'Efektīvs sarežģītos, hierarhiskos apstākļos'],
        risks: ['Izdzen talantus ar pārmērīgu kontroli', 'Rada bailes, nevis lojalitāti', 'Vāja adaptācija mainoties videi'],
        roles: ['Operāciju direktors', 'Krīzes vadītājs', 'Ražošanas vadītājs', 'Struktūrvienības vadītājs']
    },
    expert: {
        lv: 'Ekspertu Vadītājs',
        archClass: 'leader',
        color: '#3b82f6',
        icon: '🔬',
        strengths: ['Augsta kompetence', 'Analītisks, godīgs skatījums', 'Cienīts komandā par zināšanām'],
        risks: ['Ieslīgst detaļās', 'Grūti deleģēt', 'Zema spontānā starppersonu ietekme'],
        roles: ['Tehniskais vadītājs (CTO)', 'Vadošais analītiķis', 'Nozares eksperts', 'R&D vadītājs']
    },
    mentor: {
        lv: 'Mentors / Koučs',
        archClass: 'coach',
        color: '#ec4899',
        icon: '💛',
        strengths: ['Attīsta citus — redz potenciālu, ko paši neredz', 'Rada drošu, uzticīgu komandas klimatu', 'Efektīvs ilgtermiņa talantu audzēšanā'],
        risks: ['Pārmērīgi absorbē citu problēmas', 'Grūti uzturēt robežas', 'Var tikt izmantots labās gribas dēļ'],
        roles: ['HR vadītājs', 'Komandas koučs', 'Talantu attīstības vadītājs', 'Pedagogs / pasniedzējs']
    },
    visionary: {
        lv: 'Vizionārs / Ideālists',
        archClass: 'visionary',
        color: '#06b6d4',
        icon: '🌟',
        strengths: ['Redz iespējas tur, kur citi redz šķēršļus', 'Spēj iedvesmot ar ideju, nevis statusu', 'Ilgtermiņa transformāciju dzinulis'],
        risks: ['Vāja ikdienas izpilde un detaļu kontrole', 'Grūti atrast praktiski domājošus sabiedrotos', 'Vīzija bieži apsteidz resursus'],
        roles: ['Produkta vīzijas vadītājs', 'Stratēģijas direktors', 'Uzņēmējs / dibinātājs', 'Inovāciju vadītājs']
    },
    admin: {
        lv: 'Administrators / Birokrāts',
        archClass: 'manager',
        color: '#64748b',
        icon: '📋',
        strengths: ['Procesi un struktūra darbojas uzticami', 'Zems kļūdu skaits un augstas kvalitātes kontrole', 'Droša, prognozējama vide komandai'],
        risks: ['Pārmērīga procesa ievērošana kavē inovāciju', 'Grūti adaptēties ātrām pārmaiņām', 'Var liegt citus eksperimentēt'],
        roles: ['Procesu vadītājs', 'Kvalitātes vadītājs', 'Projektu administrators', 'Operāciju koordinators']
    },
    specialist: {
        lv: 'Speciālists / Izpildītājs',
        archClass: 'executor',
        color: '#10b981',
        icon: '⚙️',
        strengths: ['Uzticams un disciplinēts', 'Augstas kvalitātes darbs', 'Stabils, prognozējams komandas loceklis'],
        risks: ['Maz pašiniciatīvas', 'Zems vadīšanas potenciāls bez mentoringa', 'Pārlieka atkarība no norādījumiem'],
        roles: ['Nozares speciālists', 'Vecākais eksperts', 'Kvalitātes inženieris', 'Uzturēšanas / uzticamības loma']
    },
    solo: {
        lv: 'Neatkarīgais Eksperts',
        archClass: 'independent',
        color: '#8b5cf6',
        icon: '🦅',
        strengths: ['Spēcīga personīgā vīzija un pārliecība', 'Neatkarīga, kritiska domāšana', 'Efektīvs individuālos projektos un solo darbā'],
        risks: ['Grūti iekļauties komandas struktūrās', 'Ignorē vai apšauba hierarhiju', 'Zema tolerance pret kontroli no ārpuses'],
        roles: ['Konsultants', 'Pētnieks', 'Neatkarīgais eksperts', 'Autors / analītiķis']
    }
};

const ARCH_CLASS_LABEL = {
    leader:      'Vadītāja tips',
    coach:       'Koučinga tips',
    visionary:   'Vizionāra tips',
    manager:     'Menedžera tips',
    executor:    'Izpildītāja tips',
    independent: 'Neatkarīgā operatora tips'
};

export function calculateLeadershipType(personality) {
    const p = {};
    for (const cat of personality) {
        for (const t of cat.traits) {
            p[t.id] = t.pct;
        }
    }
    const g = id => p[id] ?? 50;

    // ── Kompozītu metrikas ────────────────────────────────────────────────────

    // Siltā harizma: sociālā magnetisma bāze
    const warmCharisma = Math.round(
        g('charisma')       * 0.45 +
        g('social')         * 0.30 +
        g('expressiveness') * 0.25
    );

    // Aukstā harizma: autoritātes bāze (diktatoriska ietekme, ne siltums)
    const coldCharisma = Math.round(
        g('authority')  * 0.40 +
        g('analytical') * 0.25 +
        g('locus')      * 0.20 +
        g('status')     * 0.15
    );

    // Kontroles dziņa: trauksme + ēnas puse kā vadīšanas motivācija
    const controlDrive = Math.round(
        g('authority')   * 0.35 +
        g('neuroticism') * 0.25 +
        g('jealousy')    * 0.20 +
        g('destructive') * 0.20
    );

    // ── 8 arhetipa skori ──────────────────────────────────────────────────────

    const scores = {
        // Vada caur sociālo siltumu un iedvesmu
        charismatic:   Math.round(
            warmCharisma    * 0.50 +
            g('assertive')  * 0.25 +
            g('optimism')   * 0.25
        ),
        // Vada caur kontroli, disciplīnu un hierarhiju
        authoritarian: Math.round(
            coldCharisma    * 0.45 +
            g('longterm')   * 0.20 +
            g('ambition')   * 0.20 +
            controlDrive    * 0.15
        ),
        // Vada caur ekspertīzi un kompetenci
        expert:        Math.round(
            g('analytical')    * 0.30 +
            g('hyperfocus')    * 0.30 +
            g('conscient')     * 0.20 +
            g('perseverance')  * 0.20
        ),
        // Vada caur citiem — attīstot, nevis kontrolējot
        mentor:        Math.round(
            g('empathy')    * 0.35 +
            g('nurturing')  * 0.30 +
            g('diplomacy')  * 0.20 +
            g('social')     * 0.15
        ),
        // Vada caur ideju un misiju — nākotnes vīziju
        visionary:     Math.round(
            g('inspiration')    * 0.30 +
            g('creativity')     * 0.25 +
            g('souldirection')  * 0.25 +
            g('abstract')       * 0.20
        ),
        // Pārvalda caur procesiem, noteikumiem un struktūru
        admin:         Math.round(
            g('traditional')            * 0.30 +
            g('conscient')              * 0.25 +
            g('categorical')            * 0.25 +
            (100 - g('risk'))           * 0.20
        ),
        // Izpilda — uzticams, lojalitātes orientēts; hierarhijas pieņemšana obligāta
        specialist:    Math.round(
            g('conscient')              * 0.25 +
            g('loyalty')                * 0.25 +
            g('perseverance')           * 0.25 +
            (100 - g('initiative'))     * 0.15 +
            g('hierarchy')              * 0.10
        ),
        // Darbojas autonomi — pretojas hierarhijai un statusam
        solo:          Math.round(
            g('independent')            * 0.35 +
            (100 - g('hierarchy'))      * 0.25 +
            (100 - g('h10status'))      * 0.20 +
            (100 - g('social'))         * 0.20
        )
    };

    const sorted = Object.entries(scores).sort((a, b) => b[1] - a[1]);
    const [primaryKey, primaryScore] = sorted[0];
    const [secondaryKey, secondaryScore] = sorted[1];

    const primary = { key: primaryKey, score: primaryScore, ...LEADER_META[primaryKey] };

    const allTypes = sorted.map(([key, score]) => ({
        key,
        score,
        ...LEADER_META[key]
    }));

    return {
        primary,
        secondary:      { key: secondaryKey, score: secondaryScore, ...LEADER_META[secondaryKey] },
        archClassLabel: ARCH_CLASS_LABEL[primary.archClass],
        allTypes,
        warmCharisma,
        coldCharisma,
        controlDrive
    };
}
