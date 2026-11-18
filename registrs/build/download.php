<?php
// server/build/download.php — CSV lejupielāde no data.gov.lv (ports of "0 download data.py").
// Saudzīgs režīms: pauze starp failiem + atkārtojumi ar pieaugošu gaidīšanu, ja neizdodas.
// Vides mainīgie: REG_DL_DELAY_S (pauze starp failiem, noklusējums 2 s),
//                 REG_DL_ATTEMPTS (mēģinājumi failam, noklusējums 3),
//                 REG_DL_RETRY_S (bāzes gaidīšana pirms atkārtojuma, noklusējums 15 s),
//                 REG_DL_BUDGET_S (visas lejupielādes laika budžets, noklusējums 1500 s; 0 = bez budžeta),
//                 REG_DL_HOST_FAILS (pēc cik failiem pēc kārtas ar tīkla kļūmi saimnieku atmet, noklusējums 3; 0 = nekad),
//                 REG_DL_HOST_PROBE_S (cik bieži atmestam saimniekam dot vienu pārbaudes mēģinājumu, noklusējums 300 s; 0 = nedot).
//
// KĀPĒC BUDŽETS UN DROŠINĀTĀJS (2026-10-22 nakts būve). data.gov.lv no 02:00 neatbildēja
// (savienojuma taimauts 30 s), un katrs no 69 tā failiem tērēja 135 s: 3 mēģinājumi + 15 s + 30 s
// gaidīšana — kopā ~2,6 h. Ap 03:00 (1 h pēc cron starta) Hostinger nokāva visu procesu koku
// 32. failā: bez kļūdas rindas, build_state.json palika "running", dzīvie dati — vakardienas.
// Cron palaisti procesi serverī dzīvo crond.service cgroup; neviens no tiem žurnālu 88 dienās
// nav pārdzīvojis stundu, bet ar roku palaistie gāja 2 h 22 min. Tāpēc lejupielāde tagad
// nekad neaizņem vairāk par budžetu, un nesasniedzamu saimnieku atmet pēc 3 failiem.
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * curl kļūdas, kas nozīmē "saimnieks nav sasniedzams vai pārtrauc savienojumu", nevis "šī
 * resursa nav" — tikai tās (un HTTP 502/503/504) skaitās saimnieka drošinātājā (sk.
 * download_all_csvs). HTTP 4xx, 500 un atmests saturs ir konkrēta resursa jautājums: trīs pēc
 * kārtas pārvietotas vai salūzušas datu kopas nedrīkst atmest visu data.gov.lv.
 * 5/6 — nav DNS, 7 — nevar pieslēgties, 8 — dīvaina atbilde, 16/92 — HTTP/2 rāmis/plūsma,
 * 18 — fails pārtraukts, 28 — taimauts, 35 — TLS savienojums, 52 — tukša atbilde,
 * 55/56 — sūtīšanas/saņemšanas kļūda.
 */
const DL_TIKLA_KLUDAS = [5, 6, 7, 8, 16, 18, 28, 35, 52, 55, 56, 92];
const DL_TIKLA_HTTP = [502, 503, 504];

/**
 * Vesels skaitlis no vides; tukšs/neskaitlisks/negatīvs → noklusējums. Atšķirībā no `getenv() ?: x`
 * atļauj 0 (= izslēgts). Negatīvs nedrīkst klusi kļūt par 0 un izslēgt budžetu vai drošinātāju.
 */
function dl_env_int(string $nosaukums, int $nokl): int {
    $v = getenv($nosaukums);
    if ($v === false || !is_numeric(trim($v)) || (int)trim($v) < 0) return $nokl;
    return (int)trim($v);
}

/** Saimnieka atslēga drošinātājam: host[:ports], mazajiem burtiem. */
function dl_saimnieks(string $url): string {
    $h = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    $p = parse_url($url, PHP_URL_PORT);
    return $p ? "$h:$p" : $h;
}

/**
 * Riska/apsūdzību saraksti, kurus avots drīkst LIKUMĪGI iztukšot. Tukšas datu kopas sargs
 * (dl_saturs_derigs) tiem neattiecas: ja sankciju vai PTAC melnā saraksta kopa kļūst tukša,
 * sekojam avotam — novecojis apgalvojums par risku ("uzņēmums joprojām melnajā sarakstā")
 * ir sliktāks par iztrūkstošu (recenzija 2026-10-14). Lielos UR riska failus no pēkšņas
 * iztukšošanās jau sargā sarukuma likums (<5 % no ≥50 KB).
 */
const DL_TUKSUMS_ATLAUTS = [
    'sanctions', 'ptac_melnais_saraksts', 'ptac_lemumi', 'ptac_apnemsanas',
    'pdb_saimndarbibaaptureta_odata', 'suspensions_prohibitions', 'securing_measures',
    'insolvency_legal_person_proceeding', 'liquidations',
];

/**
 * Lejupielādē vienu URL uz failu (straumējot, ne atmiņā).
 * Raksta uz .part un atomiski pārsauc tikai pēc veiksmes — pusfails nekad neaizstāj veco.
 * @return bool true, ja izdevās (HTTP 200 un fails > 0 baiti)
 */
function download_one(string $url, string $dest_path, int $timeout = 300, int $connect_timeout = 30): bool {
    $tmp = $dest_path . '.part';
    $meginajums = static function (?string $cainfo) use ($url, $tmp, $timeout, $connect_timeout): array {
        $fp = @fopen($tmp, 'wb');
        if ($fp === false) return [false, 0, 'fopen neizdevās: ' . $tmp, '', 0];
        $ch = curl_init($url);
        $opt = [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connect_timeout,
            CURLOPT_FAILONERROR => true,           // HTTP >= 400 -> kļūda
            CURLOPT_USERAGENT => 'saraksts.lv data updater (PHP)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',                // ja serveris sūta gzip, curl atkodē — diskā vienmēr jēls CSV
        ];
        if ($cainfo !== null) $opt[CURLOPT_CAINFO] = $cainfo;
        curl_setopt_array($ch, $opt);
        $ok = curl_exec($ch);
        $no = curl_errno($ch);
        $err = curl_error($ch);
        $ctype = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch); // PHP >= 8.0 rokturi aizver automātiski (curl_close ir deprecated 8.5+)
        fclose($fp);
        return [$ok !== false, $no, $err, $ctype, $http];
    };
    $GLOBALS['__dl_kluda'] = '';
    $GLOBALS['__dl_galigs'] = false;   // true = atkārtot nav jēgas (sk. dl_saturs_derigs)
    $GLOBALS['__dl_tikls'] = false;    // true = saimnieks nesasniedzams/502–504 (sk. DL_TIKLA_KLUDAS)
    [$ok, $no, $err, $ctype, $http] = $meginajums(null);
    // 60/51 = vienaudža sertifikātu nevar verificēt ar zināmajām CA. Ja vietne sūta
    // nepilnu ķēdi (deminimis.fm.gov.lv bez Sectigo starpsertifikāta — 2026-08-22
    // visas 3 nakts būves mēģinājumi serverī krita), mēģinām vēlreiz ar sistēmas
    // CA + ca_extra.pem. Verifikācija paliek ieslēgta; tikai ķēde ir pilnāka.
    if (!$ok && in_array($no, [60, 51], true) && ($kopums = dl_ca_bundle()) !== null) {
        [$ok, $no, $err, $ctype, $http] = $meginajums($kopums);
    }

    if (!$ok || !is_file($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);
        $GLOBALS['__dl_tikls'] = !$ok && (in_array($no, DL_TIKLA_KLUDAS, true) || in_array($http, DL_TIKLA_HTTP, true));
        $GLOBALS['__dl_kluda'] = $err !== '' ? $err : 'tukšs fails';
        if ($err !== '') error_log("Lejupielādes kļūda $url: $err");
        return false;
    }
    // HTTP 200 vēl nenozīmē CSV: WAF/uzturēšanas/pieteikšanās lapa ar 200 agrāk
    // tika saglabāta kā .csv, konversija ielasīja nulli rindu, tabula pazuda, un
    // būve nokrita tikai 3.5 posmā (2026-08-19 nakts). Saturu pārbaudām PIRMS
    // vecā faila aizstāšanas — nederīgu atmetam, un paliek iepriekšējā versija.
    if (!dl_saturs_derigs($tmp, $dest_path, $ctype, $iemesls)) {
        @unlink($tmp);
        $GLOBALS['__dl_kluda'] = (string)$iemesls;
        error_log("Lejupielādes kļūda $url: $iemesls");
        return false;
    }
    // Atomiski aizvieto veco failu tikai pēc veiksmīgas lejupielādes
    if (!@rename($tmp, $dest_path)) {
        @unlink($tmp);
        $GLOBALS['__dl_kluda'] = 'neizdevās pārvietot uz ' . $dest_path;
        error_log("Lejupielādes kļūda $url: neizdevās pārvietot $tmp → $dest_path");
        return false;
    }
    return true;
}

/** Pēdējā download_one() atteikuma iemesls — žurnālam, lai "neizdevās" nav akls. */
function dl_pedeja_kluda(): string {
    return (string)($GLOBALS['__dl_kluda'] ?? '');
}

/**
 * Vai lejupielādētais saturs tiešām ir CSV, nevis kļūdas lapa ar HTTP 200.
 * Pārbauda: tukšums; HTML/XML/JSON sākums (pēc BOM un atstarpēm); CSV atdalītājs
 * pirmajā rindā (visiem avotiem ≥2 kolonnas); aizdomīgs sarukums pret iepriekšējo
 * versiju (<5 % no ≥50 KB faila — reģistri tā nesarūk, tā pazūd).
 * Sliekšņi apzināti piesardzīgi: mērķis ir atmest acīmredzamu nederību, ne
 * spriest par datu kvalitāti — to dara konversija un tabulu sarukuma pārbaude būvē.
 */
function dl_saturs_derigs(string $tmp, string $dest_path, string $content_type, ?string &$iemesls): bool {
    $iemesls = null;
    $n = filesize($tmp);
    if ($n === false || $n === 0) { $iemesls = 'tukšs fails'; return false; }
    $kl = dl_izskatas_pec_csv($tmp);
    if ($kl !== null) {
        $iemesls = $kl . ($content_type !== '' ? ", Content-Type: $content_type" : '');
        return false;
    }
    // Aizdomīgs sarukums — bet TIKAI pret iepriekšējo versiju, kas pati izskatās pēc
    // CSV: ja vecais fails ir pirms šī labojuma saglabāta HTML lapa, tas nedrīkst
    // mūžīgi bloķēt likumīgu mazu CSV (recenzija 2026-08-22).
    if (is_file($dest_path) && dl_izskatas_pec_csv($dest_path) === null) {
        $vec = filesize($dest_path);
        if ($vec !== false && $vec >= 50 * 1024 && $n < $vec * 0.05) {
            $iemesls = sprintf('aizdomīgi mazs: %s b pret %s b iepriekš (<5 %%) — paturam iepriekšējo; ja sarukums ir likumīgs, izdzēsiet veco failu',
                number_format($n, 0, ',', ' '), number_format($vec, 0, ',', ' '));
            return false;
        }
    }
    // Tukša datu kopa: derīga galvene, bet aiz tās tikai tukšas rindas ("","","","").
    // VID PVN grupu kopa (abi resursi) tāda ir kopš 2026-09-14 — HTTP 200, 117 baiti.
    // Sarukuma likums augšā to neķēra (vecais fails 20 KB < 50 KB), fails tika pieņemts,
    // un ~249 uzņēmumu lapās PVN grupa klusi pazuda (audits 2026-10-14). Ja iepriekšējā
    // versijā dati BIJA, paturam to. Atkārtot lejupielādi nav jēgas — avots atdos to pašu.
    if (!in_array(basename($dest_path, '.csv'), DL_TUKSUMS_ATLAUTS, true)
        && dl_ir_datu_rinda($tmp) === false && is_file($dest_path)
        && dl_izskatas_pec_csv($dest_path) === null && dl_ir_datu_rinda($dest_path) === true) {
        $iemesls = 'jaunajā failā nav nevienas aizpildītas datu rindas (tikai galvene) — paturam iepriekšējo; '
            . 'ja avots kopu tiešām iztukšojis, izdzēsiet veco failu';
        $GLOBALS['__dl_galigs'] = true;
        return false;
    }
    return true;
}

/**
 * Vai failā aiz galvenes ir vismaz viena rinda ar saturu — ne tikai atdalītāji, pēdiņas
 * un atstarpes. Lasa straumē un apstājas pie pirmās atrastās, tāpēc arī 200 MB failam
 * tas parasti ir pirmās rindas jautājums. null = failu nevar nolasīt.
 */
function dl_ir_datu_rinda(string $path): ?bool {
    $fh = @fopen($path, 'rb');
    if ($fh === false) return null;
    if (fgets($fh) === false) { fclose($fh); return false; }   // galvene (vai nekā)
    while (($l = fgets($fh)) !== false) {
        if (trim(str_replace(['"', ',', ';', "\t", "\xC2\xA0"], '', $l)) !== '') { fclose($fh); return true; }
    }
    fclose($fh);
    return false;
}

/**
 * null = izskatās pēc CSV; citādi — iemesls, kāpēc ne. Skatās pirmos 16 KB:
 *  - pēc BOM un atstarpēm nedrīkst būt tukšs (tikai-BOM ķermenis agrāk gāja cauri);
 *  - pirmais baits nedrīkst būt '<', '{' vai '[' — neviens no 78 reālajiem avotu
 *    failiem tā nesākas (visi sākas ar burtu vai pēdiņu), bet HTML/XML/JSON kļūdas
 *    lapas — vienmēr; fiksēts tagu saraksts te izlaida "<center><h1>503 …" un
 *    "<Error>Access denied" (recenzija 2026-08-22);
 *  - paraugā nedrīkst būt stiprie HTML marķieri (<!doctype, <html, <body) — arī
 *    aiz HTML komentāra vai pēc pirmās rindas;
 *  - pirmajā rindā jābūt CSV atdalītājam (; , vai tabulācija) — visiem avotiem ≥2 kolonnas.
 */
function dl_izskatas_pec_csv(string $path): ?string {
    $fh = @fopen($path, 'rb');
    if ($fh === false) return 'failu nevar nolasīt';
    $paraugs = (string)fread($fh, 16384);
    fclose($fh);
    if (str_starts_with($paraugs, "\xEF\xBB\xBF")) {
        $paraugs = substr($paraugs, 3);
    } elseif (str_starts_with($paraugs, "\xFF\xFE") || str_starts_with($paraugs, "\xFE\xFF")) {
        $dec = @iconv('UTF-16', 'UTF-8', $paraugs);
        if ($dec !== false) $paraugs = $dec;
    }
    $s = ltrim($paraugs, " \t\r\n\x0B\0");
    if ($s === '') return 'tukšs saturs (tikai BOM/atstarpes)';
    $c = $s[0];
    if ($c === '<') return "HTML/XML lapa .csv vietā (sākas ar '<')";
    if ($c === '{' || $c === '[') return 'JSON atbilde .csv vietā';
    $pirma = strtok($s, "\r\n");
    if ($pirma === false || !preg_match('/[;,\t]/', $pirma)) {
        return 'pirmajā rindā nav CSV atdalītāja (; , vai tabulācijas)';
    }
    // Stipros marķierus skatām TIKAI galvenes rindā: CSV galvenē '<html' nav nekad,
    // bet datu laukos (brīvteksts) teorētiski var būt — visā 16 KB paraugā tas dotu
    // viltus atmetumu (recenzija 2026-08-22). Lapas ar '<' sākumā jau atmestas augstāk.
    $pl = strtolower($pirma);
    foreach (['<!doctype', '<html', '<body'] as $tag) {
        if (str_contains($pl, $tag)) return "HTML lapa .csv vietā (galvenē '$tag')";
    }
    return null;
}

/**
 * Sistēmas CA kopums + ca_extra.pem vienā pagaidu failā (kešots procesa ietvaros).
 * null, ja ca_extra.pem nav vai sistēmas kopumu neizdodas atrast — tad paliek
 * parastā curl uzvedība un nekas nekļūst vājāks.
 */
function dl_ca_bundle(): ?string {
    static $kopums = null, $meklets = false;
    if ($meklets) return $kopums;
    $meklets = true;
    $extra = __DIR__ . '/ca_extra.pem';
    if (!is_readable($extra)) return null;
    $sys = (string)ini_get('curl.cainfo');
    if ($sys === '' || !is_readable($sys)) {
        $loc = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : [];
        $sys = (string)($loc['default_cert_file'] ?? '');
    }
    if ($sys === '' || !is_readable($sys) || filesize($sys) < 1000) return null;
    $out = sys_get_temp_dir() . '/saraksts_ca_bundle_' . md5($sys . '|' . (string)filemtime($extra)) . '.pem';
    if (!is_file($out)) {
        $saturs = file_get_contents($sys);
        if ($saturs === false || @file_put_contents($out, $saturs . "\n" . file_get_contents($extra)) === false) return null;
    }
    return $kopums = $out;
}

/**
 * Lejupielādē vienu URL ar atkārtojumiem (backoff: retry_s, 2*retry_s, ...).
 * $deadline (microtime) — lejupielādes laika budžeta beigas: pēc tām jaunu mēģinājumu nesāk,
 * curl taimautus apgriež līdz atlikušajam laikam, un gaidīšanu, kas pārsniegtu budžetu, nesāk.
 * Budžeta dēļ pārtraukts fails atzīmēts ar $GLOBALS['__dl_budzets'] = true.
 * @return array [bool ok, int used_attempts]
 */
function download_with_retries(string $url, string $dest, int $attempts, int $retry_s, ?callable $log = null, ?float $deadline = null): array {
    $GLOBALS['__dl_budzets'] = false;
    for ($try = 1; $try <= $attempts; $try++) {
        build_abort_if_stopped();
        $timeout = 300; $connect = 30;
        if ($deadline !== null) {
            $atlicis = (int)floor($deadline - microtime(true));
            if ($atlicis < 1) {
                $GLOBALS['__dl_budzets'] = true;
                if ($try === 1) $GLOBALS['__dl_kluda'] = 'lejupielādes laika budžets beidzies';
                return [false, $try - 1];
            }
            $timeout = min($timeout, $atlicis);
            $connect = min($connect, $atlicis);
        }
        if (download_one($url, $dest, $timeout, $connect)) return [true, $try];
        if (!empty($GLOBALS['__dl_galigs'])) return [false, $try];   // saturs atmests — tas pats būs arī nākamreiz
        if ($deadline !== null && $deadline - microtime(true) < 1.5) {
            // Mēģinājumu nogrieza budžets (taimauts apgriezts līdz floor(atlikums), tātad pēc tā paliek < 1 s)
            // — par saimnieku tas neliecina.
            $GLOBALS['__dl_budzets'] = true;
            return [false, $try];
        }
        if ($try < $attempts) {
            $wait = $retry_s * $try; // pieaugoša pauze: 15s, 30s, ...
            $kl = dl_pedeja_kluda();
            if ($deadline !== null && microtime(true) + $wait >= $deadline) {
                $GLOBALS['__dl_budzets'] = true;
                if ($log) $log("      ! {$try}. mēģinājums neizdevās" . ($kl !== '' ? " ($kl)" : '') . " — atkārtojumam laika budžetā vairs nepietiek.");
                return [false, $try];
            }
            if ($log) $log("      ! {$try}. mēģinājums neizdevās" . ($kl !== '' ? " ($kl)" : '') . " — gaida {$wait}s un atkārto ...");
            for ($w = 0; $w < $wait; $w++) { build_abort_if_stopped(); sleep(1); }
        }
    }
    return [false, $attempts];
}

/**
 * Lejupielādē visus CSV uz $csv_dir. Atgriež [ok_count, fail_count, fail_names[], izlaisto_skaits].
 * fail_count/fail_names ietver arī izlaistos (budžets beidzies / saimnieks nesasniedzams) — tiem,
 * tāpat kā neizdevušajiem, paliek iepriekšējā versija.
 * $urls_in — tikai testiem; null = CSV_URLS + dinamiskie (EIS gadi no CKAN).
 *
 * Divi piegājieni. 1.: visi faili pēc kārtas; saimnieku, kam REG_DL_HOST_FAILS faili pēc kārtas
 * krīt ar tīkla kļūmi, atmet, un tā atlikušos failus ATLIEK. 2.: atmestajiem saimniekiem ik
 * REG_DL_HOST_PROBE_S viens pārbaudes mēģinājums ar nākamo atlikto failu (agrāk atmestais —
 * pirmais), kamēr zonde ietilpst budžetā; atbild → pārējos lejupielādē parasti. Bez otrā
 * piegājiena īss data.gov.lv pārrāvums ap 02:00 atmestu to uz visu nakti — arī register.csv,
 * kas ir saraksta beigās, jo pēc atmešanas atlikušie 66 faili tiktu izlaisti milisekundēs.
 */
function download_all_csvs(string $csv_dir, ?callable $log = null, ?array $urls_in = null): array {
    $t0 = microtime(true);   // budžets skaitās no paša sākuma — arī CKAN pārbaude (līdz 20 s) tajā ietilpst
    if (!is_dir($csv_dir)) @mkdir($csv_dir, 0775, true);

    $delay_s = dl_env_int('REG_DL_DELAY_S', 2);                   // pauze starp failiem (saudzē data.gov.lv)
    $attempts = max(1, dl_env_int('REG_DL_ATTEMPTS', 3));         // mēģinājumi vienam failam
    $retry_s = max(1, dl_env_int('REG_DL_RETRY_S', 15));          // bāzes pauze pirms atkārtojuma
    // Budžets 1500 s = ~7× parastā lejupielāde (211 s 2026-10-21). Ar pārējiem posmiem (~8 min)
    // pilnā būve tad beidzas ~35 min pēc starta — droši zem stundas, kurā cron procesu nokauj.
    $budzets_s = dl_env_int('REG_DL_BUDGET_S', 1500);
    $saimn_limits = dl_env_int('REG_DL_HOST_FAILS', 3);
    $zonde_s = dl_env_int('REG_DL_HOST_PROBE_S', 300);
    $zondes_max = 5;           // griesti zondēm uz saimnieku (bez budžeta citādi zondētu bezgalīgi)
    $deadline = $budzets_s > 0 ? $t0 + $budzets_s : null;

    // Pārtrauktu lejupielāžu atliekas: process, ko nokauj curl vidū, .part failu neizdzēš
    // (2026-10-22 palika 0 baitu eis_e_iepirkumi_rezultati_2019.csv.part). Tie ir tikai mūsu faili.
    foreach (glob($csv_dir . '/*.csv.part') ?: [] as $p) @unlink($p);

    // Deduplicē URL (saglabā secību)
    $seen = []; $urls = [];
    foreach ($urls_in ?? array_merge(CSV_URLS, build_dinamiskie_urli()) as $u) {
        if (!isset($seen[$u])) { $seen[$u] = 1; $urls[] = $u; }
    }
    if ($urls_in === null && !empty($GLOBALS['__eis_ckan_kluda']) && $log) {
        $log('   ! EIS jauno gadu pārbaude (CKAN) neizdevās: ' . $GLOBALS['__eis_ckan_kluda'] . ' — lieto tikai zināmos gadus');
    }
    $total = count($urls);
    if ($log) $log("   Kopā $total faili; pauze starp failiem {$delay_s}s, līdz $attempts mēģinājumiem failam"
        . ($deadline !== null ? ", laika budžets {$budzets_s}s" : '')
        . ($saimn_limits > 0 ? ", saimnieku atmet pēc $saimn_limits failiem pēc kārtas ar tīkla kļūmi" : '') . '.');

    $ok = 0; $fail = 0; $failed = [];
    $pec_kartas = [];          // saimnieks => failu skaits pēc kārtas ar tīkla kļūmi
    $atmests_kops = [];        // saimnieks => microtime, kad atmests vai pēdējo reizi zondēts
    $zondes = [];              // saimnieks => izdarīto zonžu skaits
    $atlikti = [];             // saimnieks => [[url, faila vārds, "i/kopā"], ...] — 2. piegājienam
    $izlaisti = [];            // iemesls => [faila vārdi] — izlaisti bez mēģinājuma
    $budzets_beidzies = false;

    // < 1 s — tas pats slieksnis, ar ko download_with_retries vairs nesāk mēģinājumu
    $budzets_atlicis = function () use (&$budzets_beidzies, $deadline, $budzets_s, $log): bool {
        if (!$budzets_beidzies && $deadline !== null && $deadline - microtime(true) < 1) {
            $budzets_beidzies = true;
            if ($log) $log("   ! Lejupielādes laika budžets ({$budzets_s}s) beidzies — atlikušos failus izlaižu (paliek iepriekšējās versijas).");
        }
        return !$budzets_beidzies;
    };
    $izlaist = function (string $filename, string $iemesls) use (&$fail, &$failed, &$izlaisti): void {
        $fail++; $failed[] = $filename;
        $izlaisti[$iemesls][] = $filename;
    };
    $atmests = function (string $saimn) use (&$pec_kartas, $saimn_limits): bool {
        return $saimn_limits > 0 && ($pec_kartas[$saimn] ?? 0) >= $saimn_limits;
    };
    // Vai nākamā zonde (pēc $zonde_s no $no) ietilpst: zondes ir atļautas, griesti nav sasniegti, un
    // budžetā pēc tās gaidīšanas vēl ir pilns savienojuma logs (30 s) — citādi gaidīt velti.
    $zonde_ietilpst = function (string $saimn, float $no) use (&$zondes, $zonde_s, $zondes_max, $deadline): bool {
        return $zonde_s > 0 && ($zondes[$saimn] ?? 0) < $zondes_max
            && ($deadline === null || $no + $zonde_s + 30 <= $deadline);
    };
    $pauze = function () use ($delay_s): void {   // saudzīga pauze starp failiem
        for ($w = 0; $w < $delay_s; $w++) { build_abort_if_stopped(); sleep(1); }
    };
    // Viens fails: lejupielāde, žurnāls, drošinātāja skaitītājs. true = izdevās.
    $viens = function (string $url, string $filename, string $nr, int $meg, string $darbiba) use (
        $csv_dir, $retry_s, $deadline, $log, $saimn_limits, $zonde_s, $budzets_atlicis, $izlaist, $zonde_ietilpst,
        &$ok, &$fail, &$failed, &$pec_kartas, &$atmests_kops
    ): bool {
        if (!$budzets_atlicis()) { $izlaist($filename, 'laika budžets beidzies'); return false; }
        $saimn = dl_saimnieks($url);
        $dest = $csv_dir . '/' . $filename;
        if ($log) $log("   -> [$nr] $darbiba$filename ...");
        [$success, $used] = download_with_retries($url, $dest, $meg, $retry_s, $log, $deadline);
        if ($success) {
            $ok++;
            $pec_kartas[$saimn] = 0;
            $tries = $used > 1 ? " ({$used}. mēģinājumā)" : '';
            if ($log) $log("      OK (" . number_format((float)filesize($dest), 0, '.', ' ') . " b)$tries");
            return true;
        }
        if ($used === 0) {   // budžets beidzās starp pārbaudi un mēģinājumu — nekas netika prasīts
            $budzets_atlicis();
            $izlaist($filename, 'laika budžets beidzies');
            return false;
        }
        $fail++; $failed[] = $filename;
        $kl = dl_pedeja_kluda();
        if ($log) $log("      ! NEIZDEVĀS: $filename pēc $used mēģinājum" . ($used === 1 ? 'a' : 'iem') . ($kl !== '' ? " ($kl)" : '') . " — paliek iepriekšējā versija.");
        // Būves beigu kopsavilkumam un admin žurnālam (build_all.php) — citādi rinda
        // paliek 400 rindu žurnāla vidū, un "status: ok" to nerāda (recenzija 2026-10-14).
        if (function_exists('avotu_bridinajumi')) {
            avotu_bridinajumi("Lejupielāde: $filename neizdevās" . ($kl !== '' ? " ($kl)" : '') . ' — paliek iepriekšējā versija');
        }
        // Drošinātājs: skaita tikai tīkla kļūmes (saimnieks neatbild / 502–504). Budžeta pārtraukts
        // fails par saimnieku neko neliecina; 4xx, 500 un atmests saturs nozīmē, ka saimnieks atbild.
        if (empty($GLOBALS['__dl_budzets'])) {
            $pec_kartas[$saimn] = !empty($GLOBALS['__dl_tikls']) ? ($pec_kartas[$saimn] ?? 0) + 1 : 0;
            if ($saimn_limits > 0 && $pec_kartas[$saimn] === $saimn_limits) {
                $atmests_kops[$saimn] = $tagad = microtime(true);
                if ($log) $log("   ! $saimn: $saimn_limits faili pēc kārtas neizdevās ar tīkla kļūmi (vai 502–504) — pārējos šī saimnieka failus atlieku"
                    . ($zonde_ietilpst($saimn, $tagad) ? " un pēc {$zonde_s}s pārbaudīšu, vai tas atkal atbild."
                        : ' (pārbaudei laika budžetā vietas nav — paliek iepriekšējās versijas).'));
            }
        }
        return false;
    };

    // ── 1. piegājiens ──
    $i = 0;
    foreach ($urls as $url) {
        build_abort_if_stopped();
        $i++;
        $filename = basename(parse_url($url, PHP_URL_PATH) ?? '');
        // Daļa avotu atdod CSV no dinamiska galapunkta BEZ .csv faila vārda
        // (piem. deminimis.fm.gov.lv/public/mekletajs/export_csv). Tiem faila vārdu
        // norādām paši ar #fails=... fragmentu URL beigās — citādi lejupielādētājs
        // tos klusi izlaistu, un tabula nekad nerastos (2026-08-19).
        $frag = parse_url($url, PHP_URL_FRAGMENT) ?? '';
        if ($frag !== '' && preg_match('/^fails=([a-z0-9_\-]+\.csv)$/i', $frag, $fm)) {
            $filename = $fm[1];
        }
        if ($filename === '' || !str_ends_with(strtolower($filename), '.csv')) {
            if ($log) $log("   - Izlaists (nav .csv): $url");
            continue;
        }
        if (!$budzets_atlicis()) { $izlaist($filename, 'laika budžets beidzies'); continue; }   // bez pauzes
        $saimn = dl_saimnieks($url);
        if ($atmests($saimn)) { $atlikti[$saimn][] = [$url, $filename, "$i/$total"]; continue; }
        $viens($url, $filename, "$i/$total", $attempts, 'Lejupielādē: ');
        if ($i < $total) $pauze();   // izņemot pēc pēdējā
    }

    // ── 2. piegājiens: atmesto saimnieku atliktie faili; vienmēr nākamais tas, kura zonde agrākā ──
    while ($atlikti) {
        build_abort_if_stopped();
        uksort($atlikti, fn($a, $b) => ($atmests_kops[$a] ?? 0.0) <=> ($atmests_kops[$b] ?? 0.0));
        $saimn = array_key_first($atlikti);
        $kops = $atmests_kops[$saimn] ?? 0.0;
        if (!$budzets_atlicis() || !$zonde_ietilpst($saimn, $kops)) {
            $iem = $budzets_beidzies ? 'laika budžets beidzies'
                : (($zonde_s > 0 && $deadline !== null && ($zondes[$saimn] ?? 0) === 0)
                    ? "$saimn nesasniedzams; pārbaudei laika budžetā vietas nebija" : "$saimn nesasniedzams");
            foreach ($atlikti[$saimn] as [, $f]) $izlaist($f, $iem);
            unset($atlikti[$saimn]);
            continue;
        }
        $kad = $kops + $zonde_s;
        $gaid = (int)ceil($kad - microtime(true));
        if ($gaid > 0) {
            if ($log) $log("   … $saimn: " . count($atlikti[$saimn]) . " atlikti faili; gaida {$gaid}s un pārbauda, vai saimnieks atkal atbild ...");
            while (microtime(true) < $kad) { build_abort_if_stopped(); usleep(200000); }
        }
        $zondes[$saimn] = ($zondes[$saimn] ?? 0) + 1;
        [$url0, $f0, $nr0] = array_shift($atlikti[$saimn]);
        $viens($url0, $f0, $nr0, 1, "Pārbauda, vai $saimn atkal atbild ({$zondes[$saimn]}. reize): ");
        if ($atmests($saimn)) {   // zonde krita ar tīkla kļūmi (vai to nogrieza budžets) — nākamā pēc $zonde_s
            $atmests_kops[$saimn] = microtime(true);
            if (!$atlikti[$saimn]) unset($atlikti[$saimn]);
            continue;
        }
        if ($log) $log("   ✔ $saimn atkal atbild — atliktos failus lejupielādē.");
        while ($atlikti[$saimn]) {
            build_abort_if_stopped();
            if ($atmests($saimn)) break;   // atkal atmests — $viens atjaunoja laiku; paliek rindā nākamajai zondei
            if (!$budzets_atlicis()) break;   // atlikušos izlaidīs ārējā cilpa — bez liekām pauzēm
            $pauze();
            [$url, $f, $nr] = array_shift($atlikti[$saimn]);
            $viens($url, $f, $nr, $attempts, 'Lejupielādē: ');
        }
        if (!$atlikti[$saimn]) unset($atlikti[$saimn]);
    }

    $n_izl = 0;
    foreach ($izlaisti as $iem => $faili) {
        $n_izl += count($faili);
        $msg = 'Lejupielāde: ' . count($faili) . " faili izlaisti bez mēģinājuma ($iem) — paliek iepriekšējās versijas";
        if ($log) $log("   ! $msg: " . implode(', ', $faili));
        if (function_exists('avotu_bridinajumi')) avotu_bridinajumi($msg);
    }
    $meginati = array_values(array_diff($failed, array_merge([], ...array_values($izlaisti))));
    if ($log) $log("   Lejupielāde pabeigta: $ok OK, " . ($fail - $n_izl) . ' kļūdas'
        . ($meginati ? ' (' . implode(', ', $meginati) . ')' : '')
        . ($n_izl ? ", $n_izl izlaisti" : '')
        . ' (' . round(microtime(true) - $t0) . 's)');
    return [$ok, $fail, $failed, $n_izl];
}
