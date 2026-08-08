<?php
header_remove('X-Powered-By');
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/timezone.php'; // datumi lapās Rīgas laikā
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php $__title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : 'Uzņēmumu Meklēšana'; ?>
    <title><?php echo htmlspecialchars($__title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if (isset($pageDesc)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($__title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars('https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    // Kanoniskais URL: bez vaicājuma parametriem un bez "www." — lai Google
    // nesadala vienas lapas signālus starp www/ne-www un filtru URL variantiem.
    // Sarakstu lapu filtri (konkursi.php?valsts=...) NAV atsevišķi indeksējamas lapas.
    $__host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'saraksts.lv');
    $__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    ?>
    <link rel="canonical" href="<?php echo htmlspecialchars("https://{$__host}{$__path}", ENT_QUOTES, 'UTF-8'); ?>">
    <?php // Strukturētie dati (JSON-LD): lapa tos padod kā masīvu $pageJsonLd.
    if (isset($pageJsonLd)): ?>
    <script type="application/ld+json"><?php echo json_encode($pageJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php endif; ?>
	<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/assets/img/icons.php'; ?>

    <script type="text/plain" data-category="tracking" async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
    <script type="text/plain" data-category="tracking">
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-XXXXXXXXXX');
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;600;700&family=Quicksand:wght@400;700&display=swap" rel="stylesheet">
    <?php // SRI: ja CDN kādreiz atdotu citu saturu, pārlūks to atteiksies izpildīt.
          // Google Fonts CSS ir dinamisks (mainās pēc pārlūka), tāpēc tam SRI nav iespējams. ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
          integrity="sha384-3B6NwesSXE7YJlcLI9RpRqGf2p/EgVH8BgoKTaUrmKNDkHPStTQ3EyoYjCGXaOTS"
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <?php if (!defined('REG_FA_LOADED')) define('REG_FA_LOADED', true); // header.php nedublē FA ?>
    
    <?php // Katrs CSS fails ar savu ?v= — main.css @import ķēde kešā nesastāv (skat. lib/assets.php).
          require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/assets.php';
          echo reg_css_links(); ?>

    <?php if (isset($extraHeadContent)) echo $extraHeadContent; ?>
</head>