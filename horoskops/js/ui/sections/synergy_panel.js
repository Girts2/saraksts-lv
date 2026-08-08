// Sinerģijas Metrika — 5 sistēmu (Vēdiskā · BaZi · Maiju · Rietumu · Tatva) sapludinātās metrikas.
// Atšķirībā no pārējiem paneļiem (psiholoģiskais trait-modelis), šeit katrs skaitlis ir
// vairāku neatkarīgu sistēmu balsojums. renderSynergyPanel atgriež 4 ATSEVIŠĶAS kartes:
//   KARTE 1 (Pārskats): gatavība + forma + gredzens + 6 joslas + leģenda
//   KARTE 2 (Dziļā analīze): operatīvais arhetips + sinerģijas reizinājumi + sistēmu dominance/konflikti
//   KARTE 3 (🔋 Enerģijas kapacitāte): baterijas modelis + enerģijas tips
//   KARTE 4 (Talanti un rīcība): SAKĻAUJAMA — vienmēr redzams Stratēģiskais portrets; izvērstais = talantu detektors + atkodēšana/lēcas + rīcības plāns
// Tas dod jaunu meta-informāciju:
//   • Sistēmu konsenss — vai sistēmas vienojas (drošs raksturojums) vai nē (atkarīgs no konteksta)
//   • Kombināciju arhetips — 6 skalu sintēze vienā operatīvajā tipā
//   • Galvenie dzinēji — spēcīgākais/vājākais faktors no katras metrikas 'details'
//   • Enerģijas sadalījums (gredzens) + operatīvā gatavība + profila formas tips
//   • Sinerģijas reizinājumi — kā divas asis kopā pastiprina/dzēš viena otru (ģeom. vidējais)
//   • Enerģijas kapacitāte — baterijas modelis: maks. jauda / ilgizturība / atjaunošanās + enerģijas tips
//   • Talantu detektors — jēltalanti + apstiprinātie (konverģence) + slēptie (latentie) talanti
//     → Padziļinātie secinājumi (talent_insights.js): noteikumu dzinējs, 2 lēcas (pašizpēte + HR)
//   • Sistēmu dominance un konflikti — kura no 5 tradīcijām visvairāk nosaka cilvēku un kur tās nesaskan
//   • Rīcības plāns — sintēze 3 ieteikumos: balsties uz / attīsti / apzinies

import { renderTalentInsightsHtml } from './talent_insights.js?v=5';

// ── Sistēmu klasifikators un metadati (modulā — lieto gan kartes, gan getSystemSynthesis) ──
const SYS_COLOR = { 'Vēdiskā': '#8b5cf6', 'BaZi': '#ef4444', 'Maiju': '#10b981', 'Rietumu': '#3b82f6', 'Tatva': '#f59e0b' };
const SYS_ROLE = {
    'Vēdiskā': 'sociālo statusu un likteņa virzienu',
    'BaZi':    'iekšējo kodolu un enerģijas struktūru',
    'Maiju':   'temperamentu un dvēseles uzdevumu',
    'Rietumu': 'emocionālo un attiecību slāni',
    'Tatva':   'ķermeņa stihiju un instinktus',
};
function sysOf(name) {
    const n = (name || '').toLowerCase();
    if (/bazi|dienas valdnieks|iekšējais kodols|perfekcionisma/.test(n)) return 'BaZi';
    if (/maij|lojalitāte|loģiskā struktūra|kognitīvais filtrs|ilgtermiņa aizvainojums|intelekts|briedums/.test(n)) return 'Maiju';
    if (/vēdisk|dzīves periods|iedomība|ego/.test(n)) return 'Vēdiskā';
    if (/tatva|empātija|iekšējā stihija|emocionāls trauslums/.test(n)) return 'Tatva';
    if (/rietum|diplomātija|emocionālā bāze|analīzes paralīze|spirit|darbības dzinulis/.test(n)) return 'Rietumu';
    return null;
}

// Krustsistēmu sintēze 'Personības Kopsavilkuma' identitātes kartēm:
// kur 4 aktīvo metriku apakšskori liecina par tradīciju SASKAŅU (stabilais kodols) vs NESASKAŅU (mainīgās šķautnes) + vadošā tradīcija.
export function getSystemSynthesis(profile) {
    const s = profile.synergy || {};
    const metricDefs = [
        { label: 'līderību',          details: s.leadership?.details  || [] },
        { label: 'stresa noturību',   details: s.stress?.details      || [] },
        { label: 'komandas saderību', details: s.teamwork?.details    || [] },
        { label: 'darba izpildi',     details: s.performance?.details  || [] },
    ];
    const toneOf = (details) => {
        const scores = (details || []).map(d => Number(d.score) || 0);
        if (scores.length < 2) return 'partial';
        const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
        const sd = Math.sqrt(scores.reduce((a, b) => a + (b - mean) ** 2, 0) / scores.length);
        return sd < 1.4 ? 'agree' : sd < 2.6 ? 'partial' : 'diverge';
    };
    const stableCore = metricDefs.filter(m => toneOf(m.details) === 'agree').map(m => m.label);
    const contextDependent = metricDefs.filter(m => toneOf(m.details) === 'diverge').map(m => m.label);
    const buckets = {};
    metricDefs.forEach(m => (m.details || []).forEach(d => {
        const sys = sysOf(d.name);
        if (sys) (buckets[sys] ||= []).push(Number(d.score) || 0);
    }));
    const sysStats = Object.keys(buckets).map(sys => ({
        sys, role: SYS_ROLE[sys],
        pct: Math.round((buckets[sys].reduce((a, b) => a + b, 0) / buckets[sys].length) * 10),
    })).sort((a, b) => b.pct - a.pct);
    return { stableCore, contextDependent, dominant: sysStats[0] || null };
}

function buildSynergyCards(profile) {
    const s = profile.synergy || {};
    const num = v => Math.max(0, Math.min(100, Math.round(Number(v) || 0)));

    // --- Motivācija (atšķirīga forma: 4 komponentes 0–100, nevis details) ---
    const motComp = s.motivation || {};
    const motKeys = ['Intelektuālā', 'Statusa', 'Sociālā', 'Materiālā'];
    const motVals = motKeys.map(k => Number(motComp[k] || 0));
    const motAvg = Math.round((motVals[0] + motVals[1] + motVals[3]) / 3); // intelekts+statuss+materiālais (Sociālā = konstante, izlaista)
    // motDetails IZLAIŽ 'Sociālā' (scoring.js to hardkodē uz 75 — nav reāli mērīts), lai konsenss un dzinēji nemaldina
    const motDetails = ['Intelektuālā', 'Statusa', 'Materiālā'].map(k => ({ name: k, element: '', score: Number(motComp[k] || 0) / 10 }));

    // --- Metriku reģistrs ---
    const metrics = [
        { key: 'leadership',  short: 'Līderība',  label: 'Līderība / Statuss',   color: '#6366f1', pct: num(s.leadership?.pct),  details: s.leadership?.details  || [], invert: false },
        { key: 'stress',      short: 'Noturība',  label: 'Stresa Noturība',      color: '#10b981', pct: num(s.stress?.pct),      details: s.stress?.details      || [], invert: false },
        { key: 'teamwork',    short: 'Komanda',   label: 'Komandas Saderība',    color: '#3b82f6', pct: num(s.teamwork?.pct),    details: s.teamwork?.details    || [], invert: false },
        { key: 'performance', short: 'Izpilde',   label: 'Darba Izpildes Stils', color: '#f59e0b', pct: num(s.performance?.pct), details: s.performance?.details || [], invert: false },
        { key: 'risks',       short: 'Riski',     label: 'Riski / Ēnas',         color: '#ef4444', pct: num(s.risks?.pct),       details: s.risks?.details       || [], invert: true  },
        { key: 'motivation',  short: 'Motivācija',label: 'Motivācija (vidējā)',  color: '#8b5cf6', pct: num(motAvg),             details: motDetails,                  invert: false },
    ];

    // ── A. Sistēmu konsenss (apakšskoru izkliede 0–10 skalā) ──
    const consensus = (m) => {
        const scores = (m.details || []).map(d => Number(d.score) || 0);
        if (scores.length < 2) return null;
        const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
        const sd = Math.sqrt(scores.reduce((a, b) => a + (b - mean) ** 2, 0) / scores.length);
        if (sd < 1.4) return { label: 'Sistēmas vienojas',  tone: 'agree',   bg: '#ecfdf5', col: '#065f46', icon: '✓' };
        if (sd < 2.6) return { label: 'Daļēja saskaņa',     tone: 'partial', bg: '#fffbeb', col: '#92400e', icon: '≈' };
        return                { label: 'Atkarīgs no konteksta', tone: 'diverge', bg: '#eff6ff', col: '#1e40af', icon: '↔' };
    };

    // ── C. Galvenie dzinēji (spēcīgākais / vājākais apakšfaktors) ──
    const drivers = (m) => {
        const dets = (m.details || []).map(d => ({ name: d.name, element: d.element || '', score: Number(d.score) || 0 }));
        if (!dets.length) return null;
        const sorted = [...dets].sort((a, b) => b.score - a.score);
        return { top: sorted[0], bottom: sorted[sorted.length - 1] };
    };

    const L = metrics[0].pct, S = metrics[1].pct, T = metrics[2].pct, P = metrics[3].pct, R = metrics[4].pct, M = metrics[5].pct;

    // ── D. Operatīvā gatavība + profila forma ──
    const readiness = Math.round((L + S + T + P + M + (100 - R)) / 6);
    const assets = [L, S, T, P, M];
    const spread = Math.max(...assets) - Math.min(...assets);
    let shape, shapeDesc;
    if (spread <= 18)      { shape = 'Universāls profils';   shapeDesc = 'Spējas sadalītas līdzsvaroti — daudzpusīgs, bez izteiktas specializācijas.'; }
    else if (spread <= 35) { shape = 'Jaukts profils';       shapeDesc = 'Ir gan stipras puses, gan vājākas zonas — daļēja specializācija.'; }
    else                   { shape = 'Specializēts profils'; shapeDesc = 'Viena vai divas asis dominē pār pārējām — izteikta specializācija.'; }

    const rBadge = readiness >= 70 ? { bg: '#ecfdf5', col: '#065f46', txt: 'Augsta' }
                 : readiness >= 50 ? { bg: '#fffbeb', col: '#92400e', txt: 'Vidēja' }
                 :                    { bg: '#fef2f2', col: '#991b1b', txt: 'Zema' };

    // ── B. Kombināciju arhetips ──
    const archetype = (() => {
        const hi = 65, lo = 45;
        if (L >= hi && P >= hi && S >= hi)
            return { icon: '⚙️', title: 'Pilnvērtīgs operators', text: 'Vada, izpilda un iztur spiedienu vienlaikus. Reta kombinācija — spēj uzņemties pilnu atbildību no idejas līdz rezultātam.' };
        if (L >= hi && R >= 60)
            return { icon: '⚡', title: 'Spēcīgs, bet nepastāvīgs vadonis', text: 'Dabiska autoritāte iet roku rokā ar iekšēju nestabilitāti. Vadīšanas spēks ir reāls, bet ēnas (ego, spiediens) to var sabotēt.' };
        if (R >= 70)
            return { icon: '🌋', title: 'Talantīgs, bet pašsabotējošs', text: 'Spējas ir reālas, taču ļoti augsts ēnu līmenis (aizvainojums, perfekcionisms, ego) regulāri grauj rezultātus. Galvenais izaicinājums ir nevis prasmes, bet darbs ar iekšējiem riskiem.' };
        if (P >= hi && T <= lo)
            return { icon: '🎯', title: 'Vientuļais izpildītājs', text: 'Pabeidz lietas viens un precīzi, bet komandā jūtas neērti. Vislabāk strādā autonomi ar skaidru atbildības lauku.' };
        if (T >= hi && L <= lo)
            return { icon: '🤝', title: 'Komandas balsts', text: 'Stiprs sadarbībā un attiecībās, bet negrib stāvēt priekšgalā. Neaizstājams kā partneris, ne kā vienpersonisks līderis.' };
        if (R >= 60 && S <= lo)
            return { icon: '🌊', title: 'Trausls zem spiediena', text: 'Jutīgs un ievainojams — ātri pārslogojas. Vajadzīga aizsargājoša vide un skaidras robežas, lai ēnas neuzņemtu virsroku.' };
        if (S >= hi && R <= lo)
            return { icon: '🪨', title: 'Noturīgs stabilizators', text: 'Mierīgs un grūti izsitams no līdzsvara. Ne vienmēr spilgtākais, bet drošākais balsts krīzē un haosā.' };
        if (L >= hi && T >= hi)
            return { icon: '🌟', title: 'Iedvesmojošs līderis', text: 'Vada caur attiecībām, ne caur spiedienu. Apvieno autoritāti ar sadarbības spēju — dabisks komandas dzinējs.' };
        if (P >= hi && S >= hi && T <= 55)
            return { icon: '🛠️', title: 'Izturīgs amatnieks', text: 'Pabeidz darbu pat zem slodzes, bet labprātāk klusumā. Uzticams izpildītājs, kas nepadodas pie pirmajām grūtībām.' };
        if (Math.max(...assets) < hi && Math.min(...assets) > lo)
            return { icon: '⚖️', title: 'Līdzsvarots universālis', text: 'Nav vienas dominējošas ass — pielāgojas dažādām lomām. Stiprums ir elastībā, ne specializācijā.' };
        // fallback: nosauc dominējošo asi
        const top = metrics.slice(0, 4).concat(metrics[5]).sort((a, b) => b.pct - a.pct)[0];
        return { icon: '🧭', title: `Dominē: ${top.label}`, text: `Profila stiprākā ass ir “${top.label}” (${top.pct}%). Pārējās metrikas to papildina, bet nedominē — galvenā vērtība slēpjas šajā vienā jomā.` };
    })();

    // ── B. Sinerģijas reizinājumi (ģeometriskais vidējais — "vājākā posma" loģika: ja viena ass vāja, kritīs viss) ──
    const geo = (a, b) => Math.round(Math.sqrt(Math.max(0, a) * Math.max(0, b)));
    const bandGood = v => v >= 60 ? { bg: '#ecfdf5', col: '#065f46', tag: 'Stiprs' } : v >= 40 ? { bg: '#fffbeb', col: '#92400e', tag: 'Mērens' } : { bg: '#fef2f2', col: '#991b1b', tag: 'Vājš' };
    const bandBad  = v => v >= 60 ? { bg: '#fef2f2', col: '#991b1b', tag: 'Augsts' } : v >= 40 ? { bg: '#fffbeb', col: '#92400e', tag: 'Mērens' } : { bg: '#ecfdf5', col: '#065f46', tag: 'Zems' };
    const products = [
        (() => { const v = geo(L, S);       const b = bandGood(v); return { icon: '🛡️', title: 'Vadības ilgtspēja',   formula: 'Līderība × Noturība',           v, b, text: v >= 60 ? 'Spēj noturēt vadošo lomu arī zem ilgstoša spiediena.' : v >= 40 ? 'Vada, bet ilgstoša slodze pakāpeniski nogurdina.' : 'Vadība ātri izsīkst, tiklīdz spiediens kļūst pastāvīgs.' }; })(),
        (() => { const v = geo(P, 100 - R); const b = bandGood(v); return { icon: '✅', title: 'Izpildes uzticamība',  formula: 'Izpilde × (100 − Riski)',       v, b, text: v >= 60 ? 'Uzsākto pabeidz droši — ēnas rezultātu neapdraud.' : v >= 40 ? 'Parasti pabeidz, bet riski reizēm izjauc rezultātu.' : 'Spēcīga izpilde, taču augstie riski to bieži sabotē.' }; })(),
        (() => { const v = geo(T, 100 - R); const b = bandGood(v); return { icon: '🤝', title: 'Sadarbības drošums',    formula: 'Komanda × (100 − Riski)',       v, b, text: v >= 60 ? 'Stabils, paredzams partneris ilgtermiņā.' : v >= 40 ? 'Labs komandā, bet ēnas padara saderību mainīgu.' : 'Sadarbības potenciāls ir, bet riski to padara nestabilu.' }; })(),
        (() => { const v = geo(M, 100 - S); const b = bandBad(v);  return { icon: '🔥', title: 'Izdegšanas risks',      formula: 'Motivācija × (100 − Noturība)', v, b, text: v >= 60 ? 'Liela dzinme un mērena izturība — augsts pārkaršanas risks.' : v >= 40 ? 'Mērens izdegšanas risks pie ilgstošas slodzes.' : 'Dzinme un izturība līdzsvarā — izdegšana maz ticama.' }; })(),
    ];

    // ── Enerģijas kapacitāte (baterijas modelis) ──
    const peakPower = geo(M, P);            // maksimālā jauda: dzinme × izpilde (īslaicīgais pīķis)
    const endurance = geo(S, 100 - R);      // ilgizturība: tvertne × (100 − noplūde)
    const recovery  = S;                    // atjaunošanās: stresa noturība (cik ātri uzlādējas)
    const capacity  = Math.round((peakPower + endurance * 2 + recovery) / 4); // ilgizturība sver vairāk
    const capColor  = capacity >= 60 ? '#16a34a' : capacity >= 40 ? '#f59e0b' : '#ef4444';
    const capBadge  = capacity >= 60 ? 'Liela rezerve' : capacity >= 40 ? 'Mērena rezerve' : 'Maza rezerve';
    const battType = (() => {
        if (peakPower >= 60 && endurance >= 60) return { icon: '🚀', t: 'Augstas kapacitātes dzinējs', d: 'Gan spēcīgi rāvieni, gan izturība ilgtermiņā — reta kombinācija ar lielu kopējo jaudu.' };
        if (peakPower - endurance >= 25)        return { icon: '⚡', t: 'Sprinteris', d: 'Spēj īslaicīgi sniegt izcilus rezultātus, bet ātri izsīkst. Vislabāk strādā pēkšņos rāvienos ar atpūtas pauzēm starp tiem.' };
        if (endurance - peakPower >= 25)        return { icon: '🐢', t: 'Maratonists', d: 'Stabila, vienmērīga enerģija ilgtermiņā bez spilgtiem rāvieniem. Stiprums ir noturībā, ne pīķos.' };
        if (peakPower < 40 && endurance < 40)   return { icon: '🪫', t: 'Zema rezerve', d: 'Enerģijas tvertne ir maza — svarīgi taupīt spēkus, izvairīties no pārslodzes un veidot biežas atjaunošanās pauzes.' };
        return { icon: '🔋', t: 'Līdzsvarots ritms', d: 'Vienmērīga, paredzama enerģija — ne sprinteris, ne maratonists, bet uzticams vidējais temps.' };
    })();
    const battSvg = (() => {
        const bw = 150, bh = 64, term = 8, pad = 6;
        const innerW = bw - pad * 2;
        const fillW = Math.max(2, innerW * capacity / 100);
        return `
        <svg viewBox="0 0 ${bw + term + 4} ${bh}" style="width:100%; max-width:185px; height:auto;">
            <rect x="1" y="6" width="${bw}" height="${bh - 12}" rx="8" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2"/>
            <rect x="${bw + 1}" y="${bh / 2 - 9}" width="${term}" height="18" rx="2" fill="#cbd5e1"/>
            <rect x="${1 + pad}" y="${6 + pad}" width="${fillW.toFixed(1)}" height="${bh - 12 - pad * 2}" rx="5" fill="${capColor}"/>
            <text x="${1 + bw / 2}" y="${bh / 2 + 6}" text-anchor="middle" font-size="20" font-weight="900" fill="#1e293b">${capacity}%</text>
        </svg>`;
    })();
    const capBar = (label, val, hint) => `
        <div style="margin-bottom:8px;">
            <div style="display:flex; justify-content:space-between; font-size:0.76rem; margin-bottom:2px;">
                <span style="color:#334155; font-weight:600;">${label}</span>
                <span style="font-weight:800; color:#0f766e;">${val}%</span>
            </div>
            <div style="background:#e2e8f0; border-radius:5px; height:6px; overflow:hidden;">
                <div style="width:${val}%; height:100%; background:#14b8a6; border-radius:5px;"></div>
            </div>
            <div style="font-size:0.64rem; color:#94a3b8; margin-top:2px;">${hint}</div>
        </div>`;

    // ── D. Sistēmu dominance un konflikti (SYS_COLOR/SYS_ROLE/sysOf definēti modulā augšā) ──
    // Tikai aktīvās metrikas (riskiem negatīva valence, motivācijai nav sistēmu apakšskoru)
    const assetMetrics = metrics.filter(m => ['leadership', 'stress', 'teamwork', 'performance'].includes(m.key));
    const sysBuckets = {};
    assetMetrics.forEach(m => (m.details || []).forEach(d => {
        const sys = sysOf(d.name);
        if (!sys) return;
        (sysBuckets[sys] ||= []).push({ metric: m.short, score: Number(d.score) || 0 });
    }));
    const sysStats = Object.keys(sysBuckets).map(sys => {
        const arr = sysBuckets[sys];
        const avg = arr.reduce((a, x) => a + x.score, 0) / arr.length;
        const sorted = [...arr].sort((a, b) => b.score - a.score);
        return { sys, color: SYS_COLOR[sys], pct: Math.round(avg * 10), hi: sorted[0], lo: sorted[sorted.length - 1], spread: sorted[0].score - sorted[sorted.length - 1].score };
    }).sort((a, b) => b.pct - a.pct);
    const domTop = sysStats[0];
    const conflict = [...sysStats].sort((a, b) => b.spread - a.spread)[0];

    const sysBars = sysStats.map(st => `
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
            <span style="width:60px; font-size:0.76rem; color:#334155; font-weight:600;">${st.sys}</span>
            <div style="flex:1; background:#e2e8f0; border-radius:5px; height:7px; overflow:hidden;">
                <div style="width:${st.pct}%; height:100%; background:${st.color}; border-radius:5px;"></div>
            </div>
            <span style="width:34px; text-align:right; font-size:0.76rem; font-weight:800; color:${st.color};">${st.pct}%</span>
        </div>`).join('');
    const conflictHtml = (conflict && conflict.spread >= 3)
        ? `<b style="color:#b45309;">${conflict.sys}</b> dod pretrunīgu signālu: spēcīgs <b>${conflict.hi.metric}</b> (${Math.round(conflict.hi.score * 10)}%) jomā, bet vājš <b>${conflict.lo.metric}</b> (${Math.round(conflict.lo.score * 10)}%) jomā.`
        : `Sistēmas savā starpā lielu pretrunu nerāda — signāli ir samērā saskaņoti.`;

    // ── 💎 Talantu detektors (konverģence apakšskoros) ──
    const allTalentDetails = [];
    assetMetrics.forEach(m => (m.details || []).forEach(d => {
        allTalentDetails.push({ metric: m.short, name: d.name, element: d.element || '', score: Number(d.score) || 0, sys: sysOf(d.name) });
    }));
    // 1) Jēltalanti — spēcīgākie atsevišķie apakšskori (≥7/10)
    const rawTalents = [...allTalentDetails].sort((a, b) => b.score - a.score).filter(d => d.score >= 7).slice(0, 3);
    // 2) Apstiprinātie — metrika augsta (≥60%) UN ≥2 ATŠĶIRĪGAS sistēmas to apstiprina (≥7).
    //    Skaitām atšķirīgas sistēmas, ne apakšskorus — citādi viena sistēma ar vairākām detaļām
    //    (piem. Maiji Temp + Maiji Dvēsele + Briedums) maldīgi izskatītos pēc "neatkarīgas validācijas".
    const confirmedTalents = assetMetrics
        .map(m => {
            const sysSet = new Set((m.details || []).filter(d => (Number(d.score) || 0) >= 7).map(d => sysOf(d.name)).filter(Boolean));
            return { key: m.key, label: m.label, pct: m.pct, strong: sysSet.size };
        })
        .filter(t => t.pct >= 60 && t.strong >= 2)
        .sort((a, b) => b.pct - a.pct);
    // 3) Slēptie — divu līmeņu detektors (hibrīdais AND-filtrs):
    //    💎 Neatklāts spēks:     pīķis ≥85 UN kopējais <50 UN delta ≥40
    //    📈 Bremzēts potenciāls: pīķis ≥65 UN delta ≥35 (un nav 1. līmenis)
    const joinLv = arr => arr.length <= 1 ? (arr[0] || '') : arr.slice(0, -1).join(', ') + ' un ' + arr[arr.length - 1];
    const potentials = assetMetrics.map(m => {
        const top = [...(m.details || [])].sort((a, b) => (Number(b.score) || 0) - (Number(a.score) || 0))[0];
        if (!top) return null;
        const peak = Math.round((Number(top.score) || 0) * 10);   // sistēmas pīķis 0–100
        const delta = peak - m.pct;
        let tier = null;
        if (peak >= 85 && m.pct < 50 && delta >= 40) tier = 'diamond';
        else if (peak >= 65 && delta >= 35 && m.pct < 55) tier = 'growth';
        return { key: m.key, label: m.label, pct: m.pct, peak, delta, topSys: sysOf(top.name), tier };
    }).filter(Boolean);
    const latentTalents = potentials.filter(t => t.tier).sort((a, b) => b.delta - a.delta);
    const diamonds = latentTalents.filter(t => t.tier === 'diamond');
    // "Neapstrādāts dimants": 0 apstiprinātu talantu + ≥2 neatklāta spēka talanti
    const isDiamondProfile = confirmedTalents.length === 0 && diamonds.length >= 2;
    const avgDiamondDelta = diamonds.length ? Math.round(diamonds.reduce((a, t) => a + t.delta, 0) / diamonds.length) : 0;

    // Secinājumu dzinējs (noteikumu bāzēts naratīvs — pašizpēte + HR/vadība). Atgriež {portraitHtml, restHtml}.
    const talentInsights = renderTalentInsightsHtml({
        confirmed: confirmedTalents,
        latent: latentTalents,
        diamonds,
        isDiamondProfile,
        raw: rawTalents,
        metricPct: Object.fromEntries(metrics.map(m => [m.key, m.pct])),
    });

    // Profila izlecošais bloks "Neapstrādāts dimants"
    const diamondCalloutHtml = isDiamondProfile ? `
        <div style="background:linear-gradient(135deg,#312e81,#4f46e5); border-radius:12px; padding:14px 16px; margin-bottom:1.1rem;">
            <div style="display:flex; align-items:center; gap:11px;">
                <span style="font-size:1.9rem;">💎</span>
                <div>
                    <div style="font-size:0.64rem; color:#c7d2fe; text-transform:uppercase; letter-spacing:1.8px; font-weight:800;">Neapstrādāts dimants · reti sastopams profils</div>
                    <div style="font-size:1.12rem; font-weight:800; color:#fff;">Masīvs neizmantots potenciāls</div>
                </div>
            </div>
            <div style="font-size:0.84rem; line-height:1.55; color:#dbeafe; margin-top:7px;">
                Šim profilam neviena spēja nav apstiprināta vairākās sistēmās vienlaikus, taču <b>${diamonds.length}</b> jomās slēpjas spēcīgs, vēl neizmantots potenciāls — vidēji netiek izmantoti ap <b>${avgDiamondDelta}%</b> no dabiskās kapacitātes. Šis cilvēks, visticamāk, dzīvo manāmi zem savām iespējām: ne spēju trūkuma dēļ, bet tāpēc, ka tās vēl nav atradušas piemērotu vidi. Maz piemērots lineāriem, rutīnas uzdevumiem — vislielāko atdevi dos autonomija, īsts izaicinājums un atbalstošs mentors, ne stingrs priekšnieks.
            </div>
        </div>` : '';

    const rawHtml = rawTalents.length
        ? rawTalents.map(d => `
            <div style="border:1px solid #e9d5ff; background:#faf5ff; border-radius:9px; padding:8px 11px; flex:1 1 180px; min-width:170px;">
                <div style="font-size:0.7rem; color:#7c3aed; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">${d.metric}</div>
                <div style="font-size:0.84rem; color:#334155; font-weight:600; margin:1px 0;">${d.name}${d.element ? ` · ${d.element}` : ''}</div>
                <div style="font-size:0.72rem; color:#94a3b8;">${d.sys || '—'} · <b style="color:#7c3aed;">${Math.round(d.score * 10)}%</b></div>
            </div>`).join('')
        : `<div style="font-size:0.78rem; color:#94a3b8;">Nav izteiktu atsevišķu jēltalantu — spējas sadalītas vienmērīgi.</div>`;
    const confirmedHtml = confirmedTalents.length
        ? confirmedTalents.map(t => `
            <div style="display:flex; align-items:flex-start; gap:7px; margin-bottom:6px;">
                <span style="font-size:0.9rem; line-height:1.3;">✅</span>
                <span style="flex:1; font-size:0.8rem; color:#334155; line-height:1.4;"><b>${t.label}</b> — ${t.strong} sistēmas neatkarīgi apstiprina (${t.pct}%)</span>
            </div>`).join('')
        : `<div style="font-size:0.76rem; color:#64748b; line-height:1.45;">Neviena spēja nav vienlaikus gan augsta, gan apstiprināta vairākās sistēmās — talanti ir, bet vēl nav "noslīpēti" līdz pārliecinošam līmenim.</div>`;
    const TIER = {
        diamond: { badge: '💎 Neatklāts spēks',     bg: '#eef2ff', col: '#4f46e5', bar: '#6366f1' },
        growth:  { badge: '📈 Bremzēts potenciāls', bg: '#f1f5f9', col: '#475569', bar: '#3b82f6' },
    };
    const latentHtml = latentTalents.length
        ? latentTalents.map(t => {
            const tier = TIER[t.tier] || TIER.growth;
            return `
            <div style="margin-bottom:11px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:6px; margin-bottom:4px;">
                    <span style="font-size:0.8rem; color:#334155; font-weight:600;">${t.label}</span>
                    <span style="background:${tier.bg}; color:${tier.col}; font-size:0.65rem; font-weight:800; padding:2px 8px; border-radius:10px; white-space:nowrap;">${tier.badge}</span>
                </div>
                <div style="position:relative; height:10px; background:#e2e8f0; border-radius:5px; overflow:hidden; margin-bottom:3px;" title="Pelēks = ikdienas izpausme; krāsa = slēptais potenciāls">
                    <div style="position:absolute; left:0; top:0; height:100%; width:${t.peak}%; background:${tier.bar}; opacity:0.4;"></div>
                    <div style="position:absolute; left:0; top:0; height:100%; width:${t.pct}%; background:#94a3b8;"></div>
                </div>
                <div style="font-size:0.68rem; color:#94a3b8;">Ikdiena <b style="color:#64748b;">${t.pct}%</b> · Potenciāls <b style="color:${tier.col};">${t.peak}%</b> (${t.topSys || 'sistēma'}) · neizmantoti <b style="color:${tier.col};">${t.delta}%</b></div>
            </div>`;
        }).join('')
        : `<div style="font-size:0.76rem; color:#64748b; line-height:1.45;">Nav slēptu talantu — kas ir attīstīts, tas jau izpaužas redzamajos rādītājos.</div>`;

    // ── 🎯 Rīcības plāns (sintēze: balsties / attīsti / apzinies) ──
    const paceAdvice = {
        'Sprinteris': 'plāno darbu īsos intensīvos rāvienos ar reālām atpūtas pauzēm starp tiem',
        'Maratonists': 'tavs spēks ir vienmērīgā tempā — izvairies no pēkšņiem pārslodzes pīķiem',
        'Augstas kapacitātes dzinējs': 'rezerve ir liela, bet ne bezgalīga — arī spēcīga baterija jāuzlādē',
        'Zema rezerve': 'taupi enerģiju apzināti un veido biežas atjaunošanās pauzes',
        'Līdzsvarots ritms': 'uzturi vienmērīgu tempu — tava priekšrocība ir paredzamība, ne pīķi',
    };
    const riskMetric = metrics.find(m => m.key === 'risks');
    const riskTop = [...((riskMetric && riskMetric.details) || [])].sort((a, b) => (Number(b.score) || 0) - (Number(a.score) || 0))[0];
    const weakest = [...assetMetrics].sort((a, b) => a.pct - b.pct)[0];

    const planBuild = confirmedTalents.length
        ? `Tava drošā bāze ir <b>${joinLv(confirmedTalents.map(t => t.label))}</b> — vairākas sistēmas to apstiprina, tāpēc liec sevi lomās, kas uz to balstās jau tagad.`
        : (rawTalents.length
            ? `Tavi spēcīgākie signāli ir <b>${[...new Set(rawTalents.map(d => d.metric))].join('</b>, <b>')}</b> — sāc no tiem, kaut tie vēl nav līdz galam apstiprināti.`
            : `Spējas ir līdzsvarotas bez vienas dominējošas — izvēlies virzienu pēc intereses, ne pēc šaurās stiprās puses.`);
    const planDevelop = latentTalents.length
        ? `Lielākais neizmantotais potenciāls: <b>${joinLv(latentTalents.map(t => t.label))}</b> — dotība jau ir, tikai nav izpausta, tāpēc mācības vai prakse šeit dos vislielāko atdevi.`
        : `Vājākā ass ir <b>${weakest.label}</b> (${weakest.pct}%) — tās mērķtiecīga stiprināšana visvairāk paceltu kopējo sniegumu.`;
    const planAware = `Galvenā ēna, kas var sabotēt rezultātus: <b>${riskTop ? riskTop.name : 'iekšējie riski'}</b>${riskTop && riskTop.element ? ` (${riskTop.element})` : ''}. Enerģijas ziņā tu esi <b>${battType.t}</b> — ${paceAdvice[battType.t] || 'pielāgo tempu savai kapacitātei'}.`;

    const planRows = [
        { icon: '🟢', verb: 'Balsties uz', col: '#16a34a', bg: '#f0fdf4', text: planBuild },
        { icon: '🟣', verb: 'Attīsti',     col: '#7c3aed', bg: '#faf5ff', text: planDevelop },
        { icon: '🟠', verb: 'Apzinies',    col: '#d97706', bg: '#fffbeb', text: planAware },
    ];

    // ───────────────────────── ENERĢIJAS SADALĪJUMS — gredzens ─────────────────────────
    // Atšķirībā no '🎯 Spēju spektrs' radara (kas rāda magnitūdu pa asīm), gredzens rāda
    // PROPORCIJAS — cik liela daļa no kopējās enerģijas pieder katrai dimensijai (KUR koncentrējas).
    const totalPct = metrics.reduce((a, m) => a + m.pct, 0) || 1;
    const slices = metrics
        .map(m => ({ short: m.short, label: m.label, color: m.color, pct: m.pct, share: m.pct / totalPct }))
        .sort((a, b) => b.share - a.share);

    const Dcx = 80, Dcy = 80, Dr = 56, Dw = 24, GAP = 1.5;
    const Dcirc = 2 * Math.PI * Dr;
    let cum = 0;
    const arcs = slices.map(sl => {
        const startAng = -90 + cum * 360;
        const dash = Math.max(sl.share * Dcirc - GAP, 0.4);
        cum += sl.share;
        return `<circle cx="${Dcx}" cy="${Dcy}" r="${Dr}" fill="none" stroke="${sl.color}" stroke-width="${Dw}"
            stroke-dasharray="${dash.toFixed(2)} ${Dcirc.toFixed(2)}" transform="rotate(${startAng.toFixed(2)} ${Dcx} ${Dcy})"/>`;
    }).join('');
    const topSlice = slices[0];
    const donutSvg = `
        <svg viewBox="0 0 160 160" style="width:100%; max-width:190px; height:auto;">
            <circle cx="${Dcx}" cy="${Dcy}" r="${Dr}" fill="none" stroke="#f1f5f9" stroke-width="${Dw}"/>
            ${arcs}
            <text x="${Dcx}" y="${Dcy - 6}" text-anchor="middle" font-size="8.5" fill="#94a3b8" font-weight="600">Dominē</text>
            <text x="${Dcx}" y="${Dcy + 8}" text-anchor="middle" font-size="15" fill="#1e293b" font-weight="800">${Math.round(topSlice.share * 100)}%</text>
            <text x="${Dcx}" y="${Dcy + 20}" text-anchor="middle" font-size="8" fill="${topSlice.color}" font-weight="700">${topSlice.short}</text>
        </svg>`;
    const donutLegend = slices.map(sl => `
        <div style="display:flex; align-items:center; gap:7px; font-size:0.74rem; margin-bottom:5px;">
            <span style="width:10px; height:10px; border-radius:2px; background:${sl.color}; flex:none;"></span>
            <span style="color:#334155; flex:1;">${sl.label}</span>
            <span style="font-weight:800; color:${sl.color};">${Math.round(sl.share * 100)}%</span>
            <span style="color:#94a3b8; font-size:0.66rem; width:42px; text-align:right;">(${sl.pct}%)</span>
        </div>`).join('');

    // ───────────────────────── JOSLAS (A+C) ─────────────────────────
    const bar = (m) => {
        const cons = consensus(m);
        const drv = drivers(m);
        const consChip = cons
            ? `<span style="background:${cons.bg}; color:${cons.col}; font-size:0.68rem; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap;">${cons.icon} ${cons.label}</span>`
            : '';
        let driverLine = '';
        if (drv) {
            if (m.invert) {
                driverLine = `<span style="color:#b91c1c;">⚠ Galvenā ēna: <b>${drv.top.name}</b>${drv.top.element ? ` · ${drv.top.element}` : ''}</span>`;
            } else {
                const elTop = drv.top.element ? ` · ${drv.top.element}` : '';
                driverLine = `<span style="color:#16a34a;">⬆ <b>${drv.top.name}</b>${elTop}</span> <span style="color:#94a3b8;">|</span> <span style="color:#94a3b8;">⬇ ${drv.bottom.name}</span>`;
            }
        }
        return `
            <div style="margin-bottom:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:3px;">
                    <span style="font-size:0.85rem; color:#334155; font-weight:600;">${m.label}</span>
                    <span style="font-size:0.85rem; font-weight:800; color:${m.color};">${m.pct}%</span>
                </div>
                <div style="background:#e2e8f0; border-radius:6px; height:8px; overflow:hidden; margin-bottom:5px;">
                    <div style="width:${m.pct}%; height:100%; background:${m.color}; border-radius:6px;"></div>
                </div>
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; font-size:0.72rem;">
                    ${consChip}
                    <span style="color:#64748b;">${driverLine}</span>
                </div>
            </div>`;
    };

    const CARD = 'background:white; border-radius:14px; padding:1.5rem 1.6rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem;';
    const cardTitle = (t, sub) => `<h3 style="font-size:1.05rem; color:#1e293b; margin:0 0 0.2rem 0;">${t}</h3><div style="font-size:0.78rem; color:#94a3b8; margin-bottom:1rem;">${sub}</div>`;

    const overviewCard = `
        <!-- ════ KARTE 1: PĀRSKATS (kas tu esi) ════ -->
        <div style="${CARD}">
            <!-- Galvene -->
            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:0.4rem;">
                <div>
                    <h3 style="font-size:1.05rem; color:#1e293b; margin:0 0 2px 0;">Sinerģijas Metrika · Pārskats</h3>
                    <div style="font-size:0.78rem; color:#94a3b8;">5 sistēmu (Vēdiskā · BaZi · Maiju · Rietumu · Tatva) sapludinātais balsojums</div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <div style="text-align:right;">
                        <div style="font-size:0.65rem; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; font-weight:700;">Operatīvā gatavība</div>
                        <div style="font-size:1.4rem; font-weight:900; color:#1e293b; line-height:1;">${readiness}%</div>
                    </div>
                    <span style="background:${rBadge.bg}; color:${rBadge.col}; font-size:0.72rem; font-weight:700; padding:4px 10px; border-radius:12px;">${rBadge.txt}</span>
                </div>
            </div>

            <!-- Forma -->
            <div style="background:#f8fafc; border-radius:8px; padding:8px 12px; margin:0.8rem 0 1.2rem; font-size:0.8rem; color:#475569;">
                <b style="color:#6366f1;">${shape}.</b> ${shapeDesc}
            </div>

            <!-- Enerģijas sadalījums (gredzens) + joslas -->
            <div style="display:flex; flex-wrap:wrap; gap:1.5rem;">
                <div style="flex:1 1 260px; min-width:240px; display:flex; flex-direction:column; align-items:center;">
                    ${donutSvg}
                    <div style="width:100%; max-width:250px; margin-top:12px;">
                        ${donutLegend}
                    </div>
                    <div style="font-size:0.68rem; color:#94a3b8; text-align:center; margin-top:8px; line-height:1.45;">
                        Cik liela daļa no kopējās enerģijas pieder katrai dimensijai — <b>kur</b> koncentrējas šis cilvēks. <b>Riski / Ēnas</b> redzami kā patstāvīga šķēle: jo lielāka, jo vairāk profila enerģijas aizņem ēnas.
                    </div>
                </div>
                <div style="flex:2 1 380px; min-width:320px;">
                    ${metrics.map(bar).join('')}
                </div>
            </div>

            <!-- Leģenda -->
            <div style="margin-top:0.9rem; font-size:0.7rem; color:#94a3b8; line-height:1.5;">
                <b>Konsenss</b> rāda, vai 5 sistēmas vienojas par šo iezīmi: <span style="color:#065f46;">✓ vienojas</span> = drošs, stabils raksturojums; <span style="color:#1e40af;">↔ atkarīgs no konteksta</span> = parādās tikai dažās dzīves situācijās. <b>⬆ / ⬇</b> rāda spēcīgāko un vājāko sistēmu aiz katra skaitļa.
            </div>
        </div>`;

    const deepCard = `
        <!-- ════ KARTE 2: DZIĻĀ ANALĪZE (arhetips · reizinājumi · sistēmas) ════ -->
        <div style="${CARD}">
            ${cardTitle('Dziļā analīze', '— ko skaitļi nozīmē, kad tos saliek kopā')}

            <!-- Operatīvais arhetips -->
            <div style="background:linear-gradient(135deg, #312e81 0%, #1e293b 100%); border-radius:12px; padding:1.2rem 1.4rem; color:#e0e7ff;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:0.5rem;">
                    <span style="font-size:1.6rem;">${archetype.icon}</span>
                    <div>
                        <div style="font-size:0.65rem; color:#a5b4fc; text-transform:uppercase; letter-spacing:1.5px; font-weight:700;">Operatīvais arhetips</div>
                        <div style="font-size:1.15rem; font-weight:800; color:#fff;">${archetype.title}</div>
                    </div>
                </div>
                <div style="font-size:0.88rem; line-height:1.6; color:#c7d2fe;">${archetype.text}</div>
            </div>

            <!-- Sinerģijas reizinājumi -->
            <div style="margin-top:1.3rem;">
                <div style="font-size:0.78rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:0.6rem;">Sinerģijas reizinājumi <span style="font-weight:500; text-transform:none; color:#94a3b8;">— kā divas asis kopā pastiprina vai dzēš viena otru</span></div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(245px, 1fr)); gap:10px;">
                    ${products.map(p => `
                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px;">
                            <div style="display:flex; justify-content:space-between; align-items:baseline; gap:8px;">
                                <span style="font-size:0.85rem; font-weight:700; color:#334155;">${p.icon} ${p.title}</span>
                                <span style="background:${p.b.bg}; color:${p.b.col}; font-size:0.7rem; font-weight:800; padding:2px 8px; border-radius:10px; white-space:nowrap;">${p.v}% · ${p.b.tag}</span>
                            </div>
                            <div style="font-size:0.66rem; color:#94a3b8; margin:2px 0 5px;">${p.formula}</div>
                            <div style="font-size:0.76rem; color:#475569; line-height:1.45;">${p.text}</div>
                        </div>`).join('')}
                </div>
            </div>

            <!-- Sistēmu dominance un konflikti -->
            <div style="margin-top:1.3rem; display:flex; flex-wrap:wrap; gap:1.3rem;">
                <div style="flex:1 1 280px; min-width:260px;">
                    <div style="font-size:0.78rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:0.6rem;">Vadošās sistēmas</div>
                    ${sysBars}
                    ${domTop ? `<div style="font-size:0.74rem; color:#475569; line-height:1.5; margin-top:6px;">Šo cilvēku visvairāk nosaka <b style="color:${domTop.color};">${domTop.sys}</b> (${domTop.pct}%) — tā veido <b>${SYS_ROLE[domTop.sys] || 'pamatraksturu'}</b>.</div>` : ''}
                </div>
                <div style="flex:1 1 280px; min-width:260px;">
                    <div style="font-size:0.78rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:0.6rem;">Sistēmu konflikts</div>
                    <div style="background:#fffbeb; border-left:3px solid #f59e0b; border-radius:6px; padding:9px 12px; font-size:0.78rem; color:#475569; line-height:1.5;">${conflictHtml}</div>
                    <div style="font-size:0.7rem; color:#94a3b8; margin-top:6px; line-height:1.45;">Kad viena sistēma vienā jomā redz spēku, bet citā vājumu, šī iezīme dzīvē izpaužas nevienmērīgi — atkarībā no situācijas.</div>
                </div>
            </div>
        </div>`;

    const energyCard = `
        <!-- ════ KARTE 3: 🔋 ENERĢIJAS KAPACITĀTE ════ -->
        <div style="${CARD}">
            ${cardTitle('🔋 Enerģijas kapacitāte', '— cik daudz un cik ilgi cilvēks spēj dot')}
            <div style="display:flex; flex-wrap:wrap; gap:1.3rem; align-items:stretch;">
                <!-- Kreisā: baterija + joslas -->
                <div style="flex:2 1 360px; min-width:320px; display:flex; flex-wrap:wrap; gap:1.3rem; align-items:center;">
                    <div style="flex:1 1 150px; min-width:150px; display:flex; flex-direction:column; align-items:center; gap:7px;">
                        ${battSvg}
                        <span style="background:${capColor}1a; color:${capColor}; font-size:0.72rem; font-weight:800; padding:3px 10px; border-radius:11px;">${capBadge}</span>
                    </div>
                    <div style="flex:2 1 220px; min-width:210px;">
                        ${capBar('Maksimālā jauda', peakPower, 'Motivācija × Izpilde — īslaicīgais pīķis')}
                        ${capBar('Ilgizturība', endurance, 'Noturība × (100−Riski) — cik ilgi notur tempu')}
                        ${capBar('Atjaunošanās', recovery, 'Stresa noturība — cik ātri uzlādējas')}
                    </div>
                </div>
                <!-- Labā: Enerģijas tips -->
                <div style="flex:1 1 260px; min-width:240px; background:linear-gradient(135deg,#042f2e,#134e4a); border-radius:10px; padding:13px 15px; display:flex; flex-direction:column; justify-content:center; gap:6px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.6rem;">${battType.icon}</span>
                        <div>
                            <div style="font-size:0.68rem; color:#5eead4; text-transform:uppercase; letter-spacing:1px; font-weight:700;">Enerģijas tips</div>
                            <div style="font-size:0.98rem; font-weight:800; color:#fff;">${battType.t}</div>
                        </div>
                    </div>
                    <div style="font-size:0.8rem; line-height:1.45; color:#99f6e4;">${battType.d}</div>
                </div>
            </div>
        </div>`;

    const talentsCard = `
        <!-- ════ KARTE 4: TALANTI UN RĪCĪBA (ko darīt) ════ -->
        <div style="${CARD}">
            ${cardTitle('Talanti un rīcība', '— tavi dabiskie spēki un ko ar tiem darīt')}

            <!-- VIENMĒR REDZAMS: Stratēģiskais portrets -->
            ${talentInsights.portraitHtml}

            <!-- Sakļaušanas poga -->
            <button onclick="var x=document.getElementById('talanti-extra'),o=x.style.display==='none';x.style.display=o?'':'none';this.innerHTML=o?'▲ Sakļaut':'▼ Rādīt visus talantus un rīcības plānu';" style="margin-top:1.1rem; width:100%; background:#f1f5f9; border:1px solid #e2e8f0; color:#475569; font-size:0.78rem; font-weight:700; padding:9px; border-radius:8px; cursor:pointer;">▼ Rādīt visus talantus un rīcības plānu</button>

            <!-- IZVĒRSTAIS (noklusējumā sakļauts) -->
            <div id="talanti-extra" style="display:none;">
                <!-- 💎 Talantu detektors -->
                <div style="margin-top:1.1rem;">
                    <div style="font-size:0.78rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:0.6rem;">💎 Talantu detektors <span style="font-weight:500; text-transform:none; color:#94a3b8;">— kur vairākas sistēmas saskan par dabisku dotību</span></div>
                    ${diamondCalloutHtml}
                    <div style="font-size:0.68rem; color:#94a3b8; font-weight:700; margin-bottom:5px; letter-spacing:0.3px;">JĒLTALANTI · spēcīgākie atsevišķie signāli</div>
                    <div style="display:flex; flex-wrap:wrap; gap:9px; margin-bottom:1rem;">${rawHtml}</div>
                    <div style="display:flex; flex-wrap:wrap; gap:1.3rem;">
                        <div style="flex:1 1 280px; min-width:260px; background:#f0fdf4; border-radius:9px; padding:11px 13px;">
                            <div style="font-size:0.72rem; font-weight:800; color:#166534; margin-bottom:7px; letter-spacing:0.3px;">APSTIPRINĀTIE TALANTI</div>
                            ${confirmedHtml}
                        </div>
                        <div style="flex:1 1 280px; min-width:260px; background:#faf5ff; border-radius:9px; padding:11px 13px;">
                            <div style="font-size:0.72rem; font-weight:800; color:#7c3aed; margin-bottom:7px; letter-spacing:0.3px;">SLĒPTIE TALANTI</div>
                            ${latentHtml}
                        </div>
                    </div>
                </div>

                <!-- 🔍 Padziļinātie secinājumi (atkodēšana + lēcas) -->
                <div style="margin-top:1.3rem; border-top:1px dashed #e2e8f0; padding-top:1rem;">
                    ${talentInsights.restHtml}
                </div>

                <!-- 🎯 Rīcības plāns -->
                <div style="margin-top:1.3rem;">
                    <div style="font-size:0.78rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:0.6rem;">🎯 Rīcības plāns <span style="font-weight:500; text-transform:none; color:#94a3b8;">— ko ar šo informāciju darīt praktiski</span></div>
                    ${planRows.map(r => `
                        <div style="display:flex; gap:11px; background:${r.bg}; border-left:3px solid ${r.col}; border-radius:8px; padding:10px 13px; margin-bottom:8px;">
                            <span style="font-size:1.05rem; line-height:1.3;">${r.icon}</span>
                            <div style="flex:1;">
                                <div style="font-size:0.74rem; font-weight:800; color:${r.col}; text-transform:uppercase; letter-spacing:0.5px;">${r.verb}</div>
                                <div style="font-size:0.82rem; color:#334155; line-height:1.5; margin-top:2px;">${r.text}</div>
                            </div>
                        </div>`).join('')}
                </div>
            </div>
        </div>`;

    return { overviewCard, deepCard, energyCard, talentsCard };
}

// Kopsavilkuma cilnē: Pārskats + Dziļā analīze
export function renderSynergyPanel(profile) {
    const c = buildSynergyCards(profile);
    return c.overviewCard + c.deepCard;
}

// Profila cilnē (zem Darba Stila Pozīcijas): 🔋 Enerģijas kapacitāte + Talanti un rīcība
export function renderEnergyTalentsCards(profile) {
    const c = buildSynergyCards(profile);
    return c.energyCard + c.talentsCard;
}
