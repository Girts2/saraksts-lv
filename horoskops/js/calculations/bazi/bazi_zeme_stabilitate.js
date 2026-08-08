export function calculateBaZiEarthStability(params) {
    let earthPercent = params.earthElementPercent || 0;
    // 2 Zemes elementi no 8 pīlāriem ir 25%. Ja ir 25%, rezultāts ir 100%.
    let eScore = Math.floor(Math.min((earthPercent / 25) * 100, 100));

    let eDesc = "";
    if (params.isDetailedUI) {
        eDesc = "<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>";
        eDesc += `<b>Zemes elements bāzes kartē:</b> ${earthPercent}%<br>`;
        eDesc += `<br><b style="color:#64748b;">Aprēķins:</b> Katri 2.5% Zemes elementa dod 10% rezultātu. (${earthPercent}% = <b>${eScore}%</b>).<br>`;
        eDesc += "</div>";

        let eSummary = `<br><b style="color:#f59e0b; font-size:0.85rem;">🌍 Zemes Elements (Stabilitāte un Fiziskā Noturība):</b> <span style="font-size:0.85rem; color:#334155;">${eScore}% enkura spēks.</span>`;
        if (eScore >= 76) {
            eSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #6366f1; background:rgba(99, 102, 241, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Nekustīgs kalns:</b> Uzticamība robežojas ar stūrgalvību. Viņš turēs vārdu pat tad, ja tas ir kļuvis neloģiski vai kaitīgi. Viņu nav iespējams "izkustināt", kas reizēm traucē pielāgoties pārmaiņām.</div>`;
        } else if (eScore >= 46) {
            eSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Auglīga augsne:</b> Ideāls balanss. Cilvēks ir uzticams, stabils un piezemēts. Viņš saprot realitāti un tur doto vārdu, jo viņam piemīt dabisks smaguma centrs.</div>`;
        } else if (eScore >= 21) {
            eSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #f59e0b; background:rgba(245, 158, 11, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Mērens svārstīgums:</b> Stabilitāte parādās tikai tad, kad viss iet gludi. Pie pirmajām grūtībām vai stresa cilvēks "pazaudē pamatu zem kājām" un kļūst neprognozējams.</div>`;
        } else {
            eSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Plūstošās smiltis:</b> Cilvēkam pilnīgi trūkst iekšēja enkura. Viņš ir pārāk elastīgs, viegli maina viedokli un solījumus atkarībā no tā, kurp pūš vējš. Nav pamata, uz kura būvēt ilgtermiņa uzticību.</div>`;
        }
        
        eDesc += eSummary;
    }

    return {
        score: eScore,
        html: eDesc
    };
}
