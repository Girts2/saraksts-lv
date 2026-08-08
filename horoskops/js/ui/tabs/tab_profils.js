
// Atgriež TIKAI Ieteicamās Profesijas (O*NET) — pārējais identitātes saturs pārcelts uz
// cilni 'Profils'. Lieto cilnē 'Karjera' (render_dashboard.js).
export function renderCareersPanel(profile) {
    // ── Ieteicamo Profesiju Paneļi ────────────────────────────────────────────
    const careersData = profile.careers || {};
    const zonesData   = careersData.zones || {};
    const careersPanel = Object.keys(zonesData).length ? (() => {
        const ZONE_META = {
            2: { label: 'Zone 2',   edu: 'Vidusskola',        color: '#10b981' },
            3: { label: 'Zone 3',   edu: 'Arodskola',         color: '#3b82f6' },
            4: { label: 'Zone 4',   edu: 'Bakalaurs',         color: '#8b5cf6' },
            5: { label: 'Zone 5',   edu: 'Maģistrs / PhD',    color: '#ef4444' },
        };

        const AREA_COLOR = {
            'Vadība':         '#6366f1', 'Analītika':     '#3b82f6',
            'Radošums':       '#ec4899', 'Tehnoloģijas':  '#06b6d4',
            'Izglītība':      '#f59e0b', 'Veselība':      '#10b981',
            'Tieslietas':     '#64748b', 'Finanses':      '#8b5cf6',
            'Zinātne':        '#0ea5e9', 'Sociālais':     '#e11d48',
            'Pakalpojumi':    '#f97316', 'Administrācija':'#78716c',
            'Lauksaimniecība':'#84cc16', 'Celtniecība':   '#d97706',
            'Inženierija':    '#0891b2', 'Ražošana':      '#7c3aed',
            'Transports':     '#0f766e', 'Cits':          '#94a3b8',
        };

        // Per-profesijas BIG FIVE + RIASEC skalu attēlojums (vecGrid/dimRow/BF_COLOR/RS_COLOR) NOŅEMTS —
        // bija testa versijai; fināla lietotājam katras profesijas atsevišķās skalas nav jārāda.
        // 'Tavas Vērtības · Big Five + RIASEC' bloks arī NOŅEMTS — dublējās ar 'Personības un Interešu Profils' paneli.

        const profCard = (c, rank) => {
            const color = AREA_COLOR[c.area] || '#94a3b8';
            const bar   = Math.min(c.score, 100);
            // Latviskais profesijas apraksts (occupation_data.csv Description) — PILNS, ne saīsināts (aptinas pa rindām)
            const taskHtml = c.desc
                ? `<div style="font-size:0.6rem; color:#64748b; line-height:1.45; margin-top:3px;">${c.desc}</div>`
                : '';
            return `
            <div style="display:flex; align-items:flex-start; gap:6px; padding:6px 0; border-bottom:1px solid #f1f5f9;">
                <span style="font-size:0.62rem; font-weight:700; color:#cbd5e1; width:16px; text-align:right; flex-shrink:0; padding-top:2px;">${rank}</span>
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:6px; margin-bottom:2px;">
                        <span style="font-size:0.75rem; font-weight:600; color:#1e293b; flex:1; min-width:0; line-height:1.3;">${c.lv}</span>
                        <span style="font-size:0.72rem; font-weight:700; color:${color}; flex-shrink:0;">${c.score}%</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                        <div style="flex:1; background:#e2e8f0; border-radius:2px; height:3px; overflow:hidden;">
                            <div style="width:${bar}%; height:100%; background:${color}; border-radius:2px;"></div>
                        </div>
                        <span style="font-size:0.55rem; color:#94a3b8; flex-shrink:0;">${c.area}</span>
                    </div>
                    ${taskHtml}
                </div>
            </div>`;
        };

        const activeZones = [2, 3, 4, 5].filter(z => zonesData[z]?.length);
        const cols = activeZones.map((z, idx) => {
            const meta  = ZONE_META[z];
            const list  = zonesData[z];
            const borderR = idx < activeZones.length - 1 ? '1px solid #e2e8f0' : 'none';
            return `
            <div style="flex:1 1 0; min-width:0; border-right:${borderR}; padding:0 0.7rem;">
                <div style="padding-bottom:0.6rem; border-bottom:2px solid ${meta.color}; margin-bottom:0.5rem;">
                    <div style="display:flex; align-items:center; justify-content:center; gap:6px; margin-bottom:5px;">
                        <span style="background:${meta.color}; color:white; font-size:0.58rem; font-weight:700; padding:2px 7px; border-radius:10px; white-space:nowrap;">${meta.label}</span>
                        <span style="font-size:0.58rem; color:#94a3b8;">${list.length} prof.</span>
                    </div>
                    <div style="font-size:1.24rem; font-weight:800; color:${meta.color}; text-align:center; line-height:1.2;">${meta.edu}</div>
                </div>
                ${list.map((c, i) => profCard(c, i + 1)).join('')}
            </div>`;
        });

        return `
        <div style="background:white; border-radius:14px; box-shadow:0 2px 8px rgba(0,0,0,0.06); padding:1.1rem 0.9rem 1rem 0.9rem;">
            <h3 style="font-size:0.95rem; color:#1e293b; margin:0 0 0.2rem 0; font-weight:700;">Piemērotās profesijas</h3>
            <div style="font-size:0.78rem; color:#94a3b8; margin:0 0 1rem 0;">Profesijas, kas vislabāk atbilst personības un interešu profilam — sakārtotas pēc nepieciešamā izglītības līmeņa.</div>
            <div style="display:flex; gap:0;">
                ${cols.join('')}
            </div>
            <div style="margin-top:1.1rem; padding-top:0.9rem; border-top:1px solid #f1f5f9; font-size:0.7rem; color:#94a3b8; line-height:1.6;">
                <p style="margin:0 0 0.5rem 0;"><b style="color:#64748b;">Profesiju dati.</b> Profesiju saraksts un to apraksti ir ņemti no O*NET — ASV Darba departamenta plašās profesiju datubāzes, kas apraksta ap 770 profesijas un katrai no tām raksturīgās personības iezīmes un interešu virzienus. Apraksti ir pārtulkoti latviski.</p>
                <p style="margin:0 0 0.5rem 0;"><b style="color:#64748b;">Kā tās tiek izvēlētas.</b> No tavas dzimšanas kartes vispirms tiek aprēķināts tavs personības un interešu profils. Pēc tam katra profesija tiek salīdzināta ar tevi: cik tava profila forma — tavas izteiktākās stiprās puses un intereses — sakrīt ar to, ko šī profesija parasti prasa. Profesijas tiek sakārtotas pēc šīs atbilstības, atsevišķi katrā izglītības līmenī, un katrā līmenī parādītas 20 vispiemērotākās.</p>
                <p style="margin:0;"><b style="color:#64748b;">Ko nozīmē procenti.</b> Atbilstības procents rāda, cik labi tava profila forma saskan ar profesijas tipisko formu — salīdzinājumā ar citiem cilvēkiem un citām profesijām. Tā ir ievirze, kas palīdz pamanīt virzienus, kuros tu varētu justies dabiski, nevis galīgs spriedums par tavām spējām.</p>
            </div>
        </div>`;
    })() : '';

    // Identitātes saturs (hero, Sistēmu Kopsavilkums, Sinerģija) PĀRCELTS uz cilni 'Profils'
    // (render_dashboard.js → Kopsavilkums). Šeit paliek tikai Ieteicamās Profesijas (KO).
    return `
        <div style="padding:0 0 1rem;">
            ${careersPanel}
        </div>`;
}
