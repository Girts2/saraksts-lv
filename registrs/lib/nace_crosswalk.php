<?php
// registrs/lib/nace_crosswalk.php — NACE 2.0 → 2.1 precīzā atbilstības karte.
//
// VID/data.gov.lv uzņēmumu dati pārgāja uz NACE 2.1 2024. gadā (2024. g. ieraksti = 99.6% 2.1).
// Pirms-2024 iesniegumi vēl nes NACE 2.0 kodus. Šī karte pārkodē tos veco-kodu "atlicējus" uz
// precīzu 2.1 klasi TIKAI tur, kur atbilstība ir DETERMINISTISKA (viens 2.1 mērķis).
//
// AVOTS: atvasināts no pašu VID datu 2023→2024 pārkodēšanas (uzņēmumi, kam ir gan 2023 vecais,
// gan 2024 jaunais kods; dominants ≥90%, n≥5) — autoritatīvāks šiem datiem par ģenerisko
// Eurostat tabulu, jo rāda, kā LV uzņēmumi REĀLI tika pārkodēti. Nodaļas 45 (autotirdzniecība/
// remonts, 2.1 izņemta) ieraksti sakrīt ar oficiālo Eurostat Rev.2→Rev.2.1 korespondenci
// (un ar Struktūras SEKT_LEGACY_NACE).
//
// SVARĪGI: patiesie SADALĪJUMI (viens 2.0 → vairāki 2.1, piem. 5610→5611/5612, 4719→...) ŠEIT
// NAV — tos per-uzņēmums atrisināt nevar (arī oficiālā tabula ne), tāpēc tie paliek grupas
// līmenī caur hierarhisko fallback. Karte skar tikai nozares APRAKSTU; `nace_code` paliek 2.0.
//
// Pārģenerēšana: registrs/build/nace_crosswalk_gen.php (empīriskā analīze no dzīvās ur_data.db).
declare(strict_types=1);

const NACE_2_0_TO_2_1 = [
    '1411' => '1424', // Ādas un kažokādu apģērbu ražošana
    '1412' => '1423', // Darba apģērbu ražošana
    '1420' => '1424', // Ādas un kažokādu apģērbu ražošana
    '1431' => '1410', // Trikotāžas apģērbu un apģērbu piederumu ražošana
    '1439' => '1410', // Trikotāžas apģērbu un apģērbu piederumu ražošana
    '1729' => '1725', // Citu papīra un kartona izstrādājumu ražošana
    '2319' => '2315', // Citu stikla izstrādājumu ražošana, ieskaitot tehniskā stikla izstrādājumus
    '2349' => '2345', // Citu keramikas izstrādājumu ražošana
    '2369' => '2366', // Citu betona, cementa un ģipša izstrādājumu ražošana
    '2529' => '2522', // Citu metāla cisternu, rezervuāru un tilpņu ražošana
    '2550' => '2540', // Metāla kalšana un veidošana; pulvermetalurģija
    '2572' => '2562', // Slēdzeņu un eņģu ražošana
    '2573' => '2563', // Darbarīku ražošana
    '3101' => '3100', // Mēbeļu ražošana
    '3102' => '3100', // Mēbeļu ražošana
    '3103' => '3100', // Mēbeļu ražošana
    '3109' => '3100', // Mēbeļu ražošana
    '4110' => '6812', // Būvniecības projektu attīstīšana
    '4511' => '4781', // Mehānisko transportlīdzekļu mazumtirdzniecība
    '4519' => '4781', // Mehānisko transportlīdzekļu mazumtirdzniecība
    '4520' => '9531', // Mehānisko transportlīdzekļu remonts un apkope
    '4531' => '4782', // Mehānisko transportlīdzekļu daļu un piederumu mazumtirdzniecība
    '4532' => '4782', // Mehānisko transportlīdzekļu daļu un piederumu mazumtirdzniecība
    '4540' => '4783', // Motociklu un to daļu un piederumu mazumtirdzniecība
    '4651' => '4650', // Informācijas un komunikāciju tehnoloģiju iekārtu vairumtirdzniecība
    '4652' => '4650', // Informācijas un komunikāciju tehnoloģiju iekārtu vairumtirdzniecība
    '4665' => '4647', // Mājsaimniecības, biroja un veikalu mēbeļu, paklāju un apgaismes ierīču vairumtirdzniecība
    '4666' => '4650', // Informācijas un komunikāciju tehnoloģiju iekārtu vairumtirdzniecība
    '4669' => '4664', // Citu mašīnu un iekārtu vairumtirdzniecība
    '4674' => '4684', // Metālizstrādājumu, cauruļu, apkures iekārtu un to piederumu vairumtirdzniecība
    '4675' => '4685', // Ķīmisku produktu vairumtirdzniecība
    '4676' => '4686', // Citu starpproduktu vairumtirdzniecība
    '4677' => '4687', // Atkritumu un lūžņu vairumtirdzniecība
    '5629' => '5622', // Ēdināšanas pakalpojumi uz līguma pamata un citi ēdināšanas pakalpojumi
    '5814' => '5813', // Žurnālu un periodisko izdevumu izdošana
    '6130' => '6110', // Kabeļu, bezvadu un satelītu telekomunikācijas pakalpojumi
    '6201' => '6210', // Datorprogrammēšana
    '6202' => '6220', // Konsultēšana datoru pielietojumu jautājumos un datoriekārtu pārvaldība
    '6203' => '6220', // Konsultēšana datoru pielietojumu jautājumos un datoriekārtu pārvaldība
    '6209' => '6290', // Citi informācijas tehnoloģiju un datoru pakalpojumi
    '6399' => '6392', // Citi informācijas pakalpojumi
    '6810' => '6811', // Sava nekustamā īpašuma pirkšana un pārdošana
    '7021' => '7330', // Sabiedrisko attiecību un komunikācijas pakalpojumi
    '7022' => '7020', // Uzņēmējdarbības konsultācijas un citas vadības konsultācijas
    '7211' => '7210', // Pētniecība un eksperimentālā izstrāde dabaszinātnēs un inženierzinātnēs
    '7219' => '7210', // Pētniecība un eksperimentālā izstrāde dabaszinātnēs un inženierzinātnēs
    '7729' => '7722', // Citu personīgas lietošanas un mājsaimniecības preču iznomāšana un ekspluatācijas līzings
    '7830' => '7820', // Pagaidu darbā iekārtošanas aģentūru darbība un cita cilvēkresursu nodrošināšana
    '8010' => '8001', // Izmeklēšanas un personiskās drošības nodrošināšanas pakalpojumi
    '8020' => '8009', // Citur neklasificēti drošības nodrošināšanas pakalpojumi
    '8030' => '8001', // Izmeklēšanas un personiskās drošības nodrošināšanas pakalpojumi
    '8129' => '8123', // Citi tīrīšanas pakalpojumi
    '8211' => '8210', // Biroju administratīvās darbības un atbalsta pakalpojumi
    '8542' => '8540', // Augstākā izglītība
    '8560' => '8569', // Citur neklasificēti izglītības atbalsta pakalpojumi
    '9001' => '9020', // Izpildītājmāksla
    '9002' => '9039', // Citas mākslas un izpildītājmākslas atbalsta darbības
    '9004' => '9031', // Mākslas iestāžu un objektu darbība
    '9102' => '9121', // Muzeju un mākslas kolekciju darbība
    '9103' => '9122', // Vēsturisku vietu un monumentu darbība
    '9511' => '9510', // Datoru un sakaru iekārtu remonts un apkope
    '9512' => '9510', // Datoru un sakaru iekārtu remonts un apkope
    '9603' => '9630', // Apbedīšana un ar to saistītas darbības
    '9604' => '9623', // Dienas spa, saunu un tvaika pirts darbība
    '9609' => '9699', // Citur neklasificētu individuālo pakalpojumu sniegšana
];
