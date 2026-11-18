<?php
/**
 * granti/bin/izvilkums_demo.php — MĒRĪJUMS: cik daudz no valsts iestāžu ziņu plūsmas
 * vispār ir konkursi, un cik no tiem termiņš un summa ir nolasāmi bez cilvēka.
 *
 * Šis NAV izvilcējs produkcijai. Tas atbild uz vienu jautājumu ar skaitli: vai HTML/JSON
 * teksta pusē vispār ir, ko izvilkt. Ja atbilde ir "termiņš nolasās 30 % gadījumu", tad
 * avots sadaļai neder, un to labāk zināt tagad, nevis pēc tam, kad uzrakstīts parseris.
 *
 * Lietojums: php granti/bin/izvilkums_demo.php [--limit=50]
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/ievacejs.php';

$opts = getopt('', ['limit::']);
$LIM = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 50;

/** Vienotās valsts Drupal platformas ziņu plūsma. Viens un tas pats visām iestādēm. */
const DEMO_AVOTI = [
    'CFLA' => 'https://www.cfla.gov.lv/jsonapi/node/article',
    'LZP'  => 'https://www.lzp.gov.lv/jsonapi/node/article',
    'VKKF' => 'https://www.vkkf.lv/jsonapi/node/article',
    'LAD'  => 'https://www.lad.gov.lv/jsonapi/node/article',
    'VIAA' => 'https://www.viaa.gov.lv/jsonapi/node/article',
];

/**
 * Vai ziņa IZSLUDINA konkursu? Šķiro darbības vārds, ne tēma: "izsludina atlasi" un
 * "aicina pieteikties" ir konkurss, bet "atlases rezultāti" un "noslēgusies atlase" —
 * ziņa PAR konkursu, kas jau ir beidzies. Bez šī šķīruma puse sadaļas būtu vēsture.
 */
function d_ir_konkurss(string $v): bool {
    $v = mb_strtolower($v);
    $pret = ['rezultāt', 'noslēgusies', 'noslēdzies', 'apstiprināt', 'piešķirt', 'vebinār',
             'seminār', 'tiešraide', 'godin', 'izvērtēšan'];
    foreach ($pret as $p) if (str_contains($v, $p)) return false;
    $par = ['izsludina', 'izsludināt', 'aicina pieteikties', 'var pieteikties', 'atvērts konkurss',
            'atverts konkurss', 'pieņem pieteikumus', 'sāk pieteikšanos', 'atlase', 'konkurs'];
    foreach ($par as $p) if (str_contains($v, $p)) return true;
    return false;
}

/** Latviešu datumu formas -> Y-m-d. Mēnesis locījumā ("oktobrim", "oktobra", "oktobrī"). */
function d_datums(string $t): ?string {
    $men = ['janvār' => 1, 'februār' => 2, 'mart' => 3, 'aprīl' => 4, 'maij' => 5, 'jūnij' => 6,
            'jūlij' => 7, 'august' => 8, 'septembr' => 9, 'oktobr' => 10, 'novembr' => 11, 'decembr' => 12];
    // 1) "2026. gada 15. oktobrī" / "līdz 2026. gada 15. oktobrim"
    if (preg_match('~(\d{4})\.\s*gada\s*(\d{1,2})\.\s*(\p{L}+)~u', $t, $m)) {
        foreach ($men as $s => $n) if (str_starts_with(mb_strtolower($m[3]), $s))
            return sprintf('%04d-%02d-%02d', (int)$m[1], $n, (int)$m[2]);
    }
    // 2) "15. oktobrim" bez gada — gadu ņem no konteksta tikai tad, ja tas TUVUMĀ ir;
    //    citādi datums bez gada ir minējums, un minējums termiņā ir bīstamāks par tukšumu.
    if (preg_match('~(\d{1,2})\.\s*(\p{L}+)\p{L}*\s*(?:plkst|,|\)|\s)~u', $t, $m)
        && preg_match('~\b(20\d{2})\b~', $t, $g)) {
        foreach ($men as $s => $n) if (str_starts_with(mb_strtolower($m[2]), $s))
            return sprintf('%04d-%02d-%02d', (int)$g[1], $n, (int)$m[1]);
    }
    // 3) 15.10.2026 un 2026-10-15
    if (preg_match('~\b(\d{1,2})\.(\d{1,2})\.(20\d{2})\b~', $t, $m))
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    if (preg_match('~\b(20\d{2})-(\d{2})-(\d{2})\b~', $t, $m)) return $m[0];
    return null;
}

/** Summa eiro. Atgriež lielāko atrasto — konkursa aploksni, ne kafijas tāmi. */
function d_summa(string $t): ?float {
    $max = null;
    if (preg_match_all('~(\d[\d\s\x{00A0}.]*(?:,\d+)?)\s*(milj|miljon)~ui', $t, $mm, PREG_SET_ORDER))
        foreach ($mm as $m) $max = max($max ?? 0, (float)str_replace([' ', "\u{00A0}", '.', ','], ['', '', '', '.'], $m[1]) * 1e6);
    if (preg_match_all('~(\d[\d\s\x{00A0}]{2,}(?:,\d+)?)\s*(?:EUR|€|eiro)~ui', $t, $mm, PREG_SET_ORDER))
        foreach ($mm as $m) $max = max($max ?? 0, (float)str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $m[1]));
    return $max;
}

function d_teksts(string $html): string {
    $t = preg_replace('~<[^>]+>~', ' ', $html) ?? $html;
    return trim(preg_replace('~\s+~u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

$kop = ['zinas' => 0, 'konkursi' => 0, 'ar_terminu' => 0, 'ar_summu' => 0];
foreach (DEMO_AVOTI as $iestade => $bāze) {
    // TEKSTS NAV RAKSTA LAUKOS. Vienotajā valsts Drupal platformā raksta saturs dzīvo
    // Paragraphs vienībās (relationships.field_article_content), tāpēc pirmais mērījums
    // rādīja "termiņš nolasāms 5 %" — tas mērīja tikai virsrakstus, nevis avotu. Ar
    // ?include=... teksts atnāk tajā pašā atbildē (included[].field_text_content.value).
    $url = $bāze . '?page%5Blimit%5D=' . $LIM . '&sort=-created&include=field_article_content';
    $g = gr_iev_get($url, 30);
    if (!$g['ok']) { printf("%-6s  ! %s\n", $iestade, $g['kapec']); continue; }
    $j = json_decode($g['body'], true);
    $rows = is_array($j['data'] ?? null) ? $j['data'] : [];
    // Paragrāfu teksti pēc id, lai rakstam tos var salikt kopā vienā virknē.
    $pgr = [];
    foreach (($j['included'] ?? []) as $i) {
        foreach (($i['attributes'] ?? []) as $k => $v) {
            if (is_array($v) && isset($v['value']) && str_contains($k, 'text'))
                $pgr[(string)$i['id']] = ($pgr[(string)$i['id']] ?? '') . ' ' . (string)$v['value'];
        }
    }
    $n = $k = $dt = $sm = 0;
    $paraugi = [];
    foreach ($rows as $r) {
        $a = $r['attributes'] ?? [];
        $v = (string)($a['title'] ?? '');
        $saturs = '';
        foreach (($r['relationships']['field_article_content']['data'] ?? []) as $ref)
            $saturs .= ' ' . ($pgr[(string)($ref['id'] ?? '')] ?? '');
        $teksts = $v . ' ' . d_teksts($saturs);
        $n++;
        if (!d_ir_konkurss($v)) continue;
        $k++;
        $d = d_datums($teksts); $s = d_summa($teksts);
        if ($d !== null) $dt++;
        if ($s !== null) $sm++;
        if (count($paraugi) < 2) $paraugi[] = sprintf('      · %s → termiņš %s, summa %s',
            mb_substr($v, 0, 58), $d ?? '—', $s !== null ? number_format($s, 0, ',', ' ') . ' €' : '—');
    }
    printf("%-6s  ziņas %3d → konkursi %3d (%d%%), no tiem termiņš %d (%d%%), summa %d (%d%%)\n",
        $iestade, $n, $k, $n ? round($k / $n * 100) : 0, $dt, $k ? round($dt / $k * 100) : 0,
        $sm, $k ? round($sm / $k * 100) : 0);
    foreach ($paraugi as $p) echo $p, "\n";
    $kop['zinas'] += $n; $kop['konkursi'] += $k; $kop['ar_terminu'] += $dt; $kop['ar_summu'] += $sm;
}
printf("\nKOPĀ: %d ziņas → %d konkursa izsludinājumi; termiņš nolasāms %d (%d%%), summa %d (%d%%).\n",
    $kop['zinas'], $kop['konkursi'], $kop['ar_terminu'],
    $kop['konkursi'] ? round($kop['ar_terminu'] / $kop['konkursi'] * 100) : 0,
    $kop['ar_summu'], $kop['konkursi'] ? round($kop['ar_summu'] / $kop['konkursi'] * 100) : 0);
