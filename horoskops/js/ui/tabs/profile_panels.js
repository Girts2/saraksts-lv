// ── PROFILA PANEĻI (pārcelti no tab_profils.js / cilnes 'x') ──────────────────
// Šie divi paneļi atbild uz "KAS — kas ir šis cilvēks" un dzīvo cilnē 'Profils'.
//   • renderLeadershipPanel    — "Darba & Vadīšanas Stils" (8 arhetipi)
//   • renderPersonalityMatrix  — pilnā personības matrica (73 dimensijas)
// Vienots avots: tos lieto render_dashboard.js (Profils), tab_profils.js tos vairs nerāda.

import { getFlag } from '../trait_flags.js';

// ── Vadīšanas / sadarbības stils ──────────────────────────────────────────────
export function renderLeadershipPanel(profile) {
    const lt = profile.leadership;
    if (!lt) return '';

    const pr  = lt.primary;
    const sec = lt.secondary;

    // Arhetipa būtība (viena rinda katram no 8 tipiem) — skaidrojumam + metriku interpretācija.
    const ARCH_ESSENCE = {
        charismatic:   'vada caur personīgo piemēru un iedvesmu, ne caur formālu varu',
        authoritarian: 'vada caur skaidru struktūru, disciplīnu un ātriem lēmumiem',
        expert:        'vada caur kompetenci un zināšanu autoritāti, ne caur pozīciju',
        mentor:        'vada, attīstot citus — caur uzticību un cilvēku izaugsmi',
        visionary:     'vada caur idejām un nākotnes redzējumu, ne caur ikdienas kontroli',
        admin:         'vada caur procesiem, kārtību un uzticamu izpildi',
        specialist:    'dod vislabāko kā uzticams, kvalitatīvs izpildītājs, ne kā vienpersonisks vadītājs',
        solo:          'darbojas vislabāk patstāvīgi — ar savu vīziju un minimālu ārēju kontroli',
    };
    const prEss  = ARCH_ESSENCE[pr.key]  || 'darbojas savā unikālā stilā';
    const secEss = ARCH_ESSENCE[sec.key] || '';
    const skaidrojums = `Galvenais darba stils ir <b>${pr.lv}</b> (${pr.score}%) — viņš ${prEss}. To papildina sekundārā ievirze <b>${sec.lv}</b> (${sec.score}%)${secEss ? `, kas ${secEss}` : ''}.`;

    const warm = lt.warmCharisma, cold = lt.coldCharisma, ctrl = lt.controlDrive;
    const charismaNote = warm > cold + 8
        ? 'Ietekmē galvenokārt caur <b>silto harizmu</b> — personīgo saikni un attiecībām.'
        : cold > warm + 8
        ? 'Ietekmē galvenokārt caur <b>auksto harizmu</b> — kompetenci, statusu un racionālu autoritāti.'
        : 'Apvieno silto un auksto harizmu samērā līdzsvaroti.';
    const ctrlNote = ctrl >= 65
        ? ' Augsta kontroles dziņa — tieksme strukturēt un kontrolēt; jāuzmanās no mikromenedžmenta.'
        : ctrl <= 35
        ? ' Zema kontroles dziņa — viegli deleģē un dod citiem brīvību.'
        : ' Mērena kontroles dziņa — kontrolē, kur vajag, bet spēj arī atlaist.';
    const metrikuInterp = charismaNote + ctrlNote;

    const tableRow = (t) => {
        const isPrimary   = t.key === pr.key;
        const isSecondary = t.key === sec.key;
        const rowBg = isPrimary
            ? `background:${t.color}12; border-left:3px solid ${t.color};`
            : isSecondary
            ? `background:#f8fafc; border-left:3px solid ${t.color}60;`
            : 'border-left:3px solid transparent;';
        const nameFw = isPrimary ? '800' : isSecondary ? '600' : '400';
        const badge  = isPrimary
            ? `<span style="font-size:0.6rem;font-weight:700;background:${t.color};color:white;padding:1px 7px;border-radius:8px;margin-left:6px;vertical-align:middle;white-space:nowrap;">PRIMĀRS</span>`
            : isSecondary
            ? `<span style="font-size:0.6rem;font-weight:600;background:${t.color}22;color:${t.color};padding:1px 7px;border-radius:8px;margin-left:6px;vertical-align:middle;white-space:nowrap;border:1px solid ${t.color}60;">2.</span>`
            : '';
        return `
        <tr style="${rowBg}">
            <td style="padding:7px 10px 7px 12px; white-space:nowrap;">
                <span style="font-size:0.82rem; font-weight:${nameFw}; color:#1e293b;">${t.icon} ${t.lv}</span>${badge}
            </td>
            <td style="padding:7px 16px 7px 8px; width:100%;">
                <div style="background:#e2e8f0; border-radius:3px; height:7px; overflow:hidden; min-width:60px;">
                    <div style="width:${t.score}%; height:100%; background:${t.color}; border-radius:3px; transition:width 0.7s ease;"></div>
                </div>
            </td>
            <td style="padding:7px 12px 7px 0; text-align:right; white-space:nowrap;">
                <span style="font-size:0.82rem; font-weight:700; color:${isPrimary ? t.color : '#475569'};">${t.score}%</span>
            </td>
        </tr>`;
    };

    return `
    <div style="background:white; border-radius:14px; padding:1.4rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1rem;">

        <!-- Virsraksts (kreisā) + CENTRĒTS personības tips (3× lielāks) -->
        <div style="display:flex; align-items:center; margin-bottom:1.3rem; gap:0.5rem;">
            <h3 style="font-size:0.95rem; color:#1e293b; margin:0; flex:1; min-width:0;">Darba & Vadīšanas Stils</h3>
            <span style="font-size:2.1rem; font-weight:800; line-height:1; background:${pr.color}18; color:${pr.color}; padding:7px 24px; border-radius:16px; border:1.5px solid ${pr.color}40; white-space:nowrap;">${lt.archClassLabel}</span>
            <div style="flex:1; min-width:0;"></div>
        </div>

        <!-- Divas daļas BLAKUS: kreisā = arhetipu tabula, labā = metrikas + stiprumi/riski -->
        <div style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:flex-start;">

        <!-- KREISĀ: Arhetipa tabula -->
        <div style="flex:1 1 480px; min-width:430px;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid #f1f5f9;">
                    <th style="padding:4px 10px 6px 12px; font-size:0.68rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px; text-align:left;">Arhetips</th>
                    <th style="padding:4px 16px 6px 8px; font-size:0.68rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px; text-align:left;">Atbilstība</th>
                    <th style="padding:4px 12px 6px 0; font-size:0.68rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px; text-align:right;">%</th>
                </tr>
            </thead>
            <tbody>
                ${lt.allTypes.map(tableRow).join('')}
            </tbody>
        </table>
        </div>

        <!-- LABĀ: metriku režģis + skaidrojums + sekundārais (vertikāla kolonna) -->
        <div style="flex:1 1 440px; min-width:400px; display:flex; flex-direction:column; gap:1.1rem;">

            <!-- Metrikas | Stiprumi | Riski (3 kolonnas) -->
            <div style="display:grid; grid-template-columns:auto 1fr 1fr; gap:1.2rem; align-items:start;">
                <!-- Metrikas -->
                <div style="display:flex; flex-direction:column; gap:0.5rem; min-width:130px;">
                    <div style="font-size:0.68rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:2px;">Metrikas</div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="width:8px; height:8px; border-radius:50%; background:#f59e0b; flex-shrink:0;"></div>
                        <span style="font-size:0.78rem; color:#475569;">Siltā harizma</span>
                        <span style="font-size:0.78rem; font-weight:700; color:#f59e0b; margin-left:auto;">${lt.warmCharisma}%</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="width:8px; height:8px; border-radius:50%; background:#ef4444; flex-shrink:0;"></div>
                        <span style="font-size:0.78rem; color:#475569;">Aukstā harizma</span>
                        <span style="font-size:0.78rem; font-weight:700; color:#ef4444; margin-left:auto;">${lt.coldCharisma}%</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="width:8px; height:8px; border-radius:50%; background:#8b5cf6; flex-shrink:0;"></div>
                        <span style="font-size:0.78rem; color:#475569;">Kontroles dziņa</span>
                        <span style="font-size:0.78rem; font-weight:700; color:#8b5cf6; margin-left:auto;">${lt.controlDrive}%</span>
                    </div>
                </div>
                <!-- Stiprumi -->
                <div>
                    <div style="font-size:0.68rem; font-weight:700; color:#16a34a; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:6px;">Stiprumi</div>
                    <ul style="margin:0; padding-left:1.1rem; font-size:0.8rem; color:#334155; line-height:1.75;">
                        ${pr.strengths.map(str => `<li>${str}</li>`).join('')}
                    </ul>
                </div>
                <!-- Riski -->
                <div>
                    <div style="font-size:0.68rem; font-weight:700; color:#dc2626; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:6px;">Riski</div>
                    <ul style="margin:0; padding-left:1.1rem; font-size:0.8rem; color:#334155; line-height:1.75;">
                        ${pr.risks.map(rsk => `<li>${rsk}</li>`).join('')}
                    </ul>
                </div>
            </div>

            <!-- Ko tas nozīmē: arhetipa skaidrojums + metriku interpretācija -->
            <div style="border-top:1px solid #f1f5f9; padding-top:0.9rem;">
                <div style="font-size:0.68rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:5px;">Ko tas nozīmē</div>
                <div style="font-size:0.8rem; color:#334155; line-height:1.6;">${skaidrojums}</div>
                <div style="font-size:0.8rem; color:#475569; line-height:1.6; margin-top:6px;">${metrikuInterp}</div>
            </div>

            <!-- Sekundārā ievirze -->
            <div style="background:#f8fafc; border-radius:10px; padding:0.7rem 0.9rem;">
                <div style="font-size:0.68rem; font-weight:700; color:${sec.color}; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:5px;">Sekundārā ievirze · ${sec.icon} ${sec.lv} (${sec.score}%)</div>
                <ul style="margin:0; padding-left:1.1rem; font-size:0.8rem; color:#334155; line-height:1.7;">
                    ${sec.strengths.slice(0, 3).map(s => `<li>${s}</li>`).join('')}
                </ul>
            </div>

        </div>
        </div>
    </div>`;
}

// ── Personības un Interešu Profils (bipolārs) ─────────────────────────────────
// Big Five tiek rādīts VIENMĒR; RIASEC tikai ja opts.riasec === true.
// Profils cilnē: { riasec:false } (tikai Big Five). Karjeras cilnē: { riasec:true } (Big Five + RIASEC).
export function renderBipolarProfile(profile, { riasec = true } = {}) {
    const personality = profile.personality || [];
    const userVec     = profile.careers?.user || null;

    const ocean    = personality.find(cat => cat.id === 'ocean');
    const oceanPct = {};
    if (ocean) for (const t of ocean.traits) oceanPct[t.id] = t.pct;
    // Noapaļo + ierobežo 0–100, lai VISI patērētāji (joslas, pentagons, nozīmes, band9, kombinācijas) rāda vienu skaitli.
    const clampPct = x => Math.max(0, Math.min(100, Math.round(Number(x))));
    const bfG = {
        O: clampPct(oceanPct.openness      ?? userVec?.bf?.O  ?? 50),
        C: clampPct(oceanPct.conscient     ?? userVec?.bf?.C  ?? 50),
        E: clampPct(oceanPct.extraversion  ?? userVec?.bf?.E  ?? 50),
        A: clampPct(oceanPct.agreeableness ?? userVec?.bf?.A  ?? 50),
        N: clampPct(oceanPct.neuroticism   ?? (userVec?.bf?.Ns != null ? 100 - userVec.bf.Ns : 50)),
    };
    const rs = userVec?.riasec || {};

    // Bipolārā josla ar IEKRĀSOTU tipisko joslu (35–65) un izceltu marķieri, ja ārpus tās.
    const mkBipolar = ({ letter, name, value, color, desc, leftPole, rightPole }) => {
        const v = Math.max(0, Math.min(100, Math.round(value ?? 50)));
        const notable = v < 35 || v > 65;
        const dotSize  = notable ? 17 : 13;
        const dotShadow = notable ? `0 0 0 4px ${color}33, 0 2px 6px rgba(0,0,0,0.25)` : '0 2px 5px rgba(0,0,0,0.2)';
        const stack = s => String(s).split(' · ').join('<br>');   // vārdi viens zem otra → vairāk vietas skalai
        const headerHtml = `
            <div style="margin-bottom:3px;">
                <span style="font-size:0.9rem; font-weight:800; color:${color};">${letter}</span>
                <span style="font-size:0.78rem; font-weight:600; color:#334155; margin-left:7px;">${name}</span>
                ${notable ? `<span style="font-size:0.58rem; font-weight:800; color:${color}; background:${color}18; border:1px solid ${color}40; border-radius:6px; padding:1px 6px; margin-left:6px; vertical-align:middle;">IZTEIKTI</span>` : ''}
            </div>
            <div style="font-size:0.68rem; color:#64748b; line-height:1.45; font-style:italic;">${desc}</div>`;
        // Izceļ to polu, kas ir IZTEIKTS (vērtība ārpus pelēkās 35–65 joslas): v<35 → kreisais, v>65 → labais.
        const EXP = 'border:1.5px solid #16a34a; background:#f0fdf4; border-radius:8px; padding:5px 8px; color:#166534; font-weight:700;';
        const leftHtml  = (v < 35)
            ? `<div style="font-size:0.7rem; text-align:right; line-height:1.35; ${EXP}">${stack(leftPole)}</div>`
            : `<div style="font-size:0.7rem; color:#64748b; text-align:right; line-height:1.35; padding-right:8px;">${stack(leftPole)}</div>`;
        const rightHtml = (v > 65)
            ? `<div style="font-size:0.7rem; text-align:left; line-height:1.35; ${EXP}">${stack(rightPole)}</div>`
            : `<div style="font-size:0.7rem; color:#64748b; text-align:left; line-height:1.35; padding-left:8px;">${stack(rightPole)}</div>`;
        const barHtml = `
            <div style="position:relative; height:44px; margin:0 8px;">
                <div style="position:absolute; top:13px; left:0; right:0; height:4px; background:#e2e8f0; border-radius:2px;"></div>
                <!-- tipiskā josla 35–65 -->
                <div style="position:absolute; top:8px; left:35%; width:30%; height:14px; background:rgba(148,163,184,0.16); border-left:1px dashed #cbd5e1; border-right:1px dashed #cbd5e1; border-radius:3px;"></div>
                <div style="position:absolute; top:11px; left:50%; width:1px; height:8px; background:#94a3b8;"></div>
                <div style="position:absolute; top:${(15 - dotSize / 2).toFixed(1)}px; left:${v}%; transform:translateX(-50%); width:${dotSize}px; height:${dotSize}px; background:${color}; border-radius:50%; border:2.5px solid white; box-shadow:${dotShadow}; z-index:2;"></div>
                <div style="position:absolute; top:30px; left:${v}%; transform:translateX(-50%); font-size:0.65rem; font-weight:800; color:${color}; white-space:nowrap; text-shadow:0 0 3px #fff, 0 0 3px #fff;">${v}</div>
            </div>`;
        // riasec (Karjera, šauras blakus kolonnas): teksts VIRS skalas → skalai vairāk horizontālās vietas.
        if (riasec) {
            return `
            <div style="padding:0.7rem 0; border-bottom:1px solid #f1f5f9;">
                <div style="margin-bottom:7px;">${headerHtml}</div>
                <div style="display:grid; grid-template-columns:115px 1fr 115px; align-items:center; gap:0.6rem;">
                    ${leftHtml}${barHtml}${rightHtml}
                </div>
            </div>`;
        }
        // Profils (riasec:false, plata kolonna): teksts blakus skalai (kā iepriekš).
        return `
        <div style="display:grid; grid-template-columns:190px 120px 1fr 120px; align-items:center; gap:0.75rem; padding:0.75rem 0; border-bottom:1px solid #f1f5f9;">
            <div>${headerHtml}</div>
            ${leftHtml}${barHtml}${rightHtml}
        </div>`;
    };

    // ── Big Five bagātinājums (tikai Profila panelī, !riasec) ────────────────────
    // Atbildes 3. PERSONĀ, VĪRIEŠU dzimtē. 9 līmeņi (3× sīkāks solis nekā agrākie 3) →
    // teksts mainās ~ik 11 punktus. band9(v) = 0..8. (lvl/L paliek kombināciju detektoram.)
    const band9 = v => Math.max(0, Math.min(8, Math.floor(v / 100 * 9)));
    const lvl = x => x > 65 ? 'high' : (x < 35 ? 'low' : 'mid');   // 35–65 = tipisks (sakrīt ar joslas "IZTEIKTI" un ievadu)
    const L = { O: lvl(bfG.O), C: lvl(bfG.C), E: lvl(bfG.E), A: lvl(bfG.A), N: lvl(bfG.N) };

    // 1) AUTO-PORTRETS — 9 vīriešu dz. gradācijas katrai asij (zems pols → augsts pols)
    const POR = {
        O: ['ārkārtīgi praktisks un tradicionāls', 'izteikti praktisks, tur pie pārbaudītā', 'praktisks un piesardzīgs pret jaunievedumiem', 'drīzāk konkrēts, ar mērenu zinātkāri', 'līdzsvarots starp jauno un pārbaudīto', 'mēreni zinātkārs un atvērts idejām', 'radošs un atvērts jaunām pieejām', 'izteikti radošs un eksperimentējošs', 'ārkārtīgi radošs, abstrakts vizionārs'],
        C: ['ļoti spontāns un brīvs no struktūras', 'izteikti spontāns, ar mainīgu fokusu', 'elastīgs un improvizējošs', 'drīzāk elastīgs, bet pamatā paveic', 'organizēts, kad vajag, taču elastīgs', 'pietiekami disciplinēts un kārtīgs', 'disciplinēts un uzticams', 'izteikti disciplinēts un metodisks', 'ārkārtīgi metodisks, gandrīz perfekcionistisks'],
        E: ['ļoti rezervēts un noslēgts', 'izteikti introvertēts un klusējošs', 'rezervēts un introspektīvs', 'drīzāk kluss, bet ērts mazās grupās', 'ērts gan komandā, gan vienatnē', 'pietiekami sabiedrisks un atvērts', 'sabiedrisks un enerģisks', 'izteikti sabiedrisks un runīgs', 'ārkārtīgi enerģisks, dzīvo no pūļa'],
        A: ['ļoti tiešs un sacensties orientēts', 'izteikti skeptisks un kritiski neatkarīgs', 'tiešs un godīgs, aizstāv savu', 'drīzāk neatkarīgs, bet sadarbojas', 'sabalansē sadarbību ar savu nostāju', 'pietiekami pretimnākošs un kooperatīvs', 'empātisks un pretimnākošs', 'izteikti empātisks un altruistisks', 'ārkārtīgi pašaizliedzīgs'],
        N: ['pilnīgi mierīgs un nesatricināms', 'ļoti stabils un aukstasinīgs', 'mierīgs un noturīgs', 'drīzāk noturīgs, reaģē samērīgi', 'emocionāli līdzsvarots', 'mēreni jutīgs', 'emocionāli jutīgs un reaģējošs', 'ļoti jutīgs un viegli satraucams', 'ārkārtīgi jutīgs un emocionāli mainīgs'],
    };
    const portrait = `Šis cilvēks ir ${POR.O[band9(bfG.O)]}. Darbā — ${POR.C[band9(bfG.C)]}; sociāli — ${POR.E[band9(bfG.E)]}. Sadarbībā — ${POR.A[band9(bfG.A)]}; zem spiediena — ${POR.N[band9(bfG.N)]}.`;

    // 2) "KO TAS NOZĪMĒ" katrai asij — 9 vīriešu dz. gradācijas (spēks + uzmanība)
    const MEAN = {
        O: ['stingri tur pie pārbaudītā — drošs, bet pārmaiņas uztver kā draudu', 'praktisks un piesardzīgs — uzmanība: var palaist garām jaunas iespējas', 'uzticas zināmajam — stabils, bet negribīgi maina pieeju', 'pamatā konkrēts, ar nelielu zinātkāri', 'elastīgi sabalansē jauno un pārbaudīto', 'mēreni atvērts idejām un eksperimentiem', 'tīko pēc jaunā — radošs, bet rutīnā var zaudēt fokusu', 'ļoti radošs — uzmanība: par daudz ideju, par maz pabeigtā', 'idejām pārpilns vizionārs — uzmanība: praktiskā izpilde un fokuss'],
        C: ['rīkojas pēc brīža — uzmanība: nepabeigti darbi un haoss', 'izteikti spontāns — uzmanība: saistību un termiņu turēšana', 'elastīgs un improvizē — labi mainīgā vidē, grūtāk rutīnā', 'pamatā paveic, bet fokuss svārstās', 'organizēts, kad vajag, spēj improvizēt', 'pietiekami kārtīgs un uzticams', 'uzticams un pabeidz iesākto — uzmanība: neelastība', 'ļoti disciplinēts — uzmanība: perfekcionisms un stīvums', 'galēji metodisks — uzmanība: grūti deleģēt un pieņemt "pietiek labi"'],
        E: ['dziļi noslēgts — uzlādējas vienatnē, pūlis nogurdina', 'izteikti introvertēts — uzmanība: var palikt nepamanīts grupā', 'rezervēts — dziļš fokuss vienatnē, klusāks komandā', 'drīzāk kluss, bet ērts pazīstamā lokā', 'ērti gan grupā, gan viens', 'pietiekami sabiedrisks un kontaktējams', 'uzlādējas no cilvēkiem, vada sarunu — uzmanība: nepacietība pret vientuļo darbu', 'ļoti sabiedrisks — uzmanība: grūtāk ar ilgu fokusētu darbu', 'galēji enerģisks pūlī — uzmanība: vajag pastāvīgu sociālu stimulāciju'],
        A: ['ass un sacensties orientēts — uzmanība: var atgrūst komandu', 'izteikti skeptisks — uzmanība: grūti uzticēties citiem', 'tiešs un godīgs — var šķist ass, bet uzticams', 'drīzāk neatkarīgs, tomēr sadarbojas', 'sadarbojas, bet aizstāv savu nostāju', 'pietiekami pretimnākošs un kooperatīvs', 'veido harmoniju un sadarbību — uzmanība: grūti pateikt "nē"', 'ļoti empātisks — uzmanība: paša vajadzības atstāj pēdējās', 'galēji pašaizliedzīgs — uzmanība: viegli izmantojams'],
        N: ['auksts prāts krīzē — uzmanība: var nepamanīt savu vai citu spriedzi', 'ļoti stabils un aukstasinīgs', 'mierīgs un noturīgs zem spiediena', 'pamatā noturīgs, reaģē samērīgi', 'emocionāli līdzsvarots', 'mēreni jutīgs pret spriedzi un kritiku', 'jūtīgs un nojauš riskus — uzmanība: stress un pārdzīvojumi', 'ļoti reaktīvs — uzmanība: trauksme un izdegšana', 'galēji jutīgs — uzmanība: emocijas viegli pārņem, vajag drošu vidi'],
    };
    const meanRow = (ltr, nm, key, col) => `
        <div style="display:flex; gap:8px; align-items:baseline; padding:5px 0; border-bottom:1px solid #f8fafc;">
            <span style="font-size:0.74rem; font-weight:800; color:${col}; flex:0 0 14px;">${ltr}</span>
            <span style="font-size:0.72rem; color:#334155; line-height:1.5;"><b>${nm} ${bfG[key]}</b> — ${MEAN[key][band9(bfG[key])]}</span>
        </div>`;
    const meaningBlock = `
        <div style="flex:1; min-width:300px;">
            <div style="font-size:0.6rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">Ko katra ass nozīmē</div>
            ${meanRow('O','Atvērtība','O','#3b82f6')}
            ${meanRow('C','Apzinīgums','C','#10b981')}
            ${meanRow('E','Ekstraversija','E','#f59e0b')}
            ${meanRow('A','Laipnība','A','#ec4899')}
            ${meanRow('N','Neirotisms','N','#8b5cf6')}
        </div>`;

    // 3) KOMBINĀCIJU DETEKTORS
    const combos = [];
    const addC = (cond, icon, title, text) => { if (cond) combos.push({ icon, title, text }); };
    addC(L.O==='high' && L.C==='high', '🧩', 'Vizionārs izpildītājs', 'Rada jaunas idejas UN tās noved līdz galam — reta, vērtīga kombinācija.');
    addC(L.O==='high' && L.C==='low',  '💡', 'Ideju ģenerators', 'Daudz radošu ideju, bet izpilde svārstīga — viņam vajag partneri, kas pabeidz.');
    addC(L.E==='low'  && L.N==='high', '🌧️', 'Jūtīgs vientuļnieks', 'Dziļi izjūt un viegli pārslogojas — viņam vajag klusu, drošu vidi.');
    addC(L.E==='high' && L.A==='high', '🤝', 'Dabisks tīklotājs', 'Silts un sabiedrisks — viegli veido un uztur attiecības.');
    addC(L.A==='low'  && L.C==='high', '🎯', 'Prasīgs izpildītājs', 'Augsti standarti un tieša komunikācija — rezultāts pāri komfortam.');
    addC(L.N==='low'  && L.C==='high', '🛡️', 'Stabils balsts', 'Mierīgs un uzticams pat krīzē — drošs atbalsts komandai.');
    addC(L.O==='high' && L.E==='high', '🚀', 'Entuziasma dzinējs', 'Aizraujas ar jauno un aizrauj līdzi citus.');
    addC(L.A==='high' && L.N==='high', '💗', 'Empātisks, bet ievainojams', 'Rūpējas par citiem, taču pats viegli izsīkst.');
    addC(L.C==='low'  && L.N==='high', '🌀', 'Haoss zem stresa', 'Spiediena brīžos viņam vajag struktūru un atbalstu, lai nepazustu.');
    addC(L.O==='low'  && L.C==='high', '🧱', 'Uzticams pamatu turētājs', 'Praktisks un kārtīgs — lielisks stabilās, procesu lomās.');
    addC(L.E==='low'  && L.A==='low',  '🧭', 'Patstāvīgs vienpatis', 'Strādā labāk viens un bez sociāla spiediena — tiešs, neatkarīgs, ne komandas cilvēks.');
    addC(L.O==='low'  && L.N==='low',  '⚓', 'Mierīgs realists', 'Praktisks un emocionāli stabils — pārbaudīta pieeja bez liekas dramatikas.');
    addC(L.C==='low'  && L.A==='high', '🌻', 'Sirsnīgs, bet izklaidīgs', 'Labvēlīgs un palīdzošs, taču saistību un termiņu turēšana svārstās.');
    addC(L.E==='high' && L.C==='low',  '🎪', 'Sociāls improvizators', 'Enerģisks un kontaktējams, bet struktūra un pabeigšana klibo.');

    // Izteiktās atsevišķās asis (ārpus 35–65) — lai rezerves teksts būtu godīgs.
    const notableList = [['Atvērtība', bfG.O], ['Apzinīgums', bfG.C], ['Ekstraversija', bfG.E], ['Laipnība', bfG.A], ['Neirotisms', bfG.N]]
        .filter(([n, v]) => v < 35 || v > 65)
        .map(([n, v]) => `<b>${n}</b> (${v}${v > 65 ? ', augsts' : ', zems'})`);

    const comboBlock = combos.length ? `
        <div style="margin-top:1.2rem;">
            <div style="font-size:0.6rem; font-weight:800; color:#7c3aed; text-transform:uppercase; letter-spacing:1px; margin-bottom:7px;">🧬 Iezīmju kombinācijas — ko skaitļi atsevišķi nepasaka</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:0.7rem;">
                ${combos.slice(0,4).map(c => `
                <div style="background:#faf5ff; border:1px solid #e9d5ff; border-left:3px solid #a855f7; border-radius:10px; padding:0.7rem 0.9rem;">
                    <div style="font-size:0.82rem; font-weight:800; color:#1e293b; margin-bottom:3px;">${c.icon} ${c.title}</div>
                    <div style="font-size:0.74rem; color:#475569; line-height:1.5;">${c.text}</div>
                </div>`).join('')}
            </div>
        </div>` : notableList.length ? `
        <div style="margin-top:1.2rem; background:#fffbeb; border:1px solid #fde68a; border-left:3px solid #f59e0b; border-radius:10px; padding:0.7rem 0.9rem; font-size:0.78rem; color:#78350f; line-height:1.55;">
            🔎 <b>Izteiktas atsevišķas iezīmes:</b> ${notableList.join(', ')} — tās neveido vienu nozīmīgu pāru kombināciju, tāpēc katra darbojas patstāvīgi. Skaties katras ass nozīmi atsevišķi (pa kreisi).
        </div>` : `
        <div style="margin-top:1.2rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:0.7rem 0.9rem; font-size:0.78rem; color:#166534;">⚖️ Līdzsvarots profils — nevienā asī nav izteiktu galējību (visi rādītāji 35–65); šis cilvēks ir elastīgs un pielāgojas situācijai.</div>`;

    // 4) PENTAGONA RADARS
    const pentagon = (() => {
        // [burts, pilns nosaukums, vērtība, krāsa]
        const axes = [
            ['O', 'Atvērtība',    bfG.O, '#3b82f6'],
            ['C', 'Apzinīgums',   bfG.C, '#10b981'],
            ['E', 'Ekstraversija',bfG.E, '#f59e0b'],
            ['A', 'Laipnība',     bfG.A, '#ec4899'],
            ['N', 'Neirotisms',   bfG.N, '#8b5cf6'],
        ];
        const cx = 185, cy = 165, R = 88;
        const pt = (i, r) => { const a = (-90 + i * 72) * Math.PI / 180; return [cx + r * Math.cos(a), cy + r * Math.sin(a)]; };
        const poly = r => axes.map((_, i) => pt(i, r).map(n => n.toFixed(1)).join(',')).join(' ');
        // Tīkls + skalas skaitļi (25/50/75/100) pie augšējās ass
        let grids = '', scale = '';
        [0.25, 0.5, 0.75, 1].forEach(f => {
            grids += `<polygon points="${poly(R * f)}" fill="none" stroke="#cbd5e1" stroke-opacity="0.55" stroke-width="1"/>`;
            scale += `<text x="${(cx + 4).toFixed(1)}" y="${(cy - R * f + 3).toFixed(1)}" font-size="9" fill="#94a3b8" font-family="'Outfit',sans-serif">${Math.round(f * 100)}</text>`;
        });
        const spokes = axes.map((_, i) => { const [x, y] = pt(i, R); return `<line x1="${cx}" y1="${cy}" x2="${x.toFixed(1)}" y2="${y.toFixed(1)}" stroke="#e2e8f0" stroke-width="1"/>`; }).join('');
        const valPts = axes.map((ax, i) => pt(i, R * (Math.max(0, Math.min(100, ax[2])) / 100)));
        const valPoly = valPts.map(p => p.map(n => n.toFixed(1)).join(',')).join(' ');
        const dots = valPts.map((p, i) => `<circle cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="4.5" fill="${axes[i][3]}" stroke="#fff" stroke-width="1.5"/>`).join('');
        // Uzraksti ārpus virsotnēm: nosaukums + vērtība% (kā Spēju spektrā)
        const off = [
            { dx: 0,   dy: -16, a: 'middle' }, // augša
            { dx: 14,  dy: -2,  a: 'start'  }, // augš-labā
            { dx: 14,  dy: 10,  a: 'start'  }, // apakš-labā
            { dx: -14, dy: 10,  a: 'end'    }, // apakš-kreisā
            { dx: -14, dy: -2,  a: 'end'    }, // augš-kreisā
        ];
        const labels = axes.map((ax, i) => {
            const [x, y] = pt(i, R + 16); const o = off[i];
            return `
            <text x="${(x + o.dx).toFixed(1)}" y="${(y + o.dy).toFixed(1)}" text-anchor="${o.a}" font-size="13" font-weight="700" fill="#334155" font-family="'Outfit',sans-serif">${ax[1]}</text>
            <text x="${(x + o.dx).toFixed(1)}" y="${(y + o.dy + 15).toFixed(1)}" text-anchor="${o.a}" font-size="13" font-weight="800" fill="${ax[3]}" font-family="'Outfit',sans-serif">${ax[2]}</text>`;
        }).join('');
        return `
        <svg viewBox="0 0 370 330" style="width:100%; max-width:350px;">
            ${grids}${spokes}${scale}
            <polygon points="${valPoly}" fill="rgba(124,58,237,0.16)" stroke="#7c3aed" stroke-width="2" stroke-linejoin="round"/>
            ${dots}${labels}
        </svg>`;
    })();

    const bigFiveSection = `
        <div style="font-size:0.65rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.2rem; padding-bottom:5px; border-bottom:2px solid #f1f5f9;">Big Five — Personības Dimensijas</div>
        ${mkBipolar({ letter:'O', name:'Atvērtība', value:bfG.O, color:'#3b82f6',
            desc:'Vai persona meklē jaunas pieejas un idejas vai dod priekšroku pārbaudītām metodēm?',
            leftPole:'Tradicionāls · Konkrēts · Rutīnu mīlošs',
            rightPole:'Radošs · Abstrakts · Eksperimentētājs' })}
        ${mkBipolar({ letter:'C', name:'Apzinīgums', value:bfG.C, color:'#10b981',
            desc:'Cik uzticams un disciplinēts darbinieks? Vai pabeidz uzsākto un ievēro saistības?',
            leftPole:'Impulsīvs · Spontāns · Neorganizēts',
            rightPole:'Disciplinēts · Metodisks · Uzticams' })}
        ${mkBipolar({ letter:'E', name:'Ekstraversija', value:bfG.E, color:'#f59e0b',
            desc:'Vai efektīvāks komandas/klientu lomās vai patstāvīgā, fokusētā darbā?',
            leftPole:'Introspektīvs · Klusējoš · Uzlādējas vientulībā',
            rightPole:'Sabiedrisks · Runīgs · Enerģisks pūlī' })}
        ${mkBipolar({ letter:'A', name:'Laipnība', value:bfG.A, color:'#ec4899',
            desc:'Kā persona strādā komandā — veido harmoniju un sadarbību vai mudina uz sacensību?',
            leftPole:'Skeptisks · Tiešs · Sacensties orientēts',
            rightPole:'Empātisks · Pretimnākošs · Altruistisks' })}
        ${mkBipolar({ letter:'N', name:'Neirotisms', value:bfG.N, color:'#8b5cf6',
            desc:'Kā persona reaģē uz stresu, kritiku un nenoteiktību darba vidē?',
            leftPole:'Stabils · Mierīgs · Stiprs zem spiediena',
            rightPole:'Trauksmaīgs · Jūtīgs · Emocionāli mainīgs' })}`;

    const riasecSection = riasec ? `
        <div style="font-size:0.65rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:1px; margin:0 0 0.2rem; padding-bottom:5px; border-bottom:2px solid #f1f5f9;">RIASEC — Profesionālās Intereses (Holland)</div>
        ${mkBipolar({ letter:'R', name:'Reālistiskais', value:rs.R, color:'#d97706',
            desc:'Vai pievilina praktisks, taustāms darbs ar redzamu rezultātu?',
            leftPole:'Konceptuāls · Abstrakts',
            rightPole:'Praktisks · Tehnisks · Fizisks' })}
        ${mkBipolar({ letter:'I', name:'Izmeklējošais', value:rs.I, color:'#0891b2',
            desc:'Vai motivē problēmu izpēte, analīze un dziļas izpratnes meklēšana?',
            leftPole:'Rīcībā orientēts · Intuitīvs',
            rightPole:'Analītiski pētniecisks · Faktu balstīts' })}
        ${mkBipolar({ letter:'A', name:'Mākslinieciskais', value:rs.A, color:'#7c3aed',
            desc:'Vai nepieciešama radoša brīvība, vai persona labi strādā strukturētā vidē?',
            leftPole:'Strukturēts · Rutīnas meklētājs',
            rightPole:'Radošs · Ekspresīvs · Netradicionāls' })}
        ${mkBipolar({ letter:'S', name:'Sociālais', value:rs.S, color:'#16a34a',
            desc:'Vai enerģija nāk no palīdzēšanas un mācīšanas citiem, vai no sistēmām un procesiem?',
            leftPole:'Lietu / sistēmu orientēts',
            rightPole:'Cilvēku orientēts · Palīdzošs' })}
        ${mkBipolar({ letter:'E', name:'Uzņēmīgais', value:rs.E, color:'#f97316',
            desc:'Vai ir dabiska tieksme uz vadīšanu, pārliecināšanu un ietekmi uz citiem?',
            leftPole:'Izpildītājs · Seko norādījumiem',
            rightPole:'Vadošs · Ambiciozs · Pārliecinošs' })}
        ${mkBipolar({ letter:'C', name:'Konvencionālais', value:rs.C, color:'#64748b',
            desc:'Vai personas comfort zone ir skaidra struktūra, procesi un kārtība?',
            leftPole:'Brīvs no struktūras · Neformāls',
            rightPole:'Kārtīgs · Procesuāls · Mīl datus' })}
        <div style="padding-top:0.1rem;"></div>` : '';

    const titleText = riasec ? 'Personības un Interešu Profils' : 'Personības Profils';
    const riasecIntro = riasec
        ? ' Papildus <b>RIASEC</b> (Džona Hollanda modelis) parāda profesionālās intereses — kāda veida darbs cilvēku dabiski pievelk.'
        : '';

    // Profilā (!riasec) — bagātinātais izkārtojums; Karjerā (riasec) — kompakts (Big Five + RIASEC).
    const portraitBox = `
        <div style="background:#eef2ff; border:1px solid #c7d2fe; border-radius:12px; padding:0.95rem 1.15rem; margin-top:1.3rem;">
            <div style="font-size:0.6rem; font-weight:800; color:#4338ca; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:5px;">🧬 Personības portrets</div>
            <div style="font-size:0.88rem; color:#1e293b; line-height:1.7;">${portrait}</div>
        </div>`;

    const bodyHtml = riasec
        ? `<div style="display:flex; gap:1.6rem; flex-wrap:wrap; align-items:flex-start;">
            <div style="flex:1 1 540px; min-width:500px;">${bigFiveSection}</div>
            <div style="flex:1 1 540px; min-width:500px;">${riasecSection}</div>
        </div>`
        : `
        <!-- KREISĀ: Big Five forma + Ko katra ass nozīmē; LABĀ: Big Five — Personības Dimensijas + Personības portrets -->
        <div style="display:flex; gap:1.6rem; flex-wrap:wrap; align-items:flex-start;">
            <div style="flex:1 1 320px; min-width:300px;">
                <div style="text-align:center; padding-top:1.4rem;">
                    <div style="font-size:0.6rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">Big Five forma</div>
                    ${pentagon}
                </div>
                <div style="margin-top:1.3rem;">
                    ${meaningBlock}
                </div>
            </div>
            <div style="flex:2 1 620px; min-width:620px;">
                ${bigFiveSection}
                ${portraitBox}
            </div>
        </div>

        ${comboBlock}`;

    return `
    <div style="background:white; border-radius:14px; padding:1.4rem 1.6rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1rem;">
        <h3 style="font-size:1rem; color:#1e293b; margin:0 0 0.3rem 0; font-weight:700;">${titleText}</h3>

        <div style="background:#f8fafc; border-radius:10px; padding:0.9rem 1.1rem; margin-bottom:1.4rem; border-left:3px solid #94a3b8;">
            <p style="font-size:0.78rem; color:#334155; line-height:1.7; margin:0;">
                <b>Big Five (OCEAN)</b> ir psiholoģijā visplašāk pārbaudītais personības modelis — 5 pamatiezīmes:
                Atvērtība (O), Apzinīgums (C), Ekstraversija (E), Laipnība (A) un Neirotisms (N), kas kopā aptver lielāko daļu no tā,
                ar ko cilvēki atšķiras viens no otra. Šis panelis rāda, kur uz katras ass atrodas <b>pētāmā persona</b>.${riasecIntro}
                Vērtības ir <b>aprēķinātas no šī cilvēka dzimšanas kartes (astroloģiskajiem) datiem</b> — tāpēc horoskopu var lasīt arī kā Big Five personības profilu.
                Pelēkā josla (<b>35–65</b>) ir tipiskais vidējais; marķieris <b>ārpus tās</b> = izteikta iezīme, kas ietekmēs sadarbību, lomu piemērotību un darba vidi.
            </p>
        </div>

        ${bodyHtml}
    </div>`;
}

// ── Personības Matrica (73 dimensijas, 5 sistēmas) ────────────────────────────
export function renderPersonalityMatrix(profile) {
    const personality = profile.personality || [];
    if (!personality.length) return '';

    const renderTrait = (t, catColor) => {
        const flag = getFlag(t.id, t.pct);
        const pctColor = flag === 'green' ? '#10b981' : flag === 'red' ? '#ef4444' : catColor;
        const barColor = flag === 'green' ? '#10b981' : flag === 'red' ? '#ef4444' : catColor;
        const boxStyle = flag === 'green'
            ? 'border:1.5px solid #10b981;border-radius:6px;padding:5px 7px 5px 7px;background:rgba(16,185,129,0.07);'
            : flag === 'red'
            ? 'border:1.5px solid #ef4444;border-radius:6px;padding:5px 7px 5px 7px;background:rgba(239,68,68,0.07);'
            : '';
        return `
        <div style="margin-bottom:0.75rem;${boxStyle}">
            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:3px;">
                <span style="font-size:0.78rem; color:#cbd5e1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:68%;" title="${t.desc || t.label}">${t.label}</span>
                <span style="font-size:0.8rem; font-weight:700; color:${pctColor}; margin-left:4px; white-space:nowrap;">${t.pct}%</span>
            </div>
            <div style="background:#1e293b; border-radius:4px; height:5px; overflow:hidden;">
                <div style="width:${t.pct}%; height:100%; background:${barColor}; border-radius:4px; transition:width 0.7s ease; opacity:${flag ? '1' : '0.85'};"></div>
            </div>
            ${t.desc ? `<div style="font-size:0.65rem; color:#475569; margin-top:2px; line-height:1.3;">${t.desc}</div>` : ''}
        </div>`;
    };

    return `
    <div style="background:#0f172a; border-radius:16px; padding:1.6rem; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.4rem; flex-wrap:wrap;">
            <div style="display:flex; align-items:baseline; gap:1rem; flex-wrap:wrap;">
                <h3 style="font-size:1rem; color:#e2e8f0; margin:0; font-weight:800; letter-spacing:-0.3px;">Personības Matrica</h3>
                <span style="font-size:0.75rem; color:#475569;">${personality.reduce((s,c) => s + c.traits.length, 0)} dimensijas · 5 astroloģijas sistēmas</span>
            </div>
            <div style="display:flex; gap:10px; font-size:0.72rem; flex-shrink:0;">
                <span style="border:1.5px solid #10b981;border-radius:4px;padding:2px 8px;color:#10b981;">Ļoti labs</span>
                <span style="border:1.5px solid #ef4444;border-radius:4px;padding:2px 8px;color:#ef4444;">Pievērst uzmanību</span>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem;">
            ${personality.map(cat => `
            <div>
                <div style="display:flex; align-items:center; gap:6px; margin-bottom:0.9rem; padding-bottom:6px; border-bottom:1px solid rgba(255,255,255,0.06);">
                    <div style="width:10px; height:10px; border-radius:50%; background:${cat.color}; flex-shrink:0;"></div>
                    <span style="font-size:0.72rem; font-weight:700; color:${cat.color}; text-transform:uppercase; letter-spacing:1.2px;">${cat.label}</span>
                </div>
                ${cat.traits.map(t => renderTrait(t, cat.color)).join('')}
            </div>`).join('')}
        </div>
    </div>`;
}
