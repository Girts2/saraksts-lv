<?php
/**
 * registrs/build/avotu_kolonnas.php — avotu kolonnu līgums (audits 2026-10-14).
 *
 * KĀPĒC. data.gov.lv u.c. avoti reizēm pārsauc vai izmet kolonnas. Līdz šim tas
 * beidzās vienā no trim sliktiem veidiem (visi pārbaudīti ar būves pašas funkcijām):
 *  - SQL ar kolonnu "pēdiņās" (PTAC, VVD ūdens, APUS): SQLite trūkstošu identifikatoru
 *    pēdiņās KLUSI pārvērš par teksta konstanti — PTAC soda naudas 1 056 473 € kļuva
 *    par 0, statuss visiem "Lēmuma statuss", pārsaukts reģ. nr. → 0 rindu;
 *  - SQL bez pēdiņām: "no such column" ārpus try → krīt VISA nakts būve, un visa
 *    vietne paliek uz vecajiem datiem (arī tie avoti, kas nemainījās);
 *  - lapā data_fetcher norij SQL kļūdu → riska tabula pazūd → zaļa riska josla.
 *
 * TRĪS LIKUMI:
 *  1. AVOTU_KOLONNAS — tikai ATSLĒGU un LOĢIKAS kolonnas (meklēšana, savienojumi, aprēķini,
 *     statusa/riska nozīme). Ja kādas trūkst (vai tabula neielasījās), ŠĪ tabula paliek
 *     iepriekšējā versijā no dzīvās ur_data.db; pārējā būve turpinās. Tīri attēlošanas
 *     kolonnas te APZINĀTI nav: to likumīga izņemšana (piem. UR izmet birth_date) nedrīkst
 *     iesaldēt tabulu un mūžīgi publicēt vecos datus (recenzija 2026-10-14).
 *  2. AVOTU_PERSONU_KOLONNAS — personu tabulām ATĻAUTO kolonnu saraksts. Kolonna, kuras tur
 *     nav (jauna vai pārsaukta, piem. birth_date → dzimsanas_datums), tiek IZMESTA: lapa
 *     maskē personas datus PĒC kolonnas nosaukuma (page_builder MASKING_CONFIG, gdpr_scrub),
 *     un nezināmu kolonnu tā rādītu nemaskētu. Izņemta zināma kolonna — vienkārši pieņemts.
 *  3. Atvasināto tabulu avoti (EIS, ES fondi, PTAC, BIS, VVD, APUS, ZVA, VID statusi) — to
 *     kolonnu sarakstus tur paši būvētāji convert.php blakus savam SQL un sauc
 *     avotu_atvasinatas_derigas(). Tur pārnes ATVASINĀTO tabulu, bet jēltabulas NOMET:
 *     tajās ir fizisku personu dati, ko tikai būvētājs prot atsijāt (fail-closed).
 *
 * Katra līguma darbība nonāk avotu_bridinajumi() sarakstā — build_all.php to izvada būves
 * žurnāla beigās (to redz admin paneļa žurnāla aste) un applog dienas žurnālā.
 */
declare(strict_types=1);

const AVOTU_KOLONNAS = [
    // ---- UR pamata tabulas -----------------------------------------------------------
    'register' => ['regcode', 'name', 'name_before_quotes', 'name_in_quotes', 'type',
                   'registered', 'terminated', 'closed', 'atvk'],
    'financial_statements' => ['id', 'legal_entity_registration_number', 'year', 'source_type',
                               'rounded_to_nearest', 'currency', 'employees', 'created_at'],
    // PZA un bilances lauki: visi, ko lieto aprēķini (Sankey, koeficienti, sadaļas) — pazudis
    // lauks tur nedotu kļūdu, bet klusu nulli aprēķinā.
    'income_statements' => ['statement_id', 'net_turnover', 'by_nature_inventory_change',
        'by_nature_long_term_investment_expenses', 'by_nature_other_operating_revenues',
        'by_nature_material_expenses', 'by_nature_labour_expenses', 'by_nature_depreciation_expenses',
        'by_function_cost_of_goods_sold', 'by_function_gross_profit', 'by_function_selling_expenses',
        'by_function_administrative_expenses', 'by_function_other_operating_revenues', 'other_operating_expenses',
        'equity_investment_earnings', 'other_long_term_investment_earnings', 'other_interest_revenues',
        'investment_fair_value_adjustments', 'interest_expenses', 'extra_revenues', 'extra_expenses',
        'income_before_income_taxes', 'provision_for_income_taxes', 'other_taxes', 'extra_dividends', 'net_income'],
    'balance_sheets' => ['statement_id', 'cash', 'marketable_securities', 'accounts_receivable', 'inventories',
        'total_current_assets', 'investments', 'fixed_assets', 'intangible_assets', 'total_non_current_assets',
        'total_assets', 'future_housing_repairs_payments', 'current_liabilities', 'non_current_liabilities',
        'provisions', 'equity'],
    'cash_flow_statements' => ['statement_id'],
    // ---- personas (atslēgas + Pensionāra loģika: maskētais kods dod dzimšanas gadu) ------
    'members' => ['id', 'legal_entity_registration_number', 'at_legal_entity_registration_number', 'entity_type'],
    'officers' => ['id', 'legal_entity_registration_number', 'at_legal_entity_registration_number',
                   'latvian_identity_number_masked'],
    'beneficial_owners' => ['legal_entity_registration_number', 'latvian_identity_number_masked'],
    'stockholders' => ['id', 'legal_entity_registration_number', 'at_legal_entity_registration_number', 'entity_type'],
    'members_joint_owners' => ['member_id'],
    'stockholders_joint_owners' => ['member_id'],
    // ---- riska tabulas: trūkstoša kolonna = viltus "risku nav" (tabula pazūd) vai viltus
    //      "aktīvs process" (proceeding_ended_on) -------------------------------------
    'insolvency_legal_person_proceeding' => ['debtor_registration_number', 'proceeding_started_on',
                                             'proceeding_ended_on', 'proceeding_form'],
    'liquidations' => ['legal_entity_registration_number'],
    'suspensions_prohibitions' => ['legal_entity_registration_number'],
    'securing_measures' => ['legal_entity_registration_number'],
    'sanctions' => ['sanctions_subject_registration_number'],
    'pdb_saimndarbibaaptureta_odata' => ['Registracijas_kods', 'Aizliegts_veikt_darijumus_no',
                                         'Aizliegts_veikt_darijumus_lidz', 'Lemuma_par_atjaunosanu_datums'],
    // ---- VID -----------------------------------------------------------------------------
    'pdb_pvnmaksataji_odata' => ['Numurs', 'Aktivs', 'Registrets', 'Izslegts'],
    'pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata' => ['Registracijas_kods', 'Taksacijas_gads',
        'Pamatdarbibas_NACE_kods', 'Juridiska_adrese_ATVK_nosaukums',
        'Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR', 'Taja_skaita_IIN', 'Taja_skaita_VSAOI',
        'Videjais_nodarbinato_personu_skaits_cilv'],
    'pdb_samaksato_nodoklu_kopsummas_cet' => ['Registracijas_kods', 'Taksacijas_gads_ceturksnis',
        'Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR', 'Taja_skaita_IIN_summa',
        'Taja_skaita_VSAOI_summa', 'Videjais_nodarbinato_personu_skaits_cilv'],
    'reitings_uznemumi' => ['Registracijas_kods', 'Reitings'],
    // de minimis: GDPR filtrs (person_reg_nr, project_title) un paneļa dedup atslēga
    // (datums|sniedzējs|programma|instruments|summa|projekts) — visas ir loģika.
    'deminimis' => ['person_reg_nr', 'project_title', 'assign_date', 'assigned_amount',
                    'assigner_title', 'program_title', 'instrument_title'],
    // ---- pārējās UR kopas: lapa tās rāda vispārīgi, vajag meklēšanas kolonnu ------------
    'register_name_history' => ['regcode', 'date_to', 'name'],
    'reorganizations' => ['source_entity_regcode', 'final_entity_regcode', 'reorganization_type'],
    'equity_capitals' => ['legal_entity_registration_number', 'equity_capital_type', 'amount', 'currency', 'date_from'],
    'areas_of_activity_of_associations_foundations' => ['regcode', 'area_of_activity'],
    'akf_data' => ['legal_entity_registration_number'],
    'arbitration_list' => ['legal_entity_registration_number'],
    'arbitration_members' => ['at_legal_entity_registration_number'],
    'area_of_activity' => ['legal_entity_registration_number'],
    'farm_land' => ['legal_entity_registration_number'],
    'memorandum_date' => ['legal_entity_registration_number'],
    'political_parties' => ['legal_entity_registration_number'],
    'property_investment_appraisers_list' => ['legal_entity_registration_number'],
    'religious_affiliations' => ['legal_entity_registration_number'],
    'special_statuses' => ['legal_entity_registration_number'],
    'ppi_public_persons_institutions' => ['registrationNumber'],
    'ppi_delegated_entities' => ['registrationNumber', 'delegatedEntityRegistrationNumber'],
];

/**
 * Personu tabulu ATĻAUTĀS kolonnas (2026-10-14 UR datu kopu galvenes, identiskas augustam).
 * Citu kolonnu izmetam (sk. galvas 2. likumu). Ja UR pievieno kolonnu, ko vajag rādīt,
 * pievieno to šeit — un, ja tā ir personas dati, arī page_builder MASKING_CONFIG.
 */
const AVOTU_PERSONU_KOLONNAS = [
    'members' => ['id', 'uri', 'at_legal_entity_registration_number', 'entity_type', 'name',
        'latvian_identity_number_masked', 'birth_date', 'legal_entity_registration_number', 'number_of_shares',
        'share_nominal_value', 'share_currency', 'date_from', 'registered_on', 'last_modified_at'],
    'officers' => ['id', 'uri', 'at_legal_entity_registration_number', 'entity_type', 'position', 'governing_body',
        'name', 'latvian_identity_number_masked', 'birth_date', 'legal_entity_registration_number',
        'rights_of_representation_type', 'representation_with_at_least', 'registered_on', 'last_modified_at'],
    'beneficial_owners' => ['id', 'legal_entity_registration_number', 'forename', 'surname',
        'latvian_identity_number_masked', 'birth_date', 'nationality', 'residence', 'registered_on', 'last_modified_at'],
    'stockholders' => ['id', 'at_legal_entity_registration_number', 'entity_type', 'name',
        'latvian_identity_number_masked', 'birth_date', 'legal_entity_registration_number', 'number_of_shares',
        'share_nominal_value', 'share_currency', 'votes', 'stock_type', 'depository_registration_number',
        'depository_name', 'date_from', 'registered_on', 'last_modified_at'],
    'members_joint_owners' => ['id', 'member_id', 'entity_type', 'name', 'latvian_identity_number_masked',
        'birth_date', 'legal_entity_registration_number'],
    'stockholders_joint_owners' => ['id', 'member_id', 'entity_type', 'name', 'latvian_identity_number_masked',
        'birth_date', 'legal_entity_registration_number'],
    // brīvteksts ar personvārdiem (gdpr_scrub skrubji pēc nosaukuma)
    'liquidations' => ['id', 'legal_entity_registration_number', 'liquidation_type', 'liquidation_type_text',
        'date_from', 'grounds_for_liquidation', 'registered_on', 'last_modified_at'],
    'securing_measures' => ['id', 'legal_entity_registration_number', 'securing_measure_type',
        'securing_measure_type_text', 'type_details', 'date_from', 'case_number', 'institution_identifier',
        'institution_registration_number', 'institution_name'],
];

/**
 * Tabulas, kurās personas datus maskē vai atsijā PĒC kolonnas nosaukuma. Ja tām trūkst
 * atslēgu kolonnu un iepriekšējās versijas nav, tabulu NOMETAM, nevis atstājam jauno.
 */
const AVOTU_PERSONU_TABULAS = [
    'members', 'officers', 'beneficial_owners', 'stockholders', 'members_joint_owners',
    'stockholders_joint_owners', 'liquidations', 'securing_measures', 'deminimis',
];

/**
 * Dzīvās ur_data.db ceļš, no kuras pārnest iepriekšējo versiju. Uzstāda convert_all_csvs();
 * testi un atsevišķi izsaukumi var to atstāt null — tad pārnešanas nav, tikai brīdinājums.
 */
function avotu_dzivas_db(?string $iestatit = null, bool $notirit = false): ?string {
    static $cels = null;
    if ($notirit) $cels = null;
    if ($iestatit !== null) $cels = $iestatit;
    return $cels;
}

/** CSV, kuru konversija šajā būvē neizdevās (convert_all_csvs to pieraksta). */
function avotu_neizdevas(?string $tabula = null, bool $notirit = false): array {
    static $saraksts = [];
    if ($notirit) $saraksts = [];
    if ($tabula !== null) $saraksts[$tabula] = true;
    return array_keys($saraksts);
}

/** Šīs būves avotu līguma un lejupielādes brīdinājumi (build_all.php izvada beigās). */
function avotu_bridinajumi(?string $teksts = null, bool $notirit = false): array {
    static $saraksts = [];
    if ($notirit) $saraksts = [];
    if ($teksts !== null) $saraksts[] = $teksts;
    return $saraksts;
}

/** Vai tabula ir galvenajā (staging) DB. */
function avotu_tabula_ir(PDO $pdo, string $tabula): bool {
    return (bool)$pdo->query("SELECT 1 FROM main.sqlite_master WHERE type='table' AND name=" . $pdo->quote($tabula))->fetchColumn();
}

/** Tabulas kolonnu nosaukumi (staging DB). */
function avotu_kolonnas(PDO $pdo, string $tabula): array {
    return array_map('strval', array_column(
        $pdo->query('PRAGMA main.table_info("' . str_replace('"', '""', $tabula) . '")')->fetchAll(PDO::FETCH_ASSOC), 'name'));
}

/**
 * Kuru no $kolonnas tabulā NAV (precīza nosaukumu sakritība — kā SQL ar pēdiņām to meklē).
 * null, ja tabulas nav vispār.
 */
function avotu_trukst(PDO $pdo, string $tabula, array $kolonnas): ?array {
    if (!avotu_tabula_ir($pdo, $tabula)) return null;
    $ir = array_flip(avotu_kolonnas($pdo, $tabula));
    return array_values(array_filter($kolonnas, static fn($k) => !isset($ir[$k])));
}

/**
 * Pārnes tabulu (ar tās indeksiem) no dzīvās DB uz staging, aizstājot staging versiju.
 * Dzīvo DB tikai LASA (ATTACH + SELECT). Atgriež pārnesto rindu skaitu vai null, ja
 * dzīvās DB vai tajā šīs tabulas nav, vai to nevar nolasīt (bojāta/aizslēgta) — tad
 * staging tabulu NEAIZTIEK. Kļūda te būvi neaptur: bojāta dzīvā DB citādi bloķētu arī
 * būvi, kas to aizstātu ar veselu (recenzija 2026-10-14).
 */
function avotu_parnest(PDO $pdo, string $tabula, ?callable $log = null): ?int {
    $dziva = avotu_dzivas_db();
    if ($dziva === null || !is_file($dziva) || filesize($dziva) < 1024) return null;
    $staging = (string)($pdo->query('PRAGMA database_list')->fetch()['file'] ?? '');
    if (realpath($dziva) !== false && realpath($dziva) === realpath($staging)) return null;
    $q = '"' . str_replace('"', '""', $tabula) . '"';
    try {
        $pdo->exec('ATTACH DATABASE ' . $pdo->quote($dziva) . ' AS avotu_dziva');
    } catch (Throwable $e) {
        if ($log) $log("      ! $tabula: dzīvo DB nevar pievienot pārnešanai (" . $e->getMessage() . ')');
        return null;
    }
    // Kopija vispirms PAGAIDU tabulā; staging tabulu aizstājam tikai pēc pilnas veiksmes.
    // NE transakcija + ROLLBACK: konversijas savienojumam ir journal_mode=OFF, ar ko
    // ROLLBACK SQLite dokumentācijā ir "nedefinēts" — daļēja tabula varētu palikt.
    $tmp = '"avotu_parnests_tmp"';
    try {
        $ir = $pdo->query("SELECT 1 FROM avotu_dziva.sqlite_master WHERE type='table' AND name=" . $pdo->quote($tabula))->fetchColumn();
        if (!$ir) return null;
        $indeksi = $pdo->query("SELECT sql FROM avotu_dziva.sqlite_master WHERE type='index' AND sql IS NOT NULL AND tbl_name="
            . $pdo->quote($tabula))->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec("DROP TABLE IF EXISTS main.$tmp");
        $pdo->exec("CREATE TABLE main.$tmp AS SELECT * FROM avotu_dziva.$q");
        $pdo->exec("DROP TABLE IF EXISTS main.$q");
        $pdo->exec("ALTER TABLE main.$tmp RENAME TO $q");
        foreach ($indeksi as $isql) {
            try { $pdo->exec((string)$isql); } catch (Throwable $e) { /* indekss nav būtisks datu pareizībai */ }
        }
        $n = (int)$pdo->query("SELECT COUNT(*) FROM main.$q")->fetchColumn();
        if ($log) $log("      ↺ $tabula: paturēta iepriekšējā versija no dzīvās DB (" . number_format($n, 0, ',', ' ') . ' rindas)');
        return $n;
    } catch (Throwable $e) {
        try { $pdo->exec("DROP TABLE IF EXISTS main.$tmp"); } catch (Throwable $e2) {}
        if ($log) $log("      ! $tabula: iepriekšējo versiju nevar nolasīt (" . $e->getMessage() . ')');
        return null;
    } finally {
        try { $pdo->exec('DETACH DATABASE avotu_dziva'); } catch (Throwable $e) { /* nav pievienota */ }
    }
}

/**
 * Personu tabulas atļauto kolonnu sargs: nezināmas kolonnas izmet (ALTER TABLE DROP COLUMN,
 * SQLite ≥ 3.35). Ja izmest neizdodas — tabulu aizstāj ar iepriekšējo vai nomet (fail-closed).
 */
function avotu_personu_baltais(PDO $pdo, string $tabula, ?callable $log = null): void {
    $atlauts = array_flip(AVOTU_PERSONU_KOLONNAS[$tabula] ?? []);
    if (!$atlauts) return;
    $ir = avotu_kolonnas($pdo, $tabula);
    $svesas = array_values(array_filter($ir, static fn($c) => !isset($atlauts[$c])));
    $izmestas = array_values(array_diff(array_keys($atlauts), $ir));
    if ($izmestas && $log) $log("      i $tabula: avotā vairs nav kolonnu " . implode(', ', $izmestas) . ' — pieņemts');
    if (!$svesas) return;
    try {
        foreach ($svesas as $c) $pdo->exec('ALTER TABLE main."' . str_replace('"', '""', $tabula) . '" DROP COLUMN "' . str_replace('"', '""', $c) . '"');
        $t = "AVOTA STRUKTŪRA MAINĪJUSIES: $tabula — jauna kolonna " . implode(', ', $svesas)
            . ' izmesta (personu tabula; ja to vajag rādīt, pievieno avotu_kolonnas.php un maskēšanu)';
        avotu_bridinajumi($t);
        if ($log) $log("      ! $t");
    } catch (Throwable $e) {
        $t = "AVOTA STRUKTŪRA MAINĪJUSIES: $tabula — jaunu kolonnu " . implode(', ', $svesas) . ' nevar izmest (' . $e->getMessage() . ')';
        avotu_bridinajumi($t);
        if ($log) $log("      ! $t");
        if (avotu_parnest($pdo, $tabula, $log) === null) {
            $pdo->exec('DROP TABLE IF EXISTS main."' . str_replace('"', '""', $tabula) . '"');
            if ($log) $log("      ! $tabula: iepriekšējās versijas nav — tabula NOMESTA (personas dati)");
        }
    }
}

/**
 * Vispārīgā pārbaude visām AVOTU_KOLONNAS tabulām (sauc convert_all_csvs PIRMS GDPR
 * filtriem un atvasinātajiem būvētājiem). Tabula ar trūkstošām atslēgu/loģikas kolonnām
 * vai tabula, kas staging DB nemaz neielasījās, tiek aizstāta ar iepriekšējo versiju no
 * dzīvās DB. Personu tabulām pēc tam — atļauto kolonnu sargs.
 * Atgriež [tabula => iemesls] tām, kurām bija jāpārnes (žurnālam un testiem).
 */
function avotu_parbaude(PDO $pdo, ?callable $log = null): array {
    $rez = [];
    foreach (AVOTU_KOLONNAS as $tabula => $kolonnas) {
        $trukst = avotu_trukst($pdo, $tabula, $kolonnas);
        if ($trukst === []) {
            if (isset(AVOTU_PERSONU_KOLONNAS[$tabula])) avotu_personu_baltais($pdo, $tabula, $log);
            continue;
        }
        $iemesls = $trukst === null ? 'tabula neielasījās' : 'trūkst kolonnu: ' . implode(', ', $trukst);
        avotu_bridinajumi("AVOTA STRUKTŪRA MAINĪJUSIES: $tabula — $iemesls");
        if ($log) $log("      ! AVOTA STRUKTŪRA MAINĪJUSIES: $tabula — $iemesls");
        $n = avotu_parnest($pdo, $tabula, $log);
        if ($n === null && $trukst !== null && in_array($tabula, AVOTU_PERSONU_TABULAS, true)) {
            // Fail-closed: šajās tabulās personas datus maskē/atsijā PĒC kolonnas nosaukuma.
            $pdo->exec('DROP TABLE main."' . str_replace('"', '""', $tabula) . '"');
            if ($log) $log("      ! $tabula: iepriekšējās versijas nav — tabula NOMESTA (personas dati; nepārbaudītu struktūru nepublicējam)");
        } elseif ($n === null && $log) {
            $log("      ! $tabula: iepriekšējās versijas nav — " . ($trukst === null
                ? 'tabula šonakt paliek tukša'
                : 'paliek jaunā (nepilnā) versija; atkarīgās sadaļas var rādīt nepilnus datus'));
        } elseif ($n !== null && isset(AVOTU_PERSONU_KOLONNAS[$tabula])) {
            avotu_personu_baltais($pdo, $tabula, $log);
        }
        $rez[$tabula] = $iemesls;
    }
    return $rez;
}

/**
 * Atvasinātās tabulas avotu pārbaude (sauc katrs būvētājs convert.php sākumā, PIRMS savām
 * agrīnajām izejām). $avoti = [jēltabula => [vajadzīgās kolonnas]]; $prefiksi — avotu
 * tabulu vārdu prefiksi, ja to kopa nav fiksēta (EIS gadi, APUS gadi).
 * Problēma ir: (a) esošai jēltabulai trūkst kolonnu; (b) avota CSV konversija šajā būvē
 * NEIZDEVĀS (tabulas nav, bet tai bija jābūt — citādi būvētājs to klusi izlaistu un
 * atvasinātā tabula paliktu bez šī avota, piem. PTAC bez lēmumiem = viltus "risku nav").
 * Ja problēma ir:
 *   1) žurnālā skaidra rinda;
 *   2) VISAS šīs atvasinātās tabulas jēltabulas nometam (tajās ir personu dati, ko
 *      atsijā tikai būvētājs — fail-closed);
 *   3) atvasināto tabulu pārnesam no dzīvās DB (tā jau ir filtrēta) un no tās izmetam
 *      subjektus, kuru šīsnakts register vairs nav (būvētāju fail-closed pārbaudēm).
 * true = var būvēt; false = būvētājam jāiziet (darīts viss, kas vajadzīgs).
 */
function avotu_atvasinatas_derigas(PDO $pdo, string $atvasinata, array $avoti, ?callable $log = null, array $prefiksi = []): bool {
    $problemas = [];
    foreach ($avoti as $tabula => $kolonnas) {
        $trukst = avotu_trukst($pdo, $tabula, $kolonnas);
        if ($trukst) $problemas[] = "$tabula (trūkst: " . implode(', ', $trukst) . ')';
    }
    foreach (avotu_neizdevas() as $t) {
        $mans = isset($avoti[$t]);
        foreach ($prefiksi as $p) if (str_starts_with($t, $p)) $mans = true;
        if ($mans) $problemas[] = "$t (CSV neizdevās konvertēt)";
    }
    if (!$problemas) return true;
    $teksts = "AVOTA STRUKTŪRA MAINĪJUSIES: $atvasinata — " . implode('; ', $problemas);
    avotu_bridinajumi($teksts);
    if ($log) $log("      ! $teksts");
    $nometamas = array_keys($avoti);
    foreach ($prefiksi as $p) {
        foreach ($pdo->query("SELECT name FROM main.sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (str_starts_with((string)$t, $p)) $nometamas[] = (string)$t;
        }
    }
    foreach (array_unique($nometamas) as $tabula) {
        if (avotu_tabula_ir($pdo, $tabula)) $pdo->exec('DROP TABLE main."' . str_replace('"', '""', $tabula) . '"');
    }
    $qa = '"' . str_replace('"', '""', $atvasinata) . '"';
    if (avotu_parnest($pdo, $atvasinata, $log) === null) {
        $pdo->exec("DROP TABLE IF EXISTS main.$qa");
        if ($log) $log("      ! $atvasinata: iepriekšējās versijas nav — sadaļa šonakt būs tukša");
    } elseif (avotu_tabula_ir($pdo, 'register') && avotu_trukst($pdo, $atvasinata, ['regcode']) === []) {
        // Būvētāju fail-closed pārbaudes prasa, lai katrs subjekts būtu ŠĪSNAKTS register.
        // Pārnestajā versijā var palikt kāds, kura vairs nav — bez šī visa būve kristu.
        $iz = (int)$pdo->exec("DELETE FROM main.$qa WHERE regcode IS NULL OR regcode NOT IN (SELECT regcode FROM main.register)");
        if ($iz > 0 && $log) $log("      ↺ $atvasinata: izmestas $iz pārnestās rindas, kuru subjekts vairs nav register");
    }
    return false;
}
