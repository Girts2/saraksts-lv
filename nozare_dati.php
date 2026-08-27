<?php
/**
 * nozare_dati.php — konkurentu salīdzinājuma tabulas dati vienam NACE kodam.
 *
 * KĀPĒC (audits 2026-08-26): "Konkurentu salīdzinājums" lielām nozarēm iegulda
 * lapā ~482 KB JSON (NACE 6820 = 5 921 rinda) — 45 615 lapām blobs ir ≥ ~80 KB,
 * un tas pats saturs tika sūtīts ar KATRU uzņēmuma lapu. Šeit tas ir VIENS
 * kešojams JSON uz NACE kodu; panelis (view/partials/test_panel.php) to ielādē
 * slinki tikai nozarēm ar >300 uzņēmumiem. Rindas būvē tas pats
 * lib/nozares_tabula.php, ko lieto panelis — skaitļi nekad neatšķiras.
 *
 * TIKAI uzņēmumu līmeņa agregāti (tas pats saturs, kas lapā) — personas datu nav.
 */
declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/lib/applog.php';
applog_boot('registrs');

require_once __DIR__ . '/registrs/lib/db.php';
require_once __DIR__ . '/registrs/lib/config.php';
require_once __DIR__ . '/registrs/lib/nozares_tabula.php';

$nace_q = $_GET['nace'] ?? '';
$nace = is_string($nace_q) ? (preg_replace('/\D/', '', $nace_q) ?? '') : '';
if (!preg_match('/^\d{4}$/', $nace) || $nace === '0000') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"nace parametram jābūt 4 cipariem"}';
    return;
}

try {
    $conn = get_ur_db();
    $nz = reg_nozares_tabula($conn, $nace);
} catch (Throwable $e) {
    $nz = null;
}
if ($nz === null) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"nozares datu nav"}';
    return;
}

// Kešošana: dati mainās tikai nakts būvē (02:00), tāpēc 12 h publisks kešs un
// ETag no ģenerēšanas zīmoga — atkārtots apmeklējums nozarē nesūta ne baitu.
$etag = '"nz-' . md5($nace . '|' . $nz['generated'] . '|' . $nz['total']) . '"';
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=43200');
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    return;
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'nace' => $nace,
    'total' => $nz['total'],
    'generated' => $nz['generated'],
    'rows' => $nz['rows_js'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
