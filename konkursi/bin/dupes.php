<?php
/**
 * konkursi/bin/dupes.php — dublējošos iepirkumu pārskats un tīrīšana.
 *
 * Lietošana:
 *   php konkursi/bin/dupes.php                # tikai parāda (nedzēš neko)
 *   php konkursi/bin/dupes.php --show=20      # parāda 20 grupu saturu
 *   php konkursi/bin/dupes.php --source=TED   # tikai viena avota grupas
 *   php konkursi/bin/dupes.php --apply        # tiešām izdzēš liekās rindas
 *
 * Sinhronizācija dublikātus novāc pati (ks_dedupe_notices, sk. konkursi_sync),
 * tāpēc šis rīks ir pārbaudei: apskatīt, KAS tiktu dzēsts un kāpēc, pirms
 * uzticēties automātiskajam solim. --apply noder arī tīrīšanai starp palaišanām.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/sync_engine.php';

$opts   = getopt('', ['show::', 'source::', 'apply']);
$show   = isset($opts['show']) ? max(1, (int)$opts['show']) : 0;
$source = isset($opts['source']) ? strtoupper((string)$opts['source']) : '';
$apply  = isset($opts['apply']);

$pdo = konkursi_db();
$key = ks_dupe_key_sql('n');

// ── Kopsavilkums pa avotiem ──────────────────────────────────────────────────
$skip = "'" . implode("','", KS_DEDUPE_SKIP_SOURCES) . "'";
$sql = "SELECT n.source, COUNT(*) - COUNT(DISTINCT k) AS liekas, COUNT(DISTINCT k) AS grupas
        FROM (SELECT n.*, $key AS k FROM notices n
              WHERE n.category = 'iepirkumi'
                AND n.contract_folder_id IS NOT NULL AND n.contract_folder_id <> ''
                AND n.source NOT IN ($skip)) n
        GROUP BY n.source HAVING liekas > 0 ORDER BY liekas DESC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

printf("%-12s %10s %10s\n", 'AVOTS', 'GRUPAS', 'LIEKAS');
printf("%s\n", str_repeat('-', 34));
$tot = 0;
foreach ($rows as $r) {
    printf("%-12s %10d %10d\n", $r['source'], (int)$r['grupas'], (int)$r['liekas']);
    $tot += (int)$r['liekas'];
}
printf("%s\n%-12s %10s %10d\n\n", str_repeat('-', 34), 'KOPĀ', '', $tot);

// ── Grupu saturs ─────────────────────────────────────────────────────────────
if ($show > 0) {
    $where = "WHERE n.category = 'iepirkumi' AND n.contract_folder_id IS NOT NULL AND n.contract_folder_id <> ''";
    $args  = [];
    if ($source !== '') { $where .= ' AND n.source = ?'; $args[] = $source; }

    // Divi soļi ar nolūku. Grupēt PĒC rēķinātas atslēgas nozīmē pilnu 160k rindu
    // skenēšanu un šķirošanu (indekss uz izteiksmi neattiecas). Tāpēc vispirms
    // paņem tikai dažus dublikātu ID, un tad izvelk to grupas pēc
    // contract_folder_id — tas jau ir indeksēts (idx_notices_cat_folder).
    $st = $pdo->prepare(
        "SELECT contract_folder_id FROM (
             SELECT n.contract_folder_id, ROW_NUMBER() OVER (
                        PARTITION BY $key ORDER BY n.id) AS rn
             FROM notices n $where
         ) WHERE rn = 2 LIMIT $show"
    );
    $st->execute($args);
    $folders = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!$folders) { echo "Dublikātu nav.\n\n"; return; }

    $in  = implode(',', array_fill(0, count($folders), '?'));
    $st2 = $pdo->prepare(
        "SELECT $key AS k, n.id, n.source, n.publication_number, n.publication_date,
                n.deadline_date, n.main_cpv, n.title, n.buyer_name
         FROM notices n
         WHERE n.category = 'iepirkumi' AND n.contract_folder_id IN ($in)
         ORDER BY k, (n.source <> 'TED'), n.publication_date DESC, n.id DESC"
    );
    $st2->execute($folders);

    $cur = null; $first = true;
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['k'] !== $cur) {
            $cur = $r['k']; $first = true;
            printf("\n▸ %s\n  %s\n", $r['k'], mb_substr((string)$r['buyer_name'], 0, 60));
        }
        printf("   %s %-8s %-14s pub %s  termiņš %s  cpv %-8s %s\n",
            $first ? 'PALIEK ' : 'dzēšams',
            $r['source'], (string)$r['publication_number'],
            (string)$r['publication_date'], (string)$r['deadline_date'],
            (string)$r['main_cpv'], mb_substr((string)$r['title'], 0, 45));
        $first = false;
    }
    echo "\n";
}

// ── Tīrīšana ─────────────────────────────────────────────────────────────────
if ($apply) {
    $res = ks_dedupe_notices($pdo);
    printf("✓ Izdzēstas %d liekās rindas (%d grupas).\n", $res['removed'], $res['groups']);
} else {
    $res = ks_dedupe_notices($pdo, true);
    printf("Sausā palaišana: izdzēstu %d rindas no %d grupām. Ar --apply tiešām izdzēsīs.\n",
        $res['removed'], $res['groups']);
}
