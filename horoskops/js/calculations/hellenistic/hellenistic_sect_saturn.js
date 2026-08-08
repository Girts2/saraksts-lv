export function calculateHellenisticSectSaturn(params) {
    let score = 50;
    let breakdown = [];
    const sect = params.sect || "Diena";

    if (sect === "Diena") {
        score = 100;
        breakdown.push(`Dienas Sekte (Saule virs horizonta): Saturns ir 'komandas biedrs' un konstruktīvs likumdevējs (+50%).`);
    } else {
        score = 30;
        breakdown.push(`Nakts Sekte (Saule zem horizonta): Saturns ir 'iebrucējs'. Atbildība tiek uztverta kā sods (-20%).`);
    }

    let diagText = "";
    if (score >= 70) {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #10b981; background:rgba(16, 185, 129, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Dabiska Atbildība:</b> Persona sociālos likumus un pienākumus pieņem dabiski, uztverot tos kā dzīves sastāvdaļu, nevis apspiešanu.</div>`;
    } else {
        diagText = `<div style="margin-top:6px; padding:8px; border-left:2px solid #ef4444; background:rgba(239, 68, 68, 0.05); font-size:0.8rem; color:#1e293b; line-height:1.4;"><b>Iekšējā Pretestība:</b> Pienākums tiek uztverts kā ārējs uzspiedums vai nasta. Var izpildīt prasīto, bet bieži izjūt vēlmi no tā atbrīvoties.</div>`;
    }

    let desc = "";
    if (params.isDetailedUI) {
        desc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>`;
        desc += `<b style="color:#64748b;">Aprēķins:</b><br>• ` + breakdown.join("<br>• ") + `<br><b>Gala rezultāts: ${score}%</b>`;
        desc += `</div>${diagText}`;
    }

    return { score, html: desc };
}
