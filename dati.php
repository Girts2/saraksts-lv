<?php
/**
 * dati.php — "Par datiem" (publiskā lapa).
 * Neitrāls datu avotu un labojumu apraksts + kompakta VDAR sadaļa. Apzināti BEZ
 * uzsaukumiem iebilst/sūdzēties (2026-08-12 Girta norāde: virsraksti "precizitāte
 * un iebildumi" mudināja apstrīdēt lapā rakstīto). Pilnais VDAR teksts ar DVI
 * paliek privātuma politikā (cookie/cookieconsent-init.js) — juridiskā bāze tur.
 * Saites uz šo lapu — kājenē (abi režīmi).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('registrs');

$pageTitle = "Par datiem";
$pageDesc = "Kā Saraksts.lv iegūst, aprēķina un atjauno datus no valsts atvērtajiem datiem. Vietnes aprēķini un apkopojumi pieejami ar CC BY 4.0 licenci.";

$ur_db = __DIR__ . '/csv/SQLite/ur_data.db';
$data_updated = is_file($ur_db) ? date('Y-m-d', (int)@filemtime($ur_db)) : null;

// LICENCE (2026-09-17). Visas 51 Reģistra avota kopas data.gov.lv ir CC0 1.0 (pārbaudīts
// CKAN API: license_id katrai kopai no registrs/build/config.php + 4 kopām, ko būve ņem
// tieši no iestādes), tāpēc saviem atvasinājumiem licenci drīkstam izvēlēties paši: CC BY 4.0.
// (Kopš 2026-10-14 vēsturisko nosaukumu failu ņemam no UR dati.ur.gov.lv — tā ir tā pati
// data.gov.lv CC0 kopa "tiesibu-subjektu-vesturiskie-nosaukumi", tikai svaigāks spogulis.)
// TVĒRUMS IR PA SADAĻĀM UN IZSMEĻOŠS (recenzija 2026-09-17): licence sedz TIKAI sadaļas, kas
// būvētas no CC0 avotiem — Reģistrs, Nozare, TOP, Struktūra, Publiskie īpašnieki. NESEDZ:
// (1) fizisko personu datus — CC licence neatceļ VDAR, un IK/ZEM/IND nosaukums PATS ir personas
//     vārds (tāpēc nekur nerakstām "JSON bez fizisko personu datiem" — pareizi ir "bez amatpersonu,
//     dalībnieku un patieso labuma guvēju tabulām");
// (2) Konkursi, Granti (svešu portālu saturs + mašīntulkojumi), Iespēja (OpenStreetMap ODbL ar
//     "share-alike"), Horoskops (O*NET CC BY, Swiss Ephemeris AGPL);
// (3) Pensionārs — atlase balstās uz vienīgā PLG dzimšanas gadu, t.i. uz personas datiem;
// (4) pirmkodu — tam ir MIT licence.
// Tas pats teksts ir vēl četrās vietās, un tām JĀSAKRĪT: lietošanas noteikumu 2. punkts
// (registrs/cookie/cookieconsent-init.js), kājene (registrs/footer/footer.php), llms.txt
// un JSON eksporta _avots bloks (company_json.php).
// JSON-LD "Dataset": licence mašīnlasāmā veidā + isBasedOn uz avota iestāžu lapām data.gov.lv.
$pageJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Dataset',
    '@id' => 'https://saraksts.lv/dati.php#dataset',
    // Nosaukums un apraksts uzskaita TIKAI to, ko licence sedz — meklētāji (Google Dataset Search)
    // redz tikai šo bloku, ne 4. sadaļas izņēmumus, tāpēc izņēmumi ir atkārtoti pašā aprakstā.
    'name' => 'Saraksts.lv — Latvijas uzņēmumu finanšu rādītāju aprēķini un kopsavilkumi',
    'description' => 'Saraksts.lv aprēķini un apkopojumi no valsts atvērtajiem datiem: finanšu koeficienti, '
        . 'no VID datiem aprēķinātās vidējās algas, nozaru, novadu un pilsētu kopsavilkumi, TOP saraksti un '
        . 'uzņēmumu lapu mašīnlasāmais JSON eksports (bez amatpersonu, dalībnieku un patieso labuma guvēju '
        . 'tabulām). Avota dati ir valsts iestāžu atvērtie dati ar CC0 1.0 licenci; uz fizisko personu '
        . 'datiem licence neattiecas.',
    'url' => 'https://saraksts.lv/dati.php',
    'usageInfo' => 'https://saraksts.lv/dati.php#licence',
    'inLanguage' => 'lv',
    'isAccessibleForFree' => true,
    'license' => 'https://creativecommons.org/licenses/by/4.0/',
    'creator' => ['@type' => 'Organization', 'name' => 'Saraksts.lv', 'url' => 'https://saraksts.lv/'],
    'spatialCoverage' => ['@type' => 'Place', 'name' => 'Latvija'],
    'keywords' => ['Latvijas uzņēmumi', 'Uzņēmumu reģistrs', 'gada pārskati', 'finanšu koeficienti',
                   'vidējā alga', 'nozaru statistika', 'atvērtie dati'],
    'isBasedOn' => [
        'https://data.gov.lv/dati/lv/organization/ur',
        'https://data.gov.lv/dati/lv/organization/valsts-ienemumu-dienests',
        'https://data.gov.lv/dati/lv/organization/buvniecibas-valsts-kontroles-birojs',
        'https://data.gov.lv/dati/lv/organization/ptac',
        'https://data.gov.lv/dati/lv/organization/centrala-finansu-un-ligumu-agentura',
        'https://data.gov.lv/dati/lv/organization/vdaa',
        'https://data.gov.lv/dati/lv/organization/zalu-valsts-agentura',
        'https://data.gov.lv/dati/lv/organization/vvd',
        'https://data.gov.lv/dati/lv/organization/finansu-ministrija',
    ],
];
if ($data_updated !== null) {
    $pageJsonLd['dateModified'] = $data_updated;
}

ob_start(); ?>
    <style>
      main.dp-main { max-width: 860px; margin: 0 auto; padding: 16px 20px 60px; }
      .dp-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 24px 28px; margin-bottom: 20px; font-size: 15px; line-height: 1.6; color: #1f2937; }
      .dp-panel h1 { font-size: 24px; margin: 0 0 14px; }
      .dp-panel h2 { font-size: 18px; margin: 22px 0 10px; }
      .dp-panel ul { margin: 8px 0 12px; padding-left: 22px; }
      .dp-panel li { margin-bottom: 6px; }
      .dp-muted { color: #6b7280; font-size: 13.5px; }
      .dp-mail { font-weight: 600; }
      .dp-panel a { color: #3451d1; }
      .dp-panel code { font-size: 13.5px; overflow-wrap: anywhere; }
    </style>
<?php $extraHeadContent = ob_get_clean(); ?>
<!DOCTYPE html>
<html lang="lv">
<?php include __DIR__ . '/registrs/head/head.php'; ?>
<body>
<?php include __DIR__ . '/registrs/header.php'; ?>
<main class="dp-main">
    <div class="dp-panel">
        <h1>Par datiem</h1>

        <h2>1. No kurienes nāk dati</h2>
        <p>Saraksts.lv atkalizmanto valsts atvērtos datus: Latvijas Republikas Uzņēmumu reģistra datu kopas
        un VID publiskojamās datubāzes datu kopas no portāla <a href="https://data.gov.lv" rel="noopener">data.gov.lv</a>.
        Dati tiek regulāri automātiski atjaunoti<?= $data_updated !== null ? ' (pašreizējās kopijas datums: ' . htmlspecialchars($data_updated) . ')' : '' ?>;
        atjaunošanas datums ir norādīts arī katras uzņēmuma lapas faktu panelī.
        Finanšu koeficienti, vidējās algas un citi rādītāji ir mūsu aprēķini no šiem publiskajiem datiem —
        katram aprēķinam lapā ir norādīta formula un atruna, ka tas ir informatīvs.</p>
        <p>Uzņēmumu lapu papildu sadaļās izmantotas arī Būvniecības valsts kontroles biroja (BIS),
        Patērētāju tiesību aizsardzības centra, Centrālās finanšu un līgumu aģentūras (ES fondu projekti),
        Valsts digitālās attīstības aģentūras (EIS iepirkumu rezultāti), Zāļu valsts aģentūras,
        Valsts vides dienesta un Finanšu ministrijas (<em>de minimis</em> atbalsts) atvērto datu kopas;
        avots ar saiti uz datu kopu ir norādīts katrā sadaļā.</p>
        <p class="dp-muted">Oficiāli un juridiski saistoši dati ir tikai pirmavotos:
        <a href="https://info.ur.gov.lv" rel="noopener">info.ur.gov.lv</a> (UR) un
        <a href="https://www.vid.gov.lv" rel="noopener">vid.gov.lv</a> (VID).</p>

        <p>Sadaļā <a href="/granti/">Granti</a> dati nāk no diviem citiem avotiem, ne no data.gov.lv:
        Eiropas Komisijas <a href="https://ec.europa.eu/info/funding-tenders/opportunities/portal/" rel="noopener">Finansējuma
        un konkursu portāla</a> (atkalizmantošana atļauta ar Komisijas Lēmumu 2011/833/ES) un
        <a href="https://sif.map.gov.lv/contests" rel="noopener">Sabiedrības integrācijas fonda</a> publiskā
        konkursu saraksta. Konkursu apraksti ES portālā ir angliski; latviskais teksts vietnē ir
        <strong>mašīntulkojums</strong>, un katrā konkursa lapā ir poga uz oriģinālu. Juridiski saistošs
        ir tikai konkursa dokuments avota portālā.</p>

        <h2>2. Labojumi</h2>
        <p>Ja vietnes aprēķinā vai attēlojumā ir neprecizitāte, izlabojam paši — rakstiet uz
        <span class="dp-mail">info@example.com</span>, norādot reģistrācijas numuru un konkrēto vietu.
        Avota datu (UR, VID) labojumi notiek pirmavotā; vietne izmaiņas pārņem automātiski
        nākamajā datu atjaunošanā. VID nodokļu datus par periodiem, ko VID savās datu kopās vairs
        nepublicē (tajās ir tikai pēdējais ceturksnis un iepriekšējie trīs gadi), rādām pēdējā
        publicētajā versijā; individuālo komersantu gada datus par šādiem gadiem nerādām.</p>

        <h2>3. Personas dati</h2>
        <ul>
            <li>personas kodus un dzimšanas datus nepublicējam;</li>
            <li>vidējās algas aprēķinu nerādām uzņēmumiem ar mazāk nekā 3 darbiniekiem;</li>
            <li>mašīnlasāmajā datu eksportā (JSON) fizisko personu tabulu nav;</li>
            <li>visi vērtējošie rādītāji ir informatīvi aprēķini ar atklātu metodiku.</li>
        </ul>
        <p>Fizisko personu datu apstrādes pamats ir leģitīma interese (VDAR 6. panta 1. punkta
        f) apakšpunkts). Datu subjektu pieprasījumus (piekļuve, labošana, iebildumi — VDAR
        21. pants) izskatām viena mēneša laikā: <span class="dp-mail">info@example.com</span>.</p>

        <h2 id="licence">4. Licence</h2>
        <p>Saraksts.lv veidotie aprēķini un apkopojumi sadaļās Reģistrs, Nozare, TOP, Struktūra un
        Publiskie īpašnieki — finanšu koeficienti, vidējās algas, nozaru, novadu un pilsētu kopsavilkumi,
        TOP saraksti un uzņēmumu lapu mašīnlasāmais eksports (JSON) — ir pieejami ar licenci
        <a href="https://creativecommons.org/licenses/by/4.0/deed.lv" rel="license noopener">Creative Commons
        Attiecinājums 4.0 Starptautisks (CC BY 4.0)</a>. Tos drīkst kopēt, izplatīt un pārveidot
        jebkuram mērķim, arī komerciālam, norādot avotu.</p>
        <p>Atsauces paraugs: <em>Avots: Saraksts.lv (CC BY 4.0)</em> ar saiti uz lapu, no kuras dati ņemti,
        piemēram, <code>https://saraksts.lv/40103562412</code>. Ja dati ir pārveidoti, to norāda.</p>
        <ul>
            <li>Avota dati ir valsts iestāžu atvērtie dati ar CC0 1.0 licenci — tos var brīvi ņemt arī
            tieši no portāla <a href="https://data.gov.lv" rel="noopener">data.gov.lv</a>.</li>
            <li>Uz fizisko personu datiem licence neattiecas: to tālāku izmantošanu nosaka VDAR.</li>
            <li>Uz sadaļām Konkursi, Granti, Iespēja, Horoskops un Pensionārs licence neattiecas —
            tām ir savi avoti un noteikumi.</li>
            <li>Vietnes pirmkodam ir MIT licence — <a href="/lejupielade.php">Lejupielāde</a>.</li>
        </ul>

        <p class="dp-muted">Pilni teksti:
        <a href="#" onclick="if (window.showInfoModal) showInfoModal('privacy'); return false;">Privātuma politika</a> ·
        <a href="#" onclick="if (window.showInfoModal) showInfoModal('terms'); return false;">Lietošanas noteikumi</a>
        (tie paši teksti ir arī logā "Mainīt sīkdatņu iestatījumus" lapas kājenē).</p>
    </div>
</main>
<?php include __DIR__ . '/registrs/footer/footer.php'; ?>
</body>
</html>
