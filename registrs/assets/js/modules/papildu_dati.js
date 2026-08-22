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

    // Drukā sakļauts <details> saturu nerāda vispār (to nevar atvērt ar CSS),
    // tāpēc pirms drukas visas sadaļas atveram un pēc tam atjaunojam stāvokli.
    let atvertas = null;
    window.addEventListener('beforeprint', () => {
        atvertas = [...bloks.querySelectorAll('details.pd-item')].filter((d) => d.open);
        bloks.querySelectorAll('details.pd-item').forEach((d) => { d.open = true; });
    });
    window.addEventListener('afterprint', () => {
        if (!atvertas) return;
        bloks.querySelectorAll('details.pd-item').forEach((d) => { d.open = atvertas.includes(d); });
        atvertas = null;
    });
}
