// Executive Summary — Personības Lietošanas Instrukcija
// ───────────────────────────────────────────────────────
// Apvieno galvenos secinājumus no visiem 5 akadēmiskajiem moduļiem
// (Logoterapija, Šeina enkuri, Cynefin, Boulbija piesaiste, Gotmana
// jātnieki) vienā 4-5 teikumu naratīvā, kas kalpo kā profila kompass.
//
// Lasītājs šo bloku redz PIRMS visām 6 sadaļām — tas ir "personības
// lietošanas instrukcija" ar 30 sekunžu lasāmību.

// ── Šeina enkuru → sadarbības padoma kartē ───────────────────────────────────

export const ANCHOR_COLLABORATION = {
    technical: {
        give:    'iespēju iedziļināties dziļas ekspertīzes problēmās bez vadības formalitātēm',
        avoid:   'spiediens uz tīri menedžera lomu vai prasība pēc sekla, plaša pārklājuma uz dziļuma rēķina',
        verb:    'iedot dziļumu, ne plašumu'
    },
    management: {
        give:    'skaidru autoritāti, atbildības robežas un institucionālo atbalstu',
        avoid:   'neskaidra struktūra vai pakļautība vājākam vadītājam',
        verb:    'iedot skaidru komandēšanas līniju'
    },
    autonomy: {
        give:    'rīcības brīvību ar skaidriem rezultātiem, bez mikromenedžmenta',
        avoid:   'pārmērīga uzraudzība, atskaitīšanās un struktūras prasības',
        verb:    'iedot autonomiju ar skaidru rezultāta gaidīšanu'
    },
    security: {
        give:    'skaidru ilgtermiņa ceļvedi, stabilus apstākļus un paredzamību',
        avoid:   'pēkšņas pārmaiņas vai nenoteiktība bez paskaidrojuma',
        verb:    'iedot stabilitāti un paredzamību'
    },
    entrepreneurial: {
        give:    'resursus un vīzijas atbalstu — bez procedūru spaida',
        avoid:   'birokrātija, lēna lēmumu pieņemšana, formāli protokoli',
        verb:    'iedot resursus, ne procedūras'
    },
    service: {
        give:    'darba saikni ar lielāku mērķi un sabiedrisku ietekmi',
        avoid:   'darba reducēšana līdz transakcijai vai pakalpojumam bez nozīmes',
        verb:    'iedot misiju, ne tikai algu'
    },
    challenge: {
        give:    'sarežģītākās problēmas un neapšaubāmus izaicinājumus',
        avoid:   'rutīnas uzdevumi vai stabils, paredzams darbs bez izaicinājuma',
        verb:    'iedot grūtāko problēmu, ne komfortu'
    },
    lifestyle: {
        give:    'elastīgu grafiku un darba-dzīves robežu cieņu',
        avoid:   'prasība pēc pilnas identitātes pakļaušanas darbam',
        verb:    'iedot elastīgumu un robežu'
    }
};

// ── Piesaistes stila → sadarbības niansē kartē ───────────────────────────────

export const ATTACHMENT_HINTS = {
    secure: {
        comm:    'tieša, atvērta komunikācija ar reāliem konfliktiem un to risināšanu',
        crisis:  'meklēs kontaktu un kopīgu risinājumu',
        warning: null
    },
    anxious: {
        comm:    'biežs, skaidrs apstiprinājums un emocionālā pieejamība',
        crisis:  'pieprasīs apstiprinājumu un klātbūtni',
        warning: 'atstātība un klusums tiek interpretēti kā draudi'
    },
    avoidant: {
        comm:    'cieņa pret klusumu un autonomijas vajadzību, bez emocionālā spiediena',
        crisis:  'sašaurinās un izstājas no kontakta',
        warning: 'emocionālā pieprasījuma intensitāte izraisīs distancēšanos'
    },
    disorganized: {
        comm:    'augsta paredzamība, emocionāls briedums un nepersonīga izturība pret uzbrukumiem',
        crisis:  'vienlaikus pieprasīs un atbaidīs tuvumu',
        warning: 'krīzes reakcija var būt pretrunīga un dezorientējoša'
    }
};

// ── Galvenā eksportējamā funkcija ────────────────────────────────────────────

export function buildExecutiveSummary(profile) {
    const dharma         = profile?.existentialAudit?.macro?.dharma || null;
    const primaryAnchor  = profile?.careerAnchors?.primary || null;
    const cynefinPrim    = profile?.careerAnchors?.cynefin?.primaryMeta || null;
    const attachment     = profile?.relationshipDynamics?.attachment?.primary || null;
    const topHorseman    = profile?.relationshipDynamics?.horsemen?.top?.[0] || null;
    const macroPhase     = profile?.timing?.macro?.phaseMeta || null;
    const rhythmType     = profile?.existentialAudit?.mezo?.primary || null;

    // Ja kāds no centrālajiem moduļiem nav pieejams — graceful fallback
    if (!dharma || !primaryAnchor || !attachment) {
        return {
            paragraph: 'Profila kopsavilkums vēl nav pieejams — daži centrālie aprēķini nav pabeigti.',
            highlights: null
        };
    }

    const collabRules = ANCHOR_COLLABORATION[primaryAnchor.key] || ANCHOR_COLLABORATION.technical;
    const attachHint  = ATTACHMENT_HINTS[attachment.key] || ATTACHMENT_HINTS.secure;

    // ── Naratīva sintēze (4-5 teikumi) ───────────────────────────────────────
    // Locījumu konvencija (2026-06-11): give = AKUZATĪVS (objekts pie "dot viņam X"),
    // avoid = NOMINATĪVS (priekšmets pie "Visvairāk kaitē X"); pārējās entītijas
    // pēc kola/iekavās nominatīvā — tā šablonu šuvēs nerodas locīšanas kļūdas.

    const sentence1 = `Šis ir cilvēks, kura galvenā dzīves misija — ${dharma.mission.toLowerCase()}.`;

    const sentence2 = `Lai šo misiju realizētu, profila primārais enkurs ir <b>${primaryAnchor.lv}</b>` +
        (cynefinPrim ? ` (ideālais vides tips — <b>${cynefinPrim.lv}</b>)` : '') +
        ` — bez šī enkura iekšējais dzinējs sāks slāpēties.`;

    const sentence3 = `Attiecību bāzes režīms — <b>${attachment.lv}</b>` +
        (topHorseman ? `; krīzes apstākļos galvenais konfliktu paterns ir <b>${topHorseman.lv}</b>` : '') + '.';

    const sentence4 = `Sadarbības atslēga: dot viņam ${collabRules.give}. ` +
        `Visvairāk kaitē ${collabRules.avoid}. ` +
        `Komunikācijā tas nozīmē: ${attachHint.comm}` +
        (attachHint.warning ? ` (${attachHint.warning})` : '') + '.';

    const phaseSentence = macroPhase
        ? `Pašreizējā profila biznesa fāze — <b>${macroPhase.lv}</b>; šajā periodā stratēģijai jāatbilst nevis vecajai loģikai, bet šī laika prasībām.`
        : '';

    const paragraph = [sentence1, sentence2, sentence3, sentence4, phaseSentence].filter(Boolean).join(' ');

    // ── Atslēgvārdu chip dati ───────────────────────────────────────────────

    const highlights = {
        dharma:        dharma.lv,
        dharmaColor:   dharma.color,
        dharmaIcon:    dharma.icon,
        anchor:        primaryAnchor.lv,
        anchorColor:   primaryAnchor.color,
        anchorIcon:    primaryAnchor.icon,
        cynefin:       cynefinPrim?.lv || null,
        cynefinColor:  cynefinPrim?.color || null,
        cynefinIcon:   cynefinPrim?.icon || null,
        attachment:    attachment.lv,
        attachmentColor: attachment.color,
        attachmentIcon: attachment.icon,
        horseman:      topHorseman?.lv || null,
        horsemanColor: topHorseman?.color || null,
        horsemanIcon:  topHorseman?.icon || null,
        phase:         macroPhase?.lv || null,
        phaseColor:    macroPhase?.color || null,
        phaseIcon:     macroPhase?.icon || null,
        rhythm:        rhythmType?.lv || null,
        rhythmColor:   rhythmType?.color || null,
        rhythmIcon:    rhythmType?.icon || null,
        collabVerb:    collabRules.verb,
        crisisPattern: attachHint.crisis
    };

    return { paragraph, highlights };
}
