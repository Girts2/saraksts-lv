// day_business_view.js — "Dienas" IZPILDOŠAIS panelis (executive summary biznesa lietotājam)
// ─────────────────────────────────────────────────────────────────────────────
// Apvieno DIVUS jau esošos ŠODIENAS skatus vienā, biznesa cilvēkam acumirklī
// uztveramā kartītē:
//   1) Biznesa kalendāra dienas kartīte (statuss 🟢/🟡/🔴, kopsavilkums, rīcības
//      logi, Rahu Kaal brīdinājums, "kāpēc", saskaņa/ticamība) — dzinējs
//      buildBusinessCalendar (electional.js), prezentācija sky_chart bizCalDayCard.
//   2) Mēness jaudas 24h līkne ar muhurtām (Abhijit/Rahu Kaal/Brahma), saullēkts/
//      riets, tranzītu pīķi — dati buildDayConsensus (weekly_consensus.js),
//      prezentācija tab_q4_future 7-dienu SVG.
//
// Šeit dzinēji NETIEK dublēti — abi augstāk minētie tiek izsaukti atkārtoti; jauna
// ir tikai prezentācija (viena hierarhiska kartīte). Ideālā izkārtojuma pamatā —
// biznesa lietotāja 3 jautājumi: (1) vai šodien zaļā gaisma, (2) cikos rīkoties /
// cikos klusēt, (3) kāpēc (pārliecībai).
// ─────────────────────────────────────────────────────────────────────────────

import { buildBusinessCalendar, fmtHour } from '../../logic/electional.js?v=8';
import { buildDayConsensus } from '../../logic/weekly_consensus.js?v=11';

// ── Palīgi (kanoniskā versija sky_chart.js — atkārtoti šeit, lai panelis būtu
//    pašpietiekams un neatkarīgs no UI moduļa iekšējiem elementiem) ────────────

// Stundas kategorija parastam cilvēkam: laba / neitrāla / izvairies (3 krāsas)
function hourCategory(s) { if (s >= 60) return 'good'; if (s >= 42) return 'ok'; return 'avoid'; }
const HOUR_COL = { good: '#22c55e', ok: '#fbbf24', avoid: '#ef4444' };
const heatColor = (s) => HOUR_COL[hourCategory(s)];
const CAT_LV = { good: 'laba', ok: 'neitrāla', avoid: 'izvairies' };

// Dienas kvalitāte = nomoda stundu (6–22) vidējais (sasaucas ar joslas krāsām)
function dayQuality(hs) {
    let sum = 0, n = 0;
    for (let h = 6; h < 22; h++) { if (hs[h] != null) { sum += hs[h]; n++; } }
    const avg = n ? sum / n : 50;
    if (avg >= 56) return { key: 'good',  emoji: '🟢', word: 'Laba diena',      col: '#16a34a', bg: '#ecfdf5', bd: '#86efac', avg };
    if (avg >= 45) return { key: 'mixed', emoji: '🟡', word: 'Jaukta diena',    col: '#d97706', bg: '#fffbeb', bd: '#fcd34d', avg };
    return                 { key: 'hard',  emoji: '🔴', word: 'Sarežģīta diena', col: '#dc2626', bg: '#fef2f2', bd: '#fca5a5', avg };
}

// Viena teikuma dienas kopsavilkums (kvalitāte × cik notikumiem bagāta)
function daySummary(intensityScore, q) {
    const eventful = (intensityScore ?? 0) >= 50;
    if (q.key === 'good')  return eventful
        ? 'Spēcīga un atbalstoša diena — labs brīdis rīkoties un virzīt svarīgo uz priekšu.'
        : 'Mierīga, labvēlīga diena — viss rit gludi, bez lieliem satricinājumiem.';
    if (q.key === 'mixed') return eventful
        ? 'Mainīga diena ar daudz notikumiem — ir gan labi, gan saspringti brīži. Izvēlies laiku rūpīgi.'
        : 'Neitrāla, klusa diena — nekas īpašs nenotiek. Laba ikdienas darbiem.';
    return eventful
        ? 'Saspringta, notikumiem bagāta diena — emocijas un berze augstas. Svarīgus lēmumus labāk atliec.'
        : 'Smagnēja, mazliet kūtra diena — enerģijas maz. Nesteidzies ar svarīgo.';
}

// Nepārtrauktie stundu diapazoni pēc kategorijas (tikai nomoda 6–22)
function hourRanges(hs, wantCat) {
    const out = []; let cur = null;
    for (let h = 6; h < 22; h++) {
        if (hourCategory(hs[h]) === wantCat) { if (!cur) cur = [h, h]; cur[1] = h; }
        else if (cur) { out.push(cur); cur = null; }
    }
    if (cur) out.push(cur);
    return out;
}
const fmtRanges = (ranges) => ranges.map(r => `${fmtHour(r[0])}–${fmtHour(r[1] + 1)}`).join(', ');

// Decimāla lokālā stunda → "HH:MM"
const toHM = (dec) => {
    let x = ((dec % 24) + 24) % 24;
    let h = Math.floor(x), m = Math.round((x - h) * 60);
    if (m === 60) { m = 0; h = (h + 1) % 24; }
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
};

// BaZi domēns → biznesa ikona + vārds (fokusa taga tulkošanai bez žargona)
const DOMAIN_META = {
    nauda:        { icon: '💰', word: 'Nauda / finanses' },
    darbs:        { icon: '💼', word: 'Darbs / karjera' },
    attiecības:   { icon: '🤝', word: 'Attiecības / sarunas' },
    komunikācija: { icon: '💬', word: 'Komunikācija / darījumi' },
    veselība:     { icon: '🌿', word: 'Veselība / atpūta' },
    risks:        { icon: '⚠️', word: 'Paaugstināts risks' },
    vispārējs:    { icon: '🎯', word: 'Vispārējs fons' },
};

const MOOD_WORD   = { supportive: 'labs, atbalstošs', challenging: 'saspringts, jūtīgs', neutral: 'mierīgs' };
const ENERGY_WORD = { supportive: 'labvēlīga',        challenging: 'saspringta',         neutral: 'neitrāla' };
const VAL_DOT     = { supportive: '#22c55e',          challenging: '#ef4444',            neutral: '#94a3b8' };
const dot = (v) => `<span style="display:inline-block; width:9px; height:9px; border-radius:50%; background:${VAL_DOT[v] || VAL_DOT.neutral}; margin-right:6px; vertical-align:middle;"></span>`;

// ── Klikšķu skaidrojumi joslas elementiem: KO nozīmē + KĀ ietekmē personu ─────
// _dayBizCtx glabā pašreiz attēloto dienu (d/hourly/q); window.dayBizExplain(kind,arg)
// pārraksta #day-biz-explain kastīti ar attiecīgā elementa aprakstu. Konteksts
// tiek atsvaidzināts katrā renderDayBizInner (dienas maiņa / lentes bīde).
let _dayBizCtx = null;

function explainShell(accent, icon, title, body) {
    return `<div style="display:flex; gap:10px; align-items:flex-start; background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid ${accent}; border-radius:10px; padding:0.7rem 0.9rem;">
        <span style="font-size:1.3rem; line-height:1.1;">${icon}</span>
        <div><div style="font-weight:800; color:#0f172a; font-size:0.85rem; margin-bottom:2px;">${title}</div>
        <div style="font-size:0.8rem; color:#475569; line-height:1.5;">${body}</div></div>
    </div>`;
}
function explainHint() {
    // Noklusējuma stāvoklī (nekas nav uzklikšķināts) tukšo labo pusi izmantojam, lai
    // cilvēkvalodā izskaidrotu paneļa galveno terminu — "Mēness jauda" — lasītājam, kas
    // astroloģiju nepārzina. Pēc tam saglabājam "klikšķini" pavedienu.
    return `<div style="display:flex; flex-direction:column; gap:0.55rem;">
        <div style="display:flex; gap:9px; align-items:flex-start; background:#eef2ff; border:1px solid #e0e7ff; border-radius:10px; padding:0.65rem 0.85rem;">
            <span style="font-size:1.1rem; line-height:1.2;">🌙</span>
            <div style="font-size:0.78rem; color:#3730a3; line-height:1.5;"><b>Mēness jauda</b> (zilā līkne) rāda, cik viegli tev šajā stundā koncentrēties un rīkoties. <b>Augstu</b> = iekšēja plūsma, viss veicas vieglāk; <b>zemu</b> = pretestība, labāk rutīna un atpūta.</div>
        </div>
        <div style="display:flex; gap:8px; align-items:center; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:0.55rem 0.85rem; font-size:0.75rem; color:#94a3b8;">
            <span style="font-size:0.95rem;">💡</span> Klikšķini uz jebkura elementa grafikā vai krāsu joslā zem tā — parādīsies, ko tas nozīmē un kā tevi ietekmē.
        </div>
    </div>`;
}
// Šodienas Mēness nakšatra (no 48h loga) — dod cilvēkvalodā "kam šodiena piemērota".
// Pārņemts no bij. "4. Dienas Stīga" sadaļas — vērtīgais, saprotamais parametrs (fokusa
// teksts, nevis žargons). Pieejams tikai šodienai; citām dienām 48h logs necentrējas → null.
function currentNakInfo(profile) {
    const arr = profile?.nakshatra_transits_48h;
    if (!Array.isArray(arr)) return null;
    const now = Date.now();
    const cur = arr.find(n => now >= n.startMs && now <= n.endMs);
    const nd = cur?.nakData;
    if (!nd || !nd.natureFocus) return null;
    return { name: nd.nakshatra, icon: nd.nakIcon || '🌙', focus: nd.natureFocus };
}
// Elementa apraksts atkarībā no veida (kind) un pašreizējās dienas konteksta
function buildExplainHTML(kind, arg, ctx) {
    if (!ctx) return explainHint();
    const { d, hourly } = ctx;
    const rk = d.rahuKaal || {}, ab = d.abhijit || {}, br = d.brahma || {};
    switch (kind) {
        case 'hour': {
            const h = arg;
            const sc = (d.hourlyScore || [])[h] ?? 50;
            const cat = hourCategory(sc);
            const gold = (d.golden?.hours || []).includes(h);
            const inRk = rk.start != null && (h + 1) > rk.start && h < rk.end;
            const power = hourly[h];
            const catWord = { good: 'laba', ok: 'neitrāla', avoid: 'izvairāma' }[cat];
            const catEffect = cat === 'good'
                ? 'Tavai enerģijai un apstākļiem šī stunda saskan — labs brīdis darīt svarīgo: sarunas, lēmumi, virzīšana uz priekšu.'
                : cat === 'avoid'
                ? 'Paaugstināta berze un pretestība — neuzsāc svarīgo, izvairies no strīdiem un steidzīgiem lēmumiem.'
                : 'Neitrāla fona stunda — laba ikdienas darbiem, bez izteiktas ietekmes vienā vai otrā virzienā.';
            let extra = '';
            if (gold) extra += ' <b>Ietilpst tavā dienas zelta logā</b> — dienas labākais brīdis rīkoties.';
            if (inRk) extra += ' <b>Šī ir Rahu Kaal (šķēršļu) stunda</b> — neslēdz darījumus, nesāc jaunu.';
            const powerStr = (power != null) ? ` Mēness jauda šai stundai ≈ ${power}%.` : '';
            return explainShell(HOUR_COL[cat], cat === 'good' ? '🟢' : cat === 'avoid' ? '🔴' : '🟡',
                `Plkst. ${String(h).padStart(2, '0')}:00–${String((h + 1) % 24).padStart(2, '0')}:00 — ${catWord} stunda`,
                catEffect + extra + powerStr);
        }
        case 'curve': {
            let peak = 0, peakH = 0;
            (hourly || []).forEach((v, i) => { if (v > peak) { peak = v; peakH = i; } });
            return explainShell('#6366f1', '⚡', 'Mēness jaudas līkne (Tara Bala)',
                `Rāda, cik Mēness šodien tev enerģētiski palīdz — stundu pa stundai. <b>Augsts</b> = iekšējā plūsma, viegli koncentrēties un virzīties; <b>zems</b> = pretestība, labāk atpūta un rutīna. Šodien jaudas pīķis ≈ <b>${peak}%</b> ap plkst. ${String(peakH).padStart(2, '0')}:00.`);
        }
        case 'abhijit':
            return explainShell('#f59e0b', '☀️', `Abhijit Muhurta — "uzvaras stunda"${ab.intensity === 'weak' ? ' (šodien vājāka)' : ''}`,
                `Vēdiskā tradīcijā labvēlīgākais dienas brīdis (ap pusdienlaiku) uzsākt svarīgo: līgumi, prezentācijas, pieteikumi. Tev šodien: <b>${ab.start != null ? toHM(ab.start) : '—'}–${ab.end != null ? toHM(ab.end) : '—'}</b>. Ja iespējams — ieplāno svarīgāko soli šeit.`);
        case 'rahu':
            return explainShell('#ef4444', '🛑', 'Rahu Kaal — dienas šķēršļu josla',
                `Tradicionāli nelabvēlīgs periods: paaugstināts kļūdu, kavēkļu un pārpratumu risks. Neslēdz darījumus un nesāc jaunu; strādā ar jau iesākto. Tev šodien: <b>${rk.start != null ? toHM(rk.start) : '—'}–${rk.end != null ? toHM(rk.end) : '—'}</b>.`);
        case 'brahma':
            return explainShell('#8b5cf6', '🌌', 'Brahma Muhurta — agrā rīta stunda',
                `Klusākais, skaidrākais prāta laiks pirms saullēkta. Ideāls dziļam darbam, plānošanai un stratēģijai, kamēr pasaule vēl guļ. Tev: <b>${br.start != null ? toHM(br.start) : '—'}–${br.end != null ? toHM(br.end) : '—'}</b>.`);
        case 'sunrise':
            return explainShell('#f59e0b', '🌅', `Saullēkts ${d.sunriseHour != null ? toHM(d.sunriseHour) : '—'}`,
                'Dienas astronomiskā sākuma robeža. No tā rēķina planētu stundas un muhurtas — tāpēc tas nosaka, cikos iekrīt tavi labie un šķēršļu logi.');
        case 'sunset':
            return explainShell('#ec4899', '🌇', `Saulriets ${d.sunsetHour != null ? toHM(d.sunsetHour) : '—'}`,
                'Dienas gaišā posma beigas. Pēc tā enerģija dabiski nostājas uz atslābumu; svarīgus jaunus sākumus labāk atstāt rītam.');
        case 'peak': {
            const a = (d.western?.aspects || [])[arg];
            if (!a) return explainHint();
            const PL = { Saule: 'Saule', Merkurs: 'Prāts/komunikācija', Venera: 'Attiecības/finanses', Marss: 'Rīcība/enerģija', Jupiters: 'Veiksme/izaugsme', Saturns: 'Struktūra/disciplīna' };
            const harmWord = a.harmony === 1 ? 'atbalstošs' : a.harmony === -1 ? 'saspringts' : 'jaukts';
            const acc = a.harmony === 1 ? '#10b981' : a.harmony === -1 ? '#ef4444' : '#a855f7';
            return explainShell(acc, '✦', `Tranzīta pīķis plkst. ${a.peakHour != null ? toHM(a.peakHour) : '—'}`,
                `Debesu planēta saskaras ar tavu dzimšanas karti: <b>${PL[a.transit] || a.transit} ↔ ${PL[a.natal] || a.natal}</b> (${harmWord} brīdis). Šajā stundā attiecīgā dzīves joma izceļas spilgtāk — ${a.harmony === -1 ? 'esi uzmanīgs, iespējama berze' : 'labs brīdis to izmantot'}.`);
        }
        default: return explainHint();
    }
}

// ── Apvienotā 24h josla: krāsu kvalitātes bloki + Mēness jaudas līkne + muhurtas ─
// "Zilā jaudas līkne uzlikta tieši virsū krāsainajiem stundu blokiem" (biznesa maketa
// centrālais elements). Apakšā — kraukšķīgā 24h biznesa kalendāra josla ar zelta
// logu un Rahu Kaal ietvariem; "Šobrīd" atzīme šķērso abus slāņus.
function renderTimeline(d, hourly, brahma, nowH, nowM, nowStr, isToday = true, sel = null) {
    const hs = d.hourlyScore || [];
    const X0 = 34, PW = 516, YT = 12, YB = 62, PH = YB - YT;   // līknes zona
    const BW = PW / 24;
    const STRIP_Y = 68, STRIP_H = 10;                          // kraukšķīgā biznesa josla
    const bx = (h) => X0 + h * BW;                             // bloka kreisā mala
    const cx = (h) => X0 + (h + 0.5) * BW;                     // bloka centrs (līknes punkts)
    const tx = (t) => X0 + (Math.max(0, Math.min(24, t)) / 24) * PW;  // nepārtraukts laiks
    const vy = (v) => YB - (Math.max(0, Math.min(100, v)) / 100) * PH;

    // Fona krāsu tinte (mīksta) aiz līknes
    let tint = '';
    for (let h = 0; h < 24; h++) tint += `<rect x="${bx(h).toFixed(1)}" y="${YT}" width="${BW.toFixed(2)}" height="${PH}" fill="${heatColor(hs[h] ?? 50)}" opacity="0.10"/>`;

    const grid = `
        <line x1="${X0}" y1="${YT}" x2="${X0 + PW}" y2="${YT}" stroke="#eef2f7" stroke-width="1" stroke-dasharray="3 3"/>
        <line x1="${X0}" y1="${vy(50).toFixed(1)}" x2="${X0 + PW}" y2="${vy(50).toFixed(1)}" stroke="#eef2f7" stroke-width="1" stroke-dasharray="3 3"/>
        <line x1="${X0}" y1="${YB}" x2="${X0 + PW}" y2="${YB}" stroke="#e2e8f0" stroke-width="1.2"/>`;
    const ylabels = `
        <text x="${X0 - 5}" y="${YT + 3}" font-size="7" fill="#94a3b8" text-anchor="end" font-weight="700">100%</text>
        <text x="${X0 - 5}" y="${(vy(50) + 3).toFixed(1)}" font-size="7" fill="#94a3b8" text-anchor="end" font-weight="700">50%</text>
        <text x="${X0 - 5}" y="${YB + 3}" font-size="7" fill="#94a3b8" text-anchor="end" font-weight="700">0%</text>`;

    // Brahma Muhurta (mīksts violets)
    let brahmaHtml = '';
    if (brahma && brahma.end > 0 && brahma.start < 24) {
        const a = tx(Math.max(0, brahma.start)), b = tx(Math.min(24, brahma.end));
        if (b > a) brahmaHtml = `<rect x="${a.toFixed(1)}" y="${YT}" width="${(b - a).toFixed(1)}" height="${PH}" fill="rgba(139,92,246,0.10)" stroke="rgba(139,92,246,0.28)" stroke-width="0.6" stroke-dasharray="2 1.5" style="cursor:pointer;" onclick="window.dayBizExplain && window.dayBizExplain('brahma')"><title>Brahma Muhurta — klikšķini, lai uzzinātu vairāk</title></rect>`;
    }
    // Rahu Kaal (sarkans svītrots)
    let rahuHtml = '';
    const rk = d.rahuKaal;
    if (rk && rk.start != null) {
        const a = tx(rk.start), b = tx(rk.end);
        if (b > a) rahuHtml = `<rect x="${a.toFixed(1)}" y="${YT}" width="${(b - a).toFixed(1)}" height="${PH}" fill="rgba(239,68,68,0.12)" stroke="rgba(239,68,68,0.55)" stroke-width="1" stroke-dasharray="3 1.5" rx="1" style="cursor:pointer;" onclick="window.dayBizExplain && window.dayBizExplain('rahu')"><title>Šķēršļu laiks (Rahu Kaal) — klikšķini, lai uzzinātu vairāk</title></rect>`;
    }
    // Abhijit (zelta josla no pamatnes)
    let abhijitHtml = '';
    const ab = d.abhijit;
    if (ab && ab.start != null) {
        const a = tx(ab.start), b = tx(ab.end);
        const strong = ab.intensity !== 'weak';
        const hgt = (strong ? 1 : 0.6) * PH, y = YB - hgt;
        if (b > a) abhijitHtml = `<rect x="${a.toFixed(1)}" y="${y.toFixed(1)}" width="${(b - a).toFixed(1)}" height="${hgt.toFixed(1)}" fill="rgba(251,191,36,0.28)" stroke="#f59e0b" stroke-width="1.1" rx="1" style="cursor:pointer;" onclick="window.dayBizExplain && window.dayBizExplain('abhijit')"><title>Veiksmīgā stunda (Abhijit) — klikšķini, lai uzzinātu vairāk</title></rect>`;
    }

    // Mēness jaudas līkne (0–100%) + laukuma pildījums. Vizuālais = pointer-events:none;
    // klikšķiem atsevišķa "resnā" caurspīdīgā trepe (curveHitHtml), novietota augšējā slānī.
    let curveHtml = '', curveHitHtml = '';
    if (hourly && hourly.length === 24) {
        const pts = hourly.map((v, h) => `${cx(h).toFixed(1)} ${vy(v).toFixed(1)}`);
        const line = 'M ' + pts.join(' L ');
        const fill = `M ${cx(0).toFixed(1)} ${YB} L ` + pts.join(' L ') + ` L ${cx(23).toFixed(1)} ${YB} Z`;
        curveHtml = `<g pointer-events="none"><path d="${fill}" fill="rgba(99,102,241,0.08)"/><path d="${line}" fill="none" stroke="#6366f1" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></g>`;
        curveHitHtml = `<path d="${line}" fill="none" stroke="transparent" stroke-width="11" pointer-events="stroke" style="cursor:pointer;" onclick="window.dayBizExplain && window.dayBizExplain('curve')"><title>Mēness jaudas līkne — klikšķini</title></path>`;
    }

    // Tranzītu pīķi (uz līknes)
    let peaksHtml = '';
    (d.western?.aspects || []).forEach((a, idx) => {
        if (a.peakHour == null) return;
        const px = tx(a.peakHour);
        const hi = Math.max(0, Math.min(23, Math.round(a.peakHour)));
        const py = vy((hourly && hourly[hi] != null) ? hourly[hi] : 50);
        const col = a.harmony === 1 ? '#10b981' : a.harmony === -1 ? '#ef4444' : '#a855f7';
        peaksHtml += `<g style="cursor:pointer;" onclick="window.dayBizExplain && window.dayBizExplain('peak',${idx})"><circle cx="${px.toFixed(1)}" cy="${py.toFixed(1)}" r="7" fill="transparent"/><circle cx="${px.toFixed(1)}" cy="${py.toFixed(1)}" r="3" fill="${col}" stroke="#fff" stroke-width="0.8" pointer-events="none"/><title>Tranzīta pīķis plkst. ${toHM(a.peakHour)} — klikšķini</title></g>`;
    });

    // Saullēkta / saulrieta atzīmes
    let sunHtml = '';
    if (d.sunriseHour != null && d.sunsetHour != null) {
        const sr = tx(d.sunriseHour), ss = tx(d.sunsetHour);
        const sunMark = (x, col, emoji, kind) => `<g style="cursor:pointer;" onclick="window.dayBizExplain && window.dayBizExplain('${kind}')">
            <rect x="${(x - 7).toFixed(1)}" y="0" width="14" height="${STRIP_Y}" fill="transparent"/>
            <line x1="${x.toFixed(1)}" y1="${YT}" x2="${x.toFixed(1)}" y2="${STRIP_Y + STRIP_H}" stroke="${col}" stroke-width="0.7" stroke-dasharray="2 2" pointer-events="none"/>
            <text x="${x.toFixed(1)}" y="${YT - 3}" font-size="7" text-anchor="middle" pointer-events="none">${emoji}</text>
        </g>`;
        sunHtml = sunMark(sr, '#f59e0b', '🌅', 'sunrise') + sunMark(ss, '#ec4899', '🌇', 'sunset');
    }

    // Apakšējā kraukšķīgā biznesa josla (24 bloki; zelta logs + Rahu ietvari + saullēkts/riets)
    const goldSet = new Set(d.golden ? d.golden.hours : []);
    const inRahu = (h) => rk && rk.start != null && (h + 1) > rk.start && h < rk.end;
    const srH = Math.round(d.sunriseHour ?? 6), ssH = Math.round(d.sunsetHour ?? 21);
    let strip = '';
    for (let h = 0; h < 24; h++) {
        const gold = goldSet.has(h), rahu = inRahu(h);
        const bd = gold ? 'stroke="#b45309" stroke-width="1.4"' : (rahu ? 'stroke="#7f1d1d" stroke-width="1.4"' : 'stroke="#ffffff" stroke-width="0.5"');
        const mark = (h === srH) ? '🌅' : (h === ssH) ? '🌇' : '';
        const tip = `${String(h).padStart(2, '0')}:00 — ${CAT_LV[hourCategory(hs[h] ?? 50)]} stunda${gold ? ' · labākais brīdis' : ''}${rahu ? ' · Rahu Kaal' : ''}`;
        strip += `<rect x="${bx(h).toFixed(1)}" y="${STRIP_Y}" width="${BW.toFixed(2)}" height="${STRIP_H}" fill="${heatColor(hs[h] ?? 50)}" ${bd} style="cursor:pointer;" onclick="window.dayBizExplain && window.dayBizExplain('hour',${h})"><title>${tip} — klikšķini</title></rect>`;
        if (mark) strip += `<text x="${cx(h).toFixed(1)}" y="${(STRIP_Y + STRIP_H - 2.4).toFixed(1)}" font-size="6.5" text-anchor="middle" pointer-events="none">${mark}</text>`;
    }

    // "Šobrīd" atzīme (sarkana vertikāle + punkts uz līknes + laiks) — TIKAI šodienai
    const curXf = tx(nowH + nowM / 60);
    const curY = vy((hourly && hourly[Math.min(23, nowH)] != null) ? hourly[Math.min(23, nowH)] : 50);
    const labelRight = curXf > X0 + PW - 40;
    const playhead = isToday ? `
        <line x1="${curXf.toFixed(1)}" y1="0" x2="${curXf.toFixed(1)}" y2="${STRIP_Y + STRIP_H}" stroke="#ef4444" stroke-width="1.2" stroke-dasharray="3 1.5"/>
        <circle cx="${curXf.toFixed(1)}" cy="${curY.toFixed(1)}" r="4" fill="#ef4444" stroke="#fff" stroke-width="1.5"/>
        <text x="${labelRight ? (curXf - 4).toFixed(1) : (curXf + 4).toFixed(1)}" y="8" font-size="8" font-weight="800" fill="#ef4444" text-anchor="${labelRight ? 'end' : 'start'}">${nowStr}</text>` : '';

    const xlabels = [0, 6, 12, 18, 24].map(h =>
        `<text x="${tx(h).toFixed(1)}" y="${STRIP_Y + STRIP_H + 9}" font-size="7" fill="#94a3b8" text-anchor="middle" font-weight="700" font-family="monospace">${String(h).padStart(2, '0')}</text>`
    ).join('');

    // Izvēlētā elementa izcēlums (tumšs apvilkums) — ģeometrija tāda pati kā elementiem
    let hlHtml = '';
    if (sel) {
        const HC = '#0f172a';
        const outline = (a, b) => `<rect x="${a.toFixed(1)}" y="${(YT - 1).toFixed(1)}" width="${(b - a).toFixed(1)}" height="${(PH + 2).toFixed(1)}" fill="none" stroke="${HC}" stroke-width="2" rx="2"/>`;
        if (sel.kind === 'hour') {
            const h = sel.arg;
            hlHtml = `<rect x="${(bx(h) - 0.6).toFixed(1)}" y="${(YT - 1).toFixed(1)}" width="${(BW + 1.2).toFixed(1)}" height="${(STRIP_Y + STRIP_H - YT + 2).toFixed(1)}" fill="none" stroke="${HC}" stroke-width="1.7" rx="2"/>`;
        } else if (sel.kind === 'abhijit' && ab && ab.start != null) {
            hlHtml = outline(tx(ab.start), tx(ab.end));
        } else if (sel.kind === 'rahu' && rk && rk.start != null) {
            hlHtml = outline(tx(rk.start), tx(rk.end));
        } else if (sel.kind === 'brahma' && brahma && brahma.start != null) {
            hlHtml = outline(tx(Math.max(0, brahma.start)), tx(Math.min(24, brahma.end)));
        } else if (sel.kind === 'curve' && hourly && hourly.length === 24) {
            const pts = hourly.map((v, h) => `${cx(h).toFixed(1)} ${vy(v).toFixed(1)}`);
            hlHtml = `<path d="M ${pts.join(' L ')}" fill="none" stroke="#312e81" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" opacity="0.92"/>`;
        } else if ((sel.kind === 'sunrise' && d.sunriseHour != null) || (sel.kind === 'sunset' && d.sunsetHour != null)) {
            const x = tx(sel.kind === 'sunrise' ? d.sunriseHour : d.sunsetHour);
            hlHtml = `<line x1="${x.toFixed(1)}" y1="${(YT - 7).toFixed(1)}" x2="${x.toFixed(1)}" y2="${STRIP_Y + STRIP_H}" stroke="${HC}" stroke-width="1.8"/><circle cx="${x.toFixed(1)}" cy="${(YT - 6).toFixed(1)}" r="6.5" fill="none" stroke="${HC}" stroke-width="1.5"/>`;
        } else if (sel.kind === 'peak') {
            const a = (d.western?.aspects || [])[sel.arg];
            if (a && a.peakHour != null) {
                const px = tx(a.peakHour), hi = Math.max(0, Math.min(23, Math.round(a.peakHour)));
                const py = vy((hourly && hourly[hi] != null) ? hourly[hi] : 50);
                hlHtml = `<circle cx="${px.toFixed(1)}" cy="${py.toFixed(1)}" r="6.5" fill="none" stroke="${HC}" stroke-width="1.8"/>`;
            }
        }
    }

    // Slāņu secība klikšķiem: fons/režģis/līknes vizuālais/playhead/uzraksti = pointer-events:none;
    // interaktīvie (muhurtas, līknes trepe, pīķi, saullēkts/riets, stundu bloki) uztver klikšķus.
    const svg = `<svg viewBox="0 0 560 92" style="width:100%; height:auto; overflow:visible;" role="img" aria-label="Dienas 24 stundu jaudas un kvalitātes josla — interaktīva, klikšķini elementu">
        <g pointer-events="none">${tint}${grid}</g>
        ${brahmaHtml}${rahuHtml}${curveHtml}${abhijitHtml}${curveHitHtml}${peaksHtml}${sunHtml}${strip}
        <g pointer-events="none">${hlHtml}${playhead}${ylabels}${xlabels}</g>
    </svg>`;

    const lgItem = (swatch, label) => `<span style="display:inline-flex; align-items:center; gap:4px;">${swatch}${label}</span>`;
    const sq = (fill, extra = '') => `<span style="display:inline-block; width:9px; height:9px; border-radius:2px; background:${fill}; ${extra}"></span>`;
    const legend = `<div style="display:flex; flex-wrap:wrap; gap:9px 13px; justify-content:center; font-size:0.6rem; color:#64748b; font-weight:700; margin-top:8px; padding-top:8px; border-top:1px solid #f1f5f9;">
        ${lgItem(sq('#22c55e'), 'laba')}
        ${lgItem(sq('#fbbf24'), 'neitrāla')}
        ${lgItem(sq('#ef4444'), 'izvairies')}
        ${lgItem('<span style="display:inline-block; width:14px; height:2.5px; border-radius:2px; background:#6366f1;"></span>', 'Mēness jauda')}
        ${lgItem(sq('rgba(251,191,36,0.28)', 'box-shadow:inset 0 0 0 1px #f59e0b;'), 'Abhijit (zelta)')}
        ${lgItem(sq('rgba(239,68,68,0.12)', 'box-shadow:inset 0 0 0 1px rgba(239,68,68,0.55);'), 'Rahu Kaal')}
        ${lgItem(sq('rgba(139,92,246,0.10)', 'box-shadow:inset 0 0 0 1px rgba(139,92,246,0.28);'), 'Brahma')}
        ${isToday ? lgItem('<span style="display:inline-block; width:2px; height:10px; background:#ef4444;"></span>', 'Šobrīd') : ''}
    </div>`;

    // Vienmēr redzams, cilvēkvalodā skaidrojums, ko nozīmē "Mēness jauda" (zilā līkne) —
    // lasītājam, kas nepārzina astroloģijas terminus (leģendā tas ir tikai nosaukums).
    const caption = `<div style="display:flex; gap:7px; align-items:flex-start; margin-top:9px; padding:0.5rem 0.75rem; background:#f8faff; border:1px solid #eef2ff; border-radius:8px; font-size:0.72rem; color:#475569; line-height:1.45;">
        <span style="font-size:0.9rem; line-height:1.2;">🌙</span>
        <span><b style="color:#4f46e5;">Mēness jauda</b> — zilā līkne rāda tavas iekšējās enerģijas ritmu dienas gaitā: cik viegli šajā stundā koncentrēties, izlemt un virzīties uz priekšu. <b>Augstāk = vieglāk</b> (labs brīdis svarīgajam), <b>zemāk = grūtāk</b> (labāk rutīna un atpūta).</span>
    </div>`;

    return svg + legend + caption;
}

// ── Nedēļas kopaina: interaktīva ±dienu lente (konteksts + dienas izvēle) ─────
// Biznesa lietotājam vajag REDZĒT šodienu dziļi UN nedēļas formu vienā acu uzmetienā
// (detaļa + konteksts, kā laikapstākļu lietotnē). Katra šūna: diena + PILNS datums
// (D.M.GGGG), kvalitātes emocijzīme, mini 24h siltumjosla (dienas "forma" + zelta logs),
// labākais laiks. IZVĒLĒTĀ diena izcelta; klikšķis pārbūvē augšējo dziļo kartīti + 24h
// joslu uz to dienu (window.setDayBizDay). Šodiena vienmēr marķēta "ŠODIEN"; pagājušās
// (offset<0) pieklusinātas. Logu var bīdīt ±1 dienu (window.shiftDayBizRibbon).
const WD_SHORT = ['Sv', 'Pr', 'Ot', 'Tr', 'Ce', 'Pk', 'Se'];
function renderWeekRibbon(days, selectedOffset) {
    if (!days || days.length < 2) return '';
    const cells = days.map(d => {
        const q = dayQuality(d.hourlyScore || []);
        const dt = new Date(d.date + 'T12:00:00Z');
        const wd = WD_SHORT[dt.getUTCDay()];
        const dmy = `${dt.getUTCDate()}.${dt.getUTCMonth() + 1}.${dt.getUTCFullYear()}`;
        const g = d.golden;
        const best = g ? `${fmtHour(g.startHour)}–${fmtHour(g.endHour)}` : '—';
        const goldSet = new Set(g ? g.hours : []);
        const hs = d.hourlyScore || [];
        let strip = '';
        for (let h = 0; h < 24; h++) {
            const gold = goldSet.has(h);
            strip += `<span style="flex:1; background:${heatColor(hs[h] ?? 50)};${gold ? ' box-shadow:inset 0 0 0 1px #b45309;' : ''}"></span>`;
        }
        const c = d.consensus || {};
        const consMini = c.confirmed ? `⚖ ${c.agree}/3` : '';
        const isToday = d.offset === 0;
        const isSel = d.offset === selectedOffset;
        const isPast = d.offset < 0;
        const muted = isPast && !isSel;   // pagājušais konteksts (ja nav izvēlēts)
        return `
        <div onclick="window.setDayBizDay && window.setDayBizDay(${d.offset})"
             role="button" tabindex="0" aria-pressed="${isSel}"
             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.setDayBizDay && window.setDayBizDay(${d.offset});}"
             title="${d.weekday}, ${dmy} — ${q.word}${isToday ? ' (šodien)' : isPast ? ' (pagājusi)' : ''}${g ? ` · labākais ${best}` : ''}. Klikšķini → rāda šo dienu augšā."
             style="position:relative; flex:1 1 0; min-width:0; cursor:pointer; border:1px solid ${isSel ? q.col : '#e2e8f0'}; ${isSel ? `background:${q.bg}; box-shadow:0 0 0 1.5px ${q.col};` : 'background:#fff;'} ${muted ? 'opacity:0.5; filter:saturate(0.7);' : ''} border-radius:11px; padding:9px 5px 7px; display:flex; flex-direction:column; gap:4px; align-items:center; text-align:center;">
            ${isToday ? `<span style="position:absolute; top:-7px; left:50%; transform:translateX(-50%); font-size:0.5rem; background:#0f172a; color:#fff; border-radius:4px; padding:1px 5px; font-weight:800; letter-spacing:0.3px; white-space:nowrap;">ŠODIEN</span>` : ''}
            <span style="font-weight:800; color:${isSel ? q.col : '#0f172a'}; font-size:0.82rem; line-height:1;">${wd}</span>
            <span style="color:#94a3b8; font-size:0.6rem; font-variant-numeric:tabular-nums; line-height:1;">${dmy}</span>
            <span style="font-size:1rem; line-height:1;">${q.emoji}</span>
            <div style="display:flex; width:100%; height:9px; gap:0.5px; border-radius:3px; overflow:hidden; box-shadow:inset 0 0 0 1px rgba(15,23,42,0.05);">${strip}</div>
            <div style="font-size:0.7rem; font-weight:800; color:${g ? q.col : '#cbd5e1'}; font-variant-numeric:tabular-nums; line-height:1;">${best}</div>
            <div style="font-size:0.58rem; color:#94a3b8; font-weight:700; line-height:1; min-height:0.58rem;" title="Cik neatkarīgas tradīcijas saskan">${consMini}</div>
        </div>`;
    }).join('');

    const navBtn = (label, onclick, title) => `<button type="button" onclick="${onclick}" title="${title}" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:3px 9px; font-size:0.66rem; font-weight:800; color:#475569; cursor:pointer; white-space:nowrap;">${label}</button>`;

    return `
    <div style="margin-top:1.1rem; border-top:1px solid #f1f5f9; padding-top:0.9rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.7rem;">
            <span style="font-size:0.72rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:1.5px;">📅 Nedēļas kopaina · ${days.length} dienas</span>
            <div style="display:flex; align-items:center; gap:6px;">
                ${navBtn('◀ −1 diena', 'window.shiftDayBizRibbon && window.shiftDayBizRibbon(-1)', 'Pabīdīt logu 1 dienu atpakaļ')}
                ${navBtn('⟳ šodien', 'window.resetDayBiz && window.resetDayBiz()', 'Atgriezties uz šodienu')}
                ${navBtn('+1 diena ▶', 'window.shiftDayBizRibbon && window.shiftDayBizRibbon(1)', 'Pabīdīt logu 1 dienu uz priekšu')}
            </div>
        </div>
        <div style="display:flex; gap:6px;">${cells}</div>
        <div style="font-size:0.66rem; color:#cbd5e1; font-weight:600; margin-top:0.55rem;">klikšķini dienu → rāda to augšā (24h josla) · izvēlētā izcelta · pagājušās pieklusinātas</div>
    </div>`;
}

// ── Interaktīvais stāvoklis: izvēlētā diena (hero) + lentes loga sākums ───────
let _dayProfile = null;
const _dayState = { selectedOffset: 0, ribbonStart: -2, ribbonSpan: 9, selectedElement: null };

function rerenderDayBiz() {
    const root = document.getElementById('day-biz-root');
    if (root) root.innerHTML = renderDayBizInner();
}
function registerDayBizHandlers() {
    if (window.__dayBizHandlersReady) return;
    window.setDayBizDay      = (offset) => { _dayState.selectedOffset = offset; _dayState.selectedElement = null; rerenderDayBiz(); };
    window.shiftDayBizRibbon = (delta)  => { _dayState.ribbonStart   += delta;  rerenderDayBiz(); };
    window.resetDayBiz       = ()       => { _dayState.selectedOffset = 0; _dayState.ribbonStart = -2; _dayState.selectedElement = null; rerenderDayBiz(); };
    // Joslas elementa klikšķis → izvēlas elementu (apraksts kastē + izcēlums grafikā). Atkārtots
    // klikšķis uz tā paša elementa to noņem. Pārbūvē paneli (izcēlums ģeometrija = renderTimeline).
    window.dayBizExplain     = (kind, arg) => {
        const cur = _dayState.selectedElement;
        _dayState.selectedElement = (cur && cur.kind === kind && String(cur.arg) === String(arg)) ? null : { kind, arg };
        rerenderDayBiz();
    };
    window.__dayBizHandlersReady = true;
}

// Relatīvā dienas etiķete galvenei (orientācijai, kad izvēlēta ne-šodiena)
function relDayLabel(offset) {
    const M = { '0': 'ŠODIEN', '1': 'RĪT', '2': 'PARĪT', '-1': 'VAKAR', '-2': 'AIZVAKAR' };
    if (M[offset] !== undefined) return M[offset];
    return offset > 0 ? `+${offset} DIENAS` : `${offset} DIENAS`;
}

// ── Galvenais eksports: interaktīva izvēlētās dienas kartīte + nedēļas lente ──
// Sāk ar šodienu; klikšķis lentē / bīdīšana pārbūvē #day-biz-root saturu uz vietas.
export function renderDayBusinessPanel(profile) {
    if (!profile) return '';
    _dayProfile = profile;
    _dayState.selectedOffset = 0;   // katrs dashboard render sākas ar šodienu
    _dayState.ribbonStart = -2;
    registerDayBizHandlers();
    return `<div id="day-biz-root">${renderDayBizInner()}</div>`;
}

// Kartītes saturs — pārbūvējams pēc dienas izvēles / lentes bīdīšanas
function renderDayBizInner() {
    const profile = _dayProfile;
    if (!profile) return '';
    const sel = _dayState.selectedOffset;

    // Izvēlētā diena (hero) — atsevišķs izsaukums, lai tā būtu pieejama pat tad, ja lente
    // ir pabīdīta prom. Jēldiena (jauda + Brahma) tai pašai dienai.
    const d = buildBusinessCalendar(profile, sel, 1)?.days?.[0];
    if (!d) return '<div style="padding:1.5rem; color:#94a3b8;">Nevarēja aprēķināt izvēlēto dienu.</div>';
    const isToday = !!d.isToday;
    let raw = null;
    try { raw = buildDayConsensus(profile, d.date); } catch (e) { /* jēldiena nav kritiska */ }
    const hourly = raw?.hourly || [];
    const brahma = raw?.brahma || null;

    // Nedēļas lentes logs (var būt pabīdīts prom no izvēlētās dienas)
    const ribbonDays = buildBusinessCalendar(profile, _dayState.ribbonStart, _dayState.ribbonSpan)?.days || [];

    const hs = d.hourlyScore || [];
    const q = dayQuality(hs);
    _dayBizCtx = { d, hourly, q };   // konteksts joslas elementu klikšķu aprakstiem
    const selEl = _dayState.selectedElement;   // izvēlētais joslas elements (apraksts + izcēlums)
    const explainInit = selEl ? buildExplainHTML(selEl.kind, selEl.arg, _dayBizCtx) : explainHint();

    // Pašreizējais lokālais laiks (jēgpilns tikai šodienai) + Mēness jauda
    const tz = profile?.current_loc?.timezone ?? profile?.birth_info?.timezone ?? 'Europe/Riga';
    let nowH = new Date().getHours(), nowM = new Date().getMinutes();
    if (window.moment) { try { const m = window.moment().tz(tz); nowH = m.hours(); nowM = m.minutes(); } catch (e) {} }
    const nowStr = `${String(nowH).padStart(2, '0')}:${String(nowM).padStart(2, '0')}`;
    // Šodien → jauda tekošajā stundā; citām dienām → dienas pīķa jauda
    const jaudaVal = isToday ? (hourly[nowH] != null ? hourly[nowH] : null)
                             : (hourly.length ? Math.max(...hourly) : null);
    const jaudaLbl = isToday ? 'jauda' : 'pīķis';

    const timeUnknown = !!profile?.birth_info?.isTimeUnknown;

    // ── Saskaņa + ticamība ──
    const c = d.consensus || {};
    const consText = c.confirmed ? `Saskaņa ${c.agree}/3` : (c.live >= 2 ? 'Saskaņa: dalīta' : 'Saskaņa: vāja');
    const consOk = !!c.confirmed;
    const confLabel = d.confidence?.label || '—';

    // ── Fokusa tagi ──
    const primDomain = (d.bazi?.domains?.[0]) || (c.domains || []).find(x => x && x !== 'vispārējs') || 'vispārējs';
    const dmeta = DOMAIN_META[primDomain] || DOMAIN_META['vispārējs'];
    const pillarStr = d.bazi?.pillar ? `${d.bazi.pillar.stem} ${d.bazi.pillar.branch}` : '';
    const focusMoney = `<span style="display:inline-flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; padding:5px 11px; font-size:0.82rem; color:#1e293b;">
        <span style="font-size:0.95rem;">${dmeta.icon}</span><b>${dmeta.word}</b>${pillarStr ? `<span style="color:#94a3b8; font-weight:600; font-size:0.74rem;">${pillarStr}</span>` : ''}
    </span>`;
    const moonLbl = (d.moon?.label && d.moon.label !== '—') ? d.moon.label : null;
    const focusMoon = moonLbl ? `<span style="display:inline-flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; padding:5px 11px; font-size:0.82rem; color:#1e293b;">
        <span style="font-size:0.95rem;">⚡</span><b>${moonLbl}</b>${jaudaVal != null ? `<span style="color:#6366f1; font-weight:700; font-size:0.74rem;">${jaudaVal}% ${jaudaLbl}</span>` : ''}
    </span>` : '';

    // ── Rīcības plāns ──
    const g = d.golden;
    const bestTime = g ? `${fmtHour(g.startHour)}–${fmtHour(g.endHour)}` : null;
    const suit = (d.suitable || []).slice(0, 3).map(s => s.label.toLowerCase());
    const goodR = hourRanges(hs, 'good');
    const avoidR = hourRanges(hs, 'avoid');
    const avoidCount = avoidR.reduce((a, r) => a + (r[1] - r[0] + 1), 0);
    const rk = d.rahuKaal || {};
    const rahuStr = (rk.start != null) ? `${fmtHour(rk.start)}–${fmtHour(rk.end)}` : '';

    const actGood = (q.key === 'hard')
        ? `<div style="font-weight:800; color:#0f172a; font-size:0.9rem;">🕊️ Mierīgākais brīdis</div>
           <div style="font-size:1.15rem; font-weight:900; color:${q.col}; margin:3px 0;">${bestTime || 'nav izteikta'}</div>
           <div style="font-size:0.8rem; color:#64748b; line-height:1.45;">Šodien svarīgo labāk atliec. Ja kaut kas jādara — dari šajā logā.</div>`
        : `<div style="font-weight:800; color:#0f172a; font-size:0.9rem;">✅ Labākais laiks rīkoties</div>
           <div style="font-size:1.35rem; font-weight:900; color:${q.col}; margin:3px 0;">${bestTime || 'nav izteikta'}</div>
           ${suit.length ? `<div style="font-size:0.8rem; color:#334155; line-height:1.45;"><span style="color:#94a3b8;">Vislabāk derēs:</span> <b>${suit.join(', ')}</b></div>`
                         : (goodR.length ? `<div style="font-size:0.8rem; color:#64748b;">Labās stundas: ${fmtRanges(goodR)}</div>` : '')}`;

    let avoidStr;
    if (avoidCount >= 11) avoidStr = 'gandrīz visu dienu — neuzsāc svarīgo';
    else if (avoidR.length) avoidStr = fmtRanges(avoidR);
    else avoidStr = 'nav izteiktu bīstamu stundu';
    const actAvoid = `<div style="font-weight:800; color:#0f172a; font-size:0.9rem;">⛔ Izvairies</div>
        <div style="font-size:1.35rem; font-weight:900; color:#dc2626; margin:3px 0;">${avoidStr}</div>
        ${rahuStr ? `<div style="font-size:0.8rem; color:#334155; line-height:1.45;"><span style="color:#94a3b8;">🛑 Kritiskais logs (Rahu Kaal):</span> <b>${rahuStr}</b> — neslēdz darījumus.</div>`
                  : `<div style="font-size:0.8rem; color:#94a3b8;">Nav izteikta kritiskā perioda.</div>`}`;

    // ── "Kāpēc" fona rindas ──
    const whyRows = [];
    if (d.moon)  whyRows.push(`<div style="font-size:0.82rem; color:#334155;">${dot(d.moon.valence)}<b>Noskaņojums:</b> ${MOOD_WORD[d.moon.valence] || 'mierīgs'}</div>`);
    if (d.bazi)  whyRows.push(`<div style="font-size:0.82rem; color:#334155;">${dot(d.bazi.valence)}<b>Dienas enerģija:</b> ${ENERGY_WORD[d.bazi.valence] || 'neitrāla'}</div>`);
    if (d.sunriseHour != null) whyRows.push(`<div style="font-size:0.82rem; color:#334155;"><span style="margin-right:6px;">🌅</span><b>Astronomija:</b> saullēkts ${toHM(d.sunriseHour)} · saulriets ${toHM(d.sunsetHour)}</div>`);
    // "Kam šodiena piemērota" — pārņemts no bij. "Dienas Stīga" (nakšatras fokuss); tikai šodienai.
    if (isToday) {
        const nak = currentNakInfo(profile);
        if (nak) whyRows.push(`<div style="font-size:0.82rem; color:#334155; flex-basis:100%;"><span style="margin-right:6px;">${nak.icon}</span><b>Kam šodiena piemērota:</b> ${nak.focus} <span style="color:#94a3b8; font-size:0.72rem;">(Mēness ${nak.name})</span></div>`);
    }

    const unkNote = timeUnknown
        ? `<div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:0.55rem 0.8rem; margin-top:0.9rem; font-size:0.74rem; color:#92400e; line-height:1.45;">⏳ <b>Dzimšanas laiks nezināms.</b> 24h jaudas līkne ir vidējais rādītājs (stundu logi orientējoši); dienas signāli (statuss, BaZi, tranzīti, saskaņa) ir pilnvērtīgi.</div>`
        : '';

    // ── Kartīte ──
    return `
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-top:5px solid ${q.col}; border-radius:18px; box-shadow:0 4px 22px rgba(15,23,42,0.07); padding:1.5rem 1.7rem; margin-bottom:1.6rem; position:relative; overflow:hidden;">
        <div style="position:absolute; top:-22px; right:-6px; font-size:5rem; opacity:0.06; pointer-events:none;">${q.emoji}</div>

        <!-- Eyebrow -->
        <div style="font-size:0.66rem; font-weight:800; color:${q.col}; text-transform:uppercase; letter-spacing:2.5px; margin-bottom:0.7rem;">Dienas pārskats</div>

        <!-- Galvene: nedēļas diena + pilns datums (ar gadu) + relatīvā etiķete + laiks -->
        <div style="display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.5rem;">
            <div style="display:flex; align-items:baseline; gap:0.5rem; flex-wrap:wrap;">
                <span style="font-size:1.05rem; font-weight:800; color:#0f172a;">${d.weekday}</span>
                <span style="font-size:1.05rem; font-weight:900; color:#0f172a;">${d.dateLabel} ${d.date.slice(0, 4)}</span>
                <span style="font-size:0.62rem; background:${isToday ? '#0f172a' : q.col}; color:#fff; border-radius:5px; padding:2px 7px; font-weight:800; letter-spacing:0.5px;">${relDayLabel(sel)}</span>
            </div>
            ${isToday ? `<span style="font-size:0.95rem; font-weight:800; color:#ef4444; font-variant-numeric:tabular-nums;">🕒 ${nowStr}</span>` : ''}
        </div>

        <!-- Statuss + saskaņa/ticamība -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.6rem; margin-bottom:0.55rem;">
            <span style="display:inline-flex; align-items:center; gap:8px; background:${q.bg}; border:1px solid ${q.bd}; border-radius:10px; padding:6px 14px; font-size:1.05rem; font-weight:900; color:${q.col};">
                <span>${q.emoji}</span>${q.word}
            </span>
            <span style="display:inline-flex; align-items:center; gap:8px; font-size:0.76rem;">
                <span style="background:${consOk ? '#ecfdf5' : '#f1f5f9'}; color:${consOk ? '#16a34a' : '#64748b'}; border:1px solid ${consOk ? '#86efac' : '#e2e8f0'}; border-radius:7px; padding:3px 9px; font-weight:800;" title="Cik neatkarīgas tradīcijas (Mēness · ķīniešu BaZi · rietumu tranzīti) saskan">${consText}${consOk ? ' ✓' : ''}</span>
                <span style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:7px; padding:3px 9px; font-weight:800;" title="Jo tuvāka diena, jo precīzāka prognoze">Ticamība: ${confLabel}</span>
            </span>
        </div>

        <!-- Kreisā puse: kopsavilkums + Fokuss | Labā puse: interaktīvais elementa apraksts -->
        <div style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:1.3rem; align-items:start; margin-bottom:1.1rem;">
            <div>
                <p style="margin:0 0 0.8rem 0; color:#334155; font-size:0.92rem; line-height:1.55; font-weight:500;">${daySummary(d.intensity?.score, q)}</p>
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px;">
                    <span style="font-size:0.72rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">🎯 Fokuss</span>
                    ${focusMoney}${focusMoon}
                </div>
            </div>
            <div id="day-biz-explain">${explainInit}</div>
        </div>

        <!-- Apvienotā laika josla (grafiks) -->
        <div style="border-top:1px solid #f1f5f9; padding-top:0.9rem;">
            <div style="font-size:0.72rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:0.5rem;">Dienas laika josla · 24h</div>
            ${renderTimeline(d, hourly, brahma, nowH, nowM, nowStr, isToday, selEl)}
        </div>

        <!-- Rīcības plāns (2 kolonnas) -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:0.8rem; margin-top:1.1rem;">
            <div style="background:${q.key === 'hard' ? '#f8fafc' : '#f0fdf4'}; border:1px solid ${q.key === 'hard' ? '#e2e8f0' : '#bbf7d0'}; border-radius:12px; padding:0.9rem 1.1rem;">${actGood}</div>
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:0.9rem 1.1rem;">${actAvoid}</div>
        </div>

        <!-- Kāpēc (fona analītika) -->
        <div style="margin-top:1.1rem; border-top:1px solid #f1f5f9; padding-top:0.9rem;">
            <div style="font-size:0.72rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:0.5rem;">Kāpēc · fona analītika</div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem 1.6rem;">${whyRows.join('')}</div>
        </div>

        <!-- Nedēļas kopaina — interaktīva 9-dienu lente (izvēle + ±1 bīde) -->
        ${renderWeekRibbon(ribbonDays, sel)}

        ${unkNote}
    </div>`;
}
