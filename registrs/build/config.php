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

const CSV_URLS = [
    "https://data.gov.lv/dati/dataset/8a66567b-3583-4aa0-841b-eb13f025cd78/resource/6adabd83-93f9-4d7f-bebd-fa109bbf794a/download/stockholders.csv",
    "https://data.gov.lv/dati/dataset/8a66567b-3583-4aa0-841b-eb13f025cd78/resource/55370dca-6765-4566-b2cc-42537782f750/download/stockholders_joint_owners.csv",
    "https://data.gov.lv/dati/dataset/ee36b5fb-b5db-409f-b25e-c8ffe5808e47/resource/9e7d7af9-43d1-4171-8c5b-e5a6c17cd985/download/property_investment_appraisers_list.csv",
    "https://data.gov.lv/dati/dataset/d907e394-298d-406c-be15-aacc7972d9f2/resource/922c31c2-7c30-44a2-80d3-2331cb397d77/download/arbitration_list.csv",
    "https://data.gov.lv/dati/dataset/3e49d1e4-9ad4-4437-ad1d-a2b631fd2983/resource/33dcd626-6626-49db-a8c0-0c4a61a4b231/download/arbitration_members.csv",
    "https://data.gov.lv/dati/dataset/77c9fc67-476a-4302-a15d-77336de395ae/resource/2b60b7c7-d9a4-475f-a09c-08bd1779393e/download/political_parties.csv",
    "https://data.gov.lv/dati/dataset/1d17b3ac-5cf2-4126-96e0-5eb308bb620c/resource/2543c5bb-fa69-4274-b8b7-e9cbf1d2d597/download/akf_data.csv",
    "https://data.gov.lv/dati/dataset/f46958b7-97e9-42a1-b9a0-51ae93222571/resource/fb1c3429-38eb-4e83-9a50-a2878d9f424d/download/memorandum_date.csv",
    "https://data.gov.lv/dati/dataset/5565337a-7f82-41f0-8d16-f091078df603/resource/7910fef5-93eb-4d03-acf0-f45465d67414/download/equity_capitals.csv",
    "https://data.gov.lv/dati/dataset/09047585-e67a-46c6-8ee8-575987efdef0/resource/59e7ec49-f1c6-4410-8ee6-e7737ac5eaee/download/liquidations.csv",
    "https://data.gov.lv/dati/dataset/c35c2fce-0014-4664-957f-b71c2f56acae/resource/1a077ff6-4c4e-4cfe-b603-b7e40851e8a9/download/suspensions_prohibitions.csv",
    "https://data.gov.lv/dati/dataset/b4684b24-3dd2-475e-9502-48cc91b00776/resource/a572e7a4-b23d-44b7-93d8-d09fc47729dd/download/securing_measures.csv",
    "https://data.gov.lv/dati/dataset/1b55ac08-5a57-4234-9fb5-c248d3f97157/resource/83cebcee-ba0c-4162-b061-a8a1fae84f63/download/reorganizations.csv",
    "https://data.gov.lv/dati/dataset/ef0d8766-09f9-49c9-a39a-31fdbd8edb93/resource/94a73fcd-82f5-4743-84fa-93e96cd56a39/download/farm_land.csv",
    "https://data.gov.lv/dati/dataset/2fcacd83-8b01-4452-a087-b199c72817be/resource/49bbd751-3fa2-4d78-8c35-ae0e1c5250d6/download/area_of_activity.csv",
    "https://data.gov.lv/dati/dataset/4c83e119-3d10-450a-aa68-c29f7242b09d/resource/0c7e176b-a7bb-449e-b984-f4507fb38253/download/special_statuses.csv",
    "https://data.gov.lv/dati/dataset/a0dda750-245d-4d4b-b423-403ab11e1b7f/resource/6b9e88b4-64ec-4b55-bebb-ba55eac65825/download/religious_affiliations.csv",
    "https://data.gov.lv/dati/dataset/b7848ab9-7886-4df0-8bc6-70052a8d9e1a/resource/20a9b26d-d056-4dbb-ae18-9ff23c87bdee/download/beneficial_owners.csv",
    "https://data.gov.lv/dati/dataset/bb54838d-9365-4f76-8513-804f4efa8ab6/resource/8065ad80-1a4d-4afb-b1d1-d93b9a62b1cc/download/insolvency_legal_person_proceeding.csv",
    "https://data.gov.lv/dati/dataset/49a0f06b-2d2b-4368-8ed5-676a05244f6a/resource/263f7def-5df6-45ed-a747-81e1d48a48c4/download/areas_of_activity_of_associations_foundations.csv",
    "https://data.gov.lv/dati/dataset/e1162626-e02a-4545-9236-37553609a988/resource/837b451a-4833-4fd1-bfdd-b45b35a994fd/download/members.csv",
    "https://data.gov.lv/dati/dataset/e1162626-e02a-4545-9236-37553609a988/resource/466e5b9b-b31b-4e68-aaee-ae34254b902b/download/members_joint_owners.csv",
    "https://data.gov.lv/dati/dataset/096c7a47-33cd-4dc9-a876-2c86e86230fd/resource/e665114a-73c2-4375-9470-55874b4cfa6b/download/officers.csv",
    "https://data.gov.lv/dati/dataset/8d31b878-536a-44aa-a013-8bc6b669d477/resource/27fcc5ec-c63b-4bfd-bb08-01f073a52d04/download/financial_statements.csv",
    "https://data.gov.lv/dati/dataset/8d31b878-536a-44aa-a013-8bc6b669d477/resource/50ef4f26-f410-4007-b296-22043ca3dc43/download/balance_sheets.csv",
    "https://data.gov.lv/dati/dataset/8d31b878-536a-44aa-a013-8bc6b669d477/resource/d5fd17ef-d32e-40cb-8399-82b780095af0/download/income_statements.csv",
    "https://data.gov.lv/dati/dataset/8d31b878-536a-44aa-a013-8bc6b669d477/resource/1a11fc29-ba7c-4e5a-8edc-7a28cea24988/download/cash_flow_statements.csv",
    "https://data.gov.lv/dati/dataset/2e4926ea-8648-44e6-9227-3cb20604ec31/resource/190ba502-08d1-4c4c-b1b9-b58299bf9a9f/download/ppi_public_persons_institutions.csv",
    "https://data.gov.lv/dati/dataset/2e4926ea-8648-44e6-9227-3cb20604ec31/resource/090df3ca-0873-42d8-b375-1a2454700a13/download/ppi_delegated_entities.csv",
    "https://data.gov.lv/dati/dataset/4de9697f-850b-45ec-8bba-61fa09ce932f/resource/25e80bf3-f107-4ab4-89ef-251b5b9374e9/download/register.csv",
    "https://data.gov.lv/dati/dataset/bc206e80-4655-435a-859b-f37ae9aadfb0/resource/ad772b8b-76e4-4334-83d9-3beadf513aa6/download/register_name_history.csv",
    "https://data.gov.lv/dati/dataset/9a5eae1c-2438-48cf-854b-6a2c170f918f/resource/610910e9-e086-4c5b-a7ea-0a896a697672/download/pdb_pvnmaksataji_odata.csv",
    "https://data.gov.lv/dati/dataset/41481e3e-630f-4b73-b02e-a415d27896db/resource/acd4c6f9-5123-46a5-80f6-1f44b4517f58/download/reitings_uznemumi.csv",
    "https://data.gov.lv/dati/dataset/5ed74664-b49d-4b28-aacb-040931646e9b/resource/a42d6e8c-1768-4939-ba9b-7700d4f1dd3a/download/pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata.csv",
    "https://data.gov.lv/dati/dataset/364f3fa8-916f-48fe-9c81-12f13adb35f3/resource/9117ca92-7858-41cc-8c78-d5241ae5fcec/download/pdb_samaksato_nodoklu_kopsummas_cet.csv",
];

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
];

// Kolonnas, kurām veido indeksu.
// Otrā rinda: tabulas, kur meklēšanas kolonna saucas savādāk (SEARCH_COLUMNS_MAP_REG_NR
// registrs/lib/config.php). Bez tām katra uzņēmuma lapa skenēja reorganizations (10 825
// rindas, DIVreiz — meklē pa abām pusēm) un insolvency_legal_person_proceeding (17 955).
// Kopā ~1,5 ms uz lapu siltā kešā, kas bija ap 60 % no visa datu ielādes laika.
const INDEX_COLUMNS = [
    'regcode', 'legal_entity_registration_number', 'Numurs', 'Registracijas_kods',
    'member_id', 'statement_id', 'super_id', 'id',
    'source_entity_regcode', 'final_entity_regcode', 'debtor_registration_number',
    'at_legal_entity_registration_number', 'registrationNumber', 'delegatedEntityRegistrationNumber',
];

// Tabulas ar komatu kā atdalītāju (pārējām ';').
// stockholders_joint_owners te bija KĻŪDAINI: fails diskā ir semikolu atdalīts kā visi
// UR faili, tāpēc tabula uzbūvējās kā VIENA kolonna ar visu galveni nosaukumā (40 rindu
// ķīselis). Lapās tas nenonāca (tabula ne tiek meklēta, ne rādīta), bet dati bija broken.
const COMMA_SEP_TABLES = [
    'pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata',
    'pdb_pvnmaksataji_odata', 'reitings_uznemumi',
    'pdb_samaksato_nodoklu_kopsummas_cet',
];

function csv_sep_for(string $table): string {
    return in_array($table, COMMA_SEP_TABLES, true) ? ',' : ';';
}
