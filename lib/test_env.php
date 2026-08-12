<?php
/**
 * lib/test_env.php — "Test ..." sadaļu vides vārti (2026-08-09, Girta lēmums).
 *
 * Testa sadaļas ir izstrādes stadijā un NAV publicējamas, bet servera izlikšana
 * notiek, augšupielādējot VISU server/ — tāpēc failus nevar vienkārši neuzlikt.
 * Risinājums: pēc noklusējuma slēgts. Testa vide = tikai localhost/127.* hosts
 * (lokālais php -S) vai CLI bez hosta (būves skripti). Jebkurš publisks hosts
 * (saraksts.lv u.c.) dabū 404, un header.php testa izvēlni tur nerāda.
 */
declare(strict_types=1);

/** Vai šī ir lokālā testa vide? */
function reg_test_env(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return PHP_SAPI === 'cli';   // CLI/būves konteksts bez HTTP
    $host = preg_replace('/:\d+$/', '', $host);    // noņem portu
    return $host === 'localhost'
        || str_starts_with($host, '127.')
        || str_ends_with($host, '.localhost');
}

/** 404 un beigas, ja nav testa vide. Sauc testa lapu pašā augšā. */
function reg_test_gate(): void {
    if (reg_test_env()) return;
    http_response_code(404);
    $f = ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)) . '/404.php';
    if (is_file($f)) include $f;
    exit;
}
