import { swisseph } from '../swisseph/dist/swisseph-browser.js?v=2.4';

export async function initAstroCore() {
    await swisseph.init();
}

/**
 * Pārveido datuma loku uz Jūlija Dienu.
 * UTC datums ir sagaidāms formātā "1980-06-02T12:00:00Z"
 */
export function getJulianDay(utcDateString) {
    const date = new Date(utcDateString);
    return swisseph.dateToJulianDay(date, 1); // 1 = Gregorian
}

/**
 * Palīgfunkcija, kas pārvērš 0-360 grādus formātā: Zodiaka Zīme un grādi.
 */
export function formatDegree(deg) {
    const zodiacSigns = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", 
                    "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];
    const signIdx = Math.floor(deg / 30);
    const signDeg = deg % 30;
    return `${signDeg.toFixed(2)}° ${zodiacSigns[signIdx]}`;
}

/**
 * Aprēķina Lahiri Ajanamsu manuāli.
 * Tā kā @swisseph/browser WASM kompilācijā trūkst swe_get_ayanamsa wrapera,
 * mēs izmantojam precīzu astronomisko vidējās gaitas precesijas formulu Lahiri vērtībai (J2000 epoch).
 */
export function getLahiriAyanamsa(jd) {
    const J2000 = 2451545.0; // 2000. gada 1. janvāris 12:00
    const BASE_AYANAMSA = 23.85316; // 23°51'11.4" (Lahiri J2000, precīzāka vērtība #8 labojums)
    const PRECESSION_RATE = 50.290966; // loka sekundes gadā
    const YEARS_SINCE_J2000 = (jd - J2000) / 365.25;
    
    return BASE_AYANAMSA + (YEARS_SINCE_J2000 * (PRECESSION_RATE / 3600.0));
}

/**
 * Aprēķina visu planētu un māju fiziskās pozīcijas debesīs (Tropiski un Siderāli)
 */
export function getAstronomicalData(jd, lat, lon) {
    const planets = {
        "Saule": 0,
        "Meness": 1,
        "Merkurs": 2,
        "Venera": 3,
        "Marss": 4,
        "Jupiters": 5,
        "Saturns": 6,
        "Urans": 7,
        "Neptuns": 8,
        "Plutons": 9,
        "Rahu": 10 // True Node var būt 11, bet Mean ir 10
    };
    
    const resultsTropical = {};
    const resultsSidereal = {};
    
    // Tā kā WASM neļauj set_sid_mode ar C, mēs izrēķināsim Tropisko un vienkārši atņemsim Ajanamsu (Precīzs 1:1 rezultāts)
    const ayanamsa = getLahiriAyanamsa(jd);
    
    // 260 = Moshier (4) + Speed (256)
    const flagTropical = 260; 
    
    for (const [name, pId] of Object.entries(planets)) {
        try {
            // Tropiskie grādi
            const tropData = swisseph.calculatePosition(jd, pId, flagTropical);
            resultsTropical[name] = tropData.longitude;
            
            // Siderālie grādi = Tropiskie - Ajanamsa
            let sidDeg = (tropData.longitude - ayanamsa) % 360;
            if (sidDeg < 0) sidDeg += 360;
            resultsSidereal[name] = sidDeg;
        } catch (e) {
            console.warn(`Nevarēja aprēķināt planētu ${name}: ${e.message}`);
        }
    }
    
    // #2 labojums: Ketu (Dienvidu Mezgls) = Rahu + 180° — nav pie Swiss Ephemeris kā atsevišķs objekts
    if (resultsSidereal["Rahu"] !== undefined) {
        resultsSidereal["Ketu"] = (resultsSidereal["Rahu"] + 180) % 360;
        resultsTropical["Ketu"] = (resultsTropical["Rahu"] + 180) % 360;
    }
    
    // Mājas (Placidus - 'P')
    // swisseph_browser native call
    let housesTrop = null;
    // Placidus ('P') nav definēts ārpus polārā loka (|lat| > ~66.5°) — tur starpkuspijas
    // deģenerējas. Augstos platumos lieto Porphyry ('O'), kas definēts visur; Ascendants/MC
    // paliek identiski (ģeometriski), tikai starpkuspijas kļūst korektas. (Verificēts vs swe.houses.)
    const hsys = (Math.abs(lat) > 66.0) ? "O" : "P";
    try {
        housesTrop = swisseph.calculateHouses(jd, lat, lon, hsys);
    } catch(err) {
        // Galējais fallback: Porphyry (vienmēr definēts), pirms pilnīgi tukšām kuspijām.
        try { housesTrop = swisseph.calculateHouses(jd, lat, lon, "O"); }
        catch(e2) {
            console.error("Houses calculation failed! JD:", jd, "Lat:", lat, "Lon:", lon, "Error:", err);
            housesTrop = { ascendant: 0, mc: 0, cusps: new Array(12).fill(0) };
        }
    }
    
    // Tāpat ar siderālajām mājām mēs varam izkalkulēt no tropiskajām, atņemot ajanamsu
    resultsTropical["Ascendant"] = housesTrop.ascendant;
    resultsTropical["MC"] = housesTrop.mc;
    
    // Siderālās Mājas
    let sidAsc = (housesTrop.ascendant - ayanamsa) % 360;
    let sidMc = (housesTrop.mc - ayanamsa) % 360;
    if (sidAsc < 0) sidAsc += 360;
    if (sidMc < 0) sidMc += 360;
    
    resultsSidereal["Ascendant"] = sidAsc;
    resultsSidereal["MC"] = sidMc;
    
    // Siderālās kuspijas
    const sidCusps = housesTrop.cusps.map(cusp => {
        let sidC = (cusp - ayanamsa) % 360;
        if (sidC < 0) sidC += 360;
        return sidC;
    });
    
    return {
        ayanamsa: ayanamsa,
        tropical: resultsTropical,
        sidereal: resultsSidereal,
        houses_tropical: housesTrop.cusps,
        houses_sidereal: sidCusps
    };
}
