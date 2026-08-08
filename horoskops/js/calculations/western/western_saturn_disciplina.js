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

export function calculateWesternSaturnDiscipline(params) {
    const p = params.planets || {};
    let score = 40;
    let breakdown = [];
    
    // 1. Saturna pozīcija
    let saturn = p["Saturns"];
    if (saturn && saturn.sign) {
        if (["Mežāzis", "Ūdensvīrs", "Svari"].includes(saturn.sign)) {
            score = 60;
            breakdown.push(`Saturns spēcīgā zīmē (${saturn.sign}) -> Bāze 60%`);
        } else if (["Auns", "Vēzis", "Lauva"].includes(saturn.sign)) {
            score = 20;
            breakdown.push(`Saturns vājā zīmē (${saturn.sign}) -> Bāze 20%`);
        } else {
            breakdown.push(`Saturns neitrālā zīmē (${saturn.sign}) -> Bāze 40%`);
        }
    } else {
        breakdown.push(`Saturna zīme nav noteikta -> Bāze 40%`);
    }

    // 2. Aspekti
    if (saturn && p["Saule"]) {
        let aspSS = getAspect(saturn.longitude, p["Saule"].longitude);
        if (aspSS === "Conjunction") { score += 20; breakdown.push(`Saule Savienojumā ar Saturnu (+20%). Super-uzticams, stingrs.`); }
        else if (aspSS === "Trine" || aspSS === "Sextile") { score += 15; breakdown.push(`Saule Harmonijā ar Saturnu (+15%). Dabisks atbildības līmenis.`); }
        else if (aspSS === "Square" || aspSS === "Opposition") { score -= 10; breakdown.push(`Saule Spriedzē ar Saturnu (-10%). Atbildība ir liels stress, izdegšanas risks.`); }
    }
    
    if (saturn && p["Merkurs"]) {
        let aspSM = getAspect(saturn.longitude, p["Merkurs"].longitude);
        if (aspSM === "Conjunction" || aspSM === "Trine" || aspSM === "Sextile") { score += 15; breakdown.push(`Merkurs Harmonijā ar Saturnu (+15%). Solījumi un vārdi tiek turēti.`); }
        else if (aspSM === "Square" || aspSM === "Opposition") { score -= 10; breakdown.push(`Merkurs Spriedzē ar Saturnu (-10%). Saziņas un termiņu iekšējs stress.`); }
    }
    
    if (saturn && p["Ascendant"]) {
        let aspSA = getAspect(saturn.longitude, p["Ascendant"].longitude);
        if (aspSA === "Conjunction" || aspSA === "Trine") { score += 10; breakdown.push(`Ascendants Harmonijā ar Saturnu (+10%). Izstaro uzticamību un nopietnību.`); }
    }

    if (score > 100) score = 100;
    if (score < 0) score = 0;

    let diagText = "";
    if (score >= 70) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Strukturēts Izpildītājs:</b> Persona ir dabiski strukturēta un uztver pienākumu kā dzīves jēgu. Pabeidz iesākto, jo nevar atļauties haosu.</div>`;
    } else if (score <= 30) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Noteikumu Izbēdzējs:</b> Persona izjūt Saturnu kā apgrūtinājumu un cenšas izvairīties no ierobežojumiem. Atbildība viņam šķiet "nasta", ko gribas nomest.</div>`;
    } else {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #3b82f6; background:rgba(59, 130, 246, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Veselīga Atbildība:</b> Pilda noteikumus, kad tie ir nepieciešami, taču nepadara disciplīnu par apsēstību.</div>`;
    }

    let desc = "";
    if (params.isDetailedUI) {
        desc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>`;
        desc += `<b style="color:#64748b;">Aprēķins:</b><br>• ` + breakdown.join("<br>• ") + `<br><b>Gala rezultāts: ${score}%</b>`;
        desc += `</div>${diagText}`;
    }

    return { score, html: desc };
}
