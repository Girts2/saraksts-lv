<?php require_once __DIR__ . '/lib/applog.php';
applog_boot('404');   // kopīgais notikumu žurnāls (lib/applog.php)
?>
<!DOCTYPE html>
<html lang="lv">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> - Saraksts.lv</title>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/assets/img/icons.php'; ?>
    <meta name="robots" content="noindex, follow">

    <?php // ?k= = administratora atslēga — GA to ierakstītu page_location (sk. registrs/head/head.php).
          // Produkcijā šo lapu atdod `ErrorDocument 404` ar IEKŠĒJU pārvirzi: sākotnējais vaicājums tad ir
          // REDIRECT_QUERY_STRING, bet $_GET ir tukšs — tāpēc jāskatās abi (dzīvā pārbaude 2026-09-22).
    parse_str((string)($_SERVER['REDIRECT_QUERY_STRING'] ?? ''), $__rq404);
    if (!isset($_GET['k']) && !isset($__rq404['k'])): ?>
    <script type="text/plain" data-category="tracking" async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
    <script type="text/plain" data-category="tracking">
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-XXXXXXXXXX');
    </script>
    <?php endif; ?>

    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/assets.php';
          echo reg_css_links(); ?>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/header.php'; ?>

    <div class="container" style="text-align: center; padding-top: 60px; padding-bottom: 60px;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">404</h1>
        <p style="font-size: 1.25rem; color: var(--color-text-secondary); margin-bottom: 2rem;">Atvainojiet, pieprasītā lapa netika atrasta...</p>
        
        
        <a href="/" style="display: inline-block; margin-top: 2rem; text-decoration: underline;">Atgriezties uz sākumlapu</a>
    </div>

    <?php include 'registrs/footer/footer.php'; ?>

    
    <?php include 'registrs/cookie/cookie.php'; ?>


</body>
</html>