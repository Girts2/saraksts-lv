<?php
/**
 * company.php — dinamiskais uzņēmuma lapas ruteris.
 * Aizstāj pārrenderētos statiskos PHP failus: aprēķina page_data pieprasījuma
 * brīdī no ur_data.db un renderē ar tiem pašiem master_top/master_bottom.
 *
 * Sagaida: $_GET['reg'] (11 cipari) vai definētu $REG.
 * DOCUMENT_ROOT satur /registrs/ (assets, head, footer, cookie, mi, templates).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('registrs');   // kopīgais notikumu žurnāls (lib/applog.php)

require_once __DIR__ . '/registrs/lib/db.php';
require_once __DIR__ . '/registrs/lib/data_fetcher.php';
require_once __DIR__ . '/registrs/lib/page_builder.php';
require_once __DIR__ . '/registrs/view/_tpl.php';

$reg = $REG ?? ($_GET['reg'] ?? '');
$reg = preg_replace('/\D/', '', (string)$reg);

if (strlen($reg) !== 11) {
    http_response_code(404);
    $f404 = $_SERVER['DOCUMENT_ROOT'] . '/404.php';
    if (is_file($f404)) require $f404;
    return;
}

// Tiešais /company.php?reg=NNN ir strādājošs DUBLIKĀTS tīrajam /NNN (htaccess
// pārraksta iekšēji, REQUEST_URI paliek /NNN, tāpēc šis zars tur nenostrādā).
// 301 uz kanonisko formu, lai meklētāji nesadala signālus starp abiem URL.
// ?: nevis ??: parse_url kroplam pieprasījumam ('///company.php', '/:80') atdod
// FALSE, ne null — `false ?? ''` paliek false, un str_ends_with zem strict_types
// meta TypeError = ārēji izraisāms 500 (htaccess -f kārtula šādus URL apkalpo,
// jo serveris ceļu kartēšanai slīpsvītras saplacina, bet REQUEST_URI patur jēlu).
$__cpath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
if (PHP_SAPI !== 'cli' && str_ends_with($__cpath, 'company.php')) {
    http_response_code(301);
    header('Location: /' . $reg);
    return;
}

$conn = get_ur_db();
$main = fetch_main_company_data($conn, $reg);
if ($main === null) {
    http_response_code(404);
    $f404 = $_SERVER['DOCUMENT_ROOT'] . '/404.php';
    if (is_file($f404)) require $f404;
    return;
}

$all_res = fetch_all_data_for_reg_nr($conn, $reg);
$memb = fetch_member_as_entity_data($conn, $reg);
$gen = ['company_main_data' => $main, 'all_results' => $all_res, 'member_as_entity_records' => $memb];
$sid = fetch_super_id($conn, $reg);
if ($sid !== null) {
    $rels = fetch_regcodes_by_super_ids($conn, [$sid], true);
    $rels = array_values(array_filter($rels, fn($r) => $r !== (string)$reg));
    if (!empty($rels)) {
        $gen['company_super_id'] = $sid;
        $gen['related_businesses_regcodes'] = $rels;
    }
}

$final_d = build_page_data($gen);

// $page_data = pilnais final_d (inner_content to lasa) + skalārie SEO lauki (master_top/head).
$page_data = $final_d;
$page_data['page_title'] = $final_d['seo']['title'] ?? '';
$page_data['meta_description'] = $final_d['meta_description'] ?? '';
$page_data['page_keywords'] = $final_d['page_keywords'] ?? '';
// build_page_data 'canonical_url' NEuzstāda (vēsturiski tukšs) — kanoniskā forma
// uzņēmuma lapai vienmēr ir BASE_DOMAIN/{reg} (bez beigu slīpsvītras, bez parametriem).
$page_data['canonical_url'] = $final_d['canonical_url'] ?? (BASE_DOMAIN . '/' . $reg);
$page_data['generation_date'] = $final_d['generationDate'] ?? '';
$og = $final_d['seo_metadata']['open_graph'] ?? [];
$page_data['og_title'] = $og['title'] ?? '';
$page_data['og_desc'] = $og['description'] ?? '';
$page_data['og_url'] = $og['url'] ?? '';
$page_data['og_image'] = $og['image'] ?? '';
$page_data['schema_org_json'] = $final_d['seo_metadata']['schema_org_json'] ?? '';
$page_data['faq_schema_json'] = $final_d['seo_metadata']['faq_schema_json'] ?? '';

// Mainīgie, ko sagaida master_top / ai_panel.
$rawData = $final_d['ai_json_data'] ?? '{}';
$js_config_json = $final_d['js_config_json'] ?? '{}';
$dataVersion = $final_d['data_version'] ?? '';

// Mašīnlasāmā JSON alternatīva (company_json.php) — MI aģentiem un rīkiem.
$extraHeadContent = ($extraHeadContent ?? '')
    . '<link rel="alternate" type="application/json" href="' . htmlspecialchars(BASE_DOMAIN . '/' . $reg . '.json', ENT_QUOTES) . '" title="Uzņēmuma dati JSON formātā">' . "\n";

// 1. master_top (definē $prompts, apstrādā AI SSE, izvada head+body+header+search).
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/templates/master_top.php';

// 2. Iekšējais saturs (ai_panel izmanto $prompts, $page_data, $dyn_cache...).
include __DIR__ . '/registrs/view/inner_content.php';

// 3. master_bottom.
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/templates/master_bottom.php';
