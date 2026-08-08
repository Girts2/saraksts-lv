<?php
/**
 * 1. solis — četri pamata POI tipi → CSV. ("1 iestādes.py" ports)
 *
 *   bar.csv       amenity = bar | pub | nightclub
 *   cofe.csv      amenity = cafe
 *   food.csv      amenity = restaurant | fast_food | food_court
 *   frizieri.csv  shop    = hairdresser
 *
 * KĀPĒC OVERPASS, NEVIS .pbf. Python versija lejupielādēja 139 MB Geofabrik
 * izgriezumu un lasīja to ar `osmium`, lai iegūtu ~2500 punktus. PHP .pbf
 * lasītāja nav — bet 9. solis blakus jau ņem septiņus CITUS POI tipus no
 * Overpass ar tīru curl. Šie četri tipi tur iederas bez izņēmuma: rezultāts ir
 * tas pats, un 139 MB lejupielāde pazūd pavisam.
 *
 * ELEMENTU TIPI. Python apstrādāja TIKAI `node` (PlaceHandler ieviesa tikai
 * node()), tāpēc restorāns, kas kartē uzzīmēts kā ēkas kontūra, pazuda.
 * NOKLUSĒJUMS ŠEIT IR TAS PATS — tikai `node`, lai ports paliek ports un konkurentu
 * skaits nemainās.
 *
 * `--with-ways` pieliek arī `way` un `relation`, tāpat kā 9. solī. Tas dod +326
 * objektus (+12,9 %: bar 341→373, cofe 722→835, food 1118→1271, frizieri 353→365)
 * un padara visus 11 konkurentu slāņus savstarpēji salīdzināmus — 9. solis
 * `way`/`relation` ņem vienmēr, tāpēc šiem četriem tipiem konkurentu ir
 * sistemātiski mazāk. Bet tā JAU IR DATU IZMAIŅA, ne ports: pēc tās 12 biznesa
 * tipu konkurences koeficienti `iespeja.php` var būt jāpārkalibrē
 * (`tools/calibrate_new.py`). Tāpēc tas ir aiz karoga, nevis noklusējumā.
 *
 * NOSAUKUMS OBLIGĀTS. Python ņēma tikai objektus ar `name` (9. solis pretēji —
 * tur nosaukums drīkst trūkt). Filtru liekam pašā vaicājumā, tāpēc Overpass
 * atsūta mazāk datu.
 *
 * Lietošana:
 *   php step1_poi.php                  — kā Python: tikai OSM mezgli
 *   php step1_poi.php --with-ways      — pieliek way+relation (mainīs skaitļus!)
 *   php step1_poi.php --out=/kāda/mape — cita izvades mape
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

/**
 * Kategorijas nāk no valsts profila (countries/<kods>.php) — šeit tās vairs nav
 * ierakstītas. Ņemam tikai tos POI tipus, kuriem profilā ir 'csv': pārējos
 * 9. solis liek datubāzē tieši, bez starpfaila.
 *
 * @var array<string, array> CSV fails → OSM selektori [atslēga, vērtība]
 */
$CATEGORIES = [];
foreach (ie_country()['poi'] as $ptype => $def) {
    if (isset($def['csv'])) $CATEGORIES[$def['csv']] = $def['selectors'];
}

$t0 = ie_start('1. solis — pamata POI no Overpass (' . count($CATEGORIES) . ' CSV faili)');

$withWays = in_array('--with-ways', $argv, true);
$outDir = ie_out_dir();
foreach ($argv as $a) if (str_starts_with($a, '--out=')) $outDir = rtrim(substr($a, 6), '/');
if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) ie_fail("nevar izveidot mapi: $outDir");

$types = $withWays ? ['node', 'way', 'relation'] : ['node'];
ie_say('Elementu tipi: ' . implode('+', $types)
    . ($withWays ? '  (PAPLAŠINĀTS — skaitļi mainīsies pret Python)' : '  (kā Python 1. solis)'));
ie_say("Izvade: $outDir");

// Ievācam visus četrus tipus vienā piegājienā. PBF gadījumā tas nozīmē vienu
// faila caurskati, nevis četras — Latvijā 26 s vietā 100 s, Vācijā stundas vietā
// minūtes. Overpass gadījumā uzvedība paliek tā pati, kas bija.
$defs = [];
foreach ($CATEGORIES as $file => $selectors) {
    $defs[$file] = ['selectors' => $selectors, 'types' => $types, 'requireName' => true];
}
$collected = ie_osm_collect($defs);

$skipped = [];
$total = 0;
foreach ($CATEGORIES as $file => $selectors) {
    $data = $collected[$file] ?? null;
    if ($data === null) {
        $skipped[] = $file;
        continue;
    }

    $rows = [];
    $seen = [];
    foreach (($data['elements'] ?? []) as $e) {
        $key = ($e['type'] ?? '') . '/' . ($e['id'] ?? '');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $name = ie_cut($e['tags']['name'] ?? null, 250);
        if ($name === '') continue;               // Python: if 'name' in n.tags

        $ll = ie_osm_latlon($e);
        if ($ll === null) continue;
        [$lat, $lon] = $ll;

        $rows[] = [ie_point($lon, $lat), $name];
    }

    // Rakstām tikai tad, kad rindas ir rokā — neizdevies vaicājums vecu failu neiztukšo.
    $path = "$outDir/$file";
    $tmp  = $path . '.tmp';
    $fh = fopen($tmp, 'w');
    if ($fh === false) ie_fail("nevar rakstīt: $tmp");
    ie_fputcsv_py($fh, ['point', 'name']);          // csv.writer noklusējums = CRLF
    foreach ($rows as $r) ie_fputcsv_py($fh, $r);
    fclose($fh);
    if (!@rename($tmp, $path)) ie_fail("nevar pārsaukt $tmp → $path");

    $total += count($rows);
    ie_say(sprintf('   %-13s %5d ieraksti', $file, count($rows)));
}

ie_say("Kopā $total rindas");
ie_done($t0);

if ($skipped) ie_fail('izlaisti faili: ' . implode(', ', $skipped));
