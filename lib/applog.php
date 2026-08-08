<?php
/**
 * lib/applog.php — vietnes notikumu žurnāls (visām sadaļām kopīgs).
 *
 * MĒRĶIS: pēc kļūmes varēt atrast cēloni — kas notika, kurā sadaļā, cik ilgi,
 * ar kādu statusu. Bez tā vienīgā pēda ir servera error_log bez konteksta.
 *
 * BUDŽETS. Prasība ir "maksimāli plaši, bet ≤ 50 KB dienā". Pie ~250 apmeklētājiem
 * dienā vietne apkalpo ~5 000 PHP pieprasījumu (statiskos failus Apache atdod pats,
 * PHP tos neredz — tāpēc tie šeit neparādās vispār). Pilna rinda katram būtu ~1 MB
 * dienā, t.i. 20× pāri budžetam. Tāpēc divi slāņi:
 *
 *   1. APKOPOJUMS — parastā satiksme neaiziet rindās, bet stundu skaitītājos
 *      (sadaļa × galapunkts × statusa klase → skaits, vidējais/maks. ilgums).
 *      Viena stunda ≈ 10–20 rindas × ~70 B ≈ 1 KB → ~24 KB dienā ar visu.
 *   2. DETAĻAS — pilna rinda TIKAI tam, kas ir interesants: HTTP 5xx, PHP kļūdas
 *      un brīdinājumi, neapstrādāti izņēmumi, lēni pieprasījumi, darbu (job) sākums/
 *      beigas/kļūme, admin darbības un neveiksmīgi piekļuves mēģinājumi.
 *
 * Kad fails tuvojas limitam, detaļu rindas tiek apturētas (skaitītāji turpinās), lai
 * 50 KB nekad netiktu pārsniegti. Reizi dienā jauns fails; vecāki par 3 gadiem — dzēsti.
 *
 * PRIVĀTUMS: IP adrese ir personas dati, tāpēc glabājam anonimizētu (IPv4 pēdējais
 * oktets, IPv6 pēdējie 80 biti → 0) — pietiek tīkla/ISP līmeņa diagnostikai, bet
 * konkrētu cilvēku neidentificē. Sk. arī registrs/ai_cache GDPR piezīmi htaccess.
 *
 * LIETOŠANA (lapas sākumā, pirms izvades):
 *     require_once __DIR__ . '/lib/applog.php';
 *     applog_boot('konkursi');
 * Pārējais notiek pats (shutdown āķis). No CLI darbiem:
 *     applog_job('konkursi.sync', 'start'|'ok'|'error', ['ms'=>..., 'msg'=>...]);
 */
declare(strict_types=1);

// LAIKA ZONA. Žurnāls ir bezjēdzīgs, ja laiki nesakrīt ar pārējiem žurnāliem — PHP
// noklusējums šeit ir UTC, t.i. 3 h nobīde pret Rīgu. Projektā tam jau ir vienots
// risinājums (reg_init_timezone), un tas ir jāizsauc ARĪ šeit: applog strādā ne tikai
// lapās (kur to ievelk head.php), bet arī CLI darbos un cron dispečerā, kur to neviens
// cits neiestatītu. Bez šī dienas fails mainītos 03:00 pēc Rīgas laika, nevis pusnaktī.
$__applog_tz = dirname(__DIR__) . '/registrs/lib/timezone.php';
if (is_file($__applog_tz)) {
    require_once $__applog_tz;
} elseif (date_default_timezone_get() === 'UTC') {
    date_default_timezone_set('Europe/Riga');
}
unset($__applog_tz);

const APPLOG_MAX_BYTES      = 50 * 1024;  // cietais dienas faila griests
const APPLOG_DETAIL_RESERVE = 5 * 1024;   // pēdējie 5 KB rezervēti apkopojumam/kļūmēm
// Pašā beigās vienmēr jāatliek vieta brīdinājumam par nogriešanu. Bez šīs rezerves
// pie ļoti lielas slodzes fails vienkārši apstātos, un no malas nebūtu redzams, vai
// tiešām nekas nenotika, vai arī ieraksti tika izmesti — tas ir bīstamākais variants.
const APPLOG_MARKER_RESERVE = 256;
const APPLOG_KEEP_DAYS      = 1095;       // 3 gadi
const APPLOG_SLOW_MS        = 2000;       // virs tā pieprasījums saņem savu rindu

function applog_dir(): string  { return dirname(__DIR__) . '/log'; }
function applog_file(?string $day = null): string { return applog_dir() . '/' . ($day ?? date('Y-m-d')) . '.log'; }
function applog_state_db(): string { return applog_dir() . '/state.sqlite'; }

/** Iekšējais stāvoklis (viena pieprasījuma laikā). */
function &applog_ctx(): array {
    static $ctx = ['on' => false, 'section' => '', 'endpoint' => '', 't0' => 0.0, 'notes' => [], 'worst' => 0];
    return $ctx;
}

/**
 * IP anonimizācija: IPv4 → a.b.c.0, IPv6 → pirmie 48 biti.
 * Neatgriezeniski, bet saglabā tīkla piederību, kas diagnostikā ir tas, kas vajadzīgs.
 */
function applog_anon_ip(string $ip): string {
    if ($ip === '') return '-';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $p = explode('.', $ip); $p[3] = '0';
        return implode('.', $p);
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // Baitu līmenī, nevis pa kolu grupām: saīsinātās formas ("::1", "fe80::1")
        // pēc explode(':') dod tukšus elementus, un rezultāts sanāca nederīgs ("::1::").
        // inet_pton normalizē uz 16 baitiem, tāpēc maskēšana ir viennozīmīga.
        $bin = @inet_pton($ip);
        if ($bin !== false && strlen($bin) === 16) {
            $masked = @inet_ntop(substr($bin, 0, 6) . str_repeat("\0", 10));
            if ($masked !== false) return $masked;
        }
        return '-';
    }
    return '-';
}

/** Klienta IP (aiz proxy — X-Forwarded-For pirmā vērtība). */
function applog_client_ip(): string {
    $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if ($first !== '') return $first;
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Galapunkta apzīmējums ar ZEMU kardinalitāti — skripts + darbība, BEZ parametru
 * vērtībām. Ar vērtībām katrs meklējums kļūtu par savu rindu un apkopojums izjuktu.
 */
function applog_endpoint(): string {
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'cli'));
    $action = (string)($_GET['action'] ?? '');
    if ($action !== '' && preg_match('/^[a-z_]{1,20}$/', $action)) return $script . '?' . $action;
    return $script;
}

/** Statusa klase (2xx/3xx/4xx/5xx) — apkopojuma dimensija. */
function applog_status_class(int $code): string {
    return $code >= 500 ? '5xx' : ($code >= 400 ? '4xx' : ($code >= 300 ? '3xx' : '2xx'));
}

/** Stāvokļa DB (skaitītāji starp izskalošanām). Atgriež null, ja neizdodas — žurnāls nekad nedrīkst lauzt lapu. */
function applog_db(): ?PDO {
    static $pdo = null, $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    try {
        if (!is_dir(applog_dir())) @mkdir(applog_dir(), 0775, true);
        $pdo = new PDO('sqlite:' . applog_state_db(), null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout=3000');
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA synchronous=NORMAL');
        $pdo->exec('CREATE TABLE IF NOT EXISTS agg (
            day TEXT NOT NULL, hour INTEGER NOT NULL, section TEXT NOT NULL,
            endpoint TEXT NOT NULL, cls TEXT NOT NULL,
            n INTEGER NOT NULL DEFAULT 0, ms_sum INTEGER NOT NULL DEFAULT 0,
            ms_max INTEGER NOT NULL DEFAULT 0, ips TEXT NOT NULL DEFAULT "",
            PRIMARY KEY (day, hour, section, endpoint, cls))');
        $pdo->exec('CREATE TABLE IF NOT EXISTS meta (k TEXT PRIMARY KEY, v TEXT)');
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

function applog_meta_get(PDO $pdo, string $k): ?string {
    $st = $pdo->prepare('SELECT v FROM meta WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string)$v;
}

function applog_meta_set(PDO $pdo, string $k, string $v): void {
    $st = $pdo->prepare('INSERT INTO meta(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v');
    $st->execute([$k, $v]);
}

/**
 * Pievieno rindu dienas failam, ja budžets atļauj.
 * $force — kļūmju rindas, kas tiek rakstītas arī rezerves zonā (bet ne pāri griestiem).
 */
function applog_append(string $line, bool $force = false): void {
    try {
        $f = applog_file();
        if (!is_dir(applog_dir())) @mkdir(applog_dir(), 0775, true);
        $newDay = !is_file($f);
        $size = $newDay ? 0 : (int)@filesize($f);

        // Divi sliekšņi: parastās rindas apstājas agrāk, atstājot pēdējos kilobaitus
        // apkopojumam un kļūmēm; brīdinājuma rindai atlicināta atsevišķa aste.
        $hard  = APPLOG_MAX_BYTES - APPLOG_MARKER_RESERVE;
        $limit = $force ? $hard : ($hard - APPLOG_DETAIL_RESERVE);
        if ($size + strlen($line) > $limit) {
            // Vienreiz dienā atzīmē, ka budžets sasniegts — klusums citādi būtu
            // nešķirams no "nekas nenotika".
            $pdo = applog_db();
            if ($pdo) {
                $flag = 'trunc:' . date('Y-m-d');
                if (applog_meta_get($pdo, $flag) === null) {
                    applog_meta_set($pdo, $flag, '1');
                    $note = applog_line('WARN', 'applog', 'budzets',
                        'Dienas limits (' . APPLOG_MAX_BYTES . ' B) sasniegts — turpmākās rindas izlaistas; '
                        . 'skaitītāji log/state.sqlite turpinās');
                    // Rezerve garantē, ka šī rinda ietilpst.
                    @file_put_contents($f, $note, FILE_APPEND | LOCK_EX);
                }
            }
            return;
        }

        if ($newDay) {
            @file_put_contents($f, "# saraksts.lv notikumu žurnāls " . date('Y-m-d')
                . " · laiks=Europe/Riga · IP anonimizēts · limits " . APPLOG_MAX_BYTES . " B\n", FILE_APPEND | LOCK_EX);
            applog_prune();   // jauna diena → izmet vecākus par APPLOG_KEEP_DAYS
        }
        @file_put_contents($f, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { /* žurnāls nekad nelauž lapu */ }
}

/** Vienota rindas forma: laiks, līmenis, sadaļa, notikums, teksts. */
function applog_line(string $level, string $section, string $event, string $msg): string {
    $msg = preg_replace('/\s+/u', ' ', trim($msg)) ?? '';
    if (mb_strlen($msg, 'UTF-8') > 300) $msg = mb_substr($msg, 0, 300, 'UTF-8') . '…';
    return sprintf("%s %-5s %-11s %-14s %s\n", date('H:i:s'), $level, $section, $event, $msg);
}

/** Publiskais notikuma ieraksts (admin darbības, drošība, darbi). */
function applog_event(string $level, string $section, string $event, string $msg, bool $force = false): void {
    applog_append(applog_line($level, $section, $event, $msg), $force);
}

/** CLI darba (job) notikums — sākums/beigas/kļūme. Vienmēr detaļās. */
function applog_job(string $job, string $status, array $extra = []): void {
    $bits = [];
    foreach ($extra as $k => $v) $bits[] = $k . '=' . (is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
    $level = $status === 'error' ? 'ERROR' : ($status === 'start' ? 'INFO' : 'INFO');
    applog_event($level, 'job', $job, $status . ($bits ? ' ' . implode(' ', $bits) : ''), $status === 'error');
}

/** Vecāku par APPLOG_KEEP_DAYS dienu failu dzēšana. */
function applog_prune(): int {
    $cut = date('Y-m-d', time() - APPLOG_KEEP_DAYS * 86400);
    $n = 0;
    foreach ((array)@glob(applog_dir() . '/*.log') as $p) {
        $day = basename((string)$p, '.log');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) && $day < $cut) { @unlink($p); $n++; }
    }
    // Skaitītāju tabulā vecās dienas arī nav vajadzīgas.
    $pdo = applog_db();
    if ($pdo) {
        try {
            $st = $pdo->prepare('DELETE FROM agg WHERE day < ?');
            $st->execute([$cut]);
        } catch (Throwable $e) { /* nebūtiski */ }
    }
    return $n;
}

/**
 * Izskalo pabeigtās stundas apkopojumu dienas failā.
 * Sauc katrs pieprasījums, bet darbu izdara tikai pirmais pēc stundas maiņas
 * (meta 'flushed' atzīme + LOCK_EX uz faila raksta).
 */
function applog_flush_due(PDO $pdo): void {
    $nowKey = date('Y-m-d H');
    $last = applog_meta_get($pdo, 'flushed');
    if ($last === $nowKey) return;
    applog_meta_set($pdo, 'flushed', $nowKey);   // rezervē darbu sev

    $st = $pdo->prepare('SELECT day, hour, section, endpoint, cls, n, ms_sum, ms_max, ips
                         FROM agg WHERE day || " " || printf("%02d", hour) < ?
                         ORDER BY day, hour, section, endpoint, cls');
    $st->execute([$nowKey]);
    $rows = $st->fetchAll();
    if (!$rows) return;

    $byHour = [];
    foreach ($rows as $r) $byHour[$r['day'] . ' ' . sprintf('%02d', $r['hour'])][] = $r;

    foreach ($byHour as $key => $rs) {
        [$day, $h] = explode(' ', $key);
        $total = 0; $uniq = [];
        foreach ($rs as $r) {
            $total += (int)$r['n'];
            foreach (array_filter(explode(',', (string)$r['ips'])) as $ip) $uniq[$ip] = 1;
        }
        applog_append_to($day, applog_line('STAT', 'satiksme', $h . ':00',
            "pieprasījumi=$total tīkli=" . count($uniq)));
        foreach ($rs as $r) {
            $n = (int)$r['n'];
            $avg = $n > 0 ? (int)round((int)$r['ms_sum'] / $n) : 0;
            applog_append_to($day, applog_line('STAT', (string)$r['section'],
                $h . ':00', sprintf('%-22s %s n=%d vid=%dms maks=%dms',
                    $r['endpoint'], $r['cls'], $n, $avg, (int)$r['ms_max'])));
        }
    }
    try {
        $del = $pdo->prepare('DELETE FROM agg WHERE day || " " || printf("%02d", hour) < ?');
        $del->execute([$nowKey]);
    } catch (Throwable $e) { /* nebūtiski */ }
}

/** Pievieno rindu KONKRĒTAS dienas failam (stundas izskalošanai pāri pusnaktij). */
function applog_append_to(string $day, string $line): void {
    if ($day === date('Y-m-d')) { applog_append($line, true); return; }
    try {
        $f = applog_file($day);
        if (!is_dir(applog_dir())) @mkdir(applog_dir(), 0775, true);
        $size = is_file($f) ? (int)@filesize($f) : 0;
        if ($size + strlen($line) <= APPLOG_MAX_BYTES) @file_put_contents($f, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { /* žurnāls nekad nelauž lapu */ }
}

/** Skaitītāja palielināšana (parastā satiksme). */
function applog_bump(string $section, string $endpoint, string $cls, int $ms, string $net): void {
    $pdo = applog_db();
    if (!$pdo) return;
    try {
        applog_flush_due($pdo);
        // UZMANĪBU: labajā pusē lieto TIKAI excluded.* jaunajai vērtībai un kailu
        // kolonnas vārdu esošajai. Ar saistīto parametru funkcijas iekšienē
        // (`MAX(ms_max, :ms)`) SQLite kailo `ms_max` atrisina uz JAUNO rindu, tāpēc
        // maksimums sanāca vienāds ar pēdējo vērtību (pārbaudīts: 50,300,120 → 120,
        // nevis 300). Ar `excluded.ms_max` rezultāts ir pareizs.
        //
        // ips: distinktie /24 tīkli stundā (unikālo apmeklētāju aplēsei); virs ~700
        // rakstzīmēm vairs nekrāj, lai viena rinda nekļūtu par kilobaitu.
        $st = $pdo->prepare('INSERT INTO agg (day,hour,section,endpoint,cls,n,ms_sum,ms_max,ips)
                             VALUES (:d,:h,:s,:e,:c,1,:ms,:ms,:ip)
                             ON CONFLICT(day,hour,section,endpoint,cls) DO UPDATE SET
                               n = n + 1,
                               ms_sum = ms_sum + excluded.ms_sum,
                               ms_max = MAX(ms_max, excluded.ms_max),
                               ips = CASE
                                   WHEN length(ips) > 700 THEN ips
                                   WHEN instr("," || ips || ",", "," || excluded.ips || ",") > 0 THEN ips
                                   ELSE ips || CASE WHEN ips = "" THEN "" ELSE "," END || excluded.ips END');
        $st->execute([':d' => date('Y-m-d'), ':h' => (int)date('G'), ':s' => $section,
                      ':e' => $endpoint, ':c' => $cls, ':ms' => $ms, ':ip' => $net]);
    } catch (Throwable $e) { /* nebūtiski */ }
}

/** PHP kļūdu/brīdinājumu pārtvērējs — katrs saņem savu rindu. */
function applog_error_handler(int $no, string $str, string $file = '', int $line = 0): bool {
    // @-apslāpētās kļūdas IZLAIŽAM. Kodā ir simtiem apzinātu `@mkdir`, `@filemtime`,
    // `@file_get_contents` izsaukumu, kur kļūme ir gaidīta un apstrādāta ar atgriezto
    // vērtību. PHP 8 custom apstrādātāju sauc arī tiem, tāpēc bez šīs pārbaudes
    // žurnāls piepildījās ar "mkdir(): File exists" katrā lapas ielādē un 50 KB
    // budžets aizietu dažās stundās uz troksni. Tas pats nosacījums atmet arī līmeņus,
    // kas atslēgti ar error_reporting().
    if (!(error_reporting() & $no)) return false;
    $ctx = &applog_ctx();
    $names = [E_WARNING => 'WARN', E_NOTICE => 'NOTE', E_USER_WARNING => 'WARN',
              E_USER_NOTICE => 'NOTE', E_DEPRECATED => 'NOTE', E_USER_DEPRECATED => 'NOTE',
              E_USER_ERROR => 'ERROR', E_RECOVERABLE_ERROR => 'ERROR'];
    $lvl = $names[$no] ?? 'WARN';
    if ($lvl === 'ERROR') $ctx['worst'] = 2; elseif ($ctx['worst'] < 1) $ctx['worst'] = 1;
    applog_event($lvl, $ctx['section'] ?: 'php', 'php-kluda',
        $str . ' @ ' . basename($file) . ':' . $line, $lvl === 'ERROR');
    return false;   // ļauj arī parastajam PHP apstrādātājam nostrādāt
}

/** Neapstrādāts izņēmums — vienmēr detaļās. */
function applog_exception_handler(Throwable $e): void {
    $ctx = &applog_ctx();
    $ctx['worst'] = 2;
    applog_event('FATAL', $ctx['section'] ?: 'php', 'iznemums',
        get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), true);
}

/** Pieprasījuma noslēgums: skaitītājs + rinda, ja bija kas interesants. */
function applog_shutdown(): void {
    $ctx = &applog_ctx();
    if (!$ctx['on']) return;
    $ctx['on'] = false;   // dubulta izsaukuma aizsardzība
    try {
        $ms   = (int)round((microtime(true) - $ctx['t0']) * 1000);
        $code = http_response_code();
        $code = is_int($code) ? $code : 200;
        $cls  = applog_status_class($code);
        $net  = applog_anon_ip(applog_client_ip());

        // Liktenīga kļūda (parse/fatal) shutdown brīdī — error_get_last to atklāj.
        $last = error_get_last();
        if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $ctx['worst'] = 2;
            applog_event('FATAL', $ctx['section'], 'php-fatal',
                $last['message'] . ' @ ' . basename((string)$last['file']) . ':' . (int)$last['line'], true);
        }

        applog_bump($ctx['section'], $ctx['endpoint'], $cls, $ms, $net);

        // Detaļu rinda tikai tad, ja ir ko stāstīt.
        $why = null;
        if ($cls === '5xx')            $why = 'statuss=' . $code;
        elseif ($ctx['worst'] >= 2)    $why = 'kluda pieprasijuma laika';
        elseif ($ms >= APPLOG_SLOW_MS) $why = 'lens';
        if ($why !== null) {
            applog_event($cls === '5xx' || $ctx['worst'] >= 2 ? 'ERROR' : 'WARN',
                $ctx['section'], 'pieprasijums',
                sprintf('%s %s %d %dms tikls=%s %s', $_SERVER['REQUEST_METHOD'] ?? '-',
                    $ctx['endpoint'], $code, $ms, $net, $why),
                $cls === '5xx' || $ctx['worst'] >= 2);
        }
    } catch (Throwable $e) { /* žurnāls nekad nelauž lapu */ }
}

/**
 * Ieslēdz žurnalēšanu šim pieprasījumam. Droši saukt vairākkārt (pirmais uzvar) un
 * droši saukt no jebkuras sadaļas — ja kaut kas neizdodas, lapa strādā tāpat.
 */
function applog_boot(string $section): void {
    $ctx = &applog_ctx();
    if ($ctx['on']) return;
    try {
        $ctx['on']       = true;
        $ctx['section']  = preg_replace('/[^a-z0-9_-]/', '', strtolower($section)) ?: 'nezinams';
        $ctx['endpoint'] = applog_endpoint();
        $ctx['t0']       = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $ctx['worst']    = 0;
        set_error_handler('applog_error_handler');
        set_exception_handler('applog_exception_handler');
        register_shutdown_function('applog_shutdown');
    } catch (Throwable $e) { $ctx['on'] = false; }
}
