<?php
/**
 * top.php — "TOP: lielākie uzņēmumi pa novadiem un pilsētām" (/top/, /top/{slug}).
 *
 * Ieviests 2026-08-22 (prototips test_top.php → publicēts tajā pašā dienā, Girta lēmums)
 * pēc SEO ieteikumu vērtējuma: reģionālās TOP
 * lapas — "Talsu novada lielākie uzņēmumi" — ir vienīgais no Google MI ieteikumiem, kas
 * vietnē vēl nebija (nozaru TOP jau ir /nozare/{kods}). Lapas atbild uz reāliem
 * vaicājumiem ("lielākie uzņēmumi Talsos", "Liepājas uzņēmumi pēc apgrozījuma") un
 * dod gatavu reģionālo pārskatu, ko citur nav — ne plānu sarakstu.
 *
 * URL (lokāli router.php, produkcijā .htaccess): /top/ — Latvijas TOP-100 + visas teritorijas
 *                                /top/{slug}      — viena teritorija (42: 7 valstspilsētas + 35 novadi)
 * Dati: nozare/katalogs.sqlite `companies` (būvē section_nozare.php katru nakti no jaunākā
 * gada pārskata: apgrozījums, peļņa, darbinieki, neto alga, NACE, `location`).
 *
 * `location` ir juridiskās adreses PIRMAIS segments ("Talsu nov.", "Rīga") — UR adreses
 * daļai uzņēmumu nav atjauninātas kopš 2021. g. reformas, tāpēc tur ir arī vecie novadi
 * (Riebiņu, Kārsavas, Dagdas…). Tos kartējam uz pašreizējiem (TP_VECIE_NOVADI); ārvalstu/
 * tukšas adreses (~360 no 220 038) paliek ārpus teritorijām, bet ir Latvijas kopsummā.
 *
 * KAS APZINĀTI NAV: nozaru TOP (dublētu /nozare/{kods}) un pagastu/pilsētu līmenis (pārāk
 * plānas lapas — 500+ vienības ar dažiem uzņēmumiem katrā). 42 teritorijas × bagāts saturs.
 *
 * Privātums: tie paši dati, kas nozaru lapās — tikai reģistrā esoši subjekti; vidējo algu
 * rāda tikai, ja aiz tās ≥3 darbinieki (tā pati norma kā nozare_nace.php).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/test_env.php';   // tikai "Renderēts N ms" piezīmei lokālajā vidē
require_once __DIR__ . '/lib/applog.php';
applog_boot('top');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/lib/top_teritorijas.php';   // TP_TERITORIJAS, TP_VECIE_NOVADI (arī sitemap lieto)

function tp_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tp_n0($v): string { return $v === null ? '—' : number_format(round((float)$v), 0, ',', ' '); }
function tp_pct(?float $v): string {
    if ($v === null) return '—';
    return ($v > 0 ? '+' : '') . number_format($v, 1, ',', ' ') . ' %';
}
function tp_money_short(?float $v): string {
    if ($v === null) return '—';
    $a = abs($v);
    if ($a >= 1e9) return number_format($v / 1e9, 2, ',', ' ') . ' mljrd. €';
    if ($a >= 1e6) return number_format($v / 1e6, 1, ',', ' ') . ' milj. €';
    if ($a >= 1e3) return number_format($v / 1e3, 0, ',', ' ') . ' tūkst. €';
    return number_format($v, 0, ',', ' ') . ' €';
}
/** slug → [location atslēga, nosaukums, ģenitīvs] vai null. */
function tp_pec_sluga(string $slug): ?array {
    foreach (TP_TERITORIJAS as $loc => [$nos, $gen, $sl]) {
        if ($sl === $slug) return [$loc, $nos, $gen];
    }
    return null;
}
/** Visas DB `location` vērtības, kas pieder teritorijai (pati + vecie novadi). */
function tp_location_vertibas(string $loc): array {
    $v = [$loc];
    foreach (TP_VECIE_NOVADI as $vecs => $jauns) if ($jauns === $loc) $v[] = $vecs;
    return $v;
}
/** SQL CASE, kas jebkuru location pārvērš pašreizējā teritorijā (indeksa lapas grupēšanai). */
function tp_norm_case(): string {
    $when = '';
    foreach (TP_VECIE_NOVADI as $vecs => $jauns) {
        $when .= " WHEN location = " . "'" . str_replace("'", "''", $vecs) . "' THEN '" . str_replace("'", "''", $jauns) . "'";
    }
    return "CASE$when ELSE location END";
}

// ── Ievade ────────────────────────────────────────────────────────────────────
$slug_raw = trim((string)($_GET['t'] ?? ''));
$slug = strtolower($slug_raw);
if ($slug !== '' && !preg_match('/^[a-z-]{1,40}$/', $slug)) $slug = '-';
$ter = $slug !== '' ? tp_pec_sluga($slug) : null;
$base = 'https://saraksts.lv';
// Viena kanoniskā forma: /top/ (indekss) un /top/{slug} bez beigu slīpsvītras un ar
// mazajiem burtiem; citas formas (/top, /top/riga/, ?t=RIGA) → 301, ne dublikāts
// (recenzija 2026-08-22: head.php canonical būvē no REQUEST_URI).
$canonicalUrl = $base . '/top/' . ($ter !== null ? $slug : '');
if (PHP_SAPI !== 'cli') {
    $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $kanon_path = '/top/' . ($ter !== null ? $slug : '');
    if (($ter !== null || $slug === '') && $req_path !== $kanon_path) {
        header('Location: ' . $kanon_path, true, 301);
        return;
    }
}
$db_file = __DIR__ . '/nozare/katalogs.sqlite';
if (($slug !== '' && $ter === null) || !is_file($db_file)) {
    http_response_code(404);
    $f404 = __DIR__ . '/404.php';
    if (is_file($f404)) { $_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: __DIR__; require $f404; }
    return;
}
$pdo = new PDO('sqlite:' . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$data_updated = date('Y-m-d', @filemtime($db_file) ?: time());
$t0 = microtime(true);

// Dalītie vecie novadi: Aglonas nov. 2021. g. sadalīts — Aglonas pag. → Preiļu nov., bet
// Grāveru/Kastuļinas/Šķeltovas pag. → Krāslavas nov. `location` glabā tikai adreses pirmo
// segmentu, tāpēc pagastu ņemam no UR register (recenzija 2026-08-22: 6 no 19 uzņēmumiem
// citādi nonāk Preiļos). $tp_parcelt: regcode → pareizā teritorija.
$tp_parcelt = [];
$tp_vajag_parcelt = $ter === null || in_array($ter[0], ['Preiļu nov.', 'Krāslavas nov.'], true);
if ($tp_vajag_parcelt) {
    // Kešots (katalogs mtime): kopa ir statiska līdz nākamajai būvei; JOIN pār visu
    // companies katrā pieprasījumā maksāja 200 ms (recenzijas pēcpārbaude 2026-08-22).
    $parc_fails = tp_kesa_cels($db_file, 'parcelt');
    $parc = is_file($parc_fails) ? json_decode((string)file_get_contents($parc_fails), true) : null;
    if (is_array($parc)) {
        $tp_parcelt = $parc;
    } else {
        try {
            $ur_path = __DIR__ . '/csv/SQLite/ur_data.db';
            if (function_exists('reg_ur_db_path')) $ur_path = reg_ur_db_path();
            $kandidati = $pdo->query("SELECT regcode FROM companies WHERE location = 'Aglonas nov.'")->fetchAll(PDO::FETCH_COLUMN);
            if ($kandidati && is_file($ur_path)) {
                $pdo->exec("ATTACH DATABASE '" . str_replace("'", "''", $ur_path) . "' AS ur");
                $in = implode(',', array_fill(0, count($kandidati), '?'));
                $st = $pdo->prepare("SELECT regcode FROM ur.register WHERE regcode IN ($in)
                    AND (address LIKE '%Grāveru pag.%' OR address LIKE '%Kastuļinas pag.%' OR address LIKE '%Šķeltovas pag.%')");
                $st->execute(array_map('strval', $kandidati));
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $reg) $tp_parcelt[(string)$reg] = 'Krāslavas nov.';
                $pdo->exec('DETACH DATABASE ur');
            }
            @file_put_contents($parc_fails, json_encode($tp_parcelt));
        } catch (Throwable $e) { /* bez UR datubāzes dalījumu nevar izšķirt — paliek pēc location */ }
    }
}
/** WHERE daļa teritorijai: location IN (...) ± pārceltie uzņēmumi. @return [sql, params] */
function tp_where(string $loc, array $parcelt): array {
    $vert = tp_location_vertibas($loc);
    $sql = 'location IN (' . implode(',', array_fill(0, count($vert), '?')) . ')';
    $params = $vert;
    $iznemt = []; $pielikt = [];
    foreach ($parcelt as $reg => $kur) {
        if ($kur === $loc) $pielikt[] = (string)$reg;
        else $iznemt[] = (string)$reg;   // pārcelts prom no kādas no šīs teritorijas location vērtībām
    }
    if ($iznemt) { $sql .= ' AND regcode NOT IN (' . implode(',', array_fill(0, count($iznemt), '?')) . ')'; $params = array_merge($params, $iznemt); }
    if ($pielikt) { $sql = "($sql OR regcode IN (" . implode(',', array_fill(0, count($pielikt), '?')) . '))'; $params = array_merge($params, $pielikt); }
    return [$sql, $params];
}
/**
 * Keša faila ceļš: viena mape uz katalogs.sqlite mtime (sys_get_temp_dir()/saraksts_top_{mtime}/),
 * vecās mtime mapes dzēš, kad parādās jauna — citādi pēc katras nakts būves temp mapē
 * paliktu +44 faili (recenzija 2026-08-22).
 */
function tp_kesa_cels(string $db_file, string $atsl): string {
    $mt = (string)@filemtime($db_file);
    $dir = sys_get_temp_dir() . '/saraksts_top_' . md5($db_file . '|' . $mt . '|v3');
    if (!is_dir($dir)) {
        foreach (glob(sys_get_temp_dir() . '/saraksts_top_*', GLOB_ONLYDIR) ?: [] as $vec) {
            foreach (glob($vec . '/*.json') ?: [] as $f) @unlink($f);
            @rmdir($vec);
        }
        @mkdir($dir, 0775, true);
    }
    return $dir . '/' . $atsl . '.json';
}
/** "1 darbinieks" / "2 darbinieki" (21 darbinieks, 11 darbinieki). */
function tp_dsk(int $n, string $viensk, string $dsk): string {
    return ($n % 10 === 1 && $n % 100 !== 11) ? $viensk : $dsk;
}

// Nozaru nosaukumi nodaļu līmenī (2 cipari) — nozaru struktūras blokam.
$nace_nod = [];
foreach ($pdo->query("SELECT code, name FROM nace WHERE level = 2") as $r) $nace_nod[(string)$r['code']] = (string)$r['name'];

$chg = static function ($cur, $prev): ?float {
    $cur = (float)($cur ?? 0); $prev = (float)($prev ?? 0);
    return $prev == 0.0 ? null : (($cur - $prev) / abs($prev)) * 100;
};

if ($ter === null) {
    // ── INDEKSA LAPA: Latvija kopā + teritorijas ─────────────────────────────
    // Kešs sys_get_temp_dir() ar katalogs.sqlite mtime atslēgu: visas 220k rindas
    // jāgrupē tikai reizi dienā, ne katrā pieprasījumā (recenzija: 0,6 s → ~0 ms).
    $kesa_fails = tp_kesa_cels($db_file, 'index');
    $kess = is_file($kesa_fails) ? json_decode((string)file_get_contents($kesa_fails), true) : null;
    if (is_array($kess) && isset($kess['agg'], $kess['top'], $kess['ter_rindas'])) {
        ['agg' => $agg, 'top' => $top, 'ter_rindas' => $ter_rindas, 'arpus' => $arpus] = $kess;
    } else {
        $agg = $pdo->query("SELECT COUNT(*) c, SUM(CASE WHEN turnover IS NOT NULL THEN 1 ELSE 0 END) c_fin,
                SUM(turnover) t, SUM(prev_turnover) pt, SUM(CASE WHEN prev_turnover IS NOT NULL THEN turnover END) t_pair,
                SUM(employees) e, SUM(prev_employees) pe, SUM(CASE WHEN prev_employees IS NOT NULL THEN employees END) e_pair,
                SUM(profit) p FROM companies")->fetch() ?: [];
        $top = $pdo->query("SELECT regcode, name, nace_code_np, turnover, turnover_change, profit, employees, location
                FROM companies WHERE turnover > 0
                ORDER BY turnover DESC, name_sort ASC LIMIT 100")->fetchAll();
        // Grupējam pēc NEAPSTRĀDĀTĀ location (56 ms) un vecos novadus saskaitām PHP pusē —
        // CASE normalizācija SQL pusē maksāja ~250 ms (recenzija 2026-08-22).
        $pa_loc = [];
        foreach ($pdo->query("SELECT location, COUNT(*) c, SUM(CASE WHEN turnover IS NOT NULL THEN 1 ELSE 0 END) c_fin,
                SUM(turnover) t, SUM(employees) e, MAX(turnover) mt FROM companies GROUP BY location") as $r) {
            $pa_loc[(string)$r['location']] = $r;
        }
        $pa_ter = []; $arpus = 0;
        foreach ($pa_loc as $l => $r) {
            $ter_k = TP_VECIE_NOVADI[$l] ?? $l;
            if (!isset(TP_TERITORIJAS[$ter_k])) { $arpus += (int)$r['c']; continue; }
            $a =& $pa_ter[$ter_k];
            $a = $a ?? ['c' => 0, 'c_fin' => 0, 't' => 0.0, 'e' => 0, 'mt' => null];
            $a['c'] += (int)$r['c']; $a['c_fin'] += (int)$r['c_fin']; $a['t'] += (float)($r['t'] ?? 0); $a['e'] += (int)($r['e'] ?? 0);
            if ($r['mt'] !== null && ($a['mt'] === null || (float)$r['mt'] > $a['mt'])) $a['mt'] = (float)$r['mt'];
            unset($a);
        }
        // Pārceltie uzņēmumi (dalītie novadi) — pārliekam no vienas teritorijas uz otru.
        if ($tp_parcelt) {
            $in = implode(',', array_fill(0, count($tp_parcelt), '?'));
            $st = $pdo->prepare("SELECT regcode, location, turnover, employees FROM companies WHERE regcode IN ($in)");
            $st->execute(array_keys($tp_parcelt));
            foreach ($st->fetchAll() as $r) {
                $no = TP_VECIE_NOVADI[$r['location']] ?? $r['location']; $uz = $tp_parcelt[$r['regcode']];
                foreach ([[$no, -1], [$uz, 1]] as [$k, $z]) {
                    if (!isset($pa_ter[$k])) $pa_ter[$k] = ['c' => 0, 'c_fin' => 0, 't' => 0.0, 'e' => 0, 'mt' => null];
                    $pa_ter[$k]['c'] += $z; if ($r['turnover'] !== null) $pa_ter[$k]['c_fin'] += $z;
                    $pa_ter[$k]['t'] += $z * (float)($r['turnover'] ?? 0); $pa_ter[$k]['e'] += $z * (int)($r['employees'] ?? 0);
                }
            }
        }
        // Pārcelšanas skartajām teritorijām maksimumu nevar paņemt no location grupām
        // (pārceltais varētu būt bijis savas vecās grupas maksimums) — tām TOP-1 rēķinām
        // ar īstu teritorijas vaicājumu (recenzija 2026-08-22).
        $top1 = [];
        $skartas = [];
        foreach ($tp_parcelt as $reg => $uz) { $skartas[$uz] = true; }
        if ($tp_parcelt) {
            $in = implode(',', array_fill(0, count($tp_parcelt), '?'));
            $st = $pdo->prepare("SELECT DISTINCT location FROM companies WHERE regcode IN ($in)");
            $st->execute(array_keys($tp_parcelt));
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $l) { $k = TP_VECIE_NOVADI[$l] ?? $l; if (isset(TP_TERITORIJAS[$k])) $skartas[$k] = true; }
        }
        foreach (array_keys($skartas) as $k) {
            if (!isset(TP_TERITORIJAS[$k])) continue;
            [$wh1, $wp1] = tp_where($k, $tp_parcelt);
            $st = $pdo->prepare("SELECT regcode, name, location, turnover FROM companies WHERE $wh1 AND turnover > 0 ORDER BY turnover DESC, name_sort ASC LIMIT 1");
            $st->execute($wp1);
            $r1 = $st->fetch();
            if ($r1) { $top1[$k] = $r1; if (isset($pa_ter[$k])) $pa_ter[$k]['mt'] = (float)$r1['turnover']; }
        }
        $maksimumi = array_values(array_unique(array_filter(array_map(static fn($a) => $a['mt'], $pa_ter), static fn($v) => $v !== null)));
        if ($maksimumi) {
            $st = $pdo->prepare('SELECT regcode, name, location, turnover FROM companies WHERE turnover IN (' . implode(',', array_fill(0, count($maksimumi), '?')) . ') ORDER BY turnover DESC, name_sort ASC');
            $st->execute($maksimumi);
            foreach ($st->fetchAll() as $r) {
                $ter_k = $tp_parcelt[$r['regcode']] ?? (TP_VECIE_NOVADI[$r['location']] ?? $r['location']);
                if (isset($skartas[$ter_k])) continue;   // jau izrēķināts ar īsto vaicājumu
                if (isset(TP_TERITORIJAS[$ter_k]) && !isset($top1[$ter_k]) && isset($pa_ter[$ter_k]) && (float)$r['turnover'] === (float)$pa_ter[$ter_k]['mt']) $top1[$ter_k] = $r;
            }
        }
        $ter_rindas = [];
        foreach (TP_TERITORIJAS as $loc => [$nos, $gen, $sl]) {
            $a = $pa_ter[$loc] ?? ['c' => 0, 'c_fin' => 0, 't' => 0, 'e' => 0];
            $ter_rindas[] = ['loc' => $loc, 'nos' => $nos, 'slug' => $sl, 'c' => (int)$a['c'], 'c_fin' => (int)$a['c_fin'],
                't' => (float)($a['t'] ?? 0), 'e' => (int)($a['e'] ?? 0), 'top1' => $top1[$loc] ?? null];
        }
        usort($ter_rindas, static fn($a, $b) => $b['t'] <=> $a['t']);
        @file_put_contents($kesa_fails, json_encode(['agg' => $agg, 'top' => $top, 'ter_rindas' => $ter_rindas, 'arpus' => $arpus]));
    }

    $pageTitle = 'Latvijas lielākie uzņēmumi pa novadiem un pilsētām — TOP-100 un 42 teritorijas';
    $pageDesc = 'Lielākie Latvijas uzņēmumi pēc apgrozījuma: Latvijas TOP-100 un 42 novadu un pilsētu pārskati — uzņēmumu skaits, apgrozījums, darbinieki, lielākais uzņēmums katrā teritorijā.';
    $il_items = [];
    foreach (array_slice($top, 0, 10) as $i => $r) $il_items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => (string)$r['name'], 'url' => $base . '/' . $r['regcode']];
    $pageJsonLd = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Lielākie uzņēmumi', 'item' => $base . '/top/']]],
        ['@type' => 'ItemList', 'name' => 'Latvijas lielākie uzņēmumi pēc apgrozījuma', 'itemListElement' => $il_items],
        ['@type' => 'CollectionPage', 'name' => $pageTitle, 'description' => $pageDesc, 'url' => $base . '/top/', 'dateModified' => $data_updated],
    ]];
    $crumbs = [['Lielākie uzņēmumi', '/top/']];
} else {
    // ── TERITORIJAS LAPA ─────────────────────────────────────────────────────
    [$loc, $nos, $gen] = $ter;
    [$wh, $wp] = tp_where($loc, $tp_parcelt);
    // Dienas kešs arī teritorijas lapai: katalogs mainās reizi naktī, bet 5 vaicājumi bez
    // location indeksa ir 5 pilni 220k rindu skeni (100–500 ms atkarībā no CPU slodzes).
    $ter_kess = tp_kesa_cels($db_file, 'ter4_' . $slug);
    $tk = is_file($ter_kess) ? json_decode((string)file_get_contents($ter_kess), true) : null;
    if (is_array($tk) && isset($tk['agg'], $tk['tab'], $tk['nozares'])) {
        ['agg' => $agg, 'tab' => $tab, 'nozares' => $nozares] = $tk;
    } else {
    // Izmaiņu % TIKAI pāra rindās (kur ir arī iepriekšējais periods): SUM(employees) pret
    // SUM(prev_employees) salīdzināja dažādas kopas un deva +18,7 % īsto +3,6 % vietā
    // (recenzija 2026-08-22; tā pati formula mantota no nozare_nace.php).
    $st = $pdo->prepare("SELECT COUNT(*) c, SUM(turnover) t, SUM(prev_turnover) pt,
            SUM(CASE WHEN prev_turnover IS NOT NULL THEN turnover END) t_pair,
            SUM(employees) e, SUM(prev_employees) pe,
            SUM(CASE WHEN prev_employees IS NOT NULL THEN employees END) e_pair,
            SUM(profit) p, SUM(prev_profit) pp,
            SUM(CASE WHEN turnover IS NOT NULL THEN 1 ELSE 0 END) c_fin,
            SUM(avg_net_salary * employees) pay,
            SUM(CASE WHEN avg_net_salary IS NOT NULL THEN employees ELSE 0 END) pay_e
        FROM companies WHERE $wh");
    $st->execute($wp);
    $agg = $st->fetch() ?: ['c' => 0];   // $cnt ("uzņēmumi ar gada pārskatu" — viens skaitlis visur) rēķinās aiz keša bloka

    // VIENA šķirojama tabula trīs paneļu vietā (Girts 2026-08-22): lai šķirošana pēc
    // darbiniekiem/peļņas/algas dotu ĪSTOS TOP, ne tikai pārkārtotas pēc apgrozījuma
    // atlasītās 50 rindas, rindu kopa ir APVIENOJUMS: 100 lielākie pēc apgrozījuma +
    // 50 lielākie darba devēji + 50 pelnošākie + 50 ar augstāko neto algu (≥3 darb.).
    // Noklusējuma secība — pēc apgrozījuma; bez JavaScript paliek tā.
    $st = $pdo->prepare("SELECT regcode, name, nace_code_np, turnover, turnover_change, profit, profit_change,
            employees, employees_change, avg_net_salary, salary_change
        FROM companies WHERE regcode IN (
            SELECT regcode FROM (SELECT regcode FROM companies WHERE $wh AND turnover > 0 ORDER BY turnover DESC, name_sort ASC LIMIT 100)
            UNION SELECT regcode FROM (SELECT regcode FROM companies WHERE $wh AND employees > 0 ORDER BY employees DESC, name_sort ASC LIMIT 50)
            UNION SELECT regcode FROM (SELECT regcode FROM companies WHERE $wh AND profit > 0 ORDER BY profit DESC, name_sort ASC LIMIT 50)
            UNION SELECT regcode FROM (SELECT regcode FROM companies WHERE $wh AND employees >= 3 AND avg_net_salary IS NOT NULL ORDER BY avg_net_salary DESC, name_sort ASC LIMIT 50))
        ORDER BY (turnover IS NULL) ASC, turnover DESC, name_sort ASC");
    $st->execute(array_merge($wp, $wp, $wp, $wp)); $tab = $st->fetchAll();
    // Nozaru struktūra: NACE nodaļas (2 cipari) pēc apgrozījuma. 40 % uzņēmumu katalogā
    // nace_code_np = 'UNDEFINED' — tie te neiet (citādi rastos viltus nodaļa "UN").
    $st = $pdo->prepare("SELECT substr(nace_code_np, 1, 2) nod, COUNT(*) c, SUM(turnover) t, SUM(employees) e
        FROM companies WHERE $wh AND nace_code_np GLOB '[0-9][0-9]*'
        GROUP BY nod ORDER BY t DESC LIMIT 10");
    $st->execute($wp); $nozares = $st->fetchAll();
    @file_put_contents($ter_kess, json_encode(['agg' => $agg, 'tab' => $tab, 'nozares' => $nozares]));
    }
    // Lede un ItemList: lielākais pēc apgrozījuma un lielākais darba devējs no tās pašas kopas.
    $top = array_values(array_filter($tab, static fn($r) => (float)($r['turnover'] ?? 0) > 0));
    $top_emp = array_values(array_filter($tab, static fn($r) => (int)($r['employees'] ?? 0) > 0));
    usort($top_emp, static fn($a, $b) => ((int)$b['employees'] <=> (int)$a['employees']) ?: strcmp((string)$a['name'], (string)$b['name']));
    $cnt = (int)($agg['c_fin'] ?? 0);
    $avg_net = ($agg['pay_e'] ?? 0) >= 3 ? (float)$agg['pay'] / (float)$agg['pay_e'] : null;
    $chg_turnover = $chg($agg['t_pair'] ?? 0, $agg['pt'] ?? 0);
    $chg_emp = $chg($agg['e_pair'] ?? 0, $agg['pe'] ?? 0);

    $pageTitle = "$gen lielākie uzņēmumi: apgrozījums, peļņa, darbinieki, algas";
    $pageDesc = "$nos: " . tp_n0($cnt) . ' ' . tp_dsk($cnt, 'uzņēmums', 'uzņēmumi') . ' ar gada pārskatu, kopējais apgrozījums ' . tp_money_short((float)($agg['t'] ?? 0))
        . ', ' . tp_n0($agg['e'] ?? null) . ' ' . tp_dsk((int)($agg['e'] ?? 0), 'darbinieks', 'darbinieki') . ($avg_net !== null ? ', vidējā neto alga ' . tp_n0($avg_net) . ' €/mēn' : '')
        . '. Šķirojama TOP tabula: apgrozījums, peļņa, darbinieki, algas un to izmaiņas.';
    $il_items = [];
    foreach (array_slice($top, 0, 10) as $i => $r) $il_items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => (string)$r['name'], 'url' => $base . '/' . $r['regcode']];
    $pageJsonLd = ['@context' => 'https://schema.org', '@graph' => array_values(array_filter([
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Lielākie uzņēmumi', 'item' => $base . '/top/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $nos, 'item' => $base . '/top/' . TP_TERITORIJAS[$loc][2]]]],
        $il_items ? ['@type' => 'ItemList', 'name' => "$gen lielākie uzņēmumi pēc apgrozījuma", 'itemListElement' => $il_items] : null,
        ['@type' => 'CollectionPage', 'name' => $pageTitle, 'description' => $pageDesc, 'url' => $base . '/top/' . TP_TERITORIJAS[$loc][2], 'dateModified' => $data_updated],
    ]))];
    $crumbs = [['Lielākie uzņēmumi', '/top/'], [$nos, '/top/' . TP_TERITORIJAS[$loc][2]]];
}

ob_start(); ?>
    <style>
      main.tp-main { max-width: 1100px; margin: 0 auto; padding: 16px 20px 40px; }
      .tp-crumbs { font-size: 14px; color: #555; margin: 0 0 14px 0; }
      .tp-crumbs a { color: #007bff; text-decoration: none; } .tp-crumbs a:hover { text-decoration: underline; }
      .tp-crumbs .sep { margin: 0 6px; color: #999; }
      .tp-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 20px; margin-bottom: 20px; }
      .tp-panel h1 { font-size: 24px; margin: 0 0 6px 0; }
      h2.tp-h2 { font-size: 18px; margin: 0 0 12px 0; }
      .tp-lede { font-size: 15px; color: #374151; line-height: 1.55; margin: 8px 0 14px 0; }
      .tp-stats { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 4px 0; }
      .tp-chip { background: #eef4ff; border: 1px solid #c5d8f5; color: #2c5282; border-radius: 6px; padding: 6px 12px; font-size: 13.5px; }
      .tp-chip strong { color: #1a365d; }
      .tp-note { font-size: 12.5px; color: #6b7280; margin-top: 10px; }
      .tp-table-wrap { overflow-x: auto; }
      table.tp-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
      .tp-table th { text-align: left; background: #f8fafc; color: #64748b; font-weight: 600; padding: 8px 10px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
      .tp-table td { padding: 7px 10px; border-bottom: 1px solid #f0f4f8; white-space: nowrap; vertical-align: top; }
      /* Uzņēmuma nosaukums: fiksēts platums, garie nosaukumi lūst 2–3 rindās (kolonna neizstiepj tabulu). */
      .tp-table td.name { white-space: normal; width: 1%; } /* 1 % = kolonna tieši bloka platumā; atlikums tiek pārējām */
      .tp-nm { display: block; width: 260px; white-space: normal; overflow-wrap: anywhere; line-height: 1.3; }
      /* Izmaiņu % kā paskaidrojums zem pamatvērtības (nešķiro). */
      .tp-chg { display: block; font-size: 11.5px; line-height: 1.25; margin-top: 1px; font-weight: 500; }
      @media (max-width: 640px) { .tp-nm { width: 190px; } }
      .tp-table td.num, .tp-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
      .tp-table td.nr { color: #94a3b8; width: 2.2em; }
      .tp-table a { color: #007bff; text-decoration: none; } .tp-table a:hover { text-decoration: underline; }
      .tp-table .nace { color: #6b7280; font-size: 12px; }
      .tp-pos { color: #2f855a; } .tp-neg { color: #c53030; }
      .tp-grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(480px,100%), 1fr)); gap: 20px; }
      .tp-ter { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 8px; list-style: none; padding: 0; margin: 0; }
      .tp-ter li { border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; font-size: 13.5px; background: #fbfdff; }
      .tp-ter a { color: #007bff; text-decoration: none; font-weight: 600; } .tp-ter a:hover { text-decoration: underline; }
      .tp-ter .meta { color: #6b7280; font-size: 12.5px; margin-top: 2px; }
      .tp-bar { display:inline-block; height: 8px; background: #c5d8f5; border-radius: 2px; vertical-align: middle; }
      th.tp-sort { cursor: pointer; user-select: none; }
      th.tp-sort:hover, th.tp-sort:focus { color: #1a365d; outline: none; text-decoration: underline; }
      th[aria-sort="descending"]::after { content: " ▼"; font-size: 10px; }
      th[aria-sort="ascending"]::after { content: " ▲"; font-size: 10px; }
      .tp-sortbar { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 0 0 10px 0; font-size: 13px; color: #6b7280; }
      .tp-sortbar button { border: 1px solid #cfd6de; background: #f4f6f8; color: #3b5a8a; border-radius: 5px; padding: 3px 10px; font-size: 12.5px; cursor: pointer; }
      .tp-sortbar button:hover, .tp-sortbar button.active { background: #e1ebf7; border-color: #9fb7d6; }
      .tp-table tr.tp-top3 td.nr { color: #1a365d; font-weight: 700; }
    </style>
<?php
$extraHeadContent = ob_get_clean();
$nace_link = static function (?string $np): string {
    $np = (string)$np;
    // 'UNDEFINED' (40 % katalogā) un citi ne-cipari: bez saites — /nozare/UN.DE deva 404.
    if (!preg_match('/^\d{2,4}$/', $np)) return '<span class="nace">—</span>';
    $kods = strlen($np) >= 3 ? substr($np, 0, 2) . '.' . substr($np, 2) : $np;
    return '<a class="nace" href="/nozare/' . tp_e($kods) . '" title="Nozare ' . tp_e($kods) . '">' . tp_e($kods) . '</a>';
};
/** Izmaiņu % kā paskaidrojums zem pamatvērtības (ne atsevišķa kolonna, nešķiro). */
$chg_sub = static function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    // No gandrīz nulles bāzes sanāk «+62 545,9 %» — troksnis; rādām tikai virzienu virs ±1 000 %.
    $txt = abs($f) >= 1000 ? ($f > 0 ? '> +1 000 %' : '< −1 000 %') : tp_pct($f);
    return '<span class="tp-chg ' . ($f >= 0 ? 'tp-pos' : 'tp-neg') . '" title="Izmaiņas pret iepriekšējo pārskata periodu">' . tp_e($txt) . '</span>';
};
/** Skaitliska šūna ar neapstrādātu vērtību šķirošanai; $sub — neobligāts paskaidrojums (HTML) zem vērtības. */
$num_cell = static function ($raw, string $teksts, string $sub = ''): string {
    return '<td class="num" data-v="' . ($raw === null ? '' : tp_e((string)$raw)) . '">' . tp_e($teksts) . $sub . '</td>';
};
/** Uzņēmuma nosaukuma šūna: fiksēta platuma bloks, saite uz uzņēmuma lapu. */
$name_cell = static function (string $regcode, string $name, string $piedeva = ''): string {
    return '<td class="name" data-v="' . tp_e(mb_strtolower($name)) . '"><span class="tp-nm"><a href="/' . tp_e($regcode) . '">' . tp_e($name) . '</a>' . $piedeva . '</span></td>';
};
/** Šķirojamas tabulas galvene: $kols = [[teksts, 'num'|'txt'|'no', vai noklusējuma dilstoši]]. */
$thead = static function (array $kols): string {
    $h = '<thead><tr>';
    foreach ($kols as [$t, $k, $def]) {
        $h .= '<th' . ($k === 'num' ? ' class="num"' : '') . ' data-k="' . $k . '"' . ($def ? ' aria-sort="descending"' : '') . '>' . tp_e($t) . '</th>';
    }
    return $h . '</tr></thead>';
};
?>
<!DOCTYPE html>
<html lang="lv">
<?php include __DIR__ . '/registrs/head/head.php'; ?>
<body>
<?php include __DIR__ . '/registrs/header.php'; ?>
<main class="tp-main">
    <nav class="tp-crumbs" aria-label="Atrašanās vieta">
<?php foreach ($crumbs as $i => [$n, $u]): ?>
<?php if ($i > 0): ?><span class="sep">›</span><?php endif; ?>
<?php if ($i < count($crumbs) - 1): ?><a href="<?= tp_e($u) ?>"><?= tp_e($n) ?></a><?php else: ?><strong><?= tp_e($n) ?></strong><?php endif; ?>
<?php endforeach; ?>
    </nav>

<?php if ($ter === null): ?>
    <div class="tp-panel">
        <h1>Latvijas lielākie uzņēmumi pa novadiem un pilsētām</h1>
        <p class="tp-lede">
            Latvijā <strong><?= tp_n0($agg['c_fin'] ?? 0) ?> uzņēmumi</strong> ar jaunāko gada pārskatu (vēl <?= tp_n0((int)($agg['c'] ?? 0) - (int)($agg['c_fin'] ?? 0)) ?>
            katalogā ir tikai ar darbinieku datiem), kopējais apgrozījums
            <strong><?= tp_e(tp_money_short((float)($agg['t'] ?? 0))) ?></strong><?= ($c = $chg($agg['t_pair'] ?? 0, $agg['pt'] ?? 0)) !== null ? ' (' . tp_e(tp_pct($c)) . ' pret iepriekšējo periodu)' : '' ?>,
            <strong><?= tp_n0($agg['e'] ?? null) ?> darbinieki</strong>. Zemāk — Latvijas TOP-100 pēc apgrozījuma un
            <strong>42 teritoriju</strong> pārskati (7 valstspilsētas un 35 novadi), katrā —
            lielākie uzņēmumi, darba devēji un nozaru struktūra.
        </p>
        <p class="tp-note">Dati: UR/VID atvērtie dati, jaunākais iesniegtais gada pārskats katram uzņēmumam (katalogs atjaunots <?= tp_e($data_updated) ?>).
            Teritorija — pēc juridiskās adreses; adreses ar pirms 2021. gada novadiem pieskaitītas pašreizējiem.
            <?= $arpus > 0 ? tp_n0($arpus) . ' uzņēmumiem adrese ir ārpus Latvijas vai nav norādīta — tie ir Latvijas kopsummā, bet ne teritorijās.' : '' ?></p>
    </div>

    <div class="tp-panel">
        <h2 class="tp-h2">Novadi un pilsētas — šķiro pēc jebkuras kolonnas</h2>
        <p class="tp-sortbar">Šķirot pēc:
            <button type="button" data-sort-col="2">Apgrozījums</button><button type="button" data-sort-col="1">Uzņēmumi</button><button type="button" data-sort-col="3">Darbinieki</button><button type="button" data-sort-col="0">Nosaukums</button></p>
<?php $mx = max(1.0, (float)($ter_rindas[0]['t'] ?? 1)); ?>
        <div class="tp-table-wrap"><table class="tp-table" data-sortable>
            <?= $thead([['Teritorija', 'txt', false], ['Uzņēmumi ar pārskatu', 'num', false], ['Apgrozījums', 'num', true], ['Darbinieki', 'num', false], ['Lielākais uzņēmums', 'txt', false]]) ?>
            <tbody>
<?php foreach ($ter_rindas as $r): ?>
            <tr><td data-v="<?= tp_e(mb_strtolower($r['nos'])) ?>"><a href="/top/<?= tp_e($r['slug']) ?>"><?= tp_e($r['nos']) ?></a></td>
                <?= $num_cell($r['c_fin'], tp_n0($r['c_fin'])) ?>
                <td class="num" data-v="<?= tp_e((string)$r['t']) ?>"><span class="tp-bar" style="width:<?= max(2, (int)round(60 * $r['t'] / $mx)) ?>px"></span> <?= tp_e(tp_money_short($r['t'])) ?></td>
                <?= $num_cell($r['e'], tp_n0($r['e'])) ?>
                <?= $r['top1'] ? $name_cell((string)$r['top1']['regcode'], (string)$r['top1']['name'], ' <span class="nace">' . tp_e(tp_money_short((float)$r['top1']['turnover'])) . '</span>') : '<td class="name" data-v="">—</td>' ?></tr>
<?php endforeach; ?>
            </tbody></table></div>
    </div>

    <div class="tp-panel">
        <h2 class="tp-h2">Latvijas TOP-100 — šķiro pēc jebkuras kolonnas</h2>
        <p class="tp-sortbar">Šķirot pēc:
            <button type="button" data-sort-col="4">Apgrozījums</button><button type="button" data-sort-col="5">Peļņa</button><button type="button" data-sort-col="6">Darbinieki</button><button type="button" data-sort-col="3">Teritorija</button><button type="button" data-sort-col="1">Nosaukums</button></p>
        <div class="tp-table-wrap"><table class="tp-table" data-sortable>
            <?= $thead([['#', 'no', false], ['Uzņēmums', 'txt', false], ['Nozare', 'txt', false], ['Teritorija', 'txt', false], ['Apgrozījums', 'num', true], ['Peļņa', 'num', false], ['Darbinieki', 'num', false]]) ?>
            <tbody>
<?php foreach ($top as $i => $r): $terN = TP_TERITORIJAS[TP_VECIE_NOVADI[$r['location']] ?? $r['location']][0] ?? (string)$r['location']; ?>
            <tr><td class="nr"><?= $i + 1 ?></td><?= $name_cell((string)$r['regcode'], (string)$r['name']) ?>
                <td data-v="<?= tp_e((string)$r['nace_code_np']) ?>"><?= $nace_link($r['nace_code_np']) ?></td><td data-v="<?= tp_e(mb_strtolower($terN)) ?>"><?= tp_e($terN) ?></td>
                <?= $num_cell($r['turnover'], tp_money_short((float)$r['turnover']), $chg_sub($r['turnover_change'])) ?>
                <?= $num_cell($r['profit'], tp_money_short($r['profit'] !== null ? (float)$r['profit'] : null)) ?><?= $num_cell($r['employees'], tp_n0($r['employees'])) ?></tr>
<?php endforeach; ?>
            </tbody></table></div>
        <p class="tp-note">Tabulā ir 100 lielākie pēc apgrozījuma — šķirošana pēc citas kolonnas pārkārto šos pašus 100. Mazie procenti zem apgrozījuma — izmaiņas pret iepriekšējo pārskata periodu.
            Pilnie darba devēju un peļņas TOP katrā teritorijā ir teritoriju lapās.</p>
    </div>

<?php else: ?>
    <div class="tp-panel">
        <h1><?= tp_e($gen) ?> lielākie uzņēmumi</h1>
<?php if ($cnt === 0): ?>
        <p class="tp-lede">Šai teritorijai katalogā nav uzņēmumu ar gada pārskatu.</p>
<?php else: ?>
        <p class="tp-lede">
            <?= tp_e($nos) ?>: <strong><?= tp_n0($cnt) ?> <?= tp_dsk($cnt, 'uzņēmums', 'uzņēmumi') ?></strong> ar jaunāko gada pārskatu,
            kopējais apgrozījums <strong><?= tp_e(tp_money_short((float)($agg['t'] ?? 0))) ?></strong><?= $chg_turnover !== null ? ' (' . tp_e(tp_pct($chg_turnover)) . ' pret iepriekšējo periodu)' : '' ?>
            un <strong><?= tp_n0($agg['e'] ?? null) ?> <?= tp_dsk((int)($agg['e'] ?? 0), 'darbinieks', 'darbinieki') ?></strong><?= $chg_emp !== null ? ' (' . tp_e(tp_pct($chg_emp)) . ')' : '' ?>.
<?php if ($avg_net !== null): ?>
            Vidējā neto alga: <strong>~<?= tp_n0($avg_net) ?> € mēnesī</strong>.
<?php endif; ?>
<?php if (!empty($top)): ?>
            Lielākais uzņēmums pēc apgrozījuma — <a href="/<?= tp_e($top[0]['regcode']) ?>"><?= tp_e($top[0]['name']) ?></a>
            (<?= tp_e(tp_money_short((float)$top[0]['turnover'])) ?>)<?= !empty($top_emp) ? ', lielākais darba devējs — <a href="/' . tp_e($top_emp[0]['regcode']) . '">' . tp_e($top_emp[0]['name']) . '</a> (' . tp_n0($top_emp[0]['employees']) . ' ' . tp_dsk((int)$top_emp[0]['employees'], 'darbinieks', 'darbinieki') . ')' : '' ?>.
<?php endif; ?>
        </p>
        <div class="tp-stats">
            <span class="tp-chip">Uzņēmumi ar pārskatu: <strong><?= tp_n0($cnt) ?></strong></span>
            <span class="tp-chip">Apgrozījums: <strong><?= tp_e(tp_money_short((float)($agg['t'] ?? 0))) ?></strong></span>
            <span class="tp-chip">Peļņa kopā: <strong><?= tp_e(tp_money_short((float)($agg['p'] ?? 0))) ?></strong></span>
            <span class="tp-chip">Darbinieki: <strong><?= tp_n0($agg['e'] ?? null) ?></strong></span>
<?php if ($avg_net !== null): ?>
            <span class="tp-chip">Vid. neto alga: <strong>~<?= tp_n0($avg_net) ?> €/mēn</strong></span>
<?php endif; ?>
        </div>
<?php endif; ?>
        <p class="tp-note">Dati: UR/VID atvērtie dati, jaunākais gada pārskats katram uzņēmumam (katalogs atjaunots <?= tp_e($data_updated) ?>).
            Teritorija — pēc juridiskās adreses (vecie novadi pieskaitīti pašreizējiem, arī Varakļānu novads Madonas novadam kopš 2025. gada); uzņēmumi, kas darbojas
            <?= tp_e($nos === 'Rīga' ? 'Rīgā' : 'šeit') ?>, bet reģistrēti citur, sarakstā nav. Izmaiņu % — pret iepriekšējo pārskata periodu.</p>
    </div>

<?php if (!empty($tab)): ?>
    <div class="tp-panel">
        <h2 class="tp-h2">TOP uzņēmumi — šķiro pēc apgrozījuma, peļņas, darbiniekiem vai algas</h2>
        <p class="tp-sortbar">Šķirot pēc:
            <button type="button" data-sort-col="3">Apgrozījums</button><button type="button" data-sort-col="4">Peļņa</button><button type="button" data-sort-col="5">Darbinieki</button><button type="button" data-sort-col="6">Neto alga</button><button type="button" data-sort-col="1">Nosaukums</button></p>
        <div class="tp-table-wrap"><table class="tp-table" data-sortable>
            <?= $thead([['#', 'no', false], ['Uzņēmums', 'txt', false], ['Nozare', 'txt', false], ['Apgrozījums', 'num', true], ['Peļņa', 'num', false], ['Darbinieki', 'num', false], ['Neto alga', 'num', false]]) ?>
            <tbody>
<?php foreach ($tab as $i => $r): $alga_ok = $r['avg_net_salary'] !== null && (int)$r['employees'] >= 3; ?>
            <tr><td class="nr"><?= $i + 1 ?></td><?= $name_cell((string)$r['regcode'], (string)$r['name']) ?>
                <td data-v="<?= tp_e((string)$r['nace_code_np']) ?>"><?= $nace_link($r['nace_code_np']) ?></td>
                <?= $num_cell($r['turnover'], tp_money_short($r['turnover'] !== null ? (float)$r['turnover'] : null), $chg_sub($r['turnover_change'])) ?>
                <?= $num_cell($r['profit'], tp_money_short($r['profit'] !== null ? (float)$r['profit'] : null), $chg_sub($r['profit_change'])) ?>
                <?= $num_cell($r['employees'], tp_n0($r['employees']), $chg_sub($r['employees_change'])) ?>
                <?= $num_cell($alga_ok ? $r['avg_net_salary'] : null, $alga_ok ? tp_n0($r['avg_net_salary']) . ' €' : '—', $alga_ok ? $chg_sub($r['salary_change']) : '') ?></tr>
<?php endforeach; ?>
            </tbody></table></div>
        <p class="tp-note">Tabulā <?= count($tab) ?> uzņēmumi: 100 lielākie pēc apgrozījuma, 50 lielākie darba devēji, 50 pelnošākie un 50 ar augstāko
            neto algu (rādīta tikai uzņēmumiem ar ≥3 darbiniekiem) — tāpēc šķirošana pēc jebkuras no šīm kolonnām dod īsto TOP, ne tikai pārkārtojumu.
            Mazie procenti zem vērtībām — izmaiņas pret iepriekšējo pārskata periodu.</p>
    </div>
<?php endif; ?>

<?php if (!empty($nozares)): ?>
    <div class="tp-panel">
        <h2 class="tp-h2">Nozaru struktūra <?= tp_e($nos === 'Rīga' ? 'Rīgā' : $gen . ' uzņēmumos') ?></h2>
        <div class="tp-table-wrap"><table class="tp-table" data-sortable>
            <?= $thead([['#', 'no', false], ['NACE nodaļa', 'txt', false], ['Uzņēmumi', 'num', false], ['Apgrozījums', 'num', true], ['Daļa', 'num', false], ['Darbinieki', 'num', false]]) ?>
            <tbody>
<?php $tk = max(1.0, (float)($agg['t'] ?? 1)); foreach ($nozares as $i => $r): $nod = (string)$r['nod']; $dala = 100 * (float)($r['t'] ?? 0) / $tk; ?>
            <tr><td class="nr"><?= $i + 1 ?></td><td data-v="<?= tp_e($nod) ?>"><a href="/nozare/<?= tp_e($nod) ?>"><?= tp_e($nod) ?> <?= tp_e($nace_nod[$nod] ?? '') ?></a></td>
                <?= $num_cell($r['c'], tp_n0($r['c'])) ?><?= $num_cell($r['t'], tp_money_short((float)($r['t'] ?? 0))) ?>
                <?= $num_cell(round($dala, 2), number_format($dala, 1, ',', ' ') . ' %') ?><?= $num_cell($r['e'], tp_n0($r['e'])) ?></tr>
<?php endforeach; ?>
            </tbody></table></div>
        <p class="tp-note">Visas teritorijas: <a href="/top/">Latvijas lielākie uzņēmumi pa novadiem un pilsētām</a>. Nozaru TOP visā Latvijā — <a href="/nozare.php">nozaru katalogā</a>.</p>
    </div>
<?php endif; ?>
<?php endif; ?>
<?php if (reg_test_env()): ?>
    <p class="tp-note">Renderēts <?= number_format((microtime(true) - $t0) * 1000, 0) ?> ms.</p>
<?php endif; ?>
</main>
<script>
// Tabulu šķirošana klienta pusē: th[data-k] = num | txt | no (# kolonna, nešķiro);
// td[data-v] = neapstrādātā vērtība. Skaitļiem pirmais klikšķis — dilstoši, tekstam — augoši;
// tukšas vērtības vienmēr beigās; "#" kolonna pārnumurējas, lai rinda = vieta pēc šīs kolonnas.
(function () {
    function vert(td) { return td ? td.getAttribute('data-v') : null; }
    function sortTable(table, i, desc) {
        var th = table.tHead.rows[0].cells[i], txt = th.getAttribute('data-k') === 'txt';
        var tb = table.tBodies[0], rows = Array.prototype.slice.call(tb.rows);
        rows.sort(function (a, b) {
            var x = vert(a.cells[i]), y = vert(b.cells[i]);
            var xe = x === null || x === '', ye = y === null || y === '';
            if (xe && ye) return 0; if (xe) return 1; if (ye) return -1;
            if (txt) return desc ? y.localeCompare(x, 'lv') : x.localeCompare(y, 'lv');
            x = parseFloat(x); y = parseFloat(y);
            return desc ? y - x : x - y;
        });
        rows.forEach(function (r, n) { tb.appendChild(r); var c = r.querySelector('td.nr'); if (c) c.textContent = n + 1; r.classList.toggle('tp-top3', n < 3); });
        Array.prototype.forEach.call(table.tHead.rows[0].cells, function (h) { h.removeAttribute('aria-sort'); });
        th.setAttribute('aria-sort', desc ? 'descending' : 'ascending');
        var bar = table.parentNode.parentNode.querySelector('.tp-sortbar');
        if (bar) Array.prototype.forEach.call(bar.querySelectorAll('button'), function (b) { b.classList.toggle('active', parseInt(b.getAttribute('data-sort-col'), 10) === i); });
    }
    document.querySelectorAll('table.tp-table[data-sortable]').forEach(function (table) {
        Array.prototype.forEach.call(table.tHead.rows[0].cells, function (th, i) {
            var k = th.getAttribute('data-k');
            if (k === 'no') return;
            th.classList.add('tp-sort'); th.tabIndex = 0; th.setAttribute('role', 'button'); th.title = 'Šķirot pēc šīs kolonnas';
            function go() { var cur = th.getAttribute('aria-sort'); var desc = k === 'txt' ? cur === 'ascending' : cur !== 'descending'; sortTable(table, i, desc); }
            th.addEventListener('click', go);
            th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
        });
        var bar = table.parentNode.parentNode.querySelector('.tp-sortbar');
        if (bar) Array.prototype.forEach.call(bar.querySelectorAll('button[data-sort-col]'), function (b) {
            b.addEventListener('click', function () { var i = parseInt(b.getAttribute('data-sort-col'), 10); var k = table.tHead.rows[0].cells[i].getAttribute('data-k'); sortTable(table, i, k !== 'txt'); });
        });
        // sākuma stāvoklis: noklusējuma kolonna jau ir aria-sort="descending" no servera, TOP-3 izcelti
        Array.prototype.forEach.call(table.tBodies[0].rows, function (r, n) { r.classList.toggle('tp-top3', n < 3); });
    });
})();
</script>
<?php $footerRich = 'nozare'; include __DIR__ . '/registrs/footer/footer.php'; ?>
</body>
</html>
