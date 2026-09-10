<?php
/**
 * granti.php — GRANTI: neatmaksājamā projektu finansējuma konkursi.
 *
 * Divi avoti vienuviet:
 *   🇪🇺 ES Finansējuma un konkursu portāls (SEDIA) — visi atvērtie un gaidāmie
 *      grantu uzsaukumi (Apvārsnis Eiropa, LIFE, Erasmus+, Digitālā Eiropa u.c.);
 *   🇱🇻 Sabiedrības integrācijas fonds (sif.map.gov.lv) — LV projektu konkursi.
 *
 * ADRESES. Saraksts ir /granti/, viens konkurss — /granti/{slug}, kur slug nāk no
 * datubāzes kolonnas (būve to raksta ar gr_slug()). Maršruts ir DIVĀS vietās, kas
 * jātur sinhroni: router.php (lokālais php -S) un htaccess.txt (produkcija). Vecā
 * ?id=EU:… forma joprojām strādā, bet atbild ar 301 uz kanonisko ceļu.
 *
 * DATI. granti/db/granti.sqlite, ko būvē granti/bin/build.php reizi dienā (cron caur
 * cron/dispatch.php -> darbs granti.diena). Lapa datubāzi tikai LASA un nekad neiet uz
 * ES portālu vai Gemini pieprasījuma laikā.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('granti');   // kopīgais notikumu žurnāls (lib/applog.php)
// Bez šī lapa rēķināja UTC, kamēr visa pārējā vietne strādā Europe/Riga: no pusnakts līdz
// 3:00 pēc Latvijas laika konkurss ar vakardienas termiņu vēl skaitījās dzīvs, un "vēl N
// dienas" bija par vienu par daudz. Sk. registrs/lib/timezone.php.
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/timezone.php';
reg_init_timezone();
// E_ALL, ne 0: lib/applog.php apstrādātājs respektē error_reporting(), tāpēc ar nulli
// servera puses brīdinājumi (arī PHP 8.5 deprecācijas) nekad nenonāktu kopīgajā žurnālā un
// lapa klusētu par savām kļūdām. display_errors=0 — apmeklētājs nekad neredz nevienu.
// Tā dara arī top.php, nozare_nace.php un konkursi.php.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/granti/lib/config.php';
$db_file = granti_db_path();
require_once __DIR__ . '/granti/lib/teksts.php';
// ── Maršruts un kanoniskā adrese ────────────────────────────────────────────
$__celsPrasits = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

/**
 * Slug nāk NO CEĻA, ne no ?t=.
 *
 * .htaccess pārrakstīšana lieto [QSA], un Apache pievieno apmeklētāja vaicājuma virkni
 * AIZ ceļa parametra: /granti/x?t=y kļūst par "t=x&t=y". PHP no dublikātiem patur PĒDĒJO,
 * tātad apmeklētājs varēja aizstāt ceļa daļu ar savu — /granti/x?t=z atdeva pavisam citu
 * konkursu vai visu sarakstu, un kanonizācija to vēl pārvirzīja. Ceļš ir vienīgais avots,
 * kam var uzticēties; ?t= paliek tikai tiešai granti.php izsaukšanai un router.php vajadzībām.
 */
$slug_q = '';
if (preg_match('~^' . preg_quote(GRANTI_URL_BASE, '~') . '([^/]*)/?$~', $__celsPrasits, $__mm)) {
    $slug_q = rawurldecode($__mm[1]);
} elseif (isset($_GET['t']) && is_string($_GET['t'])) {
    $slug_q = $_GET['t'];
}
$detSlug = preg_match('/^[a-z0-9-]{1,200}$/', $slug_q) ? $slug_q : '';
// Ja slug ir dots, bet formātam neatbilst, tā ir nederīga adrese, ne saraksta lapa. Agrāk tāda
// klusi atdeva sarakstu ar HTTP 200, kaut URL joprojām rādīja to pašu virkni — meklētājs to
// indeksētu kā dublikātu, un cilvēks nesaprastu, kur pazuda konkurss.
$detNederigs = ($slug_q !== '' && $detSlug === '');
if ($detNederigs) http_response_code(404);

// VECĀ ADRESE -> 301. ?id=EU:HORIZON-… bija izstrādes formāts; tas paliek strādājošs, bet
// atbild ar pārvirzi uz /granti/{slug}, lai indeksā nav divu adrešu vienam konkursam.
$id_q = $_GET['id'] ?? '';
if ($detSlug === '' && is_string($id_q) && $id_q !== ''
    && preg_match('/^(EU|SIF):[A-Za-z0-9._-]{1,120}$/', $id_q)) {
    header('Location: ' . GRANTI_URL_BASE . gr_slug($id_q), true, 301);
    exit;
}

// KANONISKĀ FORMA. Maršruts pieņem gan /granti, gan /granti/, gan /granti/{slug}/, un
// /granti.php ir sasniedzams tieši (docroot saknes .php htaccess neaizver). Bez šī katra
// forma pati sevi pasludinātu par kanonisko un sadaļa indeksā būtu divas reizes.
$__celsKanon = GRANTI_URL_BASE . ($detSlug !== '' ? $detSlug : '');
$__mususadala = rtrim($__celsPrasits, '/') === rtrim(GRANTI_URL_BASE, '/')
             || str_starts_with($__celsPrasits, GRANTI_URL_BASE)
             || $__celsPrasits === '/granti.php';
if ($__celsPrasits !== '' && $__celsPrasits !== $__celsKanon && !$detNederigs && $__mususadala) {
    $__qs = (string)($_SERVER['QUERY_STRING'] ?? '');
    // 't' un 'id' ir ceļa daļa, ne filtrs — vaicājuma virknē tie nedrīkst atgriezties.
    parse_str($__qs, $__p); unset($__p['t'], $__p['id']);
    $__qs = http_build_query($__p);
    header('Location: ' . $__celsKanon . ($__qs !== '' ? '?' . $__qs : ''), true, 301);
    exit;
}
// Šodiena PĒC RĪGAS LAIKA. SQLite date('now') vienmēr atgriež UTC un PHP zonu neievēro,
// tāpēc datumu rēķina PHP puse un SQL to saņem kā gatavu virkni.
$sodien = date('Y-m-d');
$d7  = date('Y-m-d', strtotime('+7 days'));
$d30 = date('Y-m-d', strtotime('+30 days'));
$d90 = date('Y-m-d', strtotime('+90 days'));
const GR_PAGE_SIZE = 50;

// ── Pieprasījuma parametri ──────────────────────────────────────────────────
$q    = trim(gr_get('q'));
/**
 * Viena vieta, kur GET vērtība kļūst par virkni.
 *
 * (string)$_GET['x'] uz masīva ievades (?statuss[]=x) met "Array to string conversion" —
 * septiņi tādi brīdinājumi bija septiņos parametros. Lapā tie nebija redzami tikai tāpēc,
 * ka toreiz lapai bija error_reporting(0); tagad ir E_ALL ar display_errors=0, un tāds
 * brīdinājums aizietu kopīgajā žurnālā — tāpēc ievade jāsakārto šeit, ne jāslēpj.
 * Masīvs, objekts un null te visi nozīmē "vērtības nav".
 */
function gr_get(string $v, string $noklusejums = ''): string {
    $x = $_GET[$v] ?? null;
    return is_string($x) ? $x : $noklusejums;
}

$tab  = gr_get('statuss', 'atverti');
// Rādāmā valoda. Noklusējums ir latviešu, ja tulkojums ir; ?val=en rāda oriģinālu.
// Juridiski saistošs ir tikai oriģināls, tāpēc pārslēgs ir redzams, ne paslēpts.
$val = gr_get('val') === 'en' ? 'en' : 'lv';
if (!in_array($tab, ['atverti', 'gaidami', 'visi', 'beigusies'], true)) $tab = 'atverti';
// Termiņa josla: cik dienas atlikušas. Nobriedušās saskarnes (simpler.grants.gov,
// GrantStation) to dod kā gatavas pogas, nevis kalendāru — cilvēks domā "vai pagūšu",
// nevis "no 2026-09-06 līdz 2026-10-06".
$dienas = gr_get('dienas');
if (!in_array($dienas, ['', '7', '30', '90', 'talak'], true)) $dienas = '';
$src  = gr_get('avots');
if (!in_array($src, ['', 'EU', 'SIF'], true)) $src = '';
// Vērtību pret DB pārbauda zemāk, kad $progList ir zināms; formātu jau te, lai nederīgs
// teksts nenonāktu fasešu vaicājumos.
$prog = gr_get('programma');
if ($prog !== '' && !preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $prog)) $prog = '';
require_once __DIR__ . '/granti/lib/nozares.php';
require_once __DIR__ . '/granti/lib/modelis.php';
$noz = gr_get('nozare');
if ($noz !== '' && !isset(GR_NOZARES[$noz])) $noz = '';
// Pieteikšanās modelis: vai drīkstu pieteikties viens. Pēc izpētes tas ir svarīgākais
// atbilstības kritērijs Latvijas MVU un biedrībai — 411 no 604 dzīvajiem prasa konsorciju.
$mod = gr_get('kam');
if ($mod !== '' && !isset(GR_MODELI[$mod])) $mod = '';
// Kaskādes granti: projekti, kas daļu naudas pārdalīs tālāk trešām personām. Mazai
// organizācijai tas ir vienīgais ES naudas veids, ko var dabūt bez konsorcija.
$fstp = gr_get('kaskade') === '1' ? '1' : '';
$sort = gr_get('secibaa', 'termins');
if (!in_array($sort, ['termins', 'budzets', 'nosaukums', 'programma'], true)) $sort = 'termins';
// Griesti pirms aritmētikas: ($page-1)*50 ar PHP_INT_MAX pārplūst uz float, PDO to piesien
// OFFSET vietā, SQLite atbild "datatype mismatch", un visa lapa pārvēršas par kļūdas rindiņu.
$page = min(max(1, (int)gr_get('lapa', '1')), 100000);
// Skats: blīva tabula (noklusējums — pārskatāmāka 50 ierakstiem) vai kartītes.
$view = gr_get('skats', 'tabula');
if (!in_array($view, ['tabula', 'kartes'], true)) $view = 'tabula';

/** Saite uz šo pašu lapu ar pārrakstītiem parametriem (tukšos izlaiž). */
function gr_url(array $over = []): string {
    global $q, $tab, $src, $prog, $sort, $view, $noz, $dienas, $mod, $fstp, $val;
    $p = array_merge(['q' => $q, 'statuss' => $tab, 'avots' => $src, 'nozare' => $noz,
                      'kam' => $mod, 'kaskade' => $fstp, 'dienas' => $dienas,
                      'programma' => $prog, 'secibaa' => $sort, 'skats' => $view,
                      'val' => $val], $over);
    if (($p['statuss'] ?? '') === 'atverti') unset($p['statuss']);
    if (($p['secibaa'] ?? '') === 'termins') unset($p['secibaa']);
    if (($p['skats'] ?? '') === 'tabula') unset($p['skats']);
    if (($p['val'] ?? '') === 'lv') unset($p['val']);
    // Konkursa adrese ir CEĻŠ, ne parametrs: /granti/{slug}. Saraksta saites to nenes līdzi.
    $slug = $over['slug'] ?? null;
    unset($p['slug']);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($p);
    return GRANTI_URL_BASE . ($slug !== null ? rawurlencode((string)$slug) : '')
         . ($qs !== '' ? '?' . $qs : '');
}

// ENT_SUBSTITUTE: bez tā nederīgs UTF-8 baits liek htmlspecialchars atgriezt TUKŠU virkni,
// un lapa rakstīja 'vaicājumam ""', lai gan meklējums nebija tukšs.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** Naudas summa kompakti: 634 milj. € / 80 000 € / —. */
function gr_eur($v): string {
    if ($v === null || $v === '' || (float)$v <= 0) return '—';
    $v = (float)$v;
    if ($v >= 995e6) return number_format($v / 1e9, ($v < 10e9 ? 1 : 0), ',', ' ') . ' mljrd. €';
    if ($v >= 1e6)   return number_format($v / 1e6, ($v < 10e6 && fmod($v, 1e6) > 1e3 ? 1 : 0), ',', ' ') . ' milj. €';
    return number_format($v, 0, ',', ' ') . ' €';
}

/** Dienu skaits līdz datumam ar latvisku locījumu un krāsu klasi. */
function gr_deadline_badge(?string $date, string $status): array {
    if ($date === null || $date === '') return ['bez termiņa', 'gr-dl-none'];
    $days = (int)floor((strtotime($date . ' 23:59:59') - time()) / 86400);
    $dien = fn(int $n) => ($n % 10 === 1 && $n % 100 !== 11) ? 'diena' : 'dienas';
    if ($status === 'closed' || $days < 0) return ['noslēdzies ' . $date, 'gr-dl-past'];
    if ($days === 0) return ['šodien!', 'gr-dl-hot'];
    $txt = $date . ' — vēl ' . $days . ' ' . $dien($days);
    if ($days <= 7)  return [$txt, 'gr-dl-hot'];
    if ($days <= 30) return [$txt, 'gr-dl-soon'];
    return [$txt, 'gr-dl-ok'];
}

/** Tas pats, bet tabulai: atsevišķi datums un atlikušais laiks. */
function gr_deadline_cells(?string $date, string $status): array {
    if ($date === null || $date === '') return ['—', '', 'gr-dl-none'];
    $days = (int)floor((strtotime($date . ' 23:59:59') - time()) / 86400);
    if ($status === 'closed' || $days < 0) return [$date, 'noslēdzies', 'gr-dl-past'];
    if ($days === 0) return [$date, 'šodien!', 'gr-dl-hot'];
    $left = $days . ' d.';
    if ($days <= 7)  return [$date, $left, 'gr-dl-hot'];
    if ($days <= 30) return [$date, $left, 'gr-dl-soon'];
    return [$date, $left, 'gr-dl-ok'];
}

/**
 * Konkursa atzīmes: vienreizējs maksājums, kaskādes granti, divu posmu pieteikums.
 * Katra atzīme atbild uz jautājumu, ko cilvēks citādi uzzinātu tikai konkursa dokumentā.
 */
function gr_flags(array $r, callable $e): void {
    if ((int)($r['lump_sum'] ?? 0) === 1) {
        echo '<span class="gr-fl gr-fl-ls" title="Vienreizējs maksājums (lump sum): nav jāatskaitās par katru izmaksu čeku, bet nauda ir fiksēta.">',
             '💶 vienreizējs maksājums</span>';
    }
    if ((int)($r['fstp'] ?? 0) === 1) {
        $fmax = $r['fstp_max'] !== null ? (float)$r['fstp_max'] : null;
        // "Mazos" drīkst teikt tikai tad, kad summa tiešām ir maza. Daļā konkursu vienai
        // trešajai personai paredzēts līdz 1 milj. € — tur etiķete "mazie granti" melotu.
        // Bez summas neapgalvo, ka granti ir "mazi" — 103 no 158 konkursu summa nav nolasāma,
        // un daļā tur vienai trešajai personai paredzēts līdz 10 milj. €.
        $vards = ($fmax !== null && $fmax <= 200000) ? 'mazos grantus' : 'grantus tālāk';
        $max = $fmax !== null ? ' līdz ' . gr_eur($fmax) : '';
        echo '<span class="gr-fl gr-fl-fstp" title="Projekts daļu naudas pārdalīs tālāk trešām personām (financial support to third parties). Šo grantu vēlāk var pieteikt arī organizācija, kas nav konsorcijā.">',
             '🔁 dalīs ', $vards, $e($max), '</span>';
    }
    $dm = gr_termina_modelis_lv($r['deadline_model'] ?? null);
    if ($dm !== null) {
        $md = (string)($r['deadline_model'] ?? '');
        // "Vairāki griezuma datumi" solīja "ja nepagūsti šo, ir nākamais" arī tad, kad nākamā
        // NAV: 12 no 13 šādiem konkursiem priekšā ir tikai viens datums. Solījumu dodam tikai
        // tad, kad nākotnes termiņu tiešām ir vairāk par vienu. Un 'continuous' nozīmē
        // nepārtrauktu iesniegšanu, ne vairākus datumus — vecais teksts tiem bija svešs.
        if ($md === 'two-stage') {
            $t = 'Vispirms iesniedz īsu koncepciju; pilnu pieteikumu raksta tikai tie, kas tiek tālāk.';
        } elseif ($md === 'continuous') {
            $t = 'Iesniegt var jebkurā brīdī, kamēr konkurss ir atvērts — fiksēta griezuma datuma nav.';
        } else {
            $vel = gr_nakamie_termini($r, date('Y-m-d'));
            $t = $vel > 1
                ? 'Konkursam ir vairāki iesniegšanas datumi — ja nepagūsti šo, ir nākamais.'
                : 'Konkursam bija vairāki iesniegšanas datumi, bet priekšā ir tikai šis — nākamā nav.';
        }
        echo '<span class="gr-fl gr-fl-2st" title="', $e($t), '">⏱ ', $e($dm), '</span>';
    }
}

/**
 * Rādāmais finansējums: [summa, etiķete uzbraucot, vai tā ir konkursa aploksne].
 *
 * Daļā programmu (EDF, CBE, EIC) portāls katrai konkursa tēmai atkārto VISA konkursa
 * aploksni — EDF-2026-RA septiņām tēmām tur ir 110 milj., lai gan katras tēmas teksts
 * nosauc savu (14–20 milj.). Ja tēmas summa ir nolasāma no teksta, rāda to; ja nav,
 * rāda konkursa aploksni, bet SAUC to īstajā vārdā, nevis izliekas, ka tas ir tēmas budžets.
 *
 * @return array{0:?float,1:string,2:bool}
 */
function gr_budzets_radit(array $r): array {
    $topic = isset($r['budget_topic']) && $r['budget_topic'] !== null ? (float)$r['budget_topic'] : null;
    $total = isset($r['budget_total']) && $r['budget_total'] !== null ? (float)$r['budget_total'] : null;
    $konkursa = ($r['budget_scope'] ?? 'tema') === 'konkurss';
    if ($topic !== null) {
        return [$topic, 'Šīs tēmas indikatīvais budžets, nolasīts no konkursa nosacījumiem.', false];
    }
    if ($konkursa && $total !== null) {
        return [$total, 'VISA konkursa aploksne, ko dala visas tā tēmas — šīs tēmas budžets ir mazāks. '
            . 'ES portāls to nepublicē atsevišķi, un konkursa nosacījumos tas šoreiz nav nolasāms.', true];
    }
    return [$total, 'Konkursa tēmai paredzētais finansējums.', false];
}

/**
 * Faktiskais statuss no datumiem. Portāla `status` atpaliek tāpat kā termiņš: astoņi ieraksti
 * ar jau pagājušu atvēršanas datumu sēdēja cilnē "Gaidāmie" ar tekstu "atvērs 2026-07-22"
 * (nākotnes forma par pagātnes datumu), un trīs no tiem ES portālā jau bija "Open".
 */
function gr_statuss(array $r, string $sodien): string {
    $dl = $r['dl'] ?? $r['deadline_date'] ?? null;
    if ($dl !== null && $dl < $sodien) return 'closed';
    if (($r['status'] ?? '') === 'forthcoming') {
        $op = $r['opening_date'] ?? null;
        return ($op === null || $op <= $sodien) ? 'open' : 'forthcoming';
    }
    // Saglabātais 'closed' ar NĀKOTNES termiņu ir pretruna: cilnes ($dzivs) rēķina pēc datuma,
    // tāpēc tāda rinda stāvētu "Atvērtajos" un vienlaikus rādītu "noslēdzies". Datums uzvar,
    // tāpat kā visur citur šajā lapā. Šodien tādu rindu ir 0; kārtula sedz nākotni.
    if (($r['status'] ?? '') === 'closed' && $dl !== null && $dl >= $sodien) return 'open';
    return (string)($r['status'] ?? 'open');
}

/**
 * Cik termiņu vēl PRIEKŠĀ. deadline_count no būves ir konkursa visu laiku termiņu skaits —
 * ESC-HUMAID rādīja "8 term.", lai gan septiņi no tiem ir 2021.–2025. gads un atlicis viens.
 */
function gr_nakamie_termini(array $r, string $sodien): int {
    $ds = (string)($r['deadlines'] ?? '');
    if ($ds === '') return 0;
    return count(array_filter(explode(',', $ds), fn($d) => trim($d) >= $sodien));
}

/**
 * Rādāmais teksts vienam laukam: [teksts, vai tas ir tulkots].
 *
 * Tulkojums nāk no lv_* kolonnām, ko granti_build.php aizpilda no tulkojumu keša. Ja tulkojuma
 * nav (jauns konkurss, vēl neapstrādāts, vai tulkošana atmesta pēc kļūmēm), rāda ORIĢINĀLU —
 * tukšu vietu nerāda nekad. Ar ?val=en oriģinālu rāda vienmēr.
 */
function gr_t(array $r, string $lauks, string $val): array {
    $orig = (string)($r[$lauks === 'title' || $lauks === 'call_title' ? $lauks : 'sec_' . $lauks] ?? '');
    if ($val === 'en') return [$orig, false];
    $lv = (string)($r['lv_' . $lauks] ?? '');
    return $lv !== '' ? [$lv, true] : [$orig, false];
}

/** Vai šim ierakstam vispār ir kaut viens tulkots lauks (pārslēga rādīšanai). */
function gr_ir_tulkojums(array $r): bool {
    foreach (['title','call_title','objective','outcome','scope','eligibility','specific'] as $l)
        if (!empty($r['lv_' . $l])) return true;
    return false;
}

/** Latviešu locījums skaitāmam vārdam: 1 grants, 2 granti, 11 granti, 21 grants. */
function gr_pl(int $n, string $vsk, string $dsk): string {
    return $n . ' ' . (($n % 10 === 1 && $n % 100 !== 11) ? $vsk : $dsk);
}

// ── Dati ────────────────────────────────────────────────────────────────────
$error = null; $rows = []; $total = 0; $meta = [];
$cntOpen = 0; $cntForth = 0; $sumBudget = 0.0; $progList = []; $nozStats = [];
try {
    if (!is_file($db_file)) throw new Exception('Datubāze vēl nav uzbūvēta — palaid: php granti/bin/build.php');
    // PDO::connect() (8.4+) atdod dzinēja apakšklasi Pdo\Sqlite ar createFunction();
    // `new PDO()` arī 8.5 atdod bāzes PDO, kur ir tikai deprecated sqliteCreateFunction().
    $pdo = method_exists('PDO', 'connect') ? PDO::connect('sqlite:' . $db_file) : new PDO('sqlite:' . $db_file);
    // tulko.php naktī raksta lv_* kolonnas tieši šajā failā (DELETE žurnāls): bez gaidīšanas
    // lasītājs tajā sekundē dabūtu SQLITE_BUSY un apmeklētājs 503.
    $pdo->exec('PRAGMA busy_timeout=3000');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Latviešu kārtošanai. SQLite COLLATE NOCASE ir tikai ASCII, tāpēc "Ātrs" nokļūtu aiz
    // "Zebras". Diakritiku pārnesam uz pamatburtu; tas nav pilns latviešu alfabēts (ā un a
    // sakrīt), bet sarakstā tas ir tuvu pareizajam un noteikti tuvāk nekā baitu secība.
    // PHP 8.4+ atdod Pdo\Sqlite ar createFunction(); vecais PDO::sqliteCreateFunction() kopš
    // 8.5 ir deprecated un serverī (tīmeklis 8.5) trāpītu KATRĀ pieprasījumā — 2115 rindas
    // dienā kopīgajā žurnālā ar 50 KB budžetu. Vispirms jaunais, tad vecais (CLI 8.3).
    $__kartotFn = static function (?string $v): string {
            if ($v === null) return '';
            return mb_strtolower(strtr($v, [
                'ā'=>'a','č'=>'c','ē'=>'e','ģ'=>'g','ī'=>'i','ķ'=>'k','ļ'=>'l','ņ'=>'n','š'=>'s','ū'=>'u','ž'=>'z',
                'Ā'=>'A','Č'=>'C','Ē'=>'E','Ģ'=>'G','Ī'=>'I','Ķ'=>'K','Ļ'=>'L','Ņ'=>'N','Š'=>'S','Ū'=>'U','Ž'=>'Z',
            ]), 'UTF-8');
    };
    if (method_exists($pdo, 'createFunction')) {
        $pdo->createFunction('gr_kartot', $__kartotFn, 1);
    } elseif (method_exists($pdo, 'sqliteCreateFunction')) {
        $pdo->sqliteCreateFunction('gr_kartot', $__kartotFn, 1);
    }
    foreach ($pdo->query("SELECT key, value FROM meta") as $m) $meta[$m['key']] = $m['value'];
    // RĀDĀMAIS termiņš = nākamais griezums, kas vēl priekšā. Konkursam ar griezumiem
    // 2026-10-13 / 2027-02-16 / 2027-09-28 deadline_apply ir pēdējais (tas nosaka dzīvumu),
    // bet cilvēkam svarīgs ir tuvākais — citādi lapa rādīja "vēl 385 dienas", kad līdz
    // nākamajai iespējai bija 35. Divu posmu konkursam otrais datums nav iespēja, tāpēc
    // tur paliek deadline_apply (1. posms).
    // json_each ir VIENĪGĀ SQLite JSON funkcija visā kodā, un tīmekļa PHP bibliotēka serverī
    // nav pārbaudīta (iebūvēts kopš 3.38). Ja tās nav, atkāpjamies uz deadline_apply — mazāk
    // precīzi daudzgriezumu konkursiem, bet sadaļa strādā, nevis atdod 503 katrā pieprasījumā.
    $irJson = true;
    try { $pdo->query("SELECT json_valid('[]')"); } catch (Throwable $e) { $irJson = false; }
    $dlSql = $irJson
        ? "(CASE WHEN deadline_model = 'two-stage' THEN deadline_apply
               ELSE COALESCE(
                 (SELECT MIN(value) FROM json_each('[\"' || replace(deadlines, ',', '\",\"') || '\"]')
                  WHERE value >= '$sodien'),
                 deadline_apply, deadline_last, deadline_date) END)"
        : "COALESCE(deadline_apply, deadline_last, deadline_date)";
    $det = null;
    if ($detSlug !== '') {
        $stD = $pdo->prepare("SELECT *, $dlSql dl FROM grants WHERE slug = ?");
        $stD->execute([$detSlug]);
        $det = $stD->fetch() ?: null;
        if ($det === null) { http_response_code(404); }
    }
    // DZĪVS = termiņš vēl nav pagājis. ES portāla statuss atpaliek (mūsu 2026-08-31 būvē
    // 36 ierakstiem statuss ir 'open'/'forthcoming', bet termiņš jau aiz muguras), un
    // portāls pats to pašu dara lielākā mērogā — noklusējuma skats tur rāda 94 % slēgto.
    // Tāpēc statusu rēķinām no datuma KATRĀ pieprasījumā, ne būvē.
    // Dzīvs = PĒDĒJAIS termiņš vēl nav pagājis. Konkursam ar diviem termiņiem (142 kešā)
    // deadline_date ir tas, kas bija tuvākais BŪVES brīdī; pēc tā datuma ieraksts izskatītos
    // beidzies, lai gan nākamais griezums vēl priekšā. deadline_last to sedz; ja kolonnas
    // vēl nav (veca DB), šis vaicājums izgāztos ar "no such column" un catch to parādītu
    // kā tukšu lapu. COLUMN klātbūtni pārbauda tikai lv_title un submit_url gadījumā;
    // pārējām būves kolonnām mēs to apzināti neprasām, jo bez tām DB nav derīga.
    // Dzīvs = vēl var PIETEIKTIES. deadline_apply divu posmu konkursā ir PIRMĀ posma datums,
    // jo pēc tā jauns pieteikums vairs nav iespējams (otrajā posmā iesniedz tikai tie, kas
    // izgāja pirmo). deadline_last te neder — ar to trīs LIFE konkursi, kurus ES portāls
    // rāda kā "Closed", pusgadu stāvētu "Atvērtajos".
    // $dlSql (rādāmais termiņš) ir definēts augstāk, pirms detaļu vaicājuma — viena izteiksme abiem.
    $dzivs = "(COALESCE(deadline_apply, deadline_last, deadline_date) IS NULL
               OR COALESCE(deadline_apply, deadline_last, deadline_date) >= '$sodien')";
    $vNav = "(opening_date IS NOT NULL AND opening_date > '$sodien')";
    $cntOpen   = (int)$pdo->query("SELECT COUNT(*) FROM grants WHERE $dzivs AND (status='open' OR NOT $vNav)")->fetchColumn();
    $cntForth  = (int)$pdo->query("SELECT COUNT(*) FROM grants WHERE status='forthcoming' AND $dzivs AND $vNav")->fetchColumn();
    // Cik ES konkursiem ir latviskojums. Kolonna parādās tikai pēc tam, kad būve ir
    // sasaistījusies ar tulkojumu kešu (--bez-tulkojumiem to izlaiž), tāpēc pārbauda shēmu,
    // nevis paļaujas uz to, ka kolonna ir.
    $cntLv = 0; $cntEu = (int)$pdo->query("SELECT COUNT(*) FROM grants WHERE source='EU'")->fetchColumn();
    $grCols = array_column($pdo->query("PRAGMA table_info(grants)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (in_array('lv_title', $grCols, true))
        $cntLv = (int)$pdo->query("SELECT COUNT(*) FROM grants WHERE source='EU' AND lv_title IS NOT NULL")->fetchColumn();
    // Meklēšanai JĀAPTVER arī latviskotie nosaukumi. Kad saraksts kļuva latvisks, meklēšana
    // joprojām gāja tikai pret angļu `title`, tāpēc lapā redzamais vārds ("Kvalitātes zīme")
    // neatrada pats savu ierakstu. Papildinājums ir tukšs, ja kolonnu vēl nav.
    // Cik konkursiem ir tiešā pieteikšanās saite. Kolonna parādās tikai pēc būves ar
    // gr_pieteiksanas_saite(), tāpēc te tāpat jāskatās shēma, ne jāpieņem.
    $cntPiet = in_array('submit_url', $grCols, true)
        ? (int)$pdo->query("SELECT COUNT(*) FROM grants WHERE submit_url IS NOT NULL
             AND COALESCE(deadline_apply, deadline_last, deadline_date) >= '$sodien'")->fetchColumn()
        : 0;
    // PROGRAMMU VALIDĒ ŠEIT, ne pēc fasetēm. Agrāk nederīga vērtība (?programma=NAVTADAS)
    // tika atmesta tikai no galvenā saraksta, bet palika nozaru un "beigušos" skaitītājos:
    // saraksts rādīja 364 konkursus, nozaru plāksnes pazuda pilnībā, un cilne "Nesen
    // beigušies" solīja 0, kaut to ir 89. Lapa runāja pretī pati sev. Reāls ceļš ir veca
    // grāmatzīme pēc tam, kad portāls pārsauc programmu (AGRIP -> AGRIP2027).
    // Pārbauda pret PILNU kopu, ne pret filtrēto: derīga programma, kas šajā šķēlumā dod 0,
    // nav nederīga vērtība, un lietotājam jāpaliek iespējai to izslēgt.
    $visasProg = $pdo->query("SELECT DISTINCT programme FROM grants WHERE source='EU'")->fetchAll(PDO::FETCH_COLUMN);
    if ($prog !== '' && !in_array($prog, $visasProg, true)) $prog = '';
    $qLvSql = in_array('lv_title', $grCols, true)
        ? " OR lv_title LIKE ? ESCAPE '\\' OR lv_call_title LIKE ? ESCAPE '\\'" : '';
    $qLvN = $qLvSql === '' ? 0 : 2;

    // Konkursa aploksne var būt kopīga vairākām tēmām (EIC: 5 tēmas dala 634 milj.) —
    // kopsummā katru (konkurss, summa) pāri skaita vienreiz.
    // Konkursa aploksne DAŽKĀRT ir kopīga visām tēmām (EIC: 6 tēmas x 634 milj.), bet
    // klasteru konkursos katrai tēmai ir SAVS budžets, kas citām nejauši sakrīt
    // (HORIZON-CL2-2026-01: 26 tēmas, 9 dažādas summas, DEMOCRACY-01 un -02 abas 12 milj.).
    // Vecā DISTINCT (call_id, budget) tos sapludināja un zaudēja 1,7 mljrd. Kopīgo aploksni
    // atpazīst pēc tā, ka konkursā VISĀM tēmām summa ir identiska; citādi summē visas.
    $sumBudget = (float)$pdo->query("SELECT SUM(CASE WHEN n > 1 AND d = 1 THEN b ELSE s END) FROM (
        SELECT COUNT(*) n, COUNT(DISTINCT budget_total) d, MAX(budget_total) b, SUM(budget_total) s
        FROM grants WHERE status IN ('open','forthcoming') AND $dzivs AND budget_total IS NOT NULL
        GROUP BY COALESCE(call_id, id))")->fetchColumn();

    // Nozaru plāksnes: skaits, tuvākais termiņš un aploksne katrai nozarei.
    // Budžets summējas pa (konkurss, summa) pāriem — tā pati unikalizācija kā kopsummā,
    // citādi viena konkursa aploksne saskaitītos tik reižu, cik tam ir tēmu.
    // Plāksnes ir trešā faseta: to skaitītāji jārēķina ar PĀRĒJIEM aktīvajiem filtriem
    // (meklējums, modelis, kaskāde, avots, programma, termiņa josla), bet BEZ pašas nozares.
    // Agrāk tie bija globāli, tāpēc ar q=quantum plāksne solīja 62 un klikšķis deva 2.
    $wNz = []; $aNz = [];
    if ($mod !== '')  { $wNz[] = "model = ?"; $aNz[] = $mod; }
    if ($fstp !== '') $wNz[] = "fstp = 1";
    if ($src !== '')  { $wNz[] = "source = ?"; $aNz[] = $src; }
    if ($prog !== '') { $wNz[] = "programme = ?"; $aNz[] = $prog; }
    if ($q !== '') {
        $wNz[] = "(title LIKE ? ESCAPE '\\' OR programme_label LIKE ? ESCAPE '\\'
                   OR call_title LIKE ? ESCAPE '\\' OR keywords LIKE ? ESCAPE '\\'
                  OR id LIKE ? ESCAPE '\\' OR call_id LIKE ? ESCAPE '\\'$qLvSql)";
        $likeN = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        for ($i = 0; $i < 6 + $qLvN; $i++) $aNz[] = $likeN;
    }
    if ($dienas !== '') {
        $J = ['7' => $d7, '30' => $d30, '90' => $d90];
        $wNz[] = $dienas === 'talak'
            ? "($dlSql IS NULL OR $dlSql > '$d90')"
            : "$dlSql IS NOT NULL AND $dlSql >= '$sodien'
               AND $dlSql <= '" . ($J[$dienas] ?? $d90) . "'";
    }
    $wNzS = $wNz ? ' AND ' . implode(' AND ', $wNz) : '';

    // Programmu izvēlne un cilne "Beigušies" ir tādi paši atlasītāji kā pārējie, tāpēc arī
    // to skaitļiem jārēķinās ar aktīvajiem filtriem. Agrāk tie bija globāli: izvēlne solīja
    // "Apvārsnis Eiropa (459)", bet klikšķis deva 190, un cilne solīja 116 pret reālajiem 9.
    // $wNz jau satur visu, izņemot nozari un programmu, tāpēc nozari pievienojam atsevišķi.
    $wPr = $wNz; $aPr = $aNz;
    if ($noz !== '') { $wPr[] = "nozare = ?"; $aPr[] = $noz; }
    $wPrS = $wPr ? ' AND ' . implode(' AND ', $wPr) : '';
    $stp = $pdo->prepare("SELECT programme, programme_label, COUNT(*) n FROM grants
                          WHERE status IN ('open','forthcoming') AND $dzivs AND source='EU' $wPrS
                          GROUP BY programme ORDER BY n DESC");
    $stp->execute($aPr);
    $progList = $stp->fetchAll();
    // Ja izvēlētā programma šajā šķēlumā nav sarakstā, pievieno to ar nulli, lai izvēlne
    // nekļūtu tukša un filtru varētu noņemt (tā pati problēma, kas bija ar nulles čipiem).
    if ($prog !== '' && !in_array($prog, array_column($progList, 'programme'), true)) {
        $progList[] = ['programme' => $prog, 'programme_label' => $prog, 'n' => 0];
    }

    // Beigušos definīcija ir pretēja visām termiņa joslām, tāpēc ar ieslēgtu joslu skaitītājs
    // vienmēr būtu 0, kamēr cilnes saite to joslu noņem un atver 113 ierakstus.
    $wPrNoD = array_values(array_filter($wPr, fn($c) => !str_contains($c, 'deadline')));
    // Argumentu saraksts paliek TAS PATS tikai tāpēc, ka izmestajos termiņa nosacījumos nav
    // neviena "?" (datumi tur ir PHP salikti literāļi). Ja kāds tur kādreiz pievienos
    // parametru, argumenti pret vietturiem nobīdītos un SQLite mestu HY093. Tāpēc to
    // pārbaudām, nevis pieņemam: pie neatbilstības filtru vienkārši nelietojam.
    $izmestieVietturi = 0;
    foreach ($wPr as $c) if (str_contains($c, 'deadline')) $izmestieVietturi += substr_count($c, '?');
    if ($izmestieVietturi > 0) { $wPrNoD = $wPr; }
    $aPrNoD = $aPr;
    $wPrNoDS = $wPrNoD ? ' AND ' . implode(' AND ', $wPrNoD) : '';
    $stc = $pdo->prepare("SELECT COUNT(*) FROM grants WHERE NOT $dzivs $wPrNoDS");
    $stc->execute($aPrNoD);
    $cntPast = (int)$stc->fetchColumn();

    $nozStats = [];
    // Tā pati atvērts/gaidāms definīcija, kas cilnēm: portāla 'forthcoming' ar jau pagājušu
    // atvēršanas datumu IR atvērts. Agrāk plāksne skaitīja tikai portāla 'open' un solīja
    // 62, kamēr klikšķis deva 63.
    $irAtv = "(status='open' OR NOT (opening_date IS NOT NULL AND opening_date > '$sodien'))";
    $stn = $pdo->prepare("SELECT nozare,
                SUM($irAtv) n_open, SUM(NOT $irAtv) n_forth,
                MIN(CASE WHEN $irAtv AND $dlSql >= '$sodien'
                         THEN $dlSql END) next_dl
              FROM grants WHERE status IN ('open','forthcoming') AND $dzivs $wNzS GROUP BY nozare");
    $stn->execute($aNz);
    foreach ($stn as $r) { $nozStats[(string)$r['nozare']] = $r + ['budget' => 0.0]; }
    $stb = $pdo->prepare("SELECT nozare, SUM(CASE WHEN n > 1 AND d = 1 THEN b ELSE s END) s FROM (
                SELECT nozare, COUNT(*) n, COUNT(DISTINCT budget_total) d,
                       MAX(budget_total) b, SUM(budget_total) s
                FROM grants WHERE status IN ('open','forthcoming') AND $dzivs AND budget_total IS NOT NULL
                      $wNzS
                GROUP BY nozare, COALESCE(call_id, id))
              GROUP BY nozare");
    $stb->execute($aNz);
    foreach ($stb as $r) {
        if (isset($nozStats[(string)$r['nozare']])) $nozStats[(string)$r['nozare']]['budget'] = (float)$r['s'];
    }

    // Bāzes nosacījumi = viss, IZŅEMOT abus fasešu filtrus (termiņa josla un pieteikšanās
    // modelis). Katra faseta savus skaitītājus rēķina pret bāzi + OTRU fasetu, nevis pret
    // sevi pašu — citādi izvēlētā vērtība rādītu visu, bet pārējās nulles.
    $wb = []; $ab = [];
    if ($noz !== '') { $wb[] = "nozare = ?"; $ab[] = $noz; }
    // "Gaidāms" = portāls saka forthcoming UN atvēršanas datums vēl nav pienācis.
    // Pretējā gadījumā konkurss jau ir atvērts, lai kā portāla lauks atpaliktu.
    $velNav = "(opening_date IS NOT NULL AND opening_date > '$sodien')";
    if ($tab === 'atverti')        $wb[] = "$dzivs AND (status = 'open' OR NOT $velNav)";
    elseif ($tab === 'gaidami')    $wb[] = "status = 'forthcoming' AND $dzivs AND $velNav";
    elseif ($tab === 'beigusies')  $wb[] = "NOT $dzivs";
    else                           $wb[] = $dzivs;          // "Visi" = visi DZĪVIE
    if ($fstp !== '') $wb[] = "fstp = 1";
    if ($src !== '')  { $wb[] = "source = ?"; $ab[] = $src; }
    if ($prog !== '') { $wb[] = "programme = ?"; $ab[] = $prog; }
    if ($q !== '') {
        // Meklē arī pēc konkursa un tēmas KODA (HORIZON-CL4-2027-04-DATA-09): cilvēks, kas
        // to atnes no ES portāla vai konsultanta e-pasta, citādi nekad neatrada ierakstu.
        // ESCAPE ir OBLIGĀTA: SQLite noklusējumā LIKE aizbēgšanas rakstzīmes NAV, tāpēc
        // "\\%" nozīmēja "slīpsvītra + jebkas", un meklējums "55%" atgrieza 0 rezultātu.
        $wb[] = "(title LIKE ? ESCAPE '\\' OR programme_label LIKE ? ESCAPE '\\'
                  OR call_title LIKE ? ESCAPE '\\' OR keywords LIKE ? ESCAPE '\\'
                  OR id LIKE ? ESCAPE '\\' OR call_id LIKE ? ESCAPE '\\'$qLvSql)";
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        for ($i = 0; $i < 6 + $qLvN; $i++) $ab[] = $like;
    }

    $JOSLU_SQL = [
        // Joslas rēķina pēc TĀ PAŠA datuma, kas nosaka dzīvumu — citādi ieraksts, kas ir
        // "dzīvs", var neietilpt nevienā joslā (320 + 10 != 333).
        '7'     => "$dlSql IS NOT NULL AND $dlSql >= '$sodien' AND $dlSql <= '$d7'",
        '30'    => "$dlSql IS NOT NULL AND $dlSql >= '$sodien' AND $dlSql <= '$d30'",
        '90'    => "$dlSql IS NOT NULL AND $dlSql >= '$sodien' AND $dlSql <= '$d90'",
        'talak' => "($dlSql IS NULL OR $dlSql > '$d90')",
    ];
    $MODELA_SQL = "model = ?";

    /** Skaita rindas ar doto nosacījumu kopu. */
    $gr_count = function (array $w, array $a) use ($pdo): int {
        $st = $pdo->prepare("SELECT COUNT(*) FROM grants" . ($w ? ' WHERE ' . implode(' AND ', $w) : ''));
        $st->execute($a);
        return (int)$st->fetchColumn();
    };

    // Termiņa joslu skaitītāji: bāze + modelis (ja izvēlēts).
    $wD = $wb; $aD = $ab;
    if ($mod !== '') { $wD[] = $MODELA_SQL; $aD[] = $mod; }
    $dienuJoslas = [];
    foreach ($JOSLU_SQL as $k => $cond) $dienuJoslas[$k] = $gr_count([...$wD, "($cond)"], $aD);

    // Modeļu skaitītāji: bāze + termiņa josla (ja izvēlēta). Viens GROUP BY, ne pieci vaicājumi.
    $wM = $wb; $aM = $ab;
    if ($dienas !== '' && isset($JOSLU_SQL[$dienas])) $wM[] = '(' . $JOSLU_SQL[$dienas] . ')';
    $modStats = [];
    $stm = $pdo->prepare("SELECT model, COUNT(*) c FROM grants"
        . ($wM ? ' WHERE ' . implode(' AND ', $wM) : '') . " GROUP BY model");
    $stm->execute($aM);
    foreach ($stm as $r) $modStats[(string)$r['model']] = (int)$r['c'];

    // Kaskādes skaitītājs: tie paši filtri, tikai ar fstp=1 (un bez tā, ja jau ieslēgts).
    $wF = array_values(array_filter($wb, fn($c) => $c !== "fstp = 1"));
    if ($mod !== '')    { $wF[] = $MODELA_SQL; }
    $aF = $ab; if ($mod !== '') $aF[] = $mod;
    if ($dienas !== '' && isset($JOSLU_SQL[$dienas])) $wF[] = '(' . $JOSLU_SQL[$dienas] . ')';
    $cntFstp = $gr_count([...$wF, "fstp = 1"], $aF);

    // Galvenais vaicājums: bāze + abi fasešu filtri.
    $where = $wb; $args = $ab;
    if ($mod !== '')    { $where[] = $MODELA_SQL; $args[] = $mod; }
    if ($dienas !== '' && isset($JOSLU_SQL[$dienas])) $where[] = '(' . $JOSLU_SQL[$dienas] . ')';

    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    if ($sort === 'budzets') {
        // Kārto pēc TĀS summas, ko lapa rāda, citādi konkursa aploksne mestu EDF tēmas augšā.
        $order = "ORDER BY COALESCE(budget_topic, budget_total) IS NULL,
                           COALESCE(budget_topic, budget_total) DESC";
    } elseif ($sort === 'nosaukums') {
        // Kārto pēc TĀ PAŠA nosaukuma, kas ir redzams. Ar angļu `title` latviskais saraksts
        // ailē "Konkurss ↑" izskatījās nesakārtots. COLLATE NOCASE ir tikai ASCII, tāpēc
        // diakritiku normalizējam paši, citādi ā/č/ē šķirotos pēc baita aiz z.
        $order = $val === 'en'
            ? "ORDER BY title COLLATE NOCASE ASC"
            : (in_array('lv_title', $grCols, true)
                ? "ORDER BY gr_kartot(COALESCE(lv_title, title)) ASC"
                : "ORDER BY gr_kartot(title) ASC");   // DB bez tulkojumu kolonnām (--bez-tulkojumiem)
    } elseif ($sort === 'programma') {
        // Programmas iekšienē paliek termiņa secība — citādi kolonna kļūst nelasāma.
        // Termiņus šķiro pēc TĀ PAŠA datuma, ko aile rāda ($dlSql), ne pēc deadline_date.
        // Šodien tie sakrīt visām dzīvajām rindām, bet konkursam ar vairākiem griezuma
        // datumiem tie var atšķirties, un tad secība klusi pārstātu atbilst redzamajam.
        $order = "ORDER BY programme_label COLLATE NOCASE ASC,
                          CASE WHEN $dlSql IS NULL THEN 1 ELSE 0 END, $dlSql ASC";
    } elseif ($tab === 'gaidami') {
        // Nākotnes atvēršanas augoši; pagājušās (portāla novecojušie ieraksti) — beigās.
        $order = "ORDER BY CASE WHEN opening_date IS NULL THEN 2
                               WHEN opening_date < '$sodien' THEN 1 ELSE 0 END,
                          opening_date ASC";
    } else {
        // Nākotnes termiņi augoši; pagājušie (portālā tomēr 'Open') un beztermiņa — beigās.
        $order = "ORDER BY CASE WHEN $dlSql IS NULL THEN 2
                               WHEN $dlSql < '$sodien' THEN 1 ELSE 0 END,
                          $dlSql ASC";
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM grants $w");
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    // dl = tas pats datums, pēc kura rēķina dzīvumu, lai rinda nevarētu vienlaikus būt
    // "Atvērtajos" un rādīt "noslēdzies".
    $st = $pdo->prepare("SELECT *, $dlSql dl
                         FROM grants $w $order LIMIT ? OFFSET ?");
    $st->execute([...$args, GR_PAGE_SIZE, ($page - 1) * GR_PAGE_SIZE]);
    $rows = $st->fetchAll();
} catch (Throwable $ex) { $error = $ex->getMessage(); }

if ($error !== null) {
    // Kļūda ir servera puses, ne apmeklētāja: 503 pasaka meklētājam "atnāc vēlāk", kamēr
    // 200 ar kļūdas tekstu iemācītu tam, ka šī ir lapas normālais saturs.
    http_response_code(503);
    applog_event('ERROR', 'granti', 'db', $error, true);
}
$pages = max(1, (int)ceil($total / GR_PAGE_SIZE));
// Ārpus diapazona esošs lapas numurs deva tukšu lapu ar tekstu "Atrasti 604" — pretrunīgu
// pašu ar sevi. Tagad tas noved uz pēdējo lapu, un rindas vienmēr ir tās, ko sola skaitītājs.
// Šis vaicājums bija ĀRPUS try/catch: izņēmums te deva neapstrādātu fatālu kļūdu un pilnīgi
// tukšu lapu, nevis to pašu kļūdas rindiņu, ko dod visi pārējie vaicājumi.
if ($error === null && $page > $pages && $total > 0) {
    try {
        $page = $pages;
        // dl = tas pats datums, pēc kura rēķina dzīvumu, lai rinda nevarētu vienlaikus būt
        // "Atvērtajos" un rādīt "noslēdzies".
        $st = $pdo->prepare("SELECT *, $dlSql dl
                             FROM grants $w $order LIMIT ? OFFSET ?");
        $st->execute([...$args, GR_PAGE_SIZE, ($page - 1) * GR_PAGE_SIZE]);
        $rows = $st->fetchAll();
    } catch (Throwable $ex) { $error = $ex->getMessage(); $rows = []; }
}

// Programmu krāsas (nozīmīgākajām savas; pārējām neitrāla).
$PROG_COLOR = [
    'HORIZON' => '#1d4ed8', 'LIFE2027' => '#15803d', 'EDF' => '#475569',
    'DIGITAL' => '#7c3aed', 'ERASMUS2027' => '#c2410c', 'CEF2027' => '#0e7490',
    'CERV' => '#be185d', 'EURATOM2027' => '#a16207', 'SIF' => '#9f1239',
];

// ── Galvas mainīgie (tos nolasa registrs/head/head.php) ─────────────────────
// KANONISKAIS URL IR OBLIGĀTS. Bez tā head.php to būvē no REQUEST_URI un nogriež vaicājuma
// virkni, tātad KATRS filtra variants pasludinātu sevi par atsevišķu kanonisko lapu.
const GR_DOMENS = 'https://saraksts.lv';
if ($det !== null) {
    $__nos = (string)(($val === 'lv' ? ($det['lv_title'] ?? '') : '') ?: $det['title']);
    $pageTitle = mb_substr($__nos, 0, 90) . ' | Granti | Saraksts.lv';
    $__t = trim(preg_replace('~\s+~u', ' ',
        strip_tags((string)($det['lv_scope'] ?? $det['sec_scope'] ?? $det['lv_objective'] ?? $det['sec_objective'] ?? ''))) ?? '');
    $pageDesc = $__t !== ''
        ? mb_substr($__t, 0, 155)
        : 'Grantu konkurss: termiņš, finansējums un pieteikšanās nosacījumi.';
    $canonicalUrl = GR_DOMENS . GRANTI_URL_BASE . (string)$det['slug'];
} else {
    $pageTitle = 'Granti — atvērtie projektu finansējuma konkursi (ES + LV) | Saraksts.lv';
    $pageDesc  = 'Neatmaksājamā projektu finansējuma konkursi vienuviet: ES Finansējuma un konkursu portāls '
               . 'un Sabiedrības integrācijas fonds. Termiņi, summas un pieteikšanās.';
    // FILTRĒTS VAI LAPOTS SARAKSTS NAV TĀ PATI LAPA. Ja visiem variantiem atdotu vienu
    // kanonisko /granti/, mēs meklētājam apgalvotu, ka 2. lapa un "tikai vide, gaidāmie"
    // ir tas pats saturs, kas pirmā lapa — tas ir nepatiess un maksā indeksāciju.
    // Tāpēc variants norāda pats uz sevi UN tiek atzīmēts ar noindex,follow: saraksta
    // vienīgā indeksējamā forma ir tīrā /granti/, bet saites no variantiem skaitās.
    $__variants = gr_url();          // pašreizējie filtri kanoniskajā formā
    $__navNoklus = $__variants !== GRANTI_URL_BASE || $page > 1;
    $canonicalUrl = GR_DOMENS . ($__navNoklus
        ? $__variants . ($page > 1 ? (str_contains($__variants, '?') ? '&' : '?') . 'lapa=' . $page : '')
        : GRANTI_URL_BASE);
}

// Strukturētie dati. Saraksta lapai — maize + saraksts ar redzamajiem konkursiem; konkursa
// lapai — maize + CollectionPage. Nelietojam schema.org/Grant: tas apraksta PIEŠĶIRTU
// finansējumu ar saņēmēju, nevis atvērtu konkursu, uz ko var pieteikties.
$__maize = ['@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Sākums', 'item' => GR_DOMENS . '/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Granti', 'item' => GR_DOMENS . GRANTI_URL_BASE],
]];
if ($det !== null) {
    // Nosaukums TĀDS PATS kā redzamajā lapā: bez lv atkāpes strukturētie dati apgalvotu
    // angļu nosaukumu, kamēr H1 un <title> rāda latvisko — pretruna vienā @graph.
    $__maize['itemListElement'][] = ['@type' => 'ListItem', 'position' => 3,
        'name' => mb_substr($__nos, 0, 120), 'item' => $canonicalUrl];
}
$__graf = [$__maize];
if ($det === null && $rows) {
    $__el = [];
    foreach (array_slice($rows, 0, 10) as $__i => $__r) {
        $__el[] = ['@type' => 'ListItem', 'position' => $__i + 1,
                   'name' => mb_substr((string)(($__r['lv_title'] ?? '') ?: $__r['title']), 0, 120),
                   'url'  => GR_DOMENS . GRANTI_URL_BASE . (string)$__r['slug']];
    }
    $__graf[] = ['@type' => 'ItemList', 'name' => 'Atvērtie grantu konkursi', 'itemListElement' => $__el];
}
$__graf[] = ['@type' => 'CollectionPage', 'name' => $pageTitle, 'description' => $pageDesc,
             'url' => $canonicalUrl, 'inLanguage' => 'lv']
          + (isset($meta['built_at']) ? ['dateModified' => substr((string)$meta['built_at'], 0, 10)] : []);
$pageJsonLd = ['@context' => 'https://schema.org', '@graph' => $__graf];

ob_start();
?>
    <style>
      .gr-wrap{max-width:1100px;margin:20px auto 60px;padding:0 15px}
      .gr-head{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 22px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
      .gr-head h1{margin:0 0 6px;font-size:24px;line-height:1.25}
      .gr-head p{color:#4a4a4a;font-size:14.5px;margin:6px 0 0;line-height:1.55}
      .gr-badge{display:inline-block;background:#fff3cd;border:1px solid #ffe08a;color:#7a5d00;border-radius:6px;padding:3px 10px;font-size:13px;margin-bottom:10px}
      .gr-chips{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 0}
      .gr-chip{background:#eef4ec;border:1px solid #cfe0ca;border-radius:6px;padding:5px 12px;font-size:13.5px}
      .gr-toolbar{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0 4px;align-items:center}
      .gr-toolbar input[type=search]{flex:1;min-width:220px;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px}
      .gr-toolbar select{padding:8px 10px;border:1px solid #ccc;border-radius:6px;font-size:13.5px;background:#fff;max-width:260px}
      .gr-toolbar button{padding:8px 16px;border:none;border-radius:6px;background:#5b8c5a;color:#fff;font-size:14px;cursor:pointer}
      .gr-toolbar button:hover{background:#4d7a4c}
      .gr-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:16px 0 0}
      .gr-tab{padding:6px 16px;border-radius:18px;border:1px solid #d5dad3;background:#fff;color:#333;font-size:13.5px;text-decoration:none}
      .gr-tab:hover{border-color:#5b8c5a}
      .gr-tab.act{background:#5b8c5a;border-color:#5b8c5a;color:#fff;font-weight:600}
      .gr-count{color:#5b6472;font-size:13.5px;margin:14px 2px 8px}
      .gr-card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 18px;margin-bottom:10px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
      .gr-card.gr-closed{opacity:.62}
      .gr-tags{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:7px}
      .gr-prog{display:inline-block;color:#fff;border-radius:4px;padding:2px 9px;font-size:12px;font-weight:600;letter-spacing:.02em}
      .gr-src{font-size:12px;color:#666;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:4px;padding:2px 8px}
      .gr-forth{font-size:12px;color:#1e40af;background:#dbeafe;border:1px solid #bfdbfe;border-radius:4px;padding:2px 8px}
      .gr-card h2{margin:0 0 4px;font-size:16.5px;line-height:1.4;font-weight:600}
      .gr-card h2 a{color:#17173d;text-decoration:none}
      .gr-card h2 a:hover{color:#1d4ed8;text-decoration:underline;text-underline-offset:3px}
      .gr-call{color:#6b7280;font-size:12.5px;margin:0 0 8px}
      .gr-meta{display:flex;flex-wrap:wrap;gap:6px 22px;font-size:13.5px;color:#444}
      .gr-meta b{font-weight:600}
      .gr-dl-hot{color:#b91c1c;font-weight:600}
      .gr-dl-soon{color:#b45309;font-weight:600}
      .gr-dl-ok{color:#15803d}
      .gr-dl-past,.gr-dl-none{color:#999}
      .gr-more{font-size:12px;color:#888;font-weight:400}
      /* ── Detaļu skats ────────────────────────────────────────────────────── */
      /* Ārējā saite bija 12 px #8a8f98 uz balta = 3,2:1 kontrasts (mērīts) un pieslieta
         virsraksta beigām, tāpēc to lasīja kā virsraksta daļu. Zila un lielāka ar īstu
         pieskāriena laukumu; nozīmi nes aria-label, ne bultiņa. */
      .gr-ext{font-size:15px;line-height:1;color:#1d4ed8;margin-left:6px;text-decoration:none;
        display:inline-block;padding:2px 4px;border-radius:4px;vertical-align:baseline}
      .gr-ext:hover,.gr-ext:focus-visible{background:#e8eeff;text-decoration:none;outline:none}
      /* Galvenā darbība detaļu skatā: pieteikšanās notiek portālā, ne šeit. */
      .gr-darbibas{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;margin:14px 0 4px}
      /* 11 px vertikālā atkāpe, ne 10: ar 10 pogas augstums mobilajā skatā bija 43 px
         (mērīts), un pieskāriena mērķim ieteicamais minimums ir 44 px. */
      .gr-poga{display:inline-block;background:#1d4ed8;color:#fff;font-weight:600;font-size:14.5px;
        padding:11px 18px;border-radius:6px;text-decoration:none;min-height:22px}
      .gr-poga:hover,.gr-poga:focus-visible{background:#153bab;color:#fff;text-decoration:none}
      /* Otrā, klusinātā poga: kad ir īstā pieteikšanās saite, apraksta lapa vairs nav galvenā
         darbība, bet to nedrīkst arī paslēpt — tur ir nolikums un dokumenti. */
      .gr-poga2{display:inline-block;background:#fff;color:#1d4ed8;font-weight:600;font-size:14.5px;
        padding:10px 16px;border:1px solid #c3cee8;border-radius:6px;text-decoration:none}
      .gr-poga2:hover,.gr-poga2:focus-visible{background:#f2f5fd;text-decoration:none}
      .gr-poga-piez{font-size:12.5px;color:#5b6472;flex-basis:100%;margin:0}
      /* Redzams tikai ekrāna lasītājam: formas laukiem nebija neviena nosaukuma, un
         lasītājs nosauca tikai izvēlēto vērtību. placeholder par nosaukumu neskaitās. */
      .gr-slepts{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;
        clip:rect(0 0 0 0);white-space:nowrap;border:0}
      .gr-val{font-size:12.5px;color:#1d4ed8}
      .gr-det{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:22px 26px;margin:16px 0;
        box-shadow:0 1px 3px rgba(0,0,0,.08)}
      .gr-det h1{font-size:21px;line-height:1.3;margin:6px 0 10px}
      .gr-det .gr-tags{margin-bottom:10px}
      .gr-isuma{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 18px;
        background:#f7f9f6;border:1px solid #dfe4dc;border-radius:8px;padding:14px 16px;margin:14px 0 6px}
      .gr-isuma div{font-size:13.5px;color:#374151}
      .gr-isuma b{display:block;font-size:12px;color:#5b6472;font-weight:600;margin-bottom:2px;
        text-transform:uppercase;letter-spacing:.03em}
      .gr-sec{margin:22px 0 0}
      .gr-sec h2{font-size:16px;margin:0 0 8px;color:#17173d;padding-bottom:6px;border-bottom:2px solid #eef2ec}
      .gr-sec .gr-body{font-size:14.5px;line-height:1.6;color:#2b3036}
      .gr-sec .gr-body p{margin:0 0 9px}
      .gr-sec .gr-body ul,.gr-sec .gr-body ol{margin:4px 0 10px;padding-left:22px}
      .gr-sec .gr-body li{margin-bottom:5px}
      .gr-sec .gr-body a{color:#3b5a8a}
      .gr-sec.gr-req{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px}
      .gr-sec.gr-req h2{border-bottom-color:#fde68a}
      .gr-birkas{margin:0 0 8px}
      .gr-birku-sk{margin:0 0 12px;padding-left:18px;font-size:13px;color:#3f4653;line-height:1.55}
      .gr-birku-sk li{margin:3px 0}
      .gr-birka{display:inline-block;font-size:12px;background:#fff7ed;border:1px solid #fdba74;color:#9a3412;
        border-radius:4px;padding:2px 8px;margin:0 4px 4px 0;cursor:help}
      .gr-kval{background:#f3f4f6;border-radius:8px;padding:12px 16px;font-size:13.5px;color:#4b5563;line-height:1.55}
      .gr-det .gr-note{margin-top:20px}
      .gr-back{display:inline-block;margin:14px 0 0;color:#5b8c5a;text-decoration:none;font-size:14px}
      .gr-back:hover{text-decoration:underline;text-underline-offset:3px}
      @media (max-width:720px){.gr-det{padding:16px 14px}.gr-isuma{grid-template-columns:1fr 1fr}}

      /* ── Termiņa joslas ──────────────────────────────────────────────────── */
      .gr-days{display:flex;flex-wrap:wrap;gap:7px;align-items:center;margin:16px 0 0}
      .gr-days .lb{font-size:13px;color:#5b6472;margin-right:2px}
      .gr-days a,.gr-days span.off{padding:5px 13px;border:1px solid #d5dad3;border-radius:16px;
        background:#fff;font-size:13px;text-decoration:none;color:#374151;white-space:nowrap}
      .gr-days a:hover{border-color:#5b8c5a;color:#2f5a2e}
      .gr-days a.act{background:#5b8c5a;border-color:#5b8c5a;color:#fff;font-weight:600}
      .gr-days a b{font-weight:600}
      .gr-days a.act b{color:#fff}
      .gr-days .n{opacity:.65;font-size:12px;margin-left:3px}
      .gr-days span.off{color:#5b6472;background:#f2f3f2;border-style:dashed;border-color:#c9ceca}   /* josla bez rezultātiem */
      .gr-days .hot{border-color:#f0b8b8}
      .gr-days .hot b{color:#b91c1c}

      /* ── Pieteikšanās modeļa filtrs ──────────────────────────────────────── */
      .gr-kam{display:flex;flex-wrap:wrap;gap:7px;align-items:center;margin:9px 0 0}
      .gr-kam .lb{font-size:13px;color:#5b6472;margin-right:2px}
      .gr-kam a,.gr-kam span.off{padding:5px 12px;border:1px solid #d5dad3;border-radius:16px;
        background:#fff;font-size:13px;text-decoration:none;color:#374151;white-space:nowrap}
      .gr-kam a:hover{border-color:var(--c,#5b8c5a);color:var(--c,#2f5a2e)}
      .gr-kam a.act{background:var(--c,#5b8c5a);border-color:var(--c,#5b8c5a);color:#fff;font-weight:600}
      .gr-kam .n{opacity:.65;font-size:12px;margin-left:3px}
      .gr-kam span.off{color:#5b6472;background:#f2f3f2;border-style:dashed;border-color:#c9ceca}
      .gr-kam .hint{font-size:12px;color:#5b6472;margin-left:2px;cursor:help;border-bottom:1px dotted #c9ced4}
      /* Atzīmes (vienreizējs maksājums, kaskādes granti, divu posmu pieteikums) */
      .gr-fl{display:inline-block;font-size:11.5px;line-height:1.5;border-radius:4px;
        padding:0 6px;margin:2px 4px 0 0;white-space:nowrap;border:1px solid}
      .gr-fl-ls{background:#eef2ff;border-color:#c7d2fe;color:#3730a3}
      .gr-fl-fstp{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
      .gr-fl-2st{background:#fef3c7;border-color:#fde68a;color:#92400e}
      .gr-c-kam{width:120px;font-size:12.5px;line-height:1.35}
      .gr-c-kam .ic{font-size:14px;margin-right:3px}

      /* ── Nozaru plāksnes ─────────────────────────────────────────────────── */
      .gr-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:10px;margin:16px 0 0}
      .gr-tile{position:relative;display:block;background:#fff;border:1px solid #e5e7eb;border-radius:8px;
        padding:12px 14px 11px;text-decoration:none;color:inherit;box-shadow:0 1px 2px rgba(0,0,0,.05);
        border-left:4px solid var(--c,#64748b);transition:box-shadow .12s,transform .12s}
      .gr-tile:hover{box-shadow:0 3px 10px rgba(0,0,0,.11);transform:translateY(-1px)}
      .gr-tile.act{border-color:#5b8c5a;border-left-color:var(--c,#5b8c5a);background:#f6faf5;
        box-shadow:0 0 0 2px rgba(91,140,90,.25)}
      .gr-tile .ic{font-size:19px;line-height:1}
      .gr-tile .nm{display:block;font-size:14px;font-weight:600;color:#17173d;margin:6px 0 8px;line-height:1.3;
        min-height:36px}
      .gr-tile .big{font-size:22px;font-weight:700;color:var(--c,#333);line-height:1;font-variant-numeric:tabular-nums}
      .gr-tile .big span{font-size:12.5px;font-weight:500;color:#6b7280;margin-left:4px}
      .gr-tile .sub{display:block;color:#5b6472;font-size:12px;margin-top:6px;line-height:1.45}
      .gr-tile .sub b{color:#444;font-weight:600}
      .gr-tile .dl{color:#b45309}
      .gr-tile-all{display:flex;align-items:center;justify-content:center;text-align:center;
        border-style:dashed;border-left-width:1px;color:#5b8c5a;font-weight:600;font-size:14px}
      .gr-crumb{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:18px 0 0;font-size:14px}
      .gr-crumb .cur{font-weight:600;font-size:17px;color:#17173d}
      .gr-crumb a{color:#5b8c5a;text-decoration:none;font-size:13.5px}
      .gr-crumb a:hover{text-decoration:underline;text-underline-offset:3px}
      .gr-secopen{margin:16px 0 0}
      .gr-secopen summary{cursor:pointer;color:#5b8c5a;font-size:13.5px;padding:4px 0}

      /* ── Tabulas skats (blīvais) ─────────────────────────────────────────── */
      .gr-switch{display:flex;gap:0;margin-left:auto}
      .gr-switch a{padding:6px 14px;border:1px solid #d5dad3;background:#fff;color:#555;
        font-size:13px;text-decoration:none}
      .gr-switch a:first-child{border-radius:6px 0 0 6px}
      .gr-switch a:last-child{border-radius:0 6px 6px 0;border-left:none}
      .gr-switch a.act{background:#eef4ec;border-color:#5b8c5a;color:#2f5a2e;font-weight:600}
      .gr-tblwrap{overflow-x:auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;
        box-shadow:0 1px 3px rgba(0,0,0,.06)}
      table.gr-tbl{border-collapse:collapse;width:100%;min-width:1040px;font-size:13.5px}
      .gr-tbl thead th{position:sticky;top:0;z-index:2;background:#f7f9f6;text-align:left;
        font-size:12.5px;font-weight:600;color:#4b5563;letter-spacing:.02em;
        padding:9px 10px;border-bottom:1px solid #dfe4dc;white-space:nowrap}
      .gr-tbl thead th a{color:#4b5563;text-decoration:none}
      .gr-tbl thead th a:hover{color:#2f5a2e;text-decoration:underline;text-underline-offset:3px}
      .gr-tbl thead th.on a{color:#2f5a2e;font-weight:700}
      .gr-tbl td{padding:9px 10px;border-bottom:1px solid #f0f2ef;vertical-align:top}
      .gr-tbl tbody tr:nth-child(even){background:#fcfdfc}
      .gr-tbl tbody tr:hover{background:#f4f8f3}
      .gr-tbl tr.gr-closed td{opacity:.6}
      .gr-c-dl{white-space:nowrap;width:112px}
      .gr-c-dl .d{display:block;font-variant-numeric:tabular-nums}
      .gr-c-dl .l{display:block;font-size:12px;opacity:.85}
      .gr-c-open{font-size:12px;color:#1e40af;white-space:nowrap}
      .gr-c-name a{color:#17173d;text-decoration:none;font-weight:600;line-height:1.35}
      .gr-c-name a:hover{color:#1d4ed8;text-decoration:underline;text-underline-offset:3px}
      .gr-c-name .call{display:block;color:#6b7280;font-size:12px;margin-top:2px;font-weight:400}
      .gr-c-prog{width:190px}
      .gr-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;
        vertical-align:1px;flex:none}
      .gr-c-prog span.t{font-size:12.5px;color:#374151;line-height:1.3}
      .gr-c-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;width:108px}
      .gr-c-num.b{font-weight:600;color:#1f2937}
      .gr-scope{display:block;font-size:11px;font-weight:400;color:#8a6d3b}
      .gr-c-range{white-space:nowrap;font-variant-numeric:tabular-nums;width:150px;color:#374151}
      .gr-c-range .n{display:block;font-size:12px;color:#6b7280}
      .gr-flag{font-size:12px}

      /* ── Šaurs ekrāns ────────────────────────────────────────────────────────
         Tabula ir noklusējuma skats, un 1040 px minimums nozīmē, ka telefonā
         redzamas ~2 no 6 ailēm. Zem 720 px tabula pārkārtojas par rindu blokiem:
         katra rinda kļūst par kartīti ar ailes nosaukumu blakus vērtībai. */
      @media (max-width: 720px) {
        .gr-tblwrap{overflow-x:visible;border:none;box-shadow:none;background:transparent}
        table.gr-tbl{min-width:0;display:block}
        .gr-tbl thead{display:none}
        .gr-tbl tbody,.gr-tbl tr,.gr-tbl td{display:block;width:auto}
        .gr-tbl tr{background:#fff;border:1px solid #e5e7eb;border-radius:8px;
          margin-bottom:10px;padding:10px 12px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
        .gr-tbl tbody tr:nth-child(even){background:#fff}
        .gr-tbl td{border-bottom:none;padding:3px 0}
        .gr-tbl td:empty{display:none}
        /* Ailes nosaukums parādās tikai šeit, jo galvene ir paslēpta. */
        .gr-tbl td[data-l]:not(.gr-c-name):before{content:attr(data-l) ": ";
          color:#5b6472;font-size:12px}
        .gr-c-dl,.gr-c-prog,.gr-c-num,.gr-c-range,.gr-c-kam{width:auto;text-align:left}
        .gr-c-dl .d,.gr-c-dl .l{display:inline}
        .gr-c-name{font-size:15px;margin:4px 0}
        .gr-tiles{grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
        .gr-tile .nm{min-height:0}
      }
      .gr-pager{display:flex;gap:6px;flex-wrap:wrap;margin:18px 0 0}
      .gr-pager a,.gr-pager span{padding:6px 12px;border:1px solid #d5dad3;border-radius:6px;font-size:13.5px;text-decoration:none;color:#333;background:#fff}
      .gr-pager span.cur{background:#5b8c5a;border-color:#5b8c5a;color:#fff;font-weight:600}
      .gr-note{color:#5b6472;font-size:12.5px;margin-top:16px;line-height:1.55}
      .gr-empty{background:#fff;border:1px dashed #ccd;border-radius:8px;padding:30px;text-align:center;color:#777}
    </style>
<?php
$extraHeadContent = ob_get_clean();
// PLĀNĀS LAPAS. 93 konkursiem apraksta nav vispār (visi 83 SIF — tas publicē tikai
// nosaukumu, summas un termiņu — un 10 ES ierakstu). Tāda lapa meklētājam ir tukša, bet
// dzēst to nedrīkst: cilvēkam tā ir vienīgā vieta ar termiņu un saiti. Tāpēc noindex,
// follow — lapa paliek, saites no tās skaitās, indeksā tā neiet.
if ($det !== null) {
    $__satursGarums = strlen((string)($det['sec_objective'] ?? '') . ($det['sec_outcome'] ?? '')
        . ($det['sec_scope'] ?? '') . ($det['sec_eligibility'] ?? '') . ($det['sec_specific'] ?? ''));
    if ($__satursGarums < 500) $extraHeadContent = "<meta name=\"robots\" content=\"noindex,follow\">\n" . $extraHeadContent;
}
// Nederīga adrese un neatrasts konkurss jau atdeva 404; indeksēt tos nav ko.
if ($detNederigs || ($detSlug !== '' && $det === null)) {
    $extraHeadContent = "<meta name=\"robots\" content=\"noindex,follow\">\n" . $extraHeadContent;
} elseif ($det === null && !empty($__navNoklus)) {
    // Filtrēts vai lapots saraksts — sk. piezīmi pie $canonicalUrl.
    $extraHeadContent = "<meta name=\"robots\" content=\"noindex,follow\">\n" . $extraHeadContent;
}
?>
<!DOCTYPE html>
<html lang="lv">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/head/head.php'; ?>
<body>
<?php
// Kura izvēlnes poga ir aktīva. Norādām TIEŠI, jo lokālajā php -S serverī pieprasījums
// iet caur router.php un PHP_SELF vairs nav 'granti.php' — sk. piezīmi registrs/header.php.
$reg_nav_aktiva = 'granti/';
include $_SERVER['DOCUMENT_ROOT'] . '/registrs/header.php'; ?>

<div class="gr-wrap">
  <div class="gr-head">
    <h1>Granti — projektu finansējums, kas nav jāatmaksā</h1>
    <p>Atvērtie un gaidāmie grantu konkursi vienuviet: 🇪🇺 ES Finansējuma un konkursu
       portāls (Apvārsnis Eiropa, LIFE, Erasmus+, Digitālā Eiropa u.c.) un
       🇱🇻 Sabiedrības integrācijas fonds.
       <?php if ($cntLv > 0): ?>ES konkursu nosaukumi latviski ir <?= $cntLv ?> no <?= $cntEu ?>;
       tie ir mašīntulkojumi, un katrā konkursā ir poga uz oriģinālu.<?php else: ?>ES konkursu
       nosaukumi ir oriģinālvalodā.<?php endif; ?>
       <?php if ($cntPiet > 0): ?><?= $e(gr_pl($cntPiet, 'konkursam', 'konkursiem')) ?> ir tieša saite uz pieteikuma
       iesniegšanu.<?php endif; ?></p>
<?php if ($error === null): ?>
    <div class="gr-chips">
      <span class="gr-chip">Atvērti: <strong><?= $cntOpen ?></strong></span>
      <span class="gr-chip">Gaidāmie: <strong><?= $cntForth ?></strong></span>
      <span class="gr-chip">Izsludinātais finansējums: <strong><?= gr_eur($sumBudget) ?></strong></span>
      <span class="gr-chip" title="Kad pēdējoreiz palaista datu būve.">Atjaunots: <strong><?= $e($meta['built_at'] ?? '—') ?></strong></span>
      <?php if (!empty($meta['eu_bulk_date'])): ?>
      <span class="gr-chip" title="ES portāla datu faila datums. Būves palaišana un datu vecums nav viens un tas pats — ja fails ir vecs, arī saraksts ir vecs.">ES dati: <strong><?= $e($meta['eu_bulk_date']) ?></strong></span>
      <?php endif; ?>
      <?php if (!empty($meta['bridinajums'])): /* būve publicēja ar zināmu robu (SIF avots, tēmu detaļas) — jāredz bez ssh */ ?>
      <span class="gr-chip" style="background:#fff3cd;border-color:#ffe08a" title="Būves brīdinājums. Dati ir publicēti, bet nepilni; nākamā būve mēģina vēlreiz.">⚠ <?= $e($meta['bridinajums']) ?></span>
      <?php endif; ?>
    </div>
<?php endif; ?>
  </div>

<?php if ($error !== null): ?>
  <?php /* Iekšējo kļūdas tekstu apmeklētājam NERĀDA: PDO izņēmumā ir datubāzes ceļš un
           vaicājuma fragments, un lapa to sniegtu ikvienam, kas panāk kļūdu. Detaļa aiziet
           kopīgajā žurnālā (lib/applog.php), apmeklētājs saņem ziņu un HTTP 503, lai
           meklētājs kļūdas lapu neieindeksētu kā saturu. */ ?>
  <div class="gr-card" style="margin-top:14px"><p style="color:#a33;margin:0">Datu bāze pašlaik nav pieejama.
    Sadaļa tiek atjaunota reizi dienā — pamēģini pēc brīža.</p></div>
<?php elseif ($detSlug !== ''): ?>
  <?php if ($det === null): ?>
    <div class="gr-empty">Šāds konkurss datubāzē nav atrasts — iespējams, tas vairs nav aktuāls.
      <p style="margin:12px 0 0"><a href="<?= $e(gr_url(['lapa' => null])) ?>" style="color:#5b8c5a">← Uz sarakstu</a></p></div>
  <?php else:
    $r = $det;
    [$dlTxt, $dlCls] = gr_deadline_badge($r['dl'] ?? $r['deadline_date'], gr_statuss($r, $sodien));
    [$mNm, $mIc, $mCol, $mSk] = gr_modelis_info((string)($r['model'] ?? 'atkariba'));
    [$nzNm, $nzIc, $nzCol] = gr_nozare_info((string)$r['nozare']);
    [$bSum, $bTit, $bKonk] = gr_budzets_radit($r);
    $toaLv = $r['action_type'] ? gr_toa_lv(explode(',', (string)$r['action_type'])[0]) : '';
    $sadasIr = false;
    foreach (array_keys(GR_SADALAS) as $k) if (!empty($r['sec_' . $k])) { $sadasIr = true; break; }
  ?>
  <a class="gr-back" href="<?= $e(gr_url(['lapa' => null])) ?>">← Atpakaļ uz sarakstu</a>
  <article class="gr-det">
    <div class="gr-tags">
      <span class="gr-prog" style="background:<?= $PROG_COLOR[$r['programme']] ?? '#64748b' ?>"><?= $e($r['programme_label'] ?: $r['programme']) ?></span>
      <span class="gr-src"><?= $r['source'] === 'EU' ? '🇪🇺 ES portāls' : '🇱🇻 SIF' ?></span>
      <span class="gr-src"><?= $nzIc ?> <?= $e($nzNm) ?></span>
      <?php if (gr_statuss($r, $sodien) === 'forthcoming'): ?><span class="gr-forth">gaidāms</span><?php endif; ?>
    </div>
    <?php [$hTitle, $hTitleLv] = gr_t($r, 'title', $val); [$hCall, ] = gr_t($r, 'call_title', $val); ?>
    <h1><?= $e($hTitle) ?></h1>
    <?php if ($r['call_title'] && $r['call_title'] !== $r['title']): ?><p class="gr-call"><?= $e($hCall) ?> · <?= $e($r['call_id'] ?? '') ?></p><?php endif; ?>

    <?php /* Galvenā darbība. Kad detaļu skats parādījās, virsraksta saite sāka vest uz ŠO lapu,
             un vienīgā saite uz oriģinālu palika licences piezīmē lapas apakšā — 3456 px garā
             lapā uz 3217. pikseļa. Pieteikšanās notiek portālā, tāpēc tā ir poga, ne piezīme. */ ?>
    <?php
      /* Pieteikšanās poga. Saite nāk no būves lauka submit_url: ES pusē to atdod pats portāls
         (links[].url), SIF pusē tā atvasināta no konkursa numura. Rāda TIKAI tad, ja saite
         tiešām ir un konkurss pēc DZĪVAJIEM datumiem ir atvērts — trim MSCA tēmām portāls
         atdod vairākas saites ar vienādu granta veidu, un četrām Euratom tēmām saites nav
         nemaz, tāpēc vārtus liek uz paša lauka, ne uz statusa. */
      $subUrl  = (string)($r['submit_url'] ?? '');
      $stLive   = gr_statuss($r, $sodien);
      $varPiet  = $subUrl !== '' && $stLive === 'open';
      $esAvots  = $r['source'] === 'EU';
    ?>
    <p class="gr-darbibas">
      <?php if ($varPiet): ?>
        <a class="gr-poga" href="<?= $e($subUrl) ?>" rel="noopener" target="_blank">Sākt pieteikumu ↗</a>
        <a class="gr-poga2" href="<?= $e($r['url']) ?>" rel="noopener" target="_blank">Konkursa apraksts ↗</a>
        <span class="gr-poga-piez"><?= $esAvots
          ? 'Atvērsies ES iesniegšanas sistēma. Vajadzīgs bezmaksas EU Login konts.'
          : 'Atvērsies SIF pieteikuma forma. Autorizācija ar Latvija.lv.' ?></span>
      <?php else: ?>
        <a class="gr-poga" href="<?= $e($r['url']) ?>" rel="noopener" target="_blank">
          <?= $esAvots ? 'Atvērt ES portālā' : 'Atvērt SIF lapā' ?> ↗</a>
        <span class="gr-poga-piez"><?php
          if ($stLive === 'forthcoming') echo 'Pieteikšanās vēl nav atvērta. Iesniegšanas sistēma parādās, kad konkurss atveras.';
          elseif ($stLive === 'closed')  echo 'Termiņš ir pagājis, pieteikties vairs nevar.';
          elseif ($esAvots)              echo 'Pieteikšanos sāk konkursa lapā, sadaļā “Start submission”.';
          else                           echo 'Pieteikšanās, pilns nolikums un dokumenti ir tikai tur.';
        ?></span>
      <?php endif; ?>
      <?php /* Rāda arī tad, ja ŠIM ierakstam tulkojuma nav, bet skats ir angļu: citādi SIF
               lapā (kur lv_* nav nekad) cilvēks paliktu angļu režīmā bez izejas. */ ?>
      <?php if (gr_ir_tulkojums($r) || $val === 'en'): ?>
        <a class="gr-val" href="<?= $e(gr_url(['slug' => $r['slug'], 'val' => $val === 'en' ? 'lv' : 'en'])) ?>"><?=
          $val === 'en' ? 'Rādīt latviski' : 'Rādīt oriģinālu angliski' ?></a>
      <?php endif; ?>
    </p>

    <div class="gr-isuma">
      <div><b>Termiņš</b><span class="<?= $dlCls ?>"><?= $e($dlTxt) ?></span>
        <?php if (gr_statuss($r, $sodien) === 'forthcoming' && $r['opening_date']): ?><br>atvērs <?= $e($r['opening_date']) ?><?php endif; ?>
        <?php $nt = ($r['deadline_model'] ?? '') === 'two-stage' ? 0 : gr_nakamie_termini($r, $sodien); if ($nt > 1): ?><br>vēl <?= $nt ?> termiņi<?php endif; ?>
        <?php $dm = gr_termina_modelis_lv($r['deadline_model'] ?? null); if ($dm): ?><br><?= $e($dm) ?><?php endif; ?>
      </div>
      <div title="<?= $e($mSk) ?>"><b>Kam der</b><?= $mIc ?> <?= $e($mNm) ?><?= $toaLv !== '' ? '<br><span style="font-size:12.5px;color:#6b7280">' . $e($toaLv) . '</span>' : '' ?></div>
      <div title="<?= $e($bTit) ?>"><b><?= $bKonk ? 'Visa konkursa aploksne' : 'Finansējums' ?></b><?= $bSum !== null ? gr_eur($bSum) : '—' ?>
        <?php if ($r['expected_grants']): ?><br>≈ <?= $e(gr_pl((int)$r['expected_grants'], 'grants', 'granti')) ?><?php endif; ?></div>
      <?php if ($r['budget_min'] || $r['budget_max']): ?>
      <div><b>Viens grants</b><?php
        if ($r['budget_min'] && $r['budget_max'] && (float)$r['budget_min'] === (float)$r['budget_max']) echo gr_eur($r['budget_max']);
        else echo ($r['budget_min'] ? gr_eur($r['budget_min']) : 'līdz'), $r['budget_min'] ? ' – ' : ' ', ($r['budget_max'] ? gr_eur($r['budget_max']) : ''); ?></div>
      <?php endif; ?>
      <?php if (!empty($r['trl'])): ?><div title="Tehnoloģiskās gatavības līmenis: 1 = pamatprincipi, 9 = sistēma darbojas reālā vidē. Konkurss sagaida, ka projekts sāk vai beidz šajā līmenī."><b>TRL</b><?= $e($r['trl']) ?></div><?php endif; ?>
      <?php if (!empty($r['page_limit'])): ?><div title="Lappušu limits attiecas uz pieteikuma B daļu — projekta aprakstu. Pārsniegtās lapas pēc termiņa padara neredzamas, un vērtētāji tās nelasa; pieteikumu par to nenoraida."><b>Pieteikuma apjoms</b>līdz <?= (int)$r['page_limit'] ?> lpp.<?php
        if (!empty($r['page_limit_stage'])) echo '<br><span style="font-size:12.5px;color:#6b7280">1. posma koncepcijai</span>'; ?></div><?php endif; ?>
    </div>
    <div><?php gr_flags($r, $e); ?></div>

    <?php if (!$sadasIr): ?>
      <p class="gr-note" style="margin-top:16px">Šim konkursam strukturēts apraksts nav pieejams<?= $r['source'] === 'SIF' ? ' — SIF publicē tikai nosaukumu, summas un termiņus; nolikums ir konkursa lapā' : '' ?>.
        <a href="<?= $e($r['url']) ?>" rel="noopener" target="_blank">Atvērt oriģinālu ↗</a></p>
    <?php else: ?>
      <?php $birkas = $r['req_tags'] ? explode('|', (string)$r['req_tags']) : []; ?>
      <?php foreach (GR_SADALAS as $k => $virsr): if (empty($r['sec_' . $k])) continue; ?>
      <section class="gr-sec<?= $k === 'eligibility' || $k === 'specific' ? ' gr-req' : '' ?>">
        <h2><?= $e($virsr) ?></h2>
        <?php if ($k === 'eligibility' && $birkas): ?>
        <?php /* Paskaidrojums ir REDZAMS teksts, ne tikai title=: uzbraukšanas mājiens
                 neeksistē ne uz skārienekrāna, ne ar tastatūru, un tieši šī ir tā vieta,
                 kur cilvēks izlemj, vai viņš vispār drīkst pieteikties. */ ?>
        <p class="gr-birkas"><?php foreach ($birkas as $bk): ?><span class="gr-birka">⚑ <?= $e($bk) ?></span> <?php endforeach; ?></p>
        <ul class="gr-birku-sk"><?php foreach ($birkas as $bk): ?>
          <li><b><?= $e($bk) ?>.</b> <?= $e(gr_birkas_skaidrojums($bk)) ?></li>
        <?php endforeach; ?></ul>
        <?php endif; ?>
        <?php [$secT, ] = gr_t($r, $k, $val); ?>
        <div class="gr-body"><?= $secT /* sanitizēts būvē un pēc tulkošanas: gr_sanitize_html */ ?></div>
      </section>
      <?php endforeach; ?>
      <?php if (empty($r['sec_eligibility'])): ?>
      <section class="gr-sec gr-req"><h2>Īpašās prasības pieteikuma iesniedzējam</h2>
        <div class="gr-body"><p>Šim konkursam nav īpašu prasību papildus programmas vispārējiem noteikumiem — atbilstību nosaka darba programmas B pielikums (valstis, konsorcija sastāvs) un pieteikšanās modelis augstāk.</p></div></section>
      <?php endif; ?>
      <section class="gr-sec"><h2>Kvalifikācija un finansiālā spēja</h2>
        <div class="gr-kval">Konkursa tekstā šī prasība <strong>netiek aprakstīta</strong> — tā ir vienāda visai programmai (darba programmas C pielikums). Praksē: finansiālo spēju pārbauda tikai koordinatoram un tikai tad, ja pieprasītais ES ieguldījums pārsniedz 500 000 €; publiskajām iestādēm, augstskolām un starptautiskām organizācijām to nepārbauda. Darbības spēju vērtē pēc pieteikumā aprakstītās komandas un pieredzes. Tas ir vispārējs skaidrojums, ne šī konkursa teksts.</div></section>
    <?php endif; ?>

    <p class="gr-note"><strong>Avots un licence.</strong> <a href="<?= $e($r['url']) ?>" rel="noopener" target="_blank">Oriģinālais konkursa teksts ↗</a>
      <?php if ($r['source'] === 'EU'): ?>— © Eiropas Savienība, 1995–2026, <a href="https://creativecommons.org/licenses/by/4.0/deed.lv" rel="noopener">CC BY 4.0</a>.
      Teksts šeit ir <strong>sakārtots pa sadaļām un attīrīts no noformējuma</strong> (grozīts).
      <?php if ($val === 'lv' && gr_ir_tulkojums($r)): ?><strong>Latviskojums ir mašīntulkojums</strong> un arī skaitās grozījums; kļūdas tulkojumā ir mūsu, ne Eiropas Komisijas.<?php endif; ?>
      Juridiski saistošs ir tikai oriģināls.<?php endif; ?></p>
  </article>
  <?php endif; ?>
<?php else: ?>

<?php
/**
 * Nozaru plāksnes. Programma NAV nozare (484 no 638 konkursiem ir "Apvārsnis Eiropa",
 * tāpēc plāksne pēc programmas neko nešķirotu) — nozare nāk no programmeDivision
 * klasteriem, sk. granti/lib/nozares.php.
 */
$gr_tiles = function () use ($nozStats, $noz, $e) {
    echo '<div class="gr-tiles">';
    foreach (GR_NOZARES as $k => $_def) {
        $s = $nozStats[$k] ?? null;
        if (!$s || ((int)$s['n_open'] + (int)$s['n_forth']) === 0) continue;   // tukšu plāksni nerāda
        [$nm, $ic, $col] = gr_nozare_info($k);
        $dl = $s['next_dl'] ?? null;
        $days = $dl ? (int)floor((strtotime($dl . ' 23:59:59') - time()) / 86400) : null;
        printf('<a class="gr-tile%s" style="--c:%s" href="%s">',
            $noz === $k ? ' act' : '', $col,
            $e(gr_url(['nozare' => $noz === $k ? '' : $k, 'programma' => '', 'lapa' => null])));
        echo '<span class="ic">', $ic, '</span>';
        echo '<span class="nm">', $e($nm), '</span>';
        $nOpen = (int)$s['n_open']; $nForth = (int)$s['n_forth'];
        $viensk = fn(int $n) => $n % 10 === 1 && $n % 100 !== 11;
        echo '<span class="big">', $nOpen, '<span>', $viensk($nOpen) ? 'atvērts' : 'atvērti', '</span></span>';
        echo '<span class="sub">';
        if ($nForth > 0) echo '+', $nForth, $viensk($nForth) ? ' gaidāms · ' : ' gaidāmi · ';
        echo '<b>', gr_eur($s['budget']), '</b>';
        if ($days !== null && $days >= 0) {
            echo '<br><span class="dl">tuvākais termiņš ', $days === 0 ? 'šodien' : 'pēc ' . gr_pl($days, 'dienas', 'dienām'), '</span>';
        }
        echo '</span></a>';
    }
    if ($noz !== '') {
        printf('<a class="gr-tile gr-tile-all" href="%s">↺ Visas nozares</a>',
            $e(gr_url(['nozare' => '', 'programma' => '', 'lapa' => null])));
    }
    echo '</div>';
};
?>

<?php if ($noz === ''): ?>
  <?php $gr_tiles(); ?>
<?php else: [$nzNm, $nzIc, $nzCol] = gr_nozare_info($noz); ?>
  <div class="gr-crumb">
    <a href="<?= $e(gr_url(['nozare' => '', 'programma' => '', 'lapa' => null])) ?>">← Visas nozares</a>
    <span class="cur"><?= $nzIc ?> <?= $e($nzNm) ?></span>
  </div>
  <details class="gr-secopen">
    <summary>Pārslēgt nozari</summary>
    <?php $gr_tiles(); ?>
  </details>
<?php endif; ?>

  <form class="gr-toolbar" method="get" action="/granti/">
    <?php if ($tab !== 'atverti'): ?><input type="hidden" name="statuss" value="<?= $e($tab) ?>"><?php endif; ?>
    <?php if ($view !== 'tabula'): ?><input type="hidden" name="skats" value="<?= $e($view) ?>"><?php endif; ?>
    <?php if ($noz !== ''): ?><input type="hidden" name="nozare" value="<?= $e($noz) ?>"><?php endif; ?>
    <?php /* Bez šiem trim laukiem poga "Meklēt" klusi atcēla termiņa joslu, pieteikšanās
             modeli un kaskādes filtru — lietotājs redzēja plašāku sarakstu, nekā prasīja. */ ?>
    <?php if ($dienas !== ''): ?><input type="hidden" name="dienas" value="<?= $e($dienas) ?>"><?php endif; ?>
    <?php if ($mod !== ''): ?><input type="hidden" name="kam" value="<?= $e($mod) ?>"><?php endif; ?>
    <?php if ($fstp !== ''): ?><input type="hidden" name="kaskade" value="1"><?php endif; ?>
    <?php /* Un valodu arī: bez šī "Meklēt" angļu skatā klusi atgrieza latvisko. */ ?>
    <?php if ($val !== 'lv'): ?><input type="hidden" name="val" value="<?= $e($val) ?>"><?php endif; ?>
    <label class="gr-slepts" for="gr-q">Meklēt konkursos</label>
    <input id="gr-q" type="search" name="q" value="<?= $e($q) ?>" placeholder="Meklēt nosaukumā, programmā, atslēgvārdos...">
    <label class="gr-slepts" for="gr-avots">Avots</label>
    <select id="gr-avots" name="avots">
      <option value="">Visi avoti</option>
      <option value="EU" <?= $src === 'EU' ? 'selected' : '' ?>>🇪🇺 ES portāls</option>
      <option value="SIF" <?= $src === 'SIF' ? 'selected' : '' ?>>🇱🇻 SIF</option>
    </select>
    <label class="gr-slepts" for="gr-prog">Programma</label>
    <select id="gr-prog" name="programma">
      <option value="">Visas programmas</option>
      <?php foreach ($progList as $p): ?>
      <option value="<?= $e($p['programme']) ?>" <?= $prog === $p['programme'] ? 'selected' : '' ?>>
        <?= $e($p['programme_label'] ?? $p['programme']) ?> (<?= (int)$p['n'] ?>)
      </option>
      <?php endforeach; ?>
    </select>
    <label class="gr-slepts" for="gr-sec">Kārtot</label>
    <select id="gr-sec" name="secibaa">
      <option value="termins">Pēc termiņa</option>
      <option value="budzets" <?= $sort === 'budzets' ? 'selected' : '' ?>>Pēc budžeta</option>
      <option value="nosaukums" <?= $sort === 'nosaukums' ? 'selected' : '' ?>>Pēc nosaukuma</option>
      <option value="programma" <?= $sort === 'programma' ? 'selected' : '' ?>>Pēc programmas</option>
    </select>
    <button type="submit">Meklēt</button>
  </form>

  <?php
  // Termiņa joslas. Kritērijs Nr. 1 pēc izpētes: cilvēks vispirms jautā "vai pagūšu".
  // Joslas ir KUMULATĪVAS (grants.gov paraugs: "next 7 / 30 / 90 days") — cilvēks domā
  // "cik man laika", nevis "kurā intervālā šis krīt", tāpēc 30 ietver arī 7.
  $joslas = ['7' => '≤ 7 dienas', '30' => '≤ 30 dienas', '90' => '≤ 90 dienas', 'talak' => 'vēlāk vai bez termiņa'];
  ?>
  <div class="gr-days">
    <span class="lb">Termiņš:</span>
    <a class="<?= $dienas === '' ? 'act' : '' ?>" href="<?= $e(gr_url(['dienas' => '', 'lapa' => null])) ?>">jebkurš</a>
    <?php foreach ($joslas as $k => $lbl):
        $n = $dienuJoslas[$k] ?? 0;
        // Aktīvo čipu nekad nepārvērš par pelēku tekstu, pat ja skaitītājs ir 0 —
        // citādi pats filtrs, kas iztukšoja sarakstu, pazūd no ekrāna un nav izslēdzams.
        if ($n === 0 && $dienas !== $k): ?>
      <span class="off"><?= $e($lbl) ?> <span class="n">0</span></span>
    <?php else: ?>
      <a class="<?= $dienas === $k ? 'act' : '' ?><?= $k === '7' ? ' hot' : '' ?>"
         href="<?= $e(gr_url(['dienas' => $k, 'lapa' => null])) ?>"><b><?= $e($lbl) ?></b><span class="n"><?= $n ?></span></a>
    <?php endif; endforeach; ?>
  </div>

  <div class="gr-kam">
    <span class="lb">Kam der:</span>
    <a class="<?= $mod === '' ? 'act' : '' ?>" href="<?= $e(gr_url(['kam' => '', 'lapa' => null])) ?>">visi</a>
    <?php foreach (GR_MODELI as $mk => $_md):
        [$mNm, $mIc, $mCol, $mSkaidr] = GR_MODELI[$mk];
        $n = $modStats[$mk] ?? 0;
        if ($n === 0 && $mod !== $mk): ?>
      <span class="off"><?= $mIc ?> <?= $e($mNm) ?> <span class="n">0</span></span>
    <?php else: ?>
      <a class="<?= $mod === $mk ? 'act' : '' ?>" style="--c:<?= $mCol ?>" title="<?= $e($mSkaidr) ?>"
         href="<?= $e(gr_url(['kam' => $mk, 'lapa' => null])) ?>"><?= $mIc ?> <?= $e($mNm) ?><span class="n"><?= $n ?></span></a>
    <?php endif; endforeach; ?>
    <?php if ($cntFstp > 0 || $fstp !== ''): ?>
      <a class="<?= $fstp !== '' ? 'act' : '' ?>" style="--c:#065f46"
         title="Projekti, kas daļu naudas pārdalīs tālāk trešām personām (financial support to third parties). Tos mazos grantus vēlāk var pieteikt arī organizācija, kas nav konsorcijā — tipiski līdz 60 000 €."
         href="<?= $e(gr_url(['kaskade' => $fstp !== '' ? '' : '1', 'lapa' => null])) ?>">🔁 dalīs grantus tālāk<span class="n"><?= $cntFstp ?></span></a>
    <?php endif; ?>
    <span class="hint" title="Modelis atvasināts no ES darbības veida (RIA, IA, CSA, MSCA u.c.) pēc programmu noteikumiem, nevis nolasīts no konkursa teksta. Precīzā prasība vienmēr ir konkursa dokumentā.">mūsu vērtējums</span>
  </div>

  <nav class="gr-tabs">
    <a class="gr-tab <?= $tab === 'atverti' ? 'act' : '' ?>"<?= $tab === 'atverti' ? ' aria-current="page"' : '' ?> href="<?= $e(gr_url(['statuss' => 'atverti', 'lapa' => null])) ?>">Atvērtie</a>
    <a class="gr-tab <?= $tab === 'gaidami' ? 'act' : '' ?>"<?= $tab === 'gaidami' ? ' aria-current="page"' : '' ?> href="<?= $e(gr_url(['statuss' => 'gaidami', 'lapa' => null])) ?>">Gaidāmie</a>
    <a class="gr-tab <?= $tab === 'visi' ? 'act' : '' ?>"<?= $tab === 'visi' ? ' aria-current="page"' : '' ?> href="<?= $e(gr_url(['statuss' => 'visi', 'lapa' => null])) ?>">Visi</a>
    <a class="gr-tab <?= $tab === 'beigusies' ? 'act' : '' ?>"<?= $tab === 'beigusies' ? ' aria-current="page"' : '' ?> style="opacity:.7"
       title="ES konkursi, kurus portāls vēl sauc par atvērtiem vai gaidāmiem, bet kuru termiņš jau pagājis (daļa no 2025. gada), un SIF konkursu vēsture. Šī nav arhīva sadaļa — būve no portāla ņem tikai atvērtos un gaidāmos."
       href="<?= $e(gr_url(['statuss' => 'beigusies', 'dienas' => '', 'lapa' => null])) ?>">Nesen beigušies (<?= $cntPast ?>)</a>
    <span class="gr-switch">
      <a class="<?= $view === 'tabula' ? 'act' : '' ?>" href="<?= $e(gr_url(['skats' => 'tabula'])) ?>">▤ Tabula</a>
      <a class="<?= $view === 'kartes' ? 'act' : '' ?>" href="<?= $e(gr_url(['skats' => 'kartes'])) ?>">▭ Kartītes</a>
    </span>
    <?php /* Valodas pārslēgs bija TIKAI detaļu skatā un tikai tad, ja tam ierakstam ir
             tulkojums. Tāpēc no ?val=en varēja iestrēgt: gr_url() valodu nes līdzi visās
             saitēs, bet neviena vadīkla to atpakaļ nepārslēdza. Sarakstā tas ir vienmēr,
             kolīdz tulkojumi vispār eksistē. */ ?>
    <?php if ($cntLv > 0): ?>
    <span class="gr-switch">
      <a class="<?= $val === 'lv' ? 'act' : '' ?>" href="<?= $e(gr_url(['val' => 'lv'])) ?>" hreflang="lv">Latviski</a>
      <a class="<?= $val === 'en' ? 'act' : '' ?>" href="<?= $e(gr_url(['val' => 'en'])) ?>" hreflang="en">Oriģināls</a>
    </span>
    <?php endif; ?>
  </nav>

  <p class="gr-count"><?php
    $vaic = $q !== '' ? ' vaicājumam "' . $e($q) . '"' : '';
    if ($total === 0) {
        echo 'Nav atrasts neviens konkurss', $vaic, '.';
    } else {
        $viensk = $total % 10 === 1 && $total % 100 !== 11;
        echo $viensk ? 'Atrasts ' : 'Atrasti ', '<strong>', number_format($total, 0, ',', ' '), '</strong> ',
             $viensk ? 'konkurss' : 'konkursi', $vaic, '.';
    } ?></p>

<?php if (!$rows): ?>
  <div class="gr-empty"><?php
    // Vispārīgs "pamēģini citu cilni" nepalīdz, ja saraksta iztukšoja nozares un modeļa
    // kombinācija: paziņojums nosauc, kas tieši ir ieslēgts, un dod vienu klikšķi ārā.
    $akt = [];
    if ($noz !== '')    { [$n1] = gr_nozare_info($noz); $akt[] = $n1; }
    if ($mod !== '')    { [$m1] = gr_modelis_info($mod); $akt[] = $m1; }
    if ($dienas !== '') $akt[] = ($joslas[$dienas] ?? $dienas);
    if ($fstp !== '')   $akt[] = 'dalīs grantus tālāk';
    if ($src !== '')    $akt[] = $src === 'EU' ? 'ES portāls' : 'SIF';
    if ($prog !== '')   $akt[] = 'programma';
    if ($q !== '')      $akt[] = 'meklējums "' . $e($q) . '"';
    if ($akt) {
        echo 'Šai kombinācijai nav neviena konkursa: <strong>', implode('</strong> + <strong>', $akt), '</strong>.';
        echo '<p style="margin:12px 0 0"><a href="', $e(gr_url(['nozare' => '', 'kam' => '', 'dienas' => '',
             'kaskade' => '', 'programma' => '', 'avots' => '', 'q' => '', 'lapa' => null])),
             '" style="color:#5b8c5a">↺ Notīrīt visus filtrus</a></p>';
    } else {
        echo 'Nekas netika atrasts. Pamēģini plašāku meklēšanu vai citu cilni.';
    } ?></div>
<?php endif; ?>

<?php if ($rows && $view === 'tabula'):
    // Kārtojamo kolonnu galvenes: klikšķis pārslēdz secību un atgriež uz 1. lapu.
    $th = function (string $key, string $label, string $cls = '') use ($e, $sort) {
        // Bultiņa rāda ĪSTO virzienu: budžets krīt, pārējie aug. Cilnē "Gaidāmie" rindas
        // kārto pēc atvēršanas, ne termiņa datuma, tāpēc tur termiņa aile NAV aktīvā
        // kārtošana — agrāk bultiņa bija noslēpta, bet 'on' klase (tumšs, treknraksts) palika,
        // un aile izskatījās sakārtota, kaut datumi gāja 2027-03, 2027-02, 2027-02.
        global $tab;
        $melo = $key === 'termins' && $tab === 'gaidami';
        $aktivs = $sort === $key && !$melo;
        $on = $aktivs ? ' on' : '';
        $ar = $aktivs ? ($key === 'budzets' ? ' ↓' : ' ↑') : '';
        $sortAttr = $aktivs ? ' aria-sort="' . ($key === 'budzets' ? 'descending' : 'ascending') . '"' : '';
        return '<th scope="col" class="' . $cls . $on . '"' . $sortAttr . '><a href="'
             . $e(gr_url(['secibaa' => $key, 'lapa' => null])) . '">' . $label . $ar . '</a></th>';
    };
?>
  <div class="gr-tblwrap">
  <table class="gr-tbl">
    <thead>
      <tr>
        <?= $th('termins', 'Termiņš', 'gr-c-dl') ?>
        <?= $th('nosaukums', 'Konkurss') ?>
        <th scope="col" class="gr-c-kam">Kam der</th>
        <?= $th('programma', 'Programma', 'gr-c-prog') ?>
        <?= $th('budzets', 'Finansējums', 'gr-c-num') ?>
        <th scope="col" class="gr-c-range">Viens grants</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        [$dlDate, $dlLeft, $dlCls] = gr_deadline_cells($r['dl'] ?? $r['deadline_date'], gr_statuss($r, $sodien));
        $pc = $PROG_COLOR[$r['programme']] ?? '#64748b';
    ?>
      <?php /* Krāso pēc DZĪVĀ statusa, ne pēc būves brīža: 9 ierakstiem DB statuss vēl nav
               'closed', bet termiņš jau pagājis, un cilnē "Beigušies" tie stāvēja pilnā
               spilgtumā, kaut ailē rakstīts "noslēdzies". */ ?>
      <tr<?= gr_statuss($r, $sodien) === 'closed' ? ' class="gr-closed"' : '' ?>>
        <td class="gr-c-dl <?= $dlCls ?>" data-l="Termiņš">
          <span class="d"><?= $e($dlDate) ?></span>
          <?php if ($dlLeft !== ''): ?><span class="l"><?= $e($dlLeft) ?><?php
            // Divu posmu konkursā otrais datums nav otra iespēja iesniegt, tāpēc skaitu nerāda.
            $nt = ($r['deadline_model'] ?? '') === 'two-stage' ? 0 : gr_nakamie_termini($r, $sodien);
            if ($nt > 1) echo ' · ', $nt, ' term.';
          ?></span><?php endif; ?>
        </td>
        <td class="gr-c-name">
          <?php [$tTitle, ] = gr_t($r, 'title', $val); [$tCall, ] = gr_t($r, 'call_title', $val); ?>
          <a href="<?= $e(gr_url(['slug' => $r['slug'], 'lapa' => $page > 1 ? (string)$page : null])) ?>"><?= $e($tTitle) ?></a>
          <a class="gr-ext" href="<?= $e($r['url']) ?>" target="_blank" rel="noopener"
             aria-label="<?= $e($tTitle) ?> — atvērt oriģinālu <?= $r['source'] === 'EU' ? 'ES portālā' : 'SIF lapā' ?> (jaunā cilnē)"
             title="Atvērt oriģinālu <?= $r['source'] === 'EU' ? 'ES portālā' : 'SIF lapā' ?>">↗</a>
          <?php if ($r['call_title'] && $r['call_title'] !== $r['title']): ?>
            <span class="call"><?= $e($tCall) ?></span>
          <?php endif; ?>
          <?php if (gr_statuss($r, $sodien) === 'forthcoming'): ?>
            <span class="gr-c-open">🚪 gaidāms<?= $r['opening_date'] ? ' — atvērs ' . $e($r['opening_date']) : '' ?></span>
          <?php endif; ?>
          <div><?php gr_flags($r, $e); ?></div>
        </td>
        <td class="gr-c-kam" data-l="Kam der"><?php
          [$mNm, $mIc, $mCol, $mSk] = gr_modelis_info((string)($r['model'] ?? 'atkariba'));
          $toaLv = $r['action_type'] ? gr_toa_lv(explode(',', (string)$r['action_type'])[0]) : '';
        ?><span title="<?= $e($mSk . ($toaLv !== '' ? ' Darbības veids: ' . $toaLv . '.' : '')) ?>"
          ><span class="ic"><?= $mIc ?></span><?= $e($mNm) ?></span></td>
        <td class="gr-c-prog" data-l="Programma">
          <span class="gr-flag"><?= $r['source'] === 'EU' ? '🇪🇺' : '🇱🇻' ?></span>
          <span class="gr-dot" style="background:<?= $pc ?>"></span><span class="t"><?= $e($r['programme_label'] ?: $r['programme']) ?></span>
        </td>
        <?php [$bSum, $bTit, $bKonk] = gr_budzets_radit($r); ?>
        <td class="gr-c-num b" data-l="Finansējums" title="<?= $e($bTit) ?>"><?php
          echo $bSum !== null ? gr_eur($bSum) : '—';
          if ($bKonk) echo '<span class="gr-scope">visa konkursa</span>';
        ?></td>
        <td class="gr-c-range" data-l="Viens grants">
          <?php if ($r['budget_min'] || $r['budget_max']):
            if ($r['budget_min'] && $r['budget_max'] && (float)$r['budget_min'] === (float)$r['budget_max']) {
                echo gr_eur($r['budget_max']);
            } else {
                echo ($r['budget_min'] ? gr_eur($r['budget_min']) : 'līdz'), $r['budget_min'] ? ' – ' : ' ',
                     ($r['budget_max'] ? gr_eur($r['budget_max']) : '');
            }
          else: ?>—<?php endif; ?>
          <?php if ($r['expected_grants']): ?><span class="n">≈ <?= $e(gr_pl((int)$r['expected_grants'], 'grants', 'granti')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<?php foreach (($view === 'kartes' ? $rows : []) as $r):
    [$dlTxt, $dlCls] = gr_deadline_badge($r['dl'] ?? $r['deadline_date'], gr_statuss($r, $sodien));
    $pc = $PROG_COLOR[$r['programme']] ?? '#64748b';
?>
  <article class="gr-card<?= gr_statuss($r, $sodien) === 'closed' ? ' gr-closed' : '' ?>">
    <div class="gr-tags">
      <span class="gr-prog" style="background:<?= $pc ?>"><?= $e($r['programme_label'] ?: $r['programme']) ?></span>
      <span class="gr-src"><?= $r['source'] === 'EU' ? '🇪🇺 ES portāls' : '🇱🇻 SIF' ?></span>
      <?php if (gr_statuss($r, $sodien) === 'forthcoming'): ?><span class="gr-forth">gaidāms</span><?php endif; ?>
      <?php /* "MVU draudzīgs" nozīmīte noņemta 2026-09-06: ES lauks `sme` mūsu 638 kešotajos
               ierakstos ir False 638 reizes no 638, tāpēc nosacījums nekad nenostrādāja.
               Sk. mirušo vārtu principu — vārts, kas visiem atbild "nē", ir sliktāks par tā trūkumu. */ ?>
    </div>
    <?php [$kTitle, ] = gr_t($r, 'title', $val); [$kCall, ] = gr_t($r, 'call_title', $val); ?>
    <h2><a href="<?= $e(gr_url(['slug' => $r['slug'], 'lapa' => $page > 1 ? (string)$page : null])) ?>"><?= $e($kTitle) ?></a>
        <a class="gr-ext" href="<?= $e($r['url']) ?>" target="_blank" rel="noopener"
           aria-label="<?= $e($kTitle) ?> — atvērt oriģinālu <?= $r['source'] === 'EU' ? 'ES portālā' : 'SIF lapā' ?> (jaunā cilnē)"
           title="Atvērt oriģinālu <?= $r['source'] === 'EU' ? 'ES portālā' : 'SIF lapā' ?>">↗</a></h2>
    <?php if ($r['call_title'] && $r['call_title'] !== $r['title']): ?>
    <p class="gr-call"><?= $e($kCall) ?></p>
    <?php endif; ?>
    <div class="gr-meta">
      <span class="<?= $dlCls ?>">📅 <?= $e($dlTxt) ?>
        <?php $nt = ($r['deadline_model'] ?? '') === 'two-stage' ? 0 : gr_nakamie_termini($r, $sodien);
              if ($nt > 1): ?><span class="gr-more">(vēl <?= $nt ?> termiņi)</span><?php endif; ?>
      </span>
      <?php if (gr_statuss($r, $sodien) === 'forthcoming' && $r['opening_date']): ?>
      <span>🚪 Atvērs: <b><?= $e($r['opening_date']) ?></b></span>
      <?php endif; ?>
      <?php [$bSum, $bTit, $bKonk] = gr_budzets_radit($r); if ($bSum !== null): ?>
      <span title="<?= $e($bTit) ?>"><?= $bKonk ? '💶 Visa konkursa aploksne: ' : '💶 Kopā: ' ?><b><?= gr_eur($bSum) ?></b></span>
      <?php endif; ?>
      <?php if ($r['budget_min'] || $r['budget_max']): ?>
      <span>Grants: <b><?php
        if ($r['budget_min'] && $r['budget_max'] && (float)$r['budget_min'] === (float)$r['budget_max']) {
            echo gr_eur($r['budget_max']);
        } else {
            echo ($r['budget_min'] ? gr_eur($r['budget_min']) : 'līdz'), $r['budget_min'] ? ' – ' : ' ',
                 ($r['budget_max'] ? gr_eur($r['budget_max']) : '');
        } ?></b></span>
      <?php endif; ?>
      <?php if ($r['expected_grants']): ?>
      <span>≈ <?= $e(gr_pl((int)$r['expected_grants'], 'grants', 'granti')) ?></span>
      <?php endif; ?>
      <?php [$mNm, $mIc, $mCol, $mSk] = gr_modelis_info((string)($r['model'] ?? 'atkariba')); ?>
      <span title="<?= $e($mSk) ?>"><?= $mIc ?> <?= $e($mNm) ?></span>
    </div>
    <div><?php gr_flags($r, $e); ?></div>
  </article>
<?php endforeach; ?>

<?php if ($pages > 1): ?>
  <nav class="gr-pager">
    <?php if ($page > 1): ?><a href="<?= $e(gr_url(['lapa' => $page - 1])) ?>">←</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++):
        if ($pages > 9 && $i > 2 && $i < $pages - 1 && abs($i - $page) > 2) {
            if ($i === 3 || $i === $pages - 2) echo '<span>…</span>';
            continue;
        } ?>
      <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
      <?php else: ?><a href="<?= $e(gr_url(['lapa' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= $e(gr_url(['lapa' => $page + 1])) ?>">→</a><?php endif; ?>
  </nav>
<?php endif; ?>

  <p class="gr-note"><strong>Avoti.</strong> <a href="https://ec.europa.eu/info/funding-tenders/opportunities/portal/" rel="noopener">ES
    Finansējuma un konkursu portāls</a> — © Eiropas Savienība, 1995–2026; saturs licencēts ar
    <a href="https://creativecommons.org/licenses/by/4.0/deed.lv" rel="noopener">CC BY 4.0</a>
    (Komisijas Lēmums 2011/833/ES un Lēmums C(2019) 1655). <a href="https://sif.map.gov.lv/contests" rel="noopener">Sabiedrības
    integrācijas fonds</a> — atsauce sniegta atbilstoši platformas lietošanas noteikumu 8.6. punktam.
    Šī ir privātpersonas uzturēta bezmaksas vietne; neviena no minētajām iestādēm to neuztur un neapstiprina.
    Juridiski saistošs ir tikai oriģinālais uzsaukums, uz kuru ved saite pie katra ieraksta.
    Budžeti no ES portāla tēmu detaļām; konkursa aploksne var būt kopīga vairākām tēmām, un daļai konkursu budžets nav publicēts.
    ES konkursiem ar vairākiem termiņiem rādīts tuvākais nākotnē esošais.</p>
  <p class="gr-note"><strong>Par ailēm “Kam der” un atzīmēm.</strong> Tās ir mūsu vērtējums,
    kas atvasināts no ES darbības veida (RIA, IA, CSA, MSCA, balva u.c.) pēc publiski
    zināmajiem programmu noteikumiem — nevis lauks, ko portāls publicē. Pašos konkursa
    nosacījumu tekstos atbilstības prasību parasti nav: 481 no 638 gadījumu tur ir tikai
    atsauce uz darba programmas B pielikumu. Tāpēc tur, kur noteikumi pieļauj gan vienu
    pieteicēju, gan partneru grupu, rakstīts “atkarīgs no konkursa”, nevis izvēlēts viens
    variants. <strong>Precīzā prasība vienmēr ir konkursa dokumentā</strong> — šī aile ir
    saraksta sašaurināšanai, ne lēmuma pieņemšanai.</p>

<?php endif; ?>
</div>

<?php $footerRich = 'konkursi'; include $_SERVER['DOCUMENT_ROOT'] . '/registrs/footer/footer.php'; ?>
</body>
</html>
