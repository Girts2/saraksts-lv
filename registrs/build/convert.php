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
/**
 * Reģistrācijas kodu apostrofu noņemšana (VID PDB "Saimnieciskās darbības apturēšana").
 *
 * Šajā vienā VID kopā kods ir ietverts apostrofos — '40001005630' — atšķirībā no
 * visām pārējām VID kopām (pdb_samaksato_nodoklu_kopsummas_cet, reitings_uznemumi u.c.),
 * kur tas ir tīrs. Bez normalizācijas JOIN pret register atgriež NULLI rindu, un
 * kļūda ir KLUSA: tabula ielasās, indekss uzbūvējas, tikai neviena lapa neatrod datus.
 * Normalizējam ielādes brīdī, lai DB kolonna izskatās tāpat kā visur citur.
 */
function fix_apostrofu_kodus(PDO $pdo, ?callable $log = null): int {
    $tabulas = ['pdb_saimndarbibaaptureta_odata' => 'Registracijas_kods'];
    $kopa = 0;
    foreach ($tabulas as $tab => $col) {
        try {
            $ir = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($tab))->fetchColumn();
            if (!$ir) continue;
            $apostrofs = "'";
            $n = $pdo->exec("UPDATE $tab SET $col = REPLACE($col, " . $pdo->quote($apostrofs) . ", '') WHERE instr($col, " . $pdo->quote($apostrofs) . ") > 0");
            $kopa += (int)$n;
            if ($log && $n) $log("      + $tab: noņemti apostrofi $n kodiem");
        } catch (Throwable $e) {
            if ($log) $log("      ! Apostrofu normalizēšanas kļūda ($tab): " . $e->getMessage());
        }
    }
    return $kopa;
}

/**
 * de minimis: FIZISKO personu datu izņemšana (GDPR datu minimizācija).
 *
 * DIVI slāņi, jo 2026-08-19 audits atrada, ka ar pirmo nepietiek:
 *  1. RINDAS: 9 181 ieraksts pieder fiziskām personām (personas kods DDMMYY-XXXXX,
 *     4 162 cilvēki) — tos dzēšam pavisam.
 *  2. BRĪVTEKSTS: project_title raksta atbalsta sniedzējs, un tur mēdz būt nolīgto
 *     personu, īpašnieku un darbinieku vārdi, izglītības vēsture, pat ārvalstu
 *     personas kods ("...daļu iegāde no Arūnas Mažonis (kods 36009290200)").
 *     Lauku NEVIENS panelis nerāda — vienīgie patērētāji bija jēltabula, JSON
 *     eksports un MI prompts. Tāpēc saturu dzēšam pavisam; kolonna paliek shēmas
 *     stabilitātei.
 *
 * FAIL-CLOSED: ja pēc dzēšanas kaut kas paliek vai vaicājums neizdodas, metam
 * izņēmumu un būve krīt. Klusa "nesanāca" šeit nozīmētu personas datus publiskā
 * vietnē — labāk pārtraukta būve nekā noplūde.
 */
function fix_deminimis_personas(PDO $pdo, ?callable $log = null): int {
    $ir = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote('deminimis'))->fetchColumn();
    if (!$ir) return 0;

    $n = (int)$pdo->exec("DELETE FROM deminimis WHERE person_reg_nr IS NULL OR length(trim(person_reg_nr)) <> 11 OR trim(person_reg_nr) GLOB '*[^0-9]*'");
    if ($log && $n) $log("      + deminimis: izmestas $n fizisko personu rindas (GDPR)");

    // project_title vietā glabājam ĪSU HEŠU, ne tukšumu: pats teksts ir personas
    // datu avots, BET tas ir vienīgais, kas atšķir divus vienā dienā piešķirtus
    // vienāda apjoma atbalstus. Bez tā panelis sapludinātu atsevišķus projektus
    // (pārbaudīts: 7 unikāli piešķīrumi kļuva par 3). Hešs nav atgriezenisks un
    // nesatur personas datus, bet ļauj korekti atšķirt ierakstus.
    $t = 0;
    // Jau apstrādātās rindas atpazīstam pēc HEŠA FORMĀTA, ne garuma: tieši 8 zīmju
    // īsts nosaukums ("Tehnika") paliktu neapstrādāts (audits 2026-08-19).
    $hesa_forma = "project_title GLOB '[0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f]'";
    $sel = $pdo->query("SELECT rowid, project_title FROM deminimis
        WHERE IFNULL(project_title,'') <> '' AND NOT ($hesa_forma)");
    $upd = $pdo->prepare("UPDATE deminimis SET project_title = ? WHERE rowid = ?");
    $pdo->beginTransaction();
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $upd->execute([substr(md5((string)$row['project_title']), 0, 8), (int)$row['rowid']]);
        $t++;
    }
    $pdo->commit();
    if ($log && $t) $log("      + deminimis: project_title brīvteksts aizstāts ar hešu $t rindās (GDPR)");

    $atlikums = (int)$pdo->query("SELECT COUNT(*) FROM deminimis
        WHERE person_reg_nr IS NULL OR length(trim(person_reg_nr)) <> 11
           OR trim(person_reg_nr) GLOB '*[^0-9]*'
           OR (IFNULL(project_title,'') <> '' AND NOT ($hesa_forma))")->fetchColumn();
    if ($atlikums > 0) {
        throw new RuntimeException("de minimis GDPR filtrs neizdevās: palikušas $atlikums rindas ar personas datiem — būve pārtraukta.");
    }
    return $n;
}

/**
 * EIS iepirkumu rezultāti: no deviņām gada jēltabulām uzbūvē vienu saspiestu
 * `iepirkumi` tabulu un jēltabulas nomet.
 *
 * KĀPĒC SASPIESTA TABULA: jēlfaili kopā ir 289 tūkst. rindu / 184 MB SQLite —
 * viena rinda uz katru (iepirkuma daļa × dokuments × uzvarētājs). Displejam
 * vajadzīga viena rinda uz LĪGUMU, tāpēc saspiežam līdz ~201 tūkst. rindām /
 * 48 MB un pārējo izmetam.
 *
 * TRĪS DUBULTOŠANĀS, kas naivai summēšanai dod nepatiesus miljardus:
 *   1. Iepirkuma DAĻAS — viens līgums (dok_ID) parādās katrā daļā, kurā uzvarēts.
 *      Grupējam pēc līguma ķēdes, nevis skaitām rindas.
 *   2. GROZĪJUMI — grozījuma rindā `Aktuala_liguma_summa` ir līguma summa PĒC
 *      grozījuma (nevis starpība). Saskaitot pamatlīgumu un grozījumu, summa
 *      dubultojas. Ņemam PĒDĒJĀ dokumenta summu ķēdē (Saistita_liguma_ID).
 *   3. VAIRĀKI UZVARĒTĀJI — vispārīgā vienošanās vai piegādātāju apvienība:
 *      pilnā (maksimālā) summa atkārtojas KATRAM uzvarētājam. Piemēri datos:
 *      LVM 398 milj. € ar 56 uzvarētājiem, VAMOIC 9 999 999 999,99 € (sistēmas
 *      "bez ierobežojuma" vērtība) ar 5. Tāpēc glabājam `uzv_skaits`, un panelis
 *      daudzuzvarētāju summas kopsummā NEIESKAITA. Karogs
 *      `Ligums_ir_vispariga_vienosanas` vien NEPIETIEK — minētais LVM līgums
 *      tajā ir "Nē".
 *
 * GDPR: uzvarētāju vidū ir FIZISKAS personas (saimnieciskās darbības veicēji) ar
 * personas kodu un pilnu vārdu — gan ar defisi ('280477-11016'), gan BEZ tās
 * ('12036012678' = Gints Riepnieks), tāpēc de minimis filtra likums (11 cipari)
 * te NESTRĀDĀ. Turklāt ārvalstu fiziskas personas kods var sākties ar 4 kā LV
 * uzņēmuma numurs. Vienīgais drošais tests: uzvarētājs ir `register` tabulā.
 * FAIL-CLOSED — ja pēc filtra paliek kaut viens svešs kods, būve krīt.
 *
 * NEGLABĀJAM: `Izbeigsanas_iemesls` (brīvteksts ar līgumslēdzēju un darbinieku
 * vārdiem) un `Iepirkuma_dalas_nosaukums` (tur avotā jau atrasti BIS numuri
 * personas koda formātā). Paliek tikai `izbeigts` karogs.
 */
function build_iepirkumi_table(PDO $pdo, ?callable $log = null): int {
    $tabulas = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table'
         AND name LIKE 'eis!_e!_iepirkumi!_rezultati!_%' ESCAPE '!' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!$tabulas) return 0;

    $ir_reg = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='register'")->fetchColumn();
    if (!$ir_reg) {
        // Bez register nav ar ko atsijāt fiziskās personas — labāk krist nekā noplūst.
        throw new RuntimeException('iepirkumi: nav register tabulas, uzvarētājus nevar pārbaudīt — būve pārtraukta.');
    }

    // Vienota jēlrindu kopa ar normalizētiem laukiem. Datums avotā ir DD.MM.YYYY;
    // pārvēršam ISO, lai kārtojas un lai panelis nerēķina.
    $sel = [];
    foreach ($tabulas as $t) {
        $sel[] = "SELECT
            CASE WHEN TRIM(IFNULL(Saistita_liguma_ID,'')) GLOB '[0-9]*'
                 THEN TRIM(Saistita_liguma_ID) ELSE TRIM(IFNULL(Noslegta_liguma_dok_ID,'')) END AS ch,
            TRIM(IFNULL(Noslegta_liguma_dok_ID,'')) AS did,
            -- SQLite TRIM() bez otrā argumenta griež TIKAI atstarpes: 10 EIS rindās
            -- uzvarētāja kodā ir tabulācija, un tās klusi izkrita (audits 2026-08-19).
            TRIM(REPLACE(REPLACE(REPLACE(IFNULL(Uzvaretaja_registracijas_numurs,''),
                char(9),' '), char(10),' '), char(13),' ')) AS win,
            TRIM(IFNULL(Iepirkuma_ID,'')) AS iid,
            TRIM(IFNULL(Iepirkuma_nosaukums,'')) AS nos,
            TRIM(IFNULL(Pasutitaja_nosaukums,'')) AS pas,
            TRIM(REPLACE(REPLACE(REPLACE(IFNULL(Pasutitaja_registracijas_numurs,''),
                char(9),' '), char(10),' '), char(13),' ')) AS pasnr,
            TRIM(IFNULL(Proceduras_veids,'')) AS pv,
            TRIM(IFNULL(Liguma_dok_veids,'')) AS dv,
            CAST(NULLIF(TRIM(IFNULL(Aktuala_liguma_summa,'')),'') AS REAL) AS amt,
            CAST(NULLIF(TRIM(IFNULL(Sakotneja_liguma_summa,'')),'') AS REAL) AS samt,
            TRIM(IFNULL(Aktuala_liguma_summas_valuta,'')) AS val,
            CASE WHEN TRIM(IFNULL(Ligums_ir_vispariga_vienosanas,'')) = 'Jā' THEN 1 ELSE 0 END AS vv,
            CASE WHEN Liguma_dok_noslegsanas_datums GLOB '[0-9][0-9].[0-9][0-9].[0-9][0-9][0-9][0-9]'
                 THEN substr(Liguma_dok_noslegsanas_datums,7,4) || '-' ||
                      substr(Liguma_dok_noslegsanas_datums,4,2) || '-' ||
                      substr(Liguma_dok_noslegsanas_datums,1,2)
                 ELSE '' END AS d
        FROM \"$t\"";
    }
    $pdo->exec('DROP TABLE IF EXISTS iepirkumi');
    // Pagaidu tabulas dzīvo savienojumā, nevis failā — nometam, lai funkcija būtu
    // atkārtojama vienā procesā (atkārtota būve, testi).
    foreach (['eis_v', 'eis_ch', 'eis_last'] as $tmp) $pdo->exec("DROP TABLE IF EXISTS temp.\"$tmp\"");
    $pdo->exec('CREATE TEMP TABLE eis_v AS ' . implode(' UNION ALL ', $sel));
    $pdo->exec('CREATE INDEX temp.eis_v_ch ON eis_v(ch)');
    $jel = (int)$pdo->query('SELECT COUNT(*) FROM eis_v')->fetchColumn();

    // Ķēdes līmeņa fakti. `sk` ir kārtošanas atslēga "pēdējais dokuments, kam ir
    // summa": vispirms tie, kam summa > 0 (izbeigšanas ieraksts mēdz būt ar 0 —
    // tad rādām pēdējo īsto līguma vērtību un atsevišķi karogu, ka izbeigts),
    // tad pēc datuma, tad pēc dokumenta numura.
    $pdo->exec("CREATE TEMP TABLE eis_ch AS
        SELECT ch,
            MIN(NULLIF(d,'')) AS d_pirmais,
            MAX(d) AS d_pedejais,
            COUNT(DISTINCT did) AS dok_skaits,
            COUNT(DISTINCT CASE WHEN win <> '' THEN win END) AS uzv_skaits,
            MAX(CASE WHEN dv = 'Līguma izbeigšana' THEN 1 ELSE 0 END) AS izbeigts,
            MAX((CASE WHEN amt IS NOT NULL AND amt <> 0 THEN '1' ELSE '0' END)
                || d || printf('%09d', CAST(did AS INTEGER))) AS sk
        FROM eis_v GROUP BY ch");

    // Pēdējā dokumenta lauki. GROUP BY (nevis DISTINCT), lai uz ķēdi garantēti
    // paliktu tieši viena rinda arī tad, ja avots kādā daļā raksta citu tekstu.
    $pdo->exec("CREATE TEMP TABLE eis_last AS
        SELECT v.ch AS ch, MAX(v.amt) AS amt, MAX(v.samt) AS samt,
               MIN(v.val) AS val, MAX(v.vv) AS vv, MIN(v.iid) AS iid,
               MIN(v.nos) AS nos, MIN(v.pas) AS pas, MIN(v.pasnr) AS pasnr, MIN(v.pv) AS pv
        FROM eis_v v JOIN eis_ch c ON c.ch = v.ch
         AND c.sk = ((CASE WHEN v.amt IS NOT NULL AND v.amt <> 0 THEN '1' ELSE '0' END)
                     || v.d || printf('%09d', CAST(v.did AS INTEGER)))
        GROUP BY v.ch");

    $pdo->exec("CREATE TABLE iepirkumi AS
        SELECT w.win AS regcode, w.ch AS chain_id, l.iid AS iepirkuma_id,
               l.nos AS iep_nosaukums, l.pas AS pasutitajs, l.pasnr AS pasutitaja_regnr,
               l.pv AS proc_veids, l.amt AS summa, l.samt AS sak_summa, l.val AS valuta,
               l.vv AS vv, c.uzv_skaits AS uzv_skaits, c.dok_skaits AS dok_skaits,
               c.d_pirmais AS datums, c.d_pedejais AS pedejais, c.izbeigts AS izbeigts
        FROM (SELECT DISTINCT ch, win FROM eis_v WHERE win <> '') w
        JOIN eis_last l ON l.ch = w.ch
        JOIN eis_ch  c ON c.ch = w.ch
        WHERE w.win IN (SELECT regcode FROM register)");
    $pdo->exec('CREATE INDEX iepirkumi_regcode ON iepirkumi(regcode)');
    $n = (int)$pdo->query('SELECT COUNT(*) FROM iepirkumi')->fetchColumn();

    // Iepirkuma nosaukums ir brīvteksts. Šodien tajā personas kodu nav (pārbaudīti
    // visi 289 tūkst. ieraksti), bet de minimis mācība ir skaidra: brīvteksts agri
    // vai vēlu tādu satur. Maskējam, nevis metam izņēmumu — nosaukumos mēdz būt
    // arī BIS būvatļaujas numuri tādā pašā formātā, un tie būvi apturēt nedrīkst.
    $masko = $pdo->query("SELECT rowid, iep_nosaukums FROM iepirkumi
        WHERE iep_nosaukums GLOB '*[0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9]*'")->fetchAll(PDO::FETCH_ASSOC);
    if ($masko) {
        $upd = $pdo->prepare('UPDATE iepirkumi SET iep_nosaukums = ? WHERE rowid = ?');
        $pdo->beginTransaction();
        foreach ($masko as $r) {
            $upd->execute([preg_replace('/\d{6}-\d{5}/', '…', (string)$r['iep_nosaukums']), (int)$r['rowid']]);
        }
        $pdo->commit();
        if ($log) $log('      + iepirkumi: maskēti personas koda formāta cipari ' . count($masko) . ' nosaukumos');
    }

    foreach ($tabulas as $t) $pdo->exec("DROP TABLE \"$t\"");

    // FAIL-CLOSED pārbaude: katram uzvarētājam jābūt register tabulā un jāizskatās
    // pēc juridiskas personas koda. Ja nav — dzēšam un metam izņēmumu.
    $atlikums = (int)$pdo->query("SELECT COUNT(*) FROM iepirkumi
        WHERE regcode IS NULL OR LENGTH(regcode) <> 11 OR regcode GLOB '*[^0-9]*'
           OR regcode NOT IN (SELECT regcode FROM register)")->fetchColumn();
    if ($atlikums > 0) {
        throw new RuntimeException("iepirkumi: $atlikums rindas ar uzvarētāju ārpus register — būve pārtraukta (var būt fiziskas personas).");
    }
    if ($log) $log("      + iepirkumi: $jel jēlrindas -> $n līgumu ierakstu (" . count($tabulas) . ' gada faili)');
    return $n;
}

/**
 * CFLA ES fondu projekti: no 12 jēltabulām (3 periodi × saraksts/partneri/līgumi/
 * maksājumi) uzbūvē vienu `es_fondi` tabulu ar TRIM lomām un jēltabulas nomet.
 *
 * LOMAS. Uzņēmums ES fondu datos parādās trīs veidos, un nauda katrā nozīmē citu:
 *   `sanemejs`   — finansējuma saņēmējs: projekta attiecināmās izmaksas, ES daļa
 *                  un faktiski izmaksātais.
 *   `partneris`  — sadarbības partneris: avotā NAV naudas, tikai dalība projektā.
 *   `izpilditajs`— iepirkuma līguma izpildītājs projektā: līguma summa bez PVN.
 * Tāpēc kopsummas panelī rāda ATSEVIŠĶI — sajaukt saņemto atbalstu ar nopelnītu
 * piegādes līgumu būtu nepatiesi.
 *
 * KOLONNU NOSAUKUMI PERIODOS ATŠĶIRAS (izpēte to paredzēja): 21-27 periodā ES daļa
 * ir `ProjektamPieskirtaisESFonduLidzfinansejums`, ne `EsFonduLidzfinansejums`;
 * fonds ir `Fonds`, ne `EsFonds`; datumi ir `ProjektaIeviesanas*`; līguma summa ir
 * `SummaBezPvn`, ne `UzProjektuAttiecinamaSummaBezPvn`. AF periodā atbildīgā
 * iestāde ir `NozaresMinistrija`. Kļūda te ir KLUSA — kolonna vienkārši tukša.
 *
 * MAKSĀJUMI NAV ES DAĻA. `veiktie_maksajumi` summa attiecas pret projekta KOPĒJĀM
 * ATTIECINĀMAJĀM izmaksām (mediāna 1,00), nevis pret ES līdzfinansējumu (mediāna
 * 1,18 — starpība ir nacionālais publiskais līdzfinansējums). Rādīt to kā "izmaksāts
 * no ES naudas" pārspīlētu par ~18-43 %.
 *
 * DUBLIKĀTI: iepirkumu līgumu failā 14-20 periodā ir 5 677 burtiski atkārtotas
 * rindas no 57 054 (10 %). Dedublicējam pēc pilnas atslēgas, IESKAITOT iepirkuma
 * identifikācijas numuru — tas ir vienīgais, kas atšķir divus vienā dienā slēgtus
 * vienāda apjoma līgumus (de minimis mācība), bet pašu numuru NEGLABĀJAM.
 *
 * GDPR. Avotā ir fiziskas personas trijās vietās:
 *   1. 1 281 projektam saņēmējs ir fiziska persona; CFLA vārdu un reģ. numuru
 *      dzēš, BET atstāj e-pastu (gitacakule@gmail.com) un DZĪVES adresi. Tāpēc
 *      e-pastu un adresi neglabājam vispār — nevienam no saņēmējiem.
 *   2. 34 projektiem fiziskas personas vārds tomēr PALICIS `IesniedzejaNosaukums`
 *      laukā. Visiem tiem reģ. numurs ir tukšs, tāpēc projekta īpašnieka vārdu
 *      partnera/izpildītāja rindās pārņemam TIKAI tad, ja saņēmējam ir 11 ciparu
 *      juridiskās personas kods. Bez šī nosacījuma personvārds nokļūtu svešu
 *      uzņēmumu lapās (rindu filtrs to nesedz — tas skatās rindas uzņēmumu).
 *   3. Izpildītāju vidū ir fiziskas personas ar vārdu UN personas kodu vienā laukā
 *      ("Viesturs Rudzītis, 010660-10610"), un 2 348 izpildītāju nosaukumi ir
 *      personvārda formā. Tos izmet register filtrs; izpildītāja nosaukumu
 *      neglabājam vispār (lapa jau ir šī uzņēmuma lapa).
 * FAIL-CLOSED: ja pēc filtra paliek rinda ar uzņēmumu ārpus register, būve krīt.
 */
function build_es_fondi_table(PDO $pdo, ?callable $log = null): int {
    $ir = static function (string $t) use ($pdo): bool {
        return (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($t))->fetchColumn();
    };
    // Periodu kolonnu karte. Atslēga = periods, kā to rādīsim.
    $periodi = [
        '14-20' => ['s' => '1420', 'es' => 'EsFonduLidzfinansejums', 'fonds' => 'EsFonds',
                    'sak' => 'SakumaDatums', 'beig' => 'BeiguDatums',
                    'iestade' => 'AtbildigaIestade', 'summa' => 'UzProjektuAttiecinamaSummaBezPvn'],
        '21-27' => ['s' => '2127', 'es' => 'ProjektamPieskirtaisESFonduLidzfinansejums', 'fonds' => 'Fonds',
                    'sak' => 'ProjektaIeviesanasSakumaDatums', 'beig' => 'ProjektaIeviesanasBeiguDatums',
                    'iestade' => 'AtbildigaIestade', 'summa' => 'SummaBezPvn'],
        'AF'    => ['s' => 'af', 'es' => 'EsFonduLidzfinansejums', 'fonds' => 'EsFonds',
                    'sak' => 'SakumaDatums', 'beig' => 'BeiguDatums',
                    'iestade' => 'NozaresMinistrija', 'summa' => 'UzProjektuAttiecinamaSummaBezPvn'],
    ];
    $esosie = [];
    foreach ($periodi as $p => $cfg) {
        if ($ir('es_fondi_saraksts_' . $cfg['s'])) $esosie[$p] = $cfg;
    }
    if (!$esosie) return 0;
    if (!$ir('register')) {
        throw new RuntimeException('es_fondi: nav register tabulas, saņēmējus nevar pārbaudīt — būve pārtraukta.');
    }

    $pdo->exec('DROP TABLE IF EXISTS es_fondi');
    foreach (['esf_proj', 'esf_maks'] as $tmp) $pdo->exec("DROP TABLE IF EXISTS temp.\"$tmp\"");

    // --- Projekti (visi periodi vienā skatā) --------------------------------------
    $sel = [];
    foreach ($esosie as $p => $c) {
        $t = 'es_fondi_saraksts_' . $c['s'];
        $sel[] = "SELECT " . $pdo->quote($p) . " AS periods,
            TRIM(IFNULL(ProjektaNumurs,'')) AS pnr,
            TRIM(IFNULL(ProjektaNosaukums,'')) AS pnos,
            TRIM(IFNULL(PasakumaNosaukums,'')) AS pasakums,
            TRIM(IFNULL({$c['fonds']},'')) AS fonds,
            TRIM(IFNULL({$c['iestade']},'')) AS iestade,
            TRIM(IFNULL(ProjektaStatuss,'')) AS statuss,
            TRIM(IFNULL({$c['sak']},'')) AS sakums,
            TRIM(IFNULL({$c['beig']},'')) AS beigas,
            CAST(NULLIF(TRIM(IFNULL(KopejaisAttiecinamaisFinansejums,'')),'') AS REAL) AS izmaksas,
            CAST(NULLIF(TRIM(IFNULL({$c['es']},'')),'') AS REAL) AS es_fin,
            TRIM(IFNULL(IesniedzejaRegNo,'')) AS sregno,
            -- Saņēmēja nosaukumu pārņemam TIKAI ar juridiskas personas kodu (sk. GDPR augšā).
            CASE WHEN TRIM(IFNULL(IesniedzejaRegNo,'')) GLOB '[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]'
                 THEN TRIM(IFNULL(IesniedzejaNosaukums,'')) ELSE '' END AS snos
        FROM \"$t\"";
    }
    $pdo->exec('CREATE TEMP TABLE esf_proj AS ' . implode(' UNION ALL ', $sel));
    $pdo->exec('CREATE INDEX temp.esf_proj_pnr ON esf_proj(pnr)');

    // --- Veiktie maksājumi (summa uz projektu) ------------------------------------
    $msel = [];
    foreach ($esosie as $p => $c) {
        $t = 'es_fondi_maksajumi_' . $c['s'];
        if ($ir($t)) $msel[] = "SELECT TRIM(IFNULL(ProjektaNumurs,'')) AS pnr,
            CAST(NULLIF(TRIM(IFNULL(Summa,'')),'') AS REAL) AS s FROM \"$t\"";
    }
    if ($msel) {
        $pdo->exec('CREATE TEMP TABLE esf_maks AS SELECT pnr, SUM(s) AS izmaksats FROM ('
            . implode(' UNION ALL ', $msel) . ') GROUP BY pnr');
        $pdo->exec('CREATE INDEX temp.esf_maks_pnr ON esf_maks(pnr)');
    } else {
        $pdo->exec('CREATE TEMP TABLE esf_maks (pnr TEXT, izmaksats REAL)');
    }

    // --- Gala tabula: trīs lomas -------------------------------------------------
    $pdo->exec("CREATE TABLE es_fondi (
        regcode TEXT, loma TEXT, periods TEXT, projekta_nr TEXT, projekta_nosaukums TEXT,
        pasakums TEXT, fonds TEXT, iestade TEXT, statuss TEXT, sakums TEXT, beigas TEXT,
        izmaksas REAL, es_fin REAL, izmaksats REAL, sanemejs TEXT, summa REAL,
        iep_veids TEXT, datums TEXT)");

    $pdo->exec("INSERT INTO es_fondi
        SELECT p.sregno, 'sanemejs', p.periods, p.pnr, p.pnos, p.pasakums, p.fonds, p.iestade,
               p.statuss, p.sakums, p.beigas, p.izmaksas, p.es_fin, m.izmaksats, '', NULL, '', ''
        FROM esf_proj p LEFT JOIN esf_maks m ON m.pnr = p.pnr
        WHERE p.sregno IN (SELECT regcode FROM register)");

    $psel = [];
    foreach ($esosie as $p => $c) {
        $t = 'es_fondi_partneri_' . $c['s'];
        if ($ir($t)) $psel[] = "SELECT TRIM(IFNULL(ProjektaNumurs,'')) AS pnr, TRIM(IFNULL(RegNr,'')) AS rn FROM \"$t\"";
    }
    if ($psel) {
        $pdo->exec("INSERT INTO es_fondi
            SELECT x.rn, 'partneris', p.periods, p.pnr, p.pnos, p.pasakums, p.fonds, p.iestade,
                   p.statuss, p.sakums, p.beigas, NULL, NULL, NULL, p.snos, NULL, '', ''
            FROM (SELECT DISTINCT pnr, rn FROM (" . implode(' UNION ALL ', $psel) . ")) x
            JOIN esf_proj p ON p.pnr = x.pnr
            WHERE x.rn IN (SELECT regcode FROM register)");
    }

    $lsel = [];
    foreach ($esosie as $p => $c) {
        $t = 'es_fondi_ligumi_' . $c['s'];
        if (!$ir($t)) continue;
        // Iepirkuma identifikācijas numurs (pid) ir TIKAI dedublikācijas atslēgā —
        // to neglabājam, jo vienā rindā tas ir personas kods.
        $lsel[] = "SELECT DISTINCT TRIM(IFNULL(ProjektaNumurs,'')) AS pnr,
            TRIM(IFNULL(IzpilditajaRegNo,'')) AS rn,
            TRIM(IFNULL(IepirkumaProcedurasIdentifikacijasNr,'')) AS pid,
            TRIM(IFNULL(IepirkumaProcedurasVeids,'')) AS veids,
            CAST(NULLIF(TRIM(IFNULL({$c['summa']},'')),'') AS REAL) AS summa,
            CASE WHEN TRIM(IFNULL(LemumaPublicesanasDatums,'')) GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]'
                 THEN TRIM(LemumaPublicesanasDatums)
                 WHEN TRIM(IFNULL(IepirkumaIzsludinasanasVaiUzaicinajumaIzsutisanasDatums,'')) GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]'
                 THEN TRIM(IepirkumaIzsludinasanasVaiUzaicinajumaIzsutisanasDatums)
                 ELSE '' END AS dat
        FROM \"$t\"";
    }
    if ($lsel) {
        $pdo->exec("INSERT INTO es_fondi
            SELECT x.rn, 'izpilditajs', p.periods, p.pnr, p.pnos, p.pasakums, p.fonds, p.iestade,
                   p.statuss, p.sakums, p.beigas, NULL, NULL, NULL, p.snos, x.summa, x.veids, x.dat
            FROM (" . implode(' UNION ALL ', $lsel) . ") x
            JOIN esf_proj p ON p.pnr = x.pnr
            WHERE x.rn IN (SELECT regcode FROM register)");
    }

    // PROJEKTA NOSAUKUMA SKRUBIS (audits 2026-08-19, kritisks atradums).
    // CFLA nosaukumos ģimenes ārstu praksēm ir ierakstīts konkrēta cilvēka vārds
    // ("Agneses Ķirses ģimenes ārsta prakses modernizācija"), un šo nosaukumu mēs
    // PĀRŅEMAM partnera un izpildītāja rindās — tātad trešās personas vārds nonāca
    // SVEŠU uzņēmumu lapās, JSON eksportā un MI promptā (988 rindas, 442 uzņēmumi).
    // Register filtrs to nesedz: tas pārbauda rindas subjektu, ne pārņemto tekstu.
    require_once __DIR__ . '/../lib/gdpr_scrub.php';
    // VISAS rindas, bez LIKE priekšatlases: 2026-08-20 audits atrada, ka vārti
    // '%imenes ārst%' izlaida "Ludmilas Zeiļukas ārsta prakses…" (bez vārda "ģimenes")
    // un "Ģimenes ārstes…" (SQLite LIKE lielo Ģ nesaloka). Lēmumu pieņem pati
    // scrub_projekta_nosaukums(); 58k rindu pilna caurskate ir sekundes jautājums.
    $sel = $pdo->query("SELECT rowid, projekta_nosaukums FROM es_fondi")->fetchAll(PDO::FETCH_ASSOC);
    if ($sel) {
        $upd = $pdo->prepare('UPDATE es_fondi SET projekta_nosaukums = ? WHERE rowid = ?');
        $pdo->beginTransaction();
        $mainiti = 0;
        foreach ($sel as $row) {
            $tirs = scrub_projekta_nosaukums((string)$row['projekta_nosaukums']);
            if ($tirs !== (string)$row['projekta_nosaukums']) { $upd->execute([$tirs, (int)$row['rowid']]); $mainiti++; }
        }
        $pdo->commit();
        if ($log && $mainiti) $log("      + es_fondi: no $mainiti projektu nosaukumiem izņemts fiziskas personas vārds (GDPR)");
    }

    $pdo->exec('CREATE INDEX es_fondi_regcode ON es_fondi(regcode)');
    $n = (int)$pdo->query('SELECT COUNT(*) FROM es_fondi')->fetchColumn();

    // Nometam VISAS es_fondi_* jēltabulas, ne tikai to periodu, kam bija `saraksts`:
    // ja kāda perioda saraksta fails neielasījās, pārējie tā faili ar fizisku personu
    // vārdiem un personas kodiem palika datubāzē (audits 2026-08-19).
    foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table'
        AND name LIKE 'es!_fondi!_%' ESCAPE '!'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $pdo->exec("DROP TABLE \"$t\"");
    }

    $atlikums = (int)$pdo->query("SELECT COUNT(*) FROM es_fondi
        WHERE regcode IS NULL OR LENGTH(regcode) <> 11 OR regcode GLOB '*[^0-9]*'
           OR regcode NOT IN (SELECT regcode FROM register)")->fetchColumn();
    if ($atlikums > 0) {
        throw new RuntimeException("es_fondi: $atlikums rindas ar subjektu ārpus register — būve pārtraukta (var būt fiziskas personas).");
    }
    // Otra fail-closed pārbaude: personas koda formāts nevienā glabātā tekstā.
    $pk = (int)$pdo->query("SELECT COUNT(*) FROM es_fondi WHERE
        projekta_nosaukums GLOB '*[0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9]*'
        OR sanemejs GLOB '*[0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9]*'
        OR iep_veids GLOB '*[0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9]*'")->fetchColumn();
    if ($pk > 0) {
        throw new RuntimeException("es_fondi: $pk rindās teksts personas koda formātā — būve pārtraukta.");
    }
    // Fail-closed arī personvārdiem projekta nosaukumā: ja pēc skrubja kaut kas palicis,
    // būve krīt, nevis klusi publicē cilvēka vārdu svešā lapā.
    $palicis = 0;
    foreach ($pdo->query("SELECT DISTINCT projekta_nosaukums FROM es_fondi")->fetchAll(PDO::FETCH_COLUMN) as $nos) {
        if (ir_personvards_projekta_nosaukuma((string)$nos)) $palicis++;
    }
    if ($palicis > 0) {
        throw new RuntimeException("es_fondi: $palicis projektu nosaukumos palicis personvārds — būve pārtraukta.");
    }
    if ($log) {
        $lomas = $pdo->query("SELECT loma, COUNT(*) c FROM es_fondi GROUP BY 1")->fetchAll(PDO::FETCH_KEY_PAIR);
        $apr = [];
        foreach ($lomas as $l => $c) $apr[] = "$l $c";
        $log("      + es_fondi: $n rindas (" . implode(', ', $apr) . '), ' . count($esosie) . ' periodi');
    }
    return $n;
}

/**
 * PTAC (Patērētāju tiesību aizsardzības centrs) — septiņas kopas vienā `ptac`
 * tabulā ar `veids` kolonnu; jēltabulas pēc apstrādes tiek nomestas.
 *
 * DIVAS DAĻAS, ko lapā rāda dažādās vietās:
 *   licences/reģistri — `kredits`, `paradi`, `hipotek`, `stridi` → sadalas/ptac.php
 *   uzraudzība        — `melnais`, `lemums`, `apnemsanas` → sadalas/tiesiskais.php
 *                       (sarkanais panelis lapas augšā; tur tam ir īstā vieta).
 *
 * SODA NAUDAS SLAZDS: avotā summa ir ar KOMATU ('8048,8', '200,8', '14173,8' —
 * 3 no 24). SQLite CAST('8048,8' AS REAL) dod 8048.0, t.i. klusi nogriež centus.
 * Tāpēc pirms CAST komatu aizstājam ar punktu.
 *
 * NEGLABĀJAM `Notikuma apraksts` brīvtekstu: 50 lēmumu aprakstos ir fiziskas
 * personas vārds kopā ar saitēm uz viņa personīgajiem sociālo tīklu profiliem
 * ("Sergejs Šaburovs", https://www.facebook.com/sergejs.saburovs). Strukturētie
 * lauki (numurs, datums, statuss, joma, soda nauda) pasaka to pašu signālu bez
 * personas datiem. Tāpat neglabājam adreses, tālruņus un e-pastus — tie fiziskām
 * personām un individuālajiem komersantiem ir privātie kontakti.
 *
 * GDPR: melnajā sarakstā ir arī 1 fiziska persona (`Komersanta veids` =
 * 'Fiziska persona'). To izmet register filtrs; papildus fail-closed pārbaude.
 */
function build_ptac_table(PDO $pdo, ?callable $log = null): int {
    $ir = static function (string $t) use ($pdo): bool {
        return (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($t))->fetchColumn();
    };
    $tabulas = ['ptac_kreditetaji', 'ptac_paradu_atguveji', 'ptac_hipotek_starpnieki',
                'ptac_melnais_saraksts', 'ptac_lemumi', 'ptac_apnemsanas', 'ptac_stridu_risinataji'];
    $esosas = array_values(array_filter($tabulas, $ir));
    if (!$esosas) return 0;
    if (!$ir('register')) {
        throw new RuntimeException('ptac: nav register tabulas, komersantus nevar pārbaudīt — būve pārtraukta.');
    }

    $pdo->exec('DROP TABLE IF EXISTS ptac');
    $pdo->exec("CREATE TABLE ptac (
        regcode TEXT, veids TEXT, statuss TEXT, numurs TEXT,
        no_dat TEXT, lidz_dat TEXT, apturets TEXT, anulets TEXT,
        joma TEXT, soda_nauda REAL, papildus TEXT)");

    // ISO datums vai tukšums (avotā visi ir YYYY-MM-DD, bet sargājamies).
    $d = static fn(string $c): string =>
        "CASE WHEN TRIM(IFNULL($c,'')) GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]'
              THEN TRIM($c) ELSE '' END";
    $t = static fn(string $c): string => "TRIM(IFNULL($c,''))";
    $reg = "TRIM(IFNULL(\"Komersanta reģistrācijas numurs\",'')) IN (SELECT regcode FROM register)";

    if ($ir('ptac_kreditetaji')) {
        $pdo->exec("INSERT INTO ptac SELECT {$t('"Komersanta reģistrācijas numurs"')}, 'kredits',
            {$t('"Licences statuss"')}, {$t('"Reģistrācijas numurs"')},
            {$d('"Licences spēkā no"')}, '', {$d('"Apturēšanas datums"')}, {$d('"Anulēšanas datums"')},
            '', NULL, {$t('"Kredīta veids"')}
            FROM ptac_kreditetaji WHERE $reg");
    }
    if ($ir('ptac_paradu_atguveji')) {
        // Pārlicencēšana: ja tā ir, spēkā esošais periods ir tās, ne sākotnējās licences.
        $pdo->exec("INSERT INTO ptac SELECT {$t('"Komersanta reģistrācijas numurs"')}, 'paradi',
            {$t('"Licences statuss"')},
            CASE WHEN {$t('"Pārlicencēšanas reģistrācijas numurs"')} <> ''
                 THEN {$t('"Pārlicencēšanas reģistrācijas numurs"')} ELSE {$t('"Licences reģistrācijas numurs"')} END,
            CASE WHEN {$t('"Pārlicencēšanas reģistrācijas numurs"')} <> ''
                 THEN {$d('"Spēkā no"')} ELSE {$d('"Licence spēkā no"')} END,
            CASE WHEN {$t('"Pārlicencēšanas reģistrācijas numurs"')} <> ''
                 THEN {$d('"Beigu datums"')} ELSE {$d('"Licences beigu datums"')} END,
            {$d('"Licences apturēšanas datums"')}, {$d('"Licences anulēšanas datums"')},
            '', NULL, ''
            FROM ptac_paradu_atguveji WHERE $reg");
    }
    if ($ir('ptac_hipotek_starpnieki')) {
        $pdo->exec("INSERT INTO ptac SELECT {$t('"Komersanta reģistrācijas numurs"')}, 'hipotek',
            {$t('"Licences statuss"')}, {$t('"Reģistrācijas numurs"')},
            {$d('"Reģistrācijas datums"')}, {$d('"Izslēgšanas no reģistra datums"')}, '', '',
            '', NULL, {$t('"Kredītstarpniecības veids"')}
            FROM ptac_hipotek_starpnieki WHERE $reg");
    }
    if ($ir('ptac_melnais_saraksts')) {
        $pdo->exec("INSERT INTO ptac SELECT {$t('"Komersanta reģistrācijas numurs"')}, 'melnais',
            {$t('"Lēmuma statuss"')}, {$t('"Lēmuma numurs"')}, {$d('"Lēmuma datums"')}, '', '', '',
            '', NULL, ''
            FROM ptac_melnais_saraksts WHERE $reg");
    }
    if ($ir('ptac_lemumi')) {
        $pdo->exec("INSERT INTO ptac SELECT {$t('"Komersanta reģistrācijas numurs"')}, 'lemums',
            {$t('"Lēmuma statuss"')}, {$t('"Lēmuma numurs"')}, {$d('"Lēmuma datums"')}, '', '', '',
            {$t('"Joma"')},
            CAST(NULLIF(REPLACE(TRIM(IFNULL(\"Soda nauda\",'')), ',', '.'), '') AS REAL), ''
            FROM ptac_lemumi WHERE $reg");
    }
    if ($ir('ptac_apnemsanas')) {
        $pdo->exec("INSERT INTO ptac SELECT {$t('"Komersanta reģistrācijas numurs"')}, 'apnemsanas',
            '', {$t('"DVS dokumenta reģistrācijas numurs"')}, {$d('"Notikuma datums"')}, '', '', '',
            '', NULL, {$t('"Apņemšanās veids"')}
            FROM ptac_apnemsanas WHERE $reg");
    }
    if ($ir('ptac_stridu_risinataji')) {
        // Šajā failā komersanta kolonna saucas citādi.
        $pdo->exec("INSERT INTO ptac SELECT {$t('"Reģistrācijas numurs"')}, 'stridi',
            '', '', {$d('"Publicēšanas datums"')}, {$d('"Izslēgšanas datums"')}, '', '',
            '', NULL, {$t('"Strīdu risināšanas veids"')}
            FROM ptac_stridu_risinataji
            WHERE {$t('"Reģistrācijas numurs"')} IN (SELECT regcode FROM register)");
    }

    // Avotā (melnais saraksts) viens lēmums mēdz būt ierakstīts divreiz burtiski
    // identiski — bez dedupes tas sarkanajā panelī rādījās un skaitījās dubultā
    // (audits 2026-08-20: 10 lēmumi).
    $pdo->exec('DELETE FROM ptac WHERE rowid NOT IN (SELECT MIN(rowid) FROM ptac
        GROUP BY regcode, veids, statuss, numurs, no_dat, lidz_dat, apturets, anulets,
                 joma, soda_nauda, papildus)');
    $pdo->exec('CREATE INDEX ptac_regcode ON ptac(regcode)');
    $n = (int)$pdo->query('SELECT COUNT(*) FROM ptac')->fetchColumn();
    foreach ($esosas as $tab) $pdo->exec("DROP TABLE \"$tab\"");

    $atlikums = (int)$pdo->query("SELECT COUNT(*) FROM ptac
        WHERE regcode IS NULL OR LENGTH(regcode) <> 11 OR regcode GLOB '*[^0-9]*'
           OR regcode NOT IN (SELECT regcode FROM register)")->fetchColumn();
    if ($atlikums > 0) {
        throw new RuntimeException("ptac: $atlikums rindas ar komersantu ārpus register — būve pārtraukta.");
    }
    if ($log) {
        $v = $pdo->query("SELECT veids, COUNT(*) c FROM ptac GROUP BY 1 ORDER BY 1")->fetchAll(PDO::FETCH_KEY_PAIR);
        $apr = [];
        foreach ($v as $kk => $cc) $apr[] = "$kk $cc";
        $log("      + ptac: $n rindas (" . implode(', ', $apr) . ')');
    }
    return $n;
}

/**
 * Papildu reģistru tabulas: BIS, VVD (vide), ZVA (farmācija) un VID statusi.
 *
 * Visas pēc viena parauga: jēltabulas ielasās parastajā konveijerā, šī funkcija
 * no tām izveido kompaktas tabulas ar `veids` kolonnu, atsijā pret `register` un
 * jēltabulas nomet. Register filtrs te ir GAN GDPR aizsardzība (VVD C kategorijā
 * un VID minimālās algas kopā operators/darba devējs var būt FIZISKA persona —
 * minimālās algas failā kolonna tā arī saucas "reg_kods_vai_personas_koda_otra_dala"
 * un blakus ir dzimšanas gads un uzvārds), GAN izmēra samazinājums.
 *
 * NEGLABĀJAM: operatoru/īpašnieku vārdus (lapa jau ir šī uzņēmuma lapa), adreses,
 * koordinātas, e-pastus, tālruņus, kā arī ZVA `pharm_manager` un
 * `responsible_person` — tie ir konkrētu cilvēku vārdi.
 *
 * FAIL-CLOSED: ja pēc filtra kādā tabulā paliek rinda ar subjektu ārpus register,
 * būve krīt.
 */
function build_papildu_tabulas(PDO $pdo, ?callable $log = null): int {
    $ir = static function (string $t) use ($pdo): bool {
        return (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($t))->fetchColumn();
    };
    if (!$ir('register')) {
        throw new RuntimeException('papildu tabulas: nav register — būve pārtraukta.');
    }
    // ISO datums vai tukšums.
    $iso = static fn(string $c): string =>
        "CASE WHEN TRIM(IFNULL($c,'')) GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]*'
              THEN substr(TRIM($c),1,10) ELSE '' END";
    // DD.MM.YYYY -> ISO (SLO un minimālās algas kopas).
    $lv = static fn(string $c): string =>
        "CASE WHEN TRIM(IFNULL($c,'')) GLOB '[0-9][0-9].[0-9][0-9].[0-9][0-9][0-9][0-9]*'
              THEN substr(TRIM($c),7,4) || '-' || substr(TRIM($c),4,2) || '-' || substr(TRIM($c),1,2)
              ELSE '' END";
    $t = static fn(string $c): string => "TRIM(IFNULL($c,''))";
    $num = static fn(string $c): string => "CAST(NULLIF(TRIM(IFNULL($c,'')),'') AS REAL)";
    $jel = [];
    $kopa = 0;

    // ---- BIS: būvkomersanta gada rādītāji + statusu vēsture ---------------------
    if ($ir('bis_darbiba') || $ir('bis_statusi')) {
        $pdo->exec('DROP TABLE IF EXISTS bis');
        $pdo->exec("CREATE TABLE bis (regcode TEXT, veids TEXT, gads TEXT, ienemumi REAL,
            apjoms_lv REAL, apjoms_arpus REAL, nodarb INTEGER, datums TEXT, statuss TEXT, bis_nr TEXT)");
        if ($ir('bis_darbiba')) {
            $pdo->exec("INSERT INTO bis SELECT {$t('Registracijas_numurs_mitnes_valsti')}, 'darbiba',
                {$t('Kalendarais_gads_par_kuru_sniegti_dati')},
                {$num('Kopejie_ienemumi_par_sniegtajiem_buvniecibas_pakalpojumiem_eur')},
                IFNULL({$num('Kopejais_sniegto_buvdarbu_apjoms_latvija_eur')},0)
                    + IFNULL({$num('Kopejais_sniegto_arhitekturas_un_inzeniertehnisko_pakalpojumu_apjoms_latvija_eur')},0),
                IFNULL({$num('Kopejais_sniegto_buvdarbu_apjoms_arpus_latvijas_eur')},0)
                    + IFNULL({$num('Kopejais_sniegto_arhitekturas_un_inzeniertehnisko_pakalpojumu_apjoms_arpus_latvijas_eur')},0),
                CAST(NULLIF({$t('Kopa_buvnieciba_nodarbinato_skaits')},'') AS INTEGER),
                {$iso('Datu_iesniegsanas_datums')}, '', {$t('Registracijas_numurs_registra')}
                FROM bis_darbiba WHERE {$t('Registracijas_numurs_mitnes_valsti')} IN (SELECT regcode FROM register)");
            $jel[] = 'bis_darbiba';
        }
        if ($ir('bis_statusi')) {
            $pdo->exec("INSERT INTO bis SELECT {$t('Registracijas_numurs_mitnes_valsti')}, 'statuss',
                '', NULL, NULL, NULL, NULL, {$iso('Statusa_uzstadisanas_datums')},
                {$t('Statuss')}, {$t('Registracijas_numurs_registra')}
                FROM bis_statusi WHERE {$t('Registracijas_numurs_mitnes_valsti')} IN (SELECT regcode FROM register)");
            $jel[] = 'bis_statusi';
        }
        // BIS avots burtiski atkārto rindas (3 459 'statuss' + 47 'darbiba' dublikāti,
        // audits 2026-08-20) — viena vēstures rinda lapā rādījās līdz 7 reizēm.
        $pdo->exec('DELETE FROM bis WHERE rowid NOT IN (SELECT MIN(rowid) FROM bis
            GROUP BY regcode, veids, gads, ienemumi, apjoms_lv, apjoms_arpus,
                     nodarb, datums, statuss, bis_nr)');
        $pdo->exec('CREATE INDEX bis_regcode ON bis(regcode)');
        $kopa += (int)$pdo->query('SELECT COUNT(*) FROM bis')->fetchColumn();
    }

    // ---- VVD: vides atļaujas un licences ---------------------------------------
    $vvd = ['vvd_ab', 'vvd_c', 'vvd_atkritumi', 'vvd_udens', 'vvd_zemes_dzilas'];
    if (array_filter($vvd, $ir)) {
        $pdo->exec('DROP TABLE IF EXISTS vide');
        $pdo->exec("CREATE TABLE vide (regcode TEXT, veids TEXT, numurs TEXT, statuss TEXT,
            no_dat TEXT, lidz_dat TEXT, apturets TEXT, nosaukums TEXT, apraksts TEXT)");
        if ($ir('vvd_ab')) {
            $pdo->exec("INSERT INTO vide SELECT {$t('Operatora_registracijas_numurs')}, 'ab',
                {$t('Atlaujas_numurs')}, {$t('Statuss')}, {$iso('Atlaujas_izsniegsanas_datums')},
                {$iso('Atlaujas_deriguma_termins')}, {$iso('Apturesanas_datums')},
                {$t('Iekartas_nosaukums')}, {$t('Piesarnojoso_darbibas_veids')}
                FROM vvd_ab WHERE {$t('Operatora_registracijas_numurs')} IN (SELECT regcode FROM register)");
            $jel[] = 'vvd_ab';
        }
        if ($ir('vvd_c')) {
            $pdo->exec("INSERT INTO vide SELECT {$t('Operatora_registracijas_numurs')}, 'c',
                {$t('Registracijas_numurs')}, {$t('Statuss')}, {$iso('Registracijas_datums')}, '', '',
                {$t('Iekartas_nosaukums')}, {$t('Piesarnojoso_darbibas_veids')}
                FROM vvd_c WHERE {$t('Operatora_registracijas_numurs')} IN (SELECT regcode FROM register)");
            $jel[] = 'vvd_c';
        }
        if ($ir('vvd_atkritumi')) {
            $pdo->exec("INSERT INTO vide SELECT {$t('Atkritumu_apsaimniekotaja_registracijas_nr')}, 'atkritumi',
                {$t('Atlaujas_numurs')}, {$t('Statuss')}, {$iso('Deriga_no')}, {$iso('Deriga_lidz')},
                {$iso('Apturesanas_datums')}, '',
                -- Avotā darbību kolonnās VIENMĒR ir 'Jā'/'Nē' — salīdzinājums <> ''
                -- deva visas darbības katrai atļaujai (audits 2026-08-20). Un vēl
                -- divas darbības (pārkraušana, atrakšana) vispār netika lasītas.
                TRIM(CASE WHEN {$t('Savaksana_vai_Savaksana_un_parvadasana')} = 'Jā' THEN 'savākšana, ' ELSE '' END ||
                     CASE WHEN {$t('Parvadasana')} = 'Jā' THEN 'pārvadāšana, ' ELSE '' END ||
                     CASE WHEN {$t('Parkrausana_un_uzglabasana')} = 'Jā' THEN 'pārkraušana, ' ELSE '' END ||
                     CASE WHEN {$t('Skirosana_un_uzglabasana')} = 'Jā' THEN 'šķirošana, ' ELSE '' END ||
                     CASE WHEN {$t('Uzglabasana')} = 'Jā' THEN 'uzglabāšana, ' ELSE '' END ||
                     CASE WHEN {$t('Atraksana')} = 'Jā' THEN 'atrakšana, ' ELSE '' END, ', ')
                FROM vvd_atkritumi WHERE {$t('Atkritumu_apsaimniekotaja_registracijas_nr')} IN (SELECT regcode FROM register)");
            $jel[] = 'vvd_atkritumi';
        }
        if ($ir('vvd_udens')) {
            $pdo->exec("INSERT INTO vide SELECT {$t('"Operatora reģistrācijas numurs"')}, 'udens',
                {$t('"Atļaujas numurs"')}, {$t('Statuss')}, {$iso('"Atļauja derīga no"')},
                {$iso('"Derīga līdz"')}, '', '', {$t('"Paredzētās darbības raksturojums"')}
                FROM vvd_udens WHERE {$t('"Operatora reģistrācijas numurs"')} IN (SELECT regcode FROM register)");
            $jel[] = 'vvd_udens';
        }
        if ($ir('vvd_zemes_dzilas')) {
            $pdo->exec("INSERT INTO vide SELECT {$t('Zemes_dzilu_izmantotaja_registracijas_nr')}, 'zemes',
                {$t('Licences_numurs')}, {$t('Statuss')}, {$iso('Licence_deriga_no')},
                {$iso('Licence_deriga_lidz')}, {$iso('Licences_atcelsanas_datums')}, '',
                {$t('Zemes_dzilu_izmantosanas_veids')}
                FROM vvd_zemes_dzilas WHERE {$t('Zemes_dzilu_izmantotaja_registracijas_nr')} IN (SELECT regcode FROM register)");
            $jel[] = 'vvd_zemes_dzilas';
        }
        $pdo->exec('CREATE INDEX vide_regcode ON vide(regcode)');
        $kopa += (int)$pdo->query('SELECT COUNT(*) FROM vide')->fetchColumn();
    }

    // ---- APUS: atkritumu gada pārskata B daļa (daudzumi tonnās) -----------------
    // Atdalītājs te ir SEMIKOLS (noklusējums), atšķirībā no pārējām VVD kopām.
    // Skaitļi ar komatu kā decimālzīmi — pirms CAST komats jāaizstāj ar punktu,
    // citādi SQLite '12,5' nolasa kā 12 (tā pati kļūda, kas PTAC soda naudām).
    $apus = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'
        AND name LIKE 'apus!_atkritumi!_%' ESCAPE '!' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    if ($apus) {
        $k_reg = '"Atskaites organizācijas reģ. numurs"';
        $k_gads = '"Pārskata gads"';
        $k_nos = '"Atkritumu nosaukums [B1]"';
        $k_kl = '"Atkritumu klases kods [B2]"';
        $k_rad = '"Radītais atkritumu daudzums gadā (t) [B6]"';
        $k_par = '"Operatora/komers. darbības ar atkritumiem gada laikā(pārstrād. daudzums, tonnās) [B7]"';
        $k_apg = '"Operatora/komers. darbības ar atkritumiem gada laikā (apglabātais daudzums,tonnās) [B7]"';
        $k_nod = '"Citam operatoram vai komersantam nodotie atkritumi (Nodots pārstrādei gadā) (t) [B8]"';
        $k_nap = '"Citam operatoram vai komersantam nodotie atkritumi (Nodots apglabāšanai gadā, t) [B8]"';
        // B9 (izvests/eksportēts) agrāk netika lasīts vispār: 73 rindās pazuda
        // 44 054 t, un 23 uzņēmumiem tas bija VIENĪGAIS daudzums (audits 2026-08-20).
        $k_izp = '"Izvestie/eksportētie atkritumi (Izvests/Eksportēts pārstrādei gadā) (t) [B9]"';
        $k_iza = '"Izvestie/eksportētie atkritumi (Izvests/Eksportēts apglabāšanai gadā) (t) [B9]"';
        $k_uzk = '"Operatora vai komersanta uzkrātais atkritumu daudzums gada beigās (t) [B10]"';
        $t_num = static fn(string $c): string =>
            "CAST(NULLIF(REPLACE(TRIM(IFNULL($c,'')), ',', '.'), '') AS REAL)";
        $pdo->exec('DROP TABLE IF EXISTS atkritumi');
        // APGLABĀŠANA (izgāztuvē) ir atsevišķa no PĀRSTRĀDES, un agrāk to nelasījām
        // vispār — 12 786 t pazuda, un 118 ierakstiem visi skaitļi bija nulles
        // (audits 2026-08-19). Tagad glabājam abus un sadaļā saucam "apsaimniekots".
        $pdo->exec('CREATE TABLE atkritumi (regcode TEXT, gads TEXT, nosaukums TEXT, klase TEXT,
            radits REAL, parstradats REAL, apglabats REAL, nodots REAL, nodots_apglabasanai REAL,
            izvests_parstradei REAL, izvests_apglabasanai REAL, uzkrats REAL)');
        foreach ($apus as $tab) {
            $pdo->exec("INSERT INTO atkritumi SELECT {$t($k_reg)}, {$t($k_gads)}, {$t($k_nos)}, {$t($k_kl)},
                {$t_num($k_rad)}, {$t_num($k_par)}, {$t_num($k_apg)}, {$t_num($k_nod)},
                {$t_num($k_nap)}, {$t_num($k_izp)}, {$t_num($k_iza)}, {$t_num($k_uzk)}
                FROM \"$tab\" WHERE {$t($k_reg)} IN (SELECT regcode FROM register)");
            $jel[] = $tab;
        }
        $pdo->exec('CREATE INDEX atkritumi_regcode ON atkritumi(regcode)');
        $kopa += (int)$pdo->query('SELECT COUNT(*) FROM atkritumi')->fetchColumn();
    }

    // ---- ZVA: farmaceitiskās darbības uzņēmumi ---------------------------------
    if ($ir('zva_fdu')) {
        $pdo->exec('DROP TABLE IF EXISTS zva');
        $pdo->exec("CREATE TABLE zva (regcode TEXT, veids TEXT, kods TEXT, statuss TEXT,
            no_dat TEXT, lidz_dat TEXT, objekts TEXT)");
        // lic_status: 4 = spēkā, 3 = apturēta (sakrīt ar suspended karogu).
        $pdo->exec("INSERT INTO zva SELECT {$t('reg_num')}, {$t('object_type_txt')}, {$t('lic_code')},
            CASE WHEN {$t('suspended')} = '1' THEN 'Apturēta' ELSE 'Spēkā' END,
            {$iso('valid_from')}, {$iso('valid_to')}, {$t('object_name')}
            FROM zva_fdu WHERE {$t('reg_num')} IN (SELECT regcode FROM register)");
        $pdo->exec('CREATE INDEX zva_regcode ON zva(regcode)');
        $jel[] = 'zva_fdu';
        $kopa += (int)$pdo->query('SELECT COUNT(*) FROM zva')->fetchColumn();
    }

    // ---- VID statusi: SLO, minimālās algas maksātāji, PVN grupas ----------------
    $vid = ['slo_registrs', 'vid_minalgas', 'vid_pvn_grupas'];
    if (array_filter($vid, $ir)) {
        $pdo->exec('DROP TABLE IF EXISTS vid_statusi');
        $pdo->exec("CREATE TABLE vid_statusi (regcode TEXT, veids TEXT, statuss TEXT,
            no_dat TEXT, lidz_dat TEXT, papildus TEXT)");
        if ($ir('slo_registrs')) {
            $pdo->exec("INSERT INTO vid_statusi SELECT {$t('Registracijas_kods')}, 'slo',
                {$t('Statuss')}, {$lv('Speka_no')}, {$lv('Speka_lidz')}, {$t('Darbibas_jomas_nosaukums')}
                FROM slo_registrs WHERE {$t('Registracijas_kods')} IN (SELECT regcode FROM register)");
            $jel[] = 'slo_registrs';
        }
        if ($ir('vid_minalgas')) {
            // Reģ. kods avotā ir APOSTROFOS ('40103291885') tāpat kā citās VID kopās.
            $mk = "TRIM(REPLACE(IFNULL(Darba_deveja_reg_kods_vai_personas_koda_otra_dala,''), '''', ''))";
            $pdo->exec("INSERT INTO vid_statusi SELECT $mk, 'minalga', '',
                {$lv('Periods_no')}, {$lv('Periods_lidz')}, ''
                FROM vid_minalgas WHERE $mk IN (SELECT regcode FROM register)");
            $jel[] = 'vid_minalgas';
        }
        if ($ir('vid_pvn_grupas')) {
            $pdo->exec("INSERT INTO vid_statusi SELECT {$t('Dalibnieka_NMR_kods')}, 'pvn_grupa',
                CASE WHEN {$t('PVN_grupas_galvenais_uznemums')} = 'Ir' THEN 'Galvenais uzņēmums' ELSE 'Dalībnieks' END,
                '', '', {$t('PVN_grupas_nosaukums')}
                FROM vid_pvn_grupas WHERE {$t('Dalibnieka_NMR_kods')} IN (SELECT regcode FROM register)");
            $jel[] = 'vid_pvn_grupas';
        }
        $pdo->exec('CREATE INDEX vid_statusi_regcode ON vid_statusi(regcode)');
        $kopa += (int)$pdo->query('SELECT COUNT(*) FROM vid_statusi')->fetchColumn();
    }

    foreach ($jel as $tab) $pdo->exec("DROP TABLE IF EXISTS \"$tab\"");

    foreach (['bis', 'vide', 'zva', 'vid_statusi', 'atkritumi'] as $tab) {
        if (!$ir($tab)) continue;
        $atl = (int)$pdo->query("SELECT COUNT(*) FROM \"$tab\"
            WHERE regcode IS NULL OR LENGTH(regcode) <> 11 OR regcode GLOB '*[^0-9]*'
               OR regcode NOT IN (SELECT regcode FROM register)")->fetchColumn();
        if ($atl > 0) {
            throw new RuntimeException("$tab: $atl rindas ar subjektu ārpus register — būve pārtraukta.");
        }
    }
    if ($log && $kopa) {
        $apr = [];
        foreach (['bis', 'vide', 'zva', 'vid_statusi', 'atkritumi'] as $tab) {
            if ($ir($tab)) $apr[] = $tab . ' ' . (int)$pdo->query("SELECT COUNT(*) FROM \"$tab\"")->fetchColumn();
        }
        $log('      + papildu reģistri: ' . implode(', ', $apr));
    }
    return $kopa;
}

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

    // GDPR filtri PIRMS visa pārējā un ĀRPUS kopīgā try bloka (2026-08-19 audits):
    // agrāk fix_deminimis_personas() bija trešā vienā try ķēdē kopā ar
    // fix_rounding_flags(), kam pašam nav izņēmumdrošības un kas uzreiz vaicā
    // financial_statements. Ja tā tabula kādā būvē nebūtu ielasījusies, izņēmums
    // ķēdi pārtrauktu, ārējais catch to apēstu ar maldinošu "Vienību karogu
    // pārbaudes kļūda", un personas dati klusi paliktu datubāzē. Tagad tie krīt
    // ar troksni: kļūda personu dzēšanā PĀRTRAUC būvi, nevis tiek nolikta žurnālā.
    fix_deminimis_personas($pdo, $log);

    // Arī iepirkumi ir personas datu filtrs (uzvarētāju vidū ir saimnieciskās
    // darbības veicēji ar personas kodu), tāpēc tas pats režīms: ārpus try, kļūda
    // pārtrauc būvi. Turklāt tas nomet ~184 MB jēltabulu, tāpēc pēc tā vajag VACUUM.
    $iep = build_iepirkumi_table($pdo, $log);
    // Tas pats režīms: ES fondu kopās fiziskas personas ir gan saņēmēju, gan
    // izpildītāju vidū, un daļai vārds ir palicis arī tur, kur CFLA to dzēsa.
    $iep += build_es_fondi_table($pdo, $log);
    // PTAC: melnajā sarakstā ir arī fiziska persona, tāpēc tas pats fail-closed režīms.
    $iep += build_ptac_table($pdo, $log);
    // BIS, VVD, ZVA un VID papildu reģistri — arī ar fiziskām personām avotā.
    $iep += build_papildu_tabulas($pdo, $log);

    try {
        fix_rounding_flags($pdo, $log);
        fix_apostrofu_kodus($pdo, $log);
    } catch (Throwable $e) {
        if ($log) $log("      ! Vienību karogu pārbaudes kļūda: " . $e->getMessage());
    }

    // VACUUM tikai tad, ja tiešām nometām jēltabulas: bez tā fails paliek par
    // ~184 MB lielāks nekā saturs, un tas viss aizceļo uz serveri ar rsync.
    if ($iep > 0) {
        try {
            $pdo->exec('VACUUM');
            if ($log) $log('      + DB saspiesta (VACUUM pēc EIS jēltabulu nomešanas)');
        } catch (Throwable $e) {
            if ($log) $log('      ! VACUUM neizdevās: ' . $e->getMessage());
        }
    }

    $pdo = null;
    if ($log) $log("   ✅ ur_data.db sagatavota: $db_path");
}
