// ── TICAMĪBAS NOZĪMĪTE (per-sadaļa) ──────────────────────────────────────────
// Renderē aprēķināto sadaļas ticamību: josla + % + līmeņa zvaigznes +
// izvēršams "Kāpēc / Kā paaugstināt" bloks. Caurspīdīgs, lai lietotājs zina,
// kur strādāt katrai sadaļai.

import { computeConfidence } from "../../logic/section_confidence.js?v=2";
import { descriptorFor } from "../../logic/confidence_registry.js?v=2";

// Precizitātes vārdnīca — viens avots (agrāk tab_experiment.js iekšienē).
export const precisionMeta = {
    'Visaugstākā':        { color: '#15803d', stars: '★★★★★', bg: '#f0fdf4' },
    'Augsta':             { color: '#4d7c0f', stars: '★★★★☆', bg: '#f7fee7' },
    'Mērena līdz Augsta': { color: '#b45309', stars: '★★★★☆', bg: '#fffbeb' },
    'Mērena':             { color: '#c2410c', stars: '★★★☆☆', bg: '#fff7ed' },
    'Zema':               { color: '#7e22ce', stars: '★★☆☆☆', bg: '#faf5ff' },
    'Nekāda':             { color: '#b91c1c', stars: '✕', bg: '#fef2f2' },
};

// Galvenais: ticamības nozīmīte no arhetipa atslēgas (vai gatava descriptora).
// archetypeKeyOrDescriptor: string (reģistra atslēga) vai descriptors objekts.
export function confidenceBadge(archetypeKeyOrDescriptor, profile, opts = {}) {
    const descriptor = typeof archetypeKeyOrDescriptor === 'string'
        ? descriptorFor(archetypeKeyOrDescriptor, opts)
        : (archetypeKeyOrDescriptor || {});
    if (opts.limits) descriptor.limits = opts.limits;   // sadaļas-specifisks ierobežojuma teksts
    const res = computeConfidence(descriptor, profile);
    if (!res.applicable) return '';
    return renderBadge(res, opts);
}

// Renderē jau aprēķinātu rezultātu (ja kāds grib computeConfidence atsevišķi).
export function renderBadge(res, opts = {}) {
    const m = precisionMeta[res.levelKey] || precisionMeta['Mērena'];
    const pct = res.pct;
    const compact = !!opts.compact;

    const factorRows = res.factors.map(f => {
        // score == null → informatīva rinda (piem., konverģence nav novērtēta) bez %
        const fp = f.score == null ? '—' : Math.round(f.score * 100) + '%';
        const hint = f.howToRaise
            ? `<div style="font-size:0.74rem; color:${m.color}; margin-top:1px;">↑ ${f.howToRaise}</div>`
            : '';
        return `<div style="margin:4px 0;">
            <div style="display:flex; justify-content:space-between; gap:0.5rem; font-size:0.76rem; color:#334155;">
                <span><b>${f.label}</b> <span style="color:#94a3b8;">— ${f.deltaTxt}</span></span>
                <span style="font-weight:700; color:#475569; font-variant-numeric:tabular-nums;">${fp}</span>
            </div>${hint}
        </div>`;
    }).join('');

    // "Ko vēl darīt / kas ierobežo" (2026-07-09 audits): ja nav neviena ↑ padoma,
    // godīgi pasaka, ka ar dotajām ieejām vairāk uzlabot nevar; ierobežojuma rinda
    // (no arhetipu reģistra) redzama VIENMĒR — arī pie 100% tā paskaidro griestus.
    const anyHow = res.factors.some(f => f.howToRaise);
    const howSummary = anyHow ? '' : `<div style="font-size:0.74rem; color:#15803d; margin-top:4px;">✓ Ar šobrīd norādītajiem datiem šo sadaļu vairāk uzlabot nevar.</div>`;
    const limitsRow = res.limits ? `<div style="font-size:0.74rem; color:#64748b; margin-top:5px; padding-top:5px; border-top:1px dashed ${m.color}30;"><b style="color:#475569;">Ierobežo:</b> ${res.limits}</div>` : '';

    return `<div style="margin-top:0.5rem; border-top:1px dashed #e2e8f0; padding-top:0.5rem;">
        <div style="display:flex; align-items:center; gap:0.5rem;">
            <span style="font-size:0.66rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Ticamība</span>
            <span style="flex:1; background:#f1f5f9; border-radius:3px; height:7px; overflow:hidden;"><span style="display:block; height:100%; width:${pct}%; background:${m.color};"></span></span>
            <span style="font-size:0.8rem; font-weight:800; color:${m.color}; font-variant-numeric:tabular-nums;">${pct}%</span>
            <span title="${res.levelKey}" style="font-size:0.72rem; color:${m.color}; letter-spacing:1px;">${m.stars}</span>
        </div>
        ${compact ? '' : `<details style="margin-top:3px;">
            <summary style="cursor:pointer; font-size:0.72rem; color:#64748b; list-style:none;">ℹ️ ${res.levelKey} · kāpēc un kā paaugstināt</summary>
            <div style="background:${m.bg}; border:1px solid ${m.color}30; border-radius:6px; padding:0.5rem 0.65rem; margin-top:4px;">
                ${factorRows}${howSummary}${limitsRow}
            </div>
        </details>`}
    </div>`;
}
