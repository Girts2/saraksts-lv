<?php
/**
 * registrs/build/validacija.php — staging DB pārbaudes PIRMS dzīvās DB nomaiņas.
 *
 * KĀPĒC: 2026-08-19 naktī avots atdeva HTML ar HTTP 200, .csv tika pārrakstīts ar
 * lapu, konversija ielasīja nulli rindu, tabula pdb_pvnmaksataji_odata pazuda, un
 * būve nokrita tikai 3.5 posmā ar "no such table". Lejupielādētājs tagad tādu failu
 * atmet (download.php dl_saturs_derigs), bet šis ir OTRAIS slānis: ja kāda tabula
 * staging DB pret dzīvo DB ir pazudusi vai krasi sarukusi, to redz žurnālā, un
 * pamata tabulām swap tiek atcelts, nevis publicēta tukša datubāze.
 */
declare(strict_types=1);

/**
 * Tabulas, bez kurām vietne vai pati būve nestrādā — to sarukums >50 % vai
 * pazušana atceļ swap. Pārējām tabulām tas pats ir tikai brīdinājums: avota gada
 * maiņa (APUS janvārī) vai reti atjaunots reģistrs var likumīgi sarukt, un
 * nakts būvi tāpēc apturēt nedrīkst.
 */
const BUILD_PAMATA_TABULAS = [
    'register', 'financial_statements', 'balance_sheets', 'income_statements',
    'cash_flow_statements', 'members', 'officers', 'beneficial_owners',
    // VID tabulas lieto 3. un 3.5 posms (prepare, Nozare, Struktūra, Pensionārs,
    // report_tracker) — bez tām būve krīt vēlāk (2026-08-19: pdb_pvnmaksataji_odata).
    // UZMANĪBU: pdb_nm_* satur 3 taksācijas gadus; ja VID kādreiz publicēs vienu,
    // −66 % apturēs būvi — tad tas ir jāizvērtē ar roku, ne jāignorē.
    'pdb_pvnmaksataji_odata', 'pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata',
    'pdb_samaksato_nodoklu_kopsummas_cet',
];

/**
 * Salīdzina staging un dzīvās DB tabulu rindu skaitus.
 * @return array{bridinajumi: string[], kritiskie: string[], salidzinatas: int}
 */
function build_tabulu_sarukums(PDO $staging, string $live_path, float $slieksnis = 0.5, int $min_rindas = 1000): array {
    $out = ['bridinajumi' => [], 'kritiskie' => [], 'salidzinatas' => 0];
    if (!is_file($live_path) || filesize($live_path) < 1024) return $out;   // pirmā būve — nav ar ko salīdzināt
    try {
        $live = new PDO('sqlite:' . $live_path);
        $live->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tabulas = $live->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                        ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        // bojāta vai ne-SQLite dzīvā DB: salīdzināt nevar, bet būvi tas apturēt nedrīkst
        $out['bridinajumi'][] = 'dzīvo DB neizdevās nolasīt salīdzināšanai: ' . $e->getMessage();
        return $out;
    }
    $stagingTabulas = [];
    foreach ($staging->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $r) {
        $stagingTabulas[(string)$r['name']] = true;
    }
    // Virtuālās (FTS) tabulas dzīvajā DB — to ēnu tabulas (x_content, x_docsize, x_idx,
    // x_config, x_data) seko rādītājam, ne datu avotam, un netiek salīdzinātas.
    // Izlaižam TIKAI pēc īsta virtuālā prefiksa, ne pēc sufiksa vien — citādi pazustu
    // arī īstā tabula akf_data (recenzija 2026-08-22).
    $virtualas = [];
    try {
        foreach ($live->query("SELECT name FROM sqlite_master WHERE type='table' AND sql LIKE 'CREATE VIRTUAL TABLE%'") as $r) {
            $virtualas[(string)$r['name']] = true;
        }
    } catch (Throwable $e) { /* bez FTS — nekā ko izlaist */ }
    foreach ($tabulas as $t) {
        $t = (string)$t;
        if (preg_match('/^(.+)_(content|docsize|idx|config|data)$/', $t, $m) && isset($virtualas[$m[1]])) continue;
        if (isset($virtualas[$t])) continue;   // pats virtuālais rādītājs — COUNT uz tā nav datu mērs
        $q = '"' . str_replace('"', '""', $t) . '"';
        try {
            $vec = (int)$live->query("SELECT COUNT(*) FROM $q")->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
        if ($vec < $min_rindas) continue;   // mazas/tukšas tabulas nesalīdzinām — troksnis
        $pamata = in_array($t, BUILD_PAMATA_TABULAS, true);
        if (!isset($stagingTabulas[$t])) {
            $zina = "tabula $t PAZUDUSI (dzīvajā " . number_format($vec, 0, ',', ' ') . ' rindas)';
            // Pamata tabulai pazušana atceļ swap; pārējām — tikai brīdinājums: vienas
            // blakus tabulas konversijas kļūda (ko convert_all_csvs norij) nedrīkst
            // bloķēt visu nakts būvi — sadaļa pati paslēpjas bez datiem (recenzija 2026-08-22).
            if ($pamata) $out['kritiskie'][] = $zina . ' [pamata tabula]';
            else $out['bridinajumi'][] = $zina;
            continue;
        }
        $out['salidzinatas']++;
        try {
            $jauns = (int)$staging->query("SELECT COUNT(*) FROM $q")->fetchColumn();
        } catch (Throwable $e) {
            $out['bridinajumi'][] = "tabulu $t staging DB neizdevās saskaitīt: " . $e->getMessage();
            continue;
        }
        if ($jauns < $vec * $slieksnis) {
            $proc = $vec > 0 ? round(100 - 100 * $jauns / $vec) : 100;
            $zina = "tabula $t sarukusi " . number_format($vec, 0, ',', ' ') . ' → '
                  . number_format($jauns, 0, ',', ' ') . " rindas (−$proc %)";
            if ($pamata) $out['kritiskie'][] = $zina . ' [pamata tabula]';
            else $out['bridinajumi'][] = $zina;
        }
    }
    return $out;
}
