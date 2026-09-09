<?php
/**
 * registrs/ziedot_beigas.php — atbalsta aicinājums SATURA BEIGĀS.
 *
 * Iekļauj master_bottom.php pirms kājenes, tāpēc parādās uzņēmuma lapas galā —
 * tieši tajā brīdī, kad cilvēks tikko dabūja atbildi, ko meklēja. Tas ir
 * apzināti izvēlēts moments: savstarpības sajūta ("es tikko kaut ko saņēmu")
 * ir vislielākā uzreiz pēc saņemtās vērtības, ne lapas augšā, kur cilvēks vēl
 * neko nav ieguvis. Uz šī paņēmiena — aicinājums raksta beigās, nevis maksas
 * siena sākumā — ir uzbūvēts viss Guardian lasītāju atbalsta modelis.
 *
 * Josla lapas augšā (ziedot_lenta.php) šo NEAIZSTĀJ: tā aizritinās prom un ir
 * aizverama, turklāt to redz cilvēks, kurš vēl neko nav saņēmis.
 *
 * Apzināti bez JS, bez aizvēršanas pogas un bez summām: tas ir viens klusi
 * stāvošs bloks lapas beigās, ne uznirstošs logs. Summas izvēlas atbalsta lapā.
 */
?>
<style>
/* Vēss, savaldīgs bloks — tas stāv aiz datiem, tāpēc nedrīkst izskatīties pēc
   reklāmas karoga. Zaļo nes tikai poga un virsraksta ikona. */
.zb-blok{
    max-width:760px;margin:34px auto 10px;padding:22px 24px;
    border:1px solid #dfe2ee;border-left:3px solid #2e7d32;border-radius:10px;
    background:linear-gradient(180deg,#fbfcff,#fff);
}
.zb-blok h2{
    margin:0 0 10px;font-size:17px;color:#140a3f;
    display:flex;align-items:center;gap:9px;
}
.zb-blok h2 i{color:#2e7d32;font-size:.95em;opacity:.85}
.zb-blok p{margin:0 0 16px;font-size:15px;line-height:1.65;color:#444}
.zb-poga{
    display:inline-flex;align-items:center;gap:9px;
    background:#2e7d32;color:#fff;text-decoration:none;
    font-size:15px;font-weight:700;padding:11px 22px;border-radius:8px;
    box-shadow:0 3px 10px rgba(46,125,50,.22);
    transition:background .2s,transform .2s,box-shadow .2s;
}
.zb-poga:hover{background:#276b2b;transform:translateY(-1px);
               box-shadow:0 5px 14px rgba(46,125,50,.3);color:#fff}
@media (prefers-reduced-motion:reduce){ .zb-poga:hover{transform:none} }
@media (max-width:600px){
    .zb-blok{margin:26px 0 8px;padding:18px}
    .zb-poga{width:100%;justify-content:center}
}
</style>

<section class="zb-blok" aria-labelledby="zb-virsraksts">
    <h2 id="zb-virsraksts">
        <i class="fas fa-unlock" aria-hidden="true"></i>Šie dati tev bija pieejami bez maksas
    </h2>
    <?php /* Pamatojums ir vietnes MODELIS, ne uzturētājs: par uzturētāju runāt
             (cik viņam grūti, cik maz laika) nozīmē lūgt žēlumu, un žēlums te ir
             gan manipulācija, gan vājāks arguments nekā skaidra apmaiņa. Tāpat
             apzināti nav vainas motīva — tas rada pretestību, ne atbalstu. */ ?>
    <p>
        Tavs atbalsts palīdz vietnei palikt atvērtai visiem — bez reklāmām un
        bez datu tirgošanas. Ja šī lapa tev noderēja, atbalsti tās darbību.
    </p>
    <a class="zb-poga" href="/ziedot.php" data-atb="lapas-beigas">
        <i class="fas fa-mug-hot" aria-hidden="true"></i>Atbalstīt vietnes darbību
    </a>
</section>
