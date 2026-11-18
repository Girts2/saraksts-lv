<?php
/**
 * granti/lib/ievacejs.php — LEGĀLS tīmekļa ievācējs (robots.txt, ātruma vārsts, kešs).
 *
 * KĀPĒC ATSEVIŠĶS SLĀNIS. build.php gr_http_get() ir "paņem failu no ES portāla" — viens
 * zināms avots ar zināmu licenci (CC BY 4.0) un bez robots ierobežojumiem. Tiklīdz avotu
 * ir divdesmit un daļa no tiem ir svešas vietnes, katram pieprasījumam pirms izsūtīšanas
 * jāatbild uz trim jautājumiem, uz kuriem gr_http_get() neatbild:
 *   1) vai robots.txt to atļauj TIEŠI mums;
 *   2) cik bieži drīkst prasīt (Crawl-delay, un bez tā — mūsu pašu pauze);
 *   3) ko no atbildes drīkst PATURĒT (licence), pretstatā tam, ko drīkst tikai izlasīt.
 * Šis fails ir vienīgā vieta, kur notiek izejošie pieprasījumi uz svešām vietnēm, tāpēc
 * atbildes uz šiem trim jautājumiem nevar apiet nejauši.
 *
 * APZINĀTI NAV "piespiedu" karodziņa. Ja kādam avotam robots.txt saka nē, pareizais solis
 * ir rakstīt vietnes īpašniekam (tā ieguvām ΓΕΜΗ API Grieķijā), nevis pievienot --ignore.
 * Karodziņš, kas reiz ir kodā, agri vai vēlu tiek lietots.
 *
 * ROBOTS.TXT SEMANTIKA (RFC 9309), ieskaitot to, kas parasti tiek izdarīts nepareizi:
 *   · Grupu izvēlas pēc MŪSU marķiera (GR_IEV_MARKIERIS), ne pēc pilnās User-Agent virknes;
 *     ja tāda nav — pēc "*". Vietnes īpašniekam jādod iespēja mūs nosaukt vārdā.
 *   · Uzvar GARĀKAIS ceļa sakritums, ne pirmais; vienādā garumā uzvar Allow.
 *   · robots.txt ar 4xx = viss atļauts. Ar 5xx vai bez atbildes = viss LIEGTS (RFC prasa
 *     tieši tā; pretējais nozīmētu, ka nokritis serveris mums atver visu).
 *   · Content-Signal (Cloudflare 2025) nav robots standarta daļa, bet ir skaidri izteikta
 *     īpašnieka griba: search=no nozīmē, ka avotu neievācam, lai ko teiktu Disallow rindas.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Marķieris, ko vietnes īpašnieks var ierakstīt savā robots.txt, lai runātu tieši ar mums. */
const GR_IEV_MARKIERIS = 'SarakstsLV';
/** Pilnā User-Agent virkne. Kontaktadrese IR daļa no legalitātes: bez tās īpašnieks nevar sūdzēties. */
const GR_IEV_UA = 'Mozilla/5.0 (compatible; SarakstsLV/1.0; +https://saraksts.lv/)';
/** Noklusējuma pauze starp diviem pieprasījumiem uz VIENU hostu, ja robots.txt Crawl-delay nedod. */
const GR_IEV_PAUZE_S = 2.0;
/** Griesti Crawl-delay vērtībai (s). Virs tā avotu šajā būvē vienkārši neievācam. */
const GR_IEV_PAUZE_MAX_S = 30.0;
/** Griesti vienai atbildei. Lapa, kas atdod 50 MB, ir kļūda, ne dati. */
const GR_IEV_MAX_BAITI = 8 * 1024 * 1024;
/** Cik ilgi kešot robots.txt. Biežāk prasīt ir nepieklājīgi, retāk — riskanti. */
const GR_IEV_ROBOTS_TTL = 21600;   // 6 h
/**
 * Anthropic rāpuļu marķieri. IZSTRĀDES REŽĪMĀ (mašīna, kur ievākšanu palaiž MI asistents) ievācējs papildus
 * mūsu grupai ievēro arī šo marķieru PRECĪZĀS grupas: vietne ar "User-agent: ClaudeBot / Disallow: /" no
 * izstrādes sesijas netiek apmeklēta. 2026-09-13 audits: statiskais karodziņš sedza tikai EKII, un šīs sesijas
 * būves apmeklēja arī daugavpils.lv un kandavaspartneriba.lv (kopā ar EKII 13 lapas), kuru robots.txt ClaudeBot
 * liedz. Serverī (granti/data/serveris.flag) to neievēro: tur rāpulis ir SarakstsLV, un noteicošā ir tā vai "*" grupa.
 */
const GR_IEV_MI_MARKIERI = ['claudebot', 'claude-user', 'claude-searchbot', 'anthropic-ai', 'claude-web'];

/** Izstrādes režīms: bez servera karodziņa VAI ar --izstrade. Noklusējums ir aizvērtā puse. */
function gr_iev_izstrades_rezims(): bool {
    if (!empty($GLOBALS['gr_iev_izstrade'])) return true;
    return !is_file(granti_data_dir() . '/serveris.flag');
}

function gr_iev_kesh_dir(): string {
    $d = granti_cache_dir() . '/iev';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

/**
 * Audita pēdas: katrs izejošais pieprasījums ar laiku, kodu un iemeslu, ja liegts.
 * Pašgriešana ar to pašu robežu, kas būves žurnālam: ~130 pieprasījumu naktī nozīmē ap
 * 4 MB gadā, un audita vērtība ir pēdējām nedēļām, ne pirmajai.
 */
function gr_iev_zurnals(string $m): void {
    $f = granti_data_dir() . '/ievacejs.log';
    if (is_file($f) && filesize($f) > 500 * 1024) {
        $saturs = (string)file_get_contents($f);
        @file_put_contents($f, "... (nogriezts) ...\n" . substr($saturs, -200 * 1024), LOCK_EX);
    }
    @file_put_contents($f, date('Y-m-d H:i:s') . '  ' . $m . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Izmet keša failus, kam neviens nav pieskāries $dienas dienas. Kešs aug ar katru jaunu
 * adresi (46 MB pēc vienas dienas ar 454 failiem), un pamesta adrese — beidzies konkurss,
 * pārsaukta lapa — tur paliktu mūžīgi. Sauc būve pēc ievākšanas.
 */
function gr_iev_kesa_tirisana(int $dienas = 30): int {
    $robeza = time() - $dienas * 86400;
    $n = 0;
    foreach (glob(gr_iev_kesh_dir() . '/*') ?: [] as $f) {
        if (str_contains(basename($f), 'robots_')) continue;   // mazi un vienmēr vajadzīgi
        if (is_file($f) && filemtime($f) < $robeza && @unlink($f)) $n++;
    }
    return $n;
}

/** Vai tīkls ir aizstāts ar testa funkciju. VIENS nosacījums abām vietām (aizstājējs un pauzes izlaišana), un tikai komandrindā. */
function gr_iev_tikls_aizstats(): bool {
    return PHP_SAPI === 'cli' && isset($GLOBALS['gr_iev_jelgs_aizstajejs']) && is_callable($GLOBALS['gr_iev_jelgs_aizstajejs']);
}

/** Cik pārvirzes lēcienu gr_iev_get() iet pēc kārtas (katram sava robots pārbaude un pauze). */
const GR_IEV_MAX_PARVIRZES = 5;

/**
 * Zems līmenis: viens HTTP GET bez robots pārbaudes. $sekot = true TIKAI robots.txt vilkšanai (RFC 9309 §2.3.1.2 prasa
 * robots.txt pārvirzēm sekot); satura pieprasījumiem gr_iev_get() pārvirzes iet PATI, lēcienu pa lēcienam.
 * $GLOBALS['gr_iev_jelgs_aizstajejs'] — tikai testiem: aizstāj tīklu ar funkciju (url, galvas, sekot) => atbilde.
 */
function gr_iev_jelgs(string $url, int $timeout = 20, array $papildGalvas = [], bool $sekot = true): array {
    if (gr_iev_tikls_aizstats()) return ($GLOBALS['gr_iev_jelgs_aizstajejs'])($url, $papildGalvas, $sekot);
    $buf = ''; $galvas = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => $sekot, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING => '', CURLOPT_USERAGENT => GR_IEV_UA,
        CURLOPT_HTTPHEADER => $papildGalvas,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        // Ķermeni krājam paši — tā pati kārtula, kas build.php: pie TLS pārrāvuma
        // RETURNTRANSFER atdotu false, kaut dati jau ir pilni (sk. [[ref-grantu-datu-avoti]]).
        // Šeit tas VĒL svarīgāk: griestu pārsniegšanu var apturēt tikai rakstīšanas pusē.
        CURLOPT_WRITEFUNCTION => static function ($c, string $d) use (&$buf): int {
            $buf .= $d;
            if (strlen($buf) > GR_IEV_MAX_BAITI) return 0;   // 0 = curl pārtrauc (kods 23)
            return strlen($d);
        },
        CURLOPT_HEADERFUNCTION => static function ($c, string $h) use (&$galvas): int {
            $p = explode(':', $h, 2);
            if (count($p) === 2) $galvas[strtolower(trim($p[0]))] = trim($p[1]);
            return strlen($h);
        },
    ]);
    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $kods = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $tips = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $galamerkis = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    return ['kods' => $kods, 'tips' => $tips, 'body' => $buf, 'galvas' => $galvas,
            'galamerkis' => $galamerkis,
            'errno' => $errno, 'ok' => $ok === true || ($kods === 200 && $buf !== ''),
            'parak_liels' => $errno === CURLE_WRITE_ERROR];
}

/**
 * Hosta robots.txt noteikumi MUMS. Kešo diskā; atdod arī Content-Signal rindas un Crawl-delay.
 * @return array{kods:int,kartulas:array,pauze:?float,signali:array,atveria_visu:bool}
 */
function gr_iev_robots(string $shema, string $hosts): array {
    static $atmina = [];
    $atsl = $shema . '://' . $hosts;
    if (isset($atmina[$atsl])) return $atmina[$atsl];
    $f = gr_iev_kesh_dir() . '/robots_' . preg_replace('~[^a-z0-9.-]~i', '_', $hosts) . '.json';
    if (is_file($f) && time() - (int)filemtime($f) < GR_IEV_ROBOTS_TTL) {
        $j = json_decode((string)file_get_contents($f), true);
        // FORMU VALIDĒ. Bojāts vai citas versijas kešs ({"kods":200}) agrāk deva tukšu
        // kārtulu sarakstu, t.i. "viss atļauts" — vienīgais vārts, kam jākrīt uz aizvērto pusi.
        if (is_array($j) && isset($j['kartulas']) && is_array($j['kartulas']) && array_key_exists('signali', $j)
            && isset($j['mi']) && is_array($j['mi']))
            return $atmina[$atsl] = $j;
        @unlink($f);
    }
    $r = gr_iev_jelgs($atsl . '/robots.txt', 15);
    // ROBOTS.TXT, KAS NAV ROBOTS.TXT. kultura.riga.lv robots.txt pārvirza uz HTML sākumlapu
    // un atdod 200 ar 142 KB HTML; parsētājs tur atrod nulli kārtulu un secina "viss atļauts".
    // Tas ir tas pats soft-404 slazds, kas [[ref-robots-parbaude-pec-satura]]. Pārbaudām
    // formu: teksta tips VAI saturs, kas izskatās pēc robots.txt. Citādi — kā 5xx, t.i. liegts.
    $tipsOk = stripos($r['tips'], 'text/plain') !== false;
    $formaOk = preg_match('~^\s*(#|user-agent\s*:|sitemap\s*:|allow\s*:|disallow\s*:)~im', (string)$r['body']) === 1;
    $kods = $r['kods'];
    if ($kods === 200 && !$tipsOk && !$formaOk) {
        // KAS TAS IR: faila NAV. Vietne atdod sākumlapu vai soft-404 (kultura.riga.lv robots.txt
        // pārvirza uz HTML, liepaja.lv atdod 24 KB HTML) — tas nozīmē "robots.txt nav", un
        // RFC 9309 §2.3.1.3 tādā gadījumā atļauj visu. Pirmajā redakcijā es to pieņēmu par
        // liegumu, un tas klusi izslēdza vietnes, kurām vienkārši nav robots.txt.
        // 5xx un tīkla klusums paliek liegums — tā ir cita lieta: serveris ir, atbildes nav.
        gr_iev_zurnals("ROBOTS  $atsl/robots.txt atdeva " . ($r['tips'] ?: 'nezināmu tipu') . ' — uzskatām, ka faila nav (atļauts).');
        $kods = 404;
    }
    $res = gr_iev_robots_parse($r['body'], $kods);
    $res['kods'] = $r['kods'];
    // KLUSUMU NEKEŠO DISKĀ. Tīkla kļūme vai 5xx ir "šobrīd nav atbildes", ne īpašnieka lēmums:
    // ar dienas kešu viens taimauts aizvēra hostu uz 24 h. Šajā palaišanā liegums paliek (atmiņā).
    if ($r['kods'] !== 0 && $r['kods'] < 500) @file_put_contents($f, json_encode($res, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $atmina[$atsl] = $res;
}

/** robots.txt teksta parsēšana. Atdalīta no tīkla, lai to var testēt bez interneta. */
function gr_iev_robots_parse(string $teksts, int $kods): array {
    // 4xx = faila nav => viss atļauts. 5xx/tukšums = LIEGTS (RFC 9309 5.1): nokritis
    // serveris nav atļauja. Robeža ir tieši šeit, tāpēc to nevar "pa ceļam" pazaudēt.
    if ($kods >= 400 && $kods < 500) return ['kartulas' => [], 'pauze' => null, 'signali' => [], 'atveria_visu' => true, 'mi' => []];
    if ($kods !== 200)               return ['kartulas' => [['allow' => false, 'cels' => '/']], 'pauze' => null, 'signali' => [], 'atveria_visu' => false, 'mi' => []];

    // BOM. Windows redaktors pieliek EF BB BF, trim() to nenoņem, un pirmā rinda kļūst par
    // "\xEF\xBB\xBFuser-agent" — grupa nerodas, tās Disallow rindas nokrīt, un pilnībā
    // liegta vietne kļūst pilnībā atļauta. Mērīts: "BOM + User-agent: * + Disallow: /" -> atļauts.
    $teksts = preg_replace('~^\xEF\xBB\xBF~', '', $teksts) ?? $teksts;
    $grupas = [];      // marķieris (mazie burti) => ['kartulas'=>[], 'pauze'=>?float]
    $tekosie = [];     // šīs grupas User-agent marķieri
    $gaidaAgentu = true;
    $signali = [];
    foreach (preg_split('~\R~', $teksts) ?: [] as $rinda) {
        $rinda = trim(preg_replace('~#.*$~', '', $rinda) ?? '');
        if ($rinda === '') continue;
        $p = explode(':', $rinda, 2);
        if (count($p) !== 2) continue;
        $lauks = strtolower(trim($p[0]));
        $vert  = trim($p[1]);
        if ($lauks === 'user-agent') {
            // Tukšs marķieris ("User-agent:" bez vērtības) nedrīkst radīt grupu: zemāk
            // str_contains($mus, '') ir true, tāpēc tāda tukša grupa uzvarētu pār "*" un
            // atvērtu visu. Mērīts uz "User-agent: *\nDisallow: /\n\nUser-agent:\n" -> atļauts.
            if ($vert === '') continue;
            // Jauna grupa sākas tikai tad, ja iepriekšējai JAU bija kārtulas: vairākas
            // User-agent rindas pēc kārtas ir VIENA grupa ar vairākiem adresātiem.
            if (!$gaidaAgentu) { $tekosie = []; $gaidaAgentu = true; }
            $tekosie[] = strtolower($vert);
            if (!isset($grupas[strtolower($vert)])) $grupas[strtolower($vert)] = ['kartulas' => [], 'pauze' => null];
            continue;
        }
        if ($tekosie === []) continue;   // kārtula pirms jebkura User-agent — ignorējam
        $gaidaAgentu = false;
        foreach ($tekosie as $a) {
            if ($lauks === 'disallow' || $lauks === 'allow') {
                // "Disallow:" ar tukšu vērtību nozīmē "nekas nav liegts" — to NEDRĪKST
                // pievienot kā kārtulu ar tukšu ceļu, citādi tā sakristu ar visu.
                if ($vert === '') { if ($lauks === 'disallow') continue; else continue; }
                $grupas[$a]['kartulas'][] = ['allow' => $lauks === 'allow', 'cels' => $vert];
            } elseif ($lauks === 'crawl-delay') {
                $grupas[$a]['pauze'] = (float)str_replace(',', '.', $vert);
            } elseif ($lauks === 'content-signal') {
                $signali[$a] = $vert;
            }
        }
    }
    // Grupu izvēle: vispirms mūsu marķieris, tad "*".
    // PRECĪZA marķiera sakritība, ne apakšvirkne. RFC 9309 prasa produkta marķieri; ar
    // str_contains() grupa "User-agent: sa" vai "l" sagrāba mūsu grupu un varēja pārspēt
    // "*" liegumu ar savu Allow. Mērīts.
    $mus = strtolower(GR_IEV_MARKIERIS);
    $izv = null;
    foreach ($grupas as $agents => $g) {
        if ($agents !== '*' && (string)$agents === $mus) { $izv = $g; break; }
    }
    if ($izv === null) $izv = $grupas['*'] ?? ['kartulas' => [], 'pauze' => null];
    $sig = $signali[$mus] ?? ($signali['*'] ?? null);
    // Anthropic marķieru PRECĪZĀS grupas (bez atkāpes uz "*"): izstrādes režīma pārbaudei gr_iev_atlauts().
    // Grupa bez kārtulām nozīmē "viss atļauts" (tukšs saraksts), tieši kā RFC 9309.
    $mi = [];
    foreach (GR_IEV_MI_MARKIERI as $tk) if (isset($grupas[$tk])) $mi[$tk] = $grupas[$tk]['kartulas'];
    return ['kartulas' => $izv['kartulas'], 'pauze' => $izv['pauze'],
            'signali' => $sig !== null ? gr_iev_signalu_parse($sig) : [],
            'atveria_visu' => $izv['kartulas'] === [], 'mi' => $mi];
}

/** "search=yes,ai-train=no,use=reference" -> ['search'=>'yes', ...]. */
function gr_iev_signalu_parse(string $s): array {
    $out = [];
    foreach (explode(',', $s) as $gab) {
        $kv = explode('=', trim($gab), 2);
        if (count($kv) === 2) $out[strtolower(trim($kv[0]))] = strtolower(trim($kv[1]));
    }
    return $out;
}

/** robots ceļa šablons -> regulārā izteiksme (atbalsta * un $). */
function gr_iev_celsh(string $celsh): string {
    $re = '';
    foreach (str_split($celsh) as $c) {
        if ($c === '*') { $re .= '.*'; continue; }
        if ($c === '$') { $re .= '$'; continue; }
        $re .= preg_quote($c, '~');
    }
    return '~^' . $re . '~';
}

/**
 * Vai šo adresi drīkst vilkt? Uzvar garākais sakritums; vienādā garumā — Allow.
 * $kapec atdod cilvēkam lasāmu iemeslu, ko zonde un žurnāls rāda bez minēšanas.
 */
function gr_iev_atlauts(string $url, ?string &$kapec = null): bool {
    $d = parse_url($url);
    if (!isset($d['host'])) { $kapec = 'nederīga adrese'; return false; }
    $r = gr_iev_robots($d['scheme'] ?? 'https', $d['host']);
    // NEATBILDĒJIS ROBOTS.TXT ≠ ĪPAŠNIEKA LIEGUMS. Abi nozīmē "nevelkam" (RFC 9309 §2.3.1.4), bet
    // iemesls ir cits: recenzents atrada liepaja.lv, aif.lv un charityfund.lv sarakstā "liedz paši",
    // kaut to robots.txt vienkārši neatbildēja — un tāpēc tās netika rādītas kā nesasniedzamas.
    $rk = (int)($r['kods'] ?? 200);
    if ($rk === 0 || $rk >= 500) { $kapec = 'robots.txt neatbild (' . ($rk === 0 ? 'nav atbildes' : 'HTTP ' . $rk) . ')'; return false; }
    // Content-Signal ir īpašnieka tieši izteikta griba; search=no nozīmē nē arī tad, ja
    // Disallow rindu nav. Mēs esam meklēšanai līdzīgs apkopotājs, tāpēc skatāmies 'search'.
    if (($r['signali']['search'] ?? '') === 'no') { $kapec = 'Content-Signal: search=no'; return false; }
    $celsh = ($d['path'] ?? '/') . (isset($d['query']) ? '?' . $d['query'] : '');
    [$atl, $kapec] = gr_iev_kartulu_lemums($r['kartulas'], $celsh);
    if (!$atl) return false;
    if (gr_iev_izstrades_rezims())
        foreach (($r['mi'] ?? []) as $tk => $kart) {
            [$miAtl, $miK] = gr_iev_kartulu_lemums((array)$kart, $celsh);
            if (!$miAtl) { $kapec = "izstrādes režīms: robots.txt liedz $tk ($miK)"; return false; }
        }
    return true;
}

/** Ceļa kanoniskā forma (RFC 3986 §6.2.2): nerezervētās rakstzīmes atkodē ("%6Bonkursi" = "konkursi"), pārējos %xx — lielie burti. */
function gr_iev_cels_kanon(string $p): string {
    return preg_replace_callback('~%([0-9A-Fa-f]{2})~', static function (array $m): string {
        $c = chr((int)hexdec($m[1]));
        return preg_match('~^[A-Za-z0-9._\~-]$~', $c) ? $c : '%' . strtoupper($m[1]);
    }, $p) ?? $p;
}

/** Robots salīdzināšanai (RFC 9309 §2.2.2): kanons + ne-ASCII baiti %-kodēti — "pieteikšanās" = "pieteik%C5%A1an%C4%81s". */
function gr_iev_robots_kanon(string $p): string {
    return preg_replace_callback('~[\x80-\xFF]~', static fn(array $m): string => '%' . strtoupper(bin2hex($m[0])), gr_iev_cels_kanon($p)) ?? $p;
}

/** Garākā sakritība, vienādā garumā Allow: [atļauts, cilvēkam lasāms iemesls]. */
function gr_iev_kartulu_lemums(array $kartulas, string $celsh): array {
    $labakais = null; $garums = -1;
    $celsh = gr_iev_robots_kanon($celsh);
    foreach ($kartulas as $k) {
        $kc = gr_iev_robots_kanon((string)$k['cels']);
        if (preg_match(gr_iev_celsh($kc), $celsh) !== 1) continue;
        $l = strlen($kc);
        if ($l > $garums || ($l === $garums && $k['allow'])) { $garums = $l; $labakais = $k; }
    }
    if ($labakais === null) return [true, 'robots.txt neaizliedz'];
    return [(bool)$labakais['allow'], ($labakais['allow'] ? 'Allow: ' : 'Disallow: ') . $labakais['cels']];
}

/** Hosti (ar un bez www), kuru kešotais robots.txt kādam Anthropic marķierim liedz sakni — izstrādes pārnesei. */
function gr_iev_mi_liegti_hosti(): array {
    $out = [];
    foreach (glob(gr_iev_kesh_dir() . '/robots_*.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (!is_array($j) || !is_array($j['mi'] ?? null)) continue;
        foreach ($j['mi'] as $kart) {
            if (gr_iev_kartulu_lemums((array)$kart, '/')[0]) continue;
            $h = substr(basename($f, '.json'), 7);
            $out[$h] = true;
            $out[str_starts_with($h, 'www.') ? substr($h, 4) : 'www.' . $h] = true;
            break;
        }
    }
    return array_keys($out);
}

/** Pauze pirms nākamā pieprasījuma uz to pašu hostu (Crawl-delay vai mūsu noklusējums). */
function gr_iev_pauze(string $hosts, ?float $crawlDelay): void {
    $f = gr_iev_kesh_dir() . '/ts_' . preg_replace('~[^a-z0-9.-]~i', '_', $hosts);
    // CRAWL-DELAY AR GRIESTIEM. "Crawl-delay: 3600" nozīmētu stundu vienam pieprasījumam,
    // un būve vienkārši karātos, turot slēdzeni. Virs griestiem avots vairs nav savācams
    // saprātīgā laikā — tad labāk to nevākt vispār, nekā gulēt visu nakti.
    $vajag = min(GR_IEV_PAUZE_MAX_S, max(GR_IEV_PAUZE_S, (float)($crawlDelay ?? 0)));
    // Testos tīkls ir aizstāts ar tabulu (gr_iev_jelgs_aizstajejs) — nav neviena servera, pret kuru būt pieklājīgam,
    // un gaidīšana tikai pagarinātu testu un mutāciju komplektu (8 s pret 1 s). Ar īstu tīklu pauze ir VIENMĒR.
    if (gr_iev_tikls_aizstats()) { @file_put_contents($f, (string)microtime(true)); return; }
    if (is_file($f)) {
        $pag = microtime(true) - (float)file_get_contents($f);
        if ($pag < $vajag) usleep((int)(($vajag - $pag) * 1e6));
    }
    @file_put_contents($f, (string)microtime(true));
}

/**
 * Legāls GET: robots -> pauze -> nosacītais GET (ETag/Last-Modified) -> kešs.
 *
 * Nosacītais GET nav optimizācija, bet pieklājība: dienas būve citādi katru dienu vilktu
 * to pašu nemainīto lapu no sveša servera. 304 gadījumā atdodam kešoto ķermeni.
 *
 * @return array{ok:bool,kods:int,tips:string,body:string,no_kesa:bool,kapec:string}
 */
function gr_iev_get(string $url, int $timeout = 25): array {
    $kapec = ''; $kapec2 = '';
    // CEĻU NORMALIZĒ PIRMS ROBOTS PĀRBAUDES. "/lv/../konkursi/" neatbilst kārtulai "/konkursi/",
    // bet curl to aizsūta jau kā "/konkursi/" — t.i. liegtais pieprasījums aizgāja (recenzents,
    // libcurl 8.21). Pārbaudām tieši to ceļu, ko saņems serveris.
    $url = gr_iev_normalize($url);
    if (!gr_iev_atlauts($url, $kapec)) {
        gr_iev_zurnals("LIEGTS  $url  ($kapec)");
        return ['ok' => false, 'kods' => 0, 'tips' => '', 'body' => '', 'no_kesa' => false, 'galamerkis' => $url,
                'kapec' => (str_starts_with($kapec, 'robots.txt neatbild') ? 'robots-nepieejams: ' : 'robots: ') . $kapec];
    }
    $d = parse_url($url);
    $r = gr_iev_robots($d['scheme'] ?? 'https', $d['host'] ?? '');
    gr_iev_pauze((string)($d['host'] ?? ''), $r['pauze']);

    $atsl = gr_iev_kesh_dir() . '/' . hash('sha256', $url);
    $meta = is_file($atsl . '.json') ? json_decode((string)file_get_contents($atsl . '.json'), true) : null;
    $galvas = [];
    if (is_array($meta) && is_file($atsl . '.body')) {
        if (!empty($meta['etag']))  $galvas[] = 'If-None-Match: ' . $meta['etag'];
        if (!empty($meta['mtime'])) $galvas[] = 'If-Modified-Since: ' . $meta['mtime'];
    }
    // PĀRVIRZES IET PAŠI, LĒCIENU PA LĒCIENAM (2026-09-17 audits). Agrāk curl sekoja pats (FOLLOWLOCATION), un galamērķi pret
    // robots.txt pārbaudījām tikai PĒC atbildes: ķermeni izmetām, bet pieprasījums uz liegto adresi jau bija aizgājis
    // (LIAA JSON:API -> office.business.gov.lv ar "Disallow: /"). Tagad katrs lēciens vispirms iziet to pašu vārtu, ko
    // sākotnējā adrese: robots.txt (izstrādē arī Anthropic grupas) un pauze TĀ hostam; liegts lēciens tīklā neaiziet.
    $tek = $url; $a = null;
    for ($lec = 0; ; $lec++) {
        if ($lec > 0) {
            if (!gr_iev_atlauts($tek, $kapec2)) {
                gr_iev_zurnals("LIEGTS  pārvirze -> $tek  ($kapec2)");
                // Priedēklis kā pirmajam lēcienam: "robots:" = īpašnieka liegums (PASTĀVĪGS — vietņu kods to neskaita par
                // pārejošu kļūmi un vecās rindas nepārnes bez gala), "robots-nepieejams:" = robots.txt neatbild (pārejošs).
                return ['ok' => false, 'kods' => 0, 'tips' => '', 'body' => '', 'no_kesa' => false, 'galamerkis' => $tek,
                        'kapec' => (str_starts_with($kapec2, 'robots.txt neatbild') ? 'robots-nepieejams: ' : 'robots: ')
                                 . 'pārvirze uz liegtu adresi: ' . $kapec2];
            }
            $dd = parse_url($tek);
            $rr = gr_iev_robots($dd['scheme'] ?? 'https', $dd['host'] ?? '');
            gr_iev_pauze((string)($dd['host'] ?? ''), $rr['pauze']);
        }
        $a = gr_iev_jelgs($tek, $timeout, $galvas, false);
        $loc = (string)($a['galvas']['location'] ?? '');
        $irParvirze = in_array($a['kods'], [301, 302, 303, 307, 308], true) && $loc !== '';
        gr_iev_zurnals(sprintf('%-6s %s  (%s)', $a['kods'] ?: 'ERR', $tek, $irParvirze ? '-> ' . $loc : ($a['tips'] ?: 'errno ' . $a['errno'])));
        if (!$irParvirze) break;
        $nak = gr_iev_abs_url($tek, $loc);
        if ($nak === null) return ['ok' => false, 'kods' => $a['kods'], 'tips' => '', 'body' => '', 'no_kesa' => false,
                                   'galamerkis' => $tek, 'kapec' => 'pārvirze uz nederīgu adresi'];
        if ($lec >= GR_IEV_MAX_PARVIRZES) return ['ok' => false, 'kods' => $a['kods'], 'tips' => '', 'body' => '', 'no_kesa' => false,
                                                  'galamerkis' => $tek, 'kapec' => 'pārāk daudz pārviržu (' . GR_IEV_MAX_PARVIRZES . ')'];
        $tek = gr_iev_normalize($nak);
    }
    $a['galamerkis'] = $tek;
    $GLOBALS['gr_iev_pedejie_lecieni'] = $lec;   // cik PAPILDU pieprasījumu prasīja pārvirzes (vietņu griestu skaitītājam)
    if ($a['kods'] === 304 && is_file($atsl . '.body')) {
        return ['ok' => true, 'kods' => 304, 'tips' => (string)($meta['tips'] ?? ''),
                'body' => gr_iev_utf8((string)file_get_contents($atsl . '.body'), (string)($meta['tips'] ?? '')),
                'no_kesa' => true, 'kapec' => 'nav mainījies', 'galamerkis' => (string)($meta['galamerkis'] ?? $url)];
    }
    if ($a['parak_liels']) return ['ok' => false, 'kods' => $a['kods'], 'tips' => $a['tips'], 'body' => '',
                                   'no_kesa' => false, 'kapec' => 'atbilde > ' . round(GR_IEV_MAX_BAITI / 1048576) . ' MB'];
    if ($a['kods'] === 200 && $a['body'] !== '') {
        @file_put_contents($atsl . '.body', $a['body'], LOCK_EX);
        @file_put_contents($atsl . '.json', json_encode([
            'etag' => $a['galvas']['etag'] ?? '', 'mtime' => $a['galvas']['last-modified'] ?? '',
            'tips' => $a['tips'], 'url' => $url, 'galamerkis' => $a['galamerkis'], 'kad' => date('c')], JSON_UNESCAPED_UNICODE), LOCK_EX);
        return ['ok' => true, 'kods' => 200, 'tips' => $a['tips'], 'body' => gr_iev_utf8($a['body'], $a['tips']),
                'no_kesa' => false, 'kapec' => '', 'galamerkis' => $a['galamerkis'] ?: $url];
    }
    return ['ok' => false, 'kods' => $a['kods'], 'tips' => $a['tips'], 'body' => $a['body'],
            'no_kesa' => false, 'kapec' => $a['kods'] === 0 ? ('tīkla kļūme (curl ' . $a['errno'] . ')') : 'HTTP ' . $a['kods']];
}

/**
 * Ceļa punktu segmentu noņemšana (RFC 3986 §5.2.4), ieskaitot %2e. "/lv/novads/../konkursi/" ->
 * "/lv/konkursi/". Tā pati normalizācija, ko veic curl un serveris, tāpēc robots kārtulas
 * jāsalīdzina ar šo ceļu, ne ar saitē rakstīto.
 */
function gr_iev_bez_punktiem(string $p): string {
    $p = preg_replace('~%2e~i', '.', $p) ?? $p;
    $abs = str_starts_with($p, '/');
    $segs = explode('/', $p); $pedejais = count($segs) - 1; $out = [];
    foreach ($segs as $i => $sg) {
        if ($sg === '.' || $sg === '..') {
            if ($sg === '..' && count($out) > ($abs ? 1 : 0)) array_pop($out);
            if ($i === $pedejais) $out[] = '';
            continue;
        }
        $out[] = $sg;
    }
    $r = implode('/', $out);
    return ($abs && !str_starts_with($r, '/')) ? '/' . $r : $r;
}

/** Absolūta http(s) adrese kanoniskā formā: shēma un hosts mazajiem burtiem, ceļš bez punktiem,
 *  bez fragmenta. Adrese ar lietotāju/paroli autoritātē -> '' (nederīga). */
function gr_iev_normalize(string $url): string {
    $d = parse_url(trim($url));
    if ($d === false || !isset($d['scheme'], $d['host'])) return $url;
    if (isset($d['user']) || isset($d['pass'])) return '';
    $cels = gr_iev_bez_punktiem(gr_iev_cels_kanon($d['path'] ?? '/'));
    $shema = strtolower($d['scheme']);
    $ports = isset($d['port']) && !(($shema === 'https' && $d['port'] === 443) || ($shema === 'http' && $d['port'] === 80)) ? ':' . $d['port'] : '';
    return $shema . '://' . strtolower($d['host']) . $ports
         . ($cels === '' ? '/' : $cels) . (isset($d['query']) ? '?' . $d['query'] : '');
}

/**
 * Saite -> absolūta adrese pēc RFC 3986 (§5.2). Iepriekšējā redakcija līmēja "dirname(bāze)/saite",
 * un recenzents atrada trīs kļūdas: "?page=2" pie ".../pasvaldibas-projektu-konkursi" kļuva par
 * "/lv/?page=2", bāze bez ceļa deva "https:/…", un "../" palika adresē. Tikai http(s).
 */
function gr_iev_abs_url(string $baze, string $href): ?string {
    $href = trim(preg_replace('~#.*$~s', '', $href) ?? '');
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $href)) return preg_match('~^https?://~i', $href) ? (gr_iev_normalize($href) ?: null) : null;
    $b = parse_url($baze);
    if ($b === false || !isset($b['scheme'], $b['host'])) return null;
    $shema = strtolower($b['scheme']);
    if (str_starts_with($href, '//')) return gr_iev_normalize($shema . ':' . $href) ?: null;
    $auth = $shema . '://' . strtolower($b['host']) . (isset($b['port']) ? ':' . $b['port'] : '');
    $bCels = ($b['path'] ?? '') === '' ? '/' : $b['path'];
    if ($href === '') return gr_iev_normalize($auth . $bCels . (isset($b['query']) ? '?' . $b['query'] : '')) ?: null;
    if (str_starts_with($href, '?')) return gr_iev_normalize($auth . $bCels . $href) ?: null;
    if (str_starts_with($href, '/')) return gr_iev_normalize($auth . $href) ?: null;
    return gr_iev_normalize($auth . substr($bCels, 0, (int)strrpos($bCels, '/') + 1) . $href) ?: null;
}

/**
 * Teksta atbilde -> derīgs UTF-8.
 *
 * KĀPĒC NE "VISU NO WINDOWS-1252". Iepriekšējā redakcija (gr_lv_teksts) visu lapu pārkodēja no
 * cp1252, ja kaut viens baits bija nederīgs, un recenzents to redzēja divos veidos: UTF-8 lapa ar
 * vienu Word domuzīmi kļuva par "ierobeÅ¾ota … terminÅ†Å¡" (CFLA atlases veids vairs neatpazīstams),
 * un Baltijas kodējuma LEADER vietnes (talsupartneriba.lv — windows-1257) kļuva par
 * "Izsludinâtâs … pieòemðanas". Tagad: ja lielākā daļa augsto baitu jau ir derīgas UTF-8
 * virknes, nomainām TIKAI bojātos; citādi pārkodējam no deklarētā kodējuma, un bez deklarācijas —
 * no windows-1257 (latviešu burti tur un ISO-8859-13 stāv vienās vietās). Bināriem tipiem neaiztiek.
 */
function gr_iev_utf8(string $body, string $tips = ''): string {
    if ($body === '' || mb_check_encoding($body, 'UTF-8')) return $body;
    if ($tips !== '' && !preg_match('~^\s*(?:text/|application/(?:xhtml|xml|json|rss|atom|ld\+json))~i', $tips)) return $body;
    $cs = '';
    if (preg_match('~charset\s*=\s*["\']?([\w.:-]+)~i', $tips, $m)) $cs = strtolower($m[1]);
    elseif (preg_match('~<meta[^>]+charset\s*=\s*["\']?([\w.:-]+)~i', substr($body, 0, 8192), $m)) $cs = strtolower($m[1]);
    $augsti = preg_match_all('~[\x80-\xFF]~', $body);
    $derigi = 0;
    if (preg_match_all('~[\xC2-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF4][\x80-\xBF]{3}~', $body, $mm))
        foreach ($mm[0] as $x) $derigi += strlen($x);
    // JAUKTS SATURS (≥ 50 % augsto baitu ir derīgas UTF-8 virknes): tās paliek, un KATRS bojātais baits tiek atkodēts no
    // windows-1257 (tur ir gan latviešu burti, gan Word pieturzīmes 0x93/0x94/0x96). Deklarācija nav pierādījums (recenzents 2:
    // 1257 lapa ar galveni "charset=UTF-8" kļuva par "Izsludin?ts"; UTF-8 lapa ar cp1252 baitiem — par "IzsludinÄts").
    if ($augsti > 0 && $derigi >= 0.5 * $augsti) {
        $r = preg_replace_callback('~[\xC2-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF4][\x80-\xBF]{3}|[\x80-\xFF]~',
            static fn(array $x): string => strlen($x[0]) > 1 ? $x[0] : ((string)@iconv('WINDOWS-1257', 'UTF-8//IGNORE', $x[0]) ?: '?'), $body);
        return mb_scrub((string)($r ?? $body), 'UTF-8');
    }
    $no = match (true) {
        in_array($cs, ['windows-1252', 'cp1252', 'iso-8859-1', 'latin1', 'us-ascii'], true) => 'WINDOWS-1252',
        $cs === '' || in_array($cs, ['utf-8', 'utf8', 'windows-1257', 'cp1257', 'iso-8859-13', 'iso-8859-4', 'baltic'], true) => 'WINDOWS-1257',
        default => strtoupper($cs),
    };
    $r = @iconv($no, 'UTF-8//IGNORE', $body);
    if ($r === false || $r === '') $r = @mb_convert_encoding($body, 'UTF-8', $no === 'WINDOWS-1257' ? 'ISO-8859-13' : 'Windows-1252');
    return is_string($r) && $r !== '' ? $r : mb_scrub($body, 'UTF-8');
}
