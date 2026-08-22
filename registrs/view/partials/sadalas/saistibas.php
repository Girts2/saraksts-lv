<?php
/**
 * sadalas/saistibas.php — "Saistītie uzņēmumi un izmaiņas" sadaļa (publiska).
 *
 * Sekcijas: Mātes uzņēmumi / dalībnieki (tikai juridiskās personas), Meitas
 * uzņēmumi / dalības, Reorganizācijas, Agrākie nosaukumi. Panelis rādās TIKAI
 * tad, ja ir vismaz viens ieraksts. Saites starp saistītajiem uzņēmumiem ir
 * arī iekšējais linkings (GSC indeksācijai).
 *
 * GDPR: vaicājam TIKAI entity_type LEGAL_ENTITY/FOREIGN_ENTITY — fizisko
 * personu dalībnieki netiek ne vaicāti, ne rādīti (Girta 2026-07-24 lēmums
 * par personas datu izņemšanu). FOREIGN_ENTITY pārbaudīts 2026-08-18: visi
 * 5493 ieraksti bez birth_date un personas koda (Ltd./GmbH/B.V. u.tml.).
 *
 * Dati: reorganizations + register_name_history + equity_capitals jau ir
 * $page_data['results'] (SEARCH_COLUMNS_MAP_REG_NR); members abos virzienos
 * partial vaicā pats (members apzināti NAV rezultātu kartē — tur ir fiziskas
 * personas, mēs atlasām tikai juridiskās).
 */
/** @var array $page_data */

require_once __DIR__ . '/../../_tpl.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/sadalu_formats.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/public_owner.php';

$sa_reg = preg_replace('/\D/', '', (string)($page_data['search_reg_nr'] ?? ''));
$sa_res = is_array($page_data['results'] ?? null) ? $page_data['results'] : [];
if (strlen($sa_reg) !== 11) return;

$sa_date = static function ($v): string {
    $s = trim((string)$v);
    return ($s === '' || $s === '0000-00-00') ? '' : substr($s, 0, 10);
};

try { $sa_db = get_ur_db(); } catch (Throwable $e) { return; }

// --- Mātes: šī uzņēmuma dalībnieki, kas ir juridiskās personas -----------------
$sa_par_raw = [];
try {
    $st = $sa_db->prepare("SELECT entity_type, name, legal_entity_registration_number reg,
            number_of_shares, share_nominal_value, share_currency, date_from
        FROM members
        WHERE at_legal_entity_registration_number = ? AND entity_type IN ('LEGAL_ENTITY','FOREIGN_ENTITY')
          AND IFNULL(legal_entity_registration_number,'') <> at_legal_entity_registration_number
        UNION ALL
        SELECT entity_type, name, legal_entity_registration_number reg,
            number_of_shares, share_nominal_value, share_currency, date_from
        FROM stockholders
        WHERE at_legal_entity_registration_number = ? AND entity_type IN ('LEGAL_ENTITY','FOREIGN_ENTITY')
          AND IFNULL(legal_entity_registration_number,'') <> at_legal_entity_registration_number");
    $st->execute([$sa_reg, $sa_reg]);
    $sa_par_raw = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// --- Meitas: šis uzņēmums kā dalībnieks/akcionārs citos -------------------------
$sa_sub_raw = [];
try {
    $st = $sa_db->prepare("SELECT at_legal_entity_registration_number reg,
            number_of_shares, share_nominal_value, share_currency, date_from
        FROM members
        WHERE legal_entity_registration_number = ? AND entity_type = 'LEGAL_ENTITY'
          AND at_legal_entity_registration_number <> legal_entity_registration_number
        UNION ALL
        SELECT at_legal_entity_registration_number reg,
            number_of_shares, share_nominal_value, share_currency, date_from
        FROM stockholders
        WHERE legal_entity_registration_number = ? AND entity_type = 'LEGAL_ENTITY'
          AND at_legal_entity_registration_number <> legal_entity_registration_number");
    $st->execute([$sa_reg, $sa_reg]);
    $sa_sub_raw = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$sa_reorg = is_array($sa_res['reorganizations'] ?? null) ? $sa_res['reorganizations'] : [];
$sa_names = is_array($sa_res['register_name_history'] ?? null) ? $sa_res['register_name_history'] : [];

if (!$sa_par_raw && !$sa_sub_raw && !$sa_reorg && !$sa_names) return;

// --- Palīgi --------------------------------------------------------------------
/**
 * Pamatkapitāla bāze [summa, valūta] procentu saucējam.
 *
 * TIPS IR SVARĪGĀKS PAR DATUMU (audits 2026-08-19): daļas UR datos ir izteiktas
 * pret REĢISTRĒTO pamatkapitālu, tāpēc dalīšana ar APMAKSĀTO (kas daļēji apmaksātām
 * sabiedrībām ir mazāks) deva absurdus procentus lapā — trīs dalībnieki pa 48 % vai
 * divi pa "100 %". Ņemam jaunāko REGISTERED_EQUITY; ja tāda nav (kopā 3071 rinda
 * pret 139 142 PAID_UP) — jaunāko PAID_UP_EQUITY. Pārējos tipus (INVESTMENT,
 * CONDITIONAL_EQUITY, EQUITY_KB, MINIMUM_RESERVES) par bāzi nelietojam vispār.
 */
$sa_capital = static function (array $rows): array {
    $best = [];   // tips => [summa, valūta, datums]
    foreach ($rows as $r) {
        $amt = (float)($r['amount'] ?? 0);
        if ($amt <= 0) continue;
        $type = strtoupper(trim((string)($r['equity_capital_type'] ?? '')));
        if ($type !== 'REGISTERED_EQUITY' && $type !== 'PAID_UP_EQUITY') continue;
        $d = (string)($r['date_from'] ?? '');
        if (!isset($best[$type]) || $d > $best[$type][2]) {
            $best[$type] = [$amt, (string)($r['currency'] ?? ''), $d];
        }
    }
    $pick = $best['REGISTERED_EQUITY'] ?? $best['PAID_UP_EQUITY'] ?? null;
    return $pick === null ? [0.0, ''] : [$pick[0], $pick[1]];
};
/** Daļas % pret kapitālu; '' ja nav droši izrēķināms. */
$sa_pct = static function (float $val, string $cur, float $cap, string $capCur): string {
    if ($val <= 0 || $cap <= 0 || $cur === '' || $cur !== $capCur) return '';
    $p = $val / $cap * 100;
    if ($p > 100.5) return '';
    if ($p >= 99.95) return '100 %';
    return number_format($p, $p < 10 ? 1 : 0, ',', ' ') . ' %';
};

// Šī uzņēmuma pamatkapitāls (mātes daļu procentiem).
[$sa_cap_amt, $sa_cap_cur] = $sa_capital(is_array($sa_res['equity_capitals'] ?? null) ? $sa_res['equity_capitals'] : []);

// Mātes: apvieno viena dalībnieka vairākas paketes.
$sa_parents = [];
foreach ($sa_par_raw as $r) {
    $key = ($r['reg'] ?: '') . '|' . mb_strtolower(trim((string)$r['name']));
    if (!isset($sa_parents[$key])) {
        $sa_parents[$key] = ['name' => trim((string)$r['name']), 'reg' => trim((string)($r['reg'] ?? '')),
            'foreign' => $r['entity_type'] === 'FOREIGN_ENTITY', 'val' => 0.0,
            'cur' => (string)($r['share_currency'] ?? ''), 'from' => $sa_date($r['date_from'] ?? '')];
    }
    $sa_parents[$key]['val'] += (float)($r['number_of_shares'] ?? 0) * (float)($r['share_nominal_value'] ?? 0);
    $d = $sa_date($r['date_from'] ?? '');
    if ($d !== '' && ($sa_parents[$key]['from'] === '' || $d < $sa_parents[$key]['from'])) $sa_parents[$key]['from'] = $d;
}
$sa_parents = array_values($sa_parents);
usort($sa_parents, static fn($a, $b) => $b['val'] <=> $a['val']);

// Meitas: apvieno paketes pa uzņēmumiem.
$sa_subs = [];
foreach ($sa_sub_raw as $r) {
    $reg = trim((string)($r['reg'] ?? ''));
    if ($reg === '') continue;
    if (!isset($sa_subs[$reg])) $sa_subs[$reg] = ['reg' => $reg, 'val' => 0.0, 'cur' => (string)($r['share_currency'] ?? ''), 'from' => $sa_date($r['date_from'] ?? '')];
    $sa_subs[$reg]['val'] += (float)($r['number_of_shares'] ?? 0) * (float)($r['share_nominal_value'] ?? 0);
    $d = $sa_date($r['date_from'] ?? '');
    if ($d !== '' && ($sa_subs[$reg]['from'] === '' || $d < $sa_subs[$reg]['from'])) $sa_subs[$reg]['from'] = $d;
}
$sa_subs = array_values($sa_subs);
// Kārto pēc dalības vērtības: pie nogriešanas (SA_LIM) citādi varēja pazust
// 100 % dalība, kamēr redzama palika 0,1 % (audits 2026-08-19).
usort($sa_subs, static fn($a, $b) => $b['val'] <=> $a['val']);

// --- Saistīto uzņēmumu vārdi, statusi un kapitāli (vienā piegājienā) -----------
$sa_lookup = [];
foreach ($sa_subs as $s2) $sa_lookup[$s2['reg']] = true;
foreach ($sa_parents as $p2) { if (!$p2['foreign'] && $p2['reg'] !== '') $sa_lookup[$p2['reg']] = true; }
foreach ($sa_reorg as $r) {
    foreach (['source_entity_regcode', 'final_entity_regcode'] as $f) {
        $x = trim((string)($r[$f] ?? ''));
        if ($x !== '' && $x !== $sa_reg) $sa_lookup[$x] = true;
    }
}
$sa_info = []; // reg => ['name'=>, 'closed'=>bool]
$sa_caps = []; // reg => [amt, cur]
if ($sa_lookup) {
    $regs = array_keys($sa_lookup);
    foreach (array_chunk($regs, 400) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        try {
            $st = $sa_db->prepare("SELECT regcode, name, closed FROM register WHERE regcode IN ($ph)");
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $sa_info[(string)$r['regcode']] = ['name' => (string)$r['name'],
                    'closed' => in_array((string)($r['closed'] ?? ''), ['L', 'R'], true)];
            }
        } catch (Throwable $e) {}
        try {
            $st = $sa_db->prepare("SELECT legal_entity_registration_number reg, amount, currency, date_from, equity_capital_type FROM equity_capitals WHERE legal_entity_registration_number IN ($ph)");
            $st->execute($chunk);
            $tmp = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $tmp[(string)$r['reg']][] = $r;
            foreach ($tmp as $rg => $rows) $sa_caps[$rg] = $sa_capital($rows);
        } catch (Throwable $e) {}
    }
}

/** Juridiskās formas īsais pieraksts attēlošanai (tas pats šablons, kas
 *  test_panel.php $tst_short_form; + VAS/VU vēsturiskajiem nosaukumiem).
 *  Tikai attēlošanai — datos nosaukumi paliek pilnie. */
$sa_short = static function (string $n): string {
    // \w{0,3} galā = locījumu galotnes: reorganizāciju un vēsturiskajos
    // nosaukumos formas mēdz būt ģenitīvā ("Valsts akciju sabiedrībAS
    // "LATVENERGO" filiāle") — bez tā sanāca "VASs".
    static $repl = [
        '/privatizējam\w{0,3} valsts akciju sabiedrīb\w{0,3}/iu' => 'P VAS',
        '/valsts akciju sabiedrīb\w{0,3}/iu' => 'VAS',
        '/valsts uzņēmum\w{0,3}/iu' => 'VU',
        '/sabiedrīb\w{0,3} ar ierobežotu atbildību/iu' => 'SIA',
        '/akciju sabiedrīb\w{0,3}/iu' => 'AS',
        '/individuāl\w{0,4}\s+komersant\w{0,3}/iu' => 'IK',
        '/individuāl\w{0,4}\s+uzņēmum\w{0,3}/iu' => 'IU',
        '/zemnieku saimniecīb\w{0,3}/iu' => 'ZS',
        '/zvejnieku saimniecīb\w{0,3}/iu' => 'ZvS',
        '/pilnsabiedrīb\w{0,3}/iu' => 'PS',
        '/komandītsabiedrīb\w{0,3}/iu' => 'KS',
        '/kooperatīv\w{0,4}\s+sabiedrīb\w{0,3}/iu' => 'Koop. sab.',
        '/ārvalsts komersanta filiāle/iu' => 'filiāle',
    ];
    $out = preg_replace(array_keys($repl), array_values($repl), $n);
    return is_string($out) ? trim($out) : $n;
};

/**
 * Saite uz uzņēmuma lapu, ja tas ir reģistrā; citādi vienkāršs teksts.
 *
 * SAITI VEIDO TIKAI 11 CIPARU KODAM: reorganizāciju ierakstos (un pāris akcionāru
 * rindās) mēdz būt vēsturiski 9 ciparu reģistrācijas numuri, kuriem lapas nav —
 * htaccess/router apkalpo tikai ^[0-9]{11}$, tāpēc tādas saites vienmēr atgrieza
 * 404 (~2000 mirušu iekšējo saišu 1884 lapās; audits 2026-08-19). Nosaukumu un
 * "(likvidēts)" atzīmi rādām joprojām — zūd tikai neizpildāmā saite.
 */
$sa_link = static function (string $reg, string $fallback = '') use ($sa_info, $sa_short): string {
    $name = $sa_info[$reg]['name'] ?? '';
    if ($name === '') return h($fallback !== '' ? $sa_short($fallback) : ($reg !== '' ? 'reģ. Nr. ' . $reg : ''));
    $liq = !empty($sa_info[$reg]['closed']) ? ' <span class="sa-muted">(likvidēts)</span>' : '';
    if (!preg_match('/^\d{11}$/', $reg)) return h($sa_short($name)) . $liq;
    return '<a href="/' . h($reg) . '">' . h($sa_short($name)) . '</a>' . $liq;
};

// --- Reorganizāciju teikumi ----------------------------------------------------
// source = uzņēmums, PAR kuru type_text runā ("Apvienošanas ceļā pievienota pie
// {final}"); final pusei formulējums jāapgriež.
$sa_reorg_rows = [];
foreach ($sa_reorg as $r) {
    $src = trim((string)($r['source_entity_regcode'] ?? ''));
    $fin = trim((string)($r['final_entity_regcode'] ?? ''));
    $d   = $sa_date($r['registered'] ?? '');
    $t   = strtoupper(trim((string)($r['reorganization_type'] ?? '')));
    $txt = trim((string)($r['reorganization_type_text'] ?? '')) ?: 'Reorganizācija';
    if ($src === $sa_reg && $fin !== '' && $fin !== $sa_reg) {
        // "Apvienošanas ceļā pievienota pie X" / "Pārveidota uz X" lasās tieši;
        // pārējiem tipiem starpā vajag bultu.
        $sep = in_array($t, ['ACQUISITION', 'MERGER', 'TRANSFORMATION'], true) ? ' ' : ' → ';
        $sa_reorg_rows[] = ['d' => $d, 'html' => h($txt) . $sep . $sa_link($fin)];
    } elseif ($fin === $sa_reg && $src !== '' && $src !== $sa_reg) {
        $map = [
            'ACQUISITION'    => '%s apvienošanas ceļā pievienots šim uzņēmumam',
            'MERGER'         => '%s saplūšanas ceļā pievienots šim uzņēmumam',
            'SEPARATION'     => 'Nodalīts no %s',
            'DIVISION'       => 'Izveidots, sašķeļot %s',
            'TRANSFORMATION' => 'Izveidots, pārveidojot %s',
        ];
        $fmt = $map[$t] ?? ('Reorganizācija ar %s');
        $sa_reorg_rows[] = ['d' => $d, 'html' => sprintf($fmt, $sa_link($src))];
    } else {
        $sa_reorg_rows[] = ['d' => $d, 'html' => h($txt)];
    }
}
usort($sa_reorg_rows, static fn($a, $b) => strcmp($b['d'], $a['d']));

// --- Agrākie nosaukumi: jaunākie pirmie, bez dubultiem -------------------------
$sa_name_rows = [];
foreach ($sa_names as $r) {
    $nm = trim((string)($r['name'] ?? ''));
    if ($nm === '') continue;
    $sa_name_rows[$nm . '|' . ($r['date_to'] ?? '')] = ['d' => $sa_date($r['date_to'] ?? ''), 'name' => $nm];
}
$sa_name_rows = array_values($sa_name_rows);
usort($sa_name_rows, static fn($a, $b) => strcmp($b['d'], $a['d']));

// --- Sakļaušanas robežas (galējības: 201 dalībnieks, 61 meita, 41 reorg) ------
$sa_pilns = $pd_pilns ?? false;
$SA_LIM = ['par' => pd_limits($sa_pilns, 24), 'sub' => pd_limits($sa_pilns, 24),
           'reorg' => pd_limits($sa_pilns, 12), 'names' => pd_limits($sa_pilns, 8)];

$sa_n_par = count($sa_parents);
$sa_n_sub = count($sa_subs);
$sa_n_reo = count($sa_reorg_rows);
$sa_n_nam = count($sa_name_rows);

$sa_chips = [];
if ($sa_n_par) $sa_chips[] = 'Dalībnieki (jur.): ' . $sa_n_par;
if ($sa_n_sub) $sa_chips[] = 'Meitas/dalības: ' . $sa_n_sub;
if ($sa_n_reo) $sa_chips[] = 'Reorganizācijas: ' . $sa_n_reo;
if ($sa_n_nam) $sa_chips[] = 'Agrākie nosaukumi: ' . $sa_n_nam;

// Platums platajā skatā VIENMĒR pilns (divu paneļu rinda) — arī pie maza satura.
// Agrākais "sa-compact" sašaurinājums (824px) taupīja tukšumu paša paneļa iekšienē,
// bet lapā atstāja plašu baltu joslu tam blakus (Girta 2026-08-19 precizējums):
// pilna platuma josla lapas ritmā izskatās labāk nekā sarāvies panelis tukšumā.
// --- Sadaļas līgums (papildu_dati_panel.php) ---
$pd_nos = 'Saistītie uzņēmumi un izmaiņas';
$pd_n = $sa_n_par + $sa_n_sub + $sa_n_reo + $sa_n_nam;
$pd_kops = $sa_chips ? $sa_chips[0] : '';
?>
<div class="saistibas-facts">
    <div class="sa-head">
<?php foreach ($sa_chips as $c): ?>
        <span class="sa-chip"><?= h($c) ?></span>
<?php endforeach; ?>
    </div>

    <div class="sa-body">
<?php if ($sa_parents): ?>
    <div class="sa-sec">
    <h4>Mātes uzņēmumi / dalībnieki</h4>
    <ul class="sa-list">
<?php foreach (array_slice($sa_parents, 0, $SA_LIM['par']) as $p): ?>
        <li><?php if (!$p['foreign'] && $p['reg'] !== '' && isset($sa_info[$p['reg']])): ?><?= $sa_link($p['reg'], $p['name']) ?><?php else: ?><?= h($sa_short($p['name'])) ?><?php $pub = reg_public_owner_type($p['name']); ?><?= $pub !== '' ? ' <span class="sa-muted">(' . h($pub) . ')</span> <a class="sa-owner-link" href="/ipasnieks/' . h(reg_public_owner_slug($p['name'])) . '">visi tā uzņēmumi →</a>' : ($p['foreign'] ? ' <span class="sa-muted">(ārvalstu)</span>' : '') ?><?php endif; ?>
            <?php $pct = $sa_pct($p['val'], $p['cur'], $sa_cap_amt, $sa_cap_cur); if ($pct !== ''): ?><span class="sa-pct"><?= h($pct) ?></span><?php endif; ?>
            <?php if ($p['from'] !== ''): ?><span class="sa-muted">no <?= h($p['from']) ?></span><?php endif; ?></li>
<?php endforeach; ?>
<?php if ($sa_n_par > $SA_LIM['par']): ?>
        <?= pd_vairak($sa_n_par - $SA_LIM['par'], 'saistibas', $sa_pilns) ?>
<?php endif; ?>
    </ul>
    </div>
<?php endif; ?>

<?php if ($sa_subs): ?>
    <div class="sa-sec">
    <h4>Meitas uzņēmumi / dalības</h4>
    <ul class="sa-list">
<?php foreach (array_slice($sa_subs, 0, $SA_LIM['sub']) as $s2): ?>
        <li><?= $sa_link($s2['reg']) ?>
            <?php [$cAmt, $cCur] = $sa_caps[$s2['reg']] ?? [0.0, '']; $pct = $sa_pct($s2['val'], $s2['cur'], $cAmt, $cCur); if ($pct !== ''): ?><span class="sa-pct"><?= h($pct) ?></span><?php endif; ?>
            <?php if ($s2['from'] !== ''): ?><span class="sa-muted">no <?= h($s2['from']) ?></span><?php endif; ?></li>
<?php endforeach; ?>
<?php if ($sa_n_sub > $SA_LIM['sub']): ?>
        <?= pd_vairak($sa_n_sub - $SA_LIM['sub'], 'saistibas', $sa_pilns) ?>
<?php endif; ?>
    </ul>
    </div>
<?php endif; ?>

<?php if ($sa_reorg_rows): ?>
    <div class="sa-sec">
    <h4>Reorganizācijas</h4>
    <ul class="sa-list">
<?php foreach (array_slice($sa_reorg_rows, 0, $SA_LIM['reorg']) as $r): ?>
        <li><?php if ($r['d'] !== ''): ?><span class="sa-muted"><?= h($r['d']) ?></span> — <?php endif; ?><?= $r['html'] ?></li>
<?php endforeach; ?>
<?php if ($sa_n_reo > $SA_LIM['reorg']): ?>
        <?= pd_vairak($sa_n_reo - $SA_LIM['reorg'], 'saistibas', $sa_pilns) ?>
<?php endif; ?>
    </ul>
    </div>
<?php endif; ?>

<?php if ($sa_name_rows): ?>
    <div class="sa-sec">
    <h4>Agrākie nosaukumi</h4>
    <ul class="sa-list">
<?php foreach (array_slice($sa_name_rows, 0, $SA_LIM['names']) as $n): ?>
        <li><?= h($sa_short($n['name'])) ?><?php if ($n['d'] !== ''): ?> <span class="sa-muted">— līdz <?= h($n['d']) ?></span><?php endif; ?></li>
<?php endforeach; ?>
<?php if ($sa_n_nam > $SA_LIM['names']): ?>
        <?= pd_vairak($sa_n_nam - $SA_LIM['names'], 'saistibas', $sa_pilns) ?>
<?php endif; ?>
    </ul>
    </div>
<?php endif; ?>
    </div>

    <p class="sa-note">Rādītas tikai juridisko personu saistības; fizisko personu dalībnieki netiek rādīti.
        Daļu % pret reģistrēto pamatkapitālu. Avots: Uzņēmumu reģistra atvērtie dati.</p>
</div>
