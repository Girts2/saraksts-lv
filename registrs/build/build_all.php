<?php
/**
 * server/build/build_all.php — pilnais datu būvēšanas orķestrators.
 *
 * Posmi:
 *   1. download  — CSV no data.gov.lv -> {data}/csv/
 *   2. convert   — CSV -> {data}/staging/ur_data.db
 *   3. prepare   — ceturkšņu apvienošana + companies.sqlite + nace_stats.sqlite (staging)
 *   3.5 sections — sadaļas no tās pašas staging ur_data.db: Nozare (nozare/katalogs.sqlite),
 *                  Struktūra (struktura.php), Pensionārs (pensionars/pensionari.sqlite)
 *   4. swap      — atomiski nomaina dzīvās DB (rename tajā pašā failu sistēmā)
 *
 * Ceļi (pārrakstāmi ar vidi):
 *   REG_DATA_DIR    — datu sakne (noklusējums: projekta sakne lokāli)
 *   UR_DB_PATH      — dzīvā ur_data.db (noklusējums: {data}/csv/SQLite/ur_data.db)
 *   REG_SEARCH_DIR  — dzīvā meklēšanas mape (noklusējums: DOCUMENT_ROOT vai {data}/out/registrs/assets/search)
 *
 * Lietošana: php build_all.php [--skip-download] [--no-swap] [--skip-sections]
 * Drošība: lock fails novērš paralēlas palaišanas; viss tiek žurnalēts build.log.
 */
declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '2048M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/download.php';
require_once __DIR__ . '/validacija.php';
require_once __DIR__ . '/convert.php';
require_once __DIR__ . '/prepare.php';
require_once __DIR__ . '/izslegsana.php';   // izņemtie profili (registrs/lib/izslegtie.php)
require_once __DIR__ . '/section_nozare.php';
require_once __DIR__ . '/section_struktura.php';
require_once __DIR__ . '/section_pensionars.php';

// --- Ceļi ---
$ROOT = build_root();
$CSV_DIR = $ROOT . '/csv';
$STATE_DIR = $ROOT . '/build_state';
$STAGING = $STATE_DIR . '/staging';
$LOG_FILE = $STATE_DIR . '/build.log';
$LOCK_FILE = $STATE_DIR . '/build.lock';
$STATE_FILE = $STATE_DIR . '/build_state.json';

$LIVE_UR_DB = reg_ur_db_path();
$LIVE_SEARCH_DIR = reg_search_dir();
$HISTORY_DB = getenv('REG_HISTORY_DB') ?: ($ROOT . '/vid_quarterly_history.sqlite');
// VID gada nodokļu vēsture (prepare.php merge_annual_vid_history) — atsevišķs fails, jo
// to sēj ar roku (2022. gads, ko VID 2026-09-14 izmeta), un servera ceturkšņu vēsturi
// tas neaiztiek. Datu saknē, tāpat kā ceturkšņu vēsture; tīmeklim .sqlite ir liegts.
$VID_GADA_DB = getenv('REG_VID_GADA_DB') ?: ($ROOT . '/vid_gada_vesture.sqlite');

@mkdir($STATE_DIR, 0775, true);
@mkdir($STAGING, 0775, true);

// Argumenti: tīmekļa sinhronais režīms skaidri iestata REG_BUILD_ARGS (prioritāte);
// citādi CLI $argv.
if (isset($GLOBALS['REG_BUILD_ARGS']) && is_array($GLOBALS['REG_BUILD_ARGS'])) {
    $build_args = $GLOBALS['REG_BUILD_ARGS'];
} else {
    $build_args = $argv ?? [];
}
if (!is_array($build_args)) $build_args = [];
$skip_download = in_array('--skip-download', $build_args, true);
$no_swap = in_array('--no-swap', $build_args, true);
$skip_sections = in_array('--skip-sections', $build_args, true);

// --- Žurnalēšana + stāvoklis ---
$log = function (string $msg) use ($LOG_FILE) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;
};
$set_state = function (string $stage, string $status = 'running', array $extra = []) use ($STATE_FILE) {
    $state = array_merge([
        'stage' => $stage, 'status' => $status,
        'updated' => date('Y-m-d H:i:s'), 'pid' => getmypid(),
    ], $extra);
    // INVALID_UTF8_SUBSTITUTE: citādi kļūdas teksts ar nederīgu UTF-8 → false → tukšs stāvokļa fails
    file_put_contents($STATE_FILE, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
};

// --- Lock (novērš dubultu palaišanu) ---
$lock_fp = fopen($LOCK_FILE, 'c+');
if ($lock_fp === false || !flock($lock_fp, LOCK_EX | LOCK_NB)) {
    $log("! Būvēšana jau darbojas (lock aizņemts) — izeju.");
    exit(1);
}
ftruncate($lock_fp, 0);
fwrite($lock_fp, (string)getmypid());
fflush($lock_fp);

// Fatāla kļūda (piem., atmiņas limits) → ieraksts žurnālā un stāvoklī (config.php)
build_fatalas_kludas_sargs($log, $set_state, $STATE_FILE);

$t_start = microtime(true);
avotu_bridinajumi(null, true);   // šīs būves avotu brīdinājumi (avotu_kolonnas.php) — no nulles
@unlink(build_stop_flag()); // iepriekšējais STOP karogs netraucē jaunai būvei
// Jauns žurnāls katrai būvei (vecais -> .prev)
if (is_file($LOG_FILE)) @rename($LOG_FILE, $LOG_FILE . '.prev');
$log("=== DATU BŪVĒŠANA SĀKTA (pid " . getmypid() . ") ===");

// Posma pabeigšanas ieraksts ar ilgumu
$t_stage = microtime(true);
$stage_done = function (string $name) use (&$t_stage, $log) {
    $log("   ✔ $name PABEIGTS (" . round(microtime(true) - $t_stage) . "s)");
    $t_stage = microtime(true);
};

try {
    // 1. Lejupielāde
    if ($skip_download) {
        $log("1. POSMS: lejupielāde IZLAISTA (--skip-download)");
    } else {
        $set_state('download');
        $log("1. POSMS: CSV lejupielāde no data.gov.lv ...");
        [$ok, $fail, $failed, $izlaisti] = download_all_csvs($CSV_DIR, $log) + [3 => 0];
        if ($ok === 0) {
            throw new RuntimeException("Neviens CSV netika lejupielādēts — pārtraucu (vecie dati paliek).");
        }
        if ($fail > 0) {
            $log("   ! BRĪDINĀJUMS: $fail faili netika atjaunoti" . ($izlaisti ? " (no tiem $izlaisti izlaisti bez mēģinājuma — laika budžets vai nesasniedzams saimnieks)" : '')
                . ' — tiem paliek iepriekšējās versijas.');
        }
        $stage_done('1. POSMS (lejupielāde)');
    }

    // 2. Konversija (staging)
    build_abort_if_stopped();
    $set_state('convert');
    $staging_ur = $STAGING . '/ur_data.db';
    $log("2. POSMS: CSV -> SQLite konversija (staging) ...");
    // Dzīvā DB — avotu kolonnu līgumam (avotu_kolonnas.php): no tās pārnes iepriekšējo
    // versiju avotam, kura struktūra mainījusies. Tikai lasa.
    convert_all_csvs($CSV_DIR, $staging_ur, $log, $LIVE_UR_DB);
    // Pamata validācija: register tabulai jābūt ar saprātīgu apjomu
    $pdo = new PDO('sqlite:' . $staging_ur);
    $reg_count = (int)$pdo->query("SELECT count(*) FROM register")->fetchColumn();
    if ($reg_count < 100000) {
        throw new RuntimeException("Validācija neizdevās: register tikai $reg_count rindas (< 100000) — swap atcelts.");
    }
    $log("   Validācija OK: register $reg_count rindas");
    $stage_done('2. POSMS (konversija)');

    // 3. Sagatave (staging)
    build_abort_if_stopped();
    $set_state('prepare');
    $log("3. POSMS: ceturkšņu apvienošana + meklēšanas DB + NACE DB ...");
    merge_quarterly_history($pdo, $HISTORY_DB, $log);
    merge_annual_vid_history($pdo, $VID_GADA_DB, $log);
    // Izņemtie profili (registrs/lib/izslegtie.php) — AIZ vēstures apvienošanas (tā atjauno VID
    // rindas) un PIRMS visām atvasinātajām DB, lai uzņēmums nenonāk nevienā katalogā.
    build_izslegt_uznemumus($pdo, $log);
    $staging_companies = $STAGING . '/companies.sqlite';
    $staging_nace = $STAGING . '/nace_stats.sqlite';
    build_search_database($pdo, $staging_companies, $log);
    build_nace_database($pdo, $staging_nace, $log);
    $stage_done('3. POSMS (sagatave)');

    // 3.5 Sadaļas (Nozare, Struktūra, Pensionārs) — no tās pašas staging ur_data.db
    $staging_katalogs = $STAGING . '/katalogs.sqlite';
    $staging_pensionari = $STAGING . '/pensionari.sqlite';
    $staging_struktura = $STAGING . '/struktura.php';
    $staging_struktura_data = $STAGING . '/struktura_data';
    if ($skip_sections) {
        $log("3.5 POSMS: sadaļas IZLAISTAS (--skip-sections)");
    } else {
        build_abort_if_stopped();
        $set_state('sections');
        $log("3.5 POSMS: sadaļu būve (Nozare, Struktūra, Pensionārs) ...");
        $nace_csv = __DIR__ . '/NACE.csv';
        build_section_nozare($pdo, $nace_csv, $staging_katalogs, $log);
        build_abort_if_stopped();
        build_section_struktura($pdo, $nace_csv, __DIR__ . '/templates/struktura_template.html',
            $staging_struktura, $staging_struktura_data, $log);
        build_abort_if_stopped();
        build_section_pensionars($pdo, $nace_csv, $staging_pensionari, $log);
        $stage_done('3.5 POSMS (sadaļas)');
    }
    $pdo = null;

    // Tabulu sarukums pret dzīvo DB (registrs/build/validacija.php) — TIEŠI PIRMS
    // swap, kad staging DB ir pilna: ceturkšņu tabulu 3. posms vēl papildina, un
    // 2. posmā salīdzinājums rādītu nepatiesu −66 % (integrācijas tests 2026-08-22).
    // Pazudusi vai krasi sarukusi pamata tabula = swap atcelts; pārējām — brīdinājums.
    $sar = build_tabulu_sarukums(new PDO('sqlite:' . $staging_ur), $LIVE_UR_DB);
    foreach ($sar['bridinajumi'] as $b) $log("   ! BRĪDINĀJUMS: $b");
    if ($sar['kritiskie']) {
        throw new RuntimeException('Validācija neizdevās: ' . implode('; ', $sar['kritiskie']) . ' — swap atcelts.');
    }
    $log('   Tabulu sarukuma pārbaude OK: ' . $sar['salidzinatas'] . ' tabulas salīdzinātas'
        . ($sar['bridinajumi'] ? ', ' . count($sar['bridinajumi']) . ' brīdinājumi' : ''));

    // 4. Atomiskā nomaiņa (pēc sākšanas STOP vairs nepārbauda — swap jāpabeidz viengabalaini)
    if ($no_swap) {
        $log("4. POSMS: swap IZLAISTS (--no-swap). Staging: $STAGING");
    } else {
        build_abort_if_stopped();
        $set_state('swap');
        $log("4. POSMS: dzīvo DB atomiskā nomaiņa ...");
        @mkdir(dirname($LIVE_UR_DB), 0775, true);
        @mkdir($LIVE_SEARCH_DIR, 0775, true);

        // rename() ir atomisks tajā pašā failu sistēmā; staging ir uz tā paša diska.
        $swap = function (string $src, string $dst) use ($log) {
            if (!is_file($src)) throw new RuntimeException("Trūkst staging faila: $src");
            if (!rename($src, $dst)) {
                // Cita failu sistēma? Mēģina copy+rename blakus mērķim.
                $tmp = $dst . '.new';
                if (!copy($src, $tmp) || !rename($tmp, $dst)) {
                    throw new RuntimeException("Neizdevās nomainīt: $dst");
                }
                @unlink($src);
            }
            $log("   + Nomainīts: $dst (" . number_format((float)filesize($dst), 0, '.', ' ') . " b)");
        };
        $swap($staging_ur, $LIVE_UR_DB);
        $swap($staging_companies, $LIVE_SEARCH_DIR . '/companies.sqlite');
        $swap($staging_nace, $LIVE_SEARCH_DIR . '/nace_stats.sqlite');

        // Mapes nomaiņa: veco pārsauc uz .old, jauno ieceļ, veco izdzēš.
        $swapDir = function (string $srcDir, string $dstDir) use ($log) {
            if (!is_dir($srcDir)) throw new RuntimeException("Trūkst staging mapes: $srcDir");
            $rrm = function (string $dir) use (&$rrm) {
                foreach (glob($dir . '/*') ?: [] as $f) { is_dir($f) ? $rrm($f) : @unlink($f); }
                @rmdir($dir);
            };
            @mkdir(dirname($dstDir), 0775, true);
            $old = $dstDir . '.old';
            if (is_dir($old)) $rrm($old);
            if (is_dir($dstDir) && !rename($dstDir, $old)) {
                throw new RuntimeException("Neizdevās atbrīvot mapi: $dstDir");
            }
            if (!rename($srcDir, $dstDir)) throw new RuntimeException("Neizdevās nomainīt mapi: $dstDir");
            if (is_dir($old)) $rrm($old);
            $log("   + Nomainīta mape: $dstDir");
        };

        // Sadaļu artefakti -> docroot (nozare/, pensionars/, struktura.php + struktura/data/)
        if (!$skip_sections) {
            @mkdir(reg_docroot() . '/nozare', 0775, true);
            @mkdir(reg_docroot() . '/pensionars', 0775, true);
            $swap($staging_katalogs, reg_docroot() . '/nozare/katalogs.sqlite');
            $swap($staging_pensionari, reg_docroot() . '/pensionars/pensionari.sqlite');
            $swap($staging_struktura, reg_docroot() . '/struktura.php');
            $swapDir($staging_struktura_data, reg_docroot() . '/struktura/data');
        }
        $stage_done('4. POSMS (swap)');

        // 5. Gada-atskaites izsekošana + AI keša invalidācija + sitemap (tikai pēc swap).
        $set_state('sitemap');
        $log("5. POSMS: gada-atskaites izsekošana, AI keša invalidācija, sitemap ...");
        require_once __DIR__ . '/report_tracker.php';
        require_once __DIR__ . '/../lib/config.php'; // BASE_DOMAIN
        $base_domain = getenv('REG_BASE_DOMAIN') ?: (defined('BASE_DOMAIN') ? BASE_DOMAIN : 'https://saraksts.lv');
        $live = new PDO('sqlite:' . $LIVE_UR_DB);
        $live->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tracker_db = $STATE_DIR . '/report_years.sqlite';
        $rt = update_report_years($live, $tracker_db, $log);
        $ai_cache_dir = reg_docroot() . '/registrs/ai_cache';
        if (!empty($rt['changed'])) invalidate_ai_cache($rt['changed'], $ai_cache_dir, $log);
        // MI atbildes glabā 2 dienas (report_tracker.php iztirit_ai_kesu) — arī lapām, kuras neviens neatver.
        iztirit_ai_kesu($ai_cache_dir, $log);
        $ai_kess_iztirits = true;
        generate_sitemaps($rt['dates'], reg_docroot() . '/sitemap', $base_domain, $log);
        // IndexNow: pasaka Bing/Yandex par lapām, kuru saturs tiešām mainījās (jauns
        // gada pārskats). Cloudflare Crawler Hints to nedarīja un nevarēja — sk.
        // registrs/build/indexnow.php galvu. Kļūme šeit būvi neaptur.
        if (!empty($rt['changed'])) {
            require_once __DIR__ . '/indexnow.php';
            indexnow_submit_regcodes($rt['changed'], $base_domain, $log);
        }
        $live = null;
        $stage_done('5. POSMS (sitemap)');
    }

    $dt = round(microtime(true) - $t_start);
    // Avotu brīdinājumi BEIGĀS: admin panelis rāda žurnāla pēdējās rindas un būves stāvokli,
    // un iepriekš šīs rindas palika 400 rindu žurnāla vidū ar "status: ok" (recenzija 2026-10-14).
    $bridinajumi = avotu_bridinajumi();
    if ($bridinajumi) {
        $log('   ! ŠĪS BŪVES AVOTU BRĪDINĀJUMI (' . count($bridinajumi) . '):');
        foreach (array_slice($bridinajumi, 0, 12) as $b) $log("     - $b");
        $applog = reg_docroot() . '/lib/applog.php';
        if (is_file($applog)) {
            try {
                require_once $applog;
                applog_event('WARN', 'registrs', 'buve_avoti', count($bridinajumi) . ' avotu brīdinājumi: '
                    . implode(' | ', array_slice($bridinajumi, 0, 3)), true);
            } catch (Throwable $e) { /* žurnāls nedrīkst apturēt pabeigtu būvi */ }
        }
    }
    $set_state('done', 'ok', ['duration_s' => $dt, 'register_rows' => $reg_count]
        + ($bridinajumi ? ['bridinajumi' => array_slice($bridinajumi, 0, 30)] : []));
    $log("=== PABEIGTS OK ({$dt}s)" . ($bridinajumi ? ' — ar ' . count($bridinajumi) . ' avotu brīdinājumiem' : '') . ' ===');
} catch (BuildStopped $e) {
    $set_state('stopped', 'stopped', ['error' => $e->getMessage()]);
    $log("■ " . $e->getMessage());
    $log("=== APTURĒTS AR STOP (dzīvās DB nav mainītas, ja swap nebija sācies) ===");
    $build_exit_code = 1;
} catch (Throwable $e) {
    $set_state('error', 'error', ['error' => $e->getMessage()]);
    $log("!!! KĻŪDA: " . $e->getMessage());
    $log("=== PĀRTRAUKTS (dzīvās DB nav mainītas, ja swap nebija sācies) ===");
    $build_exit_code = 1;
} finally {
    // MI keša 2 dienu glabāšana nedrīkst būt atkarīga no būves veiksmes (audits 2026-11-22: kļūda agrākā posmā
    // vai --no-swap izlaida 5. posmu, un vecas atbildes palika diskā). Kļūda šeit būvi neietekmē.
    if (empty($ai_kess_iztirits)) {
        try {
            require_once __DIR__ . '/report_tracker.php';
            iztirit_ai_kesu(reg_docroot() . '/registrs/ai_cache', $log);
        } catch (Throwable $e) { /* tīrīšana nav kritiska */ }
    }
    // SVARĪGI: exit() catch blokā apietu finally — tāpēc izejam TIKAI pēc sakopšanas.
    @unlink(build_stop_flag());
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
    @unlink($LOCK_FILE);
}
if (!empty($build_exit_code)) exit($build_exit_code);
