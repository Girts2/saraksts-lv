<?php
// server/build/convert.php — CSV -> ur_data.db (ports of convert_csvs_to_sqlite).
// Divu gājienu straumēšana: 1) kolonnu tipu noteikšana (pandas-veidā), 2) ievietošana.
// SQLite kolonnu afinitāte koercē vērtības (piem. "0.00" REAL kolonnā -> 0.0).
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** pandas noklusējuma NA vērtības (svarīgākās). */
function is_na_value(string $v): bool {
    if ($v === '') return true;
    static $na = ['#N/A' => 1, '#N/A N/A' => 1, '#NA' => 1, 'N/A' => 1, 'NA' => 1,
        'NULL' => 1, 'NaN' => 1, 'None' => 1, 'n/a' => 1, 'nan' => 1, 'null' => 1,
        '<NA>' => 1, '-NaN' => 1, '-nan' => 1];
    return isset($na[$v]);
}

function is_int_str(string $v): bool {
    return preg_match('/^[+-]?\d+$/', $v) === 1;
}

function is_num_str(string $v): bool {
    // pandas pieņem int, float, zinātnisko. is_numeric der; izslēdzam hex (PHP to nepieņem).
    return is_numeric($v);
}

/**
 * 1. gājiens: nosaka katras kolonnas afinitāti.
 * @return array [colname => 'INTEGER'|'REAL'|'TEXT']
 */
function infer_column_affinities(string $file, string $sep, array $header): array {
    $ncol = count($header);
    // Statuss katrai kolonnai
    $all_numeric = array_fill(0, $ncol, true);
    $all_integer = array_fill(0, $ncol, true);
    $has_na = array_fill(0, $ncol, false);
    $seen_any = array_fill(0, $ncol, false);

    $always = array_flip(ALWAYS_STRING_COLS);

    $fh = fopen($file, 'r');
    if ($fh === false) throw new RuntimeException("Nevar atvērt: $file");
    fgetcsv($fh, 0, $sep, '"', ''); // izlaižam galveni
    $n = 0;
    while (($row = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
        if ((++$n % 500000) === 0) build_abort_if_stopped();
        if (count($row) !== $ncol) continue; // on_bad_lines='skip'
        for ($i = 0; $i < $ncol; $i++) {
            if (isset($always[$header[$i]])) continue; // teksts fiksēts
            $v = trim((string)$row[$i]);
            if (is_na_value($v)) { $has_na[$i] = true; continue; }
            $seen_any[$i] = true;
            if (!$all_numeric[$i]) continue;
            if (!is_num_str($v)) { $all_numeric[$i] = false; $all_integer[$i] = false; continue; }
            if (!is_int_str($v)) { $all_integer[$i] = false; }
        }
    }
    fclose($fh);

    $aff = [];
    foreach ($header as $i => $col) {
        if (isset($always[$col])) { $aff[$col] = 'TEXT'; continue; }
        if (!$seen_any[$i]) { $aff[$col] = 'REAL'; continue; } // viss NA -> float64 (pandas)
        if ($all_numeric[$i] && $all_integer[$i] && !$has_na[$i]) { $aff[$col] = 'INTEGER'; }
        elseif ($all_numeric[$i]) { $aff[$col] = 'REAL'; }
        else { $aff[$col] = 'TEXT'; }
    }
    return $aff;
}

/**
 * 2. gājiens: ievieto datus. Atgriež ievietoto rindu skaitu.
 */
function insert_rows(PDO $pdo, string $file, string $sep, array $header, array $aff, string $table, ?callable $log = null): int {
    $ncol = count($header);
    $always = array_flip(ALWAYS_STRING_COLS);
    $is_pvn = ($table === 'pdb_pvnmaksataji_odata');
    $numurs_idx = $is_pvn ? array_search('Numurs', $header, true) : false;

    $cols_sql = implode(',', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $header));
    $ph = implode(',', array_fill(0, $ncol, '?'));
    $stmt = $pdo->prepare("INSERT INTO \"$table\" ($cols_sql) VALUES ($ph)");

    $fh = fopen($file, 'r');
    fgetcsv($fh, 0, $sep, '"', ''); // galvene
    $pdo->beginTransaction();
    $count = 0; $batch = 0;
    while (($row = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
        if (count($row) !== $ncol) continue;
        $bind = [];
        for ($i = 0; $i < $ncol; $i++) {
            $col = $header[$i];
            $raw = (string)$row[$i]; // pandas NEnogriež atstarpes (izņemot skaitļu parsēšanā un Numurs)
            if (isset($always[$col])) {
                if ($is_pvn && $i === $numurs_idx) {
                    // pandas: .str.replace('LV','').str.strip()
                    $bind[] = trim(str_replace('LV', '', $raw));
                } else {
                    // always_string (dtype=str + fillna('')): jēlvērtība, NA -> ''
                    $bind[] = is_na_value($raw) ? '' : $raw;
                }
            } elseif ($aff[$col] !== 'TEXT') {
                // skaitliska kolonna: pandas strip-parse; nogriežam un ļaujam afinitātei koercēt
                $t = trim($raw);
                $bind[] = ($t === '' || is_na_value($t)) ? null : $t;
            } else {
                // object/TEXT kolonna: jēlvērtība, NA -> NULL
                $bind[] = is_na_value($raw) ? null : $raw;
            }
        }
        $stmt->execute($bind);
        $count++; $batch++;
        if ($batch >= 20000) {
            $pdo->commit(); $pdo->beginTransaction(); $batch = 0;
            build_abort_if_stopped();
            // Progresa sirdspuksti lielajām tabulām (redzams žurnālā, ka process dzīvo)
            if ($log && ($count % 200000) === 0) {
                $log("      … $table: " . number_format((float)$count, 0, '.', ' ') . " rindas ievietotas …");
            }
        }
    }
    $pdo->commit();
    fclose($fh);
    return $count;
}

/**
 * Konvertē vienu CSV -> tabula DB.
 */
function convert_one_csv(PDO $pdo, string $file, string $table, ?callable $log = null): int {
    $t0 = microtime(true);
    $sep = csv_sep_for($table);
    if ($log) $log("   -> Konvertē: $table (" . number_format((float)filesize($file), 0, '.', ' ') . " b) ...");

    // Galveni lasām atsevišķi un noņemam BOM PIRMS parsēšanas — citādi BOM neļauj
    // fgetcsv atpazīt citātu pirmajā laukā (kolonna paliktu "Registracijas_kods" ar pēdiņām).
    $fh = fopen($file, 'r');
    if ($fh === false) throw new RuntimeException("Nevar atvērt: $file");
    $first_line = fgets($fh);
    fclose($fh);
    if ($first_line === false) return 0;
    $first_line = preg_replace('/^\xEF\xBB\xBF/', '', $first_line); // UTF-8 BOM (jēlbaiti)
    $first_line = rtrim($first_line, "\r\n");
    $header = str_getcsv($first_line, $sep, '"', '');
    if (empty($header) || count($header) < 1) return 0;

    $aff = infer_column_affinities($file, $sep, $header);

    // Izveido tabulu
    $pdo->exec("DROP TABLE IF EXISTS \"$table\"");
    $col_defs = [];
    foreach ($header as $col) {
        $col_defs[] = '"' . str_replace('"', '""', $col) . '" ' . $aff[$col];
    }
    $pdo->exec("CREATE TABLE \"$table\" (" . implode(', ', $col_defs) . ")");

    $n = insert_rows($pdo, $file, $sep, $header, $aff, $table, $log);

    // Indeksi
    foreach (INDEX_COLUMNS as $idx_col) {
        if (in_array($idx_col, $header, true)) {
            $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $idx_col);
            try {
                $pdo->exec("CREATE INDEX IF NOT EXISTS \"idx_{$table}_{$safe}\" ON \"$table\" (\"$idx_col\")");
            } catch (Throwable $e) {}
        }
    }
    if ($log) $log("      + $table: $n rindas (" . round(microtime(true) - $t0, 1) . "s)");
    return $n;
}

/**
 * Vienību karoga (rounded_to_nearest) sanitātes labošana.
 * UR avota datos daži pārskati ir ar kļūdainu karogu: vērtības tūkstošos, bet
 * atzīme ONES (lapa rādītu ~1000× par mazu) vai otrādi (~1000× par lielu).
 * Īstai vienību maiņai starp gadiem neapstrādātajām vērtībām jālec ~1000× —
 * ja karogs mainās, bet skaitļu magnitūda nemainās, karogs ir kļūdains.
 * Labo tikai pie stipriem pierādījumiem; katru labojumu izraksta žurnālā.
 * Zināmie avota gadījumi: 40003000642 (2025 UGP ONES→THOUSANDS),
 * 40003129564 + 40103053167 (2018 THOUSANDS→ONES). Atgriež laboto skaitu.
 */
function fix_rounding_flags(PDO $pdo, ?callable $log = null): int {
    // Priekšfiltrs: tikai uzņēmuma+veida sērijas, kurās sastopami ABI karogi.
    $series = $pdo->query("
        SELECT legal_entity_registration_number AS reg
        FROM financial_statements
        WHERE rounded_to_nearest IN ('ONES','THOUSANDS')
        GROUP BY legal_entity_registration_number, source_type
        HAVING COUNT(DISTINCT rounded_to_nearest) > 1
    ")->fetchAll(PDO::FETCH_COLUMN);
    if (!$series) { if ($log) $log('   ✅ Vienību karogu pārbaude: aizdomīgu sēriju nav.'); return 0; }

    $sel = $pdo->prepare("
        SELECT fs.id, fs.year, COALESCE(fs.source_type, '') AS source_type,
               fs.rounded_to_nearest AS flag,
               COALESCE(fs.employees, 0) AS emp, i.net_turnover AS raw
        FROM financial_statements fs
        JOIN income_statements i ON i.statement_id = fs.id
        WHERE fs.legal_entity_registration_number = ?
          AND fs.rounded_to_nearest IN ('ONES','THOUSANDS')
          AND i.net_turnover > 0
    ");
    $upd = $pdo->prepare("UPDATE financial_statements SET rounded_to_nearest = ? WHERE id = ?");

    $fixed = 0;
    foreach (array_unique($series) as $reg) {
        $sel->execute([$reg]);
        $byType = [];
        foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $byType[$r['source_type']][(int)$r['year']] = $r;
        }
        foreach ($byType as $st => $sery) {
            foreach ($sery as $y => $s) {
                $raw = (float)$s['raw'];
                // Kaimiņi (year±1) tajā pašā pārskatu veidā: karogam jāatšķiras no
                // VISIEM un magnitūdai jāsakrīt (attiecība 0,05..20, ne ~1000).
                $nb = [];
                foreach ([$y - 1, $y + 1] as $ny) if (isset($sery[$ny])) $nb[] = $sery[$ny];
                if (!$nb) continue;
                $suspect = true;
                foreach ($nb as $n) {
                    $ratio = (float)$n['raw'] / $raw;
                    if ($n['flag'] === $s['flag'] || $ratio < 0.05 || $ratio > 20) { $suspect = false; break; }
                }
                if (!$suspect) continue;

                if ($s['flag'] === 'ONES') {
                    // ONES→THOUSANDS: kaimiņiem jābūt lieliem (≥1 milj. EUR) un vajag
                    // vēl vismaz vienu pierādījumu: abpusēju sviestmaizi, tā paša gada
                    // dvīni (cits veids, THOUSANDS, līdzīga magnitūda) vai darbinieku
                    // absurdu (≥20 cilvēki pie <1000 EUR apgrozījuma uz darbinieku).
                    $bigNb = true;
                    foreach ($nb as $n) if ((float)$n['raw'] < 1000) { $bigNb = false; break; }
                    if (!$bigNb) continue;
                    $twin = false;
                    foreach ($byType as $st2 => $sery2) {
                        if ($st2 === $st || !isset($sery2[$y])) continue;
                        $tr = (float)$sery2[$y]['raw'] / $raw;
                        if ($sery2[$y]['flag'] === 'THOUSANDS' && $tr >= 0.5 && $tr <= 2) { $twin = true; break; }
                    }
                    if (count($nb) === 2 || $twin || (float)$s['emp'] >= 20) {
                        $upd->execute(['THOUSANDS', $s['id']]);
                        $fixed++;
                        if ($log) $log("      ~ vienību karogs labots: $reg $y $st ONES→THOUSANDS (apgroz. raw {$s['raw']})");
                    }
                } else {
                    // THOUSANDS→ONES: nelabotu rādītu ≥5 mljrd EUR — Latvijā neeksistē.
                    if ($raw >= 5000000) {
                        $upd->execute(['ONES', $s['id']]);
                        $fixed++;
                        if ($log) $log("      ~ vienību karogs labots: $reg $y $st THOUSANDS→ONES (apgroz. raw {$s['raw']})");
                    }
                }
            }
        }
    }
    if ($log) $log("   ✅ Vienību karogu pārbaude: laboti $fixed pārskati.");
    return $fixed;
}

/**
 * Konvertē visus CSV mapē -> ur_data.db ceļā.
 */
function convert_all_csvs(string $csv_dir, string $db_path, ?callable $log = null): void {
    if (is_file($db_path)) @unlink($db_path);
    @mkdir(dirname($db_path), 0775, true);

    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = OFF');
    $pdo->exec('PRAGMA synchronous = OFF');

    $files = glob($csv_dir . '/*.csv');
    sort($files);
    foreach ($files as $file) {
        build_abort_if_stopped();
        $table = basename($file, '.csv');
        try {
            convert_one_csv($pdo, $file, $table, $log);
        } catch (BuildStopped $e) {
            throw $e; // STOP nav tabulas kļūda — pārtrauc visu būvi
        } catch (Throwable $e) {
            if ($log) $log("      ! Kļūda konvertējot $table: " . $e->getMessage());
        }
    }
    build_abort_if_stopped();
    try {
        fix_rounding_flags($pdo, $log);
    } catch (Throwable $e) {
        if ($log) $log("      ! Vienību karogu pārbaudes kļūda: " . $e->getMessage());
    }

    $pdo = null;
    if ($log) $log("   ✅ ur_data.db sagatavota: $db_path");
}
