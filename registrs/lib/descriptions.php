<?php
// AUTO-ĢENERĒTS no kods/templates/TXT/descriptions.py (gen_descriptions_php.py). NEREDIĢĒT.

const AGE_DESCRIPTIONS = [
    [0, 2, ["pavisam jauns un tikai sāk savu darbību, veidojot pirmos soļus tirgū.<br>", "kā jaunietis uzņēmumu pasaulē un vēl tikai uzsāk savu attīstību.<br>", "dibināts pavisam nesen un šobrīd veido savus pirmos panākumus.<br>", "uzņēmums, kas vēl tikai veido savu pieredzi, sperot pirmos soļus darbības ceļā.<br>", "jauns un pilns ar enerģiju, gatavs nostiprināties tirgū.<br>"]],
    [3, 5, ["uzņēmums, kas darbojas jau vairākus gadus un pamazām nostiprinās tirgū.<br>", "stabils uzņēmums ar dažus gadus ilgu pieredzi un pirmajiem panākumiem.<br>", "uzņēmums, kas šajos gados jau sekmīgi apliecinājis sevi savā nozarē.<br>", "uzņēmums, kas ar katru gadu gūst aizvien lielāku stabilitāti.<br>", "uzņēmums, kas darbojas pietiekami ilgi, lai būtu atpazīstams savā vidē.<br>"]],
    [6, 10, ["nostiprinājies uzņēmums ar 6–10 gadu pieredzi un guvis atzinību tirgū.<br>", "uzņēmums ar gandrīz desmitgades pieredzi un skaidru attīstības virzienu.<br>", "stabils uzņēmums ar gandrīz desmit gadu darbību, kas jau ieguvis savu vietu nozarē.<br>", "uzņēmums, kas ilgstoši darbojas un ir ieguvis labu reputāciju.<br>", "uzņēmums, kas gadu gaitā nostiprinājis savu stabilitāti un uzticamību.<br>"]],
    [11, 15, ["uzņēmums, kas darbojas jau vairāk nekā desmit gadus un turpina attīstīties.<br>", "uzņēmums ar gandrīz piecpadsmit gadu pieredzi un noturīgām tradīcijām.<br>", "uzņēmums ar desmitgades stabilitāti, kas pierādījis savu dzīvotspēju.<br>", "pieredzējis uzņēmums ar vairāk nekā desmit gadu darbību, kas sekmīgi turpina savu ceļu.<br>", "uzņēmums, kas darbojas ar vērtībām, kas veidojušās ilgu gadu laikā.<br>"]],
    [16, 20, ["uzņēmums, kas jau sasniedzis gandrīz divdesmit gadu pieredzi un nostiprinājies kā uzticams partneris.<br>", "uzņēmums ar gandrīz divdesmit gadu pieredzi un stabilām pozīcijām tirgū.<br>", "uzņēmums, kas jau vairāk nekā pusotru desmitgadi sekmīgi darbojas un attīstās.<br>", "nobriedis uzņēmums ar stabilu darbību un ilgtspējīgu attieksmi.<br>", "pieredzējis un uzticams uzņēmums, kas turpina stiprināt savu reputāciju.<br>"]],
    [21, 25, ["uzņēmums, kas darbojas jau vairāk nekā 20 gadus un ir nostiprinājis savas tradīcijas.<br>", "uzņēmums, kas jau vairāk nekā divus gadu desmitus sekmīgi darbojas tirgū.<br>", "uzņēmums ar vairāk nekā divdesmit gadu pieredzi, kas apliecina tā noturību.<br>", "ilggadējs uzņēmums ar bagātu pieredzi, kas turpina paplašināt savu darbību.<br>", "uzņēmums ar divu desmitgažu stabilitāti, kas kļuvis par uzticamu partneri.<br>"]],
    [26, 30, ["uzņēmums, kas darbojas gandrīz trīs gadu desmitus un ir ieguvis bagātīgu pieredzi.<br>", "uzņēmums ar gandrīz trīs desmitgažu pieredzi un noturīgām tradīcijām.<br>", "uzņēmums, kas jau vairāk nekā ceturtdaļgadsimtu sekmīgi darbojas nozarē.<br>", "uzņēmums ar tradīcijām, kas veidojušās trīsdesmit gadu laikā, un turpina savu attīstību.<br>", "nobriedis uzņēmums ar bagātīgu vēsturi, kas šodien ir stabila vērtība tirgū.<br>"]],
    [31, PHP_FLOAT_MAX, ["uzņēmums, kas darbojas jau vairāk nekā 30 gadus un turpina stiprināt savas pozīcijas.<br>", "uzņēmums ar vairāk nekā trīs desmitgažu pieredzi un noturīgu reputāciju.<br>", "īsts veterāns savā nozarē, kas apliecina izturību un nemainīgu stabilitāti.<br>", "ilggadējs uzņēmums ar gadu desmitiem krātu pieredzi, kas joprojām saglabā aktualitāti.<br>", "uzņēmums ar tradīcijām, kas pārsniedz paaudžu robežas, un turpina būt nozīmīgs tirgus dalībnieks.<br>"]],
];

const FINANCIAL_DESCRIPTIONS = [
    "current_ratio" => [
        "danger" => ["tā īstermiņa maksātspēja ir <b>kritiski zema</b>, kas norāda uz nopietnām finanšu grūtībām.<br>", "tam <b>trūkst apgrozāmo līdzekļu</b>, lai segtu savas tuvākās saistības.<br>"],
        "precaution" => ["tas spēj segt saistības, taču darbojas ar <b>minimālu finanšu rezervi</b>.<br>", "tā likviditāte ir <b>pietiekama ikdienas darbībai</b>, bet ar ierobežotām rezervēm.<br>"],
        "healthy" => ["tas demonstrē <b>stabilu maksātspēju</b> un spēju laikus segt īstermiņa saistības.<br>", "tam ir <b>komfortabla finanšu rezerve</b> un labas pozīcijas tirgū.<br>"],
        "excessive" => ["tā likviditāte ir <b>pārmērīgi augsta</b>, kas var liecināt par neefektīvu līdzekļu izmantošanu.<br>", "tam ir <b>lieli brīvie līdzekļi</b>, kas netiek pietiekami ieguldīti attīstībā.<br>"],
    ],
    "quick_ratio" => [
        "danger" => ["Ātrās likviditātes rādītājs parāda, ka darbība ir <b>atkarīga no krājumu pārdošanas</b>, lai segtu parādus.<br>", "Bez krājumu realizācijas uzņēmumam varētu rasties <b>naudas plūsmas problēmas</b>.<br>"],
        "borderline" => ["Ātrā likviditāte ir <b>uz robežas</b> starp stabilitāti un risku, prasot uzmanīgu pārvaldību.<br>", "Spēja nekavējoties segt saistības ir <b>ierobežota</b> un atkarīga no situācijas.<br>"],
        "healthy" => ["Līdzīgi tiek uzturēts arī <b>veselīgs ātrās likviditātes līmenis</b>.<br>", "Tā finanšu stāvoklis ir <b>stabils</b> un labi līdzsvarots.<br>"],
        "inefficient" => ["Vienlaikus, <b>pārlieku augstais likviditātes līmenis</b> norāda uz līdzekļu neefektīvu izmantošanu.<br>", "Uzņēmuma kontos ir <b>pārāk daudz neizmantotas naudas</b>, kas varētu strādāt.<br>"],
    ],
    "net_profit_margin" => [
        "low" => ["Diemžēl peļņas marža ir <b>zema</b>, ko varētu ietekmēt konkurence vai izmaksu spiediens.<br>", "Uzņēmuma rentabilitāte ir <b>vāja</b>, kas var kavēt turpmāko izaugsmi.<br>"],
        "average" => ["Peļņas rentabilitāte ir <b>veselīgā līmenī</b> un nodrošina stabilu darbību.<br>", "Uzņēmums strādā ar <b>stabilu un noturīgu peļņas normu</b>.<br>"],
        "high" => ["Papildus tam, uzņēmums demonstrē <b>augstu rentabilitāti</b> un spēcīgas tirgus pozīcijas.<br>", "Darbība ir ar <b>augstu peļņas maržu</b>, kas liecina par efektīvu pārvaldību.<br>"],
    ],
    "roa" => [
        "low" => ["Tomēr aktīvu atdeve ir <b>nepietiekama</b>, kas liecina par to neefektīvu izmantošanu.<br>", "Resursu izmantošana peļņas gūšanai ir <b>vāja</b> un prasa uzlabojumus.<br>"],
        "satisfactory" => ["Aktīvu atdeve ir <b>apmierinošā līmenī</b>.<br>", "Resursu izmantošana ir <b>vidēja</b>, taču pastāv iespējas to uzlabot.<br>"],
        "high" => ["Tāpat resursi tiek pārvaldīti gudri, nodrošinot <b>augstu aktīvu atdevi</b>.<br>", "Uzņēmums <b>efektīvi izmanto savus aktīvus</b> peļņas gūšanai.<br>"],
    ],
    "roe" => [
        "low" => ["Arī pašu kapitāla atdeve ir <b>zema</b>, sniedzot no ieguldījumiem <b>pieticīgu atdevi</b>.<br>", "Ieguldījumi pagaidām negūst pietiekamu atdevi.<br>"],
        "average" => ["Kapitāla atdeve ir <b>vidējā līmenī</b>, nodrošinot stabilu, bet ne izcilu sniegumu.<br>", "Pašu kapitāla izmantošanas efektivitāte ir <b>stabila, bet mērena</b>.<br>"],
        "high" => ["Tas nodrošina arī <b>izcilu atdevi no pašu kapitāla</b>, kas apliecina augstu ieguldījumu efektivitāti.<br>", "Ieguldītais kapitāls tiek pārvaldīts efektīvi, garantējot <b>augstu atdevi</b>.<br>"],
    ],
    "debt_to_equity" => [
        "low_risk" => ["Finansiālo stabilitāti pastiprina <b>zems saistību līmenis</b>, kas norāda uz neatkarību no kreditoriem.<br>", "Uzņēmums ir <b>finansiāli neatkarīgs</b> un ar zemu riska profilu.<br>"],
        "elevated_risk" => ["Vienlaikus saistību īpatsvars ir <b>paaugstināts</b>, kas palielina finanšu riskus.<br>", "Uzņēmuma finansiālā neatkarība ir <b>mērena</b>, balstoties uz aizņemto kapitālu.<br>"],
        "high_risk" => ["Darbība tiek lielā mērā finansēta ar <b>augstu aizņemtā kapitāla īpatsvaru</b>.<br>", "Uzņēmumam ir <b>liels parādu slogs</b>, kas rada paaugstinātu risku nākotnē.<br>"],
    ],
    "asset_turnover" => [
        "low" => ["Kā izaicinājums iezīmējas <b>zems aktīvu apgrozījums</b>, kas norāda uz neizmantotu pārdošanas potenciālu.<br>", "Uzņēmuma resursi tiek <b>neefektīvi</b> izmantoti ieņēmumu radīšanai.<br>"],
        "average" => ["Aktīvu apgrozījums ir <b>vidējā līmenī</b>, kas ir līdzsvarots rādītājs.<br>", "Aktīvu izmantošanas efektivitāte ir <b>apmierinoša</b>, taču ar izaugsmes potenciālu.<br>"],
        "high" => ["Pozitīvi, ka aktīvu apgrozījums ir <b>augsts</b>.<br>", "Uzņēmums spēj <b>ātri un efektīvi pārvērst savus aktīvus ieņēmumos</b>.<br>"],
    ],
    "roce" => [
        "low" => ["Arī ieguldītā kapitāla atdeve ir <b>zema</b>, kas liecina par neefektīvu kapitāla izmantošanu.<br>", "Kapitāla izmantošana peļņas radīšanai ir <b>nepietiekama</b>.<br>"],
        "average" => ["Ieguldītā kapitāla atdeve ir <b>vidējā līmenī</b>, atspoguļojot stabilu, bet mērenu efektivitāti.<br>", "Kapitāla efektivitāte ir <b>līdzsvarota</b>.<br>"],
        "high" => ["Turklāt, ieguldītais kapitāls tiek izmantots efektīvi, nodrošinot <b>augstu atdevi</b>.<br>", "Uzņēmums demonstrē spēju <b>efektīvi izmantot visu kapitālu</b> vērtības radīšanai.<br>"],
    ],
    "altman_z_score" => [
        // 2026-08-07: vārds "bankrots" izņemts (reputācijas piesardzība) — runājam par
        // finanšu noturības rādītājiem, nevis prognozējam uzņēmuma likteni.
        "distress" => ["Kopumā, aprēķinātie finanšu noturības rādītāji ir <b>kritiski zemi</b>, kas prasa pastiprinātu uzmanību.<br>", "Finanšu noturība pēc aprēķiniem ir <b>kritiski zema</b>, un tas norāda uz paaugstinātiem riskiem.<br>"],
        "grey" => ["Kopumā, uzņēmums atrodas <b>\"pelēkajā zonā\"</b> ar nenoteiktu finanšu stabilitāti.<br>", "Tā finanšu stāvoklis ir <b>nestabils</b> un prasa rūpīgu uzraudzību.<br>"],
        "safe" => ["Kopumā, augstie noturības rādītāji apstiprina, ka uzņēmums ir <b>finansiāli stabils</b> un drošs.<br>", "Noslēgumā, uzņēmuma <b>finanšu noturība ir augsta</b>, kas liecina par ilgtspējīgu darbību.<br>"],
    ],
    "interest_coverage" => [
        "low" => ["Procentu seguma rādītājs ir <b>kritiski zems</b>, radot risku nespēt segt kredītmaksājumus.<br>", "Uzņēmuma operatīvā peļņa ir nepietiekama, lai <b>droši segtu procentu izmaksas</b>.<br>"],
        "average" => ["Procentu seguma līmenis ir <b>viduvējs</b>, prasot piesardzīgu finanšu plānošanu.<br>", "Spēja segt procentu maksājumus ir <b>apmierinoša, bet ne izcila</b>.<br>"],
        "high" => ["Uzņēmums spēj <b>viegli un droši segt procentu maksājumus</b>.<br>", "Augsts procentu seguma rādītājs liecina par <b>spēcīgu maksātspēju</b> attiecībā pret kreditoriem.<br>"],
    ],
];

const DESCRIPTION_DISCLAIMER = "* Šis apraksts ir informatīvs. Analīze ir vispārīga un neņem vērā konkrētās nozares specifiku. Apraksts balstīts uz publiski pieejamiem uzņēmuma finanšu datiem un uz aprēķiniem sadaļā 'Finanšu Rādītāji un Riska Analīze'.";
