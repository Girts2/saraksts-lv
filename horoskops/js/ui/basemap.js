// Pamatkarte bez ārēja API: Natural Earth krasti, valstu robežas un pilsētas no mūsu pašu
// servera (basemap_data.js, ģenerē _Optimizacija/test_tools/horoskops/basemap_build.py).
// Aizstāj CARTO dark_all flīzes — kopš 2026-10 tās bez API atslēgas atdod 200 ar
// "API KEY REQUIRED" ūdenszīmi (konsolē kļūdas nav, tikai bojāts attēls).
//
// Krāsas mērītas (ΔE2000, zeme pret jūru): zem lokatora rūtiņām (0,55 necaurredzamība)
// ≥ 6,0 visos skalas galos; zem radara rūtiņām ≥ 10; bez rūtiņām 12,9.

const OCEAN = '#0b1220';
const LAND = '#2b3a52';
const COAST = '#64748b';
const BORDER = '#94a3b8';

// 1:50M dati tālāk par 9. tālummaiņu kļūst stūraini — dziļāk tuvināt nav jēgas.
export const BASEMAP_MAX_ZOOM = 9;

let _geomPromise = null;

function loadGeometry() {
    return _geomPromise ??= import('./basemap_data.js?v=2')
        .then(m => withWorldCopies(decodeAll(m.default)))
        .catch(e => { _geomPromise = null; throw e; });
}

// Vektoru slāņi (atšķirībā no flīzēm) blakus pasaules kopijās neatkārtojas, bet lokatora smalkais
// režģis seko skatam arī aiz ±180° (piem. TOP sarakstā Fidži, Tonga) — tāpēc kopijas ±360°.
function withWorldCopies(g) {
    const shift = (pts, d) => pts.map(([lat, lon]) => [lat, lon + d]);
    const copies = (items, fn) => [0, -360, 360].flatMap(d => d ? items.map(x => fn(x, d)) : items);
    return {
        land: copies(g.land, (rings, d) => rings.map(r => shift(r, d))),
        coast: copies(g.coast, shift),
        borders: copies(g.borders, shift),
        cities: g.cities
    };
}

// Google polyline (lat, lon secībā), vērtības ×prec.
function decodeLine(str, prec) {
    const pts = [];
    let i = 0, lat = 0, lon = 0;
    while (i < str.length) {
        for (let k = 0; k < 2; k++) {
            let shift = 0, result = 0, b;
            do {
                b = str.charCodeAt(i++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while (b >= 0x20);
            const d = (result & 1) ? ~(result >> 1) : (result >> 1);
            if (k === 0) lat += d; else lon += d;
        }
        pts.push([lat / prec, lon / prec]);
    }
    return pts;
}

// Datu griezuma malas (antimeridiāns, Antarktīdas apakšmala) — aizpildījumam vajag, krastam nē.
function isCut(a, b) {
    if (Math.abs(a[1]) >= 179.99 && Math.abs(b[1]) >= 179.99 && a[1] * b[1] > 0) return true;
    return a[0] <= -89.9 && b[0] <= -89.9;
}

// Eksportēts testam (_Optimizacija/test_tools/horoskops/basemap_test.mjs).
export function decodeAll(d) {
    const land = d.land.map(rings => rings.map(s => decodeLine(s, d.prec)));
    const coast = [];
    for (const rings of land) {
        for (const ring of rings) {
            const first = ring[0], last = ring[ring.length - 1];
            const pts = (first[0] === last[0] && first[1] === last[1]) ? ring : ring.concat([first]);
            let cur = [pts[0]];
            for (let i = 1; i < pts.length; i++) {
                if (isCut(pts[i - 1], pts[i])) {
                    if (cur.length > 1) coast.push(cur);
                    cur = [pts[i]];
                } else {
                    cur.push(pts[i]);
                }
            }
            if (cur.length > 1) coast.push(cur);
        }
    }
    const borders = d.borders.map(s => decodeLine(s, d.prec));
    return { land, coast, borders, cities: d.cities };
}

function injectStyles() {
    if (document.getElementById('basemap-styles')) return;
    const st = document.createElement('style');
    st.id = 'basemap-styles';
    st.textContent = `
        .bm-city { width:0 !important; height:0 !important; }
        .bm-city::before { content:''; position:absolute; left:-2px; top:-2px; width:4px; height:4px; border-radius:50%; background:#94a3b8; }
        .bm-city span { position:absolute; left:5px; top:-8px; white-space:nowrap; font:500 11px/1.3 system-ui,-apple-system,'Segoe UI',sans-serif; color:#cbd5e1; text-shadow:0 0 3px ${OCEAN},0 0 3px ${OCEAN}; }
        .bm-city.bm-capital::before { background:#e2e8f0; }
        .bm-city.bm-capital span { color:#f1f5f9; font-weight:600; }
    `;
    document.head.appendChild(st);
}

// Pilsētu nosaukumi pēc NE min_zoom (dati to paredz tieši tīmekļa karšu tālummaiņai).
// Pārklāšanās: svarīgākie (mazāks min_zoom, galvaspilsētas) aizņem vietu pirmie, pārējie
// tajā mērogā netiek rādīti. Katrs nosaukums stāv tajā pasaules kopijā, kas tuvāk skata centram.
function addCityLabels(map, cities) {
    const group = L.layerGroup().addTo(map);
    const items = cities.map(([name, lat, lon, minZoom, capital]) => {
        const label = document.createElement('span');
        label.textContent = name;
        return {
            minZoom,
            capital,
            lat,
            lon,
            shownLon: lon,
            width: name.length * (capital ? 7 : 6.3) + 6,
            marker: L.marker([lat, lon], {
                pane: 'basemapLabels',
                interactive: false,
                keyboard: false,
                icon: L.divIcon({ className: 'bm-city' + (capital ? ' bm-capital' : ''), html: label, iconSize: null })
            })
        };
    }).sort((a, b) => a.minZoom - b.minZoom || b.capital - a.capital);

    const update = () => {
        const z = map.getZoom();
        const centerLon = map.getCenter().lng;
        const taken = [];
        for (const it of items) {
            let show = it.minZoom <= z;
            if (show) {
                const lon = it.lon + 360 * Math.round((centerLon - it.lon) / 360);
                if (lon !== it.shownLon) {
                    it.shownLon = lon;
                    it.marker.setLatLng([it.lat, lon]);
                }
                const p = map.project([it.lat, lon], z);
                const box = [p.x - 4, p.y - 9, p.x + it.width, p.y + 9];
                show = !taken.some(t => box[0] < t[2] && box[2] > t[0] && box[1] < t[3] && box[3] > t[1]);
                if (show) taken.push(box);
            }
            if (show !== group.hasLayer(it.marker)) {
                if (show) group.addLayer(it.marker); else group.removeLayer(it.marker);
            }
        }
    };
    map.on('moveend', update); // moveend nāk arī pēc tālummaiņas
    update();
}

// Pievieno pamatkarti Leaflet kartei. Dati ielādējas vienreiz (kešots modulis), tāpēc kartes
// pārbūve (map.remove + jauna L.map) tos vairs nelejupielādē.
export function addBasemap(map) {
    injectStyles();
    map.getContainer().style.background = OCEAN;
    // Zeme zem rūtiņām (overlayPane 400); krasti, robežas un nosaukumi virs tām — citādi smalkajā
    // režģī (rūtiņas pārklāj visu logu ar 0,55 necaurredzamību) robežas nav saskatāmas.
    // Augšējie slāņi peli neķer, tāpēc rūtiņu hover strādā kā agrāk.
    for (const [name, z] of [['basemap', 250], ['basemapLines', 420], ['basemapLabels', 450]]) {
        if (map.getPane(name)) continue;
        const pane = map.createPane(name);
        pane.style.zIndex = z;
        pane.style.pointerEvents = 'none';
    }
    map.attributionControl?.addAttribution('<a href="https://www.naturalearthdata.com/" target="_blank" rel="noopener">Natural Earth</a>');

    let alive = true;
    map.once('unload', () => { alive = false; });

    return loadGeometry().then(g => {
        if (!alive) return;
        // Noklusētais canvas padding (0,1): lielāks = audekls ×4 laukumā (retina iPhone atmiņa).
        const fill = { renderer: L.canvas({ pane: 'basemap' }), pane: 'basemap', interactive: false };
        const lines = { renderer: L.canvas({ pane: 'basemapLines' }), pane: 'basemapLines', interactive: false };
        L.polygon(g.land, { ...fill, stroke: false, fillColor: LAND, fillOpacity: 1 }).addTo(map);
        L.polyline(g.coast, { ...lines, color: COAST, weight: 0.9, opacity: 0.9 }).addTo(map);
        L.polyline(g.borders, { ...lines, color: BORDER, weight: 0.8, opacity: 0.6, dashArray: '3 3' }).addTo(map);
        addCityLabels(map, g.cities);
    }).catch(e => console.warn('Pamatkarte netika ielādēta:', e));
}
