<?php
/**
 * registrs/lib/assets.php — statisko resursu versionēšana (keša laušana).
 *
 * Problēma: hostings/Cloudflare .js/.css kešo līdz 7 dienām, un ?v= kešlauzis
 * uz main.js/main.css sedz TIKAI tieši ielādēto failu. ES moduļu importi
 * (charts_controller.js → modules/*.js) un CSS @import ķēde iet bez versijas,
 * tāpēc pēc koda izvietošanas apmeklētāji līdz nedēļai redzēja VECOS skriptus,
 * lai gan HTML (dinamisks PHP) jau bija jauns. .htaccess "no-cache" mēģinājums
 * neder — LiteSpeed/Cloudflare slānis galvenes pārraksta (pārbaudīts 2026-08-03:
 * origin joprojām atdod max-age arī ar svaigu vaicājuma parametru).
 *
 * Risinājums — versija KATRAM failam pašā URL (URL maiņa uzvar jebkuru kešu):
 *   - CSS: main.css @import ķēdes vietā head'ā atsevišķi <link> ar ?v=filemtime
 *     (secību nolasa no main.css, tāpēc main.css paliek vienīgais avots);
 *   - JS: <script type="importmap">, kas katru moduļa URL pārkartē uz versionēto —
 *     tas nostrādā arī importiem moduļu IEKŠIENĒ, kur PHP neko nevar pierakstīt.
 *     Pārlūki bez import map atbalsta (Safari <16.4) karti ignorē un strādā kā
 *     līdz šim — sliktāk nekļūst.
 */

declare(strict_types=1);

/** Relatīvais ceļš + ?v=filemtime (ja fails eksistē). */
function reg_asset_v(string $rel): string {
    $t = @filemtime($_SERVER['DOCUMENT_ROOT'] . $rel);
    return $rel . ($t ? '?v=' . $t : '');
}

/**
 * <link> rindas visai CSS ķēdei versionētā veidā. Secību (kaskādei ir nozīme)
 * nolasa no main.css @import rindām; ja nolasīt neizdodas, krīt atpakaļ uz
 * vienu main.css linku (= vecā uzvedība).
 */
function reg_css_links(): string {
    $dir = '/registrs/assets/css/';
    $css = @file_get_contents($_SERVER['DOCUMENT_ROOT'] . $dir . 'main.css');
    $out = '';
    if ($css !== false && preg_match_all('/@import\s+url\(\s*["\']?([^"\')\s]+)["\']?\s*\)/', $css, $m)) {
        foreach ($m[1] as $name) {
            $out .= '<link rel="stylesheet" href="' . reg_asset_v($dir . $name) . '">' . "\n    ";
        }
        return rtrim($out) . "\n";
    }
    return '<link rel="stylesheet" href="' . reg_asset_v($dir . 'main.css') . '">' . "\n";
}

/**
 * Import map JSON: visi assets/js (+modules/) faili → tas pats ceļš + ?v=filemtime.
 * lib/ (d3, chart.umd u.c. UMD vendori) netiek kartēti — tos ielādē parasti
 * <script> tagi un tie praktiski nemainās.
 */
function reg_js_importmap(): string {
    $root = $_SERVER['DOCUMENT_ROOT'];
    $map = [];
    foreach (array_merge(
        glob($root . '/registrs/assets/js/*.js') ?: [],
        glob($root . '/registrs/assets/js/modules/*.js') ?: []
    ) as $f) {
        $rel = substr($f, strlen($root));
        $map[$rel] = $rel . '?v=' . filemtime($f);
    }
    return json_encode(['imports' => $map], JSON_UNESCAPED_SLASHES);
}
