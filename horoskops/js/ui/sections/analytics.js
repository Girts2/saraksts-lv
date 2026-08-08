import { makeBar, makeSection } from '../ui_helpers.js';

export function renderAnalyticsSection(profile, baziBaseStr, mayanStr, mColor, mTone, mSign) {
    let getPlanetScore = (p) => {
        if(!p) return 50;
        if(p.dignity && (p.dignity.includes("Valdījums") || p.dignity.includes("Eksaltācija") || p.dignity.includes("Moolatrikona"))) return 90;
        if(p.dignity && (p.dignity.includes("Kritums") || p.dignity.includes("Trimda"))) return 30;
        return 60;
    };

    let wMerc = profile.western?.planets?.["Merkurs"] || profile.western?.planets?.["Mercury"];
    let wJup = profile.western?.planets?.["Jupiters"] || profile.western?.planets?.["Jupiter"];
    let wMercScore = getPlanetScore(wMerc);
    let wJupScore = getPlanetScore(wJup);

    let hMerc = profile.hellenistic?.data?.planets?.find(p => p.name === "Merkurs" || p.name === "Mercury");
    let hMercScore = hMerc && hMerc.condition === "Lielisks" ? 90 : (hMerc && hMerc.condition === "Vājš" ? 30 : 60);
    let hLotSpirit = profile.hellenistic?.data?.spiritLot?.sign ? 80 : 50;

    let baziRes = baziBaseStr.includes("Resource") || baziBaseStr.includes("Resurss");
    // Audita labojums: agrāk JSON.stringify(bazi).includes("Metāls"/"Ūdens") — VIENMĒR
    // true (elementu objekta atslēgas eksistē arī pie 0%) → komponentes bija konstantas.
    let hasMetal = (profile.bazi?.elements?.["Metāls"] || 0) > 0;
    let hasWater = (profile.bazi?.elements?.["Ūdens"] || 0) > 0;

    // Precīza GALVENĀS zīmes pārbaude (basic.sign glifs), NE apakšvirkne visā JSON
    let isNoj = mSign === "Kaban";       // Zeme/Zināšanas / No'j
    let isTzikin = mSign === "Men";      // Ērglis / Tzi'kin
    let highTone = mTone > 6 ? 80 : 50;

    let aScore = Math.round((wMercScore + hMercScore + (baziRes ? 80 : 50) + highTone + 60) / 5);
    let dScore = Math.round((wJupScore + hLotSpirit + (hasMetal ? 90 : 50) + (isNoj ? 90 : 50) + 60) / 5);
    let sScore = Math.round((wJupScore + wMercScore + (hasWater ? 80 : 50) + (isTzikin ? 90 : 50) + 60) / 5);

    let P1_k_x = 120;
    let P1_k_y = 110 - (aScore / 100 * 80);
    let P2_k_x = 120 + (dScore / 100 * 80) * 0.866025;
    let P2_k_y = 110 + (dScore / 100 * 80) * 0.5;
    let P3_k_x = 120 - (sScore / 100 * 80) * 0.866025;
    let P3_k_y = 110 + (sScore / 100 * 80) * 0.5;

    let kBadgeName = "Līdzsvarots Analītiķis";
    let kBadgeDesc = "Laba spēja uzņemt, analizēt un pielietot informāciju.";
    let kTotal = Math.round((aScore + dScore + sScore) / 3);

    if (aScore >= 80 && dScore >= 80 && sScore <= 40) {
        kBadgeName = "Aizmāršīgais ģēnijs";
        kBadgeDesc = "Persona zibenīgi saprot sarežģītus konceptus un datus, taču pilnīgi nespēj tos pārvērst praktiskā darbībā. Viņa analītika paliek teorētiska un grūti pielietojama biznesā.";
    } else if (aScore <= 40 && dScore >= 80 && sScore >= 60) {
        kBadgeName = "Lēnais sistēmpētnieks";
        kBadgeDesc = "Persona ir dabisks analītikas lielmeistars, kurš redz katru kļūdu, taču viņa uztveres ātrums ir ārkārtīgi lēns. Viņam jādod laiks – viņš atradīs atbildi, bet to nevar gaidīt tūlīt.";
    } else if (aScore >= 80 && dScore <= 40 && sScore >= 80) {
        kBadgeName = "Virsspusējais adaptētājs";
        kBadgeDesc = "Persona ļoti ātri apgūst virspusējas iemaņas un prot tās 'pārdot' kā ekspertīzi, taču viņa analītiskais dziļums ir kritisks. Viņš var pieļaut fundamentālas kļūdas, jo neredz detaļas.";
    } else if (aScore >= 80 && dScore >= 80 && sScore >= 80) {
        kBadgeName = "Intelektuālais Arhitekts";
        kBadgeDesc = "Geniāls prāts, kurš apvieno uztveres ātrumu ar analītisko dziļumu un spēju visu iemācīto nekavējoties pielietot praksē.";
    } else if (aScore <= 40 && dScore <= 40 && sScore <= 40) {
        kBadgeName = "Paviršs Strādnieks";
        kBadgeDesc = "Zemas mācīšanās spējas un analītiskais dziļums. Vislabāk strādā ar vienkāršiem, rutīnas uzdevumiem, kur nav jāmācās nekas jauns.";
    } else {
        // Vidējā zona — nozīmīti nosaka DOMINĒJOŠĀ ass (2026-07-09 plašuma audits)
        const kDiffA = aScore - Math.max(dScore, sScore);
        const kDiffD = dScore - Math.max(aScore, sScore);
        const kDiffS = sScore - Math.max(aScore, dScore);
        if (kDiffA >= 8) { kBadgeName = "Ātrais Uztvērējs"; kBadgeDesc = "Spēcīgākā ass ir uztveres ātrums — jauna informācija tiek 'paķerta lidojumā'; dziļums un pielietojums tam seko."; }
        else if (kDiffD >= 8) { kBadgeName = "Dziļuma Racējs"; kBadgeDesc = "Uzticamākā ass ir analītiskais dziļums — cēloņsakarības tiek izraktas līdz saknei; ātrums un pielietošana ir vidēji."; }
        else if (kDiffS >= 8) { kBadgeName = "Praktiskais Sintezētājs"; kBadgeDesc = "Galvenā vērtība ir spēja zināšanas pārvērst darbojošos risinājumos; teorētiskais dziļums tiek ņemts tieši tik, cik vajag."; }
    }

    // Frāžu joslas (2026-07-09 plašuma audits): +50 un +70 robežas smalkākai atbildei vidusdiapazonā
    let getA = (s) => s<=20?"kritiski lēna un sašaurināta informācijas uztvere":s<=40?"ierobežota spēja ātri operēt ar jaunumiem":s<=50?"nesteidzīga uztvere, kam vajadzīgs strukturēts izklāsts":s<=60?"adekvāta un viduvēja uztveres kapacitāte":s<=70?"raita jaunas informācijas apguve":s<=80?"ātra datu apstrāde un teicama operatīvā atmiņa":"zibenīgs prāts un fotogrāfiska faktu fiksācija";
    let getD = (s) => s<=20?"pilnīgs analītiskā aparāta trūkums":s<=40?"virsspusēja domāšana un nevēlēšanās iedziļināties":s<=50?"selektīvs dziļums — iedziļinās tikai tajā, kas pašam interesē":s<=60?"standarta pētnieciskā spēja bez lielas aizrautības":s<=70?"laba spēja izsekot cēloņsakarībām arī sarežģītos jautājumos":s<=80?"spēcīga spēja dekonstruēt sarežģītas sistēmas":"ģeniāls analītiskais dziļums un cēloņsakarību izpratne";
    let getS = (s) => s<=20?"pilnīga nespēja teoriju pielietot praksē":s<=40?"vāja spēja savienot zināšanas ar reālo dzīvi":s<=50?"pielietošana, kas prasa gatavus paraugus un piemērus":s<=60?"normāla un praktiska informācijas adaptācija":s<=70?"droša zināšanu pārnese uz reāliem uzdevumiem":s<=80?"teicama spēja sintezēt datus gatavos produktos":"virtuoza teorijas konvertācija praktiskā rīcībā";

    let contrastLogic = "";
    let maxDiffA = aScore - ((dScore + sScore)/2);
    let maxDiffD = dScore - ((aScore + sScore)/2);
    let maxDiffS = sScore - ((aScore + dScore)/2);

    if (maxDiffA >= 30) contrastLogic = "Secinājums: Persona visu uztver zibenīgi, taču atsakās iedziļināties detaļās un nespēj apvienot datus reālā sistēmā (Virsspusējais skenētājs).";
    else if (maxDiffD >= 30) contrastLogic = "Secinājums: Milzīgs teorētiskais dziļums. Cilvēks var pētīt problēmu stundām, taču strādā ārkārtīgi lēni un nespēj teoriju pielietot reālā biznesā.";
    else if (maxDiffS >= 30) contrastLogic = "Secinājums: Tīrs kompilators (Hakeris). Neko nesaprot dziļumā un uztver lēni, bet māk salipināt gatavus svešus risinājumus, lai tie reāli strādātu.";
    else if (maxDiffA <= -30) contrastLogic = "Secinājums: Lēns un stīvs prāts, kuram vajag paskaidrot divreiz. Taču, kad viņš beidzot saprot, viņš to pēta un ievieš izcili (Lēnais Profesors).";
    else if (maxDiffD <= -30) contrastLogic = "Secinājums: Persona ir izcili ātra un spējīga izmantot lietas, bet tās analītika ir katastrofāli sekla. Pielaidīs muļķīgas kļūdas paviršības dēļ.";
    else if (maxDiffS <= -30) contrastLogic = "Secinājums: 'Gudrais Muļķis' vai Tīrs Teorētiķis. Redz, uztver un analizē perfekti, bet nespēj zināšanas materializēt lēmumā vai produktā.";
    else if (aScore >= 70 && dScore >= 70 && sScore >= 70) contrastLogic = "Secinājums: Intelektuālā mašīna. Zibenīgi uztver informāciju, analizē to līdz pat atomiem un uzreiz liek strādāt arhitektūrā. Perfekts CTO vai Analītiķis.";
    else if (aScore <= 40 && dScore <= 40 && sScore <= 40) contrastLogic = "Secinājums: Prāta procesi ir lēni un vāji. Persona nav spējīga strādāt pozīcijās, kas pieprasa pastāvīgu jaunu lēmumu vai sarežģītas informācijas plūsmu.";
    // Mērena nosvere (±14..29) — agrāk viss šis diapazons krita vienā "balansa" tekstā (2026-07-09)
    else if (maxDiffA >= 14) contrastLogic = "Secinājums: Uztveres ātrums nedaudz apsteidz dziļumu un pielietošanu — jaunais tiek 'paķerts' uzreiz, bet ne vienmēr izanalizēts līdz galam. Apzināta pauze pirms secinājuma ('vai tiešām sapratu, ne tikai atpazinu?') novērš paviršas kļūdas.";
    else if (maxDiffD >= 14) contrastLogic = "Secinājums: Analītiskais dziļums ir soli priekšā ātrumam un pielietošanai — spriedumi ir pamatīgi, bet prasa laiku un mēdz palikt teorijā. Katrai analīzei der noteikt 'pietiekami labi' brīdi un vienu praktisku nākamo soli.";
    else if (maxDiffS >= 14) contrastLogic = "Secinājums: Pielietošana ir spēcīgākā ass — zināšanas ātri kļūst par darbojošos risinājumu, kaut uztvere un teorētiskais dziļums ir vidēji. Sarežģītos jautājumos der pieaicināt 'dziļuma' partneri, lai risinājums balstās pilnā ainā.";
    else if (maxDiffA <= -14) contrastLogic = "Secinājums: Uztveres ātrums nedaudz atpaliek no dziļuma un pielietošanas — jauna viela prasa vairāk laika, bet apgūtais tiek saprasts un lietots pamatīgi. Strukturēti materiāli un atkārtojums ir šī profila draugi.";
    else if (maxDiffD <= -14) contrastLogic = "Secinājums: Ātrums un pielietošana ir spēcīgāki par analītisko dziļumu — lēmumi top raiti un praktiski, bet retos, patiesi sarežģītos jautājumos der apzināta dziļā analīze vai eksperta otrais skats.";
    else if (maxDiffS <= -14) contrastLogic = "Secinājums: Uztvere un analīze ir spēcīgākas par pielietošanu — sapratne ir laba, bet pārvēršana konkrētā rīcībā mēdz buksēt. Mazi, ātri prototipi ('izmēģināt, ne izplānot') aizver šo plaisu.";
    else {
        // Līdzsvars — bet PRECĪZI nosaucot vadošo un atpalikušo asi
        const axes = [["uztveres ātrums", aScore], ["analītiskais dziļums", dScore], ["pielietošana", sScore]].sort((a, b) => b[1] - a[1]);
        contrastLogic = (axes[0][1] - axes[2][1]) >= 6
            ? `Secinājums: Kognitīvais aparāts ir sabalansēts; salīdzinoši spēcīgākā ass ir ${axes[0][0]}, nedaudz atpaliek ${axes[2][0]} — ikdienas datu plūsmai un adekvātiem spriedumiem ar to pietiek.`
            : "Secinājums: Visas trīs kognitīvās asis ir gandrīz identiskā līmenī — vienmērīgs prāta profils, kas vienlīdz labi uztver, analizē un pielieto bez izteiktas šaurās vietas.";
    }

    let dynamicWarning = `<b>Sintēze:</b> Kognitīvo bāzi veido ${getA(aScore)}, kam seko ${getD(dScore)}. Rezultātu nosaka ${getS(sScore)}. <br><br><b>${contrastLogic}</b>`;

    let kWarningBg = "#eff6ff"; let kWarningBorder = "#3b82f6"; let kWarningText = "#1e40af";
    let kWarningIcon = "🧭 Kopsavilkums";

    if (aScore >= 60 && dScore >= 60 && sScore >= 60) {
        kWarningBg = "#ecfdf5"; kWarningBorder = "#10b981"; kWarningText = "#065f46"; kWarningIcon = "✅ Analītiskais Apstiprinājums";
    } else if (aScore < 20 || dScore < 20 || sScore < 20) {
        kWarningBg = "#fef2f2"; kWarningBorder = "#ef4444"; kWarningText = "#7f1d1d"; kWarningIcon = "🚨 Kognitīvais Lūzums";
    } else if (aScore < 40 || dScore < 40 || sScore < 40) {
        kWarningBg = "#fffbeb"; kWarningBorder = "#f59e0b"; kWarningText = "#92400e"; kWarningIcon = "⚠️ Vājais posms";
    }

    let radarHtmlK = `
    <div style="width: 100%; box-sizing: border-box; background: white; padding: 13px 16px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 14px; border-top: 4px solid #3b82f6;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; margin-bottom: 0.8rem;">
            <div style="min-width:0;">
                <h4 style="margin:0; font-size:1.05rem; color:#1e293b; display: flex; align-items: center; gap: 0.5rem;">📚 Analītika un Domāšanas Stils (Sintezēts Indekss)</h4>
                <div style="font-size:0.7rem; color:#94a3b8; margin-top:2px; line-height:1.4;">Mēra analītisko <b>kapacitāti</b> (apstrāde · dziļums · sintēze) — cik labi prāts analizē. <b>Mācīšanās ātrumu</b> (cik ātri apgūst jauno) sk. Personības Matricā.</div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 0.72rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Kognitīvais Indekss</span>
                <span style="font-size: 1.7rem; font-weight: 900; color: #3b82f6; line-height: 1;">${kTotal}%</span>
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
                        
                        <polygon points="${P1_k_x},${P1_k_y} ${P2_k_x},${P2_k_y} ${P3_k_x},${P3_k_y}" fill="rgba(59, 130, 246, 0.25)" stroke="#3b82f6" stroke-width="2" />
                        
                        <circle cx="${P1_k_x}" cy="${P1_k_y}" r="4" fill="#3b82f6" />
                        <circle cx="${P2_k_x}" cy="${P2_k_y}" r="4" fill="#3b82f6" />
                        <circle cx="${P3_k_x}" cy="${P3_k_y}" r="4" fill="#3b82f6" />
                        
                        <text x="120" y="18" font-size="11" font-weight="900" fill="#14b8a6" text-anchor="middle">ĀTRUMS</text>
                        <text x="210" y="165" font-size="11" font-weight="900" fill="#6366f1" text-anchor="middle">DZIĻUMS</text>
                        <text x="30" y="165" font-size="11" font-weight="900" fill="#ec4899" text-anchor="middle">SINTĒZE</text>
                    </svg>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #14b8a6;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Uztveres ātrums (Ā)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #14b8a6;">${aScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${aScore}%; height: 100%; background: #14b8a6;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Datu apstrāde un operatīvā atmiņa. (Merkurs, Resurss, Tonāls)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #6366f1;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Analītiskais dziļums (D)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #6366f1;">${dScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${dScore}%; height: 100%; background: #6366f1;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Spēja dekonstruēt un pētīt. (Jupiters, Metāls, No'j)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #ec4899;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Adaptācija un Sintēze (S)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #ec4899;">${sScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${sScore}%; height: 100%; background: #ec4899;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Praktiskais pielietojums. (Ūdens, Gara Lote, Tz'ikin)</div>
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
