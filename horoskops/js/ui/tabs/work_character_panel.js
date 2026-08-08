import { calculateWorkCharacter } from '../../logic/work_character.js';

// "Darba Stila Pozīcija" / Darba Rakstura Analīze panelis.
// Pārcelts no renderTabProfils (q1) uz cilni 'Profils' (skat. render_dashboard.js).
export function renderWorkCharacterPanel(profile) {
    // ── Darba Rakstura Aprēķins ────────────────────────────────────────────────
    const wc = calculateWorkCharacter(profile);
    const q  = wc.quadrant;

    // Profila dati, ko izmanto faktoru tabula un Dašas komentārs (pārcelti līdzi no renderTabProfils).
    const hell  = profile.hellenistic?.data || {};
    const dasha = profile.vedic?.current_dasha || {};

    // Stiprumi/riski/lomas nāk no ARHETIPA (leadership.primary), lai sakristu ar
    // virsrakstā/vizuālajā šāvienā rādīto arhetipu — ne no kvadranta. Fallback uz kvadrantu.
    const arch       = profile.leadership?.primary || {};
    const wcStrengths = arch.strengths?.length ? arch.strengths : q.strengths;
    const wcRisks     = arch.risks?.length     ? arch.risks     : q.risks;
    const wcRoles     = arch.roles?.length     ? arch.roles     : q.ideal_roles;
    // ── Kvadrantu 2D karte ────────────────────────────────────────────────────
    // punkts: left = dominance_pct%, bottom = stability_pct% (no kvadranta apakšas)
    const dotLeft   = wc.dominance_pct;
    const dotBottom = wc.stability_pct;

    // ── Iekšējās pretrunas uz 2D kartes: VIENA sarkana bulta = abu asu pretspēku rezultants ──
    // x komponente no Ietekmes ass, y komponente no Stabilitātes ass; leņķis = vidējais (svērts pēc stipruma).
    const tensionArrows = (() => {
        const ts = (wc.innerTensions || []).filter(t => t.ratioPct > 0);
        if (!ts.length) return '';
        const x = dotLeft, y = 100 - dotBottom;          // SVG koord. (y aug no augšas)
        let vx = 0, vy = 0, strong = false;
        ts.forEach(t => {
            if (t.hasTension) strong = true;
            if (t.axis === 'dominance')                  // Dominants (labais) uzvar → pretspēks pa kreisi (−x)
                vx += (t.dominantPole === t.posPole ? -t.ratioPct : t.ratioPct);
            else                                         // Stabils (augša) uzvar → pretspēks uz leju (+y)
                vy += (t.dominantPole === t.posPole ? t.ratioPct : -t.ratioPct);
        });
        const mag = Math.hypot(vx, vy);
        if (mag === 0) return '';
        const SCALE = 0.30;                              // rezultanta magnitūda → SVG vienības
        const drawLen = Math.max(mag * SCALE, 5);        // min 5, lai vāja spriedze ir redzama
        const clamp = v => Math.max(3, Math.min(97, v));
        const x2 = clamp(x + (vx / mag) * drawLen).toFixed(1);   // vienības vektors saglabā leņķi
        const y2 = clamp(y + (vy / mag) * drawLen).toFixed(1);
        return `
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute; inset:0; width:100%; height:100%; z-index:1; pointer-events:none; overflow:visible;">
            <defs>
                <marker id="tArrow" markerWidth="6" markerHeight="6" refX="3.5" refY="2.5" orient="auto" markerUnits="userSpaceOnUse">
                    <path d="M0,0 L5,2.5 L0,5 Z" fill="#dc2626"/>
                </marker>
            </defs>
            <line x1="${x}" y1="${y}" x2="${x2}" y2="${y2}" stroke="#dc2626" stroke-width="${strong ? 2.6 : 1.8}" marker-end="url(#tArrow)" opacity="${strong ? 0.92 : 0.6}"/>
        </svg>`;
    })();

    const quadrantChart = `
        <div>
            <div style="text-align:center; font-size:0.58rem; color:#94a3b8; margin-bottom:3px; padding-left:28px;">↑ Stabils (max)</div>
            <div style="display:flex; gap:0; align-items:stretch;">

                <!-- Y skalas atzīmes -->
                <div style="display:flex; flex-direction:column; justify-content:space-between; align-items:flex-end; width:26px; flex-shrink:0; padding-right:4px; box-sizing:border-box;">
                    <span style="font-size:0.48rem; color:#94a3b8; line-height:1.2;">max</span>
                    <span style="font-size:0.48rem; color:#94a3b8; line-height:1.2;">75%</span>
                    <span style="font-size:0.48rem; color:#94a3b8; line-height:1.2;">50%</span>
                    <span style="font-size:0.48rem; color:#94a3b8; line-height:1.2;">25%</span>
                    <span style="font-size:0.48rem; color:#94a3b8; line-height:1.2;">min</span>
                </div>

                <!-- Grafiks -->
                <div style="flex:1; position:relative; aspect-ratio:1; border-radius:12px; overflow:hidden; min-height:0;">

                    <!-- Fona kvadranti -->
                    <div style="position:absolute; inset:0; display:grid; grid-template-columns:1fr 1fr; grid-template-rows:1fr 1fr;">
                        <div style="background:#f0fdf4; padding:6px; font-size:0.58rem; font-weight:700; color:#15803d; display:flex; align-items:flex-start;">DIPLOMĀTS</div>
                        <div style="background:#fefce8; padding:6px; font-size:0.58rem; font-weight:700; color:#a16207; display:flex; align-items:flex-start; justify-content:flex-end;">STRATĒĢIS</div>
                        <div style="background:#eff6ff; padding:6px; font-size:0.58rem; font-weight:700; color:#1d4ed8; display:flex; align-items:flex-end;">KATALIZATORS</div>
                        <div style="background:#fff1f2; padding:6px; font-size:0.58rem; font-weight:700; color:#b91c1c; display:flex; align-items:flex-end; justify-content:flex-end;">PIONIERIS</div>
                    </div>

                    <!-- Galvenās asis (50%) -->
                    <div style="position:absolute; top:50%; left:0; right:0; height:1px; background:rgba(100,116,139,0.5);"></div>
                    <div style="position:absolute; left:50%; top:0; bottom:0; width:1px; background:rgba(100,116,139,0.5);"></div>

                    <!-- Palīglines 25% un 75% -->
                    <div style="position:absolute; top:25%; left:0; right:0; border-top:1px dashed rgba(100,116,139,0.35);"></div>
                    <div style="position:absolute; top:75%; left:0; right:0; border-top:1px dashed rgba(100,116,139,0.35);"></div>
                    <div style="position:absolute; left:25%; top:0; bottom:0; border-left:1px dashed rgba(100,116,139,0.35);"></div>
                    <div style="position:absolute; left:75%; top:0; bottom:0; border-left:1px dashed rgba(100,116,139,0.35);"></div>

                    <!-- Iekšējās pretrunas (sarkanas bultas) -->
                    ${tensionArrows}

                    <!-- Pozīcijas koordinātes -->
                    <div style="position:absolute; top:24px; right:4px; font-size:0.48rem; color:#475569; background:rgba(255,255,255,0.88); padding:1px 5px; border-radius:3px; z-index:3; line-height:1.6; white-space:nowrap;">Dom ${dotLeft}% · Stab ${dotBottom}%</div>

                    <!-- Marķieris -->
                    <div style="
                        position:absolute;
                        left:${dotLeft}%;
                        bottom:${dotBottom}%;
                        transform:translate(-50%, 50%);
                        width:16px; height:16px;
                        background:${q.border};
                        border-radius:50%;
                        border:3px solid white;
                        box-shadow:0 2px 10px rgba(0,0,0,0.35);
                        z-index:2;
                    "></div>

                    <!-- Halos -->
                    <div style="
                        position:absolute;
                        left:${dotLeft}%;
                        bottom:${dotBottom}%;
                        transform:translate(-50%, 50%);
                        width:44px; height:44px;
                        border:2px dashed ${q.border};
                        border-radius:50%;
                        opacity:0.3;
                        z-index:1;
                    "></div>

                </div><!-- /grafiks -->
            </div>

            <!-- X skalas atzīmes -->
            <div style="display:flex; justify-content:space-between; padding-left:30px; margin-top:4px;">
                <span style="font-size:0.48rem; color:#94a3b8;">min</span>
                <span style="font-size:0.48rem; color:#94a3b8;">25%</span>
                <span style="font-size:0.48rem; color:#94a3b8;">50%</span>
                <span style="font-size:0.48rem; color:#94a3b8;">75%</span>
                <span style="font-size:0.48rem; color:#94a3b8;">max</span>
            </div>
            ${tensionArrows ? `<div style="display:flex; align-items:center; gap:5px; margin-top:6px; padding-left:30px; font-size:0.56rem; color:#94a3b8; line-height:1.3;"><span style="color:#dc2626; font-size:0.8rem; line-height:1;">→</span> Sarkanā bulta — iekšējā pretspēka virziens un stiprums.</div>` : ''}

            <!-- Virzienu etiķetes -->
            <div style="display:flex; justify-content:space-between; padding-left:30px; margin-top:2px;">
                <span style="font-size:0.6rem; color:#94a3b8;">← Pielāgojams</span>
                <span style="font-size:0.6rem; color:#94a3b8;">Dominants →</span>
            </div>
            <div style="text-align:center; font-size:0.58rem; color:#94a3b8; margin-top:2px; padding-left:26px;">↓ Straujš (min)</div>
        </div>`;

    // ── Faktoru tabula ────────────────────────────────────────────────────────
    const f = wc.factors;

    // Formatē vienu šūnu: undefined/virkne → pelēks '—', skaitlis → zaļš/sarkans ar zīmi
    const fmtCell = (v) => {
        if (v === undefined || v === null || typeof v === 'string')
            return { color: '#94a3b8', text: (typeof v === 'string' ? v : '—') };
        return { color: v >= 0 ? '#16a34a' : '#dc2626', text: (v >= 0 ? '+' : '') + v };
    };

    const factorRow = (label, val1, val2) => {
        const c1 = fmtCell(val1);
        const c2 = fmtCell(val2);
        return `
        <tr style="border-bottom:1px solid #f1f5f9;">
            <td style="padding:6px 8px; font-size:0.8rem; color:#475569;">${label}</td>
            <td style="padding:6px 8px; font-size:0.8rem; text-align:center; color:${c1.color}; font-weight:600;">${c1.text}</td>
            <td style="padding:6px 8px; font-size:0.8rem; text-align:center; color:${c2.color}; font-weight:600;">${c2.text}</td>
        </tr>`;
    };

    const factorsTable = `
        <table style="width:100%; border-collapse:collapse; margin-top:0.5rem;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:6px 8px; font-size:0.75rem; text-align:left; color:#64748b; font-weight:600;">Faktors</th>
                    <th style="padding:6px 8px; font-size:0.75rem; text-align:center; color:#64748b; font-weight:600;">Ass 1<br>(Dom)</th>
                    <th style="padding:6px 8px; font-size:0.75rem; text-align:center; color:#64748b; font-weight:600;">Ass 2<br>(Stab)</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background:#fafafa;"><td colspan="3" style="padding:4px 8px; font-size:0.68rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Ietekmes ass (Ax1)</td></tr>
                ${factorRow('BaZi Daymaster: ' + f.daymaster.label,     f.daymaster.ax1,  undefined)}
                ${factorRow('Elementu balanss: ' + f.elements.label,     f.elements.ax1,   undefined)}
                ${factorRow('Temperaments: ' + (hell.humor || '—'),      f.temperament.ax1, f.temperament.ax2)}
                ${factorRow('Saturna modalitāte: ' + f.saturn.label,     f.saturn.ax1,     undefined)}
                ${factorRow('Dasha lords: ' + f.dasha.label,             f.dasha.ax1,      undefined)}
                ${factorRow('Saule: ' + f.sun.label,                     f.sun.ax1,        undefined)}
                ${factorRow('Jupiters: ' + f.jupiter.label,              f.jupiter.ax1,    f.jupiter.ax2)}
                ${factorRow('BaZi Gada Dievs: ' + f.yearGod.label,       f.yearGod.ax1,    undefined)}
                ${factorRow('BaZi Mēneša Dievs: ' + f.monthGod.label,    f.monthGod.ax1,   undefined)}
                ${factorRow('Vēdiskie yogas: ' + f.yogas.label,          f.yogas.ax1,      undefined)}
                <tr style="background:#fafafa;"><td colspan="3" style="padding:4px 8px; font-size:0.68rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Stabilitātes ass (Ax2)</td></tr>
                ${factorRow('Nakšatra: ' + f.nakshatra.label,            undefined,        f.nakshatra.ax2)}
                ${factorRow('Marss: ' + f.mars.label,                    undefined,        f.mars.ax2)}
                ${factorRow('BaZi konflikti',                            undefined,        f.conflicts.ax2)}
                ${factorRow('BaZi kombinācijas: ' + f.combinations.label, undefined,       f.combinations.ax2)}
                ${factorRow('Veneras dignitas: ' + f.venus.label,        undefined,        f.venus.ax2)}
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc; font-weight:700;">
                    <td style="padding:7px 8px; font-size:0.8rem; color:#1e293b;">Rezultāts (raw)</td>
                    <td style="padding:7px 8px; font-size:0.8rem; text-align:center; color:#1e293b;">${wc.raw_x >= 0 ? '+' : ''}${wc.raw_x}</td>
                    <td style="padding:7px 8px; font-size:0.8rem; text-align:center; color:#1e293b;">${wc.raw_y >= 0 ? '+' : ''}${wc.raw_y}</td>
                </tr>
            </tfoot>
        </table>`;

    // ── Pretrunu / kopskata kopīgie dati ──────────────────────────────────────
    const tensions = wc.innerTensions || [];
    const intensityColor = { strong: '#ef4444', moderate: '#f59e0b', mild: '#0ea5e9', none: '#10b981' };   // siltuma spektrs: zaļš(saskaņa)→zils→dzeltens→sarkans
    const TNEG = '#f59e0b', TPOS = '#6366f1';   // negatīvais pols oranžs (kreisā), pozitīvais indigo (labā) — kā spēku kartē

    // ── Σ Summārā ass — viena josla kopskatam (neto rezultāts no visiem spēkiem) ──
    // Asu apraksti — kopīgi kopskatam (Σ) un spēku kartei
    const AX1_DESC = 'Vai dabiski uzņemies vadību un uzstāj, vai pielāgojies un seko.';
    const AX2_DESC = 'Vai darbojies noturīgi un paredzami, vai ātri un mainīgi.';

    const summaryAxisBar = (label, desc, negPole, posPole, netPct) => {
        const netIsPos = netPct >= 50;
        const netHalf  = Math.abs(netPct - 50) / 50 * 50;
        const netBar = netIsPos
            ? `<div style="position:absolute; left:50%; top:2px; bottom:2px; width:${netHalf}%; background:${TPOS}; border-radius:0 4px 4px 0;"></div>`
            : `<div style="position:absolute; right:50%; top:2px; bottom:2px; width:${netHalf}%; background:${TNEG}; border-radius:4px 0 0 4px;"></div>`;
        return `
        <div style="flex:1 1 280px; min-width:240px;">
            <div style="display:flex; align-items:baseline; justify-content:space-between; margin-bottom:5px;">
                <span style="font-size:0.84rem; font-weight:800; color:#1e293b;">Σ ${label}</span>
                <span style="font-size:0.84rem; font-weight:800; color:${netIsPos ? TPOS : TNEG};">${netPct}%</span>
            </div>
            <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                <span style="font-size:0.64rem; color:#94a3b8; font-weight:600; white-space:nowrap;">${negPole}</span>
                <div style="flex:1; position:relative; height:20px; background:#eef2f7; border-radius:5px;">
                    <div style="position:absolute; left:50%; top:0; bottom:0; width:1px; background:#94a3b8;"></div>
                    ${netBar}
                </div>
                <span style="font-size:0.64rem; color:#94a3b8; font-weight:600; white-space:nowrap;">${posPole}</span>
            </div>
            <div style="font-size:0.7rem; color:#64748b; line-height:1.4;">${desc}</div>
        </div>`;
    };

    const summaryBarsHtml = `
        <div style="display:flex; flex-wrap:wrap; gap:1.4rem 1.8rem;">
            ${summaryAxisBar('Ietekme', AX1_DESC, 'Pielāgojams', 'Dominants', wc.dominance_pct)}
            ${summaryAxisBar('Stabilitāte', AX2_DESC, 'Straujš', 'Stabils', wc.stability_pct)}
        </div>`;

    // ── Pretrunu TEASER — divkolonnu: vilkšana virvē + naratīvs (vienmēr abas asis) ──
    const tensionTeaserHtml = tensions.length ? `
        <div style="border-top:1px dashed #e2e8f0; margin-top:1rem; padding-top:0.9rem;">
            <div style="font-size:0.72rem; font-weight:800; color:#7c3aed; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:4px;">🧲 Iekšējās pretrunas</div>
            ${tensions.map(t => {
                const c = intensityColor[t.intensity];
                // Vilkšana virvē: uzvarētāja puse sniedzas līdz polam (50% no joslas), zaudētāja — proporcionāli (ratioPct).
                const winRight = t.dominantPole === t.posPole;   // posPole = labais pols (Dominants/Stabils)
                const posHalf  = winRight ? 50 : t.ratioPct / 2;
                const negHalf  = winRight ? t.ratioPct / 2 : 50;
                return `
                <!-- Divkolonnu bloks: KREISĀ = vilkšana virvē uz ass pretpoliem, LABĀ = ģenerētais skaidrojums -->
                <div style="display:flex; flex-wrap:wrap; gap:0.9rem 1.3rem; align-items:stretch; margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid #f1f5f9;">

                    <!-- KREISAIS: skala -->
                    <div style="flex:1 1 240px; min-width:215px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:11px;">
                            <span style="font-size:0.82rem; font-weight:800; color:#1e293b;">${t.axisLabel}</span>
                            <span style="background:${c}; color:#fff; border-radius:6px; padding:2px 9px; font-size:0.64rem; font-weight:800; box-shadow:0 1px 4px ${c}66;">${t.intensityLabel}</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:7px; margin-bottom:6px;">
                            <span style="font-size:0.6rem; font-weight:800; color:${TNEG}; white-space:nowrap;">${t.negPole}</span>
                            <div style="flex:1; position:relative; height:24px; background:#f1f5f9; border-radius:7px; box-shadow:inset 0 0 0 1px #e2e8f0;">
                                <div style="position:absolute; right:50%; top:3px; bottom:3px; width:${negHalf}%; background:${TNEG}; border-radius:6px 0 0 6px; box-shadow:0 0 6px ${TNEG}66;"></div>
                                <div style="position:absolute; left:50%; top:3px; bottom:3px; width:${posHalf}%; background:${TPOS}; border-radius:0 6px 6px 0; box-shadow:0 0 6px ${TPOS}66;"></div>
                                <div style="position:absolute; left:50%; top:0; bottom:0; width:2px; transform:translateX(-50%); background:#94a3b8; border-radius:1px;"></div>
                            </div>
                            <span style="font-size:0.6rem; font-weight:800; color:${TPOS}; white-space:nowrap;">${t.posPole}</span>
                        </div>
                        <div style="font-size:0.64rem; color:#475569;">Galvenais virziens: <b style="color:#1e293b;">${t.dominantPole}</b> · pretspēks <b style="color:${c};">${t.ratioPct}%</b></div>
                    </div>

                    <!-- LABAIS: skaidrojums -->
                    <div style="flex:1 1 300px; min-width:255px; background:#faf5ff; border-left:3px solid ${c}; border-radius:0 9px 9px 0; padding:0.7rem 0.95rem;">
                        <div style="font-size:0.58rem; font-weight:800; color:#7c3aed; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">Ko tas nozīmē</div>
                        <div style="font-size:0.78rem; color:#334155; line-height:1.55;">${t.narrative}</div>
                    </div>
                </div>`;
            }).join('')}
            <div style="font-size:0.74rem; color:#94a3b8; line-height:1.45; margin-top:0.4rem; font-style:italic;">Kur personas spēki velk pretējos virzienos — slēptā spriedze, kas veido šīs personas darba stilu.</div>
        </div>` : `
        <div style="border-top:1px dashed #e2e8f0; margin-top:1rem; padding-top:0.9rem;">
            <div style="font-size:0.72rem; font-weight:800; color:#15803d; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:4px;">⚖️ Iekšējā saskaņa</div>
            <div style="font-size:0.84rem; color:#334155; line-height:1.55;">Personas spēki velk saskaņoti vienā virzienā — bez slēptiem iekšējiem konfliktiem.</div>
        </div>`;

    // ── Pozīcijas apraksts (aizstāj kvadranta tipa nosaukumu — koordinātas, ne tips) ──
    const posCap = s => s.charAt(0).toUpperCase() + s.slice(1);
    const domWord = (p) => p >= 68 ? 'izteikti dominants' : p >= 56 ? 'drīzāk dominants' : p >= 45 ? 'līdzsvarots ietekmē' : p >= 33 ? 'drīzāk pielāgojams' : 'izteikti pielāgojams';
    const stabWord = (p) => p >= 68 ? 'izteikti stabils' : p >= 56 ? 'drīzāk stabils' : p >= 45 ? 'līdzsvarots stabilitātē' : p >= 33 ? 'drīzāk straujš' : 'izteikti straujš';
    const posHeadline = `${posCap(domWord(wc.dominance_pct))} · ${posCap(stabWord(wc.stability_pct))}`;

    // ── Spēku karte (jaunais hero — kuri faktori velk uz katru polu) ───────────
    const forceMapBlock = (() => {
        const fm = wc.forceMap || { ax1: [], ax2: [] };
        const NEG = '#f59e0b', POS = '#6366f1';   // pielāgojams/reaktīvs ↔ dominants/stabils

        const axisPanel = (title, desc, netPct, negPole, posPole, factors) => {
            const rows = factors.filter(f => Math.abs(f.contrib) > 0.001)
                                 .sort((a, b) => Math.abs(b.contrib) - Math.abs(a.contrib));
            const maxAbs = Math.max(...rows.map(f => Math.abs(f.contrib)), 0.01);
            const rowHtml = rows.map(f => {
                const isPos = f.contrib >= 0;
                const halfPct = Math.abs(f.contrib) / maxAbs * 50;
                const name = f.label ? `${f.name} · ${f.label}` : f.name;
                const bar = isPos
                    ? `<div style="position:absolute; left:50%; top:2px; bottom:2px; width:${halfPct}%; background:${POS}; border-radius:0 3px 3px 0;"></div>`
                    : `<div style="position:absolute; right:50%; top:2px; bottom:2px; width:${halfPct}%; background:${NEG}; border-radius:3px 0 0 3px;"></div>`;
                return `
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                    <div style="width:160px; font-size:0.74rem; color:#475569; text-align:right; flex-shrink:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${name}</div>
                    <div style="flex:1; position:relative; height:16px; background:#f1f5f9; border-radius:3px;">
                        <div style="position:absolute; left:50%; top:0; bottom:0; width:1px; background:#cbd5e1;"></div>
                        ${bar}
                    </div>
                </div>`;
            }).join('');

            return `
            <div style="flex:1 1 380px; min-width:300px;">
                <div style="font-size:0.82rem; font-weight:800; color:#1e293b; margin-bottom:2px;">${title}</div>
                <div style="font-size:0.72rem; color:#64748b; line-height:1.4; margin-bottom:10px;">${desc}</div>
                <div style="display:flex; gap:8px; margin-bottom:7px;">
                    <div style="width:160px; flex-shrink:0;"></div>
                    <div style="flex:1; display:flex; justify-content:space-between; font-size:0.66rem; font-weight:700;">
                        <span style="color:${NEG};">← ${negPole}</span>
                        <span style="color:${POS};">${posPole} →</span>
                    </div>
                </div>
                ${rowHtml}
            </div>`;
        };

        return `
        <div style="margin:0 1.6rem 1.4rem; background:#fcfcfd; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.3rem;">
            <div style="font-size:0.8rem; font-weight:800; color:#1e293b; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:1.2rem;">🧭 Spēku karte</div>
            <div style="display:flex; flex-wrap:wrap; gap:1.8rem;">
                ${axisPanel('Ietekmes ass', AX1_DESC, wc.dominance_pct, 'Pielāgojams', 'Dominants', fm.ax1)}
                ${axisPanel('Stabilitātes ass', AX2_DESC, wc.stability_pct, 'Straujš', 'Stabils', fm.ax2)}
            </div>
        </div>`;
    })();

    // ── Darba Rakstura galvenais bloks (minimizējams: kopskats vienmēr redzams, izvērsums pēc klikšķa) ──
    const workCharacterBlock = `
        <div style="background:white; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.08); overflow:hidden; margin-bottom:1.5rem;">
          <details class="wc-details">

            <!-- ░░ KOPSKATS — vienmēr redzams; klikšķis izvērš ░░ -->
            <summary>
                <!-- Virsraksts -->
                <div style="background:${q.color}; border-bottom:3px solid ${q.border}; padding:1.2rem 1.6rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:2px; color:${q.textColor}; margin-bottom:6px; font-weight:700;">Darba Stila Pozīcija</div>
                        <div style="font-size:1.45rem; font-weight:900; color:${q.textColor}; letter-spacing:-0.5px;">${posHeadline}</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:7px; font-size:0.78rem; font-weight:700; color:${q.textColor};">
                        <span class="wc-hint-closed">Izvērst analīzi</span>
                        <span class="wc-hint-open">Sakļaut</span>
                        <span class="wc-caret" style="font-size:0.66rem;">▶</span>
                    </div>
                </div>
                <!-- Σ summārās asis + pretrunu teaser -->
                <div style="padding:1.3rem 1.6rem;">
                    ${summaryBarsHtml}
                    ${tensionTeaserHtml}
                </div>
            </summary>

            <!-- ░░ IZVĒRSTĀ DAĻA — kas veido šos rezultātus ░░ -->
            <div style="border-top:1px solid #eef2f7;">
                <div style="padding:1.3rem 1.6rem 0.2rem; font-size:0.75rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:2px;">Kas veido šos rezultātus</div>

                <!-- Saturs -->
                <div style="padding:1rem 1.6rem 1.4rem; display:grid; grid-template-columns:1fr 1fr 1fr; gap:1.5rem; align-items:start;">

                    <!-- Kolonna 1: 2D karte -->
                    <div>
                        <div style="font-size:0.8rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:1.5rem; text-align:center;">Pozīcija matricā</div>
                        ${quadrantChart}
                    </div>

                    <!-- Kolonna 2: Stiprumi, riski, lomas -->
                    <div>
                        <div style="margin-bottom:1rem;">
                            <div style="font-size:0.8rem; font-weight:700; color:#16a34a; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.5rem;">Darba stiprumi</div>
                            <ul style="margin:0; padding-left:1.2rem; font-size:0.83rem; color:#334155; line-height:1.8;">
                                ${wcStrengths.map(str => `<li>${str}</li>`).join('')}
                            </ul>
                        </div>
                        <div style="margin-bottom:1rem;">
                            <div style="font-size:0.8rem; font-weight:700; color:#dc2626; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.5rem;">Riski</div>
                            <ul style="margin:0; padding-left:1.2rem; font-size:0.83rem; color:#334155; line-height:1.8;">
                                ${wcRisks.map(r => `<li>${r}</li>`).join('')}
                            </ul>
                        </div>
                        <div>
                            <div style="font-size:0.8rem; font-weight:700; color:#2563eb; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.5rem;">Piemērotās lomas</div>
                            <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                ${wcRoles.map(r => `<span style="background:#eff6ff; color:#1d4ed8; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:600;">${r}</span>`).join('')}
                            </div>
                        </div>
                    </div>

                    <!-- Kolonna 3: Slāņu analīze -->
                    <div>
                        <div style="background:#f8fafc; border-radius:10px; padding:0.9rem; margin-bottom:0.8rem; border-left:3px solid #64748b;">
                            <div style="font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Ko nozīmē šī pozīcija</div>
                            <div style="font-size:0.82rem; color:#334155; line-height:1.5;">${q.base_desc}</div>
                        </div>
                        <div style="background:#f0f9ff; border-radius:10px; padding:0.9rem; margin-bottom:0.8rem; border-left:3px solid #0ea5e9;">
                            <div style="font-size:0.72rem; font-weight:700; color:#0369a1; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Šobrīd (${dasha.lord || '—'} Dasha)</div>
                            <div style="font-size:0.82rem; color:#334155; line-height:1.5;">${wc.dashaDynamic}</div>
                        </div>
                        <div style="background:#fff7ed; border-radius:10px; padding:0.9rem; border-left:3px solid #f59e0b;">
                            <div style="font-size:0.72rem; font-weight:700; color:#b45309; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Stresa profils</div>
                            <div style="font-size:0.82rem; color:#334155; line-height:1.5;">${wc.stressComment}</div>
                        </div>
                    </div>
                </div>

                <!-- Spēku karte (atsevišķie faktori) -->
                ${forceMapBlock}

                <!-- Precīzie skaitļi (tehniskais aprēķins) -->
                <details style="border-top:1px solid #f1f5f9;">
                    <summary style="padding:0.8rem 1.6rem; font-size:0.8rem; font-weight:600; color:#64748b; cursor:pointer; user-select:none; list-style:none; display:flex; align-items:center; gap:6px;">
                        <span style="font-size:0.7rem;">▶</span> Precīzie skaitļi un normalizācija (tehniskais aprēķins)
                    </summary>
                    <div style="padding:0 1.6rem 1rem;">
                        ${factorsTable}
                        <div style="margin-top:0.7rem; font-size:0.73rem; color:#94a3b8; font-style:italic;">
                            Normalizācija (simetriska ap 0; raw=0 → 50% = neitrāls): ietekme = ${wc.dominance_pct}% &nbsp;|&nbsp;
                            stabilitāte = ${wc.stability_pct}% &nbsp;(raw: ${wc.raw_x >= 0 ? '+' : ''}${wc.raw_x} / ${wc.raw_y >= 0 ? '+' : ''}${wc.raw_y})
                        </div>
                    </div>
                </details>
            </div>

          </details>
        </div>`;

    return workCharacterBlock;
}
