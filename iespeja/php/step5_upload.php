<?php
/**
 * 5. solis — galvenais ēku slānis + pamata POI tipi MySQL. ("5 Upload.py" ports)
 *
 *   out-all.csv   → <valsts>_buildings   (building_id, residents, lvl, location)
 *   cofe.csv      → <valsts>_poi, ptype='cafe'         \
 *   bar.csv       → <valsts>_poi, ptype='bar'           | (ptype, name, location)
 *   food.csv      → <valsts>_poi, ptype='restaurant'    |
 *   frizieri.csv  → <valsts>_poi, ptype='hairdresser'  /
 *
 * VIENA POI TABULA. Agrāk katram POI tipam bija sava tabula ar identisku shēmu,
 * un tips bija iekodēts tabulas nosaukumā. Tas nozīmēja, ka jauna biznesa tipa
 * pievienošana prasīja saskaņot četras vietas (konveijers, frontenda meklēšana,
 * frontenda kartes slāņi, dublēšanas rīks). Tagad tips ir kolonna, un jauns tips
 * ir viena rinda valsts profilā.
 *
 * KOORDINĀŠU SECĪBA. 4. solis raksta out-all.csv formātā POINT(lat lon), bet
 * MySQL POINT gaida POINT(lon lat), tāpēc galvenajai tabulai koordinātas mainām
 * vietām. 1. solis raksta jau POINT(lon lat), tāpēc POI failiem NEMAINĀM.
 * Abos gadījumos lietojam sākotnējās skaitļu virknes, nevis pārrakstām float,
 * lai neieviestu noapaļošanas atšķirību pret Python versiju.
 *
 * Lietošana:
 *   php step5_upload.php                 — raksta MySQL
 *   php step5_upload.php --dry-run=MAPE  — neko neraksta, rindas nonāk CSV failos
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

const WKT_RE = '/^POINT\(\s*(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s*\)/i';

/** Kopīgs POINT(...) izgriezums. @return array{0:string,1:string}|null */
function s5_parse_point(?string $raw): ?array
{
    if ($raw === null || $raw === '') return null;
    if (!str_starts_with(strtoupper($raw), 'POINT(')) return null;
    if (!preg_match(WKT_RE, trim($raw), $m)) return null;
    return [$m[1], $m[2]];
}

/** Ēku tabulas shēma vienam šķēlumam. */
function s5_buildings_ddl(string $table): string
{
    return "
        CREATE TABLE IF NOT EXISTS `$table` (
            `building_id` VARCHAR(32) NOT NULL,
            `residents` VARCHAR(50) NULL,
            `lvl` VARCHAR(10) NULL,
            `location` POINT NOT NULL,
            SPATIAL INDEX `ix_loc` (`location`),
            PRIMARY KEY (`building_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

/**
 * Ēku slānis. Rindas tiek maršrutētas uz reģiona šķēlumu; Latvijai reģions ir
 * viens, tāpēc praksē tā ir viena tabula, bet kods ir tas pats, kas Vācijai.
 */
function s5_buildings(?PDO $pdo, ?string $dryDir): bool
{
    $path = ie_find('out-all.csv');
    $shards = ie_building_shards();
    ie_say("\n--- ĒKU SLĀNIS: $path → " . implode(', ', $shards) . ' ---');

    foreach ($shards as $t) ie_prepare_table($pdo, $t, s5_buildings_ddl($t));

    /** @var array<string, IeSink> reģiona kods → uzkrājējs */
    $sinks = [];
    foreach (ie_regions() as $r) {
        $t = ie_table('buildings', $r['code']);
        $sinks[$r['code']] = new IeSink($pdo, $t, ['building_id', 'residents', 'lvl'], 'location',
                                        false, $dryDir !== null ? "$dryDir/$t.csv" : null);
    }

    $rows = 0; $bad = 0; $done = 0;
    foreach (ie_csv_rows($path) as $row) {
        $rows++;
        $bid = $row['eka'] ?? null;
        $pt  = s5_parse_point($row['point'] ?? null);
        if ($pt === null || $bid === null || $bid === '') { $bad++; continue; }

        // out-all.csv: POINT(lat lon) → MySQL: POINT(lon lat)
        [$lat, $lon] = $pt;

        $sinks[ie_region_for_point((float)$lon, (float)$lat)]->add([
            $bid,
            ($row['cilveki'] ?? '') !== '' ? $row['cilveki'] : null,
            ($row['level']   ?? '') !== '' ? $row['level']   : null,
            "POINT($lon $lat)",
        ]);

        if (++$done % 100000 === 0) ie_say("   …ievietotas $done rindas");
    }

    $ok = 0;
    foreach ($sinks as $code => $sink) {
        $n = $sink->finish();
        $ok += $n;
        if (count($sinks) > 1) ie_say(sprintf('   %-24s %8d rindas', ie_table('buildings', $code), $n));
    }
    ie_say("Apstrādātas: $rows, ievietotas: $ok, izlaistas: $bad");
    return $bad === 0 || $ok > 0;
}

/** Viens POI tips no CSV faila → kopīgā POI tabula. */
function s5_poi(?PDO $pdo, string $file, string $ptype, ?string $dryDir): int
{
    $path  = ie_find($file);
    $table = ie_table('poi');
    ie_say("\n--- $file → $table (ptype='$ptype') ---");

    $sink = new IeSink($pdo, $table, ['ptype', 'name'], 'location',
                       false, $dryDir !== null ? "$dryDir/poi_$ptype.csv" : null);

    $rows = 0; $bad = 0;
    foreach (ie_csv_rows($path) as $row) {
        $rows++;
        $pt = s5_parse_point($row['point'] ?? null);
        if ($pt === null) { $bad++; continue; }

        // 1. solis raksta jau POINT(lon lat) — secību NEMAINĀM.
        [$lon, $lat] = $pt;
        $name = isset($row['name']) && $row['name'] !== null ? trim((string)$row['name']) : '';

        $sink->add([$ptype, $name !== '' ? $name : null, "POINT($lon $lat)"]);
    }
    $ok = $sink->finish();
    ie_say("Apstrādātas: $rows, ievietotas: $ok, izlaistas: $bad");
    return $ok;
}

// ── Izpilde ─────────────────────────────────────────────────────────────────

$t0  = ie_start('5. solis — MySQL augšupielāde (ēkas + pamata POI)');
$dry = ie_dry_run_arg($argv);
if ($dry !== null) {
    if (!is_dir($dry) && !@mkdir($dry, 0775, true)) ie_fail("nevar izveidot mapi: $dry");
    ie_say("SAUSAIS REŽĪMS — MySQL netiek aiztikts, rindas → $dry/");
}
$pdo = $dry === null ? ie_db() : null;
if ($pdo !== null) {
    $c = ie_config();
    ie_say("Pieslēgts {$c['host']}:{$c['port']} / {$c['name']}");
}
ie_say('Valsts: ' . ie_country()['name'] . ' (' . IE_COUNTRY . '), reģioni: ' . count(ie_regions()));

$fails = [];
if (!s5_buildings($pdo, $dry)) $fails[] = ie_table('buildings');

// Šī soļa POI tipi = tie, kuriem valsts profilā ir CSV fails.
$csvTypes = [];
foreach (ie_country()['poi'] as $ptype => $def) {
    if (isset($def['csv'])) $csvTypes[$ptype] = $def['csv'];
}

// Shēma ĀRPUS transakcijas (DDL = netiešs commit), dati iekšpusē.
ie_poi_create($pdo);

// Vecos šo tipu ierakstus izdzēš TRANSAKCIJĀ, 9. soļa tipus neaiztiekot.
if ($pdo !== null) $pdo->beginTransaction();
try {
    ie_poi_clear($pdo, array_keys($csvTypes));
    foreach ($csvTypes as $ptype => $file) s5_poi($pdo, $file, $ptype, $dry);
    if ($pdo !== null) $pdo->commit();
} catch (Throwable $e) {
    if ($pdo !== null) $pdo->rollBack();
    ie_fail('POI ielāde neizdevās, izmaiņas atceltas: ' . $e->getMessage());
}

ie_done($t0);

// Python versija kļūdas tikai izdrukāja un beidza ar kodu 0, tāpēc administratīvais
// panelis un cron neizdošanos neredzēja. Šeit kļūme atgriež kodu 1.
if ($fails) ie_fail('neizdevās: ' . implode(', ', $fails));
