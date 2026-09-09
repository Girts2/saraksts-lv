<?php
/**
 * registrs/bin/panel_ab.php — MI PANEĻA atbilžu A/B: modelis × domāšanas līmenis.
 *
 * Kāpēc: paneļa rēķinu dzen DOMĀŠANAS tokeni (mērījums 2026-08: ievade 12 763,
 * domāšana 2 806, izvade 1 339 uz izsaukumu — domāšana maksā divreiz vairāk par
 * pašu atbildi). gemini-3.7-flash domāšanu izslēgt NEVAR (thinkingLevel:low tik
 * un tā tērē ~900-1000 tokenu, thinkingBudget:0 tas klusi ignorē), bet
 * gemini-3.8-flash pie 'low' domā nulli — pie TĀS PAŠAS cenas ($0,75/$3,75).
 * Vienīgais, ko nezinām, ir atbilžu kvalitāte. Šis skripts to noliek blakus.
 *
 * Uz reālām atbildēm: īsti uzņēmumi no ur_data.db, īstie paneļa prompti (tos
 * NEkopē — izvelk no master_top.php), īstais googleSearch rīks un maxOutputTokens.
 *
 * UZMANĪBU — MAKSAS izsaukumi. Sausā palaišana rāda aplēsi; tulko tikai ar --apply.
 * googleSearch grounding maksā $14/1000 pieprasījumu ATSEVIŠĶI no tokeniem.
 *
 * Lietošana:
 *   php registrs/bin/panel_ab.php                      # sausā: izlase + izmaksu aplēse
 *   php registrs/bin/panel_ab.php --apply              # palaiž (maksā!)
 *   --n=4                     uzņēmumu skaits (viens no katras apgrozījuma joslas)
 *   --seed=1                  izlases sēkla (deterministiska)
 *   --pogas=a,b,c             pogu id (nokl. 3 visbiežāk lietotās pēc žurnāla)
 *   --varianti=modelis:līmenis,...   (nokl. 3.7:high, 3.8:high, 3.8:low)
 *   --reveal                  atskaitē uzreiz atklāj, kura atbilde kuram variantam
 *   --out=fails.html
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_time_limit(0);

$ROOT = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $ROOT;

require_once $ROOT . '/lib/applog.php';
applog_boot('registrs');
require_once $ROOT . '/registrs/lib/db.php';
require_once $ROOT . '/registrs/lib/data_fetcher.php';
require_once $ROOT . '/registrs/lib/page_builder.php';

// ─────────────────────────────────────────────────────────────────────────────
// ĪSTIE PROMPTI: izvelkam no master_top.php, NEKOPĒJAM
// ─────────────────────────────────────────────────────────────────────────────
// Kopēts prompts novecotu jau pirmajā redakcijā, un A/B mērītu kaut ko citu,
// nekā lietotājs redz. Tāpēc no master_top.php izpildām TIKAI definīciju daļu
// (līdz SSE apstrādātājam) — tā definē apply_placeholders(), build_preambula(),
// $prompts un $gemini_api_key, un neko neizvada.
$mt_src = (string)@file_get_contents($ROOT . '/registrs/templates/master_top.php');
$mt_cut = strpos($mt_src, '// 4. SSE PIEPRASĪJUMA APSTRĀDE');
if ($mt_src === '' || $mt_cut === false) {
    fwrite(STDERR, "✗ master_top.php nav nolasāms vai mainījusies struktūra (nav '// 4. SSE PIEPRASĪJUMA APSTRĀDE')\n");
    exit(1);
}
eval('?>' . substr($mt_src, 0, $mt_cut));
if (!isset($prompts) || !function_exists('apply_placeholders')) {
    fwrite(STDERR, "✗ master_top.php definīcijas neielādējās (nav \$prompts / apply_placeholders)\n");
    exit(1);
}
if (empty($gemini_api_key)) { fwrite(STDERR, "✗ Nav API atslēgas (registrs/mi/key.php)\n"); exit(1); }

// ─────────────────────────────────────────────────────────────────────────────
// PARAMETRI
// ─────────────────────────────────────────────────────────────────────────────
$apply  = in_array('--apply', $argv, true);
$reveal = in_array('--reveal', $argv, true);
$n = 4; $seed = 1;
$out = __DIR__ . '/ab_panelis.html';
// Nokl. pogas — trīs visbiežāk spiestās pēc servera žurnāla (mi.tokeni, 2026-08…09):
// A. Situācijas izvērtējums 161, 1. Finanšu veselība 102, 4. Reputācija un stratēģija 98.
// Trīs dažādi darba veidi: strukturēts spriedums, tīri skaitļi, web meklēšana.
$btnIds = ['uznemuma_diagnoze', 'izdzivosanas_rentgens', 'osint_strategija'];
$variants = [
    ['model' => 'gemini-3.7-flash', 'thinking' => 'high'],   // ražošanā šodien
    ['model' => 'gemini-3.8-flash', 'thinking' => 'high'],   // tā pati cena, jaunāks
    ['model' => 'gemini-3.8-flash', 'thinking' => 'low'],    // kandidāts ietaupījumam
];
// Sarunas sēkla: ar KO lietotājs ekrānā ir, kad viņš uzdod jautājumu. Ražošanā
// čata vēsture tiek "iesēta" ar situācijas izvērtējuma atbildi (ai_panel.php
// seedChatIfNeeded → {q:'Parādi situācijas izvērtējumu.', a: diagnoze}), tāpēc
// arī te sēklu ģenerē ražošanas variants, un visiem variantiem tā ir VIENA.
$seedVariant = null;
foreach ($argv as $a) {
    if (preg_match('/^--n=(\d+)$/', $a, $m))    $n = max(1, (int)$m[1]);
    if (preg_match('/^--sekla=(.+)$/', $a, $m)) {
        [$mo, $th] = array_pad(explode(':', trim($m[1]), 2), 2, 'high');
        $seedVariant = ['model' => $mo, 'thinking' => $th];
    }
    if (preg_match('/^--seed=(\d+)$/', $a, $m)) $seed = (int)$m[1];
    if (preg_match('/^--out=(.+)$/', $a, $m))   $out = $m[1];
    if (preg_match('/^--pogas=(.+)$/', $a, $m)) $btnIds = array_values(array_filter(explode(',', $m[1])));
    if (preg_match('/^--varianti=(.+)$/', $a, $m)) {
        $variants = [];
        foreach (explode(',', $m[1]) as $v) {
            [$mo, $th] = array_pad(explode(':', trim($v), 2), 2, 'high');
            if ($mo !== '') $variants[] = ['model' => $mo, 'thinking' => $th];
        }
    }
}
foreach ($btnIds as $b) {
    if (!isset($prompts['finansu_analize']['buttons'][$b])) {
        fwrite(STDERR, "✗ Nav tādas pogas: $b\n  Pieejamās: "
            . implode(', ', array_keys($prompts['finansu_analize']['buttons'])) . "\n");
        exit(1);
    }
    // user_input pogas ('saruna', 'lietotaja_jautajums') strādā — jautājumu un
    // sarunas vēsturi skripts saģenerē pats (sk. ab_sekla / ab_jautajums).
}

// Cenas $/1M tokenu (ai.google.dev/gemini-api/docs/pricing, pārbaudīts 2026-09-03).
// 3.6/3.7/3.8 ir IEVADA cena līdz 2026-12-31; no 2027-01-01 tā dubultojas.
const AB_PRICES = [
    'gemini-3.8-flash'      => [0.75, 3.75],
    'gemini-3.7-flash'      => [0.75, 3.75],
    'gemini-3.6-flash'      => [0.75, 3.75],
    'gemini-3.5-flash'      => [1.50, 9.00],
    'gemini-3.1-flash-lite' => [0.25, 1.50],
    'gemini-3.5-flash-lite' => [0.50, 3.00],
];
const AB_PRICE_DEFAULT = [0.75, 3.75];
const AB_GROUNDING_USD_1K = 14.0;   // $14/1000 grounding pieprasījumu
const AB_USD_TO_EUR = 0.95;         // tāpat kā KONKURSI_USD_TO_EUR

function ab_price(string $model): array { return AB_PRICES[$model] ?? AB_PRICE_DEFAULT; }

// ─────────────────────────────────────────────────────────────────────────────
// IZLASE: īsti uzņēmumi, pa vienam no katras apgrozījuma joslas
// ─────────────────────────────────────────────────────────────────────────────
// Joslas, nevis nejauša izlase: domāšanas ieguvums (ja tāds ir) parādās tur, kur
// dati ir sarežģīti, tāpēc mikrouzņēmums un vairākmiljonu uzņēmums abi ir jāredz.
// Kārtība deterministiska (rowid hash + sēkla) — tā pati sēkla dod to pašu izlasi.
function ab_select_companies(PDO $conn, int $n, int $seed): array
{
    $bands = [
        ['nos' => 'mikro (<100 tūkst.)',   'min' => 1,        'max' => 100000],
        ['nos' => 'mazs (0,1–1 milj.)',    'min' => 100000,   'max' => 1000000],
        ['nos' => 'vidējs (1–10 milj.)',   'min' => 1000000,  'max' => 10000000],
        ['nos' => 'liels (>10 milj.)',     'min' => 10000000, 'max' => 100000000000],
    ];
    $sql = "SELECT f.legal_entity_registration_number reg, r.name, f.year, f.employees,
                   i.net_turnover t, i.income_after_income_taxes p,
                   (SELECT COUNT(*) FROM financial_statements f2
                     WHERE f2.legal_entity_registration_number = f.legal_entity_registration_number) gadi
            FROM financial_statements f
            JOIN income_statements i ON i.statement_id = f.id
            JOIN register r ON r.regcode = f.legal_entity_registration_number
            WHERE f.year = (SELECT MAX(year) FROM financial_statements WHERE year <= 2025)
              AND f.rounded_to_nearest = 'ONES'
              AND i.net_turnover >= :min AND i.net_turnover < :max
              AND TRIM(COALESCE(r.terminated,'')) = '' AND TRIM(COALESCE(r.closed,'')) = ''
              AND LENGTH(f.legal_entity_registration_number) = 11
            ORDER BY ((f.rowid * 2654435761 + :seed) % 1000003)
            LIMIT 40";
    $picked = [];
    $perBand = (int)ceil($n / count($bands));
    foreach ($bands as $b) {
        $st = $conn->prepare($sql);
        // PDO/SQLite tips: skaitļus saistām ar INT, citādi TEXT salīdzinājums klusi
        // atdod 0 rindu (lib atmiņas piezīme par tipu slazdiem).
        $st->bindValue(':min', $b['min'], PDO::PARAM_INT);
        $st->bindValue(':max', $b['max'], PDO::PARAM_INT);
        $st->bindValue(':seed', $seed, PDO::PARAM_INT);
        $st->execute();
        $taken = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($taken >= $perBand || count($picked) >= $n) break;
            if ((int)$row['gadi'] < 3) continue;          // vajag laikrindu, ne vienu gadu
            $row['josla'] = $b['nos'];
            $picked[] = $row;
            $taken++;
        }
        if (count($picked) >= $n) break;
    }
    return $picked;
}

// ─────────────────────────────────────────────────────────────────────────────
// PROMPTA SAGATAVOŠANA: tas pats, ko dara ask_ai apstrādātājs
// ─────────────────────────────────────────────────────────────────────────────
// Riska semafors un VID ceturkšņi ir PĀRRAKSTĪTI no master_top.php ask_ai bloka
// (tur tie dzīvo apstrādātāja vidū, nevis funkcijā). Ja tur ko maina, jāmaina te.
function ab_risk_summary(array $page_data): string
{
    $lib = $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/risk_semaphore.php';
    if (!is_file($lib)) return '';
    try {
        require_once $lib;
        return reg_risk_semaphore_text(reg_risk_semaphore($page_data));
    } catch (Throwable $e) { return ''; }
}

function ab_vid_quarters(array $page_data): string
{
    try {
        $q_by_key = [];
        foreach ((array)($page_data['results']['pdb_samaksato_nodoklu_kopsummas_cet'] ?? []) as $qr) {
            if (preg_match('/(\d{4})\.\s*gada\s*(\d)\./u', (string)($qr['Taksacijas_gads_ceturksnis'] ?? ''), $m)) {
                $q_by_key[(int)$m[1] * 10 + (int)$m[2]] = $qr;
            }
        }
        krsort($q_by_key);
        $lines = [];
        foreach (array_slice($q_by_key, 0, 8, true) as $qk => $qr) {
            $qv = function ($x) { $s = trim((string)$x); return $s === '' ? '-' : $s; };
            $lines[] = intdiv($qk, 10) . ' Q' . ($qk % 10)
                . ' | ' . $qv($qr['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? '')
                . ' | ' . $qv($qr['Taja_skaita_PVN_iemaksa'] ?? '')
                . ' | ' . $qv($qr['Taja_skaita_IIN_summa'] ?? '')
                . ' | ' . $qv($qr['Taja_skaita_VSAOI_summa'] ?? '')
                . ' | ' . $qv($qr['Videjais_nodarbinato_personu_skaits_cilv'] ?? '');
        }
        return empty($lines) ? ''
            : "Ceturksnis | Nodokļi kopā | PVN | IIN | VSAOI | Darbinieki\n" . implode("\n", $lines);
    } catch (Throwable $e) { return ''; }
}

function ab_build_page(PDO $conn, string $reg): ?array
{
    $main = fetch_main_company_data($conn, $reg);
    if ($main === null) return null;
    $gen = [
        'company_main_data'        => $main,
        'all_results'              => fetch_all_data_for_reg_nr($conn, $reg),
        'member_as_entity_records' => fetch_member_as_entity_data($conn, $reg),
    ];
    $final = build_page_data($gen);
    return [
        'page_data' => $final,
        'rawData'   => (string)($final['ai_json_data'] ?? '{}'),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// SARUNAS SĒKLA: ar ko lietotājs ekrānā ir brīdī, kad viņš jautā
// ─────────────────────────────────────────────────────────────────────────────
// Ražošanā čata pirmo gājienu "iesēj" situācijas izvērtējuma atbilde, un nākamo
// jautājumu lietotājs parasti paņem no tās pašas atbildes "Ko jautāt tālāk"
// čipiem (ai_panel.php seedChatIfNeeded + renderFollowupChips). Tāpēc arī te
// jautājums nav izdomāts, bet paņemts no reālas izvērtējuma atbildes.
function ab_pick_followup(string $diag, int $seed): string
{
    if (preg_match('/##\s*Ko jautāt tālāk(.*)$/su', $diag, $m)) {
        preg_match_all('/^\s*[-*•]\s*(.+?)\s*$/mu', $m[1], $mm);
        $qs = array_values(array_filter(array_map('trim', $mm[1]), fn($q) => mb_strlen($q) > 8));
        if ($qs) return $qs[$seed % count($qs)];
    }
    // Ja izvērtējums sadaļu nav uzrakstījis, salīdzinājums tāpat jāturpina — bet
    // to pasakām skaļi, nevis klusi paslēpjam zem izdomāta jautājuma.
    fwrite(STDERR, "  ⚠ Izvērtējumā nav «Ko jautāt tālāk» — lietoju rezerves jautājumu\n");
    return 'Kāpēc uzņēmumam ir tieši šī problēma, un cik tā ir nopietna?';
}

/** Tas pats formāts un griests, ko sūta ai_panel.php buildChatHistoryParam(). */
function ab_chat_history(string $diag): string
{
    $txt = "Lietotājs: Parādi situācijas izvērtējumu.\nAnalītiķis: " . $diag;
    return mb_strlen($txt) > 6000 ? "(sarunas sākums izlaists)\n" . mb_substr($txt, -6000) : $txt;
}

// ─────────────────────────────────────────────────────────────────────────────
// IZSAUKUMS: tā pati SSE straume, tas pats payload, kas ražošanā
// ─────────────────────────────────────────────────────────────────────────────
function ab_call(string $key, string $model, string $thinking, string $prompt): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model
         . ':streamGenerateContent?alt=sse&key=' . $key;
    $payload = [
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'tools'            => [['googleSearch' => new stdClass()]],
        'generationConfig' => [
            'maxOutputTokens' => 24576,
            'thinkingConfig'  => ['thinkingLevel' => $thinking],
        ],
    ];
    $raw = ''; $t0 = microtime(true); $ttfb = null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_WRITEFUNCTION  => function ($c, $chunk) use (&$raw, &$ttfb, $t0) {
            // Pirmais TEKSTA gabals, ne pirmais baits: domāšanas laikā straume klusē,
            // un tieši to gaidīšanu lietotājs redz kā "lapa nekas nenotiek".
            if ($ttfb === null && strpos($chunk, '"text"') !== false) $ttfb = microtime(true) - $t0;
            $raw .= $chunk;
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    $dt   = microtime(true) - $t0;

    $text = ''; $usage = []; $grounded = false; $queries = []; $finish = '';
    // Rindu dalītājs TIEŠS, ne \R: PCRE baitu režīmā \R par rindas beigām uzskata
    // arī baitu 0x85, kas ir latviešu "Ņ" otrais baits (master_top.php 2026-08-19).
    foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
        if (strpos($line, 'data:') !== 0) continue;
        $j = json_decode(trim(substr($line, 5)), true);
        if (!is_array($j)) continue;
        $text .= (string)($j['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if (isset($j['usageMetadata'])) $usage = $j['usageMetadata'];
        if (isset($j['candidates'][0]['finishReason'])) $finish = (string)$j['candidates'][0]['finishReason'];
        $gm = $j['candidates'][0]['groundingMetadata'] ?? null;
        if (is_array($gm)) {
            $grounded = true;
            foreach ((array)($gm['webSearchQueries'] ?? []) as $q) $queries[] = (string)$q;
        }
    }
    $in  = (int)($usage['promptTokenCount'] ?? 0);
    $outT = (int)($usage['candidatesTokenCount'] ?? 0);
    $th  = (int)($usage['thoughtsTokenCount'] ?? 0);
    [$pin, $pout] = ab_price($model);
    $usd = $in / 1e6 * $pin + ($outT + $th) / 1e6 * $pout
         + ($grounded ? AB_GROUNDING_USD_1K / 1000 : 0);

    return [
        'ok' => $code === 200 && $text !== '', 'http' => $code, 'err' => $cerr,
        'text' => $text, 'in' => $in, 'out' => $outT, 'think' => $th,
        'sec' => $dt, 'ttfb' => $ttfb, 'usd' => $usd,
        'grounded' => $grounded, 'queries' => array_values(array_unique($queries)),
        'finish' => $finish,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// MĒRĪJUMI: promptu paša noteikumi, ko var pārbaudīt mašīnai
// ─────────────────────────────────────────────────────────────────────────────
// Šie NEmēra "labu atbildi" — tie mēra instrukciju ievērošanu. Kvalitāti spriež
// cilvēks aklajā salīdzinājumā zemāk; te ir tikai tas, ko var saskaitīt.
function ab_metrics(string $t, string $rawData, string $btnId): array
{
    $m = [];
    $m['zimes']    = function_exists('mb_strlen') ? mb_strlen($t) : strlen($t);
    $m['vardi']    = preg_match_all('/\p{L}+/u', $t);   // 'saruna' prasa ~250 vārdus
    $m['virsr']    = preg_match_all('/^##+\s+\S/mu', $t);
    $m['nav_datu'] = preg_match_all('/Nav datu/u', $t);
    // Obligātā noslēguma atruna (prompta [OBLIGĀTI NOTEIKUMI] pēdējais punkts).
    $m['atruna']   = (int)(bool)preg_match('/automātiski ģenerēts izglītojošs apskats/u', $t);
    // Aizliegtie vārdi — prompts tos tieši aizliedz.
    $m['simulac']  = preg_match_all('/simulāc|simulēt|simulēts/iu', $t);
    // "Visas procentuālās vērtības noapaļo līdz vienam decimāldaļskaitlim."
    $m['proc_2d']  = preg_match_all('/\d+[.,]\d{2,}\s*%/u', $t);
    // "Ko jautāt tālāk" — obligāts diagnozes un sarunas pogām.
    $m['ko_talak'] = (int)(bool)preg_match('/##\s*Ko jautāt tālāk/u', $t);
    // Angļu piesārņojums (prompts prasa TIKAI latviešu valodu).
    $m['anglu']    = preg_match_all('/\b(the|and|revenue|company|however|therefore)\b/iu', $t);
    // Skaitļi: cik daudz konkrētikas un cik no lielajiem skaitļiem tiešām ir datos.
    // «Nav datos» NAV vienāds ar «izdomāts» — daļa ir aprēķini (CAGR, attiecības,
    // summas). Tas ir salīdzināms rādītājs starp variantiem, ne halucināciju mērs.
    preg_match_all('/\d[\d\s\x{00A0}]{3,}(?:[.,]\d+)?/u', $t, $mm);
    $m['skaitli'] = count($mm[0]);
    $digits = preg_replace('/\D/', '', $rawData);
    $nef = 0;
    foreach ($mm[0] as $num) {
        $n = preg_replace('/\D/', '', explode(',', str_replace('.', ',', $num))[0]);
        if (strlen((string)$n) < 4) continue;
        if (strpos($digits, (string)$n) === false) $nef++;
    }
    $m['skaitli_ne_datos'] = $nef;
    return $m;
}

// ─────────────────────────────────────────────────────────────────────────────
// PALAIŠANA
// ─────────────────────────────────────────────────────────────────────────────
// --rebuild: atskaiti pārtaisa no saglabātā JSON (cita kārtība, --reveal, jauni
// rādītāji) BEZ neviena API izsaukuma. Palaišana maksā ~EUR 1,5 — pārbūve neko.
foreach ($argv as $a) {
    if (!preg_match('/^--rebuild=(.+)$/', $a, $m)) continue;
    $j = json_decode((string)@file_get_contents($m[1]), true);
    if (!is_array($j) || empty($j['rows'])) { fwrite(STDERR, "✗ Nederīgs JSON: {$m[1]}\n"); exit(1); }
    ab_report($j['variants'], $j['tasks'], $j['rows'], (int)($j['seed'] ?? 1), $reveal, $out);
    echo "Atskaite pārbūvēta no {$m[1]}: $out\n";
    exit(0);
}

$conn = get_ur_db();
$companies = ab_select_companies($conn, $n, $seed);
if (empty($companies)) { fwrite(STDERR, "✗ Izlase tukša — pārbaudi ur_data.db\n"); exit(1); }

echo "Varianti (" . count($variants) . "):\n";
foreach ($variants as $v) printf("  %-22s thinking=%s\n", $v['model'], $v['thinking']);
echo "Pogas (" . count($btnIds) . "): " . implode(', ', $btnIds) . "\n";
echo "Uzņēmumi (" . count($companies) . ", sēkla $seed):\n";

$tasks = [];
foreach ($companies as $c) {
    $built = ab_build_page($conn, (string)$c['reg']);
    if ($built === null) { echo "  ⚠ {$c['reg']} — nav datu, izlaižu\n"; continue; }
    $risk = ab_risk_summary($built['page_data']);
    $vid  = ab_vid_quarters($built['page_data']);
    $json = json_decode($built['rawData'], true) ?: [];
    printf("  %-11s %-42s %-20s apgroz.=%s peļņa=%s dati=%d KB\n",
        $c['reg'], mb_substr((string)$c['name'], 0, 40), $c['josla'],
        number_format((float)$c['t'], 0, ',', ' '), number_format((float)$c['p'], 0, ',', ' '),
        (int)round(strlen($built['rawData']) / 1024));
    foreach ($btnIds as $b) {
        $tpl  = $prompts['finansu_analize']['buttons'][$b]['prompt'];
        $chat = !empty($prompts['finansu_analize']['buttons'][$b]['user_input']);
        $tasks[] = [
            'reg' => (string)$c['reg'], 'company' => (string)$c['name'], 'josla' => $c['josla'],
            'apgroz' => (float)$c['t'], 'pelna' => (float)$c['p'], 'gads' => (int)$c['year'],
            'btn' => $b, 'btn_name' => $prompts['finansu_analize']['buttons'][$b]['name'] ?? $b,
            // Čata pogām prompts vēl NAV gatavs: tajā trūkst jautājuma un sarunas
            // vēstures, un tos var iegūt tikai ar API izsaukumu (sēklu), tāpēc
            // sausajā palaišanā tos neģenerē. Aplēsei pietiek ar pusfabrikātu.
            'prompt'  => apply_placeholders($tpl, $json, $built['rawData'], $risk, $vid),
            'rawData' => $built['rawData'],
            'chat'    => $chat,
            'diag_prompt' => $chat
                ? apply_placeholders($prompts['finansu_analize']['buttons']['uznemuma_diagnoze']['prompt'],
                                     $json, $built['rawData'], $risk, $vid)
                : null,
        ];
    }
}
if (empty($tasks)) { fwrite(STDERR, "✗ Nav uzdevumu\n"); exit(1); }

// Aplēse: ievades tokeni no reālā prompta garuma (~4 zīmes/tokens), izvade un
// domāšana no 2026-08 žurnāla mērījuma (izvade 1 339, domāšana 2 806 / high).
$estUsd = 0.0;
foreach ($tasks as $t) {
    $inTok = (int)(strlen($t['prompt']) / 4);
    foreach ($variants as $v) {
        [$pin, $pout] = ab_price($v['model']);
        $think = $v['thinking'] === 'high' ? 2806 : ($v['thinking'] === 'medium' ? 1500 : 300);
        $estUsd += $inTok / 1e6 * $pin + (1339 + $think) / 1e6 * $pout + AB_GROUNDING_USD_1K / 1000;
    }
}
$calls = count($tasks) * count($variants);
$chatRegs = array_unique(array_map(fn($t) => $t['reg'], array_filter($tasks, fn($t) => $t['chat'])));
$seedCalls = count($chatRegs);
if ($seedCalls) {
    $sv = $seedVariant ?? $variants[0];
    // Sēkla ir viena uz uzņēmumu, ne uz variantu: visiem variantiem sarunas sākums
    // jābūt VIENĀDAM, citādi salīdzina divas dažādas sarunas, ne divus modeļus.
    $estUsd += $seedCalls * 0.046;
    printf("Sarunas sēkla: %d izsaukumi ar %s:%s (situācijas izvērtējums → tā «Ko jautāt tālāk» pirmais jautājums)\n",
        $seedCalls, $sv['model'], $sv['thinking']);
}
printf("\nIzsaukumi: %d (%d uzdevumi × %d varianti)%s\n", $calls, count($tasks), count($variants),
    $seedCalls ? " + $seedCalls sēklas" : '');
printf("Aplēse:    ~USD %.2f (~EUR %.2f) — no tā grounding ~USD %.2f ($14/1000)\n",
    $estUsd, $estUsd * AB_USD_TO_EUR, $calls * AB_GROUNDING_USD_1K / 1000);

// Neaizpildīts vietturis nozīmētu, ka A/B mēra citu promptu, nekā lietotājs redz.
$left = [];
$vēlāk = ['{{LIETOTAJA_JAUTAJUMS}}', '{{ATBILDES_STILS}}', '{{SARUNAS_VESTURE}}'];
foreach ($tasks as $t) {
    if (preg_match_all('/\{\{[A-Z_]+\}\}/', $t['prompt'], $mm)) {
        foreach ($mm[0] as $ph) {
            if ($t['chat'] && in_array($ph, $vēlāk, true)) continue;   // aizpilda pēc sēklas
            $left[$ph] = ($left[$ph] ?? 0) + 1;
        }
    }
}
if ($left) {
    echo "\n⚠ Neaizpildīti vietturi promptos: ";
    foreach ($left as $ph => $c) echo "$ph ($c) ";
    echo "\n";
} else {
    echo "Vietturi: visi aizpildīti ✓\n";
}
printf("Prompta garums: %s–%s zīmes\n",
    number_format(min(array_map(fn($t) => strlen($t['prompt']), $tasks)), 0, ',', ' '),
    number_format(max(array_map(fn($t) => strlen($t['prompt']), $tasks)), 0, ',', ' '));

if (in_array('--dump', $argv, true)) {
    echo "\n--- 1. prompts (pirmās 2500 zīmes) ---\n";
    echo mb_substr($tasks[0]['prompt'], 0, 2500), "\n--- … ---\n";
    exit(0);
}

if (!$apply) {
    echo "\nSausā palaišana — neviens API izsaukums nav veikts. Palaid ar --apply.\n";
    exit(0);
}

// ── Sarunas sēkla (tikai čata pogām) ─────────────────────────────────────────
if ($seedCalls) {
    $sv = $seedVariant ?? $variants[0];
    printf("\nSarunas sēkla — %s · %s (%d uzņēmumi)…\n", $sv['model'], $sv['thinking'], $seedCalls);
    $seedByReg = [];
    foreach ($tasks as $t) {
        if (!$t['chat'] || isset($seedByReg[$t['reg']])) continue;
        $r = ab_call($gemini_api_key, $sv['model'], $sv['thinking'], $t['diag_prompt']);
        if (!$r['ok']) { fwrite(STDERR, "✗ Sēkla {$t['reg']}: HTTP {$r['http']} {$r['err']}\n"); exit(1); }
        $q = ab_pick_followup($r['text'], $seed);
        $seedByReg[$t['reg']] = ['diag' => $r['text'], 'q' => $q];
        printf("  %-11s %5.1fs  jautājums: %s\n", $t['reg'], $r['sec'], $q);
    }
    $stils = $ai_answer_styles[array_key_first($ai_answer_styles)]['instruction'] ?? '';
    foreach ($tasks as $i => $t) {
        if (!$t['chat']) continue;
        $sd = $seedByReg[$t['reg']];
        $tasks[$i]['prompt'] = str_replace(
            ['{{LIETOTAJA_JAUTAJUMS}}', '{{ATBILDES_STILS}}', '{{SARUNAS_VESTURE}}'],
            [$sd['q'], $stils, ab_chat_history($sd['diag'])],
            $t['prompt']);
        $tasks[$i]['jautajums'] = $sd['q'];
    }
}

echo "\nSāku (" . date('H:i:s') . ")…\n";
$rows = [];
$done = 0;
foreach ($tasks as $ti => $t) {
    foreach ($variants as $vi => $v) {
        $r = ab_call($gemini_api_key, $v['model'], $v['thinking'], $t['prompt']);
        $r['metrics'] = $r['ok'] ? ab_metrics($r['text'], $t['rawData'], $t['btn']) : [];
        $r['task'] = $ti; $r['variant'] = $vi;
        $rows[] = $r;
        $done++;
        printf("  [%2d/%2d] %-11s %-24s %-22s %-4s  %s %5.1fs (līdz tekstam %s) in=%5d izv=%4d dom=%5d USD %.4f%s\n",
            $done, $calls, $t['reg'], mb_substr($t['btn_name'], 0, 22), $v['model'], $v['thinking'],
            $r['ok'] ? '✓' : '✗', $r['sec'], $r['ttfb'] === null ? '—' : sprintf('%.1fs', $r['ttfb']),
            $r['in'], $r['out'], $r['think'], $r['usd'],
            $r['ok'] ? ($r['finish'] === 'MAX_TOKENS' ? '  ⚠ APRAUTS' : '') : '  HTTP ' . $r['http'] . ' ' . substr($r['err'], 0, 60));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ATSKAITE
// ─────────────────────────────────────────────────────────────────────────────
function ab_md(string $t): string
{
    $h = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $h = preg_replace('/^###\s+(.+)$/mu', '<h4>$1</h4>', $h);
    $h = preg_replace('/^##\s+(.+)$/mu', '<h3>$1</h3>', $h);
    $h = preg_replace('/^#\s+(.+)$/mu', '<h3>$1</h3>', $h);
    $h = preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', $h);
    $h = preg_replace('/^[-•]\s+(.+)$/mu', '<li>$1</li>', $h);
    $h = preg_replace('/(<li>.*<\/li>\n?)+/su', '<ul>$0</ul>', $h);
    return nl2br($h);
}

function ab_report(array $variants, array $tasks, array $rows, int $seed,
                   bool $reveal, string $out): void
{
    $calls = count($tasks) * count($variants);
    $agg = [];
    foreach ($variants as $vi => $v) {
        $agg[$vi] = ['n' => 0, 'ok' => 0, 'sec' => 0.0, 'ttfb' => 0.0, 'ttfb_n' => 0, 'usd' => 0.0,
                     'in' => 0, 'out' => 0, 'think' => 0, 'grounded' => 0, 'apr' => 0, 'met' => []];
    }
    foreach ($rows as $r) {
        $a = &$agg[$r['variant']];
        $a['n']++; $a['sec'] += $r['sec']; $a['usd'] += $r['usd'];
        $a['in'] += $r['in']; $a['out'] += $r['out']; $a['think'] += $r['think'];
        if ($r['ok']) $a['ok']++;
        if ($r['grounded']) $a['grounded']++;
        if ($r['finish'] === 'MAX_TOKENS') $a['apr']++;
        if ($r['ttfb'] !== null) { $a['ttfb'] += $r['ttfb']; $a['ttfb_n']++; }
        foreach (($r['metrics'] ?? []) as $k => $val) $a['met'][$k] = ($a['met'][$k] ?? 0) + $val;
        unset($a);
    }

    // Aklā kārtība: uz katru uzdevumu varianti sajaukti deterministiski (sēkla + uzdevums),
    // lai spriedumu neietekmē tas, ka viens variants vienmēr stāv pirmais.
    // crc32, ne lineāra formula: ($x * konst + $ti * konst) % 1000 dod tikai dažus
    // atkārtojošos rakstus, un 12 uzdevumos 9 sanāca vienā un tajā pašā secībā —
    // tad aklums ir tikai uz papīra (2026-09-03).
    $order = [];
    foreach ($tasks as $ti => $t) {
        $idx = array_keys($variants);
        // md5, ne crc32: CRC ir lineārs, un, mainoties tikai pēdējai zīmei, vērtību
    // KĀRTĪBA visiem uzdevumiem sanāca viena un tā pati (pārbaudīts 2026-09-03).
    $hx = fn(int $x): int => (int)hexdec(substr(md5($seed . '-' . $ti . '-' . $x), 0, 8));
    usort($idx, fn($x, $y) => $hx($x) <=> $hx($y));
        $order[$ti] = $idx;
    }

    $byTask = [];
    foreach ($rows as $r) $byTask[$r['task']][$r['variant']] = $r;

    $met_labels = [
        'zimes' => 'Zīmes', 'vardi' => 'Vārdi', 'virsr' => 'Virsraksti', 'skaitli' => 'Skaitļi',
        'skaitli_ne_datos' => 'Skaitļi ārpus datiem', 'nav_datu' => '«Nav datu»',
        'atruna' => 'Atruna beigās', 'ko_talak' => '«Ko jautāt tālāk»',
        'simulac' => 'Aizliegtais «simulāc»', 'proc_2d' => '% ar 2+ decimāliem', 'anglu' => 'Angļu vārdi',
    ];

    $H = [];
    $H[] = '<!doctype html><html lang="lv"><meta charset="utf-8">';
    $H[] = '<meta name="viewport" content="width=device-width,initial-scale=1">';
    $H[] = '<title>MI paneļa A/B — ' . date('Y-m-d H:i') . '</title><style>
    body{font:15px/1.55 -apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:24px;background:#f6f7f9;color:#16181d}
    h1{font-size:22px;margin:0 0 4px} h2{font-size:18px;margin:32px 0 8px}
    .sub{color:#667;margin-bottom:18px}
    table{border-collapse:collapse;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08);margin:8px 0 18px;font-size:14px}
    th,td{border:1px solid #e3e6ea;padding:6px 10px;text-align:right}
    th:first-child,td:first-child{text-align:left}
    th{background:#eef1f4;font-weight:600}
    .win{background:#e8f6ec;font-weight:600}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;align-items:start}
    .card{background:#fff;border:1px solid #e3e6ea;border-radius:8px;overflow:hidden}
    .card h4{margin:0;padding:8px 12px;background:#eef1f4;font-size:14px;border-bottom:1px solid #e3e6ea}
    .card .body{padding:12px;max-height:62vh;overflow:auto;font-size:14px}
    .card .body h3{font-size:15px;margin:14px 0 4px;color:#24304a}
    .card .body h4{background:none;border:0;padding:0;margin:10px 0 3px;font-size:14px}
    .meta{padding:6px 12px;background:#fafbfc;border-top:1px solid #eee;color:#667;font-size:12.5px}
    .task{margin:26px 0 8px;padding:8px 12px;background:#24304a;color:#fff;border-radius:6px}
    .task b{color:#ffd479}
    .note{background:#fff8e1;border-left:4px solid #ffb300;padding:10px 14px;margin:14px 0;font-size:14px}
    .err{color:#b00}
    code{background:#eef1f4;padding:1px 4px;border-radius:3px}
    </style>';
    $H[] = '<h1>MI paneļa A/B — modelis × domāšanas līmenis</h1>';
    $H[] = '<div class="sub">' . date('Y-m-d H:i') . ' · ' . count($tasks) . ' uzdevumi × '
         . count($variants) . ' varianti = ' . $calls . ' izsaukumi · sēkla ' . $seed
         . ' · īstie paneļa prompti no <code>master_top.php</code>, īsti uzņēmumi no <code>ur_data.db</code>, googleSearch ieslēgts kā ražošanā</div>';

    $H[] = '<h2>Kopsavilkums</h2><table><tr><th>Variants</th><th>Sekmīgi</th><th>Vidēji s</th>'
         . '<th>Līdz 1. tekstam s</th><th>Ievade</th><th>Izvade</th><th>Domāšana</th>'
         . '<th>USD/izsaukums</th><th>Grounding</th><th>Aprauts</th></tr>';
    $minUsd = min(array_map(fn($a) => $a['n'] ? $a['usd'] / $a['n'] : 0, $agg));
    foreach ($variants as $vi => $v) {
        $a = $agg[$vi]; $nn = max(1, $a['n']);
        $u = $a['usd'] / $nn;
        $H[] = '<tr><td>' . htmlspecialchars($v['model']) . ' · <b>' . htmlspecialchars($v['thinking']) . '</b></td>'
            . '<td>' . $a['ok'] . '/' . $a['n'] . '</td>'
            . '<td>' . number_format($a['sec'] / $nn, 1, ',', ' ') . '</td>'
            . '<td>' . ($a['ttfb_n'] ? number_format($a['ttfb'] / $a['ttfb_n'], 1, ',', ' ') : '—') . '</td>'
            . '<td>' . number_format($a['in'] / $nn, 0, ',', ' ') . '</td>'
            . '<td>' . number_format($a['out'] / $nn, 0, ',', ' ') . '</td>'
            . '<td>' . number_format($a['think'] / $nn, 0, ',', ' ') . '</td>'
            . '<td' . (abs($u - $minUsd) < 1e-9 ? ' class="win"' : '') . '>' . number_format($u, 4, ',', ' ') . '</td>'
            . '<td>' . $a['grounded'] . '/' . $a['n'] . '</td>'
            . '<td>' . ($a['apr'] ?: '—') . '</td></tr>';
    }
    $H[] = '</table>';

    $H[] = '<h2>Instrukciju ievērošana (vidēji uz atbildi)</h2>';
    $H[] = '<table><tr><th>Rādītājs</th>';
    foreach ($variants as $v) $H[] = '<th>' . htmlspecialchars($v['model'] . ' · ' . $v['thinking']) . '</th>';
    $H[] = '</tr>';
    foreach ($met_labels as $k => $lab) {
        $H[] = '<tr><td>' . $lab . '</td>';
        foreach ($variants as $vi => $v) {
            $a = $agg[$vi]; $nn = max(1, $a['ok']);
            $H[] = '<td>' . number_format(($a['met'][$k] ?? 0) / $nn, 2, ',', ' ') . '</td>';
        }
        $H[] = '</tr>';
    }
    $H[] = '</table>';
    $H[] = '<div class="note"><b>Ko šie skaitļi nav.</b> Tie mēra tikai to, ko var saskaitīt: '
         . 'obligāto atrunu, aizliegtos vārdus, procentu noapaļošanu, virsrakstu skaitu. '
         . '«Skaitļi ārpus datiem» nav halucināciju mērs — liela daļa ir aprēķini (CAGR, attiecības, summas), '
         . 'kas datos burtiski neparādās; salīdzināms tikai starp variantiem uz tā paša uzdevuma. '
         . 'Kvalitāti spriež cilvēks zemāk, akli.</div>';

    $H[] = '<h2>Atbildes blakus (akli)</h2>';
    $H[] = '<div class="sub">Katrā uzdevumā varianti sajaukti — kurš ir kurš, atklāts tabulā pašās beigās'
         . ($reveal ? ' (un šoreiz arī virs katras atbildes, jo palaists ar --reveal)' : '') . '.</div>';
    foreach ($tasks as $ti => $t) {
        $H[] = '<div class="task"><b>' . htmlspecialchars($t['company']) . '</b> (' . $t['reg'] . ') · '
            . htmlspecialchars($t['josla']) . ' · apgrozījums ' . number_format($t['apgroz'], 0, ',', ' ')
            . ' € · peļņa ' . number_format($t['pelna'], 0, ',', ' ') . ' € (' . $t['gads'] . ')'
            . ' · poga: ' . htmlspecialchars($t['btn_name'])
            . (!empty($t['jautajums']) ? '<br>Jautājums: <b>' . htmlspecialchars($t['jautajums']) . '</b>' : '')
            . '</div>';
        $H[] = '<div class="grid">';
        foreach ($order[$ti] as $pos => $vi) {
            $r = $byTask[$ti][$vi] ?? null;
            $lab = 'Atbilde ' . chr(65 + $pos) . ($reveal ? ' — ' . htmlspecialchars($variants[$vi]['model'] . ' · ' . $variants[$vi]['thinking']) : '');
            $H[] = '<div class="card"><h4>' . $lab . '</h4>';
            if (!$r || !$r['ok']) {
                $H[] = '<div class="body err">✗ Neizdevās: HTTP ' . ($r['http'] ?? '?') . ' '
                    . htmlspecialchars(substr((string)($r['err'] ?? ''), 0, 200)) . '</div>';
            } else {
                $H[] = '<div class="body">' . ab_md($r['text']) . '</div>';
                $H[] = '<div class="meta">' . number_format($r['sec'], 1, ',', ' ') . ' s · līdz 1. tekstam '
                    . ($r['ttfb'] === null ? '—' : number_format($r['ttfb'], 1, ',', ' ') . ' s')
                    . ' · domāšana ' . number_format($r['think'], 0, ',', ' ')
                    . ' · izvade ' . number_format($r['out'], 0, ',', ' ')
                    . ' · USD ' . number_format($r['usd'], 4, ',', ' ')
                    . ($r['grounded'] ? ' · meklēja: ' . htmlspecialchars(implode('; ', array_slice($r['queries'], 0, 4))) : ' · nemeklēja')
                    . ($r['finish'] === 'MAX_TOKENS' ? ' · <b>APRAUTS</b>' : '') . '</div>';
            }
            $H[] = '</div>';
        }
        $H[] = '</div>';
    }

    $H[] = '<h2>Atslēga (kurš ir kurš)</h2><table><tr><th>Uzdevums</th><th>A</th><th>B</th><th>C</th></tr>';
    foreach ($tasks as $ti => $t) {
        $H[] = '<tr><td>' . htmlspecialchars($t['company']) . ' · ' . htmlspecialchars($t['btn_name']) . '</td>';
        foreach ($order[$ti] as $vi) {
            $H[] = '<td>' . htmlspecialchars($variants[$vi]['model'] . ' · ' . $variants[$vi]['thinking']) . '</td>';
        }
        $H[] = '</tr>';
    }
    $H[] = '</table></html>';

    file_put_contents($out, implode("\n", $H));
}



ab_report($variants, $tasks, $rows, $seed, $reveal, $out);

// Jēldatus saglabājam blakus: viena palaišana maksā ~EUR 1,5, un atskaites
// pārbūvei (cita kārtība, --reveal, jauni rādītāji) nedrīkst vajadzēt maksāt vēlreiz.
$json = $out . '.json';
file_put_contents($json, json_encode([
    'laiks' => date('c'), 'seed' => $seed, 'variants' => $variants,
    // Promptu un jēldatus neglabājam (tie ir atvasināmi no ur_data.db un master_top.php,
    // un JSON citādi izaugtu ~10×); atskaitei tie nav vajadzīgi.
    'tasks' => array_map(fn($t) => array_diff_key($t, ['prompt' => 1, 'rawData' => 1]), $tasks),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$totUsd = array_sum(array_map(fn($r) => $r['usd'], $rows));
printf("\nKopā iztērēts: USD %.4f (EUR %.4f)\n", $totUsd, $totUsd * AB_USD_TO_EUR);
echo "Atskaite: $out\nJēldati:  $json\n";
