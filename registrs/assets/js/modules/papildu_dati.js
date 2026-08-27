/**
 * papildu_dati.js — "Papildu reģistri un dati" bloka sadaļu pilnā satura ielāde.
 *
 * Lapā sadaļas rāda tikai pirmos ierakstus, un pārējos apzīmē rinda "… un vēl N".
 * Uzņēmumam ar 2 426 iepirkumu līgumiem visu ierakstu iegulšana lapā pievienotu
 * ~360 KB HTML KATRAM apmeklētājam, arī tiem, kas sadaļu nemaz neatver, tāpēc
 * pilno sarakstu ņemam no /{regnr}/sadala/{atslega} tikai pēc klikšķa.
 *
 * Bez JavaScript "… un vēl N" ir parasta saite uz /{regnr}/sadala/{atslega} —
 * pilnais skats atveras kā atsevišķa lapa. Šis modulis saites klikšķi pārtver
 * un ielādē saturu turpat sadaļā, lai lapu nav jāpamet.
 */

/** Vai šī sadaļa jau ir ielādēta pilnā apjomā. */
const ielādētās = new Set();

function pieteiktPogas(sakne) {
    sakne.querySelectorAll('.pd-vairak[data-sadala]').forEach((poga) => {
        if (poga.dataset.piesaistits === '1') return;
        poga.dataset.piesaistits = '1';
        poga.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            ielādēt(poga);
        });
    });
}

async function ielādēt(poga) {
    const sadaļa = poga.closest('.pd-item');
    const saturs = sadaļa ? sadaļa.querySelector('.pd-saturs') : null;
    const reg = sadaļa ? sadaļa.dataset.reg : '';
    const atslēga = poga.dataset.sadala;
    if (!saturs || !reg || !atslēga || ielādētās.has(atslēga)) return;

    if (poga.dataset.ielade === '1') return;   // dubultklikšķis saitei
    poga.dataset.ielade = '1';
    const sākotnējais = poga.textContent;
    if ('disabled' in poga) poga.disabled = true;
    poga.textContent = 'Ielādē…';
    try {
        const atbilde = await fetch(`/${reg}/sadala/${atslēga}`, { headers: { 'Accept': 'text/html' } });
        if (!atbilde.ok) throw new Error(String(atbilde.status));
        const teksts = await atbilde.text();
        if (!teksts.trim()) throw new Error('tukšs');
        saturs.innerHTML = teksts;
        ielādētās.add(atslēga);
        pieteiktPogas(saturs);   // pilnajā skatā pogu vairs nav, bet sargājamies
    } catch (kļūda) {
        delete poga.dataset.ielade;
        if ('disabled' in poga) poga.disabled = false;
        poga.textContent = sākotnējais;
        // Kļūdu rādām lietotājam, nevis tikai konsolē: klusa neveiksme izskatās
        // tāpat kā "nav datu", un tā ir sliktāka par godīgu paziņojumu.
        const p = document.createElement('span');
        p.className = 'pd-muted';
        p.textContent = ' Neizdevās ielādēt — pamēģiniet vēlreiz.';
        poga.insertAdjacentElement('afterend', p);
        setTimeout(() => p.remove(), 6000);
    }
}

export function init() {
    const bloks = document.querySelector('.papildu-facts');
    if (!bloks) return;
    pieteiktPogas(bloks);

    // Atvēršanās virziens (Girta 2026-08-26): pārlūka ritināšanas enkurošana šajā
    // flex `order` izkārtojumā izvēlas enkuru ZEM sadaļas, tāpēc sadaļa vizuāli
    // vērās UZ AUGŠU — lapas apakša palika fiksēta un scrollY palēcās par visu
    // izvērsuma augstumu (mērīts: +391 px). Turam fiksētu klikšķināto summary:
    // pēc <details> toggle (kad izkārtojums un enkurošana jau piemēroti)
    // atjaunojam tā pozīciju skatlogā — sadaļa tad veras uz leju, lapas augša
    // paliek uz vietas. Darbojas arī aizverot un ar tastatūru (Enter/Space uz
    // summary arī raisa click).
    bloks.addEventListener('click', (e) => {
        const kops = e.target && e.target.closest ? e.target.closest('summary') : null;
        if (!kops || !bloks.contains(kops)) return;
        const sadala = kops.parentElement;
        if (!sadala || sadala.tagName !== 'DETAILS') return;
        const pirms = kops.getBoundingClientRect().top;
        sadala.addEventListener('toggle', () => {
            const delta = kops.getBoundingClientRect().top - pirms;
            if (delta) window.scrollBy(0, delta);
        }, { once: true });
    });

    // Drukā sakļauts <details> saturu nerāda vispār (to nevar atvērt ar CSS),
    // tāpēc pirms drukas sadaļas atveram un pēc tam atjaunojam stāvokli.
    // VISUS details, ne tikai .pd-item: sadaļu iekšienē ir savi <details>
    // (piem., tiesiskā "Kā lasīt šos datus"), kas citādi drukā palika sakļauti
    // ar redzamu, bet neatveramu virsrakstu (audits 2026-08-26).
    let atvertas = null;
    window.addEventListener('beforeprint', () => {
        atvertas = [...bloks.querySelectorAll('details')].filter((d) => d.open);
        bloks.querySelectorAll('details').forEach((d) => { d.open = true; });
    });
    window.addEventListener('afterprint', () => {
        if (!atvertas) return;
        bloks.querySelectorAll('details').forEach((d) => { d.open = atvertas.includes(d); });
        atvertas = null;
    });
}
