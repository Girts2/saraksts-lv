// ── CILNE 'y' — SLĒPTIE IESKATI ───────────────────────────────────────────────
// Apkopo visu, ko horoskops aprēķina, bet līdz šim nerādīja:
//   1. Patiesi paslēptie: Hellēnisma daļas, Maiju ēna, Taro
//   2. Apglabātie: Yogas, Aštakavarga, Navamša, Super-events, Midpoints, Muntha
//   3. Jaunie/atklātie aprēķini: Profekcijas, Triplicitāte, Arudha, Aspektu raksti,
//      Planētu spēka indekss, BaZi sadursmes/harmonijas

import { interpretLot, interpretProfection, computeArudhaLagna, computeAspectPatterns, computePlanetStrength, computeNavamsha } from '../../logic/hidden_insights.js?v=10';

// ── Palīgi ────────────────────────────────────────────────────────────────────
const safe = (fn) => { try { return fn() || ''; } catch (e) { return `<div style="color:#ef4444;font-size:0.8rem;padding:0.4rem;">⚠ ${e.message}</div>`; } };

function card(icon, title, sub, inner, theory) {
    if (!inner) return '';
    const theoryBlock = theory ? `
        <div style="margin-top:0.9rem; padding:0.6rem 0.85rem; background:#f8fafc; border-left:3px solid #cbd5e1; border-radius:0 6px 6px 0; font-size:0.74rem; line-height:1.55; color:#64748b;">
            <div><b style="color:#475569;">Ko tā dara:</b> ${theory.does}</div>
            <div style="margin-top:3px;"><b style="color:#475569;">Teorētiskais maksimums:</b> ${theory.max}</div>
        </div>` : '';
    return `
    <div style="background:white; border-radius:14px; padding:1.3rem 1.4rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1rem;">
        <div style="display:flex; align-items:baseline; gap:0.6rem; margin-bottom:0.9rem; flex-wrap:wrap;">
            <span style="font-size:1.15rem;">${icon}</span>
            <h3 style="font-size:0.98rem; color:#1e293b; margin:0; font-weight:800;">${title}</h3>
            ${sub ? `<span style="font-size:0.72rem; color:#94a3b8;">${sub}</span>` : ''}
        </div>
        ${inner}
        ${theoryBlock}
    </div>`;
}

function groupHeading(text) {
    return `<h2 style="font-size:0.78rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:2px; margin:1.6rem 0 0.8rem; padding-bottom:6px; border-bottom:2px solid #e2e8f0;">${text}</h2>`;
}

const fmtDate = (ms) => { try { return new Date(ms).toLocaleDateString('lv-LV', { year:'numeric', month:'short', day:'numeric' }); } catch { return '—'; } };

// ── Arudha Lagna karte — atsevišķi eksportēta (pārcelta uz cilni 'Psiholoģija' 2026-06-08) ──
export function renderArudhaCard(profile) {
    const arudhaHtml = safe(() => {
        const a = computeArudhaLagna(profile);
        if (!a) return '';
        return `
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem;">
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.8rem 1rem;">
                <div style="font-size:0.62rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Kas cilvēks ir iekšēji · ${a.lagna}</div>
                <div style="font-size:0.85rem; color:#1e293b; line-height:1.55;">${a.lagnaSelf}</div>
            </div>
            <div style="background:rgba(124,58,237,0.07); border:1px solid #e9d5ff; border-radius:10px; padding:0.8rem 1rem;">
                <div style="font-size:0.62rem; color:#7c3aed; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Kā cilvēku redz citi · ${a.arudha}</div>
                <div style="font-size:0.85rem; color:#1e293b; line-height:1.55;">${a.meaning}</div>
            </div>
        </div>
        <div style="font-size:0.82rem; color:#475569; line-height:1.6; padding:0.55rem 0.85rem; background:#faf5ff; border-left:3px solid #8b5cf6; border-radius:0 8px 8px 0;">
            <b>Plaisa starp būtību un tēlu:</b> ${a.gapText}
        </div>`;
    });
    return card('🎭', 'Iekšējais Es un publiskais tēls', 'iekšējā būtība pret publisko tēlu', arudhaHtml);
}

export const MIDPOINT_DICT = {
    'Sun/Moon': {
        title: 'Eksistenciālās krīzes un izdegšanas ass',
        'Auns': { g: 'Krīzē ieslēdz karavīra režīmu un cīnās viens pats.', r: 'Ātri izdeg, ja viņam nedod brīvību un lēmumu varu.', h: 'Uzticiet viņam "ugunsgrēku dzēšanu" vienatnē, kā varonim.' },
        'Vērsis': { g: 'Panikā saglabā absolūtu mieru un glābj uzņēmuma pamatresursus.', r: 'Apdraudējumā kļūst pilnīgi ietiepīgs un neizkustināms.', h: 'Dodiet konkrētu, nemainīgu rīcības plānu ar taustāmiem mērķiem.' },
        'Dvīņi': { g: 'Zibenīgi atrod izeju caur komunikāciju, idejām un kontaktiem.', r: 'Haosā kļūst nervozs, pļāpīgs un izkliedē uzmanību desmit virzienos.', h: 'Sūtiet viņu runāt un vākt informāciju, bet ne pieņemt gala lēmumus.' },
        'Vēzis': { g: 'Krīzē kā māte aizstāvēs savu komandu līdz pēdējam.', r: 'Emocionāli sabrūk, ja jūt nodevību no vadības vai kolēģiem.', h: 'Pārlieciniet, ka komanda ir drošībā, tad viņš strādās stoiski.' },
        'Lauva': { g: 'Traģēdijā uzņemas drāmas varoņa lomu un spēj iedvesmot pūli.', r: 'Sabrūk, ja viņa upurus neviens publiski neciena un nenovērtē.', h: 'Slavējiet viņa devumu glābšanas operācijā — tas viņu baros.' },
        'Jaunava': { g: 'Sistēmas sabrukumā aukstasinīgi salabo procesus, detaļas un tabulas.', r: 'Iestrēgst sīkumos un pārmērīgi kontrolē citus, aizmirstot lielo mērķi.', h: 'Uzticiet viņam auditu un kārtības atjaunošanu, bet vadiet kursu.' },
        'Svari': { g: 'Sagraujošos konfliktos meklē kompromisus un spēj samierināt puses.', r: 'Spiediena apstākļos nespēj pieņemt lēmumus, svārstās un vilcinās.', h: 'Lūdziet palīdzību iekšējā diplomātijā, nevis ātrā rīcībā.' },
        'Skorpions': { g: 'Jo lielāka krīze, jo jaudīgāk strādā – izbauda ekstremālus izdzīvošanas apstākļus.', r: 'Sāk meklēt vainīgos un netieši izdarīt spiedienu uz vājākajiem.', h: 'Uzticiet viņam vissmagākos, nepatīkamākos krīzes uzdevumus.' },
        'Strēlnieks': { g: 'Ar neizsmeļamu optimismu un vīziju izved komandu no dziļākās bedres.', r: 'Ignorē riskus un pieņem neadekvāti optimistiskus lēmumus panikā.', h: 'Lielisks kā komandas iedvesmotājs, bet pieskatiet viņa riskus.' },
        'Mežāzis': { g: 'Ekstremālā situācijā kļūst par stabilu klinšu sienu un uzņemas atbildību.', r: 'Izdzīvošanas vārdā kļūst auksts, cinisks un bezkompromisa pret cilvēkiem.', h: 'Dodiet vadības grožus pragmatiskai, strukturālai glābšanai.' },
        'Ūdensvīrs': { g: 'Atrod neordināru, ģeniālu tehnoloģisku glābiņu no pilnīga strupceļa.', r: 'Atkabinās no realitātes un aroganti noraida visus tradicionālos padomus.', h: 'Nodrošiniet viņam brīvību un resursus dīvainajām izdzīvošanas idejām.' },
        'Zivis': { g: 'Spēj intuitīvi izglābt situāciju un nesavtīgi upurēties citu dēļ.', r: 'Spēcīgā panikā var pazust, slīgt ilūzijās vai pilnībā izolēties.', h: 'Nesitiet ar sausiem faktiem; palūdziet viņa intuitīvo vērtējumu par krīzi.' }
    },
    'Sun/Mars': {
        title: 'Fiziskās izdzīvošanas un uzbrukuma ass',
        'Auns': { g: 'Nekavējoties pāriet pretuzbrukumā bez jebkādām bailēm un domāšanas.', r: 'Dusmās akls niknums var likt asi vērsties pret oponentu.', h: 'Nevajag konfrontēt; novirziet šo cīņas enerģiju pret ārējo konkurentu.' },
        'Vērsis': { g: 'Ieņem aizsardzības pozīciju un cīnās kā buldozers, ko neviens nespēj izsist.', r: 'Kļūst agresīvi stūrgalvīgs, noslēdzas sevī un noraida jebkādus gājienus.', h: 'Nedzeniet viņu uz priekšu ar varu, spiedienā viņš izkustēsies pats savā tempā.' },
        'Dvīņi': { g: 'Uzbrukuma brīdī reaģē zibenīgi, izmantojot faktus un stratēģiju.', r: 'Krīzē mētājas uz visām pusēm, sējot vēl lielāku paniku un tenkas.', h: 'Neletiet viņam runāt ar presi vai klientiem bez stingras uzraudzības dusmu brīdī.' },
        'Vēzis': { g: 'Aizsargā uzņēmumu un komandu ar spēcīgu "mātes lācenes" aizsardzības instinktu.', r: 'Stūrī iedzīts, izmanto emocionālu spiedienu un izvairās no atklātas konfrontācijas — rīkojas netieši.', h: 'Neaiztieciet "viņa cilvēkus", pretējā gadījumā kļūsiet par ilgstošu pretinieku.' },
        'Lauva': { g: 'Sagrūstot autoritātei, drosmīgi un lepni nostājas priekšā, lai aizsargātu vājākos.', r: 'Ja krīzē tiek apstrīdēts viņa statuss, var atbildēt ar publisku oponenta sakaušanu.', h: 'Strīdos cieniet viņa lepnumu, nekad nesodiet citu kolēģu priekšā.' },
        'Jaunava': { g: 'Atvaira jebkuru uzbrukumu ar metodisku, dzelžainu un asu faktu analīzi.', r: 'Stresā kļūst kodīgs, un viņa kritika metodiski grauj citu pašapziņu.', h: 'Pieprasiet krīzes risinājumus, bet apturiet viņa "kļūdu meklēšanas" un vainošanas fāzi.' },
        'Svari': { g: 'Cīnās par taisnīgumu un vienlīdzību asās konfrontācijās un nepadodas.', r: 'Slēpj dusmas aiz pasīvās agresijas un nesaka problēmas tieši, baidoties no cīņas.', h: 'Apdraudējumā prasiet tiešu atbildi, neļaujot izvairīties no aci pret aci konflikta.' },
        'Skorpions': { g: 'Ideāls ārkārtas krīzes risinātājs – neitralizē jebkuru pretinieku ātri un bez trokšņa.', r: 'Apdraudēts ilgi atceras pāridarījumus un var uz tiem atgriezties pat pēc gadiem.', h: 'Konfliktā nekad, nekad nemelojiet un nenododiet viņu.' },
        'Strēlnieks': { g: 'Aizstāv principus ar ugunīgu pārliecību un spēj aizraut līdzi veselu izdzīvošanas "armiju".', r: 'Dusmās kļūst par morāli augstprātīgu un arogantu taisnības soģi.', h: 'Sūtiet viņu kā ideoloģisko cīnītāju un tēla glābēju sarežģītās sarunās.' },
        'Mežāzis': { g: 'Aukstasinīgi un bezkompromisa izdara spiedienu uz oponentu caur sistēmu, sodiem un likumu.', r: 'Krīzes brīdī ir gatavs upurēt lojālus kolēģus un nodaļas, lai glābtu kopējo struktūru.', h: 'Izcils spēles noteikumu ieviesējs "kara laikā", kad jāglābj uzņēmums.' },
        'Ūdensvīrs': { g: 'Pārrauj uzbrukuma ķēdi ar pilnīgi negaidītiem, asimetriskiem gājieniem, ko neviens neparedzēja.', r: 'Stūra iedzīts, uzvedas auksti, neprognozējami un atslēdzas no jebkādas loģikas.', h: 'Ļaujiet viņam cīnīties par izdzīvošanu savā unikālajā stilā, bez tipiskiem noteikumiem.' },
        'Zivis': { g: 'Smagā spiedienā atrod slepenus ceļus, "pelēkās zonas" un izejas, ko citi nepamana.', r: 'Tēlo upuri, izslīd no tiešas atbildības un atstāj izdzīvošanas krīzi risināt citiem.', h: 'Pieskatiet, lai krīzes un konflikta brīdī viņš fiziski vai mentāli nepazustu "miglā".' }
    },
    'Mercury/Mars': {
        title: 'Verbālā asuma un strīdu ass',
        'Auns': { g: 'Krīzes sapulcē saka patiesību acīs pat augstākajai vadībai, bez liekas politkorektuma.', r: 'Rupjš, impulsīvs un ass, var nolamāt kolēģus, bet ātri aizmirst.', h: 'Sūtiet viņu runāt tad, kad komandai vai klientam vajag stingru, tiešu signālu.' },
        'Vērsis': { g: 'Strīdā runā maz, bet viņa argumenti ir smagi, lēni un neizkustināmi kā betona bloki.', r: 'Stūrgalvīgi atkārto vienu un to pašu, pilnībā nedzirdot un ignorējot oponentu faktus.', h: 'Nemēģiniet viņu pārkliegt vai pārliecināt konfliktā, tas ir bezjēdzīgi un patērēs tikai laiku.' },
        'Dvīņi': { g: 'Konfliktā šauj zibenīgas replikas, spēj intelektuāli uzvarēt un sakaut jebkuru oponentu.', r: 'Spiediena rezultātā kļūst par kodīgu baumu izplatītāju, cinisku zobgali un provokatoru.', h: 'Ideāli der asām publiskajām debatēm, kad nepieciešams intelektuāls asums.' },
        'Vēzis': { g: 'Strīdā aizstāv vājākos ar sirdi, balstoties uz dziļu empātiju un taisnīgumu.', r: 'Ja zaudē argumentus, izmanto klusēšanu, nopūtas un vainas apziņas radīšanu.', h: 'Konfliktu vienmēr risiniet četrās acīs un sāciet ar emocionālo saikni, nevis sausiem cipariem.' },
        'Lauva': { g: 'Saspringtās pārrunās runā ar karaļa pārliecību un mudina oponentu atkāpties.', r: 'Spiediena rezultātā sāk bļaut, dramatizēt un uztver jebkuru kritisku vārdu kā smagu apvainojumu.', h: 'Strīdā vienmēr lūdziet izklāstīt viedokli, izrādot cieņu viņa statusam.' },
        'Jaunava': { g: 'Konfliktā kritizē oponentu ar ķirurģisku precizitāti un pārliecina ar neapgāžamiem faktiem.', r: 'Panikā pieķeras mikroskopiskiem sīkumiem un burtiski "izved no pacietības" ar perfekcionismu.', h: 'Ja vēlaties uzvarēt diskusiju ar viņu, lieciet pierakstīt savus argumentus e-pastā punktu pa punktam.' },
        'Svari': { g: 'Spēj pārliecināt oponentu ar diplomātisku, laipnu smaidu, asu inteliģenci un pieklājību.', r: 'Konfliktā baidās no tiešas cīņas, tāpēc aiz muguras pierunā citus, cenšoties saglabāt tīras rokas.', h: 'Uzmaniet viņu krīzes brīžos no "labā policista" ietekmēšanas un slēptas koalīciju veidošanas.' },
        'Skorpions': { g: 'Izsaka klusus, bet ļoti precīzus vārdus, kas apzināti trāpa oponenta dziļākajās bailēs.', r: 'Kritisku strīdu pārvērš par spiediena taktiku, kodīgiem mājieniem un citu noslēpumu izpaušanu.', h: 'Ja viņš sapulcē par sarežģītu krīzi klusē, uzmanieties — tas nozīmē, ka viņš ir sagatavojis pārdomātu gājienu.' },
        'Strēlnieks': { g: 'Pauž brutāli smagu patiesību ar humoru un tādu pārliecību, ka visi tam sāk ticēt.', r: 'Spriedzē kļūst arogants, nesmalkjūtīgs un uzskata, ka viņš ir vienīgais, kam ir taisnība telpā.', h: 'Aiciniet viņu asai diskusijai tad, kad komanda ir iestrēgusi un vajag paplašināt skatupunktu.' },
        'Mežāzis': { g: 'Strīdā katrs viņa izrunātais vārds ir kā āmura sitiens, demonstrējot absolūtu faktu autoritāti.', r: 'Noraida visus oponentu ierosinājumus kā nekompetentus ar klusu un augstprātīgu "akmens seju".', h: 'Gatavojoties konfliktam ar viņu, ņemiet līdzi TIKAI sausus datus un līgumus, bez emocijām.' },
        'Ūdensvīrs': { g: 'Atspēko pat visasākos oponentus ar negaidītu un pilnīgi aukstu loģisko paradoksu.', r: 'Spiedienā kļūst neciešami augstprātīgs un izturas kā neapšaubāms profesors pret nesaprašām.', h: 'Mēģiniet izmantot viņa "ārpus kastes" argumentus cīņai pret uzņēmuma konkurentiem.' },
        'Zivis': { g: 'Konfliktā izmanto poētiskus, viedus un spēcīgus tēlus, kas tieši uzrunā oponenta sirdsapziņu.', r: 'Klausās oponentu, pacietīgi māj ar galvu, bet neko neizdara, izliekas nesaprotam un netur solījumus.', h: 'Smagā strīdā koncentrējieties nevis uz ko viņš teica, bet KĀ un ko viņš tajā brīdī patiesi jūt.' }
    },
    'Mars/Saturn': {
        title: 'Paralīzes un spītības ass',
        'Auns': { g: 'Iet cauri jebkuram betonam un nepadodas pat pie absolūta fiziska un mentāla izsīkuma.', r: 'Ātrs sabrukums, ja krīzes brīdī ceļā ir lēna birokrātiska siena, ko nevar izsist ar spēku.', h: 'Nekad nelieciet viņam gaidīt, aizpildīt formas vai rakstīt atskaites aktīvas krīzes laikā.' },
        'Vērsis': { g: 'Velk krīzes un bankrota nastu kā pacietīgs vērsis — lēnām, sāpīgi, bet nekad neapstājoties.', r: 'Smagā spiedienā atsakās mainīt savu virzienu, pat ja tas skaidri nozīmē neizbēgamu katastrofu.', h: 'Ļaujiet saglabāt viņa lēno un ierasto tempu, viņš klusējot iznesīs smagāko laiku uz saviem pleciem.' },
        'Dvīņi': { g: 'Mentālā izturība pret haosu ir izcila; vienmēr meklēs un atradīs viltīgus apvedceļus izdzīvošanai.', r: 'Krīzē apjūk un paralizējas, ja viņam piespiež ilgstoši un monotoniski darīt vienu un to pašu.', h: 'Dodiet iespēju mainīt uzdevumus un domāt brīvi; neiesprostojiet viņu izolētā telpā un rutīnā.' },
        'Vēzis': { g: 'Noturēs komandu, ofisu un resursus visstingrākajā taupības režīmā, lai ģimene izdzīvotu.', r: 'Panikā noslēdzas savā drošības "čaulā" un sāk pilnībā ignorēt visas augstākās vadības prasības.', h: 'Cieniet viņa teritoriju un nepārkāpiet personīgās robežas pat ārkārtas stāvoklī.' },
        'Lauva': { g: 'Stoiski un klusējot iztur jebkuru spiedienu, lai tikai nesagādātu vilšanos saviem sekotājiem un faniem.', r: 'Ja krīzē netiek cienīts un respektēts, viņš lepni un demonstratīvi nometīs atbildību un aizies.', h: 'Lūdziet viņa izturību kā īpašu, neatkārtojamu pakalpojumu un upuri komandai.' },
        'Jaunava': { g: 'Birokrātiskos un pārmērīgas kontroles apstākļos spēj funkcionēt ideāli un nezaudēt prātu.', r: 'Krīzē pazūd sīkumos, paralizējas, kārtojot mapītes, kamēr visa lielā sistēma brūk.', h: 'Ārkārtas stāvoklī uzticiet viņam fiksēt procesus, bet vienmēr piekodiniet skatīties uz "lielo bildi".' },
        'Svari': { g: 'Uztur attiecības un mēģina saliedēt komandu arī tad, kad partneri asi konfliktē savā starpā.', r: 'Apdraudējumā iestājas pilnīga paralīze un klusēšana, kad tiek pieprasīts izvēlēties pusi asā konfliktā.', h: 'Krīzē maigi, bet dzelžaini spiediet pieņemt lēmumu, pretējā gadījumā viņš baidīsies darīt neko.' },
        'Skorpions': { g: 'Jo sliktāk un bīstamāk klājas uzņēmumam, jo dzelžaināks viņš kļūst. Krīze viņu reāli baro.', r: 'Pasīvi agresīvi klusi pretojas jebkuram krīzes rīkojumam, kas viņam šķiet "vājš" vai nepietiekami radikāls.', h: 'Iedodiet viņam visgrūtāko, izmisīgāko un nepatīkamāko izdzīvošanas misiju pilnīgā vienatnē.' },
        'Strēlnieks': { g: 'Uztur komandas morāli un skatās uz garo mērķi, smaidot ignorējot reālās fiziskās sāpes un spiedienu.', r: 'Spiedienā atklāti izsmej un ignorē sīkos drošības noteikumus, kas viņam šķietami traucē izdzīvot.', h: 'Uzticiet rīcību un komandu, bet nekad nedodiet viņam dokumentācijas kārtošanu krīzes pīķī.' },
        'Mežāzis': { g: 'Absolūta disciplīna. Izdarīs jebko un strādās bez miega, lai tikai struktūra neizjuktu.', r: 'Spiedienā pārvēršas par auksti bezpersonisku izpildītāju, neņemot vērā darbinieku nogurumu vai lūgumus.', h: 'Pievērsiet uzmanību viņa asajiem stūriem, lai viņš izdzīvojot pilnībā nesalauztu komandas garu.' },
        'Ūdensvīrs': { g: 'Spēj izturēt dzelžainu un ilgstošu izolāciju no citiem, strādājot pie utopiska krīzes risinājuma.', r: 'Saskaroties ar rīkojumiem, atteiksies pakļauties un demonstratīvi aizies "pagrīdes pretestībā".', h: 'Pat izmisuma un panikas režīmā obligāti respektējiet viņa dīvainās darba un domāšanas metodes.' },
        'Zivis': { g: 'Spēj intuitīvi strādāt un pielāgoties totālā haosā, instinktīvi izdzīvojot jebkurā vidē.', r: 'Ilgstošā un smagā krīzē viņš var vienkārši pazust bez paskaidrojumiem vai pilnībā zaudēt realitātes sajūtu.', h: 'Ārkārtas stāvoklī ieviesiet viņam ļoti skaidru, gandrīz bērnišķīgu dienas grafiku, lai noturētu viņu pie zemes.' }
    },
    'Jupiter/Uranus': {
        title: 'Radikālo lūzumu un anarhijas ass',
        'Auns': { g: 'Uzņēmuma nāves brīdī izraisa eksplozīvu, ģeniālu pavērsienu, mainot virzienu pēdējā sekundē.', r: 'Panikā pēkšņi riskē ar pēdējiem uzņēmuma resursiem pilnīgi neapdomīgi un impulsīvi.', h: 'Krīzes izlaušanās brīdī paveriet ceļu un ļaujiet viņam ar spēku izsist aizslēgtās durvis.' },
        'Vērsis': { g: 'Apdraudējumā izdomā inovatīvu un reālu veidu, kā pēkšņi piesaistīt vai izvilkt lielu naudu.', r: 'Iespringst uz brīnumainiem, taču utopiskiem peļņas modeļiem, kas praksē neatbilst reālajai tirgus situācijai.', h: 'Izmantojiet viņa pēkšņos uzplaiksnījumus TIKAI ārkārtas krīzes finansējuma atrašanai.' },
        'Dvīņi': { g: 'Vissliktākajā krīzes momentā spēj ģeniāli pārrakstīt visus spēles noteikumus savā un uzņēmuma labā.', r: 'Katastrofā mētājas starp tūkstoš ģeniālām idejām minūtē, beigās nespējot realizēt nevienu no tām.', h: 'Krīzē lūdziet viņam ģenerēt drosmīgas idejas un pārveidot sistēmu, bet realizāciju vienmēr uzticiet citiem.' },
        'Vēzis': { g: 'Lai izdzīvošanu un saglabātu darbinieku saikni, pēkšņi un veiksmīgi ievieš radikāli jaunu iekšējo kultūru.', r: 'Spiedienā kļūst iracionāli emocionāls un var pieņemt slēgtus vai fatālus personāla lēmumus.', h: 'Vienmēr uzmanīgi klausieties viņa nojautās par cilvēkresursiem krīzes un masveida atlaišanu laikā.' },
        'Lauva': { g: 'Brīdī, kad reputācija ir smagi cietusi, spēj veikt izcilu, harizmātisku un drosmīgu publisko uznācienu.', r: 'Haosā izspēlē nevajadzīgas drāmas un dod pārspīlētus, teatrālus solījumus masām, ko fiziski nevar izpildīt.', h: 'Izmantojiet viņu kā galveno krīzes saziņas seju, taču obligāti nodrošiniet, ka kāds jurists viņam sagatavo runas tekstu.' },
        'Jaunava': { g: 'Sistēmas krahā viņš detaļās atklāj radikālu un neredzētu veidu, kā optimizēt procesus par 300%.', r: 'Panikā var nejauši izjaukt visu strādājošo krīzes sistēmu, mēģinot to pārveidot "absolūti perfekti".', h: 'Sūtiet viņu pēkšņi un izlēmīgi modernizēt kādu kritiski novecojušu vai iestrēgušu zaudējumu nodaļu.' },
        'Svari': { g: 'Izdomā ģeniālus kompromisus un negaidītas partnerības pat starp konkurentiem uzņēmuma glābšanai.', r: 'Negaidītā spriedzē var pēkšņi mainīt puses, noslēgt dīvainus līgumus un pamest iepriekšējos sabiedrotos.', h: 'Izcils kā ārkārtas pārrunu vedējs ar bankrotētājiem vai agresīviem kreditoriem.' },
        'Skorpions': { g: 'Sagrūstot visam, spēj mistiski izvilkt uzņēmumu "no pelniem" kā fēnikss, ieviešot radikālas metodes.', r: 'Sēj ap sevi pilnīgu anarhiju un spēcīgu spiedienu; mēģina nojaukt visu esošo, pirms rada jauno.', h: 'Uzticiet viņam bez vilcināšanās pilnībā pārstrukturēt sliktās un neefektīvās zaudējumu nodaļas.' },
        'Strēlnieks': { g: 'Kad nav nekādu cerību uz izdzīvošanu, viņš pēkšņi saredz vizionāru izeju starptautiskā mērogā.', r: 'Spiedienā izvirza gigantiskus, nereālus krīzes plānus, kuriem nav pilnīgi nekāda seguma reālajā ekonomikā.', h: 'Izmantojiet viņu kā stratēģisko un garīgo vīzijas devēju tunelim bez gaismas, bet kontrolējiet reālo finanšu izpildi.' },
        'Mežāzis': { g: 'Smagā haosā un anarhijā zibenīgi uzbūvē pavisam jaunu, vēl dzelžaināku un stabilāku izdzīvošanas struktūru.', r: 'Izmisumā noraida visus ieteikumus un ievieš stingru, stūrgalvīgu vienpersonisku kārtību bez elementāras iejūtības.', h: 'Krīzes brīdī uzticiet viņam no nulles izstrādāt un nekavējoties ieviest jaunos "izdzīvošanas" un krīzes likumus.' },
        'Ūdensvīrs': { g: 'Strupceļā piedāvā futūristisku stratēģiju, kas ir 10 gadus priekšā esošajiem un konkurentu modeļiem.', r: 'Pilnībā noraida jelkādu autoritāti un krīzes pašā karstumā var demonstratīvi izstāties no glābšanas komandas.', h: 'Uzklausiet viņu – viņa šķietami trakās un dīvainās tehnoloģiskās idejas izmisuma brīdī tiešām var strādāt.' },
        'Zivis': { g: 'Ar spēcīgu intuīciju uztausta "nākamo lielo vilni", kad tirgus brūk, un parāda virzienu glābiņam.', r: 'Gaidot brīnumu, ieslīgst pasīvā gaidīšanā un nespēj vadīt pilnīgi nekādus reālos procesus.', h: 'Ja smagas krīzes laikā viņš klusējot apgalvo, ka redz mistisku izeju — tas ļoti bieži izrādās pilnīga taisnība.' }
    }
};

export function renderMidpointsCard(profile) {
    const midpointsHtml = safe(() => {
        const mp = profile.western?.midpoints || [];
        if (!mp.length) return '';
        const SIGNS = ["Auns","Vērsis","Dvīņi","Vēzis","Lauva","Jaunava","Svari","Skorpions","Strēlnieks","Mežāzis","Ūdensvīrs","Zivis"];
        const PAIR_LV = {
            'Sun/Moon': 'Saule–Mēness', 'Sun/Mars': 'Saule–Marss', 'Mercury/Mars': 'Merkurs–Marss',
            'Mars/Saturn': 'Marss–Saturns', 'Jupiter/Uranus': 'Jupiters–Urāns'
        };

        return `<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); align-items:start; gap:1rem;">
            ${mp.map(m => {
                if (m.degree == null) return '';
                const signIndex = Math.floor(m.degree/30)%12;
                const sign = `${(m.degree % 30).toFixed(1)}° ${SIGNS[signIndex]}`;
                const elType = [0,4,8].includes(signIndex) ? 'Uguns' : [1,5,9].includes(signIndex) ? 'Zeme' : [2,6,10].includes(signIndex) ? 'Gaiss' : 'Ūdens';
                const elColor = elType === 'Uguns' ? '#ea580c' : elType === 'Zeme' ? '#166534' : elType === 'Gaiss' ? '#0284c7' : '#0f766e';
                const sName = SIGNS[signIndex];

                const dict = MIDPOINT_DICT;
                const baseData = dict[m.pair];
                if (!baseData) return '';
                const el = baseData[sName] || {g:'', r:'', h:''};
                const pairLabel = PAIR_LV[m.pair] || m.pair;

                return `
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem 1rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:5px; margin-bottom:0.4rem;">
                        <span style="font-size:0.92rem; font-weight:800; color:#1e293b;">${baseData.title}</span>
                        <span style="font-size:0.7rem; font-weight:700; background:#e2e8f0; color:${elColor}; padding:3px 7px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                            <span style="color:#64748b;">${pairLabel} · ${sign}</span> <b>${elType}</b>
                        </span>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr; gap:0.5rem; font-size:0.8rem; line-height:1.45;">
                        <div style="color:#15803d;"><span style="font-weight:700;">🟢 Specifika krīzē:</span> ${el.g}</div>
                        <div style="color:#b91c1c;"><span style="font-weight:700;">🔴 Bīstamība:</span> ${el.r}</div>
                        <div style="margin-top:0.2rem; padding-top:0.5rem; border-top:1px dashed #cbd5e1; color:#0f172a;"><span style="font-weight:700; color:#475569;">HR Padoms:</span> <b style="color:#1e293b;">${el.h}</b></div>
                    </div>
                </div>`;
            }).join('')}
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border:1px dashed #cbd5e1; border-radius:10px; padding:1.2rem; display:flex; flex-direction:column; justify-content:center; color:#475569; font-size:0.75rem; line-height:1.4;">
                <b style="color:#334155; margin-bottom:0.4rem; display:block; font-size:0.85rem;">📌 Kā lasīt šo paneli?</b>
                <p style="margin:0 0 0.5rem 0;">Apraksta uzvedību <b>tikai</b> zem liela spiediena un smagās krīzes situācijās. Palīdz saprast, kuros brīžos darbinieks mirdzēs un kuros var pilnībā nolemt projektu.</p>
                <p style="margin:0 0 0.5rem 0;"><b>Kā tas strādā:</b> Konvertē klasiskos astropsiholoģijas stresa punktus reālos izdzīvošanas scenārijos un uzvedības modeļos.</p>
                <p style="margin:0;"><b>Kāpēc tas ir vērtīgi:</b> Sniegs gatavas un ļoti tiešas HR vadlīnijas krīzes menedžmentam, palīdzot noteikt kandidāta dabisko lomu haosā.</p>
            </div>
        </div>`;
    });
    return card('⚖️', 'Uzvedība krīzes situācijās', 'Reakcija un dabiskā loma zem spiediena', midpointsHtml);
}

// Darba klupšanas akmeņi pa vadības stilam (8 tipi) — uzvedība, kas parādās ZEM SPIEDIENA.
// DIVOS LOMAS KONTEKSTOS: asEmployee (pakļautā/izpildītāja lomā) un asLeader (vadot citus).
// Atvasināti no katra tipa `risks` (leadership_type.js), izvērsti HR-rīcības formātā.
// Eksportēta (2026-06-12): logic/investor_memo.js to izmanto traucējummeklēšanas sintēzei.
export const DERAILERS = {
    charismatic: {
        asEmployee: [
            { name: 'Vajadzība pēc uzmanības', trigger: 'fona vai neredzamas lomas, kur uzslavu maz', shows: 'meklē uzmanību; grūti strādāt klusumā bez atzinības', impact: 'aizēno kolēģus; rodas sāncensība par redzamību', mitMgr: 'dod regulāru atzinību un redzamas lomas', mitSelf: 'mācies gūt gandarījumu no paša darba, ne tikai no uzmanības' },
            { name: 'Saturs pakārtots tēlam', trigger: 'izvēle starp izrādīšanos un neuzkrītošu darbu', shows: 'priekšroka spožiem uzdevumiem; izvairās no "pelēkā" darba', impact: 'rutīnas un atbalsta darbs paliek nepadarīts', mitMgr: 'skaidri novērtē arī neredzamo darbu', mitSelf: 'uzņemies arī neuzkrītošus, bet svarīgus uzdevumus' },
        ],
        asLeader: [
            { name: 'Statusa atkarība', trigger: 'kad personīgā atzinība vai redzamība ir apdraudēta', shows: 'pieņem lēmumus, kas ceļ paša tēlu, ne komandas rezultātu; cīnās par uzmanību', impact: 'komanda jūtas otršķirīga; lēmumi balstās uz ego, ne datiem', mitMgr: 'dod atzinību proaktīvi un skaidri saisti to ar komandas rezultātu', mitSelf: 'apzināti laid citus priekšplānā — deleģē uzmanību, ne tikai uzdevumus' },
            { name: 'Negrib atlaist kontroli', trigger: 'augsta slodze un svarīgi, redzami projekti', shows: 'patur svarīgākos uzdevumus sev, negrib uzticēt citiem', impact: 'kļūst par pudeles kaklu; komanda neaug un nejūtas uzticēta', mitMgr: 'vienojies par konkrētiem deleģēšanas mērķiem', mitSelf: 'deleģē vismaz vienu nozīmīgu uzdevumu pilnībā, bez pārbaudes' },
        ],
    },
    authoritarian: {
        asEmployee: [
            { name: 'Saķeras ar savu priekšnieku', trigger: 'kad viņu kontrolē vai mikrovada', shows: 'apšauba lēmumus, pretojas kontrolei, grib darīt savā veidā', impact: 'berze ar vadību; uztverts kā "sarežģīts"', mitMgr: 'dod autonomiju un skaidru "kāpēc", ne tikai pavēles', mitSelf: 'izvēlies cīņas — ne katrs norādījums jāapšauba' },
            { name: 'Grūti pakļauties', trigger: 'hierarhija, kurā viņš nav galvenais', shows: 'uzņemas vairāk varas, nekā loma paredz', impact: 'konflikti par robežām ar kolēģiem un vadību', mitMgr: 'nosaki skaidras lomas robežas', mitSelf: 'respektē lomas robežas, pirms tās paplašini' },
        ],
        asLeader: [
            { name: 'Pārmērīga kontrole / mikrovadība', trigger: 'termiņi un sajūta, ka zaudē kontroli', shows: 'pārbauda katru detaļu, uzspiež savu veidu, negrib deleģēt', impact: 'izdzen talantus; komanda zaudē iniciatīvu un bailēs neziņo problēmas', mitMgr: 'vienojies par rezultātu rāmjiem, ne procesa kontroli; aizsargā komandas autonomiju', mitSelf: 'definē, KO sasniegt, ne KĀ to darīt — deleģē procesu' },
            { name: 'Bailes, nevis lojalitāte', trigger: 'pretestība vai lēmumu apšaubīšana', shows: 'spiediens, autoritātes demonstrēšana, sods par kļūdām', impact: 'komanda klusē un slēpj kļūdas; lojalitāte ir virspusēja', mitMgr: 'veido psiholoģisko drošību; atalgo godīgu sliktu ziņu nešanu', mitSelf: 'apzināti uzdod jautājumus, nevis dod pavēles' },
        ],
    },
    expert: {
        asEmployee: [
            { name: 'Perfekcionisms kavē tempu', trigger: 'termiņi ar "pietiekami labi" standartu', shows: 'pārstrādā detaļas, nokavē termiņus', impact: 'lēna piegāde; vadītājam jāgaida', mitMgr: 'nosaki skaidrus termiņus un "pietiekami labi" definīciju', mitSelf: 'liec laika limitu un piegādā, kad tas pienāk' },
            { name: 'Pretojas ne-eksperta vadībai', trigger: 'norādes no kāda, ko uzskata par mazāk kompetentu', shows: 'ignorē vai apšauba norādījumus', impact: 'berze ar vadītāju; sadarbības grūtības', mitMgr: 'pamato lēmumus ar loģiku, ne tikai amatu', mitSelf: 'pieļauj, ka cita perspektīva var būt vērtīga' },
        ],
        asLeader: [
            { name: 'Detaļu paralīze', trigger: 'nenoteiktība un personīgi augsti standarti', shows: 'iedziļinās detaļās pāri vajadzīgajam, kavē lēmumus', impact: 'lēni lēmumi; komanda gaida, projekts stostās', mitMgr: 'nosaki "pietiekami labi" robežas un skaidrus termiņus', mitSelf: 'liec laika limitu analīzei un pieņem lēmumu tā beigās' },
            { name: 'Vāja starppersonu ietekme', trigger: 'situācijas, kur cilvēki jāiedvesmo vai jāpārliecina', shows: 'paļaujas tikai uz faktiem, ne uz attiecībām', impact: 'labas idejas paliek nesadzirdētas; grūti gūt atbalstu', mitMgr: 'pāro ar komunikatīvu partneri prezentācijām', mitSelf: 'pirms argumentiem velti laiku attiecībām un kontekstam' },
        ],
    },
    mentor: {
        asEmployee: [
            { name: 'Palīdz citiem sava darba vietā', trigger: 'kolēģu grūtības un lūgumi pēc palīdzības', shows: 'aizraujas, palīdzot citiem; atstāj savu darbu novārtā', impact: 'paša uzdevumi kavējas', mitMgr: 'skaidri nosaki paša prioritātes', mitSelf: 'vispirms savs darbs, tad palīdzība citiem' },
            { name: 'Grūti pateikt "nē" priekšniekam', trigger: 'papildu uzdevumi un augoša slodze', shows: 'uzņemas par daudz, neaizstāv robežas', impact: 'pārslodze un izdegšana', mitMgr: 'pamani pārslodzi; neuzkrauj vairāk, nekā jaudas', mitSelf: 'mācies pateikt "man jau ir pilna slodze"' },
        ],
        asLeader: [
            { name: 'Robežu izplūšana', trigger: 'komandas emocionālās grūtības un konflikti', shows: 'uzņemas citu problēmas, grūti pasaka "nē"', impact: 'pārslodze un izdegšana; atbildība izplūst', mitMgr: 'palīdzi nospraust robežas; neuzliec viņam visas komandas emocionālo slogu', mitSelf: 'mācies pateikt "nē" — neuzņemies citu atbildību kā savu' },
            { name: 'Izmantojamība', trigger: 'cilvēki, kas izmanto labo gribu', shows: 'dod par daudz un neprasa pretī', impact: 'paša vajadzības tiek ignorētas; resursi izsīkst', mitMgr: 'pamani un aizsargā; nepieļauj, ka viņu izmanto', mitSelf: 'apzinies savas vajadzības un aizstāvi tās' },
        ],
    },
    visionary: {
        asEmployee: [
            { name: 'Garlaikojas rutīnā', trigger: 'ikdienas atkārtots darbs', shows: 'zaudē interesi, atstāj detaļas novārtā', impact: 'ikdienas izpilde cieš; rodas kļūdas', mitMgr: 'saisti rutīnu ar lielāku mērķi; dod arī attīstošus uzdevumus', mitSelf: 'atrodi jēgu arī rutīnā un pabeidz iesākto' },
            { name: 'Apsteidz uzdevuma robežas', trigger: 'konkrēts, ierobežots uzdevums', shows: 'paplašina tvērumu, dara vairāk nekā prasīts', impact: 'novirzās no uzdevuma; kavē termiņus', mitMgr: 'nosaki skaidru tvērumu un termiņus', mitSelf: 'vispirms pabeidz prasīto, tad piedāvā idejas' },
        ],
        asLeader: [
            { name: 'Izpildes plaisa', trigger: 'ikdienas rutīna un detaļu darbs', shows: 'aizraujas ar vīziju, atstāj novārtā izpildi un detaļas', impact: 'idejas nerealizējas; projekti paliek nepabeigti', mitMgr: 'pāro ar izpildes partneri; prasi konkrētus nākamos soļus', mitSelf: 'katrai vīzijai pievieno 3 konkrētus, izmērāmus soļus' },
            { name: 'Vīzija apsteidz resursus', trigger: 'ambiciozi mērķi un jaunu iespēju entuziasms', shows: 'sola un plāno vairāk, nekā resursi reāli ļauj', impact: 'komanda pārslogota; mērķi nereāli, uzticība krīt', mitMgr: 'prasi resursu un laika plānu pirms apņemšanās', mitSelf: 'pārbaudi realitāti pirms solīšanas — vai resursi sedz vīziju' },
        ],
    },
    admin: {
        asEmployee: [
            { name: 'Pārmērīga noteikumu ievērošana', trigger: 'situācijas, kur vajag elastību', shows: 'stingri pie procedūrām pat tad, kad nav jēgas', impact: 'lēnums un neelastība; komanda zaudē sparu', mitMgr: 'skaidro, kur drīkst elastību un izņēmumus', mitSelf: 'jautā: vai šis noteikums šeit kalpo mērķim' },
            { name: 'Pretojas pārmaiņām no augšas', trigger: 'jaunas metodes, reorganizācija', shows: 'turas pie esošā, lēni pieņem jauno', impact: 'atpaliek; uztverts kā bremze', mitMgr: 'dod laiku un skaidru pārmaiņu pamatojumu', mitSelf: 'meklē, kas jaunajā darbojas, pirms pretojies' },
        ],
        asLeader: [
            { name: 'Procesa pārmērība', trigger: 'jaunas, nestandarta idejas un eksperimenti', shows: 'pieturas pie noteikumiem, bloķē netradicionālas pieejas', impact: 'inovācija apstājas; radošie talanti zaudē sparu', mitMgr: 'nodali, kur procesi tiešām vajadzīgi un kur ne', mitSelf: 'apzināti atļauj izņēmumus eksperimentiem' },
            { name: 'Birokrātija nomāc komandu', trigger: 'kad jāorganizē komandas ikdienas darbs', shows: 'ievieš pārmērīgi daudz procedūru, atskaišu un apstiprinājumu', impact: 'komanda noslogota ar formalitātēm; lēna un zaudē motivāciju', mitMgr: 'pārskati, kuras procedūras tiešām nepieciešamas, un atmet lieko', mitSelf: 'katrai procedūrai jautā — vai tā paātrina vai bremzē komandu' },
        ],
    },
    specialist: {
        asEmployee: [
            { name: 'Zema pašiniciatīva', trigger: 'nestrukturētas situācijas bez skaidrām norādēm', shows: 'gaida norādījumus, neuzņemas iniciatīvu', impact: 'kavē projektu; vadītājam jādod katrs solis', mitMgr: 'dod skaidrus uzdevumus un pakāpeniski palielini autonomiju', mitSelf: 'pieņem vienu lēmumu pats, pirms prasi norādes' },
            { name: 'Nedrošība jaunā', trigger: 'nepazīstami izaicinājumi', shows: 'paļaujas uz citiem, baidās kļūdīties', impact: 'ierobežota izaugsme un patstāvība', mitMgr: 'nodrošini mentoru un drošu vidi mēģinājumiem', mitSelf: 'izaicini sevi ar mazu patstāvīgu soli' },
        ],
        asLeader: [
            { name: 'Vāja vadības pārliecība', trigger: 'kad jāuzņemas lēmumi par citiem', shows: 'vilcinās, meklē apstiprinājumu, izvairās no atbildības', impact: 'komanda paliek bez skaidra virziena', mitMgr: 'sāc ar nelielu komandu un skaidru atbalstu', mitSelf: 'pieņem lēmumus pats — komandai vajag virzienu, ne nevainojamību' },
            { name: 'Grūti deleģēt un prasīt', trigger: 'kad jāuzdod uzdevumi un jāprasa rezultāts', shows: 'dara pats, nevis uztic; izvairās no neērtām sarunām', impact: 'pārslogojas; komanda neaug', mitMgr: 'māci deleģēšanas un atgriezeniskās saites prasmes', mitSelf: 'deleģē un sniedz skaidru atgriezenisko saiti' },
        ],
    },
    solo: {
        asEmployee: [
            { name: 'Grūti komandas ritmā', trigger: 'kopēji procesi, sapulces, koordinācija', shows: 'dara savu, izlaiž kopējos punktus', impact: 'nesaskaņas un dublēšanās ar komandu', mitMgr: 'dod autonomu lomu ar skaidrām saskarnēm', mitSelf: 'vienojies par minimālajiem kopējiem kontrolpunktiem' },
            { name: 'Apšauba autoritāti', trigger: 'kontrole vai uzraudzība no vadības', shows: 'ignorē hierarhiju; zema tolerance pret kontroli', impact: 'konflikti ar vadību', mitMgr: 'vadi caur jēgu un autonomiju, ne kontroli', mitSelf: 'izvēlies, kad apšaubīt — ne vienmēr' },
        ],
        asLeader: [
            { name: 'Komandu atstāj bez konteksta', trigger: 'kad komandai vajag informāciju un skaidru virzienu', shows: 'maz komunicē; sagaida, ka komanda tiek galā patstāvīgi, kā viņš pats', impact: 'komanda strādā neziņā; zūd saskaņa un virziens', mitMgr: 'prasi regulāru komandas informēšanu un skaidru virzienu', mitSelf: 'dalies kontekstā un gaidās, ne tikai gala uzdevumos' },
            { name: 'Vada caur sevi, ne komandu', trigger: 'kad komandai vajag struktūru un kopēju ritmu', shows: 'paļaujas uz savu izpildi, neveido komandas procesus', impact: 'komanda kļūst atkarīga no viņa un neaug līdzi', mitMgr: 'prasi komandas procesu un pēctecības veidošanu', mitSelf: 'veido sistēmu, kas darbojas arī bez tevis' },
        ],
    },
};

// Ēnas intensitātes konsenss no synergy.risks.details (5 tradīciju saskaņa).
function riskConsensus(details) {
    const sc = (details || []).map(d => Number(d.score)).filter(n => !isNaN(n));
    if (sc.length < 2) return null;
    const mean = sc.reduce((a, b) => a + b, 0) / sc.length;
    const sd = Math.sqrt(sc.reduce((a, b) => a + (b - mean) ** 2, 0) / sc.length);
    if (sd < 1.4) return { label: 'sistēmas vienojas', icon: '✓', col: '#065f46', bg: '#ecfdf5' };
    if (sd < 2.6) return { label: 'daļēja saskaņa', icon: '≈', col: '#92400e', bg: '#fffbeb' };
    return { label: 'sistēmas nesaskan', icon: '⚡', col: '#991b1b', bg: '#fef2f2' };
}

export function renderShadowCard(profile) {
    const inner = safe(() => {
        const lead = profile.leadership?.primary;
        const drObj = DERAILERS[lead?.key] || { asEmployee: [], asLeader: [] };
        const emp = (drObj.asEmployee || []).slice(0, 2);
        const ldr = (drObj.asLeader || []).slice(0, 2);

        // Ēnas intensitāte (synergy.risks.pct — VIRKNE no normalizePct, tāpēc Number()).
        const rawPct = profile.synergy?.risks?.pct;
        const intensity = (rawPct !== undefined && rawPct !== null && rawPct !== '' && !isNaN(Number(rawPct))) ? Math.round(Number(rawPct)) : null;
        const cons = riskConsensus(profile.synergy?.risks?.details);
        const lvl = intensity == null ? null : intensity > 65 ? { t: 'augsta', col: '#ef4444' } : intensity >= 40 ? { t: 'vidēja', col: '#f59e0b' } : { t: 'zema', col: '#10b981' };

        // Ja nav ne derailers, ne intensitātes — nerādīt karti.
        if (!emp.length && !ldr.length && intensity == null) return '';

        const header = intensity == null ? '' : `
        <div style="display:flex; align-items:center; gap:0.8rem; flex-wrap:wrap; margin-bottom:1rem;">
            <div style="flex:1; min-width:200px;">
                <div style="display:flex; justify-content:space-between; font-size:0.74rem; margin-bottom:3px;">
                    <span style="color:#64748b; font-weight:700;">Ēnas intensitāte</span>
                    <span style="color:${lvl.col}; font-weight:800;">${intensity}% · ${lvl.t}</span>
                </div>
                <div style="height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden;"><div style="width:${intensity}%; height:100%; background:${lvl.col};"></div></div>
            </div>
            ${cons ? `<span style="font-size:0.72rem; padding:4px 9px; border-radius:5px; background:${cons.bg}; color:${cons.col}; font-weight:700;">${cons.icon} ${cons.label}</span>` : ''}
        </div>`;

        const derailerBlock = (dr, i) => `
        <div style="border:1px solid #fee2e2; border-radius:10px; padding:0.85rem 1rem; margin-bottom:0.8rem; background:#fffafa;">
            <div style="font-size:0.92rem; font-weight:800; color:#991b1b; margin-bottom:0.55rem;">▸ Klupšanas akmens Nr. ${i + 1}: ${dr.name}</div>
            <div style="display:grid; grid-template-columns:auto 1fr; gap:4px 12px; font-size:0.8rem; line-height:1.5;">
                <span style="color:#94a3b8; font-weight:700; white-space:nowrap;">Izraisītājs</span><span style="color:#475569;">${dr.trigger}</span>
                <span style="color:#94a3b8; font-weight:700; white-space:nowrap;">Kā izpaužas</span><span style="color:#475569;">${dr.shows}</span>
                <span style="color:#94a3b8; font-weight:700; white-space:nowrap;">Ietekme</span><span style="color:#475569;">${dr.impact}</span>
            </div>
            <div style="display:flex; gap:8px; margin-top:0.6rem; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px; background:#eff6ff; border-left:3px solid #3b82f6; border-radius:0 6px 6px 0; padding:6px 10px; font-size:0.78rem; color:#1e40af;"><b>Viņa vadītājam:</b> ${dr.mitMgr}</div>
                <div style="flex:1; min-width:200px; background:#f0fdf4; border-left:3px solid #22c55e; border-radius:0 6px 6px 0; padding:6px 10px; font-size:0.78rem; color:#166534;"><b>Pašai personai:</b> ${dr.mitSelf}</div>
            </div>
        </div>`;

        const intro = `<div style="font-size:0.85rem; color:#475569; line-height:1.6; margin-bottom:0.9rem;">Zem spiediena stiprās puses var pārvērsties par <b style="color:#991b1b;">riskiem</b>. Tie atšķiras pēc lomas — <b style="color:#1e293b;">kā darbinieks</b> (pakļautā lomā) un <b style="color:#1e293b;">kā vadītājs</b> (vadot citus).${lead?.lv ? ` <span style="color:#94a3b8;">(balstīts darba stilā: ${lead.icon || ''} ${lead.lv})</span>` : ''}</div>`;

        const section = (icon, label, list) => list.length ? `
        <div style="flex:1 1 340px; min-width:0;">
            <div style="font-size:0.8rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:1px; margin:0.2rem 0 0.7rem; padding-bottom:5px; border-bottom:2px solid #e2e8f0;">${icon} ${label}</div>
            ${list.map(derailerBlock).join('')}
        </div>` : '';

        const sectionsRow = `<div style="display:flex; gap:1.2rem; flex-wrap:wrap; align-items:flex-start;">${section('👤', 'Kā darbinieks', emp)}${section('👔', 'Kā vadītājs', ldr)}</div>`;
        return intro + header + sectionsRow;
    });

    return card('🌑', 'Klupšanas akmeņi', 'Stresa riski · kā darbinieks un kā vadītājs', inner);
}

// ── Galvenā eksporta funkcija ─────────────────────────────────────────────────
export function renderTabYInsights(profile) {
    const hel  = profile.hellenistic || {};
    const ved  = profile.vedic || {};
    const wes  = profile.western || {};
    const baz  = profile.bazi || {};
    const syn  = profile.synergy || {};
    const yf   = (profile.vedic && profile.vedic.yearly_forecast) || {};

    // ── 1. HELLĒNISMA SLĀNIS ─────────────────────────────────────────────────
    const lotsHtml = safe(() => {
        const fS = hel.lot_of_fortune_sign, sS = hel.lot_of_spirit_sign;
        if (!fS && !sS) return '';
        const row = (emoji, name, deg, sign, text, color) => `
        <div style="border-left:3px solid ${color}; padding:0.5rem 0.9rem; margin-bottom:0.7rem; background:${color}0d; border-radius:0 8px 8px 0;">
            <div style="display:flex; align-items:baseline; gap:0.5rem; flex-wrap:wrap;">
                <b style="color:#1e293b;">${emoji} ${name}</b>
                <span style="font-size:0.78rem; color:${color}; font-weight:700;">${sign || ''}</span>
                <span style="font-size:0.72rem; color:#94a3b8;">${deg || ''}</span>
            </div>
            <div style="font-size:0.83rem; color:#475569; line-height:1.55; margin-top:3px;">${text}</div>
        </div>`;
        return (fS ? row('🍀', 'Fortūnas daļa', hel.lot_of_fortune, fS, interpretLot(fS, 'fortune'), '#10b981') : '')
             + (sS ? row('✦', 'Gara daļa', hel.lot_of_spirit, sS, interpretLot(sS, 'spirit'), '#8b5cf6') : '');
    });

    const profectionHtml = safe(() => {
        const p = interpretProfection(profile);
        if (!p) return '';
        const supColor = p.support === 'atbalstīts' ? '#10b981' : p.support === 'izaicinošs' ? '#ef4444' : '#64748b';
        return `
        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:0.8rem;">
            <div style="background:#1e293b; color:#fff; border-radius:10px; padding:0.7rem 1.1rem; text-align:center; min-width:96px;">
                <div style="font-size:0.58rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">Profekcijas gads</div>
                <div style="font-size:1.1rem; font-weight:800;">${p.house}. māja</div>
                <div style="font-size:0.7rem; color:#cbd5e1;">${p.sign}</div>
            </div>
            <div style="flex:1; min-width:220px; font-size:0.85rem; color:#475569; line-height:1.6;">
                Šī gada galvenā tēma ir <b style="color:#1e293b;">${p.houseTheme}</b>.
            </div>
        </div>
        <div style="font-size:0.83rem; color:#475569; line-height:1.6; margin-bottom:0.7rem;">
            Gada valdnieks ir <b style="color:#1e293b;">${p.lord}</b>${p.lordSign ? `, kas tavā dzimšanas kartē atrodas <b>${p.lordSign}</b>${p.lordHouse ? ` (${p.lordHouse}. mājā)` : ''}` : ''}.${p.lordHouseTheme ? ` Tāpēc darbība šogad notiek caur <b>${p.lordHouseTheme}</b> — tur jāmeklē gada notikumi un iespējas.` : ''}
        </div>
        <div style="font-size:0.82rem; line-height:1.6; padding:0.55rem 0.85rem; background:${supColor}14; border-left:3px solid ${supColor}; border-radius:0 8px 8px 0; color:#475569;">
            ${p.supportText}
        </div>`;
    });

    const triplicityHtml = safe(() => {
        const t = hel.triplicity;
        if (!Array.isArray(t) || t.length < 3 || t[0] === '?') return '';
        const labels = ['Jaunība / sākums', 'Brieduma gadi', 'Vēlīnais posms'];
        return `<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.7rem;">
            ${t.slice(0,3).map((lord, i) => `
            <div style="text-align:center; background:#f8fafc; border-radius:10px; padding:0.7rem;">
                <div style="font-size:0.62rem; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px;">${labels[i]}</div>
                <div style="font-size:0.92rem; font-weight:700; color:#1e293b;">${lord}</div>
            </div>`).join('')}
        </div>
        <div style="font-size:0.78rem; color:#94a3b8; margin-top:0.6rem; line-height:1.5;">Veiksmes daļas valdnieki pa dzīves posmiem — kura planēta nes atbalstu kurā dzīves trešdaļā.</div>`;
    });

    // ── 2. VĒDU SLĒPTIE DATI ─────────────────────────────────────────────────
    // (Arudha Lagna karte PĀRCELTA uz cilni 'Psiholoģija' — sk. renderArudhaCard)

    const strengthHtml = safe(() => {
        const ps = computePlanetStrength(profile);
        if (!ps.length) return '';
        return `${ps.map(p => {
            const col = p.score >= 70 ? '#10b981' : p.score >= 45 ? '#f59e0b' : '#ef4444';
            return `
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:0.5rem;">
                <span style="font-size:0.82rem; color:#334155; width:80px; flex-shrink:0;">${p.planet}</span>
                <div style="flex:1; background:#e2e8f0; border-radius:4px; height:8px; overflow:hidden;">
                    <div style="width:${p.score}%; height:100%; background:${col}; border-radius:4px;"></div>
                </div>
                <span style="font-size:0.78rem; font-weight:700; color:${col}; width:38px; text-align:right;">${p.score}%</span>
                <span style="font-size:0.68rem; color:#94a3b8; width:140px; flex-shrink:0;">${p.sign} · ${p.dignityLabel}${p.dig ? ' · virziens' : ''}</span>
            </div>`;
        }).join('')}
        <div style="font-size:0.72rem; color:#94a3b8; margin-top:0.6rem; line-height:1.5;">Vienkāršots indekss: dabiskais spēks (Naisargika) + dignitāte (eksaltācija/mājvieta/kritums) + virziena spēks (Dig bala). <b>Nav</b> pilns klasiskais Shadbala.</div>`;
    });

    const yogasHtml = safe(() => {
        const y = ved.yogas || [];
        if (!y.length) return '';
        return y.map(yo => `
        <div style="border-left:3px solid #f59e0b; padding:0.45rem 0.9rem; margin-bottom:0.6rem; background:#fffbeb; border-radius:0 8px 8px 0;">
            <div style="display:flex; align-items:baseline; gap:0.5rem; flex-wrap:wrap;">
                <b style="color:#92400e;">${yo.name}</b>
                <span style="font-size:0.7rem; color:#b45309; background:#fef3c7; padding:1px 7px; border-radius:8px;">${yo.type || ''}</span>
            </div>
            <div style="font-size:0.82rem; color:#475569; line-height:1.5; margin-top:3px;">${yo.desc || ''}</div>
        </div>`).join('');
    });

    const ashtakaHtml = safe(() => {
        const av = ved.ashtakavarga;
        if (!Array.isArray(av) || av.length !== 12) return '';
        const SIGNS = ["Auns","Vērsis","Dvīņi","Vēzis","Lauva","Jaunava","Svari","Skorpions","Strēlnieks","Mežāzis","Ūdensvīrs","Zivis"];
        const max = Math.max(...av, 1);
        return `<div style="display:grid; grid-template-columns:repeat(2,1fr); gap:0.3rem 1.2rem;">
            ${av.map((b, i) => {
                const col = b >= 30 ? '#10b981' : b >= 25 ? '#f59e0b' : '#ef4444';
                return `<div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:0.74rem; color:#475569; width:78px; flex-shrink:0;">${SIGNS[i]}</span>
                    <div style="flex:1; background:#e2e8f0; border-radius:3px; height:7px; overflow:hidden;">
                        <div style="width:${Math.round(b/max*100)}%; height:100%; background:${col};"></div>
                    </div>
                    <span style="font-size:0.74rem; font-weight:700; color:${col}; width:24px; text-align:right;">${b}</span>
                </div>`;
            }).join('')}
        </div>
        <div style="font-size:0.72rem; color:#94a3b8; margin-top:0.6rem; line-height:1.5;">Bindu (punktu) skaits katrā zīmē — cik “uzlādēta” ir katra dzīves joma. Vairāk par 30 = stipra; mazāk par 25 = vāja.</div>`;
    });

    const navamshaHtml = safe(() => {
        const n = computeNavamsha(profile);
        if (!n) return '';
        let html = '';

        // Karakamsa — dvēseles ceļš (hero)
        if (n.karakamsa) {
            html += `<div style="background:linear-gradient(135deg,#0f766e,#134e4a); color:#fff; border-radius:10px; padding:0.8rem 1.1rem; margin-bottom:0.8rem;">
                <div style="font-size:0.62rem; color:#99f6e4; text-transform:uppercase; letter-spacing:1px; margin-bottom:3px;">Dvēseles ceļš · Karakamsa (${n.karakamsa.planet} → ${n.karakamsa.sign})</div>
                <div style="font-size:0.85rem; line-height:1.55;">Tava dvēsele dziļākajā līmenī virzās caur ${n.karakamsa.quality}.</div>
            </div>`;
        }

        // Vargottama — stiprākais kodols
        html += `<div style="margin-bottom:0.8rem; padding:0.6rem 0.85rem; border-left:3px solid ${n.vargottama.length ? '#10b981' : '#cbd5e1'}; background:${n.vargottama.length ? '#f0fdf4' : '#f8fafc'}; border-radius:0 8px 8px 0;">
            <b style="color:#1e293b; font-size:0.84rem;">💪 Stiprākais kodols (Vargottama):</b>${n.vargottama.length
                ? `<span style="color:#15803d; font-weight:700;"> ${n.vargottama.join(' · ')}</span>
                   <div style="font-size:0.78rem; color:#475569; margin-top:3px; line-height:1.5;">Šīs planētas ir tajā pašā zīmē gan dzimšanas kartē, gan D9 — tas nozīmē izcili stabilu, noturīgu spēku, kas neizjūk zem spiediena.</div>`
                : `<span style="color:#94a3b8;"> nav</span>
                   <div style="font-size:0.78rem; color:#94a3b8; margin-top:3px; line-height:1.5;">Nevienai planētai D1 un D9 zīme nesakrīt — nav īpaši pastiprināta “dubultā” kodola.</div>`}
        </div>`;

        // D9 uzsvars (stellijs)
        if (n.emphasis) {
            html += `<div style="margin-bottom:0.8rem; font-size:0.83rem; color:#475569; line-height:1.55;">
                🎯 <b>D9 uzsvars:</b> ${n.emphasis.count} planētas (${n.emphasis.planets.join(', ')}) ir ${n.emphasis.sign} navamšā — iekšēji cilvēks dziļi orientēts uz ${n.emphasis.quality}.
            </div>`;
        }

        // Partnerība — Mēness + Venera D9
        const partner = [];
        if (n.moon)  partner.push(`Mēness ${n.moon.d9}`);
        if (n.venus) partner.push(`Venera ${n.venus.d9}`);
        if (partner.length) {
            const seek = n.venus ? n.signQuality[n.venus.d9] : (n.moon ? n.signQuality[n.moon.d9] : '');
            html += `<div style="margin-bottom:0.8rem; font-size:0.83rem; color:#475569; line-height:1.55;">
                💞 <b>Partnerības dziļums:</b> ${partner.join(' · ')} — attiecībās tu dziļumā meklē ${seek}.
            </div>`;
        }

        // Pilnā D1→D9 tabula (tikai klasiskās 9)
        html += `<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.4rem; margin-top:0.4rem;">
            ${n.planets.map(p => `
            <div style="background:${p.vargottama ? '#dcfce7' : '#f8fafc'}; border:1px solid ${p.vargottama ? '#10b981' : '#e2e8f0'}; border-radius:8px; padding:0.4rem 0.6rem;">
                <div><span style="font-size:0.76rem; color:#475569;">${p.planet}${p.vargottama ? ' ⭐' : ''}</span>
                <span style="font-size:0.78rem; font-weight:700; color:#1e293b; float:right;">${p.d9}</span></div>
                <div style="font-size:0.62rem; color:#94a3b8;">D1 ${p.d1} → D9 ${p.d9}</div>
            </div>`).join('')}
        </div>
        <div style="font-size:0.7rem; color:#94a3b8; margin-top:0.5rem; line-height:1.5;">Tikai klasiskās 9 planētas — Rietumu Urāns/Neptūns/Plutons/MC izlaisti, jo Vēdu D9 tos nelieto. ⭐ = Vargottama.</div>`;

        return html;
    });

    const superEventsHtml = safe(() => {
        const se = ved.super_events || [];
        if (!se.length) return '';
        const age = profile.progressions?.age;
        return se.map(e => {
            let status = '', sColor = '#64748b';
            if (age != null && e.startAge != null && e.endAge != null) {
                if (age >= e.startAge && age <= e.endAge) { status = 'Aktīvs tagad'; sColor = '#ef4444'; }
                else if (age > e.endAge) { status = 'Bijis'; sColor = '#94a3b8'; }
                else { status = 'Gaidāms'; sColor = '#10b981'; }
            }
            return `
            <div style="display:flex; gap:0.8rem; align-items:flex-start; margin-bottom:0.7rem; ${status === 'Aktīvs tagad' ? 'background:#fef2f2; border-radius:10px; padding:0.6rem;' : ''}">
                <div style="background:#1e293b; color:#fff; border-radius:8px; padding:0.3rem 0.6rem; font-size:0.72rem; font-weight:700; white-space:nowrap; flex-shrink:0;">
                    ${e.startAge != null ? `${Math.round(e.startAge)}${e.endAge != null ? '–'+Math.round(e.endAge) : ''} g.` : ''}
                </div>
                <div style="flex:1;">
                    <div style="display:flex; align-items:baseline; gap:0.5rem; flex-wrap:wrap;">
                        <b style="color:#1e293b; font-size:0.85rem;">${e.name || ''}</b>
                        ${status ? `<span style="font-size:0.62rem; font-weight:700; color:#fff; background:${sColor}; padding:1px 8px; border-radius:8px;">${status}</span>` : ''}
                    </div>
                    <div style="font-size:0.8rem; color:#475569; line-height:1.5;">${e.desc || ''}</div>
                </div>
            </div>`;
        }).join('');
    });

    // ── 3. RIETUMU RAKSTI ────────────────────────────────────────────────────
    const patternsHtml = safe(() => {
        const ps = computeAspectPatterns(profile);
        if (!ps.length) return '<div style="font-size:0.83rem; color:#94a3b8;">Šajā kartē nav izteiktu klasisko aspektu rakstu (T-kvadrāts, Lielais trijstūris, Jods).</div>';
        return ps.map(p => `
        <div style="border-left:3px solid ${p.color}; padding:0.5rem 0.9rem; margin-bottom:0.6rem; background:${p.color}0d; border-radius:0 8px 8px 0;">
            <div style="display:flex; align-items:baseline; gap:0.5rem; flex-wrap:wrap;">
                <b style="color:#1e293b;">${p.icon} ${p.type}</b>
                <span style="font-size:0.72rem; color:${p.color}; font-weight:700;">${p.planets.join(' · ')}</span>
            </div>
            <div style="font-size:0.82rem; color:#475569; line-height:1.5; margin-top:3px;">${p.meaning}</div>
        </div>`).join('');
    });

    // (Viduspunkti + Ēnas izaicinājums (Maiju dēmons) kartes PĀRCELTAS uz cilni 'Psiholoģija'
    //  — sk. renderMidpointsCard / renderShadowCard.)

    const taroHtml = safe(() => {
        if (!syn.taro || syn.taro === 'Nezināms') return '';
        return `<div style="font-size:1rem; color:#1e293b; font-weight:700;">${syn.taro}</div>
        <div style="font-size:0.78rem; color:#94a3b8; margin-top:4px; line-height:1.5;">Simboliskā dzīves tēma, kas izriet no Maiju ietekmes atslēgas.</div>`;
    });

    // ── 5. BAZI DINAMIKA ─────────────────────────────────────────────────────
    const baziHtml = safe(() => {
        const conf = baz.conflicts || [];
        const comb = baz.combinations || [];
        if (!conf.length && !comb.length) return '';
        const pair = (p) => Array.isArray(p) ? p.join(' ↔ ') : p;
        let html = '';
        if (conf.length) html += `<div style="margin-bottom:0.7rem;">
            <div style="font-size:0.7rem; font-weight:700; color:#dc2626; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">⚔ Sadursmes</div>
            ${conf.map(c => `<div style="font-size:0.82rem; color:#475569; padding:2px 0;"><b>${pair(c.pair)}</b> — ${c.type}</div>`).join('')}
        </div>`;
        if (comb.length) html += `<div>
            <div style="font-size:0.7rem; font-weight:700; color:#16a34a; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">🤝 Harmonijas</div>
            ${comb.map(c => `<div style="font-size:0.82rem; color:#475569; padding:2px 0;"><b>${pair(c.pair)}</b> — ${c.type}</div>`).join('')}
        </div>`;
        return html + `<div style="font-size:0.72rem; color:#94a3b8; margin-top:0.6rem; line-height:1.5;">BaZi zaru iekšējās sadursmes (spriedze) un harmonijas (dabiskā plūsma) starp dzīves pīlāriem.</div>`;
    });

    // ── 6. GADA LAIKS ────────────────────────────────────────────────────────
    const yearlyHtml = safe(() => {
        const m = yf.muntha;
        const ecl = yf.eclipses || [];
        const retro = yf.retrogrades || [];
        let html = '';
        if (m && (m.signName || m.focusTheme)) {
            html += `<div style="margin-bottom:0.7rem;">
                <b style="color:#1e293b; font-size:0.85rem;">🎯 Muntha${m.signName ? ' · ' + m.signName : ''}${m.houseNo ? ' (' + m.houseNo + '. māja)' : ''}</b>
                ${m.focusTheme ? `<div style="font-size:0.82rem; color:#475569; line-height:1.5;">${m.focusTheme}</div>` : ''}
            </div>`;
        }
        if (retro.length) {
            html += `<div style="margin-bottom:0.6rem;"><div style="font-size:0.7rem; font-weight:700; color:#f97316; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">↺ Retrogrādi (tuvākie 6 mēn.)</div>
            ${retro.map(r => `<div style="font-size:0.8rem; color:#475569;">${r.planet}: ${fmtDate(r.start)} – ${fmtDate(r.end)}</div>`).join('')}</div>`;
        }
        if (ecl.length) {
            html += `<div><div style="font-size:0.7rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">🌑 Aptumsumi</div>
            ${ecl.map(e => `<div style="font-size:0.8rem; color:#475569;">${e.time ? fmtDate(e.time) : ''} — ${e.type || ''}</div>`).join('')}</div>`;
        }
        return html;
    });

    // ── SALIKŠANA ────────────────────────────────────────────────────────────
    return `
    <div class="q-tab" style="max-width:1100px; margin:0 auto;">

        <!-- Hero -->
        <div style="background:linear-gradient(135deg,#0f172a,#312e81); border-radius:16px; padding:1.8rem 2.2rem; margin-bottom:1.4rem; position:relative; overflow:hidden;">
            <div style="position:absolute; top:-20px; right:0; font-size:5rem; opacity:0.08;">🔍</div>
            <div style="font-size:0.68rem; font-weight:800; color:#a5b4fc; text-transform:uppercase; letter-spacing:3px; margin-bottom:0.4rem;">Cilne y</div>
            <h2 style="margin:0 0 0.3rem 0; font-size:1.5rem; font-weight:900; color:#fff;">Slēptie ieskati</h2>
            <p style="margin:0; color:#c7d2fe; font-size:0.88rem; line-height:1.6; max-width:720px;">
                Viss, ko karte aprēķina, bet līdz šim nerādīja — Hellēnisma daļas, profekcijas, Arudha (publiskais tēls), aspektu raksti, planētu spēks, Vēdu yogas, Maiju ēna un BaZi dinamika.
            </p>
        </div>

        ${groupHeading('🏆 Spēcīgākie ieskati — augsta nozīme un precizitāte')}
        ${card('🌀', 'Lielie dzīves notikumi', 'Sade Sati · Jupitera atgriešanās', superEventsHtml, {
            does: 'Aprēķina Saturna (Sade Sati) un Jupitera atgriešanās ciklus pa vecumiem un norāda, kurš posms ir aktīvs tagad.',
            max: 'Var norādīt vecuma logus, kad gaidāmas lielas dzīves revīzijas vai iespēju restarti. Nevar pateikt notikuma saturu vai vai tas būs “labs/slikts” — tikai cikla laiku un kvalitāti.'
        })}
        ${card('🔺', 'Aspektu raksti', 'T-kvadrāts · trijstūris · Jods', patternsHtml, {
            does: 'Meklē ģeometriskas planētu figūras kartē un nosaka to fokusa planētu — kur enerģija jāizmanto.',
            max: 'Var atklāt cilvēka pamata iekšējo dzinēju vai dāvanu — kur ir vislielākā spriedze (motivācija) vai dabiskā plūsma (talants). Nevar pateikt, kā cilvēks to izmantos — tikai struktūras klātbūtni.'
        })}
        ${card('🪷', 'Vēdu Yogas', 'iedzimtas talantu kombinācijas', yogasHtml, {
            does: 'Atpazīst klasiskas planētu kombinācijas, kas saistītas ar konkrētiem dzīves rezultātiem (bagātība, slava, gudrība).',
            max: 'Var norādīt iedzimtas predispozīcijas uz konkrētu veiksmes vai talanta veidu. Nevar garantēt, ka joga “nostrādās” — tas atkarīgs no planētu spēka, dzīves apstākļiem un izvēlēm.'
        })}
        ${card('💎', 'Navamša (D9)', 'Vargottama · Karakamsa · partnerība', navamshaHtml, {
            does: 'Sadala katru zīmi 9 daļās (otra karte) un nosaka Vargottama planētas (D1=D9 → stiprākais kodols), Karakamsu (dvēseles ceļu) un partnerības dziļumu.',
            max: 'Var atklāt planētas “patieso” iekšējo spēku, dvēseles virzienu un attiecību tēmas. Nevar pateikt par konkrētu partneri vai laulību — tikai iekšējo kvalitāti.'
        })}
        ${card('📅', 'Gada profekcija', 'šī gada māja, valdnieks un atbalsts', profectionHtml, {
            does: 'Katru dzīves gadu pārceļ uzmanību uz nākamo māju no Ascendenta (profektētā māja = gada tēma); valdnieka dzimšanas pozīcija rāda, kur notiek darbība, un dignitāte — vai gads ir atbalstīts.',
            max: 'Var norādīt, kura dzīves joma šogad aktivizējas, kur kartē meklēt notikumus un vai gads ir labvēlīgs vai izaicinošs. Nevar prognozēt konkrētus datumus vai garantēt notikumus.'
        })}

        ${groupHeading('🟡 Vidēji spēcīgi — jēgpilni, bet abstraktāki')}
        ${card('📊', 'Aštakavarga', 'dzīves jomu stipruma karte', ashtakaHtml, {
            does: 'Piešķir punktu (bindu) skaitu katrai no 12 zīmēm, summējot planētu savstarpējo atbalstu.',
            max: 'Var sakārtot dzīves jomas pēc dabiskā “lādiņa” — kuras ir spēcīgas un kuras vājas. Nevar pateikt notikumu iznākumu — tikai relatīvo jomu stiprumu.'
        })}
        ${card('☯', 'BaZi sadursmes un harmonijas', 'pīlāru iekšējā spriedze un plūsma', baziHtml, {
            does: 'Analizē attiecības starp 4 dzīves pīlāru zariem — sadursmes (spriedze) un harmonijas (plūsma).',
            max: 'Var atklāt iekšējās enerģiju sadursmes un dabiskās harmonijas starp dzīves jomām. Nevar pateikt konkrētus notikumus — tikai strukturālo dinamiku.'
        })}
        ${card('🍀', 'Fortūnas un Gara daļas', 'ķermenis/veiksme un griba/karjera', lotsHtml, {
            does: 'Aprēķina divus matemātiskus punktus no Saules, Mēness un Ascendenta attiecības — Fortūnu (ķermenis, veselība, materiālā plūsma) un Garu (apzinātā griba, karjera, prāts).',
            max: 'Var norādīt, kur dzīvē enerģija un veiksme dabiski koncentrējas un kā cilvēks neapzināti (Fortūna) pret apzināti (Gars) virza savu dzīvi. Nevar pateikt konkrētus notikumus vai naudas summas — tikai jomas un stilu.'
        })}

        ${groupHeading('🔧 Tehniskie un simboliskie — papildu konteksts')}
        ${card('⚖️', 'Planētu spēka indekss', 'vienkāršots Shadbala', strengthHtml, {
            does: 'Apvieno katras planētas dabisko spēku, dignitāti (zīmes kvalitāti) un virziena spēku vienā skaitlī.',
            max: 'Var parādīt, kuras psihes funkcijas (Saule = ego, Mēness = emocijas, Marss = griba u.c.) darbojas ar vislielāko dabisko jaudu un kuras prasa apzinātu piepūli. Nevar mērīt realizētu prasmi — tikai iedzimtā potenciāla intensitāti.'
        })}
        ${card('🔱', 'Triplicitātes valdnieki', 'veiksme pa dzīves posmiem', triplicityHtml, {
            does: 'Sadala dzīvi trīs posmos (jaunība / briedums / vēlīnais) un katram piešķir atbalsta planētu pēc Veiksmes daļas elementa.',
            max: 'Var ieskicēt, kurā dzīves trešdaļā cilvēkam ir visvairāk dabiskā atbalsta un kāda veida. Nevar pateikt precīzu vecumu vai notikumus — tikai posma vispārējo kvalitāti.'
        })}
        ${card('🃏', 'Taro dzīves tēma', 'simboliska', taroHtml, {
            does: 'No Maiju ietekmes atslēgas atvasina simbolisku Taro arhetipa tēmu.',
            max: 'Var dot simbolisku “virsrakstu” pārdomām. Nevar prognozēt — tas ir refleksijas, ne paredzēšanas rīks.'
        })}
        ${card('🗓️', 'Šī gada logi', 'Muntha · retrogrādi · aptumsumi', yearlyHtml, {
            does: 'Aprēķina gada uzmanības punktu (Muntha), tuvākos retrogrādus un aptumsumus.',
            max: 'Var norādīt īstermiņa laika logus, kad noteiktas tēmas pastiprinās. Aptumsumi un retrogrādi ir vienādi visiem tajā periodā — maz personiski.'
        })}

    </div>`;
}
