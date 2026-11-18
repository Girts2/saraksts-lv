/*!
 * registrs/assets/js/ziedot_forma.js — atbalsta formas ērtības (ziedot.php).
 * saraksts.lv, MIT.
 *
 * Forma strādā arī BEZ šī faila: summa ir radio pogas, maksāšanas veidi ir
 * <button type="submit">, un serveris (lib/ziedot_stripe.php) pārbauda visu
 * pats. Šis fails tikai:
 *   - lauku "Cita summa" rāda tikai tad, kad izvēlēta "Cita" flīze (bez JS — vienmēr);
 *     fiksētas summas izvēle lauku iztīra;
 *   - pasaka "izvēlies summu" uzreiz, bez lapas pārlādes, un aizved fokusu uz summām;
 *   - nelaiž formu nosūtīt divreiz (dubultklikšķis → divas Stripe sesijas);
 *   - IEGULTAIS REŽĪMS (ja formai ir data-pk): maksāšanas veidi kā taisnstūri —
 *     karte (atver kartes laukus un "Atbalstīt ar 10 €" tikai pēc izvēles) un
 *     Stripe Express Checkout (PayPal / Apple Pay / Google Pay), BEZ e-pasta.
 *     Maksājuma laikā summa ir fiksēta. Parastās Stripe pogas pazūd tikai tad,
 *     kad Stripe.js ir ielādējies; ja kartes lauki neielādējas, tās atgriežas.
 *
 * Notikumi mērītājam (ziedot_ga.js), lai tas nebūtu atkarīgs no šī faila iekšienes:
 *   atb:kase    { eur, metode, cita }       — maksājuma sākums iegultajā formā
 *   atb:paldies { eur, metode, cita, id }   — Stripe apstiprināts maksājums
 * Uznirstošajā logā (ziedot.php?logs=1, forma ar data-logs) tos pašus notikumus un
 * lapas augstumu sūta arī vecāklapai (postMessage) — tur tos saņem atbalsta_logs.js.
 * Turklāt: { atb: 'aizvert' } uz Esc (taustiņš ietvarā vecāklapu nesasniedz) un
 * { atb: 'aiznemts', v } maksājuma laikā — tad logs nejauši neaizveras.
 *
 * preventDefault te ir TIKAI tad, kad serveris formu tik un tā atraidītu. Ja
 * šis fails salūzt, forma iet uz serveri neskarta — maksājums no JS neatkarīgs.
 * Apzināti tīrs ES5, tāpat kā ziedot_ga.js.
 */
(function () {
    'use strict';

    // Saite «Iemaksas noteikumi» (uznirstošais logs) ved uz ziedot.php#noteikumi —
    // sakļautais <details> pats neatveras, tāpēc to atver šeit. Pirms formas
    // pārbaudes: noteikumi nav atkarīgi no tā, vai forma ir lapā.
    function atvertNoteikumus() {
        if (location.hash !== '#noteikumi') return;
        var d = document.getElementById('noteikumi');
        if (d && !d.open) d.open = true;
    }
    atvertNoteikumus();
    window.addEventListener('hashchange', atvertNoteikumus);

    var forma = document.getElementById('atb-forma');
    if (!forma) return;

    var MIN = parseFloat(forma.getAttribute('data-min')) || 2;
    var MAX = parseFloat(forma.getAttribute('data-max')) || 1000;
    var cita = forma.querySelector('input[name="cita"]');
    var citaBloks = document.getElementById('atb-cita-bloks');
    var radio = forma.querySelectorAll('input[name="summa"]');
    var zina = document.getElementById('atb-kluda-lok');
    var revolut = forma.querySelector('button[value="revolut"]');
    var pedejaPoga = null;
    var nosutits = false;
    var LOGS = forma.getAttribute('data-logs') === '1' && window.parent !== window;

    /** Uznirstošais logs: ziņa vecāklapai (tikai tai pašai izcelsmei). */
    function vecakam(zina) {
        if (!LOGS) return;
        try { window.parent.postMessage(zina, location.origin); } catch (e) {}
    }

    // Logā vecāklapa iframe augstumu pielāgo saturam. body, ne documentElement.
    // scrollHeight: tas nav mazāks par ietvaru, un logs vairs nesarautos. Maksājuma
    // laikā vismaz 640 px — 3-D Secure logs ir position:fixed ietvara iekšienē, un
    // īsākā ietvarā tas būtu nogriezts.
    var MIN_3DS = 640, maksaGaita = false;
    function zinotAugstumu() {
        var h = Math.ceil(document.body.getBoundingClientRect().height);
        vecakam({ atb: 'augstums', h: maksaGaita ? Math.max(h, MIN_3DS) : h });
    }

    /** Fokuss uz summām: lauks "Cita summa", ja izvēlēta "Cita", citādi pirmā summa. */
    function fokussUzSummu() {
        var rc = radioCita();
        try { (rc && rc.checked && cita ? cita : radio[0]).focus(); } catch (e) {}
    }

    function radioCita() {
        for (var i = 0; i < radio.length; i++) if (radio[i].value === 'cita') return radio[i];
        return null;
    }

    function atzimet() {
        for (var i = 0; i < radio.length; i++) {
            var l = radio[i].parentNode;
            if (!l || !l.classList) continue;
            if (radio[i].checked) l.classList.add('atb-izv'); else l.classList.remove('atb-izv');
        }
        var rc = radioCita();
        if (citaBloks) {
            if (rc && rc.checked) citaBloks.classList.add('atv'); else citaBloks.classList.remove('atv');
        }
    }

    function radit(teksts) {
        if (!zina) return;
        zina.textContent = teksts;
        zina.hidden = false;
    }
    function slept() { if (zina) { zina.hidden = true; zina.textContent = ''; } }

    /** Tā pati loģika, kas serverī (ziedot_summa): ierakstīts "Cita" uzvar. */
    function summa() {
        var t = cita ? String(cita.value).replace(/[\s €]|eur/gi, '').replace(',', '.') : '';
        if (t !== '') {
            if (!/^\d{1,5}(\.\d{1,2})?$/.test(t)) {
                return { kluda: 'Summu neizdevās nolasīt. Ieraksti to ar cipariem, piemēram, 7 vai 7,50.' };
            }
            var v = parseFloat(t);
            if (v < MIN || v > MAX) return { kluda: 'Summai jābūt no ' + MIN + ' līdz ' + MAX + ' €.' };
            return { eur: v, cita: true };
        }
        for (var i = 0; i < radio.length; i++) {
            if (radio[i].checked && radio[i].value !== 'cita') {
                return { eur: parseInt(radio[i].value, 10), cita: false };
            }
        }
        return { kluda: cita ? 'Izvēlies summu vai ieraksti savu laukā «Cita».' : 'Izvēlies summu.' };
    }
    function eurTeksts(v) {
        return (Math.round(v * 100) % 100 === 0 ? String(Math.round(v)) : v.toFixed(2).replace('.', ',')) + '\u00a0€';
    }

    /** Revolut Pro saitēm summa ir fiksēta — der tikai tās summas, kam saite ir. */
    function revolutDer(s) {
        // Tāpat kā serverī: "Cita" ar 10 ir tas pats, kas 10 € flīze.
        if (!revolut || s.kluda) return false;
        var der = (revolut.getAttribute('data-tikai-summas') || '').split(',');
        for (var i = 0; i < der.length; i++) if (parseInt(der[i], 10) === s.eur) return true;
        return false;
    }
    function revolutStavoklis() {
        if (!revolut) return;
        var s = summa();
        // Pirms summas izvēles poga paliek aktīva — cilvēks vēl nav neko nepareizi izdarījis.
        var bloket = !s.kluda && !revolutDer(s);
        if (bloket) revolut.setAttribute('aria-disabled', 'true');
        else revolut.removeAttribute('aria-disabled');
    }

    // Viss, kas jāpārrēķina pēc summas maiņas. Iegultais režīms sevi pievieno šeit.
    var klausitaji = [revolutStavoklis];
    function atjaunot() {
        for (var i = 0; i < klausitaji.length; i++) {
            try { klausitaji[i](); } catch (e) {}
        }
    }
    function notikums(vards, dati) {
        try { document.dispatchEvent(new CustomEvent(vards, { detail: dati })); } catch (e) {}
        vecakam({ atb: vards, dati: dati });
    }

    try {
        forma.classList.add('atb-js');   // CSS: lauks "Cita summa" tikai pēc "Cita" izvēles
        // Lauks redzams tikai pēc "Cita" izvēles, tāpēc rakstīšana nozīmē, ka tā jau izvēlēta.
        if (cita) cita.addEventListener('input', function () { atzimet(); slept(); atjaunot(); });

        // Fokusu uz lauku "Cita summa" — tikai pēc klikšķa/pieskāriena. Ar bultiņām pa
        // summām ejot, fokuss paliek grupā (WCAG 3.2.2); lauks ir nākamais Tab (audits 10-20).
        var citaPele = false;
        var rcFlise = radioCita() && radioCita().parentNode;
        if (rcFlise) rcFlise.addEventListener('pointerdown', function () { citaPele = true; });
        for (var i = 0; i < radio.length; i++) {
            radio[i].addEventListener('change', function () {
                if (this.checked && this.value !== 'cita' && cita) cita.value = '';
                atzimet(); slept(); atjaunot();
                // Pēc atzimet(): lauks līdz tam ir display:none un fokusu nepieņem.
                if (this.checked && this.value === 'cita' && cita && citaPele) cita.focus();
                citaPele = false;
            });
        }

        forma.addEventListener('click', function (e) {
            var p = e.target && e.target.closest ? e.target.closest('button[name="metode"]') : null;
            if (p) pedejaPoga = p;
        });

        forma.addEventListener('submit', function (e) {
            try {
                if (nosutits) { e.preventDefault(); return; }
                var poga = e.submitter || pedejaPoga;
                pedejaPoga = null;   // tikai šim nosūtījumam — vecs klikšķis nedrīkst noteikt nākamo
                // Iegultajā režīmā ar formu drīkst iet tikai REDZAMA poga (Revolut; logā
                // arī PayPal). Enter laukā "Cita" formu nosūtītu ar pirmo — paslēpto —
                // Stripe pogu uz kasi, kas prasa e-pastu. Tā vietā aizvedam līdz kartei.
                if (iegultsAktivs && !(poga && !poga.classList.contains('atb-slepts'))) {
                    e.preventDefault();
                    var mp = document.getElementById('atb-maksat');
                    var kp = document.getElementById('atb-veids-karte');
                    if (mp && mp.offsetParent !== null) mp.focus();
                    else if (kp) kp.focus();
                    return;
                }
                var metode = poga ? poga.value : 'karte';
                var s = summa();
                if (s.kluda) {
                    e.preventDefault();
                    radit(s.kluda);
                    fokussUzSummu();
                    return;
                }
                if (metode === 'revolut' && !revolutDer(s)) {
                    e.preventDefault();
                    radit('Ar Revolut var samaksāt '
                        + (revolut.getAttribute('data-tikai-summas') || '').split(',').join(', ')
                        + ' €. Citai summai izvēlies karti.');
                    return;
                }
                // Logā ziedot_ga.js nav (bez header.php) — kases sākumu ieskaita vecāklapa.
                vecakam({ atb: 'atb:kase', dati: { eur: s.eur, metode: metode, cita: s.cita } });
                // Pogas NEatslēdzam: atslēgta iesniedzēja poga netiktu nosūtīta
                // formas datos (metode pazustu). Pietiek ar karodziņu — un tas pats
                // atslēdzas pēc 10 s, ja pārlūks navigāciju pārtrauca (Esc, tīkls).
                nosutits = true;
                setTimeout(function () { nosutits = false; if (poga) poga.removeAttribute('aria-busy'); }, 10000);
                if (poga) poga.setAttribute('aria-busy', 'true');   // CSS: gaidīšanas izskats
            } catch (err) { /* kļūdas gadījumā forma iet uz serveri, kas pārbauda pats */ }
        });

        // Atpakaļ no Stripe (bfcache) — lapa atjaunojas ar nosutits=true, un forma
        // vairs neietu. pageshow ar persisted to atjauno.
        window.addEventListener('pageshow', function () {
            nosutits = false;
            // Taimeri bfcache laikā stāv — bez šī poga pēc atgriešanās no Stripe
            // vēl līdz 10 s izskatītos pelēka (recenzija 09-22).
            var b = forma.querySelectorAll('[aria-busy]');
            for (var i = 0; i < b.length; i++) b[i].removeAttribute('aria-busy');
        });

        atzimet();
        atjaunot();

        // Logā: augstums pēc satura (kartes lauki un Stripe iframe to maina) un Esc.
        if (LOGS) {
            if (window.ResizeObserver) new ResizeObserver(zinotAugstumu).observe(document.body);
            window.addEventListener('load', zinotAugstumu);
            zinotAugstumu();
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' || e.key === 'Esc') vecakam({ atb: 'aizvert' });
            });
        }
    } catch (e) { /* forma strādā arī bez šī */ }

    // ── Iegultais režīms (Stripe Elements) ───────────────────────────────────
    var iegultsAktivs = false;
    var PK = forma.getAttribute('data-pk');
    var kaste = document.getElementById('atb-iegults');
    if (!PK || !kaste) return;

    var lauki   = document.getElementById('atb-iegults-lauki');
    var kartesPoga = document.getElementById('atb-veids-karte');
    var panelis = document.getElementById('atb-kartes-panelis');
    var maksat  = document.getElementById('atb-maksat');
    var statuss = document.getElementById('atb-statuss');
    var expressKaste = document.getElementById('atb-express');
    var maksatT = maksat ? (maksat.querySelector('.atb-poga-t') || maksat) : null;
    var stripePogas = forma.querySelectorAll('[data-stripe-poga]');
    var tikaiRezerve = forma.querySelectorAll('[data-rezerve]');
    var tikaiIegults = forma.querySelectorAll('[data-iegults-tikai]');
    // Elements prasa summu jau izveidē. Līdz izvēlei — 10 € (maciņu pogas redzamas
    // jau pirmajā skatā); maksāt bez izvēlētas summas tik un tā nevar.
    var SAKUMA_CENTI = 1000;

    /** Rezerves pogas un to paskaidrojumi ↔ iegultās formas paskaidrojumi («bez e-pasta»). */
    function paraditPogas(jaa) {
        var i;
        for (i = 0; i < stripePogas.length; i++) {
            // Logā PayPal paliek rezerves poga (forma target=_blank): Express Checkout PayPal
            // pāradresē uz paypal.com, ko ietvarā rādīt nedrīkst (audits 10-20).
            var palikt = jaa || (LOGS && stripePogas[i].value === 'paypal');
            if (palikt) stripePogas[i].classList.remove('atb-slepts');
            else stripePogas[i].classList.add('atb-slepts');
        }
        for (i = 0; i < tikaiRezerve.length; i++) tikaiRezerve[i].hidden = !jaa;
        for (i = 0; i < tikaiIegults.length; i++) tikaiIegults[i].hidden = jaa;
    }
    /** Atpakaļ uz parastajām pogām — iegultā forma nav izdevusies. */
    function atteikties() {
        iegultsAktivs = false;
        kaste.hidden = true;
        paraditPogas(true);
    }
    /** Tas pats, bet pēc cilvēka darbības: pasaka, kas noticis, un aizved fokusu uz pogām. */
    function uzRezervi(teksts) {
        atteikties();
        radit((teksts ? teikums(teksts) + ' ' : '') + 'Izmanto maksājuma pogu zemāk.');
        var p = forma.querySelector('[data-stripe-poga]');
        if (p) { try { p.focus(); } catch (x) {} }
    }
    // Statusa rinda NAV hidden: aria-live reģions jau jābūt lapā, kad tajā ieraksta
    // tekstu, citādi ekrānlasītājs "Apstrādā…" nenolasa (audits 10-20).
    function rakstit(teksts, klase) {
        if (!statuss) return;
        statuss.textContent = teksts;
        statuss.className = 'atb-statuss' + (klase ? ' ' + klase : '');
    }

    var stripe = null, elKarte = null, elMacins = null, uzstadits = false, aiznemts = false;
    var karteEl = null, kartesMontets = false, kartesGatavs = false;
    var macinsAktivs = false;   // maciņa logs atvērts (PayPal popup nav modāls)
    var samaksats = false;   // pēc veiksmīga maksājuma forma vairs neatveras (nejaušs otrs maksājums)

    /**
     * Maksājums iet: summa fiksēta (iegultsAtjaunot neko nemaina), poga aizņemta, logs
     * nejauši neaizveras un ir pietiekami augsts 3-D Secure logam. aria-disabled, ne
     * disabled: atslēgta poga pazaudē fokusu, un tastatūra sāktu no lapas sākuma.
     */
    function aiznemt(jaa) {
        aiznemts = jaa;
        maksaGaita = jaa;
        if (maksat) {
            if (jaa) maksat.setAttribute('aria-disabled', 'true');
            else maksat.removeAttribute('aria-disabled');
        }
        vecakam({ atb: 'aiznemts', v: jaa });
        zinotAugstumu();
        if (!jaa) iegultsAtjaunot();   // summa varēja mainīties maksājuma laikā
    }

    /** Stripe maciņa nosaukums → tas pats vārds, ko lieto parastās formas pogas (GA). */
    function metodesVards(t) {
        return t === 'apple_pay' ? 'apple' : t === 'google_pay' ? 'google' : (t || 'macins');
    }

    function nodoms(s, metode) {
        var dati = 'darbiba=nodoms&metode=' + encodeURIComponent(metode)
                 + (s.cita ? '&summa=cita&cita=' + encodeURIComponent(String(s.eur))
                           : '&summa=' + encodeURIComponent(String(s.eur)));
        return fetch('/ziedot.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: dati
        }).then(function (r) {
            return r.json().catch(function () { return {}; });
        }).then(function (j) {
            if (j && j.client_secret) return j.client_secret;
            // Servera puses kļūme (429, 403, Stripe API) — iegultā forma šeit vairs
            // nepalīdzēs; ejam uz parastajām pogām (rezerve), ne tikai rādām kļūdu.
            throw { lietotajam: (j && j.teksts) || 'Maksājumu šeit neizdevās sākt.', rezerve: true };
        });
    }
    function atgriezt() { return location.origin + '/ziedot.php?paldies=1' + (LOGS ? '&logs=1' : ''); }

    function pabeigts(pi, s, metode) {
        var ok = pi && pi.status === 'succeeded', apstrade = pi && pi.status === 'processing';
        if (ok || apstrade) {
            samaksats = true;
            aiznemt(false);
            // purchase tikai pēc apstiprinājuma (succeeded), ne "processing".
            if (ok) notikums('atb:paldies', { eur: s.eur, metode: metode, cita: s.cita, id: pi.id });
            // Nekas, ar ko varētu nejauši samaksāt otrreiz: ne lauki, ne maciņi, ne pogas.
            lauki.hidden = true;
            if (expressKaste) expressKaste.hidden = true;
            for (var i = 0; i < stripePogas.length; i++) stripePogas[i].classList.add('atb-slepts');
            if (revolut) revolut.hidden = true;
            rakstit(ok ? 'Paldies! Tava samaksa (' + eurTeksts(s.eur) + ') ir saņemta.'
                       : 'Paldies! Maksājums tiek apstrādāts.', 'atb-ok');
            statuss.setAttribute('tabindex', '-1');
            statuss.focus();
            return;
        }
        aiznemt(false);
        rakstit('Maksājums netika pabeigts. Nauda nav noņemta — vari mēģināt vēlreiz.', 'atb-kl');
    }
    /**
     * Kļūda, ko cilvēks var labot turpat: cita karte, CVC, atcelta 3-D Secure pārbaude
     * — paliekam iegultajā formā. Viss pārējais (servera kļūme, atslēgas, Stripe
     * integrācija) — atpakaļ uz parastajām pogām, lai maksājums tomēr notiek.
     */
    function labojama(e) {
        var t = e && e.stripeTips;
        return t === 'card_error' || t === 'validation_error'
            || (e && e.stripeKods === 'payment_intent_authentication_failure');
    }
    function teikums(t) {
        t = String(t || '').replace(/\s+$/, '');
        return t && !/[.!?…]$/.test(t) ? t + '.' : t;
    }
    function kluda(e) {
        aiznemt(false);
        // Arī tīkla kļūda (fetch TypeError) un nezināmas kļūdas → rezerve.
        if (!labojama(e)) { uzRezervi(e && e.lietotajam); return; }
        rakstit(teikums(e && e.lietotajam) || 'Maksājumu neizdevās pabeigt. Lūdzu, mēģini vēlreiz.', 'atb-kl');
    }
    function stripeKluda(err) {
        return { lietotajam: err && err.message, stripeTips: (err && err.type) || 'nezinams',
                 stripeKods: (err && err.code) || '' };
    }
    /**
     * confirmPayment atgrieza kļūdu, kas nav kartes kļūda (savienojums, API). Nauda
     * TOMĒR var būt noņemta — pirms rezerves pogām pajautājam Stripe, kā ir. Citādi
     * "Izmanto pogu zemāk" aicinātu samaksāt otrreiz (recenzija 09-22).
     */
    function pecApstiprinajuma(cs, err, s, metode) {
        var e = stripeKluda(err);
        if (labojama(e)) throw e;
        return stripe.retrievePaymentIntent(cs).then(function (x) {
            var pi = x && x.paymentIntent;
            if (pi && (pi.status === 'succeeded' || pi.status === 'processing')) { pabeigts(pi, s, metode); return; }
            throw e;
        }, function () { throw e; });
    }

    /** Izveido kartes laukus un maciņus. Maciņus iemontē uzreiz (taisnstūri redzami
     *  pirmajā skatā), kartes laukus — tikai pēc kartes izvēles (atvertKarti). */
    function uzstadit(centi) {
        uzstadits = true;
        // Logā PayPal tikai kā rezerves poga (paraditPogas) — sk. tur.
        var paypal = forma.getAttribute('data-paypal') === '1' && !LOGS;
        var izskats = {
            theme: 'stripe',
            variables: { colorPrimary: '#206a25', colorText: '#140a3f', borderRadius: '8px',
                         fontSizeBase: '16px', spacingUnit: '4px' }
        };
        elKarte = stripe.elements({ mode: 'payment', amount: centi, currency: 'eur', locale: 'lv',
                                    paymentMethodTypes: ['card'], appearance: izskats });
        elMacins = stripe.elements({ mode: 'payment', amount: centi, currency: 'eur', locale: 'lv',
                                     paymentMethodTypes: paypal ? ['card', 'paypal'] : ['card'],
                                     appearance: izskats });

        karteEl = elKarte.create('payment', {
            layout: 'tabs',
            // Maciņi ir atsevišķi taisnstūri. link:'never' — citādi zem kartes
            // parādās Link bloks «Saglabāt manu informāciju» ar e-pastu, tālruni un vārdu
            // (redzēts 2026-10-20), pretēji «Bez reģistrēšanās un bez e-pasta».
            wallets: { applePay: 'never', googlePay: 'never', link: 'never' }
        });
        // Kartes laukus montē tikai pēc cilvēka klikšķa — kļūme tad jāpaskaidro.
        karteEl.on('loaderror', function () { uzRezervi(''); });
        karteEl.on('ready', function () { kartesGatavs = true; });

        // Wikipedia stilā (2026-10-20): balti ar apmali, tikai logo, viena kolonna.
        // Google Pay «white» apmali zīmē pats; Apple Pay vajag «white-outline».
        var ece = elMacins.create('expressCheckout', {
            buttonType: { applePay: 'plain', googlePay: 'plain', paypal: 'paypal' },
            buttonTheme: { applePay: 'white-outline', googlePay: 'white', paypal: 'white' },
            buttonHeight: 50,
            // Bez šī Stripe pēc noklusējuma prasa norēķinu adresi (vārdu + adresi) —
            // pretēji «pa minimumu» un 'click' komentāram zemāk (dokumentācija 2026-10-20).
            billingAddressRequired: false,
            paymentMethods: { applePay: 'auto', googlePay: 'auto', paypal: paypal ? 'auto' : 'never',
                              link: 'never', amazonPay: 'never', klarna: 'never' },
            paymentMethodOrder: ['paypal', 'apple_pay', 'google_pay'],   // kā Wikipedia formā
            // maxRows NEDRĪKST kopā ar overflow:'never' (Stripe dokumentācija) — tas varēja
            // gāzt create() un ar to visu iegulto režīmu uz rezervi.
            layout: { maxColumns: 1, overflow: 'never' }
        });
        // Maciņi neielādējās: PayPal tad ir rezerves poga (Stripe lapa), karte paliek.
        ece.on('loaderror', function () {
            var pp = forma.querySelector('[data-stripe-poga][value="paypal"]');
            if (pp) pp.classList.remove('atb-slepts');
        });
        // E-pastu, vārdu un tālruni NEPRASA — tikai summa un maciņa apstiprinājums.
        // Bez izvēlētas summas (vai kamēr iet cits maksājums) resolve() nesauc: maciņš
        // neatveras, cilvēks redz, ko izvēlēties.
        var macinaSumma = null;
        ece.on('click', function (ev) {
            if (aiznemts || samaksats) return;
            var s = summa();
            if (s.kluda) { radit(s.kluda); fokussUzSummu(); return; }
            // Maksā to summu, kas bija redzama klikšķa brīdī. PayPal logs nav modāls —
            // kamēr tas atvērts, summa lapā var mainīties (audits 10-20).
            macinaSumma = s;
            macinsAktivs = true;
            ev.resolve();
        });
        ece.on('cancel', function () { macinaSumma = null; macinsAktivs = false; iegultsAtjaunot(); });
        ece.on('confirm', function (ev) {
            var s = macinaSumma || summa();
            macinaSumma = null;
            macinsAktivs = false;
            if (s.kluda || samaksats || aiznemts) {
                try { ev.paymentFailed({ reason: 'fail' }); } catch (e) {}
                if (s.kluda) radit(s.kluda);
                return;
            }
            aiznemt(true);
            var metode = ev.expressPaymentType === 'paypal' ? 'paypal' : 'macins';
            var vards = metodesVards(ev.expressPaymentType);
            notikums('atb:kase', { eur: s.eur, metode: vards, cita: s.cita });
            // Stripe prasa elements.submit() PIRMS nodoma izveides (Express Checkout
            // dokumentācija) — bez tā confirmPayment maciņiem kristu (recenzija 09-22).
            var csM = null;
            elMacins.submit().then(function (r) {
                if (r && r.error) throw stripeKluda(r.error);
                return nodoms(s, metode);
            }).then(function (cs) {
                csM = cs;
                return stripe.confirmPayment({ elements: elMacins, clientSecret: cs,
                    confirmParams: { return_url: atgriezt() }, redirect: 'if_required' });
            }).then(function (r) {
                if (r.error) return pecApstiprinajuma(csM, r.error, s, vards);
                pabeigts(r.paymentIntent, s, vards);
            }).catch(function (e) {
                try { ev.paymentFailed({ reason: 'fail' }); } catch (x) {}
                kluda(e);
            });
        });
        ece.mount('#atb-express');
    }

    function iegultsAtjaunot() {
        if (!iegultsAktivs || samaksats) return;
        // Maksājuma laikā summa ir fiksēta: ne pogas teksts, ne Elements nemainās (citādi
        // pogā 20 €, bet maksājums par 10 €). aiznemt(false) pēc tam izsauc šo vēlreiz.
        if (aiznemts) return;
        var s = summa();
        var der = !s.kluda;
        if (!uzstadits) {
            try { uzstadit(der ? Math.round(s.eur * 100) : SAKUMA_CENTI); } catch (e) { atteikties(); return; }
        }
        // Poga paliek aktīva arī bez summas — klikšķis pasaka, kas jāizvēlas.
        maksatT.textContent = der ? 'Atbalstīt ar ' + eurTeksts(s.eur) : 'Atbalstīt';
        if (der) {
            var c = Math.round(s.eur * 100);
            try { elKarte.update({ amount: c }); } catch (e) {}
            // Atvērta maciņa logā summa paliek tā, ar kuru to atvēra (ece 'click').
            if (!macinsAktivs) { try { elMacins.update({ amount: c }); } catch (e) {} }
        }
    }

    function sakt() {
        try {
            stripe = window.Stripe(PK, { locale: 'lv' });
        } catch (e) { return; }   // parastās pogas paliek
        iegultsAktivs = true;
        kaste.hidden = false;
        paraditPogas(false);
        klausitaji.push(iegultsAtjaunot);
        iegultsAtjaunot();

        // Kartes laukus atver tikai tad, kad cilvēks pats izvēlas karti (Ģirta norāde
        // 2026-10-20: CVC prasīšana pirms izvēles rada nepatīkamu sajūtu).
        kartesPoga.addEventListener('click', function () {
            var atvert = panelis.hidden;
            panelis.hidden = !atvert;
            kartesPoga.setAttribute('aria-expanded', String(atvert));
            if (atvert && !kartesMontets && karteEl) {
                kartesMontets = true;
                try { karteEl.mount('#atb-karte'); } catch (e) { uzRezervi(''); }
            }
        });

        maksat.addEventListener('click', function () {
            if (aiznemts || samaksats) return;
            var s = summa();
            // Kļūda ir pie summām (virs kartes laukiem) — fokuss to aizved līdz turienei.
            if (s.kluda) { radit(s.kluda); fokussUzSummu(); return; }
            // Lauki vēl ielādējas: submit() tad kristu ar ne-labojamu kļūdu → rezerve.
            if (!kartesGatavs) { rakstit('Kartes lauki vēl ielādējas — mirkli…', ''); return; }
            aiznemt(true);
            rakstit('Apstrādā…', '');
            var csK = null;
            elKarte.submit().then(function (r) {
                if (r && r.error) throw stripeKluda(r.error);
                notikums('atb:kase', { eur: s.eur, metode: 'karte', cita: s.cita });
                return nodoms(s, 'karte');
            }).then(function (cs) {
                csK = cs;
                return stripe.confirmPayment({ elements: elKarte, clientSecret: cs,
                    confirmParams: { return_url: atgriezt() }, redirect: 'if_required' });
            }).then(function (r) {
                if (r.error) return pecApstiprinajuma(csK, r.error, s, 'karte');
                pabeigts(r.paymentIntent, s, 'karte');
            }).catch(kluda);
        });
    }

    // Stripe.js ielādējas async. Gaidām līdz 10 s; ja nesagaidām — parastās pogas.
    var gaidits = 0;
    (function gaidit() {
        // Kļūda sakt() vidū (piem., ziedot.php un šis fails no dažādām versijām) nedrīkst
        // atstāt paslēptas rezerves pogas bez iegultās formas.
        if (window.Stripe) { try { sakt(); } catch (e) { atteikties(); } return; }
        if ((gaidits += 150) > 10000) return;
        setTimeout(gaidit, 150);
    })();
})();
