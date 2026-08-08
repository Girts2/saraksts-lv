export function calculateBaZiResourceIntegrity(params) {
    let mainGod = params.mainGod || "";
    let hiddenGod = params.hiddenGod || "";
    let gods = params.gods || [];

    let rScore = 0; // Default: No resource
    let diagText = "Resursa elements kartē nav atrasts. Lēmumi pilnībā balstās uz izdzīvošanu vai kailu loģiku.";

    let godsArray = Array.isArray(gods) ? gods : (typeof gods === 'object' && gods !== null ? Object.values(gods) : []);
    
    let hasDR = godsArray.includes("Direct_Resource");
    let hasIR = godsArray.includes("Indirect_Resource");

    if (mainGod === "Direct_Resource") {
        rScore = 100;
        diagText = "Tiešais Resurss ir Mēneša pīlārā (Galvenais profils). Iedzimta augsta morāle un godaprāts.";
    } else if (hasDR) {
        rScore = 80;
        diagText = "Tiešais Resurss atrodams kartē. Ir spēcīga sirdsapziņa un izpratne par pareizo/nepareizo.";
    } else if (hiddenGod === "Direct_Resource") {
        rScore = 60;
        diagText = "Tiešais Resurss ir slēpts. Iekšēji jūt morāles robežas.";
    } else if (mainGod === "Indirect_Resource") {
        rScore = 10;
        diagText = "Netiešais Resurss ir galvenais profils. Analītiķis un izdzīvotājs, var apiet morāli savā labā.";
    } else if (hasIR) {
        rScore = 20;
        diagText = "Netiešais Resurss ir kartē. Bieži rīkojas pēc situācijas izdevīguma, nevis ideāliem.";
    }

    let rDesc = "";
    if (params.isDetailedUI) {
        rDesc = "<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>";
        rDesc += `<b>10 Dievību Analīze:</b> ${diagText}<br>`;
        rDesc += `<br><b style="color:#64748b;">Aprēķins:</b> Galvenā dievība: ${mainGod || "Nav"}, Slēptā: ${hiddenGod || "Nav"}. Pārējās kartē: ${godsArray.join(", ") || "Nav"}. Rezultāts: <b>${rScore}%</b>.<br>`;
        rDesc += "</div>";

        let rSummary = `<br><b style="color:#f59e0b; font-size:0.85rem;">🛡 Resurss (Integritāte un Sirdsapziņa):</b> <span style="font-size:0.85rem; color:#334155;">${rScore}% iekšējais kodols.</span>`;
        if (rScore >= 81) {
            rSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #6366f1; background:rgba(99, 102, 241, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Pārlieku jūtīgais ideālists:</b> Morāle ir tik augsta, ka tā kļūst nepraktiska. Cilvēks var ieslīgt pašpārmetumos un analīzē, nespējot rīkoties, jo baidās pieļaut kaut mazāko kļūdu pret savu godaprātu.</div>`;
        } else if (rScore >= 51) {
            rSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Goda vīrs / sieva:</b> Skaidra sirdsapziņa. Viņam ir svarīgi būt "labajam tēlam" ne tikai citu, bet arī savās acīs. Viņš jūt fizisku diskomfortu, ja rīkojas negodīgi vai neizpilda solīto.</div>`;
        } else if (rScore >= 21) {
            rSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #f59e0b; background:rgba(245, 158, 11, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Ierobežota empātija:</b> Sirdsapziņa darbojas, bet tā ir klusa. Cilvēks saprot, kas ir pareizi, bet spēj atrast sev attaisnojumus ("visi tā dara"), lai apietu morāles normas, ja situācija kļūst sarežģīta.</div>`;
        } else {
            rSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Pliks aprēķinātājs:</b> Rīkojas bez iekšēja morāles filtra. Viņa integritāte ir atkarīga no acumirklīgā izdevīguma. Viņam nav sirdsapziņas pārmetumu, ja solījums netiek turēts, ja vien tas neapdraud viņa drošību.</div>`;
        }
        
        rDesc += rSummary;
    }

    return {
        score: rScore,
        html: rDesc
    };
}
