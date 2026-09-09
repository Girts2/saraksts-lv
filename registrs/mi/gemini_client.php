<?php
/**
 * registrs/mi/gemini_client.php — CENTRALIZĒTAIS Gemini API klients.
 *
 * VIENĪGĀ vieta (ārpus master_top.php straumēšanas), kur dzīvo Gemini API kods:
 * citas sadaļas (Konkursi u.c.) šo failu require un sauc funkcijas — mainot
 * modeli/atslēgu/API šeit, izmaiņas propagējas visur.
 *
 * Atslēga: registrs/mi/key.php (_get_g_key). Modelis: REG_GEMINI_MODEL — vairs
 * NE tas pats, ko MI panelis (tas dzīvo master_top.php $sse_model).
 */
declare(strict_types=1);

require_once __DIR__ . '/key.php';   // definē _get_g_key()

// Virsrakstu tulkošanas modelis. 2026-09-02 nomainīts no gemini-3-flash-preview
// pēc A/B mērījuma uz 200 svešvalodu virsrakstiem (konkursi/bin/translate_ab.php):
// izmaksas 0,122 → 0,061 €/1000 virsrakstu (−50%), kvalitātes pazīmes praktiski
// nemainīgas (0 formāta kļūmju; nemainīti atstāti 3% pret bāzes 2%; latviešu
// diakritika 94% pret 98%). Dārgākais gemini-3.5-flash-lite testā bija SLIKTĀKS —
// pārraksta svešus īpašvārdus latviskās formās, ko uzvedne tieši aizliedz.
// UZMANĪBU: gemini-3.1-flash-lite izslēgšanas datums ir 2027-05-07 — līdz tam
// jāizvēlas pēctecis un jāatkārto A/B.
const REG_GEMINI_MODEL = 'gemini-3.1-flash-lite';

/** Aktīvais modelis: REG_GEMINI_MODEL vides mainīgais to pārraksta (A/B testiem —
 *  produkcijas noklusējums paliek konstante). */
function reg_gemini_model(): string
{
    $env = getenv('REG_GEMINI_MODEL');
    return is_string($env) && $env !== '' ? $env : REG_GEMINI_MODEL;
}

/** Aktīvā atslēga: REG_GEMINI_KEY_MODE=free → bezmaksas līmeņa atslēga (ikdienas
 *  tulkošana cron; $0). Noklusējums = maksas atslēga (MI panelis, vienreizējie darbi). */
function reg_gemini_key(): string
{
    return getenv('REG_GEMINI_KEY_MODE') === 'free' && function_exists('_get_g_key_free')
        ? _get_g_key_free()
        : _get_g_key();
}

/**
 * Zema līmeņa NE-straumējošs generateContent izsaukums.
 * $opts: temperature, maxOutputTokens, thinkingLevel ('low'|'high'; nav = bez
 * thinkingConfig), json (true → responseMimeType application/json), timeout.
 * @return string|null modeļa teksta atbilde vai null kļūdai
 */
function reg_gemini_generate(string $prompt, array $opts = []): ?string
{
    $req = reg_gemini_request($prompt, $opts);
    if ($req === null) return null;
    $ch = reg_gemini_curl($req);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $cerr = curl_error($ch);
    // curl_close() PHP 8+ ir no-op (un 8.5 novecojis) — rīku atbrīvo GC.
    return reg_gemini_response($body, $code, $cerr);
}

/**
 * Paralēli generateContent izsaukumi (curl_multi) — visiem viena $opts kopa.
 * Atdod atbildes ar TĀM PAŠĀM atslēgām kā $prompts; null = attiecīgā izsaukuma
 * kļūda. Lietot TIKAI ar maksas atslēgu: bezmaksas līmeņa RPM kvota paralēlismu
 * tāpat neatļauj, un 429 kļūdas te maksātu dubulti (katrs elements jāatkārto).
 * @param array<int|string, string> $prompts
 * @return array<int|string, ?string>
 */
function reg_gemini_generate_multi(array $prompts, array $opts = [], int $concurrency = 4): array
{
    if (!function_exists('curl_multi_init')) {   // reta būve bez multi — secīgā atkāpe
        $out = [];
        foreach ($prompts as $k => $p) $out[$k] = reg_gemini_generate((string)$p, $opts);
        return $out;
    }
    $out = [];
    $mh = curl_multi_init();
    curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, max(1, $concurrency));
    $handles = [];
    foreach ($prompts as $k => $p) {
        $req = reg_gemini_request((string)$p, $opts);
        if ($req === null) { $out[$k] = null; continue; }
        $ch = reg_gemini_curl($req);
        $handles[] = ['k' => $k, 'ch' => $ch];
        curl_multi_add_handle($mh, $ch);
    }
    if ($handles) {
        do {
            $st = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
        } while ($running && $st === CURLM_OK);
        foreach ($handles as $h) {
            $body = curl_multi_getcontent($h['ch']);
            $code = (int)curl_getinfo($h['ch'], CURLINFO_RESPONSE_CODE);
            $cerr = curl_error($h['ch']);
            $out[$h['k']] = reg_gemini_response($body, $code, $cerr);
            curl_multi_remove_handle($mh, $h['ch']);
        }
    }
    curl_multi_close($mh);
    return $out;
}

/** Uzbūvē generateContent pieprasījumu (URL + ķermenis + taimauts) — kopīgs
 *  vienkāršajam un curl_multi ceļam, lai abi ir garantēti identiski. */
function reg_gemini_request(string $prompt, array $opts = []): ?array
{
    $key = reg_gemini_key();
    if ($key === '' || !function_exists('curl_init')) return null;

    $gen = reg_gemini_gen_config($opts);

    return [
        'url' => 'https://generativelanguage.googleapis.com/v1beta/models/'
               . reg_gemini_model() . ':generateContent?key=' . rawurlencode($key),
        'body' => json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => $gen,
        ], JSON_UNESCAPED_UNICODE),
        'timeout' => (int)($opts['timeout'] ?? 90),
    ];
}

/**
 * generationConfig no klienta opts. Atsevišķi no reg_gemini_request(), jo to pašu
 * konfigurāciju vajag arī Batch API ceļam (konkursi/lib/translate_batch.php), kur
 * pieprasījuma korpuss tiek būvēts pats. Viens avots — citādi abi ceļi ar laiku
 * klusi sāktu sūtīt atšķirīgus parametrus un A/B mērītu divas dažādas lietas.
 */
function reg_gemini_gen_config(array $opts): array
{
    $gen = ['maxOutputTokens' => (int)($opts['maxOutputTokens'] ?? 4096)];
    if (isset($opts['temperature'])) $gen['temperature'] = (float)$opts['temperature'];
    if (isset($opts['thinkingLevel'])) $gen['thinkingConfig'] = ['thinkingLevel' => (string)$opts['thinkingLevel']];
    elseif (isset($opts['thinkingBudget'])) $gen['thinkingConfig'] = ['thinkingBudget' => (int)$opts['thinkingBudget']]; // 0 = domāšana izslēgta
    if (!empty($opts['json'])) $gen['responseMimeType'] = 'application/json';
    return $gen;
}

/** curl rokturis no reg_gemini_request() apraksta. */
function reg_gemini_curl(array $req): CurlHandle
{
    $ch = curl_init($req['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $req['body'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $req['timeout'],
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    return $ch;
}

/**
 * Apstrādā generateContent atbildi: kļūdu stāvoklis + tokenu uzskaite.
 * Google atbild ar precīzu iemeslu (RESOURCE_EXHAUSTED / PERMISSION_DENIED /
 * API_KEY_INVALID ...) — to saglabājam reg_gemini_last_error(), lai izsaucējs
 * var pateikt, KĀPĒC atslēga neatbild.
 */
function reg_gemini_response(string|false|null $body, int $code, string $cerr): ?string
{
    if ($body === false || $body === null || $code >= 400) {
        $status = ''; $msg = '';
        if (is_string($body)) {
            $d = json_decode($body, true);
            $status = (string)($d['error']['status'] ?? '');
            $msg    = (string)($d['error']['message'] ?? '');
        }
        if ($status === '' && $cerr !== '') { $status = 'CURL'; $msg = $cerr; }
        reg_gemini_set_error($code, $status, $msg);
        return null;
    }
    reg_gemini_set_error(0, '', ''); // veiksme — notīra iepriekšējo kļūdu

    $d = json_decode((string)$body, true);
    // Tokenu uzskaite (usageMetadata) — precīzai izmaksu kontrolei. Uzkrāj procesa
    // ietvaros; nolasa ar reg_gemini_usage_total().
    if (isset($d['usageMetadata']) && is_array($d['usageMetadata'])) {
        reg_gemini_usage_add(
            (int)($d['usageMetadata']['promptTokenCount'] ?? 0),
            (int)($d['usageMetadata']['candidatesTokenCount'] ?? 0),
            (int)($d['usageMetadata']['thoughtsTokenCount'] ?? 0)
        );
    }
    $text = $d['candidates'][0]['content']['parts'][0]['text'] ?? null;
    return is_string($text) && $text !== '' ? $text : null;
}

/**
 * Pēdējās API kļūdas glabātuve. Vajadzīga, lai izsaucējs varētu pateikt, KĀPĒC
 * atslēga neatbild — 'kvota beigusies' un 'atslēga nederīga' prasa pilnīgi
 * dažādu rīcību, bet abi izskatījās vienādi (null).
 * @return array{code:int,status:string,message:string}
 */
function &reg_gemini_error_state(): array
{
    static $e = ['code' => 0, 'status' => '', 'message' => ''];
    return $e;
}

function reg_gemini_set_error(int $code, string $status, string $message): void
{
    $e = &reg_gemini_error_state();
    $e = ['code' => $code, 'status' => $status, 'message' => $message];
}

/** @return array{code:int,status:string,message:string} pēdējā kļūda (code 0 = nav) */
function reg_gemini_last_error(): array
{
    return reg_gemini_error_state();
}

/** Īss, žurnālam derīgs pēdējās kļūdas apraksts; '' ja kļūdas nav. */
function reg_gemini_error_brief(): string
{
    $e = reg_gemini_last_error();
    if ($e['code'] === 0 && $e['status'] === '') return '';
    $msg = trim(preg_replace('/\s+/', ' ', $e['message']) ?? '');
    // Google ziņa ir gara un ar saitēm — atstājam sākumu, kur ir būtība.
    if (mb_strlen($msg) > 160) $msg = mb_substr($msg, 0, 160) . '…';
    return 'HTTP ' . $e['code'] . ($e['status'] !== '' ? ' ' . $e['status'] : '') . ($msg !== '' ? ': ' . $msg : '');
}

/** Uzkrāj tokenu skaitus šajā procesā (in, out, thoughts). */
function reg_gemini_usage_add(int $in, int $out, int $thoughts): void
{
    $t = &reg_gemini_usage_total();
    $t['in'] += $in; $t['out'] += $out; $t['thoughts'] += $thoughts; $t['calls']++;
}

/** @return array{in:int,out:int,thoughts:int,calls:int} kopējie tokeni šajā procesā (atsauce). */
function &reg_gemini_usage_total(): array
{
    static $t = ['in' => 0, 'out' => 0, 'thoughts' => 0, 'calls' => 0];
    return $t;
}

/**
 * Pārtulko virsrakstu masīvu uz latviešu valodu (paketē — viens API izsaukums).
 * Ievade: parasts virkņu masīvs (≤ ~50 gab.). Izvade: masīvs tajā pašā secībā
 * un garumā, vai null kļūdai. Tulkošanai domāšana izslēgta (ātri/lēti) un JSON izvade.
 */
function reg_gemini_translate_titles(array $titles): ?array
{
    $titles = array_values(array_map('strval', $titles));
    if ($titles === []) return [];
    $out = reg_gemini_generate(reg_gemini_titles_prompt($titles), reg_gemini_titles_opts());
    return reg_gemini_titles_parse($out, count($titles));
}

/**
 * Vairākas virsrakstu paketes VIENĀ paralēlā piegājienā (curl_multi) — sienas
 * laiks ≈ lēnākā izsaukuma laiks, nevis summa. Atdod rezultātus ar tām pašām
 * atslēgām kā $chunks; null elementā = attiecīgās paketes kļūda (API vai
 * formāta). Lietot tikai ar maksas atslēgu (sk. reg_gemini_generate_multi).
 * @param array<int|string, array<string>> $chunks
 * @return array<int|string, ?array>
 */
function reg_gemini_translate_titles_multi(array $chunks, int $concurrency = 4): array
{
    $prompts = [];
    $counts  = [];
    foreach ($chunks as $k => $titles) {
        $titles = array_values(array_map('strval', $titles));
        $counts[$k]  = count($titles);
        $prompts[$k] = reg_gemini_titles_prompt($titles);
    }
    $res = reg_gemini_generate_multi($prompts, reg_gemini_titles_opts(), $concurrency);
    $out = [];
    foreach ($counts as $k => $n) {
        $out[$k] = $n === 0 ? [] : reg_gemini_titles_parse($res[$k] ?? null, $n);
    }
    return $out;
}

/** Virsrakstu tulkošanas uzvedne — kopīga vienkāršajam un paralēlajam ceļam. */
function reg_gemini_titles_prompt(array $titles): string
{
    return "Tu esi profesionāls tulkotājs. Pārtulko šos publisko iepirkumu paziņojumu "
        . "virsrakstus uz DABISKU latviešu valodu. Iztulko VISU jēgpilno saturu, tostarp "
        . "projektu nosaukumus un aprakstošas svešvalodu frāzes. Nemainītus atstāj TIKAI: "
        . "atsauces un līguma numurus, mērvienības un modeļu kodus, kā arī organizāciju un "
        . "vietu īpašvārdus. Lieto vispāratzītus latviešu saīsinājumus (ANO, ĢIS, HES). "
        . "Ja virsraksts jau ir latviski, atstāj to kā ir.\n"
        . "Atbildi TIKAI ar JSON masīvu ar tieši " . count($titles) . " virknēm tādā pašā secībā.\n\n"
        . reg_gemini_titles_json($titles);
}

/**
 * Virsrakstu saraksts uzvednei. JSON_INVALID_UTF8_SUBSTITUTE, jo bez tā viens
 * virsraksts ar nederīgu baitu (UNDP avots, Windows-1252 domuzīmes) liek json_encode
 * atgriezt false, virknē tas kļūst par tukšumu, un modelis saņem uzvedni "atbildi ar
 * tieši 40 virknēm" BEZ NEVIENA virsraksta — tad tas izdomā 40 tulkojumus, skaita
 * pārbaude iziet, un 40 īsti virsraksti dabū izdomātus tulkojumus (audits 2026-09-04:
 * serverī 9 tādi virsraksti, katrs vilka līdzi savu paketi). Ja tomēr false — izņēmums.
 */
function reg_gemini_titles_json(array $titles): string
{
    $j = json_encode(array_values($titles), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($j === false) throw new RuntimeException('Virsrakstu sarakstu nevar kodēt JSON: ' . json_last_error_msg());
    return $j;
}

/**
 * Normalizēts oriģināls salīdzināšanai: mazie burti, tikai burti un cipari, viena
 * atstarpe. Tā "Bad Ems ? Nassau" un "Bad Ems – Nassau" kļūst vienādi.
 */
function reg_gemini_title_norm(string $t): string
{
    $t = mb_strtolower($t);
    $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t);
    return trim((string)$t);
}

/** Skaitlis salīdzināšanai: arābu cipari vai romiešu skaitlis (II. daļa, rejon III). */
function reg_gemini_title_is_number(string $w): bool
{
    return preg_match('/^\p{N}+$/u', $w) === 1
        || preg_match('/^(?=[ivxlcdm]+$)m{0,3}(cm|cd|d?c{0,3})(xc|xl|l?x{0,3})(ix|iv|v?i{0,3})$/', $w) === 1;
}

/**
 * MARĶIERIS — vārds, kam abās pusēs jāsakrīt burtiski: līguma numurs, izmērs, gads,
 * daļas apzīmējums (sen19047, 100mg, 2500х1250х12, м1400, τμημα3, xviii, los04).
 * Mērījums 2026-09-05 uz 203 433 reāliem virsrakstiem: 126 pāri atšķīrās TIKAI ar
 * šādu marķieri, un 125 no tiem ir dažādi iepirkumi ar dažādiem tulkojumiem — tātad
 * klase praktiski nekad nav labdabīga, un rakstzīmju līdzība tai nav pielaižama.
 */
function reg_gemini_title_is_marker(string $w): bool
{
    return preg_match('/\p{N}/u', $w) === 1 || reg_gemini_title_is_number($w);
}

/**
 * Rakstzīmju (NE baitu) līmeņa Levenšteina attālums, apturēts pie $max. PHP levenshtein()
 * skaita baitus, tāpēc kirilicā un grieķu rakstā tā pati vienas rakstzīmes maiņa dotu
 * divreiz mazāku attālumu nekā latīņu rakstā (recenzija 2026-09-05).
 */
function reg_gemini_word_distance(string $a, string $b, int $max = 1): int
{
    $x = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $y = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $n = count($x); $m = count($y);
    if (abs($n - $m) > $max) return $max + 1;
    $prev = range(0, $m);
    for ($i = 1; $i <= $n; $i++) {
        $cur = [$i]; $best = $i;
        for ($j = 1; $j <= $m; $j++) {
            $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + ($x[$i - 1] === $y[$j - 1] ? 0 : 1));
            if ($cur[$j] < $best) $best = $cur[$j];
        }
        if ($best > $max) return $max + 1;
        $prev = $cur;
    }
    return $prev[$m];
}

/**
 * Vai divi vārdi ir viens un tas pats ar vienas rakstzīmes drukas kļūdu vai locījumu?
 * Priedēklis NAV drukas kļūda: tieši tā atšķiras medicīniskais no NEmedicīniskā,
 * pieslēgtais no NEpieslēgtā un Rohbau no Stahlbau, tāpēc pirmajām divām rakstzīmēm
 * jāsakrīt un garumu starpība nedrīkst pārsniegt vienu.
 */
function reg_gemini_words_same(string $w, string $v): bool
{
    if ($w === $v) return true;
    $lw = mb_strlen($w); $lv = mb_strlen($v);
    if ($lw < 4 || $lv < 4) return false;                            // īsi vārdi nav drukas kļūdas
    if (abs($lw - $lv) > 1) return false;
    if (mb_substr($w, 0, 2) !== mb_substr($v, 0, 2)) return false;   // priedēklis nav drukas kļūda
    return reg_gemini_word_distance($w, $v, 1) <= 1;
}

/**
 * Vai divi oriģināli ir TAS PATS virsraksts ar sīkām atšķirībām — drukas kļūda,
 * bojāta domuzīme, rindas pārnesums, vārdu secība? Tādiem vienāds tulkojums ir
 * pareizs, nevis nobīdes pazīme. Naktī 2026-09-05 12 no 107 paketēm (480 virsraksti)
 * krita tieši uz šādiem pāriem.
 * Noteikumi (kalibrēti uz 203 433 reāliem virsrakstiem): kopīgos vārdus noņem pa
 * pāriem; atlikušo vārdu skaitam abās pusēs jāsakrīt (liekais vārds = cits virsraksts);
 * neviens atlikušais vārds nedrīkst būt marķieris; katram atlikušajam vārdam vajag
 * savu partneri otrā pusē pēc reg_gemini_words_same.
 */
function reg_gemini_titles_alike(string $a, string $b): bool
{
    if ($a === $b) return true;
    $na = reg_gemini_title_norm($a); $nb = reg_gemini_title_norm($b);
    if ($na === '' || $nb === '') return false;
    if ($na === $nb) return true;
    $ta = explode(' ', $na); $tb = explode(' ', $nb);
    $ca = array_count_values($ta); $cb = array_count_values($tb);
    $ra = []; $rb = [];
    foreach ($ca as $w => $n) for ($i = (int)($cb[$w] ?? 0); $i < $n; $i++) $ra[] = (string)$w;
    foreach ($cb as $w => $n) for ($i = (int)($ca[$w] ?? 0); $i < $n; $i++) $rb[] = (string)$w;
    if (!$ra && !$rb) return true;                        // tikai vārdu secība
    if (count($ra) !== count($rb)) return false;          // liekais vārds = cits virsraksts
    foreach ($ra as $w) if (reg_gemini_title_is_marker($w)) return false;
    foreach ($rb as $w) if (reg_gemini_title_is_marker($w)) return false;
    $free = $rb;
    foreach ($ra as $w) {
        $hit = null;
        foreach ($free as $k => $v) if (reg_gemini_words_same($w, $v)) { $hit = $k; break; }
        if ($hit === null) return false;
        unset($free[$hit]);
    }
    return true;
}

/** Marķieri, kas tulkojumā parasti paliek nemainīti (>=3 zīmes un satur ciparu). */
function reg_gemini_title_codes(string $t): array
{
    if (!preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-\/]*/u', $t, $m)) return [];
    $out = [];
    foreach ($m[0] as $w) if (mb_strlen($w) >= 3 && preg_match('/\p{N}/u', $w) === 1) $out[] = $w;
    return $out;
}

/**
 * Vai izvade ir NOBĪDĪTA par vienu (out[j] = in[j-1] tulkojums)? Nobīdē virsraksta
 * kods parādās NĀKAMĀS pozīcijas tulkojumā, nevis savā. Kodi tulkojumā saglabājas
 * 97,2 % gadījumu, tāpēc prasa DIVAS tādas pozīcijas. Šo pārbauda tikai tad, kad
 * sadursme ir pielaista — pielaide citādi atslēgtu nobīdes sargu tieši tur, kur
 * nobīde sākas (recenzija 2026-09-05: 143 no 259 blakus stāvošiem pāriem).
 */
function reg_gemini_titles_shifted(array $in, array $out): bool
{
    $n = count($in); $mis = 0;
    for ($i = 0; $i + 1 < $n; $i++) {
        foreach (reg_gemini_title_codes((string)$in[$i]) as $c) {
            if (stripos((string)$out[$i], $c) === false && stripos((string)$out[$i + 1], $c) !== false) { $mis++; break; }
        }
        if ($mis >= 2) return true;
    }
    return false;
}

/**
 * Sadursmes: izvades pozīcijas, kur vienāds tulkojums dots ATŠĶIRĪGIEM oriģināliem.
 * Skaits var sakrist arī tad, ja modelis divus virsrakstus saplūdinājis vienā un
 * citu sadalījis — tad divi atšķirīgi virsraksti dabū vienādu tulkojumu, un tas ir
 * NOBĪDES signāls: pozīcijas aiz dublikāta var nest kaimiņa tulkojumu. Tāpēc
 * izsaucējs pie jebkuras sadursmes atmet visu paketi (mazākā tā izdosies). Gandrīz
 * identiskus oriģinālus (sk. reg_gemini_titles_alike) ar vienādu tulkojumu pielaiž,
 * bet tikai tad, ja kodu pārbaude neuzrāda nobīdi.
 * Ja skaits nesakrīt — sadursmē ir visas pozīcijas.
 * @return int[] pozīciju indeksi (pēc array_values), augošā secībā
 */
function reg_gemini_titles_conflicts(array $in, array $out): array
{
    $in = array_values($in); $out = array_values($out);
    if (count($in) !== count($out)) return array_keys($in);
    $groups = [];
    foreach ($out as $i => $t) {
        $k = mb_strtolower(trim((string)$t));
        if ($k === '') continue;
        $groups[$k][] = $i;
    }
    $bad = []; $tolerated = false;
    foreach ($groups as $idx) {
        $n = count($idx);
        if ($n < 2) continue;
        $alike = true;
        for ($a = 0; $a < $n && $alike; $a++) {
            for ($b = $a + 1; $b < $n; $b++) {
                if (!reg_gemini_titles_alike((string)$in[$idx[$a]], (string)$in[$idx[$b]])) { $alike = false; break; }
            }
        }
        if ($alike) { $tolerated = true; continue; }
        foreach ($idx as $i) $bad[] = $i;
    }
    if ($tolerated && !$bad && reg_gemini_titles_shifted($in, $out)) return array_keys($in);
    sort($bad);
    return $bad;
}

/** Vai tulkojumu saraksts saskan ar ievadi bez nevienas sadursmes? */
function reg_gemini_titles_consistent(array $in, array $out): bool
{
    return count($in) === count($out) && reg_gemini_titles_conflicts($in, $out) === [];
}

/**
 * Zemākā iespējamā domāšana AKTĪVAJAM modelim.
 *
 * Domāšanas lauks 3.x līnijā NAV vienots (mērīts 2026-09-02): 'thinkingBudget'
 * pieņem 2.5, 3.1 un 3.8, bet gemini-3.5-flash-lite un gemini-3.6-flash to noraida
 * ar HTTP 400 INVALID_ARGUMENT. 'thinkingLevel' der visai 3.x līnijai, tāpēc
 * nezināmiem modeļiem tas ir drošais noklusējums — tas domāšanu pilnībā neizslēdz,
 * tikai nolaiž zemākajā līmenī (mazliet dārgāk, bet nekad nesabrūk).
 *
 * Bez šī REG_GEMINI_MODEL pārslēgšana uz vairumu jaunāko modeļu klusi atdotu
 * NULLI tulkojumu: katrs izsaukums 400, katra pakete null.
 */
function reg_gemini_thinking_min(?string $model = null): array
{
    return preg_match('/^gemini-(2\.5|3\.1|3\.8)/', $model ?? reg_gemini_model())
        ? ['thinkingBudget' => 0]
        : ['thinkingLevel' => 'low'];
}

/** Tulkošanas izsaukuma parametri.
 *  Domāšana IZSLĒGTA (thinkingBudget=0): tulkošanai tā nedod vērā ņemamu kvalitātes
 *  pieaugumu (ar šo uzvedni), bet ar to modelis kļūst ~4× ātrāks, ~3× lētāks un
 *  deterministisks (domāšanas tokeni vairs nevar nogriezt JSON pie maxOutputTokens). */
function reg_gemini_titles_opts(): array
{
    return reg_gemini_thinking_min() + [
        'json'            => true,
        'temperature'     => 0.1,
        'maxOutputTokens' => 8192,
        'timeout'         => 120,
    ];
}

/** Validē paketes atbildi: JSON masīvs ar tieši $n virknēm, citādi null. */
function reg_gemini_titles_parse(?string $out, int $n): ?array
{
    if ($out === null) return null;
    $arr = reg_gemini_json_array($out);
    if ($arr === null || count($arr) !== $n) return null;   // garumam JĀsakrīt
    foreach ($arr as $v) if (!is_string($v)) return null;
    return array_values($arr);
}

/**
 * Robusti izvelk pirmo JSON masīvu no modeļa teksta atbildes. Apstrādā divus reālus
 * gemini-3-flash JSON-režīma defektus: (1) ```json ietvarus un (2) lieku beigu zīmi
 * aiz masīva (piem. dubultu ']'), uz kā parastais json_decode kristu.
 */
function reg_gemini_json_array(string $out): ?array
{
    $s = trim($out);
    if (str_starts_with($s, '```')) {                     // ```json … ``` vai ``` … ```
        $s = trim((string)preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $s));
    }
    $arr = json_decode($s, true);
    if (is_array($arr)) return array_is_list($arr) ? $arr : null;   // objekts ar N atslēgām ietu cauri ar nobīdi

    // Sabalansēta izvilkšana: no pirmās '[' līdz tās PĀRĪ esošajai ']' (respektē
    // virknes un aizbēgumus), ignorējot jebko pēc tam — tā apstrādā lieko ']'.
    $start = strpos($s, '[');
    if ($start === false) return null;
    $depth = 0; $inStr = false; $esc = false; $n = strlen($s);
    for ($i = $start; $i < $n; $i++) {
        $c = $s[$i];
        if ($inStr) {
            if ($esc)            $esc = false;
            elseif ($c === '\\') $esc = true;
            elseif ($c === '"')  $inStr = false;
            continue;
        }
        if ($c === '"')      $inStr = true;
        elseif ($c === '[')  $depth++;
        elseif ($c === ']') {
            if (--$depth === 0) {
                $arr = json_decode(substr($s, $start, $i - $start + 1), true);
                return is_array($arr) && array_is_list($arr) ? $arr : null;
            }
        }
    }
    return null;
}
