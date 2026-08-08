<?php
/**
 * Valsts profils — LATVIJA.
 *
 * Šeit ir VISS, kas Iespējas konveijerā ir atkarīgs no valsts: reģionu karte,
 * kalibrācijas koeficienti un POI tipu reģistrs. Soļu kods (step*.php) ir
 * valstij neitrāls un citai valstij nav jāmaina — jāmaina ir šis fails un datu
 * ielasīšanas daļa, jo avotu formāti katrā valstī atšķiras.
 *
 * Jaunu valsti sāc, nokopējot šo failu uz countries/<kods>.php.
 *
 * ── KAS ŠEIT IR SVARĪGĀKAIS ────────────────────────────────────────────────
 *
 * Kalibrācija, nevis shēma, ir tā daļa, kas citā valstī klusi saražo blēņas.
 * Absolūts slieksnis "€700/m² = A līmenis" Rīgā nozīmē prestižu biroju, bet
 * Kopenhāgenā tas ir viduvējība, un Bukarestē tāda gandrīz nav. Tāpēc līmeņus
 * NEDEFINĒ ar absolūtiem cipariem — tos rēķina kā sadalījuma kvantiles
 * ('office.level_quantiles'), tieši tāpat, kā 3. solis to jau dara dzīvojamām
 * ēkām. Tā ir vienīgā pieeja, kas pārceļas uz citu valsti bez pārkalibrēšanas.
 *
 * Ēku klasifikācijas kodi (1220, 1263, …) ir ES kopīgais CC standarts, tāpēc
 * TIE pārceļas. Pārceļas kodi — nepārceļas koeficienti.
 */
declare(strict_types=1);

return [
    'code' => 'lv',
    'name' => 'Latvija',
    'iso'  => 'LV',              // ISO 3166-1 alpha-2 — Overpass area filtram

    /**
     * OSM datu avots 1. un 9. solim.
     *
     *   'pbf'      — Geofabrik izgriezums, lasīts ar pbf.php (tīrs PHP)
     *   'overpass' — Overpass API (vecā uzvedība, paliek kā rezerve)
     *
     * KĀPĒC PBF IR NOKLUSĒJUMS. Overpass ir brīvprātīgo uzturēta infrastruktūra:
     * pēdējā palaišanā galvenā instance bija pilnībā nesasniedzama, un 9. solis
     * caur rezerves serveriem vilkās 43 minūtes. Tas pats no PBF aizņem ~26 s,
     * un pēc satura abi sakrīt 0,03 % robežās (starpība = OSM dienas dreifs).
     * Lielām valstīm PBF vairs nav izvēle, bet vienīgais ceļš.
     *
     * Robežas filtram lieto administratīvo robežu NO PAŠA FAILA (relācija ar
     * ISO3166-1), nevis Geofabrik .poly — tas ir tikai 151 punkts, un tajā
     * Valga (Igaunija) iekrīt Latvijas iekšienē.
     *
     * Lielā valstī 'pbf_url' liek katram reģionam atsevišķi (Geofabrik publicē
     * pa federālajām zemēm), un tad tas kļūst par reģiona ETL vienību.
     */
    'osm' => [
        'source'  => 'pbf',
        'pbf_url' => 'https://download.geofabrik.de/europe/latvia-latest.osm.pbf',
        'max_age' => 7,           // dienas; vecāku vietējo failu lejupielādē no jauna
    ],

    /**
     * Reģioni = ēku slāņa šķēlumi UN ETL darba vienības vienlaikus.
     *
     * Latvijā ēku slānis ir ~337 tūkstoši rindu, tāpēc dalīt nav ko — viens
     * ieraksts, kas nosedz visu valsti, un tabula paliek `lv_buildings`.
     *
     * Lielai valstij šeit būtu pa ierakstam uz katru administratīvo vienību
     * (Vācijā 16 federālās zemes), un tabulas kļūtu `de_buildings_bayern` utt.
     * Robeža, aiz kuras to ir vērts darīt: ~2 miljoni rindu ēku slānī.
     *
     * bbox = [minLon, minLat, maxLon, maxLat]
     */
    'regions' => [
        ['code' => 'lv', 'name' => 'Latvija', 'bbox' => [20.5, 55.6, 28.3, 58.2]],
    ],

    // ── Biroju slānis (6. solis) ────────────────────────────────────────────
    'office' => [
        /** CC būves klasifikācijas kods → m² uz vienu darbinieku. */
        'density'  => ['1220' => 18, '1230' => 40, '1211' => 30, '1251' => 120, '1252' => 160],
        'usable'   => 0.75,          // lietderīgā platība no kopējās
        'max_area' => 100000,        // m² — atsijā kropļainos kadastra ierakstus

        /**
         * Pirktspējas līmeņa robežas kā kadastrālās vērtības (€/m²) sadalījuma
         * kvantiles, svērtas ar ēkas platību. Tie paši griezumi, ko 3. solis
         * lieto dzīvojamām ēkām, tāpēc `lv_buildings.lvl` un `lv_offices.lvl`
         * beidzot nozīmē vienu un to pašu.
         *
         *   < q35 → E,  < q55 → D,  < q75 → C,  < q90 → B,  pārējie → A
         */
        'level_quantiles' => [0.35, 0.55, 0.75, 0.90],
    ],

    // ── Iestāžu slānis (8. solis) ───────────────────────────────────────────
    'institutions' => [
        'usable' => 0.75,
        /** CC kods → [kategorija, m² uz vienu cilvēku dienā] */
        'kinds'  => [
            '1263' => ['skola',    12],   // skolas/universitātes: skolēni + personāls
            '1264' => ['slimnica', 20],   // slimnīcas: personāls + pacienti + apmeklētāji
            '1241' => ['stacija',   5],   // stacijas/termināļi: tranzīta caurplūde
            '1261' => ['izklaide', 15],   // teātri/kino: vidējotā dienas plūsma
            '1265' => ['sports',   25],   // sporta ēkas
        ],
    ],

    // ── Tūrisma slānis (7. solis) ───────────────────────────────────────────
    'tourism' => [
        'source' => 'Turisma objekti.txt',   // OSM Overpass eksports
        /** Bāzes svars = aptuvenais dienas tūristu skaits vidējai populāritātei. */
        'base' => [
            'hotel' => 28, 'hostel' => 22, 'guest_house' => 10, 'motel' => 14,
            'apartment' => 6, 'chalet' => 5,
            'museum' => 24, 'attraction' => 20, 'zoo' => 55, 'theme_park' => 45,
            'aquarium' => 38, 'gallery' => 12, 'viewpoint' => 8,
        ],
        /** Naktsmītņu tipi — viesnīcu konkurenti nāk no šī slāņa, ne no POI. */
        'stay_types' => ['hotel', 'hostel', 'guest_house', 'motel', 'apartment', 'chalet'],
    ],

    /**
     * POI tipu reģistrs — `lv_poi.ptype` vērtības.
     *
     * Ierakstiem ar 'csv' datus ievāc 1. solis (nosaukums obligāts, tikai OSM
     * mezgli — tāds bija Python oriģināls), un 5. solis tos ielasa no faila.
     * Pārējos 9. solis ņem no Overpass tieši (nosaukums nav obligāts, arī
     * way/relation). Secība saglabāta tāda pati kā agrākajos soļos, lai žurnāli
     * paliktu salīdzināmi.
     */
    'poi' => [
        'bar'         => ['csv' => 'bar.csv',
                          'selectors' => [['amenity', 'bar'], ['amenity', 'pub'], ['amenity', 'nightclub']]],
        'cafe'        => ['csv' => 'cofe.csv',
                          'selectors' => [['amenity', 'cafe']]],
        'restaurant'  => ['csv' => 'food.csv',
                          'selectors' => [['amenity', 'restaurant'], ['amenity', 'fast_food'], ['amenity', 'food_court']]],
        'hairdresser' => ['csv' => 'frizieri.csv',
                          'selectors' => [['shop', 'hairdresser']]],

        'bakery'      => ['selectors' => [['shop', 'bakery'], ['shop', 'pastry'], ['shop', 'confectionery']]],
        'pharmacy'    => ['selectors' => [['amenity', 'pharmacy']]],
        'beauty'      => ['selectors' => [['shop', 'beauty'], ['shop', 'massage']]],
        'minimarket'  => ['selectors' => [['shop', 'convenience'], ['shop', 'supermarket']]],
        'dentist'     => ['selectors' => [['amenity', 'dentist']]],
        'fastfood'    => ['selectors' => [['amenity', 'fast_food']]],
        'fitness'     => ['selectors' => [['leisure', 'fitness_centre']]],
    ],
];
