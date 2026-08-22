<?php
/**
 * ipasnieks.php — publiskā īpašnieka profils: /ipasnieks/{slug}
 * (piem., /ipasnieks/latvijas-republika, /ipasnieks/liepajas-valstspilsetas-pasvaldiba).
 *
 * KĀPĒC ŠĀDA LAPA VISPĀR VAJADZĪGA: valsts un pašvaldības UR datos ir ierakstītas
 * TIKAI ar nosaukumu — entity_type=FOREIGN_ENTITY un TUKŠS reģistrācijas numurs.
 * Tāpēc tām nevar būt parastā /{regnr} lapa, un uzņēmuma lapā īpašnieks bija
 * strupceļš bez saites. Šī lapa ir tas trūkstošais gals: viens publiskais
 * īpašnieks → visi tā uzņēmumi ar finanšu rādītājiem.
 *
 * ROBEŽAS, kas lapā arī pateiktas lasītājam:
 *   - atlase ir pēc NOSAUKUMA (numura datos nav), tāpēc iestādes nosaukuma
 *     variācijas ("Veselības ministrija" / "Latvijas Republikas Veselības
 *     ministrija") ir atsevišķi ieraksti;
 *   - UR rāda TIEŠO līdzdalību; netiešā (caur meitas uzņēmumiem) šeit neparādās.
 *
 * GDPR: skar tikai juridiskas personas un publiskas iestādes — fizisko personu
 * dati šajā lapā nenonāk pēc definīcijas (entity_type filtrs).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('ipasnieks');

ini_set('display_errors', '0');
require_once __DIR__ . '/registrs/lib/db.php';
require_once __DIR__ . '/registrs/lib/formatters.php';
require_once __DIR__ . '/lib/public_owner.php';

function ip_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ip_n0($v): string { return $v === null || $v === '' ? '—' : number_format((float)$v, 0, ',', ' '); }
function ip_money($v): string {
    if ($v === null || $v === '') return '—';
    $n = (float)$v;
    if (abs($n) >= 1e9) return number_format($n / 1e9, 2, ',', ' ') . ' mljrd. €';
    if (abs($n) >= 1e6) return number_format($n / 1e6, 1, ',', ' ') . ' milj. €';
    return number_format($n, 0, ',', ' ') . ' €';
}


/** Visi publiskie īpašnieki (nosaukums => uzņēmumu skaits). Kešojas pieprasījumā. */
function ip_visi_ipasnieki(PDO $db): array {
    $rows = $db->query("
        SELECT name, COUNT(DISTINCT reg) c FROM (
            SELECT name, at_legal_entity_registration_number reg, legal_entity_registration_number r
              FROM members WHERE entity_type IN ('FOREIGN_ENTITY','LEGAL_ENTITY')
            UNION ALL
            SELECT name, at_legal_entity_registration_number, legal_entity_registration_number
              FROM stockholders WHERE entity_type IN ('FOREIGN_ENTITY','LEGAL_ENTITY')
        ) WHERE IFNULL(r,'') = '' AND IFNULL(name,'') <> ''
        GROUP BY name")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $veids = reg_public_owner_type((string)$r['name']);
        if ($veids === '') continue;
        $out[] = ['name' => (string)$r['name'], 'slug' => reg_public_owner_slug((string)$r['name']),
                  'veids' => $veids, 'count' => (int)$r['c']];
    }
    usort($out, static fn($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']));
    return $out;
}

$slug = trim((string)($_GET['slug'] ?? ''), '/');
$db = null;
try { $db = get_ur_db(); } catch (Throwable $e) {}
if ($db === null) { http_response_code(503); exit('Datubāze nav pieejama.'); }

$ipasnieki = ip_visi_ipasnieki($db);

// --- Saraksta lapa /ipasnieks/ ------------------------------------------------
$owner = null;
if ($slug !== '') {
    foreach ($ipasnieki as $o) { if ($o['slug'] === $slug) { $owner = $o; break; } }
    if ($owner === null) {
        http_response_code(404);
        $f = __DIR__ . '/404.php';
        if (is_file($f)) { include $f; exit; }
        exit('Nav atrasts.');
    }
}

$base = 'https://saraksts.lv';
$companies = [];
$tot = ['t' => 0.0, 'p' => 0.0, 'e' => 0];

if ($owner !== null) {
    // Uzņēmumi + jaunākie finanšu rādītāji vienā piegājienā.
    $st = $db->prepare("
        SELECT DISTINCT at_legal_entity_registration_number reg FROM (
            SELECT name, at_legal_entity_registration_number, legal_entity_registration_number r
              FROM members WHERE entity_type IN ('FOREIGN_ENTITY','LEGAL_ENTITY')
            UNION ALL
            SELECT name, at_legal_entity_registration_number, legal_entity_registration_number
              FROM stockholders WHERE entity_type IN ('FOREIGN_ENTITY','LEGAL_ENTITY')
        ) WHERE IFNULL(r,'') = '' AND name = ?");
    $st->execute([$owner['name']]);
    $regs = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'reg');
    $regs = array_values(array_filter($regs, static fn($x) => preg_match('/^\d{11}$/', (string)$x) === 1));

    foreach (array_chunk($regs, 300) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $q = $db->prepare("SELECT regcode, name, closed FROM register WHERE regcode IN ($ph)");
        $q->execute($chunk);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $companies[(string)$row['regcode']] = [
                'reg' => (string)$row['regcode'],
                'name' => (string)$row['name'],
                'closed' => in_array((string)($row['closed'] ?? ''), ['L', 'R'], true),
                'year' => null, 'turnover' => null, 'profit' => null, 'employees' => null,
            ];
        }
        // Finanšu rādītāji. DIVI slazdi, kas abi reiz jau ir kodušies šajā projektā:
        //  1) summas ir REIZINĀMAS ar rounded_to_nearest faktoru (THOUSANDS/MILLIONS) —
        //     Latvenergo 949 824 nozīmē 949,8 milj. €, ne 950 tūkst.;
        //  2) vienam gadam ir DIVAS rindas — UGP (individuālais) un UKGP (konsolidētais).
        //     Ņemam individuālo, tāpat kā pārējā vietnē; konsolidēto lietojam tikai tad,
        //     ja individuālā nav (citādi mātes uzņēmuma skaitļos ieskaitītos meitas).
        $q2 = $db->prepare("
            SELECT f.legal_entity_registration_number reg, f.year, f.source_type, f.rounded_to_nearest rnd,
                   i.net_turnover t, i.net_income p, f.employees e
            FROM financial_statements f
            LEFT JOIN income_statements i ON i.statement_id = f.id
            WHERE f.legal_entity_registration_number IN ($ph)
            ORDER BY f.year ASC");
        $q2->execute($chunk);
        $best = [];   // reg => [gads, prioritāte]
        foreach ($q2->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rg = (string)$row['reg'];
            if (!isset($companies[$rg])) continue;
            $year = (int)$row['year'];
            $prio = strtoupper((string)$row['source_type']) === 'UGP' ? 2 : 1;
            if (isset($best[$rg]) && ($year < $best[$rg][0] || ($year === $best[$rg][0] && $prio <= $best[$rg][1]))) continue;
            $rnd = strtoupper((string)($row['rnd'] ?? 'ONES'));
            $factor = str_contains($rnd, 'THOUS') ? 1000 : (str_contains($rnd, 'MILL') ? 1000000 : 1);
            $best[$rg] = [$year, $prio];
            $companies[$rg]['year'] = $year;
            $companies[$rg]['turnover'] = $row['t'] !== null ? (float)$row['t'] * $factor : null;
            $companies[$rg]['profit'] = $row['p'] !== null ? (float)$row['p'] * $factor : null;
            $companies[$rg]['employees'] = $row['e'] !== null ? (int)$row['e'] : null;
        }
    }
    $companies = array_values($companies);
    usort($companies, static fn($a, $b) => ($b['turnover'] ?? -1) <=> ($a['turnover'] ?? -1));
    foreach ($companies as $c) {
        $tot['t'] += (float)($c['turnover'] ?? 0);
        $tot['p'] += (float)($c['profit'] ?? 0);
        $tot['e'] += (int)($c['employees'] ?? 0);
    }
}

// --- SEO ----------------------------------------------------------------------
if ($owner !== null) {
    $pageTitle = $owner['name'] . ' — piederošie uzņēmumi un to finanšu rādītāji';
    $pageDesc = $owner['name'] . ' ir īpašnieks ' . count($companies) . ' uzņēmumos: kopējais apgrozījums '
        . ip_money($tot['t']) . ', ' . ip_n0($tot['e']) . ' darbinieki. Saraksts ar apgrozījumu, peļņu un darbinieku skaitu.';
    $canonicalUrl = $base . '/ipasnieks/' . $owner['slug'];
} else {
    $pageTitle = 'Valsts un pašvaldību uzņēmumi — publiskie īpašnieki';
    $pageDesc = 'Latvijas valstij un pašvaldībām piederošie uzņēmumi pēc īpašnieka: ' . count($ipasnieki)
        . ' publiskie īpašnieki ar uzņēmumu sarakstiem un finanšu rādītājiem.';
    $canonicalUrl = $base . '/ipasnieks/';
}

$crumbs = [['Sākums', $base . '/'], ['Publiskie īpašnieki', $base . '/ipasnieks/']];
if ($owner !== null) $crumbs[] = [$owner['name'], $canonicalUrl];
$bc = [];
foreach ($crumbs as $i => [$n, $u]) $bc[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $n, 'item' => $u];
$pageJsonLd = ['@context' => 'https://schema.org', '@graph' => [
    ['@type' => 'BreadcrumbList', 'itemListElement' => $bc],
    ['@type' => 'CollectionPage', 'name' => $pageTitle, 'description' => $pageDesc, 'url' => $canonicalUrl],
]];

$data_updated = date('Y-m-d', @filemtime(reg_ur_db_path()) ?: time());
header('Cache-Control: public, max-age=21600');
?>
<!DOCTYPE html>
<html lang="lv">
<?php include __DIR__ . '/registrs/head/head.php'; ?>
<body>
<?php include __DIR__ . '/registrs/header.php'; ?>
<main class="ip-main">
    <nav class="ip-crumbs" aria-label="Atrašanās vieta">
<?php foreach ($crumbs as $i => [$n, $u]): ?>
<?php if ($i > 0): ?><span class="sep">›</span><?php endif; ?>
<?php if ($i < count($crumbs) - 1): ?><a href="<?= ip_e($u) ?>"><?= ip_e($n) ?></a><?php else: ?><strong><?= ip_e($n) ?></strong><?php endif; ?>
<?php endforeach; ?>
    </nav>

<?php if ($owner === null): ?>
    <div class="ip-panel">
        <h1>Valsts un pašvaldību uzņēmumi</h1>
        <p class="ip-lede">Uzņēmumu reģistra datos valsts un pašvaldības ir ierakstītas kā īpašnieki
            <strong>bez reģistrācijas numura</strong> — tikai ar nosaukumu. Šeit tie ir apkopoti:
            <strong><?= ip_n0(count($ipasnieki)) ?> publiskie īpašnieki</strong> ar tiem piederošajiem uzņēmumiem.</p>
        <table class="ip-table">
            <thead><tr><th>Īpašnieks</th><th>Veids</th><th class="num">Uzņēmumi</th></tr></thead>
            <tbody>
<?php foreach ($ipasnieki as $o): ?>
                <tr>
                    <td><a href="/ipasnieks/<?= ip_e($o['slug']) ?>"><?= ip_e($o['name']) ?></a></td>
                    <td><?= ip_e($o['veids']) ?></td>
                    <td class="num"><?= ip_n0($o['count']) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="ip-panel">
        <h1><?= ip_e($owner['name']) ?></h1>
        <p class="ip-lede">
            <?= ip_e($owner['veids'] === 'valsts' ? 'Valsts īpašums' : 'Pašvaldības īpašums') ?>:
            <strong><?= ip_n0(count($companies)) ?> uzņēmumi</strong>, kopējais apgrozījums
            <strong><?= ip_e(ip_money($tot['t'])) ?></strong>, peļņa
            <strong><?= ip_e(ip_money($tot['p'])) ?></strong>,
            <strong><?= ip_n0($tot['e']) ?> darbinieki</strong> (pēc jaunākajiem gada pārskatiem).
        </p>
        <div class="table-responsive-wrapper">
        <table class="ip-table">
            <thead><tr><th>Uzņēmums</th><th class="num">Gads</th><th class="num">Apgrozījums</th><th class="num">Peļņa</th><th class="num">Darbinieki</th></tr></thead>
            <tbody>
<?php foreach ($companies as $c): ?>
                <tr>
                    <td><a href="/<?= ip_e($c['reg']) ?>"><?= ip_e($c['name']) ?></a><?= $c['closed'] ? ' <span class="ip-muted">(likvidēts)</span>' : '' ?></td>
                    <td class="num"><?= $c['year'] !== null ? ip_e($c['year']) : '—' ?></td>
                    <td class="num"><?= ip_e(ip_money($c['turnover'])) ?></td>
                    <td class="num"><?= ip_e(ip_money($c['profit'])) ?></td>
                    <td class="num"><?= ip_n0($c['employees']) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="ip-note">
            <strong>Kā lasīt:</strong> saraksts veidots pēc īpašnieka NOSAUKUMA, jo Uzņēmumu reģistra datos
            valstij un pašvaldībām reģistrācijas numurs nav norādīts — tāpēc iestādes nosaukuma variācijas
            (piem., "Veselības ministrija" un "Latvijas Republikas Veselības ministrija") veido atsevišķus
            ierakstus. Rādīta <em>tiešā</em> līdzdalība; netiešā (caur meitas uzņēmumiem) šeit neparādās —
            to redz konkrētā uzņēmuma lapā sadaļā "Saistītie uzņēmumi un izmaiņas".
            Dati: <a href="https://data.gov.lv/dati/lv/dataset/uz-nemumu-registrs" rel="noopener">Uzņēmumu reģistra atvērtie dati</a>,
            kopija atjaunota <?= ip_e($data_updated) ?>.
        </p>
    </div>
<?php endif; ?>
</main>
<?php $footerRich = 'registrs'; include __DIR__ . '/registrs/footer/footer.php'; ?>
<style>
.ip-main{max-width:1254px;margin:20px auto;padding:0 15px}
.ip-crumbs{font-size:13px;color:#667;margin-bottom:10px}
.ip-crumbs a{color:#2c4a6b}
.ip-crumbs .sep{margin:0 6px;color:#aab}
.ip-panel{background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.06);padding:20px}
.ip-panel h1{margin:0 0 10px;font-size:1.6rem;color:#2c3e50}
.ip-lede{margin:0 0 16px;font-size:15px;line-height:1.6;color:#34495e}
.ip-table{width:100%;border-collapse:collapse;font-size:14px}
.ip-table th{text-align:left;background:#f8fafc;color:#7f8c8d;font-weight:600;padding:8px 10px;border-bottom:2px solid #e5e7eb;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.ip-table td{padding:7px 10px;border-bottom:1px solid #eef1f4}
.ip-table th.num,.ip-table td.num{text-align:right;white-space:nowrap}
.ip-table tbody tr:hover{background:#f7fbff}
.ip-muted{color:#8a94a6;font-size:12.5px}
.ip-note{margin:14px 0 0;font-size:12.5px;color:#7a8794;line-height:1.55}
</style>
</body>
</html>
