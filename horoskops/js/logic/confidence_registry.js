// ── SADAĻU TICAMĪBAS REĢISTRS ────────────────────────────────────────────────
// Lai segtu visas cilnes bez simtiem bespoke ierakstu — sadaļas marķē ar
// ARHETIPU (draiveru profilu). descriptorFor(key, opts) atgriež descriptoru,
// ko padot computeConfidence (section_confidence.js).
//
// Sweep ceļi (profile.timeSweep): vedic.ascSignDist, western.ascSignDist,
// vedic.planets.<P>.{signDist,houseDist,nakDist}, western.planets.<P>.{...},
// baziHour.branchDist. P ∈ Saule,Meness,Marss,Merkurs,Jupiters,Venera,Saturns,Rahu,Ketu.
//
// `limits` (2026-07-09 audits): katram arhetipam godīgs apraksts, KAS ierobežo
// sadaļas precizitāti un KO lietotājs vēl var izdarīt (ja var). Rāda nozīmītes
// izvērstajā blokā vienmēr — arī tad, kad "uzlabot vairs nav ko" (tad tas
// paskaidro griestus). opts.limits ļauj sadaļai tekstu pārrakstīt.

const sign  = (sys, p, w = 1) => ({ path: `${sys}.planets.${p}.signDist`,  label: `${p} zīme`,  weight: w });
const house = (sys, p, w = 1) => ({ path: `${sys}.planets.${p}.houseDist`, label: `${p} māja`,  weight: w });

// Arhetipi → descriptori. opts.planet / opts.system / opts.limits pielāgo dinamiskos.
const ARCHETYPES = {
    // Pilnībā Ascendanta-vadīts (Persona / pirmais iespaids) — bez laika nenosakāms
    'ascendant': (o = {}) => ({
        drivers: [{ path: 'vedic.ascSignDist', label: 'Ascendanta zīme', weight: 1 }],
        requires: ['time', 'birthplace'],
        limits: o.limits || 'Ascendants mainās ik ~2 stundās, tāpēc izšķirošs ir precīzs dzimšanas laiks. Dokumentos laiks bieži noapaļots līdz 15–30 min — ja iespējams, precizē pēc slimnīcas ieraksta vai piederīgo atmiņām. Bez laika šī sadaļa paliek orientējoša, un to nekompensē citi dati.',
    }),
    // Mēness (zīme gandrīz fiksēta, māja laika-atkarīga)
    'moon': (o = {}) => ({
        drivers: [sign('vedic', 'Meness', 1), house('vedic', 'Meness', 1)],
        requires: ['time', 'birthplace'],
        limits: o.limits || 'Mēness zīmi datums nosaka gandrīz vienmēr (zīme mainās ik ~2,5 dienās), bet mājas pozīcijai vajadzīgs precīzs laiks. Ar zināmu laiku atlikušo nenoteiktību rada laika noapaļojums dokumentos (±15–30 min) un mājas sistēmas izvēle robežgadījumos.',
    }),
    // Planēta: zīme (nosakāma) + māja (laika-atkarīga). opts.planet, opts.system
    'mixed-sign-house': ({ planet = 'Saule', system = 'vedic', limits = null } = {}) => ({
        drivers: [sign(system, planet, 1), house(system, planet, 1)],
        requires: ['time', 'birthplace'],
        limits: limits || 'Planētas zīmi nosaka datums; mājas pozīcijai vajadzīgs precīzs laiks (±30 min var pārbīdīt māju). Vairāk par precīzu laiku šo sadaļu neuzlabo nekas — atlikušos griestus nosaka pati metode.',
    }),
    // Tikai planētas zīme (datuma-fiksēta, augsta nosakāmība)
    'planet-sign': ({ planet = 'Saule', system = 'vedic', limits = null } = {}) => ({
        drivers: [sign(system, planet, 1)],
        requires: ['birthplace'],
        limits: limits || 'Pilnībā nosaka dzimšanas datums — papildu dati rezultātu nemaina. Vienīgais izņēmums: ja planēta zīmi maina tieši dzimšanas dienā, izšķir precīzs laiks.',
    }),
    // Tikai planētas māja (pilnībā laika-atkarīga)
    'planet-house': ({ planet = 'Saule', system = 'vedic', limits = null } = {}) => ({
        drivers: [house(system, planet, 1)],
        requires: ['time', 'birthplace'],
        limits: limits || 'Pilnībā laika-atkarīga sadaļa: bez dzimšanas laika māja nav nosakāma. Ar laiku precizitāti ierobežo tā pieraksta precizitāte (±30 min) un mājas sistēmas izvēle.',
    }),
    // Bazi stundas stabs — bez laika vienmērīgs (nenosakāms)
    'bazi-hour': (o = {}) => ({
        drivers: [{ path: 'baziHour.branchDist', label: 'Bazi stundas stabs', weight: 1 }],
        requires: ['time', 'birthplace'],
        limits: o.limits || 'BaZi stundas stabs mainās ik 2 stundās — pat ±1 h kļūda to pārbīda. Ja laiks zināms aptuveni, pārbaudi, vai tas neiekrīt divu stabu robežā (nepāra stundas: 01:00, 03:00, …). Pārējie trīs stabi (gads/mēnesis/diena) no laika nav atkarīgi.',
    }),
    // Datuma-fiksēts (Maija, Bazi gads/mēnesis/diena, Saules zīme) — laiks neietekmē
    'date-fixed': (o = {}) => ({
        drivers: [],            // nav laika-atkarīgu draiveru → D=1
        requires: ['birthplace'],
        limits: o.limits || 'Rezultātu pilnībā nosaka dzimšanas datums — ne precīzāks laiks, ne vieta to vairs neuzlabos. Griestus nosaka pati metode: kalendāra cikli apraksta tipiskas tendences, ne individuālu diagnozi.',
    }),
    // Daudzsistēmu konstrukts (līderība, uzticamība, komanda) — konverģence
    'multi-system': ({ crossSources = 'reliability', limits = null } = {}) => ({
        drivers: [],
        crossSources,
        limits: limits || 'Ticamību šeit ceļ neatkarīgu sistēmu saskaņa, ne papildu dati. Ja sistēmas nesaskan, tas apraksta pretrunīgu (daudzšķautņainu) profilu — tā ir informācija, nevis novēršama kļūda. Precīzs dzimšanas laiks nedaudz stiprina laika-atkarīgos balsotājus (mājas, stundas stabs).',
        requires: ['birthplace'],
    }),
    // Tranzīti / pašreizējais periods — atkarīgs no šībrīža datuma (nosakāms),
    // bet dzimšanas mājas (ja iesaistītas) laika-atkarīgas
    'transit': (o = {}) => ({
        drivers: [],
        requires: ['birthplace', 'current'],
        limits: o.limits || 'Balstās šodienas debesu stāvoklī — rezultāts katru dienu mainās, tāpēc tas ir laika logs, ne pastāvīga īpašība. Precizitātei svarīga pareiza šībrīža lokācija (saullēkts, muhurtas rēķinās pēc vietas); prognozes raksturs pats par sevi nosaka griestus.',
    }),
};

export function descriptorFor(key, opts = {}) {
    const fn = ARCHETYPES[key];
    if (!fn) return { drivers: [], requires: ['birthplace'] };
    return fn(opts);
}

export const ARCHETYPE_KEYS = Object.keys(ARCHETYPES);
