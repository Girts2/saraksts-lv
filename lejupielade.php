<?php
require_once __DIR__ . '/lib/applog.php';
applog_boot('lejupielade');   // kopīgais notikumu žurnāls (lib/applog.php)
// lejupielade.php — sadaļa "Lejupielāde".
//
// Lejupielādes uzskaite noņemta 2026-07-26 (agrāk: count.php + downloads_stats.sqlite).
// Saites tagad ved tieši uz failiem — nav pāradresācijas, nav skaitītāja, nav servera loģikas.

$archiveDir = __DIR__ . '/lejupielade';

/** Cilvēkam lasāms faila izmērs; null, ja faila nav. */
function dl_size(string $path): ?string
{
    if (!is_file($path)) return null;
    $bytes = (float) filesize($path);
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return sprintf($i === 0 ? '%d %s' : '%.1f %s', $bytes, $units[$i]);
}

/** Faila pēdējās maiņas datums; null, ja faila nav. */
function dl_date(string $path): ?string
{
    return is_file($path) ? date('Y-m-d', (int) filemtime($path)) : null;
}

// --- Jaunā pakotne: viss pirmkods vienā failā (būvē tools/build_download.php) ---
$mainFile = 'saraksts-lv-kods.zip';
$mainPath = $archiveDir . '/' . $mainFile;
$mainSize = dl_size($mainPath);
$mainDate = dl_date($mainPath);

// --- Vecās, atsevišķi pakotās moduļu versijas ---
$legacyFiles = [
    ['file' => 'registrs_v2.xlsm', 'name' => 'Reģistrs — Excel v2',   'note' => 'Windows + Office 2019/365. Apstrādā privātpersonu datus.'],
    ['file' => 'registrs.xlsm',    'name' => 'Reģistrs — Excel v1',   'note' => 'Vecākā Excel versija; atbalsta tikai Office 2019.'],
    ['file' => 'registrs.zip',     'name' => 'Reģistrs',              'note' => 'Python skripti + lapu ģenerators.'],
    ['file' => 'nozare.zip',       'name' => 'Nozare',                'note' => 'Nozaru analītika pēc NACE kodiem.'],
    ['file' => 'struktura.zip',    'name' => 'Struktūra',             'note' => 'D3.js īpašnieku saišu grafs — kopš tam aizstāts ar treemap karti.'],
    ['file' => 'iespeja.zip',      'name' => 'Iespēja',               'note' => 'Ģeotelpiskā biznesa potenciāla karte.'],
    ['file' => 'pensionars.zip',   'name' => 'Pensionārs',            'note' => 'Ilgtermiņa uzņēmumu portfelis.'],
    ['file' => 'horoskops.zip',    'name' => 'Horoskops',             'note' => 'Statiska JS astroloģijas lietotne.'],
];

$pageTitle = 'Pirmkoda lejupielāde — Saraksts.lv';
$pageDesc  = 'Viss Saraksts.lv pirmkods vienā failā, MIT licencē. Brīvi lietojams, pārveidojams un komerciāli izmantojams.';
?>
<?php ob_start(); ?>
    <style>
        /* Font Awesome nāk no head/head.php (6.x) — te to NEDUBLĒJAM.
           Agrāk šī lapa papildus ielādēja FA 5.15.4, kas kaskādē pārrakstīja
           .fas fontu visai lapai un padarīja jebkuru FA6 ikonu par laika bumbu. */

        .main-content-wrapper { padding-bottom: 80px; }

        /* --- Ievads / dāvinājuma paziņojums --- */
        .gift-box {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: var(--border-radius);
            padding: 30px;
            margin: 0 auto 40px;
            max-width: 900px;
            color: var(--color-text);
            box-shadow: var(--box-shadow-light);
        }
        .gift-box h2 {
            margin-top: 0;
            color: #166534;
            font-size: 1.35rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .gift-box p { margin: 0 0 12px; font-size: 1.05rem; line-height: 1.7; }
        .gift-box p:last-child { margin-bottom: 0; }

        /* --- Galvenā lejupielādes kartīte --- */
        .primary-card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-top: 4px solid var(--color-primary);
            border-radius: var(--border-radius);
            padding: 35px;
            margin: 0 auto 50px;
            max-width: 900px;
            box-shadow: var(--box-shadow-medium);
            box-sizing: border-box;
        }
        .primary-card h2 {
            margin-top: 0;
            font-size: 1.6rem;
            color: var(--color-heading);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .primary-card > p { line-height: 1.7; font-size: 1rem; }

        .file-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 22px;
            margin: 22px 0;
            padding: 14px 18px;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--color-text-secondary);
        }
        .file-meta strong { color: var(--color-text); }
        .file-meta i { margin-right: 6px; color: #6b7280; }

        .download-btn {
            background-color: var(--color-primary);
            color: white;
            text-decoration: none;
            padding: 16px 24px;
            border-radius: var(--border-radius);
            text-align: center;
            font-weight: 600;
            font-size: 1.05rem;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
            display: block;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .download-btn:hover {
            background-color: var(--color-primary-hover);
            color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
        }
        .download-btn i { margin-right: 8px; }
        .download-missing {
            display: block;
            padding: 16px 24px;
            border-radius: var(--border-radius);
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            text-align: center;
            font-weight: 600;
        }

        /* --- Divas kolonnas: kas iekšā / kas nav --- */
        .contents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .contents-grid h3 {
            font-size: 1.05rem;
            color: var(--color-heading);
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .contents-grid ul { margin: 0; padding-left: 20px; line-height: 1.75; font-size: 0.93rem; }
        .contents-grid li { margin-bottom: 4px; }
        .yes-icon { color: #16a34a; }
        .no-icon  { color: #9ca3af; }

        /* --- Juridiskā sadaļa --- */
        .legal-section {
            max-width: 900px;
            margin: 0 auto 50px;
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            padding: 30px 35px;
            box-shadow: var(--box-shadow-light);
            box-sizing: border-box;
        }
        .legal-section h2 {
            margin-top: 0;
            font-size: 1.35rem;
            color: var(--color-heading);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .legal-section h3 {
            font-size: 1.02rem;
            margin: 26px 0 8px;
            color: var(--color-heading);
        }
        .legal-section p, .legal-section li { line-height: 1.7; font-size: 0.96rem; }
        .legal-section ul { padding-left: 22px; }

        .callout {
            border-left: 4px solid #d97706;
            background: #fffbeb;
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            margin: 18px 0;
        }
        .callout.gdpr { border-left-color: #b91c1c; background: #fef2f2; }
        /* Tikai TIEŠAIS bērns ir virsraksts. Ja te būtu vienkārši ".callout strong",
           arī teikuma vidus izcēlumi kļūtu par blokiem un lauztu tekstu. */
        .callout > strong { display: block; margin-bottom: 6px; }
        .callout p { margin: 0; font-size: 0.94rem; }

        /* --- Vecā versija (savērsta) --- */
        .legacy-wrap { max-width: 900px; margin: 0 auto; }
        .legacy-details {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-light);
            overflow: hidden;
        }
        .legacy-details > summary {
            cursor: pointer;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--color-heading);
            list-style: none;
            display: flex;
            align-items: center;
            gap: 12px;
            user-select: none;
        }
        .legacy-details > summary::-webkit-details-marker { display: none; }
        .legacy-details > summary::after {
            content: '\f078'; /* chevron-down */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: auto;
            font-size: 0.85rem;
            color: var(--color-text-secondary);
            transition: transform 0.2s ease;
        }
        .legacy-details[open] > summary::after { transform: rotate(180deg); }
        .legacy-details > summary:hover { background-color: #f9fafb; }
        .legacy-badge {
            background-color: #6b7280;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 20px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .legacy-body { padding: 5px 25px 25px; border-top: 1px solid var(--color-border); }
        .legacy-intro {
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--color-text-secondary);
            margin: 18px 0 22px;
        }
        .legacy-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        .legacy-table th {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid var(--color-border);
            color: var(--color-text-secondary);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .legacy-table td { padding: 12px; border-bottom: 1px solid var(--color-border); vertical-align: top; }
        .legacy-table tr:last-child td { border-bottom: none; }
        .legacy-name { font-weight: 600; color: var(--color-heading); display: block; }
        .legacy-note { color: var(--color-text-secondary); font-size: 0.86rem; line-height: 1.5; }
        .legacy-size { white-space: nowrap; color: var(--color-text-secondary); }
        .legacy-link {
            display: inline-block;
            background-color: #6b7280;
            color: white;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
            transition: background-color 0.2s ease;
        }
        .legacy-link:hover { background-color: #4b5563; color: white; }
        .legacy-gone { color: var(--color-text-secondary); font-size: 0.85rem; font-style: italic; }

        .table-scroll { overflow-x: auto; }

        /* Redzams tikai ekrānlasītājiem (main.css šādas klases nav). */
        .sr-only {
            position: absolute;
            width: 1px; height: 1px;
            padding: 0; margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 640px) {
            .primary-card, .legal-section { padding: 22px; }
            .legacy-body { padding: 5px 15px 20px; }
        }
    </style>
<?php $extraHeadContent = ob_get_clean(); ?>
<!DOCTYPE html>
<html lang="lv">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/head/head.php'; ?>
<body>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/header.php'; ?>

    <main class="container main-content-wrapper">
        <h1 class="main-page-title">Sistēmas pirmkoda lejupielāde</h1>

        <div class="gift-box">
            <h2><i class="fas fa-gift" aria-hidden="true"></i> Šis kods ir dāvana</h2>
            <p>
                Es šo kodu dāvinu — tiem, kas grib to <strong>attīstīt tālāk</strong>, <strong>pārveidot</strong>
                vai <strong>izmantot savām darba vajadzībām</strong>. Arī komerciāli. Nekāda maksa,
                nekāda reģistrēšanās, nekādi jautājumi.
            </p>
            <p>
                Juridiski tas ir noformēts kā <strong>MIT licence</strong> — īsākā un pieļaujošākā no
                plaši atzītajām atvērtā koda licencēm. Tā tev ļauj darīt praktiski visu, prasot pretī
                tikai divas lietas: saglabāt autortiesību paziņojumu un neuzskatīt, ka man ir kāda
                atbildība par sekām. Pilns teksts ir failā <code>LICENSE</code> pakotnes iekšpusē.
            </p>
            <p>
                Kodu praktiski pilnībā ir uzrakstījis mākslīgais intelekts (Claude un Gemini),
                attēlus — Midjourney. Kods apstrādā lielu daudzumu datu, un visas iespējamās rezultātu
                variācijas ir grūti prognozēt: <strong>kodā var būt loģikas kļūdas, kas ģenerēs
                nepareizu rezultātu</strong>. Pārbaudi rezultātus, pirms uz tiem balsties.
            </p>
        </div>

        <div class="primary-card">
            <h2>
                <i class="fas fa-box-open" aria-hidden="true" style="color: var(--color-logo);"></i>
                Viss pirmkods vienā failā
            </h2>
            <p>
                Viena PHP koda bāze, kas apkalpo visas astoņas vietnes sadaļas — Reģistrs, Nozare,
                Struktūra, Konkursi, Iespēja, Pensionārs, Horoskops un šo lapu. Ietver datu būves
                skriptus, administratora paneļus, sinhronizāciju ar ~25 iepirkumu avotiem un visu
                priekšpuses kodu.
            </p>

            <?php if ($mainSize !== null): ?>
            <div class="file-meta">
                <span><i class="fas fa-file-zipper" aria-hidden="true"></i>ZIP arhīvs</span>
                <span><i class="fas fa-weight-hanging" aria-hidden="true"></i><strong><?= htmlspecialchars($mainSize, ENT_QUOTES, 'UTF-8') ?></strong></span>
                <span><i class="fas fa-calendar-day" aria-hidden="true"></i>Atjaunots <strong><?= htmlspecialchars((string) $mainDate, ENT_QUOTES, 'UTF-8') ?></strong></span>
                <span><i class="fas fa-scale-balanced" aria-hidden="true"></i>MIT licence</span>
            </div>

            <a href="lejupielade/<?= rawurlencode($mainFile) ?>" class="download-btn" download>
                <i class="fas fa-download" aria-hidden="true"></i> Lejupielādēt pirmkodu (<?= htmlspecialchars($mainSize, ENT_QUOTES, 'UTF-8') ?>)
            </a>
            <?php else: ?>
            <p class="download-missing">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                Pakotne šobrīd nav pieejama — tā tiek pārbūvēta. Ieskaties nedaudz vēlāk.
            </p>
            <?php endif; ?>

            <div class="contents-grid">
                <div>
                    <h3><i class="fas fa-circle-check yes-icon" aria-hidden="true"></i> Kas ir iekšā</h3>
                    <ul>
                        <li>Viss PHP, JavaScript un CSS kods</li>
                        <li>Datu lejupielādes un būves skripti</li>
                        <li>Iespējas Python konveijers (9 soļi) ar pamācību</li>
                        <li>Administratora paneļi</li>
                        <li>Nozaru attēli (1049 gab.)</li>
                        <li>O*NET profesiju dati</li>
                        <li>VID ceturkšņu arhīvs, kas vairs nav pieejams data.gov.lv</li>
                        <li><code>LICENSE</code>, <code>NOTICE.md</code> un uzstādīšanas pamācība</li>
                    </ul>
                </div>
                <div>
                    <h3><i class="fas fa-circle-minus no-icon" aria-hidden="true"></i> Kas nav iekšā</h3>
                    <ul>
                        <li><strong>Atvērtie dati no data.gov.lv</strong> — kods tos lejupielādē pats</li>
                        <li><strong>Datubāzes</strong> (<code>.sqlite</code>, <code>.db</code>) — tās ģenerē būve</li>
                        <li>Būves izvade: sitemap, treemap JSON</li>
                        <li>MI atbilžu kešs</li>
                        <li><strong>Manas API atslēgas, paroles un tokeni</strong> — noņemti</li>
                    </ul>
                </div>
            </div>
        </div>

        <section class="legal-section">
            <h2><i class="fas fa-scale-balanced" aria-hidden="true"></i> Ko tu drīksti un ko der zināt</h2>

            <p>
                Mans kods ir <strong>MIT licencē</strong>: drīksti to lietot, kopēt, pārveidot,
                apvienot, publicēt, izplatīt, apakšlicencēt un pārdot. Vienīgais nosacījums —
                saglabā autortiesību un licences paziņojumu kopijās. Garantiju nav; visu risku
                par lietošanu uzņemies tu.
            </p>

            <h3>Trīs izņēmumi, ko nevaru uzdāvināt</h3>
            <p>
                Pakotnē ir arī citu autoru darbs. Es nevaru piešķirt tiesības, kas man nepieder,
                tāpēc uz šīm daļām MIT licence <strong>neattiecas</strong>:
            </p>

            <div class="callout">
                <strong><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Horoskops izmanto Swiss Ephemeris (AGPL-3.0)</strong>
                <p>
                    Mapē <code>horoskops/swisseph/</code> ir Astrodienst AG bibliotēka. Tā ir
                    <strong>duāli licencēta</strong>: vai nu GNU Affero GPL v3, vai maksas Professional
                    licence. Astrodienst nosacījums ir skaidrs — izvēle jāizdara <em>pirms</em> koda
                    izplatīšanas <em>un pirms tiek aktivizēts jebkurš publisks serviss</em>, kas to lieto.
                    Tā kā Horoskopa sadaļa izsauc šo bibliotēku,
                    <strong>uz to attiecas AGPL, nevis MIT</strong>.
                </p>
                <p style="margin-top: 10px;">
                    Praksē tas <em>nenozīmē</em>, ka jāmaksā. AGPL prasa piedāvāt pilnu pirmkodu tiem,
                    kas servisu lieto — un tieši to šī lapa dara. Ja arī tu publicē Horoskopu un līdzi
                    piedāvā tā pirmkodu AGPL licencē, ar to pietiek. Maksas licence (Astrodienst cena —
                    CHF 750 pirmajam projektam) vajadzīga tikai tad, ja pirmkodu <strong>negribi</strong>
                    atklāt. Trešā iespēja: izdzēs mapi <code>horoskops/</code> — pārējais projekts no tās
                    nav atkarīgs un paliek MIT.
                </p>
                <p style="margin-top: 10px;">
                    <strong>Rezultāti nav ierobežoti.</strong> Licence attiecas uz programmatūru, nevis
                    uz to, ko tā aprēķina. Horoskopus, kartes un planētu pozīcijas, ko lietotne
                    uzģenerē, drīksti publicēt un izmantot brīvi, arī komerciāli.
                </p>
            </div>

            <div class="callout">
                <strong><i class="fas fa-book" aria-hidden="true"></i> O*NET dati prasa atsauci (CC BY 4.0)</strong>
                <p>
                    <code>horoskops/onet/</code> satur ASV Darba departamenta O*NET® datubāzi. To drīkst
                    izmantot arī komerciāli, bet <strong>jānorāda avots</strong>. O*NET ir ASV DOL
                    reģistrēta preču zīme.
                </p>
            </div>

            <div class="callout">
                <strong><i class="fas fa-map-location-dot" aria-hidden="true"></i> Iespējas dati nāk no OpenStreetMap (ODbL 1.0)</strong>
                <p>
                    Fails <code>iespeja/Turisma objekti.txt</code> ir OpenStreetMap izgūtne, un konveijers
                    lejupielādē vēl vairāk OSM datu. Publicējot rezultātus, jānorāda
                    <strong>© OpenStreetMap contributors</strong>. Ja izplati tālāk pašu datubāzi, ko
                    konveijers uzbūvē, ODbL prasa to darīt ar tādiem pašiem noteikumiem — aprēķinātus
                    rezultātus, piemēram, kartes un aplēses, drīksti publicēt vienkārši ar atsauci.
                </p>
            </div>

            <p>
                Pilnu trešo pušu sastāvdaļu sarakstu — D3.js, Chart.js, Moment.js, marked, CookieConsent
                un pārējo — ar to licencēm atradīsi failā <code>NOTICE.md</code> pakotnes iekšpusē.
                <strong>Izlasi to, pirms izplati kodu tālāk.</strong>
            </p>

            <h3>Par personas datiem — attiecas uz visiem variantiem</h3>

            <div class="callout gdpr">
                <strong><i class="fas fa-user-shield" aria-hidden="true"></i> Palaižot šo kodu, tu kļūsti par datu pārzini</strong>
                <p>
                    Vairāki skripti lejupielādē no data.gov.lv <strong>fizisku personu datus</strong> —
                    amatpersonas, patiesā labuma guvējus un dalībniekus: vārdus, uzvārdus un maskētus
                    personas kodus. Tas attiecas gan uz Reģistra, Struktūras un Pensionāra būves
                    skriptiem, gan uz abiem Excel failiem zemāk. Tiklīdz šie dati nonāk uz tava datora
                    vai servera, <strong>tu esi datu pārzinis VDAR izpratnē</strong>: tev pašam jānodrošina
                    apstrādes tiesiskais pamats, glabāšanas termiņi un datu subjektu tiesības. Pārliecinies,
                    ka tev ir pamatots mērķis — piemēram, AML/KYC pārbaude. Šī pakotne tādu pamatu nedod.
                </p>
            </div>

            <h3>Pirms publicē savu versiju</h3>
            <p>
                Manas atslēgas ir izņemtas, bet dažas lietas tev jāaizpilda vai jāpārraksta pašam:
            </p>
            <ul>
                <li><code>admin_token.php</code> — administratora paneļu atslēga. <strong>Obligāti nomaini.</strong></li>
                <li><code>registrs/mi/key.php</code> — sava Gemini API atslēga, ja gribi MI funkcijas.</li>
                <li><code>iespeja.php</code> un mapes <code>iespeja/</code> skripti — savas MySQL pieejas
                    telpiskajai datubāzei. Šī sadaļa <strong>prasa atsevišķu soli</strong>: tās datus
                    neveido kopējā būve, bet Python konveijers — skat. <code>iespeja/README.md</code>.</li>
                <li>Google Analytics ID (vietturis <code>G-XXXXXXXXXX</code>) un e-pasta adreses.</li>
                <li><code>registrs/cookie/cookieconsent-init.js</code> — sīkdatņu un VDAR teksti ir
                    rakstīti konkrēti saraksts.lv. Tie ir juridiski saistoši tavai vietnei —
                    <strong>pārraksti tos</strong>.</li>
            </ul>
        </section>

        <div class="legacy-wrap">
            <details class="legacy-details">
                <summary>
                    <i class="fas fa-box-archive" aria-hidden="true"></i>
                    Vecā versija
                    <span class="legacy-badge">Arhīvs</span>
                </summary>
                <div class="legacy-body">
                    <p class="legacy-intro">
                        Šīs ir <strong>vecākas, atsevišķi pakotas moduļu versijas</strong>, kas šeit bija
                        pieejamas agrāk. Tās atbilst dažādiem izstrādes posmiem un <strong>vairs netiek
                        uzturētas</strong> — daļa no tām atšķiras no tā, kas šobrīd darbojas vietnē.
                        Piemēram, Struktūras arhīvā vēl ir vecais D3.js saišu grafs, ko kopš tam
                        aizstāja treemap karte. Jaunam darbam ņem pakotni augšā; šīs atstātas tikai
                        vēstures un salīdzināšanas dēļ.
                    </p>
                    <div class="table-scroll">
                        <table class="legacy-table">
                            <thead>
                                <tr>
                                    <th scope="col">Modulis</th>
                                    <th scope="col">Izmērs</th>
                                    <th scope="col">Datums</th>
                                    <th scope="col"><span class="sr-only">Lejupielāde</span></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($legacyFiles as $item):
                                $path = $archiveDir . '/' . $item['file'];
                                $size = dl_size($path);
                            ?>
                                <tr>
                                    <td>
                                        <span class="legacy-name"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="legacy-note"><?= htmlspecialchars($item['note'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="legacy-size"><?= $size !== null ? htmlspecialchars($size, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                    <td class="legacy-size"><?= htmlspecialchars((string) (dl_date($path) ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td style="text-align: right;">
                                        <?php if ($size !== null): ?>
                                            <a href="lejupielade/<?= rawurlencode($item['file']) ?>" class="legacy-link" download>
                                                <i class="fas fa-download" aria-hidden="true"></i>
                                                Lejupielādēt
                                            </a>
                                        <?php else: ?>
                                            <span class="legacy-gone">nav pieejams</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        </div>
    </main>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/footer/footer.php'; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/cookie/cookie.php'; ?>

</body>
</html>
