<?php
// registrs/build/report_tracker.php — gada-atskaites gada izsekošana + AI keša invalidācija + sitemap.
// Katram aktīvam uzņēmumam glabā pēdējo gada-pārskata gadu un tā maiņas datumu.
// Pie jauna gada: changed_date = šodien, un AI atbildes (finanšu dati mainījušies) tiek dzēstas.
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Atjaunina report_years.sqlite pēc jaunajiem datiem.
 * @return array ['changed'=>[regcode...], 'dates'=>[regcode=>YYYY-MM-DD pirmoreiz redzēts]]
 *   'dates' vienīgais patērētājs ir generate_sitemaps — kopš 2026-08-05 tas nes
 *   first_seen (kad uzņēmums pirmoreiz parādījās datubāzē), nevis changed_date;
 *   changed_date paliek AI keša invalidācijai ('changed').
 */
function update_report_years(PDO $ur, string $tracker_db, ?callable $log = null): array {
    $today = date('Y-m-d');

    // 1. Aktīvie uzņēmumi
    $active = [];
    $st = $ur->query("SELECT regcode, closed, terminated FROM register");
    while (($r = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
        $closed = (string)($r['closed'] ?? '');
        $term = (string)($r['terminated'] ?? '');
        if (in_array($closed, ['L', 'R'], true)) continue;
        if (!($term === '' || $term === '0000-00-00')) continue;
        $rc = (string)($r['regcode'] ?? '');
        if ($rc !== '' && ctype_digit($rc) && strlen($rc) === 11) $active[$rc] = 0;
    }

    // 2. Jaunākais gada-pārskata gads katram
    $st = $ur->query("SELECT legal_entity_registration_number reg, MAX(CAST(year AS INTEGER)) y
                      FROM financial_statements GROUP BY legal_entity_registration_number");
    while (($r = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
        $rc = (string)($r['reg'] ?? '');
        if (isset($active[$rc])) $active[$rc] = (int)$r['y'];
    }

    // 3. Iepriekšējais stāvoklis
    $t = new PDO('sqlite:' . $tracker_db);
    $t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $t->exec('PRAGMA journal_mode = OFF');
    $t->exec("CREATE TABLE IF NOT EXISTS report_years (regcode TEXT PRIMARY KEY, report_year INTEGER, changed_date TEXT, first_seen TEXT)");
    // Migrācija vecai tabulai bez first_seen: aizpilda ar changed_date — labākais
    // pieejamais tuvinājums; sitemap gada enkurs (sk. generate_sitemaps) to tāpat pārsedz.
    $cols = array_column($t->query("PRAGMA table_info(report_years)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('first_seen', $cols, true)) {
        $t->exec("ALTER TABLE report_years ADD COLUMN first_seen TEXT");
        $t->exec("UPDATE report_years SET first_seen = changed_date");
    }
    $prev = [];
    foreach ($t->query("SELECT regcode, report_year, changed_date, first_seen FROM report_years") as $row) {
        $prev[(string)$row['regcode']] = [(int)$row['report_year'], (string)$row['changed_date'], (string)($row['first_seen'] ?? '')];
    }

    // 4. Salīdzina, atjaunina
    $changed = [];
    $dates = [];
    $t->beginTransaction();
    $ins = $t->prepare("INSERT OR REPLACE INTO report_years (regcode, report_year, changed_date, first_seen) VALUES (?,?,?,?)");
    $batch = 0;
    foreach ($active as $rc => $year) {
        $rc = (string)$rc; // PHP pārvērš 11-ciparu masīva atslēgas par int — atgriežam string
        if (isset($prev[$rc])) {
            [$py, $pd, $pf] = $prev[$rc];
            if ($year > $py) { $date = $today; $changed[] = $rc; }  // jauns gada pārskats
            else { $date = ($pd !== '' ? $pd : $today); }           // nemainīts
            $first = ($pf !== '' ? $pf : $date);
        } else {
            $date = $today;                                          // pirmoreiz redzēts
            $first = $today;
            // Pirmajā palaišanā (tabula tukša) NEuzskatām visus par "mainītiem" AI dzēšanai
            if (!empty($prev)) $changed[] = $rc;
        }
        $ins->execute([$rc, $year, $date, $first]);
        $dates[$rc] = $first;
        if (++$batch % 20000 === 0) { $t->commit(); $t->beginTransaction(); }
    }
    $t->commit();
    $t = null;

    if ($log) $log("      + Gada-atskaites izsekošana: " . count($active) . " aktīvi, " . count($changed) . " ar jaunu gadu");
    return ['changed' => $changed, 'dates' => $dates];
}

/**
 * Dzēš norādīto uzņēmumu AI kešus (x/DD/DD/{reg}.json) — finanšu dati mainījušies.
 */
function invalidate_ai_cache(array $regcodes, string $ai_cache_dir, ?callable $log = null): int {
    $n = 0;
    foreach ($regcodes as $rc) {
        $rc = preg_replace('/\D/', '', (string)$rc);
        if (strlen($rc) !== 11) continue;
        $f = $ai_cache_dir . '/x/' . substr($rc, 0, 2) . '/' . substr($rc, 2, 2) . '/' . $rc . '.json';
        if (is_file($f)) { @unlink($f); $n++; }
    }
    if ($log) $log("      + AI kešs invalidēts: $n uzņēmumiem (jauns gada pārskats)");
    return $n;
}

/**
 * Ģenerē sitemap XML failus + indeksu + robots.txt.
 *
 * Uzņēmumu lastmod (2026-08-05, Girta lēmums): FIKSĒTS gada enkurs — 5. augusts,
 * kas reizi gadā ripo uz priekšu (2026-08-05 → 2027-08-05 → ...). Uzņēmuma lapas
 * saturs pēc būtības mainās reizi gadā (jaunā gada atskaite), un izkaisītie
 * per-uzņēmuma changed_date Google nedeva noderīgu signālu. Vienīgais izņēmums:
 * uzņēmums, kas datubāzē ienāk PĒC enkura, dabū savas ielādes dienu (first_seen)
 * — līdz to pārsedz nākamā gada enkurs. $dates = [regcode => first_seen].
 *
 * PIRMAIS fails vienmēr ir sitemap-0.xml ar sākumlapu un sadaļām. Bez tā sitemap
 * saturēja TIKAI ~450k uzņēmumu URL, un Google vietnes "seju" (sitelinks zem
 * galvenā rezultāta) izvēlējās no nejaušām uzņēmumu lapām, nevis sadaļām.
 */
function generate_sitemaps(array $dates, string $sitemap_dir, string $base_domain, ?callable $log = null): void {
    @mkdir($sitemap_dir, 0775, true);
    // Notīra vecos sitemap-*.xml
    foreach (glob($sitemap_dir . '/sitemap-*.xml') ?: [] as $old) @unlink($old);

    ksort($dates, SORT_STRING);
    $regcodes = array_keys($dates);
    $limit = 25000;
    $files = [];
    $today = date('Y-m-d');
    // Gada enkurs: pēdējais 5. augusts (ieskaitot šodienu). ISO datumi salīdzinās kā virknes.
    $anchor = date('Y') . '-08-05';
    if ($today < $anchor) $anchor = (date('Y') - 1) . '-08-05';

    // Sadaļu lapas: URL => cik bieži mainās saturs. Sākumlapa un saraksti mainās
    // ar katru būvi/sinhronizāciju (daily); statiskās — reti.
    $sections = [
        ''                => 'daily',
        'nozare.php'      => 'daily',
        'struktura.php'   => 'weekly',
        'konkursi.php'    => 'daily',
        'iespeja.php'     => 'monthly',
        'pensionars.php'  => 'weekly',
        'horoskops.php'   => 'monthly',
        'lejupielade.php' => 'monthly',
        'dati.php'        => 'monthly',
    ];
    $fh = fopen($sitemap_dir . '/sitemap-0.xml', 'w');
    fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
    fwrite($fh, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
    foreach ($sections as $path => $freq) {
        fwrite($fh, "<url><loc>{$base_domain}/{$path}</loc><lastmod>{$today}</lastmod>"
                  . "<changefreq>{$freq}</changefreq><priority>1.0</priority></url>\n");
    }
    // Reģionālās TOP lapas (top.php, 2026-08-22): /top/ + 42 teritorijas no lib/top_teritorijas.php.
    // Saturs mainās ar katru katalogs.sqlite būvi (nakts), tāpēc daily/weekly; trūkstot bibliotēkai — izlaiž.
    $top_lib = reg_docroot() . '/lib/top_teritorijas.php';
    if (is_file($top_lib)) {
        require_once $top_lib;
        fwrite($fh, "<url><loc>{$base_domain}/top/</loc><lastmod>{$today}</lastmod><changefreq>daily</changefreq><priority>0.9</priority></url>\n");
        foreach (TP_TERITORIJAS as $ter) {
            fwrite($fh, "<url><loc>{$base_domain}/top/{$ter[2]}</loc><lastmod>{$today}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>\n");
        }
    }
    fwrite($fh, '</urlset>' . "\n");
    fclose($fh);
    $files[] = 'sitemap-0.xml';

    // Per-NACE SEO lapas (/nozare/{kods}, nozare_nace.php): visi klasifikatora mezgli,
    // kuros ir vismaz viens aktīvs uzņēmums. Ja nozaru katalogs vēl nav uzbūvēts, izlaiž.
    $katalogs_db = reg_docroot() . '/nozare/katalogs.sqlite';
    if (is_file($katalogs_db)) {
        try {
            $kpdo = new PDO('sqlite:' . $katalogs_db);
            $kpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $np_counts = [];
            $q = $kpdo->query("SELECT nace_code_np np, COUNT(*) c FROM companies
                WHERE (employees > 0 OR turnover > 0 OR profit != 0) AND nace_code_np != 'UNDEFINED'
                GROUP BY nace_code_np");
            foreach ($q as $r) $np_counts[(string)$r['np']] = (int)$r['c'];

            $nodes_q = $kpdo->query("SELECT code, parent_code, level FROM nace WHERE code != 'UNDEFINED' ORDER BY code");
            $nace_nodes = $nodes_q->fetchAll(PDO::FETCH_ASSOC);
            // Sekcijas burtam pašam nav ciparu prefiksa — skaita caur bērnu nodaļām.
            $section_divs = [];
            foreach ($nace_nodes as $n) {
                if ((int)$n['level'] === 2 && $n['parent_code'] !== null && $n['parent_code'] !== '') {
                    $section_divs[$n['parent_code']][] = str_replace('.', '', $n['code']);
                }
            }
            $node_count = function (array $n) use ($np_counts, $section_divs): int {
                $prefixes = (int)$n['level'] === 1
                    ? ($section_divs[$n['code']] ?? [])
                    : [str_replace('.', '', $n['code'])];
                $sum = 0;
                // PHP ciparu-virkņu masīva atslēgas automātiski kļūst par int — jāatliek uz string.
                foreach ($np_counts as $np => $c) {
                    foreach ($prefixes as $p) {
                        if (str_starts_with((string)$np, (string)$p)) { $sum += $c; break; }
                    }
                }
                return $sum;
            };
            $klastmod = date('Y-m-d', @filemtime($katalogs_db) ?: time());
            $fh = fopen($sitemap_dir . '/sitemap-nozares.xml', 'w');
            fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
            fwrite($fh, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
            $nz_urls = 0;
            foreach ($nace_nodes as $n) {
                if ($node_count($n) <= 0) continue;
                fwrite($fh, "<url><loc>{$base_domain}/nozare/{$n['code']}</loc><lastmod>{$klastmod}</lastmod><changefreq>daily</changefreq></url>\n");
                $nz_urls++;
            }
            fwrite($fh, '</urlset>' . "\n");
            fclose($fh);
            $files[] = 'sitemap-nozares.xml';
            if ($log) $log("   sitemap-nozares.xml: {$nz_urls} NACE lapas");
        } catch (Throwable $e) {
            if ($log) $log("   sitemap-nozares.xml IZLAISTS: " . $e->getMessage());
        }
    }

    // Divlīmeņu dalījums (2026-08-07): "fin" = uzņēmumi ar >=1 ieņēmumu pārskatu VAI
    // VID gada datiem (datu bagātās lapas); "pamati" = tikai rekvizītu lapas (biedrības,
    // guļošas SIA, ZS...). Google pret abiem izturas vienādi, bet Search Console rāda
    // indeksāciju pa failiem — līmeņi ļauj to monitorēt atsevišķi un netur "plānās"
    // lapas vienā failā ar vērtīgajām. Ja UR db nav sasniedzama — viss vienā līmenī.
    $fin_set = null;
    try {
        $urdb = new PDO('sqlite:' . reg_ur_db_path());
        $urdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $fin_set = [];
        $q = $urdb->query("SELECT DISTINCT f.legal_entity_registration_number rc
                           FROM financial_statements f JOIN income_statements i ON i.statement_id = f.id");
        foreach ($q as $r) $fin_set[(string)$r['rc']] = true;
        $q = $urdb->query("SELECT DISTINCT Registracijas_kods rc FROM pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata");
        foreach ($q as $r) $fin_set[(string)$r['rc']] = true;
        $urdb = null;
    } catch (Throwable $e) {
        if ($log) $log("   sitemap līmeņu dalījums IZLAISTS (visi vienā): " . $e->getMessage());
        $fin_set = null;
    }

    $write_chunks = function (array $codes, string $prefix) use (&$files, $sitemap_dir, $base_domain, $dates, $anchor, $limit): void {
        foreach (array_chunk($codes, $limit) as $i => $chunk) {
            $fname = $prefix . ($i + 1) . '.xml';
            $fh = fopen($sitemap_dir . '/' . $fname, 'w');
            fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
            fwrite($fh, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
            foreach ($chunk as $rc) {
                // Enkurs visiem; tikai pēc enkura ienākušajiem — to ielādes diena.
                $lastmod = max($anchor, (string)$dates[$rc]);
                fwrite($fh, "<url><loc>{$base_domain}/{$rc}</loc><lastmod>{$lastmod}</lastmod></url>\n");
            }
            fwrite($fh, '</urlset>' . "\n");
            fclose($fh);
            $files[] = $fname;
        }
    };

    if ($fin_set === null) {
        $write_chunks($regcodes, 'sitemap-');
    } else {
        $fin = [];
        $pamati = [];
        foreach ($regcodes as $rc) {
            if (isset($fin_set[$rc])) $fin[] = $rc; else $pamati[] = $rc;
        }
        $write_chunks($fin, 'sitemap-fin-');
        $write_chunks($pamati, 'sitemap-pamati-');
        if ($log) $log("   sitemap līmeņi: fin=" . count($fin) . ", pamati=" . count($pamati));
    }

    // Indekss
    $idx = fopen($sitemap_dir . '/sitemap.xml', 'w');
    fwrite($idx, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
    fwrite($idx, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
    foreach ($files as $f) {
        fwrite($idx, "<sitemap><loc>{$base_domain}/sitemap/{$f}</loc><lastmod>{$today}</lastmod></sitemap>\n");
    }
    fwrite($idx, '</sitemapindex>' . "\n");
    fclose($idx);

    // robots.txt (docroot saknē)
    $robots = dirname($sitemap_dir) . '/robots.txt';
    @file_put_contents($robots, "User-agent: *\nAllow: /\nSitemap: {$base_domain}/sitemap/sitemap.xml\n");

    if ($log) $log("      + Sitemap: " . count($files) . " faili, " . count($regcodes) . " URL");
}
