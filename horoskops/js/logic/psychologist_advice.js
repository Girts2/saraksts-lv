// horoskops/js/logic/psychologist_advice.js
// ─────────────────────────────────────────────────────────────────────────────
// Virtuālais ekstra-klases ģimenes psihologs.
// Sagatavo PERSONALIZĒTAS attiecību uzlabošanas rekomendācijas KATRAM partnerim
// atsevišķi, balstoties uz:
//   (a) abu KOPĒJO attiecību dinamiku — comp 3 asis (emocijas/vērtības/komunikācija),
//       kopējais Ashta Kuta rezultāts un Bhakut/Nadi došas;
//   (b) katra cilvēka INDIVIDUĀLO psiholoģisko profilu — profile.relationshipDynamics
//       (Bowlby piesaiste, Gotmana 4 jātnieki, sinerģijas zonas, berzes punkti).
//
// Rekomendācijas SEKO ievaddatu izmaiņām ar 5% SOLI: visi vadošie procenti tiek
// kvantēti uz tuvāko 5% (q5), un kvalitatīvo joslu sliekšņi ir saskaņoti ar 5%
// režģi — tāpēc katrs 5% lēciens datos var nomainīt rekomendāciju.
//
// Noklusējums: vīrietis = KREISĀ kolonna, sieviete = LABĀ. Citā dzimumu variācijā
// vīrietis joprojām paliek pa kreisi; viena dzimuma pārim — 1. persona pa kreisi.

import { calculateRelationshipDynamics } from './relationship_dynamics.js?v=4';

// ── 5% soļa kvantēšana ───────────────────────────────────────────────────────
const STEP = 5;
const clampPct = v => Math.max(0, Math.min(100, v || 0));
export const q5 = v => Math.round(clampPct(v) / STEP) * STEP;   // → tuvākais 5%

// ── Piesaistes (Bowlby) personalizētie ieteikumi ─────────────────────────────
const ATTACHMENT_ADVICE = {
    secure: {
        edge: 'Tu esi attiecību enkurs — tava nervu sistēma paliek samērā mierīga arī partnera vētrā.',
        practice: 'Izmanto šo stabilitāti apzināti: krīzē esi tas, kas pirmais piedāvā kontaktu, nevis gaida. Tava drošā klātbūtne ļauj otram noregulēties — tikai neuztver to kā pašsaprotamu un nepārņem visu emocionālo darbu uz sevi.'
    },
    anxious: {
        edge: 'Tava sistēma drošību sajūt caur partnera klātbūtni un atsaucību; klusums viegli tiek lasīts kā draudi.',
        practice: 'Pirms reaģē uz "atstātības" signālu, dod sev 90 sekundes un pārbaudi: tie ir fakti vai trauksme? Lūdz apstiprinājumu tieši un mierīgi ("man šobrīd vajag dzirdēt, ka mēs esam labi"), nevis caur pārmetumu, klusēšanu vai pārbaudīšanu.'
    },
    avoidant: {
        edge: 'Tu emocijas regulē iekšēji un tuvumu reizēm piedzīvo kā autonomijas spiedienu.',
        practice: 'Kad gribas atrauties, nepazūdi klusumā — pasaki to skaļi ("man vajag stundu vienatnē, tad atgriežos"). Pieteikta telpa partnerim ir droša; pēkšņa emocionāla pazušana rada paniku. Treniņš: viena maza emocionālā atklāsme partnerim katru dienu.'
    },
    disorganized: {
        edge: 'Tava piesaiste vienlaikus pieprasa un atbaida tuvumu — iekšējais kompass krīzē var saplosīties.',
        practice: 'Balsties uz paredzamiem rituāliem (fiksēts kopā-laiks, skaidri "atgriešanās" solījumi pēc pauzes). Apsver individuālu vai pāru terapiju — tava sistēma visvairāk iegūst no ārēja, droša rāmja, kurā pieredzē mācīties, ka tuvums nav drauds.'
    }
};

// Kā šī persona var strādāt ar pāra vājāko asi — leņķis caur viņas piesaisti
const AXIS_ANGLE = {
    secure:       'Tava daļa šeit: esi tas, kas iniciē un tur rāmi, kamēr otrs noregulējas.',
    anxious:      'Tava daļa šeit: pašregulācija — noregulē savu trauksmi PIRMS sarunas, lai tā nekļūst par spiedienu.',
    avoidant:     'Tava daļa šeit: pietuvošanās — apzināti pasniedz par vienu soli vairāk klātbūtnes, nekā ir ērti.',
    disorganized: 'Tava daļa šeit: paredzamība — vienojies par fiksētu rituālu, kas šo jomu uztur neatkarīgi no dienas emocijām.'
};

// ── Pāra asu graduētie ieteikumi (joslas sliekšņi = 5% režģis) ────────────────
const AXIS_ADVICE = {
    emotional: {
        key: 'emotional', label: 'Dvēseles rezonanse', icon: '🫂',
        tiers: [
            { upto: 35, lvl: 'kritiska',  text: 'Emocionālā saikne šobrīd ir trausla. Sāciet ar pašu pamatu: 10 min dienā bez telefoniem, kur katrs pastāsta, kā tiešām jutās — bez risinājumiem, tikai dzirdēt un atspoguļot.' },
            { upto: 55, lvl: 'zema',      text: 'Emocionālā saikne ir, bet nepilnīga. Praktizējiet "emociju nosaukšanu": kad otrs ir saviļņots, vispirms atspoguļojiet jūtu ("redzu, ka tev sāp"), un tikai tad risiniet saturu.' },
            { upto: 70, lvl: 'vidēja',    text: 'Laba emocionālā bāze. Uzturiet to ar ikdienas "pieslēgšanās rituāliem" — sasveicināšanās un atvadu apskāviens, vakara 5 min par to, kā pagāja diena.' },
            { upto: 85, lvl: 'augsta',    text: 'Spēcīga emocionālā rezonanse. Sargājiet to no rutīnas: ik pa laikam ieplānojiet jaunu kopīgu pieredzi, kas atjauno tuvību un neļauj saikni uztvert kā pašsaprotamu.' },
            { upto: 101, lvl: 'izcila',   text: 'Izcila emocionālā saplūšana. Riska zona ir saplūšana bez robežām — apzināti uzturiet arī individuālo telpu un draugus, lai tuvums nekļūst par atkarību.' }
        ]
    },
    values: {
        key: 'values', label: 'Ģimenes vērtības', icon: '🧭',
        tiers: [
            { upto: 35, lvl: 'kritiska',  text: 'Nākotnes redzējumi būtiski atšķiras. Veltiet vienu vakaru "lielajai bildei": bērni, nauda, dzīvesvieta, karjera — uzrakstiet katrs atsevišķi un salīdziniet, kur sakrīt un kur ne.' },
            { upto: 55, lvl: 'zema',      text: 'Vērtībās ir gan kopīgais, gan atšķirīgais. Vienojieties par 3 ne-pārkāpjamiem principiem, kas jums abiem svēti; pārējā apzināti dodiet viens otram brīvību.' },
            { upto: 70, lvl: 'vidēja',    text: 'Vērtības lielā mērā sakrīt. Reizi ceturksnī pārrunājiet mērķus un budžetu, lai pārliecinātos, ka virzāties vienā virzienā, pirms rodas klusa neapmierinātība.' },
            { upto: 85, lvl: 'augsta',    text: 'Spēcīga vērtību saskaņa. Izmantojiet to kā komandu — uzstādiet vienu kopīgu ilgtermiņa projektu (mājoklis, ceļojums, ģimene), kas materializē jūsu kopīgo virzienu.' },
            { upto: 101, lvl: 'izcila',   text: 'Gandrīz pilnīga vērtību vienotība. Uzmanieties no "domu lasīšanas" — pat ja parasti sakrītat, lielos lēmumus tomēr izrunājiet, lai pieņēmumi nepārvēršas par pārpratumiem.' }
        ]
    },
    interaction: {
        key: 'interaction', label: 'Komunikācija un konflikti', icon: '🗣️',
        tiers: [
            { upto: 35, lvl: 'kritiska',  text: 'Komunikācija ir šī pāra lielākais izaicinājums. Ieviesiet konfliktu protokolu: 24h pauze pirms svarīgiem lēmumiem un noteikums "viens runā, otrs klausās, tad mainās lomām".' },
            { upto: 55, lvl: 'zema',      text: 'Saziņā mēdz būt pārpratumi. Treniņš: pirms atbildi, pārfrāzē dzirdēto ("tu saki, ka...") — šis viens paradums novērš aptuveni pusi strīdu.' },
            { upto: 70, lvl: 'vidēja',    text: 'Funkcionāla saziņa. Stipriniet to ar nedēļas "check-in": kas šonedēļ izdevās, kas kaitināja, ko vajag nākamnedēļ — 15 min, fiksēts laiks.' },
            { upto: 85, lvl: 'augsta',    text: 'Ļoti laba saziņa. Sargieties no paviršības — turpiniet klausīties tikpat uzmanīgi kā attiecību sākumā, neaizpildot otra teikumus paši.' },
            { upto: 101, lvl: 'izcila',   text: 'Gandrīz telepātiska saziņa. Riska zona — pieņēmumi; ik pa laikam pārbaudiet, vai "saprašana no pusvārda" tiešām atbilst tam, ko otrs domāja.' }
        ]
    }
};

function axisTier(axisKey, pct) {
    const a = AXIS_ADVICE[axisKey];
    const p = q5(pct);                       // 5% solis
    const tier = a.tiers.find(t => p < t.upto) || a.tiers[a.tiers.length - 1];
    return { ...tier, pct: p, label: a.label, icon: a.icon };
}

// ── Asu izvilkšana no comp ───────────────────────────────────────────────────
function axesOf(comp) {
    return [
        { key: 'emotional',   label: 'Dvēseles rezonanse',         pct: q5(comp?.emotional?.pct) },
        { key: 'values',      label: 'Ģimenes vērtības',           pct: q5(comp?.values?.pct) },
        { key: 'interaction', label: 'Komunikācija un konflikti',  pct: q5(comp?.interaction?.pct) }
    ];
}

// ── Viena cilvēka rekomendāciju komplekts ────────────────────────────────────
function personAdvice(profile, comp, weakAxisKey, genderLabel) {
    const dyn = profile?.relationshipDynamics
              || (profile ? calculateRelationshipDynamics(profile) : null);

    const att   = dyn?.attachment?.primary || { key: 'secure', lv: 'Drošā piesaiste', summary: '' };
    const horse = dyn?.horsemen?.top?.[0]  || null;
    const syn   = dyn?.synergy?.[0]        || null;
    const fric  = dyn?.friction?.[0]       || null;

    const attAdv = ATTACHMENT_ADVICE[att.key] || ATTACHMENT_ADVICE.secure;
    const axis   = axisTier(weakAxisKey, comp?.[weakAxisKey]?.pct);
    const angle  = AXIS_ANGLE[att.key] || AXIS_ANGLE.secure;

    const read = `Kā ${genderLabel} šajās attiecībās: pamata režīms — <b>${att.lv}</b>.`
        + (syn  ? ` Dabiskā dāvana — <b>${syn.lv}</b>.` : '')
        + (horse ? ` Krīzē jāuzmanās no paterna <b>${horse.lv}</b>.` : '');

    const cards = [
        {
            icon: att.icon || '🧭',
            title: `Tava piesaiste · ${att.lv}`,
            body: `${attAdv.edge} ${attAdv.practice}`,
            tone: 'self'
        }
    ];

    if (horse) cards.push({
        icon: horse.icon || '🛡',
        title: `Konfliktā · antidots pret "${horse.lv}"`,
        body: `Krīzē tev visdrīzāk aktivizējas: ${horse.pattern} <b>Antidots:</b> ${horse.antidote}`,
        tone: 'warn'
    });

    cards.push({
        icon: axis.icon,
        title: `Kopējais fokuss · ${axis.label} (${axis.pct}%)`,
        body: `${axis.text} <span class="pa-angle">${angle}</span>`,
        tone: 'focus'
    });

    if (syn) cards.push({
        icon: syn.icon || '✨',
        title: `Tava dāvana · ${syn.lv}`,
        body: `${syn.description} Apzināti ienes to ikdienā — tā ir tava daļa, ar ko balstīt pāri, īpaši grūtākos brīžos.`,
        tone: 'gift'
    });

    const caution = fric ? `Uzmanies no sava berzes punkta — <b>${fric.lv}</b>: ${fric.description}.` : '';

    return { attachment: att, read, cards, caution };
}

// ── Galvenā eksporta funkcija ────────────────────────────────────────────────
export function generatePsychologistAdvice(p1, p2, comp, gender1 = 'M', gender2 = 'F') {
    if (!p1 || !p2 || !comp) return null;

    const g1 = gender1 || 'M';
    const g2 = gender2 || 'F';

    // Kreisā/labā kolonnu sadale pēc dzimuma: vīrietis pa kreisi, sieviete pa labi.
    // Citā variācijā vīrietis paliek pa kreisi; viena dzimuma pārim — 1. persona pa kreisi.
    let leftP, rightP, leftG, rightG, leftPersona, rightPersona;
    if (g1 !== g2) {
        if (g1 === 'M') { leftP = p1; rightP = p2; leftG = g1; rightG = g2; leftPersona = 1; rightPersona = 2; }
        else            { leftP = p2; rightP = p1; leftG = g2; rightG = g1; leftPersona = 2; rightPersona = 1; }
    } else {
        leftP = p1; rightP = p2; leftG = g1; rightG = g2; leftPersona = 1; rightPersona = 2;
    }

    const labelOf = g => g === 'M' ? 'vīrietim' : 'sievietei';
    const titleOf = g => g === 'M' ? 'Vīrietis' : 'Sieviete';

    // Pāra vājākā ass — kopējais fokuss (5% solī)
    const axes = axesOf(comp);
    const weak = [...axes].sort((a, b) => a.pct - b.pct)[0];
    const strong = [...axes].sort((a, b) => b.pct - a.pct)[0];

    const iconOf = g => g === 'M' ? '♂' : '♀';
    const left  = {
        gender: leftG, persona: leftPersona, icon: iconOf(leftG),
        title: titleOf(leftG), ...personAdvice(leftP, comp, weak.key, labelOf(leftG))
    };
    const right = {
        gender: rightG, persona: rightPersona, icon: iconOf(rightG),
        title: titleOf(rightG), ...personAdvice(rightP, comp, weak.key, labelOf(rightG))
    };

    const overallPct = q5(Math.round((comp.kutaScore / 36) * 100));

    return {
        step: STEP,
        axes,                 // visi 3, jau 5% solī
        focus: weak,          // vājākā ass = galvenais fokuss
        strength: strong,     // stiprākā ass = balsts
        overallPct,
        left,
        right
    };
}
