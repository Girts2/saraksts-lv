<?php
// server/lib/config.php — AUTO-ĢENERĒTS no kods/config/*.py (gen_config_php.py). NEREDIĢĒT MANUĀLI.

// No settings.py
const MAX_SEARCH_TERM_LENGTH = 50;
const MIN_AUTOCOMPLETE_CHARS = 3;
const MAX_AUTOCOMPLETE_SUGGESTIONS = 8;
const BASE_DOMAIN = "https://saraksts.lv";
const ASSETS_BASE_PATH_FOR_HTML = "https://saraksts.lv/assets";

const SEARCH_COLUMNS_MAP_REG_NR = [
    "akf_data" => ["legal_entity_registration_number"],
    "arbitration_list" => ["legal_entity_registration_number"],
    "arbitration_members" => ["at_legal_entity_registration_number"],
    "area_of_activity" => ["legal_entity_registration_number"],
    "areas_of_activity_of_associations_foundations" => ["regcode"],
    "equity_capitals" => ["legal_entity_registration_number"],
    "farm_land" => ["legal_entity_registration_number"],
    "financial_statements" => ["legal_entity_registration_number"],
    "insolvency_legal_person_proceeding" => ["debtor_registration_number"],
    "liquidations" => ["legal_entity_registration_number"],
    "memorandum_date" => ["legal_entity_registration_number"],
    "political_parties" => ["legal_entity_registration_number"],
    "ppi_delegated_entities" => ["delegatedEntityRegistrationNumber", "registrationNumber"],
    "ppi_public_persons_institutions" => ["registrationNumber"],
    "property_investment_appraisers_list" => ["legal_entity_registration_number"],
    "register_name_history" => ["regcode"],
    "register" => ["regcode"],
    "religious_affiliations" => ["legal_entity_registration_number"],
    "securing_measures" => ["legal_entity_registration_number"],
    "special_statuses" => ["legal_entity_registration_number"],
    "suspensions_prohibitions" => ["legal_entity_registration_number"],
    "pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata" => ["Registracijas_kods"],
    "pdb_pvnmaksataji_odata" => ["Numurs"],
    "reitings_uznemumi" => ["Registracijas_kods"],
    "pdb_samaksato_nodoklu_kopsummas_cet" => ["Registracijas_kods"]
];

const TABLE_DISPLAY_CONFIG = [
    "register" => [
        "rank" => 1,
        "title" => "Uzņēmumu reģistrs",
        "link" => "https://data.gov.lv/dati/dataset/uz",
        "mysql_table_name" => "register"
    ],
    "arbitration_list" => [
        "rank" => 2,
        "title" => "Šķīrējtiesu saraksts",
        "link" => "https://data.gov.lv/dati/dataset/arbitration_list",
        "mysql_table_name" => "arbitration_list"
    ],
    "farm_land" => [
        "rank" => 3,
        "title" => "Zemnieku saimniecības zemes platība un tās atrašanās vieta",
        "link" => "https://data.gov.lv/dati/dataset/farm-land",
        "mysql_table_name" => "farm_land"
    ],
    "political_parties" => [
        "rank" => 4,
        "title" => "Politisko partiju un to apvienību saraksts",
        "link" => "https://data.gov.lv/dati/dataset/political-parties",
        "mysql_table_name" => "political_parties"
    ],
    "ppi_public_persons_institutions" => [
        "rank" => 5,
        "title" => "Publisko personu un iestāžu saraksts, Saeima un citi",
        "link" => "https://data.gov.lv/dati/dataset/public-persons-institutions",
        "mysql_table_name" => "ppi_public_persons_institutions"
    ],
    "property_investment_appraisers_list" => [
        "rank" => 6,
        "title" => "Mantiskā ieguldījuma vērtētāju saraksts",
        "link" => "https://data.gov.lv/dati/dataset/property_investment_appraisers_list",
        "mysql_table_name" => "property_investment_appraisers_list"
    ],
    "religious_affiliations" => [
        "rank" => 7,
        "title" => "Reliģisko organizāciju lietās reģistrētā konfesionālā piederības un darbības teritorija",
        "link" => "https://data.gov.lv/dati/dataset/religious-affiliations",
        "mysql_table_name" => "religious_affiliations"
    ],
    "memorandum_date" => [
        "rank" => 8,
        "title" => "Tiesību subjekta lietā reģistrēts lēmuma par dibināšanu pieņemšanas datums",
        "link" => "https://data.gov.lv/dati/dataset/memorandum-date",
        "mysql_table_name" => "memorandum_date"
    ],
    "area_of_activity" => [
        "rank" => 9,
        "title" => "Tiesību subjektu darbības mērķi un veidi",
        "link" => "https://data.gov.lv/dati/dataset/area-of-activity",
        "mysql_table_name" => "area_of_activity"
    ],
    "akf_data" => [
        "rank" => 10,
        "title" => "Paplašināta tiesību subjekta datu kopā saistībā ar ārvalstu komersantu filiāļu datiem",
        "link" => "https://data.gov.lv/dati/dataset/akf-data",
        "mysql_table_name" => "akf_data"
    ],
    "areas_of_activity_of_associations_foundations" => [
        "rank" => 11,
        "title" => "Biedrību un nodibinājumu darbības jomas",
        "link" => "https://data.gov.lv/dati/dataset/biedribu-un-nodibinajumu-darbibas-jomas",
        "mysql_table_name" => "areas_of_activity_of_associations_foundations"
    ],
    "register_name_history" => [
        "rank" => 12,
        "title" => "Tiesību subjektu vēsturiskie nosaukumi",
        "link" => "https://data.gov.lv/dati/dataset/tiesibu-subjektu-vesturiskie-nosaukumi",
        "mysql_table_name" => "register_name_history"
    ],
    "ppi_delegated_entities" => [
        "rank" => 13,
        "title" => "Publisko personu un iestāžu saraksts, deleģējusi valsts",
        "link" => "https://data.gov.lv/dati/dataset/public-persons-institutions",
        "mysql_table_name" => "ppi_delegated_entities"
    ],
    "special_statuses" => [
        "rank" => 14,
        "title" => "Tiesību subjektu nacionālās nozīmes statuss",
        "link" => "https://data.gov.lv/dati/dataset/special-statuses",
        "mysql_table_name" => "special_statuses"
    ],
    "reorganizations" => [
        "rank" => 15,
        "title" => "Tiesību subjektu reorganizācijas",
        "link" => "https://data.gov.lv/dati/dataset/reorganizatons",
        "mysql_table_name" => "reorganizations"
    ],
    "suspensions_prohibitions" => [
        "rank" => 16,
        "title" => "Tiesību subjektu lietās reģistrētie aktuālie darbības aizliegumi un darbības apturēšanas/atjaunošanas",
        "link" => "https://data.gov.lv/dati/dataset/suspensions-prohibitions",
        "mysql_table_name" => "suspensions_prohibitions"
    ],
    "insolvency_legal_person_proceeding" => [
        "rank" => 17,
        "title" => "Maksātnespējas procesi",
        "link" => "https://data.gov.lv/dati/dataset/maksatnespejas-procesi",
        "mysql_table_name" => "insolvency_legal_person_proceeding"
    ],
    "liquidations" => [
        "rank" => 18,
        "title" => "Ziņas par tiesību subjektu likvidācijas procesiem",
        "link" => "https://data.gov.lv/dati/dataset/liquidations",
        "mysql_table_name" => "liquidations"
    ],
    "equity_capitals" => [
        "rank" => 19,
        "title" => "Pamatkapitālu un ieguldījumu informācija",
        "link" => "https://data.gov.lv/dati/dataset/equity-capitals",
        "mysql_table_name" => "equity_capitals"
    ],
    "financial_statements" => [
        "rank" => 20,
        "title" => "Gada pārskatu pamatinformācija",
        "link" => "https://data.gov.lv/dati/dataset/gada-parskatu-finansu-dati",
        "mysql_table_name" => "financial_statements"
    ],
    "income_statements" => [
        "rank" => 21,
        "title" => "Peļņas vai zaudējumu aprēķini",
        "link" => "https://data.gov.lv/dati/dataset/gada-parskatu-finansu-dati",
        "mysql_table_name" => "income_statements"
    ],
    "balance_sheets" => [
        "rank" => 22,
        "title" => "Bilances",
        "link" => "https://data.gov.lv/dati/dataset/gada-parskatu-finansu-dati",
        "mysql_table_name" => "balance_sheets"
    ],
    "cash_flow_statements" => [
        "rank" => 23,
        "title" => "Naudas plūsmas pārskati",
        "link" => "https://data.gov.lv/dati/dataset/gada-parskatu-finansu-dati",
        "mysql_table_name" => "cash_flow_statements"
    ],
    "securing_measures" => [
        "rank" => 24,
        "title" => "Tiesību subjektu lietās reģistrētie nodrošinājumu līdzekļi",
        "link" => "https://data.gov.lv/dati/dataset/securing-measures",
        "mysql_table_name" => "securing_measures"
    ],
    "arbitration_members" => [
        "rank" => 25,
        "title" => "Šķīrējtiesu dibinātāji",
        "link" => "https://data.gov.lv/dati/dataset/arbitration_members",
        "mysql_table_name" => "arbitration_members"
    ],
    "pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata" => [
        "rank" => 32,
        "title" => "Samaksātie nodokļi 3 gados",
        "link" => "https://data.gov.lv/dati/dataset/komersantu-ieprieksejos-tris-taksacijas-gados-samaksato-vid-administreto-nodoklu-kopsummas",
        "mysql_table_name" => "pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata"
    ],
    "pdb_pvnmaksataji_odata" => [
        "rank" => 33,
        "title" => "PVN maksātājs",
        "link" => "https://data.gov.lv/dati/dataset/pvn-maksataji",
        "mysql_table_name" => "pdb_pvnmaksataji_odata"
    ],
    "reitings_uznemumi" => [
        "rank" => 34,
        "title" => "Nodokļu maksātāja reitings",
        "link" => "https://data.gov.lv/dati/dataset/nodoklu-maksataju-reitings",
        "mysql_table_name" => "reitings_uznemumi"
    ]
];

const COLUMN_NAME_TRANSLATIONS = [
    "id" => [
        "short" => "ID",
        "full" => "Ieraksta unikālais identifikators"
    ],
    "name" => [
        "short" => "Nosauk./Vārds",
        "full" => "Subjekta nosaukums vai personas vārds, uzvārds"
    ],
    "address" => [
        "short" => "Adrese",
        "full" => "Juridiskā adrese"
    ],
    "registered_on" => [
        "short" => "Reģ.Dat.",
        "full" => "Ieraksta reģistrācijas datums Uzņēmumu Reģistrā"
    ],
    "last_modified_at" => [
        "short" => "Lab.Dat.",
        "full" => "Ieraksta pēdējo izmaiņu datums"
    ],
    "date_from" => [
        "short" => "No",
        "full" => "Datums, no kura informācija ir spēkā"
    ],
    "date_to" => [
        "short" => "Līdz",
        "full" => "Datums, līdz kuram informācija bija spēkā"
    ],
    "entity_type" => [
        "short" => "Subj.Tips",
        "full" => "Subjekta tips (piem., NATURAL_PERSON - fiziska persona, LEGAL_ENTITY - juridiska persona)"
    ],
    "latvian_identity_number_masked" => [
        "short" => "PK Maska",
        "full" => "Maskēts Latvijas personas kods (pirmie 6 cipari)"
    ],
    "birth_date" => [
        "short" => "Dz.Dat.",
        "full" => "Dzimšanas datums (formāts YYYY-MM vai YYYY-MM-DD)"
    ],
    "legal_entity_registration_number" => [
        "short" => "Reģ.Nr.",
        "full" => "Juridiskās personas vienotais reģistrācijas numurs"
    ],
    "legal_entity_name" => [
        "short" => "Jur.Nosauk.",
        "full" => "Juridiskās personas pilnais nosaukums"
    ],
    "currency" => [
        "short" => "Valūta",
        "full" => "Valūta. Iespējamās vērtības: EUR; LVL (ja pārskata gada beigas < 01.01.2014)."
    ],
    "uri" => [
        "short" => "URI",
        "full" => "Unikālais resursa identifikators (specifisks UR sistēmā)"
    ],
    "notes" => [
        "short" => "Piezīmes",
        "full" => "Papildu piezīmes vai komentāri"
    ],
    "info" => [
        "short" => "Info",
        "full" => "Vispārīga papildus informācija"
    ],
    "regcode" => [
        "short" => "Reģ.Kods",
        "full" => "Subjekta vienotais reģistrācijas numurs (parasti 11 zīmes)"
    ],
    "sepa" => [
        "short" => "SEPA ID",
        "full" => "SEPA (Single Euro Payments Area) kreditora identifikators"
    ],
    "name_before_quotes" => [
        "short" => "PirmsPēd.",
        "full" => "Nosaukuma daļa pirms pēdiņām (bieži juridiskā forma, piem., \"Sabiedrība ar ierobežotu atbildību\")"
    ],
    "name_in_quotes" => [
        "short" => "Pēdiņās",
        "full" => "Nosaukuma daļa pēdiņās (unikālais nosaukums, piem., \"Saulespuķe\")"
    ],
    "name_after_quotes" => [
        "short" => "AizPēd.",
        "full" => "Nosaukuma daļa aiz pēdiņām (reti, piem., zemnieku saimniecībām)"
    ],
    "without_quotes" => [
        "short" => "BezPēd.?",
        "full" => "Pazīme: 1 - nosaukums nav ietverts pēdiņās; 0 - ir vai ir strukturēts"
    ],
    "regtype" => [
        "short" => "Reģ.TipsKods",
        "full" => "Reģistra veida kods (K - Komercreģistrs, B - Biedrību un nodibinājumu reģistrs, U - UR žurnāls, R - Reliģisko organizāciju reģistrs)"
    ],
    "regtype_text" => [
        "short" => "Reģ.Tips",
        "full" => "Reģistra veida pilnais nosaukums"
    ],
    "type" => [
        "short" => "Subj.Veids",
        "full" => "Subjekta tiesiskās formas kods (piem., SIA, IK, AS, BDR)"
    ],
    "type_text" => [
        "short" => "Subj.VeidsNos.",
        "full" => "Subjekta tiesiskās formas pilnais nosaukums"
    ],
    "registered" => [
        "short" => "Reģistrēts",
        "full" => "Subjekta sākotnējās reģistrācijas vai reorganizācijas datums"
    ],
    "terminated" => [
        "short" => "Izbeigts",
        "full" => "Darbības izbeigšanas (piem., likvidācijas sākuma) datums"
    ],
    "closed" => [
        "short" => "Slēgts",
        "full" => "Statuss par izslēgšanu no reģistra (L - Likvidēts, R - Reorganizēts)"
    ],
    "index" => [
        "short" => "Indekss",
        "full" => "Pasta indekss"
    ],
    "addressid" => [
        "short" => "AdresesID",
        "full" => "Adreses unikālais kods Valsts adrešu reģistrā"
    ],
    "region" => [
        "short" => "Reģions",
        "full" => "Administratīvās teritoriālās vienības kods"
    ],
    "city" => [
        "short" => "Pils./Nov.",
        "full" => "Pilsētas vai novada kods"
    ],
    "atvk" => [
        "short" => "ATVK",
        "full" => "Administratīvi teritoriālās vienības kods saskaņā ar ATVK klasifikatoru"
    ],
    "reregistration_term" => [
        "short" => "Pārreģ.Term.",
        "full" => "Noteiktais pārreģistrācijas termiņš (vēsturisks)"
    ],
    "at_legal_entity_registration_number" => [
        "short" => "Uzņ.Reģ.Nr.",
        "full" => "Saistītās juridiskās personas (piem., mātesuzņēmuma) reģistrācijas numurs"
    ],
    "number_of_shares" => [
        "short" => "Daļu/Akc.Sk.",
        "full" => "Piederošo daļu vai akciju skaits"
    ],
    "share_nominal_value" => [
        "short" => "Daļas/Akc.Nom.",
        "full" => "Vienas daļas vai akcijas nominālvērtība"
    ],
    "share_currency" => [
        "short" => "Daļu/Akc.Val.",
        "full" => "Daļu vai akciju nominālvērtības valūta"
    ],
    "votes" => [
        "short" => "Balsis",
        "full" => "Balsstiesību skaits, ko nodrošina daļas/akcijas"
    ],
    "stock_type" => [
        "short" => "Akc.Veids",
        "full" => "Akciju veids (piem., parastās, priekšrocību)"
    ],
    "depository_registration_number" => [
        "short" => "Dep.ReģNr",
        "full" => "Depozitārija reģistrācijas numurs, ja akcijas ir depozitārijā"
    ],
    "depository_name" => [
        "short" => "Dep.Nosauk.",
        "full" => "Depozitārija nosaukums"
    ],
    "member_id" => [
        "short" => "DalībniekaID",
        "full" => "Dalībnieka vai akcionāra unikālais ID (saistībai ar citām tabulām)"
    ],
    "super_id" => [
        "short" => "Super ID",
        "full" => "Iekšējais unikālais identifikators"
    ],
    "file_id" => [
        "short" => "File ID",
        "full" => "Finanšu pārskata faila ID"
    ],
    "statement_id" => [
        "short" => "Statement ID",
        "full" => "Finanšu pārskata ID"
    ],
    "source_schema" => [
        "short" => "Avota Shēma",
        "full" => "VID elektroniskā dokumenta shēma"
    ],
    "source_type" => [
        "short" => "Avota Tips",
        "full" => "VID gada pārskata veids. UR publicē UGP (Uzņēmuma gada pārskats), UKGP (Konsolidētais uzņēmuma gada pārskats), BNAGP (Biedrību, nodibinājumu un arodbiedrību gada pārskats)."
    ],
    "year" => [
        "short" => "Gads",
        "full" => "Pārskata gads"
    ],
    "year_started_on" => [
        "short" => "Gada Sākums",
        "full" => "Pārskata gada sākums"
    ],
    "year_ended_on" => [
        "short" => "Gada Beigas",
        "full" => "Pārskata gada beigas"
    ],
    "employees" => [
        "short" => "Darb. Skaits",
        "full" => "Vidējais darbinieku skaits uzņēmumā"
    ],
    "rounded_to_nearest" => [
        "short" => "Noapaļošana",
        "full" => "Noapaļošanas pakāpe: Norāda, kādās vienībās attēlo gada pārskata summas. Pieļaujamās vērtības: ONES – vieni; THOUSANDS – tūkstoši; MILLIONS – miljoni."
    ],
    "cash" => [
        "short" => "Nauda",
        "full" => "Nauda"
    ],
    "marketable_securities" => [
        "short" => "Īst. Vērtspapīri",
        "full" => "Īstermiņa finanšu ieguldījumi"
    ],
    "accounts_receivable" => [
        "short" => "Debitori",
        "full" => "Debitori kopā"
    ],
    "inventories" => [
        "short" => "Krājumi",
        "full" => "Krājumi kopā"
    ],
    "total_current_assets" => [
        "short" => "Apgrozāmie Līdz.",
        "full" => "Apgrozāmie līdzekļi kopā"
    ],
    "investments" => [
        "short" => "Ilg. Fin. Ieguld.",
        "full" => "Ilgtermiņa finanšu ieguldījumi kopā"
    ],
    "fixed_assets" => [
        "short" => "Pamatlīdzekļi",
        "full" => "Pamatlīdzekļi kopā"
    ],
    "intangible_assets" => [
        "short" => "Nemater. Ieguld.",
        "full" => "Nemateriālie ieguldījumi kopā"
    ],
    "total_non_current_assets" => [
        "short" => "Kopā Ilgter. Ieguld.",
        "full" => "Ilgtermiņa ieguldījumi kopā"
    ],
    "total_assets" => [
        "short" => "Aktīvi Kopā",
        "full" => "Kopā aktīvs"
    ],
    "future_housing_repairs_payments" => [
        "short" => "Mājokļa Rem. Maks.",
        "full" => "Maksājumi dzīvojamās mājas nākotnes remontiem"
    ],
    "current_liabilities" => [
        "short" => "Īsterm. Saistības",
        "full" => "Īstermiņa kreditori kopā"
    ],
    "non_current_liabilities" => [
        "short" => "Ilgterm. Saistības",
        "full" => "Ilgtermiņa kreditori kopā"
    ],
    "provisions" => [
        "short" => "Uzkrājumi",
        "full" => "Uzkrājumi kopā"
    ],
    "equity" => [
        "short" => "Pašu Kapitāls",
        "full" => "Pašu kapitāls kopā"
    ],
    "total_equities" => [
        "short" => "Pasīvi Kopā",
        "full" => "Kopā pasīvs"
    ],
    "net_turnover" => [
        "short" => "Neto Apgrozījums",
        "full" => "Neto apgrozījums"
    ],
    "by_nature_inventory_change" => [
        "short" => "Krājumu Izm.(Būtība)",
        "full" => "Gatavās produkcijas un nepabeigto ražojumu krājumu izmaiņas"
    ],
    "by_nature_long_term_investment_expenses" => [
        "short" => "Kapital. Izm.(Būtība)",
        "full" => "Uz pašu ilgtermiņa ieguldījumiem attiecinātās (kapitalizētās) izmaksas"
    ],
    "by_nature_other_operating_revenues" => [
        "short" => "Pār. Ieņ.(Būtība)",
        "full" => "Pārējie saimnieciskās darbības ieņēmumi"
    ],
    "by_nature_material_expenses" => [
        "short" => "Materiālu Izm.(Būtība)",
        "full" => "Materiālu izmaksas"
    ],
    "by_nature_labour_expenses" => [
        "short" => "Personāla Izm.(Būtība)",
        "full" => "Personāla izmaksas"
    ],
    "by_nature_depreciation_expenses" => [
        "short" => "Nolietojums (Būtība)",
        "full" => "Vērtības samazinājuma korekcijas (nolietojums un amortizācija)"
    ],
    "other_operating_expenses" => [
        "short" => "Pār. Izm.",
        "full" => "Pārējās saimnieciskās darbības izmaksas"
    ],
    "equity_investment_earnings" => [
        "short" => "Ieņ. no Līdzdal.",
        "full" => "Ieņēmumi no līdzdalības"
    ],
    "other_long_term_investment_earnings" => [
        "short" => "Ieņ. Pār. Ilg. Ieg.",
        "full" => "Ieņēmumi no pārējiem vērtspapīriem un aizdevumiem, kas veidojuši ilgtermiņa finanšu ieguldījumus"
    ],
    "other_interest_revenues" => [
        "short" => "Pār. Proc. Ieņ.",
        "full" => "Pārējie procentu ieņēmumi un tamlīdzīgi ieņēmumi"
    ],
    "investment_fair_value_adjustments" => [
        "short" => "Fin. Ieg. Vērt. Izm.",
        "full" => "Ilgtermiņa un īstermiņa finanšu ieguldījumu vērtības samazinājuma korekcijas (patiesās vērtības izmaiņas)"
    ],
    "interest_expenses" => [
        "short" => "Proc. Izm.",
        "full" => "Procentu maksājumi un tamlīdzīgas izmaksas"
    ],
    "extra_revenues" => [
        "short" => "Ārkārtas Ieņ.",
        "full" => "Ārkārtas ieņēmumi"
    ],
    "extra_expenses" => [
        "short" => "Ārkārtas Izm.",
        "full" => "Ārkārtas izmaksa"
    ],
    "income_before_income_taxes" => [
        "short" => "Peļņa pirms UIN",
        "full" => "Peļņa vai zaudējumi pirms uzņēmumu ienākuma nodokļa"
    ],
    "provision_for_income_taxes" => [
        "short" => "UIN",
        "full" => "Uzņēmumu ienākuma nodoklis par pārskata gadu"
    ],
    "income_after_income_taxes" => [
        "short" => "Peļņa pēc UIN",
        "full" => "Peļņa vai zaudējumi pēc uzņēmumu ienākuma nodokļa aprēķināšanas"
    ],
    "other_taxes" => [
        "short" => "Citi Nod.",
        "full" => "Pārējie nodokļi (ne UIN)"
    ],
    "extra_dividends" => [
        "short" => "Ārkārtas Divid.",
        "full" => "Ārkārtas dividendes"
    ],
    "net_income" => [
        "short" => "Tīrā Peļņa",
        "full" => "Pārskata gada peļņa vai zaudējumi (tīrā peļņa)"
    ],
    "by_function_cost_of_goods_sold" => [
        "short" => "Pārd. Pašizm.",
        "full" => "Pārdotās produkcijas ražošanas pašizmaksa, pārdoto preču vai sniegto pakalpojumu iegādes izmaksas"
    ],
    "by_function_gross_profit" => [
        "short" => "Bruto Peļņa",
        "full" => "Bruto peļņa vai zaudējumi"
    ],
    "by_function_selling_expenses" => [
        "short" => "Pārdoš. Izm.",
        "full" => "Pārdošanas izmaksas"
    ],
    "by_function_administrative_expenses" => [
        "short" => "Admin. Izm.",
        "full" => "Administrācijas izmaksas"
    ],
    "by_function_other_operating_revenues" => [
        "short" => "Pār. Ieņ.(Funkcija)",
        "full" => "Pārējie saimnieciskās darbības ieņēmumi"
    ],
    "Registracijas_kods" => [
        "short" => "Reģ. Kods",
        "full" => "Uzņēmuma reģistrācijas kods"
    ],
    "Nosaukums" => [
        "short" => "Nosaukums",
        "full" => "Uzņēmuma nosaukums"
    ],
    "Taksacijas_gads" => [
        "short" => "Gads",
        "full" => "Taksācijas gads"
    ],
    "Uznemejdarbibas_forma" => [
        "short" => "Forma",
        "full" => "Uzņēmējdarbības forma"
    ],
    "Pamatdarbibas_NACE_kods" => [
        "short" => "NACE kods",
        "full" => "Pamatdarbības NACE kods"
    ],
    "Juridiska_adrese_ATVK_kods" => [
        "short" => "Adreses ATVK",
        "full" => "Juridiskās adreses ATVK kods"
    ],
    "Juridiska_adrese_ATVK_nosaukums" => [
        "short" => "Adreses ATVK nos.",
        "full" => "Juridiskās adreses ATVK nosaukums"
    ],
    "Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR" => [
        "short" => "Nodokļi kopā (tūkst. EUR)",
        "full" => "Samaksāto VID administrēto nodokļu kopsumma tūkstošos EUR"
    ],
    "Taja_skaita_IIN" => [
        "short" => "IIN (tūkst. EUR)",
        "full" => "Tajā skaitā samaksātais Iedzīvotāju ienākuma nodoklis"
    ],
    "Taja_skaita_VSAOI" => [
        "short" => "VSAOI (tūkst. EUR)",
        "full" => "Tajā skaitā samaksātās Valsts sociālās apdrošināšanas obligātās iemaksas"
    ],
    "Videjais_nodarbinato_personu_skaits_cilv" => [
        "short" => "Vid. darb. sk.",
        "full" => "Vidējais nodarbināto personu skaits"
    ],
    "Numurs" => [
        "short" => "PVN Nr.",
        "full" => "PVN maksātāja numurs"
    ],
    "Aktivs" => [
        "short" => "Aktīvs",
        "full" => "Statusa pazīme (ir/nav)"
    ],
    "Registrets" => [
        "short" => "Reģistrēts",
        "full" => "Reģistrācijas datums PVN maksātāju reģistrā"
    ],
    "Buvniecibas_pazime" => [
        "short" => "Būvn. pazīme",
        "full" => "Būvniecības nozares pazīme (ir/nav)"
    ],
    "Izslegts" => [
        "short" => "Izslēgts",
        "full" => "Izslēgšanas datums no PVN maksātāju reģistra"
    ],
    "Reitings" => [
        "short" => "Reitings",
        "full" => "Nodokļu maksātāja reitings (A, B, C, N)"
    ],
    "Skaidrojums" => [
        "short" => "Skaidrojums",
        "full" => "Reitinga skaidrojums"
    ],
    "Informacijas_atjaunosanas_datums" => [
        "short" => "Atjaunots",
        "full" => "Informācijas atjaunošanas datums"
    ]
];
