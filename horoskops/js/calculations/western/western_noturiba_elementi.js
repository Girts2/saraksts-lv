function getAspect(deg1, deg2) {
    if (deg1 === undefined || deg2 === undefined) return null;
    let diff = Math.abs(deg1 - deg2);
    if (diff > 180) diff = 360 - diff;
    if (diff <= 8) return "Conjunction";
    if (Math.abs(diff - 60) <= 6) return "Sextile";
    if (Math.abs(diff - 90) <= 8) return "Square";
    if (Math.abs(diff - 120) <= 8) return "Trine";
    if (Math.abs(diff - 180) <= 8) return "Opposition";
    return null;
}

export function calculateWesternElementsStability(params) {
    const p = params.planets || {};
    let score = 20; // Bāze
    let breakdown = [];
    
    const earthSigns = ["Vērsis", "Jaunava", "Mežāzis"];
    const fixedSigns = ["Vērsis", "Lauva", "Skorpions", "Ūdensvīrs"];
    const airSigns = ["Dvīņi", "Svari", "Ūdensvīrs"];
    const mutableSigns = ["Dvīņi", "Jaunava", "Strēlnieks", "Zivis"];

    let earthCount = 0;
    let fixedCount = 0;
    let airMutableCount = 0;

    const keyPlanets = ["Saule", "Meness", "Merkurs", "Venera", "Marss", "Jupiters", "Saturns"];
    
    keyPlanets.forEach(pl => {
        let planet = p[pl];
        if (planet && planet.sign) {
            if (earthSigns.includes(planet.sign)) earthCount++;
            if (fixedSigns.includes(planet.sign)) fixedCount++;
            if (airSigns.includes(planet.sign) || mutableSigns.includes(planet.sign)) airMutableCount++;
        }
    });

    if (earthCount > 0) {
        let bonus = earthCount * 10;
        score += bonus;
        breakdown.push(`${earthCount} personīgās planētas Zemes zīmēs (+${bonus}%). Praktiskā inerce.`);
    }

    if (fixedCount > 0) {
        let bonus = fixedCount * 10;
        score += bonus;
        breakdown.push(`${fixedCount} personīgās planētas Fiksētajās zīmēs (+${bonus}%). Rakstura 'dzelzsbetons'.`);
    }

    if (airMutableCount >= 4) {
        score -= 20;
        breakdown.push(`Izteikts Gaisa/Mutablās enerģijas pārsvars (-20%). Izkliedēta uzmanība un mainīgums.`);
    }

    // Neptūna filtrs uzticamībai
    let neptune = p["Neptuns"];
    let merkur = p["Merkurs"];
    if (merkur && neptune) {
        let aspMN = getAspect(merkur.longitude, neptune.longitude);
        if (aspMN === "Square" || aspMN === "Opposition") {
            score -= 25;
            breakdown.push(`Neptūna Kvadrāts/Opozīcija ar Merkuru (-25%). 'Slidens' faktors. Cilvēks regulāri aizmirst solīto, haoss papīros un vārdos.`);
        }
    }

    if (score > 100) score = 100;
    if (score < 0) score = 0;

    let diagText = "";
    if (score >= 70) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Nemainīgs kā Gadalaiks:</b> Persona ir absolūti paredzama. Enerģija vērsta uz saglabāšanu un turpināšanu. Nemaina savus uzskatus katru nedēļu.</div>`;
    } else if (score <= 30) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Rakstura Hameleons:</b> Enerģija ir pārāk kustīga, kas rada nepastāvīga cilvēka iespaidu, pat ja nodomi sākotnēji ir bijuši labi. Nevar paļauties ilgtermiņā.</div>`;
    } else {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #3b82f6; background:rgba(59, 130, 246, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Funkcionāla Stabilitāte:</b> Spēj pielāgoties bez panikas, taču saglabā zināmu stabilitātes bāzi ikdienā.</div>`;
    }

    let desc = "";
    if (params.isDetailedUI) {
        desc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>`;
        desc += `<b style="color:#64748b;">Aprēķins:</b><br>• ` + breakdown.join("<br>• ") + `<br><b>Gala rezultāts: ${score}%</b>`;
        desc += `</div>${diagText}`;
    }

    return { score, html: desc };
}
