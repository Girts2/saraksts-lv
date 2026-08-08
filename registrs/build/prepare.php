<?php
// server/build/prepare.php — ports of kods/py/1_prepare_data.py
// Ceturkšņu vēstures apvienošana + companies.sqlite (FTS) + nace_stats.sqlite.
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/formatters.php'; // py_str_float

/** pandas astype(str) vienai vērtībai (float -> Python str, int/str kā ir). */
function pd_str($v): string {
    if ($v === null) return '';
    if (is_float($v)) return py_str_float($v);
    if (is_bool($v)) return $v ? 'True' : 'False';
    return (string)$v;
}

/** Meklēšanas normalizācija — ports of normalize_for_search (1_prepare_data.py). */
function normalize_for_search($name_str): string {
    if ($name_str === null || $name_str === '' || $name_str === false) return '';
    $text = mb_strtolower((string)$name_str, 'UTF-8');
    $text = str_replace(["\u{a0}", "\u{200b}"], ' ', $text);
    // Pieturzīmju/pēdiņu klase (precīzi kā Python)
    $text = preg_replace('~[„”"\'`()\[\]{}:;,./\\\\|-]~u', ' ', $text);
    $text = trim(preg_replace('/\s+/u', ' ', $text));

    static $prefixes = null;
    if ($prefixes === null) {
        $prefixes = array_flip([
            "ik", "sia", "as", "ps", "ks", "zs", "nodibinājums", "biedrība",
            "individuālais komersants", "sabiedrība ar ierobežotu atbildību",
            "akciju sabiedrība", "pilnsabiedrība", "komandītsabiedrība",
            "zemnieku saimniecība", "kooperatīvā sabiedrība", "filiāle",
            "ārvalsts komersanta filiāle", "individuālais uzņēmums",
            "pašvaldības uzņēmums", "pārstāvniecība", "fonds",
        ]);
    }
    $words = $text === '' ? [] : explode(' ', $text);
    $cleaned = [];
    foreach ($words as $w) {
        if (!isset($prefixes[$w])) $cleaned[] = $w;
    }
    $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $cleaned)));

    $parts = [];
    if ($text !== '') $parts[$text] = 1;
    $no_space = str_replace(' ', '', $text);
    if ($no_space !== '' && $no_space !== $text) $parts[$no_space] = 1;
    $keys = array_keys($parts);
    sort($keys, SORT_STRING);
    return implode(' ', $keys);
}

/**
 * Apvieno ceturkšņu vēsturi ar svaigajiem datiem.
 * Raksta atpakaļ gan history DB, gan ur_data.db (viss kā TEXT — pandas astype(str)).
 */
/**
 * Ielasa ceturkšņu CSV (komatu atdalīts, ar BOM) assoc rindās, kartējot CSV kolonnas
 * uz tabulas kolonnām $cols pēc nosaukuma. Trūkstošās kolonnas -> null.
 */
function read_quarterly_csv(string $file, string $sep, array $cols): array {
    $fh = fopen($file, 'r');
    if ($fh === false) return [];
    $first = fgets($fh);
    if ($first === false) { fclose($fh); return []; }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', rtrim((string)$first, "\r\n")); // UTF-8 BOM
    $header = str_getcsv($first, $sep, '"', '');
    $idx = array_flip($header);
    $ncol = count($header);
    $rows = [];
    while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
        if (count($r) !== $ncol) continue; // on_bad_lines='skip'
        $row = [];
        foreach ($cols as $c) {
            $row[$c] = isset($idx[$c]) ? ($r[$idx[$c]] ?? null) : null;
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

/** "2025. gada 4. ceturksnis" -> "2025-4cet"; null, ja neatpazīst. */
function quarter_tag(?string $q): ?string {
    if ($q !== null && preg_match('/(\d{4})\.\s*gada\s*(\d)\.\s*ceturksnis/u', $q, $m)) {
        return $m[1] . '-' . $m[2] . 'cet';
    }
    return null;
}

/** Kanoniskais faila vārds pēc satura ceturkšņiem: "2025-4cet pdb_...csv". */
function quarter_canonical_name(array $tags): ?string {
    if (empty($tags)) return null;
    $tags = array_values(array_unique($tags));
    sort($tags, SORT_STRING);
    $prefix = count($tags) === 1 ? $tags[0] : ($tags[0] . '_' . $tags[count($tags) - 1]);
    return $prefix . ' pdb_samaksato_nodoklu_kopsummas_cet.csv';
}

/** Atrod CSV failā sastopamos ceturkšņus (skenē kolonnu Taksacijas_gads_ceturksnis). */
function quarter_tags_in_csv(string $file, string $sep): array {
    $fh = fopen($file, 'r');
    if ($fh === false) return [];
    $first = fgets($fh);
    if ($first === false) { fclose($fh); return []; }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', rtrim((string)$first, "\r\n"));
    $header = str_getcsv($first, $sep, '"', '');
    $qi = array_search('Taksacijas_gads_ceturksnis', $header, true);
    if ($qi === false) { fclose($fh); return []; }
    $ncol = count($header);
    $tags = [];
    while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
        if (count($r) !== $ncol) continue;
        $t = quarter_tag((string)($r[$qi] ?? ''));
        if ($t !== null) $tags[$t] = 1;
    }
    fclose($fh);
    return array_keys($tags);
}

/**
 * Sakārto ceturkšņu mapi (ceturksnis/): katram manuāli iemestam *.csv failam pēc
 * SATURA piešķir kanonisko vārdu "YYYY-Ncet pdb_...csv". Tā vairāki data.gov.lv
 * faili (visiem oriģinālais nosaukums ir vienāds) var dzīvot vienā mapē: iemet
 * failu — build to pārsauc pats. Baitu-identisku dublikātu dzēš; atšķirīgu tā
 * paša ceturkšņa failu saglabā ar " v2" piedēkli (merge dublikātus tāpat apvieno).
 */
function normalize_quarter_dir(string $dir, string $sep, ?callable $log = null): void {
    if (!is_dir($dir)) return;
    foreach ((glob($dir . '/*.csv') ?: []) as $f) {
        $base = basename($f);
        // Jau kanonisks vārds — saturu nelasa vēlreiz
        if (preg_match('/^\d{4}-\dcet(_\d{4}-\dcet)?( v\d+)? pdb_samaksato_nodoklu_kopsummas_cet\.csv$/', $base)) continue;
        $canon = quarter_canonical_name(quarter_tags_in_csv($f, $sep));
        if ($canon === null) {
            if ($log) $log("      ! Ceturkšņu mapē fails bez atpazīstamiem ceturkšņiem: $base (netiek pārsaukts)");
            continue;
        }
        if ($base === $canon) continue;
        $target = $dir . '/' . $canon;
        if (is_file($target)) {
            if (@md5_file($f) === @md5_file($target)) {
                @unlink($f);
                if ($log) $log("      - Ceturkšņu dublikāts dzēsts: $base (identisks ar $canon)");
                continue;
            }
            $i = 2;
            do {
                $target = $dir . '/' . str_replace(' pdb_', " v$i pdb_", $canon);
                $i++;
            } while (is_file($target));
        }
        if (@rename($f, $target)) {
            if ($log) $log("      + Ceturkšņu fails pārsaukts: $base -> " . basename($target));
        }
    }
}

/**
 * Arhivē svaigos (current) ceturkšņus mapē kā atsevišķus CSV, ja tāda ceturkšņa
 * faila tur vēl nav. Tā vēsture uzkrājas automātiski ar katru build — arī tad,
 * kad data.gov.lv veco ceturksni vairs nepiedāvā lejupielādei.
 */
function archive_current_quarters(string $dir, array $current, array $cols, string $sep, ?callable $log = null): void {
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return;
    $by_q = [];
    foreach ($current as $r) {
        $tag = quarter_tag((string)($r['Taksacijas_gads_ceturksnis'] ?? ''));
        if ($tag !== null) $by_q[$tag][] = $r;
    }
    foreach ($by_q as $tag => $rows) {
        $target = $dir . '/' . $tag . ' pdb_samaksato_nodoklu_kopsummas_cet.csv';
        if (is_file($target)) continue;
        $fh = @fopen($target, 'w');
        if ($fh === false) continue;
        fputcsv($fh, $cols, $sep, '"', '');
        foreach ($rows as $r) {
            $row = [];
            foreach ($cols as $c) $row[] = pd_str($r[$c] ?? null);
            fputcsv($fh, $row, $sep, '"', '');
        }
        fclose($fh);
        if ($log) $log("      + Ceturksnis arhivēts: " . basename($target) . " (" . count($rows) . " rindas)");
    }
}

function merge_quarterly_history(PDO $ur, string $history_db, ?callable $log = null): void {
    $table = 'pdb_samaksato_nodoklu_kopsummas_cet';
    try {
        // Svaigie dati no ur_data.db
        $current = $ur->query("SELECT * FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if ($log) $log("   ! Ceturkšņu tabula ur_data.db nav: " . $e->getMessage());
        return;
    }
    if (empty($current)) { if ($log) $log("   - Ceturkšņu dati tukši"); return; }

    $cols = array_keys($current[0]);
    $has_quarter = in_array('Taksacijas_gads_ceturksnis', $cols, true);
    $clean_reg = function ($v) {
        $s = pd_str($v);
        return preg_replace('/\.0$/', '', $s);
    };

    // Kombinē: history vispirms, tad current (current uzvar dublikātos, keep='last')
    $combined = []; // key -> assoc row (viss string)
    $order = [];    // saglabā secību (history, tad current)
    $add_rows = function (array $rows) use (&$combined, &$order, $cols, $has_quarter, $clean_reg) {
        foreach ($rows as $r) {
            $row = [];
            foreach ($cols as $c) {
                $val = $r[$c] ?? null;
                if ($c === 'Registracijas_kods') $row[$c] = $clean_reg($val);
                else $row[$c] = pd_str($val);
            }
            if ($has_quarter) {
                $key = $row['Registracijas_kods'] . '|' . $row['Taksacijas_gads_ceturksnis'];
            } else {
                $key = count($order); // bez dedup atslēgas
            }
            if (!isset($combined[$key])) $order[] = $key;
            $combined[$key] = $row;
        }
    };

    // History (ja eksistē)
    if (is_file($history_db)) {
        try {
            $h = new PDO('sqlite:' . $history_db);
            $h->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $hist = $h->query("SELECT * FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
            // Tikai kolonnas, kas ir current (saskaņotība)
            $add_rows($hist);
            $h = null;
        } catch (Throwable $e) { /* history tabulas nav — OK */ }
    }

    // Papildu vēsturiskie ceturkšņi no CSV mapes (ceturksnis/) — kvartāli, kas VAIRS nav
    // lejupielādējami no data.gov.lv. Ielasa VISUS *.csv failus tur; kolonnu nosaukumi =
    // tabulas kolonnas. Merge secība: history -> ceturksnis CSV -> current (svaigais uzvar
    // dublikātos; ceturksnis uzvar pār veco history to pašu kvartālu).
    $quarter_dir = getenv('REG_QUARTER_DIR') ?: (reg_docroot() . '/ceturksnis');
    $sep = csv_sep_for($table);
    // 1) Manuāli iemestos failus pārsauc kanoniskos vārdos (pēc satura ceturkšņiem);
    // 2) svaigā builda ceturkšņus arhivē, lai vēsture uzkrājas automātiski.
    normalize_quarter_dir($quarter_dir, $sep, $log);
    archive_current_quarters($quarter_dir, $current, $cols, $sep, $log);
    if (is_dir($quarter_dir)) {
        $csv_files = glob($quarter_dir . '/*.csv') ?: [];
        sort($csv_files);
        foreach ($csv_files as $cf) {
            $rows = read_quarterly_csv($cf, $sep, $cols);
            if (!empty($rows)) {
                $add_rows($rows);
                if ($log) $log("      + Ceturkšņu CSV: " . basename($cf) . " (" . count($rows) . " rindas)");
            }
        }
    }

    $add_rows($current);

    // Sagatavo rakstīšanai
    $final_rows = [];
    foreach ($order as $k) $final_rows[] = $combined[$k];

    // Raksta abās DB kā TEXT
    $write = function (PDO $db) use ($table, $cols, $final_rows) {
        $db->exec("DROP TABLE IF EXISTS \"$table\"");
        $defs = implode(', ', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '" TEXT', $cols));
        $db->exec("CREATE TABLE \"$table\" ($defs)");
        $cols_sql = implode(',', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $cols));
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $stmt = $db->prepare("INSERT INTO \"$table\" ($cols_sql) VALUES ($ph)");
        $db->beginTransaction();
        $i = 0;
        foreach ($final_rows as $row) {
            $stmt->execute(array_values($row));
            if (++$i % 20000 === 0) { $db->commit(); $db->beginTransaction(); }
        }
        $db->commit();
        // Indekss regcode meklēšanai
        try { $db->exec("CREATE INDEX IF NOT EXISTS idx_{$table}_reg ON \"$table\" (\"Registracijas_kods\")"); } catch (Throwable $e) {}
    };

    // history DB
    try {
        $h = new PDO('sqlite:' . $history_db);
        $h->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $write($h); $h = null;
    } catch (Throwable $e) { if ($log) $log("   ! History DB rakstīšana neizdevās: " . $e->getMessage()); }
    // ur_data.db
    $write($ur);
    if ($log) $log("      + Apvienoti " . count($final_rows) . " ceturkšņu ieraksti");
}

/**
 * Meklēšanas datubāze (companies.sqlite) ar FTS5.
 */
function build_search_database(PDO $ur, string $out_db, ?callable $log = null): void {
    if (is_file($out_db)) @unlink($out_db);
    $c = new PDO('sqlite:' . $out_db);
    $c->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $c->exec('PRAGMA journal_mode = OFF');
    $c->exec('PRAGMA synchronous = OFF');
    $c->exec('CREATE TABLE IF NOT EXISTS companies (regcode TEXT PRIMARY KEY, original_name TEXT NOT NULL, search_helper TEXT NOT NULL)');
    $c->exec('CREATE VIRTUAL TABLE companies_fts USING fts5(search_helper_content, content="companies", content_rowid="rowid", tokenize = "unicode61 remove_diacritics 2")');
    $c->exec('CREATE TRIGGER companies_ai AFTER INSERT ON companies BEGIN INSERT INTO companies_fts (rowid, search_helper_content) VALUES (new.rowid, new.search_helper); END;');
    $c->exec('CREATE TRIGGER companies_ad AFTER DELETE ON companies BEGIN DELETE FROM companies_fts WHERE rowid=old.rowid; END;');
    $c->exec('CREATE TRIGGER companies_au AFTER UPDATE ON companies BEGIN UPDATE companies_fts SET search_helper_content=new.search_helper WHERE rowid=old.rowid; END;');

    $stmt = $ur->query("SELECT regcode, name, type, name_in_quotes, name_before_quotes, closed, terminated FROM register");
    $ins = $c->prepare('INSERT OR IGNORE INTO companies (regcode, original_name, search_helper) VALUES (?, ?, ?)');
    $c->beginTransaction();
    $count = 0; $batch = 0;
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $regcode = trim(pd_str($row['regcode'] ?? ''));
        if (!(ctype_digit($regcode) && strlen($regcode) === 11)) continue;
        $term = trim(pd_str($row['terminated'] ?? ''));
        if ($term !== '' && $term < '2020-01-01' && $term !== '0000-00-00') continue;

        $type_val = trim(pd_str($row['type'] ?? ''));
        $name_in_quotes = trim(pd_str($row['name_in_quotes'] ?? ''));
        $name_before = trim(pd_str($row['name_before_quotes'] ?? ''));
        $original_name = trim(pd_str($row['name'] ?? ''));

        $display_name = $original_name;
        if ($type_val === 'ZEM') {
            $parts = array_filter([$type_val, $name_in_quotes, $name_before], fn($p) => $p !== '');
            $display_name = implode(', ', $parts);
        } elseif ($type_val !== '' && $name_in_quotes !== '') {
            $display_name = "$type_val, $name_in_quotes";
        } elseif ($name_in_quotes !== '') {
            $display_name = $name_in_quotes;
        }
        if ($display_name === '') continue;

        $base_name = $name_in_quotes !== '' ? $name_in_quotes : ($name_before !== '' ? $name_before : $original_name);
        $normalized = normalize_for_search($base_name);
        if ($normalized === '' && $display_name !== $base_name) {
            $normalized = normalize_for_search($display_name);
        }
        $search_helper = "$regcode $normalized";
        $ins->execute([$regcode, $display_name, $search_helper]);
        $count++;
        if (++$batch % 20000 === 0) { $c->commit(); $c->beginTransaction(); }
    }
    $c->commit();
    $c = null;
    if ($log) $log("      + Indeksēti $count uzņēmumi meklēšanai");
}

/**
 * NACE statistikas DB (nace_stats.sqlite) — ports of build_nace_database.
 */
function build_nace_database(PDO $ur, string $out_db, ?callable $log = null): void {
    // 1. Aktīvie uzņēmumi: regcode -> name
    $main = []; // regcode -> [name, nace_raw, employees_vid, turnover, profit, employees_fs]
    $st = $ur->query("SELECT regcode, name, closed, terminated FROM register");
    while (($r = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
        $closed = (string)($r['closed'] ?? '');
        $term = (string)($r['terminated'] ?? '');
        $active = !in_array($closed, ['L', 'R'], true) && ($term === '' || $term === '0000-00-00');
        if (!$active) continue;
        $rc = (string)($r['regcode'] ?? '');
        if ($rc === '') continue;
        $main[$rc] = ['name' => (string)($r['name'] ?? ''), 'nace_raw' => null,
            'employees_vid' => 0, 'turnover' => 0.0, 'profit' => 0.0, 'employees_fs' => 0];
    }

    // 2. VID: jaunākā gada (pēc kolonnas neatkarīgi) derīgais NACE un darbinieki
    $invalid_nace = ['?' => 1, '' => 1, '0' => 1, '00' => 1, '0000' => 1, 'nan' => 1, 'None' => 1];
    try {
        $q = "SELECT Registracijas_kods AS reg, Pamatdarbibas_NACE_kods AS nace,
                     Videjais_nodarbinato_personu_skaits_cilv AS emp,
                     CAST(Taksacijas_gads AS REAL) AS y
              FROM pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata
              ORDER BY Registracijas_kods, y DESC";
        $seen_nace = []; $seen_emp = [];
        $st = $ur->query($q);
        while (($r = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rc = (string)($r['reg'] ?? '');
            if (!isset($main[$rc])) continue;
            // NACE: pirmais derīgais (pandas 'first' izlaiž NA)
            if (!isset($seen_nace[$rc])) {
                $nace = trim((string)($r['nace'] ?? ''));
                if ($nace !== '' && !isset($invalid_nace[$nace])) {
                    $main[$rc]['nace_raw'] = $nace;
                    $seen_nace[$rc] = true;
                }
            }
            // Darbinieki: pirmais ne-null
            if (!isset($seen_emp[$rc])) {
                $emp = $r['emp'];
                if ($emp !== null && trim((string)$emp) !== '') {
                    $main[$rc]['employees_vid'] = (int)(float)$emp;
                    $seen_emp[$rc] = true;
                }
            }
        }
    } catch (Throwable $e) {
        if ($log) $log("      - VID dati izlaisti: " . $e->getMessage());
    }

    // 3. FS: jaunākā gada apgrozījums/peļņa/darbinieki
    try {
        $q = "SELECT f.legal_entity_registration_number AS reg, f.rounded_to_nearest AS rnd,
                     f.employees AS emp, i.net_turnover AS turn, i.net_income AS inc,
                     CAST(f.year AS REAL) AS y
              FROM financial_statements f
              LEFT JOIN income_statements i ON i.statement_id = f.id
              ORDER BY f.legal_entity_registration_number, y DESC";
        $seen_fs = [];
        $st = $ur->query($q);
        while (($r = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rc = (string)($r['reg'] ?? '');
            if (!isset($main[$rc]) || isset($seen_fs[$rc])) continue;
            $seen_fs[$rc] = true; // tikai jaunākais gads
            $rnd = strtoupper((string)($r['rnd'] ?? ''));
            $mult = str_contains($rnd, 'THOUS') ? 1000 : (str_contains($rnd, 'MILL') ? 1000000 : 1);
            $turn = (float)($r['turn'] ?? 0);
            $inc = (float)($r['inc'] ?? 0);
            $main[$rc]['turnover'] = $turn * $mult;
            $main[$rc]['profit'] = $inc * $mult;
            $main[$rc]['employees_fs'] = (int)(float)($r['emp'] ?? 0);
        }
    } catch (Throwable $e) {
        if ($log) $log("      - FS dati izlaisti: " . $e->getMessage());
    }

    // 4. Grupē pēc nace_code
    $groups = []; // nace_code -> list of [name, regcode, profit, turnover, employees_final]
    foreach ($main as $rc => $m) {
        $nace_raw = $m['nace_raw'];
        if ($nace_raw === null) $nace_raw = '0000';
        if ($nace_raw === '?') $nace_raw = '0000';
        $nace_code = trim(str_replace('.', '', (string)$nace_raw));
        if (in_array($nace_code, ['nan', '', 'None', '0'], true)) $nace_code = '0000';

        $emp_final = max((int)$m['employees_vid'], (int)$m['employees_fs']);
        $profit = (float)$m['profit'];
        $turnover = (float)$m['turnover'];
        if (!($profit != 0.0 || $turnover != 0.0 || $emp_final > 0)) continue; // group_with_data filtrs vēlāk, bet efektīvāk šeit

        // (string)$rc — PHP pārvērš skaitliskās $main atslēgas par int; regcode jāpaliek string.
        $groups[$nace_code][] = [$m['name'], (string)$rc, $profit, $turnover, $emp_final];
    }

    // 5. Raksta DB
    if (is_file($out_db)) @unlink($out_db);
    $c = new PDO('sqlite:' . $out_db);
    $c->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $c->exec("CREATE TABLE IF NOT EXISTS nace_stats (code TEXT PRIMARY KEY, json_data TEXT)");
    $ins = $c->prepare("INSERT INTO nace_stats (code, json_data) VALUES (?, ?)");
    $c->beginTransaction();
    $base_meta = ['generated' => date('Y-m-d'), 'unit' => 'EUR', 'source' => 'Saraksts.lv'];
    $count = 0;
    ksort($groups, SORT_STRING);
    foreach ($groups as $nace_code => $companies) {
        // PHP pārvērš skaitliskās masīva atslēgas par int — atgriežam uz string.
        $nace_code = (string)$nace_code;
        if (strlen($nace_code) < 2 && $nace_code !== '0000') continue;
        if (empty($companies)) continue;
        // sort by profit desc (stabili)
        usort($companies, fn($a, $b) => $b[2] <=> $a[2]);
        $out = ['meta' => $base_meta, 'companies' => $companies, 'nace_code' => $nace_code];
        $ins->execute([$nace_code, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $count++;
    }
    $c->commit();
    $c = null;
    if ($log) $log("      + NACE DB gatava: $count nozares");
}
