import { renderSynergyPanel, getSystemSynthesis } from '../sections/synergy_panel.js?v=17';

export function renderTabKopsavilkums(profile) {
    const syn = getSystemSynthesis(profile);
    const lvList = arr => arr.length <= 1 ? (arr[0] || '') : arr.slice(0, -1).join(', ') + ' un ' + arr[arr.length - 1];
    const s    = profile.synergy || {};
    const ex   = profile.executive_summary || {};
    const trop = profile.astro_base?.tropical || {};
    const dm   = profile.bazi?.Daymaster || {};
    const maya = profile.maya_profile?.basic || {};
    const nak  = profile.vedic?.nakshatra || {};
    const dasha= profile.vedic?.current_dasha || {};
    const hell = profile.hellenistic?.data || {};
    const wp   = profile.western?.psychology || {};

    const chip = (label, val, bg, col) =>
        `<div style="background:${bg}; color:${col}; padding:6px 14px; border-radius:20px; font-size:0.82rem; font-weight:600; white-space:nowrap;">${label}: <b>${val}</b></div>`;

    const card = (title, icon, color, content, sysKey) => {
        const dom = sysKey && syn.dominant && syn.dominant.sys === sysKey;
        return `
        <div style="background:white; border-radius:14px; border-left:4px solid ${color}; padding:1.2rem 1.4rem; box-shadow:${dom ? '0 0 0 2px #6366f1, 0 2px 8px rgba(0,0,0,0.06)' : '0 2px 8px rgba(0,0,0,0.06)'};">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:0.8rem;">
                <span style="font-size:1.2rem;">${icon}</span>
                <b style="color:#1e293b; font-size:0.95rem;">${title}</b>
                ${dom ? `<span style="margin-left:auto; background:#eef2ff; color:#4f46e5; font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:9px; white-space:nowrap; letter-spacing:0.3px;">👑 VADOŠĀ</span>` : ''}
            </div>
            <div style="font-size:0.85rem; color:#475569; line-height:1.6;">${content}</div>
        </div>`;
    };

    const identityChips = `
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:1.5rem;">
            ${chip('Saule',    trop.sun       || '—', '#fef3c7', '#92400e')}
            ${chip('Mēness',   trop.moon      || '—', '#ede9fe', '#5b21b6')}
            ${chip('Asc',      trop.ascendant || '—', '#dbeafe', '#1e40af')}
            ${chip('BaZi',     (`${dm.polarity||''} ${dm.element||''}`).trim() || '—', '#f0fdf4', '#166534')}
            ${chip('Maiji',    (`${maya.tone||''} ${maya.sign||''}`).trim()    || '—', '#fff7ed', '#9a3412')}
            ${chip('Nakšatra', nak.nakshatra  || '—', '#fce7f3', '#9d174d')}
            ${chip('Dasha',    dasha.lord     || '—', '#f0f9ff', '#075985')}
            ${chip('Sekta',    hell.sect      || '—', '#f8fafc', '#475569')}
        </div>`;

    const systemCards = `
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
            ${card('Rietumu astroloģija', '🌍', '#3b82f6',
                `<b>Identitāte:</b> ${wp.identity||'—'}<br><b>Komunikācija:</b> ${wp.communication||'—'}`, 'Rietumu')}
            ${card('Vēdiskā astroloģija', '🌌', '#8b5cf6',
                `<b>Nakšatra:</b> ${nak.nakshatra||'—'} (${nak.lord||'—'})<br><b>Aktīvais Dasha:</b> ${dasha.lord||'—'}<br><b>Raksturs:</b> ${profile.vedic?.psychology?.egoStructure||'—'}`, 'Vēdiskā')}
            ${card('Ķīniešu BaZi', '🐉', '#ef4444',
                `<b>Daymaster:</b> ${dm.polarity||''} ${dm.element||''}<br><b>Galvenais Dievs:</b> ${profile.bazi?.mainGod||'—'}<br><b>Dziņa:</b> ${profile.bazi?.psychology?.['3. Dominējošais arhetips']||'—'}`, 'BaZi')}
            ${card('Maiju kalendārs', '☀️', '#10b981',
                `<b>Zīmogs:</b> ${maya.sign||'—'} (Tonis ${maya.tone||'—'})<br><b>Misija:</b> ${profile.maya_profile?.psychology?.['3. Dzīves misija']||'—'}`, 'Maiju')}
            ${card('Hellēnistiskā', '🏛️', '#f59e0b',
                `<b>Sekta:</b> ${hell.sect||'—'}<br><b>Temperaments:</b> ${hell.humor||'—'}<br><b>Raksturs:</b> ${profile.hellenistic?.psychology?.['1. Pamatraksturs (Ascendents)']||'—'}`)}
        </div>`;

    // Krustsistēmu konverģence/diverģence (kur tradīcijas saskan vs atšķiras + vadošā)
    const convergenceBlock = `
        <div style="background:white; border-radius:14px; padding:1.1rem 1.3rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #6366f1; margin-bottom:1rem;">
            <div style="font-size:0.92rem; font-weight:700; color:#1e293b; margin-bottom:0.6rem;">🧬 Cik ļoti tradīcijas saskan par tevi?</div>
            <div style="display:flex; flex-wrap:wrap; gap:11px;">
                <div style="flex:1 1 280px; min-width:260px; background:#f0fdf4; border-radius:9px; padding:10px 13px;">
                    <div style="font-size:0.72rem; font-weight:800; color:#166534; margin-bottom:4px; letter-spacing:0.3px;">🎯 STABILAIS KODOLS</div>
                    <div style="font-size:0.82rem; color:#334155; line-height:1.5;">${syn.stableCore.length
                        ? `Visas tradīcijas vienojas par tavu <b>${lvList(syn.stableCore)}</b>. Šīs ir tavas paredzamākās, neapstrīdamākās iezīmes — tās izpaužas konsekventi neatkarīgi no situācijas.`
                        : 'Tradīcijas pilnībā nesaskan ne par vienu pamatiezīmi — tavs profils ir izteikti daudzšķautņains, katra sistēma izceļ ko savu.'}</div>
                </div>
                <div style="flex:1 1 280px; min-width:260px; background:#fffbeb; border-radius:9px; padding:10px 13px;">
                    <div style="font-size:0.72rem; font-weight:800; color:#92400e; margin-bottom:4px; letter-spacing:0.3px;">🔄 MAINĪGĀS ŠĶAUTNES</div>
                    <div style="font-size:0.82rem; color:#334155; line-height:1.5;">${syn.contextDependent.length
                        ? `Par tavu <b>${lvList(syn.contextDependent)}</b> tradīcijas dod atšķirīgus signālus — tā izpaužas atkarībā no situācijas un konteksta, ne vienmēr vienādi.`
                        : 'Tradīcijas savā starpā nav izteikti pretrunīgas — tavs profils ir konsekvents dažādās dzīves situācijās.'}</div>
                </div>
            </div>
            ${syn.dominant ? `<div style="font-size:0.78rem; color:#475569; margin-top:9px;">👑 Vadošā tradīcija: <b style="color:#4f46e5;">${syn.dominant.sys}</b> (${syn.dominant.pct}%) — tā dod spēcīgākos signālus (iezīmēta zemāk ar 👑).</div>` : ''}
        </div>`;

    const synergySection = renderSynergyPanel(profile);

    const summaryText = ex.summary || '';

    return `
        <div style="max-width:1286px; margin:0 auto; padding-bottom:2rem;">
            <div style="background:linear-gradient(135deg, #1e293b, #334155); color:white; border-radius:16px; padding:2rem; margin-bottom:1.5rem;">
                <h2 style="margin:0 0 0.4rem 0; font-size:1.5rem; font-weight:800; letter-spacing:-0.5px;">Personības Kopsavilkums</h2>
                <p style="margin:0 0 1.2rem 0; color:#94a3b8; font-size:0.9rem;">5 astroloģijas sistēmas · Vienota matrica</p>
                ${identityChips}
                ${summaryText ? `<div style="background:rgba(255,255,255,0.08); border-radius:10px; padding:1rem; font-size:0.88rem; line-height:1.7; color:#cbd5e1;">${summaryText}</div>` : ''}
            </div>
            <h3 style="font-size:1rem; color:#475569; font-weight:600; margin:0 0 0.8rem 0; text-transform:uppercase; letter-spacing:1px;">Sistēmu Kopsavilkums</h3>
            ${convergenceBlock}
            ${systemCards}
            ${synergySection}
        </div>`;
}
