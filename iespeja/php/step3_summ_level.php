<?php
/**
 * 3. solis — VZD kadastra atvērtie dati → iedzīvotāji un līmenis pa ēkām.
 * ("3 SUMM-LEVEL.py" ports)
 *
 * 1. daļa: četri ZIP arhīvi no data.gov.lv → četri CSV starpfaili.
 * 2. daļa: analīze → out-summ-level.csv (kadastrs, kopejie_cilveki, level).
 *
 * KĀPĒC SQLITE, NEVIS MASĪVI. Python versija visu darīja ar pandas DataFrame.
 * Tiešs ports uz PHP masīviem prasītu vairākus GB atmiņas (adrešu fails vien ir
 * 2,7 M rindu) un būtu rindiņu pa rindiņai pārrakstīta pandas semantika — tieši
 * tur klusās kļūdas arī rodas. Visas apvienošanas, grupēšanas un kārtošanas te
 * dara SQLite; PHP kodā paliek tikai tas, ko SQL neprot: iedzīvotāju sadale ar
 * lielākā atlikuma metodi un svērtās kvantiles.
 *
 * VIENA ZINĀMA ATKĀPE — UN TĀ NĀK NO ORIĢINĀLA, NE NO PORTA.
 *
 * assign_real_population atlikumu izdala pa vienam tiem īpašumiem, kam lielākā
 * daļskaitļa daļa (pandas sort_values, noklusējuma kind='quicksort'). Bet vienā
 * vietā daļskaitlim ir tikai tik daudz dažādu vērtību, cik ir virtuālo cilvēku
 * pakāpju — Rīgā 353 139 īpašumiem ir 7 dažādas frakcijas, un lielākajā blokā ir
 * 209 074 īpašumi ar LĪDZ BITAM vienādu vērtību. Kuri no tiem dabū +1, izlemj
 * introsort iekšējā permutācija. Tā nav ne ievades, ne apgrieztā secība, un PHP
 * to atkārtot nevar.
 *
 * Cik tas maksā, ir izmērīts. Palaižot PAŠU PYTHON ar vienīgo izmaiņu
 * kind='mergesort', no 338 050 ēkām atšķiras 81 618 (24 %) — tikpat, cik starp
 * PHP un Python. Citiem vārdiem, oriģināla izvade šajā vietā ir nestabila pati
 * pret sevi. Pret Python ar stabilu šķirošanu šis ports sakrīt PILNĪGI:
 * iedzīvotāju skaits identisks visām 338 050 ēkām, kopsumma identiska, atšķiras
 * 4 ēku līmenis (kvantiles slieksnis peldošā punkta robežā).
 *
 * Šeit neizšķirtos kārto pēc sākotnējās CSV rindas — tas ir tieši tas, ko dod
 * pandas stabilā dilstošā šķirošana, un rezultāts ir atkārtojams.
 * Salīdzinājumu atkārto tools/summ_level_diff.php.
 *
 * Lietošana:
 *   php step3_summ_level.php                — pilns cikls
 *   php step3_summ_level.php --skip-download — lieto jau esošos četrus CSV
 *   IESPEJA_ZIP_CACHE=/mape                 — glabā/lieto lejupielādētos arhīvus
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

const TARGET_KODS   = [1110, 1121, 1122];
const LEVEL_TO_SCORE = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'E' => 1];

/** Iedzīvotāju skaits pa vietām (TSV, kā Python avotā). */
const POPULATION_RAW = <<<'TSV'
Rīga	605273
Daugavpils	77799
Jelgava	54701
Jēkabpils	21150
Jūrmala	52154
Liepāja	66680
Ogre	22767
Rēzekne	26131
Valmiera	22376
Ventspils	32634
Aizkraukles novads	28618
Aizkraukle	6853
Jaunjelgava	1713
Koknese	2427
Pļaviņas	2823
Alūksnes novads	13059
Alūksne	6175
Augšdaugavas novads	24361
Ilūkste	2082
Subate	549
Ādažu novads	23281
Ādaži	7535
Balvu novads	17910
Balvi	5584
Viļaka	1172
Bauskas novads	40906
Bauska	9811
Iecava	5343
Cēsu novads	40943
Cēsis	14699
Līgatne	1009
Dienvidkurzemes novads	32708
Aizpute	3892
Durbe	483
Grobiņa	3593
Pāvilosta	851
Priekule	1810
Dobeles novads	27474
Auce	2136
Dobele	8589
Gulbenes novads	18740
Gulbene	6715
Jelgavas novads	32053
Jēkabpils novads	39276
Aknīste	917
Viesīte	1510
Krāslavas novads	19833
Dagda	1805
Krāslava	6854
Kuldīgas novads	26956
Kuldīga	9940
Skrunda	1767
Ķekavas novads	31303
Baldone	3711
Baloži	6846
Ķekava	5039
Limbažu novads	27852
Ainaži	644
Aloja	1060
Limbaži	6613
Salacgrīva	2480
Staicele	766
Līvānu novads	10215
Līvāni	6790
Ludzas novads	20745
Kārsava	1843
Ludza	7524
Zilupe	1271
Madonas novads	27255
Cesvaine	1210
Lubāna	1453
Madona	6561
Mārupes novads	37025
Mārupe	16544
Ogres novads	57689
Ikšķile	7448
Ķegums	2059
Lielvārde	5853
Olaines novads	20658
Olaine	9908
Preiļu novads	15768
Preiļi	5841
Rēzeknes novads	28305
Viļāni	2749
Ropažu novads	35178
Vangaži	3192
Salaspils novads	23694
Salaspils	17826
Saldus novads	26320
Brocēni	2834
Saldus	9553
Saulkrastu novads	9926
Saulkrasti	3149
Siguldas novads	31469
Sigulda	14632
Smiltenes novads	17697
Ape	777
Smiltene	5129
Talsu novads	34675
Sabile	1369
Stende	1532
Talsi	8649
Valdemārpils	1135
Tukuma novads	43641
Kandava	3276
Tukums	16318
Valkas novads	7501
Valka	4564
Valmieras novads	50283
Mazsalaca	1113
Rūjiena	2650
Seda	1092
Strenči	957
Varakļānu novads	2890
Varakļāni	1653
Ventspils novads	10303
Piltene	821
TSV;

// ── Palīgi ──────────────────────────────────────────────────────────────────

function s3_population_map(): array
{
    $out = [];
    foreach (explode("\n", trim(POPULATION_RAW)) as $line) {
        if (trim($line) === '') continue;
        $parts = explode("\t", $line);
        if (count($parts) !== 2) continue;
        $loc = trim($parts[0]);
        // NFKD + atstarpju izmešana: skaitļos mēdz būt nedalāmās atstarpes
        $num = class_exists('Normalizer')
            ? (string)Normalizer::normalize($parts[1], Normalizer::FORM_KD)
            : $parts[1];
        $num = trim(str_replace([' ', "\u{00A0}"], '', $num));
        if ($num === '' || !ctype_digit($num)) continue;
        $out[$loc] = (int)$num;
    }
    return $out;
}

/** calculate_virtual_population() — cilvēku skaits pēc telpas platības un koda. */
function s3_virtual_population(?int $kods, ?float $platiba): ?int
{
    if ($kods === null || $platiba === null || is_nan($platiba) || $platiba <= 0) return null;
    if ($kods === 1110 || $kods === 1121) {
        if ($platiba <  30) return 1;
        if ($platiba <=  70) return 2;
        if ($platiba <= 250) return 3;
        if ($platiba <= 400) return 4;
        if ($platiba <= 600) return 5;
        if ($platiba <= 800) return 6;
        return 7;
    }
    if ($kods === 1122) {
        if ($platiba <  25) return 1;
        if ($platiba <=  45) return 2;
        if ($platiba <= 100) return 3;
        if ($platiba <= 200) return 4;
        if ($platiba <= 300) return 5;
        return 6;
    }
    return null;
}

/** calculate_adjustment_factor() — korekcija pēc būvniecības gada un koda. */
function s3_adjustment_factor(?int $gads, ?int $kods): float
{
    $g = 0.0; $k = 0.0;
    if ($gads !== null) {
        if     ($gads >= 1950 && $gads <= 1969) $g = -0.10;
        elseif ($gads >= 1970 && $gads <= 1989) $g = -0.05;
        elseif ($gads >= 2000 && $gads <= 2014) $g =  0.05;
        elseif ($gads >= 2015 && $gads <= 2030) $g =  0.10;
    }
    if ($kods !== null) {
        if     ($kods === 1110 || $kods === 1121) $k =  0.05;
        elseif ($kods === 1122)                   $k = -0.05;
    }
    return $g + $k;
}

/** assign_level() — A–E pēc svērto kvantiļu sliekšņiem. */
function s3_assign_level(?float $value, ?array $th): ?string
{
    if ($value === null || is_nan($value) || $th === null) return null;
    [$q35, $q55, $q75, $q90] = $th;
    if (!is_nan($q35) && $value < $q35) return 'E';
    if (!is_nan($q55) && $value < $q55) return 'D';
    if (!is_nan($q75) && $value < $q75) return 'C';
    if (!is_nan($q90) && $value < $q90) return 'B';
    if (!is_nan($q90)) return 'A';
    return null;
}

function s3_score_to_level(float $s): string
{
    if ($s >= 4.5) return 'A';
    if ($s >= 3.5) return 'B';
    if ($s >= 2.5) return 'C';
    if ($s >= 1.5) return 'D';
    return 'E';
}

/**
 * assign_real_population() — reālo iedzīvotāju sadale grupā (pilsētā/novadā).
 *
 * Proporcionāli virtuālajam skaitam, apakšā nogriežot pie 1, augšā pie kodam
 * atbilstošā maksimuma, un atlikumu izdalot pa vienam tiem, kam lielākā
 * daļskaitļa daļa.
 *
 * @param array $rows saraksts [oi, kods, virtuali]
 * @return array oi → reālais skaits
 */
function s3_assign_real_population(array $rows, ?int $realTotal): array
{
    if ($realTotal === null || $realTotal <= 0) return [];

    $valid = [];
    foreach ($rows as $r) if ($r[2] !== null && $r[2] > 0) $valid[] = $r;
    if (!$valid) return [];

    $virtualSum = 0;
    foreach ($valid as $r) $virtualSum += $r[2];
    if ($virtualSum <= 0) return [];

    $scaling = $realTotal / $virtualSum;

    $alloc = []; $frac = []; $sum = 0;
    foreach ($valid as $i => $r) {
        $initial = $r[2] * $scaling;
        $a = (int)floor($initial);
        if ($a < 1) $a = 1;                         // clip(lower=1)
        $alloc[$i] = $a;
        $frac[$i]  = $initial - floor($initial);
        $sum += $a;
    }

    $remainder = $realTotal - $sum;
    if ($remainder > 0) {
        // Daļskaitlis dilstoši; neizšķirti — pēc sākotnējās rindas (skat. faila galvu)
        $order = array_keys($alloc);
        usort($order, static fn($a, $b) => $frac[$b] <=> $frac[$a] ?: $a <=> $b);
        $n = count($order);
        for ($i = 0; $i < $remainder; $i++) $alloc[$order[$i % $n]]++;
    }

    $out = [];
    foreach ($valid as $i => $r) {
        $max = match ($r[1]) { 1110, 1121 => 7, 1122 => 6, default => PHP_INT_MAX };
        $out[$r[0]] = min($alloc[$i], $max);        // clip(upper=max_limits)
    }
    return $out;
}

/** modify_kadastra_telpa_key() — atslēga cenas meklēšanai otrajā mēģinājumā. */
function s3_modify_key(string $k): ?string
{
    return strlen($k) >= 17 ? substr($k, 0, 11) . substr($k, 14) : null;
}

// ── 1. DAĻA: datu ieguve ────────────────────────────────────────────────────

/**
 * Viena arhīva apstrāde: lejupielāde → XML straume → SQLite → sakārtots CSV.
 * SQLite te ir tikai kārtošanas buferis — 2,7 M adrešu rindu PHP masīvā neietilptu.
 */
function s3_extract(string $url, string $out, string $itemTag, array $fields,
                    array $header, int $sortCols, string $tmpDir): void
{
    $zip = s3_fetch_zip($url, $out, $tmpDir);

    $dbPath = "$tmpDir/extract.sqlite";
    @unlink($dbPath);
    $db = new PDO("sqlite:$dbPath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF; PRAGMA cache_size=-200000');

    $cols = [];
    for ($i = 0; $i < count($fields); $i++) $cols[] = "c$i TEXT";
    $db->exec('CREATE TABLE t (seq INTEGER PRIMARY KEY, ' . implode(',', $cols) . ')');
    $ph = implode(',', array_fill(0, count($fields), '?'));
    $ins = $db->prepare('INSERT INTO t VALUES (NULL,' . $ph . ')');

    $za = new ZipArchive();
    if ($za->open($zip) !== true) ie_fail("nevar atvērt arhīvu: $zip");
    $members = [];
    for ($i = 0; $i < $za->numFiles; $i++) {
        $n = (string)$za->getNameIndex($i);
        if (str_ends_with(strtolower($n), '.xml')) $members[] = $n;
    }
    $za->close();
    ie_say('   ' . count($members) . ' XML dalībnieki arhīvā');

    $db->beginTransaction();
    $total = 0;
    foreach ($members as $mi => $member) {
        $n = ie_xml_items("zip://$zip#$member", $itemTag,
            function (DOMElement $el) use ($ins, $fields, &$total): void {
                $vals = [];
                foreach ($fields as $name => $default) $vals[] = ie_xml_text($el, $name, $default);
                $ins->execute($vals);
                $total++;
            });
        if ((($mi + 1) % 20) === 0 || $mi === count($members) - 1) {
            ie_say(sprintf('   … %d/%d dalībnieki, %d ieraksti', $mi + 1, count($members), $total));
        }
        unset($n);
    }
    $db->commit();

    // Python: sorted(..., key=lambda x: (x[0]) vai (x[0], x[1])); sort ir stabils
    $order = [];
    for ($i = 0; $i < $sortCols; $i++) $order[] = "c$i";
    $order[] = 'seq';
    $sel = $db->query('SELECT ' . implode(',', array_keys(
        array_flip(array_map(static fn($i) => "c$i", range(0, count($fields) - 1)))))
        . ' FROM t ORDER BY ' . implode(',', $order));

    $fh = fopen($out, 'w');
    if ($fh === false) ie_fail("nevar rakstīt: $out");
    ie_fputcsv_py($fh, $header);
    while ($row = $sel->fetch(PDO::FETCH_NUM)) ie_fputcsv_py($fh, $row);
    fclose($fh);

    $db = null;
    @unlink($dbPath);
    ie_say("   ✓ $out — $total ieraksti");
}

/** Arhīva iegūšana ar kešu (IESPEJA_ZIP_CACHE), lai atkārtota palaišana nelejupielādē GB. */
function s3_fetch_zip(string $url, string $out, string $tmpDir): string
{
    $cache = getenv('IESPEJA_ZIP_CACHE');
    if ($cache !== false && $cache !== '') {
        if (!is_dir($cache)) @mkdir($cache, 0775, true);
        $path = rtrim($cache, '/') . '/' . basename(parse_url($url, PHP_URL_PATH) ?: 'arhivs.zip');
        if (is_file($path)) { ie_say('   [keša arhīvs] ' . basename($path)); return $path; }
        ie_say("   Lejupielādēju: {$out}…");
        ie_http_download($url, $path, 1800);
        return $path;
    }
    $path = "$tmpDir/" . basename(parse_url($url, PHP_URL_PATH) ?: 'arhivs.zip');
    ie_say("   Lejupielādēju: {$out}…");
    ie_http_download($url, $path, 1800);
    return $path;
}

/** Vērtību arhīvs — atsevišķi, jo cenas ir ligzdotas un jāgrupē pēc kadastra. */
function s3_extract_valuations(string $url, string $out, string $tmpDir): void
{
    $zip = s3_fetch_zip($url, $out, $tmpDir);

    $dbPath = "$tmpDir/val.sqlite";
    @unlink($dbPath);
    $db = new PDO("sqlite:$dbPath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF; PRAGMA cache_size=-200000');
    $db->exec('CREATE TABLE v (kadastrs TEXT PRIMARY KEY, buve TEXT, univ TEXT, fisk TEXT)');

    /* Python: pirmā NE-"-" buve uzvar; pēdējā NE-"-" cena uzvar.
       IS NOT, NEVIS <>. Python findtext() bez noklusējuma atgriež None, ja elementa
       nav, un `None != '-'` ir PATIESS — tātad vēlāks ieraksts bez cenas pārraksta
       jau atrasto ar tukšumu. SQL `NULL <> '-'` dod NULL, kas nosacījumā ir aplams,
       tāpēc pirmais variants veco vērtību paturēja un 10_…vērtības.csv atšķīrās no
       Python izvades tieši šajās rindās. SQLite `IS NOT` salīdzina null-droši. */
    $ins = $db->prepare('INSERT INTO v VALUES (?,?,?,?)
        ON CONFLICT(kadastrs) DO UPDATE SET
          buve = CASE WHEN v.buve IS \'-\'        THEN excluded.buve ELSE v.buve END,
          univ = CASE WHEN excluded.univ IS NOT \'-\' THEN excluded.univ ELSE v.univ END,
          fisk = CASE WHEN excluded.fisk IS NOT \'-\' THEN excluded.fisk ELSE v.fisk END');

    $za = new ZipArchive();
    if ($za->open($zip) !== true) ie_fail("nevar atvērt arhīvu: $zip");
    $members = [];
    for ($i = 0; $i < $za->numFiles; $i++) {
        $n = (string)$za->getNameIndex($i);
        if (str_ends_with(strtolower($n), '.xml')) $members[] = $n;
    }
    $za->close();
    ie_say('   ' . count($members) . ' XML dalībnieki arhīvā');

    $db->beginTransaction();
    $total = 0;
    foreach ($members as $mi => $member) {
        ie_xml_items("zip://$zip#$member", 'ValuationItemData',
            function (DOMElement $el) use ($ins, &$total): void {
                $kad = ie_xml_text_path($el, 'ObjectRelation', 'ObjectCadastreNr');
                if ($kad === null || $kad === '') return;
                $buve = ie_xml_text_path($el, 'ObjectRelation', 'ObjectType', '-');
                $univ = '-'; $fisk = '-';
                $list = $el->getElementsByTagNameNS('*', 'ValuationDataList')->item(0);
                if ($list instanceof DOMElement) {
                    foreach ($list->getElementsByTagNameNS('*', 'ValuationRowData') as $rd) {
                        $type = ie_xml_text($rd, 'ValueType');
                        $val  = ie_xml_text($rd, 'ObjectCadastralValue');
                        if ($type === 'univ') $univ = $val;
                        elseif ($type === 'fisc') $fisk = $val;
                    }
                }
                $ins->execute([$kad, $buve, $univ, $fisk]);
                $total++;
            });
        if ((($mi + 1) % 20) === 0 || $mi === count($members) - 1) {
            ie_say(sprintf('   … %d/%d dalībnieki, %d ieraksti', $mi + 1, count($members), $total));
        }
    }
    $db->commit();

    $sel = $db->query('SELECT kadastrs, buve, univ, fisk FROM v ORDER BY kadastrs');
    $fh = fopen($out, 'w');
    if ($fh === false) ie_fail("nevar rakstīt: $out");
    ie_fputcsv_py($fh, ['kadastrs', 'buve', 'univ_cena', 'fisk_cena']);
    $n = 0;
    while ($row = $sel->fetch(PDO::FETCH_NUM)) { ie_fputcsv_py($fh, $row); $n++; }
    fclose($fh);

    $db = null;
    @unlink($dbPath);
    ie_say("   ✓ $out — $n kadastra objekti (no $total ierakstiem)");
}

// ── Izpilde ─────────────────────────────────────────────────────────────────

$t0  = ie_start('3. solis — kadastra dati → iedzīvotāji un līmenis pa ēkām');
$dir = ie_out_dir();
$tmp = sys_get_temp_dir() . '/iespeja_s3_' . getmypid();
@mkdir($tmp, 0775, true);

$f5  = "$dir/5_Būves_raksturojošie_dati.csv";
$f6  = "$dir/6_Telpu_grupu_raksturojošie_dati.csv";
$f7  = "$dir/7_Kadastra_objektam_reģistrētās_adreses.csv";
$f10 = "$dir/10_Objekta_novērtējums_un_kadastrālās_vērtības.csv";
$out = "$dir/out-summ-level.csv";

$base = 'https://data.gov.lv/dati/dataset/be841486-4af9-4d38-aa14-6502a2ddb517/resource/';

if (!in_array('--skip-download', $argv, true)) {
    ie_say('');
    ie_say('=== 1. DAĻA: DATU IEGŪŠANA NO DATA.GOV.LV ===');

    ie_say("\n--- Būvju dati ---");
    s3_extract($base . '9fe29b57-07cd-4458-b22c-b0b9f2bc8915/download/building.zip', $f5,
        'BuildingItemData',
        ['ObjectCadastreNr' => '-', 'BuildingCadastreNr' => '-', 'BuildingName' => '-',
         'BuildingUseKindId' => '-', 'BuildingUseKindName' => '-', 'BuildingExploitYear' => '-',
         'BuildingKindId' => '-', 'TotalArea' => '-', 'FlatTotalArea' => '-', 'LivingArea' => '-'],
        ['kadastrs', 'kadastra_telpa', 'buve', 'kods', 'telpa', 'gads', 'apakskods',
         'pilna_platiba', 'ekas_platiba', 'dzivojama_platiba'], 2, $tmp);

    ie_say("\n--- Telpu grupu dati ---");
    s3_extract($base . '5d8b1cfa-1e67-4b77-a6ac-b4e37eba0d7e/download/premisegroup.zip', $f6,
        'PremiseGroupItemData',
        ['ObjectCadastreNr' => '', 'PremiseGroupCadastreNr' => '', 'PremiseGroupUseKindId' => '',
         'PremiseGroupName' => '', 'PremiseGroupUseKindName' => '', 'PremiseGroupArea' => ''],
        ['kadastrs', 'kadastra_telpa', 'kods', 'buve', 'telpa', 'platiba'], 2, $tmp);

    ie_say("\n--- Adrešu dati ---");
    s3_extract($base . '2aeea249-6948-4713-92c2-e01543ea0f33/download/address.zip', $f7,
        'AddressItemData',
        ['ObjectCadastreNr' => '-', 'ObjectType' => '-', 'PostIndex' => '-', 'Town' => '-',
         'County' => '-', 'Parish' => '-', 'Village' => '-', 'Street' => '-', 'House' => '-'],
        ['kadastrs', 'buve', 'pasta_indekss', 'pilseta', 'novads', 'pagasts', 'ciems',
         'iela', 'ekas_Nr'], 1, $tmp);

    ie_say("\n--- Vērtību dati ---");
    s3_extract_valuations($base . '35a2dbfa-e4b9-41d5-88d0-e1393115dcb1/download/valuation.zip',
        $f10, $tmp);
}

// ── 2. DAĻA: analīze ────────────────────────────────────────────────────────
ie_say('');
ie_say('=== 2. DAĻA: IEGŪTO DATU ANALĪZE ===');

foreach ([$f5, $f6, $f7, $f10] as $f) if (!is_file($f)) ie_fail("trūkst ievades faila: $f");

$dbPath = "$tmp/analysis.sqlite";
@unlink($dbPath);
$db = new PDO("sqlite:$dbPath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF; PRAGMA cache_size=-400000;
           PRAGMA temp_store=MEMORY');

// [1/6] atrašanās vietas
ie_say("\n[1/6] Sāk atrašanās vietu noteikšanu…");

$db->exec('CREATE TABLE props (oi INTEGER, kadastrs TEXT, kadastra_telpa TEXT, kods INTEGER, platiba TEXT)');
$ins = $db->prepare('INSERT INTO props VALUES (?,?,?,?,?)');
$db->beginTransaction();
$oi = 0;
foreach (ie_csv_rows($f6) as $row) {
    $k = (string)($row['kods'] ?? '');
    if ($k !== '' && ctype_digit($k) && in_array((int)$k, TARGET_KODS, true)) {
        $ins->execute([$oi, (string)($row['kadastrs'] ?? ''), (string)($row['kadastra_telpa'] ?? ''),
                       (int)$k, (string)($row['platiba'] ?? '')]);
    }
    $oi++;   // original_index = rinda VISĀ failā, arī izfiltrētās (kā pandas indekss)
}
$db->commit();

$db->exec('CREATE TABLE loc (rid INTEGER PRIMARY KEY, kadastrs TEXT, pilseta TEXT, novads TEXT)');
$ins = $db->prepare('INSERT INTO loc VALUES (NULL,?,?,?)');
$db->beginTransaction();
foreach (ie_csv_rows($f7) as $row) {
    $k = (string)($row['kadastrs'] ?? '');
    if (trim($k) === '') continue;
    $ins->execute([$k, (string)($row['pilseta'] ?? ''), (string)($row['novads'] ?? '')]);
}
$db->commit();
$db->exec('CREATE INDEX ix_loc_k ON loc(kadastrs)');

// apply_location_priority: derīga pilsēta uzvar; citādi novads; citādi '-'
$db->exec("CREATE TABLE merged AS
    SELECT p.oi AS oi, p.kadastrs AS kadastrs, p.kadastra_telpa, p.kods, p.platiba,
           CASE WHEN COALESCE(l.pilseta,'') NOT IN ('','-') THEN l.pilseta ELSE '-' END AS pilseta,
           CASE WHEN COALESCE(l.pilseta,'') NOT IN ('','-') THEN '-'
                WHEN COALESCE(l.novads,'')  NOT IN ('','-') THEN l.novads ELSE '-' END AS novads
    FROM props p LEFT JOIN loc l ON l.kadastrs = p.kadastra_telpa
    ORDER BY p.rowid, l.rid");

// perform_fallback_merge: pēc kadastra prefiksa, vispirms 8, tad 7 zīmes
foreach ([8, 7] as $plen) {
    $db->exec("DROP TABLE IF EXISTS fb");
    $db->exec("CREATE TABLE fb AS
        SELECT substr(l.kadastrs,1,$plen) AS pfx, l.pilseta, l.novads
        FROM loc l JOIN (SELECT substr(kadastrs,1,$plen) AS p, MIN(rid) AS r
                         FROM loc WHERE length(kadastrs) >= $plen GROUP BY p) m ON m.r = l.rid");
    $db->exec('CREATE INDEX ix_fb ON fb(pfx)');
    $db->exec("UPDATE merged SET
        pilseta = COALESCE((SELECT CASE WHEN f.pilseta NOT IN ('','-') THEN f.pilseta ELSE '-' END
                            FROM fb f WHERE f.pfx = substr(merged.kadastrs,1,$plen)), '-'),
        novads  = COALESCE((SELECT CASE WHEN f.pilseta NOT IN ('','-') THEN '-'
                                        WHEN f.novads  NOT IN ('','-') THEN f.novads ELSE '-' END
                            FROM fb f WHERE f.pfx = substr(merged.kadastrs,1,$plen)), '-')
        WHERE pilseta = '-' AND novads = '-'");
}
$n = (int)$db->query('SELECT COUNT(*) FROM merged')->fetchColumn();
ie_say("    Rezultāts: $n īpašumi ar noteiktām atrašanās vietām.");

// [2/6] iedzīvotāji
ie_say("\n[2/6] Sāk iedzīvotāju skaita aprēķināšanu…");

// VISS ŠIS BLOKS IR SQL AR NOLŪKU. Pirmajā variantā virtuālo skaitu, vietu un
// reālo sadali rēķināja PHP cikls ar `UPDATE … WHERE oi = ?` katrai rindai. `oi`
// nav indeksēts, tāpēc katrs atjauninājums skenēja visu tabulu — 10 minūtēs solis
// pat nepaguva līdz [3/6]. Šeit rindu-pa-rindai atjauninājumu vairs nav nevienā.
$db->exec("ALTER TABLE merged ADD COLUMN vieta TEXT");
$db->exec("ALTER TABLE merged ADD COLUMN virtuali INTEGER");
$db->exec("ALTER TABLE merged ADD COLUMN reali INTEGER");

// vieta = pilsēta, citādi novads; regex \s+nov\.$ → " novads"
$db->exec("UPDATE merged SET vieta = (
    WITH v(s) AS (SELECT trim(CASE WHEN pilseta <> '-' THEN pilseta
                                   WHEN novads  <> '-' THEN novads END))
    SELECT CASE WHEN s IS NULL THEN NULL
                WHEN substr(s,-4) = 'nov.' AND substr(s,-5,1) IN (' ', char(9))
                     THEN rtrim(substr(s,1,length(s)-4)) || ' novads'
                ELSE s END FROM v)");

// calculate_virtual_population() — tīra CASE izteiksme
$db->exec("UPDATE merged SET virtuali = CASE
    WHEN platiba IS NULL OR platiba = '' OR CAST(platiba AS REAL) <= 0 THEN NULL
    WHEN kods IN (1110,1121) THEN CASE
        WHEN CAST(platiba AS REAL) <  30 THEN 1 WHEN CAST(platiba AS REAL) <=  70 THEN 2
        WHEN CAST(platiba AS REAL) <= 250 THEN 3 WHEN CAST(platiba AS REAL) <= 400 THEN 4
        WHEN CAST(platiba AS REAL) <= 600 THEN 5 WHEN CAST(platiba AS REAL) <= 800 THEN 6
        ELSE 7 END
    WHEN kods = 1122 THEN CASE
        WHEN CAST(platiba AS REAL) <  25 THEN 1 WHEN CAST(platiba AS REAL) <=  45 THEN 2
        WHEN CAST(platiba AS REAL) <= 100 THEN 3 WHEN CAST(platiba AS REAL) <= 200 THEN 4
        WHEN CAST(platiba AS REAL) <= 300 THEN 5 ELSE 6 END
    ELSE NULL END");
$db->exec('CREATE INDEX ix_m_vieta ON merged(vieta)');

// Iedzīvotāju tabula → SQL
$db->exec('CREATE TABLE pop (vieta TEXT PRIMARY KEY, total INTEGER)');
$ins = $db->prepare('INSERT OR REPLACE INTO pop VALUES (?,?)');
$db->beginTransaction();
foreach (s3_population_map() as $place => $total) $ins->execute([$place, $total]);
$db->commit();

/* assign_real_population() SQL formā.
   a0    = max(1, floor(virtuali × kopskaits / virtuālā summa))
   frac  = daļskaitļa daļa, pēc kuras dilstoši dala atlikumu
   rk    = vieta rindā (neizšķirtos šķir sākotnējā CSV rinda — skat. faila galvu)
   Python cikls `for i in range(remainder): alloc[order[i % n]] += 1` katram
   elementam dod floor(remainder/n) reizes plus vienu, ja rk <= remainder % n. */
$db->exec("CREATE TABLE a0 AS
    SELECT m.rowid AS rid, m.vieta AS vieta, m.kods AS kods,
           MAX(1, CAST(m.virtuali * p.total * 1.0 / g.vsum AS INTEGER)) AS a0,
           (m.virtuali * p.total * 1.0 / g.vsum)
             - CAST(m.virtuali * p.total * 1.0 / g.vsum AS INTEGER) AS frac
    FROM merged m
    JOIN (SELECT vieta, SUM(virtuali) AS vsum FROM merged
          WHERE vieta IS NOT NULL AND virtuali > 0 GROUP BY vieta) g ON g.vieta = m.vieta
    JOIN pop p ON p.vieta = m.vieta
    WHERE m.virtuali IS NOT NULL AND m.virtuali > 0 AND p.total > 0 AND g.vsum > 0");

/* NEIZŠĶIRTOS ŠĶIR JAUKTĀ ATSLĒGA, NEVIS RINDAS NUMURS.
   Pirmais variants neizšķirtos kārtoja pēc sākotnējās CSV rindas (= pandas stabilā
   šķirošana). Rindu līmenī tas sakrita ar Python precīzi, BET CSV ir kārtots pēc
   kadastra numura, un tas ir ģeogrāfiski grupēts — tāpēc +1 tika piešķirts veselām
   apkaimēm pēc kārtas, nevis izklaidus. Mērījums 500 m šūnās: vidējā novirze 7,3 %,
   p95 29 %, maksimums 92 %. Lapa summē iedzīvotājus rādiusā, tāpēc tas būtu bijis
   redzams. numpy nejaušā permutācija šo blakusefektu neradīja nejauši, bet radīja.
   Jauktā atslēga ir deterministiska (atkārtojama starp palaišanām) un ar atrašanās
   vietu nekorelē.

   Knuta multiplikatīvais jaucējs TĪRĀ SQL, nevis PHP funkcija: PDO::sqliteCreateFunction()
   kopš PHP 8.5 ir novecojis, un Pdo\Sqlite::createFunction() pastāv tikai no 8.4 —
   ar SQL izteiksmi solis strādā jebkurā versijā, un žurnālā nav brīdinājumu.
   Pēdējie 9 cipari (<10⁹) reiz 2654435761 paliek zem int64 griesta, tāpēc SQLite
   nepārslēdzas uz peldošo punktu un rezultāts ir stingri atkārtojams. */
$db->exec("CREATE TABLE rk AS
    SELECT a.rid, a.vieta, a.kods, a.a0,
           ROW_NUMBER() OVER (PARTITION BY a.vieta
                              ORDER BY a.frac DESC,
                                       (CAST(substr(m.kadastra_telpa,-9) AS INTEGER)
                                        * 2654435761) % 4294967291,
                                       a.rid) AS rk
    FROM a0 a JOIN merged m ON m.rowid = a.rid");
$db->exec('CREATE INDEX ix_rk_rid ON rk(rid)');

$db->exec("CREATE TABLE rem AS
    SELECT r.vieta AS vieta, MAX(p.total) - SUM(r.a0) AS r, COUNT(*) AS n
    FROM rk r JOIN pop p ON p.vieta = r.vieta GROUP BY r.vieta");
$db->exec('CREATE INDEX ix_rem ON rem(vieta)');

$db->exec("UPDATE merged SET reali = (
    SELECT MIN(
        r.a0 + CASE WHEN e.r > 0
                    THEN (e.r / e.n) + CASE WHEN r.rk <= (e.r % e.n) THEN 1 ELSE 0 END
                    ELSE 0 END,
        CASE r.kods WHEN 1110 THEN 7 WHEN 1121 THEN 7 WHEN 1122 THEN 6 ELSE 999999999 END)
    FROM rk r JOIN rem e ON e.vieta = r.vieta WHERE r.rid = merged.rowid)");

$assigned = (int)$db->query('SELECT COUNT(*) FROM merged WHERE reali IS NOT NULL')->fetchColumn();
ie_say("    Rezultāts: $assigned īpašumiem piešķirts reālais iedzīvotāju skaits.");

// --dump-step2=FAILS — īpašumu līmeņa izmete salīdzināšanai ar Python versiju
foreach ($argv as $a) {
    if (!str_starts_with($a, '--dump-step2=')) continue;
    $p = substr($a, 13);
    $fh = fopen($p, 'w');
    ie_fputcsv_py($fh, ['kadastrs', 'kadastra_telpa', 'kods', 'vieta', 'virtuali_cilveki', 'reali_cilveki'], "\n");
    $q = $db->query('SELECT kadastrs, kadastra_telpa, kods, vieta, virtuali, reali FROM merged ORDER BY rowid');
    while ($r = $q->fetch(PDO::FETCH_NUM)) ie_fputcsv_py($fh, $r, "\n");
    fclose($fh);
    ie_say("    [izmete] $p");
}

// [3/6] cenas
ie_say("\n[3/6] Sāk cenas datu pievienošanu…");
$db->exec('CREATE TABLE cenas (kadastrs TEXT PRIMARY KEY, univ REAL)');
$ins = $db->prepare('INSERT OR IGNORE INTO cenas VALUES (?,?)');   // drop_duplicates(keep=first)
$db->beginTransaction();
foreach (ie_csv_rows($f10) as $row) {
    $v = $row['univ_cena'] ?? '';
    if ($v === '' || $v === null || !is_numeric($v)) continue;      // to_numeric(errors='coerce') + dropna
    $ins->execute([(string)($row['kadastrs'] ?? ''), (float)$v]);
}
$db->commit();

$db->exec('ALTER TABLE merged ADD COLUMN univ_cena REAL');
$db->exec('UPDATE merged SET univ_cena = (SELECT c.univ FROM cenas c WHERE c.kadastrs = merged.kadastra_telpa)');
// Otrais mēģinājums ar pārveidotu atslēgu: kadastra_telpa[:11] + kadastra_telpa[14:]
$db->exec("UPDATE merged SET univ_cena = (SELECT c.univ FROM cenas c
              WHERE c.kadastrs = substr(merged.kadastra_telpa,1,11) || substr(merged.kadastra_telpa,15))
           WHERE univ_cena IS NULL AND length(kadastra_telpa) >= 17");
$n = (int)$db->query('SELECT COUNT(*) FROM merged WHERE univ_cena IS NOT NULL')->fetchColumn();
ie_say("    Rezultāts: $n īpašumiem pievienota cena.");

// [4/6] būvniecības gads
ie_say("\n[4/6] Sāk būvniecības gada pievienošanu…");
$db->exec('CREATE TABLE g1 (k TEXT PRIMARY KEY, gads INTEGER)');   // pēc kadastra_telpa
$db->exec('CREATE TABLE g2 (k TEXT PRIMARY KEY, gads INTEGER)');   // pēc kadastrs
$i1 = $db->prepare('INSERT OR IGNORE INTO g1 VALUES (?,?)');
$i2 = $db->prepare('INSERT OR IGNORE INTO g2 VALUES (?,?)');
$db->beginTransaction();
foreach (ie_csv_rows($f5) as $row) {
    $g = $row['gads'] ?? '';
    if ($g === '' || $g === null || !is_numeric($g)) continue;      // dropna(subset=['gads'])
    $gi = (int)(float)$g;
    $i1->execute([(string)($row['kadastra_telpa'] ?? ''), $gi]);
    $i2->execute([(string)($row['kadastrs'] ?? ''), $gi]);
}
$db->commit();

$db->exec('ALTER TABLE merged ADD COLUMN gads INTEGER');
// Python kartē df_analysis['kadastrs'] pret lookup pēc kadastra_telpa — atkārtojam
$db->exec('UPDATE merged SET gads = (SELECT g.gads FROM g1 g WHERE g.k = merged.kadastrs)');
$db->exec('UPDATE merged SET gads = (SELECT g.gads FROM g2 g WHERE g.k = substr(merged.kadastrs,1,11))
           WHERE gads IS NULL');
$n = (int)$db->query('SELECT COUNT(*) FROM merged WHERE gads IS NOT NULL')->fetchColumn();
ie_say("    Rezultāts: $n īpašumiem pievienots būvniecības gads.");

// [5/6] individuālie līmeņi
ie_say("\n[5/6] Sāk individuālo līmeņu (A–E) piešķiršanu…");
$db->exec('DELETE FROM merged WHERE univ_cena IS NULL');
$db->exec('ALTER TABLE merged ADD COLUMN vpp REAL');
$db->exec('ALTER TABLE merged ADD COLUMN adjv REAL');
$db->exec('ALTER TABLE merged ADD COLUMN lvl TEXT');
$db->exec('UPDATE merged SET vpp = CASE WHEN reali IS NULL OR reali = 0 THEN NULL
                                        ELSE univ_cena * 1.0 / reali END');

// calculate_adjustment_factor() — arī tīra CASE izteiksme
$db->exec("UPDATE merged SET adjv = vpp * (1
    + CASE WHEN gads BETWEEN 1950 AND 1969 THEN -0.10
           WHEN gads BETWEEN 1970 AND 1989 THEN -0.05
           WHEN gads BETWEEN 2000 AND 2014 THEN  0.05
           WHEN gads BETWEEN 2015 AND 2030 THEN  0.10 ELSE 0 END
    + CASE WHEN kods IN (1110,1121) THEN 0.05
           WHEN kods = 1122         THEN -0.05 ELSE 0 END)
    WHERE vpp IS NOT NULL");

/* Svērtās kvantiles pa vietām. Vietu ir ~150, tāpēc PHP pusē paliek tikai tās —
   sliekšņus ierakstām mazā tabulā, un līmeni visām rindām piešķir VIENS SQL
   atjauninājums. Rindas lasām vienā sakārtotā vaicājumā un grupu robežas ķeram
   plūsmā, lai atmiņā vienlaikus būtu tikai viena vieta (Rīga ir lielākā). */
$db->exec('CREATE TABLE thr (vieta TEXT PRIMARY KEY, q35 REAL, q55 REAL, q75 REAL, q90 REAL)');
$insThr = $db->prepare('INSERT OR REPLACE INTO thr VALUES (?,?,?,?,?)');

/* Kārtošanu un summēšanu dara SQL, kvantiles rēķina plūsmā. Iepriekšējā versija
   visas grupas rindas turēja PHP masīvā — Rīgā ~100 MB, kas ar cron noklusējuma
   memory_limit=128M soli nogāza. Tagad atmiņa nav atkarīga no pilsētas izmēra. */
$db->exec('CREATE INDEX ix_m_vieta_vpp ON merged(vieta, vpp)');
$sumQ = $db->prepare('SELECT SUM(reali) FROM merged
                      WHERE vieta = ? AND vpp IS NOT NULL AND reali > 0');
$rowQ = $db->prepare('SELECT vpp, reali FROM merged
                      WHERE vieta = ? AND vpp IS NOT NULL AND reali > 0 ORDER BY vpp');

$places = $db->query("SELECT DISTINCT vieta FROM merged WHERE vieta IS NOT NULL")
             ->fetchAll(PDO::FETCH_COLUMN);
$db->beginTransaction();
foreach ($places as $place) {
    $sumQ->execute([$place]);
    $total = (float)$sumQ->fetchColumn();
    if ($total <= 0) continue;

    $rowQ->execute([$place]);
    $th = ie_weighted_quantile_stream(
        (function () use ($rowQ) { while ($r = $rowQ->fetch(PDO::FETCH_NUM)) yield $r; })(),
        $total, [0.35, 0.55, 0.75, 0.9]);
    if ($th === null) continue;

    foreach ($th as $v) if (is_nan($v)) continue 2;
    $insThr->execute([$place, $th[0], $th[1], $th[2], $th[3]]);
}
$db->commit();

// Sliekšņi nāk no value_per_person, bet tiek piemēroti adj_value — tā ir oriģinālā loģika
$db->exec("UPDATE merged SET lvl = (
    SELECT CASE WHEN merged.adjv <  t.q35 THEN 'E'
                WHEN merged.adjv <  t.q55 THEN 'D'
                WHEN merged.adjv <  t.q75 THEN 'C'
                WHEN merged.adjv <  t.q90 THEN 'B'
                ELSE 'A' END
    FROM thr t WHERE t.vieta = merged.vieta)
    WHERE adjv IS NOT NULL AND vieta IS NOT NULL");

$levelled = (int)$db->query('SELECT COUNT(*) FROM merged WHERE lvl IS NOT NULL')->fetchColumn();
ie_say("    Rezultāts: $levelled īpašumiem piešķirts līmenis.");

// [6/6] ēku kopējie līmeņi
ie_say("\n[6/6] Sāk ēku kopējo līmeņu aprēķināšanu…");
$sel = $db->query("SELECT kadastrs,
                          SUM(reali) AS cilveki,
                          SUM(CASE lvl WHEN 'A' THEN 5 WHEN 'B' THEN 4 WHEN 'C' THEN 3
                                       WHEN 'D' THEN 2 WHEN 'E' THEN 1 END * reali) AS svars
                   FROM merged
                   WHERE reali IS NOT NULL AND reali > 0 AND lvl IS NOT NULL
                   GROUP BY kadastrs ORDER BY kadastrs");

$fh = fopen($out, 'w');
if ($fh === false) ie_fail("nevar rakstīt: $out");
ie_fputcsv_py($fh, ['kadastrs', 'kopejie_cilveki', 'level'], "\n");   // pandas to_csv = LF
$rows = 0;
while ($r = $sel->fetch(PDO::FETCH_NUM)) {
    $avg = (float)$r[2] / (float)$r[1];
    ie_fputcsv_py($fh, [$r[0], (int)$r[1], s3_score_to_level($avg)], "\n");
    $rows++;
}
fclose($fh);

$db = null;
@unlink($dbPath);
@rmdir($tmp);

ie_say("\nGALA REZULTĀTS saglabāts failā: $out");
ie_say("   Kopā apstrādātas un saglabātas $rows ēkas.");
ie_done($t0);
if ($rows === 0) ie_fail('out-summ-level.csv palika tukšs');
