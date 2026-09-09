<?php
/**
 * konkursi/bin/translate_ab.php — virsrakstu tulkošanas modeļu A/B salīdzinājums.
 *
 * KĀPĒC: virsrakstu tulkošana ir lielākā MI izmaksu pozīcija (2026-08: ~€28 no €45),
 * un 85% no tās ir IZVADES tokeni. Lētāki flash-lite modeļi izvadi samaksā 2–7,5×
 * lētāk, bet latviešu valoda ir zemresursu valoda — kvalitāte jāpārbauda, ne jācer.
 *
 * KĀ: paņem N jau iztulkotus virsrakstus (esošais tulkojums = bāzes līnija), liek
 * tos pārtulkot kandidātmodeļiem pa TO PAŠU koda ceļu, ko lieto produkcija
 * (registrs/mi/gemini_client.php), un saliek blakus HTML atskaitē ar tokenu
 * uzskaiti, izmaksām un automātiskajām kvalitātes pazīmēm.
 *
 * Modeli pārslēdz REG_GEMINI_MODEL vides mainīgais — koda izmaiņas nav vajadzīgas.
 *
 * Lietošana:
 *   php konkursi/bin/translate_ab.php                      # sausā: paraugs + izmaksu aplēse
 *   php konkursi/bin/translate_ab.php --apply              # palaiž (MAKSĀ)
 *   php konkursi/bin/translate_ab.php --apply --n=300 --models=gemini-2.5-flash-lite
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../../registrs/mi/gemini_client.php';

set_time_limit(0);

// ── Cenas $/1M tokenu (2026-09-02, ai.google.dev/gemini-api/docs/pricing) ────
// UZMANĪBU: 3.x ievadcenas ir akcija līdz 31.12.2026 — no 01.01.2027 dubultojas.
const AB_PRICES = [
    'gemini-3-flash-preview' => [0.50, 3.00],   // agrākais produkcijas modelis (līdz 2026-09-02)
    'gemini-2.5-flash-lite'  => [0.10, 0.40],
    'gemini-3.1-flash-lite'  => [0.25, 1.50],   // pašreizējais produkcijas modelis
    'gemini-3.5-flash-lite'  => [0.30, 2.50],
    'gemini-3.6-flash'       => [0.75, 3.75],
    'gemini-3.7-flash'       => [0.75, 3.75],
    'gemini-3.8-flash'       => [0.75, 3.75],
    // Mistral (mistral.ai/pricing/api, 2026-09-03). Cita firma — cits API ceļš,
    // sk. ab_is_mistral()/ab_mistral_multi(). Batch atlaide arī te ir −50 %.
    'ministral-3b-latest'    => [0.10, 0.10],
    'ministral-8b-latest'    => [0.15, 0.15],
    'ministral-14b-latest'   => [0.20, 0.20],
    'mistral-small-latest'   => [0.15, 0.60],
    'mistral-large-latest'   => [0.50, 1.50],
    'mistral-medium-latest'  => [1.50, 7.50],
];
const AB_USD_TO_EUR = 0.95;          // tas pats konservatīvais kurss, ko lieto config.php
const AB_LV_SOURCES = ['IUB', 'MODTI', 'RSTI', 'ASTI', 'LDZ'];   // jau latviski — netulko

// ── Argumenti ───────────────────────────────────────────────────────────────
$apply  = in_array('--apply', $argv, true);
$n      = 200;
$seed   = 42;
$out    = __DIR__ . '/ab_tulkojumi.html';
$models = ['gemini-3.1-flash-lite', 'gemini-3.5-flash-lite', 'gemini-3.6-flash'];
foreach ($argv as $a) {
    if (preg_match('/^--n=(\d+)$/', $a, $m))      $n = max(10, (int)$m[1]);
    if (preg_match('/^--seed=(\d+)$/', $a, $m))   $seed = (int)$m[1];
    if (preg_match('/^--out=(.+)$/', $a, $m))     $out = $m[1];
    if (preg_match('/^--models=(.+)$/', $a, $m))  $models = array_filter(array_map('trim', explode(',', $m[1])));
}

/**
 * Tulkošanas parametri konkrētam modelim.
 *
 * Domāšanas lauks 3.x līnijā NAV vienots: 'thinkingBudget' pieņem 2.5, 3.1 un 3.8,
 * bet 3.5-flash-lite un 3.6-flash to noraida ar HTTP 400 INVALID_ARGUMENT (mērīts
 * 2026-09-02). Tiem der 'thinkingLevel'. Produkcijas reg_gemini_titles_opts() sūta
 * TIKAI 'thinkingBudget' — tāpēc REG_GEMINI_MODEL pārslēgšana uz šiem modeļiem
 * bez šī labojuma klusi atdotu nulli tulkojumu.
 */
function ab_opts(string $model): array
{
    // Vienīgais patiesības avots ir klients — citādi A/B mērītu citu konfigurāciju,
    // nekā produkcija lieto.
    return reg_gemini_thinking_min($model)
        + ['json' => true, 'temperature' => 0.1, 'maxOutputTokens' => 8192, 'timeout' => 120];
}

// ── Mistral (otrs piegādātājs) ──────────────────────────────────────────────
// Uzvedne, paketēšana un atbildes parsēšana ir TĀS PAŠAS, ko lieto produkcija —
// atšķiras tikai HTTP ceļš un tokenu uzskaites lauki. Citādi salīdzinājums
// mērītu divas dažādas uzvednes, ne divus modeļus.
function ab_is_mistral(string $model): bool
{
    return str_starts_with($model, 'mistral-') || str_starts_with($model, 'ministral-');
}

/** Atslēga nāk no vides vai faila — kodā tā nekad nenonāk. */
function ab_mistral_key(): string
{
    $k = trim((string)getenv('MISTRAL_API_KEY'));
    if ($k === '' && ($f = trim((string)getenv('MISTRAL_KEY_FILE'))) !== '' && is_file($f)) {
        $k = trim((string)file_get_contents($f));
    }
    if ($k === '') {
        fwrite(STDERR, "✗ Nav Mistral atslēgas: uzstādi MISTRAL_API_KEY vai MISTRAL_KEY_FILE\n");
        exit(1);
    }
    return $k;
}

/** Paralēli izsaukumi ar curl_multi; atgriež [indekss => teksts|null]. */
function ab_mistral_multi(array $prompts, string $model, int $concurrency = 4): array
{
    static $usage = ['in' => 0, 'out' => 0, 'calls' => 0];
    if ($prompts === ['__usage__']) return $usage;      // uzkrājuma nolasīšana

    $key = ab_mistral_key();
    $out = [];
    foreach (array_chunk($prompts, max(1, $concurrency), true) as $wave) {
        $mh = curl_multi_init();
        $hs = [];
        foreach ($wave as $i => $prompt) {
            $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 180,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
                CURLOPT_POSTFIELDS     => json_encode([
                    'model'       => $model,
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.1,
                    'max_tokens'  => 8192,
                ], JSON_UNESCAPED_UNICODE),
            ]);
            curl_multi_add_handle($mh, $ch);
            $hs[$i] = $ch;
        }
        do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);
        foreach ($hs as $i => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            $j = json_decode((string)$body, true);
            $usage['calls']++;
            if ($code !== 200) {
                fwrite(STDERR, "  ⚠ Mistral HTTP $code: " . mb_substr(preg_replace('/\s+/', ' ', (string)$body), 0, 160) . "\n");
                $out[$i] = null;
                continue;
            }
            $usage['in']  += (int)($j['usage']['prompt_tokens'] ?? 0);
            $usage['out'] += (int)($j['usage']['completion_tokens'] ?? 0);
            $out[$i] = (string)($j['choices'][0]['message']['content'] ?? '');
        }
        curl_multi_close($mh);
    }
    return $out;
}

$pdo = konkursi_db();

// ── Paraugs ─────────────────────────────────────────────────────────────────
// Tikai SVEŠVALODU avoti: latviskajiem title_lv ir kopija, ne tulkojums, un tie
// A/B nesaka neko. Deterministisks paraugs (seed) — atkārtojams starp palaišanām.
$ph = implode(',', array_fill(0, count(AB_LV_SOURCES), '?'));
$st = $pdo->prepare(
    "SELECT title, title_lv, source, buyer_country
       FROM notices
      WHERE title_lv IS NOT NULL AND title_lv != '' AND title IS NOT NULL AND title != ''
        AND source NOT IN ($ph)
      GROUP BY title
      ORDER BY (rowid * 2654435761) % 1000003
      LIMIT " . (int)$n);
$st->execute(AB_LV_SOURCES);
$sample = $st->fetchAll(PDO::FETCH_ASSOC);
$total  = count($sample);
if ($total === 0) { fwrite(STDERR, "Nav paraugu — vai tenders.db ir tukša?\n"); exit(1); }

$titles = array_column($sample, 'title');
$chars  = array_sum(array_map('mb_strlen', $titles));

echo "Paraugs:        $total virsraksti (vid. " . round($chars / $total) . " rakstzīmes)\n";
$byCountry = [];
foreach ($sample as $r) { $c = $r['buyer_country'] ?: '??'; $byCountry[$c] = ($byCountry[$c] ?? 0) + 1; }
arsort($byCountry);
echo "Valstis:        " . implode(', ', array_map(
    fn($k, $v) => "$k=$v", array_keys(array_slice($byCountry, 0, 8)), array_slice($byCountry, 0, 8))) . "\n";
echo "Modeļi:         " . implode(', ', $models) . "\n";

// Aplēse: ~4 rakstz./tokens, izvade ~27 tokeni/virsraksts (tā pati formula, ko
// lieto translate_titles.php sausā palaišana).
$inTok  = $chars / 4 + $total * 1.5;
$outTok = $total * 27;
$est = 0.0;
foreach ($models as $mo) {
    [$pi, $po] = AB_PRICES[$mo] ?? [0.50, 3.00];
    $est += $inTok / 1e6 * $pi + $outTok / 1e6 * $po;
}
printf("Aplēse:         ~USD %.4f (~EUR %.4f) par visiem %d modeļiem\n",
    $est, $est * AB_USD_TO_EUR, count($models));

if (!$apply) {
    echo "\nSausā palaišana — API netiek saukts. Palaid ar --apply.\n";
    exit(0);
}

// ── Palaišana ───────────────────────────────────────────────────────────────
$chunks  = array_chunk($titles, KONKURSI_TRANSLATE_BATCH);
$results = [];   // modelis => ['tulk' => [...], 'stat' => [...]]

foreach ($models as $mo) {
    $isMistral = ab_is_mistral($mo);
    putenv('REG_GEMINI_MODEL=' . $mo);           // klients to nolasa katrā izsaukumā
    $before = $isMistral ? ab_mistral_multi(['__usage__'], $mo) : reg_gemini_usage_total();
    $before = ['in' => $before['in'], 'out' => $before['out'],
               'thoughts' => $before['thoughts'] ?? 0, 'calls' => $before['calls']];
    $t0     = microtime(true);
    $opts   = ab_opts($mo);

    // Tas pats ceļš, ko lieto sync (tā pati uzvedne, tā pati paketēšana, tie paši
    // paralēlie viļņi) — TIKAI domāšanas parametrs tiek izšķirts pa modeļiem, jo
    // reg_gemini_titles_opts() 'thinkingBudget' jaunākie modeļi noraida ar HTTP 400.
    $tr = [];
    foreach (array_chunk($chunks, KONKURSI_TRANSLATE_PARALLEL, true) as $wave) {
        $prompts = [];
        foreach ($wave as $i => $chunk) $prompts[$i] = reg_gemini_titles_prompt($chunk);
        $raw = $isMistral
            ? ab_mistral_multi($prompts, $mo, KONKURSI_TRANSLATE_PARALLEL)
            : reg_gemini_generate_multi($prompts, $opts, KONKURSI_TRANSLATE_PARALLEL);
        foreach ($wave as $i => $chunk) {
            $tr[$i] = reg_gemini_titles_parse($raw[$i] ?? null, count($chunk));
        }
        usleep(KONKURSI_TRANSLATE_DELAY_MS * 1000);
    }

    $secs  = microtime(true) - $t0;
    $after = $isMistral ? ab_mistral_multi(['__usage__'], $mo) : reg_gemini_usage_total();
    $after = ['in' => $after['in'], 'out' => $after['out'],
              'thoughts' => $after['thoughts'] ?? 0, 'calls' => $after['calls']];
    $in    = $after['in'] - $before['in'];
    $outT  = $after['out'] - $before['out'];
    $th    = $after['thoughts'] - $before['thoughts'];
    [$pi, $po] = AB_PRICES[$mo] ?? [0.50, 3.00];
    $usd   = $in / 1e6 * $pi + ($outT + $th) / 1e6 * $po;

    // Saplacina paketes atpakaļ vienā sarakstā tādā pašā secībā kā $titles.
    $flat = [];
    foreach ($chunks as $i => $chunk) {
        $lv = $tr[$i] ?? null;
        foreach ($chunk as $j => $orig) $flat[] = $lv === null ? null : (string)($lv[$j] ?? '');
    }

    $results[$mo] = [
        'tulk' => $flat,
        'stat' => [
            'in' => $in, 'out' => $outT, 'thoughts' => $th, 'calls' => $after['calls'] - $before['calls'],
            'secs' => $secs, 'usd' => $usd, 'eur' => $usd * AB_USD_TO_EUR,
            'kluda' => $isMistral ? '' : reg_gemini_error_brief(),
        ],
    ];
    $err = $isMistral ? '' : reg_gemini_error_brief();
    printf("%-24s %5.1f s | ievade %6d | izvade %6d (+dom. %d) | USD %.4f%s\n",
        $mo, $secs, $in, $outT, $th, $usd, $err !== '' ? "  ⚠ $err" : '');
}
putenv('REG_GEMINI_MODEL');   // atstāj vidi tīru

// ── Automātiskās kvalitātes pazīmes ─────────────────────────────────────────
/** Latviešu tekstam raksturīgās rakstzīmes (garumzīmes/mīkstinājumi). */
function ab_lv_diakritika(string $s): bool {
    return (bool)preg_match('/[āčēģīķļņšūžĀČĒĢĪĶĻŅŠŪŽ]/u', $s);
}
/** Normalizē salīdzināšanai: mazie burti, saspiestas atstarpes, bez pieturzīmēm galos. */
function ab_norm(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', mb_strtolower($s, 'UTF-8')), " \t\n\r.,;:");
}

$baseDia = 0; $baseNet = 0;
foreach ($sample as $r) {
    if (ab_lv_diakritika((string)$r['title_lv'])) $baseDia++;
    // Arī bāzes modelis daļu atstāj nemainītu (virsraksts jau latviski, tikai
    // īpašvārdi/kodi) — bez šī skaitļa kandidātu «netulkoti» aile nav salīdzināma.
    if (ab_norm((string)$r['title_lv']) === ab_norm((string)$r['title'])) $baseNet++;
}

$metrics = [];
foreach ($models as $mo) {
    $m = ['nav' => 0, 'tukss' => 0, 'netulkots' => 0, 'diakritika' => 0, 'sakrit' => 0, 'garums' => []];
    foreach ($sample as $i => $r) {
        $cand = $results[$mo]['tulk'][$i] ?? null;
        if ($cand === null)       { $m['nav']++;   continue; }   // formāta/API kļūme
        if (trim($cand) === '')   { $m['tukss']++; continue; }
        if (ab_norm($cand) === ab_norm((string)$r['title']))    $m['netulkots']++;
        if (ab_norm($cand) === ab_norm((string)$r['title_lv'])) $m['sakrit']++;
        if (ab_lv_diakritika($cand))                            $m['diakritika']++;
        $m['garums'][] = mb_strlen($cand) / max(1, mb_strlen((string)$r['title_lv']));
    }
    $m['garums_vid'] = $m['garums'] ? array_sum($m['garums']) / count($m['garums']) : 0;
    $metrics[$mo] = $m;
}

// ── HTML atskaite ───────────────────────────────────────────────────────────
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$h = [];
$h[] = '<!doctype html><meta charset="utf-8"><title>Tulkojumu A/B</title>';
$h[] = '<style>body{font:14px/1.5 system-ui,sans-serif;margin:24px;max-width:none}'
     . 'table{border-collapse:collapse;width:100%;margin:16px 0}'
     . 'th,td{border:1px solid #ddd;padding:6px 8px;vertical-align:top;text-align:left}'
     . 'th{background:#f4f4f4;position:sticky;top:0}'
     . 'td.orig{color:#666;font-size:13px}.bad{background:#fde8e8}.warn{background:#fff6e0}'
     . 'code{background:#f4f4f4;padding:1px 4px}h2{margin-top:32px}</style>';
$h[] = '<h1>Virsrakstu tulkošana: modeļu A/B</h1>';
$h[] = '<p>Paraugs: <b>' . $total . '</b> unikāli svešvalodu virsraksti no <code>tenders.db</code>. '
     . 'Bāzes līnija = esošais <code>title_lv</code> (gemini-3-flash-preview).</p>';

$h[] = '<h2>Izmaksas un ātrums</h2><table><tr><th>Modelis</th><th>Cena $/1M (in/out)</th>'
     . '<th>Ievade</th><th>Izvade</th><th>Domāšana</th><th>Dom. režīms</th><th>Laiks</th><th>USD par ' . $total . '</th>'
     . '<th>€/1000 virsr.</th><th>Prognoze €/mēn*</th></tr>';
foreach ($models as $mo) {
    $s = $results[$mo]['stat'];
    [$pi, $po] = AB_PRICES[$mo] ?? [0.50, 3.00];
    $per1000 = $s['eur'] / $total * 1000;
    $mode = array_key_exists('thinkingBudget', ab_opts($mo)) ? 'budget=0' : 'level=low';
    $h[] = sprintf('<tr><td><b>%s</b></td><td>%.2f / %.2f</td><td>%d</td><td>%d</td><td>%d</td>'
        . '<td>%s</td><td>%.1f s</td><td>%.4f</td><td>%.3f</td><td><b>%.2f</b></td></tr>',
        $e($mo), $pi, $po, $s['in'], $s['out'], $s['thoughts'], $e($mode), $s['secs'], $s['usd'],
        $per1000, $per1000 * 210);
}
$h[] = '</table><p style="color:#666">* pie ~210 000 virsrakstiem mēnesī (2026-08 faktiskais apjoms). '
     . 'Ar Batch API šos skaitļus dala uz 2.</p>';

$h[] = '<h2>Kvalitātes pazīmes</h2><table><tr><th>Modelis</th><th>Formāta kļūmes</th>'
     . '<th>Tukši</th><th>Netulkoti (= oriģināls)</th><th>Ar latviešu diakritiku</th>'
     . '<th>Sakrīt ar bāzi</th><th>Garums pret bāzi</th></tr>';
$h[] = sprintf('<tr><td><b>bāze: gemini-3-flash-preview</b></td><td>—</td><td>—</td><td>%d (%.0f%%)</td>'
    . '<td>%d (%.0f%%)</td><td>100%%</td><td>1,00</td></tr>',
    $baseNet, 100 * $baseNet / $total, $baseDia, 100 * $baseDia / $total);
foreach ($models as $mo) {
    $m = $metrics[$mo];
    $cls = fn($v, $lim) => $v > $lim ? ' class="bad"' : '';
    $h[] = sprintf('<tr><td><b>%s</b></td><td%s>%d</td><td%s>%d</td><td%s>%d (%.0f%%)</td>'
        . '<td>%d (%.0f%%)</td><td>%d (%.0f%%)</td><td>%.2f</td></tr>',
        $e($mo), $cls($m['nav'], 0), $m['nav'], $cls($m['tukss'], 0), $m['tukss'],
        $cls($m['netulkots'], $total * 0.05), $m['netulkots'], 100 * $m['netulkots'] / $total,
        $m['diakritika'], 100 * $m['diakritika'] / $total,
        $m['sakrit'], 100 * $m['sakrit'] / $total, $m['garums_vid']);
}
$h[] = '</table><p style="color:#666">«Netulkoti» = modelis atdeva oriģinālu nemainītu — '
     . 'galvenā vājo modeļu kļūme. «Diakritika» tuvu bāzes līmenim nozīmē, ka valoda tiešām ir latviešu. '
     . 'Neviena no šīm pazīmēm nemēra <b>jēgu</b> — tabulu zemāk jāizlasa cilvēkam.</p>';

$h[] = '<h2>Blakus salīdzinājums</h2><table><tr><th style="width:60px">Nr</th><th>Oriģināls / bāze</th>';
foreach ($models as $mo) $h[] = '<th>' . $e($mo) . '</th>';
$h[] = '</tr>';
foreach ($sample as $i => $r) {
    $h[] = '<tr><td>' . ($i + 1) . '<br><small style="color:#999">' . $e($r['source']) . ' · '
         . $e($r['buyer_country']) . '</small></td>';
    $h[] = '<td class="orig">' . $e($r['title']) . '<br><b style="color:#111">' . $e($r['title_lv']) . '</b></td>';
    foreach ($models as $mo) {
        $c = $results[$mo]['tulk'][$i] ?? null;
        $cls = $c === null || trim((string)$c) === '' ? ' class="bad"'
             : (ab_norm((string)$c) === ab_norm((string)$r['title']) ? ' class="warn"' : '');
        $h[] = '<td' . $cls . '>' . ($c === null ? '<i>— nav atbildes —</i>' : $e($c)) . '</td>';
    }
    $h[] = '</tr>';
}
$h[] = '</table>';

file_put_contents($out, implode("\n", $h));
echo "\nAtskaite: $out\n";
