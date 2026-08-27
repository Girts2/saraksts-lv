<?php
// Kopīgais notikumu žurnāls — ja vietnes lib/ nav (savrupa izvietošana), lapa
// strādā bez žurnāla, nevis mirst uz require.
if (is_file(__DIR__ . '/lib/applog.php')) {
    require_once __DIR__ . '/lib/applog.php';
    applog_boot('iespeja');
}
// --- BACKEND: AJAX Apstrāde ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Keep 0 for production
    ini_set('log_errors', 1);
    // ini_set('error_log', '/path/to/your/php-error.log');

    // --- Datubāzes un Konfigurācijas Definīcijas ---
    //
    // PAŠPIETIEKAMS FAILS: pieslēgums, tabulu nosaukumi un reģionu karte ir
    // ielikti šeit iekšā, lai izvietošana ir VIENS fails bez apakšmapēm.
    //
    // CENA, kas par to samaksāta apzināti: konveijeram (Iespēja/php/config.php,
    // iespeja/config.py) ir SAVAS šo pašu datu kopijas. Mainot DB paroli vai
    // pievienojot reģionu, jāizlabo arī tur. Vides mainīgie IESPEJA_DB_* strādā
    // abās pusēs un ir vienīgais veids, kā šo dublēšanos apiet.
    //
    // Funkcijas ir tās pašas, kas konveijera schema.php — nosaukumi un semantika
    // sakrīt, tāpēc kods zem tām ir identisks abos izvietojumos.

    if (!function_exists('ie_config')) {
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
            foreach (['host' => 'IESPEJA_DB_HOST', 'port' => 'IESPEJA_DB_PORT',
                      'name' => 'IESPEJA_DB_NAME', 'user' => 'IESPEJA_DB_USER',
                      'pass' => 'IESPEJA_DB_PASS'] as $key => $var) {
                $v = getenv($var);
                if ($v !== false && $v !== '') $cfg[$key] = $v;
            }
            $cfg['port'] = (int)$cfg['port'];
            return $cache = $cfg;
        }
    }

    if (!defined('IE_COUNTRY')) {
        $ieEnvCountry = getenv('IESPEJA_COUNTRY');
        define('IE_COUNTRY', ($ieEnvCountry !== false && $ieEnvCountry !== '')
            ? strtolower($ieEnvCountry) : 'lv');
    }

    if (!function_exists('ie_country')) {
        /** Valsts profils — tikai tas, ko lieto LAPA (konveijeram ir pilnais). */
        function ie_country(): array
        {
            static $profiles = [
                'lv' => [
                    'code' => 'lv',
                    'name' => 'Latvija',
                    // Reģioni = ēku slāņa šķēlumi. Latvijai viens; lielā valstī
                    // pa ierakstam uz administratīvo vienību, un tad tabulas ir
                    // <kods>_buildings_<reģions>. bbox = [minLon,minLat,maxLon,maxLat]
                    'regions' => [
                        ['code' => 'lv', 'name' => 'Latvija', 'bbox' => [20.5, 55.6, 28.3, 58.2]],
                    ],
                    // Viesnīcu konkurenti = šie tūrisma slāņa tipi
                    'tourism' => ['stay_types' => ['hotel', 'hostel', 'guest_house',
                                                   'motel', 'apartment', 'chalet']],
                ],
            ];
            if (!isset($profiles[IE_COUNTRY])) {
                throw new RuntimeException('nav valsts profila: ' . IE_COUNTRY);
            }
            return $profiles[IE_COUNTRY];
        }

        /** Tabulas pilnais nosaukums: <valsts>_<slānis>[_<reģions>]. */
        function ie_table(string $layer, ?string $region = null): string
        {
            $c = ie_country();
            $base = $c['code'] . '_' . $layer;
            if ($region === null || $layer !== 'buildings') return $base;
            if (count($c['regions']) < 2) return $base;
            return $base . '_' . $region;
        }

        /** Ēku tabulas, kas skar taisnstūri — VISAS pārklājošās (pierobežai). */
        function ie_shards_for_bbox(float $minLon, float $minLat,
                                    float $maxLon, float $maxLat): array
        {
            $out = [];
            $regions = ie_country()['regions'];
            foreach ($regions as $r) {
                [$rMinLon, $rMinLat, $rMaxLon, $rMaxLat] = $r['bbox'];
                if ($maxLon < $rMinLon || $minLon > $rMaxLon) continue;
                if ($maxLat < $rMinLat || $minLat > $rMaxLat) continue;
                $out[] = ie_table('buildings', $r['code']);
            }
            if (!$out) $out[] = ie_table('buildings', $regions[0]['code']);
            return array_values(array_unique($out));
        }
    }

    $ieCfg  = ie_config();
    $dbHost = $ieCfg['host'];
    $dbName = $ieCfg['name'];
    $dbUser = $ieCfg['user'];
    $dbPass = $ieCfg['pass'];

    $levelColorsPHP = ['A' => '#FFBF00', 'B' => '#FFBF00', 'C' => '#FFBF00', 'D' => '#FFBF00', 'E' => '#FFBF00', 'NULL' => '#AAAAAA']; // Gold for PPL icons
    $levelGreyColor = '#B0B0B0'; // Grey for PPL icons

    $establishmentTypes = ['Kafejnīca', 'Restorāns', 'Krogs', 'Frizētava', 'Beķereja', 'Aptieka', 'Skaistumkopšana', 'Minimārkets', 'Zobārsts', 'Ātrā ēdināšana', 'Fitnesa klubs', 'Viesnīca'];
    $pplToIndex = ['E' => 0, 'D' => 1, 'C' => 2, 'B' => 3, 'A' => 4];
    $indexToPPL = ['E', 'D', 'C', 'B', 'A'];

    /* Biznesa tips (UI) → `<valsts>_poi`.`ptype`. Agrāk katrs tips bija ATSEVIŠĶA
       tabula, un tabulas nosaukums ar hostinga konta prefiksu bija ierakstīts šeit
       otrreiz. Tagad tips ir kolonnas vērtība, tāpēc jauns biznesa tips ir viena
       rinda valsts profilā, nevis četri saskaņoti labojumi. */
    $typeToPtype = [
        'Kafejnīca' => 'cafe',
        'Restorāns' => 'restaurant',
        'Krogs'     => 'bar',
        'Frizētava' => 'hairdresser',
        'Beķereja'        => 'bakery',
        'Aptieka'         => 'pharmacy',
        'Skaistumkopšana' => 'beauty',
        'Minimārkets'     => 'minimarket',
        'Zobārsts'        => 'dentist',
        'Ātrā ēdināšana'  => 'fastfood',
        'Fitnesa klubs'   => 'fitness'
        /* Viesnīca: konkurenti = tūrisma slāņa naktsmītņu tipi (īpašā apstrāde) */
    ];
    $ptypeToType = array_flip($typeToPtype);

    /* Tabulu nosaukumi — vienā vietā, no schema.php. */
    $tblPoi   = ie_table('poi');
    $tblOff   = ie_table('offices');
    $tblInst  = ie_table('institutions');
    $tblTour  = ie_table('tourism');
    $stayTypesSql = "'" . implode("','", ie_country()['tourism']['stay_types']) . "'";

    // --- DATU KOPAS AR SCENĀRIJIEM ---
    $dataScenarios = [ /* ... NEMAINĪTS ... */ 'pessimistic' => [ 'visitorsPer1000' => [ 'Kafejnīca'=>[17,34,49.3,69.7,89.25],'Restorāns'=>[2.04,4.59,8.16,13.25,21.41],'Krogs'=>[9,17,26,36,46],'Frizētava'=>[2.34,3.12,4.67,6.23,7.79],'Beķereja'=>[38.4,70.4,99.8,128,160],'Aptieka'=>[6.56,9.76,13.6,17.44,21.76],'Skaistumkopšana'=>[0.66,0.98,1.48,2.13,2.79],'Minimārkets'=>[91.2,100.32,104.88,100.32,91.2],'Zobārsts'=>[0.8,1.12,1.52,2.08,2.64],'Ātrā ēdināšana'=>[10.49,18.89,27.29,37.78,48.28],'Fitnesa klubs'=>[2.73,4.13,5.85,7.88,10.3],'Viesnīca'=>[0.09,0.14,0.18,0.23,0.27] ], 'averagePrice' => [ 'Kafejnīca'=>[3.15,3.78,4.5,5.4,6.75],'Restorāns'=>[9.84,12.3,16.41,22.97,32.81],'Krogs'=>[7.00,9.00,12.00,16.00,22.00],'Frizētava'=>[12.6,16.2,21.6,28.8,40.5],'Beķereja'=>[2.2,2.64,3.34,4.05,5.28],'Aptieka'=>[7.92,10.29,12.68,15.84,20.6],'Skaistumkopšana'=>[15.49,21.68,29.43,40.27,54.21],'Minimārkets'=>[4.75,6.33,7.92,10.29,13.47],'Zobārsts'=>[39.6,52.8,70.4,92,123],'Ātrā ēdināšana'=>[3.83,4.53,5.58,6.97,9.06],'Fitnesa klubs'=>[1.87,2.54,3.29,4.19,5.61],'Viesnīca'=>[25.55,35.77,49.41,68.15,93.9] ], 'initialInvestment' => [ 'Kafejnīca'=>[25000,35000,45000,60000,80000],'Restorāns'=>[60000,80000,110000,150000,200000],'Krogs'=>[40000,55000,70000,90000,120000],'Frizētava'=>[8000,12000,18000,26000,38000],'Beķereja'=>[16000,23000,32000,45000,63000],'Aptieka'=>[27000,36000,50000,63000,81000],'Skaistumkopšana'=>[9000,14000,20000,29000,41000],'Minimārkets'=>[36000,50000,63000,81000,104000],'Zobārsts'=>[40000,54000,72000,94000,126000],'Ātrā ēdināšana'=>[27000,38000,52000,70000,94000],'Fitnesa klubs'=>[54000,76000,104000,135000,180000],'Viesnīca'=>[126000,171000,234000,315000,423000] ] ], 'realistic' => [ /* kalibrēts pret katalogs.sqlite izdzīvotāju mediānām: apgrozījums ~×1.4 pret pesimistisko (café/rest/bar bija 0.61-0.66 no reālā), frizētava ~×1.05 (jau 0.96); čeki = reālie LV 2026 */ 'visitorsPer1000' => [ 'Kafejnīca'=>[21.25,40.8,59.5,83.3,106.25],'Restorāns'=>[2.55,5.61,10.2,16.82,26.51],'Krogs'=>[10,20,31,44,56],'Frizētava'=>[2.34,3.27,4.67,6.62,8.18],'Beķereja'=>[48,88,124.8,160,200],'Aptieka'=>[8.16,12.24,16.96,21.76,27.2],'Skaistumkopšana'=>[0.82,1.23,1.8,2.62,3.44],'Minimārkets'=>[114,125.4,131.1,125.4,114],'Zobārsts'=>[1.04,1.44,1.92,2.56,3.36],'Ātrā ēdināšana'=>[13.15,23.64,34.14,47.23,60.31],'Fitnesa klubs'=>[3.43,5.15,7.33,9.91,12.87],'Viesnīca'=>[0.11,0.17,0.23,0.28,0.33] ], 'averagePrice' => [ 'Kafejnīca'=>[3.6,4.32,5.22,6.3,8.1],'Restorāns'=>[10.66,13.94,18.87,26.25,37.73],'Krogs'=>[7.50,10.00,13.50,18.00,25.00],'Frizētava'=>[13.5,17.1,22.5,30.6,43.2],'Beķereja'=>[2.5,3,3.8,4.6,6],'Aptieka'=>[9,11.7,14.4,18,23.4],'Skaistumkopšana'=>[17.6,24.64,33.44,45.76,61.6],'Minimārkets'=>[5.4,7.2,9,11.7,15.3],'Zobārsts'=>[45,60,80,105,140],'Ātrā ēdināšana'=>[4.36,5.15,6.34,7.92,10.3],'Fitnesa klubs'=>[2.12,2.89,3.74,4.76,6.38],'Viesnīca'=>[29.04,40.66,56.14,77.44,106.48] ], 'initialInvestment' => [ 'Kafejnīca'=>[28000,40000,52000,69000,92000],'Restorāns'=>[68000,92000,125000,170000,230000],'Krogs'=>[46000,62000,80000,103000,138000],'Frizētava'=>[9500,14000,21000,30000,44000],'Beķereja'=>[18000,26000,36000,50000,70000],'Aptieka'=>[30000,40000,55000,70000,90000],'Skaistumkopšana'=>[10000,15000,22000,32000,46000],'Minimārkets'=>[40000,55000,70000,90000,115000],'Zobārsts'=>[45000,60000,80000,105000,140000],'Ātrā ēdināšana'=>[30000,42000,58000,78000,105000],'Fitnesa klubs'=>[60000,85000,115000,150000,200000],'Viesnīca'=>[140000,190000,260000,350000,470000] ] ], 'optimistic' => [ 'visitorsPer1000' => [ 'Kafejnīca'=>[21.25,42.5,68,93.5,119],'Restorāns'=>[2.55,7.65,15.29,28.04,45.88],'Krogs'=>[10,20,40,60,80],'Frizētava'=>[6.23,9.35,14.02,19.47,27.26],'Beķereja'=>[62.4,114.4,162.2,208,259.2],'Aptieka'=>[10.64,15.92,22.08,28.32,35.36],'Skaistumkopšana'=>[1.07,1.56,2.38,3.44,4.51],'Minimārkets'=>[148.2,163.02,170.43,163.02,148.2],'Zobārsts'=>[1.36,1.84,2.48,3.36,4.32],'Ātrā ēdināšana'=>[17.07,30.71,44.36,61.43,78.71],'Fitnesa klubs'=>[4.45,6.71,9.52,12.79,16.77],'Viesnīca'=>[0.14,0.22,0.29,0.36,0.43] ], 'averagePrice' => [ 'Kafejnīca'=>[4.05,4.95,6.3,7.65,9.9],'Restorāns'=>[13.12,18.05,26.25,36.91,53.32],'Krogs'=>[10.00,14.00,19.00,26.00,35.00],'Frizētava'=>[19.8,27,37.8,54,76.5],'Beķereja'=>[3.12,3.75,4.75,5.75,7.5],'Aptieka'=>[11.25,14.62,18,22.5,29.25],'Skaistumkopšana'=>[22,30.8,41.8,57.2,77],'Minimārkets'=>[6.75,9,11.25,14.62,19.12],'Zobārsts'=>[56.25,75,100,131,175],'Ātrā ēdināšana'=>[5.45,6.44,7.92,9.9,12.87],'Fitnesa klubs'=>[2.65,3.61,4.67,5.95,7.97],'Viesnīca'=>[36.3,50.82,70.18,96.8,133.58] ], 'initialInvestment' => [ 'Kafejnīca'=>[35000,50000,65000,85000,115000],'Restorāns'=>[85000,115000,155000,210000,280000],'Krogs'=>[55000,75000,100000,130000,170000],'Frizētava'=>[14000,20000,30000,42000,58000],'Beķereja'=>[22000,32000,45000,62000,88000],'Aptieka'=>[38000,50000,69000,88000,112000],'Skaistumkopšana'=>[12000,19000,28000,40000,58000],'Minimārkets'=>[50000,69000,88000,112000,144000],'Zobārsts'=>[56000,75000,100000,131000,175000],'Ātrā ēdināšana'=>[38000,52000,72000,98000,131000],'Fitnesa klubs'=>[75000,106000,144000,188000,250000],'Viesnīca'=>[175000,238000,325000,438000,588000] ] ] ];
    // Bāzes kredīta atmaksa
    $bizM2 = ['Kafejnīca'=>60,'Restorāns'=>130,'Krogs'=>85,'Frizētava'=>35]; $bizDaysOpen = ['Kafejnīca'=>30,'Restorāns'=>30,'Krogs'=>28,'Frizētava'=>26,'Beķereja'=>30,'Aptieka'=>30,'Skaistumkopšana'=>26,'Minimārkets'=>30,'Zobārsts'=>22,'Ātrā ēdināšana'=>30,'Fitnesa klubs'=>30,'Viesnīca'=>30]; $bizLaborFloorPD = ['Kafejnīca'=>2.2,'Restorāns'=>3.5,'Krogs'=>2.0,'Frizētava'=>0.0]; $bizLaborPct = ['Kafejnīca'=>0.35,'Restorāns'=>0.34,'Krogs'=>0.30,'Frizētava'=>0.50]; $bizUtilM2 = ['Kafejnīca'=>7,'Restorāns'=>13,'Krogs'=>8,'Frizētava'=>5,'Beķereja'=>10,'Aptieka'=>5,'Skaistumkopšana'=>6,'Minimārkets'=>9,'Zobārsts'=>8,'Ātrā ēdināšana'=>10,'Fitnesa klubs'=>5,'Viesnīca'=>6]; $bizOpFixMonth = ['Kafejnīca'=>450,'Restorāns'=>700,'Krogs'=>480,'Frizētava'=>140,'Beķereja'=>350,'Aptieka'=>600,'Skaistumkopšana'=>200,'Minimārkets'=>500,'Zobārsts'=>800,'Ātrā ēdināšana'=>420,'Fitnesa klubs'=>600,'Viesnīca'=>1200]; $bizCapacityDay = ['Kafejnīca'=>400,'Restorāns'=>260,'Krogs'=>350,'Frizētava'=>80]; $bizResPerVenue = ['Kafejnīca'=>1800,'Restorāns'=>3000,'Krogs'=>2800,'Frizētava'=>1200,'Beķereja'=>2500,'Aptieka'=>2200,'Skaistumkopšana'=>1600,'Minimārkets'=>1200,'Zobārsts'=>2800,'Ātrā ēdināšana'=>2000,'Fitnesa klubs'=>3500,'Viesnīca'=>50000]; $rentPerM2Month = ['A'=>26.0,'B'=>18.0,'C'=>13.0,'D'=>9.0,'E'=>6.0]; $sizeTiers = ['Kafejnīca'=>[['name'=>'Maza','m2'=>35,'cap'=>90,'floor'=>1.0,'invMul'=>0.5,'opMul'=>0.55,'staff'=>2],['name'=>'Vidēja','m2'=>65,'cap'=>200,'floor'=>2.5,'invMul'=>1.0,'opMul'=>1.0,'staff'=>4],['name'=>'Liela','m2'=>130,'cap'=>450,'floor'=>5.5,'invMul'=>2.2,'opMul'=>1.8,'staff'=>9]],'Restorāns'=>[['name'=>'Mazs','m2'=>70,'cap'=>70,'floor'=>2.5,'invMul'=>0.5,'opMul'=>0.6,'staff'=>4],['name'=>'Vidējs','m2'=>140,'cap'=>200,'floor'=>5.0,'invMul'=>1.1,'opMul'=>1.1,'staff'=>9],['name'=>'Liels','m2'=>240,'cap'=>380,'floor'=>9.0,'invMul'=>2.0,'opMul'=>1.7,'staff'=>16]],'Krogs'=>[['name'=>'Vidējs','m2'=>85,'cap'=>350,'floor'=>2.0,'invMul'=>1.0,'opMul'=>1.0,'staff'=>4]],'Frizētava'=>[['name'=>'Salons','m2'=>26,'cap'=>80,'floor'=>0.0,'invMul'=>1.0,'opMul'=>1.0,'staff'=>2]],'Beķereja'=>[['name'=>'Maza','m2'=>25,'cap'=>130,'floor'=>1.0,'invMul'=>0.6,'opMul'=>0.6,'staff'=>2],['name'=>'Vidēja','m2'=>45,'cap'=>260,'floor'=>2.0,'invMul'=>1.0,'opMul'=>1.0,'staff'=>4]],'Aptieka'=>[['name'=>'Aptieka','m2'=>45,'cap'=>220,'floor'=>0.0,'invMul'=>1.0,'opMul'=>1.0,'staff'=>2]],'Skaistumkopšana'=>[['name'=>'Salons','m2'=>30,'cap'=>60,'floor'=>0.0,'invMul'=>1.0,'opMul'=>1.0,'staff'=>1]],'Minimārkets'=>[['name'=>'Bodīte','m2'=>60,'cap'=>380,'floor'=>0.0,'invMul'=>0.7,'opMul'=>0.7,'staff'=>2],['name'=>'Vidējs','m2'=>120,'cap'=>650,'floor'=>0.0,'invMul'=>1.2,'opMul'=>1.2,'staff'=>4]],'Zobārsts'=>[['name'=>'Kabinets','m2'=>40,'cap'=>22,'floor'=>0.0,'invMul'=>1.0,'opMul'=>1.0,'staff'=>2]],'Ātrā ēdināšana'=>[['name'=>'Mazs','m2'=>45,'cap'=>220,'floor'=>1.5,'invMul'=>0.7,'opMul'=>0.7,'staff'=>3],['name'=>'Vidējs','m2'=>90,'cap'=>420,'floor'=>3.0,'invMul'=>1.2,'opMul'=>1.2,'staff'=>6]],'Fitnesa klubs'=>[['name'=>'Studija','m2'=>90,'cap'=>100,'floor'=>0.0,'invMul'=>0.45,'opMul'=>0.5,'staff'=>1],['name'=>'Klubs','m2'=>400,'cap'=>320,'floor'=>0.0,'invMul'=>1.3,'opMul'=>1.3,'staff'=>4]],'Viesnīca'=>[['name'=>'Viesu nams','m2'=>300,'cap'=>12,'floor'=>0.0,'invMul'=>0.6,'opMul'=>0.6,'staff'=>2],['name'=>'Viesnīca','m2'=>800,'cap'=>30,'floor'=>0.0,'invMul'=>1.4,'opMul'=>1.4,'staff'=>6]]]; $salaryBands = /* 2026-08-03 pārkalibrēts uz VID 2024 reālajām bruto algām (VSAOI/0.3409/galviņām/12; Q1..P90 pa NACE, N=72..942 uz tipu): vecās joslas bija pilnlaika sludinājumu līmenī (~2× virs galviņu realitātes, kur daudz daļlaika) un, tā kā bizStaffNeeded skaita galviņas, algu rēķins dubultojās — Krogs/Restorāns/Skaistumkopšana modelī NEKUR nebija peļņā, lai gan reāli 48-60% ir plusos */ ['Kafejnīca'=>[450,1000],'Restorāns'=>[650,1400],'Krogs'=>[450,1100],'Frizētava'=>[400,900],'Beķereja'=>[550,1150],'Aptieka'=>[950,1900],'Skaistumkopšana'=>[400,900],'Minimārkets'=>[530,1000],'Zobārsts'=>[1100,3100],'Ātrā ēdināšana'=>[450,1000],'Fitnesa klubs'=>[400,1000],'Viesnīca'=>[500,1250]]; $prodPerWorker = ['Kafejnīca'=>20,'Restorāns'=>5,'Krogs'=>9,'Frizētava'=>4,'Beķereja'=>35,'Aptieka'=>70,'Skaistumkopšana'=>3.5,'Minimārkets'=>90,'Zobārsts'=>5,'Ātrā ēdināšana'=>16,'Fitnesa klubs'=>60,'Viesnīca'=>4]; $minStaff = ['Kafejnīca'=>2,'Restorāns'=>3,'Krogs'=>2,'Frizētava'=>1,'Beķereja'=>2,'Aptieka'=>1,'Skaistumkopšana'=>1,'Minimārkets'=>2,'Zobārsts'=>2,'Ātrā ēdināšana'=>2,'Fitnesa klubs'=>1,'Viesnīca'=>2]; $cogsBands = ['Kafejnīca'=>[0.22,0.25,0.28,0.31,0.34],'Restorāns'=>[0.28,0.31,0.34,0.37,0.40],'Krogs'=>[0.18,0.21,0.24,0.27,0.30],'Frizētava'=>[0.08,0.11,0.14,0.17,0.20],'Beķereja'=>[0.3,0.32,0.34,0.36,0.38],'Aptieka'=>[0.72,0.735,0.75,0.765,0.78],'Skaistumkopšana'=>[0.12,0.14,0.16,0.18,0.2],'Minimārkets'=>[0.75,0.76,0.775,0.79,0.8],'Zobārsts'=>[0.14,0.15,0.16,0.17,0.18],'Ātrā ēdināšana'=>[0.3,0.31,0.33,0.35,0.37],'Fitnesa klubs'=>[0.03,0.04,0.05,0.06,0.07],'Viesnīca'=>[0.28,0.295,0.31,0.325,0.34]]; $footfallBench = ['Kafejnīca'=>55,'Restorāns'=>32,'Krogs'=>40,'Frizētava'=>5,'Beķereja'=>100,'Aptieka'=>95,'Skaistumkopšana'=>3,'Minimārkets'=>120,'Zobārsts'=>11,'Ātrā ēdināšana'=>50,'Fitnesa klubs'=>35,'Viesnīca'=>12]; $officeVisitRate = ['Kafejnīca'=>0.25,'Restorāns'=>0.18,'Krogs'=>0.06,'Frizētava'=>0.008,'Beķereja'=>0.3,'Aptieka'=>0.04,'Skaistumkopšana'=>0.006,'Minimārkets'=>0.1,'Zobārsts'=>0.0015,'Ātrā ēdināšana'=>0.22,'Fitnesa klubs'=>0.05,'Viesnīca'=>0.004]; $tourismRate = ['Kafejnīca'=>1.0,'Restorāns'=>0.9,'Krogs'=>0.55,'Frizētava'=>0.0,'Beķereja'=>0.5,'Aptieka'=>0.05,'Skaistumkopšana'=>0.02,'Minimārkets'=>0.15,'Zobārsts'=>0,'Ātrā ēdināšana'=>0.7,'Fitnesa klubs'=>0.02,'Viesnīca'=>0.35]; $instVisitRate = ['skola'=>['Kafejnīca'=>0.15,'Restorāns'=>0.05,'Krogs'=>0.0,'Frizētava'=>0.005,'Beķereja'=>0.2,'Aptieka'=>0.02,'Skaistumkopšana'=>0.002,'Minimārkets'=>0.1,'Zobārsts'=>0.001,'Ātrā ēdināšana'=>0.2,'Fitnesa klubs'=>0.01,'Viesnīca'=>0],'slimnica'=>['Kafejnīca'=>0.12,'Restorāns'=>0.06,'Krogs'=>0.0,'Frizētava'=>0.005,'Beķereja'=>0.1,'Aptieka'=>0.35,'Skaistumkopšana'=>0.002,'Minimārkets'=>0.08,'Zobārsts'=>0.002,'Ātrā ēdināšana'=>0.06,'Fitnesa klubs'=>0,'Viesnīca'=>0.02],'stacija'=>['Kafejnīca'=>0.20,'Restorāns'=>0.10,'Krogs'=>0.03,'Frizētava'=>0.002,'Beķereja'=>0.25,'Aptieka'=>0.05,'Skaistumkopšana'=>0.001,'Minimārkets'=>0.15,'Zobārsts'=>0,'Ātrā ēdināšana'=>0.3,'Fitnesa klubs'=>0.005,'Viesnīca'=>0.03],'izklaide'=>['Kafejnīca'=>0.10,'Restorāns'=>0.15,'Krogs'=>0.20,'Frizētava'=>0.0,'Beķereja'=>0.05,'Aptieka'=>0.01,'Skaistumkopšana'=>0.002,'Minimārkets'=>0.05,'Zobārsts'=>0,'Ātrā ēdināšana'=>0.1,'Fitnesa klubs'=>0.01,'Viesnīca'=>0.01],'sports'=>['Kafejnīca'=>0.08,'Restorāns'=>0.05,'Krogs'=>0.10,'Frizētava'=>0.0,'Beķereja'=>0.05,'Aptieka'=>0.02,'Skaistumkopšana'=>0.003,'Minimārkets'=>0.06,'Zobārsts'=>0,'Ātrā ēdināšana'=>0.1,'Fitnesa klubs'=>0.03,'Viesnīca'=>0.005]]; $seasonIdx = ['kurorts'=>[0.69,1.06,1.42,0.83],'riga'=>[0.87,0.99,1.11,1.02],'piekraste'=>[0.83,1.03,1.24,0.90],'iekszeme'=>[0.86,1.01,1.17,0.97]]; $seasonZones = [['kurorts',56.975,23.90,4],['kurorts',56.965,23.77,5],['kurorts',56.950,23.55,6],['kurorts',57.262,24.414,5],['kurorts',57.130,24.270,4],['piekraste',56.507,21.010,6],['piekraste',57.394,21.564,6],['piekraste',56.889,21.185,3],['piekraste',57.505,22.808,4],['piekraste',57.747,22.588,5],['piekraste',57.335,23.115,3],['piekraste',57.160,23.220,3],['piekraste',57.752,24.358,4],['piekraste',57.863,24.357,3],['piekraste',56.350,20.990,4],['piekraste',57.600,24.400,3]]; define('RAMP_Y1_PCT', 0.065); define('RAMP_Y2_PCT', 0.03); $instOutflow = ['skola'=>0.30,'slimnica'=>0.30,'stacija'=>1.0,'izklaide'=>0.07,'sports'=>0.30]; /* cik daļa iestādes cilvēku iziet ārpus ēkas: izklaidē ārā iet tikai personāls (~7%, apmeklētājiem iekšējās kafejnīcas), skolēni/slimnīcu apmeklētāji ~30%, stacijas caurplūde tāpat iet pa ielu */ define('TOUR_PPL_IDX', 3); /* tūristi = Augsta (B) pirktspēja */ define('INST_PPL_IDX', 1); /* iestāžu plūsma = Zema (D) pirktspēja */ define('BENCH_PRICE_ELASTICITY', 0.6); /* izdzīvošanas sliekšņa atkarība no čeka: bench_pos = bench×(čeks_C/čeks_pos)^α — lētam čekam vajag vairāk klientu/d, premium mazāk → lēts gals atbalsta vairāk vietu; α=0 būtu vecā plakanā uzvedība, α=1 = ieņēmumi plakani */ $daypartSrc = ['res'=>[0.35,0.65],'office'=>[0.75,0.25],'tourist'=>[0.55,0.45]]; /* [diena,vakars] klātbūtne: iedzīvotāji pa dienu izbraukuši uz darbu (paliek ~35%: pensionāri, attālinātie, maiņas), biroji = pusdienas + after-work logs, tūristi visu dienu */ $daypartInst = ['skola'=>[0.9,0.1],'slimnica'=>[0.9,0.1],'stacija'=>[0.6,0.4],'izklaide'=>[0.3,0.7],'sports'=>[0.4,0.6]]; /* izklaide (teātri/kino) reāli ir vakara publika, sports = pēc darba */ $bizDaypart = ['Kafejnīca'=>[0.75,0.25],'Restorāns'=>[0.35,0.65],'Krogs'=>[0.15,0.85],'Frizētava'=>[0.6,0.4],'Beķereja'=>[0.85,0.15],'Aptieka'=>[0.75,0.25],'Skaistumkopšana'=>[0.55,0.45],'Minimārkets'=>[0.45,0.55],'Zobārsts'=>[0.65,0.35],'Ātrā ēdināšana'=>[0.6,0.4],'Fitnesa klubs'=>[0.35,0.65],'Viesnīca'=>[0.5,0.5]]; /* tipa apmeklējumu diena/vakars: café slēdzas ap 18-19, restorāns = biznesa pusdienas 25-40% + vakariņas, krogs ~85% pēc 17:00 */ $bizRentMul = ['Fitnesa klubs'=>0.5,'Viesnīca'=>0.55]; /* lielplatības tipi īrē ne-mazumtirdzniecības telpas (pagrabi/2.stāvi) — retail m² likmei koeficients */ function daypartF($sd, $bd){ return 2.0 * ($sd[0]*$bd[0] + $sd[1]*$bd[1]); } /* saskaņas koef.: neitrāls×neitrāls=1.0, saskaņots līdz ~1.4, pretējs līdz ~0.5 */
    // Dinamisko izmaksu konstantes
    define('LABOR_COST_PERSON_DAY', 75.0); $cogsPercentage = ['Kafejnīca' => 0.27, 'Restorāns' => 0.31, 'Krogs' => 0.22, 'Frizētava' => 0.12]; define('VAT_RATE', 0.21); define('UTIL_VAR_PER_VISITOR', 0.05); define('OTHEROP_VAR_PCT', 0.06); define('LOAN_TERM_MONTHS', 60); define('LOAN_ANNUAL_RATE', 0.10);

    /* ── Reģionālā pirktspēja (2026-08-04 kalibrācija) ────────────────────────
       Bāzes čeki/algas/īres ir RĪGAS līmenī, un bez korekcijas Daugavpils
       restorāns modelī pelnīja kā Vecrīgā (reāli Latgales algas ~65-70% no
       Rīgas, čeki un prime īres vēl zemāk). Indekss ≈ CSP vidējā bruto alga
       pilsētā pret Rīgu (2024). Ārpus zonām = mazpilsētu/lauku 0.74. */
    $incomeZones = [ /* [zona, lat, lng, rādiuss_km, indekss] */
        ['riga',       56.950, 24.110, 17, 1.00],
        ['jurmala',    56.970, 23.800, 13, 0.92],
        ['ogre',       56.816, 24.605,  6, 0.90],
        ['sigulda',    57.153, 24.853,  5, 0.88],
        ['tukums',     56.967, 23.155,  5, 0.80],
        ['ventspils',  57.390, 21.564,  7, 0.85],
        ['liepaja',    56.511, 21.011,  7, 0.80],
        ['jelgava',    56.650, 23.721,  7, 0.84],
        ['valmiera',   57.538, 25.427,  6, 0.86],
        ['cesis',      57.313, 25.275,  5, 0.84],
        ['jekabpils',  56.499, 25.878,  6, 0.74],
        ['rezekne',    56.510, 27.332,  6, 0.68],
        ['daugavpils', 55.874, 26.536,  8, 0.67],
    ];
    /* Nemainīgās bāzes — ie_apply_region drīkst izsaukt vairākkārt vienā
       pieprasījumā (idempotenti pārrēķina no bāzes, nevis krāj reizinājumus). */
    $IE_REGION_BASE = ['salary' => $salaryBands, 'rent' => $rentPerM2Month];

    function ie_income_idx(float $lat, float $lng, array $zones): float {
        foreach ($zones as [$n, $zla, $zlo, $rKm, $idx]) {
            $dLat = ($lat - $zla) * 111.32;
            $dLng = ($lng - $zlo) * 111.32 * cos(deg2rad($zla));
            if (sqrt($dLat * $dLat + $dLng * $dLng) <= $rKm) return $idx;
        }
        return 0.74;
    }

    /* Piemēro reģiona indeksu tikko no $dataScenarios paņemtajiem masīviem:
       čeki ~ algām (×idx); apmeklējumu biežums maigāk (ārā-iešana Latgalē
       retāka arī pēc biežuma, ne tikai čeka); algas seko reģiona darba tirgum;
       īres krīt straujāk par algām (prime telpu tirgus mazpilsētās gandrīz
       neeksistē); investīcijas (remonts = darbaspēks) maigi. */
    function ie_apply_region(float $lat, float $lng): void {
        global $incomeZones, $IE_REGION_BASE, $bizVSrc, $bizPSrc, $bizISrc,
               $salaryBands, $rentPerM2Month, $response;
        $idx = ie_income_idx($lat, $lng, $incomeZones);
        $wageMul = 0.35 + 0.65 * $idx;
        $demMul  = 0.25 + 0.75 * $idx;
        $invMul  = 0.60 + 0.40 * $idx;
        /* Cik lielā mērā tipa cenas/pieprasījums ir LOKĀLI: zālēm cenas regulētas
           un vienādas visā LV (0.2), pārtikai tīklu cenas vienādas (0.5),
           zobārstniecība/viesnīcas daļēji (tūristi/pacienti maksā līdzīgāk);
           pakalpojumi un HoReCa — pilnībā lokāli (1.0). */
        $sens = ['Aptieka' => 0.2, 'Minimārkets' => 0.5, 'Zobārsts' => 0.7, 'Viesnīca' => 0.8];
        foreach ($bizPSrc as $t => $arr) {
            $ei = 1.0 - (1.0 - $idx) * ($sens[$t] ?? 1.0);
            foreach ($arr as $k => $v) $bizPSrc[$t][$k] = round($v * $ei, 2);
        }
        foreach ($bizVSrc as $t => $arr) {
            $ed = 1.0 - (1.0 - $demMul) * ($sens[$t] ?? 1.0);
            foreach ($arr as $k => $v) $bizVSrc[$t][$k] = $v * $ed;
        }
        foreach ($bizISrc as $t => $arr) foreach ($arr as $k => $v) $bizISrc[$t][$k] = (int)round($v * $invMul);
        foreach ($IE_REGION_BASE['salary'] as $t => $b) $salaryBands[$t] = [(int)round($b[0] * $wageMul), (int)round($b[1] * $wageMul)];
        foreach ($IE_REGION_BASE['rent'] as $k => $v) $rentPerM2Month[$k] = round($v * pow($idx, 3), 2);
        $response['income_idx'] = $idx;
    }
    /* Tirgus piesātinājums: jauna vieta "atbalstās" tikai tad, kad esošās sasniedz ~2.2×bench
       apmeklētāju. Agrāk koeficients bija 1× (supported ≥ pool/bench), kas apmeklētājus VISUR
       nogrieza zem izdzīvošanas sliekšņa — pelnošo krogu mērogs (~90-100/d pie bench 40; reālie
       pelnošie 5630 vid. 368k€/g) bija strukturāli nesasniedzams, tāpēc karte HoReCa rādīja
       mīnusus pat labākajās vietās. 2.2 ≈ reālais pelnošo Q3/mediānas mērogs. */
    define('SUPPORT_SATURATION', 2.2);
    /* Investīciju finansējums: pēc noklusējuma 100% kredīts (10%/5g) — godīgi jaunam biznesam,
       bet slēpj, cik mīnusa rada tieši kredīts (reāli izdzīvojušie krogi procentos maksā ~100€/mēn).
       UI izvēles rūtiņa ļauj rēķināt no pašu kapitāla (loan=false). */
    $loanShare = (isset($_GET['loan']) && $_GET['loan'] === 'false') ? 0.0 : 1.0;
    // --- Helper Funkcijas ---
    function getVisitProbability($resIdx, $estIdx){ if($resIdx===null||$estIdx===null)return 0.0; $d=$estIdx-$resIdx; if($d===0)return 1.0; if($d>0){ $u=[1=>0.65,2=>0.35,3=>0.15,4=>0.05]; return $u[$d]??0.0; } $l=[1=>0.90,2=>0.80,3=>0.70,4=>0.60]; return $l[-$d]??0.0; } function loanMonthlyPayment($principal){ if($principal<=0)return 0.0; $r=LOAN_ANNUAL_RATE/12.0; if($r<=0)return $principal/LOAN_TERM_MONTHS; return $principal*$r/(1-pow(1+$r,-LOAN_TERM_MONTHS)); } function bizStaffNeeded($type, $visitorsDay){ global $prodPerWorker, $minStaff; $p = $prodPerWorker[$type] ?? 40; return max($minStaff[$type] ?? 2, (int)ceil(max(0.0,$visitorsDay) * 1.3 / $p)); } function bizSalaryGross($type, $areaIdx, $preLaborMargin){ global $salaryBands; $b = $salaryBands[$type] ?? [800,1500]; $areaPos = max(0,min(4,$areaIdx))/4.0; $areaFloor = $areaPos*0.55; $perf = max(0.0,min(1.0,($preLaborMargin-0.40)/0.25)); $bandPos = max(0.0,min(1.0,$areaFloor+(1.0-$areaFloor)*$perf)); return $b[0]+$bandPos*($b[1]-$b[0]); } function bizComputeProfit($type, $pos, $sz, $compCount, $areaIdx){ global $useResidents, $officeByLvlDemand, $officeVisitRate, $tourismFootfallDemand, $tourismRate, $instByCatDemand, $instVisitRate, $daypartSrc, $daypartInst, $bizDaypart, $footfallBench, $bizResPerVenue, $total_iedzivotaji, $buildingsData, $bizVSrc, $bizPSrc, $bizISrc, $cogsBands, $bizUtilM2, $bizOpFixMonth, $rentPerM2Month, $bizRentMul, $averageRadiusPPL, $bizDaysOpen, $prodPerWorker, $minStaff, $loanShare; $m2=$sz['m2']; $do=$bizDaysOpen[$type] ?? 30; $raw=0.0; foreach($buildingsData as $b){ if($b['iedzivotaji']==0)continue; $ri=$b['levelIndex']; if($ri===null)continue; $raw += $b['iedzivotaji']*(($bizVSrc[$type][$ri]??0)/1000.0)*getVisitProbability($ri,$pos); } if (!($useResidents ?? true)) { $raw = 0.0; } $bdp = $bizDaypart[$type] ?? [0.5,0.5]; $raw *= daypartF($daypartSrc['res'], $bdp); /* iedzīvotāji = vakara publika: krogam/restorānam pilnvērtīgi, dienas kafejnīcai mazāk */ $officeDem = 0.0; foreach (($officeByLvlDemand ?? []) as $oli => $ow) { $officeDem += $ow * ($officeVisitRate[$type] ?? 0) * getVisitProbability($oli < 0 ? $areaIdx : $oli, $pos); } /* katra biroja ēka sver ar SAVU pirktspēju (pēc kadastrālās vērtības €/m²); bez lvl -> rajona vidējais */ $officeDem *= daypartF($daypartSrc['office'], $bdp); $touristDem = ($tourismFootfallDemand ?? 0) * ($tourismRate[$type] ?? 0) * daypartF($daypartSrc['tourist'], $bdp) * getVisitProbability(TOUR_PPL_IDX, $pos); $instDem = 0.0; foreach (($instByCatDemand ?? []) as $icat => $ippl) { $instDem += $ippl * ($instVisitRate[$icat][$type] ?? 0) * daypartF($daypartInst[$icat] ?? [0.5,0.5], $bdp); } $instDem *= getVisitProbability(INST_PPL_IDX, $pos); /* iestāžu plūsmas jau attīrītas pieplūdē (tikai tie, kas iet ārā) */ $bench = $footfallBench[$type] ?? 45; $checkMid = $bizPSrc[$type][2] ?? 0; $checkPos = $bizPSrc[$type][$pos] ?? 0; $benchPos = ($checkMid > 0 && $checkPos > 0) ? $bench * pow($checkMid / $checkPos, BENCH_PRICE_ELASTICITY) : $bench; $pool = max($raw + $officeDem + $touristDem + $instDem, $compCount * $bench); $theoRes = ($bizResPerVenue[$type]>0)?($total_iedzivotaji/$bizResPerVenue[$type]):0; $supported = max($compCount, $theoRes, $pool / (SUPPORT_SATURATION * $benchPos)); $vis = min($pool/($supported + 1.0), $sz['cap']); if (empty($GLOBALS['considerCompetitors']) && $compCount > 0) { $vis = min($vis * (1.0 + $compCount/($compCount + 5.0)), $sz['cap']); } /* Personāla optimizācija: apkalpo tik, cik IZDEVĪGI, ne tik, cik atnāk — pārbaudām VISUS
       personāla līmeņus no minimālā līdz pilnajam (agrāk tikai 1 soli uz leju; pie liela
       pieprasījuma un lēta čeka tas lika apkalpot visus ar milzu algu rēķinu un absurdiem
       mīnusiem tieši tur, kur pieprasījums vislielākais). */ $cands = [$vis]; $sfull = bizStaffNeeded($type, $vis); $prodW = $prodPerWorker[$type] ?? 20; $mS = $minStaff[$type] ?? 2; for ($s = $mS; $s < $sfull; $s++) { $vAlt = $s * $prodW / 1.3; if ($vAlt > 0 && $vAlt < $vis) { $cands[] = $vAlt; } } $bestOut = null; foreach ($cands as $v) { $pnet = ($bizPSrc[$type][$pos]??0)/(1+VAT_RATE); $net = $v*$pnet*$do; $cogsPct = $cogsBands[$type][$pos] ?? 0.30; $cogs = $net*$cogsPct; $rent = $m2*($rentPerM2Month[$averageRadiusPPL]??13.0)*($bizRentMul[$type] ?? 1.0); $util = $m2*($bizUtilM2[$type]??7) + UTIL_VAR_PER_VISITOR*$v*$do; $op = ($bizOpFixMonth[$type]??450)*$sz['opMul'] + $net*OTHEROP_VAR_PCT; $inv = ($bizISrc[$type][$pos]??0)*$sz['invMul']; $loan = loanMonthlyPayment($inv * $loanShare); $preLabor = $net - ($cogs+$rent+$util+$op+$loan); $plm = $net>0 ? $preLabor/$net : -1; $salary = bizSalaryGross($type,$areaIdx,$plm); $staff = bizStaffNeeded($type,$v); $labor = $staff*$salary*1.2359; $profit = $preLabor - $labor; $out = ['visitors'=>$v,'net'=>$net,'cogs'=>$cogs,'rent'=>$rent,'util'=>$util,'op'=>$op,'loan'=>$loan,'labor'=>$labor,'salary'=>$salary,'staff'=>$staff,'cogs_pct'=>$cogsPct,'profit'=>$profit,'investment'=>$inv,'check'=>$bizPSrc[$type][$pos]??0,'m2'=>$m2,'size'=>$sz['name'],'positioning'=>['E','D','C','B','A'][$pos]]; if ($bestOut === null || $profit > $bestOut['profit']) { $bestOut = $out; } } return $bestOut; } function bizMarket($type){ global $competitorsInternalData; return isset($competitorsInternalData[$type]) ? count($competitorsInternalData[$type]) : 0; } function bizSeasonZone($lat, $lng){ global $seasonZones; foreach ($seasonZones as $z) { if (simpleDistance($lat, $lng, $z[1], $z[2]) <= $z[3]*1000) return $z[0]; } if ($lat >= 56.85 && $lat <= 57.10 && $lng >= 23.95 && $lng <= 24.35) return 'riga'; return 'iekszeme'; }
    function getCompetitorRetentionProbability($d){ /* ... NEMAINĪTS ... */ if($d<=100)return 0.60;if($d<=200)return 0.70;if($d<=300)return 0.75;if($d<=400)return 0.85;if($d<=500)return 0.90;return 1.0;}
    function simpleDistance($la1,$lo1,$la2,$lo2){ /* ... NEMAINĪTS ... */ if($la1==$la2&&$lo1==$lo2)return 0;$eR=6371000;$fLa=deg2rad($la1);$fLo=deg2rad($lo1);$tLa=deg2rad($la2);$tLo=deg2rad($lo2);$dLa=$tLa-$fLa;$dLo=$tLo-$fLo;$a=2*asin(sqrt(pow(sin($dLa/2),2)+cos($fLa)*cos($tLa)*pow(sin($dLo/2),2)));return $a*$eR;}
    // --- AJAX pieprasījumu apstrāde ---
    $response = ['error' => null, 'points' => [], 'points_count' => 0, 'statistics' => [], 'type' => null, 'competitors_found' => [], 'competitor_impact_applied' => false, 'scenario_used' => 'realistic', 'radius_used' =>500];
    // 1. Meklēšana rādiusā
    if ($_GET['action'] === 'search') { /* ... Viss search action kods paliek nemainīgs ... */ $response['type'] = 'search_results'; $colEka = 'building_id'; $colCilveki = 'residents'; $colLevel = 'lvl'; $colLocation = 'location'; $colName = 'name'; $latClick = isset($_GET['lat']) ? filter_var($_GET['lat'], FILTER_VALIDATE_FLOAT) : false; $lngClick = isset($_GET['lng']) ? filter_var($_GET['lng'], FILTER_VALIDATE_FLOAT) : false; $considerCompetitors = isset($_GET['competitors']) && $_GET['competitors'] === 'true'; $useResidents = !(isset($_GET['lres']) && $_GET['lres'] === 'false'); $useOffices = !(isset($_GET['loff']) && $_GET['loff'] === 'false'); $useTourism = !(isset($_GET['ltour']) && $_GET['ltour'] === 'false'); $useInstitutions = !(isset($_GET['linst']) && $_GET['linst'] === 'false'); $scenario = (isset($_GET['scenario']) && in_array($_GET['scenario'], ['pessimistic','realistic','optimistic'], true)) ? $_GET['scenario'] : 'realistic'; $radius = 500; if (isset($_GET['radius'])) { $inputRadius = filter_var($_GET['radius'], FILTER_VALIDATE_INT); if ($inputRadius !== false && $inputRadius >= 100 && $inputRadius <= 5000) { $radius = $inputRadius; } else { error_log("PHP WARNING: Invalid radius parameter: " . htmlspecialchars($_GET['radius'])); } } $response['radius_used'] = $radius; $response['scenario_used'] = $scenario; $response['competitor_impact_applied'] = $considerCompetitors; if ($latClick === false || $lngClick === false) { $response['error'] = "Nederīgi koordinātu parametri."; echo json_encode($response); exit; } $centerPointSQL = "POINT(?,?)"; try { $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4"; $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, (defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? Pdo\Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND) => "SET NAMES utf8mb4"]); // --- Bounding-box priekšfiltrs, lai izmantotu SPATIAL INDEX(location) ---
        // ST_Distance_Sphere WHERE daļā neizmanto R-tree indeksu -> pilns tabulas skenējums (~337k rindas).
        // Sašaurinām kandidātus ar taisnstūri (MBRContains lieto indeksu), tad precizējam ar to pašu
        // sfērisko attālumu -> gala rezultāts paliek APLIS; taisnstūris ir tikai iekšējs ātruma filtrs.
        $mPerDegLat = 111320.0; $mPerDegLng = 111320.0 * cos(deg2rad($latClick));
        if ($mPerDegLng < 1.0) { $mPerDegLng = 1.0; } // drošība pret dalīšanu ar ~0 (Latvijā nav aktuāli)
        $bboxMargin = 1.05; // neliela rezerve pret grādu->metru tuvinājumu
        $dLat = ($radius / $mPerDegLat) * $bboxMargin; $dLng = ($radius / $mPerDegLng) * $bboxMargin;
        $minLat = $latClick - $dLat; $maxLat = $latClick + $dLat; $minLng = $lngClick - $dLng; $maxLng = $lngClick + $dLng;
        // %F = lokāles-neatkarīgs (vienmēr '.'), lai lv_LV komats nesabojā WKT. Koordinātas lon lat secībā, kā glabāts.
        $bboxWKT = sprintf('POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))', $minLng, $minLat, $maxLng, $minLat, $maxLng, $maxLat, $minLng, $maxLat, $minLng, $minLat);
        /* Ēku slānis var būt sadalīts pa reģioniem (lielās valstīs). ie_shards_for_bbox() atgriež VISAS tabulas, ko skar taisnstūris — pierobežā to ir vairāk par vienu, un tad tās jāsavieno, citādi puse rezultāta pazūd. Latvijā šķēlums ir viens, tāpēc UNION nav, un vaicājums ir tieši tāds pats kā līdz šim. */ $ieShards = ie_shards_for_bbox($minLng, $minLat, $maxLng, $maxLat); $ieParts = []; $ieParams = []; foreach ($ieShards as $ieShard) { $ieParts[] = "SELECT `{$colEka}`,`{$colCilveki}`,`{$colLevel}`,ST_Distance_Sphere({$centerPointSQL},`{$colLocation}`)AS distance,ST_Y(`{$colLocation}`)AS disp_lat,ST_X(`{$colLocation}`)AS disp_lng FROM`{$ieShard}`WHERE MBRContains(ST_GeomFromText(?,4326),`{$colLocation}`) AND ST_Distance_Sphere({$centerPointSQL},`{$colLocation}`)<=?"; array_push($ieParams, $lngClick, $latClick, $bboxWKT, $lngClick, $latClick, $radius); } $sqlBuildings = implode(" UNION ALL ", $ieParts); $stmtBuildings = $pdo->prepare($sqlBuildings); if (!$stmtBuildings->execute($ieParams)) { throw new Exception("SQL buildings query failed."); } $buildingsData = []; $total_iedzivotaji = 0; $level_building_counts = array_fill_keys(array_keys($levelColorsPHP), 0); $level_people_counts = array_fill_keys(array_keys($levelColorsPHP), 0); $weightedPPLSum = 0; $totalPeopleForPPL = 0; while ($row = $stmtBuildings->fetch()) { $iedzivotaji_int = (isset($row[$colCilveki]) && is_numeric($row[$colCilveki])) ? intval($row[$colCilveki]) : 0; $level_val = (isset($row[$colLevel]) && isset($levelColorsPHP[strtoupper($row[$colLevel])])) ? strtoupper($row[$colLevel]) : 'NULL'; $lat_float = (isset($row['disp_lat']) && is_numeric($row['disp_lat'])) ? floatval($row['disp_lat']) : null; $lng_float = (isset($row['disp_lng']) && is_numeric($row['disp_lng'])) ? floatval($row['disp_lng']) : null; if ($lat_float !== null && $lng_float !== null) { $levelIndex = ($level_val !== 'NULL' && isset($pplToIndex[$level_val])) ? $pplToIndex[$level_val] : null; $buildingInfo = ['kadastrs' => $row[$colEka] ?? 'N/A', 'iedzivotaji' => $iedzivotaji_int, 'level' => $level_val, 'levelIndex' => $levelIndex, 'distance_from_center' => (isset($row['distance']) && is_numeric($row['distance'])) ? floatval($row['distance']) : null, 'lat' => $lat_float, 'lng' => $lng_float]; $buildingsData[] = $buildingInfo; $response['points'][] = $buildingInfo; $total_iedzivotaji += $iedzivotaji_int; if ($level_val !== 'NULL') { $level_building_counts[$level_val]++; $level_people_counts[$level_val] += $iedzivotaji_int; if ($levelIndex !== null && $iedzivotaji_int > 0) { $weightedPPLSum += ($levelIndex * $iedzivotaji_int); $totalPeopleForPPL += $iedzivotaji_int; } } else { $level_building_counts['NULL']++; } } else { error_log("PHP WARNING (search): Skipped building."); } } $response['points_count'] = count($response['points']); $averageRadiusPPLIndex = null; $averageRadiusPPL = 'C'; if ($totalPeopleForPPL > 0) { $averageRadiusPPLIndex = round($weightedPPLSum / $totalPeopleForPPL); $averageRadiusPPL = $indexToPPL[$averageRadiusPPLIndex] ?? 'C'; } $competitorsInternalData = []; $competitorsResponseData = []; foreach (array_keys($typeToPtype) as $typeKey) { $competitorsResponseData[$typeKey] = ['count' => 0, 'names' => []]; } if (true) { /* konkurentu dati VIENMĒR — slēdzis kontrolē tikai dalīšanu */ /* MBRContains PIRMS attāluma: ST_Distance_Sphere WHERE daļā R-tree indeksu neizmanto, tāpēc bez šī priekšfiltra katrs klikšķis skenēja visu POI tabulu. Ēku vaicājumā tas bija salabots jau sen, konkurentu ciklā — nē. */ foreach ($typeToPtype as $estType => $compPtype) { $sqlCompetitors = "SELECT`{$colName}`,ST_Y(`{$colLocation}`)AS lat,ST_X(`{$colLocation}`)AS lng FROM`{$tblPoi}`WHERE MBRContains(ST_GeomFromText(?,4326),`{$colLocation}`) AND `ptype`=? AND ST_Distance_Sphere({$centerPointSQL},`{$colLocation}`)<=?"; $stmtCompetitors = $pdo->prepare($sqlCompetitors); if (!$stmtCompetitors->execute([$bboxWKT, $compPtype, $lngClick, $latClick, $radius])) { error_log("PHP WARNING: SQL competitors failed for ptype: ".$compPtype); continue; } $competitorsInternalData[$estType] = []; while ($compRow = $stmtCompetitors->fetch()) { $compLat = (isset($compRow['lat']) && is_numeric($compRow['lat'])) ? floatval($compRow['lat']) : null; $compLng = (isset($compRow['lng']) && is_numeric($compRow['lng'])) ? floatval($compRow['lng']) : null; $compName = $compRow[$colName] ?? 'N/A'; if ($compLat !== null && $compLng !== null) { $assignedLevel = 'NULL'; $assignedLevelIndex = null; $minDistToBuilding = PHP_FLOAT_MAX; foreach ($buildingsData as $bldg) { if ($bldg['level'] !== 'NULL') { $dist = simpleDistance($compLat, $compLng, $bldg['lat'], $bldg['lng']); if ($dist < $minDistToBuilding) { $minDistToBuilding = $dist; $assignedLevel = $bldg['level']; $assignedLevelIndex = $bldg['levelIndex']; } } } $competitorsInternalData[$estType][] = ['name' => $compName, 'lat' => $compLat, 'lng' => $compLng, 'assigned_level' => $assignedLevel, 'assigned_level_index' => $assignedLevelIndex]; if (isset($competitorsResponseData[$estType])) { $competitorsResponseData[$estType]['names'][] = $compName; $competitorsResponseData[$estType]['count']++; } } } } } $response['competitors_found'] = $competitorsResponseData; $donutColorsPHP = ['A' => 'darkgreen', 'B' => 'lime', 'C' => 'dodgerblue', 'D' => 'orange', 'E' => 'red', 'NULL' => '#AAAAAA']; $statistics_level_building_counts = []; $statistics_level_people_counts = []; $statistics_level_percentages = []; $statistics_level_donut_colors = []; uksort($level_building_counts, function ($a, $b) use ($pplToIndex) { $o=array_flip(array_keys($pplToIndex)); $an=($a==='NULL'); $bn=($b==='NULL'); if($an&&$bn)return 0; if($an)return 1; if($bn)return -1; return ($o[$b]??99) <=> ($o[$a]??99); }); $total_non_null_buildings = 0; foreach($level_building_counts as $l => $c) { if($l !== 'NULL') $total_non_null_buildings += $c; } foreach($level_building_counts as $l => $c) { if ($c > 0) { $statistics_level_building_counts[$l] = $c; $statistics_level_people_counts[$l] = $level_people_counts[$l] ?? 0; if($l !== 'NULL' && $total_non_null_buildings > 0) { $statistics_level_percentages[$l] = round(($c / $total_non_null_buildings) * 100, 1); } elseif ($l === 'NULL') { $statistics_level_percentages[$l] = ($response['points_count'] > 0) ? round(($c / $response['points_count']) * 100, 1) : 0; } else { $statistics_level_percentages[$l] = 0; } $statistics_level_donut_colors[$l] = $donutColorsPHP[$l] ?? '#AAAAAA'; } } $officeWorkers = 0; $officeCount = 0; $officesFound = []; $officeByLvl = []; try { $stmtOff = $pdo->prepare("SELECT `building_id` AS `eka`,`workers`,`lvl`,ST_Y(`location`) la,ST_X(`location`) lo,ST_Distance_Sphere({$centerPointSQL},`location`) dist FROM `{$tblOff}` WHERE MBRContains(ST_GeomFromText(?,4326),`location`) AND ST_Distance_Sphere({$centerPointSQL},`location`)<=? ORDER BY `workers` DESC"); $stmtOff->execute([$lngClick, $latClick, $bboxWKT, $lngClick, $latClick, $radius]); while ($orow = $stmtOff->fetch()) { $w = (int)$orow["workers"]; $officeWorkers += $w; $officeCount++; $olv = strtoupper((string)($orow["lvl"] ?? '')); $oli = $pplToIndex[$olv] ?? -1; $officeByLvl[$oli] = ($officeByLvl[$oli] ?? 0) + $w; if (count($officesFound) < 300) { $officesFound[] = ["eka"=>$orow["eka"], "workers"=>$w, "lvl"=>(isset($pplToIndex[$olv]) ? $olv : null), "lat"=>(float)$orow["la"], "lng"=>(float)$orow["lo"], "dist"=>round((float)$orow["dist"])]; } } } catch (Exception $e) { $officeWorkers = 0; $officeCount = 0; $officesFound = []; $officeByLvl = []; } $response["offices_found"] = $officesFound; /*
        ── JAUKTAIS PIRKTSPĒJAS INDEKSS (2026-07-30) ─────────────────────────
        Līdz šim $averageRadiusPPL — indekss, kas nosaka NOMU (rentPerM2Month)
        un ALGU grīdu (bizSalaryGross) — nāca TIKAI no dzīvojamām ēkām. Biroju
        kvartālā ar 40 trūcīgiem iedzīvotājiem un 1450 B-līmeņa biroju
        darbiniekiem (Ilzenes/Rankas stūris) tas deva graustu nomu 6 €/m² pie
        premium pieprasījuma — peļņas cipari tur bija mākslīgi uzpūsti.

        PAMATOJUMS (mērīts 2026-07-30, tools ziņojums sarunā):
        · 264 veikalu punkti pret CITU veikalu kadastrālo €/m² 500 m rādiusā:
          Spearman 0.47 (tikai iedzīvotāji) → 0.63 (ar birojiem, svars 1.0);
          ārpus Rīgas 0.43→0.66; biroju zonās (ow>5×rw) 0.32→0.56.
        · Tirgus enkuri: Teikas veikala pārdošana 1667 €/m² ⇒ noma ~11–13 →
          jaunais C=13 trāpa (vecais E=6 kļūdījās 2×); Skanstes Verde biroji
          18 €/m² — jaunais A=26 der ielas tirdzniecībai jaunajos projektos.
        · Ietekme ķirurģiska: 4000 nejaušās apbūves vietās līmenis mainās 9 %
          gadījumu (5 % kāpj, 4 % krīt), pārējos nemainās.

        Iestāžu plūsmu (skolēni!) un tūristus indeksā apzināti NEliekam —
        skolēnu D-līmeņa masa nepamatoti nosistu centra indeksus; pierādījumi
        ir tikai par birojiem. */
        $residentsPPL = $averageRadiusPPL;
        $blendW = (float)$totalPeopleForPPL; $blendSum = (float)$weightedPPLSum;
        foreach ($officeByLvl as $oliB => $wB) {
            if ($oliB >= 0 && $wB > 0) { $blendW += $wB; $blendSum += $oliB * $wB; }
        }
        if ($blendW > 0) {
            $averageRadiusPPLIndex = (int)round($blendSum / $blendW);
            $averageRadiusPPL = $indexToPPL[$averageRadiusPPLIndex] ?? 'C';
        } $tourismScore = 0; $tourismCount = 0; $tourismFound = []; try { $stmtT = $pdo->prepare("SELECT `tname`,`ttype`,`score`,ST_Y(`location`) la,ST_X(`location`) lo,ST_Distance_Sphere({$centerPointSQL},`location`) dist FROM `{$tblTour}` WHERE MBRContains(ST_GeomFromText(?,4326),`location`) AND ST_Distance_Sphere({$centerPointSQL},`location`)<=? ORDER BY `score` DESC"); $stmtT->execute([$lngClick, $latClick, $bboxWKT, $lngClick, $latClick, $radius]); $stayTypes = ie_country()['tourism']['stay_types']; $stayComps = []; while ($trow = $stmtT->fetch()) { $sc = (float)$trow["score"]; $tourismScore += $sc; $tourismCount++; if (in_array($trow["ttype"], $stayTypes, true)) { $stayComps[] = ['name' => ($trow["tname"] !== '' && $trow["tname"] !== null) ? $trow["tname"] : '(naktsmītne)', 'lat' => (float)$trow["la"], 'lng' => (float)$trow["lo"], 'assigned_level' => 'NULL', 'assigned_level_index' => null]; } if (count($tourismFound) < 100) { $tourismFound[] = ["name"=>$trow["tname"], "type"=>$trow["ttype"], "score"=>round($sc), "lat"=>(float)$trow["la"], "lng"=>(float)$trow["lo"], "dist"=>round((float)$trow["dist"])]; } } /* Viesnīcas konkurenti = naktsmītnes no tūrisma slāņa */ $competitorsInternalData['Viesnīca'] = $stayComps; $competitorsResponseData['Viesnīca'] = ['count' => count($stayComps), 'names' => array_slice(array_column($stayComps, 'name'), 0, 40)]; $response['competitors_found'] = $competitorsResponseData; } catch (Exception $e) { $tourismScore = 0; $tourismCount = 0; $tourismFound = []; } $tourismCluster = min(2.5, 1.0 + 0.12 * sqrt(max(0, $tourismCount - 1))); $tourismFootfall = $tourismScore * $tourismCluster; $response["tourism_found"] = $tourismFound; $instPeople = 0; $instCount = 0; $instByCat = []; $instFound = []; try { $stmtI = $pdo->prepare("SELECT `building_id` AS `eka`,`cat`,`people`,ST_Y(`location`) la,ST_X(`location`) lo,ST_Distance_Sphere({$centerPointSQL},`location`) dist FROM `{$tblInst}` WHERE MBRContains(ST_GeomFromText(?,4326),`location`) AND ST_Distance_Sphere({$centerPointSQL},`location`)<=? ORDER BY `people` DESC"); $stmtI->execute([$lngClick, $latClick, $bboxWKT, $lngClick, $latClick, $radius]); while ($irow = $stmtI->fetch()) { $p = (int)round(((int)$irow["people"]) * ($instOutflow[$irow["cat"]] ?? 1.0)); /* uzreiz tikai ārā ejošā plūsma — pārējie nav nevienā aprēķinā */ if ($p < 1) { continue; } $instPeople += $p; $instCount++; $instByCat[$irow["cat"]] = ($instByCat[$irow["cat"]] ?? 0) + $p; if (count($instFound) < 150) { $instFound[] = ["eka"=>$irow["eka"], "cat"=>$irow["cat"], "people"=>$p, "lat"=>(float)$irow["la"], "lng"=>(float)$irow["lo"], "dist"=>round((float)$irow["dist"])]; } } } catch (Exception $e) { $instPeople = 0; $instCount = 0; $instByCat = []; $instFound = []; } $response["institutions_found"] = $instFound; $officeWorkersDemand = $useOffices ? $officeWorkers : 0; $officeByLvlDemand = $useOffices ? $officeByLvl : []; $tourismFootfallDemand = $useTourism ? $tourismFootfall : 0; $instByCatDemand = $useInstitutions ? $instByCat : []; $instPeopleDay = 0; foreach ($instByCat as $ioc => $iop) { $instPeopleDay += $iop * (($daypartInst[$ioc] ?? [0.5,0.5])[0]); } $bizVSrc = $dataScenarios[$scenario]['visitorsPer1000']; $bizPSrc = $dataScenarios[$scenario]['averagePrice']; $bizISrc = $dataScenarios[$scenario]['initialInvestment']; ie_apply_region((float)$latClick, (float)$lngClick); $areaIdx = $pplToIndex[$averageRadiusPPL] ?? 2; $seasonZone = bizSeasonZone($latClick, $lngClick); $optimal_setups = []; $bestTierByType = []; if ($total_iedzivotaji > 0) { foreach ($establishmentTypes as $type) { $compCountO = bizMarket($type); $best = null; $bestSz = null; foreach (($sizeTiers[$type] ?? []) as $sz) { for ($pos=0; $pos<5; $pos++) { $r = bizComputeProfit($type, $pos, $sz, $compCountO, $areaIdx); if ($best === null || $r['profit'] > $best['profit_month']) { $best = ['size'=>$r['size'],'m2'=>$r['m2'],'staff'=>$r['staff'],'check'=>round($r['check'],2),'salary'=>round($r['salary'],0),'investment'=>round($r['investment'],0),'visitors'=>round($r['visitors'],0),'revenue_month'=>round($r['net'],0),'profit_month'=>round($r['profit'],0),'positioning'=>$r['positioning'],'cogs_pct'=>round($r['cogs_pct']*100),'labor_month'=>round($r['labor'])]; $bestSz = $sz; } } } if ($best !== null && $bestSz !== null) { /* čeku simulācija ar TO PAŠU optimālo izmēru — lai popup tabula sakrīt ar virsrakstu */ $best['sim'] = []; for ($p2 = 0; $p2 < 5; $p2++) { $r2 = bizComputeProfit($type, $p2, $bestSz, $compCountO, $areaIdx); $best['sim'][] = ['check'=>round($r2['check'],2),'visitors'=>round($r2['visitors'],1),'revenue'=>round($r2['net']),'profit'=>round($r2['profit'])]; } } if ($best !== null) { $best['profit_y1'] = (int)round($best['profit_month'] - RAMP_Y1_PCT * $best['revenue_month']); $best['profit_y2'] = (int)round($best['profit_month'] - RAMP_Y2_PCT * $best['revenue_month']); if (in_array($type, ['Kafejnīca','Restorāns','Krogs','Beķereja','Ātrā ēdināšana','Viesnīca'], true)) { /* VID sezonalitāte mērīta HoReCa — galamērķa tipiem (aptieka/zobārsts/...) nepiemēro */ $zi = $seasonIdx[$seasonZone] ?? [1,1,1,1]; $contrib = $best['revenue_month'] * (1 - $best['cogs_pct']/100.0 - OTHEROP_VAR_PCT) - $best['labor_month'] * 0.6; $best['profit_summer'] = (int)round($best['profit_month'] + ($zi[2]-1) * $contrib); $best['profit_winter'] = (int)round($best['profit_month'] + ($zi[0]-1) * $contrib); $best['season_zone'] = $seasonZone; } } $bestTierByType[$type] = $bestSz; $optimal_setups[$type] = $best; } } $profitability_results = []; if ($total_iedzivotaji > 0) { foreach ($establishmentTypes as $type) { $profitability_results[$type] = []; $tiers = $sizeTiers[$type] ?? []; $defSz = $bestTierByType[$type] ?? $tiers[(int)floor(count($tiers)/2)] ?? ['name'=>'-','m2'=>60,'cap'=>300,'invMul'=>1.0,'opMul'=>1.0]; /* paneļa tabula ar TĀ PAŠA optimālā izmēra tieru — sakrīt ar popup */ $compCount = bizMarket($type); $doM = $bizDaysOpen[$type] ?? 30; for ($estPPLIndex = 0; $estPPLIndex < 5; $estPPLIndex++) { $establishmentPPL = $indexToPPL[$estPPLIndex]; $r = bizComputeProfit($type, $estPPLIndex, $defSz, $compCount, $areaIdx); $vatable = $r['cogs'] + $r['rent'] + $r['util'] + $r['net']*OTHEROP_VAR_PCT; $pvn_month = VAT_RATE * max(0, $r['net'] - $vatable); $minLabor = ($minStaff[$type] ?? 2) * $r['salary'] * 1.2359; $fixedMonth = $r['rent'] + $defSz['m2']*($bizUtilM2[$type]??7) + ($bizOpFixMonth[$type]??450)*$defSz['opMul'] + $r['loan'] + $minLabor; $netCheck = $r['check']/(1+VAT_RATE); $cpc = $netCheck*(1 - $r['cogs_pct'] - OTHEROP_VAR_PCT) - UTIL_VAR_PER_VISITOR; $beVis = ($cpc>0) ? (int)ceil(($fixedMonth/$doM)/$cpc) : null; $costTotal = $r['cogs']+$r['rent']+$r['util']+$r['op']+$r['loan']+$r['labor']; $profitability_results[$type][$establishmentPPL] = ['visitors'=>round($r['visitors'],1),'price'=>$r['check'],'revenue'=>round($r['net'],0),'cost_staff'=>round($r['labor'],0),'cost_rent'=>round($r['rent'],0),'cost_cogs'=>round($r['cogs'],0),'cost_pvn'=>round($pvn_month,0),'cost_utilities'=>round($r['util'],0),'cost_other_op'=>round($r['op'],0),'cost_loan'=>round($r['loan'],0),'cost_total'=>round($costTotal,0),'profit'=>round($r['profit'],0),'salary'=>round($r['salary'],0),'staff'=>$r['staff'],'cogs_pct'=>round($r['cogs_pct']*100),'breakeven_visitors'=>$beVis,'investment'=>round($r['investment'],0)]; } } }
/* ── Pesimistiskais diapazons popupam (2026-08-04) ──────────────────────────
   Katram tipam labākā (izmērs × pozicionējums) mēneša peļņa PESIMISTISKAJĀ
   scenārijā — popup rinda rāda "Reāli … (pesim. …)", lai lejupvērstais risks
   ir redzams bez scenārija pārslēgšanas. Tīrs PHP pārrēķins ar jau nolasītiem
   pieprasījuma datiem; DB vaicājumi NETIEK atkārtoti. ie_apply_region atkārtots
   izsaukums ir idempotents (algas/īres pārrēķina no bāzes ar to pašu indeksu). */
$pessimistic_optimal = null;
if ($total_iedzivotaji > 0) {
    $savedV = $bizVSrc; $savedP = $bizPSrc; $savedI = $bizISrc;
    $bizVSrc = $dataScenarios['pessimistic']['visitorsPer1000'];
    $bizPSrc = $dataScenarios['pessimistic']['averagePrice'];
    $bizISrc = $dataScenarios['pessimistic']['initialInvestment'];
    ie_apply_region((float)$latClick, (float)$lngClick);
    $pessimistic_optimal = [];
    foreach ($establishmentTypes as $type) {
        $compCountP = bizMarket($type);
        $bestPr = null;
        foreach (($sizeTiers[$type] ?? []) as $szP) {
            for ($piP = 0; $piP < 5; $piP++) {
                $rP = bizComputeProfit($type, $piP, $szP, $compCountP, $areaIdx);
                if ($bestPr === null || $rP['profit'] > $bestPr) $bestPr = $rP['profit'];
            }
        }
        if ($bestPr !== null) $pessimistic_optimal[$type] = (int)round($bestPr);
    }
    $bizVSrc = $savedV; $bizPSrc = $savedP; $bizISrc = $savedI;
}
$response['statistics'] = [ 'pessimistic_optimal' => $pessimistic_optimal, 'total_iedzivotaji' => $total_iedzivotaji, 'office_workers' => $officeWorkers ?? 0, 'office_count' => $officeCount ?? 0, 'tourism_count' => $tourismCount ?? 0, 'tourism_footfall' => round($tourismFootfall ?? 0), 'inst_count' => $instCount ?? 0, 'inst_people' => $instPeople ?? 0, 'inst_people_day' => (int)round($instPeopleDay ?? 0), 'office_by_lvl' => (function() use ($officeByLvl, $indexToPPL) { $o = []; foreach ($officeByLvl as $oli => $w) { $k = ($oli >= 0 && isset($indexToPPL[$oli])) ? $indexToPPL[$oli] : 'NULL'; $o[$k] = ($o[$k] ?? 0) + $w; } return $o; })(), 'average_radius_ppl' => $averageRadiusPPL, 'residents_ppl' => $residentsPPL ?? $averageRadiusPPL, 'level_percentages' => $statistics_level_percentages, 'level_counts' => $statistics_level_building_counts, 'level_people_counts' => $statistics_level_people_counts, 'level_colors' => $statistics_level_donut_colors, 'profitability_results' => $profitability_results ?? null, 'optimal_setups' => $optimal_setups ?? null ]; } catch (PDOException $e) { $response['error'] = "DB Kļūda: " . $e->getMessage(); error_log("PHP PDO ERROR: " . $e->getMessage()); } catch (Exception $e) { $response['error'] = "Servera Kļūda: " . $e->getMessage(); error_log("PHP ERROR: " . $e->getMessage()); }
    // 2. POI ielāde
    } elseif ($_GET['action'] === 'heatmap') { $response['type'] = 'heatmap';
        // SILTUMKARTE: 20x20 režģis redzamajā apgabalā; visi dati vienā ielādē + telpiskās kastes; matemātika sinhronā ar bizComputeProfit
        $hmType = $_GET['btype'] ?? 'Kafejnīca'; $hmPopMode = ($hmType === 'PopDiena') ? 'day' : (($hmType === 'PopNakts') ? 'night' : null); /* populācijas siltumkarte: cilvēki šūnā pa diennakts daļām, bez biznesa optimizācijas */
        if ($hmPopMode === null && !in_array($hmType, $establishmentTypes)) { $response['error'] = 'Nederīgs biznesa tips.'; echo json_encode($response); exit; }
        $hs = filter_var($_GET['s'] ?? '', FILTER_VALIDATE_FLOAT); $hw = filter_var($_GET['w'] ?? '', FILTER_VALIDATE_FLOAT); $hn = filter_var($_GET['n'] ?? '', FILTER_VALIDATE_FLOAT); $he = filter_var($_GET['e'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($hs === false || $hw === false || $hn === false || $he === false || $hn <= $hs || $he <= $hw) { $response['error'] = 'Nederīgas robežas.'; echo json_encode($response); exit; }
        if (($hn - $hs) > 0.09 || ($he - $hw) > 0.18) { $response['error'] = 'Pietuviniet karti tuvāk — siltumkartei apgabals par lielu.'; echo json_encode($response); exit; }
        $scenario = (isset($_GET['scenario']) && in_array($_GET['scenario'], ['pessimistic','realistic','optimistic'], true)) ? $_GET['scenario'] : 'realistic';
        $considerCompetitors = isset($_GET['competitors']) && $_GET['competitors'] === 'true';
        $useResidents = !(isset($_GET['lres']) && $_GET['lres'] === 'false'); $useOffices = !(isset($_GET['loff']) && $_GET['loff'] === 'false'); $useTourism = !(isset($_GET['ltour']) && $_GET['ltour'] === 'false'); $useInstitutions = !(isset($_GET['linst']) && $_GET['linst'] === 'false');
        $bizVSrc = $dataScenarios[$scenario]['visitorsPer1000']; $bizPSrc = $dataScenarios[$scenario]['averagePrice']; $bizISrc = $dataScenarios[$scenario]['initialInvestment']; /* Siltumkartei lat/lng parametru NAV (tikai robežas s/w/n/e) — $latClick te būtu nedefinēts un reģions vienmēr būtu lauku 0.74, arī Rīgā. Ņem skata centru; siltumkartes apgabals ir ierobežots (~10 km), tāpēc viena zona uz skatu pietiek. */ ie_apply_region(($hs + $hn) / 2.0, ($hw + $he) / 2.0);
        $GRID = 20; $latStep = ($hn - $hs) / $GRID; $lngStep = ($he - $hw) / $GRID;
        $R = 500.0; $mLat = 111320.0; $mLng = 111320.0 * cos(deg2rad(($hs + $hn) / 2)); $bLat = $R / $mLat; $bLng = $R / $mLng;
        $hbbox = sprintf('POLYGON((%F %F,%F %F,%F %F,%F %F,%F %F))', $hw-$bLng,$hs-$bLat, $he+$bLng,$hs-$bLat, $he+$bLng,$hn+$bLat, $hw-$bLng,$hn+$bLat, $hw-$bLng,$hs-$bLat);
        try { $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4"; $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $bkRes = []; $bkOff = []; $bkInst = []; $bkTour = []; $bkComp = [];
        $hmShards = ie_shards_for_bbox($hw, $hs, $he, $hn); $hmParts = []; $hmParams = []; foreach ($hmShards as $hmT) { $hmParts[] = "SELECT residents AS cilveki, UPPER(lvl) lv, ST_Y(location) la, ST_X(location) lo FROM `$hmT` WHERE MBRContains(ST_GeomFromText(?,4326), location)"; $hmParams[] = $hbbox; } $q = $pdo->prepare(implode(" UNION ALL ", $hmParts)); $q->execute($hmParams);
        while ($r0 = $q->fetch()) { $c0 = (int)$r0['cilveki']; $li = $pplToIndex[$r0['lv']] ?? null; if ($c0 <= 0 || ($li === null && $hmPopMode === null)) continue; $bkRes[floor(($r0['la']-$hs)/$bLat).'_'.floor(($r0['lo']-$hw)/$bLng)][] = [(float)$r0['la'], (float)$r0['lo'], $c0, $li ?? 0]; }
        if ($useOffices) { $q = $pdo->prepare("SELECT workers, lvl, ST_Y(location) la, ST_X(location) lo FROM `$tblOff` WHERE MBRContains(ST_GeomFromText(?,4326), location)"); $q->execute([$hbbox]); while ($r0 = $q->fetch()) { $oli0 = $pplToIndex[strtoupper((string)($r0['lvl'] ?? ''))] ?? -1; $bkOff[floor(($r0['la']-$hs)/$bLat).'_'.floor(($r0['lo']-$hw)/$bLng)][] = [(float)$r0['la'], (float)$r0['lo'], (int)$r0['workers'], $oli0]; } }
        if ($useInstitutions) { $q = $pdo->prepare("SELECT people, cat, ST_Y(location) la, ST_X(location) lo FROM `$tblInst` WHERE MBRContains(ST_GeomFromText(?,4326), location)"); $q->execute([$hbbox]); while ($r0 = $q->fetch()) { $pE = (int)round(((int)$r0['people']) * ($instOutflow[$r0['cat']] ?? 1.0)); if ($pE < 1) continue; $bkInst[floor(($r0['la']-$hs)/$bLat).'_'.floor(($r0['lo']-$hw)/$bLng)][] = [(float)$r0['la'], (float)$r0['lo'], $pE, $r0['cat']]; } }
        if ($useTourism) { $q = $pdo->prepare("SELECT score, ST_Y(location) la, ST_X(location) lo FROM `$tblTour` WHERE MBRContains(ST_GeomFromText(?,4326), location)"); $q->execute([$hbbox]); while ($r0 = $q->fetch()) { $bkTour[floor(($r0['la']-$hs)/$bLat).'_'.floor(($r0['lo']-$hw)/$bLng)][] = [(float)$r0['la'], (float)$r0['lo'], (float)$r0['score']]; } }
        if ($hmPopMode === null && isset($typeToPtype[$hmType])) { try { $q = $pdo->prepare("SELECT ST_Y(location) la, ST_X(location) lo FROM `$tblPoi` WHERE MBRContains(ST_GeomFromText(?,4326), location) AND `ptype`=?"); $q->execute([$hbbox, $typeToPtype[$hmType]]);
        while ($r0 = $q->fetch()) { $bkComp[floor(($r0['la']-$hs)/$bLat).'_'.floor(($r0['lo']-$hw)/$bLng)][] = [(float)$r0['la'], (float)$r0['lo']]; } } catch (Exception $eC) { error_log("PHP WARNING (heatmap): konkurentu tips nav pieejams: " . $typeToPtype[$hmType]); } }
        elseif ($hmPopMode === null && $hmType === 'Viesnīca') { /* naktsmītnes no tūrisma slāņa kā konkurenti */ $q = $pdo->prepare("SELECT ST_Y(location) la, ST_X(location) lo FROM `$tblTour` WHERE MBRContains(ST_GeomFromText(?,4326), location) AND ttype IN ($stayTypesSql)"); $q->execute([$hbbox]); while ($r0 = $q->fetch()) { $bkComp[floor(($r0['la']-$hs)/$bLat).'_'.floor(($r0['lo']-$hw)/$bLng)][] = [(float)$r0['la'], (float)$r0['lo']]; } }
        /* pārējiem jaunajiem tipiem OSM konkurentu tabulu vēl nav — comp=0, piesātinājums caur pool/bench joprojām strādā */
        $cells = []; $tiers = $sizeTiers[$hmType] ?? []; $benchH = $footfallBench[$hmType] ?? 45; $doH = $bizDaysOpen[$hmType] ?? 30; $R2 = $R * $R; $bdpH = $bizDaypart[$hmType] ?? [0.5,0.5]; $fRes = daypartF($daypartSrc['res'], $bdpH); $fOff = daypartF($daypartSrc['office'], $bdpH); $fTour = daypartF($daypartSrc['tourist'], $bdpH);
        for ($ci = 0; $ci < $GRID; $ci++) { for ($cj = 0; $cj < $GRID; $cj++) {
            $cla = $hs + ($ci + 0.5) * $latStep; $clo = $hw + ($cj + 0.5) * $lngStep;
            $bi = (int)floor(($cla - $hs) / $bLat); $bj = (int)floor(($clo - $hw) / $bLng);
            $byl = [0,0,0,0,0]; $offW = 0; $offByL = []; $tourS = 0.0; $tourC = 0; $instC = []; $comp = 0;
            for ($di = -1; $di <= 1; $di++) { for ($dj = -1; $dj <= 1; $dj++) { $k = ($bi + $di) . '_' . ($bj + $dj);
                if (isset($bkRes[$k])) foreach ($bkRes[$k] as $p) { $dx = ($p[0] - $cla) * $mLat; $dy = ($p[1] - $clo) * $mLng; if ($dx*$dx + $dy*$dy <= $R2) { $byl[$p[3]] += $p[2]; } }
                if (isset($bkOff[$k])) foreach ($bkOff[$k] as $p) { $dx = ($p[0] - $cla) * $mLat; $dy = ($p[1] - $clo) * $mLng; if ($dx*$dx + $dy*$dy <= $R2) { $offW += $p[2]; $offByL[$p[3]] = ($offByL[$p[3]] ?? 0) + $p[2]; } }
                if (isset($bkInst[$k])) foreach ($bkInst[$k] as $p) { $dx = ($p[0] - $cla) * $mLat; $dy = ($p[1] - $clo) * $mLng; if ($dx*$dx + $dy*$dy <= $R2) { $instC[$p[3]] = ($instC[$p[3]] ?? 0) + $p[2]; } }
                if (isset($bkTour[$k])) foreach ($bkTour[$k] as $p) { $dx = ($p[0] - $cla) * $mLat; $dy = ($p[1] - $clo) * $mLng; if ($dx*$dx + $dy*$dy <= $R2) { $tourS += $p[2]; $tourC++; } }
                if (isset($bkComp[$k])) foreach ($bkComp[$k] as $p) { $dx = ($p[0] - $cla) * $mLat; $dy = ($p[1] - $clo) * $mLng; if ($dx*$dx + $dy*$dy <= $R2) { $comp++; } }
            } }
            $totRes = array_sum($byl);
            if ($hmPopMode !== null) { /* populācijas režīms: summē cilvēkus pa diennakts daļām (sinhroni ar $daypartSrc/$daypartInst) */ $resT = $useResidents ? $totRes : 0; $tFFp = $tourS * min(2.5, 1.0 + 0.12 * sqrt(max(0, $tourC - 1))); $dayP = $resT * $daypartSrc['res'][0] + $offW * $daypartSrc['office'][0] + $tFFp * $daypartSrc['tourist'][0]; $nightP = $resT * $daypartSrc['res'][1] + $offW * $daypartSrc['office'][1] + $tFFp * $daypartSrc['tourist'][1]; foreach ($instC as $ic => $ip) { $dpi = $daypartInst[$ic] ?? [0.5,0.5]; $dayP += $ip * $dpi[0]; $nightP += $ip * $dpi[1]; } $pv = ($hmPopMode === 'day') ? $dayP : $nightP; if ($pv >= 1) { $cells[] = ['lat' => round($cla, 6), 'lng' => round($clo, 6), 'p' => (int)round($pv)]; } continue; }
            if ($totRes < 20 && $offW == 0 && $comp == 0 && $tourC == 0) { continue; }
            $aIdx = $totRes > 0 ? (int)round(($byl[1] + 2*$byl[2] + 3*$byl[3] + 4*$byl[4]) / $totRes) : 2; $aPPL = ['E','D','C','B','A'][$aIdx];
            $tFF = $tourS * min(2.5, 1.0 + 0.12 * sqrt(max(0, $tourC - 1)));
            $theo = ($bizResPerVenue[$hmType] > 0) ? ($totRes / $bizResPerVenue[$hmType]) : 0;
            $bestP = null;
            foreach ($tiers as $sz) { for ($pos = 0; $pos < 5; $pos++) {
                $raw = 0.0; if ($useResidents) { for ($r2i = 0; $r2i < 5; $r2i++) { $raw += $byl[$r2i] * (($bizVSrc[$hmType][$r2i] ?? 0) / 1000.0) * getVisitProbability($r2i, $pos); } }
                $offD = 0.0; foreach ($offByL as $oli => $ow) { $offD += $ow * ($officeVisitRate[$hmType] ?? 0) * getVisitProbability($oli < 0 ? $aIdx : $oli, $pos); }
                $dem = $raw * $fRes + $offD * $fOff + $tFF * ($tourismRate[$hmType] ?? 0) * $fTour * getVisitProbability(TOUR_PPL_IDX, $pos);
                $iD = 0.0; foreach ($instC as $ic => $ip) { $iD += $ip * ($instVisitRate[$ic][$hmType] ?? 0) * daypartF($daypartInst[$ic] ?? [0.5,0.5], $bdpH); } $dem += $iD * getVisitProbability(INST_PPL_IDX, $pos);
                /* Režģa kalibrācija (2026-08-04): šūnas tuvinājumi (vienkāršotais
                   tūrisma reizinātājs, konkurentu skaitīšana pa ptype, īre pa šūnas
                   aIdx) pret precīzo action=search aprēķinu tajā pašā punktā deva
                   sistemātiski +16..+46% (vid. ~+22%) peļņu. Pieprasījuma slāpējums
                   0.90 (peļņa pret pieprasījumu ir superlineāra caur fiksētajām
                   izmaksām) virsmu noliek uz precīzā aprēķina līmeni; forma paliek. */
                $dem *= 0.90;
                $ckM = $bizPSrc[$hmType][2] ?? 0; $ckP = $bizPSrc[$hmType][$pos] ?? 0; $benchP = ($ckM > 0 && $ckP > 0) ? $benchH * pow($ckM / $ckP, BENCH_PRICE_ELASTICITY) : $benchH; $pool = max($dem, $comp * $benchH); $supp = max($comp, $theo, $pool / (SUPPORT_SATURATION * $benchP)); $v = min($pool / ($supp + 1.0), $sz['cap']);
                if (!$considerCompetitors && $comp > 0) { $v = min($v * (1.0 + $comp / ($comp + 5.0)), $sz['cap']); }
                $cands = [$v]; $sf = bizStaffNeeded($hmType, $v); $pw = $prodPerWorker[$hmType] ?? 20; $mS2 = $minStaff[$hmType] ?? 2;
                for ($s2i = $mS2; $s2i < $sf; $s2i++) { $va = $s2i * $pw / 1.3; if ($va > 0 && $va < $v) { $cands[] = $va; } }
                foreach ($cands as $v2) { $pnet = ($bizPSrc[$hmType][$pos] ?? 0) / (1 + VAT_RATE); $net = $v2 * $pnet * $doH; $cg = $cogsBands[$hmType][$pos] ?? 0.30;
                    $costs = $net * $cg + $sz['m2'] * ($rentPerM2Month[$aPPL] ?? 13.0) * ($bizRentMul[$hmType] ?? 1.0) + $sz['m2'] * ($bizUtilM2[$hmType] ?? 7) + UTIL_VAR_PER_VISITOR * $v2 * $doH + ($bizOpFixMonth[$hmType] ?? 450) * $sz['opMul'] + $net * OTHEROP_VAR_PCT + loanMonthlyPayment(($bizISrc[$hmType][$pos] ?? 0) * $sz['invMul'] * $loanShare);
                    $pre = $net - $costs; $plm2 = $net > 0 ? $pre / $net : -1; $sal2 = bizSalaryGross($hmType, $aIdx, $plm2); $st2 = bizStaffNeeded($hmType, $v2); $pr = $pre - $st2 * $sal2 * 1.2359;
                    if ($bestP === null || $pr > $bestP) { $bestP = $pr; } }
            } }
            if ($bestP !== null) { $cells[] = ['lat' => round($cla, 6), 'lng' => round($clo, 6), 'p' => (int)round($bestP)]; }
        } }
        $response['cells'] = $cells; $response['grid'] = ['latStep' => $latStep, 'lngStep' => $lngStep]; $response['points_count'] = count($cells); $response['hm_mode'] = $hmPopMode ?: 'profit';
        } catch (PDOException $e) { $response['error'] = 'DB kļūda siltumkartē.'; error_log('PHP heatmap PDO: ' . $e->getMessage()); } catch (Exception $e) { $response['error'] = 'Kļūda siltumkartē.'; error_log('PHP heatmap: ' . $e->getMessage()); }
    } elseif ($_GET['action'] === 'get_points' && isset($_GET['type'])) { /* ... NEMAINĪTS ... */ $type = $_GET['type']; $response['type'] = $type; /* Kartes slāņa atslēga (JS) → ptype. 'hotel' ir izņēmums: naktsmītnes nāk no tūrisma slāņa, ne no POI tabulas. */ $tableMap = [ 'cafe' => 'cafe', 'food' => 'restaurant', 'krogs' => 'bar', 'hairdresser' => 'hairdresser', 'bakery' => 'bakery', 'pharmacy' => 'pharmacy', 'beauty' => 'beauty', 'minimarket' => 'minimarket', 'dentist' => 'dentist', 'fastfood' => 'fastfood', 'fitness' => 'fitness', 'hotel' => null ]; $colWhere = ''; $poiParams = []; if (!array_key_exists($type, $tableMap)) { $response['error'] = "Nederīgs POI tips."; echo json_encode($response); exit; } if ($type === 'hotel') { $tableName = $tblTour; $colWhere = " AND `ttype` IN ($stayTypesSql)"; } else { $tableName = $tblPoi; $colWhere = " AND `ptype`=?"; $poiParams[] = $tableMap[$type]; } $colName = 'name'; $colLocation = 'location'; try { $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4"; $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, (defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? Pdo\Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND)=>"SET NAMES utf8mb4"]); $colName = ($type === 'hotel') ? 'tname' : $colName; $sql = "SELECT`{$colName}` AS `name`,ST_Y(`{$colLocation}`)AS lat,ST_X(`{$colLocation}`)AS lng FROM`{$tableName}`WHERE ST_Y(`{$colLocation}`)IS NOT NULL AND ST_X(`{$colLocation}`)IS NOT NULL" . ($colWhere ?? ''); $stmt = $pdo->prepare($sql); $stmt->execute($poiParams); $points = []; while ($row = $stmt->fetch()) { $lat_float = (isset($row['lat'])&&is_numeric($row['lat']))?floatval($row['lat']):null; $lng_float = (isset($row['lng'])&&is_numeric($row['lng']))?floatval($row['lng']):null; if ($lat_float !== null && $lng_float !== null) { $points[] = ['name'=>$row['name']??'N/A', 'lat'=>$lat_float, 'lng'=>$lng_float]; } else { error_log("PHP WARNING (POI): Skipped POI in table $tableName."); } } $response['points'] = $points; $response['points_count'] = count($points); } catch (PDOException $e) { $response['error'] = "DB Kļūda (POI): " . $e->getMessage(); error_log("PHP PDO ERROR (POI): " . $e->getMessage()); } catch (Exception $e) { $response['error'] = "Servera Kļūda (POI): " . $e->getMessage(); error_log("PHP ERROR (POI): " . $e->getMessage()); } } else { $response['error'] = "Nezināma darbība."; error_log("PHP ERROR: Unknown action specified."); }
    echo json_encode($response);
    exit;
}

// --- FRONTEND: HTML Lapa ---
?>
<?php 
$pageTitle = "Biznesa iespēju karte — kur Latvijā atvērt savu biznesu";
$pageDesc = "Ģeotelpiska biznesa vietu analīze: klikšķini kartē un uzzini apmeklētāju plūsmu, konkurentus un 12 biznesa tipu ienesīguma aplēsi jebkurā Latvijas vietā.";
ob_start();
?>
<meta name="keywords" content="savs bizness, biznesa idejas, kafejnīcas atvēršana, restorāna atvēršana, frizētavas atvēršana, kroga atvēršana, iedzīvotāju pirktspēja, cilvēku pirktspēja, iedzīvotāju blīvums">
<link rel="canonical" href="https://saraksts.lv/iespeja" />

	
	<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/assets/img/icons.php'; ?>
    <meta name="msapplication-TileColor" content="#da532c">
    
    <meta name="theme-color" content="#ffffff">



<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/assets.php';
      echo reg_css_links(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* --- CSS Stili, kas attiecas uz pašu kartes lapu --- */
html, body { height: 100%; margin: 0; padding: 0; font-family: sans-serif;}
#map-container { position: relative; width: 100%; height: 100%; }
#map { width: 100%; height: 100%; background-color: #f0f0f0; }

/* --- Paneļu Stili --- */
#controls-container { position: absolute; top: 10px; left: 10px; width: 280px; max-height: calc(100% - 20px); background-color: rgba(255, 255, 255, 0.95); padding: 12px; border: 1px solid #bbb; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); z-index: 1000; overflow-y: auto; font-size: 0.85em; }
#analysis-container { position: absolute; top: 10px; right: 10px; width: 420px; max-height: calc(100% - 20px); background-color: rgba(255, 255, 255, 0.95); padding: 12px; border: 1px solid #bbb; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); z-index: 1000; overflow-y: auto; font-size: 0.85em; display: none; }

/* --- Vispārīgi Elementi Paneļos --- */
#controls-container h5, #analysis-container h5 { margin-top: 0; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 6px; font-size: 1.05em; color: #333; display: flex; align-items: center; justify-content: space-between; }
#info-sidebar p, #analysis-container p { margin-bottom: 0.5rem; margin-top: 0.2rem; line-height: 1.3; }
#info-sidebar b, #analysis-container b { color: #111; }
hr { margin: 8px 0; border: 0; border-top: 1px solid #ddd; }

/* --- Kreisais Panelis Specifiski --- */
#radius-label-container { display: flex; align-items: center; margin-bottom: 5px; }
#radius-label-container label { margin-bottom: 0; font-weight: bold; flex-grow: 1; }
#radius-toggle-btn { cursor: pointer; background: none; border: none; font-size: 1.1em; padding: 0 5px; color: #555; margin-left: 5px; }
#radius-toggle-btn:hover { color: #000; }
#radius-controls-collapsible { display: none; margin-top: 5px; padding-top: 5px; border-top: 1px dashed #eee; }
#radius-controls-collapsible .slider-container { display: flex; align-items: center; }
#radius-controls-collapsible input[type="range"] { flex-grow: 1; margin-right: 8px; cursor: pointer; height: 5px;}
#radius-controls-collapsible input[type="number"] { width: 60px; text-align: right; margin-right: 3px; font-size: 0.95em; padding: 2px 4px;}

/* Konteiners diagrammai un leģendai */
#chart-legend-wrapper { display: flex; align-items: flex-start; gap: 10px; margin-top: 8px; margin-bottom: 15px; }
#chart-container { flex: 0 0 120px; height: 120px; position: relative; }
#levelDonutChart { display: block; max-width: 100%; max-height: 100%; width: 100% !important; height: 100% !important;}

/* Pielāgotā leģenda (ar vertikālu izkārtojumu) */
#custom-legend { flex: 1; list-style: none; padding: 0; margin: 0; font-size: 0.8em; line-height: 1.4; }
#custom-legend li { display: flex; align-items: flex-start; margin-bottom: 4px; }
.legend-color-box { width: 10px; height: 10px; display: inline-block; margin-right: 6px; border: 1px solid #ccc; flex-shrink: 0; margin-top: 2px; }
.legend-details { display: flex; flex-direction: column; } /* Vertikāli */
.legend-text { font-weight: bold; }
.legend-stats { color: #555; }
.legend-percent { font-weight: normal; margin-right: 3px; }
.legend-count { font-size: 0.9em; }

#layer-controls h5, #demand-controls h5, #heatmap-controls h5 { font-size: 1em; margin-bottom: 8px;}
#layer-controls label, #demand-controls label, #heatmap-controls label { display: block; margin-bottom: 4px; cursor: pointer; font-size: 0.95em;}
#layer-controls input[type="checkbox"], #demand-controls input[type="checkbox"], #heatmap-controls input[type="checkbox"] { margin-right: 6px; vertical-align: middle; }
.poi-legend i { margin-right: 5px; width: 12px; text-align: center; }
.poi-loading { color: #888; margin-left: 5px; font-size: 0.9em; }

/* --- Labais Panelis Specifiski --- */
#analysis-container h6 { font-size: 1em; margin-bottom: 6px; margin-top: 10px; padding-bottom: 4px; border-bottom: 1px dashed #ddd; color: #444; }
#analysis-options { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 10px; }
#analysis-options > div { margin-bottom: 0; }
#competitor-options label { font-weight: normal; cursor: pointer; font-size: 0.95em; }
#competitor-options input { vertical-align: middle; margin-right: 4px; }
#scenario-options { display: flex; align-items: center; gap: 5px; }
#scenario-options b { font-weight: bold; margin-right: 2px; font-size: 0.95em; } /* Scenārijs: */
#scenario-options label { margin-left: 0; font-weight: normal; cursor: pointer; font-size: 0.95em; white-space: nowrap; }
#scenario-options input { vertical-align: middle; margin-right: 3px; }

/* PPL Eiro Simbolu Stili */
.ppl-euro-wrapper { display: inline-block; white-space: nowrap; line-height: 1; }
.ppl-euro-symbol { font-weight: bold; font-size: 1em; margin: 0 0px; display: inline-block; padding: 0 1px; }
.ppl-euro-symbol.gold { color: #FFBF00; /* Zeltaināks */ }
.ppl-euro-symbol.grey { color: #B0B0B0; /* Nedaudz tumšāks pelēks */ }

/* Tabulu Stili (Pielāgoti PPL Eiro simboliem) */
.competitor-summary-table, .profit-summary-table, .profit-details-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 0.85em; }
.competitor-summary-table th, .competitor-summary-table td,
.profit-summary-table th, .profit-summary-table td,
.profit-details-table th, .profit-details-table td { border: 1px solid #e8e8e8; padding: 3px 2px; text-align: center; vertical-align: middle; }
.competitor-summary-table th, .profit-summary-table th, .profit-details-table th { background-color: #f9f9f9; font-weight: bold; white-space: nowrap; padding: 4px 2px; }
.profit-summary-table td.profit-value { font-weight: bold; }
.profit-summary-table td.profit-positive, .profit-details-table .profit-positive { color: green; font-weight: bold;}
.profit-summary-table td.profit-negative, .profit-details-table .profit-negative { color: red; font-weight: bold;}
.profit-summary-table td.profit-zero, .profit-details-table .profit-zero { color: #555; }

/* Specifiski Konkurentu Tabulai */
.competitor-summary-table th:nth-child(1), .competitor-summary-table td:nth-child(1) { width: 65px; text-align: left; padding-left: 4px;} /* Tips */
.competitor-summary-table th:nth-child(2), .competitor-summary-table td:nth-child(2) { width: 40px; text-align: right; padding-right: 4px; } /* Skaits */
.competitor-summary-table th:nth-child(3), .competitor-summary-table td:nth-child(3) { text-align: left; padding-left: 4px; vertical-align: top;} /* Konkurenti */

/* Specifiski Peļņas Kopsavilkuma Tabulai */
.profit-summary-table th:first-child { width: 90px; text-align: left; padding-left: 4px;} /* Iestādes līmenis */
.profit-summary-table th:not(:first-child) { width: 55px; } /* PPL € simboli */
.profit-summary-table td:first-child { text-align: left; padding-left: 4px; font-weight: bold; } /* Iestādes tips */

/* Specifiski Peļņas Detaļu Tabulai */
.profit-details-table th:first-child { width: 55px; } /* Virsraksts "PPL" */
.profit-details-table td:first-child { width: 55px; padding-top: 5px; padding-bottom: 5px;} /* PPL € simboli */
.profit-details-table th:nth-child(n+4), .profit-details-table td:nth-child(n+4) { font-size: 0.9em; }
.profit-details-table th:last-child, .profit-details-table td:last-child { width: 55px; } /* Invest. kolonna */

#profit-details-container { display: none; margin-top: 8px; }
#toggle-details-btn { display: block; margin: 8px auto; padding: 4px 8px; background-color: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; text-align: center; font-size: 0.85em; width: auto; }
#toggle-details-btn:hover { background-color: #e0e0e0; }

/* Citi Stili */
.loading-text { text-align: center; color: #555; font-style: italic; padding: 10px 0; }
.error-text { color: #D8000C; background-color: #FFD2D2; padding: 8px; border-radius: 4px; margin-top: 5px; font-weight: bold; font-size: 0.9em; }
.awesome-marker-icon { text-align: center; }
.small-note { font-size: 0.85em; color: #666; margin-top: 5px; display: block; text-align: center;}
path.leaflet-interactive:focus { outline: none; } /* pārlūka fokusa rāmis ap uzklikšķināto siltumkartes šūnu — vizuāls troksnis */
.map-shape-legend { background: rgba(255,255,255,0.93); padding: 6px 10px; border: 1px solid #bbb; border-radius: 5px; font-size: 0.78em; line-height: 1.65; box-shadow: 0 1px 4px rgba(0,0,0,0.2); color: #333; }
.map-shape-legend .lg-shape { display: inline-block; vertical-align: middle; margin-right: 6px; }
</style>
<?php 
$extraHeadContent = ob_get_clean(); 
?>
<!DOCTYPE html>
<html lang="lv">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/head/head.php'; ?>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/header.php'; ?>

<div id="map-container">
<div id="map"></div>
<div id="controls-container">
<div id="info-sidebar">
    <h5>
        <span>Kopsavilkums</span>
    </h5>
    <div id="radius-label-container">
        <label for="radius-slider">Analīzes rādiuss:</label>
        <button id="radius-toggle-btn" title="Rādīt/Slēpt rādiusa izvēli">▼</button>
    </div>
    <div id="radius-controls-collapsible">
        <div class="slider-container">
            <input type="range" id="radius-slider" min="100" max="5000" step="50" value="500">
            <input type="number" id="radius-input" min="100" max="5000" step="50" value="500">
            <span>m</span>
        </div>
    </div>
    <p id="click-prompt" style="font-style: italic; color: #666; text-align: center; margin-top: 15px;">Noklikšķiniet uz kartes, lai sāktu analīzi...</p>
    <div id="sidebar-content"></div>
</div>
<hr>
<div id="layer-controls">
    <h5 id="hd-comp" style="cursor:pointer;">Rādīt konkurentus: <span id="hd-comp-arr" style="float:right;">▸</span></h5>
    <div id="layer-controls-body" style="display:none;column-count:2;column-gap:4px;">
    <label><input type="checkbox" id="cb-cafe" value="cafe"> ☕ Kafejnīcas</label>
    <label><input type="checkbox" id="cb-food" value="food"> 🍴 Restorāni</label>
    <label><input type="checkbox" id="cb-krogs" value="krogs"> 🍺 Krogi</label>
    <label><input type="checkbox" id="cb-hairdresser" value="hairdresser"> ✂️ Frizētavas</label>
    <label><input type="checkbox" value="bakery"> 🥐 Beķerejas</label>
    <label><input type="checkbox" value="pharmacy"> 💊 Aptiekas</label>
    <label><input type="checkbox" value="beauty"> 💅 Skaist. saloni</label>
    <label><input type="checkbox" value="minimarket"> 🛒 Pārt. veikali</label>
    <label><input type="checkbox" value="dentist"> 🦷 Zobārsti</label>
    <label><input type="checkbox" value="fastfood"> 🍕 Ātrā ēdin.</label>
    <label><input type="checkbox" value="fitness"> 🏋️ Fitnesa klubi</label>
    <label><input type="checkbox" value="hotel"> 🏨 Naktsmītnes</label>
    </div>
</div>
<hr>
<div id="demand-controls">
<h5 id="hd-biz" style="cursor:pointer;">Biznesa veidi: <span id="hd-biz-arr" style="float:right;">▸</span></h5>
<div id="demand-controls-body" style="display:none;">
<label><input type="checkbox" class="cb-biz" data-biztype="Restorāns" checked> 🍴 Restorāns</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Krogs" checked> 🍺 Krogs</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Kafejnīca" checked> ☕ Kafejnīca</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Frizētava" checked> ✂️ Frizētava</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Beķereja" checked> 🥐 Beķereja/konditoreja</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Aptieka" checked> 💊 Aptieka</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Skaistumkopšana" checked> 💅 Skaistumkopšana</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Minimārkets" checked> 🛒 Pārtikas minimārkets</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Zobārsts" checked> 🦷 Zobārsts</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Ātrā ēdināšana" checked> 🍕 Ātrā ēdināšana/picērija</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Fitnesa klubs" checked> 🏋️ Fitnesa klubs</label>
<label><input type="checkbox" class="cb-biz" data-biztype="Viesnīca" checked> 🏨 Viesnīca</label>
</div>
</div>
<hr>
<div id="heatmap-controls">
<h5>Siltumkarte:</h5>
<label><input type="checkbox" id="cb-heatmap"> 🔥 Siltumkarte</label>
<select id="hm-type" style="width:100%;margin-bottom:5px;padding:3px;"><option value="Kafejnīca">☕ Kafejnīca</option><option value="Restorāns">🍴 Restorāns</option><option value="Krogs">🍺 Krogs</option><option value="Frizētava">✂️ Frizētava</option><option value="Beķereja">🥐 Beķereja</option><option value="Aptieka">💊 Aptieka</option><option value="Skaistumkopšana">💅 Skaistumkopšana</option><option value="Minimārkets">🛒 Minimārkets</option><option value="Zobārsts">🦷 Zobārsts</option><option value="Ātrā ēdināšana">🍕 Ātrā ēdināšana</option><option value="Fitnesa klubs">🏋️ Fitnesa klubs</option><option value="Viesnīca">🏨 Viesnīca</option><option value="PopDiena">☀️ Dienas populācija</option><option value="PopNakts">🌙 Nakts populācija</option></select>
<div id="hm-info" class="small-note" style="display:none;margin-top:5px;text-align:left;"></div>
</div>
</div>

<div id="analysis-container"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
<script>
// --- Globālie mainīgie un Inicializācija ---
var mapCenter = [56.9496, 24.1052]; var map = L.map('map').setView(mapCenter, 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
var searchCircle; var centerMarker = null; var AJAX_URL = window.location.pathname; var buildingMarkersLayer = L.layerGroup().addTo(map);
var poiLayers = {}; ['cafe','food','krogs','hairdresser','bakery','pharmacy','beauty','minimarket','dentist','fastfood','fitness','hotel'].forEach(function(k){ poiLayers[k] = L.layerGroup(); });
// Krāsas Donut diagrammai
var donutLevelColors = { 'A': 'darkgreen', 'B': 'lime', 'C': 'dodgerblue', 'D': 'orange', 'E': 'red', 'NULL': '#AAAAAA' };
// Krāsas PPL € simboliem
const goldColor = '#FFBF00';
const greyColor = '#B0B0B0';
var defaultBuildingColor = '#555555';
var levelDescriptions = { 'A': 'Maksimāla', 'B': 'Augsta', 'C': 'Vidēja', 'D': 'Zema', 'E': 'Minimāla', 'NULL': 'Nezināma' };

function createAwesomeIcon(iconName, markerColor) { return L.divIcon({ html: `<i class="fas ${iconName}" style="color: ${markerColor}; font-size: 16px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);"></i>`, iconSize: [18, 18], className: 'awesome-marker-icon'}); }
var poiEmoji = {cafe:'☕',food:'🍴',krogs:'🍺',hairdresser:'✂️',bakery:'🥐',pharmacy:'💊',beauty:'💅',minimarket:'🛒',dentist:'🦷',fastfood:'🍕',fitness:'🏋️',hotel:'🏨'}; var poiEmojiIcon = function(k){ return L.divIcon({html:'<div style="font-size:15px;line-height:1;text-shadow:0 0 3px #fff,0 0 3px #fff;">'+(poiEmoji[k]||'📍')+'</div>', className:'', iconSize:[18,18], iconAnchor:[9,9]}); };
var poiDataCache = {};
var currentDonutChart = null; var lastClickCoords = null; var currentRadius = 500;

// --- Funkcijas ---

// PPL Eiro Simbolu ģenerēšana
function generatePplEuroSymbols(level) {
    const levelMap = { 'E': 1, 'D': 2, 'C': 3, 'B': 4, 'A': 5 };
    const numGold = levelMap[level] || 0;
    const numGrey = 5 - numGold;
    let iconsHtml = '<span class="ppl-euro-wrapper" title="Līmenis ' + level + '">';
    for (let i = 0; i < numGold; i++) { iconsHtml += `<span class="ppl-euro-symbol gold" style="color:${goldColor};">€</span>`; }
    for (let i = 0; i < numGrey; i++) { iconsHtml += `<span class="ppl-euro-symbol grey" style="color:${greyColor};">€</span>`; }
    iconsHtml += '</span>';
    return iconsHtml;
}

// displayBuildingPoints
function displayBuildingPoints(points) { /* ... nemainīgs ... */ console.log("JS: displayBuildingPoints", points ? points.length : 0); buildingMarkersLayer.clearLayers(); if (!points || points.length === 0) return; let v = 0; points.forEach(function(p, i) { try { let lat = parseFloat(p.lat); let lng = parseFloat(p.lng); if (isNaN(lat) || isNaN(lng)) return; let iedz = parseInt(p.iedzivotaji, 10) || 0; let clr = donutLevelColors[p.level] || defaultBuildingColor; let r = 3 + Math.log(Math.max(1, iedz) + 1) * 1.5; r = Math.min(r, 15); let m = L.circle([lat, lng], { radius: r, color: clr, weight: 1, fillColor: clr, fillOpacity: 0.65 }); let pop = `<b>Ēka:</b> ${p.kadastrs || 'N/A'}<br><b>Iedzīvotāji:</b> ${iedz}<br><b>Pirktspēja:</b> ${(p.level && p.level !== 'NULL') ? (levelDescriptions[p.level] || p.level) + ' pirktspēja' : 'Nezināma'}<br><b>Attālums:</b> ${p.distance_from_center !== null ? Math.round(p.distance_from_center) + " m" : "N/A"}`; m.bindPopup(pop, {offset: L.point(0, -r)}); buildingMarkersLayer.addLayer(m); v++; } catch(e) { console.error(`JS(Bldg) Err ${i}:`, p, e); } }); console.log(`JS: Ēkas kartē: ${v} no ${points.length}`); }

// Ģeneratoru marķieri: biroji=kvadrāti, iestādes=piecstūri, tūrisms=trijstūri
function genIcon(shape, color, sizePx) {
  var st = 'width:' + sizePx + 'px;height:' + sizePx + 'px;background:' + color + ';opacity:0.9;';
  if (shape === 'pentagon') st += 'clip-path:polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%);';
  else if (shape === 'triangle') st += 'clip-path:polygon(50% 0%, 100% 100%, 0% 100%);';
  else st += 'border:1px solid rgba(0,0,0,0.35);';
  return L.divIcon({ html: '<div style="' + st + '"></div>', iconSize: [sizePx, sizePx], iconAnchor: [sizePx/2, sizePx/2], className: 'gen-marker' });
}
function displayGeneratorPoints(data) {
  var IT = {skola:'Skola',slimnica:'Slimnīca',stacija:'Stacija',izklaide:'Izklaide',sports:'Sports'};
  var TT = {hotel:'Viesnīca',hostel:'Hostelis',guest_house:'Viesu nams',motel:'Motelis',apartment:'Apartaments',chalet:'Brīvdienu māja',museum:'Muzejs',attraction:'Apskates objekts',viewpoint:'Skatu punkts',zoo:'Zoo',gallery:'Galerija',theme_park:'Atrakciju parks',aquarium:'Akvārijs'};
  var distTxt = function(d){ return (d !== undefined && d !== null) ? d + ' m' : 'N/A'; };
  (data.offices_found || []).forEach(function(o) {
    if (o.lat === undefined) return;
    var sz = Math.round(Math.max(4, Math.min(14, 4 + Math.sqrt(Math.max(1, o.workers)) * 0.28)));
    var m = L.marker([o.lat, o.lng], { icon: genIcon('square', '#1f6fb2', sz) });
    var lvlTxt = (o.lvl && levelDescriptions[o.lvl]) ? levelDescriptions[o.lvl] + ' pirktspēja (pēc ēkas vērtības)' : 'Nezināma';
    m.bindPopup('<b>Birojs (ēka):</b> ' + o.eka + '<br><b>Darbinieki (dienā):</b> ' + o.workers + '<br><b>Pirktspēja:</b> ' + lvlTxt + '<br><b>Attālums:</b> ' + distTxt(o.dist));
    buildingMarkersLayer.addLayer(m);
  });
  (data.institutions_found || []).forEach(function(o) {
    if (o.lat === undefined) return;
    var sz = Math.round(Math.max(5, Math.min(15, 5 + Math.sqrt(Math.max(1, o.people)) * 0.35)));
    var m = L.marker([o.lat, o.lng], { icon: genIcon('pentagon', '#8e44ad', sz) });
    m.bindPopup('<b>Iestāde (ēka):</b> ' + o.eka + '<br><b>Kategorija:</b> ' + (IT[o.cat] || o.cat) + '<br><b>Ārējā plūsma:</b> ~' + o.people + ' cilv./dienā<br><b>Pirktspēja:</b> Zema pirktspēja (pieņēmums)<br><b>Attālums:</b> ' + distTxt(o.dist));
    buildingMarkersLayer.addLayer(m);
  });
  (data.tourism_found || []).forEach(function(o) {
    if (o.lat === undefined) return;
    var sz = Math.round(Math.max(5, Math.min(15, 5 + Math.sqrt(Math.max(1, o.score)) * 0.7)));
    var m = L.marker([o.lat, o.lng], { icon: genIcon('triangle', '#e67e22', sz) });
    m.bindPopup('<b>Tūrisma objekts:</b> ' + (o.name || '(nenosaukts)') + '<br><b>Tips:</b> ' + (TT[o.type] || o.type) + '<br><b>Svars:</b> ' + o.score + '<br><b>Pirktspēja:</b> Augsta pirktspēja (pieņēmums)<br><b>Attālums:</b> ' + distTxt(o.dist));
    buildingMarkersLayer.addLayer(m);
  });
}
// displayPoiPoints
function displayPoiPoints(points, type) { var lyr = poiLayers[type]; if (!lyr) return; lyr.clearLayers(); if (!points || points.length === 0) { if (map.hasLayer(lyr)) map.removeLayer(lyr); return; } var ico = poiEmojiIcon(type); points.forEach(function(p){ var lat = parseFloat(p.lat), lng = parseFloat(p.lng); if (isNaN(lat) || isNaN(lng)) return; lyr.addLayer(L.marker([lat, lng], { icon: ico }).bindPopup('<b>' + $('<div>').text(p.name || 'Bez nosaukuma').html() + '</b>')); }); var cb = $('#layer-controls-body input[value="' + type + '"]'); if (cb.is(':checked')) { if (!map.hasLayer(lyr)) map.addLayer(lyr); } else { if (map.hasLayer(lyr)) map.removeLayer(lyr); } }
// updateSidebar
function updateSidebar(data) { /* ... nemainīgs ... */ var sidebarContent = $("#sidebar-content"); sidebarContent.empty(); $('#click-prompt').hide(); if (currentDonutChart) { currentDonutChart.destroy(); currentDonutChart = null; } if (data.error || !data.statistics) { sidebarContent.append(`<p class="error-text">${data.error || 'Trūkst statistikas datu.'}</p>`); return; } var stats = data.statistics; var pointsCount = data.points_count ?? 0; var totalIedzivotaji = stats.total_iedzivotaji ?? 0; var offMark = ' <span style="color:#999;font-weight:normal;">(izslēgts)</span>'; var resOffM = true ? '' : offMark; var offOffM = true ? '' : offMark; var tourOffM = true ? '' : offMark; var instOffM = true ? '' : offMark; let summaryHtml = `<p style="margin-bottom: 0.3rem;">Atrastās ēkas: <b>${pointsCount}</b></p>`; summaryHtml += `<p style="margin-bottom: 0.3rem;">Kopā iedzīvotāji: <b>${totalIedzivotaji}</b>${resOffM}</p>`; summaryHtml += `<p style="margin-bottom: 0.3rem;">Biroju darbinieki (dienā): <b>${(stats.office_workers ?? 0).toLocaleString('lv-LV')}</b>${offOffM}</p>`; summaryHtml += `<p style="margin-bottom: 0.3rem;">Iestāžu darbinieki (dienā): <b>${(stats.inst_people ?? 0).toLocaleString('lv-LV')}</b>${instOffM}</p>`; summaryHtml += `<p style="margin-bottom: 0.3rem;">Tūrisma objekti: <b>${(stats.tourism_count ?? 0)}</b>${tourOffM}</p>`; if(stats.average_radius_ppl) { summaryHtml += `<p>Vidējā pirktspēja rajonā: <b style="color:${donutLevelColors[stats.average_radius_ppl] || 'black'}">${(levelDescriptions[stats.average_radius_ppl] || stats.average_radius_ppl)} pirktspēja</b></p>`; } sidebarContent.append(summaryHtml); /* Kopējā pieprasījuma pirktspēja: iedzīvotāji pa līmeņiem + biroji pa ēku lvl + iestāžu ārējā plūsma (Zema) + tūristi (Augsta) — tikai ieslēgtie avoti */ var combLvl = {'A':0,'B':0,'C':0,'D':0,'E':0,'NULL':0}; var srcRows = []; if (true) { ['A','B','C','D','E','NULL'].forEach(function(l){ combLvl[l] += (stats.level_people_counts && stats.level_people_counts[l]) ? stats.level_people_counts[l] : 0; }); srcRows.push('👥 Iedzīvotāji: <b>' + totalIedzivotaji.toLocaleString('lv-LV') + '</b>'); } if (true) { var obl = stats.office_by_lvl || {}; var offSum = 0, domL = null, domW = -1; Object.keys(obl).forEach(function(l){ var w = obl[l] || 0; offSum += w; combLvl[combLvl.hasOwnProperty(l) ? l : 'NULL'] += w; if (l !== 'NULL' && w > domW) { domW = w; domL = l; } }); srcRows.push('🏢 Biroju darbinieki: <b>' + offSum.toLocaleString('lv-LV') + '</b>' + (domL ? ' (pārsvarā ' + (levelDescriptions[domL] || domL).toLowerCase() + ' pirktspēja)' : '')); } if (true) { var ipo = stats.inst_people || 0; combLvl['D'] += ipo; if (ipo > 0) srcRows.push('🏛 Iestāžu plūsma: <b>' + ipo.toLocaleString('lv-LV') + '</b> (zema pirktspēja)'); } if (true) { var tf = Math.round(stats.tourism_footfall || 0); combLvl['B'] += tf; if (tf > 0) srcRows.push('🧳 Tūristu plūsma: <b>' + tf.toLocaleString('lv-LV') + '</b> (augsta pirktspēja)'); } /* diena/vakars — sinhroni ar PHP $daypartSrc/$daypartInst: iedzīvotāji 35/65, biroji 75/25, tūristi 55/45, iestādes no inst_people_day */ var dayN = 0, eveN = 0; if (true) { dayN += totalIedzivotaji * 0.35; eveN += totalIedzivotaji * 0.65; } if (true) { var ow0 = stats.office_workers || 0; dayN += ow0 * 0.75; eveN += ow0 * 0.25; } if (true) { var ip0 = stats.inst_people || 0, ipd = stats.inst_people_day || 0; dayN += ipd; eveN += (ip0 - ipd); } if (true) { var tf0 = Math.round(stats.tourism_footfall || 0); dayN += tf0 * 0.55; eveN += tf0 * 0.45; } var combTotal = 0; Object.keys(combLvl).forEach(function(l){ combTotal += combLvl[l]; }); if (combTotal > 0) { sidebarContent.append("<hr style='margin: 5px 0;'><p style='margin-bottom: 5px;'><b>Pirktspēja rādiusā (visi avoti):</b></p>"); sidebarContent.append('<div id="chart-legend-wrapper"><div id="chart-container"><canvas id="levelDonutChart"></canvas></div><ul id="custom-legend"></ul></div>'); const chartLabels = ['A','B','C','D','E','NULL'].filter(l => combLvl[l] > 0); const chartDataValues = chartLabels.map(l => Math.round(combLvl[l] / combTotal * 1000) / 10); const chartBackgroundColors = chartLabels.map(l => donutLevelColors[l] || defaultBuildingColor); const chartConfig = { type: 'doughnut', data: { labels: chartLabels.map(l => (levelDescriptions[l] || l) + ' pirktspēja'), datasets: [{ data: chartDataValues, backgroundColor: chartBackgroundColors, borderColor: '#ffffff', borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false }, tooltip: { enabled: false } } } }; var ctx = document.getElementById('levelDonutChart')?.getContext('2d'); if (ctx) { currentDonutChart = new Chart(ctx, chartConfig); } var legendHtml = ''; chartLabels.forEach(level => { let color = donutLevelColors[level] || defaultBuildingColor; let description = levelDescriptions[level] || level; let percentage = Math.round(combLvl[level] / combTotal * 1000) / 10; legendHtml += `<li><span class="legend-color-box" style="background-color: ${color};"></span><div class="legend-details"><span class="legend-text">${description} pirktspēja</span><div class="legend-stats"><span class="legend-percent">${percentage.toFixed(1)}%</span><span class="legend-count">(${Math.round(combLvl[level]).toLocaleString('lv-LV')} cilv.)</span></div></div></li>`; }); $('#custom-legend').html(legendHtml); if (srcRows.length > 0) { sidebarContent.append('<p class="small-note" style="margin-top:6px;line-height:1.6;">' + srcRows.join('<br>') + '</p>'); } var dnTot = dayN + eveN; if (dnTot > 0) { var dp = Math.round(dayN / dnTot * 100); sidebarContent.append('<p style="margin-top:6px;font-size:0.95em;"><b>Publika pa diennakti:</b><br>☀️ Dienā: <b>~' + Math.round(dayN).toLocaleString('lv-LV') + '</b> (' + dp + '%) · 🌙 Vakarā: <b>~' + Math.round(eveN).toLocaleString('lv-LV') + '</b> (' + (100 - dp) + '%)</p>'); } } else if (pointsCount > 0) { sidebarContent.append("<p class='small-note' style='margin-top: 10px;'>Nav datu par PPL sadalījumu.</p>"); } if (pointsCount === 0) { sidebarContent.append("<p class='small-note' style='margin-top: 10px;'>Nav atrasta neviena ēka.</p>"); } }

// updateAnalysisPanel
function updateAnalysisPanel(data) { /* ... nemainīgs ... */ var analysisContainer = $("#analysis-container"); analysisContainer.empty().hide(); if (data.error || !data.statistics) { return; } var stats = data.statistics; var totalIedzivotaji = stats.total_iedzivotaji ?? 0; var scenarioUsed = data.scenario_used || 'realistic'; var competitorImpactApplied = data.competitor_impact_applied; var radiusUsed = data.radius_used || currentRadius; analysisContainer.append(`<h5><span>Biznesa Analīze (Rādiuss: ${radiusUsed}m)</span><button id="analysis-toggle" style="font-size:0.82em;padding:2px 8px;border:1px solid #bbb;border-radius:4px;background:#f5f5f5;cursor:pointer;">${window._analysisExpanded ? 'Mazāk ▲' : 'Detalizētāk ▼'}</button></h5>`); analysisContainer.append(`<div id="analysis-body" style="display:${window._analysisExpanded ? 'block' : 'none'};"></div>`); $('#analysis-body').append(`<div id="analysis-options"><div id="competitor-options"><label><input type="checkbox" id="cb-competitors" ${competitorImpactApplied ? 'checked' : ''}> Iekļaut konkurentus</label> <label title="Ieslēgts: investīcijas 100% ar kredītu (10%, 5 g.). Izslēgts: no pašu kapitāla, bez kredīta maksājuma."><input type="checkbox" id="cb-loan" ${window._loanOn !== false ? 'checked' : ''}> Invest. ar kredītu</label></div><div id="scenario-options"><b>Scenārijs:</b> <label><input type="radio" name="scenario" value="pessimistic" ${scenarioUsed === 'pessimistic' ? 'checked' : ''}> Pesim.</label> <label><input type="radio" name="scenario" value="realistic" ${scenarioUsed !== 'pessimistic' && scenarioUsed !== 'optimistic' ? 'checked' : ''}> Reāli</label> <label><input type="radio" name="scenario" value="optimistic" ${scenarioUsed === 'optimistic' ? 'checked' : ''}> Optim.</label></div></div><div id="competitor-section" style="margin-bottom: 8px;"></div><div id="profitability-section"></div>`); var competitorSection = analysisContainer.find("#competitor-section"); var profitabilitySection = analysisContainer.find("#profitability-section"); if (data.competitors_found) { competitorSection.append('<h6>Konkurenti rādiusā</h6>'); var compSummaryHtml = '<table class="competitor-summary-table"><thead><tr><th>Tips</th><th>Skaits</th><th>Konkurenti</th></tr></thead><tbody>'; var hasAnyCompetitors = false; var competitorTypesDisplay = { 'Kafejnīca': 'Kafejnīcas', 'Restorāns': 'Restorāni', 'Krogs': 'Krogi', 'Frizētava': 'Frizētavas', 'Beķereja': 'Beķerejas', 'Aptieka': 'Aptiekas', 'Skaistumkopšana': 'Skaist. saloni', 'Minimārkets': 'Pārtikas veikali', 'Zobārsts': 'Zobārsti', 'Ātrā ēdināšana': 'Ātrā ēdināšana', 'Fitnesa klubs': 'Fitnesa klubi', 'Viesnīca': 'Naktsmītnes' }; Object.keys(competitorTypesDisplay).forEach(typeKey => { var compData = data.competitors_found[typeKey]; var count = compData?.count ?? 0; var names = compData?.names ?? []; var namesHtml = '-'; if (count > 0 && names.length > 0) { hasAnyCompetitors = true; namesHtml = names.map(name => $('<div>').text(name).html()).join(', '); } compSummaryHtml += `<tr><td>${competitorTypesDisplay[typeKey]}</td><td>${count}</td><td>${namesHtml}</td></tr>`; }); compSummaryHtml += '</tbody></table>'; competitorSection.append(compSummaryHtml); if (!hasAnyCompetitors && competitorImpactApplied) { competitorSection.append('<p class="small-note" style="margin-top:-8px;">(Neviens konkurents nav atrasts)</p>'); } else if (!competitorImpactApplied) { competitorSection.append('<p class="small-note" style="margin-top:-8px;">(Konkurentu ietekme nav ieslēgta)</p>'); } } else { competitorSection.append('<p class="small-note">Konkurentu dati nav ielādēti.</p>'); } var offs = data.offices_found || []; if ((stats.office_count || 0) > 0) { var offHtml = '<h6 style="margin-top:10px;">Biroji rādiusā (' + stats.office_count + ' ēkas, ' + (stats.office_workers||0).toLocaleString('lv-LV') + ' darb.)</h6>'; offHtml += '<table style="width:100%;border-collapse:collapse;font-size:0.85em;margin-bottom:12px;"><thead><tr><th style="text-align:left;border-bottom:1px solid #ccc;padding:2px;">Ēka (kadastrs)</th><th style="text-align:right;border-bottom:1px solid #ccc;padding:2px;">Darbinieki</th></tr></thead><tbody>'; var shown = Math.min(30, offs.length); for (var oi=0; oi<shown; oi++) { offHtml += '<tr><td style="text-align:left;padding:1px 2px;color:#555;">' + offs[oi].eka + '</td><td style="text-align:right;padding:1px 2px;font-weight:bold;">' + offs[oi].workers + '</td></tr>'; } if (stats.office_count > shown) { offHtml += '<tr><td colspan="2" style="color:#888;text-align:center;padding:2px;">... un vēl ' + (stats.office_count - shown) + ' ēkas</td></tr>'; } offHtml += '</tbody></table>'; competitorSection.append(offHtml); } var TT = {hotel:'Viesnīca',hostel:'Hostelis',guest_house:'Viesu nams',motel:'Motelis',apartment:'Apartaments',chalet:'Brīvdienu m.',museum:'Muzejs',attraction:'Apskates o.',viewpoint:'Skatu p.',zoo:'Zoo',gallery:'Galerija',theme_park:'Atrakc. parks',aquarium:'Akvārijs'}; var tour = data.tourism_found || []; if ((stats.tourism_count || 0) > 0) { var tHtml = '<h6 style="margin-top:10px;">Tūrisma objekti rādiusā (' + stats.tourism_count + ')</h6>'; tHtml += '<table style="width:100%;border-collapse:collapse;font-size:0.85em;margin-bottom:12px;"><thead><tr><th style="text-align:left;border-bottom:1px solid #ccc;padding:2px;">Objekts</th><th style="text-align:left;border-bottom:1px solid #ccc;padding:2px;">Tips</th><th style="text-align:right;border-bottom:1px solid #ccc;padding:2px;">Svars</th></tr></thead><tbody>'; var ts = Math.min(25, tour.length); for (var ti=0; ti<ts; ti++) { var to=tour[ti]; tHtml += '<tr><td style="text-align:left;padding:1px 2px;">' + (to.name||'(nenosaukts)') + '</td><td style="text-align:left;padding:1px 2px;color:#777;">' + (TT[to.type]||to.type) + '</td><td style="text-align:right;padding:1px 2px;font-weight:bold;">' + to.score + '</td></tr>'; } if (stats.tourism_count > ts) { tHtml += '<tr><td colspan="3" style="color:#888;text-align:center;padding:2px;">... un vēl ' + (stats.tourism_count - ts) + ' objekti</td></tr>'; } tHtml += '</tbody></table>'; competitorSection.append(tHtml); } var IT = {skola:'Skola',slimnica:'Slimnīca',stacija:'Stacija',izklaide:'Izklaide',sports:'Sports'}; var instL = data.institutions_found || []; if ((stats.inst_count || 0) > 0) { var iHtml = '<h6 style="margin-top:10px;">Iestādes rādiusā (' + stats.inst_count + ' ēkas, ' + (stats.inst_people||0).toLocaleString('lv-LV') + ' cilv./d)</h6>'; iHtml += '<table style="width:100%;border-collapse:collapse;font-size:0.85em;margin-bottom:12px;"><thead><tr><th style="text-align:left;border-bottom:1px solid #ccc;padding:2px;">Ēka (kadastrs)</th><th style="text-align:left;border-bottom:1px solid #ccc;padding:2px;">Kategorija</th><th style="text-align:right;border-bottom:1px solid #ccc;padding:2px;">Cilvēki/d</th></tr></thead><tbody>'; var isn = Math.min(20, instL.length); for (var ii=0; ii<isn; ii++) { iHtml += '<tr><td style="text-align:left;padding:1px 2px;color:#555;">' + instL[ii].eka + '</td><td style="text-align:left;padding:1px 2px;color:#777;">' + (IT[instL[ii].cat]||instL[ii].cat) + '</td><td style="text-align:right;padding:1px 2px;font-weight:bold;">' + instL[ii].people + '</td></tr>'; } if (stats.inst_count > isn) { iHtml += '<tr><td colspan="3" style="color:#888;text-align:center;padding:2px;">... un vēl ' + (stats.inst_count - isn) + ' ēkas</td></tr>'; } iHtml += '</tbody></table>'; competitorSection.append(iHtml); } if (totalIedzivotaji > 0 && stats.profitability_results) { var impactText = competitorImpactApplied ? "ar konkurentiem" : "bez konkurentiem"; var scenarioText = (scenarioUsed === 'optimistic') ? 'Optimistiski' : ((scenarioUsed === 'pessimistic') ? 'Pesimistiski' : 'Reālais'); profitabilitySection.append(`<h6>Biznesa Potenciāls (Mēneša peļņa/zaudējumi, €)</h6><p class='small-note' style='margin-top:-4px; margin-bottom: 6px;'>(${scenarioText}, ${impactText}, katram tipam tā optimālais izmērs)</p>`); var profitData = stats.profitability_results; var establishmentTypesDisplay = {'Kafejnīca': 'Kafejnīca','Restorāns': 'Restorāns','Krogs': 'Krogs','Frizētava': 'Frizētava','Beķereja': 'Beķereja','Aptieka': 'Aptieka','Skaistumkopšana': 'Skaistumkopšana','Minimārkets': 'Minimārkets','Zobārsts': 'Zobārsts','Ātrā ēdināšana': 'Ātrā ēdināšana','Fitnesa klubs': 'Fitnesa klubs','Viesnīca': 'Viesnīca'}; var pplLevels = ['E', 'D', 'C', 'B', 'A']; var summaryHtml = '<table class="profit-summary-table"><thead><tr><th>Iestādes līmenis</th>'; pplLevels.forEach(level => summaryHtml += `<th title="Līmenis ${level}">${generatePplEuroSymbols(level)}</th>`); summaryHtml += '</tr></thead><tbody>'; Object.keys(establishmentTypesDisplay).forEach(typeKey => { if(profitData[typeKey]) { summaryHtml += `<tr><td>${establishmentTypesDisplay[typeKey]}</td>`; pplLevels.forEach(level => { var profit = profitData[typeKey]?.[level]?.profit; var profitClass = 'profit-zero'; var profitText = '-'; if (typeof profit === 'number') { profitText = profit.toFixed(0); if (profit > 0.05) profitClass = 'profit-positive'; else if (profit < -0.05) profitClass = 'profit-negative'; } summaryHtml += `<td class="profit-value ${profitClass}">${profitText}</td>`; }); summaryHtml += '</tr>'; } }); summaryHtml += '</tbody></table>'; profitabilitySection.append(summaryHtml); profitabilitySection.append('<button id="toggle-details-btn">Rādīt/Slēpt Detaļas</button>'); var detailsHtml = '<div id="profit-details-container">'; Object.keys(establishmentTypesDisplay).forEach(typeKey => { if(profitData[typeKey]) { var oszD = (stats.optimal_setups || {})[typeKey]; detailsHtml += `<h6 style='font-size: 0.9em; margin-bottom: 4px;'>${establishmentTypesDisplay[typeKey]} - Detaļas${oszD ? ' (' + oszD.size + ', ' + oszD.m2 + ' m²)' : ''}</h6>`; detailsHtml += `<table class="profit-details-table"><thead><tr><th>PPL</th><th title="Apmeklētāji dienā">Apm./d</th><th title="Bezzaudējuma punkts (klienti/dienā, lai segtu izmaksas)">Bezz.</th><th title="Neto ieņēmumi mēnesī, bez PVN (€)">Ieņ./mēn</th> <th class="cost-col-head" title="Personāla izmaksas mēnesī (€)">Pers</th><th class="cost-col-head" title="Nomas izmaksas mēnesī (€)">Noma</th><th class="cost-col-head" title="Izejvielu/Materiālu izmaksas mēnesī (€)">COGS</th><th class="cost-col-head" title="Neto PVN valstij mēnesī (€)">PVN</th><th class="cost-col-head" title="Komunālie/Citi izdevumi mēnesī (€)">Citi</th><th class="cost-col-head" title="Kredīta maksājums mēnesī (€)">Kred.</th> <th title="Peļņa/Zaudējumi mēnesī (€)">P/Z mēn</th><th title="Aptuvenās sākotnējās investīcijas (€)">Invest.</th></tr></thead><tbody>`; pplLevels.forEach(level => { var d = profitData[typeKey]?.[level]; var profit = d?.profit; var profitClass = 'profit-zero'; if (typeof profit === 'number') { if (profit > 0.05) profitClass = 'profit-positive'; else if (profit < -0.05) profitClass = 'profit-negative'; } let visitors = d?.visitors ?? '-'; if (typeof visitors === 'number') visitors = Math.round(visitors); let be = d?.breakeven_visitors ?? '-'; let revenue = d?.revenue?.toFixed(0) ?? '-'; let staff = d?.cost_staff?.toFixed(0) ?? '-'; let rent = d?.cost_rent?.toFixed(0) ?? '-'; let cogs = d?.cost_cogs?.toFixed(0) ?? '-'; let pvn = d?.cost_pvn?.toFixed(0) ?? '-'; let other = ((d?.cost_utilities ?? 0) + (d?.cost_other_op ?? 0)).toFixed(0); let loan = d?.cost_loan?.toFixed(0) ?? '-'; let profitVal = profit?.toFixed(0) ?? '-'; let investment = d?.investment?.toLocaleString('lv-LV', {maximumFractionDigits: 0}) ?? '-'; detailsHtml += `<tr><td>${generatePplEuroSymbols(level)}</td> <td>${visitors}</td><td>${be}</td><td>${revenue}</td> <td class="cost-col">${staff}</td><td class="cost-col">${rent}</td><td class="cost-col">${cogs}</td><td class="cost-col">${pvn}</td><td class="cost-col">${other}</td><td class="cost-col">${loan}</td> <td class="${profitClass}">${profitVal}</td><td>${investment}</td></tr>`; }); detailsHtml += '</tbody></table>'; } }); detailsHtml += '</div>'; profitabilitySection.append(detailsHtml); } else if (totalIedzivotaji <= 0 && pointsCount > 0) { profitabilitySection.append('<p class="small-note">Peļņas aprēķins nav iespējams (nav iedzīvotāju).</p>'); } else if (pointsCount === 0) { profitabilitySection.append('<p class="small-note">Peļņas aprēķins nav iespējams (nav ēku).</p>'); } else { profitabilitySection.append('<p class="small-note">Peļņas dati nav pieejami.</p>'); } analysisContainer.css('width', window._analysisExpanded ? '420px' : 'auto'); analysisContainer.fadeIn(); }

// triggerSearchRequest
function triggerSearchRequest() { /* ... nemainīgs ... */ if (!lastClickCoords) { return; } var clickLat = lastClickCoords.lat; var clickLng = lastClickCoords.lng; var considerCompetitors = $('#analysis-container').is(':visible') ? $('#cb-competitors').is(':checked') : true; var selectedScenario = window._selScenario || ($('#analysis-container').is(':visible') ? $('input[name="scenario"]:checked').val() : 'realistic'); var selectedRadius = currentRadius; console.log(`JS: Izsauc AJAX search (Lat: ${clickLat.toFixed(4)}, Lng: ${clickLng.toFixed(4)}, Radius: ${selectedRadius}, Competitors: ${considerCompetitors}, Scenario: ${selectedScenario})`); $("#sidebar-content").html('<p class="loading-text"><i class="fas fa-spinner fa-spin"></i> Meklē datus...</p>'); $('#click-prompt').hide(); if ($("#analysis-container").is(':visible')) { $("#analysis-container").hide().empty(); } if (currentDonutChart) { currentDonutChart.destroy(); currentDonutChart = null; } window._popupExpanded = false; map.eachLayer(function(l){ if (l instanceof L.Popup) { map.removeLayer(l); } }); /* vecie popupi (autoClose:false) jānovāc pašiem */ buildingMarkersLayer.clearLayers(); if (searchCircle) { map.removeLayer(searchCircle); searchCircle = null; } if (centerMarker) { map.removeLayer(centerMarker); centerMarker = null; } searchCircle = L.circle([clickLat, clickLng], { radius: selectedRadius, color: 'blue', weight: 1, fillOpacity: 0.05, interactive: false }).addTo(map); centerMarker = L.marker([clickLat, clickLng], { icon: L.divIcon({ html: '<div style="color:#d8000c;font-size:24px;font-weight:bold;line-height:1;text-shadow:0 0 3px #fff,0 0 3px #fff,0 0 3px #fff;">\u2715</div>', iconSize: [24,24], iconAnchor: [12,12], className: 'center-cross-icon' }), zIndexOffset: 1000, interactive: true }).addTo(map); 
    
    // === AJAX PIEPRASĪJUMS ATJAUNINĀTS ===
    $.getJSON(AJAX_URL, { action: 'search', lat: clickLat, lng: clickLng, radius: selectedRadius, competitors: considerCompetitors, scenario: selectedScenario, loan: (window._loanOn !== false), lres: true, loff: true, ltour: true, linst: true }) 
    .done(function(data) { console.log("JS: AJAX atbilde (search):", data); if (data && data.type === 'search_results') { currentRadius = data.radius_used || selectedRadius; if ($('#radius-input').val() != currentRadius) { $('#radius-slider').val(currentRadius); $('#radius-input').val(currentRadius); } updateSidebar(data); updateAnalysisPanel(data); if (data.statistics && data.statistics.optimal_setups) { window._pessOptimal = data.statistics.pessimistic_optimal || null; buildOptimalPopup(data.statistics.optimal_setups, data.scenario_used, data.statistics.profitability_results, data.competitors_found); } if (!data.error && data.points?.length > 0) { displayBuildingPoints(data.points); } else { buildingMarkersLayer.clearLayers(); } if (!data.error) { displayGeneratorPoints(data); } } else { updateSidebar({ error: data.error || "Negaidīta servera atbilde." }); $("#analysis-container").hide().empty(); } }) .fail(function(jqXHR, textStatus, errorThrown) { console.error("JS: AJAX kļūda (search):", textStatus, errorThrown, jqXHR.responseText); var errorMsg = `Kļūda saziņā ar serveri (${textStatus}). Mēģiniet vēlreiz vēlāk.`; try { let responseData = JSON.parse(jqXHR.responseText); if (responseData && responseData.error) { errorMsg = responseData.error; } } catch(e) {} $("#sidebar-content").html(`<p class="error-text">${errorMsg}</p>`); $("#analysis-container").hide().empty(); }); }


window._selScenario = null;
function setScenarioFromPopup(sc){ window._selScenario = sc; $('input[name="scenario"][value="'+sc+'"]').prop('checked', true); if (lastClickCoords) triggerSearchRequest(); runHeatmap(window._hmCenter); }
function eurFmt(n){ return '\u20ac' + Math.round(n).toLocaleString('lv-LV'); }
function eurPM(n){ var c = n >= 0 ? '#1e8449' : '#c0392b'; return '<b style="color:'+c+';">' + (n >= 0 ? '+' : '\u2212') + eurFmt(Math.abs(n)) + '/mēn</b>'; }
function togglePopupDetail(i){ var el = document.getElementById('popt-'+i); if (!el) return; var vis = el.style.display !== 'none'; el.style.display = vis ? 'none' : 'block'; var btn = document.getElementById('poptb-'+i); if (btn) btn.textContent = vis ? 'Plašāk' : 'Mazāk'; }
function optRow(k,v){ return '<tr><td style="color:#555;padding:1px 8px 1px 0;white-space:nowrap;">'+k+'</td><td style="font-weight:bold;text-align:right;">'+v+'</td></tr>'; }
function buildOptimalPopup(setups, scenarioUsed, profResults, compFound){
  if (!centerMarker || !setups) return;
  window._lastPopupArgs = [setups, scenarioUsed, profResults, compFound]; /* Biznesa veidi checkbox pārbūvei bez servera */
  var bindFresh = function(h, w){ centerMarker.unbindPopup(); centerMarker.bindPopup(h, {maxWidth: w, autoClose: false, closeOnClick: false}).openPopup(); }; /* unbind+bind — citādi atvērta popupa DOM neatsvaidzinās */
  var icons = {'Kafejnīca':'☕','Restorāns':'🍴','Krogs':'🍺','Frizētava':'✂️','Beķereja':'🥐','Aptieka':'💊','Skaistumkopšana':'💅','Minimārkets':'🛒','Zobārsts':'🦷','Ātrā ēdināšana':'🍕','Fitnesa klubs':'🏋️','Viesnīca':'🏨'}; var ZN = {kurorts:'kūrorta zona',riga:'Rīga',piekraste:'piekraste',iekszeme:'iekšzeme'};
  var bizOn = function(t){ var el = $('#demand-controls input.cb-biz[data-biztype="' + t + '"]'); return el.length ? el.is(':checked') : true; };
  var order = ['Kafejnīca','Restorāns','Krogs','Frizētava','Beķereja','Aptieka','Skaistumkopšana','Minimārkets','Zobārsts','Ātrā ēdināšana','Fitnesa klubs','Viesnīca'].filter(function(t){ return setups[t] && bizOn(t); });
  order.sort(function(a,b){ return setups[b].profit_month - setups[a].profit_month; });
  if (!window._popupExpanded) { /* MINI versija: tikai virsraksti + Izvērst poga */
    var mh = '<div style="min-width:255px;max-width:300px;font-size:0.95em;line-height:1.6;">'
      + '<div style="font-weight:bold;font-size:1.05em;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:5px;">📍 Optimālais bizness šeit</div>';
    order.forEach(function(t){ var sx = setups[t]; var pc = sx.profit_month >= 0 ? '#0a0' : '#d8000c'; var sign = sx.profit_month >= 0 ? '+' : '\u2212';
      /* Aptieka: skaitlis rāda darbojošās aptiekas ekonomiku, bet JAUNAS atvēršanu
         ierobežo licencēšanas izvietojuma kritēriji — bez brīdinājuma tas maldina. */
      var lic = t === 'Aptieka' ? ' <span title="Jaunas aptiekas atvēršanu ierobežo licencēšanas izvietojuma kritēriji (attālums/iedzīvotāju skaits) — parasti iespējama tikai esošas aptiekas pārpirkšana." style="cursor:help;">⚠️</span>' : '';
      /* Pesimistiskais diapazons: lejupvērstais risks redzams bez scenārija
         pārslēgšanas (pesim. skatā pašā to nerāda — dublētu galveno skaitli). */
      var pes = (window._pessOptimal && typeof window._pessOptimal[t] === 'number' && (scenarioUsed||'realistic') !== 'pessimistic') ? window._pessOptimal[t] : null;
      var pesHtml = pes === null ? '' : '<div style="font-size:0.78em;color:' + (pes < 0 ? '#d8000c' : '#888') + ';text-align:right;margin-top:-2px;">pesim. ' + (pes >= 0 ? '+' : '−') + eurFmt(Math.abs(pes)) + '</div>';
      mh += '<div style="display:flex;justify-content:space-between;gap:10px;"><span>' + (icons[t]||'') + ' ' + t + lic + '</span><span style="text-align:right;"><b style="color:' + pc + ';white-space:nowrap;">' + sign + eurFmt(Math.abs(sx.profit_month)) + '/mēn</b>' + pesHtml + '</span></div>'; });
    mh += '<button onclick="expandOptimalPopup()" style="display:block;width:100%;margin-top:8px;padding:7px;background:#2c7be5;color:#fff;border:none;border-radius:5px;font-weight:bold;font-size:1em;cursor:pointer;">🔍 Izvērst izpēti</button></div>';
    bindFresh(mh, 320);
    return;
  }
  var nT = order.length; var colsN = Math.min(3, Math.max(1, Math.ceil(nT / 4))); var colsFit = Math.max(1, Math.floor((map.getSize().x - 120) / 362)); var gridCols = Math.max(1, Math.min(colsN, colsFit)); var gridRows = Math.ceil(nT / gridCols); var popW = gridCols * 350 + (gridCols - 1) * 10 + 20; /* adaptīvi: max 4 blokus kolonnā, bet ne platāk par ekrānu */
  var scBtn = function(sc,lbl){ var act = (scenarioUsed||'realistic')===sc; return '<button onclick="setScenarioFromPopup(\''+sc+'\')" style="font-size:0.85em;padding:2px 7px;margin-left:4px;border-radius:4px;cursor:pointer;border:1px solid '+(act?'#333':'#bbb')+';background:'+(act?'#333':'#f5f5f5')+';color:'+(act?'#fff':'#444')+';font-weight:'+(act?'bold':'normal')+';">'+lbl+'</button>'; };
  var html = '<div style="max-width:' + popW + 'px;font-size:0.95em;line-height:1.35;">'
    + '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:6px;">'
    + '<span style="font-weight:bold;font-size:1.1em;white-space:nowrap;">📍 Optimālais bizness šeit</span>'
    + '<span style="white-space:nowrap;">' + scBtn('pessimistic','Pesimistiski') + scBtn('realistic','Reālais') + scBtn('optimistic','Optimistiski') + '<button onclick="collapseOptimalPopup()" title="Savērst" style="font-size:0.85em;padding:2px 7px;margin-left:6px;border-radius:4px;cursor:pointer;border:1px solid #bbb;background:#f5f5f5;">▲</button></span>'
    + '</div>'
    + '<div style="display:grid;grid-template-rows:repeat(' + gridRows + ',auto);grid-auto-flow:column;grid-auto-columns:350px;gap:6px 10px;align-items:start;">'; /* aizpilda pa kolonnām pēc peļņas */
  order.forEach(function(t,i){
    var sx = setups[t];
    var pc = sx.profit_month >= 0 ? '#0a0' : '#d8000c';
    var sign = sx.profit_month >= 0 ? '+' : '\u2212';
    var best = (i===0 && sx.profit_month>=0) ? ' <span style="background:#0a0;color:#fff;font-size:0.82em;padding:0 4px;border-radius:3px;">★ labākais</span>' : '';
    var pr = (profResults || {})[t]; var simRows = null;
    if (sx.sim && sx.sim.length) { simRows = sx.sim; } /* tā paša OPTIMĀLĀ izmēra simulācija — sakrīt ar virsrakstu */
    else if (pr) { simRows = ['E','D','C','B','A'].map(function(Lv){ var r = pr[Lv]; return r ? {check: r.price, visitors: r.visitors, revenue: r.revenue, profit: r.profit} : null; }).filter(Boolean); }
    var extraTbl = '';
    if (simRows && simRows.length) { extraTbl = '<div id="popt-'+i+'" style="display:none;margin-top:5px;border-top:1px dashed #ddd;padding-top:4px;">'
      + '<div style="color:#777;font-size:0.9em;margin-bottom:3px;">Simulācija pie dažādiem čekiem (' + sx.size + ', ' + sx.m2 + ' m²):</div>'
      + '<table style="width:100%;border-collapse:collapse;font-size:0.92em;"><tr><th style="text-align:left;padding:1px 2px;border-bottom:1px solid #ddd;">čeks</th><th style="text-align:right;padding:1px 2px;border-bottom:1px solid #ddd;">klienti/d</th><th style="text-align:right;padding:1px 2px;border-bottom:1px solid #ddd;">apgroz./mēn</th><th style="text-align:right;padding:1px 2px;border-bottom:1px solid #ddd;">peļņa/mēn</th></tr>'
      + simRows.map(function(r){ var pc2 = r.profit >= 0 ? '#1e8449' : '#c0392b';
          return '<tr><td style="padding:1px 2px;">\u20AC' + Number(r.check).toFixed(2) + '</td><td style="text-align:right;padding:1px 2px;">' + Math.round(r.visitors) + '</td><td style="text-align:right;padding:1px 2px;">' + eurFmt(r.revenue) + '</td><td style="text-align:right;padding:1px 2px;font-weight:bold;color:' + pc2 + ';">' + (r.profit >= 0 ? '+' : '') + eurFmt(r.profit) + '</td></tr>'; }).join('')
      + '</table></div>'; }
    html += '<div style="padding:5px 6px;border:1px solid #e5e5e5;border-radius:4px;' + (i===0 ? 'background:#f6fff6;' : '') + '">'
      + '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:6px;">'
      + '<span style="font-weight:bold;">' + (icons[t]||'') + ' ' + t + ' — ' + sx.size + best + '</span>'
      + '<span style="white-space:nowrap;"><b style="color:' + pc + ';">' + sign + eurFmt(Math.abs(sx.profit_month)) + '/mēn</b><button id="poptb-'+i+'" onclick="togglePopupDetail('+i+')" style="font-size:0.82em;padding:1px 7px;margin-left:6px;border:1px solid #bbb;border-radius:4px;background:#f5f5f5;cursor:pointer;">Plašāk</button></span>'
      + '</div>'
      + '<table style="color:#444;margin-top:5px;font-size:0.92em;width:100%;border-collapse:collapse;">'
      + '<tr><td style="padding:1px 10px 1px 0;">optimālā platība: <b>' + sx.m2 + ' m²</b></td><td style="padding:1px 0;">čeks no klienta: <b>€' + Number(sx.check).toFixed(2) + '</b></td></tr>' + '<tr><td style="padding:1px 10px 1px 0;">darbinieku skaits: <b>' + sx.staff + '</b></td><td style="padding:1px 0;">klientu skaits dienā: <b>~' + sx.visitors + '</b></td></tr>' + '<tr><td style="padding:1px 10px 1px 0;">alga: <b>' + eurFmt(sx.salary) + '</b></td><td style="padding:1px 0;">ierīkošana: <b>' + eurFmt(sx.investment) + '</b></td></tr>'
      + (compFound && compFound[t] && compFound[t].count !== undefined ? '<tr><td colspan="2" style="padding:1px 0;color:#666;">konkurenti rādiusā: <b>' + compFound[t].count + '</b></td></tr>' : '') + '</table>' + (sx.profit_y1 !== undefined ? '<div style="margin-top:4px;padding-top:3px;border-top:1px dashed #e5e5e5;color:#555;font-size:0.92em;">Palaišana: 1. gads ' + eurPM(sx.profit_y1) + ' → 2. gads ' + eurPM(sx.profit_y2) + ' → tālāk ' + eurPM(sx.profit_month) + '</div>' : '') + ((sx.profit_summer !== undefined && sx.profit_summer !== sx.profit_winter) ? '<div style="color:#555;font-size:0.92em;">Sezona (' + (ZN[sx.season_zone]||sx.season_zone) + '): vasaras mēn. ' + eurPM(sx.profit_summer) + ' · ziemas mēn. ' + eurPM(sx.profit_winter) + '</div>' : '') + extraTbl + '</div>';
  });
  html += '</div></div>';
  bindFresh(html, popW);
}
function expandOptimalPopup(){ window._popupExpanded = true; if (window._lastPopupArgs) buildOptimalPopup.apply(null, window._lastPopupArgs); }
function collapseOptimalPopup(){ window._popupExpanded = false; if (window._lastPopupArgs) buildOptimalPopup.apply(null, window._lastPopupArgs); }
var mapLegend = L.control({ position: 'bottomleft' });
mapLegend.onAdd = function() {
  var div = L.DomUtil.create('div', 'map-shape-legend');
  div.innerHTML = '<b style="display:block;margin-bottom:2px;">Apzīmējumi kartē</b>'
    + '<span class="lg-shape" style="width:11px;height:11px;border-radius:50%;background:conic-gradient(darkgreen, lime, dodgerblue, orange, red, darkgreen);border:1px solid #555;"></span>Dzīvojamā ēka (krāsa = pirktspēja)<br>'
    + '<span class="lg-shape" style="width:11px;height:11px;background:#1f6fb2;border:1px solid rgba(0,0,0,0.35);"></span>Birojs (darbinieki)<br>'
    + '<span class="lg-shape" style="width:12px;height:12px;background:#8e44ad;clip-path:polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%);"></span>Iestāde (skola, slimnīca...)<br>'
    + '<span class="lg-shape" style="width:12px;height:12px;background:#e67e22;clip-path:polygon(50% 0%, 100% 100%, 0% 100%);"></span>Tūrisma objekts<br>'
    + '<span class="lg-shape" style="color:#d8000c;font-weight:bold;width:12px;text-align:center;">\u2715</span>Izvēlētā analīzes vieta';
  return div;
};
mapLegend.addTo(map);
var heatLayer = L.layerGroup().addTo(map); window._hmCenter = null;
function hmRender(d){
  heatLayer.clearLayers();
  var ps = d.cells.map(function(c){ return c.p; });
  var mx = Math.max.apply(null, ps), mn = Math.min.apply(null, ps);
  var mode = d.hm_mode || 'profit'; var lp = function(a,b,u){ return Math.round(a+(b-a)*u); };
  var hmColor = function(t){ var r,g,bl;
    if (mode === 'day') { return 'rgb('+lp(255,230,t)+','+lp(249,158,t)+','+lp(200,5,t)+')'; }   /* bāli dzeltens -> dzintara */
    if (mode === 'night') { return 'rgb('+lp(198,8,t)+','+lp(219,48,t)+','+lp(239,107,t)+')'; }  /* gaiši zils -> tumši zils */
    if (t < 0.5) { var u = t/0.5; r = lp(192,241,u); g = lp(57,196,u); bl = lp(43,15,u); }
    else { var u2 = (t-0.5)/0.5; r = lp(241,30,u2); g = lp(196,132,u2); bl = lp(15,73,u2); }
    return 'rgb('+r+','+g+','+bl+')'; };
  d.cells.forEach(function(c){
    var t = (mx > mn) ? (c.p - mn) / (mx - mn) : 0.5;
    var rect = L.rectangle([[c.lat - d.grid.latStep/2, c.lng - d.grid.lngStep/2], [c.lat + d.grid.latStep/2, c.lng + d.grid.lngStep/2]],
      { stroke: false, fillColor: hmColor(t), fillOpacity: 0.30 + 0.15 * t, interactive: true });
    rect.bindTooltip((mode === 'profit') ? ((c.p >= 0 ? '+' : '') + c.p.toLocaleString('lv-LV') + ' \u20AC/mēn') : ('~' + c.p.toLocaleString('lv-LV') + ' cilv.'), {sticky: true});
    heatLayer.addLayer(rect);
  });
  if (mode === 'profit') { $('#hm-info').html('\uD83D\uDFE2 labākais: <b>' + (mx >= 0 ? '+' : '') + mx.toLocaleString('lv-LV') + ' \u20AC/mēn</b> · \uD83D\uDD34 sliktākais: ' + mn.toLocaleString('lv-LV') + ' \u20AC/mēn<br>Klikšķis kartē = analīze + siltumkarte ap to vietu.').show(); } else { $('#hm-info').html(((mode === 'day') ? '\u2600\uFE0F Dienas populācija' : '\uD83C\uDF19 Nakts populācija') + ' — visvairāk: <b>~' + mx.toLocaleString('lv-LV') + ' cilv.</b> · vismazāk: ~' + mn.toLocaleString('lv-LV') + ' cilv.<br>Klikšķis kartē = analīze + siltumkarte ap to vietu.').show(); }
}
function runHeatmap(center){
  if (!$('#cb-heatmap').is(':checked')) return;
  var b = map.getBounds();
  var spanLat = Math.min(b.getNorth() - b.getSouth(), 0.088);
  var spanLng = Math.min(b.getEast() - b.getWest(), 0.176);
  var cla = center ? center.lat : (b.getSouth() + b.getNorth()) / 2;
  var clo = center ? center.lng : (b.getWest() + b.getEast()) / 2;
  window._hmCenter = { lat: cla, lng: clo };
  $('#hm-info').text('\u23F3 Rēķina siltumkarti...').show();
  var params = { action: 'heatmap', btype: $('#hm-type').val(), s: cla - spanLat/2, n: cla + spanLat/2, w: clo - spanLng/2, e: clo + spanLng/2,
    scenario: window._selScenario || 'realistic',
    competitors: ($('#cb-competitors').length ? $('#cb-competitors').is(':checked') : true),
    loan: (window._loanOn !== false),
    lres: true, loff: true, ltour: true, linst: true };
  $.getJSON(AJAX_URL, params).done(function(d){
    if (!$('#cb-heatmap').is(':checked')) return;
    if (d.error || !d.cells || !d.cells.length) { heatLayer.clearLayers(); $('#hm-info').text(d.error || 'Šajā apgabalā nav datu.').show(); return; }
    hmRender(d);
  }).fail(function(){ $('#hm-info').text('Kļūda rēķinot siltumkarti.').show(); });
}
$('#cb-heatmap').on('change', function(){
  if ($(this).is(':checked')) { runHeatmap(null); }
  else { heatLayer.clearLayers(); window._hmCenter = null; $('#hm-info').hide(); }
});
$('#hm-type').on('change', function(){ runHeatmap(window._hmCenter); });
// --- Event Listeners ---

// Klikšķis uz kartes
map.on('click', function(e) { lastClickCoords = { lat: e.latlng.lat, lng: e.latlng.lng }; triggerSearchRequest(); if ($('#cb-heatmap').is(':checked')) { runHeatmap(e.latlng); } });

// Rādiusa kontroles
$('#radius-slider').on('input', function() { let value = $(this).val(); $('#radius-input').val(value); });
$('#radius-slider').on('change', function() { currentRadius = parseInt($(this).val(), 10); if (lastClickCoords) { triggerSearchRequest(); } });
$('#radius-input').on('change', function() { let value = parseInt($(this).val(), 10); const min = parseInt($(this).attr('min'), 10); const max = parseInt($(this).attr('max'), 10); if (isNaN(value) || value < min) { value = min; } else if (value > max) { value = max; } $(this).val(value); $('#radius-slider').val(value); currentRadius = value; if (lastClickCoords) { triggerSearchRequest(); } });
$('#radius-toggle-btn').on('click', function() { $('#radius-controls-collapsible').slideToggle(200); $(this).text($(this).text() === '▼' ? '▲' : '▼'); });

// POI Checkbox
$('#layer-controls-body input[type="checkbox"]').on('change', function() { var type = $(this).val(); var lyr = poiLayers[type]; if (!lyr) return; if (!$(this).is(':checked')) { if (map.hasLayer(lyr)) map.removeLayer(lyr); return; } if (poiDataCache[type]) { displayPoiPoints(poiDataCache[type], type); return; } $.getJSON(AJAX_URL, { action: 'get_points', type: type }).done(function(data) { if (data && data.type === type && !data.error && data.points !== undefined) { poiDataCache[type] = data.points; displayPoiPoints(data.points, type); } }).fail(function(){ console.error('POI ielāde neizdevās: ' + type); }); });
$('#hd-comp').on('click', function(){ var b = $('#layer-controls-body'); var vis = b.is(':visible'); b.slideToggle(150); $('#hd-comp-arr').text(vis ? '▸' : '▾'); });
$('#hd-biz').on('click', function(){ var b = $('#demand-controls-body'); var vis = b.is(':visible'); b.slideToggle(150); $('#hd-biz-arr').text(vis ? '▸' : '▾'); });
// Citi Event Listeners (labajam panelim)
$(document).on('change', '#cb-competitors', function() { if (lastClickCoords) triggerSearchRequest(); runHeatmap(window._hmCenter); });
$(document).on('change', '#cb-loan', function() { window._loanOn = $(this).is(':checked'); if (lastClickCoords) triggerSearchRequest(); runHeatmap(window._hmCenter); });
$(document).on('change', 'input[name="scenario"]', function() { window._selScenario = $(this).val(); if (lastClickCoords) triggerSearchRequest(); runHeatmap(window._hmCenter); });
$('#demand-controls input.cb-biz').on('change', function() { if (window._lastPopupArgs) { buildOptimalPopup.apply(null, window._lastPopupArgs); } });
window._analysisExpanded = false;
$(document).on('click', '#analysis-toggle', function() { window._analysisExpanded = !window._analysisExpanded; $('#analysis-body').toggle(); $(this).text(window._analysisExpanded ? 'Mazāk ▲' : 'Detalizētāk ▼'); $('#analysis-container').css('width', window._analysisExpanded ? '420px' : 'auto'); });
$(document).on('click', '#toggle-details-btn', function() { $('#profit-details-container').slideToggle(200); });

// Sākuma stāvoklis
$(document).ready(function() {
    currentRadius = parseInt($('#radius-slider').val(), 10); $('#radius-input').val(currentRadius);
    $("#sidebar-content").empty(); $('#click-prompt').show(); $("#analysis-container").hide();
    $('#radius-controls-collapsible').hide(); $('#radius-toggle-btn').text('▼');
});

</script>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/cookie/cookie.php'; ?>
</body>
</html>