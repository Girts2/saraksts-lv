// horoskops/js/logic/compatibility.js
// Pilna Ashta Kuta implementācija pēc Parashara Hora Shastra tradīcijām

import { getKutaExplanation } from './kuta_descriptions.js?v=13';

// ── Pamata datu tabulas ────────────────────────────────────────────────────

const RASIS = ['Auns','Vērsis','Dvīņi','Vēzis','Lauva','Jaunava',
               'Svari','Skorpions','Strēlnieks','Mežāzis','Ūdensvīrs','Zivis'];

// 27 Nakšatru precīzas Gana un Nadi vērtības (Parashara)
// Index 0=Ašvini ... 26=Revati
const NAK_GANA = [
    'D','M','R','M','D','M','D','D','R',  // 0-8  (5=Ardra=Manushya — Parashara/BV Raman standarts)
    'R','M','M','D','R','D','R','D','R',  // 9-17
    'R','M','M','D','R','R','M','M','D'   // 18-26
]; // D=Deva, M=Manushya, R=Rakshasa

// Klasiskā Nadi tabula (zigzags, NE vienkāršs A-M-N cikls)
// Ādi  = 0,5,6,11,12,17,18,23,24 · Madhya = 1,4,7,10,13,16,19,22,25 · Antya = 2,3,8,9,14,15,20,21,26
const NAK_NADI = [
    'A','M','N','N','M','A','A','M','N',  // 0-8
    'N','M','A','A','M','N','N','M','A',  // 9-17
    'A','M','N','N','M','A','A','M','N'   // 18-26
]; // A=Ādi, M=Madhya, N=Antya

// Rashi → planētu valdnieks
const RASHI_LORD = [
    'Mars','Venus','Mercury','Moon','Sun','Mercury',
    'Venus','Mars','Jupiter','Saturn','Saturn','Jupiter'
]; // index 0-11

// Planētu draudzība: F=draugs, N=neitrāls, E=ienaidnieks
//              Sun  Moon Mars Merc Jupi Venu Satn
const PLANET_FRIENDS = {
    'Sun':     { Sun:'F', Moon:'F', Mars:'F', Mercury:'N', Jupiter:'F', Venus:'E', Saturn:'E' },
    'Moon':    { Sun:'F', Moon:'F', Mars:'N', Mercury:'F', Jupiter:'N', Venus:'N', Saturn:'N' },
    'Mars':    { Sun:'F', Moon:'F', Mars:'F', Mercury:'E', Jupiter:'F', Venus:'N', Saturn:'N' },
    'Mercury': { Sun:'F', Moon:'E', Mars:'N', Mercury:'F', Jupiter:'N', Venus:'F', Saturn:'N' },
    'Jupiter': { Sun:'F', Moon:'F', Mars:'F', Mercury:'E', Jupiter:'F', Venus:'E', Saturn:'N' },
    'Venus':   { Sun:'E', Moon:'E', Mars:'N', Mercury:'F', Jupiter:'N', Venus:'F', Saturn:'F' },
    'Saturn':  { Sun:'E', Moon:'E', Mars:'E', Mercury:'F', Jupiter:'N', Venus:'F', Saturn:'F' }
};

// Yoni dzīvnieki katrai Nakšatrai (0-26) un dzimums
const YONI = [
    ['Zirgs','M'], ['Zilonis','M'], ['Aita','F'],    ['Čūska','M'],   ['Čūska','F'],
    ['Suns','F'],  ['Kaķis','F'],   ['Aita','M'],    ['Kaķis','M'],   ['Žurka','M'],
    ['Žurka','F'], ['Govs','M'],    ['Bifelis','F'], ['Tīģeris','F'], ['Bifelis','M'],
    ['Tīģeris','M'],['Zaķis','F'], ['Zaķis','M'],   ['Suns','M'],    ['Pērtiķis','M'],
    ['Mangusta','M'],['Pērtiķis','F'],['Lauva','F'],['Zirgs','F'],   ['Lauva','M'],
    ['Govs','F'],  ['Zilonis','F']
];

// Ienaidnieku pāri (0 punkti)
const YONI_ENEMIES = [
    ['Zirgs','Bifelis'], ['Zilonis','Lauva'], ['Aita','Pērtiķis'],
    ['Čūska','Mangusta'], ['Suns','Zaķis'], ['Kaķis','Žurka'],
    ['Govs','Tīģeris']   // klasiskais 7. lielo ienaidnieku pāris (iztrūka)
];

// Draudzīgie pāri (3 punkti) — tikai klasiskais Parashara saraksts
const YONI_FRIENDS = [
    ['Govs','Zilonis'],    // Cow ↔ Elephant
    ['Tīģeris','Lauva'],   // Tiger ↔ Lion
    ['Suns','Lauva'],      // Dog ↔ Lion
    ['Pērtiķis','Lauva'],  // Monkey ↔ Lion
    ['Zirgs','Zilonis']    // Horse ↔ Elephant
];

// ── Palīgfunkcijas ────────────────────────────────────────────────────────

// Normalizē BaZi elementu — dažādi moduļi var sniegt dažādu formātu
function normBazi(e) {
    if (!e) return '';
    const map = {
        'wood':'Koks',    'koks':'Koks',
        'fire':'Uguns',   'uguns':'Uguns',
        'earth':'Zeme',   'zeme':'Zeme',
        'metal':'Metāls', 'metāls':'Metāls',
        'water':'Ūdens',  'ūdens':'Ūdens'
    };
    return map[e.toLowerCase()] || e;
}

function getRasiIndex(moonStr) {
    if (!moonStr) return -1;
    return RASIS.findIndex(r => moonStr.includes(r));
}

// Apreķina Vēdisko Rashi tieiši no Nakšatras indeksa + elapsed_factor
// Tas novērš tropicās (Rietumu) un siderālās (Vēdiskās) sistēmu jaukšanu
// Formula: katra zīme = 9 pādas (2.25 nakšatras × 4 pādas)
function deriveRasiFromNak(nakIndex, elapsedFactor) {
    // elapsedFactor 0.0–1.0 → pāda 1–4
    const pada = Math.min(4, Math.floor((elapsedFactor || 0.5) / 0.25) + 1);
    return Math.floor((nakIndex * 4 + (pada - 1)) / 9);
}


function getNakIndex(nak) {
    if (!nak) return 0;
    // Normalizē transliterāciju: anglisču rakstība → latviešu NAKS masīvs
    const normNak = (s) => s
        .replace(/sh/gi, 'š').replace(/aa/gi, 'ā').replace(/ii/gi, 'ī')
        .replace(/Sh/g, 'Š').replace(/ph/gi, 'ph')
        .replace(/vishakha/i, 'Višākha')
        .replace(/ashadha/i, 'Ašādha')
        .replace(/ashvini|asvini/i, 'Ašvini')
        .replace(/ashlesha/i, 'Ašleša')
        .replace(/shravana/i, 'Šravana')
        .replace(/shatabhisha/i, 'Šatabhiša')
        .replace(/jyeshtha/i, 'Džještha')
        .replace(/mrigashira/i, 'Mrigaširša')
        .replace(/pushya/i, 'Puša')
        .replace(/magha/i, 'Maga');
    const normalized = normNak(nak);
    const NAKS = ['Ašvini','Bharani','Krittika','Rohini','Mrigaširša','Ardra',
                  'Punarvasu','Puša','Ašleša','Maga','Pūrva Phalguni','Uttara Phalguni',
                  'Hasta','Čitra','Svati','Višākha','Anuradha','Džještha',
                  'Mula','Pūrva Ašādha','Uttara Ašādha','Šravana','Dhaništha',
                  'Šatabhiša','Pūrva Bhadrapada','Uttara Bhadrapada','Revati'];
    // Meklē pēc normalizētā teksta
    let i = NAKS.findIndex(n => normalized.toLowerCase().includes(n.toLowerCase().split(' ')[0]));
    // Ja neatrod — mēģina ar oriģinālo tekstu
    if (i < 0) {
        i = NAKS.findIndex(n => nak.toLowerCase().includes(n.toLowerCase().split(' ')[0]));
    }
    // Nekad neizmanto charCodeAt — tas dod nejaušus rezultātus
    return i >= 0 ? i : 0;
}

function getMutualRelation(p1, p2) {
    const r1 = PLANET_FRIENDS[p1]?.[p2] || 'N';
    const r2 = PLANET_FRIENDS[p2]?.[p1] || 'N';
    return [r1, r2];
}

// Pārbauda vai divu Rashi lordi ir dabīgi draugi (Dosha Parihara)
function areLordsFriends(l1, l2) {
    if (l1 === l2) return true;
    const r1 = PLANET_FRIENDS[l1]?.[l2] || 'N';
    const r2 = PLANET_FRIENDS[l2]?.[l1] || 'N';
    // Draugi ja vismaz viens F un neviens nav E
    return (r1 === 'F' || r2 === 'F') && r1 !== 'E' && r2 !== 'E';
}

// ── 8 Kuta aprēķini ───────────────────────────────────────────────────────

// 1. VARNA (max 1) — Sociālais līmenis tikai pēc Rashi (tīrs BPHS)
// Klasiskais noteikums ir lomu-atkarīgs (līgavaiņa Varna ≥ līgavas). Viendzimuma
// pārim lomu nav — bez atsevišķa zara punkts būtu atkarīgs no ievades secības
// (simetrijas audits 2026-07-09: 40/49 viendzimuma pāriem rezultāts mainījās,
// apmainot pamatprofilu ↔ partneri). Tāpēc gender1===gender2 → secības-neatkarīgs
// vidējais pār abiem lomu piešķīrumiem: vienādi rangi = 1, atšķirīgi = 0.5.
// vM/vF — lomu-sakārtotas Varnas aprakstu tekstam (null viendzimuma pārim).
function calcVarna(ri1, ri2, gender1, gender2) {
    const VARNA = {
        0:'Kshatriya', 1:'Vaishya',  2:'Shudra',   3:'Brahmana',
        4:'Kshatriya', 5:'Vaishya',  6:'Shudra',   7:'Brahmana',
        8:'Kshatriya', 9:'Vaishya', 10:'Shudra',  11:'Brahmana'
    };
    const ORDER = { 'Brahmana':4, 'Kshatriya':3, 'Vaishya':2, 'Shudra':1 };
    const v1 = VARNA[ri1] || 'Vaishya';
    const v2 = VARNA[ri2] || 'Vaishya';
    if (gender1 === gender2) {
        const score = ORDER[v1] === ORDER[v2] ? 1 : 0.5;
        return { score, max: 1, v1, v2, vM: null, vF: null };
    }
    const vM = gender1 === 'M' ? v1 : v2;
    const vF = gender1 === 'M' ? v2 : v1;
    return { score: ORDER[vM] >= ORDER[vF] ? 1 : 0, max: 1, v1, v2, vM, vF };
}

// 2. VASYA (max 2) — Pilna 5×5 grupu saderības matrica
function calcVasya(ri1, ri2) {
    function getGroup(ri) {
        if ([0,1,8,9].includes(ri)) return 'Chatushpada';
        if ([2,5,6,10].includes(ri)) return 'Manava';
        if ([3,11].includes(ri)) return 'Jalachara';
        if (ri === 4) return 'Vanachara';
        if (ri === 7) return 'Keeta';
        return 'Manava';
    }

    // Pilna 5×5 grupu saderības matrica (pēc Parashara)
    const GROUP_COMPAT = {
        'Chatushpada': { 'Chatushpada':2, 'Manava':1, 'Jalachara':0, 'Vanachara':1, 'Keeta':0 },
        'Manava':      { 'Chatushpada':1, 'Manava':2, 'Jalachara':1, 'Vanachara':0, 'Keeta':1 },
        'Jalachara':   { 'Chatushpada':0, 'Manava':1, 'Jalachara':2, 'Vanachara':0, 'Keeta':1 },
        'Vanachara':   { 'Chatushpada':1, 'Manava':0, 'Jalachara':0, 'Vanachara':2, 'Keeta':0 },
        'Keeta':       { 'Chatushpada':0, 'Manava':1, 'Jalachara':1, 'Vanachara':0, 'Keeta':2 }
    };

    // Klasiskā Vasya kontroles tabula (standarta Muhurta / Ashtakoota) — zīme : tās "vasya" zīmes.
    // Pāri ir nesakārtoti (saderība ir abpusēja). Iepriekšējie [4,8],[7,11],[10,8] neparādās
    // nevienā klasiskajā tabulā un bija kļūdas; salaboti uz Lauva→Svari u.c. standarta vērtībām.
    const VASYA_2PT = [
        [0,4],[0,7],            // Auns → Lauva, Skorpions
        [0,9],[0,10],           // Mežāzis → Auns; Ūdensvīrs → Auns
        [1,3],[1,6],            // Vērsis → Vēzis, Svari
        [2,5],                  // Dvīņi → Jaunava
        [3,7],[3,8],            // Vēzis → Skorpions, Strēlnieks
        [4,6],                  // Lauva → Svari
        [5,6],[5,11],           // Svari → Jaunava; Jaunava → Zivis
        [6,9],                  // Svari → Mežāzis
        [8,11],                 // Strēlnieks → Zivis
        [9,10],[9,11]           // Mežāzis → Ūdensvīrs; Zivis → Mežāzis
    ];

    const g1 = getGroup(ri1);
    const g2 = getGroup(ri2);
    let score;

    if (ri1 === ri2) {
        score = 2; // Vienāda zīme
    } else if (VASYA_2PT.some(([a,b]) => (a===ri1&&b===ri2)||(a===ri2&&b===ri1))) {
        score = 2; // Specīfiska kontrole
    } else {
        score = GROUP_COMPAT[g1]?.[g2] ?? 0; // Grupu matrica
    }

    return { score, max: 2, g1, g2 };
}

// 3. TARA (max 3) — Nakšatru labklājības pozīcija (stingrais režīms)
function calcTara(ni1, ni2) {
    const goodPos = [2,4,6,8,9]; // Sampat, Kshema, Sadhana, Mitra, Parma Mitra
    // Inkluzīvā skaitīšana: tara = (attālums mod 9) + 1 → 1..9 (1=Janma … 9=Param Mitra).
    // Iepriekš trūka "+1", kas nobīdīja visu skalu par vienu pozīciju.
    const fwd = (((ni2 - ni1 + 27) % 27) % 9) + 1;
    const bwd = (((ni1 - ni2 + 27) % 27) % 9) + 1;
    const fwdGood = goodPos.includes(fwd);
    const bwdGood = goodPos.includes(bwd);
    const score = (fwdGood && bwdGood) ? 3 : (fwdGood || bwdGood) ? 1 : 0;
    return { score, max: 3, fwd, bwd };
}

// 4. YONI (max 4) — Dzīvnieka saderība ar dzimuma niansi
function calcYoni(ni1, ni2) {
    const [a1] = YONI[ni1] || ['Zirgs','M'];
    const [a2] = YONI[ni2] || ['Zirgs','M'];
    let score = 2; // noklusējums: neitrāls
    if (a1 === a2) {
        score = 4; // vienāds dzīvnieks = pilni 4pt (tīrs BPHS, neatkarīgi no dzimuma)
    } else if (YONI_ENEMIES.some(([x,y]) => (x===a1&&y===a2)||(x===a2&&y===a1))) {
        score = 0; // nāvīgi ienaidnieki
    } else if (YONI_FRIENDS.some(([x,y]) => (x===a1&&y===a2)||(x===a2&&y===a1))) {
        score = 3; // draudzīgi dzīvnieki
    }
    return { score, max: 4, a1, a2 };
}

// 5. GRAHA MAITRI (max 5) — Planētu draudzība pēc Rashi lordiem
function calcMaitri(ri1, ri2) {
    const l1 = RASHI_LORD[ri1] || 'Sun';
    const l2 = RASHI_LORD[ri2] || 'Moon';
    let score;
    if (l1 === l2) {
        score = 5;
    } else {
        const [r1, r2] = getMutualRelation(l1, l2);
        const map = { FF:5, FN:4, NF:4, NN:3, FE:1, EF:1, NE:0, EN:0, EE:0 };
        score = map[r1+r2] ?? 2;
    }
    return { score, max: 5, l1, l2 };
}

// 6. GANA (max 6) — Temperaments
function calcGana(ni1, ni2) {
    const g1 = NAK_GANA[ni1] || 'M';
    const g2 = NAK_GANA[ni2] || 'M';
    const GL = { D:'Deva', M:'Manushya', R:'Rakshasa' };
    let score = 0;
    if (g1 === g2) score = 6;
    else if ((g1==='D'&&g2==='M')||(g1==='M'&&g2==='D')) score = 5;
    // Klasiskā Ashtakoota tabula (BV Raman / Jagannatha Hora): Deva+Rakshasa = 1pt (nosacīts),
    // Manushya+Rakshasa = 0pt (cilvēks+dēmons — lielākā ikdienas temperamenta pretruna).
    else if ((g1==='D'&&g2==='R')||(g1==='R'&&g2==='D')) score = 1;
    return { score, max: 6, g1: GL[g1], g2: GL[g2] };
}

// 7. BHAKUT / RASHI (max 7) — Mēness zīmju leņķiskā attiecība
// parihara=true → Dosha Parihara: Bhakut doša atceļas, ja zīmju valdnieki ir draugi/viena planēta
function calcBhakut(ri1, ri2, parihara = false) {
    if (ri1 < 0 || ri2 < 0) return { score: 0, max: 7, rel:'?', rashi1:'?', rashi2:'?', neutralized: false };
    const diff = (ri2 - ri1 + 12) % 12;
    // Klasiskā Bhakut: doša (0pt) TIKAI 2/12, 5/9, 6/8 attiecībās; visas pārējās = 7pt.
    // 4/10 nav klasiska doša, un 3/11 ir labvēlīga (pilni 7pt) — iepriekš tās bija nepareizi sodītas.
    const REL = {
        0:{ score:7, rel:'1/1'  }, 1:{ score:0, rel:'2/12' },
        2:{ score:7, rel:'3/11' }, 3:{ score:7, rel:'4/10' },
        4:{ score:0, rel:'5/9'  }, 5:{ score:0, rel:'6/8'  },
        6:{ score:7, rel:'7/7'  }, 7:{ score:0, rel:'6/8'  },
        8:{ score:0, rel:'5/9'  }, 9:{ score:7, rel:'4/10' },
       10:{ score:7, rel:'3/11' },11:{ score:0, rel:'2/12' }
    };
    const r = REL[diff];
    let score = r.score;
    let neutralized = false;
    if (parihara && score === 0 && areLordsFriends(RASHI_LORD[ri1], RASHI_LORD[ri2])) {
        score = 7; neutralized = true;
    }
    return { score, max: 7, rel: r.rel,
             rashi1: RASIS[ri1]||'?', rashi2: RASIS[ri2]||'?', neutralized };
}

// 8. NADI (max 8) — Enerģētiskā veselība
// parihara=true → Nadi doša atceļas, ja (vienāda rashi, dažāda nakšatra) vai (vienāda nakšatra, dažāda rashi)
function calcNadi(ni1, ni2, ri1 = -1, ri2 = -1, parihara = false) {
    const n1 = NAK_NADI[ni1] || 'A';
    const n2 = NAK_NADI[ni2] || 'A';  // abiem vienāds noklusejums
    const NL = { A:'Ādi', M:'Madhya', N:'Antya' };
    let score = n1 !== n2 ? 8 : 0;
    let neutralized = false;
    if (parihara && score === 0 &&
        ((ri1 === ri2 && ni1 !== ni2) || (ni1 === ni2 && ri1 !== ri2))) {
        score = 8; neutralized = true;
    }
    return { score, max: 8, n1: NL[n1], n2: NL[n2], neutralized };
}

// ── Svērtie stāvokļi nezināmam laikam (24h lokālā sadalījuma vidējošana) ────

function isUnknownTime(p) {
    return !!(p?.birth_info?.isTimeUnknown);
}

// Punkta nakšatras indekss: .index → .nakshatra_index → teksta meklēšana
function pointNakIndex(p) {
    if (typeof p?.vedic?.nakshatra?.index === 'number') return p.vedic.nakshatra.index;
    if (typeof p?.vedic?.nakshatra?.nakshatra_index === 'number') return p.vedic.nakshatra.nakshatra_index;
    return getNakIndex(p?.vedic?.nakshatra?.nakshatra || '');
}

// Punkta Rashi indekss: rashi_index → atvasināts no nakšatras (NE no tropiskā teksta)
function pointRashiIndex(p) {
    if (typeof p?.vedic?.nakshatra?.rashi_index === 'number') return p.vedic.nakshatra.rashi_index;
    const ef = p?.vedic?.nakshatra?.elapsed_factor ?? p?.vedic?.nakshatra?.elapsed ?? 0.5;
    return deriveRasiFromNak(pointNakIndex(p), ef);
}

// Svērto nakšatru marginālis [{v, w}]: nezināmam laikam — 24h sweep nakDist, citādi punkts.
function nakMargin(p) {
    const dist = p?.timeSweep?.vedic?.planets?.['Meness']?.nakDist;
    if (isUnknownTime(p) && dist && Object.keys(dist).length) {
        return Object.entries(dist).map(([i, w]) => ({ v: +i, w }));
    }
    return [{ v: pointNakIndex(p), w: 1 }];
}

// Svērto Rashi marginālis [{v, w}]: nezināmam laikam — 24h sweep signDist (Mēness zīme), citādi punkts.
function rashiMargin(p) {
    const dist = p?.timeSweep?.vedic?.planets?.['Meness']?.signDist;
    if (isUnknownTime(p) && dist && Object.keys(dist).length) {
        return Object.entries(dist).map(([i, w]) => ({ v: +i, w }));
    }
    return [{ v: pointRashiIndex(p), w: 1 }];
}

// Joint (nakšatra + rashi) svērto stāvokļu saraksts [{ni, ri, w}].
// Zināmam laikam → 1 stāvoklis. Nezināmam → marginālu reizinājums.
// Vienas dimensijas Kuta gadījumā (tikai nak VAI tikai rashi) reizinājums dod TIEŠU gaidāmo
// vērtību (neizmantotā dimensija summējas uz 1). Nadi Parihara (vajadzīgi abi) nezināmā laikā
// pieņem nak⊥rashi — neliela aproksimācija tikai neitralizācijas karogam, eksakta zināmam laikam.
function jointStates(p) {
    // Patiess joint (nakšatra × rashi) no 24h sweep, ja pieejams — eksakts VISĀM Kuta,
    // ieskaitot Nadi Parihara (kas vajadzīgi abi). Novērš agrāko nak⊥rashi tuvinājumu.
    const joint = p?.timeSweep?.vedic?.planets?.['Meness']?.nakSignDist;
    if (isUnknownTime(p) && joint && Object.keys(joint).length) {
        return Object.entries(joint).map(([k, w]) => {
            const key = +k;
            return { ni: Math.floor(key / 12), ri: key % 12, w };
        });
    }
    // Rezerve (zināms laiks vai vecāks sweep bez joint): neatkarīgu marginālu reizinājums.
    // Vienas dimensijas Kuta gadījumā tas joprojām dod tiešu gaidāmo vērtību.
    const nm = nakMargin(p), rm = rashiMargin(p);
    const out = [];
    for (const a of nm) for (const b of rm) out.push({ ni: a.v, ri: b.v, w: a.w * b.w });
    return out;
}

// Gaidāmā vērtība pār abu personu joint stāvokļu krustojumu.
// fn(stateA, stateB) → Kuta rezultāts; stāvoklī ir .ni un .ri.
// Atgriež modālās (visticamākās) kombinācijas meta + vidējoto punktu skaitu (score),
// kā arī modalScore — veselo punktu skaitu apraksta teksta izvēlei.
function expectKuta(states1, states2, fn) {
    let scoreSum = 0, wSum = 0, modal = null, modalW = -1;
    for (const a of states1) {
        for (const b of states2) {
            const w = a.w * b.w;
            if (w <= 0) continue;
            const r = fn(a, b);
            scoreSum += r.score * w;
            wSum += w;
            if (w > modalW) { modalW = w; modal = r; }
        }
    }
    const avg = wSum > 0 ? scoreSum / wSum : 0;
    return { ...modal, score: avg, modalScore: modal ? modal.score : 0 };
}

// ── Psiholoģiskās asis no relationshipDynamics (Big-Five / Bowlby / Gotman) ──
// Trīs galvenās asis vēsturiski bija TIKAI pārkrāsotas Mēness Kuta. Šeit pievienojam
// reālu psiholoģisko signālu no abu partneru profiliem, lai "Komunikācija" tiešām
// saturētu komunikācijas datus (Gotmana jātnieki), nevis Tara+Vasya.
const PSYCH_WEIGHT = 0.5;                                   // hibrīda sajaukums: 50% Mēness / 50% psiholoģija
const clamp100 = v => Math.max(0, Math.min(100, v));

const synScore  = (dyn, key) => dyn?.synergyAll?.find(s => s.key === key)?.score ?? null;
const fricScore = (dyn, key) => dyn?.frictionAll?.find(f => f.key === key)?.score ?? null;
const attScore  = (dyn, key) => dyn?.attachment?.allScores?.find(a => a.key === key)?.score ?? null;
// horsemen.allScores ir sakārtots dilstoši → [0] ir spēcīgākais (sliktākais) jātnieks
const worstHorseman = dyn => dyn?.horsemen?.allScores?.[0]?.score ?? null;

// Viena cilvēka 3 asu ieguldījums (0–100) no viņa relationshipDynamics
function personPsychAxes(dyn) {
    if (!dyn) return null;
    const g = (v, d = 50) => (v == null ? d : v);
    const emoSafety = g(synScore(dyn, 'emotional_safety'));
    const physInt   = g(synScore(dyn, 'physical_intimacy'));
    const intel     = g(synScore(dyn, 'intellectual_partnership'));
    const loyalty   = g(synScore(dyn, 'loyalty_depth'));
    const secure    = g(attScore(dyn, 'secure'));
    const emoDist   = g(fricScore(dyn, 'emotional_distance'));
    const worst     = g(worstHorseman(dyn));

    return {
        // Emocionālā rezonanse: emocionālā drošība + jutekliskums + drošā piesaiste
        emotional:   0.40 * emoSafety + 0.30 * physInt + 0.30 * secure,
        // Ģimenes vērtības: lojalitāte/dziļums + drošā piesaiste + intelektuālā partnerība
        values:      0.55 * loyalty   + 0.30 * secure  + 0.15 * intel,
        // Komunikācija/konflikti: zemi Gotmana jātnieki + intelektuālā saikne + zema emoc. distance
        interaction: 0.45 * (100 - worst) + 0.30 * intel + 0.25 * (100 - emoDist)
    };
}

// Pāra psiholoģiskās asis = abu partneru vidējais (ja vismaz vienam ir dynamics)
function pairPsychAxes(p1, p2) {
    const a = personPsychAxes(p1?.relationshipDynamics);
    const b = personPsychAxes(p2?.relationshipDynamics);
    if (!a && !b) return null;
    const avg = (x, y) => {
        const vals = [x, y].filter(v => v != null);
        return vals.length ? vals.reduce((s, v) => s + v, 0) / vals.length : 50;
    };
    return {
        emotional:   clamp100(Math.round(avg(a?.emotional,   b?.emotional))),
        values:      clamp100(Math.round(avg(a?.values,      b?.values))),
        interaction: clamp100(Math.round(avg(a?.interaction, b?.interaction)))
    };
}

// ── Galvenā eksporta funkcija ─────────────────────────────────────────────

export function calculateCompatibility(p1, p2, gender1 = 'M', gender2 = 'F', parihara = false, opts = {}) {
    if (!p1 || !p2) return null;
    // gender1, gender2, parihara: padoti kā parametri — nav atkarīgi no window global

    const gender1Eff = gender1 || 'M';
    const gender2Eff = gender2 || 'F';

    // Joint (nakšatra+rashi) svērtie stāvokļi: zināmam laikam — viens punkts (w=1),
    // nezināmam — 24h lokālā sadalījuma sweep (gaidāmā vērtība). Vienas dimensijas Kuta
    // vidējošana paliek eksakta; Nadi Parihara izmanto abas dimensijas.
    const js1 = jointStates(p1), js2 = jointStates(p2);

    const varna  = expectKuta(js1, js2, (a, b) => calcVarna(a.ri, b.ri, gender1Eff, gender2Eff));
    const vasya  = expectKuta(js1, js2, (a, b) => calcVasya(a.ri, b.ri));
    const tara   = expectKuta(js1, js2, (a, b) => calcTara(a.ni, b.ni));
    const yoni   = expectKuta(js1, js2, (a, b) => calcYoni(a.ni, b.ni));
    const maitri = expectKuta(js1, js2, (a, b) => calcMaitri(a.ri, b.ri));
    const gana   = expectKuta(js1, js2, (a, b) => calcGana(a.ni, b.ni));
    const bhakut = expectKuta(js1, js2, (a, b) => calcBhakut(a.ri, b.ri, parihara));
    const nadi   = expectKuta(js1, js2, (a, b) => calcNadi(a.ni, b.ni, a.ri, b.ri, parihara));

    const r1 = v => Math.round(v * 10) / 10;
    [varna, vasya, tara, yoni, maitri, gana, bhakut, nadi].forEach(k => { k.score = r1(k.score); });

    const timeAveraged = isUnknownTime(p1) || isUnknownTime(p2);

    const total = r1(varna.score + vasya.score + tara.score + yoni.score +
                  maitri.score + gana.score + bhakut.score + nadi.score);

    // Ātrais ceļš skenēšanai (partneru meklētājs): tikai kopskaits, bez aprakstiem/asīm.
    if (opts.scoreOnly) return { kutaScore: total, timeAveraged };

    const kutaBreakdown = [
        {
            name:'Varna', lv:'Sociālais Saderības Līmenis',
            score: varna.score, max: 1,
            desc: `${varna.v1} ↔ ${varna.v2}`,
            explanation: 'Pamatojas tikai uz Mēness Rashi (tīrs BPHS). Brahmana > Kshatriya > Vaishya > Shudra. Vīrieša Varna ≥ sievietes Varna = 1pt. Viendzimuma pārim lomu nav — vienādas Varnas = 1pt, atšķirīgas = 0.5pt (secības-neatkarīgi). Nosaka kopīgu izaugsmes virzienu.',
            neutralized: false,
            kutaDesc: getKutaExplanation('varna', varna.modalScore, 1, { v1: varna.v1, v2: varna.v2, vM: varna.vM, vF: varna.vF })
        },
        {
            name:'Vasya', lv:'Savstarpējā Pievilcība un Kontrole',
            score: vasya.score, max: 2,
            desc: `${vasya.g1} ↔ ${vasya.g2}`,
            explanation: 'Rashi tiek iedalīti 5 grupās: Chatushpada (4-kājainie), Manava (cilvēki), Jalachara (ūdens), Vanachara (mežāzis), Keeta (kukainis). Viena grupa = 2pt, Manava ar citiem = 1pt, Vanachara/Keeta = 0pt.',
            neutralized: false,
            kutaDesc: getKutaExplanation('vasya', vasya.modalScore, 2, { g1: vasya.g1, g2: vasya.g2 })
        },
        {
            name:'Tara', lv:'Zvaigžņu Labklājība',
            score: tara.score, max: 3,
            desc: `Uz priekšu: ${tara.fwd}, Atpakaļ: ${tara.bwd}`,
            explanation: 'Nakšatru pozīcija viens no otra (9-pozīciju ciklā). Labvēlīgas: 2(Sampat), 4(Kshema), 6(Sadhana), 8(Mitra), 9(Parma Mitra). Abi=3pt, viens=1pt, neviens=0pt.',
            neutralized: false,
            kutaDesc: getKutaExplanation('tara', tara.modalScore, 3, { fwd: tara.fwd, bwd: tara.bwd })
        },
        {
            name:'Yoni', lv:'Fiziskā un Instinktīvā Saderība',
            score: yoni.score, max: 4,
            desc: `${yoni.a1} ↔ ${yoni.a2}`,
            explanation: 'Katrai Nakšatrai ir dzīvnieks-simbols. Vienāds dzīvnieks=4pt, Draudzīgi=3pt, Neitrāli=2pt, Ienaidnieki (7 nāvīgie pāri)=0pt.',
            neutralized: false,
            kutaDesc: getKutaExplanation('yoni', yoni.modalScore, 4, { a1: yoni.a1, a2: yoni.a2 })
        },
        {
            name:'Graha Maitri', lv:'Mentālā Draudzība (Planētu)',
            score: maitri.score, max: 5,
            desc: `${maitri.l1} ↔ ${maitri.l2}`,
            explanation: 'Salīdzina abu Mēness Rashi planētu-valdnieku savstarpējo draudzību. Abi draugi=5pt, draugs+neitrāls=4pt, abi neitrāli=3pt, draugs+ienaidnieks=1pt, ienaidnieki=0pt.',
            neutralized: false,
            kutaDesc: getKutaExplanation('maitri', maitri.modalScore, 5, { l1: maitri.l1, l2: maitri.l2 })
        },
        {
            name:'Gana', lv:'Temperamenta un Dvēseles Daba',
            score: gana.score, max: 6,
            desc: `${gana.g1} ↔ ${gana.g2}`,
            explanation: 'Nakšatras Gana: Deva (dievišķs) 9 Nakšatras; Manushya (cilvēcisks) 9; Rakshasa (intensīvs) 9. Vienādi=6pt, Deva+Manushya=5pt, Deva+Rakshasa=1pt (nosacīts), Manushya+Rakshasa=0pt.',
            neutralized: false,
            kutaDesc: getKutaExplanation('gana', gana.modalScore, 6, { g1: gana.g1, g2: gana.g2 })
        },
        {
            name:'Bhakut', lv:'Mēness Zīmju Leņķiskā Attiecība',
            score: bhakut.score, max: 7,
            desc: `${bhakut.rashi1} ↔ ${bhakut.rashi2} (${bhakut.rel || '?'})`,
            explanation: 'Mēness zīmju telpiskā attiecība. 1/1=7pt, 7/7=7pt, 3/11=7pt, 4/10=7pt. Nelabvēlīgi (Bhakut doša): 2/12, 5/9, 6/8=0pt.',
            neutralized: bhakut.neutralized,
            kutaDesc: getKutaExplanation('bhakut', bhakut.modalScore, 7, { rashi1: bhakut.rashi1, rashi2: bhakut.rashi2, rel: bhakut.rel })
        },
        {
            name:'Nadi', lv:'Enerģētiskā Veselība (Svarīgākā!)',
            score: nadi.score, max: 8,
            desc: `${nadi.n1} ↔ ${nadi.n2}`,
            explanation: 'SVARĪGĀKĀ kategorija (max 8pt). Ādi/Madhya/Antya — enerģētiskās plūsmas tips. Dažādas Nadi=8pt.',
            neutralized: nadi.neutralized,
            kutaDesc: getKutaExplanation('nadi', nadi.modalScore, 8, { n1: nadi.n1, n2: nadi.n2 })
        }
    ];

    // ── Trīs galvenās asis: Vēdiskā (Mēness Kuta) + psiholoģiskā hibrīds ─────
    // Vēdiskā (Mēness) komponente — vēsturiskās pārkrāsotās Kuta.
    const moonEmot = clamp100(Math.round((nadi.score/8)*40 + (gana.score/6)*35 + (yoni.score/4)*25));
    const moonVal  = clamp100(Math.round((bhakut.score/7)*45 + (maitri.score/5)*35 + (varna.score/1)*20));
    const moonInt  = clamp100(Math.round((tara.score/3)*60 + (vasya.score/2)*40));

    // Psiholoģiskā komponente — no abu partneru relationshipDynamics (ja pieejama).
    const psych = pairPsychAxes(p1, p2);

    // Hibrīds: ja psiholoģiskie dati ir, 50/50 sajaukums; citādi tīra Mēness komponente.
    const blend = (moon, ps) => ps == null ? moon
                  : clamp100(Math.round(moon * (1 - PSYCH_WEIGHT) + ps * PSYCH_WEIGHT));
    const emotPct = blend(moonEmot, psych?.emotional);
    const valPct  = blend(moonVal,  psych?.values);
    const intPct  = blend(moonInt,  psych?.interaction);

    const desc = (v, arr) => arr.find(([t]) => v <= t)?.[1] || arr[arr.length-1][1];
    const emotDesc = desc(emotPct, [[20,'Svešinieki — emocionāla distanse.'],[40,'Vēsais miers — racionālas attiecības.'],[60,'Simpātija — labs atbalsts.'],[80,'Dvēseles radinieki — jūt bez vārdiem.'],[101,'Vienota plūsma — absolūta saplūšana.']]);
    const valDesc  = desc(valPct,  [[20,'Pretpoli — pilnīgi pretēji uzskati.'],[40,'Kompromiss — iespējams ar upuriem.'],[60,'Līdzsvarots — galvenajos sakrīt.'],[80,'Vienots ceļš — kopīgs virziens.'],[101,'Viens vesels — absolūta vienprātība.']]);
    const intDesc  = desc(intPct,  [[20,'Sadzursme — strīdi dominē.'],[40,'Aizsardzība — bieži jāskaidrojas.'],[60,'Darba režīms — konstruktīva saziņa.'],[80,'Aktīva ieklausīšanās — ātri atrisina.'],[101,'Telepātija — saprot no pusvārda.']]);

    return {
        kutaScore:     total,
        kutaBreakdown,
        timeAveraged,
        parihara,
        emotional:   { pct: emotPct, desc: emotDesc, moon: moonEmot, psych: psych?.emotional   ?? null },
        values:      { pct: valPct,  desc: valDesc,  moon: moonVal,  psych: psych?.values      ?? null },
        interaction: { pct: intPct,  desc: intDesc,  moon: moonInt,  psych: psych?.interaction ?? null },
        hasPsych:    psych != null
    };
}
