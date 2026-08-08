<?php
/**
 * 8. solis — iestāžu slānis → <valsts>_institutions. ("8 Iestades.py" ports)
 *
 * Skolas / slimnīcas / stacijas / izklaide / sports — vietas ar lielu dienas
 * cilvēku plūsmu. Izglītība (1263) ir ŠEIT, nevis biroju slānī (6. solis to
 * apzināti neiekļauj), lai plūsma netiktu skaitīta divreiz.
 *
 * Ievade: 5_Būves_raksturojošie_dati.csv (3. solis) + Building.gml (4. solis)
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

/** Platība no 'ekas_platiba', ar atkāpšanos uz 'pilna_platiba'. */
function s8_area(array $row): float
{
    foreach (['ekas_platiba', 'pilna_platiba'] as $col) {
        $v = $row[$col] ?? '';
        if ($v === '' || $v === '-' || $v === null) continue;
        if (!is_numeric($v)) return 0.0;            // Python: ValueError → a = 0
        return (float)$v;
    }
    return 0.0;
}

$table = ie_table('institutions');

$t0  = ie_start("8. solis — iestāžu slānis ($table)");
$dry = ie_dry_run_arg($argv);
if ($dry !== null) ie_say("SAUSAIS REŽĪMS — rindas → $dry");

/**
 * Savienojumu veram tikai pirms rakstīšanas — tas pats iemesls, kas 6. solī:
 * pirms tam tiek lasīts 164 MB kadastra CSV, un ražošanas MariaDB nokauj
 * savienojumu pēc 300 s dīkstāves.
 */
$pdo = null;

/** CC kods → [kategorija, m² uz vienu cilvēku dienā] — no valsts profila. */
$inst_kinds = ie_country()['institutions']['kinds'];
$usable     = (float)ie_country()['institutions']['usable'];

// ── 1/3 Iestāžu ēkas no būvju CSV ───────────────────────────────────────────
$csv = ie_find('5_Būves_raksturojošie_dati.csv');
ie_say("1/3 Nolasu iestāžu ēkas no $csv");

$inst = [];   // bid → [kategorija → cilvēki]
foreach (ie_csv_rows($csv) as $row) {
    $k = $row['kods'] ?? '';
    if (!isset($inst_kinds[$k])) continue;

    $bid = (string)($row['kadastra_telpa'] ?? '');
    if ($bid === '' || strlen($bid) < 14) continue;

    $a = s8_area($row);
    if ($a <= 0) continue;

    [$cat, $dens] = $inst_kinds[$k];
    $p = $a * $usable / $dens;
    if ($p < 1) continue;

    $inst[$bid][$cat] = ($inst[$bid][$cat] ?? 0) + $p;
}

// Vienai ēkai viena dominējošā kategorija, cilvēki = visu kategoriju summa.
// Python max(cats, key=cats.get) neizšķirtā gadījumā atgriež PIRMO — tāpēc
// salīdzinām stingri ar >, saglabājot ievietošanas secību.
$flat = [];
$byCat = [];
foreach ($inst as $bid => $cats) {
    $best = null; $bestV = -INF;
    foreach ($cats as $cat => $v) {
        if ($v > $bestV) { $bestV = $v; $best = $cat; }
    }
    $flat[$bid] = [$best, array_sum($cats)];
    $byCat[$best] = ($byCat[$best] ?? 0) + 1;
}
ie_say(sprintf('   Ēkas: %d, cilvēki: %.0f, pa kat.: %s',
    count($flat),
    array_sum(array_column($flat, 1)),
    json_encode($byCat, JSON_UNESCAPED_UNICODE)));

// ── 2/3 GPS centroīdi no GML ────────────────────────────────────────────────
$gml = ie_find('Building.gml');
ie_say("2/3 Meklēju GPS centroīdus no $gml");

// Tikai tagad — visa lēnā CSV lasīšana ir aiz muguras.
if ($dry === null) $pdo = ie_db();

ie_prepare_table($pdo, $table, "
    CREATE TABLE IF NOT EXISTS `$table` (
        `building_id` VARCHAR(32) NOT NULL, `people` INT NULL, `cat` VARCHAR(20) NULL,
        `location` POINT NOT NULL,
        SPATIAL INDEX `ix_loc` (`location`), PRIMARY KEY(`building_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$sink = new IeSink($pdo, $table, ['building_id', 'people', 'cat'], 'location',
                   true, $dry !== null ? (is_dir($dry) ? "$dry/$table.csv" : $dry) : null);

$seen = [];
$sum  = [];
$stat = ie_gml_centroids($gml,
    static fn(string $bid): bool => isset($flat[$bid]),
    function (string $bid, float $lon, float $lat) use (&$sink, &$flat, &$seen, &$sum): void {
        [$cat, $ppl] = $flat[$bid];
        $people = ie_round_half_even($ppl);
        $sink->add([$bid, $people, $cat, sprintf('POINT(%.8f %.8f)', $lon, $lat)]);
        if (!isset($seen[$bid])) {                 // INSERT IGNORE: dublikāti DB nenonāk
            $seen[$bid] = true;
            $sum[$cat] = [($sum[$cat][0] ?? 0) + 1, ($sum[$cat][1] ?? 0) + $people];
        }
    });
$sent = $sink->finish();

// ── 3/3 Kopsavilkums ────────────────────────────────────────────────────────
ie_say("3/3 Ielādēts $table");
ksort($sum);
foreach ($sum as $cat => [$cnt, $ppl]) {
    ie_say(sprintf('   %-10s %6d ēkas  %8d cilv.', $cat, $cnt, $ppl));
}
$dupes = $sent - count($seen);
ie_say(sprintf('   Kopā atrasts GML: %d%s', count($seen),
    $dupes > 0 ? " ($dupes dublikāti izlaisti)" : ''));
ie_say(sprintf('   GML: skenētas %d, atbilst %d, ar centroīdu %d, bez %d',
    $stat['scanned'], $stat['matched'], $stat['emitted'], $stat['failed']));

ie_done($t0);
if (count($seen) === 0) ie_fail("$table palika tukšs — pārbaudi ievades failus");
