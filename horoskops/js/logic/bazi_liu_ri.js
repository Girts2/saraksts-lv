// ─────────────────────────────────────────────────────────────────────────────
// BaZi Liu Ri (流日) — dienas pīlārs kā 3. NEATKARĪGAIS balsotājs nedēļas konsensam
// ─────────────────────────────────────────────────────────────────────────────
// Sk. atmiņu feat-nedelas-horoskops-konsenss. Šis ir kalendāra (sešdesmitnieku)
// balsotājs — neatkarīgs no Mēness/Saules ass. Divi kanāli:
//   1) STUMBRS → Ten God (day-master vs Liu Ri stumbrs) → domēns + valence
//   2) ZARS → sadursme (六冲) ar natālajiem zariem → svārstība (valence→izaicinošs, stiprums↑)
// Izvade projicēta uz KOPĒJO ontoloģiju (valence + domēni + stiprums + drivers),
// lai BaZi var "balsot" blakus Tara Bala un Rietumu tranzītiem.

import { STEMS, BRANCHES, BRANCH_CLASHES, calculateTenGods, baziDayJDN, dayPillarFromJDN } from '../bazi.js?v=12';

// ── Kopējās ontoloģijas asis ─────────────────────────────────────────────────
// valence: 'supportive' | 'challenging' | 'neutral'
// domains: apakškopa no {darbs, attiecības, nauda, veselība, komunikācija, risks}
// strength: 0..1 (pirmā kārta — heiristiski svari, deklarēti iepriekš, regulējami)

// Ten God → bāzes ontoloģija. Avoti: klasiskā 10 dievu semantika.
const TEN_GOD_ONTOLOGY = {
    Friend:            { valence: 'neutral',     domains: ['attiecības'],          strength: 0.35 }, // 比肩 — līdzgaitnieki, paļāvība uz sevi
    Rob_Wealth:        { valence: 'challenging', domains: ['nauda', 'risks'],      strength: 0.50 }, // 劫财 — sāncensība, naudas noplūde
    Eating_God:        { valence: 'supportive',  domains: ['komunikācija', 'veselība'], strength: 0.50 }, // 食神 — patīkama izpausme, labsajūta
    Hurting_Officer:   { valence: 'challenging', domains: ['komunikācija'],        strength: 0.60 }, // 伤官 — spožums + berze ar noteikumiem
    Direct_Wealth:     { valence: 'supportive',  domains: ['nauda'],               strength: 0.50 }, // 正财 — stabils ienākums, čaklums
    Indirect_Wealth:   { valence: 'supportive',  domains: ['nauda', 'risks'],      strength: 0.50 }, // 偏财 — neparedzēta nauda, lielas plūsmas
    Direct_Officer:    { valence: 'supportive',  domains: ['darbs'],               strength: 0.50 }, // 正官 — struktūra, atbildība, atzinība
    Seven_Killings:    { valence: 'challenging', domains: ['darbs', 'risks'],      strength: 0.70 }, // 七杀 — spiediens, konflikts, termiņi
    Direct_Resource:   { valence: 'supportive',  domains: ['veselība'],            strength: 0.50 }, // 正印 — atbalsts, atpūta, mācīšanās
    Indirect_Resource: { valence: 'neutral',     domains: ['veselība'],            strength: 0.40 }, // 偏印 — netradicionālas zināšanas, introspekcija
};

// Kurš natālais pīlārs tiek skarts sadursmē → domēna nianse (zaru "pilis").
const PILLAR_DOMAIN = { Year: 'darbs', Month: 'darbs', Day: 'attiecības', Hour: 'veselība' };

const branchKey = (b) => String(b || '').split(' ')[0];

// ── Galvenais: Ten God + sadursme → ontoloģija ───────────────────────────────
export function mapToOntology(god, clash) {
    const base = TEN_GOD_ONTOLOGY[god] || { valence: 'neutral', domains: ['risks'], strength: 0.30 };
    const drivers = god ? [`TenGod:${god}`] : ['TenGod:none'];
    let valence = base.valence;
    let strength = base.strength;
    const domains = [...base.domains];

    if (clash && clash.hit) {
        // Sadursme dominē: pārceļ valenci uz izaicinošu, ceļ stiprumu, pievieno svārstību.
        valence = 'challenging';
        strength = Math.min(1, strength + 0.30);
        if (!domains.includes('risks')) domains.push('risks');
        for (const p of clash.pillars) {
            drivers.push(`Clash:${p.pillar}(${p.branch})`);
            const d = PILLAR_DOMAIN[p.pillar];
            if (d && !domains.includes(d)) domains.push(d);
        }
    }
    return { valence, domains, strength: Math.round(strength * 100) / 100, drivers };
}

// Tikai Ten God bāze (bez sadursmes) — ērtai pārbaudei/atkļūdošanai.
export function tenGodToOntology(god) { return mapToOntology(god, { hit: false, pillars: [] }); }

// Vai Liu Ri zars sadursmē ar kādu natālo zaru; atgriež skartos pīlārus.
export function detectNatalClash(profile, liuRiBranch) {
    const key = branchKey(liuRiBranch);
    const pillars = [];
    for (const name of ['Year', 'Month', 'Day', 'Hour']) {
        const nb = profile?.bazi?.[name]?.Branch;
        if (!nb) continue;
        const nk = branchKey(nb);
        // key !== nk: zars nesadursmējas pats ar sevi (六冲 ir starp PRETĒJIEM zariem).
        if (key !== nk && BRANCH_CLASHES.some(c => c.includes(key) && c.includes(nk))) pillars.push({ pillar: name, branch: nk });
    }
    return { hit: pillars.length > 0, pillars };
}

// Pilns Liu Ri signāls vienai dienai (pēc JDN).
export function liuRiSignalForJDN(profile, jdn) {
    const dm = profile?.bazi?.Daymaster;
    if (!dm) return null;
    const pillar = dayPillarFromJDN(jdn);
    const god = calculateTenGods(dm, pillar.Stem);
    const clash = detectNatalClash(profile, pillar.Branch);
    return {
        group: 'bazi',
        jdn,
        pillar: { stem: pillar.Stem.name, branch: branchKey(pillar.Branch), element: pillar.Stem.element },
        god,
        ...mapToOntology(god, clash),
    };
}

// "Šodienas" BaZi JDN pašreizējā lokācijā (LMT + 23:00 robeža).
// lon — pašreizējās lokācijas garums; nowMs — Date.now() (vai tests).
export function todayBaziJDN(lon = 0, nowMs = Date.now()) {
    const lmt = new Date(nowMs + (lon / 15) * 3600000);
    return baziDayJDN(lmt.getUTCFullYear(), lmt.getUTCMonth() + 1, lmt.getUTCDate(), lmt.getUTCHours());
}

// Nedēļas (vai N dienu) Liu Ri signālu virkne, sākot no jdnStart.
export function buildLiuRiWeek(profile, jdnStart, days = 7) {
    const out = [];
    for (let i = 0; i < days; i++) out.push(liuRiSignalForJDN(profile, jdnStart + i));
    return out;
}
