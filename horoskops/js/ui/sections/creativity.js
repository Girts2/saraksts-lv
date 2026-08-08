import { makeBar, makeSection } from '../ui_helpers.js';

export function renderCreativitySection(profile, baziBaseStr, mayanStr, mColorMain, mayanToneMain, mSign) {
    let getPlanetScore = (p) => {
        if(!p) return 50;
        if(p.dignity && (p.dignity.includes("Valdījums") || p.dignity.includes("Eksaltācija") || p.dignity.includes("Moolatrikona"))) return 90;
        if(p.dignity && (p.dignity.includes("Kritums") || p.dignity.includes("Trimda"))) return 30;
        return 60;
    };

    let wMerc = profile.western?.planets?.["Merkurs"] || profile.western?.planets?.["Mercury"];
    let wVen = profile.western?.planets?.["Venera"] || profile.western?.planets?.["Venus"];
    let wSun = profile.western?.planets?.["Saule"] || profile.western?.planets?.["Sun"];
    let wNep = profile.western?.planets?.["Neptuns"] || profile.western?.planets?.["Neptune"]; // atslēga BEZ diakritikas (audita labojums: "Neptūns" neeksistē → skors bija konst. 50)

    let wVenScore = getPlanetScore(wVen);
    let wMercScore = getPlanetScore(wMerc);
    let wNepScore = getPlanetScore(wNep);
    let wSunScore = getPlanetScore(wSun);

    let hasEG = baziBaseStr.includes("Eating_God") || baziBaseStr.includes("Ēšanas Dievs");
    let hasHO = baziBaseStr.includes("Hurting_Officer") || baziBaseStr.includes("Ievainojošais Virsnieks");
    let hasRes = baziBaseStr.includes("Resource") || baziBaseStr.includes("Resurss");
    let baziInsp = hasRes ? 80 : 40;
    let baziStyle = hasHO ? 90 : (hasEG ? 70 : 40);
    let baziTech = hasEG ? 90 : (hasHO ? 70 : 40);

    let hVenH = profile.hellenistic?.data?.planets?.find(p => p.name === "Venera");
    let hVenScore = hVenH && hVenH.condition === "Lielisks" ? 90 : (hVenH && hVenH.condition === "Vājš" ? 30 : 60);
    let hMercH = profile.hellenistic?.data?.planets?.find(p => p.name === "Merkurs");
    let hMercScore = hMercH && hMercH.condition === "Lielisks" ? 90 : (hMercH && hMercH.condition === "Vājš" ? 30 : 60);
    let hLotSpirit = profile.hellenistic?.data?.spiritLot?.sign ? 80 : 50;

    // Precīza GALVENĀS zīmes pārbaude (basic.sign glifs), NE apakšvirkne visā JSON
    let isBatz = mSign === "Čuven (Chuwen)";   // Pērtiķis / B'atz'
    let isNoj = mSign === "Kaban";             // Zeme / No'j
    let mayanInsp = (mColorMain || '').includes("Zil") ? 80 : 50;   // krāsa formātā "Zils/Melns (Rietumi)"
    let mayanStyle = (isBatz || isNoj) ? 90 : 50;
    let mayanTech = mayanToneMain === 11 ? 95 : (mayanToneMain % 2 !== 0 ? 70 : 50);

    let iScore = Math.round((wNepScore + baziInsp + hLotSpirit + mayanInsp + 60) / 5);
    let sScore = Math.round((baziStyle + wSunScore + mayanStyle + wMercScore + 60) / 5);
    let tScore = Math.round((wVenScore + mayanTech + hMercScore + hVenScore + baziTech) / 5);

    let P1_k_x = 120;
    let P1_k_y = 110 - (iScore / 100 * 80);
    let P2_k_x = 120 + (sScore / 100 * 80) * 0.866025;
    let P2_k_y = 110 + (sScore / 100 * 80) * 0.5;
    let P3_k_x = 120 - (tScore / 100 * 80) * 0.866025;
    let P3_k_y = 110 + (tScore / 100 * 80) * 0.5;

    let kBadgeName = "Līdzsvarots Radītājs";
    let kBadgeDesc = "Visas radošās asis ir aktīvas mērenā līmenī.";
    let kTotal = Math.round((iScore + sScore + tScore) / 3);

    if (iScore >= 70 && sScore <= 50 && tScore <= 50) {
        kBadgeName = "Sapņotājs-Gūsteknis";
        kBadgeDesc = "Vīzijas ir grandiozas, bet tās nekad neierauga dienasgaismu. Trūkst tehnikas un drosmes parādīt sevi.";
    } else if (iScore <= 50 && sScore >= 70 && tScore <= 50) {
        kBadgeName = "Tukšais Provokators";
        kBadgeDesc = "Mīl skatuvi un šoku, bet aiz tā nav ne dziļa satura, ne tehniskas kvalitātes.";
    } else if (iScore <= 50 && sScore <= 50 && tScore >= 70) {
        kBadgeName = "Perfekts Kopētājs";
        kBadgeDesc = "Izcila amatniecība un tehnika, taču trūkst oriģinalitātes un paša iedvesmas. Rada to, ko jau zina.";
    } else if (iScore >= 70 && sScore >= 70 && tScore <= 50) {
        kBadgeName = "Nepabeigtais Ģēnijs";
        kBadgeDesc = "Idejas un oriģinalitāte ir avangardā, taču pietrūkst zemes un pacietības novest lietas līdz formašanai.";
    } else if (iScore >= 70 && sScore <= 50 && tScore >= 70) {
        kBadgeName = "Radošais Intraverts";
        kBadgeDesc = "Veido šedevrus klusumā. Rada kvalitatīvas, dziļas lietas, taču baidās izcelties un reklamēt sevi.";
    } else if (iScore <= 50 && sScore >= 70 && tScore >= 70) {
        kBadgeName = "Meistars-Izpildītājs";
        kBadgeDesc = "Lieliski ietērpj un pārdod idejas. Savu iedvesmu var meklēt pie citiem, bet noformē perfekti.";
    } else if (iScore >= 70 && sScore >= 70 && tScore >= 70) {
        kBadgeName = "Vizionārs-Radītājs";
        kBadgeDesc = "Retums. Ideāls sapņotāja, inovatora un amatnieka apvienojums.";
    } else if (iScore <= 40 && sScore <= 40 && tScore <= 40) {
        kBadgeName = "Sistēmas Strādnieks";
        kBadgeDesc = "Radošums nav šī profila prioritāte. Vairāk piemērots precīzai instrukciju izpildei.";
    } else {
        // Vidējā zona — nozīmīti nosaka DOMINĒJOŠĀ ass (2026-07-09: plašuma audits —
        // agrāk visi vidējie profili dabūja vienu "Līdzsvarots Radītājs" nozīmīti).
        const kDiffI = iScore - Math.max(sScore, tScore);
        const kDiffS = sScore - Math.max(iScore, tScore);
        const kDiffT = tScore - Math.max(iScore, sScore);
        if (kDiffI >= 8) { kBadgeName = "Iedvesmas Dzinējs"; kBadgeDesc = "Radošo motoru velk iekšējā tēlu pasaule; stils un tehnika seko tai ar soli nopakaļ."; }
        else if (kDiffS >= 8) { kBadgeName = "Stila Nesējs"; kBadgeDesc = "Spēcīgākais trumpis ir atpazīstams rokraksts un pasniegšana; iedvesma un tehnika tam kalpo."; }
        else if (kDiffT >= 8) { kBadgeName = "Formas Meistars"; kBadgeDesc = "Uzticamākā ass ir izpildījums — spēja pabeigt un noslīpēt; idejas mēdz nākt no ārpuses."; }
    }

    // Frāžu joslas (2026-07-09 plašuma audits): +50 un +70 robežas — vairums profilu
    // dzīvo 45..75 zonā, kur agrāk bija tikai divi formulējumi uz asi.
    let getI = (s) => s<=20?"pilnīgi bloķēta iekšējā uztvere (tikai racionāla pieeja)":s<=40?"vāja fantāzija un limitēts resurss":s<=50?"piezemēta iztēle, kas iedarbojas tikai ar ārēju stimulu":s<=60?"praktiska un funkcionāla iztēle":s<=70?"dzīva iztēle ar regulāriem spilgtiem uzplaiksnījumiem":s<=80?"bagātīga un plūstoša tēlu pasaule":"bezgalīga vizionāra iedvesma";
    let getS = (s) => s<=20?"pilnīgs drosmes trūkums un 'copy-paste' mentalitāte":s<=40?"bailes no atšķirīgā un pielāgošanās pūlim":s<=50?"piesardzīga gaume, kas tikai pa retam atļaujas ko savu":s<=60?"sabalansēta gaume bez vēlmes šokēt":s<=70?"drošs personiskais rokraksts pazīstamu formu ietvaros":s<=80?"izteikts autordarba stils un drosmīgi meklējumi":"provokatīva un unikāla ekspresija";
    let getT = (s) => s<=20?"tehniska nevarība un pilnīgs formas trūkums":s<=40?"vājas amatniecības prasmes":s<=50?"nestabila tehnika — kvalitāte atkarīga no dienas formas":s<=60?"adekvāta, standarta izpildījuma kapacitāte":s<=70?"stabila, pārbaudīta amatnieka roka":s<=80?"augstas raudzes profesionalitāte formā":"virtuozs un nevainojams tehniskais izpildījums";

    let contrastLogic = "";
    let maxDiffI = iScore - ((sScore + tScore)/2);
    let maxDiffS = sScore - ((iScore + tScore)/2);
    let maxDiffT = tScore - ((iScore + sScore)/2);

    if (maxDiffI >= 30) contrastLogic = "Secinājums: Idejas lido mākoņos, bet tās netiek realizētas. Liels fantāzijas potenciāls, kas iestrēgst formā.";
    else if (maxDiffS >= 30) contrastLogic = "Secinājums: Saukļi un stils pārsniedz saturu. Cilvēks izmisīgi cenšas atšķirties, bet trūkst dziļuma.";
    else if (maxDiffT >= 30) contrastLogic = "Secinājums: Tīra amatniecība. Cilvēks perfekti izpilda svešus rasējumus, bet pašam nav savas unikālās vīzijas.";
    else if (maxDiffI <= -30) contrastLogic = "Secinājums: Cilvēks ir sauss tehniķis vai provokators, kuram kritiski trūkst patiesas iedvesmas avota.";
    else if (maxDiffS <= -30) contrastLogic = "Secinājums: Rada fantastiskas un kvalitatīvas lietas, bet tās ir pilnībā 'pelēkas' un konvencionālas. Nav savas odziņas.";
    else if (maxDiffT <= -30) contrastLogic = "Secinājums: Klasisks sapņotāja sindroms – milzīga, unikāla iedvesma, kura fiziski nekad netiek pabeigta un atlieta formā.";
    else if (iScore >= 70 && sScore >= 70 && tScore >= 70) contrastLogic = "Secinājums: Izcila sinerģija. Persona spēj ģenerēt dziļas idejas, pasniegt tās oriģināli un novest līdz perfektai fiziskai realizācijai.";
    else if (iScore <= 40 && sScore <= 40 && tScore <= 40) contrastLogic = "Secinājums: Radošā enerģija ir vāja. Persona orientējas uz izdzīvošanu vai loģiku, nevis mākslu.";
    // Mērena nosvere (±14..29) — agrāk viss šis diapazons krita vienā "balansa" tekstā (2026-07-09)
    else if (maxDiffI >= 14) contrastLogic = "Secinājums: Iedvesma nedaudz apsteidz stilu un tehniku — ideju dzimst vairāk, nekā tiek noformēts. Der ārējs 'pabeidzēja' partneris vai apzināts realizācijas rituāls.";
    else if (maxDiffS >= 14) contrastLogic = "Secinājums: Pasniegšana un rokraksts ir soli priekšā saturam un tehnikai. Forma pārdod, bet dziļums un izpildījums jāpiedisciplinē, lai iespaids nepārsniedz devumu.";
    else if (maxDiffT >= 14) contrastLogic = "Secinājums: Izpildījums ir drošākā ass — roka pabeidz to, ko galva iedod. Izaugsmes rezerve ir iedvesmas barošanā: bez svaigām idejām tehnika sāk atkārtoties.";
    else if (maxDiffI <= -14) contrastLogic = "Secinājums: Iedvesma nedaudz atpaliek no stila un tehnikas — persona spoži noformē un pabeidz, bet svaigu ideju pieplūdums jāorganizē apzināti (vide, ceļošana, citu jomu impulsi).";
    else if (maxDiffS <= -14) contrastLogic = "Secinājums: Saturs un tehnika ir spēcīgāki par pasniegšanu — darbi ir labāki, nekā izskatās no malas. Nedaudz apzinātas 'iepakošanas' drosmes dotu tūlītēju atdevi.";
    else if (maxDiffT <= -14) contrastLogic = "Secinājums: Idejas un stils nedaudz apsteidz izpildījuma izturību — iesāktā pabeigšana ir šaurākā vieta. Mazāki darbi ar tuvāku finiša līniju dod labāku rezultātu.";
    else {
        // Līdzsvars — bet PRECĪZI nosaucot vadošo un atpalikušo asi (ne tikai "viss balansā")
        const axes = [["iedvesma", iScore], ["oriģinalitāte", sScore], ["realizācija", tScore]].sort((a, b) => b[1] - a[1]);
        contrastLogic = (axes[0][1] - axes[2][1]) >= 6
            ? `Secinājums: Radošie komponenti ir līdzsvarā bez ekstrēmām novirzēm; salīdzinoši spēcīgākā ass ir ${axes[0][0]}, nedaudz atpaliek ${axes[2][0]} — ikdienas dizaina un satura uzdevumiem ar to pilnīgi pietiek.`
            : "Secinājums: Visas trīs radošās asis ir gandrīz identiskā līmenī — reti vienmērīgs profils, kas ļauj brīvi pārslēgties starp ideju ģenerēšanu, noformēšanu un pabeigšanu.";
    }

    let dynamicWarning = `<b>Sintēze:</b> Personas radošo plūsmu raksturo ${getI(iScore)}, ko papildina ${getS(sScore)}. To visu fiksē un realizē ${getT(tScore)}. <br><br><b>${contrastLogic}</b>`;

    let kWarningBg = "#eff6ff"; let kWarningBorder = "#3b82f6"; let kWarningText = "#1e40af";
    let kWarningIcon = "🧭 Kopsavilkums";

    if (iScore >= 60 && sScore >= 60 && tScore >= 60) {
        kWarningBg = "#ecfdf5"; kWarningBorder = "#10b981"; kWarningText = "#065f46"; kWarningIcon = "✅ Radošais Apstiprinājums";
    } else if (iScore < 20 || sScore < 20 || tScore < 20) {
        kWarningBg = "#fef2f2"; kWarningBorder = "#ef4444"; kWarningText = "#7f1d1d"; kWarningIcon = "🚨 Radošais Lūzums";
    } else if (iScore < 40 || sScore < 40 || tScore < 40) {
        kWarningBg = "#fffbeb"; kWarningBorder = "#f59e0b"; kWarningText = "#92400e"; kWarningIcon = "⚠️ Vājais posms";
    }

    let radarHtmlK = `
    <div style="width: 100%; box-sizing: border-box; background: white; padding: 13px 16px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 14px; border-top: 4px solid #ec4899;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; margin-bottom: 0.8rem;">
            <h4 style="margin:0; font-size:1.05rem; color:#1e293b; display: flex; align-items: center; gap: 0.5rem;">🌈 Kreativitāte un pašizpausme (Sintezēts Indekss)</h4>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 0.72rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Radošais Indekss</span>
                <span style="font-size: 1.7rem; font-weight: 900; color: #ec4899; line-height: 1;">${kTotal}%</span>
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
                        
                        <polygon points="${P1_k_x},${P1_k_y} ${P2_k_x},${P2_k_y} ${P3_k_x},${P3_k_y}" fill="rgba(236, 72, 153, 0.25)" stroke="#ec4899" stroke-width="2" />
                        
                        <circle cx="${P1_k_x}" cy="${P1_k_y}" r="4" fill="#ec4899" />
                        <circle cx="${P2_k_x}" cy="${P2_k_y}" r="4" fill="#ec4899" />
                        <circle cx="${P3_k_x}" cy="${P3_k_y}" r="4" fill="#ec4899" />
                        
                        <text x="120" y="18" font-size="11" font-weight="900" fill="#8b5cf6" text-anchor="middle">IEDVESMA</text>
                        <text x="210" y="165" font-size="11" font-weight="900" fill="#ef4444" text-anchor="middle">ORIĢINALITĀTE</text>
                        <text x="30" y="165" font-size="11" font-weight="900" fill="#0ea5e9" text-anchor="middle">REALIZĀCIJA</text>
                    </svg>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #8b5cf6;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Iedvesma (I)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #8b5cf6;">${iScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${iScore}%; height: 100%; background: #8b5cf6;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Iekšējais resurss, fantāzija, tēli. (Neptūns, Resurss BaZi, Vēdu 5. māja, Gara Lote)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #ef4444;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Oriģinalitāte (O)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #ef4444;">${sScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${sScore}%; height: 100%; background: #ef4444;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Unikālais paraksts, drosme būt citādam. (Hurting Officer, Saule, Nawal B'atz', Merkurijs)</div>
                </div>
                <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #0ea5e9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Realizācija (R)</div>
                        <div style="font-size: 1rem; font-weight: 900; color: #0ea5e9;">${tScore}%</div>
                    </div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${tScore}%; height: 100%; background: #0ea5e9;"></div></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Spēja ietērpt ideju formā. (Venera, Tonāls, Hellēnistiskā fāze)</div>
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
