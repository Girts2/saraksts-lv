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

export function calculateWesternSunJupiterEthics(params) {
    const p = params.planets || {};
    let score = 40;
    let breakdown = [];
    
    // 1. Saules un Jupitera pozīcijas
    let sun = p["Saule"];
    if (sun && sun.sign) {
        if (["Lauva", "Auns"].includes(sun.sign)) {
            score += 20; breakdown.push(`Saule spēcīgā zīmē (${sun.sign}) -> +20% lepnums/gods`);
        } else if (["Svari", "Ūdensvīrs"].includes(sun.sign)) {
            score -= 10; breakdown.push(`Saule vājā zīmē (${sun.sign}) -> -10% svārstīgums`);
        }
    }

    let jupiter = p["Jupiters"];
    if (jupiter && jupiter.sign) {
        if (["Strēlnieks", "Zivis", "Vēzis"].includes(jupiter.sign)) {
            score += 20; breakdown.push(`Jupiters spēcīgā zīmē (${jupiter.sign}) -> +20% augsti ideāli`);
        } else if (["Dvīņi", "Jaunava", "Mežāzis"].includes(jupiter.sign)) {
            score -= 10; breakdown.push(`Jupiters vājā zīmē (${jupiter.sign}) -> -10% praktisks/ierobežots`);
        }
    }

    // 2. Aspekti
    if (sun && jupiter) {
        let aspSJ = getAspect(sun.longitude, jupiter.longitude);
        if (aspSJ === "Conjunction" || aspSJ === "Trine" || aspSJ === "Sextile") { 
            score += 20; 
            breakdown.push(`Saule Harmonijā/Savienojumā ar Jupiteru (+20%). Cilvēkam dabiski riebjas melot, iekšējs taisnīgums.`); 
        }
        else if (aspSJ === "Square" || aspSJ === "Opposition") { 
            score -= 20; 
            breakdown.push(`Saule Spriedzē ar Jupiteru (-20%). Liekulības vai savu spēku pārvērtēšanas risks. "Mazie meli".`); 
        }
    }

    // Neptūna filtrs ētikai (ja Neptūns bojā Sauli)
    let neptune = p["Neptuns"];
    if (sun && neptune) {
        let aspSN = getAspect(sun.longitude, neptune.longitude);
        if (aspSN === "Square" || aspSN === "Opposition") {
            score -= 25;
            breakdown.push(`Neptūna Kvadrāts/Opozīcija ar Sauli (-25%). "Slidens" faktors. Apzināta vai neapzināta maldināšana.`);
        } else if (aspSN === "Conjunction") {
            score -= 15;
            breakdown.push(`Neptūna Savienojums ar Sauli (-15%). Māksliniecisks, bet "miglains" nodomos.`);
        }
    }

    if (score > 100) score = 100;
    if (score < 0) score = 0;

    let diagText = "";
    if (score >= 70) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Ideālists (Bruņinieks):</b> Rīcību vada augstāki principi un lepnums. Nekrāpjas, jo tas neatbilst viņa pašvērtējumam un goda kodeksam.</div>`;
    } else if (score <= 30) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Oportūnists:</b> Ētika ir elastīga. Rīcība atkarīga no situācijas izdevīguma. Nav problēmu apiet noteikumus, ja tas palīdz izdzīvot vai uzvarēt.</div>`;
    } else {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #3b82f6; background:rgba(59, 130, 246, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Praktisks Godīgums:</b> Pamatā godīgs, taču kritiskās situācijās spēj izdarīt kompromisus ar sirdsapziņu.</div>`;
    }

    let desc = "";
    if (params.isDetailedUI) {
        desc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>`;
        desc += `<b style="color:#64748b;">Aprēķins:</b><br>• ` + breakdown.join("<br>• ") + `<br><b>Gala rezultāts: ${score}%</b>`;
        desc += `</div>${diagText}`;
    }

    return { score, html: desc };
}
