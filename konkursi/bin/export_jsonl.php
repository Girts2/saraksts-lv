<?php
/**
 * konkursi/bin/export_jsonl.php — JSONL eksports (arhīvs/pārnesamība).
 *
 * SQLite paliek galvenā glabātuve; šis ir NEOBLIGĀTS cilvēkam lasāms / pārnesams
 * momentuzņēmums. Straumē pa rindai (atmiņas efektīvi arī pie miljoniem rindu).
 *
 * Lietošana:
 *   php konkursi/bin/export_jsonl.php > versions.jsonl        # nemainīgais žurnāls (visas versijas)
 *   php konkursi/bin/export_jsonl.php --current > current.jsonl # tikai pašreizējais skats (1 rinda/id)
 *   php konkursi/bin/export_jsonl.php --out=arhivs.jsonl      # rakstīt failā, nevis stdout
 *   php konkursi/bin/export_jsonl.php --source=SIMAP          # tikai viena avota
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

set_time_limit(0);

$current = in_array('--current', $argv, true);
$source = null;
$out = null;
foreach ($argv as $a) {
    if (preg_match('/^--source=([A-Z]+)$/', $a, $m)) $source = $m[1];
    elseif (preg_match('/^--out=(.+)$/', $a, $m)) $out = $m[1];
}

$pdo = konkursi_db();
$table = $current ? 'notices' : 'notice_versions';
$order = $current ? 'id' : 'id, version_no';

$where = '';
$params = [];
if ($source !== null) { $where = ' WHERE source = ?'; $params[] = $source; }

$fh = $out !== null ? fopen($out, 'wb') : STDOUT;
if ($fh === false) { fwrite(STDERR, "Nevar atvērt: $out\n"); exit(1); }

// Straumēšana pa rowid partijām (nepatur visu atmiņā, neapdraud lasījuma kursoru).
$batch = 5000;
$lastRowid = 0;
$total = 0;
$sql = "SELECT rowid, * FROM $table" . ($where ? $where . ' AND rowid > ?' : ' WHERE rowid > ?')
     . " ORDER BY rowid LIMIT $batch";
$sel = $pdo->prepare($sql);

while (true) {
    $sel->execute(array_merge($params, [$lastRowid]));
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    $sel->closeCursor();
    if (!$rows) break;
    foreach ($rows as $r) {
        $lastRowid = (int)$r['rowid'];
        unset($r['rowid']);
        // JSON laukus (masīvi/objekti) izvērš atpakaļ, lai izvade ir īsts JSON, ne virkne virknē.
        foreach (['cpv_codes', 'lots', 'organizations', 'notice_contact'] as $j) {
            if (isset($r[$j]) && is_string($r[$j]) && $r[$j] !== '') {
                $dec = json_decode($r[$j], true);
                if ($dec !== null) $r[$j] = $dec;
            }
        }
        fwrite($fh, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $total++;
    }
}

if ($out !== null) {
    fclose($fh);
    fwrite(STDERR, "Eksportēts: $total rindas → $out (" . ($current ? 'pašreizējais skats' : 'versiju žurnāls') . ")\n");
}
