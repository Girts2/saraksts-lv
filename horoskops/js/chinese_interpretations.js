/**
 * chinese_interpretations.js - "Sistēmas X" BaZi Interpretāciju Datubāze
 */

export const BAZI_DAYMASTERS = {
    "Jan_Koks": "Līderis-pionieris. Spēcīga griba, tieksme augt un dominēt. Grūti lokāms, lūst pie ekstrēma spiediena.",
    "In_Koks": "Diplomāts-vītenis. Izcila pielāgošanās spēja. Panāk savu caur tīklošanos un maigā spēka manipulācijām.",
    "Jan_Uguns": "Saule. Atklāts, harizmātisks, bet mēdz būt neparedzami impulsīvs. Visu dara publiski.",
    "In_Uguns": "Svece. Inteliģents, uzmanīgs, spēj saskatīt detaļas tumsā. Emocionāli trausls, bet ass.",
    "Jan_Zeme": "Kalns. Stabils, konservatīvs, uzticams. Gandrīz neiespējami pierunāt uz straujām pārmaiņām.",
    "In_Zeme": "Augsne. Barojošs, rūpīgs, izcils resursu pārvaldnieks. Mēdz 'noslīkt' citu problēmās.",
    "Jan_Metāls": "Zobens. Auksta loģika, dzelžaina disciplīna. Uzbrūk tieši un bez žēlastības.",
    "In_Metāls": "Dārgakmens. Mīl prestižu un luksusu. Svarīga ārējā atzinība; šantažējams caur statusu.",
    "Jan_Ūdens": "Okeāns. Milzīga enerģija, nepieradināms. Spēj sagraut jebkuru šķērsli ar neatlaidību.",
    "In_Ūdens": "Rasa. Smalkākais manipulators. Iekļūst jebkurā spraugā, nolasot apkārtējo zemapziņu."
};

export const BAZI_ARCHETYPES = {
    "Friend": "Sociālais stabilitātes punkts. Meklē līdzīgos, ciena sadarbību.",
    "Rob_Wealth": "Agresīvs konkurents. Ārēji draudzīgs, bet vienmēr gatavs pārņemt resursus.",
    "Eating_God": "Ilgtermiņa stratēģis. Baudu mīlošs, radošs, necieš ierobežojumus.",
    "Hurting_Officer": "Sistēmas dumpinieks. Nekaunīgs, izcils orators, grauj autoritātes.",
    "Direct_Wealth": "Pragmātiķis. Vērtē darbu, īpašumu un reālus rezultātus.",
    "Indirect_Wealth": "Azartspēlmanis. Redz iespējas tur, kur citi redz riskus. Liela nauda/zaudējumi.",
    "Direct_Officer": "Likuma kalps. Ciena protokolu, hierarhiju un disciplīnu.",
    "Seven_Killings": "Taktiskais uzbrucējs. Vara, kontrole un gatavība upurēt visu uzvaras vārdā.",
    "Direct_Resource": "Akadēmiķis. Tic faktiem, vēsturei un loģiskam pamatojumam.",
    "Indirect_Resource": "Intuitīvais analītiķis. Interesējas par neparasto, slepeno un mistisko."
};

export const BAZI_CONFLICT_DESC = {
    "Clash": "⚠️ STRUKTURĀLS KONFLIKTS: Iekšēja pretruna, kas izraisa pēkšņas dzīves maiņas un traumas.",
    "Harm": "⚠️ UZTICĪBAS LŪZUMS: Subjekts jūtas nodots vai mēdz sabotēt savas tuvās attiecības."
};

/**
 * buildChinesePsychologyProfile - Sintēzes funkcija
 */
export function buildChinesePsychologyProfile(baziData) {
    const { dm, elements, mainGod, hiddenGod, conflicts, gods } = baziData;

    let dominantElement = Object.keys(elements).reduce((a, b) => elements[a] > elements[b] ? a : b);
    let missingElements = Object.keys(elements).filter(k => elements[k] === 0);
    
    // We get gods from baziData.gods mapping each polarity
    
    return {
        "1. Personības pamatstruktūra": BAZI_DAYMASTERS[`${dm.polarity}_${dm.elem}`] || BAZI_DAYMASTERS["Jan_Koks"],
        "2. Temperaments": `Elementu balanss: Koks ${elements["Koks"]}%, Uguns ${elements["Uguns"]}%, Zeme ${elements["Zeme"]}%, Metāls ${elements["Metāls"]}%, Ūdens ${elements["Ūdens"]}%. Dominē ${dominantElement}.`,
        "3. Dominējošais arhetips": BAZI_ARCHETYPES[mainGod],
        "4. Saskarsmes stils": BAZI_ARCHETYPES["Friend"] + " / " + BAZI_ARCHETYPES["Rob_Wealth"],
        "5. Attieksme pret varu": BAZI_ARCHETYPES["Direct_Officer"] + " / " + BAZI_ARCHETYPES["Seven_Killings"],
        "6. Radošā brīvība": BAZI_ARCHETYPES["Eating_God"] + " / " + BAZI_ARCHETYPES["Hurting_Officer"],
        "7. Informācijas asimilācija": BAZI_ARCHETYPES["Direct_Resource"] + " / " + BAZI_ARCHETYPES["Indirect_Resource"],
        "8. Pragmatisms": BAZI_ARCHETYPES["Direct_Wealth"] + " / " + BAZI_ARCHETYPES["Indirect_Wealth"],
        "9. Konkurence": `Pamatā ir ${elements[dm.elem]}% savējā elementa spēks, kas noteiks resursu pārdali starp līdzīgiem.`,
        "10. Iekšējā spriedze": conflicts.length > 0 
            ? conflicts.map(c => `${BAZI_CONFLICT_DESC[c.type] || c.type} (${c.pair.join("-")})`).join(" ")
            : "Sistēma ir stabila; iekšēju strukturālu konfliktu nav.",
        "11. Slēptās vēlmes": `Subjekta 'Melnā kaste': ${hiddenGod ? BAZI_ARCHETYPES[hiddenGod] : "Neaktīvs"}. Tas nosaka rīcību krīzes brīžos.`,
        "12. Partnerība": "Dienas Zara 'Spouse Palace' nosaka primārās attiecības.",
        "13. Lēmumu stratēģija": "Lēmumi atspoguļo Mēneša un Gada stumbru proporcijas.",
        "14. Adaptācija krīzēm": missingElements.length > 0 ? `Tukšums un nespēja dabīgi kompensēt kritienu šādos sektoros: ${missingElements.join(", ")}.` : "Cilvēks spēj pielāgoties no visām sfērām."
    };
}
