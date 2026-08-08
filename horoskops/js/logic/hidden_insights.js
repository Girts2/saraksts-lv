// ── SLĒPTIE IESKATI (cilne 'y') ───────────────────────────────────────────────
// Jauni aprēķini + interpretācijas tiem datiem, ko horoskops līdz šim nerādīja:
//   • interpretLot           — Hellēnisma Fortūnas/Gara daļas nozīme (pēc zīmes)
//   • computeArudhaLagna      — kā citi uztver šo cilvēku (publiskais tēls)
//   • computeAspectPatterns   — T-kvadrāts / Lielais trijstūris / Jods
//   • computePlanetStrength   — vienkāršots planētu spēka indekss (Shadbala-lite)
//
// Visi aprēķini ir deterministiski no dzimšanas datiem (sidēriskās pozīcijas).

const SIGNS = ["Auns","Vērsis","Dvīņi","Vēzis","Lauva","Jaunava","Svari","Skorpions","Strēlnieks","Mežāzis","Ūdensvīrs","Zivis"];

const SIGN_RULERS = {
    "Auns":"Marss", "Vērsis":"Venera", "Dvīņi":"Merkurs", "Vēzis":"Meness",
    "Lauva":"Saule", "Jaunava":"Merkurs", "Svari":"Venera", "Skorpions":"Marss",
    "Strēlnieks":"Jupiters", "Mežāzis":"Saturns", "Ūdensvīrs":"Saturns", "Zivis":"Jupiters"
};

// ── Hellēnisma daļas — nozīme pēc zīmes ──────────────────────────────────────
const LOT_FORTUNE = {
    "Auns":"Veiksme un ķermeņa enerģija plūst caur drosmi un iniciatīvu — labklājība nāk, kad cilvēks rīkojas pirmais.",
    "Vērsis":"Materiālā stabilitāte veidojas lēni un noturīgi — ķermenis un finanses uzplaukst mierā un pastāvībā.",
    "Dvīņi":"Labklājība nāk caur saziņu, daudzveidību un kontaktiem — veiksme mīl kustību un informāciju.",
    "Vēzis":"Ķermeņa un materiālā labsajūta saistīta ar mājām, ģimeni un emocionālo drošību.",
    "Lauva":"Veiksme plūst caur pašizpausmi, radošumu un atpazīstamību — labklājība mīl spožumu.",
    "Jaunava":"Materiālā stabilitāte nāk caur kārtību, rūpību un noderīgu prasmi — veiksme detaļās.",
    "Svari":"Labklājība veidojas caur attiecībām, partnerībām un līdzsvaru — veiksme nāk kopā ar citiem.",
    "Skorpions":"Ķermeņa un finanšu enerģija iet caur dziļām pārmaiņām, kopīgiem resursiem un atjaunošanos.",
    "Strēlnieks":"Veiksme plūst caur paplašināšanos, ceļojumiem un pārliecību — labklājība mīl plašumu.",
    "Mežāzis":"Materiālā labklājība nāk caur disciplīnu, struktūru un ilgtermiņa būvēšanu.",
    "Ūdensvīrs":"Veiksme nāk caur kopienām, idejām un neatkarību — labklājība mīl oriģinalitāti.",
    "Zivis":"Ķermeņa un materiālā plūsma saistīta ar intuīciju, līdzjūtību un atlaišanu."
};
const LOT_SPIRIT = {
    "Auns":"Gribas un karjeras dziņa izpaužas caur iniciatīvu un cīņu — cilvēks aktīvi dzen pirmo soli.",
    "Vērsis":"Mērķtiecība iet caur pacietīgu, taustāmu būvēšanu — griba pārvēršas pastāvībā.",
    "Dvīņi":"Prāts un griba virzīti uz mācīšanos, runāšanu un sakariem — dzinējs ir zinātkāre.",
    "Vēzis":"Iekšējais dzinējspēks saistīts ar rūpēm, aizsardzību un emocionālo jēgu.",
    "Lauva":"Griba izpaužas caur vadību, radīšanu un vēlmi atstāt nospiedumu.",
    "Jaunava":"Mērķtiecība iet caur pilnveidošanu, analīzi un noderīgu kalpošanu.",
    "Svari":"Dzinējs ir taisnīgums, sadarbība un attiecību veidošana — griba caur citiem.",
    "Skorpions":"Iekšējā dziņa ir transformācija, dziļums un patiesība zem virsmas.",
    "Strēlnieks":"Griba virzīta uz jēgu, mācīšanu un robežu paplašināšanu.",
    "Mežāzis":"Mērķtiecība iet caur atbildību, ambīciju un ilgtermiņa sasniegumiem.",
    "Ūdensvīrs":"Dzinējs ir vīzija, inovācija un kalpošana lielākai kopienai.",
    "Zivis":"Iekšējā griba virzīta uz garīgumu, iztēli un pašaizliedzību."
};

export function interpretLot(signName, kind) {
    const dict = kind === 'spirit' ? LOT_SPIRIT : LOT_FORTUNE;
    return dict[signName] || '';
}

// ── Gada profekcija — personiska gada prognoze ───────────────────────────────
const PROFECTION_HOUSE = {
    1:  "personība, ķermenis, jaunie sākumi un pašapziņa",
    2:  "nauda, resursi, vērtības un pašvērtējums",
    3:  "saziņa, mācīšanās, tuvākā vide un brāļi/māsas",
    4:  "mājas, ģimene, saknes un emocionālais pamats",
    5:  "radošums, romance, bērni, prieks un riski",
    6:  "darbs, veselība, ikdiena un pienākumi",
    7:  "partnerības, laulība, līgumi un attiecības",
    8:  "pārmaiņas, kopīgie resursi, krīzes un dziļums",
    9:  "ceļojumi, izglītība, jēga un pārliecība",
    10: "karjera, statuss, mērķi un publiskā loma",
    11: "draugi, kopienas, ienākumi un nākotnes cerības",
    12: "atlaišana, garīgums, izolācija un slēptais"
};

export function interpretProfection(profile) {
    const pr = profile?.hellenistic?.profection;
    if (!pr || !pr.sign || !pr.house) return null;

    const houseTheme = PROFECTION_HOUSE[pr.house] || '';

    // Gada valdnieka dzimšanas pozīcija (tropiskā, whole-sign — saskan ar profekciju,
    // un izvairās no nedrošajām Placidus cusp mājām)
    const pl = profile?.western?.planets || {};
    const ascLon  = pl.Ascendant?.longitude;
    const lordObj = pl[pr.lord];
    let lordSign = lordObj?.sign || null, lordHouse = null, lordHouseTheme = '';
    if (ascLon !== undefined && lordObj?.longitude !== undefined) {
        const ascIdx  = Math.floor(ascLon / 30) % 12;
        const lordIdx = Math.floor(lordObj.longitude / 30) % 12;
        lordSign = SIGNS[lordIdx];
        lordHouse = ((lordIdx - ascIdx + 12) % 12) + 1;
        lordHouseTheme = PROFECTION_HOUSE[lordHouse] || '';
    }

    // Atbalsts pēc gada valdnieka dignitātes
    const dignity = lordObj?.dignity || '';
    let support = 'neitrāls';
    let supportText = `Gada valdnieks ${pr.lord} ir neitrālā stāvoklī${dignity ? ` (${dignity})` : ''} — gads ir aptuveni līdzsvarots, iznākums atkarīgs no paša izvēlēm.`;
    if (/Eksaltācija|Valdījums/.test(dignity)) {
        support = 'atbalstīts';
        supportText = `Gada valdnieks ${pr.lord} ir stiprā stāvoklī (${dignity}) — šis gads ir labi atbalstīts, ar vēja palīdzību tēmas virzienā.`;
    } else if (/Trimda|Kritums/.test(dignity)) {
        support = 'izaicinošs';
        supportText = `Gada valdnieks ${pr.lord} ir vājā stāvoklī (${dignity}) — šis gads prasa vairāk piepūles un pacietības; tēma var nest pārbaudījumus.`;
    }

    return { house: pr.house, sign: pr.sign, lord: pr.lord, houseTheme, lordSign, lordHouse, lordHouseTheme, dignity, support, supportText };
}

// ── Atslēgvārdu izcēlumi: zaļš = stiprā/pozitīvā nozīme, sarkans = jutīgā/negatīvā ──
const POS = (t) => `<b style="color:#16a34a;">${t}</b>`;
const NEG = (t) => `<b style="color:#dc2626;">${t}</b>`;

// ── Arudha Lagna — kā citi uztver cilvēku (3. persona, bagātināts, daudz-auditoriju) ──
const ARUDHA_PERCEPTION = {
    "Auns": `Citi šo cilvēku uztver kā ${POS('enerģisku, drosmīgu un tiešu')} — līderi, kas nebaidās rīkoties un uzņemties iniciatīvu. Viņam piedēvē ${POS('spēku, pārliecību un ātru lēmumu')}; reizēm šī tiešā enerģija var ${NEG('šķist pārāk uzstājīga, dominējoša vai nepacietīga')}.`,
    "Vērsis": `Citi šo cilvēku uztver kā ${POS('stabilu, uzticamu un mierīgu spēku')} — cilvēku, uz kuru var paļauties un kurš nesteidzas. Viņam piedēvē ${POS('noturību, praktiskumu un drošību')}; reizēm šī mierība var ${NEG('šķist lēnīga, ietiepīga vai pārāk piesardzīga')}.`,
    "Dvīņi": `Citi šo cilvēku uztver kā ${POS('runātīgu, zinošu un daudzpusīgu')} — cilvēku, kas pārzina visu un viegli iesaistās sarunā. Viņam piedēvē ${POS('asu prātu un pielāgošanās spēju')}; reizēm viņš var ${NEG('šķist izklaidīgs, virspusējs vai nepastāvīgs')}.`,
    "Vēzis": `Citi šo cilvēku uztver kā ${POS('gādīgu, siltu un aizsargājošu')} — cilvēku ar mājas siltumu, pie kura var meklēt atbalstu. Viņam piedēvē ${POS('empātiju un uzticamību')}; reizēm šī jutība var ${NEG('šķist pārāk personiska, mainīga vai noslēgta')}.`,
    "Lauva": `Citi šo cilvēku uztver kā ${POS('spožu, pārliecinātu un cienījamu')} — dabisku centra figūru, kas piesaista uzmanību. Viņam piedēvē ${POS('autoritāti, harizmu un dāsnumu')}; reizēm šis spožums var ${NEG('šķist dominējošs, egocentrisks vai prasīgs pēc uzmanības')}.`,
    "Jaunava": `Citi šo cilvēku uztver kā ${POS('precīzu, prātīgu un kompetentu')} — kārtīgu un noderīgu cilvēku, kas pamana detaļas. Viņam piedēvē ${POS('rūpību, uzticamību un kompetenci')}; reizēm viņš var ${NEG('šķist kritisks, pedantisks vai pārāk prasīgs')}.`,
    "Svari": `Citi šo cilvēku uztver kā ${POS('šarmantu, taisnīgu un diplomātisku')} — patīkamu un līdzsvarotu cilvēku, ar kuru viegli sadarboties. Viņam piedēvē ${POS('taktu, godīgumu un labu gaumi')}; reizēm viņš var ${NEG('šķist neizlēmīgs, izvairīgs vai izpaticīgs')}.`,
    "Skorpions": `Citi šo cilvēku uztver kā ${POS('noslēpumainu, intensīvu un grūti nolasāmu')} — ar sajūtu, ka zem virsmas slēpjas ${POS('spēks vai dziļāks nolūks')}. Viņam piedēvē ${POS('kontroli, iekšēju stingrību un spēju saskatīt vairāk, nekā pasaka')}, un viņu uztver nopietni; reizēm viņš var ${NEG('šķist noslēgts, aprēķināts vai grūti uzticams')}.`,
    "Strēlnieks": `Citi šo cilvēku uztver kā ${POS('gudru, optimistisku un brīvu')} — skolotāju vai ceļotāju, kas iedvesmo ar plašu skatu. Viņam piedēvē ${POS('atklātību, zināšanas un dzīvesprieku')}; reizēm šī brīvība var ${NEG('šķist nesaistīta, pārāk tieša vai bezrūpīga')}.`,
    "Mežāzis": `Citi šo cilvēku uztver kā ${POS('nopietnu, ambiciozu un autoritatīvu')} — cilvēku ar statusu un atbildības sajūtu. Viņam piedēvē ${POS('disciplīnu, kompetenci un uzticamību')}; reizēm viņš var ${NEG('šķist auksts, distancēts vai pārāk stingrs')}.`,
    "Ūdensvīrs": `Citi šo cilvēku uztver kā ${POS('oriģinālu, neatkarīgu un progresīvu')} — savdabīgu domātāju, kas redz lietas savādāk. Viņam piedēvē ${POS('inteliģenci, vīziju un neatkarību')}; reizēm viņš var ${NEG('šķist distancēts, dīvains vai grūti aizsniedzams')}.`,
    "Zivis": `Citi šo cilvēku uztver kā ${POS('līdzjūtīgu, garīgu un māksliniecisku')} — jūtīgu sapņotāju ar dvēseli. Viņam piedēvē ${POS('iejūtību, iztēli un maigumu')}; reizēm viņš var ${NEG('šķist izplūdis, nepraktisks vai viegli ietekmējams')}.`
};

// Iekšējā būtība (Lagna) — pretstatā tam, kā citi uztver (Arudha); 3. persona, bagātināts
const LAGNA_SELF = {
    "Auns": `Šo cilvēku iekšēji dzen ${POS('rīcība, iniciatīva un neatkarība')} — viņš grib būt ${POS('pirmais')}, sākt jaunu un virzīt notikumus uz priekšu. Viņš uzplaukst, kad var ${POS('rīkoties ātri un patstāvīgi')} un redzēt tūlītēju rezultātu; turpretī ${NEG('gaidīšana, šķēršļi un kavēšanās')} viņu kaitina un izsīkina spēcīgāk nekā citus.`,
    "Vērsis": `Šo cilvēku iekšēji dzen ${POS('stabilitāte, miers un taustāma drošība')} — viņš būvē lēni, pamatīgi un noturīgi, vērtējot to, kas ir reāls un paliekošs. Viņš uzplaukst ${POS('paredzamā, drošā un ērtā vidē')}; turpretī ${NEG('steiga, haoss un pēkšņas pārmaiņas')} viņu satrauc un izsit no līdzsvara.`,
    "Dvīņi": `Šo cilvēku iekšēji dzen ${POS('zinātkāre, daudzpusība un saziņa')} — prāts pastāvīgi kustībā, viņš mācās, savieno idejas un meklē jaunu informāciju. Viņš uzplaukst ${POS('daudzveidībā, dialogā un brīvā ideju plūsmā')}; turpretī ${NEG('vienmuļība, rutīna un garlaicība')} viņu nomāc un liek zaudēt interesi.`,
    "Vēzis": `Šo cilvēku iekšēji dzen ${POS('jūtas, rūpes un piederība')} — emocijas, tuvība un drošības sajūta ir viņa kodols, un viņš dabiski gādā par citiem. Viņš uzplaukst, kad jūtas ${POS('emocionāli drošs un piederīgs')}; turpretī ${NEG('aukstums, atstumšana un nedrošība')} viņu ievaino dziļāk nekā citus.`,
    "Lauva": `Šo cilvēku iekšēji dzen ${POS('radīšana, pašizpausme un vēlme atstāt paliekošu nospiedumu')} — viņam ir svarīgi ${POS('tikt pamanītam un novērtētam')}. Viņš darbojas ar ${POS('lepnumu un dāsnumu')}, dabiski tiecas uz centra lomu un uzplaukst, kad viņa ieguldījumu atzīst; turpretī ${NEG('vienaldzība un nepamanīšana')} viņu nomāc spēcīgāk nekā citus.`,
    "Jaunava": `Šo cilvēku iekšēji dzen ${POS('kārtība, precizitāte un noderīgums')} — viņš meklē jēgu un pilnību detaļās un grib būt ${POS('kompetents un patiesi vajadzīgs')}. Viņš uzplaukst, kad var ${POS('uzlabot, sakārtot un atrisināt')}; turpretī ${NEG('haoss, paviršība un kritika')} viņu satrauc, un viņš mēdz būt pārāk paškritisks.`,
    "Svari": `Šo cilvēku iekšēji dzen ${POS('harmonija, taisnīgums un attiecības')} — līdzsvars, skaistums un saskaņa ir viņa kodols, un viņš tiecas savienot un izlīdzināt. Viņš uzplaukst ${POS('mierīgā, sadarbīgā un godīgā vidē')}; turpretī ${NEG('konflikti, netaisnība un spiediens izvēlēties')} viņu nomāc un liek vilcināties.`,
    "Skorpions": `Šo cilvēku iekšēji dzen ${POS('dziļums, intensitāte un patiesība')} — viņš meklē to, kas slēpjas zem virsmas, un spēj ${POS('pārveidoties un iet līdz pašam galam')}. Viņš uzplaukst, kad var ${POS('pilnībā nodoties un kontrolēt sev svarīgo')}; turpretī ${NEG('virspusība, nodevība un kontroles zaudēšana')} viņu skar dziļi.`,
    "Strēlnieks": `Šo cilvēku iekšēji dzen ${POS('brīvība, jēga un plašums')} — viņš tiecas mācīties, ceļot, izprast lielo ainu un meklēt augstāku jēgu. Viņš uzplaukst ${POS('brīvībā, izaugsmē un jaunās iespējās')}; turpretī ${NEG('ierobežojumi, sīkumainība un rutīna')} viņu žņaudz.`,
    "Mežāzis": `Šo cilvēku iekšēji dzen ${POS('mērķtiecība, disciplīna un ilgtermiņa būvēšana')} — viņš strādā nopietni, pacietīgi un grib ${POS('sasniegt rezultātu un nostiprināt statusu')}. Viņš uzplaukst ${POS('skaidrā struktūrā ar reālu atbildību')}; turpretī ${NEG('neefektivitāte, vieglprātība un kontroles trūkums')} viņu kaitina.`,
    "Ūdensvīrs": `Šo cilvēku iekšēji dzen ${POS('oriģinalitāte, neatkarība un idejas')} — viņš domā savā galvā, redz nākotni un tiecas uz ${POS('progresu un kopienas labumu')}. Viņš uzplaukst ${POS('brīvībā eksperimentēt un domāt savādāk')}; turpretī ${NEG('spiediens pielāgoties un stingras konvencijas')} viņu nomāc.`,
    "Zivis": `Šo cilvēku iekšēji dzen ${POS('jūtīgums, intuīcija un iztēle')} — robežas izplūst, un viņu vada dvēsele, līdzjūtība un radošums. Viņš uzplaukst ${POS('radošā, līdzjūtīgā un iedvesmojošā vidē')}; turpretī ${NEG('skarbums, spiediens un pārāk daudz nežēlīgas realitātes')} viņu nogurdina.`
};

export function computeArudhaLagna(profile) {
    const sid = profile?.birth_sidereal;
    if (!sid || sid["Ascendant"] === undefined) return null;

    const lagnaIdx = Math.floor(sid["Ascendant"] / 30) % 12;
    const lagnaName = SIGNS[lagnaIdx];
    const lord = SIGN_RULERS[lagnaName];
    if (sid[lord] === undefined) return null;

    const lordIdx = Math.floor(sid[lord] / 30) % 12;
    const dist = (lordIdx - lagnaIdx + 12) % 12;     // attālums zīmēs no lagnas līdz valdniekam
    let arudhaIdx = (lordIdx + dist) % 12;            // tāds pats attālums no valdnieka

    // BPHS izņēmums: ja Arudha sakrīt ar lagnu vai ir 7. no tās → ņem 10. no Arudhas
    if (arudhaIdx === lagnaIdx || arudhaIdx === (lagnaIdx + 6) % 12) {
        arudhaIdx = (arudhaIdx + 9) % 12;
    }

    // Plaisa starp būtību un tēlu (mazākais attālums zīmēs, 0–6)
    const raw = (arudhaIdx - lagnaIdx + 12) % 12;
    const gap = Math.min(raw, 12 - raw);
    // Arudha vienmēr ir PĀRA zīmju attālumā no lagnas (raw = 2×dist), un BPHS izņēmums
    // kartē attālumu 0/6 → 3, tāpēc gap ∈ {2,3,4}. Tāpēc 3 reāli sasniedzami līmeņi:
    //   gap 2 = mazākais (tēls tuvu būtībai) · gap 3 = vidējs · gap 4 = lielākais (manāma plaisa)
    const gapText = gap <= 2
        ? `Šī cilvēka tēls ${POS('lielā mērā sakrīt')} ar viņa būtību — cilvēki viņu redz aptuveni tādu, kāds viņš ir patiesībā. ${POS('Pirmais iespaids ir samērā uzticams')}, un starp iekšējo un ārējo ir maz berzes.`
        : gap === 3
        ? `Šī cilvēka tēls ${NEG('nedaudz atšķiras')} no būtības — daļa cilvēku viņu uztver virspusēji vai pārprot kādu iezīmi. Pirmais iespaids dod ${NEG('tikai daļēju ainu')}, tāpēc tuvāka iepazīšanās atklāj citu nokrāsu, nekā gaidīts.`
        : `Starp šī cilvēka būtību un publisko tēlu ir ${NEG('manāma plaisa')} — daudzi redz citu cilvēku, nekā viņš ir iekšēji. Viņu ${NEG('viegli novērtēt nepareizi')} pēc pirmā iespaida, un viņam pašam mēdz nākties vai nu apzināti tuvināt tēlu būtībai, vai dzīvot ar sajūtu, ka tiek ${NEG('nepareizi nolasīts')}.`;

    return {
        lagna: lagnaName,
        lagnaSelf: LAGNA_SELF[lagnaName] || '',
        lord,
        lordSign: SIGNS[lordIdx],
        arudha: SIGNS[arudhaIdx],
        meaning: ARUDHA_PERCEPTION[SIGNS[arudhaIdx]] || '',
        gap,
        gapText
    };
}

// ── Navamša (D9) — Vargottama, Karakamsa, uzsvars ────────────────────────────
const NAV_CLASSICAL = ["Saule","Meness","Merkurs","Venera","Marss","Jupiters","Saturns","Rahu","Ketu"];

// D9 zīmes iekšējā/dvēseles kvalitāte
const NAV_SIGN = {
    "Auns":"drosmi, iniciatīvu un neatkarīgu rīcību",
    "Vērsis":"stabilitāti, pacietību un juteklisku sakņošanos",
    "Dvīņi":"zinātkāri, daudzpusību un elastīgu prātu",
    "Vēzis":"emocionālu dziļumu, rūpes un piederību",
    "Lauva":"radošumu, vadību un pašcieņu",
    "Jaunava":"precizitāti, kalpošanu un pilnveidi",
    "Svari":"harmoniju, partnerību un taisnīgumu",
    "Skorpions":"intensitāti, transformāciju un dziļu patiesību",
    "Strēlnieks":"jēgu, filozofiju un augstāku mācīšanos",
    "Mežāzis":"atbildību, disciplīnu un ilgtermiņa būvēšanu",
    "Ūdensvīrs":"oriģinalitāti, neatkarību un vīziju",
    "Zivis":"līdzjūtību, intuīciju un garīgumu"
};

export function computeNavamsha(profile) {
    const sid = profile?.birth_sidereal;
    const nv  = profile?.vedic?.navamsha;
    if (!sid || !nv) return null;

    const planets = NAV_CLASSICAL
        .filter(p => nv[p] && sid[p] !== undefined)
        .map(p => {
            const d1 = SIGNS[Math.floor(sid[p] / 30) % 12];
            const d9 = nv[p];
            return { planet: p, d1, d9, vargottama: d1 === d9 };
        });
    if (!planets.length) return null;

    const vargottama = planets.filter(p => p.vargottama).map(p => p.planet);

    // Karakamsa — Atmakarakas (dvēseles planētas) D9 zīme
    const akPlanet = profile?.vedic?.atmakaraka?.planet;
    const karakamsa = (akPlanet && nv[akPlanet])
        ? { planet: akPlanet, sign: nv[akPlanet], quality: NAV_SIGN[nv[akPlanet]] || '' }
        : null;

    // D9 uzsvars — zīme ar visvairāk planētām (dvēseles orientācija)
    const counts = {};
    planets.forEach(p => { counts[p.d9] = (counts[p.d9] || 0) + 1; });
    let emphasisSign = null, emphasisCount = 0;
    for (const [s, c] of Object.entries(counts)) if (c > emphasisCount) { emphasisSign = s; emphasisCount = c; }
    const emphasisPlanets = planets.filter(p => p.d9 === emphasisSign).map(p => p.planet);

    return {
        planets, vargottama, karakamsa,
        emphasis: emphasisCount >= 2 ? { sign: emphasisSign, count: emphasisCount, planets: emphasisPlanets, quality: NAV_SIGN[emphasisSign] || '' } : null,
        moon:  planets.find(p => p.planet === 'Meness') || null,
        venus: planets.find(p => p.planet === 'Venera') || null,
        signQuality: NAV_SIGN
    };
}

// ── Aspektu raksti — T-kvadrāts / Lielais trijstūris / Jods ──────────────────
// Iekļauj arī ārējās planētas (Urāns/Neptūns/Plutons) — standarts Rietumu aspektu
// rakstos (atšķirībā no Vēdu D9). Tas būtiski palielina rakstu atpazīšanu.
const PATTERN_PLANETS = ["Saule","Meness","Merkurs","Venera","Marss","Jupiters","Saturns","Urans","Neptuns","Plutons"];

function sep(a, b) {
    let d = Math.abs(a - b) % 360;
    if (d > 180) d = 360 - d;
    return d;
}

// Planētu psiholoģiskās tēmas (aspektu rakstu interpretācijai)
const PLANET_THEMES = {
    "Saule":"identitāte un dzīves virziens", "Meness":"emocijas un iekšējās vajadzības",
    "Merkurs":"domāšana un saziņa", "Venera":"mīlestība, vērtības un attiecības",
    "Marss":"griba, enerģija un rīcība", "Jupiters":"izaugsme, jēga un pārliecība",
    "Saturns":"disciplīna, atbildība un bailes",
    "Urans":"pārmaiņas, brīvība un negaidīti pavērsieni", "Neptuns":"iztēle, ideāli un izplūšana",
    "Plutons":"vara, transformācija un dziļas pārmaiņas"
};
const th = (p) => PLANET_THEMES[p] || p;

export function computeAspectPatterns(profile) {
    const pl = profile?.western?.planets || {};
    const pts = PATTERN_PLANETS
        .filter(p => pl[p] && pl[p].longitude !== undefined)
        .map(p => ({ name: p, lon: pl[p].longitude }));

    const patterns = [];
    const near = (val, target, orb) => Math.abs(val - target) <= orb;

    // Lielais trijstūris — 3 planētas savstarpēji ~120°
    for (let i = 0; i < pts.length; i++)
        for (let j = i + 1; j < pts.length; j++)
            for (let k = j + 1; k < pts.length; k++) {
                if (near(sep(pts[i].lon, pts[j].lon), 120, 8) &&
                    near(sep(pts[j].lon, pts[k].lon), 120, 8) &&
                    near(sep(pts[i].lon, pts[k].lon), 120, 8)) {
                    const [a, b, c] = [pts[i].name, pts[j].name, pts[k].name];
                    patterns.push({
                        type: "Lielais trijstūris",
                        icon: "△", color: "#10b981",
                        planets: [a, b, c], apex: null,
                        meaning: `Iedzimta, viegli plūstoša dāvana, kas dabiski savieno ${th(a)}, ${th(b)} un ${th(c)}. Šīs jomas strādā bez piepūles — risks ir uztvert tās kā pašsaprotamas un neattīstīt.`
                    });
                }
            }

    // T-kvadrāts — opozīcija (~180°) + abas planētas kvadrātā (~90°) ar trešo (apex)
    for (let i = 0; i < pts.length; i++)
        for (let j = i + 1; j < pts.length; j++) {
            if (!near(sep(pts[i].lon, pts[j].lon), 180, 7)) continue;
            for (let k = 0; k < pts.length; k++) {
                if (k === i || k === j) continue;
                if (near(sep(pts[i].lon, pts[k].lon), 90, 7) &&
                    near(sep(pts[j].lon, pts[k].lon), 90, 7)) {
                    const apex = pts[k].name;
                    patterns.push({
                        type: "T-kvadrāts",
                        icon: "⊥", color: "#ef4444",
                        planets: [pts[i].name, pts[j].name, apex], apex,
                        meaning: `Iekšēja spriedze starp ${th(pts[i].name)} un ${th(pts[j].name)}, kas dzen uz sasniegumiem caur neapmierinātību. Izlādes ceļš ir <b>${apex}</b> (${th(apex)}) — tieši šeit šī enerģija jāizmanto apzināti.`
                    });
                }
            }
        }

    // Jods — divi sekstili (~60°) + abi kvinkunksi (~150°) ar trešo (apex)
    for (let i = 0; i < pts.length; i++)
        for (let j = i + 1; j < pts.length; j++) {
            if (!near(sep(pts[i].lon, pts[j].lon), 60, 3)) continue;
            for (let k = 0; k < pts.length; k++) {
                if (k === i || k === j) continue;
                if (near(sep(pts[i].lon, pts[k].lon), 150, 3) &&
                    near(sep(pts[j].lon, pts[k].lon), 150, 3)) {
                    const apex = pts[k].name;
                    patterns.push({
                        type: "Jods (Likteņa pirksts)",
                        icon: "Y", color: "#8b5cf6",
                        planets: [pts[i].name, pts[j].name, apex], apex,
                        meaning: `Smalks, gandrīz liktenīgs aicinājums: ${th(pts[i].name)} un ${th(pts[j].name)} virza uz <b>${apex}</b> (${th(apex)}) kā īpašu dzīves uzdevumu. Prasa pastāvīgu pielāgošanos, līdz atrod savu aicinājumu.`
                    });
                }
            }
        }

    return patterns;
}

// ── Vienkāršots planētu spēka indekss (Shadbala-lite) ────────────────────────
// Apvieno: dabiskais spēks (Naisargika) + dignitāte (eksaltācija/mājvieta/kritums)
//          + virziena spēks (Dig bala). NAV pilns klasiskais Shadbala.
const NAISARGIKA = { "Saule":100, "Meness":86, "Venera":71, "Jupiters":57, "Merkurs":43, "Marss":29, "Saturns":14 };
const EXALT = { "Saule":"Auns", "Meness":"Vērsis", "Marss":"Mežāzis", "Merkurs":"Jaunava", "Jupiters":"Vēzis", "Venera":"Zivis", "Saturns":"Svari" };
const OWN   = { "Saule":["Lauva"], "Meness":["Vēzis"], "Marss":["Auns","Skorpions"], "Merkurs":["Dvīņi","Jaunava"], "Jupiters":["Strēlnieks","Zivis"], "Venera":["Vērsis","Svari"], "Saturns":["Mežāzis","Ūdensvīrs"] };
// Labākā māja virziena spēkam (Dig bala)
const DIG_HOUSE = { "Jupiters":1, "Merkurs":1, "Saule":10, "Marss":10, "Saturns":7, "Meness":4, "Venera":4 };

export function computePlanetStrength(profile) {
    const sid = profile?.birth_sidereal;
    if (!sid || sid["Ascendant"] === undefined) return [];
    const lagnaIdx = Math.floor(sid["Ascendant"] / 30) % 12;

    const out = [];
    for (const p of Object.keys(NAISARGIKA)) {
        if (sid[p] === undefined) continue;
        const signIdx = Math.floor(sid[p] / 30) % 12;
        const signName = SIGNS[signIdx];
        const house = ((signIdx - lagnaIdx + 12) % 12) + 1;

        // Dignitāte
        let dignity = 0, dignityLabel = "Neitrāla";
        if (EXALT[p] === signName) { dignity = 20; dignityLabel = "Eksaltācija"; }
        else if ((OWN[p] || []).includes(signName)) { dignity = 12; dignityLabel = "Mājvieta"; }
        else if (EXALT[p] === SIGNS[(signIdx + 6) % 12]) { dignity = -20; dignityLabel = "Kritums"; }

        // Virziena spēks
        const dig = (DIG_HOUSE[p] === house) ? 15 : 0;

        const score = Math.max(0, Math.min(100, Math.round(NAISARGIKA[p] * 0.55 + 25 + dignity + dig)));
        out.push({ planet: p, sign: signName, house, score, dignityLabel, dig: dig > 0 });
    }
    out.sort((a, b) => b.score - a.score);
    return out;
}
