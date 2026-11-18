/*!
 * registrs/assets/js/ziedot_ga.js — GA4 mērījumi atbalsta ceļam (1. LĪMENIS).
 * Ielādē registrs/ziedot_ga.php ar <script defer>. saraksts.lv, MIT.
 *
 * KĀPĒC ATSEVIŠĶS FAILS, NEVIS INLINE
 * -----------------------------------
 * Līdz 2026-09-08 šis bija inline <script> galvenē, t.i. KATRAS lapas augšā.
 * Auditā izmērīts: ja tāds bloks nonāktu serverī nogriezts (bez </script>),
 * pārlūks visu atlikušo lapu apēstu kā skripta tekstu — /ziedot.php palika 0
 * Stripe pogu, un neviens nevarētu samaksāt. Atsevišķs .js to izslēdz pilnībā:
 * nogriezts .js nogāž tikai sevi, lapas HTML paliek neskarts. defer nozīmē, ka
 * izpilde notiek pēc parsēšanas, tāpēc lapas zīmēšanu tas arī neaiztur.
 *
 * KO ŠIS MĒRA UN KO NE
 * --------------------
 * Parastajā formā maksājums notiek Stripe/Revolut lapā, tāpēc GA4 pašu apmaksu
 * NEREDZ (izņēmums: iegultais režīms — purchase pēc Stripe.js apstiprinājuma).
 *   Stripe dod SKAITĪTĀJU (cik samaksāja) — precīzi un bez zuduma.
 *   GA dod SAUCĒJU (cik ieraudzīja aicinājumu un cik uz tā uzklikšķināja).
 * Konversija ir attiecība starp abiem, un to nevar nolasīt ne no viena paneļa
 * atsevišķi. GA4 piltuvē redzēsi 100 % pamešanu (begin_checkout bez purchase) —
 * tā nav kļūda, tā ir 1. līmeņa robeža.
 *
 * TRĪS NOTIKUMI
 *   select_promotion — klikšķis uz aicinājuma, kas ved uz /ziedot.php
 *                      (josla, izvēlne, kājene, lapas beigu bloks).
 *   begin_checkout   — atbalsta formas nosūtīšana (summa + maksāšanas veids).
 *   add_payment_info — tas pats brīdis, ar payment_type = izvēlētā poga
 *                      (karte|apple|google|paypal|revolut). Kopš 2026-09-22
 *                      forma ir Wikipedia paraugā, ne saites uz Stripe.
 *   purchase         — TIKAI iegultajā režīmā (Stripe Elements lapā): pēc tam,
 *                      kad Stripe.js apstiprina maksājumu (atb:paldies).
 * Pēc veiksmīga Stripe maksājuma cilvēks atgriežas uz /ziedot.php?paldies=1 —
 * tā lapas skatījums ir aptuvens "pabeigts" signāls (Revolut atpakaļ nesūta).
 *
 * Visi parametri ir GA4 STANDARTA parametri (creative_slot, item_list_name,
 * item_variant, payment_type, value, currency), tāpēc atskaitēs tie parādās uzreiz — GA4 administrācijā NAV
 * jāreģistrē pielāgotās dimensijas. Pielāgotais parametrs, ko neviens neatceras
 * reģistrēt, praksē nozīmē datus, kurus nekad neredz.
 *
 * MĒRĪJUMS IR GRĪDA, NE PATIESĪBA. gtag ielādējas tikai pēc sīkdatņu piekrišanas,
 * un nāk klāt reklāmu bloķētāji. Stripe var rādīt VAIRĀK maksājumu nekā GA
 * klikšķu; tas ir normāli, ne kļūda.
 *
 * PERSONAS DATI: uz GA neaiziet ne e-pasts, ne vārds, ne Stripe klienta ID —
 * tikai summa, izvietojuma nosaukums un GA4 pašas savāktā lapas adrese.
 *
 * Apzināti tīrs ES5 (bez const/let/arrow/template virknēm): mērīšana nedrīkst
 * būt iemesls, kāpēc vecākā pārlūkā salūst lapa, kurā notiek maksājums.
 */
(function () {
    'use strict';

    var PROMO_ID  = 'atbalsts';
    var PROMO_NOS = 'Atbalstīt saraksts.lv';
    var AVOTA_ATSLEGA = 'atb_avots';
    // Cik ilgi nepatērēts avots ir spēkā. Bez tā klikšķis uz joslas, kam neseko
    // maksājuma klikšķis, paliktu sessionStorage visu cilnes mūžu (pārlūki sesiju
    // atjauno arī pēc restarta), un tieša ielāde pēc nedēļas skaitītos kā josla.
    var AVOTA_TTL_MS = 30 * 60 * 1000;

    /** Vai lietotājs ir piekritis izsekošanai.
     *
     *  gtag definē TIKAI tas head.php bloks, kam ir type="text/plain"
     *  data-category="tracking" — sīkdatņu rīks (CookieConsent 3.1.0) to izpilda
     *  vienīgi pēc piekrišanas. Tāpēc funkcijas esamība ir uzticama piekrišanas
     *  pazīme, un mums nav jāzina paša sīkdatņu rīka API (kas var mainīties).
     */
    function irPiekritis() {
        try { return typeof window.gtag === 'function'; } catch (e) { return false; }
    }

    function ga(nosaukums, parametri) {
        if (!irPiekritis()) return;
        try {
            window.gtag('event', nosaukums, parametri || {});
        } catch (e) { /* mērīšana nekad nedrīkst salauzt lapu */ }
    }

    // Kurš aicinājums cilvēku atveda uz atbalsta lapu. sessionStorage, nevis URL
    // parametrs: ?no=josla sadalītu /ziedot.php vairākos URL, un tas maksātu SEO
    // signālus par mērījumu, kas tikpat labi tiek bez tā.
    //
    // BEZ PIEKRIŠANAS GLABĀTUVI NEAIZTIEKAM. sessionStorage ir informācijas
    // glabāšana lietotāja iekārtā, un šis ieraksts kalpo tikai analītikas
    // piesaistei — tas nav "tehniski nepieciešams" ePrivātuma direktīvas 5(3)
    // panta izpratnē, tāpēc bez piekrišanas tam tur nav ko darīt.
    function glabatAvotu(vieta) {
        if (!irPiekritis()) return;
        try { sessionStorage.setItem(AVOTA_ATSLEGA, vieta + '|' + Date.now()); } catch (e) {}
    }

    // Avotu PATĒRĒ (izlasa un izdzēš) — citādi cilvēks, kurš reiz atnāca no
    // joslas, pēc nedēļas atgriezies tieši uz /ziedot.php joprojām skaitītos kā
    // joslas nopelns.
    var avots = null;
    function avotaVerts() {
        if (avots !== null) return avots;
        // Nekešojam, kamēr nav piekrišanas: tā var pienākt arī vēlāk tajā pašā
        // lapā, un iekešots 'tiess' tad paliktu uz visu atlikušo apmeklējumu.
        if (!irPiekritis()) return 'tiess';
        try {
            var raw = sessionStorage.getItem(AVOTA_ATSLEGA) || '';
            sessionStorage.removeItem(AVOTA_ATSLEGA);
            var dalas = raw.split('|');
            var laiks = parseInt(dalas[1], 10);
            var svaigs = isNaN(laiks) || (Date.now() - laiks) <= AVOTA_TTL_MS;
            avots = (dalas[0] && svaigs) ? dalas[0] : 'tiess';
        } catch (e) { avots = 'tiess'; }
        return avots;
    }

    /** Vai esam uz pašas atbalsta lapas. Tur izvēlnes un kājenes "Atbalstīt" ved
     *  uz to pašu lapu: klikšķis nav aicinājuma izvēle, un tas pārrakstītu īsto
     *  avotu (josla → izvelne). Josla sevi tur slēpj pati; šie divi — nē. */
    function irAtbalstaLapa() {
        try { return /^\/ziedot\.php$/.test(location.pathname); } catch (e) { return false; }
    }

    // Pēdējā uzklikšķinātā maksāšanas veida poga — rezerve pārlūkiem bez
    // SubmitEvent.submitter (Safari < 15.4).
    var pedejaMetode = null;

    function apstradat(e) {
        var poga = e.target && e.target.closest
                 ? e.target.closest('#atb-forma button[name="metode"]') : null;
        if (poga) { pedejaMetode = poga.value; return; }

        var mrk = e.target && e.target.closest ? e.target.closest('[data-atb]') : null;
        if (!mrk) return;

        // Lapu, no kuras klikšķināts, GA4 pieliek pati (page_location katram
        // notikumam), tāpēc atsevišķs parametrs tam nav vajadzīgs.
        var vieta = mrk.getAttribute('data-atb');
        if (!vieta) return;
        if (irAtbalstaLapa()) return;
        glabatAvotu(vieta);
        // items[] ir OBLIGĀTS, lai notikums nonāktu GA4 Promotions atskaitē. GA4
        // events reference (select_promotion → items: "No*"): bez items masīva
        // "some reports, such as the Promotions report, and metrics won't include
        // this promotion event". creative_slot notikuma līmenī ir tikai rezerve
        // item-dimensijai — bez neviena item nav uz kā to uzlikt, un sadalījums pa
        // izvietojumiem paliktu neredzams (audits 2026-09-09).
        ga('select_promotion', {
            promotion_id: PROMO_ID,
            promotion_name: PROMO_NOS,
            creative_slot: vieta,
            items: [{
                item_id: 'atbalsts',
                item_name: 'Atbalsts saraksts.lv',
                item_category: 'Atbalsts',
                promotion_id: PROMO_ID,
                promotion_name: PROMO_NOS,
                creative_slot: vieta
            }]
        });
    }

    /** Summa no formas tāpat kā serverī: ierakstīts "Cita" uzvar. null = nav derīgas. */
    function formasSumma(forma) {
        var c = forma.querySelector('input[name="cita"]');
        var t = c ? String(c.value).replace(/[\s\u00a0€]|eur/gi, '').replace(',', '.') : '';
        if (t !== '') {
            var v = /^\d{1,5}(\.\d{1,2})?$/.test(t) ? parseFloat(t) : NaN;
            return isNaN(v) ? null : { eur: v, id: 'cita' };
        }
        var r = forma.querySelector('input[name="summa"]:checked');
        if (!r || r.value === 'cita') return null;
        var n = parseInt(r.value, 10);
        return isNaN(n) ? null : { eur: n, id: String(n) };
    }

    function formaNosutita(e) {
        var forma = e.target;
        if (!forma || forma.id !== 'atb-forma') return;
        // Burbuļošanas fāzē: ziedot_forma.js (klausās uz pašas formas) jau ir
        // izlēmis. Atraidīta forma (nav summas) nav kases sākums.
        if (e.defaultPrevented) return;
        var s = formasSumma(forma);
        if (!s) return;
        var metode = (e.submitter && e.submitter.value) || pedejaMetode || 'karte';
        kasesSakums(s.eur, s.id, metode);
    }

    function prece(eur, id, metode) {
        var p = {
            item_id: 'atbalsts-' + id,
            item_name: 'Atbalsts saraksts.lv',
            item_category: 'Atbalsts',
            item_variant: metode,
            item_list_name: avotaVerts(),   // josla|izvelne|kajene|lapas-beigas|tiess
            price: eur,
            quantity: 1,
            // Tas pats avots arī kā promotion dimensija: GA4 promotion dimensijas
            // aizpilda no jebkura e-kom notikuma items, tāpēc Promotions atskaitē
            // "clicked" un "checked out" tad stāv blakus pa izvietojumiem.
            promotion_id: PROMO_ID,
            promotion_name: PROMO_NOS
        };
        p.creative_slot = p.item_list_name;
        return p;
    }

    var pedejaPrece = null;
    function kasesSakums(eur, id, metode) {
        var p = prece(eur, id, metode);
        pedejaPrece = p;
        ga('begin_checkout', { currency: 'EUR', value: eur, items: [p] });
        ga('add_payment_info', { currency: 'EUR', value: eur, payment_type: metode, items: [p] });
    }

    // Iegultā forma (ziedot_forma.js) formu nenosūta — tā ziņo ar saviem notikumiem.
    // atb:paldies nāk TIKAI pēc Stripe apstiprinājuma (status=succeeded), tāpēc
    // tas ir īsts purchase, nevis klikšķis. transaction_id = PaymentIntent id
    // (nav personas dati) — GA4 pēc tā izmet dublikātus.
    function iegultaKase(e) {
        var d = e && e.detail;
        if (!d || !(d.eur > 0)) return;
        kasesSakums(d.eur, d.cita ? 'cita' : String(d.eur), String(d.metode || 'karte'));
    }
    function iegultaisPaldies(e) {
        var d = e && e.detail;
        if (!d || !(d.eur > 0) || !d.id) return;
        var p = pedejaPrece || prece(d.eur, d.cita ? 'cita' : String(d.eur), String(d.metode || 'karte'));
        ga('purchase', { transaction_id: String(d.id), currency: 'EUR', value: d.eur, items: [p] });
    }

    // Notveršanas fāzē: ja kāds cits klausītājs kādreiz izsauktu stopPropagation,
    // burbuļojošs klausītājs klusi pārstātu skaitīt, un to pamanītu tikai pēc
    // mēnešiem, kad skaitļi jau būtu iztulkoti nepareizi. Pilna aizsardzība tā nav —
    // agrāk reģistrēts notveršanas klausītājs ar stopImmediatePropagation apklusinātu
    // arī šo (maksājums arī tad iet cauri; zaudē tikai mērījumu).
    //
    // VISS KLAUSĪTĀJA KORPUSS TRY/CATCH IEKŠPUSĒ. Izmests izņēmums navigāciju
    // neatceļ (pārbaudīts auditā), bet nepiegružo lietotāja konsoli un neaizķer
    // citus kļūdu pārtvērējus, kas lapā var būt.
    try {
        document.addEventListener('click', function (e) {
            try { apstradat(e); } catch (err) { /* mērīšana nedrīkst traucēt klikšķi */ }
        }, true);
        // Nosūtīšana — BURBUĻOŠANAS fāzē (skat. formaNosutita). Nosūtīšanu tas
        // neaiztur: gtag sūta ar sendBeacon, un šis fails nosūtīšanu nekad neaptur.
        document.addEventListener('submit', function (e) {
            try { formaNosutita(e); } catch (err) { /* mērīšana nedrīkst traucēt maksājumu */ }
        }, false);
        document.addEventListener('atb:kase', function (e) {
            try { iegultaKase(e); } catch (err) {}
        }, false);
        document.addEventListener('atb:paldies', function (e) {
            try { iegultaisPaldies(e); } catch (err) {}
        }, false);
    } catch (e) { /* ja pat klausītāju nevar reģistrēt, lapa strādā bez mērījuma */ }
})();
