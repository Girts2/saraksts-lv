// Koplietojama lokālā→UTC konvertācija.
// Izvilkta no main.js, lai to varētu lietot arī 24h sadalījuma dzinējs
// (logic/time_sweep.js) bez cikliskas atkarības uz main.js.
//
// Lieto moment-timezone, ja zināma zona, citādi aptuveno offset (lon/15).
//
// SVARĪGI: atgriež arī `utcDateStr` — UTC KALENDĀRA datumu, kas var atšķirties no
// `dateStr` (piem. Tokija 00:30 → UTC 15:30 IEPRIEKŠĒJĀ dienā). Izsaucējiem, kas
// pārbūvē pilnu UTC brīdi (`${date}T${utcStr}:00Z` Jūlija dienas/efemerīdu aprēķinam),
// JĀLIETO `utcDateStr`, NEVIS oriģinālais `dateStr` — citādi tuvu pusnaktij dzimušiem
// cilvēkiem visa karte (planētas, mājas, BaZi, Dasha) sabojājas par veselu diennakti.
// `dateStr` pats par sevi paliek pareizs LOKĀLĀ dzimšanas datuma marķierim (attēlošanai,
// Maiju/Ķeltu kalendāriem, kas apzināti balstās uz vietējo, ne UTC, kalendāra dienu).
export function localToUtc(dateStr, timeStr, lon, timezone) {
    if (timezone && window.moment) {
        try {
            const m = window.moment.tz(`${dateStr} ${timeStr}`, "YYYY-MM-DD HH:mm", timezone);
            const offset = m.utcOffset() / 60; // lasa PIRMS .utc() izsaukuma, kas maina režīmu
            m.utc();
            return {
                utcStr: m.format("HH:mm"),
                utcDateStr: m.format("YYYY-MM-DD"),
                offset: offset,
                timezoneStr: timezone
            };
        } catch (e) {
            console.warn("Moment TZ failed", e);
        }
    }
    // Fallback: aptuvenais offset
    const offset = Math.round(lon / 15);
    const [h, m] = timeStr.split(':').map(Number);
    let totalMin = h * 60 + m - offset * 60;
    const dayShift = Math.floor(totalMin / 1440);
    totalMin = ((totalMin % 1440) + 1440) % 1440;
    const shifted = new Date(dateStr + 'T00:00:00Z');
    shifted.setUTCDate(shifted.getUTCDate() + dayShift);
    return {
        utcStr: String(Math.floor(totalMin / 60)).padStart(2, '0') + ':' + String(totalMin % 60).padStart(2, '0'),
        utcDateStr: shifted.toISOString().slice(0, 10),
        offset: offset,
        timezoneStr: "Aptuvenais (lon/15)"
    };
}
