<?php
/**
 * dati.php — "Datu precizitāte, labošana un iebildumi" (publiskā lapa).
 * Iebildumu/kļūdu kanāls: kļūdu ziņošana jebkuram lietotājam + VDAR 21. panta
 * iebildumu kārtība datu subjektiem. Saites uz šo lapu — kājenē (abi režīmi).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('registrs');

$pageTitle = "Datu precizitāte, labošana un iebildumi";
$pageDesc = "Kā Saraksts.lv iegūst un atjauno datus, kā ziņot par kļūdu un kā fiziskām personām iesniegt iebildumus (VDAR 21. pants).";

$ur_db = __DIR__ . '/csv/SQLite/ur_data.db';
$data_updated = is_file($ur_db) ? date('Y-m-d', (int)@filemtime($ur_db)) : null;

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
    </style>
<?php $extraHeadContent = ob_get_clean(); ?>
<!DOCTYPE html>
<html lang="lv">
<?php include __DIR__ . '/registrs/head/head.php'; ?>
<body>
<?php include __DIR__ . '/registrs/header.php'; ?>
<main class="dp-main">
    <div class="dp-panel">
        <h1>Datu precizitāte, labošana un iebildumi</h1>

        <h2>1. No kurienes nāk dati</h2>
        <p>Saraksts.lv atkalizmanto valsts atvērtos datus: Latvijas Republikas Uzņēmumu reģistra datu kopas
        un VID publiskojamās datubāzes datu kopas no portāla <a href="https://data.gov.lv" rel="noopener">data.gov.lv</a>.
        Dati tiek regulāri automātiski atjaunoti<?= $data_updated !== null ? ' (pašreizējās kopijas datums: ' . htmlspecialchars($data_updated) . ')' : '' ?>;
        atjaunošanas datums ir norādīts arī katras uzņēmuma lapas faktu panelī.
        Finanšu koeficienti, vidējās algas un citi rādītāji ir mūsu aprēķini no šiem publiskajiem datiem —
        katram aprēķinam lapā ir norādīta formula un atruna, ka tas ir informatīvs.</p>
        <p class="dp-muted">Oficiāli un juridiski saistoši dati ir tikai pirmavotos:
        <a href="https://info.ur.gov.lv" rel="noopener">info.ur.gov.lv</a> (UR) un
        <a href="https://www.vid.gov.lv" rel="noopener">vid.gov.lv</a> (VID).</p>

        <h2>2. Pamanījāt kļūdu datos?</h2>
        <p>Rakstiet uz <span class="dp-mail">info@example.com</span>, norādot:</p>
        <ul>
            <li>uzņēmuma <strong>reģistrācijas numuru</strong> vai lapas adresi;</li>
            <li><strong>kas tieši</strong> ir nepareizi (kurš skaitlis, teksts vai tabulas rinda);</li>
            <li>ja iespējams — pareizo vērtību un tās avotu.</li>
        </ul>
        <p>Kā notiek labošana:</p>
        <ul>
            <li>ja kļūda ir <strong>mūsu aprēķinā vai attēlojumā</strong> — izlabojam paši;</li>
            <li>ja kļūda ir <strong>avota datos</strong> (UR vai VID datu kopās) — tā jālabo pirmavotā
                (iesniedzot precizējumu UR vai VID); mūsu vietne izmaiņas pārņems automātiski nākamajā
                datu atjaunošanā. Acīmredzamas avota kļūdas gadījumā varam attiecīgo ierakstu
                līdz labojumam apzīmēt vai noņemt no rādīšanas.</li>
        </ul>

        <h2>3. Fizisko personu tiesības (VDAR)</h2>
        <p>Vietnē no Uzņēmumu reģistra atvērtajiem datiem parādās arī fizisko personu dati
        (piemēram, dalībnieku vārdi). Apstrādes mērķis ir komercdarbības vides caurskatāmība;
        tiesiskais pamats — VDAR 6. panta 1. punkta f) apakšpunkts (leģitīma interese).
        Jums ir tiesības pieprasīt piekļuvi saviem datiem, to labošanu un <strong>iebilst pret apstrādi
        (VDAR 21. pants)</strong> — īpaši, ja dati ir neprecīzi, kļūdaini attiecināti uz citu personu
        vai attiecas uz sen likvidētu subjektu un to pieejamība būtiski aizskar Jūsu tiesības.</p>
        <p>Iebildumu sūtiet uz <span class="dp-mail">info@example.com</span> ar norādi "VDAR iebildums",
        pievienojot uzņēmuma reģistrācijas numuru, savu saistību ar to un pamatojumu.
        Pieprasījumu izskatām bez nepamatotas kavēšanās, ne vēlāk kā viena mēneša laikā;
        pamatota iebilduma gadījumā attiecīgos ierakstus labojam, ierobežojam vai dzēšam no vietnes.
        Jums ir arī tiesības iesniegt sūdzību <a href="https://www.dvi.gov.lv" rel="noopener">Datu valsts inspekcijā</a>.</p>

        <h2>4. Ko darām proaktīvi</h2>
        <ul>
            <li>personas kodus un dzimšanas datus <strong>nepublicējam</strong>;</li>
            <li>vidējās algas aprēķinu <strong>nerādām uzņēmumiem ar mazāk nekā 3 darbiniekiem</strong> (lai
                "vidējā alga" neatklātu konkrētas personas atalgojumu);</li>
            <li>mašīnlasāmajā datu eksportā (JSON) fizisko personu tabulas <strong>neiekļaujam</strong>;</li>
            <li>likvidētu uzņēmumu datu izpētes panelī fizisko personu vārdus nerādām;</li>
            <li>visi vērtējošie rādītāji ir apzīmēti kā informatīvi aprēķini ar atklātu metodiku.</li>
        </ul>

        <p class="dp-muted">Skat. arī pilno privātuma politiku un lietošanas noteikumus — saite "Mainīt sīkdatņu iestatījumus" lapas kājenē.</p>
    </div>
</main>
<?php include __DIR__ . '/registrs/footer/footer.php'; ?>
</body>
</html>
