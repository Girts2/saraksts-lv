<?php
require_once __DIR__ . '/../../../lib/applog.php';
applog_boot('registrs');   // kopīgais notikumu žurnāls (lib/applog.php)
// projects/python_generator/templates/php_backend/search.php

header('Content-Type: application/json; charset=utf-8');

$db_file = __DIR__ . '/companies.sqlite';
$results = [];

if (!isset($_GET['term'])) {
    echo json_encode($results);
    exit;
}

$term = trim($_GET['term']);

// Pārbaudām, vai meklēšanas termiņš nav tukšs, bet noņemam minimālā garuma ierobežojumu šeit,
// jo to kontrolē JavaScript puse.
if (!empty($term)) {
    if (!file_exists($db_file)) {
        http_response_code(500);
        echo json_encode(['error' => 'Meklēšanas datubāze nav atrasta.']);
        exit;
    }

    try {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $normalized_search_term = mb_strtolower($term, 'UTF-8');
        $cleaned_normalized_search_term = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized_search_term);
        $cleaned_normalized_search_term = preg_replace('/\s+/', ' ', $cleaned_normalized_search_term);

        $words_for_fts = preg_split('/\s+/', $cleaned_normalized_search_term, -1, PREG_SPLIT_NO_EMPTY);
        $fts_query_parts = array_map(function($word) {
            return $word . '*';
        }, $words_for_fts);
        $fts_query = implode(' ', $fts_query_parts);
        $search_term_word_count = count($words_for_fts);

        // Ja pēc tīrīšanas nepaliek neviena meklējama zīme (piem. termiņš satur tikai
        // pieturzīmes), tukšs `MATCH ''` mestu FTS5 kļūdu -> atgriežam tukšu sarakstu.
        if ($fts_query === '') {
            echo json_encode([]);
            exit;
        }

        // LABOJUMS: Mainām LIMIT no 8 uz 25
        $stmt = $pdo->prepare("
            SELECT
                c.regcode,
                c.original_name
            FROM
                companies_fts fts
            JOIN
                companies c ON fts.rowid = c.rowid
            WHERE
                companies_fts MATCH :fts_query
            ORDER BY
                CASE WHEN TRIM(SUBSTR(c.search_helper, LENGTH(c.regcode) + 2)) = :exact_term THEN 0 ELSE 1 END ASC,
                CASE WHEN (LENGTH(TRIM(SUBSTR(c.search_helper, LENGTH(c.regcode) + 2))) - LENGTH(REPLACE(TRIM(SUBSTR(c.search_helper, LENGTH(c.regcode) + 2)), ' ', '')) + 1) = :search_term_word_count THEN 0 ELSE 1 END ASC,
                fts.rank,
                c.original_name ASC
            LIMIT 25
        ");

        $stmt->bindValue(':fts_query', $fts_query, PDO::PARAM_STR);
        $stmt->bindValue(':exact_term', $cleaned_normalized_search_term, PDO::PARAM_STR);
        $stmt->bindValue(':search_term_word_count', $search_term_word_count, PDO::PARAM_INT);

        $stmt->execute();
        $results = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Datubāzes vaicājuma kļūda.']);
        exit;
    }
}

echo json_encode($results);
?>