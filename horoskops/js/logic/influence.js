// Ietekmēšanas metodes — 4 moduļi, 73 dimensiju apakškopa
// T(pct, b0..b8) — 9 joslas pa 10%, pct 5→95

export function buildInfluenceReport(personality) {
    const p = {};
    for (const cat of personality) {
        for (const t of cat.traits) p[t.id] = t.pct;
    }
    const g = id => {
        if (p[id] === undefined) console.warn(`[influence] trūkst personības dimensijas "${id}" — lietoju noklusējumu 50`);
        return p[id] ?? 50;
    };
    const T = (pct, ...b) => b[Math.min(Math.floor((Math.max(pct, 5) - 5) / 10), b.length - 1)];

    // ── 1. PĀRLIECINĀŠANAS ATSLĒGA ───────────────────────────────────────────
    const risk  = g('risk');
    const anlt  = g('analytical');
    const optm  = g('optimism');
    const agr   = g('agreeableness');

    const s1 = {
        id: 'persuasion',
        title: 'Pārliecināšanas atslēga',
        subtitle: 'Kas viņu pārliecina',
        color: '#0ea5e9',
        colorText: '#0369a1',
        summary: T(anlt,
            'Pārliecina sajūtas un vīzija, ne dati — sāc ar stāstu, ne skaitļiem.',
            'Galvenais ir emocijas un personiskā saikne — skaitļi tikai kā fons.',
            'Intuīcija sver vairāk par datiem — uzrunā sajūtu, pievieno dažus faktus.',
            'Daži skaidri fakti plus stāsts; nepārslogo ar datiem.',
            'Līdzsvaro stāstu un faktus — abi vienlīdz svarīgi.',
            'Strukturēts loģisks arguments pārliecina; emocijas kā papildinājums.',
            'Prasa skaidru loģisko ķēdi — intuīcija bez datiem netiek pieņemta.',
            'Vajag pilnu faktu pamatojumu un skaitļus, pirms viņš uzticas.',
            'Prasa precīzus datus, avotus un loģiku — gatavojies kā uz eksāmenu.'
        ),
        items: [
            { label: 'Riska attieksme', text: T(risk,
                'Izvairās no nezināmā — vajag absolūtas garantijas un pierādītus precedentus.',
                'Ļoti augsta piesardzība — ieklausās tikai ar pārbaudītu rezultātu un drošu izeju.',
                'Lemj tikai uz stingra drošības pamata — dati un precedenti sver vairāk par vīziju.',
                'Piesardzīgs — grib minimālu risku un maksimālas drošuma garantijas.',
                'Risku vērtē sabalansēti — drošība un iespēja vienlīdz svarīgas.',
                'Saprātīgs risks nebaida, ja loģika pamato — rādi riska/ieguvuma aprēķinu.',
                'Iespēja interesē vairāk par drošības tīklu — labi formulēta vīzija atver durvis.',
                'Augsta riska apetīte — drosmīgs, inovatīvs piedāvājums aizrauj vairāk par garantijām.',
                'Risku mīlētājs — jo lielāks izaicinājums, jo pievilcīgāk; drošs piedāvājums atgrūž.'
            ) },
            { label: 'Kā argumentēt', type: 'dodont',
                do: T(anlt,
                    'sākt ar stāstu un vīziju, emocionāli iesaistot.',
                    'likt uzsvaru uz emocijām un personisko saikni.',
                    'uzrunāt sajūtu pirmkārt, faktus pievienot kā atbalstu.',
                    'dot dažus skaidrus faktus un stāstu kopā.',
                    'apvienot stāstu ar faktiem vienlīdzīgi.',
                    'veidot strukturētu loģisku argumentu soli pa solim.',
                    'sagatavot skaidru loģisko ķēdi ar pamatojumu katrā posmā.',
                    'sagatavot pilnu faktu bāzi, salīdzinājumus un skaitļus.',
                    'atnest precīzus datus, avotus un loģiku katram apgalvojumam.'
                ),
                dont: T(anlt,
                    'sākt ar skaitļiem un tabulām — tie viņu atgrūž.',
                    'paļauties tikai uz statistiku.',
                    'pārslogot ar datu lapām.',
                    'pārslogot ar pārāk daudz datiem uzreiz.',
                    'izlaist stāstu vai faktus — vajag abus.',
                    'paļauties tikai uz emocijām bez loģikas.',
                    'pārliecināt ar intuīciju bez pierādījumiem.',
                    'dot tukšus apgalvojumus bez datiem.',
                    'nākt nesagatavotam vai ar aptuveniem skaitļiem.'
                )
            },
            { label: 'Pirmā reakcija', text: T(optm,
                'Pirmais instinkts — meklēt draudus: "kur ir āķis?"',
                'Ļoti piesardzīgs — pārliecina arguments, kas rāda, ko zaudēs nepiekrītot.',
                'Pirmais jautājums "kas var noiet greizi?" — atbildi uzreiz.',
                'Vispirms kliedē bažas, tikai tad runā par pozitīvo.',
                'Neitrāls — labākais arguments ietver gan riskus, gan iespējas.',
                'Vidēji optimistisks — pozitīvais strādā, bet riskus nenoklusē.',
                'Dabiski saskata iespējas — saruna par izaugsmi viņu aizrauj.',
                'Spilgts optimists — augšupejas stāsts atver vairāk nekā faktu saraksts.',
                'Vīzionārs — lielas, drosmīgas idejas ir dzinulis; lielā bilde pietiek.'
            ) },
            { label: 'Uzticēšanās', text: T(agr,
                'Uzticēšanās veidojas ļoti lēni — tikai ar pildītiem solījumiem, ne argumentiem.',
                'Kritiski neatkarīgs — uz gandrīz visu sniedz pretargumentus.',
                'Augsts skepticisms — spiediens uz ātru lēmumu tikai vairo pretestību.',
                'Uzticēšanās nāk pakāpeniski — vajag laiku un telpu šaubām.',
                'Sabalansēts — godīgs, stabils dialogs darbojas vislabāk.',
                'Daļēji pretimnākošs — pareizi argumenti un cieņa padara receptīvu.',
                'Kooperatīvs — meklē veidu, kā piekrist; loģisks pamatojums pietiek.',
                'Ļoti pretimnākošs — sadarbojas labprāt, ja piedāvājums nav kaitīgs.',
                'Var piekrist pārāk viegli — pārliecinies, ka piedāvājums viņam tiešām kalpo.'
            ) },
        ]
    };

    // ── 2. RĪCĪBAS PALAIDĒJS ─────────────────────────────────────────────────
    const init     = g('initiative');
    const perf     = g('perfectionism');
    const apc      = Math.round((g('ambition') + g('perseverance') + g('conscient')) / 3);
    const lrn      = g('learning');
    // lrnStyle: learning speed (40%) + philosophy focus (35%) + traditional structure (25%)
    // → reflects learning STYLE (practice-first vs theory/system-first), not just speed
    const lrnStyle = Math.round(lrn * 0.4 + g('h9philosophy') * 0.35 + g('traditional') * 0.25);
    // perfAuth: personal standard (55%) + directiveness toward others (45%)
    // → reflects BOTH internal quality bar and how demanding the person is with others
    const perfAuth = Math.round(perf * 0.55 + g('authority') * 0.45);

    // Variants (a): "Īsumā" parasti = T(init), BET ja dzinulis (apc) un starts (init) krasi atšķiras
    // (|init−apc| > 25), neatbilstība pati ir vērtīgākā HR informācija → atsevišķs spriedzes teksts.
    // Citādi viena dimensija nezina par otru un teksti var izklausīties pretrunīgi.
    const actionSummary =
        (init - apc < -25) ? 'Spēcīgs dzinulis, kūtrs starts — dod konkrētu pirmo soli un termiņu, un tālāk vairs neuzraugi.'
      : (init - apc >  25) ? 'Viegli uzsāk, bet grūti notur tempu — dod jēgu un atzinību, ne kontroli pie starta.'
      : T(init,
            'Dod konkrētu uzdevumu, termiņu un regulāru uzraudzību — pats nesāks.',
            'Dod skaidru pirmo soli un termiņu, tad pārbaudi gaitā — citādi apstāsies.',
            'Dod skaidru pirmo soli un termiņu; tālāk viņš tiks galā.',
            'Dod konkrētu pirmo soli un termiņu — pārējo izdarīs pats.',
            'Dod virzienu un palīdzi ar startu — tālāk strādā labi.',
            'Dod mērķi un periodisku atskaiti — pārējo paveic pats.',
            'Dod mērķi un netraucē — patstāvīgs no starta līdz finišam.',
            'Dod brīvību un uzraugi tikai rezultātu — process viņam pieder.',
            'Nosaki tikai rezultātu — jo mazāk vadi, jo labāk strādā.'
        );

    const s2 = {
        id: 'action',
        title: 'Rīcības palaidējs',
        subtitle: 'Kā palīdzēt sākt',
        color: '#10b981',
        colorText: '#047857',
        summary: actionSummary,
        items: [
            { label: 'Ikdienas pašmotivācija', text: T(apc,  // pārsaukts 2026-06-11: 'Iekšējais dzinulis' dublējās ar hakera paneļa D asi (cits konstrukts)
                'Ļoti zems — uzdevums jāpadara maksimāli viegls ar tūlītēju, taustāmu ieguvumu, citādi tas netiek pabeigts.',
                'Zems — biežas mazas "uzvaras" uztur tempu, bet liels uzdevums bez atzinības ātri noplok.',
                'Vidēji zems — sadaliet uzdevumu mazos soļos ar atgriezenisko saiti katrā posmā.',
                'Mērens, bet ar nosacījumu — vajadzīgs skaidrs "kāpēc tas svarīgi", pirms viņš ķeras klāt.',
                'Vidējs — regulāra atgriezeniskā saite uztur tempu un iesaisti.',
                'Augsts — necieš haosu; kad mērķis ir skaidrs, strādā mērķtiecīgi un izturīgi pats.',
                'Augsta darba ētika — sarežģīti uzdevumi motivē, bet vienkāršs darbs garlaiko un noplok.',
                'Ļoti ambiciozs un neatlaidīgs — izaicinājuma trūkums demotivē vairāk nekā pats grūtums.',
                'Ārkārtīgi augsts — strādās bez jebkāda ārēja stimula, kamēr mērķis nav sasniegts.'
            ) },
            { label: 'Kā vadīt', type: 'dodont',
                do: T(init,
                    'sadalīt darbu posmos ar kontrolpunktu pie katra un sākt kopā ar viņu.',
                    'dot pirmo soli, termiņu un kontrolpunktu pie starta.',
                    'norādīt skaidru pirmo soli un vienoties par starta termiņu.',
                    'dot precīzu pirmo soli un termiņu; kontrolpunkts pie starta, tālāk uzticēties.',
                    'palīdzēt iesākt, tad ļaut rīkoties ar skaidru virzienu.',
                    'dot mērķi un vienoties par periodisku atskaiti.',
                    'dot mērķi un atkāpties, uzticoties procesam.',
                    'dot brīvību procesā un vienoties tikai par rezultātu un termiņu.',
                    'noteikt gala rezultātu un netraucēt procesam.'
                ),
                dont: T(init,
                    'gaidīt pašiniciatīvu vai atstāt bez uzraudzības.',
                    'dot tikai galamērķi bez pirmā soļa un termiņa.',
                    'atstāt uzdevumu neskaidru — nenoteiktība viņu iesaldē.',
                    'dot abstraktus mērķus vai kontrolēt katru soli pēc starta.',
                    'gaidīt ātru startu bez sākotnējā stūmiena.',
                    'mikrovadīt — periodiska atskaite pietiek.',
                    'mikrovadīt vai uzraudzīt katru detaļu.',
                    'uzspiest procesu vai stingrus soļus — tas demotivē.',
                    'vadīt procesu — tas dzēš motivāciju.'
                )
            },
            { label: 'Darba ētika', text: T(perfAuth,
                'Praktiski nav standarta — pieņem jebkādu izpildi, termiņus var saspiest maksimāli.',
                'Ļoti zems standarts — ātrums dominē pār kvalitāti, no citiem neko konkrētu neprasa.',
                'Zems perfekcionisms — detaļas nerada stresu, komandai neliek uztraukties par sīkumiem.',
                'Pragmatisks — pats pieņem "pietiekami labu", no komandas sagaida mērenu atbildību.',
                'Taisnīgs balanss — sniedz augstu kvalitāti un sagaida to no citiem, neprasot vairāk, nekā dod pats.',
                'Asimetrisks — pats pragmatisks, pret citiem direktīvs: sagaida pareizu un savlaicīgu izpildi.',
                'Augsti standarti + direktīva pieeja — nesamierinās ar vāju izpildi; termiņiem jābūt reālistiskiem.',
                'Ļoti augsts perfekcionisms — vāju izpildi uztver kā cieņas trūkumu; ātrums bez kvalitātes rada iekšēju konfliktu.',
                'Ekstrēmi prasīgs pret visiem — steidzināšana bez kvalitātes pamata izraisa pretestību vai iesaldēšanos.'
            ) },
            { label: 'Apmācība', text: T(lrnStyle,
                'Mācās tikai darot — teorija neiedvesmo; labākā instrukcija ir iespēja rīkoties pašam un kļūdīties praksē.',
                'Izteikti prakse-pirmā — piemēri un izmēģinājums noder vairāk par principiem; teoriju pievienojiet vēlāk.',
                'Mācās darot — konkrēts pirmais solis viņu palaiž, teorija der kā fons, ne priekšnoteikums.',
                'Gara teorija nogalina interesi — praktiska 5 minūšu demonstrācija palaiž uzreiz: parādiet, kā tas strādā, un tikai tad sniedziet dziļāku kontekstu.',
                'Sabalansēts — teorija un prakse kopā darbojas vislabāk, nevienu neizlaidiet.',
                'Strukturēti materiāli uztverami labi — sistēmas izpratne ir svarīga pirms rīcības uzsākšanas.',
                'Teorētiskais pamats pirms darbības obligāts — improvizācija bez sagatavošanās rada stresu.',
                'Izteikts sistēmu domātājs — vispirms izprot struktūru, tad rīkojas; bez bāzes jūtas nedrošs un kavē sākumu.',
                'Ārkārtīgi teorijas orientēts — vajag pilnu sistēmas izpratni pirms jebkādas rīcības; "izmēģini un redzēsi" ir kontrproduktīvi.'
            ) },
        ]
    };

    // ── 3. AIZSARGMŪRA PĀRVARĒŠANA ───────────────────────────────────────────
    const mask  = g('persona');
    const cflS  = g('conflictstyle');
    const expr  = g('expressiveness');
    const strS  = g('fightresponse');

    const s3 = {
        id: 'defense',
        title: 'Kā veidot uzticību',
        subtitle: 'Droša un atklāta saruna',
        color: '#8b5cf6',
        colorText: '#6d28d9',
        summary: T(cflS,
            'Kritiku pasniedz ļoti maigi un privāti — tieša konfrontācija viņu aizver.',
            'Lieto "sendviča" pieeju — tieša kritika bez mīkstinājuma neizdosies.',
            'Runā par situāciju, ne personu, un vienmēr piedāvā izeju.',
            'Kritizē privāti un saglabā cieņu — tad viņš uzklausa.',
            'Tieša kritika der mierīgā tonī, sagatavotā vidē.',
            'Tieša, cieņpilna saruna ir pieņemama — bez liela protokola.',
            'Dod tiešu atgriezenisko saiti uzreiz — viņš to sagaida.',
            'Runā atklāti pat grūtās tēmās — viņš to novērtē.',
            'Esi pilnīgi tiešs — izvairīšanās kaitina vairāk par kritiku.'
        ),
        items: [
            { label: 'Atvērtība', text: T(mask,
                'Pilnīgi autentisks — nav sociālo slāņu; tieša, sirsnīga komunikācija darbojas uzreiz.',
                'Ļoti plāna maska — patiesos motīvus atklāj ātri un viegli.',
                'Zema aizsardzība — vienkārša sirsnība atver durvis.',
                'Daļēji veidota aizsardzība — uzticēšanās nāk samērā ātri ar konsekvenci.',
                'Vidēja maska — viens-divi sekmīgi kontakti ved pie patiesajiem motīviem.',
                'Vidēji aizsargāts — publiskais "es" nav privātais; vajag laiku.',
                'Augsta maska — rūpīgi veidota fasāde; dziļāk tikai ar ilgtermiņa uzticamību.',
                'Biezs aizsargmūris — pie motīviem ātri netiksi; vienīgais ceļš ir konsekvence.',
                'Ekstrēmi augsta maska — publiski un privāti ļoti atšķiras; lēmumi aizkulisēs.'
            ) },
            { label: 'Kā sniegt kritiku', type: 'dodont',
                do: T(cflS,
                    'izteikt kritiku ļoti maigi un privāti, vai caur trešo pusi.',
                    'lietot "sendviča" pieeju: pozitīvs, tad kritika, tad pozitīvs.',
                    'runāt par situāciju, ne personu, un piedāvāt izejas ceļu.',
                    'sniegt kritiku privāti un saglabāt viņa cieņu.',
                    'sniegt tiešu kritiku mierīgā tonī, sagatavotā vidē.',
                    'komunicēt tieši un cieņpilni — tas ir pieņemami.',
                    'dot tiešu atgriezenisko saiti bez liela protokola.',
                    'runāt atklāti pat grūtās tēmās — viņš to novērtē.',
                    'būt pilnīgi tiešam — skaidrs viedoklis viņam patīk labāk par izvairīšanos.'
                ),
                dont: T(cflS,
                    'kritizēt tieši vai publiski — viņš atkāpsies.',
                    'sākt ar tiešu kritiku bez mīkstinājuma.',
                    'pāriet uz personību vai atstāt kritiku bez risinājuma.',
                    'kritizēt citu klātbūtnē.',
                    'runāt paaugstinātā vai uzbrūkošā tonī.',
                    'slēpt problēmu aiz mājieniem.',
                    'aptīt vēstījumu liekā protokolā un mīkstināšanā.',
                    'apiet tēmu — tiešums der labāk.',
                    'izvairīties vai runāt neskaidri — tas kaitina vairāk par kritiku.'
                )
            },
            { label: 'Komunikācijas stils', text: T(expr,
                'Ļoti rezervēts — runā mājienos; lasi starp rindām.',
                'Minimālists — bieži klusē vai saka pusformulu; precizē teikto.',
                'Gari klusumi ir normāli — tūlītējas atbildes prasīšana slēdz durvis.',
                'Atveras viens-pret-vienu vai konfidenciālā vidē.',
                'Atklāsies ar laiku — aktīva klausīšanās svarīgāka par runāšanu.',
                'Saprot tiešu komunikāciju, bet izsakās selektīvi.',
                'Atvērts dialogam — patīk skaidri izteikties.',
                'Patīk runāt un debatēt — tas viņu enerģizē.',
                'Ļoti tiešs — pateiks visu, ko domā; tas ir raksturs, ne uzbrukums.'
            ) },
            { label: 'Zem spiediena', text: T(strS,
                'Spiedienā aizstingst — vajag drošu vidi svarīgai sarunai.',
                'Stresā rāda tikai aizsardzību, ne patiesos motīvus.',
                'Augsts bēgšanas risks — nesāc svarīgu sarunu stresa brīdī.',
                'Svarīgus lēmumus atliec uz laiku pēc spiediena.',
                'Strādā saprātīgā spiedienā, bet pārslodzē ieiet aizsardzībā.',
                'Mērens spiediens aktivizē, pārslodze nobīda uz aizsardzību.',
                'Laba stresa tolerance — konflikts netraumē.',
                'Konfrontācija mobilizē — izaicinājums kā motivācijas svira.',
                'Spiediens aktivizē viņa maksimālo kapacitāti.'
            ) },
        ]
    };

    // ── 4. ĪSTĀ BRĪŽA TRĀPĪJUMS ──────────────────────────────────────────────
    const vit   = g('vitality');
    const res   = g('resilience');
    const circ  = g('circadian');

    const s4 = {
        id: 'timing',
        title: 'Īstais brīdis',
        subtitle: 'Kad uzrunāt',
        color: '#f59e0b',
        colorText: '#b45309',
        summary: T(circ,
            'Plāno svarīgas sarunas tikai pēcpusdienā vai vakarā — rīts ir izšķiests.',
            'Produktīvais logs ir pēcpusdiena un vakars; pirms 11:00 nesāc nopietni.',
            'Rīts ir iesildīšanās — svarīgo plāno pēcpusdienā.',
            'Labākā forma no pusdienas uz priekšu; rītā nesteidzini lēmumus.',
            'Bez izteiktas preferences — dienas vidus darbojas labi.',
            'Dienas pirmā puse ražīgāka — tikšanās pirms pusdienām der labi.',
            'Rīts ir produktīvākais — svarīgo plāno pirms pusdienlaika.',
            'Maksimāla atvērtība rītos; vakarā riskē ar "runāsim rīt".',
            'Plāno agri no rīta — vakarā atvērtība strauji krīt.'
        ),
        items: [
            { label: 'Enerģija sanāksmēs', text: T(vit,
                'Delikāts enerģijas budžets — tikai īsas, fokusētas tikšanās (max 30 min).',
                'Ļoti zema — viens galvenais mērķis uz tikšanos.',
                'Zema — īsa tikšanās, viens jautājums vienā reizē.',
                'Vidēji zema — 60–90 min ir maksimums bez efektivitātes krituma.',
                'Vidēja — standarta garuma tikšanās der labi.',
                'Laba — var iesaistīties vairākās tēmās vienā reizē.',
                'Augsta — garākas, intensīvas tikšanās ir pieejamas.',
                'Ļoti augsta — maratona sesijas der, augsts temps neizsmej.',
                'Ārkārtīgi augsta — gara intensīva sesija stimulē vairāk par īsu.'
            ) },
            { label: 'Kad plānot', type: 'dodont',
                do: T(circ,
                    'plānot pēcpusdienā vai vakarā.',
                    'rīkot tikšanos pēc pulksten 11, ideāli pēcpusdienā vai vakarā.',
                    'likt svarīgo pēcpusdienā.',
                    'plānot no pusdienlaika uz priekšu.',
                    'likt tikšanos dienas vidusdaļā.',
                    'plānot pirms pusdienām.',
                    'likt svarīgās sarunas rītā, pirms pusdienlaika.',
                    'izmantot rīta logu maksimālai atvērtībai.',
                    'plānot agru rītu — tas ir viņa labākais brīdis.'
                ),
                dont: T(circ,
                    'rīkot rīta tikšanās svarīgām tēmām.',
                    'sākt nozīmīgu dialogu pirms pulksten 11.',
                    'gaidīt rezultātu no rīta sarunas.',
                    'steidzināt lēmumus agros rītos.',
                    'plānot galējos rītos vai vēlos vakaros.',
                    'atlikt svarīgo uz vēlu vakaru.',
                    'likt izšķirošo sarunu vēlā pēcpusdienā.',
                    'gaidīt vakarā galīgu lēmumu — viņš atliks.',
                    'rīkot vakara tikšanās — atvērtība būs zema.'
                )
            },
            { label: 'Slodze vienā reizē', text: T(res,
                'Trausla — vienā reizē tikai viena viegla tēma; saspringtu saturu sadali.',
                'Zema — grūtas tēmas pa vienai, mierīgā tempā.',
                'Zemāka — viena sarežģīta tēma vienā reizē.',
                'Vidēji zema — mērena slodze der, ilgstoša spriedze nē.',
                'Vidēja — standarta slodzi iztur, ļoti saspringtu saturu sadali.',
                'Laba — iztur arī grūtas, blīvas sarunas.',
                'Augsta — vairākas sarežģītas tēmas vienā reizē ir pieņemami.',
                'Ļoti augsta — blīvs, izaicinošs saturs viņu mobilizē.',
                'Ārkārtīgi augsta — jo blīvāka un grūtāka saruna, jo labāk fokusējas.'
            ) },
        ]
    };

    return [s1, s2, s3, s4];
}
