// Motivācija un vadības sviras — DIVI REŽĪMI:
//   'self' (Pašizpēte) — terapeitisks 2. personas tonis, B2C prasība (NE manipulatīvs).
//   'pro'  (HR & Profesionāļiem) — lietišķs 3. personas tonis sadarbības ietvarā (toņa pārbūve 2026-06-11:
//          bez 'uzlauzšanas'/manipulācijas leksikas; tas pats cieņas lakmuss kā Komunikācijas ceļvedim).
// Slēdzis: inline onclick caur window.__hkSetMode (strādā vienmēr, arī kad t3 saturs nonāk DOM tikai pēc cilnes klikšķa).

if (typeof window !== 'undefined' && !window.__hkSetMode) {
    window.__hkSetMode = function (btn, mode) {
        const panel = btn.closest('.hk-panel');
        if (!panel) return;
        panel.querySelectorAll('[data-hk-mode]').forEach(el => {
            el.style.display = el.getAttribute('data-hk-mode') === mode ? '' : 'none';
        });
        panel.querySelectorAll('.hk-mode-btn').forEach(b => {
            const on = b.getAttribute('data-mode-btn') === mode;
            b.style.background = on ? '#a21caf' : '#fff';
            b.style.color = on ? '#fff' : '#86198f';
            b.style.boxShadow = on ? '0 2px 6px rgba(162,28,175,0.3)' : 'none';
        });
    };
}

// ── DATU TABULAS ─────────────────────────────────────────────────────────────
// Eksportētas (2026-06-12): logic/investor_memo.js tās izmanto memoranda sintēzei.
// D ass — Dzinulis / Motivācija (10 līmeņi)
export const D_BANDS = [
    { max: 10, pro: { t: "Dziļais Miers", x: "Dzinulis: Karjeras ambīcijas ir minimālas — darbs ir līdzeklis mierīgai dzīvei, ne pašmērķis.", tr: "Absolūts miers un minimāls atbildības slogs.", at: "Strauja atbildības palielināšana mudinās viņu aiziet." },
              self: { t: "Miera meklētājs", x: "Darbs tev nav dzīves centrs — tas ir līdzeklis, lai dzīvotu mierīgi. Tava patiesā ambīcija mīt citur, ne karjerā.", tr: "Pilnīgs miers un brīvība no smaga atbildības sloga.", at: "Uzspiesta atbildība un ambīciju spiediens liek tev gribēt aiziet." } },
    { max: 20, pro: { t: "Fona Spēlētājs", x: "Dzinulis: Viņu var noturēt tikai ar drošu, prognozējamu rutīnu un harmonisku vidi bez stresa lēcieniem.", tr: "Garantēta alga un skaidrs darba laiks (17:00 durvis ciet).", at: "Neplānoti virsstundu uzdevumi vai uzspiesti līderības kursi viņu tūlītēji demotivēs." },
              self: { t: "Stabilitātes cienītājs", x: "Tu uzplaukti drošā, paredzamā vidē bez negaidītiem satricinājumiem. Mierīga rutīna tev dod spēku.", tr: "Garantēta alga un skaidrs darba laiks — 17:00 durvis ciet.", at: "Negaidītas virsstundas vai uzspiesti līderības pienākumi tev atņem sparu." } },
    { max: 30, pro: { t: "Komforta Mīļotājs", x: "Dzinulis: Fizisks un emocionāls komforts. Darba videi jābūt ērtai.", tr: "Labiekārtots ofiss, brīvs grafiks, bez stresa sapulcēm.", at: "Birokrātija un ārkārtas situācijas darba beigās." },
              self: { t: "Komforta cienītājs", x: "Tev svarīga ērta, patīkama darba vide — gan fiziski, gan emocionāli. Komforts tev nav greznība, tā ir pamatvajadzība.", tr: "Labiekārtota darba vieta, elastīgs grafiks, bez stresa sapulcēm.", at: "Birokrātija un ārkārtas situācijas darba dienas beigās." } },
    { max: 40, pro: { t: "Attiecību Cilvēks", x: "Dzinulis: Strādā 'cilvēku', nevis ciparu dēļ. Būtiski, lai kolēģi būtu kā ģimene un priekšnieks rādītu empātiju.", tr: "Neformālas sarunas, atzinība aci pret aci, kopīgi komandas pasākumi.", at: "Sausa korporatīvā politika, agresīvi KPI un iekšējā konkurence viņam ātri atņem sparu." },
              self: { t: "Attiecību cilvēks", x: "Tevi darbā nes attiecības, nevis skaitļi. Tu uzplaukti, kad kolēģi jūtas kā tuvi cilvēki un vadītājs izrāda patiesu sirsnību.", tr: "Neformālas sarunas, atzinība aci pret aci, kopā pavadīts laiks ar komandu.", at: "Auksta korporatīvā vide, agresīvi mērķi un iekšēja konkurence — tādā gaisotnē tu zaudē sparu." } },
    { max: 50, pro: { t: "Stabilais Amatnieks", x: "Dzinulis: Labi padarīta darba sajūta. Vēlas būt profesionālis savā šaurajā nišā.", tr: "Atzinība par tehniskajām prasmēm un kvalitāti.", at: "Uzdevumi, kas nav saistīti ar viņa tiešajiem pienākumiem." },
              self: { t: "Meistars savā jomā", x: "Tev svarīga labi padarīta darba sajūta. Tu vēlies būt īsts profesionālis savā nišā un to lepni apzināties.", tr: "Atzinība par tavām tehniskajām prasmēm un darba kvalitāti.", at: "Uzdevumi, kas nav saistīti ar tavu tiešo jomu un atrauj no meistarības." } },
    { max: 60, pro: { t: "Transakcionālais Pragmatiķis", x: "Dzinulis: Strādā pēc stingras formulas 'Mans ieguldījums = mans ieguvums'. Skaidra loģika naudas izteiksmē.", tr: "Caurspīdīga bonusu sistēma. Pabeidzot X, tu saņem Y. Bez liekiem solījumiem.", at: "Mēģinājumi motivēt ar 'uzņēmuma lielo vīziju' vai nesamaksātiem papildu pienākumiem izsauks cinismu." },
              self: { t: "Skaidrības cienītājs", x: "Tu strādā pēc godīgas formulas: mans ieguldījums = mans ieguvums. Tev patīk skaidra, loģiska saikne starp darbu un atlīdzību.", tr: "Caurspīdīga bonusu sistēma: paveic X — saņem Y, bez liekiem solījumiem.", at: "Motivēšana ar 'lielo vīziju' vai nesamaksāti papildu pienākumi tevī rada vilšanos." } },
    { max: 70, pro: { t: "Karjeras Būvētājs", x: "Dzinulis: Sistemātiska augšupeja. Viņš plāno nākamos soļus karjerā un vēlas redzēt skaidru kāpņu telpu.", tr: "Individuāls izaugsmes plāns un solījums paaugstināt amatā.", at: "Izaugsmes griesti un attīstības iespēju trūkums." },
              self: { t: "Karjeras būvētājs", x: "Tu domā soli pa solim uz augšu. Tev svarīgi redzēt skaidru izaugsmes ceļu un saprast, kur tu būsi rīt.", tr: "Individuāls izaugsmes plāns un reāla amata paaugstinājuma iespēja.", at: "Izaugsmes griesti un sajūta, ka attīstīties nav kur." } },
    { max: 80, pro: { t: "Statusa Spēlētājs", x: "Dzinulis: Vēlas justies pārāks par citiem. Vizuālie statusa apliecinājumi (tituls, kabinets) ir svarīgāki par ienākumiem.", tr: "Ekskluzīvs amata nosaukums un iespēja prezentēt rezultātus uzņēmuma mērogā.", at: "Mikro-menedžments vai viņa ignorēšana kopējās sapulcēs ir tiešs ceļš uz pamešanu." },
              self: { t: "Atzinības cienītājs", x: "Tev svarīgi justies novērtētam un redzamam. Tituls, loma un atpazīstamība tev nozīmē daudz — reizēm vairāk par pašu algu.", tr: "Skanīgs amata nosaukums un iespēja prezentēt savus rezultātus plašākam lokam.", at: "Mikro-vadība vai sajūta, ka tevi neievēro kopējās sapulcēs." } },
    { max: 90, pro: { t: "Lēmējvaras Cilvēks", x: "Dzinulis: Atbildība par cilvēkiem un budžetiem. Viņš vēlas būt tas, kurš pieņem galīgos lēmumus.", tr: "Resursu un pakļauto darbinieku piešķiršana.", at: "Viņa lēmumu publiska apšaubīšana vai varas atņemšana." },
              self: { t: "Vadītāja daba", x: "Tev dabiski gribas ietekmēt lēmumus un vadīt resursus. Tu jūties savā vietā, kad vari noteikt virzienu, nevis tikai izpildīt.", tr: "Atbildība par resursiem un komandu, reāla lemšanas vara.", at: "Tavu lēmumu publiska apšaubīšana vai atbildības atņemšana." } },
    { max: 101, pro: { t: "Radikālais Inovators", x: "Dzinulis: Dziļa aizrautība ar misiju un vēlme pārveidot esošo sistēmu. Nauda viņam šķiet garlaicīgs blakusprodukts.", tr: "Pilnīga autonomija un vissarežģītākie uzdevumi. Ļaujiet viņam 'glābt pasauli'.", at: "Garlaicīgi rutīnas darbi, birokrātiskas atskaites un vadības bailes no riska." },
               self: { t: "Vizionārs", x: "Tevi dzen jēga un vēlme mainīt esošo. Nauda tev šķiet otršķirīga — tev svarīga misija un iespēja radīt ko jaunu.", tr: "Pilnīga autonomija un patiesi sarežģīti, jēgpilni izaicinājumi.", at: "Garlaicīga rutīna, birokrātiskas atskaites un vide, kas baidās no riska." } },
];

// I ass — Ietekmēšana / Kā ar mani strādāt (10 līmeņi)
export const I_BANDS = [
    { max: 10, pro: { t: "Ļoti Smalkjūtīgais", x: "Ārkārtīgi smalka komunikācijas uztvere — jebkurš asums viņu dziļi skar.", tak: "Lūgt palīdzību saudzīgi un tikai privāti.", klu: "Asa kritika vai pat neitrāls komentārs par darba kvalitāti satricina viņa pārliecību." },
              self: { t: "Ļoti jutīgs", x: "Tu uztver komunikāciju ļoti smalki — pat neitrāls komentārs var dziļi skart. Tev der maiga, droša pieeja.", tak: "Tev der, ja ar tevi runā saudzīgi un privāti, nevis publiski.", klu: "Asa kritika vai komentārs par darba kvalitāti satricina tavu pārliecību." } },
    { max: 20, pro: { t: "Slēptais Introverts", x: "Visefektīvākie ir diskrēti viens-pret-viens lūgumi un emocionāli droša telpa.", tak: "Lūguma forma: 'Man tiešām vajadzētu tavu palīdzību...' Pavēles izteiksme šeit nestrādā.", klu: "Balss pacelšana vai stingri termiņi publiski viņu apstādina un atņem darbaspējas." },
              self: { t: "Klusais introverts", x: "Tu atveries drošā, mierīgā vienatnē — viens pret vienu. Tev vajag sajūtu, ka esi emocionāli drošībā.", tak: "Maigs lūgums klātienē tev der labāk par pavēli; tev patīk, kad jautā, nevis pieprasa.", klu: "Pacelta balss vai publiski stingri termiņi tevi iedzen panikā." } },
    { max: 30, pro: { t: "Klusais Vērotājs", x: "Pirms piekrīt, viņam ilgi jāvēro situācija. Nepanes pēkšņus lēmumus.", tak: "Dodiet viņam laiku pārdomām. Piedāvājiet ideju un atgriezieties pie tās rīt.", klu: "Spiešana uz 'Tūlīt un tagad'." },
              self: { t: "Vērotājs", x: "Pirms piekrīt, tu gribi situāciju izvērtēt. Pēkšņi lēmumi tev nav patīkami.", tak: "Dod sev laiku pārdomām — tev der apsvērt ideju un atgriezties pie tās rīt.", klu: "Spiediens 'tūlīt un tagad' tevi sasprindzina." } },
    { max: 40, pro: { t: "Kooperatīvais Sabiedrotais", x: "Vislabāk iesaistās, kad lēmums tapis kopā — iesaistiet viņu risinājuma izstrādē, lai tas kļūst arī par viņa ideju.", tak: "Jautājumu forma: 'Kā tu domā, varbūt mēs varētu mēģināt darīt šādi?'", klu: "'Dari tā, jo es tā teicu.' Šāda pieeja rada klusu, pasīvu pretestību." },
              self: { t: "Līdzradītājs", x: "Tu vislabāk iesaisties, kad jūti, ka ideja ir arī tava, nevis uzspiesta no augšas.", tak: "Tev patīk, kad tevi iesaista jautājuma formā: 'Kā tu domā, varbūt mēs varētu...'", klu: "Pavēle 'dari tā, jo es tā teicu' tevī rada klusu pretestību." } },
    { max: 50, pro: { t: "Praktiskais Domātājs", x: "Pieprasa pamatotu, piezemētu loģiku. Viņu neinteresē emocijas vai sarežģītas datu zinātnes koncepcijas.", tak: "Izskaidrojiet nākamo soli kā vienkāršu un dabisku procesa turpinājumu.", klu: "Tukšu saukļu un korporatīvā žargona lietošana." },
              self: { t: "Praktiķis", x: "Tev pārliecina piezemēta, skaidra loģika. Liekas emocijas vai sarežģītas abstrakcijas tevi neuzrunā.", tak: "Tev der, kad nākamo soli paskaidro kā vienkāršu, dabisku procesa turpinājumu.", klu: "Tukši saukļi un korporatīvais žargons tevi atgrūž." } },
    { max: 60, pro: { t: "Datu Analītiķis", x: "Lietišķa, faktos balstīta informācijas apmaiņa. Pārliecina Excel tabulas, ROI aprēķini un loģikas ķēdes.", tak: "Argumentējiet katru lēmumu ar faktiem un precedentiem. 'Skaitļi rāda, ka šī pieeja ietaupīs 15%.'", klu: "Emocionāls spiediens vai nepamatots optimisms viņā rada neuzticību jūsu kompetencei." },
              self: { t: "Analītiķis", x: "Tevi pārliecina fakti, skaitļi un loģikas ķēdes. Tev patīk lēmumi, kas balstīti datos.", tak: "Argumenti ar faktiem un piemēriem tev der vislabāk: 'Skaitļi rāda, ka tā ietaupīsim 15%.'", klu: "Emocionāls spiediens vai nepamatots optimisms tevī rada neuzticību." } },
    { max: 70, pro: { t: "Procesu Virzītājs", x: "Respektē sistēmu. Ietekmēt var caur noteikumiem un instrukcijām.", tak: "Norādīt uz uzņēmuma politiku vai izstrādātu procedūru.", klu: "Lūgt viņam ignorēt noteikumus vai radīt izņēmumus 'cilvēcības vārdā'." },
              self: { t: "Sistēmas cilvēks", x: "Tu ciena kārtību un noteikumus. Tev patīk, kad lietas notiek pēc skaidras sistēmas.", tak: "Atsauce uz skaidru procedūru vai vienošanos tev der vislabāk.", klu: "Lūgums ignorēt noteikumus 'izņēmuma kārtā' tevī rada diskomfortu." } },
    { max: 80, pro: { t: "Hierarhijas Cienītājs", x: "Komunikācijā novērtē skaidru hierarhiju un lomu sadalījumu — pārlieku familiāra pieeja viņu mulsina.", tak: "Dodiet skaidrus, strukturētus uzdevumus, pamatojot tos ar lomu un atbildības sadalījumu.", klu: "Izplūdušas robežas un haotiska 'visi dara visu' pieeja mazina viņa cieņu pret vadību." },
              self: { t: "Skaidru lomu cienītājs", x: "Tev patīk skaidra struktūra un lomas. Tu novērtē, kad zini, kurš par ko atbild.", tak: "Skaidri, strukturēti uzdevumi ar saprotamu pamatojumu tev der vislabāk.", klu: "Pārāk izplūdušas robežas un haotiska 'visi dara visu' pieeja tevi mulsina." } },
    { max: 90, pro: { t: "Skaidras Vadības Cienītājs", x: "Vislabāk strādā ar izlēmīgu, nepārprotamu vadību un skaidriem lēmumiem.", tak: "Skaidra, pārliecināta nostāja: 'Lēmums ir šāds, un lūk, kāpēc.' Noteiktība bez agresijas.", klu: "Izvairīga neizlēmība un lēmumu atbildības novelšana uz viņu." },
              self: { t: "Skaidras vadības cienītājs", x: "Tev der spēcīga, droša vadība un skaidri norādījumi. Tu jūties labi, kad virziens ir nepārprotams.", tak: "Skaidra, pārliecināta nostāja un konkrēti norādījumi tev palīdz strādāt.", klu: "Pārmērīga neizlēmība vai izplūduši norādījumi tevi apgrūtina." } },
    { max: 101, pro: { t: "Konkurences Dzinējs", x: "Viņu uzlādē sacensība un izaicinājums. Ja nav mērķa, par ko cīnīties, motivācija izsīkst.", tak: "Godīgs izaicinājums: 'Šis ir patiešām grūts uzdevums — vai esi gatavs to uzņemties?' Viņš ieguldīsies, lai pierādītu sevi.", klu: "Pārmērīga aizbildniecība vai uzdevuma pasniegšana kā viegla mazina viņa motivāciju." },
               self: { t: "Sacensību gars", x: "Tevi uzlādē izaicinājums un sacensība. Bez mērķa, par ko 'cīnīties', tu zaudē sparu.", tak: "Ambiciozs izaicinājums tevi iedvesmo: 'Šis ir patiešām grūts uzdevums.'", klu: "Pārmērīgi glaimi vai lieka aizbildniecība aizskar tavu lepnumu un mazina dzini." } },
];

// V ass — Vājības / Jutīgā vieta (10 līmeņi); st = pāra stiprā puse
export const V_BANDS = [
    { max: 10, pro: { t: "Neiznīcināmais", x: "Pilnīgs psiholoģisks sastingums pret ārējiem kairinātājiem.", st: "Ārkārtas situācijās šis cilvēks ir nesatricināms balsts komandai.", en: "Ļoti zema dabiskā empātija — var netīši pārkāpt kolēģu robežām, to pat nepamanot." },
              self: { t: "Ļoti noturīgs", x: "Tu esi reti psiholoģiski izturīgs — ārēji satricinājumi tevi gandrīz neskar.", st: "Tava reti spēcīgā psiholoģiskā izturība ir īsta dāvana — tu paliec mierīgs tur, kur citiem kļūst par grūtu.", en: "Reizēm vērts apzināti pievērst uzmanību apkārtējo jūtām, lai netīši nepārkāptu citiem pāri." } },
    { max: 20, pro: { t: "Emocionāli Nesatricināmais", x: "Psiholoģiski ārkārtīgi noturīgs — citu viedoklis un spiediens viņu maz ietekmē, bailes viņu nevada.", st: "Spiediena un krīzes apstākļos saglabā vēsu prātu un lemtspēju.", en: "Viedokļu nesakritībās grūti vadāms — uz viņu iedarbojas tikai loģiski argumenti, ne emocijas vai spiediens." },
              self: { t: "Emocionāli stabils", x: "Tu nepadodies spiedienam un nedzīvo bailēs — tavi nervi ir mierīgi.", st: "Tavs mierīgums un neatkarība no spiediena dod tev skaidru galvu lēmumos.", en: "Citiem reizēm grūti tevi 'nolasīt'; apzināta atvērtība palīdz veidot uzticību." } },
    { max: 30, pro: { t: "Pamatīgais Konservatīvais", x: "Nesteidzīgs un stūrgalvīgs. Reiz izveidots uzskats mainās ļoti lēni un negribīgi.", st: "Stabils, prognozējams un uzticams — tur kursu, netiek līdzi katrai modes vēsmai.", en: "Zems inovāciju ātrums. Ja sistēma mainās, viņš drīzāk aizies, nekā pieņems jauno." },
              self: { t: "Noturīgs un pamatīgs", x: "Tu esi nesteidzīgs un pārliecināts savos uzskatos. Reiz izveidots viedoklis tev ir stabils.", st: "Tava pārliecība un stabilitāte padara tevi par balstu, uz kuru var paļauties.", en: "Pārmaiņas tev nāk grūtāk — apzināta atvērtība jaunajam palīdz neiestrēgt." } },
    { max: 40, pro: { t: "Profesionālais Lepnums", x: "Stabili nervi, taču jutīgā vieta — profesionālais lepnums un vēlme būt nemaldīgam savā jomā.", st: "Augstā profesionālā lepnuma dēļ sniedz konsekventi augstu kvalitāti.", en: "Jutīgs pret glaimiem un sava eksperta statusa apšaubīšanu — abi var viņu dzīt pārstrādāties vai izsist no līdzsvara." },
              self: { t: "Profesionāls lepnums", x: "Tev stabili nervi, bet tava jutīgā vieta ir profesionālais lepnums un vēlme būt savā jomā nekļūdīgam.", st: "Tavs profesionālais lepnums dzen tevi uz augstu kvalitāti un meistarību.", en: "Vērts atcerēties: lūgt palīdzību vai kļūdīties arī ir profesionāli — tas nemazina tavu vērtību." } },
    { max: 50, pro: { t: "Vainas Apziņas Nešējs", x: "Izteikta tieksme uzņemties atbildību arī par citu kļūdām.", st: "Ārkārtīgi atbildīgs — uzņemas un pabeidz pat to, ko citi pamet.", en: "Bieži pārstrādājas un izdeg tādēļ, ka nespēj pateikt 'Nē'." },
              self: { t: "Atbildīgā daba", x: "Tu mēdz uzņemties atbildību pat par citu kļūdām. Tava sirdsapziņa ir spēcīga.", st: "Tava spēcīgā atbildības sajūta nozīmē, ka uz tevi var paļauties pat grūtos brīžos.", en: "Iemācīties pateikt 'nē' tevi pasargā no pārslodzes un izdegšanas." } },
    { max: 60, pro: { t: "Atzinības Motivētais", x: "Viņa produktivitāte aug līdzi publiskai atzinībai — ārējais novērtējums viņam ir degviela.", st: "Motivēts ar atzinību — uzslava dod tiešu produktivitātes pieaugumu.", en: "Spēcīgas bailes no publiska nosodījuma; vainas sajūta viņu ātri nomāc — to nedrīkst izmantot kā sviru." },
              self: { t: "Atzinības cienītājs", x: "Tava produktivitāte ceļas līdz ar uzslavām — tev svarīga ārēja novērtēšana.", st: "Dzeniens pēc atzinības nozīmē, ka tev patiesi rūp rezultāts un tā kvalitāte.", en: "Vērts mācīties atrast vērtību sevī, ne tikai citu atzinībā — tas dod stabilitāti." } },
    { max: 70, pro: { t: "Piesardzīgais Perfekcionists", x: "Spēcīgas bailes no kļūdām un publiska kauna. Pirms spert soli, analizēs to desmit reizes.", st: "Perfekcionisms nozīmē ārkārtīgu rūpību — nepalaiž garām kļūdas un detaļas.", en: "Pie mazākās kritikas 'iesalst' darbos un pavada nedēļas, meklējot attaisnojumus." },
              self: { t: "Perfekcionists", x: "Tev raksturīgas bailes kļūdīties un būt publiski kritizētam, tāpēc daudz analizē, pirms spert soli.", st: "Tava uzmanība detaļām un augstie standarti dod izcilu kvalitāti.", en: "Atļaut sev 'pietiekami labi' reizēm der vairāk nekā nevainojami — tas pasargā no iestrēgšanas." } },
    { max: 80, pro: { t: "Trauksmainais Analītiķis", x: "Visur saredz riskus. Nespēj gulēt, ja kaut kas nav pabeigts.", st: "Dabisks risku radars — agri pamana problēmas, ko citi vēl neredz.", en: "Izplata trauksmi komandā, bieži 'uzvelkas' par lietām, kas vispār nav būtiskas." },
              self: { t: "Uzmanīgais", x: "Tu pamani riskus visapkārt un grūti atslēdzies, kamēr kaut kas nav pabeigts.", st: "Tava spēja laikus pamanīt riskus pasargā tevi un komandu no kļūdām.", en: "Apzinātas atelpas un robežu noteikšana palīdz neuzkrāt trauksmi." } },
    { max: 90, pro: { t: "Emocionālais Svārsts", x: "No rīta gatavs iekarot pasauli, vakarā — grib iet prom no darba.", st: "Augstajos viļņos spējīgs uz izcilu, enerģisku un radošu sniegumu.", en: "Rada ārkārtīgi neprognozējamu un svārstīgu vidi komandai." },
              self: { t: "Mainīga noskaņa", x: "Tava enerģija svārstās — no rīta gatavs kalnus gāzt, vakarā nogurums ņem virsroku.", st: "Savās emocionālajās virsotnēs tu esi spējīgs uz patiesi iedvesmotu darbu.", en: "Regulārs ritms un atpūta palīdz izlīdzināt šos kāpumus un kritumus." } },
    { max: 101, pro: { t: "Vides Spogulis", x: "Zem stresa lēmumos spēcīgi paļaujas uz apkārtējiem — savs viedoklis veidojas caur citu atbalstu.", st: "Augsta jutība = izcila empātija un spēja nolasīt grupas noskaņojumu.", en: "Viedoklis var mainīties līdzi pēdējam runātājam; lēmumos meklē apstiprinājumu pie citiem." },
               self: { t: "Jutīgs pret vidi", x: "Zem stresa tu mēdz meklēt atbalstu citos, un tavs viedoklis var mainīties atkarībā no apkārtējiem.", st: "Tava jutība pret vidi nozīmē augstu empātiju un spēju nolasīt cilvēkus.", en: "Apzināti veidot savu iekšējo viedokli pirms sarunām dod tev stabilitāti." } },
];

// 7 galvenie dzinēji (astro = kopīgs abos režīmos; rez = pro:piemērs sarunā / self:kas rezonē)
export const DRIVERS = {
    Ideja: { astro: "Ziemeļu mezgls 10. mājā, spēcīgs Jupiters, Maiju Tz'i'.",
        pro: { title: "IDEJA (JĒGA / MISIJA)", valuta: "Sajūta, ka viņš maina pasauli vai glābj projektu.", butiba: "Dziļa aizrautība ar projektu un tā jēgu. Nauda viņam ir tikai fons.", rez: "Šis projekts mainīs nozari. Mums vajag tavu vīziju, lai to glābtu.", balva: "Brīvība lēmumos un publiska atzinība par ietekmi, nevis vienkārša nauda." },
        self: { title: "JĒGA UN MISIJA", valuta: "Sajūta, ka manam darbam ir nozīme un tas kaut ko maina.", butiba: "Tevi dziļi aizrauj jēgpilns mērķis. Nauda tev ir tikai fons.", rez: "Tevi uzrunā sajūta, ka šim darbam ir patiesa nozīme un tava vīzija ir svarīga.", balva: "Brīvība lēmumos un patiesa atzinība par tavu ietekmi — ne tikai nauda." } },
    Statuss: { astro: "Saule (Vēdas), 10. māja, BaZi 7 Killings.",
        pro: { title: "STATUSS (VARA / TITULS)", valuta: "Publisks 'paldies', ietekmīgs amata nosaukums.", butiba: "Vēlas redzamu lomu un statusa apliecinājumus — tituls, kabinets un komanda viņam ir svarīgi.", rez: "Ja tu šo izdarīsi, tu varēsi prezentēt rezultātus Valdei un būsi atbildīgs par nodaļu.", balva: "Amata paaugstinājums, lielāks kabinets vai komandas piešķiršana." },
        self: { title: "ATZINĪBA UN LOMA", valuta: "Patiesa atzinība un nozīmīga loma.", butiba: "Tev svarīgi justies novērtētam un redzamam savā lomā.", rez: "Tevi uzrunā iespēja parādīt rezultātus un uzņemties nozīmīgu atbildību.", balva: "Nozīmīga loma, atpazīstamība un iespēja vadīt." } },
    Nauda: { astro: "2. un 11. māja, BaZi Direct Wealth, Vērsis.",
        pro: { title: "NAUDA (RESURSS / BAGĀTĪBA)", valuta: "Tieša piemaksa, prēmija vai finansiāli bonusi.", butiba: "Pragmatiska un transakcionāla pieeja. Viņa laiks maksā konkrētu summu.", rez: "Ja atnāksi sestdienā, šī diena tiks apmaksāta dubultā + 20% prēmija.", balva: "Caurspīdīgi bonusi un finansiālas dāvanas. Nedodiet 'paldies rakstus'." },
        self: { title: "RESURSI UN DROŠĪBA", valuta: "Konkrēta, taisnīga finansiāla atlīdzība.", butiba: "Tev patīk pragmatiska skaidrība — tavs laiks un darbs ir konkrētas vērtības.", rez: "Tevi uzrunā skaidra, godīga vienošanās par atlīdzību.", balva: "Caurspīdīgi bonusi un finansiāla atzinība, ne simboliski 'paldies raksti'." } },
    Brīvība: { astro: "Urāns (Rietumi), Eating God (BaZi), Iq' (Maiju Vējš).",
        pro: { title: "BRĪVĪBA (AUTONOMIJA)", valuta: "Elastīgs grafiks, tiesības strādāt no jebkuras vietas.", butiba: "Iespēja pašam noteikt 'kad' un 'kā'. Laiks viņam ir dārgāks par naudu.", rez: "Ja gribat, lai viņš strādā sestdienā, nesoliet naudu, bet apsoliet brīvu pirmdienu.", balva: "Piedāvājiet pašnoteikšanās lomu ar reālu autonomiju, ne tikai amata nosaukumu." },
        self: { title: "AUTONOMIJA", valuta: "Elastīgs grafiks un brīvība pašam noteikt, kā strādāt.", butiba: "Tev svarīgi pašam izvēlēties 'kad' un 'kā'. Laiks tev ir dārgāks par naudu.", rez: "Tevi uzrunā brīvība un uzticēšanās, ne kontrole.", balva: "Pašnoteikšanās un elastība, ne tikai amats vai nauda." } },
    Drošība: { astro: "Mēness (Vēdas), Direct Resource (BaZi), Saturns (Hellēnisms).",
        pro: { title: "DROŠĪBA (STABILITĀTE)", valuta: "Veselības apdrošināšana, stabila nākotnes perspektīva.", butiba: "Emocionāls miers un garantijas. Viņš strādā, lai nebūtu haosa.", rez: "Viņš atnāks sestdienā, ja vadītājs pateiks: 'Tas palīdzēs mums mierīgi noslēgt ceturksni bez stresa.'", balva: "Ilgtermiņa līgums un papildu sociālās garantijas." },
        self: { title: "STABILITĀTE", valuta: "Drošības sajūta un skaidra nākotnes perspektīva.", butiba: "Tev svarīgs emocionāls miers un garantijas. Tu strādā, lai dzīvē nebūtu haosa.", rez: "Tevi nomierina sajūta, ka viss ir stabils un paredzams.", balva: "Ilgtermiņa drošība un papildu garantijas." } },
    Meistarība: { astro: "Merkurijs (Vēdas/Rietumi), Koksne (BaZi), No'j (Maiju Zināšanas).",
        pro: { title: "MEISTARĪBA (IZAUGSME)", valuta: "Apmācības, piekļuve unikāliem rīkiem vai datiem.", butiba: "Intelektuālais azarts. Vēlme kļūt par visgudrāko istabā.", rez: "Šis ziņojums ir tehniski ļoti sarežģīts, tikai tu to spēsi izdarīt tik profesionāli.", balva: "Apmaksāti dārgi kursi, grāmatas vai ekskluzīva sertifikācija." },
        self: { title: "IZAUGSME UN MEISTARĪBA", valuta: "Iespēja mācīties, piekļuve unikāliem rīkiem un zināšanām.", butiba: "Tevi dzen intelektuālais azarts un vēlme kļūt patiesi labam savā jomā.", rez: "Tevi uzrunā sarežģīti, attīstoši uzdevumi, kas prasa meistarību.", balva: "Apmaksāti kursi, grāmatas un iespēja augt profesionāli." } },
    Piederība: { astro: "Venera, BaZi Friend zvaigzne, 7. māja.",
        pro: { title: "PIEDERĪBA (KOPIENA / ATTIECĪBAS)", valuta: "Kopības sajūta, lojalitāte un mikro-klimats.", butiba: "Cilvēks strādā, jo viņam garšo biroja virtuvē dzert kafiju un viņš mīl savu komandu.", rez: "Atnāc sestdienā, pasūtīsim picu un abi ar vadītāju šo ziņojumu piebeigsim.", balva: "Neformāli komandas pasākumi un sirsnīga, personiska atzinība." },
        self: { title: "PIEDERĪBA UN ATTIECĪBAS", valuta: "Kopības sajūta, lojalitāte un silts mikroklimats.", butiba: "Tu strādā cilvēku dēļ — tev svarīga sava komanda un sirsnīga gaisotne.", rez: "Tevi uzrunā sajūta, ka esi daļa no saliedētas komandas.", balva: "Neformāli kopā pavadīti brīži un sirsnīga, personiska atzinība." } },
};

// Dinamiskā brīdinājuma / atziņas arhetipi — tuvākā prototipa modelis D/I/V telpā.
// Prototipi: 8 kuba stūri + centrs + 3 dominantās asis = 12. Pie nelielām D/I/V izmaiņām
// pie Voronoi robežām rezultāts pārslēdzas uz citu, atbilstošāku arhetipu.
export const WARN_ARCHETYPES = [
    { d: 85, i: 25, v: 25, // augsta motivācija, grūti ietekmējams, noturīgs
        pro: { title: "Neuzpērkamais Ideālists", text: "Persona deg par ideju un ir grūti ietekmējama. Viņu nevar 'nopirkt' ar naudu, ja darbs nesaskan ar viņa vērtībām — vadītājam jākļūst par domubiedru, ne priekšnieku.", do: "Piesaisti viņu jēgpilniem mērķiem un parādi, kā viņa darbs maina lietas.", dont: "Nemēģini nopirkt lojalitāti tikai ar naudu vai prēmijām — tas rada pretestību." },
        self: { title: "Vērtību vizionārs", text: "Tevi dzen idejas un jēga, un tu esi grūti ietekmējams. Tevi nevar 'nopirkt', ja darbs nesaskan ar to, kam tici.", do: "Darbs ar jēgu un reālu ietekmi, kur vari sekot savai vīzijai.", dont: "Sargies no vides, kur vienīgais arguments ir nauda — tā tevī rada iekšēju pretestību." } },
    { d: 80, i: 75, v: 80, // augsta motivācija, viegli ietekmējams, trausls
        pro: { title: "Atzinības virzītais līderis", text: "Augsta motivācija pēc varas, bet izteikta atkarība no citu atzinības. Publiska uzslava viņu spārno; publisks rājiens dziļi ievaino — abi spēcīgi ietekmē sniegumu.", do: "Atzīsti viņa kompetenci publiski un dod redzamu, skanīgu lomu.", dont: "Neignorē viņa panākumus publiskās sapulcēs — redzamība viņam ir vitāli svarīga." },
        self: { title: "Atzinības cienītājs ar mērķi", text: "Tev ir augsta motivācija un vēlme vadīt, bet arī liela vajadzība pēc atzinības. Uzslava tevi spārno, publisks rājiens dziļi skar.", do: "Vide, kas novērtē tavu kompetenci un dod tev redzamu lomu.", dont: "Sargies kļūt pārāk atkarīgam no citu vērtējuma — meklē stabilitāti arī sevī." } },
    { d: 82, i: 75, v: 25, // augsta motivācija, viegli ietekmējams, noturīgs
        pro: { title: "Dzelžainais Komandieris", text: "Spēcīga virzība uz mērķi apvienojumā ar noturīgiem nerviem. Respektē loģiku un autoritāti, neuztraucas par citu domām.", do: "Dod skaidrus, ambiciozus mērķus un argumentē ar faktiem un rezultātu.", dont: "Nemēģini vadīt ar emocijām vai izlēkšanu — viņš to neciena." },
        self: { title: "Mērķtiecīgais līderis", text: "Tevi dzen rezultāts, un tev ir noturīgi nervi. Tu ciena loģiku un skaidru virzienu; citu domas tevi maz satricina.", do: "Skaidri, ambiciozi mērķi un brīvība tos sasniegt savā veidā.", dont: "Sargies kļūt pārāk strups pret tiem, kam vajadzīga siltāka pieeja." } },
    { d: 82, i: 25, v: 78, // augsta motivācija, grūti ietekmējams, trausls
        pro: { title: "Kaislīgais Perfekcionists", text: "Spēcīga iekšējā dzinēja apvienojums ar augstu jutīgumu. Deg par darbu, bet smagi pārdzīvo kritiku un kļūdas.", do: "Atzīsti viņa augstos standartus un dod atgriezenisko saiti maigi un privāti.", dont: "Nekritizē publiski un neuzliec lieku spiedienu — viņš 'iesalst' darbos." },
        self: { title: "Kaislīgais perfekcionists", text: "Tevi dzen iekšēja degsme, bet tu arī smagi pārdzīvo kritiku un kļūdas. Augsti standarti ir reizē tava stiprā un jutīgā puse.", do: "Vide, kas novērtē tavu rūpību un dod mierīgu, drošu atgriezenisko saiti.", dont: "Sargies no perfekcionisma slazda — 'pietiekami labi' reizēm der vairāk." } },
    { d: 20, i: 20, v: 20, // zema motivācija, grūti ietekmējams, ļoti noturīgs
        pro: { title: "Klusais Nesatricināmais", text: "Zema ambīcija, bet ārkārtīgi noturīgs un grūti ietekmējams. Strādā mierīgi savā tempā; spiediens uz viņu neiedarbojas.", do: "Dod skaidru, stabilu rutīnu un netraucē — viņš izdarīs savu darbu.", dont: "Nemēģini uzkurināt ar ambīcijām vai spiedienu — tas tikai attālinās." },
        self: { title: "Mierīgais un nesatricināmais", text: "Tev nav lielu karjeras ambīciju, bet tu esi ārkārtīgi noturīgs — spiediens un haoss tevi maz ietekmē.", do: "Stabila rutīna un miers, kur vari darīt savu darbu savā tempā.", dont: "Sargies, lai mierīgums nepārvēršas vienaldzībā pret iespējām, kas tev tomēr būtu vērtīgas." } },
    { d: 25, i: 78, v: 78, // zema motivācija, viegli ietekmējams, trausls
        pro: { title: "Jutīgais Komandas Spēlētājs", text: "Zema personīgā ambīcija, viegli ietekmējams un emocionāli jutīgs. Strādā attiecību, ne mērķu dēļ; sliktu vidi pārdzīvo smagi.", do: "Radi siltu, drošu vidi un vadi caur personīgām attiecībām un atzinību.", dont: "Nelieto aukstu spiedienu vai publisku kritiku — tas viņu dziļi ievaino un apstādina." },
        self: { title: "Sirsnīgais komandas cilvēks", text: "Tevi nes attiecības, ne ambīcijas, un tu esi jutīgs pret vidi. Siltā komandā tu uzplaukti, aukstā — zaudē sparu.", do: "Sirsnīga komanda, droša gaisotne un atzinība aci pret aci.", dont: "Sargies pārāk paļauties uz citu noskaņojumu — meklē atbalstu arī sevī." } },
    { d: 22, i: 78, v: 25, // zema motivācija, viegli vadāms, stabils
        pro: { title: "Uzticamais Izpildītājs", text: "Zema ambīcija, viegli vadāms un emocionāli stabils. Labi izpilda skaidrus uzdevumus bez liekas pretestības vai drāmas.", do: "Dod skaidrus, konkrētus uzdevumus un stabilu vidi.", dont: "Negaidi no viņa pašiniciatīvu vai cīņu par līderību — tas nav viņa stihija." },
        self: { title: "Stabilais izpildītājs", text: "Tev nav lielu ambīciju, tu esi pielāgojams un emocionāli stabils. Tu labi jūties ar skaidriem uzdevumiem un mierīgu vidi.", do: "Skaidri uzdevumi un paredzama, mierīga vide.", dont: "Sargies, lai pielāgošanās nepārklāj tavas paša vajadzības un robežas." } },
    { d: 22, i: 25, v: 80, // zema motivācija, grūti ietekmējams, trausls
        pro: { title: "Trauslais Vērotājs", text: "Zema ambīcija, noslēgts un emocionāli jutīgs. Grūti aizsniedzams un viegli ievainojams; vēro no malas, pirms iesaistās.", do: "Tuvojies pacietīgi un saudzīgi, dod laiku un drošību.", dont: "Nesteidzini un nespied publiski — viņš noslēgsies pavisam." },
        self: { title: "Klusais vērotājs", text: "Tev nav lielu ambīciju, tu esi noslēgts un jutīgs. Tev vajag laiku un drošību, pirms atveries un iesaisties.", do: "Mierīga, droša vide un laiks lietas izvērtēt savā tempā.", dont: "Sargies no pārāk ilgas noslēgšanās — neliels solis uz āru bieži palīdz vairāk nekā gaidīšana." } },
    { d: 50, i: 50, v: 50, // līdzsvars (centrs)
        pro: { title: "Līdzsvarotais Pragmatiķis", text: "Mērena motivācija, ietekmējamība un noturība — bez izteiktām galējībām. Elastīgs un paredzams, pielāgojas situācijai.", do: "Skaidri mērķi un godīga, līdzsvarota pieeja der vislabāk.", dont: "Neizmanto galējības — ne pārmērīgu spiedienu, ne pārmērīgu glaimošanu." },
        self: { title: "Līdzsvarotais pragmatiķis", text: "Tava motivācija, pieeja un jutīgums ir mērens, bez izteiktām galējībām. Tu elastīgi pielāgojies situācijai.", do: "Skaidri mērķi un godīga, līdzsvarota vide.", dont: "Sargies, lai līdzsvarotība nepārvēršas par 'visiem patīkamu' bez savas skaidras nostājas." } },
    { d: 80, i: 50, v: 50, // motivācijas dominante
        pro: { title: "Ambiciozais Dzinējs", text: "Spēcīga virzība uz priekšu ar mērenu ietekmējamību un noturību. Grib augšup un meklē izaicinājumus.", do: "Dod izaicinošus mērķus un skaidru izaugsmes ceļu.", dont: "Neaiztur viņu rutīnā bez perspektīvas — viņš zaudēs interesi." },
        self: { title: "Ambiciozais dzinējs", text: "Tevi spēcīgi dzen virzība uz priekšu, bet pieeja un noturība tev ir mērena. Tu meklē izaugsmi un izaicinājumus.", do: "Izaicinoši mērķi un skaidrs ceļš uz priekšu.", dont: "Sargies no izdegšanas, dzenoties pēc nākamā mērķa — atpūta arī ir daļa no izaugsmes." } },
    { d: 50, i: 80, v: 50, // ietekmējamības dominante
        pro: { title: "Sociālais Diplomāts", text: "Mērena ambīcija, bet ļoti viegli ietekmējams un sociāli lokans. Labi sadarbojas un pielāgojas cilvēkiem.", do: "Vadi caur sarunu un sadarbību, iesaisti viņu kopējos lēmumos.", dont: "Nelieto sausu pavēli vai izolāciju — viņš darbojas caur attiecībām." },
        self: { title: "Sociālais diplomāts", text: "Tava ambīcija ir mērena, bet tu esi sociāli lokans un labi jūti cilvēkus. Tu uzplaukti sadarbībā.", do: "Sadarbība, dialogs un iesaiste kopējos lēmumos.", dont: "Sargies pārāk pielāgoties citiem — arī tava nostāja ir svarīga." } },
    { d: 50, i: 50, v: 80, // jutīguma dominante
        pro: { title: "Trauksmainais Centīgais", text: "Mērena ambīcija un ietekmējamība, bet augsts jutīgums. Cenšas, bet viegli satraucas un pārdzīvo kritiku.", do: "Dod drošību, skaidrību un mierīgu atgriezenisko saiti.", dont: "Nelieto asu kritiku vai neskaidrību — tas izsauc trauksmi." },
        self: { title: "Centīgais un jutīgais", text: "Tava ambīcija un pieeja ir mērena, bet tu esi jutīgs un viegli satraucies. Tu centies, bet kritiku pārdzīvo smagi.", do: "Droša, skaidra vide un mierīga atgriezeniskā saite.", dont: "Sargies no pārmērīga satraukuma par sīkumiem — ne viss ir tik kritiski, kā šķiet." } },
];

// Skalas polu apzīmējumi (galos) + interpretācija pa zonām (lo<40 / mid / hi>60), pa režīmiem
const AXIS_POLES = {
    D: { lo: "Zems dzinējs", hi: "Spēcīgs dzinējs",
        self: { lo: "Tevi mazāk dzen ārēja ambīcija — svarīgāks ir miers un stabilitāte", mid: "Tava motivācija ir mērena, bez izteiktām galējībām", hi: "Tevi spēcīgi dzen ambīcija, mērķi un vēlme vadīt" },
        pro: { lo: "Vāja iekšējā ambīcija — dzinējs ir miers un stabilitāte", mid: "Mērena motivācija bez izteiktām galējībām", hi: "Spēcīga iekšējā ambīcija un virzība uz mērķi vai varu" } },
    I: { lo: "Maiga pieeja", hi: "Tieša pieeja",
        self: { lo: "Tev der maiga, saudzīga un privāta pieeja", mid: "Tev der līdzsvarota, mierīga pieeja", hi: "Tev der tieša, konkrēta un izaicinoša pieeja" },
        pro: { lo: "Vajadzīga maiga, saudzīga un privāta pieeja", mid: "Der līdzsvarota, skaidra pieeja", hi: "Reaģē uz tiešu, konkrētu un izaicinošu pieeju" } },
    V: { lo: "Noturīgs", hi: "Jutīgs",
        self: { lo: "Tu esi emocionāli ļoti noturīgs un grūti satricināms", mid: "Tava emocionālā jutība ir mērena", hi: "Tu esi emocionāli jutīgs un viegli ievainojams" },
        pro: { lo: "Emocionāli ļoti noturīgs un grūti satricināms", mid: "Mērena emocionālā jutība", hi: "Emocionāli jutīgs un viegli ievainojams" } },
};

// Galvenā iekšējā spriedze — asu mijiedarbība (D×I×V). Pirmais sakritušais noteikums uzvar.
export function keyTension(D, I, V) {
    if (D >= 65 && V >= 65) return { title: "Dzinējs pret jutīgumu", self: "Tava spēcīgā ambīcija un augstā jutība kopā rada izdegšanas risku — tev vajag gan mērķi, gan atzinību un atpūtu.", pro: "Spēcīga ambīcija + augsta jutība = izdegšanas risks. Vajag gan izaicinājumus, gan atzinību un atelpu." };
    if (D >= 65 && I < 40) return { title: "Spēcīgs, bet jāpieiet maigi", self: "Tevi spēcīgi dzen mērķi, bet tu reaģē uz maigu pieeju, ne spiedienu — vislabāk strādā ar autonomiju.", pro: "Spēcīgs dzinējs, bet grūti vadāms ar spiedienu. Motivē ar autonomiju un jēgu, ne pavēli." };
    if (D < 40 && V < 40) return { title: "Stabils, bet grūti iekustināms", self: "Tu esi mierīgs un noturīgs balsts, bet tev vajag ārēju impulsu, lai iekustētos.", pro: "Stabils un noturīgs, bet ar zemu pašiniciatīvu — vajag skaidru ārēju impulsu un virzienu." };
    if (I >= 65 && V >= 65) return { title: "Ietekmējams un jutīgs", self: "Tu viegli ļaujies ietekmei un esi jutīgs — tāpēc tev svarīgi izvēlēties drošu vidi un cilvēkus.", pro: "Viegli ietekmējams un jutīgs — atsaucīgs vadībai, bet jāsargā no negodīgas ietekmes un pārslodzes." };
    if (D >= 65 && I >= 65 && V < 40) return { title: "Dabisks līderis", self: "Spēcīgs dzinējs, sociāla pārliecība un noturība padara tevi par dabisku vadītāju — sargies tikai no pārlieku strupas pieejas.", pro: "Spēcīgs dzinējs + pārliecība + noturība = dabisks līderis. Galvenais risks — pārāk strupa pieeja pret jutīgākiem." };
    if (V >= 70) return { title: "Jutīgums kā galvenā ass", self: "Tava emocionālā jutība ir noteicošā — psiholoģiskā drošība tev ir svarīgāka par jebko citu.", pro: "Emocionālā jutība ir noteicošā ass — psiholoģiskā drošība ir galvenais vadības nosacījums." };
    if (D >= 70) return { title: "Dzinējs kā galvenā ass", self: "Tevi galvenokārt nosaka spēcīgs iekšējais dzinējs — tev vajag jēgpilnu mērķi un brīvību to sasniegt.", pro: "Galvenā ass ir spēcīgs dzinējs — vajag jēgpilnu mērķi un autonomiju to sasniegt." };
    return { title: "Līdzsvarots profils", self: "Tavas trīs asis ir samērā līdzsvarotas — tu esi elastīgs un pielāgojams, bez vienas dominējošas spriedzes.", pro: "Trīs asis samērā līdzsvarotas — elastīgs un pielāgojams profils bez vienas dominējošas spriedzes." };
}

// Krusteniskā direktīva — I×V kombinācijas sintēze (cross-dim, actionSummary mācība):
// kad abas asis ir izteiktas, kolonnu teksti atsevišķi var radīt pretrunas iespaidu
// ("skaidra vadība" blakus "viegli ievainojams") — šis bloks tās SAVIENO vienā norādē.
function crossDirective(I, V) {
    if (I > 60 && V > 60) return {
        title: "⚖️ Kombinācija: noteiktība bez asuma",
        pro: "Abas asis ir izteiktas vienlaikus: profils vislabāk strādā ar skaidru, izlēmīgu vadību (pieeja), bet ir emocionāli jutīgs (jutīgums). Praksē — lēmumi un prasības nepārprotami, tonis mierīgs un cieņpilns, kritika tikai privāti. Ass spiediens dod īslaicīgu paklausību un ilgstošu ievainojumu.",
        self: "Tev der skaidri, noteikti norādījumi, bet tu esi arī emocionāli jutīgs — tev vislabāk strādā vide, kur lēmumi ir nepārprotami, bet tonis mierīgs un cieņpilns."
    };
    if (I < 40 && V < 40) return {
        title: "⚖️ Kombinācija: telpa un uzticēšanās",
        pro: "Profilam der maiga, netieša pieeja, un vienlaikus viņš ir emocionāli noturīgs — spiediens nav ne vajadzīgs, ne efektīvs. Dodiet telpu, laiku un skaidru mērķi; rezultāts nāk bez pastāvīgas uzraudzības.",
        self: "Tev der maiga pieeja, un tu esi emocionāli noturīgs — tev vislabāk strādā uzticēšanās un telpa, ne kontrole."
    };
    return null;
}

// Mode-specific virsraksti un etiķetes
const LBL = {
    self: {
        subtitle: "Kas tevi patiesi dzen, kā ar tevi vislabāk strādāt un kur tava jutīgā vieta",
        radar: ["DZINĒJS", "PIEEJA", "JUTĪGUMS"],
        warnHeadTag: "Galvenā atziņa par sevi", warnIcon: "💡",
        doLabel: "✅ KAS TEV DER:", dontLabel: "⚠️ NO KĀ SARGĀTIES:",
        driverTag: "🧭 Mana galvenā dziļā vajadzība", driverLead: "Kas mani patiesi dzen:",
        butiba: "Būtība:", valuta: "Mana galvenā valūta:", rezTag: "Kas man rezonē:", balvaTag: "💡 Kas tevi patiesi atalgo:",
        matrixHead: "🧠 Mans profils (pašizpratnes karte)",
        mD: "🚀 Kas mani dzen", mDtype: "Tips:", mDtr: "Kas man dod enerģiju:", mDat: "Kas mani nogurdina:",
        mI: "🎯 Kā ar mani strādāt", mItype: "Mans stils:", mItak: "Kā ar mani runāt:", mIklu: "Kas mani aizver:",
        mV: "🌱 Mana jutīgā vieta", mVtype: "Tendence:", mVst: "🟢 Stiprā puse:", mVen: "Kam pievērst uzmanību:",
    },
    pro: {
        subtitle: "Vadītāja rokasgrāmata — kā šo cilvēku motivēt, kā ar viņu strādāt un kam pievērst uzmanību",
        radar: ["MOTIVĀCIJA", "PIEEJA", "JUTĪGUMS"],
        warnHeadTag: "Galvenā vadības atziņa", warnIcon: "🧭",
        doLabel: "✅ KO DARĪT (DO):", dontLabel: "❌ KO NEDARĪT (DON'T):",
        driverTag: "🕹️ Motivācijas prioritāte", driverLead: "Galvenais dzinējs:",
        butiba: "Būtība:", valuta: "Galvenā valūta:", rezTag: "Piemērs sarunā:", balvaTag: "💡 Atalgojuma ieteikums:",
        matrixHead: "🧠 Padziļinātais profils (trīs asu matrica)",
        mD: "🚀 Iekšējais dzinējs", mDtype: "Tips:", mDtr: "Kas iedvesmo:", mDat: "Kas atņem sparu:",
        mI: "🎯 Kā ar viņu strādāt", mItype: "Stils:", mItak: "Kas strādā:", mIklu: "Tipiska kļūda:",
        mV: "🌱 Jutīgā vieta", mVtype: "Tendence:", mVst: "💪 Stiprā puse (vērtība komandai):", mVen: "Ēnas puse (kam pievērst uzmanību):",
    },
};

export function pickBand(bands, v) {
    if (!Number.isFinite(v)) return null;   // trūkstoši/NaN dati → nav joslas (NEVIS klusi ekstrēmākā)
    for (const b of bands) { if (v < b.max) return b; }
    return bands[bands.length - 1];
}

// PRIORITY ENGINE (7 Galvenie Dzinēji) — viens patiesības avots; lieto arī investor_memo.js.
// Domēna noteikumi, validēti pret realitāti (tuvākā-prototipa modelis bija sliktāks 39% gadījumu).
export function pickDriverKey(D, I, V, motivation = null) {
    // Dzinēji bez tieša motivācijas-tipa ekvivalenta — no D/V heiristikas:
    if (V > 75) return "Drošība";             // augsts jutīgums → stabilitāte/garantijas
    if (D < 40 && V < 40) return "Brīvība";   // zema ambīcija + noturīgs → autonomija
    if (I < 40 && V > 40) return "Piederība"; // maiga pieeja + jutīgs → piederība/attiecības

    // Pārējie — no FAKTISKĀ motivācijas sadalījuma (synergy.motivation), NE no D/I/V magnitūdu
    // minējuma. Iepriekš mid-D (40–70) deva "Nauda" kā atlikuma kasti, ignorējot, ka materiālā
    // motivācija bieži ir zemākā (piem. Statusa=100, Materiālā=25 → nepatiesi sanāca "Nauda").
    // Sociālā IZLAISTA — scoring.js to hardkodē uz 75 (nav reālu datu), tāpēc nedrīkst dominēt.
    if (motivation) {
        const stat  = Number(motivation.Statusa)      || 0;
        const mat   = Number(motivation.Materiālā)    || 0;
        const intel = Number(motivation.Intelektuālā) || 0;
        if (stat >= mat && stat >= intel) return "Statuss";
        if (mat  >= stat && mat  >= intel) return "Nauda";
        return (D > 70) ? "Ideja" : "Meistarība"; // intelektuālā dominante: misija (augsta ambīcija) / meistarība
    }

    // Fallback bez motivācijas datiem — vecā D/I/V kaskāde
    if (D > 80) return "Ideja";
    if (D > 63 && I > 55) return "Statuss";
    if (I > 70 && V < 60) return "Meistarība";
    if (D >= 40 && D <= 70) return "Nauda";
    const fallback = ["Brīvība", "Meistarība", "Piederība"];
    return fallback[Math.floor(D + I + V) % 3];
}

// Tuvākais arhetips D/I/V telpā (12 prototipi) — kopīgs avots: hakera panelis ("Galvenā
// vadības atziņa") un investor_memo.js (cilnes galvenais profila novērtējums). Atgriež pilnu ierakstu.
export function pickWarnArchetype(D, I, V) {
    let warn = WARN_ARCHETYPES[0], bestDist = Infinity;
    for (const a of WARN_ARCHETYPES) {
        const dist = (D - a.d) ** 2 + (I - a.i) ** 2 + (V - a.v) ** 2;
        if (dist < bestDist) { bestDist = dist; warn = a; }
    }
    return warn;
}

// ── #1: Sistēmu konsenss + spēcīgākais signāls (no .details apakšskoriem) ─────
// Atkārto synergy_panel.js loģiku (sd < 1.4 vienojas / < 2.6 daļēji / citādi nesaskan).
function consensusOf(details) {
    const scores = (details || []).map(d => Number(d.score)).filter(n => !isNaN(n));
    if (scores.length < 2) return null;
    const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
    const sd = Math.sqrt(scores.reduce((a, b) => a + (b - mean) ** 2, 0) / scores.length);
    if (sd < 1.4) return { label: "Sistēmas vienojas", icon: "✓", col: "#065f46", bg: "#ecfdf5" };
    if (sd < 2.6) return { label: "Daļēja saskaņa", icon: "≈", col: "#92400e", bg: "#fffbeb" };
    return { label: "Sistēmas nesaskan", icon: "⚡", col: "#991b1b", bg: "#fef2f2" };
}
function strongestOf(details) {
    const arr = (details || []).filter(d => d && d.name);
    if (!arr.length) return null;
    let best = arr[0], bestV = -Infinity;
    for (const d of arr) {
        const val = (Number(d.score) || 0) * (Number(d.weight) || 1);
        if (val > bestV) { bestV = val; best = d; }
    }
    return best.element ? `${best.name}: ${best.element}` : best.name;
}
function consHTML(details) {
    const c = consensusOf(details);
    if (!c) return "";
    const strong = strongestOf(details);
    return `<div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:0.74rem; margin:0 0 10px 0; padding:5px 9px; background:${c.bg}; border-radius:5px; color:${c.col};">
        <b>${c.icon} ${c.label}</b>${strong ? `<span style="color:#64748b;">· spēcīgākais: ${strong}</span>` : ""}</div>`;
}

// ── #2: Skalas pozīcija ar polu apzīmējumiem (kreisais/labais gals) + interpretācija ──
function posBarHTML(v, poles, m) {
    const zone = v < 40 ? "lo" : v > 60 ? "hi" : "mid";
    const note = poles[m][zone];
    return `<div style="margin:2px 0 10px 0;">
        <div style="display:flex; justify-content:space-between; font-size:0.68rem; color:#94a3b8; margin-bottom:4px; gap:8px;">
            <span>◄ ${poles.lo}</span><span>${poles.hi} ►</span>
        </div>
        <div style="position:relative; height:8px; background:linear-gradient(90deg,#e0e7ff,#f5d0fe); border-radius:4px;">
            <div style="position:absolute; left:${v}%; top:-3px; width:14px; height:14px; margin-left:-7px; background:#d946ef; border:2px solid #fff; border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>
        </div>
        <div style="font-size:0.76rem; color:#475569; margin-top:6px; font-weight:600;">${note}</div>
    </div>`;
}

// D/I/V asis = personības dimensijas (dzinulis · pieeja · jutīgums), tāpēc tās nāk no
// nosauktajiem personības trait, NE no synergy radara skoriem (leadership/performance/risks),
// kas būvēti citam mērķim un semantiski NEsakrita ar asu definīcijām:
//   performance (darba izpilde) ≠ pieeja (komunikācija);
//   risks (omnibus, iekļauj Ego/aizvainojumu) ≠ jutīgums (trauksme);
//   leadership (kapacitāte vadīt) ≠ dzinulis (ambīcija).
// Koplietots ar investor_memo.js, lai badge un panelis vienmēr sakrīt.
export function deriveDIV(profile) {
    const traits = (profile?.personality || []).flatMap(c => c.traits || []);
    const g = (id) => { const t = traits.find(x => x.id === id); const n = Number(t?.pct); return isNaN(n) ? 50 : n; };
    return {
        D: g('ambition'),     // Dzinulis (Zems↔Spēcīgs dzinējs) = ambīcija/dzinspēks
        I: g('assertive'),    // Pieeja (Maiga↔Tieša) = asertivitāte
        V: g('neuroticism'),  // Jutīgums (Noturīgs↔Jutīgs) = neirotisms
    };
}

export function renderHackerPanel(profile) {
    const s = profile.synergy || {};

    // SVARĪGI: scoring.js normalizePct atgriež VIRKNI (toFixed), tāpēc jāpieņem skaitliskas virknes ("78").
    // Tikai patiesi trūkstoša vērtība (undefined/null/'') → noklusējums; likumīgs 0 paliek 0.
    const num = (x, def) => {
        if (x === null || x === undefined || x === '') return def;
        const n = Number(x);
        return isNaN(n) ? def : n;
    };
    // No-data vārts: ja NEVIENA no trīs sinerģijas asīm nav pieejama, nerādām fabricētu
    // lasījumu no 60/50/40 noklusējumiem — godīgāk pateikt, ka pamatdatu vēl nav.
    const present = (x) => x !== null && x !== undefined && x !== '' && !isNaN(Number(x));
    if (!present(s.leadership?.pct) && !present(s.performance?.pct) && !present(s.risks?.pct)) {
        return `
        <div class="panel hk-panel" style="grid-column: 1 / -1; border-left: 4px solid #d946ef; background: #fdf4ff;">
            <h3 class="panel-title" style="color:#a21caf; display:flex; align-items:center; gap:0.5rem;">
                <span style="font-size:1.2rem;">🕹️</span> Motivācija un vadības sviras
            </h3>
            <p style="font-size:0.9rem; color:#86198f; line-height:1.6; margin:0.25rem 0 0;">
                Šim profilam vēl nav aprēķinātas motivācijas asis (sinerģijas dzinulis · pieeja · jutīgums), tāpēc lasījums netiek rādīts. Labāk neko nerādīt nekā profilu, kas balstīts vidējos noklusējumos.
            </p>
        </div>`;
    }

    const { D, I, V } = deriveDIV(profile);   // dzinulis=ambition · pieeja=assertive · jutīgums=neuroticism (sk. deriveDIV augšā)

    const dBand = pickBand(D_BANDS, D);
    const iBand = pickBand(I_BANDS, I);
    const vBand = pickBand(V_BANDS, V);

    // Dzinēja izvēle — kopīgā pickDriverKey (definēta augšā, lieto arī investor_memo.js).
    // Padod faktisko motivācijas sadalījumu (ne tikai D/I/V), lai dzinējs nāk no datiem.
    const driverKey = pickDriverKey(D, I, V, s.motivation);
    const driver = DRIVERS[driverKey];

    // Dinamiskais brīdinājums / atziņa — tuvākais arhetips D/I/V telpā (kopīgā pickWarnArchetype).
    const warn = pickWarnArchetype(D, I, V);

    // Top-3 tuvākie arhetipi (līdzība %) + galvenā iekšējā spriedze — jaunie attēlojumi zem radara.
    const MAXD = Math.sqrt(3) * 100;
    const ranked = WARN_ARCHETYPES
        .map(a => ({ a, sim: Math.max(0, Math.round(100 * (1 - Math.sqrt((D - a.d) ** 2 + (I - a.i) ** 2 + (V - a.v) ** 2) / MAXD))) }))
        .sort((x, y) => y.sim - x.sim)
        .slice(0, 3);
    const tension = keyTension(D, I, V);

    // Trijstūra aprēķini Radar Chart (mode-neatkarīgi skaitļi)
    const cx = 100, cy = 100, r = 80;
    const ang1 = -Math.PI / 2;
    const ang2 = ang1 + (2 * Math.PI / 3);
    const ang3 = ang2 + (2 * Math.PI / 3);

    const riskPx1 = cx + (r * 0.5) * Math.cos(ang1); const riskPy1 = cy + (r * 0.5) * Math.sin(ang1);
    const riskPx2 = cx + (r * 0.5) * Math.cos(ang2); const riskPy2 = cy + (r * 0.5) * Math.sin(ang2);
    const riskPx3 = cx + (r * 0.5) * Math.cos(ang3); const riskPy3 = cy + (r * 0.5) * Math.sin(ang3);
    const riskPolygon = `${riskPx1},${riskPy1} ${riskPx2},${riskPy2} ${riskPx3},${riskPy3}`;

    const px1 = cx + (r * (D / 100)) * Math.cos(ang1); const py1 = cy + (r * (D / 100)) * Math.sin(ang1);
    const px2 = cx + (r * (I / 100)) * Math.cos(ang2); const py2 = cy + (r * (I / 100)) * Math.sin(ang2);
    const px3 = cx + (r * (V / 100)) * Math.cos(ang3); const py3 = cy + (r * (V / 100)) * Math.sin(ang3);

    // ── SATURA RENDERĒTĀJS (m = 'self' | 'pro') ──────────────────────────────
    const buildContent = (m) => {
        const L = LBL[m];
        const d = dBand[m], i = iBand[m], v = vBand[m];
        const dr = driver[m], w = warn[m];
        const cross = crossDirective(I, V);
        // #5: self = mierīga ieskata kaste (violeta), pro = uzmanības kaste (dzintara — atziņa, ne trauksme).
        const ws = m === 'self'
            ? { bg: '#faf5ff', border: '#a855f7', head: '#7c3aed', title: '#6b21a8', text: '#4c1d95' }
            : { bg: '#fffbeb', border: '#f59e0b', head: '#b45309', title: '#92400e', text: '#78350f' };

        const belowRadar = `
            <div style="width:100%; max-width:320px; margin-top:-52px;">
                <div style="background:#fff; border:1px solid #f5d0fe; border-radius:8px; padding:14px; margin-bottom:0.9rem;">
                    <div style="font-size:0.74rem; font-weight:800; color:#86198f; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px;">🧬 Tuvākie arhetipi (sajaukums)</div>
                    ${ranked.map((rk, idx) => `
                        <div style="margin-bottom:${idx < 2 ? '9px' : '0'};">
                            <div style="display:flex; justify-content:space-between; font-size:0.83rem; margin-bottom:3px;">
                                <span style="color:#1e293b; font-weight:${idx === 0 ? '700' : '500'};">${idx + 1}. ${rk.a[m].title}</span>
                                <span style="color:#d946ef; font-weight:700;">${rk.sim}%</span>
                            </div>
                            <div style="height:7px; background:#f1f5f9; border-radius:4px; overflow:hidden;">
                                <div style="height:100%; width:${rk.sim}%; background:${idx === 0 ? '#d946ef' : '#e9a8f5'};"></div>
                            </div>
                        </div>`).join('')}
                    <div style="font-size:0.71rem; color:#94a3b8; margin-top:10px; line-height:1.4;">${m === 'self' ? 'Tavs profils nav viens tips — tas ir šo arhetipu sajaukums.' : 'Profils nav viens tips — tas ir šo arhetipu sajaukums.'}</div>
                </div>
                <div style="background:#fdf4ff; border:1px solid #f5d0fe; border-radius:8px; padding:14px;">
                    <div style="font-size:0.74rem; font-weight:800; color:#86198f; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">⚖️ ${tension.title}</div>
                    <div style="font-size:0.83rem; color:#475569; line-height:1.5;">${tension[m]}</div>
                </div>
            </div>`;

        const radar = `
            <div style="flex:1; min-width:300px; display:flex; flex-direction:column; align-items:center;">
                <div style="position: relative; width: 100%; max-width: 320px; height: 260px;">
                    <svg width="100%" height="100%" viewBox="-40 -40 280 280">
                        <polygon points="${cx},${cy - r} ${cx + r * Math.cos(ang2)},${cy + r * Math.sin(ang2)} ${cx + r * Math.cos(ang3)},${cy + r * Math.sin(ang3)}" fill="rgba(0,0,0,0.03)" stroke="#e2e8f0" stroke-width="1"/>
                        <polygon points="${cx},${cy - (r * 0.66)} ${cx + (r * 0.66) * Math.cos(ang2)},${cy + (r * 0.66) * Math.sin(ang2)} ${cx + (r * 0.66) * Math.cos(ang3)},${cy + (r * 0.66) * Math.sin(ang3)}" fill="none" stroke="#e2e8f0" stroke-width="1"/>
                        <polygon points="${cx},${cy - (r * 0.33)} ${cx + (r * 0.33) * Math.cos(ang2)},${cy + (r * 0.33) * Math.sin(ang2)} ${cx + (r * 0.33) * Math.cos(ang3)},${cy + (r * 0.33) * Math.sin(ang3)}" fill="none" stroke="#e2e8f0" stroke-width="1"/>
                        <line x1="${cx}" y1="${cy}" x2="${cx}" y2="${cy - r}" stroke="#e2e8f0" stroke-width="1"/>
                        <line x1="${cx}" y1="${cy}" x2="${cx + r * Math.cos(ang2)}" y2="${cy + r * Math.sin(ang2)}" stroke="#e2e8f0" stroke-width="1"/>
                        <line x1="${cx}" y1="${cy}" x2="${cx + r * Math.cos(ang3)}" y2="${cy + r * Math.sin(ang3)}" stroke="#e2e8f0" stroke-width="1"/>
                        <polygon points="${riskPolygon}" fill="none" stroke="#ef4444" stroke-width="1" stroke-dasharray="4 4" opacity="0.6"/>
                        <text x="${cx + 2}" y="${cy - (r * 0.5) + 10}" font-size="8" fill="#ef4444" opacity="0.6">50% bāze</text>
                        <polygon points="${px1},${py1} ${px2},${py2} ${px3},${py3}" fill="rgba(217, 70, 239, 0.2)" stroke="#d946ef" stroke-width="2"/>
                        <circle cx="${px1}" cy="${py1}" r="4" fill="#d946ef"/>
                        <circle cx="${px2}" cy="${py2}" r="4" fill="#d946ef"/>
                        <circle cx="${px3}" cy="${py3}" r="4" fill="#d946ef"/>
                        <text x="${cx}" y="${cy - r - 15}" text-anchor="middle" font-size="10" font-weight="bold" fill="#86198f">${L.radar[0]}</text>
                        <text x="${cx}" y="${cy - r - 3}" text-anchor="middle" font-size="11" font-weight="bold" fill="#d946ef">${D}%</text>
                        <text x="${cx + (r + 22) * Math.cos(ang2)}" y="${cy + (r + 22) * Math.sin(ang2)}" text-anchor="middle" font-size="10" font-weight="bold" fill="#86198f">${L.radar[1]}</text>
                        <text x="${cx + (r + 22) * Math.cos(ang2)}" y="${cy + (r + 22) * Math.sin(ang2) + 12}" text-anchor="middle" font-size="11" font-weight="bold" fill="#d946ef">${I}%</text>
                        <text x="${cx + (r + 22) * Math.cos(ang3)}" y="${cy + (r + 22) * Math.sin(ang3)}" text-anchor="middle" font-size="10" font-weight="bold" fill="#86198f">${L.radar[2]}</text>
                        <text x="${cx + (r + 22) * Math.cos(ang3)}" y="${cy + (r + 22) * Math.sin(ang3) + 12}" text-anchor="middle" font-size="11" font-weight="bold" fill="#d946ef">${V}%</text>
                    </svg>
                </div>
                ${belowRadar}
            </div>`;

        return `
            <div style="display:flex; flex-wrap:wrap; gap:2rem;">
                ${radar}
                <div style="flex:2; min-width:300px; display:flex; flex-direction:column; gap:1.5rem;">
                    <div style="background:${ws.bg}; border-left:4px solid ${ws.border}; border-radius:4px; padding:15px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                            <span style="font-size:1.5rem;">${L.warnIcon}</span>
                            <div>
                                <div style="font-size:0.75rem; color:${ws.head}; font-weight:bold; text-transform:uppercase; letter-spacing:0.05em;">${L.warnHeadTag}</div>
                                <div style="font-size:1.1rem; color:${ws.title}; font-weight:bold;">"${w.title}"</div>
                            </div>
                        </div>
                        <div style="font-size:0.95rem; color:${ws.text}; line-height:1.5;">${w.text}</div>
                        <div style="display:flex; gap:10px; margin-top:12px;">
                            <div style="flex:1; background:#f0fdf4; border:1px solid #bbf7d0; padding:12px; border-radius:4px;">
                                <div style="color:#166534; font-weight:bold; font-size:0.8rem; margin-bottom:6px;">${L.doLabel}</div>
                                <div style="color:#15803d; font-size:0.85rem; line-height:1.4;">${w.do}</div>
                            </div>
                            <div style="flex:1; background:#fffbeb; border:1px solid #fde68a; padding:12px; border-radius:4px;">
                                <div style="color:#92400e; font-weight:bold; font-size:0.8rem; margin-bottom:6px;">${L.dontLabel}</div>
                                <div style="color:#b45309; font-size:0.85rem; line-height:1.4;">${w.dont}</div>
                            </div>
                        </div>
                    </div>
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:15px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                        <div style="font-size:0.85rem; color:#64748b; font-weight:bold; text-transform:uppercase; margin-bottom:4px;">${L.driverTag}</div>
                        <div style="font-size:1.15rem; color:#1e293b; font-weight:bold; margin-bottom:10px;">${L.driverLead} <span style="color:#d946ef;">${dr.title}</span></div>
                        <div style="font-size:0.85rem; color:#475569; line-height:1.5; margin-bottom:8px;">
                            <b>${L.butiba}</b> ${dr.butiba}<br/>
                            <b>${L.valuta}</b> ${dr.valuta}<br/>
                            <span style="color:#94a3b8; font-size:0.75rem;">Astroloģiskais kods: ${driver.astro}</span>
                        </div>
                        <div style="background:#fdf4ff; border-left:3px solid #d946ef; padding:10px; font-size:0.85rem; color:#86198f; margin-bottom:8px; border-radius:0 4px 4px 0;">
                            <b>${L.rezTag}</b> "${dr.rez}"
                        </div>
                        <div style="font-size:0.85rem; color:#166534; background:#f0fdf4; padding:8px; border-radius:4px;">
                            <b>${L.balvaTag}</b> ${dr.balva}
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:2rem; background:#fff; border:1px solid #f5d0fe; border-radius:8px; padding:20px; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
                <h4 style="margin:0 0 20px 0; color:#86198f; font-size:1.15rem; border-bottom:1px solid #f5d0fe; padding-bottom:12px; display:flex; align-items:center; gap:8px;">
                    ${L.matrixHead}
                </h4>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:2rem;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div style="font-size:1rem; font-weight:bold; color:#1e293b;">${L.mD}</div>
                            <div style="background:#fdf4ff; color:#d946ef; padding:4px 10px; border-radius:20px; font-weight:bold; font-size:0.85rem; border:1px solid #f5d0fe;">${D}%</div>
                        </div>
                        <div style="font-size:0.95rem; font-weight:bold; color:#a21caf; margin-bottom:8px;">${L.mDtype} ${d.t}</div>
                        <div style="font-size:0.85rem; color:#475569; line-height:1.5; margin-bottom:10px;">${d.x}</div>
                        ${posBarHTML(D, AXIS_POLES.D, m)}
                        ${consHTML(s.leadership?.details)}
                        <div style="background:#f0fdf4; padding:8px 12px; border-left:3px solid #22c55e; font-size:0.8rem; color:#166534; margin-bottom:6px; line-height:1.4;"><b>${L.mDtr}</b> ${d.tr}</div>
                        <div style="background:#fffbeb; padding:8px 12px; border-left:3px solid #f59e0b; font-size:0.8rem; color:#92400e; line-height:1.4;"><b>${L.mDat}</b> ${d.at}</div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div style="font-size:1rem; font-weight:bold; color:#1e293b;">${L.mI}</div>
                            <div style="background:#fdf4ff; color:#d946ef; padding:4px 10px; border-radius:20px; font-weight:bold; font-size:0.85rem; border:1px solid #f5d0fe;">${I}%</div>
                        </div>
                        <div style="font-size:0.95rem; font-weight:bold; color:#a21caf; margin-bottom:8px;">${L.mItype} ${i.t}</div>
                        <div style="font-size:0.85rem; color:#475569; line-height:1.5; margin-bottom:10px;">${i.x}</div>
                        ${posBarHTML(I, AXIS_POLES.I, m)}
                        ${consHTML(s.performance?.details)}
                        <div style="background:#f0fdf4; padding:8px 12px; border-left:3px solid #22c55e; font-size:0.8rem; color:#166534; margin-bottom:6px; line-height:1.4;"><b>${L.mItak}</b> ${i.tak}</div>
                        <div style="background:#fffbeb; padding:8px 12px; border-left:3px solid #f59e0b; font-size:0.8rem; color:#92400e; line-height:1.4;"><b>${L.mIklu}</b> ${i.klu}</div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div style="font-size:1rem; font-weight:bold; color:#1e293b;">${L.mV}</div>
                            <div style="background:#fdf4ff; color:#d946ef; padding:4px 10px; border-radius:20px; font-weight:bold; font-size:0.85rem; border:1px solid #f5d0fe;">${V}%</div>
                        </div>
                        <div style="font-size:0.95rem; font-weight:bold; color:#a21caf; margin-bottom:8px;">${L.mVtype} ${v.t}</div>
                        <div style="font-size:0.85rem; color:#475569; line-height:1.5; margin-bottom:10px;">${v.x}</div>
                        ${posBarHTML(V, AXIS_POLES.V, m)}
                        ${consHTML(s.risks?.details)}
                        <div style="background:#f0fdf4; padding:8px 12px; border-left:3px solid #22c55e; font-size:0.8rem; color:#166534; margin-bottom:6px; line-height:1.4;"><b>${L.mVst}</b> ${v.st}</div>
                        <div style="background:#fffbeb; padding:8px 12px; border-left:3px solid #f59e0b; font-size:0.8rem; color:#92400e; line-height:1.4;"><b>${L.mVen}</b> ${v.en}</div>
                    </div>
                </div>
                ${cross ? `<div style="margin-top:1.2rem; background:#fdf4ff; border:1px solid #f5d0fe; border-left:3px solid #d946ef; border-radius:0 8px 8px 0; padding:12px 16px;">
                    <div style="font-size:0.78rem; font-weight:800; color:#86198f; margin-bottom:4px;">${cross.title}</div>
                    <div style="font-size:0.85rem; color:#475569; line-height:1.5;">${cross[m]}</div>
                </div>` : ''}
            </div>`;
    };

    const btn = (mode, label) => {
        const on = mode === 'pro';
        return `<button class="hk-mode-btn" data-mode-btn="${mode}" onclick="window.__hkSetMode(this,'${mode}')"
            style="border:1px solid #e9d5ff; border-radius:8px; padding:8px 18px; font-size:0.9rem; font-weight:700; cursor:pointer; transition:all 0.15s;
            background:${on ? '#a21caf' : '#fff'}; color:${on ? '#fff' : '#86198f'}; box-shadow:${on ? '0 2px 6px rgba(162,28,175,0.3)' : 'none'};">${label}</button>`;
    };

    return `
        <div class="panel hk-panel" style="grid-column: 1 / -1; border-left: 4px solid #d946ef; background: #fdf4ff;">
            <h3 class="panel-title" style="color:#a21caf; display:flex; align-items:center; gap:0.5rem;">
                <span style="font-size:1.2rem;">🕹️</span> Motivācija un vadības sviras
            </h3>

            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:0.25rem 0 1rem 0;">
                <span style="font-size:0.78rem; color:#86198f; font-weight:600; margin-right:4px;">Skatījums:</span>
                ${btn('pro', 'HR & Profesionāļiem')}
                ${btn('self', 'Pašizpēte')}
            </div>

            <div data-hk-mode="pro">
                <p style="font-size:0.9rem; color:#86198f; margin:-0.25rem 0 1.5rem 0;">${LBL.pro.subtitle}</p>
                ${buildContent('pro')}
            </div>
            <div data-hk-mode="self" style="display:none;">
                <p style="font-size:0.9rem; color:#86198f; margin:-0.25rem 0 1.5rem 0;">${LBL.self.subtitle}</p>
                ${buildContent('self')}
            </div>
        </div>
    `;
}
