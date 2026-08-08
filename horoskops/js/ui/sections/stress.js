import { makeBar, makeSection } from '../ui_helpers.js';

export function renderStressSection(profile, baziBaseStr, mayanStr, mColor, mSign) {
    let getPlanetScore = (p) => {
        if(!p) return 50;
        if(p.dignity && (p.dignity.includes("Valdījums") || p.dignity.includes("Eksaltācija") || p.dignity.includes("Moolatrikona"))) return 90;
        if(p.dignity && (p.dignity.includes("Kritums") || p.dignity.includes("Trimda"))) return 30;
        return 60;
    };

    let wMoon = profile.western?.planets?.["Meness"] || profile.western?.planets?.["Moon"]; // atslēga BEZ diakritikas (audita labojums: "Mēness" neeksistē → skors bija konst. 50)
    let wMars = profile.western?.planets?.["Marss"] || profile.western?.planets?.["Mars"];
    let wSaturn = profile.western?.planets?.["Saturns"] || profile.western?.planets?.["Saturn"];

    let wMoonScore = getPlanetScore(wMoon);
    let wMarsScore = getPlanetScore(wMars);
    let wSaturnScore = getPlanetScore(wSaturn);

    let hMars = profile.hellenistic?.data?.planets?.find(p => p.name === "Marss" || p.name === "Mars");
    let hMarsScore = hMars && hMars.condition === "Lielisks" ? 90 : (hMars && hMars.condition === "Vājš" ? 30 : 60);

    let has7K = baziBaseStr.includes("Seven_Killings") || baziBaseStr.includes("Septītā Slepkava") || baziBaseStr.includes("Slepkava");
    // Audita labojums: agrāk JSON.stringify(bazi).includes("Metāls") — VIENMĒR true
    // (elementu objekta atslēga eksistē arī pie 0%) → komponente bija konstanta visiem.
    let hasMetal = (profile.bazi?.elements?.["Metāls"] || 0) > 0;
    let baziRes = baziBaseStr.includes("Resource") || baziBaseStr.includes("Resurss");

    // Precīza GALVENĀS zīmes pārbaude (basic.sign glifs), NE apakšvirkne visā JSON
    let isKawok = mSign === "Kavak (Cauac)";   // Negaiss/Vētra / Kawok
    let isTijax = mSign === "Ecnab / Cnab";    // Krams/Nazis / Tijax
    let isKame = mSign === "Kimi / Ķimi";      // Nāve / Kame

    let aScore = Math.round((wMoonScore + (hasMetal ? 80 : 50) + (isKame ? 90 : 50) + 60) / 4);
    let rScore = Math.round((wMarsScore + hMarsScore + (has7K ? 90 : 50) + (isKawok || isTijax ? 90 : 50) + 60) / 5);
    let iScore = Math.round((wSaturnScore + (baziRes ? 80 : 50) + 60) / 3);

    let P1_k_x = 120;
    let P1_k_y = 110 - (aScore / 100 * 80);
    let P2_k_x = 120 + (rScore / 100 * 80) * 0.866025;
    let P2_k_y = 110 + (rScore / 100 * 80) * 0.5;
    let P3_k_x = 120 - (iScore / 100 * 80) * 0.866025;
    let P3_k_y = 110 + (iScore / 100 * 80) * 0.5;

    let kBadgeName = "Sabalansēts Cīnītājs";
    let kBadgeDesc = "Piemīt gan mērens miers, gan spēja adekvāti reaģēt stresa situācijās.";
    let kTotal = Math.round((aScore + rScore + iScore) / 3);

    if (aScore <= 40 && rScore >= 80 && iScore <= 50) {
        kBadgeName = "Panikojošais varonis";
        kBadgeDesc = "Persona rīkojas zibenīgi, taču pilnīgā panikā. Viņa pieņemtie lēmumi var būt destruktīvi, jo tie nav balstīti mierīgā situācijas analīzē.";
    } else if (aScore >= 80 && rScore <= 40 && iScore <= 50) {
        kBadgeName = "Aukstais vērotājs";
        kBadgeDesc = "Persona saglabā apbrīnojamu mieru krīzē, taču pilnīgi nespēj rīkoties. Viņš mierīgi vēros, kā kuģis grimst, nepakustinot ne pirksta.";
    } else if (aScore >= 60 && rScore >= 60 && iScore <= 35) {
        kBadgeName = "Izdegošā klints";
        kBadgeDesc = "Persona labi rīkojas un kontrolē emocijas, taču viņa iekšējās rezerves ir kritiskas. Viņš var 'pēkšņi salūzt' tieši tad, kad šķiet, ka viss jau tiek kontrolēts.";
    } else if (aScore >= 80 && rScore >= 80 && iScore >= 80) {
        kBadgeName = "Krīzes Ģēnijs";
        kBadgeDesc = "Izcils līderis ekstremālās situācijās. Jo lielāks haoss, jo viņš kļūst mierīgāks un izlēmīgāks.";
    } else if (aScore <= 40 && rScore <= 40 && iScore <= 40) {
        kBadgeName = "Trauslais Profils";
        kBadgeDesc = "Zema stresa noturība visos līmeņos. Nepieciešama mierīga un paredzama darba vide bez pēkšņiem krīzes momentiem.";
    } else if (iScore >= 80 && rScore <= 40 && aScore <= 50) {
        kBadgeName = "Izdzīvotājs";
        kBadgeDesc = "Nereaģē ātri un bieži uztraucas, bet viņam ir milzīga izturība, ļaujot pārciest pat visgrūtākos un ilgākos spiediena apstākļus.";
    } else {
        // Vidējā zona — nozīmīti nosaka DOMINĒJOŠĀ ass (2026-07-09 plašuma audits)
        const kDiffA = aScore - Math.max(rScore, iScore);
        const kDiffR = rScore - Math.max(aScore, iScore);
        const kDiffI = iScore - Math.max(aScore, rScore);
        if (kDiffA >= 8) { kBadgeName = "Vēsais Prāts"; kBadgeDesc = "Uzticamākā ass ir paškontrole — emocijas krīzē paliek grožos; reakcijas ātrums un rezerves ir vidējas."; }
        else if (kDiffR >= 8) { kBadgeName = "Ātrais Reaģētājs"; kBadgeDesc = "Spēcīgākā puse ir rīcības ātrums — lēmums top uzreiz; miera un izturības rezerves tam seko ar atstarpi."; }
        else if (kDiffI >= 8) { kBadgeName = "Izturības Balsts"; kBadgeDesc = "Galvenais resurss ir spēja ilgstoši nest slodzi; zibenīgas reakcijas un absolūts miers nav šī profila valuta."; }
    }

    // Frāžu joslas (2026-07-09 plašuma audits): +50 un +70 robežas smalkākai atbildei vidusdiapazonā
    let getA = (s) => s<=20?"kritiska nervu sistēmas vājība un panika":s<=40?"augsta trauksmainība spiediena apstākļos":s<=50?"nervozitāte, kas prasa apzinātu savaldīšanos":s<=60?"adekvāta paškontrole standarta stresa situācijās":s<=70?"stabils miers arī saspringtās situācijās":s<=80?"iespaidīgs miers un racionāla domāšana krīzē":"ledusauksta, kiborgam līdzīga bezkaislība haosā";
    let getR = (s) => s<=20?"pilnīga sastingšana (paralīze) briesmu brīdī":s<=40?"lēna, novēlota un neizlēmīga rīcība":s<=50?"apdomīga, bet nedaudz kavēta reakcija":s<=60?"savlaicīga un funkcionāla reakcija":s<=70?"droša un izlēmīga rīcība bez liekas vilcināšanās":s<=80?"ātra, asa un mērķtiecīga rīcība":"zibenīgs izlēmīgums un iznīcinošs pretsitiens";
    let getI = (s) => s<=20?"tūlītēja izdegšana pie pirmajām grūtībām":s<=40?"vāja noturība un ātri izsīkstošas rezerves":s<=50?"izturība, kas prasa regulāras atkopšanās pauzes":s<=60?"normāla izturība vidēja garuma slodzē":s<=70?"laba izturība arī paildzinātā spriedzē":s<=80?"cieta griba un spēja 'turēt sitienu' ilgtermiņā":"titāniska un nesalaužama maratonista izturība";

    let contrastLogic = "";
    let maxDiffA = aScore - ((rScore + iScore)/2);
    let maxDiffR = rScore - ((aScore + iScore)/2);
    let maxDiffI = iScore - ((aScore + rScore)/2);

    if (maxDiffA >= 30) contrastLogic = "Secinājums: Cilvēks ir pilnīgi mierīgs, kad apkārt valda ugunsgrēks, taču viņš nesper ne soli, lai to nodzēstu (Aukstais vērotājs).";
    else if (maxDiffR >= 30) contrastLogic = "Secinājums: Paniska un zibenīga rīcība. Pieņem lēmumus haosā, bieži sastrādājot lielākus zaudējumus nekā pati krīze.";
    else if (maxDiffI >= 30) contrastLogic = "Secinājums: Absolūts 'Pacietības Mūks'. Spēj ilgi paciest toksisku spiedienu, taču atsakās ātri pieņemt neērtus, griezīgus lēmumus.";
    else if (maxDiffA <= -30) contrastLogic = "Secinājums: Fiziski rīkojas un ilgi strādā, bet iekšējā trauksme plūst pāri malām. Ārkārtīgi dramatisks personīgais fons darba laikā.";
    else if (maxDiffR <= -30) contrastLogic = "Secinājums: Dzelžaina izturība, bet trūkst asa 'naža' trieciena brīžos, kad vajag vienu nepatīkamu, ātru un zibenīgu lēmumu.";
    else if (maxDiffI <= -30) contrastLogic = "Secinājums: Izcils 'Sprinteris' taktiskajā krīzē. Taču, ja spiediens ievilksies mēnešiem, viņš vienkārši sabruks un pazudīs.";
    else if (aScore >= 70 && rScore >= 70 && iScore >= 70) contrastLogic = "Secinājums: Ideāls krīzes pavēlnieks. Dzelžains miers, zibenīga reakcija un nesalaužama izturība. Radīts darbam 'Black Swan' notikumos.";
    else if (aScore <= 40 && rScore <= 40 && iScore <= 40) contrastLogic = "Secinājums: Trausls profils. Personai nepieciešama siltumnīcas vide un miers; viņa salūzīs pie mazākā negaidītā stresa faktora.";
    // Mērena nosvere (±14..29) — agrāk viss šis diapazons krita vienā "balansa" tekstā (2026-07-09)
    else if (maxDiffA >= 14) contrastLogic = "Secinājums: Miers ir soli priekšā rīcībai un izturībai — krīzē persona vispirms stabilizē sevi un situāciju, bet aktīvais solis mēdz aizkavēties. Iepriekš sagatavots 'pirmās rīcības' saraksts šo plaisu aizver.";
    else if (maxDiffR >= 14) contrastLogic = "Secinājums: Reakcijas ātrums nedaudz apsteidz mieru un rezerves — lēmumi top ātri, bet uz iekšējās spriedzes rēķina. Pēc katras 'ugunsdzēsības' vajadzīga apzināta atkopšanās pauze.";
    else if (maxDiffI >= 14) contrastLogic = "Secinājums: Izturība ir spēcīgākā ass — persona spēj ilgi nest spiedienu, bet asos brīžos mieram un ātrai rīcībai pietrūkst rezerves. Ilgstoša pacietība nedrīkst kļūt par attaisnojumu neērtā lēmuma atlikšanai.";
    else if (maxDiffA <= -14) contrastLogic = "Secinājums: Rīcība un izturība ir spēcīgākas par iekšējo mieru — darbs tiek izdarīts, bet ar paaugstinātu iekšējo spriedzi. Regulāra izlāde (kustība, miega režīms) šeit ir darba higiēna, ne greznība.";
    else if (maxDiffR <= -14) contrastLogic = "Secinājums: Miers un izturība ir spēcīgāki par reakcijas ātrumu — persona krīzē tur līniju, bet asais, nepatīkamais lēmums mēdz nogatavoties par lēnu. Iepriekš norunāts lēmuma termiņš kompensē šo iezīmi.";
    else if (maxDiffI <= -14) contrastLogic = "Secinājums: Miers un reakcija ir spēcīgāki par rezervju dziļumu — taktiskajā krīzē persona ir laba, bet vairāku nedēļu spiedienā rezerves jāplāno apzināti (slodzes dalīšana, deleģēšana).";
    else {
        // Līdzsvars — bet PRECĪZI nosaucot vadošo un atpalikušo asi
        const axes = [["paškontrole", aScore], ["reakcijas ātrums", rScore], ["izturība", iScore]].sort((a, b) => b[1] - a[1]);
        contrastLogic = (axes[0][1] - axes[2][1]) >= 6
            ? `Secinājums: Stresa noturība ir sabalansēta; salīdzinoši spēcīgākā ass ir ${axes[0][0]}, nedaudz atpaliek ${axes[2][0]} — ikdienas biznesa krīzēm ar to pilnīgi pietiek.`
            : "Secinājums: Visas trīs stresa asis ir gandrīz identiskā līmenī — vienmērīgs profils bez izteiktas vājās vietas; ekstrēmos apstākļos rezultāts atkarīgs no sagatavotības, ne temperamenta.";
    }

    let dynamicWarning = `<b>Sintēze:</b> Personas reakciju krīzē iezīmē ${getA(aScore)}, ko papildina ${getR(rScore)}. Kopējo spiediena fonu nodrošina ${getI(iScore)}. <br><br><b>${contrastLogic}</b>`;

    let kWarningBg = "#eff6ff"; let kWarningBorder = "#3b82f6"; let kWarningText = "#1e40af";
    let kWarningIcon = "🧭 Kopsavilkums";

    if (aScore >= 60 && rScore >= 60 && iScore >= 60) {
        kWarningBg = "#ecfdf5"; kWarningBorder = "#10b981"; kWarningText = "#065f46"; kWarningIcon = "✅ Krīzes Izturības Apstiprinājums";
    } else if (aScore < 20 || rScore < 20 || iScore < 20) {
        kWarningBg = "#fef2f2"; kWarningBorder = "#ef4444"; kWarningText = "#7f1d1d"; kWarningIcon = "🚨 Kritisks Stresa Lūzums";
    } else if (aScore < 40 || rScore < 40 || iScore < 40) {
        kWarningBg = "#fffbeb"; kWarningBorder = "#f59e0b"; kWarningText = "#92400e"; kWarningIcon = "⚠️ Vājais posms";
    }

    let radarHtmlK = `
    <div style="width: 100%; box-sizing: border-box; background: white; padding: 13px 16px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 14px; border-top: 4px solid #dc2626;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; margin-bottom: 0.8rem;">
            <h4 style="margin:0; font-size:1.05rem; color:#1e293b; display: flex; align-items: center; gap: 0.5rem;">🔥 Stresa noturība un Krīzes vadība (Sintezēts Indekss)</h4>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 0.72rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Krīzes Indekss</span>
                <span style="font-size: 1.7rem; font-weight: 900; color: #dc2626; line-height: 1;">${kTotal}%</span>
                <span style="display: inline-block; padding: 4px 13px; background: #1e293b; color: white; border-radius: 20px; font-size: 0.78rem; font-weight: bold; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">${kBadgeName}</span>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: 250px minmax(0, 1fr) minmax(0, 1fr); gap: 1rem 1.3rem; align-items: start;">
                <div style="display: flex; justify-content: center; align-items: flex-start;">
                    <svg width="240" height="220" viewBox="0 0 240 220" style="overflow:visible;">
                        <polygon points="120,30 189.28,150 50.72,150" fill="rgba(0,0,0,0.03)" stroke="#cbd5e1" stroke-width="1" />
                        <polygon points="120,46 175.42,142 64.58,142" fill="none" stroke="#e2e8f0" stroke-width="1" />
                        <polygon points="120,62 161.57,134 78.43,134" fill="none" stroke="#e2e8f0" stroke-width="1" />
                        <polygon points="120,78 147.71,126 92.29,126" fill="none" stroke="#e2e8f0" stroke-width="1" />
                        <polygon points="120,94 133.86,118 106.14,118" fill="none" stroke="#e2e8f0" stroke-width="1" />
                        
                        <circle cx="120" cy="110" r="40" fill="rgba(239, 68, 68, 0.05)" stroke="#fca5a5" stroke-width="1" stroke-dasharray="3" />
                        <text x="120" y="113" font-size="7" font-weight="900" fill="#f87171" text-anchor="middle" letter-spacing="0.5px">RISKA ZONA</text>
                        
                        <line x1="120" y1="110" x2="120" y2="30" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3" />
                        <line x1="120" y1="110" x2="189.28" y2="150" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3" />
                        <line x1="120" y1="110" x2="50.72" y2="150" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3" />
                        
                        <polygon points="${P1_k_x},${P1_k_y} ${P2_k_x},${P2_k_y} ${P3_k_x},${P3_k_y}" fill="rgba(220, 38, 38, 0.25)" stroke="#dc2626" stroke-width="2" />
                        
                        <circle cx="${P1_k_x}" cy="${P1_k_y}" r="4" fill="#dc2626" />
                        <circle cx="${P2_k_x}" cy="${P2_k_y}" r="4" fill="#dc2626" />
                        <circle cx="${P3_k_x}" cy="${P3_k_y}" r="4" fill="#dc2626" />
                        
                        <text x="120" y="18" font-size="11" font-weight="900" fill="#6366f1" text-anchor="middle">AUKSTASINĪBA</text>
                        <text x="210" y="165" font-size="11" font-weight="900" fill="#f97316" text-anchor="middle">REAKCIJA</text>
                        <text x="30" y="165" font-size="11" font-weight="900" fill="#8b5cf6" text-anchor="middle">IZTURĪBA</text>
                    </svg>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #6366f1;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Aukstasinība (A)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #6366f1;">${aScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${aScore}%; height: 100%; background: #6366f1;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Spēja nepadoties panikai. (Mēness, Kame, Metāls)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #f97316;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Reakcijas ātrums (R)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #f97316;">${rScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${rScore}%; height: 100%; background: #f97316;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Izlēmība un krīzes rīcība. (Marss, 7 Killings, Negaiss/Obsidiāns)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #8b5cf6;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Ilgtermiņa izturība (I)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #8b5cf6;">${iScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${iScore}%; height: 100%; background: #8b5cf6;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Enerģētiskais enkurs 'maratonam'. (Saturns, Resursi)</div>
                </div>
            </div>
            
            <div style="padding: 9px 12px; background: ${kWarningBg}; border-left: 4px solid ${kWarningBorder}; border-radius: 6px;">
                <div style="font-size: 0.9rem; font-weight: bold; color: ${kWarningText}; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">${kWarningIcon}</div>
                <div style="font-size: 0.85rem; color: #1e293b; line-height: 1.4;">${dynamicWarning}</div>
            </div>
        </div>
    </div>`;

    return radarHtmlK;
    return radarHtmlK;
}
