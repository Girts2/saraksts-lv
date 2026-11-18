<?php
/**
 * granti/bin/zonde.php — KANDIDĀTU AVOTU ZONDE (tikai CLI, neko neievāc un nekur neraksta DB).
 *
 * Atbild uz vienu jautājumu par katru kandidātu: vai kods no šī avota VAR nolasīt konkursus,
 * un ja nē — kāpēc tieši. Bez šī soļa avotu saraksts ir minējums; ar to tas ir mērījums.
 *
 * Zonde apzināti prasa TIKAI vienu lapu no katra hosta un iet caur lib/ievacejs.php, tāpēc
 * tā respektē robots.txt un pauzes tāpat kā īstais ievācējs. Vietnes, kuru robots.txt nosauc
 * MI rāpuļus ('mi_liegts'), zonde IZLAIŽ: tās pārbauda pats serveris ar savu marķieri.
 *
 * Lietojums: php granti/bin/zonde.php [--json] [--kods=cfla.api]
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/ievacejs.php';
require_once __DIR__ . '/../lib/avoti_kandidati.php';

$opts = getopt('', ['json', 'kods::']);

/** HTML -> redzamais teksts (bez skriptiem un stiliem). */
function z_teksts(string $html): string {
    $t = preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
    $t = preg_replace('~<[^>]+>~', ' ', $t) ?? $t;
    return trim(preg_replace('~\s+~u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

/** Cik lapā ir datumu, kas izskatās pēc termiņa. Latviski tie ir četros formātos. */
function z_datumi(string $t): int {
    $n = 0;
    foreach ([
        '~\b\d{1,2}\.\s*\d{1,2}\.\s*\d{4}\b~u',                       // 15.10.2026
        '~\b\d{4}-\d{2}-\d{2}\b~',                                     // ISO
        '~\b\d{4}\.\s*gada\s*\d{1,2}\.\s*\p{L}+~u',                    // 2026. gada 15. oktobrī
        '~\b\d{1,2}\.\s*(janvār|februār|mart|aprīl|maij|jūnij|jūlij|august|septembr|oktobr|novembr|decembr)\p{L}*~u',
    ] as $re) { $n += preg_match_all($re, $t) ?: 0; }
    return $n;
}

/** Cik lapā ir naudas summu. Bez tām "konkursu saraksts" parasti ir kalendārs vai ziņas. */
function z_summas(string $t): int {
    return (preg_match_all('~\d[\d\s\x{00A0}.,]*\s*(?:EUR|€|eiro)~ui', $t) ?: 0)
         + (preg_match_all('~\b\d+[.,]?\d*\s*(?:milj|miljon|tūkst)~ui', $t) ?: 0);
}

/** Vai HTML ir tikai JS čaula (saturs top ielādēts pārlūkā, un mūsu curl to neredz)? */
function z_spa(string $html, string $teksts): bool {
    $markieri = ['id="root"', 'id="app"', '__NUXT__', '__NEXT_DATA__', 'window.__INITIAL_STATE__',
                 'data-reactroot', 'ng-version', 'id="__layout"'];
    $ir = false;
    foreach ($markieri as $m) if (stripos($html, $m) !== false) { $ir = true; break; }
    // Marķieris VIEN nepietiek: Drupal lapā arī ir <div id="app"> blakus pilnam HTML saturam.
    // Čaula ir tad, kad marķieris ir UN redzamā teksta praktiski nav.
    return $ir && mb_strlen($teksts) < 1200;
}

$rez = [];
foreach (gr_avotu_kandidati() as $a) {
    if (isset($opts['kods']) && $opts['kods'] !== '' && $a['kods'] !== $opts['kods']) continue;
    $r = ['kods' => $a['kods'], 'iestade' => $a['iestade'], 'nos' => $a['nos'], 'url' => $a['url'],
          'licence' => $a['licence'], 'kods_http' => 0, 'klase' => '', 'piezime' => '',
          'datumi' => 0, 'summas' => 0];

    if (!empty($a['mi_liegts'])) {
        $r['klase'] = 'IZLAISTS';
        $r['piezime'] = 'robots.txt nosauc MI rāpuļus — jāzondē no servera'
                      . (isset($a['signals']) ? ' (' . $a['signals'] . ')' : '');
        $rez[] = $r; continue;
    }

    $g = gr_iev_get($a['url'], 25);
    $r['kods_http'] = $g['kods'];
    $tips = strtolower(explode(';', $g['tips'])[0] ?? '');

    if (!$g['ok']) {
        if (str_starts_with($g['kapec'], 'robots:'))        { $r['klase'] = 'ROBOTS LIEDZ'; }
        elseif ($g['kods'] === 0)                            { $r['klase'] = 'NESASNIEDZAMS'; }
        elseif (in_array($g['kods'], [401, 403], true))      { $r['klase'] = 'BLOĶĒTS'; }
        elseif ($g['kods'] === 404)                          { $r['klase'] = 'ADRESE 404'; }
        elseif ($g['kods'] === 429)                          { $r['klase'] = 'ĀTRUMA LIMITS'; }
        else                                                 { $r['klase'] = 'HTTP ' . $g['kods']; }
        $r['piezime'] = $g['kapec'];
        $rez[] = $r; continue;
    }

    if (str_contains($tips, 'json')) {
        $j = json_decode($g['body'], true);
        $n = is_array($j) ? (is_array($j['data'] ?? null) ? count($j['data']) : count($j)) : 0;
        // Tukšs JSON ar HTTP 200 ir tipiskā "atvērtā API" ilūzija: atbild, bet neko nedod
        // anonīmam lietotājam (tā ir LIAA business.gov.lv problēma).
        $r['klase'] = $n > 0 ? 'MAŠĪNLASĀMS' : 'TUKŠS (vajag autorizāciju?)';
        // Datumus un summas skaita arī JSON pusē: "atdod 5 ierakstus" vēl nenozīmē, ka tajos
        // ir termiņš. Tieši šī atšķirība izšķir, vai avots der sadaļai vai tikai ziņu lentai.
        $r['datumi'] = z_datumi($g['body']); $r['summas'] = z_summas($g['body']);
        $r['piezime'] = $n . ' ieraksti; ' . strlen($g['body']) . ' B';
        $rez[] = $r; continue;
    }
    if (str_contains($tips, 'xml') || str_contains($tips, 'rss')) {
        $r['klase'] = 'XML/RSS'; $r['piezime'] = strlen($g['body']) . ' B'; $rez[] = $r; continue;
    }
    if (str_contains($tips, 'pdf')) { $r['klase'] = 'PDF'; $rez[] = $r; continue; }

    $t = z_teksts($g['body']);
    $r['datumi'] = z_datumi($t);
    $r['summas'] = z_summas($t);
    $jsonld = preg_match('~application/ld\+json~i', $g['body']) === 1;
    // Captcha KLĀTBŪTNE nav šķērslis: reCAPTCHA skripts ir arī parastā kontaktformā lapas
    // apakšā, un pirmajā redakcijā šis vārts nepatiesi apzīmogoja ALTUM un Nordic Culture
    // Point lapas, kuru saturs atnāca pilnībā. Šķērslis ir tikai tad, ja captcha ir UN
    // satura nav, t.i. lapas vietā atnāca pārbaudes siena.
    $captcha = preg_match('~recaptcha|hcaptcha|cf-turnstile~i', $g['body']) === 1 && mb_strlen($t) < 1500;

    if (z_spa($g['body'], $t))      { $r['klase'] = 'JS ČAULA';  $r['piezime'] = 'HTML bez satura (' . mb_strlen($t) . ' rakstz.)'; }
    elseif ($captcha)               { $r['klase'] = 'CAPTCHA';   $r['piezime'] = 'lapā ir captcha'; }
    elseif ($r['datumi'] >= 3)      { $r['klase'] = 'HTML AR TERMIŅIEM'; }
    elseif (mb_strlen($t) > 500)    { $r['klase'] = 'HTML BEZ TERMIŅIEM'; }
    else                            { $r['klase'] = 'TUKŠA LAPA'; }
    if ($jsonld) $r['piezime'] = trim($r['piezime'] . ' +JSON-LD');
    $r['piezime'] = trim($r['piezime'] . sprintf(' [teksts %d rakstz.]', mb_strlen($t)));
    $rez[] = $r;
}

if (isset($opts['json'])) {
    echo json_encode($rez, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
} else {
    printf("%-14s %-26s %-22s %5s  %4s %4s  %s\n", 'KODS', 'IESTĀDE', 'KLASE', 'HTTP', 'DAT', 'EUR', 'PIEZĪME');
    echo str_repeat('─', 126), "\n";
    foreach ($rez as $r)
        printf("%-14s %-26s %-22s %5s  %4d %4d  %s\n", $r['kods'], mb_substr($r['iestade'], 0, 26),
            $r['klase'], $r['kods_http'] ?: '—', $r['datumi'], $r['summas'], mb_substr($r['piezime'], 0, 46));
}
@file_put_contents(granti_data_dir() . '/zonde.json', json_encode($rez, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
