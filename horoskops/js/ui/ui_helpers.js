export const makeBar = (score, label, desc, systemId = "") => {
    let color = score >= 70 ? '#16a34a' : score >= 40 ? '#ca8a04' : '#dc2626';
    return '<div style="background:white; padding:1rem; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">' +
        '<div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:0.4rem;">' +
            '<div style="font-size:0.85rem; color:#475569; font-weight:bold; text-transform:uppercase;">' + label + '</div>' +
            '<div style="font-size:1rem; font-weight:900; color:' + color + ';">' + score + '%</div>' +
        '</div>' +
        '<div style="width:100%; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden; margin-bottom:0.5rem;">' +
            '<div style="width:' + score + '%; height:100%; background:' + color + '; transition: width 1s ease-in-out;"></div>' +
        '</div>' +
        '<div style="font-size:0.8rem; color:#64748b; line-height:1.3; margin-bottom:0.5rem;">' + desc + '</div>' +
        (systemId ? `<div id="sparkline-uzticamiba-${systemId}" class="sparkline-container" style="width:100%; height:80px; margin-top:16px; background:rgba(0,0,0,0.02); border-radius:4px; position:relative; border-bottom:1px solid rgba(0,0,0,0.05); box-sizing:border-box; display:flex; flex-direction:column;"></div>` : '') +
    '</div>';
};

export const makeSection = (title, contentHtml) => {
    return '<div style="margin-top:2rem; padding-top:1.5rem; border-top:2px dashed #c7d2fe;">' +
        '<h4 style="color:#4f46e5; margin-bottom:1rem; font-size:1.1rem;">' + title + '</h4>' +
        '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">' +
        contentHtml +
        '</div></div>';
};
