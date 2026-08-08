<?php
/**
 * Ģeometrijas zelta-diff: PHP shoelace centroīds pret shapely.
 *
 * Etalons ir out-all.csv — to uzrakstīja 4. solis ar shapely
 * (Polygon(...).buffer(0).centroid), tāpēc katra tā rinda ir shapely atbilde uz
 * to pašu poligonu. Šeit tos pašus poligonus izrēķina PHP un salīdzina.
 *
 * Vienīgā zināmā atšķirība ir .buffer(0) — shapely ar to salabo pašsagriezušās
 * kontūras, PHP tāda remonta nav. Šis rīks pasaka, cik tādu ēku patiesībā ir un
 * cik lielas ir novirzes metros.
 *
 *   php tools/gml_centroid_diff.php [--limit=N]
 */
declare(strict_types=1);
require_once __DIR__ . '/../common.php';

$limit = 0;
foreach ($argv as $a) if (str_starts_with($a, '--limit=')) $limit = (int)substr($a, 8);

$ref  = ie_find('out-all.csv');
$gml  = ie_find('Building.gml');

// ── Etalons atmiņā: bid → "lat lon" ─────────────────────────────────────────
ie_say("Lasu etalonu: $ref");
$want = [];
foreach (ie_csv_rows($ref) as $row) {
    $p = $row['point'] ?? '';
    if (!preg_match('/^POINT\(\s*(-?[\d.]+)\s+(-?[\d.]+)\s*\)/i', (string)$p, $m)) continue;
    $want[(string)$row['eka']] = [(float)$m[1], (float)$m[2]];   // [lat, lon]
    if ($limit && count($want) >= $limit) break;
}
ie_say(sprintf('   %d etalona centroīdi, atmiņa %.0f MB',
    count($want), memory_get_usage(true) / 1048576));

// ── Salīdzinājums ───────────────────────────────────────────────────────────
ie_say("Straumēju $gml un rēķinu PHP centroīdus…");
$t0 = microtime(true);

$n = 0; $exact = 0; $worst = 0.0; $worstBid = '';
$buckets = ['≤1mm' => 0, '≤1cm' => 0, '≤10cm' => 0, '≤1m' => 0, '>1m' => 0];
$examples = [];

$stat = ie_gml_centroids($gml,
    static fn(string $bid): bool => isset($want[$bid]),
    function (string $bid, float $lon, float $lat) use (&$want, &$n, &$exact, &$worst, &$worstBid, &$buckets, &$examples): void {
        [$rLat, $rLon] = $want[$bid];
        $n++;

        // Etalons ir noapaļots līdz 8 zīmēm — salīdzinām tādā pašā pierakstā.
        $sLat = sprintf('%.8f', $lat);
        $sLon = sprintf('%.8f', $lon);
        if ($sLat === sprintf('%.8f', $rLat) && $sLon === sprintf('%.8f', $rLon)) { $exact++; return; }

        // Novirze metros (Latvijas platumā 1° lat ≈ 111 320 m, 1° lon ≈ 60 800 m)
        $dy = ($lat - $rLat) * 111320.0;
        $dx = ($lon - $rLon) * 111320.0 * cos(deg2rad($rLat));
        $d  = sqrt($dx * $dx + $dy * $dy);

        if     ($d <= 0.001) $buckets['≤1mm']++;
        elseif ($d <= 0.01)  $buckets['≤1cm']++;
        elseif ($d <= 0.10)  $buckets['≤10cm']++;
        elseif ($d <= 1.0)   $buckets['≤1m']++;
        else {
            $buckets['>1m']++;
            if (count($examples) < 10) $examples[$bid] = sprintf('%.2f m', $d);
        }
        if ($d > $worst) { $worst = $d; $worstBid = $bid; }
    });

$sec = microtime(true) - $t0;

// ── Atskaite ────────────────────────────────────────────────────────────────
ie_say('');
ie_say(str_repeat('═', 62));
ie_say(sprintf('Salīdzinātas ēkas:      %d', $n));
ie_say(sprintf('Identiski līdz 8 zīmēm: %d (%.4f%%)', $exact, $n ? 100 * $exact / $n : 0));
ie_say(sprintf('Atšķiras:               %d', $n - $exact));
foreach ($buckets as $k => $v) if ($v) ie_say(sprintf('   %-6s %d', $k, $v));
if ($worstBid !== '') ie_say(sprintf('Lielākā novirze:        %.4f m (ēka %s)', $worst, $worstBid));
if ($examples) {
    ie_say('Piemēri virs 1 m:');
    foreach ($examples as $bid => $d) ie_say("   $bid  $d");
}
ie_say(sprintf('Etalonā, bet GML bez centroīda: %d', $stat['matched'] - $stat['emitted']));
ie_say(sprintf('Laiks: %.0fs (%d ēkas skenētas)', $sec, $stat['scanned']));
ie_say(str_repeat('═', 62));
