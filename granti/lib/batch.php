<?php
/**
 * granti/lib/batch.php — grantu tulkošana caur Gemini BATCH API (puse cenas).
 *
 * KĀPĒC BATCH. Tūlītējais ceļš (gr_tulk_darbs) sūta 4 paralēlus pieprasījumus un gaida.
 * Batch maksā PUSI (GR_BATCH_MULT) un neaizņem procesu: darbu iesniedz, tas nostrādā
 * Google pusē, un rezultātu savāc nākamā palaišana. Konkursu sadaļā tas pats solis deva
 * mērītus −55 % (sk. [[feat-konkursi-batch-konveijers]]), un šis modulis ir tā ports ar
 * trim atšķirībām, kas izriet no datiem:
 *   1) VIENS TEKSTS = VIENS PIEPRASĪJUMS. Konkursos vienā pieprasījumā brauc 40 virsraksti,
 *      tāpēc tur vajadzīga nobīžu atpazīšana (viens iztrūkstošs virsraksts pārbīda visus
 *      pārējos). Šeit tas viss atkrīt: atbilde attiecas uz vienu jaucējkodu.
 *   2) IZVADE IR HTML, ne JSON masīvs, tāpēc pārbaudes vārti ir gr_tulk_parbaudi_html() un
 *      gr_tulk_parbaudi_virsrakstu() — tie paši, ko lieto tūlītējais ceļš.
 *   3) GRANTU TEKSTI IR GARI (līdz GR_TULK_MAX_RAKSTZ = 60 000 rakstzīmju). Konkursu
 *      virsraksti ir īsi, tāpēc tur atbilde vienmēr atnāk iekļauta atbildē; šeit tā var
 *      atnākt ARĪ kā fails (metadata.output.responsesFile), un to šis modulis nolasa.
 *      Konkursu versija tādu darbu atzīmē par UNREADABLE un teksti aiziet atpakaļ rindā.
 *
 * NAUDAS DROŠĪBA (katrs punkts sedz vienu reālu zaudēšanas ceļu):
 *   · NODOMA RINDA PIRMS POST. Ja process nomirst starp POST un ierakstu, samaksāts darbs
 *     paliktu bez saimnieka un teksti tiktu sūtīti vēlreiz. Rinda ar stāvokli SUBMITTING
 *     rakstās PIRMS izsaukuma; saskaņošana to vēlāk atrod pēc display_name.
 *   · REZERVĒTĀ NAUDA. Dienas atlikums atskaita ne tikai jau samaksāto, bet arī lidojumā
 *     esošo darbu aplēses; bez tā divas iesniegšanas pēc kārtas abas redzētu pilnu budžetu.
 *   · TEKSTU SLĒDZENE. granti_batch_teksti rindas ir gan pozīciju karte, gan atzīme "šis
 *     jau lido"; atlases vaicājums tās izslēdz, tāpēc pārklājošas palaišanas nemaksā divreiz.
 *   · STĀVOKLIS PĒDĒJAIS. Atbrīvošana raksta darba stāvokli transakcijas beigās: process,
 *     kas nomirst pusceļā, atstāj darbu neterminālu un nākamā savākšana to pabeidz.
 */
declare(strict_types=1);

require_once __DIR__ . '/tulkojumi.php';

/** Batch cena ir puse no tūlītējās (Google publicētā atlaide). */
const GR_BATCH_MULT = 0.5;
/**
 * Cik pieprasījumu vienā darbā un cik baitu ķermenī. Abi ir sargi, ne optimizācija:
 * viss korpuss (2124 teksti, 3,7 milj. rakstzīmju) vienā iekļautā ķermenī būtu ~8 MB
 * pirms uzvednēm un vairāk pēc tām, un neveiksme tur nozīmētu visu darbu no jauna.
 */
const GR_BATCH_MAX_REQ   = 200;
const GR_BATCH_MAX_BAITI = 18 * 1024 * 1024;
/** Google dokumentē 24 h; brīdinām pēc 26 h, atceļam pēc 30 h (tāpat kā konkursos). */
const GR_BATCH_WARN_H   = 26;
const GR_BATCH_CANCEL_H = 30;
/** Cik ilgi nodoma rinda drīkst palikt bez darba vārda, pirms to atbrīvo. */
const GR_BATCH_SUBMITTING_S = 1800;

/** Google galīgie stāvokļi + mūsu pašu. Tikai koda konstantes — tās nonāk SQL tieši. */
const GR_BATCH_TERMINAL = ['COLLECTED', 'BATCH_STATE_FAILED', 'BATCH_STATE_EXPIRED',
                           'BATCH_STATE_CANCELLED', 'UNREADABLE', 'RELEASED', 'REJECTED'];
const GR_BATCH_LIVE     = ['BATCH_STATE_PENDING', 'BATCH_STATE_RUNNING'];

/** Žurnāls: būvē iet caur gr_log() (fails + ekrāns), atsevišķi palaižot — uz ekrāna. */
function gr_batch_log(string $m): void {
    if (function_exists('gr_log')) { gr_log($m); return; }
    echo date('H:i:s') . '  ' . $m . "\n";
}

/**
 * Tabulas dzīvo TAJĀ PAŠĀ keša DB, kur tulkojumi — nevis atsevišķā failā. Tā darba rinda
 * un tās slēdzene ir vienā transakcijā: citādi "teksts iesniegts" un "teksts rindā" varētu
 * atšķirties, ja viens ieraksts izdodas un otrs ne.
 */
function gr_batch_schema(PDO $d): void {
    $d->exec("CREATE TABLE IF NOT EXISTS granti_batches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job TEXT UNIQUE,              -- Google resursa vārds; līdz atbildei 'pending:<display>'
        display_name TEXT,            -- vienīgais rokturis saskaņošanai, ja POST atbilde pazūd
        state TEXT NOT NULL,
        model TEXT, requests INTEGER DEFAULT 0, teksti INTEGER DEFAULT 0,
        written INTEGER DEFAULT 0,
        in_tokens INTEGER DEFAULT 0, out_tokens INTEGER DEFAULT 0,
        eur REAL DEFAULT 0, est_eur REAL DEFAULT 0,
        created TEXT, updated TEXT, note TEXT
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS granti_batch_teksti (
        batch_id INTEGER NOT NULL, poz INTEGER NOT NULL, hash TEXT NOT NULL,
        PRIMARY KEY (batch_id, poz)
    )");
    $d->exec("CREATE INDEX IF NOT EXISTS idx_gbt_hash ON granti_batch_teksti(hash)");
}

/** Slēdzene — SAVA, ne konkursu: citādi divas sadaļas bloķētu viena otru bez iemesla. */
function gr_batch_lock() {
    $p = granti_batch_lock_path();
    $f = @fopen($p, 'c');
    if ($f === false) { gr_batch_log("Slēdzeni nevar atvērt: $p"); return null; }
    if (!flock($f, LOCK_EX | LOCK_NB)) { fclose($f); gr_batch_log('Batch jau darbojas citā procesā — izlaists.'); return null; }
    return $f;
}

/**
 * Viens API izsaukums. Atgriež ['ok'=>bool,'code'=>int,'json'=>?array,'raw'=>string].
 *
 * ATDALĪTĀJS: ceļš var jau saturēt vaicājuma virkni ("batches?pageSize=100"), un
 * beznosacījuma '?key=' tur uztaisītu otru jautājuma zīmi. Konkursu versijā tas ir tieši
 * tā, un darbu saraksta izsaukums tur nevar nostrādāt — saskaņošana vienmēr aiziet pa
 * agrās atgriešanās zaru. Šeit atdalītāju izvēlas pēc ceļa.
 *
 * 'ok' prasa VISUS trīs: HTTP 200, tukšu curl kļūdu UN parsējamu JSON. Atbilde, kas
 * pārtrūkst pusceļā, atdod kodu 200 ar bojātu ķermeni.
 */
function gr_batch_api(string $path, ?array $body = null, int $timeout = 300, string $method = ''): array {
    $sep = str_contains($path, '?') ? '&' : '?';
    $url = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($path, '/')
         . $sep . 'key=' . urlencode(reg_gemini_key());
    $ch = curl_init($url);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 30,
          CURLOPT_HTTPHEADER => ['Content-Type: application/json']];
    if ($body !== null) {
        $enc = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($enc === false) return ['ok' => false, 'code' => 0, 'json' => null, 'raw' => 'json_encode: ' . json_last_error_msg()];
        $o[CURLOPT_POST] = true;
        $o[CURLOPT_POSTFIELDS] = $enc;
    }
    if ($method !== '') $o[CURLOPT_CUSTOMREQUEST] = $method;
    curl_setopt_array($ch, $o);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    // curl_close() nav: kopš PHP 8.0 tas neko nedara, un 8.5 par katru izsaukumu izdod
    // deprecācijas brīdinājumu — 40 rindas cron žurnālā uz vienu darbu.
    $json = is_string($raw) ? json_decode($raw, true) : null;
    return ['ok' => $code === 200 && $cerr === '' && is_array($json),
            'code' => $code, 'json' => is_array($json) ? $json : null,
            'raw' => $cerr !== '' ? "curl: $cerr" : (string)$raw];
}

/** Batch cenas ($/1M) — tūlītējās reiz reizinātājs. */
function gr_batch_cena(int $in, int $out): float {
    return gr_tulk_cena($in, $out) * GR_BATCH_MULT;
}

/**
 * Aplēse pirms iesniegšanas, ar MĒRĪTAJĀM konstantēm (sk. granti/lib/tulkojumi.php galvu):
 * 5,00 rakstzīmes uz ievades tokenu, 364 izvades tokeni uz 1000 rakstzīmēm, uzvednes
 * pieskaitījums pa veidiem. Aplēse tiek turēta rezervētā budžetā, kamēr darbs lido.
 */
function gr_batch_aplese(array $rindas): float {
    $rakstz = 0; $uzv = 0;
    foreach ($rindas as $r) {
        $rakstz += strlen((string)$r['avots']);
        $uzv += GR_TULK_UZVEDNES_TOKENI[$r['veids']] ?? GR_TULK_UZVEDNES_TOKENI['html'];
    }
    $in  = (int)round($rakstz / 5.0) + $uzv;
    $out = (int)round($rakstz / 1000 * 364);
    return gr_batch_cena($in, $out);
}

/** SQL virkne terminālajiem stāvokļiem (tikai koda konstantes — nekad izpildlaika vērtības). */
function gr_batch_terminal_sql(): string { return "'" . implode("','", GR_BATCH_TERMINAL) . "'"; }

/** Cik naudas lidojumā (aplēses par darbiem, kas vēl nav pabeigti). */
function gr_batch_lidojuma(PDO $d): float {
    return (float)$d->query("SELECT COALESCE(SUM(est_eur),0) FROM granti_batches
                             WHERE state NOT IN (" . gr_batch_terminal_sql() . ")")->fetchColumn();
}

/**
 * Atbrīvo darba tekstus atpakaļ rindā un pieraksta darbam galīgo stāvokli.
 *
 * $bump — palielina neizdevas skaitītāju (teksts, kas trīs reizes nav atnācis, paliek
 * angliski, nevis mūžīgi maksā). $chargeEstimate — pieskaita aplēsi dienas tēriņam:
 * neizdevies darbs Google pusē var būt daļēji izpildīts un apmaksāts, tāpēc labāk
 * pārskaitīt nekā nedaskaitīt. Stāvoklis rakstās PĒDĒJAIS, vienā transakcijā.
 */
function gr_batch_release(PDO $d, int $bid, string $state, ?string $note = null,
                          bool $bump = false, bool $chargeEstimate = false): void {
    $own = !$d->inTransaction();
    if ($own) $d->beginTransaction();
    try {
        $hashes = $d->query("SELECT hash FROM granti_batch_teksti WHERE batch_id = " . (int)$bid)
                    ->fetchAll(PDO::FETCH_COLUMN);
        if ($bump && $hashes) {
            $u = $d->prepare("UPDATE tulkojumi SET neizdevas = neizdevas + 1, kluda = ? WHERE hash = ?");
            // Datums kļūdas tekstā: tulko.php pēc 7 dienām šādas (darba līmeņa, ne teksta
            // vainas) kļūmes atiestata, lai nedēļa ar Google sastrēgumu neiesaldētu tekstus mūžam.
            foreach ($hashes as $h) $u->execute(['batch ' . date('Y-m-d') . ': ' . ($note ?? $state), $h]);
        }
        $est = 0.0;
        if ($chargeEstimate) {
            $est = (float)$d->query("SELECT COALESCE(est_eur,0) FROM granti_batches WHERE id = " . (int)$bid)->fetchColumn();
        }
        $d->prepare("DELETE FROM granti_batch_teksti WHERE batch_id = ?")->execute([$bid]);
        $d->prepare("UPDATE granti_batches SET state = ?, note = ?, updated = ? WHERE id = ?")
          ->execute([$state, $note, date('c'), $bid]);
        if ($own) $d->commit();
        if ($est > 0) gr_tulk_diena_pieskaiti($est);
    } catch (Throwable $e) {
        if ($own && $d->inTransaction()) $d->rollBack();
        throw $e;
    }
}

/**
 * Iesniedz vienu darbu. Atgriež ['job'=>?string,'requests'=>int,'teksti'=>int,'why'=>string].
 * $max ierobežo tekstu skaitu (0 vai null = cik ietilpst griestos).
 */
function gr_batch_submit(PDO $d, ?int $max = null): array {
    gr_batch_schema($d);
    if (reg_gemini_key() === '') return ['job' => null, 'requests' => 0, 'teksti' => 0, 'why' => 'nav Gemini atslēgas'];
    if (reg_gemini_model() !== GR_TULK_MODELIS) {
        gr_batch_log('  ⚠ modelis ' . reg_gemini_model() . ', bet cenu konstantes ir par ' . GR_TULK_MODELIS . '.');
    }

    // Atlase. NOT IN (batch teksti) ir lidojuma slēdzene — bez tās pārklājošas palaišanas
    // sūtītu un maksātu par vienu tekstu divas reizes.
    $lim = ($max !== null && $max > 0) ? min($max, GR_BATCH_MAX_REQ) : GR_BATCH_MAX_REQ;
    $q = $d->prepare("SELECT hash, veids, lauks, avots FROM tulkojumi
                      WHERE versija = ? AND lv IS NULL AND neizdevas < ? AND LENGTH(avots) <= ?
                        AND hash NOT IN (SELECT hash FROM granti_batch_teksti)
                      ORDER BY neizdevas, LENGTH(avots) LIMIT $lim");
    // PARAM_INT abiem skaitļiem: LENGTH() ir izteiksme bez kolonnas afinitātes, un TEXT
    // parametrs tur vienmēr salīdzinās kā lielāks — vārts klusi nenostrādātu.
    $q->bindValue(1, GR_TULK_VERSIJA, PDO::PARAM_STR);
    $q->bindValue(2, GR_TULK_MAX_KLUMES, PDO::PARAM_INT);
    $q->bindValue(3, GR_TULK_MAX_RAKSTZ, PDO::PARAM_INT);
    $q->execute();
    $rindas = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$rindas) return ['job' => null, 'requests' => 0, 'teksti' => 0, 'why' => 'rinda tukša'];

    // Budžets: jau iztērētais PLUS lidojumā esošo aplēses.
    $atlikums = GR_TULK_DIENAS_EUR - gr_tulk_diena_terets() - gr_batch_lidojuma($d);
    if ($atlikums <= 0) return ['job' => null, 'requests' => 0, 'teksti' => 0, 'why' => 'dienas budžets izsmelts'];
    while ($rindas && gr_batch_aplese($rindas) > $atlikums) array_pop($rindas);
    if (!$rindas) return ['job' => null, 'requests' => 0, 'teksti' => 0, 'why' => 'budžetā neietilpst neviens teksts'];

    // Pieprasījumi. Viens teksts = viens pieprasījums; atslēga OBLIGĀTI 'metadata' iekšpusē
    // (blakus 'request' liktā atslēga dod HTTP 400 — mērīts konkursos 2026-09-03).
    $gen = reg_gemini_gen_config(reg_gemini_thinking_min() + ['temperature' => 0.1, 'maxOutputTokens' => 32768]);
    $requests = []; $izmantotie = []; $baiti = 0;
    foreach ($rindas as $r) {
        $teksts = (string)$r['avots'];
        // Nederīgs baits klusi salauztu json_encode visam ķermenim; konkursos tieši tas
        // uzvednei nogrieza visus virsrakstus, un modelis 40 tulkojumus izdomāja.
        if (!mb_check_encoding($teksts, 'UTF-8')) {
            $d->prepare("UPDATE tulkojumi SET neizdevas = neizdevas + 1, kluda = ? WHERE hash = ?")
              ->execute(['nederīgs UTF-8 avotā', $r['hash']]);
            continue;
        }
        $uzvedne = $r['veids'] === 'virsraksts'
            ? gr_tulk_uzvedne_virsraksts($teksts)
            : gr_tulk_uzvedne_html($teksts);
        $baiti += strlen($uzvedne) + 256;
        if ($requests && $baiti > GR_BATCH_MAX_BAITI) break;   // pārējie aizies nākamajā darbā
        $requests[] = ['request' => ['contents' => [['parts' => [['text' => $uzvedne]]]],
                                     'generationConfig' => $gen],
                       'metadata' => ['key' => 'c' . count($requests)]];
        $izmantotie[] = $r;
    }
    if (!$requests) return ['job' => null, 'requests' => 0, 'teksti' => 0, 'why' => 'nav derīgu tekstu'];

    $display = 'granti-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8);
    $est = gr_batch_aplese($izmantotie);

    // NODOMA RINDA PIRMS POST — sk. faila galvu.
    $d->beginTransaction();
    $d->prepare("INSERT INTO granti_batches (job, display_name, state, model, requests, teksti, created, updated, est_eur)
                 VALUES (?,?,'SUBMITTING',?,?,?,?,?,?)")
      ->execute(['pending:' . $display, $display, reg_gemini_model(),
                 count($requests), count($izmantotie), date('c'), date('c'), $est]);
    $bid = (int)$d->lastInsertId();
    $ins = $d->prepare("INSERT OR IGNORE INTO granti_batch_teksti (batch_id, poz, hash) VALUES (?,?,?)");
    foreach ($izmantotie as $i => $r) $ins->execute([$bid, $i, $r['hash']]);
    $d->commit();

    $res = gr_batch_api('models/' . reg_gemini_model() . ':batchGenerateContent',
        ['batch' => ['display_name' => $display, 'input_config' => ['requests' => ['requests' => $requests]]]], 300);

    if ($res['ok'] && !empty($res['json']['name'])) {
        $job = (string)$res['json']['name'];
        $st  = (string)($res['json']['metadata']['state'] ?? 'BATCH_STATE_PENDING');
        // Žurnālā PIRMS DB: ja process nomirst tieši šeit, darba vārds vismaz ir pierakstīts.
        gr_batch_log("  Batch iesniegts: $job ($st, " . count($requests) . " pieprasījumi, aplēse "
            . number_format($est, 3) . " €)");
        $d->prepare("UPDATE granti_batches SET job = ?, state = ?, updated = ? WHERE id = ?")
          ->execute([$job, $st, date('c'), $bid]);
        return ['job' => $job, 'requests' => count($requests), 'teksti' => count($izmantotie), 'why' => ''];
    }
    if ($res['code'] >= 400) {
        gr_batch_release($d, $bid, 'REJECTED', 'HTTP ' . $res['code'] . ': ' . substr($res['raw'], 0, 160));
        gr_batch_log('  Batch noraidīts: HTTP ' . $res['code'] . ' ' . substr($res['raw'], 0, 160));
        return ['job' => null, 'requests' => 0, 'teksti' => 0, 'why' => 'HTTP ' . $res['code']];
    }
    // Noildze vai tīkls: rindu ATSTĀJAM stāvoklī SUBMITTING. Darbs Google pusē var būt
    // izveidots; saskaņošana to atradīs pēc display_name.
    gr_batch_log('  Batch atbilde nesaņemta (' . substr($res['raw'], 0, 120) . ') — rinda paliek saskaņošanai.');
    return ['job' => null, 'requests' => 0, 'teksti' => 0, 'why' => 'atbilde nesaņemta'];
}

/**
 * Saskaņošana: atrod Google pusē darbus, kuru POST atbilde pie mums nenonāca, un
 * pieņem tos pēc display_name. Ko neatrod un kas vecāks par GR_BATCH_SUBMITTING_S — atbrīvo.
 */
function gr_batch_reconcile(PDO $d): void {
    $rindas = $d->query("SELECT id, display_name, created FROM granti_batches WHERE state = 'SUBMITTING'")
                ->fetchAll(PDO::FETCH_ASSOC);
    if (!$rindas) return;
    // VISAS LAPAS, ne tikai pirmā. Konts ir kopīgs ar konkursu sadaļu, tāpēc 100 darbu
    // logā mūsu vakardienas darbs var vairs nebūt — un "nav sarakstā" te nozīmē neatgriezenisku
    // atbrīvošanu. Griesti 20 lapām, lai bojāts nextPageToken nekļūtu par bezgalīgu cilpu.
    $pec = []; $lapa = 0; $token = '';
    do {
        $cels = 'batches?pageSize=100' . ($token !== '' ? '&pageToken=' . urlencode($token) : '');
        $res = gr_batch_api($cels, null, 60);
        if (!$res['ok']) {
            // Saraksts nav pieejams. SUBMITTING rinda tur rezervētu budžetu un slēdz tekstus, kamēr
            // nav termināla; ja saraksts nestrādā ilgstoši (atslēgas ierobežojums, kvota, galapunkta
            // maiņa), rinda paliktu mūžīgi un dienas budžets būtu nulle uz visiem laikiem. Pēc 24 h
            // atbrīvo: aplēsi pieskaita (darbs Google pusē var būt bijis un apmaksāts), kļūmi
            // tekstiem NEskaita (tā nav to vaina).
            gr_batch_log('  Darbu sarakstu no API nolasīt neizdevās — saskaņošana izlaista.');
            // Tikai ja jau PIRMĀ lapa neizdevās: ja nokrita 2. lapa, 1. lapā redzētie darbi ir
            // dzīvi, un atbrīvot tos nozīmētu maksāt par tiem otrreiz.
            foreach ($lapa === 0 ? $rindas : [] as $r) {
                if (time() - strtotime((string)$r['created']) > 86400) {
                    gr_batch_release($d, (int)$r['id'], 'RELEASED', 'saraksts nepieejams > 24 h', false, true);
                    gr_batch_log('  Atbrīvots bez saskaņošanas (> 24 h): ' . $r['display_name']);
                }
            }
            return;
        }
        foreach (($res['json']['operations'] ?? []) as $op) {
            $dn = $op['metadata']['displayName'] ?? $op['metadata']['display_name'] ?? '';
            if ($dn !== '') $pec[$dn] = $op;
        }
        $token = (string)($res['json']['nextPageToken'] ?? '');
    } while ($token !== '' && ++$lapa < 20);
    foreach ($rindas as $r) {
        $dn = (string)$r['display_name'];
        if (isset($pec[$dn])) {
            $op = $pec[$dn];
            $d->prepare("UPDATE granti_batches SET job = ?, state = ?, note = ?, updated = ? WHERE id = ?")
              ->execute([(string)$op['name'], (string)($op['metadata']['state'] ?? 'BATCH_STATE_PENDING'),
                         'pārņemts pēc saskaņošanas', date('c'), (int)$r['id']]);
            gr_batch_log("  Saskaņots: $dn -> " . $op['name']);
            continue;
        }
        if (time() - strtotime((string)$r['created']) > GR_BATCH_SUBMITTING_S) {
            gr_batch_release($d, (int)$r['id'], 'RELEASED', 'darbs Google pusē neatradās');
            gr_batch_log("  Atbrīvots (nav atrasts): $dn");
        }
    }
}

/**
 * Rezultāta faila nolasīšana. Batch atbilde grantiem var atnākt kā fails, jo sadaļu
 * teksti ir gari — konkursu virsrakstiem tas nekad nenotiek, tāpēc tur šī zara nav.
 * Formāts: JSONL, katrā rindā atbilde ar atslēgu 'key' (saknē vai metadata iekšpusē).
 */
function gr_batch_faila_atbildes(string $fileName): array {
    $url = 'https://generativelanguage.googleapis.com/download/v1beta/' . ltrim($fileName, '/')
         . ':download?alt=media&key=' . urlencode(reg_gemini_key());
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300,
                            CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // ATŠĶIR PĀREJOŠO NO GALĪGĀ. 404 nozīmē, ka faila nav un nebūs; 500, 503, noildze vai
    // tīkla kļūme nozīmē "pamēģini vēlāk". Iepriekš abi atdeva null, un saucējs apmaksātu
    // darbu atzīmēja par UNREADABLE — rezultāts palika Google pusē, nauda samaksāta, un
    // tie paši teksti tika pirkti otrreiz.
    if ($code !== 200 || !is_string($raw) || $raw === '') {
        return ['galigs' => $code === 404, 'atbildes' => null, 'kods' => $code];
    }
    $out = [];
    foreach (preg_split('~\R~', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $j = json_decode($line, true);
        if (!is_array($j)) continue;
        $key = $j['key'] ?? $j['metadata']['key'] ?? null;
        if ($key === null) continue;
        $out[] = ['metadata' => ['key' => (string)$key],
                  'response' => $j['response'] ?? null,
                  'error'    => $j['error'] ?? null];
    }
    // Tukšs vai neparsējams fails ir GALĪGA vaina: atkārtota lejupielāde to nemainīs.
    return ['galigs' => true, 'atbildes' => $out ?: null, 'kods' => $code];
}

/** Viena darba savākšana. Atgriež [ierakstīti, eur]. */
function gr_batch_collect_one(PDO $d, array $row): array {
    $bid = (int)$row['id'];
    $job = (string)$row['job'];
    $vecums = time() - strtotime((string)$row['created']);

    $res = gr_batch_api($job, null, 120);
    if (!$res['ok']) {
        // 404 vai INVALID_ARGUMENT = darba nav un nebūs; pārējais var būt pārejošs, bet
        // pēc 30 h to vairs negaidām. Abos gadījumos aplēse tiek pierakstīta izdevumos.
        $pazudis = $res['code'] === 404 || ($res['code'] === 400 && str_contains($res['raw'], 'INVALID_ARGUMENT'));
        if ($pazudis || $vecums > GR_BATCH_CANCEL_H * 3600) {
            gr_batch_release($d, $bid, 'RELEASED', 'API: HTTP ' . $res['code'], true, true);
            gr_batch_log("  Darbs $job nav sasniedzams (HTTP {$res['code']}) — teksti atbrīvoti.");
        }
        return [0, 0.0];
    }
    $state = (string)($res['json']['metadata']['state'] ?? '');

    if (in_array($state, GR_BATCH_LIVE, true)) {
        $d->prepare("UPDATE granti_batches SET state = ?, updated = ? WHERE id = ?")->execute([$state, date('c'), $bid]);
        if ($vecums > GR_BATCH_CANCEL_H * 3600) {
            gr_batch_api($job . ':cancel', [], 60);
            gr_batch_release($d, $bid, 'BATCH_STATE_CANCELLED', 'vecāks par ' . GR_BATCH_CANCEL_H . ' h', true, true);
            gr_batch_log("  Darbs $job atcelts (pārāk vecs).");
        } elseif ($vecums > GR_BATCH_WARN_H * 3600) {
            gr_batch_log("  ⚠ Darbs $job vēl $state pēc " . round($vecums / 3600) . " h.");
        }
        return [0, 0.0];
    }
    if ($state !== 'BATCH_STATE_SUCCEEDED') {
        gr_batch_release($d, $bid, $state !== '' ? $state : 'RELEASED', 'API stāvoklis: ' . $state, true, true);
        gr_batch_log("  Darbs $job beidzās ar $state — teksti atbrīvoti.");
        return [0, 0.0];
    }

    $out = $res['json']['metadata']['output'] ?? [];
    $atbildes = $out['inlinedResponses']['inlinedResponses'] ?? null;
    if (!is_array($atbildes)) {
        $fails = $out['responsesFile'] ?? '';
        if ($fails !== '') {
            $fr = gr_batch_faila_atbildes((string)$fails);
            $atbildes = $fr['atbildes'];
            if ($atbildes === null) {
                if (!$fr['galigs'] && $vecums <= GR_BATCH_CANCEL_H * 3600) {
                    // Pārejoša kļūme: rindu ATSTĀJAM, nākamā savākšana mēģinās vēlreiz.
                    gr_batch_log("  Darbs $job: rezultāta faila lejupielāde neizdevās (HTTP {$fr['kods']}) — mēģinās vēlreiz.");
                    return [0, 0.0];
                }
                gr_batch_release($d, $bid, 'UNREADABLE', 'responsesFile nenolasījās (HTTP ' . $fr['kods'] . '): ' . $fails, true, true);
                gr_batch_log("  Darbs $job: rezultāta failu $fails nolasīt neizdevās galīgi.");
                return [0, 0.0];
            }
            gr_batch_log('  Rezultāts atnāca kā fails (' . count($atbildes) . ' atbildes).');
        } else {
            gr_batch_release($d, $bid, 'UNREADABLE', 'nav ne inlinedResponses, ne responsesFile', true, true);
            return [0, 0.0];
        }
    }

    $karte = $d->prepare("SELECT poz, hash FROM granti_batch_teksti WHERE batch_id = ?");
    $karte->execute([$bid]);
    $pozHash = $karte->fetchAll(PDO::FETCH_KEY_PAIR);

    $inTok = 0; $outTok = 0; $ok = 0; $slikti = []; $redzeti = [];
    $veidi = [];
    if ($pozHash) {
        $ph = implode(',', array_fill(0, count($pozHash), '?'));
        $st = $d->prepare("SELECT hash, veids, lauks, avots FROM tulkojumi WHERE hash IN ($ph)");
        $st->execute(array_values($pozHash));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $veidi[$r['hash']] = $r;
    }

    $rakstiSt = $d->prepare("UPDATE tulkojumi SET lv = ?, modelis = ?, iztulkots = ?, kluda = NULL,
                             uzvedne = ?, neizdevas = 0 WHERE hash = ? AND lv IS NULL");
    $d->beginTransaction();
    try {
        foreach ($atbildes as $resp) {
            $key = (string)($resp['metadata']['key'] ?? '');
            if (!preg_match('/^c(\d+)$/', $key, $m)) continue;
            $poz = (int)$m[1];
            $hash = $pozHash[$poz] ?? null;
            if ($hash === null || !isset($veidi[$hash])) continue;
            $redzeti[$hash] = true;

            $u = $resp['response']['usageMetadata'] ?? [];
            $inTok  += (int)($u['promptTokenCount'] ?? 0);
            $outTok += (int)($u['candidatesTokenCount'] ?? 0) + (int)($u['thoughtsTokenCount'] ?? 0);

            $fin = (string)($resp['response']['candidates'][0]['finishReason'] ?? '');
            if (isset($resp['error']) && $resp['error'] !== null) { $slikti[$hash] = 'batch kļūda'; continue; }
            if ($fin !== '' && $fin !== 'STOP') { $slikti[$hash] = 'finishReason ' . $fin; continue; }
            $teksts = (string)($resp['response']['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if ($teksts === '') { $slikti[$hash] = 'tukša atbilde'; continue; }

            $r = $veidi[$hash];
            [$labi, $tirs, $piez] = $r['veids'] === 'virsraksts'
                ? gr_tulk_parbaudi_virsrakstu((string)$r['avots'], $teksts)
                : gr_tulk_parbaudi_html((string)$r['avots'], $teksts);
            if ($labi) {
                $rakstiSt->execute([$tirs, reg_gemini_model(), date('c'), GR_TULK_UZVEDNE, $hash]);
                $ok++;
            } else {
                $slikti[$hash] = $piez;
            }
        }
        // NEATNĀKUŠĀS ATBILDES. Cilpa iet pār to, KAS ATNĀCA; ja Google atdod mazāk atbilžu,
        // nekā bija pieprasījumu (vai faila rindas neparsējas), tie jaucējkodi cilpā nemaz
        // nenonāk. Zemāk tiek atbrīvotas VISAS šī darba rindas, tāpēc bez šī soļa tādi teksti
        // atgrieztos rindā ar neizdevas=0 un tiktu pirkti no jauna KATRĀ palaišanā — vienīgā
        // kļūmes klase modulī bez griestiem, jo GR_TULK_MAX_KLUMES nekad nesāktu skaitīt.
        foreach ($pozHash as $poz => $h) {
            if (!isset($redzeti[$h]) && !isset($slikti[$h])) $slikti[$h] = 'atbilde neatnāca';
        }
        // Kļūmju skaitītāji vienā vietā beigās: avārija pusceļā nedrīkst tos uzskaitīt divreiz.
        if ($slikti) {
            $u = $d->prepare("UPDATE tulkojumi SET neizdevas = neizdevas + 1, kluda = ? WHERE hash = ?");
            foreach ($slikti as $h => $piez) $u->execute([$piez, $h]);
        }
        $eur = gr_batch_cena($inTok, $outTok);
        $d->prepare("DELETE FROM granti_batch_teksti WHERE batch_id = ?")->execute([$bid]);
        $d->prepare("UPDATE granti_batches SET state='COLLECTED', written=?, in_tokens=?, out_tokens=?,
                     eur=?, note=?, updated=? WHERE id=?")
          ->execute([$ok, $inTok, $outTok, $eur, count($slikti) . ' noraidīti', date('c'), $bid]);
        // Tēriņš IEKŠ tās pašas transakcijas, kas darbu noslēdz. Ārpus tās avārija starp
        // commit un pieskaitīšanu atstātu darbu par pabeigtu, bet naudu nepierakstītu —
        // dienas griesti tad rādītu mazāk, nekā patiesībā samaksāts, un atļautu vēl vienu darbu.
        gr_tulk_diena_pieskaiti($eur);
        $d->commit();
    } catch (Throwable $e) {
        if ($d->inTransaction()) $d->rollBack();
        throw $e;
    }
    gr_batch_log(sprintf('  Savākts %s: ierakstīti %d, noraidīti %d, %.4f €.', $job, $ok, count($slikti), $eur));
    return [$ok, $eur];
}

/**
 * Savāc visus nepabeigtos darbus. Atgriež ['written'=>int,'jobs'=>int,'eur'=>float].
 *
 * $arSledzeni=false padod TIKAI gr_batch_run(), kas slēdzeni jau tur. Visiem pārējiem
 * saucējiem tā ir obligāta: divas paralēlas savākšanas tulkojumu gan nepārrakstītu
 * (UPDATE ... WHERE lv IS NULL), BET katra pieskaitītu tos pašus tokenus dienas tēriņam,
 * un budžeta sargs sāktu rādīt divreiz lielāku skaitli, nekā patiesībā samaksāts.
 */
function gr_batch_collect(PDO $d, bool $arSledzeni = true): array {
    $lock = null;
    if ($arSledzeni) {
        $lock = gr_batch_lock();
        if ($lock === null) return ['written' => 0, 'jobs' => 0, 'eur' => 0.0];
    }
    try {
        return gr_batch_collect_iekseji($d);
    } finally {
        if ($lock !== null) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

function gr_batch_collect_iekseji(PDO $d): array {
    gr_batch_schema($d);
    // Bāreņi: tekstu rindas, kuru darbs jau ir galīgs vai izdzēsts, citādi bloķētu atlasi mūžīgi.
    $d->exec("DELETE FROM granti_batch_teksti WHERE batch_id NOT IN
              (SELECT id FROM granti_batches WHERE state NOT IN (" . gr_batch_terminal_sql() . "))");
    gr_batch_reconcile($d);
    $rindas = $d->query("SELECT id, job, created FROM granti_batches
                         WHERE state NOT IN (" . gr_batch_terminal_sql() . ") AND state <> 'SUBMITTING'
                         ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $w = 0; $eur = 0.0; $n = 0;
    foreach ($rindas as $r) {
        try {
            [$a, $b] = gr_batch_collect_one($d, $r);
            $w += $a; $eur += $b; $n++;
        } catch (Throwable $e) {
            // Viens bojāts darbs nedrīkst apturēt pārējos.
            gr_batch_log('  ! darbs ' . $r['job'] . ': ' . $e->getMessage());
        }
    }
    return ['written' => $w, 'jobs' => $n, 'eur' => $eur];
}

/**
 * Pilnais cikls: savāc -> iesniedz -> gaidi -> savāc. $wait sekundes ir cik ilgi gaidīt
 * pirmā darba pabeigšanu (mērīts konkursos: 100-216 pieprasījumi ~2-4 min).
 */
function gr_batch_run(PDO $d, int $wait = 900, ?int $max = null): int {
    $lock = gr_batch_lock();
    if ($lock === null) return 0;
    try {
        $r1 = gr_batch_collect($d, false);
        if ($r1['written'] > 0) gr_batch_log(sprintf('Savākti %d teksti no %d darbiem (%.4f €).', $r1['written'], $r1['jobs'], $r1['eur']));

        $iesniegti = 0;
        while (true) {
            // ATLIKUŠAIS, ne kopējais. Ar $max katram izsaukumam operatora "--limit=250"
            // pirmajā reizē paņēma 200 un otrajā vēl 200 — 400 tekstu 250 vietā.
            $atlicis = ($max !== null && $max > 0) ? $max - $iesniegti : null;
            if ($atlicis !== null && $atlicis <= 0) break;
            $s = gr_batch_submit($d, $atlicis);
            if ($s['job'] === null) { if ($iesniegti === 0) gr_batch_log('Iesniegšana: ' . $s['why'] . '.'); break; }
            $iesniegti += $s['teksti'];
        }
        if ($iesniegti === 0) return $r1['written'];

        $cilpa = 0;
        $lidz = time() + max(0, $wait);
        while (time() < $lidz) {
            sleep(15);
            $vel = (int)$d->query("SELECT COUNT(*) FROM granti_batches
                                   WHERE state IN ('" . implode("','", GR_BATCH_LIVE) . "','SUBMITTING')")->fetchColumn();
            if ($vel === 0) break;
            $r = gr_batch_collect($d, false);
            $cilpa += $r['written'];
            if ($r['written'] > 0) gr_batch_log(sprintf('Savākti %d teksti (%.4f €).', $r['written'], $r['eur']));
        }
        $r2 = gr_batch_collect($d, false);
        return $r1['written'] + $cilpa + $r2['written'];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Pārskats panelim un --statuss izdrukai. */
function gr_batch_stats(PDO $d): array {
    gr_batch_schema($d);
    return [
        'lidojuma'  => (int)$d->query("SELECT COUNT(*) FROM granti_batches WHERE state NOT IN (" . gr_batch_terminal_sql() . ")")->fetchColumn(),
        'rezerveta' => gr_batch_lidojuma($d),
        'pedejie'   => $d->query("SELECT job, state, requests, written, eur, created, note
                                  FROM granti_batches ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC),
    ];
}
