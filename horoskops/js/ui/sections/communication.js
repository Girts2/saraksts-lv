import { makeBar, makeSection } from '../ui_helpers.js';

export function renderCommunicationSection(profile, baziBaseStr, mayanStr, mColor, mSign) {
    let getPlanetScore = (p) => {
        if(!p) return 50;
        if(p.dignity && (p.dignity.includes("Valdījums") || p.dignity.includes("Eksaltācija") || p.dignity.includes("Moolatrikona"))) return 90;
        if(p.dignity && (p.dignity.includes("Kritums") || p.dignity.includes("Trimda"))) return 30;
        return 60;
    };

    let wMerc = profile.western?.planets?.["Merkurs"] || profile.western?.planets?.["Mercury"];
    let wVen = profile.western?.planets?.["Venera"] || profile.western?.planets?.["Venus"];
    let wMercScore = getPlanetScore(wMerc);
    let wVenScore = getPlanetScore(wVen);

    let hMercH = profile.hellenistic?.data?.planets?.find(p => p.name === "Merkurs" || p.name === "Mercury");
    let hMercScore = hMercH && hMercH.condition === "Lielisks" ? 90 : (hMercH && hMercH.condition === "Vājš" ? 30 : 60);
    let hVenH = profile.hellenistic?.data?.planets?.find(p => p.name === "Venera" || p.name === "Venus");
    let hVenScore = hVenH && hVenH.condition === "Lielisks" ? 90 : (hVenH && hVenH.condition === "Vājš" ? 30 : 60);

    let hasFriend = baziBaseStr.includes("Friend") || baziBaseStr.includes("Draugs");
    let hasRob = baziBaseStr.includes("Rob_Wealth") || baziBaseStr.includes("Bagātības Laupītājs");
    let hasHO = baziBaseStr.includes("Hurting_Officer") || baziBaseStr.includes("Ievainojošais Virsnieks");
    let hasDW = baziBaseStr.includes("Direct_Wealth") || baziBaseStr.includes("Tiešā Bagātība");
    let hasIW = baziBaseStr.includes("Indirect_Wealth") || baziBaseStr.includes("Netiešā Bagātība");

    // Precīza GALVENĀS zīmes pārbaude (basic.sign glifs), NE apakšvirkne visā JSON
    let isIq = mSign === "Ik";          // Vējš / Iq' (komunikācija)
    let isTzikin = mSign === "Men";     // Ērglis / Tzi'kin
    let isE = mSign === "Eb";           // Ceļš / E'
    let isWhite = (mColor || '').includes("Balt");   // krāsa formātā "Balts (Ziemeļi)"
    let isEarthFam = mayanStr.includes("Zemes ģimen") || mayanStr.includes("Earth Famil");

    let aScore = Math.round((wMercScore + hMercScore + (isIq ? 90 : 50) + (isWhite ? 80 : 50) + 60) / 5);
    let hScore = Math.round((wVenScore + hVenScore + (hasHO ? 90 : 50) + (hasFriend ? 80 : 50) + (isEarthFam ? 80 : 50) + 60) / 6);
    let eScore = Math.round(((hasRob ? 90 : 50) + (hasDW || hasIW ? 80 : 50) + (isTzikin || isE ? 90 : 50) + 60) / 4);

    let P1_k_x = 120;
    let P1_k_y = 110 - (aScore / 100 * 80);
    let P2_k_x = 120 + (hScore / 100 * 80) * 0.866025;
    let P2_k_y = 110 + (hScore / 100 * 80) * 0.5;
    let P3_k_x = 120 - (eScore / 100 * 80) * 0.866025;
    let P3_k_y = 110 + (eScore / 100 * 80) * 0.5;

    let kBadgeName = "Dabiskais Orators";
    let kBadgeDesc = "Līdzsvarota spēja izteikties, patikt un paplašināt tīklu.";
    let kTotal = Math.round((aScore + hScore + eScore) / 3);

    if (aScore <= 50 && hScore >= 80 && eScore <= 50) {
        kBadgeName = "Klusais Magnēts";
        kBadgeDesc = "Personai piemīt milzīga harizma, taču viņam ir grūti izteikties vārdos. Viņš apbur ar klātbūtni, bet nespēj skaidri noformulēt savus piedāvājumus.";
    } else if (aScore >= 80 && hScore <= 50 && eScore >= 80) {
        kBadgeName = "Aukstais Tīklotājs";
        kBadgeDesc = "Izcils stratēģis un runātājs, kuram pilnīgi trūkst personīgā šarma. Viņš veido kontaktus mehāniski, kas apkārtējos var radīt sajūtu par izmantošanu.";
    } else if (aScore >= 80 && hScore >= 60 && eScore <= 40) {
        kBadgeName = "Tukšais Pļāpātājs";
        kBadgeDesc = "Persona runā izcili un ir patīkama, taču viņa komunikācijai nav mērķa. Viņš patērē daudz sociālās enerģijas bez reāla rezultāta vai tīkla paplašināšanas.";
    } else if (aScore >= 80 && hScore >= 80 && eScore >= 80) {
        kBadgeName = "Sociālais Arhitekts";
        kBadgeDesc = "Harizmātisks, izcili runājošs līderis ar milzīgu, labi strukturētu kontaktu tīklu.";
    } else if (aScore <= 40 && hScore <= 40 && eScore <= 40) {
        kBadgeName = "Vientuļnieks";
        kBadgeDesc = "Izteikta tendence distancēties no sabiedrības. Kontaktējas tikai ar šauru, pārbaudītu cilvēku loku.";
    } else {
        // Vidējā zona — nozīmīti nosaka DOMINĒJOŠĀ ass (2026-07-09 plašuma audits)
        const kDiffA = aScore - Math.max(hScore, eScore);
        const kDiffH = hScore - Math.max(aScore, eScore);
        const kDiffE = eScore - Math.max(aScore, hScore);
        if (kDiffA >= 8) { kBadgeName = "Vārda Cilvēks"; kBadgeDesc = "Spēcīgākais instruments ir skaidra doma un formulējums; šarms un tīkls tam seko."; }
        else if (kDiffH >= 8) { kBadgeName = "Klātbūtnes Cilvēks"; kBadgeDesc = "Vislielākā vērtība ir personīgā klātbūtne un simpātijas — cilvēki grib sadarboties tieši ar viņu."; }
        else if (kDiffE >= 8) { kBadgeName = "Tīkla Audzētājs"; kBadgeDesc = "Dabiskā stiprā puse ir kontaktu loka paplašināšana; runas māksla un šarms ir vidēji, bet aptvērums plats."; }
    }

    // Frāžu joslas (2026-07-09 plašuma audits): +50 un +70 robežas smalkākai atbildei vidusdiapazonā
    let getA = (s) => s<=20?"kritiska nespēja noformulēt domu":s<=40?"sausa un haotiska runa":s<=50?"vienkārša, funkcionāla izteikšanās bez pārliecināšanas spara":s<=60?"skaidra un loģiska informācijas nodošana":s<=70?"raita argumentācija ar labu struktūru":s<=80?"pārliecinoša un precīza argumentācija":"izcila oratora un vārda meistara spēja";
    let getH = (s) => s<=20?"pilnīgs sociālā šarma trūkums (atgrūdoša enerģija)":s<=40?"kokaina un neērta sociālā klātbūtne":s<=50?"atturīga, bet korekta sociālā klātbūtne":s<=60?"adekvāta un pieklājīga uzvedība":s<=70?"patīkama klātbūtne, kas iegūst uzticību tuvākā lokā":s<=80?"silta un magnētiska sociālā klātbūtne":"hipnotiska harizma un diplomātijas ģēnijs";
    let getE = (s) => s<=20?"pilnīga sociālā izolācija (slēgts loks)":s<=40?"kūtra kontaktu veidošana":s<=50?"pasīva tīklošana — kontakti rodas paši, ne meklēti":s<=60?"standarta profesionālā tīklošana":s<=70?"apzināta kontaktu loka kopšana un paplašināšana":s<=80?"mērķtiecīga sociālā ekspansija":"agresīva un globāla tīkla būvēšana";

    let contrastLogic = "";
    let maxDiffA = aScore - ((hScore + eScore)/2);
    let maxDiffH = hScore - ((aScore + eScore)/2);
    let maxDiffE = eScore - ((aScore + hScore)/2);

    if (maxDiffA >= 30) contrastLogic = "Secinājums: Cilvēks runā ļoti gudri un loģiski, bet sabiedrība to neuztver vai nevēlas klausīties harizmas trūkuma dēļ.";
    else if (maxDiffH >= 30) contrastLogic = "Secinājums: Šarms un smaids slēpj satura trūkumu. Cilvēks izbrauc uz simpātijām, bet nav spējīgs skaidri noformulēt fiksētus rezultātus.";
    else if (maxDiffE >= 30) contrastLogic = "Secinājums: Milzīgs kontaktu saraksts, bet sarunas ir virspusējas. Tīklošanas mašīna bez dziļākas stratēģijas vai personiskas saiknes.";
    else if (maxDiffA <= -30) contrastLogic = "Secinājums: Spējīgs, harizmātisks tīklotājs, kurš brīžos, kad nepieciešams argumentēt vai aizstāvēt viedokli, izgāžas.";
    else if (maxDiffH <= -30) contrastLogic = "Secinājums: Robots-Taktiķis. Izcili argumentē un pārdod racionāli, taču cilvēki nejūt emocionālu saikni un var neuzticēties.";
    else if (maxDiffE <= -30) contrastLogic = "Secinājums: Brīnišķīgs sarunu biedrs ar skaidru domu, taču nevēlas vai nespēj šīs prasmes iznest plašākā auditorijā (Vientuļnieka sindroms).";
    else if (aScore >= 70 && hScore >= 70 && eScore >= 70) contrastLogic = "Secinājums: Ideāla sociālā mašīna. Apbur ar harizmu, pārliecina ar loģiku un agresīvi paplašina savu ietekmes tīklu.";
    else if (aScore <= 40 && hScore <= 40 && eScore <= 40) contrastLogic = "Secinājums: Sociāli pasīvs un noslēgts profils. Saskarsme prasa lielu enerģiju un ir neefektīva. Vislabāk strādā viens.";
    // Mērena nosvere (±14..29) — agrāk viss šis diapazons krita vienā "balansa" tekstā (2026-07-09)
    else if (maxDiffA >= 14) contrastLogic = "Secinājums: Argumentācija ir soli priekšā šarmam un tīklam — doma skaidra, bet tai reizēm pietrūkst emocionālā iesaiņojuma un auditorijas. Sagatavota 'cilvēciskā' ievadfrāze pirms būtības manāmi palielina uzklausīšanu.";
    else if (maxDiffH >= 14) contrastLogic = "Secinājums: Personiskā pievilcība nedaudz apsteidz argumentāciju un tīklošanu — simpātijas rodas vieglāk nekā konkrēta vienošanās. Der ieradums sarunas beigās fiksēt secinājumu vienā skaidrā teikumā.";
    else if (maxDiffE >= 14) contrastLogic = "Secinājums: Kontaktu aptvērums aug ātrāk par runas dziļumu un šarmu — pazīšanās ir daudz, bet ne visas pārvēršas sadarbībā. Retāki, bet dziļāki kontakti dotu lielāku atdevi.";
    else if (maxDiffA <= -14) contrastLogic = "Secinājums: Šarms un kontakti ir spēcīgāki par argumentāciju — persona patīk, bet svarīgos brīžos formulējums paliek izplūdis. Iepriekš uzrakstīti atslēgas punkti sarunai novērš šo plaisu.";
    else if (maxDiffH <= -14) contrastLogic = "Secinājums: Loģika un tīklošana strādā labāk par emocionālo saikni — sadarbība balstās lietišķumā. Tas ir stabili, bet uzticības veidošanai der apzināti neformāli brīži.";
    else if (maxDiffE <= -14) contrastLogic = "Secinājums: Runa un klātbūtne ir spēcīgākas par tīklošanu — esošajā lokā persona ir pārliecinoša, bet jaunu kontaktu veidošana notiek kūtri. Viens apzināts jauns kontakts nedēļā būtiski paplašina iespēju lauku.";
    else {
        // Līdzsvars — bet PRECĪZI nosaucot vadošo un atpalikušo asi
        const axes = [["argumentācija", aScore], ["harizma", hScore], ["tīklošana", eScore]].sort((a, b) => b[1] - a[1]);
        contrastLogic = (axes[0][1] - axes[2][1]) >= 6
            ? `Secinājums: Komunikācijas prasmes ir sabalansētas; salīdzinoši spēcīgākā ass ir ${axes[0][0]}, nedaudz atpaliek ${axes[2][0]} — kvalitatīvai kontaktu bāzei un informācijas nodošanai ar to pietiek.`
            : "Secinājums: Visas trīs komunikācijas asis ir gandrīz identiskā līmenī — vienmērīgs profils, kas ļauj brīvi pārslēgties starp argumentēšanu, attiecību siltumu un jaunu kontaktu veidošanu.";
    }

    let dynamicWarning = `<b>Sintēze:</b> Personas komunikāciju raksturo ${getA(aScore)}, ko pavada ${getH(hScore)}. To papildina ${getE(eScore)}. <br><br><b>${contrastLogic}</b>`;

    let kWarningBg = "#eff6ff"; let kWarningBorder = "#3b82f6"; let kWarningText = "#1e40af";
    let kWarningIcon = "🧭 Kopsavilkums";
    if (aScore >= 60 && hScore >= 60 && eScore >= 60) {
        kWarningBg = "#ecfdf5"; kWarningBorder = "#10b981"; kWarningText = "#065f46"; kWarningIcon = "✅ Komunikācijas Apstiprinājums";
    } else if (aScore < 20 || hScore < 20 || eScore < 20) {
        kWarningBg = "#fef2f2"; kWarningBorder = "#ef4444"; kWarningText = "#7f1d1d"; kWarningIcon = "🚨 Sociālais Lūzums";
    } else if (aScore < 40 || hScore < 40 || eScore < 40) {
        kWarningBg = "#fffbeb"; kWarningBorder = "#f59e0b"; kWarningText = "#92400e"; kWarningIcon = "⚠️ Vājais posms";
    }

    let radarHtmlK = `
    <div style="width: 100%; box-sizing: border-box; background: white; padding: 13px 16px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 14px; border-top: 4px solid #14b8a6;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; margin-bottom: 0.8rem;">
            <h4 style="margin:0; font-size:1.05rem; color:#1e293b; display: flex; align-items: center; gap: 0.5rem;">💬 Komunikācija un tīklošana (Sintezēts Indekss)</h4>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 0.72rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Sociālais Indekss</span>
                <span style="font-size: 1.7rem; font-weight: 900; color: #14b8a6; line-height: 1;">${kTotal}%</span>
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
                        
                        <polygon points="${P1_k_x},${P1_k_y} ${P2_k_x},${P2_k_y} ${P3_k_x},${P3_k_y}" fill="rgba(20, 184, 166, 0.25)" stroke="#14b8a6" stroke-width="2" />
                        
                        <circle cx="${P1_k_x}" cy="${P1_k_y}" r="4" fill="#14b8a6" />
                        <circle cx="${P2_k_x}" cy="${P2_k_y}" r="4" fill="#14b8a6" />
                        <circle cx="${P3_k_x}" cy="${P3_k_y}" r="4" fill="#14b8a6" />
                        
                        <text x="120" y="18" font-size="11" font-weight="900" fill="#3b82f6" text-anchor="middle">ARTIKULĀCIJA</text>
                        <text x="210" y="165" font-size="11" font-weight="900" fill="#ec4899" text-anchor="middle">HARIZMA</text>
                        <text x="30" y="165" font-size="11" font-weight="900" fill="#f59e0b" text-anchor="middle">EKSPANSIJA</text>
                    </svg>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #3b82f6;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Artikulācija (A)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #3b82f6;">${aScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${aScore}%; height: 100%; background: #3b82f6;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Runas skaidrība, loģika un asprātība. (Merkurs, Vējš, Baltā krāsa)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #ec4899;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Harizma (H)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #ec4899;">${hScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${hScore}%; height: 100%; background: #ec4899;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Sociālais šarms un diplomātija. (Venera, Ievainotais Ierēdnis, Draugs)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #f59e0b;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Ekspansija (E)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #f59e0b;">${eScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${eScore}%; height: 100%; background: #f59e0b;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Spēja veidot tīklu. (11. māja, Laupītājs, Ērglis, Ceļš)</div>
                </div>
            </div>
            
            <div style="padding: 9px 12px; background: ${kWarningBg}; border-left: 4px solid ${kWarningBorder}; border-radius: 6px;">
                <div style="font-size: 0.9rem; font-weight: bold; color: ${kWarningText}; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">${kWarningIcon}</div>
                <div style="font-size: 0.85rem; color: #1e293b; line-height: 1.4;">${dynamicWarning}</div>
            </div>
        </div>
    </div>`;

    return radarHtmlK;
}
