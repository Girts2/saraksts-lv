<?php
// server/build/section_struktura.php — "Struktūra" sadaļas būve: Latvijas uzņēmumu TREEMAP.
// 2026-07-15 pilnīga pārbūve: vecais mātes-meitas D3 grafs aizstāts ar nozaru (NACE 2.1) treemap.
// 2026-07-15 (2. kārta): 7 metrikas (apgrozījums/peļņa/aktīvi/pamatkapitāls/marža/darbinieki/
// bruto alga), likvidācijas procesā esošie izslēgti, uzņēmumi bez peļņas datiem izslēgti
// (krāsai vienmēr vajadzīgs net_income), nodaļu kopsummas pa metrikām + pos/neg mozaīkai.
//
// Ģenerē:
//   - struktura.php lapu (statisks šablons; dati nāk no JSON ar lazy ielādi),
//   - <data>/overview.json  (sekcijas + nodaļas ar metriku kopsummām + TOP-40 uzņēmumi katrā),
//   - <data>/div_XX.json    (viens fails katrai NACE nodaļai: klases + VISI nodaļas uzņēmumi).
//
// Atlase: aktīvi (closed/terminated tukši) + nav reorganizēti prom (reorganizations.source_entity)
// + NAV likvidācijas procesā (liquidations) + jaunākais gada pārskats pēdējos 3 pārskatu gados
// (jaunākais pilnais gads = MAX(year) ar >=5000 iesniedzējiem) + apgrozījums > 0 + net_income
// nav null. rounded_to_nearest reizinātājs piemērots naudas vērtībām (apgrozījums/peļņa/aktīvi).
//
// Uzņēmuma rinda: [reg, name, t, p, a, k, e, s, cls, year, bal, t2, p2, e2, age, rtg, rsk, rg, fm,
//                  vat, ef, vq]   (vq = 1, ja e un s nāk no VID ceturkšņiem, 0 = gada dati)
//   t=apgrozījums €, p=peļņa €, a=aktīvi € (var būt null), k=pamatkapitāls € (var būt null),
//   e=darbinieki — SVAIGĀKAIS pieejamais: VID jaunākais ceturksnis > gada pārskats > VID gads,
//   ef=darbinieki PĀRSKATA periodā (fs.employees vai VID gads) — tikai salīdzinājumiem ar e2
//     (darbinieku dinamika) un produktivitātei, lai periodi sakristu; var būt null,
//   s=bruto alga €/mēn no VID CETURKŠŅU datiem: pēdējo SEKT_SALARY_QUARTERS ceturkšņu VSAOI
//     summa / 0.3409 / (Σ darbinieki × 3 mēn.); ja ceturkšņu datu nav — VID gada dati (/12),
//   bal=14 bilances posteņi (SEKT_BAL_COLS secībā, € ar round faktoru; null ja nav/0; pašu
//   kapitāls (idx 9) saglabā arī negatīvu vērtību) vai null, ja visa bilance tukša,
//   t2/p2/e2=apgrozījums/peļņa/darbinieki no TIEŠI iepriekšējā gada pārskata (year-1; null ja nav),
//   age=vecums gados (no register.registered; null ja nav), rtg=VID reitings A/B/C/J/N (null ja nav),
//   rsk=riska bitu maska: 1=saimn. darbības apturēšana, 2=nodrošinājuma līdzekļi, 4=AKTĪVS
//   maksātnespējas process (0 = tīrs).
//   Frontenda "Bilance" izvēlne izmēra blokus pēc bilances posteņiem; krāsu režīmi izmanto
//   t2/p2/e2/age/rtg/rsk un nodaļas 'cs' daļas (skat. SEKT_CS_* komentāru pie aprēķina).
//
// NACE: VID Pamatdarbibas_NACE_kods (ffill '?', jaunākā gada rinda) -> NACE 2.1 klase; rezerves
// ķēde klase -> grupa -> nodaļa; vecās NACE 2.0 45.x autonozares kartētas uz 2.1; bez
// atbilstības -> pseidosekcija 'X' "Nezināma nozare" (nodaļa '00').
declare(strict_types=1);

const SEKT_TOP_PER_DIV = 40;   // TOP uzņēmumi nodaļā (pēc apgrozījuma), ko iegulst overview.json
const SEKT_MIN_FILERS = 5000;  // gads skaitās "pilns", ja iesniedzēju >= šim slieksnim
const SEKT_LVL_EUR = 1.42288;  // 1 LVL = 1.42288 EUR (vēsturiskiem pamatkapitāla ierakstiem)
const SEKT_MARGIN_CAP = 300.0; // maržas |%| griesti izkārtojuma vērtībai
// Cik JAUNĀKOS VID ceturkšņus apvieno bruto algas aprēķinam. Viens ceturksnis atsevišķi ir ļoti
// troksnains (prēmijas, atvaļinājumu rezerves, sezonalitāte — blakus ceturkšņi vienam uzņēmumam
// atšķiras p10/p90 = 0,61/1,69 reizes), tāpēc summējam pēdējos līdz 4 ceturkšņus un dalām ar
// nostrādātajiem darbinieku-mēnešiem. Vērtība 1 = tikai jaunākais ceturksnis.
const SEKT_SALARY_QUARTERS = 4;

// Bilances posteņi (tā pati kopa un secība kā Reģistra bilances panelī) — bal masīva indeksi.
const SEKT_BAL_COLS = [
    'total_non_current_assets',        // 0 Ilgtermiņa ieguldījumi
    'intangible_assets',               // 1   Nemateriālie ieguldījumi
    'fixed_assets',                    // 2   Pamatlīdzekļi
    'investments',                     // 3   Finanšu ieguldījumi
    'total_current_assets',            // 4 Apgrozāmie līdzekļi
    'inventories',                     // 5   Krājumi
    'accounts_receivable',             // 6   Debitori
    'marketable_securities',           // 7   Vērtspapīri
    'cash',                            // 8   Nauda
    'equity',                          // 9 Pašu kapitāls (saglabā arī negatīvu)
    'provisions',                      // 10 Uzkrājumi
    'non_current_liabilities',         // 11 Ilgtermiņa saistības
    'current_liabilities',             // 12 Īstermiņa saistības
    'future_housing_repairs_payments', // 13 Nākamo periodu ieņēmumi
];

// Vecie NACE 2.0 kodi, kuru nodaļas 2.1 klasifikatorā vairs nav (45 = autotirdzniecība/remonts).
const SEKT_LEGACY_NACE = [
    '4511' => '4781', '4519' => '4781', // transportlīdzekļu tirdzniecība -> 47.81
    '4531' => '4782', '4532' => '4782', // daļu tirdzniecība -> 47.82
    '4540' => '4783',                   // motocikli -> 47.83
    '4520' => '9531',                   // apkope un remonts -> 95.31
];

/** Ielasa NACE.csv (komats, UTF-8 BOM) -> [secName, divInfo, grpDiv, clsInfo]. */
function sekt_parse_nace(string $nace_csv): array {
    $fh = fopen($nace_csv, 'r');
    if ($fh === false) throw new RuntimeException("Struktūra: nevar atvērt $nace_csv");
    $header = fgetcsv($fh, 0, ',', '"', '\\');
    if ($header === false) throw new RuntimeException("Struktūra: tukšs $nace_csv");
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
    $idx = array_flip($header);
    foreach (['Kods', 'Nosaukums', 'Vecāka kods', 'Līmenis'] as $col) {
        if (!isset($idx[$col])) throw new RuntimeException("Struktūra: NACE.csv trūkst kolonnas '$col'");
    }
    $secName = []; $divInfo = []; $grpDiv = []; $clsInfo = [];
    while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        $code = trim((string)$r[$idx['Kods']]);
        $name = trim((string)$r[$idx['Nosaukums']]);
        $parent = trim((string)$r[$idx['Vecāka kods']]);
        $level = (int)$r[$idx['Līmenis']];
        $np = str_replace('.', '', $code);
        if ($level === 1) {
            $secName[$code] = $name;
        } elseif ($level === 2) {
            $divInfo[$np] = ['sec' => $parent, 'name' => $name];
        } elseif ($level === 3) {
            $grpDiv[$np] = str_replace('.', '', $parent);
        } elseif ($level === 4) {
            $clsInfo[$np] = ['div' => substr($np, 0, 2), 'name' => $name];
        }
    }
    fclose($fh);
    return [$secName, $divInfo, $grpDiv, $clsInfo];
}

/** Skaitlis no DB šūnas (Python float(str.replace(',', '.')) konvencija). */
function sekt_num($v): ?float {
    if (is_int($v) || is_float($v)) return (float)$v;
    if (is_string($v)) {
        $s = trim(str_replace(',', '.', $v));
        if ($s !== '' && is_numeric($s)) return (float)$s;
    }
    return null;
}

/** VID teksta lauka parse: noņem tūkstošu atstarpes ("2 622"), ',' -> '.'. null, ja nav skaitlis. */
function sekt_vid_num($raw): ?float {
    $s = preg_replace('/\s+/u', '', (string)($raw ?? ''));
    $s = str_replace(',', '.', $s);
    if ($s === '' || !is_numeric($s)) return null;
    return (float)$s;
}

/** VID VSAOI teksta parse: kā sekt_vid_num, bet ×1000 (vērtības ir tūkst. € — tā pati
 *  konvencija kā Nozares noz_vsaoi_val). null, ja nav parsējams. */
function sekt_vsaoi($raw): ?float {
    $v = sekt_vid_num($raw);
    return $v === null ? null : $v * 1000.0;
}

/** "2026. gada 2. ceturksnis" -> [20262, "2026. g. 2. cet."]; null, ja neatpazīst. */
function sekt_quarter($raw): ?array {
    if (!preg_match('/(\d{4})\.\s*gada\s*(\d)\.\s*ceturksnis/u', (string)$raw, $m)) return null;
    return [(int)$m[1] * 10 + (int)$m[2], $m[1] . '. g. ' . $m[2] . '. cet.'];
}

/** rounded_to_nearest reizinātājs (naudas vērtībām pārskatā). */
function sekt_round_factor($rn): float {
    $r = strtoupper(trim((string)($rn ?? '')));
    if ($r === 'THOUSANDS') return 1000.0;
    if ($r === 'MILLIONS') return 1000000.0;
    return 1.0;
}

/**
 * Uzbūvē treemap sadaļu: struktura.php lapa + JSON datu mape.
 *
 * @param PDO           $ur            ur_data.db (staging vai dzīvā)
 * @param string        $nace_csv      NACE 2.1 klasifikators (build/NACE.csv)
 * @param string        $template_path statiskais lapas šablons
 * @param string        $out_php       izvades lapa (staging/struktura.php)
 * @param string        $out_data_dir  izvades JSON mape (staging/struktura_data)
 * @param callable|null $log
 */
function build_section_struktura(PDO $ur, string $nace_csv, string $template_path, string $out_php, string $out_data_dir, ?callable $log = null): void {
    $log = $log ?? function ($m) {};
    if (!is_file($template_path)) throw new RuntimeException("Struktūra: trūkst šablona $template_path");

    [$secName, $divInfo, $grpDiv, $clsInfo] = sekt_parse_nace($nace_csv);
    $log('      Struktūra: NACE klasifikators — ' . count($secName) . ' sekcijas, '
        . count($divInfo) . ' nodaļas, ' . count($clsInfo) . ' klases');

    // Pseidozari nezināmajai nozarei.
    $secName['X'] = 'Nezināma nozare';
    $divInfo['00'] = ['sec' => 'X', 'name' => 'Nezināma nozare'];

    // 1. Aktīvie uzņēmumi (tā pati definīcija kā pārējās sadaļās) + reģistrācijas datums vecumam
    //    + juridiskā forma (register.type) filtriem.
    $log('      Struktūra: atlasa aktīvos uzņēmumus ...');
    $activeName = []; // regcode => name
    $regYear = [];    // regcode => vecums pilnos gados
    $formOf = [];     // regcode => formas kods (SIA/AS/ZEM/...)
    $formLabel = [];  // formas kods => nosaukums ("Sabiedrība ar ierobežotu atbildību")
    $nowTs = time();
    $q = $ur->query("SELECT regcode, name, registered, type, type_text FROM register
                     WHERE (closed IS NULL OR TRIM(closed) = '')
                       AND (terminated IS NULL OR TRIM(terminated) IN ('', '0000-00-00'))
                     ORDER BY rowid");
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = (string)$r[0];
        if ($reg === '') continue;
        if (!array_key_exists($reg, $activeName)) {
            $activeName[$reg] = $r[1] === null ? null : (string)$r[1];
            $rd = trim((string)($r[2] ?? ''));
            if ($rd !== '' && ($ts = strtotime($rd)) !== false && $ts < $nowTs) {
                $regYear[$reg] = (int)floor(($nowTs - $ts) / (365.25 * 86400)); // pilni gadi
            }
            $tp = trim((string)($r[3] ?? ''));
            if ($tp !== '') {
                $formOf[$reg] = $tp;
                if (!isset($formLabel[$tp])) {
                    $tt = trim((string)($r[4] ?? ''));
                    $formLabel[$tp] = $tt !== '' ? $tt : $tp;
                }
            }
        }
    }

    // 1b. Izslēdzamie: reorganizētie prom (avota puse) + likvidācijas procesā esošie.
    // SEPARATION (nodalīšana) NEizslēdz — tur avota uzņēmums pēc definīcijas turpina
    // pastāvēt (2026-08-05 audita atradums: AS "Latvijas Gāze" un vēl ~710 aktīvi
    // uzņēmumi ar svaigiem pārskatiem pazuda no kartes 2017. g. nodalīšanu dēļ).
    // TRANSFORMATION paliek izslēgts — tur avota regcode vienmēr mainās (pārbaudīts: 0/1856 sakrīt).
    $excl = [];
    $q = $ur->query("SELECT DISTINCT CAST(CAST(source_entity_regcode AS INTEGER) AS TEXT)
                     FROM reorganizations WHERE source_entity_regcode IS NOT NULL
                       AND reorganization_type <> 'SEPARATION'");
    while ($r = $q->fetch(PDO::FETCH_NUM)) $excl[(string)$r[0]] = true;
    $nReorg = count($excl);
    $q = $ur->query("SELECT DISTINCT legal_entity_registration_number FROM liquidations
                     WHERE legal_entity_registration_number IS NOT NULL
                       AND TRIM(legal_entity_registration_number) <> ''");
    while ($r = $q->fetch(PDO::FETCH_NUM)) $excl[(string)$r[0]] = true;
    $log('      Struktūra: aktīvi ' . count($activeName) . ", izslēdzamie (reorg $nReorg + likvidācijas) kopā " . count($excl));

    // 1c. VID reitings (jaunākais ieraksts uzvar) + riska pazīmju bitu maska.
    $rating = []; // reg => 'A'|'B'|'C'|'J'|'N'
    $q = $ur->query("SELECT Registracijas_kods, Reitings FROM reitings_uznemumi
                     WHERE Reitings IS NOT NULL AND TRIM(Reitings) <> '' ORDER BY rowid");
    while ($r = $q->fetch(PDO::FETCH_NUM)) $rating[trim((string)$r[0])] = trim((string)$r[1]);

    $risk = []; // reg => bitmaska (1=apturēšana, 2=nodrošinājums, 4=aktīva maksātnespēja)
    $q = $ur->query('SELECT DISTINCT legal_entity_registration_number FROM suspensions_prohibitions
                     WHERE legal_entity_registration_number IS NOT NULL');
    while ($r = $q->fetch(PDO::FETCH_NUM)) $risk[trim((string)$r[0])] = ($risk[trim((string)$r[0])] ?? 0) | 1;
    $q = $ur->query('SELECT DISTINCT legal_entity_registration_number FROM securing_measures
                     WHERE legal_entity_registration_number IS NOT NULL');
    while ($r = $q->fetch(PDO::FETCH_NUM)) $risk[trim((string)$r[0])] = ($risk[trim((string)$r[0])] ?? 0) | 2;
    $q = $ur->query("SELECT DISTINCT CAST(debtor_registration_number AS TEXT)
                     FROM insolvency_legal_person_proceeding
                     WHERE debtor_registration_number IS NOT NULL
                       AND (proceeding_ended_on IS NULL OR TRIM(proceeding_ended_on) = '')");
    while ($r = $q->fetch(PDO::FETCH_NUM)) $risk[trim((string)$r[0])] = ($risk[trim((string)$r[0])] ?? 0) | 4;
    $log('      Struktūra: reitingi ' . count($rating) . ', riska pazīmes ' . count($risk) . ' uzņēmumiem');

    // 1d. Aktīvie PVN maksātāji.
    $vat = [];
    $q = $ur->query("SELECT DISTINCT Numurs FROM pdb_pvnmaksataji_odata WHERE TRIM(Aktivs) = 'ir'");
    while ($r = $q->fetch(PDO::FETCH_NUM)) $vat[trim((string)$r[0])] = true;
    $log('      Struktūra: aktīvi PVN maksātāji ' . count($vat));

    // 2. Pārskatu logs: jaunākais "pilnais" gads + 2 iepriekšējie.
    $row = $ur->query('SELECT MAX(year) FROM (
                          SELECT year, COUNT(DISTINCT legal_entity_registration_number) c
                          FROM financial_statements WHERE year IS NOT NULL
                          GROUP BY year HAVING c >= ' . SEKT_MIN_FILERS . ')')->fetch(PDO::FETCH_NUM);
    $newest = (int)($row[0] ?? 0);
    if ($newest < 2000) throw new RuntimeException('Struktūra: nevar noteikt jaunāko pārskatu gadu');
    $minYear = $newest - 2;
    $log("      Struktūra: pārskatu logs {$minYear}–{$newest} (+ vēlāki nepilnie gadi)");

    // 3. Jaunākais pārskats katram aktīvajam uzņēmumam loga ietvaros (UGP prioritāte gadā)
    //    + TIEŠI iepriekšējā gada (year-1) pārskats izaugsmes krāsu režīmiem (tāpēc lasa no
    //    minYear-1; iekļaušanai joprojām vajag jaunāko gadu >= minYear).
    $log('      Struktūra: atlasa jaunākos pārskatus ...');
    $best = []; // reg => [year, sid, rn, emp, prevSid|null, prevRn|null, prevEmp|null]
    $prevFrom = $minYear - 1;
    $q = $ur->query("SELECT legal_entity_registration_number, year, source_type, rounded_to_nearest, id, employees
                     FROM financial_statements
                     WHERE year IS NOT NULL AND year >= $prevFrom
                     ORDER BY legal_entity_registration_number, rowid");
    $curReg = null; $byYear = []; // year => [isUgp, sid, rn, emp] (rindas pa reg ir blakus)
    $finish = function () use (&$curReg, &$byYear, &$best, $minYear) {
        if ($curReg !== null && !empty($byYear)) {
            $bestYear = -1;
            foreach ($byYear as $y => $_) if ($y >= $minYear && $y > $bestYear) $bestYear = $y;
            if ($bestYear > 0) {
                $b = $byYear[$bestYear];
                $p = $byYear[$bestYear - 1] ?? null;
                $best[$curReg] = [$bestYear, $b[1], $b[2], $b[3],
                    $p !== null ? $p[1] : null, $p !== null ? $p[2] : null, $p !== null ? $p[3] : null];
            }
        }
        $byYear = [];
    };
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = (string)$r[0];
        if (!array_key_exists($reg, $activeName) || isset($excl[$reg])) continue;
        if ($reg !== $curReg) { $finish(); $curReg = $reg; }
        $year = (int)$r[1];
        $isUgp = (string)$r[2] === 'UGP';
        $b = $byYear[$year] ?? null;
        // gada ietvaros: pirmā UGP rinda, citādi pirmā rinda (CSV secība)
        if ($b === null || ($isUgp && !$b[0])) {
            $byYear[$year] = [$isUgp, (string)$r[4], $r[3], sekt_num($r[5])];
        }
    }
    $finish();
    $log('      Struktūra: pārskati logā ' . count($best) . ' uzņēmumiem');

    // 4. Apgrozījums + peļņa + bilance no izvēlētā pārskata (krāsai peļņa OBLIGĀTA).
    $incStmt = $ur->prepare('SELECT net_turnover, net_income FROM income_statements
                             WHERE statement_id = ? ORDER BY rowid LIMIT 1');
    $balStmt = $ur->prepare('SELECT total_assets, ' . implode(', ', SEKT_BAL_COLS)
        . ' FROM balance_sheets WHERE statement_id = ? ORDER BY rowid LIMIT 1');
    $fin = []; // reg => [t, p, a|null, e|null, year, bal|null, t2|null, p2|null, e2|null]
    foreach ($best as $reg => $b) {
        if ($b[1] === '') continue;
        $incStmt->execute([$b[1]]);
        $row = $incStmt->fetch(PDO::FETCH_NUM);
        if ($row === false) continue;
        $t = sekt_num($row[0]);
        $p = sekt_num($row[1]);
        if ($t === null || $t <= 0 || $p === null) continue; // bez izmēra vai krāsas nav attēlojams
        $f = sekt_round_factor($b[2]);
        $balStmt->execute([$b[1]]);
        $brow = $balStmt->fetch(PDO::FETCH_NUM);
        $a = null; $bal = null;
        if ($brow !== false) {
            $a = sekt_num($brow[0]);
            $bal = [];
            $any = false;
            foreach (SEKT_BAL_COLS as $i => $col) {
                $v = sekt_num($brow[$i + 1]);
                // 0 = "nav posteņa" (izņemot pašu kapitālu, kur negatīvs ir informatīvs)
                if ($v === null || ($v == 0.0) || ($v < 0 && $i !== 9)) { $bal[] = null; continue; }
                $bal[] = (int)round($v * $f);
                $any = true;
            }
            if (!$any) $bal = null;
        }
        // Iepriekšējā gada pārskats (izaugsme/pavērsiens/darbinieku dinamika).
        $t2 = null; $p2 = null; $e2 = null;
        if ($b[4] !== null && $b[4] !== '') {
            $incStmt->execute([$b[4]]);
            $prow = $incStmt->fetch(PDO::FETCH_NUM);
            if ($prow !== false) {
                $pf = sekt_round_factor($b[5]);
                $tv = sekt_num($prow[0]);
                $pv = sekt_num($prow[1]);
                if ($tv !== null && $tv > 0) $t2 = (int)round($tv * $pf);
                if ($pv !== null) $p2 = (int)round($pv * $pf);
            }
            if ($b[6] !== null && $b[6] > 0) $e2 = round($b[6], 1);
        }
        $fin[$reg] = [
            (int)round($t * $f),
            (int)round($p * $f),
            ($a !== null && $a > 0) ? (int)round($a * $f) : null,
            ($b[3] !== null && $b[3] > 0) ? round($b[3], 1) : null,
            $b[0],
            $bal,
            $t2, $p2, $e2,
        ];
    }
    unset($best);
    $log('      Struktūra: ar apgrozījumu un peļņas datiem ' . count($fin) . ' uzņēmumi');

    // 5a. Pamatkapitāls: jaunākais PAID_UP_EQUITY (rezervē REGISTERED_EQUITY), LVL -> EUR.
    $capital = []; // reg => [isPaid, dateFrom, eur]
    $q = $ur->query("SELECT legal_entity_registration_number, equity_capital_type, amount, currency, date_from
                     FROM equity_capitals
                     WHERE equity_capital_type IN ('PAID_UP_EQUITY', 'REGISTERED_EQUITY')
                     ORDER BY rowid");
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = trim((string)$r[0]);
        if ($reg === '' || !isset($fin[$reg])) continue;
        $amount = sekt_num($r[2]);
        if ($amount === null || $amount <= 0) continue;
        if (strtoupper(trim((string)($r[3] ?? ''))) === 'LVL') $amount *= SEKT_LVL_EUR;
        $isPaid = (string)$r[1] === 'PAID_UP_EQUITY' ? 1 : 0;
        $df = (string)($r[4] ?? '');
        $cur = $capital[$reg] ?? null;
        // prioritāte: apmaksātais > reģistrētais; tad jaunākais date_from
        if ($cur === null || $isPaid > $cur[0] || ($isPaid === $cur[0] && $df >= $cur[1])) {
            $capital[$reg] = [$isPaid, $df, (int)round($amount)];
        }
    }

    // 5b. NACE + VID gada dati (bruto algai, darbinieku rezervei un reģionam): ffill '?',
    //     jaunākā gada rinda. Rīgas priekšpilsētas/rajonus apvieno zem "Rīga".
    $RIGA_PARTS = ['Vidzemes priekšpilsēta' => 1, 'Latgales priekšpilsēta' => 1,
                   'Zemgales priekšpilsēta' => 1, 'Ziemeļu rajons' => 1,
                   'Kurzemes rajons' => 1, 'Centra rajons' => 1];
    $nace = [];   // reg => [gads, np]
    $vid = [];    // reg => [gads, emp, vsaoi, region|null]
    $q = $ur->query('SELECT Registracijas_kods, Pamatdarbibas_NACE_kods, Taksacijas_gads,
                            Videjais_nodarbinato_personu_skaits_cilv, Taja_skaita_VSAOI,
                            Juridiska_adrese_ATVK_nosaukums
                     FROM pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata
                     ORDER BY Registracijas_kods, Taksacijas_gads, rowid');
    $curReg = null; $ffill = null;
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = trim((string)$r[0]);
        if ($reg !== $curReg) { $curReg = $reg; $ffill = null; }
        $code = $r[1] === null ? '' : trim((string)$r[1]);
        if ($code === '' || $code === '?') $code = $ffill; else $ffill = $code;
        if (!isset($fin[$reg])) continue;
        $gads = (int)$r[2];
        if ($code !== null && (!isset($nace[$reg]) || $gads >= $nace[$reg][0])) {
            $nace[$reg] = [$gads, str_replace('.', '', $code)];
        }
        if (!isset($vid[$reg]) || $gads >= $vid[$reg][0]) {
            $rgn = trim((string)($r[5] ?? ''));
            if ($rgn === '' || $rgn === '?') $rgn = null;
            elseif (isset($RIGA_PARTS[$rgn])) $rgn = 'Rīga';
            $vid[$reg] = [$gads, sekt_vid_num($r[3]), sekt_vsaoi($r[4]), $rgn];
        }
    }

    // 5b2. VID CETURKŠŅU dati — svaigākais avots algai un darbinieku skaitam (gada tabula atpaliek
    //      par ~1,5 gadu). Ceturkšņu tabulā NAV NACE un adreses, tāpēc nozare un reģions joprojām
    //      nāk no gada tabulas. Algu rēķina, summējot pēdējos SEKT_SALARY_QUARTERS ceturkšņus un
    //      dalot ar nostrādātajiem darbinieku-mēnešiem (viens ceturksnis atsevišķi ir troksnains).
    $qRows = [];   // reg => [qKey => [vsaoi €, darbinieki]]
    $qLabels = []; // qKey => "2026. g. 2. cet."
    $q = $ur->query('SELECT Registracijas_kods, Taksacijas_gads_ceturksnis,
                            Taja_skaita_VSAOI_summa, Videjais_nodarbinato_personu_skaits_cilv
                     FROM pdb_samaksato_nodoklu_kopsummas_cet');
    while ($r = $q->fetch(PDO::FETCH_NUM)) {
        $reg = trim((string)$r[0]);
        if ($reg === '' || !isset($fin[$reg])) continue;
        $qi = sekt_quarter($r[1]);
        if ($qi === null) continue;
        $qLabels[$qi[0]] = $qi[1];
        $v = sekt_vsaoi($r[2]);
        $e = sekt_vid_num($r[3]);
        if ($v === null || $v <= 0 || $e === null || $e <= 0) continue; // bez algas fonda nerēķina
        $qRows[$reg][$qi[0]] = [$v, $e];
    }
    krsort($qLabels);
    $useQ = array_slice(array_keys($qLabels), 0, SEKT_SALARY_QUARTERS, true); // jaunākie, dilstoši
    $useSet = array_flip($useQ);
    $salaryPeriod = null; $empPeriod = null;
    if ($useQ !== []) {
        $empPeriod = $qLabels[$useQ[0]];
        $salaryPeriod = count($useQ) > 1
            ? $qLabels[$useQ[count($useQ) - 1]] . ' – ' . $qLabels[$useQ[0]]
            : $qLabels[$useQ[0]];
    }

    $salQ = []; // reg => bruto alga €/mēn no ceturkšņiem
    $empQ = []; // reg => darbinieki jaunākajā PIEEJAMAJĀ ceturksnī (loga ietvaros)
    foreach ($qRows as $reg => $byQ) {
        $sumV = 0.0; $sumEmpMonths = 0.0; $newestKey = -1;
        foreach ($byQ as $k => [$v, $e]) {
            if (!isset($useSet[$k])) continue;
            $sumV += $v;
            $sumEmpMonths += $e * 3.0; // ceturksnis = 3 darbinieku-mēneši uz katru darbinieku
            if ($k > $newestKey) { $newestKey = $k; $empQ[$reg] = round($e, 1); }
        }
        // Alga tikai >=3 darbiniekiem (jaunākajā ceturksnī) — privātums mazajos uzņēmumos.
        if ($sumEmpMonths > 0 && $sumV > 0 && ($empQ[$reg] ?? 0) >= 3) {
            $sv = (int)round(($sumV / 0.3409) / $sumEmpMonths);
            if ($sv > 0) $salQ[$reg] = $sv;
        }
    }
    unset($qRows);
    $log('      Struktūra: VID ceturkšņi ' . ($salaryPeriod ?? 'nav') . ' — algas '
        . count($salQ) . ', darbinieku skaits ' . count($empQ) . ' uzņēmumiem');

    // 5c. Galīgā bruto alga katram uzņēmumam (ceturkšņi > gada dati) + mediāna krāsu režīmam.
    $salAll = []; // reg => alga €/mēn
    $nSalYear = 0;
    foreach ($fin as $reg => $_) {
        if (isset($salQ[$reg])) { $salAll[$reg] = $salQ[$reg]; continue; }
        $v = $vid[$reg] ?? null;
        // Gada atkāpes ceļā tas pats >=3 darbinieku privātuma slieksnis.
        if ($v !== null && $v[1] !== null && $v[1] >= 3 && $v[2] !== null && $v[2] > 0) {
            $sv = (int)round(($v[2] / 0.3409) / $v[1] / 12);
            if ($sv > 0) { $salAll[$reg] = $sv; $nSalYear++; }
        }
    }
    $salaries = array_values($salAll);
    sort($salaries);
    $medianS = $salaries !== [] ? $salaries[intdiv(count($salaries), 2)] : 0;
    unset($salaries, $salQ);
    $log("      Struktūra: bruto alga " . count($salAll) . " uzņēmumiem (no tiem $nSalYear pēc "
        . "gada datiem), mediāna $medianS €/mēn");

    // 6. Uzņēmumi -> nodaļas ar pilnu metriku vektoru.
    $divisions = []; // np2 => ['companies'=>[], 'tot'=>[...], 'pos'=>, 'neg'=>]
    $noNace = 0; $legacyMapped = 0;
    $emptyTot = ['t' => 0, 'p' => 0, 'a' => 0, 'k' => 0, 'e' => 0.0, 's' => 0, 'm' => 0.0,
                 'b' => array_fill(0, count(SEKT_BAL_COLS), 0)];
    $regionIds = []; $regionNames = []; // reģionu vārdnīca (id = indekss masīvā)
    $formIds = []; $formNames = [];     // juridisko formu vārdnīca
    // Nodaļas 'cs' krāsu daļas (0..1, "labā" toņa īpatsvars neielādēto zonu mozaīkai) — indeksi:
    // 0 peļņa/marža (p>=0), 1 likviditāte (apgrozāmie/īsterm.saist.>=1), 2 parādu slogs
    // (saistības/aktīvi<=0.7), 3 pašu kapitāls (>0), 4 izaugsme (t>t2), 5 pavērsiens (p>=0),
    // 6 darbinieku dinamika (e>=e2), 7 nozare (nelieto, 0.5), 8 alga (s>=mediāna),
    // 9 produktivitāte (p>=0 starp tiem, kam ir darbinieki), 10 vecums (age<10),
    // 11 reitings (A), 12 risks (bez pazīmēm), 13 svaigums (jaunākais gads).
    foreach ($fin as $reg => [$t, $p, $a, $eFs, $year, $bal, $t2, $p2, $e2]) {
        $np = $nace[$reg][1] ?? null;
        $cls = ''; $div = null;
        if ($np !== null) {
            if (isset(SEKT_LEGACY_NACE[$np])) { $np = SEKT_LEGACY_NACE[$np]; $legacyMapped++; }
            if (isset($clsInfo[$np])) { $cls = $np; $div = $clsInfo[$np]['div']; }
            elseif (isset($grpDiv[substr($np, 0, 3)])) $div = $grpDiv[substr($np, 0, 3)];
            elseif (isset($divInfo[substr($np, 0, 2)])) $div = substr($np, 0, 2);
        }
        if ($div === null) { $div = '00'; $noNace++; }
        $name = $activeName[$reg];
        if ($name === null || trim($name) === '') $name = $reg;
        $name = str_replace('Sabiedrība ar ierobežotu atbildību', 'SIA', $name);
        $name = str_replace('Akciju sabiedrība', 'AS', $name);

        $k = $capital[$reg][2] ?? null;
        $vidEmp = $vid[$reg][1] ?? null;
        // ef = pārskata perioda darbinieki (salīdzinājumiem ar e2 un produktivitātei),
        // e  = svaigākais zināmais skaits (VID jaunākais ceturksnis > pārskats > VID gads).
        $ef = $eFs !== null ? $eFs : (($vidEmp !== null && $vidEmp > 0) ? round($vidEmp, 1) : null);
        $e = $empQ[$reg] ?? $ef;
        $s = $salAll[$reg] ?? null;
        // vq = 1, ja darbinieki un alga nāk no VID ceturkšņiem (0 = gada dati / pārskats)
        $vq = isset($empQ[$reg]) ? 1 : 0;

        $age = $regYear[$reg] ?? null;
        $rtg = $rating[$reg] ?? null;
        $rsk = $risk[$reg] ?? 0;

        // Reģiona un formas vārdnīcu id (kompaktāks JSON nekā virknes katrā rindā).
        $rgn = $vid[$reg][3] ?? null;
        $rgId = null;
        if ($rgn !== null) {
            if (!isset($regionIds[$rgn])) { $regionIds[$rgn] = count($regionNames); $regionNames[] = $rgn; }
            $rgId = $regionIds[$rgn];
        }
        $fmCode = $formOf[$reg] ?? null;
        $fmId = null;
        if ($fmCode !== null) {
            if (!isset($formIds[$fmCode])) {
                $formIds[$fmCode] = count($formNames);
                $formNames[] = $formLabel[$fmCode] ?? $fmCode;
            }
            $fmId = $formIds[$fmCode];
        }
        $vatFlag = isset($vat[$reg]) ? 1 : 0;

        if (!isset($divisions[$div])) {
            $divisions[$div] = ['companies' => [], 'tot' => $emptyTot, 'pos' => 0, 'neg' => 0,
                                'cs' => array_fill(0, 14, [0, 0])];
        }
        $d = &$divisions[$div];
        $d['companies'][] = [(string)$reg, $name, $t, $p, $a, $k, $e, $s, $cls, $year, $bal,
                             $t2, $p2, $e2, $age, $rtg, $rsk, $rgId, $fmId, $vatFlag, $ef, $vq];
        $d['tot']['t'] += $t;
        $d['tot']['p'] += abs($p);
        if ($a !== null) $d['tot']['a'] += $a;
        if ($k !== null) $d['tot']['k'] += $k;
        if ($e !== null) $d['tot']['e'] += $e;
        if ($s !== null) $d['tot']['s'] += $s;
        $d['tot']['m'] += min(abs($p) / $t * 100.0, SEKT_MARGIN_CAP);
        if ($bal !== null) { // izkārtojums summē tikai pozitīvo (negatīvam blokam nav izmēra)
            foreach ($bal as $i => $v) {
                if ($v !== null && $v > 0) $d['tot']['b'][$i] += $v;
            }
        }
        if ($p >= 0) $d['pos']++; else $d['neg']++;

        // Krāsu daļu skaitītāji [labie, ar datiem] (indeksu nozīmes — komentārā augstāk).
        $csv = &$d['cs'];
        $csv[0][1]++; if ($p >= 0) $csv[0][0]++;
        $b4 = $bal[4] ?? null; $b12v = $bal[12] ?? null;
        if ($b4 !== null && $b4 > 0 && $b12v !== null && $b12v > 0) {
            $csv[1][1]++; if ($b4 / $b12v >= 1.0) $csv[1][0]++;
        }
        if ($a !== null && $a > 0 && $bal !== null) {
            $liab = ($bal[11] ?? 0) + ($bal[12] ?? 0) + ($bal[13] ?? 0);
            $csv[2][1]++; if ($liab / $a <= 0.7) $csv[2][0]++;
        }
        $b9 = $bal[9] ?? null;
        if ($b9 !== null) { $csv[3][1]++; if ($b9 > 0) $csv[3][0]++; }
        if ($t2 !== null) { $csv[4][1]++; if ($t > $t2) $csv[4][0]++; }
        $csv[5][1]++; if ($p >= 0) $csv[5][0]++;
        if ($ef !== null && $e2 !== null && $e2 > 0) { $csv[6][1]++; if ($ef >= $e2) $csv[6][0]++; }
        if ($s !== null) { $csv[8][1]++; if ($medianS > 0 && $s >= $medianS) $csv[8][0]++; }
        if ($ef !== null && $ef > 0) { $csv[9][1]++; if ($p >= 0) $csv[9][0]++; }
        if ($age !== null) { $csv[10][1]++; if ($age < 10) $csv[10][0]++; }
        if ($rtg !== null) { $csv[11][1]++; if ($rtg === 'A') $csv[11][0]++; }
        $csv[12][1]++; if ($rsk === 0) $csv[12][0]++;
        $csv[13][1]++; if ($year === $newest) $csv[13][0]++;
        unset($csv, $d);
    }
    unset($fin, $nace, $vid, $capital, $activeName, $regYear, $rating, $risk, $formOf, $formLabel,
          $vat, $salAll, $empQ);
    $log('      Struktūra: sadalīti pa ' . count($divisions) . " nodaļām (bez NACE $noNace, "
        . "pārkartoti no vecā NACE $legacyMapped)");

    // 7. JSON izvades.
    @mkdir($out_data_dir, 0775, true);
    foreach (glob($out_data_dir . '/*.json') ?: [] as $old) @unlink($old); // tīra iepriekšējo būvi

    $ovDivs = [];
    $searchIndex = [];
    $totalT = 0; $totalN = 0;
    uksort($divisions, fn($a, $b) => strcmp((string)$a, (string)$b)); // PHP num-atslēgas kļūst int
    foreach ($divisions as $div => &$d) {
        $div = (string)$div;
        if (strlen($div) === 1) $div = '0' . $div;
        usort($d['companies'], fn($a, $b) => $b[2] <=> $a[2]); // pēc apgrozījuma, dilstoši
        $sec = $divInfo[$div]['sec'];
        $n = count($d['companies']);
        $totalT += $d['tot']['t']; $totalN += $n;

        // noapaļo agregātus (mazāks JSON)
        $tot = $d['tot'];
        $tot['e'] = round($tot['e'], 1);
        $tot['m'] = round($tot['m'], 1);

        // Nodaļas fails: klašu vārdnīca + visi uzņēmumi.
        $clsNames = [];
        foreach ($d['companies'] as $c) {
            $cls = $c[8];
            if (!isset($clsNames[$cls])) {
                $clsNames[$cls] = $cls === '' ? 'Bez precizētas apakšnozares' : ($clsInfo[$cls]['name'] ?? $cls);
            }
        }
        $divJson = ['code' => $div, 'classes' => $clsNames, 'companies' => $d['companies']];
        file_put_contents($out_data_dir . "/div_$div.json",
            json_encode($divJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Meklēšanas indekss: [reg, nosaukums, nodaļa] — lazy ielādei frontendā.
        foreach ($d['companies'] as $c) $searchIndex[] = [$c[0], $c[1], $div];

        // Overview ieraksts: TOP-N (bez klases koda — to atnes nodaļas fails).
        $top = array_map(
            fn($c) => [$c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7], $c[9], $c[10],
                       $c[11], $c[12], $c[13], $c[14], $c[15], $c[16], $c[17], $c[18], $c[19],
                       $c[20], $c[21]],
            array_slice($d['companies'], 0, SEKT_TOP_PER_DIV)
        );
        $csOut = [];
        foreach ($d['cs'] as $i => [$good, $den]) {
            $csOut[] = $i === 7 ? 0.5 : ($den > 0 ? round($good / $den, 3) : 0.5);
        }
        $ovDivs[] = [
            'code' => $div, 'sec' => $sec, 'name' => $divInfo[$div]['name'],
            'n' => $n, 'pos' => $d['pos'], 'neg' => $d['neg'],
            'tot' => $tot, 'cs' => $csOut, 'top' => $top,
        ];
    }
    unset($d, $divisions);

    usort($ovDivs, fn($a, $b) => $b['tot']['t'] <=> $a['tot']['t']);
    $ovSecs = [];
    foreach ($secName as $code => $nm) {
        foreach ($ovDivs as $dv) {
            if ($dv['sec'] === $code) { $ovSecs[] = ['code' => $code, 'name' => $nm]; break; }
        }
    }

    $overview = [
        'generated' => date('Y-m-d'),
        'years' => [$minYear, $newest],
        'totalT' => $totalT, 'totalN' => $totalN,
        'medianS' => $medianS,
        'salaryPeriod' => $salaryPeriod, // VID ceturkšņu logs, no kā rēķināta bruto alga
        'empPeriod' => $empPeriod,       // jaunākais VID ceturksnis (darbinieku skaitam)
        'regions' => $regionNames,
        'forms' => $formNames,
        'sections' => $ovSecs,
        'divisions' => $ovDivs,
    ];
    file_put_contents($out_data_dir . '/overview.json',
        json_encode($overview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    file_put_contents($out_data_dir . '/search.json',
        json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $log('      Struktūra: uzrakstīti ' . (count($ovDivs) + 2) . ' JSON faili ('
        . $totalN . ' uzņēmumi, apgrozījums ' . number_format($totalT / 1e9, 1, ',', ' ')
        . ' mljrd. €, reģioni ' . count($regionNames) . ', formas ' . count($formNames) . ')');

    // 8. Lapa = statiskais šablons (dati nāk no JSON, tāpēc bez vietturiem).
    @mkdir(dirname($out_php), 0775, true);
    if (!copy($template_path, $out_php)) {
        throw new RuntimeException("Struktūra: nevar ierakstīt $out_php");
    }
    $log('      Struktūra: uzģenerēts ' . basename($out_php));
}
