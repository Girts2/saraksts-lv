<?php
/**
 * 2. solis — Leaflet jaunākā laidiena lejupielāde mapē `leaflet/`. ("2 karte.py" ports)
 *
 * NAV OBLIGĀTS. Pašreizējā iespeja.php Leaflet ņem no CDN (unpkg.com), tāpēc šis
 * solis noder tikai tad, ja gribi to lokāli — tad iespeja.php jāizlabo arī
 * <script> un <link> rindas.
 *
 * TRĪS LIETAS, KAS ŠEIT ATŠĶIRAS NO PYTHON VERSIJAS. Visas ir par to, kas notiek,
 * kad kaut kas neizdodas:
 *
 *  1. Python vispirms IZDZĒSA esošo `leaflet/` un tikai tad pārsauca jauno. Ja
 *     lejupielāde vai atarhivēšana pa vidu neizdevās, vecā mape jau bija prom un
 *     jaunās nebija — lapa palika bez kartes. Šeit viss izpakojas blakus, un maiņa
 *     notiek tikai tad, kad jaunais saturs ir gatavs.
 *  2. Saknes mapes nosaukumu Python ņēma no PIRMĀ ieraksta (`namelist()[0]`).
 *     Šodien tas ir "dist/", bet ja arhīvā faili kādreiz gulētu saknē, `os.rename`
 *     pārsauktu FAILU par "leaflet". Šeit saknes mapi nosaka pēc visiem ierakstiem.
 *  3. Ceļu pārbaude: arhīva ieraksts ar ".." vai absolūtu ceļu rakstītu ārpus
 *     mērķa mapes. ZipArchive::extractTo to nefiltrē.
 *
 * Un kļūme atgriež izejas kodu 1 — Python versija visu noķēra un beidza ar 0,
 * tāpēc cron un administratīvais panelis to nekad neredzēja.
 */
declare(strict_types=1);
require_once __DIR__ . '/common.php';

const GH_API = 'https://api.github.com/repos/Leaflet/Leaflet/releases/latest';
const ASSET  = 'leaflet.zip';
const TARGET = 'leaflet';

$t0  = ie_start('2. solis — Leaflet lejupielāde (nav obligāts)');
$dir = ie_out_dir();

// ── Jaunākā laidiena meklēšana ──────────────────────────────────────────────
ie_say('Meklējam informāciju par jaunāko versiju no: ' . GH_API);
$data = json_decode(ie_http_get(GH_API, 60), true);
if (!is_array($data)) ie_fail('GitHub API atbilde nav derīgs JSON');

$version = (string)($data['tag_name'] ?? 'nezināma versija');
$url = null;
foreach (($data['assets'] ?? []) as $a) {
    if (($a['name'] ?? '') === ASSET) { $url = (string)($a['browser_download_url'] ?? ''); break; }
}
if ($url === null || $url === '') ie_fail("jaunākajā laidienā nav atrasts '" . ASSET . "'");

ie_say("Atrasta jaunākā versija: $version");
ie_say("Lejupielādējam no: $url");

// ── Lejupielāde un pārbaude ─────────────────────────────────────────────────
$tmp = "$dir/.leaflet.tmp." . getmypid();
$zipPath = "$tmp.zip";
ie_http_download($url, $zipPath, 600);

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) { @unlink($zipPath); ie_fail("nevar atvērt arhīvu: $zipPath"); }
if ($zip->numFiles === 0) { $zip->close(); @unlink($zipPath); ie_fail('lejupielādētais ZIP arhīvs ir tukšs'); }

// Saknes mape = kopīgais pirmais segments VISIEM ierakstiem (vai nav vispār)
$root = null;
$rootCommon = true;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string)$zip->getNameIndex($i);

    if ($name === '' || $name[0] === '/' || $name[0] === '\\'
        || str_contains($name, '..') || str_contains($name, '\\')
        || preg_match('#^[A-Za-z]:#', $name)) {
        $zip->close(); @unlink($zipPath);
        ie_fail("arhīvā ir nedrošs ceļš: $name");
    }

    $seg = strpos($name, '/') === false ? null : substr($name, 0, strpos($name, '/'));
    if ($seg === null) { $rootCommon = false; continue; }
    if ($root === null) $root = $seg;
    elseif ($root !== $seg) $rootCommon = false;
}

ie_say('Lejupielāde veiksmīga. Sākam atarhivēšanu.');
ie_rmtree($tmp);
if (!@mkdir($tmp, 0775, true)) { $zip->close(); @unlink($zipPath); ie_fail("nevar izveidot mapi: $tmp"); }
if (!$zip->extractTo($tmp)) { $zip->close(); @unlink($zipPath); ie_rmtree($tmp); ie_fail('atarhivēšana neizdevās'); }
$zip->close();
@unlink($zipPath);

$source = ($rootCommon && $root !== null) ? "$tmp/$root" : $tmp;
if (!is_dir($source)) { ie_rmtree($tmp); ie_fail("atarhivētajā saturā nav mapes: $source"); }
ie_say($rootCommon && $root !== null
    ? "Fails atarhivēts. Arhīva saknes mape: '$root'."
    : 'Fails atarhivēts. Arhīvā nav saknes mapes — lietojam visu saturu.');

// ── Maiņa: vecā mape pazūd tikai tad, kad jaunā jau ir vietā ────────────────
$final = "$dir/" . TARGET;
$old   = "$final.old." . getmypid();

if (file_exists($final)) {
    ie_say("Mape '" . TARGET . "' jau eksistē. Tā tiks aizvietota.");
    if (!@rename($final, $old)) { ie_rmtree($tmp); ie_fail("nevar pārvietot veco mapi: $final"); }
}
if (!@rename($source, $final)) {
    if (file_exists($old)) @rename($old, $final);      // atgriežam veco vietā
    ie_rmtree($tmp);
    ie_fail("nevar pārvietot jauno mapi vietā: $final");
}

ie_rmtree($old);
ie_rmtree($tmp);

$n = iterator_count(new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($final, FilesystemIterator::SKIP_DOTS)));
ie_say("Mape '$final' atjaunota: Leaflet $version, $n faili.");

ie_done($t0);
