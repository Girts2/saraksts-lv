export const MAYAN_ARCHETYPES = {
    0: { name: "Pūķis (Imix)", type: "Piegādes vadītājs", desc: "Sākotnējais dzinējs. Baro projektus un cilvēkus." },
    1: { name: "Vējš (Ik)", type: "Propagandists/Vēstnesis", desc: "Komunikācijas virpulis. Informācijas aprite." },
    2: { name: "Nakts (Akbaļ)", type: "Analītiķis/Slepenais aģents", desc: "Zemapziņas pētnieks. Darbojas klusumā un tumsā." },
    3: { name: "Sēkla (Kan)", type: "Arhitekts/Stratēģis", desc: "Mērķtiecīga sēkla. Plānošana un precizitāte." },
    4: { name: "Čūska (Čikčan)", type: "Speciālo uzdevumu veicējs", desc: "Instinktīvā jauda. Fiziska izdzīvošana un jutekļi." },
    5: { name: "Pasaules Savienotājs (Kimi)", type: "Sistēmu reorganizētājs", desc: "Hierarhijas un transformācijas meistars." },
    6: { name: "Roka (Maņik)", type: "Tehniskais izpildītājs", desc: "Realizācijas spēks. Instrumentu pārvaldība." },
    7: { name: "Zvaigzne (Lamat)", type: "Tēla veidotājs/Diplomāts", desc: "Skaistuma un harmonijas kods." },
    8: { name: "Mēness (Muļuk)", type: "Kolektīva noskaņojuma vadītājs", desc: "Emocionālais filters. Atmiņa un plūsma." },
    9: { name: "Suns (Ok)", type: "Lojāls izmeklētājs/Pavadonis", desc: "Lojalitātes kodols. Komandas gars." },
    10: { name: "Pērtiķis (Čuven)", type: "Dezinformators/Mākslinieks", desc: "Spēles un ilūzijas meistars." },
    11: { name: "Cilvēks (Eb)", type: "Ilgtermiņa administrators", desc: "Cilvēciskā vieduma kauss. Izturība un griba." },
    12: { name: "Debesu gājējs (Ben)", type: "Pētnieks/Infiltrators", desc: "Debesu gājējs. Robežu pārkāpējs." },
    13: { name: "Burvis (Hiš)", type: "Vērotājs/Pelēkais kardināls", desc: "Šamaniskā redze. Darbojas ārpus laika." },
    14: { name: "Ērglis (Men)", type: "Globālais stratēģis", desc: "Vīzijas ērglis. Redz kopbildi." },
    15: { name: "Kariote (Kib)", type: "Iekšējās drošības sargs", desc: "Kariote/Viedums. Ticība savai iekšējai balsij." },
    16: { name: "Zeme (Kaban)", type: "Pārmaiņu katalizators", desc: "Zemes evolūcija. Kustības iniciators." },
    17: { name: "Spogulis (Ecnab)", type: "Iekšējais revidents/Atmaskotājs", desc: "Patiesības nazis. Griež pušu melus." },
    18: { name: "Vētra (Kavak)", type: "Krīzes menedžeris", desc: "Vētras katalizators. Pašatjaunošanās caur haosu." },
    19: { name: "Saule (Ahav)", type: "Ideoloģiskais līderis", desc: "Saules apziņa. Augstākā vadība un gaisma." }
};

export const MAYAN_TONES = {
    1: "1. Magnētiskais (Sākums): Piesaista resursus un cilvēkus. Sākuma punkts.",
    2: "2. Lunārais (Polaritāte): Darbojas caur polaritāti un izaicinājumu. Redz šķēršļus.",
    3: "3. Elektriskais (Ritms): Aktivizē un savieno. Enerģijas devējs.",
    4: "4. Pašeksistējošais (Forma): Definē formu un struktūru. Praktisks plānotājs.",
    5: "5. Virstoņa (Centrs): Piešķir jaudu un autoritāti. Komandējošais tips.",
    6: "6. Ritmiskais (Līdzsvars): Organizē efektivitāti un balansu. Darba zirgs.",
    7: "7. Rezonanses (Iedvesma): Iedvesmo un nolasot apkārtējo vidi. 'Antena'.",
    8: "8. Galaktiskais (Integritāte): Modelē un harmonizē. Integritātes sargs.",
    9: "9. Saules (Nodoms): Realizē un manifestē. Uz mērķi vērsts spēks.",
    10: "10. Planetārais (Manifestācija): Pilnveido un ražo rezultātu. Finālists.",
    11: "11. Spektrālais (Atbrīvošanās): Atbrīvo un kliedē. Vecā graujējs.",
    12: "12. Kristāliskais (Sadarbība): Sadarbojas un dalās. Tīklošanas meistars.",
    13: "13. Kosmiskais (Pāreja): Pārspēj un transformē. Nākamā līmeņa pāreja."
};

export const EARTH_FAMILIES = {
    "Polar": { name: "Polārā ģimene", role: "Skaņas/Idejas uztveršana un nosūtīšana. Sistēmas 'Antenas'.", signs: [19, 4, 9, 14] }, // Ahau, Chicchan, Oc, Men
    "Cardinal": { name: "Kardinālā ģimene", role: "Enerģijas ievirzīšana un sākšana. Sistēmas 'Iniciatori'.", signs: [0, 5, 10, 15] }, // Imix, Cimi, Chuen, Cib
    "Core": { name: "Centrālo ģimene", role: "Enerģijas filtrēšana un fokusēšana. Sistēmas 'Procesori'.", signs: [1, 6, 11, 16] }, // Ik, Manik, Eb, Caban
    "Signal": { name: "Signālu ģimene", role: "Informācijas izplatīšana un ziņošana. Sistēmas 'Komunikatori'.", signs: [2, 7, 12, 17] }, // Akbal, Lamat, Ben, Etznab
    "Gateway": { name: "Vārtu ģimene", role: "Procesu noslēgšana un sagatavošana jaunam ciklam. Sistēmas 'Finālisti'.", signs: [3, 8, 13, 18] } // Kan, Muluc, Ix, Cauac
};

export const COLOR_DYNAMICS = {
    "Sarkans": { phase: "Iniciācija", operative: "Subjekts vislabāk darbojas projektu sākuma stadijā. Meklē jaunas teritorijas." },
    "Balts": { phase: "Attīrīšana", operative: "Subjekts ir procesa optimizētājs. Atmet lieko, fokusējas uz būtību." },
    "Zils": { phase: "Transformācija", operative: "Subjekts ir haosa menedžeris. Pārvērš šķēršļus iespējās." },
    "Dzeltens": { phase: "Nogatavināšana", operative: "Subjekts ir vērsts uz galarezultātu un ražu. Pabeidz iesākto." }
};

export const getColorFromIndex = (idx) => {
    const rootColorIdx = idx % 4;
    return ["Sarkans", "Balts", "Zils", "Dzeltens"][rootColorIdx];
};

export const getEarthFamily = (idx) => {
    for (let fKey in EARTH_FAMILIES) {
        if (EARTH_FAMILIES[fKey].signs.includes(idx)) {
            return EARTH_FAMILIES[fKey];
        }
    }
    return { name: "Nezināms", role: "Nezināms" };
};

export const buildMayanPsychologyProfile = (kinData) => {
    const seal = kinData.seal;
    const tone = kinData.tone;
    const color = getColorFromIndex(seal);
    const waveColor = getColorFromIndex(kinData.trecena);
    const earthFamily = getEarthFamily(seal);
    
    // 10. Ritmu uztvere
    const rhythmicSync = `${MAYAN_TONES[tone].split(':')[0]} Tonis iedarbina ${MAYAN_ARCHETYPES[seal].name} virzību.`;

    // 11. Iekšējais līdzsvars
    const innerBalance = tone <= 7 ? "Iekšupvērsts fokuss: Subjekts koncentrē spēku uz idejas uzbūvi sevī." : "Ārupvērsts fokuss: Subjekts raida spēku ārējā vidē. Aktīva sociālā plūsma.";
    
    // 12. Manifestācijas spēja (Just a generic mapping using tone vs GAP (simplified))
    const manifestPower = (tone === 1 || tone === 5 || tone === 9 || tone === 13) ? "Spēcīgs torņa vibrācijas lādiņš realitātes ietekmēšanai." : "Stabilizatora un atbalstītāja funkcija starp pīlāriem.";

    return {
        "1. Dvēseles arhetips": `${MAYAN_ARCHETYPES[seal].name} - ${MAYAN_ARCHETYPES[seal].type}. ${MAYAN_ARCHETYPES[seal].desc}`,
        "2. Enerģijas frekvence": MAYAN_TONES[tone],
        "3. Dzīves misija": `Trecena sākums: ${MAYAN_ARCHETYPES[kinData.trecena].name} (${waveColor} vilnis). ${COLOR_DYNAMICS[waveColor].operative} Stratēģiskais fons.`,
        "4. Dabiskie talanti": `Analogs (${MAYAN_ARCHETYPES[kinData.oracle.analog].name}): Komforta zona. Dabiskais sabiedrotais plūsmai.`,
        "5. Augstākā vadība": `Vadošais (${MAYAN_ARCHETYPES[kinData.oracle.guide].name}): Autoritātes sargs. Iedvesmotājs un vīzijas turētājs.`,
        "6. Psiholoģiskie izaicinājumi": `Antipods (${MAYAN_ARCHETYPES[kinData.oracle.antipode].name}): Izaugsmes spriedze. 'Sarkanā poga', kas audzē pretspēku.`,
        "7. Slēptais spēks": `Okultais (${MAYAN_ARCHETYPES[kinData.oracle.occult].name}): Zemapziņas trumpis. Mistiskais spēks krīzes pārvarēšanai.`,
        "8. Rīcības pamatmotīvs": `Fāze: ${color}. Dinamika: ${COLOR_DYNAMICS[color].operative}`,
        "9. Kolektīvā misija": `${earthFamily.name}: ${earthFamily.role}`,
        "10. Ritmu uztvere": `Frekvences lasījums: ${rhythmicSync}`,
        "11. Iekšējais līdzsvars": innerBalance,
        "12. Manifestācijas spēja": manifestPower
    };
};

export const getMayanNawalReliability = (sealIdx) => {
    // Dzelzs Likums
    if ([11, 9, 16].includes(sealIdx)) return { score: 100, category: "Dzelzs Likums", desc: "Uzticamība nav izvēle, bet izdzīvošanas instinkts." };
    // Struktūras Nesēji
    if ([12, 3, 14].includes(sealIdx)) return { score: 80, category: "Struktūras Nesēji", desc: "Organizatori, kuriem ir svarīga hierarhija un rezultāts." };
    // Zemes Spēks
    if ([0, 6, 19, 8].includes(sealIdx)) return { score: 65, category: "Zemes Spēks", desc: "Pilda solījumus kopienas vārdā. Praktiska atbildība." };
    // Transformācija
    if ([4, 7, 15, 17, 2].includes(sealIdx)) return { score: 45, category: "Transformācija", desc: "Mainīga uzticamība atkarībā no iekšējās izaugsmes fāzes." };
    // Haosa Elementi
    if ([10, 18, 1, 5, 13].includes(sealIdx)) return { score: 20, category: "Haosa Elementi", desc: "Radoši un impulsi ir svarīgāki par lineāru plānu." };
    return { score: 50, category: "Nezināms", desc: "Nezināma dinamika." };
};

export const getMayanTonalReliability = (tone) => {
    const scores = { 1: 20, 2: 30, 3: 40, 4: 55, 5: 60, 6: 65, 7: 75, 8: 70, 9: 80, 10: 90, 11: 85, 12: 100, 13: 95 };
    let baseScore = scores[tone] || 50;
    let stabilityBonus = (tone % 2 === 0) ? 5 : -5;
    let finalScore = Math.min(100, Math.max(0, baseScore + stabilityBonus));
    
    let category = "Zems Svars";
    if (tone >= 5 && tone <= 9) category = "Vidējs Svars";
    if (tone >= 10) category = "Augsts Svars";
    
    return { score: finalScore, baseScore, category };
};

export const getMayanCrossReliability = (centerIdx, oracle) => {
    let center = getMayanNawalReliability(centerIdx);
    let future = getMayanNawalReliability(oracle.guide);
    let past = getMayanNawalReliability(oracle.occult);
    let right = getMayanNawalReliability(oracle.analog);
    let left = getMayanNawalReliability(oracle.antipode);
    
    let rawScore = (center.score * 0.40) + (future.score * 0.20) + (past.score * 0.15) + (right.score * 0.15) + (left.score * 0.10);
    
    let stableCount = 0;
    let chaosCount = 0;
    const groups = [center.category, future.category, past.category, right.category, left.category];
    groups.forEach(g => {
        if (g === "Dzelzs Likums" || g === "Struktūras Nesēji") stableCount++;
        if (g === "Haosa Elementi") chaosCount++;
    });
    
    let mod = 1.0;
    let modDesc = "Neitrāls fons.";
    if (stableCount === 5) {
        mod = 1.2;
        modDesc = "Likteņa krusts ir dzelžaini stabils.";
    } else if (chaosCount >= 3) {
        mod = 0.7;
        modDesc = "Iekšējie konflikti un haotiskas ietekmes traucē pildīt solījumus.";
    }
    
    let finalScore = Math.min(100, Math.max(0, Math.floor(rawScore * mod)));
    return { score: finalScore, futureCategory: future.category, modDesc };
};
export const calculateMayanReliabilityFinal = (sealIdx, tone, oracle) => {
    let nawal = getMayanNawalReliability(sealIdx);
    let tonal = getMayanTonalReliability(tone);
    let cross = getMayanCrossReliability(sealIdx, oracle);
    
    let rawSum = Math.floor((nawal.score * 0.40) + (tonal.score * 0.30) + (cross.score * 0.30));
    
    let archetype = "Mākoņu Sapņotājs";
    let desc = "Kritiski zema uzticamība. Personas zīmogs ir saistīts ar ilūzijām un spēli. Viņš nedzīvo lineārā atbildības laikā, tāpēc uzskata, ka solījumi ir mainīgi lielumi, kas atkarīgi no viņa mirkļa iedvesmas.";
    
    if (rawSum >= 80) {
        archetype = "Saules Gvarde";
        desc = "Šis cilvēks ir Colkin kalendāra stabilitātes punkts. Viņa zīme un augstais tonāls norāda uz personu, kas burtiski 'nes laika atbildību'. Viņš ir uzticams pēc savas dabas — viņa vārds ir viņa eksistences pamats.";
    } else if (rawSum >= 50) {
        archetype = "Zemes Sargs";
        desc = "Personai ir spēcīga saikne ar realitāti un praktisku atbildību. Viņš ir uzticams partneris, taču viņa rīcību reizēm ietekmē viņa iekšējie cikli. Viņš pilda solījumus, jo tas ir loģisks dzīves ceļš, nevis akla sekošana likumam.";
    } else if (rawSum >= 25) {
        archetype = "Vēja Dzenātais";
        desc = "Personas enerģija ir pārāk viegla un nepastāvīga. Viņš ir 'pārejas enerģijas' cilvēks — lielisks pārmaiņām, bet bīstams stabilitātei. Viņa solījumi izplēn līdz ar jaunu 'laika viļņa' sākumu.";
    }
    
    return {
        score: rawSum,
        archetype: archetype,
        desc: desc,
        nawal: nawal,
        tonal: tonal,
        cross: cross
    };
};
