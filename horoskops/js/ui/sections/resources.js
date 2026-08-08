import { makeBar, makeSection } from '../ui_helpers.js';

export function renderResourcesSection(profile, baziBaseStr, mayanStr, mColor, mSign) {
    let getPlanetScore = (p) => {
        if(!p) return 50;
        if(p.dignity && (p.dignity.includes("Valdījums") || p.dignity.includes("Eksaltācija") || p.dignity.includes("Moolatrikona"))) return 90;
        if(p.dignity && (p.dignity.includes("Kritums") || p.dignity.includes("Trimda"))) return 30;
        return 60;
    };

    let wSaturn = profile.western?.planets?.["Saturns"] || profile.western?.planets?.["Saturn"];
    let wJup = profile.western?.planets?.["Jupiters"] || profile.western?.planets?.["Jupiter"];
    let wSaturnScore = getPlanetScore(wSaturn);
    let wJupScore = getPlanetScore(wJup);

    let hLotFortune = profile.hellenistic?.data?.fortuneLot?.sign ? 80 : 50;

    // Audita labojums: agrāk JSON.stringify(bazi).includes("Zeme") — VIENMĒR true
    // (elementu objekta atslēga eksistē arī pie 0%) → komponente bija konstanta visiem.
    let hasEarth = (profile.bazi?.elements?.["Zeme"] || 0) > 0;
    let hasDW = baziBaseStr.includes("Direct_Wealth") || baziBaseStr.includes("Tiešā Bagātība");
    let hasIW = baziBaseStr.includes("Indirect_Wealth") || baziBaseStr.includes("Netiešā Bagātība");

    // Precīza GALVENĀS zīmes pārbaude (basic.sign glifs), NE apakšvirkne visā JSON
    let isKat = mSign === "Kan";               // Tīkls / K'at
    let isToj = mSign === "Muļuk (Muluk)";     // Maksājums / Toj
    let isE = mSign === "Eb";                  // Ceļš / E'

    let pScore = Math.round((wSaturnScore + (hasEarth ? 80 : 50) + (hasDW ? 80 : 50) + (isKat ? 90 : 50) + 60) / 5);
    let iScore = Math.round((wJupScore + (hasIW ? 90 : 50) + hLotFortune + (isToj ? 90 : 50) + 60) / 5);
    let oScore = Math.round((wSaturnScore + (hasDW ? 80 : 50) + (isE ? 90 : 50) + 60) / 4);

    let P1_k_x = 120;
    let P1_k_y = 110 - (pScore / 100 * 80);
    let P2_k_x = 120 + (iScore / 100 * 80) * 0.866025;
    let P2_k_y = 110 + (iScore / 100 * 80) * 0.5;
    let P3_k_x = 120 - (oScore / 100 * 80) * 0.866025;
    let P3_k_y = 110 + (oScore / 100 * 80) * 0.5;

    let kBadgeName = "Racionāls Pārvaldnieks";
    let kBadgeDesc = "Sabalansēta spēja pelnīt, taupīt un optimizēt procesus.";
    let kTotal = Math.round((pScore + iScore + oScore) / 3);

    if (pScore >= 80 && iScore <= 40) {
        kBadgeName = "Skopais mākoņu stūmējs";
        kBadgeDesc = "Persona ir izcils budžeta sargs, kurš neļaus tērēt, taču viņam pilnīgi trūkst spējas piesaistīt jaunus līdzekļus. Uzņēmums ar viņu neaugs, bet bankrotēt arī nebankrotēs.";
    } else if (pScore <= 40 && iScore >= 80) {
        kBadgeName = "Caurumainais magnēts";
        kBadgeDesc = "Fenomenāla spēja piesaistīt naudu un resursus, taču tie pazūd tikpat ātri, cik parādās. Bez stingra grāmatveža-uzrauga šis cilvēks var radīt finanšu katastrofu.";
    } else if (oScore >= 80 && pScore <= 60 && iScore <= 60) {
        kBadgeName = "Tehniskais optimizētājs";
        kBadgeDesc = "Viņš neizdomās kā nopelnīt miljonu un viņam nav žēl naudas, bet viņš izdarīs tā, lai esošie 100 eiro strādātu kā 200. Izcils operatīvās efektivitātes vadītājs.";
    } else if (pScore >= 70 && iScore >= 70 && oScore >= 70) {
        kBadgeName = "Resursu Pavēlnieks";
        kBadgeDesc = "Materiālās pasaules meistars. Spēj piesaistīt milzu resursus, tos gudri saglabāt un izcili optimizēt apriti.";
    } else if (pScore <= 40 && iScore <= 40 && oScore <= 40) {
        kBadgeName = "Izšķērdīgais Haoss";
        kBadgeDesc = "Resursi un nauda šim cilvēkam slīd cauri pirkstiem. Viņam kategoriski nedrīkst uzticēt uzņēmuma vai projekta budžetu.";
    } else {
        // Vidējā zona — nozīmīti nosaka DOMINĒJOŠĀ ass (2026-07-09 plašuma audits)
        const kDiffP = pScore - Math.max(iScore, oScore);
        const kDiffI = iScore - Math.max(pScore, oScore);
        const kDiffO = oScore - Math.max(pScore, iScore);
        if (kDiffP >= 8) { kBadgeName = "Budžeta Sargs"; kBadgeDesc = "Uzticamākā ass ir taupība un kontrole — rezerve vienmēr būs; peļņas piesaiste un optimizācija ir vidējas."; }
        else if (kDiffI >= 8) { kBadgeName = "Peļņas Magnēts"; kBadgeDesc = "Spēcīgākā puse ir spēja piesaistīt naudu un iespējas; disciplīna un procesu slīpēšana tam seko ar atstarpi."; }
        else if (kDiffO >= 8) { kBadgeName = "Procesu Slīpētājs"; kBadgeDesc = "Galvenais talants ir likt esošajiem resursiem strādāt efektīvāk; taupība un peļņas magnētisms ir vidēji."; }
    }

    // Frāžu joslas (2026-07-09 plašuma audits): +50 un +70 robežas smalkākai atbildei vidusdiapazonā
    let getP = (s) => s<=20?"kritisks izšķērdīgums un naudas vērtības neizpratne":s<=40?"vājas taupīšanas spējas un impulsu tēriņi":s<=50?"svārstīga disciplīna — taupība mijas ar impulsu pirkumiem":s<=60?"adekvāta un mērena budžeta plānošana":s<=70?"apzināta budžeta disciplīna ar drošības rezervi":s<=80?"stingra taupība un pragmatiska finanšu rezerve":"dzelžaina budžeta kontrole un taupības režīms";
    let getI = (s) => s<=20?"absolūts finansiālā magnētisma trūkums":s<=40?"grūtības piesaistīt naudu ārpus noteiktas algas":s<=50?"piesardzīga pelnīšana ar retiem papildu ienākumiem":s<=60?"stabila, prognozējama un vidēja līmeņa peļņa":s<=70?"laba spēja pamanīt un izmantot peļņas iespējas":s<=80?"spēcīga spēja piesaistīt līdzekļus un investīcijas":"fenomenāls finansiālais magnētisms un liela peļņa";
    let getO = (s) => s<=20?"pilnīgs haoss loģistikā un resursu patēriņā":s<=40?"neefektīva un dārga materiālu izmantošana":s<=50?"nevienmērīga efektivitāte — kārtība prasa ārēju rāmi":s<=60?"standarta operacionālā efektivitāte":s<=70?"laba procesu kārtība un izmaksu apzināšanās":s<=80?"spēcīgas spējas samazināt izmaksas un kāpināt atdevi":"ģeniāla sistēmu optimizācija un maksimāla resursu izspiešana";

    let contrastLogic = "";
    let maxDiffP = pScore - ((iScore + oScore)/2);
    let maxDiffI = iScore - ((pScore + oScore)/2);
    let maxDiffO = oScore - ((pScore + iScore)/2);

    if (maxDiffP >= 30) contrastLogic = "Secinājums: Persona ir apsēsta ar taupīšanu un budžeta griešanu, taču tas burtiski žņaudz biznesu un liedz piesaistīt jaunus ienākumus.";
    else if (maxDiffI >= 30) contrastLogic = "Secinājums: Ienesīgums ir grandiozs, taču nopelnītais tiek nekavējoties izšķērdēts haotiskas pārvaldības dēļ (Caurais Spainis).";
    else if (maxDiffO >= 30) contrastLogic = "Secinājums: Izcils tehniķis, kurš visu ir perfekti optimizējis, bet biznesam trūkst jaudas, naudas pieplūduma un reālas peļņas.";
    else if (maxDiffP <= -30) contrastLogic = "Secinājums: Milzīgs finanšu haoss – cilvēks piesaista un apgroza naudu, bet viņam kategoriski nedrīkst dot pieeju budžetam bez uzrauga.";
    else if (maxDiffI <= -30) contrastLogic = "Secinājums: Sistēma ir droša un perfekti optimizēta, taču ieņēmumi stagnē, jo vadītājam trūkst finansiālā magnētisma vai riska apetītes.";
    else if (maxDiffO <= -30) contrastLogic = "Secinājums: Resursi ir, bet sistēma ir ārkārtīgi neefektīva. Nauda un laiks tiek tērēti bezjēdzīgi, sistēma izdzīvo tikai uz ieņēmumu rēķina.";
    else if (pScore >= 70 && iScore >= 70 && oScore >= 70) contrastLogic = "Secinājums: Ideāla finanšu arhitektūra. Spēj piesaistīt lielu naudu, to droši saglabāt un efektīvi likt lietā. Augstākā līmeņa CFO materiāls.";
    else if (pScore <= 40 && iScore <= 40 && oScore <= 40) contrastLogic = "Secinājums: Kritiska zona. Sistēma nesaglabā, nepelna un neoptimizē resursus. Augsts finansiālā kabeļa pārrāvuma vai bankrota risks.";
    // Mērena nosvere (±14..29) — agrāk viss šis diapazons krita vienā "balansa" tekstā (2026-07-09)
    else if (maxDiffP >= 14) contrastLogic = "Secinājums: Taupība nedaudz apsteidz pelnīšanu un optimizāciju — rezerve ir droša, bet izaugsme piesardzīga. Neliela apzināta 'riska kabata' (fiksēts % no budžeta jaunām iespējām) atbrīvo potenciālu bez drošības zaudēšanas.";
    else if (maxDiffI >= 14) contrastLogic = "Secinājums: Peļņas piesaiste ir soli priekšā disciplīnai un procesiem — nauda ienāk vieglāk, nekā tiek noturēta. Automātiska uzkrājuma daļa no katra ienākuma novērš 'caurā spaiņa' efektu vēl pirms tas sākas.";
    else if (maxDiffO >= 14) contrastLogic = "Secinājums: Optimizācija ir spēcīgākā ass — esošais tiek izspiests maksimāli, bet jaunas naudas pieplūdums un uzkrāšana ir vidēji. Efektivitātes talants dod lielāko atdevi, ja to savieno ar drosmīgāku peļņas mērķi.";
    else if (maxDiffP <= -14) contrastLogic = "Secinājums: Pelnīšana un optimizācija ir spēcīgākas par taupības disciplīnu — sistēma darbojas, bet rezerves veidojas lēni. Skaidri budžeta griesti impulsa lēmumiem šeit dod tūlītēju stabilitāti.";
    else if (maxDiffI <= -14) contrastLogic = "Secinājums: Kārtība un optimizācija ir spēcīgākas par peļņas magnētismu — sistēma droša, bet ieņēmumu izaugsme kūtra. Regulāra 'iespēju stunda' (jaunu ienākumu avotu apzināšana) līdzsvaro šo profilu.";
    else if (maxDiffO <= -14) contrastLogic = "Secinājums: Nauda ienāk un tiek turēta, bet aprites efektivitāte nedaudz atpaliek — daļa resursu strādā zem jaudas. Periodiska izmaksu revīzija ('kur aiziet katrs desmitais eiro?') atbrīvo slēptu rezervi.";
    else {
        // Līdzsvars — bet PRECĪZI nosaucot vadošo un atpalikušo asi
        const axes = [["taupība", pScore], ["ienesīgums", iScore], ["optimizācija", oScore]].sort((a, b) => b[1] - a[1]);
        contrastLogic = (axes[0][1] - axes[2][1]) >= 6
            ? `Secinājums: Resursu pārvaldība ir adekvāta; salīdzinoši spēcīgākā ass ir ${axes[0][0]}, nedaudz atpaliek ${axes[2][0]} — ikdienas finansiālajai kārtībai ar to pietiek.`
            : "Secinājums: Visas trīs resursu asis ir gandrīz identiskā līmenī — vienmērīgs finanšu profils, kas spēj gan nopelnīt, gan noturēt, gan saprātīgi likt lietā bez izteiktas vājās vietas.";
    }

    let dynamicWarning = `<b>Sintēze:</b> Personas attieksmi pret resursiem raksturo ${getP(pScore)}, ko pavada ${getI(iScore)}. Operatīvajā līmenī to atspoguļo ${getO(oScore)}. <br><br><b>${contrastLogic}</b>`;

    let kWarningBg = "#eff6ff"; let kWarningBorder = "#3b82f6"; let kWarningText = "#1e40af";
    let kWarningIcon = "🧭 Kopsavilkums";

    if (pScore >= 60 && iScore >= 60 && oScore >= 60) {
        kWarningBg = "#ecfdf5"; kWarningBorder = "#10b981"; kWarningText = "#065f46"; kWarningIcon = "✅ Finansiālais Apstiprinājums";
    } else if (pScore < 20 || iScore < 20 || oScore < 20) {
        kWarningBg = "#fef2f2"; kWarningBorder = "#ef4444"; kWarningText = "#7f1d1d"; kWarningIcon = "🚨 Finanšu Lūzums";
    } else if (pScore < 40 || iScore < 40 || oScore < 40) {
        kWarningBg = "#fffbeb"; kWarningBorder = "#f59e0b"; kWarningText = "#92400e"; kWarningIcon = "⚠️ Vājais posms";
    }

    let radarHtmlK = `
    <div style="width: 100%; box-sizing: border-box; background: white; padding: 13px 16px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 14px; border-top: 4px solid #16a34a;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; margin-bottom: 0.8rem;">
            <h4 style="margin:0; font-size:1.05rem; color:#1e293b; display: flex; align-items: center; gap: 0.5rem;">💰 Kontrole un Resursu pārvaldība (Sintezēts Indekss)</h4>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 0.72rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Ekonomiskais Indekss</span>
                <span style="font-size: 1.7rem; font-weight: 900; color: #16a34a; line-height: 1;">${kTotal}%</span>
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
                        
                        <polygon points="${P1_k_x},${P1_k_y} ${P2_k_x},${P2_k_y} ${P3_k_x},${P3_k_y}" fill="rgba(22, 163, 74, 0.25)" stroke="#16a34a" stroke-width="2" />
                        
                        <circle cx="${P1_k_x}" cy="${P1_k_y}" r="4" fill="#16a34a" />
                        <circle cx="${P2_k_x}" cy="${P2_k_y}" r="4" fill="#16a34a" />
                        <circle cx="${P3_k_x}" cy="${P3_k_y}" r="4" fill="#16a34a" />
                        
                        <text x="120" y="18" font-size="11" font-weight="900" fill="#10b981" text-anchor="middle">PRAGMATISMS</text>
                        <text x="210" y="165" font-size="11" font-weight="900" fill="#f59e0b" text-anchor="middle">IENESĪGUMS</text>
                        <text x="30" y="165" font-size="11" font-weight="900" fill="#0ea5e9" text-anchor="middle">OPTIMIZĀCIJA</text>
                    </svg>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #10b981;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Pragmatisms (P)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #10b981;">${pScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${pScore}%; height: 100%; background: #10b981;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Budžeta groži un taupība. (Saturns, Zeme, K'at)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #f59e0b;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Ienesīgums (I)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #f59e0b;">${iScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${iScore}%; height: 100%; background: #f59e0b;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Iespēju magnēts. (Jupiters, Indirect Wealth, Toj)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #0ea5e9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Optimizācija (O)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #0ea5e9;">${oScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${oScore}%; height: 100%; background: #0ea5e9;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Loģistika un efektivitāte. (Direct Wealth, E')</div>
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
