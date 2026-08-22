<?php
/**
 * sadalas/tiesiskais.php — "Tiesiskais statuss" sadaļa (publiska).
 *
 * Redzams karogs uzņēmuma lapā par maksātnespējas procesiem, tiesiskās aizsardzības
 * procesiem (TAP), likvidācijām, darbības liegumiem un nodrošinājuma līdzekļiem.
 * NOVIETOJUMS: tieši zem Pamatinformācijas paneļa (order:1 + DOM tūlīt aiz
 * company_facts_panel) — lai apmeklētājs liegumus redz uzreiz, ne lapas lejā
 * starp izpētes tabulām (Girta 2026-08-18 lēmums; līdz tam panelis bija tikai
 * lokālajā testa vidē kā "Test Tiesiskais statuss").
 *
 * KĀPĒC ŠIS EKSISTĒ: lib/risk_semaphore.php šos pašus faktus jau aprēķina, BET tos
 * izsauc tikai templates/master_top.php MI prompta preambulai — lapas HTML tie
 * nenonāca, tāpēc ne apmeklētājs, ne rāpuļi tos neredzēja. Šis panelis to izlabo.
 *
 * TAP NOŠĶIRŠANA: proceeding_form atšķir trīs lietas, kuras nedrīkst jaukt —
 *   INSOLVENCY (15 388)                     = maksātnespējas process,
 *   LEGAL_PROTECTION (2 109)                = tiesiskās aizsardzības process,
 *   OUT_OF_COURT_LEGAL_PROTECTION (458)     = ārpustiesas TAP.
 * TAP ir uzņēmuma mēģinājums atgūties, vienojoties ar kreditoriem — tas NAV
 * maksātnespēja, un veiksmīgi pabeigts TAP ir pretējs signāls nekā bankrots.
 *
 * DATU MINIMIZĀCIJA: rāda tikai juridiskās personas datus (process, datumi, tiesa,
 * iznākums, iestādes lēmuma atsauce). Administratoru un citu fizisko personu
 * tabulās nav un netiek vaicāti.
 *
 * Panelis parādās TIKAI tad, ja ir vismaz viens ieraksts — "tīrajiem" uzņēmumiem
 * (95 % gadījumu) lapa paliek nemainīga.
 */
/** @var array $page_data */

require_once __DIR__ . '/../../_tpl.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';

$ts_res = $page_data['results'] ?? [];
if (!is_array($ts_res)) $ts_res = [];

$ts_proc  = is_array($ts_res['insolvency_legal_person_proceeding'] ?? null) ? $ts_res['insolvency_legal_person_proceeding'] : [];
$ts_liq   = is_array($ts_res['liquidations'] ?? null) ? $ts_res['liquidations'] : [];
$ts_susp  = is_array($ts_res['suspensions_prohibitions'] ?? null) ? $ts_res['suspensions_prohibitions'] : [];
$ts_sec   = is_array($ts_res['securing_measures'] ?? null) ? $ts_res['securing_measures'] : [];
// Sankciju riski (UR/CC0). Rindas jau izgājušas scrub_sanctions() — personu vārdu,
// dzimšanas datumu un personas kodu tur nav, tikai uzņēmuma līmeņa fakts.
$ts_sank  = is_array($ts_res['sanctions'] ?? null) ? $ts_res['sanctions'] : [];
// VID saimnieciskās darbības apturēšanas vēsture (periodi + atjaunošana) — papildina
// UR suspensions_prohibitions, kam ir tikai sākuma datums un tikai pašreizējais stāvoklis.
$ts_vid   = is_array($ts_res['pdb_saimndarbibaaptureta_odata'] ?? null) ? $ts_res['pdb_saimndarbibaaptureta_odata'] : [];
// PTAC uzraudzība: melnais saraksts, lēmumi ar soda naudu, rakstveida apņemšanās.
// Licences un reģistri no tās pašas tabulas ir atsevišķā sadaļā sadalas/ptac.php — šeit
// tikai riska ieraksti. Brīvteksta apraksti netiek glabāti (sk. build_ptac_table).
$ts_ptac = [];
foreach (is_array($ts_res['ptac'] ?? null) ? $ts_res['ptac'] : [] as $ts_p) {
    if (in_array(trim((string)($ts_p['veids'] ?? '')), ['melnais', 'lemums', 'apnemsanas'], true)) $ts_ptac[] = $ts_p;
}
if (!$ts_proc && !$ts_liq && !$ts_susp && !$ts_sec && !$ts_sank && !$ts_vid && !$ts_ptac) return;

/** proceeding_form → [cilvēklasāms nosaukums, īsais tips]. */
$ts_form = static function (?string $f): array {
    switch (strtoupper(trim((string)$f))) {
        case 'INSOLVENCY':                    return ['Maksātnespējas process', 'mn'];
        case 'LEGAL_PROTECTION':              return ['Tiesiskās aizsardzības process (TAP)', 'tap'];
        case 'OUT_OF_COURT_LEGAL_PROTECTION': return ['Ārpustiesas tiesiskās aizsardzības process', 'tap'];
        default:                              return ['Process', 'cits'];
    }
};
$ts_date = static function ($v): string {
    $s = trim((string)$v);
    return ($s === '' || $s === '0000-00-00') ? '' : substr($s, 0, 10);
};

// --- Procesu sagatavošana: jaunākie pirmie, aktīvie atsevišķi ----------------
// 10 gadu logs (Girta 2026-08-18): aktīvos procesus rāda VIENMĒR; pabeigtos —
// tikai pēdējo 10 gadu (67 % pabeigto procesu DB ir vecāki — tie tabulu tikai
// piesārņo). Senākos nesadzēš, bet sakļauj vienā "senāki procesi" čipā.
// Liegumiem/nodrošinājumiem filtra nav (UR kopa jau satur tikai aktuālos);
// likvidācijām nav beigu datuma, tāpēc tās droši klasificēt nevar — rāda visas.
$ts_cutoff = date('Y-m-d', strtotime('-10 years'));
$ts_old_procs = 0;
$ts_rows = [];
$ts_active_mn = $ts_active_tap = 0;
$ts_past_mn = $ts_past_tap = 0;
foreach ($ts_proc as $p) {
    [$label, $kind] = $ts_form($p['proceeding_form'] ?? null);
    $start = $ts_date($p['proceeding_started_on'] ?? '');
    $end   = $ts_date($p['proceeding_ended_on'] ?? '');
    $active = ($start !== '' && $end === '');
    if (!$active && $end !== '' && $end < $ts_cutoff) { $ts_old_procs++; continue; }
    if ($active) { $kind === 'tap' ? $ts_active_tap++ : $ts_active_mn++; }
    else         { $kind === 'tap' ? $ts_past_tap++   : $ts_past_mn++; }
    $ts_rows[] = [
        'label' => $label, 'kind' => $kind, 'active' => $active,
        'start' => $start, 'end' => $end,
        'court' => trim((string)($p['court_name'] ?? '')),
        'case'  => trim((string)($p['court_case_initial_number'] ?? '')),
        'res'   => trim((string)($p['proceeding_resolution_name'] ?? '')),
    ];
}
usort($ts_rows, static function ($a, $b) {
    if ($a['active'] !== $b['active']) return $a['active'] ? -1 : 1;   // aktīvie augšā
    return strcmp($b['start'], $a['start']);                            // tad jaunākie
});

// --- Kopsavilkuma karogs ----------------------------------------------------
$ts_active_liq = count($ts_liq);
// PTAC uzraudzība un VID AKTĪVA darbības apturēšana agrāk karogā neietilpa, tāpēc
// uzņēmumam melnajā sarakstā vai ar spēkā esošu apturējumu sakļautā rindā rakstīja
// "Vēsturiski procesi — šobrīd aktīvu nav" (audits 2026-08-19). Tagad tie ir kaskādē.
$ts_pt_melns_n = 0; $ts_pt_sods_n = 0;
foreach ($ts_ptac as $ts_p) {
    $ts_pv = trim((string)($ts_p['veids'] ?? ''));
    if ($ts_pv === 'melnais') $ts_pt_melns_n++;
    if ($ts_pv === 'lemums' && (float)($ts_p['soda_nauda'] ?? 0) > 0) $ts_pt_sods_n++;
}
// VID apturējums ir AKTĪVS, ja avotā nav ne beigu, ne atjaunošanas datuma —
// tā pati loģika, ko lieto VID sekcija zemāk.
$ts_vid_akt = 0;
foreach ($ts_vid as $ts_v) {
    $lidz = trim((string)($ts_v['Aizliegts_veikt_darijumus_lidz'] ?? ''));
    $atj  = trim((string)($ts_v['Lemuma_par_atjaunosanu_datums'] ?? ''));
    if ($lidz === '' && $atj === '') $ts_vid_akt++;
}
if ($ts_sank) {
    // Sankcijas ir smagākais signāls: tās nozīmē darījumu aizliegumu, ne tikai risku.
    $ts_level = 'risk';
    $ts_head  = 'Sankciju risks';
} elseif ($ts_active_mn > 0 || $ts_active_liq > 0) {
    $ts_level = 'risk';
    $ts_head  = $ts_active_mn > 0 ? 'Aktīvs maksātnespējas process' : 'Uzsākta likvidācija';
} elseif ($ts_active_tap > 0) {
    $ts_level = 'warn';
    $ts_head  = 'Aktīvs tiesiskās aizsardzības process';
} elseif ($ts_pt_melns_n > 0) {
    $ts_level = 'risk';
    $ts_head  = 'PTAC melnajā sarakstā';
} elseif ($ts_vid_akt > 0) {
    $ts_level = 'risk';
    $ts_head  = 'Apturēta saimnieciskā darbība (VID)';
} elseif ($ts_susp || $ts_sec) {
    $ts_level = 'warn';
    $ts_head  = 'Reģistrēti darbības ierobežojumi';
} elseif ($ts_pt_sods_n > 0) {
    $ts_level = 'warn';
    $ts_head  = 'PTAC lēmums ar soda naudu';
} else {
    $ts_level = 'past';
    $ts_head  = 'Vēsturiski procesi — šobrīd aktīvu nav';
}

$ts_chips = [];
if ($ts_sank)       $ts_chips[] = ['risk', count($ts_sank) . (count($ts_sank) === 1 ? ' sankciju ieraksts' : ' sankciju ieraksti')];
if ($ts_active_mn)  $ts_chips[] = ['risk', $ts_active_mn . pd_dsk($ts_active_mn, ' aktīvs maksātnespējas process', ' aktīvi maksātnespējas procesi')];
if ($ts_active_tap) $ts_chips[] = ['warn', $ts_active_tap . pd_dsk($ts_active_tap, ' aktīvs TAP', ' aktīvi TAP')];
if ($ts_past_mn)    $ts_chips[] = ['past', $ts_past_mn . pd_dsk($ts_past_mn, ' pabeigts maksātnespējas process', ' pabeigti maksātnespējas procesi')];
if ($ts_past_tap)   $ts_chips[] = ['past', $ts_past_tap . pd_dsk($ts_past_tap, ' pabeigts TAP', ' pabeigti TAP')];
if ($ts_active_liq) $ts_chips[] = ['risk', 'likvidācija'];
if ($ts_susp)       $ts_chips[] = ['warn', count($ts_susp) . pd_dsk(count($ts_susp), ' darbības liegums/apturēšana', ' darbības liegumi/apturēšanas')];
if ($ts_sec)        $ts_chips[] = ['warn', count($ts_sec) . pd_dsk(count($ts_sec), ' nodrošinājuma līdzeklis', ' nodrošinājuma līdzekļi')];
if ($ts_ptac) {
    $ts_pt_melns = 0; $ts_pt_lem = 0; $ts_pt_sods = 0.0;
    foreach ($ts_ptac as $ts_p) {
        $ts_pv = trim((string)($ts_p['veids'] ?? ''));
        if ($ts_pv === 'melnais') $ts_pt_melns++;
        if ($ts_pv === 'lemums') { $ts_pt_lem++; $ts_pt_sods += (float)($ts_p['soda_nauda'] ?? 0); }
    }
    if ($ts_pt_melns) $ts_chips[] = ['risk', 'PTAC melnajā sarakstā'];
    if ($ts_pt_lem)   $ts_chips[] = ['warn', $ts_pt_lem . ($ts_pt_lem === 1 ? ' PTAC lēmums' : ' PTAC lēmumi')];
}
// "vecāki par 10 gadiem", ne "pirms {gads}. g.": robeža ir šodiena mīnus 10 gadi,
// tāpēc procesi no paša robežgada sākuma čipā citādi tiktu pieteikti nepatiesi
// (audits 2026-08-20: 2016-07-20 beidzies process bija "pirms 2016. g.").
if ($ts_old_procs)  $ts_chips[] = ['past', $ts_old_procs . ($ts_old_procs === 1 ? ' senāks process (vecāks par 10 gadiem)' : ' senāki procesi (vecāki par 10 gadiem)')];

// Platums seko LAPAS SKATAM, ne satura daudzumam (Girta 2026-08-19): divu paneļu
// skatā vienmēr pilni divi paneļi, viena paneļa skatā — viens. Agrākais mazsatura
// sašaurinājums (ts-compact 824px) taupīja tukšumu paneļa iekšienē, bet lapā
// atstāja plašu baltu joslu blakus — noņemts. Tas pats attiecas uz saistību paneli.
// --- Sadaļas līgums (papildu_dati_panel.php) ---
// Skaits = visi ieraksti, kas veido tiesisko ainu; kopsavilkumā liekam PAŠU
// brīdinājumu ($ts_head), lai risks būtu redzams arī SAKĻAUTĀ sadaļā — citādi
// šī bloka pārcelšana no lapas augšas paslēptu tieši to, kas jāredz uzreiz.
$ts_pilns = $pd_pilns ?? false;
$ts_lim = pd_limits($ts_pilns, 6);
$ts_lim3 = pd_limits($ts_pilns, 3);
$pd_nos = 'Tiesiskais statuss';
// Konteineram: sadaļa sarkanā ietvarā, ja ir reāls risks (sk. papildu_dati_panel.php).
$pd_limenis = $ts_level;
$pd_n = count($ts_proc) + count($ts_liq) + count($ts_susp) + count($ts_sec)
      + count($ts_sank) + count($ts_vid) + count($ts_ptac);
$pd_kops = $ts_head;
?>
<div class="tiesiskais-facts ts-<?= h($ts_level) ?>">
    <div class="ts-head">
        <div class="ts-flag ts-flag-<?= h($ts_level) ?>"><strong><?= h($ts_head) ?></strong></div>
<?php if ($ts_chips): ?>
<?php foreach ($ts_chips as [$cls, $txt]): ?>
        <span class="ts-chip ts-chip-<?= h($cls) ?>"><?= h($txt) ?></span>
<?php endforeach; ?>
<?php endif; ?>
    </div>

<?php // ts-body buferī: uzņēmumam, kam VISI procesi vecāki par 10 gadu logu,
      // neviena apakšsadaļa nerādās, un tukšs <div class="ts-body"> lapā ir
      // tieši tas "tukšais laukums", kas jāizskauž (sim1000 2026-08-20: 11/1000).
      ob_start(); ?>
<?php if ($ts_sank): ?>
<?php
    // Grupē pa sarakstiem (OFAC SDN / ES), lai neatkārtotu vienu un to pašu rindu
    // katrai saistītajai personai. Personu vārdu te NAV — tikai loma.
    $ts_sank_g = [];
    foreach ($ts_sank as $sn) {
        $list = trim((string)($sn['list_text'] ?? '')) ?: 'Sankciju saraksts';
        if (!isset($ts_sank_g[$list])) {
            $ts_sank_g[$list] = ['lomas' => [], 'no' => '', 'pamats' => '', 'url' => ''];
        }
        $loma = trim((string)($sn['position_text_lv'] ?? ''));
        if ($loma !== '' && !in_array($loma, $ts_sank_g[$list]['lomas'], true)) $ts_sank_g[$list]['lomas'][] = $loma;
        $d = $ts_date($sn['entry_date'] ?? '');
        if ($d !== '' && ($ts_sank_g[$list]['no'] === '' || $d < $ts_sank_g[$list]['no'])) $ts_sank_g[$list]['no'] = $d;
        $pb = trim((string)($sn['legal_base'] ?? ''));
        if ($pb !== '' && $ts_sank_g[$list]['pamats'] === '') $ts_sank_g[$list]['pamats'] = $pb;
        $u = trim((string)($sn['legal_base_url'] ?? ''));
        if ($u !== '' && $ts_sank_g[$list]['url'] === '' && preg_match('#^https?://#', $u)) $ts_sank_g[$list]['url'] = $u;
    }
?>
    <div class="ts-sec ts-sec-sank">
    <h4>Sankciju riski</h4>
    <ul class="ts-list">
<?php foreach ($ts_sank_g as $list => $g): ?>
        <li><strong><?= h($list) ?></strong>
            <?php if ($g['no'] !== ''): ?><span class="ts-muted">— no <?= h($g['no']) ?></span><?php endif; ?>
            <?php if ($g['lomas']): ?><br><span class="ts-muted">Saistība: <?= h(mb_strtolower(implode(', ', $g['lomas']), 'UTF-8')) ?></span><?php endif; ?>
            <?php if ($g['pamats'] !== ''): ?><br><span class="ts-muted"><?php if ($g['url'] !== ''): ?><a href="<?= h($g['url']) ?>" rel="noopener nofollow" target="_blank"><?= h($g['pamats']) ?></a><?php else: ?><?= h($g['pamats']) ?><?php endif; ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
    <p class="ts-sank-note">Uzņēmums ir sankciju subjekts vai, pēc Uzņēmumu reģistra rīcībā esošās informācijas,
        var atrasties sankciju subjekta faktiskā kontrolē. Ar sankciju subjektu saistītu fizisko personu vārdus
        nerādām — pārbaudiet oficiālajā avotā. Pirms darījuma pārliecinieties
        <a href="https://sankcijas.fid.gov.lv/" rel="noopener nofollow" target="_blank">sankciju subjektu meklētājā (FID)</a>.</p>
    </div>
<?php endif; ?>
<?php if ($ts_ptac): ?>
<?php
    // Grupējam pa veidiem un kārtojam pēc datuma no jaunākā.
    $ts_pt_g = ['melnais' => [], 'lemums' => [], 'apnemsanas' => []];
    foreach ($ts_ptac as $ts_p) {
        $ts_pv = trim((string)($ts_p['veids'] ?? ''));
        if (isset($ts_pt_g[$ts_pv])) $ts_pt_g[$ts_pv][] = $ts_p;
    }
    foreach ($ts_pt_g as $ts_pk => $ts_pv2) {
        usort($ts_pt_g[$ts_pk], static fn($a, $b) => strcmp((string)($b['no_dat'] ?? ''), (string)($a['no_dat'] ?? '')));
    }
    $ts_pt_eur = static function (float $v): string {
        return number_format($v, ($v == (int)$v ? 0 : 2), ',', ' ') . ' €';
    };
?>
    <div class="ts-sec">
    <h4>Patērētāju tiesību uzraudzība (PTAC)</h4>
    <ul class="ts-list">
<?php foreach (array_slice($ts_pt_g['melnais'], 0, $ts_lim) as $ts_p): ?>
        <li><strong>Iekļauts PTAC melnajā sarakstā</strong>
            <?php $ts_d = $ts_date($ts_p['no_dat'] ?? ''); if ($ts_d !== ''): ?><span class="ts-muted">— <?= h($ts_d) ?></span><?php endif; ?>
            <?php $ts_nr = trim((string)($ts_p['numurs'] ?? '')); $ts_st = trim((string)($ts_p['statuss'] ?? '')); ?>
            <?php if ($ts_nr !== '' || $ts_st !== ''): ?><br><span class="ts-muted"><?= h(trim(($ts_nr !== '' ? 'lēmums Nr. ' . $ts_nr : '') . ($ts_st !== '' ? ($ts_nr !== '' ? ', ' : '') . mb_strtolower($ts_st, 'UTF-8') : ''))) ?></span><?php endif; ?></li>
<?php endforeach; ?>
<?= pd_vairak(count($ts_pt_g['melnais']) - $ts_lim, 'tiesiskais', $ts_pilns) ?>
<?php foreach (array_slice($ts_pt_g['lemums'], 0, $ts_lim) as $ts_p): ?>
        <li><strong>PTAC lēmums<?php $ts_sn = (float)($ts_p['soda_nauda'] ?? 0); if ($ts_sn > 0): ?>, soda nauda <?= h($ts_pt_eur($ts_sn)) ?><?php endif; ?></strong>
            <?php $ts_d = $ts_date($ts_p['no_dat'] ?? ''); if ($ts_d !== ''): ?><span class="ts-muted">— <?= h($ts_d) ?></span><?php endif; ?>
            <?php $ts_jm = trim((string)($ts_p['joma'] ?? '')); $ts_st = trim((string)($ts_p['statuss'] ?? '')); ?>
            <?php if ($ts_jm !== '' || $ts_st !== ''): ?><br><span class="ts-muted"><?= h(trim($ts_jm . ($ts_st !== '' ? ($ts_jm !== '' ? ', ' : '') . mb_strtolower($ts_st, 'UTF-8') : ''))) ?></span><?php endif; ?></li>
<?php endforeach; ?>
<?= pd_vairak(count($ts_pt_g['lemums']) - $ts_lim, 'tiesiskais', $ts_pilns) ?>
<?php foreach (array_slice($ts_pt_g['apnemsanas'], 0, $ts_lim3) as $ts_p): ?>
        <li>Rakstveida apņemšanās
            <?php $ts_d = $ts_date($ts_p['no_dat'] ?? ''); if ($ts_d !== ''): ?><span class="ts-muted">— <?= h($ts_d) ?></span><?php endif; ?>
            <?php $ts_pp = trim((string)($ts_p['papildus'] ?? '')); if ($ts_pp !== ''): ?><br><span class="ts-muted"><?= h($ts_pp) ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
    <p class="ts-muted" style="margin:3px 0 0">Pilnu lēmuma tekstu skatiet
        <a href="https://www.ptac.gov.lv/" rel="noopener nofollow" target="_blank">PTAC vietnē</a> pēc lēmuma numura.</p>
    </div>
<?php endif; ?>
<?php if ($ts_susp): ?>
    <div class="ts-sec">
    <h4>Darbības liegumi un apturēšanas</h4>
    <ul class="ts-list">
<?php foreach ($ts_susp as $s): ?>
        <li><?= h(trim((string)($s['suspension_code_text'] ?? '')) ?: 'Darbības ierobežojums') ?>
            <?php $d = $ts_date($s['date_from'] ?? ''); if ($d !== ''): ?><span class="ts-muted">— no <?= h($d) ?></span><?php endif; ?>
            <?php $g = trim((string)($s['ground_for'] ?? '')); if ($g !== ''): ?><br><span class="ts-muted"><?= h($g) ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
    </div>
<?php endif; ?>

<?php if ($ts_vid): ?>
<?php
    // VID apturēšanas vēsture. DATUMU SLAZDI reālajos datos (pārbaudīti 72 617 rindās):
    //   - vērtības ar apkārtējām atstarpēm (" 09.01.2014 "), tukšums = "  ", ne NULL;
    //   - 2 rindas ar piedēkli "24.07.2026 (spēkā neesošs)" — naivs parsētājs krīt;
    //   - 13 rindas, kurās beigas ir PIRMS sākuma (VID atcēlis lēmumu ātrāk, nekā tas
    //     stājās spēkā) — periodu tur nerādām, jo "no 24.07. līdz 22.07." ir bezjēdzīgs;
    //   - tukšs "lidz" UN tukšs atjaunošanas datums = apturējums joprojām spēkā.
    // SVARĪGI: "spēkā" nosakām pēc SĀKOTNĒJIEM laukiem, ne pēc attīrītajiem — citādi
    //          apgriezts 2013. gada periods parādītos kā šodien spēkā esošs.
    $ts_d = static function ($v): string {
        $s = trim((string)$v);
        return preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $s, $m) ? "$m[3]-$m[2]-$m[1]" : '';
    };
    $ts_vid_rows = [];
    foreach ($ts_vid as $v) {
        $no   = $ts_d($v['Aizliegts_veikt_darijumus_no'] ?? '');
        $lidz = $ts_d($v['Aizliegts_veikt_darijumus_lidz'] ?? '');
        $lem  = $ts_d($v['Lemuma_datums'] ?? '');
        $atj  = $ts_d($v['Lemuma_par_atjaunosanu_datums'] ?? '');
        // "(spēkā neesošs)" piedēklis vai atjaunošana PIRMS sākuma = lēmums atcelts,
        // aizliegums spēkā nestājās — "Apturēta no X, atjaunota X-1d" bija nepatiess
        // (audits 2026-08-20: 40203582379).
        $nestajas = stripos((string)($v['Aizliegts_veikt_darijumus_no'] ?? ''), 'spēkā neesošs') !== false
            || ($atj !== '' && $no !== '' && $atj < $no)
            || ($lidz !== '' && $no !== '' && $lidz < $no);
        $ts_vid_rows[] = [
            'no' => $no, 'lidz' => $lidz, 'lem' => $lem, 'atj' => $atj,
            'aktivs'  => ($lidz === '' && $atj === '' && !$nestajas),
            'periods' => ($no !== '' && $lidz !== '' && $lidz >= $no),
            'nestajas' => $nestajas,
        ];
    }
    usort($ts_vid_rows, static function ($a, $b) {
        if ($a['aktivs'] !== $b['aktivs']) return $a['aktivs'] ? -1 : 1;
        return strcmp($b['no'] ?: $b['lem'], $a['no'] ?: $a['lem']);
    });
    $ts_vid_n = count($ts_vid_rows);
    $ts_vid_show = array_slice($ts_vid_rows, 0, 8);
?>
    <div class="ts-sec">
    <h4>VID darbības apturēšanas vēsture<?= $ts_vid_n > 1 ? ' (' . $ts_vid_n . ' reizes)' : '' ?></h4>
    <ul class="ts-list">
<?php foreach ($ts_vid_show as $v): ?>
<?php
    // Viena rinda = viens VID lēmums. Tekstu saliekam pa gabaliem, lai nekad
    // nerastos "Apturēta , atjaunota" ar karājošos komatu.
    $galv = 'Apturēta';
    if ($v['periods'])            $galv .= ' ' . $v['no'] . ' – ' . $v['lidz'];
    elseif ($v['no'] !== '')      $galv .= ' no ' . $v['no'];
    elseif ($v['lem'] !== '')     $galv .= ' ar VID ' . $v['lem'] . ' lēmumu';
?>
        <li><?php if ($v['nestajas']): ?><span class="ts-muted">Lēmums<?= $v['lem'] !== '' ? ' ' . h($v['lem']) : '' ?> atcelts pirms stāšanās spēkā — darbība netika apturēta</span>
            <?php elseif ($v['aktivs']): ?><strong><?= h($galv) ?></strong> <span class="ts-tag">spēkā</span>
            <?php else: ?><?= h($galv) ?><?php if ($v['atj'] !== ''): ?><span class="ts-muted">, atjaunota <?= h($v['atj']) ?></span><?php elseif ($v['lidz'] !== '' && !$v['periods']): ?><span class="ts-muted">, beigusies <?= h($v['lidz']) ?></span><?php endif; ?><?php endif; ?>
            <?php if ($v['lem'] !== '' && $v['no'] !== '' && !$v['periods']): ?><br><span class="ts-muted">VID lēmums <?= h($v['lem']) ?></span><?php endif; ?></li>
<?php endforeach; ?>
<?php if ($ts_vid_n > count($ts_vid_show)): ?>
        <?= pd_vairak($ts_vid_n - count($ts_vid_show), 'tiesiskais', $ts_pilns) ?>
<?php endif; ?>
    </ul>
    </div>
<?php endif; ?>
<?php if ($ts_liq): ?>
    <div class="ts-sec">
    <h4>Likvidācija</h4>
    <ul class="ts-list">
<?php foreach ($ts_liq as $l): ?>
        <li><?= h(trim((string)($l['liquidation_type_text'] ?? '')) ?: 'Likvidācija') ?>
            <?php $d = $ts_date($l['date_from'] ?? ''); if ($d !== ''): ?><span class="ts-muted">— no <?= h($d) ?></span><?php endif; ?>
            <?php $g = trim((string)($l['grounds_for_liquidation'] ?? '')); if ($g !== ''): ?><br><span class="ts-muted"><?= h($g) ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
    </div>
<?php endif; ?>

<?php if ($ts_sec): ?>
    <div class="ts-sec">
    <h4>Nodrošinājuma līdzekļi</h4>
    <ul class="ts-list">
<?php foreach ($ts_sec as $s): ?>
        <li><?= h(trim((string)($s['securing_measure_type_text'] ?? '')) ?: 'Nodrošinājuma līdzeklis') ?>
            <?php $d = $ts_date($s['date_from'] ?? ''); if ($d !== ''): ?><span class="ts-muted">— no <?= h($d) ?></span><?php endif; ?>
            <?php /* type_details = vienīgais lauks, kas atšķir viena datuma ierakstus
                     ("aizliegums pamatkapitāla samazināšanai" pret "aizliegums komercķīlas
                     reģistrācijai"); bez tā rindas izskatījās pēc dublikātiem (audits
                     2026-08-19). Dati jau izgājuši GDPR skrubi data_fetcher.php. */ ?>
            <?php $td = trim((string)($s['type_details'] ?? '')); if ($td !== ''): ?><br><span class="ts-muted"><?= h($td) ?></span><?php endif; ?>
            <?php $i = trim((string)($s['institution_name'] ?? '')); if ($i !== ''): ?><br><span class="ts-muted"><?= h($i) ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
    </div>
<?php endif; ?>

<?php $ts_body = ob_get_clean(); if (trim($ts_body) !== ''): ?>
    <div class="ts-body">
<?= $ts_body ?>
    </div>
<?php endif; ?>

<?php if ($ts_rows): ?>
<?php
    // Kolonnas, kas TUKŠAS visās rindās, nemaz nerādām — aktīviem procesiem
    // "Beidzies"/"Iznākums" vienmēr būtu tikai domuzīmes, un platajā panelī
    // tās radīja lielus tukšus laukumus.
    $ts_has_start = $ts_has_end = $ts_has_court = $ts_has_res = false;
    foreach ($ts_rows as $r) {
        if ($r['start'] !== '') $ts_has_start = true;
        if ($r['end']   !== '') $ts_has_end   = true;
        if ($r['court'] !== '') $ts_has_court = true;
        if ($r['res']   !== '') $ts_has_res   = true;
    }
?>
    <div class="ts-sec-table">
    <h4>Maksātnespējas un tiesiskās aizsardzības procesi</h4>
    <div class="table-responsive-wrapper">
        <table class="data-panel-table">
            <thead>
                <tr><th>Process</th><?php if ($ts_has_start): ?><th>Sākts</th><?php endif; ?><?php if ($ts_has_end): ?><th>Beidzies</th><?php endif; ?><?php if ($ts_has_court): ?><th>Tiesa</th><?php endif; ?><?php if ($ts_has_res): ?><th>Iznākums</th><?php endif; ?></tr>
            </thead>
            <tbody>
<?php foreach ($ts_rows as $r): ?>
                <tr<?= $r['active'] ? ' class="ts-row-active"' : '' ?>>
                    <td class="ts-c-proc"><?= h($r['label']) ?><?= $r['active'] ? ' <span class="ts-tag">aktīvs</span>' : '' ?>
                        <?php if ($r['case'] !== ''): ?><br><span class="ts-muted"><?= h($r['case']) ?></span><?php endif; ?></td>
<?php if ($ts_has_start): ?>
                    <td data-th="Sākts"><?= h($r['start'] !== '' ? $r['start'] : '—') ?></td>
<?php endif; ?>
<?php if ($ts_has_end): ?>
                    <td data-th="Beidzies"><?= h($r['end'] !== '' ? $r['end'] : '—') ?></td>
<?php endif; ?>
<?php if ($ts_has_court): ?>
                    <td data-th="Tiesa"><?= h($r['court'] !== '' ? $r['court'] : '—') ?></td>
<?php endif; ?>
<?php if ($ts_has_res): ?>
                    <td data-th="Iznākums"><?= h($r['res'] !== '' ? $r['res'] : '—') ?></td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>
<?php endif; ?>

<?php /* Skaidrojums TIKAI tad, ja tabula ar procesiem tiešām ir. Mērījums uz 300
         uzņēmumiem: 91 no tiem visi ieraksti ir vecāki par 10 gadu logu, tāpēc
         sadaļā nebija neviena ieraksta — tikai karogs, čips un šis 6 KB garais
         skaidrojums par tabulu, kuras nav. Tas ir tieši tas "tukšais laukums",
         kas jāizskauž: atvērtā sadaļa tagad rāda divas rindas, ne ekrānu teksta. */ ?>
<?php if ($ts_rows): ?>
    <details class="ts-note">
        <summary>Kā lasīt šos datus</summary>
        <p><em>Maksātnespējas process</em> nozīmē, ka parādnieks nespēj norēķināties ar
        kreditoriem. <em>Tiesiskās aizsardzības process (TAP)</em> ir kas cits — tas ir tiesas apstiprināts
        mēģinājums uzņēmumu saglabāt, vienojoties ar kreditoriem par parādu pārstrukturēšanu; veiksmīgi pabeigts
        TAP nozīmē, ka uzņēmums no krīzes izkļuva. Pabeigti procesi ir vēsture, nevis pašreizējais stāvoklis.
        Avots: <a href="https://data.gov.lv/dati/lv/dataset/maksatnespejas-procesi" rel="noopener">Uzņēmumu reģistra atvērtie dati</a>
        (maksātnespējas reģistrs, likvidācijas, liegumi, nodrošinājuma līdzekļi).</p>
    </details>
<?php endif; ?>
</div>
