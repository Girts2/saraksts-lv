<?php
require_once __DIR__ . '/../../../lib/applog.php';
applog_boot('registrs');   // kopīgais notikumu žurnāls (lib/applog.php)
// kods/templates/php_backend/nace_stats.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Atļaujam piekļuvi, ja vajag

$db_file = __DIR__ . '/../search/nace_stats.sqlite';
// Piezīme: Mēs kopēsim DB uz assets/search/nace_stats.sqlite, 
// bet šis fails būs assets/search/nace_stats.php, tāpēc ceļš ir vienkāršs.
// Precizēsim ceļu: file_ops.py to ieliks tajā pašā mapē.
$db_file_local = __DIR__ . '/nace_stats.sqlite';

if (!isset($_GET['code'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing NACE code']);
    exit;
}

$code = preg_replace('/[^0-9]/', '', $_GET['code']); // Tikai cipari

if (!file_exists($db_file_local)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database not found']);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $db_file_local);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT json_data FROM nace_stats WHERE code = :code LIMIT 1");
    $stmt->bindValue(':code', $code, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Dati jau ir JSON formātā DB, tāpēc vienkārši izvadām
        echo $result['json_data'];
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'No data for this NACE code']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>