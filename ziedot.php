<?php
require_once __DIR__ . '/lib/applog.php';
applog_boot('ziedot');

// ─── VIENĪGAIS, KAS JĀNOMAINA ────────────────────────────────────────────────
// Stripe Payment Link. Kamēr tukšs, poga rādās neaktīva un lapa par to pasaka.
//
// PUBLISKAJĀ IZLAIDUMĀ ŠĪ VĒRTĪBA IR NOŅEMTA. Ieliec savu saiti no
// dashboard.stripe.com → Payments → Payment Links. Ja atstāj tukšu, lapa
// darbojas, tikai poga ir neaktīva.
const ZIEDOT_STRIPE_SAITE = '';
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Ziedot — atbalstīt saraksts.lv uzturēšanu";
$pageDesc  = "Saraksts.lv ir bezmaksas un bez reklāmām. Lielākās uzturēšanas izmaksas ir serveris un MI funkcionalitāte. Ja vietne noderēja, to var atbalstīt ar brīvprātīgu ziedojumu. Pretī netiek dots nekas.";
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
/* Atstatums no pogas — vienas teksta rindas augumā, lai piezīme nelīp klāt.
   .atb-card p ir specifiskāks par .atb-note, tāpēc izmērs jāraksta ar to pašu
   specifiskumu, citādi 13,5 px nekad nenostrādā. */
.atb-card p.atb-note { font-size: 13.5px; color: #777; margin: 30px 0 0; line-height: 1.55; }
/* Vārdzīmes glifā ir daudz tukšuma virs un zem, tāpēc optiski tā izskatās mazāka
   nekā fonta izmērs sola — 2,6em vajag, lai tā līdzinātos blakus tekstam. */
.atb-stripe { color: #635BFF; font-size: 2.6em; vertical-align: -7px; }
/* Tikai ekrānlasītājam: vietnē globālas .sr-only klases nav, tāpēc sava. */
.atb-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
          overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

@media (prefers-reduced-motion: reduce) {
    .atb-tvaiks { display: none; }
    .atb-btn:hover { transform: none; }
}
.atb-fine { font-size: 13.5px; color: #666; line-height: 1.65; }
.atb-fine strong { color: #444; }
@media (max-width: 600px) {
    .atb-wrap h1 { font-size: 25px; }
    .atb-card { padding: 20px; }
}
</style>

<main class="atb-wrap">

    <h1>Ziedot vietnes uzturēšanai</h1>

    <?php /* Vadošais motīvs ir SAVSTARPĪBA, ne trūkums: šī nav labdarība, kur
             palīdz svešiniekam — lasītājs pats tikko saņēma vērtību. Pētījumos
             savstarpība ir spēcīgākais ziedošanas dzinulis, tāpēc tā ir pirmajā
             teikumā, nevis pieminēta garāmejot beigās. */ ?>
    <p class="atb-lead">
        Saraksts.lv ir bezmaksas, bez reklāmām un bez reģistrēšanās. Tāda tā arī paliks —
        arī tiem, kas neziedo. Ja vietne tev noderēja, tās uzturēšanu
        var atbalstīt. <strong>Arī daži eiro palīdz</strong>, un mazs ziedojums nav
        nekas mazāk vērtīgs par lielu.
    </p>

    <div class="atb-card atb-card-cta">
        <div class="atb-cta">
            <span class="atb-tvaiks" aria-hidden="true"><i></i><i></i><i></i></span>
            <?php if (ZIEDOT_STRIPE_SAITE !== ''): ?>
            <a class="atb-btn"
               href="<?php echo htmlspecialchars(ZIEDOT_STRIPE_SAITE, ENT_QUOTES, 'UTF-8'); ?>"
               target="_blank" rel="noopener">
                <i class="fas fa-mug-hot" aria-hidden="true"></i>Ziedot
            </a>
            <?php else: ?>
            <span class="atb-btn atb-off">
                <i class="fas fa-mug-hot" aria-hidden="true"></i>Ziedot
            </span>
            <p class="atb-note"><em>Maksājumu saite nav pievienota —
               ieliec savu Stripe Payment Link konstantē <code>ZIEDOT_STRIPE_SAITE</code>
               faila <code>ziedot.php</code> augšā.</em></p>
            <?php endif; ?>

            <?php /* Logo nāk no Font Awesome Brands, ko lapa jau ielādē — nav ne
                     ārēja attēla, ne pieprasījuma uz Stripe serveriem. Vārdzīme
                     aizstāj vārdu, tāpēc ekrānlasītājam nosaukums ir atsevišķi. */ ?>
            <p class="atb-note">
                Summu izvēlies pats. Maksājumu apstrādā
                <i class="fab fa-stripe atb-stripe" aria-hidden="true"></i><span class="atb-sr">Stripe</span>.
            </p>
        </div>
    </div>

    <div class="atb-card">
        <h2><i class="fas fa-coins" aria-hidden="true"></i>Kam nauda tiek izlietota</h2>
        <p>Divas pozīcijas aizņem lielāko daļu izmaksu:</p>
        <ul>
            <li><strong>Serveris.</strong> Vietne glabā un apstrādā datus par visiem
                Latvijā reģistrētajiem uzņēmumiem, un tie tiek pārbūvēti katru nakti.</li>
            <li><strong>MI funkcionalitāte.</strong> Katra mākslīgā intelekta atbilde
                ir maksas vaicājums uz ārēju pakalpojumu. Jo vairāk cilvēku to lieto,
                jo lielākas izmaksas.</li>
        </ul>
        <p class="atb-fine">Pārējais — domēni un datu avotu uzturēšana.</p>
    </div>

    <?php /* Juridiski šeit OBLIGĀTI jāpaliek skaidram, ka pretizpildījuma nav —
             uz tā balstās gan PVN pozīcija (EST Tolsma C-16/93), gan Stripe
             kategorijas izvēle. Bet virsrakstam nav jābūt "Neko": tas pats saturs,
             pateikts kā princips, nevis kā liegums, lasītāju neatgrūž. */ ?>
    <div class="atb-card">
        <h2><i class="fas fa-scale-balanced" aria-hidden="true"></i>Visiem vienādi</h2>
        <p>
            Ziedojums nedod nekādas priekšrocības, un tas ir apzināti. Vietnei jāstrādā
            vienādi neatkarīgi no tā, vai cilvēks var atļauties maksāt.
        </p>
        <p class="atb-fine">
            Nav abonementa, slēgtu sadaļu, agrākas piekļuves vai atrunātu pakalpojumu —
            pilnīgi viss saturs ir un paliek vienādi pieejams arī tiem, kas nav ziedojuši.
            Šis ir brīvprātīgs ziedojums, nevis pirkums: pretī netiek sniegtas preces,
            pakalpojumi vai priekšrocības, un tāpēc ziedojumu neatmaksā.
        </p>
    </div>

    <p class="atb-fine">
        Vietnes pirmkods ir publisks un pieejams MIT licencē —
        <a href="/lejupielade.php">Lejupielāde</a>.
    </p>

</main>

    <?php $footerRich = 'registrs'; include 'registrs/footer/footer.php'; ?>
</body>
</html>
