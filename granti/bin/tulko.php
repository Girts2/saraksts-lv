<?php
/**
 * granti/bin/tulko.php — grantu tekstu tulkošana DZĪVAJĀ datubāzē (TIKAI CLI).
 *
 * Būve ar --tulkot dara to pašu, bet prasa pilnu pārbūvi. Šis skripts strādā ar jau
 * uzbūvēto granti/db/granti.sqlite, tāpēc der cron rindai: pārbūve reizi dienā, tulkošana
 * uzreiz pēc tās (darbs `granti.tulko` sarakstā lib/admin_jobs.php).
 *
 * DIVI CEĻI:
 *   · BATCH (noklusējums) — Gemini Batch API, PUSE cenas. Darbu iesniedz, tas nostrādā
 *     Google pusē, rezultātu savāc turpat vai nākamā palaišana. Sk. lib/batch.php.
 *   · TŪLĪTĒJAIS (--tulits) — 4 paralēli pieprasījumi, atbilde uzreiz, pilna cena.
 *     Der, kad vajag dažus tekstus tagad (piem. pēc uzvednes maiņas pārbaudei).
 *
 * Lietojums:
 *   php granti/bin/tulko.php                  — batch: savāc, iesniedz, sagaidi, savāc
 *   php granti/bin/tulko.php --limit=50       — ne vairāk kā 50 tekstus
 *   php granti/bin/tulko.php --tulits         — tūlītējais ceļš (pilna cena)
 *   php granti/bin/tulko.php --savac          — TIKAI savāc gatavos batch darbus (neko nesūta)
 *   php granti/bin/tulko.php --statuss        — stāvoklis; neraksta grants DB
 *   php granti/bin/tulko.php --aplese         — cik maksātu atlikušais (netulko)
 *   php granti/bin/tulko.php --tiri=180       — izmet rindu, kas 180 dienas nav pieskarta
 *   php granti/bin/tulko.php --tiri-versijas  — izmet vecas uzvednes versijas netulkotās rindas
 *                                               (visiem karodziņiem ar vērtību vajag "=")
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/tulkojumi.php';
require_once __DIR__ . '/../lib/batch.php';

$opts = getopt('', ['limit::', 'statuss', 'tiri::', 'tiri-versijas', 'aplese', 'db::',
                    'tulits', 'savac', 'gaidit::']);
// getopt ar "::" pieņem TIKAI --limit=50. Rakstot "--limit 50", vērtība ir false, un
// (int)false = 0 nozīmēja "bez ierobežojuma" — tieši pretēji tam, ko cilvēks gribēja.
// Klusi pretēja nozīme ir sliktāka par kļūdu.
foreach (['limit', 'tiri', 'db', 'gaidit'] as $o) {
    if (array_key_exists($o, $opts) && $opts[$o] === false) {
        fwrite(STDERR, "Karodziņam --$o vajag vērtību ar vienādības zīmi: --$o=VĒRTĪBA\n");
        exit(2);
    }
}
function gt_log(string $m): void { echo date('H:i:s') . '  ' . $m . "\n"; }

$DB_FILE = $opts['db'] ?? granti_db_path();
if (!is_file((string)$DB_FILE)) { fwrite(STDERR, "Nav datubāzes: $DB_FILE (vispirms palaid granti/bin/build.php)\n"); exit(1); }
$grants = new PDO('sqlite:' . $DB_FILE);
$grants->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$grants->exec('PRAGMA busy_timeout=10000');

$lasitTikai = isset($opts['statuss']) || isset($opts['aplese']);

if (isset($opts['tiri-versijas'])) {
    gt_log('Izmestas vecas versijas netulkotās rindas: ' . gr_tulk_tiri_versijas() . '.');
}

// Sasaista dzīvo DB ar kešu: ieliek rindā jauno, izmet bāreņus, aizpilda lv_* no keša.
$st = gr_tulk_sinhronize($grants, 'gt_log', !$lasitTikai);

// Tīrīšana notiek PĒC sinhronizācijas. Pirms tās tā dzēsa arī rindas, kas dzīvajā DB IR, un
// sinhronizācija tās uzreiz ielika atpakaļ ar neizdevas=0 — t.i. "--tiri=1" klusi atiestatīja
// visu kļūmju vēsturi un lika maksāt par jau atmestiem tekstiem vēlreiz (pārbaudīts: 3 -> 0).
if (isset($opts['tiri'])) {
    $d = $opts['tiri'] === '' ? 180 : max(0, (int)$opts['tiri']);   // --tiri=0 nozīmē 0, ne 180
    gt_log('Iztīrīti pamesti rindas ieraksti: ' . gr_tulk_tiri($d) . " (vecāki par $d dienām).");
}

// PĀRĀK VECAS BATCH LĪMEŅA KĻŪMES ATIESTATA. Darba līmeņa kļūme (EXPIRED, CANCELLED, fails
// nenolasāms) nav teksta vaina, bet skaitītājs to skaita tāpat — citādi nedēļa ar Google
// sastrēgumu iesaldētu simtiem tekstu angliski uz mūžiem. Pēc 7 dienām tiem dod jaunas trīs
// reizes; ja Google joprojām klūp, atkal pēc 7 dienām — ne bezgalīgi katru dienu.
if (!$lasitTikai) {
    $c = gr_tulk_db();
    $at = $c->prepare("UPDATE tulkojumi SET neizdevas = 0, kluda = NULL
                       WHERE lv IS NULL AND neizdevas >= ? AND kluda LIKE 'batch 20__-__-__:%' AND substr(kluda, 7, 10) < ?");
    $at->bindValue(1, GR_TULK_MAX_KLUMES, PDO::PARAM_INT);
    $at->bindValue(2, date('Y-m-d', strtotime('-7 days')), PDO::PARAM_STR);
    $at->execute();
    if ($at->rowCount() > 0) gt_log('Atiestatīti ' . $at->rowCount() . ' teksti pēc vecām batch līmeņa kļūmēm (> 7 dienas).');
}

if ($lasitTikai) {
    $r = gr_tulk_rindas_stats();
    $cache = gr_tulk_db();
    // Versijas filtrs OBLIGĀTS: bez tā aplēsē ieskaitās vecas uzvednes rindas, kuras
    // tulkotājs nekad neņems (v1 -> v2 maiņa aplēsi uzpūta no 2,19 uz 4,35 €).
    $rakstz = (int)$cache->query("SELECT COALESCE(SUM(LENGTH(avots)),0) FROM tulkojumi
        WHERE versija = " . $cache->quote(GR_TULK_VERSIJA) . "
          AND lv IS NULL AND neizdevas < " . GR_TULK_MAX_KLUMES)->fetchColumn();
    // Mērītas konstantes (sk. granti/lib/tulkojumi.php galvu): 5,00 rakstz. uz ievades tokenu,
    // 364 izvades tokeni uz 1000 rakstzīmēm; uzvednes pieskaitījums pa veidiem.
    $paVeidiem = $cache->query("SELECT veids, COUNT(*) FROM tulkojumi
        WHERE versija = " . $cache->quote(GR_TULK_VERSIJA) . "
          AND lv IS NULL AND neizdevas < " . GR_TULK_MAX_KLUMES . " GROUP BY veids")->fetchAll(PDO::FETCH_KEY_PAIR);
    $uzvTok = 0;
    foreach (GR_TULK_UZVEDNES_TOKENI as $veids => $tok) $uzvTok += (int)($paVeidiem[$veids] ?? 0) * $tok;
    $inTok  = (int)round($rakstz / 5.0) + $uzvTok;
    $outTok = (int)round($rakstz / 1000 * 364);
    printf("Keša versija %s, uzvedne %s. Rindā %d tekstu (%s rakstz.), gatavi %d, atmesti pēc kļūmēm %d.\n",
        GR_TULK_VERSIJA, GR_TULK_UZVEDNE, $r['rinda'], number_format($rakstz), $r['gatavi'], $r['atmesti']);
    // Jaukts korpuss ir apzināts (sk. GR_TULK_UZVEDNE): rāda, cik gatavo ir ar kuru uzvedni.
    $pa = [];
    foreach ($r['uzvednes'] as $u => $n) $pa[] = "$u: $n";
    if ($pa) printf("Gatavie pēc uzvednes versijas: %s.\n", implode(', ', $pa));
    if ($r['vecas'] > 0)
        printf("Vecāku versiju rindas: %d — tās netiek tulkotas; izmest ar --tiri-versijas.\n", $r['vecas']);
    printf("Aplēse: %s ievades + %s izvades tokenu = %.2f € tūlītējā, %.2f € batch cenā.\n",
        number_format($inTok), number_format($outTok),
        gr_tulk_cena($inTok, $outTok), gr_batch_cena($inTok, $outTok));
    printf("Šodien jau iztērēts %.3f € no %.2f € griestiem.\n", gr_tulk_diena_terets(), GR_TULK_DIENAS_EUR);

    $b = gr_batch_stats(gr_tulk_db());
    printf("Batch: lidojumā %d darbi, rezervēts %.3f €.\n", $b['lidojuma'], $b['rezerveta']);
    foreach ($b['pedejie'] as $x)
        printf("  %-46s %-22s pieprasījumi=%-4d ierakstīti=%-4d %.4f €\n",
            substr((string)$x['job'], 0, 46), (string)$x['state'], (int)$x['requests'], (int)$x['written'], (float)$x['eur']);
    exit(0);
}

$lim = isset($opts['limit']) ? max(0, (int)$opts['limit']) : 0;

// TIKAI savākšana: der cron otrajai palaišanai dienā, kad vakardienas darbs jau gatavs,
// un tas nekad nesūta neko jaunu, tātad nemaksā par jaunu darbu.
if (isset($opts['savac'])) {
    $r = gr_batch_collect(gr_tulk_db());
    gt_log(sprintf('Savākti %d teksti no %d darbiem (%.4f €).', $r['written'], $r['jobs'], $r['eur']));
    if ($r['written'] > 0) gr_tulk_aizpildi($grants, 'gt_log');
    exit(0);
}

if ($st['rinda'] === 0 && !isset($opts['tulits'])) {
    // Rinda tukša, bet lidojumā vēl var būt darbi — tos savācam.
    $r = gr_batch_collect(gr_tulk_db());
    if ($r['written'] > 0) {
        gt_log(sprintf('Savākti %d teksti no %d darbiem (%.4f €).', $r['written'], $r['jobs'], $r['eur']));
        gr_tulk_aizpildi($grants, 'gt_log');
    } else {
        gt_log('Rinda ir tukša — nekas nav jātulko.');
    }
    exit(0);
}
if ($st['rinda'] === 0) { gt_log('Rinda ir tukša — nekas nav jātulko.'); exit(0); }

if (isset($opts['tulits'])) {
    gt_log('Tūlītējais ceļš (pilna cena). Tulko ' . ($lim > 0 ? "līdz $lim" : 'visus ' . $st['rinda']) . ' tekstus...');
    $res = gr_tulk_darbs($lim, 'gt_log');
    gt_log(sprintf('Tulkoti %d, kļūmes %d, izmaksas %.3f €.', $res['tulkoti'], $res['klumes'], $res['eur']));
    if ($res['tulkoti'] > 0) gr_tulk_aizpildi($grants, 'gt_log');
} else {
    $gaidit = isset($opts['gaidit']) ? max(0, (int)$opts['gaidit']) : 900;
    gt_log('Batch ceļš (puse cenas). Rindā ' . $st['rinda'] . ' tekstu; gaidīšana ' . $gaidit . ' s.');
    $n = gr_batch_run(gr_tulk_db(), $gaidit, $lim > 0 ? $lim : null);
    gt_log("Batch ierakstīti $n teksti.");
    if ($n > 0) gr_tulk_aizpildi($grants, 'gt_log');
}

$r = gr_tulk_rindas_stats();
gt_log(sprintf('Atlicis rindā: %d (atmesti pēc %d kļūmēm: %d).', $r['rinda'], GR_TULK_MAX_KLUMES, $r['atmesti']));
