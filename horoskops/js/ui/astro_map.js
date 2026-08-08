import { calculateDailyMuhurtas } from '../vedic_kp.js?v=10';

let mapObj = null;
let mapGridLayers = [];

// Helper funkcija astroloģiskās kartes inicializācijai
export function initAstroMap(baseDateStr) {
    // Sagatavojam slider noklusējuma laiku - momentu tagad, vai izvēlēto bāzes datumu
    const nowTime = new Date();
    const baseDate = new Date(baseDateStr + "T00:00:00");
    
    let currentDisplayMs = nowTime.getTime();
    // Ja izvēlētais datums nav šodiena, iestatām to uz pulksten 12:00
    if (baseDate.toDateString() !== nowTime.toDateString()) {
        currentDisplayMs = new Date(baseDateStr + "T12:00:00").getTime();
    }

    const slider = document.getElementById("map-time-slider");
    const timeDisplay = document.getElementById("slider-time-display");
    const datePicker = document.getElementById("map-date-picker");
    
    if (!slider || !timeDisplay || !datePicker) return;

    // Ja bāzes datums nesakrīt ar datePicker vērtību, mēs drošības pēc atjaunojam to
    if (datePicker.value !== baseDateStr) {
        datePicker.value = baseDateStr;
    }
    
    // Aprēķinam minūtes kopš dienas sākuma
    const startOfDay = new Date(currentDisplayMs);
    startOfDay.setHours(0,0,0,0);
    
    let startSliderVal = Math.floor((currentDisplayMs - startOfDay.getTime()) / 60000);
    slider.value = startSliderVal;
    updateSliderDisplay(startSliderVal, startOfDay, timeDisplay);

    if (mapObj) {
        try { mapObj.remove(); } catch(e) {}
        mapObj = null;
    }
    mapGridLayers = [];

    // Konfigurējam Leaflet
    const mapEl = document.getElementById('latvia-map');
    if (!mapEl) return;
    mapObj = L.map('latvia-map').setView([20.0, 0.0], 2);
    
    // Dark matter premium dizains
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CartoDB',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(mapObj);


    // Ģenerējam režģi pāri visai pasaulei
    const minLat = -60.0, maxLat = 80.0, latStep = 4.0;
    const minLon = -180.0, maxLon = 180.0, lonStep = 5.0;
    
    // Šī funkcija atjaunos krāsas vienam poligonam ievadītajā laikā
    function getPolygonColor(lat, lon, targetTimeMs) {
        const muhData = calculateDailyMuhurtas(baseDateStr, lat, lon);
        let polyColor = "rgba(100, 116, 139, 0.15)"; // Default pelēks
        let polyTitle = "Neitrāls laiks";
        let borderColor = "transparent";
        
        // Pārbauda Rahu Kaal
        let isRahu = muhData.rahu.find(r => targetTimeMs >= r.start && targetTimeMs <= r.end);
        if (isRahu) {
            return { bg: "rgba(239, 68, 68, 0.25)", border: "rgba(239, 68, 68, 0.5)", title: "🔴 TUKŠAIS LAIKS (Rahu Kaal)\nBloķēta enerģija, šķēršļi." };
        }
        
        // Pārbauda Muhurtu
        let isMuh = muhData.muhurtas.find(m => targetTimeMs >= m.start && targetTimeMs <= m.end);
        if (isMuh) {
            // Pārvērš sešpadsmitnieku krāsā rgba
            let hex = isMuh.color.replace("#", "");
            let r = parseInt(hex.substring(0,2), 16), g = parseInt(hex.substring(2,4), 16), b = parseInt(hex.substring(4,6), 16);
            polyColor = `rgba(${r}, ${g}, ${b}, 0.25)`;
            borderColor = `rgba(${r}, ${g}, ${b}, 0.5)`;
            
            let q = isMuh.qual;
            let iQual = q === "ZELTS" ? "⭐ ZELTS" : (q.includes("Destruktīva") ? "🔴 Destruktīva" : (q.includes("Bīstama") ? "🟠 Bīstama" : "🟢 Labvēlīga"));
            if (isMuh.title.includes("Zemspriegums") || isMuh.title.includes("Rutīna") || q === "Neitrāla") iQual = "⚪ Neitrāla";
            if (q.includes("Karsta")) iQual = "🔥 Karstā";
            
            polyTitle = `${iQual}\n${isMuh.title}\nMuhurta: ${isMuh.name}`;
        }
        
        return { bg: polyColor, border: borderColor, title: polyTitle };
    }

    const baseDateNoTime = baseDateStr; // saglabājam referenci eventam
    
    for (let lat = minLat; lat < maxLat; lat += latStep) {
        for (let lon = minLon; lon < maxLon; lon += lonStep) {
            
            let cLat = lat + latStep/2;
            let cLon = lon + lonStep/2;
            
            // Sākotnējais krāsojums
            let colData = getPolygonColor(cLat, cLon, currentDisplayMs);
            
            let bounds = [[lat, lon], [lat + latStep, lon + lonStep]];
            let rect = L.rectangle(bounds, {
                color: colData.border,
                weight: 0.5,
                fillColor: colData.bg,
                fillOpacity: 1,
                className: 'grid-cell-poly'
            });
            
            rect.bindTooltip(colData.title, { direction: "center", className: "map-tooltip" });
            
            // Saglabājam datus elementā ātrai atjaunošanai
            rect.astroData = { cLat: cLat, cLon: cLon };
            
            rect.addTo(mapObj);
            mapGridLayers.push(rect);
        }
    }
    
    // Slider Event Listener
    slider.oninput = function() {
        let msVal = parseInt(this.value) * 60000;
        let sliderTimeMs = startOfDay.getTime() + msVal;
        
        updateSliderDisplay(this.value, startOfDay, timeDisplay);
        
        // Atjauno visus
        mapGridLayers.forEach(rect => {
            let colData = getPolygonColor(rect.astroData.cLat, rect.astroData.cLon, sliderTimeMs);
            rect.setStyle({ fillColor: colData.bg, color: colData.border });
            rect.setTooltipContent(colData.title);
        });
    };
    
    // Date picker event listener
    datePicker.onchange = function() {
        if (this.value) {
            // Vienkārši izsaucam initAstroMap ar jauno datumu
            initAstroMap(this.value);
        }
    };
}

export function updateSliderDisplay(minutes, startOfDay, displayEl) {
    let actualTimeMs = startOfDay.getTime() + (parseInt(minutes) * 60000);
    let d = new Date(actualTimeMs);
    let hs = d.getHours().toString().padStart(2, '0');
    let ms = d.getMinutes().toString().padStart(2, '0');
    displayEl.innerText = `${hs}:${ms}`;
}
