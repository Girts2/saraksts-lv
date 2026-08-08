<?php
// server/build/section_pensionars.php — "Pensionārs" sadaļas būve (ports of Pensionārs/db_generator.py).
// Ģenerē pensionars/pensionari.sqlite tieši no ur_data.db + NACE.csv.
//
// Atlases loģika (kā Python): aktīvi uzņēmumi bez akcionāriem, ar tieši 1 dalībnieku,
// tieši 1 patiesā labuma guvēju, kura personas koda prefikss norāda dzimšanu 1930–1965,
// kurš vienlaikus ir amatpersona; dibināti pirms 2016; ar apgrozījumu, darbiniekiem un peļņu.
//
// Piezīme: Python NEpiemēro rounded_to_nearest reizinātāju finanšu vērtībām (atšķirībā no
// Nozares) — ports to saglabā apzināti, lai izvade sakristu.
//
// APZINĀTAS NOVIRZES no Python oriģināla (labojumi 2026-07-24 audita rezultātā):
//   1. Negatīvs pašu/ieguldītais kapitāls forsē D/E, ROE, ROCE veselības lampiņu SARKANU
//      (Python rādīja maldinoši zaļu, jo koeficienta zīme apgriežas).
//   2. Dibināšanas datums tiek validēts ar checkdate() (noraida 0000-00-00 u.c.).
//   3. owner_name / owner_pk (PLG personas dati) NETIEK glabāti izvades DB (GDPR).
declare(strict_types=1);

require_once __DIR__ . '/section_common.php';
require_once __DIR__ . '/../lib/nace_crosswalk.php'; // NACE_2_0_TO_2_1 (kopīga ar Reģistru)

/** pandas to_numeric(errors='coerce').fillna(0) analogs. */
function pens_num0($v): float {
    if ($v === null) return 0.0;
    if (is_int($v) || is_float($v)) return (float)$v;
    $t = trim((string)$v);
    return ($t === '' || !is_numeric($t)) ? 0.0 : (float)$t;
}

/** Nozaru vērtēšanas reizinātāji (NACE 2 ciparu prefikss). */
const PENS_SECTOR_MULTIPLIERS = [
    '01' => 4.0, '02' => 4.0, '03' => 3.5, '05' => 3.5, '06' => 3.5, '07' => 3.5, '08' => 3.5, '09' => 4.0,
    '10' => 3.5, '11' => 3.5, '12' => 3.0, '13' => 3.0, '14' => 3.0, '15' => 3.0, '16' => 3.5, '17' => 3.5, '18' => 3.0,
    '19' => 4.0, '20' => 4.5, '21' => 6.0, '22' => 3.5, '23' => 3.5, '24' => 3.5, '25' => 3.5, '26' => 5.5, '27' => 4.5,
    '28' => 4.0, '29' => 3.5, '30' => 3.5, '31' => 3.5, '32' => 3.5, '33' => 3.5, '35' => 5.0, '36' => 4.5, '37' => 4.5,
    '38' => 4.5, '39' => 4.5, '41' => 2.5, '42' => 2.5, '43' => 2.5, '45' => 3.0, '46' => 3.5, '47' => 3.0, '49' => 3.5,
    '50' => 3.5, '51' => 3.5, '52' => 4.0, '53' => 3.5, '55' => 2.5, '56' => 2.5, '58' => 5.0, '59' => 4.5, '60' => 4.5,
    '61' => 5.5, '62' => 6.5, '63' => 6.0, '64' => 4.0, '65' => 4.0, '66' => 4.0, '68' => 5.0, '69' => 4.0, '70' => 4.0,
    '71' => 4.0, '72' => 5.0, '73' => 3.5, '74' => 3.5, '75' => 4.5, '77' => 4.0, '78' => 3.5, '79' => 3.0, '80' => 3.0,
    '81' => 3.0, '82' => 3.0, '84' => 3.0, '85' => 3.5, '86' => 5.0, '87' => 4.0, '88' => 3.5, '90' => 3.0, '91' => 3.0,
    '92' => 7.0, '93' => 3.5, '94' => 3.0, '95' => 3.0, '96' => 2.5,
];

function pens_valuation_multiplier($nace_code): float {
    if ($nace_code === null || trim((string)$nace_code) === '') return 3.0;
    $code = str_replace('.', '', trim((string)$nace_code));
    if (strlen($code) >= 2) return PENS_SECTOR_MULTIPLIERS[substr($code, 0, 2)] ?? 3.0;
    return 3.0;
}

/** NACE apraksts hierarhiski: precīzais kods → grupa (3-cip.) → nodaļa (2-cip.).
 *  VID nodokļu dati mēdz lietot NACE 2.0 kodus (piem. 5610, 4120, 4719), kas NACE 2.1
 *  klasifikatorā ($naceDesc) kā 4-ciparu klase neeksistē, bet vecāka līmeņa kods gandrīz
 *  vienmēr ir. Vecos 2.0 kodus ar deterministisku 2.1 pēcteci (NACE_2_0_TO_2_1, kopīga ar
 *  Reģistru) vispirms pārkartē precīzi; sadalījumi paliek grupas līmenī. */
function pens_nace_desc(array $naceDesc, $nace): ?string {
    if ($nace === null) return null;
    $c = str_replace('.', '', trim((string)$nace));
    if ($c === '') return null;
    $c = NACE_2_0_TO_2_1[$c] ?? $c;
    foreach ([$c, substr($c, 0, 3), substr($c, 0, 2)] as $lookup) {
        if (strlen($lookup) < 2) continue;
        if (isset($naceDesc[$lookup])) return $naceDesc[$lookup];
    }
    return null;
}

/** safe_divide (Pensionāra variants — bez rezultāta isfinite pārbaudes). */
function pens_safe_divide(float $n, float $d): ?float {
    if ($d == 0.0 || !is_finite($d)) return null;
    return $n / $d;
}

/** Finanšu rādītāji (Pensionāra variants; vērtības BEZ reizinātāja, null -> 0). */
function pens_ratios(array $v): array {
    $g = fn(string $k): float => (float)($v[$k] ?? 0.0);
    $ratios = [];
    $ratios['current_ratio'] = pens_safe_divide($g('total_current_assets'), $g('current_liabilities'));
    $ratios['quick_ratio'] = pens_safe_divide($g('total_current_assets') - $g('inventories'), $g('current_liabilities'));
    $ratios['net_profit_margin'] = pens_safe_divide($g('net_income') * 100, $g('net_turnover'));
    $ratios['roa'] = pens_safe_divide($g('net_income') * 100, $g('total_assets'));
    $ratios['roe'] = pens_safe_divide($g('net_income') * 100, $g('equity'));
    $total_liabilities = $g('current_liabilities') + $g('non_current_liabilities');
    $ratios['debt_to_equity'] = pens_safe_divide($total_liabilities, $g('equity'));
    $ratios['asset_turnover'] = pens_safe_divide($g('net_turnover'), $g('total_assets'));
    $ebit = $g('income_before_income_taxes') + $g('interest_expenses');
    $ratios['roce'] = pens_safe_divide($ebit * 100, $g('total_assets') - $g('current_liabilities'));
    $ratios['interest_coverage'] = pens_safe_divide($ebit, $g('interest_expenses'));
    $A = pens_safe_divide($g('total_current_assets') - $g('current_liabilities'), $g('total_assets'));
    $B = pens_safe_divide($g('equity'), $g('total_assets'));
    $C = pens_safe_divide($ebit, $g('total_assets'));
    $D = pens_safe_divide($g('equity'), $total_liabilities);
    $E = pens_safe_divide($g('net_turnover'), $g('total_assets'));
    $ratios['altman_z_score'] = ($A !== null && $B !== null && $C !== null && $D !== null && $E !== null)
        ? 0.717 * $A + 0.847 * $B + 3.107 * $C + 0.420 * $D + 0.998 * $E : null;
    return $ratios;
}

/** Veselības simbols (Pensionāra sliekšņi — pie robežvērtībām atšķiras no Nozares!). */
function pens_health_symbol(string $key, ?float $v): string {
    if ($v === null || !is_finite($v)) return 'n';
    switch ($key) {
        case 'current_ratio':     return $v < 1.0 ? 'r' : ($v < 1.5 ? 'o' : ($v <= 3.0 ? 'g' : 'b'));
        case 'quick_ratio':       return $v < 0.8 ? 'r' : ($v < 1.0 ? 'o' : ($v <= 2.0 ? 'g' : 'b'));
        case 'net_profit_margin': return $v < 0 ? 'r' : ($v < 10 ? 'o' : 'g');
        case 'roa':               return $v < 2 ? 'r' : ($v < 5 ? 'o' : 'g');
        case 'roe':               return $v < 5 ? 'r' : ($v < 15 ? 'o' : 'g');
        case 'debt_to_equity':    return $v < 1.5 ? 'g' : ($v <= 2.0 ? 'o' : 'r');
        case 'asset_turnover':    return $v < 0.5 ? 'r' : ($v < 1.0 ? 'o' : 'g');
        case 'roce':              return $v < 5 ? 'r' : ($v < 15 ? 'o' : 'g');
        case 'interest_coverage': return $v < 1.5 ? 'r' : ($v < 3.0 ? 'o' : 'g');
        case 'altman_z_score':    return $v < 1.23 ? 'r' : ($v < 2.90 ? 'o' : 'g');
    }
    return 'n';
}

const PENS_HEALTH_KEYS = ['current_ratio', 'quick_ratio', 'net_profit_margin', 'roa', 'roe',
    'debt_to_equity', 'asset_turnover', 'roce', 'interest_coverage', 'altman_z_score'];

/** Personas koda prefikss = dzimis 1930–1965 (kā Python is_valid_pk_prefix). */
function pens_valid_pk($pk): bool {
    if (!is_string($pk) || !str_contains($pk, '-')) return false;
    $prefix = explode('-', $pk)[0];
    if (strlen($prefix) !== 6 || !ctype_digit($prefix)) return false;
    $day = (int)substr($prefix, 0, 2);
    $yy = (int)substr($prefix, 4, 2);
    return $day >= 1 && $day <= 31 && $yy >= 30 && $yy <= 65;
}

/** Python json.dumps([{'y': int, 'v': num}]) formāts (ar atstarpēm aiz ':' un ','). */
function pens_history_json(array $items): string {
    if (empty($items)) return '[]';
    $parts = [];
    foreach ($items as [$y, $v]) {
        $vs = is_int($v) ? (string)$v : json_encode((float)$v); // json_encode float saglabā .0
        $parts[] = '{"y": ' . $y . ', "v": ' . $vs . '}';
    }
    return '[' . implode(', ', $parts) . ']';
}

/**
 * Uzbūvē Pensionāra pensionari.sqlite.
 *
 * @param PDO           $ur       ur_data.db
 * @param string        $nace_csv NACE.csv klasifikators
 * @param string        $out_db   izvades SQLite (staging/pensionari.sqlite)
 * @param callable|null $log
 */
function build_section_pensionars(PDO $ur, string $nace_csv, string $out_db, ?callable $log = null): void {
    $log = $log ?? function ($m) {};
    ini_set('precision', '17');
    if (!is_file($nace_csv)) throw new RuntimeException("Pensionārs: trūkst $nace_csv");

    // --- NACE apraksti (kods bez punktiem -> nosaukums) ---
    $naceDesc = [];
    $fh = fopen($nace_csv, 'r');
    if ($fh === false) throw new RuntimeException("Pensionārs: nevar atvērt $nace_csv");
    $first = fgets($fh);
    $first = preg_replace('/^\xEF\xBB\xBF/', '', rtrim((string)$first, "\r\n"));
    $header = str_getcsv($first, ',', '"', '');
    $idx = array_flip($header);
    while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if (count($row) !== count($header)) continue;
        $code = str_replace('.', '', (string)$row[$idx['Kods']]);
        if ($code !== '' && !isset($naceDesc[$code])) $naceDesc[$code] = (string)$row[$idx['Nosaukums']];
    }
    fclose($fh);

    // --- Izslēgšanas kopas ---
    $log('      Pensionārs: veido filtrus (akcionāri, dalībnieki, PLG, amatpersonas) ...');
    $stockholderRegs = [];
    foreach ($ur->query("SELECT DISTINCT at_legal_entity_registration_number FROM stockholders
                         WHERE at_legal_entity_registration_number != ''") as $r) {
        $stockholderRegs[(string)$r[0]] = true;
    }
    $multiMemberRegs = [];
    foreach ($ur->query("SELECT at_legal_entity_registration_number FROM members
                         WHERE at_legal_entity_registration_number != ''
                         GROUP BY at_legal_entity_registration_number HAVING COUNT(*) > 1") as $r) {
        $multiMemberRegs[(string)$r[0]] = true;
    }

    // --- PLG: skaits pilnajā tabulā + derīgie (vecuma prefikss) ---
    // GDPR: glabājam TIKAI maskēto personas kodu (amatpersonu-filtram) — vārds/uzvārds
    // izvades DB vairs nenonāk, tāpēc tos vairs neizvelkam.
    $boCount = [];   // reg => rindu skaits beneficial_owners
    $boPk = [];      // reg => derīgais maskētais pk (pirmā derīgā rinda faila secībā)
    $q = $ur->query('SELECT legal_entity_registration_number, latvian_identity_number_masked
                     FROM beneficial_owners ORDER BY rowid');
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = (string)$r[0];
        if ($reg === '') continue;
        $boCount[$reg] = ($boCount[$reg] ?? 0) + 1;
        if (!isset($boPk[$reg]) && pens_valid_pk($r[1])) {
            $boPk[$reg] = (string)$r[1];
        }
    }

    // --- Amatpersonu atslēgas (reg_pk) ---
    $officerKeys = [];
    $q = $ur->query("SELECT at_legal_entity_registration_number, latvian_identity_number_masked
                     FROM officers WHERE latvian_identity_number_masked IS NOT NULL");
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $officerKeys[(string)$r[0] . '_' . (string)$r[1]] = true;
    }

    // --- Reģistra caurskate: visi filtri + dibināšanas gads < 2016 ---
    $log('      Pensionārs: atlasa uzņēmumus ...');
    $final = []; // reg => [name, address, founded_year] reģistra secībā (PLG dati te NENONĀK)
    $q = $ur->query("SELECT regcode, name, type, name_in_quotes, registered, address FROM register
                     WHERE (closed IS NULL OR closed NOT IN ('L','R'))
                       AND (terminated IS NULL OR terminated IN ('', '0000-00-00'))
                     ORDER BY rowid");
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = (string)$r[0];
        if ($reg === '' || isset($final[$reg])) continue;
        if (isset($stockholderRegs[$reg]) || isset($multiMemberRegs[$reg])) continue;
        if (($boCount[$reg] ?? 0) !== 1 || !isset($boPk[$reg])) continue;
        $pk = $boPk[$reg]; // tikai amatpersonu-filtram; PLG dati NETIEK glabāti (GDPR, sk. zemāk)
        if (!isset($officerKeys[$reg . '_' . $pk])) continue;
        $registered = (string)($r[4] ?? '');
        // pd.to_datetime(coerce) analogs: noraida nederīgus datumus (0000-00-00, 2015-13-45 u.c.),
        // ko iepriekšējais regexs klusi pieņēma (founded_year=0 utt.).
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $registered, $m)) continue;
        if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) continue;
        $founded = (int)$m[1];
        if ($founded >= 2016) continue;
        // GDPR datu minimizācija: PLG vārds un maskētais personas kods kalpo TIKAI atlases
        // filtram ($pk augšā) — tos NEGLABĀ izvades DB, lai personas dati nenonāktu publicētajā
        // artefaktā (aizsardzība papildus .htaccess liegumam .sqlite failiem).
        $final[$reg] = ['name' => sect_format_name($r[2], $r[3], $r[1]), 'address' => $r[5],
            'founded' => $founded];
    }
    unset($stockholderRegs, $multiMemberRegs, $boCount, $boPk, $officerKeys);
    $log('      Pensionārs: pēc filtriem ' . count($final) . ' kandidāti');
    if (empty($final)) throw new RuntimeException('Pensionārs: netika atrasts neviens uzņēmums');

    // --- FS dati kandidātiem: vēsture (5 gadi) + jaunākais gads ---
    $ur->exec('DROP TABLE IF EXISTS temp.pens_regs');
    $ur->exec('CREATE TEMP TABLE pens_regs (reg TEXT PRIMARY KEY)');
    $ins = $ur->prepare('INSERT OR IGNORE INTO temp.pens_regs (reg) VALUES (?)');
    $ur->beginTransaction();
    foreach ($final as $reg => $_) $ins->execute([(string)$reg]);
    $ur->commit();

    $FIN_COLS = ['net_turnover', 'net_income', 'income_before_income_taxes', 'interest_expenses',
        'total_assets', 'total_non_current_assets', 'total_current_assets', 'inventories',
        'equity', 'provisions', 'non_current_liabilities', 'current_liabilities', 'employees'];
    $q = $ur->query("SELECT fs.legal_entity_registration_number, fs.year,
                            inc.net_turnover, inc.net_income, inc.income_before_income_taxes, inc.interest_expenses,
                            b.total_assets, b.total_non_current_assets, b.total_current_assets, b.inventories,
                            b.equity, b.provisions, b.non_current_liabilities, b.current_liabilities,
                            fs.employees
                     FROM financial_statements fs
                     JOIN temp.pens_regs p ON p.reg = fs.legal_entity_registration_number
                     LEFT JOIN income_statements inc ON inc.statement_id = fs.id
                     LEFT JOIN balance_sheets b ON b.statement_id = fs.id
                     WHERE fs.source_type IS NULL OR fs.source_type != 'UKGP'
                     ORDER BY fs.legal_entity_registration_number, fs.year DESC, fs.rowid");
    $COL = ['reg' => 0, 'year' => 1, 'net_turnover' => 2, 'net_income' => 3, 'income_before_income_taxes' => 4,
        'interest_expenses' => 5, 'total_assets' => 6, 'total_non_current_assets' => 7, 'total_current_assets' => 8,
        'inventories' => 9, 'equity' => 10, 'provisions' => 11, 'non_current_liabilities' => 12,
        'current_liabilities' => 13, 'employees' => 14];
    $fsLatest = [];  // reg => vals (jaunākā gada rinda; gads dilstoši -> pirmā rinda)
    $fsHist = [];    // reg => ['t' => [[y,v]..], 'p' => .., 'e' => ..]
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = (string)$r[$COL['reg']];
        $year = (int)pens_num0($r[$COL['year']]);
        $vals = [];
        foreach ($FIN_COLS as $c) $vals[$c] = pens_num0($r[$COL[$c]]);
        if (!isset($fsLatest[$reg])) {
            $vals['year'] = $year;
            $fsLatest[$reg] = $vals;
            $fsHist[$reg] = ['t' => [], 'p' => [], 'e' => []];
        }
        $h = &$fsHist[$reg];
        if ($vals['net_turnover'] != 0.0 && count($h['t']) < 5) $h['t'][] = [$year, $vals['net_turnover']];
        if ($vals['net_income'] != 0.0 && count($h['p']) < 5) $h['p'][] = [$year, $vals['net_income']];
        if ($vals['employees'] != 0.0 && count($h['e']) < 5) $h['e'][] = [$year, (int)$vals['employees']];
        unset($h);
    }
    $q->closeCursor();
    $q = null;
    $ur->exec('DROP TABLE IF EXISTS temp.pens_regs');
    $log('      Pensionārs: finanšu dati ' . count($fsLatest) . ' kandidātiem');

    // --- VID nodokļu dati: NACE ffill + jaunākais gads (kandidātiem) ---
    $taxNace = []; // reg => nace_code (ar ffill; bez punktu noņemšanas — kā Python)
    $q = $ur->query('SELECT Registracijas_kods, Pamatdarbibas_NACE_kods, Taksacijas_gads
                     FROM pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata
                     ORDER BY Registracijas_kods, Taksacijas_gads, rowid');
    $curReg = null; $ffill = null; $bestGads = null;
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = (string)$r[0];
        if ($reg !== $curReg) { $curReg = $reg; $ffill = null; $bestGads = null; }
        $nace = $r[1] === null ? null : trim((string)$r[1]);
        if ($nace === '?' || $nace === null) $nace = $ffill;
        else $ffill = $nace;
        $gads = (int)pens_num0($r[2]);
        if (!isset($final[$reg])) continue;
        if ($bestGads === null || $gads > $bestGads) {
            $bestGads = $gads;
            $taxNace[$reg] = $nace;
        }
    }

    // --- Nodokļu maksātāju reitings ---
    $rating = [];
    foreach ($ur->query('SELECT Registracijas_kods, Reitings FROM reitings_uznemumi ORDER BY rowid') as $r) {
        $reg = (string)$r[0];
        if ($reg !== '' && !isset($rating[$reg])) $rating[$reg] = $r[1];
    }

    // --- Izvades DB ---
    if (is_file($out_db)) @unlink($out_db);
    @mkdir(dirname($out_db), 0775, true);
    $out = new PDO('sqlite:' . $out_db);
    $out->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $out->exec('PRAGMA journal_mode = OFF');
    $out->exec('PRAGMA synchronous = OFF');
    $out->exec('CREATE TABLE companies (
            regcode TEXT PRIMARY KEY, name TEXT, location TEXT, turnover REAL,
            employees INTEGER, profit REAL, founded_year INTEGER,
            nace_description TEXT,
            financial_health_string TEXT,
            balance_year INTEGER,
            total_assets REAL,
            total_non_current_assets REAL,
            total_current_assets REAL,
            equity REAL,
            provisions REAL,
            non_current_liabilities REAL,
            current_liabilities REAL,
            valuation_multiplier REAL,
            history_turnover TEXT,
            history_profit TEXT,
            history_employees TEXT,
            tax_rating TEXT
        )');
    $insC = $out->prepare('INSERT INTO companies (
            regcode, name, location, turnover, employees, profit, founded_year,
            nace_description, financial_health_string, balance_year,
            total_assets, total_non_current_assets, total_current_assets, equity, provisions,
            non_current_liabilities, current_liabilities, valuation_multiplier,
            history_turnover, history_profit, history_employees, tax_rating
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

    $out->beginTransaction();
    $count = 0;
    foreach ($final as $reg => $info) {
        $v = $fsLatest[$reg] ?? null;
        if ($v === null) continue; // nav FS -> turnover/employees/profit = 0 -> filtrs izmet
        // Datu filtrs: apgrozījums, darbinieki un peļņa != 0
        if ($v['net_turnover'] == 0.0 || $v['employees'] == 0.0 || $v['net_income'] == 0.0) continue;

        $ratios = pens_ratios($v);
        // Negatīvs saucējs padara koeficienta ZĪMI maldinošu: negatīvs pašu kapitāls dod
        // negatīvu (t.i. "zemu" = zaļu) D/E un apgrieztu ROE; negatīvs ieguldītais kapitāls
        // apgriež ROCE. Faktiski negatīvs pašu kapitāls = maksātnespēja — forsējam sarkanu.
        // (Altman Z rāmi neaiztiekam — tas ir nosaukts akadēmisks modelis ar savu formulu.)
        $eqNeg = ((float)($v['equity'] ?? 0.0)) < 0.0;
        $capEmployedNeg = ((float)($v['total_assets'] ?? 0.0) - (float)($v['current_liabilities'] ?? 0.0)) < 0.0;
        $health = '';
        foreach (PENS_HEALTH_KEYS as $k) {
            $sym = pens_health_symbol($k, $ratios[$k]);
            if ($sym !== 'n') {
                if (($k === 'debt_to_equity' || $k === 'roe') && $eqNeg) $sym = 'r';
                elseif ($k === 'roce' && $capEmployedNeg) $sym = 'r';
            }
            $health .= $sym;
        }

        // Python apvieno tax kodu KĀ IR ar NACE kodiem bez punktiem (VID kodi jau ir bez punktiem)
        $nace = $taxNace[$reg] ?? null;
        $location = trim(explode(',', (string)($info['address'] ?? ''), 2)[0]);
        $h = $fsHist[$reg];

        $insC->execute([
            (string)$reg, $info['name'], $location,
            $v['net_turnover'], $v['employees'], $v['net_income'],
            $info['founded'],
            pens_nace_desc($naceDesc, $nace),
            $health, $v['year'],
            $v['total_assets'], $v['total_non_current_assets'], $v['total_current_assets'],
            $v['equity'], $v['provisions'], $v['non_current_liabilities'], $v['current_liabilities'],
            pens_valuation_multiplier($nace),
            pens_history_json($h['t']), pens_history_json($h['p']), pens_history_json($h['e']),
            $rating[$reg] ?? null,
        ]);
        $count++;
    }
    $out->commit();
    $log("      Pensionārs: ievietoti $count uzņēmumi; pensionari.sqlite gatavs");
    $out = null;
}
