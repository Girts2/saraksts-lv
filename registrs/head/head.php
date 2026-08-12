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
    <?php
    // Kanoniskais URL: ja lapa pati ir izrēķinājusi ($canonicalUrl no master_top —
    // uzņēmuma lapām BASE_DOMAIN/{reg}), lietojam TO gan canonical, gan og:url.
    // Agrāk $canonicalUrl nekad netika lietots un canonical būvējās no REQUEST_URI —
    // /40003032949/ (htaccess to pieņem) kanonizējās pats uz sevi ar slīpsvītru,
    // un abas formas Google acīs bija atsevišķas lapas.
    // Citādi (sarakstu lapas) — kā līdz šim: bez vaicājuma parametriem un bez "www.",
    // jo filtri (konkursi.php?valsts=...) NAV atsevišķi indeksējamas lapas.
    if (isset($canonicalUrl) && $canonicalUrl !== '') {
        $__canonical = $canonicalUrl;
    } else {
        $__host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'saraksts.lv');
        $__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $__canonical = "https://{$__host}{$__path}";
    }
    ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($__canonical, ENT_QUOTES, 'UTF-8'); ?>">
<?php // og:image: vērtība tika izrēķināta (page_builder open_graph.image) un piešķirta
      // ($ogImage master_top), bet neviens šablons to neizvadīja — koplietotās saites
      // sociālajos tīklos rādījās bez attēla. ?>
    <?php if (isset($ogImage) && $ogImage !== ''): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($__canonical, ENT_QUOTES, 'UTF-8'); ?>">
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