// Praktiskie Scenāriji — Cross-modulu sintēze konkrētām dzīves situācijām
// ───────────────────────────────────────────────────────────────────────
// Iepriekšējās 6 sadaļas strukturētas pēc PSIHOLOĢIJAS SKOLĀM (Jungs, Šeins,
// Boulbijs, Frankls). Šī, 7. sadaļa, ir SCENĀRIJU bāzēta — apvieno konkrētus
// laukus no visiem moduļiem 4 reālās dzīves situācijās:
//
//   🔥 Krīze un augsts stress
//   🎯 Motivācija un iesaiste
//   🤝 Komandā un sadarbībā
//   💸 Lielo lēmumu pieņemšanas brīdī
//
// Lasītājs/HR/koučs šeit atrod tiešas, instrumentālas atbildes uz "Kā ar šo
// cilvēku rīkoties, kad notiek X?" — bez nepieciešamības pašam savienot 6
// dažādu sadaļu izvilkumus.

// ── Scenāriju metadati ──────────────────────────────────────────────────────

const SCENARIO_META = {
    crisis: {
        title: 'Krīze un augsts stress',
        icon:  '🔥',
        color: '#ef4444',
        hook:  'Kad sistēma sabrūk, lēmumi jāpieņem ātri un resursi ir ierobežoti'
    },
    motivation: {
        title: 'Motivācija un iesaiste',
        icon:  '🎯',
        color: '#22c55e',
        hook:  'Kas šim profilam aktivizē iekšējo dzinēju, un kas to noslāpē'
    },
    team: {
        title: 'Komandā un sadarbībā',
        icon:  '🤝',
        color: '#3b82f6',
        hook:  'Kāda loma šim profilam dabiska, ar kuru tipu sader, ko nedrīkst gaidīt'
    },
    decisions: {
        title: 'Lielo lēmumu pieņemšanas brīdī',
        icon:  '💸',
        color: '#a855f7',
        hook:  'Kāds lēmumu pieņemšanas algoritms šim profilam dabisks, un kur slēpjas neredzamie aklie plankumi'
    }
};

// ── Helperi: drošas piekļuves un teksta normalizācija ────────────────────────

const lc = (s) => (typeof s === 'string' ? s.toLowerCase() : '');
const safe = (v, fallback = '—') => (v != null && v !== '') ? v : fallback;
const cleanEnd = (s) => (typeof s === 'string' ? s.replace(/\s*\.\s*$/, '') : s);

// ── Scenāriju ģeneratori ────────────────────────────────────────────────────

function buildCrisisScenario(profile) {
    const anchors  = profile?.careerAnchors;
    const cynefin  = anchors?.cynefin?.primaryMeta;
    const kahneman = anchors?.kahneman?.dominantMeta;
    const horseman = profile?.relationshipDynamics?.horsemen?.top?.[0];
    const friction = profile?.relationshipDynamics?.friction?.[0];
    const shadow   = profile?.relationshipDynamics?.shadow;
    const boundary = anchors?.boundary;
    const flow     = profile?.existentialAudit?.micro;

    const narrative =
        `Krīzes apstākļos šim profilam aktivizējas dominējošā lēmumu pieņemšanas sistēma: <b>${kahneman?.lv || 'jaukta'}</b>. ` +
        `Labākie lēmumi rodas vidē, kas atbilst <b>${cynefin?.lv || 'paredzamai struktūrai'}</b>. ` +
        (horseman
            ? `Stresa virsotnē galvenais attiecību konfliktu paterns ir <b>${horseman.lv}</b>: ${cleanEnd(horseman.pattern)}. `
            : '') +
        `Tā nav vājuma pazīme, bet sistēmas dabiskā konfigurācija — apzināti vadīts, šis paterns kļūst par stratēģisku resursu; neapzināti — par krīzes paternu, kas sabojā situāciju.`;

    const dos = [
        cynefin ? `Ļauj viņam strādāt pieejā, kas atbilst <b>${cynefin.lv}</b>, ne universālajā "atrisini visu uzreiz" — tā ir vide, kur viņa krīzes kapacitāte ir visaugstākā` : null,
        horseman ? `Pirms galvenā lēmuma dod viņam pauzi — Gotmana antidots: ${cleanEnd(horseman.antidote)}` : null,
        anchors?.primary ? `Atgādini viņam galveno robežu — <b>${anchors.primary.lv}</b>: krīzē nevirzīt lēmumus, kas to apdraud` : null,
        flow?.restart ? `Plānoti iebūvē viņa grafikā atjaunošanās logu: ${cleanEnd(flow.restart)}` : null
    ].filter(Boolean);

    const donts = [
        boundary ? `Neuztici viņam lomu, kas trāpa kapacitātes robežā — krīzē tā izsmels resursus ātrāk: ${cleanEnd(boundary.burnoutTrigger)}` : null,
        friction ? `Neaktivizē šādu paternu: <b>${friction.lv}</b> — krīzē tas pavairos problēmu (${cleanEnd(friction.description)})` : null,
        shadow ? `Neuztver viņa pirmās emocionālās reakcijas kā galīgo nostāju — dziļais ēnas paterns (${cleanEnd(shadow).slice(0, 100)}…) aktivizējas tieši stresa pīķī` : null,
        `Nespied viņu krīzē vienlaikus izšķirt karjeras un attiecību lielos jautājumus — kapacitāte sašaurinās, un viens domēns sabojā otru`
    ].filter(Boolean);

    const keyInsight = cynefin && kahneman
        ? `Šis profils krīzē uzlabojas, kad lēmumu pieņemšanas stils (<b>${kahneman.lv}</b>) tiek izmantots vidē, kas atbilst <b>${cynefin.lv}</b> — pretēja konfigurācija izsmels resursus nelietderīgi`
        : `Krīzē šim profilam svarīgākais ir saglabāt savu dabisko lēmumu pieņemšanas algoritmu, nevis pielāgoties citu gaidām`;

    return { ...SCENARIO_META.crisis, narrative, dos, donts, keyInsight };
}

function buildMotivationScenario(profile) {
    const anchors    = profile?.careerAnchors;
    const dharma     = profile?.existentialAudit?.macro?.dharma;
    const synergy    = profile?.relationshipDynamics?.synergy?.[0];
    const alignment  = profile?.timing?.anchorAlignment;
    const phase      = profile?.timing?.macro?.phaseMeta;
    const flow       = profile?.existentialAudit?.micro;
    const boundary   = anchors?.boundary;

    const narrative =
        (dharma
            ? `Šī profila dziļākais motivācijas avots nāk no eksistenciālās misijas: ${cleanEnd(dharma.mission)}. `
            : '') +
        (anchors?.primary
            ? `Operatīvajā līmenī tas izpaužas kā primārā vajadzība: <b>${anchors.primary.lv}</b>. Bez šī enkura iekšējais dzinējs sāks slāpēties. `
            : '') +
        (alignment?.status === 'aligned'
            ? `Pašreizējais 10 gadu cikls (<b>${phase?.lv || 'pašreizējā fāze'}</b>) dabiski atbalsta šo dzinēju, tāpēc motivācijas augstas amplitūdas logs ir tagad.`
            : alignment?.status === 'conflict'
                ? `Taču pašreizējais 10 gadu cikls (<b>${phase?.lv || 'pašreizējā fāze'}</b>) konfliktē ar šo enkuru — motivācija būs pakļauta iekšējai velkmei pretrunā ar laika prasībām.`
                : `Pašreizējais 10 gadu cikls neitrāli pieņem šo dzinēju — motivācija prasīs apzinātu uzturēšanu.`);

    const dos = [
        dharma ? `Sasaisti viņa ikdienas uzdevumus ar dziļāko misiju — parādi, kā konkrētais projekts kalpo dzinējam: ${cleanEnd(dharma.mission).slice(0, 80)}…` : null,
        synergy ? `Izmanto viņa dabisko sinerģijas zonu (<b>${synergy.lv}</b>) kā motivācijas avotu — kad viņš jūtas iesprostots, palīdzi atgriezties pie tās` : null,
        flow?.morning ? `Atbalsti viņa rīta rutīnu: ${cleanEnd(flow.morning)}. Bez tās motivācija visu dienu velkas smagnēji` : null,
        anchors?.primary?.thrives ? `Meklē kontekstus, kuros profils uzplaukst: ${lc(anchors.primary.thrives)}. Tas ir dabiskais "uzlādes" stāvoklis` : null
    ].filter(Boolean);

    const donts = [
        boundary ? `Neuzliec lomas, kas balstās vājākajā enkurā (<b>${boundary.lv}</b>) — tās neaktivizē motivāciju, tikai izdegšanu` : null,
        anchors?.primary?.risks ? `Nepalaid garām enkura ēnas pusi — galvenie riski: ${lc(anchors.primary.risks).slice(0, 80)}…` : null,
        `Neizmanto ārējos motivatorus (alga, statuss, atzīšana) kā galveno dzinējspēku — šim profilam tie sniedz tikai daļēju enerģiju`,
        dharma?.emptySignal ? `Neignorē tukšuma signālu: ${cleanEnd(dharma.emptySignal)}. Tas ir agrīns brīdinājums par stratēģiskā virziena zaudēšanu` : null
    ].filter(Boolean);

    const keyInsight = dharma && anchors?.primary
        ? `Augstākā motivācija rodas, kad primārais enkurs (<b>${anchors.primary.lv}</b>) kalpo eksistenciālajai misijai (<b>${dharma.lv}</b>) — abas dimensijas jāuztur sinhroni`
        : `Šim profilam motivācija ir misijas un enkura sintēzes funkcija — viena bez otras dod tikai daļēju efektu`;

    return { ...SCENARIO_META.motivation, narrative, dos, donts, keyInsight };
}

function buildTeamScenario(profile) {
    const leadership = profile?.leadership;
    const anchors    = profile?.careerAnchors;
    const attachment = profile?.relationshipDynamics?.attachment?.primary;
    const synergy    = profile?.relationshipDynamics?.synergy?.[0];
    const friction   = profile?.relationshipDynamics?.friction?.[0];

    const leaderLv = leadership?.primary?.lv || 'unikāls hibrīds tips';
    const warmVsCold = (leadership?.warmCharisma ?? 50) > (leadership?.coldCharisma ?? 50)
        ? 'siltā harizma'
        : 'vēsā autoritāte';

    const narrative =
        `Komandā šī profila dabiskā loma — <b>${leaderLv}</b>, ar dominējošu ietekmes avotu: <b>${warmVsCold}</b>. ` +
        (anchors?.primary
            ? `Komandā profilam nepieciešams primārais enkurs (<b>${anchors.primary.lv}</b>) — ja loma to liedz, sākas iekšējā pretestība un izdegšana. `
            : '') +
        (attachment
            ? `Attiecību bāzes režīms ar kolēģiem — <b>${attachment.lv}</b>; tas nozīmē, ka tuvības un distances regulēšanai ir specifiska gramatika.`
            : '');

    const dos = [
        leadership?.primary?.strengths?.length
            ? `Izmanto profila stiprumus: ${leadership.primary.strengths.slice(0, 2).map(s => lc(s)).join('; ')}`
            : null,
        synergy ? `Pavadi lomas, kur prasīta sinerģijas zona, kas šim profilam dabiska — <b>${synergy.lv}</b>. Tā ir dāvana, ko viņš ienes komandā` : null,
        attachment?.partnerNeeds ? `Saderēs labākais ar kolēģiem, kuriem raksturīgs: ${cleanEnd(attachment.partnerNeeds)}` : null,
        anchors?.primary?.thrives ? `Iedod kontekstu, kur profils uzplaukst — ${lc(anchors.primary.thrives)}. Tas ir dabiskais komandas ekosistēmas tips` : null
    ].filter(Boolean);

    const donts = [
        leadership?.primary?.risks?.length
            ? `Nenoslogo profilu situācijās, kas aktivizē viņa riskus: ${leadership.primary.risks.slice(0, 2).map(r => lc(r)).join('; ')}`
            : null,
        friction ? `Nesagaidi, ka profils dabiski regulēs šādu paternu — <b>${friction.lv}</b>. Tas prasa apzinātu apkārtējās vides palīdzību` : null,
        anchors?.primary?.risks ? `Neuzdod uzdevumus, kas izaicina enkura robežas: ${cleanEnd(anchors.primary.risks).split(';')[0]}` : null,
        `Nepieņem viņa klusumu vai distancēšanos kā nelojalitāti — tas var būt tikai dabiskā piesaistes stila izpausme`
    ].filter(Boolean);

    const keyInsight = leadership?.primary && anchors?.primary
        ? `Šis profils komandā realizē maksimālo vērtību tikai dabiskās lomas (<b>${leaderLv}</b>) un primārā enkura (<b>${anchors.primary.lv}</b>) saglabāšanas apstākļos — citās konfigurācijās rodas brīvā kapacitāte, ko grūti iesaistīt`
        : `Komandas integrācija jābūvē ap šī profila dabisko lomu un primāro enkuru — nepiespiest "universālo darbinieku"`;

    return { ...SCENARIO_META.team, narrative, dos, donts, keyInsight };
}

function buildDecisionsScenario(profile) {
    const kahneman   = profile?.careerAnchors?.kahneman;
    const cynefin    = profile?.careerAnchors?.cynefin?.primaryMeta;
    const anchors    = profile?.careerAnchors;
    const timing     = profile?.timing;
    const transition = timing?.macro?.transition;
    const horseman   = profile?.relationshipDynamics?.horsemen?.top?.[0];

    const s1pct = kahneman?.s1pct ?? 50;
    const s2pct = kahneman?.s2pct ?? 50;
    const dominantMeta = kahneman?.dominantMeta;

    const narrative =
        `Lēmumu pieņemšanā šim profilam dabiski dominē <b>${dominantMeta?.lv || 'jaukts stils'}</b> (${s1pct}% intuīcija · ${s2pct}% analīze). ` +
        `Labākie lēmumi rodas vidē, kas atbilst <b>${cynefin?.lv || 'noteiktai struktūrai'}</b>. ` +
        (anchors?.primary
            ? `Lielajos lēmumos primāro enkuru (<b>${anchors.primary.lv}</b>) nedrīkst pakļaut īstermiņa spiedienam — ja tas notiek, lēmums pēc 2–3 gadiem izrādīsies stratēģiska kļūda. `
            : '') +
        (transition?.active
            ? `<b style="color:#fca5a5;">Brīdinājums:</b> profils šobrīd atrodas Jiao Yun pārejas zonā — šis nav īstais brīdis neatgriezeniskiem lielajiem lēmumiem (10+ gadu kontrakti, hipotēkas, identitātes pārmaiņas).`
            : '');

    const dos = [
        cynefin ? `Veido lēmumu pieņemšanas vidi, kas atbilst <b>${cynefin.lv}</b> — citas vides nedos optimālo signālu` : null,
        s1pct >= 60
            ? 'Pirms galvenā lēmuma dod vietu viņa intuīcijai — viens teikums "pirmā sajūta saka…" pirms analīzes ir vissvarīgākā datu daļa'
            : s2pct >= 60
                ? 'Dod laiku datu vākšanai un analīzei pirms lēmuma — steiga šajā sistēmā samazina lēmuma kvalitāti'
                : 'Palīdzi sabalansēt intuīciju un analīzi — abas sistēmas šim profilam ir vienlīdz aktīvas, viena bez otras dod nepilnīgu ainu',
        anchors?.primary ? `Pārbaudi katru lielāku lēmumu pret viņa primāro enkuru — <b>${anchors.primary.lv}</b>: ja lēmums to ilgtermiņā apdraud, jāatgriežas pie alternatīvām` : null,
        timing?.matrix?.dos?.[0] ? `Saskaņo lēmumu ar pašreizējās fāzes loģiku: ${lc(timing.matrix.dos[0])}` : null
    ].filter(Boolean);

    const donts = [
        transition?.active ? `Nevirzi neatgriezeniskus lielos lēmumus pārejas zonā — jānogaida vismaz 12 mēneši pēc fāzes maiņas, lai stratēģiskā perspektīva nostiprinās` : null,
        horseman?.key === 'defensiveness' ? `Rēķinies, ka aizsardzības režīmā viņš var noraidīt datus, kas runā pretī gribētajam lēmumam — palīdzi faktus nolikt uz galda neitrāli` : null,
        timing?.matrix?.donts?.[0] ? `Neignorē pašreizējās fāzes brīdinājumus: ${lc(timing.matrix.donts[0])}` : null,
        s1pct >= 60
            ? 'Neļauj augstas likmes finanšu vai juridiskos lēmumus balstīt tikai intuīcijā — tā šajos kontekstos var nest atskaņas no neapzinātām bailēm'
            : 'Neļauj ieslīgt analīzes paralīzē — kādā brīdī ir jāizlemj, pat ja dati nav 100% pilnīgi'
    ].filter(Boolean);

    const keyInsight = transition?.active
        ? `Šobrīd profils atrodas turbulences zonā — labākais lēmums ir ATLIKT lielos lēmumus, ne piespiest tos`
        : (cynefin && dominantMeta
            ? `Vislabākie lēmumi rodas, kad lēmumu pieņemšanas stils (<b>${dominantMeta.lv}</b>) tiek izmantots vidē, kas atbilst <b>${cynefin.lv}</b> — citi konteksti samazina kvalitāti par 30–50%`
            : `Lielajos lēmumos primārais ir enkura un misijas saglabāšana — taktika ir sekundāra`);

    return { ...SCENARIO_META.decisions, narrative, dos, donts, keyInsight };
}

// ── Galvenā eksportējamā funkcija ────────────────────────────────────────────

export function calculatePracticalScenarios(profile) {
    return {
        crisis:     buildCrisisScenario(profile),
        motivation: buildMotivationScenario(profile),
        team:       buildTeamScenario(profile),
        decisions:  buildDecisionsScenario(profile)
    };
}
