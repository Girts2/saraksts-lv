// ════════════════════════════════════════════════════════════════════════════
// ASTROLOĢISKĀ KARTE (cilne "Karte" / t6)
// 360° melnbalts ritenis ar visiem swisseph objektiem. Iekšējais disks = objekti
// VIRS horizonta (redzami debesīs), gredzens starp iekšējo un ārējo apli = objekti
// AIZ horizonta. Krāsainās "situācijas" (aspekti, leņķiskie notikumi, retrogrādi)
// izceltas ar vienkāršu skaidrojumu — divi slāņi: vispārīgi (debesis tagad) un
// personalizēti (tranzīts pret dzimšanas karti).
// ════════════════════════════════════════════════════════════════════════════
import { getJulianDay, getAstronomicalData } from '../core_astro.js?v=9';
import { setupAutocomplete } from './autocomplete.js?v=9';
import { buildDayConsensus } from '../logic/weekly_consensus.js?v=11';
import { buildElectionalWindows, buildBusinessCalendar, clearElectionalCache, weekdayLV, fmtDateLV, fmtHour } from '../logic/electional.js?v=8';
import { swisseph } from '../../swisseph/dist/swisseph-browser.js?v=2.4';

// ── Konstantes ───────────────────────────────────────────────────────────────
const BODIES = ["Saule","Meness","Merkurs","Venera","Marss","Jupiters","Saturns","Urans","Neptuns","Plutons","Rahu","Ketu"];
// Swisseph ķermeņu ID (retrogrāda ātruma nolasīšanai)
const PID = { Saule:0, Meness:1, Merkurs:2, Venera:3, Marss:4, Jupiters:5, Saturns:6, Urans:7, Neptuns:8, Plutons:9, Rahu:10 };
// VS15 piespiež teksta (melnbaltu) prezentāciju — citādi macOS rāda krāsainas emoji.
const VS = "︎";
const GLYPH = {
    Saule:"☉"+VS, Meness:"☽"+VS, Merkurs:"☿"+VS, Venera:"♀"+VS, Marss:"♂"+VS, Jupiters:"♃"+VS,
    Saturns:"♄"+VS, Urans:"♅"+VS, Neptuns:"♆"+VS, Plutons:"♇"+VS, Rahu:"☊"+VS, Ketu:"☋"+VS,
    Ascendant:"Asc", MC:"MC"
};
const SIGN_GLYPH = ["♈","♉","♊","♋","♌","♍","♎","♏","♐","♑","♒","♓"].map(g => g + VS);
const SIGN_NAME  = ["Auns","Vērsis","Dvīņi","Vēzis","Lauva","Jaunava","Svari","Skorpions","Strēlnieks","Mežāzis","Ūdensvīrs","Zivis"];
// Lasāmie latviešu nosaukumi (neastrologam glifs neko nepasaka — rādām vārdu).
const NAME_LV = {
    Saule:"Saule", Meness:"Mēness", Merkurs:"Merkurs", Venera:"Venera", Marss:"Marss",
    Jupiters:"Jupiters", Saturns:"Saturns", Urans:"Urāns", Neptuns:"Neptūns",
    Plutons:"Plutons", Rahu:"Rahu", Ketu:"Ketu", Ascendant:"Ascendants", MC:"MC"
};
// Zodiaka režīmi neastrologa valodā: poga + īsais paskaidrojums + virsraksts/apraksts virs kartes.
const ZODIAC_META = {
    tropical: {
        btn:   "Zeme",
        hint:  "seko Zemes asij un tās svārstībām",
        title: "Rakstura un psiholoģijas horoskops",
        desc:  "Rietumu sistēma lieliski apraksta cilvēka iekšējo pasauli, ego, uzvedības modeļus un to, kā viņš uztver pasauli.",
    },
    sidereal: {
        btn:   "Kosmoss",
        hint:  "seko reālajiem zvaigznājiem",
        title: "Likteņa un dzīves ceļa horoskops",
        desc:  "Vēdiskajā tradīcijā šis zodiaks vairāk fokusējas uz karmu, objektīviem notikumiem, laika periodiem (dašām) un to, kas ar cilvēku notiks, nevis tikai to, kā viņš jūtas.",
    },
};

// Dzīves jomas katrai planētai (vienkāršai valodai)
const LIFE = {
    Saule:"identitāte un dzīves spēks", Meness:"emocijas un noskaņojums",
    Merkurs:"domāšana un saziņa", Venera:"mīlestība, attiecības un nauda",
    Marss:"enerģija, dzinulis un konflikti", Jupiters:"izaugsme un iespējas",
    Saturns:"atbildība, robežas un disciplīna", Urans:"pārmaiņas un brīvība",
    Neptuns:"sapņi, intuīcija un ilūzijas", Plutons:"dziļa transformācija un vara",
    Rahu:"tieksmes un nākotnes virziens", Ketu:"atlaišana un pagātne",
    Ascendant:"tavs tēls un pieeja dzīvei", MC:"karjera un sabiedriskais stāvoklis"
};

// Aspekti (tā pati pieeja kā buildWesternTransitWeek horoskops.js)
const ASPECTS = [
    { name:"konjunkcija", sym:"☌", angle:0,   harmony:0,  gOrb:8, pOrb:6 },
    { name:"sekstils",    sym:"⚹", angle:60,  harmony:1,  gOrb:4, pOrb:3 },
    { name:"kvadrāts",    sym:"□", angle:90,  harmony:-1, gOrb:6, pOrb:4 },
    { name:"trigons",     sym:"△", angle:120, harmony:1,  gOrb:6, pOrb:4 },
    { name:"opozīcija",   sym:"☍", angle:180, harmony:-1, gOrb:7, pOrb:5 },
];
const ASP_MEANING = {
    konjunkcija:"saplūšana — divi spēki apvienojas, jauns sākums šajā jomā",
    sekstils:"viegla iespēja — durvis paveras, ja parādi mazliet iniciatīvas",
    kvadrāts:"berze — spriedze, kas spiež rīkoties un pārvarēt šķērsli",
    trigons:"harmonija — plūstoša, dabiska veiksme šajā jomā",
    opozīcija:"pretstats — divi spēki velk uz pretējām pusēm, jāmeklē līdzsvars",
};

// Krāsas (vienīgās krāsas kartē — pamats ir melnbalts)
const COL = {
    harmon:"#16a34a",   // harmonisks (trigons/sekstils) — zaļš
    tense:"#dc2626",    // saspīlēts (kvadrāts/opozīcija) — sarkans
    conj:"#7c3aed",     // konjunkcija — violets
    angular:"#d97706",  // leņķiskie notikumi (horizonts/kulminācija) — zelts
    retro:"#4f46e5",    // retrogrāds — indigo
};
const aspColor = (h) => h>0 ? COL.harmon : (h<0 ? COL.tense : COL.conj);

// Filtru (ietekmju saišu/stāvokļu) definīcijas — viens avots pogām un leģendai.
const TOGGLE_DEFS = [
    { id:"toggle-harmony",     col:COL.harmon,  label:"🟢 Harmonija",        legend:"harmonija",            desc:"Planētas atbalsta viena otru, nodrošinot vieglu enerģijas plūsmu." },
    { id:"toggle-tension",     col:COL.tense,   label:"🔴 Spriedze",         legend:"spriedze",             desc:"Rada iekšēju berzi vai nemieru, kas kalpo kā dzinējspēks rīcībai." },
    { id:"toggle-conjunction", col:COL.conj,    label:"🟣 Saplūšana",        legend:"saplūšana",            desc:"Divi spēki apvienojas vienā punktā, ievērojami pastiprinot viens otru." },
    { id:"toggle-horizon",     col:COL.angular, label:"⚖️ Uz horizonta / MC", legend:"uz horizonta / kulminē", desc:"Planētas uz horizonta vai zenītā/nadirā. Visspēcīgākā izpausme." },
    { id:"toggle-retrograde",  col:COL.retro,   label:"🌀 Retrogrāds",       legend:"retrogrāds",           desc:"Šķietama atpakaļgaita. Aicina palēnināties, labot un pārvērtēt." },
];

// Koplietotā leģenda (Ritenim un Saules sistēmai)
function defaultLegendHTML() {
    return `<div class="karte-legend" style="margin:0; border:none; padding:0;">` +
        TOGGLE_DEFS.map(t => `<span><i style="background:${t.col}"></i>${t.legend}</span>`).join("") +
        `</div>`;
}

// ── Ģeometrija ───────────────────────────────────────────────────────────────
const VB = 800, CX = 400, CY = 400;
const R_OUTER = 354;       // ārējais aplis
const R_SIGN  = 336;       // zodiaka zīmju glifi
const R_RIMIN = 318;       // malas joslas iekšmala
const R_DEGNUM = 305;      // 360° grādu skaitļi
const R_INNER = 252;       // iekšējais aplis (horizonta robeža) — palielināts
const R_ABOVE_MARK = 238;  // virs-horizonta planētu atzīmes (diska malā)
const R_ABOVE_LAB  = 196;  // virs-horizonta uzraksti (iekšpusē)
const R_BELOW_MARK = 274;  // aiz-horizonta planētu atzīmes (gredzenā)
const R_BELOW_LAB  = 294;  // aiz-horizonta uzraksti (uz āru)

function polar(lon, r) {
    const a = (180 - lon) * Math.PI / 180; // 0° pa kreisi, CCW (astroloģijas konvencija)
    return { x: CX + r * Math.cos(a), y: CY - r * Math.sin(a) };
}
// Mazākais leņķiskais attālums starp diviem garumiem (0..180)
const sep = (a, b) => { let d = Math.abs(a - b) % 360; return d > 180 ? 360 - d : d; };
const norm = (d) => ((d % 360) + 360) % 360;

// Kurā mājā (1..12) ir garums; mājas 7-12 = virs horizonta.
// Swisseph atgriež 1-indeksētu masīvu: cusps[1..12] = mājas 1..12, cusps[0] neizmantots.
function houseOf(lon, cusps) {
    for (let i = 1; i <= 12; i++) {
        const start = cusps[i];
        const end   = cusps[i === 12 ? 1 : i + 1];
        const span  = norm(end - start);
        const off   = norm(lon - start);
        if (off < span || span === 0) return i;
    }
    return 1;
}

// ── Datu slānis ──────────────────────────────────────────────────────────────
// effDate = JS Date (UTC instant). zodiac = 'tropical' | 'sidereal'
function computeSky(effDate, lat, lon, zodiac) {
    const jd = getJulianDay(effDate.toISOString());
    const A  = getAstronomicalData(jd, lat, lon);

    const disp  = zodiac === 'sidereal' ? A.sidereal : A.tropical;
    const cusps = zodiac === 'sidereal' ? A.houses_sidereal : A.houses_tropical;

    const asc = disp["Ascendant"], mc = disp["MC"];
    const dsc = norm(asc + 180), ic = norm(mc + 180);
    // Tropiskais ASC/MC — Saules sistēmas (heliocentriskā, vienmēr tropiskā) skata horizonta pārklājumam.
    const ascTrop = norm(A.tropical["Ascendant"]), mcTrop = norm(A.tropical["MC"]);

    // Saule/Mēness nekad nav retrogrādi; mezgli (Rahu/Ketu) ir vienmēr → izlaižam kā troksni.
    const NEVER_RETRO = ["Saule","Meness","Rahu","Ketu"];
    // Retrogrāds tieši no swisseph ātruma (longitudeSpeed < 0) — precīzāk un lētāk nekā otrs pilns aprēķins.
    const isRetro = (n) => {
        if (NEVER_RETRO.includes(n) || PID[n] === undefined) return false;
        try { return swisseph.calculatePosition(jd, PID[n], 260).longitudeSpeed < 0; }
        catch (e) { return false; }
    };

    const bodies = BODIES.filter(n => disp[n] !== undefined).map(n => {
        const lonDisp = norm(disp[n]);
        const lonTrop = norm(A.tropical[n]);
        const retro = isRetro(n);
        const house = houseOf(lonDisp, cusps);
        return { name:n, glyph:GLYPH[n], lon:lonDisp, lonTrop, retro, house, above:(house >= 7) };
    });

    return { bodies, asc, dsc, mc, ic, ascTrop, mcTrop, cusps, jd, lat };
}

// ── Situāciju slānis ─────────────────────────────────────────────────────────
function detectGeneral(sky) {
    const out = [];
    const bs = sky.bodies;

    // 1) Aspekti starp tranzīta ķermeņiem
    for (let i = 0; i < bs.length; i++) {
        for (let j = i + 1; j < bs.length; j++) {
            // Mezglu savstarpējā opozīcija (Rahu/Ketu) ir triviāla — izlaižam
            if ((bs[i].name === "Rahu" && bs[j].name === "Ketu") ||
                (bs[i].name === "Ketu" && bs[j].name === "Rahu")) continue;
            const s = sep(bs[i].lon, bs[j].lon);
            for (const asp of ASPECTS) {
                const orb = Math.abs(s - asp.angle);
                if (orb <= asp.gOrb) {
                    out.push({
                        kind:"aspect", harmony:asp.harmony, color:aspColor(asp.harmony),
                        a:bs[i], b:bs[j], orb,
                        icon: asp.harmony>0 ? "🟢" : (asp.harmony<0 ? "🔴" : "🟣"),
                        title:`${bs[i].glyph} ${bs[i].name} ${asp.sym} ${bs[j].glyph} ${bs[j].name}`,
                        sub:`${asp.name} (orbs ${orb.toFixed(1)}°)`,
                        plain: ASP_MEANING[asp.name],
                        life:`${cap(LIFE[bs[i].name])} satiekas ar ${LIFE[bs[j].name]}.`,
                    });
                    break;
                }
            }
        }
    }

    // 2) Leņķiskie notikumi (planēta uz horizonta vai kulminē)
    const angles = [
        { lon:sky.asc, label:"lec pāri austrumu horizontam", note:"nāk priekšplānā, kļūst aktuāls" },
        { lon:sky.dsc, label:"riet rietumu horizontā",       note:"attiecības un citi cilvēki izceļ šo tēmu" },
        { lon:sky.mc,  label:"kulminē (augstākais punkts)",  note:"redzams publiski, ietekmē karjeru/reputāciju" },
        { lon:sky.ic,  label:"viszemākais punkts (sakņu zona)", note:"darbojas iekšēji — mājas, ģimene, pamati" },
    ];
    for (const b of bs) {
        for (const ang of angles) {
            const o = sep(b.lon, ang.lon);
            if (o <= 2.5) {
                out.push({
                    kind:"angular", harmony:0, color:COL.angular, a:b, b:null, orb:o,
                    icon:"⭐",
                    title:`${b.glyph} ${b.name} ${ang.label}`,
                    sub:`leņķiskais notikums (orbs ${o.toFixed(1)}°)`,
                    plain:`Šobrīd ${b.name} ir vienā no četrām spēcīgākajām debess vietām.`,
                    life:`${cap(LIFE[b.name])} tagad ${ang.note}.`,
                });
                break;
            }
        }
    }

    // 3) Retrogrādi
    for (const b of bs) {
        if (b.retro) {
            out.push({
                kind:"retro", harmony:0, color:COL.retro, a:b, b:null,
                icon:"℞",
                title:`${b.glyph} ${b.name} retrogrāds`,
                sub:"šķietama atpakaļgaita",
                plain:`No Zemes skatoties, ${b.name} šķiet ejam atpakaļ — laiks pārskatīt, ne sākt no jauna.`,
                life:`Pārdomā un sakārto: ${LIFE[b.name]}.`,
            });
        }
    }
    return out;
}

function detectPersonal(sky, natalLon) {
    if (!natalLon) return [];
    const out = [];
    for (const b of sky.bodies) {
        for (const np of Object.keys(natalLon)) {
            const nDeg = natalLon[np];
            if (nDeg === undefined || nDeg === null) continue;
            const s = sep(b.lonTrop, nDeg); // personalizēti — vienmēr tropiski (saderībā ar natālo)
            for (const asp of ASPECTS) {
                const orb = Math.abs(s - asp.angle);
                if (orb <= asp.pOrb) {
                    const isAngle = (np === 'Ascendant' || np === 'MC');
                    const npGlyph = isAngle ? '' : (GLYPH[np] || '');
                    const npName  = np === 'Ascendant' ? 'Ascendants' : np;
                    out.push({
                        kind:"transit", harmony:asp.harmony, color:aspColor(asp.harmony),
                        a:b, natal:np, natalLon:nDeg, orb,
                        icon: asp.harmony>0 ? "🟢" : (asp.harmony<0 ? "🔴" : "🟣"),
                        title:`Tranzīta ${b.glyph} ${b.name} ${asp.sym} tavs ${npGlyph} ${npName}`.replace(/\s+/g, ' ').trim(),
                        sub:`${asp.name} (orbs ${orb.toFixed(1)}°)`,
                        plain: ASP_MEANING[asp.name],
                        life:`Pašreizējā ${b.name} ietekmē tavu jomu: ${LIFE[np]}.`,
                    });
                    break;
                }
            }
        }
    }
    // Saspīlētie pirmie (svarīgākie), tad pēc orba
    out.sort((x, y) => (x.harmony - y.harmony) || (x.orb - y.orb));
    return out;
}

const cap = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : s;

// ── SVG riteņa zīmējums (melnbalts pamats + krāsainas situācijas) ─────────────
// Sadala uzrakstu leņķus tā, lai blakus esošie nepārklātos (1D izkliede pa loku)
function spreadAngles(lons, minGap) {
    const a = [...lons];
    for (let iter = 0; iter < 80; iter++) {
        let moved = false;
        for (let i = 1; i < a.length; i++) {
            const gap = a[i] - a[i - 1];
            if (gap < minGap) {
                const push = (minGap - gap) / 2;
                a[i] += push; a[i - 1] -= push; moved = true;
            }
        }
        if (!moved) break;
    }
    return a;
}

function renderWheelSVG(sky, general, personal, toggleHarmony = true, toggleTension = true, toggleConjunction = true, toggleHorizon = true, toggleRetrograde = true) {
    const lat = sky.lat ?? 0;
    let s = `<svg viewBox="0 0 ${VB} ${VB}" xmlns="http://www.w3.org/2000/svg" style="width:100%;max-width:720px;height:auto;font-family:'Outfit',sans-serif;">`;
    // foni
    s += `<circle cx="${CX}" cy="${CY}" r="${R_INNER}" fill="#fafafa" stroke="none"/>`;
    s += `<circle cx="${CX}" cy="${CY}" r="${R_OUTER}" fill="none" stroke="#111" stroke-width="1.5"/>`;
    s += `<circle cx="${CX}" cy="${CY}" r="${R_RIMIN}" fill="none" stroke="#aaa" stroke-width="0.8"/>`;
    s += `<circle cx="${CX}" cy="${CY}" r="${R_INNER}" fill="none" stroke="#111" stroke-width="1.2"/>`;

    // zodiaka zīmes (12 segmenti) + nosaukums zem glifa
    for (let i = 0; i < 12; i++) {
        const b0 = polar(i * 30, R_RIMIN), b1 = polar(i * 30, R_OUTER);
        s += `<line x1="${b0.x.toFixed(1)}" y1="${b0.y.toFixed(1)}" x2="${b1.x.toFixed(1)}" y2="${b1.y.toFixed(1)}" stroke="#aaa" stroke-width="0.7"/>`;
        const g = polar(i * 30 + 15, R_SIGN);
        s += `<text x="${g.x.toFixed(1)}" y="${g.y.toFixed(1)}" font-size="20" fill="#222" text-anchor="middle" dominant-baseline="central">${SIGN_GLYPH[i]}</text>`;
    }
    // 360° grādu skala: skaitļi ik 30°, taktis ik 10° (lieli) / 5° (mazi)
    for (let d = 0; d < 360; d += 5) {
        const lvl = d % 30 === 0 ? 11 : (d % 10 === 0 ? 7 : 4);
        const p0 = polar(d, R_RIMIN), p1 = polar(d, R_RIMIN - lvl);
        s += `<line x1="${p0.x.toFixed(1)}" y1="${p0.y.toFixed(1)}" x2="${p1.x.toFixed(1)}" y2="${p1.y.toFixed(1)}" stroke="#bbb" stroke-width="0.6"/>`;
    }
    for (let d = 0; d < 360; d += 30) {
        const np = polar(d, R_DEGNUM);
        s += `<text x="${np.x.toFixed(1)}" y="${np.y.toFixed(1)}" font-size="10" fill="#888" text-anchor="middle" dominant-baseline="central">${d}°</text>`;
    }

    // horizonta orientieri (ASC/DSC/MC/IC) + debespušu virzieni
    // ASC=Austrumi (lec), DSC=Rietumi (riet), MC=Dienvidi (z. puslodē), IC=Ziemeļi.
    if (toggleHorizon) {
        const dirN = lat >= 0;
        const axes = [
            { lon:sky.asc, t:"ASC", dir:"Austrumi ↑" },
            { lon:sky.dsc, t:"DSC", dir:"Rietumi" },
            { lon:sky.mc,  t:"MC",  dir: dirN ? "Dienvidi" : "Ziemeļi" },
            { lon:sky.ic,  t:"IC",  dir: dirN ? "Ziemeļi" : "Dienvidi" },
        ];
        for (const ax of axes) {
            const p0 = polar(ax.lon, R_INNER), p1 = polar(ax.lon, R_RIMIN);
            s += `<line x1="${p0.x.toFixed(1)}" y1="${p0.y.toFixed(1)}" x2="${p1.x.toFixed(1)}" y2="${p1.y.toFixed(1)}" stroke="#999" stroke-width="0.8" stroke-dasharray="3 3"/>`;
            const lp = polar(ax.lon, R_INNER + 13);
            s += `<text x="${lp.x.toFixed(1)}" y="${lp.y.toFixed(1)}" font-size="10" font-weight="700" fill="#111" text-anchor="middle" dominant-baseline="central">${ax.t}</text>`;
            const dp = polar(ax.lon, R_OUTER + 16);
            s += `<text x="${dp.x.toFixed(1)}" y="${dp.y.toFixed(1)}" font-size="13" font-weight="800" fill="#0f172a" text-anchor="middle" dominant-baseline="central">${ax.dir}</text>`;
        }
    }

    // personalizēto natālo punktu svītriņas uz malas
    const seenNatal = new Set();
    for (const t of personal) {
        const key = t.natal + Math.round(t.natalLon);
        if (seenNatal.has(key)) continue; seenNatal.add(key);
        const p0 = polar(t.natalLon, R_OUTER), pi = polar(t.natalLon, R_RIMIN);
        s += `<line x1="${pi.x.toFixed(1)}" y1="${pi.y.toFixed(1)}" x2="${p0.x.toFixed(1)}" y2="${p0.y.toFixed(1)}" stroke="${t.color}" stroke-width="2.4" opacity="0.85"/>`;
    }

    // planētu atzīmju pozīcijas (precīzais garums) — vajadzīgas aspektu līnijām
    const markR = b => b.above ? R_ABOVE_MARK : R_BELOW_MARK;
    const mpos = {};
    sky.bodies.forEach(b => { mpos[b.name] = polar(b.lon, markR(b)); });

    // aspektu līnijas starp planētām (krāsainas)
    for (const g of general) {
        if (g.kind !== "aspect") continue;
        
        // Pārbaudām vadīklas
        if (g.harmony > 0 && !toggleHarmony) continue;
        if (g.harmony < 0 && !toggleTension) continue;
        if (g.harmony === 0 && !toggleConjunction) continue;
        
        const pa = mpos[g.a.name], pb = mpos[g.b.name];
        const dash = g.harmony < 0 ? '5 4' : '0';
        s += `<line x1="${pa.x.toFixed(1)}" y1="${pa.y.toFixed(1)}" x2="${pb.x.toFixed(1)}" y2="${pb.y.toFixed(1)}" stroke="${g.color}" stroke-width="1.5" stroke-dasharray="${dash}" opacity="0.7"/>`;
    }

    // situāciju izcēluma krāsa katram ķermenim (leņķiskais/retro/aspekts)
    const highlight = {};
    for (const g of general) {
        if (g.kind === "angular" && toggleHorizon) highlight[g.a.name] = g.color;
        if (g.kind === "retro" && toggleRetrograde) highlight[g.a.name] = g.color;
    }

    // ķermeņi ar NOSAUKUMIEM (atzīme precīzajā garumā + dekliķēts uzraksts + līderlīnija)
    const drawBand = (list, markerR, labR, minGap) => {
        const sorted = [...list].sort((x, y) => x.lon - y.lon);
        const labAng = spreadAngles(sorted.map(b => b.lon), minGap);
        sorted.forEach((b, i) => {
            const m = polar(b.lon, markerR);
            const L = polar(labAng[i], labR);
            const hc = highlight[b.name];
            // līderlīnija no atzīmes uz uzrakstu
            s += `<line x1="${m.x.toFixed(1)}" y1="${m.y.toFixed(1)}" x2="${L.x.toFixed(1)}" y2="${L.y.toFixed(1)}" stroke="#cbd5e1" stroke-width="0.8"/>`;
            // atzīme (mazs glifs precīzajā vietā) + izcēlums
            if (hc) s += `<circle cx="${m.x.toFixed(1)}" cy="${m.y.toFixed(1)}" r="11" fill="none" stroke="${hc}" stroke-width="2"/>`;
            s += `<circle cx="${m.x.toFixed(1)}" cy="${m.y.toFixed(1)}" r="2.2" fill="#111"/>`;
            s += `<text x="${m.x.toFixed(1)}" y="${(m.y - 9).toFixed(1)}" font-size="13" fill="#444" text-anchor="middle" dominant-baseline="central">${b.glyph}</text>`;
            // uzraksts: NOSAUKUMS + grāds zīmē (lasāmi) — ar hover (multireader) tekstu
            const sign = SIGN_NAME[Math.floor(b.lon / 30)];
            const degTxt = `${Math.floor(b.lon % 30)}° ${sign}${b.retro && toggleRetrograde ? " ℞" : ""}`;
            const tip = `${NAME_LV[b.name]} — ${LIFE[b.name]}. ${degTxt}, ${b.above ? "virs horizonta (redzams debesīs)" : "aiz horizonta"}.`;
            s += `<g><title>${tip}</title>`;
            s += `<text x="${L.x.toFixed(1)}" y="${(L.y - 5).toFixed(1)}" font-size="13" font-weight="700" fill="#0f172a" text-anchor="middle" dominant-baseline="central">${NAME_LV[b.name]}</text>`;
            s += `<text x="${L.x.toFixed(1)}" y="${(L.y + 8).toFixed(1)}" font-size="9.5" fill="#64748b" text-anchor="middle" dominant-baseline="central">${degTxt}</text>`;
            s += `</g>`;
        });
    };
    drawBand(sky.bodies.filter(b => b.above), R_ABOVE_MARK, R_ABOVE_LAB, 17);
    drawBand(sky.bodies.filter(b => !b.above), R_BELOW_MARK, R_BELOW_LAB, 15);

    // centra zīmes
    s += `<text x="${CX}" y="${(CY - 8).toFixed(1)}" font-size="13" fill="#111" text-anchor="middle" font-weight="800">VIRS HORIZONTA</text>`;
    s += `<text x="${CX}" y="${(CY + 9).toFixed(1)}" font-size="10" fill="#777" text-anchor="middle">redzami debesīs šobrīd</text>`;
    const ringLbl = polar(sky.ic, (R_INNER + R_RIMIN) / 2);
    s += `<text x="${ringLbl.x.toFixed(1)}" y="${ringLbl.y.toFixed(1)}" font-size="10" fill="#aaa" text-anchor="middle">aiz horizonta</text>`;

    s += `</svg>`;
    return s;
}

function renderSidebarScale(items, maxOrb = 8.0) {
    if (!items || items.length === 0) return "";
    
    let wHarmonic = 0;
    let wTense = 0;
    let wNeutral = 0;
    
    items.forEach(item => {
        let w = 0.5;
        if (item.orb !== undefined) {
            w = Math.max(0.1, 1 - item.orb / maxOrb);
        }
        if (item.harmony > 0) {
            wHarmonic += w;
        } else if (item.harmony < 0) {
            wTense += w;
        } else {
            wNeutral += w;
        }
    });
    
    const total = wHarmonic + wTense + wNeutral;
    if (total <= 0) return "";
    
    const pctHarmonic = Math.round((wHarmonic / total) * 100);
    const pctTense = Math.round((wTense / total) * 100);
    const pctNeutral = Math.max(0, 100 - pctHarmonic - pctTense);
    
    return `
        <div style="margin-bottom:0.6rem; padding:4px 0 8px 0; border-bottom:1px solid #e2e8f0; font-family:'Outfit',sans-serif; width:100%; box-sizing:border-box;">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.72rem; font-weight:800; line-height:1.2;">
                <span style="color:#16a34a;">Harmonija ${pctHarmonic}%</span>
                <span style="color:#7c3aed;">Neitrāls ${pctNeutral}%</span>
                <span style="color:#dc2626;">Spriedze ${pctTense}%</span>
            </div>
            <div style="height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden; display:flex; width:100%;">
                <div style="width:${pctHarmonic}%; background:#16a34a; height:100%;"></div>
                <div style="width:${pctNeutral}%; background:#7c3aed; height:100%;"></div>
                <div style="width:${pctTense}%; background:#dc2626; height:100%;"></div>
            </div>
        </div>
    `;
}

function card(it, num) {
    let maxOrb = 8.0;
    if (it.kind === "transit") maxOrb = 6.0;
    else if (it.kind === "angular") maxOrb = 2.5;

    let pct = 100;
    let label = "Spēks: <b>100%</b> (fona)";
    if (it.orb !== undefined) {
        pct = Math.round(Math.max(0, 1 - it.orb / maxOrb) * 100);
        label = `Spēks: <b>${pct}%</b>`;
    }

    const numPrefix = num ? `${num}. ` : "";

    return `<div style="border-left:3px solid ${it.color}; padding: 6px 0 6px 8px; border-bottom: 1px solid #e2e8f0; margin-bottom: 4px;">
        <div style="font-weight:700;color:#1e293b;font-size:0.88rem;">${numPrefix}${it.icon} ${it.title}</div>
        <div style="font-size:0.72rem;color:${it.color};font-weight:600;margin:1px 0 2px;">${it.sub}</div>
        <div style="font-size:0.8rem;color:#334155;line-height:1.3;">${it.plain}</div>
        <div style="font-size:0.78rem;color:#475569;margin-top:2px;"><b>Tavā dzīvē:</b> ${it.life}</div>
        <div style="font-size:0.75rem; color:#64748b; margin-top:3px; display:flex; align-items:center; gap:6px;">
            <span>${label}</span>
            <div style="flex:1; height:4px; background:#e2e8f0; border-radius:2px; overflow:hidden; max-width:80px;">
                <div style="width:${pct}%; background:${it.color}; height:100%;"></div>
            </div>
        </div>
    </div>`;
}


// ── Stāvoklis + render cikls ─────────────────────────────────────────────────
let _profile = null;
let _state = { zodiac:'tropical', offsetMin:0, electional:{ horizon:30 }, calendar:{ startOffset:0, span:9 } };

function natalFromProfile(profile) {
    const pl = profile?.western?.planets;
    if (!pl) return null;
    // Ja dzimšanas laiks nezināms, Ascendants/MC nav ticami (atkarīgi tikai no laika) — izlaižam.
    const timeUnknown = profile?.birth_info?.isTimeUnknown;
    const out = {};
    for (const k of Object.keys(pl)) {
        if (timeUnknown && (k === 'Ascendant' || k === 'MC')) continue;
        if (pl[k] && pl[k].longitude !== undefined) out[k] = norm(pl[k].longitude);
    }
    return out;
}

function effectiveDate() {
    const dateEl = document.getElementById('karte-date');
    const timeEl = document.getElementById('karte-time');
    const locEl  = document.getElementById('karte-loc');
    const tz = locEl?.getAttribute('data-timezone');
    const ds = dateEl?.value, ts = timeEl?.value || "12:00";
    let base;
    if (tz && window.moment) {
        try { base = window.moment.tz(`${ds} ${ts}`, "YYYY-MM-DD HH:mm", tz); }
        catch (e) { base = null; }
    }
    if (base) return base.clone().add(_state.offsetMin, 'minutes').toDate();
    // rezerve: pārlūka lokālais laiks
    return new Date(new Date(`${ds}T${ts}`).getTime() + _state.offsetMin * 60000);
}

function fmtWhen(d, tz) {
    try {
        if (tz && window.moment) return window.moment.tz(d, tz).format("DD.MM.YYYY HH:mm");
    } catch (e) {}
    return d.toLocaleString('lv-LV');
}

function computeHeliocentricData(jd, skyGeocentric) {
    const planets = {
        "Merkurs": 2,
        "Venera": 3,
        "Marss": 4,
        "Jupiters": 5,
        "Saturns": 6,
        "Urans": 7,
        "Neptuns": 8,
        "Plutons": 9
    };
    
    const flagHelio = 260 | 8; // 260 = Moshier + Speed, 8 = Heliocentric
    const results = {};

    // Heliocentriskās planētas no swisseph ir TROPISKAS. Lai viss diagrammas kadrs sakristu,
    // Zeme/Mēness/mezgli jānovieto pēc TROPISKĀ garuma (lonTrop), nevis displeja (kas var būt siderāls).
    // Zeme heliocentriskajā sistēmā ir tieši pretī ģeocentriskajai (tropiskajai) Saulei.
    const sunGeo = skyGeocentric.bodies.find(b => b.name === "Saule");
    results["Zeme"] = sunGeo ? norm(sunGeo.lonTrop + 180) : 0;

    for (const [name, pId] of Object.entries(planets)) {
        try {
            const data = swisseph.calculatePosition(jd, pId, flagHelio);
            results[name] = data.longitude;
        } catch (e) {
            console.warn(`Neizdevās aprēķināt heliocentrisko pozīciju priekš ${name}:`, e);
            const geo = skyGeocentric.bodies.find(b => b.name === name);
            results[name] = geo ? geo.lonTrop : 0;
        }
    }

    // Mēness un mezgli — shematiski ap Zemi (ģeocentriski objekti), tropiskajā kadrā.
    const moonGeo = skyGeocentric.bodies.find(b => b.name === "Meness");
    results["Meness"] = moonGeo ? moonGeo.lonTrop : 0;
    const rahuGeo = skyGeocentric.bodies.find(b => b.name === "Rahu");
    results["Rahu"] = rahuGeo ? rahuGeo.lonTrop : 0;
    const ketuGeo = skyGeocentric.bodies.find(b => b.name === "Ketu");
    results["Ketu"] = ketuGeo ? ketuGeo.lonTrop : 180;

    return results;
}

function renderSolarSystemSVG(helio, sky, general, toggleHarmony = true, toggleTension = true, toggleConjunction = true, toggleHorizon = true, toggleRetrograde = true) {
    const VB_H = 800, CX_H = 400, CY_H = 400;
    
    let s = `<svg viewBox="0 0 ${VB_H} ${VB_H}" xmlns="http://www.w3.org/2000/svg" style="width:100%; max-width:720px; height:auto; font-family:'Outfit',sans-serif; background:#ffffff;">`;
    
    // Fons un ārējā mala (rādiuss 354, lai saskaņotu ar citiem grafikiem)
    s += `<circle cx="${CX_H}" cy="${CY_H}" r="354" fill="#fafafa" stroke="#1e293b" stroke-width="1.8" />`;
    
    // Orbītu parametri (rādiuss, krāsa, izmērs diagrammā, mērogots par 1.573)
    const orbits = [
        { name: "Merkurs", r: 50, col: "#64748b", size: 5.5 },
        { name: "Venera", r: 78, col: "#ea580c", size: 7.0 },
        { name: "Zeme", r: 107, col: "#2563eb", size: 8.0 },
        { name: "Marss", r: 138, col: "#dc2626", size: 6.0 },
        { name: "Jupiters", r: 176, col: "#b45309", size: 11.5 },
        { name: "Saturns", r: 217, col: "#d97706", size: 10.0, ring: true },
        { name: "Urans", r: 258, col: "#06b6d4", size: 8.5 },
        { name: "Neptuns", r: 299, col: "#1d4ed8", size: 8.0 },
        { name: "Plutons", r: 337, col: "#475569", size: 4.5 }
    ];
    
    // Uzzīmējam orbītu apļus
    orbits.forEach(orb => {
        s += `<circle cx="${CX_H}" cy="${CY_H}" r="${orb.r}" fill="none" stroke="#e2e8f0" stroke-width="1.0" stroke-dasharray="4 4" />`;
    });
    
    // Uzzīmējam fonā subtīlas zodiaka asis ik pēc 30 grādiem
    for (let d = 0; d < 360; d += 30) {
        const rad = 354;
        const a = (180 - d) * Math.PI / 180;
        const x2 = CX_H + rad * Math.cos(a);
        const y2 = CY_H - rad * Math.sin(a);
        s += `<line x1="${CX_H}" y1="${CY_H}" x2="${x2.toFixed(1)}" y2="${y2.toFixed(1)}" stroke="#cbd5e1" stroke-width="0.8" opacity="0.35" />`;
    }
    
    // Pre-calculate planet coordinates
    const planetCoords = {};
    orbits.forEach(orb => {
        const lon = helio[orb.name];
        if (lon === undefined) return;
        const a = (180 - lon) * Math.PI / 180;
        planetCoords[orb.name] = {
            x: CX_H + orb.r * Math.cos(a),
            y: CY_H - orb.r * Math.sin(a),
            lon: lon,
            angle: a
        };
    });

    // Pievienojam Sauli, Mēnesi, Rahu, Ketu pie planetCoords, lai varētu zīmēt ietekmju saites un atrast koordinātas
    planetCoords["Saule"] = {
        x: CX_H,
        y: CY_H,
        lon: 0,
        angle: 0
    };
    
    if (planetCoords["Zeme"]) {
        const ex = planetCoords["Zeme"].x;
        const ey = planetCoords["Zeme"].y;
        
        // Mēness (attālums mērogots līdz 14)
        if (helio["Meness"] !== undefined) {
            const moonLon = helio["Meness"];
            const moonDist = 14.0;
            const moonAngle = (180 - moonLon) * Math.PI / 180;
            planetCoords["Meness"] = {
                x: ex + moonDist * Math.cos(moonAngle),
                y: ey - moonDist * Math.sin(moonAngle),
                lon: moonLon,
                angle: moonAngle
            };
        }
        
        // Rahu un Ketu (attālums mērogots līdz 28)
        if (helio["Rahu"] !== undefined) {
            const rahuLon = helio["Rahu"];
            const ra = (180 - rahuLon) * Math.PI / 180;
            planetCoords["Rahu"] = {
                x: ex + 28 * Math.cos(ra),
                y: ey - 28 * Math.sin(ra),
                lon: rahuLon,
                angle: ra
            };
            
            const ketuLon = helio["Ketu"] !== undefined ? helio["Ketu"] : (rahuLon + 180) % 360;
            const ka = (180 - ketuLon) * Math.PI / 180;
            planetCoords["Ketu"] = {
                x: ex + 28 * Math.cos(ka),
                y: ey - 28 * Math.sin(ka),
                lon: ketuLon,
                angle: ka
            };
        }
    }
    
    // 1. Zodiaka asis / horizonta asis no Zemes (leņķiskie notikumi)
    if (toggleHorizon && planetCoords["Zeme"]) {
        const ex = planetCoords["Zeme"].x;
        const ey = planetCoords["Zeme"].y;
        
        // Tropiskais kadrs (Saules sistēma vienmēr tropiska) — saskaņā ar heliocentriskajām planētām.
        const ascT = sky.ascTrop ?? sky.asc;
        const mcT  = sky.mcTrop ?? sky.mc;
        const a_asc = (180 - ascT) * Math.PI / 180;
        const a_dsc = (180 - (ascT + 180)) * Math.PI / 180;
        const a_mc = (180 - mcT) * Math.PI / 180;
        const a_ic = (180 - (mcT + 180)) * Math.PI / 180;
        
        const x_asc = CX_H + 354 * Math.cos(a_asc);
        const y_asc = CY_H - 354 * Math.sin(a_asc);
        const x_dsc = CX_H + 354 * Math.cos(a_dsc);
        const y_dsc = CY_H - 354 * Math.sin(a_dsc);
        const x_mc = CX_H + 354 * Math.cos(a_mc);
        const y_mc = CY_H - 354 * Math.sin(a_mc);
        const x_ic = CX_H + 354 * Math.cos(a_ic);
        const y_ic = CY_H - 354 * Math.sin(a_ic);
        
        // Horizon line (gold)
        s += `<line x1="${x_asc.toFixed(1)}" y1="${y_asc.toFixed(1)}" x2="${x_dsc.toFixed(1)}" y2="${y_dsc.toFixed(1)}" stroke="${COL.angular}" stroke-width="2.2" opacity="0.65" />`;
        
        // Meridian line (dashed gold)
        s += `<line x1="${x_mc.toFixed(1)}" y1="${y_mc.toFixed(1)}" x2="${x_ic.toFixed(1)}" y2="${y_ic.toFixed(1)}" stroke="${COL.angular}" stroke-width="1.5" stroke-dasharray="4 4" opacity="0.5" />`;
        
        // Labels ASC / DSC and MC / IC near the edge (at 370 radius, slightly outside the boundary)
        s += `<text x="${(CX_H + 370 * Math.cos(a_asc)).toFixed(1)}" y="${(CY_H - 370 * Math.sin(a_asc)).toFixed(1)}" font-size="11" fill="${COL.angular}" font-weight="800" text-anchor="middle" dominant-baseline="central">ASC</text>`;
        s += `<text x="${(CX_H + 370 * Math.cos(a_dsc)).toFixed(1)}" y="${(CY_H - 370 * Math.sin(a_dsc)).toFixed(1)}" font-size="11" fill="${COL.angular}" font-weight="800" text-anchor="middle" dominant-baseline="central">DSC</text>`;
        s += `<text x="${(CX_H + 370 * Math.cos(a_mc)).toFixed(1)}" y="${(CY_H - 370 * Math.sin(a_mc)).toFixed(1)}" font-size="11" fill="${COL.angular}" font-weight="800" text-anchor="middle" dominant-baseline="central">MC</text>`;
        s += `<text x="${(CX_H + 370 * Math.cos(a_ic)).toFixed(1)}" y="${(CY_H - 370 * Math.sin(a_ic)).toFixed(1)}" font-size="11" fill="${COL.angular}" font-weight="800" text-anchor="middle" dominant-baseline="central">IC</text>`;
    }
    
    // 2. Rahu un Ketu (Mēness mezglu ass ap Zemi)
    if (planetCoords["Rahu"] && planetCoords["Ketu"]) {
        const rx = planetCoords["Rahu"].x;
        const ry = planetCoords["Rahu"].y;
        const kx = planetCoords["Ketu"].x;
        const ky = planetCoords["Ketu"].y;
        
        // Draw the axis line
        s += `<line x1="${kx.toFixed(1)}" y1="${ky.toFixed(1)}" x2="${rx.toFixed(1)}" y2="${ry.toFixed(1)}" stroke="#6d28d9" stroke-width="1.0" stroke-dasharray="3 3" opacity="0.45" />`;
        
        // Draw Rahu and Ketu glyphs (slightly larger circles and text)
        s += `<circle cx="${rx.toFixed(1)}" cy="${ry.toFixed(1)}" r="7" fill="#f5f3ff" stroke="#6d28d9" stroke-width="1.0" />`;
        s += `<text x="${rx.toFixed(1)}" y="${(ry + 0.3).toFixed(1)}" font-size="9" fill="#6d28d9" text-anchor="middle" dominant-baseline="central" font-weight="700">☊</text>`;
        
        s += `<circle cx="${kx.toFixed(1)}" cy="${ky.toFixed(1)}" r="7" fill="#f5f3ff" stroke="#6d28d9" stroke-width="1.0" />`;
        s += `<text x="${kx.toFixed(1)}" y="${(ky + 0.3).toFixed(1)}" font-size="9" fill="#6d28d9" text-anchor="middle" dominant-baseline="central" font-weight="700">☋</text>`;
    }
    
    // 3. Geocentrisko aspektu saites starp planētām
    for (const g of general) {
        if (g.kind !== "aspect") continue;
        
        // Pārbaudām vadīklas
        if (g.harmony > 0 && !toggleHarmony) continue;
        if (g.harmony < 0 && !toggleTension) continue;
        if (g.harmony === 0 && !toggleConjunction) continue;
        
        const c1 = planetCoords[g.a.name];
        const c2 = planetCoords[g.b.name];
        if (c1 && c2) {
            const maxOrb = 8.0;
            const ratio = Math.max(0, 1 - (g.orb || 0) / maxOrb);
            const strokeWidth = 1.2 + 1.6 * ratio;
            const opacity = (0.35 + 0.45 * ratio).toFixed(2);
            
            const dash = g.harmony < 0 ? "4 4" : (g.harmony === 0 ? "2 2" : "none");
            
            s += `<line x1="${c1.x.toFixed(1)}" y1="${c1.y.toFixed(1)}" x2="${c2.x.toFixed(1)}" y2="${c2.y.toFixed(1)}" stroke="${g.color}" stroke-width="${strokeWidth.toFixed(1)}" stroke-dasharray="${dash}" opacity="${opacity}" />`;
        }
    }
    
    // Saule centrā (mērogota lielāka)
    s += `
    <defs>
        <radialGradient id="karte-sunGlow" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#fef08a" />
            <stop offset="70%" stop-color="#eab308" />
            <stop offset="100%" stop-color="#ca8a04" stop-opacity="0" />
        </radialGradient>
    </defs>`;
    s += `<circle cx="${CX_H}" cy="${CY_H}" r="28" fill="url(#karte-sunGlow)" opacity="0.75" />`;
    s += `<circle cx="${CX_H}" cy="${CY_H}" r="12.5" fill="#eab308" stroke="#ca8a04" stroke-width="1.5" />`;
    
    // Uzzīmējam planētas
    orbits.forEach(orb => {
        const coord = planetCoords[orb.name];
        if (!coord) return;
        
        const x = coord.x;
        const y = coord.y;
        const a = coord.angle;
        
        s += `<g>`;
        if (orb.ring) {
            // Saturnam uzzīmējam smalku slīpu elipses gredzenu fonā (mērogots)
            s += `<ellipse cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" rx="${(orb.size * 1.6).toFixed(1)}" ry="${(orb.size * 0.6).toFixed(1)}" fill="none" stroke="#d97706" stroke-width="2.5" transform="rotate(-15, ${x.toFixed(1)}, ${y.toFixed(1)})" opacity="0.8" />`;
        }
        
        // Ja planēta ir retrogrāda (pēc ģeocentriskajiem datiem)
        const geoBody = sky.bodies.find(b => b.name === orb.name);
        const isRetro = geoBody ? geoBody.retro : false;
        const isAngular = general.some(g => g.kind === "angular" && g.a.name === orb.name);
        
        if (isAngular && toggleHorizon) {
            // Zelta gredzens ap leņķiskajām planētām (mērogots)
            s += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="${(orb.size + 3.5).toFixed(1)}" fill="none" stroke="${COL.angular}" stroke-width="2.2" opacity="0.9" />`;
        }
        
        if (isRetro && toggleRetrograde) {
            // Indigo gredzens retrogrādām planētām (mērogots)
            const retroR = orb.size + (isAngular && toggleHorizon ? 7.0 : 4.0);
            s += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="${retroR.toFixed(1)}" fill="none" stroke="${COL.retro}" stroke-width="1.8" stroke-dasharray="3 2" opacity="0.8" />`;
        }
        
        s += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="${orb.size.toFixed(1)}" fill="${orb.col}" stroke="#ffffff" stroke-width="1.2" />`;
        
        // Zemei pievienojam mazu Mēness aplīti blakus (mērogots)
        if (orb.name === "Zeme" && planetCoords["Meness"]) {
            const mx = planetCoords["Meness"].x;
            const my = planetCoords["Meness"].y;
            s += `<circle cx="${mx.toFixed(1)}" cy="${my.toFixed(1)}" r="2.5" fill="#94a3b8" />`;
        }
        
        // Planetārais teksts (mērogoti offsets un font-size, un rādām smukus LV nosaukumus)
        let ringOffset = 8;
        if (isRetro && toggleRetrograde) {
            ringOffset = isAngular && toggleHorizon ? 16 : 12;
        } else if (isAngular && toggleHorizon) {
            ringOffset = 11;
        }
        const textDist = orb.size + ringOffset;
        
        const tx = x + textDist * Math.cos(a);
        const ty = y - textDist * Math.sin(a);
        
        let textAnchor = "middle";
        const cosVal = Math.cos(a);
        if (cosVal > 0.3) textAnchor = "start";
        else if (cosVal < -0.3) textAnchor = "end";
        
        let nameText = NAME_LV[orb.name] || orb.name;
        if (isRetro && toggleRetrograde) nameText += " ℞";
        
        s += `<text x="${tx.toFixed(1)}" y="${ty.toFixed(1)}" font-size="12.5" font-weight="700" fill="#1e293b" text-anchor="${textAnchor}" dominant-baseline="central" stroke="#ffffff" stroke-width="3.5" stroke-linejoin="round" paint-order="stroke fill">${nameText}</text>`;
        s += `</g>`;
    });
    
    s += `</svg>`;
    return s;
}

function redraw() {
    const locEl = document.getElementById('karte-loc');
    const svgBox = document.getElementById('sky-view-svg-box');
    const whenEl = document.getElementById('karte-when');
    
    if (!locEl || !svgBox) return;

    let lat = parseFloat(locEl.getAttribute('data-lat'));
    let lon = parseFloat(locEl.getAttribute('data-lon'));
    let tz  = locEl.getAttribute('data-timezone');
    if (isNaN(lat) || isNaN(lon)) {
        svgBox.innerHTML = `<div style="color:#94a3b8;padding:1rem;">Izvēlies lokāciju no saraksta.</div>`;
        return;
    }

    let eff = effectiveDate();
    
    // Nolasām laika režīmu (Tranzīti / Dzimšanas karte)
    const dataModeNatal = document.getElementById('data-mode-natal')?.checked ?? false;
    let isNatalActive = false;
    if (dataModeNatal && _profile && _profile.birth_info) {
        const bi = _profile.birth_info;
        if (bi.date) {
            isNatalActive = true;
            lat = bi.lat ?? 56.9496;
            lon = bi.lon ?? 24.1052;
            tz  = bi.timezone ?? 'Europe/Riga';
            const timeStr = bi.time || '12:00';
            if (window.moment) {
                try {
                    eff = window.moment.tz(`${bi.date} ${timeStr}`, "YYYY-MM-DD HH:mm", tz).toDate();
                } catch(e) {
                    eff = new Date(`${bi.date}T${timeStr}`);
                }
            } else {
                eff = new Date(`${bi.date}T${timeStr}`);
            }
        }
    }

    if (whenEl) {
        if (isNatalActive) {
            whenEl.textContent = `Piedzimšanā: ${fmtWhen(eff, tz)}`;
        } else {
            whenEl.textContent = fmtWhen(eff, tz);
        }
    }

    // Natālajā režīmā laika ritināšana/datums/laiks neietekmē karti (fiksēts dzimšanas brīdis) → atspējojam.
    ['karte-slider', 'karte-date', 'karte-time', 'karte-now'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = isNatalActive;
    });
    const sliderWrap = document.querySelector('.karte-slider-wrap');
    if (sliderWrap) sliderWrap.style.opacity = isNatalActive ? '0.45' : '1';

    // Iegūstam vietējo datumu formātā YYYY-MM-DD priekš laikapstākļu joslas
    const dateEl = document.getElementById('karte-date');
    let dateStr = dateEl?.value || eff.toISOString().split('T')[0];
    if (tz && window.moment) {
        try { dateStr = window.moment.tz(eff, tz).format("YYYY-MM-DD"); } catch(e) {}
    }

    try {
        const sky = computeSky(eff, lat, lon, _state.zodiac);
        const general = detectGeneral(sky);
        // Natālajā režīmā karte JAU ir dzimšanas karte → tranzīts-pret-natālo būtu triviāls
        // (katra planēta konjunkcijā pati ar sevi). Tāpēc personalizēto slāni izlaižam.
        const personal = isNatalActive ? [] : detectPersonal(sky, natalFromProfile(_profile));
        
        const toggleHarmony = document.getElementById('toggle-harmony')?.checked ?? true;
        const toggleTension = document.getElementById('toggle-tension')?.checked ?? true;
        const toggleConjunction = document.getElementById('toggle-conjunction')?.checked ?? true;
        const toggleHorizon = document.getElementById('toggle-horizon')?.checked ?? true;
        const toggleRetrograde = document.getElementById('toggle-retrograde')?.checked ?? true;
        
        const skyView = _state.skyView || 'wheel';
        const titleEl = document.getElementById('sky-view-title');
        const descEl = document.getElementById('sky-view-desc');
        const legendEl = document.getElementById('sky-view-legend');
        const weatherBarBox = document.getElementById('cosmic-weather-bar');
        const leftZone = document.getElementById('sky-cards-left-zone');
        const rightZone = document.getElementById('sky-cards-right-zone');


        if (skyView === 'solar') {
            if (titleEl) titleEl.innerHTML = "🌌 Saules Sistēma (Heliocentriski no augšas)";
            if (descEl) descEl.innerHTML = "Planētu izvietojums reālajās orbītās ap Sauli šajā laika brīdī (tropiskais kadrs). Zeme rāda īsto orbītu; Mēness un mezgli attēloti shematiski ap Zemi.";
            
            const helio = computeHeliocentricData(sky.jd, sky);
            svgBox.innerHTML = renderSolarSystemSVG(helio, sky, general, toggleHarmony, toggleTension, toggleConjunction, toggleHorizon, toggleRetrograde);

            if (legendEl) legendEl.innerHTML = defaultLegendHTML();
        }
        else if (skyView === 'clock') {
            if (titleEl) titleEl.innerHTML = "🕰️ Kosmiskais Pulkstenis";
            if (descEl) descEl.innerHTML = "Pārorientēts pret horizontu un diennakts laiku. Saule un Mēness kā galvenie rādītāji, atspoguļojot reālu dienas un nakts ritmu.";
            
            svgBox.innerHTML = renderCosmicClockSVG(sky, general, personal, toggleHarmony, toggleTension, toggleConjunction, toggleHorizon, toggleRetrograde);
            
            if (legendEl) {
                legendEl.innerHTML = `
                    <div style="display:flex; flex-wrap:wrap; gap:1.5rem; justify-content:center; font-size:0.8rem; color:#475569; margin:0 auto; width:100%;">
                        <span style="display:inline-flex; align-items:center; gap:0.4rem;">
                            <span style="width:16px; height:8px; border:2px solid #16a34a; border-radius: 50% 50% 0 0 / 100% 100% 0 0; border-bottom: none; display:inline-block; vertical-align:middle;"></span>
                            <span>🟢 <b>Harmoniskā ietekme</b> (zaļi loki – plūstoša enerģija)</span>
                        </span>
                        <span style="display:inline-flex; align-items:center; gap:0.4rem;">
                            <span style="width:16px; height:8px; border:2px dashed #dc2626; border-radius: 50% 50% 0 0 / 100% 100% 0 0; border-bottom: none; display:inline-block; vertical-align:middle;"></span>
                            <span>🔴 <b>Saspringtā ietekme</b> (sarkani loki – izaicinājums)</span>
                        </span>
                    </div>`;
            }
        } 
        else if (skyView === 'wheel') {
            if (titleEl) titleEl.innerHTML = "🌌 Klasiskais Astroloģiskais Ritenis";
            if (descEl) descEl.innerHTML = "Tradicionālais 360° ritenis ar zodiaka fiksāciju un visām aspektu līnijām. Iekšējais disks parāda planētas virs horizonta, gredzens – aiz horizonta.";
            
            svgBox.innerHTML = renderWheelSVG(sky, general, personal, toggleHarmony, toggleTension, toggleConjunction, toggleHorizon, toggleRetrograde);

            if (legendEl) legendEl.innerHTML = defaultLegendHTML();
        }
        
        // Zīmējam laikapstākļu joslu (24h)
        if (weatherBarBox && _profile) {
            try {
                const dayData = buildDayConsensus(_profile, dateStr);
                weatherBarBox.innerHTML = renderWeatherBarHTML(dayData);
            } catch (err) {
                console.error("[KARTE] buildDayConsensus kļūda:", err);
                weatherBarBox.innerHTML = `<div style="color:#ef4444; font-size:0.85rem; padding:8px;">Neizdevās aprēķināt laikapstākļu datus šim datumam: ${err.message}</div>`;
            }
        } else if (weatherBarBox) {
            weatherBarBox.innerHTML = `<div style="color:#64748b; font-size:0.85rem; padding:8px; font-style:italic;">Ievadiet savus datus augšā, lai redzētu savu personīgo laikapstākļu joslu.</div>`;
        }

        // Zīmējam detalizētās kartītes
        if (leftZone && rightZone) {
            const sortG = [...general].sort((a, b) => {
                const oA = a.orb !== undefined ? a.orb : 99;
                const oB = b.orb !== undefined ? b.orb : 99;
                return oA - oB;
            });
            const sortP = [...personal].sort((a, b) => {
                const oA = a.orb !== undefined ? a.orb : 99;
                const oB = b.orb !== undefined ? b.orb : 99;
                return oA - oB;
            });

            const displayG = sortG.slice(0, 30);
            const displayP = sortP.slice(0, 30);

            const gScale = renderSidebarScale(displayG, 8.0);
            const pScale = renderSidebarScale(displayP, 6.0);

            const gHtml = displayG.length ? displayG.map((item, idx) => card(item, idx + 1)).join("") : `<div style="color:#94a3b8;font-size:0.85rem;padding:8px;text-align:center;">Šobrīd nav aktīvu ietekmju — mierīgas debesis.</div>`;
            const pHtml = displayP.length ? displayP.map((item, idx) => card(item, idx + 1)).join("") : `<div style="color:#94a3b8;font-size:0.85rem;padding:8px;text-align:center;">Pašreizējās debesis šobrīd neveido ciešus aspektus ar tavu dzimšanas karti.</div>`;

            // Natālajā režīmā kreisais panelis rāda pašu dzimšanas karti, labais — paskaidrojumu
            // (tranzīts-pret-natālo natālajā kartē būtu triviāls).
            const leftTitle = isNatalActive ? "🌟 Tava dzimšanas karte" : "🌌 Debesis tagad";
            leftZone.innerHTML = `
                <h3 style="font-size:1.05rem;color:#0f172a;margin:0 0 .4rem;text-align:center;font-weight:800;border-bottom:1px solid #f1f5f9;padding-bottom:0.4rem;">${leftTitle}</h3>
                ${gScale}
                <div class="sky-cards-scroll-container">${gHtml}</div>`;

            if (isNatalActive) {
                rightZone.innerHTML = `
                    <h3 style="font-size:1.05rem;color:#0f172a;margin:0 0 .4rem;text-align:center;font-weight:800;border-bottom:1px solid #f1f5f9;padding-bottom:0.4rem;">🫵 Tev personīgi</h3>
                    <div style="color:#64748b;font-size:0.85rem;padding:10px 8px;text-align:center;line-height:1.45;">
                        Šis ir <b>tavs dzimšanas attēls</b>, tāpēc “pašreizējās debesis pret dzimšanas karti” šeit nav piemērojams.<br><br>
                        Pārslēdzies uz <b>🔵 Šobrīd debesīs (Tranzīti)</b>, lai redzētu, kā pašreizējās planētas ietekmē tavu karti.
                    </div>`;
            } else {
                rightZone.innerHTML = `
                    <h3 style="font-size:1.05rem;color:#0f172a;margin:0 0 .4rem;text-align:center;font-weight:800;border-bottom:1px solid #f1f5f9;padding-bottom:0.4rem;">🫵 Tev personīgi</h3>
                    ${pScale}
                    <div class="sky-cards-scroll-container">${pHtml}</div>`;
            }
        }
    } catch (e) {
        console.error("[KARTE] redraw kļūda:", e);
        svgBox.innerHTML = `<div style="color:#dc2626;padding:1rem;">Kļūda kartes zīmēšanā: ${e.message}</div>`;
    }
}

// Atjauno zodiaka īso hint + virsrakstu/aprakstu virs kartes pēc _state.zodiac
function applyZodiacMeta() {
    const m = ZODIAC_META[_state.zodiac];
    const zh = document.getElementById('karte-zhint');
    if (zh) zh.textContent = m.hint;
    const h = document.getElementById('karte-headline');
    if (h) h.innerHTML = `<div class="kh-title">${m.title}</div><div class="kh-desc">${m.desc}</div>`;
}



function renderCosmicClockSVG(sky, general, personal, toggleHarmony = true, toggleTension = true, toggleConjunction = true, toggleHorizon = true, toggleRetrograde = true) {
    const lat = sky.lat ?? 0;
    const getRotatedLon = (lon) => norm(sky.asc - lon);

    let s = `<svg viewBox="0 0 ${VB} ${VB}" xmlns="http://www.w3.org/2000/svg" style="width:100%; max-width:720px; height:auto; font-family:'Outfit',sans-serif;">`;
    
    // Pattern & stili
    s += `
    <defs>
        <pattern id="karte-diagonalStripes" width="10" height="10" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
            <line x1="0" y1="0" x2="0" y2="10" stroke="#cbd5e1" stroke-width="1.8"/>
        </pattern>
        <style>
            .axis-label { font-size: 10px; font-weight: 800; fill: #1e293b; text-anchor: middle; dominant-baseline: central; letter-spacing: 1px; }
            .horizon-label { font-size: 11px; font-weight: 800; fill: #1e293b; dominant-baseline: central; letter-spacing: 0.5px; }
        </style>
    </defs>`;

    // Foni: augšā = VIRS horizonta (debess puse, balta), apakšā = AIZ horizonta (zem zemes, slīpsvītras).
    // (Nav "diena/nakts" — naktī zvaigznes joprojām ir virs horizonta.)
    s += `<path d="M ${CX - R_OUTER} ${CY} A ${R_OUTER} ${R_OUTER} 0 0 1 ${CX + R_OUTER} ${CY} Z" fill="#ffffff" stroke="none"/>`;
    s += `<path d="M ${CX - R_OUTER} ${CY} A ${R_OUTER} ${R_OUTER} 0 0 0 ${CX + R_OUTER} ${CY} Z" fill="#ffffff" stroke="none"/>`;
    s += `<path d="M ${CX - R_OUTER} ${CY} A ${R_OUTER} ${R_OUTER} 0 0 0 ${CX + R_OUTER} ${CY} Z" fill="url(#karte-diagonalStripes)" stroke="none"/>`;

    // Koncentriskie gredzeni (skalas)
    s += `<circle cx="${CX}" cy="${CY}" r="300" fill="none" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3" opacity="0.35"/>`; // Ārējais
    s += `<circle cx="${CX}" cy="${CY}" r="230" fill="none" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3" opacity="0.35"/>`; // Vidējais
    s += `<circle cx="${CX}" cy="${CY}" r="160" fill="none" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3" opacity="0.35"/>`; // Iekšējais

    // Meditējoša cilvēka siluets centrā
    s += `
    <g opacity="0.4" stroke="#475569" stroke-linecap="round" stroke-linejoin="round">
        <!-- Galva -->
        <circle cx="400" cy="374" r="6" fill="none" stroke-width="1.8"/>
        <!-- Ķermenis -->
        <path d="M 388 408 C 388 394, 394 387, 400 387 C 406 387, 412 394, 412 408 C 414 416, 386 416, 388 408 Z" fill="none" stroke-width="1.8"/>
        <!-- Rokas -->
        <path d="M 391 395 C 386 399, 386 407, 395 410" fill="none" stroke-width="1.2"/>
        <path d="M 409 395 C 414 399, 414 407, 405 410" fill="none" stroke-width="1.2"/>
    </g>`;
    
    // Robežas
    s += `<circle cx="${CX}" cy="${CY}" r="${R_OUTER}" fill="none" stroke="#1e293b" stroke-width="1.8"/>`;
    s += `<circle cx="${CX}" cy="${CY}" r="${R_RIMIN}" fill="none" stroke="#cbd5e1" stroke-width="0.8" opacity="0.2"/>`;

    // Skalu paskaidrojošie teksti (tikai gaišajā augšpusē)
    s += `<text x="${CX + 10}" y="${CY - 165}" font-size="8.5" fill="#475569" font-weight="600" opacity="0.75">Iekšējais loks — dienas/emociju ritms (Mēness)</text>`;
    s += `<text x="${CX + 10}" y="${CY - 235}" font-size="8.5" fill="#475569" font-weight="600" opacity="0.75">Vidējais loks — gada/personīgais ritms (Saule, iekšējās planētas)</text>`;
    s += `<text x="${CX + 10}" y="${CY - 305}" font-size="8.5" fill="#475569" font-weight="600" opacity="0.75">Ārējais loks — dzīves posmi & zīmīgi gadu cikli (lēnās planētas)</text>`;

    // Rotētas Zodiaka zīmes (subtīlas)
    for (let i = 0; i < 12; i++) {
        const rotDegStart = getRotatedLon(i * 30);
        const b0 = polar(rotDegStart, R_RIMIN), b1 = polar(rotDegStart, R_OUTER);
        s += `<line x1="${b0.x.toFixed(1)}" y1="${b0.y.toFixed(1)}" x2="${b1.x.toFixed(1)}" y2="${b1.y.toFixed(1)}" stroke="#cbd5e1" stroke-width="0.6" opacity="0.25"/>`;
        
        const rotDegMid = getRotatedLon(i * 30 + 15);
        const g = polar(rotDegMid, R_SIGN);
        s += `<text x="${g.x.toFixed(1)}" y="${g.y.toFixed(1)}" font-size="14" fill="#64748b" text-anchor="middle" dominant-baseline="central" opacity="0.3">${SIGN_GLYPH[i]}</text>`;
    }

    // Horizonta līnija
    const horCol = toggleHorizon ? COL.angular : "#cbd5e1";
    const horWidth = toggleHorizon ? 2.8 : 1.2;
    const horOpacity = toggleHorizon ? 0.95 : 0.25;
    s += `<line x1="${CX - R_OUTER - 15}" y1="${CY}" x2="${CX + R_OUTER + 15}" y2="${CY}" stroke="${horCol}" stroke-width="${horWidth}" opacity="${horOpacity}"/>`;
    s += `<text x="${CX - R_OUTER - 20}" y="${CY}" class="horizon-label" fill="${toggleHorizon ? COL.angular : '#64748b'}" opacity="${toggleHorizon ? 1.0 : 0.4}" text-anchor="end">🌅 UZLĒKŠANA (ASC)</text>`;
    s += `<text x="${CX + R_OUTER + 20}" y="${CY}" class="horizon-label" fill="${toggleHorizon ? COL.angular : '#64748b'}" opacity="${toggleHorizon ? 1.0 : 0.4}" text-anchor="start">🌇 RIETĒŠANA (DSC)</text>`;

    // MC/IC asis (Zenīts un Nadirs)
    const mcRot = getRotatedLon(sky.mc);
    const icRot = getRotatedLon(sky.ic);
    const pMc0 = polar(mcRot, 160), pMc1 = polar(mcRot, R_RIMIN);
    const pIc0 = polar(icRot, 160), pIc1 = polar(icRot, R_RIMIN);
    
    const axisCol = toggleHorizon ? COL.angular : "#cbd5e1";
    const axisWidth = toggleHorizon ? 1.8 : 0.8;
    const axisDash = toggleHorizon ? "none" : "3 3";
    const axisOpacity = toggleHorizon ? 0.85 : 0.2;
    
    s += `<line x1="${pMc0.x.toFixed(1)}" y1="${pMc0.y.toFixed(1)}" x2="${pMc1.x.toFixed(1)}" y2="${pMc1.y.toFixed(1)}" stroke="${axisCol}" stroke-width="${axisWidth}" stroke-dasharray="${axisDash}" opacity="${axisOpacity}"/>`;
    s += `<line x1="${pIc0.x.toFixed(1)}" y1="${pIc0.y.toFixed(1)}" x2="${pIc1.x.toFixed(1)}" y2="${pIc1.y.toFixed(1)}" stroke="${axisCol}" stroke-width="${axisWidth}" stroke-dasharray="${axisDash}" opacity="${axisOpacity}"/>`;
    
    const mcL = polar(mcRot, R_OUTER + 14);
    s += `<text x="${mcL.x.toFixed(1)}" y="${mcL.y.toFixed(1)}" class="axis-label" fill="${toggleHorizon ? COL.angular : '#64748b'}" opacity="${toggleHorizon ? 1.0 : 0.45}">☀️ ZENĪTS (MC)</text>`;
    const icL = polar(icRot, R_OUTER + 14);
    s += `<text x="${icL.x.toFixed(1)}" y="${icL.y.toFixed(1)}" class="axis-label" fill="${toggleHorizon ? COL.angular : '#64748b'}" opacity="${toggleHorizon ? 1.0 : 0.45}">🌌 APAKŠZENĪTS (IC)</text>`;

    // Dzimšanas kartes punkti uz malas (mazas, krāsainas svītriņas)
    const seenNatal = new Set();
    for (const t of personal) {
        const key = t.natal + Math.round(t.natalLon);
        if (seenNatal.has(key)) continue; seenNatal.add(key);
        const rotNatalLon = getRotatedLon(t.natalLon);
        const p0 = polar(rotNatalLon, R_OUTER), pi = polar(rotNatalLon, R_RIMIN);
        s += `<line x1="${pi.x.toFixed(1)}" y1="${pi.y.toFixed(1)}" x2="${p0.x.toFixed(1)}" y2="${p0.y.toFixed(1)}" stroke="${t.color}" stroke-width="2.2" opacity="0.7"/>`;
    }

    // Planētu izvietojums (atbilstoši gredzenu rādiusiem)
    const highlight = {};
    for (const g of general) {
        if (g.kind === "angular" || g.kind === "retro") highlight[g.a.name] = g.color;
    }

    const getPlanetRadius = (name) => {
        if (name === "Meness") return 160;
        if (["Saule", "Merkurs", "Venera", "Marss"].includes(name)) return 230;
        return 300; // Jupiters, Saturns, Urans, Neptuns, Plutons, Rahu, Ketu
    };

    // Filtram aspektus balstoties uz izvēlētajām vadīklām
    const activeAspects = general.filter(g => {
        if (g.kind !== "aspect") return false;
        if (g.harmony > 0) return toggleHarmony;
        if (g.harmony < 0) return toggleTension;
        if (g.harmony === 0) return toggleConjunction;
        return false;
    });
    
    // Kārtojam aspektus pēc to leņķiskā attāluma (garuma), lai īsākie loki būtu tuvāk centram,
    // bet garākie aptvertu tos no ārpuses. Tas rada izcilu vizuālo struktūru!
    activeAspects.sort((a, b) => {
        const spanA = sep(a.a.lon, a.b.lon);
        const spanB = sep(b.a.lon, b.b.lon);
        return spanA - spanB;
    });

    // Saskaitām, cik aspektu pavisam ir katrai planētai (lai pareizi sadalītu to rādītājus)
    const totalAspectsPerPlanet = {};
    activeAspects.forEach(g => {
        totalAspectsPerPlanet[g.a.name] = (totalAspectsPerPlanet[g.a.name] || 0) + 1;
        totalAspectsPerPlanet[g.b.name] = (totalAspectsPerPlanet[g.b.name] || 0) + 1;
    });

    const currentAspectIndex = {};

    // Dinamiska leņķa nobīdes noteikšana (fiziskā attāluma izlīdzināšanai atkarībā no gredzena rādiusa)
    const getSpacingDeg = (radius) => {
        if (radius >= 300) return 1.1; // Lielāks rādiuss = mazāks leņķis (aptuveni 5.7px)
        if (radius >= 230) return 1.4; // Vidējs rādiuss (aptuveni 5.6px)
        return 2.0;                    // Mazs rādiuss (aptuveni 5.5px)
    };

    const N = activeAspects.length;
    const centerR = 100; // Ideālais vidus rādiuss
    let spacing = 9;
    if (N > 9) {
        spacing = Math.max(5.5, 80 / N); // Saspiežam distanci, ja ir ļoti daudz aspektu
    }
    const startR = Math.max(55, centerR - ((N - 1) * spacing) / 2);

    activeAspects.forEach((g, idx) => {
        const r = startR + idx * spacing;
        
        const angA = getRotatedLon(g.a.lon);
        const angB = getRotatedLon(g.b.lon);
        
        const radA = getPlanetRadius(g.a.name);
        const radB = getPlanetRadius(g.b.name);
        
        // Aprēķinām leņķisko nobīdi Planētai A, lai paralēlās līnijas nepārklātos
        const totalA = totalAspectsPerPlanet[g.a.name];
        const currA  = currentAspectIndex[g.a.name] || 0;
        currentAspectIndex[g.a.name] = currA + 1;
        const offsetIdxA = currA - (totalA - 1) / 2;
        const angA_offset = angA + offsetIdxA * getSpacingDeg(radA);
        
        // Aprēķinām leņķisko nobīdi Planētai B
        const totalB = totalAspectsPerPlanet[g.b.name];
        const currB  = currentAspectIndex[g.b.name] || 0;
        currentAspectIndex[g.b.name] = currB + 1;
        const offsetIdxB = currB - (totalB - 1) / 2;
        const angB_offset = angB + offsetIdxB * getSpacingDeg(radB);
        
        // Planetārie punkti un loka galapunkti (izmantojot nobīdītos leņķus)
        const pA_planet = polar(angA_offset, radA);
        const pB_planet = polar(angB_offset, radB);
        const pA_arc = polar(angA_offset, r);
        const pB_arc = polar(angB_offset, r);
        
        // Mērogojam ietekmes stiprumu (ratio no 0 līdz 1)
        const maxOrb = 8.0;
        const ratio = Math.max(0, 1 - (g.orb || 0) / maxOrb);
        
        let color = COL.harmon; // Harmoniska ietekme (zaļa)
        let dash = "none";
        if (g.harmony < 0) {
            color = COL.tense; // Saspringta ietekme (sarkana)
            dash = "3 2";
        } else if (g.harmony === 0) {
            color = COL.conj; // Saplūšana (violeta)
            dash = "1 1"; // Punktēta līnija saplūšanai
        }
        
        const strokeWidth = 1.0 + 1.6 * ratio; // 1.0px līdz 2.6px
        const opacity = (0.28 + 0.52 * ratio).toFixed(2); // 0.28 līdz 0.80
        
        // Zīmējam apļa loku ar pareizu sweep flag
        const diff = (angB_offset - angA_offset + 360) % 360;
        const sweep = diff <= 180 ? 1 : 0;
        
        // Vienots ceļš: No Planētas A rādiusa -> uz leju līdz rāiusam r -> pa loku līdz leņķim B -> uz augšu līdz Planētai B
        const pathStr = `M ${pA_planet.x.toFixed(1)} ${pA_planet.y.toFixed(1)} ` +
                        `L ${pA_arc.x.toFixed(1)} ${pA_arc.y.toFixed(1)} ` +
                        `A ${r.toFixed(1)} ${r.toFixed(1)} 0 0 ${sweep} ${pB_arc.x.toFixed(1)} ${pB_arc.y.toFixed(1)} ` +
                        `L ${pB_planet.x.toFixed(1)} ${pB_planet.y.toFixed(1)}`;
                        
        s += `<path d="${pathStr}" fill="none" stroke="${color}" stroke-width="${strokeWidth.toFixed(1)}" stroke-dasharray="${dash}" opacity="${opacity}" />`;
    });

    const drawClockBand = (list, labR, minGap) => {
        const sorted = [...list].sort((x, y) => getRotatedLon(x.lon) - getRotatedLon(y.lon));
        const labAng = spreadAngles(sorted.map(b => getRotatedLon(b.lon)), minGap);
        sorted.forEach((b, i) => {
            const visualLon = getRotatedLon(b.lon);
            const radius = getPlanetRadius(b.name);
            const m = polar(visualLon, radius);
            const L = polar(labAng[i], labR);

            const isImportant = b.name === "Saule" || b.name === "Meness";

            // Uzzīmējam līderlīniju
            s += `<line x1="${m.x.toFixed(1)}" y1="${m.y.toFixed(1)}" x2="${L.x.toFixed(1)}" y2="${L.y.toFixed(1)}" stroke="#cbd5e1" stroke-width="0.8" opacity="0.6"/>`;

            if (isImportant) {
                // Izcelta Saule un Mēness (lielākas nozīmītes ar ikonām)
                const icon = b.name === "Saule" ? "☀️" : "🌙";
                const hc = highlight[b.name];
                const drawBorder = hc && hc === COL.angular && toggleHorizon;
                s += `<circle cx="${m.x.toFixed(1)}" cy="${m.y.toFixed(1)}" r="18" fill="#ffffff" stroke="${drawBorder ? COL.angular : '#cbd5e1'}" stroke-width="${drawBorder ? '2.5' : '1.5'}"/>`;
                s += `<text x="${m.x.toFixed(1)}" y="${(m.y + 0.5).toFixed(1)}" font-size="18" text-anchor="middle" dominant-baseline="central">${icon}</text>`;
            } else {
                // Parastās planētas nozīmīte (krāsains aplītis ar glifu)
                const circleColor = b.above ? "#475569" : "#e2e8f0";
                const textColor = b.above ? "#ffffff" : "#0f172a";
                const strokeColor = b.above ? "#ffffff" : "#475569";
                s += `<circle cx="${m.x.toFixed(1)}" cy="${m.y.toFixed(1)}" r="11" fill="${circleColor}" stroke="${strokeColor}" stroke-width="1.2"/>`;
                
                // Papildu izcēluma aplis retrogrādām vai leņķiskām planētām
                const hc = highlight[b.name];
                if (hc) {
                    const isAngular = hc === COL.angular;
                    const isRetro = hc === COL.retro;
                    if ((isAngular && toggleHorizon) || (isRetro && toggleRetrograde)) {
                        s += `<circle cx="${m.x.toFixed(1)}" cy="${m.y.toFixed(1)}" r="14" fill="none" stroke="${hc}" stroke-width="2.2" opacity="0.85"/>`;
                    }
                }
                
                s += `<text x="${m.x.toFixed(1)}" y="${(m.y + 0.5).toFixed(1)}" font-size="12" font-weight="700" fill="${textColor}" text-anchor="middle" dominant-baseline="central">${b.glyph}</text>`;
            }

            const sign = SIGN_NAME[Math.floor(b.lon / 30)];
            const degTxt = `${Math.floor(b.lon % 30)}° ${sign}${b.retro ? " ℞" : ""}`;
            const tip = `${NAME_LV[b.name]} — ${LIFE[b.name]}. ${degTxt}, ${b.above ? "virs horizonta" : "aiz horizonta"}.`;

            // Teksta stili un krāsas, garantējot izcilu kontrastu uz jebkura fona
            const textColor = b.above ? "#0f172a" : "#1e293b";
            const subColor = b.above ? "#475569" : "#475569";
            const fontW = isImportant ? "800" : "600";
            const fontSizeName = isImportant ? "13" : "11";
            const fontSizeDeg = isImportant ? "10.5" : "9";

            s += `<g><title>${tip}</title>`;
            // Baltā kontūra (stroke) apkārt burtiem garantē lasāmību uz švīkainā fona
            s += `<text x="${L.x.toFixed(1)}" y="${(L.y - 7).toFixed(1)}" font-size="${fontSizeName}" font-weight="${fontW}" fill="${textColor}" text-anchor="middle" dominant-baseline="central" stroke="#ffffff" stroke-width="3.5" stroke-linejoin="round" paint-order="stroke fill">${NAME_LV[b.name]}</text>`;
            s += `<text x="${L.x.toFixed(1)}" y="${(L.y + 8).toFixed(1)}" font-size="${fontSizeDeg}" fill="${subColor}" text-anchor="middle" dominant-baseline="central" stroke="#ffffff" stroke-width="3.5" stroke-linejoin="round" paint-order="stroke fill">${degTxt}</text>`;
            // Caurspīdīgs laukums vieglākai hover tooltip uztveršanai
            s += `<circle cx="${m.x.toFixed(1)}" cy="${m.y.toFixed(1)}" r="14" fill="transparent" style="cursor:help;"/>`;
            s += `</g>`;
        });
    };

    drawClockBand(sky.bodies.filter(b => b.above), R_ABOVE_LAB, 18);
    drawClockBand(sky.bodies.filter(b => !b.above), R_BELOW_LAB, 16);

    // Centra uzraksti
    s += `<text x="${CX}" y="${(CY - 8).toFixed(1)}" font-size="12" fill="#64748b" text-anchor="middle" font-weight="800" opacity="0.8">PULKSTENIS</text>`;
    s += `<text x="${CX}" y="${(CY + 9).toFixed(1)}" font-size="9.5" fill="#94a3b8" text-anchor="middle" opacity="0.8">rotēts pret horizontu</text>`;
    s += `</svg>`;
    return s;
}

function renderWeatherBarHTML(dayData) {
    if (!dayData) return `<div style="color:#94a3b8; font-size:0.85rem; padding:8px;">Neizdevās ielādēt dienas laikapstākļu datus.</div>`;

    const { hourly, abhijit, rahuKaal, brahma, sunriseHour, sunsetHour } = dayData;
    
    // 24 stundu šūnas
    let cellsHtml = "";
    for (let h = 0; h < 24; h++) {
        const val = (hourly && hourly[h] !== undefined) ? hourly[h] : 50;
        
        let bg = "#e2e8f0"; // neitrāls
        let label = "Neitrāla, stabila stunda";
        if (val >= 80) { bg = "#bbf7d0"; label = "Labvēlīga stunda (uzplaukums/atbalsts)"; }
        if (val <= 20) { bg = "#fecaca"; label = "Saspringta stunda (piesardzība)"; }
        
        const isBrahma = h >= Math.floor(brahma.start) && h < Math.ceil(brahma.end);
        const isAbhijit = h >= Math.floor(abhijit.start) && h < Math.ceil(abhijit.end);
        const isRahu = h >= Math.floor(rahuKaal.start) && h < Math.ceil(rahuKaal.end);
        
        let marker = "";
        let borderStyle = "border-right: 1px solid #ffffff;";
        if (isAbhijit) {
            bg = "#fef08a"; // zelts
            marker = "☀️";
            label += " (Abhijit Muhurta - Zelta stunda)";
        } else if (isRahu) {
            bg = "#fca5a5"; // saspīlēts sarkans
            marker = "⚠️";
            label += " (Rahu Kaal - saspringtas stundas)";
            borderStyle += " border-top: 1.5px solid #ef4444; border-bottom: 1.5px solid #ef4444;";
        } else if (isBrahma) {
            bg = "#c084fc"; // violets
            marker = "🌅";
            label += " (Brahma Muhurta - garīgais sākums)";
        }
        
        const isDay = h >= Math.floor(sunriseHour) && h < Math.ceil(sunsetHour);
        const dayIndicator = isDay ? "☀️ diena" : "🌙 nakts";
        
        cellsHtml += `
            <div style="flex: 1; height: 32px; background: ${bg}; ${borderStyle} display: flex; align-items: center; justify-content: center; position: relative; cursor: help; font-size: 0.72rem;" title="${String(h).padStart(2,'0')}:00 - ${String(h+1).padStart(2,'0')}:00 | ${dayIndicator} | ${label}">
                <span>${marker}</span>
            </div>`;
    }
    
    // Asis (ik pa 2 stundām)
    let labelsHtml = "";
    for (let h = 0; h <= 24; h += 2) {
        labelsHtml += `<span style="width: 7.7%; text-align: center; font-size: 0.7rem; color: #64748b; font-weight: 700;">${String(h).padStart(2,'0')}</span>`;
    }

    const fmtH = (val) => {
        const hrs = Math.floor(val);
        const mins = Math.round((val - hrs) * 60);
        return `${String(hrs).padStart(2,'0')}:${String(mins).padStart(2,'0')}`;
    };

    return `
        <div style="font-family: 'Outfit', sans-serif;">
            <div style="display: flex; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);">
                ${cellsHtml}
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 0.25rem; padding: 0 4px;">
                ${labelsHtml}
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.6rem; margin-top: 1rem;">
                <div style="background: #ffffff; border: 1px solid #f3e8ff; border-left: 4px solid #a855f7; border-radius: 8px; padding: 8px 10px; font-size: 0.8rem;">
                    <div style="font-weight: 700; color: #7e22ce; display: flex; align-items: center; gap: 4px;">🌅 Brahma Muhurta</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; margin: 1px 0 3px;">${fmtH(brahma.start)} - ${fmtH(brahma.end)}</div>
                    <div style="color: #475569; line-height: 1.35;">Miera un koncentrēšanās stundas pirms rītausmas. Lieliski piemērots vizualizācijai, mācībām vai rīta rosībai.</div>
                </div>
                
                <div style="background: #ffffff; border: 1px solid #fef9c3; border-left: 4px solid #eab308; border-radius: 8px; padding: 8px 10px; font-size: 0.8rem;">
                    <div style="font-weight: 700; color: #a16207; display: flex; align-items: center; gap: 4px;">☀️ Abhijit Muhurta</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; margin: 1px 0 3px;">${fmtH(abhijit.start)} - ${fmtH(abhijit.end)}</div>
                    <div style="color: #475569; line-height: 1.35;">Dienas zelta logs. Augstākā harmonija, ideāli piemērots svarīgiem lēmumiem, pirkumiem un sākumiem.</div>
                </div>
                
                <div style="background: #ffffff; border: 1px solid #fee2e2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 8px 10px; font-size: 0.8rem;">
                    <div style="font-weight: 700; color: #b91c1c; display: flex; align-items: center; gap: 4px;">⚠️ Rahu Kaal</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; margin: 1px 0 3px;">${fmtH(rahuKaal.start)} - ${fmtH(rahuKaal.end)}</div>
                    <div style="color: #475569; line-height: 1.35;">Dienas saspringtās stundas. Nav ieteicams parakstīt būtiskus finanšu līgumus vai uzsākt konfliktējošas diskusijas.</div>
                </div>
            </div>

            <div style="margin-top: 0.8rem; border-top: 1px solid #e2e8f0; padding-top: 0.5rem; display: flex; justify-content: space-between; font-size: 0.78rem; color: #64748b;">
                <span>🌅 Saullēkts šodien: <b>${fmtH(sunriseHour)}</b></span>
                <span>🌇 Saulriets šodien: <b>${fmtH(sunsetHour)}</b></span>
            </div>
        </div>`;
}

// ── "Kad rīkoties" elekcionālais panelis ─────────────────────────────────────
const elecScoreColor = (v) => v >= 80 ? '#16a34a' : (v >= 68 ? '#65a30d' : '#d97706');
const VERDICT_ICON = { good:'✅', neutral:'➖', avoid:'⚠️' };

function elecWindowCard(w, rank) {
    const col = elecScoreColor(w.avg);
    const vIcon = VERDICT_ICON[w.verdict.state] || '➖';
    const isAvoid = w.verdict.state === 'avoid';

    // 1. rinda — VERDIKTS: kam logs piemērots / nav piemērots / neitrāls
    let verdictLine;
    if (isAvoid) {
        verdictLine = `<span class="elec-verdict-label">${vIcon} Nepiemērota svarīgām darbībām</span>`;
    } else if (w.verdict.state === 'neutral') {
        verdictLine = `<span class="elec-verdict-label">${vIcon} Neitrāls logs</span><span class="elec-verdict-note">nav izteiktas nosliekšanās</span>`;
    } else {
        const suitChips = w.suitable.map(s => `<span class="elec-suit">${s.icon} ${s.label}</span>`).join('');
        verdictLine = `<span class="elec-verdict-label">${vIcon} ${w.verdict.label}:</span>${suitChips}`;
    }

    // 2. rinda — papildu: labajam logam "Mazāk piemērota"; izvairāmajam — kāpēc izvairīties
    let secondLine = '';
    if (isAvoid) {
        const why = w.reasonsBad.map(r => `<span class="elec-unsuit">${r.t}</span>`).join('');
        if (why) secondLine = `<div class="elec-unsuit-row">${why}</div>`;
    } else if (w.unsuitable.length) {
        secondLine = `<div class="elec-unsuit-row"><span class="elec-unsuit-lead">Mazāk piemērota:</span>${w.unsuitable.map(s => `<span class="elec-unsuit">${s.icon} ${s.label}</span>`).join('')}</div>`;
    }

    // Astro "kāpēc" iemesli (Mēness/konsenss/Abhijit/planētu stunda) — tikai labajiem logiem
    const reasonChips = w.reasonsGood.map(r =>
        `<span class="elec-chip${r.golden ? ' elec-chip--gold' : ''}">${r.t}</span>`).join('');
    const reasonsBlock = reasonChips ? `<div class="elec-reasons">${reasonChips}</div>` : '';
    const rankBadge = (rank != null) ? `<span class="elec-rank${rank === 1 ? ' elec-rank--top' : ''}">#${rank}</span>` : '';

    return `
    <div class="elec-card elec-card--${w.verdict.state}${w.golden ? ' elec-card--gold' : ''}" role="button" tabindex="0"
         onclick="window.focusElectionalDay('${w.date}', ${w.startHour})"
         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.focusElectionalDay('${w.date}', ${w.startHour});}"
         title="Klikšķini, lai atvērtu šo dienu kartē">
        <div class="elec-verdict" style="--vcol:${w.verdict.color};">${verdictLine}</div>
        ${secondLine}
        <div class="elec-when">
            ${rankBadge}
            <span class="elec-day">${weekdayLV(w.date)} · ${fmtDateLV(w.date)}</span>
            <span class="elec-time">${fmtHour(w.startHour)}–${fmtHour(w.endHour)}</span>
            ${w.golden ? '<span class="elec-gold-badge">★ zelta logs</span>' : ''}
        </div>
        <div class="elec-score-row">
            <div class="elec-bar"><div style="width:${w.avg}%; background:${col};"></div></div>
            <span class="elec-score-num" style="color:${col};">${w.avg}<span style="color:#94a3b8;font-weight:600;">/100</span></span>
            <span class="elec-conf elec-conf--${w.confidence.key}" title="${w.confidence.note}">Ticamība: ${w.confidence.label}</span>
        </div>
        ${reasonsBlock}
        <div class="elec-open-hint">Klikšķini → atvērt dienā kartē ↑</div>
    </div>`;
}

function renderElectionalResults(res) {
    if (!res.best.length) {
        return `<div class="elec-empty">Nākamajās ${res.meta.horizon} dienās nav izteikti labvēlīga loga — debesis ir mierīgas/neitrālas. Pamēģini garāku periodu.</div>`;
    }
    let html = `<div class="elec-cards">` + res.best.map((w, i) => elecWindowCard(w, i + 1)).join('') + `</div>`;

    if (res.avoid.length) {
        html += `<div class="elec-avoid-divider">⚠️ Nepiemērotie logi (tuvākajās 2 nedēļās) — kad svarīgu nedarīt</div>`;
        html += `<div class="elec-cards">` + res.avoid.map(w => elecWindowCard(w, null)).join('') + `</div>`;
    }

    html += `<div class="elec-foot">Skenētas ${res.meta.scanned} dienas · logi rēķināti planētu stundām, Muhurta logiem, BaZi un (tuvākajām dienām) dienas konsensam. Tas ir lēmumu atbalsts, ne garantija.</div>`;
    return html;
}

// ── BIZNESA KALENDĀRS — 9 dienu bīdāmais skats ───────────────────────────────
const VAL_META = {
    supportive:  { col:'#16a34a', bg:'#ecfdf5', bd:'#86efac', dot:'#22c55e', lv:'atbalstoša' },
    challenging: { col:'#dc2626', bg:'#fef2f2', bd:'#fca5a5', dot:'#ef4444', lv:'saspringta' },
    neutral:     { col:'#64748b', bg:'#f1f5f9', bd:'#cbd5e1', dot:'#94a3b8', lv:'neitrāla' },
};
const valDot = (v) => `<span class="bizcal-dot" style="background:${(VAL_META[v]||VAL_META.neutral).dot};"></span>`;

// Stundas kategorija parastam cilvēkam: laba / neitrāla / izvairies (3 skaidras krāsas)
function hourCategory(score) {
    if (score >= 60) return 'good';
    if (score >= 42) return 'ok';
    return 'avoid';
}
const HOUR_COL = { good:'#22c55e', ok:'#fbbf24', avoid:'#ef4444' };
function heatColor(score) { return HOUR_COL[hourCategory(score)]; }

// Kompakts rietumu tranzīta apraksts (tikai kā paskaidrojums uz uzbraukšanas)
const W_PLANET_LV = { Saule:'Saule', Merkurs:'Prāts', Venera:'Attiecības', Marss:'Rīcība', Jupiters:'Veiksme', Saturns:'Struktūra' };
const W_ASPECT_LV = { konjunkcija:'savienībā', sekstils:'harmonijā', kvadrāts:'saspringumā', trigons:'plūsmā', opozīcija:'pretstatā' };
function westShort(w) {
    if (!w || !w.top) return '';
    const t = w.top;
    const txt = `${W_PLANET_LV[t.transit]||t.transit} ${W_ASPECT_LV[t.aspect]||t.aspect} ${W_PLANET_LV[t.natal]||t.natal}`;
    return txt + (w.count > 1 ? ` +${w.count - 1}` : '');
}

// ── Parasta cilvēka valoda (bez astroloģijas žargona) ────────────────────────

// Dienas kvalitāte = nomoda stundu (6–22) vidējais vērtējums. Tieši sasaucas ar
// stundu krāsām, ko lietotājs redz joslā → nav pretrunu.
function dayQuality(d) {
    const hs = d.hourlyScore || [];
    let sum = 0, n = 0;
    for (let h = 6; h < 22; h++) { if (hs[h] != null) { sum += hs[h]; n++; } }
    const avg = n ? sum / n : 50;
    if (avg >= 56) return { key:'good',  emoji:'🟢', word:'Laba diena',      col:'#16a34a', bg:'#ecfdf5', bd:'#86efac', avg };
    if (avg >= 45) return { key:'mixed', emoji:'🟡', word:'Jaukta diena',    col:'#d97706', bg:'#fffbeb', bd:'#fcd34d', avg };
    return            { key:'hard',  emoji:'🔴', word:'Sarežģīta diena', col:'#dc2626', bg:'#fef2f2', bd:'#fca5a5', avg };
}

// Viena teikuma dienas kopsavilkums (kvalitāte × cik notikumiem bagāta)
function daySummary(d, q) {
    const eventful = (d.intensity?.score ?? 0) >= 50;
    if (q.key === 'good')  return eventful
        ? 'Spēcīga un atbalstoša diena — labs brīdis rīkoties un virzīt svarīgo uz priekšu.'
        : 'Mierīga, labvēlīga diena — viss rit gludi, bez lieliem satricinājumiem.';
    if (q.key === 'mixed') return eventful
        ? 'Mainīga diena ar daudz notikumiem — ir gan labi, gan saspringti brīži. Izvēlies laiku rūpīgi.'
        : 'Neitrāla, klusa diena — nekas īpašs nenotiek. Laba ikdienas darbiem.';
    return eventful
        ? 'Saspringta, notikumiem bagāta diena — emocijas un berze augstas. Svarīgus lēmumus labāk atliec.'
        : 'Smagnēja, mazliet kūtra diena — enerģijas maz. Nesteidzies ar svarīgo.';
}

// Stundu diapazonu formatēšana ("13:00–15:00, 18:00–19:00")
function fmtRanges(ranges) {
    return ranges.map(r => `${fmtHour(r[0])}–${fmtHour(r[1] + 1)}`).join(', ');
}
function hourRanges(hs, wantCat) {
    const out = []; let cur = null;
    for (let h = 6; h < 22; h++) {
        if (hourCategory(hs[h]) === wantCat) { if (!cur) cur = [h, h]; cur[1] = h; }
        else if (cur) { out.push(cur); cur = null; }
    }
    if (cur) out.push(cur);
    return out;
}

// Ieteikums: ko un cikos darīt
function dayAction(d, q) {
    const g = d.golden;
    const time = g ? `${fmtHour(g.startHour)}–${fmtHour(g.endHour)}` : null;
    const suit = (d.suitable || []).slice(0, 3).map(s => s.label.toLowerCase());
    if (q.key === 'hard') {
        return time
            ? `<span class="bizcal-act-ic">🕊️</span><span>Ja kaut kas jādara, mierīgākais brīdis ir <b>${time}</b>. Citādi šodien svarīgo labāk atliec.</span>`
            : `<span class="bizcal-act-ic">🕊️</span><span>Šodien svarīgus darbus labāk atliec uz citu dienu.</span>`;
    }
    let txt = time ? `Labākais laiks rīkoties: <b>${time}</b>` : 'Skaidra labākā laika šodien nav';
    if (suit.length) txt += `.<br><span class="bizcal-act-suit">Vislabāk derēs: ${suit.join(', ')}.</span>`;
    return `<span class="bizcal-act-ic">${q.key === 'good' ? '✅' : '👍'}</span><span>${txt}</span>`;
}

// "Kāpēc" — 3 ietekmes parastā valodā (žargons paliek tooltipā)
const VAL_WORD = { supportive:'labvēlīga', challenging:'saspringta', neutral:'neitrāla' };
function whyRows(d) {
    const rows = [];
    if (d.moon) {
        const w = d.moon.valence === 'supportive' ? 'labs, atbalstošs' : d.moon.valence === 'challenging' ? 'saspringts, jūtīgs' : 'mierīgs';
        rows.push(`<div class="bizcal-why" title="Mēness stāvoklis (Tara Bala): ${d.moon.label}">${valDot(d.moon.valence)}<b>Noskaņojums:</b> ${w}</div>`);
    }
    if (d.bazi) {
        rows.push(`<div class="bizcal-why" title="Ķīniešu BaZi dienas pīlārs: ${d.bazi.pillar.stem} ${d.bazi.pillar.branch}">${valDot(d.bazi.valence)}<b>Dienas enerģija:</b> ${VAL_WORD[d.bazi.valence] || 'neitrāla'}</div>`);
    }
    if (d.western) {
        const detail = westShort(d.western);
        rows.push(`<div class="bizcal-why" title="${detail ? 'Rietumu planētu tranzīts: ' + detail : 'Rietumu planētu fons'}">${valDot(d.western.valence)}<b>Planētu ietekme:</b> ${VAL_WORD[d.western.valence] || 'neitrāla'}</div>`);
    }
    return rows.join('');
}

// Viena kalendāra diena — parastam cilvēkam saprotama
function bizCalDayCard(d) {
    const q = dayQuality(d);
    const hs = d.hourlyScore || [];

    // 24h josla: 3 skaidras krāsas + zelta loga (★) un nelabvēlīgā perioda (✕) rāmji + saullēkts/saulriets
    const goldSet = new Set(d.golden ? d.golden.hours : []);
    const rk = d.rahuKaal || {};
    const inRahu = (h) => rk.start != null && (h + 1) > rk.start && h < rk.end;
    const sr = Math.round(d.sunriseHour ?? 6), ssH = Math.round(d.sunsetHour ?? 21);
    const CAT_LV = { good:'laba', ok:'neitrāla', avoid:'izvairies' };
    const heat = hs.map((sc, h) => {
        const gold = goldSet.has(h);
        const rahu = inRahu(h);
        const border = gold ? 'box-shadow:inset 0 0 0 1.6px #d97706;' : (rahu ? 'box-shadow:inset 0 0 0 1.6px #7f1d1d;' : '');
        const mark = (h === sr) ? '🌅' : (h === ssH) ? '🌇' : '';
        const tip = `${String(h).padStart(2,'0')}:00 — ${CAT_LV[hourCategory(sc)]} stunda${gold ? ' · labākais brīdis' : ''}${rahu ? ' · nelabvēlīgs periods (Rahu Kaal)' : ''}`;
        return `<span class="bizcal-hr" title="${tip}" style="background:${heatColor(sc)};${border}">${mark ? `<i class="bizcal-hr-mark">${mark}</i>` : ''}</span>`;
    }).join('');

    // Labās / izvairāmās stundas parastā valodā
    const goodR = hourRanges(hs, 'good');
    const avoidR = hourRanges(hs, 'avoid');
    const avoidHrCount = avoidR.reduce((a, r) => a + (r[1] - r[0] + 1), 0);
    const goodLine = goodR.length
        ? `<div class="bizcal-hrs bizcal-hrs--good"><b>✅ Labās stundas:</b> ${fmtRanges(goodR)}</div>`
        : `<div class="bizcal-hrs bizcal-hrs--none">Izteikti labu stundu šodien nav</div>`;
    let avoidLine;
    if (avoidHrCount >= 11) {
        avoidLine = `<div class="bizcal-hrs bizcal-hrs--avoid"><b>⛔ Izvairies:</b> gandrīz visu dienu — neuzsāc svarīgo</div>`;
    } else if (avoidR.length) {
        avoidLine = `<div class="bizcal-hrs bizcal-hrs--avoid"><b>⛔ Izvairies:</b> ${fmtRanges(avoidR)}</div>`;
    } else {
        avoidLine = '';
    }

    // Nelabvēlīgais periods (Rahu Kaal) parastā valodā
    const rahuStr = (rk.start != null) ? `${fmtHour(rk.start)}–${fmtHour(rk.end)}` : '';
    const rahuLine = rahuStr
        ? `<div class="bizcal-warn" title="Rahu Kaal — vēdiskās tradīcijas nelabvēlīgais dienas posms; neiesaka pieņemt svarīgus lēmumus vai parakstīt līgumus">⚠️ Nelabvēlīgs periods (neslēdz darījumus): <b>${rahuStr}</b></div>`
        : '';

    // Mazāk piemērots (tikai ja diena nav jau "sarežģīta" — citādi viss jau ir brīdinājums)
    const unsuit = (q.key !== 'hard' ? (d.unsuitable || []) : []).slice(0, 2).map(s => s.label.toLowerCase());
    const unsuitLine = unsuit.length
        ? `<div class="bizcal-less">Šodien mazāk piemērots: ${unsuit.join(', ')}</div>` : '';

    // Apakšrinda: saskaņa + ticamība (ar paskaidrojumiem)
    const c = d.consensus || {};
    const consChip = c.confirmed
        ? `<span class="bizcal-cons bizcal-cons--ok" title="${c.agree} no 3 neatkarīgām tradīcijām (Mēness, ķīniešu, rietumu) saskan — augstāka pārliecība">Saskaņa: ${c.agree}/3 ✓</span>`
        : (c.live >= 2 ? `<span class="bizcal-cons bizcal-cons--split" title="Tradīcijas dod pretrunīgus signālus — vājāka pārliecība">Saskaņa: dalīta</span>`
                       : `<span class="bizcal-cons bizcal-cons--one" title="Tikai viena tradīcija dod signālu">Saskaņa: vāja</span>`);
    const confTip = `Cik tuva diena — tik precīzāka prognoze. ${d.confidence.note}`;

    return `
    <div class="bizcal-card bizcal-card--${q.key}${d.isToday ? ' bizcal-card--today' : ''}" style="--acc:${q.col}; --accbg:${q.bg}; --accbd:${q.bd};"
         role="button" tabindex="0"
         onclick="window.focusElectionalDay('${d.date}', ${d.golden ? d.golden.startHour : 9})"
         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.focusElectionalDay('${d.date}', ${d.golden ? d.golden.startHour : 9});}"
         title="Klikšķini, lai atvērtu ${d.weekday}, ${d.dateLabel} kartē">
        <div class="bizcal-head">
            <div class="bizcal-date">
                <span class="bizcal-wd">${d.weekday}</span>
                <span class="bizcal-dm">${d.dateLabel}</span>
                ${d.isToday ? '<span class="bizcal-today-badge">ŠODIEN</span>' : ''}
            </div>
            <span class="bizcal-quality bizcal-quality--${q.key}">${q.emoji} ${q.word}</span>
        </div>

        <div class="bizcal-summary">${daySummary(d, q)}</div>

        <div class="bizcal-action bizcal-action--${q.key}">${dayAction(d, q)}</div>

        <div class="bizcal-heat" aria-label="24 stundu profils pēc lokālā laika">${heat}</div>
        <div class="bizcal-heat-axis"><span>00</span><span>06</span><span>12</span><span>18</span><span>24</span></div>
        ${goodLine}
        ${avoidLine}
        ${rahuLine}
        ${unsuitLine}

        <div class="bizcal-why-wrap">
            <div class="bizcal-why-lbl">Kāpēc:</div>
            ${whyRows(d)}
        </div>
        <div class="bizcal-foot">
            ${consChip}
            <span class="bizcal-conf bizcal-conf--${d.confidence.key}" title="${confTip}">Ticamība: ${d.confidence.label}</span>
        </div>
    </div>`;
}

// Makro fons no cilnes "Nākotne" (Daša + Veiksmes pīlārs)
function bizCalMacroContext(profile) {
    const parts = [];
    const cd = profile?.vedic?.current_dasha;
    if (cd && cd.lord) {
        const lord = String(cd.lord).replace(/^Meness$/, 'Mēness').replace(/^Ketu$/, 'Ketū');
        parts.push(`<span class="bizcal-macro-item">🪐 <b>${lord}</b> daša (dzīves cikls)</span>`);
    }
    const lps = profile?.bazi?.luck_pillars || [];
    const birth = profile?.birth_info?.date;
    if (lps.length && birth) {
        const age = Math.floor((Date.now() - new Date(birth).getTime()) / (365.25 * 24 * 3600 * 1000));
        const lp = lps.find(p => (p.ageEnd ?? 0) > age) || lps[0];
        if (lp) {
            const stem = (typeof lp.stem === 'object') ? lp.stem.name : lp.stem;
            const focus = lp.interpretation?.focus;
            parts.push(`<span class="bizcal-macro-item">🀄 Veiksmes pīlārs <b>${stem || '—'}</b>${focus ? ` · ${focus}` : ''}</span>`);
        }
    }
    if (!parts.length) return '';
    return `<div class="bizcal-macro"><span class="bizcal-macro-lead">Makro fons:</span>${parts.join('')}</div>`;
}

function renderBusinessCalendar(profile) {
    const { startOffset, span } = _state.calendar;
    const cal = buildBusinessCalendar(profile, startOffset, span);
    if (!cal.days.length) {
        return `<div class="elec-empty">Nevarēja aprēķināt kalendāru šim periodam.</div>`;
    }

    const macro = bizCalMacroContext(profile);
    const timeUnknown = !!profile?.birth_info?.isTimeUnknown;
    const unkBanner = timeUnknown
        ? `<div class="bizcal-unk">⏳ Dzimšanas laiks nezināms — 24h profils rēķināts kā vidējais rādītājs pēc lokālā laika (natālā Mēness pozīcija ņemta pusdienlaikā). Stundu logi ir orientējoši; dienas līmeņa signāli (BaZi, tranzīti, planētu stundas) ir pilnvērtīgi.</div>`
        : '';

    // Diapazona virsraksts
    const first = cal.days[0], last = cal.days[cal.days.length - 1];
    const rangeLbl = `${first.weekday}, ${first.dateLabel} – ${last.weekday}, ${last.dateLabel}`;

    const cards = cal.days.map(bizCalDayCard).join('');

    return `
    ${macro}
    ${unkBanner}
    <div class="bizcal-help">
        <span class="bizcal-help-lbl">Kā lasīt:</span>
        <span>Katra diena ir krāsaina pēc tā, cik tā labvēlīga:</span>
        <span class="bizcal-help-chip" style="--c:#16a34a;">🟢 laba</span>
        <span class="bizcal-help-chip" style="--c:#d97706;">🟡 jaukta</span>
        <span class="bizcal-help-chip" style="--c:#dc2626;">🔴 sarežģīta</span>
        <span>Krāsainā josla = 24 stundas: <b style="color:#16a34a;">zaļš</b> = laba stunda, <b style="color:#d97706;">dzeltens</b> = neitrāla, <b style="color:#ef4444;">sarkans</b> = labāk neuzsākt svarīgo.</span>
    </div>
    <div class="bizcal-nav">
        <button type="button" class="bizcal-navbtn" onclick="window.shiftBizCal(-3)" title="Pabīdīt 3 dienas atpakaļ">◀ −3 dienas</button>
        <span class="bizcal-range">${rangeLbl}</span>
        <div class="bizcal-nav-right">
            <button type="button" class="bizcal-navbtn bizcal-navbtn--ghost" onclick="window.resetBizCal()" title="Atgriezties uz šodienu">⟳ Šodien</button>
            <button type="button" class="bizcal-navbtn" onclick="window.shiftBizCal(3)" title="Pabīdīt 3 dienas uz priekšu">+3 dienas ▶</button>
        </div>
    </div>
    <div class="bizcal-grid">${cards}</div>
    <div class="bizcal-legend">
        <span><i style="background:#22c55e;"></i>laba stunda</span>
        <span><i style="background:#fbbf24;"></i>neitrāla</span>
        <span><i style="background:#ef4444;"></i>izvairies</span>
        <span><i style="box-shadow:inset 0 0 0 2px #d97706;background:#fff;"></i>★ labākais brīdis</span>
        <span><i style="box-shadow:inset 0 0 0 2px #7f1d1d;background:#fff;"></i>nelabvēlīgs periods</span>
        <span>🌅 saullēkts · 🌇 saulriets</span>
    </div>
    <div class="elec-foot">Aprēķinos apvienota Rietumu, ķīniešu (BaZi) un Indijas (Muhurta) astroloģija. Visi laiki — pēc tavas vietas lokālā laika. Tas ir padoms lēmumiem, ne garantija.</div>`;
}

function refreshElectional() {
    const box = document.getElementById('electional-results');
    if (!box) return;
    if (!_profile) {
        box.innerHTML = `<div class="elec-empty">Ievadi datus lapas augšā, lai redzētu tavu personīgo biznesa kalendāru.</div>`;
        return;
    }
    try { box.innerHTML = renderBusinessCalendar(_profile); }
    catch (e) {
        console.error('[KARTE] kalendāra kļūda:', e);
        box.innerHTML = `<div style="color:#dc2626; padding:10px; font-size:0.85rem;">Kļūda aprēķinot kalendāru: ${e.message}</div>`;
    }
}

// ── Scaffold + init ──────────────────────────────────────────────────────────
const SLIDER_RANGE = 10080; // ±7 dienas minūtēs

function scaffold() {
    const seg = (z) => `<button type="button" class="karte-zbtn${_state.zodiac === z ? ' karte-zbtn--on' : ''}" data-z="${z}" title="${ZODIAC_META[z].hint}">${ZODIAC_META[z].btn}</button>`;
    
    // Nolasām profila esamību dinamiskām radio pogām
    const hasProfile = !!(_profile && _profile.birth_info && _profile.birth_info.date);
    const natalDisabledAttr = hasProfile ? "" : "disabled";
    const natalTitleAttr = hasProfile ? "" : "title='Ievadi datus lapas augšā, lai ieslēgtu'";

    // Sky view tab switcher buttons:
    const skyView = _state.skyView || 'wheel';
    const tabBtn = (view, icon, label) => `
        <button type="button" class="sky-tab-btn${skyView === view ? ' sky-tab-btn--active' : ''}" data-view="${view}" onclick="window.setSkyView('${view}')">
            <span>${icon}</span> ${label}
        </button>
    `;

    return `
    <style>
      .karte-root {
        max-width: 1286px;
        margin: 1.2rem auto 2rem;
        padding: 0;
        font-family: 'Outfit', sans-serif;
        box-sizing: border-box;
      }
      @media (max-width: 1320px) {
        .karte-root {
          padding: 0 10px;
        }
      }

      .karte-controls{display:flex;flex-wrap:wrap;gap:.8rem 1.2rem;align-items:flex-end;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1rem 1.2rem;box-shadow:0 1px 4px rgba(0,0,0,.05);}
      .karte-controls .kc-grp{display:flex;flex-direction:column;gap:.25rem;}
      .karte-controls label{font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;}
      .karte-controls input[type=date],.karte-controls input[type=time],.karte-controls input[type=text]{padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.9rem;font-family:inherit;}
      .karte-zbtn{padding:.45rem .7rem;border:1px solid #cbd5e1;background:#f8fafc;cursor:pointer;font-size:.82rem;font-family:inherit;color:#334155;}
      .karte-zbtn:first-of-type{border-radius:8px 0 0 8px;} .karte-zbtn:last-of-type{border-radius:0 8px 8px 0;border-left:none;}
      .karte-zbtn--on{background:#0f172a;color:#fff;border-color:#0f172a;}
      .karte-now{padding:.45rem .8rem;border:1px solid #0f172a;background:#0f172a;color:#fff;border-radius:8px;cursor:pointer;font-size:.82rem;font-family:inherit;}
      .karte-slider-wrap{flex:1 1 240px;display:flex;flex-direction:column;gap:.25rem;min-width:220px;}
      .karte-slider-wrap input[type=range]{width:100%;}
      .karte-loc-field{position:relative;}
      
      /* Tabs Switcher styling */
      .sky-tab-container{display:flex;justify-content:center;gap:.5rem;margin:1rem 0 1.5rem;background:#f1f5f9;padding:.4rem;border-radius:12px;max-width:650px;margin-left:auto;margin-right:auto;}
      .sky-tab-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.6rem .8rem;border:none;background:transparent;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:700;color:#64748b;font-family:inherit;transition:all 0.2s;}
      .sky-tab-btn span{font-size:1.05rem;}
      .sky-tab-btn:hover{color:#0f172a;background:rgba(255,255,255,0.45);}
      .sky-tab-btn--active{background:#fff;color:#0f172a;box-shadow:0 2px 5px rgba(0,0,0,0.06);}

      #sky-view-svg-box{display:flex;justify-content:center;margin:0.8rem 0;width:100%;}
      .karte-legend{display:flex;flex-wrap:wrap;gap:.8rem;justify-content:center;font-size:.74rem;color:#475569;margin-bottom:.4rem;margin-top:0.6rem;}
      .karte-legend span{display:inline-flex;align-items:center;gap:.3rem;}
      .karte-legend i{width:12px;height:3px;border-radius:2px;display:inline-block;}
      .karte-zhint{font-size:.72rem;color:#94a3b8;font-style:italic;max-width:200px;}
      .karte-headline{text-align:center;max-width:680px;margin:.4rem auto 0;}
      .karte-headline .kh-title{font-size:1.35rem;font-weight:800;color:#0f172a;letter-spacing:-.3px;}
      .karte-headline .kh-desc{font-size:.9rem;color:#475569;line-height:1.4;margin-top:.3rem;}

      /* Main white container styling */
      .sky-main-container {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.8rem 5px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        margin-top: 1rem;
        box-sizing: border-box;
      }
      @media (max-width: 1024px) {
        .sky-main-container {
          padding: 1.2rem;
        }
      }

      /* Grid layout for sidebars + SVG */
      .sky-layout-grid {
        display: grid;
        grid-template-columns: 250px 1fr 250px;
        gap: 1.2rem;
        align-items: start;
        margin-top: 1rem;
      }
      .sky-cards-column {
        background: transparent;
        border: none;
        border-radius: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        min-height: 200px;
      }
      .sky-cards-scroll-container {
        max-height: 750px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        padding-right: 4px;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
      }
      .sky-cards-scroll-container::-webkit-scrollbar {
        width: 6px;
      }
      .sky-cards-scroll-container::-webkit-scrollbar-track {
        background: transparent;
      }
      .sky-cards-scroll-container::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 3px;
      }
      .sky-center-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 0;
        width: 100%;
      }

      @media (max-width: 1024px) {
        .sky-layout-grid {
          grid-template-columns: 1fr;
          gap: 1.5rem;
        }
        .sky-center-column {
          grid-row: 1; /* Center SVG on top on mobile/tablet */
        }
        #sky-cards-left-zone {
          grid-row: 2;
        }
        #sky-cards-right-zone {
          grid-row: 3;
        }
      }

      /* ⏱️ "Kad rīkoties" elekcionālais panelis */
      .elec-panel {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 16px;
        padding: 1.4rem 1.5rem 1.6rem;
        margin: 0 auto 1.5rem;
        max-width: 1286px;
        color: #e2e8f0;
        box-shadow: 0 6px 24px rgba(15,23,42,0.18);
      }
      .elec-panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
      .elec-title { font-size:1.3rem; font-weight:900; color:#fff; letter-spacing:-0.3px; }
      .elec-sub { font-size:0.82rem; color:#94a3b8; margin-top:0.2rem; max-width:560px; line-height:1.4; }
      .elec-sub span { color:#fbbf24; font-weight:800; }
      .elec-horizon { display:flex; gap:0.3rem; }
      .elec-hbtn { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#cbd5e1; font-size:0.78rem; font-weight:700; padding:6px 11px; border-radius:8px; cursor:pointer; font-family:inherit; transition:all .15s; }
      .elec-hbtn:hover { background:rgba(255,255,255,0.12); }
      .elec-hbtn--on { background:#fbbf24; border-color:#fbbf24; color:#1e293b; }

      .elec-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:0.7rem; }
      .elec-card { display:block; background:#fff; color:#1e293b; border-radius:12px; padding:0.85rem 0.95rem; box-shadow:0 2px 8px rgba(0,0,0,0.10); border-top:4px solid var(--vcol, #cbd5e1); cursor:pointer; transition:transform .12s, box-shadow .12s; position:relative; }
      .elec-card:hover, .elec-card:focus-visible { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,0.16); outline:none; }
      .elec-card--good   { --vcol:#16a34a; }
      .elec-card--neutral{ --vcol:#94a3b8; }
      .elec-card--avoid  { --vcol:#d97706; }
      .elec-card--gold { box-shadow:0 0 0 2px #fbbf24, 0 4px 14px rgba(251,191,36,0.25); }
      .elec-card--gold:hover { box-shadow:0 0 0 2px #fbbf24, 0 8px 22px rgba(251,191,36,0.32); }

      /* Verdikta virsraksts: kam logs piemērots / neitrāls */
      .elec-verdict { display:flex; flex-wrap:wrap; align-items:center; gap:0.35rem; margin-bottom:0.55rem; padding-bottom:0.5rem; border-bottom:1px solid #f1f5f9; }
      .elec-verdict-label { font-size:0.84rem; font-weight:900; color:var(--vcol); }
      .elec-verdict-note { font-size:0.74rem; color:#94a3b8; font-weight:600; }
      .elec-suit { font-size:0.74rem; font-weight:700; color:#15803d; background:#dcfce7; padding:3px 8px; border-radius:999px; }
      .elec-unsuit-row { display:flex; flex-wrap:wrap; align-items:center; gap:0.35rem; margin-bottom:0.55rem; }
      .elec-unsuit-lead { font-size:0.72rem; font-weight:700; color:#b91c1c; }
      .elec-unsuit { font-size:0.72rem; font-weight:700; color:#b91c1c; background:#fee2e2; padding:3px 8px; border-radius:999px; }

      .elec-when { display:flex; align-items:baseline; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.4rem; }
      .elec-rank { font-size:0.82rem; font-weight:900; color:#94a3b8; }
      .elec-rank--top { color:#d97706; }
      .elec-day { font-size:0.9rem; font-weight:800; color:#0f172a; }
      .elec-time { font-size:0.92rem; font-weight:900; color:#1d4ed8; }
      .elec-gold-badge { font-size:0.68rem; font-weight:800; color:#a16207; background:#fef9c3; padding:2px 7px; border-radius:999px; }
      .elec-score-row { display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; }
      .elec-bar { flex:1; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden; max-width:130px; }
      .elec-bar > div { height:100%; border-radius:3px; }
      .elec-score-num { font-size:0.82rem; font-weight:900; }
      .elec-conf { font-size:0.66rem; font-weight:700; padding:2px 7px; border-radius:999px; margin-left:auto; }
      .elec-conf--high { background:#dcfce7; color:#15803d; }
      .elec-conf--mid  { background:#dbeafe; color:#1d4ed8; }
      .elec-conf--base { background:#f1f5f9; color:#64748b; }
      .elec-reasons { display:flex; flex-wrap:wrap; gap:0.3rem; }
      .elec-chip { font-size:0.7rem; font-weight:600; color:#475569; background:#f1f5f9; padding:3px 8px; border-radius:6px; line-height:1.3; }
      .elec-chip--gold { background:#fef9c3; color:#a16207; font-weight:700; }
      .elec-open-hint { font-size:0.68rem; color:#94a3b8; font-weight:700; margin-top:0.6rem; opacity:0; transition:opacity .12s; }
      .elec-card:hover .elec-open-hint, .elec-card:focus-visible .elec-open-hint { opacity:1; }

      .elec-avoid-divider { font-size:0.82rem; font-weight:800; color:#fca5a5; margin:1.3rem 0 0.7rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.10); }
      .elec-empty { font-size:0.85rem; color:#94a3b8; padding:1rem; text-align:center; line-height:1.5; }
      .elec-foot { font-size:0.7rem; color:#64748b; margin-top:0.9rem; line-height:1.4; }
      @media (max-width: 640px) {
        .elec-cards { grid-template-columns:1fr; }
      }

      /* ── 9 dienu biznesa kalendārs ── */
      .bizcal-macro { display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem 0.9rem; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.10); border-radius:10px; padding:0.55rem 0.85rem; margin-bottom:0.9rem; font-size:0.76rem; color:#cbd5e1; }
      .bizcal-macro-lead { font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; font-size:0.68rem; }
      .bizcal-macro-item b { color:#fbbf24; }
      .bizcal-unk { background:rgba(251,191,36,0.10); border:1px solid rgba(251,191,36,0.30); color:#fde68a; border-radius:10px; padding:0.6rem 0.85rem; margin-bottom:0.9rem; font-size:0.76rem; line-height:1.45; }

      .bizcal-help { display:flex; flex-wrap:wrap; align-items:center; gap:0.4rem 0.6rem; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.10); border-radius:10px; padding:0.6rem 0.85rem; margin-bottom:0.9rem; font-size:0.76rem; color:#cbd5e1; line-height:1.5; }
      .bizcal-help-lbl { font-weight:800; color:#fbbf24; text-transform:uppercase; letter-spacing:0.5px; font-size:0.68rem; }
      .bizcal-help-chip { font-weight:800; color:var(--c); background:rgba(255,255,255,0.08); padding:1px 8px; border-radius:999px; }

      .bizcal-nav { display:flex; align-items:center; justify-content:space-between; gap:0.6rem; margin-bottom:1rem; flex-wrap:wrap; }
      .bizcal-nav-right { display:flex; gap:0.4rem; }
      .bizcal-navbtn { background:#fbbf24; border:1px solid #fbbf24; color:#1e293b; font-size:0.8rem; font-weight:800; padding:7px 13px; border-radius:9px; cursor:pointer; font-family:inherit; transition:all .15s; }
      .bizcal-navbtn:hover { background:#f59e0b; border-color:#f59e0b; }
      .bizcal-navbtn--ghost { background:rgba(255,255,255,0.06); border-color:rgba(255,255,255,0.18); color:#cbd5e1; }
      .bizcal-navbtn--ghost:hover { background:rgba(255,255,255,0.14); color:#fff; }
      .bizcal-range { font-size:0.86rem; font-weight:800; color:#fff; }

      .bizcal-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:0.7rem; }
      @media (max-width: 980px) { .bizcal-grid { grid-template-columns:repeat(2, 1fr); } }
      @media (max-width: 600px) { .bizcal-grid { grid-template-columns:1fr; } }

      .bizcal-card { background:#fff; color:#1e293b; border-radius:12px; padding:0.85rem 0.9rem; box-shadow:0 2px 8px rgba(0,0,0,0.10); border-top:4px solid var(--acc,#cbd5e1); cursor:pointer; transition:transform .12s, box-shadow .12s; display:flex; flex-direction:column; gap:0.5rem; }
      .bizcal-card:hover, .bizcal-card:focus-visible { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,0.16); outline:none; }
      .bizcal-card--today { box-shadow:0 0 0 2px var(--acc), 0 4px 14px rgba(0,0,0,0.14); }

      .bizcal-head { display:flex; align-items:center; justify-content:space-between; gap:0.4rem; }
      .bizcal-date { display:flex; align-items:baseline; gap:0.4rem; flex-wrap:wrap; }
      .bizcal-wd { font-size:0.74rem; font-weight:700; color:#64748b; }
      .bizcal-dm { font-size:1.0rem; font-weight:900; color:#0f172a; }
      .bizcal-today-badge { font-size:0.6rem; font-weight:900; color:#fff; background:#0f172a; padding:1px 6px; border-radius:999px; letter-spacing:0.5px; }
      .bizcal-quality { font-size:0.72rem; font-weight:900; padding:3px 10px; border-radius:999px; white-space:nowrap; border:1px solid var(--accbd); background:var(--accbg); color:var(--acc); }

      .bizcal-summary { font-size:0.78rem; color:#334155; line-height:1.4; }

      .bizcal-action { display:flex; align-items:flex-start; gap:0.4rem; font-size:0.8rem; color:#0f172a; line-height:1.4; background:var(--accbg); border:1px solid var(--accbd); border-radius:9px; padding:0.5rem 0.6rem; }
      .bizcal-action b { color:#1d4ed8; font-size:0.92rem; }
      .bizcal-action--hard b { color:#b45309; }
      .bizcal-act-ic { font-size:1rem; line-height:1.2; }
      .bizcal-act-suit { color:#15803d; font-weight:700; font-size:0.74rem; }

      .bizcal-heat { display:grid; grid-template-columns:repeat(24, 1fr); gap:1px; height:18px; border-radius:4px; overflow:hidden; }
      .bizcal-hr { position:relative; height:100%; }
      .bizcal-hr-mark { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:8px; font-style:normal; }
      .bizcal-heat-axis { display:flex; justify-content:space-between; font-size:0.58rem; color:#94a3b8; font-weight:700; margin-top:-2px; }

      .bizcal-hrs { font-size:0.74rem; line-height:1.35; }
      .bizcal-hrs--good  { color:#15803d; }
      .bizcal-hrs--avoid { color:#b91c1c; }
      .bizcal-hrs--none  { color:#94a3b8; font-style:italic; }
      .bizcal-warn { font-size:0.74rem; font-weight:700; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:7px; padding:0.3rem 0.5rem; cursor:help; }
      .bizcal-less { font-size:0.72rem; color:#94a3b8; }

      .bizcal-why-wrap { display:flex; flex-direction:column; gap:2px; border-top:1px solid #f1f5f9; padding-top:0.5rem; }
      .bizcal-why-lbl { font-size:0.62rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; }
      .bizcal-why { font-size:0.72rem; color:#475569; cursor:help; }
      .bizcal-why b { color:#334155; font-weight:700; }
      .bizcal-dot { display:inline-block; width:7px; height:7px; border-radius:50%; margin-right:5px; vertical-align:middle; }

      .bizcal-foot { display:flex; align-items:center; justify-content:space-between; gap:0.4rem; margin-top:auto; padding-top:0.3rem; }
      .bizcal-cons { font-size:0.64rem; font-weight:800; padding:2px 7px; border-radius:999px; }
      .bizcal-cons--ok    { background:#dcfce7; color:#15803d; }
      .bizcal-cons--split { background:#f1f5f9; color:#64748b; }
      .bizcal-cons--one   { background:#f8fafc; color:#94a3b8; }
      .bizcal-conf { font-size:0.62rem; font-weight:700; padding:2px 7px; border-radius:999px; }
      .bizcal-conf--high { background:#dcfce7; color:#15803d; }
      .bizcal-conf--mid  { background:#dbeafe; color:#1d4ed8; }
      .bizcal-conf--base { background:#f1f5f9; color:#64748b; }

      .bizcal-legend { display:flex; flex-wrap:wrap; gap:0.4rem 1rem; margin-top:0.9rem; font-size:0.68rem; color:#94a3b8; }
      .bizcal-legend span { display:inline-flex; align-items:center; gap:0.35rem; }
      .bizcal-legend i { width:14px; height:10px; border-radius:3px; display:inline-block; }
    </style>
    <div class="karte-controls">
        <div class="kc-grp"><label>Datums</label><input type="date" id="karte-date"></div>
        <div class="kc-grp"><label>Laiks</label><input type="time" id="karte-time"></div>
        <div class="kc-grp karte-loc-field"><label>Lokācija</label>
            <input type="text" id="karte-loc" autocomplete="off" placeholder="Pilsēta…">
            <div id="karte-loc-list" class="autocomplete-items"></div>
        </div>
        <div class="kc-grp"><label>Skatpunkts (Zodiaks)</label>
            <div>${seg('tropical')}${seg('sidereal')}</div>
            <div id="karte-zhint" class="karte-zhint"></div>
        </div>
        <div class="karte-slider-wrap">
            <label>Laika ritināšana — <span id="karte-when" style="color:#0f172a;font-weight:700;">—</span></label>
            <input type="range" id="karte-slider" min="${-SLIDER_RANGE}" max="${SLIDER_RANGE}" step="15" value="0">
        </div>
        <button type="button" class="karte-now" id="karte-now">⟳ Tagad</button>
    </div>
    
    <div id="karte-headline" class="karte-headline"></div>

    <!-- NB: "Kad rīkoties — biznesa kalendārs" NOŅEMTS 2026-07-08 — dublējās ar jauno
         'Dienas · biznesa pārskatu' (Prognozes cilnes augšā). refreshElectional() paliek
         (guard: if(!box) return;), tāpēc atlikušie izsaukumi droši no-op. -->

    <!-- Unified view card switcher -->
    <div class="sky-tab-container">
        ${tabBtn('wheel', '🌌', 'Klasiskais Ritenis')}
        ${tabBtn('clock', '🕰️', 'Kosmiskais Pulkstenis')}
        ${tabBtn('solar', '🌌', 'Saules Sistēma')}
    </div>

    <!-- MAIN CARD CONTAINER -->
    <div class="sky-main-container">
        
        <!-- Header Info (Dynamic Title, Description, and Sub-info) -->
        <div style="text-align:center; margin-bottom:1.5rem;">
            <h3 id="sky-view-title" style="font-size:1.4rem; color:#0f172a; margin:0 0 0.4rem 0; font-weight:800;">—</h3>
            <p id="sky-view-desc" style="font-size:0.88rem; color:#64748b; margin:0; max-width:650px; margin-left:auto; margin-right:auto; line-height:1.4;">—</p>
        </div>

        <div class="sky-layout-grid">
            <!-- Left Sidebar -->
            <div id="sky-cards-left-zone" class="sky-cards-column"></div>

            <!-- Center Column -->
            <div class="sky-center-column">
                <!-- THE MAIN SVG CONTAINER -->
                <div id="sky-view-svg-box"></div>

                <!-- Interactive Control Panel (Time modes and Checkboxes) -->
                <div style="width:100%; border-top:1px solid #f1f5f9; margin-top:1.2rem; padding-top:1.2rem;">
                    
                    <!-- Time Mode Selector -->
                    <div style="display:flex; justify-content:center; align-items:center; gap:1.2rem; margin-bottom:1.2rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.6rem 1rem; max-width:600px; margin-left:auto; margin-right:auto; flex-wrap:wrap;">
                        <span style="font-size:0.8rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">📅 Laika režīms:</span>
                        <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.82rem; font-weight:700; color:#0f172a; cursor:pointer;">
                            <input type="radio" name="data-mode" id="data-mode-transit" checked style="cursor:pointer;" onchange="window.triggerAstroRedraw()">
                            <span>🔵 Šobrīd debesīs (Tranzīti)</span>
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.82rem; font-weight:700; color:#0f172a; cursor:pointer; ${hasProfile ? '' : 'opacity:0.45; cursor:not-allowed;'}" ${natalDisabledAttr} ${natalTitleAttr}>
                            <input type="radio" name="data-mode" id="data-mode-natal" ${natalDisabledAttr} style="cursor:pointer;" onchange="window.triggerAstroRedraw()">
                            <span>👤 Dzimšanas karte (Natālais)</span>
                        </label>
                    </div>

                    <!-- Toggles Title -->
                    <div style="font-size:0.85rem; font-weight:800; color:#1e293b; text-align:center; margin-bottom:0.8rem;">🔌 Rādīt/paslēpt ietekmju saites un stāvokļus:</div>
                    
                    <!-- Grid of Checkboxes (ģenerēti no TOGGLE_DEFS) -->
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; width:100%; max-width:960px; margin:0 auto;">
                        ${TOGGLE_DEFS.map(t => `
                        <label style="display:flex; flex-direction:column; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.6rem 0.8rem; cursor:pointer; transition:all 0.2s;">
                            <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; font-weight:700; color:${t.col};">
                                <input type="checkbox" id="${t.id}" checked style="cursor:pointer;" onchange="window.triggerAstroRedraw()">
                                <span>${t.label}</span>
                            </div>
                            <span style="font-size:0.7rem; color:#64748b; margin-top:0.25rem; line-height:1.2;">${t.desc}</span>
                        </label>`).join("")}
                    </div>
                </div>

                <!-- DYNAMIC LEGEND AREA -->
                <div id="sky-view-legend" style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid #f1f5f9; width:100%;"></div>
            </div>

            <!-- Right Sidebar -->
            <div id="sky-cards-right-zone" class="sky-cards-column"></div>
        </div>

        <!-- NB: "🌡️ Dienas kosmisko laikapstākļu josla (24h)" NOŅEMTA 2026-07-08 — dublējās
             ar 'Dienas · biznesa pārskata' 24h laika joslu. Renderētājs (weatherBarBox)
             ir aizsargāts ar if(weatherBarBox && _profile), tāpēc droši izlaižas. -->

    </div>

    `;
}

export function initSkyChart(profile) {
    _profile = profile || window.currentProfile1 || null;
    _state.offsetMin = 0;
    clearElectionalCache(); // svaigs profils → pārrēķinām dienu šūnas
    if (!_state.skyView) {
        _state.skyView = 'wheel'; // default to Klasiskais Ritenis
    }

    // Inline onchange handleri (filtri, laika režīms) izsauc šo — piešķiram vienreiz.
    window.triggerAstroRedraw = redraw;

    // ── Biznesa kalendāra (9 dienas, ±3 bīdāms) apdarinātāji ────────────────
    window.shiftBizCal = (delta) => {
        _state.calendar.startOffset += delta;
        refreshElectional();
    };
    window.resetBizCal = () => {
        _state.calendar.startOffset = 0;
        refreshElectional();
    };
    window.focusElectionalDay = (date, startHour) => {
        const dEl = document.getElementById('karte-date');
        const tEl = document.getElementById('karte-time');
        if (dEl) dEl.value = date;
        if (tEl) tEl.value = String(Math.max(0, Math.min(23, Math.round(startHour)))).padStart(2, '0') + ':00';
        _state.offsetMin = 0;
        const sl = document.getElementById('karte-slider'); if (sl) sl.value = 0;
        // Natālais režīms ignorē datumu → pārslēdzam uz tranzītiem, lai izvēlētā diena tiešām parādītos.
        const transit = document.getElementById('data-mode-transit'); if (transit) transit.checked = true;
        redraw();
        document.getElementById('weather-bar-container')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.setSkyView = (view) => {
        _state.skyView = view;
        const root = document.getElementById('karte-root');
        if (root) {
            root.querySelectorAll('.sky-tab-btn').forEach(btn => {
                btn.classList.toggle('sky-tab-btn--active', btn.getAttribute('data-view') === view);
            });
        }
        redraw();
    };

    const root = document.getElementById('karte-root');
    if (!root) { console.warn('[KARTE] nav #karte-root'); return; }
    root.innerHTML = scaffold();

    // noklusējumi: lokācija = šībrīža lokācija no augšējā paneļa
    const src = document.getElementById('ui-current-location');
    const locEl = document.getElementById('karte-loc');
    if (src && locEl) {
        locEl.value = src.value;
        locEl.setAttribute('data-lat', src.getAttribute('data-lat') || '56.9496');
        locEl.setAttribute('data-lon', src.getAttribute('data-lon') || '24.1052');
        locEl.setAttribute('data-timezone', src.getAttribute('data-timezone') || 'Europe/Riga');
    }

    // noklusējumi: datums = šodien, laiks = sistēmas laiks (lokācijas joslā)
    const tz = locEl?.getAttribute('data-timezone');
    let nowD, nowT;
    if (tz && window.moment) {
        const m = window.moment.tz(tz);
        nowD = m.format('YYYY-MM-DD'); nowT = m.format('HH:mm');
    } else {
        const n = new Date();
        nowD = n.toISOString().split('T')[0];
        nowT = String(n.getHours()).padStart(2,'0') + ':' + String(n.getMinutes()).padStart(2,'0');
    }
    document.getElementById('karte-date').value = nowD;
    document.getElementById('karte-time').value = nowT;

    // autocomplete lokācijai
    const listEl = document.getElementById('karte-loc-list');
    if (locEl && listEl) setupAutocomplete(locEl, listEl);

    applyZodiacMeta();

    // notikumi
    const reset = () => { _state.offsetMin = 0; const sl = document.getElementById('karte-slider'); if (sl) sl.value = 0; };
    document.getElementById('karte-date').addEventListener('change', () => { reset(); redraw(); });
    document.getElementById('karte-time').addEventListener('change', () => { reset(); redraw(); });
    new MutationObserver(() => { reset(); redraw(); }).observe(locEl, { attributes:true, attributeFilter:['data-lat','data-lon','data-timezone'] });

    document.getElementById('karte-slider').addEventListener('input', (e) => { _state.offsetMin = parseInt(e.target.value, 10) || 0; redraw(); });
    root.querySelectorAll('.karte-zbtn').forEach(btn => btn.addEventListener('click', () => {
        _state.zodiac = btn.getAttribute('data-z');
        root.querySelectorAll('.karte-zbtn').forEach(b => b.classList.toggle('karte-zbtn--on', b === btn));
        applyZodiacMeta();
        redraw();
    }));
    document.getElementById('karte-now').addEventListener('click', () => {
        reset();
        if (tz && window.moment) { const m = window.moment.tz(tz); document.getElementById('karte-date').value = m.format('YYYY-MM-DD'); document.getElementById('karte-time').value = m.format('HH:mm'); }
        redraw();
    });

    redraw();

    // "Kad rīkoties" — skenēšana (neatkarīga no slīdņa/datuma)
    refreshElectional();
}
