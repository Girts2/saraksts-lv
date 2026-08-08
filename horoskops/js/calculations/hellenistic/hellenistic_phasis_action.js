export function calculateHellenisticPhasisAction(params) {
    let score = 100;
    let breakdown = [];
    
    const sun = params.planets["Saule"];
    const saturn = params.planets["Saturns"];
    if (!sun || !saturn) return { score: 50, html: "Planētas nav atrastas fāzes noteikšanai." };

    let diff = Math.abs(sun.longitude - saturn.longitude);
    if (diff > 180) diff = 360 - diff;

    const isCombust = diff < 15;
    const isRetro = params.isSaturnRetro;

    if (isCombust && isRetro) {
        score = 0;
        breakdown.push(`Sadedzis un Retrogrāds (Starpība ar Sauli ${diff.toFixed(1)}°). Sliktākais iespējamais stāvoklis (0%).`);
    } else if (isCombust) {
        score = 20;
        breakdown.push(`Sadedzis / Under the beams (Starpība ar Sauli ${diff.toFixed(1)}°). Neredzams karavīrs (20%).`);
    } else if (isRetro) {
        score = 20;
        breakdown.push(`Retrogrāds (Atpakaļejošs). Pārkāpj dabisko kārtību (20%).`);
    } else {
        score = 100;
        breakdown.push(`Tiešs un Redzams (Starpība ar Sauli ${diff.toFixed(1)}°). Maksimāla objektīvā rīcībspēja (100%).`);
    }

    let diagText = "";
    if (score === 100) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Skaidra Rīcība:</b> Solījumi pārvēršas darbos. Persona ir objektīvi spējīga realizēt savus plānus fiziskajā realitātē bez mistiskiem kavēkļiem.</div>`;
    } else if (score === 0) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Pilnīga Paralīze:</b> Persona var apsolīt, bet gan viņa rīcība ir neprognozējama, gan arī objektīvie apstākļi viņu pilnībā aprij. Pazūd "miglā".</div>`;
    } else {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #f59e0b; background:rgba(245, 158, 11, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Bloķēta Rīcība:</b> Objektīvi šķēršļi fiziskajai realizācijai. Vai nu darbiem nav rezultāta ("Sadedzis"), vai arī rīcība ir neprognozējama ("Retrogrāds").</div>`;
    }

    let desc = "";
    if (params.isDetailedUI) {
        desc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>`;
        desc += `<b style="color:#64748b;">Aprēķins:</b><br>• ` + breakdown.join("<br>• ") + `<br><b>Gala rezultāts: ${score}%</b>`;
        desc += `</div>${diagText}`;
    }

    return { score, html: desc };
}
