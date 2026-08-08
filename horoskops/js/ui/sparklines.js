import { getJulianDay, getAstronomicalData, formatDegree } from '../core_astro.js?v=9';
import { getBaziPillars, calculateFiveElementsBalance, calculateTenGods, findBaZiConflicts, getBaZiCombinations } from '../bazi.js?v=12';
import { analyzeMayaMatrix, calculateMayanOracle } from '../maya_toltec.js?v=6';
import { calculateMayanReliabilityFinal } from '../mayan_interpretations.js';
import { calculateDignities } from '../western.js?v=5';
import { determineSect } from '../hellenistic.js?v=3';
import { calculateVedicSaturnReliability } from '../calculations/vedic/saturn_uzticamiba.js';
import { calculateVedicSunIntegrity } from '../calculations/vedic/sun_integritate.js';
import { calculateVedicMoonEarthStability } from '../calculations/vedic/moon_zeme_stabilitate.js';
import { calculateBaZiEarthStability } from '../calculations/bazi/bazi_zeme_stabilitate.js';
import { calculateBaZiOfficerExecution } from '../calculations/bazi/bazi_amatpersona_izpildijums.js';
import { calculateBaZiResourceIntegrity } from '../calculations/bazi/bazi_resurss_integritate.js';
import { calculateWesternSaturnDiscipline } from '../calculations/western/western_saturn_disciplina.js';
import { calculateWesternSunJupiterEthics } from '../calculations/western/western_saule_etika.js';
import { calculateWesternElementsStability } from '../calculations/western/western_noturiba_elementi.js';
import { calculateHellenisticSectSaturn } from '../calculations/hellenistic/hellenistic_sect_saturn.js';
import { calculateHellenisticSpiritIntent } from '../calculations/hellenistic/hellenistic_spirit_intent.js';
import { calculateHellenisticPhasisAction } from '../calculations/hellenistic/hellenistic_phasis_action.js';
import { calculateLots } from '../hellenistic.js?v=3';
import { getNakshatraData } from '../vedic_kp.js?v=10';

export async function drawYearlySparklines(dateStr, timeStr, vScore, bScore, wScore, hScore, mScore, lat, lon, isTimeUnknown = false) {
    const bDate = new Date(dateStr);
    const bYear = bDate.getFullYear();
    const hParts = timeStr.split(':');
    const hour = parseInt(hParts[0] || '12', 10);
    const min = parseInt(hParts[1] || '0', 10);
    let year = bDate.getFullYear();
    let month = bDate.getMonth() + 1;
    let day = bDate.getDate();

    // Loop starts at current date exactNowDt
    const exactNowDt = new Date();
    const todayJd = getJulianDay(exactNowDt.toISOString());
    const exactNowMidnightDt = new Date();
    exactNowMidnightDt.setHours(12, 0, 0, 0);

    let vedic_saturnPts = [];
    let vedic_sunPts = [];
    let vedic_moonPts = [];
    let bazi_summaryPts = [];
    let bazi_earthPts = [];
    let bazi_officerPts = [];
    let bazi_resourcePts = [];
    let western_summaryPts = [];
    let western_saturnPts = [];
    let western_ethicsPts = [];
    let western_elementsPts = [];
    let hellenistic_summaryPts = [];
    let hellenistic_sectPts = [];
    let hellenistic_spiritPts = [];
    let hellenistic_phasisPts = [];
    let mayaPts = [];
    let rawScores = {
        'vedic_saturn': [], 'vedic_sun': [], 'vedic_moon': [],
        'bazi_summary': [], 'bazi_earth': [], 'bazi_officer': [], 'bazi_resource': [],
        'western_summary': [], 'western_saturn': [], 'western_ethics': [], 'western_elements': [],
        'hellenistic_summary': [], 'hellenistic_sect': [], 'hellenistic_spirit': [], 'hellenistic_phasis': [],
        'maya': []
    };

    let getSignName = (deg) => {
        const TROP_SIGNS = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];
        return TROP_SIGNS[Math.floor(deg / 30) % 12];
    };

    // Precalculate day arrays
    for(let i=0; i<365; i++) {
        // Use local time matching the user's browser, just like horoskops.js
        let d = new Date(bYear, 0, i+1, hour, min, 0);
        let m = d.getMonth() + 1;
        let dayOfMonth = d.getDate();
        let isoDate = d.getFullYear() + "-" + String(m).padStart(2,'0') + "-" + String(dayOfMonth).padStart(2,'0');
        let jd = getJulianDay(d.toISOString());
        
        // Astronomiskie dati — vajadzīgi gan BaZi Saules termiņiem, gan astro sistēmām zemāk.
        let astroData = getAstronomicalData(jd, lat, lon);

        // --- 1. BaZi ---
        // Mēneša pīlārs no Saules termiņiem (节气) — konsekventi ar horoskops.js, ne Gregora mēnesis.
        let pillarsObj = getBaziPillars(bYear, m, dayOfMonth, hour, jd, astroData.tropical["Saule"]);
        let pillarsArr = [pillarsObj.Year, pillarsObj.Month, pillarsObj.Day, pillarsObj.Hour];
        let balance = calculateFiveElementsBalance(pillarsArr);
        
        let baziGods = [];
        let countGods = {};
        [pillarsObj.Year.Stem, pillarsObj.Month.Stem, pillarsObj.Hour.Stem].forEach(stem => {
            let g = calculateTenGods(pillarsObj.Daymaster, stem);
            if (g) {
                baziGods.push(g);
                countGods[g] = (countGods[g] || 0) + 1;
            }
        });
        let mainGod = "Nav_noteikts";
        if (Object.keys(countGods).length > 0) {
            mainGod = Object.keys(countGods).reduce((a, b) => countGods[a] > countGods[b] ? a : b);
        }

        let conflicts = findBaZiConflicts([pillarsObj.Year.Branch, pillarsObj.Month.Branch, pillarsObj.Day.Branch, pillarsObj.Hour.Branch]);
        let combos = getBaZiCombinations([pillarsObj.Year.Branch, pillarsObj.Month.Branch, pillarsObj.Day.Branch, pillarsObj.Hour.Branch]);

        let baziParams = {
            earthElementPercent: balance["Zeme"] || 0,
            mainGod: mainGod,
            hiddenGod: "",
            gods: baziGods,
            conflicts: conflicts,
            combinations: combos,
            isDetailedUI: false
        };

        let bResEarth = calculateBaZiEarthStability(baziParams);
        let bResOfficer = calculateBaZiOfficerExecution(baziParams);
        let bResResource = calculateBaZiResourceIntegrity(baziParams);

        let modScore = (combos.length * 10) - (conflicts.length * 15);
        let bSumRaw = Math.floor((bResEarth.score * 0.3) + (bResOfficer.score * 0.4) + (bResResource.score * 0.3));
        let bSum = Math.floor(bSumRaw + modScore);
        if (bSum > 100) bSum = 100;
        if (bSum < 0) bSum = 0;

        bazi_summaryPts.push(`${i},${100 - bSum}`); rawScores['bazi_summary'].push(bSum);
        bazi_earthPts.push(`${i},${100 - bResEarth.score}`); rawScores['bazi_earth'].push(bResEarth.score);
        bazi_officerPts.push(`${i},${100 - bResOfficer.score}`); rawScores['bazi_officer'].push(bResOfficer.score);
        bazi_resourcePts.push(`${i},${100 - bResResource.score}`); rawScores['bazi_resource'].push(bResResource.score); 

        // --- 2. Maya ---
        let mData = analyzeMayaMatrix(isoDate);
        let mSealIdx = mData?.person_data?.sign_idx || 0;
        let mTone = mData?.person_data?.tone || 1;
        let mOracle = calculateMayanOracle(mSealIdx, mTone);
        let mReliability = calculateMayanReliabilityFinal(mSealIdx, mTone, mOracle);
        mayaPts.push(`${i},${100 - mReliability.score}`); rawScores['maya'].push(mReliability.score);

        // --- 3. Astrological Systems (Saturn) --- (astroData jau aprēķināts augšā)
        let saturnTrop = astroData.tropical["Saturns"] || 0;
        let saturnSid = astroData.sidereal["Saturns"] || 0;
        
        let tropSign = formatDegree(saturnTrop).split(" ")[1];
        let sidSign = formatDegree(saturnSid).split(" ")[1];

        // Western
        let tropP = astroData.tropical;
        let wPlanets = {
            "Saule": { sign: getSignName(tropP["Saule"]), longitude: tropP["Saule"] },
            "Meness": { sign: getSignName(tropP["Meness"]), longitude: tropP["Meness"] },
            "Merkurs": { sign: getSignName(tropP["Merkurs"]), longitude: tropP["Merkurs"] },
            "Venera": { sign: getSignName(tropP["Venera"]), longitude: tropP["Venera"] },
            "Marss": { sign: getSignName(tropP["Marss"]), longitude: tropP["Marss"] },
            "Jupiters": { sign: getSignName(tropP["Jupiters"]), longitude: tropP["Jupiters"] },
            "Saturns": { sign: getSignName(tropP["Saturns"]), longitude: tropP["Saturns"] },
            "Neptuns": { sign: getSignName(tropP["Neptuns"]), longitude: tropP["Neptuns"] },
            "Ascendant": { sign: getSignName(tropP["Ascendant"] || 0), longitude: tropP["Ascendant"] || 0 }
        };

        let wParams = { planets: wPlanets, isDetailedUI: false };
        let wSat = calculateWesternSaturnDiscipline(wParams);
        let wEth = calculateWesternSunJupiterEthics(wParams);
        let wEle = calculateWesternElementsStability(wParams);

        let wSumRaw = Math.floor((wSat.score * 0.4) + (wEth.score * 0.3) + (wEle.score * 0.3));
        if (wSumRaw > 100) wSumRaw = 100;
        if (wSumRaw < 0) wSumRaw = 0;

        western_summaryPts.push(`${i},${100 - wSumRaw}`); rawScores['western_summary'].push(wSumRaw);
        western_saturnPts.push(`${i},${100 - wSat.score}`); rawScores['western_saturn'].push(wSat.score);
        western_ethicsPts.push(`${i},${100 - wEth.score}`); rawScores['western_ethics'].push(wEth.score);
        western_elementsPts.push(`${i},${100 - wEle.score}`); rawScores['western_elements'].push(wEle.score);

        // --- Vēdu 3 Algoritmi (Saturns, Saule, Mēness) ---
        let satDeg = astroData.sidereal["Saturns"] || 0;
        let sunDegV = astroData.sidereal["Saule"] || 0;
        let moonDegV = astroData.sidereal["Meness"] || 0;
        let ascDegV = astroData.sidereal["Ascendant"] || 0;
        
        let satSign = Math.floor(satDeg / 30);
        let ascSign = Math.floor(ascDegV / 30);
        let satHouse = ((satSign - ascSign + 12) % 12) + 1;
        let sunSignV = Math.floor(sunDegV / 30);
        let sunHouseV = ((sunSignV - ascSign + 12) % 12) + 1;
        
        let astroTomV = getAstronomicalData(jd + 1, lat, lon);
        let diffTom = astroTomV.sidereal["Saturns"] - satDeg;
        while(diffTom < -180) diffTom += 360;
        while(diffTom > 180) diffTom -= 360;
        let isRetro = diffTom < 0;
        
        let nakDataV = getNakshatraData(satDeg);
        
        let satSparklineParams = {
            satDeg: satDeg,
            satHouse: satHouse,
            isRetro: isRetro,
            sunHouse: sunHouseV,
            nakLord: nakDataV?.lord || "Nezināms",
            isDetailedUI: false
        };
        let satRes = calculateVedicSaturnReliability(satSparklineParams);
        vedic_saturnPts.push(`${i},${100 - satRes.score}`); rawScores['vedic_saturn'].push(satRes.score);

        let isNightBirthV = (sunHouseV >= 1 && sunHouseV <= 6);
        let sunSparklineParams = {
            sunDeg: sunDegV,
            sunHouse: sunHouseV,
            isNightBirth: isNightBirthV,
            isDetailedUI: false
        };
        let sunRes = calculateVedicSunIntegrity(sunSparklineParams);
        vedic_sunPts.push(`${i},${100 - sunRes.score}`); rawScores['vedic_sun'].push(sunRes.score);

        let moonSparklineParams = {
            moonDeg: moonDegV,
            ascDeg: ascDegV,
            allPlanetsSideral: astroData.sidereal,
            isDetailedUI: false
        };
        let moonRes = calculateVedicMoonEarthStability(moonSparklineParams);
        vedic_moonPts.push(`${i},${100 - moonRes.score}`); rawScores['vedic_moon'].push(moonRes.score);

        // Hellenistic - Dynamic "What If Born On This Day"
        let ascDeg = astroData.tropical["Ascendant"] || 0;
        if (isTimeUnknown) {
            ascDeg = astroData.tropical["Saule"] || 0;
        }
        let sunDeg = astroData.tropical["Saule"] || 0;
        let moonDeg = astroData.tropical["Meness"] || 0;
        let sect = determineSect(ascDeg, sunDeg);
        let lots = calculateLots(ascDeg, sunDeg, moonDeg, sect);
        let spiritLotSignName = getSignName(lots.spirit_deg);

        let diffTomSat = astroTomV.sidereal["Saturns"] - satDeg;
        while(diffTomSat < -180) diffTomSat += 360;
        while(diffTomSat > 180) diffTomSat -= 360;
        let isSatRetro = diffTomSat < 0;

        let hParams = {
            sect: sect,
            planets: wPlanets,
            spiritLotSign: spiritLotSignName,
            isSaturnRetro: isSatRetro,
            isDetailedUI: false
        };

        let hSect = calculateHellenisticSectSaturn(hParams);
        let hSpirit = calculateHellenisticSpiritIntent(hParams);
        let hPhasis = calculateHellenisticPhasisAction(hParams);

        let hSumRaw = Math.floor((hSect.score * 0.4) + (hSpirit.score * 0.3) + (hPhasis.score * 0.3));
        if (hSumRaw > 100) hSumRaw = 100;
        if (hSumRaw < 0) hSumRaw = 0;

        hellenistic_summaryPts.push(`${i},${100 - hSumRaw}`); rawScores['hellenistic_summary'].push(hSumRaw);
        hellenistic_sectPts.push(`${i},${100 - hSect.score}`); rawScores['hellenistic_sect'].push(hSect.score);
        hellenistic_spiritPts.push(`${i},${100 - hSpirit.score}`); rawScores['hellenistic_spirit'].push(hSpirit.score);
        hellenistic_phasisPts.push(`${i},${100 - hPhasis.score}`); rawScores['hellenistic_phasis'].push(hPhasis.score);
    }

    const drawLine = (id, pointsStr, color) => {
        let el = document.getElementById(`sparkline-uzticamiba-${id}`);
        if(!el) return;
        
        // Build Month Grid lines
        let gridLines = '';
        const months = ["Jan", "Feb", "Mar", "Apr", "Mai", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dec"];
        const daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        let currentDay = 0;
        
        let labelsHtml = `<div style="display:flex; justify-content:space-between; padding: 2px 4px; font-size:12px; color:#64748b; font-family:sans-serif; background:rgba(0,0,0,0.02);">`;
        
        for(let m=0; m<12; m++) {
            let xPos = currentDay;
            if(m > 0) {
                gridLines += `<line x1="${xPos}" y1="0" x2="${xPos}" y2="100" stroke="rgba(0,0,0,0.08)" stroke-width="1" vector-effect="non-scaling-stroke" />`;
            }
            labelsHtml += `<span style="flex:1; text-align:center;">${months[m]}</span>`;
            currentDay += daysInMonth[m];
        }
        labelsHtml += `</div>`;

        let svg = `<svg width="100%" height="100%" viewBox="0 -5 365 110" preserveAspectRatio="none" style="display:block; position:absolute; top:0; left:0; width:100%; height:100%;">
            ${gridLines}
            <polyline points="${pointsStr}" fill="none" stroke="${color}" stroke-width="2.5" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
            <line x1="0" y1="100" x2="365" y2="100" stroke="rgba(0,0,0,0.1)" stroke-width="1" stroke-dasharray="4" vector-effect="non-scaling-stroke"/>
            <line x1="0" y1="50" x2="365" y2="50" stroke="rgba(0,0,0,0.1)" stroke-width="1" stroke-dasharray="4" vector-effect="non-scaling-stroke"/>
            <line x1="0" y1="0" x2="365" y2="0" stroke="rgba(0,0,0,0.1)" stroke-width="1" stroke-dasharray="4" vector-effect="non-scaling-stroke"/>
        </svg>`;
        
        let hoverOverlay = `
            <div class="hover-overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:10; cursor:crosshair;"></div>
            <div class="hover-line" style="display:none; position:absolute; top:0; bottom:0; width:1px; background:#64748b; pointer-events:none; z-index:5;"></div>
            <div class="hover-tooltip" style="display:none; position:absolute; background:#1e293b; color:#fff; font-size:11px; padding:4px 8px; border-radius:4px; pointer-events:none; white-space:nowrap; transform:translate(-50%, -120%); z-index:20; box-shadow:0 2px 4px rgba(0,0,0,0.2);"></div>
        `;
        
        el.innerHTML = `<div class="sparkline-container" style="flex:1; width:100%; position:relative; min-height:0;">${svg}${hoverOverlay}</div>${labelsHtml}`;
        
        const container = el.querySelector('.sparkline-container');
        const overlay = el.querySelector('.hover-overlay');
        const tooltip = el.querySelector('.hover-tooltip');
        const line = el.querySelector('.hover-line');
        
        if (overlay && rawScores[id]) {
            overlay.addEventListener('mousemove', (e) => {
                const rect = overlay.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const percent = Math.max(0, Math.min(1, x / rect.width));
                const dayIndex = Math.floor(percent * 364);
                
                const d = new Date(bYear, 0, dayIndex + 1);
                const dateText = `${d.getDate()}. ${months[d.getMonth()]}`;
                const val = rawScores[id][dayIndex];
                
                line.style.display = 'block';
                line.style.left = `${percent * 100}%`;
                
                tooltip.style.display = 'block';
                tooltip.style.left = `${percent * 100}%`;
                tooltip.style.top = `${100 - val}%`;
                tooltip.innerHTML = `<b>${dateText}</b>: ${val}%`;
            });
            
            overlay.addEventListener('mouseleave', () => {
                line.style.display = 'none';
                tooltip.style.display = 'none';
            });
        }
    };

    drawLine('vedic_saturn', vedic_saturnPts.join(' '), '#6366f1');
    drawLine('vedic_sun', vedic_sunPts.join(' '), '#f59e0b');
    drawLine('vedic_moon', vedic_moonPts.join(' '), '#10b981');
    drawLine('western_summary', western_summaryPts.join(' '), '#6366f1');
    drawLine('western_saturn', western_saturnPts.join(' '), '#6366f1');
    drawLine('western_ethics', western_ethicsPts.join(' '), '#6366f1');
    drawLine('western_elements', western_elementsPts.join(' '), '#6366f1');
    drawLine('hellenistic_summary', hellenistic_summaryPts.join(' '), '#10b981');
    drawLine('hellenistic_sect', hellenistic_sectPts.join(' '), '#10b981');
    drawLine('hellenistic_spirit', hellenistic_spiritPts.join(' '), '#10b981');
    drawLine('hellenistic_phasis', hellenistic_phasisPts.join(' '), '#10b981');
    drawLine('bazi_summary', bazi_summaryPts.join(' '), '#f59e0b');
    drawLine('bazi_earth', bazi_earthPts.join(' '), '#f59e0b');
    drawLine('bazi_officer', bazi_officerPts.join(' '), '#f59e0b');
    drawLine('bazi_resource', bazi_resourcePts.join(' '), '#f59e0b');
    drawLine('maya', mayaPts.join(' '), '#10b981');
}
