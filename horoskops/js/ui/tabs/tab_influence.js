// Ietekmēšanas metodes — tab renderer

const ICONS = { persuasion: '🔑', action: '🚀', defense: '🛡️', timing: '⏱️' };

export function renderTabInfluence(profile) {
    const sections = profile.influence;
    if (!sections?.length) return '<p style="color:#94a3b8;padding:20px">Nav pieejami dati.</p>';

    // Rīcības pāris — divas kolonnas (Dari / Nedari), HR-skenējams formāts.
    const doDont = (it) => `
        <div style="margin-bottom:0.85rem">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;color:${it._labelColor};margin-bottom:5px">${it.label}</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:7px 10px">
                    <div style="font-size:0.64rem;font-weight:800;text-transform:uppercase;letter-spacing:0.3px;color:#15803d;margin-bottom:2px">✓ Dari</div>
                    <div style="font-size:0.85rem;color:#14532d;line-height:1.5">${it.do}</div>
                </div>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:7px 10px">
                    <div style="font-size:0.64rem;font-weight:800;text-transform:uppercase;letter-spacing:0.3px;color:#b91c1c;margin-bottom:2px">✕ Nedari</div>
                    <div style="font-size:0.85rem;color:#7f1d1d;line-height:1.5">${it.dont}</div>
                </div>
            </div>
        </div>`;

    const textRow = (it) => `
        <div style="margin-bottom:0.7rem">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;color:${it._labelColor}">${it.label}</div>
            <div style="font-size:0.9rem;color:#1f2937;line-height:1.6;margin-top:1px">${it.text}</div>
        </div>`;

    const sectionBlock = (sec, i) => {
        const labelColor = sec.colorText || sec.color;
        const summaryBar = sec.summary ? `
            <div style="display:flex;gap:10px;align-items:flex-start;background:${sec.color}14;border-left:3px solid ${sec.color};border-radius:0 8px 8px 0;padding:9px 12px;margin-bottom:14px">
                <span style="font-size:0.64rem;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;color:${labelColor};white-space:nowrap;padding-top:2px">Īsumā</span>
                <span style="font-size:0.9rem;font-weight:600;color:#111827;line-height:1.5">${sec.summary}</span>
            </div>` : '';

        const body = Array.isArray(sec.items)
            ? sec.items.map(it => (it.type === 'dodont' ? doDont({ ...it, _labelColor: labelColor }) : textRow({ ...it, _labelColor: labelColor }))).join('')
            : sec.paragraph
                ? `<p style="margin:0;font-size:0.94rem;color:#1f2937;line-height:1.85">${sec.paragraph}</p>`
                : '';

        return `
        <div style="border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.05)">
            <div style="display:flex;align-items:center;gap:14px;padding:14px 18px;background:linear-gradient(135deg, ${sec.color}14 0%, ${sec.color}05 100%);border-bottom:1px solid ${sec.color}33">
                <div style="flex-shrink:0;width:46px;height:46px;border-radius:12px;background:${sec.color};color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;box-shadow:0 3px 8px ${sec.color}55">${ICONS[sec.id] || ''}</div>
                <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
                    <span style="font-size:1.5rem;font-weight:900;color:${labelColor};line-height:1">${i + 1}</span>
                    <span style="font-size:1.08rem;font-weight:800;color:#111827">${sec.title}</span>
                    <span style="font-size:0.82rem;color:#6b7280">${sec.subtitle}</span>
                </div>
            </div>
            <div style="padding:16px 18px 18px;border-left:4px solid ${sec.color}">
                ${summaryBar}
                ${body}
            </div>
        </div>`;
    };

    return `
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(500px, 1fr));gap:20px;align-items:stretch">
            ${sections.map((sec, i) => sectionBlock(sec, i)).join('')}
        </div>
        <div style="margin-top:16px;padding:10px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;font-size:0.74rem;color:#64748b;line-height:1.55">
            ℹ️ Šie ieteikumi ir <b>personības profila tendences</b>, ne mērījumi vai diagnozes. Izmanto tos kā sarunas sākumpunktu un pārbaudi praksē — ne kā stingru likumu lēmumiem par cilvēku.
        </div>`;
}
