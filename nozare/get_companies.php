<?php
require_once __DIR__ . '/../lib/applog.php';
applog_boot('nozare');   // kopīgais notikumu žurnāls (lib/applog.php)
// Fails: get_companies.php
// VERSIJA 8: Servera puses kārtošana + lapošana; tas pats aktivitātes filtrs, kas nozare.php
// kartīšu skaitīšanai (kartītes cipars == tabulas rindu kopskaits).

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
// Dati mainās tikai build reizē (reizi diennaktī) — stunda kešā ir droša
header('Cache-Control: public, max-age=3600');

const PAGE_SIZE = 500;

// Kārtošanas atslēgas baltais saraksts (atslēga => kolonna)
const SORT_COLS = [
    'name' => 'name',
    'location' => 'location',
    'turnover' => 'turnover',
    'employees' => 'employees',
    'profit' => 'profit',
    'avg_net_salary' => 'avg_net_salary',
];

try {
    $db_file = __DIR__ . '/katalogs.sqlite';
    if (!file_exists($db_file)) {
        throw new Exception('Datubāzes fails nav atrasts.');
    }

    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $kods = $_GET['kods'] ?? '';
    if (empty($kods)) {
        throw new Exception('Nav norādīts NACE kods.');
    }
    $kods = preg_replace('/[^A-Z0-9\.]/i', '', $kods);

    // Kolonnu pieejamība: DB, kas būvēta pirms 2026-07-16, vēl nesatur name_sort/change_period —
    // līdz nākamajai būvei atkāpjamies uz veco uzvedību, nevis metam 500.
    $cols = $pdo->query('PRAGMA table_info(companies)')->fetchAll(PDO::FETCH_COLUMN, 1);
    // name_sort = būves laikā aprēķināta latviešu (CLDR 'lv' tuvinājuma) kārtošanas atslēga
    $nameCol = in_array('name_sort', $cols, true) ? 'name_sort' : 'name';
    $changePeriodSel = in_array('change_period', $cols, true) ? 'change_period' : 'NULL AS change_period';

    $sortCols = SORT_COLS;
    $sortCols['name'] = $nameCol;
    $sort = $sortCols[$_GET['sort'] ?? ''] ?? 'employees';
    $dir = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    // Tas pats filtrs, ko lieto nozare.php kartīšu agregācija
    $activity_filter = '(employees > 0 OR turnover > 0 OR profit != 0)';

    if ($kods === 'UNDEFINED') {
        $where = "nace_code_np = 'UNDEFINED' AND $activity_filter";
        $params = [];
    } else {
        $where = "nace_code_np LIKE ? AND $activity_filter";
        $params = [str_replace('.', '', $kods) . '%'];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // NULL vērtības vienmēr saraksta beigās, neatkarīgi no virziena; nosaukums kā stabils otrais kārtotājs
    $sql = "SELECT regcode, name, turnover, turnover_change, employees, employees_change,
                   profit, profit_change, avg_net_salary, salary_change,
                   financial_health_string, location, $changePeriodSel
            FROM companies
            WHERE $where
            ORDER BY ($sort IS NULL) ASC, $sort $dir, $nameCol ASC
            LIMIT " . PAGE_SIZE . " OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $companies = [];
    $num = fn($v) => $v === null ? null : (float)$v;
    while ($r = $stmt->fetch()) {
        $companies[] = [
            'regcode' => (string)$r['regcode'],
            'name' => (string)($r['name'] ?? ''),
            'turnover' => $num($r['turnover']),
            'turnover_change' => $num($r['turnover_change']),
            'employees' => $r['employees'] === null ? null : (int)$r['employees'],
            'employees_change' => $num($r['employees_change']),
            'profit' => $num($r['profit']),
            'profit_change' => $num($r['profit_change']),
            // Alga tikai >=3 darbiniekiem — mazākiem tā atklātu konkrētas personas algu (privātums).
            'avg_net_salary' => ($r['employees'] !== null && (int)$r['employees'] >= 3) ? $num($r['avg_net_salary']) : null,
            'salary_change' => ($r['employees'] !== null && (int)$r['employees'] >= 3) ? $num($r['salary_change']) : null,
            // '***' pazīme UI: dati slēpti privātuma dēļ (1-2 darb.), nevis "nav datu".
            'salary_hidden' => ($r['employees'] !== null && (int)$r['employees'] > 0 && (int)$r['employees'] < 3),
            'financial_health_string' => (string)($r['financial_health_string'] ?? ''),
            'location' => (string)($r['location'] ?? ''),
            'change_period' => $r['change_period'] === null ? null : (string)$r['change_period'],
        ];
    }

    echo json_encode([
        'total' => $total,
        'offset' => $offset,
        'limit' => PAGE_SIZE,
        'companies' => $companies,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('get_companies.php: ' . $e->getMessage());
    http_response_code(500);
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'Servera kļūda datu atlasē.']);
    exit;
}
