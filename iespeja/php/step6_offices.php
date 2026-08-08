<?php
/**
 * 6. solis — biroju/komerciālo ēku slānis → <valsts>_offices. ("6 Offices.py" ports)
 *
 * Ievade:  5_Būves_raksturojošie_dati.csv (3. solis) + Building.gml (4. solis)
 *          10_Objekta_novērtējums_un_kadastrālās_vērtības.csv (pirktspējas līmenis)
 * Izvade:  <valsts>_offices (building_id, workers, lvl, location) — dienas darbinieku plūsma
 *
 * Izglītība (1263) šeit APZINĀTI nav — tā ir 8. solī, lai slāņi nedublējas.
 *
 * ── LĪMEŅI TAGAD IR KVANTILES, NEVIS ABSOLŪTI EIRO ─────────────────────────
 *
 * Agrāk šeit bija s6_lvl() ar ierakstītiem sliekšņiem: >=700 €/m² = A, >=350 = B,
 * >=120 = C, >=50 = D. Latvijai tie bija kalibrēti, bet citā valstī tie klusi
 * saražo blēņas — Kopenhāgenā gandrīz viss kļūtu par 'A', Bukarestē gandrīz viss
 * par 'E'. Lapa atvērtos, tabulas būtu pilnas, un rezultāts būtu bezjēdzīgs.
 *
 * Tagad sliekšņi nāk no PAŠU DATU sadalījuma — svērtās kvantiles pēc platības,
 * tieši tā, kā 3. solis to jau darīja dzīvojamām ēkām. Blakusieguvums: abu slāņu
 * 'lvl' beidzot nozīmē vienu un to pašu ("top 10% šajā valstī"), kas līdz šim
 * tā nebija, un neviens to nemanīja, jo abus nekad nesalīdzināja blakus.
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

/** Platība no 'ekas_platiba', ar atkāpšanos uz 'pilna_platiba'. */
function s6_area(array $row): float
{
    foreach (['ekas_platiba', 'pilna_platiba'] as $col) {
        $v = $row[$col] ?? '';
        if ($v === '' || $v === '-' || $v === null) continue;
        if (!is_numeric($v)) return 0.0;            // Python: ValueError → a = 0
        return (float)$v;
    }
    return 0.0;
}

/** Vērtība → burts pēc kvantiļu sliekšņiem (tā pati kārtība, ko lieto 3. solis). */
function s6_band(float $v, array $thr): string
{
    if ($v < $thr[0]) return 'E';
    if ($v < $thr[1]) return 'D';
    if ($v < $thr[2]) return 'C';
    if ($v < $thr[3]) return 'B';
    return 'A';
}

$t0  = ie_start('6. solis — biroju slānis (' . ie_table('offices') . ')');
$dry = ie_dry_run_arg($argv);
if ($dry !== null) ie_say("SAUSAIS REŽĪMS — rindas → $dry");

/**
 * MySQL savienojumu veram TIKAI PIRMS RAKSTĪŠANAS (skat. 2/3 sadaļu).
 *
 * Pirms tam šis solis nolasa divus kadastra CSV failus (kopā ~350 MB) un
 * izrēķina kvantiles. Uz izstrādes mašīnas tās ir sekundes, bet uz koplietotā
 * hostinga ar lēnāku disku tas var ilgt minūtes — un ražošanas MariaDB
 * `wait_timeout` ir 300 s. Dīkstāvē stāvošs savienojums tiktu nokauts, un
 * pirmais DB izsaukums nokristu ar "MySQL server has gone away". Tieši tā
 * nokrita 9. solis, kur savienojums gaidīja Overpass atbildi.
 */
$pdo = null;

$profile = ie_country()['office'];
$density = $profile['density'];
$usable  = (float)$profile['usable'];
$maxArea = (float)$profile['max_area'];
$quants  = $profile['level_quantiles'];

// ── 1/3 Biroju ēkas no būvju CSV ────────────────────────────────────────────
$csv = ie_find('5_Būves_raksturojošie_dati.csv');
ie_say("1/3 Nolasu biroju/komerciālās ēkas no $csv");

$offices = [];   // ēkas identifikators → darbinieku skaits
$areas   = [];   // ēkas identifikators → platība m²
foreach (ie_csv_rows($csv) as $row) {
    $k = $row['kods'] ?? '';
    if (!isset($density[$k])) continue;

    $bid = (string)($row['kadastra_telpa'] ?? '');
    if ($bid === '' || strlen($bid) < 14) continue;

    $a = s6_area($row);
    if ($a <= 0 || $a >= $maxArea) continue;

    $w = $a * $usable / $density[$k];
    if ($w >= 1) {
        $offices[$bid] = ($offices[$bid] ?? 0) + $w;
        $areas[$bid]   = ($areas[$bid]   ?? 0) + $a;
    }
}
ie_say(sprintf('   Atrastas %d ēkas, ~%.0f darbinieki', count($offices), array_sum($offices)));

// ── 1b/3 Pirktspēja: vispirms €/m², tad kvantiles, tad burti ────────────────
$val = ie_find('10_Objekta_novērtējums_un_kadastrālās_vērtības.csv');
ie_say("1b/3 Pirktspējas līmeņi no $val");

$ppm = [];   // ēkas identifikators → €/m²
foreach (ie_csv_rows($val) as $row) {
    if (($row['buve'] ?? '') !== 'BUILDING') continue;
    $kad = (string)($row['kadastrs'] ?? '');
    if (!isset($areas[$kad])) continue;

    $cena = $row['univ_cena'] ?? '';
    if ($cena === '' || $cena === '-' || $cena === null || !is_numeric($cena)) continue;

    $v = (float)$cena;
    if ($v > 0 && $areas[$kad] > 0) $ppm[$kad] = $v / $areas[$kad];
}

// Sliekšņi no sadalījuma, svērti ar ēkas platību: liels birojs sadalījumu
// ietekmē vairāk nekā mazs, tāpat kā 3. solī dzīvokli sver iedzīvotāju skaits.
$pairs = [];
foreach ($ppm as $bid => $v) $pairs[] = [$v, $areas[$bid]];
$thr = ie_weighted_quantile($pairs, $quants);
if ($thr === null) ie_fail('nevar aprēķināt līmeņu sliekšņus — nav derīgu kadastrālo vērtību');

$lvls = [];
foreach ($ppm as $bid => $v) $lvls[$bid] = s6_band($v, $thr);

ie_say(sprintf('   Sliekšņi (€/m²): q%d=%.0f  q%d=%.0f  q%d=%.0f  q%d=%.0f',
    (int)($quants[0] * 100), $thr[0], (int)($quants[1] * 100), $thr[1],
    (int)($quants[2] * 100), $thr[2], (int)($quants[3] * 100), $thr[3]));
ie_say(sprintf('   Līmeņi %d ēkām (%d%%)', count($lvls),
    (int)round(100 * count($lvls) / max(1, count($offices)))));

// ── 2/3 GPS centroīdi no GML ────────────────────────────────────────────────
$gml = ie_find('Building.gml');
ie_say("2/3 Meklēju GPS centroīdus no $gml");

// Tikai tagad — visa lēnā CSV lasīšana ir aiz muguras.
if ($dry === null) $pdo = ie_db();

$table = ie_table('offices');
ie_prepare_table($pdo, $table, "
    CREATE TABLE IF NOT EXISTS `$table` (
        `building_id` VARCHAR(32) NOT NULL, `workers` INT NULL, `lvl` CHAR(1) NULL,
        `location` POINT NOT NULL,
        SPATIAL INDEX `ix_loc` (`location`), PRIMARY KEY(`building_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$sink = new IeSink($pdo, $table, ['building_id', 'workers', 'lvl'], 'location',
                   true, $dry !== null ? (is_dir($dry) ? "$dry/$table.csv" : $dry) : null);

$seen = [];
$byLvl = [];
$stat = ie_gml_centroids($gml,
    static fn(string $bid): bool => isset($offices[$bid]),
    function (string $bid, float $lon, float $lat) use (&$sink, &$offices, &$lvls, &$seen, &$byLvl): void {
        $workers = ie_round_half_even($offices[$bid]);
        $lvl = $lvls[$bid] ?? null;
        $sink->add([$bid, $workers, $lvl, sprintf('POINT(%.8f %.8f)', $lon, $lat)]);
        if (!isset($seen[$bid])) {                 // INSERT IGNORE: dublikāti DB nenonāk
            $seen[$bid] = true;
            $key = $lvl ?? '-';
            $byLvl[$key] = [($byLvl[$key][0] ?? 0) + 1, ($byLvl[$key][1] ?? 0) + $workers];
        }
    });
$sent = $sink->finish();

// ── 3/3 Kopsavilkums ────────────────────────────────────────────────────────
ie_say("3/3 Ielādēts $table");
ksort($byLvl);
foreach ($byLvl as $lvl => [$cnt, $w]) {
    ie_say(sprintf('   lvl %-2s %6d ēkas  %8d darbinieki', $lvl, $cnt, $w));
}
$dupes = $sent - count($seen);
ie_say(sprintf('   DB: %d ēkas, %d darbinieki%s',
    count($seen), array_sum(array_column($byLvl, 1)),
    $dupes > 0 ? " ($dupes dublikāti izlaisti)" : ''));
ie_say(sprintf('   GML: skenētas %d, atbilst %d, ar centroīdu %d, bez %d',
    $stat['scanned'], $stat['matched'], $stat['emitted'], $stat['failed']));

ie_done($t0);
if (count($seen) === 0) ie_fail("$table palika tukšs — pārbaudi ievades failus");
