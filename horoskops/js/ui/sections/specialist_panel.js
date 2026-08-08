// 🧑‍⚕️ Speciālista ieteikums — virtuālā konsīlija panelis (t3 'Psiholoģija').
// Novietojums: starp 'Psiholoģiskā kopaina' un 'Personības lietošanas instrukcija'.
// Renderē logic/specialist_review.js sintēzi: konsīlija kopsavilkums (kur 4 skati
// saskan) + 4 speciālistu kartes, katrai 4 protokola sadaļas ar apakšvirsrakstiem
// un speciālista slēdzienu. Fail-safe: sadaļa bez datiem rāda godīgu piezīmi,
// panelis bez jebkādiem datiem — godīgu tukšo stāvokli (sk. [[audit-t3-fail-confident]]).

import { buildSpecialistReview } from '../../logic/specialist_review.js?v=3';

const LEVEL_STYLE = {
    high: { col: '#b91c1c', bg: '#fef2f2', bd: '#fecaca' },
    mid:  { col: '#b45309', bg: '#fffbeb', bd: '#fde68a' },
    ok:   { col: '#15803d', bg: '#f0fdf4', bd: '#bbf7d0' },
};

export function renderSpecialistPanel(profile) {
    const review = buildSpecialistReview(profile);

    const head = `
        <div style="display:flex; align-items:baseline; gap:0.7rem; flex-wrap:wrap; margin-bottom:0.35rem;">
            <span style="font-size:1.35rem;">🧑‍⚕️</span>
            <h3 style="font-size:1.12rem; color:#1e293b; margin:0; font-weight:800;">Speciālista ieteikums</h3>
            <span style="font-size:0.74rem; font-weight:800; color:#0e7490; background:#0e749014; border-radius:6px; padding:3px 9px; letter-spacing:0.5px;">virtuālais konsīlijs</span>
        </div>
        <p style="font-size:0.86rem; color:#64748b; line-height:1.6; margin:0 0 0.4rem; max-width:860px;">
            Četri virtuālie speciālisti izpēta šīs cilnes aprēķinus un katrs sniedz savu strukturēto slēdzienu par šo personu.
            Teksts ir algoritmiska astro-psiholoģisko datu interpretācija izglītojošā formā — <b>ne medicīniska diagnoze</b> un ne terapijas aizstājējs.
        </p>`;

    if (!review) {
        return `
    <div style="background:white; border-radius:14px; padding:1.4rem 1.5rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        ${head}
        <p style="font-size:0.9rem; color:#64748b; line-height:1.65; margin:0.4rem 0 0;">
            Šim profilam vēl nav aprēķināti pamatdati (personība, piesaiste, enkuri, psihosomatika), tāpēc konsīlijs slēdzienu nesniedz — labāk tukšs nekā viedoklis no vidējiem noklusējumiem.
        </p>
    </div>`;
    }

    // ── Laika brīdinājums (mājas/Ascendenta atkarīgie bloki ir orientējoši) ──
    const timeNote = review.isTimeUnknown ? `
        <div style="background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b; border-radius:10px; padding:0.7rem 1rem; margin:0.6rem 0 0; font-size:0.8rem; color:#92400e; line-height:1.5;">
            ⏳ <b>Dzimšanas laiks nezināms.</b> Sadaļas, kas balstās mājās un Ascendentā (Persona pret Patību, somatiskie H6/H8 marķieri), ir orientējošas; pārējie slēdzieni ir pilnvērtīgi.
        </div>` : '';

    // ── Konsīlija kopsavilkums ───────────────────────────────────────────────
    let consiliumHtml = '';
    if (review.consilium) {
        const pts = review.consilium.points.map(p => {
            const st = LEVEL_STYLE[p.level] || LEVEL_STYLE.mid;
            return `
            <div style="display:flex; gap:10px; align-items:flex-start; background:${st.bg}; border:1px solid ${st.bd}; border-radius:10px; padding:0.65rem 0.9rem;">
                <span style="color:${st.col}; font-weight:900; flex-shrink:0; margin-top:1px;">${p.icon}</span>
                <span style="font-size:0.86rem; color:#334155; line-height:1.6;">${p.text}</span>
            </div>`;
        }).join('');
        consiliumHtml = `
        <div style="background:linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border:1px solid #bae6fd; border-left:5px solid #0369a1; border-radius:12px; padding:1.1rem 1.25rem; margin:1rem 0 0;">
            <div style="font-size:0.72rem; font-weight:900; color:#0369a1; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:0.45rem;">Konsīlija kopsavilkums</div>
            <p style="font-size:0.88rem; color:#334155; line-height:1.65; margin:0 0 0.75rem;">${review.consilium.lead}</p>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">${pts}</div>
        </div>`;
    }

    // ── Speciālistu kartes ───────────────────────────────────────────────────
    const cardHtml = (sp) => {
        const sections = sp.sections.map(sec => `
            <div class="spec-sec">
                <div class="spec-sec-label" style="color:${sp.color};">${sec.label}
                    <span class="spec-sec-sub">· ${sec.sub}</span>
                </div>
                ${sec.text
                    ? `<div class="spec-sec-text">${sec.text}</div>`
                    : `<div class="spec-sec-empty">Šai sadaļai profilā pietrūkst ieejas datu — slēdziens netiek fabricēts.</div>`}
            </div>`).join('');
        const verdict = sp.verdict ? `
            <div class="spec-verdict" style="border-left-color:${sp.color}; background:${sp.color}0d;">
                <div class="spec-verdict-tag" style="color:${sp.color};">Slēdziens</div>
                <div class="spec-sec-text">${sp.verdict}</div>
            </div>` : '';
        return `
        <div class="spec-card" style="--sc:${sp.color};">
            <div class="spec-card-head" style="background:${sp.color}0e; border-left:4px solid ${sp.color};">
                <span class="spec-card-icon">${sp.icon}</span>
                <span class="spec-card-titles">
                    <span class="spec-card-title">${sp.title}</span>
                    <span class="spec-card-role">${sp.role}</span>
                </span>
            </div>
            <div class="spec-card-body">${sections}${verdict}</div>
        </div>`;
    };

    return `
    <style>
        .spec-grid{display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:1.1rem; margin-top:1.1rem;}
        @media(max-width:900px){ .spec-grid{grid-template-columns:1fr;} }
        .spec-card{border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; background:#fff; display:flex; flex-direction:column;}
        .spec-card-head{display:flex; align-items:center; gap:0.7rem; padding:0.8rem 1.1rem;}
        .spec-card-icon{font-size:1.35rem; flex-shrink:0;}
        .spec-card-titles{display:flex; flex-direction:column; min-width:0;}
        .spec-card-title{font-size:0.98rem; font-weight:800; color:#1e293b; line-height:1.25;}
        .spec-card-role{font-size:0.72rem; color:#94a3b8; line-height:1.35; margin-top:1px;}
        .spec-card-body{padding:1rem 1.15rem 1.15rem; display:flex; flex-direction:column; gap:0.95rem; flex:1;}
        .spec-sec-label{font-size:0.84rem; font-weight:800; margin-bottom:0.3rem; line-height:1.35;}
        .spec-sec-sub{font-size:0.72rem; font-weight:700; color:#94a3b8;}
        .spec-sec-text{font-size:0.88rem; color:#334155; line-height:1.68;}
        .spec-sec-text b{color:#1e293b;}
        .spec-sec-empty{font-size:0.82rem; color:#94a3b8; font-style:italic; line-height:1.5;}
        .spec-verdict{border-left:4px solid; border-radius:0 8px 8px 0; padding:0.65rem 0.9rem; margin-top:0.1rem;}
        .spec-verdict-tag{font-size:0.66rem; font-weight:900; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:3px;}
    </style>
    <div style="background:white; border-radius:14px; padding:1.4rem 1.5rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        ${head}
        ${timeNote}
        ${consiliumHtml}
        <div class="spec-grid">
            ${review.specialists.map(cardHtml).join('')}
        </div>
    </div>`;
}
