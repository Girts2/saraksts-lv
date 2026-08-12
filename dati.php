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
$pageDesc = "Kā Saraksts.lv iegūst, aprēķina un atjauno datus no valsts atvērto datu avotiem.";

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
        <h1>Par datiem</h1>

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

        <h2>2. Labojumi</h2>
        <p>Ja vietnes aprēķinā vai attēlojumā ir neprecizitāte, izlabojam paši — rakstiet uz
        <span class="dp-mail">info@example.com</span>, norādot reģistrācijas numuru un konkrēto vietu.
        Avota datu (UR, VID) labojumi notiek pirmavotā; vietne izmaiņas pārņem automātiski
        nākamajā datu atjaunošanā.</p>

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

        <p class="dp-muted">Pilna privātuma politika un lietošanas noteikumi — saite
        "Mainīt sīkdatņu iestatījumus" lapas kājenē.</p>
    </div>
</main>
<?php include __DIR__ . '/registrs/footer/footer.php'; ?>
</body>
</html>
