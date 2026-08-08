<?php
/**
 * lib/admin_jobs.php — VISU sadaļu darbu (job) reģistrs.
 *
 * Viena vieta, kas apraksta, ko vispār var palaist: komanda, argumenti, kur krīt
 * izvade, kā saprast, vai darbs jau iet. admin.php un cron/dispatch.php lasa tikai
 * šo — tāpēc jauns darbs jāpievieno vienā vietā, un tas uzreiz parādās gan panelī,
 * gan cron izvēlnē.
 *
 * TERMINI:
 *   sadaļa (section) — Reģistrs, Konkursi, Iespēja … (paneļa grupas)
 *   darbs (job)      — konkrēta izpilde ('registrs.build_full', 'konkursi.sync')
 *   daļa (part)      — darbs, kas ir kādas lielākas ķēdes gabals (piem. tikai Nozare)
 *
 * Katram darbam ir SAVS lock fails, tāpēc dažādu sadaļu darbi drīkst iet paralēli,
 * bet viens un tas pats darbs divreiz — ne.
 */
declare(strict_types=1);

require_once __DIR__ . '/applog.php';

function admin_root(): string       { return dirname(__DIR__); }
function admin_state_dir(): string  { return admin_root() . '/admin_state'; }
function admin_job_log(string $id): string { return admin_state_dir() . '/' . $id . '.log'; }
function admin_job_lock(string $id): string { return admin_state_dir() . '/' . $id . '.lock'; }
function admin_schedule_file(): string { return admin_state_dir() . '/schedule.json'; }
function admin_phpbin_file(): string { return admin_root() . '/build_state/php_bin.txt'; }

/**
 * Sadaļas un to darbi.
 *
 * 'runner'  — 'php' (PHP CLI) vai 'python' (python3); pārbaudām pirms palaišanas.
 * 'script'  — absolūts ceļš.
 * 'args'    — masīvs; katrs elements tiek atsevišķi citēts.
 * 'own_lock'— darbam JAU ir savs lock mehānisms koda iekšienē (build_all, sync);
 *             tad lieto to, nevis mūsu, citādi divi lock sistēmas cīnītos.
 * 'heavy'   — brīdinājums UI (ilgi / slogo serveri).
 * 'costs'   — maksā naudu (Gemini API) → panelī atsevišķs apstiprinājums.
 */
function admin_sections(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $secs = admin_sections_def();
    // Katram darbam pieliek savu id un piederību. Bez šī darbs, kas paņemts no
    // sadaļas saraksta, atšķirtos no tā paša darba, kas paņemts no admin_all_jobs() —
    // un admin_job_running() nezinātu, kuru slēdzeni pārbaudīt.
    foreach ($secs as $sid => $sec) {
        foreach (($sec['jobs'] ?? []) as $jid => $job) {
            $job['id'] = $jid;
            $job['section'] = $sid;
            $job['section_name'] = $sec['name'];
            $secs[$sid]['jobs'][$jid] = $job;
        }
    }
    return $cache = $secs;
}

/** Darbu apraksti (bez atvasinātajām atslēgām — tās pieliek admin_sections()). */
function admin_sections_def(): array {
    $R = admin_root();
    return [
        'registrs' => [
            'name'  => 'Reģistrs · Nozare · Struktūra · Pensionārs',
            'note'  => 'Viens datu konveijers: VID/UR CSV → staging SQLite → sadaļu datubāzes → atomiska nomaiņa.',
            'panel' => 'data_admin.php',
            'jobs'  => [
                'registrs.build_full' => [
                    'label'  => 'Pilnā būve (lejupielāde → konversija → sadaļas → nomaiņa)',
                    'runner' => 'php', 'script' => "$R/registrs/build/build_all.php", 'args' => [],
                    'own_lock' => "$R/build_state/build.lock",
                    'heavy'  => true,
                    'desc'   => 'Viss cikls no data.gov.lv. Ilgst desmitiem minūšu.',
                ],
                'registrs.build_nodl' => [
                    'label'  => 'Būve BEZ lejupielādes (izmanto jau esošos CSV)',
                    'runner' => 'php', 'script' => "$R/registrs/build/build_all.php", 'args' => ['--skip-download'],
                    'own_lock' => "$R/build_state/build.lock",
                    'heavy'  => true,
                    'desc'   => 'Pārbūvē no jau lejupielādētajiem CSV — ātrāk, ja avots nav mainījies.',
                ],
                'registrs.sections_all' => [
                    'label'  => 'Tikai sadaļas (visas trīs no dzīvās ur_data.db)',
                    'runner' => 'php', 'script' => "$R/registrs/build/sections_cli.php", 'args' => ['all'],
                    'desc'   => 'Nozare + Struktūra + Pensionārs bez pilnā cikla.',
                ],
                'registrs.section_nozare' => [
                    'label'  => 'Daļa: Nozare (katalogs.sqlite)',
                    'runner' => 'php', 'script' => "$R/registrs/build/sections_cli.php", 'args' => ['nozare'],
                    'part_of' => 'registrs.sections_all',
                ],
                'registrs.section_struktura' => [
                    'label'  => 'Daļa: Struktūra (struktura.php + data/)',
                    'runner' => 'php', 'script' => "$R/registrs/build/sections_cli.php", 'args' => ['struktura'],
                    'part_of' => 'registrs.sections_all',
                ],
                'registrs.section_pensionars' => [
                    'label'  => 'Daļa: Pensionārs (pensionari.sqlite)',
                    'runner' => 'php', 'script' => "$R/registrs/build/sections_cli.php", 'args' => ['pensionars'],
                    'part_of' => 'registrs.sections_all',
                ],
            ],
        ],

        'konkursi' => [
            'name'  => 'Konkursi (ES TED + ~46 nacionālie/IFI avoti)',
            'note'  => 'Sinhronizācija ievāc paziņojumus; tulkošana pārtulko virsrakstus latviski.',
            'panel' => 'konkursi_admin.php',
            'jobs'  => [
                'konkursi.sync' => [
                    'label'  => 'Pilnā sinhronizācija (visi avoti)',
                    'runner' => 'php', 'script' => "$R/konkursi/bin/sync.php", 'args' => [],
                    'own_lock' => "$R/konkursi/data/sync.lock",
                    'heavy'  => true,
                    'desc'   => 'TED paketes + visi nacionālie avoti. Ilgst 20–60 min.',
                ],
                'konkursi.sync_ted' => [
                    'label'  => 'Daļa: tikai TED (ES līmenis)',
                    'runner' => 'php', 'script' => "$R/konkursi/bin/sync.php", 'args' => ['--only=ted'],
                    'own_lock' => "$R/konkursi/data/sync.lock",
                    'part_of' => 'konkursi.sync',
                ],
                'konkursi.sync_baltija' => [
                    'label'  => 'Daļa: tikai Baltija (IUB, CVP IS, RHR)',
                    'runner' => 'php', 'script' => "$R/konkursi/bin/sync.php", 'args' => ['--only=iub,lt,rhr'],
                    'own_lock' => "$R/konkursi/data/sync.lock",
                    'part_of' => 'konkursi.sync',
                ],
                'konkursi.translate' => [
                    'label'  => 'Virsrakstu tulkošana latviski (atlikušie)',
                    'runner' => 'php', 'script' => "$R/konkursi/bin/translate_titles.php", 'args' => ['--apply'],
                    'costs'  => true,
                    'desc'   => 'Gemini API — MAKSAS. Tulko tikai title_lv IS NULL, tāpēc parasti maz.',
                ],
                'konkursi.index' => [
                    'label'  => 'Displeja indeksu pārbaude/būve',
                    'runner' => 'php', 'script' => "$R/konkursi/bin/migrate_display_index.php", 'args' => ['--apply'],
                    'desc'   => 'Idempotents. Jāpalaiž pēc koda izvietošanas, pirms lapa saņem apmeklētājus.',
                ],
            ],
        ],

        'lejupielade' => [
            'name'  => 'Lejupielāde (koda un datu pakotnes)',
            'note'  => 'Saliek publicējamās .zip pakotnes sadaļai "Lejupielāde".',
            'jobs'  => [
                'lejupielade.build' => [
                    'label'  => 'Pārbūvēt lejupielādes pakotnes',
                    'runner' => 'php', 'script' => "$R/tools/build_download.php", 'args' => [],
                    'heavy'  => true,
                    'desc'   => 'Saraksts-lv-kods.zip un sadaļu arhīvi.',
                ],
            ],
        ],

        'iespeja' => [
            'name'  => 'Iespēja (vietas karte)',
            'note'  => 'Deviņi soļi: OSM un kadastra dati → CSV → MySQL. Visi deviņi ir PHP — '
                     . 'python3 nav vajadzīgs. Pilnai ķēdei vajag ~7 GB brīvas vietas '
                     . '(Building.gml vien ir 5,3 GB); soļi 5.–9. raksta ražošanas MySQL.',
            'jobs'  => admin_iespeja_jobs($R),
        ],
    ];
}

/**
 * Iespējas 9 soļi.
 *
 * Soļi 5.–9. ir pārrakstīti PHP (Iespēja/php/), tāpēc tos var dzīt cron uz
 * hostinga, kur Python nav. Rindas sakrīt ar Python oriģinālu — 396 868 rindas
 * 13 tabulās, viens lauks atšķiras par 0,6 mm (peldošā punkta pēdējais bits);
 * salīdzinājumu atkārto Iespēja/php/tools/ rīki.
 *
 * Solis 1. arī ir PHP: tas ņēma 139 MB .pbf izgriezumu tikai ~2500 punktu dēļ,
 * tāpēc tagad prasa tos pašus tipus no Overpass, tāpat kā 9. solis. Blakusieguvums —
 * Geofabrik izgriezums pārkāra pār robežu un ieskaitīja Igaunijas un Lietuvas
 * uzņēmumus; Overpass lieto precīzu administratīvo robežu.
 *
 * Soļi 3. un 4. arī ir PHP: pandas apvienošanas un grupēšanas aizstāj SQLite,
 * shapely centroīdu — pašu rēķināts (skat. ie_centroid_from_poslist).
 *
 * Kopš 2026-07-28 VISI DEVIŅI SOĻI IR PHP — konveijeram vairs nevajag ne python3,
 * ne nevienu Python pakotni, tāpēc to var pilnībā dzīt cron uz koplietotā hostinga.
 * Vecie .py oriģināli pārcelti ārpus docroot (salīdzināšana pabeigta).
 */
function admin_iespeja_jobs(string $R): array {
    $steps = [
        1 => ['php',    'php/step1_poi.php',          'Pamata POI no Overpass'],
        2 => ['php',    'php/step2_leaflet.php',      'Leaflet (nav obligāts)'],
        3 => ['php',    'php/step3_summ_level.php',   'Summēšana pa līmeņiem'],
        4 => ['php',    'php/step4_all.php',          'Apvienotais kopsavilkums'],
        5 => ['php',    'php/step5_upload.php',       'Augšupielāde MySQL'],
        6 => ['php',    'php/step6_offices.php',      'Biroji'],
        7 => ['php',    'php/step7_tourism.php',      'Tūrisma objekti'],
        8 => ['php',    'php/step8_institutions.php', 'Iestādes'],
        9 => ['php',    'php/step9_competitors.php',  'OSM konkurenti (Overpass)'],
    ];
    $jobs = [];
    foreach ($steps as $n => [$runner, $file, $desc]) {
        $path = "$R/Iespēja/$file";
        if (!is_file($path)) continue;   // pakotnē soļa var nebūt
        $jobs["iespeja.step$n"] = [
            'label'  => "Solis $n · $desc",
            'runner' => $runner, 'script' => $path, 'args' => [],
            'part_of' => 'iespeja.all',
            'desc'   => $file . ($runner === 'python' ? '  (prasa python3)' : ''),
        ];
    }
    if ($jobs) {
        // Virsdarbs = visi soļi pēc kārtas vienā fona procesā.
        $jobs = ['iespeja.all' => [
            'label'  => 'Visi 9 soļi pēc kārtas',
            'runner' => 'chain', 'chain' => array_keys($jobs),
            'heavy'  => true,
            'desc'   => 'Katrs solis gaida iepriekšējo; pirmā kļūme aptur ķēdi.',
        ]] + $jobs;
    }
    return $jobs;
}

/** Plakans saraksts id => darbs. */
function admin_all_jobs(): array {
    $out = [];
    foreach (admin_sections() as $sec) {
        foreach (($sec['jobs'] ?? []) as $jid => $job) $out[$jid] = $job;
    }
    return $out;
}

function admin_job(string $id): ?array {
    $all = admin_all_jobs();
    return $all[$id] ?? null;
}

/**
 * Vai darbs šobrīd iet? Pārbauda flock, nevis faila esamību — nokauts process
 * atstāj lock failu, bet flock ir brīvs (tas pats princips kā data_admin.php).
 */
function admin_job_running(array $job): bool {
    $lock = $job['own_lock'] ?? admin_job_lock($job['id']);
    if (!is_file($lock)) return false;
    $fp = @fopen($lock, 'r');
    if ($fp === false) return true;
    $free = flock($fp, LOCK_EX | LOCK_NB);
    if ($free) { flock($fp, LOCK_UN); fclose($fp); return false; }
    fclose($fp);
    return true;
}

/** Pēdējās izpildes kopsavilkums no darba žurnāla faila. */
function admin_job_last(array $job): array {
    $log = admin_job_log($job['id']);
    if (!is_file($log)) return ['when' => null, 'tail' => '', 'size' => 0];
    $lines = @file($log, FILE_IGNORE_NEW_LINES) ?: [];
    return [
        'when' => date('Y-m-d H:i', (int)@filemtime($log)),
        'tail' => implode("\n", array_slice($lines, -12)),
        'size' => (int)@filesize($log),
    ];
}

/** Vai funkcija tiešām izsaucama (nav disable_functions sarakstā)? */
function admin_fn_ok(string $f): bool {
    if (!function_exists($f)) return false;
    static $disabled = null;
    if ($disabled === null) {
        $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
    }
    return !in_array(strtolower($f), $disabled, true);
}

function admin_sh_arg(string $s): string {
    if (admin_fn_ok('escapeshellarg')) return escapeshellarg($s);
    return "'" . str_replace("'", "'\\''", $s) . "'";
}

/** PHP CLI bināra ceļš (tā pati loģika, ko data_admin.php). */
function admin_php_bin(): string {
    $f = admin_phpbin_file();
    if (is_file($f)) {
        $b = trim((string)@file_get_contents($f));
        if ($b !== '') return $b;
    }
    $env = getenv('REG_PHP_BIN');
    return ($env !== false && $env !== '') ? $env : 'php';
}

/** python3 ceļš (vide > parastie kandidāti). */
function admin_python_bin(): string {
    $env = getenv('REG_PYTHON_BIN');
    if ($env !== false && $env !== '') return $env;
    return 'python3';
}

/**
 * Vides priedēklis CLI procesam. Web konteksts var būt konfigurēts ar SetEnv/php.ini
 * (REG_DATA_DIR, UR_DB_PATH …), bet fona process šos mainīgos NEMANTO — bez tiem būve
 * rakstītu noklusējuma ceļos, nevis tur, kur dzīvo īstie dati. `env VAR=val` jālieto
 * tāpēc, ka komandu ietin setsid/nohup, un tie `VAR=val` sintaksi neinterpretē
 * (mēģinātu palaist "VAR=val" kā komandu). Tā pati loģika, kas data_admin.php.
 */
function admin_env_prefix(): string {
    $parts = [];
    foreach (['REG_DATA_DIR', 'UR_DB_PATH', 'REG_SEARCH_DIR', 'REG_HISTORY_DB',
              'KONKURSI_DB_PATH', 'REG_TZ',
              // Iespējas 5.–9. solis: datu mape, MySQL pieslēgums un valsts kods.
              // IESPEJA_TABLE_PREFIX vairs nav — tabulu vārdus veido schema.php
              // pēc valsts koda (lv_buildings, lv_poi …), nevis hostinga prefiksa.
              'IESPEJA_DATA_DIR', 'IESPEJA_DB_HOST', 'IESPEJA_DB_PORT', 'IESPEJA_DB_NAME',
              'IESPEJA_DB_USER', 'IESPEJA_DB_PASS', 'IESPEJA_COUNTRY'] as $key) {
        $v = getenv($key);
        if ($v !== false && $v !== '') $parts[] = $key . '=' . admin_sh_arg($v);
    }
    return $parts ? 'env ' . implode(' ', $parts) . ' ' : '';
}

/** Viena darba komandrinda (bez fona ietinuma). */
function admin_job_command(array $job): ?string {
    if (($job['runner'] ?? '') === 'chain') return null;   // ķēdi veido admin_launch()
    $bin = ($job['runner'] === 'python') ? admin_python_bin() : admin_php_bin();
    $cmd = admin_env_prefix() . admin_sh_arg($bin) . ' ' . admin_sh_arg((string)$job['script']);
    foreach (($job['args'] ?? []) as $a) $cmd .= ' ' . admin_sh_arg((string)$a);
    return $cmd;
}

/**
 * Palaiž darbu (vai vairākus pēc kārtas) FONĀ.
 * Atgriež [metode|null, kļūda|null]. Fons ir obligāts: web pieprasījums LiteSpeed
 * vidē tiek nokauts pēc dažām minūtēm, bet būves ilgst desmitiem minūšu.
 */
function admin_launch(array $jobIds): array {
    if (!is_dir(admin_state_dir())) @mkdir(admin_state_dir(), 0775, true);

    // Grupas: parasts darbs = grupa no viena soļa; 'chain' darbs = grupa no vairākiem.
    // Grupas IEKŠIENĒ soļus savieno ar '&&' (pirmā kļūme aptur pārējos — ķēdes solim
    // nav jēgas, ja iepriekšējais nesagatavoja datus), bet GRUPAS savā starpā ar ';'
    // (atsevišķi izvēlēti darbi ir neatkarīgi — viena kļūme nedrīkst atcelt pārējos).
    $all = admin_all_jobs();
    $php = admin_sh_arg(admin_php_bin());
    $run = admin_sh_arg(admin_root() . '/lib/job_run.php');
    $groups = [];
    $seen = [];
    foreach ($jobIds as $id) {
        $j = $all[$id] ?? null;
        if (!$j) continue;
        $steps = (($j['runner'] ?? '') === 'chain')
            ? array_values(array_filter((array)($j['chain'] ?? []), fn($s) => isset($all[$s])))
            : [$id];
        $steps = array_values(array_filter($steps, function ($s) use (&$seen) {
            if (isset($seen[$s])) return false;   // vienu darbu divreiz nepalaižam
            $seen[$s] = true;
            return true;
        }));
        if (!$steps) continue;
        $groups[] = implode(' && ', array_map(fn($s) => "$php $run " . admin_sh_arg($s), $steps));
    }
    if (!$groups) return [null, 'Nav neviena derīga darba.'];

    // Slēdzeni, izvades uztveršanu un žurnalēšanu dara job_run.php — čaulā palikusi
    // tikai secība un fona atdalīšana. Sk. lib/job_run.php par to, kāpēc `flock`
    // utilīta netiek lietota.
    $script = implode('; ', $groups);
    $inner = 'if command -v setsid >/dev/null 2>&1; then setsid /bin/sh -c ' . admin_sh_arg($script) . ' >/dev/null 2>&1 </dev/null &'
           . ' elif command -v nohup >/dev/null 2>&1; then nohup /bin/sh -c ' . admin_sh_arg($script) . ' >/dev/null 2>&1 </dev/null &'
           . ' else /bin/sh -c ' . admin_sh_arg($script) . ' >/dev/null 2>&1 </dev/null & fi';
    $sh = '/bin/sh -c ' . admin_sh_arg($inner);

    if (admin_fn_ok('proc_open')) {
        $p = @proc_open($sh, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            foreach ($pipes as $pp) if (is_resource($pp)) @fclose($pp);
            @proc_close($p);
            return ['proc_open', null];
        }
    }
    if (admin_fn_ok('popen'))      { $h = @popen($sh, 'r'); if ($h !== false) { @pclose($h); return ['popen', null]; } }
    if (admin_fn_ok('exec'))       { @exec($sh); return ['exec', null]; }
    if (admin_fn_ok('shell_exec')) { @shell_exec($sh); return ['shell_exec', null]; }
    return [null, 'Visas fona-izpildes funkcijas (proc_open/popen/exec/shell_exec) ir liegtas šajā PHP konfigurācijā.'];
}

// ── Cron grafiks ────────────────────────────────────────────────────────────
/**
 * Grafiks ir JSON: darba id → {enabled, time:"HH:MM", days:[0-6] vai [], last_run}.
 * days tukšs = katru dienu. Grafiku IZPILDA cron/dispatch.php, ko sistēmas cron
 * sauc ik 5 minūtes — koplietotā hostingā PHP nevar droši rakstīt īsto crontab,
 * tāpēc viena sistēmas rinda + savs plānotājs ir vienīgais stabilais variants.
 */
function admin_schedule_load(): array {
    $f = admin_schedule_file();
    if (!is_file($f)) return [];
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function admin_schedule_save(array $sched): bool {
    if (!is_dir(admin_state_dir())) @mkdir(admin_state_dir(), 0775, true);
    return @file_put_contents(admin_schedule_file(),
        json_encode($sched, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

/** Vai darbam šobrīd (Rīgas laikā) pienācis laiks? Precizitāte — dispatcher solis. */
function admin_schedule_due(array $entry, int $stepMinutes = 5): bool {
    if (empty($entry['enabled'])) return false;
    $tz = new DateTimeZone('Europe/Riga');
    $now = new DateTimeImmutable('now', $tz);

    $days = $entry['days'] ?? [];
    if ($days && !in_array((int)$now->format('w'), array_map('intval', $days), true)) return false;

    [$hh, $mm] = array_pad(explode(':', (string)($entry['time'] ?? '03:00')), 2, '0');
    $target = $now->setTime((int)$hh, (int)$mm, 0);
    $diff = ($now->getTimestamp() - $target->getTimestamp()) / 60;
    if ($diff < 0 || $diff >= $stepMinutes) return false;   // logs [laiks; laiks+solis)

    // Vienu reizi dienā — ja jau šodien palaists, vairs ne.
    $last = (string)($entry['last_run'] ?? '');
    return substr($last, 0, 10) !== $now->format('Y-m-d');
}
