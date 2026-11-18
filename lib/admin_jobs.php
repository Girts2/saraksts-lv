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
                    // Stāvoklis + žurnāls mirušas būves atpazīšanai (admin_buve_nomirusi, dispečers ik 5 min).
                    'state_file' => "$R/build_state/build_state.json", 'state_log' => "$R/build_state/build.log",
                    'heavy'  => true,
                    'desc'   => 'Viss cikls no data.gov.lv. Ilgst desmitiem minūšu.',
                ],
                'registrs.build_nodl' => [
                    'label'  => 'Būve BEZ lejupielādes (izmanto jau esošos CSV)',
                    'runner' => 'php', 'script' => "$R/registrs/build/build_all.php", 'args' => ['--skip-download'],
                    'own_lock' => "$R/build_state/build.lock",
                    'state_file' => "$R/build_state/build_state.json", 'state_log' => "$R/build_state/build.log",
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

        'granti' => [
            'name'  => 'Granti (ES portāls + SIF, CFLA, VKKF, LIAA, JSPA)',
            'note'  => 'Būve lejupielādē ES bulk failu (~124 MB), ievāc Latvijas avotus (ar robots.txt '
                     . 'pārbaudi) un pārbūvē datubāzi; tulkošana '
                     . 'caur Gemini Batch API iztulko jaunos tekstus latviski (puse cenas). '
                     . 'Dienas ķēde "granti.diena" dara abus pēc kārtas.',
            'jobs'  => [
                'granti.diena' => [
                    'label'  => 'Dienas darbs: būve + tulkošana',
                    'runner' => 'chain', 'chain' => ['granti.build', 'granti.tulko'],
                    'heavy'  => true, 'costs' => true,
                    // own_lock VAJADZĪGS ARĪ ĶĒDEI, kaut pati ķēde neko neslēdz. Dispečers
                    // pārklāšanos pārbauda ar admin_job_running() pēc ŠĪ darba ieraksta; bez
                    // own_lock tas krīt atpakaļ uz admin_state/granti.diena.lock, ko neviens
                    // nekad neaizņem, tātad sargs vienmēr saka "neiet" un vakardienas būvei
                    // vēl strādājot tiktu palaista otra. Norādām būves slēdzeni — tā ir pirmā
                    // ķēdes soļa slēdzene un aizņemta visu ilgo daļu.
                    'own_lock' => "$R/granti/data/build.lock",
                    'desc'   => 'Šis ir tas, ko liek grafikā. Ja būve krīt, tulkošana nesākas.',
                ],
                'granti.build' => [
                    'label'  => 'Būve (ES bulk + topicDetails + SIF)',
                    'runner' => 'php', 'script' => "$R/granti/bin/build.php", 'args' => [],
                    // own_lock OBLIGĀTS: skripts pats tur flock uz šī faila. Bez tā job_run.php
                    // paņemtu to pašu failu, un bērns uzskatītu, ka būve jau iet.
                    'own_lock' => "$R/granti/data/build.lock",
                    'heavy'  => true,
                    'desc'   => 'Bulk failu velk no jauna tikai tad, ja kešotais vecāks par 20 h.',
                ],
                'granti.tulko' => [
                    'label'  => 'Tulkošana latviski (Gemini Batch)',
                    // --tiri: 180 dienas nepieskartas rindas prom; --tiri-versijas: vecas uzvednes
                    // versijas netulkotās rindas prom. Abi droši (tīrīšana iet PĒC sinhronizācijas)
                    // un bez tiem kešs augtu ~30 MB gadā bez neviena lasītāja.
                    'runner' => 'php', 'script' => "$R/granti/bin/tulko.php", 'args' => ['--tiri=180', '--tiri-versijas'],
                    'own_lock' => "$R/granti/data/granti_batch.lock",
                    'costs'  => true,
                    'desc'   => 'MAKSAS. Batch API = puse cenas; dienas griesti 2,50 € ir kodā.',
                ],
                'granti.savac' => [
                    'label'  => 'Daļa: tikai savākt gatavos Batch darbus',
                    'runner' => 'php', 'script' => "$R/granti/bin/tulko.php", 'args' => ['--savac'],
                    'own_lock' => "$R/granti/data/granti_batch.lock",
                    'part_of' => 'granti.tulko',
                    'desc'   => 'Neko jaunu nesūta, tāpēc nemaksā par jaunu darbu.',
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

/**
 * Vai procesa id dzīvs UN tas ir mūsu skripts? true/false; null = nevar noteikt.
 * Linux: /proc/<pid>/cmdline ar zīmi (pid pēc stundām var piederēt citam procesam, piem. lsphp).
 * Citur: posix_kill(pid, 0) — bez zīmes pārbaudes.
 */
function admin_pid_dzivs(int $pid, string $zime): ?bool {
    if ($pid <= 0) return false;
    // @ — ar open_basedir tīmekļa PHP brīdinājums nonāktu paneļa ?ajax=status JSON (display_errors=1) un to salauztu.
    if (@is_readable('/proc/self/cmdline')) {
        $cmd = @file_get_contents("/proc/$pid/cmdline");
        return is_string($cmd) && $cmd !== '' && str_contains($cmd, $zime);
    }
    if (function_exists('posix_kill')) {
        if (@posix_kill($pid, 0)) return true;
        return function_exists('posix_get_last_error') && posix_get_last_error() === 1;   // EPERM = dzīvs, cits lietotājs
    }
    return null;
}

/**
 * Mirušas būves atpazīšana (2026-10-22). Ja procesu nokauj no ārpuses (Hostinger cron procesu
 * limits, SIGKILL), ne būve, ne job_run.php beigu ierakstu neuzraksta: stāvoklis paliek
 * "running" mūžīgi, panelis rāda "1/5 · Lejupielāde" ar zilu joslu, un applog — tikai "start".
 * Šeit: stāvoklis "running", bet slēdzene brīva un process nedzīvo → stāvokli pārraksta uz
 * stage "died" / status "error" ar paskaidrojumu, pieliek rindu būves žurnālam un ERROR applog.
 * Dispečers sauc ik 5 min, panelis — katrā statusa pieprasījumā; otrreiz tas pats nenotiek,
 * jo stāvoklis vairs nav "running".
 * Kāpēc droši: būve aizņem slēdzeni PIRMS pirmā "running" ieraksta un atbrīvo PĒC beigu
 * ieraksta, tāpēc "running" + brīva slēdzene dzīvai būvei nav iespējams. Stāvokli pārlasām
 * pēc slēdzenes pārbaudes — ja tikko pabeigta būve paspēja ierakstīt "done", neko nedarām.
 * @return array|null jaunais stāvoklis, ja būve tikko atzīta par mirušu; citādi null.
 */
function admin_buve_nomirusi(string $stateFile, string $lockFile, ?string $logFile, string $sadala, ?callable $zinot = null): ?array {
    $lasit = static function () use ($stateFile): array {
        clearstatcache(true, $stateFile);
        $j = is_file($stateFile) ? json_decode((string)@file_get_contents($stateFile), true) : null;
        return is_array($j) ? $j : [];
    };
    $st = $lasit();
    if (($st['status'] ?? '') !== 'running') return null;
    if (admin_job_running(['id' => '', 'own_lock' => $lockFile])) return null;
    $st2 = $lasit();
    if (($st2['status'] ?? '') !== 'running' || ($st2['updated'] ?? '') !== ($st['updated'] ?? '')
        || ($st2['pid'] ?? null) !== ($st['pid'] ?? null)) return null;
    $pid = (int)($st['pid'] ?? 0);
    // Piesardzība: slēdzenes failu var izdzēst dzīvai būvei (build_all finally / panelis to dzēš pēc atbrīvošanas),
    // tad brīva "slēdzene" vēl nenozīmē mirušu procesu. Zīme 'registrs/build/' sedz build_all.php un cron_build.php.
    $dzivs = admin_pid_dzivs($pid, 'registrs/build/');
    if ($dzivs === true || ($dzivs === null && !is_file($lockFile))) return null;

    $posms = (string)($st['stage'] ?? '?');
    $sekas = match ($posms) {
        'swap'    => 'Nomaiņa varēja palikt daļēja — pārbaudīt dzīvās DB un palaist būvi vēlreiz',
        'sitemap' => 'Dzīvās DB jau nomainītas; sitemap/IndexNow nepabeigti',
        default   => 'Dzīvās DB nav mainītas, paliek iepriekšējās būves dati',
    };
    $msg = "Būves process (pid $pid) pazuda posmā «{$posms}» bez beigu ieraksta; pēdējā stāvokļa maiņa "
         . ($st['updated'] ?? '?') . ". Visticamāk nokauts no ārpuses (hostinga procesu limits). $sekas.";
    $jauns = ['stage' => 'died', 'status' => 'error', 'updated' => date('Y-m-d H:i:s'), 'pid' => $pid,
              'died_stage' => $posms, 'last_update' => $st['updated'] ?? null, 'error' => $msg];
    // Vēlreiz tieši pirms rakstīšanas: ja tikmēr sākusies jauna būve, tās stāvokli nepārrakstām.
    $json = json_encode($jauns, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) return null;   // citādi stāvokļa fails paliktu tukšs
    if ($lasit() !== $st) return null;
    if (@file_put_contents($stateFile, $json, LOCK_EX) === false) return null;
    if ($logFile !== null) {
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] !!! BŪVE NOMIRA: $msg\n", FILE_APPEND | LOCK_EX);
    }
    ($zinot ?? 'applog_event')('ERROR', $sadala, 'buve_nomira', $msg, true);   // $zinot — testiem
    return $jauns;
}

/**
 * Visas darbu stāvokļa datnes ('state_file') vienā piegājienā — dispečeram.
 * @return list<array> atzītās mirušās būves (jaunie stāvokļi)
 */
function admin_parbaudit_mirusas_buves(): array {
    $out = []; $redzets = [];
    foreach (admin_all_jobs() as $j) {
        $f = (string)($j['state_file'] ?? '');
        if ($f === '' || isset($redzets[$f]) || empty($j['own_lock'])) continue;
        $redzets[$f] = true;
        $m = admin_buve_nomirusi($f, (string)$j['own_lock'], $j['state_log'] ?? null, (string)$j['section']);
        if ($m !== null) $out[] = $m;
    }
    return $out;
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
              'KONKURSI_DB_PATH', 'GRANTI_DB_PATH', 'GRANTI_TULK_DB', 'REG_TZ',
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
 * Čaulas komandu GRUPAS izvēlētajiem darbiem — tīra funkcija (bez palaišanas), lai to var testēt.
 * Grupas iekšienē soļus savieno '&&', grupas savā starpā admin_launch() savieno ar ';'.
 * @return list<string>
 */
function admin_launch_grupas(array $jobIds, array $all, string $php, string $run): array {
    $groups = [];
    $seen = [];
    // SOĻI, KAS PIEDER IZVĒLĒTAI ĶĒDEI, ATSEVIŠĶI NETIEK PALAISTI (recenzents 2026-09-17). Ar ['granti.build','granti.diena']
    // $seen izņēma būvi no ķēdes, ķēdē palika viena tulkošana bez '&&' un bez --kede, un maksas solis sākās arī tad,
    // ja būve krita vai tika izlaista. Ķēde savu soli palaidīs pati, pareizajā secībā.
    $kedesSoli = [];
    foreach ($jobIds as $id)
        if ((($all[$id]['runner'] ?? '') === 'chain'))
            foreach ((array)($all[$id]['chain'] ?? []) as $s) if (isset($all[$s])) $kedesSoli[$s] = true;
    foreach ($jobIds as $id) {
        $j = $all[$id] ?? null;
        if (!$j) continue;
        $irKede = (($j['runner'] ?? '') === 'chain');
        if (!$irKede && isset($kedesSoli[$id])) continue;
        $steps = $irKede
            ? array_values(array_filter((array)($j['chain'] ?? []), fn($s) => isset($all[$s])))
            : [$id];
        $steps = array_values(array_filter($steps, function ($s) use (&$seen) {
            if (isset($seen[$s])) return false;   // vienu darbu divreiz nepalaižam
            $seen[$s] = true;
            return true;
        }));
        if (!$steps) continue;
        // ĶĒDES SOLIS DABŪ "--kede": ar to job_run.php "jau darbojas" gadījumā iziet ar 3, nevis 0, un '&&' ķēdi aptur.
        // 2026-09-17 audits: granti.diena = būve && tulkošana; ja būve jau gāja citā procesā, job_run izlaida soli ar 0,
        // un MAKSAS tulkošana sākās pret datubāzi, ko otrs process tūlīt aizstās. Atsevišķam darbam (bez ķēdes) nekas nemainās.
        $kede = $irKede ? ' --kede' : '';   // pēc darba VEIDA, ne pēc atlikušo soļu skaita (dedup var atstāt vienu soli)
        $groups[] = implode(' && ', array_map(fn($s) => "$php $run " . admin_sh_arg($s) . $kede, $steps));
    }
    return $groups;
}

/**
 * Čaulas komanda, kas $script palaiž atdalītu fonā — tīra funkcija (testējama bez palaišanas).
 *
 * MANTOTIE DESKRIPTORI CIET (2026-10-22). Hostinger cron rindu ietin
 * `flock -w 1 /tmp/cron_lock_<id> timeout -s 9 1800 php cron/dispatch.php`; flock slēdzenes
 * deskriptors (3) tiek mantots caur php un setsid līdz pašai būvei. Mērīts serverī: kamēr fona
 * darbs iet, slēdzene paliek aizņemta, tātad katrs nākamais dispečera izsaukums krīt pie
 * `flock -w 1` un grafiks stāv visu būves laiku (arī mirušas būves atpazīšana). `exec N>&-`
 * aizvērtam deskriptoram ir tukša darbība; dash/sh atbalsta tikai vienciparu N, ar to pietiek.
 */
function admin_launch_sh(string $script): string {
    $inner = 'exec 3>&- 4>&- 5>&- 6>&- 7>&- 8>&- 9>&-; '
           . 'if command -v setsid >/dev/null 2>&1; then setsid /bin/sh -c ' . admin_sh_arg($script) . ' >/dev/null 2>&1 </dev/null &'
           . ' elif command -v nohup >/dev/null 2>&1; then nohup /bin/sh -c ' . admin_sh_arg($script) . ' >/dev/null 2>&1 </dev/null &'
           . ' else /bin/sh -c ' . admin_sh_arg($script) . ' >/dev/null 2>&1 </dev/null & fi';
    return '/bin/sh -c ' . admin_sh_arg($inner);
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
    $groups = admin_launch_grupas($jobIds, admin_all_jobs(), admin_sh_arg(admin_php_bin()), admin_sh_arg(admin_root() . '/lib/job_run.php'));
    if (!$groups) return [null, 'Nav neviena derīga darba.'];

    // Slēdzeni, izvades uztveršanu un žurnalēšanu dara job_run.php — čaulā palikusi
    // tikai secība un fona atdalīšana. Sk. lib/job_run.php par to, kāpēc `flock`
    // utilīta netiek lietota.
    $sh = admin_launch_sh(implode('; ', $groups));

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
