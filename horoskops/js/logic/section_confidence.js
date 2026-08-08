// ── SADAĻAS TICAMĪBAS (CONFIDENCE) DZINĒJS ───────────────────────────────────
// Aprēķina, cik uzticams ir KONKRĒTĀS sadaļas rezultāts: 0% = nav uzticams,
// 100% = uzticams. Caurspīdīgs — dekomponējas faktoros ar "kā paaugstināt".
//
// Pilns kompozīts no trim godīgi aprēķināmiem komponentiem (iekļauj tikai tos,
// kas konkrētajai sadaļai piemērojami, lai skaitlis paliek jēgpilns):
//   D — Nosakāmība: cik labi ieejas (gl. dzimšanas laiks) nosaka rezultātu.
//        No profile.timeSweep sadalījumu koncentrācijas (top-varbūtība).
//   C — Konverģence: vai vairākas neatkarīgas sistēmas saskan (kad pieejams).
//   K — Pilnīgums: vai vajadzīgās ieejas vispār ir norādītas.

// Svari (konfigurējami "parametri"). K mazāks, lai laika trūkums nesodītu divreiz pilnā mērā.
export const CONF_WEIGHTS = { D: 1.0, C: 0.6, K: 0.4 };

// pct → līmeņa atslēga (sasaucas ar precisionMeta vārdnīcu confidence.js)
export function levelForPct(pct) {
    if (pct >= 85) return 'Visaugstākā';
    if (pct >= 70) return 'Augsta';
    if (pct >= 55) return 'Mērena līdz Augsta';
    if (pct >= 40) return 'Mērena';
    if (pct >= 20) return 'Zema';
    return 'Nekāda';
}

// Droša ceļa izvilkšana no objekta ("vedic.planets.Saule.houseDist")
function getPath(obj, path) {
    return path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj);
}

// Sadalījuma top-varbūtība (cik koncentrēts). dist = { idx: frac }.
function topProb(dist) {
    if (!dist || typeof dist !== 'object') return null;
    const vals = Object.values(dist);
    if (!vals.length) return null;
    return Math.max(...vals);
}

// Konverģence no skoru masīva [0..100]: 1 − amplitūda/100 (visi saskan → 1).
function convergence(scores) {
    const xs = (scores || []).filter(s => typeof s === 'number' && !isNaN(s));
    if (xs.length < 2) return null;
    const range = Math.max(...xs) - Math.min(...xs);
    return Math.max(0, Math.min(1, 1 - range / 100));
}

/**
 * @param {Object} descriptor
 *   drivers:   [{ path, weight?, label? }]   — sweep ceļi nosakāmībai
 *   crossSources: 'reliability' | number[]   — konverģencei (skori 0..100)
 *   requires:  ['time'|'birthplace'|'current'...]
 *   weights:   { D, C, K }                    — pārraksta noklusējumu
 * @param {Object} profile
 * @returns {{pct, levelKey, factors, summary, applicable}}
 */
export function computeConfidence(descriptor = {}, profile = {}) {
    const w = { ...CONF_WEIGHTS, ...(descriptor.weights || {}) };
    const sweep = profile.timeSweep || null;
    const timeKnown = !profile?.birth_info?.isTimeUnknown;
    const factors = [];
    const parts = []; // {x, weight}

    // ── D — Nosakāmība ───────────────────────────────────────────────
    const drivers = descriptor.drivers || [];
    if (drivers.length) {
        let D, detail, how = null;
        if (timeKnown || !sweep) {
            D = 1;
            detail = 'precīzs laiks zināms — pozīcijas viennozīmīgas';
        } else {
            let sum = 0, wsum = 0, weakest = null;
            for (const d of drivers) {
                const tp = topProb(getPath(sweep, d.path));
                const val = tp == null ? 1 : tp; // ja sweep ceļa nav (datuma-fiksēts) → 1
                const dw = d.weight ?? 1;
                sum += val * dw; wsum += dw;
                if (weakest == null || val < weakest.val) weakest = { val, label: d.label || d.path };
            }
            D = wsum ? sum / wsum : 1;
            detail = weakest && weakest.val < 0.6
                ? `vājākais: ${weakest.label} (~${Math.round(weakest.val * 100)}% noteikts)`
                : 'daļēji noteikts no datuma';
            if (D < 0.85) how = 'Norādi precīzu dzimšanas laiku (vai aptuvenu logu: rīts/diena/vakars) — sašaurinātu no 12 uz 1 zīmi.';
        }
        factors.push({ key: 'D', label: 'Nosakāmība', score: D, weight: w.D, deltaTxt: detail, howToRaise: how });
        parts.push({ x: D, weight: w.D });
    }

    // ── C — Konverģence ──────────────────────────────────────────────
    let scores = null;
    if (descriptor.crossSources === 'reliability') {
        scores = (typeof window !== 'undefined' && window.lastUzticamibaScores) || null;
    } else if (Array.isArray(descriptor.crossSources)) {
        scores = descriptor.crossSources;
    }
    const C = convergence(scores);
    if (C != null) {
        const lo = Math.round(Math.min(...scores)), hi = Math.round(Math.max(...scores));
        factors.push({
            key: 'C', label: 'Sistēmu saskaņa', score: C, weight: w.C,
            deltaTxt: C >= 0.8 ? `sistēmas saskan (${lo}–${hi}%)` : `sistēmas atšķiras (${lo}–${hi}%)`,
            howToRaise: C < 0.8 ? 'Sistēmas dod pretrunīgus rezultātus — šī sadaļa jāinterpretē piesardzīgi vai jāmeklē papildu validācija.' : null,
        });
        parts.push({ x: C, weight: w.C });
    } else if (descriptor.crossSources) {
        // Konverģences avots pieprasīts, bet skori nav pieejami (piem., panelis renderēts
        // ārpus pilnā dashboard konteksta) — GODĪGA informatīvā rinda bez ietekmes uz
        // kompozītu, lai 100% nerodas tikai no K faktora klusējot (fail-confident risks).
        factors.push({
            key: 'C', label: 'Sistēmu saskaņa', score: null, weight: 0,
            deltaTxt: 'nav novērtēta šajā skatā (Uzticamības skori nav ielādēti)',
            howToRaise: null,
        });
    }

    // ── K — Pilnīgums ────────────────────────────────────────────────
    const requires = descriptor.requires || ['birthplace'];
    if (requires.length) {
        const checks = requires.map(r => {
            if (r === 'time') return { ok: timeKnown, label: 'dzimšanas laiks' };
            if (r === 'birthplace') return { ok: !!(profile?.birth_info), label: 'dzimšanas vieta' };
            if (r === 'current') return { ok: true, label: 'šībrīža lokācija' };
            return { ok: true, label: r };
        });
        const okCount = checks.filter(c => c.ok).length;
        const K = checks.length ? okCount / checks.length : 1;
        const missing = checks.filter(c => !c.ok).map(c => c.label);
        factors.push({
            key: 'K', label: 'Datu pilnīgums', score: K, weight: w.K,
            deltaTxt: missing.length ? `trūkst: ${missing.join(', ')}` : 'visas ieejas norādītas',
            howToRaise: missing.length ? `Norādi: ${missing.join(', ')}.` : null,
        });
        parts.push({ x: K, weight: w.K });
    }

    // ── Kompozīts ────────────────────────────────────────────────────
    const wsum = parts.reduce((s, p) => s + p.weight, 0);
    const raw = wsum ? parts.reduce((s, p) => s + p.x * p.weight, 0) / wsum : 1;
    const pct = Math.round(raw * 100);

    return {
        pct,
        levelKey: levelForPct(pct),
        factors,
        applicable: parts.length > 0,
        // 2026-07-09: ierobežojuma apraksts no reģistra (kas nosaka griestus / ko vēl darīt)
        limits: descriptor.limits || null,
        summary: factors.filter(f => f.score != null).map(f => `${f.label} ${Math.round(f.score * 100)}%`).join(' · '),
    };
}
