export function calculateVedicSaturnReliability(params) {
    const {
        satDeg = 0, 
        satHouse = 1, 
        isRetro = false, 
        sunHouse = 1, 
        ashtHousePts = 28, 
        nakLord = "Nezināms", 
        nakName = "Nezināms",
        d9Sign = "Nezināms",
        isDetailedUI = false
    } = params;

    let satSign = Math.floor(satDeg / 30);
    
    // 1. Dignity
    let digScore = 50;
    let digText = "Neitrāla zīme (50%)";
    if (satSign === 6) { digScore = 100; digText = "Eksaltācija Svaros (100%)"; }
    else if (satSign === 10) { digScore = 85; digText = "Moolatrikona Ūdensvīrā (85%)"; }
    else if (satSign === 9) { digScore = 75; digText = "Paša zīme Mežāzī (75%)"; }
    else if ([1, 2, 5].includes(satSign)) { digScore = 55; digText = "Draudzīga zīme (55%)"; }
    else if ([3, 4, 7].includes(satSign)) { digScore = 30; digText = "Ienaidnieka zīme (30%)"; }
    else if (satSign === 0) { digScore = 12; digText = "Debilitācija Aunā (12%)"; }
    
    // 2. Shadbala (Heuristika)
    let rupas = 4.0;
    if (digScore >= 85) rupas += 2.0;
    else if (digScore >= 70) rupas += 1.5;
    else if (digScore >= 50) rupas += 0.5;
    else if (digScore <= 15) rupas -= 2.0;
    else if (digScore < 50) rupas -= 1.0;
    
    if (satHouse === 7) rupas += 1.5;
    else if (satHouse === 1) rupas -= 1.5;
    else if ([6,8,10,11].includes(satHouse)) rupas += 0.5;
    
    let isNightBirth = (sunHouse >= 1 && sunHouse <= 6);
    if (isNightBirth) rupas += 0.5;
    if (isRetro) rupas += 1.0;
    
    if (rupas > 9.0) rupas = 9.0;
    if (rupas < 2.0) rupas = 2.0;

    let rupasText = rupas.toFixed(1) + " Rupas ";
    if (rupas >= 5.0) rupasText += "(<span style='color:#10b981;'>Spēcīgs</span>)";
    else if (rupas < 3.0) rupasText += "(<span style='color:#ef4444;'>Vājš</span>)";
    else rupasText += "(Vidējs)";

    // 3. Ashtakavarga
    let bindus = Math.round((ashtHousePts / 45) * 8);
    if (bindus > 8) bindus = 8;
    let bindusText = bindus + " punkti ";
    if (bindus >= 5) bindusText += "(<span style='color:#10b981;'>Liels atbalsts</span>)";
    else if (bindus <= 3) bindusText += "(<span style='color:#ef4444;'>Vājš atbalsts</span>)";
    else bindusText += "(Vidējs)";

    // 4. Avastha
    let degInSign = satDeg % 30;
    let avasthaMulti = 1.0;
    let avasthaText = "Jaunietis/Piebriedis (100% jauda)";
    if (degInSign <= 6) {
        avasthaMulti = 0.85;
        avasthaText = "Bērns (Vājāka jauda)";
    } else if (degInSign >= 24) {
        avasthaMulti = 0.5;
        avasthaText = "Nespēks/Nāves stāvoklis (Bloķēta jauda)";
    }

    // 5. Nakšatra
    let enemyLords = ["Saule", "Meness", "Marss"];
    let nakText = nakName + ` (Valdnieks: ${nakLord})`;
    let nakMulti = 1.0;
    if (enemyLords.includes(nakLord)) {
        nakMulti = 0.85;
        nakText += " - <span style='color:#ef4444;'>Ienaidnieka ietekme</span>";
    } else {
        nakText += " - <span style='color:#10b981;'>Draudzīgs/Neitrāls</span>";
    }

    // Final Score
    let baseSatScore = (digScore * 0.6) + ((rupas / 8.0) * 100 * 0.4);
    let vScore = Math.floor(baseSatScore * avasthaMulti * nakMulti);
    if (vScore > 100) vScore = 100;
    if (vScore < 0) vScore = 0;

    let vDesc = "";
    if (isDetailedUI) {
        vDesc = "<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>";
        vDesc += `<b>1. Dignitāte:</b> ${digText}<br>`;
        vDesc += `<b>2. Shadbala:</b> ${rupasText}<br>`;
        vDesc += `<b>3. Ashtakavarga (BAV):</b> ${bindusText}<br>`;
        vDesc += `<b>4. Avastha:</b> ${Math.floor(degInSign)}° - ${avasthaText}<br>`;
        vDesc += `<b>5. Nakšatra:</b> ${nakText}<br>`;
        vDesc += `<b>6. Navamša (D-9):</b> ${d9Sign} (Iekšējā morāle)<br>`;
        if (isRetro) {
            vDesc += `<b>7. Retrogrāds:</b> <span style='color:#fbbf24;'>Jā (Intensīvs un neprognozējams)</span><br>`;
        } else {
            vDesc += `<b>7. Retrogrāds:</b> Nē (Stabila gaita)<br>`;
        }
        vDesc += "</div>";

        let vSummary = `<br><b style="color:#4f46e5; font-size:0.85rem;">🪐 Saturns (Noturība un Atbildības Nasta):</b> <span style="font-size:0.85rem; color:#334155;">${vScore}% uzticamības rādītājs.</span>`;
        if (vScore >= 50) {
            vSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Potenciāls (Tuvāk 100%):</b> Tas ir dzelzsbetona izpildītājs. Viņam piemīt unikāla spēja nest "smagas nastas" ilgtermiņā, nesūdzoties un nezaudējot fokusu. Viņš saprot laika vērtību un disciplīnu kā svēto likumu. Ja viņš ir uzņēmies darbu, viņš to pabeigs pat tad, ja pasaule apkārt bruks.</div>`;
        } else {
            vSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Risks (Zems rādītājs):</b> "Īstermiņa skrējējs". Cilvēkam trūkst psiholoģiskā karkasa, lai izturētu rutīnu vai spiedienu. Pie pirmajām grūtībām viņš meklē vieglāko ceļu vai vienkārši "izdeg", jo viņam trūkst iekšējās pacietības un disciplīnas rezerves fondu.</div>`;
        }
        
        vDesc += vSummary;
    }

    return {
        score: vScore,
        html: vDesc
    };
}
