<?php
/**
 * nozare_nace.php — per-NACE SEO lapa: /nozare/{kods} (piem., /nozare/47.11).
 * Servera renderēts nozares kopsavilkums + TOP-50 uzņēmumi pēc apgrozījuma +
 * apakšnozaru saites. Dati no nozare/katalogs.sqlite (būvējas reizi diennaktī).
 * MI/rāpuļiem viss saturs ir tīrā HTML; JSON-LD: BreadcrumbList + ItemList.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('nozare');

ini_set('display_errors', '0');
error_reporting(E_ALL);

// Kanoniskais bez beigu slīpsvītras: /nozare/47.11/ -> /nozare/47.11
$req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (strlen($req_path) > 1 && str_ends_with($req_path, '/')) {
    header('Location: ' . rtrim($req_path, '/'), true, 301);
    return;
}

$kods_raw = trim((string)($_GET['kods'] ?? ''));
$kods = strtoupper($kods_raw);
$valid = preg_match('/^([A-U]|[0-9]{2}(\.[0-9]{1,2})?)$/', $kods) === 1;

// Mazie burti (/nozare/a) atdeva 200 ar identisku saturu un kanonizējās paši uz
// sevi — divi URL vienai lapai (audits 2026-08-19). 301 uz lielo burtu formu, ko
// deklarē arī sitemap.
if ($valid && $kods_raw !== $kods && PHP_SAPI !== 'cli') {
    header('Location: /nozare/' . $kods, true, 301);
    return;
}

$db_file = __DIR__ . '/nozare/katalogs.sqlite';
$node = null;
if ($valid && is_file($db_file)) {
    try {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $st = $pdo->prepare('SELECT code, name, parent_code, level FROM nace WHERE code = ?');
        $st->execute([$kods]);
        $node = $st->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('nozare_nace.php DB: ' . $e->getMessage());
    }
}

if ($node === null) {
    http_response_code(404);
    $f404 = __DIR__ . '/404.php';
    if (is_file($f404)) { $_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: __DIR__; require $f404; }
    return;
}

function nz_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nz_n0($v): string { return $v === null ? '—' : number_format(round((float)$v), 0, ',', ' '); }
function nz_pct($v): string {
    if ($v === null) return '';
    $s = number_format((float)$v, 1, ',', ' ');
    return ((float)$v >= 0 ? '+' : '') . $s . ' %';
}
/** Naudas saīsinājums kopsummām: 1,2 mljrd. / 45,6 milj. / 890 tūkst. EUR */
function nz_money_short(?float $v): string {
    if ($v === null) return '—';
    $a = abs($v);
    if ($a >= 1e9) return number_format($v / 1e9, 1, ',', ' ') . ' mljrd. EUR';
    if ($a >= 1e6) return number_format($v / 1e6, 1, ',', ' ') . ' milj. EUR';
    if ($a >= 1e3) return number_format($v / 1e3, 0, ',', ' ') . ' tūkst. EUR';
    return number_format($v, 0, ',', ' ') . ' EUR';
}

// Senči (ceļam un breadcrumb).
$ancestors = [];
$pc = $node['parent_code'];
$guard = 0;
while ($pc !== null && $pc !== '' && $guard++ < 6) {
    $st = $pdo->prepare('SELECT code, name, parent_code, level FROM nace WHERE code = ?');
    $st->execute([$pc]);
    $a = $st->fetch();
    if (!$a) break;
    array_unshift($ancestors, $a);
    $pc = $a['parent_code'];
}

// Bērni.
$st = $pdo->prepare('SELECT code, name, level FROM nace WHERE parent_code = ? ORDER BY code');
$st->execute([$kods]);
$children = $st->fetchAll();

// WHERE pēc līmeņa: uzņēmumu nace_code_np ir 4 cipari bez punkta (vai UNDEFINED).
$activity = '(employees > 0 OR turnover > 0 OR profit != 0)';
$np = str_replace('.', '', $kods);
$params = [];
if ((int)$node['level'] === 1) {
    // Sekcija (burts): apvieno bērnu nodaļu prefiksus.
    $prefixes = [];
    foreach ($children as $ch) $prefixes[] = str_replace('.', '', $ch['code']);
    if (empty($prefixes)) {
        $where = '1=0';
    } else {
        $like = [];
        foreach ($prefixes as $p) { $like[] = 'nace_code_np LIKE ?'; $params[] = $p . '%'; }
        $where = '(' . implode(' OR ', $like) . ") AND $activity";
    }
} elseif ((int)$node['level'] === 4) {
    $where = "nace_code_np = ? AND $activity";
    $params[] = $np;
} else {
    $where = "nace_code_np LIKE ? AND $activity";
    $params[] = $np . '%';
}

// Nozares kopsavilkums (tās pašas formulas kā nozare.php kartītēm).
$st = $pdo->prepare("SELECT COUNT(*) c, SUM(turnover) t, SUM(prev_turnover) pt,
        SUM(employees) e, SUM(prev_employees) pe, SUM(profit) p, SUM(prev_profit) pp,
        SUM(avg_net_salary * employees) pay,
        SUM(CASE WHEN avg_net_salary IS NOT NULL THEN employees ELSE 0 END) pay_e
    FROM companies WHERE $where");
$st->execute($params);
$agg = $st->fetch() ?: ['c' => 0];
$cnt = (int)($agg['c'] ?? 0);
// Vidējo algu rāda tikai, ja aiz tās stāv >=3 darbinieki (privātums mikronozarēs).
$avg_net = ($agg['pay_e'] ?? 0) >= 3 ? (float)$agg['pay'] / (float)$agg['pay_e'] : null;
$chg = function ($cur, $prev) {
    $cur = (float)($cur ?? 0); $prev = (float)($prev ?? 0);
    return $prev == 0.0 ? null : (($cur - $prev) / abs($prev)) * 100;
};
$chg_turnover = $chg($agg['t'] ?? 0, $agg['pt'] ?? 0);
$chg_emp = $chg($agg['e'] ?? 0, $agg['pe'] ?? 0);

// TOP-50 pēc apgrozījuma.
$st = $pdo->prepare("SELECT regcode, name, turnover, turnover_change, profit, employees,
        avg_net_salary, location, change_period
    FROM companies WHERE $where
    ORDER BY (turnover IS NULL) ASC, turnover DESC, name_sort ASC LIMIT 50");
$st->execute($params);
$top = $st->fetchAll();

// Apakšnozaru statistika vienā vaicājumā (grupē pa np, kartē uz bērnu prefiksu).
$child_stats = [];
if (!empty($children) && $cnt > 0) {
    $st = $pdo->prepare("SELECT nace_code_np np, COUNT(*) c, SUM(turnover) t FROM companies WHERE $where GROUP BY nace_code_np");
    $st->execute($params);
    $by_np = $st->fetchAll();
    foreach ($children as $ch) {
        $chp = str_replace('.', '', $ch['code']);
        $cs = ['c' => 0, 't' => 0.0];
        foreach ($by_np as $r) {
            if (str_starts_with((string)$r['np'], $chp)) { $cs['c'] += (int)$r['c']; $cs['t'] += (float)($r['t'] ?? 0); }
        }
        $child_stats[$ch['code']] = $cs;
    }
}

$data_updated = date('Y-m-d', @filemtime($db_file) ?: time());
$level_names = [1 => 'sekcija', 2 => 'nodaļa', 3 => 'grupa', 4 => 'klase'];
$level_name = $level_names[(int)$node['level']] ?? 'nozare';

// SEO galvene (head.php lieto $pageTitle/$pageDesc/$pageJsonLd/$extraHeadContent).
$pageTitle = "{$node['code']} {$node['name']} — nozares uzņēmumi, apgrozījums, algas";
$pageDesc = "NACE {$node['code']} {$node['name']}: {$cnt} uzņēmumi Latvijā, kopējais apgrozījums " . nz_money_short((float)($agg['t'] ?? 0))
    . ", " . nz_n0($agg['e'] ?? null) . " darbinieki" . ($avg_net !== null ? ", vidējā neto alga " . nz_n0($avg_net) . " EUR/mēn" : '')
    . ". Top uzņēmumi ar finanšu datiem.";

$base = 'https://saraksts.lv';
$crumbs = [['Nozares', $base . '/nozare.php']];
foreach ($ancestors as $a) $crumbs[] = ["{$a['code']} {$a['name']}", $base . '/nozare/' . $a['code']];
$crumbs[] = ["{$node['code']} {$node['name']}", $base . '/nozare/' . $node['code']];
$bc_items = [];
foreach ($crumbs as $i => [$n, $u]) {
    $bc_items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $n, 'item' => $u];
}
$il_items = [];
foreach (array_slice($top, 0, 10) as $i => $r) {
    $il_items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => (string)$r['name'], 'url' => $base . '/' . $r['regcode']];
}
$pageJsonLd = ['@context' => 'https://schema.org', '@graph' => array_values(array_filter([
    ['@type' => 'BreadcrumbList', 'itemListElement' => $bc_items],
    !empty($il_items) ? ['@type' => 'ItemList',
        'name' => "Lielākie uzņēmumi nozarē {$node['code']} {$node['name']} pēc apgrozījuma",
        'itemListElement' => $il_items] : null,
    ['@type' => 'CollectionPage', 'name' => $pageTitle, 'description' => $pageDesc,
        'url' => $base . '/nozare/' . $node['code'], 'dateModified' => $data_updated],
]))];

ob_start(); ?>
    <style>
      main.nz-main { max-width: 1100px; margin: 0 auto; padding: 16px 20px 40px; }
      .nz-crumbs { font-size: 14px; color: #555; margin: 0 0 14px 0; }
      .nz-crumbs a { color: #007bff; text-decoration: none; }
      .nz-crumbs a:hover { text-decoration: underline; }
      .nz-crumbs .sep { margin: 0 6px; color: #999; }
      .nz-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 20px; margin-bottom: 20px; }
      .nz-panel h1 { font-size: 24px; margin: 0 0 6px 0; }
      .nz-lede { font-size: 15px; color: #374151; line-height: 1.55; margin: 8px 0 14px 0; }
      .nz-stats { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 4px 0; }
      .nz-chip { background: #eef4ff; border: 1px solid #c5d8f5; color: #2c5282; border-radius: 6px; padding: 6px 12px; font-size: 13.5px; }
      .nz-chip strong { color: #1a365d; }
      .nz-note { font-size: 12.5px; color: #6b7280; margin-top: 10px; }
      .nz-table-wrap { overflow-x: auto; }
      table.nz-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
      .nz-table th { text-align: left; background: #f8fafc; color: #64748b; font-weight: 600; padding: 8px 10px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
      .nz-table td { padding: 7px 10px; border-bottom: 1px solid #f0f4f8; white-space: nowrap; }
      .nz-table td.num, .nz-table th.num { text-align: right; }
      .nz-table a { color: #007bff; text-decoration: none; }
      .nz-table a:hover { text-decoration: underline; }
      .nz-pos { color: #2f855a; } .nz-neg { color: #c53030; }
      .nz-children { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 8px; list-style: none; padding: 0; margin: 0; }
      .nz-children li { border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; font-size: 13.5px; background: #fbfdff; }
      .nz-children a { color: #007bff; text-decoration: none; font-weight: 600; }
      .nz-children a:hover { text-decoration: underline; }
      .nz-children .meta { color: #6b7280; font-size: 12.5px; margin-top: 2px; }
      h2.nz-h2 { font-size: 18px; margin: 0 0 12px 0; }
    </style>
<?php
$extraHeadContent = ob_get_clean();
if ($cnt === 0) $extraHeadContent .= '<meta name="robots" content="noindex,follow">' . "\n";
header('Cache-Control: public, max-age=21600');
?>
<!DOCTYPE html>
<html lang="lv">
<?php include __DIR__ . '/registrs/head/head.php'; ?>
<body>
<?php include __DIR__ . '/registrs/header.php'; ?>
<main class="nz-main">
    <nav class="nz-crumbs" aria-label="Atrašanās vieta">
<?php foreach ($crumbs as $i => [$n, $u]): ?>
<?php if ($i > 0): ?><span class="sep">›</span><?php endif; ?>
<?php if ($i < count($crumbs) - 1): ?><a href="<?= nz_e($u) ?>"><?= nz_e($n) ?></a><?php else: ?><strong><?= nz_e($n) ?></strong><?php endif; ?>
<?php endforeach; ?>
    </nav>

    <div class="nz-panel">
        <h1><?= nz_e($node['code']) ?> — <?= nz_e($node['name']) ?></h1>
        <p class="nz-lede">
            NACE <?= nz_e($level_name) ?> <?= nz_e($node['code']) ?> "<?= nz_e($node['name']) ?>": Latvijā
            <strong><?= nz_n0($cnt) ?> aktīvi uzņēmumi</strong> ar kopējo apgrozījumu
            <strong><?= nz_e(nz_money_short((float)($agg['t'] ?? 0))) ?></strong><?= $chg_turnover !== null ? ' (' . nz_e(nz_pct($chg_turnover)) . ' pret iepriekšējo periodu)' : '' ?>
            un <strong><?= nz_n0($agg['e'] ?? null) ?> darbiniekiem</strong><?= $chg_emp !== null ? ' (' . nz_e(nz_pct($chg_emp)) . ')' : '' ?>.
<?php if ($avg_net !== null): ?>
            Vidējā neto alga nozarē: <strong>~<?= nz_n0($avg_net) ?> EUR mēnesī</strong>.
<?php endif; ?>
<?php if (!empty($top)): ?>
            Lielākais uzņēmums pēc apgrozījuma — <a href="/<?= nz_e($top[0]['regcode']) ?>"><?= nz_e($top[0]['name']) ?></a> (<?= nz_e(nz_money_short($top[0]['turnover'] !== null ? (float)$top[0]['turnover'] : null)) ?>).
<?php endif; ?>
        </p>
        <div class="nz-stats">
            <span class="nz-chip">Uzņēmumi: <strong><?= nz_n0($cnt) ?></strong></span>
            <span class="nz-chip">Apgrozījums: <strong><?= nz_e(nz_money_short((float)($agg['t'] ?? 0))) ?></strong></span>
            <span class="nz-chip">Peļņa kopā: <strong><?= nz_e(nz_money_short((float)($agg['p'] ?? 0))) ?></strong></span>
            <span class="nz-chip">Darbinieki: <strong><?= nz_n0($agg['e'] ?? null) ?></strong></span>
<?php if ($avg_net !== null): ?>
            <span class="nz-chip">Vid. neto alga: <strong>~<?= nz_n0($avg_net) ?> EUR/mēn</strong></span>
<?php endif; ?>
        </div>
        <p class="nz-note">
            Dati: VID/UR atvērtie dati (katalogs atjaunots <?= nz_e($data_updated) ?>). Izmaiņu % — pret iepriekšējo periodu
            (gads vai ceturksnis atkarībā no datu pieejamības). Interaktīvā analītika:
            <a href="/nozare.php">nozaru katalogs</a>.
        </p>
    </div>

<?php if (!empty($top)): ?>
    <div class="nz-panel">
        <h2 class="nz-h2">TOP <?= count($top) ?> uzņēmumi pēc apgrozījuma</h2>
        <div class="nz-table-wrap">
            <table class="nz-table">
                <thead>
                    <tr>
                        <th>Pozīcija</th><th>Uzņēmums</th><th>Vieta</th>
                        <th class="num">Apgrozījums, EUR</th><th class="num">Izmaiņa</th>
                        <th class="num">Peļņa, EUR</th><th class="num">Darbinieki</th>
                        <th class="num">~Neto alga, EUR/mēn</th>
                    </tr>
                </thead>
                <tbody>
<?php $nz_any_hidden = false; foreach ($top as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><a href="/<?= nz_e($r['regcode']) ?>"><?= nz_e($r['name']) ?></a></td>
                        <td><?= nz_e($r['location'] ?? '') ?></td>
                        <td class="num"><?= nz_n0($r['turnover']) ?></td>
                        <td class="num <?= ($r['turnover_change'] ?? 0) >= 0 ? 'nz-pos' : 'nz-neg' ?>"><?= nz_e(nz_pct($r['turnover_change'] !== null ? (float)$r['turnover_change'] : null)) ?></td>
                        <td class="num <?= ($r['profit'] ?? 0) >= 0 ? 'nz-pos' : 'nz-neg' ?>"><?= nz_n0($r['profit']) ?></td>
                        <td class="num"><?= nz_n0($r['employees']) ?></td>
<?php // '***' = alga slēpta privātuma dēļ (1-2 darbinieki); '—' = datu nav (0/nav darbinieku vai nav VID datu).
      $nz_sal_hidden = ($r['employees'] !== null && (int)$r['employees'] > 0 && (int)$r['employees'] < 3);
      if ($nz_sal_hidden) $nz_any_hidden = true; ?>
                        <td class="num"><?= $nz_sal_hidden ? '***' : nz_n0($r['avg_net_salary']) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="nz-note">Apgrozījums un peļņa — no jaunākajiem pieejamajiem VID/UR datiem katram uzņēmumam; alga aprēķināta no VSAOI iemaksām. Klikšķis uz nosaukuma — pilna uzņēmuma lapa ar gada pārskatiem un koeficientiem.<?= $nz_any_hidden ? ' *** — uzņēmumiem ar mazāk nekā 3 darbiniekiem algas aprēķins tiek slēpts privātuma aizsardzībai.' : '' ?></p>
    </div>
<?php endif; ?>

<?php if (!empty($children)): ?>
    <div class="nz-panel">
        <h2 class="nz-h2">Apakšnozares (<?= count($children) ?>)</h2>
        <ul class="nz-children">
<?php foreach ($children as $ch): $cs = $child_stats[$ch['code']] ?? ['c' => 0, 't' => 0.0]; ?>
            <li>
                <a href="/nozare/<?= nz_e($ch['code']) ?>"><?= nz_e($ch['code']) ?> — <?= nz_e($ch['name']) ?></a>
                <div class="meta"><?= nz_n0($cs['c']) ?> uzņēmumi · <?= nz_e(nz_money_short($cs['t'])) ?></div>
            </li>
<?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
</main>
<?php $footerRich = 'nozare'; include __DIR__ . '/registrs/footer/footer.php'; ?>
</body>
</html>
