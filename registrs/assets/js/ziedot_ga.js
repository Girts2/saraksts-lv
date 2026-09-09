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
 * Maksājums notiek uz buy.stripe.com, tāpēc GA4 pašu apmaksu NEREDZ.
 *   Stripe dod SKAITĪTĀJU (cik samaksāja) — precīzi un bez zuduma.
 *   GA dod SAUCĒJU (cik ieraudzīja aicinājumu un cik uz tā uzklikšķināja).
 * Konversija ir attiecība starp abiem, un to nevar nolasīt ne no viena paneļa
 * atsevišķi. GA4 piltuvē redzēsi 100 % pamešanu (begin_checkout bez purchase) —
 * tā nav kļūda, tā ir 1. līmeņa robeža.
 *
 * DIVI NOTIKUMI
 *   select_promotion — klikšķis uz aicinājuma, kas ved uz /ziedot.php
 *                      (josla, izvēlne, kājene, lapas beigu bloks).
 *   begin_checkout   — klikšķis uz Stripe saites atbalsta lapā.
 *
 * Visi parametri ir GA4 STANDARTA parametri (creative_slot, item_list_name,
 * value, currency), tāpēc atskaitēs tie parādās uzreiz — GA4 administrācijā NAV
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

    function apstradat(e) {
        var mrk = e.target && e.target.closest
                ? e.target.closest('[data-atb],[data-atb-summa]') : null;
        if (!mrk) return;

        // Lapa netiek izlādēta uzreiz (Stripe saites atveras jaunā cilnē), bet arī
        // pārējām gtag lieto sendBeacon, tāpēc event_callback aizture nav vajadzīga.
        // Aizture te būtu tieši nevēlama: tā liktu navigācijai gaidīt uz GA.
        var summa = mrk.getAttribute('data-atb-summa');
        if (summa !== null) {
            var eur = parseInt(summa, 10);
            var fiksets = !isNaN(eur) && eur > 0;   // "cita" → brīvi izvēlama summa
            var prece = {
                item_id: 'atbalsts-' + (fiksets ? eur : 'cita'),
                item_name: 'Atbalsts saraksts.lv',
                item_category: 'Atbalsts',
                item_list_name: avotaVerts(),   // josla|izvelne|kajene|lapas-beigas|tiess
                quantity: 1
            };
            // Tas pats avots arī kā promotion dimensija: GA4 promotion dimensijas
            // aizpilda no jebkura e-kom notikuma items, tāpēc Promotions atskaitē
            // "clicked" un "checked out" tad stāv blakus pa izvietojumiem.
            prece.promotion_id = PROMO_ID;
            prece.promotion_name = PROMO_NOS;
            prece.creative_slot = prece.item_list_name;
            var dati = { items: [prece] };
            if (fiksets) {
                // Brīvi izvēlamai summai value APZINĀTI nav: nulle atskaitē izskatītos
                // kā ziedojums par 0 €, un vidējais čeks kļūtu par meliem. Bez value
                // šie klikšķi skaitās notikumos, bet naudas skaitli neietekmē.
                dati.currency = 'EUR';
                dati.value = eur;
                prece.price = eur;
            }
            ga('begin_checkout', dati);
            return;
        }

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
    } catch (e) { /* ja pat klausītāju nevar reģistrēt, lapa strādā bez mērījuma */ }
})();
