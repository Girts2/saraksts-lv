<?php
/**
 * registrs/mi/gemini_client.php — CENTRALIZĒTAIS Gemini API klients.
 *
 * VIENĪGĀ vieta (ārpus master_top.php straumēšanas), kur dzīvo Gemini API kods:
 * citas sadaļas (Konkursi u.c.) šo failu require un sauc funkcijas — mainot
 * modeli/atslēgu/API šeit, izmaiņas propagējas visur.
 *
 * Atslēga: registrs/mi/key.php (_get_g_key). Modelis: tas pats, ko lieto MI
 * panelis (gemini-3-flash-preview).
 */
declare(strict_types=1);

require_once __DIR__ . '/key.php';   // definē _get_g_key()

const REG_GEMINI_MODEL = 'gemini-3-flash-preview';

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

    $gen = ['maxOutputTokens' => (int)($opts['maxOutputTokens'] ?? 4096)];
    if (isset($opts['temperature'])) $gen['temperature'] = (float)$opts['temperature'];
    if (isset($opts['thinkingLevel'])) $gen['thinkingConfig'] = ['thinkingLevel' => (string)$opts['thinkingLevel']];
    elseif (isset($opts['thinkingBudget'])) $gen['thinkingConfig'] = ['thinkingBudget' => (int)$opts['thinkingBudget']]; // 0 = domāšana izslēgta
    if (!empty($opts['json'])) $gen['responseMimeType'] = 'application/json';

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
        . json_encode($titles, JSON_UNESCAPED_UNICODE);
}

/** Tulkošanas izsaukuma parametri.
 *  Domāšana IZSLĒGTA (thinkingBudget=0): tulkošanai tā nedod vērā ņemamu kvalitātes
 *  pieaugumu (ar šo uzvedni), bet ar to modelis kļūst ~4× ātrāks, ~3× lētāks un
 *  deterministisks (domāšanas tokeni vairs nevar nogriezt JSON pie maxOutputTokens). */
function reg_gemini_titles_opts(): array
{
    return [
        'thinkingBudget'  => 0,
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
    if (is_array($arr)) return $arr;

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
                return is_array($arr) ? $arr : null;
            }
        }
    }
    return null;
}
