export function calculateVedicMoonEarthStability(params) {
    const {
        moonDeg = 0,
        ascDeg = 0,
        allPlanetsSideral = {},
        isDetailedUI = false
    } = params;

    let moonSign = Math.floor(moonDeg / 30);
    let ascSign = Math.floor(ascDeg / 30);

    const isEarth = (sign) => [1, 5, 9].includes(sign);
    const isWater = (sign) => [3, 7, 11].includes(sign);
    const isFire = (sign) => [0, 4, 8].includes(sign);
    const isAir = (sign) => [2, 6, 10].includes(sign);

    // 1. Moon Position (50%)
    let moonScore = 0;
    let moonText = "";
    if (isEarth(moonSign)) { moonScore = 50; moonText = "Zemes zīmē (50/50 punkti) - Liels miers un praktiskums."; }
    else if (isWater(moonSign)) { moonScore = 35; moonText = "Ūdens zīmē (35/50 punkti) - Emocionāls, bet jūt atbildību."; }
    else if (isFire(moonSign)) { moonScore = 20; moonText = "Uguns zīmē (20/50 punkti) - Impulsīvs, ātri iedegas un atdziest."; }
    else if (isAir(moonSign)) { moonScore = 5; moonText = "Gaisa zīmē (5/50 punkti) - Vējains prāts, izklaidība, haoss izpildē."; }

    // 2. Earth Planets Balance (30%)
    let earthCount = 0;
    let earthPlanets = [];
    const validPlanets = ["Saule", "Meness", "Marss", "Merkurs", "Jupiters", "Venera", "Saturns", "Rahu", "Ketu"];
    validPlanets.forEach(p => {
        if (allPlanetsSideral[p] !== undefined) {
            let s = Math.floor(allPlanetsSideral[p] / 30);
            if (isEarth(s)) {
                earthCount++;
                earthPlanets.push(p);
            }
        }
    });
    // Add Ascendant if earth
    if (isEarth(ascSign)) {
        earthCount++;
        earthPlanets.push("Lagna");
    }

    let planetScore = 0;
    if (earthCount >= 3) planetScore = 30;
    else if (earthCount === 2) planetScore = 20;
    else if (earthCount === 1) planetScore = 10;
    else planetScore = 0;

    let planetText = `${earthCount} planētas/punkti Zemes zīmēs (${planetScore}/30 punkti).`;
    if (earthPlanets.length > 0) planetText += ` (${earthPlanets.join(", ")})`;

    // 3. Ascendant (Lagna) Stability (20%)
    let lagnaScore = 10;
    let lagnaText = "Neitrāla zīme (10/20 punkti).";
    if (isEarth(ascSign)) { lagnaScore = 20; lagnaText = "Zemes zīmē (20/20 punkti) - Iedzimta stabilitāte un rāmums."; }
    else if (isAir(ascSign)) { lagnaScore = 0; lagnaText = "Gaisa zīmē (0/20 punkti) - Kustīga, mainīga personība."; }
    else if (isFire(ascSign)) { lagnaScore = 5; lagnaText = "Uguns zīmē (5/20 punkti) - Enerģiska personība."; }
    else if (isWater(ascSign)) { lagnaScore = 15; lagnaText = "Ūdens zīmē (15/20 punkti) - Līdzjūtīga, intuitīva stabilitāte."; }

    let totalScore = moonScore + planetScore + lagnaScore;
    if (totalScore > 100) totalScore = 100;
    if (totalScore < 0) totalScore = 0;

    let mDesc = "";
    if (isDetailedUI) {
        mDesc = "<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>";
        mDesc += `<b>1. Prāta stāvoklis (Mēness):</b> ${moonText}<br>`;
        mDesc += `<b>2. Zemes elements kartē:</b> ${planetText}<br>`;
        mDesc += `<b>3. Personības bāze (Lagna):</b> ${lagnaText}<br>`;
        mDesc += "</div>";

        let mSummary = `<br><b style="color:#10b981; font-size:0.85rem;">🌍 Mēness / Zeme (Emocionālā Noturība un Operatīvais Miers):</b> <span style="font-size:0.85rem; color:#334155;">${totalScore}% ikdienas ritms.</span>`;
        if (totalScore >= 50) {
            mSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Potenciāls (Tuvāk 100%):</b> Prātamiers un "iezemētība". Šis cilvēks nav bezjūtīgs, bet viņa emocionālā inteliģence ir tik augsta, ka prāts paliek skaidrs jebkurā vētrā. Viņš lēmumus pieņem, balstoties uz realitāti, nevis mirkļa izjūtām. Viņa rīcība ir paredzama, stabila un loģiska.</div>`;
        } else {
            mSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Risks (Zems rādītājs):</b> "Vējains prāts". Cilvēks ir savu noskaņojumu ķīlnieks. Viņa darba kvalitāte tieši atkarīga no tā, "ar kuru kāju viņš šodien izkāpis no gultas". Viņš viegli ļaujas panikai, impulsīvām idejām un bieži maina plānus, jo viņa iekšējais "laika apstāklis" ir pārāk mainīgs.</div>`;
        }
        
        mDesc += mSummary;
    }

    return {
        score: totalScore,
        html: mDesc
    };
}
