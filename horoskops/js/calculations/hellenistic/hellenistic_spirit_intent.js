const TROP_SIGNS = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];

const signLords = {
    "Auns": "Marss", "Vērsis": "Venera", "Dvīņi": "Merkurs", "Vēzis": "Meness",
    "Lauva": "Saule", "Jaunava": "Merkurs", "Svari": "Venera", "Skorpions": "Marss",
    "Strēlnieks": "Jupiters", "Mežāzis": "Saturns", "Ūdensvīrs": "Saturns", "Zivis": "Jupiters"
};

export function calculateHellenisticSpiritIntent(params) {
    let score = 50;
    let breakdown = [];
    
    const ascPlanet = params.planets["Ascendant"];
    if (!ascPlanet) return { score, html: "Ascendants nav noteikts." };
    const ascSignIndex = Math.floor(ascPlanet.longitude / 30) % 12;

    const spiritSignName = params.spiritLotSign;
    const lordName = signLords[spiritSignName];
    if (!lordName) return { score, html: "Gara Lotes zīme nav noteikta." };

    const lordPlanet = params.planets[lordName];
    if (!lordPlanet) return { score, html: `Gara Lotes valdnieks (${lordName}) nav atrasts.` };

    const lordSignIndex = Math.floor(lordPlanet.longitude / 30) % 12;

    // Whole Sign House calculation
    const wholeSignHouse = (lordSignIndex - ascSignIndex + 12) % 12 + 1;

    breakdown.push(`Gara Lotes zīme ir ${spiritSignName}. Tradicionālais Valdnieks: ${lordName}.`);
    breakdown.push(`Valdnieks atrodas ${TROP_SIGNS[lordSignIndex]}. Whole Sign sistēmā tā ir ${wholeSignHouse}. māja.`);

    let diagText = "";
    if ([1, 4, 7, 10].includes(wholeSignHouse)) {
        score = 100;
        breakdown.push(`Stūra māja (Kentron). Maksimāla jauda (+100%).`);
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Spēcīga Griba:</b> Personai ir varas sviras pār savu dzīvi un lēmumiem. Ja viņš kaut ko apsola, viņš var garantēt izpildi, jo nav atkarīgs no ārējiem apstākļiem.</div>`;
    } else if ([2, 5, 8, 11].includes(wholeSignHouse)) {
        score = 65;
        breakdown.push(`Sekojošā māja (Epanaphora). Vidēja jauda (65%).`);
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #3b82f6; background:rgba(59, 130, 246, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Stabila Intence:</b> Persona mēģina noturēt savu lēmumu un parasti viņam tas izdodas, ja vien nav radikālu šķēršļu.</div>`;
    } else {
        score = 20;
        breakdown.push(`Krītošā māja (Apoklima). Zema jauda (20%).`);
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Apstākļu Upuris:</b> Var gribēt būt uzticams, bet dzīve pastāvīgi piespēlē situācijas (veselība, apstākļi, citi cilvēki), kurās viņš nespēj realizēt savu vārdu. Gribas vājums ikdienā.</div>`;
    }

    let desc = "";
    if (params.isDetailedUI) {
        desc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>`;
        desc += `<b style="color:#64748b;">Aprēķins:</b><br>• ` + breakdown.join("<br>• ") + `<br><b>Gala rezultāts: ${score}%</b>`;
        desc += `</div>${diagText}`;
    }

    return { score, html: desc };
}
