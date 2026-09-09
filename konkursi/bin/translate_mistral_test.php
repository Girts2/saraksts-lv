<?php
/**
 * Test: Mistral tulkojumu izmēģinājumu stends (LOKĀLS, serverī neizvieto).
 *
 * Kāpēc: 2026-09-03 A/B rādīja, ka Mistral Small "klusi izlaiž rindas" (5-30 % pakešu),
 * un tāpēc to noraidīja. Tas tests tomēr sauca API TIKAI vienā veidā — Gemini uzvedne,
 * bez response_format, temperature 0,1, max_tokens 8192 — un NEPĀRBAUDĪJA finish_reason,
 * tāpēc nogriezta atbilde un izlaistas rindas izskatījās vienādi. Šis stends izmēģina
 * vairākus pieprasījuma veidus uz tiem pašiem datiem un mēra katru atsevišķi.
 *
 * SVARĪGI (izpēte 2026-09-05): Mistral OFICIĀLAJĀ atbalstīto valodu sarakstā
 * (docs.mistral.ai/resources/languages) latviešu valodas NAV — Eiropā uzskaitītas 19 valodas,
 * un no Baltijas nav nevienas. Nav arī bulgāru un maķedoniešu (mūsu avotu valodas).
 * Tāpēc kvalitāte jāmēra pašiem, ne jāpieņem. Tāpat: JSON režīms (json_object) oficiāli
 * NEGARANTĒ shēmas ievērošanu, un vai json_schema piespiež minItems/maxItems, Mistral
 * nekur nedokumentē — tieši tāpēc šis stends izmēģina abus un salīdzina.
 *
 * Lietošana:
 *   MISTRAL_KEY_FILE=/ceļš/uz/atslēgu php konkursi/bin/translate_mistral_test.php --visi
 *   ... --n=200 --gabals=40 --modelis=mistral-small-latest --veids=shema
 *   ... --makets            (bez atslēgas: pārbauda pašu stendu ar viltus atbildēm)
 *   ... --izvade=/ceļš.json (neapstrādātās atbildes analīzei)
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../../registrs/mi/gemini_client.php';

const MT_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';
/**
 * USD par 1M tokenu [ievade, izvade]. Avots: mistral.ai/pricing/api un modeļu kartes
 * docs.mistral.ai/models/*, pārbaudīts 2026-09-05 (katrs skaitlis divreiz, divi neatkarīgi lasījumi).
 * UZMANĪBU: modeļu klāsts kopš 2026-09-03 ir mainījies — open-mistral-nemo IZŅEMTS 2026-07-31,
 * vecie ministral-*-2410 izņemti 2025-12-31 (pieprasījums uz izņemtu ID dod HTTP 404).
 * Aizstājvārdi -latest tagad rāda uz Ministral 3 saimi (2512).
 */
const MT_PRICES = [
    'mistral-small-latest'  => [0.15, 0.60],   // Small 4 (v26.03), 256k
    'mistral-medium-latest' => [1.50, 7.50],   // Medium 3.5 (v26.04)
    'mistral-large-latest'  => [0.50, 1.50],   // Large 3 (v25.12)
    'ministral-14b-latest'  => [0.20, 0.20],   // Ministral 3 14B (v25.12)
    'ministral-8b-latest'   => [0.15, 0.15],   // Ministral 3 8B
    'ministral-3b-latest'   => [0.10, 0.10],   // Ministral 3 3B
];
const MT_GEMINI_PRICE = [0.25, 1.50];      // gemini-3.1-flash-lite, pilnā cena
const MT_BATCH_MULT   = 0.5;             // apstiprināts: mistral.ai/pricing/api pārslēgs "Batch (-50%)"

// ── Argumenti ───────────────────────────────────────────────────────────────
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z_]+)(?:=(.*))?$/', $a, $m)) $args[$m[1]] = $m[2] ?? '1';
}
$N       = (int)($args['n'] ?? 120);
$CHUNK   = (int)($args['gabals'] ?? 40);
$MODEL   = (string)($args['modelis'] ?? 'mistral-small-latest');
$ONLY    = (string)($args['veids'] ?? '');
$MOCK    = isset($args['makets']);
$ALL     = isset($args['visi']);
$OUT     = (string)($args['izvade'] ?? '');
$CONC    = (int)($args['paralēli'] ?? $args['paraleli'] ?? 4);

// ── Atslēga ─────────────────────────────────────────────────────────────────
function mt_key(): string
{
    static $k = null;
    if ($k !== null) return $k;
    $k = trim((string)getenv('MISTRAL_API_KEY'));
    if ($k === '' && ($f = trim((string)getenv('MISTRAL_KEY_FILE'))) !== '' && is_file($f)) $k = trim((string)file_get_contents($f));
    if ($k === '') { fwrite(STDERR, "✗ Nav atslēgas. Uzstādi MISTRAL_API_KEY vai MISTRAL_KEY_FILE (vai palaid ar --makets).\n"); exit(2); }
    return $k;
}

// ── Paraugs ─────────────────────────────────────────────────────────────────
/** Kāds raksts? Tokenizatora efektivitāte un modeļa kļūdas pa rakstiem atšķiras. */
function mt_script(string $t): string
{
    if (preg_match('/\p{Cyrillic}/u', $t)) return 'kirilica';
    if (preg_match('/\p{Greek}/u', $t))    return 'grieķu';
    if (preg_match('/[\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}]/u', $t)) return 'ĶJ';
    return 'latīņu';
}

/**
 * Deterministisks paraugs no īstiem virsrakstiem, kam JAU ir Gemini tulkojums
 * (tas ir atskaites punkts kvalitātei). Latviskos avotus izlaiž — tiem title_lv
 * ir kopija, ne tulkojums. Stratificē pēc raksta un garuma, lai redzētu, KUR lūst.
 */
function mt_sample(PDO $pdo, int $n): array
{
    $rows = $pdo->query(
        "SELECT title, title_lv, buyer_country, LENGTH(title) len
           FROM notices
          WHERE title_lv IS NOT NULL AND title_lv != '' AND title IS NOT NULL AND title != ''
            AND title != title_lv
          GROUP BY title
          ORDER BY (rowid * 2654435761) % 1000003
          LIMIT " . (int)($n * 8)
    )->fetchAll(PDO::FETCH_ASSOC);
    $bins = [];
    foreach ($rows as $r) {
        $s = mt_script($r['title']);
        $g = $r['len'] < 60 ? 'īss' : ($r['len'] < 160 ? 'vidējs' : 'garš');
        $bins["$s/$g"][] = $r;
    }
    ksort($bins);
    $out = []; $i = 0;
    while (count($out) < $n && $bins) {            // apļveida atlase pa grupām
        foreach ($bins as $k => &$b) {
            if (!$b) { unset($bins[$k]); continue; }
            $out[] = array_shift($b);
            if (count($out) >= $n) break 2;
        }
        unset($b);
        if (++$i > 10000) break;
    }
    return $out;
}

// ── Uzvednes veidi ──────────────────────────────────────────────────────────
/**
 * Katrs veids = cits veids, kā PRASĪT to pašu. Atgriež [uzvedne, pieprasījuma papildu lauki,
 * parsētājs]. Parsētājs atgriež masīvu ar tieši count($titles) elementiem vai null.
 */
function mt_strategy(string $name, array $titles): array
{
    $n = count($titles);
    $rules = "Tu esi profesionāls tulkotājs. Pārtulko šos publisko iepirkumu paziņojumu "
        . "virsrakstus uz DABISKU latviešu valodu. Iztulko VISU jēgpilno saturu, tostarp "
        . "projektu nosaukumus un aprakstošas svešvalodu frāzes. Nemainītus atstāj TIKAI: "
        . "atsauces un līguma numurus, mērvienības un modeļu kodus, kā arī organizāciju un "
        . "vietu īpašvārdus. Lieto vispāratzītus latviešu saīsinājumus (ANO, ĢIS, HES). "
        . "Ja virsraksts jau ir latviski, atstāj to kā ir.";
    $json = reg_gemini_titles_json($titles);
    // Parsētājs atgriež ['lv' => pilns masīvs vai null, 'got' => cik elementus modelis DEVA,
    // 'trūkst' => kuru pozīciju nav (tikai formātiem, kur to var zināt).
    $listArr = function ($x) use ($n) {
        if (!is_array($x) || !array_is_list($x)) return ['lv' => null, 'got' => -1, 'trūkst' => []];
        $v = array_values($x); $got = count($v);
        $ok = $got === $n && count(array_filter($v, 'is_string')) === $n;
        return ['lv' => $ok ? $v : null, 'got' => $got, 'trūkst' => []];
    };

    switch ($name) {
        // 1. TIEŠI tā, kā 2026-09-03 A/B tests sauca Mistral (bāzes līnija).
        case 'bāze':
            return [
                $rules . "\nAtbildi TIKAI ar JSON masīvu ar tieši $n virknēm tādā pašā secībā.\n\n" . $json,
                ['temperature' => 0.1, 'max_tokens' => 8192],
                fn(string $raw) => $listArr(reg_gemini_json_array($raw)),
            ];
        // 2. Tas pats, bet temperature 0 un bez mākslīga izvades griestu ierobežojuma.
        case 'bāze_t0':
            return [
                $rules . "\nAtbildi TIKAI ar JSON masīvu ar tieši $n virknēm tādā pašā secībā.\n\n" . $json,
                ['temperature' => 0],
                fn(string $raw) => $listArr(reg_gemini_json_array($raw)),
            ];
        // 3. Mistral JSON režīms (prasa vārdu "json" uzvednē un OBJEKTU, ne masīvu).
        case 'json_obj':
            return [
                $rules . "\nAtbildi ar json objektu formā {\"tulkojumi\": [...]}, kur masīvā ir "
                    . "tieši $n virknes tādā pašā secībā kā ievadē.\n\n" . $json,
                ['temperature' => 0, 'response_format' => ['type' => 'json_object']],
                function (string $raw) use ($listArr) { $j = json_decode($raw, true); return $listArr($j['tulkojumi'] ?? null); },
            ];
        // 4. Strukturēta izvade ar shēmu: masīva garums ir LĪGUMS, ne lūgums.
        case 'shema':
            return [
                $rules . "\nPārtulko json masīvu ar tieši $n virknēm tādā pašā secībā.\n\n" . $json,
                ['temperature' => 0, 'response_format' => ['type' => 'json_schema', 'json_schema' => [
                    'name'   => 'tulkojumi',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'properties' => ['tulkojumi' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => $n, 'maxItems' => $n]],
                        'required' => ['tulkojumi'],
                    ],
                ]]],
                function (string $raw) use ($listArr) { $j = json_decode($raw, true); return $listArr($j['tulkojumi'] ?? null); },
            ];
        // 5. Numurētas atslēgas: izlaista rinda kļūst REDZAMA, ne klusa nobīde.
        case 'numuri':
            $num = [];
            foreach (array_values($titles) as $i => $t) $num[(string)($i + 1)] = $t;
            return [
                $rules . "\nAtbildi ar json objektu, kur katrai atslēgai no \"1\" līdz \"$n\" ir "
                    . "tulkojums. Neizlaid nevienu atslēgu.\n\n" . json_encode($num, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                ['temperature' => 0, 'response_format' => ['type' => 'json_object']],
                function (string $raw) use ($n) {
                    $j = json_decode($raw, true);
                    if (!is_array($j)) return ['lv' => null, 'got' => -1, 'trūkst' => []];
                    $o = []; $miss = [];
                    for ($i = 1; $i <= $n; $i++) {
                        $v = $j[(string)$i] ?? $j[$i] ?? null;
                        if (is_string($v)) $o[$i - 1] = $v; else { $o[$i - 1] = null; $miss[] = $i; }
                    }
                    return ['lv' => $miss ? null : $o, 'got' => $n - count($miss), 'trūkst' => $miss];
                },
            ];
        // 6. Bez JSON vispār: numurētas rindas. Nav pēdiņu un aizbēgumu, ko sabojāt.
        case 'rindas':
            $lines = [];
            foreach (array_values($titles) as $i => $t) $lines[] = ($i + 1) . '|' . str_replace(["\r", "\n"], ' ', $t);
            return [
                $rules . "\nIevadē katra rinda ir NUMURS|VIRSRAKSTS. Atbildi ar tieši $n rindām "
                    . "formā NUMURS|TULKOJUMS. Bez ievada, bez paskaidrojumiem, bez tukšām rindām.\n\n"
                    . implode("\n", $lines),
                ['temperature' => 0],
                function (string $raw) use ($n) {
                    $o = array_fill(0, $n, null);
                    foreach (preg_split('/\R/u', trim($raw)) as $ln) {
                        if (!preg_match('/^\s*(\d+)\s*\|\s*(.+)$/u', $ln, $m)) continue;
                        $i = (int)$m[1] - 1;
                        if ($i >= 0 && $i < $n && $o[$i] === null) $o[$i] = trim($m[2]);
                    }
                    $miss = [];
                    foreach ($o as $i => $v) if ($v === null) $miss[] = $i + 1;
                    return ['lv' => $miss ? null : $o, 'got' => $n - count($miss), 'trūkst' => $miss];
                },
            ];
        // 7. Pa vienam virsrakstam: kvalitātes un pilnīguma griesti, cenas sods.
        case 'pa_vienam':
            return [
                $rules . "\nAtbildi TIKAI ar tulkojumu, bez pēdiņām un paskaidrojumiem.\n\n" . reset($titles),
                ['temperature' => 0],
                fn(string $raw) => ['lv' => trim($raw) === '' ? null : [trim($raw)], 'got' => trim($raw) === '' ? 0 : 1, 'trūkst' => []],
            ];
    }
    throw new RuntimeException("Nezināms veids: $name");
}

const MT_STRATEGIES = ['bāze', 'bāze_t0', 'json_obj', 'shema', 'numuri', 'rindas', 'pa_vienam'];

// ── API ─────────────────────────────────────────────────────────────────────
/**
 * Paralēli pieprasījumi. Atgriež katram: kods, saturs, finish_reason (TO vecais tests
 * nepārbaudīja!), lietojums, kļūda, ilgums.
 */
function mt_call(array $reqs, string $model, int $conc, bool $mock): array
{
    if ($mock) return mt_mock($reqs);
    $key = mt_key(); $out = [];
    foreach (array_chunk($reqs, max(1, $conc), true) as $wave) {
        $mh = curl_multi_init(); $hs = [];
        foreach ($wave as $i => $r) {
            $body = array_merge(['model' => $model, 'messages' => [['role' => 'user', 'content' => $r['prompt']]]], $r['opts']);
            $ch = curl_init(MT_ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 300,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $key],
                CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ]);
            curl_multi_add_handle($mh, $ch); $hs[$i] = [$ch, microtime(true)];
        }
        do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);
        foreach ($hs as $i => [$ch, $t0]) {
            $raw = (string)curl_multi_getcontent($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            $j = json_decode($raw, true);
            $out[$i] = [
                'code'    => $code,
                'cerr'    => $cerr,
                'content' => (string)($j['choices'][0]['message']['content'] ?? ''),
                'finish'  => (string)($j['choices'][0]['finish_reason'] ?? ''),
                'in'      => (int)($j['usage']['prompt_tokens'] ?? 0),
                'out'     => (int)($j['usage']['completion_tokens'] ?? 0),
                'err'     => $code === 200 ? '' : mb_substr(preg_replace('/\s+/', ' ', $raw), 0, 200),
                'ms'      => (int)round((microtime(true) - $t0) * 1000),
            ];
        }
        curl_multi_close($mh);
    }
    return $out;
}

/** Viltus atbildes: pārbauda pašu stendu (skaitīšanu, parsēšanu, ziņojumu) bez atslēgas. */
function mt_mock(array $reqs): array
{
    $out = [];
    foreach ($reqs as $i => $r) {
        $n = $r['n'];
        $vals = [];
        for ($k = 0; $k < $n; $k++) $vals[] = "Tulkojums $k";
        $mode = $i % 4;                                   // 0 labs, 1 izlaista rinda, 2 nogriezts, 3 kļūda
        if ($mode === 1) array_pop($vals);
        $body = match ($r['veids'] ?? '') {
            'rindas'             => implode("\n", array_map(fn($v, $k) => ($k + 1) . '|' . $v, $vals, array_keys($vals))),
            'json_obj', 'shema'  => json_encode(['tulkojumi' => $vals], JSON_UNESCAPED_UNICODE),
            'numuri'             => json_encode(array_combine(array_map(fn($k) => (string)($k + 1), array_keys($vals)), $vals), JSON_UNESCAPED_UNICODE),
            'pa_vienam'          => $vals[0] ?? '',
            default              => json_encode($vals, JSON_UNESCAPED_UNICODE),
        };
        if ($mode === 2) $body = substr($body, 0, (int)(strlen($body) * 0.7));
        $out[$i] = [
            'code' => $mode === 3 ? 429 : 200, 'cerr' => '',
            'content' => $mode === 3 ? '' : $body,
            'finish' => $mode === 2 ? 'length' : 'stop',
            'in' => 1000, 'out' => 500,
            'err' => $mode === 3 ? '{"message":"rate limit"}' : '', 'ms' => 100,
        ];
    }
    return $out;
}

// ── Viens mēģinājums ────────────────────────────────────────────────────────
function mt_run(array $sample, string $strategy, string $model, int $chunk, int $conc, bool $mock, array &$raws): array
{
    $chunks = $strategy === 'pa_vienam' ? array_chunk($sample, 1) : array_chunk($sample, $chunk);
    $reqs = [];
    foreach ($chunks as $ci => $c) {
        [$prompt, $opts, $parse] = mt_strategy($strategy, array_column($c, 'title'));
        $reqs[$ci] = ['prompt' => $prompt, 'opts' => $opts, 'parse' => $parse, 'n' => count($c), 'veids' => $strategy];
    }
    $t0 = microtime(true);
    $res = mt_call($reqs, $model, $conc, $mock);
    $wall = microtime(true) - $t0;

    $st = ['veids' => $strategy, 'modelis' => $model, 'gabals' => $strategy === 'pa_vienam' ? 1 : $chunk,
           'paketes' => count($chunks), 'virsraksti' => count($sample), 'ok_paketes' => 0, 'tulkoti' => 0,
           'http_kļūdas' => 0, 'nogriezti' => 0, 'nepareizs_skaits' => 0, 'neparsējas' => 0, 'iztrūkstošie' => 0,
           'in' => 0, 'out' => 0, 'sek' => round($wall, 1), 'sakrīt' => 0, 'tuvu' => 0, 'atšķiras' => 0,
           'kļūdu_paraugs' => [], 'atšķirību_paraugs' => []];

    foreach ($chunks as $ci => $c) {
        $r = $res[$ci] ?? null;
        $raws[] = ['veids' => $strategy, 'modelis' => $model, 'pakete' => $ci, 'n' => count($c),
                   'code' => $r['code'] ?? 0, 'finish' => $r['finish'] ?? '', 'saturs' => mb_substr((string)($r['content'] ?? ''), 0, 4000)];
        if (!$r || $r['code'] !== 200) {
            $st['http_kļūdas']++;
            if (count($st['kļūdu_paraugs']) < 3) $st['kļūdu_paraugs'][] = 'HTTP ' . ($r['code'] ?? 0) . ' ' . mb_substr((string)($r['err'] ?? ''), 0, 120);
            continue;
        }
        $st['in'] += $r['in']; $st['out'] += $r['out'];
        if ($r['finish'] === 'length') {
            $st['nogriezti']++;
            if (count($st['kļūdu_paraugs']) < 3) $st['kļūdu_paraugs'][] = "finish_reason=length (izvade {$r['out']} tokeni)";
            continue;
        }
        $p  = ($reqs[$ci]['parse'])($r['content']);
        $lv = $p['lv'] ?? null;
        if ($lv === null) {
            $got = (int)($p['got'] ?? -1);
            if ($got < 0) {
                $st['neparsējas']++;
                if (count($st['kļūdu_paraugs']) < 3) $st['kļūdu_paraugs'][] = 'neparsējas: ' . mb_substr(preg_replace('/\s+/', ' ', $r['content']), 0, 110);
            } else {
                $st['nepareizs_skaits']++;
                $st['iztrūkstošie'] += max(0, count($c) - $got);
                $miss = $p['trūkst'] ?? [];
                if (count($st['kļūdu_paraugs']) < 3) $st['kļūdu_paraugs'][] = 'atgriezti ' . $got . ' no ' . count($c)
                    . ($miss ? ' (trūkst pozīcijas ' . implode(',', array_slice($miss, 0, 8)) . ')' : ' (kuras — nav zināms, masīvs bez numuriem)');
            }
            continue;
        }
        $st['ok_paketes']++; $st['tulkoti'] += count($lv);
        foreach (array_values($c) as $pi2 => $row2) $GLOBALS['MT_PAIRS'][] = [
            'veids' => $strategy, 'modelis' => $model,
            'oriģināls' => $row2['title'], 'gemini' => (string)$row2['title_lv'], 'mistral' => $lv[$pi2],
            'valsts' => $row2['buyer_country'] ?? '', 'raksts' => mt_script($row2['title'])];
        foreach (array_values($c) as $i => $row) {
            $a = mb_strtolower(trim($lv[$i])); $b = mb_strtolower(trim((string)$row['title_lv']));
            if ($a === $b) $st['sakrīt']++;
            elseif (reg_gemini_titles_alike($lv[$i], (string)$row['title_lv'])) $st['tuvu']++;
            else {
                $st['atšķiras']++;
                if (count($st['atšķirību_paraugs']) < 6)
                    $st['atšķirību_paraugs'][] = ['oriģināls' => mb_substr($row['title'], 0, 60), 'gemini' => mb_substr((string)$row['title_lv'], 0, 60), 'mistral' => mb_substr($lv[$i], 0, 60)];
            }
        }
    }
    // Ja katrs virsraksts tiek izlaists neatkarīgi ar varbūtību p, tad P(pakete ok) = (1-p)^n.
    // Tas padara dažādu paketes izmēru rezultātus SALĪDZINĀMUS — un tieši šī likme
    // 2026-09-03 mērījumā bija ~1 % (40→30 %, 20→20 %, 10→10 %, 5→5 % kritušu pakešu).
    $nn = $st['gabals'];
    $formOk = $st['paketes'] - $st['http_kļūdas'];
    $st['p_izlaiž'] = ($formOk > 0 && $nn > 0)
        ? round(100 * (1 - pow($st['ok_paketes'] / $formOk, 1 / $nn)), 3) : null;
    [$pin, $pout] = MT_PRICES[$model] ?? [0, 0];
    $st['usd'] = round($st['in'] / 1e6 * $pin + $st['out'] / 1e6 * $pout, 5);
    $st['eur_uz_1000'] = $st['tulkoti'] ? round($st['usd'] * KONKURSI_USD_TO_EUR / $st['tulkoti'] * 1000, 4) : null;
    return $st;
}

// ── Palaišana ───────────────────────────────────────────────────────────────
$GLOBALS['MT_PAIRS'] = [];
$pdo = konkursi_db();
$sample = mt_sample($pdo, $N);
if (!$sample) { fwrite(STDERR, "✗ Nav parauga datu.\n"); exit(2); }
$byScript = [];
foreach ($sample as $r) $byScript[mt_script($r['title'])] = ($byScript[mt_script($r['title'])] ?? 0) + 1;
printf("Paraugs: %d virsraksti (%s)%s\n", count($sample),
    implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($byScript), $byScript)), $MOCK ? '  [MAKETS]' : '');
printf("Modelis: %s, gabals %d, paralēli %d\n\n", $MODEL, $CHUNK, $CONC);

$plan = $ALL ? MT_STRATEGIES : ($ONLY !== '' ? explode(',', $ONLY) : ['bāze', 'shema', 'rindas']);
// --gabali=40,20,10,5 — tas pats veids pie dažādiem paketes izmēriem (p_izlaiž stabilitātei)
$sizes = isset($args['gabali']) ? array_map('intval', explode(',', (string)$args['gabali'])) : [$CHUNK];
$raws = []; $rows = [];
$combos = [];
foreach ($plan as $s) foreach ($sizes as $z) $combos[] = [trim($s), $z];
foreach ($combos as [$s, $z]) {
    $rows[] = $st = mt_run($sample, $s, $MODEL, $z, $CONC, $MOCK, $raws);
    printf("%-10s gab.%3d | paketes %3d | ok %3d | HTTP kļ. %2d | nogriezti %2d | nepareizs skaits %2d | neparsējas %2d | %5.1f s | $%.5f\n",
        $st['veids'], $st['gabals'], $st['paketes'], $st['ok_paketes'], $st['http_kļūdas'], $st['nogriezti'], $st['nepareizs_skaits'], $st['neparsējas'], $st['sek'], $st['usd']);
    foreach ($st['kļūdu_paraugs'] as $e) printf("           · %s\n", $e);
}

echo "\n=== KOPSAVILKUMS ===\n";
printf("%-10s %4s %9s %8s %8s %9s %9s %9s %11s\n", 'veids', 'gab.', 'pilnīgums', 'p_izlaiž', 'sakrīt', 'tuvu', 'atšķiras', 'USD', 'EUR/1000');
foreach ($rows as $r) {
    printf("%-10s %4d %8.1f%% %7s %8d %9d %9d %9.5f %11s\n", $r['veids'], $r['gabals'],
        $r['virsraksti'] ? 100 * $r['tulkoti'] / $r['virsraksti'] : 0,
        $r['p_izlaiž'] === null ? '-' : $r['p_izlaiž'] . '%',
        $r['sakrīt'], $r['tuvu'], $r['atšķiras'], $r['usd'], $r['eur_uz_1000'] ?? '-');
}
echo "p_izlaiž = novērtētā varbūtība, ka modelis izlaiž VIENU virsrakstu. Ja tā ir stabila starp\n"
   . "paketes izmēriem, kļūme ir modeļa uzvedība, nevis garuma vai limita problēma.\n";
$gEur = (1500 / 1e6 * MT_GEMINI_PRICE[0] + 1500 / 1e6 * MT_GEMINI_PRICE[1]) * KONKURSI_USD_TO_EUR;
printf("\nAtskaite: Gemini 3.1-flash-lite Batch maksā ap %.4f EUR par 1000 virsrakstiem (mērīts serverī: 0.0349).\n", 0.0349);
foreach ($rows as $r) {
    if ($r['eur_uz_1000'] === null) continue;
    $b = $r['eur_uz_1000'] * MT_BATCH_MULT;
    printf("  %-10s pilnā cena %.4f EUR/1000, ar Batch atlaidi %.4f -> %s\n", $r['veids'], $r['eur_uz_1000'], $b,
        $b < 0.0349 ? sprintf('par %.0f%% LĒTĀK', 100 * (1 - $b / 0.0349)) : sprintf('par %.0f%% dārgāk', 100 * ($b / 0.0349 - 1)));
}
if (isset($args['pari']) && $args['pari'] !== '1') {
    file_put_contents($args['pari'], json_encode($GLOBALS['MT_PAIRS'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\nSalīdzināšanas pāri: {$args['pari']} (" . count($GLOBALS['MT_PAIRS']) . " trijnieki)\n";
}
if ($OUT !== '') { file_put_contents($OUT, json_encode(['kopsavilkums' => $rows, 'atbildes' => $raws], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); echo "\nNeapstrādātās atbildes: $OUT\n"; }
foreach ($rows as $r) {
    if (!$r['atšķirību_paraugs']) continue;
    echo "\n--- {$r['veids']}: kur atšķiras no Gemini ---\n";
    foreach ($r['atšķirību_paraugs'] as $d) printf("  %s\n    Gemini : %s\n    Mistral: %s\n", $d['oriģināls'], $d['gemini'], $d['mistral']);
}
