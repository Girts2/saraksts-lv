// struktura/js/treemap.js — Latvijas uzņēmumu treemap (canvas + d3.hierarchy/treemap/zoom).
//
// Datu plūsma: overview.json (nodaļas ar metriku kopsummām + TOP-40 + pos/neg) ielādējas uzreiz;
// pietuvinot nodaļu, lazy ielādējas div_XX.json ar klasēm un VISIEM nodaļas uzņēmumiem.
// TOP rindas tiek atkārtoti izmantotas pēc reģ. nr., tāpēc selekcija pārdzīvo ielādi.
//
// Datu svaigums: finanšu rādītāji nāk no uzņēmuma jaunākā gada pārskata, bet darbinieku skaits un
// bruto alga — no VID CETURKŠŅU datiem (par ~1,5 gadu svaigāki nekā VID gada tabula). Salīdzinājumi
// starp gadiem (izaugsme, darbinieku dinamika, produktivitāte) lieto pārskata perioda 'ef', lai abas
// puses būtu no viena avota; 'vq' rāda, vai konkrētajam uzņēmumam e/s nāk no ceturkšņiem.
//
// Metrikas: bloka izmērs = izvēlētā metrika (apgrozījums/peļņa/aktīvi/pamatkapitāls/marža/
// darbinieki/bruto alga); uzņēmumi bez attiecīgās metrikas datiem netiek rādīti. Krāsa vienmēr
// zaļa (peļņa) vai sarkana (zaudējumi). Neielādēto nodaļu "atlikums" un pārāk blīvas zonas tiek
// zīmētas kā zaļi/sarkana MOZAĪKA (proporcija = nodaļas pelnošo īpatsvars) — pelēku bloku nav.
//
// Interakcija: hover neko nerāda; klikšķis uz uzņēmuma = detaļu panelis + piezūmēšanās;
// atkārtots klikšķis uz tā paša = panelis ciet + zooms atpakaļ; klikšķis uz nozares = ienirt.

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // --- KONSTANTES ---
    const FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif";
    const COLOR = {
        profit: '#2f9e44', loss: '#e03131',
        secHdr: 'rgba(23,32,42,0.96)', divHdr: 'rgba(69,90,100,0.95)',
        bg: '#dde3e8',
    };
    const SEC_HDR_H = 20, DIV_HDR_H = 17, CLS_CHIP_H = 15;
    const LOAD_COVERAGE = 0.18; // nodaļa aizņem >18% skata -> ielādē pilnos datus (pēc nostabilizēšanās)
    const MAX_PARALLEL = 2;
    const PANEL_W = 320;        // detaļu paneļa platums — selekcijas zoom centrējas atlikušajā daļā

    // Metrikas: get = izkārtojuma vērtība (0/neg = neattēlo), leafFmt = vērtība uz bloka/panelī,
    // aggFmt = kopsumma virsrakstos (v = summa, n = uzņēmumu skaits zem mezgla).
    const METRICS = {
        t: { label: 'Apgrozījums', get: c => c.t > 0 ? c.t : 0,
             leafFmt: c => fmtEur(c.t), aggFmt: (v, n) => fmtEur(v) },
        p: { label: 'Peļņa', get: c => Math.abs(c.p),
             leafFmt: c => signEur(c.p), aggFmt: (v, n) => '|peļņa| ' + fmtEur(v) },
        a: { label: 'Aktīvi', get: c => c.a > 0 ? c.a : 0,
             leafFmt: c => fmtEur(c.a), aggFmt: (v, n) => fmtEur(v) },
        k: { label: 'Pamatkapitāls', get: c => c.k > 0 ? c.k : 0,
             leafFmt: c => fmtEur(c.k), aggFmt: (v, n) => fmtEur(v) },
        m: { label: 'Marža', get: c => (c.t > 0 && c.p !== null) ? Math.min(Math.abs(c.p) / c.t * 100, 300) : 0,
             leafFmt: c => fmtPct(c.p / c.t * 100), aggFmt: (v, n) => 'vid. ' + fmtPct(n ? v / n : 0) },
        e: { label: 'Darbinieki', get: c => c.e > 0 ? c.e : 0,
             leafFmt: c => fmtCount(c.e) + ' darb.', aggFmt: (v, n) => fmtCount(Math.round(v)) + ' darb.' },
        s: { label: 'Bruto alga', get: c => c.s > 0 ? c.s : 0,
             leafFmt: c => fmtCount(c.s) + ' €/mēn.', aggFmt: (v, n) => 'vid. ' + fmtCount(n ? Math.round(v / n) : 0) + ' €/mēn.' },
    };

    // Bilances posteņi (tā pati kopa kā Reģistra bilances panelī; indekss = bal masīva pozīcija).
    const BAL_ITEMS = [
        'Ilgtermiņa ieguldījumi', 'Nemateriālie ieguldījumi', 'Pamatlīdzekļi', 'Finanšu ieguldījumi',
        'Apgrozāmie līdzekļi', 'Krājumi', 'Debitori', 'Vērtspapīri', 'Nauda',
        'Pašu kapitāls', 'Uzkrājumi', 'Ilgtermiņa saistības', 'Īstermiņa saistības', 'Nākamo periodu ieņēmumi',
    ];
    const balv = (c, i) => (c.b && c.b[i] > 0) ? c.b[i] : 0; // izkārtojumam der tikai pozitīvais
    BAL_ITEMS.forEach((label, i) => {
        METRICS['b' + i] = {
            label, bal: i,
            get: c => balv(c, i),
            leafFmt: c => fmtEur(c.b[i]),
            aggFmt: (v, n) => fmtEur(v),
        };
    });
    METRICS.bk = { // Saistības (kreditori) = ilgtermiņa + īstermiņa + nākamo periodu
        label: 'Saistības (kreditori)', balSum: [11, 12, 13],
        get: c => balv(c, 11) + balv(c, 12) + balv(c, 13),
        leafFmt: c => fmtEur(balv(c, 11) + balv(c, 12) + balv(c, 13)),
        aggFmt: (v, n) => fmtEur(v),
    };
    let metricKey = 't';

    // Nodaļas kopsumma pašreizējai metrikai (neielādēto nodaļu atlikuma blokam).
    function divTotal(divData) {
        const m = METRICS[metricKey];
        if (m.bal !== undefined) return (divData.tot.b || [])[m.bal] || 0;
        if (m.balSum) return m.balSum.reduce((s, i) => s + ((divData.tot.b || [])[i] || 0), 0);
        return divData.tot[metricKey] || 0;
    }

    // --- KRĀSU REŽĪMI ---
    // Krāsa ir no izmēra neatkarīga dimensija. Katram režīmam: color(c, divData) -> css krāsa,
    // csIdx -> nodaļas 'cs' daļa (būves aprēķināta) neielādēto zonu mozaīkai, mosaic -> [labais,
    // sliktais] pamattoņi, legend -> čipi, panel(c) -> papildu rinda detaļu panelī.
    // GRAY = trūkst tieši ŠĪ režīma datu (bloka izmērs un vieta nemainās).
    const GRAY = '#b0b7bd';
    const rampPos = d3.interpolateRgb('#57a95d', '#14571f');
    const rampNeg = d3.interpolateRgb('#e06f6f', '#7d1414');
    function diverge(x, cap) {
        const t = Math.min(Math.abs(x) / cap, 1);
        return x >= 0 ? rampPos(t) : rampNeg(t);
    }
    function signPct(v) { return (v >= 0 ? '+' : '−') + fmtNum(Math.abs(v), 1) + '%'; }
    const growth = c => (c.t2 > 0) ? (c.t - c.t2) / c.t2 : null;
    // Dinamikai un produktivitātei lieto PĀRSKATA perioda darbiniekus (ef), lai periodi sakristu
    // ar peļņu/apgrozījumu; c.e ir svaigākais (VID ceturkšņa) skaits un der tikai kā rezerve.
    const empRep = c => (c.ef > 0) ? c.ef : (c.e > 0 ? c.e : 0);
    const empGrowth = c => (empRep(c) > 0 && c.e2 > 0) ? (empRep(c) - c.e2) / c.e2 : null;
    const liqRatio = c => (c.b && c.b[4] > 0 && c.b[12] > 0) ? c.b[4] / c.b[12] : null;
    const debtRatio = c => (c.a > 0 && c.b) ? (balv(c, 11) + balv(c, 12) + balv(c, 13)) / c.a : null;

    const RTG_COLOR = { A: '#2b8a3e', B: '#f08c00', C: '#e03131', J: '#339af0', N: '#5c636a' };
    const RTG_TEXT = { A: 'A — laba saistību izpilde', B: 'B — jāuzlabo saistību izpilde',
                       C: 'C — pārkāpumi saistību izpildē', J: 'J — jaunreģistrēts',
                       N: 'N — neaktīvs nodokļu maksātājs' };

    let medianS = 0, newestYear = 0;              // uzstāda overview ielāde
    let salaryPeriod = '', empPeriod = '';        // VID ceturkšņu logs algai / darbinieku skaitam

    const COLOR_MODES = {
        pz: { label: 'Peļņa +/−', csIdx: 0, mosaic: ['#2f9e44', '#e03131'],
              color: c => c.p >= 0 ? '#2f9e44' : '#e03131',
              legend: [['#2f9e44', 'peļņa'], ['#e03131', 'zaudējumi']],
              panel: null },
        marza: { label: 'Marža', csIdx: 0, mosaic: ['#2f8f3e', '#c22f2f'],
              color: c => diverge(c.p / c.t * 100, 20),
              legend: [['#14571f', 'marža ≥ +20%'], ['#57a95d', 'nedaudz plusos'],
                       ['#e06f6f', 'nedaudz mīnusos'], ['#7d1414', '≤ −20%']],
              panel: null }, // Marža jau ir panelī vienmēr
        likvid: { label: 'Likviditāte', csIdx: 1, mosaic: ['#2f8f3e', '#c22f2f'],
              color: c => { const r = liqRatio(c); if (r === null) return GRAY;
                  return r >= 1.5 ? '#1b6e2d' : r >= 1 ? '#57a95d' : r >= 0.7 ? '#e8590c' : '#a61e1e'; },
              legend: [['#1b6e2d', 'koef. ≥ 1,5'], ['#57a95d', '1–1,5'], ['#e8590c', '0,7–1'],
                       ['#a61e1e', '< 0,7'], [GRAY, 'nav datu']],
              panel: c => { const r = liqRatio(c); return ['Likviditātes koef.', r === null ? '—' : fmtNum(r, 2)]; } },
        parads: { label: 'Parādu slogs', csIdx: 2, mosaic: ['#2f8f3e', '#c22f2f'],
              color: c => { const d = debtRatio(c); if (d === null) return GRAY;
                  return d < 0.3 ? '#1b6e2d' : d < 0.7 ? '#57a95d' : d < 1 ? '#e8590c' : '#a61e1e'; },
              legend: [['#1b6e2d', 'saistības < 30% aktīvu'], ['#57a95d', '30–70%'],
                       ['#e8590c', '70–100%'], ['#a61e1e', '≥ 100%'], [GRAY, 'nav datu']],
              panel: c => { const d = debtRatio(c); return ['Saistības / aktīvi', d === null ? '—' : fmtNum(d * 100, 0) + '%']; } },
        pk: { label: 'Pašu kapitāls', csIdx: 3, mosaic: ['#2f9e44', '#7048e8'],
              color: c => { const v = c.b ? c.b[9] : null;
                  if (v === null || v === undefined) return GRAY;
                  return v > 0 ? '#2f9e44' : '#7048e8'; },
              legend: [['#2f9e44', 'pozitīvs'], ['#7048e8', 'negatīvs — "zem ūdens"'], [GRAY, 'nav datu']],
              panel: c => ['Pašu kapitāls (bilance)',
                  (c.b && c.b[9] !== null && c.b[9] !== undefined) ? fmtEur(c.b[9]) : '—'] },
        izaug: { label: 'Izaugsme', csIdx: 4, mosaic: ['#2f8f3e', '#c22f2f'],
              color: c => { const g = growth(c); return g === null ? GRAY : diverge(g * 100, 30); },
              legend: [['#14571f', 'aug ≥ +30%'], ['#57a95d', 'nedaudz aug'],
                       ['#e06f6f', 'nedaudz sarūk'], ['#7d1414', 'sarūk ≤ −30%'], [GRAY, 'nav pērnā gada']],
              panel: c => { const g = growth(c); return ['Apgrozījuma izaugsme', g === null ? '—' : signPct(g * 100)]; } },
        pavers: { label: 'Peļņas pavērsiens', csIdx: 5, mosaic: ['#2f9e44', '#a61e1e'],
              color: c => { if (c.p2 === null || c.p2 === undefined) return GRAY;
                  if (c.p >= 0) return c.p2 >= 0 ? '#2b8a3e' : '#0ca678';
                  return c.p2 >= 0 ? '#e8590c' : '#a61e1e'; },
              legend: [['#2b8a3e', 'stabili plusos'], ['#0ca678', 'atgriezās plusos'],
                       ['#e8590c', 'iekrita mīnusos'], ['#a61e1e', 'stabili mīnusos'], [GRAY, 'nav pērnā gada']],
              panel: c => ['Peļņa pērn', (c.p2 === null || c.p2 === undefined) ? '—' : signEur(c.p2)] },
        darb: { label: 'Darbinieku dinamika', csIdx: 6, mosaic: ['#2f8f3e', '#c22f2f'],
              color: c => { const g = empGrowth(c); return g === null ? GRAY : diverge(g * 100, 20); },
              legend: [['#14571f', 'pieņem ≥ +20%'], ['#57a95d', 'nedaudz aug'],
                       ['#e06f6f', 'nedaudz sarūk'], ['#7d1414', 'atlaiž ≤ −20%'], [GRAY, 'nav datu']],
              panel: c => { const g = empGrowth(c);
                  return ['Darbinieki pērn', c.e2 > 0 ? fmtCount(c.e2) + (g === null ? '' : ' (' + signPct(g * 100) + ')') : '—']; } },
        noz: { label: 'Nozare', csIdx: 7, mosaic: null, // mozaīka = sekcijas tonis
              color: (c, div) => div.secColor,
              legend: 'sections', panel: null },
        alga: { label: 'Algu līmenis', csIdx: 8, mosaic: ['#2f8f3e', '#c22f2f'],
              color: c => (c.s > 0 && medianS > 0) ? diverge(c.s / medianS - 1, 1) : GRAY,
              legend: [['#14571f', '≥ 2× mediāna'], ['#57a95d', 'virs mediānas'],
                       ['#e06f6f', 'zem mediānas'], ['#7d1414', '≤ ½ mediānas'], [GRAY, 'nav datu']],
              panel: c => ['Alga pret mediānu', (c.s > 0 && medianS > 0)
                  ? '×' + fmtNum(c.s / medianS, 2) + ' (mediāna ' + fmtCount(medianS) + ' €)' : '—'] },
        prod: { label: 'Produktivitāte', csIdx: 9, mosaic: ['#2f8f3e', '#c22f2f'],
              color: c => (empRep(c) > 0) ? diverge(c.p / empRep(c), 30000) : GRAY,
              legend: [['#14571f', 'peļņa ≥ 30 tūkst. €/darb.'], ['#57a95d', 'peļņa uz darbinieku'],
                       ['#e06f6f', 'zaudējumi uz darbinieku'], [GRAY, 'nav darbinieku datu']],
              panel: c => ['Peļņa uz darbinieku', empRep(c) > 0 ? signEur(c.p / empRep(c)) : '—'] },
        vec: { label: 'Vecums', csIdx: 10, mosaic: ['#339af0', '#0b3a66'],
              color: c => { const a = c.age;
                  if (a === null || a === undefined) return GRAY;
                  return a < 3 ? '#4dabf7' : a < 10 ? '#1c7ed6' : a < 25 ? '#1259a3' : '#0b3a66'; },
              legend: [['#4dabf7', '< 3 gadi'], ['#1c7ed6', '3–10'], ['#1259a3', '10–25'],
                       ['#0b3a66', '≥ 25'], [GRAY, 'nav datuma']],
              panel: c => ['Vecums', (c.age !== null && c.age !== undefined) ? fmtCount(c.age) + ' gadi' : '—'] },
        reit: { label: 'VID reitings', csIdx: 11, mosaic: ['#2b8a3e', '#e03131'],
              color: c => c.rtg ? (RTG_COLOR[c.rtg] || GRAY) : GRAY,
              legend: [['#2b8a3e', 'A laba'], ['#f08c00', 'B jāuzlabo'], ['#e03131', 'C pārkāpumi'],
                       ['#339af0', 'J jauns'], ['#5c636a', 'N neaktīvs'], [GRAY, 'nav reitinga']],
              panel: c => ['VID reitings', c.rtg ? (RTG_TEXT[c.rtg] || c.rtg) : '—'] },
        risks: { label: 'Riski', csIdx: 12, mosaic: ['#2f9e44', '#e03131'],
              color: c => { const r = c.rsk || 0;
                  if (!r) return '#2f9e44';
                  if (r & 4) return '#701010';
                  if (r & 1) return '#e03131';
                  return '#e8590c'; },
              legend: [['#2f9e44', 'bez pazīmēm'], ['#e8590c', 'nodrošinājuma līdzekļi'],
                       ['#e03131', 'darbība apturēta'], ['#701010', 'maksātnespējas process']],
              panel: c => { const r = c.rsk || 0;
                  if (!r) return ['Riska pazīmes', 'nav'];
                  const parts = [];
                  if (r & 4) parts.push('maksātnespējas process');
                  if (r & 1) parts.push('darbība apturēta');
                  if (r & 2) parts.push('nodrošinājuma līdzekļi');
                  return ['Riska pazīmes', parts.join(', ')]; } },
        svaig: { label: 'Svaigums', csIdx: 13, mosaic: ['#2b8a3e', '#d9480f'],
              color: c => { const d = newestYear - c.y;
                  return d <= 0 ? '#2b8a3e' : d === 1 ? '#f5a623' : '#d9480f'; },
              legend: 'freshness', panel: null },
    };
    let colorKey = 'pz';

    // --- APRĒĶINU APRAKSTI (rāda zem izvēles pogām) ---
    const METRIC_DESC = {
        t: 'Bloka izmērs = neto apgrozījums (eiro) no uzņēmuma jaunākā gada pārskata (pēdējie 3 pārskatu gadi).',
        p: 'Bloka izmērs = peļņas vai zaudējumu APJOMS |neto peļņa| eiro no jaunākā pārskata — liels sarkans bloks nozīmē lielus zaudējumus. Zīmi rāda krāsa.',
        a: 'Bloka izmērs = bilances kopsumma "Aktīvi kopā" (eiro) no jaunākā gada pārskata.',
        k: 'Bloka izmērs = UR reģistrētais apmaksātais pamatkapitāls (jaunākais ieraksts; vēsturiskie LVL pārrēķināti eiro ar kursu 1,42288).',
        m: 'Bloka izmērs = maržas LIELUMS: |peļņa ÷ apgrozījums| × 100%, ierobežots līdz 300%. Maržas zīmi (peļņa vai zaudējumi) rāda krāsa.',
        e: () => 'Bloka izmērs = darbinieku skaits pēc SVAIGĀKAJIEM VID datiem'
            + (empPeriod ? ' (' + empPeriod + ')' : '')
            + '. Ja uzņēmuma tajā ceturksnī nav — vidējais skaits no gada pārskata, pēc tam VID gada vidējais.',
        s: () => 'Bloka izmērs = vidējā bruto alga €/mēnesī pēc VID CETURKŠŅU datiem'
            + (salaryPeriod ? ' (' + salaryPeriod + ')' : '')
            + '. Aprēķins: ceturkšņu VSAOI iemaksu summa ÷ 34,09% ÷ nostrādātie darbinieku-mēneši.'
            + ' Vairāki ceturkšņi kopā, jo viens pats ceturksnis ir troksnains (prēmijas, sezonalitāte).'
            + ' Ja ceturkšņu datu nav — VID gada dati ÷ 12.',
        bk: 'Bloka izmērs = saistības kopā (kreditori): ilgtermiņa + īstermiņa saistības + nākamo periodu ieņēmumi no jaunākā pārskata bilances.',
    };
    BAL_ITEMS.forEach((label, i) => {
        METRIC_DESC['b' + i] = 'Bloka izmērs = bilances ' + (i <= 8 ? 'aktīvu' : 'pasīvu')
            + ' postenis "' + label + '" (eiro) no uzņēmuma jaunākā gada pārskata.';
    });

    const COLOR_DESC = {
        pz: 'Krāsa = jaunākā pārskata rezultāta zīme: peļņa (neto peļņa ≥ 0) — zaļš, zaudējumi — sarkans.',
        marza: 'Marža = peļņa ÷ apgrozījums × 100%. Tonis kļūst piesātinātāks līdz ±20%: tumši zaļš ≥ +20%, tumši sarkans ≤ −20%.',
        likvid: 'Likviditātes koeficients = apgrozāmie līdzekļi ÷ īstermiņa saistības (no bilances). Koeficients ≥ 1 nozīmē, ka īstermiņa saistības ir pilnībā segtas ar apgrozāmajiem līdzekļiem.',
        parads: 'Parādu slogs = saistības kopā (ilgtermiņa + īstermiņa + nākamo periodu) ÷ aktīvi kopā × 100%. Virs 100% saistības pārsniedz visus aktīvus.',
        pk: 'Pašu kapitāls no bilances pasīvu puses. Negatīvs (violets) = uzkrātie zaudējumi pārsniedz ieguldīto kapitālu — uzņēmums "zem ūdens".',
        izaug: 'Apgrozījuma izmaiņa pret TIEŠI iepriekšējā gada pārskatu: (šī gada apgrozījums − pērnā) ÷ pērnā × 100%. Tonis intensificējas līdz ±30%.',
        pavers: 'Salīdzina peļņas/zaudējumu zīmi šī gada un tieši iepriekšējā gada pārskatā — četras kategorijas no "stabili plusos" līdz "stabili mīnusos".',
        darb: 'Darbinieku skaita izmaiņa starp diviem gada pārskatiem: (jaunākā pārskata − pērnā) ÷ pērnā × 100%. Tonis intensificējas līdz ±20%. Šeit apzināti lieto PĀRSKATA, nevis svaigāko ceturkšņa skaitu, lai abas puses būtu no viena avota un perioda.',
        noz: 'Katrai NACE sekcijai savs pastāvīgs tonis — palīdz orientēties dziļā tuvinājumā, kur nozaru robežas vairs nav redzamas.',
        alga: () => 'Uzņēmuma vidējā bruto alga pret visu kartes uzņēmumu mediānu (' + fmtCount(medianS)
            + ' €/mēn): zaļš — virs mediānas, sarkans — zem. Tonis intensificējas līdz ±2×.',
        prod: 'Produktivitāte = peļņa ÷ darbinieku skaits (eiro uz darbinieku gadā). Abi lielumi no viena un tā paša pārskata gada. Tonis intensificējas līdz ±30 tūkst. €.',
        vec: 'Uzņēmuma vecums = pilni gadi kopš reģistrācijas Uzņēmumu reģistrā. Gaišāks zils — jaunāks uzņēmums.',
        reit: 'VID publiskais nodokļu maksātāju reitings: A — laba saistību izpilde, B — jāuzlabo, C — pārkāpumi, J — jaunreģistrēts, N — neaktīvs nodokļu maksātājs.',
        risks: 'UR reģistrētās riska pazīmes prioritārā secībā: aktīvs maksātnespējas process → saimnieciskās darbības apturēšana → nodrošinājuma līdzekļi. Zaļš — nevienas pazīmes.',
        svaig: () => 'Jaunākā iesniegtā gada pārskata gads: ' + newestYear + '. gads — zaļš, gadu vecāks — dzintara, vēl vecāks — sarkans. Rāda, cik aktuāli ir bloka finanšu dati.',
    };

    // --- DOM ---
    const wrap = document.getElementById('tm-wrap');
    const canvas = document.getElementById('tm-canvas');
    const ctx = canvas.getContext('2d');
    const crumbsEl = document.getElementById('tm-crumbs');
    const contextEl = document.getElementById('tm-context');
    const statsEl = document.getElementById('tm-stats');
    const loadingEl = document.getElementById('tm-loading');
    const backBtn = document.getElementById('tm-back');
    const panel = document.getElementById('tm-panel');
    const panelBody = document.getElementById('tm-panel-body');
    const panelClose = document.getElementById('tm-panel-close');
    const metricBtns = document.querySelectorAll('#tm-metrics > button[data-m]');
    const ddBtn = document.getElementById('tm-dd-btn');
    const ddMenu = document.getElementById('tm-dd-menu');
    const ddItems = document.querySelectorAll('#tm-dd-menu button[data-m]');
    const colorBtns = document.querySelectorAll('#tm-colors button[data-c]');
    const legendChipsEl = document.getElementById('tm-legend-chips');
    const sizeDescEl = document.getElementById('tm-size-desc');
    const colorDescEl = document.getElementById('tm-color-desc');
    const filterRowsEl = document.getElementById('tm-filter-rows');
    const filterAddEl = document.getElementById('tm-filter-add');
    const filterDescEl = document.getElementById('tm-filter-desc');
    const filterCtlEl = document.getElementById('tm-filter-ctl');
    const searchInputEl = document.getElementById('tm-search-input');
    const searchResultsEl = document.getElementById('tm-search-results');

    const noticeContainer = document.querySelector('.data-source-notice');
    const noticeToggleButton = document.querySelector('.notice-toggle');
    if (noticeContainer && noticeToggleButton) {
        noticeToggleButton.addEventListener('click', () => {
            noticeContainer.classList.toggle('expanded');
            noticeToggleButton.textContent = noticeContainer.classList.contains('expanded') ? '−' : '+';
            resize();
        });
    }

    // --- STĀVOKLIS ---
    let W = 100, H = 100, dpr = window.devicePixelRatio || 1;
    let rootData = null;      // vienkāršais datu koks
    let layoutRoot = null;    // d3.hierarchy ar treemap koordinātām (pasaules telpā)
    let nodeByData = null;    // datu objekts -> hierarhijas mezgls (pārbūvē relayout)
    let transform = d3.zoomIdentity;
    let contextNode = null;   // mezgls, kas šobrīd "aizpilda" skatu (breadcrumb)
    let navData = null;       // pēdējais apzināti ietuvinātais (datu objekts, pārdzīvo relayout)
    let selected = null;      // {data: uzņēmuma objekts, prevT: transform pirms piezūmēšanās}
    let inflight = 0;
    const loadQueue = [];
    let drawQueued = false;
    let retilePending = false; // zoom mainīja k -> jāpārkārto (adaptīvā virsraksta josla)
    let lastTileK = 1;         // k, pie kura pēdējoreiz pārkārtots izkārtojums
    // Fona pilnā ielāde: pēc pārskata renderēšanas ielādē VISU nodaļu div_XX.json,
    // lai katra nozare rāda individuālus uzņēmumus (ne "atlikuma" mozaīku). Relayout
    // droseļots, lai daudzās ielādes nesagāž kadru ātrumu.
    let eagerLoading = false;
    let eagerPending = 0;
    let eagerRelayoutTimer = null;

    // --- IZMĒRS ---
    function resize() {
        const rect = wrap.getBoundingClientRect();
        W = Math.max(320, rect.width);
        H = Math.max(420, window.innerHeight - rect.top - 8);
        wrap.style.height = H + 'px';
        dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(W * dpr);
        canvas.height = Math.round(H * dpr);
        canvas.style.width = W + 'px';
        canvas.style.height = H + 'px';
        if (rootData) { applyZoomExtent(); relayout(); requestDraw(); scheduleLoadCheck(); }
    }

    // --- FORMATĒŠANA ---
    function fmtNum(v, d) {
        return v.toLocaleString('lv-LV', { minimumFractionDigits: d, maximumFractionDigits: d });
    }
    function fmtEur(v) {
        const a = Math.abs(v);
        if (a >= 1e9) return fmtNum(v / 1e9, 1) + ' mljrd. €';
        if (a >= 1e6) return fmtNum(v / 1e6, 1) + ' milj. €';
        if (a >= 1e4) return fmtNum(v / 1e3, 0) + ' tūkst. €';
        return fmtNum(v, 0) + ' €';
    }
    function signEur(v) { return (v >= 0 ? '+' : '−') + fmtEur(Math.abs(v)); }
    function fmtPct(v) { return (v >= 0 ? '+' : '−') + fmtNum(Math.abs(v), 1) + '%'; }
    function fmtCount(n) { return n.toLocaleString('lv-LV'); }

    function metric() { return METRICS[metricKey]; }

    // --- DATU KOKS ---
    function makeCompany(row) { // overview TOP rinda: [reg,name,t,p,a,k,e,s,y,bal,t2,p2,e2,age,rtg,rsk,rg,fm,vat,ef]
        return { kind: 'c', reg: row[0], name: row[1], t: row[2], p: row[3],
                 a: row[4], k: row[5], e: row[6], s: row[7], y: row[8], b: row[9],
                 t2: row[10], p2: row[11], e2: row[12], age: row[13], rtg: row[14], rsk: row[15],
                 rg: row[16], fm: row[17], vat: row[18], ef: row[19], vq: row[20] };
    }
    function buildTree(ov) {
        const sections = new Map();
        ov.sections.forEach((s, i) => {
            // zelta leņķa toņi — blakus sekcijas dabū maksimāli atšķirīgas krāsas ("Nozare" režīmam)
            const color = d3.hsl((i * 137.508) % 360, 0.55, 0.42).formatHex();
            sections.set(s.code, { kind: 'sec', code: s.code, name: s.name, color, children: [] });
        });
        ov.divisions.forEach(dv => {
            const div = {
                kind: 'div', code: dv.code, name: dv.name, sec: dv.sec, n: dv.n,
                tot: dv.tot, cs: dv.cs || [],
                secColor: sections.get(dv.sec).color,
                seed: dv.code.charCodeAt(0) * 131 + dv.code.charCodeAt(1) * 17,
                top: dv.top.map(makeCompany),
                all: null, classes: null,
                loaded: false, loading: false, failed: false,
                restNode: { kind: 'rest', val: 0, n: 0, div: null },
                children: [], curN: dv.n,
            };
            div.restNode.div = div;
            sections.get(dv.sec).children.push(div);
        });
        return { kind: 'root', name: 'Latvija', children: [...sections.values()] };
    }

    // --- FILTRU SISTĒMA ---
    // Katrs filtrs: 'range' (min–max ievades) vai 'multi' (izvēles čipi; 'search: true' pievieno
    // meklēšanas lauku garam sarakstam). Visi pievienotie filtri darbojas REIZĒ (UN nosacījums).
    // 'sec' (nozares) ir nodaļu līmeņa filtrs — kopsummas paliek precīzas, atlikuma bloki paliek;
    // pārējie ir uzņēmumu līmeņa: aktīvi => atlikuma bloki nost (nezinām slēpto uzņēmumu sastāvu)
    // un neielādētās nodaļās redzami tikai TOP uzņēmumi, līdz nodaļa tiek pietuvināta/ielādēta.
    let regionsList = [], formsList = []; // vārdnīcas no overview.json
    const FILTER_DEFS = {
        emp:   { label: 'Darbinieku skaits', type: 'range', get: c => c.e },
        t:     { label: 'Apgrozījums, €', type: 'range', get: c => c.t },
        s:     { label: 'Bruto alga, €/mēn', type: 'range', get: c => c.s },
        izaug: { label: 'Izaugsme, %', type: 'range',
                 get: c => { const g = growth(c); return g === null ? null : g * 100; } },
        marza: { label: 'Marža, %', type: 'range', get: c => c.t > 0 ? c.p / c.t * 100 : null },
        vec:   { label: 'Vecums, gadi', type: 'range', get: c => c.age },
        pz:    { label: 'Peļņa / zaudējumi', type: 'multi', get: c => c.p >= 0 ? 'p' : 'z',
                 options: () => [['p', 'Pelnošie'], ['z', 'Zaudējumos']] },
        pk:    { label: 'Pašu kapitāls', type: 'multi',
                 get: c => (c.b && c.b[9] !== null && c.b[9] !== undefined) ? (c.b[9] > 0 ? 'poz' : 'neg') : 'nav',
                 options: () => [['poz', 'Pozitīvs'], ['neg', 'Negatīvs ("zem ūdens")'], ['nav', 'Nav datu']] },
        reit:  { label: 'VID reitings', type: 'multi', get: c => c.rtg || 'nav',
                 options: () => [['A', 'A — laba'], ['B', 'B — jāuzlabo'], ['C', 'C — pārkāpumi'],
                                 ['J', 'J — jauns'], ['N', 'N — neaktīvs'], ['nav', 'Bez reitinga']] },
        risk:  { label: 'Riska pazīmes', type: 'multi', get: c => (c.rsk || 0) ? 'ir' : 'nav',
                 options: () => [['nav', 'Bez pazīmēm'], ['ir', 'Ar pazīmēm']] },
        gads:  { label: 'Pārskata gads', type: 'multi',
                 get: c => c.y >= newestYear ? 'n' : String(c.y),
                 options: () => [['n', newestYear + '. (svaigākie)'],
                                 [String(newestYear - 1), (newestYear - 1) + '.'],
                                 [String(newestYear - 2), (newestYear - 2) + '.']] },
        pvn:   { label: 'PVN maksātājs', type: 'multi', get: c => c.vat ? 'ir' : 'nav',
                 options: () => [['ir', 'Aktīvs PVN maksātājs'], ['nav', 'Nav PVN reģistra']] },
        forma: { label: 'Juridiskā forma', type: 'multi',
                 get: c => (c.fm === null || c.fm === undefined) ? 'nav' : String(c.fm),
                 options: () => formsList.map((n, i) => [String(i), n]).concat([['nav', 'Nezināma']]) },
        reg:   { label: 'Reģions', type: 'multi', search: true,
                 get: c => (c.rg === null || c.rg === undefined) ? 'nav' : String(c.rg),
                 options: () => regionsList.map((n, i) => [String(i), n])
                     .sort((a, b) => a[1].localeCompare(b[1], 'lv')).concat([['nav', '— nav adreses datu']]) },
        sec:   { label: 'Nozares (sekcijas)', type: 'multi', search: true, divLevel: true, get: null,
                 options: () => rootData ? rootData.children.map(s => [s.code, s.code + ' — ' + s.name]) : [] },
    };
    const filterState = new Map(); // key -> {min,max} | Set (pievienošanas secībā)

    function filterConstrains(key, st) { // vai rindai ir reāls nosacījums
        return FILTER_DEFS[key].type === 'range' ? (st.min !== null || st.max !== null) : st.size > 0;
    }
    function companyFiltersActive() {
        for (const [key, st] of filterState) {
            if (!FILTER_DEFS[key].divLevel && filterConstrains(key, st)) return true;
        }
        return false;
    }
    function anyFilterActive() {
        for (const [key, st] of filterState) if (filterConstrains(key, st)) return true;
        return false;
    }
    function secFilterSet() {
        const st = filterState.get('sec');
        return (st && st.size) ? st : null;
    }
    function companyPass(c) {
        for (const [key, st] of filterState) {
            const def = FILTER_DEFS[key];
            if (def.divLevel) continue;
            if (def.type === 'range') {
                if (st.min === null && st.max === null) continue;
                const v = def.get(c);
                if (v === null || v === undefined) return false; // bez datiem filtrā nepiedalās
                if (st.min !== null && v < st.min) return false;
                if (st.max !== null && v > st.max) return false;
            } else {
                if (!st.size) continue;
                if (!st.has(def.get(c))) return false;
            }
        }
        return true;
    }

    // Pārbūvē koka bērnus pēc pašreizējās metrikas + filtriem: nerādāmie (bez datiem/nulle) izkrīt;
    // neielādētās nodaļās TOP + atlikuma mezgls (vērtība = nodaļas kopsumma mīnus TOP).
    function applyMetric() {
        const mget = metric().get;
        const cActive = companyFiltersActive();
        const secSet = secFilterSet();
        const valid = c => mget(c) > 0 && companyPass(c);
        rootData.children.forEach(sec => {
            const secOn = !secSet || secSet.has(sec.code);
            sec.children.forEach(div => {
                if (!secOn) { div.children = []; div.curN = 0; return; }
                if (div.loaded) {
                    if (div.classes) {
                        div.classes.forEach(cls => { cls.children = cls.all.filter(valid); });
                        div.children = div.classes.filter(cls => cls.children.length);
                    } else {
                        div.children = div.all.filter(valid);
                    }
                    div.curN = div.children.reduce((s, ch) => s + (ch.kind === 'cls' ? ch.children.length : 1), 0);
                } else {
                    const validTop = div.top.filter(valid);
                    let topSum = 0;
                    div.top.forEach(c => { topSum += mget(c); });
                    const restVal = Math.max(0, divTotal(div) - topSum);
                    div.restNode.val = restVal;
                    div.restNode.n = Math.max(0, div.n - div.top.length);
                    div.children = !cActive && restVal > 0 && div.restNode.n > 0
                        ? [...validTop, div.restNode] : validTop;
                    div.curN = cActive ? validTop.length : div.n;
                }
            });
        });
    }

    // ADAPTĪVĀ virsraksta josla: rezervē lentei tik LAYOUT vietas, lai EKRĀNĀ tā vienmēr būtu
    // tieši lentes augstumā (pad = hdrH / k). Tāpēc pietuvinot josla NEAUG (nav pelēko tukšo
    // joslu), attālinot lente NEPĀRKLĀJ uzņēmumus. Lente rādāma tikai mezgliem, kas EKRĀNĀ ir
    // pietiekami lieli (slieksnis pēc ekrāna izmēra → adaptīvs). n._pad saglabā rezervi zīmēšanai.
    function headerPad(n) {
        n._pad = 0;
        const kind = n.data.kind;
        if (kind !== 'sec' && kind !== 'div') return 0;
        const k = transform.k;
        const sw = (n.x1 - n.x0) * k, sh = (n.y1 - n.y0) * k;
        let hdr = 0;
        if (kind === 'sec') { if (sw >= 80 && sh >= 44) hdr = SEC_HDR_H; }
        else { if (sw >= 110 && sh >= 46) hdr = DIV_HDR_H; }
        if (!hdr) return 0;
        return (n._pad = hdr / k);
    }

    // Pārkārto tikai izkārtojumu (tos pašus mezglu objektus) ar pašreizējam k pielāgotu joslu —
    // lieto zoom laikā, lai josla ekrānā paliek nemainīga. Nepārbūvē hierarhiju/nodeByData.
    function tileLayout() {
        d3.treemap().size([W, H]).paddingInner(0).round(false).paddingTop(headerPad)(layoutRoot);
        lastTileK = transform.k;
    }

    function relayout() {
        layoutRoot = d3.hierarchy(rootData, d => d.children && d.children.length ? d.children : undefined)
            .sum(d => d.kind === 'c' ? metric().get(d) : (d.kind === 'rest' ? d.val : 0))
            .sort((a, b) => b.value - a.value);
        tileLayout();
        nodeByData = new Map();
        layoutRoot.each(n => nodeByData.set(n.data, n));
        layoutRoot.eachAfter(n => {
            n.leafN = n.children ? n.children.reduce((s, c) => s + c.leafN, 0)
                : (n.data.kind === 'rest' ? n.data.n
                    : (n.data.kind === 'c' ? 1 : 0)); // tukša nodaļa/sekcija nav uzņēmums
        });
        // izfiltrēts ārā (offMap selekcija zina, ka mezgla nav — to nedzēšam)
        if (selected && !selected.offMap && !nodeByData.has(selected.data)) clearSelection(false);
    }

    // --- LAZY IELĀDE ---
    // Droseļots relayout fona pilnajai ielādei — daudzas ielādes -> retāki relayout.
    function eagerScheduleRelayout() {
        if (eagerRelayoutTimer) return;
        eagerRelayoutTimer = setTimeout(() => {
            eagerRelayoutTimer = null;
            applyMetric(); relayout(); requestDraw();
        }, 400);
    }
    // Ielādē VISU nodaļu pilnos datus fonā (pēc pārskata renderēšanas), lai katra nozare
    // rāda individuālus uzņēmumus, ne "atlikuma" mozaīku.
    function eagerLoadAll() {
        if (!rootData) return;
        const divs = rootData.children.flatMap(s => s.children)
            .filter(d => !d.loaded && !d.loading && !d.failed);
        if (!divs.length) return;
        eagerLoading = true;
        eagerPending = divs.length;
        divs.forEach(queueLoad);
    }
    function queueLoad(div) {
        if (div.loaded || div.loading || div.failed) return;
        div.loading = true;
        loadQueue.push(div);
        pumpQueue();
    }
    function pumpQueue() {
        while (inflight < MAX_PARALLEL && loadQueue.length) {
            const div = loadQueue.shift();
            inflight++;
            loadingEl.classList.remove('hidden');
            fetch('struktura/data/div_' + div.code + '.json?v=' + TM_DATA_V)
                .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(json => {
                    // TOP eksemplārus atkārtoti izmanto — selekcija/navigācija pārdzīvo ielādi
                    const byReg = new Map(div.top.map(c => [c.reg, c]));
                    const byCls = new Map();
                    json.companies.forEach(row => { // [reg,name,t,p,a,k,e,s,cls,y,bal,t2,p2,e2,age,rtg,rsk,rg,fm,vat,ef]
                        let comp = byReg.get(row[0]);
                        if (!comp) {
                            comp = { kind: 'c', reg: row[0], name: row[1], t: row[2], p: row[3],
                                     a: row[4], k: row[5], e: row[6], s: row[7], y: row[9], b: row[10],
                                     t2: row[11], p2: row[12], e2: row[13], age: row[14], rtg: row[15], rsk: row[16],
                                     rg: row[17], fm: row[18], vat: row[19], ef: row[20], vq: row[21] };
                        }
                        comp.cls = row[8];
                        if (!byCls.has(row[8])) byCls.set(row[8], []);
                        byCls.get(row[8]).push(comp);
                    });
                    if (byCls.size <= 1) { // viena klase = lieks apvalks
                        div.all = [...byCls.values()].flat();
                        div.classes = null;
                    } else {
                        div.classes = [...byCls.entries()].map(([code, comps]) => ({
                            kind: 'cls', code, name: json.classes[code] || code,
                            all: comps, children: [],
                        }));
                        div.all = json.companies.length ? [...byCls.values()].flat() : [];
                    }
                    div.loaded = true; div.loading = false;
                    if (eagerLoading) {
                        // Fona pilnā ielāde — relayout droseļots (progresīva aizpildīšanās),
                        // pēdējai ielādei viens galīgais relayout.
                        eagerPending--;
                        if (eagerPending <= 0) {
                            eagerLoading = false;
                            if (eagerRelayoutTimer) { clearTimeout(eagerRelayoutTimer); eagerRelayoutTimer = null; }
                            applyMetric(); relayout(); requestDraw();
                            if (anyFilterActive()) updateFilterDesc();
                        } else {
                            eagerScheduleRelayout();
                        }
                        if (div.onLoaded) { const f = div.onLoaded; div.onLoaded = null; f(); }
                    } else {
                        applyMetric(); relayout();
                        // izkārtojums nodaļas iekšpusē mainījās — pārcentrē izvēlēto uzņēmumu
                        if (selected) {
                            const n = nodeByData.get(selected.data);
                            if (n) zoomToLeaf(n);
                        }
                        if (anyFilterActive()) updateFilterDesc(); // ielāde precizē redzamo skaitu
                        if (div.onLoaded) { const f = div.onLoaded; div.onLoaded = null; f(); } // meklēšanas fokuss
                        requestDraw();
                    }
                })
                .catch(() => { div.loading = false; div.failed = true; })
                .finally(() => {
                    inflight--;
                    if (!inflight && !loadQueue.length) loadingEl.classList.add('hidden');
                    pumpQueue();
                });
        }
    }

    // --- ETIĶEŠU LOĢIKA ---
    // Fonts pēc bloka izmēra: 1 rinda -> 2 rindas -> apcirsts ar "…" -> nekas; zem nosaukuma
    // metrikas vērtība, ja pietiek vietas. Aptuvenā burta platuma heiristika (0.6×fs), tad viena
    // precīza measureText pārbaude ar samazināšanu — tā etiķetes vienmēr ietilpst blokā.
    function splitTwo(name) {
        const mid = name.length / 2;
        let bestIdx = -1, bestDist = 1e9;
        for (let i = 0; i < name.length; i++) {
            if (name[i] === ' ' && Math.abs(i - mid) < bestDist) { bestDist = Math.abs(i - mid); bestIdx = i; }
        }
        if (bestIdx < 1) return null;
        return [name.slice(0, bestIdx), name.slice(bestIdx + 1)];
    }
    function drawLeafLabel(x, y, w, h, d) {
        const iw = w - 8, ih = h - 6;
        if (iw < 26 || ih < 11) return;
        const name = d.name;
        const len = Math.max(3, name.length);
        let fs = Math.min(24, ih * 0.62, iw / (0.6 * len));
        let lines = [name];
        if (fs < 9.5 && ih >= 24) {
            const parts = splitTwo(name);
            if (parts) {
                const ml = Math.max(parts[0].length, parts[1].length);
                const fs2 = Math.min(20, ih * 0.36, iw / (0.6 * ml));
                if (fs2 > fs) { fs = fs2; lines = parts; }
            }
        }
        if (fs < 9) {
            fs = 9;
            const maxCh = Math.floor(iw / (0.6 * 9));
            if (maxCh < 4) return;
            lines = [name.length > maxCh ? name.slice(0, maxCh - 1) + '…' : name];
        }
        fs = Math.max(9, Math.floor(fs));
        ctx.font = '600 ' + fs + 'px ' + FONT;
        let maxW = 0;
        lines.forEach(l => { maxW = Math.max(maxW, ctx.measureText(l).width); });
        if (maxW > iw) {
            const fs3 = Math.floor(fs * iw / maxW);
            if (fs3 >= 9) { fs = fs3; ctx.font = '600 ' + fs + 'px ' + FONT; }
            else {
                fs = 9; ctx.font = '600 9px ' + FONT;
                lines = lines.map(l => {
                    while (l.length > 3 && ctx.measureText(l + '…').width > iw) l = l.slice(0, -1);
                    return l + '…';
                });
                if (lines.length > 1) lines = [lines[0]];
            }
        }
        const showSub = ih > lines.length * fs * 1.2 + 13 && iw > 44;
        const subFs = Math.max(8, Math.min(12, Math.floor(fs * 0.78)));
        const blockH = lines.length * fs * 1.15 + (showSub ? subFs * 1.3 : 0);
        let ty = y + h / 2 - blockH / 2 + fs * 0.85;
        ctx.textAlign = 'center';
        ctx.fillStyle = 'rgba(255,255,255,0.97)';
        lines.forEach(l => { ctx.fillText(l, x + w / 2, ty); ty += fs * 1.15; });
        if (showSub) {
            ctx.font = '400 ' + subFs + 'px ' + FONT;
            ctx.fillStyle = 'rgba(255,255,255,0.82)';
            ctx.fillText(metric().leafFmt(d), x + w / 2, ty + subFs * 0.3);
        }
        ctx.textAlign = 'left';
    }

    // Virsraksta lente ir NEMAINĪGA augstuma (hdrH) un sēž rezervētās joslas augšā. Rezervētā
    // josla izkārtojumā = hdrH pie k=1 (pārskatā lente to aizpilda precīzi), bet ietuvinot tā aug
    // (hdrH×k) — pārpalikums paliek gaišs fons (kā treemap atstarpe), NEVIS smags krāsains bloks.
    // Uzņēmumu bloki vienmēr sākas zem rezervētās joslas, tāpēc lente tos nekad nepārklāj.
    // Kad mezgla augša aizritināta virs skata, lente pielīp skata augšai (konteksts paliek redzams).
    function pushHeaderBand(headers, r, hdrH, bg, fs, text) {
        if (r.w < 20) return;
        const h = Math.min(hdrH, r.h);
        const top = Math.min(Math.max(r.y, 0), r.y + r.h - h);
        headers.push({ x: r.x, y: top, w: r.w, h, bg, fs, text });
    }

    function drawHeaderStrip(x, y, w, hdrH, bg, text, fs) {
        ctx.fillStyle = bg;
        ctx.fillRect(x, y, w, hdrH);
        // teksts "pielīp" kreisajai skata malai, kad josla daļēji ārpus ekrāna
        const tx = Math.max(x, 0) + 6;
        const tw = Math.min(x + w, W) - tx - 6;
        if (tw < 20) return;
        ctx.font = '700 ' + fs + 'px ' + FONT;
        ctx.fillStyle = '#fff';
        let t = text;
        if (ctx.measureText(t).width > tw) {
            while (t.length > 2 && ctx.measureText(t + '…').width > tw) t = t.slice(0, -1);
            t += '…';
        }
        // teksts joslas augšdaļā: slaidai lentei — centrēts; augstai (ietuvinātai) — pie augšas
        ctx.fillText(t, tx, y + Math.min(hdrH / 2, 11) + fs * 0.36);
    }

    // Mozaīka — "orientējošais mazo uzņēmumu stāvoklis" neielādētām/blīvām zonām. Toņi un labā
    // toņa īpatsvars nāk no aktīvā krāsu režīma (nodaļas 'cs' daļa); "Nozare" režīmā — sekcijas
    // tonis. Deterministiska (sēkla no nodaļas koda), tāpēc raksts nemirgo starp kadriem.
    const toneCache = new Map();
    function tones(hex) {
        let t = toneCache.get(hex);
        if (!t) {
            const c = d3.color(hex);
            t = [hex, c.brighter(0.35).formatHex(), c.darker(0.35).formatHex(), c.brighter(0.7).formatHex()];
            toneCache.set(hex, t);
        }
        return t;
    }
    function mosaicSpec(divData) {
        const cm = COLOR_MODES[colorKey];
        const pair = cm.mosaic || [divData.secColor, divData.secColor];
        const share = (divData.cs && divData.cs[cm.csIdx] !== undefined) ? divData.cs[cm.csIdx] : 0.5;
        return [pair[0], pair[1], share];
    }
    function drawMosaic(x, y, w, h, divData, seedOff) {
        const [good, bad, share] = mosaicSpec(divData);
        if (w < 3 || h < 3) { // par mazu šūnām — viendabīgs sajaukums
            ctx.fillStyle = d3.interpolateRgb(bad, good)(share);
            ctx.fillRect(x, y, Math.max(w, 0.75), Math.max(h, 0.75));
            return;
        }
        const tg = tones(good), tb = tones(bad);
        const seed = divData.seed + (seedOff || 0);
        const cell = 9;
        const cols = Math.ceil(w / cell), rows = Math.ceil(h / cell);
        ctx.save();
        ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
        for (let j = 0; j < rows; j++) {
            for (let i = 0; i < cols; i++) {
                let hsh = (seed + i * 73856093 + j * 19349663) >>> 0;
                hsh = ((hsh ^ (hsh >>> 13)) * 0x5bd1e995) >>> 0;
                const r01 = ((hsh >>> 8) & 0xffff) / 0xffff;
                const shade = (hsh >>> 4) & 3;
                ctx.fillStyle = r01 < share ? tg[shade] : tb[shade];
                ctx.fillRect(x + i * cell, y + j * cell, cell - 1, cell - 1);
            }
        }
        ctx.restore();
    }

    // --- ZĪMĒŠANA ---
    function requestDraw() {
        if (drawQueued) return;
        drawQueued = true;
        requestAnimationFrame(() => {
            drawQueued = false;
            if (retilePending && layoutRoot) { retilePending = false; tileLayout(); }
            draw();
        });
    }

    function mapRect(n) {
        const k = transform.k;
        return {
            x: n.x0 * k + transform.x, y: n.y0 * k + transform.y,
            w: (n.x1 - n.x0) * k, h: (n.y1 - n.y0) * k,
        };
    }
    function offscreen(r) { return r.x > W || r.y > H || r.x + r.w < 0 || r.y + r.h < 0; }

    function aggText(node) {
        const d = node.data;
        const code = d.kind === 'sec' ? d.code + ' — ' : d.code + ' ';
        const name = d.kind === 'sec' ? d.name.toUpperCase() : d.name;
        return code + name + ' · ' + metric().aggFmt(node.value, node.leafN);
    }

    function draw() {
        if (!layoutRoot) return;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.fillStyle = COLOR.bg;
        ctx.fillRect(0, 0, W, H);
        ctx.textBaseline = 'alphabetic';

        const headers = []; // virsrakstu lentes zīmē pēc blokiem (rezervētajā joslā virs tiem)
        for (const sec of layoutRoot.children || []) {
            const sr = mapRect(sec);
            if (offscreen(sr)) continue;
            // Lente rādāma tikai mezgliem, kam headerPad rezervēja joslu (n._pad>0) — tā lente
            // aizpilda tieši rezervēto joslu (ekrānā = lentes augstums) un nekad nepārklāj blokus,
            // ne arī atstāj pelēku spraugu (rezervētā josla ir adaptīva pret k).
            for (const div of sec.children || []) {
                const dr = mapRect(div);
                if (offscreen(dr)) continue;
                drawDivision(div, dr);
                if (div._pad > 0 && (div.children || []).length) {
                    pushHeaderBand(headers, dr, DIV_HDR_H, COLOR.divHdr, 11, aggText(div));
                }
            }
            if (sec._pad > 0) {
                pushHeaderBand(headers, sr, SEC_HDR_H, COLOR.secHdr, 12, aggText(sec));
            }
        }
        headers.forEach(h => drawHeaderStrip(h.x, h.y, h.w, h.h, h.bg, h.text, h.fs));
        drawSelection();
        updateCrumbs();
    }

    function drawDivision(div, dr) {
        const d = div.data;
        // Tikai patiešām neredzamu nodaļu (< 3 px) zīmējam kā vienlaidu mozaīku.
        // Agrāk arī "blīvās" nodaļas (maz px uz uzņēmumu, pēc PILNĀ curN) tika slēptas aiz
        // rūtiņu paneļa — tāpēc mazās nozares nerādīja savus uzņēmumus. Tagad pārskatā katra
        // redzamā nodaļa zīmē savus TOP uzņēmumus + atlikuma mozaīku (kā lielās nozares).
        if (dr.w < 3 || dr.h < 3) {
            drawMosaic(dr.x, dr.y, dr.w, dr.h, d, 0);
            return;
        }
        for (const child of div.children || []) {
            if (child.data.kind === 'cls') drawClass(child, div);
            else drawLeaf(child, div);
        }
    }

    function drawClass(cls, div) {
        const cr = mapRect(cls);
        if (offscreen(cr)) return;
        if (cr.w < 2.5 || cr.h < 2.5) {
            drawMosaic(cr.x, cr.y, cr.w, cr.h, div.data, 7);
            return;
        }
        for (const leaf of cls.children || []) drawLeaf(leaf, div);
        if (cr.w >= 150 && cr.h >= 84) {
            const code = cls.data.code ? cls.data.code.replace(/^(\d\d)(\d+)$/, '$1.$2') + ' ' : '';
            const chipX = Math.max(cr.x + 1, 0); // lipīgs pie skata malām
            const chipY = Math.max(cr.y + 1, 0);
            const chipW = Math.min(Math.min(cr.x + cr.w, W) - chipX - 2, 320);
            if (chipW < 40) return;
            ctx.fillStyle = 'rgba(255,255,255,0.92)';
            ctx.fillRect(chipX, chipY, chipW, CLS_CHIP_H);
            ctx.font = '600 10.5px ' + FONT;
            ctx.fillStyle = '#263238';
            let t = code + cls.data.name;
            const maxW = chipW - 8;
            if (ctx.measureText(t).width > maxW) {
                while (t.length > 2 && ctx.measureText(t + '…').width > maxW) t = t.slice(0, -1);
                t += '…';
            }
            ctx.fillText(t, chipX + 4, chipY + CLS_CHIP_H - 4);
        }
    }

    function drawLeaf(leaf, div) {
        const r = mapRect(leaf);
        if (offscreen(r)) return;
        if (leaf.data.kind === 'rest') { // neielādētās nodaļas atlikums — mozaīka, ne pelēks bloks
            drawMosaic(r.x, r.y, r.w, r.h, div.data, 0);
            return;
        }
        if (r.w < 0.8 || r.h < 0.8) return;
        ctx.fillStyle = COLOR_MODES[colorKey].color(leaf.data, div.data);
        ctx.fillRect(r.x, r.y, r.w, r.h);
        if (r.w > 6 && r.h > 6) {
            ctx.strokeStyle = 'rgba(255,255,255,0.85)';
            ctx.lineWidth = 1;
            ctx.strokeRect(r.x + 0.5, r.y + 0.5, r.w - 1, r.h - 1);
        }
        drawLeafLabel(r.x, r.y, r.w, r.h, leaf.data);
    }

    function drawSelection() {
        if (!selected) return;
        const node = nodeByData.get(selected.data);
        if (!node) return;
        const r = mapRect(node);
        if (offscreen(r)) return;
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 5;
        ctx.strokeRect(r.x - 1.5, r.y - 1.5, r.w + 3, r.h + 3);
        ctx.strokeStyle = '#1c2733';
        ctx.lineWidth = 2.5;
        ctx.strokeRect(r.x - 1.5, r.y - 1.5, r.w + 3, r.h + 3);
    }

    // --- KONTEKSTS (breadcrumb) ---
    function coverage(n) {
        const r = mapRect(n);
        const ix = Math.max(0, Math.min(r.x + r.w, W) - Math.max(r.x, 0));
        const iy = Math.max(0, Math.min(r.y + r.h, H) - Math.max(r.y, 0));
        return (ix * iy) / (W * H);
    }
    function computeContext() {
        let node = layoutRoot, changed = true;
        while (changed) {
            changed = false;
            for (const c of node.children || []) {
                if (c.data.kind === 'c' || c.data.kind === 'rest') continue;
                if (coverage(c) >= 0.9) { node = c; changed = true; break; }
            }
        }
        // Apzināti ietuvinātais mezgls (klikšķis/crumb) ir konteksts arī tad, ja platuma/augstuma
        // dēļ tas neaizpilda visu skatu. Nedzēšam (pārejas animācijas sākumā pārklājums īslaicīgi
        // ir mazs) — vienkārši nelietojam, kamēr tas nav būtiski redzams.
        if (navData) {
            const nav = nodeByData.get(navData);
            if (nav && nav.depth > node.depth && coverage(nav) >= 0.3) return nav;
        }
        return node;
    }
    function crumbLabel(n) {
        if (n.data.kind === 'root') return 'Latvija';
        if (n.data.kind === 'cls') return n.data.name;
        if (n.data.kind === 'sec' || n.data.kind === 'div') return n.data.code + ' ' + n.data.name;
        return n.data.name;
    }
    function updateCrumbs() {
        const node = computeContext();
        if (node === contextNode) return;
        contextNode = node;
        crumbsEl.textContent = '';
        const path = node.ancestors().reverse();
        path.forEach((n, i) => {
            if (i) {
                const sep = document.createElement('span');
                sep.className = 'tm-sep';
                sep.textContent = '›';
                crumbsEl.appendChild(sep);
            }
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = crumbLabel(n);
            b.className = i === path.length - 1 ? 'tm-crumb tm-crumb-cur' : 'tm-crumb';
            b.addEventListener('click', () => { // caur karti — mezgls var būt no vecas hierarhijas
                const cur = nodeByData.get(n.data);
                if (cur) zoomToNode(cur);
            });
            crumbsEl.appendChild(b);
        });
        contextEl.textContent = fmtCount(node.leafN) + ' uzņēmumi · ' + metric().aggFmt(node.value, node.leafN);
        backBtn.disabled = node === layoutRoot;
    }

    // --- IELĀDES PĀRBAUDE PĒC NOSTABILIZĒŠANĀS ---
    // Zoom animācijas starpkadros nodaļas īslaicīgi izskatās lielas — ielādi ierosina
    // tikai tad, kad skats ~250 ms nav mainījies, un tikai nodaļām, kas reāli aizņem skatu.
    let loadCheckTimer = null;
    function scheduleLoadCheck() {
        clearTimeout(loadCheckTimer);
        loadCheckTimer = setTimeout(checkLoads, 250);
    }
    function checkLoads() {
        if (!layoutRoot) return;
        // Skatam nostabilizējoties — precīzs pārkārtojums (starp kadriem pieļauta <5% k nobīde).
        if (Math.abs(Math.log(transform.k / lastTileK)) > 0.001) { tileLayout(); requestDraw(); }
        for (const sec of layoutRoot.children || []) {
            for (const div of sec.children || []) {
                if (div.data.kind !== 'div') continue;
                if (!div.data.loaded && !div.data.loading && coverage(div) >= LOAD_COVERAGE) {
                    queueLoad(div.data);
                }
            }
        }
    }

    // --- ZOOM ---
    const zoom = d3.zoom()
        .scaleExtent([1, 60000])
        .translateExtent([[0, 0], [1e7, 1e7]]) // īsto robežu uzliek pēc izmēra
        .clickDistance(6)
        .on('zoom', (ev) => {
            transform = ev.transform;
            // Pārkārto tikai tad, kad k mainījies jūtami (>~5%) — starpkadros josla nobīdās <1px
            // (nemanāmi), tāpēc nav jāpārkārto katrā kadrā (92k mezglu tile ir dārgs).
            if (Math.abs(Math.log(transform.k / lastTileK)) > 0.05) retilePending = true;
            requestDraw();
            scheduleLoadCheck();
        });

    function applyZoomExtent() {
        zoom.translateExtent([[0, 0], [W, H]]);
    }

    function clampT(k, tx, ty) {
        tx = Math.min(0, Math.max(W - k * W, tx));
        ty = Math.min(0, Math.max(H - k * H, ty));
        return d3.zoomIdentity.translate(tx, ty).scale(k);
    }

    function zoomToNode(n) {
        const rw = n.x1 - n.x0, rh = n.y1 - n.y0;
        const k = Math.max(1, Math.min(60000, 0.96 * Math.min(W / rw, H / rh)));
        navData = n.data;
        d3.select(canvas).transition().duration(600).ease(d3.easeCubicInOut)
            .call(zoom.transform, clampT(k, W / 2 - k * (n.x0 + rw / 2), H / 2 - k * (n.y0 + rh / 2)));
    }

    // Piezūmēšanās izvēlētajam uzņēmumam: bloks ~40% no brīvās zonas (pa kreisi no paneļa).
    function zoomToLeaf(n) {
        const rw = n.x1 - n.x0, rh = n.y1 - n.y0;
        const freeW = Math.max(200, W - PANEL_W);
        const k = Math.max(1, Math.min(60000, 0.4 * Math.min(freeW / rw, H / rh)));
        d3.select(canvas).transition().duration(550).ease(d3.easeCubicInOut)
            .call(zoom.transform, clampT(k, freeW / 2 - k * (n.x0 + rw / 2), H / 2 - k * (n.y0 + rh / 2)));
    }

    function findByPoint(mx, my) {
        const wx = (mx - transform.x) / transform.k;
        const wy = (my - transform.y) / transform.k;
        let node = layoutRoot;
        while (node.children) {
            const hit = node.children.find(c => wx >= c.x0 && wx < c.x1 && wy >= c.y0 && wy < c.y1);
            if (!hit) break;
            node = hit;
        }
        return node;
    }

    d3.select(canvas).call(zoom).on('dblclick.zoom', null);

    // --- SELEKCIJA + DETAĻU PANELIS ---
    function panelRow(lbl, val, cls) {
        const row = document.createElement('div');
        row.className = 'tmp-row';
        const l = document.createElement('span');
        l.className = 'lbl';
        l.textContent = lbl;
        const v = document.createElement('span');
        v.className = 'val' + (cls ? ' ' + cls : '');
        v.textContent = val;
        row.append(l, v);
        return row;
    }
    // showPanel(node) — parastā selekcija; showPanel(null, off) — uzņēmums, kas pašreizējā izmēra
    // metrikā kartē NAV redzams (off = {data, div, cls, note}): panelis paliek atvērts ar
    // paskaidrojumu, lai pārslēdzot metriku neapjūk, kur uzņēmums pazuda.
    function showPanel(node, off) {
        const d = off ? off.data : node.data;
        let div = off ? off.div : null;
        let cls = off ? off.cls : null;
        if (!off) {
            const anc = node.ancestors().map(a => a.data);
            div = anc.find(a => a.kind === 'div');
            cls = anc.find(a => a.kind === 'cls');
        }
        panelBody.textContent = '';
        const nm = document.createElement('div');
        nm.className = 'tmp-name';
        nm.textContent = d.name;
        panelBody.appendChild(nm);
        const sub = document.createElement('div');
        sub.className = 'tmp-sub';
        sub.textContent = 'Reģ. nr. ' + d.reg
            + (div ? ' · ' + div.code + ' ' + div.name : '')
            + (cls ? ' · ' + cls.name : '');
        panelBody.appendChild(sub);
        const rows = document.createElement('div');
        rows.className = 'tmp-rows';
        rows.appendChild(panelRow('Apgrozījums', fmtEur(d.t)));
        rows.appendChild(panelRow(d.p >= 0 ? 'Peļņa' : 'Zaudējumi', signEur(d.p), d.p >= 0 ? 'tmp-pos' : 'tmp-neg'));
        rows.appendChild(panelRow('Marža', d.t > 0 ? fmtPct(d.p / d.t * 100) : '—'));
        rows.appendChild(panelRow('Aktīvi', d.a > 0 ? fmtEur(d.a) : '—'));
        rows.appendChild(panelRow('Pamatkapitāls', d.k > 0 ? fmtEur(d.k) : '—'));
        // Darbinieki un alga: vq=1 -> VID ceturkšņi (svaigāki par pārskatu), citādi gada dati.
        const qSrc = d.vq === 1;
        rows.appendChild(panelRow('Darbinieki' + (qSrc && empPeriod ? ' (' + empPeriod + ')' : ''),
            d.e > 0 ? fmtCount(d.e) : '—'));
        rows.appendChild(panelRow('Bruto alga' + (d.s > 0 ? (qSrc && salaryPeriod ? ' (' + salaryPeriod + ')' : ' (VID gada dati)') : ''),
            d.s > 0 ? fmtCount(d.s) + ' €/mēn.' : '—'));
        // ja izvēlēta bilances metrika, parāda arī tās vērtību (ieskaitot negatīvu pašu kapitālu)
        const m = metric();
        if (m.bal !== undefined) {
            const raw = d.b ? d.b[m.bal] : null;
            rows.appendChild(panelRow(m.label, raw !== null && raw !== undefined ? fmtEur(raw) : '—'));
        } else if (m.balSum) {
            const sum = balv(d, 11) + balv(d, 12) + balv(d, 13);
            rows.appendChild(panelRow(m.label, sum > 0 ? fmtEur(sum) : '—'));
        }
        // aktīvā krāsu režīma vērtība (izaugsme, likviditāte, reitings, riski u.c.)
        const cm = COLOR_MODES[colorKey];
        if (cm.panel) {
            const [lbl, val] = cm.panel(d);
            rows.appendChild(panelRow(lbl, val));
        }
        rows.appendChild(panelRow('Pārskata gads', String(d.y)));
        panelBody.appendChild(rows);
        if (off && off.note) {
            const nt = document.createElement('div');
            nt.className = 'tmp-note';
            nt.textContent = off.note;
            panelBody.appendChild(nt);
        }
        const a = document.createElement('a');
        a.className = 'tmp-link';
        a.href = '/' + d.reg;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = 'Atvērt sadaļā “Reģistrs” →';
        panelBody.appendChild(a);
        panel.classList.remove('hidden');
    }
    function clearSelection(zoomBack) {
        const prevT = selected ? selected.prevT : null;
        selected = null;
        panel.classList.add('hidden');
        if (zoomBack && prevT) {
            d3.select(canvas).transition().duration(500).ease(d3.easeCubicInOut)
                .call(zoom.transform, prevT);
        }
        requestDraw();
    }
    panelClose.addEventListener('click', () => clearSelection(true));

    canvas.addEventListener('click', (e) => {
        if (e.defaultPrevented) return; // bija panning
        if (!layoutRoot) return;
        const hit = findByPoint(e.offsetX, e.offsetY);
        if (!hit || hit === layoutRoot) { if (selected) clearSelection(false); return; }

        if (hit.data.kind === 'c') {
            if (selected && selected.data === hit.data) { clearSelection(true); return; } // ārā + atpakaļ
            const prevT = selected ? selected.prevT : transform; // ķēdē saglabā pirmsselekcijas skatu
            selected = { data: hit.data, prevT };
            showPanel(hit);
            zoomToLeaf(hit);
            requestDraw();
            return;
        }

        // ne-uzņēmums: aizver selekciju (bez zooma) un ienirst nākamajā līmenī
        if (selected) clearSelection(false);
        const path = hit.ancestors().reverse(); // root..hit
        const ctxDepth = (contextNode || layoutRoot).depth;
        let target = null;
        for (const n of path) {
            if (n.depth > ctxDepth && n.data.kind !== 'c' && n.data.kind !== 'rest') { target = n; break; }
        }
        if (!target) target = path.find(n => n.data.kind === 'div') || hit.parent;
        if (target && target !== layoutRoot) zoomToNode(target);
    });

    function zoomOut() {
        if (!layoutRoot) return;
        const cur = nodeByData.get((contextNode || layoutRoot).data) || layoutRoot;
        zoomToNode(cur.parent || layoutRoot);
    }
    backBtn.addEventListener('click', zoomOut);
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (!ddMenu.classList.contains('hidden')) { ddMenu.classList.add('hidden'); return; }
        if (selected) clearSelection(true);
        else zoomOut();
    });

    // --- METRIKU PĀRSLĒGS (pogas + izvēršamā "Bilance" izvēlne) ---
    // Paskaidrojums, kad izvēlētajam uzņēmumam pašreizējā metrikā nav attēlojama izmēra.
    function offMapNote() {
        return 'Ar rādītāju “' + METRICS[metricKey].label + '” šim uzņēmumam nav attēlojama izmēra '
            + '(nav datu, vērtība 0 vai negatīva), tāpēc kartē tas nav redzams. Pārējie dati paliek spēkā.';
    }
    function setActiveButtons() {
        metricBtns.forEach(x => x.classList.toggle('active', x.dataset.m === metricKey));
        ddItems.forEach(x => x.classList.toggle('active', x.dataset.m === metricKey));
        const isBal = METRICS[metricKey].bal !== undefined || !!METRICS[metricKey].balSum;
        ddBtn.classList.toggle('active', isBal);
        ddBtn.textContent = isBal ? 'Bilance: ' + METRICS[metricKey].label + ' ▾' : 'Bilance ▾';
    }
    function selectMetric(key) {
        if (key === metricKey || !rootData || !METRICS[key]) return;
        // Izvēlētais uzņēmums PĀRDZĪVO metrikas maiņu — tieši tāpēc metriku pārslēdz: lai redzētu
        // to pašu uzņēmumu citā rādītājā. Nodaļu/klasi paņemam PIRMS relayout (pēc tam mezgli mainās).
        let keep = null;
        if (selected) {
            const n = nodeByData.get(selected.data);
            const anc = n ? n.ancestors().map(a => a.data) : [];
            keep = { data: selected.data,
                     div: anc.find(a => a.kind === 'div') || selected.div || null,
                     cls: anc.find(a => a.kind === 'cls') || selected.cls || null };
        }
        metricKey = key;
        setActiveButtons();
        updateSizeDesc();
        selected = null; // bez clearSelection — paneli pārzīmēsim paši
        navData = null;
        contextNode = null;
        applyMetric();
        relayout();
        if (keep) {
            const node = nodeByData.get(keep.data);
            if (node) { // uzņēmums ir kartē arī ar jauno metriku — atlasīts un piezūmēts
                selected = { data: keep.data, prevT: d3.zoomIdentity };
                showPanel(node);
                zoomToLeaf(node);
                requestDraw();
                return;
            }
            // Jaunajā metrikā uzņēmumam nav vērtības (piem. bilances postenis bez datiem vai 0):
            // karti atgriežam pārskatā, bet paneli paturam ar godīgu paskaidrojumu.
            selected = { data: keep.data, prevT: d3.zoomIdentity, offMap: true,
                         div: keep.div, cls: keep.cls };
            showPanel(null, { data: keep.data, div: keep.div, cls: keep.cls, note: offMapNote() });
        } else {
            panel.classList.add('hidden');
        }
        d3.select(canvas).call(zoom.transform, d3.zoomIdentity); // atpakaļ uz pārskatu
        requestDraw();
    }
    metricBtns.forEach(b => b.addEventListener('click', () => selectMetric(b.dataset.m)));
    ddItems.forEach(b => b.addEventListener('click', () => {
        ddMenu.classList.add('hidden');
        selectMetric(b.dataset.m);
    }));
    ddBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        ddMenu.classList.toggle('hidden');
    });
    document.addEventListener('click', (e) => { // klikšķis ārpusē aizver izvēlni
        if (!ddMenu.classList.contains('hidden') && !ddMenu.contains(e.target) && e.target !== ddBtn) {
            ddMenu.classList.add('hidden');
        }
    });

    // --- KRĀSU PĀRSLĒGS + DINAMISKĀ LEĢENDA ---
    function updateLegend() {
        legendChipsEl.textContent = '';
        const cm = COLOR_MODES[colorKey];
        let items = cm.legend;
        if (items === 'sections' && rootData) { // nozaru toņi: rāda lielākās sekcijas
            items = rootData.children.slice(0, 9).map(s => [s.color, s.code]);
        } else if (items === 'freshness') {
            items = [['#2b8a3e', String(newestYear) + '. g. pārskats'],
                     ['#f5a623', String(newestYear - 1) + '. g.'],
                     ['#d9480f', '≤ ' + (newestYear - 2) + '. g.']];
        }
        (items || []).forEach(([col, label]) => {
            const sp = document.createElement('span');
            const chip = document.createElement('i');
            chip.className = 'tm-chip';
            chip.style.background = col;
            sp.appendChild(chip);
            sp.appendChild(document.createTextNode(label));
            legendChipsEl.appendChild(sp);
        });
        const dsc = COLOR_DESC[colorKey];
        colorDescEl.textContent = typeof dsc === 'function' ? dsc() : (dsc || '');
    }
    colorBtns.forEach(b => b.addEventListener('click', () => {
        if (b.dataset.c === colorKey || !COLOR_MODES[b.dataset.c]) return;
        colorKey = b.dataset.c;
        colorBtns.forEach(x => x.classList.toggle('active', x.dataset.c === colorKey));
        updateLegend();
        refreshPanel(); // panelī jāatjaunojas krāsu režīma rindai (izaugsme, likviditāte, ...)
        requestDraw(); // izkārtojums nemainās — tikai pārkrāso
    }));

    // Pārzīmē atvērto paneli ar pašreizējo metriku/krāsu režīmu (selekcija paliek).
    function refreshPanel() {
        if (!selected || panel.classList.contains('hidden')) return;
        if (selected.offMap) {
            showPanel(null, { data: selected.data, div: selected.div, cls: selected.cls,
                              note: offMapNote() });
            return;
        }
        const node = nodeByData.get(selected.data);
        if (node) showPanel(node);
    }

    function updateSizeDesc() {
        const mdsc = METRIC_DESC[metricKey];
        sizeDescEl.textContent = typeof mdsc === 'function' ? mdsc() : (mdsc || '');
    }
    updateSizeDesc();
    updateLegend();

    // --- FILTRU UI: pievienojamas/noņemamas rindas, visas darbojas reizē ---
    function updateFilterDesc() {
        let nAct = 0;
        for (const [key, st] of filterState) if (filterConstrains(key, st)) nAct++;
        filterCtlEl.classList.toggle('active', nAct > 0);
        if (!nAct) {
            filterDescEl.textContent = filterState.size
                ? 'Nosacījumi nav aizpildīti — rāda visus uzņēmumus.'
                : 'Pievieno filtrus — visi darbojas reizē.';
            return;
        }
        let visible = 0;
        if (layoutRoot) layoutRoot.leaves().forEach(l => { if (l.data.kind === 'c') visible++; });
        filterDescEl.textContent = 'Aktīvi ' + nAct + ' filtri · redzami ' + fmtCount(visible) + ' uzņēmumi.'
            + (companyFiltersActive()
                ? ' Neielādētās nozarēs redzami tikai lielākie — tuvini, lai ielādētu visus.' : '');
    }
    function applyFilters() {
        if (!rootData) return;
        clearSelection(false);
        applyMetric();
        relayout();
        requestDraw();
        scheduleLoadCheck();
        updateFilterDesc();
    }
    let filterTimer = null;
    function applyFiltersDebounced() {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(applyFilters, 350);
    }

    function addFilterRow(key) {
        if (filterState.has(key) || !FILTER_DEFS[key]) return;
        const def = FILTER_DEFS[key];
        filterState.set(key, def.type === 'range' ? { min: null, max: null } : new Set());

        const row = document.createElement('div');
        row.className = 'tm-frow';
        row.dataset.key = key;
        const head = document.createElement('div');
        head.className = 'tm-frow-head';
        const lbl = document.createElement('span');
        lbl.textContent = def.label;
        const rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'tm-frow-rm';
        rm.title = 'Noņemt filtru';
        rm.textContent = '×';
        rm.addEventListener('click', () => {
            filterState.delete(key);
            row.remove();
            refreshFilterAdd();
            applyFilters();
        });
        head.append(lbl, rm);
        row.appendChild(head);

        if (def.type === 'range') {
            const st = filterState.get(key);
            const body = document.createElement('div');
            body.className = 'tm-frow-range';
            const mkInput = (ph, setter) => {
                const inp = document.createElement('input');
                inp.type = 'number';
                inp.placeholder = ph;
                inp.addEventListener('input', () => {
                    const v = parseFloat(inp.value);
                    setter(isNaN(v) ? null : v);
                    applyFiltersDebounced();
                });
                return inp;
            };
            body.append('no ', mkInput('min', v => { st.min = v; }), ' līdz ', mkInput('maks.', v => { st.max = v; }));
            row.appendChild(body);
        } else {
            const st = filterState.get(key);
            const opts = def.options();
            let search = null;
            if (def.search && opts.length > 12) {
                search = document.createElement('input');
                search.type = 'search';
                search.className = 'tm-frow-search';
                search.placeholder = 'Meklēt…';
                row.appendChild(search);
            }
            const body = document.createElement('div');
            body.className = 'tm-frow-opts' + (opts.length > 12 ? ' tall' : '');
            opts.forEach(([val, label]) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'tm-fchip';
                chip.textContent = label;
                chip.dataset.label = label.toLowerCase();
                chip.addEventListener('click', () => {
                    if (st.has(val)) { st.delete(val); chip.classList.remove('on'); }
                    else { st.add(val); chip.classList.add('on'); }
                    applyFilters();
                });
                body.appendChild(chip);
            });
            if (search) {
                search.addEventListener('input', () => {
                    const q = search.value.trim().toLowerCase();
                    body.querySelectorAll('.tm-fchip').forEach(ch => {
                        ch.style.display = !q || ch.dataset.label.includes(q) ? '' : 'none';
                    });
                });
            }
            row.appendChild(body);
        }
        filterRowsEl.appendChild(row);
        refreshFilterAdd();
        updateFilterDesc();
    }

    function refreshFilterAdd() { // izvēlnē tikai vēl nepievienotie
        filterAddEl.textContent = '';
        const def0 = document.createElement('option');
        def0.value = '';
        def0.textContent = '+ Pievienot filtru…';
        filterAddEl.appendChild(def0);
        Object.entries(FILTER_DEFS).forEach(([key, def]) => {
            if (filterState.has(key)) return;
            const o = document.createElement('option');
            o.value = key;
            o.textContent = def.label;
            filterAddEl.appendChild(o);
        });
    }
    filterAddEl.addEventListener('change', () => {
        if (filterAddEl.value) addFilterRow(filterAddEl.value);
        filterAddEl.value = '';
    });

    // --- MEKLĒŠANA PĒC NOSAUKUMA / REĢ. NR. ---
    let searchIdx = null, searchIdxLoading = false;
    function ensureSearchIndex() {
        if (searchIdx || searchIdxLoading) return;
        searchIdxLoading = true;
        loadingEl.textContent = 'Ielādē meklēšanas indeksu…';
        loadingEl.classList.remove('hidden');
        fetch('struktura/data/search.json?v=' + TM_DATA_V)
            .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(rows => {
                searchIdx = rows.map(r => [r[0], r[1], r[2], r[1].toLowerCase()]);
                renderSearch();
            })
            .catch(() => {})
            .finally(() => {
                searchIdxLoading = false;
                loadingEl.classList.add('hidden');
                loadingEl.textContent = 'Ielādē…';
            });
    }
    function renderSearch() {
        const q = searchInputEl.value.trim().toLowerCase();
        searchResultsEl.textContent = '';
        if (!q || q.length < 2 || !searchIdx) {
            searchResultsEl.classList.add('hidden');
            return;
        }
        const starts = [], contains = [];
        for (const row of searchIdx) {
            if (row[3].startsWith(q) || row[0].startsWith(q)) starts.push(row);
            else if (row[3].includes(q)) contains.push(row);
            if (starts.length >= 12) break;
        }
        const hits = starts.concat(contains).slice(0, 12);
        if (!hits.length) {
            const d = document.createElement('div');
            d.className = 'tm-sr-empty';
            d.textContent = 'Nekas nav atrasts';
            searchResultsEl.appendChild(d);
        }
        hits.forEach(([reg, name, div]) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'tm-sr-item';
            const nm = document.createElement('span');
            nm.textContent = name;
            const rg = document.createElement('small');
            rg.textContent = reg;
            item.append(nm, rg);
            item.addEventListener('mousedown', (e) => { // mousedown — pirms input blur
                e.preventDefault();
                searchResultsEl.classList.add('hidden');
                searchInputEl.value = name;
                focusCompany(reg, div);
            });
            searchResultsEl.appendChild(item);
        });
        searchResultsEl.classList.remove('hidden');
    }
    function focusCompany(reg, divCode) {
        const divData = rootData.children.flatMap(s => s.children).find(d => d.code === divCode);
        if (!divData) return;
        const trySel = () => {
            const scan = arr => arr ? arr.find(c => c.reg === reg) : null;
            const comp = divData.loaded ? scan(divData.all) : scan(divData.top);
            if (!comp) return;
            const node = nodeByData.get(comp);
            if (!node) { // izfiltrēts vai 0 vērtība pašreizējā izmēra metrikā
                filterDescEl.textContent = '"' + comp.name + '" pašreizējā skatā nav redzams (filtri vai izmēra metrika bez datiem).';
                return;
            }
            selected = { data: comp, prevT: transform };
            showPanel(node);
            zoomToLeaf(node);
            requestDraw();
        };
        if (divData.loaded) trySel();
        else { divData.onLoaded = trySel; queueLoad(divData); }
    }
    searchInputEl.addEventListener('focus', ensureSearchIndex);
    searchInputEl.addEventListener('input', () => {
        ensureSearchIndex();
        renderSearch();
    });
    searchInputEl.addEventListener('blur', () => {
        setTimeout(() => searchResultsEl.classList.add('hidden'), 150);
    });

    // Atkļūdošanai (konsolē): pašreizējais transform/konteksts/ielādes stāvoklis.
    window.__tmDebug = function () {
        return {
            k: transform.k, x: transform.x, y: transform.y, W, H, metric: metricKey, color: colorKey,
            context: contextNode ? crumbLabel(contextNode) : null,
            selected: selected ? selected.data.name : null,
            loaded: rootData ? rootData.children.flatMap(s => s.children).filter(d => d.loaded).map(d => d.code) : [],
        };
    };

    // --- STARTS ---
    resize();
    window.addEventListener('resize', () => {
        clearTimeout(window.__tmRz);
        window.__tmRz = setTimeout(resize, 150);
    });

    fetch('struktura/data/overview.json?v=' + TM_DATA_V)
        .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(ov => {
            medianS = ov.medianS || 0;
            newestYear = ov.years[1];
            salaryPeriod = ov.salaryPeriod || '';
            empPeriod = ov.empPeriod || '';
            regionsList = ov.regions || [];
            formsList = ov.forms || [];
            rootData = buildTree(ov);
            refreshFilterAdd();
            addFilterRow('emp'); // darbinieku filtrs redzams uzreiz (kā līdz šim)
            statsEl.textContent = fmtCount(ov.totalN) + ' uzņēmumi · ' + fmtEur(ov.totalT)
                + ' apgrozījums · pārskati ' + ov.years[0] + '–' + ov.years[1];
            applyZoomExtent();
            applyMetric();
            relayout();
            updateLegend();
            requestDraw();
            scheduleLoadCheck();
            // Pēc pirmās zīmēšanas fonā ielādē VISU nozaru pilnos datus, lai atlikuma
            // mozaīka visur tiek aizstāta ar individuāliem uzņēmumiem (arī mazākajām firmām).
            setTimeout(eagerLoadAll, 300);
        })
        .catch(err => {
            loadingEl.textContent = 'Neizdevās ielādēt datus (' + err.message + '). Pārlādē lapu.';
            loadingEl.classList.remove('hidden');
        });
});
