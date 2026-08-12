<?php
/**
 * registrs/nvo/nvo_data.php — datu piekļuve "Test Biedrības" paneļiem.
 *
 * Katra funkcija atgriež gatavu masīvu vai null, ja datu nav. null = paneli
 * nerāda vispār (tā ir visa "adaptīvās" sistēmas loģika — sk. nvo_panels.php).
 *
 * PERSONAS DATI: amatpersonas atgriežas TIKAI agregēti (skaits, pārstāvības
 * veids, datumi). Vārdi, personas kodi un dzimšanas datumi netiek lasīti no DB.
 */
declare(strict_types=1);

// Fizisko personu vārdu skrubis (securing_measures) — šī sadaļa vaicā UR DB tieši,
// tāpēc data_fetcher.php skrubis šeit neaizsniedzas.
require_once __DIR__ . '/../lib/gdpr_scrub.php';

/** Savienojums ar papildu avotu DB (ārpus docroot). null, ja nav uzbūvēta. */
function nvo_db(): ?PDO {
    static $pdo = false;
    if ($pdo !== false) return $pdo;
    $f = dirname(__DIR__, 3) . '/test_data/nvo.sqlite';
    if (!is_file($f)) return $pdo = null;
    try {
        $pdo = new PDO('sqlite:' . $f);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $pdo = null; }
    return $pdo;
}

/** Vaicājums, kas nekad nemet — trūkstoša tabula vai DB atgriež tukšu masīvu. */
function nvo_q(?PDO $db, string $sql, array $args = []): array {
    if ($db === null) return [];
    try {
        $st = $db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

// ── UR pamatdati ─────────────────────────────────────────────────────────────

/** Reģistra pamatieraksts + atvasinātais vecums un statuss. */
function nvo_pamatdati(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, 'SELECT * FROM register WHERE regcode = ? LIMIT 1', [$rc]);
    if (!$r) return null;
    $r = $r[0];
    $reg = (string)($r['registered'] ?? '');
    $term = trim((string)($r['terminated'] ?? ''));
    $vecums = null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $reg)) {
        $vecums = (int)floor((time() - strtotime($reg)) / 31556952);
    }
    $dib = nvo_q($ur, 'SELECT document_date FROM memorandum_date WHERE legal_entity_registration_number = ? LIMIT 1', [$rc]);
    return [
        'regcode'     => $rc,
        'nosaukums'   => str_replace('""', '"', (string)($r['name'] ?? $rc)),
        'tips'        => (string)($r['type'] ?? ''),
        'tips_teksts' => (string)($r['type_text'] ?? ''),
        'registrs'    => (string)($r['regtype_text'] ?? ''),
        'registrets'  => $reg,
        'vecums'      => $vecums,
        'dibinats'    => $dib[0]['document_date'] ?? null,
        'adrese'      => str_replace('""', '"', (string)($r['address'] ?? '')),
        'atvk'        => (string)($r['atvk'] ?? ''),
        'aktivs'      => $term === '' || $term === '0000-00-00',
        'terminated'  => $term,
        'closed'      => (string)($r['closed'] ?? ''),
    ];
}

/** Statūtu mērķi (brīvs teksts no UR). Segums ~100 %. */
function nvo_merki(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, "SELECT area_of_activity FROM area_of_activity
                     WHERE legal_entity_registration_number = ?
                       AND TRIM(COALESCE(area_of_activity,'')) <> '' LIMIT 1", [$rc]);
    if (!$r) return null;
    $txt = trim((string)$r[0]['area_of_activity']);
    // UR tekstā mērķi bieži atdalīti ar ';' vai jaunām rindām — sadalām punktos.
    $parts = preg_split('/\s*;\s*|\R+/u', $txt, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== '' && $p !== '.'));
    return ['teksts' => $txt, 'punkti' => $parts];
}

/** Klasificētās darbības jomas (UR biedrību/nodibinājumu jomu kopa). */
function nvo_jomas(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, "SELECT DISTINCT area_of_activity AS joma
                     FROM areas_of_activity_of_associations_foundations
                     WHERE regcode = ? AND TRIM(COALESCE(area_of_activity,'')) <> ''
                     ORDER BY 1", [$rc]);
    return $r ? array_column($r, 'joma') : null;
}

/**
 * Pārvaldības struktūra — TIKAI agregēti rādītāji, bez personu datiem.
 * Vārdi/personas kodi/dzimšanas datumi netiek izvilkti no DB vispār.
 */
function nvo_parvaldiba(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, "SELECT governing_body, position, rights_of_representation_type AS parst,
                            entity_type, registered_on
                     FROM officers WHERE at_legal_entity_registration_number = ?", [$rc]);
    if (!$r) return null;
    $veidi = []; $parst = []; $datumi = [];
    foreach ($r as $row) {
        $g = trim((string)($row['governing_body'] ?? '')) ?: trim((string)($row['position'] ?? '')) ?: 'Nenorādīts';
        $veidi[$g] = ($veidi[$g] ?? 0) + 1;
        $p = trim((string)($row['parst'] ?? ''));
        if ($p !== '') $parst[$p] = ($parst[$p] ?? 0) + 1;
        $d = trim((string)($row['registered_on'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $datumi[] = $d;
    }
    sort($datumi);
    $pedeja = $datumi ? end($datumi) : null;
    $gadi_kops = $pedeja ? (int)floor((time() - strtotime($pedeja)) / 31556952) : null;
    return [
        'skaits'      => count($r),
        'institucijas'=> $veidi,
        'parstaviba'  => $parst,
        'pirma'       => $datumi[0] ?? null,
        'pedeja'      => $pedeja,
        'gadi_kops'   => $gadi_kops,
    ];
}

/** Gada pārskatu iesniegšanas vēsture + disciplīnas vērtējums. Segums ~89 %. */
function nvo_parskati(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, "SELECT f.year, f.employees, f.currency, f.source_type,
                            (b.statement_id IS NOT NULL) AS ir_bilance
                     FROM financial_statements f
                     LEFT JOIN balance_sheets b ON b.statement_id = f.id
                     WHERE f.legal_entity_registration_number = ?
                     ORDER BY f.year DESC", [$rc]);
    if (!$r) return ['gadi' => [], 'pedejais' => null, 'statuss' => 'nav', 'kopa' => 0];
    $gadi = [];
    foreach ($r as $row) {
        $y = (int)$row['year'];
        if (isset($gadi[$y])) continue;                       // viens ieraksts uz gadu
        $gadi[$y] = [
            'gads'       => $y,
            'darbinieki' => $row['employees'] !== null ? (float)$row['employees'] : null,
            'valuta'     => (string)$row['currency'],
            'ir_bilance' => (bool)$row['ir_bilance'],
        ];
    }
    $pedejais = max(array_keys($gadi));
    $seit = (int)date('Y');
    // Pārskats par gadu N jāiesniedz līdz N+1 31. martam; N+1 gads vēl nav kavējums.
    $atpaliek = $seit - 1 - $pedejais;
    $statuss = $atpaliek <= 0 ? 'kartiba' : ($atpaliek === 1 ? 'aizkave' : 'kave');
    return [
        'gadi' => $gadi, 'pedejais' => $pedejais, 'kopa' => count($gadi),
        'statuss' => $statuss, 'atpaliek' => max(0, $atpaliek),
    ];
}

/**
 * Bilances pa gadiem + no bilances atvasinātie rādītāji un gada rezultāta aplēse.
 *
 * Gada rezultāts biedrībai netiek publicēts (UR atvērtajos datos nav biedrību
 * ieņēmumu/izdevumu pārskata), tāpēc to APLĒŠAM kā fondu izmaiņu starp gadiem.
 * Aplēse ir derīga tikai tad, ja abos gados fondi nav negatīvi, iepriekšējā gada
 * bilance nav nulle un izmaiņa nepārsniedz aktīvus — pretējā gadījumā rādām, ka
 * aplēse nav ticama, nevis nepareizu skaitli.
 */
function nvo_bilances(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, "SELECT f.year, f.currency, f.employees, b.*
                     FROM financial_statements f
                     JOIN balance_sheets b ON b.statement_id = f.id
                     WHERE f.legal_entity_registration_number = ?
                     ORDER BY f.year DESC", [$rc]);
    if (!$r) return null;

    $gadi = [];
    foreach ($r as $row) {
        $y = (int)$row['year'];
        if (isset($gadi[$y])) continue;
        $f = fn(string $k) => $row[$k] !== null ? (float)$row[$k] : 0.0;
        $ta  = $f('total_assets');
        $cl  = $f('current_liabilities');
        $ncl = $f('non_current_liabilities');
        $tca = $f('total_current_assets');
        $eq  = $f('equity');
        $gadi[$y] = [
            'gads'        => $y,
            'valuta'      => (string)$row['currency'],
            'darbinieki'  => $row['employees'] !== null ? (float)$row['employees'] : null,
            'nauda'       => $f('cash'),
            'debitori'    => $f('accounts_receivable'),
            'krajumi'     => $f('inventories'),
            'apgroz'      => $tca,
            'ilgtermina'  => $f('total_non_current_assets'),
            'pamatlidz'   => $f('fixed_assets'),
            'nemat'       => $f('intangible_assets'),
            'aktivi'      => $ta,
            'ist_saist'   => $cl,
            'ilg_saist'   => $ncl,
            'fondi'       => $eq,
            // No bilances vien aprēķināmie rādītāji (nav atkarīgi no ieņēmumiem)
            'likviditate' => $cl > 0 ? $tca / $cl : null,
            'naudas_seg'  => $cl > 0 ? $f('cash') / $cl : null,
            'autonomija'  => $ta > 0 ? $eq / $ta : null,
            'saist_slogs' => $ta > 0 ? ($cl + $ncl) / $ta : null,
            'pamatl_ipat' => $ta > 0 ? $f('total_non_current_assets') / $ta : null,
        ];
    }
    krsort($gadi);

    // Gada rezultāta aplēse pa gadu pāriem (tikai EUR pret EUR).
    foreach ($gadi as $y => &$g) {
        $p = $gadi[$y - 1] ?? null;
        $g['rezultats'] = null;
        $g['rezultats_ticams'] = false;
        if ($p === null || $g['valuta'] !== 'EUR' || $p['valuta'] !== 'EUR') continue;
        $d = $g['fondi'] - $p['fondi'];
        $g['rezultats'] = $d;
        $g['rezultats_ticams'] = $g['fondi'] >= 0 && $p['fondi'] >= 0
            && $p['aktivi'] > 0 && $g['aktivi'] > 0
            && abs($d) <= $g['aktivi'];
    }
    unset($g);

    return ['gadi' => $gadi, 'pedejais' => max(array_keys($gadi))];
}

/** Nosaukuma vēsture. */
function nvo_nosaukumi(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, 'SELECT name, date_to FROM register_name_history WHERE regcode = ? ORDER BY date_to DESC', [$rc]);
    return $r ?: null;
}

/** PVN maksātāja statuss. */
function nvo_pvn(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, 'SELECT Aktivs, Registrets, Izslegts FROM pdb_pvnmaksataji_odata WHERE Numurs = ? LIMIT 1', [$rc]);
    if (!$r) return null;
    // VID datos datumi ir formātā " 08.08.2000 " — pārvēršam ISO, lai nv_date() strādā.
    $iso = static function ($v): string {
        return preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', (string)$v, $m) ? "$m[3]-$m[2]-$m[1]" : '';
    };
    return [
        'aktivs'    => trim((string)$r[0]['Aktivs']) === 'ir',
        'registrets'=> $iso($r[0]['Registrets']),
        'izslegts'  => $iso($r[0]['Izslegts']),
    ];
}

/** Deleģētie valsts pārvaldes uzdevumi. */
function nvo_delegetie(PDO $ur, string $rc): ?array {
    $r = nvo_q($ur, 'SELECT name AS deleg_iestade, delegatedEntityRegisteredOn AS no_datuma,
                            delegatedEntityRemovedOn AS lidz_datumam
                     FROM ppi_delegated_entities WHERE delegatedEntityRegistrationNumber = ?', [$rc]);
    return $r ?: null;
}

/** Riska ieraksti: likvidācija, nodrošinājuma līdzekļi, apturēšana, maksātnespēja. */
function nvo_riski(PDO $ur, string $rc): ?array {
    $out = [];
    foreach ([
        ['likvidacija', 'SELECT liquidation_type_text AS veids, date_from AS datums, grounds_for_liquidation AS pamats FROM liquidations WHERE legal_entity_registration_number = ?'],
        ['nodrosinajums', 'SELECT securing_measure_type_text AS veids, date_from AS datums, institution_name AS iestade FROM securing_measures WHERE legal_entity_registration_number = ?'],
        ['apturesana', 'SELECT suspension_code_text AS veids, date_from AS datums, ground_for AS pamats FROM suspensions_prohibitions WHERE legal_entity_registration_number = ?'],
        ['maksatnespeja', 'SELECT proceeding_type AS veids, proceeding_started_on AS datums, proceeding_ended_on AS beigas, court_name AS tiesa FROM insolvency_legal_person_proceeding WHERE debtor_registration_number = ?'],
    ] as [$key, $sql]) {
        $r = nvo_q($ur, $sql, [$rc]);
        // securing_measures.institution_name mēdz būt konkrētas fiziskas personas vārds
        // ("Zvērināta tiesu izpildītāja <Vārds Uzvārds>"). Šī sadaļa vaicā UR DB TIEŠI,
        // apejot data_fetcher skrubi, tāpēc to jāpiemēro arī šeit.
        if ($r && $key === 'nodrosinajums') {
            foreach ($r as $i => $row) {
                if (isset($row['iestade']) && is_string($row['iestade'])) {
                    $r[$i]['iestade'] = scrub_amatpersonas_vards($row['iestade']);
                }
            }
        }
        // Tas pats likvidācijas pamatojuma brīvtekstam (lēmuma pieņēmēja personvārdi).
        if ($r && $key === 'likvidacija') {
            foreach ($r as $i => $row) {
                if (isset($row['pamats']) && is_string($row['pamats'])) {
                    $r[$i]['pamats'] = scrub_likvidacijas_pamats($row['pamats']);
                }
            }
        }
        if ($r) $out[$key] = $r;
    }
    return $out ?: null;
}

// ── Papildu avoti (nvo.sqlite) ───────────────────────────────────────────────

/**
 * Sabiedriskā labuma organizācijas statuss + lēmumu vēsture (VID).
 * Avotā viens lēmums parādās atkārtoti — pa vienai rindai katrai jomai —
 * tāpēc lēmumus deduplicējam pēc (būtība, datums, statuss).
 */
function nvo_slo(string $rc): ?array {
    $r = nvo_q(nvo_db(), 'SELECT * FROM slo WHERE regcode = ? ORDER BY lemuma_datums DESC', [$rc]);
    if (!$r) return null;
    $jomas = [];
    $speka = false; $atnemts = false; $no = null; $lidz = null;
    $lemumi = [];
    foreach ($r as $row) {
        $s = trim((string)$row['statuss']);
        if ($s === 'Spēkā esošs') { $speka = true; $no = $no ?? $row['speka_no']; }
        if ($s === 'Atņemts')     { $atnemts = true; $lidz = $lidz ?? $row['speka_lidz']; }
        $j = trim((string)$row['joma']);
        if ($j !== '') $jomas[$j] = 1;
        $key = $row['lemuma_butiba'] . '|' . $row['lemuma_datums'] . '|' . $s;
        if (!isset($lemumi[$key])) $lemumi[$key] = $row;
    }
    return [
        'speka'    => $speka,
        'atnemts'  => $atnemts && !$speka,
        'speka_no' => $no,
        'atnemts_datums' => $lidz,
        'jomas'    => array_keys($jomas),
        'lemumi'   => array_values($lemumi),
    ];
}

/** Dzīvojamo māju pārvaldnieka statuss (BVKB). */
function nvo_parvaldnieks(string $rc): ?array {
    $r = nvo_q(nvo_db(), 'SELECT * FROM parvaldnieki WHERE regcode = ? ORDER BY registrets', [$rc]);
    if (!$r) return null;
    $ter = [];
    foreach ($r as $row) {
        foreach (preg_split('/\s*;\s*/', (string)$row['teritorijas'], -1, PREG_SPLIT_NO_EMPTY) ?: [] as $t) {
            $ter[trim($t)] = 1;
        }
    }
    $akt = array_values(array_filter($r, fn($x) => trim((string)$x['statuss']) === 'Aktīvs'));
    return [
        'aktivs'      => (bool)$akt,
        'ieraksti'    => $r,
        'teritorijas' => array_keys($ter),
        'majas_lapa'  => trim((string)($r[0]['majas_lapa'] ?? '')),
        'registrets'  => $r[0]['registrets'] ?? null,
        'aizliegums'  => trim((string)($r[0]['aizliegums'] ?? '')),
    ];
}

/** de minimis atbalsts (FM) — projektu nosaukumi, piešķīrēji, summas. */
function nvo_deminimis(string $rc): ?array {
    $r = nvo_q(nvo_db(), 'SELECT * FROM deminimis WHERE regcode = ? ORDER BY datums DESC', [$rc]);
    if (!$r) return null;
    $kopa = 0.0; $pa_gadiem = []; $pieskireji = [];
    foreach ($r as $row) {
        $s = (float)$row['summa'];
        $kopa += $s;
        $y = substr((string)$row['datums'], 0, 4);
        if ($y !== '') $pa_gadiem[$y] = ($pa_gadiem[$y] ?? 0) + $s;
        $p = trim((string)$row['pieskirejs']);
        if ($p !== '') $pieskireji[$p] = ($pieskireji[$p] ?? 0) + $s;
    }
    krsort($pa_gadiem);
    arsort($pieskireji);
    return ['ieraksti' => $r, 'kopa' => $kopa, 'pa_gadiem' => $pa_gadiem, 'pieskireji' => $pieskireji];
}

/** ES fondu projekti (CFLA). */
function nvo_es_projekti(string $rc): ?array {
    $r = nvo_q(nvo_db(), 'SELECT * FROM es_projekti WHERE regcode = ? ORDER BY sakums DESC', [$rc]);
    if (!$r) return null;
    $es = 0.0; $kop = 0.0;
    foreach ($r as $row) { $es += (float)$row['es_fin']; $kop += (float)$row['kopejas']; }
    return ['projekti' => $r, 'es_kopa' => $es, 'kopejas' => $kop];
}

/**
 * Sociālo pakalpojumu sniedzēja reģistrācijas (LM).
 * Avotā pakalpojumu nosaukumi ir gan ar lielo, gan mazo sākumburtu — apvienojam,
 * lai viens pakalpojums nerādītos divās rindās. Aktīvi sniegtie iet augšā.
 */
function nvo_soc_pakalpojumi(string $rc): ?array {
    $r = nvo_q(nvo_db(), 'SELECT * FROM soc_pakalpojumi WHERE regcode = ?', [$rc]);
    if (!$r) return null;
    $klienti = 0; $grupas = []; $kateg = []; $rindas = [];
    foreach ($r as $row) {
        $nos = trim((string)$row['pakalpojums']);
        if ($nos === '') continue;                                   // avotā ir tukši ieraksti
        $row['pakalpojums'] = mb_strtoupper(mb_substr($nos, 0, 1)) . mb_substr($nos, 1);
        $sniedz = trim((string)$row['statuss']) === 'Sniedz';
        if ($sniedz) $klienti += (int)$row['planotie_klienti'];
        foreach (preg_split('/\s*;\s*/', (string)$row['klientu_grupas'], -1, PREG_SPLIT_NO_EMPTY) ?: [] as $g) {
            $grupas[trim($g)] = 1;
        }
        $k = trim((string)$row['klientu_kategorija']);
        if ($k !== '') $kateg[rtrim($k, '. ')] = 1;
        $rindas[] = ['s' => $sniedz ? 0 : 1, 'n' => mb_strtolower($nos), 'row' => $row];
    }
    if (!$rindas) return null;
    usort($rindas, fn($a, $b) => [$a['s'], $a['n']] <=> [$b['s'], $b['n']]);
    return [
        'ieraksti' => array_column($rindas, 'row'),
        'klienti_kopa' => $klienti,
        'grupas' => array_keys($grupas),
        'kategorijas' => array_keys($kateg),
    ];
}

/** LAD maksājumi (ES lauksaimniecības un lauku attīstības fondi). */
function nvo_lad(string $rc): ?array {
    $r = nvo_q(nvo_db(), 'SELECT * FROM lad WHERE regcode = ? LIMIT 1', [$rc]);
    if (!$r) return null;
    $p = nvo_q(nvo_db(), 'SELECT kods, nosaukums, eu FROM lad_pasakumi WHERE regcode = ? ORDER BY eu DESC', [$rc]);
    return ['kopsavilkums' => $r[0], 'pasakumi' => $p];
}

/**
 * Darbības jomu etaloni (mediāna/kvartiles) salīdzinājumam.
 * Biedrībai bieži ir vairākas jomas — atgriežam VISAS, kurām etalons ir, jo
 * vienas izvēle būtu patvaļīga un maldinātu (piem. Sarkanajam Krustam gan
 * "Labdarība", gan "Izglītība" — tie ir divi ļoti atšķirīgi etaloni).
 */
function nvo_etaloni(array $jomas): ?array {
    if (!$jomas) return null;
    $db = nvo_db();
    if ($db === null) return null;
    $ph = implode(',', array_fill(0, count($jomas), '?'));
    $r = nvo_q($db, "SELECT * FROM jomu_etaloni WHERE joma IN ($ph) ORDER BY n DESC", array_values($jomas));
    return $r ?: null;
}

/** Publiskie iepirkumi, kur biedrība ir PASŪTĪTĀJS (IUB paziņojumi, pārnesti būves laikā). */
function nvo_iepirkumi(string $rc): ?array {
    $r = nvo_q(nvo_db(), 'SELECT * FROM iepirkumi WHERE regcode = ? ORDER BY publicets DESC LIMIT 50', [$rc]);
    return $r ?: null;
}

/** Papildu avotu DB metadati (būves laiks, avotu saraksts). */
function nvo_meta(): array {
    $r = nvo_q(nvo_db(), 'SELECT key, value FROM meta');
    $out = [];
    foreach ($r as $row) { $out[(string)$row['key']] = (string)$row['value']; }
    return $out;
}
