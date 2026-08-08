// Investora memorands — "Personības lietošanas instrukcija" vadītāja skatā
// ─────────────────────────────────────────────────────────────────────────
// Koncentrāts no visas t3 cilnes darījuma valodā: verdikti pa lomām, ko cilvēks
// pelna / maksā, sviras, riski ar avotu konsensu, traucējummeklēšana un starts.
// NEĢENERĒ jaunus apgalvojumus — tikai savāc, pārtulko un sarindo esošo banku
// nosacījums→sekas tekstus. Konkrētība nāk no avotu konverģences, ne no izdomas.
//
// TONIS: apzināti ciniski-pragmatisks vadītāja skats ("ko es ar viņu iegūstu").
// Robeža: kā dabūt maksimumu un nepārmaksāt — JĀ; kā izmantot vājības pret
// cilvēku — NĒ. Korpusa toņa skenerim šis fails ir apzināts izņēmums.

import { D_BANDS, I_BANDS, V_BANDS, DRIVERS, pickBand, pickDriverKey, pickWarnArchetype, deriveDIV } from '../ui/sections/hacker_panel.js?v=7';
import { DERAILERS } from '../ui/tabs/tab_y_insights.js?v=32';
import { ATTACHMENT_HINTS, ANCHOR_COLLABORATION } from './executive_summary.js?v=3';

// ── Verdiktu konteksti un pamatojumu teksti ──────────────────────────────────
// score: f(g, axes) → 0..100; sliekšņi: PIRKT ≥ hi, NELIKT < lo, citādi TURĒT.
const CONTEXTS = [
    {
        key: 'expert', label: 'Dziļa ekspertīze, kvalitātes turēšana', hi: 58, lo: 38,
        score: (g) => (g('analytical') + g('perseverance') + g('conscient')) / 3,
        reason: {
            PIRKT:  'Tur kvalitāti gadiem bez tavas uzmanības — lētākā uzraudzība portfelī',
            TURĒT:  'Kvalitāti dod, bet vajag ārēju rāmi un termiņus',
            NELIKT: 'Rūpīgā izpilde nav viņa valūta — kvalitātes kontrole paliks uz tevis',
        },
    },
    {
        key: 'manager', label: 'Cilvēku vadība, hierarhijas cīņas', hi: 65, lo: 40,
        score: (g, ax) => ax.D,
        reason: {
            PIRKT:  'Grib varu un nesīs atbildību — dod komandu un prasi rezultātu',
            TURĒT:  'Vadīs, ja uzliksi, bet par krēslu necīnīsies — vara viņu nebaro',
            NELIKT: 'Necīnīsies par varu — komandu pārņems tie, kam tā vajadzīga',
        },
    },
    {
        key: 'crisis', label: 'Krīzes fronte, ugunsgrēku dzēšana', hi: 60, lo: 40,
        score: (g, ax) => (g('fightresponse') + (100 - ax.V)) / 2,
        reason: {
            PIRKT:  'Spiediens viņu mobilizē — sūti tur, kur deg, un netraucē',
            TURĒT:  'Īsu krīzi iztur, ja aizmugure ir droša; ilgstošā — viņu labāk nomainīt',
            NELIKT: 'Spiedienā sastingst vai izdeg — krīzes izmaksas pārsniegs ieguvumu',
        },
    },
    {
        // hi 60→55 (2026-06-12 audits): pie 60 PIRKT dabūja tikai 3/120 profilu — slieksnis bija deģenerējis sadaļu
        key: 'expansion', label: 'Strauja ekspansija, jauni tirgi', hi: 55, lo: 40,
        score: (g) => (g('risk') + g('initiative') + g('adaptability')) / 3,
        reason: {
            PIRKT:  'Riskē un iesāk pats — der tur, kur kartes vēl nav uzzīmētas',
            TURĒT:  'Jaunajā ies, bet tikai ar skaidru plānu un atkāpšanās ceļu',
            NELIKT: 'Nenoteiktība viņam izmaksā dārgi — ekspansijā viņš būs bremze, ne dzinējs',
        },
    },
    {
        // BUGFIX 2026-06-12: id 'extra' personības matricā neeksistē (vienmēr deva 50) — pareizais ir 'extraversion'
        key: 'client', label: 'Klientu fronte, pārstāvniecība', hi: 60, lo: 40,
        score: (g) => (g('extraversion') + g('diplomacy') + g('charisma')) / 3,
        reason: {
            PIRKT:  'Dabiski tur publiku un klientu — lieto kā uzņēmuma seju',
            TURĒT:  'Klientu noturēs viens pret vienu; lielu skatuvi nedod',
            NELIKT: 'Publiskā fronte viņu izsmeļ — turi aizmugurē, kur ir viņa vērtība',
        },
    },
];

// Svira, kas NESTRĀDĀ — anti-valūta katram dzinējam (netērēt resursus tukšgaitā)
const DEAD_LEVERS = {
    Ideja:      'Prēmijas un bonusi bez jēgas maiņas — naudu paņems, atdevi nedos. Netērē budžetu tur, kur vajag misiju.',
    Statuss:    'Privāts "paldies" bez skatuves — atzinība, ko neviens neredz, viņam nav valūta.',
    Nauda:      'Goda raksti, tituli un "komandas gars" bez seguma — viņš rēķina, nevis jūt.',
    Brīvība:    'Piemaksas par virsstundām — laiks viņam ir dārgāks par naudu; pirksi stundas, zaudēsi cilvēku.',
    Drošība:    'Lielā vīzija un karjeras kāpnes — viņš to dzird kā risku, ne kā balvu.',
    Meistarība: 'Viegli uzdevumi ar piemaksu — garlaicība viņam ir sods, nevis atpūta.',
    Piederība:  'Individuāli bonusi, kas izceļ viņu no komandas — atrauts no savējiem viņš zaudē jēgu strādāt.',
};

// Risku tēmu kategorijas (atslēgvārdu kartējums avotu tekstiem).
// 2026-06-12 audits: 'kontrole' un 'autonomija' apvienotas (semantiski dublējās un šķēla balsis),
// regulārās izteiksmes paplašinātas ar sinonīmiem — pie 7 šaurām tēmām 'augsta' pārliecība
// (3+ avoti) bija sasniedzama tikai 1% rindu. Pirmā sakritusī tēma uzvar (secība svarīga).
const RISK_THEMES = [
    // 2026-06-13 audits (#3): regex paplašināts ar pavēles-formas frāzēm no iBand.klu, kas agrāk
    // krita "cits" grozā (spiešana, "es tā teicu", haotisks, neizlēmība, tukši saukļi).
    { key: 'parmainas',  label: 'Pēkšņas pārmaiņas un nenoteiktība',      re: /pēkšņ|nenoteiktīb|haos|haotisk|neparedzam|virziena maiņ|reorganiz|pārmaiņ|satricinā|nedrošīb|negaidīt|improvizāc|nestabil|neizlēmīb|izplūduš/i },
    { key: 'publiska',   label: 'Publiska kritika un statusa aizskārums', re: /publisk|rājien|kritik|apšaub|nosodī|kauns|reputāc|prestiž|izsmiekl|apsmiekl|goda aizskār|ignorē.*sapulc|sapulc.*ignorē/i },
    { key: 'parslodze',  label: 'Pārslodze un robežu pārkāpšana',         re: /pārslodz|virsstund|izdeg|pārstrādā|izsmel|bez.*pauz|bez atelp|nespēj.*nē|slodz/i },
    { key: 'rutina',     label: 'Rutīna bez jēgas un izaicinājuma',       re: /rutīn|garlaic|vienveidīg|vienmuļ|monoton|birokrāt|sekl|atkārtojam|šaur.*iedziļināšan|izaicinājuma trūkum/i },
    { key: 'kontrole',   label: 'Kontrole un autonomijas atņemšana',      re: /mikro|kontrol|uzraudzīb|atskait|pavēl|uzspiest|spiedien|spiešan|es tā teicu|aizbildniecīb|autonomij|brīvīb|ierobežo|formalitāt|protokol|strikt|reglament|priekšrakst|pakļaut/i },
    { key: 'aukstums',   label: 'Auksta vide un ignorēšana',              re: /aukst|klusum|atstātīb|vienaldzīb|izolāc|konkurenc.*iekšēj|iekšēj.*konkurenc|KPI|distanc|tukš.*saukļ|saukļ|žargon/i },
];

// Fallback-virsraksts pēc avota nosaukuma — lai NEVIENS risks nerenderējas bez kategorijas,
// arī ja teksts neietilpst nevienā tēmā (2026-06-13 audits #3). Atslēgas = riskSources nosaukumi.
const SOURCE_FALLBACK_LABEL = {
    'Pieejas josla':   'Nepareiza pieeja un komunikācija',
    'Jutīguma josla':  'Jutīgā vieta',
    'Karjeras enkurs': 'Lomas neatbilstība',
    'Piesaiste':       'Attiecību spriedze',
    'Bioritms':        'Nepiemērots darba ritms',
};

const VERDICT_ORDER = { PIRKT: 0, 'TURĒT': 1, NELIKT: 2 };

export function buildInvestorMemo(profile) {
    // ── Avoti ────────────────────────────────────────────────────────────────
    const num = (x, def) => {
        if (x === null || x === undefined || x === '') return def;
        const n = Number(x);
        return isNaN(n) ? def : n;
    };
    const s = profile?.synergy || {};
    // No-data vārts: bez sinerģijas asu datiem verdikti un arhetips būtu fabricēti no
    // 60/50/40 noklusējumiem. Tad atgriežam null → panelis rāda godīgu "nav datu" stāvokli.
    const present = (x) => x !== null && x !== undefined && x !== '' && !isNaN(Number(x));
    if (!present(s.leadership?.pct) && !present(s.performance?.pct) && !present(s.risks?.pct)) return null;
    // D/I/V no nosauktajiem personības trait (sk. deriveDIV) — NE no synergy radara
    // skoriem (leadership/performance/risks), kas semantiski nesakrita ar asu definīcijām.
    const ax = deriveDIV(profile);
    const p = Object.fromEntries(
        (profile?.personality || []).flatMap(cat => (cat.traits || []).map(t => [t.id, num(t.pct, 50)]))
    );
    const g = id => p[id] ?? 50;

    const dBand = pickBand(D_BANDS, ax.D).pro;
    const iBand = pickBand(I_BANDS, ax.I).pro;
    const vBand = pickBand(V_BANDS, ax.V).pro;
    const driverKey = pickDriverKey(ax.D, ax.I, ax.V, s.motivation);
    const driver = DRIVERS[driverKey]?.pro || null;

    // Cilnes galvenais profila novērtējums — hakera paneļa "Galvenā vadības atziņa" arhetips
    // (tuvākais D/I/V prototips). Izcelts memoranda augšā, kā "Darba & Vadīšanas Stils" archClassLabel.
    const archetypeEntry = pickWarnArchetype(ax.D, ax.I, ax.V);
    const archetypeRaw = archetypeEntry?.pro || null;
    // Atbilstuma kvalitāte: cik tālu profils ir no tuvākā prototipa D/I/V telpā. Liels attālums
    // (>20) = neviens tips neder labi → badge jāhedžo (vāji izteikts), ne jārāda kā stingrs fakts.
    const archDist = archetypeEntry ? Math.sqrt((ax.D - archetypeEntry.d) ** 2 + (ax.I - archetypeEntry.i) ** 2 + (ax.V - archetypeEntry.v) ** 2) : 0;
    const archetype = archetypeRaw ? { title: archetypeRaw.title, text: archetypeRaw.text, weak: archDist > 20 } : null;

    const anchor   = profile?.careerAnchors?.primary || null;
    const collab   = anchor ? (ANCHOR_COLLABORATION[anchor.key] || null) : null;
    const attach   = profile?.relationshipDynamics?.attachment?.primary || null;
    const attachHint = attach ? (ATTACHMENT_HINTS[attach.key] || null) : null;
    const horseman = profile?.relationshipDynamics?.horsemen?.top?.[0] || null;
    const leadKey  = profile?.leadership?.primary?.key || null;
    const derailers = (leadKey && DERAILERS[leadKey]?.asEmployee) || [];
    const rhythm   = profile?.existentialAudit?.mezo?.primary || null;
    const micro    = profile?.existentialAudit?.micro || null;
    const phase    = profile?.timing?.macro?.phaseMeta || null;
    const isTimeUnknown = !!profile?.birth_info?.isTimeUnknown;

    // ── Verdikti pa kontekstiem ──────────────────────────────────────────────
    const deployment = CONTEXTS.map(c => {
        const sc = Math.max(0, Math.min(100, Math.round(c.score(g, ax))));
        const verdict = sc >= c.hi ? 'PIRKT' : sc < c.lo ? 'NELIKT' : 'TURĒT';
        return { key: c.key, label: c.label, score: sc, verdict, reason: c.reason[verdict] };
    }).sort((a, b) => (VERDICT_ORDER[a.verdict] - VERDICT_ORDER[b.verdict]) || (b.score - a.score));

    // Galvenās verdiktu kartes: spēcīgākais PIRKT, izteiktākais NELIKT, viens vidus.
    // 2026-06-12 audits: 46% profilu nedabūja nevienu PIRKT — tad pirmā karte ir
    // augstākā skora konteksts ar atzīmi bestUse ("labākais pielietojums").
    const buys  = deployment.filter(d => d.verdict === 'PIRKT');
    const sells = deployment.filter(d => d.verdict === 'NELIKT');
    const byScore = [...deployment].sort((a, b) => b.score - a.score);
    const cards = [];
    if (buys.length) cards.push(buys[0]);
    else if (byScore.length) cards.push({ ...byScore[0], bestUse: true });
    if (sells.length) {
        const worst = sells[sells.length - 1];
        if (!cards.some(c => c.key === worst.key)) cards.push(worst);
    }
    for (const d of deployment) {
        if (cards.length >= 3) break;
        if (!cards.some(c => c.key === d.key)) cards.push(d);
    }

    // ── Pelna / Maksā ────────────────────────────────────────────────────────
    // Šuvju palīgi: lc = turpinājums aiz prefiksa (mazais burts); uc = patstāvīga
    // teikuma sākums (lielais burts); dot = garantēta pieturzīme beigās.
    const lc = (t) => t ? t.charAt(0).toLowerCase() + t.slice(1) : '';
    const uc = (t) => t ? t.charAt(0).toUpperCase() + t.slice(1) : '';
    const dot = (t) => t ? (/[.!?…]$/.test(t.trim()) ? t.trim() : t.trim() + '.') : '';
    const earns = [];
    if (ax.D < 45)      earns.push('Negrib tavu krēslu — vari uzticēt teritoriju, neuzraugot muguru.');
    else if (ax.D > 70) earns.push('Velk uz augšu pats — dod mērķi, un viņš nes rezultātu bez pātagas.');
    else                earns.push('Stabils dzinējs bez galējībām — tur tempu, ja noteikumi ir skaidri un atlīdzība godīga.');
    if (vBand.st) earns.push(vBand.st);
    if (anchor?.thrives) earns.push(`Vislabāk atmaksājas: ${lc(anchor.thrives)}.`);

    // Avotu budžets (2026-06-12 audits): vBand.en šeit dublējās ar Riskiem 69% profilu —
    // tas semantiski IR risks, tāpēc pārcelts turp; izmaksu trešā rinda = piesaistes krīzes cena.
    const costs = [];
    if (attachHint?.comm) costs.push(`Uzturēšanas prasība: ${lc(attachHint.comm)}.`);
    if (rhythm?.recovery) costs.push(`Atjaunošanās izmaksas: ${lc(rhythm.recovery)}.`);
    if (attachHint?.crisis) costs.push(`Krīzes brīdī ${lc(attachHint.crisis)} — ieplāno to kā darba laika izmaksu.`);

    // ── Sviras ───────────────────────────────────────────────────────────────
    const levers = {
        main: driver ? {
            title: driver.title,
            text: `${driver.butiba} Valūta: ${lc(driver.valuta)}`,
            phrase: driver.rez || null,
            award: driver.balva || null,
        } : null,
        reserve: iBand.tak ? { title: iBand.t, text: iBand.tak } : null,
        dead: {
            text: DEAD_LEVERS[driverKey] || 'Universālās sviras šim profilam nestrādā — skaties galveno valūtu.',
            extra: dBand.at ? `Atņem sparu: ${lc(dBand.at)}` : null,
        },
    };

    // ── Riski ar avotu konsensu ──────────────────────────────────────────────
    // leadable:false = avots BALSO par tēmu, bet tā teksts nekļūst par riska virsrindu,
    // jo jau tiek rādīts citā sadaļā (dBand.at → Sviras-Nestrādā; deraileri → Traucējummeklēšana).
    // Tas likvidē paneļa iekšējos dublikātus, nezaudējot konsensa skaitīšanu (2026-06-12 audits).
    const riskSources = [
        { name: 'Dzinuļa josla',   text: dBand.at,  leadable: false },
        { name: 'Pieejas josla',   text: iBand.klu, leadable: true },
        { name: 'Jutīguma josla',  text: vBand.en,  leadable: true },
        { name: 'Karjeras enkurs', text: anchor?.burnoutTrigger || null, leadable: true },
        { name: 'Piesaiste',       text: attachHint?.warning || null, leadable: true },
        { name: 'Bioritms',        text: rhythm?.burnoutTrigger || null, leadable: true },
        ...derailers.slice(0, 2).map(d => ({ name: 'Darba stils', text: d.trigger ? `${d.trigger} — ${d.impact || ''}` : null, leadable: false })),
    ].filter(src => src.text);

    const themed = new Map();
    const misc = [];
    for (const src of riskSources) {
        const theme = RISK_THEMES.find(t => t.re.test(src.text));
        if (theme) {
            if (!themed.has(theme.key)) themed.set(theme.key, { theme, sources: [] });
            themed.get(theme.key).sources.push(src);
        } else {
            misc.push(src);
        }
    }
    const LEVELS = ['zema', 'vidēja', 'augsta'];
    const levelOf = (count) => {
        let idx = count >= 3 ? 2 : count === 2 ? 1 : 0;
        if (isTimeUnknown && idx > 0) idx -= 1; // bez dzimšanas laika pārliecība par pakāpi zemāka
        return LEVELS[idx];
    };
    // Grupas bez neviena vadāmā avota tiek IZLAISTAS (to teksti jau redzami Sviras-Nestrādā /
    // Traucējummeklēšanā) — pretējā gadījumā rezerves ceļš atjaunoja dublikātus 26% profilu.
    const risks = [...themed.values()]
        .sort((a, b) => b.sources.length - a.sources.length)
        .map(grp => {
            const pool = grp.sources.filter(s => s.leadable);
            if (!pool.length) return null;
            const lead = pool.reduce((best, s) => (s.text.length > best.text.length ? s : best), pool[0]);
            return {
                label: grp.theme.label,
                level: levelOf(grp.sources.length),
                count: grp.sources.length,
                text: dot(uc(lead.text)),
                sources: grp.sources.map(s => s.name),
            };
        })
        .filter(Boolean)
        .slice(0, 3);
    for (const src of misc) {
        if (risks.length >= 3) break;
        if (!src.leadable) continue;
        risks.push({ label: SOURCE_FALLBACK_LABEL[src.name] || null, level: levelOf(1), count: 1, text: dot(uc(src.text)), sources: [src.name] });
    }

    // ── Traucējummeklēšana ───────────────────────────────────────────────────
    const firstPart = (t, seps = [' — ', '; ', '. ']) => {
        if (!t) return '';
        for (const sep of seps) {
            const i = t.indexOf(sep);
            if (i > 10) return t.slice(0, i);
        }
        return t;
    };
    const trouble = derailers.slice(0, 2).map(d => ({
        symptom: dot(uc(d.shows)) || '—',
        cause: d.name ? `${d.name} (izraisītājs: ${lc(d.trigger) || '—'})` : (uc(d.trigger) || '—'),
        action: dot(uc(d.mitMgr)) || '—',
    }));
    if (horseman?.lv) {
        trouble.push({
            symptom: dot(uc(firstPart(horseman.pattern))) || horseman.lv,
            cause: `Konflikta paterns: ${horseman.lv}`,
            action: dot(uc(firstPart(horseman.antidote))) || '—',
        });
    }

    // ── Pirmās 30 dienas un ritms ────────────────────────────────────────────
    // 2026-06-12 audits: iBand.tak šeit 100% dublējās ar Sviras-Rezerves un driver.valuta —
    // ar Sviras-Galveno; soļi 1–2 tagad nāk no ceļveža kopsavilkumiem (rīcības palaidējs,
    // īstais brīdis), kas memorandā citur neparādās. Kols prefiksā tikai, ja teksts pats to nesatur.
    const inf = profile?.influence || [];
    const infSummary = (sid) => inf.find(sec => sec.id === sid)?.summary || null;
    const step = (prefix, text) => text.includes(':') ? `${prefix} — ${lc(text)}` : `${prefix}: ${lc(text)}`;
    const actionSum = infSummary('action');
    const timingSum = infSummary('timing');
    const start30 = [];
    if (actionSum) start30.push(dot(uc(actionSum)));
    if (timingSum) start30.push(step('Sarunu laiks', timingSum));
    if (collab?.give) start30.push(dot(step('Pirmajā mēnesī nodrošini', collab.give)));
    if (start30.length < 3 && iBand.tak) start30.push(dot(uc(iBand.tak)));
    const rhythmLines = [];
    if (rhythm?.lv && rhythm?.biotope) rhythmLines.push(`${rhythm.lv}: ${lc(rhythm.biotope)}.`);
    if (micro?.restart) rhythmLines.push(`Restarts pēc spriedzes: ${lc(micro.restart)}.`);

    return {
        isTimeUnknown,
        axes: ax,
        driverKey,
        archetype,
        phaseLv: phase?.lv || null,
        cards,
        deployment,
        earns: earns.slice(0, 3),
        costs: costs.slice(0, 3),
        levers,
        risks,
        trouble: trouble.slice(0, 3),
        start30: start30.slice(0, 3),
        rhythmLines: rhythmLines.slice(0, 2),
    };
}
