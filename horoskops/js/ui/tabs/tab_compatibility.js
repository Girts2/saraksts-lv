// horoskops/js/ui/tabs/tab_compatibility.js

import { calculateCompatibility } from '../../logic/compatibility.js?v=15';
import { calculateCompatConsensus } from '../../logic/compat_consensus.js?v=7';
import { calculateRelationshipForecast } from '../../logic/relationship_forecast.js?v=7';
import { generateRelationshipGuide } from '../../logic/compatibility_guide.js?v=11';
import { findCompatibleDates } from '../../logic/compatibility_finder.js?v=19';
import { generatePsychologistAdvice } from '../../logic/psychologist_advice.js?v=1';

// ── Kopdzīves simulācija (laika līnija) ──────────────────────────────────────
// Kalendārā X ass (reāli gadi, Jan-1 režģis ik 1 gads), daudzlīmeņu multileader
// izsaukumi ar piesaisti pie pīķa, ne-pārklājošas net-zonas, "TAGAD" marķieris.
const FC = { W: 900, padL: 74, padR: 118, plotH: 196 };
const fcClamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
// Zvaigznes = intensitāte 1–5 (zelta aizpildītas + pelēkas tukšās).
const fcStars = w => `<tspan fill="#f59e0b">${'★'.repeat(w.intensity)}</tspan><tspan fill="#d1d5db">${'★'.repeat(5 - w.intensity)}</tspan>`;
function fcWrap(s, maxChars, maxLines) {
    const words = (s || '').split(' '); const lines = []; let cur = '';
    for (const w of words) { const t = (cur ? cur + ' ' : '') + w; if (t.length <= maxChars) cur = t; else { if (cur) lines.push(cur); cur = w; } }
    if (cur) lines.push(cur);
    if (lines.length > maxLines) { lines.length = maxLines; lines[maxLines-1] = lines[maxLines-1].slice(0, maxChars-1) + '…'; }
    return lines;
}

function renderForecastTimeline(fc) {
    const v0 = fc.viewStartCal, v1 = fc.viewEndCal, span = Math.max(1, v1 - v0);
    const plotL = FC.padL, plotR = FC.W - FC.padR, plotW = plotR - plotL;
    const X = cal => plotL + (fcClamp(cal, v0, v1) - v0) / span * plotW;
    const climateAt = cal => fc.samples.reduce((b, x) => Math.abs(x.cal - cal) < Math.abs(b.cal - cal) ? x : b, fc.samples[0]).climate;

    // ── Izsaukumu lane-packing (daudzlīmeņu, ja posmi seko cits citam) ───────
    const Wb = 178;
    const build = w => {
        const lines = fcWrap(w.label, 28, 3);
        const peakX = X(w.peakCal);
        const bx = fcClamp(peakX - Wb / 2, 2, FC.W - Wb - 2);
        return { w, lines, peakX, bx, right: bx + Wb, boxH: 15 + lines.length * 11 + 6 };
    };
    const pack = arr => {
        const items = [...arr].sort((a, b) => b.intensity - a.intensity).slice(0, 4).map(build).sort((a, b) => a.peakX - b.peakX);
        const laneRight = [];
        for (const it of items) {
            let lane = 0;
            while (lane < laneRight.length && laneRight[lane] > it.bx - 6) lane++;
            it.lane = lane; laneRight[lane] = it.right;
        }
        return { items, lanes: Math.max(laneRight.length, 1), maxH: Math.max(0, ...items.map(i => i.boxH)) };
    };
    const topPack = pack(fc.growth), botPack = pack(fc.crises);
    const rowH = 62;
    // Atstarpe starp izsaukumu kastēm un grafika etiķetēm (TAGAD / ♥ sākums augšā,
    // "Kalendārais gads →" apakšā) — bez tās kastes nosedz šos apzīmējumus.
    const calloutGap = 24;
    const topArea = fc.growth.length ? topPack.lanes * rowH + 8 : 8;
    const botArea = fc.crises.length ? botPack.lanes * rowH + 8 : 8;
    const plotTop = topArea + 14 + calloutGap;
    const plotBot = plotTop + FC.plotH;
    const xLabelH = 20;
    const H = plotBot + xLabelH + botArea + 14 + calloutGap;
    const Y = v => plotBot - (v / 100) * (plotBot - plotTop);

    // Ne-pārklājošas NET zonas: katrs periods = viena krāsa (zaļa=neto labvēlīgs, sarkana=neto spriedze).
    let bands = '', runSign = 0, runStart = null, prevCal = v0;
    const emit = endCal => {
        if (runSign !== 0 && runStart !== null) {
            const x0 = X(runStart), x1 = X(endCal), col = runSign > 0 ? '#10b981' : '#ef4444';
            bands += `<rect x="${x0.toFixed(1)}" y="${plotTop.toFixed(1)}" width="${Math.max(2, x1 - x0).toFixed(1)}" height="${(plotBot - plotTop).toFixed(1)}" fill="${col}" opacity="0.11"/>`;
        }
    };
    for (const s of fc.samples) {
        const sign = s.net > 0.5 ? 1 : s.net < -0.5 ? -1 : 0;
        if (sign !== runSign) { emit(prevCal); runSign = sign; runStart = sign !== 0 ? s.cal : null; }
        prevCal = s.cal;
    }
    emit(prevCal);

    // Y režģis + verbālie enkuri + nosaukums
    let yGrid = '';
    [0, 25, 50, 75, 100].forEach(v => {
        const y = Y(v);
        yGrid += `<line x1="${plotL}" y1="${y.toFixed(1)}" x2="${plotR}" y2="${y.toFixed(1)}" stroke="${v===50?'#cbd5e1':'#eef2f7'}" stroke-width="1" ${v===50?'stroke-dasharray="2 4"':''}/>
            <text x="${(plotL-9).toFixed(1)}" y="${(y+3).toFixed(1)}" font-size="10" fill="#94a3b8" text-anchor="end">${v}</text>`;
    });
    const yVerbal = `
        <text x="${(plotL+6).toFixed(1)}" y="${(Y(96)+3).toFixed(1)}" font-size="9.5" fill="#10b981" font-weight="700">▲ harmonija</text>
        <text x="${(plotL+6).toFixed(1)}" y="${(Y(6)+3).toFixed(1)}" font-size="9.5" fill="#ef4444" font-weight="700">▼ krīze</text>`;
    const yTitle = `<text x="18" y="${((plotTop+plotBot)/2).toFixed(1)}" font-size="11" fill="#475569" font-weight="700" text-anchor="middle" transform="rotate(-90 18 ${((plotTop+plotBot)/2).toFixed(1)})">Attiecību klimats →</text>`;

    // Klimata laukums + līkne
    const pts = fc.samples.map(s => `${X(s.cal).toFixed(1)},${Y(s.climate).toFixed(1)}`);
    const line = `M ${pts.join(' L ')}`;
    const area = `${line} L ${X(fc.samples[fc.samples.length-1].cal).toFixed(1)},${plotBot.toFixed(1)} L ${X(fc.samples[0].cal).toFixed(1)},${plotBot.toFixed(1)} Z`;

    // Pagātnes ēnojums (kalendārs pirms šodienas)
    const pastShade = (fc.todayCal > v0)
        ? `<rect x="${plotL}" y="${plotTop.toFixed(1)}" width="${(X(fc.todayCal)-plotL).toFixed(1)}" height="${(plotBot-plotTop).toFixed(1)}" fill="#64748b" opacity="0.05"/>`
        : '';

    // ── Multileader izsaukumi: izaugsme AUGŠĀ, pārbaudes APAKŠĀ ──────────────
    const renderCallout = (it, isTop) => {
        const { w, lines, peakX, bx, boxH, lane } = it;
        const col = isTop ? '#047857' : '#dc2626', fill = isTop ? '#ecfdf5' : '#fef2f2', bd = isTop ? '#a7f3d0' : '#fecaca';
        const laneOff = lane * rowH;
        const boxBottom = isTop ? (plotTop - 10 - calloutGap - laneOff) : (plotBot + xLabelH + 8 + calloutGap + laneOff + boxH);
        const boxTop = boxBottom - boxH;
        const peakY = Y(climateAt(w.peakCal));
        const landY = isTop ? boxBottom : boxTop;                 // kastes mala pret līkni
        const cx = fcClamp(peakX, bx + 14, bx + Wb - 14);
        const range = w.startCal === w.endCal ? `${w.startCal}` : `${w.startCal}–${w.endCal}`;
        const lead = `<path d="M ${peakX.toFixed(1)} ${peakY.toFixed(1)} L ${cx.toFixed(1)} ${landY.toFixed(1)}" fill="none" stroke="${col}" stroke-width="1" opacity="0.55"/>
            <circle cx="${peakX.toFixed(1)}" cy="${peakY.toFixed(1)}" r="3.4" fill="${col}" stroke="#fff" stroke-width="1"/>`;
        const textLines = lines.map((ln, i) => `<text x="${(bx+9).toFixed(1)}" y="${(boxTop + 28 + i*11).toFixed(1)}" font-size="9.6" fill="#475569">${ln}</text>`).join('');
        const box = `<g>
            <rect x="${bx.toFixed(1)}" y="${boxTop.toFixed(1)}" width="${Wb}" height="${boxH}" rx="7" fill="${fill}" stroke="${bd}"/>
            <text x="${(bx+9).toFixed(1)}" y="${(boxTop+14).toFixed(1)}" font-size="10.5" font-weight="800" fill="${col}">${range} <tspan font-size="9">${fcStars(w)}</tspan></text>
            ${textLines}
        </g>`;
        return lead + box;
    };
    const topCallouts = topPack.items.map(it => renderCallout(it, true)).join('');
    const botCallouts = botPack.items.map(it => renderCallout(it, false)).join('');

    // Snapshot punkti (5/10/20)
    const snaps = Object.values(fc.snapshots).map(s => {
        const x = X(s.cal), y = Y(s.climate);
        return `<g><circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="4.5" fill="#6d28d9" stroke="#fff" stroke-width="2"/>
            <text x="${x.toFixed(1)}" y="${(y-8).toFixed(1)}" font-size="9" fill="#6d28d9" text-anchor="middle" font-weight="800">+${s.fromNow}g</text></g>`;
    }).join('');

    // X ass: režģis ik 1 GADS (Jan 1), reāli gadu skaitļi
    const y0 = Math.ceil(v0 - 1e-6), y1 = Math.floor(v1 + 1e-6);
    const labelStep = span <= 12 ? 1 : span <= 27 ? 2 : 5;
    let xAxis = `<line x1="${plotL}" y1="${plotBot.toFixed(1)}" x2="${plotR}" y2="${plotBot.toFixed(1)}" stroke="#cbd5e1" stroke-width="1.2"/>`;
    for (let yr = y0; yr <= y1; yr++) {
        const x = X(yr);
        const isLabel = (yr % labelStep === 0);
        xAxis += `<line x1="${x.toFixed(1)}" y1="${plotTop.toFixed(1)}" x2="${x.toFixed(1)}" y2="${plotBot.toFixed(1)}" stroke="#f1f5f9" stroke-width="1"/>
            <line x1="${x.toFixed(1)}" y1="${plotBot.toFixed(1)}" x2="${x.toFixed(1)}" y2="${(plotBot + (isLabel?5:3)).toFixed(1)}" stroke="#94a3b8" stroke-width="1"/>`;
        if (isLabel) xAxis += `<text x="${x.toFixed(1)}" y="${(plotBot+16).toFixed(1)}" font-size="9.5" fill="#64748b" text-anchor="middle">${yr}</text>`;
    }
    const xTitle = `<text x="${((plotL+plotR)/2).toFixed(1)}" y="${(plotBot+xLabelH+9).toFixed(1)}" font-size="10.5" fill="#475569" font-weight="700" text-anchor="middle">Kalendārais gads →</text>`;

    // "TAGAD" + attiecību sākuma marķieri (ja redzami)
    let nowMark = '';
    if (fc.todayCal >= v0 && fc.todayCal <= v1) {
        const nx = X(fc.todayCal);
        nowMark = `<line x1="${nx.toFixed(1)}" y1="${plotTop.toFixed(1)}" x2="${nx.toFixed(1)}" y2="${plotBot.toFixed(1)}" stroke="#6d28d9" stroke-width="1.6"/>
            <polygon points="${(nx-5).toFixed(1)},${(plotTop-7).toFixed(1)} ${(nx+5).toFixed(1)},${(plotTop-7).toFixed(1)} ${nx.toFixed(1)},${(plotTop-1).toFixed(1)}" fill="#6d28d9"/>
            <text x="${nx.toFixed(1)}" y="${(plotTop-9).toFixed(1)}" font-size="9.5" fill="#6d28d9" text-anchor="middle" font-weight="800">TAGAD</text>`;
    }
    let startMark = '';
    if ((fc.nowAge||0) > 0 && fc.relStartCal >= v0 && fc.relStartCal <= v1) {
        const sx = X(fc.relStartCal);
        startMark = `<line x1="${sx.toFixed(1)}" y1="${plotTop.toFixed(1)}" x2="${sx.toFixed(1)}" y2="${plotBot.toFixed(1)}" stroke="#ec4899" stroke-width="1.2" stroke-dasharray="4 3"/>
            <text x="${sx.toFixed(1)}" y="${(plotTop-9).toFixed(1)}" font-size="9" fill="#ec4899" text-anchor="middle" font-weight="700">♥ sākums</text>`;
    }

    return `<svg viewBox="0 0 ${FC.W} ${H.toFixed(0)}" style="width:100%; height:auto; display:block;" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Attiecību klimata prognoze pa kalendārajiem gadiem">
        <defs><linearGradient id="fcArea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#a78bfa" stop-opacity="0.5"/><stop offset="1" stop-color="#a78bfa" stop-opacity="0.04"/></linearGradient></defs>
        ${pastShade}${bands}${xAxis}${yGrid}${yVerbal}${yTitle}
        <path d="${area}" fill="url(#fcArea)"/>
        <path d="${line}" fill="none" stroke="#7c3aed" stroke-width="2.2" stroke-linejoin="round"/>
        ${snaps}${startMark}${nowMark}${xTitle}
        ${topCallouts}${botCallouts}
    </svg>`;
}

function renderForecastPanel(fc) {
    if (!fc) return '';
    const nowAge = fc.nowAge || 0;

    const panYears = fc.panYears || 0;
    const vFrom = Math.round(fc.viewStartCal), vTo = Math.round(fc.viewEndCal);

    // Cik ilgi kopā — pārslēdz enkuru (re-anchor); pan — pabīda skalu pa gadiem.
    // Pogas 0–5 gadi; ilgākam stāžam blakus ir manuālais gadu ievades lauks.
    const durOpts = [[0,'Tikko sākām'],[1,'1 gadu'],[2,'2 gadus'],[3,'3 gadus'],[4,'4 gadus'],[5,'5 gadus']];
    const isCustomDur = !durOpts.some(([n]) => n === nowAge);
    const controls = `
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:10px; background:rgba(255,255,255,0.7); border-radius:9px; padding:9px 13px;">
            <span style="font-size:0.82rem; font-weight:700; color:#475569;">Cik ilgi jūs jau esat kopā?</span>
            ${durOpts.map(([n,lbl]) => `<button onclick="window.setForecastDuration && window.setForecastDuration(${n})"
                style="cursor:pointer; border:1px solid ${nowAge===n?'#7c3aed':'#e2e8f0'}; background:${nowAge===n?'#7c3aed':'#fff'}; color:${nowAge===n?'#fff':'#475569'}; font-size:0.78rem; font-weight:700; padding:5px 11px; border-radius:99px;">${lbl}</button>`).join('')}
            <label style="display:inline-flex; align-items:center; gap:6px; font-size:0.78rem; font-weight:700; color:${isCustomDur?'#7c3aed':'#475569'};">vai ievadi:
                <input type="number" id="fc-custom-years" min="0" max="80" step="1" value="${isCustomDur ? nowAge : ''}" placeholder="gadi"
                    onchange="window.setForecastDuration && window.setForecastDuration(Math.max(0, Math.min(80, parseInt(this.value, 10) || 0)))"
                    onkeydown="if(event.key==='Enter'){this.blur();}"
                    style="width:70px; padding:4px 8px; border:1px solid ${isCustomDur?'#7c3aed':'#e2e8f0'}; background:${isCustomDur?'#f5f3ff':'#fff'}; color:#334155; border-radius:8px; font-size:0.8rem; font-weight:700; font-family:inherit; outline:none;">
                gadus</label>
        </div>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; background:rgba(255,255,255,0.7); border-radius:9px; padding:9px 13px;">
            <span style="font-size:0.82rem; font-weight:700; color:#475569;">Skalas logs: <b style="color:#6d28d9;">${vFrom}–${vTo}</b></span>
            <button onclick="window.setForecastPan && window.setForecastPan(-5)" style="cursor:pointer; border:1px solid #e2e8f0; background:#fff; color:#475569; font-size:0.78rem; font-weight:700; padding:5px 12px; border-radius:8px;">◀ agrāk (−5g)</button>
            <button onclick="window.setForecastPan && window.setForecastPan(5)" style="cursor:pointer; border:1px solid #e2e8f0; background:#fff; color:#475569; font-size:0.78rem; font-weight:700; padding:5px 12px; border-radius:8px;">vēlāk (+5g) ▶</button>
            ${panYears!==0 ? `<button onclick="window.setForecastPan && window.setForecastPan(${-panYears})" style="cursor:pointer; border:1px solid #ddd6fe; background:#f5f3ff; color:#6d28d9; font-size:0.76rem; font-weight:700; padding:5px 11px; border-radius:8px;">↺ atpakaļ uz tagad</button>` : ''}
        </div>`;

    // Ko mēra grafiks — paskaidrojums
    const explainer = `
        <details open style="margin-bottom:14px; background:rgba(255,255,255,0.75); border:1px solid #ece9fb; border-radius:9px;">
            <summary style="cursor:pointer; padding:10px 13px; font-size:0.82rem; font-weight:700; color:#6d28d9; list-style:none;">📖 Ko šis grafiks mēra?</summary>
            <div style="padding:2px 14px 13px; font-size:0.81rem; color:#475569; line-height:1.6;">
                <b>Vertikālā ass (Y)</b> = <b>attiecību klimats</b>: cik viegls vai sasprindzināts ir periods (augstu = harmonija, 50 = neitrāls, zemu = krīze).
                <b>Horizontālā ass (X)</b> = <b>reālais kalendārais gads</b> (režģis ik 1 gads). Violetā <b>TAGAD</b> līnija rāda šodienu; rozā <b>♥ sākums</b> — attiecību sākumu.
                Līkni veido <b>tranzīti</b> — kad lēnās planētas (Saturns, Plūtons, Urāns, Neptūns, Jupiters) skar jūsu <b>kopīgo kompozītkarti</b>.
                <b style="color:#047857;">Zaļie izsaukumi augšā</b> = labvēlīgie posmi; <b style="color:#dc2626;">sarkanie apakšā</b> = gaidāmās pārbaudes. Katrā izsaukumā <b>★ zvaigznes = intensitāte</b> (1–5), mērogota pret šī pāra spēcīgāko posmu — ★5 ir jūsu izteiktākais.
                <b>Krāsotās fona zonas</b> rāda katra perioda <b>neto</b> toni — zaļas (kopumā labvēlīgi) vai sarkanas (kopumā spriedze); tās <b>nepārklājas</b>, katrs gads ir viena krāsa.
            </div>
        </details>`;

    const snapCard = s => {
        const col = s.climate >= 58 ? '#10b981' : s.climate >= 45 ? '#f59e0b' : '#ef4444';
        return `<div style="flex:1; min-width:170px; background:#fff; border:1px solid #ece9fb; border-top:3px solid ${col}; border-radius:10px; padding:12px 14px;">
            <div style="display:flex; justify-content:space-between; align-items:baseline;">
                <b style="color:#334155; font-size:0.92rem;">Pēc ${s.fromNow} gadiem <span style="color:#94a3b8; font-weight:600; font-size:0.8rem;">(${s.year})</span></b>
                <span style="font-size:1.15rem; font-weight:900; color:${col};">${s.climate}%</span>
            </div>
            <div style="font-size:0.79rem; color:#475569; line-height:1.5; margin-top:5px;">${s.text}</div>
        </div>`;
    };
    const winRow = (w, isCrisis) => {
        const col = isCrisis ? '#dc2626' : '#047857';
        const bg = isCrisis ? '#fef2f2' : '#ecfdf5';
        const bd = isCrisis ? '#fecaca' : '#a7f3d0';
        const range = w.startCal === w.endCal ? `${w.startCal}` : `${w.startCal}–${w.endCal}`;
        return `<div style="display:flex; gap:10px; align-items:flex-start; background:${bg}; border:1px solid ${bd}; border-radius:8px; padding:9px 12px; margin-bottom:7px;">
            <div style="text-align:center; min-width:74px;">
                <div style="font-size:0.82rem; font-weight:800; color:${col};">${range}</div>
                <div style="font-size:0.72rem;" title="Intensitāte ${w.intensity}/5"><span style="color:#f59e0b;">${'★'.repeat(w.intensity)}</span><span style="color:#d1d5db;">${'★'.repeat(5-w.intensity)}</span></div>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700; color:#334155; font-size:0.84rem;">${w.label}</div>
                <div style="font-size:0.79rem; color:#475569; line-height:1.5;">${w.text}</div>
            </div>
        </div>`;
    };

    const crisisList = fc.crises.length
        ? fc.crises.sort((a,b)=>a.startYear-b.startYear).map(c => winRow(c, true)).join('')
        : `<div style="font-size:0.8rem; color:#64748b; padding:8px;">Šajā logā nav prognozētas spēcīgas pārbaudes no lēnajām planētām — labvēlīgi.</div>`;
    const growthList = fc.growth.length
        ? fc.growth.sort((a,b)=>a.startYear-b.startYear).slice(0,5).map(g => winRow(g, false)).join('')
        : `<div style="font-size:0.8rem; color:#64748b; padding:8px;">Nav izteiktu Jupitera izaugsmes logu šajā periodā.</div>`;

    // Tipiskie pāru posmi + sakritības (kalendārā)
    const normRows = (fc.normative || []).map(m => {
        const amp = m.astroOverlap;
        return `<div style="display:flex; gap:9px; align-items:flex-start; padding:7px 0; border-top:1px solid #f1f5f9;">
            <span style="font-size:1.1rem; line-height:1.3;">${m.icon}</span>
            <div style="flex:1;">
                <div style="font-size:0.82rem; color:#334155;"><b>${m.label}</b> <span style="color:#94a3b8;">· ${m.age[0]}–${m.age[1]}. attiecību gads · ~${Math.round(m.calStart)}–${Math.round(m.calEnd)}</span></div>
                <div style="font-size:0.78rem; color:#64748b; line-height:1.5;">${m.text}</div>
                ${amp ? `<div style="font-size:0.76rem; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:4px 9px; margin-top:4px;">⚠️ <b>Sakrīt ar astroloģisku pārbaudi:</b> ${amp.label} (${amp.startCal}–${amp.endCal}, intensitāte ${amp.intensity}/5) — tipiskais dzīves spiediens un planētu tranzīts saliekas kopā, tāpēc šis ir <b>visjutīgākais</b> posms.</div>` : ''}
            </div>
        </div>`;
    }).join('');

    const timeNote = fc.timeUnknown
        ? `<div style="font-size:0.74rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:7px 11px; margin-bottom:12px;">⏳ Vismaz vienam partnerim laiks nezināms — kompozīta Mēness daļa ir aptuvenāka, tāpēc emocionālo logu precīzs laiks var nedaudz nobīdīties. Lēno planētu (Saturns/Plūtons) logi paliek droši.</div>`
        : '';
    const ageNote = nowAge > 0
        ? `Jūs norādījāt, ka kopā jau <b>${nowAge} gadi</b>, tāpēc grafika kreisā (ēnotā) puse līdz <b>TAGAD</b> ir jūsu <b>pagātne</b> — varat pārbaudīt, vai atpazīstat tur iezīmētās pārbaudes. Labā puse ir nākotne. Ar <b>◀ / ▶</b> pogām varat pabīdīt skalu, lai redzētu tālākus gadus.`
        : `Grafiks sākas no šodienas. Ja jau dzīvojat kopā, augšā izvēlieties, cik gadus — tad redzēsiet arī pagātni. Ar <b>◀ / ▶</b> pogām varat pabīdīt skalu uz priekšu vai atpakaļ.`;

    return `
    <div style="margin-top:22px; background:linear-gradient(135deg,#f5f3ff 0%, #eef2ff 100%); border:2px solid #ddd6fe; border-radius:14px; padding:20px;">
        <div style="display:flex; align-items:center; gap:11px; flex-wrap:wrap; margin-bottom:10px;">
            <span style="font-size:1.6rem;">🔮</span>
            <div style="flex:1; min-width:200px;">
                <h4 style="margin:0; color:#6d28d9; font-size:1.12rem;">Kopdzīves simulācija laikā</h4>
                <div style="font-size:0.78rem; color:#64748b; line-height:1.4;">Kad attiecības tiks pārbaudītas un kad tām būs vējš mugurā — apvienojot planētu tranzītus uz jūsu kopīgo karti ar tipiskajiem pāru dzīves posmiem.</div>
            </div>
        </div>

        ${controls}
        ${explainer}
        ${timeNote}

        <!-- Klimata līkne -->
        <div style="background:#fff; border:1px solid #ece9fb; border-radius:11px; padding:10px 8px 4px; margin-bottom:8px; overflow-x:auto;">
            ${renderForecastTimeline(fc)}
        </div>
        <div style="display:flex; gap:14px; flex-wrap:wrap; font-size:0.72rem; color:#64748b; margin-bottom:8px; padding-left:4px;">
            <span style="display:flex; align-items:center; gap:5px;"><span style="width:14px; height:8px; background:#7c3aed; border-radius:2px;"></span> attiecību klimats</span>
            <span style="display:flex; align-items:center; gap:5px;"><span style="width:14px; height:8px; background:#10b981; opacity:0.25; border-radius:2px;"></span> neto labvēlīgs gads</span>
            <span style="display:flex; align-items:center; gap:5px;"><span style="width:14px; height:8px; background:#ef4444; opacity:0.25; border-radius:2px;"></span> neto spriedzes gads</span>
            <span style="display:flex; align-items:center; gap:5px;"><span style="color:#f59e0b;">★</span> = intensitāte (1–5)</span>
            <span style="display:flex; align-items:center; gap:5px;"><span style="color:#6d28d9; font-weight:700;">| TAGAD</span> · <span style="color:#ec4899; font-weight:700;">♥ sākums</span></span>
        </div>
        <div style="font-size:0.74rem; color:#64748b; line-height:1.5; margin-bottom:16px; padding-left:4px;">${ageNote}</div>

        <!-- Snapshoti pēc 5/10/20 gadiem -->
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:18px;">
            ${Object.values(fc.snapshots).map(snapCard).join('')}
        </div>

        <!-- Krīžu un izaugsmes logi -->
        <div style="display:flex; gap:18px; flex-wrap:wrap; margin-bottom:18px;">
            <div style="flex:1; min-width:280px;">
                <div style="font-size:0.86rem; font-weight:800; color:#dc2626; margin-bottom:8px;">⚠️ Gaidāmās pārbaudes (krīžu logi)</div>
                ${crisisList}
            </div>
            <div style="flex:1; min-width:280px;">
                <div style="font-size:0.86rem; font-weight:800; color:#047857; margin-bottom:8px;">🌱 Labvēlīgie logi (izaugsme)</div>
                ${growthList}
            </div>
        </div>

        <!-- Tipiskie pāru posmi -->
        <div style="background:rgba(255,255,255,0.7); border:1px solid #ece9fb; border-radius:11px; padding:13px 16px;">
            <div style="font-size:0.86rem; font-weight:800; color:#6d28d9; margin-bottom:4px;">🧭 Tipiskie pāru dzīves posmi (uzlikti uz astroloģiskās kartes)</div>
            <div style="font-size:0.76rem; color:#64748b; line-height:1.5; margin-bottom:4px;">Šie posmi nāk no attiecību psiholoģijas (nevis horoskopa) un attiecas uz vairumu pāru. Kur tie <b>sakrīt</b> ar planētu pārbaudi — spiediens pastiprinās.</div>
            ${normRows}
        </div>

        <div style="margin-top:14px; font-size:0.73rem; color:#94a3b8; line-height:1.55; background:rgba(255,255,255,0.6); border-radius:8px; padding:9px 13px;">
            ℹ️ Šī ir <b>tēmu un stresa logu laika karte</b>, ne deterministiska nākotnes pareģošana. Tā apvieno divus avotus: <b>(1)</b> reālu efemerīdu — kad lēnās planētas skar jūsu kopīgo kompozītkarti, un <b>(2)</b> tipiskos pāru attīstības posmus no attiecību psiholoģijas. Rezultātu vienmēr izšķir abu izvēles un ieguldītais darbs. Sākuma datums: ${fc.startDate}, šodiena: ${fc.anchorDate}.
        </div>
    </div>`;
}

// Async: aprēķina prognozi un ievieto konteinerā (#relationship-forecast-container).
// anchorYearsAgo — cik gadus pāris jau ir kopā (0 = tikko sākam); panYears — skalas pabīde.
// Kešo window._relForecastHtml + atver window.setForecastDuration / setForecastPan pogas.
export async function populateForecast(p1, p2, gender1, gender2, parihara, anchorYearsAgo = 0, panYears = 0) {
    if (!p1 || !p2) return;
    // Re-anchor pogas: "cik ilgi kopā" (atiestata pan) un "pabīdi skalu" (uzkrāj pan)
    window.setForecastDuration = (n) => populateForecast(p1, p2, gender1, gender2, parihara, n, 0);
    window.setForecastPan = (d) => populateForecast(p1, p2, gender1, gender2, parihara, anchorYearsAgo, panYears + d);
    const live = (typeof document !== 'undefined') && document.getElementById('relationship-forecast-container');
    try {
        if (live) live.innerHTML = forecastPlaceholder();
        const cons = calculateCompatConsensus(p1, p2, gender1, gender2, parihara);
        const baseline = cons?.overall?.pct ?? 60;
        const fc = await calculateRelationshipForecast(p1, p2, baseline, { anchorYearsAgo, panYears });
        const html = fc ? renderForecastPanel(fc)
            : `<div style="margin-top:22px; padding:14px; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; font-size:0.82rem; color:#9a3412;">Kopdzīves simulāciju neizdevās aprēķināt (trūkst kompozīta datu vai efemerīda).</div>`;
        window._relForecastHtml = html;
        const live2 = (typeof document !== 'undefined') && document.getElementById('relationship-forecast-container');
        if (live2) live2.innerHTML = html;
    } catch (e) {
        const el = (typeof document !== 'undefined') && document.getElementById('relationship-forecast-container');
        if (el) el.innerHTML = `<div style="margin-top:22px; padding:14px; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; font-size:0.82rem; color:#9a3412;">Kļūda simulācijā: ${e.message}</div>`;
    }
}

function forecastPlaceholder() {
    return `<div style="margin-top:22px; padding:24px; background:#f5f3ff; border:2px dashed #ddd6fe; border-radius:14px; text-align:center; color:#7c3aed; font-size:0.9rem;">
        🔮 Rēķinu kopdzīves simulāciju (planētu tranzīti uz jūsu kopīgo karti)…</div>`;
}

// ── Daudzsistēmu konsensa panelis (6 balsotāji) ──────────────────────────────
// Cilvēkam, kas par horoskopiem neko nezina: katrai asij plain-valodas apraksts,
// nosaukti sistēmu balsi (ne šifri), krāsu nozīme paskaidrota, signāli.
function renderConsensusPanel(cons) {
    if (!cons || cons.overall?.pct == null) return '';
    const o = cons.overall;
    const confPct = Math.round(o.confidence * 100);

    // Balsotāja čips ar CILVĒCISKO nosaukumu (ne burtu) + % + krāsu pēc līmeņa.
    const vColor = p => p >= 65 ? '#10b981' : p >= 50 ? '#22c55e' : p >= 35 ? '#f59e0b' : '#ef4444';
    const voterChip = v => `<span title="${v.what}" style="display:inline-flex; align-items:center; gap:4px; font-size:0.72rem; font-weight:600; padding:3px 9px; border-radius:99px; background:#fff; color:#334155; border:1px solid #e2e8f0; cursor:help;">
        <span style="font-size:0.85rem;">${v.icon}</span> ${v.name}
        <b style="color:${vColor(v.pct)};">${v.pct}%</b></span>`;

    // Viena ass = karte ar jautājumu, līmeni, joslu, plain-aprakstu un sistēmu balsīm.
    const axisCard = ax => {
        if (!ax || ax.pct == null) return '';
        const cConf = ax.confidence >= 0.75 ? '#10b981' : ax.confidence >= 0.55 ? '#f59e0b' : '#ef4444';
        const confTxt = ax.confidence >= 0.75 ? 'augsta' : ax.confidence >= 0.55 ? 'vidēja' : 'zema';
        return `
        <div style="background:#fff; border:1px solid #ece9fb; border-radius:11px; padding:14px 16px; margin-bottom:11px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                <div style="flex:1; min-width:170px;">
                    <div style="font-weight:800; color:#334155; font-size:0.96rem;">${ax.title}</div>
                    <div style="font-size:0.76rem; color:#94a3b8; line-height:1.4;">${ax.question}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.5rem; font-weight:900; color:${ax.color}; line-height:1;">${ax.pct}%</div>
                    <div style="font-size:0.74rem; font-weight:700; color:${ax.color}; text-transform:capitalize;">${ax.level}</div>
                </div>
            </div>
            <div style="background:#f1f5f9; border-radius:6px; height:9px; margin:9px 0 8px;">
                <div style="width:${ax.pct}%; height:9px; border-radius:6px; background:${ax.color}; transition:width .5s;"></div>
            </div>
            <div style="font-size:0.82rem; color:#475569; line-height:1.55; margin-bottom:9px;">${ax.narrative}</div>
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-size:0.7rem; color:#94a3b8; font-weight:600;">Sistēmu balsis:</span>
                ${ax.voters.map(voterChip).join('')}
                <span title="Pārliecība — cik sistēmas savā starpā saskan" style="font-size:0.7rem; color:${cConf}; font-weight:700; margin-left:auto; cursor:help;">pārliecība: ${confTxt}</span>
            </div>
        </div>`;
    };

    const sigColor = { warn: '#b45309', good: '#047857', info: '#475569' };
    const sigBg    = { warn: '#fffbeb', good: '#ecfdf5', info: '#f8fafc' };
    const sigBorder= { warn: '#fde68a', good: '#a7f3d0', info: '#e2e8f0' };
    const signalHtml = cons.signals.map(s => `
        <div style="display:flex; gap:9px; align-items:flex-start; background:${sigBg[s.type]}; border:1px solid ${sigBorder[s.type]}; border-radius:8px; padding:8px 11px; margin-bottom:6px;">
            <span style="font-size:1rem; line-height:1.3;">${s.icon}</span>
            <span style="font-size:0.8rem; color:${sigColor[s.type]}; line-height:1.5;">${s.text}</span>
        </div>`).join('');

    // Krāsu leģenda — ko nozīmē krāsas (cilvēkam, kas neko nezina).
    const colorLegend = `
        <div style="display:flex; gap:14px; flex-wrap:wrap; align-items:center; font-size:0.72rem; color:#64748b; margin-bottom:14px; background:rgba(255,255,255,0.6); border-radius:8px; padding:7px 12px;">
            <span style="font-weight:700; color:#475569;">Krāsas:</span>
            <span style="display:flex; align-items:center; gap:5px;"><span style="width:11px; height:11px; border-radius:3px; background:#10b981;"></span> spēcīga (65%+)</span>
            <span style="display:flex; align-items:center; gap:5px;"><span style="width:11px; height:11px; border-radius:3px; background:#22c55e;"></span> laba (50–64%)</span>
            <span style="display:flex; align-items:center; gap:5px;"><span style="width:11px; height:11px; border-radius:3px; background:#f59e0b;"></span> vidēja (35–49%)</span>
            <span style="display:flex; align-items:center; gap:5px;"><span style="width:11px; height:11px; border-radius:3px; background:#ef4444;"></span> vāja (zem 35%)</span>
        </div>`;

    // "Kā lasīt" — katra sistēma vienreiz paskaidrota.
    const systemsLegend = `
        <details style="margin-top:12px; background:rgba(255,255,255,0.65); border-radius:9px; border:1px solid #ece9fb;">
            <summary style="cursor:pointer; padding:9px 13px; font-size:0.78rem; font-weight:700; color:#6d28d9; list-style:none;">❔ Ko nozīmē šīs sistēmas? (klikšķini)</summary>
            <div style="padding:2px 14px 12px;">
                ${Object.values(cons.axes).flatMap(a => a?.voters || [])
                    .filter((v, i, arr) => arr.findIndex(x => x.system === v.system) === i)
                    .map(v => `<div style="display:flex; gap:8px; align-items:flex-start; padding:5px 0; border-top:1px solid #f1f5f9;">
                        <span style="font-size:1rem;">${v.icon}</span>
                        <div style="font-size:0.78rem; color:#475569; line-height:1.5;"><b style="color:#334155;">${v.name}.</b> ${v.what}.</div>
                    </div>`).join('')}
                <div style="font-size:0.74rem; color:#64748b; line-height:1.5; margin-top:8px; padding-top:8px; border-top:1px solid #f1f5f9;">
                    Katra ass apvieno tikai tās sistēmas, kas šo jomu mēra. <b>Pārliecība</b> ir augsta, kad sistēmas savā starpā saskan, un zema, kad tās nesaskan.
                </div>
            </div>
        </details>`;

    return `
    <div style="margin-top:6px; margin-bottom:22px; background:linear-gradient(135deg,#faf5ff 0%, #eff6ff 100%); border:2px solid #ddd6fe; border-radius:14px; padding:18px 20px;">
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
            <span style="font-size:1.6rem;">🧭</span>
            <div style="flex:1; min-width:200px;">
                <h4 style="margin:0; color:#6d28d9; font-size:1.12rem;">Daudzsistēmu konsenss</h4>
                <div style="font-size:0.78rem; color:#64748b; line-height:1.4;">Sešas astroloģijas un psiholoģijas sistēmas vērtē šo saderību — un mēs salīdzinām, kur tās saskan.</div>
            </div>
            <div style="text-align:center; padding:9px 18px; background:#fff; border:2px solid ${o.color}; border-radius:12px;">
                <div style="font-size:1.9rem; font-weight:900; color:${o.color}; line-height:1;">${o.pct}%</div>
                <div style="font-size:0.78rem; font-weight:700; color:${o.color};">${o.mark} ${o.tier}</div>
                <div style="font-size:0.66rem; color:#94a3b8;">pārliecība ${confPct}%</div>
            </div>
        </div>

        <!-- Kopvērtējuma plain skaidrojums -->
        <div style="font-size:0.84rem; color:#475569; line-height:1.6; background:rgba(255,255,255,0.7); border-radius:9px; padding:10px 14px; margin-bottom:14px;">${o.plain}</div>

        ${colorLegend}

        <!-- 3 asis kā kartes -->
        ${axisCard(cons.axes.emotional)}
        ${axisCard(cons.axes.values)}
        ${axisCard(cons.axes.interaction)}

        <!-- Signāli -->
        <div style="margin-top:14px;">
            <div style="font-size:0.82rem; font-weight:800; color:#6d28d9; margin-bottom:7px;">⚡ Signāli — kur sistēmas saskan un kur brīdina</div>
            ${signalHtml}
        </div>

        ${systemsLegend}
    </div>`;
}

// ── Ģimenes psihologa rekomendāciju panelis (2 kolonnas: vīrietis | sieviete) ──
function renderPsychologistPanel(advice) {
    if (!advice) return '';

    const axisColor = p => p >= 70 ? '#10b981' : p >= 50 ? '#f59e0b' : '#ef4444';
    const axisBar = a => {
        const isFocus = a.key === advice.focus.key;
        const c = axisColor(a.pct);
        return `
        <div style="flex:1; min-width:150px;">
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.74rem; color:#334155; margin-bottom:3px;">
                <b>${a.label}${isFocus ? ' <span style="color:#0e7490;">⬅ fokuss</span>' : ''}</b>
                <span style="color:${c}; font-weight:800;">${a.pct}%</span>
            </div>
            <div style="background:#e2e8f0; border-radius:4px; height:6px;">
                <div style="width:${a.pct}%; height:6px; border-radius:4px; background:${c}; transition:width .4s;"></div>
            </div>
        </div>`;
    };

    const cardHtml = (card, accent) => `
        <div style="background:#fff; border:1px solid #e7eaf0; border-left:3px solid ${accent}; border-radius:8px; padding:9px 11px; margin-bottom:7px;">
            <div style="font-weight:700; color:#334155; font-size:0.81rem; margin-bottom:3px;">${card.icon} ${card.title}</div>
            <div style="font-size:0.79rem; color:#475569; line-height:1.5;">${card.body}</div>
        </div>`;

    const column = (side, accent, bg) => `
        <div style="flex:1; min-width:280px; background:${bg}; border:2px solid ${accent}; border-radius:12px; padding:15px;">
            <div style="display:flex; align-items:flex-start; gap:10px; margin-bottom:11px;">
                <span style="font-size:1.7rem; color:${accent}; line-height:1;">${side.icon}</span>
                <div>
                    <div style="font-weight:800; color:${accent}; font-size:1rem;">Ieteikumi ${side.title.toLowerCase() === 'vīrietis' ? 'vīrietim' : 'sievietei'}
                        <span style="color:#94a3b8; font-weight:600; font-size:0.74rem;">· ${side.persona}. persona</span>
                    </div>
                    <div style="font-size:0.77rem; color:#64748b; line-height:1.45; margin-top:2px;">${side.read}</div>
                </div>
            </div>
            ${side.cards.map(c => cardHtml(c, accent)).join('')}
            ${side.caution ? `<div style="margin-top:4px; font-size:0.75rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:6px 10px; line-height:1.45;">⚠️ ${side.caution}</div>` : ''}
        </div>`;

    return `
    <div style="margin-top:22px; background:linear-gradient(135deg,#ecfeff 0%, #f0f9ff 100%); border:2px solid #a5f3fc; border-radius:14px; padding:20px;">
        <style>.pa-angle{display:block; margin-top:5px; padding:4px 8px; background:#ecfeff; border-radius:6px; color:#0e7490; font-weight:600; font-size:0.76rem;}</style>

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
            <span style="font-size:1.5rem;">👨‍⚕️</span>
            <h4 style="margin:0; color:#0e7490; font-size:1.05rem;">Ģimenes psihologa rekomendācijas</h4>
        </div>
        <p style="margin:0 0 14px 0; font-size:0.82rem; color:#475569; line-height:1.55;">
            Personalizēti ieteikumi, kas ņem vērā <b>abu kopējo attiecību dinamiku</b> un <b>katra individuālo
            psiholoģisko profilu</b> (piesaistes stilu, konfliktu paternus, dabiskās stiprās puses).
        </p>

        <!-- Kopējais fokuss: 3 asis 5% solī -->
        <div style="background:rgba(255,255,255,0.7); border-radius:10px; padding:12px 14px; margin-bottom:16px;">
            <div style="font-size:0.78rem; font-weight:700; color:#0e7490; margin-bottom:8px;">
                Kopējais fokuss · galvenais darba lauks šobrīd: <b>${advice.focus.label}</b> (${advice.focus.pct}%) ·
                balsts: <b>${advice.strength.label}</b> (${advice.strength.pct}%)
            </div>
            <div style="display:flex; gap:14px; flex-wrap:wrap;">
                ${advice.axes.map(axisBar).join('')}
            </div>
        </div>

        <!-- 2 kolonnas -->
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            ${column(advice.left,  advice.left.gender  === 'M' ? '#1d4ed8' : '#be185d', advice.left.gender  === 'M' ? '#eff6ff' : '#fdf2f8')}
            ${column(advice.right, advice.right.gender === 'M' ? '#1d4ed8' : '#be185d', advice.right.gender === 'M' ? '#eff6ff' : '#fdf2f8')}
        </div>

        <div style="margin-top:14px; font-size:0.74rem; color:#64748b; line-height:1.5; background:rgba(255,255,255,0.55); border-radius:8px; padding:8px 12px;">
            ℹ️ Šī nav medicīniska diagnoze, bet strukturēts atskaites punkts sarunai. Avoti: Bowlby piesaistes teorija,
            Gotmana attiecību pētījumi un Vēdiskā Ashta Kuta saderība. Mainot dzimumu, laiku vai metodi, ieteikumi pārrēķinās.
        </div>
    </div>`;
}

export function renderTabCompatibilityResults(p1, p2) {
    if (!p1 || !p2) return '';

    // Sinhronizē dzimumu no UI radio pogām pirms aprēķina
    const g1Radio = document.querySelector('input[name="gender-1"]:checked');
    const g2Radio = document.querySelector('input[name="gender-2"]:checked');
    if (g1Radio) window.currentGender1 = g1Radio.value;
    if (g2Radio) window.currentGender2 = g2Radio.value;

    const gender1 = window.currentGender1 || 'M';
    const gender2 = window.currentGender2 || 'F';
    const parihara = window.currentPariharaMode !== false;   // noklusējums: Tradicionālā (ar Dosha Parihara)
    const comp  = calculateCompatibility(p1, p2, gender1, gender2, parihara);
    const cons  = calculateCompatConsensus(p1, p2, gender1, gender2, parihara);
    const guide = generateRelationshipGuide(p1, p2, comp);
    const advice = generatePsychologistAdvice(p1, p2, comp, gender1, gender2);

    // Metodes slēdzis: Stingrā (tīrs BPHS) ↔ Tradicionālā (ar Dosha Parihara)
    const methodToggle = `
        <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:18px; padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
            <span style="font-size:0.82rem; font-weight:700; color:#475569;">⚖️ Aprēķina metode:</span>
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.82rem; color:#334155; font-weight:600;">
                <input type="radio" name="compat-method" value="strict" onchange="setCompatibilityMethod(false)" ${!parihara ? 'checked' : ''}>
                Stingrā <span style="color:#94a3b8; font-weight:400;">(tīrs BPHS)</span>
            </label>
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.82rem; color:#334155; font-weight:600;">
                <input type="radio" name="compat-method" value="parihara" onchange="setCompatibilityMethod(true)" ${parihara ? 'checked' : ''}>
                Tradicionālā <span style="color:#94a3b8; font-weight:400;">(ar Dosha Parihara)</span>
            </label>
            <span style="font-size:0.74rem; color:#94a3b8; flex-basis:100%;">
                ${parihara
                    ? 'Bhakut/Nadi doša tiek neitralizēta, ja zīmju valdnieki ir draugi vai zīmes/nakšatras sakrīt — saudzīgāks, tradicionāls skats.'
                    : 'Doša netiek neitralizēta — godīgāks, stingrāks skats, kas rāda arī vājās puses.'}
            </span>
        </div>`;

    const g1 = window.currentGender1 || 'M';
    const g2 = window.currentGender2 || 'F';
    const icon1 = g1 === 'M' ? '♂' : '♀';
    const icon2 = g2 === 'M' ? '♂' : '♀';
    const color1 = g1 === 'M' ? '#1d4ed8' : '#be185d';
    const color2 = g2 === 'M' ? '#1d4ed8' : '#be185d';
    const label1 = g1 === 'M' ? 'Vīrietis' : 'Sieviete';
    const label2 = g2 === 'M' ? 'Vīrietis' : 'Sieviete';

    // Viendzimuma pāris: Ashta Kuta dzimumu loma (īpaši Varna) ir balstīta uz vīrietis/sieviete
    // hierarhiju. Ja abi vienādi, sistēma vienu pieņem par "augstāko" — to atklāti apzīmējam.
    const sameSexNote = (g1 === g2) ? `
        <div style="margin-bottom:18px; padding:10px 14px; background:#fff7ed; border:1px solid #fed7aa; border-left:4px solid #f97316; border-radius:0 10px 10px 0; font-size:0.8rem; color:#9a3412; line-height:1.5;">
            ⚠️ <b>Viendzimuma pāris:</b> Vēdiskā Ashta Kuta ir vēsturiski veidota vīrietis–sieviete attiecībām, un daži faktori
            (īpaši Varna — sociālais līmenis) pieņem dzimumu hierarhiju. Šeit 1. persona tiek pieņemta par atskaites pusi.
            Skaitļus interpretējiet ar šo ierobežojumu prātā; psiholoģiskā komponente (Big-Five / piesaiste) ir dzimumneitrāla.
        </div>` : '';

    return `
        <div style="background:#fffcfd; padding:20px; border-radius:12px; border:1px solid #FADADD; margin-top:20px;">
            <!-- Personas galvene ar dzimumiem -->
            <div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px; padding:10px 16px; border-radius:10px; background:${g1==='M'?'#eff6ff':'#fdf2f8'}; border:2px solid ${color1}; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.8rem; color:${color1};">${icon1}</span>
                    <div>
                        <div style="font-weight:700; color:${color1}; font-size:1rem;">1. Persona — ${label1}</div>
                        <div style="font-size:0.8rem; color:#64748b;">${p1.astro_base?.tropical?.sun || ''} · ${p1.bazi?.Daymaster?.element || ''}</div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; font-size:1.5rem; color:#d946ef; font-weight:700;">💞</div>
                <div style="flex:1; min-width:200px; padding:10px 16px; border-radius:10px; background:${g2==='M'?'#eff6ff':'#fdf2f8'}; border:2px solid ${color2}; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.8rem; color:${color2};">${icon2}</span>
                    <div>
                        <div style="font-weight:700; color:${color2}; font-size:1rem;">2. Persona — ${label2}</div>
                        <div style="font-size:0.8rem; color:#64748b;">${p2.astro_base?.tropical?.sun || ''} · ${p2.bazi?.Daymaster?.element || ''}</div>
                    </div>
                </div>
            </div>

            <!-- Viendzimuma pāra piezīme (ja attiecas) -->
            ${sameSexNote}

            <!-- Metodes slēdzis -->
            ${methodToggle}

            <!-- Daudzsistēmu konsenss (6 balsotāji) — primārā analīze, pilnā platumā -->
            <h4 style="color:#d946ef; margin-top:0; margin-bottom:0.6rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <span>Dziļā Saderības Analīze</span>
                <span style="font-size:0.8rem; padding:4px 8px; background:#FFD700; color:#713f12; border-radius:6px; border:1px solid #ca8a04;">
                    Ashta Kuta: <b>${comp.timeAveraged ? (Math.round(comp.kutaScore * 10) / 10).toFixed(1) : Math.round(comp.kutaScore)} / 36</b>
                </span>
            </h4>
            ${renderConsensusPanel(cons)}

            <!-- Kopdzīves simulācija laikā (async — aizpildās ar populateForecast) -->
            <div id="relationship-forecast-container">${(typeof window !== 'undefined' && window._relForecastHtml) || forecastPlaceholder()}</div>

            <!-- Partnerības ceļvedis (pilnā platumā zem konsensa) -->
            <div style="display:flex; gap:20px; flex-wrap:wrap; margin-top:4px;">
                <div style="flex:1; min-width:300px; background:#FADADD; padding:20px; border-radius:12px; border:1px solid #fecdd3;">
                    <h4 style="color:#be185d; margin-top:0; display:flex; align-items:center; gap:8px;">
                        <span style="font-size:1.2rem;">💖</span> Partnerības Ceļvedis
                    </h4>
                    <div style="background:rgba(255,255,255,0.6); padding:12px; border-radius:8px; margin-bottom:12px;">
                        <b style="color:#9f1239; font-size:0.9rem;">⚠️ Kritiskais Punkts</b>
                        <p style="margin:4px 0 0 0; font-size:0.85rem; color:#4c1d95; line-height:1.4;">${guide.criticalPoint}</p>
                    </div>
                    <div style="background:rgba(255,255,255,0.6); padding:12px; border-radius:8px; margin-bottom:12px;">
                        <b style="color:#b45309; font-size:0.9rem;">🗣️ Mīlestības Valodas</b>
                        <ul style="margin:4px 0 0 0; padding-left:20px; font-size:0.85rem; color:#431407;">
                            <li style="margin-bottom:4px;"><b>1. Persona:</b> ${guide.loveLanguages.p1.type} – <i>${guide.loveLanguages.p1.desc}</i></li>
                            <li><b>2. Persona:</b> ${guide.loveLanguages.p2.type} – <i>${guide.loveLanguages.p2.desc}</i></li>
                        </ul>
                    </div>
                    <div style="background:rgba(255,255,255,0.6); padding:12px; border-radius:8px;">
                        <b style="color:#1e3a8a; font-size:0.9rem;">🛑 Konfliktu Svira</b>
                        <p style="margin:4px 0 0 0; font-size:0.85rem; color:#172554; line-height:1.4;">${guide.conflictLever}</p>
                    </div>
                </div>

            </div><!-- /flex wrapper -->

            <!-- Ģimenes psihologa rekomendācijas (2 kolonnas) -->
            ${renderPsychologistPanel(advice)}

            <!-- Ashta Kuta detalizētais sadalījums pārcelts uz 'Saderība' paneļa kreiso kolonnu (renderKutaCompact) -->


        </div>`;
}

// ── Saderības gauge (270° loks, 0–36 Ashta Kuta) ─────────────────────────────
// 500px māksliniecisks spidometrs. Krāsu zonas no GAUGE_TIERS (vienots 5-zonu avots).
// Adata plūstoši animējas (inerce) caur CSS transition.
const G = { W: 540, H: 442, cx: 270, cy: 248, Rband: 175, bandW: 28 };

function polarXY(cx, cy, r, deg) {
    const a = deg * Math.PI / 180;
    return { x: cx + r * Math.cos(a), y: cy + r * Math.sin(a) };
}
// v=0 → 135° (apakšā pa kreisi), v=36 → 405°/45° (apakšā pa labi); 270° loks ar atveri apakšā.
function gaugeValToAngle(v) { return 135 + (Math.max(0, Math.min(36, v)) / 36) * 270; }
function gaugeArc(cx, cy, r, v0, v1) {
    const a0 = gaugeValToAngle(v0), a1 = gaugeValToAngle(v1);
    const p0 = polarXY(cx, cy, r, a0), p1 = polarXY(cx, cy, r, a1);
    const large = (a1 - a0) > 180 ? 1 : 0;
    return `M ${p0.x.toFixed(2)} ${p0.y.toFixed(2)} A ${r} ${r} 0 ${large} 1 ${p1.x.toFixed(2)} ${p1.y.toFixed(2)}`;
}

// ── 5 saderības zonas (vienots avots gauge skalai, leģendai, verdiktam un detalizētajam panelim) ──
// Robežas 18/21/25/29 sakrīt ar partneru meklētāja līmeņiem. Apraksti — plašā valodā, BEZ žargona,
// lai cilvēks, kas neko nezina par sistēmu, saprot, ko skaitlis nozīmē attiecībām.
const GAUGE_TIERS = [
    { min: 29, name: 'Izcila',    range: '29–36', color: '#8b5cf6', mark: '✨',
      desc: 'Gandrīz ideāla dabiskā saderība — retums. Cilvēki saprotas teju bez vārdiem. Tomēr arī šeit ilgtermiņu izšķir abu ieguldītais darbs.' },
    { min: 25, name: 'Ļoti laba', range: '25–28', color: '#0ea5e9', mark: '🔵',
      desc: 'Spēcīga saikne un savstarpēja sapratne. Domstarpības atrisinās ātri un bez dziļiem aizvainojumiem.' },
    { min: 21, name: 'Laba',      range: '21–24', color: '#10b981', mark: '🟢',
      desc: 'Stabils, veselīgs pamats. Šeit atrodas lielākā daļa veiksmīgu pāru — atšķirības ir, bet pārvaramas.' },
    { min: 18, name: 'Vidēja',    range: '18–20', color: '#f59e0b', mark: '🟡',
      desc: 'Pamats ir pietiekams, bet bez rezerves. Ikdienā gaidāma regulāra berze; vajadzīga pacietība un atklātas sarunas.' },
    { min: 0,  name: 'Zema',      range: '0–17',  color: '#ef4444', mark: '🔴',
      desc: 'Dabiskā saderība ir vāja. Attiecības iespējamas, bet prasīs daudz apzināta darba, kompromisu un, iespējams, ārēju atbalstu.' }
];

// Izvēršamais Ashta Kuta skaidrojums (paneļa galvenē). Statisks HTML.
const ASHTA_KUTA_FACTORS = [
    ['Varna (1 punkts) — Garīgā un sociālā daba', 'Mēra abu partneru ego attīstību, garīgo līmeni un to, kā viņi raugās uz saviem pienākumiem dzīvē.'],
    ['Vashya (2 punkti) — Savstarpējā pievilkšanās un ietekme', 'Parāda, kurš attiecībās būs dominējošais un vai starp partneriem pastāvēs dabisks magnētisms un spēja vienam otru uzklausīt.'],
    ['Tara (3 punkti) — Liktenis un veselība (Zvaigžņu saderība)', 'Analizē abu partneru biolauku mijiedarbību — vai viņi viens otram nesīs labklājību, ilgu mūžu un veiksmi, vai arī radīs enerģētisku izsīkumu.'],
    ['Yoni (4 punkti) — Seksuālā un fiziskā saderība', 'Katru Nakšatru simbolizē kāds dzīvnieks (piemēram, zirgs, lauva, kaķis, čūska). Šis faktors mēra fizisko pievilkšanos, intīmo saderību un bioloģisko ritmu sakritību.'],
    ['Graha Maitri (5 punkti) — Prāta un intelektuālā draudzība', 'Salīdzina abu partneru Mēness zīmju valdniekus (planētas). Parāda, vai cilvēkiem būs kopīgas intereses, līdzīgs skatījums uz dzīvi un vai viņi spēs būt labi draugi.'],
    ['Gana (6 punkti) — Temperaments un psihes tips', 'Iedala cilvēkus trīs kategorijās: Deva (dievišķais/mierīgais), Manushya (cilvēciskais/ambiciozais) un Rakshasa (dēmoniskais/valdonīgais). Parāda, vai abu dzīves ritmi un uzvedības modeļi būs saskanīgi.'],
    ['Bhakoot (7 punkti) — Emocionālā un finansiālā stabilitāte', 'Analizē attālumu starp abu Mēness zīmēm. Tas ir kritiski svarīgs faktors, kas nosaka kopējo laimi, emocionālo saišu dziļumu un spēju kopā veidot labklājību. Negatīvs rādītājs rada Bhakoot Dosha (emocionālu distanci).'],
    ['Nadi (8 punkti) — Ģenētiskā un nervu sistēmas saderība', 'Visvairāk punktu nesošais un svarīgākais faktors. Tas mēra abu partneru konstitūciju (Vēdu medicīnā jeb Ājurvēdā pazīstamu kā Vata, Pitta, Kapha bioenerģijas). Ja Nadi sakrīt, veidojas Nadi Dosha, kas var norādīt uz veselības problēmām, nervu pārslodzi vai grūtībām radīt pēcnācējus.']
];
const ASHTA_KUTA_INFO = `
    <p>Ashta Kuta (tulkojumā no sanskrita: <i>Astoņi vaļi</i> jeb <i>Astoņi rādītāji</i>) ir viena no slavenākajām un ietekmīgākajām metodēm Vēdu astroloģijā (Jyotish), ko izmanto divu cilvēku — parasti vīrieša un sievietes — savstarpējās saderības un laulības potenciāla noteikšanai.</p>
    <p>Šī sistēma analizē un salīdzina abu partneru Mēness pozīcijas (Nakšatras jeb Mēness zvaigznājus) dzimšanas brīdī. Tā kā Vēdu astroloģijā Mēness atbild par prātu (Manas), emocijām, zemapziņu un ikdienas uztveri, Ashta Kuta būtībā parāda to, cik viegli vai grūti diviem cilvēkiem būs līdzāspastāvēt un saprasties psiholoģiskā un enerģētiskā līmenī.</p>
    <p>Sistēmu veido 8 faktori (Kutas), un katram no tiem ir sava skaitliskā vērtība jeb svars (no 1 līdz 8). Kopā tie veido maksimālo rezultātu — <b>36 punktus</b>.</p>
    <h5>Astoņi "Ashta Kuta" faktori un to nozīme</h5>
    ${ASHTA_KUTA_FACTORS.map(([head, body]) => `<div class="ak-factor"><b>${head}.</b> ${body}</div>`).join('')}
`;

// Abu metožu skaidrojumi (divi bloki zem rezultātu čipiem).
const METHOD_STRICT_TEXT = `Stingrā metode (tīrs BPHS) ir vajadzīga viena galvenā iemesla dēļ: tā fiksē objektīvo realitāti un parāda patieso "starta kapitālu", ar kādu divi cilvēki ienāk attiecībās. Senatnē (un arī mūsdienu ļoti ortodoksālajā astroloģijā) stingro metodi izmantoja kā primāro, nepielūdzamo filtru. Ja sistēma uzrādīja kritisku nesaderību pēc stingrās metodes, tas bija nopietns brīdinājums, ka attiecībās viss būs jānopelna ar smagu darbu un nekas nenotiks "pats no sevis". Tā neļauj pārim ieslīgt ilūzijās. Seno tekstu burtiska lasīšana (Stingrā metode) pieprasīja ideālu saderību, jo laulība bija neatgriezenisks ekonomisks un sociāls solis, kurā sievietei un vīrietim nebija izvēles tiesību.`;
const METHOD_TRAD_TEXT = `Tradicionālā metode astroloģiskajā saderības analīzē ir dzīva, elastīga un praktiska pieeja, ko gadsimtiem ilgi izmantojuši praktizējoši astrologi Indijā un visā pasaulē. Tīrā matemātiskā formula (Stingrā metode) pati par sevi ir pārāk ierobežojoša, jo neņem vērā reālās dzīves nianses un astroloģiskos izņēmumus. Ja Stingrā metode darbojas kā robots pēc principa "Redzu problēmu — atņemu punktus", tad Tradicionālā metode darbojas kā pieredzējis tiesnesis vai ārsts: tā meklē kontekstu, izņēmumus un mīkstinošos apstākļus. Tradicionālā metode, kas attīstījusies cauri paaudzēm, ņem vērā mūsdienu cilvēka psiholoģiju un brīvo gribu. Tā uzskata, ka zems punktu skaits nav "lāsts", bet gan norāde uz mājasdarbu, kas abiem jāveic.`;

// Kopīgi atvasinājumi (lieto gan render, gan dzīvā patchošana applyGaugeState)
function gaugeTier(s)    { return GAUGE_TIERS.find(t => s >= t.min) || GAUGE_TIERS[GAUGE_TIERS.length - 1]; }
function gaugeColor(s)   { return gaugeTier(s).color; }
function gaugeVerdict(s) { return gaugeTier(s).name + ' saderība'; }
function gaugeFmt(s, ta) { return ta ? (Math.round(s * 10) / 10).toFixed(1) : String(Math.round(s)); }
// Adata zīmēta uz augšu (270°); pagrieziens = mērķa leņķis − 270 (±135° pār skalu).
function needleDelta(s)  { return gaugeValToAngle(s) - 270; }

// Vienots gauge datu avots: abas metodes PILNĀ aprēķinā (lai būtu gan kopskaiti, gan aktīvās metodes
// 8-Kuta sadalījums kreisajai kolonnai, gan tradicionālās neitralizēto sarakts piezīmei).
function gaugeData(p1, p2) {
    const gender1 = (typeof window !== 'undefined' && window.currentGender1) || 'M';
    const gender2 = (typeof window !== 'undefined' && window.currentGender2) || 'F';
    const parihara = (typeof window !== 'undefined') ? (window.currentPariharaMode !== false) : true; // VIENOTS ar "Dziļā Saderības Analīze"; default Tradicionālā
    const strictC = calculateCompatibility(p1, p2, gender1, gender2, false);   // pilna
    const tradC   = calculateCompatibility(p1, p2, gender1, gender2, true);    // pilna
    const activeC = parihara ? tradC : strictC;
    const neutralized = (tradC?.kutaBreakdown || []).filter(k => k.neutralized).map(k => k.name);
    return {
        parihara,
        activeComp:   activeC,
        breakdown:    activeC ? activeC.kutaBreakdown : [],
        score:        activeC ? activeC.kutaScore : 0,
        strictScore:  strictC ? strictC.kutaScore : 0,
        tradScore:    tradC   ? tradC.kutaScore   : 0,
        timeAveraged: activeC ? activeC.timeAveraged : false,
        neutralized
    };
}

// 8-Kuta saraksts (gauge paneļa kreisā kolonna). Katra rinda izvēršama — zem tās dziļais apraksts
// (kutaDesc: summary/detail/practice), lai dati un skaidrojums būtu vienuviet.
function renderKutaCompact(breakdown, timeAveraged) {
    const fmt = v => timeAveraged ? (Math.round(v * 10) / 10).toFixed(1) : Math.round(v);
    return (breakdown || []).map(k => {
        const rowPct = k.max > 0 ? Math.round((k.score / k.max) * 100) : 0;
        const dot = rowPct >= 70 ? '#10b981' : rowPct >= 40 ? '#f59e0b' : '#ef4444';
        const kd = k.kutaDesc || {};
        return `
        <details class="gk-row">
            <summary class="gk-sum">
                <div class="gk-top">
                    <span class="gk-name">${k.name}${k.neutralized ? ' <span class="gk-neut" title="Neitralizēts ar Parihara">⚖</span>' : ''}</span>
                    <span class="gk-score" style="color:${dot};">${fmt(k.score)}<span class="gk-max">/${k.max}</span></span>
                </div>
                <div class="gk-track"><div class="gk-fill" style="width:${rowPct}%; background:${dot};"></div></div>
                <div class="gk-lvrow">
                    <span class="gk-lv">${k.lv}</span>
                    <span class="gk-toggle">skaidrojums <span class="gk-pm"></span></span>
                </div>
            </summary>
            <div class="gk-desc">
                ${k.desc ? `<div class="gk-chip">${k.desc}</div>` : ''}
                ${kd.summary ? `<p class="gk-d-sum">${kd.summary}</p>` : ''}
                ${kd.detail ? `<p class="gk-d-det">${kd.detail}</p>` : ''}
                ${kd.practice ? `<p class="gk-d-prac"><b>Praksē:</b> ${kd.practice}</p>` : ''}
            </div>
        </details>`;
    }).join('');
}

function renderCompatibilityGauge(score, timeAveraged) {
    const { cx, cy, Rband, bandW } = G;
    const s = Math.max(0, Math.min(36, score || 0));
    const tier = gaugeTier(s);
    const color = tier.color;
    const verdict = gaugeVerdict(s);
    const scoreFmt = gaugeFmt(s, timeAveraged);
    const Rin = Rband - bandW / 2, Rout = Rband + bandW / 2;

    // Pelēkā pamata sliede (dziļums zem zonām, noapaļoti gali)
    const track = `<path d="${gaugeArc(cx,cy,Rband, 0, 36)}" fill="none" stroke="#eef1f6" stroke-width="${bandW + 8}" stroke-linecap="round"/>`;

    // 5 krāsainās zonas ar maziem atstatumiem pie robežām 18/21/25/29 (butt gali → arī šaurās zonas redzamas)
    const zoneArc = (v0, v1, col) => `<path d="${gaugeArc(cx,cy,Rband, v0, v1)}" fill="none" stroke="${col}" stroke-width="${bandW}"/>`;
    const zones = `<g filter="url(#gShadow)">
        ${zoneArc(0.4, 17.7, '#ef4444')}
        ${zoneArc(18.3, 20.7, '#f59e0b')}
        ${zoneArc(21.3, 24.7, '#10b981')}
        ${zoneArc(25.3, 28.7, '#0ea5e9')}
        ${zoneArc(29.3, 35.6, '#8b5cf6')}
    </g>`;

    // Skala: mazās iedaļas ik 3 + nosvītrotie robežu skaitļi (0,18,21,25,29,36) tieši ārpus joslas
    const bounds = [0, 18, 21, 25, 29, 36];
    let ticks = '';
    for (let v = 0; v <= 36; v += 3) {
        if (bounds.includes(v)) continue;
        const a = gaugeValToAngle(v);
        const i = polarXY(cx, cy, Rout + 3, a), o = polarXY(cx, cy, Rout + 8, a);
        ticks += `<line x1="${i.x.toFixed(1)}" y1="${i.y.toFixed(1)}" x2="${o.x.toFixed(1)}" y2="${o.y.toFixed(1)}" stroke="#cbd5e1" stroke-width="1.4" stroke-linecap="round"/>`;
    }
    bounds.forEach(v => {
        const a = gaugeValToAngle(v);
        const i = polarXY(cx, cy, Rout + 3, a), o = polarXY(cx, cy, Rout + 12, a);
        const lbl = polarXY(cx, cy, Rout + 25, a);
        ticks += `<line x1="${i.x.toFixed(1)}" y1="${i.y.toFixed(1)}" x2="${o.x.toFixed(1)}" y2="${o.y.toFixed(1)}" stroke="#64748b" stroke-width="2.2" stroke-linecap="round"/>`;
        ticks += `<text x="${lbl.x.toFixed(1)}" y="${lbl.y.toFixed(1)}" font-size="13" font-weight="700" fill="#475569" text-anchor="middle" dominant-baseline="middle">${v}</text>`;
    });

    // Zonu NOSAUKUMI uz ārējās malas (katras zonas viduspunktā, zonas krāsā) — pašpaskaidrojoši
    const zoneName = (v, t) => {
        const p = polarXY(cx, cy, Rout + 44, gaugeValToAngle(v));
        return `<text x="${p.x.toFixed(1)}" y="${p.y.toFixed(1)}" font-size="13" font-weight="800" fill="${t.color}" text-anchor="middle" dominant-baseline="middle">${t.name}</text>`;
    };
    const names = zoneName(9, gaugeTier(9)) + zoneName(19.5, gaugeTier(19)) + zoneName(23, gaugeTier(23))
                + zoneName(27, gaugeTier(27)) + zoneName(32.5, gaugeTier(32));

    // Adata (zīmēta uz augšu; pagriežas ar CSS). Slaida smaile + neliels pretsvars.
    const L = Rin - 14;
    const delta = needleDelta(s);
    const needle = `<g id="gauge-needle" class="gauge-needle" style="transform:rotate(${delta.toFixed(2)}deg);" filter="url(#gNeedleShadow)">
        <polygon points="${cx-7},${cy} ${cx},${(cy-L).toFixed(1)} ${cx+7},${cy}" fill="url(#gNeedle)"/>
        <polygon points="${cx-5},${cy} ${cx},${cy+26} ${cx+5},${cy}" fill="#475569"/>
    </g>`;

    // Statiskā rumba (negriežas)
    const hub = `
        <circle cx="${cx}" cy="${cy}" r="18" fill="url(#gHub)" stroke="#fff" stroke-width="2"/>
        <circle cx="${cx}" cy="${cy}" r="6" fill="#e2e8f0"/>`;

    // Centra teksts (atverē zem rumbas) — bez žargona: skaitlis + plašvalodas verdikts
    const centerText = `
        <text x="${cx}" y="298" font-size="12" font-weight="700" fill="#94a3b8" text-anchor="middle" letter-spacing="2.5">REZULTĀTS</text>
        <text id="gauge-score" data-gscore="${s}" x="${cx}" y="352" font-size="60" font-weight="900" fill="${color}" text-anchor="middle">${scoreFmt}<tspan font-size="24" fill="#94a3b8" font-weight="700"> / 36</tspan></text>
        <text id="gauge-verdict" x="${cx}" y="388" font-size="19" font-weight="800" fill="${color}" text-anchor="middle">${tier.mark} ${verdict}</text>`;

    return `<svg viewBox="0 0 ${G.W} ${G.H}" style="width:min(540px,100%); height:auto; display:block;" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Saderība ${scoreFmt} no 36 — ${verdict}">
        <defs>
            <linearGradient id="gNeedle" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#475569"/><stop offset="1" stop-color="#0f172a"/></linearGradient>
            <radialGradient id="gHub" cx="0.4" cy="0.35" r="0.7"><stop offset="0" stop-color="#64748b"/><stop offset="1" stop-color="#1e293b"/></radialGradient>
            <filter id="gShadow" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="3" stdDeviation="5" flood-color="#0f172a" flood-opacity="0.16"/></filter>
            <filter id="gNeedleShadow" x="-50%" y="-50%" width="200%" height="200%"><feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#0f172a" flood-opacity="0.3"/></filter>
        </defs>
        ${track}${zones}${ticks}${names}${needle}${hub}${centerText}
    </svg>`;
}

// Dzīvā gauge atjaunināšana BEZ pārrenderēšanas — adata plūstoši pārvietojas (inerce), skaitlis
// skaitās, verdikts/krāsa/pārslēdzis sinhronizējas. Atgriež false, ja SVG nav DOM (tad izsaucējs
// pārrenderē). Lieto pārslēdzot Stingrā ↔ Tradicionālā.
export function applyGaugeState(p1, p2) {
    if (typeof document === 'undefined' || !p1 || !p2) return false;
    const needle  = document.getElementById('gauge-needle');
    const scoreEl = document.getElementById('gauge-score');
    if (!needle || !scoreEl) return false;

    const { score, timeAveraged, parihara, breakdown } = gaugeData(p1, p2);
    const s = Math.max(0, Math.min(36, score));
    const color = gaugeColor(s);

    // 1) Adata — atslēdz ieejas keyframes, lai transition (inerce) pārvalda kustību no pašreizējā leņķa
    needle.style.animation = 'none';
    needle.style.transform = `rotate(${needleDelta(s).toFixed(2)}deg)`;

    // 2) Verdikts + krāsas
    const verdictEl = document.getElementById('gauge-verdict');
    if (verdictEl) { verdictEl.textContent = `${gaugeTier(s).mark} ${gaugeVerdict(s)}`; verdictEl.setAttribute('fill', color); }
    scoreEl.setAttribute('fill', color);

    // 3) Skaitļa plūstošā skaitīšana (rAF), aptuveni saskaņota ar adatas kustību
    const from = parseFloat(scoreEl.getAttribute('data-gscore')) || 0;
    scoreEl.setAttribute('data-gscore', String(s));
    const numNode = scoreEl.firstChild;   // teksta mezgls pirms <tspan> / 36
    const reduce = (typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    if (numNode) {
        if (reduce || from === s) {
            numNode.nodeValue = gaugeFmt(s, timeAveraged);
        } else {
            const dur = 1200, t0 = performance.now(), ease = x => 1 - Math.pow(1 - x, 3);
            const frame = now => {
                const k = Math.min(1, (now - t0) / dur);
                numNode.nodeValue = gaugeFmt(from + (s - from) * ease(k), timeAveraged);
                if (k < 1) requestAnimationFrame(frame);
            };
            requestAnimationFrame(frame);
        }
    }

    // 4) Pārslēdža pogu stāvoklis + ARIA
    const sBtn = document.querySelector('.gauge-switch .gs-strict');
    const tBtn = document.querySelector('.gauge-switch .gs-trad');
    if (sBtn) { sBtn.classList.toggle('active', !parihara); sBtn.setAttribute('aria-pressed', String(!parihara)); }
    if (tBtn) { tBtn.classList.toggle('active',  parihara); tBtn.setAttribute('aria-pressed', String(parihara)); }

    // 4b) Tīrais/Koriģētais salīdzinājuma čipi — pārvieto izcēlumu (skaitļi paliek fiksēti)
    const gcS = document.getElementById('gc-strict');
    const gcT = document.getElementById('gc-trad');
    if (gcS) gcS.classList.toggle('active', !parihara);
    if (gcT) gcT.classList.toggle('active',  parihara);

    // 5) Kreisā kolonna: 8-Kuta sadalījums (mainās ar metodi — Bhakut/Nadi neitralizācija)
    const kutaList = document.getElementById('gauge-kuta-list');
    if (kutaList) kutaList.innerHTML = renderKutaCompact(breakdown, timeAveraged);

    return true;
}

// Panelis "Saderība": liels metodes pārslēdzis + gauge. Default = Stingrā (BPHS), neatkarīgs no
// galvenās analīzes slēdža (kas noklusējumā ir Tradicionālā). Atgriež konteinera IEKŠĒJO saturu.
export function renderGaugePanel(p1, p2) {
    if (!p1 || !p2) return '';
    const parihara = (typeof window !== 'undefined') ? (window.currentPariharaMode !== false) : true; // VIENOTS ar "Dziļā Saderības Analīze"; default Tradicionālā
    const { strictScore, tradScore, timeAveraged, neutralized, breakdown, score: active } = gaugeData(p1, p2);
    const fmt = v => timeAveraged ? (Math.round(v * 10) / 10).toFixed(1) : String(Math.round(v));
    // Piezīmi vada REĀLĀ delta (ne modālais neutralized karogs): nezināmam laikam doša var būt
    // neitralizēta tikai daļā 24h sadalījuma → skaitļi atšķiras pat ja modālais stāvoklis nav.
    // Vienīgais avots metožu starpībai kodā ir Bhakut/Nadi Parihara, tāpēc nosaukums vienmēr drošs.
    const delta = Math.round((tradScore - strictScore) * 10) / 10;
    const names = neutralized.length ? neutralized.join(', ') : 'Bhakut/Nadi';
    const noteHtml = delta >= 0.05
        ? `Tradicionālā neitralizē <b>${names}</b> doša(s) → <b>+${fmt(delta)}</b> punkti pret stingro. Stingrā rāda neizskaistināto bāzi.`
        : `Šim pārim Parihara neko būtiski nemaina — abas metodes dod ~vienādu rezultātu.`;

    return `
    <div style="background:#fff; border:1px solid #FADADD; border-radius:14px; padding:22px; margin:15px 0; animation:gaugePanelIn .5s ease both;">
        <style>
            @keyframes gaugePanelIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
            /* Ieejas slīdējums: adata aizdziļinās no minimuma uz aprēķināto vērtību ar inerci.
               'to' nav norādīts → izmanto inline transform (= mērķa leņķis), tāpēc kešs/pārrender korekts. */
            @keyframes gaugeNeedleIn { from { transform:rotate(-135deg); } }
            .gauge-needle {
                transform-box: view-box;
                transform-origin: ${G.cx}px ${G.cy}px;
                transition: transform 1.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                animation: gaugeNeedleIn 1.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            }
            @media (prefers-reduced-motion: reduce) {
                .gauge-needle { transition:none; animation:none; }
                [style*="gaugePanelIn"] { animation:none !important; }
            }
            .gauge-switch{display:flex; gap:0; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:14px; padding:6px; max-width:520px; margin:0 auto 18px auto;}
            .gauge-switch button{flex:1; border:none; background:transparent; cursor:pointer; padding:15px 12px; font-size:1.08rem; font-weight:800; color:#64748b; border-radius:10px; transition:all .22s; letter-spacing:.2px; line-height:1.15;}
            .gauge-switch button .gs-sub{display:block; font-size:0.72rem; font-weight:500; color:#94a3b8; margin-top:3px;}
            .gauge-switch button:hover:not(.active){color:#334155; background:rgba(255,255,255,0.6);}
            .gauge-switch button.active{color:#fff; box-shadow:0 3px 10px rgba(0,0,0,0.2);}
            .gauge-switch button.active.gs-strict{background:linear-gradient(135deg,#64748b,#334155);}
            .gauge-switch button.active.gs-trad{background:linear-gradient(135deg,#e879f9,#c026d3);}
            .gauge-switch button.active .gs-sub{color:rgba(255,255,255,0.85);}
            /* Tīrais vs Koriģētais čipi */
            .gauge-compare{display:flex; justify-content:center; gap:12px; margin-top:4px; flex-wrap:wrap;}
            .gc-chip{display:flex; flex-direction:column; align-items:center; min-width:128px; padding:8px 16px; border-radius:11px; border:1.5px solid #e2e8f0; background:#f8fafc; transition:all .25s;}
            .gc-chip span{font-size:0.72rem; font-weight:700; letter-spacing:.3px; color:#94a3b8;}
            .gc-chip b{font-size:1.3rem; font-weight:900; color:#94a3b8; line-height:1.2;}
            .gc-chip.active{background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.1);}
            #gc-strict.active{border-color:#475569;} #gc-strict.active b, #gc-strict.active span{color:#334155;}
            #gc-trad.active{border-color:#c026d3;}   #gc-trad.active b{color:#c026d3;} #gc-trad.active span{color:#9f1239;}
            /* Divi metožu skaidrojuma bloki (pilnā platumā, blakus) zem rezultātu čipiem */
            .method-explain{display:flex; gap:20px; margin-top:18px; flex-wrap:wrap;}
            .me-block{flex:1 1 320px; border-radius:12px; padding:16px 18px;}
            .me-block h5{margin:0 0 8px 0; font-size:0.92rem; display:flex; align-items:center; gap:6px;}
            .me-block p{margin:0; font-size:0.82rem; color:#475569; line-height:1.62;}
            .me-strict{background:#f8fafc; border:1px solid #e2e8f0;}
            .me-strict h5{color:#334155;}
            .me-trad{background:#fdf4ff; border:1px solid #f0abfc;}
            .me-trad h5{color:#a21caf;}
            /* Zonu leģenda (vienmēr redzama — atslēga, ko skaitlis nozīmē) */
            .gauge-legend{margin:0; width:100%; border:1px solid #eef2f7; border-radius:12px; overflow:hidden; background:#fafbfc;}
            .gauge-legend .gz-head{font-size:0.72rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:.6px; padding:11px 15px 6px;}
            .gz-row{display:flex; align-items:flex-start; gap:11px; padding:9px 15px; border-top:1px solid #f1f5f9;}
            .gz-swatch{flex:0 0 auto; width:15px; height:15px; border-radius:4px; margin-top:3px; box-shadow:0 1px 2px rgba(0,0,0,0.15);}
            .gz-txt{font-size:0.81rem; color:#475569; line-height:1.5;}
            .gz-txt b{font-size:0.83rem;}
            /* 3 kolonnas: kreisā = Ashta Kuta sadalījums · centrā = gauge · labā = zonu nozīme */
            .gauge-3col{display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap; justify-content:center;}
            .gauge-col-left{flex:1 1 280px; min-width:260px; max-width:340px; background:#fffdf7; border:1px solid #fde68a; border-radius:12px; padding:16px;}
            .gauge-col-center{flex:0 1 560px; min-width:320px;}
            .gauge-col-right{flex:1 1 280px; min-width:260px; max-width:360px;}
            .gk-title{margin:0 0 12px 0; color:#92400e; font-size:0.95rem; display:flex; align-items:center; gap:6px;}
            .gk-row{border-bottom:1px solid #f5ecd6;}
            .gk-row:last-of-type{border-bottom:none;}
            .gk-sum{padding:8px 0; cursor:pointer; list-style:none;}
            .gk-sum::-webkit-details-marker{display:none;}
            .gk-sum:hover .gk-name{color:#92400e;}
            .gk-top{display:flex; justify-content:space-between; align-items:baseline; gap:8px;}
            .gk-name{font-weight:700; color:#334155; font-size:0.85rem;}
            .gk-neut{color:#0e7490; cursor:help;}
            .gk-score{font-weight:800; font-size:0.86rem; white-space:nowrap;}
            .gk-max{color:#94a3b8; font-weight:600; font-size:0.74rem;}
            .gk-track{background:#eadfc4; border-radius:4px; height:6px; margin:4px 0 2px;}
            .gk-fill{height:6px; border-radius:4px; transition:width .45s;}
            .gk-lvrow{display:flex; justify-content:space-between; align-items:center; gap:8px;}
            .gk-lv{font-size:0.72rem; color:#a8a29e;}
            .gk-toggle{font-size:0.72rem; font-weight:700; color:#92400e; white-space:nowrap;}
            .gk-sum:hover .gk-toggle{text-decoration:underline;}
            .gk-pm::after{content:'➕';}
            .gk-row[open] .gk-pm::after{content:'➖';}
            .gk-desc{padding:2px 0 11px 0; font-size:0.78rem; color:#475569; line-height:1.55;}
            .gk-chip{display:inline-block; background:#fff7ed; border:1px solid #fed7aa; border-radius:4px; padding:2px 8px; color:#9a3412; font-size:0.72rem; font-weight:600; margin-bottom:7px;}
            .gk-d-sum{margin:0 0 5px 0; font-weight:600; color:#1e293b;}
            .gk-d-det{margin:0 0 7px 0;}
            .gk-d-prac{margin:0; padding:6px 9px; background:#f0fdf4; border-left:3px solid #34d399; border-radius:0 6px 6px 0; color:#065f46;}
            /* Ashta Kuta skaidrojums (galvenē, izvēršams) */
            .ak-info{margin:0 0 18px 0; max-width:860px; border:1px solid #eef2f7; border-radius:10px; background:#fafbfc;}
            .ak-info summary{cursor:pointer; padding:10px 14px; font-weight:700; color:#0e7490; font-size:0.82rem; list-style:none; display:flex; align-items:center; gap:6px;}
            .ak-info summary::-webkit-details-marker{display:none;}
            .ak-info summary::after{content:'▾'; color:#94a3b8; margin-left:auto;}
            .ak-info[open] summary::after{content:'▴';}
            .ak-info .ak-body{padding:2px 16px 14px 16px; font-size:0.82rem; color:#475569; line-height:1.6;}
            .ak-info .ak-body p{margin:0 0 8px 0;}
            .ak-info .ak-body h5{margin:12px 0 8px 0; color:#334155; font-size:0.87rem;}
            .ak-factor{padding:6px 0; border-top:1px solid #eef2f7;}
            .ak-factor b{color:#1e293b;}
        </style>

        <h3 style="margin:0 0 3px 0; color:#9f1239; text-align:left; font-size:1.3rem;">Saderība</h3>
        <p style="margin:0 0 8px 0; text-align:left; font-size:0.92rem; color:#475569; font-weight:600;">
            Ashta Kuta — savstarpējās saderības un laulības potenciāla noteikšana
        </p>
        <details class="ak-info">
            <summary>ℹ️ Kas ir Ashta Kuta sistēma?</summary>
            <div class="ak-body">${ASHTA_KUTA_INFO}</div>
        </details>

        <!-- 3 kolonnas: kreisā = Ashta Kuta sadalījums · centrā = gauge · labā = zonu nozīme -->
        <div class="gauge-3col">

            <!-- Kreisā kolonna: detalizētā aprēķina dati + piezīmes -->
            <div class="gauge-col-left">
                <h4 class="gk-title">🔢 Ashta Kuta — Detalizēts Aprēķins</h4>
                <div id="gauge-kuta-list">${renderKutaCompact(breakdown, timeAveraged)}</div>
            </div>

            <!-- Centrālā kolonna: liels metodes pārslēdzis (default Stingrā) + gauge -->
            <div class="gauge-col-center">
                <div class="gauge-switch" role="radiogroup" aria-label="Aprēķina metode">
                    <button class="gs-strict ${!parihara ? 'active' : ''}" onclick="setGaugeMethod(false)" aria-pressed="${!parihara}">
                        Stingrā<span class="gs-sub">tīrs BPHS</span>
                    </button>
                    <button class="gs-trad ${parihara ? 'active' : ''}" onclick="setGaugeMethod(true)" aria-pressed="${parihara}">
                        Tradicionālā<span class="gs-sub">ar Dosha Parihara</span>
                    </button>
                </div>
                <div style="display:flex; justify-content:center;">
                    ${renderCompatibilityGauge(active, timeAveraged)}
                </div>
            </div>

            <!-- Labā kolonna: ko skaitlis nozīmē attiecībām (plašā valodā) -->
            <div class="gauge-col-right">
                <div class="gauge-legend">
                    <div class="gz-head">Ko skaitlis nozīmē attiecībām</div>
                    ${GAUGE_TIERS.map(t => `
                    <div class="gz-row">
                        <span class="gz-swatch" style="background:${t.color};"></span>
                        <div class="gz-txt"><b style="color:${t.color};">${t.range} · ${t.name}</b> — ${t.desc}</div>
                    </div>`).join('')}
                </div>
            </div>
        </div>

        <!-- Tīrais vs Koriģētais: abi skaitļi vienlaikus, aktīvais izcelts -->
        <div class="gauge-compare">
            <div id="gc-strict" class="gc-chip ${!parihara ? 'active' : ''}"><span>Stingrā · BPHS</span><b>${fmt(strictScore)}</b></div>
            <div id="gc-trad" class="gc-chip ${parihara ? 'active' : ''}"><span>Tradicionālā · Parihara</span><b>${fmt(tradScore)}</b></div>
        </div>
        <div id="gc-note" style="text-align:center; font-size:0.78rem; color:#64748b; margin-top:8px; line-height:1.5; max-width:560px; margin-left:auto; margin-right:auto;">${noteHtml}</div>

        <!-- Divi metožu skaidrojumi blakus, pilnā platumā (kreisais=Stingrā, labais=Tradicionālā) -->
        <div class="method-explain">
            <div class="me-block me-strict">
                <h5>⚖️ Stingrā metode (tīrs BPHS)</h5>
                <p>${METHOD_STRICT_TEXT}</p>
            </div>
            <div class="me-block me-trad">
                <h5>🕊️ Tradicionālā metode (ar Dosha Parihara)</h5>
                <p>${METHOD_TRAD_TEXT}</p>
            </div>
        </div>
    </div>`;
}

export function renderTabCompatibility(p1, p2) {
    const resultsHtml = (p1 && p2) ? renderTabCompatibilityResults(p1, p2) : '';

    // Partnera dzimums noklusējumā = PRETĒJAIS pamatprofila dzimumam (skatītājs var mainīt manuāli).
    // Saglabā jau manuāli izvēlēto (window.currentGender2), citādi ņem pretpolu personai 1.
    const p1Gender = (typeof document !== 'undefined' && document.querySelector('input[name="gender-1"]:checked')?.value)
                     || window.currentGender1 || 'M';
    const partnerGender = window.currentGender2 || (p1Gender === 'M' ? 'F' : 'M');

    // Partneru meklētāja aprēķina metode (atsevišķa no galvenās analīzes); noklusējums Tradicionālā.
    const finderParihara = (typeof window !== 'undefined' ? window.currentFinderPariharaMode : undefined) !== false;

    // Partnera datums bez testa noklusējuma — tukšs (dd/mm/gggg), bet iepriekš ievadītais
    // tiek atjaunots no localStorage (galvenā atjaunošana notiek _setupPartnerUtcHint).
    let savedDate2 = '';
    try { savedDate2 = (typeof localStorage !== 'undefined' && localStorage.getItem('astroUiDate2')) || ''; } catch (e) {}

    return `
        <div class="input-panel" style="display:block; margin-bottom:15px; background:#fff1f2; border:1px solid #fecdd3; padding:20px;">
            <h3 style="margin:0 0 1rem 0; color:#9f1239;">Ievadiet Partnera Datus</h3>
            <div style="display:flex; gap:18px; flex-wrap:wrap; align-items:flex-start;">

                <!-- Datums -->
                <div class="input-group" style="flex:1 1 150px;">
                    <label>Datums</label>
                    <input type="date" id="ui-date-2" class="input-control" value="${savedDate2}">
                </div>

                <!-- Laiks -->
                <div class="input-group" style="flex:1 1 150px;">
                    <label>Dzimšanas laiks <span class="label-note">(vietējais)</span></label>
                    <input type="time" id="ui-time-2" class="input-control" value="12:00">
                    <label class="input-check" for="ui-time-unknown-2">
                        <input type="checkbox" id="ui-time-unknown-2" checked> Nezinu laiku
                    </label>
                    <div id="utc-display-2" class="input-subhint"></div>
                </div>

                <!-- Dzimšanas vieta -->
                <div class="input-group" style="flex:2 1 230px;">
                    <label>Dzimšanas vieta</label>
                    <div class="input-field">
                        <input type="text" id="ui-location-2" class="input-control"
                            value="Rīga, Latvija" data-lat="56.9496" data-lon="24.1052" data-timezone="Europe/Riga"
                            autocomplete="off" placeholder="Sāciet rakstīt pilsētas nosaukumu...">
                        <div id="autocomplete-list-2" class="autocomplete-items"></div>
                    </div>
                </div>

                <!-- Dzimums (horizontāls; native radio paslēpts, izceļ ar :has(input:checked)) -->
                <div class="input-group" style="flex:1.2 1 180px;">
                    <label>Dzimums</label>
                    <div id="gender-container-2" style="display:flex; flex-direction:row; gap:8px; border-radius:9px; transition:all 0.3s;">
                        <label class="gender-opt" id="lbl-gender2-m" style="flex:1; justify-content:center; gap:5px; white-space:nowrap;">
                            <input type="radio" name="gender-2" id="ui-gender2-m" value="M" ${partnerGender === 'M' ? 'checked' : ''} style="display:none;">
                            <span class="gender-icon" id="icon-gender2-m" style="color:#1d4ed8;">♂</span> Vīrietis
                        </label>
                        <label class="gender-opt" id="lbl-gender2-f" style="flex:1; justify-content:center; gap:5px; white-space:nowrap;">
                            <input type="radio" name="gender-2" id="ui-gender2-f" value="F" ${partnerGender === 'F' ? 'checked' : ''} style="display:none;">
                            <span class="gender-icon" id="icon-gender2-f" style="color:#be185d;">♀</span> Sieviete
                        </label>
                    </div>
                </div>

                <!-- Poga (izlīdzināta ar ievades laukiem caur tukšu etiķeti) -->
                <div class="input-group" style="flex:0 0 auto;">
                    <label style="visibility:hidden;" aria-hidden="true">Aprēķināt</label>
                    <button class="btn-calc" onclick="calculatePartnerMatrix()"
                        style="height:auto; padding:0.72rem 1.6rem; font-size:0.95rem; background:#d946ef; white-space:nowrap;">
                        Aprēķināt Saderību
                    </button>
                </div>
            </div>
        </div>
        <div id="compatibility-gauge-container">${(p1 && p2) ? renderGaugePanel(p1, p2) : ''}</div>
        <div id="compatibility-results-container">${resultsHtml}</div>

        <!-- Potenciālie Partneri Sadaļa -->
        <div style="margin-top: 30px; background: #fdf2f8; padding: 20px; border-radius: 12px; border: 1px solid #fbcfe8;">
            <h3 style="margin-top: 0; color: #be185d; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.2rem;">✨</span> Potenciālo Partneru Datumi
            </h3>
            <p style="font-size: 0.9rem; color: #475569; margin-bottom: 15px;">
                Sistēma skenē dzimšanas datumus +/- 7 gadu logā (14 gadi) ap jūsu dzimšanas gadu un atrod
                Mēness nakšatras ar augstāko Ashta Kuta saderību. Rezultāti sadalīti divās joslās —
                <b>Izcili</b> (29+) un <b>Vidējā saderība</b> (18–28); rādīti <b>visi datumi</b>, kas sasniedz attiecīgo līmeni.
            </p>

            <!-- Metodes slēdzis + poga VIENĀ RINDĀ (slēdzis atsevišķs no galvenās analīzes) -->
            <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:8px;">
                <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; padding:8px 14px; background:#fff; border:1px solid #fbcfe8; border-radius:10px;">
                    <span style="font-size:0.82rem; font-weight:700; color:#9d174d;">⚖️ Aprēķina metode:</span>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.82rem; color:#334155; font-weight:600;">
                        <input type="radio" name="finder-method" value="strict" onchange="setFinderMethod(false)" ${!finderParihara ? 'checked' : ''}>
                        Stingrā <span style="color:#94a3b8; font-weight:400;">(tīrs BPHS)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.82rem; color:#334155; font-weight:600;">
                        <input type="radio" name="finder-method" value="parihara" onchange="setFinderMethod(true)" ${finderParihara ? 'checked' : ''}>
                        Tradicionālā <span style="color:#94a3b8; font-weight:400;">(ar Dosha Parihara)</span>
                    </label>
                </div>
                <button id="btn-find-partners" onclick="generatePotentialPartners()"
                    style="padding: 10px 20px; font-size: 0.95rem; background: #be185d; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); white-space:nowrap;">
                    Meklēt Saderīgākos Datumus
                </button>
            </div>
            <div style="font-size:0.74rem; color:#94a3b8; margin-bottom:12px; line-height:1.5;">
                ${finderParihara
                    ? 'Bhakut/Nadi doša tiek neitralizēta — vairāk pozīciju sasniedz izcilu saderību.'
                    : 'Stingrā režīmā dažām nakšatru/rashi pozīcijām strukturāli nav iespējams sasniegt izcilu saderību — “Izcili” josla var būt tukša.'}
                Metodes maiņa pārmeklē atkārtoti, ja rezultāti jau ir parādīti.
            </div>
            <div id="potential-partners-list" style="margin-top: 5px;"></div>
        </div>`;
}

export async function generatePotentialPartnersHTML(p1, gender1, targetGender, parihara = false) {
    const { excellent, medium } = await findCompatibleDates(p1, gender1, targetGender, parihara);
    const methodLabel = parihara ? 'Tradicionālā (Parihara)' : 'Stingrā (BPHS)';

    // Tiers pēc Ashta Kuta punktiem (no 36)
    const tier = s => s >= 29 ? { c:'#10b981', t:'Izcili' }
                  : s >= 25 ? { c:'#16a34a', t:'Ļoti labi' }
                  : s >= 21 ? { c:'#f59e0b', t:'Labi' }
                  : s >= 18 ? { c:'#f59e0b', t:'Vidēji' }
                  :           { c:'#ef4444', t:'Vāji' };

    const renderTable = (dates) => {
        const rows = dates.map(d => {
            const tr = tier(d.rounded);                 // josla/etiķete pēc noapaļotā
            return `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 8px; font-weight: 600; color: #1e293b;">${d.date}</td>
                <td style="padding: 8px; color: ${tr.c}; font-weight: 700;">${d.score.toFixed(1)} / 36</td>
                <td style="padding: 8px;"><span style="background:${tr.c}1a; color:${tr.c}; font-weight:600; font-size:0.78rem; padding:2px 8px; border-radius:99px;">${tr.t}</span></td>
                <td style="padding: 8px; color: #475569;">${d.nakshatra}</td>
            </tr>`;
        }).join('');
        return `<div style="max-height: 320px; overflow-y: auto; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 10px;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #f1f5f9; color: #64748b;">
                        <th style="padding: 8px;">Datums</th>
                        <th style="padding: 8px;">Ashta Kuta</th>
                        <th style="padding: 8px;">Vērtējums</th>
                        <th style="padding: 8px;">Mēness Nakšatra</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    };

    const emptyMsg = txt => `<div style="padding:14px; background:#fff; border-radius:8px; color:#64748b; text-align:center; font-size:0.86rem;">${txt}</div>`;

    const blockHeader = (icon, color, title, count) => `
        <div style="display:flex; align-items:center; gap:8px; margin:0 0 10px 0;">
            <span style="font-size:1.05rem;">${icon}</span>
            <h4 style="margin:0; color:${color}; font-size:0.98rem;">${title}</h4>
            <span style="font-size:0.76rem; color:#94a3b8;">${count} ${count === 1 ? 'datums' : 'datumi'}</span>
        </div>`;

    const excellentBlock = excellent.length
        ? renderTable(excellent)
        : emptyMsg(`Šajā ±7 gadu logā neizdevās atrast <b>izcilu</b> saderību (29+ punkti) ar metodi <b>${methodLabel}</b>.`);

    const mediumBlock = medium.length
        ? renderTable(medium)
        : emptyMsg(`Nav rezultātu <b>vidējā</b> diapazonā (18–28 punkti) ar metodi <b>${methodLabel}</b>.`);

    return `
        ${blockHeader('🟢', '#047857', 'Izcili (29+ punkti)', excellent.length)}
        ${excellentBlock}

        <hr style="border:none; border-top:2px dashed #fbcfe8; margin:22px 0;">

        ${blockHeader('🟡', '#b45309', 'Vidējā saderība (18–28 punkti)', medium.length)}
        ${mediumBlock}

        <div style="font-size:0.78rem; color:#94a3b8; margin-top:12px; line-height:1.5;">
            ℹ️ Rādīti <b>visi datumi</b> ±7 gadu logā (14 gadi), kas sasniedz attiecīgo saderības līmeni — hronoloģiski,
            tāpēc gadi aug (dažādi partneru vecumi). Viena un tā pati saderīgā Mēness nakšatra atkārtojas ~ik 27 dienas.
            <br>⏳ Tāpat kā partnerim ar <b>“nezinu laiku”</b>, katram datumam rādīts <b>24h vidējais</b> (gaidāmā vērtība
            pār visām Mēness pozīcijām dienā) — <b>tas pats skaitlis, ko dod galvenā analīze tam pašam datumam</b>, nevis
            dienas maksimums. Tāpēc punkti ir daļskaitļi.
            <div style="text-align:right; margin-top:4px;">Metode: <b>${methodLabel}</b> · kopā <b>${excellent.length + medium.length}</b> datumi</div>
        </div>`;
}

