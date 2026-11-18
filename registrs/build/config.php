<?php
// server/build/config.php — datu būvēšanas konfigurācija (ceļi, URL, kolonnu likumi).
declare(strict_types=1);

require_once __DIR__ . '/../lib/paths.php';
require_once __DIR__ . '/../lib/timezone.php'; // žurnālu laiki Rīgas laikā, ne UTC

/**
 * Bāzes ceļš datu mapei (csv/, build_state/). Skat. paths.php (REG_DATA_DIR > docroot).
 */
function build_root(): string {
    return reg_data_root();
}

/** Izņēmums, ar ko būve tiek apturēta pēc STOP pogas (netiek "norīts" kā parasta kļūda). */
class BuildStopped extends RuntimeException {}

/** STOP karoga fails (raksta data_admin STOP poga; pārbauda būves cikls). */
function build_stop_flag(): string {
    return build_root() . '/build_state/build.stop';
}

/** Izmet BuildStopped, ja pieprasīta apturēšana. Sauc posmu robežās un garajos ciklos. */
function build_abort_if_stopped(): void {
    if (is_file(build_stop_flag())) {
        throw new BuildStopped('Apturēts ar STOP pogu.');
    }
}

/**
 * Fatāla PHP kļūda (atmiņas limits u.c.) apiet build_all.php try/catch/finally: bez šī sarga būve
 * «pazūd» bez iemesla — rc=255, žurnālā nekā, un dispečers to nosauc par ārēju nokaušanu
 * (2026-11-04…10 tā 7 naktis krita ceturkšņu apvienošana ar «Allowed memory size exhausted»;
 * serverī CLI display_errors un log_errors ir izslēgti). Beigu funkcija fatālu kļūdu ieraksta
 * būves žurnālā un stāvoklī (status=error, failed_stage = posms, kurā tā notika). Rezerve tiek
 * atbrīvota, lai pēc atmiņas izsīkuma pietiktu ierakstam. Posmu ņem tikai no ŠĪ procesa stāvokļa
 * (pid), citādi pirms pirmā $set_state tiktu nosaukts iepriekšējās būves posms. Sekas — kā
 * admin_buve_nomirusi(). Testi: test_tools/registrs/ceturksnu_apvienosana/fatalas_kludas_sarga_testi.php
 */
function build_fatalas_kludas_sargs(callable $log, callable $set_state, string $state_file): void {
    $GLOBALS['__build_atminas_rezerve'] = str_repeat("\0", 4 << 20);
    register_shutdown_function(static function () use ($log, $set_state, $state_file): void {
        $GLOBALS['__build_atminas_rezerve'] = null;
        $e = error_get_last();
        if ($e === null || !in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }
        $iepr = json_decode((string)@file_get_contents($state_file), true);
        $posms = (is_array($iepr) && (int)($iepr['pid'] ?? 0) === getmypid()) ? (string)($iepr['stage'] ?? '') : '';
        $sekas = match ($posms) {
            'swap'    => 'nomaiņa varēja palikt daļēja — pārbaudīt dzīvās DB un palaist būvi vēlreiz',
            'sitemap' => 'dzīvās DB jau nomainītas; sitemap/IndexNow nepabeigti',
            default   => 'dzīvās DB nav mainītas, paliek iepriekšējās būves dati',
        };
        // Nederīgs UTF-8 (piem., trasē nogriezts arguments) → json_encode false → tukšs stāvokļa fails
        $msg = mb_scrub('PHP fatāla kļūda' . ($posms !== '' ? " posmā «{$posms}»" : '') . ': ' . $e['message']
             . ' (' . basename((string)$e['file']) . ':' . $e['line'] . ')', 'UTF-8');
        try { $set_state('error', 'error', ['error' => $msg, 'failed_stage' => $posms]); } catch (Throwable $t) {}
        try {
            $log('!!! ' . $msg);
            $log("=== PĀRTRAUKTS — $sekas ===");
        } catch (Throwable $t) {}
    });
}

const CSV_URLS = [
    // BIS — Būvniecības informācijas sistēma (CC0). Būvkomersantu gada darbības
    // rādītāji (apgrozījums būvniecībā + nodarbinātie) un statusu izmaiņu vēsture.
    // Lielākais segums no visām papildu kopām: 16 737 un 20 261 uzņēmums.
    // Faila vārdā avotā ir datums — CKAN to ignorē, sk. PTAC komentāru zemāk.
    "https://data.gov.lv/dati/dataset/e50cbd65-d986-4c8b-b1fa-0ca8b0f44895/resource/0154b9e0-4fdc-4ec2-9b9f-c3595b9d3dfd/download/bis_darbiba.csv",
    "https://data.gov.lv/dati/dataset/e6a85fbe-9223-4cb3-a86e-91aaba0f0a26/resource/98717d85-d414-4d75-a191-86ae553904ce/download/bis_statusi.csv",
    // ZVA — Farmaceitiskās darbības uzņēmumu reģistrs (aptiekas, lieltirgotavas,
    // ražotāji). UZMANĪBU: avotā ir arī aptiekas vadītāja un atbildīgās personas
    // VĀRDI, e-pasti un tālruņi — tos neglabājam (sk. build_papildu_tabulas).
    "https://dati.zva.gov.lv/fdu-registrs/export/fdu_register.csv#fails=zva_fdu.csv",
    // VVD — Valsts vides dienesta atļaujas un licences (CC0, tieši no VVD reģistriem).
    // Piecas kopas: A/B kategorijas piesārņojošās darbības, C kategorijas
    // reģistrācijas, atkritumu apsaimniekošana, ūdens resursi, zemes dzīles.
    "https://registri.vvd.gov.lv/opendata/permits/ab.csv#fails=vvd_ab.csv",
    "https://registri.vvd.gov.lv/opendata/permits/c.csv#fails=vvd_c.csv",
    "https://registri.vvd.gov.lv/opendata/permits/grp.csv#fails=vvd_atkritumi.csv",
    "https://registri.vvd.gov.lv/opendata/permits/aqu.csv#fails=vvd_udens.csv",
    "https://registri.vvd.gov.lv/opendata/licences/sub.csv#fails=vvd_zemes_dzilas.csv",
    // Sabiedriskā labuma organizācijas (VID), minimālās algas maksātāji (VID) un
    // PVN grupu dalībnieki (VID) — trīs mazas kopas ar statusa faktiem.
    // UZMANĪBU minimālās algas kopai: tur darba devējs var būt FIZISKA persona
    // (kolonna "reg_kods_vai_personas_koda_otra_dala" + dzimšanas gads + uzvārds).
    "https://data.gov.lv/dati/dataset/694111ef-a1ae-4838-b0b0-75e70d4faf81/resource/476c04af-877c-4136-adc1-a42841650e45/download/slo_registrs.csv",
    "https://data.gov.lv/dati/dataset/7f192faa-ba7d-4188-a9c0-e1d6caefe498/resource/4c228367-fbc2-420a-8941-ba0210145188/download/vid_minalgas.csv",
    "https://data.gov.lv/dati/dataset/f6c5743b-a5cf-41b8-8b2b-53ebd698b30d/resource/14a89cf8-9cb0-415f-bbba-6b5c77a82814/download/vid_pvn_grupas.csv",
    // PTAC — Patērētāju tiesību aizsardzības centrs (CC0, atjaunojas katru dienu).
    // Septiņas kopas: trīs licenču/reģistru un četras uzraudzības.
    // SVARĪGI PAR URL: avota faila vārdā ir DATUMS (ptacdecisions_19-08-2026.csv),
    // kas mainās katru dienu, un tabulas vārds mainītos līdzi. CKAN faila vārda
    // segmentu ignorē — failu atdod pēc resursa ID (pārbaudīts: pieprasījums ar
    // svešu vārdu atdod to pašu 19 688 baitu failu), tāpēc URL galā rakstām SAVU
    // stabilo vārdu. Mainīgs paliek tikai resursa ID, ja PTAC augšupielādētu
    // resursu no jauna — tad jāatjauno šie URL.
    "https://data.gov.lv/dati/dataset/169359e9-9cad-4adb-96c9-ab505f40a5f4/resource/9853bae0-2157-40f7-8d48-a8a5690d0c8a/download/ptac_kreditetaji.csv",
    "https://data.gov.lv/dati/dataset/adaad1d5-e7f6-46a7-8768-6f83fc22c5e0/resource/d2d285ba-b54b-4929-b973-5846efbb33e5/download/ptac_paradu_atguveji.csv",
    "https://data.gov.lv/dati/dataset/8594c36d-aed1-4d34-a7a3-95da72b68a09/resource/1744cc71-bd9c-4c64-ae45-4b6525879af5/download/ptac_hipotek_starpnieki.csv",
    "https://data.gov.lv/dati/dataset/ce734457-1ad0-4ee4-9398-526e81fef49b/resource/9e846006-a249-44d2-81a1-79bb93c330df/download/ptac_melnais_saraksts.csv",
    "https://data.gov.lv/dati/dataset/a527c089-463d-4ee8-991b-43741e18af77/resource/2e32ffed-5357-4f00-9abe-a13b3587b772/download/ptac_lemumi.csv",
    "https://data.gov.lv/dati/dataset/cbaf0063-57f6-4fd3-bc8e-2d2645064a2a/resource/98559067-034c-4667-8653-c73667d6a34a/download/ptac_apnemsanas.csv",
    "https://data.gov.lv/dati/dataset/6b6f0816-0941-45b7-bfcf-f0e70aaeeeec/resource/f3c4998f-6e91-4ef5-a188-ac96799d3b7c/download/ptac_stridu_risinataji.csv",
    // CFLA — ES fondu līdzfinansētie projekti (CC0). TRĪS plānošanas periodi, katrā
    // trīs mums vajadzīgie faili: projektu saraksts (finansējuma saņēmējs),
    // sadarbības partneri un iepirkumu līgumi (izpildītāji), plus veiktie maksājumi.
    // VISIEM avota failiem ir VIENĀDS vārds (es_fondu_projektu_saraksts.csv u.tml.)
    // visos trijos datu kopās, tāpēc katram jādod savs vārds ar #fails= — citādi
    // tie pārrakstītu cits citu, un tabulā paliktu tikai pēdējais periods.
    // Jēltabulas pēc apstrādes tiek nomestas — sk. build_es_fondi_table().
    "https://data.gov.lv/dati/dataset/4ce3df9e-b3c4-4373-af8c-afb1dc6a9a61/resource/9c2d6bf2-41a4-4a58-a5ba-3a4ad6d7e79f/download/es_fondu_projektu_saraksts.csv#fails=es_fondi_saraksts_1420.csv",
    "https://data.gov.lv/dati/dataset/4ce3df9e-b3c4-4373-af8c-afb1dc6a9a61/resource/12f8a44f-e1a9-4f7e-8a32-7f472ac28de8/download/es_fondu_projektu_sadarbibas_partneri.csv#fails=es_fondi_partneri_1420.csv",
    "https://data.gov.lv/dati/dataset/4ce3df9e-b3c4-4373-af8c-afb1dc6a9a61/resource/3eb18493-11bf-495c-b609-40fea4a27244/download/es_fondu_projektu_iepirkumu_ligumi.csv#fails=es_fondi_ligumi_1420.csv",
    "https://data.gov.lv/dati/dataset/4ce3df9e-b3c4-4373-af8c-afb1dc6a9a61/resource/8ce38e54-41ee-4532-972d-ddb5df190323/download/es_fondu_projektu_veiktie_maksajumi.csv#fails=es_fondi_maksajumi_1420.csv",
    "https://data.gov.lv/dati/dataset/bfa55ed7-f58c-48c4-bf8e-cb7ff4f12b05/resource/80aca232-54ad-4a9e-b1e3-c06623c1020b/download/es_fondu_projektu_saraksts.csv#fails=es_fondi_saraksts_2127.csv",
    "https://data.gov.lv/dati/dataset/bfa55ed7-f58c-48c4-bf8e-cb7ff4f12b05/resource/5d9b36b2-6ff4-44b7-8bb3-d99d8043a271/download/es_fondu_projektu_sadarbibas_partneri.csv#fails=es_fondi_partneri_2127.csv",
    "https://data.gov.lv/dati/dataset/bfa55ed7-f58c-48c4-bf8e-cb7ff4f12b05/resource/ce976d97-e701-42a0-8cb5-b2569c640186/download/es_fondu_projektu_iepirkumu_ligumi.csv#fails=es_fondi_ligumi_2127.csv",
    "https://data.gov.lv/dati/dataset/bfa55ed7-f58c-48c4-bf8e-cb7ff4f12b05/resource/b4a9c3e9-4202-417f-b825-a2824e9f471b/download/es_fondu_projektu_veiktie_maksajumi.csv#fails=es_fondi_maksajumi_2127.csv",
    "https://data.gov.lv/dati/dataset/588f2204-ad9a-4576-bdf0-23cf5ca0bed0/resource/bfcae357-328c-423f-9835-215e7fd3db4e/download/es_fondu_projektu_saraksts.csv#fails=es_fondi_saraksts_af.csv",
    "https://data.gov.lv/dati/dataset/588f2204-ad9a-4576-bdf0-23cf5ca0bed0/resource/ad29f6bb-4183-4dd3-9b81-eb62c8959c92/download/es_fondu_projektu_sadarbibas_partneri.csv#fails=es_fondi_partneri_af.csv",
    "https://data.gov.lv/dati/dataset/588f2204-ad9a-4576-bdf0-23cf5ca0bed0/resource/9fd9a783-325f-46e0-b699-26eea3f85fe4/download/es_fondu_projektu_iepirkumu_ligumi.csv#fails=es_fondi_ligumi_af.csv",
    "https://data.gov.lv/dati/dataset/588f2204-ad9a-4576-bdf0-23cf5ca0bed0/resource/69b685a4-9b44-4664-b1ba-0ffed7addfbd/download/es_fondu_projektu_veiktie_maksajumi.csv#fails=es_fondi_maksajumi_af.csv",
    // EIS iepirkumu rezultāti (e-konkursi, CC0) — publiskie līgumi, ko uzņēmums
    // IEGUVIS. Deviņi gada faili; ķēdes sniedzas pār failiem (grozījumi 2019. gada
    // līgumam publicēti 2026. gadā), tāpēc jāņem VISI, nevis pēdējie divi.
    // Jēltabulas pēc apstrādes tiek nomestas — sk. build_iepirkumi_table()
    // convert.php, kas no tām izveido saspiesto `iepirkumi` tabulu.
    // UZMANĪBU: faili ar KOMATU (sk. csv_sep_for) un identifikatori ar vadošām
    // nullēm — sk. ALWAYS_STRING_COLS.
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/cecd0be7-c8e0-451a-8314-f1d806db3bc1/download/eis_e_iepirkumi_rezultati_2018.csv",
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/1d37ba16-4d7b-4c1e-9650-1ee9b6a32666/download/eis_e_iepirkumi_rezultati_2019.csv",
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/abf811a3-26e8-48c2-bc86-e9b74ca0b385/download/eis_e_iepirkumi_rezultati_2020.csv",
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/a1342945-ce4b-480b-abb5-b74d43c41534/download/eis_e_iepirkumi_rezultati_2021.csv",
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/97a7c410-60c0-4d08-b554-4d1abb9092da/download/eis_e_iepirkumi_rezultati_2022.csv",
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/71f88053-97c1-4928-93c3-8d83d714f27f/download/eis_e_iepirkumi_rezultati_2023.csv",
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/3a02a1a7-0322-4c0d-9700-8af9832f0f91/download/eis_e_iepirkumi_rezultati_2024.csv",
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/79b34e1c-8989-4984-816a-8e8f92b701f3/download/eis_e_iepirkumi_rezultati_2025.csv",
    "https://data.gov.lv/dati/dataset/e909312a-61c9-4cde-a72a-0a09dd75ef43/resource/c4007411-1ba5-40f3-9b50-f25881c93f51/download/eis_e_iepirkumi_rezultati_2026.csv",
    // de minimis atbalsts (FM uzskaites sistēma, CC0). Dinamisks galapunkts bez
    // .csv vārda — faila vārdu norādām ar #fails= (sk. download.php).
    // UZMANĪBU: kopā ir arī FIZISKAS personas (4162 personas kodi) — tās
    // izmet fix_deminimis_personas() uzreiz pēc ielādes; DB tās nenonāk.
    // Sistēma publisko tikai kārtējo + 2 iepriekšējos fiskālos gadus.
    "https://deminimis.fm.gov.lv/public/mekletajs/export_csv#fails=deminimis.csv",
    // VID saimnieciskās darbības apturēšana (CC0, atjaunojas katru nakti ~05:00).
    // UR suspensions_prohibitions rāda TIKAI pašreizējo apturējumu un tikai sākuma
    // datumu; šī kopa dod aizlieguma PERIODU, atjaunošanas datumu un VĒSTURI
    // (64 tūkst. uzņēmumu ar jau beigušos apturējumu, 1016 ar atkārtotiem).
    // UZMANĪBU: fails ir ar KOMATU — sk. COMMA_SEP_TABLES zemāk.
    "https://data.gov.lv/dati/dataset/fd2fffaf-3ff4-43a9-8e04-09f258bca463/resource/074fe277-64a8-47ea-a9f6-12aee57c8964/download/pdb_saimndarbibaaptureta_odata.csv",
    // Sankciju riski (UR, CC0): sankciju subjekti UN uzņēmumi, kas varētu būt
    // to faktiskā kontrolē. Maza kopa (~63 rindas), bet satur FIZISKU personu
    // vārdus, dzimšanas datus un personas kodu maskas — tāpēc data_fetcher.php
    // tai piemēro scrub_sanctions() (baltais saraksts) pirms jebkuras izvades.
    // UR kopas — TIEŠI no dati.ur.gov.lv, ne no data.gov.lv spoguļa (2026-10-23). Kopš 2026-10-22
    // data.gov.lv (un vairākas citas valsts vietnes) servera IP nomet TCP līmenī, bet dati.ur.gov.lv
    // atbild. Saturs baitu precīzi tas pats (30 no 31 vienāds izmērs, paraugu md5 vienāds; spogulis
    // atjaunojas ~45 min vēlāk). Vienīgi area_of_activity data.gov.lv kopijā visus laukus liek
    // pēdiņās un 402 nosaukumos "&" raksta kā "&amp;" — oriģināls ir tīrāks. Faila vārdi nemainās.
    "https://dati.ur.gov.lv/sanctions/sanctions.csv",
    "https://dati.ur.gov.lv/stockholders/stockholders.csv",
    "https://dati.ur.gov.lv/stockholders/stockholders_joint_owners.csv",
    "https://dati.ur.gov.lv/property_investment_appraisers_list/property_investment_appraisers_list.csv",
    "https://dati.ur.gov.lv/arbitration_list/arbitration_list.csv",
    "https://dati.ur.gov.lv/arbitration_members/arbitration_members.csv",
    "https://dati.ur.gov.lv/political_parties/political_parties.csv",
    "https://dati.ur.gov.lv/akf_data/akf_data.csv",
    "https://dati.ur.gov.lv/memorandum_date/memorandum_date.csv",
    "https://dati.ur.gov.lv/equity_capitals/equity_capitals.csv",
    "https://dati.ur.gov.lv/liquidations/liquidations.csv",
    "https://dati.ur.gov.lv/suspensions_prohibitions/suspensions_prohibitions.csv",
    "https://dati.ur.gov.lv/securing_measures/securing_measures.csv",
    "https://dati.ur.gov.lv/reorganizations/reorganizations.csv",
    "https://dati.ur.gov.lv/farm_land/farm_land.csv",
    "https://dati.ur.gov.lv/area_of_activity/area_of_activity.csv",
    "https://dati.ur.gov.lv/special_statuses/special_statuses.csv",
    "https://dati.ur.gov.lv/religious_affiliations/religious_affiliations.csv",
    "https://dati.ur.gov.lv/beneficial_owners/beneficial_owners.csv",
    "https://dati.ur.gov.lv/insolvency/insolvency_legal_person_proceeding.csv",
    "https://dati.ur.gov.lv/register/areas_of_activity_of_associations_foundations.csv",
    "https://dati.ur.gov.lv/members/members.csv",
    "https://dati.ur.gov.lv/members/members_joint_owners.csv",
    "https://dati.ur.gov.lv/officers/officers.csv",
    "https://dati.ur.gov.lv/financial_data/financial_statements.csv",
    "https://dati.ur.gov.lv/financial_data/balance_sheets.csv",
    "https://dati.ur.gov.lv/financial_data/income_statements.csv",
    "https://dati.ur.gov.lv/financial_data/cash_flow_statements.csv",
    "https://dati.ur.gov.lv/register/ppi_public_persons_institutions.csv",
    "https://dati.ur.gov.lv/register/ppi_delegated_entities.csv",
    "https://dati.ur.gov.lv/register/register.csv",
    // Vēsturiskie nosaukumi — TIEŠI no UR (dati.ur.gov.lv), ne no data.gov.lv: tur
    // resurss ad772b8b… iesaldēts kopš 2025-04-02 (CKAN last_modified), kamēr UR savu
    // failu atjauno katru dienu ar to pašu galveni (regcode;date_to;name). Ar veco
    // saiti lapām trūka 3 178 nosaukumu maiņu 3 090 subjektiem (audits 2026-10-14).
    // Serveris (Hostinger) šo adresi sasniedz — pārbaudīts ar curl tajā pašā dienā.
    "https://dati.ur.gov.lv/register/register_name_history.csv",
    "https://data.gov.lv/dati/dataset/9a5eae1c-2438-48cf-854b-6a2c170f918f/resource/610910e9-e086-4c5b-a7ea-0a896a697672/download/pdb_pvnmaksataji_odata.csv",
    "https://data.gov.lv/dati/dataset/41481e3e-630f-4b73-b02e-a415d27896db/resource/acd4c6f9-5123-46a5-80f6-1f44b4517f58/download/reitings_uznemumi.csv",
    "https://data.gov.lv/dati/dataset/5ed74664-b49d-4b28-aacb-040931646e9b/resource/a42d6e8c-1768-4939-ba9b-7700d4f1dd3a/download/pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata.csv",
    "https://data.gov.lv/dati/dataset/364f3fa8-916f-48fe-9c81-12f13adb35f3/resource/9117ca92-7858-41cc-8c78-d5241ae5fcec/download/pdb_samaksato_nodoklu_kopsummas_cet.csv",
];

/**
 * Dinamiskie URL, kuros ir GADS. APUS (VVD atkritumu gada pārskati) galapunkts ir
 * /a3report-csv/b/{gads}, un dati ir TIKAI kārtējam gadam — par citu gadu API atdod
 * HTTP 422 ("reportYear must be between {kārtējais} and 2999"), ne tukšu failu.
 * Fiksēts gads URL sarakstā nozīmētu, ka 1. janvārī kopa klusi paliek tukša, tāpēc
 * gadu liekam klāt būves brīdī; iepriekšējo gadu prasām tikai janvārī–februārī.
 */
function build_dinamiskie_urli(): array {
    $g = (int)date('Y');
    // Iepriekšējo gadu prasām tikai janvārī–februārī (kad kārtējā gada pārskats var
    // vēl nebūt): pārējā laikā API par pagājušo gadu atdod HTTP 422, ne tukšu failu,
    // un katra nakts būve velti tērēja 3 mēģinājumus + 45 s (recenzija 2026-08-22).
    $gadi = (int)date('n') <= 2 ? [$g, $g - 1] : [$g];
    $out = [];
    foreach ($gadi as $y) {
        $out[] = "https://apus-api.vvd.gov.lv/API-P/a3report-csv/b/$y#fails=apus_atkritumi_$y.csv";
    }
    return array_merge($out, build_eis_jaunie_gadi());
}

/** EIS iepirkumu rezultātu datu kopa data.gov.lv (CKAN pakotnes ID). */
const EIS_CKAN_PAKOTNE = 'e909312a-61c9-4cde-a72a-0a09dd75ef43';

/**
 * EIS gada faili, kuru CSV_URLS sarakstā vēl nav. EIS katru janvāri pievieno jaunu
 * resursu (EIS_E_IEPIRKUMI_REZULTATI_2027.csv) ar jaunu resursa ID, un fiksētais
 * saraksts to nekad neieraudzītu (audits 2026-10-14). Jautājam CKAN package_show.
 * Kļūda vai taimauts = tukšs saraksts: būve turpina ar CSV_URLS, nekas nesalūst.
 * Pieņemam tikai https://data.gov.lv/ saites ar gadu nosaukumā; faila vārdu
 * nosakām paši ar #fails=, lai tabula sauktos tāpat kā pārējie gadi.
 */
function build_eis_jaunie_gadi(?string $json = null): array {
    $GLOBALS['__eis_ckan_kluda'] = '';
    if ($json === null) {
        $ch = curl_init('https://data.gov.lv/dati/api/3/action/package_show?id=' . EIS_CKAN_PAKOTNE);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'saraksts.lv data updater (PHP)', CURLOPT_ENCODING => '',
        ]);
        $json = curl_exec($ch);
        $kl = curl_error($ch);
        unset($ch);
        if (!is_string($json)) { $GLOBALS['__eis_ckan_kluda'] = $kl !== '' ? $kl : 'nav atbildes'; return []; }
    }
    $d = json_decode($json, true);
    if (!is_array($d) || empty($d['success']) || !is_array($d['result']['resources'] ?? null)) {
        $GLOBALS['__eis_ckan_kluda'] = 'negaidīta atbilde';
        return [];
    }
    $zinami = [];
    foreach (CSV_URLS as $u) {
        if (preg_match('/eis_e_iepirkumi_rezultati_(\d{4})\.csv/i', $u, $m)) $zinami[$m[1]] = true;
    }
    $out = [];
    foreach ($d['result']['resources'] as $r) {
        $nos = (string)($r['name'] ?? '');
        $url = (string)($r['url'] ?? '');
        if (!preg_match('/^EIS_E_IEPIRKUMI_REZULTATI_(\d{4})\.csv$/i', $nos, $m)) continue;
        if (isset($zinami[$m[1]]) || !str_starts_with($url, 'https://data.gov.lv/')) continue;
        $out[] = preg_replace('/#.*$/', '', $url) . '#fails=eis_e_iepirkumi_rezultati_' . $m[1] . '.csv';
        $zinami[$m[1]] = true;
    }
    return $out;
}

// Kolonnas, kas VIENMĒR jāglabā kā teksts (identifikatori — nedrīkst zaudēt nulles).
// atvk: VISI 486 675 kodi sākas ar 0 (LV prefikss klasifikatorā, piem. Rīga 0010000) —
// kā REAL katrs zaudēja vadošo nulli. source/final_entity_regcode: vecie 9 ciparu kodi
// ar vadošo nulli ('010100292') kā REAL kļuva par 10100292. Pārējie trīs — tā pati
// identifikatoru klase, šodien bez nulles-priekšā datiem, bet aizsargājam profilaktiski.
// LĪDZ nākamajai DB pārbūvei vecās REAL vērtības labo data_fetcher normalizētāji.
const ALWAYS_STRING_COLS = [
    'id', 'statement_id', 'file_id', 'regcode',
    'legal_entity_registration_number', 'at_legal_entity_registration_number',
    'member_id', 'super_id', 'Registracijas_kods', 'Numurs',
    'atvk', 'source_entity_regcode', 'final_entity_regcode',
    'debtor_registration_number', 'registrationNumber', 'delegatedEntityRegistrationNumber',
    // Sankciju kopa: abas reģ.nr. kolonnas — bez šī 11 ciparu kods ielasās kā
    // REAL un kļūst par '40003009618.0', kas nesakrīt ne ar vienu register rindu.
    'sanctions_subject_registration_number', 'person_reg_nr',
    // EIS iepirkumu rezultāti: uzvarētāja un pasūtītāja 11 ciparu kodi (bez šī
    // '40003009618.0' nesakristu ne ar vienu register rindu — tā pati kļūda, kas
    // sankcijām) un trīs dokumentu identifikatori, ar kuriem saliek līgumu ķēdes.
    'Uzvaretaja_registracijas_numurs', 'Pasutitaja_registracijas_numurs',
    'Iepirkuma_ID', 'Saistita_liguma_ID', 'Noslegta_liguma_dok_ID',
    // CFLA ES fondi: saņēmēja, partnera un izpildītāja reģ. numuri.
    'IesniedzejaRegNo', 'RegNr', 'IzpilditajaRegNo',
    // PTAC: komersanta kods un (strīdu risinātāju failā) reģistrācijas numurs.
    // Pārējos PTAC failos 'Reģistrācijas numurs' ir licences numurs ('NK-2023-1') —
    // arī teksts, tāpēc kopīgais ieraksts nekaitē.
    'Komersanta reģistrācijas numurs', 'Reģistrācijas numurs',
    // BIS, ZVA, VVD, SLO, VID minimālās algas un PVN grupas — reģ. numuru kolonnas.
    'Registracijas_numurs_mitnes_valsti', 'reg_num', 'Operatora_registracijas_numurs',
    'Atkritumu_apsaimniekotaja_registracijas_nr', 'Operatora reģistrācijas numurs',
    'Zemes_dzilu_izmantotaja_registracijas_nr', 'Registracijas_kods',
    'Darba_deveja_reg_kods_vai_personas_koda_otra_dala', 'Dalibnieka_NMR_kods',
    'PVN_grupas_galvenais_uznemums',
    // APUS atkritumu pārskats. BEZ ŠĪ: kolonnā ir arī tukšas vērtības, afinitātes
    // noteikšana to padara par REAL, 11 ciparu kods kļūst par 4.0003518013e10 un
    // JOIN pret register atdod 0 rindu — visa `atkritumi` tabula klusi paliek tukša
    // (audits 2026-08-19).
    'Atskaites organizācijas reģ. numurs', 'Atskaites objekta VZD kods',
    // EWC atkritumu klases kodi sākas ar nulli (piem. 020304) — kā REAL tie zaudē
    // vadošo nulli un kļūst par nederīgiem kodiem (94 ieraksti; audits 2026-08-19).
    'Atkritumu klases kods [B2]',
];

// Kolonnas, kurām veido indeksu.
// Otrā rinda: tabulas, kur meklēšanas kolonna saucas savādāk (SEARCH_COLUMNS_MAP_REG_NR
// registrs/lib/config.php). Bez tām katra uzņēmuma lapa skenēja reorganizations (10 825
// rindas, DIVreiz — meklē pa abām pusēm) un insolvency_legal_person_proceeding (17 955).
// Kopā ~1,5 ms uz lapu siltā kešā, kas bija ap 60 % no visa datu ielādes laika.
const INDEX_COLUMNS = [
    'person_reg_nr',
    // at_legal_entity_registration_number bija tikai otrajā rindā zemāk kā
    // "meklēšanas kolonna", bet members/stockholders to vaicā arī saistību sadaļa
    // TIEŠI (partial pats), un bez indeksa katra uzņēmuma lapa skenēja 206 tūkst.
    // rindu (audits 2026-08-19).
    'at_legal_entity_registration_number',
    'regcode', 'legal_entity_registration_number', 'Numurs', 'Registracijas_kods',
    'member_id', 'statement_id', 'super_id', 'id',
    'source_entity_regcode', 'final_entity_regcode', 'debtor_registration_number',
    'at_legal_entity_registration_number', 'registrationNumber', 'delegatedEntityRegistrationNumber',
    'sanctions_subject_registration_number',
];

// Tabulas ar komatu kā atdalītāju (pārējām ';').
// stockholders_joint_owners te bija KĻŪDAINI: fails diskā ir semikolu atdalīts kā visi
// UR faili, tāpēc tabula uzbūvējās kā VIENA kolonna ar visu galveni nosaukumā (40 rindu
// ķīselis). Lapās tas nenonāca (tabula ne tiek meklēta, ne rādīta), bet dati bija broken.
const COMMA_SEP_TABLES = [
    'deminimis',
    'pdb_saimndarbibaaptureta_odata',
    'pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata',
    'pdb_pvnmaksataji_odata', 'reitings_uznemumi',
    'pdb_samaksato_nodoklu_kopsummas_cet',
];

function csv_sep_for(string $table): string {
    // EIS gada failus neuzskaitām pa vienam: avots katru gadu pievieno jaunu
    // (eis_e_iepirkumi_rezultati_2027 utt.), un aizmirsts ieraksts te nozīmētu
    // klusi salauztu tabulu — visa rinda vienā kolonnā.
    if (str_starts_with($table, 'eis_e_iepirkumi_rezultati_')) return ',';
    // CFLA ES fondu faili (12 gab., trīs periodi) — arī ar komatu.
    if (str_starts_with($table, 'es_fondi_')) return ',';
    // PTAC kopas — arī ar komatu.
    if (str_starts_with($table, 'ptac_')) return ',';
    // BIS, ZVA, VVD un VID papildu kopas — arī ar komatu.
    foreach (['bis_', 'zva_', 'vvd_', 'slo_', 'vid_'] as $pre) {
        if (str_starts_with($table, $pre)) return ',';
    }
    return in_array($table, COMMA_SEP_TABLES, true) ? ',' : ';';
}
