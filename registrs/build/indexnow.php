<?php
/**
 * registrs/build/indexnow.php — mainīto uzņēmumu lapu pieteikšana IndexNow protokolā.
 *
 * KĀPĒC: Bing Webmaster Tools 2026-09-09 rādīja, ka kopš 08-08 iesniegti 6 300 URL un
 * VISI ir statiskas datnes (nozare/nace-foto/*.webp, horoskops/onet/*.csv, favicon,
 * pat lejupielade/saraksts-lv-kods.zip) — neviena uzņēmuma lapa. Cēlonis nav kļūda
 * konfigurācijā: sūtītājs ir Cloudflare Crawler Hints, un tas signalizē pēc SAVA keša,
 * bet HTML lapas caur Cloudflare nekad netiek kešotas (cf-cache-status: DYNAMIC).
 * Tātad tieši tās lapas, kuru dēļ protokols ir vajadzīgs, tur nevar nonākt pēc
 * konstrukcijas. Daļai sūtīto vēl `.htaccess` liek `X-Robots-Tag: noindex`.
 *
 * KO DARA: nakts būves 5. posmā paņem uzņēmumus, kuriem PARĀDĪJIES JAUNS gada pārskats
 * (report_tracker `$rt['changed']`), un pasaka par tiem IndexNow galapunktam. Tas ir
 * vienīgais brīdis, kad lapas saturs tiešām mainās; pārējās dienās lapa ir tā pati.
 *
 * ATSLĒGAS GLABĀŠANA — divi pretēji spiedieni, abi ievēroti.
 * (1) Verifikācijas failam JĀBŪT docroot saknē. Apakšmapē tas verificē tikai tās
 *     apakšmapes URL: mēģinājums ar `/indexnow/<atslēga>.txt` 2026-09-09 atdeva
 *     HTTP 422 "One or more URLs are not related to your site verified through the
 *     keylocation parameter" — uzņēmumu lapas ir saknē, atslēga bija apakšmapē.
 * (2) Saknes `.txt` faili nonāk publiskajā ZIP (tur jau ceļo robots.txt un llms.txt),
 *     un pakotnes skrubis pārbauda tikai SATURU, ne nosaukumus — tātad `<atslēga>.txt`
 *     aizplūstu ar pašu atslēgu nosaukumā.
 * Risinājums: fails ir saknē, bet `tools/build_download.php` to izslēdz pēc ŠABLONA
 * (32 heksadecimālas rakstzīmes + .txt), nevis pēc vārda — tā atslēga nenonāk ne
 * pakotnē, ne pakotājā kodā. Privātā kopija lasīšanai glabājas `indexnow/key.txt`
 * (visa mape ir EXCLUDE_DIRS).
 *
 * DROŠĪBA: nekad nemet izņēmumu uz augšu un nekad neaptur būvi. IndexNow ir papildu
 * signāls, ne datu ceļš.
 */
declare(strict_types=1);

const INDEXNOW_ENDPOINT   = 'https://api.indexnow.org/IndexNow';
const INDEXNOW_MAX_URLS   = 10000;   // protokola griests vienam pieprasījumam
const INDEXNOW_TIMEOUT_S  = 20;

/** Atslēgas mape docroot iekšienē (izslēgta no lejupielādes pakotnes). */
function indexnow_dir(): string {
    // reg_docroot() nāk no registrs/lib/paths.php. Būvē tas jau ir ielādēts, bet failu
    // var izsaukt arī atsevišķi (rokas pieteikšana), tāpēc ielādē pats, ja trūkst.
    if (!function_exists('reg_docroot')) require_once __DIR__ . '/../lib/paths.php';
    return reg_docroot() . '/indexnow';
}

/**
 * Atgriež IndexNow atslēgu, vajadzības gadījumā to izveidojot.
 *
 * Atslēga ir 32 heksadecimālas rakstzīmes (protokols atļauj 8–128 no [a-zA-Z0-9-]).
 * Glabājas divos failos: `key.txt` (no kā to lasa kods) un `<atslēga>.txt` (ko prasa
 * protokols verifikācijai). Ja mapi neizdodas izveidot, atgriež null — sūtīšana klusi
 * izlaista.
 */
function indexnow_key(?callable $log = null): ?string
{
    $dir = indexnow_dir();
    $keyfile = $dir . '/key.txt';
    try {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return null;

        $key = is_file($keyfile) ? trim((string)@file_get_contents($keyfile)) : '';
        if (!preg_match('/^[a-zA-Z0-9-]{8,128}$/', $key)) {
            $key = bin2hex(random_bytes(16));
            if (@file_put_contents($keyfile, $key . "\n") === false) return null;
            if ($log) $log('  IndexNow: izveidota jauna atslēga');
        }
        // Verifikācijas fails DOCROOT SAKNĒ ar atslēgu saturā (sk. galvas 1. punktu).
        $pub = reg_docroot() . '/' . $key . '.txt';
        if (!is_file($pub)) @file_put_contents($pub, $key);
        return $key;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Nosūta URL kopu IndexNow. Atgriež [nosūtīto skaits, HTTP kodu saraksts].
 *
 * Atbildes kodi (indexnow.org/documentation): 200 pieņemts, 202 pieņemts bet atslēga
 * vēl tiek pārbaudīta, 400 slikts formāts, 403 atslēga neder, 422 URL nepieder hostam,
 * 429 par biežu. Neviens no tiem nav iemesls apturēt būvi — tos tikai ierakstām žurnālā.
 */
function indexnow_submit(array $urls, string $base_domain, ?callable $log = null): array
{
    $urls = array_values(array_unique(array_filter($urls, fn($u) => is_string($u) && $u !== '')));
    if (!$urls) return [0, []];

    $key = indexnow_key($log);
    if ($key === null) {
        if ($log) $log('  IndexNow: atslēga nav pieejama — izlaists');
        return [0, []];
    }

    $host = parse_url($base_domain, PHP_URL_HOST) ?: 'saraksts.lv';
    $sent = 0; $codes = [];

    foreach (array_chunk($urls, INDEXNOW_MAX_URLS) as $chunk) {
        $payload = json_encode([
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => rtrim($base_domain, '/') . '/' . $key . '.txt',
            'urlList'     => $chunk,
        ], JSON_UNESCAPED_SLASHES);

        try {
            $ch = curl_init(INDEXNOW_ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => INDEXNOW_TIMEOUT_S,
            ]);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            $codes[] = $code;
            if ($code === 200 || $code === 202) $sent += count($chunk);
            elseif ($log) $log('  IndexNow: HTTP ' . $code . ($err !== '' ? ' (' . $err . ')' : ''));
        } catch (Throwable $e) {
            if ($log) $log('  IndexNow: kļūda — ' . $e->getMessage());
        }
    }
    return [$sent, $codes];
}

/**
 * Ērtais ceļš būvei: reģistrācijas numuru saraksts → uzņēmumu lapu URL → nosūtīšana.
 */
function indexnow_submit_regcodes(array $regcodes, string $base_domain, ?callable $log = null): int
{
    $urls = [];
    foreach ($regcodes as $rc) {
        $rc = trim((string)$rc);
        if (preg_match('/^\d{11}$/', $rc)) $urls[] = rtrim($base_domain, '/') . '/' . $rc;
    }
    if (!$urls) return 0;
    [$sent, $codes] = indexnow_submit($urls, $base_domain, $log);
    if ($log) {
        $log('  IndexNow: pieteiktas ' . $sent . ' no ' . count($urls) . ' lapām'
             . ($codes ? ' (HTTP ' . implode(', ', array_unique($codes)) . ')' : ''));
    }
    return $sent;
}
