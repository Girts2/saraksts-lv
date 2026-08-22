<?php
/**
 * konkursi/lib/snapshot.php — starta momentuzņēmums (mazais datu fails).
 *
 * Lapas starts agrāk gaidīja 5–6 SQLite skaitīšanas vaicājumus pār ~200k rindu
 * tabulu (lēnā serverī ~10 s līdz pirmajām kartiņām). Sinhronizācijas beigās
 * šeit uzbūvē mazu JSON failu (data/start.json) ar GATAVIEM skaitiem un
 * pirmajām KONKURSI_SNAPSHOT_CARDS kartiņām katrai avotu paneļa rindai;
 * konkursi.php to iegulž HTML (<script type="application/json" id="kk-start">),
 * un JS to atrāda momentā, kamēr fonā ielādējas īstie API dati, kas pēc tam
 * klusi nomaina saturu. Fails NAV web-pieejams (htaccess bloķē /konkursi/data/)
 * — to lasa tikai PHP.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* 100 (agrāk 10): lēnā serverī pilnā API atbilde nāk ilgi, un lietotājs 10
   kartiņas izritina ātrāk, nekā tā atnāk — momentuzņēmumam jāsedz pietiekami
   dziļa ritināšana. Failu tas audzē ~10× (~0,25→~2 MB), bet HTML iet gzip. */
const KONKURSI_SNAPSHOT_CARDS = 100;

function konkursi_snapshot_path(): string {
    return __DIR__ . '/../data/start.json';
}

/**
 * Avotu paneļa dati — to pašu atbildi lieto konkursi.php ?action=sources UN
 * momentuzņēmums, tāpēc loģika dzīvo šeit vienā eksemplārā.
 * SVARĪGI: skaitiem JĀSAKRĪT ar 'list' darbības displeja filtriem
 * (konkursi_display_conds), citādi paneļa skaits nesakristu ar kartiņu ciļņu
 * skaitu (F3 kļūda).
 */
function konkursi_sources_data(PDO $pdo, bool $archive = false): array {
    [$activeSrc, $resultSrc] = konkursi_display_conds($archive, '');

    $bySource = [];
    foreach ($pdo->query("SELECT source, COUNT(*) c FROM notices
                          WHERE category = 'iepirkumi' AND $activeSrc GROUP BY source") as $r) {
        $bySource[$r['source']]['iepirkumi'] = (int)$r['c'];
    }
    // "category != 'iepirkumi'" (nevienādība) plānotājam neļāva meklēt pa indeksu
    // ar category priekšā — tas ņēma idx_notices_source_cat un katrai rindai lasīja
    // plato tabulas rindu: serverī 55 s (žurnāli 2026-08-22). Kategoriju vienādību
    // saraksts ("IN") ļauj meklēt sedzošajā idx_notices_cat_pub_source. Saraksts no
    // DB, ne konstante — jauna kategorija nekad netiek klusi izlaista.
    $cats = $pdo->query("SELECT DISTINCT category FROM notices WHERE category != 'iepirkumi'")
                ->fetchAll(PDO::FETCH_COLUMN);
    $inCats = $cats ? implode(',', array_map(static fn($c) => $pdo->quote((string)$c), $cats)) : "''";
    foreach ($pdo->query("SELECT source, category, COUNT(*) c FROM notices
                          WHERE category IN ($inCats) AND $resultSrc GROUP BY source, category") as $r) {
        $bySource[$r['source']][$r['category']] = (int)$r['c'];
    }
    // Valstis, kurām nav (vai ir tikai daļējs) nacionālais avots — rindu
    // skaita pēc pasūtītāja valsts, nevis avota. Pseido-kods '#XX' ar tabulā
    // neiespējamu '#' nekad nesadursies ar īstu source vērtību.
    //   SE — nav valsts reģistra (Lag 2019:668, sludinājumi privātās datubāzēs)
    //   MT — etenders.gov.mt lietošanas noteikumi (2.4., "Data mining") aizliedz
    //        automatizētu ieguvi, tāpēc nacionālos nevācam
    //   CY — aktīvo konkursu meklēšana ir aiz CAPTCHA; rindā TED + piešķirtie
    //        līgumi no data.gov.cy (avots CYPRUS)
    //   GE — valsts OCDS API (odapi.spa.ge) miris kopš 2020 (TLS cert
    //        izbeidzies), portāls aiz F5 WAF; rindā tikai TED, pārējais
    //        skatāms tenders.procurement.gov.ge (sk. COUNTRY_NOTE)
    //   TR/AZ — pētījumi iesaka skrāpēšanu ar proxy-rotāciju/CAPTCHA
    //        apiešanu, ko apzināti NEDARĀM
    //   AM — armeps.am OCDS API ir tik lēns/nestabils, ka pat viena lapa
    //        noildzt; to slogot pretēji lietotāja norādei (0 TED)
    $ccSt = $pdo->prepare("SELECT COUNT(*) FROM notices
                           WHERE buyer_country = ? AND category = 'iepirkumi' AND $activeSrc");
    $ccRest = $pdo->prepare("SELECT category, COUNT(*) c FROM notices
                             WHERE buyer_country = ? AND category IN ($inCats) AND $resultSrc
                             GROUP BY category");
    foreach (['SE', 'MT', 'CY', 'GB', 'GE', 'TR', 'AZ', 'AM'] as $cc) {
        $ccSt->execute([$cc]);
        $bySource['#' . $cc]['iepirkumi'] = (int)$ccSt->fetchColumn();
        $ccRest->execute([$cc]);
        foreach ($ccRest->fetchAll() as $r) {
            $bySource['#' . $cc][$r['category']] = (int)$r['c'];
        }
    }
    return [
        'sources'   => $bySource,
        'last_sync' => konkursi_meta_get($pdo, 'last_sync'),
    ];
}

/**
 * ?action=sources ar kešu meta tabulā. Avotu skaiti mainās TIKAI sinhronizācijā
 * (un līdz ar displeja logu — tāpat kā starta momentuzņēmums), tāpēc atbildi
 * glabājam meta 'sources_cache_{n|a}' ar atslēgu last_sync: sinhronizācija to
 * uzsilda beigās (konkursi_sources_cache_warm), galapunkts tikai nolasa. Ja keša
 * nav vai tas no citas sinhronizācijas — rēķina dzīvi (ar sedzošajiem indeksiem
 * tas ir sekundes daļas) un mēģina saglabāt; neizdevusies saglabāšana nav kļūda.
 * Iemesls: 2026-08-22 žurnālos šis galapunkts serverī ņēma 85–98 s.
 */
function konkursi_sources_cached(PDO $pdo, bool $archive): array {
    $key  = 'sources_cache_' . ($archive ? 'a' : 'n');
    $sync = (string)(konkursi_meta_get($pdo, 'last_sync') ?? '');
    $raw  = konkursi_meta_get($pdo, $key);
    if ($raw !== null) {
        $c = json_decode($raw, true);
        if (is_array($c) && ($c['sync'] ?? null) === $sync && is_array($c['data'] ?? null)) {
            return $c['data'];
        }
    }
    $data = konkursi_sources_data($pdo, $archive);
    try {
        konkursi_meta_set($pdo, $key, json_encode(['sync' => $sync, 'data' => $data], JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) {
        // lasīšanas ceļš (tīmekļa pieprasījums) — ja DB tobrīd aizņemta, paliekam bez keša
    }
    return $data;
}

/** Uzsilda abus ?action=sources variantus (sauc sinhronizācijas beigās pēc ks_refresh_meta). */
function konkursi_sources_cache_warm(PDO $pdo): void {
    $sync = (string)(konkursi_meta_get($pdo, 'last_sync') ?? '');
    foreach ([false, true] as $archive) {
        $data = konkursi_sources_data($pdo, $archive);
        konkursi_meta_set($pdo, 'sources_cache_' . ($archive ? 'a' : 'n'),
            json_encode(['sync' => $sync, 'data' => $data], JSON_UNESCAPED_UNICODE));
    }
}

/** Galvenes statistika — tā pati atbilde, ko dod ?action=stats. */
function konkursi_stats_data(PDO $pdo): array {
    $counts  = json_decode(konkursi_meta_get($pdo, 'counts') ?? '{}', true) ?: [];
    $sources = json_decode(konkursi_meta_get($pdo, 'sources') ?? '{}', true) ?: [];
    $natCountries = konkursi_meta_get($pdo, 'national_countries');
    if ($natCountries === null) {
        $natCountries = (int)$pdo->query(
            "SELECT COUNT(DISTINCT buyer_country) FROM notices
             WHERE source <> 'TED' AND buyer_country IS NOT NULL AND buyer_country <> ''"
        )->fetchColumn();
    }
    return [
        'counts'    => $counts,
        'sources'   => $sources,
        'total'     => array_sum($counts),
        'countries' => (int)(konkursi_meta_get($pdo, 'countries') ?? 0),
        'national_countries' => (int)$natCountries,
        'last_sync' => konkursi_meta_get($pdo, 'last_sync'),
    ];
}

/**
 * Uzbūvē momentuzņēmumu: galvenes statistika, avotu panelis un katrai paneļa
 * rindai — 4 ciļņu skaiti + pirmās kartiņas ('iepirkumi', 'jaunākie vispirms').
 * Atslēgām JĀSAKRĪT ar SOURCE_META 'code' vērtībām konkursi/js/konkursi.js:
 * tukša = "Visi avoti", komats = vairāki avoti vienā rindā, '#XX' = valsts
 * rinda (filtrē pēc buyer_country). Iztrūkstoša atslēga nav kļūda — JS tad
 * vienkārši rāda parasto ielādes ritenīti.
 */
function konkursi_snapshot_build(PDO $pdo): array {
    [$active, $result] = konkursi_display_conds(false);

    $KEYS = [
        '', 'TED',
        'IUB,MODTI,RSTI,ASTI,LDZ', 'CVPIS', 'RHR',                              // Baltija
        'HILMA', 'KOMMERS', 'UDBUD,COMDIA', 'DOFFIN', 'ISUTB',                  // Ziemeļeiropa
        'BZP', 'BKMS', 'VVZ', 'UVO', 'ATKD', 'EKR', 'SIMAP', 'LIVERG',          // Centrāleiropa
        'PROZORRO', 'MTENDER',                                                  // Austrumeiropa
        'TENDERNED', 'BOSA', 'BOAMP', '#GB', 'ETENDERS',                        // Rietumeiropa
        'ANAC', 'PLACSP', 'BASE', 'KIMDIS', '#MT', '#CY',                       // Dienvideiropa
        'ENAR', 'EOJN', 'SEAP', 'EOP', 'JNRS', 'EJN', 'ESJN', 'CEJN', 'APPAL',  // Balkāni
        '#TR', '#GE', '#AZ', '#AM',                                             // Kaukāzs
    ];
    $cats = ['iepirkumi', 'rezultati', 'izmainas', 'citi'];

    // Tie paši lauki, ko atdod ?action=list (konkursi.php) — kartiņu renderis abos
    // ceļos saņem identisku rindu, tāpēc nomaiņa ir vizuāli nemanāma.
    $cols = 'n.id, n.source, n.category, n.title, n.title_lv, n.buyer_name, n.buyer_country, n.buyer_activity,
             n.procure_nature, n.publication_date, n.deadline_date, n.publication_number,
             n.budget, n.currency, n.funding_program, n.main_cpv';
    $order = 'COALESCE(n.publication_date, n.first_seen) DESC, n.id DESC';

    $lists = [];
    foreach ($KEYS as $key) {
        // Atslēga → SQL nosacījums (bez kategorijas).
        $conds = [];
        $args  = [];
        if ($key !== '') {
            if ($key[0] === '#') {
                $conds[] = 'n.buyer_country = :cc';
                $args[':cc'] = substr($key, 1);
            } else {
                $srcs = explode(',', $key);
                $ph = [];
                foreach ($srcs as $i => $s) { $ph[] = ":s$i"; $args[":s$i"] = $s; }
                $conds[] = count($ph) === 1 ? "n.source = {$ph[0]}" : 'n.source IN (' . implode(',', $ph) . ')';
            }
        }
        $where = $conds ? implode(' AND ', $conds) . ' AND ' : '';

        // Ciļņu skaiti — pa vienam COUNT uz kategoriju (iet pa nosedzošajiem indeksiem).
        $counts = [];
        foreach ($cats as $c) {
            $dc = $c === 'iepirkumi' ? $active : $result;
            $st = $pdo->prepare("SELECT COUNT(*) FROM notices n WHERE {$where}n.category = :cat AND $dc");
            $st->execute($args + [':cat' => $c]);
            $counts[$c] = (int)$st->fetchColumn();
        }

        $st = $pdo->prepare("SELECT $cols FROM notices n WHERE {$where}n.category = 'iepirkumi' AND $active
                             ORDER BY $order LIMIT " . KONKURSI_SNAPSHOT_CARDS);
        $st->execute($args);
        $rows = $st->fetchAll();

        // Garos teksta laukus apcērt — momentuzņēmums ir priekšskats, pilnā versija
        // atnāk ar īstajiem API datiem pēc pāris sekundēm; failam jāpaliek mazam.
        foreach ($rows as &$r) {
            foreach (['title' => 300, 'title_lv' => 300, 'buyer_name' => 200] as $f => $max) {
                if (isset($r[$f]) && is_string($r[$f]) && mb_strlen($r[$f], 'UTF-8') > $max) {
                    $r[$f] = mb_substr($r[$f], 0, $max - 1, 'UTF-8') . '…';
                }
            }
        }
        unset($r);

        $lists[$key] = ['counts' => $counts, 'notices' => $rows];
    }

    return [
        'generated' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Riga')))->format(DATE_ATOM),
        'stats'     => konkursi_stats_data($pdo),
        'sources'   => konkursi_sources_data($pdo, false),
        'lists'     => $lists,
    ];
}

/** Uzraksta momentuzņēmumu atomāri (tmp + rename) un atdod faila ceļu. */
function konkursi_snapshot_write(PDO $pdo): string {
    $path = konkursi_snapshot_path();
    @mkdir(dirname($path), 0775, true);
    $json = json_encode(konkursi_snapshot_build($pdo), JSON_UNESCAPED_UNICODE);
    if ($json === false) throw new RuntimeException('Momentuzņēmuma JSON kodēšana neizdevās.');
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json) === false) {
        throw new RuntimeException("Nevar ierakstīt $tmp");
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Nevar pārvietot $tmp → $path");
    }
    return $path;
}
