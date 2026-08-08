// 🧑‍⚕️ Speciālista ieteikums — virtuālā konsīlija dzinējs (t3 'Psiholoģija').
// ─────────────────────────────────────────────────────────────────────────
// 4 virtuālie speciālisti (klīniskais psihologs · psihoterapeits · psihiatrs ·
// Junga psihoanalītiķis) katrs atbild uz savu 4 jautājumu protokolu par VIENU
// profilu. NEĢENERĒ jaunus apgalvojumus no nulles — apvieno, interpretē un
// sasaista jau aprēķinātos t3 cilnes datus (careerAnchors, existentialAudit,
// psychosomaticAudit, relationshipDynamics, vedic/western psiholoģija, timing,
// Arudha, BaZi elementi, tranzīti).
//
// Variāciju plašums (lai nelielas profila izmaiņas maina tekstu) nāk no:
//   1) avotu augstās kardinalitātes — 8 enkuri × 4 piesaistes × 4 jātnieki ×
//      9 dharmas × 13 ķeltu koki × 7 dzinēji × 4 bioritmi × 12×12 Lagna/Arudha …
//   2) skaitlisko joslu sliekšņiem (S1%, V, boundary, psychosom, elementu %)
//   3) krustu sintēzes noteikumiem (piesaiste×paterns, stils×vide,
//      akūts×hronisks, enkurs×dzinējs) — pie robežām teksts pārslēdzas uz citu zaru.
//
// Fail-safe (sk. [[audit-t3-fail-confident]]): trūkstot sadaļas pamatdatiem,
// sadaļas text = null → UI rāda godīgu piezīmi, NEVIS no 50-noklusējumiem
// fabricētu lasījumu.

import { deriveDIV, pickDriverKey, DRIVERS } from '../ui/sections/hacker_panel.js?v=7';
import { computeArudhaLagna } from './hidden_insights.js?v=10';
import { computeMaskSynthesis } from './mask_synthesis.js?v=6';
import { getCelticTree } from '../ui/sections/psych_overview_map.js?v=33';

// ── Teksta šuvju palīgi (kā investor_memo.js) ────────────────────────────────
const lc  = (t) => t ? t.charAt(0).toLowerCase() + t.slice(1) : '';
const uc  = (t) => t ? t.charAt(0).toUpperCase() + t.slice(1) : '';
const dot = (t) => t ? (/[.!?…]$/.test(t.trim()) ? t.trim() : t.trim() + '.') : '';
const num = (x) => { if (x === null || x === undefined || x === '') return NaN; const n = Number(x); return Number.isFinite(n) ? n : NaN; };
// psychosomatic_audit elementu objektos `element` ir garais virsraksts
// ("Uguns · Aizrautība un Nervu Sistēma") — inline tekstam un cikla kartēm vajag tīro vārdu.
const plainEl = (e) => String(e || '').split('·')[0].trim();

// Personības trait bez fabricēta noklusējuma — trūkstošs = NaN (ne 50!).
function traitPct(profile, id) {
    for (const cat of (profile?.personality || [])) {
        for (const t of (cat.traits || [])) {
            if (t.id === id) return num(t.pct);
        }
    }
    return NaN;
}

// ═════════════════════════════════════════════════════════════════════════════
// KOPLIETOTIE KRUSTA NOTEIKUMI — lieto gan speciālistu sadaļas, gan konsīlijs
// ═════════════════════════════════════════════════════════════════════════════

// Izdegšanas koridors: bioritma "nāves zona" × vājākā enkura "nāves zona".
// Ja abi neatkarīgie mērījumi rāda vienu tēmu → konverģence; ja pretējas → šaurs koridors.
const RHYTHM_KILL   = { explosive: 'monotony', steady: 'chaos', cyclical: 'monotony', adaptive: 'monotony' };
const BOUNDARY_KILL = { technical: 'monotony', security: 'monotony', lifestyle: 'monotony',
                        challenge: 'chaos', entrepreneurial: 'chaos',
                        management: 'other', autonomy: 'other', service: 'other' };
function burnoutCorridor(profile) {
    const rKey = profile?.existentialAudit?.mezo?.primary?.key || null;
    const bKey = profile?.careerAnchors?.boundary?.key || null;
    const r = rKey ? RHYTHM_KILL[rKey] : null;
    const b = bKey ? BOUNDARY_KILL[bKey] : null;
    if (!r || !b || b === 'other') return null;
    if (r === b) return { kind: r === 'monotony' ? 'same-monotony' : 'same-chaos' };
    return { kind: 'corridor' };
}

// Dharma (Atmakaraka) × Šeina enkuri — vai dvēseles uzdevums un karjeras dzinējs sakrīt.
const DHARMA_ANCHOR_FIT = {
    'Saule':    ['management', 'challenge'],
    'Mēness':   ['service', 'lifestyle'],
    'Marss':    ['challenge', 'management'],
    'Merkurs':  ['technical', 'service'],
    'Jupiters': ['service', 'technical'],
    'Venera':   ['lifestyle', 'service'],
    'Saturns':  ['security', 'management', 'technical'],
    'Rahu':     ['entrepreneurial', 'challenge', 'autonomy'],
    'Ketu':     ['autonomy', 'technical'],
};
function dharmaAnchorRelation(profile) {
    const planet = String(profile?.existentialAudit?.macro?.atmakarakaPlanet || '').replace(/^Meness$/, 'Mēness');
    const dharma = profile?.existentialAudit?.macro?.dharma || null;
    const top = profile?.careerAnchors?.primary || null;
    const bnd = profile?.careerAnchors?.boundary || null;
    const fit = DHARMA_ANCHOR_FIT[planet];
    if (!dharma || !fit || !top) return null;
    if (fit.includes(top.key)) return { status: 'aligned', dharmaLv: dharma.lv, anchorLv: top.lv };
    if (bnd && fit.includes(bnd.key)) return { status: 'growth', dharmaLv: dharma.lv, anchorLv: bnd.lv };
    return { status: 'gap', dharmaLv: dharma.lv, anchorLv: top.lv };
}

// Kānemana dominante × Cynefin vide — pilna 3×4 krusta tabula (12 kombinācijas).
// Aizstāj veco divzaru isAligned tekstu; arī labo kļūdu, kur 'adaptive' profils
// (isAligned pēc formulas vienmēr false) nepatiesi dabūja "stils un vide nesaskan".
const KAHNEMAN_CYNEFIN = {
    's1:chaotic':          'Stils un vide šeit ir retā pilnīgā saskaņā: krīzes vide prasa rīcību pirms pilnas informācijas, un tieši tā šī galva strādā dabiski. Higiēna — pēckrīzes izvērtējums "aukstā galvā", lai ātrums nepārvēršas par vienīgo pieejamo režīmu.',
    's1:complex':          'Intuīcija eksperimentu vidē strādā labi, ja to formalizē: hipotēze → mazs mēģinājums → korekcija. Risks — pirmo veiksmīgo paternu pasludināt par likumu; pretlīdzeklis — apzināti meklēt vienu pretpiemēru, pirms mērogot.',
    's1:complicated':      'Šķēre: vide prasa ekspertu analīzi, galva grib lēmumu tūlīt. Kompensācija strādā, ja intuīcijai atvēl pirmo versiju, bet gala lēmumu izlaiž caur kontrolsarakstu vai kolēģa "otro skatu" — bez šī drošinātāja kļūdas būs retas, bet dārgas.',
    's1:simple':           'Kārtības vidē intuitīvais stils garlaikojas un sāk improvizēt tur, kur vajag procedūru — kļūdas šeit rada improvizācija, ne kompetences trūkums. Der automatizēt rutīnu līdz minimumam un intuīcijai atstāt izņēmumu apstrādi.',
    's2:simple':           'Analītiskais stils kārtības vidē ir saskaņā ar rezervi: procedūras tiek ne tikai ievērotas, bet uzlabotas. Risks ir pārinženierija — vienkāršu procesu pārvēršana sarežģītos; jautājums "vai kāds to tiešām lietos?" der kā bremze.',
    's2:complicated':      'Ideāla saskaņa: ekspertīzes vide ar pareizām atbildēm ir šīs galvas dzimtā valoda — dot laiku analīzei nozīmē saņemt precīzu rezultātu. Vienīgā higiēna — iepriekš norunāts lēmuma termiņš, lai precizitāte nekļūst par bezgalību.',
    's2:complex':          'Šķēre: vide bez pareizām atbildēm, galva meklē pareizo atbildi. Analīzes paralīzes risku mazina lēmumu sadalīšana atgriezeniskos mikro-soļos — mērķis nav "izlemt pareizi", bet "izlemt lēti un pārbaudīt".',
    's2:chaotic':          'Grūtākā kombinācija: krīze prasa rīcību ātrāk, nekā analīze pagūst. Kompensācija — iepriekš sagatavoti "ja–tad" protokoli, ko krīzē tikai izpilda; analītiskais spēks tad strādā pirms notikuma, ne tā laikā.',
    'adaptive:chaotic':    'Adaptīvā pārslēgšanās krīzes vidē ir vērtīga, bet ar vienu nosacījumu: ātrais režīms jāizvēlas apzināti un agri — svārstīšanās starp režīmiem krīzes vidū maksā vairāk nekā jebkurš no tiem atsevišķi.',
    'adaptive:complex':    'Adaptīvais stils eksperimentu videi der gandrīz ideāli: intuīcija ģenerē hipotēzes, analīze tās šķiro. Vienīgais risks — režīmu maiņa bez pēdām; der fiksēt, kurā režīmā pieņemts katrs lielais lēmums.',
    'adaptive:complicated':'Ekspertīzes vidē adaptīvajam stilam apzināti jānoturas lēnajā režīmā līdz galam — tipiskākā kļūda šeit ir puspabeigta analīze, kas "pabeigta" ar intuīciju.',
    'adaptive:simple':     'Kārtības vidē adaptīvais stils viegli garlaikojas; tā vērtība parādās izņēmumu brīžos, kad procedūra beidzas. Der apzināti dalīt: rutīna — procesam, sev — anomālijas.',
};

// Šeina enkurs × motivācijas dzinējs — saskaņas karte + virzienu frāzes šķēres tekstam.
const ANCHOR_DRIVER_FIT = {
    technical:       ['Meistarība', 'Ideja'],
    management:      ['Statuss', 'Nauda'],
    autonomy:        ['Brīvība', 'Meistarība'],
    security:        ['Drošība', 'Nauda'],
    entrepreneurial: ['Ideja', 'Nauda', 'Brīvība'],
    service:         ['Ideja', 'Piederība'],
    challenge:       ['Statuss', 'Meistarība'],
    lifestyle:       ['Brīvība', 'Piederība'],
};
const ANCHOR_DIR = {
    technical: 'dziļu meistarību', management: 'atbildību un lēmumu varu', autonomy: 'rīcības brīvību',
    security: 'paredzamību un garantijas', entrepreneurial: 'iespēju radīt savu', service: 'jēgu, kas kalpo citiem',
    challenge: 'pretinieku, ko pārvarēt', lifestyle: 'dzīves un darba līdzsvaru',
};
const DRIVER_DIR = {
    Ideja: 'misijas sajūtu', Statuss: 'redzamību un titulu', Nauda: 'taisnīgu, izmērāmu atlīdzību',
    Brīvība: 'grafika un metodes brīvību', Drošība: 'stabilitātes garantijas',
    Meistarība: 'izaugsmi un sarežģītus uzdevumus', Piederība: 'komandas siltumu',
};

// EN → LV planētu/aspektu vārdnīca (western.js tranzīti glabā EN vārdus)
const PLANET_LV = {
    Sun: 'Saule', Moon: 'Mēness', Mercury: 'Merkurs', Venus: 'Venera', Mars: 'Marss',
    Jupiter: 'Jupiters', Saturn: 'Saturns', Uranus: 'Urāns', Neptune: 'Neptūns',
    Pluto: 'Plutons', Chiron: 'Hirons', Lilith: 'Lilita', Ascendant: 'Ascendents', MC: 'MC',
};
const ASPECT_LV = { Conjunction: 'konjunkcija', Square: 'kvadrāts', Opposition: 'opozīcija', Trine: 'trigons' };
// Smaguma secība "nozīmīgākā" tranzīta izvēlei — lēnākā planēta sver vairāk.
const TRANSIT_WEIGHT = { Pluto: 7, Saturn: 6, Neptune: 5, Uranus: 4, Chiron: 3, Jupiter: 2, Mars: 1 };

// ═════════════════════════════════════════════════════════════════════════════
// 1. 🧠 KLĪNISKAIS UN VESELĪBAS PSIHOLOGS
// ═════════════════════════════════════════════════════════════════════════════

function buildClinical(profile) {
    const ca = profile?.careerAnchors || null;
    const ex = profile?.existentialAudit || null;

    // ── 1.1. Kognitīvā arhitektūra (Kānemans S1/S2 + Cynefin vide) ───────────
    let cognitive = null;
    if (ca?.kahneman) {
        const k = ca.kahneman;
        const s1 = k.s1pct, s2 = k.s2pct;
        const envLv = ca.cynefin?.primaryMeta?.lv || null;
        const envDesc = ca.cynefin?.primaryMeta?.description || '';
        const parts = [];
        if (k.dominant === 'adaptive') {
            parts.push(`Kognitīvā arhitektūra ir <b>adaptīva</b>: ātrā, intuitīvā apstrāde (Sistēma 1) aizņem ~${s1}%, lēnā, analītiskā (Sistēma 2) — ~${s2}%. Nav viena iebūvēta režīma — cilvēks pārslēdzas atkarībā no situācijas, un tas ir situācijas inteliģences rādītājs, ne neizlēmības pazīme.`);
        } else if (k.dominant === 's1') {
            const grade = s1 >= 75 ? 'izteikti dominē' : s1 >= 62 ? 'skaidri dominē' : 'nedaudz pārsver';
            parts.push(`Informācijas apstrādē ${grade} <b>Kānemana Sistēma 1</b> (~${s1}% pret ${s2}%): lēmumi top ātri, no paterniem un uzkrātās pieredzes, negaidot pilnu datu ainu. Stiprā puse — reakcijas ātrums neskaidrībā; cena — sistemātiskas domāšanas kļūdas (pārmērīga pašpaļāvība, pirmā iespaida enkurs), ja neviens neliek apstāties.`);
        } else {
            const grade = s2 >= 75 ? 'izteikti dominē' : s2 >= 62 ? 'skaidri dominē' : 'nedaudz pārsver';
            parts.push(`Informācijas apstrādē ${grade} <b>Kānemana Sistēma 2</b> (~${s2}% pret ${s1}%): pirms lēmuma tiek prasīti dati, pārbaude un loģiska argumentācija. Stiprā puse — precizitāte un noturība pret manipulāciju; cena — lēnums un analīzes paralīzes risks tur, kur logs rīcībai ir īss.`);
        }
        if (envLv) {
            parts.push(`Dabiskā lēmumu vide pēc Cynefin ir <b>${envLv}</b> — ${lc(dot(envDesc))}`);
            // Stila × vides krusta lasījums — 12 kombināciju tabula (ne binārs aligned/misaligned).
            const cross = KAHNEMAN_CYNEFIN[`${k.dominant}:${ca.cynefin?.primary || ''}`];
            if (cross) parts.push(cross);
        }
        cognitive = parts.join(' ');
    }

    // ── 1.2. Bioritms un dienas dizains ──────────────────────────────────────
    let biorhythm = null;
    const rhythm = ex?.mezo?.primary || null;
    const micro  = ex?.micro || null;
    const celtic = getCelticTree(profile?.birth_info?.date || '');
    if (rhythm || micro || celtic) {
        const parts = [];
        if (rhythm) {
            parts.push(`Dabas dotais bioritms ir <b>${rhythm.lv}</b>: ${lc(dot(rhythm.biotope))}`);
            if (ex?.mezo?.isHybrid && ex?.mezo?.secondary) {
                parts.push(`Profils ir hibrīds — fonā darbojas arī ${lc(ex.mezo.secondary.lv || '')} komponente, tāpēc ideālais grafiks nav viens režīms, bet abu ritmu apzināta maiņa.`);
            }
            const prodKey = {
                explosive: 'Produktivitātes atslēga — sprinti ar pilnu atslēgšanos starp tiem: intensīvs posms, redzama finiša līnija, tad reāla pauze. Vienmērīga slodze šo dzinēju nevis taupa, bet dzēš.',
                steady:    'Produktivitātes atslēga — stabils, aizsargāts dienas karkass: vienāds ritms, paredzami procesi, minimums pēkšņu virziena maiņu. Šo profilu izsmeļ nevis darba apjoms, bet haoss.',
                cyclical:  'Produktivitātes atslēga — sezonāla loģika: atļaut sev augstas intensitātes "vasaras" fāzes un neuzskatīt "ziemas" atkāpšanos par slinkumu. Cīņa pret savu vilni maksā dārgāk par pašu darbu.',
                adaptive:  'Produktivitātes atslēga — vairāki paralēli virzieni un regulāra konteksta maiņa: variācija šim profilam ir enerģijas avots, monotonija — slazds.',
            }[rhythm.key];
            if (prodKey) parts.push(prodKey);
        }
        if (micro?.morning) parts.push(`Ikdienas līmenī (BaZi ${micro.element || ''} plūsma): ${lc(dot(micro.morning))}`);
        if (celtic) parts.push(`Ķeltu koku kalendārā šī persona ir <b>${celtic.name}</b> (tips: ${celtic.type}) — ${lc(dot((celtic.traits || '').split(/(?<=\.)\s/)[0] || ''))}`);
        biorhythm = parts.join(' ');
    }

    // ── 1.3. Profesionālās vērtības un motivācijas sviras ────────────────────
    let values = null;
    if (ca?.allAnchors?.length) {
        const a1 = ca.allAnchors[0], a2 = ca.allAnchors[1];
        const parts = [];
        parts.push(`Dominējošais Šeina karjeras enkurs ir <b>${a1.lv}</b> (${a1.score}%): ${lc(dot((a1.description || '').split(/(?<=\.)\s/)[0] || ''))}`);
        if (a2) {
            const spread = a1.score - a2.score;
            if (spread <= 5) {
                parts.push(`Gandrīz tikpat stiprs ir otrais enkurs — <b>${a2.lv}</b> (${a2.score}%): loma, kas apmierina tikai vienu no abiem, atstās hronisku "kaut kā trūkst" sajūtu, tāpēc sarunās jārunā par abiem.`);
            } else if (spread >= 20) {
                parts.push(`Enkurs ir izteikti viendabīgs — nākamais (${a2.lv}, ${a2.score}%) atpaliek par ${spread} punktiem, tāpēc praktiski visas karjeras izvēles nosaka viens kritērijs: vai loma baro <b>${lc(a1.lv)}</b>.`);
            } else {
                parts.push(`Otrs balsts — <b>${a2.lv}</b> (${a2.score}%) — darbojas kā korektīvs: tas nenosaka virzienu, bet nosaka, no kādām lomām cilvēks klusi izvairīsies.`);
            }
        }
        if (a1.thrives) parts.push(`Vislabāk atmaksājas ${lc(dot(a1.thrives))}`);
        // Dzinēja "valūta" — tikai ja personības dati vispār eksistē (citādi deriveDIV fabricē 50/50/50)
        if ((profile?.personality || []).some(c => (c.traits || []).length)) {
            const ax = deriveDIV(profile);
            const driverKey = pickDriverKey(ax.D, ax.I, ax.V, profile?.synergy?.motivation);
            const driver = DRIVERS[driverKey]?.pro;
            if (driver) {
                parts.push(`Galvenā valūta sarunā un atalgojumā — <b>${driver.title}</b>: ${lc(dot(driver.valuta))} Atalgojuma forma, kas reāli strādā: ${lc(dot(driver.balva))}`);
                // Enkurs × dzinējs — saskaņas/šķēres krusta lasījums.
                const aDir = ANCHOR_DIR[a1.key], dDir = DRIVER_DIR[driverKey];
                if (aDir && dDir) {
                    parts.push((ANCHOR_DRIVER_FIT[a1.key] || []).includes(driverKey)
                        ? `Enkurs un ikdienas dzinējs velk vienā virzienā (enkurs prasa ${aDir}, dzinējs to pašu baro caur ${dDir}) — motivācijas sistēma ir iekšēji saskanīga. Ēnas puse: kad viss ir viens virziens, pateikt "nē" šai teritorijai kļūst gandrīz neiespējami, un tieši tur jāliek ārējās robežas.`
                        : `Vērā ņemama šķēre motivācijā: enkurs prasa ${aDir}, bet ikdienas dzinēju baro ${dDir}. Šāds cilvēks var izvēlēties "pareizās" lomas un tomēr justies tukšs — lomai jāatbilst enkuram, bet atalgojuma mehānikai un ikdienas ritmam jārunā dzinēja valodā; tie ir divi atsevišķi jautājumi.`);
                }
            }
        }
        values = parts.join(' ');
    }

    // ── 1.4. Aktuālais psiholoģiskais fons (tranzīti + operatīvais gads) ─────
    let background = null;
    const tr = Array.isArray(profile?.western?.transits) ? profile.western.transits : [];
    const timing = profile?.timing || null;
    if (tr.length || timing?.tactical || timing?.macro) {
        const parts = [];
        const crit = tr.filter(t => t.nature === 'critical');
        const harm = tr.filter(t => t.nature === 'harmonic');
        if (tr.length) {
            const heavy = [...crit].sort((a, b) => (TRANSIT_WEIGHT[b.transitingPlanet] || 0) - (TRANSIT_WEIGHT[a.transitingPlanet] || 0))[0] || null;
            if (crit.length > harm.length) {
                parts.push(`Pašreizējais tranzītu fons ir <b>saspringts</b> (${crit.length} kritiski pret ${harm.length} harmoniskiem aspektiem) — periods prasa vairāk pašregulācijas nekā parasti, un lēmumi zem emocijām šobrīd maksās dārgāk.`);
            } else if (harm.length > crit.length && harm.length > 0) {
                parts.push(`Pašreizējais tranzītu fons ir <b>atbalstošs</b> (${harm.length} harmoniski pret ${crit.length} kritiskiem aspektiem) — labs logs iniciatīvām, kas prasa vidi "ar aizvēju".`);
            } else if (crit.length === 0 && harm.length === 0) {
                parts.push(`Nozīmīgu lēno planētu tranzītu šobrīd nav — fons ir <b>kluss</b>, psiholoģiskā dienaskārtība nāk no iekšējiem, ne ārējiem procesiem.`);
            } else {
                parts.push(`Tranzītu fons ir <b>jaukts</b> (${crit.length} kritiski, ${harm.length} harmoniski) — spriedzes un atbalsta viļņi mijas, tāpēc plānošanā der īsāki soļi.`);
            }
            if (heavy) {
                const tp = PLANET_LV[heavy.transitingPlanet] || heavy.transitingPlanet;
                const np = PLANET_LV[heavy.natalPlanet] || heavy.natalPlanet;
                parts.push(`Smagākais aktīvais aspekts: tranzīta ${tp} ${ASPECT_LV[heavy.aspect] || lc(heavy.aspect || '')} ar natālo ${np} — tieši šī tēma šobrīd "spiež" visvairāk.`);
            }
        }
        if (timing?.tactical?.phaseMeta?.lv) {
            parts.push(`Operatīvais ${timing.tactical.year || 'šis'} gads BaZi ciklā prasa fokusu: <b>${timing.tactical.phaseMeta.lv}</b>${timing.tactical.alignedWithMacro ? ' — un tas sakrīt ar 10 gadu makro fāzi, tātad gads strādā pa straumei' : ''}.`);
        }
        if (timing?.macro?.transition?.active) {
            parts.push(`Būtiski: cilvēks atrodas <b>10 gadu cikla pārejas zonā</b> (${timing.macro.transition.kind === 'entering' ? 'jauna fāze tikai sākas' : 'vecā fāze noslēdzas'}) — dzīves fāžu maiņas punktā lielus, neatgriezeniskus lēmumus ieteicams nogaidīt.`);
        }
        if (timing?.anchorAlignment?.status === 'conflict') {
            parts.push(`Karjeras enkurs un pašreizējā dzīves fāze ir <b>konfliktā</b> — tas subjektīvi jūtams kā "daru pareizi, bet neiet"; šajā logā vajadzīgs alternatīvs ceļš, ne lielāka piepūle.`);
        } else if (timing?.anchorAlignment?.status === 'aligned') {
            parts.push(`Karjeras enkurs un pašreizējā dzīves fāze ir <b>saskaņā</b> — iekšējās vērtības un ārējais cikls velk vienā virzienā.`);
        }
        if (profile?.progressions?.moon_prog) parts.push(`Progresīvā Mēness fāze: ${lc(dot(String(profile.progressions.moon_prog)))}`);
        background = parts.join(' ');
    }

    // ── Speciālista slēdziens ────────────────────────────────────────────────
    let verdict = null;
    if (ca?.kahneman || rhythm) {
        const style = ca?.kahneman
            ? (ca.kahneman.dominant === 's1' ? 'intuitīvi ātrs' : ca.kahneman.dominant === 's2' ? 'analītiski pamatīgs' : 'adaptīvi elastīgs')
            : null;
        // Ritma nosaukums akuzatīvā ("jābūvē ap … ritmu") — lv lauks ir nominatīvā.
        const RHYTHM_ACC = { explosive: 'sprinta ritmu', steady: 'maratona ritmu', cyclical: 'viļņveida ritmu', adaptive: 'adaptīvo ritmu' };
        const rhythmBit = rhythm ? `; ikdiena jābūvē ap ${RHYTHM_ACC[rhythm.key] || `ritmu "${rhythm.lv}"`}` : '';
        verdict = `Funkcionāli ${style ? `<b>${style}</b> profils` : 'profils'}, kura efektivitāte ir vides jautājums, ne gribasspēka${rhythmBit}. Galvenā higiēna: dot lēmumu videi atbilstošu tempu un neplānot pret savu bioritmu.`;
    }

    return {
        key: 'clinical', icon: '🧠', color: '#0369a1',
        title: 'Psihologs',
        role: 'Kognitīvais stils · bioritms · profesionālās vērtības · aktuālais fons',
        sections: [
            { label: 'Kognitīvā arhitektūra',            sub: 'Kānemana S1/S2 un lēmumu vide',        text: cognitive },
            { label: 'Bioritms un dienas dizains',        sub: 'Dabas ritmi un produktivitātes atslēga', text: biorhythm },
            { label: 'Profesionālās vērtības un valūta',  sub: 'Šeina enkuri un motivācijas sviras',    text: values },
            { label: 'Aktuālais psiholoģiskais fons',     sub: 'Tranzīti un operatīvais gads',          text: background },
        ],
        verdict,
    };
}

// ═════════════════════════════════════════════════════════════════════════════
// 2. 🛋️ PSIHOTERAPEITS (sistēmiskais un KBT skats)
// ═════════════════════════════════════════════════════════════════════════════

// Piesaiste × Gotmana paterns — PILNA 4×4 krusta tabula (visi 16 pāri; atslēga "piesaiste:jātnieks").
const ATTACH_HORSEMAN_SYNTH = {
    'secure:criticism':          'Drošā bāze šo paternu padara vadāmu: kritika parādās tikai zem izteiktas slodzes un reti pāraug personas nosodījumā. Antidots (maigais sākums) šeit nostrādā gandrīz uzreiz, jo abpusējā labā griba nav jāatjauno — tā ir.',
    'secure:contempt':           'Neparasts pāris: droša piesaiste ar nicinājuma paternu parasti norāda nevis uz piesaistes deficītu, bet uz ilgi nekomunicētu aizvainojumu vai vērtību plaisu. Jautājums "kas gadiem nav pateikts skaļi?" šeit dod vairāk nekā komunikācijas tehnikas.',
    'secure:defensiveness':      'Aizsardzība šeit ir ieradums, ne izdzīvošanas stratēģija: drošā bāze ļauj paternu pamanīt un apturēt jau strīda vidū. Pietiek ar norunātu signālu ("mēs abi tagad aizsargājamies") — un saruna atgriežas pie satura.',
    'secure:stonewalling':       'Drošā piesaiste mūra celšanu padara par tehnisku, ne eksistenciālu problēmu: pauze tiešām ir pauze, ne sods. Vienīgais nosacījums — pauzi pieteikt un nosaukt atgriešanās laiku, citādi partneris mieru sāk lasīt kā atsalšanu.',
    'anxious:criticism':         'Trauksmainā piesaiste baro kritikas paternu: bailes zaudēt saikni pārvēršas uzbrukumā partnera raksturam — "tu nekad" patiesībā nozīmē "man ir bail, ka tevis man nav". Kamēr šī tulkošana nenotiek apzināti, partneris dzird tikai apsūdzību.',
    'anxious:contempt':          'Iekšēji pretrunīgs pāris: piesaistes sistēma alkst tuvuma, bet konfliktā ieslēdzas tieši tas paterns, kas tuvumu grauj visātrāk. Aiz sarkasma šeit parasti stāv uzkrāts aizvainojums "es ieguldu vairāk, nekā saņemu" — un tas risināms kā bilances, ne kā toņa jautājums.',
    'anxious:defensiveness':     'Trauksmainā piesaiste un aizsardzības paterns veido apburto loku: jebkura partnera piezīme tiek nolasīta kā atgrūšanas signāls, un atbilde ir pretsūdzība. Konflikts eskalē nevis satura, bet drošības sajūtas dēļ — tāpēc pirmais darbs ir nomierināt piesaistes sistēmu, ne uzvarēt strīdā.',
    'anxious:stonewalling':      'Klusums šeit ir nevis pašaizsardzība, bet vēstījums: "skaties, kā ir, kad manis nav". Pasīvi agresīvā distance no partnera prasa mierinājumu, kuru pati padara neiespējamu — pirmais solis ir vajadzību pateikt vārdos, pirms iestājas klusēšana.',
    'avoidant:criticism':        'Izvairīgā piesaiste ar kritikas paternu veido "cietoksni ar artilēriju": pats tuvumu nepieprasa, bet partnera nepilnības fiksē precīzi. Kritika šeit kalpo distances uzturēšanai — kamēr partneris ir "nepareizs", tuvums nav jāriskē.',
    'avoidant:contempt':         'Distance plus devalvācija veido "ledus sienu": partnerim praktiski nav piekļuves, un attiecības var gadiem stāvēt formālas sadarbības režīmā. Atkusnis sākas nevis ar lielām sarunām, bet ar ievainojamības mikrodevām — vienu godīgu teikumu dienā.',
    'avoidant:defensiveness':    'Katrs pārmetums tiek atvairīts, pirms tas sasniedz saturu — un attiecību saruna beidzas, īsti nesākusies. Gotmana "5% piekrišanas" tehnika šeit ir atslēgas instruments: daļēja atzīšana nesagrauj autonomiju, bet atver dialogu.',
    'avoidant:stonewalling':     'Piesaistes stils un konflikta paterns šeit viens otru pastiprina: distance ir vienlaikus drošības stratēģija un strīda ierocis. Partnerim tas izskatās pēc vienaldzības, lai gan iekšēji notiek fizioloģiska pārslodze — tāpēc pieteikta pauze ("atgriezīšos pēc pusstundas") ir vienīgais izejas ceļš, kas nerada atsvešināšanos.',
    'disorganized:criticism':    'Kritika šeit nav stils, bet trauksmes sprādziens: uzbrukumam parasti seko vainas izjūta un strauja pievilkšanās — partnerim tas izskatās pēc emocionāliem kalniņiem. Stabilizē nevis strīda tehnika, bet paredzamības rituāli ārpus konflikta.',
    'disorganized:contempt':     'Haotiskās piesaistes un nicinājuma kombinācija ir klīniski smagākā: tuvums vienlaikus tiek alkots un devalvēts. Attiecību darbs šeit reti izdodas bez trešās puses (terapeita), jo paterns aktivizējas ātrāk, nekā pāris to pamana.',
    'disorganized:defensiveness':'Partnera vārdi tiek dzirdēti caur draudu filtru: pat neitrāla piezīme aktivizē aizsardzību, jo piesaistes sistēmai tuvais cilvēks vienlaikus ir drošības un briesmu avots. Deeskalācijai vajadzīga lēna, paredzama komunikācija un mierā norunāti strīda "noteikumi".',
    'disorganized:stonewalling': 'Klusums šeit mēdz būt nevis stratēģija, bet disociācija — sistēma atslēdzas no pārslodzes. Argumenti šajā brīdī nesasniedz; atgriešanos dod ķermeniska nomierināšanās (elpa, kustība, laiks), un tikai pēc tam saruna.',
};

// Aizsardzības mehānisma atvasināšanas noteikumi (pirmais sakritušais uzvar).
// Ieejas: piesaistes atslēga, jātnieka atslēga, Kānemana dominante, top enkura
// atslēga, D (ambīcija), V (jutīgums), psihosomatikas skors.
function deriveDefenseMechanism({ aKey, hKey, kDom, anchorKey, D, V, psySom }) {
    if (aKey === 'avoidant' && hKey === 'stonewalling') return {
        name: 'Emocionālā izstāšanās (distancēšanās)',
        how: 'No emocionālām sāpēm sistēma sargājas ar atvienošanos: klusums, "viss kārtībā", fiziska aiziešana. Ārēji izskatās pēc miera, iekšēji ir pārslodze — jūtas netiek pārdzīvotas, tās tiek atliktas.',
        work: 'Mācīties pieteikt pauzi vārdos, nevis ar pazušanu, un atgriezties pie tēmas noteiktā laikā — atlikšana bez atgriešanās kļūst par hronisku izolāciju.',
    };
    if (aKey === 'avoidant' && anchorKey === 'autonomy') return {
        name: 'Pretatkarība (pašpietiekamības cietoksnis)',
        how: 'Aizsardzība pret ievainojamību ir uzbūvēta kā dzīvesveids: "man nevienu nevajag" nav secinājums, bet mūris. Palīdzības lūgšana tiek piedzīvota kā pazemojums, tāpēc grūtības tiek slēptas līdz brīdim, kad tās vairs nav noslēpjamas.',
        work: 'Trenēt "mazos lūgumus" — apzināti lūgt sīku palīdzību situācijās, kur tā objektīvi nav vajadzīga: sistēma mācās, ka atkarības mikrodeva nav kontroles zaudēšana.',
    };
    if (aKey === 'avoidant' && kDom === 's2') return {
        name: 'Intelektualizācija',
        how: 'Emocionāli smagais tiek pārvērsts analīzē: jūtu vietā parādās shēmas, argumenti un "objektīvs izvērtējums". Tas ļauj funkcionēt krīzē, bet emocija paliek neapstrādāta un uzkrājas fonā.',
        work: 'Treniņš nosaukt emocionālo stāvokli vienā teikumā pirms analīzes ("es esmu dusmīgs / man ir bail") — analīze drīkst sekot, ne aizstāt.',
    };
    if (aKey === 'avoidant' && kDom === 's1') return {
        name: 'Novēršanās darbībā (distrakcija)',
        how: 'No smagām emocijām sistēma aizbēg uz priekšu — jauna aktivitāte, kustība, tūlītējs darāmais. Ātrā galva vienmēr atrod, ar ko aizpildīt telpu, kurā citādi būtu jājūt; ārēji tas izskatās pēc enerģijas, ne pēc bēgšanas.',
        work: 'Iebūvēt "tukšuma logus" — īsus brīžus bez stimuliem pēc emocionāli grūtām situācijām; ja pirmajās minūtēs parādās nemiers, tas ir signāls, ka novēršanās tikko strādāja.',
    };
    if (aKey === 'anxious' && anchorKey === 'service') return {
        name: 'Kompulsīvā aprūpe (bēgšana citu vajadzībās)',
        how: 'Pašam sāpīgais tiek klusināts, risinot citu problēmas: kamēr esmu vajadzīgs, mani nepametīs. Rūpes šeit ir īstas, bet tām ir dubultfunkcija — tās uztur arī paša piesaistes drošību, tāpēc atteikt ir gandrīz neiespējami.',
        work: 'Pirms katras "jā" atbildes pārbaudīt motīvu: es palīdzu, jo varu — vai tāpēc, ka baidos nebūt vajadzīgs? Otrajā gadījumā vismaz reizi apzināti atteikt un izturēt diskomfortu.',
    };
    if ((anchorKey === 'challenge' || anchorKey === 'management' || anchorKey === 'technical') && Number.isFinite(D) && D >= 65) return {
        name: 'Bēgšana darbā (darbs kā anestēzija)',
        how: 'Iekšējā spriedze tiek pārvērsta darba apjomā: jo smagāk emocionāli, jo vairāk projektu. Sniegums īstermiņā pat aug, tāpēc apkārtne mehānismu nevis pamana, bet apbalvo — un tas padara to noturīgu.',
        work: 'Robežas rituāls dienas beigās un godīgs jautājums pie katra jauna projekta: "es to gribu — vai es no kaut kā bēgu?" Darba apjoms nav mērāms ar izvairīšanās kvalitāti.',
    };
    if (aKey === 'anxious' && hKey === 'defensiveness') return {
        name: 'Projekcija un vainas novirzīšana',
        how: 'Pašam nepieņemamais (bailes, ka esmu par maz) tiek piedēvēts partnerim vai videi: "tu mani nenovērtē, tu mani atstāsi". Aizsardzība nostrādā ātrāk par apzināšanos, tāpēc pati persona to piedzīvo kā patiesību, ne kā mehānismu.',
        work: 'KBT domu pieraksts brīžos, kad parādās "tu vienmēr / tu nekad" — pārbaudīt, kurš teikuma īpašnieks patiesībā ir "es".',
    };
    if (aKey === 'anxious') return {
        name: 'Apstiprinājuma meklēšana un pieglaimošanās (fawning)',
        how: 'Drošības sajūta tiek nomāta ar nepārtrauktu ārējā apstiprinājuma vākšanu: pārjautāšana, pielāgošanās, savas vajadzības atlikšana. Īstermiņā tas nomierina, ilgtermiņā baro pašvērtības atkarību no citu reakcijām.',
        work: 'Sistemātiski mazi "nē" treniņi drošās situācijās un pašapstiprinājuma prakse pirms citu vērtējuma saņemšanas.',
    };
    if (anchorKey === 'entrepreneurial' && kDom === 's1') return {
        name: 'Bēgšana jaunos sākumos (hipomāniskā aizsardzība)',
        how: 'Pret smagumu sistēma sargājas ar jaunu projektu: kamēr kaut kas sākas, nekas nav jāpabeidz un nekas nav jāsēro. Entuziasms ir īsts, bet tā ritms ir aizdomīgs — jauns starts parādās tieši tad, kad vecajā kļūst emocionāli grūti.',
        work: 'Noteikums "pabeigt vai apzināti apglabāt" pirms nākamā starta: katram pamestam projektam viena godīga rindkopa, kāpēc tas tika pamests — sēras nevar pārlēkt, tās var tikai atlikt.',
    };
    if (anchorKey === 'security' && kDom === 's2') return {
        name: 'Kontrole un rituāli',
        how: 'Trauksme tiek savaldīta ar kārtību: plāni, saraksti, dubultpārbaudes, "viss zem kontroles". Struktūra ir reāls stiprums, bet tā kļūst par aizsardzību brīdī, kad novirze no plāna izraisa nesamērīgu spriedzi.',
        work: 'Dozēts nekontrolētais — regulāri mazi eksperimenti bez plāna B; mērķis nav atteikties no struktūras, bet pierādīt sistēmai, ka arī bez tās nekas nesabrūk.',
    };
    if (hKey === 'contempt') return {
        name: 'Devalvācija (pārākuma pozīcija)',
        how: 'Pret ievainojamību sistēma sargājas, noniecinot avotu: ja otrs ir "muļķis" vai "sīkumains", viņa vārdi nesāp. Cena ir augsta — nicinājums ir spēcīgākais attiecību korozijas faktors Gotmana datos.',
        work: 'Apzināta "cieņas inventarizācija": regulāri fiksēt, ko otrā cilvēkā patiesi ciena — devalvācijas refleksam vajag pretsvaru, kas ir gatavs pirms konflikta.',
    };
    if (aKey === 'disorganized' && Number.isFinite(V) && V >= 70) return {
        name: 'Regresija',
        how: 'Zem izteiktas slodzes sistēma atkāpjas uz agrīnāku funkcionēšanas līmeni: pieaugušā spējas (plānot, runāt, izturēt neskaidrību) uz brīdi izslēdzas, un paliek bezpalīdzība vai impulsīva reakcija. Pēc epizodes pašam par to ir kauns — un kauns paternu tikai nostiprina.',
        work: 'Iepriekš sagatavots "krīzes minimums" (viena persona, kam zvanīt; viena darbība, ko darīt) — regresijas brīdī izvēles vairs nestrādā, strādā tikai iepriekš ieliktas sliedes. Kauna vietā — fakta konstatācija: sistēma pārslogojās.',
    };
    if (aKey === 'disorganized') return {
        name: 'Šķelšana (splitting)',
        how: 'Iekšējā pretruna (tuvums = vajadzība un draudi vienlaikus) tiek risināta, sadalot pasauli galējībās: cilvēki un situācijas ir vai nu ideāli, vai bīstami — un šis vērtējums var mainīties strauji.',
        work: 'Stabilizējošs rāmis: paredzami rituāli attiecībās un prakse noturēt "gan-gan" formulējumus ("viņš mani sadusmoja UN viņš man ir svarīgs") — vēlams ar terapeita atbalstu.',
    };
    if (Number.isFinite(V) && V >= 65) return (Number.isFinite(psySom) && psySom >= 60) ? {
        name: 'Somatizācija',
        how: 'Emocionālā slodze, kas netiek izteikta vārdos, pāriet ķermenī: spriedzes brīžos parādās fiziski simptomi, kamēr "psiholoģiski viss kārtībā". Ķermenis kļūst par vienīgo kanālu, kuram atļauts sūdzēties.',
        work: 'Regulāra ķermeņa stāvokļa skenēšana un emociju verbalizācija pirms simptomu mistifikācijas — fiziskajam signālam vienmēr jautāt "par ko tu runā?".',
    } : {
        name: 'Ruminācija (trauksmainā pārdomāšana)',
        how: 'No nenoteiktības sāpēm sistēma sargājas ar bezgalīgu scenāriju malšanu: šķietami "risinu problēmu", faktiski uzturu trauksmi. Domāšana šeit nav instruments, bet izvairīšanās no jušanas.',
        work: 'Norobežots "raižu laiks" (15 min dienā ar pierakstu) un ārpus tā — uzmanības pārslēgšanas prakse; rumināciju aptur struktūra, ne gribasspēks.',
    };
    if (aKey === 'secure') return {
        name: 'Nobriedušās aizsardzības (sublimācija, humors)',
        how: 'Spriedze pārsvarā tiek novadīta konstruktīvi — darbībā, humorā, tiešā sarunā. Tas ir psiholoģiski veselīgākais aizsardzības spektrs; regresija uz primitīvākiem mehānismiem gaidāma tikai izteiktā pārslodzē.',
        work: 'Uzturēt to, kas jau strādā, un sekot līdzi pārslodzes brīžiem — arī nobriedusi sistēma zem hroniska stresa atkāpjas uz vienkāršākiem mehānismiem.',
    };
    return {
        name: 'Racionalizācija',
        how: 'Nepatīkamajam tiek atrasts loģisks, pieņemams izskaidrojums ("man to nemaz nevajadzēja") — pašvērtība tiek pasargāta, bet mācība no situācijas netiek paņemta.',
        work: 'Pēcanalīzes paradums: pie katra "tas nebija svarīgi" pārjautāt, kas tieši sāpētu, ja tas TOMĒR bija svarīgi.',
    };
}

function buildTherapist(profile) {
    const rd = profile?.relationshipDynamics || null;
    const vP = profile?.vedic?.psychology || {};
    const wp = profile?.western?.psychology || {};
    const ca = profile?.careerAnchors || null;

    const att  = rd?.attachment?.primary || null;
    const att2 = rd?.attachment?.allScores?.[1] || null;
    const h1   = rd?.horsemen?.top?.[0] || null;
    const h2   = rd?.horsemen?.top?.[1] || null;

    // ── 2.1. Piesaistes stils un ilgtermiņa partnerība ───────────────────────
    let attachment = null;
    if (att) {
        const parts = [];
        parts.push(`Piesaistes sistēmas dominante ir <b>${att.lv}</b> (${att.score}%). ${dot(att.operatingMode || att.summary || '')}`);
        if (att2 && (att.score - att2.score) <= 8) {
            parts.push(`Būtiska nianse: gandrīz tikpat izteikta ir <b>${lc(att2.lv)}</b> (${att2.score}%) — reālā uzvedība svārstīsies starp abiem režīmiem atkarībā no partnera uzvedības un drošības līmeņa attiecībās, tāpēc cilvēks pats sev var šķist pretrunīgs.`);
        }
        const longTerm = {
            secure: 'Ilgtermiņa prognoze ir laba: konfliktus šis profils pārsvarā izmanto kā informāciju, ne kā ieroci, un spēj atjaunot tuvību pēc plaisām.',
            anxious: 'Ilgtermiņā jārēķinās ar protesta uzvedību brīžos, kad partneris kļūst mazāk pieejams: biežāki zvani, pārbaudīšana, aizvainojuma demonstrēšana. Tas nav kaprīzes, bet piesaistes trauksmes simptoms — un partnerim tas jāzina, lai nereaģētu ar distanci, kas trauksmi tikai pastiprina.',
            avoidant: 'Ilgtermiņā tipisks "pieprasīšanas–atkāpšanās" cikls: jo vairāk partneris prasa tuvību, jo tālāk šis profils atkāpjas. Attiecības noturas, ja partneris tuvību piedāvā, nevis pieprasa, un ja distances brīži tiek norunāti kā leģitīmi.',
            disorganized: 'Ilgtermiņa partnerībā jārēķinās ar svārstībām starp pievilkšanos un atgrūšanu, kas partnerim ir grūti prognozējamas. Stabilitāti dod ārkārtīgi paredzams partneris un, godīgi sakot, terapeitisks atbalsts — paša spēkiem šis paterns pārrakstās lēni.',
        }[att.key];
        if (longTerm) parts.push(longTerm);
        if (att.partnerNeeds) parts.push(`Ģimenes dinamikā šim cilvēkam nepieciešams: ${lc(dot(att.partnerNeeds))}`);
        attachment = parts.join(' ');
    }

    // ── 2.2. Destruktīvākais konflikta paterns (Gotmans) ─────────────────────
    let conflict = null;
    if (h1) {
        const parts = [];
        parts.push(`Destruktīvākais uzvedības modelis konfliktā ir <b>${h1.lv}</b> (${h1.score}%): ${lc(dot(h1.pattern || ''))}`);
        if (h2 && h2.score >= 55 && (h1.score - h2.score) <= 10) {
            parts.push(`Eskalācijas otrajā fāzē pieslēdzas arī <b>${lc(h2.lv)}</b> (${h2.score}%) — strīda beigās partneris bieži atbild tieši uz šo otro paternu, nevis uz sākotnējo tēmu.`);
        }
        if (h1.antidote) parts.push(`<b>Klīniskais antidots:</b> ${lc(dot(h1.antidote))}`);
        const synth = att ? ATTACH_HORSEMAN_SYNTH[`${att.key}:${h1.key}`] : null;
        if (synth) parts.push(synth);
        conflict = parts.join(' ');
    }

    // ── 2.3. Dziļie iekšējie konflikti (Ketu slazds · Lilita · pretrunas) ────
    let innerWork = null;
    {
        const parts = [];
        if (wp.innerConflicts) parts.push(`Apspiesto pretrunu kodols: ${lc(dot(wp.innerConflicts))}`);
        if (vP.ketuTrap) parts.push(`Zemapziņas komforta slazds (Ketu): ${lc(dot(vP.ketuTrap))} Tā ir "vecā programma", kurā cilvēks atkāpjas zem stresa — pazīstama, bet izaugsmi bloķējoša.`);
        if (wp.blackmailPoints) parts.push(`Lilitas zona — pirmatnējais, kas netiek rādīts pat sev: ${lc(dot(wp.blackmailPoints))} Šī zona parasti izpaužas netieši (kauns, pēkšņas "nepamatotas" dusmas, tabu tēmas), un tieši tāpēc tā konfliktos strādā kā neredzams detonators.`);
        if (wp.trauma) parts.push(`Fona ievainojums: ${lc(dot(wp.trauma))}`);
        if (parts.length) {
            parts.push('Terapeitiski svarīgi: šīs zonas nav "jāizlabo" — tās ir jāatpazīst brīdī, kad tās pārņem vadību, jo tad izvēle atgriežas pie apzinātās daļas.');
            innerWork = parts.join(' ');
        }
    }

    // ── 2.4. Primārais emocionālās aizsardzības mehānisms (atvasināts) ───────
    let defense = null;
    let defenseName = null;
    if (att || h1) {
        const D = traitPct(profile, 'ambition');
        const V = traitPct(profile, 'neuroticism');
        const psySom = num(profile?.psychosomaticAudit?.psychosomMeter?.psychosomScore);
        const mech = deriveDefenseMechanism({
            aKey: att?.key || null, hKey: h1?.key || null,
            kDom: ca?.kahneman?.dominant || null,
            anchorKey: ca?.primary?.key || null,
            D, V, psySom,
        });
        defenseName = mech.name;
        defense = `Primārais aizsardzības mehānisms — <b>${mech.name}</b> (atvasināts no piesaistes stila, konflikta paterna un kognitīvā profila kombinācijas). ${mech.how} <b>Darba virziens:</b> ${lc(mech.work)}`;
    }

    let verdict = null;
    if (att && h1) {
        verdict = `Terapeitiskais darba lauks: <b>${lc(att.lv)}</b> kā bāzes režīms, <b>${lc(h1.lv)}</b> kā krīzes paterns${defenseName ? ` un <b>${lc(defenseName)}</b> kā ikdienas aizsardzība` : ''}. Neviens no šiem nav spriedums — tie ir treniņa punkti, un visi trīs reaģē uz apzinātu praksi.`;
    }

    return {
        key: 'therapist', icon: '🛋️', color: '#be185d',
        title: 'Psihoterapeits',
        role: 'Sistēmiskais un KBT skats · piesaiste · konfliktu paterni · aizsardzības',
        sections: [
            { label: 'Piesaistes stils attiecībās',       sub: 'Boulbija sistēma un ģimenes dinamika',   text: attachment },
            { label: 'Destruktīvais konflikta paterns',   sub: 'Gotmana jātnieki un antidots',           text: conflict },
            { label: 'Dziļie iekšējie konflikti',         sub: 'Ketu slazds, Lilita un apspiestās pretrunas', text: innerWork },
            { label: 'Primārais aizsardzības mehānisms',  sub: 'Kā psihe sargājas no sāpēm',             text: defense },
        ],
        verdict,
    };
}

// ═════════════════════════════════════════════════════════════════════════════
// 3. 🩺 PSIHIATRS (medicīniskais pārraugs)
// ═════════════════════════════════════════════════════════════════════════════

function buildPsychiatrist(profile) {
    const pa = profile?.psychosomaticAudit || null;
    const ca = profile?.careerAnchors || null;
    const rhythm = profile?.existentialAudit?.mezo?.primary || null;
    const micro  = profile?.existentialAudit?.micro || null;

    const V     = traitPct(profile, 'neuroticism');
    const fight = traitPct(profile, 'fightresponse');
    const psySom = num(pa?.psychosomMeter?.psychosomScore);
    const boundaryRisk = ca?.boundary ? Math.max(0, Math.min(100, 100 - num(ca.boundary.score))) : NaN;

    // ── 3.1. Nervu sistēmas bāze (noturība + akūtā/hroniskā ass) ─────────────
    let resilience = null;
    if (Number.isFinite(V) || Number.isFinite(fight) || Number.isFinite(psySom)) {
        const parts = [];
        if (Number.isFinite(V)) {
            const band = V <= 25 ? ['ļoti augsta', 'ilgstoša psiholoģiskā slodze šo sistēmu deformē lēni — profils iztur to, kas citus jau būtu apturējis. Klīniskais risks šeit ir pretējs: signāli par pārslodzi var neparādīties, kamēr rezerve jau ir tukša']
                : V <= 45 ? ['augsta', 'ilgstošu slodzi sistēma nes stabili, ja ir saprātīgi atjaunošanās logi — dekompensācija gaidāma tikai pie ilgas, nekontrolētas pārslodzes']
                : V <= 60 ? ['mērena', 'ilgstoša slodze ir izturama, bet ne bezmaksas — pēc intensīviem periodiem vajadzīgi apzināti atjaunošanās posmi, citādi jutība pakāpeniski aug']
                : V <= 78 ? ['pazemināta — profils ir jutīgs', 'ilgstoša spriedze šai sistēmai ir dārgāka nekā vidēji: trauksmes fons, miega kvalitāte un emocionālās svārstības ir pirmie rādītāji, kas jāmonitorē']
                : ['zema — profils ir izteikti jutīgs', 'ilgstoša psiholoģiskā slodze bez aizsardzības pasākumiem šeit ar augstu varbūtību noved līdz klīniskam izsīkumam — psiholoģiskā drošība nav komforts, bet darba nespējas profilakse'];
            parts.push(`Nervu sistēmas bāzes noturība ir <b>${band[0]}</b> (jutīguma ass ${Math.round(V)}%): ${band[1]}.`);
        }
        if (Number.isFinite(fight)) {
            parts.push(fight > 60
                ? `Akūtā stresā sistēma <b>mobilizējas</b> (cīņas reakcija ${Math.round(fight)}%) — īsa krīze šo cilvēku drīzāk saliek, nevis izsit.`
                : fight < 40
                ? `Akūtā stresā sistēma sliecas <b>sastingt vai izvairīties</b> (cīņas reakcija ${Math.round(fight)}%) — pirmajās krīzes minūtēs nevajag gaidīt lēmumus; vajag protokolu, kas nostrādā automātiski.`
                : `Akūtā stresa reakcija ir mērena (${Math.round(fight)}%) — ne panika, ne supermobilizācija; izšķirošais ir konteksts un sagatavotība.`);
        }
        if (Number.isFinite(fight) && Number.isFinite(V)) {
            if (fight > 60 && V >= 60) parts.push('Kopā tas veido <b>sprintera fizioloģiju</b>: trieciens tiek izturēts labi, bet hroniska, nebeidzama spriedze šo pašu sistēmu izdedzina — bīstama ir nevis krīze, bet krīzes režīma normalizēšana.');
            else if (fight < 40 && V <= 45) parts.push('Kopā tas veido <b>maratonista fizioloģiju</b>: pirmā reakcija uz triecienu ir vāja, bet ilgstošā distancē sistēma ir noturīga — akūtos brīžos vajag ārēju vadību, ilgtermiņā šis cilvēks tur pats.');
            else if (fight < 40 && V >= 60) parts.push('Kopā tas ir <b>divkārši jutīgs kontūrs</b> — gan akūtais trieciens, gan hroniskā slodze maksā dārgi. Vides izvēle (paredzamība, zems konfliktu fons) šeit ir galvenais medicīniskais instruments.');
            else if (fight > 60 && V <= 45) parts.push('Kopā tas ir <b>krīžu izturības kontūrs</b> — mobilizējas triecienā un nesabrūk distancē. Šāda konfigurācija pati par sevi meklē pārslodzi; robežas jānosaka ar galvu, ne ar sajūtām, jo sajūtas brīdinās par vēlu.');
            else parts.push('Akūtā un hroniskā ass neveido galējību pāri — reakcija uz spiedienu šeit ir kontekstatkarīga: izšķir sagatavotība, miegs un vides paredzamība, ne temperaments. Tas nozīmē, ka slodzes panesamību var reāli vadīt ar režīmu — rets gadījums, kad higiēna dod vairāk nekā raksturs.');
        }
        if (Number.isFinite(psySom)) parts.push(`Somatizācijas tendence: ${Math.round(psySom)}% — ${psySom >= 70 ? 'augsta; ķermenis ir skaļa agrīnās brīdināšanas sistēma, un tā jāklausās' : psySom <= 30 ? 'zema; ķermenis klusē arī tad, kad slodze jau ir kritiska — tāpēc jāmonitorē ar galvu (miegs, kļūdu biežums, cinisma līmenis), ne ar pašsajūtu' : 'mērena; daļa slodzes parādīsies ķermenī, daļa uzkrāsies klusi'}.`);
        resilience = parts.join(' ');
    }

    // ── 3.2. Ķermeņa "check-engine" signāli ─────────────────────────────────
    let soma = null;
    if (pa) {
        const parts = [];
        const acute = pa.acute?.signals || [];
        const chron = pa.chronic?.patterns || [];
        if (acute.length) {
            parts.push(`<b>Akūtā zona (pirmie signāli):</b> ${acute.slice(0, 2).map(s => lc(dot(s.soma || ''))).join(' ')}`);
        }
        if (chron.length) {
            parts.push(`<b>Hroniskā zona (ja stress netiek apstrādāts ilgstoši):</b> ${chron.slice(0, 2).map(s => lc(dot(s.soma || ''))).join(' ')}`);
        }
        if (!acute.length && !chron.length) {
            parts.push('Izteiktu somatisko marķieru kartē nav — neapstrādāts stress šim profilam biežāk izpaudīsies psihiski (miegs, koncentrēšanās, garastāvoklis), ne ķermeniski. Tas nav drošības sertifikāts: tas nozīmē, ka "check-engine" lampiņa deg klusāk, un jāskatās apzināti.');
        }
        if (pa.acute?.hironWound) parts.push(`Fona ievainojums (Hirons): ${lc(dot(pa.acute.hironWound))}`);
        if (pa.psychosomMeter?.narrative) parts.push(dot(pa.psychosomMeter.narrative));
        soma = parts.join(' ');
    }

    // ── 3.3. Izdegšanas garantija (vājākais enkurs + ritma laušana + elementi) ─
    let burnout = null;
    if (ca?.boundary || rhythm?.burnoutTrigger || pa?.compensation) {
        const parts = [];
        if (ca?.boundary?.burnoutTrigger) {
            parts.push(`Klīniski drošākā izdegšanas recepte šim profilam: ielikt viņu ${lc(dot(ca.boundary.burnoutTrigger))} (vājākais Šeina enkurs — ${lc(ca.boundary.lv || '')}, ${ca.boundary.score}%).`);
        }
        if (rhythm?.burnoutTrigger) parts.push(`Otrs garantēts ceļš — bioritma laušana: ${lc(dot(rhythm.burnoutTrigger))}`);
        const excess = pa?.compensation?.excessElements?.[0] || null;
        const defic  = pa?.compensation?.deficiencyElements?.[0] || null;
        const exEl = excess ? plainEl(excess.element) : null;
        const dfEl = defic ? plainEl(defic.element) : null;
        if (excess) parts.push(`Elementu disbalanss rāda, KĀ izdegšana izskatīsies: pārsvarā ir <b>${exEl}</b> (${excess.pct}%) — ${lc(dot(excess.excess || ''))}`);
        if (defic) parts.push(`Trūkstošais resurss ir <b>${dfEl}</b> (tikai ${defic.pct}%): ${lc(dot(defic.deficiency || ''))}`);
        // Pārsvara–iztrūkuma PĀRA lasījums pēc kontroles cikla (Koks→Zeme→Ūdens→Uguns→Metāls→Koks):
        // trūkst kontrolētāja = nav bremzes; pārsvars nomāc trūkstošo = pašpastiprinošs loks.
        if (exEl && dfEl) {
            const ELEM_CONTROLS = { Koks: 'Zeme', Zeme: 'Ūdens', 'Ūdens': 'Uguns', Uguns: 'Metāls', 'Metāls': 'Koks' };
            if (ELEM_CONTROLS[dfEl] === exEl) {
                parts.push(`Šis pāris ir īpaši nelabvēlīgs: ciklā tieši ${dfEl} ierobežo ${exEl} — trūkstot šai dabiskajai bremzei, pārsvars eskalē klusi un bez iekšēja pretspēka. Ārējais rāmis (cilvēks vai kalendārs, kas aptur) šeit aizstāj iztrūkstošo iekšējo.`);
            } else if (ELEM_CONTROLS[exEl] === dfEl) {
                parts.push(`Disbalanss ir pašpastiprinošs: ${exEl} pārsvars ciklā tieši nomāc jau tā trūkstošo ${dfEl} — jo dziļāk stresā, jo mazāk pieejams tieši tas resurss, kas stresu apturētu. Tāpēc ${dfEl} uzpilde jāieplāno mierīgajos periodos, nevis jāmeklē krīzē.`);
            } else {
                parts.push(`Pārsvara–iztrūkuma pāris (${exEl} ↔ ${dfEl}) nozīmē: atjaunošanās nav "atpūta vispār", bet virziena maiņa — apzināti mazāk ${exEl} tipa aktivitāšu un vairāk ${dfEl} uzpildes.`);
            }
        }
        // Bioritma × vājākā enkura konverģence — vai abi rāda vienu "nāves zonu".
        const corr = burnoutCorridor(profile);
        if (corr?.kind === 'same-monotony') parts.push('Divi neatkarīgi mērījumi (bioritms un vājākais enkurs) saskan par vienu nāves zonu: <b>vienmuļība</b>. Šim profilam bīstamākā nav pārslodze, bet ilgstoši nemainīga, izaicinājumu tukša vide — izdegšana nāks caur izsīkumu, ne caur sabrukumu.');
        else if (corr?.kind === 'same-chaos') parts.push('Divi neatkarīgi mērījumi (bioritms un vājākais enkurs) saskan par vienu nāves zonu: <b>haoss un nepārtraukta neparedzamība</b>. Bīstama ir nevis darba masa, bet fona nestabilitāte — izdegšanas profilakse šeit nozīmē paredzamības salas kalendārā.');
        else if (corr?.kind === 'corridor') parts.push('Mērījumi iezīmē šauru drošo koridoru: viens rāda, ka dedzina rutīna, otrs — ka dedzina haoss. Optimālā josla ir <b>strukturēta variācija</b> — stabils karkass ar regulāri mainīgu saturu; abas galējības šim profilam ir riska zonas.');
        if (parts.length) burnout = parts.join(' ');
    }

    // ── 3.4. Atjaunošanās protokols ("recepte") ──────────────────────────────
    let recovery = null;
    if (pa?.compensation?.primary || micro?.restart || rhythm?.recovery) {
        const parts = [];
        const prim = pa?.compensation?.primary || null;
        if (prim?.compensation) parts.push(`<b>Primārā recepte (${plainEl(prim.element) || 'elementu balanss'}):</b> ${lc(dot(prim.compensation))}`);
        const defic = pa?.compensation?.deficiencyElements?.[0] || null;
        if (defic?.compensation && plainEl(defic.element) !== plainEl(prim?.element)) {
            parts.push(`<b>Trūkstošā elementa (${plainEl(defic.element)}) uzpilde:</b> ${lc(dot(defic.compensation))}`);
        }
        if (micro?.restart) parts.push(`<b>Ikdienas restarts:</b> ${lc(dot(micro.restart))}`);
        if (rhythm?.recovery) parts.push(`<b>Cikla līmenī:</b> ${lc(dot(rhythm.recovery))}`);
        parts.push('Protokols nav "labsajūtas ieteikums" — tas ir apgrieztais izdegšanas mehānisms: katrs punkts kompensē konkrētu šī profila noplūdes kanālu.');
        recovery = parts.join(' ');
    }

    // ── Slēdziens: kopējais risku līmenis no pieejamajiem mērījumiem ─────────
    let verdict = null;
    {
        const inputs = [V, boundaryRisk, psySom >= 70 ? psySom : NaN].filter(Number.isFinite);
        if (inputs.length) {
            const mean = inputs.reduce((a, b) => a + b, 0) / inputs.length;
            verdict = mean >= 65
                ? 'Medicīniskā pārrauga slēdziens: profils strādā ar <b>šauru drošības rezervi</b> — atjaunošanās protokols šeit nav ieteikums, bet nepieciešamība, un slodzes plānošanai jānotiek pirms simptomiem, ne pēc tiem.'
                : mean >= 45
                ? 'Medicīniskā pārrauga slēdziens: rezerve ir <b>vidēja</b> — sistēma tur ikdienas slodzi, bet izdegšanas trigera zonās (skat. augstāk) drošības rezerve strauji sarūk. Profilaktiskais režīms ir pilnīgi pietiekams, ja to ievēro.'
                : 'Medicīniskā pārrauga slēdziens: nervu sistēmas rezerve ir <b>laba</b> — akūtu iejaukšanos nekas neprasa. Galvenais risks šādam profilam ir tieši izturība: signāli nāk vēlu, tāpēc robežas jāliek pēc kalendāra, ne pēc pašsajūtas.';
        }
    }

    return {
        key: 'psychiatrist', icon: '🩺', color: '#b91c1c',
        title: 'Psihiatrs — medicīniskais pārraugs',
        role: 'Stresa tolerance · somatiskie signāli · izdegšanas triggeri · atjaunošanās',
        sections: [
            { label: 'Nervu sistēmas bāze',           sub: 'Noturība un stresa tolerance',            text: resilience },
            { label: 'Ķermeņa "check-engine" signāli', sub: 'Kur somatizējas neapstrādāts stress',     text: soma },
            { label: 'Izdegšanas garantija',           sub: 'Vide, kas šo profilu izdedzinās',         text: burnout },
            { label: 'Nervu sistēmas restarts',        sub: 'Elementārais atjaunošanās protokols',     text: recovery },
        ],
        verdict,
    };
}

// ═════════════════════════════════════════════════════════════════════════════
// 4. 🎭 JUNGA PSIHOANALĪTIĶIS
// ═════════════════════════════════════════════════════════════════════════════

function buildJungian(profile) {
    const vP = profile?.vedic?.psychology || {};
    const wp = profile?.western?.psychology || {};
    const nak = profile?.vedic?.nakshatra || {};
    const dharma = profile?.existentialAudit?.macro?.dharma || null;
    const atmakaraka = String(profile?.existentialAudit?.macro?.atmakarakaPlanet || '').replace(/^Meness$/, 'Mēness') || null;
    const celtic = getCelticTree(profile?.birth_info?.date || '');

    let arudha = null;
    try { arudha = computeArudhaLagna(profile); } catch { arudha = null; }

    // ── 4.1. Persona pret Patību (Lagna ↔ Arudha Lagna) ──────────────────────
    let persona = null;
    if (arudha) {
        const parts = [];
        parts.push(`Iekšējā būtība (Lagna) ir <b>${arudha.lagna}</b>: ${lc(dot(arudha.lagnaSelf || ''))}`);
        parts.push(`Publiskā maska (Arudha Lagna) — <b>${arudha.arudha}</b>: ${lc(dot(arudha.meaning || ''))}`);
        parts.push(dot(arudha.gapText || ''));
        const gapWork = arudha.gap <= 2
            ? 'Junga valodā: Persona šeit ir caurspīdīgs apvalks, ne cietums — individuācijas enerģija var iet dziļumā (Ēnas integrācijā), nevis fasādes remontā.'
            : arudha.gap === 3
            ? 'Junga valodā: Persona daļēji dzīvo savu dzīvi — ir jomas, kurās cilvēks tiek "lasīts nepareizi", un tas prasa enerģiju. Individuācijas darbs sākas ar godīgu jautājumu, kuru no abām versijām viņš pats uzskata par īsto.'
            : 'Junga valodā: Persona un Patība te ir divi dažādi stāsti — maska ir izaugusi par patstāvīgu konstrukciju. Tas dod sociālu aizsardzību, bet ilgtermiņā baro vientulības sajūtu ("mani mīl par to, kas es neesmu") — tieši šī plaisa ir galvenais individuācijas darba lauks.';
        parts.push(gapWork);
        // Ķeltu slāņa krusts: Vilkābele (Iluzionists) ir "dzimusī maskēšanās" zīme —
        // neatkarīgs otrais mērījums tai pašai Personas tēmai (pastiprina vai kontrastē).
        if (celtic?.name === 'Vilkābele') {
            parts.push(arudha.gap >= 4
                ? 'Ķeltu slānis šo mērījumu neatkarīgi pastiprina: Vilkābele ir dzimusī maskēšanās zīme — ārējais apzināti neatspoguļo iekšējo. Divi neatkarīgi slāņi par vienu tēmu nozīmē, ka maska šeit nav nejaušība, bet personības nesošā konstrukcija; to nevar "noraut", to var tikai pakāpeniski padarīt caurspīdīgāku.'
                : 'Interesants kontrasts: ķeltu slānis (Vilkābele — dzimusī maskēšanās zīme) sliecas uz slēpšanos, bet Arudhas mērījums rāda salīdzinoši caurspīdīgu tēlu — maskēšanās šeit ir apgūta prasme, ko cilvēks lieto selektīvi, nevis pastāvīgs stāvoklis.');
        }
        persona = parts.join(' ');
    } else {
        const mask = computeMaskSynthesis(profile);
        if (mask?.applicable) {
            persona = `Precīzs Lagnas/Arudhas aprēķins bez dzimšanas laika nav iespējams, tāpēc Personas slānis šeit dots pa dominējošo stihiju (<b>${mask.topElement}</b>) — sociālā maska veidojas ap šīs stihijas izteiksmi, bet plaisu starp masku un būtību kvantificēt nevar. Šis ir vienīgais konsīlija bloks, kur mērījuma vietā jāpaliek pie kvalitatīva apraksta.`;
        }
    }

    // ── 4.2. Anima/Animus — neapzinātais dvēseles ideāls ─────────────────────
    let anima = null;
    if (vP.emotionalBase || vP.animaProjection || wp.loveLanguage || wp.emotionalNeeds) {
        const parts = [];
        if (vP.emotionalBase) parts.push(`Iekšējā jūtu pasaule (Mēness arhetips): ${lc(dot(vP.emotionalBase))}`);
        if (vP.animaProjection) parts.push(`Neapzinātā projekcija — dvēseles ideāls, ko cilvēks meklē (un "ierauga") partnerī: ${lc(dot(vP.animaProjection))} Kamēr projekcija nav apzināta, partneris tiek mīlēts par lomu, ne par sevi — un agri vai vēlu "pieviļ", vienkārši būdams cilvēks.`);
        if (nak.nakshatra) parts.push(`Mēness fona zvaigznājs ir <b>${nak.nakshatra}</b>${nak.lord ? ` (valda ${String(nak.lord).replace(/^Meness$/, 'Mēness')})` : ''} — tas nosaka šīs jūtu pasaules pamattoni.`);
        if (wp.loveLanguage) parts.push(`Emocionālā valoda: ${lc(dot(wp.loveLanguage))}`);
        if (wp.emotionalNeeds) parts.push(`Bāzes emocionālā vajadzība: ${lc(dot(wp.emotionalNeeds))}`);
        anima = parts.join(' ');
    }

    // ── 4.3. Ēnas arhetips un apspiestās ambīcijas ───────────────────────────
    let shadow = null;
    if (vP.hiddenAmbitions || vP.rahuRisk || celtic?.shadow || vP.ketuTalent) {
        const parts = [];
        if (vP.hiddenAmbitions) parts.push(`Ēnas dzinulis (Rahu) — apspiestā, "nepieklājīgā" ambīcija, kas neapzināti vada rīcību: ${lc(dot(vP.hiddenAmbitions))}`);
        if (vP.rahuRisk) parts.push(`Kad Ēna pārņem vadību nepamanīta, risks izskatās šādi: ${lc(dot(vP.rahuRisk))}`);
        if (celtic?.shadow) parts.push(`${celtic.name ? `<b>${celtic.name}</b> koka ēnas puse to papildina` : 'Ķeltu koka ēnas puse'}: ${lc(dot(celtic.shadow))}`);
        if (vP.ketuTalent) parts.push(`Ēnā ir arī ieslēgts resurss (Ketu talants): ${lc(dot(vP.ketuTalent))} Junga tēze šeit strādā burtiski — Ēnā glabājas ne tikai apspiestais negatīvais, bet arī nedzīvotā dzīve; integrācija atbrīvo abus.`);
        shadow = parts.join(' ');
    }

    // ── 4.4. Individuācijas mērķis un dvēseles misija ────────────────────────
    let mission = null;
    if (dharma) {
        const parts = [];
        parts.push(`Individuācijas virsuzdevums ir <b>${dharma.lv}</b>${atmakaraka ? ` — dvēseles ceļvedis (Atmakaraka) ir ${atmakaraka}` : ''}.`);
        if (dharma.mission) parts.push(`Misijas kodols: ${lc(dot(dharma.mission))}`);
        if (dharma.deepSatisfaction) parts.push(`Frankla loģoterapijas valodā — jēgas avots šim cilvēkam nav sasniegumi paši par sevi, bet konkrēts eksistenciāls brīdis: ${lc(dot(dharma.deepSatisfaction))}`);
        if (dharma.emptySignal) parts.push(`Brīdinājuma signāls, ka ceļš ir pazaudēts: ${lc(dot(dharma.emptySignal))}`);
        if (dharma.signature) parts.push(`Paliekošais mantojums, kas dod pilnu eksistenciālu piepildījumu: ${lc(dot(dharma.signature))}`);
        // Dharma × Šeina enkuri — vai dvēseles uzdevums un karjeras dzinējs sakrīt (3 zari).
        const rel = dharmaAnchorRelation(profile);
        if (rel?.status === 'aligned') parts.push(`Reta un vērtīga konfigurācija: dvēseles uzdevums un dominējošais karjeras enkurs (${lc(rel.anchorLv)}) velk vienā virzienā — ikdienas darbs var kļūt par individuācijas ceļu, nevis novērst no tā.`);
        else if (rel?.status === 'growth') parts.push(`Smalka spriedze: dharma prasa tieši to teritoriju, kas profilam ir vājākais enkurs (${lc(rel.anchorLv)}) — dvēseles uzdevums šeit nav komforta zona, bet apzināta izaugsmes kāpne. Praktiski: misijas virzienā jāiet mazos, atbalstītos soļos, negaidot, ka tas "sanāks dabiski".`);
        else if (rel?.status === 'gap') parts.push(`Jāatzīmē plaisa: dominējošais karjeras enkurs (${lc(rel.anchorLv)}) baro citu virzienu nekā dharma — panākumi ir iespējami, bet jēgas deficīts sekos, ja šis uzdevums nedabūs vietu vai nu darbā, vai apzināti ārpus tā.`);
        mission = parts.join(' ');
    }

    let verdict = null;
    if (arudha || dharma) {
        const bits = [];
        if (arudha) bits.push(`samazināt plaisu starp ${arudha.lagna}-būtību un ${arudha.arudha}-tēlu`);
        if (celtic?.type) bits.push(`integrēt "${celtic.type}" ēnas pusi, nevis to apkarot`);
        if (dharma) bits.push(`dot dzīvē reālu vietu uzdevumam "${lc(dharma.lv)}"`);
        verdict = `Individuācijas virziens: ${bits.join('; ')}. Mērķis nav kļūt par citu cilvēku — mērķis ir pārstāt tērēt enerģiju tam, lai nebūtu tas, kas viņš jau ir.`;
    }

    return {
        key: 'jungian', icon: '🎭', color: '#6d28d9',
        title: 'Junga psihoanalītiķis',
        role: 'Persona ↔ Patība · Anima/Animus · Ēna · individuācijas mērķis',
        sections: [
            { label: 'Persona pret Patību',        sub: 'Lagna, Arudha Lagna un tēla plaisa',      text: persona },
            { label: 'Anima / Animus',              sub: 'Neapzinātais dvēseles ideāls',            text: anima },
            { label: 'Ēnas arhetips',               sub: 'Apspiestās ambīcijas un dzinuļi',         text: shadow },
            { label: 'Individuācijas mērķis',       sub: 'Dharma, jēga un paliekošais mantojums',   text: mission },
        ],
        verdict,
    };
}

// ═════════════════════════════════════════════════════════════════════════════
// KONSĪLIJA KOPSAVILKUMS — kur 4 skati saskan
// ═════════════════════════════════════════════════════════════════════════════

function buildConsilium(profile, specialists) {
    // Neatkarīgo brīdinājuma mērījumu konverģence (izdegšanas ass)
    const V = traitPct(profile, 'neuroticism');
    const psySom = num(profile?.psychosomaticAudit?.psychosomMeter?.psychosomScore);
    const bScore = num(profile?.careerAnchors?.boundary?.score);
    const boundaryRisk = Number.isFinite(bScore) ? 100 - bScore : NaN;
    const tr = Array.isArray(profile?.western?.transits) ? profile.western.transits : [];
    const critNow = tr.filter(t => t.nature === 'critical').length > tr.filter(t => t.nature === 'harmonic').length;

    const stressVotes = [
        Number.isFinite(V) && V >= 60,
        Number.isFinite(boundaryRisk) && boundaryRisk >= 55,
        Number.isFinite(psySom) && psySom >= 70,
        tr.length > 0 && critNow,
    ].filter(Boolean).length;
    const stressMeasured = [V, boundaryRisk, psySom].some(Number.isFinite) || tr.length > 0;

    const att = profile?.relationshipDynamics?.attachment?.primary || null;
    const h1  = profile?.relationshipDynamics?.horsemen?.top?.[0] || null;
    let arudhaGap = null;
    try { arudhaGap = computeArudhaLagna(profile)?.gap ?? null; } catch { arudhaGap = null; }
    // isAligned formula 'adaptive' dominantei VIENMĒR dod false — tas nav stila/vides
    // konflikts, tāpēc kompensācijas punktu rādām tikai izteiktai s1/s2 dominantei.
    const kDom = profile?.careerAnchors?.kahneman?.dominant || null;
    const misaligned = profile?.careerAnchors?.isAligned === false && (kDom === 's1' || kDom === 's2');

    const points = [];
    if (stressMeasured) {
        if (stressVotes >= 3) points.push({ level: 'high', icon: '⚠️', text: `Izdegšanas tēmā speciālisti <b>saskan</b>: ${stressVotes} no 4 neatkarīgiem mērījumiem (jutīgums, robežu enkurs, somatizācija, tranzītu fons) rāda paaugstinātu risku — tas ir konsīlija galvenais brīdinājums.` });
        else if (stressVotes === 2) points.push({ level: 'mid', icon: '△', text: 'Izdegšanas tēmā ir <b>daļēja saskaņa</b>: divi neatkarīgi mērījumi rāda paaugstinātu slodzi — nav akūti, bet psihiatra atjaunošanās protokols nav atliekams uz "kādreiz".' });
        else points.push({ level: 'ok', icon: '✓', text: 'Izdegšanas asī neviens speciālists akūtu risku neredz — rezerve ir, un uzsvars pārceļas uz attīstību, ne aizsardzību.' });
    }
    if (att && h1) {
        points.push(att.key !== 'secure' && num(h1.score) >= 60
            ? { level: 'mid', icon: '△', text: `Attiecību blokā terapeits un psihoanalītiķis norāda uz vienu sakni: <b>${lc(att.lv)}</b> baro paternu "<b>${lc(h1.lv)}</b>" — darbs ar piesaistes drošību automātiski mīkstinās arī konfliktus.` }
            : { level: 'ok', icon: '✓', text: 'Attiecību blokā destruktīvie paterni nav dominējošie — attiecības šim profilam ir resurss, ne riska zona.' });
    }
    if (arudhaGap === 4) points.push({ level: 'mid', icon: '△', text: 'Junga analītiķa mērījums (liela Personas–Patības plaisa) sasaucas ar terapeita aizsardzību analīzi: publiskais tēls prasa uzturēšanas enerģiju, kas citur parādās kā nogurums "bez iemesla".' });
    if (misaligned) points.push({ level: 'mid', icon: '△', text: 'Klīniskais psihologs un psihiatrs saskan par vienu klusu izmaksu pozīciju: kognitīvais stils kompensē vidi (nevis plūst ar to), un šī kompensācija ir pastāvīgs enerģijas patēriņš.' });

    // Ķermeņa kanāla saslēgums: augsta somatizācija + reāli somatiskie marķieri kartē.
    const hasSomaMarkers = !!(profile?.psychosomaticAudit?.acute?.signals?.length || profile?.psychosomaticAudit?.chronic?.patterns?.length);
    if (Number.isFinite(psySom) && psySom >= 70 && hasSomaMarkers) {
        points.push({ level: 'mid', icon: '△', text: 'Terapeita un psihiatra slēdzieni saslēdzas: ķermenis šim profilam ir galvenais emociju kanāls — augsta somatizācijas tendence sakrīt ar konkrētiem somatiskajiem marķieriem kartē. Fiziskie simptomi šeit vispirms jālasa kā psiholoģiska informācija (un tikai pēc tam jāārstē kā simptomi).' });
    }

    // Izdegšanas vides konverģence: bioritms + vājākais enkurs rāda vienu "nāves zonu".
    const corr = burnoutCorridor(profile);
    if (corr?.kind === 'same-monotony') points.push({ level: 'mid', icon: '△', text: 'Psihiatrs un klīniskais psihologs no dažādiem datiem nonāk pie vienas riska vides: <b>vienmuļība</b> — gan bioritms, gan vājākais karjeras enkurs rāda, ka šo profilu dedzina nevis slodze, bet nemainīgums.' });
    else if (corr?.kind === 'same-chaos') points.push({ level: 'mid', icon: '△', text: 'Psihiatrs un klīniskais psihologs no dažādiem datiem nonāk pie vienas riska vides: <b>haoss</b> — gan bioritms, gan vājākais karjeras enkurs rāda, ka šo profilu dedzina neparedzamība, ne darba apjoms.' });

    // Jēgas ass: dharma pret dominējošo enkuru (Jungs ↔ klīniskais psihologs).
    const rel = dharmaAnchorRelation(profile);
    if (rel?.status === 'gap') points.push({ level: 'mid', icon: '△', text: `Jungs un klīniskais psihologs šeit nesaskan produktīvā veidā: karjeras enkurs (${lc(rel.anchorLv)}) un dvēseles uzdevums (${lc(rel.dharmaLv)}) baro dažādus virzienus — profils var būt sekmīgs un tomēr just jēgas deficītu; misijai vajadzīga sava atsevišķa telpa.` });
    else if (rel?.status === 'aligned') points.push({ level: 'ok', icon: '✓', text: `Jēgas asī speciālisti saskan pozitīvi: karjeras enkurs un dvēseles uzdevums (${lc(rel.dharmaLv)}) velk vienā virzienā — darbs šim profilam var būt arī individuācijas ceļš.` });

    if (!points.length) return null;

    const availableCount = specialists.filter(s => s.sections.some(sec => sec.text)).length;
    const lead = `Četri virtuālie speciālisti neatkarīgi izvērtēja vienus un tos pašus šī profila aprēķinus — katrs pa savai metodikai (Kānemans/Šeins · Boulbijs/Gotmans · stresa fizioloģija · Junga arhetipi). Zemāk — punkti, kuros viņu slēdzieni <b>pārklājas</b>: konverģence starp neatkarīgām metodēm ir stiprākais signāls, ko šī sistēma spēj dot.${availableCount < 4 ? ' Daļai speciālistu pietrūka ieejas datu, tāpēc viņu sadaļas ir nepilnas — konsenss rēķināts tikai no pieejamā.' : ''}`;

    return { lead, points };
}

// ═════════════════════════════════════════════════════════════════════════════
// Galvenā eksportējamā funkcija
// ═════════════════════════════════════════════════════════════════════════════

export function buildSpecialistReview(profile) {
    if (!profile) return null;
    const specialists = [
        buildClinical(profile),
        buildTherapist(profile),
        buildPsychiatrist(profile),
        buildJungian(profile),
    ];
    // Ja NEVIENAM speciālistam nav nevienas sadaļas ar datiem — panelis godīgi nerādās.
    const anyData = specialists.some(s => s.sections.some(sec => sec.text) || s.verdict);
    if (!anyData) return null;

    return {
        isTimeUnknown: !!profile?.birth_info?.isTimeUnknown,
        consilium: buildConsilium(profile, specialists),
        specialists,
    };
}
