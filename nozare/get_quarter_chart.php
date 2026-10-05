<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/applog.php';
applog_boot('nozare');
// Nozares ceturkšņu grafiks. Dati ir jau saskaitīti nace_quarter (būvē),
// tāpēc te ir viens SUM pēc NACE prefiksa — tas pats filtrs, kas uzņēmumu sarakstam.

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

function nq_fail(int $code, string $msg): void {
    http_response_code($code);
    header('Cache-Control: no-store');
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function nq_quarter_title(int $qkey): string {
    $year = intdiv($qkey, 10);
    $q = $qkey % 10;
    return $year . '. gada ' . $q . '. ceturksnis';
}

function nq_quarter_label(int $qkey): string {
    return intdiv($qkey, 10) . ' Q' . ($qkey % 10);
}

try {
    $db_file = __DIR__ . '/katalogs.sqlite';
    if (!is_file($db_file)) {
        nq_fail(500, 'Datubāzes fails nav atrasts.');
    }

    $kods = preg_replace('/[^A-Z0-9\.]/i', '', (string)($_GET['kods'] ?? ''));
    $kods = strtoupper((string)$kods);
    if ($kods === '') {
        nq_fail(400, 'Nav norādīts NACE kods.');
    }

    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $has = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'nace_quarter'")->fetchColumn();
    if (!$has) {
        echo json_encode([
            'ready' => false,
            'quarters' => [],
            'note' => '',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($kods === 'UNDEFINED') {
        $where = "nace_np = 'UNDEFINED'";
        $params = [];
    } else {
        $where = 'nace_np LIKE ?';
        $params = [str_replace('.', '', $kods) . '%'];
    }

    $sql = "SELECT qkey,
                   SUM(turnover_sum) AS ts, SUM(turnover_n) AS tn,
                   SUM(gross_pay) AS gp, SUM(gross_emp) AS ge,
                   SUM(net_pay) AS npay, SUM(net_emp) AS ne,
                   SUM(employees) AS emp
            FROM nace_quarter
            WHERE $where
            GROUP BY qkey
            ORDER BY qkey";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $quarters = [];
    while ($r = $st->fetch()) {
        $qkey = (int)$r['qkey'];
        $tn = (int)$r['tn'];
        $ge = (float)$r['ge'];
        $ne = (float)$r['ne'];
        $quarters[] = [
            'key' => $qkey,
            'label' => nq_quarter_label($qkey),
            'title' => nq_quarter_title($qkey),
            'turnover' => $tn > 0 ? (int)round((float)$r['ts'] / $tn) : null,
            'turnover_n' => $tn,
            'gross' => $ge > 0 ? (int)round((float)$r['gp'] / $ge) : null,
            'net' => $ne > 0 ? (int)round((float)$r['npay'] / $ne) : null,
            'employees' => (int)round((float)$r['emp']),
        ];
    }

    echo json_encode([
        'ready' => true,
        'quarters' => $quarters,
        'note' => 'Vidējais apgrozījums ir aptuvens ceturkšņa rādītājs: PVN iemaksa (tūkst. EUR) × 1000 ÷ 21 %. '
            . 'Iekļauti tikai uzņēmumi, kas tajā ceturksnī PVN iemaksāja — iemaksa ir izejas PVN mīnus priekšnodoklis, '
            . 'tāpēc skaitlis tuvina ar PVN apliekamo apgrozījumu, nevis gada pārskata neto apgrozījumu. '
            . 'Bruto alga — tā pati formula kā uzņēmuma lapā: (VSAOI × 1000 ÷ 0,3409) ÷ darbinieki ÷ 3 mēneši. '
            . 'Neto — no bruto ar attiecīgā gada IIN. Abas algas svērtas ar darbinieku skaitu un tikai uzņēmumiem ar vismaz 3 darbiniekiem. '
            . 'Darbinieku skaits — nozares kopsumma.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_quarter_chart.php: ' . $e->getMessage());
    nq_fail(500, 'Servera kļūda datu atlasē.');
}
