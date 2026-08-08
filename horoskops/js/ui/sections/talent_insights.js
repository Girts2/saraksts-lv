// Talantu secinājumu dzinējs — deterministisks naratīvs no aprēķinātajiem talantu datiem.
// 4 slāņi: (1) sistēmu balsis · (2) metriku nozīmes · (3) kombināciju likumi · (4) 2 izvades lēcas.
// Ievaddati (sig): { confirmed:[{key,label,pct,strong}], latent:[{key,label,pct,peak,delta,topSys,tier}],
//                    diamonds:[...], isDiamondProfile:bool, raw:[{metric,name,element,score,sys}],
//                    metricPct:{leadership,stress,teamwork,performance,risks,motivation} }

// ── 1. Sistēmu balsis: KĀPĒC signāls no katras tradīcijas kaut ko nozīmē ──
// SYSTEM_VOICE dod tikai AVOTU ("kas mēra ..."), NE nozīmi — nozīme nāk no metrikas (sk. METRIC_LATENT.meaning)
const SYSTEM_VOICE = {
    'Vēdiskā': { measures: 'dzīves uzdevumu (Dharmu) un karmisko kapacitāti' },
    'Maiju':   { measures: 'dziļos bioloģiskos un izdzīvošanas ritmus' },
    'Rietumu': { measures: 'emocionālo un attiecību slāni' },
    'BaZi':    { measures: 'iekšējo enerģijas kodolu' },
    'Tatva':   { measures: 'ķermeņa stihiju un instinktus' },
};

// ── 2. Metriku nozīmes: ikdienas seja + latentais arhetips + ko šī latentā spēja NOZĪMĒ ──
const METRIC_LATENT = {
    leadership:  { archetype: 'Negribīgais līderis',       everyday: 'apzināti izvairās no varas pozīcijām un titulu medīšanas', meaning: 'Tā ir spēja vadīt un strukturēt, kas pamostas tieši tad, kad situācija to pieprasa.' },
    stress:      { archetype: 'Krīzes rezerve',            everyday: 'var šķist tikai vidēji noturīgs vai pat trauksmains',        meaning: 'Tā ir dziļa noturība, kas izpaužas nevis ikdienā, bet patiesā krīzē.' },
    teamwork:    { archetype: 'Klusais savienotājs',       everyday: 'reti izceļas kā komandas redzamais dzinējspēks',            meaning: 'Tā ir spēja saliedēt un savienot cilvēkus, kad tai tiek dota telpa.' },
    performance: { archetype: 'Neizmantotais izpildītājs', everyday: 'kārto uzdevumus, bet bez izteiktas pabeigšanas reputācijas', meaning: 'Tā ir spēja novest darbu līdz galam, kad ir skaidrs mērķis un piemērota vide.' },
    motivation:  { archetype: 'Klusā dzinme',              everyday: 'no malas var likties bez spēcīgas ambīcijas',               meaning: 'Tā ir iekšēja dzinme, kas atraisās tieši pareizajā jomā.' },
};

export function renderTalentInsightsHtml(sig) {
    const C = sig.confirmed || [], Lt = sig.latent || [];
    const hasC = k => C.some(t => t.key === k);
    const hasL = k => Lt.some(t => t.key === k);
    const getL = k => Lt.find(t => t.key === k);
    const P = k => Math.round(sig.metricPct?.[k] ?? 50);
    const sysPct = t => Math.round(t.peak != null ? t.peak : (t.topScore || 0) * 10);

    const latentList = () => Lt.map(t => `<b>${t.label}</b> (${t.topSys || 'kāda sistēma'} redz ${sysPct(t)}%, kaut ikdienā tikai ${t.pct}%)`).join('; ');

    // ── 3. Kombināciju likumi (prioritārā secībā — pirmais sakritušais kļūst par galveno portretu) ──
    const RULES = [
        {
            when: () => sig.isDiamondProfile,
            title: 'Neapstrādātais dimants — vēl nesaskaņots',
            desc: () => `Šis ir rets profils, kurā milzīga iekšējā kapacitāte sadzīvo ar zemu ikdienas izpausmi. Katra no spēcīgākajām dotībām sakņojas atšķirīgā tradīcijā (${[...new Set((sig.diamonds || []).map(d => d.topSys))].join(', ')}), un tās vēl nav savstarpēji saskaņojušās. Tāpēc no malas cilvēks var šķist nekonsekvents — vienā jomā spožs, citā negaidīti bezpalīdzīgs. Tas nav spēju trūkums, bet integrācijas trūkums: iekšējie dzinēji pagaidām darbojas izolēti. Kad tie sāks “sarunāties” savā starpā, šis var kļūt par vienu no spēcīgākajiem profiliem — taču tas prasa pareizo vidi un laiku, ne spiedienu.`,
            self: () => `Tevī mīt ievērojama, vēl neizmantota kapacitāte (${(sig.diamonds || []).length} jomās), bet tā pagaidām nav saskaņojusies vienotā veselumā. Tāpēc reizēm jūties spožs, citreiz apjucis — tas ir normāli šādam profilam. Neprasi no sevis uzreiz visu: izvēlies vienu spēcīgāko jomu un dod tai laiku un drošu vidi nobriest. Konsekvence nāks, kad daļas sāks strādāt kopā.`,
            hr: () => `Augsta riska, augstas atdeves profils. Standarta novērtējumā viņš šķitīs nekonsekvents, un lineārā rutīnā viņš izdegs, neparādot savu patieso potenciālu. Nepieciešams mentors, ne priekšnieks: grūti, jēgpilni uzdevumi ar reālu autonomiju. Viņa spējas “pamostas”, kad jāuzņemas tieša atbildība par rezultātu.`,
        },
        {
            when: () => hasL('leadership') && (hasC('teamwork') || P('teamwork') >= 65),
            title: 'Negribīgais līderis ar komandas saknēm',
            desc: () => `Šis ir cilvēks, kura patiesā vadības spēja ir paslēpta zem spēcīgas piederības komandai. Ikdienā viņš neuzbāžas un apzināti nemeklē varu vai titulus (redzamā līderības bāze tikai ${P('leadership')}%) — un tieši tāpēc apkārtējie viņam uzticas. Taču zem virsmas mīt nopietna iekšējā autoritāte: ${getL('leadership').topSys} sistēma to fiksē ${sysPct(getL('leadership'))}% līmenī. Viņš nevada caur spiedienu vai pozīciju, bet caur uzticamību, piemēru un rūpēm par grupu. Tā vadība atveras tieši tad, kad komandai vajadzīgs kāds, kam sekot — un tā nāk no pienākuma sajūtas, ne no ambīcijas. Organizācijā tas ir rets un vērtīgs tips: līderis, kuru cilvēki izvirza paši, ne tāds, kas sevi uzspiež.`,
            self: () => `Tevī mīt līdera potenciāls (${getL('leadership').topSys} rāda ${sysPct(getL('leadership'))}%), ko ikdienā neizmanto (redzamā bāze tikai ${P('leadership')}%). Iespējams, tu domā, ka līderība nozīmē būt agresīvam vai valdošam. Tavs ceļš uz statusu ir caur uzticamību — atļauj sev uzņemties iniciatīvu nevis sevis, bet savas komandas dēļ.`,
            hr: () => `Nespiediet šo cilvēku klasiskā frontālā vadības vai agresīvā pārdošanas pozīcijā — tas viņu demotivēs. Taču, ja vajag “pelēko kardinālu” vai projektu vadītāju, kas satur komandu kopā caur ilgstošu krīzi, šim profilam ir neizsmeltas slēptās rezerves. Vadība viņam jāuztic, ne jāliek to pieprasīt.`,
        },
        {
            when: () => hasL('stress') && (hasC('performance') || P('performance') >= 65),
            title: 'Slēptā krīzes rezerve',
            desc: () => `Šis cilvēks ir uzticams izpildītājs ar negaidīti dziļu noturības rezervi. Stabilā ikdienā viņš var šķist tikai vidēji izturīgs — pat jutīgs vai trauksmains (redzamais rādītājs ${P('stress')}%) —, tāpēc viņu viegli novērtēt par zemu. Taču patiesā, eksistenciālā krīzē, kad šķietami stiprie sabrūk, viņa dziļākais slānis ieslēdzas pilnā spēkā: ${getL('stress').topSys}, kas mēra cilvēka pamatdziņas, fiksē ${sysPct(getL('stress'))}%. Viņa spēks nav demonstratīvs, bet balstās rezervju ekonomikā — viņš netērē enerģiju ikdienas sīkumos, lai tā būtu pieejama tieši tad, kad tā patiešām nepieciešama. Apvienojumā ar spēju novest darbu līdz galam tas ir cilvēks, kuram var uzticēt ilgtermiņa, smagu uzdevumu, zinot, ka viņš to izturēs.`,
            self: () => `Ikdienā vari justies tikai vidēji noturīgs pret stresu (${P('stress')}%), varbūt pat trauksmains. Bet ${getL('stress').topSys} rāda ${sysPct(getL('stress'))}% — patiesā, eksistenciālā krīzē, kad šķietami “stiprie” salūzt, tavas rezerves atveras pilnā spēkā. Nenovērtē sevi par zemu sarežģītākajos brīžos.`,
            hr: () => `Neizslēdziet šo cilvēku no augsta spiediena lomām pēc pirmā iespaida — viņa īstā izturība parādās tieši krīzē, ne ikdienas sīkumos. Stabilā periodā viņš var šķist mierīgs vai pieticīgs, bet kritiskā brīdī tieši viņš noturēsies.`,
        },
        {
            when: () => hasC('teamwork') && hasC('performance'),
            title: 'Ideālais integrators — “Neredzamais balsts”',
            desc: () => `Šis cilvēks ir jebkuras organizācijas mugurkauls — tas, kas vienlaikus satur grupu kopā un noved darbu līdz galam. Vairākas neatkarīgas sistēmas apstiprina abas šīs spējas: gan prasmi saliedēt cilvēkus (${P('teamwork')}%), gan uzticami pabeigt sarežģītus uzdevumus (${P('performance')}%). Viņš nav zvaigzne, kas pieprasa skatuvi, bet līme, kas notur komandu kopā tieši krīzes brīžos. Viņam var iedot sarežģītu, ilgtermiņa uzdevumu bez mikromenedžmenta, un viņš to izpildīs klusi, paļāvīgi un līdz galam. Tā kā viņa darbs ir neuzkrītošs, viņa patiesā vērtība bieži kļūst pamanāma tikai tad, kad viņa pēkšņi nav — tad sajūk gan procesi, gan komandas saliedētība.`,
            self: () => `Tu esi tas, kas satur lietas kopā un noved tās līdz galam — jebkuras grupas mugurkauls. Tava vērtība nav skatuvē vai titulā, bet uzticamībā: tev var iedot sarežģītu ilgtermiņa uzdevumu, un tu to izdarīsi bez liekas uzraudzības.`,
            hr: () => `Iedodiet sarežģītu, ilgtermiņa uzdevumu bez mikromenedžmenta — viņš to novedīs līdz galam. Šis ir “līme”, kas notur grupu kopā krīzes brīžos, ne zvaigzne, kas pieprasa skatuvi. Neaizstājams kodols, kuru nevajadzētu pārvērst par frontmenu.`,
        },
        {
            when: () => Lt.length >= 2,
            title: 'Daudzslāņu potenciāls — vēl neatvērts',
            desc: () => `Šis ir profils ar vairākām dotībām, kas snauž zem virskārtas un neparādās ne pirmajā iespaidā, ne standarta pašnovērtējuma testos. Konkrēti: ${latentList()}. Katrā no šīm jomām redzamais ikdienas rādītājs ir zems, taču kāda no senajām sistēmām fiksē ievērojami augstāku patieso kapacitāti. Tas nozīmē, ka cilvēks dzīvo manāmi zem savām iespējām — ne spēju trūkuma dēļ, bet tāpēc, ka šīs spējas vēl nav atradušas izpausmes kanālu vai drošu vidi, kurā parādīties. Bieži aiz tā slēpjas pārliecība “tas nav priekš manis” vai agrīna pieredze, kas šīs dotības apslāpēja. Pareizā kontekstā, ar apzinātu praksi un atļauju kļūdīties, tieši šie latentie slāņi var kļūt par viņa spēcīgākajām pusēm. Tas ir klasisks “neslīpēts dimants” — augstākā iespējamā atdeve no mērķtiecīga attīstības ieguldījuma.`,
            self: () => `Tev ir vairākas dotības, kas snauž zem virskārtas: ${latentList()}. Tās neparādās ikdienas testos vai pirmajā iespaidā, bet tās ir reālas — pareizā vidē un ar apzinātu praksi tās var kļūt par tavām spēcīgākajām pusēm. Sāc ar vienu un dod tai drošu telpu parādīties.`,
            hr: () => `Šim cilvēkam ir vairākas latentas spējas (${Lt.map(t => t.label).join(', ')}), ko standarta novērtējums nepamana. Tas ir “neslīpēts dimants” — ieguldījums attīstībā un pareizo uzdevumu izvēlē šeit dos negaidīti lielu atdevi.`,
        },
        {
            when: () => hasC('leadership'),
            title: 'Dabiskais līderis',
            desc: () => `Šī cilvēka vadības spēja nav nejaušība vai apstākļu sakritība — vairākas neatkarīgas sistēmas to apstiprina vienlaikus (${P('leadership')}%). Tas nozīmē, ka autoritāte ir iedzimta, ne iemācīta vai izspēlēta: cilvēki viņam dabiski piešķir uzmanību un seko, bieži pat to neapzinoties. Viņš jūtas savā vietā, kad uzņemas atbildību par virzienu un par citiem — tukšumā bez vadības lomas viņš drīzāk nogurst nekā atpūšas. Galvenais risks šādam profilam nav spēju trūkums, bet pārmērīga kontrole vai nepacietība pret lēnākiem — apzināta deleģēšana un uzticēšanās komandai ir viņa izaugsmes mala.`,
            self: () => `Tava vadības spēja nav nejaušība — vairākas neatkarīgas sistēmas to apstiprina (${P('leadership')}%). Tev var uzticēt atbildību un cilvēkus; tā ir tava dabiskā teritorija, ne uzspiesta loma. Sargies tikai no pārmērīgas kontroles — tava izaugsme ir spējā uzticēties citiem.`,
            hr: () => `Drošs kandidāts vadošām lomām — autoritāte ir iedzimta, ne izspēlēta. Var likt priekšgalā, deleģēt lēmumus un sagaidīt, ka komanda viņam sekos dabiski. Vērojiet vien, vai viņš nepārņem pārāk daudz kontroles.`,
        },
        {
            when: () => hasC('performance'),
            title: 'Izpildes meistars',
            desc: () => `Šī cilvēka kodols ir spēja pabeigt — novest lietas līdz galam tieši tur, kur lielākā daļa apstājas pusceļā. Vairākas sistēmas neatkarīgi apstiprina šo izpildes spēku (${P('performance')}%), kas nozīmē, ka tā ir dziļa, stabila iezīme, ne situatīva motivācija. Viņš dod vislabāko rezultātu, kad uzdevums ir konkrēts, ar skaidru atbildības lauku un mērāmu galarezultātu. Toties haotiskā, nepārtraukti mainīgā vidē bez skaidra mērķa viņa spēks izšķīst — viņam vajadzīga struktūra, lai uzliesmotu. Viņa uzticamība ir tāda, ka apkārtējie sāk to uztvert kā pašsaprotamu; tieši tāpēc ir svarīgi šo klusi paveikto darbu pamanīt un novērtēt.`,
            self: () => `Tava stiprā puse ir pabeigšana — tu novedi lietas līdz galam tur, kur citi apstājas pusceļā. Uzticies šai spējai un meklē lomas, kur tieši tas tiek novērtēts. Tev vajadzīga skaidrība un struktūra — tā ir tava degviela, ne ierobežojums.`,
            hr: () => `Iedodiet konkrētus, mērāmus uzdevumus ar skaidru atbildības lauku — viņš tos izpildīs uzticami un bez mikromenedžmenta. Mazāk piemērots haotiskām, nepārtraukti mainīgām lomām bez skaidra mērķa.`,
        },
        {
            when: () => hasL('leadership'),
            title: 'Snaudošā autoritāte',
            desc: () => `Šajā cilvēkā mīt vadības kapacitāte, kas vēl nav izpausta. ${getL('leadership').topSys} sistēma fiksē spēcīgu iekšējo autoritāti (${sysPct(getL('leadership'))}%), kaut ikdienā viņš to nerāda (redzamā bāze ${P('leadership')}%). Tas nav demonstratīvs, agresīvs spēks, bet kluss, gandrīz neredzams — tāds, kas pats nemeklē varu un nereti pat izvairās no uzmanības. Iespējams, viņš sevi neuzskata par līderi vai uzskata, ka līderība nozīmē kaut ko viņam svešu. Taču pareizos apstākļos — kad situācija to pieprasa vai kad citi viņam uztic atbildību — šī autoritāte var atvērties pārsteidzoši dabiski. Tas ir potenciāls, kas gaida nevis treniņu tehnikā, bet atļauju sev to izmantot.`,
            self: () => `${getL('leadership').topSys} rāda spēcīgu iekšējo autoritāti (${sysPct(getL('leadership'))}%), kaut ikdienā tu to nerādi (${P('leadership')}%). Tā nav agresija, bet kluss spēks — tas izpaudīsies, kad apstākļi to pieprasīs. Nereti vajadzīga tikai atļauja pašam sev to izmantot.`,
            hr: () => `Šim cilvēkam ir slēpts vadības potenciāls, ko viņš pats nemeklē. Vērts to pārbaudīt — uzdodiet nelielu vadības uzdevumu un vērojiet, vai snaudošā autoritāte atveras.`,
        },
    ];

    const headline = RULES.find(r => r.when());

    // Fallback, ja neviens likums nesakrīt — joprojām personalizēts un detalizēts
    const portrait = headline
        ? { title: headline.title, desc: headline.desc() }
        : (C[0]
            ? { title: `Stiprā puse: ${C[0].label}`, desc: `Šī cilvēka skaidri apstiprinātā stiprā puse ir ${C[0].label.toLowerCase()} (${C[0].pct}%), ko vairākas neatkarīgas sistēmas atzīst vienlaikus. Tā ir droša bāze, uz kuru balstīties jau tagad — gan izvēloties lomas, gan veidojot karjeras virzienu. Pārējās spējas to papildina, bet galvenā vērtība un atpazīstamība nāks tieši no šīs jomas.` }
            : (Lt[0]
                ? { title: `Slēptais potenciāls: ${Lt[0].label}`, desc: `Šī cilvēka redzamie rādītāji ir samērā pieticīgi, taču zem virskārtas slēpjas neizpausta dotība: ${Lt[0].label.toLowerCase()} (${Lt[0].topSys} redz ${sysPct(Lt[0])}%, kaut ikdienā tikai ${Lt[0].pct}%). Tas norāda, ka cilvēks dzīvo zem savām iespējām — ne spēju trūkuma dēļ, bet tāpēc, ka šī dotība vēl nav atradusi izpausmes vidi. Pareizā kontekstā tā var kļūt par viņa spēcīgāko pusi.` }
                : { title: 'Līdzsvarots profils', desc: `Šim cilvēkam nav vienas dominējošas dotības, kas pārspētu visas pārējās — spējas ir izvietotas līdzsvaroti. Tā stiprums ir daudzpusībā un pielāgošanās spējā: viņš spēj iejusties dažādās lomās, nevis ir piesiets vienai šaurai specializācijai. Virzienu vērts izvēlēties pēc intereses un vērtībām, ne pēc kādas izteiktas iezīmes spiediena.` }));

    const selfText = headline ? headline.self()
        : (C[0] ? `Tava drošā bāze ir ${C[0].label.toLowerCase()} — balsties uz to un veido savu virzienu ap šo stiprumu.`
            : (Lt[0] ? `Tevī snauž ${Lt[0].label.toLowerCase()} — pareizā vidē tā var atvērties kā nopietna stiprā puse.`
                : `Tavas spējas ir līdzsvarotas — izvēlies virzienu pēc intereses, ne pēc šaurās stiprās puses.`));
    const hrText = headline ? headline.hr()
        : (C[0] ? `Izvietojiet šo cilvēku lomā, kas balstās uz ${C[0].label.toLowerCase()} — tur viņš dos vislabāko rezultātu.`
            : (Lt[0] ? `Šim cilvēkam ir latents ${Lt[0].label.toLowerCase()} potenciāls — vērts to apzināti attīstīt.`
                : `Daudzpusīgs profils bez izteiktas specializācijas — piemērots elastīgām, mainīgām lomām.`));

    // Slēpto talantu atkodēšana (vienmēr, ja ir latentie) — izmanto sistēmu balsis
    const decoded = Lt.map(t => {
        const m = METRIC_LATENT[t.key];
        const v = SYSTEM_VOICE[t.topSys] || {};
        return `<div style="margin-bottom:8px; font-size:0.8rem; color:#475569; line-height:1.5;">
            <b style="color:#7c3aed;">💎 ${t.label}${m ? ` · ${m.archetype}` : ''}</b><br>
            Ikdienā ${m ? m.everyday : 'redzamais rādītājs ir zems'} (${t.pct}%), bet <b>${t.topSys}</b> — kas mēra ${v.measures || 'dziļāko slāni'} — rāda ${sysPct(t)}%. ${m ? m.meaning : 'Tā ir neizmantota dotība.'}
        </div>`;
    }).join('');

    // Atgriež DIVAS daļas, lai izsaucējs var "Stratēģisko portretu" rādīt atsevišķi (vienmēr redzamu)
    const portraitHtml = `
        <div style="background:linear-gradient(135deg,#1e1b4b,#312e81); border-radius:10px; padding:12px 15px;">
            <div style="font-size:0.66rem; color:#a5b4fc; text-transform:uppercase; letter-spacing:1px; font-weight:700;">Stratēģiskais portrets</div>
            <div style="font-size:1.05rem; font-weight:800; color:#fff; margin:2px 0 5px;">${portrait.title}</div>
            <div style="font-size:0.84rem; color:#c7d2fe; line-height:1.6;">${portrait.desc}</div>
        </div>`;

    const restHtml = `
        <div style="font-size:0.72rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.6rem;">🔍 Padziļinātie secinājumi <span style="font-weight:500; text-transform:none; color:#94a3b8;">— atkodēšana un ieteikumi</span></div>
        ${decoded ? `<div style="margin-bottom:11px;"><div style="font-size:0.7rem; font-weight:700; color:#7c3aed; margin-bottom:5px;">SLĒPTO TALANTU ATKODĒŠANA</div>${decoded}</div>` : ''}
        <div style="display:flex; flex-wrap:wrap; gap:11px;">
            <div style="flex:1 1 280px; min-width:260px; background:#eff6ff; border-radius:9px; padding:11px 13px;">
                <div style="font-size:0.73rem; font-weight:800; color:#1e40af; margin-bottom:5px;">🧭 IZAUGSMES PADOMS (tev)</div>
                <div style="font-size:0.82rem; color:#334155; line-height:1.5;">${selfText}</div>
            </div>
            <div style="flex:1 1 280px; min-width:260px; background:#f8fafc; border-radius:9px; padding:11px 13px;">
                <div style="font-size:0.73rem; font-weight:800; color:#475569; margin-bottom:5px;">💼 PĀRVALDĪBAS INSTRUKCIJA (vadītājam)</div>
                <div style="font-size:0.82rem; color:#334155; line-height:1.5;">${hrText}</div>
            </div>
        </div>`;

    return { portraitHtml, restHtml };
}
