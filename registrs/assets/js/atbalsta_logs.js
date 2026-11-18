/*!
 * registrs/assets/js/atbalsta_logs.js — atbalsta uznirstošais logs (MI atbildes).
 * saraksts.lv, MIT.
 *
 * Saites ar data-atb-logs (MI atbilžu beigās, ai_panel.php) atver logu ar
 * ziedot.php?logs=1 ietvarā — tā pati forma un maksājuma loģika, kas atbalsta lapā,
 * tikai bez lapas rāmja. Logs ir virs lapas, tāpēc MI atbilžu izkārtojums nemainās.
 * data-atb-logs="" — summu izvēlas logā (MI atbildēs tā, Ģirta norāde 2026-10-20:
 * lūgums, ne prasījums); "5|10|20" — ar jau izvēlētu summu.
 * Aizvērt: × augšējā labajā stūrī, Esc (arī no ietvara iekšienes) vai klikšķis ārpus
 * loga. Kamēr maksājums iet, Esc un fona klikšķis logu neaizver — tikai ×.
 *
 * Saites href ved uz atbalsta lapu — ja šis fails neielādējas vai pārlūkam nav
 * <dialog>, cilvēks nonāk parastajā atbalsta lapā.
 *
 * No ietvara (ziedot_forma.js, postMessage, tikai tā pati izcelsme):
 *   { atb: 'augstums', h }                   — ietvara augstums pēc satura;
 *   { atb: 'aizvert' }                       — Esc ietvarā (taustiņš šeit nenonāk);
 *   { atb: 'aiznemts', v }                   — maksājums iet / beidzies;
 *   { atb: 'atb:kase' | 'atb:paldies', dati } — pārsūta kā CustomEvent šim dokumentam,
 *                                              kur tos saņem ziedot_ga.js (piekrišana ir te).
 * Apzināti tīrs ES5, tāpat kā ziedot_forma.js.
 */
(function () {
    'use strict';

    // Tās pašas summas, kas lib/ziedot_stripe.php ZIEDOT_SUMMAS (serveris pārbauda pats).
    var SUMMAS = ['5', '10', '20'];
    var logs = null, ietvars = null, aiznemts = false;

    var STILS =
        '.atbl{width:min(480px,calc(100vw - 24px));max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px);' +
        'padding:0;border:0;border-radius:14px;box-shadow:0 20px 60px rgba(20,10,63,.35);overflow:auto;' +
        'background:#fff;color:#140a3f}' +
        '.atbl::backdrop{background:rgba(20,10,63,.45)}' +
        '.atbl-galva{position:sticky;top:0;z-index:1;display:flex;align-items:center;justify-content:space-between;' +
        'gap:8px;padding:8px 8px 0 20px;background:#fff}' +
        '.atbl-galva h2{margin:0;font-size:17px;line-height:1.3;color:#140a3f}' +
        '.atbl-x{flex:none;width:44px;height:44px;border:0;border-radius:8px;background:none;cursor:pointer;' +
        'font-size:28px;line-height:1;color:#5a5f7a}' +
        '.atbl-x:hover{background:#f0f2f8}.atbl-x:focus-visible{outline:3px solid #140a3f;outline-offset:2px}' +
        '.atbl-ifr{display:block;width:100%;height:440px;border:0}' +
        // scrollbar-gutter: bez tā, pazūdot ritjoslai, lapa aiz loga paplašinās un raustās.
        'html.atbl-atverts{overflow:hidden;scrollbar-gutter:stable}';

    function izveidot() {
        var st = document.createElement('style');
        st.textContent = STILS;
        document.head.appendChild(st);

        logs = document.createElement('dialog');
        logs.className = 'atbl';
        logs.setAttribute('aria-labelledby', 'atbl-virsraksts');
        logs.innerHTML =
            '<div class="atbl-galva"><h2 id="atbl-virsraksts">Atbalsti saturu</h2>' +
            '<button type="button" class="atbl-x" aria-label="Aizvērt">×</button></div>';
        document.body.appendChild(logs);

        logs.querySelector('.atbl-x').addEventListener('click', aizvert);   // × aizver vienmēr
        // Klikšķis uz fona trāpa pašam <dialog> elementam, ne tā saturam.
        logs.addEventListener('click', function (e) { if (e.target === logs && !aiznemts) aizvert(); });
        // Esc, kad fokuss ir vecāklapā (piem., uz ×): maksājuma laikā neaizveram.
        logs.addEventListener('cancel', function (e) { if (aiznemts) e.preventDefault(); });
        // 'close' notiek arī pēc Esc — tāpēc tīrīšana te, ne aizvert().
        logs.addEventListener('close', function () {
            document.documentElement.classList.remove('atbl-atverts');
            aiznemts = false;
            // Ietvaru izņem pavisam (aptur Stripe). src maiņa uz about:blank pievienotu
            // ierakstu pārlūka vēsturē, un «Atpakaļ» sāktu staigāt pa logu (audits 10-20).
            if (ietvars && ietvars.parentNode) ietvars.parentNode.removeChild(ietvars);
            ietvars = null;
        });
    }

    function aizvert() { if (logs && logs.open) logs.close(); }

    /** false — logu atvērt nevar (vecs pārlūks); tad lai strādā saites href. */
    function atvert(summa) {
        if (!window.HTMLDialogElement) return false;
        if (!logs) izveidot();
        // Katru reizi jauns ietvars: pirmā ielāde jaunā ietvarā vēsturē ierakstu nepievieno.
        ietvars = document.createElement('iframe');
        ietvars.className = 'atbl-ifr';
        ietvars.title = 'Samaksas forma';
        ietvars.setAttribute('allow', 'payment');   // Apple Pay / Google Pay ietvarā (tā pati izcelsme)
        ietvars.src = '/ziedot.php?logs=1' + (summa ? '&summa=' + encodeURIComponent(summa) : '');
        logs.appendChild(ietvars);
        aiznemts = false;
        document.documentElement.classList.add('atbl-atverts');
        logs.showModal();
        return true;
    }

    document.addEventListener('click', function (e) {
        // Ar Cmd/Ctrl/Shift vai vidējo pogu — saite jaunā cilnē, kā parasti.
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var p = e.target && e.target.closest ? e.target.closest('[data-atb-logs]') : null;
        if (!p) return;
        var s = p.getAttribute('data-atb-logs') || '';
        if (s !== '' && SUMMAS.indexOf(s) === -1) return;
        try { if (atvert(s)) e.preventDefault(); } catch (x) { /* saite ved uz atbalsta lapu */ }
    });

    window.addEventListener('message', function (e) {
        if (!ietvars || e.source !== ietvars.contentWindow || e.origin !== location.origin) return;
        var d = e.data || {};
        if (d.atb === 'augstums' && d.h > 0) {
            ietvars.style.height = Math.min(Math.max(d.h, 200), 2000) + 'px';
        } else if (d.atb === 'aizvert') {
            if (!aiznemts) aizvert();
        } else if (d.atb === 'aiznemts') {
            aiznemts = d.v === true;
        } else if (d.atb === 'atb:kase' || d.atb === 'atb:paldies') {
            try { document.dispatchEvent(new CustomEvent(d.atb, { detail: d.dati || {} })); } catch (x) {}
        }
    });
})();
