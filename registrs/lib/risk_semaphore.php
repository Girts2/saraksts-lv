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
        $signals = [];

        try {
            // 1a. VID nodokļu maksātāja reitings
            $rating_row = $vid['rating'] ?? null;
            if (is_array($rating_row) && !empty($rating_row['Reitings'])) {
                $rt = strtoupper(trim((string)$rating_row['Reitings']));
                $expl = trim((string)($rating_row['Skaidrojums'] ?? ''));
                $st = ['A' => 'ok', 'B' => 'warn', 'C' => 'risk', 'N' => 'risk', 'J' => 'na'][$rt] ?? 'na';
                $signals[] = tst_signal('VID nodokļu maksātāja reitings', $st,
                    'Reitings: ' . $rt . ($expl !== '' ? ' — ' . $expl : ''));
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
                $st = 'ok';
                if ($chg !== null && $chg < -50) $st = 'risk';
                elseif ($chg !== null && $chg < -20) $st = 'warn';
                $signals[] = tst_signal('Nodokļu maksājumu dinamika', $st,
                    $tax_pairs[1]['year'] . '. g. ' . number_format($tax_pairs[1]['taxes'], 2, ',', ' ') . ' tūkst. € → '
                    . $tax_pairs[0]['year'] . '. g. ' . number_format($tax_pairs[0]['taxes'], 2, ',', ' ') . ' tūkst. € ('
                    . tst_pct_txt($chg) . ').');
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
                foreach ($ugp as $r) {
                    $e = $r['employees'] ?? null;
                    if (is_numeric($e) && (int)($r['year'] ?? 0) > 0) $emp_pairs[] = ['year' => (int)$r['year'], 'emp' => (float)$e];
                }
                usort($emp_pairs, fn($a, $b) => $b['year'] <=> $a['year']);
            }
            if (count($emp_pairs) >= 2 && ($emp_pairs[0]['emp'] > 0 || $emp_pairs[1]['emp'] > 0)) {
                $chg = tst_pct_change($emp_pairs[1]['emp'], $emp_pairs[0]['emp']);
                $st = 'ok';
                if ($emp_pairs[0]['emp'] <= 0) $st = 'risk'; // visi darbinieki prom
                elseif ($chg !== null && $chg <= -30) $st = 'risk';
                elseif ($chg !== null && $chg <= -10) $st = 'warn';
                $signals[] = tst_signal('Darbinieku skaits', $st,
                    $emp_pairs[1]['year'] . '. g. ' . round($emp_pairs[1]['emp']) . ' → '
                    . $emp_pairs[0]['year'] . '. g. ' . round($emp_pairs[0]['emp']) . ' darbinieki ('
                    . tst_pct_txt($chg) . ').');
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
                $share_cap = null;
                foreach (($res['equity_capitals'] ?? []) as $ecr) {
                    $a = tst_num($ecr['amount'] ?? null);
                    if ($a !== null) $share_cap = max((float)($share_cap ?? 0), $a);
                }
                if ($latest_eq['eq'] < 0) {
                    $signals[] = tst_signal('Pašu kapitāls', 'risk',
                        $latest_eq['year'] . '. g. pašu kapitāls ir negatīvs (' . tst_eur($latest_eq['eq']) . ' €) — īpašnieku ieguldījums ir "apēsts"; Komerclikums šādā situācijā prasa rīcību.');
                } else {
                    $st = 'ok';
                    $txt = $latest_eq['year'] . '. g. pašu kapitāls: ' . tst_eur($latest_eq['eq']) . ' €.';
                    if (count($eq_years) >= 2) {
                        $chg = tst_pct_change($eq_years[1]['eq'], $eq_years[0]['eq']);
                        if ($chg !== null && $chg <= -30) { $st = 'warn'; $txt .= ' Gada laikā sarucis par ' . tst_pct_txt($chg) . '.'; }
                    }
                    if ($share_cap !== null && $share_cap > 0 && $latest_eq['eq'] < 0.5 * $share_cap) {
                        $st = 'warn';
                        $txt .= ' Zem puses no pamatkapitāla (' . tst_eur($share_cap) . ' €).';
                    }
                    $signals[] = tst_signal('Pašu kapitāls', $st, $txt);
                }
            } else {
                $signals[] = tst_signal('Pašu kapitāls', 'na', 'Bilances datu nav.');
            }

            // 1e. Gada pārskata savlaicīgums (report_years.sqlite)
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
                            $signals[] = tst_signal('Gada pārskats', 'risk', 'Reģistrā nav neviena iesniegta gada pārskata.');
                        } elseif ($ry >= $expected) {
                            $signals[] = tst_signal('Gada pārskats', 'ok', 'Jaunākais pārskats: par ' . $ry . '. gadu — iesniegts laikus.');
                        } elseif ($expected - $ry === 1) {
                            if ($month <= 4) {
                                $signals[] = tst_signal('Gada pārskats', 'ok', 'Jaunākais: par ' . $ry . '. gadu; ' . $expected . '. gada pārskata termiņš (30.04.) vēl nav pagājis.');
                            } elseif ($month <= 7) {
                                $signals[] = tst_signal('Gada pārskats', 'warn', 'Pārskats par ' . $expected . '. gadu vēl nav iesniegts (pamattermiņš 30.04.; atsevišķiem uzņēmumiem 31.07.).');
                            } else {
                                $signals[] = tst_signal('Gada pārskats', 'risk', 'Pārskats par ' . $expected . '. gadu nav iesniegts arī pēc 31.07. termiņa.');
                            }
                        } else {
                            $signals[] = tst_signal('Gada pārskats', 'risk', 'Pārskati kavēti ' . ($expected - $ry) . ' gadus (jaunākais — par ' . $ry . '. gadu).');
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
                    $st = $last_fs_year >= $expected ? 'ok' : (($expected - $last_fs_year === 1 && (int)date('n') <= 7) ? 'warn' : 'risk');
                    $signals[] = tst_signal('Gada pārskats', $st, 'Jaunākais pieejamais pārskats: par ' . $last_fs_year . '. gadu.');
                } else {
                    $signals[] = tst_signal('Gada pārskats', 'na', 'Nav datu.');
                }
            }

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
                        $st = $gap > 30 ? 'risk' : ($gap > 15 ? 'warn' : 'ok');
                        $signals[] = tst_signal('Algu/apgrozījuma šķēre', $st,
                            $y_old . '→' . $y_new . ': darbaspēka izmaksas ' . tst_pct_txt($lab_g)
                            . ', apgrozījums ' . tst_pct_txt($to_g) . ' (šķēre ' . tst_pct_txt($gap) . ' pp).');
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
            // rāda view/partials/test_tiesiskais_panel.php, lai MI nerunā tam pretī.
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
            $signals[] = tst_signal('Juridiskie riski', empty($legal_bits) ? 'ok' : $legal_st,
                empty($legal_bits) ? 'Nav reģistrētu maksātnespējas procesu, likvidāciju vai liegumu.' : ucfirst(implode('; ', $legal_bits)) . '.');
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
        $map = ['ok' => 'zaļš', 'warn' => 'dzeltens', 'risk' => 'sarkans', 'na' => 'nav datu'];
        $lines = [];
        $lines[] = 'RISKA SEMAFORS (automātiski aprēķināti signāli no publiskajiem datiem; tas pats aprēķins, ko lietotājs redz lapas sadaļā "Datu izpēte"):';
        $lines[] = '- KOPVĒRTĒJUMS [' . ($map[$sem['overall']] ?? '?') . ']: ' . (string)($sem['overall_txt'] ?? '');
        foreach (($sem['signals'] ?? []) as $s) {
            $lines[] = '- ' . $s['label'] . ' [' . ($map[$s['status']] ?? '?') . ']: ' . $s['text'];
        }
        $lines[] = 'Izmanto šos signālus kā kontekstu un savos secinājumos uz tiem atsaucies; ja tavi aprēķini no JSON datiem kādam signālam ir pretrunā, skaidri norādi šo pretrunu.';
        return implode("\n", $lines);
    }
}
