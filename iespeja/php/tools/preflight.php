<?php
/**
 * Iespējas konveijera GATAVĪBAS PĀRBAUDE mērķa serverim.
 *
 * Palaid to uz servera PIRMS konveijera izvietošanas. Tas neko nemaina un neko
 * nelejupielādē — tikai pārbauda, vai vide izpilda konveijera prasības, un pasaka
 * konkrēti, kas trūkst.
 *
 *   php tools/preflight.php              — pilna pārbaude
 *   php tools/preflight.php --no-db      — izlaiž MySQL pieslēgumu
 *   php tools/preflight.php --net        — pārbauda arī ārējos avotus (lēnāk)
 *
 * Izejas kods 0 = viss kārtībā, 1 = ir kritiskas problēmas.
 */
declare(strict_types=1);

$noDb = in_array('--no-db', $argv, true);
$net  = in_array('--net', $argv, true);

$fails = 0; $warns = 0;

function head(string $s): void { echo "\n", $s, "\n", str_repeat('─', 62), "\n"; }
function ok(string $s, string $v = ''): void   { printf("  [ OK ]  %-34s %s\n", $s, $v); }
function bad(string $s, string $v = ''): void  { global $fails; $fails++; printf("  [KĻŪDA] %-34s %s\n", $s, $v); }
function warn(string $s, string $v = ''): void { global $warns; $warns++; printf("  [BRĪDI] %-34s %s\n", $s, $v); }

echo str_repeat('═', 62), "\n  IESPĒJA — vides gatavības pārbaude\n", str_repeat('═', 62), "\n";

// ── 1. PHP ──────────────────────────────────────────────────────────────────
head('1. PHP');

// `never` atgriezes tips (ie_fail) prasa 8.1; zem tā kods pat neparsējas.
if (PHP_VERSION_ID >= 80100) ok('versija', PHP_VERSION);
else bad('versija', PHP_VERSION . ' — vajag vismaz 8.1 (`never` atgriezes tips)');

if (PHP_INT_SIZE >= 8) ok('64-bitu veseli skaitļi', PHP_INT_SIZE * 8 . ' biti');
else bad('64-bitu veseli skaitļi', '32 biti — OSM id un PBF varint pārpildīsies');

if (PHP_SAPI === 'cli') ok('SAPI', 'cli');
else warn('SAPI', PHP_SAPI . ' — konveijers domāts komandrindai/cron');

// ── 2. Paplašinājumi ────────────────────────────────────────────────────────
head('2. Paplašinājumi');
$need = [
    'zlib'      => 'PBF blobu atspiešana (bez tā .pbf nav lasāms)',
    'pdo_mysql' => 'ierakstīšana MySQL',
    'xmlreader' => 'Building.gml straumēšana (4., 6., 8. solis)',
    'curl'      => 'lejupielādes',
    'mbstring'  => 'nosaukumu griešana pēc rakstzīmēm',
    'json'      => 'konfigurācija un keši',
];
$optional = [
    'pdo_sqlite' => '3. solis (kadastra apstrāde)',
    'zip'        => '2. un 4. solis (arhīvu atvēršana)',
    'intl'       => 'kārtošana pēc latviešu alfabēta',
];
foreach ($need as $e => $why) {
    extension_loaded($e) ? ok($e, $why) : bad($e, "TRŪKST — $why");
}
foreach ($optional as $e => $why) {
    extension_loaded($e) ? ok($e, $why) : warn($e, "trūkst — $why");
}

// ── 3. Limiti ───────────────────────────────────────────────────────────────
head('3. Izpildes limiti');

$ml = ini_get('memory_limit');
@ini_set('memory_limit', '512M');
$mlAfter = ini_get('memory_limit');
if ($mlAfter === '-1' || $mlAfter === '512M' || (int)$mlAfter >= 512) {
    ok('memory_limit', "$ml → $mlAfter (ceļams)");
} else {
    bad('memory_limit', "$ml, nepaceļas ($mlAfter) — 3. un 4. solis prasa ~512M");
}

$mt = (int)ini_get('max_execution_time');
if ($mt === 0) ok('max_execution_time', 'bez limita');
else warn('max_execution_time', "{$mt}s — 3./4. solis ir garāks; cron vidē parasti 0");

// PBF lasītājam vajag reālu ātrumu; šis ir aptuvens CPU mērs.
$t = microtime(true);
$x = 0; for ($i = 0; $i < 3000000; $i++) { $x += $i % 7; }
$cpu = microtime(true) - $t;
printf("  [INFO]  %-34s %.2fs uz 3M cikliem\n", 'CPU relatīvais ātrums', $cpu);
if ($cpu > 1.5) warn('CPU', 'lēns — PBF izvilkšana būs vairākas minūtes, ne sekundes');

// ── 4. Disks ────────────────────────────────────────────────────────────────
head('4. Diska vieta');
$dir = getenv('IESPEJA_DATA_DIR') ?: getcwd();
$free = @disk_free_space($dir);
if ($free === false) {
    warn('brīvā vieta', 'nevar noteikt');
} else {
    $gb = $free / 1073741824;
    printf("  [%s]  %-34s %.1f GB (%s)\n", $gb >= 20 ? ' OK ' : 'KĻŪDA',
        'brīvā vieta', $gb, $dir);
    if ($gb < 20) { $fails++; echo "          Pilnai ķēdei vajag ~10 GB (Building.gml 5,5 GB + arhīvi).\n"; }
    elseif ($gb < 60) warn('vietas rezerve', 'pietiek Latvijai, lielai valstij ne');
}
if (is_writable($dir)) ok('rakstāma datu mape', $dir);
else bad('rakstāma datu mape', "$dir nav rakstāma");

// ── 5. Konfigurācija un shēma ───────────────────────────────────────────────
head('5. Konfigurācija');
$base = dirname(__DIR__);
foreach (['config.php', 'schema.php', 'pbf.php', 'common.php'] as $f) {
    is_file("$base/$f") ? ok($f) : bad($f, 'TRŪKST');
}
if ($fails === 0 || is_file("$base/schema.php")) {
    require_once "$base/schema.php";
    try {
        $c = ie_country();
        ok('valsts profils', $c['name'] . ' (' . IE_COUNTRY . ')');
        ok('tabulas', implode(', ', ie_all_tables()));
        $src = $c['osm']['source'] ?? 'overpass';
        ok('OSM avots', $src . ($src === 'pbf' ? '  ' . ($c['osm']['pbf_url'] ?? '') : ''));
    } catch (Throwable $e) {
        bad('valsts profils', $e->getMessage());
    }
}

// ── 6. MySQL ────────────────────────────────────────────────────────────────
if (!$noDb) {
    head('6. MySQL');
    require_once "$base/config.php";
    $cfg = ie_config();
    try {
        $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4",
            $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
        ok('pieslēgums', "{$cfg['host']}/{$cfg['name']}");

        $ver = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        ok('serveris', $ver);

        // Telpiskās funkcijas — bez tām lapa nestrādā vispār.
        try {
            $pdo->query("SELECT ST_Distance_Sphere(ST_GeomFromText('POINT(24 56)',4326), ST_GeomFromText('POINT(25 57)',4326))")->fetchColumn();
            ok('ST_Distance_Sphere', 'ir');
        } catch (PDOException $e) { bad('ST_Distance_Sphere', 'NAV — telpiskie vaicājumi nestrādās'); }

        // SPATIAL indekss uz POINT NOT NULL
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ie_preflight_tmp (
                id INT AUTO_INCREMENT PRIMARY KEY, location POINT NOT NULL,
                SPATIAL INDEX (location)) ENGINE=InnoDB');
            $pdo->exec("INSERT INTO ie_preflight_tmp (location) VALUES (ST_PointFromText('POINT(24 56)',4326))");
            $pdo->query("SELECT COUNT(*) FROM ie_preflight_tmp WHERE MBRContains(
                ST_GeomFromText('POLYGON((23 55,25 55,25 57,23 57,23 55))',4326), location)")->fetchColumn();
            $pdo->exec('DROP TABLE ie_preflight_tmp');
            ok('SPATIAL indekss + MBRContains', 'strādā');
        } catch (PDOException $e) {
            bad('SPATIAL indekss', substr($e->getMessage(), 0, 60));
            @$pdo->exec('DROP TABLE IF EXISTS ie_preflight_tmp');
        }

        $mx = (int)$pdo->query("SELECT @@max_allowed_packet")->fetchColumn();
        if ($mx >= 4 * 1048576) ok('max_allowed_packet', round($mx / 1048576) . ' MB');
        else warn('max_allowed_packet', round($mx / 1048576) . ' MB — pakešu ievietošana var krist');

        $wt = (int)$pdo->query("SELECT @@wait_timeout")->fetchColumn();
        printf("  [INFO]  %-34s %ds\n", 'wait_timeout', $wt);
    } catch (PDOException $e) {
        bad('pieslēgums', substr($e->getMessage(), 0, 70));
    }
}

// ── 7. Tīkls ────────────────────────────────────────────────────────────────
if ($net) {
    head('7. Ārējie avoti');
    $urls = [
        'Geofabrik (PBF)'   => 'https://download.geofabrik.de/europe/latvia-latest.osm.pbf',
        'data.gov.lv'       => 'https://data.gov.lv/dati/lv/',
        'kadastrs.lv Atom'  => 'https://grafws.kadastrs.lv/atom/bu/atom_Building.xml',
        'Overpass (rezerve)'=> 'https://overpass-api.de/api/status',
    ];
    foreach ($urls as $name => $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20, CURLOPT_RETURNTRANSFER => true]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        unset($ch);
        if ($code >= 200 && $code < 400) ok($name, "HTTP $code");
        elseif ($name === 'Overpass (rezerve)') warn($name, $err ?: "HTTP $code");
        else bad($name, $err ?: "HTTP $code");
    }
} else {
    echo "\n  (ārējos avotus nepārbaudīju — pievieno --net)\n";
}

// ── Kopsavilkums ────────────────────────────────────────────────────────────
echo "\n", str_repeat('═', 62), "\n";
if ($fails === 0 && $warns === 0)      echo "  VISS KĀRTĪBĀ — konveijeru var palaist.\n";
elseif ($fails === 0)                  echo "  DERĪGS ar $warns brīdinājumu(-iem) — skat. augstāk.\n";
else                                   echo "  NEDERĪGS: $fails kritiska(-s) problēma(-s), $warns brīdinājums(-i).\n";
echo str_repeat('═', 62), "\n";
exit($fails === 0 ? 0 : 1);
