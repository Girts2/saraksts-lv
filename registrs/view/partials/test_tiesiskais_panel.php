<?php
/**
 * test_tiesiskais_panel.php — "Test Tiesiskais statuss" panelis (lokālā testa vide).
 *
 * Redzams karogs uzņēmuma lapā par maksātnespējas procesiem, tiesiskās aizsardzības
 * procesiem (TAP), likvidācijām, darbības liegumiem un nodrošinājuma līdzekļiem.
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
 * iznākums). Administratoru un citu fizisko personu tabulās nav un netiek vaicāti.
 *
 * Panelis parādās TIKAI tad, ja ir vismaz viens ieraksts — "tīrajiem" uzņēmumiem
 * (95 % gadījumu) lapa paliek nemainīga.
 */
/** @var array $page_data */

require_once __DIR__ . '/../_tpl.php';

$ts_res = $page_data['results'] ?? [];
if (!is_array($ts_res)) $ts_res = [];

$ts_proc  = is_array($ts_res['insolvency_legal_person_proceeding'] ?? null) ? $ts_res['insolvency_legal_person_proceeding'] : [];
$ts_liq   = is_array($ts_res['liquidations'] ?? null) ? $ts_res['liquidations'] : [];
$ts_susp  = is_array($ts_res['suspensions_prohibitions'] ?? null) ? $ts_res['suspensions_prohibitions'] : [];
$ts_sec   = is_array($ts_res['securing_measures'] ?? null) ? $ts_res['securing_measures'] : [];
if (!$ts_proc && !$ts_liq && !$ts_susp && !$ts_sec) return;

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
$ts_rows = [];
$ts_active_mn = $ts_active_tap = 0;
$ts_past_mn = $ts_past_tap = 0;
foreach ($ts_proc as $p) {
    [$label, $kind] = $ts_form($p['proceeding_form'] ?? null);
    $start = $ts_date($p['proceeding_started_on'] ?? '');
    $end   = $ts_date($p['proceeding_ended_on'] ?? '');
    $active = ($start !== '' && $end === '');
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
if ($ts_active_mn > 0 || $ts_active_liq > 0) {
    $ts_level = 'risk';
    $ts_head  = $ts_active_mn > 0 ? 'Aktīvs maksātnespējas process' : 'Uzsākta likvidācija';
} elseif ($ts_active_tap > 0) {
    $ts_level = 'warn';
    $ts_head  = 'Aktīvs tiesiskās aizsardzības process';
} elseif ($ts_susp || $ts_sec) {
    $ts_level = 'warn';
    $ts_head  = 'Reģistrēti darbības ierobežojumi';
} else {
    $ts_level = 'past';
    $ts_head  = 'Vēsturiski procesi — šobrīd aktīvu nav';
}

$ts_chips = [];
if ($ts_active_mn)  $ts_chips[] = ['risk', $ts_active_mn . ' aktīvs maksātnespējas process'];
if ($ts_active_tap) $ts_chips[] = ['warn', $ts_active_tap . ' aktīvs TAP'];
if ($ts_past_mn)    $ts_chips[] = ['past', $ts_past_mn . ' pabeigts maksātnespējas process'];
if ($ts_past_tap)   $ts_chips[] = ['past', $ts_past_tap . ' pabeigts TAP'];
if ($ts_active_liq) $ts_chips[] = ['risk', 'likvidācija'];
if ($ts_susp)       $ts_chips[] = ['warn', count($ts_susp) . ' darbības liegums/apturēšana'];
if ($ts_sec)        $ts_chips[] = ['warn', count($ts_sec) . ' nodrošinājuma līdzeklis'];
?>
<div class="balance-facts tiesiskais-facts ts-<?= h($ts_level) ?>">
    <h2>🧪 Test Tiesiskais statuss</h2>
    <div class="ts-flag ts-flag-<?= h($ts_level) ?>">
        <strong><?= h($ts_head) ?></strong>
    </div>

<?php if ($ts_chips): ?>
    <div class="ts-chips">
<?php foreach ($ts_chips as [$cls, $txt]): ?>
        <span class="ts-chip ts-chip-<?= h($cls) ?>"><?= h($txt) ?></span>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($ts_rows): ?>
    <h4>Maksātnespējas un tiesiskās aizsardzības procesi</h4>
    <div class="table-responsive-wrapper">
        <table class="data-panel-table">
            <thead>
                <tr><th>Process</th><th>Sākts</th><th>Beidzies</th><th>Tiesa</th><th>Iznākums</th></tr>
            </thead>
            <tbody>
<?php foreach ($ts_rows as $r): ?>
                <tr<?= $r['active'] ? ' class="ts-row-active"' : '' ?>>
                    <td><?= h($r['label']) ?><?= $r['active'] ? ' <span class="ts-tag">aktīvs</span>' : '' ?>
                        <?php if ($r['case'] !== ''): ?><br><span class="ts-muted"><?= h($r['case']) ?></span><?php endif; ?></td>
                    <td><?= h($r['start'] !== '' ? $r['start'] : '—') ?></td>
                    <td><?= h($r['end'] !== '' ? $r['end'] : '—') ?></td>
                    <td><?= h($r['court'] !== '' ? $r['court'] : '—') ?></td>
                    <td><?= h($r['res'] !== '' ? $r['res'] : '—') ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($ts_liq): ?>
    <h4>Likvidācija</h4>
    <ul class="ts-list">
<?php foreach ($ts_liq as $l): ?>
        <li><?= h(trim((string)($l['liquidation_type_text'] ?? '')) ?: 'Likvidācija') ?>
            <?php $d = $ts_date($l['date_from'] ?? ''); if ($d !== ''): ?><span class="ts-muted">— no <?= h($d) ?></span><?php endif; ?>
            <?php $g = trim((string)($l['grounds_for_liquidation'] ?? '')); if ($g !== ''): ?><br><span class="ts-muted"><?= h($g) ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($ts_susp): ?>
    <h4>Darbības liegumi un apturēšanas</h4>
    <ul class="ts-list">
<?php foreach ($ts_susp as $s): ?>
        <li><?= h(trim((string)($s['suspension_code_text'] ?? '')) ?: 'Darbības ierobežojums') ?>
            <?php $d = $ts_date($s['date_from'] ?? ''); if ($d !== ''): ?><span class="ts-muted">— no <?= h($d) ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($ts_sec): ?>
    <h4>Nodrošinājuma līdzekļi</h4>
    <ul class="ts-list">
<?php foreach ($ts_sec as $s): ?>
        <li><?= h(trim((string)($s['securing_measure_type_text'] ?? '')) ?: 'Nodrošinājuma līdzeklis') ?>
            <?php $d = $ts_date($s['date_from'] ?? ''); if ($d !== ''): ?><span class="ts-muted">— no <?= h($d) ?></span><?php endif; ?>
            <?php $i = trim((string)($s['institution_name'] ?? '')); if ($i !== ''): ?><br><span class="ts-muted"><?= h($i) ?></span><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>

    <p class="ts-note">
        <strong>Kā lasīt:</strong> <em>maksātnespējas process</em> nozīmē, ka parādnieks nespēj norēķināties ar
        kreditoriem. <em>Tiesiskās aizsardzības process (TAP)</em> ir kas cits — tas ir tiesas apstiprināts
        mēģinājums uzņēmumu saglabāt, vienojoties ar kreditoriem par parādu pārstrukturēšanu; veiksmīgi pabeigts
        TAP nozīmē, ka uzņēmums no krīzes izkļuva. Pabeigti procesi ir vēsture, nevis pašreizējais stāvoklis.
        Avots: <a href="https://data.gov.lv/dati/lv/dataset/maksatnespejas-procesi" rel="noopener">Uzņēmumu reģistra atvērtie dati</a>
        (maksātnespējas reģistrs, likvidācijas, liegumi, nodrošinājuma līdzekļi).
    </p>
</div>
<style>
.single-column-layout > .tiesiskais-facts{order:6;flex-basis:100%;max-width:none}
.tiesiskais-facts .ts-flag{border-radius:6px;padding:9px 14px;margin:2px 0 12px;font-size:15px}
.tiesiskais-facts .ts-flag-risk{background:#fdecea;border:1px solid #f5c2bc;color:#8a1c11}
.tiesiskais-facts .ts-flag-warn{background:#fff8e1;border:1px solid #ffe08a;color:#7a5d00}
.tiesiskais-facts .ts-flag-past{background:#eef2f7;border:1px solid #d6dee8;color:#41526b}
.ts-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}
.ts-chip{border-radius:6px;padding:4px 10px;font-size:13px;border:1px solid}
.ts-chip-risk{background:#fdecea;border-color:#f5c2bc;color:#8a1c11}
.ts-chip-warn{background:#fff8e1;border-color:#ffe08a;color:#7a5d00}
.ts-chip-past{background:#f4f6f8;border-color:#dfe4ea;color:#556}
.tiesiskais-facts h4{margin:14px 0 6px}
.tiesiskais-facts .ts-row-active{background:#fffaf8;font-weight:500}
.tiesiskais-facts .ts-tag{background:#8a1c11;color:#fff;border-radius:4px;padding:1px 6px;font-size:11px;vertical-align:1px}
.tiesiskais-facts .ts-muted{color:#777;font-size:12.5px}
.tiesiskais-facts .ts-list{margin:0;padding-left:18px;font-size:14px}
.tiesiskais-facts .ts-list li{margin-bottom:5px}
.tiesiskais-facts .ts-note{margin-top:12px;color:#777;font-size:12.5px;line-height:1.55}
</style>
