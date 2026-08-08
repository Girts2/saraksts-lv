// 10 Debesu Stumbri (Heavenly Stems)
export const STEMS = [
    { name: "Gui", element: "Ūdens", polarity: "Iņ" },   // 0
    { name: "Jia", element: "Koks", polarity: "Jaņ" },    // 1
    { name: "Yi", element: "Koks", polarity: "Iņ" },      // 2
    { name: "Bing", element: "Uguns", polarity: "Jaņ" },   // 3
    { name: "Ding", element: "Uguns", polarity: "Iņ" },    // 4
    { name: "Wu", element: "Zeme", polarity: "Jaņ" },     // 5
    { name: "Ji", element: "Zeme", polarity: "Iņ" },      // 6
    { name: "Geng", element: "Metāls", polarity: "Jaņ" },  // 7
    { name: "Xin", element: "Metāls", polarity: "Iņ" },    // 8
    { name: "Ren", element: "Ūdens", polarity: "Jaņ" }    // 9
];

// 12 Zemes Zari (Earthly Branches)
export const BRANCHES = [
    "Hai (Cūka)", "Zi (Žurka)", "Chou (Vērsis)", "Yin (Tīģeris)", 
    "Mao (Trusis)", "Chen (Pūķis)", "Si (Čūska)", "Wu (Zirgs)", 
    "Wei (Kaza)", "Shen (Pērtiķis)", "You (Gailis)", "Xu (Suns)"
];

// 6 zemes zaru sadursmes (六冲). Vienots avots gan natālajām, gan Liu Ri sadursmēm.
export const BRANCH_CLASHES = [
    ["Zi", "Wu"], ["Chou", "Wei"], ["Yin", "Shen"],
    ["Mao", "You"], ["Chen", "Xu"], ["Si", "Hai"]
];

// Gregora civilā datuma → JDN (vesels dienas numurs).
// BaZi diena sākas 23:00 LOKĀLI (LMT), tāpēc stunda >= 23 pieder NĀKAMĀS dienas pīlāram.
export function baziDayJDN(year, month, day, hour = 12) {
    const a  = Math.floor((14 - month) / 12);
    const yy = year + 4800 - a;
    const mm = month + 12 * a - 3;
    let jdn = day + Math.floor((153 * mm + 2) / 5) + 365 * yy
            + Math.floor(yy / 4) - Math.floor(yy / 100) + Math.floor(yy / 400) - 32045;
    if (hour >= 23) jdn += 1;   // 23:00–23:59 → nākamā BaZi diena
    return jdn;
}

// Sexagenary dienas pīlārs no JDN. Nobīde +50 verificēta pret almanahu
// (1949-10-01=甲子, 1592-12-31=甲申, 2000-01-01=戊午); sk. tools/test_liu_ri_daypillar.mjs.
// Iepriekšējā +15 deva dienas pīlāru par 35 dienām nobīdītu.
export function dayPillarFromJDN(jdn) {
    const cyc = jdn + 50;
    const s = ((cyc % 10) + 10) % 10;
    const b = ((cyc % 12) + 12) % 12;
    return { Stem: STEMS[s], Branch: BRANCHES[b], stemIdx: s, branchIdx: b };
}

export function getBaziPillars(year, month, day, hour, jd, sunTrop = null) {
    // 1. GADA PĪLĀRS
    // BaZi gads sākas Li Chun brīdī (Saule = 315°), ne fiksētā 4. februārī. Ja Saules
    // garums ir pieejams, robežu nosaka pēc tā — citādi gada un mēneša pīlāri izšķiras
    // pie robežas (piem., 4. feb. pirms Li Chun: mēnesis vēl Chou (vecā gada 12.), bet
    // gads jau jaunais → mēneša stumbrs pēc Piecu Tīģeru likuma sanāk no nepareizā gada).
    // Janvārī/februārī Saule ir ~280°–345°: garums ≥315° nozīmē, ka Li Chun ir pagājis.
    let baziYear;
    if (sunTrop != null && (month === 1 || month === 2)) {
        baziYear = (sunTrop >= 315 && sunTrop < 345) ? year : year - 1;
    } else {
        baziYear = (month === 1 || (month === 2 && day < 4)) ? year - 1 : year;
    }
    let yearStemIdx = (baziYear - 3) % 10;
    let yearBranchIdx = (baziYear - 3) % 12;
    if (yearStemIdx < 0) yearStemIdx += 10;
    if (yearBranchIdx < 0) yearBranchIdx += 12;

    // 2. MĒNEŠA PĪLĀRS
    let monthBranchIdx, monthStemIdx;
    if (sunTrop != null) {
        // KORREKTI: no SAULES TERMIŅIEM (节气), ne Gregora mēneša. Yin mēnesis sākas Sun=315°,
        // tad ik +30°. Verificēts: tools/verify_app.py (app's Gregora mēnesis kļūdains agri-mēneša dzimšanām).
        const solarMonthIdx = Math.floor(((((sunTrop - 315) % 360) + 360) % 360) / 30); // 0=Yin
        monthBranchIdx = (3 + solarMonthIdx) % 12;                       // BRANCHES: Yin=3
        monthStemIdx   = ((yearStemIdx % 5) * 2 + 1 + solarMonthIdx) % 10; // Piecu Tīģeru likums (五虎遁)
    } else {
        // Vēsturiskā aproksimācija (Gregora mēnesis) — paturēta saderībai bez Saules garuma (sparklines).
        monthBranchIdx = (month + 1) % 12;
        monthStemIdx = ((yearStemIdx % 5) * 2 + month) % 10;
    }
    
    // 3. DIENAS PĪLĀRS (nepārtrauktais 60 dienu cikls).
    // No LOKĀLĀ (LMT) datuma ar 23:00 robežu, NE no UTC jd — citādi diena nesakrīt
    // ar gada/mēneša/stundas pīlāriem (visi LMT) un 23:00 robeža nav iespējama.
    // (jd parametrs vairs netiek lietots dienas pīlāram; paturēts saderībai.)
    const dayJDN = baziDayJDN(year, month, day, hour);
    const dayPil = dayPillarFromJDN(dayJDN);
    const dayStemIdx = dayPil.stemIdx;
    const dayBranchIdx = dayPil.branchIdx;
    
    // 4. STUNDAS PĪLĀRS
    // hourBranchIdx ir 0=Zi SECĪGS indekss (Zi=0..Hai=11) — tas, ko prasa Piecu Žurku
    // likuma (五鼠遁) formula zemāk. BRANCHES masīvs savukārt sākas ar Hai (indekss 0),
    // tāpēc ZARA NOSAUKUMAM vajag (+1)%12 pārrēķinu — sk. BRANCHES definīciju augšā.
    const hourBranchIdx = Math.floor((hour + 1) / 2) % 12;
    // Piecu Žurku likums: STEMS masīvs sākas ar Gui (ne Jia), tāpēc pēc standarta
    // formulas (Jia-bāzēts) pārrēķina nepieciešams +9 (ekvivalenti -1) nobīde.
    // Verificēts pret klasisko faktu: Jia-dienas Zi-stunda = "Jiazi" (pirmā 60-cikla vienība).
    const hourStemIdx = ((dayStemIdx % 5) * 2 + hourBranchIdx + 9) % 10;

    const daymaster = STEMS[dayStemIdx];

    return {
        Year: { Stem: STEMS[yearStemIdx], Branch: BRANCHES[yearBranchIdx] },
        Month: { Stem: STEMS[monthStemIdx], Branch: BRANCHES[monthBranchIdx] },
        Day: { Stem: daymaster, Branch: BRANCHES[dayBranchIdx] },
        Hour: { Stem: STEMS[hourStemIdx], Branch: BRANCHES[(hourBranchIdx + 1) % 12] },
        Daymaster: daymaster
    };
}

const ZODIAC_TO_ELEMENT = {
    "Zi": "Ūdens", "Chou": "Zeme", "Yin": "Koks", "Mao": "Koks", 
    "Chen": "Zeme", "Si": "Uguns", "Wu": "Uguns", "Wei": "Zeme",
    "Shen": "Metāls", "You": "Metāls", "Xu": "Zeme", "Hai": "Ūdens"
};

const RELATIONSHIP_MATRIX = {
    "Koks": { "Koks": "Companion", "Uguns": "Output", "Zeme": "Wealth", "Metāls": "Influence", "Ūdens": "Resource" },
    "Uguns": { "Uguns": "Companion", "Zeme": "Output", "Metāls": "Wealth", "Ūdens": "Influence", "Koks": "Resource" },
    "Zeme": { "Zeme": "Companion", "Metāls": "Output", "Ūdens": "Wealth", "Koks": "Influence", "Uguns": "Resource" },
    "Metāls": { "Metāls": "Companion", "Ūdens": "Output", "Koks": "Wealth", "Uguns": "Influence", "Zeme": "Resource" },
    "Ūdens": { "Ūdens": "Companion", "Koks": "Output", "Uguns": "Wealth", "Zeme": "Influence", "Metāls": "Resource" }
};

export function calculateFiveElementsBalance(pillars) {
    let counts = { "Koks": 0, "Uguns": 0, "Zeme": 0, "Metāls": 0, "Ūdens": 0 };
    
    // Evaluate Stems
    pillars.forEach(p => {
        counts[p.Stem.element] += 1;
        // The Branch string usually looks like "Zi (Žurka)", we need the first word.
        let bKey = p.Branch.split(" ")[0];
        if(ZODIAC_TO_ELEMENT[bKey]) {
            counts[ZODIAC_TO_ELEMENT[bKey]] += 1;
        }
    });

    let total = pillars.length * 2; // stem+zars katram pīlāram (4 pīlāri=8; 3 pīlāri bez stundas=6)
    let balance = {};
    for (let el in counts) {
        balance[el] = Math.round((counts[el] / total) * 100);
    }
    return balance;
}

export function calculateTenGods(dmStem, targetStem) {
    if (!targetStem) return null;
    const relationship = RELATIONSHIP_MATRIX[dmStem.element][targetStem.element];
    // Notice: The input data uses 'Jaņ' and 'Iņ'
    const samePolarity = dmStem.polarity === targetStem.polarity;

    const godMap = {
        "Companion": samePolarity ? "Friend" : "Rob_Wealth",
        "Output": samePolarity ? "Eating_God" : "Hurting_Officer",
        "Wealth": samePolarity ? "Indirect_Wealth" : "Direct_Wealth",
        "Influence": samePolarity ? "Seven_Killings" : "Direct_Officer",
        "Resource": samePolarity ? "Indirect_Resource" : "Direct_Resource"
    };

    return godMap[relationship];
}

export function findBaZiConflicts(branches) {
    const clashes = BRANCH_CLASHES;

    let activeConflicts = [];
    let bKeys = branches.map(b => b.split(" ")[0]);
    for (let i = 0; i < bKeys.length; i++) {
        for (let j = i + 1; j < bKeys.length; j++) {
            // bKeys[i] !== bKeys[j]: vienāds zars nesadursmējas pats ar sevi (六冲 = pretēji zari)
            if (bKeys[i] !== bKeys[j] && clashes.some(c => c.includes(bKeys[i]) && c.includes(bKeys[j]))) {
                activeConflicts.push({ pair: [bKeys[i], bKeys[j]], type: "Clash" });
            }
        }
    }
    return activeConflicts;
}

// Īstie slēptie celmi (藏干) katram zaram — galvenais (galvenā cji) pirmais.
// 2026-07-03 audita labojums: agrākā aproksimācija ("zara elements + polaritāte Iņ
// tikai Zi/Mao/You") Chou/Wu/Wei zariem deva APGRIEZTU polaritāti (to īstais pirmais
// celms Ji/Ding/Ji ir Iņ) → hiddenGod iekrita nepareizā Direct/Indirect dievu ģimenē
// ~35% cilvēku (7/20 audita personām; sk. tools/profils_audit.py).
const HIDDEN_STEMS_MAP = {
    "Zi": ["Gui"], "Chou": ["Ji", "Gui", "Xin"], "Yin": ["Jia", "Bing", "Wu"], "Mao": ["Yi"],
    "Chen": ["Wu", "Yi", "Gui"], "Si": ["Bing", "Geng", "Wu"], "Wu": ["Ding", "Ji"],
    "Wei": ["Ji", "Ding", "Yi"], "Shen": ["Geng", "Ren", "Wu"], "You": ["Xin"],
    "Xu": ["Wu", "Xin", "Ding"], "Hai": ["Ren", "Jia"]
};
export function GET_HIDDEN_STEMS(branchStr) {
    const bKey = branchStr.split(" ")[0];
    // Atgriež īstos celmus ar precīzu elementu UN polaritāti no STEMS tabulas.
    return (HIDDEN_STEMS_MAP[bKey] || []).map(n => STEMS.find(s => s.name === n));
}

export function calculateDaymasterStrength(daymaster, elementsBalance) {
    const dmElement = daymaster.element;
    const resourceMap = { "Koks": "Ūdens", "Uguns": "Koks", "Zeme": "Uguns", "Metāls": "Zeme", "Ūdens": "Metāls" };
    const resourceElement = resourceMap[dmElement];
    
    let supportPercentage = (elementsBalance[dmElement] || 0) + (elementsBalance[resourceElement] || 0);
    let isStrong = supportPercentage > 40; 
    
    const outputMap = { "Koks": "Uguns", "Uguns": "Zeme", "Zeme": "Metāls", "Metāls": "Ūdens", "Ūdens": "Koks" };
    const wealthMap = { "Koks": "Zeme", "Uguns": "Metāls", "Zeme": "Ūdens", "Metāls": "Koks", "Ūdens": "Uguns" };
    const influenceMap = { "Koks": "Metāls", "Uguns": "Ūdens", "Zeme": "Koks", "Metāls": "Uguns", "Ūdens": "Zeme" };
    
    let favorable = [];
    if (isStrong) {
        favorable = [outputMap[dmElement], wealthMap[dmElement], influenceMap[dmElement]];
    } else {
        favorable = [resourceElement, dmElement];
    }
    
    return {
        isStrong: isStrong,
        supportScore: supportPercentage,
        statusStr: isStrong ? "Stiprs Dienas Dominants" : "Vājš Dienas Dominants",
        favorableElements: favorable
    };
}

export function calculateLuckPillars(yearStem, monthStem, monthBranch, isMale, sunTrop = null) {
    const isYangYear = yearStem.polarity === "Jaņ";
    const forward = (isYangYear && isMale) || (!isYangYear && !isMale);

    // 起运 (qiyun) sākuma vecums: attālums no dzimšanas Saules garuma līdz mēneša-pīlāra
    // robežai (节, ik 30° sākot no 315°) ÷ 3 (BaZi likums: 3 dienas = 1 gads). forward →
    // līdz NĀKAMAJAI robežai; backward → no IEPRIEKŠĒJĀS. Saules vidējā kustība ≈0.9856°/d.
    // Bez sunTrop (sparklines) atkrīt uz vēsturisko aproksimāciju 3.
    let startAge = 3;
    if (sunTrop != null) {
        const offset = ((((sunTrop - 315) % 30) + 30) % 30);   // 0..30° iekš solārā mēneša
        const degToBoundary = forward ? (30 - offset) : offset;
        startAge = Math.max(0, Math.round((degToBoundary / 0.98565) / 3));
    }

    let currentStemIdx = STEMS.findIndex(s => s.name === monthStem.name);
    let currentBranchIdx = BRANCHES.findIndex(b => b === monthBranch);

    let pillars = [];
    for (let i = 0; i < 8; i++) {
        if (forward) {
            currentStemIdx = (currentStemIdx + 1) % 10;
            currentBranchIdx = (currentBranchIdx + 1) % 12;
        } else {
            currentStemIdx = (currentStemIdx - 1 + 10) % 10;
            currentBranchIdx = (currentBranchIdx - 1 + 12) % 12;
        }
        pillars.push({
            ageStart: (i * 10) + startAge,
            ageEnd: (i * 10) + startAge + 9,
            stem: STEMS[currentStemIdx],
            branch: BRANCHES[currentBranchIdx]
        });
    }
    return pillars;
}

export function getBaZiCombinations(branches) {
    const bKeys = branches.map(b => b.split(" ")[0]);
    
    const harmonies = [
        ["Zi", "Chou"], ["Yin", "Hai"], ["Mao", "Xu"], 
        ["Chen", "You"], ["Si", "Shen"], ["Wu", "Wei"]
    ];
    
    const trines = [
        ["Shen", "Zi", "Chen"], 
        ["Hai", "Mao", "Wei"],  
        ["Yin", "Wu", "Xu"],    
        ["Si", "You", "Chou"]   
    ];
    
    let combos = [];
    
    for (let i = 0; i < bKeys.length; i++) {
        for (let j = i + 1; j < bKeys.length; j++) {
            if (harmonies.some(h => h.includes(bKeys[i]) && h.includes(bKeys[j]))) {
                combos.push({ pair: [bKeys[i], bKeys[j]], type: "Sešu Zaru Harmonija (Neitralizē sadursmes)" });
            }
        }
    }
    
    let uniqueB = [...new Set(bKeys)];
    for (let t of trines) {
        if (t.every(b => uniqueB.includes(b))) {
            combos.push({ pair: t, type: "Trīskāršā Alianse (Milzīga spēka saplūšana)" });
        }
    }
    
    return combos;
}

export function calculateSymbolicStars(dayStem, dayBranch, yearBranch, allBranches) {
    const dsName = dayStem.name;
    const dbKey = dayBranch.split(" ")[0];
    const ybKey = yearBranch.split(" ")[0];
    const aKeys = allBranches.map(b => b.split(" ")[0]);
    
    let stars = [];
    
    const nobleMap = {
        "Jia": ["Chou", "Wei"], "Wu": ["Chou", "Wei"], "Geng": ["Chou", "Wei"],
        "Yi": ["Zi", "Shen"], "Ji": ["Zi", "Shen"],
        "Bing": ["Hai", "You"], "Ding": ["Hai", "You"],
        "Xin": ["Yin", "Wu"],
        "Ren": ["Mao", "Si"], "Gui": ["Mao", "Si"]
    };
    if (nobleMap[dsName]) {
        aKeys.forEach(b => {
            if (nobleMap[dsName].includes(b)) stars.push({ star: "Noble Man (Dižciltīgais)", branch: b, desc: "Sargeņģelis, kas atnes padomdevējus un palīdzību krīzē." });
        });
    }
    
    const peachMap = {
        "Shen": "You", "Zi": "You", "Chen": "You",
        "Hai": "Zi", "Mao": "Zi", "Wei": "Zi",
        "Yin": "Mao", "Wu": "Mao", "Xu": "Mao",
        "Si": "Wu", "You": "Wu", "Chou": "Wu"
    };
    let p1 = peachMap[dbKey];
    let p2 = peachMap[ybKey];
    if (p1 && aKeys.includes(p1)) stars.push({ star: "Peach Blossom (Persiku Zieds)", branch: p1, desc: "Milzīga pievilcība, harizma un romantikas/mārketinga veiksme." });
    if (p2 && p2 !== p1 && aKeys.includes(p2)) stars.push({ star: "Peach Blossom (Persiku Zieds)", branch: p2, desc: "Milzīga pievilcība, harizma un romantikas/mārketinga veiksme." });
    
    const horseMap = {
        "Shen": "Yin", "Zi": "Yin", "Chen": "Yin",
        "Hai": "Si", "Mao": "Si", "Wei": "Si",
        "Yin": "Shen", "Wu": "Shen", "Xu": "Shen",
        "Si": "Hai", "You": "Hai", "Chou": "Hai"
    };
    let h1 = horseMap[dbKey];
    let h2 = horseMap[ybKey];
    if (h1 && aKeys.includes(h1)) stars.push({ star: "Traveling Horse (Ceļojošais Zirgs)", branch: h1, desc: "Fiziska kustība, pārcelšanās, starptautisks darbs vai liela dinamika." });
    if (h2 && h2 !== h1 && aKeys.includes(h2)) stars.push({ star: "Traveling Horse (Ceļojošais Zirgs)", branch: h2, desc: "Fiziska kustība, pārcelšanās, starptautisks darbs vai liela dinamika." });

    const uniqueStars = Array.from(new Set(stars.map(a => a.star + a.branch))).map(id => {
        return stars.find(a => a.star + a.branch === id);
    });
    
    return uniqueStars;
}
