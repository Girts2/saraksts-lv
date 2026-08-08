<?php
/**
 * 4. solis — ēku ģeometrija (INSPIRE GML) + koordinātes pie kopsavilkuma.
 * ("4 ALL.py" ports)
 *
 * Ievade:  out-summ-level.csv (3. solis) + Building.gml (kadastrs.lv Atom)
 *          + "būves dabā neeksistē" saraksts (data.gov.lv)
 * Izvade:  out-all.csv (eka, point, cilveki, level)
 *          atskaite-non-building.txt, atskaite-all.txt
 *
 * PUNKTA SECĪBA. Šeit tiek rakstīts POINT(lat lon) — NEVIS parastais POINT(lon lat).
 * Tā ir 5. soļa gaidītā ievade, un tas koordinātas samaina pirms MySQL. Nemainīt
 * bez 5. soļa labošanas.
 *
 * ATMIŅA. Python vispirms izparsēja VISU 1,38 M ēku ģeometriju DataFrame un tikai
 * tad apvienoja. Šeit vispirms nolasa vajadzīgo ēku sarakstu un GML straumē ar
 * filtru, tāpēc atmiņā nonāk tikai tās ēkas, kas tiešām vajadzīgas. Rezultāts ir
 * tas pats — kreisā apvienošana nesakritušās ģeometrijas tāpat izmet.
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

const ATOM_URL = 'https://grafws.kadastrs.lv/atom/bu/atom_Building.xml';
const EXCL_URL = 'https://data.gov.lv/dati/dataset/09f883c4-8474-45d9-9b50-7d1b0ae5723a/'
               . 'resource/9461cdf9-cac9-458e-86f1-7c1f7721eb67/download/'
               . 'buves_daba_neeksiste_2025-08-04.csv';

$t0  = ie_start('4. solis — ēku ģeometrija un gala kopsavilkums');
$dir = ie_out_dir();
$gmlPath  = "$dir/Building.gml";
$exclPath = "$dir/buves_daba_neeksiste_lejupieladets.csv";
$outPath  = "$dir/out-all.csv";
$repDel   = "$dir/atskaite-non-building.txt";
$repGps   = "$dir/atskaite-all.txt";

// ── 0. DAĻA: sākotnējo datu sagatavošana ────────────────────────────────────
ie_say('--- 0. DAĻA: Sākotnējo datu sagatavošana ---');

if (is_file($gmlPath)) {
    ie_say(sprintf('Fails Building.gml jau eksistē (%.1f GB) — lejupielāde izlaista.',
        filesize($gmlPath) / 1073741824));
} else {
    ie_say('Meklēju aktuālo lejupielādes saiti: ' . ATOM_URL);
    $atom = ie_http_get(ATOM_URL, 60);
    $href = null;
    $xml = @simplexml_load_string($atom);
    if ($xml !== false) {
        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $hits = $xml->xpath('//atom:entry/atom:link[@rel="alternate"]') ?: [];
        if ($hits && isset($hits[0]['href'])) $href = (string)$hits[0]['href'];
    }
    if ($href === null) ie_fail('XML barotnē netika atrasta saite entry/link[@rel="alternate"]');
    ie_say("Atrasta aktuālā saite: $href");

    $zipPath = "$dir/Building.gml.zip";
    ie_say('Lejupielādēju arhīvu…');
    ie_http_download($href, $zipPath, 3600);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) ie_fail("nevar atvērt arhīvu: $zipPath");
    if ($zip->locateName('Building.gml') === false) {
        $zip->close();
        ie_fail("arhīvā netika atrasts 'Building.gml'");
    }
    if (!$zip->extractTo($dir, 'Building.gml')) { $zip->close(); ie_fail('atarhivēšana neizdevās'); }
    $zip->close();
    @unlink($zipPath);
    ie_say('Building.gml veiksmīgi lejupielādēts un atarhivēts.');
}

ie_say('Lejupielādējam izslēdzamo ēku sarakstu…');
ie_http_download(EXCL_URL, $exclPath, 600);
ie_say("Saraksts saglabāts: $exclPath");

// ── 2. DAĻA: nevajadzīgo ēku filtrēšana ─────────────────────────────────────
// (Python numerācijā 2. daļa; te tā jāizpilda pirms GML, lai zinātu, ko meklēt.)
ie_say('');
ie_say('--- 2. DAĻA: Nevajadzīgo ēku filtrēšana ---');

$excl = [];
foreach (ie_csv_rows($exclPath) as $row) {
    $v = $row['BuiCadNr'] ?? null;
    if ($v === null || $v === '') continue;
    $excl[(string)$v] = true;
}
ie_say('Nolasīti ' . count($excl) . ' unikāli izslēdzamie kadastra numuri.');

$summ = ie_find('out-summ-level.csv');
// {$summ} obligāti: "$summ…" PHP nolasa kā VIENU mainīgo (identifikatoros ir atļauts Unicode)
ie_say("Nolasām galveno datu failu: {$summ}…");

$kept = [];      // secībā: [kadastrs, cilveki, level]
$removed = [];
foreach (ie_csv_rows($summ) as $row) {
    $k = (string)($row['kadastrs'] ?? '');
    $rec = [$k, (string)($row['kopejie_cilveki'] ?? ''), (string)($row['level'] ?? '')];
    if (isset($excl[$k])) $removed[] = $rec; else $kept[] = $rec;
}
ie_say('Nolasītas ' . (count($kept) + count($removed)) . ' rindas.');
ie_say('Atrastas ' . count($removed) . ' rindas izdzēšanai.');
ie_say(count($kept) . ' rindas tiek nodotas tālākai apstrādei.');

$fh = fopen($repDel, 'w');
fwrite($fh, "Atskaites par rindām, kas izdzēstas no 'out-summ-level.csv'\n" . str_repeat('=', 80) . "\n\n");
fwrite($fh, 'Kopējais izdzēsto rindu skaits: ' . count($removed) . "\n\n");
if ($removed) {
    fwrite($fh, "kadastrs,kopejie_cilveki,level\n");
    foreach ($removed as $r) ie_fputcsv_py($fh, $r, "\n");
}
fclose($fh);
ie_say("Atskaite par izdzēstajām ēkām saglabāta: $repDel");

// ── 1. DAĻA: GPS centroīdi no GML ───────────────────────────────────────────
ie_say('');
ie_say('--- 1. DAĻA: GPS datu ģenerēšana no GML faila ---');

$wanted = [];
foreach ($kept as $r) $wanted[$r[0]] = true;

$gps = [];       // kadastrs → "POINT(lat lon)"; pirmais gadījums uzvar
$stat = ie_gml_centroids($gmlPath,
    static fn(string $bid): bool => isset($wanted[$bid]),
    function (string $bid, float $lon, float $lat) use (&$gps): void {
        // drop_duplicates(keep='first') — vēlākos dublikātus ignorējam
        if (!isset($gps[$bid])) $gps[$bid] = sprintf('POINT(%.8f %.8f)', $lat, $lon);
    });
ie_say(sprintf('Veiksmīgi apstrādātas un atmiņā sagatavotas %d ēkas ar GPS centroīdiem.', count($gps)));
if ($stat['failed'] > 0) ie_say(sprintf('Ēkas ar kļūdām vai izlaistas GML apstrādē: %d', $stat['failed']));
if (!$gps) ie_fail('No GML faila neizdevās nolasīt nevienu derīgu ēkas GPS punktu.');

// ── 3. DAĻA: apvienošana un gala fails ──────────────────────────────────────
ie_say('');
ie_say('--- 3. DAĻA: Datu apvienošana un gala faila veidošana ---');
ie_say('Meklējam un dzēšam rindas bez GPS punkta…');

$fh = fopen($outPath, 'w');
if ($fh === false) ie_fail("nevar rakstīt: $outPath");
ie_fputcsv_py($fh, ['eka', 'point', 'cilveki', 'level'], "\n");   // pandas to_csv = LF

$missing = [];
$final = 0;
foreach ($kept as [$k, $cilveki, $level]) {
    if (!isset($gps[$k])) { $missing[] = $k; continue; }
    ie_fputcsv_py($fh, [$k, $gps[$k], $cilveki, $level], "\n");
    $final++;
}
fclose($fh);

ie_say('Atrastas un izdzēstas ' . count($missing) . ' rindas bez atbilstoša GPS punkta.');
ie_say("Gala failā tiks saglabātas $final rindas.");
ie_say("Gala rezultāts veiksmīgi saglabāts failā: $outPath");

$fh = fopen($repGps, 'w');
fwrite($fh, "Atskaites par rindām, kurām netika atrasts GPS punkts\n" . str_repeat('=', 80) . "\n\n");
fwrite($fh, 'Kopējais rindu skaits bez GPS punkta: ' . count($missing) . "\n\n");
foreach ($missing as $k) fwrite($fh, "$k\n");
fclose($fh);
ie_say("Atskaite par rindām bez GPS saglabāta: $repGps");

ie_done($t0);
if ($final === 0) ie_fail('out-all.csv palika tukšs');
