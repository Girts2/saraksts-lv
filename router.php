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

// 1c. Mašīnlasāms JSON: /{regnr}.json -> company_json.php (produkcijā .htaccess).
if (preg_match('#^/(\d{11})\.json$#', $uri, $m)) {
    $_GET['reg'] = $m[1];
    chdir($docroot);
    require __DIR__ . '/company_json.php';
    return true;
}

// 2. Tīrs URL: /{11-ciparu regcode} -> DINAMISKAIS ruteris (company.php).
if (preg_match('#^/(\d{11})$#', $uri, $m)) {
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
