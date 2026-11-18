<?php
/**
 * lib/ziedot_stripe.php — atbalsta formas maksājuma ceļš (ziedot.php).
 *
 * Forma atbalsta lapā ir Wikipedia paraugā: summa (5 / 10 / 20 € vai sava) un
 * maksāšanas veida poga. Poga nosūta formu uz to pašu ziedot.php (POST), un šis
 * fails izlemj, kur cilvēku aizsūtīt:
 *
 *   1. Stripe Checkout sesija caur API — ar tieši izvēlēto summu un TIKAI ar
 *      izvēlēto maksāšanas veidu (poga "PayPal" atver PayPal, ne visu sarakstu).
 *      Vajag Stripe ierobežoto atslēgu privātajā failā (skat. zemāk).
 *   2. REZERVE: esošās Stripe Payment Links. Ja atslēgas nav vai API neatbild,
 *      cilvēks tik un tā nonāk kasē — ar fiksēto summu vai ar brīvi ievadāmu.
 *      Maksājums nedrīkst salūzt tāpēc, ka salūzis API izsaukums (Ģirta prasība
 *      2026-09-08: "Nevēlos, lai kļūdas gadījumā cilvēki nevar samaksāt").
 *   0. IEGULTAIS REŽĪMS (ja privātajā failā ir arī publiskā atslēga pk_…):
 *      Stripe Payment Element + Express Checkout Element tieši mūsu lapā —
 *      Apple Pay / Google Pay ar vienu pieskārienu, kartei numurs, termiņš, CVC un
 *      valsts (to prasa Stripe). E-PASTU NEPRASA (Ģirta norāde 2026-09-22: "pa minimumu"): Stripe
 *      mitinātā kase e-pastu prasa vienmēr, iegultā — nē. Serveris tikai izveido
 *      PaymentIntent (ziedot_nodoms); maksājumu apstiprina Stripe.js pārlūkā.
 *      Ja Stripe.js neielādējas, lapā paliek parastās pogas (1. un 2. ceļš).
 *   3. Revolut — TIKAI īstas Revolut maksājumu saites, ja tās ir ieliktas
 *      privātajā failā. Revolut logo pie Stripe maksājuma neliekam (Ģirta
 *      norāde 2026-09-22: "Ja Revolut, tad Revolut"). Revolut Pro saitei summa
 *      ir fiksēta, tāpēc Revolut der tikai 5 / 10 / 20 €, ne "Cita".
 *
 * PRIVĀTAIS FAILS (ārpus docroot, tāpēc to neaiztiek ne rsync, ne pakotne):
 *     <docroot>/../privats/ziedot.php
 *   lokāli:   _Optimizacija/privats/ziedot.php
 *   serverī:  domains/saraksts.lv/privats/ziedot.php
 * Saturs:
 *     <?php return [
 *         'stripe_atslega' => 'rk_live_…',   // ierobežotā: Checkout Sessions + PaymentIntents = Write
 *         'stripe_publiska'=> 'pk_live_…',   // ieslēdz iegulto režīmu (tā pati vide: live/test)
 *         'paypal'         => true,          // tikai pēc tam, kad PayPal savienots ar Stripe
 *         'revolut'        => [5 => 'https://…', 10 => 'https://…', 20 => 'https://…'],
 *     ];
 * Atslēgu var dot arī vides mainīgajā ZIEDOT_STRIPE_ATSLEGA.
 *
 * Revolut saites var likt arī ATSEVIŠĶĀ failā blakus — privats/ziedot_saites.php
 * (<?php return ['revolut' => [5 => …, 10 => …, 20 => …]];). Tās nav noslēpums
 * (lapā redzamas visiem), tāpēc tās var mainīt, neatverot failu ar atslēgām.
 *
 * KĀPĒC ĀRPUS DOCROOT: darba koku dala vairākas sesijas, un kāda cita rsync ar
 * plašu filtru (*.php) reiz jau izvietoja svešas nepabeigtas izmaiņas (2026-09-09).
 * Docroot iekšā lokālā testa atslēga tā varētu aizstāt produkcijas atslēgu.
 *
 * Payment Link adreses te glabājas tāpat kā līdz šim ziedot.php augšā — tās
 * nav noslēpums (atveras ikvienam, kas nospiež pogu), un pakotnē tās nomaina skrubis
 * (tools/build_download.secrets.php). Tās paliek kodā APZINĀTI: ja privātais
 * fails kādreiz pazūd, rezerves ceļš joprojām strādā.
 */
declare(strict_types=1);

// reg_test_env() — lai lokālajā vidē Stripe atgrieztu uz localhost, ne produkciju.
if (!function_exists('reg_test_env') && is_file(__DIR__ . '/test_env.php')) {
    require_once __DIR__ . '/test_env.php';
}

// ─── Stripe Payment Links (rezerves ceļš) ────────────────────────────────────
// PUBLISKAJĀ IZLAIDUMĀ ŠĪS VĒRTĪBAS IR NOŅEMTAS. Ieliec savas saites no
// dashboard.stripe.com → Payments → Payment Links. Ja atstāj tukšu, forma
// darbojas tikai ar API atslēgu.
//
// Saite ar brīvi izvēlamu summu ("Customers choose what to pay"): rezerve
// summai "Cita" un katrai summai, kurai nav savas saites.
// 2026-10-28: vecā donate.stripe.com saite (Stripe ziedojuma tips, poga «Ziedot») aizstāta ar
// jaunu: «Customers choose what to pay», poga «Pay», produkts «Samaksa par saturu —
// saraksts.lv», robežas 2–1000 € (tāpat kā ZIEDOT_MIN/MAX_CENTI), bez iepriekš ieliktas summas.
// Ja tā kādreiz ir tukša un API atslēgas nav, forma «Cita» un summas bez savas saites (20 €)
// NERĀDA (ziedot_pieejamas_summas) — cilvēks netiek aizvests līdz strupceļam «nav_saites».
const ZIEDOT_STRIPE_SAITE = '';

// Saites ar iepriekš iestatītu summu. Stripe Payment Links summu caur URL
// parametru nepieņem (pārbaudīts 2026-08-31), tāpēc katrai summai sava saite.
// 20 € saites vēl nav — līdz tai 20 € rezervē iet caur brīvās summas saiti.
const ZIEDOT_SUMMU_SAITES = [
    10 => '',
    5  => '',
];
// ─────────────────────────────────────────────────────────────────────────────

/** Formā piedāvātās summas (eiro), rādīšanas secībā. */
const ZIEDOT_SUMMAS = [5, 10, 20];

/** "Cita" summas robežas centos. Apakšējā: zem ~2 € Stripe komisija apēd lielu
 *  daļu, un sīkas summas ir karšu testētāju iecienītākās. Augšējā: pārrakstīšanās
 *  sargs (5000 vietā 50), ne aizliegums — lielākam atbalstam var atkārtot. */
const ZIEDOT_MIN_CENTI = 200;
const ZIEDOT_MAX_CENTI = 100000;

/** Maksāšanas veidi, ko forma var atsūtīt. apple/google ir kartes sesija —
 *  Stripe Checkout maciņu pogas rāda pats ierīcēs, kur tās strādā. */
const ZIEDOT_METODES = ['karte', 'apple', 'google', 'paypal', 'revolut'];

/** Juridiskais teikums pie maksāšanas pogas (Stripe custom_text) un uznirstošajā logā.
 *  Uz pretizpildījuma TRŪKUMA stāv PVN pozīcija (EST Tolsma C-16/93: summu izvēlas
 *  maksātājs, un saturs nav atkarīgs no samaksas), tāpēc abi fakti teikumā paliek —
 *  bez vārdiem «nevis pirkums» (audits 2026-10-28: blakus «samaksa par saturu» pretruna).
 *  «Samaksa par saturu», ne «ziedojums» vai «atbalsta iemaksa» (2026-10-28): ziedojumus
 *  saņem sabiedriskā labuma (SLOL 9. p. 1. d.) u. c. organizācijas, nevis komersants, un
 *  Stripe tiešu ziedojumu vākšanu nepieņem — pieņem satura veidotāja samaksu par saturu.
 *  Pilnie noteikumi — ziedot.php #noteikumi. */
const ZIEDOT_JURIDISKI = 'Šī ir brīvprātīga samaksa par saturu: saturs ir pieejams visiem arī '
                       . 'bez tās, summu izvēlas maksātājs, un samaksa nedod papildu priekšrocības.';

/** Maksājuma nosaukums Stripe kasē, čekā un panelī — tas pats vārds, kas lapā.
 *  Payment Links produktiem panelī jābūt tieši tādam pašam nosaukumam. */
const ZIEDOT_NOSAUKUMS = 'Samaksa par saturu — saraksts.lv';

/** Privātā faila ceļš. */
function ziedot_privatais_fails(): string {
    return dirname(__DIR__, 2) . '/privats/ziedot.php';
}

/**
 * Konfigurācija no privātā faila (+ vides mainīgā). Vienmēr atgriež pilnu masīvu
 * ar drošām noklusējuma vērtībām — trūkstošs vai bojāts fails nozīmē "nav API,
 * nav PayPal, nav Revolut", un forma strādā caur Payment Links.
 */
function ziedot_konfig(): array {
    static $k = null;
    if ($k !== null) return $k;

    $c = [];
    $f = ziedot_privatais_fails();
    if (is_file($f)) {
        try {
            $x = include $f;
            if (is_array($x)) $c = $x;
        } catch (Throwable $e) { /* bojāts fails = kā tāda nebūtu */ }
    }
    // Nenoslēpumu fails: no tā ņemam TIKAI saites, nekad atslēgas.
    $fs = dirname(__DIR__, 2) . '/privats/ziedot_saites.php';
    if (is_file($fs)) {
        try {
            $x = include $fs;
            if (is_array($x) && isset($x['revolut']) && is_array($x['revolut'])) $c['revolut'] = $x['revolut'];
        } catch (Throwable $e) { /* bojāts fails = kā tāda nebūtu */ }
    }
    $env = getenv('ZIEDOT_STRIPE_ATSLEGA');
    if (is_string($env) && $env !== '') $c['stripe_atslega'] = $env;
    return $k = ziedot_konfig_tirit($c);
}

/** Konfigurācijas pārbaude un tīrīšana (atsevišķi — lai to var testēt bez faila). */
function ziedot_konfig_tirit(array $c): array {
    $k = array_merge(['stripe_atslega' => '', 'stripe_publiska' => '', 'paypal' => false,
                      'revolut' => []], $c);

    // Tikai Stripe slepenās vai ierobežotās atslēgas forma; publiskā (pk_) te
    // neder, un nejauši ielikts cits teksts nedrīkst nonākt Authorization galvenē.
    $a = is_string($k['stripe_atslega']) ? trim($k['stripe_atslega']) : '';
    $k['stripe_atslega'] = preg_match('/^(rk|sk)_(live|test)_[A-Za-z0-9]+$/', $a) ? $a : '';
    $k['paypal'] = $k['paypal'] === true && $k['stripe_atslega'] !== '';

    // Iegultajam režīmam vajag abas atslēgas NO VIENAS VIDES: pk_test ar rk_live
    // dotu PaymentIntent, ko pārlūks nevar apstiprināt — forma "strādātu" līdz
    // pēdējam klikšķim un tad kristu.
    $p = is_string($k['stripe_publiska']) ? trim($k['stripe_publiska']) : '';
    $vide = fn(string $x): string => preg_match('/^(?:rk|sk|pk)_(live|test)_/', $x, $m) ? $m[1] : '';
    $k['stripe_publiska'] = (preg_match('/^pk_(live|test)_[A-Za-z0-9]+$/', $p)
                             && $k['stripe_atslega'] !== '' && $vide($p) === $vide($k['stripe_atslega'])) ? $p : '';
    $k['iegults'] = $k['stripe_publiska'] !== '';

    $rev = [];
    foreach ((array)$k['revolut'] as $eur => $url) {
        if (!in_array($eur, ZIEDOT_SUMMAS, true) || !is_string($url)) continue;
        $url = trim($url);
        if (preg_match('~^https://([a-z0-9-]+\.)*revolut\.(com|me)/~i', $url)) $rev[$eur] = $url;
    }
    $k['revolut'] = $rev;
    return $k;
}

/** Formas lauks kā teksts. summa[]=… u.c. masīvi → '' (citādi PHP brīdinājums žurnālā). */
function ziedot_lauks(array $a, string $k): string {
    $v = $a[$k] ?? '';
    return is_string($v) ? $v : '';
}

/**
 * Summa no formas → centi, vai kļūdas kods.
 *
 * Ja "Cita" laukā ir ierakstīts skaitlis, uzvar TAS: ievadīt savu summu ir
 * apzināta darbība. Ar JS klikšķis uz fiksētas summas lauku iztīra, tāpēc
 * pretruna rodas tikai bez JS, un tad pēdējais apzinātais solis ir ieraksts.
 *
 * @return array{0:?int,1:string} [centi|null, kļūdas kods|'']
 */
function ziedot_summa(array $post): array {
    $cita  = trim(ziedot_lauks($post, 'cita'));
    $summa = ziedot_lauks($post, 'summa');

    if ($cita !== '') {
        // Visas atstarpes (arī nedalāmā un šaurā), € un EUR jebkādā reģistrā — tāpat
        // kā pārlūkā (ziedot_forma.js), citādi "5 Eur" pārlūkā derētu, serverī ne.
        $t = preg_replace('/[\s\x{00A0}\x{202F}\x{2009}€]|eur/iu', '', $cita) ?? '';
        $t = str_replace(',', '.', $t);
        if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $t)) return [null, 'summa_forma'];
        $c = (int)round((float)$t * 100);
        if ($c < ZIEDOT_MIN_CENTI || $c > ZIEDOT_MAX_CENTI) return [null, 'summa_robezas'];
        return [$c, ''];
    }
    if (ctype_digit($summa) && in_array((int)$summa, ZIEDOT_SUMMAS, true)) {
        return [(int)$summa * 100, ''];
    }
    return [null, 'summa'];
}

/**
 * Summas, ko forma drīkst piedāvāt: tikai tās, kurām ir ceļš līdz kasei.
 * Ar API atslēgu (Checkout Session / iegultais režīms) — visas un «Cita». Bez tās —
 * summas ar savu Payment Link; pārējās un «Cita» tikai tad, ja ir brīvās summas saite.
 * Pārējo dara ziedot_apstradat (ar to pašu loģiku), tāpēc piedāvātais vienmēr nonāk kasē.
 *
 * @return array{0:int[],1:bool} [summas eiro rādīšanas secībā, vai rādīt «Cita»]
 */
function ziedot_pieejamas_summas(array $konf, string $briva = ZIEDOT_STRIPE_SAITE,
                                 array $saites = ZIEDOT_SUMMU_SAITES): array {
    if ($konf['stripe_atslega'] !== '' || $briva !== '') return [ZIEDOT_SUMMAS, true];
    return [array_values(array_filter(ZIEDOT_SUMMAS, fn(int $eur): bool => !empty($saites[$eur]))), false];
}

/** Vai summa ir viena no piedāvātajām (tad der fiksētās saites un Revolut). */
function ziedot_fiksetie_eur(int $centi): ?int {
    if ($centi % 100 !== 0) return null;
    $eur = intdiv($centi, 100);
    return in_array($eur, ZIEDOT_SUMMAS, true) ? $eur : null;
}

/** Adrese, uz kuru Stripe atgriež. Hosts no pieprasījuma TIKAI no baltā saraksta —
 *  HTTP_HOST sūta klients, un svešs hosts te nozīmētu atgriešanos svešā vietnē. */
function ziedot_izcelsme(): string {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if (preg_match('/^(localhost|127\.\d+\.\d+\.\d+)(:\d+)?$/', $host)
        && function_exists('reg_test_env') && reg_test_env()) {
        return 'http://' . $host;
    }
    return 'https://saraksts.lv';
}

/** Stripe Checkout sesijas parametri (bez tīkla — lai to var pārbaudīt testos). */
function ziedot_sesijas_parametri(int $centi, string $metode, string $izcelsme): array {
    $tips = $metode === 'paypal' ? 'paypal' : 'card';
    return [
        'mode'        => 'payment',
        'submit_type' => 'pay',          // poga "Maksāt": samaksa par saturu, ne ziedojums (ZIEDOT_JURIDISKI)
        'locale'      => 'lv',           // vietne ir latviski — kasei jābūt tāpat
        'line_items'  => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'eur',
                'unit_amount'  => $centi,
                'product_data' => ['name' => ZIEDOT_NOSAUKUMS],
            ],
        ]],
        'payment_method_types' => [$tips],
        'payment_intent_data'  => ['description' => ZIEDOT_NOSAUKUMS],
        'custom_text'  => ['submit' => ['message' => ZIEDOT_JURIDISKI]],
        'metadata'     => ['avots' => 'ziedot.php', 'metode' => $metode],
        'success_url'  => $izcelsme . '/ziedot.php?paldies=1',
        'cancel_url'   => $izcelsme . '/ziedot.php',
    ];
}

/**
 * Stripe API versija — FIKSĒTA. Bez tās uzvedība seko konta noklusējumam, un
 * versijas maiņa Stripe panelī var klusi salauzt formu: 2026-08-26.preview jau
 * izņem payment_method_types no PaymentIntents (aizstājējs allowed_payment_method_types
 * kopš 2026-07-29.dahlia). Mainot versiju — vispirms pārbaudi changelog un testus.
 */
const ZIEDOT_STRIPE_VERSIJA = '2026-08-26.dahlia';

/**
 * Īstais HTTP izsaukums uz Stripe. Atgriež [HTTP statuss, atbildes JSON|null].
 * 0 = tīkla kļūme. Idempotences atslēga ir nejauša katram izsaukumam — atkārtojumu
 * mēs neveicam, tā tikai liedz Stripe pusē dubultot vienu un to pašu pieprasījumu.
 * Taimauts īss (4 + 8 s): cilvēks gaida uz baltas lapas, un rezerves ceļš ir ātrāks.
 */
function ziedot_stripe_http(string $atslega, array $parametri, string $cels = 'checkout/sessions'): array {
    if (!function_exists('curl_init')) return [0, null];
    if (!in_array($cels, ['checkout/sessions', 'payment_intents'], true)) return [0, null];
    $ch = curl_init('https://api.stripe.com/v1/' . $cels);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($parametri, '', '&', PHP_QUERY_RFC1738),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $atslega,
            'Stripe-Version: ' . ZIEDOT_STRIPE_VERSIJA,
            'Idempotency-Key: ' . bin2hex(random_bytes(16)),
        ],
    ]);
    $t = curl_exec($ch);
    $k = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    // curl_close() nesaucam: kopš PHP 8.0 tai nav efekta, 8.5 to atzīmē kā novecojušu.
    $j = is_string($t) ? json_decode($t, true) : null;
    return [$k, is_array($j) ? $j : null];
}

/**
 * Formas apstrāde → adrese, uz kuru pāradresēt (303).
 *
 * Tīra funkcija: konfigurāciju un HTTP izsaukumu saņem no ārpuses, tāpēc
 * testi (test_tools/atbalsts/) to pārbauda bez tīkla un bez īstas atslēgas.
 *
 * @param callable(string,array):array $http  ziedot_stripe_http paraksts
 * @param callable(string):void|null   $zurnals kļūdu ieraksts (applog)
 */
function ziedot_apstradat(array $post, array $konf, callable $http, string $izcelsme,
                          ?callable $zurnals = null): string {
    $atpakal = function (string $kods, ?array $post = null) use ($izcelsme): string {
        $q = ['kluda' => $kods];
        // Atpakaļ uz formu ar to pašu izvēli — cilvēkam nav jāsāk no jauna.
        if ($post !== null) {
            $s = ziedot_lauks($post, 'summa');
            $c = trim(ziedot_lauks($post, 'cita'));
            if ($c !== '' && mb_strlen($c) <= 10) $q['cita'] = $c;
            elseif (ctype_digit($s) && in_array((int)$s, ZIEDOT_SUMMAS, true)) $q['summa'] = $s;
        }
        return $izcelsme . '/ziedot.php?' . http_build_query($q) . '#atbalsts';
    };

    $metode = ziedot_lauks($post, 'metode');
    if (!in_array($metode, ZIEDOT_METODES, true)) $metode = 'karte';

    [$centi, $kluda] = ziedot_summa($post);
    if ($centi === null) return $atpakal($kluda, $post);
    $eur = ziedot_fiksetie_eur($centi);

    // ── Revolut: tikai īstas Revolut saites, tikai fiksētām summām ──────────
    if ($metode === 'revolut') {
        if (!$konf['revolut']) return $atpakal('revolut_nav', $post);
        if ($eur === null || !isset($konf['revolut'][$eur])) return $atpakal('revolut_summa', $post);
        return $konf['revolut'][$eur];
    }

    // ── Stripe caur API ──────────────────────────────────────────────────────
    if ($konf['stripe_atslega'] !== '') {
        if ($metode === 'paypal' && !$konf['paypal']) $metode = 'karte';
        [$k, $j] = $http($konf['stripe_atslega'],
                         ziedot_sesijas_parametri($centi, $metode, $izcelsme));
        $url = is_array($j) ? (string)($j['url'] ?? '') : '';
        if ($k === 200 && str_starts_with($url, 'https://checkout.stripe.com/')) return $url;

        if ($zurnals) {
            $zina = is_array($j) ? (string)($j['error']['message'] ?? '') : '';
            // Stripe kļūdas ziņā atslēga ir daļēji aizklāta, bet žurnālā tai nav vietas vispār.
            $zina = preg_replace('/\b(rk|sk|pk)_(live|test)_[A-Za-z0-9*]+/', '[atslēga]', $zina) ?? '';
            $zurnals("Stripe sesija neizdevās: HTTP $k, metode=$metode" . ($zina !== '' ? " — $zina" : ''));
        }
        // PayPal nevar aizstāt ar citu veidu klusi: cilvēks spieda "PayPal" un
        // kasē ieraudzītu karti. Labāk pateikt un ļaut izvēlēties pašam.
        if ($metode === 'paypal') return $atpakal('paypal', $post);
    }

    // ── Rezerve: Payment Links ───────────────────────────────────────────────
    // Fiksētajai summai — tās saite; citādi — brīvās summas saite (cilvēks summu
    // ievada vēlreiz Stripe lapā; tas ir sliktāk, bet maksājums notiek).
    if ($eur !== null && !empty(ZIEDOT_SUMMU_SAITES[$eur])) return ZIEDOT_SUMMU_SAITES[$eur];
    if (ZIEDOT_STRIPE_SAITE !== '') return ZIEDOT_STRIPE_SAITE;
    return $atpakal('nav_saites', $post);
}

/**
 * Pēc pāradresācijas (PayPal, 3-D Secure) Stripe atgriež uz return_url ar
 * payment_intent un payment_intent_client_secret. Noslēpums nedrīkst palikt
 * adreses joslā: GA4 page_location to aizsūtītu Google, un tas nonāk žurnālos.
 * Atgriež tīro adresi (tikai paldies + atļauts redirect_status) vai null.
 */
function ziedot_tira_atgriesana(array $get): ?string {
    if (!isset($get['payment_intent']) && !isset($get['payment_intent_client_secret'])
        && !isset($get['setup_intent_client_secret'])) return null;
    $rs = ziedot_lauks($get, 'redirect_status');
    // Viss, kas nav skaidri "izdevās" vai "apstrādā", ir neizdevies — citādi, piem.,
    // requires_payment_method tiktu nomests un lapa pateiktu "Paldies" (recenzija 09-22).
    $q  = ['paldies' => '1', 'redirect_status' => in_array($rs, ['succeeded', 'processing'], true) ? $rs : 'failed'];
    // Uznirstošais logs (MI atbildes): pēc 3-D Secure jāpaliek kompaktajā lapā.
    if (ziedot_lauks($get, 'logs') === '1') $q['logs'] = '1';
    return '/ziedot.php?' . http_build_query($q) . '#atbalsts';
}

/** Lapas paziņojums pēc atgriešanās (?kluda=… / ?paldies=1). [klase, teksts] vai null. */
function ziedot_pazinojums(array $get, bool $ar_citu = true): ?array {
    if (ziedot_lauks($get, 'paldies') === '1') {
        // Iegultajā režīmā pēc pāradresācijas (PayPal, 3-D Secure) Stripe pieliek
        // redirect_status — tikai tas saka, vai maksājums tiešām izdevās.
        $rs = ziedot_lauks($get, 'redirect_status');
        if ($rs === 'failed') return ['kluda', 'Maksājums netika pabeigts. Nauda nav noņemta — vari mēģināt vēlreiz.'];
        if ($rs === 'processing') return ['ok', 'Paldies! Maksājums tiek apstrādāts.'];
        return ['ok', 'Paldies! Tava samaksa ir saņemta.'];
    }
    $min = number_format(ZIEDOT_MIN_CENTI / 100, 0, ',', ' ');
    $max = number_format(ZIEDOT_MAX_CENTI / 100, 0, ',', ' ');
    $ar_saiti = array_values(array_filter(ZIEDOT_SUMMAS,
        fn(int $eur): bool => !empty(ZIEDOT_SUMMU_SAITES[$eur])));
    $teksti = [
        'summa'         => $ar_citu ? 'Izvēlies summu vai ieraksti savu laukā «Cita».' : 'Izvēlies summu.',
        'summa_forma'   => 'Summu neizdevās nolasīt. Ieraksti to ar cipariem, piemēram, 7 vai 7,50.',
        'summa_robezas' => "Summai jābūt no $min līdz $max €.",
        'revolut_summa' => 'Ar Revolut var samaksāt ' . implode(', ', ZIEDOT_SUMMAS)
                         . ' €. Citai summai izvēlies karti.',
        'revolut_nav'   => 'Revolut maksājums šobrīd nav pieejams. Izvēlies citu maksāšanas veidu.',
        'paypal'        => 'PayPal maksājumu šobrīd neizdevās atvērt. Izvēlies citu maksāšanas veidu vai mēģini vēlreiz.',
        // Bez brīvās summas saites šī summa nekad nenonāks kasē — «mēģini vēlreiz» būtu nepatiess;
        // summas ar savu saiti strādā (forma citas arī nerāda — te nonāk tikai no viltota POST).
        'nav_saites'    => (ZIEDOT_STRIPE_SAITE === '' && $ar_saiti)
                         ? 'Šo summu šobrīd nevar samaksāt — izvēlies ' . implode(' vai ', $ar_saiti) . ' €.'
                         : 'Maksājumu šobrīd neizdevās atvērt. Lūdzu, mēģini vēlreiz pēc brīža.',
        'biezums'       => 'Pārāk daudz mēģinājumu no šī tīkla.',
    ];
    $k = ziedot_lauks($get, 'kluda');
    return isset($teksti[$k]) ? ['kluda', $teksti[$k]] : null;
}


// ─── Iegultais režīms: PaymentIntent ─────────────────────────────────────────

/** PaymentIntent parametri. Bez receipt_email un bez klienta — e-pastu neprasām. */
function ziedot_nodoma_parametri(int $centi, string $metode, bool $paypal = false): array {
    // Tieši tie veidi, kas ir attiecīgajā Stripe Elements grupā lapā (ziedot_forma.js):
    // kartes laukiem — tikai karte; maciņu pogām — karte (Apple/Google Pay ir kartes)
    // un PayPal, ja ieslēgts. Nesakritība ar Elements iestatījumiem Stripe.js pusē
    // dotu kļūdu tikai pēdējā klikšķī. Link apzināti NĒ — tas prasa e-pastu.
    $veidi = ($metode !== 'karte' && $paypal) ? ['card', 'paypal'] : ['card'];
    return [
        'amount'               => $centi,
        'currency'             => 'eur',
        'payment_method_types' => $veidi,
        'description'          => ZIEDOT_NOSAUKUMS,
        'metadata'             => ['avots' => 'ziedot.php', 'metode' => $metode, 'forma' => 'iegulta'],
    ];
}

/**
 * Iegultās formas pieprasījums → ['client_secret' => …] vai ['kluda' => kods, 'teksts' => …].
 * Summu pārbauda tieši tāpat kā parastajai formai (ziedot_summa), tāpēc pārlūks
 * nevar pasūtīt citu summu, nekā lapa atļauj.
 */
function ziedot_nodoms(array $post, array $konf, callable $http, ?callable $zurnals = null,
                       ?callable $biezums = null): array {
    if (empty($konf['iegults'])) return ['kluda' => 'nav_iegults'];
    [$centi, $kluda] = ziedot_summa($post);
    if ($centi === null) {
        return ['kluda' => $kluda, 'teksts' => ziedot_pazinojums(['kluda' => $kluda])[1] ?? ''];
    }
    $metode = ziedot_lauks($post, 'metode');
    if (!in_array($metode, ['karte', 'macins', 'paypal'], true)) $metode = 'karte';
    if ($metode === 'paypal' && !$konf['paypal']) $metode = 'karte';

    // Biežuma sargs skaita TIKAI derīgus pieprasījumus tieši pirms Stripe izsaukuma —
    // atraidīti (nav summas, nav iegultā režīma) neiztērē neviena budžetu (recenzija 09-22).
    if ($biezums !== null && !$biezums()) {
        return ['kluda' => 'biezums', 'teksts' => ziedot_pazinojums(['kluda' => 'biezums'])[1] ?? ''];
    }

    [$k, $j] = $http($konf['stripe_atslega'], ziedot_nodoma_parametri($centi, $metode, (bool)$konf['paypal']),
                     'payment_intents');
    $cs = is_array($j) ? (string)($j['client_secret'] ?? '') : '';
    if ($k === 200 && preg_match('/^pi_[A-Za-z0-9]+_secret_[A-Za-z0-9]+$/', $cs)) {
        return ['client_secret' => $cs];
    }
    if ($zurnals) {
        $zina = is_array($j) ? (string)($j['error']['message'] ?? '') : '';
        $zina = preg_replace('/\b(rk|sk|pk)_(live|test)_[A-Za-z0-9*]+/', '[atslēga]', $zina) ?? '';
        $zurnals("PaymentIntent neizdevās: HTTP $k, metode=$metode" . ($zina !== '' ? " — $zina" : ''));
    }
    // Teksts bez "mēģini vēlreiz": pārlūks pēc tā pāriet uz parastajām pogām un pieliek
    // "Izmanto maksājuma pogu zemāk." — divi pretrunīgi padomi vienā teikumā neder.
    return ['kluda' => 'nav_saites', 'teksts' => 'Maksājumu šeit neizdevās sākt.'];
}

/**
 * Vienkāršs biežuma sargs PaymentIntent izveidei: 10 no vienas IP (/24) 10 minūtēs.
 * Atgriež 0 = atļauts, 1 = atteikts (šī IP), 2 = atļauts, bet visas vietnes plūsma
 * pārsniedz 120 stundā (tikai BRĪDINĀJUMS žurnālā, ne bloks — kopīgi griesti ļautu
 * ar 120 nevērtīgiem pieprasījumiem nobloķēt maksājumus visiem; recenzija 09-22).
 *
 * TAS NAV aizsardzība pret karšu testēšanu: viens client_secret ļauj mēģināt
 * vairākas kartes. Īstā kontrole ir Stripe Radar noteikumi; šis tikai ierobežo
 * pamestu PaymentIntent daudzumu. Atteikuma gadījumā pārlūks pāriet uz Stripe
 * mitināto kasi, kurai ir sava aizsardzība.
 * IP — reg_klienta_ip() (CF-Connecting-IP tikai no Cloudflare tīkla).
 * Kļūmes gadījumā (fails nav rakstāms) LAIŽ cauri — sargs nedrīkst apturēt maksājumus.
 */
function ziedot_biezums(string $ip, ?string $fails = null, ?int $tagad = null): int {
    $fails ??= dirname(__DIR__) . '/log/ziedot_biezums.json';
    $tagad ??= time();
    $atsl = substr(hash('sha256', (function_exists('applog_anon_ip') ? applog_anon_ip($ip) : $ip)), 0, 16);
    $fh = @fopen($fails, 'c+');
    if (!$fh) return 0;
    try {
        if (!flock($fh, LOCK_EX)) return 0;
        $d = json_decode((string)stream_get_contents($fh), true);
        if (!is_array($d)) $d = [];
        // Tīrīšana: vecāki par stundu prom.
        foreach ($d as $a => $laiki) {
            $d[$a] = array_values(array_filter((array)$laiki, fn($t) => is_int($t) && $t > $tagad - 3600));
            if (!$d[$a]) unset($d[$a]);
        }
        $visi  = array_sum(array_map('count', $d));
        $sie   = count(array_filter($d[$atsl] ?? [], fn($t) => $t > $tagad - 600));
        $atlauts = $sie < 10;
        if ($atlauts) $d[$atsl][] = $tagad;
        ftruncate($fh, 0); rewind($fh);
        fwrite($fh, json_encode($d));
        fflush($fh);
        flock($fh, LOCK_UN);
        if (!$atlauts) return 1;
        return $visi >= 120 ? 2 : 0;
    } catch (Throwable $e) {
        return 0;
    } finally {
        fclose($fh);
    }
}
