// Q5 — "Partneru saderība" — divu cilvēku Vēdiskā Ashta Kuta saderība
// (Uzticamības & Atbildības audits pārcelts uz cilni 'Profils' — render_dashboard.js)

import { renderTabCompatibility } from './tab_compatibility.js?v=56';

export function renderTabQ5Relate(profile, profile2) {
    const compatHtml = (() => {
        try   { return renderTabCompatibility(profile, profile2); }
        catch (e) { return `<div style="color:red;padding:2rem"><b>Saderība kļūda:</b> ${e.message}</div>`; }
    })();

    return `
    <div class="q-tab q-tab--relate">

        <!-- Saderība -->
        <div style="margin-bottom:2rem;">
            ${compatHtml}
        </div>

    </div>`;
}
