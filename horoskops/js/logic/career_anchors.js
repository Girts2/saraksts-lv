// Karjeras Kapacitātes un Lēmumu Dizaina modelis
// ─────────────────────────────────────────────────
// 3 ietvari, kas atspoguļo cilvēka profesionālo kapacitāti akadēmiski
// atzītā valodā (līdzīgi kā Junga arhetipi 1. sadaļā):
//
//   1) Edgar Schein "Career Anchors" (MIT Sloan) — 8 enkuri, kas atklāj,
//      no kā cilvēks karjerā neatteiksies. Galvenā ass.
//   2) Cynefin (Dave Snowden) — 4 lēmumu konteksti, kuros cilvēka
//      kognitīvais algoritms strādā vislabāk.
//   3) Kahneman System 1 / System 2 — ātrās intuitīvās vs lēnās analītiskās
//      domāšanas īpatsvars.
//
// Datu avoti: profile.personality (Big Five + ~70 dimensijas),
// profile.bazi (10 Dievi, Daymaster stiprums), profile.leadership
// (warmCharisma, coldCharisma, controlDrive, soloScore).

// ── Schein 8 enkuru metadati ─────────────────────────────────────────────────

const ANCHORS_META = {
    technical: {
        lv: 'Tehniskā meistarība',
        en: 'Technical / Functional Competence',
        color: '#3b82f6',
        icon: '🔬',
        description: 'Galvenais ir kļūt par neaizvietojamu meistaru savā jomā. Vadošais amats bez ekspertīzes nešķiet pievilcīgs — labāk dziļāks specializācijas līmenis nekā plašāka, bet seklāka atbildība',
        thrives: 'Specializētās lomās, kur darba kvalitāti vērtē kolēģi-eksperti, ne menedžeri',
        risks: 'Pretojas paaugstinājumiem, ja tie nozīmē atteikšanos no roku darba; var izolēties no biznesa konteksta',
        narrativeDrive:  'vēlme kļūt par neaizvietojamu meistaru savā specializētajā jomā',
        burnoutTrigger:  'lomā, kas piespiež gadiem ilgu šauru tehnisku iedziļināšanos vienā jomā ar minimālu rīcības brīvību ārpus tās — dziļā tehnicizācija šim profilam jūtas kā arests'
    },
    management: {
        lv: 'Vadības atbildība',
        en: 'General Managerial Competence',
        color: '#ef4444',
        icon: '⚔️',
        description: 'Galvenais ir uzņemties atbildību par cilvēkiem, resursiem un stratēģiskiem lēmumiem. Tehniskā ekspertīze ir tikai trampolīns, ne galamērķis',
        thrives: 'Vidējā un augstākā vadības līmenī, kur jāsabalansē daudzas dimensijas reizē',
        risks: 'Ja netiek paaugstināts, jūtas nenovērtēts; var pieņemt lomas pirms ir gatavs',
        narrativeDrive:  'atbildība par cilvēkiem, resursiem un stratēģiskiem lēmumiem augstā līmenī',
        burnoutTrigger:  'lomā, kas pieprasa cilvēku vadības pienākumus un hierarhisku komandas pārvaldīšanu — vadības formalitātes nogurdina ātrāk nekā paši darba uzdevumi'
    },
    autonomy: {
        lv: 'Autonomija un neatkarība',
        en: 'Autonomy / Independence',
        color: '#f59e0b',
        icon: '🦅',
        description: 'Galvenais ir strādāt pēc saviem noteikumiem — savs grafiks, sava metodika, sava atbildības robeža. Hierarhijas un mikromenedžments degradē sniegumu',
        thrives: 'Freelance, konsultāciju, akadēmiskās vai radošās lomās ar plašu rīcības brīvību',
        risks: 'Grūti pielāgojas korporatīvai struktūrai; var palaist garām komandas izaugsmes iespējas',
        narrativeDrive:  'nepieciešamība pēc personīgās rīcības brīvības un pēc savu noteikumu noteikšanas',
        burnoutTrigger:  'pilnīgi autonomā lomā bez komandas, struktūras vai ārējās atbildības — pārmērīga vientulība un sava grafika veidošana rada nedrošību, ne brīvību'
    },
    security: {
        lv: 'Drošība un stabilitāte',
        en: 'Security / Stability',
        color: '#64748b',
        icon: '🛡',
        description: 'Galvenais ir paredzamība — stabils ienākums, skaidra karjeras līkne, ilgtermiņa garantija. Risks tikai tad, ja viss aizmugurē ir nodrošināts',
        thrives: 'Valsts sektorā, lielās korporācijās, regulētās nozarēs ar skaidru hierarhiju',
        risks: 'Var palaist garām izaugsmes iespējas pārmērīgas piesardzības dēļ; izvairās no nepieciešama riska',
        narrativeDrive:  'stabila, paredzama un ilgtermiņā garantēta darba vide',
        burnoutTrigger:  'stingri reglamentētā birokrātiskā vidē ar lēnu mainību un paredzamiem procesiem — stabilitāte ātri kļūst par garlaicību, kas izsūks motivāciju'
    },
    entrepreneurial: {
        lv: 'Uzņēmējdarbība un radīšana',
        en: 'Entrepreneurial Creativity',
        color: '#a855f7',
        icon: '🚀',
        description: 'Galvenais ir radīt kaut ko savu no nulles — uzņēmumu, produktu, kustību. Algotā lomā jūtas kā cilvēks, kas dzīvo svešās mājās',
        thrives: 'Dibinot savu biznesu, vadot startup vai jaunu virzienu lielā uzņēmumā',
        risks: 'Var pārvērtēt savas iespējas un riskēt ar resursiem, ko nedrīkstētu likt uz spēles',
        narrativeDrive:  'vēlme radīt kaut ko savu no nulles — uzņēmumu, produktu vai jaunu virzienu',
        burnoutTrigger:  'lomā, kas pieprasa kaut ko radīt no nulles bez gatavās instrukcijas — pārmērīga radošā nenoteiktība un atbildība par dibinātāja risku rada paralīzi'
    },
    service: {
        lv: 'Kalpošana un sūtība',
        en: 'Service / Dedication to a Cause',
        color: '#22c55e',
        icon: '💛',
        description: 'Galvenais ir, lai darbam ir nozīme, kas pārsniedz personīgo labklājību. Lielāka alga bez jēgas nekompensē iekšējo tukšumu',
        thrives: 'Veselības aprūpē, izglītībā, sociālajā jomā, NVO, misijas-vadītos uzņēmumos',
        risks: 'Var paciest sliktus apstākļus pārāk ilgi pārliecības dēļ; ignorēt savas vajadzības',
        narrativeDrive:  'darbs, kam ir nozīme, kas pārsniedz personīgo labklājību',
        burnoutTrigger:  'lomā, kur darbam jākalpo lielākam mērķim ar zemu atalgojumu un augstu emocionālo iesaisti — "misijas darbs" šim profilam jūtas kā ekspluatācija, ne sūtība'
    },
    challenge: {
        lv: 'Tīrais izaicinājums',
        en: 'Pure Challenge',
        color: '#dc2626',
        icon: '⛰',
        description: 'Galvenais ir risināt to, ko citi atsakās uzņemt. Stabils, paredzams darbs ātri kļūst garlaicīgs — vajag pretinieku, kuru pārvarēt',
        thrives: 'Krīžu vadībā, sarežģītās restrukturizācijās, sacensību sportā, sarežģītā pārdošanā',
        risks: 'Var meklēt mākslīgus konfliktus, ja īsto nav; vājāks ikdienas darba ritmā',
        narrativeDrive:  'iespēja risināt to, ko citi atsakās uzņemt',
        burnoutTrigger:  'vidē ar nepārtrauktiem smagiem izaicinājumiem, krīžu situācijām un augstā riska lēmumiem — pārmērīga konfrontācija izsmel resursus ātrāk, nekā tos atjauno'
    },
    lifestyle: {
        lv: 'Dzīves līdzsvars',
        en: 'Lifestyle Integration',
        color: '#ec4899',
        icon: '🌿',
        description: 'Galvenais ir, lai karjera iekļaujas dzīvē kopumā — ģimenē, attiecībās, veselībā, personīgajos projektos. Karjera nedrīkst pārņemt visu identitāti',
        thrives: 'Lomās ar elastīgu grafiku, attālinātā darbā, paralēli vairākās lomās',
        risks: 'Var tikt uztverts kā mazāk ambiciozs; konkurē ar kolēģiem, kas dzīvo darbam',
        narrativeDrive:  'karjeras integrācija ar personīgo dzīvi, attiecībām un veselību',
        burnoutTrigger:  'lomā ar stingri reglamentētu grafiku un striktu darba-dzīves nodalījumu — pārmērīga "balansa noteikšana" demotivē cilvēku, kurš būtu gatavs ļaut darbam ielauzties privātajā telpā'
    }
};

const ANCHOR_KEYS = Object.keys(ANCHORS_META);

// ── Cynefin domēni ───────────────────────────────────────────────────────────

const CYNEFIN_META = {
    simple: {
        lv: 'Kārtības vide',
        en: 'Simple / Clear',
        color: '#22c55e',
        icon: '▢',
        description: 'Vislabāk strādā vidē ar skaidriem noteikumiem un atkārtotām situācijām. Procedūras, kontrolsaraksti, paredzamība — tas ir komforta zona, kur cilvēks ātri kļūst par uzticamu izpildītāju',
        narrativeHabitat: 'vidē ar skaidriem noteikumiem, atkārtotām situācijām un paredzamiem procesiem'
    },
    complicated: {
        lv: 'Ekspertīzes vide',
        en: 'Complicated',
        color: '#3b82f6',
        icon: '◇',
        description: 'Vislabāk strādā ar sarežģītām problēmām, kurām ir pareizas atbildes — ja iedod laiku analīzei. Mīl datus, eksperta konsultāciju un loģisku argumentāciju, pretojas steigai un improvizācijai',
        narrativeHabitat: 'ekspertīzes vidē, kur sarežģītām problēmām ir pareizas atbildes — ja iedod laiku rūpīgai analīzei'
    },
    complex: {
        lv: 'Eksperimentēšanas vide',
        en: 'Complex',
        color: '#a855f7',
        icon: '◈',
        description: 'Vislabāk strādā vidē, kur nav skaidru atbilžu un kur stratēģija veidojas eksperimentējot. Mēģina, novēro rezultātu, koriģē — emergenta domāšana, kas pieņem nenoteiktību kā realitāti',
        narrativeHabitat: 'neparedzamā vidē, kur nav gatavu atbilžu un kur stratēģija veidojas eksperimentējot un mācoties no rezultāta'
    },
    chaotic: {
        lv: 'Krīzes vide',
        en: 'Chaotic',
        color: '#ef4444',
        icon: '◬',
        description: 'Vislabāk strādā krīzē, kur jārīkojas pirms ir skaidri noteikumi. Stabilizē sistēmu ar instinktu un pieredzi, analīzi atstājot vēlākam laikam — ugunsdzēsējs, ne arhitekts',
        narrativeHabitat: 'krīžu vidē, kur jārīkojas pirms ir skaidri noteikumi un kur sistēmu stabilizē ar instinktu un pieredzi'
    }
};

// ── Kahneman sistēmas ────────────────────────────────────────────────────────

const KAHNEMAN_META = {
    s1: {
        lv: 'Ātrā sistēma',
        en: 'System 1 — Intuitive',
        color: '#f59e0b',
        description: 'Lēmumus pieņem ātri, balstoties uz instinktu, paterniem un pieredzi. Reti pieprasa visus datus pirms rīkošanās — uzticas iekšējam signālam',
        narrativeStyle: 'ātri un intuitīvi, paļaujoties uz instinktu un paterniem'
    },
    s2: {
        lv: 'Lēnā sistēma',
        en: 'System 2 — Analytical',
        color: '#3b82f6',
        description: 'Lēmumus pieņem pēc analīzes, datu pārbaudes un loģiskas argumentācijas. Nesteidzas, izstaigā opcijas, pieprasa pierādījumus pirms iesaistīšanās',
        narrativeStyle: 'lēni un analītiski, balstoties uz datiem un loģisku argumentāciju'
    },
    adaptive: {
        lv: 'Adaptīvā sistēma',
        en: 'Adaptive — Context-Switching',
        color: '#8b5cf6',
        description: 'Šis profils nepiedāvā vienu dominējošu kognitīvo režīmu — tas elastīgi pārslēdzas starp ātro (intuitīvo) un lēno (analītisko) sistēmu atkarībā no situācijas. Tas ir stiprums, nevis neskaidrība',
        narrativeStyle: 'adaptīvi, elastīgi pārslēdzoties starp intuīciju un analīzi atkarībā no situācijas'
    }
};

// ── Līderības tipa naratīvi (kartējas pret leadership_type.js arhetipiem) ────

const LEADERSHIP_NARRATIVES = {
    charismatic:   'harizmātisks vadītājs, kas motivē komandu ar personīgo magnetismu',
    authoritarian: 'stingrs autoritārais vadītājs ar augstu disciplīnu un skaidru hierarhiju',
    expert:        'ekspertu vadītājs, kura autoritāte balstās uz dziļām zināšanām',
    mentor:        'mentors, kas attīsta talantus caur uzticības veidošanu',
    visionary:     'vizionārs, kas iedvesmo ar nākotnes ideju, ne ar statusu',
    admin:         'administrators, kas uztur sistēmas, procesus un kvalitātes kontroli',
    specialist:    'uzticams speciālists, kura stiprums ir lojāla ilgtermiņa izpilde',
    solo:          'neatkarīgs eksperts vai konsultants, kas darbojas autonomi, ārpus hierarhijas'
};

// ── BaZi 10 Dievu klātbūtnes svars ───────────────────────────────────────────
// Atgriež 0-100 katram dievam, balstoties uz pozīcijām + main/hidden bonus.

function buildGodWeights(bazi) {
    const godsObj = bazi?.gods || {};
    const positions = Object.values(godsObj);
    const counts = {};
    positions.forEach(g => { if (g) counts[g] = (counts[g] || 0) + 1; });

    const allGodKeys = [
        'Friend', 'Rob_Wealth',
        'Eating_God', 'Hurting_Officer',
        'Indirect_Wealth', 'Direct_Wealth',
        'Seven_Killings', 'Direct_Officer',
        'Indirect_Resource', 'Direct_Resource'
    ];

    const weights = {};
    for (const key of allGodKeys) {
        const positionScore = (counts[key] || 0) * 25;
        const mainBonus     = (bazi?.mainGod   === key) ? 15 : 0;
        const hiddenBonus   = (bazi?.hiddenGod === key) ? 10 : 0;
        weights[key] = Math.min(100, positionScore + mainBonus + hiddenBonus);
    }
    return weights;
}

// ── Big Five → RIASEC (Larson et al. 2002 meta-analīze) ──────────────────────
// Replicēts no career_suggestions.js — saglabā vienotu zinātnisko bāzi.

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

// ── Galvenā eksportētā funkcija ───────────────────────────────────────────────

export function calculateCareerAnchors(profile, leadership) {
    const personality = profile?.personality || [];
    const bazi = profile?.bazi || {};

    // Flatten visus traits → id → pct karte
    const p = Object.fromEntries(
        personality.flatMap(cat => (cat.traits || []).map(t => [t.id, t.pct]))
    );
    const g = id => p[id] ?? 50;

    // Big Five no 'ocean' kategorijas (tāpat kā career_suggestions.js)
    const ocean = personality.find(cat => cat.id === 'ocean');
    const bf = {};
    if (ocean?.traits) for (const t of ocean.traits) bf[t.id] = t.pct;
    const O  = bf.openness      ?? 50;
    const C  = bf.conscient     ?? 50;
    const E  = bf.extraversion  ?? 50;
    const A  = bf.agreeableness ?? 50;
    const N  = bf.neuroticism   ?? 50;
    const Ns = 100 - N;

    const riasec = bigFiveToRiasec(O, C, E, A);
    const gods   = buildGodWeights(bazi);
    const dmStr  = bazi?.dm_strength?.supportScore ?? 50;

    const warmCharisma = leadership?.warmCharisma ?? 50;
    const coldCharisma = leadership?.coldCharisma ?? 50;
    const controlDrive = leadership?.controlDrive ?? 50;
    const soloScore    = leadership?.allTypes?.find(t => t.key === 'solo')?.score ?? 50;

    const round = v => Math.max(0, Math.min(100, Math.round(v)));

    // ── Schein 8 enkuru skori ────────────────────────────────────────────────
    // RIASEC ir Big Five DERIVĀTS (bigFiveToRiasec lieto tikai O/C/E/A), tāpēc
    // tas NAV neatkarīgs signāls. Kur vienā formulā sastopas neapstrādāts Big
    // Five mērs UN tā RIASEC atvasinājums par to pašu dimensiju, mēs paturam
    // tiešo mēru un izmetam RIASEC dublikātu (svaru sapludinot) — citādi viena
    // un tā pati dimensija tiek balsota divreiz.

    const anchorScores = {
        technical: round(
            g('analytical')           * 0.20 +
            g('hyperfocus')           * 0.15 +
            g('perfectionism')        * 0.10 +
            gods.Eating_God           * 0.20 +
            gods.Hurting_Officer      * 0.10 +
            riasec.I                  * 0.15 +
            riasec.R                  * 0.10
        ),
        management: round(
            g('authority')            * 0.20 +
            g('ambition')             * 0.15 +
            coldCharisma              * 0.10 +
            gods.Direct_Officer       * 0.15 +
            gods.Seven_Killings       * 0.10 +
            riasec.E                  * 0.20 +
            dmStr                     * 0.10
        ),
        autonomy: round(
            g('independent')          * 0.25 +
            (100 - g('hierarchy'))    * 0.15 +
            (100 - g('conscient'))    * 0.15 +   // bija 0.10 + (100-riasec.C)*0.05 — riasec.C ≈ apzinīgums, dublikāts sapludināts
            gods.Rob_Wealth           * 0.15 +
            gods.Hurting_Officer      * 0.15 +
            soloScore                 * 0.15
        ),
        security: round(
            g('conscient')            * 0.35 +   // bija 0.20 + riasec.C*0.15 — riasec.C ≈ apzinīgums, dublikāts sapludināts
            (100 - g('risk'))         * 0.15 +
            g('traditional')          * 0.10 +
            gods.Direct_Resource      * 0.20 +
            gods.Direct_Officer       * 0.10 +
            dmStr                     * 0.10
        ),
        entrepreneurial: round(
            g('initiative')           * 0.15 +
            g('risk')                 * 0.15 +
            g('creativity')           * 0.10 +
            g('ambition')             * 0.10 +
            gods.Indirect_Wealth      * 0.20 +
            gods.Seven_Killings       * 0.10 +
            riasec.E                  * 0.15 +
            riasec.A                  * 0.05
        ),
        service: round(
            g('empathy')              * 0.20 +
            g('nurturing')            * 0.15 +
            g('loyalty')              * 0.10 +
            gods.Direct_Resource      * 0.15 +
            gods.Indirect_Resource    * 0.10 +
            A                         * 0.30     // bija riasec.S*0.20 + A*0.10 — riasec.S dominē patīkamība, dublikāts sapludināts raw A
        ),
        challenge: round(
            g('ambition')             * 0.20 +
            g('assertive')            * 0.15 +
            g('perseverance')         * 0.10 +
            gods.Seven_Killings       * 0.20 +
            gods.Rob_Wealth           * 0.10 +
            riasec.I                  * 0.10 +
            riasec.E                  * 0.10 +
            Ns                        * 0.05
        ),
        lifestyle: round(
            (100 - g('perfectionism')) * 0.15 +
            A                          * 0.15 +
            g('diplomacy')             * 0.10 +
            g('social')                * 0.10 +
            gods.Indirect_Resource     * 0.20 +
            gods.Friend                * 0.10 +
            (100 - g('ambition'))      * 0.10 +
            (100 - controlDrive)       * 0.10
        )
    };

    const allAnchors = ANCHOR_KEYS
        .map(key => ({
            key,
            score: anchorScores[key],
            ...ANCHORS_META[key]
        }))
        .sort((a, b) => b.score - a.score);

    // ── Cynefin domēnu skori ─────────────────────────────────────────────────

    const cynefinScores = {
        simple: round(
            g('traditional')          * 0.20 +
            g('conscient')            * 0.20 +
            g('categorical')          * 0.15 +
            (100 - g('risk'))         * 0.15 +
            gods.Direct_Resource      * 0.15 +
            gods.Direct_Officer       * 0.15
        ),
        complicated: round(
            g('analytical')           * 0.25 +
            g('hyperfocus')           * 0.20 +
            g('perfectionism')        * 0.10 +
            g('abstract')             * 0.10 +
            gods.Eating_God           * 0.20 +
            riasec.I                  * 0.15
        ),
        complex: round(
            g('creativity')           * 0.20 +
            g('inspiration')          * 0.15 +
            g('abstract')             * 0.15 +
            O                         * 0.25 +   // bija 0.15 + riasec.A*0.10 — riasec.A dominē atvērtība, dublikāts sapludināts raw O
            gods.Indirect_Wealth      * 0.15 +
            gods.Indirect_Resource    * 0.10
        ),
        chaotic: round(
            g('assertive')            * 0.20 +
            g('initiative')           * 0.15 +
            g('risk')                 * 0.15 +
            gods.Seven_Killings       * 0.20 +
            gods.Rob_Wealth           * 0.15 +
            Ns                        * 0.10 +
            warmCharisma              * 0.05
        )
    };

    const cynefinSorted = Object.entries(cynefinScores)
        .sort((a, b) => b[1] - a[1]);
    const cynefinPrimary = cynefinSorted[0][0];

    // ── Kahneman S1 vs S2 ────────────────────────────────────────────────────

    const s1Raw = round(
        (100 - g('perfectionism'))    * 0.15 +
        (100 - g('analytical'))       * 0.15 +
        g('assertive')                * 0.15 +
        E                             * 0.15 +
        (100 - C)                     * 0.10 +
        gods.Rob_Wealth               * 0.15 +
        gods.Seven_Killings           * 0.15
    );
    const s2Raw = round(
        g('analytical')               * 0.20 +
        g('hyperfocus')               * 0.15 +
        g('perfectionism')            * 0.15 +
        C                             * 0.15 +
        gods.Eating_God               * 0.15 +
        gods.Direct_Resource          * 0.10 +
        (100 - g('risk'))             * 0.10
    );

    // Normalizē uz polaritāti (summējas 100%); degeneratīvā 0+0 gadījumā — 50/50
    const total = s1Raw + s2Raw;
    const s1pct = total > 0 ? Math.round((s1Raw / total) * 100) : 50;
    const s2pct = 100 - s1pct;
    // Neizšķirtības zona: ja starpība ≤ 8pp (46–54%), profils ir adaptīvs —
    // nav godīgi piespiest vienu dominanti, jo cilvēks reāli pārslēdzas.
    const kahnemanGap = Math.abs(s1pct - s2pct);
    const kahnemanDominant = kahnemanGap <= 8 ? 'adaptive' : (s1pct > s2pct ? 's1' : 's2');

    // ── Kapacitātes naratīvs (sintēze pār 4 signāliem) ──────────────────────
    // Apvieno primāro enkuru + Cynefin domēnu + līderības tipu + Kahneman stilu
    // vienā stratēģiskā teikumā — tas ir 2. sadaļas "vienojošais stāsts".

    const primaryAnchor = allAnchors[0];
    const primaryCynefin = CYNEFIN_META[cynefinPrimary];
    const leaderKey = leadership?.primary?.key;
    const leaderPhrase = LEADERSHIP_NARRATIVES[leaderKey] || 'unikāls hibrīds vadības stils';
    const kahnemanMeta = KAHNEMAN_META[kahnemanDominant];

    // Cynefin/Kahneman koherences pārbaude: vai lēmumu stils saskan ar vidi?
    //   S1 (intuīcija) + chaotic/complex = aligned (krīze prasa instinktu)
    //   S2 (analīze)   + simple/complicated = aligned (rāmis prasa analīzi)
    //   Pretējie gadījumi = kompensācija (lēmumu stils balansē vides prasību)
    const intuitiveDomains = ['chaotic', 'complex'];
    const isAligned =
        (kahnemanDominant === 's1' && intuitiveDomains.includes(cynefinPrimary)) ||
        (kahnemanDominant === 's2' && !intuitiveDomains.includes(cynefinPrimary));
    const decisionLinker = isAligned
        ? 'kas pastiprina šo stratēģisko konfigurāciju'
        : 'kas kompensē vides prasību un nodrošina iekšējo balansu';

    const narrativeParagraph =
        `Šī profila galvenais karjeras dzinējs ir ${primaryAnchor.narrativeDrive}. ` +
        `Šis dzinējs visdabiskāk atbrīvojas ${primaryCynefin.narrativeHabitat}. ` +
        `Šādā vidē izpildes mehānisms veidojas kā ${leaderPhrase}. ` +
        (kahnemanDominant === 'adaptive'
            ? `Lēmumus profils pieņem ${kahnemanMeta.narrativeStyle} — tas ir situācijas inteliģences rādītājs, ne neskaidrības pazīme.`
            : `Lēmumus profils pieņem pārsvarā ${kahnemanMeta.narrativeStyle}, ${decisionLinker}.`);

    // ── Kapacitātes robeža (vājākais enkurs = izdegšanas trigeris) ──────────

    const boundary = allAnchors[allAnchors.length - 1];

    // ── Datu pārklājums (ticamības signāls) ──────────────────────────────────
    // Skori lieto ?? 50 noklusējumus trūkstošiem laukiem, tāpēc nepilnīgs
    // profils izvadā izskatās tikpat pārliecināti kā pilns. Šeit fiksējam, cik
    // daudz REĀLO ieejas datu tiešām bija, lai UI var to godīgi parādīt.
    const hasBigFive    = !!(ocean?.traits?.length);
    const hasFacets     = personality.some(cat => cat.id !== 'ocean' && (cat.traits || []).length > 0);
    const hasBazi       = Object.keys(bazi?.gods || {}).length > 0;
    const hasLeadership = !!leadership && [leadership.warmCharisma, leadership.coldCharisma, leadership.controlDrive]
        .some(v => v != null);

    // Personība ir dominējošais signāls enkuru formulās; BaZi un līderība —
    // koriģējošie. Sver pārklājumu attiecīgi (summa = 100).
    let coverage = 0;
    if (hasBigFive)    coverage += 45;
    if (hasFacets)     coverage += 25;
    if (hasBazi)       coverage += 20;
    if (hasLeadership) coverage += 10;
    const confidenceLevel = coverage >= 80 ? 'high' : coverage >= 50 ? 'medium' : 'low';

    return {
        primary:    primaryAnchor,
        secondary:  allAnchors[1],
        boundary,
        allAnchors,
        isAligned,   // lēmumu stils saskan ar primāro vidi (lieto "lēmumu špikeris")
        narrative: {
            paragraph: narrativeParagraph,
            components: {
                drive:    primaryAnchor.narrativeDrive,
                habitat:  primaryCynefin.narrativeHabitat,
                role:     leaderPhrase,
                style:    kahnemanMeta.narrativeStyle
            }
        },
        cynefin: {
            primary: cynefinPrimary,
            primaryMeta: CYNEFIN_META[cynefinPrimary],
            profile: cynefinScores,
            meta: CYNEFIN_META
        },
        kahneman: {
            s1pct,
            s2pct,
            dominant: kahnemanDominant,
            dominantMeta: KAHNEMAN_META[kahnemanDominant],
            meta: KAHNEMAN_META
        },
        confidence: {
            coverage,
            level: confidenceLevel,
            inputs: { bigFive: hasBigFive, facets: hasFacets, bazi: hasBazi, leadership: hasLeadership }
        }
    };
}
