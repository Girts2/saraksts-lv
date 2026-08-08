<?php
/**
 * Rindu zelta-diff: PHP sausā režīma izvade pret Python pyshim izvadi.
 *
 * Abus failus lasa ar vienu un to pašu parsētāju, tāpēc pēdiņu likšanas
 * atšķirības (PHP 8.4 liek pēdiņas ap laukiem ar atstarpēm galos, Python csv ne)
 * nerada viltus neatbilstības — salīdzina VĒRTĪBAS, ne baitus.
 *
 *   php tools/rows_diff.php PHP.csv PY.csv [--show=10] [--skip-b=1]
 *
 * Pēc noklusējuma galveni izlaiž TIKAI pirmajam failam (pyshim izvadē tās nav).
 * Ja arī otram failam ir galvene — piem. salīdzinot divus out-all.csv —, lieto
 * --skip-b=1, citādi viss pārbīdās par vienu rindu.
 *
 * Izejas kods 0 = identiski, 1 = atšķiras.
 */
declare(strict_types=1);

$args = array_values(array_filter(array_slice($argv, 1), static fn($a) => !str_starts_with($a, '--')));
if (count($args) < 2) {
    fwrite(STDERR, "Lietošana: php rows_diff.php PHP.csv PY.csv [--show=N]\n");
    exit(2);
}
[$aPath, $bPath] = $args;
$show = 10;
foreach ($argv as $a) if (str_starts_with($a, '--show=')) $show = (int)substr($a, 7);

foreach ([$aPath, $bPath] as $p) {
    if (!is_file($p)) { fwrite(STDERR, "Nav faila: $p\n"); exit(2); }
}

$fa = fopen($aPath, 'r');
$fb = fopen($bPath, 'r');

// PHP pusē pirmā rinda ir kolonnu nosaukumi, pyshim pusē tādas nav.
$head = fgetcsv($fa, 0, ',', '"', '');
foreach ($argv as $a) {
    if (str_starts_with($a, '--skip-b=')) {
        for ($i = (int)substr($a, 9); $i > 0; $i--) fgetcsv($fb, 0, ',', '"', '');
    }
}

$line = 0; $bad = 0; $extraA = 0; $extraB = 0;
$diffs = [];

while (true) {
    $ra = fgetcsv($fa, 0, ',', '"', '');
    $rb = fgetcsv($fb, 0, ',', '"', '');

    if ($ra === false && $rb === false) break;
    if ($ra === false) { $extraB++; while (fgetcsv($fb, 0, ',', '"', '') !== false) $extraB++; break; }
    if ($rb === false) { $extraA++; while (fgetcsv($fa, 0, ',', '"', '') !== false) $extraA++; break; }

    $line++;
    $ra = array_map(static fn($v) => (string)$v, $ra);
    $rb = array_map(static fn($v) => (string)$v, $rb);

    if ($ra !== $rb) {
        $bad++;
        if (count($diffs) < $show) {
            $cols = [];
            $n = max(count($ra), count($rb));
            for ($i = 0; $i < $n; $i++) {
                $x = $ra[$i] ?? '<nav>'; $y = $rb[$i] ?? '<nav>';
                if ($x !== $y) $cols[] = sprintf('%s: PHP=%s PY=%s', $head[$i] ?? "#$i", $x, $y);
            }
            $diffs[] = "  rinda $line — " . implode('; ', $cols);
        }
    }
}
fclose($fa); fclose($fb);

$name = basename($aPath);
if ($bad === 0 && $extraA === 0 && $extraB === 0) {
    echo sprintf("✓ %-24s %d rindas identiskas\n", $name, $line);
    exit(0);
}

echo sprintf("✗ %-24s %d rindas, %d atšķiras", $name, $line, $bad);
if ($extraA) echo ", PHP pusē vēl $extraA";
if ($extraB) echo ", PY pusē vēl $extraB";
echo "\n";
foreach ($diffs as $d) echo $d, "\n";
exit(1);
