<?php
/**
 * Iespēja/php/schema.php — TABULU NOSAUKUMI UN ŠĶĒLUMI. Vienīgā vieta.
 *
 * Šo failu iekļauj GAN konveijers (common.php), GAN frontends (iespeja.php).
 * Tas ir apzināti: kamēr tabulu nosaukumi bija ierakstīti abās pusēs atsevišķi,
 * jebkura shēmas maiņa nozīmēja atcerēties četras vietas (konveijers, frontenda
 * meklēšana, frontenda siltumkarte, dublēšanas rīks). Tagad tā ir viena.
 *
 * NOSLĒPUMU ŠEIT NAV. DB parole dzīvo config.php; šis fails ir tīra shēma, tāpēc
 * to drīkst iekļaut arī publiskā lapa.
 *
 * ── NOSAUKUMU FORMĀTS ──────────────────────────────────────────────────────
 *
 *   <valsts kods>_<angliskais nosaukums>[_<reģions>]
 *
 *   lv_buildings      lv_offices      lv_institutions
 *   lv_tourism        lv_poi
 *
 * Valsts kods priekšā nozīmē, ka VISAS ES valstis var dzīvot vienā MySQL
 * datubāzē (koplietotā hostingā DB skaits mēdz būt limitēts) VAI katra savā —
 * kods abos gadījumos ir tas pats. Reģiona sufikss parādās tikai tad, kad valstij
 * ir vairāk par vienu reģionu; Latvijai to nav, tāpēc nosaukumi paliek tīri.
 *
 * ── KO MAINA, PĀRCEĻOT UZ CITU VALSTI ──────────────────────────────────────
 *
 *   1. IE_COUNTRY zemāk (vai vides mainīgais IESPEJA_COUNTRY)
 *   2. countries/<kods>.php — reģioni, kalibrācija, POI tipi
 *   3. datu ielādes soļi (avotu formāti atšķiras pa valstīm)
 *
 * Shēma, vaicājumi un frontends nemainās NEKAD. Tas ir šī faila mērķis.
 */
declare(strict_types=1);

/** Valsts kods. Vides mainīgais uzvar, lai viens kods varētu apkalpot vairākas. */
if (!defined('IE_COUNTRY')) {
    $ieEnvCountry = getenv('IESPEJA_COUNTRY');
    define('IE_COUNTRY', ($ieEnvCountry !== false && $ieEnvCountry !== '')
        ? strtolower($ieEnvCountry) : 'lv');
}

/** Loģiskie slāņi. Sufikss tabulas nosaukumā = šī masīva vērtība. */
const IE_LAYERS = ['buildings', 'offices', 'institutions', 'tourism', 'poi'];

/** Slāņi, kas NEKAD netiek sadalīti pa reģioniem (skat. ie_shards_for_bbox). */
const IE_UNSHARDED = ['offices', 'institutions', 'tourism', 'poi'];

/**
 * Valsts profils no countries/<kods>.php.
 *
 * @return array{code:string,name:string,iso:string,regions:array,office:array,
 *               institutions:array,tourism:array,poi:array}
 */
function ie_country(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $path = __DIR__ . '/countries/' . IE_COUNTRY . '.php';
    if (!is_file($path)) {
        throw new RuntimeException("nav valsts profila: $path");
    }
    $cfg = require $path;
    if (!is_array($cfg) || ($cfg['code'] ?? '') === '') {
        throw new RuntimeException("nederīgs valsts profils: $path");
    }
    if (!$cfg['regions']) {
        throw new RuntimeException("valsts profilā nav neviena reģiona: $path");
    }
    return $cache = $cfg;
}

/**
 * Tabulas pilnais nosaukums.
 *
 * ie_table('buildings')           → lv_buildings
 * ie_table('poi')                 → lv_poi
 * ie_table('buildings', 'bayern') → de_buildings_bayern   (ja reģionu ir vairāki)
 *
 * Reģiona sufikss pazūd, ja valstij ir tikai viens reģions — citādi Latvijai
 * sanāktu "lv_buildings_lv". Izsaucējam par to nav jāzina: tas vienmēr padod
 * reģionu, un šī funkcija izlemj.
 */
function ie_table(string $layer, ?string $region = null): string
{
    if (!in_array($layer, IE_LAYERS, true)) {
        throw new InvalidArgumentException("nezināms slānis: $layer");
    }
    $c = ie_country();
    $base = $c['code'] . '_' . $layer;

    if ($region === null || in_array($layer, IE_UNSHARDED, true)) return $base;
    if (count($c['regions']) < 2) return $base;
    return $base . '_' . $region;
}

/** Visi reģioni: [['code'=>…, 'name'=>…, 'bbox'=>[minLon,minLat,maxLon,maxLat]], …] */
function ie_regions(): array
{
    return ie_country()['regions'];
}

/** Visas ēku slāņa tabulas (viena mazai valstij, N lielai). @return string[] */
function ie_building_shards(): array
{
    $out = [];
    foreach (ie_regions() as $r) $out[] = ie_table('buildings', $r['code']);
    return array_values(array_unique($out));
}

/**
 * Ēku tabulas, kas skar doto taisnstūri.
 *
 * ATGRIEŽ VISAS PĀRKLĀJOŠĀS, ne tuvāko. 5 km rādiuss pie reģiona robežas skar
 * divus vai trīs šķēlumus, un tad rezultāti jāsavieno — citādi puse māju pazūd,
 * un tas izpaužas kā "pierobežā dati ir dīvaini", nevis kā kļūda.
 *
 * Mazai valstij te vienmēr ir viens elements, tāpēc izsaucēja kods lielām un
 * mazām valstīm ir viens un tas pats.
 *
 * @return string[]
 */
function ie_shards_for_bbox(float $minLon, float $minLat, float $maxLon, float $maxLat): array
{
    $out = [];
    foreach (ie_regions() as $r) {
        [$rMinLon, $rMinLat, $rMaxLon, $rMaxLat] = $r['bbox'];
        if ($maxLon < $rMinLon || $minLon > $rMaxLon) continue;   // nepārklājas pa X
        if ($maxLat < $rMinLat || $minLat > $rMaxLat) continue;   // nepārklājas pa Y
        $out[] = ie_table('buildings', $r['code']);
    }
    // Ja klikšķis ir ārpus visiem reģioniem (jūrā, aiz robežas), atgriežam pirmo,
    // lai vaicājums nostrādā un atbild ar tukšu rezultātu, nevis mestu kļūdu.
    if (!$out) $out[] = ie_table('buildings', ie_regions()[0]['code']);
    return array_values(array_unique($out));
}

/**
 * Kuram reģionam pieder punkts — ar to ielādes soļi izšķir, kurā šķēlumā rakstīt.
 *
 * Pateicoties šim, 5. soļa kods lielai un mazai valstij ir viens un tas pats:
 * vienam reģionam tas vienmēr atgriež to pašu, sešpadsmit reģioniem — pareizo.
 * Ielādes solim nekad nav jāzina, vai valsts ir sadalīta.
 */
function ie_region_for_point(float $lon, float $lat): string
{
    $regions = ie_regions();
    if (count($regions) < 2) return $regions[0]['code'];

    foreach ($regions as $r) {
        [$minLon, $minLat, $maxLon, $maxLat] = $r['bbox'];
        if ($lon >= $minLon && $lon <= $maxLon && $lat >= $minLat && $lat <= $maxLat) {
            return $r['code'];
        }
    }
    // Ārpus visiem taisnstūriem (mērījuma kļūda, sala, piekraste) — tuvākais pēc
    // centra. Rinda nedrīkst pazust tikai tāpēc, ka bbox karte nav ideāla.
    $best = $regions[0]['code'];
    $bestD = INF;
    foreach ($regions as $r) {
        [$minLon, $minLat, $maxLon, $maxLat] = $r['bbox'];
        $dx = $lon - ($minLon + $maxLon) / 2;
        $dy = $lat - ($minLat + $maxLat) / 2;
        $d  = $dx * $dx + $dy * $dy;
        if ($d < $bestD) { $bestD = $d; $best = $r['code']; }
    }
    return $best;
}

/** Visas valsts tabulas — dublēšanai un migrācijai. @return string[] */
function ie_all_tables(): array
{
    $out = ie_building_shards();
    foreach (IE_UNSHARDED as $layer) $out[] = ie_table($layer);
    return $out;
}
