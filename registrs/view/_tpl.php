<?php
// server/templates/_tpl.php — šablonu palīgfunkcijas.
declare(strict_types=1);
require_once __DIR__ . '/../lib/formatters.php';

// py_truthy() ir definēts formatters.php (šis fails to require).

/**
 * Python dict.get(key, default): default TIKAI ja atslēga nav; ja ir null -> null.
 */
function dget(array $d, string $key, $default = null) {
    return array_key_exists($key, $d) ? $d[$key] : $default;
}

/**
 * Jinja `x or default`.
 */
function pyor($v, $default) {
    return py_truthy($v) ? $v : $default;
}

/** Ekranēšanas saīsinājums (Jinja `| e`). */
function h($v): string {
    return jinja_e($v);
}

/**
 * Naudas/skaitļu formāts servera renderētajām tabulām: "24 496" / "24 496,5".
 * Veseliem skaitļiem bez decimāldaļas; frakcijām līdz 2 zīmēm (kā JS toLocaleString('lv-LV')).
 */
function tpl_num($v, ?string $suffix = null): string {
    if ($v === null || (is_float($v) && !is_finite($v))) return '—';
    if (!is_int($v) && !is_float($v)) {
        if (!is_numeric($v)) return '—';
        $v = (float)$v;
    }
    $s = number_format((float)$v, 2, ',', ' ');
    $s = rtrim(rtrim($s, '0'), ',');
    return $suffix !== null && $suffix !== '' ? $s . ' ' . $suffix : $s;
}
