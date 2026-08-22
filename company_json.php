<?php
/**
 * company_json.php — mašīnlasāms uzņēmuma datu eksports: /{regnr}.json
 * (htaccess/router pāradresē uz ?reg=). Atdod to pašu MI paneļa skrubēto
 * payload (ai_json_data), kas jau ir publisks katras lapas HTML iekšienē,
 * TIKAI bez fizisko personu tabulām (GDPR piesardzība mašīnlasāmajā kanālā).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('registrs');

require_once __DIR__ . '/registrs/lib/db.php';
require_once __DIR__ . '/registrs/lib/data_fetcher.php';
require_once __DIR__ . '/registrs/lib/page_builder.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Robots-Tag: noindex');   // JSON nav meklētāju indeksam; kanoniskā ir HTML lapa

$reg_q = $_GET['reg'] ?? '';
$reg = preg_replace('/\D/', '', is_string($reg_q) ? $reg_q : '');
if (strlen($reg) !== 11) {
    http_response_code(404);
    echo json_encode(['kluda' => 'Nederīgs reģistrācijas numurs (jābūt 11 cipariem).'], JSON_UNESCAPED_UNICODE);
    return;
}

$conn = get_ur_db();
$main = fetch_main_company_data($conn, $reg);
if ($main === null) {
    http_response_code(404);
    echo json_encode(['kluda' => "Reģistrācijas numurs {$reg} netika atrasts."], JSON_UNESCAPED_UNICODE);
    return;
}

$all_res = fetch_all_data_for_reg_nr($conn, $reg);
$memb = fetch_member_as_entity_data($conn, $reg);
$gen = ['company_main_data' => $main, 'all_results' => $all_res, 'member_as_entity_records' => $memb];
$final_d = build_page_data($gen);

$data = json_decode((string)($final_d['ai_json_data'] ?? '{}'), true);
if (!is_array($data)) $data = [];

// Fizisko personu tabulas mašīnlasāmajā eksportā neiekļaujam.
// UZMANĪBU (audits 2026-08-19): šis saraksts ir tikai DROŠĪBAS TĪKLS, ne galvenā
// aizsardzība — neviena no šīm tabulām nav SEARCH_COLUMNS_MAP_REG_NR, tāpēc tās
// raw_database_records vispār nenonāk, un unset zemāk praksē neko nenoņem.
// Īstā aizsardzība ir SLĀNI ZEMĀK: data_fetcher.php skrubji, kas personas datus
// izgriež jau ielādes brīdī (securing_measures, liquidations, vērtētāju saraksts) —
// tikai tie sedz VISUS trīs kanālus: HTML, šo JSON un Gemini promptu. Pievienojot
// šeit jaunu tabulu, vienmēr pārbaudi, vai tā vispār nāk cauri ielādes kartei.
$person_tables = [
    'officers', 'members', 'beneficial_owners', 'stockholders',
    'members_joint_owners', 'stockholders_joint_owners', 'member_as_entity',
    'property_investment_appraisers_list',
];
if (isset($data['raw_database_records']) && is_array($data['raw_database_records'])) {
    foreach ($person_tables as $pt) unset($data['raw_database_records'][$pt]);
}

$out = [
    '_avots' => [
        'nosaukums' => 'Saraksts.lv',
        'lapa' => BASE_DOMAIN . '/' . $reg,
        'dati_atjaunoti' => $final_d['data_updated'] ?? null,
        'piezime' => 'Neoficiāls apkopojums no data.gov.lv (UR) un VID atvērtajiem datiem. Oficiālais pirmavots: info.ur.gov.lv',
    ],
] + $data;

// 1h, ne 24h: ur_data.db pārbūve citādi līdz diennaktij rādītu vecus skaitļus
// starpniekkešos, kamēr HTML dvīnis (bez Cache-Control) jau rāda jaunos.
// Last-Modified = DB faila laiks — pēc pārbūves keši revalidējas tūlīt.
header('Cache-Control: public, max-age=3600');
$__db_mtime = @filemtime(reg_ur_db_path());
if ($__db_mtime !== false) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $__db_mtime) . ' GMT');
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
