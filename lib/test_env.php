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

/**
 * Vai šī ir lokālā testa vide?
 *
 * DIVI nosacījumi, ne viens (2026-08-19): agrāk pietika ar hostu, bet HTTP_HOST
 * sūta KLIENTS — pieprasījums produkcijas serverim ar galveni "Host: localhost"
 * atvēra testa lapas publiski (pārbaudīts lokāli: atdeva 200). Tāpēc papildus
 * prasām, lai pieprasījums NĀK no pašas mašīnas (loopback REMOTE_ADDR).
 * Lokālajā php -S abi nosacījumi izpildās; produkcijā REMOTE_ADDR ir apmeklētāja
 * (vai Cloudflare mezgla) adrese, tāpēc vārti paliek slēgti pat ar viltotu hostu.
 */
function reg_test_env(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return PHP_SAPI === 'cli';   // CLI/būves konteksts bez HTTP

    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $is_loopback = $remote === '127.0.0.1' || $remote === '::1' || str_starts_with($remote, '127.');
    if (!$is_loopback) return false;

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
