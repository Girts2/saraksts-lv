<?php
require_once __DIR__ . '/lib/applog.php';
applog_boot('ziedot');

// ─── VIENĪGAIS, KAS JĀNOMAINA ────────────────────────────────────────────────
// Stripe Payment Link. Kamēr tukšs, poga rādās neaktīva un lapa par to pasaka.
//
// PUBLISKAJĀ IZLAIDUMĀ ŠĪ VĒRTĪBA IR NOŅEMTA. Ieliec savu saiti no
// dashboard.stripe.com → Payments → Payment Links. Ja atstāj tukšu, lapa
// darbojas, tikai poga ir neaktīva.
const ZIEDOT_STRIPE_SAITE = 'https://donate.stripe.com/aFa5kwbTF7xage77SUaAw00';

// Ieteiktās summas. Katrai vajag ATSEVIŠĶU Payment Link ar fiksētu cenu, jo
// Stripe Payment Links summu caur URL parametru nepieņem (pārbaudīts 2026-08-31:
// atbalstīti tikai prefilled_email, client_reference_id, promo kods un utm_*).
//
// Secība ir apzināta — lielākā pirmā: pirmā redzētā summa kļūst par enkuru, pret
// ko cilvēks vērtē pārējās, un tas ceļ vidējo ziedojumu. Summu NEATZĪMĒT kā
// "populārāko", kamēr nav īstu ziedojumu datu — izdomāts sociālais pierādījums
// ir meli, un tie te maksā vairāk, nekā dod.
//
// Kamēr saites tukšas, lapa rāda vienu pogu ar brīvi izvēlamu summu (kā līdz šim).
const ZIEDOT_SUMMU_SAITES = [
    10 => 'https://buy.stripe.com/8x24gs5vh9Fi0f9b56aAw01',
    5  => 'https://buy.stripe.com/bJe28ke1N7xa8LF8WYaAw02',
    3  => 'https://buy.stripe.com/00w14g3n96t6aTNgpqaAw03',
];
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Atbalstīt saraksts.lv uzturēšanu";
$pageDesc  = "Saraksts.lv ir bezmaksas un bez reklāmām. Lielākās uzturēšanas izmaksas ir serveris un MI funkcionalitāte. Ja vietne noderēja, to var atbalstīt brīvprātīgi — arī daži eiro palīdz.";
?>
<!DOCTYPE html>
<html lang="lv">

<?php include 'registrs/head/head.php'; ?>

<body>
    <?php include 'registrs/header.php'; ?>

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
/* Ziedojuma kartīte ir vienīgā silti tonētā lapā — pārējais paliek vēss un lietišķs.
   Siltums te ir pieļaujams tāpēc, ka tā ir vienīgā vieta, kur cilvēkam kaut ko
   piedāvā, nevis rāda datus. */
.atb-card-cta {
    background:
        radial-gradient(120% 90% at 50% 0%, #fff8e8 0%, rgba(255,248,232,0) 70%),
        linear-gradient(180deg, #fffdf8, #fff);
    border-color: #efe4cd;
}
.atb-cta { position: relative; text-align: center; padding: 18px 0 2px; }

/* Tvaiks virs pogas — sasaucas ar kafijas krūzes ikonu. Lēns un blāvs;
   pie prefers-reduced-motion pazūd pavisam. */
.atb-tvaiks { position: absolute; top: -6px; left: 50%; transform: translateX(-50%);
              width: 74px; height: 30px; pointer-events: none; }
/* Iegareni un izpludināti, nevis apļi — apļi lasās kā putekļu graudi, ne kā tvaiks.
   Šūpošanās sānis dod dzīvīgumu, kāds tvaikam ir dabā. */
.atb-tvaiks i {
    position: absolute; bottom: 0; width: 7px; height: 16px;
    border-radius: 50%;
    background: linear-gradient(to top, rgba(190,166,120,.6), rgba(190,166,120,0));
    filter: blur(2.5px);
    opacity: 0;
    animation: atb-tvaiks 5.4s ease-in-out infinite;
}
.atb-tvaiks i:nth-child(1) { left: 18px; animation-delay: 0s;   }
.atb-tvaiks i:nth-child(2) { left: 34px; animation-delay: 1.8s; }
.atb-tvaiks i:nth-child(3) { left: 50px; animation-delay: 3.6s; }
@keyframes atb-tvaiks {
    0%   { transform: translateY(10px) translateX(0)    scaleY(.6);  opacity: 0;   }
    20%  {                                                            opacity: .75; }
    55%  { transform: translateY(-6px) translateX(-4px) scaleY(1.15);               }
    100% { transform: translateY(-26px) translateX(4px) scaleY(1.7);  opacity: 0;   }
}

.atb-btn {
    position: relative;
    display: inline-flex; align-items: center; gap: 10px;
    background: #4CAF50; color: #fff; text-decoration: none;
    font-size: 17px; font-weight: 700; padding: 14px 30px; border-radius: 8px;
    box-shadow: 0 4px 14px rgba(76,175,80,.28);
    transition: background .2s, transform .2s, box-shadow .2s;
}
.atb-btn:hover { background: #43a047; transform: translateY(-2px);
                 box-shadow: 0 7px 20px rgba(76,175,80,.36); }
.atb-btn.atb-off { background: #b9bec6; cursor: not-allowed; pointer-events: none;
                   box-shadow: none; }
/* Vērtības teikums tieši virs pogas — mikro-kopijas pētījumos šī ir ienesīgākā
   vieta lapā: tas atbild uz "kāpēc tieši tagad" pēdējā mirklī pirms lēmuma. */
.atb-virs { text-align: center; margin: 0 0 10px; }

/* Summu pogas. Vienāds izmērs visām: atšķirīgs izmērs vai krāsa nozīmētu, ka
   viena summa ir "pareizā", un tas ir spiediens, ne izvēle. Pieskāriena zona
   ≥44 px arī uz telefona. */
.atb-summas { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.atb-summa {
    box-sizing: border-box; /* bez tā iekšējā atkāpe pieskaitās platumam un šaurā ekrānā rindā ietilpst tikai viena poga */
    min-width: 88px; padding: 13px 20px; border-radius: 8px;
    background: #2e7d32; color: #fff; text-decoration: none;
    font-size: 17px; font-weight: 700; text-align: center;
    box-shadow: 0 3px 10px rgba(46,125,50,.2);
    transition: background .2s, transform .2s, box-shadow .2s;
}
.atb-summa:hover { background: #276b2b; transform: translateY(-2px);
                   box-shadow: 0 6px 16px rgba(46,125,50,.3); color: #fff; }
/* "Cita summa" ir izeja, ne piedāvājums — klusāka, lai neatvelk uzmanību no
   ieteiktajām, bet paliek acīmredzami pieejama tiem, kas grib citu skaitli. */
/* Robeža #7f88ad, ne gaišāka: #cfd4e6 uz kartītes fona deva izmērītus 1,41:1,
   un WCAG 1.4.11 saskarnes elementam prasa 3:1 — poga tad nav atšķirama no
   teksta (audits 2026-09-02). Tagad 3,43:1. */
.atb-summa-cita {
    background: #fff; color: #206a25; border: 1px solid #7f88ad;
    font-size: 15px; font-weight: 600; box-shadow: none;
}
.atb-summa-cita:hover { background: #f3f6ff; color: #276b2b; }
/* Telefonā trīs summas vienā rindā (vienādi platas, mazāka sānu atkāpe), bet
   "Cita summa" atsevišķā rindā zem tām — tā izvēle paliek viena acu kustība. */
@media (max-width: 600px) {
    .atb-summa { flex: 1 1 0; min-width: 0; padding: 13px 6px; }
    .atb-summa-cita { flex: 0 0 100%; padding: 12px; }
}
/* Atstatums no pogas — vienas teksta rindas augumā, lai piezīme nelīp klāt.
   .atb-card p ir specifiskāks par .atb-note, tāpēc izmērs jāraksta ar to pašu
   specifiskumu, citādi 13,5 px nekad nenostrādā. */
/* #6b6b6b, ne #777: pēdējais deva izmērītus 4,26:1 (uz balta 4,48) — zem AA
   sliekšņa 4,5:1 pat labākajā gadījumā (audits 2026-09-02). Tagad 5,33:1. */
.atb-card p.atb-note { font-size: 13.5px; color: #6b6b6b; margin: 30px 0 0; line-height: 1.55; }
/* Vārdzīmes glifā ir daudz tukšuma virs un zem, tāpēc optiski tā izskatās mazāka
   nekā fonta izmērs sola — 2,6em vajag, lai tā līdzinātos blakus tekstam. */
.atb-stripe { color: #635BFF; font-size: 2.6em; vertical-align: -7px; }
/* Tikai ekrānlasītājam: vietnē globālas .sr-only klases nav, tāpēc sava. */
.atb-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
          overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

@media (prefers-reduced-motion: reduce) {
    .atb-tvaiks { display: none; }
    .atb-btn:hover,
    .atb-summa:hover { transform: none; }
}
.atb-fine { font-size: 13.5px; color: #666; line-height: 1.65; }
.atb-fine strong { color: #444; }
@media (max-width: 600px) {
    .atb-wrap h1 { font-size: 25px; }
    .atb-card { padding: 20px; }
}
</style>

<main class="atb-wrap">

    <h1>Atbalsti vietnes darbību</h1>

    <?php /* Vadošais motīvs ir SAVSTARPĪBA, ne trūkums: šī nav labdarība, kur
             palīdz svešiniekam — lasītājs pats tikko saņēma vērtību. Pētījumos
             savstarpība ir spēcīgākais ziedošanas dzinulis, tāpēc tā ir pirmajā
             teikumā, nevis pieminēta garāmejot beigās. */ ?>
    <p class="atb-lead">
        Saraksts.lv ir bezmaksas, bez reklāmām un bez reģistrēšanās, un vietnes
        vienīgais ienākumu avots ir lietotāju atbalsts. Ja vietne tev noderēja, tās uzturēšanu
        vari atbalstīt arī tu. <strong>Arī daži eiro palīdz</strong>, un mazs atbalsts nav
        nekas mazāk vērtīgs par lielu.
    </p>

    <div class="atb-card atb-card-cta">
        <?php /* Trīsdaļu mikro-kopija ap pogu (teksts virs → konkrēta poga →
                 nomierinājums zem) ir pārbaudītākā ziedojumu saskarnes forma.
                 Summas šeit NENOSAUC (Ģirta norāde 2026-08-31): konkrēts skaitlis
                 kļūst par enkuru, un lasītājs sāk vērtēt to, nevis pašu atbalstu.
                 Vietas, kur summas tomēr vajadzīgas, ir Stripe formā. */ ?>
        <?php /* Formulējums apzināti par IENĀKUMU AVOTU, ne par to, ka ziedojumi
                 izmaksas jau sedz: pēdējais tagadnes formā būtu nepatiess, kamēr
                 ziedojumu apjoms ir mazs (audits 2026-09-02). */ ?>
        <p class="atb-virs">
            Tavs atbalsts apmaksā serveri, datu atjaunošanu un MI atbildes.
        </p>
        <div class="atb-cta">
            <span class="atb-tvaiks" aria-hidden="true"><i></i><i></i><i></i></span>
            <?php
            // Rāda summu pogas tikai tad, ja to saites tiešām ir ieliktas; citādi
            // paliek viena poga ar brīvi izvēlamu summu (uzvedība kā līdz šim).
            //
            // Pārbauda gan saiti, gan ATSLĒGU: null/false/atstarpes izlaistu
            // vaļīgāks filtrs un dotu href="" (pārlādē to pašu lapu), bet
            // nevesela atslēga ('2.50') pogā parādītos kā "2 €", kamēr Stripe
            // iekasētu 2,50 € — teksts un maksājums izšķirtos (audits 2026-09-02).
            $atb_summas = [];
            foreach (ZIEDOT_SUMMU_SAITES as $eur => $saite) {
                if (!is_int($eur) || $eur <= 0) continue;
                if (!is_string($saite) || trim($saite) === '') continue;
                $atb_summas[$eur] = trim($saite);
            }
            ?>
            <?php /* aria-label katrai pogai: ekrānlasītāja saišu sarakstā "10 €"
                     bez darbības vārda un konteksta nav saprotams, un jauna cilne
                     bez brīdinājuma dezorientē (audits 2026-09-02). */ ?>
            <?php if ($atb_summas): ?>
            <div class="atb-summas" role="group" aria-label="Atbalsta summa">
                <?php foreach ($atb_summas as $eur => $saite): ?>
                <a class="atb-summa" data-atb-summa="<?= (int)$eur ?>" href="<?= htmlspecialchars($saite, ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener"
                   aria-label="Atbalstīt ar <?= (int)$eur ?> € — atveras jaunā cilnē"><?= (int)$eur ?>&nbsp;€</a>
                <?php endforeach; ?>
                <?php if (ZIEDOT_STRIPE_SAITE !== ''): ?>
                <a class="atb-summa atb-summa-cita" data-atb-summa="cita"
                   href="<?= htmlspecialchars(ZIEDOT_STRIPE_SAITE, ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener"
                   aria-label="Atbalstīt ar citu summu — atveras jaunā cilnē">Cita summa</a>
                <?php endif; ?>
            </div>
            <?php elseif (ZIEDOT_STRIPE_SAITE !== ''): ?>
            <a class="atb-btn" data-atb-summa="cita"
               href="<?php echo htmlspecialchars(ZIEDOT_STRIPE_SAITE, ENT_QUOTES, 'UTF-8'); ?>"
               target="_blank" rel="noopener">
                <i class="fas fa-mug-hot" aria-hidden="true"></i>Atbalstīt vietnes darbību
            </a>
            <?php else: ?>
            <span class="atb-btn atb-off">
                <i class="fas fa-mug-hot" aria-hidden="true"></i>Atbalstīt vietnes darbību
            </span>
            <p class="atb-note"><em>Maksājumu saite nav pievienota —
               ieliec savu Stripe Payment Link konstantē <code>ZIEDOT_STRIPE_SAITE</code>
               faila <code>ziedot.php</code> augšā.</em></p>
            <?php endif; ?>

            <?php /* Logo nāk no Font Awesome Brands, ko lapa jau ielādē — nav ne
                     ārēja attēla, ne pieprasījuma uz Stripe serveriem. Vārdzīme
                     aizstāj vārdu, tāpēc ekrānlasītājam nosaukums ir atsevišķi. */ ?>
            <?php /* Nomierinājums zem pogas mazina pēdējā mirkļa šaubas par
                     maksājuma drošību — bez summām, tās izvēlas cilvēks pats. */ ?>
            <p class="atb-note">
                Summu izvēlies pats — arī citu, ne tikai piedāvāto. Drošu maksājumu apstrādā
                <i class="fab fa-stripe atb-stripe" aria-hidden="true"></i><span class="atb-sr">Stripe</span>.
            </p>
        </div>
    </div>

    <?php /* Kur nauda aiziet — BEZ summām (Ģirta norāde 2026-08-31). Atbilde uz
             klusēto aizdomu "gan jau nauda aiziet kaut kur citur" strādā arī bez
             skaitļiem: svarīgi ir, KAS tiek apmaksāts, ne cik precīzi. */ ?>
    <div class="atb-card">
        <h2><i class="fas fa-coins" aria-hidden="true"></i>Kam nauda tiek izlietota</h2>
        <ul>
            <li><strong>Serveris.</strong> Vietne glabā un apstrādā datus par visiem
                Latvijā reģistrētajiem uzņēmumiem, un tie tiek pārbūvēti katru nakti.</li>
            <li><strong>MI funkcionalitāte.</strong> Katra <em>jauna</em> mākslīgā intelekta
                atbilde ir maksas vaicājums uz ārēju pakalpojumu (atkārtotas nāk no keša).
                Izmaksas aug līdz ar lietotāju skaitu — vietnes izaugsme burtiski
                maksā naudu.</li>
            <li><strong>Domēns un datu avotu uzturēšana.</strong></li>
        </ul>
        <p class="atb-fine">
            Atbalsts iet tieši šo izmaksu segšanai.
        </p>
    </div>

    <?php /* Sabiedrības līmeņa pamatojums: atbalsts šeit tur dzīvu ne tikai vienu
             vietni, bet atvērto datu ķēdes pēdējo posmu — dati vērtību rada tikai
             tad, kad tos kāds padara lietojamus. Skaitļi no ES pētījuma ar saitēm;
             tos neatjauno lib konstantes, jo tie nav mūsu mērījumi. */ ?>
    <div class="atb-card">
        <h2><i class="fas fa-unlock" aria-hidden="true"></i>Ko atvērtie dati dod sabiedrībai</h2>
        <ul>
            <li><strong>Drošāki darījumi.</strong> Pirms līguma vari pārbaudīt, kas
                īsti ir otrā puse — mazāk krāpšanas un fiktīvu firmu upuru.</li>
            <li><strong>Godīga konkurence.</strong> Mazajam uzņēmējam pieejama
                lielākā daļa tās pašas informācijas, kas lielajiem ar dārgiem
                abonementiem.</li>
            <li><strong>Caurspīdīgums.</strong> Ikviens var pārbaudīt uzņēmuma
                amatpersonas, dalībniekus un gada pārskatus. Uzņēmumu pamatdatus
                un pārskatus ES ar Atvērto datu direktīvu ir noteikusi par
                augstvērtīgu datu kopu, kas visās dalībvalstīs jādara pieejama
                bez maksas un mašīnlasāmi.</li>
        </ul>
        <?php /* Skaitļiem OBLIGĀTI jābūt ar gadu un scenāriju: bez tiem lasītājs
                 2019. gada mērījumu lasa kā šodienas, bet optimistisko prognozi
                 kā vienīgo (audits 2026-09-02). Latvijas skaitlis ir MŪSU
                 ekstrapolācija pēc pētījuma metodes, ne stratēģijas dati, tāpēc
                 stratēģijas saite vairs nestāv aiz tā kā avots. */ ?>
        <p>
            Tam ir arī izmērāma ekonomiskā puse:
            <a href="https://op.europa.eu/en/publication-detail/-/publication/1021d8a7-5782-11ea-8b81-01aa75ed71a1"
               target="_blank" rel="noopener">ES pētījums</a> atvērto datu tirgu
            ES un EBTA valstīs <strong>2019. gadā</strong> vērtēja 184 miljardos €
            jeb aptuveni 1,2&nbsp;% no IKP; 2025. gada prognoze bija no 200
            miljardiem bāzes scenārijā līdz 334 miljardiem optimistiskajā.
            Pēc tās pašas metodes — daļa no iekšzemes kopprodukta — Latvijai tas
            nozīmētu ap pusmiljardu € gadā.
        </p>
        <p class="atb-fine">
            Latvijas atvērto datu politiku nosaka
            <a href="https://likumi.lv/ta/id/342401-latvijas-atverto-datu-strategija"
               target="_blank" rel="noopener">Latvijas atvērto datu stratēģija</a>.
        </p>
        <p class="atb-fine">
            Dati paši par sevi vērtību nerada — tā rodas, kad tos kāds padara
            saprotamus un lietojamus. Saraksts.lv ir šīs ķēdes pēdējais posms,
            un tavs atbalsts to tur dzīvu.
        </p>
    </div>

    <?php /* Juridiski šeit OBLIGĀTI jāpaliek skaidram, ka pretizpildījuma nav —
             uz tā balstās gan PVN pozīcija (EST Tolsma C-16/93), gan Stripe
             kategorijas izvēle. Bet virsrakstam nav jābūt "Neko": tas pats saturs,
             pateikts kā princips, nevis kā liegums, lasītāju neatgrūž. */ ?>
    <div class="atb-card">
        <h2><i class="fas fa-scale-balanced" aria-hidden="true"></i>Visiem vienādi</h2>
        <p>
            Atbalsts nedod nekādas priekšrocības, un tas ir apzināti. Vietnei jāstrādā
            vienādi neatkarīgi no tā, vai cilvēks var atļauties maksāt.
        </p>
        <p class="atb-fine">
            Nav abonementa, slēgtu sadaļu, agrākas piekļuves vai atrunātu pakalpojumu —
            pilnīgi viss saturs ir un paliek vienādi pieejams ikvienam.
            Šis ir brīvprātīgs ziedojums, nevis pirkums: pretī netiek sniegtas preces,
            pakalpojumi vai priekšrocības, un tāpēc ziedojumu neatmaksā.
        </p>
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

</main>

    <?php $footerRich = 'registrs'; include 'registrs/footer/footer.php'; ?>

</body>
</html>
