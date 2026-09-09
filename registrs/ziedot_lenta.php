<?php
/**
 * registrs/ziedot_lenta.php — ziedojumu josla tieši zem galvenes.
 *
 * Iekļauj header.php beigās, tāpēc parādās visās lapās, kas galveni izmanto.
 *
 * VIENS teikums vienā brīdī, nevis ritoša lenta (Ģirta 2026-08-31 norāde):
 * ritošajā rindā vairāki teikumi ar dažādām summām (2,34 / 2,50 / 5 €) jauca
 * cits citu kā konkurējoši enkuri, un kustīgu tekstu ir grūti lasīt. Tagad
 * Wikipedia banneru shēma — viena pilna uzruna, ko ik pa brīdim nomaina
 * nākamā; CENAS JOSLĀ NERĀDA vispār, skaitļi paliek ziedot.php lapā.
 * Blakusieguvums: bez kustības vairs nevajag ne reduced-motion rezerves
 * rindu, ne Mac/Windows atšķirību — visi redz vienu un to pašu.
 *
 * APZINĀTI parastajā plūsmā, NEVIS fiksētajā galvenē: (1) fiksētajā būtu
 * jāmaina body padding-top, kas jau tāpat ir trausls (skat. mērskriptu
 * header.php beigās); (2) plūsmā josla aizslīd prom līdz ar ritināšanu —
 * redzama ierodoties, tālāk netraucē.
 *
 * "Atbalstīt" poga ir fiksēta labajā malā; arī viss teksta apgabals ir saite
 * uz to pašu lapu — teikumam pa vidu trāpīt konkrētam vārdam nevar prasīt.
 *
 * Nerādās uz pašas ziedot.php (cilvēks jau ir galamērķī) un tad, ja lietotājs
 * to ir aizvēris (localStorage).
 */
if (basename($_SERVER['PHP_SELF']) === 'ziedot.php') return;

// Teikumi, ko rāda CITU PĒC CITA (ne visus reizē). Pilnas uzrunas Wikipedia
// stilā — teikums pasaka, KO no lasītāja grib. Par uzturētāju NERUNĀ (Ģirta
// norāde 2026-08-31): žēluma svira ir manipulācija.
//
// TEIKUMIEM JĀBŪT APGALVOJOŠIEM, NE NOLIEDZOŠIEM (Ģirta norāde 2026-09-02):
// "vietni neapmaksā ne reklāmas, ne maksas sadaļas" pieminēja nemaksāšanu trīs
// reizes vienā rindā un tā vietā, lai rādītu, ko atbalsts DOD, lika domāt par
// to, ka maksāt nevajag. Tagad katrs teikums nosauc atbalsta rezultātu ("tavs
// atbalsts apmaksā...", "tu palīdzi..."). Vienlaikus tie paliek patiesi arī pie
// maza atbalsta apjoma: tie saka, ko atbalsts dara, nevis ka izmaksas jau segtas.
// Sākuma teikumu izvēlas pēc lapu skaitītāja, tāpēc atkārtots apmeklētājs
// nesāk vienmēr ar to pašu.
$zl_teikumi = [
    'Ja saraksts.lv tev šodien noderēja, atbalsti tā uzturēšanu — arī daži eiro palīdz.',
    'Tavs atbalsts apmaksā vietnes serverus, datus un MI atbildes.',
    'Ar atbalstu tu palīdzi vietnei palikt atvērtai visiem un bez reklāmām.',
];
// UZTURĒŠANAS IZMAKSU SKAITĻUS ŠEIT NELIKT (Ģirta norāde 2026-09-02). Agrāk te
// bija teikums "Ap N ziedojumiem ... nosegtu uzturēšanu veselu gadu", ko rēķināja
// lib/ziedot_izmaksas.php — bet no tā skaitļa var atvasināt, cik vietne maksā
// mēnesī, un tas nav publiskojams. Līdz ar to nav vajadzīga arī pati bibliotēka.

// Otrs komplekts BIEŽAJAM lietotājam (skat. slieksni skriptā): apzinātam
// brīvbraucējam misijas atgādinājums neko nedod — viņa aprēķinu lauž personiskā
// bilance ({N} = viņa paša atvērto lapu skaits šajā pārlūkā), fakts, ka katra
// MI atbilde tiešām maksā, un izšķirošā mazākuma patiesība. Skaitītājs dzīvo
// tikai localStorage — uz serveri nekas neaiziet.
// Trešais teikums ("atbalsta mazāk nekā viens no simta") IZŅEMTS 2026-09-02:
// ziedotāju skaita datu nav, tātad tas bija apgalvojums par faktu bez avota —
// tieši tas, ko aizliedz pašu noteikums par izdomātu sociālo pierādījumu. Turklāt
// negatīvs sociālais pierādījums ("gandrīz neviens to nedara") pētījumos ziedojumus
// samazina. Atgriezt drīkst tikai ar īstu skaitli no Stripe.
$zl_teikumi_biezajam = [
    'Šī ir tava {N}. atvērtā lapa šeit — ja vietne tev regulāri palīdz, atbalsti tās uzturēšanu.',
    'Katra jauna MI atbilde vietnei maksā reālu naudu — izmaksas aug līdz ar lietotājiem.',
];
?>
<style>
/* "Basic" (Google Fonts, OFL licence, © Sorkin Type Co) — apaļīgs, draudzīgs
   groteskas fonts joslas uzrunai. Failus hostējam PAŠI (assets/fonts/, ~24 KB
   kopā, licence turpat Basic-OFL.txt), lai lapa nepievieno vēl vienu ārēju
   pieprasījumu. Kopš 2026-09-02 tas pats attiecas uz Inter un Source Sans 3
   (deklarācijas assets/css/_variables.css) — agrākā piezīme, ka pašhostēšana
   vietni no Google pieprasījumiem neatbrīvo, vairs nav spēkā lapām, kas iet
   caur head/head.php. Ārpus tā palikušas trīs lapas ar savu <head>:
   horoskops.php, nozare.php, mi.php.
   latin-ext fails sedz visas latviešu diakritikas — pārbaudīts pret fonta
   pārklājuma kartēm pirms pievienošanas. */
@font-face{
    font-family:'Basic';font-style:normal;font-weight:400;font-display:swap;
    src:url('/registrs/assets/fonts/basic-latin-ext.woff2') format('woff2');
    unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF;
}
@font-face{
    font-family:'Basic';font-style:normal;font-weight:400;font-display:swap;
    src:url('/registrs/assets/fonts/basic-latin.woff2') format('woff2');
    unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
}
/* Metriku saskaņots rezerves fonts, kamēr Basic vēl ielādējas. Bez tā rindu
   skaits nomaiņas brīdī mainījās un saturs zem joslas lēca: IZMĒRĪTS 320 px
   platumā — Verdana 71 px, Basic 52 px trim no sešiem teikumiem (audits
   2026-09-02). Arial ir tuvākais (Basic/Arial platumu attiecība 95,8 %,
   Verdana tikai 84,5 %), tāpēc rezervi ņemam no Arial ar size-adjust.
   Rindas augstums CSS ir fiksēts (1.4), tāpēc vertikālās metrikas nav jālabo. */
@font-face{
    font-family:'Basic rezerve';
    src:local('Arial'),local('Helvetica Neue'),local('Liberation Sans');
    size-adjust:95.8%;
}
.zl-josla{
    display:flex;align-items:stretch;
    background:#eef0f9;border-bottom:1px solid #dcdfee;
    font-size:13.5px;color:#2a2350;
}
.zl-josla[hidden]{display:none}
/* Aizvērtā stāvokļa slēpšana notiek CSS līmenī pēc head.php uzliktās klases —
   tā josla neienāk izkārtojumā pēc pirmā zīmējuma (skat. piezīmi head.php). */
html.zl-slepts .zl-josla{display:none}

/* Teksta apgabals ir saite uz atbalsta lapu — klikšķis jebkur joslā = poga. */
.zl-logs{
    flex:1;min-width:0;display:flex;align-items:center;
    color:inherit;text-decoration:none;cursor:pointer;
}
.zl-logs:hover{text-decoration:none;color:inherit}

/* Uzruna — vienīgā vieta galvenes joslā ar "Basic" fontu un zaļo: draudzīga,
   personiska balss, kas apzināti atšķiras no lietišķā datu izkārtojuma apkārt.
   Zaļā saime tā pati, kas "Atbalstīt" pogai, tikai tumšāka: pogas #2e7d32 uz
   #eef0f9 fona izmērīti dod tikai 4,51:1 (AA robeža), #206a25 dod 5,86:1.
   Svars tikai 400 — "Basic" citu nepiedāvā, un sintētiskais biezinājums
   apaļīgo formu izķēmotu. box-sizing dēļ JS augstuma izlīdzināšanas (zemāk). */
.zl-teksts{
    display:block;width:100%;box-sizing:border-box;
    padding:9px 14px;text-align:center;line-height:1.4;
    font-family:'Basic','Basic rezerve',Arial,sans-serif;
    font-weight:400;font-size:14px;color:#206a25;
    transition:opacity .45s ease;
}
.zl-teksts.zl-maina{opacity:0}
@media (prefers-reduced-motion:reduce){ .zl-teksts{transition:none} }

/* Fiksētā poga — vienmēr redzama, vienmēr noklikšķināma. Tā pati zaļā, kas
   teikumam (#206a25 = 5,86:1): pogas #2e7d32 uz šī fona deva izmērītus 4,51:1,
   t.i. AA ar 0,01 rezervi (audits 2026-09-02). */
.zl-cta{
    flex-shrink:0;display:inline-flex;align-items:center;gap:7px;
    padding:0 14px;border-left:1px solid #dcdfee;
    color:#206a25;font-weight:700;text-decoration:none;white-space:nowrap;
    transition:background .2s;
}
.zl-cta:hover{background:#e3e7f5;text-decoration:underline}
.zl-cta i{font-size:.95em}

/* Kreisajā malā, atdalīts ar līniju — pretējā galā no "Atbalstīt" pogas.
   Krāsa #5a5f7a, ne gaišāka: iepriekšējais #8b90ad uz #eef0f9 deva izmērītus
   2,76:1 (WCAG prasa 4,5:1), tagad 5,51:1. Platums 44 px = minimālā pieskāriena
   zona; agrāk mobilajā tas saruka līdz 28 px (audits 2026-09-02). */
.zl-aizvert{
    flex-shrink:0;width:44px;background:none;cursor:pointer;
    border:0;border-right:1px solid #dcdfee;
    color:#5a5f7a;font-size:17px;line-height:1;padding:0;
}
.zl-aizvert:hover{color:#2a2350;background:#e3e7f5}

@media (max-width:600px){
    .zl-josla{font-size:12.5px}
    .zl-teksts{font-size:13px;padding:8px 10px}
    .zl-cta{padding:0 10px;gap:5px}
}
</style>

<?php /* BEZ `hidden`: josla ir redzama jau pirmajā zīmējumā, un aizvērtajiem to
         noslēpj head.php uzliktā klase html.zl-slepts. Tā izkārtojums ir gatavs
         uzreiz, nevis mainās, kad nostrādā skripts. */ ?>
<div class="zl-josla" id="zl-josla">
  <?php /* × ir KREISAJĀ malā, tālu no "Atbalstīt": blakus stāvot, aizvēršana un
           atbalstīšana ir divi pretēji iznākumi vienā pieskāriena zonā, un uz
           telefona netrāpīt ir viegli. Sliktākais gadījums tad būtu, ka cilvēks,
           kurš gribēja atbalstīt, joslu neatgriezeniski aizver. */ ?>
  <button class="zl-aizvert" id="zl-aizvert" type="button" aria-label="Aizvērt atbalsta joslu">&times;</button>

  <?php /* BEZ aria-label: tas pārrakstīja saites pieejamo nosaukumu, tāpēc
           ekrānlasītājs nekad nedzirdēja pašu uzrunu, un redzamais teksts
           nesakrita ar pieejamo nosaukumu (WCAG 2.5.3; audits 2026-09-02).
           Tagad saites nosaukums IR pats teikums. */ ?>
  <a class="zl-logs" href="/ziedot.php" data-atb="josla">
    <span class="zl-teksts" id="zl-teksts"><?= htmlspecialchars($zl_teikumi[0], ENT_QUOTES, 'UTF-8') ?></span>
  </a>

  <a class="zl-cta" href="/ziedot.php" data-atb="josla">
    <i class="fas fa-mug-hot" aria-hidden="true"></i>Atbalstīt
  </a>
</div>

<script>
(function () {
    var josla = document.getElementById('zl-josla');
    if (!josla) return;
    // Galvenes punkts "Atbalstīt" tagad ir pastāvīgs (header.php), tāpēc josla to
    // vairs neslēpj un nerāda — aizvērta josla neatņem ceļu uz atbalsta lapu.

    // Stāvokli tur DIVĀS vietās apzināti: klase uz <html> nosaka pirmo zīmējumu
    // (to uzliek head.php pirms izkārtojuma), `hidden` — vēlāko aizvēršanu tajā
    // pašā lapas dzīvē. Abas jātur sinhroni, lai pārlādes uzvedība nemainās.
    function stavoklis(aizverts) {
        josla.hidden = aizverts;
        document.documentElement.classList.toggle('zl-slepts', aizverts);
    }

    var slepts = false;
    try { slepts = localStorage.getItem('zl_ziedot_slepts') === '1'; } catch (e) {}
    stavoklis(slepts);

    // Lapu skaitītājs: izvēlas teikumu komplektu (biežajam lietotājam personiskā
    // bilance) un sākuma teikumu, lai katrs apmeklējums nesākas ar to pašu.
    // Skaita arī tad, ja josla aizvērta — tas ir lietojuma mērs.
    var BIEZAIS_SLIEKSNIS = 12;
    var lapu = 0;
    try {
        lapu = (parseInt(localStorage.getItem('zl_lapu_skaits'), 10) || 0) + 1;
        localStorage.setItem('zl_lapu_skaits', String(lapu));
    } catch (e) {}

    <?php /* JSON_HEX_TAG obligāts, jo izvade ir <script> iekšpusē: teikumus šeit
             mēdz rediģēt, un "</script>" tekstā citādi pārtrauktu skriptu.
             Tā pati konvencija, kas ai_panel.php un page_builder.php. */ ?>
    var teikumi = <?= json_encode($zl_teikumi, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    if (lapu >= BIEZAIS_SLIEKSNIS) {
        // Skaitlim griesti: "tava 4831. lapa" skan uzmācīgi, ne pārliecinoši.
        teikumi = <?= json_encode($zl_teikumi_biezajam, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
            .map(function (t) { return t.replace('{N}', Math.min(lapu, 999)); });
    }

    var teksts = document.getElementById('zl-teksts');
    var idx = lapu % teikumi.length;
    if (teksts) teksts.textContent = teikumi[idx];

    // Josla ir parastajā plūsmā, tāpēc teikuma maiņa nedrīkst mainīt tās
    // augstumu (viss saturs zem tās lēkātu). Izmēra garāko teikumu pie faktiskā
    // platuma un rezervē tā augstumu; pārmēra pie loga maiņas un kad ielādējas
    // fonts (metrika atšķiras no rezerves fonta).
    function izlidzinaAugstumu() {
        if (!teksts || josla.hidden) return;
        var platums = teksts.offsetWidth;
        if (!platums) return;
        var mers = teksts.cloneNode(false);
        mers.removeAttribute('id');   // citādi mērīšanas laikā DOM ir divi elementi ar vienu id
        mers.style.position = 'absolute';
        mers.style.visibility = 'hidden';
        mers.style.width = platums + 'px';
        mers.style.minHeight = '0';
        josla.appendChild(mers);
        var max = 0;
        teikumi.forEach(function (t) {
            mers.textContent = t;
            if (mers.offsetHeight > max) max = mers.offsetHeight;
        });
        josla.removeChild(mers);
        if (max) teksts.style.minHeight = max + 'px';
    }
    izlidzinaAugstumu();
    // Debounce: katrs resize notikums citādi izsauc klona mērīšanu ar vairākām
    // piespiedu izkārtojuma pārrēķināšanām; loga vilkšana to izsauc simtiem reižu.
    var merTaimeris = null;
    addEventListener('resize', function () {
        clearTimeout(merTaimeris);
        merTaimeris = setTimeout(izlidzinaAugstumu, 150);
    });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(izlidzinaAugstumu);

    // Nomaiņa ik 9 s — pietiek izlasīt garāko teikumu bez steigas. Maiņu aptur
    // pele VIRS joslas, FOKUSS joslā un neredzama cilne: agrāk pauze bija tikai
    // pelei, tāpēc tastatūras un ekrānlasītāja lietotājam teksts mainījās zem
    // rokas (audits 2026-09-02).
    if (!slepts && teikumi.length > 1 && teksts) {
        var pauze = false;
        var apturet = function () { pauze = true; };
        var turpinat = function () { pauze = false; };
        josla.addEventListener('mouseenter', apturet);
        josla.addEventListener('mouseleave', turpinat);
        josla.addEventListener('focusin', apturet);
        josla.addEventListener('focusout', turpinat);

        // Kam sistēmā izslēgtas animācijas, izgaišanas nav (CSS transition:none),
        // tāpēc gaidīšana uz to atstātu tekstu TUKŠU uz 450 ms — tieši to mirgoņu,
        // ko šis iestatījums grib novērst. Tur maiņa notiek vienā solī.
        var bezKustibas = matchMedia('(prefers-reduced-motion: reduce)').matches;
        setInterval(function () {
            if (pauze || document.hidden || josla.hidden) return;
            idx = (idx + 1) % teikumi.length;
            if (bezKustibas) { teksts.textContent = teikumi[idx]; return; }
            teksts.classList.add('zl-maina');
            setTimeout(function () {
                teksts.textContent = teikumi[idx];
                teksts.classList.remove('zl-maina');
            }, 450);
        }, 9000);
    }

    var aizvert = document.getElementById('zl-aizvert');
    if (aizvert) aizvert.addEventListener('click', function () {
        stavoklis(true);
        try { localStorage.setItem('zl_ziedot_slepts', '1'); } catch (e) {}
        // Aizverot joslu, fokuss citādi nokristu uz <body> un tastatūras
        // lietotājs sāktu no lapas augšas. Pārliekam to uz galvenes "Atbalstīt" —
        // tuvāko elementu ar to pašu nozīmi (audits 2026-09-02).
        var navSaite = document.querySelector('.main-nav a[href="/ziedot.php"]');
        if (navSaite) navSaite.focus();
    });
})();
</script>
