<?php
/**
 * sadala.php — "Papildu reģistri un dati" sadaļas PILNAIS saturs.
 *
 * Galapunkts: /{11-ciparu regnr}/sadala/{sadalas_atslega}
 * Atdod TIKAI sadaļas HTML fragmentu (bez lapas ietvara), ko papildu_dati.js
 * ieliek atvērtās sadaļas vietā, kad lietotājs uzklikšķina uz "… un vēl N".
 *
 * KĀPĒC ATSEVIŠĶS GALAPUNKTS: lapā sadaļas rāda tikai pirmos ierakstus. Uzņēmumam
 * ar 2 426 iepirkumu līgumiem visu ierakstu iegulšana lapā pievienotu ~360 KB HTML
 * KATRAM apmeklētājam, arī tiem, kas sadaļu nemaz neatver. Tāpēc pilno sarakstu
 * ielādē tikai pēc pieprasījuma.
 *
 * Datu ķēde te ir ŠAURĀKA nekā company.php: nevajag ne super_id radniecīgos
 * uzņēmumus, ne SEO/OG laukus — tikai `results`, ko lasa sadaļu faili.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('registrs');

require_once __DIR__ . '/registrs/lib/db.php';
require_once __DIR__ . '/registrs/lib/data_fetcher.php';
require_once __DIR__ . '/registrs/lib/page_builder.php';
require_once __DIR__ . '/registrs/view/_tpl.php';

// ?reg[]=1 (masīvs) ar (string) metienu dotu "Array to string conversion"
// brīdinājumu ar pilnu faila ceļu atbildē (audits 2026-08-20).
$reg_q = $REG ?? ($_GET['reg'] ?? '');
$atsl_q = $SADALA ?? ($_GET['sadala'] ?? '');
$reg = preg_replace('/\D/', '', is_string($reg_q) ? $reg_q : '');
$atsl = strtolower(is_string($atsl_q) ? $atsl_q : '');

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');            // fragments nav atsevišķa lapa
header('Cache-Control: public, max-age=300');

// Atslēgu baltais saraksts: faila vārds nekad nenāk no lietotāja teksta.
$ATLAUTAS = ['tiesiskais', 'saistibas', 'iepirkumi', 'esfondi', 'atbalsts', 'bis',
             'vide', 'atkritumi', 'ptac', 'zva', 'vid_statusi'];
if (strlen($reg) !== 11 || !in_array($atsl, $ATLAUTAS, true)) {
    http_response_code(404);
    echo '<p class="pd-note">Sadaļa nav atrasta.</p>';
    return;
}

try {
    $conn = get_ur_db();
    $main = fetch_main_company_data($conn, $reg);
    if ($main === null) {
        http_response_code(404);
        echo '<p class="pd-note">Uzņēmums nav atrasts.</p>';
        return;
    }
    $page_data = build_page_data([
        'company_main_data' => $main,
        'all_results' => fetch_all_data_for_reg_nr($conn, $reg),
        'member_as_entity_records' => fetch_member_as_entity_data($conn, $reg),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p class="pd-note">Datus neizdevās ielādēt.</p>';
    return;
}

// $pd_pilns liek sadaļai rādīt VISUS ierakstus (ar drošības griestiem pašā sadaļā).
$pd_pilns = true;
$pd_nos = ''; $pd_n = 0; $pd_kops = ''; $pd_limenis = '';
$fails = __DIR__ . '/registrs/view/partials/sadalas/' . $atsl . '.php';
if (!is_file($fails)) {
    http_response_code(404);
    echo '<p class="pd-note">Sadaļa nav atrasta.</p>';
    return;
}
ob_start();
include $fails;
$saturs = ob_get_clean();
if (trim($saturs) === '') {
    // Balta saraksta sadaļa BEZ datiem šim uzņēmumam: tukša 200 izskatījās pēc
    // kļūdas (papildu_dati.js to arī rādīja kā kļūdu) un tika kešota 5 min
    // (audits 2026-08-20) — tagad godīgs 404 ar paskaidrojumu.
    http_response_code(404);
    echo '<p class="pd-note">Šai sadaļai par šo uzņēmumu datu nav.</p>';
    return;
}
// Tieša navigācija (saite bez JavaScript, Sec-Fetch-Dest: document) dabū minimālu
// lasāmu ietvaru ar atpakaļceļu; fetch() no papildu_dati.js — tikai fragmentu.
if (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'document') {
    echo '<!doctype html><html lang="lv"><head><meta charset="utf-8">',
        '<meta name="viewport" content="width=device-width, initial-scale=1">',
        '<meta name="robots" content="noindex">',
        '<title>Sadaļas pilnais saturs — ', h($reg), '</title>',
        '<style>body{font-family:system-ui,sans-serif;font-size:15px;color:#333a42;',
        'max-width:760px;margin:24px auto;padding:0 16px}h4{margin:18px 0 6px}',
        'ul{padding-left:20px}li{margin-bottom:6px}a{color:#3b5a8a}',
        '.pd-muted{color:#6b7280;font-size:13px}.pd-tag{font-size:12px;border:1px solid #cfd6de;',
        'border-radius:4px;padding:0 5px}.pd-note{color:#6b7280;font-size:13px}</style></head><body>',
        '<p><a href="/', h($reg), '">&larr; Atpakaļ uz uzņēmuma lapu</a></p>',
        $saturs, '</body></html>';
    return;
}
echo $saturs;
