<?php
/**
 * risk_semaphore.php — Riska semafora aprēķins no publiskajiem datiem.
 * Lieto: view/partials/test_panel.php (renderēšanai) UN templates/master_top.php
 * (MI promptu preambulai) — abiem VIENĀDS aprēķins, lai MI un panelis nerunā pretrunās.
 * Nekad nemet izņēmumus uz āru — kļūdas gadījumā atdod signālus, kas jau sanāca.
 */
declare(strict_types=1);

// ============================================================
// Kopīgie palīgi (lieto arī test_panel pārējās sadaļas)
// ============================================================
if (!function_exists('tst_num')) {
    /** VID skaitļu teksts ("14.61", "1,2") -> float; tukšs/nederīgs -> null. */
    function tst_num($v): ?float {
        if ($v === null || $v === '') return null;
        if (is_int($v) || is_float($v)) return (float)$v;
        // NBSP (U+00A0) un šaurā NBSP (U+202F): VID TEXT kolonnas tūkstošus atdala ar
        // NBSP ("3 209"), un bez tā tieši LIELĀKIE darba devēji parsējās uz null →
        // konkurentu tabulā '—' (115 rindas cet. tabulā, 1 562 gada VSAOI laukā).
        // parse_vid_number page_builder.php to jau sen dara — šeit bija aizmirsts.
        $s = str_replace([' ', "\u{a0}", "\u{202f}", ','], ['', '', '', '.'], trim((string)$v));
        return is_numeric($s) ? (float)$s : null;
    }

    /** "2026. gada 1. ceturksnis" -> 20261 (kārtošanai); null ja neatpazīst. */
    function tst_quarter_key(?string $q): ?int {
        if ($q !== null && preg_match('/(\d{4})\.\s*gada\s*(\d)\./u', $q, $m)) {
            return (int)$m[1] * 10 + (int)$m[2];
        }
        return null;
    }

    /** "2026. gada 1. ceturksnis" -> "2026 Q1". */
    function tst_quarter_short(?string $q): string {
        $k = tst_quarter_key($q);
        return $k === null ? (string)$q : intdiv($k, 10) . ' Q' . ($k % 10);
    }

    /** Skaitlis EUR formātā bez decimāļiem (1 234 567). */
    function tst_eur($v): string {
        if ($v === null || !is_numeric($v)) return '—';
        return number_format((float)$v, 0, ',', ' ');
    }

    /** Procentu izmaiņa old->new; null ja nevar aprēķināt. */
    function tst_pct_change(?float $old, ?float $new): ?float {
        if ($old === null || $new === null || abs($old) < 1e-9) return null;
        return (($new - $old) / abs($old)) * 100.0;
    }

    /** Izmaiņas teksts ar zīmi: "+12,3 %" / "−45,0 %". */
    function tst_pct_txt(?float $p): string {
        if ($p === null) return '—';
        $s = number_format(abs($p), 1, ',', ' ') . ' %';
        return ($p >= 0 ? '+' : '−') . $s;
    }

    /** Pašu kapitāls no allProcessedData konkrētam gadam (ar mērogošanas faktoru). */
    function tst_equity_for_year(array $apd, int $year): ?float {
        $d = $apd[$year]['UGP'] ?? null;
        if (!is_array($d)) return null;
        $bal = is_array($d['balance_data'] ?? null) ? $d['balance_data'] : [];
        $fs  = is_array($d['fs_data'] ?? null) ? $d['fs_data'] : [];
        $eq = function_exists('get_raw_value') ? get_raw_value($bal, 'equity') : ($bal['equity'] ?? null);
        if ($eq === null || !is_numeric($eq)) return null;
        $rnd = strtoupper((string)($fs['rounded_to_nearest'] ?? 'ONES'));
        $factor = str_contains($rnd, 'THOUS') ? 1000 : (str_contains($rnd, 'MILL') ? 1000000 : 1);
        return (float)$eq * $factor;
    }

    /**
     * Pārskata valūta gadam ('EUR'/'LVL'). Pirms-2014 pārskati ir latos, un
     * signāla tekstā tos rādīt ar "€" ir nepatiesi — turklāt šis teksts nonāk MI
     * promptā, tāpēc kļūda pārceļo arī uz atbildēm (audits 2026-08-19).
     */
    function tst_currency_for_year(array $apd, int $year): string {
        $d = $apd[$year]['UGP'] ?? null;
        if (!is_array($d)) return 'EUR';
        $fs = is_array($d['fs_data'] ?? null) ? $d['fs_data'] : [];
        $cur = strtoupper(trim((string)($fs['currency'] ?? '')));
        return $cur !== '' ? $cur : 'EUR';
    }

    /** Ienākumu pārskata lauks no allProcessedData gadam (ar faktoru). */
    function tst_income_for_year(array $apd, int $year, string $field): ?float {
        $d = $apd[$year]['UGP'] ?? null;
        if (!is_array($d)) return null;
        $inc = is_array($d['income_data'] ?? null) ? $d['income_data'] : [];
        $fs  = is_array($d['fs_data'] ?? null) ? $d['fs_data'] : [];
        $v = function_exists('get_raw_value') ? get_raw_value($inc, $field) : ($inc[$field] ?? null);
        if ($v === null || !is_numeric($v)) return null;
        $rnd = strtoupper((string)($fs['rounded_to_nearest'] ?? 'ONES'));
        $factor = str_contains($rnd, 'THOUS') ? 1000 : (str_contains($rnd, 'MILL') ? 1000000 : 1);
        return (float)$v * $factor;
    }

    /** Amata koda tulkojums (fallback = kods). */
    function tst_position_lv(?string $p): string {
        static $map = [
            'CHAIR_OF_BOARD' => 'Valdes priekšsēdētājs(-a)',
            'BOARD_MEMBER' => 'Valdes loceklis(-e)',
            'CHAIR_OF_SUPERVISORY_BOARD' => 'Padomes priekšsēdētājs(-a)',
            'SUPERVISORY_BOARD_MEMBER' => 'Padomes loceklis(-e)',
            'LIQUIDATOR' => 'Likvidators(-e)',
            'PROCTOR' => 'Prokūrists(-e)',
            'ADMINISTRATOR' => 'Administrators(-e)',
            'OWNER' => 'Īpašnieks(-ce)',
            'MEMBER_OF_PARTNERSHIP' => 'Biedrs(-e)',
        ];
        $p = (string)$p;
        return $map[$p] ?? $p;
    }

    /** Semafora signāls. status: ok|warn|risk|na */
    function tst_signal(string $label, string $status, string $text): array {
        return ['label' => $label, 'status' => $status, 'text' => $text];
    }
}

/*
 * RISKA LĪMEŅU PRINCIPS (2026-11-22, riska analīzes 2.2 punkts; Girta lēmums):
 *  - 'risk' («Konstatēti nopietni riska signāli») tikai reģistru faktiem: aktīva maksātnespēja, likvidācija
 *    (šeit «Juridiskie riski»; joslā — tiesiskā karoga kaskāde ar sankcijām, PTAC, VID apturēšanu) un VID
 *    reitings C (VID pašu vērtējums; reitings N joslā neitrāls, kā līdz šim).
 *  - Dinamika (nodokļi, darbinieki, algu/apgrozījuma šķēre) — ne augstāk par 'warn' un tikai virs absolūta
 *    sliekšņa: agrāk 2 → 1 darbinieks vai nodokļi 800 → 300 € deva «nopietni» (~18 tūkst. uzņēmumu).
 *  - Gada pārskats — ne augstāk par 'warn' un tikai formām, kuru pārskati UR atvērtajos datos tiešām ir
 *    (mērīts 2026-11-22: SIA 97 %, AS 96 %, biedrības 93 %, KB/nodibinājumi 87 %, PS 74 %, arodbiedrības
 *    61–100 %, KS 50 %; IK 0,4 %, zemnieku saimn. 13 %, draudzes 1 %, filiāles 0 %). Pirmā pārskata termiņa
 *    sargs (agrāk tikai joslā) tagad ir šeit, tāpēc to saņem arī MI.
 */
const TST_PARSKATU_FORMAS = ['SIA', 'AS', 'KS', 'PS', 'KB', 'SE', 'EIG', 'BDR', 'NOD', 'ARB', 'ARV'];
const TST_NODOKLI_MIN_TUKST = 10.0;     // nodokļu dinamiku vērtē no 10 000 € bāzes gadā
const TST_DARBINIEKI_MIN = 5;           // darbinieku dinamiku vērtē no 5 darbiniekiem bāzes gadā
const TST_DARBASPEKS_MIN_EUR = 50000.0; // šķēri vērtē no 50 000 € darbaspēka izmaksām bāzes gadā

if (!function_exists('tst_pirma_parskata_termins')) {
    /** Ārējais pirmā gada pārskata termiņš (Y-m-d) no reģistrācijas datuma: 1. pusgads → 31.07. nākamajā gadā,
     *  2. pusgads (pirmais finanšu gads līdz 18 mēn.) → 31.07. aiznākamajā gadā. '' — datums nav zināms. */
    function tst_pirma_parskata_termins(string $registered): string {
        $g = (int)substr($registered, 0, 4); $m = (int)substr($registered, 5, 2);
        return ($g > 0 && $m > 0) ? sprintf('%04d-07-31', $g + ($m >= 7 ? 2 : 1)) : '';
    }
}

if (!function_exists('reg_risk_semaphore')) {
    /**
     * Aprēķina riska semaforu no $page_data (build_page_data izvades).
     * @return array{signals: array<int, array{label:string,status:string,text:string}>, overall:string, overall_txt:string}
     */
    function reg_risk_semaphore(array $page_data): array {
        $reg = (string)($page_data['search_reg_nr'] ?? '');
        $ugp = $page_data['summary_table_data_for_js']['UGP'] ?? [];
        $vid = $page_data['vid_panel_data'] ?? [];
        $res = $page_data['results'] ?? [];
        $apd = $page_data['allProcessedData'] ?? [];
        // Izbeigta darbība (likvidēts/reorganizēts/pārtraukts): daļa signālu ir
        // tagadnes apgalvojumi (pārskatu kavēšana, Komerclikuma prasība), kas
        // šādam subjektam ir bezpriekšmeta (audits 2026-08-26).
        $izbeigts = !empty($page_data['is_liquidated']);
        $signals = [];

        try {
            // 1a. VID nodokļu maksātāja reitings
            $rating_row = $vid['rating'] ?? null;
            if (is_array($rating_row) && !empty($rating_row['Reitings'])) {
                $rt = strtoupper(trim((string)$rating_row['Reitings']));
                $expl = trim((string)($rating_row['Skaidrojums'] ?? ''));
                // N (neaktīvs nodokļu maksātājs) — statuss, ne pārkāpums: 'na', kā joslā (audits 2026-11-22:
                // ~36 tūkst. uzņēmumu MI saņēma «nopietni» tikai reitinga N dēļ).
                $st = ['A' => 'ok', 'B' => 'warn', 'C' => 'risk', 'N' => 'na', 'J' => 'na'][$rt] ?? 'na';
                $signals[] = tst_signal('VID nodokļu maksātāja reitings', $st,
                    'Reitings: ' . $rt . ($expl !== '' ? ' — ' . $expl : '')
                    . ($rt === 'N' ? ' (statuss — nav deklarētas aktivitātes; tas nav pārkāpums).' : ''));
            } else {
                $signals[] = tst_signal('VID nodokļu maksātāja reitings', 'na', 'Nav datu.');
            }

            // 1b. Nodokļu maksājumu dinamika (gads pret gadu)
            $tax_rows = $res['pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata'] ?? [];
            usort($tax_rows, fn($a, $b) => (int)($b['Taksacijas_gads'] ?? 0) <=> (int)($a['Taksacijas_gads'] ?? 0));
            $tax_pairs = [];
            foreach ($tax_rows as $r) {
                $y = (int)($r['Taksacijas_gads'] ?? 0);
                $t = tst_num($r['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? null);
                if ($y > 0 && $t !== null) $tax_pairs[] = ['year' => $y, 'taxes' => $t];
            }
            if (count($tax_pairs) >= 2) {
                $chg = tst_pct_change($tax_pairs[1]['taxes'], $tax_pairs[0]['taxes']);
                $txt = $tax_pairs[1]['year'] . '. g. ' . number_format($tax_pairs[1]['taxes'], 2, ',', ' ') . ' tūkst. € → '
                    . $tax_pairs[0]['year'] . '. g. ' . number_format($tax_pairs[0]['taxes'], 2, ',', ' ') . ' tūkst. € ('
                    . tst_pct_txt($chg) . ').';
                if ($tax_pairs[1]['taxes'] < TST_NODOKLI_MIN_TUKST) {
                    // Maza bāze: procentu izmaiņa (800 → 300 € = −62 %) nav riska signāls.
                    $st = 'na';
                    $txt .= ' Bāze zem 10 000 € — izmaiņu nevērtējam.';
                } else {
                    $st = ($chg !== null && $chg < -50) ? 'warn' : 'ok';
                }
                $signals[] = tst_signal('Nodokļu maksājumu dinamika', $st, $txt);
            } else {
                $signals[] = tst_signal('Nodokļu maksājumu dinamika', 'na', 'Par maz gadu VID datos salīdzinājumam.');
            }

            // 1c. Darbinieku skaita dinamika (VID gada dati; rezerve — gada pārskati)
            $emp_pairs = [];
            foreach ($tax_rows as $r) {
                $y = (int)($r['Taksacijas_gads'] ?? 0);
                $e = tst_num($r['Videjais_nodarbinato_personu_skaits_cilv'] ?? null);
                if ($y > 0 && $e !== null) $emp_pairs[] = ['year' => $y, 'emp' => $e];
            }
            if (count($emp_pairs) < 2) {
                // Rezerve no gada pārskatiem. DEDUBLICĒ PA GADU (VID prioritāte):
                // agrāk viens un tas pats gads varēja ienākt divreiz — reizi no VID,
                // reizi no gada pārskata — un signāls salīdzināja DIVUS AVOTUS par
                // TO PAŠU gadu, uzģenerējot neesošu darbinieku kritumu (audits
                // 2026-08-19). Salīdzinām tikai divus ATŠĶIRĪGUS gadus.
                $by_year = [];
                foreach ($emp_pairs as $p) $by_year[(int)$p['year']] = $p['emp'];
                foreach ($ugp as $r) {
                    $y = (int)($r['year'] ?? 0);
                    $e = $r['employees'] ?? null;
                    if ($y > 0 && is_numeric($e) && !array_key_exists($y, $by_year)) $by_year[$y] = (float)$e;
                }
                krsort($by_year);
                $emp_pairs = [];
                foreach ($by_year as $y => $e) $emp_pairs[] = ['year' => $y, 'emp' => $e];
            }
            if (count($emp_pairs) >= 2 && ($emp_pairs[0]['emp'] > 0 || $emp_pairs[1]['emp'] > 0)) {
                $chg = tst_pct_change($emp_pairs[1]['emp'], $emp_pairs[0]['emp']);
                $txt = $emp_pairs[1]['year'] . '. g. ' . round($emp_pairs[1]['emp']) . ' → '
                    . $emp_pairs[0]['year'] . '. g. ' . round($emp_pairs[0]['emp']) . ' darbinieki ('
                    . tst_pct_txt($chg) . ').';
                if ($emp_pairs[1]['emp'] < TST_DARBINIEKI_MIN) {
                    // 2 → 1 darbinieks = −50 %, bet tas nav riska signāls (agrāk — «nopietni»).
                    $st = 'na';
                    $txt .= ' Mazs darbinieku skaits — izmaiņu nevērtējam.';
                } else {
                    $st = ($emp_pairs[0]['emp'] <= 0 || ($chg !== null && $chg <= -30)) ? 'warn' : 'ok';
                }
                $signals[] = tst_signal('Darbinieku skaits', $st, $txt);
            } else {
                $signals[] = tst_signal('Darbinieku skaits', 'na', 'Nav pietiekamu datu.');
            }

            // 1d. Pašu kapitāls
            $eq_years = [];
            foreach ($ugp as $r) {
                $y = (int)($r['year'] ?? 0);
                if ($y > 0) {
                    $eq = tst_equity_for_year($apd, $y);
                    if ($eq !== null) $eq_years[] = ['year' => $y, 'eq' => $eq];
                }
            }
            if (!empty($eq_years)) {
                $latest_eq = $eq_years[0];
                $eq_cur = tst_currency_for_year($apd, (int)$latest_eq['year']);
                $eq_zim = $eq_cur === 'EUR' ? '€' : $eq_cur;
                $share_cap = null;
                foreach (($res['equity_capitals'] ?? []) as $ecr) {
                    $a = tst_num($ecr['amount'] ?? null);
                    if ($a !== null) $share_cap = max((float)($share_cap ?? 0), $a);
                }
                if ($latest_eq['eq'] < 0) {
                    // 2026-11-19: fakts ar mērogu, bez "apēsts"/Komerclikuma, un 'warn', ne 'risk' —
                    // viens negatīvs kapitāls (piem. −1 005 € jeb 0,07 % no aktīviem) vairs nedod
                    // kopvērtējumu "Konstatēti nopietni riska signāli". Šis teksts iet arī MI ievadē.
                    $eq_ak = null;
                    $eq_d = $apd[(int)$latest_eq['year']]['UGP'] ?? null;
                    if (is_array($eq_d) && is_numeric($eq_d['balance_data']['total_assets'] ?? null)) {
                        $eq_rnd = strtoupper((string)($eq_d['fs_data']['rounded_to_nearest'] ?? 'ONES'));
                        $eq_ak = (float)$eq_d['balance_data']['total_assets'] * (str_contains($eq_rnd, 'THOUS') ? 1000 : (str_contains($eq_rnd, 'MILL') ? 1000000 : 1));
                    }
                    $eq_merogs = '';
                    if ($eq_ak !== null && $eq_ak > 0) {
                        $eq_p = abs($latest_eq['eq']) / $eq_ak * 100;
                        $eq_ps = number_format($eq_p, $eq_p < 1 ? 2 : ($eq_p < 10 ? 1 : 0), ',', ' ');
                        if (str_contains($eq_ps, ',')) $eq_ps = rtrim(rtrim($eq_ps, '0'), ','); // "10" paliek "10"
                        $eq_merogs = '; ' . $eq_ps . ' % no aktīviem';
                    }
                    $eq_aste = $izbeigts ? ') — vēsturisks rādītājs; uzņēmuma darbība ir izbeigta.' : ').';
                    $eq_sig = tst_signal('Pašu kapitāls', $izbeigts ? 'na' : 'warn',
                        $latest_eq['year'] . '. g. pašu kapitāls ir negatīvs (' . tst_eur($latest_eq['eq']) . ' ' . $eq_zim . $eq_merogs . $eq_aste);
                    $eq_sig['isais'] = 'negatīvs'; // joslas čipam: 'warn' citur nozīmē "brīdinājums"
                    $signals[] = $eq_sig;
                } else {
                    $st = 'ok';
                    $txt = $latest_eq['year'] . '. g. pašu kapitāls: ' . tst_eur($latest_eq['eq']) . ' ' . $eq_zim . '.';
                    if (count($eq_years) >= 2) {
                        $chg = tst_pct_change($eq_years[1]['eq'], $eq_years[0]['eq']);
                        if ($chg !== null && $chg <= -30) { $st = 'warn'; $txt .= ' Gada laikā sarucis par ' . tst_pct_txt($chg) . '.'; }
                    }
                    // Pamatkapitāls equity_capitals ir EUR; latu pārskatu ar to
                    // salīdzināt nedrīkst (2014. gada kurss 0,702804 — bez
                    // pārrēķina "zem puses" signāls būtu melīgs), tāpēc šo zaru
                    // laižam tikai pie sakritīgas valūtas.
                    if ($eq_cur === 'EUR' && $share_cap !== null && $share_cap > 0 && $latest_eq['eq'] < 0.5 * $share_cap) {
                        $st = 'warn';
                        $txt .= ' Zem puses no pamatkapitāla (' . tst_eur($share_cap) . ' €).';
                    }
                    $signals[] = tst_signal('Pašu kapitāls', $st, $txt);
                }
            } else {
                $signals[] = tst_signal('Pašu kapitāls', 'na', 'Bilances datu nav.');
            }

            // 1e. Gada pārskata savlaicīgums (report_years.sqlite). Ne augstāk par 'warn' (sk. principu augšā).
            $main_row = is_array($page_data['dati_php_rowData'] ?? null) ? $page_data['dati_php_rowData'] : [];
            $forma = strtoupper(trim((string)($main_row['type'] ?? '')));
            $forma_txt = trim((string)($main_row['type_text'] ?? '')) ?: $forma;
            $ar_parskatiem = $forma === '' || in_array($forma, TST_PARSKATU_FORMAS, true); // nezināma forma — kā līdz šim
            // Biedrībām, nodibinājumiem, arodbiedrībām gada pārskata termiņš ir 31.03. (nvo/nvo_panels.php), citiem 30.04./31.07.
            $nvo = in_array($forma, ['BDR', 'NOD', 'ARB', 'ARV'], true);
            $pirmais = tst_pirma_parskata_termins(trim((string)($main_row['registered'] ?? '')));
            $gp_ne = function (string $txt) use ($ar_parskatiem, $forma_txt): array {
                // Formām bez pārskatiem UR datos kavējumu nevērtējam — tikai fakts.
                return $ar_parskatiem ? tst_signal('Gada pārskats', 'warn', $txt)
                    : tst_signal('Gada pārskats', 'na', $txt . ' Formai «' . $forma_txt . '» gada pārskati UR atvērtajos datos'
                        . ' parasti nav pieejami vai to iesniegšana atkarīga no apgrozījuma — kavējumu nevērtējam.');
            };
            if ($izbeigts) {
                $signals[] = tst_signal('Gada pārskats', 'na', 'Darbība izbeigta — jaunu pārskatu iesniegšanas pienākuma nav.');
            } else {
            $ry_path = (function_exists('reg_data_root') ? reg_data_root() : dirname(__DIR__, 2)) . '/build_state/report_years.sqlite';
            $ry_done = false;
            if (is_file($ry_path)) {
                try {
                    $rp = new PDO('sqlite:' . $ry_path);
                    $rp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $q = $rp->prepare('SELECT report_year FROM report_years WHERE regcode = ?');
                    $q->execute([$reg]);
                    $row = $q->fetch(PDO::FETCH_ASSOC);
                    if ($row !== false) {
                        $ry = (int)($row['report_year'] ?? 0);
                        $expected = (int)date('Y') - 1;
                        $month = (int)date('n');
                        if ($ry <= 0) {
                            if ($pirmais !== '' && date('Y-m-d') <= $pirmais) {
                                $signals[] = tst_signal('Gada pārskats', 'na', 'Pirmā gada pārskata iesniegšanas termiņš ('
                                    . date('d.m.Y', strtotime($pirmais)) . ') vēl nav pienācis.');
                            } elseif (!$ar_parskatiem) {
                                $signals[] = $gp_ne('Reģistrā nav gada pārskatu.');
                            } else {
                                $signals[] = tst_signal('Gada pārskats', 'warn', 'Reģistrā nav neviena iesniegta gada pārskata.');
                            }
                        } elseif ($ry >= $expected) {
                            $signals[] = tst_signal('Gada pārskats', 'ok', 'Jaunākais pārskats: par ' . $ry . '. gadu — iesniegts laikus.');
                        } elseif ($expected - $ry === 1 && $nvo) {
                            $signals[] = $month <= 3
                                ? tst_signal('Gada pārskats', 'ok', 'Jaunākais: par ' . $ry . '. gadu; ' . $expected . '. gada pārskata termiņš (31.03.) vēl nav pagājis.')
                                : $gp_ne('Pārskats par ' . $expected . '. gadu nav iesniegts (termiņš 31.03.).');
                        } elseif ($expected - $ry === 1) {
                            if ($month <= 4) {
                                $signals[] = tst_signal('Gada pārskats', 'ok', 'Jaunākais: par ' . $ry . '. gadu; ' . $expected . '. gada pārskata termiņš (30.04.) vēl nav pagājis.');
                            } elseif ($month <= 7) {
                                $signals[] = $gp_ne('Pārskats par ' . $expected . '. gadu vēl nav iesniegts (pamattermiņš 30.04.; atsevišķiem uzņēmumiem 31.07.).');
                            } else {
                                $signals[] = $gp_ne('Pārskats par ' . $expected . '. gadu nav iesniegts arī pēc 31.07. termiņa.');
                            }
                        } else {
                            $signals[] = $gp_ne('Jaunākais pārskats — par ' . $ry . '. gadu (' . ($expected - $ry) . ' gadi bez jaunāka pārskata).');
                        }
                        $ry_done = true;
                    }
                } catch (Throwable $e) { /* turpinām ar fallback */ }
            }
            if (!$ry_done) {
                $last_fs_year = 0;
                foreach ($ugp as $r) $last_fs_year = max($last_fs_year, (int)($r['year'] ?? 0));
                if ($last_fs_year > 0) {
                    $expected = (int)date('Y') - 1;
                    // Janvārī–aprīlī pērnā gada pārskata termiņš (30.04.) vēl nav
                    // pagājis — pamata zars (report_years) to jau ņem vērā, bet šim
                    // rezerves zaram mēneša sargs bija aizmirsts, un tas brīdināja
                    // par "kavējumu" pirms termiņa (recenzija 2026-08-25).
                    $fb_men = (int)date('n');
                    $st = $last_fs_year >= $expected ? 'ok'
                        : (($expected - $last_fs_year === 1 && $fb_men <= ($nvo ? 3 : 4)) ? 'ok' : 'warn');
                    $fb_txt = 'Jaunākais pieejamais pārskats: par ' . $last_fs_year . '. gadu.';
                    $signals[] = $st === 'ok' ? tst_signal('Gada pārskats', 'ok', $fb_txt) : $gp_ne($fb_txt);
                } else {
                    $signals[] = tst_signal('Gada pārskats', 'na', 'Nav datu.');
                }
            }
            } // beidzas !$izbeigts zars (1e)

            // 1f. Algu/apgrozījuma šķēre
            $scissor_done = false;
            $to_years = [];
            foreach ($ugp as $r) {
                $y = (int)($r['year'] ?? 0);
                if ($y > 0 && is_numeric($r['turnover'] ?? null)) $to_years[$y] = (float)$r['turnover'];
            }
            krsort($to_years);
            $to_keys = array_keys($to_years);
            if (count($to_keys) >= 2) {
                [$y_new, $y_old] = [$to_keys[0], $to_keys[1]];
                $lab_new = tst_income_for_year($apd, $y_new, 'by_nature_labour_expenses');
                $lab_old = tst_income_for_year($apd, $y_old, 'by_nature_labour_expenses');
                if ($lab_new !== null && $lab_old !== null && abs($lab_old) > 1e-9) {
                    $lab_g = tst_pct_change(abs($lab_old), abs($lab_new));
                    $to_g = tst_pct_change($to_years[$y_old], $to_years[$y_new]);
                    if ($lab_g !== null && $to_g !== null) {
                        $gap = $lab_g - $to_g;
                        $txt = $y_old . '→' . $y_new . ': darbaspēka izmaksas ' . tst_pct_txt($lab_g)
                            . ', apgrozījums ' . tst_pct_txt($to_g) . ' (šķēre ' . tst_pct_txt($gap) . ' pp).';
                        // Latu pārskatiem (pirms 2014) bāzi pārrēķina uz eiro, lai slieksnis ir salīdzināms.
                        $lab_old_eur = abs($lab_old) * (tst_currency_for_year($apd, $y_old) === 'LVL' ? 1 / 0.702804 : 1.0);
                        if ($lab_old_eur < TST_DARBASPEKS_MIN_EUR) {
                            $st = 'na';
                            $txt .= ' Darbaspēka izmaksas zem 50 000 € — šķēri nevērtējam.';
                        } else {
                            $st = $gap > 30 ? 'warn' : 'ok';
                        }
                        $signals[] = tst_signal('Algu/apgrozījuma šķēre', $st, $txt);
                        $scissor_done = true;
                    }
                }
            }
            if (!$scissor_done) {
                $signals[] = tst_signal('Algu/apgrozījuma šķēre', 'na', 'Trūkst darbaspēka izmaksu rindas divos secīgos gados.');
            }

            // 1g. Juridiskie riski
            $legal_bits = [];
            $legal_st = 'ok';
            // Maksātnespēja un TAP jāatšķir: TAP (tiesiskās aizsardzības process) ir
            // mēģinājums uzņēmumu SAGLABĀT, vienojoties ar kreditoriem — veiksmīgi
            // pabeigts TAP ir pretējs signāls nekā bankrots. Tas pats nošķīrums, ko
            // rāda view/partials/sadalas/tiesiskais.php, lai MI nerunā tam pretī.
            foreach (($res['insolvency_legal_person_proceeding'] ?? []) as $ir) {
                $ended = trim((string)($ir['proceeding_ended_on'] ?? ''));
                $started = trim((string)($ir['proceeding_started_on'] ?? ''));
                if ($started === '') continue;
                $form = strtoupper(trim((string)($ir['proceeding_form'] ?? '')));
                $is_tap = str_contains($form, 'LEGAL_PROTECTION');
                $name = $is_tap
                    ? (str_starts_with($form, 'OUT_OF_COURT') ? 'ārpustiesas tiesiskās aizsardzības process'
                                                              : 'tiesiskās aizsardzības process (TAP)')
                    : 'maksātnespējas process';
                if ($ended === '' || $ended === '0000-00-00') {
                    // Aktīvs TAP ir brīdinājums, aktīva maksātnespēja — risks.
                    if (!$is_tap) { $legal_st = 'risk'; }
                    elseif ($legal_st === 'ok') { $legal_st = 'warn'; }
                    $legal_bits[] = 'aktīvs ' . $name . ' (no ' . $started . ')';
                } else {
                    if ($legal_st === 'ok') $legal_st = 'warn';
                    $legal_bits[] = 'vēsturisks ' . $name . ' (' . $started . '–' . $ended . ')';
                }
            }
            if (!empty($res['liquidations'])) {
                $legal_st = 'risk';
                $lt = (string)(($res['liquidations'][0]['liquidation_type_text'] ?? '') ?: 'likvidācija');
                $legal_bits[] = mb_strtolower($lt);
            }
            if (!empty($res['suspensions_prohibitions'])) {
                if ($legal_st === 'ok') $legal_st = 'warn';
                $legal_bits[] = 'reģistrēti darbības liegumi/apturēšanas (' . count($res['suspensions_prohibitions']) . ')';
            }
            if (!empty($res['securing_measures'])) {
                if ($legal_st === 'ok') $legal_st = 'warn';
                $legal_bits[] = 'nodrošinājuma līdzekļi (' . count($res['securing_measures']) . ')';
            }
            // Līmenis no TĀS PAŠAS tiesiskās kaskādes, ko rāda josla (riska_kopsavilkums.php): sankcijas, aktīva
            // maksātnespēja, likvidācija, PTAC melnais saraksts, VID apturēšana = 'risk'; TAP, ierobežojumi,
            // nodrošinājumi, PTAC sods = 'warn'; tikai vēsturiski procesi = 'warn' (apzināta diverģence no joslas,
            // sk. riska_josla.php galveni). Audits 2026-11-22: agrāk MI neredzēja sankcijas, PTAC un VID apturēšanu.
            $kask = null;
            try {
                require_once __DIR__ . '/riska_kopsavilkums.php';
                if (function_exists('reg_tiesiskais_kopsavilkums')) $kask = reg_tiesiskais_kopsavilkums($res);
            } catch (Throwable $e) { $kask = null; }
            if (is_array($kask) && !empty($kask['has'])) {
                $head = (string)$kask['head'];
                $vest_proc = (bool)array_filter($legal_bits, fn($b) => str_starts_with((string)$b, 'vēsturisks'));
                if (!empty($kask['sank'])) {
                    // Tukša loma = sarakstā ir PATS uzņēmums (data_fetcher.php); citādi — saistīta persona ar lomu
                    // (vārdi jau izmesti scrub_sanctions). Audits 2026-11-22: 36 uzņēmumiem MI rakstīja «uzņēmums
                    // ir sankciju sarakstos», lai gan sarakstā bija patiesā labuma guvējs vai dalībnieks.
                    $pats = false; $lomas = [];
                    foreach ($kask['sank'] as $sr) {
                        $loma = trim((string)($sr['position_text_lv'] ?? ''));
                        if (trim((string)($sr['position'] ?? '')) === '') $pats = true;
                        elseif ($loma !== '') $lomas[mb_strtolower($loma)] = true;
                    }
                    array_unshift($legal_bits, $pats ? 'uzņēmums pats ir sankciju sarakstā'
                        : 'sankciju sarakstā ir ar uzņēmumu saistīta persona' . ($lomas ? ' (loma: ' . implode(', ', array_slice(array_keys($lomas), 0, 3)) . ')' : ''));
                }
                // Bez atkārtošanās: faktu, kas jau ir virsrakstā, sarakstā neliekam.
                if ((int)($kask['pt_melns_n'] ?? 0) > 0) { if ($head !== 'PTAC melnajā sarakstā') $legal_bits[] = 'PTAC melnajā sarakstā'; }
                elseif ((int)($kask['pt_sods_n'] ?? 0) > 0) { if ($head !== 'PTAC lēmums ar soda naudu') $legal_bits[] = 'PTAC lēmums ar soda naudu'; }
                if ((int)($kask['vid_akt'] ?? 0) > 0) { if ($head !== 'Apturēta saimnieciskā darbība (VID)') $legal_bits[] = 'VID apturējis saimniecisko darbību'; }
                elseif (!empty($kask['vid'])) $legal_bits[] = 'vēsturiska VID saimnieciskās darbības apturēšana (šobrīd nav aktīva)';
                if (($kask['level'] ?? '') === 'risk') {
                    $legal_st = 'risk';
                } elseif (($kask['level'] ?? '') === 'warn') {
                    $legal_st = 'warn';
                } else {
                    // 'past': brīdinājums tikai par vēsturiskiem maksātnespējas/TAP procesiem (apzināta diverģence no
                    // joslas); beigusies VID apturēšana vai PTAC ieraksts bez soda — 'ok' (audits 2026-11-22).
                    $legal_st = $vest_proc ? 'warn' : 'ok';
                }
                $signals[] = tst_signal('Juridiskie riski', $legal_st,
                    $head . ($legal_bits ? ': ' . implode('; ', $legal_bits) : '') . '.');
            } else {
                $signals[] = tst_signal('Juridiskie riski', empty($legal_bits) ? 'ok' : $legal_st,
                    empty($legal_bits) ? 'Nav reģistrētu maksātnespējas procesu, likvidāciju vai liegumu.' : ucfirst(implode('; ', $legal_bits)) . '.');
            }
        } catch (Throwable $e) {
            // atdodam signālus, kas paspēja sanākt
        }

        $overall = 'ok';
        foreach ($signals as $s) {
            if ($s['status'] === 'risk') { $overall = 'risk'; break; }
            if ($s['status'] === 'warn') $overall = 'warn';
        }
        $overall_txt = [
            'ok' => 'Būtiski riska signāli nav konstatēti',
            'warn' => 'Ir brīdinājuma signāli — ieteicama piesardzība',
            'risk' => 'Konstatēti nopietni riska signāli',
        ][$overall];

        return ['signals' => $signals, 'overall' => $overall, 'overall_txt' => $overall_txt];
    }

    /** Semafora kopsavilkums teksta formā MI promptu preambulai. */
    function reg_risk_semaphore_text(array $sem): string {
        // 'na' = nevērtēts: datu nav VAI tie ir zem vērtēšanas sliekšņa (teksts pasaka, kurš no abiem).
        $map = ['ok' => 'zaļš', 'warn' => 'dzeltens', 'risk' => 'sarkans', 'na' => 'nevērtēts'];
        $lines = [];
        $lines[] = 'RISKA SEMAFORS (automātiski aprēķināti signāli no publiskajiem datiem; tas pats aprēķins, ko lietotājs redz lapas riska joslā):';
        $lines[] = '- KOPVĒRTĒJUMS [' . ($map[$sem['overall']] ?? '?') . ']: ' . (string)($sem['overall_txt'] ?? '');
        foreach (($sem['signals'] ?? []) as $s) {
            $lines[] = '- ' . $s['label'] . ' [' . ($map[$s['status']] ?? '?') . ']: ' . $s['text'];
        }
        $lines[] = 'Izmanto šos signālus kā kontekstu un savos secinājumos uz tiem atsaucies; ja tavi aprēķini no JSON datiem kādam signālam ir pretrunā, skaidri norādi šo pretrunu.';
        return implode("\n", $lines);
    }
}
