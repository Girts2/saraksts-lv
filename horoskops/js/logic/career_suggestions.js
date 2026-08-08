// ── IETEICAMĀS PROFESIJAS — O*NET Work Styles + Big Five + RIASEC ────────────
// Datu avoti (11 faili tiek ielādēti runtime):
//   scales_reference.csv                              → WI, DR, OI skalas min/max robežas
//   content_model_reference.csv                       → Work Styles elementu ID kopa (validācija)
//   work_styles.csv                                   → WI + DR vērtības katrai profesijai
//   interests.csv                                     → RIASEC OI vērtības (Analyst avots)
//   career_interest_types.csv                         → RIASEC OI vērtības (ML/Expert avots, 2026)
//   specific_interest_areas.csv                       → Detalizētas interešu jomas (OI) katrai profesijai
//   specific_interest_areas_to_career_interest_types.csv → Kartēšana: specifiskā joma → RIASEC tips
//   task_statements.csv                               → Core uzdevumu apraksti katrai profesijai
//   occupation_data.csv                               → nosaukumi un jomas
//   job_zones.csv                                     → Job Zone katrai profesijai
//   job_zone_reference.csv                            → Job Zone apraksti un sliekšņi

// ── Work Styles → Big Five kartēšana (pēc Element ID) ────────────────────────
const ELEM_TO_DIM = {
    '1.D.1.a': 'O',  // Innovation
    '1.D.1.c': 'O',  // Intellectual Curiosity
    '1.D.1.d': 'O',  // Tolerance for Ambiguity
    '1.D.1.f': 'O',  // Adaptability
    '1.D.1.b': 'C',  // Achievement Orientation
    '1.D.3.b': 'C',  // Attention to Detail
    '1.D.3.c': 'C',  // Dependability
    '1.D.1.h': 'C',  // Perseverance
    '1.D.3.d': 'C',  // Integrity
    '1.D.3.a': 'C',  // Cautiousness
    '1.D.1.i': 'E',  // Leadership Orientation
    '1.D.2.f': 'E',  // Social Orientation
    '1.D.1.g': 'E',  // Self-Confidence
    '1.D.1.e': 'E',  // Initiative
    '1.D.2.d': 'A',  // Cooperation
    '1.D.2.c': 'A',  // Empathy
    '1.D.2.a': 'A',  // Humility
    '1.D.2.b': 'A',  // Sincerity
    '1.D.4.a': 'Ns', // Stress Tolerance
    '1.D.4.b': 'Ns', // Self-Control
    '1.D.2.e': 'Ns', // Optimism
};

// ── Interests → RIASEC kartēšana (pēc Element ID) ────────────────────────────
const RIASEC_ELEMS = {
    '1.B.1.a': 'R',  // Realistic
    '1.B.1.b': 'I',  // Investigative
    '1.B.1.c': 'A',  // Artistic
    '1.B.1.d': 'S',  // Social
    '1.B.1.e': 'E',  // Enterprising
    '1.B.1.f': 'C',  // Conventional
};

// ── Kešs visiem 11 failiem ────────────────────────────────────────────────────
let _scalesCache    = null;  // scales_reference.csv
let _contentCache   = null;  // content_model_reference.csv
let _profilesCache  = null;  // work_styles.csv (DR-svērtie Big Five profili)
let _interestsCache = null;  // interests.csv + career_interest_types.csv + specific_interest_areas.csv
let _tasksCache     = null;  // task_statements.csv (top uzdevumi)
let _titlesCache    = null;  // occupation_data.csv
let _zonesCache     = null;  // job_zones.csv
let _zoneRefCache   = null;  // job_zone_reference.csv
let _profileStatsCache = null;  // profesiju Big Five populācijas vid/sd (z-score salāgošanai)
let _interestStatsCache = null; // profesiju RIASEC populācijas vidējais pa burtiem (kosinusa centrēšanai)

// ── Lietotāju Big Five populācijas statistika (empīriski mērīta: 4259 personas,
// 35 gadi, fiksēts 12:00 Rīga). Profesiju "Big Five" patiesībā ir O*NET Work
// Importance (cik SVARĪGA iezīme darbam → vidēji ~66-84), lietotāja = cik DAUDZ
// iezīmes piemīt (~42). Šīs ir divas dažādas skalas; bez z-score salāgošanas
// Eiklīda fitScore vienmēr izvēlas zemāko prasību profesijas (pavāri, ēdināšana),
// jo tikai tās atrodas tuvu ~42 lietotāju mākonim.
const USER_BF_STATS = {
    O:  { mean: 41.4, sd: 13.3 },
    C:  { mean: 42.8, sd: 14.1 },
    E:  { mean: 43.6, sd: 15.9 },
    A:  { mean: 43.9, sd: 11.8 },
    Ns: { mean: 56.7, sd:  9.3 },
};

// ── 1. scales_reference.csv → skalas robežas ─────────────────────────────────
// Kešo Promise (nevis rezultātu), lai paralēli izsaukumi dalītu vienu fetch.
async function loadScales() {
    return _scalesCache ??= fetch(new URL('../../onet/scales_reference.csv', import.meta.url).href)
        .then(r => r.text())
        .then(text => {
            const scales = {};
            for (const line of text.split('\n').slice(1)) {
                const cols = line.split('\t');
                if (cols.length < 4) continue;
                const id  = cols[0].trim();
                const min = parseFloat(cols[2]);
                const max = parseFloat(cols[3]);
                if (id && !isNaN(min) && !isNaN(max)) scales[id] = { min, max };
            }
            return scales;
        });
}

// ── 2. content_model_reference.csv → Work Styles lapas elementu ID kopa ──────
async function loadContentModel() {
    if (_contentCache) return _contentCache;
    const csvUrl = new URL('../../onet/content_model_reference.csv', import.meta.url).href;
    const text = await fetch(csvUrl).then(r => r.text());
    const workStyleIds = new Set();
    for (const line of text.split('\n').slice(1)) {
        const id = line.split('\t')[0]?.trim();
        if (id?.startsWith('1.D.') && id.split('.').length === 4)
            workStyleIds.add(id);
    }
    _contentCache = workStyleIds;
    return workStyleIds;
}

// ── 3. job_zone_reference.csv → Job Zone apraksti (referenču tabula) ────────
async function loadJobZoneReference() {
    if (_zoneRefCache) return _zoneRefCache;
    const csvUrl = new URL('../../onet/job_zone_reference.csv', import.meta.url).href;
    const text = await fetch(csvUrl).then(r => r.text());
    const zones = {};
    for (const line of text.split('\n').slice(1)) {
        const cols = line.split('\t');
        if (cols.length < 2) continue;
        const id   = parseInt(cols[0]);
        const name = cols[1]?.trim();
        if (!isNaN(id) && name) zones[id] = name;
    }
    _zoneRefCache = zones;
    return zones;
}

// ── 4. job_zones.csv → Job Zone katrai profesijai ────────────────────────────
async function loadJobZones() {
    if (_zonesCache) return _zonesCache;
    const csvUrl = new URL('../../onet/job_zones.csv', import.meta.url).href;
    const text = await fetch(csvUrl).then(r => r.text());
    const zones = {};
    for (const line of text.split('\n').slice(1)) {
        const cols = line.split('\t');
        if (cols.length < 2) continue;
        const code = cols[0].trim();
        const zone = parseInt(cols[1]);
        if (code && code.endsWith('.00') && !isNaN(zone)) zones[code] = zone;
    }
    _zonesCache = zones;
    return zones;
}

// ── 5. work_styles.csv → DR-svērtie Big Five profili ─────────────────────────
// DR (Distinctiveness Rank 0-10) svēr katras iezīmes nozīmīgumu profesijai.
// Vienkāršām profesijām DR ir zems → profils kļūst neitrāls (~50).
// Sarežģītām profesijām DR ir augsts specifiskās dimensijās → izcils profils.
// Svērtais vidējais: Σ(wiPct × dr) / Σ(dr)
async function loadOnetProfiles() {
    if (_profilesCache) return _profilesCache;

    const [scales, workStyleIds] = await Promise.all([loadScales(), loadContentModel()]);

    const wi = scales['WI'];
    if (!wi) throw new Error('WI skala nav atrasta scales_reference.csv');
    const wiRange = wi.max - wi.min;
    const wiToPct = v => Math.max(0, Math.min(100, ((v - wi.min) / wiRange) * 100));

    const csvUrl = new URL('../../onet/work_styles.csv', import.meta.url).href;
    const text   = await fetch(csvUrl).then(r => r.text());
    const lines  = text.split('\n');

    // Viena pase: vāc gan WI, gan DR katram (code, elemId)
    // raw[code][elemId] = { wi: number|null, dr: number|null }
    const raw = {};
    for (let i = 1; i < lines.length; i++) {
        const cols = lines[i].split('\t');
        if (cols.length < 5) continue;
        const code    = cols[0].trim();
        const elemId  = cols[1].trim();
        const scaleId = cols[3].trim();
        const val     = parseFloat(cols[4]);

        if (isNaN(val))             continue;
        if (!code.endsWith('.00'))  continue;
        if (!workStyleIds.has(elemId)) continue;
        if (!ELEM_TO_DIM[elemId])   continue;

        if (!raw[code])          raw[code] = {};
        if (!raw[code][elemId])  raw[code][elemId] = { wi: null, dr: null };

        if (scaleId === 'WI') raw[code][elemId].wi = val;
        if (scaleId === 'DR') raw[code][elemId].dr = val;
    }

    // Aprēķina DR-svērto vidējo pēc dimensijām
    const profiles = {};
    for (const [code, elems] of Object.entries(raw)) {
        const acc = { O:[],C:[],E:[],A:[],Ns:[] };
        for (const [elemId, { wi: wiVal, dr }] of Object.entries(elems)) {
            if (wiVal === null) continue;
            const dim    = ELEM_TO_DIM[elemId];
            const pct    = wiToPct(wiVal);
            const weight = (dr !== null && dr > 0) ? dr : 1;
            acc[dim].push({ pct, weight });
        }
        profiles[code] = {};
        for (const dim of ['O','C','E','A','Ns']) {
            const items = acc[dim];
            if (!items.length) { profiles[code][dim] = 50; continue; }
            const sumW  = items.reduce((s, x) => s + x.weight, 0);
            const sumWP = items.reduce((s, x) => s + x.pct * x.weight, 0);
            profiles[code][dim] = Math.round(sumWP / sumW);
        }
    }

    // Profesiju populācijas statistika pa asīm (z-score salāgošanai fitScore)
    const codes = Object.keys(profiles);
    const stats = {};
    for (const dim of ['O','C','E','A','Ns']) {
        const xs = codes.map(c => profiles[c][dim]);
        const mean = xs.reduce((s, x) => s + x, 0) / xs.length;
        const sd = Math.sqrt(xs.reduce((s, x) => s + (x - mean) ** 2, 0) / xs.length) || 1;
        stats[dim] = { mean, sd };
    }
    _profileStatsCache = stats;

    _profilesCache = profiles;
    return profiles;
}

// ── 6. interests.csv + career_interest_types.csv + specific_interest_areas → RIASEC
// Trīs neatkarīgi OI avoti tiek vidēļoti kopā katrai RIASEC dimensijai.
// Specifiskās jomas (1.B.3.x) tiek pārvērstas RIASEC tipos caur kartēšanas failu;
// dažas jomas (piemēram, Animal Service) kartējas uz vairākiem RIASEC tipiem vienlaicīgi.
async function loadInterests() {
    if (_interestsCache) return _interestsCache;

    const scales = await loadScales();
    const oi = scales['OI'];
    if (!oi) throw new Error('OI skala nav atrasta scales_reference.csv');
    const oiRange = oi.max - oi.min;
    const oiToPct = v => Math.max(0, Math.min(100, ((v - oi.min) / oiRange) * 100));

    // raw[code][letter] = [pct, ...] (no visiem avotiem)
    const raw = {};
    const push = (code, letter, pct) => {
        if (!raw[code]) raw[code] = {};
        if (!raw[code][letter]) raw[code][letter] = [];
        raw[code][letter].push(pct);
    };

    // Parsē failu ar tiešiem RIASEC elementiem (1.B.1.x)
    const addRiasecFile = (text) => {
        for (const line of text.split('\n').slice(1)) {
            const cols    = line.split('\t');
            if (cols.length < 5) continue;
            const code    = cols[0].trim();
            const elemId  = cols[1].trim();
            const scaleId = cols[3].trim();
            const val     = parseFloat(cols[4]);
            if (isNaN(val) || !code.endsWith('.00') || scaleId !== 'OI') continue;
            const letter = RIASEC_ELEMS[elemId];
            if (!letter) continue;
            push(code, letter, oiToPct(val));
        }
    };

    // Parsē specifiskās jomas (1.B.3.x) caur kartēšanas tabulu.
    // Katrs RIASEC tips var saņemt vairākas specifiskās jomas (piemēram, R ← 10 jomas).
    // Lai šis avots nesvertu vairāk par pārējiem diviem, vispirms vidēļo iekšpusē,
    // tad pievieno vienu vērtību galvenajam akumulatoram.
    const addSpecificAreasFile = (text, mapping) => {
        const srcRaw = {};
        for (const line of text.split('\n').slice(1)) {
            const cols    = line.split('\t');
            if (cols.length < 5) continue;
            const code    = cols[0].trim();
            const elemId  = cols[1].trim();
            const scaleId = cols[3].trim();
            const val     = parseFloat(cols[4]);
            if (isNaN(val) || !code.endsWith('.00') || scaleId !== 'OI') continue;
            const letters = mapping[elemId];
            if (!letters?.length) continue;
            const pct = oiToPct(val);
            if (!srcRaw[code]) srcRaw[code] = {};
            for (const letter of letters) {
                if (!srcRaw[code][letter]) srcRaw[code][letter] = [];
                srcRaw[code][letter].push(pct);
            }
        }
        for (const [code, dims] of Object.entries(srcRaw)) {
            for (const [letter, vals] of Object.entries(dims)) {
                push(code, letter, vals.reduce((s, v) => s + v, 0) / vals.length);
            }
        }
    };

    // Ielādē visus 4 avotus paralēli
    const [text1, text2, text3, textMap] = await Promise.all([
        fetch(new URL('../../onet/interests.csv', import.meta.url).href).then(r => r.text()),
        fetch(new URL('../../onet/career_interest_types.csv', import.meta.url).href).then(r => r.text()),
        fetch(new URL('../../onet/specific_interest_areas.csv', import.meta.url).href).then(r => r.text()),
        fetch(new URL('../../onet/specific_interest_areas_to_career_interest_types.csv', import.meta.url).href).then(r => r.text()),
    ]);

    // Izveido kartēšanu: specificAreaId → [RIASEC letters]
    const mapping = {};
    for (const line of textMap.split('\n').slice(1)) {
        const cols    = line.split('\t');
        if (cols.length < 3) continue;
        const areaId  = cols[0].trim();
        const riasecId = cols[2].trim();
        const letter  = RIASEC_ELEMS[riasecId];
        if (!areaId || !letter) continue;
        if (!mapping[areaId]) mapping[areaId] = [];
        if (!mapping[areaId].includes(letter)) mapping[areaId].push(letter);
    }

    addRiasecFile(text1);
    addRiasecFile(text2);
    addSpecificAreasFile(text3, mapping);

    const profiles = {};
    for (const [code, dims] of Object.entries(raw)) {
        profiles[code] = {};
        for (const letter of ['R','I','A','S','E','C']) {
            const vals = dims[letter] ?? [];
            profiles[code][letter] = vals.length
                ? Math.round(vals.reduce((s, v) => s + v, 0) / vals.length)
                : 50;
        }
    }

    // Profesiju RIASEC populācijas vidējais pa burtiem (kosinusa centrēšanai)
    const codes = Object.keys(profiles);
    const rstats = {};
    for (const letter of ['R','I','A','S','E','C']) {
        const sum = codes.reduce((s, c) => s + profiles[c][letter], 0);
        rstats[letter] = codes.length ? sum / codes.length : 50;
    }
    _interestStatsCache = rstats;

    _interestsCache = profiles;
    return profiles;
}

// ── 7. task_statements.csv → top 2 Core uzdevumi katrai profesijai ────────────
async function loadTopTasks() {
    if (_tasksCache) return _tasksCache;
    const csvUrl = new URL('../../onet/task_statements.csv', import.meta.url).href;
    const text   = await fetch(csvUrl).then(r => r.text());
    const tasks  = {};
    for (const line of text.split('\n').slice(1)) {
        const cols = line.split('\t');
        if (cols.length < 4) continue;
        const code = cols[0].trim();
        const task = cols[2].trim();
        const type = cols[3].trim();
        if (!code.endsWith('.00') || type !== 'Core' || !task) continue;
        if (!tasks[code]) tasks[code] = [];
        if (tasks[code].length < 2) tasks[code].push(task);
    }
    _tasksCache = tasks;
    return tasks;
}

// ── 8. occupation_data.csv → nosaukumi un jomas ───────────────────────────────
async function loadOccupations() {
    if (_titlesCache) return _titlesCache;
    const csvUrl = new URL('../../onet/occupation_data.csv?v=2', import.meta.url).href;
    const text   = await fetch(csvUrl).then(r => r.text());
    const result = {};
    for (const line of text.split('\n').slice(1)) {
        const cols  = line.split(';');
        if (cols.length < 2) continue;
        const code  = cols[0].trim();
        const title = cols[1].trim();
        const area  = cols[cols.length - 1].trim() || 'Cits';
        const desc  = cols.slice(2, -1).join(';').trim();   // latviskais apraksts (Description); slice ļauj ';' tekstā
        if (code && title) result[code] = { title, area, desc };
    }
    _titlesCache = result;
    return result;
}

// ── Big Five → RIASEC (Larson et al. 2002 meta-analīze) ──────────────────────
function bigFiveToRiasec(O, C, E, A) {
    const clamp = v => Math.max(0, Math.min(100, Math.round(v)));
    return {
        R: clamp(C * 0.35 + (100 - O) * 0.35 + (100 - A) * 0.20 + (100 - E) * 0.10),
        I: clamp(O * 0.55 + C * 0.30 + (100 - E) * 0.15),
        A: clamp(O * 0.60 + (100 - C) * 0.30 + E * 0.10),
        S: clamp(E * 0.40 + A * 0.45 + (100 - C) * 0.15),
        E: clamp(E * 0.50 + C * 0.30 + (100 - A) * 0.20),
        C: clamp(C * 0.50 + (100 - O) * 0.35 + (100 - E) * 0.15),
    };
}

// Lietotāju RIASEC populācijas vidējais (kosinusa centrēšanai). bigFiveToRiasec
// ir lineārs, tāpēc populācijas vidējais = funkcija no Big Five vidējiem.
const USER_RIASEC_MEAN = bigFiveToRiasec(
    USER_BF_STATS.O.mean, USER_BF_STATS.C.mean, USER_BF_STATS.E.mean, USER_BF_STATS.A.mean,
);

// ── Work Styles: z-score salāgota Eiklīda distance → Fit Score (0-100) ───────
// Abas puses standartizē pret SAVU populāciju (lietotāji ~42, profesijas ~66-84),
// tāpēc "augsts lietotājs" sastopas ar "augstu prasību profesiju", nevis ar
// zemāko prasību profesiju. Tas novērš centroīda/zemāko-prasību magnētu.
// MAX_Z_DIST = katra no 5 asīm līdz ~3 sd nobīdei → sqrt(5·9) ≈ 6.7.
const MAX_Z_DIST = Math.sqrt(5 * 9);

function fitScore(user, prof, profStats) {
    const distSq = ['O','C','E','A','Ns'].reduce((sum, d) => {
        const uz = (user[d] - USER_BF_STATS[d].mean) / USER_BF_STATS[d].sd;
        const pz = (prof[d] - profStats[d].mean) / profStats[d].sd;
        return sum + (uz - pz) ** 2;
    }, 0);
    // Atgriež nenoapaļotu (0-100) — noapaļošanu atstāj attēlošanai, lai ranžējums
    // pa precīzo vērtību nenovestu pie patvaļīgas secības starp sasaistītām profesijām.
    return Math.max(0, 100 - (Math.sqrt(distSq) / MAX_Z_DIST) * 100);
}

// ── RIASEC: centrēta kosinusa līdzība → Fit Score (0-100) ────────────────────
// Holland modelī svarīgs ir interešu profila "siluets" (relatīvā struktūra),
// nevis absolūtās vērtības. Tā kā visi RIASEC komponenti ir pozitīvi (0-100),
// parastais kosinuss vienmēr ir ~0.9+ un nediskriminē. Tāpēc abas puses CENTRĒ
// pret savas populācijas vidējo — tad kosinuss mēra, vai lietotājs novirzās no
// vidējā tajā pašā virzienā kā profesija. Centrētais kosinuss ∈ [-1,1] →
// pārveido uz [0,100] (100=tāds pats siluets, 50=neatkarīgs, 0=pretējs).
function riasecCosineSim(userRiasec, profRiasec, profStats) {
    const letters = ['R','I','A','S','E','C'];
    let dot = 0, magU = 0, magP = 0;
    for (const l of letters) {
        const u = (userRiasec[l] ?? 50) - USER_RIASEC_MEAN[l];
        const p = (profRiasec[l] ?? 50) - profStats[l];
        dot  += u * p;
        magU += u * u;
        magP += p * p;
    }
    if (!magU || !magP) return 50;
    const cos = dot / (Math.sqrt(magU) * Math.sqrt(magP));
    return (cos + 1) / 2 * 100;   // nenoapaļots (0-100); noapaļo tikai attēlošanai
}

// ── Galvenā eksportētā funkcija ───────────────────────────────────────────────
export async function calculateCareerSuggestions(personality) {
    if (!personality?.length) return { user: null, zones: {} };

    const ocean = personality.find(cat => cat.id === 'ocean');
    if (!ocean) return { user: null, zones: {} };

    const bf = {};
    for (const t of ocean.traits) bf[t.id] = t.pct;

    const user = {
        O:  bf.openness      ?? 50,
        C:  bf.conscient     ?? 50,
        E:  bf.extraversion  ?? 50,
        A:  bf.agreeableness ?? 50,
        Ns: 100 - (bf.neuroticism ?? 50),
    };
    const userRiasec = bigFiveToRiasec(user.O, user.C, user.E, user.A);

    let profiles, interests, occupations, jobZones, topTasks;
    try {
        [profiles, interests, occupations, jobZones, topTasks] = await Promise.all([
            loadOnetProfiles(),
            loadInterests(),
            loadOccupations(),
            loadJobZones(),
            loadTopTasks(),
        ]);
    } catch (err) {
        console.error('[career_suggestions] ielādes kļūda:', err);
        return { user: null, zones: {} };
    }

    const byZone = {};

    for (const [code, prof] of Object.entries(profiles)) {
        const occ  = occupations[code];
        if (!occ) continue;

        const zone = jobZones[code];
        if (!zone || zone < 2) continue;

        const wsScore = fitScore(user, prof, _profileStatsCache);
        const riasec  = interests[code];
        const rsScore = riasec ? riasecCosineSim(userRiasec, riasec, _interestStatsCache) : wsScore;
        const scoreExact = 0.6 * wsScore + 0.4 * rsScore;

        const item = {
            id:    code,
            lv:    occ.title,
            area:  occ.area,
            desc:  occ.desc || '',
            onet:  code,
            zone,
            score:      Math.round(scoreExact),   // attēlošanai
            scoreExact,                            // ranžēšanai (precīzs, bez sasaistes)
            wsScore: Math.round(wsScore),
            rsScore: Math.round(rsScore),
            tasks:  topTasks[code] ?? [],
            bf:     { O: prof.O, C: prof.C, E: prof.E, A: prof.A, Ns: prof.Ns },
            riasec: riasec ? { ...riasec } : null,
        };

        if (!byZone[zone]) byZone[zone] = [];
        byZone[zone].push(item);
    }

    for (const z of Object.keys(byZone)) {
        byZone[z].sort((a, b) => b.scoreExact - a.scoreExact);
        byZone[z] = byZone[z].slice(0, 20);
    }

    return {
        user:  { bf: user, riasec: userRiasec },
        zones: byZone,
    };
}
