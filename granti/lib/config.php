<?php
/**
 * granti/lib/config.php — grantu moduļa CEĻI un konstantes.
 *
 * Viena vieta, kur dzīvo visi ceļi, pēc konkursi/lib/config.php parauga. Nekādu
 * blakusefektu, izņemot laika zonu: PHP noklusējums ir UTC, un bez šī žurnālu
 * laiki būtu 3 h atpakaļ (tā pati kļūda, ko konkursi izlaboja 2026-07).
 *
 * IZKĀRTOJUMS (tas pats, kas konkursi/):
 *   granti/bin/   — CLI ieejas punkti (būve, tulkošana, cron)
 *   granti/lib/   — bibliotēkas, ko iekļauj gan lapa, gan būve
 *   granti/db/    — SQLite faili (izvietošana tos NESŪTA: rsync izslēdz *.sqlite,
 *                   serveris būvē pats — sk. [[ref-saraksts-izvietosana]])
 *   granti/data/  — slēdzenes, žurnāls, stāvokļa JSON, karodziņi, lejupielāžu kešs
 *
 * Publiskā piekļuve visām četrām mapēm ir liegta htaccess.txt rindā
 * "RedirectMatch 404 ^/granti/(lib|bin|data|db)/".
 */
declare(strict_types=1);

$__tz = __DIR__ . '/../../registrs/lib/timezone.php';
if (is_file($__tz)) { require_once $__tz; reg_init_timezone(); }

/** granti/ moduļa sakne. */
function granti_root(): string { return dirname(__DIR__); }
/** server/ (docroot). */
function granti_docroot(): string { return dirname(granti_root()); }

/**
 * Uzbūvētā datubāze. GRANTI_DB_PATH ļauj to pārvietot ārpus docroot; ja to lieto,
 * mainīgais JĀPIEVIENO admin_env_prefix() sarakstam, citādi fonā palaists darbs
 * (setsid/nohup vidi nemanto) klusi būvētu noklusējuma ceļā.
 */
function granti_db_path(): string {
    $env = getenv('GRANTI_DB_PATH');
    return ($env !== false && $env !== '') ? $env : granti_root() . '/db/granti.sqlite';
}
/**
 * Tulkojumu kešs — ATSEVIŠĶS fails no granti.sqlite. Būve raksta tukšā pagaidu
 * failā un pārceļ to pāri dzīvajai DB, tāpēc kolonnā glabāts tulkojums izzustu
 * katrā palaišanā un tiktu pirkts no jauna (sk. lib/tulkojumi.php galvu).
 */
function granti_tulk_db_path(): string {
    $env = getenv('GRANTI_TULK_DB');
    return ($env !== false && $env !== '') ? $env : granti_root() . '/db/tulkojumi.sqlite';
}

function granti_data_dir(): string  { return granti_root() . '/data'; }
/** Lejupielāžu kešs: bulk JSON (~124 MB) un topicDetails atbildes. */
function granti_cache_dir(): string { return granti_data_dir() . '/cache'; }
function granti_log_path(): string  { return granti_data_dir() . '/build.log'; }
function granti_state_path(): string{ return granti_data_dir() . '/build_state.json'; }
function granti_lock_path(): string { return granti_data_dir() . '/build.lock'; }
/** Cron karodziņš: ja faila nav, cron_build.php neko nedara (slēdzis panelī). */
function granti_cron_flag(): string { return granti_data_dir() . '/cron_enabled.flag'; }
/** STOP karodziņš: būve to pārbauda garajās cilpās un iziet ar žurnāla ierakstu. */
function granti_stop_flag(): string { return granti_data_dir() . '/stop.flag'; }
/**
 * Tulkošanas slēdzenes. Tās dzīvo data/ mapē, ne blakus datubāzei: keša DB ceļu var
 * pārcelt ar GRANTI_TULK_DB (testi to dara), un tad slēdzene aizceļotu līdzi, bet darba
 * ierakstā lib/admin_jobs.php ('own_lock') ceļš ir fiksēts. Divas atšķirīgas vietas
 * nozīmētu, ka panelis sargā vienu failu, bet skripts ņem citu.
 */
function granti_tulk_lock_path(): string  { return granti_data_dir() . '/granti_tulko.lock'; }
function granti_batch_lock_path(): string { return granti_data_dir() . '/granti_batch.lock'; }

/** Publiskā sadaļas adrese (bez domēna) — lapai, vietnes kartei un 301 pārvirzēm. */
const GRANTI_URL_BASE = '/granti/';

/**
 * Identifikators -> publiskās adreses gabals. Kopīgs būvei (raksta slug kolonnā) un
 * lapai (vecās ?id= formas 301 pārvirzei), tāpēc tas dzīvo šeit, ne vienā no tiem.
 *
 * Ievade ir ES/SIF identifikators ar avota prefiksu ("EU:HORIZON-CL4-2027-01-MAT-PROD-61",
 * "SIF:6143647"). Kols un pieturzīmes kļūst par defisi, burti mazie. Rezultāts atbilst
 * maršruta izteiksmei ^[a-z0-9-]+$ gan router.php, gan htaccess.txt pusē — ja te ielaistu
 * ko citu, adrese uzbūvētos, bet maršruts to nesaprastu un lapa atdotu 404.
 */
function gr_slug(string $id): string {
    $s = strtolower($id);
    $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? $s;
    $s = trim($s, '-');
    // 200 rakstzīmes ir ar rezervi: garākais ES identifikators korpusā ir 46 rakstzīmes.
    return $s === '' ? 'x' : substr($s, 0, 200);
}
