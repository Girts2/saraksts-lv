export function calculateBaZiOfficerExecution(params) {
    let mainGod = params.mainGod || "";
    let hiddenGod = params.hiddenGod || "";
    let gods = params.gods || [];

    let oScore = 40; // Default: neither rebellious nor perfectly disciplined
    let diagText = "Normāls rādītājs, bez izteiktiem galējībām.";

    let godsArray = Array.isArray(gods) ? gods : (typeof gods === 'object' && gods !== null ? Object.values(gods) : []);
    
    let hasDO = godsArray.includes("Direct_Officer");
    let has7K = godsArray.includes("Seven_Killings");

    let hasHO = godsArray.includes("Hurting_Officer");

    if (mainGod === "Direct_Officer") {
        oScore = 100;
        diagText = "Tiešā Amatpersona ir Mēneša pīlārā (Galvenais profils). Iedzimta super-disciplīna.";
    } else if (hasDO) {
        oScore = 80;
        diagText = "Tiešā Amatpersona atrodama kartē. Dabisks respekts pret noteikumiem.";
    } else if (hiddenGod === "Direct_Officer") {
        oScore = 60;
        diagText = "Tiešā Amatpersona ir slēpta. Noteikumi tiek ievēroti, bet tas nav dominējošs faktors.";
    } else if (mainGod === "Hurting_Officer") {
        oScore = 0;
        diagText = "Ievainojošā Amatpersona ir galvenais profils. Kategoriski noraida noteikumus un kontroli.";
    } else if (mainGod === "Seven_Killings") {
        oScore = 10;
        diagText = "Septiņas Slepkavības ir galvenais profils. Milzīga dumpiniecība, noraida autoritātes.";
    } else if (has7K || hasHO) {
        oScore = 20;
        diagText = "Slepkavības vai Ievainojošā Amatpersona ir kartē. Bieži pārkāpj noteikumus, lai panāktu savu mērķi.";
    }

    let oDesc = "";
    if (params.isDetailedUI) {
        oDesc = "<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>";
        oDesc += `<b>10 Dievību Analīze:</b> ${diagText}<br>`;
        oDesc += `<br><b style="color:#64748b;">Aprēķins:</b> Galvenā dievība: ${mainGod || "Nav"}, Slēptā: ${hiddenGod || "Nav"}. Pārējās kartē: ${godsArray.join(", ") || "Nav"}. Rezultāts: <b>${oScore}%</b>.<br>`;
        oDesc += "</div>";

        let oSummary = `<br><b style="color:#f59e0b; font-size:0.85rem;">🏛 Amatpersona (Izpildījums un Noteikumi):</b> <span style="font-size:0.85rem; color:#334155;">${oScore}% disciplīna.</span>`;
        if (oScore >= 86) {
            oSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #6366f1; background:rgba(99, 102, 241, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Stingras kārtības gūsteknis:</b> Pārlieku liela kontrole. Cilvēks var kļūt par perfekcionistu, kurš "izdeg" pats un "noēd" citus, cenšoties panākt ideālu kārtību. Atbildība kļūst par smagu, nospiedošu pienākumu.</div>`;
        } else if (oScore >= 56) {
            oSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Sistēmas balsts:</b> Augsta disciplīna. Šis cilvēks ciena hierarhiju, likumus un termiņus. Viņa iekšējais "policists" strādā nevainojami — ja darbs ir uzdots, tas tiks izdarīts pēc visiem priekšrakstiem.</div>`;
        } else if (oScore >= 26) {
            oSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #f59e0b; background:rgba(245, 158, 11, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Selektīvais izpildītājs:</b> Viņš pilda noteikumus tikai tad, ja tie šķiet loģiski vai izdevīgi. Viņam nav "automātiskas" atbildības sajūtas; katrs solījums tiek izsvērts — vai ir vērts to pildīt?</div>`;
        } else {
            oSummary += `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Brīvais gars / Dumpinieks:</b> Noteikumi un termiņi šim cilvēkam ir tikai ieteikumi. Viņam ir ļoti grūti pakļauties disciplīnai un viņš darīs visu, lai izvairītos no atbildības nastas.</div>`;
        }
        
        oDesc += oSummary;
    }

    return {
        score: oScore,
        html: oDesc
    };
}
