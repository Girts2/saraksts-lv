<?php
/**
 * 7. solis — tūrisma objektu slānis → <valsts>_tourism. ("7 Tourism.py" ports)
 *
 * Lasa "Turisma objekti.txt" (OSM Overpass eksports). Atlasa naktsmītnes un
 * apskates objektus, izmet informācijas dēļus, skulptūras un piknika vietas.
 * Svars = bāzes tips × populāritāte; klastera efektu piemēro pats vaicājums.
 *
 * Viesnīcu konkurenti iespeja.php nāk no ŠĪS tabulas (ttype filtrs), nevis no
 * atsevišķas POI tabulas — tāpēc naktsmītņu tipi te ir obligāti.
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

/** OSM tagi kā populāritātes signāls: maz zināmi = mazs svars, slaveni = liels. */
function s7_popularity(array $t): float
{
    if (array_key_exists('wikidata', $t) || array_key_exists('wikipedia', $t)) return 3.5;
    foreach (['website', 'contact:website', 'opening_hours', 'operator', 'fee'] as $k) {
        if (array_key_exists($k, $t)) return 1.5;                       // darbojas
    }
    if (($t['name'] ?? '') !== '') return 0.7;                          // vietējs
    return 0.2;                                                         // neskaidrs punkts
}

$table = ie_table('tourism');

$t0  = ie_start("7. solis — tūrisma slānis ($table)");
$dry = ie_dry_run_arg($argv);
$pdo = $dry === null ? ie_db() : null;
if ($dry !== null) ie_say("SAUSAIS REŽĪMS — rindas → $dry");

/** Bāzes svars = aptuvenais dienas tūristu skaits vidējai populāritātei. */
$base = ie_country()['tourism']['base'];

$src = ie_find(ie_country()['tourism']['source']);
$raw = file_get_contents($src);
if ($raw === false) ie_fail("nevar nolasīt: $src");
$data = json_decode($raw, true);
unset($raw);
if (!is_array($data) || !isset($data['elements'])) ie_fail("nederīgs JSON: $src");

$rows = [];
foreach ($data['elements'] as $e) {
    $tg  = $e['tags'] ?? [];
    $typ = $tg['tourism'] ?? '';
    if (!isset($base[$typ])) continue;

    $ll = ie_osm_latlon($e);
    if ($ll === null) continue;
    [$lat, $lon] = $ll;

    $rows[] = [
        $e['id'] ?? null,
        ie_round_half_even_p($base[$typ] * s7_popularity($tg), 1),
        $typ,
        ie_cut($tg['name'] ?? null, 250),
        ie_point($lon, $lat),
    ];
}
ie_say(sprintf('Filtrēti %d plūsmu ģenerējošie tūrisma objekti (no %d)',
    count($rows), count($data['elements'])));
unset($data);

ie_prepare_table($pdo, $table, "
    CREATE TABLE IF NOT EXISTS `$table` (
        `osm_id` BIGINT NOT NULL, `score` FLOAT NULL, `ttype` VARCHAR(30) NULL,
        `tname` VARCHAR(255) NULL, `location` POINT NOT NULL,
        SPATIAL INDEX `ix_loc` (`location`), PRIMARY KEY(`osm_id`),
        INDEX `ix_ttype` (`ttype`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$sink = new IeSink($pdo, $table, ['osm_id', 'score', 'ttype', 'tname'], 'location',
                   true, $dry !== null ? (is_dir($dry) ? "$dry/$table.csv" : $dry) : null);
foreach ($rows as $r) $sink->add($r);
$sent = $sink->finish();

// INSERT IGNORE pēc osm_id — kopsavilkumam skaitām unikālos, kā tos redz tabula.
$uniq = [];
foreach ($rows as $r) $uniq[(string)$r[0]] = $r[1];
ie_say(sprintf("Ielādēts $table: %d objekti, kopējais svars %d%s",
    count($uniq), (int)round(array_sum($uniq)),
    $sent - count($uniq) > 0 ? ' (' . ($sent - count($uniq)) . ' dublikāti izlaisti)' : ''));

ie_done($t0);
if (count($uniq) === 0) ie_fail("$table palika tukšs — pārbaudi \"$src\"");
