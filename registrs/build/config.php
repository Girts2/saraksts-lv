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
    "https://data.gov.lv/dati/dataset/526d69b8-4b81-49a9-94a4-9acf3d601f69/resource/fa10a73f-0a10-4ce7-b0f8-f9652d572a10/download/sanctions.csv",
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

/**
 * Dinamiskie URL, kuros ir GADS. APUS (VVD atkritumu gada pārskati) galapunkts ir
 * /a3report-csv/b/{gads}, un dati ir TIKAI kārtējam gadam — pārējie gadi atdod
 * tukšu failu. Fiksēts gads URL sarakstā nozīmētu, ka 1. janvārī kopa klusi
 * paliek tukša, tāpēc gadu liekam klāt būves brīdī un ņemam arī iepriekšējo.
 */
function build_dinamiskie_urli(): array {
    $g = (int)date('Y');
    $out = [];
    foreach ([$g, $g - 1] as $y) {
        $out[] = "https://apus-api.vvd.gov.lv/API-P/a3report-csv/b/$y#fails=apus_atkritumi_$y.csv";
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
