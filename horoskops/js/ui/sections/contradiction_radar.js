// ⚖️ Sistēmu saskaņas radars — pretrunu sintēzes slānis (t3 Psiholoģija, 2026-06-11).
// Dažādi paneļi par to pašu cilvēku izsakās caur DAŽĀDĀM lēcām (sinerģijas metrikas,
// rakstura asis, Gotmana paterni) — lasītājam tas var izskatīties pēc pretrunām.
// Šis panelis tās paceļ virspusē: parāda lēcas blakus, novērtē saskaņu un IZSKAIDRO
// atšķirību kā informāciju (synergy_panel konsensa čipu mācība, cross-dim teksti
// pēc actionSummary parauga). Datu/svaru loģika NAV mainīta — tikai sintēze.

export function renderContradictionRadar(profile) {
    // ── Avoti ────────────────────────────────────────────────────────────────
    // scoring.js normalizePct atgriež VIRKNI — num() pieņem skaitliskas virknes.
    const num = (x, def) => {
        if (x === null || x === undefined || x === '') return def;
        const n = Number(x);
        return isNaN(n) ? def : n;
    };
    const p = {};
    for (const cat of (profile?.personality || [])) {
        for (const t of (cat.traits || [])) p[t.id] = t.pct;
    }
    const g = (id) => num(p[id], NaN);

    const D    = num(profile?.synergy?.leadership?.pct, NaN);   // hakera paneļa "Iekšējais dzinējs"
    const V    = num(profile?.synergy?.risks?.pct, NaN);        // hakera paneļa "Jutīgā vieta"
    const apc  = (() => {                                       // ceļveža "Ikdienas pašmotivācija"
        const a = g('ambition'), b = g('perseverance'), c = g('conscient');
        return [a, b, c].some(isNaN) ? NaN : Math.round((a + b + c) / 3);
    })();
    const init = g('initiative');                               // ceļveža "Kā vadīt" svira
    const strS = g('fightresponse');                            // ceļveža "Zem spiediena"
    const horseman = profile?.relationshipDynamics?.horsemen?.top?.[0] || null;

    const lvl = (v) => (v > 60 ? 'hi' : v < 40 ? 'lo' : 'mid');
    const LVL_LV = { hi: 'augsts', mid: 'mērens', lo: 'zems' };

    // ── A konstrukts: "Iekšējais dzinulis" — trīs skaitliskās lēcas ─────────
    const lensesA = [
        { label: 'Vadības ambīcija', what: 'kurp viņš tiecas', src: '5 sistēmu sinerģija', v: D },
        { label: 'Ikdienas pašmotivācija', what: 'cik patstāvīgi velk uzticēto darbu', src: 'rakstura asis', v: apc },
        { label: 'Starta iniciatīva', what: 'cik viegli iesāk bez grūdiena', src: 'rakstura asis', v: init },
    ].filter(l => !isNaN(l.v));

    const levelsA = [...new Set(lensesA.map(l => lvl(l.v)))];
    const chipA = levelsA.length <= 1
        ? { icon: '✓', label: 'Lēcas vienojas', col: '#065f46', bg: '#ecfdf5' }
        : levelsA.length === 2
            ? { icon: '≈', label: 'Lēcas daļēji atšķiras', col: '#92400e', bg: '#fffbeb' }
            : { icon: '↔', label: 'Lēcas rāda dažādus slāņus', col: '#9a3412', bg: '#fff7ed' };

    // Sintēzes noteikumi izteiktākajām kombinācijām (cross-dim, ne atsevišķas joslas)
    const synthA = (() => {
        if (lensesA.length < 2) return '';
        if (!isNaN(D) && !isNaN(apc) && D < 40 && apc > 60)
            return 'Zema vadības ambīcija kopā ar augstu ikdienas pašmotivāciju NAV pretruna: viņš netiecas pēc varas vai statusa, bet uzticētu darbu velk izcili un patstāvīgi. Uzticams dziļuma cilvēks, ne karjerists — abi mērījumi ir patiesi, katrs savā slānī.';
        if (!isNaN(D) && !isNaN(apc) && D > 60 && apc < 40)
            return 'Augsta vadības ambīcija kopā ar zemāku ikdienas pašmotivāciju: viņš grib augšup un redz lielo bildi, bet rūpīgā ikdienas izpilde viņu nogurdina. Vislabāk strādā ar komandu vai procesiem, kas tur ikdienu, kamēr viņš tur virzienu.';
        if (!isNaN(apc) && !isNaN(init) && apc > 60 && init < 40)
            return 'Spēcīga pašmotivācija ar kūtru startu: kad darbs iesācies, viņš to velk izturīgi un pats, bet pirmajam solim vajag ārēju rāmi — konkrētu sākumu un termiņu. Pēc starta uzraudzība vairs nav vajadzīga.';
        if (!isNaN(apc) && !isNaN(init) && apc < 40 && init > 60)
            return 'Viegls starts ar grūtāku noturēšanu: viņš labprāt iesāk jauno, bet garā distancē motivācija plok — palīdz īsi posmi, redzami rezultāti un atzinība ceļā, ne tikai galā.';
        if (levelsA.length === 1)
            return `Visas lēcas rāda vienu līmeni (${LVL_LV[levelsA[0]]}) — šis ir drošs, stabils secinājums, uz kura var balstīt lēmumus.`;
        return 'Lēcas atšķiras, jo mēra dažādus slāņus: ambīcija rāda, KURP cilvēks tiecas; pašmotivācija — cik patstāvīgi viņš velk darbu; iniciatīva — cik viegli iesāk. Katru skaitli lasi savā kontekstā, nevis kā vienu "motivācijas" rādītāju.';
    })();

    const barRow = (l) => `
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:7px;">
            <div style="flex:0 0 240px; min-width:0;">
                <div style="font-size:0.82rem; font-weight:700; color:#1e293b;">${l.label} <span style="font-weight:400; color:#94a3b8;">· ${l.src}</span></div>
                <div style="font-size:0.74rem; color:#64748b;">${l.what}</div>
            </div>
            <div style="flex:1; background:#e2e8f0; border-radius:4px; height:7px; overflow:hidden;">
                <div style="height:100%; width:${Math.max(0, Math.min(100, l.v))}%; background:${lvl(l.v) === 'hi' ? '#7c3aed' : lvl(l.v) === 'lo' ? '#94a3b8' : '#a78bfa'};"></div>
            </div>
            <div style="flex:0 0 76px; text-align:right; font-size:0.82rem; font-weight:700; color:#475569; font-variant-numeric:tabular-nums;">${Math.round(l.v)}% <span style="font-weight:500; color:#94a3b8;">${LVL_LV[lvl(l.v)]}</span></div>
        </div>`;

    // ── B konstrukts: "Uzvedība zem spiediena" — trīs konteksti (bez skoriem) ──
    const ctxRows = [];
    if (!isNaN(strS)) {
        const t = strS > 60
            ? 'Spiediens mobilizē — konfrontācija drīzāk aktivizē nekā traumē.'
            : strS < 40
                ? 'Akūtā brīdī sastingst vai izvairās — svarīgas sarunas stresa brīdī labāk nesākt.'
                : 'Mērens spiediens aktivizē, bet pārslodze nobīda uz aizsardzību.';
        ctxRows.push({ icon: '⚡', ctx: 'Akūts darba spiediens', src: 'rakstura ass', text: t });
    }
    if (!isNaN(V)) {
        const t = V > 60
            ? 'Augsts emocionālais jutīgums — ilgstošā spriedzē meklē atbalstu un apstiprinājumu pie citiem.'
            : V < 40
                ? 'Emocionāli noturīgs — ilgstoši satricinājumi viņu skar maz.'
                : 'Mērena emocionālā jutība — ilgstoša spriedze skar, bet nesagāž.';
        ctxRows.push({ icon: '🌊', ctx: 'Ilgstoša spriedze', src: '5 sistēmu sinerģija', text: t });
    }
    if (horseman?.lv) {
        ctxRows.push({ icon: '💬', ctx: 'Attiecību konflikti', src: 'Gotmana paterns',
            text: `Galvenais konflikta paterns — ${horseman.lv}${horseman.pattern ? ': ' + String(horseman.pattern).split('—')[0].trim().toLowerCase() : ''}.` });
    }

    const synthB = (() => {
        if (isNaN(strS) || isNaN(V)) return '';
        if (strS > 60 && V > 60)
            return 'Akūtā brīdī viņš mobilizējas un iet konfrontācijā, bet ilgstoša spriedze viņu skar dziļi — sprinta izturība, ne maratona. Īsa krīze viņu aktivizē; nedēļām ilgs spiediens prasa atbalstu un atjaunošanos.';
        if (strS < 40 && V < 40)
            return 'Akūtā brīdī viņš izvairās no konfrontācijas, taču kopumā ir emocionāli noturīgs — tā nav trauslums, bet izvēle nekonfrontēt. Dod laiku, un viņš atgriežas pie jautājuma mierīgs.';
        if (strS < 40 && V > 60)
            return 'Abas lēcas saskan: gan akūtā brīdī, gan ilgtermiņā spiediens viņam ir smags — psiholoģiskā drošība ir galvenais darba apstāklis, ne komforta jautājums.';
        if (strS > 60 && V < 40)
            return 'Abas lēcas saskan: spiediens viņu mobilizē un ilgstoša spriedze nesagāž — dabisks krīžu cilvēks, kuram izaicinājums ir degviela.';
        return 'Konteksti apraksta dažādus laika posmus un vidi: pirmā reakcija, ilgstoša slodze un attiecību konflikti var atšķirties — viens cilvēks dažādos kontekstos reaģē dažādi, un tieši šī kombinācija ir profila vērtīgākā informācija.';
    })();

    if (!lensesA.length && !ctxRows.length) return '';

    // ── Renderis (gaišā tēma, card() konvencijas) ────────────────────────────
    const chip = (c) => `<span style="display:inline-flex; align-items:center; gap:5px; background:${c.bg}; color:${c.col}; border-radius:6px; padding:3px 10px; font-size:0.74rem; font-weight:800;">${c.icon} ${c.label}</span>`;

    return `
    <div style="background:white; border-radius:14px; padding:1.3rem 1.4rem; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <div style="display:flex; align-items:baseline; gap:0.6rem; margin-bottom:0.35rem; flex-wrap:wrap;">
            <span style="font-size:1.15rem;">⚖️</span>
            <h3 style="font-size:0.98rem; color:#1e293b; margin:0; font-weight:800;">Sistēmu saskaņas radars</h3>
            <span style="font-size:0.72rem; color:#94a3b8;">Kur dažādas analīzes lēcas saka atšķirīgo — un kāpēc tā nav kļūda</span>
        </div>
        <p style="margin:0 0 1rem 0; font-size:0.82rem; color:#64748b; line-height:1.55;">
            Šīs cilnes paneļi skatās uz cilvēku caur dažādām lēcām — sinerģijas metrikām, rakstura asīm un attiecību paterniem.
            Tās mēra <b>dažādus slāņus</b>, tāpēc skaitļi un apraksti var atšķirties. Šeit galvenās lēcas saliktas blakus.
        </p>

        ${lensesA.length ? `
        <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:0.95rem 1.1rem; margin-bottom:0.8rem;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:0.6rem; flex-wrap:wrap; margin-bottom:0.7rem;">
                <div style="font-size:0.78rem; font-weight:800; color:#6d28d9; text-transform:uppercase; letter-spacing:1px;">🚀 "Iekšējais dzinulis" — trīs lēcas</div>
                ${chip(chipA)}
            </div>
            ${lensesA.map(barRow).join('')}
            ${synthA ? `<div style="margin-top:0.6rem; padding-top:0.6rem; border-top:1px solid #e9d5ff; font-size:0.85rem; color:#334155; line-height:1.6;"><b style="color:#6d28d9;">Sintēze:</b> ${synthA}</div>` : ''}
        </div>` : ''}

        ${ctxRows.length ? `
        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:0.95rem 1.1rem;">
            <div style="font-size:0.78rem; font-weight:800; color:#1d4ed8; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.7rem;">🌊 Uzvedība zem spiediena — trīs konteksti</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:0.6rem;">
                ${ctxRows.map(r => `
                <div style="background:#fff; border:1px solid #dbeafe; border-radius:8px; padding:0.7rem 0.85rem;">
                    <div style="font-size:0.72rem; font-weight:800; color:#1d4ed8; margin-bottom:4px;">${r.icon} ${r.ctx} <span style="font-weight:400; color:#94a3b8;">· ${r.src}</span></div>
                    <div style="font-size:0.83rem; color:#334155; line-height:1.55;">${r.text}</div>
                </div>`).join('')}
            </div>
            ${synthB ? `<div style="margin-top:0.7rem; padding-top:0.6rem; border-top:1px solid #dbeafe; font-size:0.85rem; color:#334155; line-height:1.6;"><b style="color:#1d4ed8;">Sintēze:</b> ${synthB}</div>` : ''}
        </div>` : ''}

        <div style="margin-top:0.9rem; font-size:0.76rem; color:#94a3b8; line-height:1.5;">
            📌 Ja lēcas vienojas — secinājums ir drošs un uz tā var balstīt lēmumus. Ja atšķiras — uzvedība ir atkarīga no konteksta,
            un tieši atšķirība pasaka, <i>kuros apstākļos</i> cilvēks rādīs kuru pusi. Atšķirība nav mērījuma kļūda.
        </div>
    </div>`;
}
