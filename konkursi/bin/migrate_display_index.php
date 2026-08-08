<?php
/**
 * konkursi/bin/migrate_display_index.php — VIENREIZĒJA idx_notices_display būve.
 *
 * Indekss ir aprakstīts lib/db.php shēmā (KONKURSI_SCHEMA_SQL), tāpēc to izveidotu
 * arī pirmā datubāzes atvēršana. Bet uz 1 GB faila būve prasa sekundes, un lapas
 * starts atver DB 6 paralēlos pieprasījumos — pirmais paņemtu rakstīšanas slēdzeni,
 * pārējie gaidītu (busy_timeout=8000) un pie lēna diska varētu atdot "Servera kļūda".
 * Tāpēc pēc jaunā koda izvietošanas šo palaid PIRMS lapa saņem pirmo apmeklētāju.
 *
 * Idempotents (CREATE INDEX IF NOT EXISTS) — droši palaist atkārtoti.
 *
 * Lietošana:
 *   php konkursi/bin/migrate_display_index.php           # sausā palaišana (mēra, neko nebūvē)
 *   php konkursi/bin/migrate_display_index.php --apply   # izveido indeksu
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

set_time_limit(0);

$apply = in_array('--apply', $argv, true);

// SVARĪGI: konkursi_db() izpilda visu shēmu, tātad ar --apply tā jau uztaisītu
// indeksu pati. Sausajā palaišanā mums vajag DB BEZ tā, lai izmērītu "pirms".
// Tāpēc atveram savienojumu tieši, apejot shēmas izpildi.
$path = konkursi_db_path();
if (!is_file($path)) {
    fwrite(STDERR, "Datubāze nav atrasta: $path\n");
    exit(1);
}
$pdo = new PDO('sqlite:' . $path, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA busy_timeout=60000');   // būves laikā sync var turēt slēdzeni
$pdo->exec('PRAGMA mmap_size=268435456');
$pdo->exec('PRAGMA cache_size=-65536');

/**
 * Mērķa indeksi — TĀM PAŠĀM definīcijām jābūt lib/db.php KONKURSI_SCHEMA_SQL.
 * Divi no tiem esošā datubāzē jau pastāv ar veco, šauro definīciju; tos jāpārbūvē
 * (CREATE INDEX IF NOT EXISTS esošu indeksu nemaina).
 */
$TARGET = [
    'idx_notices_display' =>
        'CREATE INDEX idx_notices_display ON notices('
        . 'category, deadline_date, publication_date, first_seen, '
        . 'source, buyer_country, main_cpv, buyer_name)',
    'idx_notices_cat_country' =>
        'CREATE INDEX idx_notices_cat_country ON notices('
        . 'category, buyer_country, deadline_date, publication_date, first_seen)',
    'idx_notices_cat_cpv' =>
        'CREATE INDEX idx_notices_cat_cpv ON notices('
        . 'category, main_cpv, deadline_date, publication_date, first_seen)',
];

/** Esošā indeksa definīcija (NULL, ja indeksa nav). */
$currentSql = static function (PDO $pdo, string $name): ?string {
    $st = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='index' AND name = ?");
    $st->execute([$name]);
    $v = $st->fetchColumn();
    return $v === false || $v === null ? null : (string)$v;
};

/**
 * Salīdzināšanai: atstarpes un reģistrs nav būtiski. Atstarpes izmet PILNĪBĀ, nevis
 * tikai saspiež — db.php shēmā kolonnu saraksts ir pārnests jaunā rindā, tāpēc
 * saspiešana atstātu 'notices( category' pret 'notices(category' un šis skripts
 * mūžīgi ziņotu par pārbūvējamu indeksu. Indeksa DDL nav virkņu literāļu, tāpēc
 * atstarpju izmešana ir droša.
 */
$norm = static fn(string $s): string => strtolower(preg_replace('/\s+/', '', trim($s)) ?? '');

/** Ko vajag darīt katram indeksam: 'ok' | 'create' | 'rebuild'. */
$plan = [];
foreach ($TARGET as $name => $sql) {
    $cur = $currentSql($pdo, $name);
    $plan[$name] = $cur === null ? 'create' : ($norm($cur) === $norm($sql) ? 'ok' : 'rebuild');
}

// ── Displeja logi — TIE PAŠI, ko lieto konkursi.php ──────────────────────────
$today = konkursi_today();
$tz = new DateTimeZone('Europe/Riga');
$cutNoDl   = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_NODEADLINE_DAYS . ' days')->format('Y-m-d');
$cutResult = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_RESULTS_DAYS . ' days')->format('Y-m-d');
$activeCond = "(n.deadline_date >= '$today' OR (n.deadline_date IS NULL AND COALESCE(n.publication_date, n.first_seen) >= '$cutNoDl'))";
$resultCond = "(n.publication_date IS NULL OR n.publication_date >= '$cutResult')";

/** Starta karstā ceļa vaicājumi — tie, kas pirms indeksa skenēja visu tabulu. */
$PROBES = [
    'Aktīvie skaitītājs (list)' =>
        "SELECT COUNT(*) FROM notices n WHERE n.category='iepirkumi' AND $activeCond",
    'Rezultātu skaitītājs (list)' =>
        "SELECT COUNT(*) FROM notices n WHERE n.category='rezultati' AND $resultCond",
    'Avotu panelis (sources)' =>
        "SELECT n.source, COUNT(*) c FROM notices n WHERE n.category='iepirkumi' AND $activeCond GROUP BY n.source",
    'Valstu izvēlne (countries)' =>
        "SELECT n.buyer_country, COUNT(*) c, MIN(n.publication_date), MAX(n.publication_date)
           FROM notices n WHERE n.category='iepirkumi' AND $activeCond GROUP BY n.buyer_country",
    'CPV izvēlne (cpv)' =>
        "SELECT substr(n.main_cpv,1,2) d, COUNT(*) c FROM notices n
          WHERE n.category='iepirkumi' AND n.main_cpv IS NOT NULL AND $activeCond GROUP BY d",
    'Pasūtītāju izvēlne (buyers)' =>
        "SELECT n.buyer_name, COUNT(*) c FROM notices n
          WHERE n.category='iepirkumi' AND n.buyer_name IS NOT NULL AND n.buyer_name <> ''
            AND $activeCond GROUP BY n.buyer_name ORDER BY c DESC LIMIT " . KONKURSI_BUYER_SUGGEST_MAX,
];

/** printf %-30s skaita baitus, tāpēc garumzīmes izjauktu kolonnas — papildina pēc rakstzīmēm. */
$pad = static function (string $s, int $w): string {
    $n = mb_strlen($s, 'UTF-8');
    return $n >= $w ? $s : $s . str_repeat(' ', $w - $n);
};

/**
 * Izpilda vaicājumu; atgriež [ms, vai plāns iztiek BEZ tabulas lasījumiem].
 * Mērķis nav konkrēts indeksa nosaukums, bet gan tas, ka KATRS solis plānā ir
 * pārklājošs — tieši rindu lasījumi 1 GB tabulā ir tas, kas maksā sekundes.
 */
$probe = static function (PDO $pdo, string $sql): array {
    $t = microtime(true);
    $pdo->query($sql)->fetchAll();
    $ms = (microtime(true) - $t) * 1000;
    $covered = true;
    foreach ($pdo->query('EXPLAIN QUERY PLAN ' . $sql) as $r) {
        $d = (string)($r['detail'] ?? '');
        // Interesē tikai tabulas piekļuves soļi; MULTI-INDEX OR / TEMP B-TREE nav.
        if (!preg_match('/\b(SEARCH|SCAN)\b/', $d)) continue;
        if (!str_contains($d, 'COVERING INDEX')) { $covered = false; break; }
    }
    return [$ms, $covered];
};

/** Faila izmērs MB (stat kešs jānotīra, citādi pēc būves rāda veco). */
$sizeMb = static function (string $p): int {
    clearstatcache(true, $p);
    return (int)round((int)@filesize($p) / 1048576);
};

$dbMb = $sizeMb($path);
$rows = (int)$pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();
$act  = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE category='iepirkumi'")->fetchColumn();

$STATUS = ['ok' => 'jau atbilst', 'create' => 'JĀIZVEIDO', 'rebuild' => 'JĀPĀRBŪVĒ (veca definīcija)'];
echo "Datubāze:       $path ({$dbMb} MB)\n";
echo "notices rindas: $rows (no tām 'iepirkumi': $act)\n\n";
echo "Indeksi:\n";
foreach ($plan as $name => $what) printf("  %s %s\n", $pad($name, 26), $STATUS[$what]);
echo "\n";
echo $apply ? "REŽĪMS: --apply (būvē indeksus)\n\n"
            : "REŽĪMS: sausā palaišana (neko nebūvē; --apply lai izpildītu)\n\n";

// ── "Pirms" mērījums ────────────────────────────────────────────────────────
echo "Starta vaicājumu laiki ŠOBRĪD:\n";
$before = [];
foreach ($PROBES as $label => $sql) {
    [$ms, $cov] = $probe($pdo, $sql);
    $before[$label] = $ms;
    printf("  %s %8.0f ms%s\n", $pad($label, 30), $ms, $cov ? '  (jau pārklājošs)' : '  ← lasa tabulu');
}
printf("  %s %8.0f ms\n", $pad('KOPĀ', 30), array_sum($before));

if (!$apply) {
    echo "\nSausā palaišana pabeigta. Nekas netika rakstīts.\n";
    echo "Palaid ar --apply, lai izveidotu indeksus.\n";
    exit(0);
}

// ── Būve ────────────────────────────────────────────────────────────────────
// Apzināti BEZ ANALYZE: ar sqlite_stat4 plānotājs valstu izvēlnei izvēlējās šauro
// indeksu un atgriezās pie rindu lasījumiem (106 ms vietā 7 ms). Bez tā izvēle ir
// stabila un pareiza.
if (!in_array('create', $plan, true) && !in_array('rebuild', $plan, true)) {
    echo "\nVisi indeksi jau atbilst — būve izlaista.\n";
} else {
    foreach ($plan as $name => $what) {
        if ($what === 'ok') continue;
        $t = microtime(true);
        if ($what === 'rebuild') {
            echo "\nPārbūvē $name (veca definīcija) …\n";
            $pdo->exec("DROP INDEX IF EXISTS $name");
        } else {
            echo "\nBūvē $name …\n";
        }
        $pdo->exec($TARGET[$name]);
        printf("  gatavs %.1f s laikā.\n", microtime(true) - $t);
    }
}

$newMb = $sizeMb($path);
printf("\nDatubāzes izmērs: %d MB → %d MB (%+d MB)\n\n", $dbMb, $newMb, $newMb - $dbMb);

// ── "Pēc" mērījums ──────────────────────────────────────────────────────────
echo "Starta vaicājumu laiki PĒC:\n";
$after = [];
$allCovered = true;
foreach ($PROBES as $label => $sql) {
    [$ms, $cov] = $probe($pdo, $sql);
    $after[$label] = $ms;
    if (!$cov) $allCovered = false;
    printf("  %s %8.0f ms  (bija %.0f ms)%s\n", $pad($label, 30), $ms, $before[$label],
        $cov ? '  ✓ pārklājošs' : '  ⚠ lasa tabulu');
}
printf("  %s %8.0f ms  (bija %.0f ms)\n", $pad('KOPĀ', 30), array_sum($after), array_sum($before));

echo "\n";
echo $allCovered
    ? "✓ Visi starta vaicājumi iet pa pārklājošiem indeksiem — tabula netiek aiztikta.\n"
    : "⚠ Daļa vaicājumu joprojām lasa tabulu. Pārbaudi, vai displeja logi lib/config.php\n"
    . "  (KONKURSI_KEEP_*) un konkursi.php nosacījumi joprojām sakrīt ar indeksa kolonnām.\n";
