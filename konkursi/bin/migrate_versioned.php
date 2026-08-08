<?php
/**
 * konkursi/bin/migrate_versioned.php — VIENREIZĒJA migrācija uz versionēto modeli.
 *
 * Aizpilda NEMAINĪGO notice_versions žurnālu no esošās `notices` tabulas (katra
 * rinda → versija 1) un iesēj source_state ūdenszīmes (max publication_date/avots).
 * `notices` paliek neskarta — tā turpina kalpot kā pašreizējais skats.
 *
 * Idempotents (INSERT OR IGNORE pēc (id, version_no)) — droši palaist atkārtoti.
 *
 * Lietošana:
 *   php konkursi/bin/migrate_versioned.php          # sausā palaišana (tikai rāda plānu)
 *   php konkursi/bin/migrate_versioned.php --apply   # tiešām aizpilda
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/store.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$apply = in_array('--apply', $argv, true);
$pdo = konkursi_db();   // izveido notice_versions + source_state, ja vēl nav

$total   = (int)$pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();
$already = (int)$pdo->query('SELECT COUNT(*) FROM notice_versions')->fetchColumn();
$todo    = (int)$pdo->query(
    'SELECT COUNT(*) FROM notices n WHERE NOT EXISTS
        (SELECT 1 FROM notice_versions v WHERE v.id = n.id AND v.version_no = 1)'
)->fetchColumn();

echo "notices kopā:        $total\n";
echo "notice_versions jau: $already\n";
echo "aizpildāmi (v1):     $todo\n";
echo $apply ? "REŽĪMS: --apply (raksta)\n\n" : "REŽĪMS: sausā palaišana (neko neraksta; --apply lai izpildītu)\n\n";

echo "Ūdenszīmes, ko iesēt (max publication_date / avots):\n";
foreach ($pdo->query("SELECT source, COUNT(*) c, MAX(publication_date) w
                      FROM notices GROUP BY source ORDER BY source") as $r) {
    printf("  %-10s %7d rindas  →  %s\n", $r['source'], (int)$r['c'], $r['w'] ?? '(nav datuma)');
}

if (!$apply) {
    echo "\nSausā palaišana pabeigta. Nekas netika rakstīts.\n";
    exit(0);
}

// ── Aizpilde pa rowid partijām (atmiņas dēļ; raksts citā tabulā nekā lasījums) ──
$cols  = KS_NOTICE_COLS;
$vcols = array_merge($cols, ['version_no', 'observed_at', 'content_hash']);
$insSql = 'INSERT OR IGNORE INTO notice_versions (' . implode(',', $vcols) . ')'
        . ' VALUES (:' . implode(',:', $vcols) . ')';
$ins = $pdo->prepare($insSql);

$selCols = 'rowid, first_seen, ' . implode(',', $cols);
$batch = 5000;
$lastRowid = 0;
$seen = 0; $inserted = 0;
$now = date('c');

while (true) {
    $sel = $pdo->prepare("SELECT $selCols FROM notices WHERE rowid > ? ORDER BY rowid LIMIT $batch");
    $sel->execute([$lastRowid]);
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    $sel->closeCursor();
    if (!$rows) break;

    $pdo->beginTransaction();
    foreach ($rows as $r) {
        $lastRowid = (int)$r['rowid'];
        $seen++;
        $n = [];
        foreach ($cols as $c) $n[$c] = $r[$c] ?? null;

        $row = $n;
        $row['version_no']   = 1;
        // observed_at: kad MĒS pirmoreiz redzējām — first_seen, citādi publikācijas
        // datums, citādi migrācijas brīdis.
        $row['observed_at']  = $r['first_seen'] ?: ($n['publication_date'] ?: $now);
        $row['content_hash'] = ks_content_hash($n);
        $ins->execute($row);
        $inserted += $ins->rowCount();   // 0, ja jau bija (INSERT OR IGNORE)
    }
    $pdo->commit();
    echo "  … apstrādāti $seen / $total (ievietoti $inserted)\n";
}

// ── Ūdenszīmju iesēšana ──
$wm = 0;
foreach ($pdo->query("SELECT source, MAX(publication_date) w FROM notices GROUP BY source") as $r) {
    ks_set_source_state($pdo, (string)$r['source'], $r['w'] ?: null, null, null);
    $wm++;
}

echo "\nPabeigts.\n";
echo "  notice_versions ievietoti: $inserted\n";
echo "  ūdenszīmes iesētas:        $wm avotiem\n";

$vTotal = (int)$pdo->query('SELECT COUNT(*) FROM notice_versions')->fetchColumn();
$vIds   = (int)$pdo->query('SELECT COUNT(DISTINCT id) FROM notice_versions')->fetchColumn();
echo "  notice_versions kopā:      $vTotal ($vIds unikāli id)\n";
echo "  notices kopā:              $total\n";
echo ($vIds === $total ? "  ✓ Integritāte: viens-pret-vienu ar notices.\n"
                       : "  ⚠ Neatbilst: $vIds unikāli vs $total notices — pārbaudi.\n");
