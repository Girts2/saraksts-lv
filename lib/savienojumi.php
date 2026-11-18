<?php
/**
 * lib/savienojumi.php — vai serveris sasniedz ārējos datu avotus (lapa savienojumi_admin.php).
 *
 * KĀPĒC (2026-10-23): no 2026-10-22 servera IP ir valsts iestāžu bloķēšanas sarakstā — data.gov.lv,
 * VDAA platforma u.c. neatbild uz TCP savienojumu, un nakts būve klusi strādā ar vecajiem CSV.
 * No datora (cita IP) viss strādā, tāpēc pārbaudei jānotiek PAŠĀ serverī. Šeit ar vienu izsaukumu
 * paralēli zondē katru ārējo saimnieku, ko lieto vietnes kods, un pasaka, kurā solī savienojums
 * krīt: DNS, TCP, TLS vai HTTP.
 *
 * MĒRĶUS NERAKSTA AR ROKU. Tos izvelk no tiem pašiem failiem, ko lieto būve un ievācēji: virkņu
 * literāļi ar http(s)://, komentāri neskaitās. Jauns avots kodā pārbaudē parādās pats. Katram
 * saimniekam viena zonde: vispirms fails (…csv/json/xml) no koda, tad jebkura pilna adrese, citādi
 * saknes lapa. Adrese ar vietturi (%s, %d, {gads}) vai tāda, kam kodā pielīmē turpinājumu
 * ('…' . $x, "…$x"), nav pilna, un no tās ņem tikai saimnieku. Lejupielādē tikai pirmos 64 KB
 * (Range + pārtraukums), ne vairāk kā SAV_PARALELI pieprasījumu vienlaikus.
 *
 * IP BLOKS UN TLS. Ar curl 8.x CONNECT_TIME aizpildās tikai pēc VISA savienojuma (arī TLS), tāpēc
 * taimauts ar connect_time 0 var būt gan SYN bez atbildes (IP bloks), gan ugunsmūris, kas pieņem TCP,
 * bet nomet ne-pārlūka TLS rokasspiedienu (JA3; lad/vid/cfla 2026-08). Šos abus atšķir otra, tīri
 * TCP zonde (CURLOPT_CONNECT_ONLY bez TLS) — tikai rindām ar šādu taimautu.
 */
declare(strict_types=1);

/**
 * Kontroles adrese: ja tā nestrādā, serverim nav ārēja interneta vispār; atbildē ir arī servera IP.
 * Tikai pa IPv4: serverim ir arī IPv6, bet Latvijas avoti ir IPv4, un bloķēšanas saraksts redz IPv4 adresi.
 */
const SAV_KONTROLE = 'https://www.cloudflare.com/cdn-cgi/trace';

/**
 * Sadaļas: kurus failus skenēt, kāds User-Agent jāsūta (tas pats, ko sūta sadaļas kods; testi pārbauda,
 * ka virkne avota failā tiešām ir) un vai kods pievieno papildu CA starpsertifikātus.
 * 'dati' = nakts būve: zondei jāatdod dati (ne HTML, ne 4xx/5xx), citādi būve failu neiegūs.
 */
const SAV_SADALAS = [
    'registrs' => ['nosaukums' => 'Reģistrs — nakts būves lejupielāde', 'dati' => true, 'papildu_ca' => true,
                   'faili' => ['registrs/build/config.php'],
                   'ua' => 'saraksts.lv data updater (PHP)'],                    // registrs/build/download.php
    'granti'   => ['nosaukums' => 'Granti', 'dati' => false, 'papildu_ca' => false,
                   'faili' => ['granti/lib/avoti_lv.php', 'granti/bin/build.php'],
                   'ua' => 'Mozilla/5.0 (compatible; SarakstsLV/1.0; +https://saraksts.lv/)'], // granti/lib/ievacejs.php GR_IEV_UA
    'konkursi' => ['nosaukums' => 'Konkursi', 'dati' => false, 'papildu_ca' => true,
                   'faili' => ['konkursi/lib/config.php', 'konkursi/lib/sync_engine.php'], // *_parser.php tikai parsē (XML vārdtelpas, saites)
                   'ua' => 'Mozilla/5.0 (compatible; SarakstsKonkursi/1.0; +https://saraksts.lv/konkursi.php)'], // TED_USER_AGENT
    'mi'       => ['nosaukums' => 'MI (Gemini API)', 'dati' => false, 'papildu_ca' => false,
                   'faili' => ['registrs/mi/gemini_client.php'],
                   'ua' => null],                                                   // gemini_client.php UA nesūta
];

/** Saimnieki, kurus nezondē: pati vietne. */
const SAV_IZLAIST = ['saraksts.lv', 'www.saraksts.lv'];

/**
 * Vienlaicīgi pieprasījumi. ~80 uzreiz no viena tīmekļa procesa nozīmētu ~80 DNS pavedienus (CloudLinux
 * LVE skaita pavedienus; pārsniegums izskatītos kā "DNS kļūda") un vairākus SYN vienai VDAA IP uzreiz.
 */
const SAV_PARALELI = 16;

/** curl kļūdas, kad sertifikātu neizdodas pārbaudīt (bieži: avots nesūta starpsertifikātu). 77 = CA fails. */
const SAV_SERT_KLUDAS = [51, 58, 60];

/**
 * Adreses no viena PHP faila virkņu literāļiem: [['url' => …, 'pilna' => bool], …] faila secībā.
 * Nepilna = ar vietturi vai ar turpinājumu (aiz literāļa seko '.'), vai daļa no "…$x…" pirms mainīgā.
 */
function sav_urli_no_avota(string $kods): array {
    $tok = token_get_all($kods);
    $out = [];
    $n = count($tok);
    for ($i = 0; $i < $n; $i++) {
        $t = $tok[$i];
        if (!is_array($t) || !in_array($t[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) continue;
        if (!preg_match_all('~https?://[A-Za-z0-9.-]+(?::\d+)?(?:/[^\s\'"<>\\\\]*)?~', $t[1], $m)) continue;
        if ($t[0] === T_ENCAPSED_AND_WHITESPACE) {
            // "…$x…" vai heredoc: turpinājums ir tikai tad, ja aiz fragmenta seko mainīgais, ne beigas.
            $nak = $tok[$i + 1] ?? '"';
            $turpinajums = !($nak === '"' || (is_array($nak) && $nak[0] === T_END_HEREDOC));
        } else {
            $turpinajums = false;
            for ($j = $i + 1; $j < $n; $j++) {
                if (is_array($tok[$j]) && in_array($tok[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
                $turpinajums = $tok[$j] === '.';
                break;
            }
        }
        $teksts = rtrim($t[1], '\'"');
        foreach ($m[0] as $u) {
            $beigas = str_ends_with($teksts, $u); // literālis beidzas tieši ar adresi
            $u = rtrim($u, '.,;:)');              // teikuma pieturzīmes aiz adreses
            // Vietturis: sprintf (%s, %d, %1$s, %02d) vai {gads}. %2f, %20 u.c. ir URL kodējums, ne vietturis.
            $vieta = (bool)preg_match('/%(?:\d+\$)?(?:[su]|d(?![0-9a-fA-F])|0\d+d)|[{}]/', $u);
            $out[] = ['url' => $u, 'pilna' => !$vieta && !($turpinajums && $beigas)];
        }
    }
    return $out;
}

/** Vai adrese ir datu fails: ceļš …csv/json/xml/zip/gz vai būves '#fails=' (export_csv u.c. bez paplašinājuma). */
function sav_ir_fails(string $url): bool {
    $cels = (string)parse_url($url, PHP_URL_PATH);
    return (bool)preg_match('~\.(csv|json|xml|zip|gz)$~i', $cels)
        || str_starts_with((string)parse_url($url, PHP_URL_FRAGMENT), 'fails=');
}

/**
 * Zondējamie mērķi: [['sadala', 'saimnieks', 'url', 'veids' => dati|adrese|sakne, 'ua', 'papildu_ca', 'adreses'], …].
 * $sakne — docroot. Sadaļas un faili no SAV_SADALAS (testiem var padot citus).
 */
function sav_merki(string $sakne, ?array $sadalas = null): array {
    $sadalas ??= SAV_SADALAS;
    $merki = [['sadala' => 'kontrole', 'saimnieks' => (string)parse_url(SAV_KONTROLE, PHP_URL_HOST),
               'url' => SAV_KONTROLE, 'veids' => 'adrese', 'ua' => 'saraksts.lv data updater (PHP)',
               'papildu_ca' => false, 'adreses' => 1, 'ipv4' => true]];
    $redzets = [];
    foreach ($sadalas as $sid => $s) {
        $pa_saimn = [];
        foreach ($s['faili'] as $raksts) {
            $faili = glob(rtrim($sakne, '/') . '/' . $raksts) ?: [];
            sort($faili);
            foreach ($faili as $f) {
                foreach (sav_urli_no_avota((string)file_get_contents($f)) as $u) {
                    $h = strtolower((string)parse_url($u['url'], PHP_URL_HOST));
                    if ($h === '' || !str_contains($h, '.') || in_array($h, SAV_IZLAIST, true)) continue;
                    $pa_saimn[$h][] = $u;
                }
            }
        }
        foreach ($pa_saimn as $h => $saraksts) {
            if (isset($redzets[$h])) continue; // viens saimnieks = viena zonde, pirmajā sadaļā
            $redzets[$h] = true;
            $pilnas = array_values(array_filter($saraksts, fn($u) => $u['pilna']));
            $faili = array_values(array_filter($pilnas, fn($u) => sav_ir_fails($u['url'])));
            $izv = $faili[0] ?? $pilnas[0] ?? null;
            if ($izv !== null) {
                $url = preg_replace('/#.*$/', '', $izv['url']);
                $veids = !empty($s['dati']) ? 'dati' : 'adrese'; // būves sarakstā katra pilnā adrese ir datu fails
            } else {
                $url = parse_url($saraksts[0]['url'], PHP_URL_SCHEME) . '://' . $h . '/';
                $veids = 'sakne';
            }
            $adreses = count(array_unique(array_map(fn($u) => preg_replace('/#.*$/', '', $u['url']), $saraksts)));
            $merki[] = ['sadala' => $sid, 'saimnieks' => $h, 'url' => $url, 'veids' => $veids,
                        'ua' => $s['ua'], 'papildu_ca' => !empty($s['papildu_ca']), 'adreses' => $adreses];
        }
    }
    return $merki;
}

/** Viens curl rokturis zondei. $st aizpilda WRITEFUNCTION; pēc $opc['baiti'] baitiem pārtrauc. */
function sav_rokturis(array $m, array $opc, ?string $cainfo, array &$st) {
    $ch = curl_init($m['url']);
    $st = ['paraugs' => '', 'baiti' => 0, 'partraukts' => false];
    $limits = $opc['baiti'];
    $o = [
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => $opc['savienojums_s'], CURLOPT_TIMEOUT => $opc['kopa_s'],
        CURLOPT_ENCODING => '', CURLOPT_NOSIGNAL => true,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => ['Range: bytes=0-' . ($limits - 1)],
        CURLOPT_WRITEFUNCTION => static function ($ch, string $d) use (&$st, $limits): int {
            if (strlen($st['paraugs']) < 2048) $st['paraugs'] .= substr($d, 0, 2048 - strlen($st['paraugs']));
            $st['baiti'] += strlen($d);
            if ($st['baiti'] >= $limits) { $st['partraukts'] = true; return -1; }
            return strlen($d);
        },
    ];
    if ($m['ua'] !== null) $o[CURLOPT_USERAGENT] = $m['ua'];
    if (!empty($m['ipv4'])) $o[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    if ($cainfo !== null) $o[CURLOPT_CAINFO] = $cainfo;
    curl_setopt_array($ch, $o);
    return $ch;
}

/** Rezultāta lauki, kad zonde nepabeidzās (curl_multi kļūda vai sienas pulksteņa termiņš). */
function sav_nepabeigts(array $m, string $kluda): array {
    return array_replace($m, ['errno' => -1, 'kluda' => $kluda, 'http' => 0, 'ip' => '',
        'dns_s' => 0.0, 'tcp_s' => 0.0, 'tls_s' => 0.0, 'kopa_s' => 0.0, 'parvirzes' => 0, 'beigu_url' => '',
        'tips' => '', 'baiti' => 0, 'partraukts' => false, 'paraugs' => '', 'ar_papildu_ca' => false,
        'ca_meginats' => false, 'tcp_parbaude' => null, 'tcp_errno' => null, 'tcp_ip' => '']);
}

/** Roktura rezultāts masīvā (bez vērtējuma). Mērķa laukus papildina; rezultāta lauki uzvar (array_replace). */
function sav_rezultats(array $m, $ch, int $errno, array $st, bool $ar_papildu_ca, bool $ca_meginats): array {
    $i = curl_getinfo($ch);
    // curl_getinfo() masīvā appconnect_time nav (tikai *_us), tāpēc laikus prasa pa vienam. Neapaļo līdz
    // ms: 0 nozīmē "nav izveidots", un tuvs savienojums ilgst mazāk par 0,5 ms.
    $dns = (float)curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
    $ip = (string)($i['primary_ip'] ?? '');
    // curl 8.x primary_ip aizpilda tikai pēc pilna savienojuma; IP rādīšanai pajautājam DNS (atbilde jau kešā).
    // Ne pēc DNS kļūdas vai DNS taimauta — tur katrs izsaukums gaidītu vēlreiz.
    if ($ip === '' && $errno !== 6 && $dns > 0) {
        $h = trim((string)parse_url((string)($i['url'] ?? $m['url']), PHP_URL_HOST), '[]');
        $r = filter_var($h, FILTER_VALIDATE_IP) ? $h : gethostbyname($h);
        if (filter_var($r, FILTER_VALIDATE_IP)) $ip = $r;
    }
    return array_replace($m, [
        'errno' => $errno, 'kluda' => $errno > 0 ? curl_strerror($errno) : '',
        'http' => (int)($i['http_code'] ?? 0), 'ip' => $ip,
        'dns_s' => $dns,
        'tcp_s' => (float)curl_getinfo($ch, CURLINFO_CONNECT_TIME),
        'tls_s' => (float)curl_getinfo($ch, CURLINFO_APPCONNECT_TIME),
        'kopa_s' => (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME),
        'parvirzes' => (int)($i['redirect_count'] ?? 0), 'beigu_url' => (string)($i['url'] ?? ''),
        'tips' => (string)($i['content_type'] ?? ''), 'baiti' => $st['baiti'],
        'partraukts' => $st['partraukts'], 'paraugs' => $st['paraugs'],
        // 'papildu_ca' (vai sadaļa vispār lieto papildu CA) nāk no mērķa; šeit — vai TIEŠI ŠIS rezultāts ar to.
        'ar_papildu_ca' => $ar_papildu_ca, 'ca_meginats' => $ca_meginats,
        'tcp_parbaude' => null, 'tcp_errno' => null, 'tcp_ip' => '',
    ]);
}

/**
 * curl_multi ar ierobežotu paralēlumu un sienas pulksteņa termiņu. $sakt($k) dod jaunu rokturi atslēgai,
 * $beigas($k, $ch, $errno) apstrādā pabeigto un var atgriezt true, lai to pašu atslēgu palaistu vēlreiz
 * (sertifikāta atkārtojums). Rokturi, ko curl_multi nepieņem, pabeidz ar CURLE_FAILED_INIT. Atgriež
 * atslēgas, kas palika bez rezultāta (curl_multi kļūda vai termiņš) — cilpa nekad negriežas bezgalīgi.
 */
function sav_multi(array $atslegas, int $paraleli, callable $sakt, callable $beigas, float $termins_s = 120.0): array {
    $mh = curl_multi_init();
    $rinda = array_values($atslegas);
    $hs = [];
    $lidz = microtime(true) + $termins_s;
    $papildinat = function () use (&$rinda, &$hs, $mh, $paraleli, $sakt, $beigas): void {
        while ($rinda && count($hs) < $paraleli) {
            $k = array_shift($rinda);
            $ch = $sakt($k);
            if (curl_multi_add_handle($mh, $ch) !== CURLM_OK) {
                if ($beigas($k, $ch, CURLE_FAILED_INIT)) array_unshift($rinda, $k);
                continue;
            }
            $hs[$k] = $ch;
        }
    };
    $papildinat();
    do {
        $stav = curl_multi_exec($mh, $aktivi);
        while ($inf = curl_multi_info_read($mh)) {
            $k = array_search($inf['handle'], $hs, true);
            if ($k === false) continue;
            curl_multi_remove_handle($mh, $hs[$k]);
            $ch = $hs[$k];
            unset($hs[$k]);
            if ($beigas($k, $ch, (int)$inf['result'])) array_unshift($rinda, $k);
        }
        $papildinat();
        if ($hs && curl_multi_select($mh, 1.0) === -1) usleep(50000);
    } while ($hs && $stav === CURLM_OK && microtime(true) < $lidz);
    foreach ($hs as $ch) curl_multi_remove_handle($mh, $ch);
    curl_multi_close($mh);
    return array_merge(array_keys($hs), $rinda);
}

/**
 * Paralēla zondēšana: [atslēga => rezultāts bez vērtējuma]. $ca_atkartot — ja dots, mērķi ar 'papildu_ca',
 * kam sertifikātu neizdodas pārbaudīt, tajā pašā kārtā atkārto ar šo CA (tā dara būve un Konkursi).
 */
function sav_karta(array $merki, array $opc, ?string $ca_atkartot = null): array {
    $opc += ['savienojums_s' => 10, 'kopa_s' => 15, 'baiti' => 65536, 'paraleli' => SAV_PARALELI, 'termins_s' => 120.0];
    $st = []; $rez = []; $ar_ca = [];
    $sakt = function ($k) use (&$st, &$ar_ca, $merki, $opc, $ca_atkartot) {
        $st[$k] = [];
        return sav_rokturis($merki[$k], $opc, isset($ar_ca[$k]) ? $ca_atkartot : null, $st[$k]);
    };
    $beigas = function ($k, $ch, int $e) use (&$st, &$rez, &$ar_ca, $merki, $ca_atkartot): bool {
        if ($e === CURLE_WRITE_ERROR && $st[$k]['partraukts']) $e = 0; // mūsu pašu pārtraukums pēc limita
        $otra = isset($ar_ca[$k]);
        $r = sav_rezultats($merki[$k], $ch, $e, $st[$k], $otra, $otra);
        if ($otra) {
            // Sertifikāta problēma atrisināta — tālākais (arī lēna atbilde) ir īstais iznākums.
            if (!in_array($e, SAV_SERT_KLUDAS, true)) $rez[$k] = $r; else $rez[$k]['ca_meginats'] = true;
            return false;
        }
        $rez[$k] = $r;
        if ($ca_atkartot !== null && !empty($merki[$k]['papildu_ca']) && in_array($e, SAV_SERT_KLUDAS, true)) {
            $ar_ca[$k] = true;
            return true;
        }
        return false;
    };
    foreach (sav_multi(array_keys($merki), (int)$opc['paraleli'], $sakt, $beigas, (float)$opc['termins_s']) as $k) {
        $rez[$k] = sav_nepabeigts($merki[$k], 'zonde nepabeidzās (curl_multi kļūda vai pārbaudes termiņš)');
    }
    ksort($rez);
    return $rez;
}

/**
 * Tīri TCP savienojums (bez TLS un HTTP) rindām, kur pieprasījums beidzās ar taimautu bez savienojuma —
 * arī pāradresācijas mērķim, tad zondē beigu adresi. 'tcp_parbaude': true = TCP izveidojas (krīt TLS vai
 * atbilde), false = arī TCP taimauts (SYN bez atbildes), null = cita kļūda (atteikts, DNS…) — tā nav
 * pierādījums blokam. Vienu IP:portu zondē vienreiz: VDAA platformā uz vienas IP ir vairāki saimnieki, un
 * mūsu IP jau ir reputācijas sarakstā — liekie SYN izskatītos pēc skenēšanas.
 */
function sav_tcp_parbaude(array $rez, array $opc): array {
    $opc += ['tcp_s' => 5, 'paraleli' => SAV_PARALELI];
    $grupas = []; $merkis = [];
    foreach ($rez as $k => $r) {
        $tcp_nav = $r['parvirzes'] > 0 || $r['tcp_s'] <= 0; // pāradresācijai laiki ir summa — skatām beigu adresi
        if ($r['errno'] !== 28 || !$tcp_nav || $r['dns_s'] <= 0) continue;
        $p = parse_url($r['parvirzes'] > 0 && $r['beigu_url'] !== '' ? $r['beigu_url'] : $r['url']);
        $host = (string)($p['host'] ?? '');
        if ($host === '') continue;
        $ports = (int)($p['port'] ?? (($p['scheme'] ?? '') === 'https' ? 443 : 80));
        // Pāradresācijai IP pieder beigu saimniekam tikai tad, ja tas ir tas pats; citādi pēc nosaukuma.
        $ip = $r['parvirzes'] === 0 ? $r['ip'] : '';
        $merk = $ip !== '' ? $ip : trim($host, '[]');
        $atsl = $merk . '|' . $ports . '|' . (!empty($r['ipv4']) ? 4 : 0);
        $grupas[$atsl][] = $k;
        $merkis[$atsl] = [$merk, $ports, !empty($r['ipv4'])];
    }
    if (!$grupas) return $rez;
    $sakt = function ($atsl) use ($merkis, $opc) {
        [$merk, $ports, $v4] = $merkis[$atsl];
        $h = str_contains($merk, ':') ? '[' . $merk . ']' : $merk; // IPv6 literālis bez iekavām
        $ch = curl_init("http://$h:$ports/");
        curl_setopt_array($ch, [CURLOPT_CONNECT_ONLY => true, CURLOPT_CONNECTTIMEOUT => $opc['tcp_s'], CURLOPT_NOSIGNAL => true]);
        if ($v4) curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        return $ch;
    };
    $beigas = function ($atsl, $ch, int $e) use (&$rez, $grupas, $merkis): bool {
        // curl 8.x neveiksmīgam savienojumam primary_ip neaizpilda — tad rādām, ko zondējām (IP vai nosaukumu).
        $ip = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        foreach ($grupas[$atsl] as $k) {
            $rez[$k]['tcp_parbaude'] = $e === 0 ? true : ($e === 28 ? false : null);
            $rez[$k]['tcp_errno'] = $e;
            $rez[$k]['tcp_ip'] = $ip !== '' ? $ip : $merkis[$atsl][0];
        }
        return false;
    };
    sav_multi(array_keys($grupas), (int)$opc['paraleli'], $sakt, $beigas, (float)$opc['tcp_s'] + 5.0);
    return $rez;
}

/**
 * Zondē visus mērķus; rezultāti mērķu secībā, ar vērtējumu. Mērķiem ar 'papildu_ca' sertifikāta kļūdu
 * atkārto ar sistēmas CA + koda starpsertifikātiem (registrs/build/ca_extra.pem, konkursi/data/ca/*.pem).
 * $opc: savienojums_s, kopa_s, baiti, paraleli, tcp_s; 'ca' — papildinātā CA fails (null = sav_ca_kopums(sakne)).
 */
function sav_zonde(array $merki, array $opc = []): array {
    $opc += ['ca' => null, 'sakne' => dirname(__DIR__)];
    $ca = $opc['ca'] ?? sav_ca_kopums($opc['sakne']);
    $rez = sav_tcp_parbaude(sav_karta($merki, $opc, $ca), $opc);
    return array_map('sav_vertejums', $rez);
}

/**
 * Sistēmas CA + koda starpsertifikāti vienā pagaidu failā (kā download.php dl_ca_bundle un
 * konkursi ks_ca_bundle). null, ja sistēmas CA nav atrodams vai papildu sertifikātu nav.
 */
function sav_ca_kopums(string $sakne): ?string {
    $extra = array_merge([$sakne . '/registrs/build/ca_extra.pem'], glob($sakne . '/konkursi/data/ca/*.pem') ?: []);
    $extra = array_values(array_filter($extra, 'is_readable'));
    if (!$extra) return null;
    $sys = (string)ini_get('curl.cainfo');
    if ($sys === '' || !is_readable($sys)) $sys = (string)(ini_get('openssl.cafile') ?: '');
    if ($sys === '' || !is_readable($sys)) {
        $loc = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : [];
        $sys = (string)($loc['default_cert_file'] ?? '');
    }
    if ($sys === '' || !is_readable($sys) || filesize($sys) < 1000) return null;
    $zime = $sys . '|' . filemtime($sys);
    foreach ($extra as $f) $zime .= '|' . $f . '|' . filemtime($f);
    $out = sys_get_temp_dir() . '/saraksts_sav_ca_' . md5($zime) . '.pem';
    if (!is_file($out)) {
        // Pagaidu fails + pārsaukšana: pusierakstīts kopums nekad nav redzams zem gala vārda.
        $buf = (string)file_get_contents($sys);
        foreach ($extra as $f) $buf .= "\n" . (string)file_get_contents($f);
        $tmp = $out . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $buf) === false || !@rename($tmp, $out)) { @unlink($tmp); return null; }
    }
    return $out;
}

/** Iemesls vienā frāzē pēc curl kļūdas (bez konteksta par TCP pārbaudi). */
function sav_kludas_teksts(int $e, string $kluda, bool $ar_papildu_ca = false): string {
    return match (true) {
        $e === 6 => 'DNS: nosaukumu neizdodas atrisināt',
        $e === 7 => 'savienojums atteikts (ports aizvērts vai serveris nedarbojas)',
        $e === 28 => 'neatbild (taimauts)',
        $e === 35 => 'TLS rokasspiediens neizdevās',
        $e === 47 => 'pārāk daudz pāradresāciju',
        $e === 52 => 'serveris aizvēra savienojumu bez atbildes',
        $e === 56 => 'savienojums pārtrūka',
        // Pirmajā kārtā CAINFO nedodam — tad 77 ir sistēmas CA fails, ne mūsu papildu kopums.
        $e === 77 => ($ar_papildu_ca ? 'papildu' : 'sistēmas') . ' CA failu neizdodas nolasīt',
        in_array($e, SAV_SERT_KLUDAS, true) => 'sertifikātu neizdodas pārbaudīt',
        default => $kluda !== '' ? $kluda : 'nav atbildes',
    };
}

/** Kāpēc būve šo datu failu neiegūtu (FAILONERROR + dl_saturs_derigs), vai null, ja atbilde der. */
function sav_datu_problema(array $r): ?string {
    $h = $r['http'];
    $html = (bool)preg_match('~^\s*(<!doctype\s+html|<html|<head|<body)~i', ltrim($r['paraugs'], "\xEF\xBB\xBF"))
         || stripos($r['tips'], 'text/html') === 0;
    return match (true) {
        $h === 403 => 'piekļuve liegta (HTTP 403) — iespējams, ugunsmūris',
        $h === 429 => 'pārāk daudz pieprasījumu (HTTP 429)',
        $h === 416 || $r['baiti'] === 0 => "fails tukšs (HTTP $h)",
        $h >= 500 => "servera kļūda (HTTP $h)",
        $h >= 400 => "fails nav atrodams (HTTP $h) — avots pārvietots?",
        $html => "atbild ar HTML lapu, ne datiem (HTTP $h) — kļūdas vai bloķēšanas lapa?",
        default => null,
    };
}

/**
 * Pieliek rezultātam 'statuss' (ok|bridinajums|kluda) un 'teksts' latviski.
 * Reģistra datu failiem 'ok' nozīmē: būve šo failu iegūtu (dati, ne HTML, ne 4xx/5xx). Citām sadaļām
 * saknes lapai pietiek ar jebkuru HTTP atbildi; pilnai adresei 403/429/5xx ir brīdinājums.
 * «IP bloks» — TIKAI ja arī tīrā TCP zonde beidzās ar taimautu (tcp_parbaude === false).
 */
function sav_vertejums(array $r): array {
    $e = $r['errno']; $h = $r['http'];
    $dati = $r['veids'] === 'dati';
    $ca = !empty($r['ar_papildu_ca']) ? ' — ar koda papildu CA sertifikātu' : '';
    $kb = $r['baiti'] >= 1024 ? round($r['baiti'] / 1024) . ' KB' : $r['baiti'] . ' B';
    $tcp = $r['tcp_parbaude'] ?? null;
    if ($e < 0) return $r + ['statuss' => 'kluda', 'teksts' => $r['kluda']];
    if ($e === 0 && $h > 0) {
        if ($r['veids'] === 'sakne') return $r + ['statuss' => 'ok', 'teksts' => "sasniedzams (HTTP $h, pārbaudīta saknes lapa)$ca"];
        if (!$dati) {
            if ($h === 403 || $h === 429 || $h >= 500) {
                return $r + ['statuss' => 'bridinajums', 'teksts' => "sasniedzams, bet adrese atbild ar HTTP $h" . ($h === 403 ? ' (piekļuve liegta — iespējams, ugunsmūris)' : '')];
            }
            return $r + ['statuss' => 'ok', 'teksts' => "sasniedzams (HTTP $h, $kb)$ca"];
        }
        $t = sav_datu_problema($r);
        return $t === null ? $r + ['statuss' => 'ok', 'teksts' => "lejupielāde strādā ($kb saņemti, HTTP $h)$ca"]
                           : $r + ['statuss' => 'kluda', 'teksts' => "sasniedzams, bet būve šo failu neiegūtu: $t"];
    }
    // Atbilde sākās (arī pēc pāradresācijas), tad pārtrūka vai nepabeidzās laikā: avots sasniedzams. Datu
    // failam vēl jāder pašai atbildei — 403 vai HTML lapu būve neņems, pat ja tā būtu pilna.
    if ($h >= 200 && $r['baiti'] > 0 && in_array($e, [18, 28, 56], true)) {
        if ($dati && ($t = sav_datu_problema($r)) !== null) {
            return $r + ['statuss' => 'kluda', 'teksts' => "sasniedzams, bet būve šo failu neiegūtu: $t"];
        }
        return $r + ['statuss' => 'bridinajums', 'teksts' => "atbilde sākās (HTTP $h, $kb), bet savienojums pārtrūka vai nepabeidzās laikā"];
    }
    if ($r['parvirzes'] > 0) {
        // Krita pāradresācijas mērķis; laiki tad ir summa pa soļiem, tāpēc par TCP spriež pēc beigu adreses zondes.
        $kur = 'pāradresē uz ' . (string)parse_url($r['beigu_url'], PHP_URL_HOST);
        return $r + match (true) {
            in_array($e, [52, 56], true) => ['statuss' => $dati ? 'kluda' : 'bridinajums', 'teksts' => "$kur, bet tur serveris pārtrauc savienojumu bez atbildes"],
            $e === 28 && $tcp === false => ['statuss' => 'kluda', 'teksts' => "$kur, bet tur TCP savienojums neizveidojas — tā izskatās IP bloks pret šo serveri"],
            $e === 28 && $tcp === true => ['statuss' => 'bridinajums', 'teksts' => "$kur; tur savienojums izveidojas, bet atbilde nepienāca laikā"],
            default => ['statuss' => 'kluda', 'teksts' => "$kur, bet tur: " . sav_kludas_teksts($e, $r['kluda'], !empty($r['ar_papildu_ca']))],
        };
    }
    $savienots = $r['tls_s'] > 0 || ($r['tcp_s'] > 0 && !str_starts_with($r['url'], 'https'));
    if ($savienots && $e === 28) {
        return $r + ['statuss' => 'bridinajums', 'teksts' => 'savienojums strādā, bet atbilde nepienāca '
            . round($r['kopa_s']) . ' s laikā (īstajā ielādē taimauts garāks)'];
    }
    if ($savienots && in_array($e, [52, 56], true)) {
        // Sasniedzams, bet atbildes nav: datu failam tas nozīmē, ka būve to neiegūs.
        return $r + ['statuss' => $dati ? 'kluda' : 'bridinajums',
                     'teksts' => 'savienojums izveidojas, bet serveris to pārtrauc bez atbildes'];
    }
    $t = match (true) {
        $e === 28 && $r['dns_s'] <= 0 => 'DNS atbilde nepienāca laikā',
        $e === 28 && $tcp === false => 'TCP savienojums neizveidojas (taimauts) — no servera neatbild; tā izskatās IP bloks pret šo serveri',
        $e === 28 && ($tcp === true || $r['tcp_s'] > 0) => 'TCP savienojums izveidojas, bet TLS rokasspiediens neatbild — iespējams, ugunsmūris nomet ne-pārlūka klientus',
        $e === 28 && ($r['tcp_errno'] ?? null) !== null => 'savienojums neizveidojas (taimauts); tīrā TCP zonde: ' . sav_kludas_teksts((int)$r['tcp_errno'], ''),
        $e === 28 => 'savienojums neizveidojas (taimauts)',
        in_array($e, SAV_SERT_KLUDAS, true) => 'savienojums ir, bet sertifikātu neizdodas pārbaudīt'
            . match (true) {
                !empty($r['ca_meginats']) => ' arī ar koda papildu CA',
                !empty($r['papildu_ca']) => ' (papildu CA kopumu neizdevās sagatavot)',
                default => ' (šīs sadaļas kods papildu CA nelieto)',
            } . " ({$r['kluda']})",
        default => sav_kludas_teksts($e, $r['kluda'], !empty($r['ar_papildu_ca'])),
    };
    return $r + ['statuss' => 'kluda', 'teksts' => $t];
}

/** Servera ārējā IP no kontroles atbildes (Cloudflare trace: "ip=…"). */
function sav_servera_ip(array $rez): string {
    foreach ($rez as $r) {
        if ($r['sadala'] === 'kontrole' && preg_match('/^ip=([0-9a-f.:]+)$/mi', $r['paraugs'] ?? '', $m)) return $m[1];
    }
    return '';
}

/** Kopsavilkums: [statuss, teikums] — kas strādā, kas ne, un ko tas nozīmē. */
function sav_secinajums(array $rez): array {
    $kontrole = null; $kludas = 0; $bridin = 0; $avoti = 0; $bloks = 0; $bloka_ip = []; $tls = 0; $reg = [0, 0];
    foreach ($rez as $r) {
        if ($r['sadala'] === 'kontrole') { $kontrole = $r; continue; }
        $avoti++;
        if ($r['sadala'] === 'registrs') { $reg[1]++; if ($r['statuss'] !== 'kluda') $reg[0]++; }
        if ($r['statuss'] === 'bridinajums') $bridin++;
        if ($r['statuss'] !== 'kluda') continue;
        $kludas++;
        $tcp = $r['tcp_parbaude'] ?? null;
        if ($r['errno'] === 28 && $tcp === false) {
            $bloks++;
            $bloka_ip[($r['tcp_ip'] ?? '') !== '' ? $r['tcp_ip'] : ($r['ip'] !== '' ? $r['ip'] : $r['saimnieks'])] = true;
        }
        if ($r['errno'] === 28 && $tcp === true && $r['parvirzes'] === 0) $tls++;
    }
    if ($kontrole !== null && $kontrole['statuss'] === 'kluda') {
        return ['kluda', 'Serveris nesasniedz pat kontroles adresi (' . $kontrole['saimnieks'] . ') — serverī nestrādā ārējais internets vai DNS.'];
    }
    $nakts = $reg[1] ? " Nakts būvei strādā {$reg[0]} no {$reg[1]} Reģistra avotiem." : '';
    if (!$kludas) {
        return [$bridin ? 'bridinajums' : 'ok', "Visi $avoti avoti strādā" . ($bridin ? ", $bridin ar brīdinājumu." : '.') . $nakts];
    }
    $t = "$kludas no $avoti avotiem nestrādā.";
    if ($bloks) {
        $t .= " No nestrādājošajiem $bloks (" . count($bloka_ip) . ' IP adresēs) neatbild uz TCP savienojumu, kaut internets strādā —'
            . ' tā izskatās bloks pret servera IP (ja no cita tīkla tie atbild, tas ir apstiprināts).';
    }
    if ($tls) $t .= " $tls pieņem TCP, bet nomet TLS rokasspiedienu (iespējams, ugunsmūris).";
    return ['kluda', $t . $nakts];
}
