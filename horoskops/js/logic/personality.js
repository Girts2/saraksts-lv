// ── PERSONĪBAS MATRICA ────────────────────────────────────────────────────────
// 73 dimensijas / 21 kategorija. Katrs faktors: 1–10. Normalizācija → 0–100%.

import { calculateBigFive } from './big_five.js?v=2';

// Maiju tonis → atvērtība/radošums (arī izmantota big_five.js)
const TONE_OPEN = {1:5,2:7,3:7,4:3,5:6,6:6,7:8,8:3,9:6,10:6,11:8,12:8,13:6};

// ── KOPĪGĀS TABULAS ───────────────────────────────────────────────────────────

// Saules zīme → daudzām dimensijām
const SUN = {
    analytical:    { Jaunava:10,Mežāzis:9,Ūdensvīrs:8,Vērsis:7,Svari:7,Skorpions:6,Dvīņi:6,Vēzis:4,Lauva:3,Auns:3,Strēlnieks:3,Zivis:3 },
    creative:      { Lauva:10,Zivis:9,Svari:8,Strēlnieks:8,Ūdensvīrs:8,Dvīņi:7,Auns:6,Vēzis:6,Skorpions:5,Vērsis:4,Jaunava:3,Mežāzis:3 },
    assertive:     { Auns:10,Lauva:9,Mežāzis:8,Strēlnieks:7,Skorpions:7,Dvīņi:6,Ūdensvīrs:5,Vērsis:4,Jaunava:4,Svari:4,Vēzis:3,Zivis:2 },
    charisma:      { Lauva:10,Auns:9,Strēlnieks:8,Svari:7,Dvīņi:7,Ūdensvīrs:6,Vērsis:5,Mežāzis:5,Vēzis:4,Skorpions:4,Jaunava:3,Zivis:3 },
    ambition:      { Mežāzis:10,Auns:9,Lauva:8,Strēlnieks:7,Skorpions:7,Ūdensvīrs:6,Vērsis:6,Jaunava:5,Dvīņi:4,Svari:4,Vēzis:3,Zivis:3 },
    perseverance:  { Vērsis:10,Skorpions:9,Lauva:9,Ūdensvīrs:8,Mežāzis:8,Jaunava:7,Vēzis:5,Auns:4,Dvīņi:4,Svari:4,Strēlnieks:3,Zivis:3 },
    initiative:    { Auns:10,Lauva:8,Mežāzis:8,Strēlnieks:7,Vēzis:7,Svari:6,Ūdensvīrs:5,Dvīņi:5,Vērsis:4,Jaunava:4,Skorpions:4,Zivis:3 },
    risk:          { Auns:10,Strēlnieks:9,Lauva:8,Dvīņi:7,Ūdensvīrs:7,Svari:5,Skorpions:5,Mežāzis:4,Vēzis:3,Vērsis:3,Jaunava:2,Zivis:2 },
    perfectionism: { Jaunava:10,Mežāzis:9,Vērsis:8,Svari:7,Ūdensvīrs:7,Skorpions:6,Dvīņi:5,Lauva:5,Vēzis:4,Auns:3,Strēlnieks:2,Zivis:3 },
    adaptability:  { Dvīņi:10,Strēlnieks:9,Zivis:8,Jaunava:7,Ūdensvīrs:8,Svari:7,Vēzis:6,Auns:5,Lauva:4,Skorpions:4,Vērsis:3,Mežāzis:3 },
    spiritual:     { Zivis:10,Strēlnieks:9,Ūdensvīrs:8,Vēzis:7,Svari:6,Skorpions:7,Dvīņi:5,Lauva:4,Auns:4,Jaunava:3,Vērsis:3,Mežāzis:4 },
    material:      { Mežāzis:10,Vērsis:9,Jaunava:8,Lauva:6,Auns:6,Strēlnieks:5,Ūdensvīrs:4,Dvīņi:4,Svari:4,Vēzis:4,Skorpions:5,Zivis:3 },
    independent:   { Ūdensvīrs:10,Auns:9,Strēlnieks:8,Lauva:7,Dvīņi:7,Skorpions:6,Jaunava:5,Mežāzis:5,Svari:4,Vērsis:3,Vēzis:3,Zivis:3 },
    traditional:   { Mežāzis:10,Vērsis:9,Lauva:7,Jaunava:7,Skorpions:6,Ūdensvīrs:5,Vēzis:5,Dvīņi:3,Svari:4,Auns:3,Strēlnieks:3,Zivis:4 },
    extra:         { Lauva:10,Auns:9,Strēlnieks:8,Dvīņi:7,Svari:7,Ūdensvīrs:6,Vērsis:5,Mežāzis:4,Vēzis:3,Jaunava:3,Zivis:3,Skorpions:2 },
    conscient:     { Mežāzis:9,Jaunava:8,Vērsis:7,Ūdensvīrs:6,Svari:5,Vēzis:5,Skorpions:5,Lauva:4,Dvīņi:4,Auns:3,Strēlnieks:3,Zivis:3 },
    optimism:      { Strēlnieks:10,Auns:9,Lauva:8,Dvīņi:7,Ūdensvīrs:7,Svari:6,Zivis:6,Vērsis:5,Vēzis:4,Mežāzis:4,Jaunava:3,Skorpions:3 },
};

// Mēness zīme → emocionālās dimensijas
const MOON = {
    intuition:   { Zivis:10,Vēzis:9,Skorpions:8,Ūdensvīrs:7,Strēlnieks:6,Svari:5,Lauva:4,Dvīņi:4,Vērsis:4,Auns:3,Mežāzis:3,Jaunava:3 },
    stability:   { Vērsis:10,Mežāzis:9,Lauva:8,Ūdensvīrs:7,Jaunava:7,Strēlnieks:6,Dvīņi:5,Svari:5,Auns:4,Vēzis:3,Zivis:3,Skorpions:2 },
    empathy:     { Vēzis:10,Zivis:10,Svari:8,Vērsis:8,Jaunava:7,Dvīņi:6,Lauva:5,Ūdensvīrs:5,Strēlnieks:5,Auns:3,Mežāzis:3,Skorpions:2 },
    depth:       { Skorpions:10,Vēzis:9,Zivis:8,Ūdensvīrs:6,Auns:6,Mežāzis:5,Svari:5,Lauva:5,Vērsis:4,Strēlnieks:4,Dvīņi:3,Jaunava:3 },
    spiritual:   { Zivis:10,Vēzis:8,Strēlnieks:7,Ūdensvīrs:7,Svari:6,Skorpions:6,Dvīņi:5,Lauva:4,Auns:4,Jaunava:3,Vērsis:3,Mežāzis:3 },
    diplomacy:   { Svari:10,Vērsis:9,Dvīņi:8,Zivis:7,Vēzis:7,Jaunava:6,Ūdensvīrs:5,Strēlnieks:4,Lauva:4,Mežāzis:4,Auns:2,Skorpions:2 },
    extra:       { Lauva:9,Auns:8,Strēlnieks:7,Dvīņi:7,Svari:6,Ūdensvīrs:6,Vērsis:5,Vēzis:4,Mežāzis:3,Jaunava:3,Zivis:3,Skorpions:2 },
    optimism:    { Strēlnieks:9,Auns:8,Lauva:8,Dvīņi:7,Ūdensvīrs:6,Svari:6,Zivis:5,Vērsis:5,Vēzis:4,Mežāzis:4,Jaunava:3,Skorpions:3 },
    neuro:       { Skorpions:9,Vēzis:8,Zivis:8,Auns:7,Dvīņi:6,Lauva:5,Svari:5,Vērsis:4,Jaunava:4,Mežāzis:3,Ūdensvīrs:3,Strēlnieks:2 },
    agree:       { Vēzis:10,Vērsis:9,Zivis:9,Jaunava:7,Svari:7,Dvīņi:6,Lauva:5,Ūdensvīrs:5,Strēlnieks:5,Auns:3,Mežāzis:3,Skorpions:2 },
};

// Merkurs zīme → kognitīvās dimensijas
const MERC = {
    analytical: { Jaunava:10,Dvīņi:9,Mežāzis:8,Ūdensvīrs:8,Vērsis:7,Svari:7,Skorpions:5,Vēzis:5,Auns:4,Lauva:4,Strēlnieks:3,Zivis:3 },
    learning:   { Dvīņi:10,Ūdensvīrs:9,Strēlnieks:8,Svari:7,Zivis:7,Lauva:6,Auns:6,Vēzis:5,Skorpions:5,Vērsis:4,Jaunava:4,Mežāzis:4 },
    openness:   { Dvīņi:9,Ūdensvīrs:8,Zivis:7,Svari:7,Strēlnieks:6,Lauva:5,Vēzis:5,Auns:5,Skorpions:4,Vērsis:4,Jaunava:3,Mežāzis:3 },
};

// Marss zīme → darbaspēks / asertivitāte
const MARS = {
    assertive:  { Auns:10,Lauva:9,Strēlnieks:8,Mežāzis:7,Skorpions:7,Dvīņi:6,Vērsis:5,Ūdensvīrs:5,Jaunava:4,Svari:3,Vēzis:3,Zivis:2 },
    initiative: { Auns:10,Mežāzis:9,Strēlnieks:8,Lauva:7,Vēzis:7,Skorpions:6,Dvīņi:5,Ūdensvīrs:5,Vērsis:4,Svari:4,Jaunava:3,Zivis:3 },
    risk:       { Auns:10,Strēlnieks:9,Lauva:8,Dvīņi:7,Ūdensvīrs:6,Skorpions:5,Vēzis:4,Vērsis:3,Mežāzis:4,Svari:3,Jaunava:2,Zivis:2 },
    ambition:   { Mežāzis:10,Auns:9,Lauva:8,Strēlnieks:7,Skorpions:7,Ūdensvīrs:6,Dvīņi:5,Vērsis:5,Jaunava:4,Svari:3,Vēzis:2,Zivis:2 },
    perfectionism: { Mežāzis:9,Jaunava:8,Vērsis:7,Skorpions:7,Auns:5,Ūdensvīrs:5,Lauva:5,Dvīņi:4,Svari:4,Vēzis:3,Strēlnieks:3,Zivis:3 },
};

// Saturns zīme → struktūra
const SAT = {
    analytical:   { Mežāzis:10,Ūdensvīrs:9,Jaunava:8,Svari:7,Vērsis:6,Dvīņi:5,Skorpions:5,Lauva:4,Auns:3,Strēlnieks:3,Vēzis:3,Zivis:3 },
    ambition:     { Mežāzis:10,Auns:8,Ūdensvīrs:8,Jaunava:7,Lauva:7,Vērsis:6,Svari:5,Skorpions:5,Dvīņi:4,Strēlnieks:4,Vēzis:3,Zivis:3 },
    perseverance: { Mežāzis:10,Ūdensvīrs:9,Jaunava:8,Svari:8,Vērsis:7,Dvīņi:5,Auns:5,Skorpions:4,Lauva:4,Strēlnieks:4,Vēzis:3,Zivis:3 },
    perfectionism:{ Mežāzis:10,Jaunava:9,Ūdensvīrs:8,Svari:7,Vērsis:7,Dvīņi:5,Skorpions:5,Lauva:4,Auns:3,Strēlnieks:3,Vēzis:3,Zivis:3 },
    conscient:    { Mežāzis:10,Ūdensvīrs:9,Jaunava:8,Svari:8,Vērsis:7,Dvīņi:5,Auns:5,Skorpions:4,Lauva:4,Strēlnieks:4,Vēzis:3,Zivis:3 },
    traditional:  { Mežāzis:10,Ūdensvīrs:8,Jaunava:7,Vērsis:8,Svari:6,Lauva:5,Skorpions:5,Dvīņi:4,Auns:3,Strēlnieks:3,Vēzis:3,Zivis:3 },
    selfcontrol:  { Mežāzis:10,Ūdensvīrs:9,Jaunava:8,Svari:7,Vērsis:7,Skorpions:5,Dvīņi:5,Lauva:4,Vēzis:4,Auns:3,Strēlnieks:3,Zivis:3 },
};

// Jupiters zīme
const JUP = {
    openness:  { Strēlnieks:9,Zivis:8,Vēzis:8,Lauva:6,Auns:6,Svari:6,Dvīņi:5,Ūdensvīrs:5,Vērsis:5,Skorpions:4,Jaunava:3,Mežāzis:3 },
    optimism:  { Strēlnieks:10,Zivis:8,Vēzis:8,Lauva:7,Auns:7,Svari:6,Dvīņi:5,Ūdensvīrs:5,Vērsis:5,Skorpions:3,Jaunava:3,Mežāzis:3 },
    risk:      { Strēlnieks:9,Auns:8,Lauva:8,Zivis:6,Ūdensvīrs:6,Dvīņi:6,Svari:5,Vēzis:5,Vērsis:4,Skorpions:4,Jaunava:3,Mežāzis:3 },
    learning:  { Strēlnieks:10,Dvīņi:9,Zivis:8,Ūdensvīrs:7,Svari:7,Auns:6,Lauva:6,Vēzis:5,Vērsis:5,Skorpions:4,Jaunava:4,Mežāzis:4 },
    charisma:  { Strēlnieks:8,Lauva:7,Auns:7,Zivis:6,Vēzis:6,Svari:6,Dvīņi:5,Ūdensvīrs:5,Vērsis:4,Skorpions:4,Jaunava:3,Mežāzis:3 },
};

// Venera dignitas
const VEN_DIG = {
    creative:   { Eksaltācija:10,Valdījums:9,Peregrīns:6,Trimda:3,Kritums:2 },
    charisma:   { Eksaltācija:10,Valdījums:9,Peregrīns:6,Trimda:3,Kritums:2 },
    agree:      { Eksaltācija:10,Valdījums:9,Peregrīns:6,Trimda:3,Kritums:2 },
    diplomacy:  { Eksaltācija:10,Valdījums:9,Peregrīns:6,Trimda:3,Kritums:2 },
};

// Venera zīme → sociālā harmonija
const VEN_SIGN = {
    agree:    { Vērsis:10,Svari:10,Zivis:9,Vēzis:8,Dvīņi:7,Ūdensvīrs:6,Lauva:5,Strēlnieks:5,Jaunava:5,Mežāzis:4,Auns:4,Skorpions:3 },
    creative: { Svari:10,Vērsis:9,Lauva:8,Zivis:8,Dvīņi:7,Ūdensvīrs:7,Auns:6,Vēzis:6,Strēlnieks:5,Skorpions:4,Jaunava:3,Mežāzis:3 },
    diplomacy:{ Svari:10,Vērsis:9,Dvīņi:8,Vēzis:8,Zivis:7,Jaunava:6,Ūdensvīrs:5,Lauva:4,Strēlnieks:4,Mežāzis:4,Auns:2,Skorpions:2 },
};

// Rahu zīme → neirotisms / Ketu zīme → garīgums
const RAHU_NEURO = { Skorpions:8,Vēzis:7,Auns:7,Zivis:6,Mežāzis:6,Dvīņi:5,Vērsis:5,Jaunava:5,Lauva:5,Svari:4,Ūdensvīrs:4,Strēlnieks:3 };
const KETU_SPIRIT = { Zivis:9,Vēzis:9,Strēlnieks:8,Skorpions:8,Ūdensvīrs:7,Svari:6,Lauva:5,Auns:5,Dvīņi:4,Vērsis:3,Jaunava:3,Mežāzis:4 };

// Neptūns / Urāns zīme
const NEPT_SPIRIT = { Zivis:9,Vēzis:8,Svari:7,Strēlnieks:7,Skorpions:7,Ūdensvīrs:6,Dvīņi:5,Lauva:4,Vērsis:4,Auns:4,Jaunava:3,Mežāzis:3 };
const URAN_INDEP  = { Ūdensvīrs:10,Auns:8,Strēlnieks:7,Dvīņi:7,Lauva:6,Svari:6,Skorpions:5,Vēzis:4,Jaunava:4,Vērsis:3,Mežāzis:4,Zivis:5 };

// ── ĒNAS PUSES TABULAS ───────────────────────────────────────────────────────

// Ketu → pašsabotāžas un karmas parādu paterni
const KETU_SHADOW  = { Skorpions:9,Vēzis:8,Auns:8,Lauva:7,Mežāzis:7,Dvīņi:6,Jaunava:6,Svari:5,Ūdensvīrs:5,Zivis:7,Strēlnieks:4,Vērsis:4 };
// Plutons → obsesīvās tendences un dziļās atkarības
const PLUT_OBSESS  = { Skorpions:10,Lauva:8,Vēzis:7,Auns:7,Mežāzis:6,Ūdensvīrs:6,Strēlnieks:5,Zivis:5,Jaunava:4,Svari:4,Dvīņi:4,Vērsis:4 };

// ── IDENTITĀTES DUALITĀTES TABULAS ───────────────────────────────────────────

// Saule → publiskās personas ekspresivitāte (sociālā maska)
const SUN_PERSONA   = { Lauva:10,Auns:8,Strēlnieks:8,Dvīņi:8,Ūdensvīrs:7,Svari:7,Mežāzis:6,Vērsis:5,Jaunava:5,Vēzis:4,Skorpions:3,Zivis:3 };
// Mēness → privātās iekšējās pasaules dziļums (cik slēgta ir patiesā seja)
const MOON_PRIVACY  = { Skorpions:9,Vēzis:9,Zivis:8,Jaunava:7,Mežāzis:7,Vērsis:6,Svari:5,Ūdensvīrs:5,Dvīņi:4,Auns:4,Lauva:3,Strēlnieks:3 };
// Saule → statusa un atzinības izsalkums
const SUN_STATUS    = { Lauva:10,Mežāzis:9,Auns:8,Skorpions:7,Ūdensvīrs:6,Strēlnieks:6,Jaunava:5,Dvīņi:5,Svari:5,Vērsis:4,Vēzis:4,Zivis:3 };

// ── LAIKA UZTVERES TABULAS ────────────────────────────────────────────────────

// Saule → pārmaiņu adaptācija (Mainīgas > Kardinālas > Fiksētas)
const SUN_TRANSIT  = { Dvīņi:9,Zivis:9,Strēlnieks:8,Jaunava:7,Ūdensvīrs:7,Vēzis:6,Svari:6,Auns:6,Lauva:4,Skorpions:4,Vērsis:3,Mežāzis:3 };
// Mēness → pārmaiņu uztvere
const MOON_TRANSIT = { Dvīņi:9,Zivis:8,Strēlnieks:8,Jaunava:7,Ūdensvīrs:7,Svari:6,Vēzis:6,Auns:5,Lauva:4,Skorpions:4,Vērsis:3,Mežāzis:3 };
// Saule → ilgtermiņa domāšana (Fikss + Zeme = augsts)
const SUN_LONGTERM = { Mežāzis:10,Vērsis:9,Lauva:8,Ūdensvīrs:8,Jaunava:7,Skorpions:7,Svari:6,Dvīņi:5,Vēzis:5,Auns:4,Zivis:4,Strēlnieks:3 };
// Saturns → ilgtermiņa domāšana un atliktā gandarījuma spēja
const SAT_LONGTERM = { Mežāzis:10,Ūdensvīrs:9,Jaunava:8,Vērsis:8,Svari:7,Lauva:6,Skorpions:5,Dvīņi:4,Vēzis:3,Auns:3,Zivis:3,Strēlnieks:3 };

// ── TALANTU POTENCIĀLA TABULAS ────────────────────────────────────────────────

// Saule → hiperfokusa spēja (Fikss + Zeme = dziļa specializācija)
const SUN_HYPERFOCUS = { Vērsis:9,Lauva:9,Skorpions:9,Ūdensvīrs:8,Mežāzis:8,Jaunava:8,Vēzis:6,Auns:6,Zivis:6,Dvīņi:5,Svari:5,Strēlnieks:5 };
// Merkurs → sintezēšanas spēja (savienot nesaistītas jomas)
const MERC_SYNTH   = { Dvīņi:10,Ūdensvīrs:9,Svari:8,Strēlnieks:8,Lauva:7,Auns:6,Zivis:6,Jaunava:5,Vēzis:5,Vērsis:4,Mežāzis:4,Skorpions:4 };
// Jupiters → plašas sintēzes un vīzionārās spējas
const JUP_SYNTH    = { Strēlnieks:9,Dvīņi:8,Zivis:8,Ūdensvīrs:7,Svari:7,Lauva:6,Vēzis:6,Auns:6,Vērsis:5,Jaunava:4,Mežāzis:4,Skorpions:5 };

// ── MORĀLĀ KOMPASA TABULAS ────────────────────────────────────────────────────

// Saule → kategoriskums (melnbaltā domāšana, stingri principi)
const SUN_CATEG     = { Lauva:9,Skorpions:9,Mežāzis:8,Vērsis:8,Auns:7,Jaunava:7,Strēlnieks:6,Vēzis:4,Svari:4,Zivis:4,Dvīņi:3,Ūdensvīrs:2 };
// Saturns → principu neelastība un noteikumu stingrība
const SAT_CATEG     = { Mežāzis:10,Ūdensvīrs:9,Jaunava:8,Vērsis:8,Lauva:6,Svari:6,Skorpions:5,Dvīņi:4,Vēzis:3,Auns:3,Zivis:3,Strēlnieks:3 };
// Saule → hierarhijas cieņa (augsts = ciena; zems = dumpiniecisks)
const SUN_HIERARCHY = { Mežāzis:9,Lauva:8,Vērsis:8,Jaunava:7,Svari:6,Skorpions:6,Vēzis:5,Dvīņi:4,Auns:3,Zivis:4,Strēlnieks:3,Ūdensvīrs:2 };
// Saturns → hierarhijas cieņa (Mežāzis=struktūra; Ūdensvīrs=sistēmiska neatkarība)
const SAT_HIERARCHY  = { Mežāzis:10,Vērsis:8,Jaunava:7,Lauva:6,Svari:6,Skorpions:5,Dvīņi:4,Vēzis:4,Zivis:3,Strēlnieks:3,Auns:3,Ūdensvīrs:2 };

// ── KOMUNIKĀCIJAS TABULAS ─────────────────────────────────────────────────────

const MERC_EXPRESS  = { Auns:9,Lauva:8,Strēlnieks:8,Dvīņi:8,Ūdensvīrs:7,Jaunava:6,Svari:5,Mežāzis:5,Vērsis:4,Vēzis:3,Zivis:3,Skorpions:2 };
const MERC_ABSTRACT = { Ūdensvīrs:9,Dvīņi:9,Svari:8,Strēlnieks:8,Lauva:7,Auns:7,Zivis:6,Skorpions:5,Vēzis:4,Jaunava:3,Vērsis:3,Mežāzis:3 };
const MERC_CONFLICT = { Auns:9,Lauva:8,Strēlnieks:7,Dvīņi:6,Ūdensvīrs:6,Skorpions:5,Jaunava:4,Mežāzis:4,Vērsis:3,Vēzis:3,Svari:2,Zivis:2 };

// ── ENERĢĒTIKAS TABULAS ───────────────────────────────────────────────────────

const SUN_VITAL      = { Auns:10,Lauva:9,Strēlnieks:8,Dvīņi:7,Vērsis:7,Mežāzis:7,Ūdensvīrs:6,Svari:6,Skorpions:6,Jaunava:6,Vēzis:5,Zivis:4 };
const MARS_VITAL     = { Auns:10,Lauva:8,Strēlnieks:8,Dvīņi:7,Mežāzis:7,Jaunava:6,Skorpions:6,Ūdensvīrs:5,Vērsis:5,Svari:4,Vēzis:4,Zivis:3 };
const SUN_CIRCADIAN  = { Auns:8,Lauva:8,Strēlnieks:7,Dvīņi:7,Vērsis:6,Jaunava:6,Mežāzis:6,Svari:5,Ūdensvīrs:5,Vēzis:4,Skorpions:3,Zivis:3 };
const MOON_CIRCADIAN = { Auns:8,Lauva:8,Strēlnieks:7,Dvīņi:6,Vērsis:6,Jaunava:6,Mežāzis:5,Ūdensvīrs:5,Svari:5,Vēzis:4,Zivis:3,Skorpions:3 };
const MOON_RESIL     = { Vērsis:10,Mežāzis:9,Lauva:8,Ūdensvīrs:7,Strēlnieks:6,Jaunava:6,Dvīņi:5,Svari:5,Auns:4,Vēzis:3,Zivis:3,Skorpions:2 };

// ── ATTIECĪBU TABULAS ─────────────────────────────────────────────────────────

const MOON_ATTACH  = { Vēzis:10,Vērsis:9,Jaunava:8,Zivis:8,Svari:7,Dvīņi:6,Mežāzis:6,Lauva:5,Ūdensvīrs:5,Strēlnieks:4,Auns:3,Skorpions:2 };
const VEN_SENSUAL  = { Vērsis:10,Skorpions:9,Lauva:8,Auns:7,Vēzis:7,Svari:6,Mežāzis:6,Jaunava:5,Zivis:5,Strēlnieks:4,Dvīņi:3,Ūdensvīrs:3 };
const MOON_SENSUAL = { Vērsis:10,Vēzis:9,Skorpions:8,Lauva:7,Mežāzis:6,Jaunava:5,Auns:5,Zivis:5,Svari:4,Strēlnieks:3,Dvīņi:3,Ūdensvīrs:3 };
const MOON_NURTURE = { Vēzis:10,Vērsis:9,Jaunava:8,Zivis:8,Svari:7,Dvīņi:6,Lauva:6,Mežāzis:5,Ūdensvīrs:5,Strēlnieks:4,Auns:3,Skorpions:3 };
const VEN_NURTURE  = { Vēzis:10,Vērsis:9,Zivis:8,Jaunava:7,Svari:7,Dvīņi:6,Lauva:5,Mežāzis:4,Ūdensvīrs:4,Strēlnieks:4,Auns:3,Skorpions:3 };
const MARS_POSSESS = { Skorpions:9,Vērsis:8,Lauva:7,Vēzis:7,Auns:6,Jaunava:5,Mežāzis:5,Svari:4,Dvīņi:3,Ūdensvīrs:3,Strēlnieks:3,Zivis:4 };
const MOON_POSSESS = { Skorpions:9,Vērsis:8,Vēzis:7,Lauva:6,Auns:5,Jaunava:4,Mežāzis:4,Svari:3,Dvīņi:3,Ūdensvīrs:3,Strēlnieks:3,Zivis:4 };

// ── KRĪZES UN STRESA TABULAS ─────────────────────────────────────────────────

const SUN_FIGHT  = { Auns:9,Lauva:8,Strēlnieks:7,Mežāzis:7,Skorpions:6,Dvīņi:5,Ūdensvīrs:5,Jaunava:4,Vēzis:4,Vērsis:4,Svari:3,Zivis:2 };
const SUN_CRISIS = { Dvīņi:9,Zivis:9,Strēlnieks:8,Jaunava:7,Ūdensvīrs:7,Vēzis:6,Svari:6,Auns:5,Lauva:4,Skorpions:4,Vērsis:3,Mežāzis:3 };

// ── RESURSU UN FINANŠU TABULAS ────────────────────────────────────────────────

const SAT_SAVINGS = { Mežāzis:10,Ūdensvīrs:9,Jaunava:8,Vērsis:8,Svari:6,Skorpions:6,Lauva:4,Dvīņi:4,Vēzis:4,Auns:3,Strēlnieks:2,Zivis:2 };
const DM_SAVINGS  = { 'Jaņ Metāls':9,'Iņ Metāls':8,'Jaņ Zeme':9,'Iņ Zeme':8,'Jaņ Koks':5,'Iņ Koks':4,'Jaņ Ūdens':5,'Iņ Ūdens':4,'Jaņ Uguns':3,'Iņ Uguns':2 };
const VEN_SAVINGS = { Vērsis:9,Mežāzis:8,Jaunava:7,Ūdensvīrs:7,Svari:6,Vēzis:5,Lauva:5,Auns:4,Skorpions:4,Dvīņi:3,Zivis:2,Strēlnieks:2 };
const JUP_FINRISK = { Strēlnieks:9,Auns:8,Zivis:7,Lauva:7,Dvīņi:6,Svari:6,Ūdensvīrs:5,Vēzis:5,Skorpions:5,Vērsis:4,Jaunava:3,Mežāzis:3 };

// ── DZĪVES CEĻA UN VADĪBAS TABULAS ───────────────────────────────────────────

const SUN_LOCUS   = { Auns:9,Mežāzis:9,Lauva:8,Ūdensvīrs:8,Strēlnieks:7,Jaunava:7,Dvīņi:6,Vērsis:6,Svari:5,Vēzis:4,Skorpions:4,Zivis:2 };
const SUN_AUTH    = { Lauva:10,Mežāzis:9,Auns:9,Strēlnieks:7,Skorpions:7,Ūdensvīrs:6,Jaunava:5,Vērsis:5,Dvīņi:4,Svari:4,Vēzis:3,Zivis:3 };
const JUP_INSPIRE = { Strēlnieks:10,Zivis:8,Lauva:8,Auns:7,Svari:7,Vēzis:7,Dvīņi:6,Ūdensvīrs:6,Vērsis:5,Mežāzis:5,Skorpions:4,Jaunava:3 };
const SUN_INSPIRE = { Lauva:10,Strēlnieks:9,Auns:8,Dvīņi:7,Svari:7,Ūdensvīrs:7,Vēzis:6,Zivis:6,Vērsis:5,Mežāzis:4,Jaunava:4,Skorpions:4 };

// ── KARMISKĀS ATTĪSTĪBAS TABULAS ─────────────────────────────────────────────

const RAHU_SOUL   = { Auns:9,Lauva:8,Mežāzis:9,Ūdensvīrs:9,Strēlnieks:8,Dvīņi:7,Vēzis:6,Svari:6,Jaunava:6,Vērsis:5,Zivis:5,Skorpions:4 };
const KETU_TALENT = { Zivis:9,Vēzis:9,Strēlnieks:8,Svari:8,Lauva:7,Dvīņi:7,Ūdensvīrs:7,Vērsis:6,Jaunava:6,Auns:5,Mežāzis:5,Skorpions:5 };

// ── MEDICĪNISKĀS ASTROLOĢIJAS TABULAS ────────────────────────────────────────

const SUN_VITALCAP  = { Lauva:10,Auns:9,Strēlnieks:8,Mežāzis:7,Vērsis:7,Dvīņi:6,Svari:6,Jaunava:6,Ūdensvīrs:5,Vēzis:5,Skorpions:5,Zivis:4 };
const MERC_NEURAL   = { Dvīņi:9,Jaunava:8,Ūdensvīrs:8,Svari:8,Zivis:7,Vēzis:7,Strēlnieks:6,Lauva:5,Auns:5,Skorpions:5,Vērsis:4,Mežāzis:3 };
const SAT_IMMUNITY  = { Mežāzis:10,Ūdensvīrs:9,Jaunava:8,Vērsis:8,Svari:6,Lauva:6,Skorpions:5,Dvīņi:4,Vēzis:4,Auns:3,Strēlnieks:3,Zivis:3 };
// Hirons zīme → dziedināšanas brūces aktivitāte (Dzelzs/Ūdensvīrs: visredzamākā)
const CHIRON_WOUND  = { Auns:9,Jaunava:9,Zivis:8,Vēzis:8,Skorpions:8,Lauva:7,Mežāzis:7,Strēlnieks:7,Ūdensvīrs:6,Svari:5,Vērsis:5,Dvīņi:4 };
// Psihosomatiskais savienojums — ūdens + mainīgās zīmes = augstāks
const SUN_PSYCHOSOM  = { Vēzis:10,Zivis:9,Skorpions:8,Jaunava:7,Dvīņi:7,Svari:6,Lauva:5,Ūdensvīrs:5,Strēlnieks:4,Vērsis:4,Auns:3,Mežāzis:3 };
const MOON_PSYCHOSOM = { Vēzis:10,Zivis:9,Skorpions:8,Jaunava:7,Svari:6,Dvīņi:6,Lauva:5,Ūdensvīrs:5,Vērsis:4,Strēlnieks:4,Auns:3,Mežāzis:3 };
// Saturns hroniskā vulnerabilitāte: Saturns cienīgos → zema; ienaidnieka zīmēs → augsta
const SAT_CHRONIC   = { Mežāzis:2,Ūdensvīrs:3,Svari:4,Jaunava:4,Vērsis:5,Dvīņi:5,Lauva:6,Skorpions:6,Vēzis:7,Strēlnieks:7,Auns:8,Zivis:8 };

// ── DASHA PERIODA TABULAS ─────────────────────────────────────────────────────

const DASHA_EXP   = { 'Jupiters':9,'Venera':8,'Merkurs':7,'Saule':7,'Meness':6,'Marss':5,'Rahu':4,'Saturns':3,'Ketu':2 };
const DASHA_TRANS = { 'Rahu':9,'Saturns':8,'Ketu':8,'Marss':7,'Saule':6,'Meness':5,'Jupiters':5,'Venera':4,'Merkurs':3 };

// BaZi Daymaster → vairākas dimensijas
const DM = {
    analytical:   { 'Jaņ Metāls':8,'Iņ Metāls':8,'Jaņ Zeme':7,'Iņ Zeme':7,'Jaņ Ūdens':6,'Iņ Ūdens':6,'Jaņ Koks':4,'Iņ Koks':4,'Jaņ Uguns':3,'Iņ Uguns':3 },
    assertive:    { 'Jaņ Uguns':9,'Jaņ Metāls':8,'Jaņ Koks':6,'Iņ Uguns':6,'Iņ Metāls':5,'Jaņ Zeme':5,'Iņ Koks':4,'Jaņ Ūdens':4,'Iņ Zeme':4,'Iņ Ūdens':3 },
    ambition:     { 'Jaņ Metāls':9,'Jaņ Uguns':8,'Jaņ Zeme':8,'Iņ Metāls':7,'Iņ Zeme':7,'Jaņ Koks':6,'Iņ Uguns':5,'Iņ Koks':4,'Jaņ Ūdens':4,'Iņ Ūdens':3 },
    perseverance: { 'Jaņ Metāls':9,'Iņ Metāls':8,'Jaņ Zeme':8,'Iņ Zeme':7,'Jaņ Koks':5,'Iņ Koks':4,'Jaņ Ūdens':4,'Jaņ Uguns':3,'Iņ Uguns':3,'Iņ Ūdens':3 },
    perfectionism:{ 'Jaņ Metāls':9,'Iņ Metāls':9,'Jaņ Zeme':7,'Iņ Zeme':7,'Jaņ Koks':4,'Iņ Koks':4,'Jaņ Uguns':3,'Iņ Uguns':3,'Jaņ Ūdens':5,'Iņ Ūdens':4 },
    material:     { 'Jaņ Metāls':8,'Iņ Metāls':7,'Jaņ Zeme':9,'Iņ Zeme':8,'Jaņ Koks':5,'Iņ Koks':4,'Jaņ Uguns':4,'Iņ Uguns':3,'Jaņ Ūdens':4,'Iņ Ūdens':3 },
    traditional:  { 'Jaņ Metāls':8,'Iņ Metāls':7,'Jaņ Zeme':9,'Iņ Zeme':8,'Jaņ Koks':4,'Iņ Koks':4,'Jaņ Uguns':3,'Iņ Uguns':3,'Jaņ Ūdens':4,'Iņ Ūdens':4 },
    independent:  { 'Jaņ Uguns':9,'Jaņ Metāls':8,'Jaņ Koks':7,'Iņ Uguns':7,'Iņ Metāls':6,'Jaņ Ūdens':6,'Iņ Koks':5,'Iņ Ūdens':5,'Jaņ Zeme':4,'Iņ Zeme':4 },
    adaptive:     { 'Jaņ Ūdens':9,'Iņ Ūdens':9,'Iņ Koks':8,'Jaņ Koks':7,'Iņ Uguns':6,'Jaņ Uguns':5,'Iņ Zeme':5,'Jaņ Zeme':4,'Iņ Metāls':4,'Jaņ Metāls':3 },
    intuition:    { 'Iņ Ūdens':10,'Jaņ Ūdens':9,'Iņ Koks':7,'Jaņ Koks':6,'Iņ Uguns':5,'Jaņ Uguns':4,'Iņ Zeme':4,'Jaņ Zeme':3,'Iņ Metāls':3,'Jaņ Metāls':3 },
    conscient:    { 'Jaņ Metāls':9,'Iņ Metāls':8,'Jaņ Zeme':8,'Iņ Zeme':7,'Jaņ Koks':5,'Iņ Koks':4,'Jaņ Uguns':4,'Iņ Uguns':3,'Jaņ Ūdens':4,'Iņ Ūdens':3 },
};

// Temperamenta ieguldījums (Hellēnistiskais) katrā dimensijā
const TEMP = {
    Choleric:    { analytical:5,intuition:4,creative:6,assertive:8,charisma:7,diplomacy:2,ambition:7,perseverance:5,initiative:9,risk:8,perfectionism:4,adaptive:7,spiritual:3,material:6,independent:8,traditional:3,stability:2,empathy:2,optimism:6,depth:5,selfcontrol:3,extra:8,openness:6,conscient:5,agree:2,neuro:7,learning:5,focus:4,express:9,abstract:6,conflstyl:9,vitality:9,sensual:5,nurture:3,possess:7,fight:9,crisisflex:5,savings:5,finrisk:8,locus:9,authority:9,inspire:8,destroy:9,obsession:7,blindspot:6,persona:5,status:9,transit:7,longterm:4,hyperfocus:5,synthesis:7,categorical:8,hierarchy:4,vitalcap:9,neuralsens:6,healcap:6,psychosom:5,chromvuln:4 },
    Sanguine:    { analytical:5,intuition:5,creative:7,assertive:6,charisma:9,diplomacy:6,ambition:5,perseverance:4,initiative:8,risk:7,perfectionism:3,adaptive:9,spiritual:5,material:5,independent:7,traditional:3,stability:4,empathy:7,optimism:9,depth:4,selfcontrol:3,extra:9,openness:7,conscient:4,agree:7,neuro:4,learning:7,focus:3,express:8,abstract:8,conflstyl:6,vitality:8,sensual:7,nurture:7,possess:3,fight:6,crisisflex:9,savings:3,finrisk:7,locus:7,authority:5,inspire:9,destroy:4,obsession:4,blindspot:5,persona:3,status:6,transit:9,longterm:3,hyperfocus:4,synthesis:9,categorical:3,hierarchy:5,vitalcap:8,neuralsens:5,healcap:7,psychosom:4,chromvuln:3 },
    Phlegmatic:  { analytical:6,intuition:6,creative:5,assertive:3,charisma:5,diplomacy:9,ambition:4,perseverance:8,initiative:3,risk:3,perfectionism:6,adaptive:5,spiritual:7,material:5,independent:4,traditional:7,stability:8,empathy:9,optimism:6,depth:6,selfcontrol:8,extra:4,openness:5,conscient:7,agree:9,neuro:2,learning:6,focus:7,express:3,abstract:4,conflstyl:2,vitality:5,sensual:8,nurture:9,possess:5,fight:3,crisisflex:7,savings:7,finrisk:3,locus:4,authority:3,inspire:5,destroy:3,obsession:3,blindspot:4,persona:6,status:3,transit:5,longterm:8,hyperfocus:7,synthesis:4,categorical:5,hierarchy:8,vitalcap:5,neuralsens:3,healcap:8,psychosom:7,chromvuln:6 },
    Melancholic: { analytical:9,intuition:7,creative:7,assertive:4,charisma:5,diplomacy:5,ambition:6,perseverance:8,initiative:4,risk:3,perfectionism:9,adaptive:3,spiritual:8,material:4,independent:6,traditional:7,stability:3,empathy:6,optimism:2,depth:9,selfcontrol:7,extra:3,openness:7,conscient:8,agree:5,neuro:9,learning:8,focus:9,express:5,abstract:8,conflstyl:5,vitality:4,sensual:4,nurture:6,possess:8,fight:4,crisisflex:3,savings:8,finrisk:4,locus:6,authority:7,inspire:4,destroy:8,obsession:9,blindspot:7,persona:8,status:7,transit:3,longterm:9,hyperfocus:9,synthesis:6,categorical:9,hierarchy:7,vitalcap:4,neuralsens:9,healcap:5,psychosom:9,chromvuln:7 },
};

// Nakšatras → dažādas dimensijas
const NAK_SCORES = [
    { match:'Rohini',    intuition:7,empathy:9,spiritual:6,creative:6,stability:8 },
    { match:'Pushya',    spiritual:8,empathy:9,stability:8,traditional:7,focus:7 },
    { match:'Anuradha',  empathy:8,diplomacy:7,stability:7,depth:7 },
    { match:'Hasta',     analytical:8,creative:7,empathy:8,learning:7 },
    { match:'Revati',    intuition:8,spiritual:9,creative:7,empathy:8,adaptive:7 },
    { match:'Shravana',  learning:9,empathy:7,stability:7,diplomacy:7 },
    { match:'Ardra',     intuition:8,depth:8,creative:8,openness:8,risk:7,neuro:8,adaptive:7 },
    { match:'Swati',     adaptive:9,independent:8,openness:8,diplomacy:7,creative:7 },
    { match:'Ashwini',   initiative:8,risk:7,assertive:7,adaptive:7,openness:6 },
    { match:'Mrigashira',learning:8,creative:7,openness:7,intuition:6 },
    { match:'Chitra',    creative:9,charisma:8,openness:7,assertive:6 },
    { match:'Shatabhisha',intuition:8,spiritual:7,openness:7,independent:8,analytical:7 },
    { match:'Purva Phalguni', creative:8,charisma:7,openness:6,extra:6 },
    { match:'Purva Bhadrapada', depth:8,openness:7,risk:7,creative:7,neuro:6 },
    { match:'Uttara Phalguni', stability:8,empathy:7,traditional:7,focus:7 },
    { match:'Uttara Ashadha',  perseverance:8,ambition:7,traditional:7,focus:8 },
    { match:'Uttara Bhadrapada', spiritual:9,empathy:8,depth:7,stability:7 },
    { match:'Ashlesha',  intuition:8,depth:8,neuro:8,independent:6,assertive:5 },
    { match:'Jyeshtha',  assertive:7,ambition:7,depth:8,neuro:8 },
    { match:'Mula',      independent:8,risk:7,depth:8,neuro:7,spiritual:7 },
    { match:'Bharani',   depth:8,assertive:7,creative:6,neuro:7 },
    { match:'Vishakha',  ambition:8,assertive:7,depth:6,neuro:6 },
    { match:'Krittika',  assertive:8,analytical:7,neuro:6,focus:7 },
    { match:'Magha',     charisma:8,ambition:7,traditional:8,depth:6 },
    { match:'Dhanishta', creative:7,adaptive:8,independent:7,openness:6 },
    { match:'Punarvasu', adaptive:8,optimism:8,learning:7,empathy:7 },
    { match:'Purva Ashadha', optimism:8,ambition:8,creative:7,spiritual:7,focus:7 },
];

// ── PALĪGFUNKCIJAS ────────────────────────────────────────────────────────────

function sg(tbl, key, def = 5) {
    return (tbl && key !== undefined && key in tbl) ? tbl[key] : def;
}

function norm(avg, min, max) {
    return Math.round(Math.min(Math.max((avg - min) / (max - min) * 100, 5), 95));
}

function tempScore(humor, dim) {
    if (!humor) return 5;
    const matches = Object.keys(TEMP).filter(k => humor.includes(k));
    if (!matches.length) return 5;
    return matches.reduce((s, k) => s + (TEMP[k][dim] ?? 5), 0) / matches.length;
}

function nakScore(nakName, dim) {
    const hit = NAK_SCORES.find(n => nakName && nakName.includes(n.match));
    return hit?.[dim] ?? 5;
}

function elemPct(elements, ...keys) {
    const total = Object.values(elements).reduce((s, v) => s + (typeof v === 'number' ? v : 0), 0) || 100;
    return keys.reduce((s, k) => s + (elements[k] || 0), 0) / total * 10;
}

function weighted(factors) {
    const totalW = factors.reduce((s, [,, w]) => s + w, 0);
    return factors.reduce((s, [val,, w]) => s + val * w, 0) / totalW;
}

function desc(pct, hi, lo) {
    if (pct >= 65) return hi;
    if (pct <= 38) return lo;
    return '';
}

// ── GALVENĀ FUNKCIJA ──────────────────────────────────────────────────────────

export function calculatePersonality(profile) {
    // Datu ieguve
    const wp    = profile.western?.planets || {};
    const bazi  = profile.bazi || {};
    const hell  = profile.hellenistic?.data || {};
    const maya  = profile.maya_profile?.basic || {};
    const vedic = profile.vedic || {};

    const dm    = bazi.Daymaster || {};
    const dmKey = `${dm.polarity || ''} ${dm.element || ''}`.trim();
    const elems = bazi.elements || {};
    const nconf = (bazi.conflicts || []).length;
    const humor = hell.humor || '';
    const sect  = hell.sect || '';
    const nak   = vedic.nakshatra?.nakshatra || '';
    const tatva = maya.tatva || '';
    const mTone = Number(maya.tone) || 0;
    const mColor = maya.color || '';
    const yogas = vedic.yogas || [];
    const critAsp = (profile.western?.psychology?.criticalAspectsBlocks || []).length;

    // Planētu zīmes
    const sunS  = wp.Saule?.sign || '';
    const moonS = wp.Meness?.sign || '';
    const mercS = wp.Merkurs?.sign || '';
    const venS  = wp.Venera?.sign || '';
    const venD  = wp.Venera?.dignity || '';
    const marsS = wp.Marss?.sign || '';
    const jupS  = wp.Jupiters?.sign || '';
    const satS  = wp.Saturns?.sign || '';
    const uS    = wp.Urans?.sign || '';
    const nS    = wp.Neptuns?.sign || '';
    const rahuS = wp.Rahu?.sign || '';
    const ketuS = wp.Ketu?.sign || '';

    const dashaLord   = (vedic.current_dasha?.lord || '').trim();
    // Vedic whole-sign houses are authoritative for this vedic-based analysis.
    // (Vēsturiska piezīme: agrāk šeit bija komentārs, ka western getHouseFromCusps "always
    // returns 12" — tas bija nevis WASM kļūme, bet 0-vs-1 indeksācijas bugs getHouseFromCusps;
    // tas tagad SALABOTS. Vēdiskās veselzīmju mājas tik un tā ir pareizā izvēle vēdiskajai daļai.)
    const vp = vedic.planets || {};
    const getVH = p => vp[p]?.house;
    const VEDIC_HOUSE_KEYS = ['Saule','Meness','Merkurs','Venera','Marss','Jupiters','Saturns'];
    const satH  = getVH('Saturns');
    const sunH  = getVH('Saule');
    const planetsIn8  = VEDIC_HOUSE_KEYS.filter(p => getVH(p) === 8).length;
    const planetsIn9  = VEDIC_HOUSE_KEYS.filter(p => getVH(p) === 9).length;
    const planetsIn10 = VEDIC_HOUSE_KEYS.filter(p => getVH(p) === 10).length;
    const planetsIn12 = VEDIC_HOUSE_KEYS.filter(p => getVH(p) === 12).length;
    const rahuH       = getVH('Rahu');
    const rahuAngular = [1,4,7,10].includes(rahuH) ? 8 : [2,5,8,11].includes(rahuH) ? 6 : 4;

    // Antardasha lords — meklē aktīvo apakšperiodu pēc šodienas datuma
    const _now = Date.now();
    const antarLord = (vedic.current_dasha?.antardashas || [])
        .find(a => a.start && a.end &&
              new Date(a.start).getTime() <= _now &&
              _now <= new Date(a.end).getTime())
        ?.lord?.trim() || '';

    // Hirons — dziedināšanas brūce
    const chironS = wp.Hirons?.sign || '';
    const chironH = getVH('Hirons');

    // 6. mājas slodze (hroniskas saslimšanas sfēra) — vedic houses
    const planetsIn6  = VEDIC_HOUSE_KEYS.filter(p => getVH(p) === 6).length;
    const maleficsIn6 = ['Marss','Saturns'].filter(p => getVH(p) === 6).length;

    // Saturna māja bonus
    const satHB = [1,4,7,10].includes(satH) ? 8 : [2,5,8,11].includes(satH) ? 6 : 4;

    // BaZi polārs (Jaņ = aktīvāks)
    const isYang = dm.polarity === 'Jaņ';
    // yw(hi, lo) → hi ja Yang, lo ja Yin, vidējais ja nav BaZi datu
    const yw = (hi, lo) => dm.polarity ? (isYang ? hi : lo) : Math.round((hi + lo) / 2);

    // Tatva Water → intuīcija / garīgums
    const tatvaWater  = tatva.includes('Ūdens') ? 9 : tatva.includes('Koks') ? 7 : tatva.includes('Uguns') ? 5 : tatva.includes('Zeme') ? 5 : tatva.includes('Metāls') ? 3 : 5;
    const tatvaFire   = tatva.includes('Uguns') ? 9 : tatva.includes('Koks') ? 6 : tatva.includes('Metāls') ? 4 : tatva.includes('Zeme') ? 5 : tatva.includes('Ūdens') ? 3 : 5;
    const tatvaEarth  = tatva.includes('Zeme') ? 9 : tatva.includes('Metāls') ? 8 : tatva.includes('Koks') ? 5 : tatva.includes('Uguns') ? 4 : tatva.includes('Ūdens') ? 4 : 5;

    // Maiju krāsas enerģija
    const colorEnergy = mColor.includes('Sarkans') ? 8 : mColor.includes('Dzeltens') ? 7 : mColor.includes('Balts') ? 5 : mColor.includes('Zils') ? 4 : 5;

    // ═══════════════════════════════════════════════════════════════════════════
    // 1. KOGNITĪVAIS STILS
    // ═══════════════════════════════════════════════════════════════════════════

    // Analītiskā domāšana
    const analyt = norm(weighted([
        [sg(MERC.analytical, mercS),   `Merkurs ${mercS}`,  3],
        [sg(SUN.analytical,  sunS),    `Saule ${sunS}`,     2],
        [sg(SAT.analytical,  satS),    `Saturns ${satS}`,   2],
        [DM.analytical[dmKey] ?? 5,    dmKey,               2],
        [tempScore(humor, 'analytical'), humor,             2],
        [elemPct(elems,'Metāls','Zeme'), 'Metāls+Zeme',    1],
    ]), 3.2, 8.8);

    // Intuīcija
    const intuit = norm(weighted([
        [sg(MOON.intuition,  moonS),   `Mēness ${moonS}`,   3],
        [DM.intuition[dmKey] ?? 5,     dmKey,                2],
        [elemPct(elems,'Ūdens','Koks'), 'Ūdens+Koks',       2],
        [tatvaWater,                   tatva,                2],
        [sg(NEPT_SPIRIT, nS),          `Neptūns ${nS}`,     2],
        [nakScore(nak, 'intuition'),   nak,                  2],
        [tempScore(humor, 'intuition'), humor,               1],
    ]), 3.0, 8.5);

    // Radošums
    const creat = norm(weighted([
        [sg(SUN.creative, sunS),       `Saule ${sunS}`,     2],
        [sg(VEN_SIGN.creative, venS),  `Venera ${venS}`,    2],
        [VEN_DIG.creative[venD] ?? 6,  venD,                2],
        [nakScore(nak, 'creative'),    nak,                  2],
        [tempScore(humor, 'creative'), humor,               2],
        [TONE_OPEN[mTone] ?? 5,        `Tonis ${mTone}`,   1],
        [elemPct(elems,'Ūdens','Koks'), 'Ūdens+Koks',      1],
    ]), 3.2, 8.8);

    // Koncentrēšanās spēja
    const focus = norm(weighted([
        [satHB,                        `Saturns ${satH}.māja`, 3],
        [sg(SAT.analytical, satS),     `Saturns ${satS}`,   2],
        [DM.perseverance[dmKey] ?? 5,  dmKey,               2],
        [tempScore(humor, 'focus'),    humor,               2],
        [elemPct(elems,'Metāls'),      'Metāls',            2],
        [sg(SUN.perseverance, sunS),   `Saule ${sunS}`,     1],
    ]), 3.0, 8.8);

    // Mācīšanās spēja
    const learn = norm(weighted([
        [sg(MERC.learning, mercS),     `Merkurs ${mercS}`,  3],
        [sg(JUP.learning, jupS),       `Jupiters ${jupS}`,  3],
        [tempScore(humor, 'learning'),  humor,              2],
        [nakScore(nak, 'learning'),    nak,                  2],
        [elemPct(elems,'Ūdens','Koks'), 'Ūdens+Koks',      2],
    ]), 3.2, 8.8);

    // ═══════════════════════════════════════════════════════════════════════════
    // 2. EMOCIONĀLAIS PROFILS
    // ═══════════════════════════════════════════════════════════════════════════

    // Emocionālā stabilitāte
    const stab = norm(weighted([
        [sg(MOON.stability,  moonS),   `Mēness ${moonS}`,   3],
        [nconf === 0 ? 9 : nconf === 1 ? 5 : 2, `${nconf} konflikti`, 2],
        [sg(SAT.selfcontrol, satS),    `Saturns ${satS}`,   2],
        [tempScore(humor, 'stability'), humor,              2],
        [elemPct(elems,'Metāls','Zeme'), 'Metāls+Zeme',    2],
        [yw(6, 7),               dm.polarity,         1],
    ]), 2.8, 8.6);

    // Empātija
    const empat = norm(weighted([
        [sg(MOON.empathy,    moonS),   `Mēness ${moonS}`,   3],
        [sg(VEN_SIGN.agree,  venS),    `Venera ${venS}`,    2],
        [VEN_DIG.agree[venD] ?? 6,     venD,                1],
        [elemPct(elems,'Ūdens','Koks'), 'Ūdens+Koks',      2],
        [tatvaWater,                   tatva,               2],
        [tempScore(humor, 'empathy'),  humor,               2],
        [nakScore(nak, 'empathy'),     nak,                  2],
    ]), 2.8, 9.2);

    // Optimisms
    const optim = norm(weighted([
        [sg(SUN.optimism,    sunS),    `Saule ${sunS}`,     2],
        [sg(MOON.optimism,   moonS),   `Mēness ${moonS}`,   2],
        [sg(JUP.optimism,    jupS),    `Jupiters ${jupS}`,  3],
        [tempScore(humor, 'optimism'), humor,               3],
        [sect === 'Diena' ? 7 : sect === 'Nakts' ? 4 : 5, sect, 2],
    ]), 3.0, 9.0);

    // Emocionālā dziļums
    const depth = norm(weighted([
        [sg(MOON.depth,      moonS),   `Mēness ${moonS}`,   3],
        [nakScore(nak, 'depth'),       nak,                  2],
        [elemPct(elems,'Ūdens'),       'Ūdens',             2],
        [tatvaWater,                   tatva,               2],
        [tempScore(humor, 'depth'),    humor,               2],
        [wp.Plutons ? sg({Skorpions:9,Vēzis:8,Auns:7,Ūdensvīrs:6,Lauva:5,Zivis:7,Jaunava:5,Mežāzis:5,Strēlnieks:4,Svari:4,Dvīņi:4,Vērsis:4}, wp.Plutons.sign, 5) : 5, `Plutons`, 1],
    ]), 3.0, 8.8);

    // Paškontrole
    const selfC = norm(weighted([
        [sg(SAT.selfcontrol, satS),    `Saturns ${satS}`,   3],
        [satHB,                        `Saturns ${satH}.māja`, 2],
        [DM.perseverance[dmKey] ?? 5,  dmKey,               2],
        [tempScore(humor, 'selfcontrol'), humor,            3],
        [elemPct(elems,'Metāls'),      'Metāls',            2],
    ]), 3.0, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 3. SOCIĀLĀ DINAMIKA
    // ═══════════════════════════════════════════════════════════════════════════

    // Sabiedriskums / Ekstraversija
    const social = norm(weighted([
        [sg(SUN.extra,       sunS),    `Saule ${sunS}`,     3],
        [sg(MOON.extra,      moonS),   `Mēness ${moonS}`,   2],
        [tempScore(humor, 'extra'),    humor,               3],
        [sect === 'Diena' ? 8 : sect === 'Nakts' ? 4 : 5, sect, 2],
        [colorEnergy,                  mColor.split(' ')[0],1],
        [elemPct(elems,'Uguns','Koks'), 'Uguns+Koks',      1],
    ]), 2.8, 9.0);

    // Asertivitāte
    const asser = norm(weighted([
        [sg(MARS.assertive,  marsS),   `Marss ${marsS}`,    3],
        [sg(SUN.assertive,   sunS),    `Saule ${sunS}`,     2],
        [yw(8, 3),               dm.polarity,         2],
        [tempScore(humor, 'assertive'), humor,              2],
        [DM.assertive[dmKey] ?? 5,     dmKey,               2],
        [tatvaFire,                    tatva,               1],
    ]), 3.0, 9.0);

    // Harisma
    const charm = norm(weighted([
        [sg(SUN.charisma,    sunS),    `Saule ${sunS}`,     3],
        [VEN_DIG.charisma[venD] ?? 6,  venD,                2],
        [sg(JUP.charisma,    jupS),    `Jupiters ${jupS}`,  2],
        [tempScore(humor, 'charisma'), humor,               2],
        [nakScore(nak, 'charisma'),    nak,                  2],
        [colorEnergy,                  mColor.split(' ')[0],1],
    ]), 3.0, 9.0);

    // Diplomātija
    const diplo = norm(weighted([
        [sg(VEN_SIGN.diplomacy, venS), `Venera ${venS}`,    3],
        [VEN_DIG.diplomacy[venD] ?? 6, venD,                1],
        [sg(MOON.diplomacy,  moonS),   `Mēness ${moonS}`,   3],
        [tempScore(humor, 'diplomacy'), humor,              2],
        [(nakScore(nak, 'diplomacy') + nakScore(nak, 'empathy')) / 2, nak, 2],
        [yw(4, 7),               dm.polarity,         1],
    ]), 2.8, 9.2);

    // Uzticamība / Lojalitāte
    const loyal = norm(weighted([
        [sg(SUN.perseverance, sunS),   `Saule ${sunS}`,     2],
        [sg(SAT.perseverance, satS),   `Saturns ${satS}`,   2],
        [DM.perseverance[dmKey] ?? 5,  dmKey,               2],
        [elemPct(elems,'Metāls','Zeme'), 'Metāls+Zeme',    2],
        [(tempScore(humor, 'perseverance') + tempScore(humor, 'selfcontrol')) / 2, humor, 2],
        [nconf === 0 ? 8 : nconf === 1 ? 6 : 3, `${nconf} konfl.`, 2],
    ]), 3.0, 8.8);

    // ═══════════════════════════════════════════════════════════════════════════
    // 4. DARBASPĒKS & MOTIVĀCIJA
    // ═══════════════════════════════════════════════════════════════════════════

    // Ambīcija
    const ambit = norm(weighted([
        [sg(SAT.ambition,    satS),    `Saturns ${satS}`,   2],
        [sg(MARS.ambition,   marsS),   `Marss ${marsS}`,    2],
        [sg(SUN.ambition,    sunS),    `Saule ${sunS}`,     2],
        [DM.ambition[dmKey] ?? 5,      dmKey,               2],
        [tempScore(humor, 'ambition'), humor,               2],
        [yw(7, 4),               dm.polarity,         1],
        [yogas.some(y => y.type?.includes('Vara') || y.type?.includes('Statuss')) ? 8 : 5, 'Yogas', 1],
    ]), 3.0, 8.8);

    // Neatlaidība
    const persi = norm(weighted([
        [sg(SUN.perseverance, sunS),   `Saule ${sunS}`,     2],
        [sg(SAT.perseverance, satS),   `Saturns ${satS}`,   2],
        [satHB,                        `Saturns ${satH}.māja`, 1],
        [DM.perseverance[dmKey] ?? 5,  dmKey,               2],
        [elemPct(elems,'Metāls'),      'Metāls',            2],
        [tempScore(humor, 'perseverance'), humor,           2],
        [nakScore(nak, 'perseverance') || 5, nak,           1],
    ]), 3.0, 8.8);

    // Iniciatīva
    const initi = norm(weighted([
        [sg(SUN.initiative,  sunS),    `Saule ${sunS}`,     2],
        [sg(MARS.initiative, marsS),   `Marss ${marsS}`,    3],
        [yw(8, 3),               dm.polarity,         2],
        [tempScore(humor, 'initiative'), humor,             2],
        [nakScore(nak, 'initiative'),  nak,                  1],
        [colorEnergy,                  mColor.split(' ')[0],1],
    ]), 2.8, 9.0);

    // Risku tolerance
    const riskT = norm(weighted([
        [sg(MARS.risk,       marsS),   `Marss ${marsS}`,    3],
        [sg(JUP.risk,        jupS),    `Jupiters ${jupS}`,  2],
        [sg(SUN.risk,        sunS),    `Saule ${sunS}`,     2],
        [tempScore(humor, 'risk'),     humor,               2],
        [elemPct(elems,'Uguns'),       'Uguns',             2],
        [nakScore(nak, 'risk'),        nak,                  1],
    ]), 2.8, 9.0);

    // Perfekcionisms
    const perfe = norm(weighted([
        [sg(SUN.perfectionism, sunS),  `Saule ${sunS}`,     2],
        [sg(SAT.perfectionism, satS),  `Saturns ${satS}`,   2],
        [sg(MARS.perfectionism, marsS),`Marss ${marsS}`,    1],
        [DM.perfectionism[dmKey] ?? 5, dmKey,               2],
        [elemPct(elems,'Metāls'),      'Metāls',            2],
        [tempScore(humor, 'perfectionism'), humor,          3],
    ]), 3.0, 9.2);

    // Pielāgošanās spēja
    const adapt = norm(weighted([
        [sg(SUN.adaptability, sunS),   `Saule ${sunS}`,     2],
        [DM.adaptive[dmKey] ?? 5,      dmKey,               2],
        [elemPct(elems,'Ūdens','Koks'), 'Ūdens+Koks',      2],
        [tempScore(humor, 'adaptive'), humor,               2],
        [nakScore(nak, 'adaptive'),    nak,                  2],
        [Math.min((bazi.combinations || []).length * 2 + 3, 9), 'BaZi kombin.', 1],
    ]), 3.0, 8.8);

    // ═══════════════════════════════════════════════════════════════════════════
    // 5. VĒRTĪBU SISTĒMA
    // ═══════════════════════════════════════════════════════════════════════════

    // Garīgums
    const spirt = norm(weighted([
        [sg(MOON.spiritual,  moonS),   `Mēness ${moonS}`,   2],
        [sg(SUN.spiritual,   sunS),    `Saule ${sunS}`,     2],
        [sg(NEPT_SPIRIT,     nS),      `Neptūns ${nS}`,     2],
        [sg(KETU_SPIRIT,     ketuS),   `Ketu ${ketuS}`,     2],
        [elemPct(elems,'Ūdens'),       'Ūdens',             2],
        [tempScore(humor, 'spiritual'), humor,              2],
        [yogas.filter(y => y.type?.includes('Garīg') || y.name?.includes('Hamsa')).length * 3 + 4, 'Yogas', 1],
        [getVH('Neptuns') === 12 || getVH('Neptuns') === 4 ? 8 : 5, 'Nept.māja', 1],
    ]), 3.0, 9.0);

    // Materiālā orientācija
    const mater = norm(weighted([
        [sg(SUN.material,    sunS),    `Saule ${sunS}`,     2],
        [DM.material[dmKey] ?? 5,      dmKey,               2],
        [elemPct(elems,'Zeme','Metāls'), 'Zeme+Metāls',    3],
        [tatvaEarth,                   tatva,               2],
        [tempScore(humor, 'material'), humor,               2],
        [nakScore(nak, 'material'), nak, 1],
    ]), 3.0, 9.0);

    // Neatkarība
    const indep = norm(weighted([
        [sg(SUN.independent, sunS),    `Saule ${sunS}`,     2],
        [sg(URAN_INDEP,      uS),      `Urans ${uS}`,       2],
        [DM.independent[dmKey] ?? 5,   dmKey,               2],
        [yw(7, 4),               dm.polarity,         2],
        [elemPct(elems,'Uguns','Koks'), 'Uguns+Koks',       2],
        [tempScore(humor, 'independent'), humor,            2],
    ]), 3.0, 8.8);

    // Tradicionālisms
    const tradi = norm(weighted([
        [sg(SUN.traditional, sunS),    `Saule ${sunS}`,     2],
        [sg(SAT.traditional, satS),    `Saturns ${satS}`,   3],
        [DM.traditional[dmKey] ?? 5,   dmKey,               2],
        [elemPct(elems,'Zeme','Metāls'), 'Zeme+Metāls',    2],
        [tempScore(humor, 'traditional'), humor,            2],
        [yw(4, 7),               dm.polarity,         1],
    ]), 3.0, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 6. BIG FIVE (OCEAN)
    // ═══════════════════════════════════════════════════════════════════════════

    const bf = calculateBigFive(profile);

    // Neirotisms — no big_five.js (bf.N), NEVIS lokāli pārrēķināts (bija dublēta formula
    // ar citiem svariem un veco cieto normalizePct clamp — divergēja no big_five.js).
    const neuro = bf.N?.pct ?? 50;

    // ═══════════════════════════════════════════════════════════════════════════
    // 7. KOMUNIKĀCIJAS UN UZTVERES STILS
    // ═══════════════════════════════════════════════════════════════════════════

    // Ekspresivitāte / Tiešums
    const expres = norm(weighted([
        [sg(MERC_EXPRESS,   mercS),         `Merkurs ${mercS}`,    3],
        [sg(MARS.assertive, marsS),         `Marss ${marsS}`,      2],
        [yw(7, 4),                    dm.polarity,           2],
        [tempScore(humor, 'express'),       humor,                 2],
        [elemPct(elems,'Uguns','Koks'),     'Uguns+Koks',          1],
    ]), 3.0, 9.0);

    // Konceptuālā Uztvere (Abstrakts ↔ Konkrēts)
    const abstr = norm(weighted([
        [sg(MERC_ABSTRACT,  mercS),         `Merkurs ${mercS}`,    3],
        [sg(SUN.creative,   sunS),          `Saule ${sunS}`,       2],
        [tempScore(humor, 'abstract'),      humor,                 2],
        [elemPct(elems,'Uguns','Koks'),     'Uguns+Koks',          2],
        [sg(JUP.openness,   jupS),          `Jupiters ${jupS}`,    1],
    ]), 3.0, 8.8);

    // Konfliktu Risināšanas Stils (Konfrontācija ↔ Izvairīšanās)
    const conflS = norm(weighted([
        [sg(MERC_CONFLICT,  mercS),         `Merkurs ${mercS}`,    2],
        [sg(MARS.assertive, marsS),         `Marss ${marsS}`,      3],
        [tempScore(humor, 'conflstyl'),     humor,                 2],
        [sg(SUN.assertive,  sunS),          `Saule ${sunS}`,       2],
        [yw(7, 4),                    dm.polarity,           1],
    ]), 2.8, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 8. ENERĢĒTIKA UN FIZISKĀ DINAMIKA
    // ═══════════════════════════════════════════════════════════════════════════

    // Fiziskā Vitalitāte
    const vital = norm(weighted([
        [sg(SUN_VITAL,  sunS),              `Saule ${sunS}`,       3],
        [sg(MARS_VITAL, marsS),             `Marss ${marsS}`,      2],
        [tatvaFire,                         tatva,                 2],
        [elemPct(elems,'Uguns','Koks'),     'Uguns+Koks',          2],
        [tempScore(humor, 'vitality'),      humor,                 2],
        [sect === 'Diena' ? 8 : 5,          sect,                  1],
    ]), 3.0, 9.0);

    // Diennakts Ritms (Cīrulis ↔ Pūce)
    const circa = norm(weighted([
        [sect === 'Diena' ? 9 : sect === 'Nakts' ? 2 : 5, sect,    4],
        [sg(SUN_CIRCADIAN,  sunS),          `Saule ${sunS}`,       2],
        [sg(MOON_CIRCADIAN, moonS),         `Mēness ${moonS}`,     2],
        [yw(7, 4),                    dm.polarity,           2],
    ]), 2.0, 8.5);

    // Atjaunošanās Spēja (Resilience)
    const resil = norm(weighted([
        [sg(MOON_RESIL,      moonS),        `Mēness ${moonS}`,     3],
        [sg(SAT.selfcontrol, satS),         `Saturns ${satS}`,     2],
        [elemPct(elems,'Metāls','Zeme'),    'Metāls+Zeme',         2],
        [nconf === 0 ? 8 : nconf === 1 ? 5 : 2, `${nconf} konfl.`,2],
        [tempScore(humor, 'perseverance'),  humor,                 2],
        [yw(7, 6),                    dm.polarity,           1],
    ]), 2.8, 8.6);

    // ═══════════════════════════════════════════════════════════════════════════
    // 9. ATTIECĪBU UN INTIMITĀTES DINAMIKA
    // ═══════════════════════════════════════════════════════════════════════════

    // Piesaistes Drošība (Droša ↔ Trauksmaini/Izvairīga)
    const attSec = norm(weighted([
        [sg(MOON_ATTACH,    moonS),         `Mēness ${moonS}`,     3],
        [VEN_DIG.agree[venD] ?? 6,          venD,                  2],
        [nconf === 0 ? 8 : nconf === 1 ? 5 : 2, `${nconf} konfl.`,2],
        [tempScore(humor, 'empathy'),       humor,                 2],
        [yw(5, 7),                    dm.polarity,           1],
        [nakScore(nak, 'empathy'),          nak,                    2],
    ]), 2.8, 8.8);

    // Jutekliskums (Fiziskā Tuvuma Vajadzība)
    const sensu = norm(weighted([
        [sg(VEN_SENSUAL,  venS),            `Venera ${venS}`,      3],
        [sg(MOON_SENSUAL, moonS),           `Mēness ${moonS}`,     2],
        [elemPct(elems,'Zeme'),             'Zeme',                2],
        [tatvaEarth,                        tatva,                 2],
        [tempScore(humor, 'sensual'),       humor,                 1],
    ]), 2.8, 9.0);

    // Rūpju Izrādīšana (Nurturing)
    const nurtu = norm(weighted([
        [sg(MOON_NURTURE, moonS),           `Mēness ${moonS}`,     3],
        [sg(VEN_NURTURE,  venS),            `Venera ${venS}`,      2],
        [elemPct(elems,'Ūdens','Koks'),     'Ūdens+Koks',          2],
        [tatvaWater,                        tatva,                  2],
        [tempScore(humor, 'nurture'),       humor,                  2],
        [nakScore(nak, 'empathy'),          nak,                    1],
    ]), 2.8, 9.0);

    // Greizsirdība / Īpašnieciskums
    const poss = norm(weighted([
        [sg(MARS_POSSESS, marsS),           `Marss ${marsS}`,      3],
        [sg(MOON_POSSESS, moonS),           `Mēness ${moonS}`,     2],
        [nconf === 0 ? 3 : nconf === 1 ? 6 : 9, `${nconf} konfl.`,2],
        [tempScore(humor, 'possess'),       humor,                  2],
        [['Vērsis','Lauva','Skorpions','Ūdensvīrs'].includes(sunS) ? 7 : 4, 'Fikss', 1],
    ]), 2.5, 8.5);

    // ═══════════════════════════════════════════════════════════════════════════
    // 10. KRĪZES UN STRESA VADĪBA
    // ═══════════════════════════════════════════════════════════════════════════

    // Stresa Reakcija: Cīņa (Fight) ↔ Sastingšana (Freeze)
    const fight = norm(weighted([
        [sg(MARS.assertive, marsS),         `Marss ${marsS}`,      3],
        [sg(SUN_FIGHT,      sunS),          `Saule ${sunS}`,       2],
        [tempScore(humor, 'fight'),         humor,                  2],
        [yw(7, 4),                    dm.polarity,           2],
        [elemPct(elems,'Uguns','Metāls'),   'Uguns+Metāls',        1],
    ]), 3.0, 9.0);

    // Elastība Krīzē (Pielāgojas ↔ Stīvums)
    const crFlex = norm(weighted([
        [sg(SUN_CRISIS,    sunS),           `Saule ${sunS}`,       2],
        [DM.adaptive[dmKey] ?? 5,           dmKey,                 2],
        [elemPct(elems,'Ūdens','Koks'),     'Ūdens+Koks',          2],
        [tempScore(humor, 'crisisflex'),    humor,                  2],
        [nakScore(nak, 'adaptive'),         nak,                    2],
    ]), 3.0, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 11. RESURSU UN FINANŠU VADĪBA
    // ═══════════════════════════════════════════════════════════════════════════

    // Finanšu Disciplīna (Uzkrāšana ↔ Tēriņš)
    const savng = norm(weighted([
        [sg(SAT_SAVINGS, satS),             `Saturns ${satS}`,     2],
        [DM_SAVINGS[dmKey] ?? 5,            dmKey,                 2],
        [sg(VEN_SAVINGS, venS),             `Venera ${venS}`,      2],
        [elemPct(elems,'Zeme','Metāls'),    'Zeme+Metāls',         2],
        [tempScore(humor, 'savings'),       humor,                  2],
        [tatvaEarth,                        tatva,                  1],
    ]), 2.8, 9.0);

    // Finansiālā Risku Apetīte
    const finRsk = norm(weighted([
        [sg(JUP_FINRISK, jupS),             `Jupiters ${jupS}`,    3],
        [sg(MARS.risk,   marsS),            `Marss ${marsS}`,      2],
        [sg(SUN.risk,    sunS),             `Saule ${sunS}`,       2],
        [elemPct(elems,'Uguns'),            'Uguns',               2],
        [tempScore(humor, 'finrisk'),       humor,                  1],
    ]), 2.8, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 12. DZĪVES CEĻŠ UN VADĪBAS STILS
    // ═══════════════════════════════════════════════════════════════════════════

    // Iekšējais Kontroles Lokuss (Pats veido likteni ↔ Paļaujas uz likteni)
    const locus = norm(weighted([
        [sg(SUN_LOCUS,       sunS),         `Saule ${sunS}`,       2],
        [sg(MARS.initiative, marsS),        `Marss ${marsS}`,      2],
        [yw(8, 4),                    dm.polarity,           2],
        [tempScore(humor, 'locus'),         humor,                  2],
        [10 - sg(NEPT_SPIRIT, nS),          `Neptūns (inv)`,       1],
        [elemPct(elems,'Uguns','Metāls'),   'Uguns+Metāls',        1],
    ]), 3.0, 9.0);

    // Autoritārā Vadība (Direktīvs ↔ Demokrātisks)
    const authr = norm(weighted([
        [sg(SUN_AUTH,         sunS),        `Saule ${sunS}`,       3],
        [sg(SAT.traditional,  satS),        `Saturns ${satS}`,     2],
        [yw(8, 4),                    dm.polarity,           2],
        [tempScore(humor, 'authority'),     humor,                  2],
        [['Vērsis','Lauva','Skorpions','Ūdensvīrs'].includes(sunS) ? 7 : 4, 'Fikss', 1],
    ]), 3.0, 9.0);

    // Iedvesmas Vadība (Vīzionārs ↔ Praktiskais)
    const inspr = norm(weighted([
        [sg(JUP_INSPIRE, jupS),             `Jupiters ${jupS}`,    3],
        [sg(SUN_INSPIRE, sunS),             `Saule ${sunS}`,       2],
        [VEN_DIG.charisma[venD] ?? 6,       venD,                  1],
        [tempScore(humor, 'inspire'),       humor,                  2],
        [nakScore(nak, 'charisma'),         nak,                    1],
        [tatvaFire,                         tatva,                  1],
        [dm.polarity ? ((isYang && (dm.element === 'Uguns' || dm.element === 'Koks')) ? 8 : isYang ? 6 : 4) : 6, 'DM', 2],
    ]), 3.0, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 13. ĒNAS PUSE UN PAŠSABOTĀŽAS MEHĀNISMI
    // ═══════════════════════════════════════════════════════════════════════════

    // Destruktīvie Paterni (pašsabotāžas biežums)
    const destr = norm(weighted([
        [sg(KETU_SHADOW, ketuS),                             `Ketu ${ketuS}`,      3],
        [critAsp > 0 ? Math.min(critAsp * 2.5 + 3, 10) : 2, 'Kritiskie asp.',     2],
        [nconf === 0 ? 2 : nconf === 1 ? 6 : 9,             `${nconf} konfl.`,    2],
        [tempScore(humor, 'destroy'),                         humor,               2],
        [sg(MOON.neuro, moonS),                              `Mēness ${moonS}`,   1],
    ]), 2.5, 8.5);

    // Obsesivitāte / Slēptās Atkarības
    const obsss = norm(weighted([
        [sg(RAHU_NEURO,  rahuS),                             `Rahu ${rahuS}`,     2],
        [wp.Plutons ? sg(PLUT_OBSESS, wp.Plutons.sign, 5) : 5, 'Plutons',         2],
        [tempScore(humor, 'obsession'),                       humor,               3],
        [sg(MOON.depth,  moonS),                             `Mēness ${moonS}`,   1],
        [elemPct(elems,'Ūdens'),                              'Ūdens',             2],
    ]), 2.5, 8.5);

    // Kognitīvie Aklie Plankumi (BaZi elementu imbalance + iztrūkumi)
    const elemVals = ['Uguns','Zeme','Metāls','Ūdens','Koks'].map(k => Number(elems[k]) || 0);
    const elemTot  = elemVals.reduce((a, b) => a + b, 0) || 100;
    const elemConc = Math.max(...elemVals) / elemTot * 10;
    const missingE = elemVals.filter(v => v / elemTot < 0.05).length;

    const blind = norm(weighted([
        [Math.min(elemConc * 0.5 + missingE * 1.2 + 1, 9.5), 'BaZi elem.',       3],
        [Math.min(nconf * 1.5 + 3, 9),                        `${nconf} konfl.`,  2],
        [tempScore(humor, 'blindspot'),                        humor,              2],
        [sg(MERC.analytical, mercS) >= 7 ? 3 : sg(MERC.analytical, mercS) <= 4 ? 7 : 5, `Merc ${mercS}`, 3],
    ]), 2.0, 8.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 14. IDENTITĀTES DUALITĀTE (FASĀDE PRET BŪTĪBU)
    // ═══════════════════════════════════════════════════════════════════════════

    // Sociālās Maskas Biezums
    const maskT = norm(weighted([
        [sg(SUN_PERSONA,  sunS),          `Saule ${sunS}`,    3],
        [sg(MOON_PRIVACY, moonS),         `Mēness ${moonS}`,  3],
        [tempScore(humor, 'persona'),     humor,              2],
        [Math.min(nconf * 1.5 + 2, 8),   `${nconf} konfl.`,  1],
        [yw(4, 6),                  dm.polarity,        1],
    ]), 3.0, 8.5);

    // Statusa un Atzinības Izsalkums
    const statUs = norm(weighted([
        [sg(SUN_STATUS,       sunS),      `Saule ${sunS}`,        3],
        [satHB,                           `Saturns ${satH}.māja`, 2],
        [DM.ambition[dmKey] ?? 5,         dmKey,                  2],
        [yw(7, 4),                  dm.polarity,            2],
        [tempScore(humor, 'status'),      humor,                  2],
        [yogas.some(y => y.type?.includes('Vara') || y.type?.includes('Statuss')) ? 8 : 5, 'Yogas', 1],
    ]), 3.0, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 15. LAIKA UZTVERE UN ATTĪSTĪBAS DINAMIKA
    // ═══════════════════════════════════════════════════════════════════════════

    // Pārmaiņu Adaptācija (lieli dzīves pagriezieni — Dasha, BaZi pīlāri)
    const chAdpt = norm(weighted([
        [sg(SUN_TRANSIT,  sunS),                              `Saule ${sunS}`,    2],
        [sg(MOON_TRANSIT, moonS),                             `Mēness ${moonS}`,  2],
        [10 - sg(RAHU_NEURO, rahuS),                          `Rahu (inv) ${rahuS}`, 2],
        [Math.min((bazi.combinations || []).length * 2 + 3, 9), 'BaZi kombin.',     1],
        [tempScore(humor, 'transit'),                          humor,              3],
    ]), 3.0, 9.0);

    // Ilgtermiņa Domāšana un Pacietība (Garais Spēle)
    const lterm = norm(weighted([
        [sg(SUN_LONGTERM, sunS),          `Saule ${sunS}`,        2],
        [sg(SAT_LONGTERM, satS),          `Saturns ${satS}`,      3],
        [satHB,                           `Saturns ${satH}.māja`, 1],
        [DM.perseverance[dmKey] ?? 5,     dmKey,                  2],
        [tempScore(humor, 'longterm'),    humor,                  2],
        [elemPct(elems,'Metāls','Zeme'),  'Metāls+Zeme',          2],
    ]), 3.0, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 16. TALANTU UN "ĢĒNIJA" POTENCIĀLS
    // ═══════════════════════════════════════════════════════════════════════════

    // Hiperfokusa Zonas (specializācijas dziļums un ekspertīzes potenciāls)
    const yogaCount = yogas.length;

    const hypFoc = norm(weighted([
        [sg(SUN_HYPERFOCUS, sunS),                    `Saule ${sunS}`,   2],
        [Math.min(yogaCount * 1.5 + 3, 10),           'Yogas',           3],
        [Math.max(elemConc, 2),                        'Elem. konc.',     2],
        [tempScore(humor, 'hyperfocus'),               humor,             2],
        [nakScore(nak, 'focus'),                       nak,               1],
    ]), 2.5, 9.0);

    // Sintezēšanas Spēja (savienot dažādas jomas un domāt interdisciplināri)
    const synth = norm(weighted([
        [sg(MERC_SYNTH, mercS),           `Merkurs ${mercS}`,     3],
        [sg(JUP_SYNTH,  jupS),            `Jupiters ${jupS}`,     2],
        [sg(URAN_INDEP, uS),              `Urāns ${uS}`,          2],
        [elemPct(elems,'Ūdens','Koks'),   'Ūdens+Koks',           1],
        [tempScore(humor, 'synthesis'),   humor,                  2],
        [yogaCount >= 2 ? 7 : 5,          'Yogas',                1],
        [nakScore(nak, 'learning'),       nak,                    1],
    ]), 3.0, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 17. MORĀLAIS KOMPASS UN VĒRTĪBU NEELASTĪBA
    // ═══════════════════════════════════════════════════════════════════════════

    // Kategoriskums (melnbaltā domāšana — principi vs. niansētā uztvere)
    const categ = norm(weighted([
        [sg(SUN_CATEG,    sunS),          `Saule ${sunS}`,        2],
        [sg(SAT_CATEG,    satS),          `Saturns ${satS}`,      2],
        [DM.traditional[dmKey] ?? 5,      dmKey,                  2],
        [elemPct(elems,'Metāls'),         'Metāls',               2],
        [tempScore(humor, 'categorical'), humor,                  2],
    ]), 3.0, 9.0);

    // Autoritāšu Uztvere (Cieņa pret hierarhiju ↔ Dumpinieciskums)
    const hierRsp = norm(weighted([
        [sg(SUN_HIERARCHY, sunS),         `Saule ${sunS}`,        3],
        [sg(SAT_HIERARCHY, satS),         `Saturns ${satS}`,      2],
        [10 - sg(URAN_INDEP, uS),         `Urāns (inv)`,          2],
        [yw(5, 7),                  dm.polarity,            1],
        [tempScore(humor, 'hierarchy'),   humor,                  2],
    ]), 2.8, 8.8);

    // ═══════════════════════════════════════════════════════════════════════════
    // 18. KARMISKĀ ATTĪSTĪBA
    // ═══════════════════════════════════════════════════════════════════════════

    const soulDir = norm(weighted([
        [sg(RAHU_SOUL, rahuS),                     `Rahu ${rahuS}`,     3],
        [rahuAngular,                               `Rahu ${rahuH}.māja`,2],
        [tempScore(humor, 'locus'),                 humor,               2],
        [yw(7, 5),                            dm.polarity,         1],
        [Math.min(yogaCount * 1.5 + 3, 10),        'Yogas',             2],
    ]), 2.5, 8.5);

    const karmicBurd = norm(weighted([
        [Math.min(planetsIn12 * 2.5 + 2, 9),       '12. mājas pl.',     3],
        [sg(KETU_SHADOW, ketuS),                    `Ketu ${ketuS}`,     2],
        [Math.min(nconf * 2 + 3, 10),               `${nconf} konfl.`,   2],
        [Math.min(critAsp * 2 + 2, 10),             'Kritiskie asp.',    2],
        [tempScore(humor, 'destroy'),                humor,               1],
    ]), 2.0, 8.5);

    const pastTal = norm(weighted([
        [sg(KETU_SPIRIT, ketuS),                    `Ketu ${ketuS}`,     3],
        [sg(KETU_TALENT, ketuS),                    `Ketu ${ketuS}`,     2],
        [Math.min(yogaCount * 1.5 + 3, 10),        'Yogas',             2],
        [nakScore(nak, 'spiritual'),                 nak,                 2],
        [tempScore(humor, 'hyperfocus'),             humor,               1],
    ]), 3.0, 9.0);

    // ═══════════════════════════════════════════════════════════════════════════
    // 19. MĀJU FOKUSS
    // ═══════════════════════════════════════════════════════════════════════════

    const h8score = norm(weighted([
        [Math.min(planetsIn8 * 2.5 + 2, 9),        '8. mājas pl.',      3],
        [sg(MOON.depth, moonS),                     `Mēness ${moonS}`,   2],
        [wp.Plutons ? sg(PLUT_OBSESS, wp.Plutons.sign, 5) : 5, 'Plutons',2],
        [tempScore(humor, 'depth'),                  humor,               2],
        [Math.min(nconf * 1.5 + 3, 9),              `${nconf} konfl.`,   1],
    ]), 2.0, 8.5);

    const h9score = norm(weighted([
        [Math.min(planetsIn9 * 2.5 + 2, 9),        '9. mājas pl.',      3],
        [sg(JUP.openness, jupS),                    `Jupiters ${jupS}`,  2],
        [getVH('Jupiters') === 9 ? 9 : ([1,4,7,10].includes(getVH('Jupiters')) ? 7 : 5), 'Jup. māja', 2],
        [Math.min(yogaCount * 1.5 + 3, 10),        'Yogas',             2],
        [tempScore(humor, 'abstract'),               humor,               1],
    ]), 2.0, 8.5);

    const h10score = norm(weighted([
        [Math.min(planetsIn10 * 2.5 + 2, 9),       '10. mājas pl.',     4],
        [sunH === 10 ? 10 : ([1,4,7].includes(sunH) ? 7 : 4), `Saule ${sunH}.māja`, 3],
        [satH === 10 ? 9 : ([1,4,7].includes(satH) ? 7 : 4), `Saturns ${satH ?? '?'}.māja`, 2],
        [tempScore(humor, 'ambition'),               humor,               1],
    ]), 2.0, 8.5);

    // ═══════════════════════════════════════════════════════════════════════════
    // 20. MEDICĪNISKĀ ASTROLOĢIJA
    // ═══════════════════════════════════════════════════════════════════════════

    const vitalCap = norm(weighted([
        [sg(SUN_VITALCAP, sunS),                    `Saule ${sunS}`,     3],
        [sg(MARS_VITAL, marsS),                     `Marss ${marsS}`,    2],
        [elemPct(elems,'Uguns'),                    'Uguns',             2],
        [tatvaFire,                                  tatva,               2],
        [tempScore(humor, 'vitalcap'),               humor,               2],
        [sect === 'Diena' ? 8 : 5,                  sect,                1],
    ]), 3.0, 9.0);

    const neuralSens = norm(weighted([
        [sg(MERC_NEURAL, mercS),                    `Merkurs ${mercS}`,  3],
        [tempScore(humor, 'neuralsens'),             humor,               2],
        [elemPct(elems,'Koks','Ūdens'),             'Koks+Ūdens',        2],
        [Math.min(nconf * 1.5 + 3, 9),              `${nconf} konfl.`,   1],
        [sg(RAHU_NEURO, rahuS),                     `Rahu ${rahuS}`,     2],
    ]), 3.0, 9.0);

    const healCap = norm(weighted([
        [sg(SAT_IMMUNITY, satS),                    `Saturns ${satS}`,   3],
        [sg(MOON.stability, moonS),                 `Mēness ${moonS}`,   2],
        [elemPct(elems,'Metāls','Zeme'),            'Metāls+Zeme',       2],
        [tatvaEarth,                                 tatva,               2],
        [tempScore(humor, 'healcap'),                humor,               2],
        [nconf === 0 ? 8 : nconf === 1 ? 5 : 2,    'Konfl.',            1],
    ]), 3.0, 9.0);

    // Hirons — aktīvā dziedināšanas brūce (augsts = redzama, transformējoša)
    const chironAct = norm(weighted([
        [sg(CHIRON_WOUND, chironS),                                      `Hirons ${chironS}`,    4],
        [[1,4,7,10].includes(chironH) ? 9 : [2,5,8,11].includes(chironH) ? 6 : 3, `Hirons ${chironH ?? '?'}.māja`, 3],
        [sg(KETU_SHADOW, ketuS),                                         `Ketu ${ketuS}`,        2],
        [tempScore(humor, 'depth'),                                       humor,                  1],
    ]), 2.0, 9.0);

    // Psihosomatiskais savienojums (cik ļoti psihe ietekmē fizisko veselību)
    const psychoSom = norm(weighted([
        [sg(SUN_PSYCHOSOM,  sunS),                  `Saule ${sunS}`,     2],
        [sg(MOON_PSYCHOSOM, moonS),                 `Mēness ${moonS}`,   3],
        [elemPct(elems,'Ūdens','Koks'),             'Ūdens+Koks',        2],
        [tatvaWater,                                 tatva,               2],
        [tempScore(humor, 'psychosom'),              humor,               2],
        [sg(NEPT_SPIRIT, nS),                       `Neptūns ${nS}`,     1],
    ]), 2.5, 9.0);

    // Hroniskā vulnerabilitāte (6. māja + ļaundabīgie + konflikti)
    const chromVuln = norm(weighted([
        [Math.min(planetsIn6 * 2 + 2, 9),           '6. mājas pl.',      3],
        [Math.min(maleficsIn6 * 3 + 2, 10),         'Malef. 6.māja',     2],
        [sg(SAT_CHRONIC, satS),                      `Saturns ${satS}`,   2],
        [nconf === 0 ? 2 : nconf === 1 ? 5 : 8,     `${nconf} konfl.`,   2],
        [tempScore(humor, 'chromvuln'),               humor,               2],
        [Math.min(critAsp * 2 + 2, 9),              'Kritiskie asp.',     1],
    ]), 2.0, 8.5);

    // ═══════════════════════════════════════════════════════════════════════════
    // 21. DASHA PROFILS
    // ═══════════════════════════════════════════════════════════════════════════

    const dashExp = norm(weighted([
        [DASHA_EXP[dashaLord] ?? 5,                 `${dashaLord} Dasha`,5],
        [sg(JUP.optimism, jupS),                    `Jupiters ${jupS}`,  2],
        [tempScore(humor, 'optimism'),               humor,               2],
        [sect === 'Diena' ? 7 : 5,                  sect,                1],
    ]), 2.0, 9.0);

    const dashTrans = norm(weighted([
        [DASHA_TRANS[dashaLord] ?? 5,               `${dashaLord} Dasha`,5],
        [Math.min(nconf * 2 + 3, 10),               `${nconf} konfl.`,   2],
        [sg(KETU_SHADOW, ketuS),                    `Ketu ${ketuS}`,     2],
        [Math.min(critAsp * 2 + 2, 10),             'Kritiskie asp.',    1],
    ]), 2.0, 9.0);

    // Antardasha — apakšperioda enerģētiskais virziens (modulē mahadasha)
    const dashAntar = norm(weighted([
        [DASHA_EXP[antarLord] ?? 5,                 `${antarLord || '?'} Antar`, 6],
        [sg(JUP.openness, jupS),                    `Jupiters ${jupS}`,          2],
        [tempScore(humor, 'optimism'),               humor,                       2],
    ]), 2.0, 9.0);

    // ── Kategoriju struktūra ─────────────────────────────────────────────────
    return [
        {
            id: 'cognitive', label: 'Kognitīvais Stils', color: '#6366f1',
            traits: [
                { id:'analytical',  label:'Analītiskā domāšana', pct:analyt, desc:desc(analyt,'Loģisks, precīzs, balstās uz faktiem','Intuitīvs, balstās uz iespaidu') },
                { id:'intuition',   label:'Intuīcija',           pct:intuit, desc:desc(intuit,'Spēcīga iekšējā balss, uztver zemtekstu','Priekšroka loģikai un pierādījumiem') },
                { id:'creativity',  label:'Radošums',            pct:creat,  desc:desc(creat, 'Oriģināls, domā ārpus rāmjiem','Praktisks, meklē pārbaudītus risinājumus') },
                { id:'focus',       label:'Koncentrēšanās',      pct:focus,  desc:desc(focus, 'Ilgstoša fokusa spēja, izturīgs pret traucēkļiem','Viegli novirzās, mīl multiuzdevumus') },
                { id:'learning',    label:'Mācīšanās spēja',     pct:learn,  desc:desc(learn, 'Ātri uztver jaunas koncepcijas','Labāk apgūst ar atkārtošanu un praksi') },
            ]
        },
        {
            id: 'emotional', label: 'Emocionālais Profils', color: '#ec4899',
            traits: [
                { id:'stability',   label:'Emocionālā stabilitāte', pct:stab,  desc:desc(stab, 'Mierīgs, izlīdzināts, grūti izsviedināms no līdzsvara','Emocionāli reaktīvs, jutīgs pret stresu') },
                { id:'empathy',     label:'Empātija',                pct:empat, desc:desc(empat,'Dziļi jūt citu cilvēku stāvokļus','Racionāls, maz pakļauts citu emocijām') },
                { id:'optimism',    label:'Optimisms',               pct:optim, desc:desc(optim,'Pozitīvs, tic labākajam iznākumam','Reālistisks vai piesardzīgs, nenovērtē risku par mazu') },
                { id:'depth',       label:'Emocionālā dziļums',      pct:depth, desc:desc(depth,'Dziļa iekšēja dzīve, intensīvas jūtas','Vieglas, virspusējas emocijas, ātri pāriet') },
                { id:'selfcontrol', label:'Paškontrole',             pct:selfC, desc:desc(selfC,'Augsta griba, regulē impulsus','Impulsīvs, rīkojas pēc brīža iespaida') },
            ]
        },
        {
            id: 'social', label: 'Sociālā Dinamika', color: '#f59e0b',
            traits: [
                { id:'social',      label:'Sabiedriskums',   pct:social, desc:desc(social,'Energisks kontaktos, meklē sabiedrību','Dod priekšroku vientulībai un mazām grupām') },
                { id:'assertive',   label:'Asertivitāte',    pct:asser,  desc:desc(asser, 'Tieši pauž viedokli, aizstāv pozīciju','Izvairīgs no konfrontācijas, pakāpjas') },
                { id:'charisma',    label:'Harisma',         pct:charm,  desc:desc(charm, 'Ievelk citus, dabiska magnētiska klātbūtne','Klusāka klātbūtne, darbojas aizkulisēs') },
                { id:'diplomacy',   label:'Diplomātija',     pct:diplo,  desc:desc(diplo, 'Prasmīgi risina pretrunas, meklē kompromisu','Taisns, bieži vien pārāk tieši') },
                { id:'loyalty',     label:'Uzticamība',      pct:loyal,  desc:desc(loyal, 'Stabils, tur vārdu, uzticams ilgtermiņā','Mainīgs, vērtē katru situāciju no jauna') },
            ]
        },
        {
            id: 'drive', label: 'Darbaspēks & Motivācija', color: '#ef4444',
            traits: [
                { id:'ambition',       label:'Ambīcija',          pct:ambit, desc:desc(ambit,'Spēcīga vēlme sasniegt augstus mērķus','Apmierināts ar esošo, nemeklē izaugsmi') },
                { id:'perseverance',   label:'Neatlaidība',       pct:persi, desc:desc(persi,'Iztur grūtības, nepadodas pie šķēršļiem','Viegli atkāpjas, maina virzienus') },
                { id:'initiative',     label:'Iniciatīva',        pct:initi, desc:desc(initi,'Sāk pats, negaida atļauju vai norādījumu','Gaida, kamēr situācija nobriest vai kāds iedod virzienu') },
                { id:'risk',           label:'Risku tolerance',   pct:riskT, desc:desc(riskT,'Pieņem nenoteiktību, riskē apzināti','Izvēlas drošo ceļu, izvairās no nezināmā') },
                { id:'perfectionism',  label:'Perfekcionisms',    pct:perfe, desc:desc(perfe,'Augsti standarti, detaļām liela vērība','Pietiek ar "pietiekami labu"') },
                { id:'adaptability',   label:'Pielāgošanās',      pct:adapt, desc:desc(adapt,'Ātri pielāgojas, mīl pārmaiņas','Mīl stabilitāti, pārmaiņas uzskata par apdraudējumu') },
            ]
        },
        {
            id: 'values', label: 'Vērtību Sistēma', color: '#8b5cf6',
            traits: [
                { id:'spiritual',    label:'Garīgums',              pct:spirt, desc:desc(spirt,'Jēgas, transcendences un garīgās pieredzes meklētājs','Pragmatisks, orientēts uz taustāmo') },
                { id:'material',     label:'Materiālā orientācija', pct:mater, desc:desc(mater,'Augsti vērtē stabilitāti, resursus, drošību','Materiālās vērtības nav galvenās, svarīgāka pieredze') },
                { id:'independent',  label:'Neatkarība',            pct:indep, desc:desc(indep,'Stipra vēlme rīkoties pēc saviem noteikumiem','Labprāt strādā komandā, pieņem hierarhiju') },
                { id:'traditional',  label:'Tradicionālisms',       pct:tradi, desc:desc(tradi,'Ciena tradīcijas, normas, esošo kārtību','Progresīvs, meklē jaunus ceļus') },
            ]
        },
        {
            id: 'ocean', label: 'Big Five (OCEAN)', color: '#64748b',
            traits: [
                { id:'openness',       label:'Atvērtība',    pct:bf.O?.pct ?? 50, desc:desc(bf.O?.pct ?? 50,'Radošs, zinātkārs, mīl idejas','Praktisks, tradicionāls') },
                { id:'conscient',      label:'Apzinīgums',   pct:bf.C?.pct ?? 50, desc:desc(bf.C?.pct ?? 50,'Organizēts, disciplinēts','Spontāns, mainīgs') },
                { id:'extraversion',   label:'Ekstraversija',pct:bf.E?.pct ?? 50, desc:desc(bf.E?.pct ?? 50,'Sabiedrisks, energisks','Introspektīvs, kluss') },
                { id:'agreeableness',  label:'Pretimnākšana',pct:bf.A?.pct ?? 50, desc:desc(bf.A?.pct ?? 50,'Kooperatīvs, empātisks','Skeptisks, kritiski neatkarīgs') },
                { id:'neuroticism',    label:'Neirotisms',   pct:neuro,            desc:desc(neuro,'Emocionāli reaktīvs, augsta stresa jutība','Stabils, aukstasinīgs') },
            ]
        },
        {
            id: 'communication', label: 'Komunikācijas Stils', color: '#0ea5e9',
            traits: [
                { id:'expressiveness', label:'Ekspresivitāte',      pct:expres, desc:desc(expres,'Tieši un atvērti pauž domas, enerģisks runātājs','Rezervēts, izmanto zemtekstus un netiešas frāzes') },
                { id:'abstract',       label:'Konceptuālā Uztvere', pct:abstr,  desc:desc(abstr, 'Uztver abstraktas idejas un teorijas, domā globāli','Nepieciešami konkrēti piemēri un taustāma pieredze') },
                { id:'conflictstyle',  label:'Konfliktu Stils',     pct:conflS, desc:desc(conflS,'Konfrontē tieši, risina uzreiz bez vilcināšanās','Izvairās no konflikta, klusē vai norobežojas') },
            ]
        },
        {
            id: 'energy', label: 'Enerģētika un Dinamika', color: '#f97316',
            traits: [
                { id:'vitality',    label:'Fiziskā Vitalitāte', pct:vital, desc:desc(vital, 'Augsts enerģijas līmenis, fiziskā aktivitāte dod spēkus','Zems enerģijas budžets, nepieciešams vairāk atpūtas') },
                { id:'circadian',   label:'Diennakts Ritms',    pct:circa, desc:desc(circa, 'Cīrulis — produktīvākais no rīta, agri pieceļas','Pūce — enerģiskākais vakarā, grūtāk piecelties') },
                { id:'resilience',  label:'Atjaunošanās Spēja', pct:resil, desc:desc(resil, 'Ātri atgūst spēkus pēc pārslodzes vai slimības','Lēna atveseļošanās, ilgi jūt stresu un spiedienu') },
            ]
        },
        {
            id: 'relationships', label: 'Attiecību Dinamika', color: '#e11d48',
            traits: [
                { id:'attachment',  label:'Piesaistes Drošība',  pct:attSec, desc:desc(attSec,'Droša bāze attiecībās, uzticēšanās nāk dabiski','Trauksme vai distancēšanās tuvības situācijās') },
                { id:'sensuality',  label:'Jutekliskums',         pct:sensu,  desc:desc(sensu, 'Augsta vajadzība pēc fiziska kontakta un tuvuma','Emocionālā un intelektuālā tuvība ir svarīgāka') },
                { id:'nurturing',   label:'Rūpju Izrādīšana',    pct:nurtu,  desc:desc(nurtu, 'Dabiski rūpējas, uztur un atbalsta tuviniekus','Neatkarīgs, nekoncentrējas uz citu aprūpi') },
                { id:'jealousy',    label:'Īpašnieciskums',       pct:poss,   desc:desc(poss,  'Izteiktas ekskluzivitātes un kontroles vajadzības','Brīvs un uzticīgs, kontrole nerada vajadzību') },
            ]
        },
        {
            id: 'crisis', label: 'Krīzes un Stresa Vadība', color: '#7c3aed',
            traits: [
                { id:'fightresponse', label:'Stresa Reakcija',  pct:fight,  desc:desc(fight, 'Cīņas instinkts — uzbrūk problēmai tieši un aktīvi','Sastingšana vai atkāpšanās — pārņem paralīze') },
                { id:'crisisflex',    label:'Elastība Krīzē',   pct:crFlex, desc:desc(crFlex,'Ātri pārplāno un maina stratēģiju zem spiediena','Stīvums — uzstāj uz iepriekšējo pieeju') },
            ]
        },
        {
            id: 'resources', label: 'Resursu un Finanšu Vadība', color: '#16a34a',
            traits: [
                { id:'savings',  label:'Finanšu Disciplīna',      pct:savng,  desc:desc(savng, 'Sistemātisks uzkrājējs, drošības spilvens prioritāte','Baudītājs — tērē tagad, uzkrāšana dod grūtības') },
                { id:'finrisk',  label:'Finansiālā Risku Apetīte', pct:finRsk, desc:desc(finRsk,'Spekulatīvs, pieņem riskantus ieguldījumus','Konservatīvs ieguldītājs, izvēlas drošus risinājumus') },
            ]
        },
        {
            id: 'lifepath', label: 'Dzīves Ceļš un Vadība', color: '#0f766e',
            traits: [
                { id:'locus',       label:'Iekšējais Kontroles Lokuss', pct:locus, desc:desc(locus,'Uzņemas pilnu atbildību — pats veido savu likteni','Paļaujas uz likteni, plūsmu un augstākiem spēkiem') },
                { id:'authority',   label:'Autoritārā Vadība',          pct:authr, desc:desc(authr,'Direktīvs — nosaka noteikumus un sagaida ievērošanu','Demokrātisks — iesaista komandu lēmumu pieņemšanā') },
                { id:'inspiration', label:'Iedvesmas Vadība',           pct:inspr, desc:desc(inspr,'Vīzionārs — motivē ar ideālu un personīgu magnetismu','Praktiskais — vada ar struktūru un konkrētiem uzdevumiem') },
            ]
        },
        {
            id: 'shadow', label: 'Ēnas Puse un Pašsabotāža', color: '#78350f',
            traits: [
                { id:'destructive', label:'Destruktīvie Paterni',     pct:destr, desc:desc(destr,'Atkārtoti pašsabotāžas cikli — zemapziņa grauž panākumus','Maz izteikts ēnas mehānisms, augsta pašapziņa') },
                { id:'obsession',   label:'Obsesivitāte',             pct:obsss, desc:desc(obsss,'Intensīvas slēptas dzinuļu/atkarību tendences','Viegls, nesaistīts emocionāls profils') },
                { id:'blindspot',   label:'Kognitīvie Aklie Plankumi',pct:blind, desc:desc(blind,'Izteikti "tuneļi" — noteiktas sfēras hroniska neredzamība','Plašs uztveres spektrs, maz kognitīvo plankumu') },
            ]
        },
        {
            id: 'identity', label: 'Identitātes Dualitāte', color: '#4338ca',
            traits: [
                { id:'persona',     label:'Sociālās Maskas Biezums', pct:maskT,  desc:desc(maskT, 'Liela atšķirība starp publisko tēlu un privāto "es"','Autentisks — kāds ir publiski, tāds arī privāti') },
                { id:'status',      label:'Statusa Izsalkums',        pct:statUs, desc:desc(statUs,'Intensīva vajadzība pēc atzinības, tituliem un statusa','Statuss un tituli nav prioritāte — svarīgi darbi') },
            ]
        },
        {
            id: 'temporal', label: 'Laika Uztvere un Attīstība', color: '#0369a1',
            traits: [
                { id:'transition',  label:'Pārmaiņu Adaptācija',      pct:chAdpt, desc:desc(chAdpt,'Plaukst lielās dzīves pārejas — izmaiņas dod enerģiju','Lieli dzīves pagriezieni rada stresu un pretestību') },
                { id:'longterm',    label:'Ilgtermiņa Domāšana',      pct:lterm,  desc:desc(lterm, 'Spēlē "garo spēli" — atliek gandarījumu, redz gadu perspektīvi','Tūlītēji rezultāti — pagaidīt šķiet ļoti grūti') },
            ]
        },
        {
            id: 'genius', label: 'Talantu un Ģēnija Potenciāls', color: '#b45309',
            traits: [
                { id:'hyperfocus',  label:'Hiperfokusa Zonas',       pct:hypFoc, desc:desc(hypFoc,'Ārkārtēja dziļuma spēja — var sasniegt ekspertīzes līmeni','Plašs, bet pārsvarā virspusējs zināšanu klāsts') },
                { id:'synthesis',   label:'Sintezēšanas Spēja',      pct:synth,  desc:desc(synth, 'Savienot šķietami nesaistītas jomas — unikāls skatījums','Specializēts, priekšroka vienai nozarei bez tiltu vilkšanas') },
            ]
        },
        {
            id: 'moral', label: 'Morālais Kompass', color: '#166534',
            traits: [
                { id:'categorical', label:'Kategoriskums',           pct:categ,  desc:desc(categ, 'Melnbalts pasaules skatījums — stingri principi virs situācijas','Pelēko zonu eksperts — elastīgs, kontekstuāls skatījums') },
                { id:'hierarchy',   label:'Autoritāšu Uztvere',      pct:hierRsp,desc:desc(hierRsp,'Dabiska cieņa pret hierarhiju un esošo kārtību','Dumpiniecisks instinkts — apšauba autoritātes un status quo') },
            ]
        },
        {
            id: 'karmic', label: 'Karmiskā Attīstība', color: '#7f1d1d',
            traits: [
                { id:'souldirection', label:'Dvēseles Virziena Stiprums', pct:soulDir,    desc:desc(soulDir,   'Spēcīga karmiskā misija — obligāti jāiet ārpus komforta zonas','Maz pieprasošs evolūcijas uzdevums — dabiska virzība') },
                { id:'karmicload',    label:'Karmiskā Slodze',            pct:karmicBurd, desc:desc(karmicBurd,'Smaga neatrisināta karma prasa aktīvu darbu','Neliela karmiskā slodze — salīdzinoši brīvs ceļš') },
                { id:'pastgifts',     label:'Iepriekšējo Dzīvju Talants', pct:pastTal,    desc:desc(pastTal,   'Spēcīgi dabiskie talanti — jūtas iedzimti un nākuši bez piepūles','Talanti veidojas no nulles — šajā dzīvē mācoties') },
            ]
        },
        {
            id: 'houses', label: 'Māju Fokuss', color: '#1e3a5f',
            traits: [
                { id:'h8transform',  label:'8. Mājas Transformācija',    pct:h8score,  desc:desc(h8score, 'Dziļas transformācijas, kopīgu resursu un noslēpumainā sfēra dominē','Mazāk noslēpumains un tiešāks dzīves fokuss') },
                { id:'h9philosophy', label:'9. Mājas Filozofija',        pct:h9score,  desc:desc(h9score, 'Spēcīga vēlme pēc zināšanām, filozofijas, svešvalstīm, plašuma','Praktisks — tālās perspektīvas un teorija mazāk piesaista') },
                { id:'h10status',    label:'10. Mājas Publiskais Spēks', pct:h10score, desc:desc(h10score,'Augsts publiskās varas un karjeras magnētisms','Privātāks — publiskais statuss nav galvenā prioritāte') },
            ]
        },
        {
            id: 'medical', label: 'Medicīniskā Astroloģija', color: '#064e3b',
            traits: [
                { id:'vitalcap',   label:'Vitalitātes Kapacitāte',       pct:vitalCap,   desc:desc(vitalCap,   'Stipra sirds-asinsvadu un vitalitātes bāze — augsta dzīvotspēja','Delikāts enerģētiskais līdzsvars — atjaunošanās ir prioritāte') },
                { id:'neuralsens', label:'Nervu Sistēmas Jutīgums',       pct:neuralSens, desc:desc(neuralSens, 'Augsts neirālais jutīgums — daudz uztver, iespēja pārslogošanās','Noturīga nervu sistēma — iztur augstu kognitīvo slodzi') },
                { id:'healingcap', label:'Dziedināšanas Kapacitāte',      pct:healCap,    desc:desc(healCap,    'Spēcīga imūnsistēma un ātri pašdziedināšanās procesi','Imūnā sistēma prasa rūpīgu uzturēšanu un profilaktiku') },
                { id:'chironact',  label:'Hirons — Dziedināšanas Brūce',  pct:chironAct,  desc:desc(chironAct,  'Aktīva, transformējoša dziedināšanas brūce — palīdz citiem caur sāpi','Hirons slēpts — dziedināšanas potenciāls vēl nav atmodināts') },
                { id:'psychosom',  label:'Psihosomatiskais Savienojums',  pct:psychoSom,  desc:desc(psychoSom,  'Psihe tieši izpaužas fiziskā ķermenī — emocionālā veselība ir pamats','Spēcīgs prāts-ķermenis robežojums — stress somatizējas reti') },
                { id:'chromvuln',  label:'Hroniskā Vulnerabilitāte',      pct:chromVuln,  desc:desc(chromVuln,  'Paaugstināts hronisko traucējumu risks — nepieciešama profilaktika','Labvēlīga fiziskā konstitūcija — hroniskas problēmas mazāk varbūtīgas') },
            ]
        },
        {
            id: 'dasha', label: 'Dasha Profils', color: '#4a044e',
            traits: [
                { id:'dashaexp',   label:'Pašreizējā Perioda Ekspansivitāte', pct:dashExp,   desc:desc(dashExp,   'Paplašināšanās Dasha — optimāls laiks izaugsmei un iespējām','Ierobežojumu Dasha — ieguldīt, gaidīt, iekšēji pārskatīt') },
                { id:'dashatrans', label:'Perioda Pārmaiņu Intensitāte',      pct:dashTrans, desc:desc(dashTrans, 'Augsti transformatīvs periods — lielās pārmaiņas neizbēgamas','Relatīvi stabils periods — lielās krīzes mazāk varbūtīgas') },
                { id:'dashaantar', label:'Apakšperioda Ekspansivitāte',       pct:dashAntar, desc:desc(dashAntar, 'Aktīvais apakšperiods pastiprina izaugsmi — labvēlīgs laikposms','Apakšperiods bremzē vai iekšēji fokusē — mazāk ārēja ekspansija') },
            ]
        },
    ];
}

