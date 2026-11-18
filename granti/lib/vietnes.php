<?php
/**
 * granti/lib/vietnes.php — VISPĀRĪGS konkursu meklētājs vietnēm bez struktūras:
 * pašvaldības, 33 LEADER vietējās rīcības grupas, citas valsts iestādes un fondi.
 *
 * KĀPĒC VIENS MEKLĒTĀJS, NE 50 IZVILCĒJU. Šīm vietnēm kopīgā ir tikai forma: sākumlapā ir
 * saite ar vārdu "konkursi" vai "izsludinātie", un konkursa lapā ir virsraksts, intervāls
 * "no … līdz …" un summa. Katrai rakstīt savu parseri nozīmētu 50 trauslas kopijas; viens
 * meklētājs ar stingriem vārtiem sabojājas vienā vietā un to var izmērīt.
 *
 * VĀRTI (visi vajadzīgi, katrs sedz citu kļūdu, visi mērīti uz reālām lapām 2026-09-13):
 *   1) virsrakstā vai tekstā ir IZSLUDINĀŠANAS zīme, virsrakstā NAV rezultātu/amata/skolēnu
 *      sacensību zīmes — pašvaldību vietnēs "konkurss" visbiežāk ir vakance vai zīmējumu
 *      konkurss, ne finansējums;
 *   2) termiņš ar tuvuma vārtu (intervāla beigas), un tas nav pagātnē;
 *   3) grantu šķīrējs (lib/grantu_filtrs.php) — tas pats, kas pārējiem avotiem.
 *
 * ROTĀCIJA. 50 vietņu × vairākas lapas × 2 s pauze nav nakts būve, bet stundas darbs.
 * Tāpēc katrā būvē apmeklē tās vietnes, kuras sen nav apmeklētas (līdz budžetam), un
 * neapmeklēto vietņu rindas būve PĀRNES no iepriekšējās DB pēc hosta (kolonna `division`).
 * Konkursu logi ir nedēļām gari, tāpēc 3–4 dienu atjaunošanas cikls tos nepalaiž garām.
 */
declare(strict_types=1);

require_once __DIR__ . '/avoti_lv.php';

/** Grupu kodi grants.source kolonnai un to nosaukumi. */
const GR_VT_GRUPAS = [
    'PASV'   => 'Pašvaldības',
    'LEADER' => 'LEADER vietējās rīcības grupas',
    'VALSTS' => 'Citas valsts iestādes un fondi',
];

/** Izsludināšanas zīmes (virsrakstā vai sākuma tekstā). "Līdzdalības budžets" pats ir TĒMA (arī
 *  balsojums un rezultāti), tāpēc zīme ir tikai kopā ar konkursu vai pieteikšanos. */
const GR_VT_ZIMES = '~izsludin|projektu konkurs|projektu iesniegumu konkurs|līdzfinansējuma konkurs'
    . '|finansēšanas konkurs|pieņemšanas\s+\d+\.\s*kārt|pieņem\w*\s+pieteikum|aicina\s+pieteikties'
    . '|iniciatīv\w*\s+projektu\s+konkurs|līdzdalības\s+budžet\w*\s+(?:projektu\s+)?(?:konkurs|pieteik|iesnieg)'
    . '|grantu\s+konkurs|mikrogrant|atklāt\w*\s+konkurs~ui';
/**
 * Virsraksta un saites teksta zīmes, kas nozīmē "tas NAV finansējuma uzsaukums". Recenzenta 2026-09-13
 * papildinājumi no reālām būvēm: izsoles (ogresnovads.lv, tukums.lv), iepirkumi (daugavpils.lv),
 * tirgus izpēte (vidzeme.lv), apspriedes (jurmala.lv), bērnudārzu pieteikšanās, "Noslēgušās kārtas"
 * (brasla.lv, ziedzeme.lv), arhīvs, LEADER projektu publicitāte ("konkursa ietvaros top estrāde").
 * "amat" tikai kā vārds "amats" locījumos: "\bamat\w*" izmeta "amatiermākslas" un "amatniecības".
 */
const GR_VT_NE = '~rezultāt|noslēdzies|noslēgusies|noslēgušās|uzvarētāj|apbalvo|vakanc|konkurs\w*\s+uz\s+[^.]{0,60}?\bamat(?:u|a|iem)\b|fotokonkurs|zīmējumu'
    . '|olimpiād|viktorīn|eseju konkurs|\blogo|dziesmu konkurs|izstād|\bseminār(?:s|ā|i|os)\b|\bvebinār(?:s|ā|i|os)\b|aptauj|balsojum'
    // VĀRDU ROBEŽAS (recenzents 2): "izsol" ķēra "Emisijas kvotu izsolīšanas instrumenta … konkurss", "iepirkum" — "inovāciju
    // iepirkuma atbalstam", "ietvaros īsten" — "stratēģijas ietvaros īstenojamo projektu konkursa 6. kārta". "amats" ir arī arods.
    . '|\bizsol(?:e|es|ē|i|ei|u)\b|\biepirkum(?:i|s|u|os)\b|iepirkuma\s+(?:procedūr\w*|nolikum\w*|identifikācijas|numurs)'
    . '|bērnudārz|pirmsskol|tirgus\s+(?:priekš)?izpēt|apspried|arhīv'
    . '|ietvaros\s+(?:top|tapa|tapis|tapusi|tapuši|īstenot[sai]|īstenotas|realizēt[sai]|izveidot[sai]|atjaunot[sai]|uzstādīt[sai]|iegādāt[sai])\b~ui';
/** Tās pašas zīmes ADRESĒ (bez diakritikas): abulas.lv ".../683-devita-karta-rezultati" h1 tekstā
 *  vārda "rezultāti" nenesa, bet adrese to pasaka. */
const GR_VT_NE_ADRESE = '~rezultat|(?:^|[-_/])izsoles?(?:[-_/.]|$)|(?:^|[-_/])iepirkumi(?:[-_/.]|$)|iepirkuma-(?:proced|nolikum)|vakanc|balsojum|nosleg|uzvaretaj|apbalvo~i';
/** Saites teksts vai adrese, kas ved uz konkursu SARAKSTU (viens solis dziļāk). */
const GR_VT_SARAKSTS = '~izsludinātie konkursi|projektu konkursi|konkursi|finansēšanas konkursi|līdzfinansējum|atbalsta programm|kārtas~ui';
/**
 * GRIESTI VIENAI BŪVEI (recenzents M3: 52 vietnes = 246 pieprasījumi ~6 min, bet nekas to neierobežoja;
 * ar Crawl-delay 10 s viena vietne aizņem ~190 s). Pāri griestiem atlikušās vietnes šajā būvē
 * neapmeklē — to rindas pārnes rotācija, un nākamajā būvē tās ir rindas priekšā.
 */
const GR_VT_MAX_PIEPRASIJUMI = 600;
const GR_VT_MAX_SEKUNDES = 2700;

/**
 * Vietņu reģistrs. PASV un VALSTS ir fiksēti (tikai pārbaudīti hosti); LEADER nāk no LAD
 * lapas, kurā ir visas 33 grupas ar mājaslapu saitēm, un to kešo uz nedēļu.
 *
 * 'seklas'    — lapas, ar kurām sākt papildus sākumlapai (zināmas konkursu lapas).
 * 'mi_liegts' — vietnes robots.txt nosauc MI rāpuļus pēc vārda; izstrādes sesijā (--izstrade)
 *               to neapmeklē, serverī apmeklē (mūsu marķierim tā ir atļauta).
 * 'atslegts'  — iemesls, kāpēc vietni neapmeklē vispār.
 */
function gr_vt_registrs(?callable $log = null): array {
    $v = [
        // ── Pašvaldības (hosti pārbaudīti 2026-09-12/13) ───────────────────────
        ['host' => 'www.liepaja.lv', 'grupa' => 'PASV', 'nos' => 'Liepājas valstspilsētas pašvaldība', 'nozare' => 'sociala',
         'seklas' => ['https://www.liepaja.lv/pieteikties-lidzfinansejumam/']],
        ['host' => 'www.ventspils.lv', 'grupa' => 'PASV', 'nos' => 'Ventspils valstspilsētas pašvaldība', 'nozare' => 'sociala'],
        ['host' => 'www.jurmala.lv', 'grupa' => 'PASV', 'nos' => 'Jūrmalas valstspilsētas pašvaldība', 'nozare' => 'sociala'],
        ['host' => 'www.ogresnovads.lv', 'grupa' => 'PASV', 'nos' => 'Ogres novada pašvaldība', 'nozare' => 'sociala'],
        ['host' => 'www.valmierasnovads.lv', 'grupa' => 'PASV', 'nos' => 'Valmieras novada pašvaldība', 'nozare' => 'sociala',
         'seklas' => ['https://www.valmierasnovads.lv/jauniesu-iniciativas-projektu-konkurss/']],
        ['host' => 'www.cesis.lv', 'grupa' => 'PASV', 'nos' => 'Cēsu novada pašvaldība', 'nozare' => 'sociala',
         'seklas' => ['https://www.cesis.lv/lv/novads/cesu-novads/sabiedribas-lidzdaliba/lidzdalibas-budzets/lidzdalibas-budzeta-projektu-konkurss/']],
        ['host' => 'www.jelgava.lv', 'grupa' => 'PASV', 'nos' => 'Jelgavas valstspilsētas pašvaldība', 'nozare' => 'sociala'],
        ['host' => 'www.daugavpils.lv', 'grupa' => 'PASV', 'nos' => 'Daugavpils valstspilsētas pašvaldība', 'nozare' => 'sociala'],
        ['host' => 'www.tukums.lv', 'grupa' => 'PASV', 'nos' => 'Tukuma novada pašvaldība', 'nozare' => 'sociala'],
        // ── Citas valsts iestādes, plānošanas reģioni, fondi ───────────────────
        ['host' => 'www.nkc.gov.lv', 'grupa' => 'VALSTS', 'nos' => 'Nacionālais Kino centrs', 'nozare' => 'kultura'],
        ['host' => 'www.km.gov.lv', 'grupa' => 'VALSTS', 'nos' => 'Kultūras ministrija', 'nozare' => 'kultura'],
        ['host' => 'www.lnkc.gov.lv', 'grupa' => 'VALSTS', 'nos' => 'Latvijas Nacionālais kultūras centrs', 'nozare' => 'kultura'],
        ['host' => 'www.altum.lv', 'grupa' => 'VALSTS', 'nos' => 'ALTUM', 'nozare' => 'inovacija'],
        ['host' => 'kurzemesregions.lv', 'grupa' => 'VALSTS', 'nos' => 'Kurzemes plānošanas reģions', 'nozare' => 'sociala'],
        ['host' => 'www.zemgale.lv', 'grupa' => 'VALSTS', 'nos' => 'Zemgales plānošanas reģions', 'nozare' => 'sociala'],
        ['host' => 'www.vidzeme.lv', 'grupa' => 'VALSTS', 'nos' => 'Vidzemes plānošanas reģions', 'nozare' => 'sociala'],
        ['host' => 'www.teterevufonds.lv', 'grupa' => 'VALSTS', 'nos' => 'Borisa un Ināras Teterevu fonds', 'nozare' => 'kultura'],
        ['host' => 'aif.lv', 'grupa' => 'VALSTS', 'nos' => 'Aktīvo iedzīvotāju fonds', 'nozare' => 'sociala'],
        ['host' => 'www.charityfund.lv', 'grupa' => 'VALSTS', 'nos' => 'Rietumu Bankas labdarības fonds', 'nozare' => 'sociala'],
        ['host' => 'www.ekii.lv', 'grupa' => 'VALSTS', 'nos' => 'Emisijas kvotu izsolīšanas instruments', 'nozare' => 'klimats',
         'mi_liegts' => true],
        ['host' => 'lvafa.gov.lv', 'grupa' => 'VALSTS', 'nos' => 'Latvijas Vides aizsardzības fonds', 'nozare' => 'vide',
         'atslegts' => 'vietnes TLS sertifikāts beidzies (curl errno 60) — korekts klients tur netiek'],
    ];
    return array_merge($v, gr_vt_leader_grupas($log));
}

/** LEADER grupas no LAD lapas (kešots 7 dienas: saraksts mainās reizi gadā). */
function gr_vt_leader_grupas(?callable $log = null): array {
    $f = granti_data_dir() . '/leader_grupas.json';
    $kesa = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    $kesaDer = is_array($kesa) && count($kesa) >= 10;
    if ($kesaDer && time() - (int)filemtime($f) < 7 * 86400) return $kesa;
    // VECS KEŠS IR LABĀKS PAR NEKO (recenzents M2): ja LAD neatbild, agrāk atgriezās tukšs saraksts,
    // un visas 33 grupas pazuda no rotācijas, kaut iepriekšējais saraksts gulēja diskā.
    $vecais = fn(string $kapec): array => $kesaDer
        ? (gr_lv_log($log, "  ! LEADER saraksts: $kapec — lietojam sarakstu no " . date('Y-m-d', (int)filemtime($f)) . '.') ?? $kesa)
        : (gr_lv_log($log, "  ! LEADER saraksts: $kapec") ?? []);
    $g = gr_iev_get('https://www.lad.gov.lv/lv/pieteiksanas-termini-uz-pasakumu-elfla-2023-2027', 30);
    if (!$g['ok']) return $vecais((string)$g['kapec']);
    preg_match_all('~<button[^>]*>\s*((?:Biedrība|Nodibinājums)[^<]{3,140})</button>.*?<div class="card-body">(.*?)</div>~is',
                   $g['body'], $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) {
        if (!preg_match('~href="(https?://[^"]+)"~', $x[2], $lm)) continue;
        $host = strtolower((string)parse_url($lm[1], PHP_URL_HOST));
        if ($host === '' || str_ends_with($host, 'lad.gov.lv')) continue;
        $out[$host] = ['host' => $host, 'grupa' => 'LEADER', 'nozare' => 'lauksaimnieciba',
                       'nos' => trim(html_entity_decode($x[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))];
    }
    $out = array_values($out);
    // Kešā ieliekam TIKAI saprātīgu sarakstu: ja LAD lapa mainījusies un parsējas 2 grupas,
    // tas nav jauns saraksts, bet salauzts parsētājs — tad paliek vecais kešs, ja tāds ir.
    if (count($out) < 10) return $vecais('parsētas tikai ' . count($out) . ' grupas');
    @file_put_contents($f, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    gr_lv_log($log, 'LEADER saraksts: ' . count($out) . ' grupas ar mājaslapu.');
    return $out;
}

/** Vai divi hosti ir viena vietne. Reģistrā ir "www.daugavpils.partneribas.lv", bet vietne visas 45
 *  saites liek uz "daugavpils.partneribas.lv" — bez sapārošanas 0 kandidātu un "apmeklēta". */
function gr_vt_tas_pats_hosts(string $a, string $b): bool {
    return preg_replace('~^www\.~', '', strtolower($a)) === preg_replace('~^www\.~', '', strtolower($b));
}

/** Tā paša hosta saites ar tekstu (atrisinātas pēc RFC 3986, https, bez failiem un lejupielādēm). */
function gr_vt_saites(string $html, string $host, string $bazesUrl): array {
    if (preg_match('~<base\b[^>]*\bhref\s*=\s*["\']([^"\']+)~i', $html, $bm)
        && ($bb = gr_iev_abs_url($bazesUrl, html_entity_decode($bm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) !== null) $bazesUrl = $bb;
    preg_match_all('~<a\b([^>]*)>(.*?)</a>~is', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) {
        if (!preg_match('~href\s*=\s*["\']([^"\'#]+)~i', $x[1], $h)) continue;
        $u = html_entity_decode(trim($h[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // NEDROŠAS ADRESES PROM. NUL baits curl_init() met ValueError, kas nogāza visu būvi;
        // "https://evil.example\\@www.liepaja.lv/" parse_url() lasa kā liepaja.lv hostu, bet
        // pārlūks — kā evil.example. Lietotājs/parole autoritātē -> gr_iev_abs_url atdod null.
        if (preg_match('~[\x00-\x1F\x7F\\\\]~', $u) || ($u = gr_iev_abs_url($bazesUrl, $u)) === null) continue;
        $d = parse_url($u);
        if (!isset($d['host']) || !gr_vt_tas_pats_hosts($d['host'], $host)) continue;
        // http un https ir viena lapa (daugavkrasts.lv tika vilkts divreiz).
        if (strtolower((string)($d['scheme'] ?? '')) === 'http') $u = 'https' . substr($u, 4);
        // Faili un lejupielādes nav konkursa lapas (ogresnovads.lv /lv/media/172277/download?attachment — DOCX).
        if (preg_match('~\.(?:pdf|docx?|xlsx?|odt|ods|rtf|pptx?|jpe?g|png|gif|zip|rar|7z|edoc|asice)(?:\?|$)~i', $u)
            || preg_match('~/(?:download|media/\d+)(?:/|\?|$)|[?&](?:attachment|download)\b~i', $u)) continue;
        $txt = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($x[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (!isset($out[$u]) || mb_strlen($txt) > mb_strlen($out[$u])) $out[$u] = $txt;
    }
    return $out;
}

/** GET ar atmiņu šīs būves ietvaros (sēklas un saraksti tika vilkti divreiz) un kopējiem griestiem. */
function gr_vt_get(string $url, int $timeout = 20): array {
    static $atmina = [];
    $url = gr_iev_normalize($url);
    if (isset($atmina[$url])) return $atmina[$url];
    if (($GLOBALS['gr_vt_pieprasijumi'] ?? 0) >= GR_VT_MAX_PIEPRASIJUMI)
        return ['ok' => false, 'kods' => 0, 'tips' => '', 'body' => '', 'galamerkis' => $url,
                'kapec' => 'griesti: ' . GR_VT_MAX_PIEPRASIJUMI . ' pieprasījumi būvē'];
    $GLOBALS['gr_vt_pieprasijumi'] = ($GLOBALS['gr_vt_pieprasijumi'] ?? 0) + 1;
    if (count($atmina) >= 60) array_shift($atmina);   // Cēsu lapas ir 500 KB katra
    $GLOBALS['gr_iev_pedejie_lecieni'] = 0;
    $atb = gr_iev_get($url, $timeout);
    // Katrs pārvirzes lēciens ir īsts pieprasījums svešam serverim, tāpēc arī tas iet būves griestos (recenzents 2026-09-17:
    // 62 no 624 kešotajām adresēm ir pārvirzes, un ar 5 lēcieniem griesti 600 sliktākajā gadījumā nozīmētu 3600 pieprasījumus).
    $GLOBALS['gr_vt_pieprasijumi'] += (int)($GLOBALS['gr_iev_pedejie_lecieni'] ?? 0);
    return $atmina[$url] = $atb;
}

/** Lapošanas saite ("2", "Nākamais", "»") nav konkursa kandidāts (jurkante.lv). */
function gr_vt_ir_laposana(string $txt, string $u): bool {
    return preg_match('~^\s*(?:\d{1,3}|[«»‹›<>]+|nākam\w*|iepriekšēj\w*|pēdēj\w*|pirm\w*|next|prev\w*|\.\.\.|…)\s*$~ui', $txt) === 1
        || (preg_match('~[?&](?:page|lapa|start|offset)=\d+~i', $u) === 1 && mb_strlen($txt) < 12);
}

/**
 * Kandidāta prioritāte. Recenzents V5: griesti ņēma pirmās N saites DOKUMENTA secībā, un
 * preilupartneriba.lv 6 vietas aizņēma 2024./2025. gada lapas, mnf.lv — 5 no 6 vecās kārtas.
 * Tagad: sēkla > šī vai nākamā gada skaitlis > bez gada > pagājušais gads > vecāks; izsludināšanas
 * darbības vārds +5.
 */
function gr_vt_punkti(string $u, string $txt, int $gads): int {
    $p = 0;
    // WORDPRESS DATUMA CEĻŠ "/2026/03/23/" ir precīzākais gads. PERIODS "2023-2027" (arī "2023.-2027.") gads NAV: pārbaudes
    // būvē 2026-09-13 jurkante.lv 2024. gada kārtas ar "…2023-2027-perioda…" adresē stāvēja priekšā 2026. gada kārtai.
    if (preg_match('~/(20\d{2})/\d{2}/\d{2}(?:/|$)~', $u, $dm)) $gadi = [(int)$dm[1]];
    else {
        $s = preg_replace('~(?<!\d)20\d{2}\s*[-–—.]+\s*20\d{2}(?!\d)~u', ' ', $txt . ' ' . rawurldecode($u)) ?? '';
        $gadi = preg_match_all('~(?<!\d)(20\d{2})(?!\d)~', $s, $gm) ? array_map('intval', $gm[1]) : [];
    }
    if ($gadi) {
        $max = max($gadi);
        $p += $max >= $gads ? 20 : ($max === $gads - 1 ? -5 : -20);
    }
    if (preg_match('~izsludin|pieņem\w*\s+pieteik|aicina~ui', $txt)) $p += 5;
    return $p;
}

/**
 * Kandidātu lapas vienai vietnei: sākumlapa + sēklas → saites ar izsludināšanas zīmēm,
 * plus viens solis dziļāk pa saitēm, kas ved uz konkursu SARAKSTU ("Izsludinātie konkursi"),
 * plus līdz 3 kandidātu-sarakstu izvēršana. Griesti — pēc prioritātes (gr_vt_punkti), BEIGĀS.
 */
function gr_vt_kandidati(array $vt, int $maks, ?callable $log = null, ?string &$kluda = null, ?bool &$dalejs = null): ?array {
    $host = $vt['host'];
    $gads = (int)date('Y');
    $sekla = array_fill_keys(array_map('gr_iev_normalize', $vt['seklas'] ?? []), true);
    $kand = []; $sarakstiem = []; $kadsIzdevas = false; $kluda = null; $dalejs = false;
    // PĀREJOŠA KĻŪME SĒKLĀ VAI SARAKSTA LAPĀ = DAĻĒJS APMEKLĒJUMS (2026-09-17 audits). Agrāk "daļēji" atpazina tikai
    // kandidātlapām: ja sākumlapa atbildēja, bet sēkla vai saraksta lapa deva taimautu/5xx/429 (vai iestājās pieprasījumu
    // griesti), kandidātu bija 0, vietne skaitījās apmeklēta PILNĪBĀ, un tās dzīvās rindas tika izdzēstas līdz nākamajai
    // rotācijai. Tā pati robeža kā gr_vt_lapa(): 404, 403 un robots liegums nav pārejoši.
    $parejosa = static fn(array $g): bool => ((int)$g['kods'] === 0 && !str_starts_with((string)($g['kapec'] ?? ''), 'robots:'))
        || (int)$g['kods'] >= 500 || (int)$g['kods'] === 429;
    $der = static fn(string $u, string $txt): bool => preg_match(GR_VT_ZIMES, $txt . ' ' . $u) === 1
        && !preg_match(GR_VT_NE, $txt) && !preg_match(GR_VT_NE_ADRESE, (string)parse_url($u, PHP_URL_PATH))
        && !gr_vt_ir_laposana($txt, $u);
    $pievieno = static function (string $lapa, int $atlaide) use (&$kand, $host, $gads, $der): ?array {
        $g = gr_vt_get($lapa);
        if (!$g['ok']) return $g;
        foreach (gr_vt_saites($g['body'], $host, (string)($g['galamerkis'] ?? $lapa)) as $u => $txt)
            if (!isset($kand[$u]) && $der($u, $txt)) $kand[$u] = ['t' => $txt, 'p' => gr_vt_punkti($u, $txt, $gads) - $atlaide];
        return $g;
    };
    foreach (array_merge([gr_iev_normalize("https://$host/")], array_keys($sekla)) as $su) {
        $g = $pievieno($su, 0);
        if (!$g['ok']) { $kluda ??= $g['kapec'] ?: ('HTTP ' . $g['kods']); if ($parejosa($g)) $dalejs = true; continue; }
        $kadsIzdevas = true;
        if (isset($sekla[$su])) $kand[$su] = ['t' => 'sēkla', 'p' => 100];
        foreach (gr_vt_saites($g['body'], $host, (string)($g['galamerkis'] ?? $su)) as $u => $txt)
            if (!isset($kand[$u]) && preg_match(GR_VT_SARAKSTS, $txt) && mb_strlen($txt) < 40
                && !preg_match(GR_VT_NE, $txt) && !preg_match(GR_VT_NE_ADRESE, (string)parse_url($u, PHP_URL_PATH))
                && !gr_vt_ir_laposana($txt, $u)) $sarakstiem[$u] = $txt;
    }
    if (!$kadsIzdevas) return null;   // vietne nav sasniedzama — būve rindas pārnes
    foreach (array_slice($sarakstiem, 0, 3, true) as $su => $_) { $g = $pievieno($su, 0); if ($g !== null && !$g['ok'] && $parejosa($g)) $dalejs = true; }
    // SARAKSTA LAPAS IZVĒRŠANA. Pašvaldībās kandidāts bieži ir SARAKSTS ("Pašvaldības projektu
    // konkursi"), un konkursa lapa ar termiņu ir vienu līmeni dziļāk (Valmiera).
    $izv = array_filter($kand, fn($k) => preg_match('~konkursi\b|projektu konkursi|finansēšanas konkursi|līdzfinansējum~ui', $k['t']) === 1);
    uasort($izv, fn($x, $y) => $y['p'] <=> $x['p']);
    foreach (array_slice($izv, 0, 3, true) as $su => $_) { $g = $pievieno($su, 1); if ($g !== null && !$g['ok'] && $parejosa($g)) $dalejs = true; }
    uasort($kand, fn($x, $y) => $y['p'] <=> $x['p']);   // stabila kārtošana: vienādiem — dokumenta secība
    return array_map(fn($k) => $k['t'], array_slice($kand, 0, $maks, true));
}

/**
 * Pirmais teikums ar izsludināšanas zīmi, saīsināts līdz 140 rakstzīmēm pa vārda robežu.
 * Teikuma robeža ir punkts + atstarpe + lielais burts; "10. kārta" un "2026. gada" to nesadala,
 * jo aiz tiem seko mazais burts.
 */
function gr_vt_pirmais_teikums_ar_zimi(string $t, array $adresesVardi = []): string {
    // DIAKRITIKU NOŅEMŠANA salīdzināšanai ar adresi: adresē ir "izsludinata", tekstā "Izsludināta".
    $loc = static fn(string $x): string => strtr(mb_strtolower($x), ['ā'=>'a','č'=>'c','ē'=>'e','ģ'=>'g','ī'=>'i','ķ'=>'k','ļ'=>'l','ņ'=>'n','š'=>'s','ū'=>'u','ž'=>'z']);
    $vardi = array_values(array_filter(array_map($loc, $adresesVardi), fn($v) => mb_strlen($v) >= 4));
    $labakais = ''; $labakaisPunkti = -1;
    foreach (preg_split('~(?<=[.!?])\s+(?=\p{Lu})~u', $t) ?: [] as $teik) {
        $teik = trim($teik);
        if (mb_strlen($teik) < 20 || preg_match(GR_VT_NE, $teik)) continue;
        if (!preg_match_all(GR_VT_ZIMES, $teik, $zm, PREG_OFFSET_CAPTURE)) continue;
        // KURĀ ZĪMĒ GRIEZT. Viens "teikums" bieži satur izvēlni UN paziņojumu bez punkta starpā
        // (Brasla: "Projektu konkurss "BRASLA 2015" Mācāmies dabā … Izsludināta LEADER projektu
        // konkursa VI kārta"). Pirmā zīme tur ir izvēlnē. Ja adrese nes vārdus, izvēlamies zīmi,
        // aiz kuras 120 baitos ir visvairāk adreses vārdu; bez adreses — pirmo zīmi.
        $izvOff = $zm[0][0][1]; $izvP = 0;
        $offs = array_column($zm[0], 1);
        foreach ($offs as $ix => $off) {
            // LOGS LĪDZ NĀKAMAJAI ZĪMEI, ne fiksēti 160 baiti: fiksēts logs no izvēlnes zīmes
            // aizsniedza arī vēlāko paziņojumu, un tad izvēlne "savāca" visus adreses vārdus.
            $gar = isset($offs[$ix + 1]) ? min(160, $offs[$ix + 1] - $off) : 160;
            $logs = $loc(substr($teik, $off, max(20, $gar)));
            $pk = 0; foreach ($vardi as $v) if (str_contains($logs, $v)) $pk++;
            if ($pk >= $izvP && $pk > 0) { $izvP = $pk; $izvOff = $off; }
        }
        $gab = $teik;
        if ($izvOff > 60 || $izvP > 0) {
            $pirms = substr($teik, 0, $izvOff);
            // "LEADER" un "Projektu" šeit NAV: tie ir izvēlnes vārdi (sk. Braslu).
            // Frāzes sākums tiek meklēts arī TUVU teikuma sākumam: "Brasla Izsludināta LEADER projektu konkursa VI kārta" —
            // adreses vārdi izvēlas zīmi "projektu konkursa" (garāks logs), un bez tā virsraksts zaudēja "Izsludināta LEADER".
            if (preg_match_all('~\b(Paziņojums|Informācija|Izsludin\w*|Aicin\w*|Tiek|Atklāt\w*)\b~u', $pirms, $pm, PREG_OFFSET_CAPTURE)
                && $izvOff - (int)end($pm[0])[1] < 80)
                $gab = substr($teik, (int)end($pm[0])[1]);
            else
                $gab = substr($teik, $izvOff);
            $gab = trim($gab);
            $gab = mb_strtoupper(mb_substr($gab, 0, 1)) . mb_substr($gab, 1);
        }
        if (mb_strlen($gab) > 140) $gab = rtrim(mb_substr($gab, 0, mb_strrpos(mb_substr($gab, 0, 140), ' ') ?: 140), ' ,:;') . '…';
        // Bez adreses vārdiem uzvar pirmais teikums (kā līdz šim); ar tiem — labākā sakritība.
        if (!$vardi) return $gab;
        if ($izvP > $labakaisPunkti) { $labakaisPunkti = $izvP; $labakais = $gab; }
    }
    return $labakais;
}

/**
 * Ieraksta identifikators: grupa + hosts + jaucējkods no ceļa UN vaicājuma.
 * Tikai ceļš nepietiek: WordPress "?p=101" un "?p=202", Joomla "index.php?id=11" un "id=12"
 * deva vienu id, un divi dažādi konkursi saplūda vienā rindā. Fragments (#…) netiek ņemts —
 * tā ir tā pati lapa.
 */
function gr_vt_id(string $grupa, string $host, string $url): string {
    $cels = trim((string)parse_url($url, PHP_URL_PATH), '/') . '?' . (string)parse_url($url, PHP_URL_QUERY);
    return $grupa . ':' . substr(preg_replace('~^www\.~', '', $host), 0, 30) . ':' . substr(md5($cels), 0, 10);
}

/**
 * Virsraksta tīrīšana: "Folder:" priekšvārds, datuma zīmogs sākumā ("10/sep/2026 …" — brasla.lv),
 * vietnes nosaukuma aste ("… - cesis.lv", "… | Ogres novads").
 * ASTI GRIEŽ TIKAI pie atdalītāja ar atstarpēm abās pusēs un tikai tad, ja astē nav cipara un
 * izsludināšanas zīmes. Iepriekšējā redakcija grieza pie jebkuras defises (recenzents V8):
 * "E-prasmju projektu konkurss" -> «E», "EJZAF projektu konkursi (2023–2027)" -> «… (2023».
 */
function gr_vt_tirit_virsrakstu(string $nos): string {
    $nos = trim(preg_replace('~^(?:Folder|Mape|Kategorija)\s*:\s*~ui', '', $nos) ?? $nos);
    // Publikācijas zīmogs virsraksta SĀKUMĀ nav nosaukums: dobelespartneriba.lv "Publicēts 11 septembris 2026 Projektu iesniegumu
    // pieņemšanas 4. kārta …" (2026-09-17 audits).
    $nos = trim(preg_replace('~^(?:Publicēts|Pievienots|Atjaunināts|Atjaunots)\s*:?\s*(?:\d{1,2}\.?\s+(?:janvār|februār|mart|aprīl|maij|jūnij|jūlij|august|septembr|oktobr|novembr|decembr)\p{L}*,?\s+20\d{2}|\d{1,2}\.\d{1,2}\.20\d{2}|20\d{2}-\d{2}-\d{2})\.?\s+~ui', '', $nos) ?? $nos);
    $nos = trim(preg_replace('~^.{0,40}?\b\d{1,2}/\p{L}{3,9}/\d{4}\s+|^(?:\d{1,2}\.\d{1,2}\.\d{4}|\d{4}-\d{2}-\d{2})\.?\s+~u', '', $nos) ?? $nos);
    // Zīmogs virsraksta VIDŪ: viss aiz tā ir nākamais bloks (brasla.lv "… atbalstam 10/sep/2026 Vidzemes lauku …").
    $nos = trim(preg_replace('~\s+\d{1,2}/\p{L}{3,9}/\d{4}\b.*$~us', '', $nos) ?? $nos);
    // Īss izvēlnes priekšvārds pirms paziņojuma ar lielo burtu (zgauja.lv "Vairāk par biedrību Izsludināta …").
    if (preg_match('~^(.{3,40}?)\s+((?:Izsludin|Paziņojums\s+par|Aicin)\p{L}*\s.+)$~u', $nos, $pm) && !preg_match(GR_VT_ZIMES, $pm[1])) $nos = $pm[2];
    // Aste tiek griezta TIKAI, ja tā ir vietnes vārds: domēns ("- cesis.lv") vai ≤ 5 vārdi bez cipariem, kas visi sākas ar
    // lielo burtu vai beidzas ar organizācijas vārdu ("| Ogres novada pašvaldība"). Apakšvirsraksts paliek.
    if (preg_match('~^(.{10,}?)\s+[|–—-]\s+([^|–—]{2,60})$~u', $nos, $m) && !preg_match(GR_VT_ZIMES, $m[2])) {
        $aste = trim($m[2]); $vardi = count(preg_split('~\s+~u', $aste) ?: []);
        if (preg_match('~\b[\p{L}\d-]+\.(?:lv|eu|com|org|net)\b~ui', $aste)
            || ($vardi <= 5 && !preg_match('~\d~', $aste) && (preg_match('~^(?:\p{Lu}[\p{L}"“”.-]*\s*)+$~u', $aste)
                || preg_match('~(?:novad|pašvaldīb|partnerīb|biedrīb|fond|aģentūr|dienest|ministrij|centr)\w*$~ui', $aste))))
            $nos = trim($m[1]);
    }
    return $nos;
}

/** «Izsludinātie konkursi», «Projektu konkursi 2026», «Aktuālās kārtas» — SARAKSTA virsraksts, ne konkurss.
 *  Recenzents K2: saraksta lapa kļuva par ierakstu ar pirmā vienuma termiņu blakus īstajam konkursam. */
function gr_vt_ir_saraksta_virsraksts(string $nos): bool {
    return preg_match('~^(?:[\p{L}\p{N}"“”„.-]+\s+){0,4}(?:konkursi|konkursu\s+sludinājumi|kārtas|programmas|uzsaukumi|iespējas|sludinājumi|aktualitātes|jaunumi)'
                    . '(?:\s*\(?\s*20\d{2}\s*(?:[-–—]\s*20\d{2})?\s*\)?)?\s*$~ui', trim($nos)) === 1;
}

/** Lapas publicēšanas datums — sk. gr_lv_publicesanas_datums() (kopīgs ar JSPA). */
function gr_vt_publicesanas_datums(string $html, string $url, string $sodien): ?string {
    return gr_lv_publicesanas_datums($html, $url, $sodien);
}

/**
 * Viena konkursa dublikāti vienā vietnē: sākumlapa un ziņa (ziedzeme.lv), sadaļa un ziņa (Valmieras
 * līdzdalības budžets). Vienāds ir normalizēts virsraksts VAI termiņš kopā ar atvēršanas datumu.
 * Paliek ieraksts ar garāko adreses ceļu — tā ir konkrētā lapa, ne sākumlapa vai sadaļa.
 */
function gr_vt_dedup(array $rindas): array {
    // Recenzents 2: agrāk pietika ar vienādu virsrakstu VAI vienādiem datumiem, un saplūda ELFLA ar EJZAF kārtu un kultūras ar
    // sporta konkursu. Tagad dublikāts ir: (1) tā pati adrese; (2) tas pats virsraksts UN termiņš; (3) viena no lapām ir vietnes
    // sākumlapa UN sakrīt termiņš ar atvēršanu. Paliek garākais ceļš — konkrētā lapa.
    $ceļs = static fn(array $r): string => trim((string)parse_url((string)$r['url'], PHP_URL_PATH), '/');
    usort($rindas, fn($a, $b) => strlen($ceļs($b)) <=> strlen($ceļs($a)));
    $nu = static fn(string $u): string => rtrim(preg_replace('~^https?://(?:www\.)?~i', '', strtolower($u)) ?? $u, '/');
    $nt = static fn(string $t): string => mb_strtolower(preg_replace('~[^\p{L}\p{N}]+~u', '', $t) ?? '');
    $sakne = static fn(array $r): bool => preg_match('~^(?:lv|index\.php|sakums?)?$~i', $ceļs($r)) === 1;
    // Ar summu vispirms: no divām lapām par vienu konkursu paliek tā, kas nes vairāk faktu.
    usort($rindas, fn($a, $b) => (($b['budget'] ?? null) !== null) <=> (($a['budget'] ?? null) !== null));
    $out = [];
    foreach ($rindas as $r) {
        $h = preg_replace('~^www\.~', '', (string)($r['division'] ?? ''));
        foreach ($out as $o) {
            if (preg_replace('~^www\.~', '', (string)($o['division'] ?? '')) !== $h) continue;
            if ($nu((string)$o['url']) === $nu((string)$r['url'])
                || ($nt((string)$o['title']) === $nt((string)$r['title']) && $o['deadline'] === $r['deadline'])
                || (($sakne($o) || $sakne($r)) && $o['deadline'] === $r['deadline'] && ($o['opening_date'] ?? null) === ($r['opening_date'] ?? null))
                // (4) LĪDZDALĪBAS BUDŽETS: pašvaldībai periodā ir viens. Valmierā 2026-09-15 divas ziņas par to pašu konkursu
                // (vienādi datumi, dažādi virsraksti) kļuva par divām rindām; šaurs likums, lai ELFLA/EJZAF kārtas nesaplūstu.
                || (($o['model'] ?? '') === 'ideju_konkurss' && ($r['model'] ?? '') === 'ideju_konkurss' && $o['deadline'] === $r['deadline'])) continue 2;
        }
        $out[$r['id']] = $r;
    }
    return $out;
}

/** Noslēgtas kārtas numurs satura sākumā ("Noslēgusies 2. projektu atlases kārta", "5. kārta ir noslēgusies"). Lapu izmet TIKAI,
 *  ja noslēgta ir virsrakstā minētā kārta — "5. kārta ir noslēgusies. Tagad izsludināta 6. kārta" paliek (recenzents 2). */
const GR_VT_SATURS_SLEGTS = '~noslēgusies\s+(\d{1,2}|[IVX]{1,5})\.\s*(?:\p{L}+\s+){0,2}kārta|(\d{1,2}|[IVX]{1,5})\.\s*(?:\p{L}+\s+){0,2}kārta\s+(?:ir\s+)?noslēgusies~ui';

/** Kārtu numuri tekstā ("6. kārta", "VI kārta") kā veseli skaitļi. */
function gr_vt_kartu_numuri(string $s): array {
    return preg_match_all('~\b(\d{1,2}|[IVX]{1,5})\.?\s*kārt~u', $s, $m) ? array_values(array_unique(array_map('gr_lv_skaitlis', $m[1]))) : [];
}

/** Līdzdalības budžets virsrakstā vai satura sākumā → modelis 'ideju_konkurss' (ideju īsteno pašvaldība; sk. lib/modelis.php). */
function gr_vt_ir_lidzdalibas_budzets(string $nos, string $sat): bool {
    return preg_match('~līdzdalības\s+budžet~ui', $nos . ' ' . mb_substr($sat, 0, 600)) === 1;
}

/** Vārdi, bez kuriem virsraksts nav konkursa virsraksts ("2023 - 2027", "Vairāk par biedrību"). */
const GR_VT_VIRSRAKSTA_VARDI = '~konkurs|kārt|pieņemšan|pieteikšan|uzsaukum|atlas|programm|budžet|finansēj|atbalst|grant|stipendij~ui';

/**
 * Viena lapa → ierakstu SARAKSTS (tīkls): parasti viens ieraksts, pārskata lapai vairāki (gr_vt_virslapas_ieraksti),
 * tukšs — ja lapa neiziet vārtus vai neatbild. Vārti ir gr_vt_lapas_ieraksts().
 *
 * DAĻĒJS TIKAI PĀREJOŠAI KĻŪMEI (recenzents V7). 404/410/403 un robots liegums ir pastāvīgi: salauzta
 * saite brasla.lv sākumlapā (".../blog/postizsludinata-…" -> 404) padarīja vietni "daļēji" KATRĀ būvē,
 * un tās vecās rindas tika pārnestas bez gala. Pārejošas: tīkla klusums, 5xx, 429, būves griesti.
 */
function gr_vt_lapa(string $url, array $vt, bool $arMi, ?callable $log = null, string $saitesTeksts = '',
                    ?bool &$neizdevas = null, ?string $sodien = null): array {
    $neizdevas = false;
    $p = gr_vt_get($url, 20);
    if (!$p['ok']) {
        $k = (int)$p['kods'];
        $neizdevas = ($k === 0 && !str_starts_with((string)$p['kapec'], 'robots:')) || $k >= 500 || $k === 429;
        return [];
    }
    $sodien ??= date('Y-m-d');
    $html = (string)$p['body'];
    $r = gr_vt_lapas_ieraksts($url, $html, $vt, $arMi, $log, $saitesTeksts, $sodien);
    // WORDPRESS PUBLICĒŠANAS DATUMS kā gada enkurs, TIKAI ja tā pietrūkst: daugavkrasts.lv "No šā gada 7. septembra līdz
    // 6. oktobrim" lapā nav neviena gada, un K-A politika (bez enkura datumu bez gada nepieņem) dzīvu kārtu izmeta.
    if ($r === null && gr_vt_vajag_enkuru($html, $url, $sodien) && ($wp = gr_vt_wp_datums($url, $html, $sodien)) !== null)
        $r = gr_vt_lapas_ieraksts($url, $html, $vt, $arMi, $log, $saitesTeksts, $sodien, $wp);
    if ($r !== null) return [$r];
    // PĀRSKATA LAPA → VAIRĀKI IERAKSTI (lietotāja 2. punkts 2026-09-16): lapa ar vairākām programmām nav viens konkurss,
    // bet tās bloki ar pašu termiņu AR GADU ir. Bez termiņa ar gadu lapa paliek ārpus saraksta (sk. gr_vt_virslapas_ieraksti).
    $vs = gr_vt_virslapas_ieraksti($url, $html, $vt, $sodien);
    if ($vs) gr_lv_log($log, sprintf('  · %s: pārskata lapa sadalīta %d ierakstos (%s)', $vt['host'], count($vs), mb_substr($url, 0, 70)));
    return $vs;
}

/** Vai lapai vajag ārēju gada enkuru: satura datumi ir, bet neviens ar gadu, un publicēšanas datums lapā nav atrodams. */
function gr_vt_vajag_enkuru(string $html, string $url, string $sodien): bool {
    [, $satHtml] = gr_lv_satura_html($html);
    $d = gr_lv_datumi_teikuma(mb_substr(gr_lv_teksts($satHtml), 0, 12000));
    return $d !== [] && !array_filter($d, fn($x) => $x['y'] !== null) && gr_vt_publicesanas_datums($html, $url, $sodien) === null;
}

/**
 * WordPress raksta vai lapas datums no REST API (/wp-json/wp/v2/{posts,pages}?slug=…&_fields=date). Pieprasījums iet caur
 * ievācēju, tātad robots.txt pārbaude un pauze ir tās pašas; ja /wp-json/ liegts vai nav WordPress — null.
 */
function gr_vt_wp_datums(string $url, string $html, string $sodien): ?string {
    if (!preg_match('~/wp-content/|api\.w\.org|wpcf7~', $html)) return null;
    $slug = basename(rtrim((string)parse_url($url, PHP_URL_PATH), '/'));
    $host = (string)parse_url($url, PHP_URL_HOST);
    if ($host === '' || !preg_match('~^[a-z0-9-]{3,200}$~', $slug)) return null;
    foreach (['posts', 'pages'] as $tips) {
        $g = gr_vt_get("https://$host/wp-json/wp/v2/$tips?slug=$slug&_fields=date", 15);
        if (!$g['ok']) continue;
        $j = json_decode((string)$g['body'], true);
        if (is_array($j) && preg_match('~^(20\d{2})-(\d{2})-(\d{2})~', (string)($j[0]['date'] ?? ''), $m)
            && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) && "$m[1]-$m[2]-$m[3]" <= $sodien) return "$m[1]-$m[2]-$m[3]";
    }
    return null;
}

/** Lapas HTML → ieraksts vai null. Visi vārti ir šeit (sk. faila galvu); bez tīkla, lai to var testēt. */
function gr_vt_lapas_ieraksts(string $url, string $html, array $vt, bool $arMi, ?callable $log, string $saitesTeksts, string $sodien,
                              ?string $publArejais = null): ?array {
    $cels = (string)parse_url($url, PHP_URL_PATH);
    if (preg_match(GR_VT_NE_ADRESE, $cels)) return null;
    // SARAKSTU ADRESES: WordPress kategorijas un birkas, lapošana (lrpartneriba.lv /category/ejzaf-projektu-konkursi-…).
    if (preg_match('~(?:^|/)(?:category|kategorija|kategorijas|tag|birka|page/\d+)(?:/|$)~i', $cels)) return null;
    [$h1, $satHtml] = gr_lv_satura_html($html);
    // SAISTĪTO ZIŅU BLOKS NAV ŠĪ LAPA (audits 2026-09-16). Rēzeknes ziņai "Neformālās apmācības jauniešiem" satura nav vispār,
    // un virsraksts, termiņš un granta pazīmes tika ņemtas no bloka "Citas ziņas" — citas lapas 10. kārtas izvilkuma.
    // Troksni griež jebkurā garumā (gr_lv_teksts to dara tikai virs 400 zīmēm; sk. gr_lv_saturs_pec_h1).
    $sat = gr_f_bez_saistitajam(mb_substr(gr_lv_bez_troksna(gr_lv_teksts($satHtml)), 0, 12000));
    // Lapas paša virsraksts parasti sākas satura sākumā arī tad, ja <h1> nav (abulas.lv) — šķīrējam tas ir viens no kandidātiem.
    $satSakums = mb_substr($sat, 0, 120);
    // SARAKSTS PĒC SAITĒM: ≥3 citas tā paša hosta saites ar izsludināšanas zīmi SATURA daļā. Saraksta lapa
    // ar ziņu izvilkumiem (kandavaspartneriba.lv /projektu-konkursi/) kļuva par ierakstu ar citu termiņu
    // nekā pati ziņa. Izvēlnes vārdi ("Projektu konkursi") netiek skaitīti.
    $zSaites = 0;
    foreach (gr_vt_saites($satHtml, $vt['host'], $url) as $su => $st)
        if (gr_iev_normalize($su) !== gr_iev_normalize($url) && preg_match(GR_VT_ZIMES, $st) && !preg_match(GR_VT_NE, $st)
            && !gr_vt_ir_saraksta_virsraksts($st) && ++$zSaites >= 3) return null;
    // H1 AR "NE" ZĪMI IZŠĶIR PIRMS AIZSTĀŠANAS (recenzents K2): «Konkursu rezultāti 2026» tika
    // aizstāts ar teikumu no satura, un NE vārti redzēja tikai aizstājēju.
    if ($h1 !== '' && preg_match(GR_VT_NE, $h1)) return null;
    $nos = $h1;
    $titleTag = preg_match('~<title>(.*?)</title>~is', $html, $tm) ? gr_lv_teksts($tm[1]) : '';
    // VIRSRAKSTS, KAS IR LOGO. Daļā LEADER vietņu <h1> ir organizācijas vārds ("PPP ZIED ZEME").
    // Secība: (1) pirmais satura teikums ar izsludināšanas zīmi (adreses vārdi izvēlas, kurš);
    // (2) <title>; (3) saites teksts — navigācijā tas mēdz būt SARAKSTA nosaukums.
    // INFORMATĪVS H1 PALIEK: "Līdzdalības budžets – iespēja iedzīvotājiem lemt …" (Valmiera, būve 2026-09-13) ir labāks
    // virsraksts par teikumu no satura. Aizstājam tikai logo vai organizācijas vārdu ("Brasla", "PPP ZIED ZEME").
    $h1Informativs = $h1 !== '' && preg_match(GR_VT_VIRSRAKSTA_VARDI, $h1) === 1 && count(preg_split('~\s+~u', trim($h1)) ?: []) >= 3;
    if (!preg_match(GR_VT_ZIMES, $nos) && !$h1Informativs) {
        $adrVardi = preg_split('~[-_/]+~', strtolower((string)parse_url($url, PHP_URL_PATH))) ?: [];
        $teik = gr_vt_pirmais_teikums_ar_zimi(mb_substr($sat, 0, 4000), $adrVardi);
        if ($teik !== '') $nos = $teik;
        elseif ($titleTag !== '' && preg_match(GR_VT_ZIMES, $titleTag)) $nos = $titleTag;
        elseif ($saitesTeksts !== '' && $saitesTeksts !== 'sēkla' && preg_match(GR_VT_ZIMES, $saitesTeksts)) $nos = $saitesTeksts;
        elseif ($nos === '') $nos = $titleTag;
    }
    $nos = gr_vt_tirit_virsrakstu($nos);
    if ($nos === '' || preg_match(GR_VT_NE, $nos) || gr_vt_ir_saraksta_virsraksts($nos)) return null;
    if (!preg_match('~\p{L}{3,}~u', $nos) || (!preg_match(GR_VT_ZIMES, $nos) && !preg_match(GR_VT_VIRSRAKSTA_VARDI, $nos))) return null;
    // SĀKUMLAPA ir ieraksts tikai ar tiešu paziņojumu virsrakstā (zgauja.lv "Izsludināta … 6. kārta"), ne ar
    // nejaušu teikumu no ziņu lentes (ekii.lv "Informācija par … aktualitātēm").
    if ((trim($cels, '/') === '' || preg_match('~^/(?:lv|index\.php|sakums?)/?$~i', $cels))
        && !preg_match('~izsludin|pieņemšanas\s+\d+\.\s*kārt|paziņojums\s+par\s+atklāt~ui', $nos)) return null;
    if (!preg_match(GR_VT_ZIMES, $nos . ' ' . mb_substr($sat, 0, 1500))) return null;
    $sakums = mb_substr($sat, 0, 2000);
    $slegtas = preg_match_all(GR_VT_SATURS_SLEGTS, $sakums, $sm, PREG_SET_ORDER)
        ? array_map(fn($x) => gr_lv_skaitlis(($x[1] ?? '') !== '' ? $x[1] : (string)($x[2] ?? '')), $sm) : [];
    $nrV = gr_vt_kartu_numuri($nos);
    $irUzsaukums = preg_match('~izsludin|pieņemšan|pieteikšan~ui', $nos) === 1;
    if (($nrV && array_intersect($nrV, $slegtas)) || (!$nrV && $slegtas && !$irUzsaukums)) return null;
    if (!$irUzsaukums && preg_match('~sarindot\w*\s+projekt~ui', $sakums)) return null;
    // KĀRTAS NUMURS (recenzents V-3): virsraksts «… 5. kārta» ar blakus bloku "Pieteikšanās 6. kārtai līdz 03.11.2026." dabūja
    // 6. kārtas termiņu. Ja virsraksts min vienu kārtu, termiņu meklē tikai teikumos, kas nemin citu kārtu.
    if (count($nrV) === 1)
        $sat = implode(' ', array_filter(gr_lv_teikumi(preg_replace('~\s+~u', ' ', $sat) ?? $sat),
            fn($t) => !($n2 = gr_vt_kartu_numuri($t)) || in_array($nrV[0], $n2, true)));
    // Sākumlapā "publicēšanas datums" ir jaunākās ziņas datums, ne šī paziņojuma (Talsu partnerība 2026-09-13) — tur neņem.
    $publ = $publArejais ?? ((trim($cels, '/') === '' || preg_match('~^/(?:lv|index\.php|sakums?)/?$~i', $cels)) ? null : gr_vt_publicesanas_datums($html, $url, $sodien));
    $iv = gr_lv_termina_intervals($sat, $publ, $sodien, $termSk);
    // SARAKSTS PĒC TERMIŅIEM: ≥4 dažādi pieteikšanās termiņi vienā lapā ir konkursu saraksts bez saitēm
    // (km.gov.lv/lv/konkursi — seši konkursi, un ieraksts dabūja pirmā virsrakstu un agrākā termiņu).
    if ($termSk >= 4) return null;
    if ($iv['lidz'] === null || $iv['lidz'] < $sodien) return null;
    // VIRSRAKSTA KANDIDĀTI (audits 2026-09-16): $nos mēdz būt teikums no satura (logo <h1>), un tad šķīrēja virsraksta veto
    // neredz, ka lapa ir par konsultācijām (abulas.lv <title>) vai ka tā ir pārskata lapa (km.gov.lv <h1>).
    $fv = gr_filtrs_pilns(['avots' => $vt['grupa'], 'nosaukums' => $nos, 'teksts' => $sat, 'termins' => $iv['lidz'],
        'virsraksti' => [$h1, gr_vt_tirit_virsrakstu($titleTag), $satSakums]], $arMi, $log);
    if ($fv['verdikts'] !== 'grants') return null;

    return [
        'id' => gr_vt_id($vt['grupa'], $vt['host'], $url),
        // STATUSS NO INTERVĀLA (recenzents M5): Kandavas kārta ar pieņemšanu no 20.09. 13.09. bija "atvērta".
        'source' => $vt['grupa'], 'status' => ($iv['no'] !== null && $iv['no'] > $sodien) ? 'forthcoming' : 'open',
        'title' => $nos, 'programme' => $vt['grupa'], 'programme_label' => $vt['nos'],
        'opening_date' => $iv['no'], 'deadline' => $iv['lidz'], 'budget' => gr_lv_summa(mb_substr($sat, 0, 4000)),
        'url' => $url, 'nozare' => gr_lv_nozare($nos, $vt['nozare']),
        'model' => gr_vt_ir_lidzdalibas_budzets($nos, $sat) ? 'ideju_konkurss' : 'atkariba',
        'keywords' => '',
        // LEADER projektus iesniedz LAD EPS — tā pati sistēma, kas LAD kārtām; bez tā lapas
        // CTA teksts par EPS nekad neparādījās (tas ir zarā, kas prasa submit_url).
        'submit_url' => $vt['grupa'] === 'LEADER' ? 'https://eps.lad.gov.lv/login' : null,
        'division' => $vt['host'],
    ];
}

/**
 * PĀRSKATA LAPA → VAIRĀKI IERAKSTI (lietotāja 2. punkts, 2026-09-16). Jelgavas "Pašvaldības atbalsta programmas
 * jelgavniekiem" lapā ir sešas īstas programmas ar budžetiem (290 000, 160 000, 100 000 €); kā VIENS ieraksts tā maldina
 * (kurš no sešiem termiņiem?), tāpēc šķīrējs to sauc par virslapu. Šeit lapa kļūst par tik ierakstiem, cik <h2>/<h3>
 * blokos ir PATS SAVS termiņš AR GADU pašā blokā.
 *
 * GADA ENKURS TE NETIEK LIETOTS. Jelgavas lapas publicēšanas datums ir 2024-03-13, bet saturs ir par 2026. gadu: ar enkuru
 * desmit bloki dabūtu 2024. un 2025. gada termiņus. Ziņnesim nepareizs termiņš ir sliktāks nekā neviens, tāpēc gadam jābūt
 * tekstā (publ = null, K-A politika). Uz 465 kešotām lapām tas šodien nedod nevienu ierakstu — funkcija ir nākotnes lapām,
 * kur programmu blokos ir pilni datumi.
 *
 * Vismaz DIVI bloki: lapa ar vienu datētu bloku ir parasta konkursa lapa, ko apstrādā gr_vt_lapas_ieraksts().
 */
function gr_vt_virslapas_ieraksti(string $url, string $html, array $vt, string $sodien, int $maks = 5): array {
    $cels = (string)parse_url($url, PHP_URL_PATH);
    if (preg_match(GR_VT_NE_ADRESE, $cels)
        || preg_match('~(?:^|/)(?:category|kategorija|kategorijas|tag|birka|page/\d+)(?:/|$)~i', $cels)) return [];
    [, $satHtml] = gr_lv_satura_html($html);
    $out = [];
    foreach (preg_split('~(?=<h[23]\b)~i', $satHtml) ?: [] as $gab) {
        if (!preg_match('~<h([23])\b[^>]*>(.*?)</h\1>~is', $gab, $hm)) continue;
        $nos = gr_vt_tirit_virsrakstu(gr_lv_teksts($hm[2]));
        if ($nos === '' || preg_match(GR_VT_NE, $nos) || gr_vt_ir_saraksta_virsraksts($nos)) continue;
        if (!preg_match(GR_VT_ZIMES, $nos) && !preg_match(GR_VT_VIRSRAKSTA_VARDI, $nos)) continue;
        $sat = gr_f_bez_saistitajam(mb_substr(gr_lv_teksts($gab), 0, 6000));
        $sk = 0;
        $iv = gr_lv_termina_intervals($sat, null, $sodien, $sk);
        if ($sk >= 4 || $iv['lidz'] === null || $iv['lidz'] < $sodien) continue;
        if (gr_filtrs(['avots' => $vt['grupa'], 'nosaukums' => $nos, 'teksts' => $sat, 'termins' => $iv['lidz']])['verdikts'] !== 'grants') continue;
        $out[] = [
            // ID no lapas + bloka virsraksta: gr_vt_id() normalizē adresi un fragmentu nogriež, tāpēc ar "#slug" adresē
            // abi bloki dabūja vienu ID un otrs pārrakstīja pirmo (audits 2026-09-16).
            'id' => gr_vt_id($vt['grupa'], $vt['host'], $url) . '-' . gr_lv_id_dala(mb_strtolower($nos)),
            'source' => $vt['grupa'], 'status' => ($iv['no'] !== null && $iv['no'] > $sodien) ? 'forthcoming' : 'open',
            'title' => $nos, 'programme' => $vt['grupa'], 'programme_label' => $vt['nos'],
            'opening_date' => $iv['no'], 'deadline' => $iv['lidz'], 'budget' => gr_lv_summa(mb_substr($sat, 0, 4000)),
            'url' => $url, 'nozare' => gr_lv_nozare($nos, $vt['nozare']),
            'model' => gr_vt_ir_lidzdalibas_budzets($nos, $sat) ? 'ideju_konkurss' : 'atkariba',
            'keywords' => '',
            'submit_url' => $vt['grupa'] === 'LEADER' ? 'https://eps.lad.gov.lv/login' : null,
            'division' => $vt['host'],
        ];
        if (count($out) >= $maks) break;
    }
    return count($out) >= 2 ? $out : [];
}

/**
 * Rotācijas pārneses WHERE nosacījums un parametri — tīra funkcija, lai to var testēt bez būves.
 *
 * Pārnes PASV/LEADER/VALSTS rindas ar hostu un vēl nepagājušu termiņu, IZŅEMOT vietnes, kuras
 * šajā būvē apmeklētas pilnībā (tur svaigā vākšana ir patiesība), un VIAA/NVA, ja to plūsma
 * šonakt atbildēja. Ar --skip-vietnes apmeklēto nav, tāpēc izslēdz tikai svaigās ziņu plūsmas.
 * Recenzents šo loģiku pārbaudīja, izgriežot būves rindas pēc numuriem; tas salūza pie pirmās
 * izmaiņas — tāpēc tā dzīvo šeit un tai ir savi testi.
 *
 * @return array{0:string,1:array}
 */
function gr_vt_parneses_nosacijums(array $apmekletas, array $svaigiHosti, bool $skipVietnes, string $sodien): array {
    $w = "source IN ('PASV','LEADER','VALSTS') AND division IS NOT NULL"
       . " AND COALESCE(deadline_apply, deadline_date, '9999') >= ?";
    $args = [$sodien];
    $izslegt = array_values(array_unique(array_merge($skipVietnes ? [] : $apmekletas, $svaigiHosti)));
    if ($izslegt) {
        $w .= ' AND division NOT IN (' . implode(',', array_fill(0, count($izslegt), '?')) . ')';
        $args = array_merge($args, $izslegt);
    }
    return [$w, $args];
}

/**
 * Pārnesto dublikātu DELETE (build.php pēc rotācijas pārneses): tā pati normalizētā adrese (bez shēmas, www, gala slīpsvītras) tā
 * paša hosta ietvaros. Svaigu rindu nedzēš nekad; no diviem pārnestiem paliek pirmais. Funkcija, lai SQL var testēt bez būves.
 */
function gr_vt_parnesto_dublikatu_sql(): string {
    $nu = static fn(string $c): string => "rtrim(replace(replace(replace(lower(COALESCE($c,'')),'https://',''),'http://',''),'www.',''),'/')";
    $nd = static fn(string $c): string => "replace(lower(COALESCE($c,'')),'www.','')";
    return "DELETE FROM grants WHERE id IN (SELECT id FROM gr_parnestie) AND url IS NOT NULL AND url <> '' AND EXISTS (
        SELECT 1 FROM grants g2 WHERE g2.source IN ('PASV','LEADER','VALSTS') AND g2.url IS NOT NULL
          AND {$nd('g2.division')} = {$nd('grants.division')} AND {$nu('g2.url')} = {$nu('grants.url')}
          AND (g2.id NOT IN (SELECT id FROM gr_parnestie) OR g2.rowid < grants.rowid))";
}

/**
 * Savāc vietnes ar rotāciju.
 * @return array{rindas:array, apmekletas:array, neizdevas:array, izlaistas:array}
 */
function gr_vt_savac(?callable $log = null, bool $arMi = false, bool $izstrade = false,
                     int $vietnuBudzets = 16, int $lapuUzVietni = 6): array {
    $rotF = granti_data_dir() . '/vietnu_rotacija.json';
    $rot = is_file($rotF) ? (json_decode((string)file_get_contents($rotF), true) ?: []) : [];
    $reg = gr_vt_registrs($log);
    $izlaistas = [];
    $kandidates = array_values(array_filter($reg, function ($v) use ($izstrade, &$izlaistas) {
        if (!empty($v['atslegts'])) { $izlaistas[$v['host']] = $v['atslegts']; return false; }
        // Statiskais karodziņš tikai EKII (zināms iepriekš). Visām pārējām vietnēm to pašu katrai adresei pārbauda
        // ievācējs (gr_iev_atlauts izstrādes režīmā: Anthropic marķieru precīzās robots grupas) — recenzents M6.
        if ($izstrade && !empty($v['mi_liegts'])) { $izlaistas[$v['host']] = 'robots.txt liedz ClaudeBot — izstrādes režīmā neapmeklē'; return false; }
        return true;
    }));
    // Senāk apmeklētās pirmās; nekad neapmeklētās (0) — pašas pirmās.
    usort($kandidates, fn($a, $b) => ($rot[$a['host']] ?? 0) <=> ($rot[$b['host']] ?? 0));
    $sis = array_slice($kandidates, 0, $vietnuBudzets);

    $rindas = []; $apm = []; $neizd = []; $liegtas = []; $dalejas = []; $miLiegtas = [];
    $sakums = microtime(true);
    foreach ($sis as $vt) {
        if (($GLOBALS['gr_vt_pieprasijumi'] ?? 0) >= GR_VT_MAX_PIEPRASIJUMI || microtime(true) - $sakums > GR_VT_MAX_SEKUNDES) {
            gr_lv_log($log, '  · vietņu griesti sasniegti (' . ($GLOBALS['gr_vt_pieprasijumi'] ?? 0) . ' pieprasījumi, '
                . (int)(microtime(true) - $sakums) . ' s) — pārējās vietnes nākamajā būvē.');
            break;
        }
        // STOP no paneļa (sk. build.php $gr_apturets).
        if (function_exists('gr_stop_requested') && gr_stop_requested()) {
            gr_lv_log($log, '🛑 STOP karodziņš — vietņu vākšana pārtraukta.');
            $GLOBALS['gr_apturets'] = true;
            break;
        }
        // KĻŪMES IZOLĀCIJA PA VIETNĒM. Bez tās viens izņēmums (NUL baits saitē → curl ValueError)
        // nogāza visu būvi — arī ES, SIF un LV datus —, un rotācijas stāvoklis netika saglabāts,
        // tātad nākamajā naktī tā pati vietne bija pirmā un krita atkal.
        try {
            $kluda = null; $dalejsK = false;
            $kand = gr_vt_kandidati($vt, $lapuUzVietni, $log, $kluda, $dalejsK);
            $rot[$vt['host']] = time();   // arī neizdevušās: citādi tās vienmēr stāv rindas priekšā un ēd budžetu
            if ($kand === null) {
                // LIEGUMS NAV NESASNIEDZAMĪBA. robots.txt Disallow un WAF 403 ir vietnes īpašnieka
                // lēmums — to neziņojam kā kļūmi un nemēģinām katru nakti no jauna ar brīdinājumu.
                // Izstrādes režīma liegums (robots.txt liedz Anthropic rāpuļiem) nav ne īpašnieka liegums mūsu rāpulim,
                // ne kļūme: serverī šo vietni apmeklēs. Atsevišķs saraksts — būve to rindas izstrādē nepārnes.
                if (str_contains((string)$kluda, 'izstrādes režīms')) { $miLiegtas[$vt['host']] = (string)$kluda; continue; }
                if (preg_match('~^robots:|HTTP 403~', (string)$kluda)) $liegtas[$vt['host']] = (string)$kluda;
                else $neizd[] = $vt['host'];
                continue;
            }
            $n = 0; $kaut = (bool)$dalejsK;   // sēklas vai saraksta lapas pārejoša kļūme arī ir daļējs apmeklējums
            foreach ($kand as $u => $saitesTeksts) {
                $nz = false;
                // Viena lapa var dot vairākus ierakstus: pārskata lapa ar vairākām programmām (gr_vt_virslapas_ieraksti).
                foreach (gr_vt_lapa($u, $vt, $arMi, $log, (string)$saitesTeksts, $nz) as $r) { $rindas[$r['id']] = $r; $n++; }
                if ($nz) $kaut = true;
            }
            // DAĻĒJS APMEKLĒJUMS. Ja kāda kandidātlapa neatbildēja, vietne NESKAITĀS apmeklēta
            // pārnesei: tās vecās rindas paliek, citādi konkurss pazustu līdz nākamajai rotācijai
            // tikai tāpēc, ka viena lapa tajā naktī atdeva 404 vai taimautu.
            if ($kaut) $dalejas[] = $vt['host']; else $apm[] = $vt['host'];
            if ($n > 0) gr_lv_log($log, "  · {$vt['nos']}: $n konkursi ar dzīvu termiņu.");
        } catch (Throwable $e) {
            $neizd[] = $vt['host'];
            gr_lv_log($log, "  ! {$vt['host']}: " . $e->getMessage());
        }
    }
    $rindas = gr_vt_dedup(array_values($rindas));
    // Rotācijas stāvokli NERAKSTA šeit: būve to dara tikai pēc veiksmīgas publicēšanas, lai
    // apturēta vai kritusi būve neatzīmētu vietnes kā apmeklētas, kaut dati izmesti.
    gr_lv_log($log, sprintf('Vietnes: apmeklētas %d, daļēji %d, liegtas %d, izstrādes režīmā liegtas %d, neizdevās %d, izlaistas %d; atrasti %d konkursi.',
        count($apm), count($dalejas), count($liegtas), count($miLiegtas), count($neizd), count($izlaistas), count($rindas)));
    if ($miLiegtas) gr_lv_log($log, '  · izstrādes režīmā liegtas (serverī apmeklēs): ' . implode('; ', array_map(fn($h, $k) => "$h ($k)", array_keys($miLiegtas), $miLiegtas)));
    if ($neizd) gr_lv_log($log, '  ! neizdevās: ' . implode(', ', $neizd));
    if ($liegtas) gr_lv_log($log, '  · liedz paši: ' . implode('; ', array_map(fn($h, $k) => "$h ($k)", array_keys($liegtas), $liegtas)));
    if ($dalejas) gr_lv_log($log, '  · daļēji (rindas pārnes): ' . implode(', ', $dalejas));
    if ($izlaistas) gr_lv_log($log, '  · izlaistas: ' . implode('; ', array_map(fn($h, $k) => "$h ($k)", array_keys($izlaistas), $izlaistas)));
    return ['rindas' => array_values($rindas), 'apmekletas' => $apm, 'neizdevas' => $neizd,
            'liegtas' => $liegtas, 'dalejas' => $dalejas, 'izlaistas' => $izlaistas,
            'mi_liegtas' => array_values(array_unique(array_merge(array_keys($miLiegtas),
                array_keys(array_filter($izlaistas, fn($k) => str_contains($k, 'ClaudeBot')))))),
            'rotacija' => $rot, 'rotacijas_fails' => $rotF];
}
