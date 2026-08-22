<?php
/**
 * konkursi.php — ES publisko iepirkumu konkursi (TED dati).
 *
 * Augšdaļā JSON API (?action=list|detail|countries|cpv|stats) — lasa tikai
 * lokālo SQLite (konkursi/db/tenders.db); TED serveri lapas skatīšanās laikā
 * NEKAD netiek izsaukti. Datus atjaunina konkursi/bin/sync.php (manuāli vai cron).
 */
declare(strict_types=1);

/* Izvades gzip (2026-08-05): iegultais momentuzņēmums (100 kartiņas × ~44
   rindas) HTML izaudzē līdz ~2 MB — bez saspiešanas tas lēnā serverī apēstu
   visu momentānā starta ieguvumu (gzip: ~2,2 MB → ~0,45 MB). Der arī API JSON
   atbildēm. ob_gzhandler pats skatās Accept-Encoding; ja hostings jau saspiež
   caur zlib.output_compression, otrreiz nesāk. */
if (!ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}

require_once __DIR__ . '/lib/applog.php';
applog_boot('konkursi');   // kopīgais notikumu žurnāls (lib/applog.php)

// --- BACKEND: JSON API ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=60');
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    require_once __DIR__ . '/konkursi/lib/db.php';

    /** ES/EEZ valstu nosaukumi latviski (nominatīvs sarakstiem). */
    $COUNTRY_LV = [
        'LV' => 'Latvija', 'LT' => 'Lietuva', 'EE' => 'Igaunija', 'PL' => 'Polija',
        'FI' => 'Somija', 'SE' => 'Zviedrija', 'DK' => 'Dānija', 'DE' => 'Vācija',
        'FR' => 'Francija', 'NL' => 'Nīderlande', 'BE' => 'Beļģija', 'LU' => 'Luksemburga',
        'IE' => 'Īrija', 'AT' => 'Austrija', 'CZ' => 'Čehija', 'SK' => 'Slovākija',
        'HU' => 'Ungārija', 'SI' => 'Slovēnija', 'HR' => 'Horvātija', 'RO' => 'Rumānija',
        'BG' => 'Bulgārija', 'GR' => 'Grieķija', 'IT' => 'Itālija', 'ES' => 'Spānija',
        'PT' => 'Portugāle', 'CY' => 'Kipra', 'MT' => 'Malta', 'NO' => 'Norvēģija',
        'IS' => 'Islande', 'LI' => 'Lihtenšteina', 'CH' => 'Šveice', 'GB' => 'Lielbritānija',
        'UA' => 'Ukraina', 'MD' => 'Moldova', 'RS' => 'Serbija', 'BA' => 'Bosnija un Hercegovina',
        'AL' => 'Albānija', 'MK' => 'Ziemeļmaķedonija', 'ME' => 'Melnkalne', 'TR' => 'Turcija',
        'GE' => 'Gruzija', 'AZ' => 'Azerbaidžāna', 'AM' => 'Armēnija',
        'AD' => 'Andora', 'MC' => 'Monako', 'SM' => 'Sanmarīno',
        'XK' => 'Kosova',   // parādās caur UNDP (ANO) iepirkumiem
    ];

    $out = static function (array $data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    };

    try {
        $pdo = konkursi_db();
        $action = (string)$_GET['action'];
        $cats = ['iepirkumi', 'rezultati', 'izmainas', 'citi'];

        // ── Kopīgā filtru nolasīšana ──
        $cat      = in_array($_GET['cat'] ?? '', $cats, true) ? (string)$_GET['cat'] : 'iepirkumi';
        $q        = trim((string)($_GET['q'] ?? ''));
        $country  = strtoupper(trim((string)($_GET['country'] ?? '')));
        $nature   = trim((string)($_GET['nature'] ?? ''));
        $activity = trim((string)($_GET['activity'] ?? ''));
        $cpv      = trim((string)($_GET['cpv'] ?? ''));
        $buyer    = trim((string)($_GET['buyer'] ?? ''));
        $source   = strtoupper(trim((string)($_GET['source'] ?? '')));
        if (mb_strlen($buyer, 'UTF-8') > KONKURSI_BUYER_QUERY_MAX) {
            $buyer = mb_substr($buyer, 0, KONKURSI_BUYER_QUERY_MAX, 'UTF-8');
        }
        if (!preg_match('/^[A-Z]{2}$/', $country)) $country = '';
        // 'tirgus-izpete' ir pseido-veids: filtrē pēc avota (MODTI ierakstiem
        // procure_nature nav), lai "Darbu veids" izvēlnē var atlasīt tikai izpētes.
        if (!in_array($nature, ['works', 'supplies', 'services', 'tirgus-izpete'], true)) $nature = '';
        if (!preg_match('/^[a-z0-9-]{1,30}$/', $activity)) $activity = '';
        if (!preg_match('/^\d{2}$/', $cpv)) $cpv = '';
        // Avots var būt vairāki, atdalīti ar komatu (piem. 'UDBUD,COMDIA' — Dānijai
        // ir divi nacionālie avoti, bet sānu panelī tie ir viena rinda).
        $VALID_SOURCES = ['TED', 'IUB', 'MODTI', 'RSTI', 'ASTI', 'LDZ', 'CVPIS', 'RHR', 'HILMA', 'DOFFIN', 'UDBUD', 'BZP', 'BKMS', 'BOAMP', 'ETENDERS', 'TENDERNED', 'PLACSP', 'VVZ', 'UVO', 'BOSA', 'ATKD', 'SEAP', 'EOP', 'KIMDIS', 'ENAR', 'EOJN', 'EKR', 'BASE', 'ANAC', 'CYPRUS', 'UKFTS', 'UKCF', 'UKPCS', 'COMDIA', 'KOMMERS', 'ISUTB', 'SIMAP', 'PROZORRO', 'LIVERG', 'MTENDER', 'EJN', 'ESJN', 'JNRS', 'CEJN', 'APPAL', 'WB', 'EBRD', 'UNDP'];
        $sourceList = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $source)),
            fn($s) => in_array($s, $VALID_SOURCES, true)
        )));
        $source = $sourceList ? implode(',', $sourceList) : '';

        /** SQL nosacījums + parametri avota filtram (viens vai vairāki). */
        $srcCond = function (array $list, array &$args, string $t = 'n'): ?string {
            if (!$list) return null;
            $ph = [];
            foreach ($list as $i => $s) { $ph[] = ":src$i"; $args[":src$i"] = $s; }
            return count($ph) === 1 ? "$t.source = {$ph[0]}" : "$t.source IN (" . implode(',', $ph) . ')';
        };

        // Pārāk īsa ievade (1 rakstzīme) meklēšanu nefiltrē — tas būtu pilns skens.
        // Frontend tāpat sūta tikai no 3 rakstzīmēm; šis sargā tiešus API izsaukumus.
        if (mb_strlen($q, 'UTF-8') < 2) $q = '';
        $useFts = $q !== '' && konkursi_fts_enabled($pdo);
        $match  = $useFts ? konkursi_fts_query($q) : '';
        if ($useFts && $match === '') { $useFts = false; $q = ''; }   // nav lietojamu terminu

        // Nosacījumi BEZ kategorijas (kategoriju pieliek atsevišķi — vajag counts visām cilnēm)
        $conds = [];
        $args  = [];
        if ($c = $srcCond($sourceList, $args)) $conds[] = $c;
        if ($country !== '')  { $conds[] = 'n.buyer_country = :country';   $args[':country'] = $country; }
        if ($nature === 'tirgus-izpete') { $conds[] = "n.source IN ('MODTI','RSTI','ASTI','LDZ')"; }
        elseif ($nature !== '')          { $conds[] = 'n.procure_nature = :nature'; $args[':nature'] = $nature; }
        if ($activity !== '') { $conds[] = 'n.buyer_activity = :activity'; $args[':activity'] = $activity; }
        if ($cpv !== '')      { $conds[] = "n.main_cpv LIKE :cpv";         $args[':cpv'] = $cpv . '%'; }
        // Precīza sakritība — vērtība nāk no izvēlnes, ko aizpilda 'buyers' darbība.
        if ($buyer !== '')    { $conds[] = 'n.buyer_name = :buyer';        $args[':buyer'] = $buyer; }
        $joinFts = '';
        if ($q !== '') {
            if ($useFts) {
                $joinFts = ' JOIN notices_fts f ON f.rowid = n.rowid';
                $conds[] = 'f.notices_fts MATCH :match';
                $args[':match'] = $match;
            } else {
                $conds[] = '(lower(n.title) LIKE :like OR lower(n.title_lv) LIKE :like OR lower(n.buyer_name) LIKE :like OR lower(n.description) LIKE :like)';
                $args[':like'] = '%' . mb_strtolower($q, 'UTF-8') . '%';
            }
        }
        // Displeja logi: dati glabātuvē tiek KRĀTI (netiek dzēsti), tāpēc noklusējuma
        // skats tos ierobežo laikā — tie paši logi, ko agrāk piemēroja ks_prune. Ar
        // &archive=1 filtru atmet un rāda visu uzkrāto vēsturi. Nosacījumi dzīvo
        // konkursi_display_conds (lib/config.php) — tos pašus lieto starta
        // momentuzņēmums (lib/snapshot.php), lai skaitļi abos ceļos sakrīt.
        $archive = ((string)($_GET['archive'] ?? '')) === '1';
        [$activeCond, $resultCond] = konkursi_display_conds($archive);
        // Kategorijas displeja nosacījums (piemēro visur, kur filtrē pēc kategorijas).
        $catDisplayCond = fn(string $c): ?string =>
            $c === 'iepirkumi' ? $activeCond : ($c === 'rezultati' || $c === 'izmainas' || $c === 'citi' ? $resultCond : null);

        if ($action === 'list') {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $sort = (string)($_GET['sort'] ?? 'jaunakie');
            $offset = ($page - 1) * KONKURSI_PAGE_SIZE;

            // Ciļņu skaitītāji (ar aktīvajiem filtriem)
            $counts = [];
            if ($q !== '') {
                // Meklēšanas skenēšana ir dārgā daļa — visus 4 skaitītājus savāc VIENĀ
                // caurskatē ar nosacītām summām, nevis 4 atsevišķos COUNT (FTS 5×→2×).
                $sums = [];
                foreach ($cats as $c) {
                    $dc = $catDisplayCond($c);
                    $sums[] = "SUM(n.category = " . $pdo->quote($c) . ($dc ? " AND $dc" : '') . ") AS \"$c\"";
                }
                $sql = "SELECT " . implode(', ', $sums) . " FROM notices n$joinFts"
                     . ($conds ? ' WHERE ' . implode(' AND ', $conds) : '');
                $st = $pdo->prepare($sql);
                $st->execute($args);
                $row = $st->fetch() ?: [];
                foreach ($cats as $c) $counts[$c] = (int)($row[$c] ?? 0);
            } else {
                // Bez meklēšanas atsevišķie COUNT iet pa kategoriju indeksiem — lētāk
                // nekā viena pilna caurskate.
                foreach ($cats as $c) {
                    $cc = $conds;
                    $cc[] = 'n.category = :cat';
                    if ($dc = $catDisplayCond($c)) $cc[] = $dc;
                    $sql = "SELECT COUNT(*) FROM notices n$joinFts WHERE " . implode(' AND ', $cc);
                    $st = $pdo->prepare($sql);
                    $st->execute($args + [':cat' => $c]);
                    $counts[$c] = (int)$st->fetchColumn();
                }
            }

            $cc = $conds;
            $cc[] = 'n.category = :cat';
            if ($dc = $catDisplayCond($cat)) $cc[] = $dc;

            $order = match ($sort) {
                'termins' => '(n.deadline_date IS NULL), n.deadline_date ASC, n.id ASC',
                'budzets' => '(n.budget IS NULL), n.budget DESC, n.id ASC',
                // Avotiem bez datumiem (Comdia) kārto pēc pirmās redzēšanas — citādi
                // NULL publikācijas datums tos vienmēr nogrūstu saraksta beigās.
                default   => 'COALESCE(n.publication_date, n.first_seen) DESC, n.id DESC',
            };

            $sql = "SELECT n.id, n.source, n.category, n.title, n.title_lv, n.buyer_name, n.buyer_country, n.buyer_activity,
                           n.procure_nature, n.publication_date, n.deadline_date, n.publication_number,
                           n.budget, n.currency, n.funding_program, n.main_cpv
                    FROM notices n$joinFts WHERE " . implode(' AND ', $cc) . "
                    ORDER BY $order LIMIT " . (KONKURSI_PAGE_SIZE + 1) . " OFFSET $offset";
            $st = $pdo->prepare($sql);
            $st->execute($args + [':cat' => $cat]);
            $rows = $st->fetchAll();

            $hasMore = count($rows) > KONKURSI_PAGE_SIZE;
            if ($hasMore) array_pop($rows);

            $out([
                'counts'   => $counts,
                'notices'  => $rows,
                'page'     => $page,
                'has_more' => $hasMore,
                'fts'      => $useFts,
            ]);
        }

        if ($action === 'detail') {
            $id = (string)($_GET['id'] ?? '');
            if ($id === '') $out(['error' => 'Trūkst id'], 400);
            $st = $pdo->prepare('SELECT * FROM notices WHERE id = ?');
            $st->execute([$id]);
            $n = $st->fetch();
            if (!$n) $out(['error' => 'Paziņojums nav atrasts'], 404);
            foreach (['cpv_codes', 'lots', 'organizations'] as $f) {
                $n[$f] = json_decode((string)($n[$f] ?? '[]'), true) ?: [];
            }
            $n['notice_contact'] = json_decode((string)($n['notice_contact'] ?? '{}'), true) ?: new stdClass();
            $out(['notice' => $n]);
        }

        // Meklēšanas nosacījums izvēlņu (valsts/CPV/pasūtītāja) skaitītājiem — lai
        // iekavu skaitļi rāda tieši atslēgvārdam atbilstošos, ne kopējos. Tas pats
        // FTS/LIKE zars, kas 'list' darbībā.
        $facetSearch = function (array &$cc, array &$ca) use ($q, $useFts, $match): string {
            if ($q === '') return '';
            if ($useFts) {
                $cc[] = 'f.notices_fts MATCH :match';
                $ca[':match'] = $match;
                return ' JOIN notices_fts f ON f.rowid = n.rowid';
            }
            $cc[] = '(lower(n.title) LIKE :like OR lower(n.title_lv) LIKE :like OR lower(n.buyer_name) LIKE :like OR lower(n.description) LIKE :like)';
            $ca[':like'] = '%' . mb_strtolower($q, 'UTF-8') . '%';
            return '';
        };

        if ($action === 'countries') {
            $cc = ['n.category = :cat'];
            $ca = [':cat' => $cat];
            if ($c = $srcCond($sourceList, $ca)) $cc[] = $c;
            if ($dc = $catDisplayCond($cat)) $cc[] = $dc;
            $joinF = $facetSearch($cc, $ca);
            // MIN/MAX publikācijas datums: rezultātu griesti (KONKURSI_RESULTS_CAP)
            // katrai valstij nogriež ATŠĶIRĪGU logu (DE ~4 dienas, LV viss 60 d) —
            // bez diapazona skaits maldina, ka pārklājums visām valstīm vienāds.
            $sql = 'SELECT n.buyer_country code, COUNT(*) cnt,
                           MIN(n.publication_date) dfrom, MAX(n.publication_date) dto
                    FROM notices n' . $joinF . ' WHERE ' . implode(' AND ', $cc)
                 // Otrā atslēga (kods) — bez tās vienāda skaita valstis kārtojas pēc
                 // tā, kuru indeksu izvēlas plānotājs, un izvēlne pārkārtojas nejauši.
                 . ' GROUP BY n.buyer_country ORDER BY cnt DESC, n.buyer_country ASC';
            $st = $pdo->prepare($sql);
            $st->execute($ca);
            $list = [];
            foreach ($st->fetchAll() as $r) {
                $code = (string)$r['code'];
                if ($code === '') continue;
                $list[] = ['code' => $code, 'count' => (int)$r['cnt'],
                           'name' => $COUNTRY_LV[$code] ?? $code,
                           'from' => $r['dfrom'], 'to' => $r['dto']];
            }
            $out(['countries' => $list]);
        }

        // Pasūtītāju saraksts kombinētajam laukam. Bez 'bq' — biežākie (nolaižamais
        // saraksts); ar 'bq' — meklē pa VISIEM attiecīgās valsts/avota pasūtītājiem,
        // ne tikai augšējiem 600. Ņem vērā kategoriju, avotu un valsti.
        if ($action === 'buyers') {
            $bq = trim((string)($_GET['bq'] ?? ''));
            if (mb_strlen($bq, 'UTF-8') > KONKURSI_BUYER_QUERY_MAX) {
                $bq = mb_substr($bq, 0, KONKURSI_BUYER_QUERY_MAX, 'UTF-8');
            }
            $cc = ['n.category = :cat', 'n.buyer_name IS NOT NULL', "n.buyer_name <> ''"];
            $ca = [':cat' => $cat];
            if ($c = $srcCond($sourceList, $ca)) $cc[] = $c;
            if ($country !== '') { $cc[] = 'n.buyer_country = :country'; $ca[':country'] = $country; }
            if ($dc = $catDisplayCond($cat)) $cc[] = $dc;
            $joinF = $facetSearch($cc, $ca);

            if ($bq !== '') {
                $lower = mb_strtolower($bq, 'UTF-8');
                $cc[] = 'lower(n.buyer_name) LIKE :bq';
                $ca[':bq']  = '%' . $lower . '%';
                $ca[':bqp'] = $lower . '%';
                // Atbilstošākie vispirms: sākas ar ievadīto, tad pārējie pēc biežuma.
                $order = 'CASE WHEN lower(n.buyer_name) LIKE :bqp THEN 0 ELSE 1 END, cnt DESC, name ASC';
                $limit = KONKURSI_BUYER_SEARCH_MAX;
            } else {
                $order = 'cnt DESC, name ASC';
                $limit = KONKURSI_BUYER_SUGGEST_MAX;
            }
            $sql = 'SELECT n.buyer_name name, COUNT(*) cnt FROM notices n' . $joinF . ' WHERE ' . implode(' AND ', $cc)
                 . ' GROUP BY n.buyer_name ORDER BY ' . $order . ' LIMIT ' . $limit;
            $st = $pdo->prepare($sql);
            $st->execute($ca);
            $out(['buyers' => $st->fetchAll(), 'query' => $bq]);
        }

        if ($action === 'cpv') {
            $cc = ['n.category = :cat', 'n.main_cpv IS NOT NULL'];
            $ca = [':cat' => $cat];
            if ($c = $srcCond($sourceList, $ca)) $cc[] = $c;
            if ($dc = $catDisplayCond($cat)) $cc[] = $dc;
            $joinF = $facetSearch($cc, $ca);
            $sql = 'SELECT substr(n.main_cpv, 1, 2) div, COUNT(*) cnt FROM notices n' . $joinF . ' WHERE ' . implode(' AND ', $cc)
                 // Otrā atslēga — tā pati stabilitāte, kas 'countries' izvēlnē.
                 . ' GROUP BY div ORDER BY cnt DESC, div ASC';
            $st = $pdo->prepare($sql);
            $st->execute($ca);
            $out(['divisions' => $st->fetchAll()]);
        }

        // Avotu panelis: katram avotam skaits pa kategorijām (iepirkumiem — tikai
        // aktīvie). Loģika dzīvo lib/snapshot.php (konkursi_sources_data) — to pašu
        // izmanto starta momentuzņēmums, tāpēc panelis un iegultie dati sakrīt.
        if ($action === 'sources') {
            require_once __DIR__ . '/konkursi/lib/snapshot.php';
            $out(konkursi_sources_cached($pdo, $archive));
        }

        if ($action === 'stats') {
            require_once __DIR__ . '/konkursi/lib/snapshot.php';
            $out(konkursi_stats_data($pdo));
        }

        $out(['error' => 'Nezināma darbība'], 400);
    } catch (Throwable $e) {
        error_log('konkursi.php API: ' . $e->getMessage());
        $out(['error' => 'Servera kļūda'], 500);
    }
}

// --- FRONTEND: HTML lapa ---
$pageTitle = 'Publisko iepirkumu konkursi — ES TED un 44 valstu nacionālie avoti';
$pageDesc  = 'Aktuālie publisko iepirkumu konkursi no oficiālajiem avotiem: ES TED un ~46 nacionālie un starptautiskie avoti 44 valstīs, virsraksti latviski. Meklē pēc valsts, nozares (CPV) un darbu veida.';
// ?v= = satura hash (lib/assets.php), ne filemtime — deploy bez satura maiņas nemaina URL.
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/assets.php';
$cssHref = reg_asset_v('/konkursi/css/konkursi.css');
$jsSrc   = reg_asset_v('/konkursi/js/konkursi.js');
?>
<!DOCTYPE html>
<html lang="lv">

<?php include 'registrs/head/head.php'; ?>

<head>
    <link rel="canonical" href="https://saraksts.lv/konkursi.php" />
    <link rel="stylesheet" href="<?php echo $cssHref; ?>">
</head>

<body>

    <?php include 'registrs/header.php'; ?>

    <div class="container" style="padding-top: 25px; padding-bottom: 0;">
        <div class="data-source-notice">
            <div class="notice-text">
                Šajā sadaļā publicētais iepirkumu apkopojums ir veidots no oficiālajiem avotiem: Eiropas Savienības publisko iepirkumu portāla
                <span class="pseudo-link">ted.europa.eu</span> (Tenders Electronic Daily — ES Oficiālā Vēstneša S sērijas pielikums, © Eiropas Savienība)
                un nacionālo (zem ES sliekšņa esošo) iepirkumu sistēmām: <span class="pseudo-link">open.iub.gov.lv</span> (Latvija),
                <span class="pseudo-link">viesiejipirkimai.lt</span> (Lietuva), <span class="pseudo-link">riigihanked.riik.ee</span> (Igaunija),
                <span class="pseudo-link">hankintailmoitukset.fi</span> (Somija), <span class="pseudo-link">doffin.no</span> (Norvēģija),
                <span class="pseudo-link">udbud.dk</span> (Dānija), <span class="pseudo-link">ezamowienia.gov.pl</span> (Polija),
                <span class="pseudo-link">oeffentlichevergabe.de</span> (Vācija), <span class="pseudo-link">boamp.fr</span> (Francija),
                <span class="pseudo-link">etenders.gov.ie</span> (Īrija), <span class="pseudo-link">tenderned.nl</span> (Nīderlande),
                <span class="pseudo-link">contrataciondelestado.es</span> (Spānija), <span class="pseudo-link">vvz.nipez.cz</span> (Čehija),
                <span class="pseudo-link">uvo.gov.sk</span> (Slovākija), <span class="pseudo-link">publicprocurement.be</span> (Beļģija),
                <span class="pseudo-link">data.gv.at</span> Kerndaten platformas (Austrija), <span class="pseudo-link">e-licitatie.ro</span> (Rumānija),
                <span class="pseudo-link">app.eop.bg</span> (Bulgārija), <span class="pseudo-link">eprocurement.gov.gr</span> ΚΗΜΔΗΣ (Grieķija),
                <span class="pseudo-link">enarocanje.si</span> (Slovēnija), <span class="pseudo-link">eojn.hr</span> (Horvātija),
                <span class="pseudo-link">ekr.gov.hu</span> (Ungārija), <span class="pseudo-link">base.gov.pt</span> (Portugāle),
                <span class="pseudo-link">dati.anticorruzione.it</span> ANAC atvērtie dati (Itālija),
                <span class="pseudo-link">kommersannons.se</span> (Zviedrija, Kommers Annons eLite), <span class="pseudo-link">utbodsvefur.is</span> (Islande),
                <span class="pseudo-link">comdia.com</span> (Dānijas pašvaldības), <span class="pseudo-link">data.gov.cy</span> (Kipra),
                <span class="pseudo-link">find-tender.service.gov.uk</span>, <span class="pseudo-link">contractsfinder.service.gov.uk</span> un
                <span class="pseudo-link">publiccontractsscotland.gov.uk</span> (Apvienotā Karaliste, OGL v3.0),
                <span class="pseudo-link">simap.ch</span> (Šveice), <span class="pseudo-link">prozorro.gov.ua</span> (Ukraina),
                <span class="pseudo-link">vergaben.llv.li</span> (Lihtenšteina), <span class="pseudo-link">mtender.gov.md</span> (Moldova),
                <span class="pseudo-link">ejn.gov.ba</span> (Bosnija un Hercegovina), <span class="pseudo-link">e-nabavki.gov.mk</span> (Ziemeļmaķedonija),
                <span class="pseudo-link">jnportal.ujn.gov.rs</span> (Serbija), <span class="pseudo-link">cejn.gov.me</span> (Melnkalne)
                un <span class="pseudo-link">app.gov.al</span> (Albānija).
                Azerbaidžānai, Armēnijai, Gruzijai un Turcijai, kur nacionālā plūsma nav pieejama, papildus rādām
                starptautisko institūciju iepirkumus: <span class="pseudo-link">search.worldbank.org</span>
                (Pasaules Banka, CC BY 4.0), <span class="pseudo-link">ecepp.ebrd.com</span> (EBRD) un
                <span class="pseudo-link">procurement-notices.undp.org</span> (ANO Attīstības programma, Europe &amp; CIS).
                Apkopojums sagatavots tikai informatīvos nolūkos — oficiālā un juridiski saistošā informācija, pilnā dokumentācija
                un pieteikšanās vienmēr notiek oriģinālajā paziņojumā TED portālā, EIS sistēmā vai attiecīgās valsts iepirkumu sistēmā.
            </div>
            <button class="notice-toggle" aria-label="Izvērst/sakļaut">+</button>
        </div>
    </div>

    <div class="container kk-container">

        <div class="kk-header">
            <h1>ES publisko iepirkumu konkursi</h1>
            <div class="kk-stats" id="kk-stats"></div>
        </div>

        <!-- Trīs paneļu izkārtojums: avoti | saraksts | atšifrējums -->
        <div class="kk-layout">

            <!-- KREISĀ KOLONNA: avoti (TED + nacionālie pa valstīm) ar kategoriju skaitiem -->
            <aside class="kk-sources" aria-label="Iepirkumu avoti">
                <div class="kk-sources-title">Iepirkumu avoti</div>
                <div id="kk-sources-list" class="kk-sources-list">
                    <div class="kk-loading"><div class="kk-spinner"></div></div>
                </div>
                <div class="kk-sources-legend">
                    <span><i class="kk-dot kk-dot-a"></i> aktīvie</span>
                    <span><i class="kk-dot kk-dot-r"></i> rezultāti</span>
                    <span><i class="kk-dot kk-dot-g"></i> grozījumi</span>
                    <span><i class="kk-dot kk-dot-c"></i> citi</span>
                </div>
            </aside>

            <!-- VIDĒJĀ KOLONNA: cilnes, filtri, saraksts -->
            <section id="kk-main" class="kk-main">
                <div class="kk-tabs" id="kk-tabs" role="tablist" aria-label="Paziņojumu kategorijas">
                    <button class="kk-tab active" data-cat="iepirkumi">Aktīvie <span class="kk-count" id="kk-cnt-iepirkumi">…</span></button>
                    <button class="kk-tab" data-cat="rezultati">Rezultāti <span class="kk-count" id="kk-cnt-rezultati">…</span></button>
                    <button class="kk-tab" data-cat="izmainas">Grozījumi <span class="kk-count" id="kk-cnt-izmainas">…</span></button>
                    <button class="kk-tab" data-cat="citi">Citi <span class="kk-count" id="kk-cnt-citi">…</span></button>
                    <!-- Valodas pārslēgs: paliek #kk-tabs iekšā (ārpus tā globāls CSS saspiež),
                         bet grupa ar flex-basis:100% nolec jaunā rindā, labajā pusē zem cilnēm. -->
                    <div class="kk-lang-group">
                        <button class="kk-lang-btn kk-lang-first" data-lang="lv" title="Rādīt virsrakstus latviski (ja iztulkoti)">Latviešu valodā</button>
                        <button class="kk-lang-btn" data-lang="orig" title="Rādīt virsrakstus oriģinālvalodā">Oriģinālvaloda</button>
                    </div>
                </div>

                <div class="kk-filters">
                    <div class="kk-fgroup kk-fgrow">
                        <label for="kk-search">Meklēt</label>
                        <input type="search" id="kk-search" placeholder="Nosaukums, pasūtītājs, atslēgvārds…" autocomplete="off">
                    </div>
                    <div class="kk-fgroup">
                        <label for="kk-country">Valsts</label>
                        <select id="kk-country"><option value="">Visas valstis</option></select>
                    </div>
                    <div class="kk-fgroup">
                        <label for="kk-cpv">Joma (CPV)</label>
                        <select id="kk-cpv"><option value="">Visas jomas</option></select>
                    </div>
                    <div class="kk-fgroup">
                        <label for="kk-nature">Darbu veids</label>
                        <select id="kk-nature">
                            <option value="">Visi veidi</option>
                            <option value="works">Būvdarbi</option>
                            <option value="supplies">Piegādes</option>
                            <option value="services">Pakalpojumi</option>
                            <option value="tirgus-izpete" style="background:#fde68a;color:#713f12;">Tirgus izpēte</option>
                        </select>
                    </div>
                    <div class="kk-fgroup kk-fgrow">
                        <label for="kk-buyer">Pasūtītājs</label>
                        <div class="kk-combo">
                            <input type="text" id="kk-buyer" class="kk-combo-input"
                                   placeholder="Visi pasūtītāji — sāc rakstīt, lai meklētu"
                                   autocomplete="off" role="combobox" aria-expanded="false"
                                   aria-controls="kk-buyer-pop" aria-autocomplete="list">
                            <button type="button" id="kk-buyer-clear" class="kk-combo-clear hidden"
                                    aria-label="Notīrīt pasūtītāju">×</button>
                            <button type="button" id="kk-buyer-toggle" class="kk-combo-toggle"
                                    aria-label="Rādīt biežākos pasūtītājus" tabindex="-1">▾</button>
                            <ul id="kk-buyer-pop" class="kk-combo-pop hidden" role="listbox"></ul>
                        </div>
                    </div>
                    <div class="kk-fgroup">
                        <label for="kk-sort">Kārtot</label>
                        <select id="kk-sort">
                            <option value="jaunakie">Jaunākie vispirms</option>
                            <option value="termins">Tuvākais termiņš</option>
                            <option value="budzets">Lielākais budžets</option>
                        </select>
                    </div>
                    <button type="button" id="kk-reset" class="kk-reset" title="Notīrīt filtrus">Notīrīt</button>
                </div>

                <div id="kk-list" class="kk-list" aria-live="polite"></div>

                <!-- Bezgalīgā ritināšana: sensors ielādē nākamo lapu, kad tuvojas saraksta beigām. -->
                <div id="kk-sentinel" class="kk-sentinel" aria-hidden="true"></div>
                <div id="kk-more-status" class="kk-more-status" role="status" aria-live="polite"></div>
            </section>

            <!-- LABĀ KOLONNA: izvēlētā konkursa atšifrējums -->
            <aside class="kk-detailpane" id="kk-detailpane" aria-label="Konkursa atšifrējums">
                <div id="kk-detail-body" class="kk-detail-body">
                    <div class="kk-empty-detail">
                        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <line x1="9" y1="9" x2="15" y2="9"/>
                            <line x1="9" y1="13" x2="15" y2="13"/>
                            <line x1="9" y1="17" x2="11" y2="17"/>
                        </svg>
                        <p>Izvēlies konkursu sarakstā,<br>lai apskatītu atšifrējumu</p>
                    </div>
                </div>
            </aside>

        </div>
    </div>

    <!-- Detaļu modālis -->
    <div id="kk-modal" class="kk-modal hidden" role="dialog" aria-modal="true">
        <div class="kk-modal-backdrop"></div>
        <div class="kk-modal-box">
            <button class="kk-modal-close" id="kk-modal-close" aria-label="Aizvērt">×</button>
            <div id="kk-modal-body"></div>
        </div>
    </div>

    <?php $footerRich = 'konkursi'; include 'registrs/footer/footer.php'; ?>

    <?php
    // Starta momentuzņēmums (lib/snapshot.php, atjauno katra sinhronizācija):
    // gatavie skaiti + pirmās kartiņas, iegulti tieši HTML — JS tos parāda
    // momentā, bez neviena API pieprasījuma, kamēr fonā ielādējas pilnie dati.
    // Ekranēšana: '<' aizstāj ar JSON unicode escape (backslash-u003c). JSON
    // sintaksē '<' sastopams TIKAI virknēs, tāpēc globālā aizstāšana ir droša
    // (JSON.parse to atkodē atpakaļ), un tā izslēdz GAN '</script>' bloka
    // pārraušanu, GAN '<!--' script-data-escaped triku ar avotu virsrakstiem.
    $startJson = @file_get_contents(__DIR__ . '/konkursi/data/start.json');
    if (is_string($startJson) && $startJson !== ''): ?>
    <script type="application/json" id="kk-start"><?php echo str_replace('<', '\u003c', $startJson); ?></script>
    <?php endif; ?>

    <script src="<?php echo $jsSrc; ?>"></script>

    <?php include 'registrs/cookie/cookie.php'; ?>

</body>
</html>
