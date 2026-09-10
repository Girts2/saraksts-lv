<?php
/**
 * granti/bin/build.php — grantu sadaļas DATU BŪVE (TIKAI CLI).
 *
 * Savāc atvērtos un gaidāmos grantu konkursus un ieraksta granti/db/granti.sqlite.
 * Palaiž cilvēks vai cron: `granti/bin/cron_build.php` ik dienu caur cron/dispatch.php
 * (darbs `granti.build` sarakstā lib/admin_jobs.php).
 *
 * Avoti:
 *   1) ES Finansējuma un konkursu portāls (SEDIA):
 *      - bulk grantsTenders.json (~116 MB; type=1 granti, statuss Open/Forthcoming)
 *      - topicDetails/{id-mazie-burti}.json — budžeti (budgetOverviewJSONItem);
 *        kešots granti/data/cache/topics/, tāpēc atkārtota būve neko nevelk.
 *   2) SIF projektu konkursi: sif.map.gov.lv/data/konkurss_public_list (limit max 100).
 *
 * Lietojums: php granti/bin/build.php [--bulk=CEĻŠ] [--skip-eu] [--skip-sif] [--skip-details]
 *                                     [--tulkot] [--tulkot-limit=N] [--bez-tulkojumiem]
 *
 * SLĒDZENE UN STĀVOKLIS. Būve tur savu neblokējošu flock uz granti/data/build.lock, tāpēc
 * divas palaišanas (cron + cilvēks panelī) nekad neiet vienlaikus. Gaita rakstās
 * granti/data/build_state.json un granti/data/build.log; STOP karodziņš pārtrauc garās
 * cilpas. Slēdzene ir arī iemesls, kāpēc darba ierakstā admin_jobs.php ir 'own_lock' —
 * bez tā job_run.php paņemtu to pašu failu un bērns uzskatītu, ka būve jau iet.
 *
 * TULKOŠANA. Būve vienmēr sasaista tekstus ar tulkojumu kešu (granti/db/tulkojumi.sqlite)
 * un aizpilda lv_* kolonnas ar to, kas kešā JAU ir; jaunos tekstus tā ieliek rindā. Ar --tulkot
 * turpat uz vietas iztulko rindā gaidošos, tāpēc jauns konkurss ir latviski tajā pašā palaišanā,
 * kurā tas parādījās. Kešs ir ATSEVIŠĶS fails: šī būve raksta tukšā pagaidu failā un pārceļ to
 * pāri dzīvajai DB, tāpēc kolonnās glabāts tulkojums izzustu katrā palaišanā un tiktu pirkts no
 * jauna. Sk. granti_tulkojumi.php faila galvu.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Laika zonu uzstāda lib/config.php (PHP noklusējums ir UTC, un žurnāls būtu 3 h atpakaļ).
ini_set('memory_limit', '2048M');   // mērīts: 130 MB JSON -> 350 MB maksimums; rezerve augšanai
// Koplietots hostings ini_set var klusi ignorēt (plāna griesti). Beigu žurnāla rinda rāda
// mērīto maksimumu un limitu; te brīdinām, ja limits ir zem mērītā ar rezervi.
$__lim = (string)ini_get('memory_limit');
$__limB = preg_match('/^(\d+)([KMG])?$/i', $__lim, $__m)
    ? (int)$__m[1] * (1024 ** (['K' => 1, 'M' => 2, 'G' => 3][strtoupper($__m[2] ?? '')] ?? 0)) : -1;
if ($__limB > 0 && $__limB < 512 * 1024 * 1024) fwrite(STDERR, "⚠ memory_limit ir $__lim — būvei vajag vismaz 512M (mērīts 350 MB).\n");
set_time_limit(0);                  // cron palaišana iet pāri noklusējuma 30 s
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/config.php';
$CACHE_DIR = granti_cache_dir();
$DB_FILE   = granti_db_path();
const GR_UA = 'Mozilla/5.0 (compatible; Saraksts.lv/1.0; +https://saraksts.lv)';
const EU_BULK_URL   = 'https://ec.europa.eu/info/funding-tenders/opportunities/data/referenceData/grantsTenders.json';
const EU_TOPIC_URL  = 'https://ec.europa.eu/info/funding-tenders/opportunities/data/topicDetails/%s.json';
const EU_PORTAL_URL = 'https://ec.europa.eu/info/funding-tenders/opportunities/portal/screen/opportunities/topic-details/%s';
const SIF_LIST_URL  = 'https://sif.map.gov.lv/data/konkurss_public_list?offset=%d&limit=%d';
const SIF_ITEM_URL  = 'https://sif.map.gov.lv/contests/%d';
const SIF_APPLY_URL = 'https://sif.map.gov.lv/manager/mani-pieteikumi/new?contest=%d';
const SIF_PAGE      = 100;   // API max — 101 atdod HTTP 400
const BULK_MAX_AGE_H = 20;   // vecāku kešoto bulk failu velk no jauna (portāls to pārģenerē reizi dienā)

@mkdir($CACHE_DIR . '/topics', 0775, true);
@mkdir(dirname($DB_FILE), 0775, true);
@mkdir(granti_data_dir(), 0775, true);

$opts = getopt('', ['bulk::', 'skip-eu', 'skip-sif', 'skip-details',
                    'tulkot', 'tulkot-limit::', 'bez-tulkojumiem']);
// getopt ar "::" pieņem TIKAI --karodzins=vertiba. Ar atstarpi vērtība ir false, un
// (int)false = 0 nozīmēja "bez ierobežojuma" — pretēji tam, ko cilvēks gribēja.
foreach (['bulk', 'tulkot-limit'] as $__o) {
    if (array_key_exists($__o, $opts) && $opts[$__o] === false) {
        fwrite(STDERR, "Karodziņam --$__o vajag vērtību ar vienādības zīmi: --$__o=VĒRTĪBA\n");
        exit(2);
    }
}

/**
 * Žurnāls: gan uz ekrāna (cilvēkam), gan failā (panelim un cron pēdām).
 * LOCK_EX, jo topicDetails vilkšana un tulkošana raksta no vienas un tās pašas rindas,
 * bet fails var būt atvērts arī panelim. Pašgriešana, lai gada cron nesaaudzē gigabaitu.
 */
function gr_log(string $m): void {
    $rinda = date('Y-m-d H:i:s') . '  ' . $m . "\n";
    echo $rinda;
    $f = granti_log_path();
    if (is_file($f) && filesize($f) > 500 * 1024) {
        $saturs = (string)file_get_contents($f);
        file_put_contents($f, "... (nogriezts) ...\n" . substr($saturs, -200 * 1024), LOCK_EX);
    }
    @file_put_contents($f, $rinda, FILE_APPEND | LOCK_EX);
}

/**
 * Gaitas stāvoklis panelim. Saplūdina ar esošo, lai daļēja atjaunināšana nenodzēstu
 * pārējos laukus; 'updated' vienmēr klāt, citādi nevar atšķirt iestrēgušu no lēnas.
 */
function gr_state(array $patch): void {
    $f = granti_state_path();
    $cur = [];
    if (is_file($f)) { $j = json_decode((string)file_get_contents($f), true); if (is_array($j)) $cur = $j; }
    $cur = $patch + $cur;
    $cur['updated'] = date('Y-m-d H:i:s');
    @file_put_contents($f, json_encode($cur, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/** Vai panelis lūdzis apstāties? Pārbauda garo cilpu sākumā. */
function gr_stop_requested(): bool { return is_file(granti_stop_flag()); }

/**
 * Pieteikšanās saite no topicDetails lauka links[].
 *
 * KĀPĒC NOLASA, NEVIS BŪVĒ. Saite ved uz citu domēnu nekā tēmas lapa:
 * .../submission/manage/screen/submission/create-draft/{N}?topic={ID}. Numurs {N} nav
 * atvasināms ne no viena mums pieejama identifikatora — mērīts uz visām 367 saitēm:
 * sakritību ar ccm2Id, callccm2Id un topicMGAs[].id ir 0, un diapazoni nesakrīt principiāli.
 *
 * KĀPĒC TIKAI TAD, JA SAITE IR TIEŠI VIENA. Trim tēmām (MSCA DN/PF, RAISE) portāls atdod
 * 2-3 saites ar VIENĀDU granta veidu, tāpēc pareizo izvēlēties nevar — tur cilvēkam jāizvēlas
 * pašam tēmas lapā. Un četrām Euratom tēmām statuss ir "Open", bet saites nav vispār, tāpēc
 * vārtu nedrīkst likt uz statusa; jāskatās pats lauks.
 *
 * Adrese nāk no ĀRĒJA JSON un lapā kļūst par href, tāpēc to pārbauda: tikai https,
 * tikai ec.europa.eu, tikai iesniegšanas ceļš.
 */
function gr_pieteiksanas_saite(array $td): ?string {
    $links = $td['TopicDetails']['links'] ?? $td['links'] ?? [];
    if (!is_array($links)) return null;
    $urls = [];
    foreach ($links as $l) {
        $u = is_array($l) ? (string)($l['url'] ?? '') : '';
        if ($u === '') continue;
        if (!preg_match('~^https://ec\\.europa\\.eu/[^\s"\'<>]*/submission/manage/screen/submission/create-draft/\\d+~', $u)) continue;
        $urls[$u] = true;
    }
    $urls = array_keys($urls);
    return count($urls) === 1 ? $urls[0] : null;
}

function gr_http_get(string $url, int $timeout = 60, ?int &$codeOut = null): ?string {
    // ĶERMENI KRĀJ PAŠI (WRITEFUNCTION), NE RETURNTRANSFER. ec.europa.eu CDN OpenSSL 3
    // klientiem pēc ~60 pieprasījumiem sāk aizvērt TLS bez close_notify: dati atnāk VISI,
    // bet curl beigās saka "SSL_read: unexpected eof" (kods 56 vai 18), un ar RETURNTRANSFER
    // curl_exec atdod false — pilns ķermenis aiziet zudumā. Mērīts 2026-09-10: 580 no 633
    // tēmu pieprasījumiem; lokāli tos izglāba LibreSSL sistēmas curl, kāda Linux serverī
    // NAV. Tagad ķermeni paturam un pieņemam, ja tas ir pilnīgs JSON — nogriezts JSON
    // nekad neparsējas, tāpēc tas ir precīzs pilnīguma tests bez atkarības no bibliotēkas.
    $buf = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_ENCODING => '', CURLOPT_USERAGENT => GR_UA,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_WRITEFUNCTION => static function ($c, string $d) use (&$buf): int { $buf .= $d; return strlen($d); },
    ]);
    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $codeOut = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($codeOut === 200 && $buf !== '') {
        if ($ok === true) return $buf;
        if (gr_json_pilns($buf)) {
            $GLOBALS['gr_izglabti'] = ($GLOBALS['gr_izglabti'] ?? 0) + 1;   // TLS pārrāvums, dati pilni
            return $buf;
        }
        gr_log("  · pārrāvums (curl $errno), ķermenis nepilns (" . strlen($buf) . " B) — mēģina sistēmas curl.");
    }
    unset($buf);
    // Serveris atbildēja GALĪGI (404, 500, 429): otrs pieprasījums caur sistēmas curl neko
    // nemainītu, tikai dubultotu slodzi un rakstītu "sistēmas curl izgāzās (kods 22)" katram 404.
    if ($ok === true) return null;

    // Otrā atkāpe — sistēmas curl. Uz macOS tas ir LibreSSL un iet cauri; Linux serverī tas
    // ir tas pats OpenSSL, un atkāpe var nepalīdzēt — tāpēc augšējais ceļš ir galvenais.
    // function_exists OBLIGĀTI: PHP 8 liegtu (disable_functions) funkciju IZŅEM no funkciju
    // tabulas, un izsaukums met Error "Call to undefined function" — @ to neapslāpē (pārbaudīts
    // ar php -d disable_functions=shell_exec). Bez sarga pirmais 404 tēmai nogalinātu būvi.
    $bin = (function_exists('shell_exec') && function_exists('exec'))
         ? trim((string)@shell_exec('command -v curl')) : '';
    if ($bin !== '') {
        // IZEJAS KODS IR JĀNOLASA. shell_exec() to nedod, tāpēc te ir exec(): nogriezts
        // pieprasījums atdod HTTP 200 ar NEPILNU ķermeni un izejas kodu 18 vai 56. -f pievieno
        // HTTP kļūdas koda pārbaudi arī curl pusē. Ja exec ir liegts, @ noklusina brīdinājumu
        // un $izvade paliek tukša — funkcija vienkārši atdod null.
        $rinda = sprintf('%s -sSf -m %d -w "\n%%{http_code}" -A %s %s 2>/dev/null',
            escapeshellcmd($bin), $timeout, escapeshellarg(GR_UA), escapeshellarg($url));
        $izvade = []; $rc = 0;
        @exec($rinda, $izvade, $rc);
        if ($rc === 0 && $izvade) {
            $codeOut = (int)array_pop($izvade);
            $b = implode("\n", $izvade);
            if ($codeOut === 200 && $b !== '' && gr_json_pilns($b)) {
                // Skaitām, cik reižu šī atkāpe izglāba: bez skaitļa nevar atšķirt "slazda šodien
                // nebija" no "atkāpe strādāja 300 reizes".
                $GLOBALS['gr_atkapes'] = ($GLOBALS['gr_atkapes'] ?? 0) + 1;
                return $b;
            }
        } elseif ($rc !== 0) {
            gr_log("  ! sistēmas curl izgāzās (kods $rc) — atbilde atmesta.");
        }
    }
    return null;
}

/**
 * Vai virkne ir PILNĪGS JSON dokuments. Mazus (< 4 MB) parsē pilnībā; lielajam bulk failam
 * (~130 MB) pilna parsēšana te būtu dubults darbs, tāpēc tam pietiek ar pareizu beigu
 * iekavu: nogriezta plūsma praktiski nekad nebeidzas tieši ar `]}}`. Pilnā parsēšana
 * notiek tūlīt pēc tam būvē, un tur bojāts fails tiek izmests no keša.
 */
function gr_json_pilns(string $s): bool {
    $t = rtrim($s);
    if ($t === '' || ($t[0] !== '{' && $t[0] !== '[')) return false;
    if (strlen($t) < 4 * 1024 * 1024) return json_decode($t) !== null || $t === 'null';
    return str_ends_with($t, '}') || str_ends_with($t, ']');
}

/** ms-laikzīmogs (int|string) -> 'Y-m-d' UTC vai null. */
function gr_ms_date($ms): ?string {
    if ($ms === null || $ms === '' ) return null;
    $s = (int)((float)$ms / 1000);
    return $s > 0 ? gmdate('Y-m-d', $s) : null;
}

/** No termiņu saraksta izvēlas tuvāko nākotnē (vai vēlāko, ja visi pagājuši). */
function gr_pick_deadline(array $dates): ?string {
    sort($dates);
    $today = date('Y-m-d');   // Rīgas diena, ne UTC
    foreach ($dates as $d) if ($d >= $today) return $d;
    return $dates ? end($dates) : null;
}

// ── DB (raksta pagaidu failā, beigās atomāri pārceļ) ─────────────────────────
// KARODZIŅU SEMANTIKA. Būve raksta TUKŠĀ pagaidu failā un beigās to atomāri pārceļ, tāpēc
// --skip-eu vai --skip-sif nozīmēja "izdzēst šo avotu no lapas", nevis "neatjaunot to":
// 720 ierakstu vietā palika 82. Tagad izlaistā avota ieraksti tiek PĀRNESTI no dzīvās DB.
// ═══ Slēdzene ════════════════════════════════════════════════════════════════
// NEBLOKĒJOŠA: otrā palaišana neiestājas rindā, bet paiet garām ar ziņu. Rokturis
// jātur mainīgajā VISU palaišanas laiku — ja tas izietu no tvēruma, PHP failu aizvērtu
// un slēdzene atbrīvotos pusceļā. Atbrīvo register_shutdown_function, lai tas notiktu
// arī pie exit() vai neķertas kļūdas.
$__lock = fopen(granti_lock_path(), 'c');
if ($__lock === false || !flock($__lock, LOCK_EX | LOCK_NB)) {
    // IZEJAS KODS 3, NE 0. Ar nulli dienas ķēde (granti.build && granti.tulko) uzskatītu
    // būvi par izpildītu un palaistu MAKSAS tulkošanu pret datubāzi, ko otrs process tobrīd
    // gatavojas aizstāt — samaksāts par tekstiem, kas pēc dažām minūtēm vairs nav aktuāli.
    // Ar ne-nulli ķēde apstājas, un panelis to parāda kā izlaistu darbu.
    fwrite(STDERR, "Būve jau notiek citā procesā (" . granti_lock_path() . ") — izlaists.\n");
    exit(3);
}
ftruncate($__lock, 0);
fwrite($__lock, (string)getmypid());
fflush($__lock);
@unlink(granti_stop_flag());   // vecs karodziņš citādi apturētu tikko sākto būvi
register_shutdown_function(static function () use ($__lock): void {
    // Ja būve nokrita (neķerta kļūda, exit(1), atmiņas trūkums), stāvoklis citādi paliktu
    // "run" uz mūžiem un panelis rādītu, ka darbs vēl iet. Fiksē kļūdu TIKAI tad, ja
    // pēdējais posms nav 'gatavs' — normāls beigu ceļš to jau ir uzrakstījis.
    $st = @json_decode((string)@file_get_contents(granti_state_path()), true);
    if (is_array($st) && ($st['stage'] ?? '') !== 'gatavs') {
        $e = error_get_last();
        gr_state(['stage' => 'kluda', 'status' => 'error',
                  'error' => $e['message'] ?? 'būve pārtraukta pirms beigām']);
    }
    @unlink(granti_stop_flag());
    if (is_resource($__lock)) { flock($__lock, LOCK_UN); fclose($__lock); }
});
$__sakums = microtime(true);
gr_state(['stage' => 'sakums', 'status' => 'run', 'pid' => getmypid(), 'error' => '', 'ieraksti' => 0]);

$tmp = $DB_FILE . '.tmp';
@unlink($tmp);
$pdo = new PDO('sqlite:' . $tmp);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA journal_mode=MEMORY");
$pdo->exec("CREATE TABLE grants (
    id TEXT PRIMARY KEY, source TEXT NOT NULL, status TEXT NOT NULL,
    title TEXT NOT NULL, programme TEXT, programme_label TEXT,
    call_id TEXT, call_title TEXT,
    opening_date TEXT, deadline_date TEXT, deadline_count INTEGER DEFAULT 0,
    budget_total REAL, budget_min REAL, budget_max REAL, expected_grants INTEGER,
    url TEXT, keywords TEXT, sme INTEGER DEFAULT 0,
    nozare TEXT, division TEXT,
    action_type TEXT, model TEXT, lump_sum INTEGER DEFAULT 0,
    fstp INTEGER DEFAULT 0, fstp_max REAL, deadline_model TEXT,
    deadlines TEXT, deadline_last TEXT, deadline_apply TEXT,
    budget_scope TEXT DEFAULT 'tema', budget_topic REAL,
    sec_objective TEXT, sec_outcome TEXT, sec_scope TEXT, sec_eligibility TEXT, sec_specific TEXT,
    trl TEXT, page_limit INTEGER, req_tags TEXT,
    -- Pieteikšanās saite. Portāls to atdod PATS laukā links[].url; to NEVAR salikt no
    -- identifier/ccm2Id (numurs tur ir 21015-46097, bet ccm2Id ~51 milj., sakritību nulle).
    -- Tāpēc lauku nolasa, nevis būvē. Sk. gr_pieteiksanas_saite().
    submit_url TEXT,
    -- 1, ja page_limit ir DIVU POSMU konkursa pirmā posma limits (IHI tēmās viens teikums
    -- nosauc abus: 20 lpp. koncepcijai, 50 lpp. pilnajam). Lapa to nosauc vārdā, citādi
    -- skaitlis izskatītos pēc visa pieteikuma limita.
    page_limit_stage INTEGER,
    -- Publiskā adrese /granti/{slug}. Glabājas DATUBĀZĒ, ne rēķinās lapā: adresei jābūt
    -- stabilai un unikālai, un vietnes karte to lasa no tās pašas vietas, ko lapa.
    slug TEXT
)");
$pdo->exec("CREATE INDEX idx_g_status ON grants(status, deadline_date)");
$pdo->exec("CREATE INDEX idx_g_nozare ON grants(nozare, status)");
$pdo->exec("CREATE INDEX idx_g_model ON grants(model, status)");
$pdo->exec("CREATE UNIQUE INDEX idx_g_slug ON grants(slug)");
$pdo->exec("CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT)");
// Kolonnu saraksts ir SKAIDRS, ne pozicionāls. Bez tā divu viena tipa kolonnu apmaiņa
// CREATE TABLE pusē (budget_min/budget_max, deadline_last/deadline_apply) pārlīstu klusi:
// vērtības nonāktu nepareizajās slejās, un neviens tests neizgāztos.
$ins = $pdo->prepare("INSERT OR REPLACE INTO grants (
    id, source, status, title, programme, programme_label, call_id, call_title, opening_date,
    deadline_date, deadline_count, budget_total, budget_min, budget_max, expected_grants, url,
    keywords, sme, nozare, division, action_type, model, lump_sum, fstp, fstp_max,
    deadline_model, deadlines, deadline_last, deadline_apply, budget_scope, budget_topic,
    sec_objective, sec_outcome, sec_scope, sec_eligibility, sec_specific, trl, page_limit,
    req_tags, submit_url, page_limit_stage
) VALUES
    (:id,:source,:status,:title,:programme,:programme_label,:call_id,:call_title,
     :opening_date,:deadline_date,:deadline_count,:budget_total,:budget_min,:budget_max,
     :expected_grants,:url,:keywords,:sme,:nozare,:division,
     :action_type,:model,:lump_sum,:fstp,:fstp_max,:deadline_model,
     :deadlines,:deadline_last,:deadline_apply,'tema',:budget_topic,
     :sec_objective,:sec_outcome,:sec_scope,:sec_eligibility,:sec_specific,:trl,:page_limit,:req_tags,
     :submit_url,:page_limit_stage)");

// Nozaru klasifikators (programmeDivision -> nozare); sk. faila galvu par prioritātēm.
require_once __DIR__ . '/../lib/nozares.php';
// Pieteikšanās modelis un atzīmes (typeOfAction -> viens/konsorcijs/CSA/balva; FSTP; lump sum).
require_once __DIR__ . '/../lib/modelis.php';
// Konkursa teksta sadaļas (Mērķis / Ko sasniegt / Ko darīt / Prasības pretendentam / TRL).
require_once __DIR__ . '/../lib/teksts.php';
// Tulkojumu kešs (patstāvīgs fails) + tulkotājs.
require_once __DIR__ . '/../lib/tulkojumi.php';

// ES programmu īsie nosaukumi latviski (UI etiķetes; datu tulkošana te NENOTIEK).
$PROG_LV = [
    'HORIZON' => 'Apvārsnis Eiropa',      'EDF' => 'Eiropas Aizsardzības fonds',
    'LIFE2027' => 'LIFE — vide un klimats', 'DIGITAL' => 'Digitālā Eiropa',
    'EURATOM2027' => 'Euratom',           'CEF2027' => 'Infrastruktūras savienošana (CEF)',
    'CERV' => 'Pilsoņi, vienlīdzība, tiesības (CERV)', 'ERASMUS2027' => 'Erasmus+',
    'RFCS2027' => 'Ogļu un tērauda pētniecība', 'ESF' => 'Eiropas Sociālais fonds+',
    'ISF' => 'Iekšējās drošības fonds',   'SMP' => 'Vienotā tirgus programma',
    'JUST2027' => 'Tiesiskuma programma', 'ESC2027' => 'Eiropas Solidaritātes korpuss',
    'CREA2027' => 'Radošā Eiropa',        'AGRIP' => 'Lauksaimniecības veicināšana',
    'EMFAF2027' => 'Zivsaimniecība (EMFAF)', 'EU4H' => 'ES Veselībai',
    'UCPM' => 'Civilā aizsardzība',       'AMIF' => 'Migrācijas un integrācijas fonds',
    'JTM' => 'Taisnīgas pārkārtošanās mehānisms', 'I3' => 'Starpreģionu inovāciju investīcijas',
    'PPPA2027' => 'Izmēģinājuma projekti (PPPA)', 'RENEWFM' => 'Atjaunīgās enerģijas mehānisms',
    'EUBA' => 'ES iestāžu darbības',      'PERI' => 'Perikls IV (eiro aizsardzība)',
    'SOCPL' => 'Sociālās politikas līnijas (SOCPL)', 'INNOVFUND' => 'Inovāciju fonds',
    'AGRIP2027' => 'Lauksaimniecības veicināšana', 'AMIF2027' => 'Migrācijas un integrācijas fonds',
    'UCPM2027' => 'Civilā aizsardzība', 'EMFAF' => 'Zivsaimniecība (EMFAF)',
    'ISFP' => 'Iekšējās drošības fonds (policija)', 'BMVI' => 'Robežu pārvaldība un vīzas',
    'COSME' => 'Uzņēmumu konkurētspēja (COSME)', 'EDIDP' => 'Aizsardzības rūpniecības attīstība',
];

$euCount = 0; $sifCount = 0; $budgetHits = 0;
// Vai STOP pārtrauca vākšanu. Apturēta būve NEDRĪKST tikt publicēta: `break` izved no
// cilpas, bet pārējie soļi (dublikāti, slug, meta, atomiskā nomaiņa) izpildītos tāpat,
// un daļēji savākta kopa aizstātu pilnu — klusi, ar stāvokli "gatavs".
$gr_apturets = false;
$bulkNovecojis = false;      // ES fails > 72 h vecs un lejupielāde neizdodas -> exit 5 pēc publicēšanas
$sifKlume = '';              // SIF avots bojāts -> pārnes vecās rindas, brīdinājums metā
$detalasNepilnas = '';       // tēmu detaļas pirmajā būvē nepilnas -> brīdinājums metā

// ═══ 1. ES portāls ═══════════════════════════════════════════════════════════
if (!isset($opts['skip-eu'])) {
    $bulkFile = $opts['bulk'] ?? ($CACHE_DIR . '/grantsTenders.json');
    // Kešotais bulk fails tika lietots mūžīgi: 8 mēnešus vecs momentuzņēmums, bet meta
    // 'built_at' rādīja šodienu. Vecāku par BULK_MAX_AGE_H pārlejupielādē; ja tas neizdodas,
    // strādā ar veco, bet fiksē tā vecumu metā, lai lapa varētu to godīgi parādīt.
    $bulkAgeH = (is_string($bulkFile) && is_file($bulkFile))
        ? (int)round((time() - (int)filemtime($bulkFile)) / 3600) : -1;
    // VECO FAILU NEDZĒŠ, KAMĒR JAUNAIS NAV DISKĀ. Iepriekš @unlink() notika PIRMS
    // lejupielādes, tāpēc viena slikta diena (ES portāls nost, TLS nogriezts) atstāja
    // sadaļu pavisam bez momentuzņēmuma: būve izgāja ar exit(1), un nākamajā reizē tā
    // sākās no nulles. Tagad jaunais raksta .part failā un veco aizstāj tikai tad, ja
    // lejupielāde izdevās; ja ne — strādājam ar veco un pasakām, cik tas vecs.
    $svaigsVajadzigs = ($bulkAgeH < 0) || ($bulkAgeH > BULK_MAX_AGE_H && !isset($opts['bulk']));
    if ($svaigsVajadzigs) {
        gr_log($bulkAgeH < 0
            ? 'ES bulk: kešota faila nav — lejupielādē ' . EU_BULK_URL . ' (~124 MB)...'
            : "ES bulk: kešotais fails ir $bulkAgeH h vecs — lejupielādē no jauna.");
        // TRĪS MĒĢINĀJUMI ar pauzi: viens nogriezts 124 MB pieprasījums citādi nozīmēja "šodien
        // būves nav" — atsākšanas nav (portāls failu pa dienu pārģenerē, un -C - dod bojātu šuvi).
        $body = null;
        for ($mek = 1; $mek <= 3; $mek++) {
            $body = gr_http_get(EU_BULK_URL, 900);
            // Minimālais izmērs UN pilnīgs JSON: portāla fails ir ~124 MB, un pat pēc pusgada
            // tas nebūs zem 10 MB; nogriezts fails nebeidzas ar aizverošo iekavu. Bez šiem
            // vārtiem tukša vai kļūdas lapa (HTTP 200 ar "Service unavailable") vai puse faila
            // aizstātu derīgu momentuzņēmumu.
            if ($body !== null && strlen($body) > 10 * 1024 * 1024 && gr_json_pilns($body)) break;
            gr_log('  ! ES bulk: mēģinājums ' . $mek . ' neizdevās'
                . ($body === null ? '' : ' (' . round(strlen($body) / 1e6) . ' MB, nepilns)')
                . ($mek < 3 ? ' — pēc 30 s vēlreiz.' : '.'));
            $body = null;
            if ($mek < 3) sleep(30);
        }
        if ($body !== null) {
            $jauns = $CACHE_DIR . '/grantsTenders.json';
            $part  = $jauns . '.part';
            // === strlen, ne !== false: pie pilna diska file_put_contents atdod MAZĀKU baitu
            // skaitu, un tas nav false — nepilns fails tiktu pārcelts pāri labajam.
            $rakstits = file_put_contents($part, $body) === strlen($body);
            unset($body);
            // PILNA PARSĒŠANA PIRMS RENAME. gr_json_pilns() lielam failam skatās tikai beigu
            // iekavu, un ~1 % nejaušu nogriezumu tai izsprūk cauri; ja tāds fails pārceļotos
            // pāri labajam kešam, nākamais json_decode kristu, kešs tiktu izmests, un rīt sāktos
            // no nulles. Parsēšana šeit maksā ~3 s un to pašu atmiņu, ko būve tāpat tērēs.
            $derigs = $rakstits && is_array(json_decode((string)file_get_contents($part), true)['fundingData']['GrantTenderObj'] ?? null);
            if ($derigs && rename($part, $jauns)) {
                $bulkFile = $jauns;
                gr_log('ES bulk: lejupielādēts ' . round(filesize($jauns) / 1e6) . ' MB, parsējas.');
            } else {
                @unlink($part);
                gr_log('  ! ES bulk: jaunais fails ' . ($rakstits ? 'neparsējas' : 'nesaglabājās') . ' — paliek iepriekšējais.');
            }
        } else {
            unset($body);
            if (!is_string($bulkFile) || !is_file($bulkFile)) {
                fwrite(STDERR, "ES bulk lejupielāde neizdevās un kešota faila nav.\n");
                gr_state(['stage' => 'kluda', 'status' => 'error', 'error' => 'ES bulk nav pieejams']);
                exit(1);
            }
            gr_log("  ! ES bulk: lejupielāde neizdevās — strādā ar $bulkAgeH h vecu kešu.");
            // Trīs dienas bez svaiga faila vairs nav "slikta diena" — sk. exit(5) beigās.
            if ($bulkAgeH > 72) $bulkNovecojis = true;
        }
    }
    gr_state(['stage' => 'es-parse']);
    gr_log('ES bulk: parsē ' . basename((string)$bulkFile) . ' (' . round(filesize((string)$bulkFile) / 1e6) . ' MB)...');
    $data = json_decode((string)file_get_contents((string)$bulkFile), true);
    $recs = $data['fundingData']['GrantTenderObj'] ?? null;
    if (!is_array($recs)) {
        // Bojāts kešs nedrīkst pārdzīvot nākamo palaišanu: citādi katra būve 20 h no vietas
        // kristu tajā pašā vietā. Ar roku dotu --bulk failu neaiztiekam.
        if (!isset($opts['bulk']) && is_string($bulkFile) && is_file($bulkFile)) {
            @unlink($bulkFile);
            gr_log('  ! ES bulk: bojātais kešotais fails izmests — nākamā būve velk no jauna.');
        }
        fwrite(STDERR, "ES bulk: negaidīta struktūra.\n");
        gr_state(['stage' => 'kluda', 'status' => 'error', 'error' => 'ES bulk: negaidīta struktūra']);
        exit(1);
    }
    gr_log('ES bulk: ' . count($recs) . ' ieraksti.');

    $live = [];
    foreach ($recs as $r) {
        if (($r['type'] ?? null) !== 1) continue;
        $st = $r['status']['abbreviation'] ?? '';
        if ($st === 'Open' || $st === 'Forthcoming') $live[] = $r;
    }
    unset($data, $recs);
    gr_log('ES bulk: dzīvi grantu konkursi: ' . count($live) . '.');
    // MINIMĀLĀ SLIEKSNE. is_array() vien nepasargā: {"fundingData":{"GrantTenderObj":[]}}
    // ir derīgs masīvs, cilpa nekad neizpildās, $euCount paliek 0, un būve mierīgi aiziet
    // līdz atomiskajai nomaiņai — tukša sadaļa pāri 636 labiem ierakstiem. Korpusā gadu
    // gaitā ir bijuši 620-640 dzīvi konkursi; 100 ir ar rezervi zem jebkuras normas.
    if (count($live) < 100) {
        fwrite(STDERR, 'ES bulk: tikai ' . count($live) . " dzīvu konkursu — izskatās pēc bojāta avota. Būve pārtraukta.\n");
        gr_state(['stage' => 'kluda', 'status' => 'error',
                  'error' => 'ES avotā tikai ' . count($live) . ' konkursu']);
        exit(1);
    }

    $n = 0;
    $klumesPecKartas = 0;          // tēmu detaļu kļūmes pēc kārtas (pauze pie 5, apstāšanās pie 25)
    $detalasApstadinatas = false;  // portāls nav sasniedzams — pārējās tēmas šajā būvē nevelk
    foreach ($live as $r) {
        $idf   = (string)$r['identifier'];
        $lc    = strtolower($idf);
        // Identifikators nāk no ĀRĒJA JSON un tiek lietots gan kā keša faila ceļš, gan kā
        // URL segments. Bez šī vārta ieraksts ar "../" izlasītu un pārrakstītu failus ārpus
        // keša mapes (pārbaudīts: topics/../../../X.json saturs nonāca datubāzē).
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $lc)) {
            gr_log("  ! izlaists nederīgs identifikators: $idf");
            continue;
        }
        // $td un $bo ir jāatiestata KATRĀ iterācijā: ar --skip-details tie netiek piešķirti,
        // un fallback zemāk lasīja IEPRIEKŠĒJĀS tēmas programmeDivision -> sveša nozare.
        $td = null; $bo = null; $mine = [];
        $stAbb = $r['status']['abbreviation'];
        $dl    = array_values(array_filter(array_map('gr_ms_date', $r['deadlineDatesLong'] ?? [])));

        // Budžets no topicDetails (kešots; identifikators OBLIGĀTI mazajiem burtiem).
        $bTotal = null; $bMin = null; $bMax = null; $bGrants = null;
        if (!isset($opts['skip-details'])) {
            $cf = $CACHE_DIR . '/topics/' . $lc . '.json';
            // KAD VELK NO JAUNA. Agrāk tikai tad, ja faila nav — tēmas budžets, sadaļas, termiņa
            // modelis un pieteikšanās saite bija iesaldēti pirmajā ielasīšanā uz mūžiem, un
            // portāla labojumi (pagarināts termiņš!) lapā nekad nenonāca; arī pārejošs 404 palika
            // kešā kā galīgs. Tagad: nav / tukšs / neparsējams / vecāks par ~2 nedēļām (ar
            // izkliedi, lai 648 tēmas nekrīt vienā naktī) / notFound vecāks par dienu.
            $vecs = is_file($cf) ? (time() - (int)filemtime($cf)) : PHP_INT_MAX;
            $kesots = (is_file($cf) && filesize($cf) > 0) ? json_decode((string)file_get_contents($cf), true) : null;
            $irNotFound = is_array($kesots) && !empty($kesots['notFound']);
            $velk = !is_array($kesots)
                 || ($irNotFound && $vecs > 86400)
                 || (!$irNotFound && $vecs > (14 + ($n % 7)) * 86400);
            if ($velk && !$detalasApstadinatas) {
                $jauns = null;
                for ($try = 0; $try < 3; $try++) {
                    $code = 0;
                    $body = gr_http_get(sprintf(EU_TOPIC_URL, $lc), 30, $code);
                    if ($body !== null) {
                        $dek = json_decode($body, true);
                        // Kešā TIKAI parsējams JSON: "HTTP 200 + Service unavailable" agrāk kļuva
                        // par tēmu bez budžeta uz visu mūžu.
                        // Ne tikai "parsējas": arī JSON KĻŪDAS objekts ({"error":…}) ir masīvs un
                        // kešā stāvētu 2 nedēļas kā tēma bez budžeta. Īstai tēmai augšā ir TopicDetails.
                        if (is_array($dek) && isset($dek['TopicDetails'])) { $jauns = $dek; file_put_contents($cf, $body); break; }
                    }
                    if ($code === 404) {
                        // 404 raksta tikai tad, ja LABA keša nav: tēma joprojām ir bulk plūsmā,
                        // un vecie dati ir labāki par "nav atrasts".
                        if (!is_array($kesots) || $irNotFound) { file_put_contents($cf, '{"notFound":true}'); $jauns = ['notFound' => true]; }
                        else { @touch($cf); $jauns = $kesots; }
                        break;
                    }
                    sleep(1 + $try * 2);   // ātruma limits/pārrāvums — nogaida
                }
                if ($jauns !== null) { $kesots = $jauns; $klumesPecKartas = 0; }
                else {
                    // Neizdevās: vecais kešs paliek (ja bija), un tā datums tiek pabīdīts, lai nākamā
                    // būve to nemēģina uzreiz atkal; kļūmes pēc kārtas skaita, lai portāla izkrišana
                    // nekļūtu par divu stundu ciklu ar 3 × 3 mēģinājumiem uz katru no 600 tēmām.
                    if (is_array($kesots)) @touch($cf);
                    $klumesPecKartas++;
                    if ($klumesPecKartas === 5) { gr_log('  ! 5 tēmu detaļas pēc kārtas neizdevās — 30 s pauze.'); sleep(30); }
                    if ($klumesPecKartas >= 25) {
                        $detalasApstadinatas = true;
                        gr_log('  ! 25 tēmu detaļas pēc kārtas neizdevās — pārējās šajā būvē nevelk (portāls nav sasniedzams).');
                    }
                }
                usleep(random_int(350000, 650000));
            }
            $td = is_array($kesots) ? $kesots : null;
            $bo = $td['TopicDetails']['budgetOverviewJSONItem'] ?? $td['budgetOverviewJSONItem'] ?? null;
            if (is_string($bo)) $bo = json_decode($bo, true);
            // budgetTopicActionMap satur ARĪ brāļu tēmu darbības ar visa konkursa
            // budžetu (EIC: 6 tēmas x 634 milj. = 3,8 mljrd. vienai tēmai!).
            // Ņem tikai darbības, kuru nosaukums sākas ar ŠĪS tēmas identifikatoru;
            // ja tādu nav un darbība ir tikai viena — to; citādi budžets nezināms.
            $all = [];
            foreach (($bo['budgetTopicActionMap'] ?? []) as $actions)
                foreach ((array)$actions as $a) $all[] = $a;
            // Kails prefikss ķēra arī BRĀĻA tēmu, kuras ID sākas ar šīs tēmas ID:
            // HORIZON-EIC-2026-PRIZE-WIP savāca arī "…-WIP-RisingInnovators" rindu, un budžets
            // kļuva 320 000 € pareizo 220 000 vietā (+45 %), bet budget_min nokrita no 50 000
            // uz 20 000. Darbības virknes forma ir "<TĒMAS-ID> - <VEIDS>", tāpēc prasām, lai
            // aiz identifikatora seko atstarpe vai domuzīme, nevis vēl viens ID gabals.
            $mine = array_values(array_filter($all, function ($a) use ($idf) {
                $act = (string)($a['action'] ?? '');
                return strcasecmp($act, $idf) === 0
                    || preg_match('/^' . preg_quote($idf, '/') . '\s*[-–—]\s/i', $act) === 1;
            }));
            if (!$mine && count($all) === 1) $mine = $all;
            // Portāls konkursa aploksni atkārto uz katras darbības rindas (MSCA:
            // 3 varianti x 593 milj. NAV 1,8 mljrd.) — identiskas budžeta kartes
            // skaita vienreiz; atšķirīgas (īsti apakšbudžeti) summē.
            $seenMaps = [];
            foreach ($mine as $a) {
                $mapKey = json_encode($a['budgetYearMap'] ?? []);
                if (!isset($seenMaps[$mapKey])) {
                    $seenMaps[$mapKey] = true;
                    foreach (($a['budgetYearMap'] ?? []) as $amt) $bTotal = ($bTotal ?? 0.0) + (float)$amt;
                }
                if (isset($a['minContribution']) && (float)$a['minContribution'] > 0)
                    $bMin = min($bMin ?? INF, (float)$a['minContribution']);
                if (isset($a['maxContribution']) && (float)$a['maxContribution'] > 0)
                    $bMax = max($bMax ?? 0.0, (float)$a['maxContribution']);
                if (isset($a['expectedGrants']))
                    $bGrants = max($bGrants ?? 0, (int)$a['expectedGrants']);
            }
            if ($bMin === INF) $bMin = null;
            if ($bTotal !== null) $budgetHits++;
        }

        $prog = $r['frameworkProgramme']['abbreviation'] ?? null;

        // Nozare no programmeDivision. Bulk ierakstā tas parasti jau ir; ja nav —
        // ņem no topicDetails, kas šai tēmai jau ir kešā (otrs pieprasījums nav vajadzīgs).
        $divSrc = $r['programmeDivision'] ?? ($td['TopicDetails']['programmeDivision'] ?? $td['programmeDivision'] ?? []);
        $divs = [];
        foreach ((array)$divSrc as $dv) {
            $a = is_array($dv) ? ($dv['abbreviation'] ?? null) : (is_string($dv) ? $dv : null);
            if ($a !== null && $a !== '') $divs[] = (string)$a;
        }
        // Rādāmā nodaļa = visdziļākā (garākā) abreviatūra; tā ir konkrētākā tēmas joma.
        $deepest = '';
        foreach ($divs as $d) if (strlen($d) > strlen($deepest)) $deepest = $d;

        // Pieteikšanās modelis un atzīmes. Viss nāk no topicDetails: darbības veids
        // (actions[].types[].typeOfAction), granta veids (topicMGAs -LS = vienreizējs
        // maksājums), kaskādes finansējums (conditions) un termiņa modelis.
        // Ar --skip-details $td un $bo nav definēti, tāpēc modelis/FSTP/lump sum/termiņa
        // modelis klusi kļuva tukši VISIEM ierakstiem. Kešotais fails tur jau ir — nolasa to,
        // tikai neveic jaunu HTTP pieprasījumu.
        if (!isset($td) || !is_array($td)) {
            $cf2 = $CACHE_DIR . '/topics/' . $lc . '.json';
            $td = is_file($cf2) ? (json_decode((string)file_get_contents($cf2), true) ?: []) : [];
            $bo2 = $td['TopicDetails']['budgetOverviewJSONItem'] ?? $td['budgetOverviewJSONItem'] ?? null;
            if (is_string($bo2)) $bo2 = json_decode($bo2, true);
            if (!isset($bo) || !is_array($bo)) $bo = is_array($bo2) ? $bo2 : [];
        }
        if (!isset($bo) || !is_array($bo)) $bo = [];

        $toa = [];
        foreach (($td['TopicDetails']['actions'] ?? $td['actions'] ?? []) as $a) {
            foreach (($a['types'] ?? []) as $t) {
                // Lauka formāts ir "HORIZON-RIA HORIZON  Research and Innovation Actions" —
                // abreviatūra un apraksts vienā virknē, tāpēc jāņem pirmais vārds.
                $code = explode(' ', trim((string)($t['typeOfAction'] ?? '')))[0];
                if ($code !== '') $toa[$code] = true;
            }
        }
        $toa = array_keys($toa);
        $lumpSum = 0;
        foreach (($td['TopicDetails']['topicMGAs'] ?? $td['topicMGAs'] ?? []) as $mg) {
            if (str_ends_with((string)($mg['abbreviation'] ?? ''), '-LS')) { $lumpSum = 1; break; }
        }
        $condHtml = (string)($td['TopicDetails']['conditions'] ?? $td['conditions'] ?? '');
        [$hasFstp, $fstpMax] = gr_fstp($condHtml);
        $sad = gr_teksta_sadalas((string)($td['TopicDetails']['description'] ?? $td['description'] ?? ''), $condHtml);
        // Termiņa modeli ņem TIKAI no šīs tēmas darbībām ($mine), ne no visas kartes: agrāk
        // "pirmais atrastais" varēja nākt no brāļa tēmas, un no tā ir atkarīgs deadline_apply
        // (two-stage -> min, citiem -> max), t.i. vai konkurss vispār skaitās dzīvs.
        // Šodien atšķirīgu modeļu vienā konkursā nav, bet dati to negarantē.
        $dlModel = null;
        foreach (($mine ?? []) as $a) {
            if (!empty($a['deadlineModel'])) { $dlModel = (string)$a['deadlineModel']; break; }
        }

        $ins->execute([
            ':id' => 'EU:' . $idf, ':source' => 'EU',
            ':status' => $stAbb === 'Open' ? 'open' : 'forthcoming',
            ':title' => (string)$r['title'],
            ':programme' => $prog, ':programme_label' => $PROG_LV[$prog] ?? $prog,
            ':call_id' => $r['callIdentifier'] ?? null, ':call_title' => $r['callTitle'] ?? null,
            ':opening_date' => gr_ms_date($r['plannedOpeningDateLong'] ?? null),
            ':deadline_date' => gr_pick_deadline($dl), ':deadline_count' => count($dl),
            ':budget_total' => $bTotal, ':budget_min' => $bMin, ':budget_max' => $bMax,
            ':expected_grants' => $bGrants,
            ':url' => sprintf(EU_PORTAL_URL, $lc),
            ':keywords' => implode(', ', $r['keywords'] ?? []),
            ':sme' => !empty($r['sme']) ? 1 : 0,
            ':nozare' => gr_nozare($prog, $divs, '', $idf), ':division' => $deepest !== '' ? $deepest : null,
            ':action_type' => $toa ? implode(',', $toa) : null, ':model' => gr_modelis($toa, $condHtml),
            ':lump_sum' => $lumpSum, ':fstp' => $hasFstp ? 1 : 0, ':fstp_max' => $fstpMax,
            ':deadline_model' => $dlModel,
            // Visi termiņi, ne tikai būves brīdī izvēlētais: bez tiem konkurss ar diviem
            // termiņiem pēc pirmā datuma izskatītos beidzies, lai gan otrais vēl priekšā,
            // un lapa to nevarētu pārrēķināt (statusu tā rēķina katrā pieprasījumā).
            ':deadlines' => $dl ? implode(',', $dl) : null,
            ':deadline_last' => $dl ? max($dl) : null,
            // PĒDĒJAIS DATUMS, KAD VĒL VAR PIETEIKTIES — nav tas pats, kas pēdējais termiņš.
            // Divu posmu konkursā deadlineDates ir 1. posma (koncepcija) un 2. posma (pilnais
            // pieteikums) datumi, un otrajā posmā iesniedz TIKAI tie, kas izgāja pirmo. Ņemot
            // max(), trīs LIFE konkursi, kurus ES portāls rāda kā "Closed", mūsu lapā vēl
            // pusgadu stāvēja "Atvērtajos" ar 163 milj. € kopsummā.
            ':deadline_apply' => $dl ? ($dlModel === 'two-stage' ? min($dl) : max($dl)) : null,
            ':budget_topic' => gr_temas_budzets($condHtml,
                (string)($td['TopicDetails']['description'] ?? $td['description'] ?? '')),
            // Strukturētās sadaļas — sanitizēts HTML, ko lapa rāda detaļu skatā.
            ':sec_objective' => $sad['objective'], ':sec_outcome' => $sad['outcome'],
            ':sec_scope' => $sad['scope'], ':sec_eligibility' => $sad['eligibility'],
            ':sec_specific' => $sad['specific'], ':trl' => $sad['trl'], ':page_limit' => $sad['page_limit'],
            ':req_tags' => ($rt = gr_prasibu_birkas($sad['eligibility'], $sad['specific'])) ? implode('|', $rt) : null,
            ':submit_url' => gr_pieteiksanas_saite($td),
            ':page_limit_stage' => $sad['page_limit_stage'],
        ]);
        $euCount++;
        if (++$n % 50 === 0) {
            gr_log("  · ES: $n/" . count($live) . " (budžeti: $budgetHits)");
            gr_state(['stage' => 'es-temas', 'ieraksti' => $euCount, 'current' => "$n/" . count($live)]);
            // STOP tiek pārbaudīts ŠEIT, ne katrā iterācijā: pārbaude ir faila esamība, un
            // 638 reizes to darīt bez vajadzības. Ik pēc 50 tēmām reakcija ir dažas sekundes.
            if (gr_stop_requested()) { $gr_apturets = true; gr_log('🛑 STOP karodziņš — ES vākšana pārtraukta.'); break; }
        }
    }
    gr_log("ES portāls: ierakstīti $euCount (budžeti atrasti: $budgetHits).");
    // DETAĻU PILNĪGUMS. Tēma bez detaļām ir ieraksts bez budžeta, sadaļām un pieteikšanās
    // saites — "veiksmīga" būve ar 590 tādām rindām būtu tukša sadaļa ar statusu "gatavs".
    // Ja ir dzīva DB, paturam to (exit 1); ja nav (pirmā reize), publicējam, bet skaļi, un
    // nākamā būve trūkstošo velk vēlreiz (kešā tādām tēmām faila nav).
    if (!isset($opts['skip-details']) && $budgetHits < 0.5 * max(1, count($live))) {
        $zina = "tēmu detaļas nepilnīgas: budžeti $budgetHits no " . count($live);
        if (is_file($DB_FILE)) {
            fwrite(STDERR, "ES portāls: $zina — būve pārtraukta, dzīvā DB paliek.\n");
            gr_state(['stage' => 'kluda', 'status' => 'error', 'error' => $zina]);
            $pdo = null; @unlink($tmp);
            exit(1);
        }
        gr_log("  ! ES portāls: $zina — publicē NEPILNU pirmo būvi.");
        $detalasNepilnas = $zina;
    }
}

// ═══ 2. SIF projektu konkursi ════════════════════════════════════════════════
if (!isset($opts['skip-sif'])) {
    $today = date('Y-m-d H:i:s');
    $sifFail = false;
    for ($off = 0; ; $off += SIF_PAGE) {
        $body = gr_http_get(sprintf(SIF_LIST_URL, $off, SIF_PAGE), 30);
        // Tīkla kļūme pirmajā lapā deva $rows = null un cilpa vienkārši beidzās: būve
        // turpinājās, pārcēla tukšo DB un beidzās ar kodu 0 — visi 82 SIF konkursi pazuda
        // bez viena vārda žurnālā. Kļūme tagad ir kļūme, ne klusa dzēšana.
        if (gr_stop_requested()) { $gr_apturets = true; $sifFail = true; gr_log('🛑 STOP karodziņš — SIF vākšana pārtraukta.'); break; }
        if ($body === null) { $sifFail = true; gr_log('SIF: pieprasījums neizdevās (offset ' . $off . ').'); break; }
        $rows = json_decode($body, true);
        if (!is_array($rows)) { $sifFail = true; gr_log('SIF: negaidīta atbildes struktūra.'); break; }
        if ($rows === []) break;
        foreach ($rows as $r) {
            $open  = (string)($r['izsludinasanas_laiks'] ?? '');
            $close = (string)($r['iesniegsanas_laiks'] ?? '');
            if ($close !== '' && $close < $today) $status = 'closed';
            elseif ($open !== '' && $open > $today) $status = 'forthcoming';
            else $status = 'open';
            $ins->execute([
                ':id' => 'SIF:' . $r['id'], ':source' => 'SIF', ':status' => $status,
                ':title' => (string)$r['nosaukums'],
                ':programme' => 'SIF', ':programme_label' => (string)($r['programmas_nosaukums'] ?? 'SIF'),
                ':call_id' => null, ':call_title' => null,
                ':opening_date' => $open !== '' ? substr($open, 0, 10) : null,
                ':deadline_date' => $close !== '' ? substr($close, 0, 10) : null,
                ':deadline_count' => $close !== '' ? 1 : 0,
                ':budget_total' => $r['kopejais_finansejums'] ?? null,
                ':budget_min' => $r['min_finansejums'] ?? null,
                ':budget_max' => $r['max_finansejums'] ?? null,
                ':expected_grants' => null,
                ':url' => sprintf(SIF_ITEM_URL, (int)$r['id']),
                // SIF atslēgvārdu nedod; te glabājas konkursa STATUSA vārds ("Vērtēšanā",
                // "Noslēdzies"). Kolonna saucas keywords, bet saturs ir cits — meklēšanai tas
                // der (cilvēks meklē "vērtēšanā"), tikai nesajaukt to ar ES atslēgvārdiem.
                ':keywords' => (string)($r['statuss'] ?? ''), ':sme' => 0,
                ':nozare' => gr_nozare('SIF', [], (string)($r['programmas_nosaukums'] ?? '')),
                ':division' => null,
                // SIF konkursiem ES darbības veida jēdziena nav, un mēs to neesam pārbaudījuši
                // pret katra konkursa nolikumu — tāpēc 'atkariba', nevis apgalvojums 'viens'.
                // Agrāk visi 82 SIF ieraksti nesa apgalvojumu "konsorcijs nav vajadzīgs",
                // ko neviens datu lauks nepamatoja.
                ':action_type' => null, ':model' => 'atkariba', ':lump_sum' => 0,
                ':fstp' => 0, ':fstp_max' => null, ':deadline_model' => null,
                ':deadlines' => $close !== '' ? substr($close, 0, 10) : null,
                ':deadline_last' => $close !== '' ? substr($close, 0, 10) : null,
                ':deadline_apply' => $close !== '' ? substr($close, 0, 10) : null,
                ':budget_topic' => null,
                ':sec_objective' => null, ':sec_outcome' => null, ':sec_scope' => null,
                ':sec_eligibility' => null, ':sec_specific' => null, ':trl' => null, ':page_limit' => null,
                ':req_tags' => null,
                // SIF pusē saite IR atvasināma no tā paša numura, kas ir mūsu id — pārbaudīts
                // pārlūkā abos atvērtajos konkursos (poga "Pieteikties konkursam" ved uz šo).
                // Slēgtajiem konkursiem šīs pogas lapā nav, tāpēc saiti liek tikai atvērtajiem;
                // vai to rādīt, izšķir lapa pēc DZĪVĀ statusa, ne pēc būves brīža.
                ':submit_url' => sprintf(SIF_APPLY_URL, (int)$r['id']),
                ':page_limit_stage' => null,
            ]);
            $sifCount++;
        }
        if (count($rows) < SIF_PAGE) break;
        usleep(300000);
    }
    gr_log("SIF: ierakstīti $sifCount.");
    // Slieksnis, ne tikai kļūmes karodziņš. SIF API var atbildēt ar HTTP 200 un tukšu
    // masīvu — tad $sifFail paliek false, $sifCount ir 0, un atomiskā nomaiņa izdzēstu
    // visus 83 esošos SIF ierakstus, neko nepasakot. Konkursu skaits tur gadiem ir ap 80.
    if ($sifCount < 10) {
        // SIF kļūme NEDRĪKST bloķēt ES atjauninājumus: agrāk exit(1) izmeta arī svaigos ~630 ES
        // ierakstus, un SIF API formas maiņa apturētu visu sadaļu, līdz kāds labo parseri.
        // Tagad: ir dzīva DB -> SIF rindas pārnes no tās (tas pats ceļš, kas --skip-sif) un
        // turpina ar brīdinājumu; nav dzīvas DB (pirmā reize) -> publicē bez SIF, skaļi.
        $sifKlume = "SIF avots deva tikai $sifCount ierakstus — izskatās pēc bojāta avota";
        $pdo->exec("DELETE FROM grants WHERE source = 'SIF'");
        $sifCount = 0;
        gr_log("  ! $sifKlume" . (is_file($DB_FILE) ? '; SIF rindas pārnes no iepriekšējās DB.' : '; pirmajā būvē SIF nav.'));
    }
}

// ═══ Alias-dublikātu apvienošana ═══════════════════════════════════════════
// Portāls dažām tēmām atdod DIVUS ierakstus ar diviem identifikatoriem. Mērīts: 4 Euratom
// pāri (ar un bez "-LS-") un 3 EIC Pathfinder pāri (ar un bez gada). Lietotājs redz sešas
// Pathfinder iespējas trīs vietā, un kopsummā divkārt ieskaitīti 320,5 milj. €.
//
// Divas kārtulas, abas konservatīvas. Nosaukumu sakritība VIENA nepietiek: JUST-2027-JACC-
// EJUSTICE-DIGITAL un -RIGHTS ir īsti dažādas tēmas ar vienu vispārīgu nosaukumu, un tās
// abas paliek.
//   A) Pieteikšanās saite nodod: ieraksta ?topic= rāda uz CITA ieraksta identifikatoru un
//      nosaukumi sakrīt. Paturam to, kam saite ir — citādi paliktu ieraksts bez pogas.
//      Nosaukuma pārbaude sargā WIDERA "ERA Fellowships", kuras saite ved uz MSCA-PF formu:
//      tur nosaukumi atšķiras, tāpēc tās paliek atsevišķi (tas ir īsts alias, ne dublikāts).
//   B) Novecojis identifikators bez gada: viens konkurss, viens nosaukums, viens termiņš un
//      viens budžets, un TIKAI viens no identifikatoriem sākas ar konkursa identifikatoru.
//      Paturam to, kas sākas.
$dubl = [];
foreach ($pdo->query("SELECT id, title, submit_url FROM grants WHERE source='EU' AND submit_url IS NOT NULL") as $r) {
    if (!preg_match('/[?&]topic=([^&]+)$/i', (string)$r['submit_url'], $m)) continue;
    $merkis = 'EU:' . rawurldecode($m[1]);
    if (strcasecmp($merkis, (string)$r['id']) === 0) continue;
    $q = $pdo->prepare("SELECT id FROM grants WHERE source='EU' AND upper(id)=upper(?) AND title=? AND id<>?");
    $q->execute([$merkis, $r['title'], $r['id']]);
    if ($cits = $q->fetchColumn()) $dubl[(string)$cits] = 'saite no ' . $r['id'];
}
foreach ($pdo->query("SELECT a.id ida, b.id idb, a.call_id FROM grants a JOIN grants b
        ON a.call_id = b.call_id AND a.title = b.title
       AND COALESCE(a.deadline_date,'') = COALESCE(b.deadline_date,'')
       AND COALESCE(a.budget_total,-1) = COALESCE(b.budget_total,-1)
     WHERE a.source='EU' AND b.source='EU' AND a.id < b.id AND a.call_id IS NOT NULL") as $r) {
    $ca = str_starts_with(substr((string)$r['ida'], 3), (string)$r['call_id']);
    $cb = str_starts_with(substr((string)$r['idb'], 3), (string)$r['call_id']);
    if ($ca && !$cb)      $dubl[(string)$r['idb']] = 'novecojis ID pret ' . $r['ida'];
    elseif ($cb && !$ca)  $dubl[(string)$r['ida']] = 'novecojis ID pret ' . $r['idb'];
}
if ($dubl) {
    $del = $pdo->prepare("DELETE FROM grants WHERE id = ?");
    foreach ($dubl as $id => $kapec) { $del->execute([$id]); gr_log("  · dublikāts izmests: $id ($kapec)"); }
    $euCount -= count($dubl);
}
gr_log('Alias-dublikāti apvienoti: ' . count($dubl) . '.');

// ═══ Budžeta tvēruma atzīmēšana (otrais piegājiens) ═════════════════════════
// Ja konkursā VISĀM tēmām budžets ir identisks, tā nav sakritība, bet pazīme, ka portāls
// katrai tēmai atkārto visa konkursa aploksni (EDF-2026-DA: 11 tēmas x 422 milj.).
// Tad summa ir konkursa, ne tēmas, un lapai tā jāsauc citā vārdā.
// BALVAS no šīs kārtulas izņemtas. Balvu konkursā portāls katrai tēmai dod SAVU darbības
// rindu, un summas sakrīt tāpēc, ka visas balvas ir vienāda lieluma (5 x 25 000 € Erasmus
// sportā, 3 x 400 000 € WIDERA dzimumu balvās). Kārtula tās uzskatīja par kopīgu aploksni un
// lapa rakstīja "šīs tēmas budžets ir mazāks" pie 25 000 € balvas, kas nav taisnība.
$pdo->exec("UPDATE grants SET budget_scope = 'konkurss' WHERE source='EU' AND model <> 'balva' AND call_id IN (
    SELECT call_id FROM grants WHERE source='EU' AND model <> 'balva'
      AND budget_total IS NOT NULL AND call_id IS NOT NULL
    GROUP BY call_id HAVING COUNT(*) > 1 AND COUNT(DISTINCT budget_total) = 1)");
$scoped = $pdo->query("SELECT COUNT(*) FROM grants WHERE budget_scope='konkurss'")->fetchColumn();
gr_log("Budžeta tvērums: $scoped tēmas rāda KONKURSA aploksni (ne tēmas budžetu).");

// ═══ Izlaisto avotu pārnese no iepriekšējās DB ══════════════════════════════
// Bez šī 'skip' būtu datu dzēšana. Pārnesam tikai to avotu, kas ŠAJĀ palaišanā netika vākts.
$skipped = [];
if (isset($opts['skip-eu']))  $skipped[] = 'EU';
if (isset($opts['skip-sif']) || $sifKlume !== '') $skipped[] = 'SIF';
if ($skipped && is_file($DB_FILE)) {
    $carried = 0;
    try {
        $old = new PDO('sqlite:' . $DB_FILE);
        $old->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Kolonnu ŠĶĒLUMS, ne jaunās shēmas saraksts: ja dzīvā DB ir no vecākas versijas,
        // "SELECT jaunā_kolonna FROM veca_db" izgāztos, un catch to noriktu par datu zudumu.
        $newCols = array_column($pdo->query("PRAGMA table_info(grants)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        $oldCols = array_column($old->query("PRAGMA table_info(grants)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        $shared = array_values(array_intersect($newCols, $oldCols));
        if (!$shared) throw new RuntimeException('vecajai DB nav nevienas kopīgas kolonnas');
        $cols = implode(',', $shared);
        $in = $pdo->prepare("INSERT OR IGNORE INTO grants ($cols) VALUES ("
            . rtrim(str_repeat('?,', count($shared)), ',') . ")");
        $ph = implode(',', array_fill(0, count($skipped), '?'));
        $q = $old->prepare("SELECT $cols FROM grants WHERE source IN ($ph)");
        $q->execute($skipped);
        $pdo->beginTransaction();
        foreach ($q->fetchAll(PDO::FETCH_NUM) as $row) { $in->execute($row); $carried++; }
        $pdo->commit();
    } catch (Throwable $e) {
        fwrite(STDERR, "Izlaisto avotu pārnese neizdevās: " . $e->getMessage() . "\n"
             . "Būve pārtraukta, lai atomiskā nomaiņa neizdzēstu " . implode('/', $skipped) . " ierakstus.\n");
        exit(1);
    }
    gr_log('Pārnesti no iepriekšējās DB (' . implode(',', $skipped) . '): ' . $carried . ' ieraksti.');
    if ($sifKlume !== '' && in_array('SIF', $skipped, true)) $sifCount = $carried;
    if ($carried === 0) {
        // Ja vecajā DB izlaistā avota ieraksti BIJA, bet pārnesām 0, tā ir kļūme, ne tukšums.
        $bija = (int)$old->query("SELECT COUNT(*) FROM grants WHERE source IN ('"
              . implode("','", $skipped) . "')")->fetchColumn();
        if ($bija > 0) {
            fwrite(STDERR, "Pārnese atdeva 0 ierakstu, lai gan vecajā DB to bija $bija. Būve pārtraukta.\n");
            exit(1);
        }
    }
}

// ═══ Publiskās adreses (slug) ════════════════════════════════════════════════
// Adrese nāk no identifikatora, kas ES portālā ir stabils un runājošs
// (EU:HORIZON-MISS-2026-04-CIT-01 -> horizon-miss-2026-04-cit-01). Avota prefiksu
// paturam, lai SIF un ES numuri nesadurtos: SIF id ir tikai cipari.
// SADURSMES nav teorētiskas: mazie/lielie burti un pieturzīmes sakļaujas, tāpēc
// pēc normalizēšanas divi atšķirīgi identifikatori var dot vienu virkni. Tādā gadījumā
// otrais dabū skaitli galā, un secība ir noteikta ar ORDER BY id, lai slug nemainītos
// no būves uz būvi (mainīga adrese = mirusi saite meklētājā).
gr_state(['stage' => 'slug']);
$slugUpd = $pdo->prepare("UPDATE grants SET slug = ? WHERE id = ?");
$aiznemts = [];
$slugN = 0;
$pdo->beginTransaction();
foreach ($pdo->query("SELECT id FROM grants ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $gid) {
    $pamats = gr_slug((string)$gid);
    $slug = $pamats;
    for ($i = 2; isset($aiznemts[$slug]); $i++) $slug = $pamats . '-' . $i;
    $aiznemts[$slug] = true;
    $slugUpd->execute([$slug, $gid]);
    $slugN++;
}
$pdo->commit();
gr_log("Publiskās adreses: $slugN.");

// ═══ Tulkojumi ══════════════════════════════════════════════════════════════
// Divi soļi ar nolūku: vispirms AIZPILDA no keša (lēti, bez tīkla), pēc tam pēc pieprasījuma
// iztulko to, kas kešā vēl nav. Bez --tulkot būve nekad neiet uz Gemini un nemaksā.
if (!isset($opts['bez-tulkojumiem'])) {
    gr_state(['stage' => 'tulkojumi']);
    $st = gr_tulk_sinhronize($pdo, 'gr_log');
    if (isset($opts['tulkot']) && $st['rinda'] > 0) {
        $lim = isset($opts['tulkot-limit']) ? max(0, (int)$opts['tulkot-limit']) : 0;
        gr_log('Tulko ' . ($lim > 0 ? "līdz $lim" : 'visus ' . $st['rinda']) . ' tekstus...');
        $res = gr_tulk_darbs($lim, 'gr_log');
        gr_log(sprintf('Tulkoti %d, kļūmes %d, izmaksas %.3f €.', $res['tulkoti'], $res['klumes'], $res['eur']));
        // Otrā aizpilde: tikko iztulkotais jāieliek arī šajā DB, citādi tas parādītos tikai
        // nākamajā būvē.
        if ($res['tulkoti'] > 0) gr_tulk_aizpildi($pdo, 'gr_log');
    }
}

// ═══ Meta un atomāra publicēšana ═════════════════════════════════════════════
$mIns = $pdo->prepare("INSERT OR REPLACE INTO meta VALUES (?,?)");
$mIns->execute(['built_at', gmdate('Y-m-d H:i') . ' UTC']);
$mIns->execute(['eu_count', (string)$euCount]);
$mIns->execute(['sif_count', (string)$sifCount]);
$mIns->execute(['eu_budget_hits', (string)$budgetHits]);
// Brīdinājumi, ko lapa un panelis var parādīt: nepilnas tēmu detaļas, bojāts SIF avots.
$mIns->execute(['bridinajums', trim(implode('; ', array_filter([$sifKlume, $detalasNepilnas])))]);
// Datu momentuzņēmuma vecums, ne tikai būves laiks: 'Atjaunots' lapā nedrīkst nozīmēt
// "šodien palaidām skriptu", ja pats ES fails ir nedēļu vecs.
$bulkPath = $CACHE_DIR . '/grantsTenders.json';
if (is_file($bulkPath)) $mIns->execute(['eu_bulk_date', gmdate('Y-m-d H:i', (int)filemtime($bulkPath)) . ' UTC']);
if ($gr_apturets) {
    $pdo = null;
    @unlink($tmp);
    gr_log('🛑 Būve apturēta ar STOP — dzīvā datubāze NAV aizstāta.');
    gr_state(['stage' => 'apturets', 'status' => 'error', 'error' => 'apturēts ar STOP karodziņu']);
    exit(4);
}
$mIns->execute(['built_at_riga', date('Y-m-d H:i')]);
$pdo = null;
if (!rename($tmp, $DB_FILE)) {
    gr_state(['stage' => 'kluda', 'status' => 'error', 'error' => 'nomaiņa neizdevās']);
    fwrite(STDERR, "Neizdevās pārcelt $tmp -> $DB_FILE\n");
    exit(1);
}
// VIETNES KARTE UZREIZ PĒC PUBLICĒŠANAS. Bez šī tā gaidītu nakts kopējo būvi (02:00), un
// starp abām karte solītu adreses, kuru vairs nav: pirmajā automātiskajā palaišanā ES plūsma
// pameta 3 konkursus, un to lapas jau atdeva 404, kamēr karte tās joprojām rādīja.
// Kļūda te nav kritiska — datubāze jau ir publicēta, un nakts būve karti pārrakstīs tāpat.
try {
    require_once __DIR__ . '/../lib/sitemap.php';
    gr_raksti_vietnes_karti(granti_docroot() . '/sitemap', 'https://saraksts.lv', 'gr_log');
} catch (Throwable $e) {
    gr_log('  ! vietnes karti neizdevās atjaunināt: ' . $e->getMessage());
}

$__ilgums = round(microtime(true) - $__sakums);
// Maksimālā atmiņa žurnālā: 124 MB JSON json_decode() ir šī skripta dārgākais solis, un uz
// koplietota hostinga memory_limit ir plāna robeža — ja skaitlis tuvojas 1 GB, tas jāzina PIRMS
// kļūmes, ne pēc "Allowed memory size exhausted".
gr_log('Gatavs: ' . $DB_FILE . ' (' . round(filesize($DB_FILE) / 1024) . ' KB, ' . $__ilgums . ' s, atmiņa '
     . round(memory_get_peak_usage(true) / 1048576) . ' MB, limits ' . ini_get('memory_limit')
     . ', TLS pārrāvumi ar pilnu ķermeni: ' . (int)($GLOBALS['gr_izglabti'] ?? 0)
     . ', atkāpes uz sistēmas curl: ' . (int)($GLOBALS['gr_atkapes'] ?? 0) . ').');
gr_state(['stage' => 'gatavs', 'status' => 'ok', 'ieraksti' => $euCount + $sifCount,
          'current' => '', 'error' => '', 'ilgums_s' => $__ilgums]);
if ($bulkNovecojis) {
    // Trīs dienas bez svaiga ES faila vairs nav "slikta diena": sadaļa rāda vecus termiņus ar
    // statusu "ok", un neviens to nepamana. Publicēts IR (veci dati ir labāki par nekādiem),
    // bet izejas kods 5 aptur ķēdi (nemaksā par novecojušu tekstu tulkojumiem) un panelī
    // rādās kā kļūda katru dienu, līdz portāls atgriežas vai kāds paskatās.
    fwrite(STDERR, "ES bulk fails ir $bulkAgeH h vecs un lejupielāde neizdodas trešo dienu — pārbaudi portālu.\n");
    gr_state(['status' => 'error', 'error' => "ES bulk $bulkAgeH h vecs, lejupielāde neizdodas"]);
    exit(5);
}
