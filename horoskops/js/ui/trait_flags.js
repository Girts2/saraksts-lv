// Personības Matricas vizuālās atzīmes
// dir '+' → augsts = labs   (green ja >= green, red ja <= red)
// dir '-' → augsts = risks  (red ja >= red, green ja <= green)
// dir 'u' → U-forma         (red pie abām galējībām)
// dir '~' → stils / neitrāls (nekrāso)

const TRAIT_FLAGS = {

    // ── KOGNITĪVAIS STILS ────────────────────────────────────────────────────
    analytical:    { dir: '+', green: 72, red: 22 },
    intuition:     { dir: '~' },
    creativity:    { dir: '+', green: 72, red: 22 },
    focus:         { dir: '+', green: 72, red: 22 },
    learning:      { dir: '+', green: 72, red: 22 },

    // ── EMOCIONĀLAIS PROFILS ─────────────────────────────────────────────────
    stability:     { dir: '+', green: 70, red: 25 },
    empathy:       { dir: '+', green: 68, red: 20 },
    optimism:      { dir: '+', green: 72, red: 18 },
    depth:         { dir: '~' },
    selfcontrol:   { dir: '+', green: 70, red: 22 },

    // ── SOCIĀLĀ DINAMIKA ─────────────────────────────────────────────────────
    social:        { dir: '~' },
    assertive:     { dir: '+', green: 70, red: 20 },
    charisma:      { dir: '+', green: 72, red: 22 },
    diplomacy:     { dir: '+', green: 70, red: 22 },
    loyalty:       { dir: '+', green: 72, red: 20 },

    // ── DARBASPĒKS & MOTIVĀCIJA ──────────────────────────────────────────────
    ambition:      { dir: '+', green: 72, red: 20 },
    perseverance:  { dir: '+', green: 72, red: 22 },
    initiative:    { dir: '+', green: 70, red: 20 },
    risk:          { dir: '~' },
    perfectionism: { dir: 'u', redLow: 15, redHigh: 88 },
    adaptability:  { dir: '+', green: 70, red: 22 },

    // ── VĒRTĪBU SISTĒMA ──────────────────────────────────────────────────────
    spiritual:     { dir: '~' },
    material:      { dir: '~' },
    independent:   { dir: '~' },
    traditional:   { dir: '~' },

    // ── BIG FIVE (OCEAN) ─────────────────────────────────────────────────────
    openness:      { dir: '+', green: 70, red: 20 },
    conscient:     { dir: '+', green: 72, red: 22 },
    extraversion:  { dir: '~' },
    agreeableness: { dir: 'u', redLow: 18, redHigh: 88 },
    neuroticism:   { dir: '-', red: 72, green: 30 },

    // ── KOMUNIKĀCIJAS STILS ──────────────────────────────────────────────────
    expressiveness:{ dir: '~' },
    abstract:      { dir: '~' },
    conflictstyle: { dir: '~' },

    // ── ENERĢĒTIKA ───────────────────────────────────────────────────────────
    vitality:      { dir: '+', green: 72, red: 22 },
    circadian:     { dir: '~' },
    resilience:    { dir: '+', green: 72, red: 22 },

    // ── ATTIECĪBU DINAMIKA ───────────────────────────────────────────────────
    attachment:    { dir: '+', green: 70, red: 22 },
    sensuality:    { dir: '~' },
    nurturing:     { dir: '+', green: 68, red: 20 },
    jealousy:      { dir: '-', red: 78, green: 30 },

    // ── KRĪZES VADĪBA ────────────────────────────────────────────────────────
    fightresponse: { dir: '~' },
    crisisflex:    { dir: '+', green: 70, red: 22 },

    // ── RESURSI & FINANSES ───────────────────────────────────────────────────
    savings:       { dir: '+', green: 68, red: 22 },
    finrisk:       { dir: '~' },

    // ── DZĪVES CEĻŠ ─────────────────────────────────────────────────────────
    locus:         { dir: '+', green: 70, red: 22 },
    authority:     { dir: '~' },
    inspiration:   { dir: '~' },

    // ── ĒNAS PUSE (visa kategorija: augsts = risks) ──────────────────────────
    destructive:   { dir: '-', red: 72, green: 28 },
    obsession:     { dir: '-', red: 72, green: 28 },
    blindspot:     { dir: '-', red: 72, green: 28 },

    // ── IDENTITĀTES DUALITĀTE ────────────────────────────────────────────────
    persona:       { dir: '-', red: 78, green: 30 },
    status:        { dir: '-', red: 80, green: 28 },

    // ── LAIKA UZTVERE ────────────────────────────────────────────────────────
    transition:    { dir: '+', green: 70, red: 22 },
    longterm:      { dir: '+', green: 70, red: 22 },

    // ── TALANTI ─────────────────────────────────────────────────────────────
    hyperfocus:    { dir: '+', green: 72, red: 22 },
    synthesis:     { dir: '+', green: 72, red: 22 },

    // ── MORĀLAIS KOMPASS ─────────────────────────────────────────────────────
    categorical:   { dir: '~' },
    hierarchy:     { dir: '~' },

    // ── KARMISKĀ ATTĪSTĪBA ───────────────────────────────────────────────────
    souldirection: { dir: '~' },
    karmicload:    { dir: '-', red: 78, green: 28 },
    pastgifts:     { dir: '+', green: 72, red: 22 },

    // ── MĀJU FOKUSS ──────────────────────────────────────────────────────────
    h8transform:   { dir: '~' },
    h9philosophy:  { dir: '~' },
    h10status:     { dir: '~' },

    // ── MEDICĪNISKĀ ASTROLOĢIJA ──────────────────────────────────────────────
    vitalcap:      { dir: '+', green: 72, red: 22 },
    neuralsens:    { dir: '~' },
    healingcap:    { dir: '+', green: 72, red: 22 },
    chironact:     { dir: '~' },
    psychosom:     { dir: '-', red: 75, green: 28 },
    chromvuln:     { dir: '-', red: 75, green: 28 },

    // ── DASHA PROFILS ────────────────────────────────────────────────────────
    dashaexp:      { dir: '+', green: 70, red: 25 },
    dashatrans:    { dir: '~' },
    dashaantar:    { dir: '+', green: 70, red: 25 },
};

// Atgriež 'green', 'red' vai null
export function getFlag(id, pct) {
    const f = TRAIT_FLAGS[id];
    if (!f || f.dir === '~') return null;
    if (f.dir === '+') {
        if (pct >= f.green) return 'green';
        if (pct <= f.red)   return 'red';
    }
    if (f.dir === '-') {
        if (pct >= f.red)   return 'red';
        if (pct <= f.green) return 'green';
    }
    if (f.dir === 'u') {
        if (pct <= f.redLow || pct >= f.redHigh) return 'red';
    }
    return null;
}
