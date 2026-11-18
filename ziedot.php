<?php
require_once __DIR__ . '/lib/applog.php';
applog_boot('ziedot');

// Maksājuma ceļš, Stripe/Revolut saites un privātā konfigurācija — viss tur.
require_once __DIR__ . '/lib/ziedot_stripe.php';
// Tikai biežuma sargam. is_file: ja izvietošanā fails pietrūktu, lapa un parastā
// forma strādā tik un tā (sargs tad skaita pēc REMOTE_ADDR).
if (is_file(__DIR__ . '/lib/cloudflare_ip.php')) require_once __DIR__ . '/lib/cloudflare_ip.php';

// Formas nosūtīšana: pāradresē uz kasi un beidz — lapa netiek zīmēta. Tas pats
// fails, ne atsevišķs galapunkts: ja izvietošanā kāds fails pazustu, forma un
// tās apstrāde pazūd kopā, nevis forma paliek ar 404 aiz pogas.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['darbiba'] ?? '') === 'nodoms') {
    // Iegultā forma (ziedot_forma.js) prasa PaymentIntent. Atbilde — JSON.
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $atb_log = function (string $m): void { applog_event('WARN', 'ziedot', 'stripe', $m, true); };
    // Tikai no mūsu pašu lapas: pārlūks POST pieprasījumam vienmēr sūta Origin.
    $atb_orig = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $atb_der  = [ziedot_izcelsme(), 'https://www.saraksts.lv'];
    if ($atb_orig !== '' && !in_array($atb_orig, $atb_der, true)) {
        http_response_code(403);
        echo json_encode(['kluda' => 'izcelsme']);
        exit;
    }
    // Biežuma sargu sauc ziedot_nodoms TIKAI derīgam pieprasījumam, tieši pirms Stripe.
    $atb_biezums = function () use ($atb_log): bool {
        $r = ziedot_biezums(function_exists('reg_klienta_ip') ? reg_klienta_ip()
                                                              : (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($r === 1) $atb_log('biežuma sargs: PaymentIntent atteikts (viena IP)');
        if ($r === 2) $atb_log('biežuma sargs: >120 PaymentIntent stundā visai vietnei — pārbaudi Stripe Radar');
        return $r !== 1;
    };
    $atb_r = ziedot_nodoms($_POST, ziedot_konfig(), 'ziedot_stripe_http', $atb_log, $atb_biezums);
    if (isset($atb_r['kluda'])) http_response_code(($atb_r['kluda'] === 'biezums') ? 429 : 400);
    echo json_encode($atb_r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $atb_uz = ziedot_apstradat($_POST, ziedot_konfig(), 'ziedot_stripe_http', ziedot_izcelsme(),
        function (string $m): void { applog_event('WARN', 'ziedot', 'stripe', $m, true); });
    header('Cache-Control: no-store');
    header('Location: ' . $atb_uz, true, 303);
    exit;
}

// Atgriešanās pēc PayPal / 3-D Secure: noņem client_secret no adreses PIRMS lapas
// zīmēšanas — citādi GA4 page_location to aizsūtītu Google (recenzija 09-22).
$atb_tira = ziedot_tira_atgriesana($_GET);
if ($atb_tira !== null) {
    header('Cache-Control: no-store');
    header('Location: ' . $atb_tira, true, 303);
    exit;
}

$atb_konf = ziedot_konfig();
// Tikai summas, kurām ir ceļš līdz kasei (bez API atslēgas un brīvās summas saites — bez «Cita»).
[$atb_summas, $atb_ar_citu] = ziedot_pieejamas_summas($atb_konf);
$atb_zina = ziedot_pazinojums($_GET, $atb_ar_citu);
// Pēc kļūdas forma atgriežas ar to pašu izvēli (ziedot_apstradat pieliek ?summa / ?cita).
$atb_g_summa = ziedot_lauks($_GET, 'summa');
$atb_g_cita  = ziedot_lauks($_GET, 'cita');
$atb_izv_summa = (ctype_digit($atb_g_summa) && in_array((int)$atb_g_summa, $atb_summas, true))
                  ? (int)$atb_g_summa : 0;
// «7 €» / «5 eur» serveris atpakaļ sūta, kā ierakstīts — vienību atmetam, citādi
// lauks pēc kļūdas paliktu tukšs (audits 10-20).
$atb_g_cita    = trim((string)preg_replace('/\s*(€|eur)\s*$/iu', '', $atb_g_cita));
$atb_izv_cita  = ($atb_ar_citu && preg_match('/^[0-9 ,.]{1,10}$/', $atb_g_cita)) ? trim($atb_g_cita) : '';

// ?logs=1 — uznirstošais logs MI atbildēs (registrs/assets/js/atbalsta_logs.js): tikai
// formas kartīte, bez galvenes, kājenes un paskaidrojumiem. Maksājumu lapu ietvert
// drīkst tikai pati vietne (clickjacking), tāpēc frame-ancestors visos režīmos.
$atb_logs = ziedot_lauks($_GET, 'logs') === '1';
header("Content-Security-Policy: frame-ancestors 'self'");
header('X-Frame-Options: SAMEORIGIN');
if ($atb_logs) header('X-Robots-Tag: noindex');

// Brīvprātīga samaksa par saturu (2026-10-28): Stripe pārskatīja kontu kā ziedojumu
// vākšanu. Lapa tagad apraksta to, kas notiek patiesībā — lasītājs pēc izvēles samaksā
// par saturu, kas viņam noderēja; saturs ir pieejams visiem neatkarīgi no tā. Bez
// «ziedojuma», bez vietnes izdzīvošanas motīva un bez sabiedriskā labuma aicinājuma.
$pageTitle = "Brīvprātīga samaksa par saturu — saraksts.lv";
$pageDesc  = "Uzņēmumu profili, pārskatu grafiki un MI analīzes, ko saraksts.lv sagatavo no atvērtajiem datiem, ir pieejami bez maksas un bez reklāmām. Ja tie noderēja, par saturu var brīvprātīgi samaksāt, summu izvēloties pašam.";
?>
<!DOCTYPE html>
<html lang="lv">

<?php include 'registrs/head/head.php'; ?>

<body<?= $atb_logs ? ' class="atb-logs-body"' : '' ?>>
    <?php if (!$atb_logs) include 'registrs/header.php'; ?>

<style>
.atb-wrap { max-width: 720px; margin: 0 auto; padding: 40px 20px 20px; }
.atb-wrap h1 { font-size: 30px; color: #140a3f; margin: 0 0 14px; line-height: 1.25; }
.atb-lead { font-size: 17px; line-height: 1.65; color: #333; margin: 0 0 26px; }
.atb-card {
    border: 1px solid #e3e3ea; border-radius: 12px; background: #fff;
    padding: 26px; margin: 0 0 26px; box-shadow: 0 1px 3px rgba(20,10,63,.06);
}
.atb-card h2 { font-size: 17px; color: #140a3f; margin: 0 0 12px;
               display: flex; align-items: center; gap: 10px; }
/* Sadaļu ikonas — tā pati Font Awesome valoda, kas galvenes izvēlnē, tāpēc tās
   silda, nesabojājot lietišķo toni. Emocijzīmes to nedarītu (skat. piezīmi zemāk). */
.atb-card h2 i { color: #4CAF50; font-size: .95em; opacity: .85; }
.atb-card p  { font-size: 15px; line-height: 1.6; color: #444; margin: 0 0 10px; }
.atb-card ul { margin: 0; padding-left: 20px; color: #444; font-size: 15px; line-height: 1.75; }
.atb-card ul li + li { margin-top: 6px; }
.atb-card ul + p { margin-top: 14px; }
/* Maksājuma kartīte ir vienīgā silti tonētā lapā — pārējais paliek vēss un lietišķs.
   Siltums te ir pieļaujams tāpēc, ka tā ir vienīgā vieta, kur cilvēkam kaut ko
   piedāvā, nevis rāda datus. */
.atb-card-cta {
    background:
        radial-gradient(120% 90% at 50% 0%, #fff8e8 0%, rgba(255,248,232,0) 70%),
        linear-gradient(180deg, #fffdf8, #fff);
    border-color: #efe4cd;
}
/* ── Atbalsta forma (Wikipedia paraugā, 2026-09-22) ─────────────────────────
   Summa kā flīzes, zem tām maksāšanas veidu pogas. Pogas ir <button type=submit>
   vienā <form> — strādā arī bez JS; JS tikai ērtībām (skat. ziedot_forma.js). */
.atb-forma { max-width: 520px; margin: 0 auto; }
.atb-lauks { border: 0; margin: 0; padding: 0; min-width: 0; }
.atb-leg { display: block; width: 100%; padding: 0; margin: 0 0 12px;
           font-size: 16px; font-weight: 700; color: #140a3f; text-align: center; }
/* Vienādas flīzes: atšķirīgs izmērs vai krāsa nozīmētu, ka viena summa ir
   "pareizā", un tas ir spiediens, ne izvēle. */
.atb-rezgis { display: grid; grid-template-columns: repeat(var(--atb-kol, 4), minmax(0, 1fr)); gap: 10px; }
.atb-flise {
    position: relative; box-sizing: border-box; min-height: 64px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    border: 1px solid #7f88ad; border-radius: 8px; background: #fff;   /* 3,48:1 uz balta, 3,29:1 uz kartītes — WCAG 1.4.11 */
    color: #140a3f; font-size: 19px; font-weight: 700; cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
}
.atb-flise:hover { background: #f3f6ff; }
/* Radio paliek ekrānlasītājam un tastatūrai, tikai neredzams. */
.atb-flise input[type="radio"] { position: absolute; opacity: 0; width: 1px; height: 1px;
                                 margin: 0; pointer-events: none; }
/* Izvēlētā flīze: .atb-izv liek JS; :has() — pārlūkos, kas to māk, arī bez JS. */
.atb-flise.atb-izv,
.atb-flise:has(input[type="radio"]:checked) { background: #206a25; border-color: #206a25; color: #fff; }
.atb-flise:has(input:focus-visible) { outline: 3px solid #140a3f; outline-offset: 2px; }
/* Pārlūkiem bez :has() — fokusa rāmis tik un tā (rāda arī pēc klikšķa, bet nekad nepazūd). */
@supports not selector(:has(*)) { .atb-flise:focus-within { outline: 3px solid #140a3f; outline-offset: 2px; } }
/* «Cita» ir tāda pati flīze kā pārējās; lauks ir zem režģa pilnā platumā (2026-10-20:
   flīzē tas bija 53×30 px — zem 44 px pieskāriena mērķa). Slēpj TIKAI ar JS
   (.atb-js liek ziedot_forma.js): bez JS lauks redzams vienmēr, un forma strādā. */
.atb-cita-bloks { margin-top: 12px; }
.atb-js .atb-cita-bloks:not(.atv) { display: none; }
.atb-cita-bloks label { display: block; margin: 0 0 6px; font-size: 14px; font-weight: 600; color: #140a3f; }
.atb-cita-rinda { position: relative; }
.atb-cita {
    box-sizing: border-box; width: 100%; height: 50px; padding: 0 44px 0 14px;
    border: 1px solid #7f88ad; border-radius: 8px; background: #fff; color: #140a3f;
    font: inherit; font-size: 18px; font-weight: 600;
}
.atb-cita:focus { outline: 3px solid #140a3f; outline-offset: 1px; }
.atb-cita-eur { position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
                font-weight: 700; color: #6b6b6b; pointer-events: none; }
.atb-cita-padoms { display: block; margin-top: 5px; font-size: 13px; color: #6b6b6b; }
.atb-kluda-lok { margin: 10px 0 0; color: #b3261e; font-size: 14px; text-align: center; }

/* Maksāšanas veidi kā Wikipedia formā (Ģirta norāde 2026-10-20): zem summām balti
   taisnstūri ar logo, ne uzreiz kartes lauki — CVC prasīšana, pirms cilvēks izvēlējies
   karti, rada nepatīkamu sajūtu. Izmērs un apmale kā Stripe maciņu pogām (50 px,
   8 px rādiuss), lai karte un Apple/Google Pay/PayPal izskatītos kā viens saraksts. */
.atb-metodes { display: grid; gap: 12px; margin-top: 18px; }
.atb-metode {
    box-sizing: border-box; width: 100%; min-height: 50px;
    display: flex; align-items: center; justify-content: center; gap: 12px;
    border: 1px solid #5f6368; border-radius: 8px; background: #fff; cursor: pointer;
    color: #140a3f; font: inherit; font-size: 18px; font-weight: 700;
    transition: background .15s, box-shadow .15s;
}
.atb-metode:hover { background: #f3f6ff; box-shadow: 0 2px 8px rgba(20,10,63,.08); }
.atb-metode:focus-visible { outline: 3px solid #140a3f; outline-offset: 2px; }
/* display:flex pārraksta [hidden] — bez šī maciņu pogas rādītos arī tur, kur nestrādā. */
.atb-metode[hidden] { display: none; }
.atb-metode[aria-disabled="true"] { opacity: .45; cursor: not-allowed; }
.atb-metode[aria-busy="true"] { opacity: .6; cursor: progress; }
.atb-metode i { font-size: 2.1em; line-height: 1; }
.atb-maksat i { font-size: .85em; opacity: .9; }
.atb-l-visa { color: #1a1f71; } .atb-l-mc { color: #eb001b; } .atb-l-amex { color: #2e77bc; }
.atb-l-paypal { color: #003087; }
.atb-pp { color: #003087; font-style: italic; font-weight: 800; font-size: 20px; }
.atb-pp b { color: #0070e0; font-weight: 800; }
.atb-rev { font-weight: 800; font-size: 20px; letter-spacing: -.01em; color: #000; }

/* Iegultais režīms (Stripe Elements). Kartes taisnstūris atver kartes laukus tikai pēc
   klikšķa; maciņus zīmē Stripe (tikai tos, kas ierīcē strādā). */
.atb-iegults { margin-top: 18px; }
.atb-iegults-lauki { display: grid; gap: 12px; }
.atb-iegults-lauki[hidden], .atb-iegults[hidden], .atb-kartes-panelis[hidden] { display: none; }
.atb-veids-karte[aria-expanded="true"] { border: 2px solid #206a25; background: #f6faf6; }
.atb-kartes-panelis { padding: 4px 0 6px; }
.atb-maksat {
    box-sizing: border-box; width: 100%; min-height: 52px; margin-top: 16px;
    border: 0; border-radius: 8px; background: #206a25; color: #fff; cursor: pointer;
    font: inherit; font-size: 18px; font-weight: 700;
}
.atb-maksat:hover { background: #1b5a20; }
.atb-maksat:focus-visible { outline: 3px solid #140a3f; outline-offset: 2px; }
.atb-maksat[aria-disabled="true"] { opacity: .6; cursor: progress; }
.atb-statuss { margin: 12px 0 0; text-align: center; font-size: 15px; }
.atb-statuss:empty { margin: 0; }   /* tukšs aria-live reģions paliek lapā, bez atstarpes */
.atb-express { margin-top: 12px; }
.atb-card p.atb-jur { margin-top: 8px; font-size: 12.5px; }
.atb-statuss.atb-ok { color: #1b5e20; font-weight: 700; }
.atb-statuss.atb-kl { color: #b3261e; }
.atb-metode.atb-slepts { display: none; }
.atb-note [hidden] { display: none; }
.atb-bez { display: block; margin-bottom: 2px; color: #1b5e20; font-weight: 600; }

/* Ekonomikas rindkopa sakļauta — atbalsta lapā tā lasījās kā referāts (2026-10-20). */
details.atb-vairak { margin-top: 14px; padding-top: 12px; border-top: 1px solid #ececf2; }
details.atb-vairak summary { cursor: pointer; list-style: none; color: #1a4fd6; font-size: 14.5px; font-weight: 600; }
details.atb-vairak summary::-webkit-details-marker { display: none; }
details.atb-vairak summary::before { content: "▸"; content: "▸" / ""; display: inline-block; width: 16px; transition: transform .15s; }
details.atb-vairak[open] summary::before { transform: rotate(90deg); }
details.atb-vairak summary:focus-visible { outline: 3px solid #140a3f; outline-offset: 2px; }
details.atb-vairak > p:first-of-type { margin-top: 10px; }

.atb-zina { margin: 0 0 16px; padding: 12px 14px; border-radius: 8px;
            font-size: 15px; line-height: 1.5; text-align: center; }
.atb-zina-ok    { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
.atb-zina-kluda { background: #fdecea; color: #8a1c14; border: 1px solid #f1aeb5; }

/* Atstatums no pogām. .atb-card p ir specifiskāks par .atb-note, tāpēc izmērs
   jāraksta ar to pašu specifiskumu, citādi 13,5 px nekad nenostrādā. */
/* #6b6b6b, ne #777: pēdējais deva izmērītus 4,26:1 (uz balta 4,48) — zem AA
   sliekšņa 4,5:1 pat labākajā gadījumā (audits 2026-09-02). Tagad 5,33:1. */
.atb-card p.atb-note { font-size: 13.5px; color: #6b6b6b; margin: 16px 0 0; line-height: 1.55;
                       text-align: center; }
/* Vārdzīmes glifā ir daudz tukšuma virs un zem, tāpēc optiski tā izskatās mazāka
   nekā fonta izmērs sola — 2,6em vajag, lai tā līdzinātos blakus tekstam. */
.atb-stripe { color: #635BFF; font-size: 2.6em; vertical-align: -7px; }
/* Tikai ekrānlasītājam: vietnē globālas .sr-only klases nav, tāpēc sava. */
.atb-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
          overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

@media (max-width: 600px) {
    .atb-rezgis { gap: 8px; }
    .atb-flise { min-height: 60px; font-size: 17px; }
}
@media (prefers-reduced-motion: reduce) {
    .atb-flise, .atb-metode, details.atb-vairak summary::before { transition: none; }
}
.atb-fine { font-size: 13.5px; color: #666; line-height: 1.65; }
.atb-card ul.atb-noteikumi { margin-top: 10px; font-size: 13.5px; line-height: 1.6; color: #555; }
.atb-card ul.atb-noteikumi li + li { margin-top: 6px; }
.atb-fine strong { color: #444; }
@media (max-width: 600px) {
    .atb-wrap h1 { font-size: 25px; }
    .atb-card { padding: 20px; }
}
/* ?logs=1: bez lapas rāmja — uznirstošais logs pats ir rāmis. */
body.atb-logs-body { margin: 0; padding: 0 !important; background: #fff; }
.atb-logs-body .atb-wrap { padding: 4px 16px 16px; }
.atb-logs-body .atb-card-cta { margin: 0; padding: 4px 0 0; border: 0; box-shadow: none; background: none; }
</style>

<main class="atb-wrap">

    <?php if (!$atb_logs): ?>
    <h1>Atbalsti saturu, kas tev noderēja</h1>

    <?php /* Vadošais motīvs ir SAVSTARPĪBA, ne trūkums: šī nav labdarība, kur
             palīdz svešiniekam — lasītājs pats tikko saņēma vērtību, un samaksa ir
             par TO saturu. Divi teikumi (2026-10-20): telefonā ievads aizņēma 7–8
             rindas un nobīdīja formu zem ekrāna malas. «Vienīgais ienākumu avots ir
             lietotāju atbalsts» izņemts 2026-10-28 — tas ir līdzekļu vākšanas motīvs
             (vietnes izdzīvošana), ne samaksa par saturu. */ ?>
    <p class="atb-lead">
        Uzņēmumu profili, pārskatu grafiki un MI analīzes, ko saraksts.lv sagatavo no
        atvērtajiem datiem, ir pieejami bez maksas un bez reklāmām. Ja kas no tā tev noderēja,
        vari par to brīvprātīgi samaksāt — summu izvēlies pats, <strong>der arī daži eiro</strong>.
    </p>
    <?php endif; ?>

    <div class="atb-card atb-card-cta" id="atbalsts">
        <?php if ($atb_zina): ?>
        <p class="atb-zina atb-zina-<?= $atb_zina[0] ?>" role="<?= $atb_zina[0] === 'ok' ? 'status' : 'alert' ?>">
            <?= htmlspecialchars($atb_zina[1], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php endif; ?>
        <?php /* Formā tikai summa un maksāšanas veidi. Teikums «Tavs atbalsts apmaksā serveri…»
                 izņemts 2026-10-20 — to pašu saka kartīte «Kam nauda tiek izlietota». */ ?>
        <?php /* Summas: 5 / 10 / 20 € un sava (Ģirta norāde 2026-09-22). Iepriekš
                 izvēlētas summas NAV — tāpat kā Wikipedia formā; nekādas summas
                 neatzīmējam kā "populārāko", kamēr nav īstu datu. */ ?>
        <?php /* Logā rezerves forma (arī PayPal/Revolut un kamēr Stripe.js nav ielādēts)
                 atver Stripe lapu JAUNĀ CILNĒ: ietvarā to rādīt nedrīkst, bet _top aizvestu
                 prom visu uzņēmuma lapu un izdzēstu MI sarunu (audits 2026-10-28). */ ?>
        <form class="atb-forma" id="atb-forma" method="post" action="/ziedot.php" novalidate<?=
              $atb_logs ? ' target="_blank" data-logs="1"' : '' ?>
              data-min="<?= ZIEDOT_MIN_CENTI / 100 ?>" data-max="<?= ZIEDOT_MAX_CENTI / 100 ?>"<?php
              if ($atb_konf['iegults']): ?>
              data-pk="<?= htmlspecialchars($atb_konf['stripe_publiska'], ENT_QUOTES, 'UTF-8') ?>"
              data-paypal="<?= $atb_konf['paypal'] ? '1' : '0' ?>"<?php endif; ?>>
            <fieldset class="atb-lauks">
                <legend class="atb-leg">Izvēlies summu</legend>
                <div class="atb-rezgis" style="--atb-kol:<?= max(1, count($atb_summas) + ($atb_ar_citu ? 1 : 0)) ?>">
                    <?php foreach ($atb_summas as $eur): ?>
                    <label class="atb-flise">
                        <input type="radio" name="summa" value="<?= (int)$eur ?>"<?= ($atb_izv_cita === '' && $atb_izv_summa === $eur) ? ' checked' : '' ?>>
                        <span><?= (int)$eur ?>&nbsp;€</span>
                    </label>
                    <?php endforeach; ?>
                    <?php if ($atb_ar_citu): ?>
                    <label class="atb-flise atb-flise-cita">
                        <input type="radio" name="summa" value="cita"<?= $atb_izv_cita !== '' ? ' checked' : '' ?>>
                        <span>Cita</span>
                    </label>
                    <?php endif; ?>
                </div>
                <?php if ($atb_ar_citu): ?>
                <div class="atb-cita-bloks<?= $atb_izv_cita !== '' ? ' atv' : '' ?>" id="atb-cita-bloks">
                    <label for="atb-cita">Cita summa</label>
                    <div class="atb-cita-rinda">
                        <input type="text" name="cita" id="atb-cita" class="atb-cita" inputmode="decimal"
                               autocomplete="off" maxlength="8" placeholder="piem., 15"
                               aria-describedby="atb-cita-padoms"
                               value="<?= htmlspecialchars($atb_izv_cita, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="atb-cita-eur" aria-hidden="true">€</span>
                    </div>
                    <small class="atb-cita-padoms" id="atb-cita-padoms">No <?= ZIEDOT_MIN_CENTI / 100 ?> līdz <?= ZIEDOT_MAX_CENTI / 100 ?>&nbsp;€</small>
                </div>
                <?php endif; ?>
                <p class="atb-kluda-lok" id="atb-kluda-lok" role="alert" hidden></p>
            </fieldset>

            <?php /* Katra poga atver kasi TIKAI ar to maksāšanas veidu, kas uz tās
                     rakstīts (Ģirta norāde: logo nedrīkst solīt citu maksājumu, nekā
                     notiek). Atsevišķu Apple Pay / Google Pay pogu šeit NAV: Stripe
                     kase maciņu rāda tikai tad, ja ierīcē tas iestatīts, un logo
                     solītu to, kas var nenotikt (recenzija 09-22). Īstās maciņu pogas
                     ir iegultajā režīmā — tās zīmē Stripe tikai tur, kur tās strādā. */ ?>
            <?php if ($atb_konf['iegults']): ?>
            <?php /* IEGULTAIS REŽĪMS. Paslēpts, līdz ziedot_forma.js ir ielādējis
                     Stripe.js un kartes lauki ir gatavi; tikai tad parastās pogas
                     pazūd. Ja kaut kas nenostrādā, cilvēks redz parastās pogas. */ ?>
            <div class="atb-iegults" id="atb-iegults" hidden>
                <?php /* Kā Wikipedia formā (Ģirta norāde 2026-10-20): zem summām
                         maksāšanas veidi kā taisnstūri. Karte — mūsu poga, kas atver
                         kartes laukus un «Atbalstīt ar X €»; zem tās maciņi vienā
                         kolonnā (PayPal, Apple Pay, Google Pay — tikai tie, kas šajā
                         ierīcē strādā). */ ?>
                <?php /* Statuss uzreiz zem kartes pogas, pirms maciņiem (kļūda pie pogas,
                         ne zem trim maciņiem); ārpus lauku bloka, lai «Paldies» paliek, kad
                         lauki pazūd. Nav hidden — aria-live reģionam jābūt lapā (audits 10-20). */ ?>
                <div class="atb-iegults-lauki" id="atb-iegults-lauki">
                    <button type="button" class="atb-metode atb-veids-karte" id="atb-veids-karte"
                            aria-expanded="false" aria-controls="atb-kartes-panelis"
                            aria-label="Maksāt ar karti (Visa, Mastercard, American Express)">
                        <i class="fab fa-cc-visa atb-l-visa" aria-hidden="true"></i>
                        <i class="fab fa-cc-mastercard atb-l-mc" aria-hidden="true"></i>
                        <i class="fab fa-cc-amex atb-l-amex" aria-hidden="true"></i>
                    </button>
                    <div class="atb-kartes-panelis" id="atb-kartes-panelis" hidden>
                        <div id="atb-karte"></div>
                        <button type="button" class="atb-maksat" id="atb-maksat"><i class="fas fa-lock" aria-hidden="true"></i> <span class="atb-poga-t">Atbalstīt</span></button>
                    </div>
                </div>
                <p class="atb-statuss" id="atb-statuss" role="status" aria-live="polite"></p>
                <div class="atb-express" id="atb-express"></div>
            </div>
            <?php endif; ?>

            <?php /* Rezerve: tie paši taisnstūri, tikai karte un PayPal ved uz Stripe
                     maksājuma lapu (Revolut — uz Revolut). Iegultais režīms kartes
                     taisnstūri aizstāj ar savējo, PayPal — ar Stripe maciņu pogu. */ ?>
            <div class="atb-metodes" role="group" aria-label="Maksāšanas veids">
                <button type="submit" name="metode" value="karte" class="atb-metode" data-stripe-poga
                        aria-label="Maksāt ar karti (Visa, Mastercard, American Express)">
                    <i class="fab fa-cc-visa atb-l-visa" aria-hidden="true"></i>
                    <i class="fab fa-cc-mastercard atb-l-mc" aria-hidden="true"></i>
                    <i class="fab fa-cc-amex atb-l-amex" aria-hidden="true"></i>
                </button>
                <?php if ($atb_konf['paypal']): ?>
                <button type="submit" name="metode" value="paypal" class="atb-metode" data-stripe-poga
                        aria-label="Maksāt ar PayPal">
                    <i class="fab fa-paypal atb-l-paypal" aria-hidden="true"></i>
                    <span class="atb-pp" aria-hidden="true">Pay<b>Pal</b></span>
                </button>
                <?php endif; ?>
                <?php if ($atb_konf['revolut']): ?>
                <button type="submit" name="metode" value="revolut" class="atb-metode"
                        data-tikai-summas="<?= htmlspecialchars(implode(',', array_keys($atb_konf['revolut'])), ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="Maksāt ar Revolut">
                    <span class="atb-rev" aria-hidden="true">Revolut</span>
                </button>
                <?php endif; ?>
            </div>

            <?php /* Logo nāk no Font Awesome Brands, ko lapa jau ielādē — nav ne
                     ārēja attēla, ne pieprasījuma uz maksājumu serveriem.
                     data-rezerve / data-iegults-tikai pārslēdz ziedot_forma.js: «bez
                     e-pasta» ir patiess TIKAI iegultajā režīmā un TIKAI kartei — Stripe
                     maksājumu lapa (rezerve) e-pastu prasa vienmēr, PayPal prasa savu
                     pieslēgšanos (audits 10-20). */ ?>
            <p class="atb-note">
                <span class="atb-bez" data-iegults-tikai hidden>Ar karti — bez reģistrēšanās un bez e-pasta</span>
                <span data-rezerve>Maksājums notiek drošā</span><span data-iegults-tikai hidden>Karšu<?= $atb_konf['paypal'] ? ' un PayPal' : '' ?> maksājumus droši apstrādā</span>
                <i class="fab fa-stripe atb-stripe" aria-hidden="true"></i><span class="atb-sr">Stripe</span><span data-rezerve> lapā</span>.
                <?php if ($atb_konf['revolut']): ?>
                <br>Revolut maksājums notiek Revolut lapā
                (<?= htmlspecialchars(implode(', ', array_keys($atb_konf['revolut'])), ENT_QUOTES, 'UTF-8') ?> €).
                <?php endif; ?>
            </p>
            <?php /* Logā nav «Visiem vienādi» bloka, bet juridiskajam teikumam maksātājam
                     jābūt redzamam (uz tā stāv PVN pozīcija; rezervē to rāda Stripe lapa,
                     custom_text). Tāpēc logā — īsi zem piezīmes (audits 10-20). Noteikumi
                     jaunā cilnē: ietvarā pāreja izdzēstu MI sarunu. */ ?>
            <?php if ($atb_logs): ?>
            <p class="atb-note atb-jur"><?= htmlspecialchars(ZIEDOT_JURIDISKI, ENT_QUOTES, 'UTF-8') ?>
                <a href="/ziedot.php#noteikumi" target="_blank" rel="noopener">Samaksas noteikumi</a></p>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$atb_logs): ?>
    <?php /* Kur nauda aiziet — BEZ summām (Ģirta norāde 2026-08-31). Atbilde uz
             klusēto aizdomu "gan jau nauda aiziet kaut kur citur" strādā arī bez
             skaitļiem: svarīgi ir, KAS tiek apmaksāts, ne cik precīzi. */ ?>
    <div class="atb-card">
        <h2><i class="fas fa-coins" aria-hidden="true"></i>Kam nauda tiek izlietota</h2>
        <ul>
            <li><strong>Serveris.</strong> Vietne glabā un apstrādā datus par visiem
                Latvijā reģistrētajiem uzņēmumiem, un tie tiek pārbūvēti katru nakti.</li>
            <li><strong>MI funkcionalitāte.</strong> Katra <em>jauna</em> mākslīgā intelekta
                atbilde ir maksas vaicājums uz ārēju pakalpojumu (atkārtotas nāk no keša).</li>
            <li><strong>Domēns un datu avotu uzturēšana.</strong></li>
        </ul>
        <p class="atb-fine">
            Samaksu izmanto šo izmaksu segšanai.
        </p>
    </div>

    <?php /* Kartīte «Ko atvērtie dati dod sabiedrībai» (ar ES pētījuma skaitļiem un
             «tavs atbalsts to tur dzīvu») izņemta 2026-10-28: tas ir aicinājums atbalstīt
             sabiedrisku mērķi, ko Stripe vērtē kā līdzekļu vākšanu, nevis samaksu par
             saturu. Teksts saglabāts _Optimizacija/_arhivs-atbalsts-2026-10-27/ziedot.php. */ ?>

    <?php /* Juridiski šeit OBLIGĀTI jāpaliek skaidram, ka pretizpildījuma nav —
             uz tā balstās PVN pozīcija (EST Tolsma C-16/93: summu izvēlas maksātājs, un
             saturs nav atkarīgs no samaksas). Kopš 2026-10-28 tas pateikts ar šiem diviem
             FAKTIEM, ne ar vārdiem «nav pirkums» / «bez pretizpildījuma»: blakus «samaksai par
             saturu» tie lasījās pretrunīgi un Stripe tos lasa kā ziedojuma definīciju (audits). «Samaksa par saturu», ne «ziedojums»: ziedojumus
             Latvijas tiesībās saņem sabiedriskā labuma (SLOL 9. p. 1. d.), politiskās u. c.
             organizācijas, nevis komersants; Stripe tiešu ziedojumu vākšanu nepieņem
             (2026-10-28 — satura veidotāja «content-related tips» savā vietnē). Modelis —
             kā Guardian «contribution terms»: samaksa komersantam bez pretizpildījuma, bez
             nodokļu atvieglojuma. Guardian 14 dienu atmaksu NEPĀRŅEMAM — samaksu neatmaksā
             (Ģirta lēmums 2026-10-27). Virsrakstam nav jābūt "Neko": tas pats saturs, pateikts
             kā princips, nevis kā liegums, lasītāju neatgrūž. Noteikumi sakļauti — tie ir
             uzziņai, ne pa ceļam uz formu. */ ?>
    <div class="atb-card">
        <h2><i class="fas fa-scale-balanced" aria-hidden="true"></i>Visiem vienādi</h2>
        <p>
            Samaksa nedod nekādas priekšrocības, un tas ir apzināti. Vietnei jāstrādā
            vienādi neatkarīgi no tā, vai cilvēks var atļauties maksāt.
        </p>
        <p class="atb-fine">
            Nav abonementa, slēgtu sadaļu, agrākas piekļuves vai atrunātu pakalpojumu —
            pilnīgi viss saturs ir un paliek vienādi pieejams ikvienam.
            Samaksa ir brīvprātīga: saturs ir pieejams arī bez tās, summu izvēlas maksātājs,
            un samaksa neatver papildu saturu un nedod priekšrocības. Tāpēc samaksu
            neatmaksā.
        </p>
        <?php /* id="noteikumi" — uz šo bloku ved saite uznirstošajā logā; ziedot_forma.js
                 atver to, ja adresē ir #noteikumi. Summas robežu te NAV: Revolut un
                 rezerves saitēm ir savas, un formas 1 000 € ir pārrakstīšanās sargs,
                 ne noteikums (audits 10-27). Virsraksti apgalvojoši, ne «Nav…». */ ?>
        <details class="atb-vairak" id="noteikumi">
            <summary>Samaksas noteikumi</summary>
            <ul class="atb-noteikumi">
                <li><strong>Saņēmējs.</strong> Samaksu saņem saraksts.lv uzturētājs —
                    Valsts ieņēmumu dienestā reģistrēts saimnieciskās darbības veicējs.</li>
                <li><strong>Izlietojums.</strong> Samaksu izmanto vietnes izmaksu segšanai:
                    serverim, MI atbildēm, domēnam un datu avotiem.</li>
                <li><strong>Samaksas būtība.</strong> Samaksa ir brīvprātīga, un summu izvēlas
                    maksātājs. Tā nav priekšnoteikums piekļuvei saturam, neatver papildu saturu,
                    nedod priekšrocības un nedod tiesību ietekmēt vietnes saturu vai datus
                    par uzņēmumiem.</li>
                <li><strong>Nodokļi.</strong> Samaksa nav ziedojums sabiedriskā labuma
                    organizācijai
                    <a href="https://likumi.lv/ta/id/90822-sabiedriska-labuma-organizaciju-likums"
                       target="_blank" rel="noopener">Sabiedriskā labuma organizāciju likuma</a>
                    izpratnē, tāpēc ziedojumu nodokļu atvieglojumi uz to neattiecas.</li>
                <li><strong>Atmaksa.</strong> Tā kā saturs ir pieejams arī bez samaksas un
                    summu izvēlas maksātājs, samaksu neatmaksā (izņēmums — «Atteikums» zemāk).</li>
                <li><strong>Atteikums.</strong> Samaksu var atteikt un atmaksāt, ja tās
                    pieņemšana varētu apdraudēt vietnes neatkarību, piemēram, ja ar to mēģina
                    ietekmēt datus par kādu uzņēmumu.</li>
                <li><strong>Maksājuma dati.</strong> Kartes numuru un drošības kodu ievada
                    maksājumu pakalpojumu sniedzēja laukos — saraksts.lv tos neredz un
                    neglabā.</li>
            </ul>
        </details>
    </div>

    <?php /* Precīzi, jo lejupielade.php un NOTICE.md saka to pašu: MIT ir MANS
             kods, bet pakotnē ir arī trešo pušu daļas ar citām licencēm (piem.
             swisseph — AGPL-3.0). Vienkāršojums "viss MIT" bija pretrunā ar
             vietnes pašas licenču lapu (audits 2026-09-02). */ ?>
    <p class="atb-fine">
        Vietnes pirmkods ir publisks: pašas vietnes kods MIT licencē, atsevišķām
        trešo pušu daļām citas licences —
        <a href="/lejupielade.php">Lejupielāde</a>.
    </p>
    <?php endif; ?>

</main>

<?php /* Formas ērtības — atsevišķs defer fails (tas pats iemesls kā ziedot_ga.js:
         nogriezts inline skripts apēstu lapu). Forma strādā arī bez tā. */
$atb_js = function_exists('reg_asset_v') ? reg_asset_v('/registrs/assets/js/ziedot_forma.js')
                                         : '/registrs/assets/js/ziedot_forma.js'; ?>
<?php if ($atb_konf['iegults']): ?>
<?php /* Stripe.js JĀIELĀDĒ no js.stripe.com (PCI prasība — to nedrīkst pašu hostēt).
         Tikai šajā lapā un tikai iegultajā režīmā. async: forma to gaida pati. */ ?>
<script async src="https://js.stripe.com/v3/" id="atb-stripe-js"></script>
<?php endif; ?>
<script defer src="<?= htmlspecialchars($atb_js, ENT_QUOTES, 'UTF-8') ?>"></script>

    <?php if (!$atb_logs) { $footerRich = 'registrs'; include 'registrs/footer/footer.php'; } ?>

</body>
</html>
