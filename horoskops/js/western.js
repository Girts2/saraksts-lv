export function getProgressedDate(birthDt, targetDt) {
    // Vecums sekundēs un gados
    const ageInMs = targetDt.getTime() - birthDt.getTime();
    const ageInDays = ageInMs / 86400000;
    const ageInYears = ageInDays / 365.2422;
    
    // Progressejam: 1 gads = 1 diena
    const progressedMs = ageInYears * 86400000;
    const progressedDt = new Date(birthDt.getTime() + progressedMs);
    
    return {
        progressed_date: progressedDt,
        age_in_years: ageInYears
    };
}

export function analyzeProgressions(tropBirthData, tropProgressedData) {
    const sunBirth = tropBirthData['Saule'];
    const sunProg = tropProgressedData['Saule'];
    
    const moonBirth = tropBirthData['Meness'];
    const moonProg = tropProgressedData['Meness'];
    
    return {
        sun_prog_deg: sunProg,
        moon_prog_deg: moonProg,
        sun_changed_sign: Math.floor(sunBirth / 30) !== Math.floor(sunProg / 30),
        moon_changed_sign: Math.floor(moonBirth / 30) !== Math.floor(moonProg / 30)
    };
}

export function determineSect(ascDeg, sunDeg) {
    const ascSign = Math.floor(ascDeg / 30);
    const sunSign = Math.floor(sunDeg / 30);
    const sunHouse = (sunSign - ascSign + 12) % 12 + 1;
    return (sunHouse >= 7) ? "Day" : "Night";
}

export function calculateAspects(planets) {
    const aspectTypes = [
        { name: 'Square', angle: 90, orb: 4, type: 'critical' },
        { name: 'Opposition', angle: 180, orb: 5, type: 'critical' },
        { name: 'Trine', angle: 120, orb: 5, type: 'harmonic' },
        { name: 'Sextile', angle: 60, orb: 4, type: 'harmonic' },
        { name: 'Conjunction', angle: 0, orb: 5, type: 'neutral' }
    ];
    
    let activeAspects = [];
    const planetNames = Object.keys(planets);

    for (let i = 0; i < planetNames.length; i++) {
        for (let j = i + 1; j < planetNames.length; j++) {
            let p1 = planets[planetNames[i]];
            let p2 = planets[planetNames[j]];
            
            if (!p1 || !p2 || p1.longitude === undefined || p2.longitude === undefined) continue;

            let diff = Math.abs(p1.longitude - p2.longitude);
            if (diff > 180) diff = 360 - diff;

            const nameMap = {
                "Saule": "Sun", "Meness": "Moon", "Merkurs": "Mercury", "Venera": "Venus",
                "Marss": "Mars", "Jupiters": "Jupiter", "Saturns": "Saturn", "Urans": "Uranus",
                "Neptuns": "Neptune", "Plutons": "Pluto", "Lilita": "Lilith", "Hirons": "Chiron"
            };

            const n1 = nameMap[planetNames[i]] || planetNames[i];
            const n2 = nameMap[planetNames[j]] || planetNames[j];

            aspectTypes.forEach(aspect => {
                if (Math.abs(diff - aspect.angle) <= aspect.orb) {
                    activeAspects.push({
                        id: `${n1}_${aspect.name}_${n2}`,
                        p1: n1,
                        p2: n2,
                        type: aspect.name,
                        nature: aspect.type
                    });
                    // Saglabājam abos virzienos vieglākai meklēšanai
                    activeAspects.push({
                        id: `${n2}_${aspect.name}_${n1}`,
                        p1: n2,
                        p2: n1,
                        type: aspect.name,
                        nature: aspect.type
                    });
                }
            });
        }
    }
    return activeAspects;
}

export function calculateTransits(natalPlanets, currentPlanets) {
    const transitAspects = [
        { name: 'Conjunction', angle: 0, orb: 2, type: 'neutral' },
        { name: 'Sextile', angle: 60, orb: 2, type: 'harmonic' },
        { name: 'Square', angle: 90, orb: 2, type: 'critical' },
        { name: 'Opposition', angle: 180, orb: 2, type: 'critical' },
        { name: 'Trine', angle: 120, orb: 2, type: 'harmonic' }
    ];
    
    let transits = [];
    // Ņemam vērā tikai lēno planētu tranzītus, jo ātrie ir pārāk bieži un nenozīmīgi
    const transitingNames = ["Marss", "Jupiters", "Saturns", "Urans", "Neptuns", "Plutons", "Hirons"];
    const natalNames = Object.keys(natalPlanets);

    const nameMap = {
        "Saule": "Sun", "Meness": "Moon", "Merkurs": "Mercury", "Venera": "Venus",
        "Marss": "Mars", "Jupiters": "Jupiter", "Saturns": "Saturn", "Urans": "Uranus",
        "Neptuns": "Neptune", "Plutons": "Pluto", "Lilita": "Lilith", "Hirons": "Chiron",
        "Ascendant": "Ascendant", "MC": "MC"
    };

    for (let tPlanet of transitingNames) {
        if (currentPlanets[tPlanet] === undefined) continue;
        let tLong = currentPlanets[tPlanet];
        
        for (let nPlanet of natalNames) {
            let nLong = natalPlanets[nPlanet]?.longitude;
            if (nLong === undefined) continue;

            let diff = Math.abs(tLong - nLong);
            if (diff > 180) diff = 360 - diff;

            transitAspects.forEach(aspect => {
                if (Math.abs(diff - aspect.angle) <= aspect.orb) {
                    transits.push({
                        transitingPlanet: nameMap[tPlanet] || tPlanet,
                        natalPlanet: nameMap[nPlanet] || nPlanet,
                        aspect: aspect.name,
                        nature: aspect.type
                    });
                }
            });
        }
    }
    return transits;
}

export function calculateDignities(planetName, signName) {
    const dignities = {
        "Saule": { domicile: ["Lauva"], exaltation: ["Auns"], detriment: ["Ūdensvīrs"], fall: ["Svari"] },
        "Meness": { domicile: ["Vēzis"], exaltation: ["Vērsis"], detriment: ["Mežāzis"], fall: ["Skorpions"] },
        "Merkurs": { domicile: ["Dvīņi", "Jaunava"], exaltation: ["Jaunava", "Ūdensvīrs"], detriment: ["Strēlnieks", "Zivis"], fall: ["Zivis", "Lauva"] },
        "Venera": { domicile: ["Vērsis", "Svari"], exaltation: ["Zivis"], detriment: ["Skorpions", "Auns"], fall: ["Jaunava"] },
        "Marss": { domicile: ["Auns", "Skorpions"], exaltation: ["Mežāzis"], detriment: ["Svari", "Vērsis"], fall: ["Vēzis"] },
        "Jupiters": { domicile: ["Strēlnieks", "Zivis"], exaltation: ["Vēzis"], detriment: ["Dvīņi", "Jaunava"], fall: ["Mežāzis"] },
        "Saturns": { domicile: ["Mežāzis", "Ūdensvīrs"], exaltation: ["Svari"], detriment: ["Vēzis", "Lauva"], fall: ["Auns"] },
        "Urans": { domicile: ["Ūdensvīrs"], exaltation: ["Skorpions"], detriment: ["Lauva"], fall: ["Vērsis"] },
        "Neptuns": { domicile: ["Zivis"], exaltation: ["Vēzis", "Lauva"], detriment: ["Jaunava"], fall: ["Mežāzis", "Ūdensvīrs"] },
        "Plutons": { domicile: ["Skorpions"], exaltation: ["Auns", "Lauva"], detriment: ["Vērsis"], fall: ["Svari", "Ūdensvīrs"] }
    };

    if (!dignities[planetName]) return "Peregrīns (Neitrāls)";

    if (dignities[planetName].domicile.includes(signName)) return "Valdījums (Trijumfs)";
    if (dignities[planetName].exaltation.includes(signName)) return "Eksaltācija (Spēks)";
    if (dignities[planetName].detriment.includes(signName)) return "Trimda (Vājums)";
    if (dignities[planetName].fall.includes(signName)) return "Kritums (Grūtības)";
    
    return "Peregrīns (Neitrāls)";
}

export function calculateMidpoints(planets) {
    let midpoints = [];
    const importantPairs = [
        ["Saule", "Meness"], ["Saule", "Marss"], ["Merkurs", "Marss"], ["Marss", "Saturns"], ["Jupiters", "Urans"]
    ];

    const nameMap = {
        "Saule": "Sun", "Meness": "Moon", "Merkurs": "Mercury", "Venera": "Venus",
        "Marss": "Mars", "Jupiters": "Jupiter", "Saturns": "Saturn", "Urans": "Uranus",
        "Neptuns": "Neptune", "Plutons": "Pluto", "Ascendant": "Ascendant"
    };

    importantPairs.forEach(pair => {
        let p1 = planets[pair[0]];
        let p2 = planets[pair[1]];
        if (p1 && p2 && p1.longitude !== undefined && p2.longitude !== undefined) {
            let l1 = p1.longitude;
            let l2 = p2.longitude;
            let diff = Math.abs(l1 - l2);
            let mid = (l1 + l2) / 2;
            if (diff > 180) {
                mid = (mid + 180) % 360;
            }
            midpoints.push({
                pair: `${nameMap[pair[0]]}/${nameMap[pair[1]]}`,
                degree: mid
            });
        }
    });
    return midpoints;
}

export function getHouseFromCusps(deg, cusps) {
    // swisseph.calculateHouses() atgriež 1-INDEKSĒTU 13-elementu masīvu:
    // cusps[0] neizmantots, cusps[1..12] = māju 1–12 SĀKUMA grādi (cusps[1] = Ascendants,
    // cusps[10] = MC). Empīriski verificēts: cusps[1] === ascendant, cusps[10] === mc.
    // (Agrāk šī funkcija iterēja masīvu kā 0-indeksētu → KATRA māja bija nepareiza: 1.–6.,
    // 11., 12. atgrieza "12", bet 7.–10. atgrieza "1". Kartes ritenis sky_chart.js houseOf()
    // jau bija pareizs — šis tagad atbilst tam.)
    // Ja masīvs īsāks par 13 (piem. galējais swisseph-kļūmes fallback new Array(12)) — "?".
    if (!cusps || cusps.length < 13) return "?";

    for (let i = 1; i <= 12; i++) {
        let start = cusps[i];
        let end   = cusps[i === 12 ? 1 : i + 1]; // 12. māja noslēdzas pie 1. mājas kuspijas
        if (start < end) {
            if (deg >= start && deg < end) return i;
        } else {
            // šķērso 0° Aunu
            if (deg >= start || deg < end) return i;
        }
    }

    return 12; // drošības fallback (korektām kuspijām nesasniedzams)
}
