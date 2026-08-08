<?php
/**
 * Iespēja/php/common.php — koplietotie palīgi 5.–9. solim.
 *
 * Aizstāj Python bibliotēkas, ko šie soļi izmantoja:
 *   mysql.connector  → PDO (ie_db, IeSink)
 *   csv.DictReader   → ie_csv_rows
 *   lxml.iterparse   → XMLReader (ie_gml_centroids)
 *   shapely.Polygon  → ie_centroid_from_poslist (shoelace)
 *   requests/curl    → ie_overpass
 *
 * SAVIETOJAMĪBA AR PYTHON. Šie soļi raksta ražošanas MySQL, tāpēc katra atkāpe
 * ir redzama lapā. Trīs vietas, kur PHP noklusējums atšķiras no Python un kur
 * tāpēc ir atsevišķa funkcija:
 *   · int(round(x))  — Python noapaļo uz PĀRA pusi (round(2.5)==2), PHP prom no
 *                      nulles (round(2.5)==3). → ie_round_half_even()
 *   · str(float)     — Python dod īsāko atgriezenisko pierakstu, PHP griež pie
 *                      precision=14. → ie_repr_float()
 *   · s[:250]        — Python griež RAKSTZĪMES, PHP substr() baitus, kas latviešu
 *                      nosaukumos sabojātu pēdējo burtu. → mb_substr()
 */
declare(strict_types=1);

/**
 * TIKAI KOMANDRINDA. Šie faili atrodas docroot iekšienē, un atšķirībā no .py
 * skriptiem serveris tos IZPILDĪTU. Katrs solis sākas ar TRUNCATE, tāpēc atvērts
 * URL būtu poga "iztukšo un pārraksti ražošanas datubāzi". htaccess apakšmapju
 * .php jau slēdz (RewriteRule ^[^/]+/.+\.php$), bet uz vienu slāni te paļauties
 * nedrīkst — šis sargs strādā arī tad, ja fails nonāk citā serverī vai htaccess
 * tiek pārrakstīts.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/**
 * Šie ir pakešu darbi, ne web pieprasījumi. PHP noklusējums CLI vidē mēdz būt
 * 128 MB, un ar to 3. solis nokrita Rīgas kvantilēs, bet 4. solim tas ir uz
 * robežas (~95 MB tikai ēku sarakstam). Manuālā palaišana ar `php -d memory_limit`
 * to slēpj; cron palaiž parasto `php`, tāpēc limits jāceļ pašam kodam.
 * ini_set klusi neizdodas, ja hostingam ir ciets griests — tad darbs krīt tāpat
 * kā agrāk, bet vismaz ne noklusējuma dēļ.
 */
if ((int)ini_get('memory_limit') !== -1) {
    $cur = ini_get('memory_limit');
    $bytes = (int)$cur * (str_contains(strtoupper((string)$cur), 'G') ? 1073741824
           : (str_contains(strtoupper((string)$cur), 'M') ? 1048576 : 1));
    if ($bytes > 0 && $bytes < 512 * 1048576) @ini_set('memory_limit', '512M');
}

require_once __DIR__ . '/config.php';   // noslēpumi (DB pieslēgums)
require_once __DIR__ . '/schema.php';   // tabulu nosaukumi, reģioni, valsts profils

const IE_NS_BU  = 'http://inspire.ec.europa.eu/schemas/bu-core2d/4.0';
const IE_NS_GML = 'http://www.opengis.net/gml/3.2';

// ── Izvade ──────────────────────────────────────────────────────────────────

function ie_say(string $msg): void
{
    echo $msg, "\n";
    if (PHP_SAPI === 'cli') @flush();
}

function ie_fail(string $msg): never
{
    fwrite(STDERR, "KĻŪDA: $msg\n");
    exit(1);
}

/** Sāk soli: laika atskaite + virsraksts. Atgriež sākuma laiku. */
function ie_start(string $title): float
{
    ie_say(str_repeat('─', 62));
    ie_say($title);
    ie_say(str_repeat('─', 62));
    return microtime(true);
}

function ie_done(float $t0): void
{
    ie_say(sprintf('Pabeigts. Laiks: %.0fs', microtime(true) - $t0));
}

// ── Ievades faili ───────────────────────────────────────────────────────────

/**
 * Atrod ievades failu. Python skripti tos lasīja tikai no darba mapes, tāpēc
 * palaišana no citas mapes klusi neizdevās. Šeit meklējam vairākās vietās —
 * to vajag arī tāpēc, ka 3./4. solis raksta docroot saknē, bet "Turisma
 * objekti.txt" atrodas Iespēja/ mapē.
 */
function ie_find(string $name): string
{
    $dirs = [];
    $env = getenv('IESPEJA_DATA_DIR');
    if ($env !== false && $env !== '') $dirs[] = rtrim($env, '/');
    $dirs[] = ie_temp_dir();           // server/temp — noklusējuma darba mape
    $dirs[] = getcwd() ?: '.';
    $dirs[] = dirname(__DIR__);        // Iespēja/
    $dirs[] = dirname(__DIR__, 2);     // pakotnes sakne

    foreach ($dirs as $d) {
        $p = $d . '/' . $name;
        if (is_file($p)) return $p;
    }
    ie_fail("nav atrasts ievades fails '$name'.\n"
          . "  Meklēju: " . implode(', ', array_unique($dirs)) . "\n"
          . "  Norādi mapi ar IESPEJA_DATA_DIR vai palaid no mapes, kur fails ir.");
}

/**
 * Darba mape lejupielādēm un starpfailiem: `server/temp`.
 *
 * KĀPĒC ATSEVIŠĶA MAPE. Agrāk soļi rakstīja darba mapē, un tā bija docroot sakne —
 * `Building.gml` (5,5 GB), četri kadastra CSV (~780 MB), PBF izgriezums un
 * starprezultāti gulēja blakus lapām. Tas ir gan nekārtība, gan risks: docroot
 * saknē esošs fails ir viens nepareizs htaccess attālumā no publiskas pieejas.
 * Visi šie faili ir atjaunojami no avotiem, tāpēc `temp/` drīkst dzēst jebkurā
 * brīdī — konveijers tos ievāks no jauna.
 */
function ie_temp_dir(): string
{
    static $dir = null;
    if ($dir !== null) return $dir;

    $env = getenv('IESPEJA_DATA_DIR');
    $dir = ($env !== false && $env !== '')
         ? rtrim($env, '/')
         : dirname(__DIR__, 2) . '/temp';       // server/temp

    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        ie_fail("nevar izveidot darba mapi: $dir");
    }
    return $dir;
}

/** Mape, kur soļi RAKSTA rezultātus. */
function ie_out_dir(): string
{
    return ie_temp_dir();
}

/** CSV kā asociatīvu rindu plūsma — csv.DictReader ekvivalents. */
function ie_csv_rows(string $path): Generator
{
    $fh = fopen($path, 'r');
    if ($fh === false) ie_fail("nevar atvērt: $path");

    $head = fgetcsv($fh, 0, ',', '"', '');
    if ($head === false) { fclose($fh); return; }
    // UTF-8 BOM pirmajā kolonnas nosaukumā salauztu atslēgu meklēšanu.
    if (isset($head[0])) $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
    $n = count($head);

    try {
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($row === [null]) continue;              // tukša rinda
            if (count($row) < $n) $row = array_pad($row, $n, null);
            elseif (count($row) > $n) $row = array_slice($row, 0, $n);
            yield array_combine($head, $row);
        }
    } finally {
        fclose($fh);
    }
}

/**
 * CSV rinda TIEŠI tā, kā to uzrakstītu Python.
 *
 * PHP fputcsv() liek pēdiņas ap katru lauku, kurā ir ATSTARPE — tāpēc
 * "POINT(24.1 56.9)" pie tā kļūst par "\"POINT(24.1 56.9)\"". Python csv modulis
 * (QUOTE_MINIMAL) pēdiņas liek tikai tad, ja laukā ir komats, pēdiņa vai
 * rindas pārtraukums. Abus variantus jebkurš parsētājs nolasa vienādi, bet failus
 * baitu līmenī salīdzināt vairs nevar — un tieši tā ir spēcīgākā pārbaude.
 *
 * Rindas beigas: csv.writer noklusējums ir CRLF (tā uzrakstīti kadastra
 * starpfaili), pandas to_csv lieto LF (out-summ-level.csv, out-all.csv).
 */
function ie_fputcsv_py($fh, array $row, string $eol = "\r\n"): void
{
    $out = [];
    foreach ($row as $v) {
        $s = $v === null ? '' : (string)$v;
        if (strpbrk($s, ",\"\r\n") !== false) $s = '"' . str_replace('"', '""', $s) . '"';
        $out[] = $s;
    }
    fwrite($fh, implode(',', $out) . $eol);
}

// ── Skaitļu un tekstu savietojamība ─────────────────────────────────────────

/** Python int(round(x)): puse uz pāra skaitli. */
function ie_round_half_even(float $v): int
{
    $f = floor($v);
    $d = $v - $f;
    if ($d > 0.5) return (int)($f + 1);
    if ($d < 0.5) return (int)$f;
    return ((int)$f) % 2 === 0 ? (int)$f : (int)($f + 1);
}

/** Python round(x, $p): puse uz pāra skaitli, ar decimāldaļām. */
function ie_round_half_even_p(float $v, int $p): float
{
    $m = 10 ** $p;
    return ie_round_half_even($v * $m) / $m;
}

/**
 * Python repr(float) — īsākais pieraksts, kas nolasās atpakaļ tajā pašā skaitlī.
 * PHP (string)$f griež pie precision=14 un WKT koordinātēs zaudētu ciparus.
 */
function ie_repr_float(float $v): string
{
    $s = json_encode($v);                          // serialize_precision=-1
    if ($s === false) $s = (string)$v;
    if (!preg_match('/[.eE]/', $s)) $s .= '.0';    // Python raksta "56.0", PHP "56"
    return $s;
}

/** Nosaukuma griešana pēc rakstzīmēm (Python s[:$n]), ne baitiem. */
function ie_cut(?string $s, int $n): string
{
    if ($s === null || $s === '') return '';
    return mb_substr($s, 0, $n, 'UTF-8');
}

// ── Ģeometrija (aizstāj shapely) ────────────────────────────────────────────

/**
 * Poligona svērtais centroīds no GML posList skaitļiem.
 *
 * posList ir "lat lon lat lon …" (WGS84 grādi), tāpat kā Python versijā:
 *   x = lon = nums[1::2],  y = lat = nums[0::2]
 *
 * Shapely rēķina laukuma svērto centroīdu — NEVIS virsotņu vidējo.
 *
 * KĀPĒC ŠEIT IR BĀZES PUNKTS. Pirmā versija lietoja parasto shoelace formulu ar
 * absolūtām koordinātēm un kļūdījās vidēji par ~10 m, dažviet par 200 m. Iemesls
 * ir katastrofāla atcelšanās: koordinātas ir ~24 un ~57 grādi, bet ēkas laukums
 * ~4·10⁻⁸ grādu². Reizinājumi x₀y₁ − x₁y₀ ir divi gandrīz vienādi ~1372 lieli
 * skaitļi, un no 16 nozīmīgajiem cipariem rezultātā paliek daži. GEOS (shapely
 * dzinējs) to apiet, pārceļot poligonu uz pirmo virsotni kā lokālu nulli un
 * summējot trijstūrus no tās; te ir tas pats algoritms, tāpēc rezultāts sakrīt
 * bitu līmenī, nevis "aptuveni".
 *
 * ATŠĶIRĪBA no Python: tur pirms centroīda ir .buffer(0), kas salabo pašsagriezušās
 * kontūras. Šeit tāda remonta nav. Cik tas maksā, mēra
 * tools/gml_centroid_diff.php pret 4. soļa out-all.csv.
 *
 * @return array{0:float,1:float}|null [lon, lat] vai null, ja der noraidīt
 */
function ie_centroid_from_poslist(array $nums): ?array
{
    $n = count($nums);
    if ($n < 6 || $n % 2 !== 0) return null;

    $cnt = intdiv($n, 2);
    $bx  = $nums[1];   // bāzes punkts = pirmā virsotne (lon)
    $by  = $nums[0];   //                                (lat)

    // Ja gredzens nav noslēgts, shapely to noslēdz pats — pievienojam pēdējo malu.
    $closed = $nums[($cnt - 1) * 2] === $by && $nums[($cnt - 1) * 2 + 1] === $bx;
    $last   = $closed ? $cnt - 1 : $cnt;

    $area2 = 0.0; $cx = 0.0; $cy = 0.0;
    for ($i = 0; $i < $last; $i++) {
        $j  = ($i + 1) % $cnt;
        $x1 = $nums[$i * 2 + 1] - $bx; $y1 = $nums[$i * 2]     - $by;
        $x2 = $nums[$j * 2 + 1] - $bx; $y2 = $nums[$j * 2]     - $by;
        $a2 = $x1 * $y2 - $x2 * $y1;   // trijstūra (bāze, i, i+1) divkāršais laukums
        $area2 += $a2;
        $cx    += $a2 * ($x1 + $x2);   // bāzes punkts ir (0,0), tāpēc tas summā neietilpst
        $cy    += $a2 * ($y1 + $y2);
    }
    if (abs($area2 * 0.5) <= 1e-12) return null;   // atbilst shapely poly.area > 1e-12

    return [$cx / (3.0 * $area2) + $bx, $cy / (3.0 * $area2) + $by];
}

/**
 * Straumē Building.gml un izsauc $emit tikai tām ēkām, ko $want atzīst.
 *
 * XMLReader nolasa gml:id atribūtu, NEIZVĒRŠOT elementa saturu, tāpēc filtrs
 * nostrādā pirms dārgā darba: 6. un 8. solim vajag pāris tūkstošus ēku no 1,38
 * miljoniem. Atmiņas patēriņš paliek ~2 MB neatkarīgi no faila izmēra (5,5 GB).
 *
 * @param callable $want fn(string $bid14): bool
 * @param callable $emit fn(string $bid14, float $lon, float $lat): void
 * @return array{scanned:int,matched:int,emitted:int,failed:int}
 */
function ie_gml_centroids(string $path, callable $want, callable $emit): array
{
    $r = new XMLReader();
    if (!@$r->open($path)) ie_fail("nevar atvērt GML: $path");

    $doc  = new DOMDocument();
    $stat = ['scanned' => 0, 'matched' => 0, 'emitted' => 0, 'failed' => 0];

    // NEJAUC read() UN next() VIENĀ CIKLĀ. next() jau pārvieto kursoru uz nākamo
    // brāli; ja pēc tā vēl izsauc read(), tas ieiet nākamā elementa iekšienē un
    // KATRS OTRAIS elements paliek neapstrādāts. Šeit tas nemanāmi izdevās tikai
    // tāpēc, ka bu:Building ir ietīts wfs:member iekšā — plakanā XML (VZD kadastra
    // eksporti) tā būtu pusē datu trūkums. Tāpēc katrā apgriezienā tiek izsaukts
    // TIKAI VIENS no abiem.
    $ok = $r->read();
    while ($ok) {
        if ($r->nodeType !== XMLReader::ELEMENT
            || $r->localName !== 'Building'
            || $r->namespaceURI !== IE_NS_BU) { $ok = $r->read(); continue; }

        $stat['scanned']++;
        $gid = (string)$r->getAttributeNs('id', IE_NS_GML);

        if (!preg_match('/(\d{14})$/', $gid, $m) || !$want($m[1])) {
            $ok = $r->next();                      // izlaiž apakškoku
            continue;
        }
        $stat['matched']++;

        try {
            $node = $r->expand($doc);
            if ($node instanceof DOMElement) {
                $pl = $node->getElementsByTagNameNS(IE_NS_GML, 'posList');
                if ($pl->length > 0) {
                    $txt = trim((string)$pl->item(0)->textContent);
                    if ($txt !== '') {
                        $nums = array_map('floatval', preg_split('/\s+/', $txt) ?: []);
                        $c = ie_centroid_from_poslist($nums);
                        if ($c !== null) {
                            $emit($m[1], $c[0], $c[1]);
                            $stat['emitted']++;
                        } else {
                            $stat['failed']++;
                        }
                    } else { $stat['failed']++; }
                } else { $stat['failed']++; }
            } else { $stat['failed']++; }
        } catch (Throwable $e) {
            $stat['failed']++;                     // Python: except Exception: pass
        }
        $ok = $r->next();
    }
    $r->close();
    return $stat;
}

// ── XML elementu straume (aizstāj ET.iterparse) ─────────────────────────────

/**
 * Straumē XML un izsauc $onItem katram elementam ar doto lokālo nosaukumu.
 * $uri drīkst būt arī 'zip://arhīvs.zip#loceklis.xml' — tad nekas netiek
 * atarhivēts uz diska.
 *
 * @param callable $onItem fn(DOMElement $el): void
 */
function ie_xml_items(string $uri, string $itemTag, callable $onItem): int
{
    $r = new XMLReader();
    if (!@$r->open($uri)) ie_fail("nevar atvērt XML: $uri");
    $doc = new DOMDocument();
    $n = 0;

    // Skat. ie_gml_centroids piezīmi: read() un next() nedrīkst izsaukt vienā
    // apgriezienā — VZD eksportos elementi ir tiešie brāļi, un tad izlaistu pusi.
    $ok = $r->read();
    while ($ok) {
        if ($r->nodeType === XMLReader::ELEMENT && $r->localName === $itemTag) {
            $el = $r->expand($doc);
            if ($el instanceof DOMElement) { $onItem($el); $n++; }
            $ok = $r->next();
        } else {
            $ok = $r->read();
        }
    }
    $r->close();
    return $n;
}

/**
 * ElementTree findtext(".//{*}NOSAUKUMS", default) ekvivalents.
 * SVARĪGI: ja elements EKSISTĒ, bet ir tukšs, atgriež "" — nevis noklusējumu.
 * Noklusējums nāk tikai tad, ja elementa nav vispār. Tā uzvedas arī Python.
 */
function ie_xml_text(DOMElement $el, string $localName, ?string $default = null): ?string
{
    $hit = $el->getElementsByTagNameNS('*', $localName)->item(0);
    return $hit === null ? $default : $hit->textContent;
}

/** findtext(".//{*}VECAKS/{*}BERNS", default) — pirmais atbilstošais dokumenta secībā. */
function ie_xml_text_path(DOMElement $el, string $parent, string $child, ?string $default = null): ?string
{
    foreach ($el->getElementsByTagNameNS('*', $parent) as $p) {
        foreach ($p->childNodes as $c) {
            if ($c instanceof DOMElement && $c->localName === $child) return $c->textContent;
        }
    }
    return $default;
}

// ── Skaitliskie palīgi (aizstāj numpy) ──────────────────────────────────────

/**
 * np.interp(x, xp, fp) — lineāra interpolācija ar piesaisti galos.
 * Ārpus xp diapazona numpy atgriež malas vērtību, nevis ekstrapolē.
 */
function ie_interp(float $x, array $xp, array $fp): float
{
    $n = count($xp);
    if ($n === 0) return NAN;
    if ($x <= $xp[0]) return $fp[0];
    if ($x >= $xp[$n - 1]) return $fp[$n - 1];

    $lo = 0; $hi = $n - 1;
    while ($hi - $lo > 1) {
        $mid = intdiv($lo + $hi, 2);
        if ($xp[$mid] <= $x) $lo = $mid; else $hi = $mid;
    }
    $d = $xp[$hi] - $xp[$lo];
    if ($d == 0.0) return $fp[$hi];
    return $fp[$lo] + ($fp[$hi] - $fp[$lo]) * ($x - $xp[$lo]) / $d;
}

/**
 * Svērtās kvantiles — "3 SUMM-LEVEL.py" weighted_quantile() ports.
 * Izmet NaN, patur tikai pozitīvus svarus, kārto pēc vērtības, normalizē
 * svaru kumulatīvo summu un interpolē.
 *
 * @param array $pairs saraksts [vērtība, svars]
 * @return float[]|null null, ja derīgu datu nav
 */
function ie_weighted_quantile(array $pairs, array $quantiles): ?array
{
    $vals = []; $wts = [];
    foreach ($pairs as [$v, $w]) {
        if ($v === null || $w === null || is_nan((float)$v) || is_nan((float)$w)) continue;
        if ((float)$w <= 0) continue;
        $vals[] = (float)$v; $wts[] = (float)$w;
    }
    if (!$vals) return null;

    $idx = range(0, count($vals) - 1);
    usort($idx, static fn($a, $b) => $vals[$a] <=> $vals[$b] ?: $a <=> $b);

    $xp = []; $fp = []; $cum = 0.0; $total = array_sum($wts);
    if ($total <= 0) return null;
    foreach ($idx as $i) {
        $cum += $wts[$i];
        $xp[] = $cum / $total;
        $fp[] = $vals[$i];
    }

    $out = [];
    foreach ($quantiles as $q) $out[] = ie_interp((float)$q, $xp, $fp);
    return $out;
}

/**
 * Tas pats, bet PLŪSMĀ — atmiņa nav atkarīga no grupas izmēra.
 *
 * KĀPĒC. ie_weighted_quantile() visu grupu ietur masīvā. Rīgā tie ir 353 139
 * īpašumi, un PHP masīvs no 353 tūkstošiem divu elementu masīviem aizņem ~100 MB.
 * Ar noklusējuma memory_limit=128M solis nokrita tieši šeit — un tieši tā to
 * palaiž cron, jo mans manuālais `php -d memory_limit=4G` testos to slēpa.
 *
 * Ievadei jābūt sakārtotai AUGOŠI pēc vērtības (to izdara SQL ORDER BY) un ar
 * jau zināmu svaru kopsummu. Vienādām vērtībām secība nav svarīga: interpolācija
 * starp diviem vienādiem fp dod to pašu skaitli.
 *
 * @param iterable $sorted rindas [vērtība, svars], augoši pēc vērtības
 * @param float[]  $quantiles augoši
 */
function ie_weighted_quantile_stream(iterable $sorted, float $totalWeight, array $quantiles): ?array
{
    if ($totalWeight <= 0) return null;

    $out = [];
    $qi  = 0;
    $nq  = count($quantiles);
    $cum = 0.0;
    $prevX = null; $prevY = null;
    $lastY = null;

    foreach ($sorted as [$v, $w]) {
        $v = (float)$v; $w = (float)$w;
        if ($w <= 0 || is_nan($v) || is_nan($w)) continue;

        $cum += $w;
        $x = $cum / $totalWeight;
        $y = $v;

        if ($prevX === null) {
            // np.interp: viss zem pirmā punkta atgriež pirmo vērtību
            while ($qi < $nq && $quantiles[$qi] <= $x) { $out[] = $y; $qi++; }
        } else {
            while ($qi < $nq && $quantiles[$qi] <= $x) {
                $q = (float)$quantiles[$qi];
                $d = $x - $prevX;
                $out[] = $d == 0.0 ? $y : $prevY + ($y - $prevY) * ($q - $prevX) / $d;
                $qi++;
            }
        }
        $prevX = $x; $prevY = $y; $lastY = $y;
    }

    if ($lastY === null) return null;                 // nevienas derīgas rindas
    while ($qi < $nq) { $out[] = $lastY; $qi++; }     // virs pēdējā punkta → pēdējā vērtība
    return $out;
}

// ── Datubāze ────────────────────────────────────────────────────────────────

function ie_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $c = ie_config();
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // SET NAMES nav vajadzīgs: DSN `charset=utf8mb4` to izdara pats, un
            // PDO::MYSQL_ATTR_INIT_COMMAND kopš PHP 8.5 ir novecojis.
        ]);
    } catch (PDOException $e) {
        ie_fail("nevar pieslēgties MySQL ({$c['host']}:{$c['port']}/{$c['name']}): " . $e->getMessage());
    }
    return $pdo;
}

/**
 * POI tabulas sagatavošana un DAĻĒJA aizvietošana pa tipiem.
 *
 * Kamēr katram POI tipam bija sava tabula, atsvaidzināšana bija TRUNCATE. Ar
 * vienu `<valsts>_poi` tabulu tas vairs neder — 5. solis atjauno 4 tipus, 9. solis
 * 7, un neviens no tiem nedrīkst nogāzt otra datus.
 *
 * Tāpēc: izdzēšam TIKAI dotos tipus, un darām to TRANSAKCIJĀ. Tas ir stingri
 * labāk par veco kārtību — TRUNCATE ir DDL ar netiešu commit, tāpēc neizdevusies
 * palaišana atstāja tukšu tabulu bez atpakaļceļa. Šeit neizdošanās nozīmē
 * ROLLBACK un vecos datus vietā.
 *
 * @param string[] $ptypes tipi, ko šis solis pārraksta
 */
function ie_poi_create(?PDO $pdo): void
{
    $table = ie_table('poi');
    if ($pdo === null) { ie_say("   [sausais režīms] `$table` netiek aiztikta"); return; }

    // UZMANĪBU: CREATE TABLE ir DDL ar NETIEŠU COMMIT — MySQL to nedrīkst saukt
    // transakcijas iekšienē, citādi transakcija klusi beidzas šeit un vēlākais
    // rollBack() vairs neko neatceļ. Tāpēc shēmas izveide ir ATSEVIŠĶA funkcija,
    // ko izsauc PIRMS beginTransaction().
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `ptype` VARCHAR(20) NOT NULL,
            `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            `location` POINT NOT NULL,
            PRIMARY KEY (`id`),
            SPATIAL INDEX `ix_poi_location` (`location`),
            INDEX `ix_poi_ptype` (`ptype`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Telpiskais indekss šauro apli, `ptype` indekss — tipu. MySQL nevar tos
    // apvienot vienā indeksā (SPATIAL nepieļauj papildu kolonnas), tāpēc
    // vaicājumam vispirms jāiet caur MBRContains un tikai tad jāfiltrē ptype.
}

/** Izdzēš tikai dotos tipus. Drīkst (un vajag) būt transakcijā. */
function ie_poi_clear(?PDO $pdo, array $ptypes): void
{
    if ($pdo === null) {
        ie_say('   [sausais režīms] netiek dzēsti tipi: ' . implode(', ', $ptypes));
        return;
    }
    $del = $pdo->prepare('DELETE FROM `' . ie_table('poi') . '` WHERE `ptype` = ?');
    foreach ($ptypes as $t) $del->execute([$t]);
}

/**
 * Rindu uzkrājējs ar pakešu ievietošanu.
 *
 * Sausajā režīmā ($dryPath) neviens baits neaiziet uz MySQL — rindas nonāk CSV
 * failā tieši tādā secībā un formā, kā tās nonāktu tabulā. Tas ļauj salīdzināt
 * PHP un Python rezultātu, nepieskaroties ražošanas datubāzei.
 */
final class IeSink
{
    private array $buf = [];
    private int $count = 0;
    private $dryFh = null;

    /**
     * @param string[] $cols  parastās kolonnas
     * @param ?string  $geom  ģeometrijas kolonna (vērtība padodama kā WKT), vai null
     */
    public function __construct(
        private ?PDO $pdo,
        private string $table,
        private array $cols,
        private ?string $geom,
        private bool $ignore = false,
        private ?string $dryPath = null,
        private int $batch = 1000,
    ) {
        if ($this->dryPath !== null) {
            $this->dryFh = fopen($this->dryPath, 'w');
            if ($this->dryFh === false) ie_fail("nevar rakstīt: {$this->dryPath}");
            $head = $this->cols;
            if ($this->geom !== null) $head[] = $this->geom;
            fputcsv($this->dryFh, $head, ',', '"', '');
        }
    }

    /** @param array $params vērtības kolonnu secībā; ģeometrija (WKT) pēdējā. */
    public function add(array $params): void
    {
        $this->buf[] = array_values($params);
        if (count($this->buf) >= $this->batch) $this->flush();
    }

    public function flush(): void
    {
        if (!$this->buf) return;

        if ($this->dryFh !== null) {
            foreach ($this->buf as $row) {
                fputcsv($this->dryFh, array_map(static function ($v) {
                    if ($v === null)     return '';
                    if (is_float($v))    return ie_repr_float($v);
                    if (is_bool($v))     return $v ? '1' : '0';
                    return (string)$v;
                }, $row), ',', '"', '');
            }
        } else {
            $ncol = count($this->cols) + ($this->geom !== null ? 1 : 0);
            $one  = '(' . implode(',', array_fill(0, count($this->cols), '?'))
                  . ($this->geom !== null
                        ? (count($this->cols) ? ',' : '') . 'ST_PointFromText(?,4326)'
                        : '')
                  . ')';
            $cols = $this->cols;
            if ($this->geom !== null) $cols[] = $this->geom;

            $sql = 'INSERT ' . ($this->ignore ? 'IGNORE ' : '') . "INTO `{$this->table}` (`"
                 . implode('`,`', $cols) . '`) VALUES '
                 . implode(',', array_fill(0, count($this->buf), $one));

            $flat = [];
            foreach ($this->buf as $row) {
                if (count($row) !== $ncol) {
                    ie_fail("rindā {$ncol} vietā " . count($row) . " vērtības ({$this->table})");
                }
                foreach ($row as $v) $flat[] = $v;
            }
            $this->pdo->prepare($sql)->execute($flat);
        }

        $this->count += count($this->buf);
        $this->buf = [];
    }

    public function finish(): int
    {
        $this->flush();
        if ($this->dryFh !== null) { fclose($this->dryFh); $this->dryFh = null; }
        return $this->count;
    }

    public function count(): int { return $this->count; }
}

/**
 * Nolasa --dry-run=CEĻŠ. Ja norādīts, solis neko neraksta MySQL, bet izvada
 * rindas CSV failā (vai mapē, ja solis raksta vairākās tabulās).
 */
function ie_dry_run_arg(array $argv): ?string
{
    foreach ($argv as $a) {
        if (str_starts_with($a, '--dry-run=')) return substr($a, 10);
        if ($a === '--dry-run') return '.';
    }
    return null;
}

/** Sagatavo tabulu: CREATE + TRUNCATE. Sausajā režīmā neko nedara. */
function ie_prepare_table(?PDO $pdo, string $table, string $createSql, array $alters = []): void
{
    if ($pdo === null) { ie_say("   [sausais režīms] tabula `$table` netiek aiztikta"); return; }
    $pdo->exec($createSql);
    foreach ($alters as $sql) {
        // ADD COLUMN IF NOT EXISTS ir MariaDB paplašinājums; MySQL 8 met kļūdu.
        try { $pdo->exec($sql); } catch (PDOException $e) { /* kolonna jau ir */ }
    }
    $pdo->exec("TRUNCATE TABLE `$table`");
}

// ── Overpass ────────────────────────────────────────────────────────────────

/**
 * Noturīga lejupielāde uz FAILU. Straumē tieši diskā, tāpēc 5 GB arhīvs neprasa
 * 5 GB atmiņas (Python versija turēja visu io.BytesIO buferī). Atkārto pie
 * pārrāvuma un pārbauda pilnīgumu pret Content-Length — tieši tā aizsardzība,
 * kuras dēļ Python pusē tapa _download_to_bytes.
 */
function ie_http_download(string $url, string $dest, int $timeout = 1800, int $retries = 4): void
{
    $lastErr = '';
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        $fh = fopen($dest . '.part', 'w');
        if ($fh === false) ie_fail("nevar rakstīt: $dest.part");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_USERAGENT      => 'saraksts.lv iespeja (datu konveijers)',
        ]);
        $ok   = curl_exec($ch);
        $err  = curl_errno($ch);
        $msg  = curl_error($ch);
        $len  = (float)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        unset($ch);
        fclose($fh);

        $got = (float)@filesize($dest . '.part');
        if ($ok !== false && $err === 0 && ($len <= 0 || $got >= $len)) {
            if (!@rename($dest . '.part', $dest)) ie_fail("nevar pārsaukt $dest.part → $dest");
            return;
        }
        @unlink($dest . '.part');
        $lastErr = $err !== 0 ? "curl $err: $msg" : sprintf('nepilna lejupielāde: %.0f/%.0f baiti', $got, $len);
        $wait = min(2 ** $attempt, 30);
        ie_say("  ⚠ Lejupielādes mēģinājums $attempt/$retries neizdevās ($lastErr). Atkārtoju pēc {$wait}s…");
        if ($attempt < $retries) sleep($wait);
    }
    ie_fail("lejupielāde neizdevās pēc $retries mēģinājumiem: $lastErr");
}

/** Rekursīva mapes dzēšana (shutil.rmtree ekvivalents). */
function ie_rmtree(string $path): void
{
    if (!file_exists($path)) return;
    if (is_link($path) || !is_dir($path)) { @unlink($path); return; }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if ($f->isDir() && !$f->isLink()) @rmdir($f->getPathname()); else @unlink($f->getPathname());
    }
    @rmdir($path);
}

/** Neliela dokumenta lejupielāde atmiņā (Atom barotne u. tml.). */
function ie_http_get(string $url, int $timeout = 60): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FAILONERROR    => true,
        CURLOPT_USERAGENT      => 'saraksts.lv iespeja (datu konveijers)',
    ]);
    $body = curl_exec($ch);
    $err  = curl_errno($ch);
    $msg  = curl_error($ch);
    unset($ch);
    if ($err !== 0 || !is_string($body)) ie_fail("nevar nolasīt $url (curl $err: $msg)");
    return $body;
}

/**
 * Overpass API vaicājums pa OSM tagu pāriem. Aizstāj Python subprocess+curl
 * ar iebūvēto curl paplašinājumu (sertifikātu ķēde PHP pusē ir kārtībā).
 *
 * @param array<array{0:string,1:string}> $selectors
 * @param string   $extra  papildu filtrs katram selektoram, piem. '["name"]'
 * @param string[] $types  OSM elementu tipi; 1. solis lasīja tikai 'node'
 */
function ie_overpass(array $selectors, int $timeout = 240,
                     string $extra = '', array $types = ['node', 'way', 'relation'],
                     ?array $bbox = null): array
{
    // Valsts nāk no profila, nevis no ierakstīta "LV" — tas bija vienīgais
    // vārds šajā failā, kas neļāva to pašu kodu palaist citai valstij.
    $iso = ie_country()['iso'];

    /**
     * REĢIONU DALĪJUMS. $bbox sašaurina vaicājumu līdz vienam reģionam.
     *
     * Latvijai tas nav vajadzīgs, bet lielām valstīm tas ir OBLIGĀTS, un tas nav
     * teorija: pēdējā 9. soļa palaišanā pat Latvijas mērogā seši no septiņiem
     * tipiem atgrieza HTTP 504 un prasīja atkārtojumu. Vācijā `shop=convenience`
     * visā valstī ir ~60 tūkstoši objektu — tāds vaicājums nepabeigsies nekad,
     * neatkarīgi no atkārtojumu skaita. Sadalot pa reģioniem, katrs gabals ir
     * tāda paša izmēra kā šodienas Latvijas vaicājums, kas strādā.
     */
    $area = '';
    if ($bbox !== null) {
        [$minLon, $minLat, $maxLon, $maxLat] = $bbox;
        // Overpass bbox secība ir (dienvidi, rietumi, ziemeļi, austrumi)
        $area = sprintf('(%.6f,%.6f,%.6f,%.6f)', $minLat, $minLon, $maxLat, $maxLon);
    }

    $union = '';
    foreach ($selectors as [$k, $v]) {
        foreach ($types as $t) {
            // {$t} obligāti: "$t[" PHP nolasītu kā masīva indeksu, ne kā tekstu
            $union .= "{$t}[\"$k\"=\"$v\"]{$extra}(area.ctry){$area};";
        }
    }
    $q = '[out:json][timeout:120];area["ISO3166-1"="' . $iso . '"][admin_level=2]->.ctry;('
       . $union . ');out center tags;';

    // Paraugu mape (IESPEJA_OVERPASS_FIXTURES): pirmā palaišana atbildi saglabā,
    // nākamās to lasa no diska. Vajadzīga zelta-diff salīdzinājumam — Python un PHP
    // jāredz VIENA UN TĀ PATI atbilde, citādi atšķirības rada OSM izmaiņas, ne kods.
    // Noder arī atkļūdošanai, lai atkārtoti nedauzītu Overpass. Ražošanā nav iestatīta.
    $fixPath = null;
    $fixDir = getenv('IESPEJA_OVERPASS_FIXTURES');
    if ($fixDir !== false && $fixDir !== '') {
        if (!is_dir($fixDir)) @mkdir($fixDir, 0775, true);
        $fixPath = rtrim($fixDir, '/') . '/' . substr(sha1($q), 0, 16) . '.json';
        if (is_file($fixPath)) {
            $cached = json_decode((string)file_get_contents($fixPath), true);
            if (is_array($cached)) {
                ie_say('   [paraugs no diska] ' . basename($fixPath));
                return $cached;
            }
        }
    }

    $errors = [];
    foreach (ie_overpass_endpoints() as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'data=' . rawurlencode($q),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_USERAGENT      => 'saraksts.lv iespeja (konkurentu POI)',
        ]);
        $body = curl_exec($ch);
        $err  = curl_errno($ch);
        $msg  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch);   // curl_close() ir novecojis kopš PHP 8.5 un bez efekta kopš 8.0

        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $why = null;
        if ($err !== 0)                                    $why = "curl kļūda $err: $msg";
        elseif ($code !== 200)                             $why = "HTTP $code";
        elseif (!is_string($body) || trim($body) === '')   $why = 'tukša atbilde';

        $data = $why === null ? json_decode($body, true) : null;
        if ($why === null && !is_array($data)) $why = 'nederīgs JSON (' . strlen($body) . 'B)';

        /**
         * TUKŠS REZULTĀTS = KĻŪME, NEVIS "nulle objektu".
         *
         * Daļa publisko instanču ir REĢIONĀLAS, ne globālas. overpass.osm.ch
         * atbild ar tīru HTTP 200, bet tajā ir tikai Šveices dati — Latvijas
         * vaicājums no tās atgriezās ar 0 elementiem un izskatījās pēc panākuma.
         * 9. solis to ierakstītu kā "šim tipam konkurentu nav" un ar savu
         * DELETE-tipa transakciju nodzēstu esošos. Neviens neko nemanītu, jo
         * kļūdas nav — tikai tukša karte. Tāpēc tukšums šeit ir kļūda: īstā
         * valstī neviens no šiem tipiem nav nulle.
         */
        if ($why === null && !($data['elements'] ?? [])) {
            $why = 'atbilde bez objektiem (reģionāla instance?)';
        }

        if ($why === null) {
            if ($fixPath !== null) @file_put_contents($fixPath, $body);
            if ($errors) ie_say("   [Overpass] izmantots rezerves serveris: $host");
            return $data;
        }
        $errors[] = "$host: $why";
    }

    throw new RuntimeException('visi Overpass serveri atteica — ' . implode('; ', $errors));
}

/**
 * Publisko Overpass instanču saraksts, mēģināšanas secībā.
 *
 * Rotācija starp OSM wiki publicētajām GLOBĀLAJĀM instancēm ir dokumentēta un
 * ieteicama prakse: tā izlīdzina slodzi un ļauj konveijeram nostrādāt, kad
 * galvenā instance klūp (kas notiek regulāri — tā ir brīvprātīgo uzturēta
 * infrastruktūra). Reģionālās instances šeit NEDRĪKST būt: skat. tukšuma sargu.
 *
 * Pārrakstāms ar IESPEJA_OVERPASS_URLS (komatatdalīts), lai uz hostinga varētu
 * likt citu secību, neaiztiekot kodu.
 *
 * @return string[]
 */
function ie_overpass_endpoints(): array
{
    $env = getenv('IESPEJA_OVERPASS_URLS');
    if ($env !== false && trim($env) !== '') {
        return array_values(array_filter(array_map('trim', explode(',', $env))));
    }
    return [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
        'https://overpass.osm.jp/api/interpreter',
        'https://overpass.openstreetmap.fr/api/interpreter',
    ];
}

/**
 * Overpass ar atkārtojumiem UN dalījumu pa reģioniem.
 *
 * 1. un 9. solim bija katram sava kopēšana-ielīmēšana ar trim mēģinājumiem un
 * 30 s pauzi. Tagad tā ir viena vieta, un tā pati vieta prot sadalīt vaicājumu
 * pa reģioniem, kad valstij to vajag. Mazai valstij (viens reģions) vaicājums
 * paliek tieši tāds pats kā līdz šim — bez bbox, tāpēc rezultāts ir baitu
 * līmenī tas pats, ko deva pārbaudītais ports.
 *
 * @return array{elements:array}|null null, ja kāds reģions nepadevās
 */
function ie_overpass_tiled(array $selectors, string $label, string $extra = '',
                           array $types = ['node', 'way', 'relation'],
                           int $attempts = 3): ?array
{
    $regions = ie_regions();
    $tiled   = count($regions) > 1;
    $last    = count($regions) - 1;

    $elements = [];
    $seen     = [];

    foreach ($regions as $ri => $r) {
        $bbox = $tiled ? $r['bbox'] : null;
        $tag  = $tiled ? "$label [{$r['code']}]" : $label;

        $data = null;
        for ($a = 1; $a <= $attempts; $a++) {
            try {
                $data = ie_overpass($selectors, 240, $extra, $types, $bbox);
                break;
            } catch (Throwable $e) {
                ie_say("   $tag: mēģinājums $a neizdevās ({$e->getMessage()}), gaidu 30s");
                if ($a < $attempts) sleep(30);
            }
        }
        if ($data === null) {
            ie_say("   $tag: IZLAISTS pēc $attempts mēģinājumiem");
            return null;
        }

        foreach (($data['elements'] ?? []) as $e) {
            // Reģionu bbox pierobežā pārklājas, tāpēc dublikāti ir gaidāmi.
            $key = ($e['type'] ?? '') . '/' . ($e['id'] ?? '');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $elements[] = $e;
        }

        if ($tiled && $ri < $last) sleep(8);   // Overpass pieklājības pauze
    }

    return ['elements' => $elements];
}

/** OSM elementa koordinātes: node → lat/lon, way/relation → center. */
function ie_osm_latlon(array $e): ?array
{
    $lat = $e['lat'] ?? null;
    $lon = $e['lon'] ?? null;
    if ($lat === null && isset($e['center'])) {
        $lat = $e['center']['lat'] ?? null;
        $lon = $e['center']['lon'] ?? null;
    }
    if ($lat === null || $lon === null) return null;
    return [(float)$lat, (float)$lon];
}

/** WKT punkts no koordinātēm, Python str(float) pierakstā. */
function ie_point(float $lon, float $lat): string
{
    return 'POINT(' . ie_repr_float($lon) . ' ' . ie_repr_float($lat) . ')';
}

// ── OSM avots: PBF vai Overpass ─────────────────────────────────────────────

/**
 * Vietējais .pbf faila ceļš; lejupielādē, ja trūkst vai novecojis.
 *
 * Fails glabājas datu mapē blakus pārējiem starpfailiem, tāpēc atkārtota
 * palaišana to nevelk vēlreiz. 133 MB Latvijai; Vācijai tie ir ~4 GB, bet arī
 * tad tā ir viena lejupielāde uz nedēļu, nevis tūkstoši API vaicājumu.
 */
function ie_pbf_local(string $url, int $maxAgeDays): string
{
    $name = basename(parse_url($url, PHP_URL_PATH) ?: 'osm.pbf');
    $path = ie_out_dir() . '/' . $name;

    if (is_file($path)) {
        $ageDays = (time() - (int)filemtime($path)) / 86400;
        if ($ageDays <= $maxAgeDays) {
            ie_say(sprintf('   PBF no diska: %s (%.1f dienas vecs, %.0f MB)',
                $name, $ageDays, filesize($path) / 1048576));
            return $path;
        }
        ie_say(sprintf('   PBF novecojis (%.1f > %d dienas) — lejupielādēju no jauna',
            $ageDays, $maxAgeDays));
    }

    ie_say("   Lejupielādēju $url");
    ie_http_download($url, $path, 3600);
    ie_say(sprintf('   Saņemts %.0f MB', filesize($path) / 1048576));
    return $path;
}

/**
 * Valsts robežas poligons ar kešu blakus PBF failam.
 *
 * Robežas salikšana prasa trīs gājienus pār failu (Latvijai ~9 s, lielai valstij
 * ievērojami vairāk), bet rezultāts starp 1. un 9. soli nemainās. Tāpēc to
 * saglabājam JSON failā — otrā soļa palaišana to nolasa acumirklī.
 */
function ie_pbf_poly_cached(string $pbfPath, string $iso): ?array
{
    $cache = $pbfPath . '.poly.json';
    if (is_file($cache) && filemtime($cache) >= filemtime($pbfPath)) {
        $j = json_decode((string)file_get_contents($cache), true);
        if (is_array($j) && isset($j['outer'])) {
            ie_say('   Robežas poligons no keša');
            return $j;
        }
    }
    ie_say("   Būvēju $iso robežas poligonu no PBF…");
    $t = microtime(true);
    $poly = ie_pbf_country_polygon($pbfPath, $iso);
    if ($poly === null) {
        ie_say('   BRĪDINĀJUMS: robeža nav atrasta — filtrs netiks piemērots');
        return null;
    }
    ie_say(sprintf('   Robeža: %d gredzeni, %d punkti, %.1fs',
        count($poly['outer']), array_sum(array_map('count', $poly['outer'])), microtime(true) - $t));
    @file_put_contents($cache, json_encode($poly));
    return $poly;
}

/**
 * POI ievākšana no valsts profilā deklarētā avota.
 *
 * Vienotais ieejas punkts 1. un 9. solim: abi padod savu tipu definīciju kopu un
 * saņem `ptype => ['elements' => …]` neatkarīgi no tā, vai dati nāk no Overpass
 * vai no PBF. Tāpēc avota nomaiņa ir viena rinda `countries/<kods>.php`, nevis
 * soļu pārrakstīšana.
 *
 * @param array $defs ptype => ['selectors'=>…, 'types'=>…, 'requireName'=>bool]
 * @return array ptype => ['elements'=>…]|null   (null = šo tipu neizdevās ievākt)
 */
function ie_osm_collect(array $defs): array
{
    $osm = ie_country()['osm'] ?? ['source' => 'overpass'];

    // ── Overpass (vecā uzvedība) ────────────────────────────────────────────
    if (($osm['source'] ?? 'overpass') !== 'pbf') {
        $out = [];
        $first = true;
        foreach ($defs as $pt => $d) {
            if (!$first) sleep(8);                    // Overpass pieklājības pauze
            $first = false;
            $extra = !empty($d['requireName']) ? '["name"]' : '';
            $out[$pt] = ie_overpass_tiled($d['selectors'], $pt, $extra, $d['types']);
        }
        return $out;
    }

    // ── PBF ─────────────────────────────────────────────────────────────────
    require_once __DIR__ . '/pbf.php';
    $iso     = ie_country()['iso'];
    $maxAge  = (int)($osm['max_age'] ?? 7);
    $regions = ie_regions();

    // Lielā valstī katram reģionam savs izgriezums (Geofabrik publicē pa zemēm);
    // mazā valstī viens fails visai valstij.
    $sources = [];
    foreach ($regions as $r) {
        if (!empty($r['pbf_url'])) $sources[$r['code']] = $r['pbf_url'];
    }
    if (!$sources) {
        if (empty($osm['pbf_url'])) ie_fail("valsts profilā nav 'osm.pbf_url'");
        $sources[$regions[0]['code']] = $osm['pbf_url'];
    }

    $merged = [];
    foreach ($defs as $pt => $_) $merged[$pt] = ['elements' => []];
    $seen = [];

    foreach ($sources as $code => $url) {
        if (count($sources) > 1) ie_say("   ── reģions $code ──");
        $path = ie_pbf_local($url, $maxAge);
        $poly = ie_pbf_poly_cached($path, $iso);

        $t = microtime(true);
        $part = ie_pbf_extract_many($path, $defs, $poly);
        ie_say(sprintf('   Izvilkti %d tipi %.1fs', count($part), microtime(true) - $t));

        foreach ($part as $pt => $res) {
            foreach ($res['elements'] as $e) {
                // Reģionu izgriezumi pārklājas pierobežā — dublikātus izmetam.
                $key = $pt . '/' . ($e['type'] ?? '') . '/' . ($e['id'] ?? '');
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $merged[$pt]['elements'][] = $e;
            }
        }
    }
    return $merged;
}
