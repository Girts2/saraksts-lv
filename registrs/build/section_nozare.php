<?php
// server/build/section_nozare.php — "Nozare" sadaļas būve (ports of Nozare/"1 aprēķins.py").
// Ģenerē nozare/katalogs.sqlite (tabulas: nace, companies) tieši no ur_data.db + NACE.csv.
//
// Zināmās (labās) novirzes no Python:
//  - name: Python dtype=str deva 'nan' artefaktus trūkstošiem type/name_in_quotes; šeit tie ir tukši
//    (tas pats labojums, kas reģistra meklēšanas DB).
//  - avg_net_salary: Python vidē trūka core.page_builder -> fallback gross*0.70; šeit izmanto īsto
//    calculate_net_salary() no lib/page_builder.php. Verifikācijai REG_NET_FALLBACK70=1 atgriež 0.70.
//  - prev_salary/salary_change (gada zars): arī prev vērtībai īstais calculate_net_salary(), nevis
//    Python 0.70 aproksimācija — citādi kategoriju algu izmaiņu % ieguva mākslīgu nobīdi.
//  - NACE 2.0 kodi, kuru vairs nav 2.1 klasifikatorā (piem., 4120, 5610), tiek piekārtoti tuvākajam
//    eksistējošajam prefiksa mezglam (5610 -> 561), nevis klusi izmesti no agregātiem.
//  - health_string tiek aprēķināta arī burtu sekcijām (A..U) caur to nodaļu prefiksiem; agrāk tās
//    palika 'nnnnnnnnnn' un UI tās lāpīja ar citu (bērnu-vairākuma) semantiku.
//  - change_period kolonna: 'cet'/'gads' — pret kādu periodu ir employees_change/salary_change.
//  - darbinieku skaits tiek noapaļots (round), nevis nocirsts — 0.5 darbinieki vairs nekļūst par 0.
//  - name_sort kolonna: latviešu kārtošanas atslēga (CLDR 'lv' tuvinājums) — SQLite BINARY
//    ORDER BY name_sort dod to pašu secību, ko klienta localeCompare('lv') deva agrāk.
declare(strict_types=1);

require_once __DIR__ . '/section_common.php';

/** pandas to_numeric(errors='coerce') analogs: null, ja nav skaitlis. */
function noz_num($v): ?float {
    if ($v === null) return null;
    if (is_int($v) || is_float($v)) return (float)$v;
    $t = trim((string)$v);
    if ($t === '' || !is_numeric($t)) return null;
    return (float)$t;
}

/** VSAOI teksta parse: noņem atstarpes, ',' -> '.', *1000 (Python vektorizētā versija). */
function noz_vsaoi_val($raw): float {
    $s = preg_replace('/\s+/u', '', (string)($raw ?? ''));
    $s = str_replace(',', '.', $s);
    $n = ($s === '' || !is_numeric($s)) ? null : (float)$s;
    return ($n ?? 0.0) * 1000.0;
}

/** Ceturkšņa sortējama atslēga: "2026. gada 1. ceturksnis" -> 20261. null, ja neatpazīst. */
function noz_quarter_key(?string $q): ?int {
    if ($q === null) return null;
    if (preg_match('/(\d{4})\D+(\d)\.?\s*ceturksn/u', $q, $m)) {
        return (int)$m[1] * 10 + (int)$m[2];
    }
    return null;
}

/** Gads no ceturkšņa atslēgas (20261 -> 2026). */
function noz_quarter_year(int $qkey): int { return intdiv($qkey, 10); }

/**
 * Latviešu kārtošanas atslēga (CLDR 'lv' kolācijas tuvinājums) BINARY salīdzināšanai SQLite pusē.
 * Divi līmeņi vienā virknē: PRIMĀRAIS (mazie burti; latviešu diakritiskie UN garie patskaņi =
 * atsevišķi burti TŪLĪT aiz bāzes burta caur 'x~' triku: 'c~' > 'cz', bet < 'd'; secība
 * i < y < ī caur 'i' < 'i~' < 'i~~'; pieturzīmes/atstarpes ignorētas kā ICU shifted) + "\t" +
 * SEKUNDĀRAIS (pilns pazeminātais nosaukums — šķir lielos/mazos un svešos diakritiskos).
 * Atdalītājam JĀBŪT zem visiem primārā baitiem (0x09 < '0'), citādi "NTT" kārtotos aiz
 * "NTT Operator". Šķērspārbaudīts pret PHP intl Collator('lv_LV') ar ALTERNATE_HANDLING=SHIFTED.
 */
function noz_name_sort_key(string $name): string {
    static $map = [
        'ā' => 'a~', 'č' => 'c~', 'ē' => 'e~', 'ģ' => 'g~', 'ķ' => 'k~',
        'ļ' => 'l~', 'ņ' => 'n~', 'š' => 's~', 'ū' => 'u~', 'ž' => 'z~',
        'y' => 'i~', 'ī' => 'i~~', // CLDR lv: i < y < ī
        'ō' => 'o',
        // biežākie svešvalodu diakritiskie -> bāzes burts (ICU primārā līmeņa tuvinājums)
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a', 'ã' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'i~', 'ß' => 'ss',
    ];
    $lower = mb_strtolower($name, 'UTF-8');
    // ICU (CLDR maxVariable=punct): pieturzīmes/atstarpes ignorētas, bet SIMBOLI (+ = € u.c.)
    // ir primāri nozīmīgi un kārtojas PIRMS cipariem/burtiem -> visi simboli uz '!' (0x21 < '0');
    // jādara PIRMS strtr, lai nesajauktu mūsu '~' marķierus (arī pats ~ nosaukumā ir \p{S})
    $primary = preg_replace('/\p{S}/u', '!', $lower) ?? $lower;
    $primary = strtr($primary, $map);
    // paliek burti (arī citu rakstību), cipari, '~' marķieris un '!' simbolu klase;
    // pieturzīmes (arī tipogrāfiskās “”) izmestas
    $primary = preg_replace('/[^\p{L}\p{N}~!]+/u', '', $primary) ?? $primary;
    return $primary . "\t" . $lower;
}

/** safe_divide ar isfinite pārbaudi (Nozares variants). */
function noz_safe_divide(float $n, float $d): ?float {
    if ($d == 0.0) return null;
    $r = $n / $d;
    return is_finite($r) ? $r : null;
}

/** Finanšu rādītāji no jaunākā gada (jau ar reizinātāju) vērtībām; null -> 0. */
function noz_ratios(array $v): array {
    $g = fn(string $k): float => (float)($v[$k] ?? 0.0);
    $ratios = [];
    $ratios['current_ratio'] = noz_safe_divide($g('total_current_assets'), $g('current_liabilities'));
    $ratios['quick_ratio'] = noz_safe_divide($g('total_current_assets') - $g('inventories'), $g('current_liabilities'));
    $ratios['net_profit_margin'] = noz_safe_divide($g('net_income'), $g('net_turnover'));
    if ($ratios['net_profit_margin'] !== null) $ratios['net_profit_margin'] *= 100;
    $ratios['roa'] = noz_safe_divide($g('net_income'), $g('total_assets'));
    if ($ratios['roa'] !== null) $ratios['roa'] *= 100;
    $ratios['roe'] = noz_safe_divide($g('net_income'), $g('equity'));
    if ($ratios['roe'] !== null) $ratios['roe'] *= 100;
    $total_liabilities = $g('current_liabilities') + $g('non_current_liabilities');
    $ratios['debt_to_equity'] = noz_safe_divide($total_liabilities, $g('equity'));
    $ratios['asset_turnover'] = noz_safe_divide($g('net_turnover'), $g('total_assets'));
    $ebit = $g('income_before_income_taxes') + $g('interest_expenses');
    $iegulditais = $g('total_assets') - $g('current_liabilities');
    $ratios['roce'] = noz_safe_divide($ebit, $iegulditais);
    if ($ratios['roce'] !== null) $ratios['roce'] *= 100;
    $ratios['interest_coverage'] = noz_safe_divide($ebit, $g('interest_expenses'));
    $A = noz_safe_divide($g('total_current_assets') - $g('current_liabilities'), $g('total_assets'));
    $B = noz_safe_divide($g('equity'), $g('total_assets'));
    $C = noz_safe_divide($ebit, $g('total_assets'));
    $D = noz_safe_divide($g('equity'), $total_liabilities);
    $E = noz_safe_divide($g('net_turnover'), $g('total_assets'));
    if ($A === null || $B === null || $C === null || $D === null || $E === null) {
        $ratios['altman_z_score'] = null;
    } else {
        // Altman Z' (1983) modelis privātiem uzņēmumiem
        $ratios['altman_z_score'] = 0.717 * $A + 0.847 * $B + 3.107 * $C + 0.420 * $D + 0.998 * $E;
    }
    return $ratios;
}

/** Veselības simbols (Nozares sliekšņi — atšķiras no Pensionāra pie robežvērtībām!). */
function noz_health_symbol(string $key, ?float $v): string {
    if ($v === null || !is_finite($v)) return 'n';
    switch ($key) {
        case 'current_ratio':     return $v < 1.0 ? 'r' : ($v < 1.5 ? 'o' : ($v <= 3.0 ? 'g' : 'b'));
        case 'quick_ratio':       return $v < 0.8 ? 'r' : ($v < 1.0 ? 'o' : ($v <= 2.0 ? 'g' : 'b'));
        case 'net_profit_margin': return $v < 0 ? 'r' : ($v < 10 ? 'o' : 'g');
        case 'roa':               return $v < 2 ? 'r' : ($v <= 5 ? 'o' : 'g');
        case 'roe':               return $v < 5 ? 'r' : ($v <= 15 ? 'o' : 'g');
        case 'debt_to_equity':    return $v < 1.5 ? 'g' : ($v <= 2.0 ? 'o' : 'r');
        case 'asset_turnover':    return $v < 0.5 ? 'r' : ($v <= 1.0 ? 'o' : 'g');
        case 'roce':              return $v < 5 ? 'r' : ($v <= 15 ? 'o' : 'g');
        case 'interest_coverage': return $v < 1.5 ? 'r' : ($v < 3.0 ? 'o' : 'g');
        case 'altman_z_score':    return $v < 1.23 ? 'r' : ($v < 2.90 ? 'o' : 'g');
    }
    return 'n';
}

const NOZ_HEALTH_KEYS = ['current_ratio', 'quick_ratio', 'net_profit_margin', 'roa', 'roe',
    'debt_to_equity', 'asset_turnover', 'roce', 'interest_coverage', 'altman_z_score'];

/**
 * Uzbūvē Nozares katalogs.sqlite.
 *
 * @param PDO           $ur       ur_data.db
 * @param string        $nace_csv NACE.csv klasifikators (statisks)
 * @param string        $out_db   izvades SQLite (staging/katalogs.sqlite)
 * @param callable|null $log
 */
function build_section_nozare(PDO $ur, string $nace_csv, string $out_db, ?callable $log = null): void {
    $log = $log ?? function ($m) {};
    ini_set('precision', '17'); // pilna double precizitāte PDO float->string saistīšanā
    if (!is_file($nace_csv)) throw new RuntimeException("Nozare: trūkst $nace_csv");
    $net70 = getenv('REG_NET_FALLBACK70') === '1';
    if (!$net70) require_once __DIR__ . '/../lib/page_builder.php'; // calculate_net_salary()

    // --- Izvades DB ---
    if (is_file($out_db)) @unlink($out_db);
    @mkdir(dirname($out_db), 0775, true);
    $out = new PDO('sqlite:' . $out_db);
    $out->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $out->exec('PRAGMA journal_mode = OFF');
    $out->exec('PRAGMA synchronous = OFF');
    $out->exec('CREATE TABLE nace (
            code TEXT PRIMARY KEY,
            name TEXT,
            parent_code TEXT,
            level INTEGER,
            health_string TEXT
        )');
    $out->exec('CREATE TABLE companies (
            regcode TEXT PRIMARY KEY,
            name TEXT,
            nace_code_np TEXT,
            turnover REAL,
            turnover_change REAL,
            prev_turnover REAL,
            employees INTEGER,
            employees_change REAL,
            prev_employees INTEGER,
            profit REAL,
            profit_change REAL,
            prev_profit REAL,
            avg_gross_salary REAL,
            avg_net_salary REAL,
            salary_change REAL,
            prev_salary REAL,
            registered_date TEXT,
            financial_health_string TEXT,
            location TEXT,
            change_period TEXT,
            name_sort TEXT
        )');
    $out->exec('CREATE INDEX idx_nace_parent ON nace (parent_code)');
    $out->exec('CREATE INDEX idx_companies_nace ON companies (nace_code_np)');

    // --- NACE.csv -> nace tabula ---
    $fh = fopen($nace_csv, 'r');
    if ($fh === false) throw new RuntimeException("Nozare: nevar atvērt $nace_csv");
    $first = fgets($fh);
    $first = preg_replace('/^\xEF\xBB\xBF/', '', rtrim((string)$first, "\r\n"));
    $header = str_getcsv($first, ',', '"', '');
    $idx = array_flip($header);
    foreach (['Kods', 'Nosaukums', 'Vecāka kods', 'Līmenis'] as $col) {
        if (!isset($idx[$col])) throw new RuntimeException("Nozare: NACE.csv trūkst kolonnas '$col'");
    }
    $insNace = $out->prepare('INSERT OR IGNORE INTO nace (code, name, parent_code, level, health_string) VALUES (?, ?, ?, ?, ?)');
    $out->beginTransaction();
    $naceCount = 0;
    $nodeNp = []; // mezglu kodi bez punktiem => true (uzņēmumu kodu izšķiršanai)
    while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if (count($row) !== count($header)) continue;
        $kods = trim((string)$row[$idx['Kods']]);
        $nosaukums = trim((string)$row[$idx['Nosaukums']]);
        $vecaks = trim((string)$row[$idx['Vecāka kods']]);
        $limenis = noz_num($row[$idx['Līmenis']]);
        $insNace->execute([$kods, $nosaukums, $vecaks, (int)($limenis ?? 9), 'nnnnnnnnnn']);
        $nodeNp[str_replace('.', '', $kods)] = true;
        $naceCount++;
    }
    $out->commit();
    fclose($fh);
    $log("      Nozare: ievietoti $naceCount NACE ieraksti");

    // --- Nodokļu dati: NACE ffill + jaunākais gads + izmaiņas pret iepriekšējo rindu ---
    $log('      Nozare: apstrādā VID nodokļu datus ...');
    $currentYear = (int)date('Y');
    $tax = []; // reg => [nace, gads, vsaoi_raw, emp, emp_change, prev_emp, sal_change, prev_sal]
    $q = $ur->query('SELECT Registracijas_kods, Pamatdarbibas_NACE_kods, Taksacijas_gads,
                            Taja_skaita_VSAOI, Videjais_nodarbinato_personu_skaits_cilv
                     FROM pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata
                     ORDER BY Registracijas_kods, Taksacijas_gads, rowid');
    $curReg = null; $ffillNace = null; $prevEmp = null; $prevNet = null;
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = trim((string)$r[0]);
        if ($reg !== $curReg) { $curReg = $reg; $ffillNace = null; $prevEmp = null; $prevNet = null; }
        $nace = $r[1] === null ? null : trim((string)$r[1]);
        if ($nace === '?' || $nace === null) $nace = $ffillNace; // '?' -> NaN -> ffill
        else $ffillNace = $nace;
        $gads = (int)(noz_num($r[2]) ?? 0);
        $emp = noz_num($r[4]) ?? 0.0;
        $vsaoi = noz_vsaoi_val($r[3]);
        // Alga tikai >=3 darbiniekiem — mazākiem "vidējā" ir konkrētas personas alga (privātums).
        $gross = ($emp >= 3 && $vsaoi > 0) ? ($vsaoi / 0.3409) / $emp / 12 : 0.0;
        // Īstais neto arī prev vērtībai — 0.70 aproksimācija pret īsto neto radīja mākslīgu izmaiņu %
        $net = $gross <= 0 ? 0.0
            : ($net70 ? $gross * 0.70 : calculate_net_salary($gross, $gads !== 0 ? $gads : $currentYear)['net_salary']);
        $empChange = ($prevEmp !== null && $prevEmp > 0) ? (($emp - $prevEmp) / $prevEmp) * 100 : null;
        $salChange = ($prevNet !== null && $prevNet > 0) ? (($net - $prevNet) / $prevNet) * 100 : null;

        if (!isset($tax[$reg]) || $gads > $tax[$reg]['gads']) {
            $naceNp = $nace === null ? null : trim(str_replace('.', '', $nace));
            $tax[$reg] = ['nace' => $naceNp, 'gads' => $gads, 'vsaoi_raw' => $r[3],
                'emp' => $emp, 'emp_change' => $empChange, 'prev_emp' => $prevEmp,
                'sal_change' => $salChange, 'prev_sal' => $prevNet];
        }
        $prevEmp = $emp;
        $prevNet = $net;
    }
    $log('      Nozare: nodokļu dati ' . count($tax) . ' uzņēmumiem');

    // --- Ceturkšņu VID dati: 2 jaunākie kvartāli katram uzņēmumam ---
    // Darbinieku skaits + Vid. Neto Alga tiek ņemti no JAUNĀKĀ ceturkšņa (pdb_..._cet),
    // nevis gada datiem — svaigāki. Glabājam arī iepriekšējo kvartālu izmaiņu %-am.
    $log('      Nozare: apstrādā ceturkšņu VID datus (Darbinieki + Vid. Neto Alga) ...');
    $qtax = []; // reg => [q1key,q1emp,q1vsaoi, q2key,q2emp,q2vsaoi]  (q1=jaunākais)
    $q = $ur->query('SELECT Registracijas_kods, Taksacijas_gads_ceturksnis,
                            Taja_skaita_VSAOI_summa, Videjais_nodarbinato_personu_skaits_cilv
                     FROM pdb_samaksato_nodoklu_kopsummas_cet');
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = trim((string)$r[0]);
        if ($reg === '') continue;
        $qkey = noz_quarter_key($r[1] === null ? null : (string)$r[1]);
        if ($qkey === null) continue;
        $emp = noz_num($r[3]) ?? 0.0;
        $vsaoiRaw = $r[2];
        if (!isset($qtax[$reg])) {
            $qtax[$reg] = ['q1key' => $qkey, 'q1emp' => $emp, 'q1vsaoi' => $vsaoiRaw,
                'q2key' => -1, 'q2emp' => 0.0, 'q2vsaoi' => null];
            continue;
        }
        $e = &$qtax[$reg];
        if ($qkey > $e['q1key']) {
            // Jauns jaunākais — vecais jaunākais nobīdās uz 2. vietu
            $e['q2key'] = $e['q1key']; $e['q2emp'] = $e['q1emp']; $e['q2vsaoi'] = $e['q1vsaoi'];
            $e['q1key'] = $qkey; $e['q1emp'] = $emp; $e['q1vsaoi'] = $vsaoiRaw;
        } elseif ($qkey !== $e['q1key'] && $qkey > $e['q2key']) {
            $e['q2key'] = $qkey; $e['q2emp'] = $emp; $e['q2vsaoi'] = $vsaoiRaw;
        }
        unset($e);
    }
    $q->closeCursor();
    $q = null;
    $log('      Nozare: ceturkšņu dati ' . count($qtax) . ' uzņēmumiem');

    // --- Finanšu pārskati: gada labākais (UGP prioritāte), izmaiņas, jaunākais gads + veselība ---
    $log('      Nozare: apstrādā finanšu pārskatus ...');
    $FIN_COLS = ['net_income', 'net_turnover', 'total_current_assets', 'inventories',
        'current_liabilities', 'non_current_liabilities', 'equity',
        'total_assets', 'interest_expenses', 'income_before_income_taxes'];
    $fs = []; // reg => [turnover, prev_turnover, turnover_change, profit, prev_profit, profit_change, emp_fs, health]
    $q = $ur->query("SELECT fs.legal_entity_registration_number, fs.year, fs.employees, fs.rounded_to_nearest,
                            inc.net_income, inc.net_turnover, inc.interest_expenses, inc.income_before_income_taxes,
                            b.total_current_assets, b.inventories, b.current_liabilities, b.non_current_liabilities,
                            b.equity, b.total_assets
                     FROM financial_statements fs
                     LEFT JOIN income_statements inc ON inc.statement_id = fs.id
                     LEFT JOIN balance_sheets b ON b.statement_id = fs.id
                     WHERE fs.year IS NOT NULL
                     ORDER BY fs.legal_entity_registration_number, fs.year,
                              (CASE WHEN UPPER(TRIM(COALESCE(fs.source_type,''))) = 'UGP' THEN 1 ELSE 2 END),
                              fs.rowid");
    $COL = ['reg' => 0, 'year' => 1, 'employees' => 2, 'rounded' => 3,
        'net_income' => 4, 'net_turnover' => 5, 'interest_expenses' => 6, 'income_before_income_taxes' => 7,
        'total_current_assets' => 8, 'inventories' => 9, 'current_liabilities' => 10,
        'non_current_liabilities' => 11, 'equity' => 12, 'total_assets' => 13];
    $curReg = null; $curYear = null; $prevVals = null; $lastVals = null;
    $finishFs = function () use (&$curReg, &$prevVals, &$lastVals, &$fs) {
        if ($curReg === null || $lastVals === null) return;
        $cur = $lastVals; $prev = $prevVals;
        $chg = function (?float $c, ?float $p): ?float {
            if ($p === null || abs($p) <= 0 || $c === null) return null;
            $v = (($c - $p) / abs($p)) * 100;
            return is_finite($v) ? $v : null;
        };
        $ratios = noz_ratios($cur['vals']);
        $health = '';
        foreach (NOZ_HEALTH_KEYS as $k) $health .= noz_health_symbol($k, $ratios[$k]);
        $fs[$curReg] = [
            'turnover' => $cur['vals']['net_turnover'], 'prev_turnover' => $prev['vals']['net_turnover'] ?? null,
            'turnover_change' => $chg($cur['vals']['net_turnover'], $prev['vals']['net_turnover'] ?? null),
            'profit' => $cur['vals']['net_income'], 'prev_profit' => $prev['vals']['net_income'] ?? null,
            'profit_change' => $chg($cur['vals']['net_income'], $prev['vals']['net_income'] ?? null),
            'emp_fs' => $cur['emp'], 'health' => $health,
        ];
    };
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = (string)$r[$COL['reg']];
        $year = (int)$r[$COL['year']];
        if ($reg !== $curReg) {
            $finishFs();
            $curReg = $reg; $curYear = null; $prevVals = null; $lastVals = null;
        }
        if ($year === $curYear) continue; // gada ietvaros ņem tikai pirmo (labāko) rindu
        $curYear = $year;
        $factor = 1;
        $rounded = strtoupper(trim((string)($r[$COL['rounded']] ?? '')));
        if ($rounded === 'THOUSANDS') $factor = 1000;
        elseif ($rounded === 'MILLIONS') $factor = 1000000;
        $vals = [];
        foreach ($FIN_COLS as $c) {
            $n = noz_num($r[$COL[$c]]);
            $vals[$c] = $n === null ? null : $n * $factor;
        }
        $prevVals = $lastVals;
        $lastVals = ['vals' => $vals, 'emp' => (int)(noz_num($r[$COL['employees']]) ?? 0.0)];
    }
    $finishFs();
    $log('      Nozare: finanšu dati ' . count($fs) . ' uzņēmumiem');

    // --- Aktīvie uzņēmumi + apvienošana + ievietošana ---
    $log('      Nozare: apvieno un ievieto uzņēmumus ...');
    $ins = $out->prepare('INSERT INTO companies (
            regcode, name, nace_code_np,
            turnover, turnover_change, prev_turnover,
            employees, employees_change, prev_employees,
            profit, profit_change, prev_profit,
            avg_gross_salary, avg_net_salary, salary_change, prev_salary,
            registered_date, financial_health_string, location, change_period, name_sort
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $q = $ur->query("SELECT regcode, name, type, name_in_quotes, registered, address FROM register
                     WHERE (closed IS NULL OR closed NOT IN ('L','R'))
                       AND (terminated IS NULL OR terminated IN ('', '0000-00-00'))
                     ORDER BY rowid");
    $healthList = []; // [nace_code_np, health] tikai tiem, kam health != 'nnnnnnnnnn' (ievietošanas secībā)
    $recoded = 0; // NACE 2.0 kodi, piekārtoti tuvākajam 2.1 prefiksa mezglam
    $out->beginTransaction();
    $count = 0;
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        [$regRaw, $name, $type, $niq, $registered, $address] = $r;
        $reg = trim((string)$regRaw);
        if ($reg === '') continue;
        $t = $tax[$reg] ?? null;
        $f = $fs[$reg] ?? null;

        $qd = $qtax[$reg] ?? null;

        // Neto algas aprēķins no ceturkšņa VSAOI (bruto: /3 mēneši), atgriež [gross, net] vai [null,null]
        $qSalary = function (float $emp, $vsaoiRaw, int $year) use ($net70): array {
            $vsaoi = noz_vsaoi_val($vsaoiRaw);
            if ($emp <= 0 || $vsaoi <= 0) return [null, null];
            $gross = ($vsaoi / 0.3409) / $emp / 3; // ceturksnis = 3 mēneši
            $net = $net70 ? $gross * 0.70 : calculate_net_salary($gross, $year)['net_salary'];
            return [$gross, $net];
        };

        $employees = 0; $empChange = null; $prevEmpOut = null;
        $avgGross = null; $avgNet = null; $salChange = null; $prevSalOut = null;
        $changePeriod = null; // pret ko ir employees_change/salary_change: 'cet' vai 'gads'

        if ($qd !== null && $qd['q1emp'] > 0) {
            // --- DARBINIEKI un VID. NETO ALGA no JAUNĀKĀ ceturkšņa (VID cet dati) ---
            $employees = (int)round($qd['q1emp']);
            [$avgGross, $avgNet] = $qSalary($qd['q1emp'], $qd['q1vsaoi'], noz_quarter_year($qd['q1key']));
            // Izmaiņas pret IEPRIEKŠĒJO pieejamo ceturksni
            if ($qd['q2key'] >= 0 && $qd['q2emp'] > 0) {
                $prevEmpOut = (int)round($qd['q2emp']);
                $empChange = (($qd['q1emp'] - $qd['q2emp']) / $qd['q2emp']) * 100;
                $changePeriod = 'cet';
                [, $prevNet] = $qSalary($qd['q2emp'], $qd['q2vsaoi'], noz_quarter_year($qd['q2key']));
                if ($prevNet !== null) {
                    $prevSalOut = $prevNet;
                    if ($avgNet !== null && $prevNet > 0) $salChange = (($avgNet - $prevNet) / $prevNet) * 100;
                }
            }
        } else {
            // --- REZERVE: nav ceturkšņu datu -> vecā loģika (FS/gada dati) ---
            $empFs = $f['emp_fs'] ?? null;
            $employees = ($empFs !== null && $empFs > 0) ? $empFs : (int)round($t !== null ? $t['emp'] : 0.0);
            if ($t !== null) {
                $vsaoi = noz_vsaoi_val($t['vsaoi_raw']);
                if ($t['emp'] > 0 && $vsaoi > 0) {
                    $avgGross = ($vsaoi / 0.3409) / $t['emp'] / 12; // gada dati = 12 mēneši
                    $year = $t['gads'] !== 0 ? $t['gads'] : $currentYear;
                    $avgNet = $net70 ? $avgGross * 0.70 : calculate_net_salary($avgGross, $year)['net_salary'];
                }
                $empChange = $t['emp_change'];
                $prevEmpOut = $t['prev_emp'] !== null ? (int)round($t['prev_emp']) : null;
                $salChange = $t['sal_change'];
                $prevSalOut = $t['prev_sal'];
                if ($empChange !== null || $salChange !== null) $changePeriod = 'gads';
            }
        }

        $naceNp = ($t !== null && $t['nace'] !== null) ? $t['nace'] : 'UNDEFINED';
        // NACE 2.0 kods, kura vairs nav 2.1 kokā -> tuvākais eksistējošais prefiksa mezgls
        // (2.0->2.1 sadalījumi ir 1:N, tāpēc precīza pārkodēšana uzņēmuma līmenī nav iespējama)
        if ($naceNp !== 'UNDEFINED' && !isset($nodeNp[$naceNp])) {
            $p = $naceNp;
            while ($p !== '' && !isset($nodeNp[$p])) $p = substr($p, 0, -1);
            $naceNp = $p !== '' ? $p : 'UNDEFINED';
            $recoded++;
        }
        $health = $f['health'] ?? 'nnnnnnnnnn';
        $location = trim(explode(',', (string)($address ?? ''), 2)[0]);
        $fmtName = sect_format_name($type, $niq, $name);

        $ins->execute([
            $reg,
            $fmtName,
            $naceNp,
            $f['turnover'] ?? null, $f['turnover_change'] ?? null, $f['prev_turnover'] ?? null,
            $employees,
            $empChange,
            $prevEmpOut,
            $f['profit'] ?? null, $f['profit_change'] ?? null, $f['prev_profit'] ?? null,
            $avgGross, $avgNet,
            $salChange,
            $prevSalOut,
            $registered === null ? null : (string)$registered,
            $health,
            $location,
            $changePeriod,
            noz_name_sort_key($fmtName),
        ]);
        if ($health !== 'nnnnnnnnnn') $healthList[] = [$naceNp, $health];
        $count++;
        if ($count % 20000 === 0) { $out->commit(); $out->beginTransaction(); }
    }
    $out->commit();
    unset($tax, $fs);
    $log("      Nozare: ievietoti $count uzņēmumi ($recoded ar NACE 2.0 kodu piekārtoti 2.1 prefiksam)");

    // --- NACE nozaru veselības agregācija ---
    // Katrai nozarei ņem uzņēmumus, kuru nace_code_np sākas ar kodu (bez punktiem), un katrā no
    // 10 pozīcijām izvēlas biežāko simbolu (izņemot 'n'); neizšķirts -> pirmais sastaptais.
    // Burtu sekcijām (A..U) prefiksu saraksts = to bērnu (nodaļu) kodi — burts pats ne ar ko nesakrīt.
    $log('      Nozare: agregē nozaru veselības rādītājus ...');
    $codes = $out->query('SELECT code FROM nace ORDER BY rowid')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('UNDEFINED', $codes, true)) {
        $out->exec("INSERT OR IGNORE INTO nace (code, name, level, health_string) VALUES ('UNDEFINED', 'Nedefinēta nozare', 1, 'nnnnnnnnnn')");
        $codes[] = 'UNDEFINED';
    }
    $letterChildren = []; // burtu sekcija => [bērnu kodi bez punktiem]
    foreach ($out->query('SELECT code, parent_code FROM nace') as $lcRow) {
        $par = trim((string)($lcRow['parent_code'] ?? ''));
        if ($par !== '' && !ctype_digit(str_replace('.', '', $par))) {
            $letterChildren[$par][] = str_replace('.', '', (string)$lcRow['code']);
        }
    }
    // Statistika pa precīzajiem uzņēmumu kodiem: stats[kods][poz][simbols] = [skaits, pirmais_indekss]
    $stats = [];
    foreach ($healthList as $i => [$code, $health]) {
        if (strlen($health) !== 10) continue;
        if (!isset($stats[$code])) $stats[$code] = [];
        for ($p = 0; $p < 10; $p++) {
            $ch = $health[$p];
            if ($ch === 'n') continue;
            if (!isset($stats[$code][$p][$ch])) $stats[$code][$p][$ch] = [0, $i];
            $stats[$code][$p][$ch][0]++;
        }
    }
    unset($healthList);
    $statCodes = array_keys($stats);
    $upd = $out->prepare('UPDATE nace SET health_string = ? WHERE code = ?');
    $out->beginTransaction();
    foreach ($codes as $code) {
        $stripped = str_replace('.', '', (string)$code);
        $prefixes = ($code !== 'UNDEFINED' && !ctype_digit($stripped))
            ? ($letterChildren[(string)$code] ?? [])
            : [$stripped];
        $matched = [];
        foreach ($statCodes as $sc) {
            foreach ($prefixes as $pf) {
                if (str_starts_with((string)$sc, $pf)) { $matched[] = $sc; break; }
            }
        }
        if (empty($matched)) { $upd->execute(['nnnnnnnnnn', $code]); continue; }
        $res = '';
        for ($p = 0; $p < 10; $p++) {
            $agg = []; // simbols => [skaits, min_indekss]
            foreach ($matched as $sc) {
                foreach ($stats[$sc][$p] ?? [] as $ch => [$cnt, $first]) {
                    if (!isset($agg[$ch])) $agg[$ch] = [0, $first];
                    $agg[$ch][0] += $cnt;
                    if ($first < $agg[$ch][1]) $agg[$ch][1] = $first;
                }
            }
            if (empty($agg)) { $res .= 'n'; continue; }
            $bestCh = null; $bestCnt = -1; $bestFirst = PHP_INT_MAX;
            foreach ($agg as $ch => [$cnt, $first]) {
                if ($cnt > $bestCnt || ($cnt === $bestCnt && $first < $bestFirst)) {
                    $bestCh = $ch; $bestCnt = $cnt; $bestFirst = $first;
                }
            }
            $res .= $bestCh;
        }
        $upd->execute([$res, $code]);
    }
    $out->commit();
    $log('      Nozare: atjaunotas ' . count($codes) . ' nozares; katalogs.sqlite gatavs');
    $out = null;
}
