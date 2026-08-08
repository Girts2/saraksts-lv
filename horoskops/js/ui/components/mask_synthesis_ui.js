// ── SINTEZĒTĀS MASKAS RENDERIS ───────────────────────────────────────────────
// Galvenais skats: viena dominējošā stihija + modalitāte ("vidējais ticamākais
// rezultāts") ar godīgu % un sistēmu saskaņu. Detalizācija (katra signāla balss
// un godīgais pa-zīmēm sadalījums) paslēpta <details> blokā, lai galvenais skats
// dod skaidru atbildi, bet caurspīdīgums paliek vienu klikšķi attālumā.

import { computeMaskSynthesis, ELEMENT_META, ELEMENTS, MODALITY_META, HOUSE_GROUP_META } from "../../logic/mask_synthesis.js?v=6";
import { signDistBar } from "./dist_bar.js?v=3";

const pct = (f) => Math.round(f * 100);

// Mazas stihiju joslas (4 rindas)
function elementBars(dist, topElement) {
    return ELEMENTS.map((el) => {
        const p = pct(dist[el] || 0);
        const m = ELEMENT_META[el];
        const isTop = el === topElement;
        return `<div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:3px; ${isTop ? '' : 'opacity:0.65;'}">
            <span style="min-width:74px; font-size:0.78rem; font-weight:${isTop ? '800' : '500'}; color:#334155;">${m.icon} ${el}</span>
            <span style="flex:1; background:#f1f5f9; border-radius:3px; height:7px; overflow:hidden;"><span style="display:block; height:100%; width:${p}%; background:${m.color};"></span></span>
            <span style="min-width:34px; text-align:right; font-size:0.78rem; font-weight:700; color:${m.color}; font-variant-numeric:tabular-nums;">${p}%</span>
        </div>`;
    }).join("");
}

// Vienas sistēmas balss (detalizācijai)
function signalRow(s, topElement) {
    const sTop = Object.entries(s.elementDist).sort((a, b) => b[1] - a[1])[0];
    const agrees = sTop[0] === topElement;
    const m = ELEMENT_META[sTop[0]];
    return `<div style="display:flex; align-items:baseline; gap:0.5rem; font-size:0.76rem; margin:3px 0; color:#334155;">
        <span style="min-width:160px;"><b>${s.label}</b> <span style="color:#94a3b8;">×${s.weight}</span></span>
        <span style="color:${m.color}; font-weight:700;">${m.icon} ${sTop[0]} (${pct(sTop[1])}%)</span>
        <span style="color:${agrees ? '#15803d' : '#94a3b8'}; font-size:0.72rem;">${agrees ? '✓ saskan' : '· cita'}</span>
        <span style="flex:1; color:#94a3b8; font-size:0.72rem; font-style:italic;">${s.note || ''}</span>
    </div>`;
}

export function maskSynthesisBlock(profile, precomputed) {
    const r = precomputed || computeMaskSynthesis(profile);
    if (!r || !r.applicable) return "";

    const em = ELEMENT_META[r.topElement];
    const mm = MODALITY_META[r.topModality];
    const conf = r.confidencePct; // tīrā svērtā stihijas daļa (bez pūtuma)
    const confColor = conf >= 50 ? "#15803d" : conf >= 35 ? "#b45309" : "#c2410c";
    // Konverģences zvaigznes — atsevišķs uzticamības signāls (cik sistēmu saskan),
    // saskaņots ar "N no M" tekstu. Neuzpūš %.
    const agreeRatio = r.totalSignals ? r.agreeCount / r.totalSignals : 0;
    const stars = agreeRatio >= 0.8 ? "★★★" : agreeRatio >= 0.5 ? "★★☆" : "★☆☆";

    const headline = `
        <div style="display:flex; align-items:center; gap:0.7rem; flex-wrap:wrap;">
            <span style="font-size:1.5rem;">${em.icon}</span>
            <span style="font-size:1.05rem; font-weight:800; color:${em.color};">${r.topModality} ${r.topElement} maska</span>
            <span style="margin-left:auto; display:flex; align-items:center; gap:0.4rem;">
                <span style="font-size:0.66rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">ticamība</span>
                <span style="font-size:1.05rem; font-weight:800; color:${confColor}; font-variant-numeric:tabular-nums;">~${conf}%</span>
            </span>
        </div>`;

    const desc = `<div style="font-size:0.82rem; color:#475569; margin-top:0.4rem; line-height:1.5;">
        Pirmais iespaids: <b>${em.mask}</b>. Modalitāte <b>${mm.label}</b> — ${mm.desc}.
    </div>`;

    const converge = `<div style="display:flex; align-items:center; gap:0.4rem; font-size:0.78rem; color:#64748b; margin-top:0.5rem;">
        <span>🤝 <b>${r.agreeCount} no ${r.totalSignals}</b> neatkarīgām sistēmām norāda uz <b style="color:${em.color};">${r.topElement}</b> stihiju${r.agreeCount >= 2 ? ' — tās konverģē' : ''}.</span>
        <span title="Sistēmu saskaņa (konverģence)" style="margin-left:auto; color:${em.color}; letter-spacing:1px; font-size:0.8rem;">${stars}</span>
    </div>`;

    const detail = `
        <details style="margin-top:0.6rem;">
            <summary style="cursor:pointer; font-size:0.74rem; color:#64748b; list-style:none;">ℹ️ Kā tas aprēķināts — sistēmu balsis un godīgais pa-zīmēm sadalījums</summary>
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:0.6rem 0.7rem; margin-top:5px;">
                <div style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Svērtais stihiju balsojums</div>
                ${elementBars(r.elementDist, r.topElement)}
                <div style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin:8px 0 4px;">Atsevišķo sistēmu balsis</div>
                ${r.signals.map((s) => signalRow(s, r.topElement)).join("")}
                <div style="font-size:0.74rem; color:#94a3b8; font-style:italic; margin-top:8px; line-height:1.5;">
                    Pa atsevišķām zīmēm Ascendents bez precīza laika paliek nenosakāms (zemāk) — tāpēc rezultāts dots pa <b>stihijām</b>, kur vairākas sistēmas balso par vienu.
                </div>
                ${signDistBar(profile?.timeSweep?.vedic?.ascSignDist, { color: "#1d4ed8", title: "Ascendanta zīme · 24h sadalījums", desc: "Bez precīza dzimšanas laika pirmais iespaids pa zīmēm nav viennozīmīgi nosakāms." })}
            </div>
        </details>`;

    return `<div style="background:linear-gradient(135deg, ${em.color}0d, #f8fafc); border:1px solid ${em.color}40; border-left:3px solid ${em.color}; border-radius:8px; padding:0.8rem 0.9rem; margin-top:0.6rem;">
        <div style="font-size:0.66rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">🎭 Sintezētā maska · vidējais rezultāts (laiks nezināms)</div>
        ${headline}${desc}${converge}${detail}
    </div>`;
}

// ── VISPĀRĪGĀ ARHETIPA SINTĒZE (Anima/Patība/Rahu/Ketu) ──────────────────────
// Kompaktāks bloks: KVALITĀTE (stihija — parasti nosakāma) + JOMA (māja — līderis
// vai godīgi "nenosakāma"). synth = computeArchetypeSynthesis(...) rezultāts.
export function archetypeSynthesisBlock(synth, opts = {}) {
    if (!synth || !synth.applicable) return "";
    const color = opts.color || "#475569";
    const q = synth.quality;
    const d = synth.domain;

    let qualityLine = "";
    if (q) {
        const em = ELEMENT_META[q.topElement];
        const tag = q.determinate
            ? `<span style="color:#15803d; font-weight:700;">nosakāma</span>`
            : `<span style="color:${em.color}; font-weight:800;">~${q.topElementPct}%</span>`;
        qualityLine = `<div style="font-size:0.82rem; color:#334155; margin-bottom:3px;">
            ${em.icon} <b style="color:${em.color};">${q.topElement}</b> ${opts.qualityNoun || "stihija"} · ${tag}
        </div>`;
    }

    let domainLine = "";
    if (d) {
        const noun = opts.domainNoun || "joma";
        const nounCap = `${noun.charAt(0).toUpperCase()}${noun.slice(1)}`;
        if (d.decisive) {
            // Atsevišķa māja tiešām izceļas
            domainLine = `<div style="font-size:0.82rem; color:#334155;">🏠 Visticamākā ${noun}: <b>${d.topHouse}. māja</b> (~${d.topHousePct}%)</div>`;
        } else if (d.groupDecisive) {
            // Atsevišķa māja nav izšķirama, BET māju grupa koncentrējas
            const gm = HOUSE_GROUP_META[d.topGroup];
            domainLine = `<div style="font-size:0.82rem; color:#334155;">🏠 Visticamākais ${noun} tips: <b>${d.topGroup} mājas</b> (~${d.topGroupPct}%)<span style="color:#64748b;"> — ${gm ? gm.desc : ''}</span></div>`;
        } else {
            // Pat grupas izkliedētas → godīgi
            domainLine = `<div style="font-size:0.78rem; color:#92400e;">🏠 ${nounCap} bez precīza laika nav nosakāma — visas mājas izriet no nezināmā Ascendenta.</div>`;
        }
    }

    const detail = opts.rawDetailsHtml
        ? `<details style="margin-top:0.5rem;"><summary style="cursor:pointer; font-size:0.72rem; color:#64748b; list-style:none;">ℹ️ 24h sadalījums (detalizēti)</summary><div style="margin-top:4px;">${opts.rawDetailsHtml}</div></details>`
        : "";

    return `<div style="background:linear-gradient(135deg, ${color}0d, #f8fafc); border:1px solid ${color}33; border-left:3px solid ${color}; border-radius:8px; padding:0.6rem 0.75rem; margin-top:0.6rem;">
        ${opts.title ? `<div style="font-size:0.64rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">${opts.icon || ""} ${opts.title}</div>` : ""}
        ${qualityLine}${domainLine}${detail}
    </div>`;
}
