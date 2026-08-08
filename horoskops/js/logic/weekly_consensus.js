// ─────────────────────────────────────────────────────────────────────────────
// Nedēļas multi-sistēmu KONSENSA dzinējs (skelets)
// ─────────────────────────────────────────────────────────────────────────────
// Sk. atmiņu feat-nedelas-horoskops-konsenss. Princips: notikums "apstiprināts"
// TIKAI tad, ja ≥2 NEATKARĪGAS sistēmas saskan (precizitāte pār recall).
// 3 neatkarīgi balsotāji:
//   (1) Mēness ass — Tara Bala (+ vēlāk tithi, Rietumu Mēness tranzīti)  [DAĻĒJI]
//   (2) Rietumu ne-Mēness tranzīti uz natālo                            [DRĪZUMĀ]
//   (3) BaZi Liu Ri — dienas pīlārs vs day-master                       [GATAVS]
// Katrs balsotājs izsakās kopējā ontoloģijā: { valence, domains, strength }.

import { liuRiSignalForJDN } from './bazi_liu_ri.js?v=2';
import { baziDayJDN } from '../bazi.js?v=12';
import { getSunriseSunset } from '../vedic_kp.js?v=10';
import { aspectFalloff } from './aspect_utils.js?v=1';

// ── Balsotājs 1 (daļējs): Tara Bala no lunar_calendar dienas ieraksta ─────────
// lunar_calendar dienai ir taraClass ∈ {good, bad, critical, neutral} + intensity 1..3.
const TARA_VALENCE = { good: 'supportive', bad: 'challenging', critical: 'challenging', neutral: 'neutral' };

export function taraBalaToOntology(calDay) {
    if (!calDay) return null;
    const valence = TARA_VALENCE[calDay.taraClass] || 'neutral';
    const strength = Math.min(0.9, 0.3 + 0.2 * (calDay.intensity || 1));
    return {
        group: 'moon',
        valence,
        domains: ['vispārējs'],            // Tara Bala ir nespecifiska (vispārēja dienas kvalitāte)
        strength: Math.round(strength * 100) / 100,
        label: calDay.taraState || '—',
        drivers: [`TaraBala:${calDay.taraState || '?'}`],
    };
}

// ── Balsotājs 2: Rietumu ātrie tranzīti uz natālo (profile.western_transit_week) ─
const PLANET_DOMAIN = {
    Saule: 'darbs', Merkurs: 'komunikācija', Venera: 'attiecības', Marss: 'risks', Jupiters: 'nauda', Saturns: 'darbs',
};
const ORB_W = 1.5;

export function westernTransitToOntology(dayData) {
    if (!dayData || !dayData.aspects || !dayData.aspects.length) return null;
    let score = 0, strength = 0;
    const domains = new Set();
    for (const a of dayData.aspects) {
        const tight = aspectFalloff(a.orb || 0, ORB_W);          // 0..1 (jo šaurāks orbs, jo stiprāk; gluds kritiens)
        let h = a.harmony;
        if (h === 0) {                                          // konjunkcija → nosaka planētas daba
            h = (a.transit === 'Marss' || a.natal === 'Marss' || a.natal === 'Saturns') ? -0.5
              : (a.transit === 'Venera' || a.natal === 'Venera' || a.natal === 'Jupiters') ? 0.5 : 0;
        }
        score += h * tight;
        strength = Math.max(strength, 0.4 + 0.4 * tight);
        domains.add(PLANET_DOMAIN[a.transit]); domains.add(PLANET_DOMAIN[a.natal]);
    }
    const valence = score > 0.15 ? 'supportive' : score < -0.15 ? 'challenging' : 'neutral';
    const top = dayData.aspects.reduce((b, a) => (b && b.orb <= a.orb ? b : a), null); // šaurākais orbs
    return {
        group: 'western',
        valence,
        domains: [...domains].filter(Boolean),
        strength: Math.round(strength * 100) / 100,
        top,
        count: dayData.aspects.length,
        drivers: dayData.aspects.map(a => `${a.transit}→${a.natal} ${a.aspect}`),
        aspects: dayData.aspects,
    };
}

// ── Konsenss: ≥2 dzīvi balsotāji vienā valencē → apstiprināts ─────────────────
export function combineVoters(voters) {
    const live = voters.filter(Boolean);
    const dir = {};
    for (const v of live) if (v.valence && v.valence !== 'neutral') dir[v.valence] = (dir[v.valence] || 0) + 1;
    let direction = null, agree = 0;
    for (const [d, n] of Object.entries(dir)) if (n > agree) { agree = n; direction = d; }
    const confirmed = agree >= 2;
    const domains = confirmed
        ? [...new Set(live.filter(v => v.valence === direction).flatMap(v => v.domains))]
        : [...new Set(live.flatMap(v => v.domains))];
    return { confirmed, agree, live: live.length, direction: confirmed ? direction : null, domains };
}

// ── Datumu palīgs (UTC pusdienlaiks — saskaņā ar buildLunarCalendar) ──────────
function defaultDates(days) {
    const t = new Date(); t.setUTCHours(12, 0, 0, 0);
    const out = [];
    for (let i = 0; i < days; i++) out.push(new Date(t.getTime() + i * 86400000).toISOString().split('T')[0]);
    return out;
}

function getHourlyTaraBala(profile, dateStr) {
    const hourly = [];
    const transits = profile?.nakshatra_transits_48h || [];
    const tz = profile?.current_loc?.timezone ?? profile?.birth_info?.timezone ?? "Europe/Riga";
    let offset = 3;
    if (window.moment) {
        try { offset = window.moment.tz(dateStr + " 12:00", "YYYY-MM-DD HH:mm", tz).utcOffset() / 60; }
        catch (e) {}
    } else {
        offset = -new Date().getTimezoneOffset() / 60;
    }
    
    for (let h = 0; h < 24; h++) {
        // Calculate the absolute millisecond timestamp of the midpoint of local hour h:
        const localHourMs = new Date(`${dateStr}T00:00:00Z`).getTime() - offset * 3600000 + (h + 0.5) * 3600000;
        
        const match = transits.find(t => localHourMs >= t.startMs && localHourMs <= t.endMs);
        let val = 50; // default neutral
        if (match && match.taraBala) {
            const status = match.taraBala.statusFrame;
            const title = match.taraBala.title || "";
            if (status === 'red') {
                if (title.includes("Lūzums")) {
                    val = 0; // Naidhana
                } else if (title.includes("Pretestība") || title.includes("Šķēršļi")) {
                    val = 20; // Pratyak/Vipat
                } else {
                    val = 40; // Janma
                }
            } else {
                if (title.includes("Miers") || title.includes("Harmonija") || title.includes("Atbalsts")) {
                    val = 100;
                } else {
                    val = 80; // Sampat / Sadhaka
                }
            }
        }
        hourly.push(val);
    }
    return hourly;
}

function calculatePlanetaryHours(lat, lon, dateStr, ssCurrent, timezone = null) {
    const dateObj = new Date(dateStr + "T12:00:00Z");
    let offset = 3;
    if (window.moment && timezone) {
        try { offset = window.moment.tz(dateStr + " 12:00", "YYYY-MM-DD HH:mm", timezone).utcOffset() / 60; }
        catch (e) {}
    } else {
        offset = -new Date().getTimezoneOffset() / 60;
    }
    const startOfDayMs = new Date(dateStr + "T00:00:00Z").getTime() - offset * 3600000; // 00:00 local time
    
    const ssPrev = getSunriseSunset(lat, lon, new Date(dateObj.getTime() - 86400000));
    const ssNext = getSunriseSunset(lat, lon, new Date(dateObj.getTime() + 86400000));
    
    const CHALDEAN = ['Sun', 'Venus', 'Mercury', 'Moon', 'Saturn', 'Jupiter', 'Mars'];
    const WEEKDAY_TO_CHALDEAN_IDX = { 0: 0, 1: 3, 2: 6, 3: 2, 4: 5, 5: 1, 6: 4 };
    
    const srMs = ssCurrent.sunrise.getTime();
    const ssMs = ssCurrent.sunset.getTime();
    
    const hours = [];
    for (let h = 0; h < 24; h++) {
        const timeMs = startOfDayMs + (h + 0.5) * 3600000; // Use midpoint of local hour h
        let planetKey = 'Sun';
        
        if (timeMs < srMs) {
            // Previous day night hours
            const prevDayOfWeek = ssPrev.sunrise.getUTCDay();
            const startIdx = WEEKDAY_TO_CHALDEAN_IDX[prevDayOfWeek];
            const nightDuration = srMs - ssPrev.sunset.getTime();
            const nightHourLen = nightDuration / 12;
            const elapsed = timeMs - ssPrev.sunset.getTime();
            const hrIdx = Math.max(0, Math.min(11, Math.floor(elapsed / nightHourLen)));
            planetKey = CHALDEAN[(startIdx + 12 + hrIdx) % 7];
        } else if (timeMs >= srMs && timeMs < ssMs) {
            // Current day day hours
            const currDayOfWeek = dateObj.getUTCDay();
            const startIdx = WEEKDAY_TO_CHALDEAN_IDX[currDayOfWeek];
            const dayDuration = ssMs - srMs;
            const dayHourLen = dayDuration / 12;
            const elapsed = timeMs - srMs;
            const hrIdx = Math.max(0, Math.min(11, Math.floor(elapsed / dayHourLen)));
            planetKey = CHALDEAN[(startIdx + hrIdx) % 7];
        } else {
            // Current day night hours
            const currDayOfWeek = dateObj.getUTCDay();
            const startIdx = WEEKDAY_TO_CHALDEAN_IDX[currDayOfWeek];
            const nightDuration = ssNext.sunrise.getTime() - ssMs;
            const nightHourLen = nightDuration / 12;
            const elapsed = timeMs - ssMs;
            const hrIdx = Math.max(0, Math.min(11, Math.floor(elapsed / nightHourLen)));
            planetKey = CHALDEAN[(startIdx + 12 + hrIdx) % 7];
        }
        hours.push(planetKey);
    }
    return hours;
}

// ── Galvenais: 7 (vai N) dienu konsensa virkne ───────────────────────────────
export function buildWeeklyConsensus(profile, days = 7) {
    // lunar_calendar ir profile AUGŠLĪMENĪ (ne profile.vedic) un būvēts beznosacījuma
    // (arī ar nezināmu laiku — tad natālā nakšatra ir aptuvena, bet signāls derīgs).
    const cal = (profile?.lunar_calendar || []).filter(d => d.offset >= 0 && d.offset < days);
    const dates = cal.length ? cal.map(d => d.date) : defaultDates(days);
    const wtw = profile?.western_transit_week || [];

    return dates.map((dateStr, i) => {
        const [y, m, dd] = dateStr.split('-').map(Number);
        const jdn = baziDayJDN(y, m, dd, 12);                 // visa diena → pusdienlaiks
        const calDay = cal.find(d => d.date === dateStr) || null;

        const moon    = taraBalaToOntology(calDay);                                   // balsotājs 1
        const bazi    = liuRiSignalForJDN(profile, jdn);                              // balsotājs 3
        const western = westernTransitToOntology(wtw.find(w => w.date === dateStr));  // balsotājs 2

        const consensus = combineVoters([moon, bazi, western]);
        const hourly = getHourlyTaraBala(profile, dateStr);

        // Saullēkta/saulrieta un stundu skalu aprēķini Abhijit Muhurtai un Rahu Kaal
        const lat = profile?.current_loc?.lat ?? profile?.birth_info?.lat ?? 56.9496;
        const lon = profile?.current_loc?.lon ?? profile?.birth_info?.lon ?? 24.1052;
        const dateObj = new Date(dateStr + "T12:00:00Z");
        const ss = getSunriseSunset(lat, lon, dateObj);
        
        const startOfDayMs = new Date(dateStr + 'T00:00:00Z').getTime();
        const sunriseHour = (ss.sunrise.getTime() - startOfDayMs) / 3600000;
        const sunsetHour = (ss.sunset.getTime() - startOfDayMs) / 3600000;
        const dayLength = sunsetHour - sunriseHour;
        const muhLength = dayLength / 15;
        
        const dayOfWeek = dateObj.getUTCDay();
        
        const tz = profile?.current_loc?.timezone ?? profile?.birth_info?.timezone ?? "Europe/Riga";
        let offset = 3;
        if (window.moment) {
            try { offset = window.moment.tz(dateStr + " 12:00", "YYYY-MM-DD HH:mm", tz).utcOffset() / 60; }
            catch (e) {}
        } else {
            offset = -new Date().getTimezoneOffset() / 60;
        }
        const abhijitIntensity = (dayOfWeek === 3) ? 'weak' : 'strong'; // Trešdienās Abhijit Muhurta ir vājāka
        const abhijit = {
            start: Math.round((sunriseHour + 7 * muhLength + offset) * 100) / 100,
            end: Math.round((sunriseHour + 8 * muhLength + offset) * 100) / 100,
            intensity: abhijitIntensity
        };
        
        const RAHU_KAAL_PARTS = [7, 1, 6, 4, 5, 3, 2];
        const partIdx = RAHU_KAAL_PARTS[dayOfWeek];
        // Rahu Kaal ir spēcīgāks Otrdienās (Marsa diena), Sestdienās (Saturna diena) un Svētdienās (Saules diena)
        const rahuIntensity = (dayOfWeek === 2 || dayOfWeek === 6) ? 'high' : (dayOfWeek === 0 ? 'medium-high' : 'normal');
        const rahuKaal = {
            start: Math.round((sunriseHour + partIdx * (dayLength / 8) + offset) * 100) / 100,
            end: Math.round((sunriseHour + (partIdx + 1) * (dayLength / 8) + offset) * 100) / 100,
            intensity: rahuIntensity
        };

        const brahma = {
            start: Math.round((sunriseHour - 1.6 + offset) * 100) / 100,
            end: Math.round((sunriseHour - 0.8 + offset) * 100) / 100
        };
        const tzPlanetary = profile?.current_loc?.timezone ?? profile?.birth_info?.timezone ?? "Europe/Riga";
        const planetaryHours = calculatePlanetaryHours(lat, lon, dateStr, ss, tzPlanetary);

        return { 
            date: dateStr, 
            jdn, 
            offset: calDay?.offset ?? i, 
            moon, 
            bazi, 
            western, 
            consensus, 
            hourly, 
            abhijit, 
            rahuKaal,
            sunriseHour: Math.round((sunriseHour + offset) * 100) / 100,
            sunsetHour: Math.round((sunsetHour + offset) * 100) / 100,
            brahma,
            planetaryHours
        };
    });
}

export function buildDayConsensus(profile, dateStr) {
    const [y, m, dd] = dateStr.split('-').map(Number);
    const jdn = baziDayJDN(y, m, dd, 12);
    const calDay = (profile?.lunar_calendar || []).find(d => d.date === dateStr) || null;

    const moon    = taraBalaToOntology(calDay);
    const bazi    = liuRiSignalForJDN(profile, jdn);
    const wtw     = profile?.western_transit_week || [];
    const western = westernTransitToOntology(wtw.find(w => w.date === dateStr));

    const consensus = combineVoters([moon, bazi, western]);
    const hourly = getHourlyTaraBala(profile, dateStr);

    const lat = profile?.current_loc?.lat ?? profile?.birth_info?.lat ?? 56.9496;
    const lon = profile?.current_loc?.lon ?? profile?.birth_info?.lon ?? 24.1052;
    const dateObj = new Date(dateStr + "T12:00:00Z");
    const ss = getSunriseSunset(lat, lon, dateObj);
    
    const startOfDayMs = new Date(dateStr + 'T00:00:00Z').getTime();
    const sunriseHour = (ss.sunrise.getTime() - startOfDayMs) / 3600000;
    const sunsetHour = (ss.sunset.getTime() - startOfDayMs) / 3600000;
    const dayLength = sunsetHour - sunriseHour;
    const muhLength = dayLength / 15;
    
    const dayOfWeek = dateObj.getUTCDay();
    
    const tz = profile?.current_loc?.timezone ?? profile?.birth_info?.timezone ?? "Europe/Riga";
    let offset = 3;
    if (window.moment) {
        try { offset = window.moment.tz(dateStr + " 12:00", "YYYY-MM-DD HH:mm", tz).utcOffset() / 60; }
        catch (e) {}
    } else {
        offset = -new Date().getTimezoneOffset() / 60;
    }
    
    const abhijitIntensity = (dayOfWeek === 3) ? 'weak' : 'strong';
    const abhijit = {
        start: Math.round((sunriseHour + 7 * muhLength + offset) * 100) / 100,
        end: Math.round((sunriseHour + 8 * muhLength + offset) * 100) / 100,
        intensity: abhijitIntensity
    };
    
    const RAHU_KAAL_PARTS = [7, 1, 6, 4, 5, 3, 2];
    const partIdx = RAHU_KAAL_PARTS[dayOfWeek];
    const rahuIntensity = (dayOfWeek === 2 || dayOfWeek === 6) ? 'high' : (dayOfWeek === 0 ? 'medium-high' : 'normal');
    const rahuKaal = {
        start: Math.round((sunriseHour + partIdx * (dayLength / 8) + offset) * 100) / 100,
        end: Math.round((sunriseHour + (partIdx + 1) * (dayLength / 8) + offset) * 100) / 100,
        intensity: rahuIntensity
    };

    const brahma = {
        start: Math.round((sunriseHour - 1.6 + offset) * 100) / 100,
        end: Math.round((sunriseHour - 0.8 + offset) * 100) / 100
    };
    
    const planetaryHours = calculatePlanetaryHours(lat, lon, dateStr, ss, tz);

    return { 
        date: dateStr, 
        jdn, 
        moon, 
        bazi, 
        western, 
        consensus, 
        hourly, 
        abhijit, 
        rahuKaal,
        sunriseHour: Math.round((sunriseHour + offset) * 100) / 100,
        sunsetHour: Math.round((sunsetHour + offset) * 100) / 100,
        brahma,
        planetaryHours
    };
}
