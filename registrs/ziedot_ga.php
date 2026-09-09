<?php
/**
 * registrs/ziedot_ga.php — pievieno atbalsta ceļa GA4 mērītāju.
 *
 * Šis fails APZINĀTI satur tikai vienu <script> birku. Pats mērīšanas kods ir
 * registrs/assets/js/ziedot_ga.js, un tur ir arī visi paskaidrojumi par to, ko
 * mēra un ko ne.
 *
 * KĀPĒC TĀ, NEVIS INLINE: šo iekļauj galvene, t.i. KATRAS lapas augša. Auditā
 * 2026-09-08 izmērīts, ka inline bloks, kas serverī nonāktu nogriezts (bez
 * </script>), liktu pārlūkam apēst visu atlikušo lapu kā skripta tekstu —
 * /ziedot.php palika 0 Stripe pogu. Ar atsevišķu .js tāda scenārija nav:
 * nogriezts .js nogāž tikai sevi, un ja fails vispār trūkst, pārlūks dabū 404
 * un lapa strādā bez mērījuma. Maksājums nav atkarīgs no mērījuma nekādā ceļā.
 *
 * defer: izpilde pēc HTML parsēšanas, tāpēc zīmēšanu neaiztur. Klausītājs ir
 * deleģēts uz document, tāpēc vēlāka izpilde neko nezaudē.
 *
 * ?v= satura hash (lib/assets.php): mainot .js saturu, mainās URL, tāpēc neviens
 * nepaliek ar vecu mērītāju kešā. Ja assets.php kādreiz nebūtu, atkāpjamies uz
 * ceļu bez versijas — .htaccess .js failiem jau liek Cache-Control: no-cache.
 */
if (!function_exists('reg_asset_v')) {
    $zga_lib = __DIR__ . '/lib/assets.php';   // __DIR__, ne DOCUMENT_ROOT: tas CLI ir tukšs
    if (is_file($zga_lib)) require_once $zga_lib;
    unset($zga_lib);
}
$zga_src = function_exists('reg_asset_v')
    ? reg_asset_v('/registrs/assets/js/ziedot_ga.js')
    : '/registrs/assets/js/ziedot_ga.js';
?>
<script defer src="<?php echo htmlspecialchars($zga_src, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php unset($zga_src); ?>
