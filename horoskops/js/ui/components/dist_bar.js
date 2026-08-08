// ── SADALĪJUMA JOSLAS (24h nezināms laiks) ───────────────────────────────────
// Koplietojams renderis varbūtību sadalījumiem, ko ražo logic/time_sweep.js.
// Lietots visās cilnēs, lai laika-jutīgos lielumus (Ascendanta zīme, planētu
// mājas, Mēness zīme, Bazi stunda) rādītu kā sadalījumu, ne fiktīvu vienvērtību.

export const SIGN_NAMES = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];

// Klasificē sadalījumu: vai ir izšķirams līderis, vai līdzvērtīgu grupa (tie).
// tieRatio — cik tuvu līderim vērtībai jābūt, lai to skaitītu par "līdzvērtīgu"
// (0.90 = 10% robežās no līdera). decisiveLead — minimālais līdera pārsvars pret
// otro, lai vispār kronētu vienu (papildu drošinātājs pret viltus uzvarētāju).
export function classifyDist(rows, opts = {}) {
    const tieRatio     = opts.tieRatio     ?? 0.90;
    const decisiveLead = opts.decisiveLead ?? 0.05; // ≥5 procentpunktu atstarpe
    if (!rows.length) return { decisive: false, tie: [], ruledOut: [] };
    const leader = rows[0].frac;
    const second = rows[1]?.frac ?? 0;
    const tie = rows.filter(r => r.frac >= leader * tieRatio);
    const decisive = tie.length === 1 && (leader - second) >= decisiveLead;
    const ruledOut = rows.filter(r => r.frac > 0 && r.frac < leader * 0.45);
    return { decisive, leader, second, tie, ruledOut };
}

// dist: { idx: frakcija } (summa ≈ 1). labels: idx → teksts.
// opts: {color, title, desc, max, unit, tieRatio, decisiveLead}
function distBars(dist, labels, opts = {}) {
    const color = opts.color || "#1d4ed8";
    const max   = opts.max   || 5;
    const unit  = opts.unit  || "varianti";
    const rows = Object.entries(dist)
        .map(([k, v]) => ({ idx: +k, frac: v, label: labels(+k) }))
        .sort((a, b) => b.frac - a.frac);

    const cls = classifyDist(rows, opts);
    const tieIdx = new Set(cls.tie.map(r => r.idx));
    const shown = rows.slice(0, max);
    const restFrac = rows.slice(max).reduce((s, r) => s + r.frac, 0);
    const pctOf = (f) => Math.round(f * 100);

    // Verdikts — godīgs kopsavilkums VIRS joslām (izšķirams līderis vai līdzvērtīga grupa)
    let verdict;
    if (cls.decisive) {
        const alt = cls.second >= 0.15 ? `, citādi <b>${rows[1].label}</b> (${pctOf(cls.second)}%)` : '';
        verdict = `<div style="font-size:0.78rem; color:${color}; font-weight:700; margin-bottom:5px;">🎯 Ticamākais: <b>${rows[0].label}</b> (${pctOf(cls.leader)}%)${alt}</div>`;
    } else {
        const names = cls.tie.map(r => r.label).join(' · ');
        const tieMass = cls.tie.reduce((s, r) => s + r.frac, 0);
        const avg = pctOf(tieMass / cls.tie.length);
        verdict = `<div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:6px; padding:0.4rem 0.55rem; margin-bottom:6px;">
            <div style="font-size:0.78rem; color:#9a3412; font-weight:800;">⚖️ Nenosakāms — ${cls.tie.length} līdzvērtīgas ${unit} (katra ~${avg}%)</div>
            <div style="font-size:0.76rem; color:#9a3412; margin-top:2px;">${names}. Bez precīza dzimšanas laika tās statistiski <b>nav izšķiramas</b> — neviena nav "tā īstā".</div>
        </div>`;
    }

    const barRows = shown.map((r) => {
        const pct = pctOf(r.frac);
        const inTie = tieIdx.has(r.idx);
        const isCrown = cls.decisive && r.idx === rows[0].idx;
        const tag = isCrown
            ? ` <span style="background:${color}22; color:${color}; border-radius:4px; padding:0px 5px; font-size:0.58rem; font-weight:800; letter-spacing:0.5px;">TICAMĀKAIS</span>`
            : (!cls.decisive && inTie ? ` <span style="color:#9a3412; font-size:0.6rem; font-weight:700;">≈</span>` : '');
        const emphasize = isCrown || (!cls.decisive && inTie);
        return `
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:3px; ${emphasize ? '' : 'opacity:0.7;'}">
                <span style="min-width:92px; font-size:0.8rem; font-weight:${emphasize ? '700' : '500'}; color:#334155;">${r.label}${tag}</span>
                <span style="flex:1; background:#f1f5f9; border-radius:3px; height:6px; overflow:hidden;"><span style="display:block; height:100%; width:${pct}%; background:${color}${emphasize ? '' : '99'};"></span></span>
                <span style="min-width:34px; text-align:right; font-size:0.78rem; font-weight:600; color:${color}; font-variant-numeric:tabular-nums;">${pct}%</span>
            </div>`;
    }).join("");

    // "Praktiski izslēgts" — tikai tie, kas NAV jau redzami joslās, un kompakti
    const shownIdx = new Set(shown.map(r => r.idx));
    const ruled = cls.ruledOut.filter(r => !shownIdx.has(r.idx));
    let ruledTxt = "";
    if (ruled.length >= 3) {
        const maxPct = pctOf(Math.max(...ruled.map(r => r.frac)));
        ruledTxt = ruled.length <= 4
            ? ` · praktiski izslēgts: ${ruled.map(r => r.label).join(', ')}`
            : ` · praktiski izslēgts: ${ruled.length} ${unit} (≤${maxPct}%)`;
    }
    const restRow = (restFrac > 0.005 || ruledTxt)
        ? `<div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">${restFrac > 0.005 ? `+ pārējās ${pctOf(restFrac)}%` : ''}${ruledTxt}</div>`
        : "";
    const header = opts.title ? `<div style="font-size:0.68rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">${opts.title}</div>` : "";
    const desc   = opts.desc  ? `<div style="font-size:0.76rem; color:#64748b; font-style:italic; margin-bottom:6px;">${opts.desc}</div>` : "";

    return `<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:0.7rem 0.85rem; margin-top:0.6rem;">${header}${desc}${verdict}${barRows}${restRow}</div>`;
}

// Zīmju sadalījums (Ascendants, planētas)
export function signDistBar(dist, opts = {}) {
    if (!dist || Object.keys(dist).length <= 1) return ""; // viens punkts → nav ko rādīt
    return distBars(dist, (i) => SIGN_NAMES[i] || `#${i}`, { unit: "zīmes", ...opts });
}

// Māju sadalījums
export function houseDistBar(dist, opts = {}) {
    if (!dist || Object.keys(dist).length <= 1) return "";
    return distBars(dist, (i) => `${i + 1}. māja`, { unit: "mājas", ...opts });
}

// Vispārējs (jau-gatavu label funkciju)
export function genericDistBar(dist, labelFn, opts = {}) {
    if (!dist || Object.keys(dist).length <= 1) return "";
    return distBars(dist, labelFn, opts);
}

// Konsolidēts 24h sadalījuma panelis — viens autoritatīvs skats uz visiem
// laika-jutīgajiem lielumiem. Cilnes to pievieno ar vienu rindu (banner+panelis).
// Tā kā visu planētu mājas seko Ascendantam, tās ir tikpat nenoteiktas — to pasakām
// vārdiski, nevis rādām 12 gandrīz vienādas joslas katrai planētai.
export function fullDistPanel(sweep, opts = {}) {
    if (!sweep) return "";
    const vAsc = signDistBar(sweep.vedic?.ascSignDist, { color: '#6366f1', title: 'Ascendants (Vēdu, siderālais) · 24h', desc: 'Galvenais laika draiveris — no tā izriet visu planētu mājas.' });
    const wAsc = signDistBar(sweep.western?.ascSignDist, { color: '#0ea5e9', title: 'Ascendants (Rietumu, tropiskais) · 24h' });
    const moon = signDistBar(sweep.vedic?.planets?.Meness?.signDist, { color: '#7e22ce', title: 'Mēness zīme · 24h' });
    const bazi = genericDistBar(sweep.baziHour?.branchDist, (i) => `Zars #${i + 1}`, { color: '#b45309', title: 'Bazi stundas stabs · 24h', max: 12 });
    const note = `<div style="font-size:0.78rem; color:#64748b; font-style:italic; margin-top:0.5rem;">Piezīme: visu planētu <b>mājas</b> seko Ascendantam (mainās ik ~2h), tāpēc tās ir tikpat nenoteiktas — katra māja aptuveni vienlīdz iespējama. Planētu <b>zīmes</b> (izņemot Mēnesi) dienas laikā nemainās.</div>`;
    return `<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.2rem; margin:0.6rem 0;">
        <div style="font-size:0.82rem; font-weight:800; color:#334155; margin-bottom:0.3rem;">📊 24h sadalījums — nezināms dzimšanas laiks</div>
        ${vAsc}${wAsc}${moon}${bazi}${note}
    </div>`;
}

// Globāls brīdinājuma banneris — rādīt sadaļās, kuru saturs ir 24h sadalījums.
export function timeUnknownBanner(text) {
    const msg = text || "Dzimšanas laiks nav zināms — šis ir 24h <b>sadalījums pār vietējo dienu</b>, ne viena vērtība. Ascendants un mājas mainās ik ~2h, tāpēc tās rādītas kā varbūtības.";
    return `<div style="background:#fffbeb; border:1px solid #fcd34d; border-left:3px solid #f59e0b; border-radius:8px; padding:0.6rem 0.8rem; margin:0.5rem 0; font-size:0.8rem; color:#92400e; line-height:1.5;">⏳ ${msg}</div>`;
}
