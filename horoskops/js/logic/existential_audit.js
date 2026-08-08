// Eksistenciālais Audits — Logoterapija + Plūsma + Hronobioloģija
// ──────────────────────────────────────────────────────────────────
// Tāpat kā Junga arhetipi 1. sadaļā un Šeina enkuri 2. sadaļā, šeit
// Viktora Frankla Logoterapija (Man's Search for Meaning, 1946) un
// Mihāja Čīksentmihāji Plūsmas teorija (Flow, 1990) iekrāmē Atmakarakas,
// Maiju Nahuala, Ķeltu kokus un BaZi Daymaster datus klīniski un
// pragmatiski validētā ikdienas enerģijas menedžmenta valodā.
//
// Output ir trīs slāņi:
//   1) Makro — Eksistenciālais dzinējs jeb Dharmas tips (Logoterapija)
//   2) Mezo — Bioritma tips + biotops + izdegšanas trigeris (Ķeltu/Maiju)
//   3) Mikro — Ikdienas Plūsmas dizains (BaZi + Tithi)

// ── DHARMAS TIPI pēc Atmakarakas planētas (Logoterapijas makro) ──────────────

export const DHARMA_BY_ATMAKARAKA = {
    Saule: {
        lv: 'Vadības un Struktūras Dharma',
        en: 'Solar Dharma — Authority & Order',
        color: '#fbbf24',
        icon: '☀',
        mission: 'Spēja radīt struktūru un uzņemties atbildību par citiem haosa brīžos',
        signature: 'Kārtība, kur bija haoss; centrālā ass, ap kuru citi var organizēties',
        deepSatisfaction: 'Brīdis, kad citi atzīst viņa autoritāti — ne caur statusu, bet caur reālu vadību krīzē',
        emptySignal: 'Tiklīdz viņš strādā tikai algai vai pakļaujas vājākam vadītājam, iestājas garīga apātija'
    },
    Mēness: {
        lv: 'Emocionālās Bāzes un Rūpju Dharma',
        en: 'Lunar Dharma — Emotional Sanctuary',
        color: '#e2e8f0',
        icon: '🌙',
        mission: 'Spēja radīt emocionālu drošību kolektīvam — būt par tilta vai mājas figūru',
        signature: 'Telpas, kurās cilvēki var atvilkt elpu, atmīkstēt un atjaunoties',
        deepSatisfaction: 'Brīdis, kad kāds saka "tu biji vienīgais, kas mani redzēja patiesi"',
        emptySignal: 'Tiklīdz attiecības tiek reducētas līdz transakcijai vai pakalpojumam, iestājas iekšējs sastingums'
    },
    Marss: {
        lv: 'Aizstāvja un Krīzes Dharma',
        en: 'Martian Dharma — Defender & Crisis Force',
        color: '#ef4444',
        icon: '⚔',
        mission: 'Spēja cīnīties par to, kas ir taisnīgs vai neaizsargāts; krīzes situācijā atklājas viņa patiesā būtība',
        signature: 'Izšķiroši momenti, kad viņa drosme deva citiem iespēju izdzīvot vai uzdrošināties',
        deepSatisfaction: 'Brīdis, kad viņš zina, ka konflikts kalpoja kaut kam lielākam par ego',
        emptySignal: 'Tiklīdz viņš kompromitē savu pārliecību drošības labad, iestājas iekšēja apātija un pasivitāte'
    },
    Merkurs: {
        lv: 'Tilta un Tulkojuma Dharma',
        en: 'Mercurial Dharma — Translator & Bridge',
        color: '#22c55e',
        icon: '✉',
        mission: 'Spēja tulkot sarežģīto vienkāršajā, savienot pasaules, ko citi nevar samainīt',
        signature: 'Saprašanas mirkļi tur, kur citi redzēja tikai sienu vai pretrunu',
        deepSatisfaction: 'Brīdis, kad kāds saka "tagad es saprotu" — tilta veidošana ir pati par sevi mērķis',
        emptySignal: 'Tiklīdz informācija kļūst rutīna bez nozīmes vai dialoga, iestājas intelektuāls nogurums'
    },
    Jupiters: {
        lv: 'Mentora un Pārpasaules Dharma',
        en: 'Jovian Dharma — Mentor & Wisdom Transmission',
        color: '#f59e0b',
        icon: '🌳',
        mission: 'Spēja pacelt citu apziņas līmeni — nodot tālāk to, ko pats izpratis',
        signature: 'Cilvēki, kas pēc kontakta ar viņu sāka domāt plašāk par sevi',
        deepSatisfaction: 'Brīdis, kad viņa students vai māceklis pāraug savu skolotāju',
        emptySignal: 'Tiklīdz darbs kļūst tikai par viņu pašu un peļņu, bez augstāka mērķa, iestājas filozofiska tukšuma sajūta'
    },
    Venera: {
        lv: 'Skaistuma un Harmonijas Dharma',
        en: 'Venusian Dharma — Beauty & Harmony',
        color: '#ec4899',
        icon: '🌸',
        mission: 'Spēja ienest pasaulē skaistumu, harmoniju un mīlestību kā reālu spēku',
        signature: 'Vide, attiecības vai darbi, kuros cilvēki atrod savu emocionālo un estētisko mājas izjūtu',
        deepSatisfaction: 'Brīdis, kad viņa darbs (mūzika, dizains, attiecības) izsauc citā cilvēkā prieku vai skaistuma apziņu',
        emptySignal: 'Tiklīdz darbs kļūst tikai funkcionāls bez estētiskā vai mīlestības elementa, iestājas dziļa skumja apātija'
    },
    Saturns: {
        lv: 'Disciplīnas un Mūžīga Mantojuma Dharma',
        en: 'Saturnian Dharma — Discipline & Durable Legacy',
        color: '#64748b',
        icon: '🗿',
        mission: 'Spēja uzbūvēt to, kas pārdzīvos viņu pašu — sistēmas, struktūras un mantojums uz simtiem gadu',
        signature: 'Izturīgas formas, ko cita paaudze izmantos kā pamatu',
        deepSatisfaction: 'Brīdis, kad viņš zina, ka viņa darbs būs nozīmīgs arī pēc viņa aiziešanas',
        emptySignal: 'Tiklīdz viņš pielāgojas īstermiņa tendencēm vai ātrai peļņai, iestājas dziļa eksistenciāla melanholija'
    },
    Rahu: {
        lv: 'Robežpārkāpēja un Inovatora Dharma',
        en: 'Rahu Dharma — Boundary-Breaker & Innovator',
        color: '#a855f7',
        icon: '🌀',
        mission: 'Spēja pārkāpt kolektīvās robežas un nodibināt jaunu paradigmu — bieži uz personīgā komforta rēķina',
        signature: 'Vietas, jomas vai idejas, kas pēc viņa vairs nekad nav tās pašas',
        deepSatisfaction: 'Brīdis, kad tirgus vai sabiedrība sāk sekot ceļam, ko viņš iesāka viens',
        emptySignal: 'Tiklīdz viņš pieņem konformitātes lomu, iestājas dziļa neapmierinātība un trauksme'
    },
    Ketu: {
        lv: 'Atsacījuma un Tīrās Esences Dharma',
        en: 'Ketu Dharma — Detachment & Pure Essence',
        color: '#94a3b8',
        icon: '🕉',
        mission: 'Spēja nodalīt būtisko no formas — atbrīvot to, kam vairs nav jēgas, un nodot tālāk tīro esenci',
        signature: 'Tas, ko viņš pārtrauca paturēt, lai cits varētu pieaugt; klusas izaugsmes telpas',
        deepSatisfaction: 'Brīdis, kad viņš apzinās, ka mazāk ir vairāk — atsacīšanās dod brīvības sajūtu, kas pārsniedz materiālo',
        emptySignal: 'Tiklīdz viņš sāk paturēt resursus, statusu vai attiecības aiz bailēm, iestājas dziļa iekšēja cīņa'
    }
};

// ── 4 BIORITMA TIPI (Mezo līmenis) ───────────────────────────────────────────

export const RHYTHM_META = {
    explosive: {
        lv: 'Sprinta ritms',
        en: 'Explosive · Sprint',
        color: '#ef4444',
        icon: '⚡',
        biotope: 'Projektu darbs ar skaidriem termiņiem un augstu intensitāti — sprints, finiša līnija, atjaunošanās pauze, atkārtošana',
        burnoutTrigger: 'Lineārs 9-to-5 grafiks ar vienmērīgu slodzi visu gadu — bez sprintu un atjaunošanās ciklu kapacitāte degenerē',
        recovery: 'Pēc katra sprintā nepieciešama pilna atvienošanās — 3-7 dienu klusa atjaunošanās bez izziņas slodzes'
    },
    steady: {
        lv: 'Maratona ritms',
        en: 'Steady · Marathon',
        color: '#a855f7',
        icon: '🏔',
        biotope: 'Ilgtermiņa projekti ar stabilu ikdienas ritmu — paredzamība, atkārtojami procesi un mērena, bet konsistenta produkcija',
        burnoutTrigger: 'Hronisks haoss, pastāvīgas virziena maiņas vai krīžu menedžmenta loma — neparedzamība, ne pārslodze, izsmel resursus',
        recovery: 'Atjaunošanās notiek caur rutīnu, ne pauzi — stabilas dienas struktūras saglabāšana ir pati par sevi atjaunošanās instruments'
    },
    cyclical: {
        lv: 'Viļņveida ritms',
        en: 'Cyclical · Wave',
        color: '#0ea5e9',
        icon: '🌊',
        biotope: 'Ciklisks darbs ar augstas intensitātes "vasaras" fāzēm un dziļām "ziemas" atjaunošanās fāzēm — sezonāla loģika',
        burnoutTrigger: 'Lineārs un nemainīgs ikdienas grafiks bez fāžu maiņas — bioritms ir cikliskais, ne lineārais, un to nedrīkst piespiest',
        recovery: 'Atjaunošanās notiek caur klusu, intuitīvu "ziemas režīmu" — 4-8 nedēļas dziļa noslēgtība bez ārējām prasībām'
    },
    adaptive: {
        lv: 'Adaptīvais ritms',
        en: 'Adaptive · Chameleon',
        color: '#22c55e',
        icon: '🦎',
        biotope: 'Daudzslāņains darbs ar vairākiem paralēliem virzieniem — variācija pati par sevi ir enerģijas avots, monolīts ir slazds',
        burnoutTrigger: 'Vienveidīga loma vai vienā šaurā virzienā ierobežotas iespējas — bez konteksta maiņas un jaunām stimuliem iestājas iekšējs sastingums',
        recovery: 'Atjaunošanās notiek caur konteksta maiņu — jauna grāmata, ceļojums, hobijs vai cita joma atjauno enerģiju vairāk par pauzi'
    }
};

// ── ĶELTU KOKU klasifikācija → bioritma tips ─────────────────────────────────

const CELTIC_RHYTHM = {
    'Bērzs':       'explosive',  // Sasniedzējs — raw initiator
    'Pīlādzis':    'steady',     // Domātājs — intellectual marathon
    'Osis':        'cyclical',   // Vizionārs — between worlds
    'Alksnis':     'explosive',  // Ceļš rādītājs — pathmaker burst
    'Vītols':      'cyclical',   // Novērotājs — moon-tied waves
    'Vilkābele':   'adaptive',   // Iluzionists — shape-shifter
    'Ozols':       'steady',     // Aizstāvis — oak marathon
    'Briedoga':    'explosive',  // Dižciltīgais — noble warrior
    'Lazda':       'steady',     // Analītiķis — analyst marathon
    'Vīnogulājs':  'adaptive',   // Izlīdzinātājs — all-angle
    'Efeja':       'adaptive',   // Izdzīvotājs — survives anywhere
    'Niedre':      'explosive',  // Izaicinātājs — challenger burst
    'Plūškoks':    'steady',     // Zinātnieks — scholar marathon
    'Bez Vārda Diena': 'adaptive' // Pārejas cilvēks — threshold
};

// ── Maiju TONIS (1-13) → bioritma tips ───────────────────────────────────────

const MAYAN_TONE_RHYTHM = {
    1: 'explosive',   // Magnētiskais — initiator
    2: 'adaptive',    // Lunārais — polarity
    3: 'explosive',   // Elektriskais — activator
    4: 'steady',      // Pašeksistējošais — structure
    5: 'explosive',   // Virstoņa — commander
    6: 'steady',      // Ritmiskais — work horse
    7: 'cyclical',    // Rezonanses — antenna
    8: 'adaptive',    // Galaktiskais — integrator
    9: 'explosive',   // Saules — manifester
    10: 'steady',     // Planetārais — finalist
    11: 'explosive',  // Spektrālais — releaser
    12: 'adaptive',   // Kristāliskais — cooperator
    13: 'cyclical'    // Kosmiskais — transition
};

// ── BaZi Daymaster elements → ikdienas Plūsmas dizains (Mikro līmenis) ──────

const BAZI_FLOW_DESIGN = {
    Koks: {
        element: 'Koks',
        emoji: '🌱',
        morning: 'Rītu sākt ar fizisku kustību ārpus telpām (pastaiga, stiepšanās dabā) un vienu jaunu mācību materiālu — augšanas signāls aktivē sistēmu',
        flow: 'Plūsmā ieiet, kad ir skaidrs izaugsmes virziens un partneris vai komanda, ar ko apspriest progresu; izolācija slāpē enerģiju',
        restart: 'Atslēgšanās notiek caur dārzu, augiem, dabu vai mākslu — zaļais skats acīm un fiziska kontakta ar zemi atjauno sistēmu',
        avoidance: 'Pārmērīgs sēdošs darbs slēgtā telpā bez kustības — bloķē dabisku augšanas dinamiku un izraisa fizisku saspringumu'
    },
    Uguns: {
        element: 'Uguns',
        emoji: '🔥',
        morning: 'Rītu sākt ar intensīvu fizisku aktivitāti vai sociālu kontaktu — ātru pulsa pacēlumu un vienu skaļu uzvaru pirms 10:00',
        flow: 'Plūsmā ieiet, kad ir sociālā enerģija un publiska redzamība — pa vienam projekta klusais darbs noslāpē uguni',
        restart: 'Atslēgšanās notiek caur prieku, smiekliem un sociālu izklaidi — vientulība šim profilam nav atjaunošanās, bet izdegšana',
        avoidance: 'Ilgstoša izolācija un klusums bez sociāla kontakta — uguns izdziest bez gaisa plūsmas'
    },
    Zeme: {
        element: 'Zeme',
        emoji: '🗿',
        morning: 'Rītu sākt ar stabilu rutīnu — viena un tā pati secība katru dienu, iekļaujot piezemētu fizisku darbību (ēdiena gatavošana, dārzs)',
        flow: 'Plūsmā ieiet, kad ir skaidrs, paredzams uzdevumu klāsts un mierīga, fiziska vide — pārmaiņas un haoss šim profilam ir traucēklis',
        restart: 'Atslēgšanās notiek caur fizisku, sensoru aktivitāti — kontakts ar zemi, akmeņiem, koku, ēdiena gatavošanu — ne caur ekrāniem',
        avoidance: 'Strauja virziena maiņa un nestrukturēts darbs — Zemes profils zaudē stabilitāti un kļūst trauksmains'
    },
    Metāls: {
        element: 'Metāls',
        emoji: '⚙',
        morning: 'Rītu sākt ar asu, strukturētu plānu un vienu ātru uzvaru (mikrouzdevumu) — bez stingra sākuma enerģija izkliedējas',
        flow: 'Plūsmā ieiet, kad ir precīzs mērķis, klusums un sistēmas, ko optimizēt — emocionāls vai haotisks fons noslāpē fokusu',
        restart: 'Atslēgšanās notiek caur disciplīnu un kvalitāti — viena augstvērtīga maltīte, viena izvēlēta grāmata, viena prasme — nevis bezgalīga konsumpcija',
        avoidance: 'Daudzuzdevumu pildīšana un emocionāli prasmīga komunikācija visu dienu — Metāla profils izdeg bez precīzas atdalīšanas'
    },
    Ūdens: {
        element: 'Ūdens',
        emoji: '🌊',
        morning: 'Rītu sākt lēni — meditācija, duša, dziļa elpa, viens stratēģisku pārdomu brīdis pirms aktivitātes — bez tā Ūdens profils visu dienu reaģē, nevis darbojas',
        flow: 'Plūsmā ieiet, kad ir laiks padomāt, klusa vide un iespēja sekot intuīcijai — strikti grafiki un mikromenedžments noslāpē kapacitāti',
        restart: 'Atslēgšanās notiek caur ūdeni (peldēšana, vannas, jūra), klusumu un dziļām sarunām ar vienu uzticamu cilvēku — ne caur ballītēm',
        avoidance: 'Pārmērīgs grafiku piepildījums un sociāla virspusīga komunikācija — Ūdens profils izdeg no nepārtraukta "uz augšas" režīma'
    }
};

// ── Maiju Nahuala dharmas raksturs (sekundārā Logoterapija) ──────────────────
// Apkopots no MAYAN_ARCHETYPES tipiem — sniedz papildu dimensiju Atmakarakas
// vektoram. Šeit grupēts kategorijās pēc dziļākā mērķa.

const MAYAN_NAHUAL_GROUP = {
    0:  'Piegādātājs · Baro citus',
    1:  'Vēstnesis · Pārvada informāciju',
    2:  'Slepenais novērotājs · Strādā klusumā',
    3:  'Arhitekts · Plāno un strukturē',
    4:  'Instinktu meistars · Fizioloģiskā jauda',
    5:  'Reorganizētājs · Transformē sistēmas',
    6:  'Realizētājs · Pārvalda instrumentus',
    7:  'Estēts · Veido tēlu un harmoniju',
    8:  'Emocionālais lasītājs · Filtrē kolektīvo plūsmu',
    9:  'Lojāls pavadonis · Glabā komandas garu',
    10: 'Mākslinieks · Spēlē ar ilūzijām un formām',
    11: 'Administrators · Pārvalda ilgtermiņa kursu',
    12: 'Robežpārkāpējs · Pārvar gaitas',
    13: 'Šamanis · Darbojas ārpus laika',
    14: 'Globālais stratēģis · Redz kopbildi',
    15: 'Iekšējās drošības sargs · Uzticas savai balsij',
    16: 'Pārmaiņu katalizators · Iniciē kustību',
    17: 'Atmaskotājs · Griež cauri meliem',
    18: 'Krīzes menedžeris · Pārkārto caur haosu',
    19: 'Ideoloģiskais līderis · Vada ar gaismu'
};

// ── Ķeltu koka nosaukuma aprēķins no dzimšanas datuma ────────────────────────
// Tikai NOSAUKUMS — pilnās traits/shadow paliek tab_experiment.js. Šeit
// klasifikācijai nepieciešama tikai koka identifikācija pēc kalendāra.

function getCelticTreeName(dateStr) {
    if (!dateStr) return null;
    const parts = dateStr.split('-');
    const month = parseInt(parts[1], 10);
    const day   = parseInt(parts[2], 10);
    const md    = month * 100 + day;

    if (md === 1223) return 'Bez Vārda Diena';

    const ranges = [
        [1224, 1231, 'Bērzs'],   [101,  120, 'Bērzs'],
        [121,  217, 'Pīlādzis'],
        [218,  317, 'Osis'],
        [318,  414, 'Alksnis'],
        [415,  512, 'Vītols'],
        [513,  609, 'Vilkābele'],
        [610,  707, 'Ozols'],
        [708,  804, 'Briedoga'],
        [805,  901, 'Lazda'],
        [902,  929, 'Vīnogulājs'],
        [930, 1027, 'Efeja'],
        [1028, 1124, 'Niedre'],
        [1125, 1222, 'Plūškoks']
    ];
    for (const [from, to, name] of ranges) {
        if (from > to) {
            if (md >= from || md <= to) return name;
        } else if (md >= from && md <= to) return name;
    }
    return null;
}

// ── Galvenā eksportējamā funkcija ────────────────────────────────────────────

export function calculateExistentialAudit(profile) {
    const celticTreeName = getCelticTreeName(profile?.birth_info?.date);
    const vedic = profile?.vedic || {};
    const bazi  = profile?.bazi  || {};
    const maya  = profile?.maya_profile?.basic || {};
    const tithi = profile?.tithi_now || null;

    // ── Makro: Dharma (Atmakaraka + Mayan Nahual) ────────────────────────────
    // BUGFIX 2026-06-11: sidereal datu atslēga ir 'Meness' (bez ē), bet DHARMA_BY_ATMAKARAKA
    // atslēga 'Mēness' — bez normalizācijas visi Mēness-Atmakarakas profili krita uz Saules dharmu.
    const atmakarakaPlanet = (vedic?.atmakaraka?.planet || 'Saule').replace(/^Meness$/, 'Mēness');
    const dharma = DHARMA_BY_ATMAKARAKA[atmakarakaPlanet] || DHARMA_BY_ATMAKARAKA.Saule;
    const mayanGroup = (maya.sign_idx != null) ? MAYAN_NAHUAL_GROUP[maya.sign_idx] : null;

    // ── Mezo: Bioritms (Celtic + Maya Tone) ──────────────────────────────────
    const celticKey = celticTreeName || null;
    const celticRhythm = celticKey ? (CELTIC_RHYTHM[celticKey] || null) : null;
    const tone = (typeof maya.tone === 'number') ? maya.tone : parseInt(maya.tone, 10);
    const mayanRhythm = (tone >= 1 && tone <= 13) ? MAYAN_TONE_RHYTHM[tone] : null;

    // Kombinē abus: ja sakrīt — viennozīmīgs profils; ja atšķiras — hibrīds
    let primaryRhythmKey = celticRhythm || mayanRhythm || 'adaptive';
    const isHybrid = celticRhythm && mayanRhythm && celticRhythm !== mayanRhythm;
    const rhythmMeta = RHYTHM_META[primaryRhythmKey];
    const secondaryRhythmMeta = isHybrid ? RHYTHM_META[mayanRhythm] : null;

    // ── Visu 4 ritma tipu distribūcijas skori (HR salīdzinājuma datu nesēji)
    // Bāzes svari: Celtic = 50%, Maya = 50%. Sakrišanas gadījumā kompenzēts.
    const allRhythmScores = (() => {
        const scores = { explosive: 0, steady: 0, cyclical: 0, adaptive: 0 };
        if (celticRhythm) scores[celticRhythm] += 50;
        if (mayanRhythm)  scores[mayanRhythm] += 50;
        // Ja nav nevienas norādes (default 'adaptive'), pielīdzina balanced
        const hasAny = celticRhythm || mayanRhythm;
        if (!hasAny) {
            return { explosive: 25, steady: 25, cyclical: 25, adaptive: 25 };
        }
        // Normalizē — ja tikai viens avots, pārējie iegūst 0 (skaidri parāda nedeterminētību)
        return scores;
    })();

    // ── Mikro: Plūsmas dizains (BaZi Daymaster + Tithi modulācija) ───────────
    const dmElement = bazi?.Daymaster?.element || 'Koks';
    const flowDesign = BAZI_FLOW_DESIGN[dmElement] || BAZI_FLOW_DESIGN.Koks;

    // Tithi modulācija — vai Mēness ir augošā (Shukla) vai dilstošā (Krishna) fāzē
    let tithiPhase = null;
    if (tithi?.tithiNumber) {
        tithiPhase = (tithi.tithiNumber <= 15) ? 'shukla' : 'krishna';
    } else if (tithi?.name) {
        tithiPhase = tithi.name.toLowerCase().includes('krishna') ? 'krishna' : 'shukla';
    }
    const tithiModulator = tithiPhase === 'krishna'
        ? 'Mēness ir dilstošā fāzē (Krishna) — šī fāze sūta enerģiju konsolidācijai, projektu pabeigšanai un atbrīvošanās procesiem. Ideāli jaunas iniciatīvas nesāc, bet labi noslēdz to, kas vairs nekalpo'
        : 'Mēness ir augošā fāzē (Shukla) — šī fāze sūta enerģiju jaunu iniciatīvu sākšanai, mērogošanai un izaugsmei. Tagad ir labs brīdis sākt to, kas prasa ārējo atbalstu un redzamību';

    return {
        macro: {
            dharma,
            atmakarakaPlanet,
            mayanGroup,
            mayanGroupLabel: mayanGroup ? `Maiju Nahual: ${mayanGroup}` : null
        },
        mezo: {
            primary: { key: primaryRhythmKey, ...rhythmMeta },
            secondary: secondaryRhythmMeta ? { key: mayanRhythm, ...secondaryRhythmMeta } : null,
            isHybrid,
            celticKey,
            tone,
            allScores: allRhythmScores,
            allMeta:   RHYTHM_META
        },
        micro: {
            element: flowDesign.element,
            emoji: flowDesign.emoji,
            morning: flowDesign.morning,
            flow: flowDesign.flow,
            restart: flowDesign.restart,
            avoidance: flowDesign.avoidance,
            tithiPhase,
            tithiModulator
        }
    };
}
