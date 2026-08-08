// Attiecību Dinamika — Bowlby Piesaistes teorija + Gotmana 4 jātnieki
// ─────────────────────────────────────────────────────────────────────
// Tāpat kā 1. sadaļā Junga arhetipi nodrošināja psihoterapeitisku
// mugurkaulu personības portretam, šeit Džona Boulbija Piesaistes teorija
// (Attachment Theory, 1969) un Džona Gotmana attiecību pētījumi (Four
// Horsemen of the Apocalypse) iekrāmē astroloģiskos datus klīniski
// validētā attiecību dinamikas valodā.
//
// Šī ir VIENA PROFILA analīze: kādu attiecību režīmu šis profils ienes,
// kādu konfliktu paterīnu rada, kādas dāvanas un robežas ir izceltas.
// Pilna 2-personu Ashta Kuta sinastrija atrodas atsevišķā Compatibility tabā.

// ── 4 Bowlby piesaistes stili ────────────────────────────────────────────────

export const ATTACHMENT_STYLES = {
    secure: {
        lv: 'Drošā piesaiste',
        en: 'Secure',
        color: '#22c55e',
        icon: '🌱',
        summary: 'Spēj veidot dziļu emocionālu tuvību nezaudējot savu identitāti — uzticība nāk dabiski, konflikti tiek risināti caur dialogu',
        operatingMode: 'Drošo piesaisti raksturo iekšēja pārliecība, ka attiecību telpa ir droša gan tuvuma, gan autonomijas brīžos. Krīzē meklē kontaktu, ne sodu vai bēgšanu',
        partnerNeeds: 'Partneris, kas spēj kā tuvumu, tā arī telpu, un kura komunikācija ir tieša, bet ne agresīva'
    },
    anxious: {
        lv: 'Trauksmainā piesaiste',
        en: 'Anxious-Preoccupied',
        color: '#f59e0b',
        icon: '🌊',
        summary: 'Tuvumā pieprasa pastāvīgu apstiprinājumu — partneris ir gan drošības avots, gan pastāvīgas trauksmes objekts',
        operatingMode: 'Trauksmainajā piesaistē emocionālā drošības sajūta ir piesaistīta partnera klātbūtnei un reakcijai. Atstātība, klusums vai distance tiek interpretēta kā draudi attiecībām',
        partnerNeeds: 'Partneris ar augstu emocionālo pieejamību, skaidru komunikāciju un spēju regulāri sniegt apstiprinājumu bez aizvainojuma'
    },
    avoidant: {
        lv: 'Izvairīgā piesaiste',
        en: 'Dismissive-Avoidant',
        color: '#3b82f6',
        icon: '🦅',
        summary: 'Tuvums tiek piedzīvots kā autonomijas apdraudējums — distance ir iekšēja drošības stratēģija',
        operatingMode: 'Izvairīgo piesaisti raksturo augsta vērtība neatkarībai un emocionālā pašregulācija "iekšpusē". Citu cilvēku emocionālie pieprasījumi pārslogo nervu sistēmu',
        partnerNeeds: 'Partneris, kas respektē autonomijas vajadzību, nepieprasa pastāvīgu emocionālo demonstrāciju un spēj radīt telpu klusumiem'
    },
    disorganized: {
        lv: 'Haotiskā piesaiste',
        en: 'Disorganized-Fearful',
        color: '#ef4444',
        icon: '🌀',
        summary: 'Vienlaikus pieprasa un atbaida tuvumu — iekšējais kompass attiecībās ir saplosīts starp pretrunīgām pamatvajadzībām',
        operatingMode: 'Haotiskā piesaiste rodas, ja agrīnās attiecību figūras vienlaikus bija gan drošības, gan draudu avots. Rezultātā tuvuma signāli aktivizē gan tuvības meklēšanu, gan paniku',
        partnerNeeds: 'Partneris ar augstu emocionālo briedumu, paredzamību un spēju nepersonīgi izturēt emocionālus uzbrukumus — bieži ar terapeitisku atbalstu attiecību veidošanā'
    }
};

// ── Gotmana 4 jātnieki + viņa antidoti ───────────────────────────────────────

export const GOTTMAN_HORSEMEN = {
    criticism: {
        lv: 'Kritika',
        en: 'Criticism',
        color: '#ef4444',
        icon: '🗡',
        pattern: 'Uzbrūk partnera raksturam, ne uzvedībai — "Tu vienmēr…", "Tu nekad…", "Ar tevi vienmēr ir problēmas"',
        antidote: 'Maigais sākums (Soft Startup) — sākt sarunu ar "Es jūtos…" un konkrētu situāciju, ne ar partnera "vienmēr/nekad" diagnozi'
    },
    contempt: {
        lv: 'Nicinājums',
        en: 'Contempt',
        color: '#dc2626',
        icon: '😤',
        pattern: 'Sarkasms, ņirgāšanās, pārākuma demonstrēšana, acu bolīšana — partneris tiek noniecināts kā cilvēks',
        antidote: 'Cieņas un apbrīnas kultūra — apzināti uzturēt sarakstu ar to, ko partnerī cieni; iekšēji izvēlēties skatu, kas redz vērtību'
    },
    defensiveness: {
        lv: 'Aizsardzība',
        en: 'Defensiveness',
        color: '#f59e0b',
        icon: '🛡',
        pattern: 'Atbildes uz partnera sūdzību ar pretsūdzību vai vainas novelšanu — "Bet tu pats…", "Es to darīju, jo tu…"',
        antidote: 'Atbildības pieņemšana — pat 5% piekrišana ("Tev ir taisnība — es to varēju izdarīt labāk") atbruņo eskalāciju un atver dialogu'
    },
    stonewalling: {
        lv: 'Mūra celšana',
        en: 'Stonewalling',
        color: '#64748b',
        icon: '🧱',
        pattern: 'Emocionālā izstāšanās no sarunas — klusums, novēršanās, fiziska aiziešana; ārēji izskatās rāms, iekšēji ir fizioloģiski pārslogots',
        antidote: 'Fizioloģiska pauze (20–30 min) — atklāti pasakot "Man vajag pauzi, es atgriezīšos 21:00", nevis vienkārši izejot; pieteikta pauze novērš pārslodzi, bet aiziešana bez vārdiem rada hronisku atsvešināšanos'
    }
};

// ── Sinerģijas zonas — ko šis profils dabiski ienes attiecībās ───────────────

const SYNERGY_ZONES_META = {
    emotional_safety: {
        lv: 'Emocionālā drošība',
        icon: '🫂',
        color: '#22c55e',
        description: 'Dabiska spēja radīt drošu telpu otram cilvēkam — empātija, klātbūtne un uzticības veidošana ir iekšēji apgūtas prasmes'
    },
    physical_intimacy: {
        lv: 'Fiziskais un jutekliskais tuvums',
        icon: '💞',
        color: '#ec4899',
        description: 'Augsta jutekliskā jutība un fiziska komforta uztvere — pieskāriens, klātbūtne un sensorā harmonija ir vērtēta valoda'
    },
    intellectual_partnership: {
        lv: 'Intelektuālā partnerība',
        icon: '🧠',
        color: '#a855f7',
        description: 'Domu apmaiņa, dziļas sarunas un kopīga ideju attīstība rada īpašu tuvības formu — partneris tiek redzēts arī kā domubiedrs'
    },
    creative_play: {
        lv: 'Radošā spēle',
        icon: '🎨',
        color: '#f59e0b',
        description: 'Spontanitāte, humors un kopīgas radošas aktivitātes uztur attiecību enerģiju svaigu — rutīna nav drošības zīme, bet bīstama zona'
    },
    loyalty_depth: {
        lv: 'Lojalitāte un dziļums',
        icon: '⚓',
        color: '#3b82f6',
        description: 'Ilgtermiņa uzticība un dziļa emocionālā investīcija nāk dabiski — attiecības tiek uztvertas kā kopīgs projekts, ne īslaicīga sadarbība'
    },
    adventure_freedom: {
        lv: 'Piedzīvojums un brīvība',
        icon: '🌍',
        color: '#06b6d4',
        description: 'Spēja nodrošināt partnerim izaugsmes telpu, jaunus piedzīvojumus un brīvības sajūtu — attiecības elpo, nesastingstot'
    }
};

// ── Berzes punkti — ko šim profilam jāmenedžē ────────────────────────────────

const FRICTION_ZONES_META = {
    control_jealousy: {
        lv: 'Kontroles un greizsirdības spiediens',
        icon: '🔒',
        color: '#f59e0b',
        description: 'Vajadzība zināt, kur, ar ko un kāpēc partneris atrodas, var pārvērsties uzraudzībā un partnera brīvības ierobežošanā'
    },
    emotional_volatility: {
        lv: 'Emocionālā nestabilitāte',
        icon: '⚡',
        color: '#ef4444',
        description: 'Strauja emocionālā reaktivitāte krīzē — situācijas nozīme tiek piepildīta ar intensitāti, kas tikai vēlāk parādās kā nesamērīga'
    },
    emotional_distance: {
        lv: 'Emocionālā distance',
        icon: '🧊',
        color: '#3b82f6',
        description: 'Tieksme atstāt partneri vienu ar viņa emocijām, "atrisinot" situāciju ar klusumu vai loģisku argumentu, ne ar klātbūtni'
    },
    perfectionism_criticism: {
        lv: 'Perfekcionisma kritika',
        icon: '📏',
        color: '#a855f7',
        description: 'Augsti standarti gan sev, gan partnerim — partnera kļūdas tiek interpretētas kā cieņas trūkums vai necentība'
    },
    shadow_destructive: {
        lv: 'Ēnas destruktīvie paterni',
        icon: '🌑',
        color: '#7f1d1d',
        description: 'Zem virsmas slēptas pašsabotāžas vai partnersabotāžas tendences — krīzes brīžos var aktivizēties uzvedība, kas apdraud paša gribētās attiecības'
    }
};

// ── Galvenā eksportējamā funkcija ────────────────────────────────────────────

export function calculateRelationshipDynamics(profile) {
    const personality = profile?.personality || [];
    const wp = profile?.western?.psychology || {};

    // Flatten traits
    const p = Object.fromEntries(
        personality.flatMap(cat => (cat.traits || []).map(t => [t.id, t.pct]))
    );
    const g = id => p[id] ?? 50;

    // Big Five
    const ocean = personality.find(cat => cat.id === 'ocean');
    const bf = {};
    if (ocean?.traits) for (const t of ocean.traits) bf[t.id] = t.pct;
    const O = bf.openness      ?? 50;
    const C = bf.conscient     ?? 50;
    const E = bf.extraversion  ?? 50;
    const A = bf.agreeableness ?? 50;
    const N = bf.neuroticism   ?? 50;

    const round = v => Math.max(0, Math.min(100, Math.round(v)));

    // ── Attachment style scoring ─────────────────────────────────────────────

    const attachmentScores = {
        secure: round(
            A                       * 0.20 +
            g('empathy')            * 0.20 +
            g('attachment')         * 0.25 +
            g('stability')          * 0.15 +
            g('nurturing')          * 0.10 +
            g('selfcontrol')        * 0.10
        ),
        anxious: round(
            (100 - g('attachment')) * 0.25 +
            N                       * 0.20 +
            g('jealousy')           * 0.20 +
            g('depth')              * 0.15 +
            (100 - g('stability'))  * 0.10 +
            g('sensuality')         * 0.10
        ),
        avoidant: round(
            (100 - g('attachment')) * 0.20 +
            g('independent')        * 0.25 +
            (100 - g('empathy'))    * 0.20 +
            (100 - g('expressiveness')) * 0.15 +
            g('selfcontrol')        * 0.10 +
            (100 - A)               * 0.10
        ),
        disorganized: round(
            (100 - g('attachment')) * 0.15 +
            g('destructive')        * 0.20 +
            g('jealousy')           * 0.15 +
            N                       * 0.15 +
            g('fightresponse')      * 0.10 +
            g('blindspot')          * 0.15 +
            (100 - g('selfcontrol')) * 0.10
        )
    };

    const attachmentSorted = Object.entries(attachmentScores).sort((a, b) => b[1] - a[1]);
    const primaryAttachmentKey = attachmentSorted[0][0];
    const primaryAttachment = {
        key: primaryAttachmentKey,
        score: attachmentSorted[0][1],
        ...ATTACHMENT_STYLES[primaryAttachmentKey]
    };

    // ── Gottman 4 horsemen scoring ───────────────────────────────────────────

    const horsemenScores = {
        criticism: round(
            g('assertive')        * 0.20 +
            g('categorical')      * 0.20 +
            g('perfectionism')    * 0.20 +
            (100 - g('empathy'))  * 0.20 +
            g('conflictstyle')    * 0.20
        ),
        contempt: round(
            (100 - g('diplomacy')) * 0.20 +
            (100 - g('empathy'))   * 0.20 +
            g('destructive')       * 0.20 +
            g('categorical')       * 0.15 +
            g('status')            * 0.15 +
            g('jealousy')          * 0.10
        ),
        defensiveness: round(
            (100 - g('locus'))      * 0.20 +
            N                       * 0.20 +
            g('jealousy')           * 0.15 +
            (100 - g('selfcontrol')) * 0.15 +
            (100 - A)               * 0.15 +
            g('blindspot')          * 0.15
        ),
        stonewalling: round(
            g('independent')            * 0.20 +
            (100 - g('expressiveness')) * 0.20 +
            (100 - E)                   * 0.20 +
            g('selfcontrol')            * 0.15 +
            (100 - g('empathy'))        * 0.15 +
            (100 - g('conflictstyle'))  * 0.10
        )
    };

    const horsemenSorted = Object.entries(horsemenScores)
        .map(([key, score]) => ({ key, score, ...GOTTMAN_HORSEMEN[key] }))
        .sort((a, b) => b.score - a.score);
    const topHorsemen = horsemenSorted.slice(0, 2);

    // ── Synergy zones scoring ────────────────────────────────────────────────

    const synergyScores = {
        emotional_safety: round(
            g('empathy')    * 0.30 +
            g('nurturing')  * 0.25 +
            g('stability')  * 0.20 +
            A               * 0.25
        ),
        physical_intimacy: round(
            g('sensuality') * 0.40 +
            g('vitality')   * 0.25 +
            g('attachment') * 0.20 +
            E               * 0.15
        ),
        intellectual_partnership: round(
            O               * 0.30 +
            g('analytical') * 0.25 +
            g('abstract')   * 0.25 +
            g('learning')   * 0.20
        ),
        creative_play: round(
            g('creativity') * 0.30 +
            g('inspiration') * 0.20 +
            g('optimism')   * 0.20 +
            E               * 0.15 +
            (100 - g('perfectionism')) * 0.15
        ),
        loyalty_depth: round(
            g('loyalty')      * 0.30 +
            g('perseverance') * 0.20 +
            g('attachment')   * 0.25 +
            g('depth')        * 0.25
        ),
        adventure_freedom: round(
            g('independent') * 0.25 +
            g('risk')        * 0.25 +
            g('initiative')  * 0.25 +
            O                * 0.25
        )
    };

    const synergySorted = Object.entries(synergyScores)
        .map(([key, score]) => ({ key, score, ...SYNERGY_ZONES_META[key] }))
        .sort((a, b) => b.score - a.score);
    const topSynergy = synergySorted.slice(0, 3);

    // ── Friction points scoring ──────────────────────────────────────────────

    const frictionScores = {
        control_jealousy: round(
            g('jealousy')          * 0.40 +
            g('categorical')       * 0.25 +
            (100 - g('diplomacy')) * 0.20 +
            g('hierarchy')         * 0.15
        ),
        emotional_volatility: round(
            N                        * 0.35 +
            (100 - g('selfcontrol')) * 0.25 +
            g('fightresponse')       * 0.20 +
            (100 - g('stability'))   * 0.20
        ),
        emotional_distance: round(
            (100 - g('empathy'))        * 0.30 +
            g('independent')            * 0.25 +
            (100 - g('expressiveness')) * 0.25 +
            (100 - E)                   * 0.20
        ),
        perfectionism_criticism: round(
            g('perfectionism')   * 0.35 +
            g('categorical')     * 0.25 +
            g('assertive')       * 0.20 +
            (100 - g('empathy')) * 0.20
        ),
        shadow_destructive: round(
            g('destructive') * 0.35 +
            g('obsession')   * 0.25 +
            g('blindspot')   * 0.20 +
            g('jealousy')    * 0.20
        )
    };

    const frictionSorted = Object.entries(frictionScores)
        .map(([key, score]) => ({ key, score, ...FRICTION_ZONES_META[key] }))
        .sort((a, b) => b.score - a.score);
    const topFriction = frictionSorted.slice(0, 3);

    // ── Ēnas slānis (Lilita) ─────────────────────────────────────────────────

    const shadow = wp.blackmailPoints || null;

    // ── Attiecību audita verdikts (sintēze) ──────────────────────────────────

    const synergyLv  = topSynergy[0]?.lv  || 'sava unikāla kontūra';
    const frictionLv = topFriction[0]?.lv || 'nezināms berzes punkts';
    const horsemanLv = topHorsemen[0]?.lv || 'nezināms';

    // Visas entītijas tiek liktas nominatīvā formā kā etiķetes (bold labels),
    // nepakļaujot tās gramatiskās locīšanas prasībām.
    const verdict = `Šī profila pamata operatīvais režīms attiecībās — <b>${primaryAttachment.lv}</b>. ${primaryAttachment.summary}. ` +
        `Krīzes brīžos lielākais konfliktu paterns, kas var aktivizēties, ir <b>${horsemanLv}</b>: ${topHorsemen[0]?.pattern || ''}. ` +
        `Profila dabiskā dāvana attiecībās ir sinerģijas zona <b>${synergyLv}</b>; apzināti jāstrādā ar berzes punktu <b>${frictionLv}</b>, lai tas nepārvērš attiecības par cīņas lauku.`;

    return {
        attachment: {
            primary: primaryAttachment,
            allScores: attachmentSorted.map(([k, s]) => ({ key: k, score: s, ...ATTACHMENT_STYLES[k] }))
        },
        horsemen: {
            top: topHorsemen,
            allScores: horsemenSorted
        },
        synergy:         topSynergy,           // top 3 (backward compat)
        synergyAll:      synergySorted,        // pilns saraksts (6 ieraksti)
        friction:        topFriction,          // top 3 (backward compat)
        frictionAll:     frictionSorted,       // pilns saraksts (5 ieraksti)
        shadow,
        verdict
    };
}
