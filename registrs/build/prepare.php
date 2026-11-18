<?php
// server/build/prepare.php — ports of kods/py/1_prepare_data.py
// Ceturkšņu vēstures apvienošana + companies.sqlite (FTS) + nace_stats.sqlite.
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/formatters.php'; // py_str_float
require_once __DIR__ . '/../lib/parskata_izvele.php'; // gada pārskata izvēles likums

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
 * Ielasa ceturkšņu CSV (komatu atdalīts, ar BOM) pa vienai rindai, kartējot CSV kolonnas
 * uz tabulas kolonnām $cols pēc nosaukuma. Trūkstošās kolonnas -> null; rindas ar citu
 * lauku skaitu izlaiž (on_bad_lines='skip'). Ģenerators — atmiņā ir tikai viena rinda.
 */
function iter_quarterly_csv(string $file, string $sep, array $cols): Generator {
    $fh = fopen($file, 'r');
    if ($fh === false) return;
    try {
        $first = fgets($fh);
        if ($first === false) return;
        $first = preg_replace('/^\xEF\xBB\xBF/', '', rtrim((string)$first, "\r\n")); // UTF-8 BOM
        $header = str_getcsv($first, $sep, '"', '');
        $idx = array_flip($header);
        $ncol = count($header);
        while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
            if (count($r) !== $ncol) continue; // on_bad_lines='skip'
            $row = [];
            foreach ($cols as $c) {
                $row[$c] = isset($idx[$c]) ? ($r[$idx[$c]] ?? null) : null;
            }
            yield $row;
        }
    } finally {
        fclose($fh);
    }
}

/** Tas pats, kas iter_quarterly_csv, bet visas rindas masīvā (maziem failiem). */
function read_quarterly_csv(string $file, string $sep, array $cols): array {
    return iterator_to_array(iter_quarterly_csv($file, $sep, $cols), false);
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
 * Arhivē svaigos (current) ceturkšņus mapē kā atsevišķus CSV (viens fails katram ceturksnim), lai
 * vēsture uzkrātos arī tad, kad data.gov.lv veco ceturksni vairs nepiedāvā lejupielādei. Mape ir
 * VIENĪGĀ ilgtermiņa kopija — tāpēc esošu failu aizstāj tikai tad, ja par to nav šaubu.
 *
 * - Ceturkšņa faila vēl nav → jauns fails ar svaigajām rindām.
 * - Fails jau ir → to ATJAUNINA uz jaunāko VID versiju (2026-11-10): kopš 2026-11-04 VID vienā failā
 *   publicē visus gada ceturkšņus un pārskata iepriekšējos (Q1–Q3 atšķīrās 557–788 rindas). Ja arhīvā
 *   paliktu pirmā versija, tad, ceturksnim izkrītot no VID faila, apvienošanā (history → CSV → current)
 *   vecā arhīva vērtība pārrakstītu labojumu. Jaunais fails = svaigās rindas + arhīva rindas, kuru
 *   atslēgas (kā apvienošanā: Registracijas_kods bez ".0" | ceturksnis) svaigajos NAV — arhīvs nekad
 *   nezaudē rindas; apvienošanas vērtības šodien nemainās (svaigie tik un tā uzvar).
 * - Esošo failu NEaiztiek (paliek, kā ir; žurnālā brīdinājums), ja: to nevar atvērt vai nolasīt līdz
 *   galam; tā kolonnu kopa atšķiras no tabulas; kādai rindai ir cits lauku skaits; kāda rinda ir ar
 *   citu/neatpazītu ceturksni; fails ir tikai lasāms (apzināti iesaldēts). (Recenzija 2026-11-10:
 *   pretējā gadījumā šādas rindas/kolonnas klusi pazustu.)
 * - Katru rindu vispirms noformē atmiņā un pārbauda, ka fwrite ierakstīja visus baitus; pēc
 *   aizvēršanas faila izmēram jāsakrīt ar gaidīto — arī īss ieraksts pēdējā rindā (pilns disks) nevar
 *   aizstāt arhīvu. Raksta pagaidu vārdā (fsync) un pārsauc tikai pēc veiksmīgām beigām.
 * - Nemainīts saturs (md5) → fails netiek pārrakstīts (idempotence).
 * - Ja tam pašam ceturksnim mapē ir citi faili (" v2", diapazons) — brīdinājums: apvienošanā tie tiek
 *   lasīti PĒC kanoniskā un var pārrakstīt VID labojumus, kad ceturksnis izkritīs no VID faila.
 *
 * $current — jebkas iterējams ar assoc rindām (arī PDOStatement): rindas raksta straumē (~460k rindu);
 * atmiņā tikai esošo ceturkšņu atslēgas (~70 MB).
 */
function archive_current_quarters(string $dir, iterable $current, array $cols, string $sep, ?callable $log = null): void {
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return;
    // Iepriekšējas nokautas būves pagaidu faili (SIGKILL/fatāla kļūda apiet catch, rename nenotika).
    // Būves lock pieder mums, tāpēc citas dzīvas būves failu te nav.
    foreach (glob($dir . '/*.csv.*.tmp') ?: [] as $f) @unlink($f);
    // Tā pati dedup atslēga kā merge_quarterly_history
    $atsl = static fn(array $r): string => preg_replace('/\.0$/', '', pd_str($r['Registracijas_kods'] ?? null))
        . '|' . pd_str($r['Taksacijas_gads_ceturksnis'] ?? null);
    // CSV rinda kā virkne (tā pati fputcsv formatēšana) → var pārbaudīt, ka ierakstīti visi baiti
    $mem = fopen('php://memory', 'w+');
    $rinda = static function (array $vals) use ($mem, $sep): string {
        ftruncate($mem, 0);
        rewind($mem);
        fputcsv($mem, $vals, $sep, '"', '');
        rewind($mem);
        return (string)stream_get_contents($mem);
    };
    $rakstit = static function (array &$f, string $line): void {
        if ($f['kluda']) return;
        if (fwrite($f['fh'], $line) !== strlen($line)) { $f['kluda'] = true; return; }
        $f['baiti'] += strlen($line);
    };

    $faili = []; // tag => ['fh','tmp','target','n','baiti','kluda','ir','atsl'] vai false (nevar atvērt)
    try {
        foreach ($current as $r) {
            $tag = quarter_tag((string)($r['Taksacijas_gads_ceturksnis'] ?? ''));
            if ($tag === null) continue;
            if (!array_key_exists($tag, $faili)) {
                $faili[$tag] = false;
                $target = $dir . '/' . $tag . ' pdb_samaksato_nodoklu_kopsummas_cet.csv';
                $tmp = $target . '.' . getmypid() . '.tmp';
                $fh = @fopen($tmp, 'w');
                if ($fh !== false) {
                    $faili[$tag] = ['fh' => $fh, 'tmp' => $tmp, 'target' => $target, 'n' => 0, 'baiti' => 0,
                                    'kluda' => false, 'ir' => is_file($target), 'atsl' => []];
                    $rakstit($faili[$tag], $rinda($cols));
                }
            }
            if ($faili[$tag] === false) continue;
            $vals = [];
            foreach ($cols as $c) $vals[] = pd_str($r[$c] ?? null);
            $rakstit($faili[$tag], $rinda($vals));
            $faili[$tag]['n']++;
            if ($faili[$tag]['ir']) $faili[$tag]['atsl'][$atsl($r)] = true;
        }
    } catch (Throwable $e) {
        foreach ($faili as $f) {
            if ($f) { fclose($f['fh']); @unlink($f['tmp']); }
        }
        fclose($mem);
        throw $e;
    }

    // Esošā arhīva rindas, kuru svaigajos nav, pieliek pagaidu failam. Atgriež atteikuma iemeslu
    // (tad esošo failu neaiztiek) vai null. Lasa stingri — nekas netiek klusi izlaists.
    $papildinat = static function (array &$f, string $tag) use ($cols, $sep, $atsl, $rinda, $rakstit): ?string {
        $fh = @fopen($f['target'], 'r');
        if ($fh === false) return 'nevar atvērt';
        try {
            if (!is_writable($f['target'])) return 'fails ir tikai lasāms';
            $first = fgets($fh);
            if ($first === false) return feof($fh) ? 'fails ir tukšs' : 'lasīšanas kļūda';
            $header = str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', rtrim((string)$first, "\r\n")), $sep, '"', '');
            $ncol = count($header);
            if ($ncol !== count(array_unique($header)) || array_diff($header, $cols) || array_diff($cols, $header)) {
                return 'kolonnas atšķiras no tabulas';
            }
            $idx = array_flip($header);
            while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
                if ($r === [null]) continue; // tukša rinda
                if (count($r) !== $ncol) return 'rinda ar citu lauku skaitu';
                $row = [];
                foreach ($cols as $c) $row[$c] = $r[$idx[$c]];
                if (quarter_tag((string)$row['Taksacijas_gads_ceturksnis']) !== $tag) return 'rindas ar citu vai neatpazītu ceturksni';
                if (isset($f['atsl'][$atsl($row)])) continue;
                $vals = [];
                foreach ($cols as $c) $vals[] = pd_str($row[$c]);
                $rakstit($f, $rinda($vals));
                if ($f['kluda']) return null;
                $f['tikai_arhiva']++;
            }
            return feof($fh) ? null : 'lasīšanas kļūda';
        } finally {
            fclose($fh);
        }
    };
    // Citi faili tam pašam ceturksnim (" v2", diapazons "YYYY-Ncet_YYYY-Ncet")
    $citi_faili = static function (string $tag) use ($dir): array {
        $t = (int)substr($tag, 0, 4) * 10 + (int)$tag[5];
        $o = [];
        foreach (glob($dir . '/*.csv') ?: [] as $p) {
            $b = basename($p);
            if ($b === $tag . ' pdb_samaksato_nodoklu_kopsummas_cet.csv') continue;
            if (!preg_match('/^(\d{4})-(\d)cet(?:_(\d{4})-(\d)cet)?/', $b, $m)) continue;
            $no = (int)$m[1] * 10 + (int)$m[2];
            $lidz = (isset($m[3]) && $m[3] !== '') ? (int)$m[3] * 10 + (int)$m[4] : $no;
            if ($t >= $no && $t <= $lidz) $o[] = $b;
        }
        return $o;
    };

    foreach (array_keys($faili) as $tag) {
        $f = $faili[$tag];
        $faili[$tag] = null; // atbrīvo atslēgu kopu pirms nākamā ceturkšņa
        if (!$f) continue;
        $base = basename($f['target']);
        $f['tikai_arhiva'] = 0;
        $atteikums = null;
        if ($f['ir'] && !$f['kluda']) $atteikums = $papildinat($f, $tag);
        $f['atsl'] = null;
        if (!$f['kluda'] && function_exists('fsync')) @fsync($f['fh']);
        if (!fclose($f['fh'])) $f['kluda'] = true;
        clearstatcache(true, $f['tmp']);
        if (!$f['kluda'] && @filesize($f['tmp']) !== $f['baiti']) $f['kluda'] = true;

        if ($f['kluda']) {
            @unlink($f['tmp']);
            if ($log) $log("      ! Ceturkšņa arhīvu neizdevās uzrakstīt: $base" . ($f['ir'] ? ' — esošais paliek, kā ir' : ''));
        } elseif (!$f['ir']) {
            if (@rename($f['tmp'], $f['target'])) {
                if ($log) $log("      + Ceturksnis arhivēts: $base (" . $f['n'] . " rindas)");
            } else {
                @unlink($f['tmp']);
                if ($log) $log("      ! Ceturkšņa arhīvu neizdevās uzrakstīt: $base");
            }
        } elseif ($atteikums !== null) {
            @unlink($f['tmp']);
            if ($log) $log("      ! Ceturkšņa arhīvs NEatjaunināts ($atteikums): $base — paliek, kā ir");
        } elseif (@md5_file($f['tmp']) === @md5_file($f['target'])) {
            @unlink($f['tmp']); // nav izmaiņu
        } elseif (@rename($f['tmp'], $f['target'])) {
            if ($log) $log("      ~ Ceturksnis atjaunināts (VID labojumi): $base (" . $f['n'] . " no svaigajiem + " . $f['tikai_arhiva'] . " tikai arhīvā)");
        } else {
            @unlink($f['tmp']);
            if ($log) $log("      ! Ceturkšņa arhīvu neizdevās atjaunināt: $base — esošais paliek, kā ir");
        }
        if ($f['ir'] && $log && ($citi = $citi_faili($tag))) {
            $log("      ! Ceturksnim $tag ir arī citi faili (" . implode(', ', $citi) . ") — apvienošanā tie tiek lasīti pēc "
                . "$base un var pārrakstīt VID labojumus, kad ceturksnis izkritīs no VID faila");
        }
    }
    fclose($mem);
}

/** Ceturkšņu apvienošanas pagaidu DB (rakstīšanas puses) kļūda — aptur būvi, nekad «vēstures nav». */
final class CeturksnuApvienosanasKluda extends RuntimeException {}

/**
 * Apvieno ceturkšņu vēsturi ar svaigajiem datiem: history DB → ceturksnis/*.csv → current.
 * Atslēga Registracijas_kods|Taksacijas_gads_ceturksnis; dublikātā uzvar pēdējais avots
 * (svaigais), bet rindas vieta paliek pirmā. Raksta atpakaļ gan history DB, gan ur_data.db
 * (viss kā TEXT — pandas astype(str)).
 *
 * ATMIŅA (audits 2026-11-10): līdz šim visi avoti glabājās PHP masīvos (~1 KB rindai, vēsture un
 * ceturkšņu CSV turklāt satur tos pašus ceturkšņus). Kad VID 2026-11-04 sāka publicēt visus
 * gada ceturkšņus vienā failā (155k → 462k rindu), maksimums sasniedza ~1,9 GB, un būve 7 naktis
 * pēc kārtas krita ar «Allowed memory size exhausted» (rc=255, bez žurnāla ieraksta). Tagad
 * avotus lasa straumē un apvieno pagaidu SQLite failā blakus ur_data.db (UPSERT pēc atslēgas;
 * _apv_s = pirmās parādīšanās secība) — PHP atmiņā ir viena rinda. Rezultāts ir identisks
 * vecajai versijai (testi: test_tools/registrs/ceturksnu_apvienosana/).
 *
 * KĻŪDAS (recenzija 2026-11-10): pagaidu DB rakstīšanas kļūda (pilns disks, I/O) jebkurā posmā
 * aptur apvienošanu ar CeturksnuApvienosanasKluda — tā NEDRĪKST izskatīties pēc «vēstures nav»,
 * citādi vēstures DB tiktu pārrakstīta tikai ar CSV + svaigajiem. Vēstures tabula, kuru nevar
 * nolasīt līdz galam (bojāta lapa), → brīdinājums, apvienojam bez tās, un vēstures DB NEpārrakstām
 * (vecā versija to klusi pārrakstīja).
 */
function merge_quarterly_history(PDO $ur, string $history_db, ?callable $log = null): void {
    $table = 'pdb_samaksato_nodoklu_kopsummas_cet';
    $qt = '"' . $table . '"';
    $ident = static fn(string $c): string => '"' . str_replace('"', '""', $c) . '"';
    try {
        // Svaigie dati no ur_data.db: kolonnas = pirmās rindas atslēgas (kā SELECT *)
        $probe = $ur->query("SELECT * FROM $qt LIMIT 1");
        $first = $probe->fetch(PDO::FETCH_ASSOC);
        $probe = null;
    } catch (Throwable $e) {
        if ($log) $log("   ! Ceturkšņu tabula ur_data.db nav: " . $e->getMessage());
        return;
    }
    if (!$first) { if ($log) $log("   - Ceturkšņu dati tukši"); return; }

    $cols = array_keys($first);
    $has_quarter = in_array('Taksacijas_gads_ceturksnis', $cols, true);
    $i_reg = array_search('Registracijas_kods', $cols, true);
    $i_q = array_search('Taksacijas_gads_ceturksnis', $cols, true);
    $clean_reg = function ($v) {
        $s = pd_str($v);
        return preg_replace('/\.0$/', '', $s);
    };
    $cl = implode(', ', array_map($ident, $cols));
    $defs = implode(', ', array_map(fn($c) => $ident($c) . ' TEXT', $cols));

    // Pagaidu apvienošanas DB diskā tajā pašā mapē, kur ur_data.db (staging)
    $ur_fails = '';
    foreach ($ur->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $d) {
        if ($d['name'] === 'main') $ur_fails = (string)$d['file'];
    }
    $mdir = ($ur_fails !== '' && is_dir(dirname($ur_fails))) ? dirname($ur_fails) : sys_get_temp_dir();
    $mfile = $mdir . '/ceturksnu_apvienosana.' . getmypid() . '.sqlite';
    // Arī iepriekšējas nokautas būves atlikumi (cits pid; finally pie SIGKILL neizpildās, ~110 MB).
    // Būves lock pieder mums, tāpēc citas dzīvas apvienošanas te nav.
    foreach (glob($mdir . '/ceturksnu_apvienosana.*.sqlite*') ?: [] as $f) @unlink($f);

    $m = $ins = $add_rows = $h = null;
    try {
        $m = new PDO('sqlite:' . $mfile);
        $m->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $m->exec('PRAGMA journal_mode = MEMORY'); // ROLLBACK jāstrādā (vēstures atritināšanai)
        $m->exec('PRAGMA synchronous = OFF');
        // _apv_s = rowid (pirmās parādīšanās secība, UPSERT to nemaina) → nolasīšana bez kārtošanas
        $m->exec("CREATE TABLE m (_apv_s INTEGER PRIMARY KEY, _apv_k TEXT NOT NULL UNIQUE, $defs)");
        $ins = $m->prepare("INSERT INTO m (_apv_s, _apv_k, $cl) VALUES ("
            . implode(', ', array_fill(0, count($cols) + 2, '?')) . ') ON CONFLICT(_apv_k) DO UPDATE SET '
            . implode(', ', array_map(fn($c) => $ident($c) . ' = excluded.' . $ident($c), $cols)));
        $seq = 0;
        // Viena avota rindas vienā transakcijā (kļūda vidū atritina visu avotu); atgriež rindu skaitu.
        // Avota (lasīšanas) kļūdas iziet kā ir; pagaidu DB (rakstīšanas) kļūdas — kā
        // CeturksnuApvienosanasKluda, lai izsaucējs tās nevar sajaukt ar «vēstures nav».
        $add_rows = function (iterable $rows) use ($m, $ins, $cols, $has_quarter, $i_reg, $i_q, $clean_reg, &$seq): int {
            $merkis = static fn(Throwable $e): CeturksnuApvienosanasKluda => $e instanceof CeturksnuApvienosanasKluda ? $e
                : new CeturksnuApvienosanasKluda('Ceturkšņu apvienošanas pagaidu DB kļūda: ' . $e->getMessage(), 0, $e);
            $n = 0;
            try { $m->beginTransaction(); } catch (Throwable $e) { throw $merkis($e); }
            try {
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($cols as $c) {
                        $val = $r[$c] ?? null;
                        $vals[] = $c === 'Registracijas_kods' ? $clean_reg($val) : pd_str($val);
                    }
                    $seq++;
                    $key = $has_quarter
                        ? (($i_reg !== false ? $vals[$i_reg] : '') . '|' . $vals[$i_q])
                        : (string)$seq; // bez dedup atslēgas
                    try { $ins->execute([$seq, $key, ...$vals]); } catch (Throwable $e) { throw $merkis($e); }
                    $n++;
                }
                try { $m->commit(); } catch (Throwable $e) { throw $merkis($e); }
            } catch (Throwable $e) {
                try { if ($m->inTransaction()) $m->rollBack(); } catch (Throwable) {}
                throw $e;
            }
            return $n;
        };

        // 1) History (ja eksistē). Fails nav DB / tabulas nav → bez vēstures, kā līdz šim (tad to
        //    zemāk pārraksta ar apvienoto). Tabulu nevar nolasīt līdz galam → bez vēstures, bet
        //    vēstures DB NEpārrakstām. Pagaidu DB kļūda → aptur visu (izņēmums iet tālāk).
        $vesturi_drikst_rakstit = true;
        if (is_file($history_db)) {
            $hst = null;
            try {
                $h = new PDO('sqlite:' . $history_db);
                $h->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $hst = $h->query("SELECT * FROM $qt", PDO::FETCH_ASSOC);
            } catch (Throwable) { /* history tabulas nav — OK */ }
            if ($hst !== null) {
                try {
                    $add_rows($hst);
                } catch (CeturksnuApvienosanasKluda $e) {
                    throw $e;
                } catch (Throwable $e) {
                    $vesturi_drikst_rakstit = false;
                    if ($log) $log("   ! Vēstures DB nevar nolasīt līdz galam: " . $e->getMessage()
                        . " — apvienoju bez vēstures; vēstures DB NEpārrakstu (pārbaudīt/atjaunot ar roku)");
                    unset($e); // izņēmuma trase tur vaicājumu → vēstures DB paliktu slēgta
                }
            }
            $hst = $h = null;
        }

        // 2) Papildu vēsturiskie ceturkšņi no CSV mapes (ceturksnis/) — kvartāli, kas VAIRS nav
        // lejupielādējami no data.gov.lv. Ielasa VISUS *.csv failus tur; kolonnu nosaukumi =
        // tabulas kolonnas. Merge secība: history -> ceturksnis CSV -> current (svaigais uzvar
        // dublikātos; ceturksnis uzvar pār veco history to pašu kvartālu).
        $quarter_dir = getenv('REG_QUARTER_DIR') ?: (reg_docroot() . '/ceturksnis');
        $sep = csv_sep_for($table);
        // 1) Manuāli iemestos failus pārsauc kanoniskos vārdos (pēc satura ceturkšņiem);
        // 2) svaigā builda ceturkšņus arhivē, lai vēsture uzkrājas automātiski.
        normalize_quarter_dir($quarter_dir, $sep, $log);
        archive_current_quarters($quarter_dir, $ur->query("SELECT * FROM $qt", PDO::FETCH_ASSOC), $cols, $sep, $log);
        if (is_dir($quarter_dir)) {
            $csv_files = glob($quarter_dir . '/*.csv') ?: [];
            sort($csv_files);
            foreach ($csv_files as $cf) {
                $n = $add_rows(iter_quarterly_csv($cf, $sep, $cols));
                if ($n > 0 && $log) $log("      + Ceturkšņu CSV: " . basename($cf) . " (" . $n . " rindas)");
            }
        }

        // 3) Svaigie dati (uzvar)
        $add_rows($ur->query("SELECT * FROM $qt", PDO::FETCH_ASSOC));
        $total = (int)$m->query('SELECT COUNT(*) FROM m')->fetchColumn();
        $add_rows = $ins = $m = null; // aizver apvienošanas DB pirms ATTACH citos savienojumos

        // 4) Raksta abās DB kā TEXT. DROP+CREATE+INSERT vienā transakcijā — pārtraukums
        //    neatstāj tukšu/pusrakstītu tabulu. Secība = pirmā parādīšanās.
        $write = function (PDO $db) use ($mfile, $table, $qt, $defs, $cl): void {
            $db->exec('ATTACH DATABASE ' . $db->quote($mfile) . ' AS ceturksnu_apv');
            try {
                $db->beginTransaction();
                try {
                    $db->exec("DROP TABLE IF EXISTS $qt");
                    $db->exec("CREATE TABLE $qt ($defs)");
                    $db->exec("INSERT INTO $qt ($cl) SELECT $cl FROM ceturksnu_apv.m ORDER BY _apv_s");
                    $db->commit();
                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    throw $e;
                }
            } finally {
                try { $db->exec('DETACH DATABASE ceturksnu_apv'); } catch (Throwable $e) {}
            }
            // Indekss regcode meklēšanai
            try { $db->exec("CREATE INDEX IF NOT EXISTS idx_{$table}_reg ON $qt (\"Registracijas_kods\")"); } catch (Throwable $e) {}
        };

        // history DB (ne tad, ja tās tabulu nevarēja nolasīt — sk. 1. soli)
        if ($vesturi_drikst_rakstit) {
            try {
                $h = new PDO('sqlite:' . $history_db);
                $h->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $write($h);
            } catch (Throwable $e) {
                if ($log) $log("   ! History DB rakstīšana neizdevās: " . $e->getMessage());
                unset($e);
            }
            $h = null;
        }
        // ur_data.db
        $write($ur);
        if ($log) $log("      + Apvienoti " . $total . " ceturkšņu ieraksti");
    } finally {
        $add_rows = $ins = $m = $h = null;
        foreach (['', '-journal'] as $s) @unlink($mfile . $s);
    }
}

/**
 * VID gada nodokļu vēsture (pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata).
 *
 * KĀPĒC: VID kopa ir "iepriekšējie TRĪS taksācijas gadi" — slīdošs logs. 2026-09-14
 * VID izmeta 2022. gadu, bet 2025. nepievienoja (palika 2023–2024), un lapās klusi
 * pazuda gads: 1 830 likvidētu uzņēmumu VID panelis pavisam (audits 2026-10-14).
 * Ceturkšņiem vēsture jau uzkrājas (merge_quarterly_history), gada datiem — ne.
 *
 * LIKUMS (gada līmenī, ne rindu): gadu, kas ir svaigajos datos, nosaka VID jaunākā
 * publikācija (tā drīkst gadu pārskatīt); gadu, kura svaigajos datos vairs nav, ņemam
 * no vēstures. Sarukuma sargs: ja svaigajā gadā ir < 50 % juridisko personu rindu pret
 * vēsturi, to uzskatām par nepilnu publikāciju: svaigās rindas paliek, un no vēstures pieliek
 * tikai trūkstošās juridiskās personas; šī gada vēsturi nepārraksta (žurnālā brīdinājums).
 *
 * FIZISKAS PERSONAS: gadu, ko VID vairs nepublicē, atjaunojam TIKAI juridiskām personām —
 * individuālo komersantu (IK; arī ZEM/IND/ZVJ, ja kādreiz parādās) rindas no vēstures neņemam.
 * Leģitīmo interešu izvērtējums (LIA): "publicējam tikai to, kas jau ir publisks UR/VID
 * atvērtajos datos" — fiziskas personas nodokļu summa, ko VID izņēmis, tur vairs nav.
 * 2022. gadā tās ir 10 324 rindas. Vēsturē fizisko personu rindas NEGLABĀJAM vispār (VDAR
 * glabāšanas ierobežojums; tās, kas tur nonāca agrāk, katrā palaišanā izmet) — gada dati par
 * IK nāk tikai no VID pašreizējās publikācijas (gala audits 2026-10-14).
 *
 * Vēsture: atsevišķs SQLite fails ($history_db, noklusējumā {datu sakne}/vid_gada_vesture.sqlite),
 * tabula ar tādu pašu nosaukumu, kolonnas TEXT. Raksta UR staging DB un vēstures DB.
 * Kļūda šeit būvi neaptur: tad lapās ir tikai svaigie gadi (kā līdz šim).
 */
function merge_annual_vid_history(PDO $ur, string $history_db, ?callable $log = null): void {
    $t = 'pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata';
    $q = '"' . $t . '"';
    $ident = static fn(string $c): string => '"' . str_replace('"', '""', $c) . '"';
    try {
        $kol = array_column($ur->query("PRAGMA main.table_info($q)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    } catch (Throwable $e) {
        $kol = [];
    }
    if (!in_array('Taksacijas_gads', $kol, true) || !in_array('Registracijas_kods', $kol, true)) {
        if ($log) $log('   - VID gada vēsture: svaigās tabulas nav vai tai nav gada/koda kolonnas — izlaista');
        return;
    }
    $g_sql = 'CAST(TRIM(Taksacijas_gads) AS INTEGER)';
    $svaigi = [];
    foreach ($ur->query("SELECT $g_sql AS g, COUNT(*) AS n FROM main.$q GROUP BY 1") as $r) {
        if ((int)$r['g'] >= 1990) $svaigi[(int)$r['g']] = (int)$r['n'];
    }
    if (!$svaigi) {
        if ($log) $log('   - VID gada vēsture: svaigajos datos nav neviena atpazīstama gada — izlaista');
        return;
    }

    $pievienoti = []; $aizsargati = [];
    $pievienota = false;
    try {
        // ATTACH try IEKŠĀ: bojāts/aizslēgts/nepieejams vēstures fails nedrīkst apturēt visu
        // nakts būvi — tad vienkārši nav vēstures (recenzija 2026-10-14).
        $ur->exec('ATTACH DATABASE ' . $ur->quote($history_db) . ' AS vg_vesture');
        $pievienota = true;
        $hkol = array_column($ur->query("PRAGMA vg_vesture.table_info($q)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!$hkol) {
            $ur->exec("CREATE TABLE vg_vesture.$q (" . implode(', ', array_map(fn($c) => $ident($c) . ' TEXT', $kol)) . ')');
            $hkol = $kol;
        } else {
            // Jauna avota kolonna → arī vēsturē (vecajām rindām NULL); pazudusi — paliek vēsturē.
            // Salīdzina BEZ reģistra: SQLite kolonnu nosaukumi ir reģistrnejutīgi, un tikai
            // reģistra maiņa (Nosaukums → NOSAUKUMS) citādi dotu "duplicate column name" un
            // vēsture katru nakti atritētu (recenzija 2026-10-14).
            $h_mazie = array_flip(array_map('mb_strtolower', $hkol));
            foreach ($kol as $c) {
                if (isset($h_mazie[mb_strtolower($c)])) continue;
                $ur->exec("ALTER TABLE vg_vesture.$q ADD COLUMN " . $ident($c) . ' TEXT');
                $hkol[] = $c;
                $h_mazie[mb_strtolower($c)] = true;
            }
        }
        $h_mazie = array_flip(array_map('mb_strtolower', $hkol));
        $cs = implode(', ', array_map($ident, array_values(array_filter($kol, fn($c) => isset($h_mazie[mb_strtolower($c)])))));
        $vesture = [];
        foreach ($ur->query("SELECT $g_sql AS g, COUNT(*) AS n FROM vg_vesture.$q GROUP BY 1") as $r) {
            if ((int)$r['g'] >= 1990) $vesture[(int)$r['g']] = (int)$r['n'];
        }

        // Fizisko personu filtrs (sk. galvu): register tips UN VID formas teksts — abi, ja ir.
        // Ja nevar atšķirt vispār, izņemtos gadus neatjaunojam un vēsturi neaiztiekam (fail-closed).
        $filtri = [];
        if ((bool)$ur->query("SELECT 1 FROM main.sqlite_master WHERE type='table' AND name='register'")->fetchColumn()) {
            $filtri[] = "Registracijas_kods NOT IN (SELECT regcode FROM main.register WHERE type IN ('IK','ZEM','IND','ZVJ'))";
        }
        if (in_array('Uznemejdarbibas_forma', $kol, true) && isset($h_mazie['uznemejdarbibas_forma'])) {
            $filtri[] = "LOWER(COALESCE(Uznemejdarbibas_forma, '')) NOT LIKE 'individuāl%'";
        }
        $var_atskirt = (bool)$filtri;
        $bez_personam = $filtri ? '(' . implode(' AND ', $filtri) . ')' : '0';
        // Sarukuma sargam salīdzinām juridisko personu skaitu (vēsturē fizisku personu nav).
        $svaigi_jp = [];
        foreach ($ur->query("SELECT $g_sql AS g, COUNT(*) AS n FROM main.$q WHERE $bez_personam GROUP BY 1") as $r) {
            $svaigi_jp[(int)$r['g']] = (int)$r['n'];
        }
        $ur->beginTransaction();
        if ($var_atskirt) {
            // Glabāšanas ierobežojums (VDAR): fizisko personu rindas vēsturē neturam vispār —
            // arī tās, kas tur nonāca agrāk (sēkla, iepriekšējās versijas) (audits 2026-10-14).
            $izmesti_vesture = (int)$ur->exec("DELETE FROM vg_vesture.$q WHERE NOT $bez_personam");
            if ($izmesti_vesture > 0) {
                $vesture = [];
                foreach ($ur->query("SELECT $g_sql AS g, COUNT(*) AS n FROM vg_vesture.$q GROUP BY 1") as $r) {
                    if ((int)$r['g'] >= 1990) $vesture[(int)$r['g']] = (int)$r['n'];
                }
            }
        }
        foreach ($vesture as $g => $n) {
            $g = (int)$g;
            if (!$var_atskirt) break;
            if (!isset($svaigi[$g])) {
                // Gads, ko VID vairs nepublicē → no vēstures (tajā ir tikai juridiskās personas).
                $pievienoti[$g] = (int)$ur->exec("INSERT INTO main.$q ($cs) SELECT $cs FROM vg_vesture.$q WHERE $g_sql = $g AND $bez_personam");
            } elseif (($svaigi_jp[$g] ?? 0) < 0.5 * $n) {
                // Aizdomīgi nepilna publikācija: svaigās rindas PALIEK (VID tās šobrīd publicē, arī
                // IK), un no vēstures pieliekam tikai juridiskās personas, kuru šajā gadā nav.
                $ur->exec("INSERT INTO main.$q ($cs) SELECT $cs FROM vg_vesture.$q WHERE $g_sql = $g AND $bez_personam
                           AND Registracijas_kods NOT IN (SELECT Registracijas_kods FROM main.$q WHERE $g_sql = $g)");
                $aizsargati[$g] = [$svaigi_jp[$g] ?? 0, $n];
            }
        }
        // Vēsturi atjaunojam ar svaigajiem gadiem (gada līmenī), izņemot aizsargātos — TIKAI
        // juridiskās personas.
        if ($var_atskirt) {
            foreach (array_keys($svaigi) as $g) {
                $g = (int)$g;
                if (isset($aizsargati[$g])) continue;
                $ur->exec("DELETE FROM vg_vesture.$q WHERE $g_sql = $g");
                $ur->exec("INSERT INTO vg_vesture.$q ($cs) SELECT $cs FROM main.$q WHERE $g_sql = $g AND $bez_personam");
            }
        } elseif ($log) {
            $log('   ! VID gada vēsture: fiziskas personas nav atšķiramas (nav register un formas kolonnas) — vēsture netiek ne lietota, ne atjaunota');
        }
        $ur->commit();
    } catch (Throwable $e) {
        if ($ur->inTransaction()) $ur->rollBack();
        $pievienoti = []; $aizsargati = [];
        if ($log) $log('   ! VID gada vēsture neizdevās (lapās būs tikai svaigie gadi): ' . $e->getMessage());
        if (function_exists('avotu_bridinajumi')) avotu_bridinajumi('VID gada vēsture neizdevās: ' . $e->getMessage());
    } finally {
        if ($pievienota) { try { $ur->exec('DETACH DATABASE vg_vesture'); } catch (Throwable $e) { /* nav pievienota */ } }
    }
    if ($log) {
        ksort($svaigi);
        $txt = '      + VID gada dati: svaigie gadi ' . implode(', ', array_keys($svaigi));
        if ($pievienoti) {
            ksort($pievienoti);
            $txt .= '; no vēstures pievienoti ' . implode(', ', array_map(
                fn($g, $n) => "$g (" . number_format($n, 0, ',', ' ') . ' rindas)', array_keys($pievienoti), $pievienoti));
            $txt .= ' (tikai juridiskās personas)';
        }
        $log($txt);
        foreach ($aizsargati as $g => [$s, $h]) {
            $t = "VID gada dati $g svaigajos tikai " . number_format($s, 0, ',', ' ')
                . ' juridisko personu rindas pret ' . number_format($h, 0, ',', ' ') . ' vēsturē (< 50 %) — trūkstošās pieliktas no vēstures';
            $log("   ! BRĪDINĀJUMS: $t");
            if (function_exists('avotu_bridinajumi')) avotu_bridinajumi($t);
        }
    }
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

    // 3. FS: jaunākā gada apgrozījums/peļņa/darbinieki — no uzņēmuma PAŠA pārskata.
    // Koncerniem vienam gadam ir divi pārskati: paša (UGP) un konsolidētais (UKGP; līdz 2015. g.
    // source_type tukšs, pazīme ir shēma DokUKGP*). Agrāk uzvarēja tas, kuru SQLite izdeva pirmo,
    // un 84 uzņēmumiem nozarē nonāca koncerna summa (piem. 40103815302: 11,2 M€ apgrozījums pret
    // paša 8,1 M€; nozares kopsumma +4,47 mljrd. €), kamēr profila "Pārskats (Individuālais)"
    // rāda paša skaitļus. Izvēle — kopīgais likums (lib/parskata_izvele.php), tas pats, ko profils
    // un sadaļas: konsolidēto ņem tikai tad, ja paša pārskata nav nevienam gadam; gadā garākais
    // periods (finanšu gada maiņas pārejas periods nav gada skaitlis), tad jaunākais iesniegums
    // (created_at, tad id kā skaitlis); gadus bez vērtības izmet.
    try {
        $q = "SELECT f.legal_entity_registration_number AS reg, f.rounded_to_nearest AS rnd,
                     f.employees AS emp, i.net_turnover AS turn, i.net_income AS inc,
                     f.source_type, f.source_schema, f.year_started_on, f.year_ended_on, f.created_at, f.id, f.year
              FROM financial_statements f
              LEFT JOIN income_statements i ON i.statement_id = f.id
              WHERE f.year IS NOT NULL
              ORDER BY f.legal_entity_registration_number";
        $st = $ur->query($q);
        $st->setFetchMode(PDO::FETCH_ASSOC);
        foreach (reg_fs_izvele_plusma($st, 'reg') as $rc => $pa_gadiem) {
            $rc = (string)$rc;
            if (!isset($main[$rc]) || !$pa_gadiem) continue;
            $r = end($pa_gadiem); // jaunākais gads
            $rnd = strtoupper((string)($r['rnd'] ?? ''));
            $mult = str_contains($rnd, 'THOUS') ? 1000 : (str_contains($rnd, 'MILL') ? 1000000 : 1);
            $main[$rc]['turnover'] = (float)($r['turn'] ?? 0) * $mult;
            $main[$rc]['profit'] = (float)($r['inc'] ?? 0) * $mult;
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
    // "Pozīcija nozarē" rangu navigatoram (registrs/lib/rangu_navigators.php) — statiskas saites uz
    // uzņēmumiem, kas nozares apgrozījuma rangā stāv ±1, ±5, ±25 … vietas tālāk. Rangs ir TIEŠI tas,
    // ko grafiks "Apgrozījums (Rangs)" (industry_charts.js) aprēķina no šī paša JSON: stabila kārtošana
    // pēc apgrozījuma dilstoši no peļņas secības. Lapā to vajag bez JSON dekodēšanas (0000 = ~1 MB).
    $c->exec("CREATE TABLE nace_rangs (code TEXT NOT NULL, rangs INTEGER NOT NULL, regcode TEXT NOT NULL,
              name TEXT, turnover REAL, PRIMARY KEY (code, rangs)) WITHOUT ROWID");
    $ins_r = $c->prepare("INSERT INTO nace_rangs (code, rangs, regcode, name, turnover) VALUES (?, ?, ?, ?, ?)");
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
        // Nederīgs UTF-8 nosaukumā vairs nenogāž visu nozari; ja JSON tomēr neizdodas (INF/NAN), nozari izlaiž
        // pilnībā — agrāk tā tika ierakstīta ar tukšu JSON, un navigatoram būtu rangi, kamēr grafiks "datu nav".
        $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            if ($log) $log("      - NACE $nace_code izlaists: " . json_last_error_msg());
            continue;
        }
        $ins->execute([$nace_code, $json]);
        // usort ir stabils (PHP ≥ 8), JS Array.sort arī — vienāda apgrozījuma secība paliek peļņas secība.
        $pec_apgr = $companies;
        usort($pec_apgr, fn($a, $b) => $b[3] <=> $a[3]);
        foreach ($pec_apgr as $i => $co) $ins_r->execute([$nace_code, $i + 1, $co[1], $co[0], $co[3]]);
        $count++;
    }
    $c->exec("CREATE INDEX idx_nace_rangs_reg ON nace_rangs (regcode)");
    $c->commit();
    $c = null;
    if ($log) $log("      + NACE DB gatava: $count nozares");
}
