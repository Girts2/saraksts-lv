// Q4 — "Kas būs nākotnē?" — Ilgtermiņa paneļi (gadu desmiti)
// NB: renderWeek7Panel ('7 dienas' konsensa panelis) DZĒSTS 2026-07-08 — vairs netika
// renderēts (dublējās ar 'Dienas · biznesa pārskatu' day_business_view.js). Rezerve: _rezerves/.

// ── Ilgtermiņa paneļi (gadu desmiti): makro 10-gadu fāze + BaZi veiksmes pīlāri ──
// Vimshottari Dasha saraksts NOŅEMTS 2026-07-06 (dublējās ar koridora "Lielie Dzīves
// Cikli" — tā pati Vimshottari sistēma, tikai bagātāka 120-gadu vizualizācija).
export function renderLongTermPanels(profile) {
    // ── Luck Pillars ──
    const luckPillars = profile?.bazi?.luck_pillars || [];
    const birthDate   = profile?.birth_info?.date || '';
    const currentAge  = birthDate ? Math.floor((Date.now() - new Date(birthDate).getTime()) / (365.25*24*3600*1000)) : null;
    const relevantLP  = currentAge !== null ? luckPillars.filter(lp => (lp.ageEnd ?? 0) > currentAge) : luckPillars;

    const elemColors = { 'Koks':'#22c55e','Uguns':'#ef4444','Zeme':'#f59e0b','Metāls':'#94a3b8','Ūdens':'#3b82f6' };
    const lpHtml = relevantLP.slice(0, 5).map((lp, idx) => {
        const stemObj = (typeof lp.stem === 'object') ? lp.stem : { name: lp.stem || '—', element: '' };
        const branchStr = (typeof lp.branch === 'string') ? lp.branch.split(' ')[0] : (lp.branch?.name || '—');
        const elemColor = elemColors[stemObj.element] || '#64748b';
        const ip = lp.interpretation;
        const isCurrent = idx === 0;
        return `
        <div style="background:white; border-radius:12px; padding:1rem 1.2rem; border-left:4px solid ${elemColor}${isCurrent ? '' : '60'}; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.4rem;">
                <span style="font-weight:800; color:${elemColor}; font-size:0.95rem;">${stemObj.name} ${branchStr}</span>
                <div style="display:flex; gap:6px; align-items:center;">
                    ${isCurrent ? `<span style="background:${elemColor}; color:#fff; border-radius:5px; padding:1px 7px; font-size:0.66rem; font-weight:800;">AKTUĀLS</span>` : ''}
                    <span style="background:#f1f5f9; color:#475569; border-radius:6px; padding:2px 10px; font-size:0.78rem;">${lp.ageStart ?? '?'}–${lp.ageEnd ?? '?'} gadi</span>
                </div>
            </div>
            ${ip ? `<div style="color:#64748b; font-size:0.82rem; margin-top:6px;"><b>Fokuss:</b> ${ip.focus || '—'}</div>` : ''}
        </div>`;
    }).join('') || '<div style="color:#94a3b8; padding:1rem;">Veiksmes pīlāri nav aprēķināti</div>';

    // ── Strategic Timing (if available) ──
    const timing = profile?.timing || null;
    let timingHeroHtml = '';
    if (timing?.macro?.phaseMeta) {
        const m = timing.macro;
        const pm = m.phaseMeta;
        timingHeroHtml = `
        <div style="background:linear-gradient(135deg, ${pm.color}18 0%, white 100%); border:2px solid ${pm.color}30; border-radius:16px; padding:1.5rem 1.8rem; margin-bottom:1.5rem;">
            <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.5rem;">
                <span style="font-size:1.4rem;">${pm.icon}</span>
                <div style="font-size:0.72rem; font-weight:800; color:${pm.color}; text-transform:uppercase; letter-spacing:2px;">Makro horizonts · 10 gadu fāze</div>
            </div>
            <h3 style="margin:0 0 0.3rem 0; color:#1e293b; font-size:1.25rem; font-weight:900;">${pm.lv}</h3>
            <div style="color:#64748b; font-size:0.85rem; margin-bottom:0.9rem;">${m.ageStart}–${m.ageEnd} gadi <span style="color:#94a3b8;">(šobrīd ${m.currentAge})</span></div>
            <div style="color:#334155; font-size:0.92rem; line-height:1.75;">${pm.summary}.</div>
        </div>`;
    }

    return `
    <div style="max-width:1238px; margin:0 auto; width:100%;">

        <!-- Makro horizonts — 10 gadu fāze -->
        ${timingHeroHtml}

        <!-- BaZi Veiksmes Pīlāri -->
        <div style="margin-bottom:0.5rem;">
            <h3 style="font-size:1rem; color:#1e293b; font-weight:700; margin:0 0 1rem 0;">BaZi Veiksmes Pīlāri — 10 Gadu Laika Logi</h3>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                ${lpHtml}
            </div>
        </div>

    </div>`;
}
