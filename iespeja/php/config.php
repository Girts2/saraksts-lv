<?php
/**
 * Iespēja/php/config.php — VIENĪGĀ vieta, kur glabājas pieslēguma dati.
 *
 * Python versijā tie bija sešos failos atsevišķi (5 Upload.py, 6 Offices.py,
 * 7 Tourism.py, 8 Iestades.py, 9 Konkurenti-OSM.py, tools/calibrate_new.py), un
 * README to pats sauc par pirmo lietu, kas jāsalabo pārtaisot. Šeit tā ir viena.
 *
 * ŠEIT IR TIKAI NOSLĒPUMI. Tabulu nosaukumi, reģioni un kalibrācija dzīvo
 * schema.php un countries/<kods>.php — tos drīkst iekļaut arī publiskā lapa,
 * šo failu ne.
 *
 * Prioritāte: vides mainīgie → config.local.php → zemāk esošie noklusējumi.
 * Uz servera vēlams likt vides mainīgos vai config.local.php; noklusējumi der
 * izstrādei. Publiskajā pakotnē tools/build_download.php šos aizstāj ar vietturiem.
 */
declare(strict_types=1);

function ie_config(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $cfg = [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'mydb',
        'user' => 'mydb',
        'pass' => '',
    ];

    $local = __DIR__ . '/config.local.php';
    if (is_file($local)) {
        $over = require $local;
        if (is_array($over)) $cfg = array_merge($cfg, $over);
    }

    $env = [
        'host' => 'IESPEJA_DB_HOST', 'port' => 'IESPEJA_DB_PORT',
        'name' => 'IESPEJA_DB_NAME', 'user' => 'IESPEJA_DB_USER',
        'pass' => 'IESPEJA_DB_PASS',
    ];
    foreach ($env as $key => $var) {
        $v = getenv($var);
        if ($v !== false && $v !== '') $cfg[$key] = $v;
    }
    $cfg['port'] = (int)$cfg['port'];

    return $cache = $cfg;
}
