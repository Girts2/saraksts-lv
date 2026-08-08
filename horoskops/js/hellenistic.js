export function determineSect(ascDeg, sunDeg) {
    // Leņķis no Saules līdz Ascendentam
    let diff = (ascDeg - sunDeg) % 360;
    if (diff < 0) diff += 360;
    
    if (diff > 0 && diff < 180) {
        return "Diena";
    } else {
        return "Nakts";
    }
}

export function calculateLots(ascDeg, sunDeg, moonDeg, sect) {
    let lotOfFortune, lotOfSpirit;
    if (sect === "Diena") {
        lotOfFortune = (ascDeg + moonDeg - sunDeg) % 360;
        lotOfSpirit = (ascDeg + sunDeg - moonDeg) % 360;
    } else {
        lotOfFortune = (ascDeg + sunDeg - moonDeg) % 360;
        lotOfSpirit = (ascDeg + moonDeg - sunDeg) % 360;
    }
    
    if (lotOfFortune < 0) lotOfFortune += 360;
    if (lotOfSpirit < 0) lotOfSpirit += 360;
        
    return {
        fortune_deg: lotOfFortune,
        spirit_deg: lotOfSpirit
    };
}

export function getZodiacSignAndModality(deg) {
    const zodiacSigns = [
        "Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", 
        "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"
    ];
    const modalities = [
        "Kardināla (Radīšana/Iniciatīva)", 
        "Fiksēta (Stabilitāte/Spēks)", 
        "Mutabla (Pielāgošanās/Pārmaiņas)"
    ];
    
    const signIdx = Math.floor(deg / 30);
    const signName = zodiacSigns[signIdx];
    const modality = modalities[signIdx % 3];
    
    return {
        sign: signName, 
        modality: modality,
        element: getElementFromSignName(signName)
    };
}

function getElementFromSignName(sign) {
    const elements = {
        "Auns": "Uguns", "Vērsis": "Zeme", "Dvīņi": "Gaiss", "Vēzis": "Ūdens",
        "Lauva": "Uguns", "Jaunava": "Zeme", "Svari": "Gaiss", "Skorpions": "Ūdens",
        "Strēlnieks": "Uguns", "Mežāzis": "Zeme", "Ūdensvīrs": "Gaiss", "Zivis": "Ūdens"
    };
    return elements[sign] || "Uguns";
}

export function GET_SIGN_RULER(signName) {
    const rulers = {
        "Auns": "Marss", "Vērsis": "Venera", "Dvīņi": "Merkurs", "Vēzis": "Meness",
        "Lauva": "Saule", "Jaunava": "Merkurs", "Svari": "Venera", "Skorpions": "Marss",
        "Strēlnieks": "Jupiters", "Mežāzis": "Saturns", "Ūdensvīrs": "Saturns", "Zivis": "Jupiters"
    };
    return rulers[signName];
}

export function calculateTemperament(ascSign, moonSign) {
    const ascElement = getElementFromSignName(ascSign);
    const moonElement = getElementFromSignName(moonSign);
    
    const eToH = {
        "Uguns": "Choleric",
        "Gaiss": "Sanguine",
        "Zeme": "Melancholic",
        "Ūdens": "Phlegmatic"
    };

    const h1 = eToH[ascElement];
    const h2 = eToH[moonElement];

    if (h1 === h2) return h1;
    
    // Sort to ensure keys match combining strings
    const combo = [h1, h2].sort();
    return `${combo[0]}-${combo[1]}`;
}

export function getLifeHelmsman(ascendantSign, planetsDict) {
    const rulerName = GET_SIGN_RULER(ascendantSign);
    let condition = "Neitrāls";
    let house = "?";

    if (planetsDict && planetsDict[rulerName]) {
        const lord = planetsDict[rulerName];
        house = lord.house;
        // Basic dignity check base based on rules (could be expanded)
        if (GET_SIGN_RULER(lord.sign) === rulerName) condition = "Trijumfējošs (Savā zīmē)";
    }
    
    return {
        planet: rulerName,
        condition: condition, 
        house: house
    };
}

// EGYPTIAN TERMS
const EGYPTIAN_TERMS = {
    "Auns": [ {p:"Jupiters", d:6}, {p:"Venera", d:14}, {p:"Merkurs", d:22}, {p:"Marss", d:27}, {p:"Saturns", d:30} ],
    "Vērsis": [ {p:"Venera", d:8}, {p:"Merkurs", d:15}, {p:"Jupiters", d:22}, {p:"Saturns", d:27}, {p:"Marss", d:30} ],
    "Dvīņi": [ {p:"Merkurs", d:7}, {p:"Jupiters", d:14}, {p:"Venera", d:21}, {p:"Saturns", d:25}, {p:"Marss", d:30} ],
    "Vēzis": [ {p:"Marss", d:6}, {p:"Jupiters", d:13}, {p:"Merkurs", d:20}, {p:"Venera", d:27}, {p:"Saturns", d:30} ],
    "Lauva": [ {p:"Jupiters", d:6}, {p:"Venera", d:11}, {p:"Saturns", d:18}, {p:"Merkurs", d:24}, {p:"Marss", d:30} ],
    "Jaunava": [ {p:"Merkurs", d:7}, {p:"Venera", d:13}, {p:"Jupiters", d:18}, {p:"Saturns", d:24}, {p:"Marss", d:30} ],
    "Svari": [ {p:"Saturns", d:6}, {p:"Merkurs", d:11}, {p:"Jupiters", d:19}, {p:"Venera", d:24}, {p:"Marss", d:30} ],
    "Skorpions": [ {p:"Marss", d:6}, {p:"Jupiters", d:14}, {p:"Venera", d:21}, {p:"Merkurs", d:27}, {p:"Saturns", d:30} ],
    "Strēlnieks": [ {p:"Jupiters", d:8}, {p:"Venera", d:14}, {p:"Merkurs", d:19}, {p:"Saturns", d:25}, {p:"Marss", d:30} ],
    "Mežāzis": [ {p:"Venera", d:6}, {p:"Merkurs", d:12}, {p:"Jupiters", d:19}, {p:"Marss", d:25}, {p:"Saturns", d:30} ],
    "Ūdensvīrs": [ {p:"Saturns", d:6}, {p:"Merkurs", d:12}, {p:"Venera", d:20}, {p:"Jupiters", d:25}, {p:"Marss", d:30} ],
    "Zivis": [ {p:"Venera", d:8}, {p:"Jupiters", d:14}, {p:"Merkurs", d:20}, {p:"Marss", d:26}, {p:"Saturns", d:30} ]
};

export function getTerm(signName, degreeInSign) {
    const terms = EGYPTIAN_TERMS[signName];
    if (!terms) return null;
    for (let t of terms) {
        if (degreeInSign < t.d) {
            return t.p;
        }
    }
    return terms[terms.length - 1].p;
}

export function calculateTriplicityLords(element, sect) {
    const table = {
        "Uguns": { d: ["Saule", "Jupiters", "Saturns"], n: ["Jupiters", "Saule", "Saturns"] },
        "Zeme": { d: ["Venera", "Meness", "Marss"], n: ["Meness", "Venera", "Marss"] },
        "Gaiss": { d: ["Saturns", "Merkurs", "Jupiters"], n: ["Merkurs", "Saturns", "Jupiters"] },
        "Ūdens": { d: ["Venera", "Marss", "Meness"], n: ["Marss", "Venera", "Meness"] }
    };
    
    if (!table[element]) return ["?", "?", "?"];
    return sect === "Diena" ? table[element].d : table[element].n;
}

export function calculateProfection(ascendantSign, age) {
    const zodiacSigns = [
        "Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", 
        "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"
    ];
    let startIdx = zodiacSigns.indexOf(ascendantSign);
    if (startIdx === -1) startIdx = 0;
    
    let currentIdx = (startIdx + (age % 12)) % 12;
    let currentSign = zodiacSigns[currentIdx];
    let currentLord = GET_SIGN_RULER(currentSign);
    let house = (age % 12) + 1;
    
    return {
        house: house,
        sign: currentSign,
        lord: currentLord
    };
}

export function calculateHellenisticAspects(planetsDict) {
    const zodiacSigns = [
        "Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", 
        "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"
    ];
    
    let aspects = [];
    let aversions = [];
    
    let pNames = Object.keys(planetsDict);
    
    for (let i = 0; i < pNames.length; i++) {
        for (let j = i + 1; j < pNames.length; j++) {
            let p1 = pNames[i];
            let p2 = pNames[j];
            
            if(!planetsDict[p1] || !planetsDict[p2]) continue;
            let s1 = planetsDict[p1].sign;
            let s2 = planetsDict[p2].sign;
            
            let i1 = zodiacSigns.indexOf(s1);
            let i2 = zodiacSigns.indexOf(s2);
            
            if (i1 === -1 || i2 === -1) continue;
            
            let diff = (i2 - i1);
            if (diff < 0) diff += 12;
            
            if ([1, 5, 7, 11].includes(diff)) {
                aversions.push({p1: p1, p2: p2, s1: s1, s2: s2});
            }
            
            if (diff === 9) {
                aspects.push({ superior: p2, inferior: p1, type: "Kvadrāts" });
            } else if (diff === 3) {
                aspects.push({ superior: p1, inferior: p2, type: "Kvadrāts" });
            }
            
            if (diff === 8) {
                aspects.push({ superior: p2, inferior: p1, type: "Trigons" });
            } else if (diff === 4) {
                aspects.push({ superior: p1, inferior: p2, type: "Trigons" });
            }
        }
    }
    
    return { aversions: aversions, superiorAspects: aspects };
}

export function checkDoryphory(planetsDict, sect) {
    let light = sect === "Diena" ? "Saule" : "Meness";
    if (!planetsDict[light]) return {light: light, planets: []};
    
    const zodiacSigns = [
        "Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", 
        "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"
    ];
    
    let lightIdx = zodiacSigns.indexOf(planetsDict[light].sign);
    if (lightIdx === -1) return {light: light, planets: []};
    
    let doryphoryPlanets = [];
    let pNames = ["Jupiters", "Venera", "Saturns", "Marss", "Merkurs"];
    
    pNames.forEach(p => {
        if (!planetsDict[p]) return;
        let pIdx = zodiacSigns.indexOf(planetsDict[p].sign);
        if (pIdx === -1) return;
        
        let diff = pIdx - lightIdx;
        if (diff < 0) diff += 12;
        
        // 11 is previous sign (oriental)
        if (diff === 11 || diff === 0) {
            doryphoryPlanets.push(p);
        }
    });
    
    return {light: light, planets: doryphoryPlanets};
}
