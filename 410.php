<?php
// 410.php — lapa izņemtam uzņēmuma profilam (registrs/lib/izslegtie.php reg_izslegts_410_html).
// Statusa kodu un galvenes iestata izsaucējs; šeit tikai saturs. Apzināti bez uzņēmuma nosaukuma.
require_once __DIR__ . '/lib/applog.php';
applog_boot('registrs');
// Atverot /410.php tieši, statuss arī 410 (ne 200).
if (!headers_sent() && http_response_code() === 200) http_response_code(410);
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profils nav pieejams - Saraksts.lv</title>
    <?php include __DIR__ . '/registrs/assets/img/icons.php'; ?>
    <meta name="robots" content="noindex, follow">
    <?php require_once __DIR__ . '/registrs/lib/assets.php';
          echo reg_css_links(); ?>
</head>
<body>
    <?php include __DIR__ . '/registrs/header.php'; ?>

    <div class="container" style="text-align: center; padding-top: 60px; padding-bottom: 60px;">
        <h1 style="font-size: 1.75rem; margin-bottom: 1rem;">Profils nav pieejams</h1>
        <p style="font-size: 1.125rem; color: var(--color-text-secondary); margin-bottom: 2rem;">Šī uzņēmuma profils no vietnes Saraksts.lv ir izņemts.</p>
        <a href="/" style="display: inline-block; margin-top: 1rem; text-decoration: underline;">Atgriezties uz sākumlapu</a>
    </div>

    <?php include __DIR__ . '/registrs/footer/footer.php'; ?>
</body>
</html>
