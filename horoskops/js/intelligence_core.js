/**
 * intelligence_core.js - "Sistēmas X" Sintēzes Dzinējs
 * Šī funkcija veic starpsistēmu datu korelāciju.
 */

export function generateExecutiveSummary(allData) {
    const { vedic, western, bazi, maya_profile, hellenistic } = allData;

    // 1. Identificēt "Sistēmas dzinēju" (BaZi + Maiji)
    const baziElem = bazi.elements || {};
    const getElem = (name) => baziElem[name] || 0;
    
    // ============================================
    // VECĀS TEKSTA BĀZES LOGIKAS ATJAUNOŠANA
    // ============================================
    const engine = `Subjekts ir ${bazi.Daymaster?.polarity} ${bazi.Daymaster?.element} tips ar ${maya_profile.oracle?.sealName} dzinējspēku. Tas liecina par ${getElem("Metāls") > 30 ? 'augstu disciplīnu un kontroli' : 'augstu adaptācijas vai resursu absorbēšanas spēju'}.`;

    const hasConflicts = western.psychology && western.psychology.criticalAspectsBlocks && western.psychology.criticalAspectsBlocks.length > 0;
    let conflictName = "Nezināms konflikts";
    if (hasConflicts) conflictName = western.psychology.criticalAspectsBlocks[0].name;
    
    const moonSgn = western.planets?.["Meness"]?.sign || "Nezināms";
    const isMoonScorpio = moonSgn === "Skorpions";

    let conflict = "";
    if (hasConflicts && isMoonScorpio && conflictName.includes("Saturns") && conflictName.includes("Saule")) {
        conflict = `Kritiskā nestabilitāte: ${conflictName}. Subjekts slēpj dziļu nepilnvērtības kompleksu aiz paranojas un kontroles (Mēness Skorpionā + Saule/Saturna spriedze).`;
    } else if (hasConflicts) {
        conflict = `Kritiskā nestabilitāte: ${conflictName}. Vēdu/Rietumu bāze norāda uz iekšēju spriedzi, kas var izsaukt destabilizāciju zem spiediena.`;
    } else {
        conflict = "Iekšējā sistēma ir relatīvi stabila un grūti izjaucama no ārpuses.";
    }

    const lilithSign = western.planets?.["Lilith"]?.sign || "Nezināms";
    const sectType = hellenistic.data?.sect || "Diena";
    const manipulation = `Ietekmēšanas vektors: ${sectType === 'Diena' ? 'Racionāla autoritāte' : 'Emocionāla manipulācija'}. Vājais punkts/Šantāžas potenciāls: pozīcija ${lilithSign} zīmē.`;

    let forecast = "";
    if (bazi.conflicts && bazi.conflicts.length > 0) {
        forecast = `BRĪDINĀJUMS: Sistēmā fiksēta strukturāla (${bazi.conflicts[0].pair.join('-')}) sadursme. Subjekts pieļaus būtiskas kļūdas pie straujām sistēmiskām pārmaiņām.`;
    } else {
        forecast = "Subjekts saglabā operatīvo kontroli pat pie ilgstošas slodzes un negaidītiem pavērsieniem.";
    }

    if (getElem("Metāls") >= 30 && maya_profile.oracle?.sealName?.includes("Chicchan")) {
        conflict = "UZMANĪBU: Subjekts ir letāli efektīvs izpildītājs ar minimālu empātiju (Izteikts Metāls + Maiju Čūskas instinkts).";
    }

    const marsP = western.planets?.["Marss"];
    if (sectType === 'Nakts' && marsP && marsP.house === 8) {
        forecast = "Kritisks brīdinājums: Paaugstināts vardarbības vai pašdestrukcijas risks krīzes situācijās (Nakts tips ar Marsu destrukcijas laukā).";
    }

    // ============================================
    // I. BIG FIVE APRĒĶINS
    // ============================================
    let extraversion = 50;
    if (sectType === "Diena") extraversion += 15; else extraversion -= 15;
    extraversion += (getElem("Uguns") * 0.5) - (getElem("Ūdens") * 0.3) - (getElem("Metāls") * 0.2);
    const mColor = maya_profile.basic?.color || "";
    if (mColor === "Sarkanais" || mColor === "Dzeltenais") extraversion += 10;
    else if (mColor === "Baltais" || mColor === "Zilais") extraversion -= 10;
    
    let neuroticism = 40;
    const humors = hellenistic.data?.humor || "";
    if (humors.includes("Melancholic")) neuroticism += 20;
    if (humors.includes("Choleric")) neuroticism += 10;
    const conflictsCount = (western.psychology?.criticalAspectsBlocks || []).length;
    neuroticism += conflictsCount * 15;
    if (western.planets?.["Meness"]?.sign === "Skorpions") neuroticism += 20;

    let conscientiousness = 50;
    conscientiousness += (getElem("Zeme") * 0.5) + (getElem("Metāls") * 0.3);
    if (sectType === "Diena") conscientiousness += 10;
    const saturn = western.planets?.["Saturns"] || {};
    if ([1, 4, 7, 10].includes(saturn.house)) conscientiousness += 15;

    let agreeableness = 50;
    agreeableness += (getElem("Ūdens") * 0.4) + (getElem("Koks") * 0.3) - (getElem("Metāls") * 0.5);
    if (sectType === "Nakts") agreeableness += 10;
    if (humors.includes("Phlegmatic")) agreeableness += 20;
    if (humors.includes("Choleric")) agreeableness -= 20;

    let openness = 50;
    openness += (getElem("Ūdens") * 0.4) + (getElem("Uguns") * 0.2) + (getElem("Gaiss") * 0.5 /* nosacīti no Vēdām/Rietumiem*/);
    const tone = maya_profile.basic?.tone || 1;
    if ([2, 3, 7, 11, 12].includes(tone)) openness += 15;
    if ([4, 8].includes(tone)) openness -= 15;

    const clamp = (val) => Math.min(Math.max(Math.round(val), 0), 100);
    extraversion = clamp(extraversion);
    neuroticism = clamp(neuroticism);
    conscientiousness = clamp(conscientiousness);
    agreeableness = clamp(agreeableness);
    openness = clamp(openness);

    const makeBar = (val) => {
        const total = 10;
        const filled = Math.round(val / 10);
        return "[" + "■".repeat(filled) + "□".repeat(total - filled) + "] " + val + "%";
    };

    const bigFiveHtml = `
        <div style="font-family: inherit; margin-bottom: 0.5rem;">
            <div style="color:#10b981;">Ekstraversija: ${makeBar(extraversion)} 
                <span style="color:#64748b; font-size:0.85em;">(${extraversion > 60 ? 'Aktīvā bāze, sociālais spēks' : 'Iekšēja izolācija, slepenība'})</span></div>
            <div style="color:${neuroticism > 70 ? '#ef4444' : '#10b981'};">Neirotisms: &nbsp;&nbsp;${makeBar(neuroticism)} 
                <span style="color:#64748b; font-size:0.85em;">(${neuroticism > 70 ? 'Kritiska emoc. nestabilitāte; šantāžas risks' : 'Stabils, aukstasinīgs zem spiediena'})</span></div>
            <div style="color:#10b981;">Apzinīgums: &nbsp;&nbsp;${makeBar(conscientiousness)} 
                <span style="color:#64748b; font-size:0.85em;">(${conscientiousness > 60 ? 'Stingri ievēro protokolus un struktūru' : 'Haotisks, bieži pārkāpj robežas'})</span></div>
            <div style="color:${agreeableness < 30 ? '#ef4444' : '#10b981'};">Piekāpība: &nbsp;&nbsp;&nbsp;${makeBar(agreeableness)} 
                <span style="color:#64748b; font-size:0.85em;">(${agreeableness < 30 ? 'Letāli cinisks, neiespaidojams' : 'Viegli manipulējams caur empātiju, uzticīgs'})</span></div>
            <div style="color:#10b981;">Atvērtība: &nbsp;&nbsp;&nbsp;${makeBar(openness)} 
                <span style="color:#64748b; font-size:0.85em;">(${openness > 60 ? 'Radoša nelinearitāte, adaptīvs' : 'Aizvērts konceptiem ārpus savas shēmas'})</span></div>
        </div>
    `;

    // ============================================
    // II. STRATĒĢISKIE DZINĒJI UN UZTICAMĪBA
    // ============================================
    const mainGod = bazi.mainGod || "Nezināms profils";
    let baziDrive = "Stabilitāte un kontrole";
    if (mainGod.includes("Seven Killing") || mainGod.includes("7K")) baziDrive = "Vara bez skrupuliem";
    if (mainGod.includes("Direct Wealth") || mainGod.includes("DW")) baziDrive = "Resursu akumulācija";
    if (mainGod.includes("Hurting Officer") || mainGod.includes("HO")) baziDrive = "Sistēmas laušana";
    const mayaDestiny = maya_profile.oracle?.sealName || "Apslēpts stars";
    const spiritSign = hellenistic.data?.spiritLot?.sign || "Nezināms";

    let reliability = 50;
    const helmsman = hellenistic.data?.helmsman || {};
    if (helmsman.condition?.includes("Trijumfējošs")) reliability += 30;
    if (conscientiousness > 60) reliability += 20;
    if (neuroticism > 80) reliability -= 30;
    reliability = clamp(reliability);

    let reliabilityDesc = "Zem spiediena sistemātiski maina pusi (pārbēdzēja risks).";
    if (reliability > 70) reliabilityDesc = "Subjekts ir dzelžaini stabils un uzticams. Nodevība maz ticama.";
    else if (reliability > 40) reliabilityDesc = "Vidēja stabilitāte. Turēs vārdu tikai izdevīguma ietvaros.";

    const motorsHtml = `
        <div style="margin-bottom: 0.5rem; border-left: 2px solid #3b82f6; padding-left: 0.8rem;">
            <div><span style="color:#f59e0b">Primārā struktūra:</span> Vara / Ideoloģija (BaZi profils: ${mainGod}).</div>
            <div><span style="color:#f59e0b">Sekundārais dzinulis:</span> Tīra izpilde (Maiju kods: ${mayaDestiny}).</div>
            <div><span style="color:#f59e0b">Vērtību sistēma:</span> Pragmātisms caur ${spiritSign} (Sengrieķu Gara Daļa).</div>
            <div><span style="color:#f59e0b">Uzticamības Reitings (Reliability):</span> ${makeBar(reliability)} <br><i>-> ${reliabilityDesc}</i></div>
        </div>
    `;

    // ============================================
    // III. OPERATĪVĀ TAKTIKA UN TEMPS
    // ============================================
    const mars = western.planets?.["Marss"] || { sign: "Nezināms" };
    let tempDesc = "Mērens, taktisks";
    const fastMars = ["Auns", "Lauva", "Strēlnieks", "Dvīņi"];
    const slowMars = ["Vērsis", "Zivis", "Mežāzis"];
    if (fastMars.includes(mars.sign) || [1, 5, 9, 13].includes(tone)) tempDesc = "Zibenīga reakcija, nepanes gaidīšanu (Auna Marss/Tonis >)";
    if (slowMars.includes(mars.sign) || [4, 6].includes(tone)) tempDesc = "Ārkārtīgi de-akselerēta dinamika, lēns un masīvs trieciens.";

    let attackStyle = "Neierasti tiešs";
    if (humors.includes("Melancholic")) attackStyle = "Analītiska sabotāža no iekšpuses.";
    else if (humors.includes("Choleric")) attackStyle = "Holeriska agresija. Tiecas uzbrukt pirmais apspēlējot vājumus.";

    const taktikaHtml = `
        <div style="margin-bottom: 0.5rem; border-left: 2px solid #eab308; padding-left: 0.8rem;">
            <div><span style="color:#8b5cf6">Zem spiediena:</span> ${attackStyle}</div>
            <div><span style="color:#8b5cf6">Operatīvais temps:</span> ${tempDesc}</div>
            <div><span style="color:#8b5cf6">Krīzes rīcība:</span> Cīņa vs Analīze bāzēta uz Marsu iekš ${mars.sign}.</div>
        </div>
    `;

    // ============================================
    // IV. EKSPLUATĀCIJAS PUNKTI UN ŠANTĀŽA
    // ============================================
    const lilith = western.planets?.["Lilith"]?.sign || "Skorpions";
    const saturnHouse = saturn.house || "?";

    const exploitationsHtml = `
        <div style="margin-bottom: 0.5rem; border-left: 2px solid #ef4444; padding-left: 0.8rem;">
            <div><span style="color:#ef4444">Rēta (Saturna Bloks):</span> Neaizsargātība ${saturnHouse}. mājas sfērā. Bailes no zaudējuma.</div>
            <div><span style="color:#ef4444">Aizliegtā zona (Lilita):</span> Patoloģiska nosliece ${lilith} zīmes ēnā.</div>
            <div><span style="color:#ef4444">Sistēmiskais Kaitnieks:</span> ${hellenistic.data?.sect === "Diena" ? "Marss (Neparedzama vardarbība)" : "Saturns (Izolācijas tieksme)"} kā subjekta ahileja papēdis.</div>
        </div>
    `;

    // ============================================
    // V. INTEGRĀCIJAS ROKASGRĀMATA (USER MANUAL)
    // ============================================
    const antipode = maya_profile.oracle?.antipode || "Maiju Izaicinājums";
    const analog = maya_profile.oracle?.analog || "Maiju Atbalsts";

    const userManualHtml = `
        <div style="margin-bottom: 0.5rem; border-left: 2px solid #10b981; padding-left: 0.8rem; border-radius: 2px;">
            <div><span style="color:#6ee7b7">Provokācija:</span> Darbojaties ar bāzi: "${antipode}". Tas izsauks nekontrolētu sistēmas kļūdu un izsist subjektu no eņģēm.</div>
            <div><span style="color:#6ee7b7">Ietekmēšana:</span> Izmantojiet intelektuālu vai emocionālu glaimu formu balstoties uz: "${analog}". Tā ir slēptās piekļuves atslēga.</div>
        </div>
    `;

    return {
        title: "[ TOP SECRET // EYES ONLY ] OPERATĪVĀ AIZSARDZĪBAS/UZBRUKUMA DOSJĒ",
        summary: `${engine} ${conflict} ${manipulation} ${forecast}`,
        strategem: generateStrategem(allData),
        sections: {
            "I. PSIHOLOĢISKAIS PROFILS (BIG FIVE)": bigFiveHtml,
            "II. STRATĒĢISKIE DZINĒJI UN UZTICAMĪBA": motorsHtml,
            "III. OPERATĪVĀ TAKTIKA UN TEMPS": taktikaHtml,
            "IV. EKSPLUATĀCIJAS PUNKTI UN ĀĶI": exploitationsHtml,
            "V. INTEGRĀCIJAS ROKASGRĀMATA (USER MANUAL)": userManualHtml
        }
    };
}

/**
 * Ģenerē specifisku rīcības taktiku pret subjektu
 */
function generateStrategem(data) {
    const mars = data.western.planets?.["Marss"] || { sign: "Nezināms" };
    const dm = data.bazi?.Daymaster?.element || "Nezināms";

    if (mars.sign === 'Skorpions' || dm === 'Metāls') {
        return "STRATEGĒMA: 'Aplenkums'. Tiešs uzbrukums būs neefektīvs. Izmantot resursu izolāciju un lēnu spiedienu.";
    }
    if (mars.sign === 'Dvīņi' || dm === 'Ūdens') {
        return "STRATEGĒMA: 'Trojas zirgs'. Izmantot dezinformāciju un viltus sociālos kontaktus. Subjekts ir informācijas hameleons.";
    }
    return "STRATEGĒMA: 'Zelta būris'. Piedāvāt statusu, drošību un redzamu vērtību apmaiņā pret sadarbību.";
}
