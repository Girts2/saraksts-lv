import { renderHackerPanel } from '../sections/hacker_panel.js?v=7';

export function renderTabMotivacija(profile) {
    // Tumšā virsraksta josla "Motivācija & Darba Sviras" NOŅEMTA (2026-06-11) —
    // dublēja paneļa paša galvu "🕹️ Motivācija un vadības sviras" un bija tumšs elements gaišajā zonā.
    return `
        <div style="max-width:1286px; margin:0 auto; padding-bottom:2rem;">
            ${renderHackerPanel(profile)}
        </div>`;
}
