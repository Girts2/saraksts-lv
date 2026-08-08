<?php
/**
 * out-summ-level.csv salīdzinājums pa ēkām (PHP pret Python).
 *
 * rows_diff.php pasaka tikai "atšķiras". Šeit vajag zināt CIK — 3. solī ir viena
 * zināma atkāpe (atlikuma sadale neizšķirtajos daļskaitļos), un jautājums ir, cik
 * ēkas tā skar un par cik cilvēkiem. Salīdzina pēc kadastra atslēgas, nevis rindu
 * pret rindu, tāpēc pārbīdes neizkropļo rezultātu.
 *
 *   php tools/summ_level_diff.php PHP.csv PY.csv [--show=10]
 */
declare(strict_types=1);
require_once __DIR__ . '/../common.php';

$args = array_values(array_filter(array_slice($argv, 1), static fn($a) => !str_starts_with($a, '--')));
if (count($args) < 2) { fwrite(STDERR, "Lietošana: php summ_level_diff.php PHP.csv PY.csv\n"); exit(2); }
$show = 10;
foreach ($argv as $a) if (str_starts_with($a, '--show=')) $show = (int)substr($a, 7);

function load(string $p): array
{
    $out = [];
    foreach (ie_csv_rows($p) as $r) {
        $out[(string)($r['kadastrs'] ?? '')] = [(int)($r['kopejie_cilveki'] ?? 0), (string)($r['level'] ?? '')];
    }
    return $out;
}

$A = load($args[0]);
$B = load($args[1]);

$onlyA = array_diff_key($A, $B);
$onlyB = array_diff_key($B, $A);

$same = 0; $diffPpl = 0; $diffLvl = 0; $diffBoth = 0;
$sumA = 0; $sumB = 0; $absDelta = 0; $maxDelta = 0; $maxKey = '';
$examples = [];
$lvlShift = [];

foreach ($A as $k => $a) {
    if (!isset($B[$k])) continue;
    $b = $B[$k];
    $sumA += $a[0]; $sumB += $b[0];
    $dp = $a[0] !== $b[0];
    $dl = $a[1] !== $b[1];
    if (!$dp && !$dl) { $same++; continue; }
    if ($dp && $dl) $diffBoth++; elseif ($dp) $diffPpl++; else $diffLvl++;
    $d = abs($a[0] - $b[0]);
    $absDelta += $d;
    if ($d > $maxDelta) { $maxDelta = $d; $maxKey = $k; }
    if ($dl) { $key = $b[1] . '→' . $a[1]; $lvlShift[$key] = ($lvlShift[$key] ?? 0) + 1; }
    if (count($examples) < $show) {
        $examples[] = sprintf('  %s  cilvēki PHP=%d PY=%d   līmenis PHP=%s PY=%s',
            $k, $a[0], $b[0], $a[1], $b[1]);
    }
}

$common = count($A) - count($onlyA);
ie_say(str_repeat('═', 66));
ie_say(sprintf('Ēkas PHP failā:          %d', count($A)));
ie_say(sprintf('Ēkas PY  failā:          %d', count($B)));
ie_say(sprintf('Tikai PHP / tikai PY:    %d / %d', count($onlyA), count($onlyB)));
ie_say(sprintf('Pilnībā identiskas:      %d (%.4f%% no kopīgajām)',
    $same, $common ? 100 * $same / $common : 0));
ie_say(sprintf('Atšķiras tikai cilvēki:  %d', $diffPpl));
ie_say(sprintf('Atšķiras tikai līmenis:  %d', $diffLvl));
ie_say(sprintf('Atšķiras abi:            %d', $diffBoth));
ie_say(sprintf('Iedzīvotāju kopsumma:    PHP %d / PY %d  (starpība %+d)', $sumA, $sumB, $sumA - $sumB));
if ($absDelta) {
    ie_say(sprintf('Absolūtā novirze kopā:   %d cilvēki; lielākā vienā ēkā %d (%s)',
        $absDelta, $maxDelta, $maxKey));
}
if ($lvlShift) {
    ksort($lvlShift);
    ie_say('Līmeņu pārbīdes (PY→PHP):');
    foreach ($lvlShift as $k => $n) ie_say(sprintf('   %-8s %d', $k, $n));
}
if ($examples) { ie_say('Piemēri:'); foreach ($examples as $e) ie_say($e); }
ie_say(str_repeat('═', 66));

exit($same === $common && !$onlyA && !$onlyB ? 0 : 1);
