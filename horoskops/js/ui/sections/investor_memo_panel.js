// 🎯 Personības lietošanas instrukcija — Investora memorands (t3 pirmais panelis).
// Renderē logic/investor_memo.js sintēzi gaišajā karšu valodā. Vadītāja skats:
// verdikti pa lomām, pelna/maksā, sviras, riski ar konsensu, traucējummeklēšana.
// Tipogrāfija (2026-06-12 lasāmības pārstrāde): katra sadaļa savā rāmītī ar tonētu
// galvu un krāsainu kreiso malu (tab_experiment card() konvencija), pamatteksts
// 0.92rem / 1.7 — paneļa saturs vairs nav viena nepārtraukta teksta siena.

import { buildInvestorMemo } from '../../logic/investor_memo.js?v=11';

const VERDICT_STYLE = {
    PIRKT:  { bg: '#f0fdf4', border: '#15803d', text: '#166534', label: '#15803d' },
    'TURĒT': { bg: '#fffbeb', border: '#b45309', text: '#92400e', label: '#b45309' },
    NELIKT: { bg: '#fef2f2', border: '#b91c1c', text: '#991b1b', label: '#b91c1c' },
};

const LEVEL_STYLE = {
    augsta: { bg: '#fef2f2', col: '#991b1b' },
    'vidēja': { bg: '#fffbeb', col: '#92400e' },
    zema:   { bg: '#f1f5f9', col: '#64748b' },
};

// Sadaļas rāmītis: tonēta galva ar krāsainu kreiso malu + gaišs ķermenis.
const section = (icon, title, sub, color, body) => `
    <div style="margin-top:1.4rem; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; background:#fff;">
        <div style="background:${color}0e; border-left:4px solid ${color}; padding:0.75rem 1.15rem; display:flex; align-items:baseline; gap:0.6rem; flex-wrap:wrap;">
            <span style="font-size:1.05rem;">${icon}</span>
            <span style="font-size:0.95rem; font-weight:800; color:#1e293b;">${title}</span>
            ${sub ? `<span style="font-size:0.78rem; color:#94a3b8;">${sub}</span>` : ''}
        </div>
        <div style="padding:1.1rem 1.25rem;">${body}</div>
    </div>`;

export function renderInvestorMemoPanel(profile) {
    const memo = buildInvestorMemo(profile);
    if (!memo || !memo.cards.length) {
        return `
    <div style="background:white; border-radius:14px; padding:1.4rem 1.5rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <div style="display:flex; align-items:baseline; gap:0.7rem; flex-wrap:wrap; margin-bottom:0.6rem;">
            <span style="font-size:1.3rem;">🎯</span>
            <h3 style="font-size:1.12rem; color:#1e293b; margin:0; font-weight:800;">Personības lietošanas instrukcija</h3>
        </div>
        <p style="font-size:0.9rem; color:#64748b; line-height:1.65; margin:0;">
            Šim profilam vēl nav aprēķinātas pamata sinerģijas asis, tāpēc verdikti un vadītāja kopsavilkums netiek rādīti — memorands parādīsies, kad būs pieejami pamatdati. Labāk tukšs nekā lasījums no vidējiem noklusējumiem.
        </p>
    </div>`;
    }

    // ── Verdiktu kartes ──────────────────────────────────────────────────────
    const cardsHtml = memo.cards.map(c => {
        const st = VERDICT_STYLE[c.verdict];
        return `
        <div style="background:${st.bg}; border:1px solid ${st.border}40; border-left:5px solid ${st.border}; border-radius:12px; padding:1rem 1.15rem;">
            <div style="font-size:0.74rem; font-weight:800; color:${st.label}; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:4px;">${c.label}</div>
            <div style="font-size:1.45rem; font-weight:900; color:${st.text}; margin-bottom:5px; letter-spacing:0.5px;">${c.verdict}${c.bestUse ? ` <span style="font-size:0.68rem; font-weight:800; background:${st.border}1a; color:${st.label}; border:1px solid ${st.border}50; border-radius:6px; padding:2px 8px; vertical-align:middle; letter-spacing:0.5px;">★ LABĀKAIS PIELIETOJUMS</span>` : ''}</div>
            <div style="font-size:0.86rem; color:#475569; line-height:1.55;">${c.reason}</div>
        </div>`;
    }).join('');

    // ── Pelna / Maksā ────────────────────────────────────────────────────────
    const bulletList = (items, dotColor) => items.map(t => `
        <div style="display:flex; gap:10px; margin-bottom:0.7rem; align-items:flex-start;">
            <span style="color:${dotColor}; font-weight:900; flex-shrink:0; margin-top:2px;">▸</span>
            <span style="font-size:0.92rem; color:#334155; line-height:1.65;">${t}</span>
        </div>`).join('');

    // ── Sviras ───────────────────────────────────────────────────────────────
    const leverRow = (tag, tagColor, tagBg, inner, last = false) => `
        <div style="display:grid; grid-template-columns:108px minmax(0,1fr); gap:14px; padding:0.85rem 0; ${last ? '' : 'border-bottom:1px solid #f1f5f9;'} align-items:start;">
            <span style="font-size:0.74rem; font-weight:800; color:${tagColor}; background:${tagBg}; border-radius:6px; padding:4px 0; text-align:center; margin-top:2px;">${tag}</span>
            <span style="font-size:0.92rem; color:#334155; line-height:1.65;">${inner}</span>
        </div>`;
    const lv = memo.levers;
    const leversHtml = [
        lv.main ? leverRow('GALVENĀ', '#166534', '#f0fdf4',
            `<b style="color:#1e293b;">${lv.main.title}</b> — ${lv.main.text}` +
            (lv.main.phrase ? `<div style="margin-top:6px; padding:7px 11px; background:#f8fafc; border-left:3px solid #cbd5e1; border-radius:0 6px 6px 0; font-style:italic; color:#64748b; font-size:0.86rem; line-height:1.55;">Frāze, kas strādā: "${lv.main.phrase}"</div>` : '') +
            (lv.main.award ? `<div style="margin-top:6px; font-size:0.84rem; color:#64748b;">Atalgo ar: ${lv.main.award}</div>` : '')) : '',
        lv.reserve ? leverRow('REZERVES', '#92400e', '#fffbeb', `<b style="color:#1e293b;">${lv.reserve.title}</b> — ${lv.reserve.text}`) : '',
        lv.dead ? leverRow('NESTRĀDĀ', '#991b1b', '#fef2f2', `${lv.dead.text}${lv.dead.extra ? ` <span style="color:#64748b;">${lv.dead.extra}</span>` : ''}`, true) : '',
    ].join('');

    // ── Riski ────────────────────────────────────────────────────────────────
    const risksHtml = memo.risks.map((r, idx) => {
        const st = LEVEL_STYLE[r.level] || LEVEL_STYLE.zema;
        return `
        <div style="display:grid; grid-template-columns:118px minmax(0,1fr); gap:14px; padding:0.85rem 0; ${idx === memo.risks.length - 1 ? '' : 'border-bottom:1px solid #f1f5f9;'} align-items:start;">
            <span style="font-size:0.74rem; font-weight:800; background:${st.bg}; color:${st.col}; border-radius:6px; padding:4px 0; text-align:center; margin-top:2px; white-space:nowrap;">${r.count} ${r.count === 1 ? 'AVOTS' : 'AVOTI'} · ${r.level.toUpperCase()}</span>
            <span style="font-size:0.92rem; color:#334155; line-height:1.65;">${r.label ? `<b style="color:#991b1b;">${r.label}.</b> ` : ''}${r.text}<br><span style="color:#94a3b8; font-size:0.78rem;">Avoti: ${r.sources.join(' + ')}</span></span>
        </div>`;
    }).join('');

    // ── Traucējummeklēšana ───────────────────────────────────────────────────
    const troubleHtml = memo.trouble.length ? `
        <table style="width:100%; border-collapse:collapse; table-layout:fixed; font-size:0.9rem;">
            <tr style="background:#f8fafc;">
                <td style="padding:8px 12px; width:30%; color:#64748b; font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:1px;">Simptoms</td>
                <td style="padding:8px 12px; width:34%; color:#64748b; font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:1px;">Iespējamais cēlonis</td>
                <td style="padding:8px 12px; color:#64748b; font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:1px;">Rīcība</td>
            </tr>
            ${memo.trouble.map((t, i) => `
            <tr style="border-top:1px solid #f1f5f9; ${i % 2 ? 'background:#fcfcfd;' : ''}">
                <td style="padding:11px 12px; vertical-align:top; color:#1e293b; font-weight:600; line-height:1.6;">${t.symptom}</td>
                <td style="padding:11px 12px; vertical-align:top; color:#64748b; line-height:1.6;">${t.cause}</td>
                <td style="padding:11px 12px; vertical-align:top; color:#334155; line-height:1.6;">${t.action}</td>
            </tr>`).join('')}
        </table>` : '';

    // ── Pirmās 30 dienas un ritms ────────────────────────────────────────────
    const startHtml = memo.start30.map((t, i) => `
        <div style="display:flex; gap:11px; margin-bottom:0.75rem; align-items:flex-start;">
            <span style="background:#4f46e5; color:#fff; min-width:1.5rem; height:1.5rem; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.78rem; font-weight:800; flex-shrink:0; margin-top:1px;">${i + 1}</span>
            <span style="font-size:0.92rem; color:#334155; line-height:1.65;">${t}</span>
        </div>`).join('');
    const rhythmHtml = memo.rhythmLines.map(t => `
        <div style="display:flex; gap:10px; margin-bottom:0.7rem; align-items:flex-start;">
            <span style="flex-shrink:0; margin-top:2px;">🛠️</span>
            <span style="font-size:0.9rem; color:#475569; line-height:1.65;">${t}</span>
        </div>`).join('');

    // ── Ceļvedis uz pilnajiem paneļiem ───────────────────────────────────────
    const LINKS = [
        { label: '🕹️ Motivācijas sviras', id: 't3-motivacija' },
        { label: '🧭 Komunikācijas ceļvedis', id: 't3-celvedis' },
        { label: '🌑 Klupšanas akmeņi', id: 't3-klupsana' },
        { label: '🔬 Dziļā analīze', id: 't3-dzila' },
        { label: '🩺 Psihosomatika un misija', id: 't3-misija' },
    ];
    // Mērķa paneļi kopš 2026-07-10 ir sakļauti <details> bloki (id uz paša <details>) —
    // pirms ritināšanas bloks jāatver, citādi scrollIntoView ved uz aizvērtu rindiņu.
    const linksHtml = LINKS.map(l => `
        <button onclick="var el=document.getElementById('${l.id}'); if(el){var d=el.closest('details'); if(d) d.open=true; el.scrollIntoView({behavior:'smooth',block:'start'});}"
            style="background:#fff; border:1px solid #e2e8f0; border-radius:9px; padding:8px 15px; font-size:0.85rem; color:#4f46e5; font-weight:700; cursor:pointer; font-family:inherit;">${l.label} ↓</button>`).join('');

    const timeNote = memo.isTimeUnknown
        ? `Dzimšanas laiks nav norādīts — verdiktu un risku pārliecība pazemināta par vienu pakāpi. `
        : '';

    return `
    <div style="background:white; border-radius:14px; padding:1.4rem 1.5rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <div style="display:flex; align-items:baseline; gap:0.7rem; flex-wrap:wrap; margin-bottom:0.2rem;">
            <span style="font-size:1.3rem;">🎯</span>
            <h3 style="font-size:1.12rem; color:#1e293b; margin:0; font-weight:800;">Personības lietošanas instrukcija</h3>
            <span style="font-size:0.8rem; color:#64748b; font-weight:600;">vadītāja memorands</span>
        </div>
        <p style="margin:0; font-size:0.82rem; color:#94a3b8;">Koncentrāts no visas cilnes · skats: ko šis cilvēks dod tev</p>

        ${section('⚖️', 'Verdikti', 'kur šis aktīvs pelna un kur zaudē', '#475569', `
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:0.85rem;">${cardsHtml}</div>
        `)}

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:0 1.4rem;">
            ${section('💰', 'Ko viņš tev pelna', '', '#15803d', bulletList(memo.earns, '#15803d'))}
            ${section('🧾', 'Ko viņš tev maksā', '', '#b45309', bulletList(memo.costs, '#b45309'))}
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(380px, 1fr)); gap:0 1.4rem; align-items:start;">
            ${section('🕹️', 'Sviras', 'kā dabūt vairāk par to pašu cenu', '#7c3aed', leversHtml)}
            ${memo.risks.length ? section('⛔', 'Riski un saistības', 'kas garantiju anulē', '#b91c1c', risksHtml) : ''}
        </div>

        ${troubleHtml ? section('🔧', 'Ātrā traucējummeklēšana', 'simptoms → cēlonis → rīcība', '#0369a1', troubleHtml) : ''}

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:0 1.4rem;">
            ${section('⚡', 'Pirmās 30 dienas', '', '#4f46e5', startHtml)}
            ${section('🔄', 'Uzturēšanas ritms', '', '#0f766e', rhythmHtml)}
        </div>

        ${section('🧭', 'Pilnās sadaļas šajā cilnē', 'detalizētais saturs — viens klikšķis', '#64748b', `
            <div style="display:flex; flex-wrap:wrap; gap:8px;">${linksHtml}</div>
        `)}

        <div style="margin-top:1.2rem; padding-top:0.8rem; border-top:1px solid #f1f5f9; font-size:0.78rem; color:#94a3b8; line-height:1.6;">
            ℹ️ ${timeNote}Šīs ir personības profila <b>tendences, ne mērījumi vai diagnozes</b> — memorands ir lēmuma sākumpunkts, ne pamatojums. Konkrētība nāk no vairāku sistēmu sakritības, ne no atsevišķa horoskopa.
        </div>
    </div>`;
}
