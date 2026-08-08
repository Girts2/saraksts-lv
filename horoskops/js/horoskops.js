import { initAstroCore, getJulianDay, getAstronomicalData, formatDegree } from "./core_astro.js?v=9";
import { analyzeMayaMatrix, KEYS_TARO, NAVAL_DESCRIPTIONS, COLOR_PROFILES, TATVA_PROFILES, TONE_PROFILES, calculateMayanOracle, calculateTrecena } from "./maya_toltec.js?v=6";
import { getBaziPillars, calculateFiveElementsBalance, calculateTenGods, findBaZiConflicts, GET_HIDDEN_STEMS, calculateDaymasterStrength, calculateLuckPillars, getBaZiCombinations, calculateSymbolicStars } from "./bazi.js?v=12";
import { determineSect, calculateLots, getZodiacSignAndModality, calculateTemperament, getLifeHelmsman, getTerm, calculateTriplicityLords, calculateProfection, calculateHellenisticAspects, checkDoryphory } from "./hellenistic.js?v=3";
import { getNakshatraData, calculateVimshottariPeriods, getCurrentDasha, calculatePanchangSummary, calculateDailyMuhurtas, PLANET_THERAPY, NAKSHATRA_THERAPY, calculateMuntha, JUPITER_TRANSIT_MEANING, SATURN_TRANSIT_MEANING, getTithiData, calculateTaraBalaStatus, calculateNavamsha, calculateVedicAspects, calculateYogas, calculateAshtakavarga, calculateNityaYogaAndKarana } from "./vedic_kp.js?v=10";
import { getProgressedDate, analyzeProgressions, calculateAspects, calculateTransits, calculateDignities, calculateMidpoints, getHouseFromCusps } from "./western.js?v=5";
import { 
    calculateLeadership, calculateStressResilience, calculateTeamwork, 
    calculateMotivation, calculatePerformanceStyle, calculatePotentialRisks 
} from "./scoring.js?v=6";
import { calculatePersonality } from "./logic/personality.js?v=2";
import { buildInfluenceReport } from "./logic/influence.js?v=8";
import { calculateLeadershipType } from "./logic/leadership_type.js";
import { calculateCareerSuggestions } from "./logic/career_suggestions.js?v=2";
import { buildVedicPsychologyProfile } from "./vedic_interpretations.js?v=8";
import { generateHybridIntelligenceReport } from "./cross_cultural_interpretations.js?v=7";
import { buildWesternPsychologyProfile } from "./western_interpretations.js?v=4";
import { calculateCareerAnchors } from "./logic/career_anchors.js?v=5";
import { calculateTimingStrategy } from "./logic/timing_strategy.js?v=6";
import { calculateRelationshipDynamics } from "./logic/relationship_dynamics.js?v=4";
import { calculateExistentialAudit } from "./logic/existential_audit.js?v=5";
import { calculatePsychosomaticAudit } from "./logic/psychosomatic_audit.js?v=3";
// buildExecutiveSummary izņemts (2026-06-12): exec summary UI aizstāja Investora memorands;
// executive_summary.js paliek, jo investor_memo.js importē tā ATTACHMENT_HINTS/ANCHOR_COLLABORATION.
import { calculatePracticalScenarios } from "./logic/practical_scenarios.js?v=4";
import { interpretLuckPillar } from "./luck_pillar_interpretations.js?v=4";
import { buildChinesePsychologyProfile } from "./chinese_interpretations.js?v=2";
import { buildMayanPsychologyProfile, MAYAN_ARCHETYPES } from "./mayan_interpretations.js";
import { buildHellenisticProfile } from "./hellenistic_interpretations.js";
import { generateExecutiveSummary } from "./intelligence_core.js?v=2";
import { computeTimeSweep } from "./logic/time_sweep.js?v=5";
import { computeMaskSynthesis, ELEMENT_META, elementPersonaText } from "./logic/mask_synthesis.js?v=6";
import { localToUtc } from "./timezone/local_to_utc.js?v=1";

export async function generateFullProfile(birthDateString, birthTimeStr, lat, lon, currentLat, currentLon, isTimeUnknown = false, timezone = null, currentTimezone = null, gender = 'M', utcDateStr = null) {
    // 1. Iniciējam astronomisko dzinēju
    await initAstroCore();

    // 2. Apvienotais Datums un Laiks — birthTimeStr ir tīrs UTC.
    // JD/efemerīdai lietojam `utcDateStr` (UTC kalendāra datums no localToUtc, ja padots) —
    // NEVIS `birthDateString` (LOKĀLAIS datums, var atšķirties par ±1 dienu pie pusnakts
    // pārejas). `birthDateString` paliek neskarts profila attēlošanai un lokālā-kalendāra
    // sistēmām (Maiji, Ķeltu koki), kur pareizi ir lietot dzimšanas VIETAS kalendāra dienu.
    const dateTimeStr = `${utcDateStr || birthDateString}T${birthTimeStr}:00Z`;
    const utcDate = new Date(dateTimeStr);
    
    // Globālie datuma mainīgie citām sistēmām (progresijām, vecumam u.c.)
    const year = utcDate.getUTCFullYear();
    const month = utcDate.getUTCMonth() + 1;
    const day = utcDate.getUTCDate();

    // Bazi prasa lokālo saules laiku, tāpēc konvertējam UTC uz LMT (Local Mean Time)
    const lmtMs = utcDate.getTime() + (lon / 15) * 60 * 60 * 1000;
    const lmtDate = new Date(lmtMs);
    const baziYear = lmtDate.getUTCFullYear();
    const baziMonth = lmtDate.getUTCMonth() + 1;
    const baziDay = lmtDate.getUTCDate();
    const baziHour = lmtDate.getUTCHours();

    const jd = getJulianDay(dateTimeStr);
    
    // 3. Iegūstam debesu pozīcijas un Mājas
    const astroData = getAstronomicalData(jd, lat, lon);
    
    // 4. Iegūstam Maiju Datus
    const mayaData = analyzeMayaMatrix(birthDateString);
    const mayaSeal = mayaData.person_data.sign_idx;
    const mayaTone = mayaData.person_data.tone;

    const mayaOracle = calculateMayanOracle(mayaSeal, mayaTone);
    mayaData.oracle = mayaOracle;
    const mayaTrecena = calculateTrecena(mayaSeal, mayaTone);

    const mayanPsychData = {
        seal: mayaSeal,
        tone: mayaTone,
        trecena: mayaTrecena,
        oracle: mayaOracle,
        color: mayaData.person_data.color.split(" ")[0]
    };
    
    const mayanPsychology = buildMayanPsychologyProfile(mayanPsychData);
    mayaData.psychology = mayanPsychology;
    mayaData.oracleObjects = {
        guide: MAYAN_ARCHETYPES[mayaOracle.guide].name,
        analog: MAYAN_ARCHETYPES[mayaOracle.analog].name,
        antipode: MAYAN_ARCHETYPES[mayaOracle.antipode].name,
        occult: MAYAN_ARCHETYPES[mayaOracle.occult].name,
        sealName: MAYAN_ARCHETYPES[mayaSeal].name
    };
    mayaData.critic = MAYAN_ARCHETYPES[mayaOracle.antipode].name;

    // 6. Ķīniešu Bazi
    const baziData = getBaziPillars(baziYear, baziMonth, baziDay, baziHour, jd, astroData.tropical["Saule"]);
    // Nezināmam laikam stundas pīlārs ir pusdienlaika FIKCIJA → izslēdzam no agregātiem
    // (atzīts 3-pīlāru BaZi lasījums: Gads/Mēnesis/Diena). Citādi ~25% elementu balansa,
    // konfliktu un mainGod būtu balstīts patvaļīgā 12:00 stundā.
    const aggPillars  = isTimeUnknown ? [baziData.Year, baziData.Month, baziData.Day]
                                      : [baziData.Year, baziData.Month, baziData.Day, baziData.Hour];
    const aggBranches = aggPillars.map(p => p.Branch);
    const pillarsArray = aggPillars;
    const baziElements = calculateFiveElementsBalance(pillarsArray);
    const baziConflicts = findBaZiConflicts(aggBranches);
    const baziHiddenStems = GET_HIDDEN_STEMS(baziData.Day.Branch);
    
    const baziGods = {
        Year: calculateTenGods(baziData.Daymaster, baziData.Year.Stem),
        Month: calculateTenGods(baziData.Daymaster, baziData.Month.Stem),
        // Nezināmam laikam stundas Dievs ir fikcija → izslēgts no mainGod balsojuma
        Hour: isTimeUnknown ? null : calculateTenGods(baziData.Daymaster, baziData.Hour.Stem)
    };
    
    const countGods = {};
    Object.values(baziGods).forEach(g => {
        if(g) {
            countGods[g] = (countGods[g] || 0) + 1;
        }
    });

    let mainGod = "Nav_noteikts";
    if (Object.keys(countGods).length > 0) {
        mainGod = Object.keys(countGods).reduce((a, b) => countGods[a] > countGods[b] ? a : b);
    }
    
    let hiddenGod = null;
    if (baziHiddenStems.length > 0) {
        hiddenGod = calculateTenGods(baziData.Daymaster, baziHiddenStems[0]);
    }
    
    baziData.elements = baziElements;
    baziData.conflicts = baziConflicts;
    baziData.combinations = getBaZiCombinations(aggBranches);
    baziData.mainGod = mainGod;
    baziData.hiddenGod = hiddenGod;
    baziData.gods = baziGods;
    
    baziData.dm_strength = calculateDaymasterStrength(baziData.Daymaster, baziElements);
    // Veiksmes pīlāru virziens atkarīgs no dzimuma (Jaņ gads+vīr / Iņ gads+siev = forward),
    // sākuma vecums (起运) no Saules garuma (sk. calculateLuckPillars). Dzimums tagad padots.
    baziData.luck_pillars = calculateLuckPillars(baziData.Year.Stem, baziData.Month.Stem, baziData.Month.Branch, gender === 'M', astroData.tropical["Saule"])
        .map(lp => ({ ...lp, interpretation: interpretLuckPillar(lp, baziData.Daymaster) }));
    baziData.symbolic_stars = calculateSymbolicStars(baziData.Daymaster, baziData.Day.Branch, baziData.Year.Branch, aggBranches);
    
    const chinesePsychology = buildChinesePsychologyProfile({ dm: baziData.Daymaster, elements: baziElements, mainGod: mainGod, hiddenGod: hiddenGod, conflicts: baziConflicts, gods: baziGods });
    baziData.psychology = chinesePsychology;

    // 7. Vēdu KP / Nakšatras
    const nakData = getNakshatraData(astroData.sidereal["Meness"]);
    // Padodam pilnu UTC laiku, lai Dasha periodi sākas no precīzā dzimšanas brīža
    const periods = calculateVimshottariPeriods(dateTimeStr, nakData);
    
    // Aprēķinām Kvalitātes Filtrus (Nakšatras) visām 9 planētām
    astroData.sidereal["Ketu"] = (astroData.sidereal["Rahu"] + 180) % 360;
    const planetNakshatras = {};
    const vedicPlanets = ["Saule", "Meness", "Marss", "Merkurs", "Jupiters", "Venera", "Saturns", "Rahu", "Ketu"];
    vedicPlanets.forEach(p => {
        if (astroData.sidereal[p] !== undefined) {
            let pNak = getNakshatraData(astroData.sidereal[p]);
            let pTherapy = PLANET_THERAPY[p] || { focus: "", risks: "", upaya_action: "", dharma: "" };
            let nTherapy = NAKSHATRA_THERAPY[pNak.nakshatra] || { shadow: "", upaya_spirit: "" };
            
            planetNakshatras[p] = {
                ...pNak,
                therapy: {
                    focus: pTherapy.focus,
                    risks: `${pTherapy.risks} Nakšatras ēna: ${nTherapy.shadow}`,
                    upaya: `Garīgums: ${nTherapy.upaya_spirit} Fiziskā rīcība: ${pTherapy.upaya_action}`,
                    dharma: pTherapy.dharma
                }
            };
        }
    });

    // Vēdisko māju un zīmju aprēķini psiholoģiskajam profilam
    const karakaPlanets = ["Saule", "Meness", "Marss", "Merkurs", "Jupiters", "Venera", "Saturns"];
    let atmakaraka = { planet: "Saule", degree: -1 };
    karakaPlanets.forEach(p => {
        if (astroData.sidereal[p] !== undefined) {
            let deg = astroData.sidereal[p] % 30;
            if (deg > atmakaraka.degree) {
                atmakaraka.degree = deg;
                atmakaraka.planet = p;
            }
        }
    });

    const ascSignIdx = Math.floor(astroData.sidereal["Ascendant"] / 30);
    const getSgnAndHouse = (pDeg) => {
        let sign = Math.floor(pDeg / 30);
        let house = ((sign - ascSignIdx + 12) % 12) + 1;
        return { sign, house };
    };

    const vPsychData = {
        moon: { ...getSgnAndHouse(astroData.sidereal["Meness"]), nakshatra: nakData.index + 1 },
        sun: getSgnAndHouse(astroData.sidereal["Saule"]),
        mercury: getSgnAndHouse(astroData.sidereal["Merkurs"]),
        mars: getSgnAndHouse(astroData.sidereal["Marss"]),
        jupiter: getSgnAndHouse(astroData.sidereal["Jupiters"]),
        saturn: getSgnAndHouse(astroData.sidereal["Saturns"]),
        venus: getSgnAndHouse(astroData.sidereal["Venera"]),
        rahu: getSgnAndHouse(astroData.sidereal["Rahu"]),
        ketu: getSgnAndHouse(astroData.sidereal["Ketu"]),
        ascendant: getSgnAndHouse(astroData.sidereal["Ascendant"]),
        atmakaraka: atmakaraka
    };
    const vedicPsychology = buildVedicPsychologyProfile(vPsychData, isTimeUnknown);
    const hybridIntelligence = generateHybridIntelligenceReport(baziData, mayaData, vPsychData);
    
    // Ņemam šodienas datumu kā target
    const todayExact = new Date();
    const todayStr = todayExact.toISOString().split('T')[0];
    const currentDasha = getCurrentDasha(periods, todayStr);
    
    // Šodienas astronomija tranzītiem un Mēness fāzei
    const todayJd = getJulianDay(todayExact.toISOString());
    const astroToday = getAstronomicalData(todayJd, lat, lon);

    // Vēdu Jauno Parametru Aprēķins
    const navamshaMap = {};
    for(let p in astroData.sidereal) {
        if(astroData.sidereal[p] !== undefined) navamshaMap[p] = calculateNavamsha(astroData.sidereal[p]);
    }
    const vedicAspects = calculateVedicAspects(astroData.sidereal);
    const yogas = calculateYogas(astroData.sidereal, astroData.sidereal["Ascendant"]);
    const ashtakavarga = calculateAshtakavarga(astroData.sidereal, astroData.sidereal["Ascendant"]);
    const panchangaExt = calculateNityaYogaAndKarana(astroData.sidereal["Saule"], astroData.sidereal["Meness"]);


    // 8. Senā Grieķija (Hellenistic)
    let helAscDeg = astroData.tropical["Ascendant"];
    if (isTimeUnknown) {
        helAscDeg = astroData.tropical["Saule"];
    }
    const sect = determineSect(helAscDeg, astroData.tropical["Saule"]);
    const lots = calculateLots(helAscDeg, astroData.tropical["Saule"], astroData.tropical["Meness"], sect);
    const ascSign = getZodiacSignAndModality(helAscDeg);

    // 9. Rietumu Progresijas
    const exactNowDt = new Date();
    const progDateInfo = getProgressedDate(utcDate, exactNowDt);
    const progJd = getJulianDay(progDateInfo.progressed_date.toISOString());
    const progAstro = getAstronomicalData(progJd, lat, lon);
    const progressions = analyzeProgressions(astroData.tropical, progAstro.tropical);
    
    // 10. Sinerģijas Loģika (Svaru Skalas)
    const dmPole = baziData.Daymaster.polarity;
    const dmElem = baziData.Daymaster.element;
    const mColor = mayaData.person_data.color;
    const mSign = mayaData.person_data.sign;
    const tatva = mayaData.person_data.tatva;
    const moonProgDeg = progressions.moon_prog_deg;                      // pašreizējais (laika-tabiem)
    const waveKrasa = mayaData.lifecycle.wave.Krasa;                     // pašreizējais (laika-tabiem)
    const dashaLord = currentDasha ? currentDasha.lord : "Nezināms";    // pašreizējais (laika-tabiem)
    const nakLord = nakData.lord;

    // PERSONĪBAS skori = iedzimti "rūpnīcas iestatījumi" → lieto TIKAI birth-fiksētus ievadus,
    // NE tranzīta laiku (citādi D/I/V dreifē ar pašreizējo datumu; sk. determinisma testu).
    const pMoon  = astroData.tropical["Meness"];   // natal Mēness (= progresijas pie dzimšanas), NE progresētais
    const pDasha = nakLord;                          // dzimšanas dašas lords (= nakšatras lords), NE pašreizējā daša
    const pWave  = "Sarkans";                        // Maiju vilnis pie dzimšanas (cycleYear=1 vienmēr Sarkans), NE pašreizējais

    const leaderScore = calculateLeadership(pDasha, dmPole, dmElem, mColor, mSign, tatva, pMoon, pWave);
    const stressScore = calculateStressResilience(mColor, tatva, dmPole, dmElem, pMoon);
    const teamScore = calculateTeamwork(mSign, pMoon, tatva, dmPole, dmElem, mColor);
    // Sociālās motivācijas data-driven signāli (laika-robusti): Venera/Mēness cieņa + BaZi pavadoņi (Friend/Rob_Wealth)
    const venusDignitySoc = calculateDignities("Venera", getZodiacSignAndModality(astroData.tropical["Venera"]).sign);
    const moonDignitySoc  = calculateDignities("Meness", getZodiacSignAndModality(astroData.tropical["Meness"]).sign);
    const hasCompanionStar = ["Friend","Rob_Wealth"].includes(mainGod) || Object.values(baziGods).some(g => ["Friend","Rob_Wealth"].includes(g));
    const motivScore = calculateMotivation(pDasha, nakLord, dmElem, mColor, venusDignitySoc, moonDignitySoc, hasCompanionStar);
    const perfScore = calculatePerformanceStyle(dmPole, dmElem, mColor, lots.spirit_deg, pDasha);
    const riskScore = calculatePotentialRisks(mSign, pMoon, dmPole, dmElem, tatva, nakLord, pDasha);

    // 11. Rietumu Astroloģijas (Psiholoģiskais Rentgens) Datu apstrāde
    const getTropSgnAndHouse = (pDeg) => {
        let sign = Math.floor(pDeg / 30);
        let house = getHouseFromCusps(pDeg, astroData.houses_tropical);
        const TROP_SIGNS = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];
        return { sign: TROP_SIGNS[sign], house, longitude: pDeg };
    };

    const tropPlanets = {
        "Saule": getTropSgnAndHouse(astroData.tropical["Saule"]),
        "Meness": getTropSgnAndHouse(astroData.tropical["Meness"]),
        "Merkurs": getTropSgnAndHouse(astroData.tropical["Merkurs"]),
        "Venera": getTropSgnAndHouse(astroData.tropical["Venera"]),
        "Marss": getTropSgnAndHouse(astroData.tropical["Marss"]),
        "Jupiters": getTropSgnAndHouse(astroData.tropical["Jupiters"]),
        "Saturns": getTropSgnAndHouse(astroData.tropical["Saturns"]),
        "Urans": getTropSgnAndHouse(astroData.tropical["Urans"]),
        "Neptuns": getTropSgnAndHouse(astroData.tropical["Neptuns"]),
        "Plutons": astroData.tropical["Plutons"] !== undefined ? getTropSgnAndHouse(astroData.tropical["Plutons"]) : undefined,
        // Hirons: Moshier efemerīda Chiron nerēķina → pievieno TIKAI ja vērtība ir
        // (agrāk atslēga karājās ar undefined un patērētājiem vajadzēja aizsargus).
        ...(astroData.tropical["Hirons"] !== undefined ? { "Hirons": getTropSgnAndHouse(astroData.tropical["Hirons"]) } : {}),
        "Rahu": getTropSgnAndHouse(astroData.tropical["Rahu"]),
        "Ketu": getTropSgnAndHouse((astroData.tropical["Rahu"] + 180) % 360),
        "Ascendant": getTropSgnAndHouse(astroData.tropical["Ascendant"]),
        "MC": getTropSgnAndHouse(astroData.tropical["MC"])
    };
    
    // Dignities
    for (let p in tropPlanets) {
        if (tropPlanets[p] && tropPlanets[p].sign) {
            tropPlanets[p].dignity = calculateDignities(p, tropPlanets[p].sign);
        }
    }

    const tropAspects = calculateAspects(tropPlanets);
    const midpoints = calculateMidpoints(tropPlanets);
    
    // Transits
    const exactNowJd = getJulianDay(exactNowDt.toISOString());
    const astroTodayExact = getAstronomicalData(exactNowJd, lat, lon);
    const transits = calculateTransits(tropPlanets, astroTodayExact.tropical);

    const westernPsychology = buildWesternPsychologyProfile({ planets: tropPlanets, houses: astroData.houses_tropical, aspects: tropAspects });

    // Hellenistic calculations
    const ascSignHell = getTropSgnAndHouse(helAscDeg).sign;
    const moonSignHell = getTropSgnAndHouse(astroData.tropical["Meness"]).sign;
    const hellenisticHumor = calculateTemperament(ascSignHell, moonSignHell);
    const helmsman = getLifeHelmsman(ascSignHell, tropPlanets);
    const spiritLotSign = getTropSgnAndHouse(lots.spirit_deg).sign;
    const fortuneLotSign = getTropSgnAndHouse(lots.fortune_deg).sign;

    // Age for Profection
    let age = exactNowDt.getFullYear() - year;
    if (exactNowDt.getMonth() + 1 < month || (exactNowDt.getMonth() + 1 === month && exactNowDt.getDate() < day)) {
        age--;
    }
    
    // Add term calculation to tropPlanets
    for (let p in tropPlanets) {
        if (tropPlanets[p] && tropPlanets[p].sign && tropPlanets[p].longitude !== undefined) {
            let degInSign = tropPlanets[p].longitude % 30;
            tropPlanets[p].term = getTerm(tropPlanets[p].sign, degInSign);
        }
    }
    
    // Calculate new Hellenistic data
    const sectLight = sect === "Diena" ? "Saule" : "Meness";
    let sectLightElement = "Uguns";
    if (astroData.tropical[sectLight] !== undefined) {
        sectLightElement = getZodiacSignAndModality(astroData.tropical[sectLight]).element;
    }
    
    const triplicity = calculateTriplicityLords(sectLightElement, sect);
    const profection = calculateProfection(ascSignHell, age);
    const helAspects = calculateHellenisticAspects(tropPlanets);
    const doryphory = checkDoryphory(tropPlanets, sect);

    const unwrapDiff = (diff) => {
         while(diff < -180) diff += 360;
         while(diff > 180) diff -= 360;
         return diff;
    };
    const astroTom = getAstronomicalData(jd + 1, lat, lon);
    const isSaturnRetro = unwrapDiff(astroTom.sidereal["Saturns"] - astroData.sidereal["Saturns"]) < 0;

    // 2026-07-03 audita labojums: sekciju paneļi (komunikācija/stress/analītika/
    // kreativitāte) lasa hellenistic.data.planets[].condition — šī masīva agrāk
    // NEBIJA, tāpēc visi hellēnistiskie skori bija konstante 60. Nosacījums pēc
    // klasiskiem kritērijiem no jau aprēķinātiem datiem: cieņa (tropiskā) + sektas
    // piederība (dienas: Saule/Jupiters/Saturns; nakts: Mēness/Venera/Marss;
    // Merkurs — neitrāls, vērtē tikai pēc cieņas).
    const sectMates = sect === "Diena" ? ["Saule", "Jupiters", "Saturns"] : ["Meness", "Venera", "Marss"];
    const hellenisticPlanets = ["Saule", "Meness", "Merkurs", "Venera", "Marss", "Jupiters", "Saturns"].map(p => {
        const dig = tropPlanets[p]?.dignity || "";
        const strong = dig.includes("Valdījums") || dig.includes("Eksaltācija");
        const weak = dig.includes("Kritums") || dig.includes("Trimda");
        const inSect = sectMates.includes(p) || p === "Merkurs";
        let condition = "Vidējs";
        if (strong && inSect) condition = "Lielisks";
        else if (weak && !inSect) condition = "Vājš";
        return { name: p, condition: condition };
    });

    const hellenisticData = {
        sect: sect,
        humor: hellenisticHumor,
        helmsman: helmsman,
        oikodespotes: helmsman.planet,
        spiritLot: { sign: spiritLotSign },
        fortuneLot: { sign: fortuneLotSign },
        planets: hellenisticPlanets,
        triplicity: triplicity,
        profection: profection,
        aspects: helAspects,
        doryphory: doryphory,
        isSaturnRetro: isSaturnRetro
    };
    const hellenisticPsychology = buildHellenisticProfile(hellenisticData);

    const profile = {
        birth_info: {
            date: birthDateString,
            time: birthTimeStr,
            isTimeUnknown: isTimeUnknown,
            jd: Number(jd.toFixed(4)),
            sect: sect,
            lat: lat,
            lon: lon,
            timezone: timezone
        },
        current_loc: {
            lat: currentLat,
            lon: currentLon
        },
        hybrid_intelligence: hybridIntelligence,
        astro_base: {
            ayanamsa: astroData.ayanamsa,
            tropical: {
                sun: formatDegree(astroData.tropical["Saule"]),
                moon: formatDegree(astroData.tropical["Meness"]),
                ascendant: formatDegree(astroData.tropical["Ascendant"]),
                asc_modality: ascSign
            },
            sidereal: {
                sun: formatDegree(astroData.sidereal["Saule"]),
                moon: formatDegree(astroData.sidereal["Meness"]),
                ascendant: formatDegree(astroData.sidereal["Ascendant"])
            }
        },
        maya_profile: {
            basic: mayaData.person_data,
            lifecycle: mayaData.lifecycle,
            psychology: mayaData.psychology,
            oracle: mayaData.oracleObjects,
            critic: mayaData.critic,
            extended: mayaData.extended_dreamspell
        },
        bazi: baziData,
        western: {
            psychology: westernPsychology,
            aspects: tropAspects,
            planets: tropPlanets,
            transits: transits,
            midpoints: midpoints
        },
        vedic: {
            moonSignIdx: vPsychData.moon.sign,
            atmakaraka: atmakaraka,
            nakshatra: nakData,
            current_dasha: currentDasha,
            all_dashas: periods,
            filters: planetNakshatras,
            psychology: vedicPsychology,
            planets: {
                // 2026-07-03 audita labojums: sidereal_deg tagad VISĀM planētām + Ascendant
                // ieraksts — agrāk grādi bija tikai Saturnam, tāpēc reliability.js Vēdu
                // Saules Integritāte un Mēness/Zeme Stabilitāte visiem rēķinājās ar 0°
                // (un ascDeg=0). "Mēness"/"Meness" — abas atslēgas apzināti pilnas un
                // identiskas (patērētājos vēsturiski abi rakstības varianti).
                "Saule":   { house: vPsychData.sun.house,     sidereal_deg: astroData.sidereal["Saule"] },
                "Mēness":  { house: vPsychData.moon.house,    sidereal_deg: astroData.sidereal["Meness"] },
                "Meness":  { house: vPsychData.moon.house,    sidereal_deg: astroData.sidereal["Meness"] },
                "Marss":   { house: vPsychData.mars.house,    sidereal_deg: astroData.sidereal["Marss"] },
                "Merkurs": { house: vPsychData.mercury.house, sidereal_deg: astroData.sidereal["Merkurs"] },
                "Jupiters":{ house: vPsychData.jupiter.house, sidereal_deg: astroData.sidereal["Jupiters"] },
                "Venera":  { house: vPsychData.venus.house,   sidereal_deg: astroData.sidereal["Venera"] },
                "Saturns": {
                    house: vPsychData.saturn.house,
                    sidereal_deg: astroData.sidereal["Saturns"],
                    isRetrograde: isSaturnRetro
                },
                "Rahu":    { house: vPsychData.rahu.house,    sidereal_deg: astroData.sidereal["Rahu"] },
                "Ketu":    { house: vPsychData.ketu.house,    sidereal_deg: astroData.sidereal["Ketu"] },
                "Ascendant": { house: 1, sidereal_deg: astroData.sidereal["Ascendant"] },
                ...(astroData.sidereal["Urans"]   !== undefined ? { "Urans":   { house: getSgnAndHouse(astroData.sidereal["Urans"]).house,   sidereal_deg: astroData.sidereal["Urans"]   } } : {}),
                ...(astroData.sidereal["Neptuns"] !== undefined ? { "Neptuns": { house: getSgnAndHouse(astroData.sidereal["Neptuns"]).house, sidereal_deg: astroData.sidereal["Neptuns"] } } : {}),
                ...(astroData.sidereal["Hirons"]  !== undefined ? { "Hirons":  { house: getSgnAndHouse(astroData.sidereal["Hirons"]).house,  sidereal_deg: astroData.sidereal["Hirons"]  } } : {})
            },
            muhurtas_today: calculateDailyMuhurtas(todayStr, currentLat, currentLon),
            super_events: calculateSuperEvents(utcDate.getTime(), astroData.sidereal, periods),
            navamsha: navamshaMap,
            drishti: vedicAspects,
            yogas: yogas,
            ashtakavarga: ashtakavarga,
            panchanga_ext: panchangaExt
        },
        // Neapstrādātās sidēriskās dzimšanas pozīcijas (cilne 'y': Arudha, spēka indekss)
        birth_sidereal: astroData.sidereal,
        lunar_phase_angle: (astroToday.tropical["Meness"] - astroToday.tropical["Saule"] + 360) % 360,
        tithi_now: getTithiData((astroToday.tropical["Meness"] - astroToday.tropical["Saule"] + 360) % 360),
        transits_today: astroToday.sidereal,
        hellenistic: {
            data: hellenisticData,
            psychology: hellenisticPsychology,
            lot_of_fortune: formatDegree(lots.fortune_deg),
            lot_of_spirit: formatDegree(lots.spirit_deg),
            // Atklāti agrāk aprēķinātie, bet neglabātie dati (cilne 'y')
            lot_of_fortune_sign: fortuneLotSign,
            lot_of_spirit_sign: spiritLotSign,
            profection: profection,      // {house, sign, lord} — gada laika valdnieks
            triplicity: triplicity        // [valdnieks1, valdnieks2, valdnieks3]
        },
        progressions: {
            sun_prog: formatDegree(progressions.sun_prog_deg),
            moon_prog: formatDegree(progressions.moon_prog_deg),
            sun_changed: progressions.sun_changed_sign,
            moon_changed: progressions.moon_changed_sign,
            prog_date_exact: progDateInfo.progressed_date.toISOString().split('T')[0],
            age: Number(progDateInfo.age_in_years.toFixed(2))
        },
        synergy: {
            leadership: leaderScore,
            stress: stressScore,
            teamwork: teamScore,
            motivation: motivScore,
            performance: perfScore,
            risks: riskScore,
            demon: mayaData.today_data.demon_info[0],
            taro: KEYS_TARO[mayaData.impact_key] ? KEYS_TARO[mayaData.impact_key]["Tēma"] : "Nezināms",
            taro_key: mayaData.impact_key
        },
        lunar_calendar: buildLunarCalendar(lat, lon, nakData.index),
        nakshatra_transits_48h: buildNakshatraTransits(lat, lon, nakData.index),
        // Balsotājs 2 nedēļas konsensam: Rietumu ātrie tranzīti uz natālo (NE Mēness — tas ir balsotājs 1)
        western_transit_week: buildWesternTransitWeek(astroData.tropical, lat, lon, currentTimezone || timezone)
    };

    // --- Gada Virziena (Yearly Overview) 365 Dienu Skeneris ---
    const yearly_forecast = {};
    const birthTimeMs = utcDate.getTime();
    const nowMs = new Date(todayStr + "T12:00:00Z").getTime();
    const ageYrs = (nowMs - birthTimeMs) / (365.25 * 86400000);
    
    yearly_forecast.muntha = calculateMuntha(astroData.sidereal["Ascendant"], ageYrs);
    yearly_forecast.months = [];
    yearly_forecast.retrogrades = [];
    yearly_forecast.eclipses = [];

    let currentMonth = null;
    let prevSunSign = -1;
    let prevMercDeg = -1;
    let prevMarsDeg = -1;
    let isMercRetro = false;
    let isMarsRetro = false;
    let retroMercStart = null;
    let retroMarsStart = null;

    for (let dayOffset = -181; dayOffset <= 180; dayOffset++) {
        const iterMs = nowMs + (dayOffset * 86400000);
        const iterDateStr = new Date(iterMs).toISOString(); 
        const iterJd = getJulianDay(iterDateStr);
        const iterData = getAstronomicalData(iterJd, lat, lon);
        
        const sunDeg = iterData.sidereal["Saule"];
        const moonDeg = iterData.sidereal["Meness"];
        const mercDeg = iterData.sidereal["Merkurs"];
        const marsDeg = iterData.sidereal["Marss"];
        const rahuDeg = iterData.sidereal["Rahu"];
        const ketuDeg = (rahuDeg + 180) % 360;

        // 1. Sankranti (Mēneši atbilstoši Saules zīmēm)
        const sunSign = Math.floor(sunDeg / 30);
        if (sunSign !== prevSunSign) {
            if (currentMonth) {
                currentMonth.end = iterMs;
                yearly_forecast.months.push(currentMonth);
            }
            const zodiacSigns = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];
            currentMonth = {
                start: iterMs,
                end: null,
                signIndex: sunSign,
                tooltip: `Saule zīmē: ${zodiacSigns[sunSign]}\nTranzīta periods lēmumu pieņemšanai.`
            };
            prevSunSign = sunSign;
        }

        // 2. Retrogrādie (Mercury / Mars)
        const unwrap = (diff) => {
             while(diff < -180) diff += 360;
             while(diff > 180) diff -= 360;
             return diff;
        };

        if (prevMercDeg >= 0) {
            const mercDiff = unwrap(mercDeg - prevMercDeg);
            if (mercDiff < 0 && !isMercRetro) {
                isMercRetro = true; retroMercStart = iterMs;
            } else if (mercDiff >= 0 && isMercRetro) {
                isMercRetro = false;
                yearly_forecast.retrogrades.push({ planet: "Merkurs", start: retroMercStart, end: iterMs });
            }
            const marsDiff = unwrap(marsDeg - prevMarsDeg);
            if (marsDiff < 0 && !isMarsRetro) {
                isMarsRetro = true; retroMarsStart = iterMs;
            } else if (marsDiff >= 0 && isMarsRetro) {
                isMarsRetro = false;
                yearly_forecast.retrogrades.push({ planet: "Marss", start: retroMarsStart, end: iterMs });
            }
        }
        prevMercDeg = mercDeg; prevMarsDeg = marsDeg;

        // 3. Eklipses
        const tithiDiff = (moonDeg - sunDeg + 360) % 360;
        const distToRahu = Math.min(Math.abs(sunDeg - rahuDeg), 360 - Math.abs(sunDeg - rahuDeg));
        const distToKetu = Math.min(Math.abs(sunDeg - ketuDeg), 360 - Math.abs(sunDeg - ketuDeg));
        
        const isNearNode = (distToRahu < 16) || (distToKetu < 16);
        const isNewMoonDay = (tithiDiff <= 8 || tithiDiff >= 352);
        const isFullMoonDay = (tithiDiff >= 172 && tithiDiff <= 188);

        if (isNearNode && (isNewMoonDay || isFullMoonDay)) {
            if (yearly_forecast.eclipses.length === 0 || (iterMs - yearly_forecast.eclipses[yearly_forecast.eclipses.length-1].time) > 864000000) {
                yearly_forecast.eclipses.push({
                    time: iterMs,
                    type: isNewMoonDay ? "Saules Aptumsums" : "Mēness Aptumsums"
                });
            }
        }
    }
    if (currentMonth) {
        currentMonth.end = nowMs + (180 * 86400000);
        yearly_forecast.months.push(currentMonth);
    }
    if (isMercRetro) yearly_forecast.retrogrades.push({ planet: "Merkurs", start: retroMercStart, end: nowMs + (180 * 86400000) });
    if (isMarsRetro) yearly_forecast.retrogrades.push({ planet: "Marss", start: retroMarsStart, end: nowMs + (180 * 86400000) });

    // Translation of current transits
    const jupSign = Math.floor(astroToday.sidereal["Jupiters"] / 30);
    const satSign = Math.floor(astroToday.sidereal["Saturns"] / 30);
    yearly_forecast.jupiter_text = JUPITER_TRANSIT_MEANING[jupSign] || "Nezināms";
    yearly_forecast.saturn_text = SATURN_TRANSIT_MEANING[satSign] || "Nezināms";

    profile.vedic.yearly_forecast = yearly_forecast;
    // --- Gada Virzienu Skeneris Beidzas ---

    // 24h lokālā laika sadalījums — kad dzimšanas laiks nav zināms.
    // Ascendants un planētu mājas iziet cauri visām 12 zīmēm/mājām 24h laikā;
    // šis dod varbūtību sadalījumus vietā fiktīvi pārliecinātai pusdienlaika vērtībai.
    if (isTimeUnknown) {
        try {
            profile.timeSweep = computeTimeSweep({ dateStr: birthDateString, lat, lon, timezone });
        } catch (e) {
            console.warn("computeTimeSweep neizdevās:", e);
        }

        // Maskas (socialMask) hibrīds avotā: bez precīza laika Ascendents = pieņemta
        // zīme (12:00), kas runā pretī 24h sintēzei. Aizvietojam ar dominējošās
        // stihijas aprakstu, lai VISAS cilnes (Vēdu/Rietumu) sakrīt ar t3 sintēzi.
        const maskSynth = computeMaskSynthesis(profile);
        if (maskSynth.applicable) {
            const el = maskSynth.topElement;
            const note = `Bez precīza dzimšanas laika maska dota pa dominējošo stihiju (${el}), ne vienu zīmi.`;
            if (profile.vedic?.psychology)
                profile.vedic.psychology.socialMask = elementPersonaText(el, { note });
            if (profile.western?.psychology)
                profile.western.psychology.socialMask = `${ELEMENT_META[el].mask}. ${note}`;
        }
    }

    profile.personality = isTimeUnknown
        ? averageDayPersonality(profile, birthDateString, lat, lon, timezone)
        : calculatePersonality(profile);
    profile.influence   = buildInfluenceReport(profile.personality);
    profile.leadership     = calculateLeadershipType(profile.personality);
    profile.careers        = await calculateCareerSuggestions(profile.personality);
    profile.careerAnchors  = calculateCareerAnchors(profile, profile.leadership);
    profile.timing         = calculateTimingStrategy(profile, profile.careerAnchors);
    profile.relationshipDynamics = calculateRelationshipDynamics(profile);
    profile.existentialAudit     = calculateExistentialAudit(profile);
    profile.psychosomaticAudit   = calculatePsychosomaticAudit(profile);
    profile.scenarios            = calculatePracticalScenarios(profile);

    // Construct the overarching execution intelligence report based on all profiles
    profile.executive_summary = generateExecutiveSummary(profile);

    return profile;
}

// Vidējo dienas personalitātes aprēķins (nezināms dzimšanas laiks)
// 48 soļi × 30 min — katrs solis ar atkārtoti aprēķinātām māju pozīcijām.
// Sweepo LOKĀLO laiku (ne UTC): katrs solis ir vietējais HH:MM, konvertēts uz UTC.
function averageDayPersonality(profile, birthDateString, lat, lon, timezone = null) {
    const TROP_SIGNS = ["Auns","Vērsis","Dvīņi","Vēzis","Lauva","Jaunava","Svari","Skorpions","Strēlnieks","Mežāzis","Ūdensvīrs","Zivis"];
    const STEP  = 30;
    const STEPS = (24 * 60) / STEP; // 48

    const basePlanets = profile.western?.planets || {};
    const allResults  = [];

    for (let i = 0; i < STEPS; i++) {
        const totalMin = i * STEP;
        const hh = String(Math.floor(totalMin / 60)).padStart(2, '0');
        const mm = String(totalMin % 60).padStart(2, '0');
        const conv    = localToUtc(birthDateString, `${hh}:${mm}`, lon, timezone);
        const jd_t    = getJulianDay(`${conv.utcDateStr || birthDateString}T${conv.utcStr}:00Z`);
        const astro_t = getAstronomicalData(jd_t, lat, lon);

        const getH = (pDeg) => ({
            sign:      TROP_SIGNS[Math.floor(pDeg / 30)],
            house:     getHouseFromCusps(pDeg, astro_t.houses_tropical),
            longitude: pDeg
        });

        const planets_t = {};
        for (const pName of ["Saule","Meness","Merkurs","Venera","Marss","Jupiters","Saturns","Urans","Neptuns"]) {
            const pDeg = astro_t.tropical[pName];
            if (pDeg !== undefined) planets_t[pName] = { ...getH(pDeg), dignity: basePlanets[pName]?.dignity };
        }
        for (const pName of ["Plutons","Hirons"]) {
            const pDeg = astro_t.tropical[pName];
            if (pDeg !== undefined) planets_t[pName] = { ...getH(pDeg), dignity: basePlanets[pName]?.dignity };
        }
        const rahuDeg = astro_t.tropical["Rahu"];
        if (rahuDeg !== undefined) {
            planets_t["Rahu"] = getH(rahuDeg);
            planets_t["Ketu"] = getH((rahuDeg + 180) % 360);
        }
        if (astro_t.tropical["Ascendant"] !== undefined) planets_t["Ascendant"] = getH(astro_t.tropical["Ascendant"]);
        if (astro_t.tropical["MC"]         !== undefined) planets_t["MC"]        = getH(astro_t.tropical["MC"]);

        const mod = { ...profile, western: { ...profile.western, planets: planets_t } };
        allResults.push(calculatePersonality(mod));
    }

    // Vidējo pct vērtību aprēķins
    // Template (id, label, desc) no 12:00 soļa — atbilst bāzes profilam
    const midResult = allResults[Math.floor(STEPS / 2)];
    return midResult.map((cat, ci) => ({
        ...cat,
        traits: cat.traits.map((t, ti) => ({
            ...t,
            pct: Math.round(allResults.reduce((sum, r) => sum + r[ci].traits[ti].pct, 0) / STEPS)
        }))
    }));
}

// Balsotājs 2: 7 dienu Rietumu ātro tranzītu aspekti uz natālo (tropiskā).
// Aģenti = Saule/Merkurs/Venēra/Marss (NE Mēness — balsotājs 1). Mērķi = laika-stabilās
// natālās planētas (Mēness/Asc/MC izlaisti, jo atkarīgi no dzimšanas laika). Šaurs orbs (1.5°),
// jo "perfektējas šonedēļ". Ģeocentriskie garumi nav atkarīgi no lat/lon.
function buildWesternTransitWeek(natalTropical, lat, lon, timezone = null) {
    const FAST    = ["Saule", "Merkurs", "Venera", "Marss"];
    const TARGETS = ["Saule", "Merkurs", "Venera", "Marss", "Jupiters", "Saturns"];
    const ASPECTS = [
        { name: "konjunkcija", angle: 0,   harmony: 0  },
        { name: "sekstils",    angle: 60,  harmony: 1  },
        { name: "kvadrāts",    angle: 90,  harmony: -1 },
        { name: "trigons",     angle: 120, harmony: 1  },
        { name: "opozīcija",   angle: 180, harmony: -1 },
    ];
    const ORB = 1.5;
    const sep = (a, b) => { let d = Math.abs(a - b) % 360; return d > 180 ? 360 - d : d; };

    const today = new Date(); today.setUTCHours(12, 0, 0, 0);
    
    // Precalculate positions for 9 days (from offset -1 to 7) to allow interpolation
    const dailyPos = [];
    for (let i = -1; i <= 7; i++) {
        const d = new Date(today.getTime() + i * 86400000);
        let trop = null;
        try { trop = getAstronomicalData(getJulianDay(d.toISOString()), lat, lon).tropical; }
        catch (e) {}
        dailyPos.push(trop);
    }

    const out = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(today.getTime() + i * 86400000);
        const tropPrev = dailyPos[i];     // offset i-1 (dailyPos[0] is offset -1)
        const tropCurr = dailyPos[i + 1]; // offset i   (dailyPos[1] is offset 0)
        const tropNext = dailyPos[i + 2]; // offset i+1 (dailyPos[2] is offset 1)
        
        if (!tropCurr) { out.push(null); continue; }
        
        const aspects = [];
        for (const tp of FAST) {
            const tDegCurr = tropCurr[tp]; if (tDegCurr === undefined) continue;
            const tDegPrev = tropPrev ? tropPrev[tp] : undefined;
            const tDegNext = tropNext ? tropNext[tp] : undefined;
            
            for (const np of TARGETS) {
                const nDeg = natalTropical[np]; if (nDeg === undefined) continue;
                
                const sCurr = sep(tDegCurr, nDeg);
                for (const asp of ASPECTS) {
                    const orb = Math.abs(sCurr - asp.angle);
                    if (orb <= ORB) {
                        // Calculate peak hour
                        let peakHour = null;
                        
                        const getDiff = (tDeg) => {
                            if (tDeg === undefined) return undefined;
                            let diff = (tDeg - nDeg - asp.angle) % 360;
                            while (diff < -180) diff += 360;
                            while (diff > 180) diff -= 360;
                            return diff;
                        };
                        
                        const diffCurr = getDiff(tDegCurr);
                        const diffPrev = getDiff(tDegPrev);
                        const diffNext = getDiff(tDegNext);
                        
                        // Check crossings
                        let offset = 3;
                        if (window.moment && timezone) {
                            try { offset = window.moment.tz(d.toISOString().split('T')[0] + " 12:00", "YYYY-MM-DD HH:mm", timezone).utcOffset() / 60; }
                            catch (e) {}
                        } else {
                            offset = -new Date().getTimezoneOffset() / 60;
                        }
                        if (diffPrev !== undefined && diffPrev * diffCurr <= 0 && diffCurr !== diffPrev) {
                            const f = -diffPrev / (diffCurr - diffPrev);
                            if (f >= 0.5 && f <= 1) {
                                const utcPeak = f * 24 - 12;
                                peakHour = Math.round(((utcPeak + offset + 24) % 24) * 100) / 100;
                            }
                        }
                        if (peakHour === null && diffNext !== undefined && diffCurr * diffNext <= 0 && diffNext !== diffCurr) {
                            const f = -diffCurr / (diffNext - diffCurr);
                            if (f >= 0 && f <= 0.5) {
                                const utcPeak = 12 + f * 24;
                                peakHour = Math.round(((utcPeak + offset + 24) % 24) * 100) / 100;
                            }
                        }
                        
                        aspects.push({
                            transit: tp,
                            natal: np,
                            aspect: asp.name,
                            harmony: asp.harmony,
                            orb: Math.round(orb * 100) / 100,
                            peakHour
                        });
                    }
                }
            }
        }
        out.push({ date: d.toISOString().split('T')[0], offset: i, aspects });
    }
    return out;
}

// Helper funkcija 38 dienu tranzītu kalendāram (-7/+30)
function buildLunarCalendar(lat, lon, birthNakIdx) {
    const calendar = [];
    // UTC pusdienlaiks — izvairies no setHours (lokālā TZ) radītas nobīdes
    const todayDateStr = new Date().toISOString().split('T')[0];
    const today = new Date(todayDateStr + 'T12:00:00Z');
    
    // Tara Bala (Dienas Intensitātes) definīcijas
    const taraTypes = [
        { name: "Fokuss (Janma)", type: "neutral", symbol: "🔥" },      // 0
        { name: "Plūsma (Sampat)", type: "good", symbol: "🌿" },         // 1
        { name: "Šķēršļi (Vipat)", type: "bad", symbol: "⚡️" },          // 2
        { name: "Miers (Kshema)", type: "good", symbol: "🌿" },          // 3
        { name: "Pretestība (Pratyak)", type: "bad", symbol: "⚡️" },     // 4
        { name: "Sniegums (Sadhaka)", type: "good", symbol: "🌿" }, // 5
        { name: "Lūzums / Krīze (Naidhana)", type: "critical", symbol: "❗️" }, // 6
        { name: "Harmonija (Mitra)", type: "good", symbol: "🌿" },       // 7
        { name: "Atbalsts (Ati Mitra)", type: "good", symbol: "🌿" }     // 8
    ];
    
    for (let i = -7; i <= 30; i++) {
        // UTC milisekundes, lai izvairītos no DST un TZ nobīdēm
        const loopMs = today.getTime() + i * 86400000;
        const loopDate = new Date(loopMs);
        
        let jd = getJulianDay(loopDate.toISOString());
        let astro = getAstronomicalData(jd, lat, lon);
        let nakData = getNakshatraData(astro.sidereal["Meness"]);
        
        // Tara Bala matemātika
        let distance = (nakData.index - birthNakIdx + 27) % 27;
        let tIdx = distance % 9; 
        
        // 1. cikls (fizioloģiskais) = spēcīgākais 3x, 3. cikls (vide) = vājākais 1x
        let cycleMultiplier = 4 - (Math.floor(distance / 9) + 1); 
        
        let taraData = taraTypes[tIdx];
        let panchangData = calculatePanchangSummary(astro.sidereal["Saule"], astro.sidereal["Meness"], loopDate, taraData.type);
        
        calendar.push({
            date: loopDate.toISOString().split('T')[0],
            offset: i,
            nakshatra: nakData.nakshatra,
            purushartha: nakData.purushartha,
            taraState: taraData.name,
            taraSymbol: taraData.symbol.repeat(cycleMultiplier),
            taraClass: taraData.type,
            intensity: cycleMultiplier,
            panchang: panchangData
        });
    }
    return calendar;
}

// Helper funkcija 4. līmeņa tiešajiem nakšatru tranzītiem stundu robežās
function buildNakshatraTransits(lat, lon, birthNakIdx) {
    const results = [];
    const nowMs = new Date().getTime();
    
    let currentNakIdx = -1;
    let currentRecord = null;
    
    const stepMs = 15 * 60 * 1000; // 15 minūtes starp aprēķiniem precīzām robežām
    const startMs = nowMs - (48 * 3600 * 1000); // 48 stundas pagātnē
    const endMs = nowMs + (8 * 24 * 3600 * 1000);   // 8 dienas nākotnē (lai nosegtu 7 dienu konsensu)
    
    for (let tMs = startMs; tMs <= endMs; tMs += stepMs) {
        let dtStr = new Date(tMs).toISOString();
        let jd = getJulianDay(dtStr);
        let astro = getAstronomicalData(jd, lat, lon);
        let nakData = getNakshatraData(astro.sidereal["Meness"]);
        
        if (nakData.index !== currentNakIdx) {
            if (currentRecord) {
                currentRecord.endMs = tMs;
                results.push(currentRecord);
            }
            currentNakIdx = nakData.index;
            let tStatus = calculateTaraBalaStatus(birthNakIdx, nakData.index);
            let distance = (nakData.index - birthNakIdx + 27) % 27;
            let cycleMultiplier = 4 - (Math.floor(distance / 9) + 1); 
            
            currentRecord = {
                startMs: tMs, 
                endMs: null,
                nakData: nakData,
                taraBala: tStatus,
                intensity: cycleMultiplier
            };
        }
    }
    if (currentRecord) {
         currentRecord.endMs = endMs + stepMs;
         results.push(currentRecord);
    }
    return results;
}

// ---------------------------------------------
// Vizuālie "Super Notikumu" slāņi 120 gadu asij
// ---------------------------------------------
function calculateSuperEvents(birthTimeMs, siderealPositions, allDashas) {
    const events = [];
    
    // 1. Sade Sati
    const moonDeg = siderealPositions["Meness"];
    const moonSign = Math.floor(moonDeg / 30);
    let saturnDeg = siderealPositions["Saturns"];
    
    let ssStartDeg = (moonSign * 30 - 30 + 360) % 360;
    let distToStart1 = (ssStartDeg - saturnDeg + 360) % 360;
    
    let yearsToFirstSS = distToStart1 / 12.2212;
    
    for (let i = 0; i < 5; i++) {
        let startAge = yearsToFirstSS + (i * 29.457);
        let endAge = startAge + (90 / 12.2212); // ~7.36 gadi
        
        // Pārklājam, ja dzimšanas brīdī jau bija Sade Sati
        if (saturnDeg >= ssStartDeg || distToStart1 > 270) {
            // Mēs jau sākām no nākamā ssStartDeg? 
            // Ja distance ir tuvu 360, varbūt mēs esam iekšā. 
            // Bet grafiski izrēķināt 5 stiklus no dzimšanas ir droši un vienkārši, un varam pamēģināt atskaitīt vienu ciklu atpakaļ vizuālajam sākumam:
            let pastStartAge = yearsToFirstSS - 29.457;
            let pastEndAge = pastStartAge + (90 / 12.2212);
            if (pastEndAge > 0 && i === 0) {
                events.push({
                    type: "sade_sati",
                    name: "Sade Sati: Lielā revīzija",
                    startAge: Math.max(0, pastStartAge),
                    endAge: Math.min(120, pastEndAge),
                    startMs: birthTimeMs + (Math.max(0, pastStartAge) * 365.25 * 86400000),
                    endMs: birthTimeMs + (Math.min(120, pastEndAge) * 365.25 * 86400000),
                    desc: "Stingra disciplīna, pazemība un atteikšanās no ego. Ideāls laiks garīgai praksei un darbam ar sevi."
                });
            }
        }
        
        if (startAge < 120 && endAge > 0) {
            events.push({
                type: "sade_sati",
                name: "Sade Sati: Lielā revīzija",
                startAge: Math.max(0, startAge),
                endAge: Math.min(120, endAge),
                startMs: birthTimeMs + (Math.max(0, startAge) * 365.25 * 86400000),
                endMs: birthTimeMs + (Math.min(120, endAge) * 365.25 * 86400000),
                desc: "Stingra disciplīna, pazemība un atteikšanās no ego. Ideāls laiks garīgai praksei un darbam ar sevi."
            });
        }
    }

    // 2. Jupiter Return (Guru Return)
    for (let i = 1; i <= 10; i++) {
        let retAge = i * 11.86;
        if (retAge < 120) {
            events.push({
                type: "guru_return",
                name: "Jupitera atgriešanās: Iespēju restarts",
                startAge: retAge - 0.5,
                endAge: retAge + 0.5,
                startMs: birthTimeMs + ((retAge - 0.5) * 365.25 * 86400000),
                endMs: birthTimeMs + ((retAge + 0.5) * 365.25 * 86400000),
                desc: "Sāc jaunus projektus, mācies, paplašini redzesloku. Liktenis šobrīd ir tavā pusē."
            });
        }
    }

    // 3. Ganda-Anta
    allDashas.forEach((dasha, idx) => {
        if (idx < allDashas.length - 1) {
            let nextDasha = allDashas[idx+1];
            if ((dasha.lord === "Rahu" && nextDasha.lord === "Jupiters") ||
                (dasha.lord === "Venera" && nextDasha.lord === "Saule")) {
                
                let transTimeMs = nextDasha.start.getTime();
                let transAge = (transTimeMs - birthTimeMs) / (365.25 * 86400000);
                
                events.push({
                    type: "ganda_anta",
                    name: "Ganda-Anta: Dzīves metamorfoze",
                    startAge: transAge - 0.5, 
                    endAge: transAge + 0.5,   
                    startMs: transTimeMs - (180 * 86400000),
                    endMs: transTimeMs + (180 * 86400000),
                    desc: `Pāreja no ${dasha.lord} uz ${nextDasha.lord}. Vecās personības beigas un pilnīgi jauna cikla dzimšana. Neko neforsē, vēro.`
                });
            }
        }
    });

    // 4. Bhrigu Chakra
    const highlightYears = [
        { age: 36, title: "Likteņa fokuss: 1. Māja (Personības restarts)", icon: "🎯" },
        { age: 48, title: "Likteņa fokuss: 1. Māja (Dzīves jēgas restarts)", icon: "🌟" },
        { age: 60, title: "Likteņa fokuss: 1. Māja (Meistara līmenis)", icon: "👑" },
    ];
    
    highlightYears.forEach(y => {
        if (y.age < 120) {
            events.push({
                type: "bhrigu",
                name: y.title,
                icon: y.icon,
                startAge: y.age,
                endAge: y.age + 1,
                startMs: birthTimeMs + (y.age * 365.25 * 86400000),
                endMs: birthTimeMs + ((y.age + 1) * 365.25 * 86400000),
                desc: "Bhrigu Chakra fokuss. Šis gads prasa pilnīgu fokusu un enerģiju."
            });
        }
    });

    return events;
}
