<?php
/**
 * test_panel.php — "⚔️ Konkurentu salīdzinājums" panelis.
 * Rāda visas nozares (NACE) uzņēmumu salīdzinājumu: apgrozījums, peļņa, marža,
 * darbinieki, produktivitāte un aprēķinātā vidējā alga (sortējams, UR/VID pārslēgs).
 *
 * Datu avoti: $page_data (page_builder), $conn (ur_data.db, read-only),
 * assets/search/nace_stats.sqlite. TIKAI uzņēmumu līmeņa agregāti — NEKĀDI personas dati:
 * amatpersonas, dalībnieki un īpašnieki netiek vaicāti, apstrādāti vai attēloti.
 *
 * (Iepriekšējās "Uzņēmuma laika līnija" un "Īpašnieku tīkls" sekcijas noņemtas 2026-07-24
 * pēc lietotāja lūguma — nekādas saistības ar fizisko personu datiem.)
 */
/** @var array $page_data */
/** @var PDO $conn */

require_once __DIR__ . '/../_tpl.php';
// tst_* palīgi (skaitļi, ceturkšņi) dzīvo kopīgajā risk_semaphore.php bibliotēkā.
require_once __DIR__ . '/../../lib/risk_semaphore.php';

$tst_reg = (string)($page_data['search_reg_nr'] ?? '');
$tst_peers = null;

// Uzņēmējdarbības formu saīsināšana tabulas nosaukumos ("Sabiedrība ar ierobežotu
// atbildību" -> "SIA" u.c.) — tikai attēlošanai, saites ved uz pilno.
$tst_short_form = function (string $n): string {
    static $repl = [
        '/sabiedrība ar ierobežotu atbildību/iu' => 'SIA',
        '/akciju sabiedrība/iu' => 'AS',
        '/individuālais komersants/iu' => 'IK',
        '/individuālais uzņēmums/iu' => 'IU',
        '/zemnieku saimniecība/iu' => 'ZS',
        '/zvejnieku saimniecība/iu' => 'ZvS',
        '/pilnsabiedrība/iu' => 'PS',
        '/komandītsabiedrība/iu' => 'KS',
        '/kooperatīvā sabiedrība/iu' => 'Koop. sab.',
        '/ārvalsts komersanta filiāle/iu' => 'filiāle',
    ];
    $out = preg_replace(array_keys($repl), array_values($repl), $n);
    return is_string($out) ? trim($out) : $n;
};

// Nozares vaicājums: IN (...) pa gabaliem, lai nepārsniegtu SQLite mainīgo limitu.
$tst_chunk_query = function (PDO $conn, string $sql_tpl, array $codes, int $chunk = 400): array {
    $out = [];
    foreach (array_chunk(array_values($codes), $chunk) as $part) {
        $ph = implode(',', array_fill(0, count($part), '?'));
        try {
            $st = $conn->prepare(sprintf($sql_tpl, $ph));
            $st->execute($part);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[] = $r;
        } catch (Throwable $e) {}
    }
    return $out;
};

try {
    // --------------------------------------------------------
    // KONKURENTU SALĪDZINĀJUMS — visa nozare
    // --------------------------------------------------------
    $nace4 = preg_replace('/\D/', '', (string)($page_data['nace_code'] ?? ''));
    $ns_path = (function_exists('reg_search_dir') ? reg_search_dir() : (dirname(__DIR__, 2) . '/assets/search')) . '/nace_stats.sqlite';
    if ($nace4 !== '' && $nace4 !== '0000' && is_file($ns_path)) {
        try {
            $np = new PDO('sqlite:' . $ns_path);
            $np->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $q = $np->prepare('SELECT json_data FROM nace_stats WHERE code = ? LIMIT 1');
            $q->execute([$nace4]);
            $jd = $q->fetchColumn();
            if (is_string($jd) && $jd !== '') {
                $data = json_decode($jd, true);
                $comps = is_array($data['companies'] ?? null) ? $data['companies'] : [];
                // Ieraksts: [name, regcode, profit, turnover, employees]; kārtots pēc peļņas dilstoši
                $n = count($comps);
                if ($n > 1) {
                    $self_i_profit = null;
                    foreach ($comps as $i => $c) {
                        if ((string)($c[1] ?? '') === $tst_reg) { $self_i_profit = $i; break; }
                    }
                    $by_turnover = $comps;
                    usort($by_turnover, fn($a, $b) => (float)($b[3] ?? 0) <=> (float)($a[3] ?? 0));
                    $self_i_turn = null;
                    foreach ($by_turnover as $i => $c) {
                        if ((string)($c[1] ?? '') === $tst_reg) { $self_i_turn = $i; break; }
                    }

                    $all_codes = [];
                    foreach ($comps as $c) {
                        $rc = (string)($c[1] ?? '');
                        if ($rc !== '') $all_codes[] = $rc;
                    }

                    // VID jaunākais ceturksnis (darbinieki + VSAOI -> alga/mēn) — visai nozarei
                    $vid_cet = []; // reg => ['key'=>, 'q'=>, 'emp'=>, 'alga'=>]
                    foreach ($tst_chunk_query($conn, "SELECT Registracijas_kods AS rc, Taksacijas_gads_ceturksnis AS q, Taja_skaita_VSAOI_summa AS vs, Videjais_nodarbinato_personu_skaits_cilv AS emp FROM pdb_samaksato_nodoklu_kopsummas_cet WHERE Registracijas_kods IN (%s)", $all_codes) as $r) {
                        $rc = (string)$r['rc'];
                        $k = tst_quarter_key((string)$r['q']);
                        if ($k === null) continue;
                        if (!isset($vid_cet[$rc]) || $k > $vid_cet[$rc]['key']) {
                            $emp = tst_num($r['emp'] ?? null);
                            $vs = tst_num($r['vs'] ?? null);
                            // Alga tikai >=3 darbiniekiem (privātums — skat. page_builder VID bloku).
                            // 'hid' = dati ir, bet slēpti (<3 darb.) — tabulā rāda '***', ne '—'.
                            $alga = ($emp !== null && $emp >= 3 && $vs !== null && $vs > 0)
                                ? (int)round((($vs * 1000) / 0.3409) / $emp / 3) : null;
                            $vid_cet[$rc] = ['key' => $k, 'q' => tst_quarter_short((string)$r['q']),
                                'emp' => $emp !== null ? (int)$emp : null, 'alga' => $alga,
                                'hid' => ($emp !== null && $emp < 3 && $vs !== null && $vs > 0)];
                        }
                    }

                    // VID gada dati (pdb_nm; jaunākais gads) -> gada alga/mēn — UR režīma algas kolonnai
                    $vid_year = []; // reg => ['year'=>, 'alga'=>]
                    foreach ($tst_chunk_query($conn, "SELECT Registracijas_kods AS rc, Taksacijas_gads AS y, Taja_skaita_VSAOI AS vs, Videjais_nodarbinato_personu_skaits_cilv AS emp FROM pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata WHERE Registracijas_kods IN (%s)", $all_codes) as $r) {
                        $rc = (string)$r['rc'];
                        $y = (int)($r['y'] ?? 0);
                        if ($y <= 0) continue;
                        if (!isset($vid_year[$rc]) || $y > $vid_year[$rc]['year']) {
                            $emp = tst_num($r['emp'] ?? null);
                            $vs = tst_num($r['vs'] ?? null);
                            // Alga tikai >=3 darbiniekiem (privātums).
                            $alga = ($emp !== null && $emp >= 3 && $vs !== null && $vs > 0)
                                ? (int)round((($vs * 1000) / 0.3409) / $emp / 12) : null;
                            $vid_year[$rc] = ['year' => $y, 'alga' => $alga,
                                'hid' => ($emp !== null && $emp < 3 && $vs !== null && $vs > 0)];
                        }
                    }

                    // Kompaktas rindas JS tabulai:
                    // [nosaukums, reg, apgroz, peļņa, UR darb, UR alga, UR algas gads, VID darb, VID alga, VID cet,
                    //  UR alga slēpta (<3 darb.), VID alga slēpta] — slēptajām rāda '***', ne '—'.
                    $rows_js = [];
                    foreach ($comps as $c) {
                        $rc = (string)($c[1] ?? '');
                        $vc = $vid_cet[$rc] ?? null;
                        $vy = $vid_year[$rc] ?? null;
                        $rows_js[] = [
                            $tst_short_form((string)($c[0] ?? '')),
                            $rc,
                            (float)($c[3] ?? 0),
                            (float)($c[2] ?? 0),
                            (int)($c[4] ?? 0),
                            $vy['alga'] ?? null,
                            $vy['year'] ?? null,
                            $vc['emp'] ?? null,
                            $vc['alga'] ?? null,
                            $vc['q'] ?? null,
                            !empty($vy['hid']) ? 1 : 0,
                            !empty($vc['hid']) ? 1 : 0,
                        ];
                    }

                    $tst_peers = [
                        'nace' => $nace4,
                        'nace_desc' => (string)($page_data['nace_description'] ?? ''),
                        'total' => $n,
                        'rank_profit' => $self_i_profit !== null ? $self_i_profit + 1 : null,
                        'rank_turnover' => $self_i_turn !== null ? $self_i_turn + 1 : null,
                        'generated' => (string)($data['meta']['generated'] ?? ''),
                        'rows_js' => $rows_js,
                    ];
                }
            }
        } catch (Throwable $e) {}
    }
} catch (Throwable $e) {
    // Panelis nekad negāž lapu
}

// Bez salīdzinājuma datiem panelim nav satura — tad to nerāda vispār.
// Iepriekš tas izvadīja virsrakstu "(visa nozare NACE 0000)" un paziņojumu, ka datu
// nav. Biedrībām un nodibinājumiem NACE koda nav un nekad nebūs (nozares klasifikators
// tiem netiek piešķirts), un salīdzināmie lielumi — apgrozījums, peļņa, marža, vidējā
// alga — tiem nepastāv, tāpēc virsraksts solīja neiespējamu salīdzinājumu.
// Tas pats attiecas uz jebkuru subjektu bez nozares statistikas: tukšs panelis ar
// paskaidrojumu, kāpēc tas ir tukšs, lasītājam neko nedod.
if ($tst_peers === null) {
    return;
}
?>

<div class="tst-facts">
    <div class="tst-header">
        <h2>⚔️ Konkurentu salīdzinājums <span class="tst-hint" style="font-weight:400;">(visa nozare NACE <?= h((string)($page_data['nace_code'] ?? '')) ?>)</span></h2>
        <span class="tst-sub">Automātiski aprēķini no publiskajiem datiem — nav finanšu konsultācija.</span>
    </div>

    <div class="tst-section">
            <div class="tst-ranks">
                <span class="tst-rank-chip">Nozarē: <strong><?= (int)$tst_peers['total'] ?></strong> uzņēmumi</span>
                <?php if ($tst_peers['rank_turnover'] !== null): ?>
                    <span class="tst-rank-chip"><strong><?= (int)$tst_peers['rank_turnover'] ?>. vieta</strong> pēc apgrozījuma</span>
                <?php endif; ?>
                <?php if ($tst_peers['rank_profit'] !== null): ?>
                    <span class="tst-rank-chip"><strong><?= (int)$tst_peers['rank_profit'] ?>. vieta</strong> pēc peļņas</span>
                <?php endif; ?>
                <span class="tst-hint"><?= h($tst_peers['nace_desc']) ?></span>
            </div>
            <div class="tst-peer-toolbar">
                <span class="tst-peer-toggle-label">Darbinieku, produktivitātes un algu kolonnas:</span>
                <div class="tst-peer-toggle" id="tst-peer-toggle">
                    <button type="button" data-mode="vid" class="tst-tgl-on">Jaunākais VID ceturksnis</button>
                    <button type="button" data-mode="ur">Gada pārskati (UR)</button>
                </div>
            </div>
<?php
            // Statiskās rindas bez JS (rāpuļi/MI): TOP 10 pēc apgrozījuma + pats uzņēmums.
            // JS render() ielādes brīdī thead/tbody pārraksta ar pilno kārtojamo tabulu.
            $tst_sorted = $tst_peers['rows_js'];
            usort($tst_sorted, fn($a, $b) => ($b[2] ?? 0.0) <=> ($a[2] ?? 0.0));
            $tst_top = array_slice($tst_sorted, 0, 10);
            $tst_self_static = null;
            $tst_self_in_top = false;
            foreach ($tst_top as $tst_r) { if ((string)$tst_r[1] === $tst_reg) { $tst_self_in_top = true; break; } }
            if (!$tst_self_in_top) {
                foreach ($tst_sorted as $tst_i => $tst_r) {
                    if ((string)$tst_r[1] === $tst_reg) { $tst_self_static = [$tst_i + 1, $tst_r]; break; }
                }
            }
            $tst_fmt0 = fn($v) => $v === null ? '—' : number_format(round((float)$v), 0, ',', ' ');
            $tst_static_row = function (int $pos, array $r) use ($tst_reg, $tst_fmt0): string {
                $is_self = (string)$r[1] === $tst_reg;
                $margin = ($r[2] ?? 0) > 0 ? ($r[3] / $r[2] * 100) : null;
                $emp = $r[7] ?? null;
                $tpe = ($emp !== null && $emp > 0 && ($r[2] ?? 0) > 0) ? $r[2] / $emp : null;
                $alga = $r[8] ?? null;
                $alga_tag = ($alga !== null && !empty($r[9])) ? ' <span class="tst-hint">(' . h((string)$r[9]) . ')</span>' : '';
                $name = $is_self
                    ? '<strong>' . h((string)$r[0]) . '</strong> <span class="tst-hint">(šis uzņēmums)</span>'
                    : '<a href="/' . h((string)$r[1]) . '">' . h((string)$r[0]) . '</a>';
                return '<tr' . ($is_self ? ' class="tst-peer-self"' : '') . '>'
                    . '<td class="tst-td-pos">' . $pos . '</td>'
                    . '<td class="tst-td-name">' . $name . '</td>'
                    . '<td>' . $tst_fmt0($r[2] ?? null) . '</td>'
                    . '<td class="' . (($r[3] ?? 0) >= 0 ? 'tst-pos' : 'tst-neg') . '">' . $tst_fmt0($r[3] ?? null) . '</td>'
                    . '<td>' . ($margin === null ? '—' : number_format($margin, 1, ',', ' ') . ' %') . '</td>'
                    . '<td>' . $tst_fmt0($emp) . '</td>'
                    . '<td>' . $tst_fmt0($tpe) . '</td>'
                    . '<td>' . ($alga === null ? (!empty($r[11]) ? '***' : '—') : $tst_fmt0($alga) . $alga_tag) . '</td>'
                    . '</tr>';
            };
            ?>
            <div class="tst-peer-scroll" id="tst-peer-scroll">
                <table class="tst-table tst-peer-table" id="tst-peer-table">
                    <thead><tr id="tst-peer-head">
                        <th>Pozīcija</th><th>Uzņēmums</th><th>Apgrozījums, €</th><th>Peļņa, €</th>
                        <th>Marža</th><th>Darb. <span class="tst-th-mode">VID</span></th>
                        <th>Apgroz./darb., € <span class="tst-th-mode">VID</span></th>
                        <th>~ Bruto alga/mēn. <span class="tst-th-mode">VID</span></th>
                    </tr></thead>
                    <tbody id="tst-peer-body">
<?php foreach ($tst_top as $tst_i => $tst_r): ?>
                        <?= $tst_static_row($tst_i + 1, $tst_r) . "\n" ?>
<?php endforeach; ?>
<?php if ($tst_self_static !== null): ?>
                        <?= $tst_static_row($tst_self_static[0], $tst_self_static[1]) . "\n" ?>
<?php endif; ?>
                    </tbody>
                </table>
            </div>
            <noscript><p class="tst-nodata">Rādītas nozares TOP 10 rindas pēc apgrozījuma un meklētais uzņēmums; pilnai tabulai ar kārtošanu nepieciešams JavaScript.</p></noscript>
            <div class="tst-note">
                Apgrozījums, peļņa un UR darbinieki — no katra uzņēmuma jaunākā gada pārskata (nozares statistika ģenerēta <?= h($tst_peers['generated']) ?>).
                VID kolonnas — no jaunākā pieejamā VID ceturkšņa katram uzņēmumam; alga aprēķināta no VSAOI. Apgroz./darb. abos režīmos lieto gada pārskata apgrozījumu.
                Klikšķis uz kolonnas virsraksta maina kārtošanu; "Pozīcija" vienmēr numurē no 1.
                *** — uzņēmumiem ar mazāk nekā 3 darbiniekiem algas aprēķins tiek slēpts privātuma aizsardzībai.
            </div>
            <script>
            (function () {
                var DATA = <?= json_encode($tst_peers['rows_js'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
                var SELF = <?= json_encode($tst_reg) ?>;
                // Rindas indeksi: 0 nosaukums, 1 reg, 2 apgroz, 3 peļņa, 4 UR darb,
                // 5 UR alga, 6 UR algas gads, 7 VID darb, 8 VID alga, 9 VID cet,
                // 10 UR alga slēpta (<3 darb. — rāda '***'), 11 VID alga slēpta.
                var state = { mode: 'vid', key: 'emp', dir: -1 };

                function val(r, key) {
                    switch (key) {
                        case 'name': return r[0];
                        case 'turn': return r[2];
                        case 'prof': return r[3];
                        case 'margin': return r[2] > 0 ? r[3] / r[2] * 100 : null;
                        case 'emp': return state.mode === 'ur' ? r[4] : r[7];
                        case 'tpe': { var e = state.mode === 'ur' ? r[4] : r[7]; return (e && r[2] > 0) ? r[2] / e : null; }
                        case 'alga': return state.mode === 'ur' ? r[5] : r[8];
                    }
                    return null;
                }
                function fmt(v, dec) {
                    if (v === null || v === undefined || isNaN(v)) return '—';
                    var s = (dec ? v.toFixed(1).replace('.', ',') : Math.round(v).toString());
                    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                }
                function esc(s) {
                    return String(s).replace(/[&<>"']/g, function (c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    });
                }

                var COLS = [
                    { key: 'pos', label: 'Pozīcija', sort: false },
                    { key: 'name', label: 'Uzņēmums', sort: true, str: true },
                    { key: 'turn', label: 'Apgrozījums, €', sort: true },
                    { key: 'prof', label: 'Peļņa, €', sort: true },
                    { key: 'margin', label: 'Marža', sort: true },
                    { key: 'emp', label: 'Darb.', sort: true, tgl: true },
                    { key: 'tpe', label: 'Apgroz./darb., €', sort: true, tgl: true },
                    { key: 'alga', label: '~ Bruto alga/mēn.', sort: true, tgl: true }
                ];

                var headEl = document.getElementById('tst-peer-head');
                var bodyEl = document.getElementById('tst-peer-body');
                var scrollEl = document.getElementById('tst-peer-scroll');
                var tglEl = document.getElementById('tst-peer-toggle');
                if (!headEl || !bodyEl || !scrollEl) return;

                function render() {
                    var rows = DATA.slice();
                    rows.sort(function (a, b) {
                        var va = val(a, state.key), vb = val(b, state.key);
                        if (state.key === 'name') return state.dir * String(va).localeCompare(String(vb), 'lv');
                        if (va === null || isNaN(va)) return 1;   // tukšās vērtības vienmēr apakšā
                        if (vb === null || isNaN(vb)) return -1;
                        return state.dir * (va - vb);
                    });

                    var hh = '';
                    COLS.forEach(function (c) {
                        var tag = c.tgl ? ' <span class="tst-th-mode">' + (state.mode === 'ur' ? 'UR' : 'VID') + '</span>' : '';
                        var arrow = (c.key === state.key) ? (state.dir === 1 ? ' ▲' : ' ▼') : '';
                        hh += '<th data-key="' + c.key + '" class="' + (c.sort ? 'tst-th-sort' : '') + (c.tgl ? ' tst-th-tgl' : '') + '">' + c.label + tag + arrow + '</th>';
                    });
                    headEl.innerHTML = hh;

                    var out = [];
                    var selfIdx = -1;
                    rows.forEach(function (r, i) {
                        var isSelf = r[1] === SELF;
                        if (isSelf) selfIdx = i;
                        var e = state.mode === 'ur' ? r[4] : r[7];
                        var alga = state.mode === 'ur' ? r[5] : r[8];
                        var algaHid = state.mode === 'ur' ? r[10] : r[11];
                        var algaTag = alga !== null ? (state.mode === 'ur' ? (r[6] ? ' <span class="tst-hint">(' + r[6] + ')</span>' : '') : (r[9] ? ' <span class="tst-hint">(' + esc(r[9]) + ')</span>' : '')) : '';
                        var m = r[2] > 0 ? r[3] / r[2] * 100 : null;
                        out.push('<tr' + (isSelf ? ' class="tst-peer-self"' : '') + '>'
                            + '<td class="tst-td-pos">' + (i + 1) + '</td>'
                            + '<td class="tst-td-name" title="' + esc(r[0]) + '">' + (isSelf ? '<strong>' + esc(r[0]) + '</strong> <span class="tst-hint">(šis uzņēmums)</span>' : '<a href="/' + esc(r[1]) + '">' + esc(r[0]) + '</a>') + '</td>'
                            + '<td>' + fmt(r[2]) + '</td>'
                            + '<td class="' + (r[3] >= 0 ? 'tst-pos' : 'tst-neg') + '">' + fmt(r[3]) + '</td>'
                            + '<td>' + (m === null ? '—' : fmt(m, true) + ' %') + '</td>'
                            + '<td>' + (e === null ? '—' : fmt(e)) + '</td>'
                            + '<td>' + ((e && r[2] > 0) ? fmt(r[2] / e) : '—') + '</td>'
                            + '<td>' + (alga === null ? (algaHid ? '***' : '—') : fmt(alga) + algaTag) + '</td>'
                            + '</tr>');
                    });
                    bodyEl.innerHTML = out.join('');

                    // Augstums = galvene + tieši 5 rindas; pētāmais uzņēmums nocentrēts
                    var thead = headEl.parentNode;
                    var firstRow = bodyEl.rows[0];
                    if (firstRow) {
                        var rowH = firstRow.offsetHeight || 31;
                        var headH = thead.offsetHeight || 34;
                        scrollEl.style.maxHeight = (headH + rowH * 5 + 2) + 'px';
                        if (selfIdx >= 0) {
                            var selfRow = bodyEl.rows[selfIdx];
                            scrollEl.scrollTop = Math.max(0, selfRow.offsetTop - headH - rowH * 2);
                        } else {
                            scrollEl.scrollTop = 0;
                        }
                    }
                }

                headEl.addEventListener('click', function (ev) {
                    var th = ev.target.closest('th');
                    if (!th || !th.classList.contains('tst-th-sort')) return;
                    var key = th.getAttribute('data-key');
                    if (state.key === key) { state.dir = -state.dir; }
                    else { state.key = key; state.dir = (key === 'name') ? 1 : -1; }
                    render();
                });
                if (tglEl) {
                    tglEl.addEventListener('click', function (ev) {
                        var btn = ev.target.closest('button');
                        if (!btn) return;
                        state.mode = btn.getAttribute('data-mode') === 'vid' ? 'vid' : 'ur';
                        Array.prototype.forEach.call(tglEl.querySelectorAll('button'), function (b) {
                            b.classList.toggle('tst-tgl-on', b === btn);
                        });
                        render();
                    });
                }
                // Sākotnējā renderēšana tikai tad, kad CSS (<style> bloks zemāk) jau
                // piemērots — citādi overflow vēl nav "auto" un scrollTop noklampējas uz 0.
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', render);
                } else {
                    render();
                }
            })();
            </script>
    </div>
</div>

<style>
.tst-facts { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; width: 100%; order: 6; }
.tst-header { border-bottom: 2px solid #f0f4f8; padding-bottom: 10px; margin-bottom: 16px; }
.tst-header h2 { margin: 0; font-size: 18px; color: #1a2a3a; display: flex; align-items: center; gap: 10px; }
.tst-sub { font-size: 12px; color: #7f8c8d; }
.tst-section { margin-top: 22px; min-width: 0; }
.tst-hint { font-size: 11.5px; color: #8a99a8; font-weight: 400; }
.tst-nodata { color: #8a99a8; font-style: italic; font-size: 13px; }
.tst-note { font-size: 11.5px; color: #8a99a8; margin-top: 10px; }
.tst-pos { color: #1e8e3e; }
.tst-neg { color: #c0392b; }

/* Konkurenti */
.tst-ranks { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 6px; }
.tst-rank-chip { background: #eef4ff; border: 1px solid #c5d8f5; color: #2c5282; border-radius: 6px; padding: 5px 10px; font-size: 12.5px; }
.tst-peer-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: flex-end; margin: 8px 0 6px 0; }
.tst-peer-toggle-label { font-size: 12px; color: #7f8c8d; }
.tst-peer-toggle { display: inline-flex; border: 1px solid #c5d8f5; border-radius: 6px; overflow: hidden; }
.tst-peer-toggle button { border: 0; background: #fff; color: #2c5282; font-size: 12.5px; padding: 6px 12px; cursor: pointer; }
.tst-peer-toggle button + button { border-left: 1px solid #c5d8f5; }
.tst-peer-toggle button.tst-tgl-on { background: #1a6aad; color: #fff; font-weight: 600; }
.tst-table { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-top: 0; }
.tst-table th { text-align: left; background: #f8fafc; color: #7f8c8d; font-weight: 600; padding: 7px 10px; border-bottom: 1px solid #e8ecf0; white-space: nowrap; }
.tst-table td { padding: 6px 10px; border-bottom: 1px solid #f0f4f8; white-space: nowrap; }
.tst-peer-scroll { overflow: auto; border: 1px solid #e8ecf0; border-radius: 6px; }
.tst-peer-table thead th { position: sticky; top: 0; z-index: 1; }
.tst-th-sort { cursor: pointer; user-select: none; }
.tst-th-sort:hover { color: #1a6aad; }
.tst-th-mode { font-size: 10px; font-weight: 700; color: #fff; background: #8fa8c0; border-radius: 3px; padding: 0 4px; vertical-align: 1px; }
.tst-td-pos { color: #8a99a8; text-align: right; width: 1%; }
.tst-td-name { min-width: 220px; max-width: 340px; overflow: hidden; text-overflow: ellipsis; }
.tst-td-name a { color: #1a6aad; text-decoration: none; }
.tst-peer-self td { background: #fff9db; font-weight: 700; }
.tst-peer-self td a { font-weight: 700; }
</style>
