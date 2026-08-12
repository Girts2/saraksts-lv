<?php
// server/router.php — maršrutētājs PHP iebūvētajam serverim (php -S).
// Produkcijā to pašu dara .htaccess rewrite; šis ir tikai lokālai testēšanai.
declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$docroot = $_SERVER['DOCUMENT_ROOT'];

// 1. Reāli faili (css/js/img/sqlite/php apakšlapas) — serverē tieši.
if ($uri !== '/' ) {
    $full = realpath($docroot . $uri);
    if ($full !== false && is_file($full) && str_starts_with($full, realpath($docroot))) {
        return false; // php -S apkalpo failu pats
    }
}

// 1b. Datu admin panelis (lokālai testēšanai; produkcijā tas ir docroot saknē).
if ($uri === '/data_admin.php') {
    require __DIR__ . '/data_admin.php';
    return true;
}

// 1b2. Per-NACE SEO lapa: /nozare/{kods} -> nozare_nace.php (produkcijā .htaccess).
if (preg_match('#^/nozare/([A-Ua-u]|[0-9]{2}(?:\.[0-9]{1,2})?)/?$#', $uri, $m)) {
    $_GET['kods'] = $m[1];
    chdir($docroot);
    require __DIR__ . '/nozare_nace.php';
    return true;
}

// 1b3. Testa sadaļa: /profesija/{6-ciparu kods} -> test_profesija.php.
// TIKAI lokāli — produkcijas .htaccess kārtula apzināti nav pievienota, jo testa
// sadaļas uz serveri neliekam (fails docroot'ā arī neeksistē produkcijā).
if (preg_match('#^/profesija/(\d{6})/?$#', $uri, $m) && is_file(__DIR__ . '/test_lapas/profesija.php')) {
    $_GET['kods'] = $m[1];
    chdir($docroot);
    require __DIR__ . '/test_lapas/profesija.php';
    return true;
}

// 1b4. Testa sadaļa: /zales/{product_id} -> test_zale.php. Produkta ID formāts
// ZVA reģistrā ir "00-0003-01" (cipari un defises). Tikai lokāli.
if (preg_match('#^/zales/([0-9A-Za-z][0-9A-Za-z-]{2,30})/?$#', $uri, $m) && is_file(__DIR__ . '/test_lapas/zale.php')) {
    $_GET['pid'] = $m[1];
    chdir($docroot);
    require __DIR__ . '/test_lapas/zale.php';
    return true;
}

// 1b5. Testa sadaļa: /viela/{ATĶ 5. līmeņa kods} -> aktīvās vielas lapa.
// Formāts: burts + 2 cipari + 2 burti + 2 cipari (piem., N02BE01). Tikai lokāli.
if (preg_match('#^/viela/([A-Za-z]\d{2}[A-Za-z]{2}\d{2})/?$#', $uri, $m)
    && is_file(__DIR__ . '/test_lapas/viela.php')) {
    $_GET['atc'] = $m[1];
    chdir($docroot);
    require __DIR__ . '/test_lapas/viela.php';
    return true;
}

// 1c. Mašīnlasāms JSON: /{regnr}.json -> company_json.php (produkcijā .htaccess).
if (preg_match('#^/(\d{11})\.json$#', $uri, $m)) {
    $_GET['reg'] = $m[1];
    chdir($docroot);
    require __DIR__ . '/company_json.php';
    return true;
}

// 2. Tīrs URL: /{11-ciparu regcode} -> DINAMISKAIS ruteris (company.php).
// /? beigās — kā produkcijas htaccess (^([0-9]{11})/?$), citādi /NNN/ lokāli 404,
// bet produkcijā strādā, un slīpsvītras kanonizāciju lokāli nevar notestēt.
if (preg_match('#^/(\d{11})/?$#', $uri, $m)) {
    $REG = $m[1];
    chdir($docroot);
    require __DIR__ . '/company.php';
    return true;
}

// 3. Sākumlapa.
if ($uri === '/' && is_file($docroot . '/index.php')) {
    chdir($docroot);
    require $docroot . '/index.php';
    return true;
}

// 4. Citādi 404.
http_response_code(404);
if (is_file($docroot . '/404.php')) { chdir($docroot); require $docroot . '/404.php'; }
