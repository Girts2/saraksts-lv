export function calculateVedicSunIntegrity(params) {
    const {
        sunDeg = 0,
        sunHouse = 1,
        isNightBirth = false,
        isDetailedUI = false
    } = params;

    let sunSign = Math.floor(sunDeg / 30);
    
    // 1. Dignity
    let digScore = 50;
    let digText = "Neitrāla zīme (50%)";
    if (sunSign === 0) { digScore = 100; digText = "Eksaltācija Aunā (100%) - Dzelžains vārds"; }
    else if (sunSign === 4) { digScore = 85; digText = "Paša zīme Lauvā (85%) - Augsts gods"; }
    else if ([3, 7, 8, 11].includes(sunSign)) { digScore = 65; digText = "Draudzīga zīme (65%) - Laba morāle"; }
    else if ([2, 5].includes(sunSign)) { digScore = 50; digText = "Neitrāla zīme (50%) - Mainīgs vārds"; }
    else if ([1, 9, 10].includes(sunSign)) { digScore = 30; digText = "Ienaidnieka zīme (30%) - Intereses pirmajā vietā"; }
    else if (sunSign === 6) { digScore = 10; digText = "Kritums Svaros (10%) - Statusa dēļ var nošmaukt"; }

    // 2. Shadbala/House Modifier
    let houseMod = 0;
    let houseText = "Vidēja pozīcija (Digbala neitrāla)";
    if (sunHouse === 10) { houseMod = +15; houseText = "10. Māja (+15%) - Augstākais izpildvaras spēks"; }
    else if (sunHouse === 4) { houseMod = -15; houseText = "4. Māja (-15%) - Slēptāka, subjektīvāka griba"; }
    else if ([1, 9, 11].includes(sunHouse)) { houseMod = +10; houseText = `${sunHouse}. Māja (+10%) - Laba izpausme`; }
    else if ([6, 8, 12].includes(sunHouse)) { houseMod = -10; houseText = `${sunHouse}. Māja (-10%) - Izaicinājumi pašapziņai`; }

    let kalaMod = 0;
    let kalaText = "Dienas dzimšana (Spēcīga Saule)";
    if (isNightBirth) {
        kalaMod = -5;
        kalaText = "Nakts dzimšana (-5%) - Saule zem horizonta";
    }

    // 3. Avastha
    let degInSign = sunDeg % 30;
    let avasthaMulti = 1.0;
    let avasthaText = "Jaunietis/Piebriedis (Pilns spēks)";
    if (degInSign <= 5) {
        avasthaMulti = 0.85;
        avasthaText = "Bērns (Vājāks fokuss)";
    } else if (degInSign >= 25) {
        avasthaMulti = 0.5;
        avasthaText = "Nespēks/Nāves stāvoklis (Vāja izpausme)";
    }

    let rawScore = (digScore + houseMod + kalaMod);
    let sScore = Math.floor(rawScore * avasthaMulti);
    if (sScore > 100) sScore = 100;
    if (sScore < 0) sScore = 0;

    let sDesc = "";
    if (isDetailedUI) {
        sDesc = "<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>";
        sDesc += `<b>1. Dignitāte:</b> ${digText}<br>`;
        sDesc += `<b>2. Mājas Pozīcija (Digbala):</b> ${houseText}<br>`;
        sDesc += `<b>3. Laika Spēks (Kalabala):</b> ${kalaText}<br>`;
        sDesc += `<b>4. Avastha (Grādi):</b> ${Math.floor(degInSign)}° - ${avasthaText}<br>`;
        sDesc += "</div>";

        let sSummary = `<br><b style="color:#f59e0b; font-size:0.85rem;">☀️ Saule (Integritāte un Rakstura Mugurkauls):</b> <span style="font-size:0.85rem; color:#334155;">${sScore}% integritāte.</span>`;
        if (sScore >= 50) {
            sSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Potenciāls (Tuvāk 100%):</b> Cilvēks-Saule ir dabisks "autoritātes etalons". Viņa pašcieņa ir cieši saistīta ar doto vārdu – nepildīt solījumu viņam nozīmētu "aptumsumu" pašam savās acīs. Viņš ir patiess nevis tāpēc, ka tā vajag, bet tāpēc, ka viņa iekšējā gaisma nepieļauj ēnas un blefu.</div>`;
        } else {
            sSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Risks (Zems rādītājs):</b> Cilvēks kļūst "caurspīdīgs" savos principos. Viņa morāles latiņa ir elastīga – viņš viegli pielāgojas situācijai, atrod attaisnojumus neizdarībai un var izmantot manipulācijas, ja tas palīdz saglabāt ārējo tēlu, pat ja iekšēji tas ir tukšs.</div>`;
        }
        
        sDesc += sSummary;
    }

    return {
        score: sScore,
        html: sDesc
    };
}
