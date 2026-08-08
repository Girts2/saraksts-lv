// Valsts kods → IANA laika josla (izmanto kā tūlītēju fallback bez API)
const COUNTRY_TZ = {
    'lv': 'Europe/Riga',
    'lt': 'Europe/Vilnius',
    'ee': 'Europe/Tallinn',
    'fi': 'Europe/Helsinki',
    'se': 'Europe/Stockholm',
    'no': 'Europe/Oslo',
    'dk': 'Europe/Copenhagen',
    'de': 'Europe/Berlin',
    'pl': 'Europe/Warsaw',
    'ru': 'Europe/Moscow',
    'by': 'Europe/Minsk',
    'ua': 'Europe/Kyiv',
    'gb': 'Europe/London',
    'fr': 'Europe/Paris',
    'es': 'Europe/Madrid',
    'it': 'Europe/Rome',
    'nl': 'Europe/Amsterdam',
    'be': 'Europe/Brussels',
    'at': 'Europe/Vienna',
    'ch': 'Europe/Zurich',
    'cz': 'Europe/Prague',
    'sk': 'Europe/Bratislava',
    'hu': 'Europe/Budapest',
    'ro': 'Europe/Bucharest',
    'bg': 'Europe/Sofia',
    'gr': 'Europe/Athens',
    'tr': 'Europe/Istanbul',
    'us': 'America/New_York',
    'ca': 'America/Toronto',
    'au': 'Australia/Sydney',
    'jp': 'Asia/Tokyo',
    'cn': 'Asia/Shanghai',
    'in': 'Asia/Kolkata',
};

let searchTimeout = null;

export function setupAutocomplete(inputEl, listEl) {
    if (!inputEl || !listEl) return;

    inputEl.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value;

        if (!query || query.length < 3) {
            listEl.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => fetchLocations(query, inputEl, listEl), 500);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== inputEl && e.target !== listEl) {
            listEl.style.display = 'none';
        }
    });
}

export async function fetchLocations(query, inputEl, listEl) {
    try {
        // addressdetails=1 nodrošina country_code lauku atbildē
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&featuretype=city&addressdetails=1`);
        const data = await response.json();

        listEl.innerHTML = '';

        if (data.length === 0) {
            listEl.innerHTML = '<div class="autocomplete-hint">Nekas nav atrasts...</div>';
            listEl.style.display = 'block';
            return;
        }

        data.forEach(place => {
            const el = document.createElement('div');
            el.innerText = place.display_name;
            el.onclick = async () => {
                inputEl.value = place.display_name;
                inputEl.setAttribute('data-lat', place.lat);
                inputEl.setAttribute('data-lon', place.lon);
                listEl.style.display = 'none';
                inputEl.style.borderColor = '#10b981';

                // 1. Tūlītējs fallback — sinhrons, no valsts koda (nav API nepieciešams)
                const countryCode = place.address?.country_code || '';
                const countryTz = COUNTRY_TZ[countryCode];
                if (countryTz) {
                    inputEl.setAttribute('data-timezone', countryTz);
                } else {
                    inputEl.removeAttribute('data-timezone');
                }

                // 2. Precīzāks async pieprasījums — atjaunina ja timeapi.io atbild
                try {
                    const tzRes = await fetch(`https://timeapi.io/api/TimeZone/coordinate?latitude=${place.lat}&longitude=${place.lon}`);
                    const tzData = await tzRes.json();
                    if (tzData && tzData.timeZone) {
                        inputEl.setAttribute('data-timezone', tzData.timeZone);
                    }
                } catch (e) {
                    // timeapi.io neatbild — paliek valsts koda josla (jau iestatīta augstāk)
                    console.warn("Timezone API failed, using country fallback:", countryTz || 'none');
                }
            };
            listEl.appendChild(el);
        });

        listEl.style.display = 'block';
    } catch (err) {
        console.error("Geocoding failed", err);
    }
}
