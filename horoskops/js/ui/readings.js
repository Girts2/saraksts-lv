export function generateAntardashaReading(maha, antar) {
    const planets = {
        "Saule": { env: "Statusa, varas un ego veidošanas skatuve", act: "vairāk spīdēt, uzņemties vadību un nostiprināt autoritāti" },
        "Meness": { env: "Emociju, privātās dzīves un ģimenes fons", act: "sajust piederību, sargāt tuvākos un meklēt sirdsmieru" },
        "Marss": { env: "Dinamiskas spriedzes, cīņas un enerģijas vide", act: "uzbrukt problēmām, drosmīgi rīkoties un ātri pārvarēt šķēršļus" },
        "Merkurs": { env: "Intelekta, biznesa, komunikācijas un loģikas skatuve", act: "analizēt, tirgoties, pārdot idejas un tīkloties" },
        "Jupiters": { env: "Izaugsmes, veiksmes un garīgās paplašināšanās vide", act: "apgūt ko jaunu, skolot citus, meklēt jēgu un riskēt ar peļņu" },
        "Venera": { env: "Baudas, dārgu resursu un caurviju diplomātijas fons", act: "mīlēt, piesaistīt bagātību un meklēt partnerību" },
        "Saturns": { env: "Lielu šķēršļu, pacietības un karmas atdeves skatuve", act: "smagi strādāt, klusēt, paciesties un lēnam strukturēt izdzīvošanu" },
        "Rahu": { env: "Ambīciju, ārpus rāmjiem domāšanas un izsalkušas enerģijas vide", act: "riskēt, izaicināt normas un meklēt ātrus materiālos ieguvumus" },
        "Ketu": { env: "Izolācijas, pēkšņu zaudējumu un garīgās introspekcijas skatuve", act: "atkāpties no materiālā burzmas, iedziļināties sevī un atlaist to, kas vairs nestrādā" }
    };

    let relMatrix = {
        "Saule_Meness": "Draugi", "Saule_Marss": "Draugi", "Saule_Jupiters": "Draugi", "Saule_Saturns": "Ienaidnieki", "Saule_Venera": "Ienaidnieki", "Saule_Rahu": "Ienaidnieki", "Saule_Ketu": "Ienaidnieki",
        "Meness_Saule": "Draugi", "Meness_Marss": "Neitrāli", "Meness_Jupiters": "Neitrāli", "Meness_Saturns": "Neitrāli", "Meness_Venera": "Neitrāli", "Meness_Merkurs": "Draugi", "Meness_Rahu": "Ienaidnieki", "Meness_Ketu": "Ienaidnieki",
        "Marss_Saule": "Draugi", "Marss_Meness": "Draugi", "Marss_Jupiters": "Draugi", "Marss_Saturns": "Neitrāli", "Marss_Venera": "Neitrāli", "Marss_Merkurs": "Ienaidnieki", "Marss_Rahu": "Ienaidnieki", "Marss_Ketu": "Neitrāli",
        "Merkurs_Saule": "Draugi", "Merkurs_Venera": "Draugi", "Merkurs_Marss": "Neitrāli", "Merkurs_Jupiters": "Neitrāli", "Merkurs_Saturns": "Neitrāli", "Merkurs_Meness": "Ienaidnieki", "Merkurs_Rahu": "Neitrāli", "Merkurs_Ketu": "Neitrāli",
        "Jupiters_Saule": "Draugi", "Jupiters_Meness": "Draugi", "Jupiters_Marss": "Draugi", "Jupiters_Saturns": "Neitrāli", "Jupiters_Merkurs": "Ienaidnieki", "Jupiters_Venera": "Ienaidnieki", "Jupiters_Rahu": "Neitrāli", "Jupiters_Ketu": "Neitrāli",
        "Venera_Merkurs": "Draugi", "Venera_Saturns": "Draugi", "Venera_Marss": "Neitrāli", "Venera_Jupiters": "Neitrāli", "Venera_Saule": "Ienaidnieki", "Venera_Meness": "Ienaidnieki", "Venera_Rahu": "Draugi", "Venera_Ketu": "Neitrāli",
        "Saturns_Merkurs": "Draugi", "Saturns_Venera": "Draugi", "Saturns_Jupiters": "Neitrāli", "Saturns_Saule": "Ienaidnieki", "Saturns_Meness": "Ienaidnieki", "Saturns_Marss": "Ienaidnieki", "Saturns_Rahu": "Draugi", "Saturns_Ketu": "Ienaidnieki",
        "Rahu_Venera": "Draugi", "Rahu_Saturns": "Draugi", "Rahu_Merkurs": "Draugi", "Rahu_Saule": "Ienaidnieki", "Rahu_Meness": "Ienaidnieki", "Rahu_Marss": "Ienaidnieki", "Rahu_Ketu": "Ienaidnieki",
        "Ketu_Saule": "Ienaidnieki", "Ketu_Meness": "Ienaidnieki", "Ketu_Saturns": "Ienaidnieki", "Ketu_Rahu": "Ienaidnieki"
    };

    let mP = planets[maha] || planets["Saturns"];
    let aP = planets[antar] || planets["Saturns"];
    let relKey = maha + "_" + antar;
    let relStatus = maha === antar ? "Draugi (Pats ar sevi)" : (relMatrix[relKey] || "Neitrāli");
    
    let outcome = "";
    let alertColor = "";
    if (relStatus.includes("Draugi")) {
        outcome = "Planetārās enerģijas brīnišķīgi sadarbojas! Tavus centienus pavadīs veiksme un durvis atvērsies vieglāk. Šis ir lielisks un stratēģisks laiks, lai agresīvi stumtu lietas uz priekšu, veidotu kontaktus un vairotu ienākumus.";
        alertColor = "#10b981";
    } else if (relStatus.includes("Ienaidnieki")) {
        outcome = "Spēcīga iekšēja berze! Lielais fons traucē tavas apziņas aktuālajai vēlmei. Sagaidāma spriedze, regulāra aizkavēšanās un augsts stresa/konfliktu līmenis. Uzmanīgi sargi savu resursu bāzi, saglabā mieru, nepārpūlies un nepieņem impulsīvus lēmumus.";
        alertColor = "#ef4444";
    } else {
        outcome = "Mērena saderība. Spēles laukums ir stabils, bet virzība – pilnībā atkarīga no tevis paša. Liela veiksme 'no gaisa' neiekritīs, taču globālas krīzes arī nebūs. Fokusējies un vienkārši dari savu darbu.";
        alertColor = "#f59e0b";
    }

    return `
        <div style="background: white; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); position:relative; overflow:hidden;">
            <div style="position:absolute; top:0; left:0; width:6px; bottom:0; background:${alertColor}; box-shadow: 2px 0 10px ${alertColor}80;"></div>
            <div style="margin-left: 10px;">
                <h4 style="margin: 0 0 10px 0; color: #1e293b; font-size: 1.1rem; display:flex; justify-content:space-between; align-items:center;">
                    <span>🔎 Notikumu Horizonts: ${maha} (Fons) + ${antar} (Fokuss)</span>
                    <button onclick="document.getElementById('antar-reading-panel').style.display='none'" style="border:none; background:rgba(0,0,0,0.05); border-radius:4px; padding:2px 8px; cursor:pointer; font-size:1rem; color:#64748b; transition:background 0.2s;">Aizvērt</button>
                </h4>
                
                <p style="margin: 0 0 6px 0; font-size: 0.9rem; color: #475569;">
                    <b style="color: #64748b; display:inline-block; width:170px;">🌍 Aizkulišu fons (Maha):</b> ${mP.env}.
                </p>
                <p style="margin: 0 0 12px 0; font-size: 0.9rem; color: #475569;">
                    <b style="color: #64748b; display:inline-block; width:170px;">⚡ Tava rīcība (Antar):</b> ${aP.act}.
                </p>
                <div style="background: rgba(0,0,0,0.02); padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <b style="color: #1e293b; font-size: 0.9rem;">Analīzes Sintēze (${relStatus}):</b>
                    <p style="margin: 6px 0 0 0; font-size: 0.85rem; color: #334155; line-height:1.5;">${outcome}</p>
                </div>
            </div>
        </div>
    `;
}

export function showAntarReading(maha, antar, start, end) {
    const panel = document.getElementById('antar-reading-panel');
    if (panel) {
        panel.innerHTML = generateAntardashaReading(maha, antar);
        panel.style.display = 'block';
        panel.scrollIntoView({behavior: "smooth", block: "center"});
    }
}
