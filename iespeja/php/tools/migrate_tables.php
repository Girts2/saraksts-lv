<?php
/**
 * VIENREIZĒJA migrācija: vecie tabulu nosaukumi → jaunā <valsts>_* shēma.
 *
 *   buildings_geo            → lv_buildings      (eka→building_id, cilveki→residents, level→lvl)
 *   offices_geo              → lv_offices        (eka→building_id)
 *   institutions_geo         → lv_institutions   (eka→building_id)
 *   tourism_geo              → lv_tourism        (kolonnas tās pašas, + ix_ttype indekss)
 *   mydb_cofe          → lv_poi  ptype='cafe'
 *   mydb_food          → lv_poi  ptype='restaurant'
 *   mydb_bar           → lv_poi  ptype='bar'
 *   mydb_frizieri      → lv_poi  ptype='hairdresser'
 *   mydb_<pārējie 7>   → lv_poi  ptype=<tas pats vārds>
 *
 * Kopē ar INSERT…SELECT tieši serverī — 397k rindas dažās sekundēs, dati neceļo
 * uz klientu. VECĀS TABULAS NETIEK DZĒSTAS: kad jaunā lapa ir pārbaudīta, tās
 * nomet ar roku (DROP saraksts izdrukājas beigās). Atkārtota palaišana ir droša —
 * jau aizpildītu mērķi izlaiž.
 *
 * Ja valstij reģionu ir vairāk par vienu, ēku slāni šis rīks NEmigrē (rindas
 * būtu jāmaršrutē pa šķēlumiem pa vienai) — tad ēkas ielādē ar step5 pa jaunam.
 *
 *   php tools/migrate_tables.php            — dara
 *   php tools/migrate_tables.php --check    — tikai parāda, kas tiktu darīts
 */
declare(strict_types=1);
require_once __DIR__ . '/../common.php';

$check = in_array('--check', $argv, true);
$t0  = ie_start('Migrācija uz <valsts>_* shēmu' . ($check ? ' (tikai pārbaude)' : ''));
$pdo = ie_db();
$c   = ie_config();
ie_say("DB: {$c['host']}:{$c['port']} / {$c['name']};  valsts: " . IE_COUNTRY);

/** Vai tabula eksistē? */
function mt_exists(PDO $pdo, string $t): bool
{
    $q = $pdo->prepare('SELECT 1 FROM information_schema.tables
                        WHERE table_schema = DATABASE() AND table_name = ?');
    $q->execute([$t]);
    return (bool)$q->fetchColumn();
}

function mt_count(PDO $pdo, string $t): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}

/**
 * Viena kopēšana: izveido mērķi (DDL), pārnes rindas, salīdzina skaitu.
 * @param string $select SELECT daļa ar kolonnu pārdēvēšanu vecajā tabulā
 */
function mt_copy(PDO $pdo, bool $check, string $old, string $new,
                 string $ddl, string $cols, string $select, string $where = ''): void
{
    if (!mt_exists($pdo, $old)) { ie_say(sprintf('   %-24s nav avota — izlaista', $old)); return; }
    $n = mt_count($pdo, $old);

    if ($check) { ie_say(sprintf('   %-24s → %-18s %8d rindas', $old, $new, $n)); return; }

    $pdo->exec($ddl);
    $sql = "INSERT INTO `$new` ($cols) SELECT $select FROM `$old`" . ($where ? " WHERE $where" : '');
    $pdo->exec($sql);
    ie_say(sprintf('   %-24s → %-18s %8d rindas', $old, $new, $n));
}

$regions = ie_regions();
$tBld  = ie_table('buildings', $regions[0]['code']);
$tOff  = ie_table('offices');
$tInst = ie_table('institutions');
$tTour = ie_table('tourism');
$tPoi  = ie_table('poi');

// ── Plūsmas slāņi ───────────────────────────────────────────────────────────
if (count($regions) > 1) {
    ie_say('   ĒKU SLĀNIS IZLAISTS: reģionu > 1, ielādē ar step5_upload.php');
} elseif (!$check && mt_exists($pdo, $tBld) && mt_count($pdo, $tBld) > 0) {
    ie_say(sprintf('   %-24s jau aizpildīta — izlaista', $tBld));
} else {
    mt_copy($pdo, $check, 'buildings_geo', $tBld, "
        CREATE TABLE IF NOT EXISTS `$tBld` (
            `building_id` VARCHAR(32) NOT NULL,
            `residents` VARCHAR(50) NULL,
            `lvl` VARCHAR(10) NULL,
            `location` POINT NOT NULL,
            SPATIAL INDEX `ix_loc` (`location`),
            PRIMARY KEY (`building_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        '`building_id`,`residents`,`lvl`,`location`',
        '`eka`,`cilveki`,`level`,`location`');
}

$skipFilled = static function (string $t) use ($pdo, $check): bool {
    if (!$check && mt_exists($pdo, $t) && mt_count($pdo, $t) > 0) {
        ie_say(sprintf('   %-24s jau aizpildīta — izlaista', $t));
        return true;
    }
    return false;
};

if (!$skipFilled($tOff)) {
    mt_copy($pdo, $check, 'offices_geo', $tOff, "
        CREATE TABLE IF NOT EXISTS `$tOff` (
            `building_id` VARCHAR(32) NOT NULL, `workers` INT NULL, `lvl` CHAR(1) NULL,
            `location` POINT NOT NULL,
            SPATIAL INDEX `ix_loc` (`location`), PRIMARY KEY(`building_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        '`building_id`,`workers`,`lvl`,`location`',
        '`eka`,`workers`,`lvl`,`location`');
}
if (!$skipFilled($tInst)) {
    mt_copy($pdo, $check, 'institutions_geo', $tInst, "
        CREATE TABLE IF NOT EXISTS `$tInst` (
            `building_id` VARCHAR(32) NOT NULL, `people` INT NULL, `cat` VARCHAR(20) NULL,
            `location` POINT NOT NULL,
            SPATIAL INDEX `ix_loc` (`location`), PRIMARY KEY(`building_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        '`building_id`,`people`,`cat`,`location`',
        '`eka`,`people`,`cat`,`location`');
}
if (!$skipFilled($tTour)) {
    mt_copy($pdo, $check, 'tourism_geo', $tTour, "
        CREATE TABLE IF NOT EXISTS `$tTour` (
            `osm_id` BIGINT NOT NULL, `score` FLOAT NULL, `ttype` VARCHAR(30) NULL,
            `tname` VARCHAR(255) NULL, `location` POINT NOT NULL,
            SPATIAL INDEX `ix_loc` (`location`), PRIMARY KEY(`osm_id`),
            INDEX `ix_ttype` (`ttype`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        '`osm_id`,`score`,`ttype`,`tname`,`location`',
        '`osm_id`,`score`,`ttype`,`tname`,`location`');
}

// ── POI: 11 tabulas → viena ar ptype ────────────────────────────────────────
// Vecais tabulas vārds → jaunais ptype. Prefikss šeit ir ierakstīts APZINĀTI:
// tas ir vēsturiskais Hostinger konta prefikss, kas pēc migrācijas pazūd pavisam,
// tāpēc to nav vērts nest konfigurācijā.
$poiMap = [
    'mydb_cofe'       => 'cafe',
    'mydb_food'       => 'restaurant',
    'mydb_bar'        => 'bar',
    'mydb_frizieri'   => 'hairdresser',
    'mydb_bakery'     => 'bakery',
    'mydb_pharmacy'   => 'pharmacy',
    'mydb_beauty'     => 'beauty',
    'mydb_minimarket' => 'minimarket',
    'mydb_dentist'    => 'dentist',
    'mydb_fastfood'   => 'fastfood',
    'mydb_fitness'    => 'fitness',
];

if (!$check) ie_poi_create($pdo);
foreach ($poiMap as $old => $ptype) {
    if (!mt_exists($pdo, $old)) { ie_say(sprintf('   %-24s nav avota — izlaista', $old)); continue; }
    if (!$check) {
        // Atkārtojamība pa tipiem: ja ptype jau ir iekšā, neduplicē.
        $q = $pdo->prepare("SELECT COUNT(*) FROM `$tPoi` WHERE `ptype` = ?");
        $q->execute([$ptype]);
        if ((int)$q->fetchColumn() > 0) {
            ie_say(sprintf('   %-24s ptype=%-12s jau ir — izlaista', $old, $ptype));
            continue;
        }
        $ins = $pdo->prepare("INSERT INTO `$tPoi` (`ptype`,`name`,`location`)
                              SELECT ?, `name`, `location` FROM `$old`");
        $ins->execute([$ptype]);
    }
    ie_say(sprintf('   %-24s → %s ptype=%-12s %6d rindas',
        $old, $tPoi, $ptype, mt_count($pdo, $old)));
}

// ── Kopsavilkums ────────────────────────────────────────────────────────────
if (!$check) {
    ie_say('');
    ie_say('Jauno tabulu rindas:');
    foreach (ie_all_tables() as $t) {
        ie_say(sprintf('   %-20s %8d', $t, mt_exists($pdo, $t) ? mt_count($pdo, $t) : 0));
    }
    ie_say('');
    ie_say('Vecās tabulas NAV dzēstas. Kad lapa pārbaudīta, palaid ar roku:');
    $olds = array_merge(['buildings_geo', 'offices_geo', 'institutions_geo', 'tourism_geo'],
                        array_keys($poiMap));
    $olds = array_filter($olds, static fn($t) => mt_exists($pdo, $t));
    ie_say('   DROP TABLE ' . implode(', ', $olds) . ';');
}
ie_done($t0);
