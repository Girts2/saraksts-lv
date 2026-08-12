<?php
/**
 * registrs/nvo/build_nvo.php — "Test Biedrības" datu būve (TIKAI lokālā testa vide).
 *
 * Lejupielādē un importē biedrību papildu datu avotus vienā SQLite failā
 * (test_data/nvo.sqlite), kas atrodas ĀRPUS docroot — tāpat kā pārējās testa
 * sadaļas. Aprēķina arī darbības jomu etalonus no ur_data.db.
 *
 * PERSONAS DATI: importē TIKAI juridiskās personas. Fizisko personu rindas
 * (vārds/uzvārds, personas kods) tiek izmestas jau lasīšanas laikā — kolonnas
 * ar vārdiem netiek importētas vispār.
 *
 * Palaišana:  php registrs/nvo/build_nvo.php [--refresh]
 *   --refresh  lejupielādē no jauna arī tad, ja kešs ir svaigs (< 24 h)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const NVO_CACHE_TTL = 86400;   // kešoto lejupielāžu derīgums sekundēs

$root      = dirname(__DIR__, 3);                 // .../_Optimizacija
$server    = dirname(__DIR__, 2);                 // .../_Optimizacija/server
$cache_dir = $root . '/test_data/nvo_cache';
$db_file   = $root . '/test_data/nvo.sqlite';
$ur_file   = $server . '/csv/SQLite/ur_data.db';
$lad_file  = $root . '/test_data/lad_atbalsts.sqlite';
$refresh   = in_array('--refresh', $argv, true);

@mkdir($cache_dir, 0775, true);

function nlog(string $m): void { echo $m . "\n"; }

/**
 * CKAN: atrod datu kopas resursa URL pēc nosaukuma fragmenta.
 * Vajadzīgs tur, kur URL satur datumu (BVKB pārvaldnieki) un mainās katru dienu.
 */
function nvo_resolve_ckan(string $package, string $format = 'CSV'): ?string {
    $url = 'https://data.gov.lv/dati/api/3/action/package_show?id=' . rawurlencode($package);
    $json = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 30, 'user_agent' => 'saraksts.lv nvo builder'],
    ]));
    if ($json === false) return null;
    $d = json_decode($json, true);
    foreach ($d['result']['resources'] ?? [] as $r) {
        if (strcasecmp((string)($r['format'] ?? ''), $format) === 0) return (string)$r['url'];
    }
    return null;
}

/** Lejupielādē uz kešu; atgriež ceļu vai null. Svaigu failu neaiztiek. */
function nvo_fetch(string $url, string $dest, bool $refresh): ?string {
    if (!$refresh && is_file($dest) && filesize($dest) > 0
        && (time() - filemtime($dest)) < NVO_CACHE_TTL) {
        nlog('   = kešs: ' . basename($dest) . ' (' . number_format((float)filesize($dest), 0, '.', ' ') . ' b)');
        return $dest;
    }
    $tmp = $dest . '.part';
    $fp = fopen($tmp, 'wb');
    if ($fp === false) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_FAILONERROR => true,
        CURLOPT_USERAGENT => 'saraksts.lv nvo builder (PHP)', CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ok = curl_exec($ch);
    $err = curl_error($ch);
    unset($ch);
    fclose($fp);
    if ($ok === false || !is_file($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);
        nlog('   ! neizdevās: ' . basename($dest) . ($err !== '' ? " ($err)" : ''));
        // Ja ir vecāka kopija, strādājam ar to — labāk veci dati nekā nekādi.
        return is_file($dest) ? $dest : null;
    }
    rename($tmp, $dest);
    nlog('   + ' . basename($dest) . ' (' . number_format((float)filesize($dest), 0, '.', ' ') . ' b)');
    return $dest;
}

/** CSV rindu ģenerators ar galveni; nogriež BOM. */
function nvo_csv(string $path, string $sep = ','): Generator {
    $fh = fopen($path, 'rb');
    if ($fh === false) return;
    $first = fgets($fh);
    if ($first === false) { fclose($fh); return; }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    rewind($fh);
    fgets($fh);                                    // patērē oriģinālo galveni
    $head = str_getcsv(rtrim($first, "\r\n"), $sep, '"', '\\');
    $n = count($head);
    while (($row = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
        if ($row === [null] || $row === false) continue;
        if (count($row) < $n) $row = array_pad($row, $n, '');
        elseif (count($row) > $n) $row = array_slice($row, 0, $n);
        yield array_combine($head, $row);
    }
    fclose($fh);
}

/** "1 234,56" / "1234.56" -> float */
function nvo_num($v): float {
    $s = str_replace([' ', "\u{a0}", ' '], '', (string)$v);
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float)$s : 0.0;
}

/** " 19.10.2012 " -> "2012-10-19"; ISO datumu atstāj kā ir. */
function nvo_date($v): ?string {
    $s = trim((string)$v);
    if ($s === '') return null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return "$m[1]-$m[2]-$m[3]";
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $s, $m)) return "$m[3]-$m[2]-$m[1]";
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
nlog('=== Test Biedrības: datu būve ===');
if (!is_file($ur_file)) { nlog('! ur_data.db nav atrasts: ' . $ur_file); exit(1); }

nlog('1) Juridisko personu saraksts no ur_data.db ...');
$ur = new PDO('sqlite:' . $ur_file);
$ur->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$legal = [];                                        // regcode => type (visas jur. personas)
foreach ($ur->query('SELECT regcode, type FROM register') as $r) {
    $legal[(string)$r['regcode']] = (string)$r['type'];
}
nlog('   juridiskās personas: ' . number_format((float)count($legal), 0, '.', ' '));

// Filtrs, kas vienlaikus atsijā fiziskās personas: kods jābūt reģistrā.
$is_legal = static fn(?string $rc): bool => $rc !== null && $rc !== '' && isset($legal[$rc]);

nlog('2) Lejupielāde ...');
$src = [];
$src['slo']  = nvo_fetch(
    'https://data.gov.lv/dati/dataset/694111ef-a1ae-4838-b0b0-75e70d4faf81/resource/476c04af-877c-4136-adc1-a42841650e45/download/pdb_sloregistrs_odata.csv',
    $cache_dir . '/slo.csv', $refresh);
$parv_url = nvo_resolve_ckan('bis_weuhnvr_k8e3rwlpgxswea') // URL satur datumu -> jāizšķir dinamiski
    ?? 'https://data.gov.lv/dati/dataset/eacde7ca-264f-4fc7-9a4a-37c767bf5dfc/resource/407e7693-2900-40e8-81b2-3abe5ced1a07/download/dzivojamo-maju-parvaldnieki-10.08.2026.csv';
$src['parv'] = nvo_fetch($parv_url, $cache_dir . '/parv.csv', $refresh);
$src['dem']  = nvo_fetch('https://deminimis.fm.gov.lv/public/mekletajs/export_csv',
    $cache_dir . '/dem.csv', $refresh);
$src['esf2127'] = nvo_fetch(
    'https://data.gov.lv/dati/dataset/bfa55ed7-f58c-48c4-bf8e-cb7ff4f12b05/resource/80aca232-54ad-4a9e-b1e3-c06623c1020b/download/es_fondu_projektu_saraksts.csv',
    $cache_dir . '/esf2127.csv', $refresh);
$src['esf1420'] = nvo_fetch(
    'https://data.gov.lv/dati/dataset/4ce3df9e-b3c4-4373-af8c-afb1dc6a9a61/resource/9c2d6bf2-41a4-4a58-a5ba-3a4ad6d7e79f/download/es_fondu_projektu_saraksts.csv',
    $cache_dir . '/esf1420.csv', $refresh);
$sps_url = nvo_resolve_ckan('socialo-pakalpojumu-sniedzeju-registra-dati', '.json');
$src['sps'] = $sps_url ? nvo_fetch($sps_url, $cache_dir . '/sps.json', $refresh) : (is_file($cache_dir . '/sps.json') ? $cache_dir . '/sps.json' : null);

nlog('3) Datubāzes izveide ...');
@unlink($db_file);
$db = new PDO('sqlite:' . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF;');
foreach (explode(";\n", <<<'SQL'
CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT);
CREATE TABLE slo (regcode TEXT, veids TEXT, statuss TEXT, speka_no TEXT, speka_lidz TEXT, lemuma_datums TEXT, lemuma_butiba TEXT, joma TEXT);
CREATE TABLE parvaldnieki (regcode TEXT, parv_nr TEXT, registrets TEXT, statuss TEXT, aizliegums TEXT, teritorijas TEXT, majas_lapa TEXT, aktualizets TEXT);
CREATE TABLE deminimis (regcode TEXT, datums TEXT, projekts TEXT, pieskirejs TEXT, programma TEXT, summa REAL, instruments TEXT);
CREATE TABLE es_projekti (regcode TEXT, periods TEXT, nr TEXT, nosaukums TEXT, statuss TEXT, fonds TEXT, kopejas REAL, es_fin REAL, sakums TEXT, beigas TEXT, merka_grupa TEXT, kopsavilkums TEXT);
CREATE TABLE soc_pakalpojumi (regcode TEXT, pakalpojums TEXT, forma TEXT, statuss TEXT, planotie_klienti INTEGER, klientu_grupas TEXT, klientu_kategorija TEXT);
CREATE TABLE lad (regcode TEXT, novads TEXT, eagf REAL, eafrd REAL, cofin REAL, eu REAL);
CREATE TABLE lad_pasakumi (regcode TEXT, kods TEXT, nosaukums TEXT, eu REAL);
CREATE TABLE jomu_etaloni (joma TEXT, gads INTEGER, n INTEGER, aktivi_p25 REAL, aktivi_med REAL, aktivi_p75 REAL, fondi_med REAL, darbin_vid REAL, neg_fondi_pct REAL)
SQL
    ) as $stmt) { $db->exec($stmt); }

$ins = static function (PDO $db, string $table, array $cols): PDOStatement {
    $ph = implode(',', array_fill(0, count($cols), '?'));
    return $db->prepare("INSERT INTO $table (" . implode(',', $cols) . ") VALUES ($ph)");
};
$counts = [];

// ── SLO reģistrs (VID) ───────────────────────────────────────────────────────
if ($src['slo']) {
    nlog('4) SLO reģistrs ...');
    $st = $ins($db, 'slo', ['regcode','veids','statuss','speka_no','speka_lidz','lemuma_datums','lemuma_butiba','joma']);
    $db->beginTransaction(); $n = 0;
    foreach (nvo_csv($src['slo']) as $r) {
        $rc = trim((string)($r['Registracijas_kods'] ?? ''));
        if (!$is_legal($rc)) continue;
        $st->execute([$rc, trim((string)$r['Organizacijas_veids']), trim((string)$r['Statuss']),
            nvo_date($r['Speka_no']), nvo_date($r['Speka_lidz']), nvo_date($r['Lemuma_pienemsanas_datums']),
            trim((string)$r['Lemuma_butiba']), trim((string)$r['Darbibas_jomas_nosaukums'])]);
        $n++;
    }
    $db->commit();
    $counts['slo'] = $n; nlog("   rindas: $n");
}

// ── Dzīvojamo māju pārvaldnieki (BVKB) ───────────────────────────────────────
// Fizisko personu rindas (bez jur. pers. reģ. nr.) tiek izmestas; kolonna
// Vards_uzvards_vai_nosaukums NETIEK importēta.
if ($src['parv']) {
    nlog('5) Dzīvojamo māju pārvaldnieki ...');
    // Kolonna Pakalpojumi NETIEK importēta: tā ir identisks ~52 KB NACE klasifikatora
    // izmests saraksts (1414 rindās tikai 15 atšķirīgas vērtības) — nesatur informāciju
    // par konkrēto pārvaldnieku, bet aizņemtu ~50 MB.
    $st = $ins($db, 'parvaldnieki', ['regcode','parv_nr','registrets','statuss','aizliegums','teritorijas','majas_lapa','aktualizets']);
    $db->beginTransaction(); $n = 0;
    foreach (nvo_csv($src['parv']) as $r) {
        $rc = trim((string)($r['Juridiskas_personas_registracijas_numurs'] ?? ''));
        if (!$is_legal($rc)) continue;
        $st->execute([$rc, trim((string)$r['Dzivojamo_maju_parvaldnieka_registracijas_numurs_registra']),
            nvo_date($r['Registrets']), trim((string)$r['Statuss']), trim((string)$r['Aizliegts_veikt_darbibu']),
            trim((string)$r['Teritorijas']), trim((string)$r['Majas_lapa']),
            nvo_date($r['Aktualizets'])]);
        $n++;
    }
    $db->commit();
    $counts['parvaldnieki'] = $n; nlog("   rindas: $n");
}

// ── de minimis atbalsts (FM) ────────────────────────────────────────────────
// person_reg_nr satur arī personas kodus (formāts 000000-00000) — $is_legal tos atsijā.
if ($src['dem']) {
    nlog('6) de minimis atbalsts ...');
    $st = $ins($db, 'deminimis', ['regcode','datums','projekts','pieskirejs','programma','summa','instruments']);
    $db->beginTransaction(); $n = 0;
    foreach (nvo_csv($src['dem']) as $r) {
        $rc = trim((string)($r['person_reg_nr'] ?? ''));
        if (!$is_legal($rc)) continue;
        $st->execute([$rc, nvo_date($r['assign_date']), trim((string)$r['project_title']),
            trim((string)$r['assigner_title']), trim((string)$r['program_title']),
            nvo_num($r['assigned_amount']), trim((string)$r['instrument_title'])]);
        $n++;
    }
    $db->commit();
    $counts['deminimis'] = $n; nlog("   rindas: $n");
}

// ── ES fondu projekti (CFLA), abi periodi ───────────────────────────────────
// 14-20 un 21-27 kolonnu nosaukumi atšķiras — ņemam pirmo, kas rindā eksistē.
$pick = static function (array $r, array $keys, string $def = ''): string {
    foreach ($keys as $k) { if (isset($r[$k]) && trim((string)$r[$k]) !== '') return trim((string)$r[$k]); }
    return $def;
};
$st = $ins($db, 'es_projekti', ['regcode','periods','nr','nosaukums','statuss','fonds','kopejas','es_fin','sakums','beigas','merka_grupa','kopsavilkums']);
$n = 0;
foreach ([['esf1420', '2014–2020'], ['esf2127', '2021–2027']] as [$k, $periods]) {
    if (!$src[$k]) continue;
    nlog("7) ES fondu projekti $periods ...");
    $db->beginTransaction();
    foreach (nvo_csv($src[$k]) as $r) {
        $rc = trim((string)($r['IesniedzejaRegNo'] ?? ''));
        if (!$is_legal($rc)) continue;
        $st->execute([$rc, $periods,
            $pick($r, ['ProjektaNumurs']),
            $pick($r, ['ProjektaNosaukums']),
            $pick($r, ['ProjektaStatuss']),
            $pick($r, ['Fonds', 'EsFonds']),
            nvo_num($pick($r, ['ProjektaKopejasIzmaksas', 'KopejaisFinansejums'], '0')),
            nvo_num($pick($r, ['ProjektamPieskirtaisESFonduLidzfinansejums', 'EsFonduLidzfinansejums'], '0')),
            nvo_date($pick($r, ['ProjektaIeviesanasSakumaDatums', 'SakumaDatums'])),
            nvo_date($pick($r, ['ProjektaIeviesanasBeiguDatums', 'BeiguDatums'])),
            $pick($r, ['ProjektaMerkaGrupa', 'MerkaGrupa']),
            mb_substr($pick($r, ['KopsavilkumsKasPublicejamsEiropasSavienibasfondutimeklavietne', 'Kopsavilkums']), 0, 4000)]);
        $n++;
    }
    $db->commit();
}
$counts['es_projekti'] = $n; nlog("   rindas kopā: $n");

// ── Sociālo pakalpojumu sniedzēju reģistrs (LM) ─────────────────────────────
if ($src['sps']) {
    nlog('8) Sociālo pakalpojumu sniedzēji ...');
    $raw = json_decode((string)file_get_contents($src['sps']), true);
    $rows = $raw['data'] ?? [];
    $st = $ins($db, 'soc_pakalpojumi', ['regcode','pakalpojums','forma','statuss','planotie_klienti','klientu_grupas','klientu_kategorija']);
    $db->beginTransaction(); $n = 0;
    foreach ($rows as $r) {
        $rc = trim((string)($r['RegistracijasNumurs'] ?? ''));
        if (!$is_legal($rc)) continue;
        $grupas = [];
        foreach ($r['KlientuGrupas'] ?? [] as $g) {
            $nm = trim((string)($g['KlientuGrupasNosaukums'] ?? ''));
            if ($nm !== '') $grupas[] = $nm;
        }
        $st->execute([$rc, trim((string)($r['SniedzamaPakalpojumaNosaukums'] ?? '')),
            trim((string)($r['PakalpojumaSniegsanasForma'] ?? '')), trim((string)($r['Statuss'] ?? '')),
            (int)($r['PlanotaisKlientuSkaits'] ?? 0), implode('; ', $grupas),
            // Pakalpojuma mērķa grupas kategorija ("bērni", "pilngadīgas personas"),
            // NEVIS konkrētu personu vecums vai dzimums.
            trim((string)($r['KlientiPecVecumaUnDzimuma'] ?? ''))]);
        $n++;
    }
    $db->commit();
    $counts['soc_pakalpojumi'] = $n; nlog("   rindas: $n");
}

// ── LAD atbalsts (no lokālās testa DB) ──────────────────────────────────────
if (is_file($lad_file)) {
    nlog('9) LAD atbalsts (lokālā testa DB) ...');
    $lad = new PDO('sqlite:' . $lad_file);
    $lad->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $st = $ins($db, 'lad', ['regcode','novads','eagf','eafrd','cofin','eu']);
    $db->beginTransaction(); $n = 0;
    foreach ($lad->query('SELECT regcode, municipality, total_eagf, total_eafrd, total_cofin, total_eu FROM recipients') as $r) {
        $rc = trim((string)$r['regcode']);
        if (!$is_legal($rc)) continue;
        $st->execute([$rc, (string)$r['municipality'], (float)$r['total_eagf'], (float)$r['total_eafrd'],
            (float)$r['total_cofin'], (float)$r['total_eu']]);
        $n++;
    }
    $db->commit();
    $counts['lad'] = $n;

    $st = $ins($db, 'lad_pasakumi', ['regcode','kods','nosaukums','eu']);
    $db->beginTransaction(); $m = 0;
    foreach ($lad->query('SELECT regcode, code, name, eagf, eafrd FROM measures') as $r) {
        $rc = trim((string)$r['regcode']);
        if (!$is_legal($rc)) continue;
        $st->execute([$rc, (string)$r['code'], (string)$r['name'], (float)$r['eagf'] + (float)$r['eafrd']]);
        $m++;
    }
    $db->commit();
    $counts['lad_pasakumi'] = $m;
    nlog("   saņēmēji: $n, pasākumi: $m");
}

// ── Publiskie iepirkumi, kur juridiskā persona ir PASŪTĪTĀJS ────────────────
// Pārnesam no konkursi/db/tenders.db būves laikā: tur notices.buyer_id nav
// indeksēts, un skenēt 224 k rindu katrā lapas ielādē maksātu ~0,8 s.
// Konkursu DB neaiztiekam — to pārbūvē savs konveijers.
$tenders_file = $server . '/konkursi/db/tenders.db';
if (is_file($tenders_file)) {
    nlog('9b) Publiskie iepirkumi (pasūtītāji) ...');
    $db->exec('CREATE TABLE iepirkumi (regcode TEXT, nosaukums TEXT, publicets TEXT, termins TEXT, budzets REAL, valuta TEXT, saite TEXT)');
    try {
        $tn = new PDO('sqlite:' . $tenders_file);
        $tn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $st = $ins($db, 'iepirkumi', ['regcode','nosaukums','publicets','termins','budzets','valuta','saite']);
        $db->beginTransaction(); $n = 0;
        foreach ($tn->query("SELECT buyer_id, COALESCE(NULLIF(title_lv,''), title) AS nos,
                                    publication_date, deadline_date, budget, currency, document_url
                             FROM notices WHERE buyer_id IS NOT NULL AND buyer_id <> ''") as $r) {
            $rc = trim((string)$r['buyer_id']);
            if (!$is_legal($rc)) continue;
            $st->execute([$rc, (string)$r['nos'], substr((string)$r['publication_date'], 0, 10),
                substr((string)$r['deadline_date'], 0, 10), (float)$r['budget'], (string)$r['currency'],
                (string)$r['document_url']]);
            $n++;
        }
        $db->commit();
        $counts['iepirkumi'] = $n; nlog("   rindas: $n");
    } catch (Throwable $e) { nlog('   ! izlaists: ' . $e->getMessage()); }
}

// ── Darbības jomu etaloni (no ur_data.db) ───────────────────────────────────
nlog('10) Darbības jomu etaloni ...');
$year = (int)$ur->query("SELECT MAX(f.year) FROM financial_statements f
    JOIN register r ON r.regcode = f.legal_entity_registration_number
    JOIN balance_sheets b ON b.statement_id = f.id
    WHERE r.type IN ('BDR','NOD') AND f.currency='EUR'")->fetchColumn();
// Pēdējais gads bieži ir nepilnīgs (pārskati vēl ienāk) — etaloniem ņemam iepriekšējo.
$year = max(2016, $year - 1);

$rows = $ur->query("
    SELECT a.area_of_activity AS joma, b.total_assets AS ta, b.equity AS eq, f.employees AS emp
    FROM financial_statements f
    JOIN register r ON r.regcode = f.legal_entity_registration_number
    JOIN balance_sheets b ON b.statement_id = f.id
    JOIN areas_of_activity_of_associations_foundations a ON a.regcode = r.regcode
    WHERE r.type IN ('BDR','NOD') AND f.currency='EUR' AND f.year = $year
      AND (r.terminated IS NULL OR r.terminated = '')
      AND TRIM(COALESCE(a.area_of_activity,'')) <> ''
")->fetchAll(PDO::FETCH_ASSOC);

$by = [];
foreach ($rows as $r) { $by[$r['joma']][] = $r; }
$q = static function (array $v, float $p): float {
    if (!$v) return 0.0;
    sort($v, SORT_NUMERIC);
    $i = (int)floor($p * (count($v) - 1));
    return (float)$v[$i];
};
$st = $ins($db, 'jomu_etaloni', ['joma','gads','n','aktivi_p25','aktivi_med','aktivi_p75','fondi_med','darbin_vid','neg_fondi_pct']);
$db->beginTransaction(); $nj = 0;
foreach ($by as $joma => $set) {
    if (count($set) < 15) continue;                 // par mazu izlasi neizmantojam
    $ta  = array_map(fn($x) => (float)$x['ta'], $set);
    $eq  = array_map(fn($x) => (float)$x['eq'], $set);
    $emp = array_map(fn($x) => (float)$x['emp'], $set);
    $neg = count(array_filter($eq, fn($v) => $v < 0));
    $st->execute([$joma, $year, count($set), $q($ta, .25), $q($ta, .5), $q($ta, .75),
        $q($eq, .5), array_sum($emp) / count($emp), 100.0 * $neg / count($set)]);
    $nj++;
}
$db->commit();
$counts['jomu_etaloni'] = $nj;
nlog("   jomas ar etalonu: $nj (bāzes gads $year)");

nlog('11) Indeksi ...');
foreach (['slo','parvaldnieki','deminimis','es_projekti','soc_pakalpojumi','lad','lad_pasakumi','iepirkumi'] as $t) {
    try { $db->exec("CREATE INDEX ix_{$t}_rc ON {$t}(regcode)"); } catch (Throwable $e) { /* tabula nav izveidota */ }
}
$db->exec('CREATE INDEX ix_jomu_etaloni ON jomu_etaloni(joma)');

$m = $db->prepare('INSERT OR REPLACE INTO meta (key,value) VALUES (?,?)');
$m->execute(['built_at', date('c')]);
$m->execute(['etalonu_gads', (string)$year]);
foreach ($counts as $k => $v) { $m->execute(["rows_$k", (string)$v]); }
$m->execute(['avoti', json_encode([
    'slo'             => 'VID Sabiedriskā labuma organizāciju reģistrs (CC0)',
    'parvaldnieki'    => 'BVKB Dzīvojamo māju pārvaldnieku saraksts (CC0)',
    'deminimis'       => 'FM Piešķirtais de minimis atbalsts (CC0)',
    'es_projekti'     => 'CFLA ES fondu līdzfinansētie projekti 14-20 un 21-27 (CC0)',
    'soc_pakalpojumi' => 'LM Sociālo pakalpojumu sniedzēju reģistrs (CC0)',
    'lad'             => 'LAD maksājumu saņēmēji (CC0)',
    'iepirkumi'       => 'IUB iepirkumu paziņojumi — biedrība kā pasūtītājs (CC0)',
], JSON_UNESCAPED_UNICODE)]);

$db->exec('VACUUM');
nlog('=== Gatavs: ' . $db_file . ' (' . number_format((float)filesize($db_file), 0, '.', ' ') . ' b) ===');
