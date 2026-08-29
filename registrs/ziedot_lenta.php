<?php
/**
 * registrs/ziedot_lenta.php — slīdošā ziedojumu lenta tieši zem galvenes.
 *
 * Iekļauj header.php beigās, tāpēc parādās visās lapās, kas galveni izmanto.
 *
 * APZINĀTI parastajā plūsmā, NEVIS fiksētajā galvenē: (1) fiksētajā būtu jāmaina
 * body padding-top, kas jau tāpat ir trausls (skat. .test-nav pārrakstīšanu
 * header.php beigās); (2) plūsmā lenta aizslīd prom līdz ar ritināšanu — redzama
 * ierodoties, tālāk netraucē.
 *
 * "Ziedot" poga ir FIKSĒTA labajā malā, ārpus ritošā apgabala: ritošā tekstā tā
 * būtu redzama tikai daļu laika, un kustīgā saitē trāpīt ir kaitinoši.
 *
 * Nerādās uz pašas ziedot.php (cilvēks jau ir galamērķī) un tad, ja lietotājs to
 * ir aizvēris (localStorage).
 */
if (basename($_SERVER['PHP_SELF']) === 'ziedot.php') return;

// Ritošais teksts. Katrs elements = viens fragments; starp tiem liek atdalītāju.
//
// Secība nav nejauša. Pirmais ir SAVSTARPĪBA ("tev noderēja") — tas ir arī tas,
// ko cilvēks redz lapas ielādes brīdī. Šī nav labdarība: lasītājs pats tikko
// saņēma vērtību, tāpēc atsauce uz viņa paša ieguvumu strādā labāk nekā stāsts
// par mūsu izmaksām. Izmaksu fragmenti nāk pēc tam kā pamatojums.
// Bez animācijas režīmā šos NERĀDA — tur ir atsevišķa $zl_statisks rinda zemāk.
$zl_fragmenti = [
    'Ja saraksts.lv tev noderēja, palīdzi to uzturēt',
    'Vietne ir bezmaksas, bez reklāmām un bez reģistrēšanās — arī tiem, kas neziedo',
    'Dati par visiem Latvijā reģistrētajiem uzņēmumiem tiek atjaunoti katru nakti',
    'Uzturēšanu visvairāk izmaksā serveris un MI atbildes',
];

// Bez animācijas režīmā ritināšanas nav, tāpēc der tikai viena rinda. Tajā jāsatilpst
// spēcīgākajam motīvam — savstarpībai un mazās summas leģitimizācijai, nevis
// izmaksu uzskaitījumam (tas lasītājam par sevi neko nedod).
// DIVI varianti, jo šaurā ekrānā pilnais NEIETILPST. Mērīts pie 12,5 px fonta:
// 360 px ekrānā (izplatīts Android platums) pieejami 255 px, bet pilnā rinda
// prasa 264 px. Uz 375 px tā ietilpst tikai ar 6 px rezervi, tāpēc paļauties
// uz vienu variantu nevar. Īsais saglabā abus motīvus — savstarpību un pavēli.
$zl_statisks     = 'Ja saraksts.lv tev noderēja, palīdzi to uzturēt';
$zl_statisks_mob = 'Noderēja? Palīdzi uzturēt vietni';
?>
<style>
.zl-josla{
    display:flex;align-items:stretch;
    background:#eef0f9;border-bottom:1px solid #dcdfee;
    font-size:13.5px;line-height:1;color:#2a2350;
}
.zl-josla[hidden]{display:none}
/* Galvenes rezerves punkts — [hidden] jāuzstāj, jo .main-nav li stili to citādi pārraksta. */
.zl-nav[hidden]{display:none !important}

/* Ritošais logs aizņem atlikušo platumu; maska abās malās, lai teksts izpeld
   un izgaist, nevis parādās un pazūd asi (labajā pusē platāka — zem fiksētās pogas). */
.zl-logs{
    flex:1;min-width:0;overflow:hidden;padding:11px 0;
    -webkit-mask-image:linear-gradient(90deg,transparent,#000 40px,#000 calc(100% - 40px),transparent);
            mask-image:linear-gradient(90deg,transparent,#000 40px,#000 calc(100% - 40px),transparent);
}
.zl-cels{display:flex;width:max-content;animation:zl-slide 45s linear infinite} /* ilgumu pārrēķina skripts pēc teksta garuma; 45s = rezerve */
.zl-josla:hover .zl-cels,
.zl-josla:focus-within .zl-cels{animation-play-state:paused}
@keyframes zl-slide{from{transform:translateX(0)}to{transform:translateX(-50%)}}

.zl-kopa{display:flex;flex-shrink:0;align-items:center;gap:26px;padding-right:26px;white-space:nowrap}
.zl-punkts{color:#a9aec9}

/* Fiksētā poga — vienmēr redzama, nekustas, tāpēc vienmēr noklikšķināma. */
.zl-cta{
    flex-shrink:0;display:inline-flex;align-items:center;gap:7px;
    padding:0 14px;border-left:1px solid #dcdfee;
    color:#2e7d32;font-weight:700;text-decoration:none;white-space:nowrap;
    transition:background .2s;
}
.zl-cta:hover{background:#e3e7f5;text-decoration:underline}
.zl-cta i{font-size:.95em}

/* Kreisajā malā, atdalīts ar līniju — pretējā galā no "Ziedot" pogas. */
.zl-aizvert{
    flex-shrink:0;width:34px;background:none;cursor:pointer;
    border:0;border-right:1px solid #dcdfee;
    color:#8b90ad;font-size:17px;line-height:1;padding:0;
}
.zl-aizvert:hover{color:#2a2350;background:#e3e7f5}

@media (max-width:600px){
    .zl-josla{font-size:12.5px}
    .zl-kopa{gap:18px;padding-right:18px}
    .zl-cta{padding:0 10px;gap:5px}
    .zl-aizvert{width:28px}
}
/* Statiskā rinda — parādās TIKAI bez animācijas režīmā. */
.zl-statisks{display:none}

/* Kam sistēmā izslēgtas animācijas — ritošās kopijas nost, vietā viena
   saspiesta rinda. Ritošos fragmentus rādīt nevar: bez kustības tie neietilpst
   un tiktu nogriezti bez norādes (pārbaudīts 375, 800 un 1200 px). */
@media (prefers-reduced-motion:reduce){
    .zl-cels{animation:none;width:100%;min-width:0}
    .zl-kopa{display:none}
    .zl-st-pilns{
        display:block;min-width:0;
        overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    }
    .zl-logs{-webkit-mask-image:none;mask-image:none;padding-left:14px}
}
/* Šaurā ekrānā pilnais variants tiktu nogriezts — vietā īsais. */
@media (prefers-reduced-motion:reduce) and (max-width:600px){
    .zl-st-pilns{display:none}
    .zl-st-mob{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
}
</style>

<div class="zl-josla" id="zl-josla" hidden>
  <?php /* × ir KREISAJĀ malā, tālu no "Ziedot": blakus stāvot, aizvēršana un
           ziedošana ir divi pretēji iznākumi vienā pieskāriena zonā, un uz telefona
           netrāpīt ir viegli. Sliktākais gadījums tad būtu, ka cilvēks, kurš gribēja
           ziedot, lentu neatgriezeniski aizver. */ ?>
  <button class="zl-aizvert" id="zl-aizvert" type="button" aria-label="Aizvērt ziedojuma joslu">&times;</button>

  <div class="zl-logs">
    <div class="zl-cels">
      <?php
      // Saturs divreiz: translateX(-50%) tad dod bezšuvju ciklu.
      // Otrā kopija aria-hidden — ekrānlasītājs to nenolasa divreiz.
      for ($i = 0; $i < 2; $i++): ?>
      <div class="zl-kopa"<?= $i ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($zl_fragmenti as $n => $teksts): ?>
          <?php if ($n): ?><span class="zl-punkts">·</span><?php endif; ?>
          <span class="zl-frag"><?= htmlspecialchars($teksts, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endforeach; ?>
      </div>
      <?php endfor; ?>
      <span class="zl-statisks zl-st-pilns"><?= htmlspecialchars($zl_statisks, ENT_QUOTES, 'UTF-8') ?></span>
      <span class="zl-statisks zl-st-mob"><?= htmlspecialchars($zl_statisks_mob, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  </div>

  <a class="zl-cta" href="/ziedot.php">
    <i class="fas fa-mug-hot" aria-hidden="true"></i>Ziedot
  </a>
</div>

<script>
(function () {
    var josla = document.getElementById('zl-josla');
    if (!josla) return;
    // Galvenes punkts "Ziedot" ir rezerves ceļš: rādās TIKAI tad, kad lenta ir
    // aizvērta, lai aizvēršana nenozīmētu, ka ceļš uz ziedošanu pazūd pavisam.
    var navPunkts = document.getElementById('zl-nav');

    function stavoklis(aizverts) {
        josla.hidden = aizverts;
        if (navPunkts) navPunkts.hidden = !aizverts;
    }

    // Sāk paslēpta (hidden atribūts) un parādās tikai tad, ja nav aizvērta —
    // tā aizvērtā josla nepamirgo pirms JS paspēj nostrādāt.
    var slepts = false;
    try { slepts = localStorage.getItem('zl_ziedot_slepts') === '1'; } catch (e) {}
    stavoklis(slepts);

    // Ātrumu tur NEMAINĪGU (px/s), nevis ilgumu: pievienojot vai saīsinot tekstu,
    // fiksēts ilgums mainītu ritināšanas ātrumu. 22 px/s ir mierīgs lasīšanas temps.
    // CSS 45s paliek kā rezerve, ja šis skripts nenostrādā.
    // OBLIGĀTI pēc stavoklis(): paslēptam elementam platums ir 0.
    if (!slepts && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        var cels = josla.querySelector('.zl-cels');
        var kopa = josla.querySelector('.zl-kopa');
        var platums = kopa ? kopa.getBoundingClientRect().width : 0;
        if (cels && platums > 0) {
            cels.style.animationDuration = Math.round(platums / 22) + 's';
        }
    }

    document.getElementById('zl-aizvert').addEventListener('click', function () {
        stavoklis(true);
        try { localStorage.setItem('zl_ziedot_slepts', '1'); } catch (e) {}
    });
})();
</script>
