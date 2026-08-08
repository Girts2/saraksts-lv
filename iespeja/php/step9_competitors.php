<?php
/**
 * 9. solis — pārējo biznesa tipu konkurenti no OSM → <valsts>_poi.
 * ("9 Konkurenti-OSM.py" ports)
 *
 * Tipi nāk no valsts profila: tie POI ieraksti, kuriem NAV 'csv' atslēgas
 * (tos ar CSV ievāc 1. solis un ielādē 5. solis). Viesnīcai konkurenti nāk no
 * tūrisma slāņa (7. solis), tāpēc tās šeit nav.
 *
 * AVOTU izvēlas valsts profils (`osm.source`): 'pbf' lasa Geofabrik izgriezumu
 * ar pbf.php, 'overpass' iet uz API. Solis abus redz vienādi, jo ie_osm_collect()
 * atgriež to pašu struktūru. Latvijā PBF ceļš aizņem ~15 s; caur Overpass tas
 * pats pēdējoreiz vilkās 43 minūtes, jo galvenā instance bija nesasniedzama un
 * seši no septiņiem tipiem gāja caur rezerves serveriem.
 *
 * SECĪBA IR SVARĪGA: vecos ierakstus dzēš tikai PĒC tam, kad dati jau ir rokā,
 * un dara to transakcijā. Ja avots nav pieejams, tips paliek ar vecajiem datiem,
 * nevis tukšs.
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

$table  = ie_table('poi');
$source = ie_country()['osm']['source'] ?? 'overpass';

$t0  = ie_start("9. solis — OSM konkurenti no " . strtoupper($source) . " ($table)");
$dry = ie_dry_run_arg($argv);
if ($dry !== null) {
    if (!is_dir($dry) && !@mkdir($dry, 0775, true)) ie_fail("nevar izveidot mapi: $dry");
    ie_say("SAUSAIS REŽĪMS — rindas → $dry/");
}
/** Šī soļa tipi: bez 'csv' atslēgas valsts profilā. */
$types = [];
foreach (ie_country()['poi'] as $ptype => $def) {
    if (!isset($def['csv'])) $types[$ptype] = $def['selectors'];
}
ie_say('Tipi: ' . implode(', ', array_keys($types)) . '; reģioni: ' . count(ie_regions()));

// ── 1/2 Ievākšana (MySQL vēl netiek aiztikts) ───────────────────────────────
$defs = [];
foreach ($types as $ptype => $selectors) {
    $defs[$ptype] = ['selectors' => $selectors,
                     'types' => ['node', 'way', 'relation'],
                     'requireName' => false];
}
$data = ie_osm_collect($defs);          // PBF vai Overpass — pēc valsts profila

$collected = [];
$skipped   = [];
foreach ($types as $ptype => $_) {
    if (($data[$ptype] ?? null) === null) { $skipped[] = $ptype; continue; }

    $rows = [];
    $named = 0;
    foreach ($data[$ptype]['elements'] as $e) {
        $ll = ie_osm_latlon($e);
        if ($ll === null) continue;
        [$lat, $lon] = $ll;

        $name = ie_cut($e['tags']['name'] ?? null, 250);
        if ($name !== '') $named++;
        $rows[] = [$ptype, $name !== '' ? $name : null, ie_point($lon, $lat)];
    }
    $collected[$ptype] = $rows;
    ie_say(sprintf('   %-12s %5d objekti (%d ar nosaukumu)', $ptype, count($rows), $named));
}

// ── 2/2 Rakstīšana (tikai tagad, kad dati ir droši) ─────────────────────────
// MySQL savienojumu veram TIKAI TAGAD, pēc ievākšanas. Ievākšana ar Overpass
// atkārtojumiem var ilgt 5–10 minūtes, un koplietotais hostings pa to laiku
// dīkstāvē stāvošu savienojumu nokauj (wait_timeout) — pirmais mēģinājums
// tieši tā nokrita ar "2006 MySQL server has gone away" pie CREATE TABLE.
$pdo = $dry === null ? ie_db() : null;
ie_poi_create($pdo);                              // DDL ĀRPUS transakcijas

if ($collected) {
    if ($pdo !== null) $pdo->beginTransaction();
    try {
        ie_poi_clear($pdo, array_keys($collected));
        foreach ($collected as $ptype => $rows) {
            $sink = new IeSink($pdo, $table, ['ptype', 'name'], 'location',
                               false, $dry !== null ? "$dry/poi_$ptype.csv" : null, 500);
            foreach ($rows as $r) $sink->add($r);
            $sink->finish();
        }
        if ($pdo !== null) $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo !== null) $pdo->rollBack();
        ie_fail('POI ielāde neizdevās, izmaiņas atceltas: ' . $e->getMessage());
    }
    ie_say(sprintf('Ierakstīti %d tipi, %d rindas', count($collected),
        array_sum(array_map('count', $collected))));
}

ie_done($t0);

// Python versija izlaistos tipus tikai izdrukāja un beidza ar kodu 0. Cron un
// administratīvais panelis tā nekad neuzzinātu, ka slānis nav atsvaidzināts.
if ($skipped) ie_fail('izlaisti tipi: ' . implode(', ', $skipped));
