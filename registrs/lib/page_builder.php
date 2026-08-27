<?php
// server/lib/page_builder.php — ports of kods/core/page_builder.py (pēc 2026-07-10 audita labojumiem)
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/financial_engine.php';
require_once __DIR__ . '/segmenter.php';
require_once __DIR__ . '/ai_json.php';
require_once __DIR__ . '/description_builder.php';
require_once __DIR__ . '/nace_map.php';
require_once __DIR__ . '/nace_crosswalk.php';

const MASKING_CONFIG = [
    'members' => ['latvian_identity_number_masked', 'birth_date'],
    'officers' => ['latvian_identity_number_masked', 'birth_date'],
    'beneficial_owners' => ['latvian_identity_number_masked', 'birth_date'],
    'stockholders' => ['latvian_identity_number_masked', 'birth_date'],
    'members_joint_owners' => ['latvian_identity_number_masked', 'birth_date'],
    'stockholders_joint_owners' => ['latvian_identity_number_masked', 'birth_date'],
];

/**
 * Neto algas aprēķins (progresīvais IIN pa robežjoslām — kā Python pēc audita).
 */
function calculate_net_salary(?float $gross_salary, int $year): array {
    if ($gross_salary === null || $gross_salary <= 0) {
        return ['net_salary' => null, 'non_taxable_minimum' => 0, 'iin' => 0, 'vsaoi_employee' => 0];
    }

    $params = [
        2022 => ['vsaoi_rate' => 0.105, 'iin_rates' => [[20004, 0.20], [78100, 0.23], [PHP_FLOAT_MAX, 0.31]],
                 'non_taxable' => fn($gs) => ($gs > 500 && $gs <= 1200) ? max(0, 350 - 0.5 * ($gs - 500)) : ($gs <= 500 ? 350 : 0)],
        2023 => ['vsaoi_rate' => 0.105, 'iin_rates' => [[20004, 0.20], [78100, 0.23], [PHP_FLOAT_MAX, 0.31]],
                 'non_taxable' => fn($gs) => ($gs > 500 && $gs <= 1800) ? max(0, 500 - 0.38462 * ($gs - 500)) : ($gs <= 500 ? 500 : 0)],
        2024 => ['vsaoi_rate' => 0.105, 'iin_rates' => [[20004, 0.20], [78100, 0.23], [PHP_FLOAT_MAX, 0.31]],
                 'non_taxable' => fn($gs) => ($gs > 500 && $gs <= 1800) ? max(0, 500 - 0.38462 * ($gs - 500)) : ($gs <= 500 ? 500 : 0)],
        2025 => ['vsaoi_rate' => 0.105, 'iin_rates' => [[105300, 0.255], [PHP_FLOAT_MAX, 0.33]],
                 'non_taxable' => fn($gs) => 510],
        // 2026: vienīgā izmaiņa pret 2025 — fiksētais neapliekamais minimums 510 -> 550
        // EUR/mēn. IIN likmes (25,5 % / 33 %) un slieksnis (105 300 EUR gadā = 8 775
        // mēnesī) nemainās, VSAOI darba ņēmēja daļa paliek 10,5 % (10,5 + 23,59 = 34,09 %,
        // kas ir tas pats 0.3409, ar ko bruto algu atvasina no VSAOI summas).
        // Avots: test_nodokli.php konstantes (NM/IIN1/IIN2/IIN2_NO/VS_DN), kas ņemtas no
        // vid.gov.lv/lv/neapliekamais-minimums.
        2026 => ['vsaoi_rate' => 0.105, 'iin_rates' => [[105300, 0.255], [PHP_FLOAT_MAX, 0.33]],
                 'non_taxable' => fn($gs) => 550],
    ];

    // Nezināmam gadam ņem tuvāko malu, nevis fiksētu 2025: citādi, kad parādīsies
    // 2027. gada dati, tie klusi rēķinātos pēc 2025., apejot 2026. Šodienas datos
    // (VID gada 2022-2024, ceturkšņu 2025 Q4 - 2026 Q2) šis zars nenostrādā nevienreiz.
    $year_to_use = $year;
    if (!isset($params[$year_to_use])) {
        $known = array_keys($params);
        $year_to_use = $year > max($known) ? max($known) : min($known);
    }
    $p = $params[$year_to_use];

    $vsaoi_employee = $gross_salary * $p['vsaoi_rate'];
    $non_taxable_minimum = (float)($p['non_taxable'])($gross_salary);

    $taxable_income = $gross_salary - $vsaoi_employee - $non_taxable_minimum;
    if ($taxable_income < 0) $taxable_income = 0.0;

    // Progresīvais IIN pa gada robežjoslām
    $annual_taxable = $taxable_income * 12;
    $annual_iin = 0.0;
    $lower = 0.0;
    $rate_used = $p['iin_rates'][0][1];
    foreach ($p['iin_rates'] as [$limit, $rate]) {
        if ($annual_taxable > $lower) {
            $taxed_in_band = min($annual_taxable, $limit) - $lower;
            $annual_iin += $taxed_in_band * $rate;
            $rate_used = $rate;
            $lower = $limit;
        } else {
            break;
        }
    }
    $iin = $annual_iin / 12;

    $net_salary = $gross_salary - $vsaoi_employee - $iin;

    return [
        'net_salary' => py_round($net_salary, 2),
        'non_taxable_minimum' => py_round($non_taxable_minimum, 2),
        'iin' => py_round($iin, 2),
        'vsaoi_employee' => py_round($vsaoi_employee, 2),
        'iin_rate_used' => $rate_used,
        // Efektīvā likme rādāmajam vienādojumam: bāze × šī likme = IIN pēc definīcijas.
        'iin_rate_effective' => $taxable_income > 0 ? $iin / $taxable_income : $rate_used,
    ];
}

function parse_vid_number($value_str): float {
    if ($value_str === null || !is_string($value_str)) {
        return 0.0;
    }
    $cleaned = str_replace([' ', "\u{a0}", ','], ['', '', '.'], $value_str);
    return is_numeric($cleaned) ? (float)$cleaned : 0.0;
}

/**
 * Python f"{x:,.2f}".replace(",", " ") ekvivalents.
 */
function fmt_salary(float $v): string {
    return number_format($v, 2, '.', ' ');
}

/**
 * Python "%g" formāts procentiem (piem., 20, 25.5).
 */
function fmt_g(float $v): string {
    $s = sprintf('%g', $v);
    return $s;
}

function prepare_vid_panel_data(array $results): array {
    $panel = [
        'has_data' => false,
        'rating' => null,
        'pvn' => null,
        'tax_table' => [],
        'quarterly_taxes' => [],
        'salary_calculation_example' => null,
    ];

    $rating_data = $results['reitings_uznemumi'] ?? [];
    if (!empty($rating_data)) {
        $panel['rating'] = $rating_data[0];
        $panel['has_data'] = true;
    }

    $pvn_data = $results['pdb_pvnmaksataji_odata'] ?? [];
    if (!empty($pvn_data)) {
        $best = null;
        foreach ($pvn_data as $row) {
            if (($row['Aktivs'] ?? null) === 'ir') { $best = $row; break; }
        }
        if ($best === null) {
            // Vairāki slēgti PVN periodi: SELECT ir bez ORDER BY, un pirmā fiziskā
            // rinda 11 132 no 22 090 šādu uzņēmumu NAV jaunākā — "izslēgts 1996"
            // uzņēmumam, kas vēl 10 gadus BIJA maksātājs, ir nepatiess (audits
            // 2026-08-26). Ņemam jaunāko pēc izslēgšanas (rezervē — reģistrācijas).
            $pvn_key = static function (array $r): string {
                foreach (['Izslegts', 'Registrets'] as $f) {
                    if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', (string)($r[$f] ?? ''), $m)) {
                        return $m[3] . $m[2] . $m[1];
                    }
                }
                return '00000000';
            };
            $best = $pvn_data[0];
            foreach ($pvn_data as $row) {
                if ($pvn_key($row) > $pvn_key($best)) $best = $row;
            }
        }
        $panel['pvn'] = $best;
        $panel['has_data'] = true;
    }

    $tax_raw = $results['pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata'] ?? [];
    if (!empty($tax_raw)) {
        $panel['has_data'] = true;
        $tax_data = $tax_raw;
        usort($tax_data, fn($a, $b) => (int)($b['Taksacijas_gads'] ?? 0) <=> (int)($a['Taksacijas_gads'] ?? 0));

        foreach ($tax_data as $i => $row) {
            $year_val = $row['Taksacijas_gads'] ?? null;
            $vsaoi_val = $row['Taja_skaita_VSAOI'] ?? null;
            $employees_val = $row['Videjais_nodarbinato_personu_skaits_cilv'] ?? null;

            // Gada rindu izmetam TIKAI tad, ja tajā nav neviena satura skaitļa. Vecais
            // "if not all([...])" izmeta visu gadu, tiklīdz darbinieku skaits bija
            // 0/NULL — bet 177 235 rindās nodokļu kopsummas IR arī bez darbiniekiem
            // (26 985 uzņēmumiem tukša VID tabula, kaut nodokļi maksāti). Darbinieki
            // un VSAOI vajadzīgi tikai ALGAS aprēķinam, ne nodokļu rindai.
            // SVARĪGI: pārbaude pēc PARSĒTĀM vērtībām, ne py_truthy — kolonnas ir TEXT,
            // un '0.00' ir "truthy" virkne; ar py_truthy vien cauri nāca 101 194 pilnīgi
            // nulliskas rindas (51 592 uzņēmumiem), un visu-nuļļu jaunākais gads izspieda
            // īsto no tax_table[0] (algas piemērs + BUJ atbilde par nodokļiem pazuda).
            if (!py_truthy($year_val)) continue;

            $year = (int)$year_val;
            $vsaoi_thousands = $vsaoi_val === null ? 0.0
                : (is_string($vsaoi_val) ? parse_vid_number($vsaoi_val) : (float)$vsaoi_val);
            $employees = (int)parse_vid_number((string)$employees_val);

            $kopsumma_num = parse_vid_number((string)($row['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? ''));
            $iin_num = parse_vid_number((string)($row['Taja_skaita_IIN'] ?? ''));
            if ($kopsumma_num == 0.0 && $iin_num == 0.0 && $vsaoi_thousands == 0.0 && $employees === 0) continue;

            $avg_gross = 0.0;
            $salary_details = [];
            // Privātums: <3 darbiniekiem "vidējā alga" faktiski atklāj konkrētas personas algu
            // (GDPR atvasinātie dati) — aprēķinu tad nerāda (2026-08-07 tiesiskais izvērtējums).
            if ($employees >= 3 && $vsaoi_thousands > 0) {
                $avg_gross = (($vsaoi_thousands * 1000) / 0.3409) / $employees / 12;
                $salary_details = calculate_net_salary($avg_gross, $year);
            }
            // '***' = dati VID ir, bet aprēķinu slēpjam (privātums); '—' = aprēķins nav
            // iespējams (VSAOI 0/negatīvs vai darbinieku NAV — 0 darbiniekiem "vidējā
            // alga" neeksistē, tur '***' ar privātuma zemsvītru būtu nepatiess).
            $salary_hidden = ($employees >= 1 && $employees < 3 && $vsaoi_thousands > 0);

            $new_row = [
                'Taksacijas_gads' => $year,
                'Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR' => $row['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? null,
                'Taja_skaita_IIN' => $row['Taja_skaita_IIN'] ?? null,
                'Taja_skaita_VSAOI' => $row['Taja_skaita_VSAOI'] ?? null,
                'Videjais_nodarbinato_personu_skaits_cilv' => $employees,
                'avg_gross_salary' => $avg_gross > 0 ? fmt_salary($avg_gross) : ($salary_hidden ? '***' : '—'),
                'avg_net_salary' => (!empty($salary_details['net_salary'])) ? "~ " . fmt_salary($salary_details['net_salary']) : ($salary_hidden ? '***' : '—'),
            ];
            $panel['tax_table'][] = $new_row;

            if ($i === 0 && $avg_gross > 0) {
                $panel['salary_calculation_example'] = [
                    'year' => $year,
                    'vsaoi_sum' => $vsaoi_thousands,
                    'employees' => $employees,
                    'gross_salary_raw' => $avg_gross,
                    'gross_salary_formatted' => $new_row['avg_gross_salary'],
                    'net_salary_formatted' => $new_row['avg_net_salary'],
                    'vsaoi_employee_part' => fmt_salary((float)($salary_details['vsaoi_employee'] ?? 0)),
                    'non_taxable_minimum' => fmt_salary((float)($salary_details['non_taxable_minimum'] ?? 0)),
                    'iin_part' => fmt_salary((float)($salary_details['iin'] ?? 0)),
                    // EFEKTĪVĀ likme (iin/apliekamā bāze), ne augšējā marginālā: IIN ir
                    // progresīvs pa gada joslām, un ar marginālo likmi drukātais
                    // vienādojums (bāze × likme = IIN) pie lielām algām meloja par
                    // ~50 EUR (piem. 3 000 € bruto 2024: (3000−315)×0.23=617,55, bet
                    // īstais IIN 567,54). Zem joslas sliekšņa efektīvā == marginālā,
                    // tāpēc lielākajai daļai lapu nekas nemainās.
                    'iin_rate_percentage' => fmt_g(py_round(($salary_details['iin_rate_effective'] ?? $salary_details['iin_rate_used'] ?? 0.20) * 100, 2)),
                    'iin_rate_decimal' => rtrim(sprintf('%.4f', $salary_details['iin_rate_effective'] ?? $salary_details['iin_rate_used'] ?? 0.20), '0'),
                ];
            }
        }
    }

    $q_raw = $results['pdb_samaksato_nodoklu_kopsummas_cet'] ?? [];
    if (!empty($q_raw)) {
        $panel['has_data'] = true;
        $q_data = $q_raw;
        usort($q_data, fn($a, $b) => strcmp((string)($b['Taksacijas_gads_ceturksnis'] ?? ''), (string)($a['Taksacijas_gads_ceturksnis'] ?? '')));

        foreach ($q_data as $q_row) {
            $quarter = $q_row['Taksacijas_gads_ceturksnis'] ?? null;
            if (empty($quarter)) continue;

            $vsaoi_val = $q_row['Taja_skaita_VSAOI_summa'] ?? null;
            $employees_val = $q_row['Videjais_nodarbinato_personu_skaits_cilv'] ?? null;
            // Tas pats princips kā gada rindām: izmetam tikai pilnīgi nullisku ceturksni,
            // nevis katru, kam trūkst darbinieku VAI VSAOI (nodokļu kopsummas rāda vienmēr).
            // Pēc PARSĒTĀM vērtībām — '0.00' ir "truthy" virkne (sk. gada zaru).
            $vsaoi_thousands = $vsaoi_val === null ? 0.0
                : ((is_int($vsaoi_val) || is_float($vsaoi_val)) ? (float)$vsaoi_val : parse_vid_number((string)$vsaoi_val));
            $employees = (int)parse_vid_number((string)$employees_val);
            $q_kopsumma = parse_vid_number((string)($q_row['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? ''));
            $q_iin = parse_vid_number((string)($q_row['Taja_skaita_IIN_summa'] ?? ''));
            if ($q_kopsumma == 0.0 && $q_iin == 0.0 && $vsaoi_thousands == 0.0 && $employees === 0) continue;

            $avg_gross = 0.0;
            $salary_details = [];
            $calc_year = 2025;
            if (preg_match('/(\d{4})/', (string)$quarter, $m)) $calc_year = (int)$m[1];

            // Tas pats <3 darbinieku privātuma slieksnis kā gada rindām (skat. augstāk).
            if ($employees >= 3 && $vsaoi_thousands > 0) {
                $avg_gross = (($vsaoi_thousands * 1000) / 0.3409) / $employees / 3;
                $salary_details = calculate_net_salary($avg_gross, $calc_year);
            }
            // >=1: 0 darbiniekiem (TEXT '0' te iet cauri py_truthy!) '***' ar privātuma
            // zemsvītru būtu nepatiess — nav personas, kuras algu slēpt; rādām '—'.
            $salary_hidden = ($employees >= 1 && $employees < 3 && $vsaoi_thousands > 0);

            $panel['quarterly_taxes'][] = [
                'Taksacijas_gads_ceturksnis' => $quarter,
                'Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR' => $q_row['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? null,
                'Taja_skaita_IIN' => $q_row['Taja_skaita_IIN_summa'] ?? null,
                'Taja_skaita_VSAOI' => $q_row['Taja_skaita_VSAOI_summa'] ?? null,
                'Videjais_nodarbinato_personu_skaits_cilv' => $employees,
                'avg_gross_salary' => $avg_gross > 0 ? fmt_salary($avg_gross) : ($salary_hidden ? '***' : '—'),
                'avg_net_salary' => (!empty($salary_details['net_salary'])) ? "~ " . fmt_salary($salary_details['net_salary']) : ($salary_hidden ? '***' : '—'),
            ];
        }
    }

    return $panel;
}

function prepare_faq_data(array $page_data): array {
    $segment = $page_data['segment'] ?? [];
    $company_title = $page_data['companyTitleForHtml'] ?? 'Šis uzņēmums';
    $vid_data = $page_data['vid_panel_data'] ?? [];
    $nace_description = $page_data['nace_description'] ?? null;
    $faq = [];

    $status = $segment['status'] ?? null;
    $form_group = $segment['form_group'] ?? null;

    $address = $page_data['formattedAddressForHtml'] ?? null;
    if ($address !== null && $address !== '—') {
        $q = "Kāda ir {$company_title} juridiskā adrese?";
        if ($status === 'Likvidēts') {
            $q = "Kāda bija {$company_title} pēdējā reģistrētā juridiskā adrese?";
        }
        $faq[] = ['question' => $q, 'answer' => "Juridiskā adrese ir {$address}."];
    }

    if ($status === 'Likvidēts') {
        $date = $page_data['liquidation_date'] ?? 'nezināms';
        if ($date === null) $date = 'nezināms';
        array_unshift($faq, [
            'question' => "Vai uzņēmums {$company_title} ir aktīvs?",
            'answer' => "Nē, šī uzņēmuma darbība ir izbeigta {$date}.",
        ]);
    } elseif ($status === 'Aktīvs') {
        if ($form_group === 'Komercsabiedrība') {
            $ugp = $page_data['summary_table_data_for_js']['UGP'] ?? [];
            $latest = !empty($ugp) ? $ugp[0] : [];
            if (!empty($latest)) {
                $year = $latest['year'] ?? null;
                $profit = $latest['profit'] ?? null;
                $turnover = $latest['turnover'] ?? null;
                $employees = $latest['employees'] ?? null;

                if ($year !== null && $profit !== null && $turnover !== null) {
                    $currency = $latest['currency'] ?? 'EUR';
                    $turnover_str = format_number_data($turnover, $currency);
                    if ($profit >= 0) {
                        $profit_loss_text = "peļņa bija";
                        $profit_str = format_number_data($profit, $currency);
                    } else {
                        $profit_loss_text = "zaudējumi bija";
                        $profit_str = format_number_data(abs($profit), $currency);
                    }
                    $faq[] = [
                        'question' => "Kāds bija {$company_title} {$year}. gada apgrozījums un finanšu rezultāts?",
                        'answer' => "{$year}. gadā uzņēmuma apgrozījums bija {$turnover_str}, un {$profit_loss_text} {$profit_str}.",
                    ];
                }
                if ($year !== null && $employees !== null) {
                    $emp_int = (int)(float)$employees;
                    $faq[] = [
                        'question' => "Cik darbinieku bija uzņēmumam {$company_title} {$year}. gadā?",
                        'answer' => "{$year}. gadā uzņēmumā vidēji bija nodarbināti {$emp_int} darbinieki.",
                    ];
                }
            }
        }
    }

    $name_history = $page_data['results']['register_name_history'] ?? [];
    if (!empty($name_history)) {
        $names = [];
        foreach ($name_history as $h) {
            $names[] = '"' . ($h['name'] ?? '') . '"';
        }
        $history_names = implode(', ', $names);
        $faq[] = [
            'question' => "Kādi ir bijuši iepriekšējie {$company_title} nosaukumi?",
            'answer' => "Uzņēmuma iepriekšējie nosaukumi ir bijuši: {$history_names}.",
        ];
    }

    // Finanšu rādītāji no ratios_history (gadi augoši, end() = jaunākais; vērtības
    // aprēķina financial_engine — tas pats avots, ko rāda rādītāju panelis).
    $rh = $page_data['ratios_history'] ?? [];
    $cr_hist = $rh['current_ratio'] ?? [];
    $cr_last = !empty($cr_hist) ? end($cr_hist) : null;
    if (is_array($cr_last) && isset($cr_last['value']) && is_numeric($cr_last['value'])) {
        $cr_year = (string)($cr_last['year'] ?? '');
        $cr_val = number_format((float)$cr_last['value'], 2, ',', ' ');
        $ratio_answer = "Kopējais likviditātes koeficients (current ratio) {$cr_year}. gadā bija {$cr_val} — apgrozāmie līdzekļi attiecībā pret īstermiņa saistībām.";
        $qr_hist = $rh['quick_ratio'] ?? [];
        $qr_last = !empty($qr_hist) ? end($qr_hist) : null;
        if (is_array($qr_last) && isset($qr_last['value']) && is_numeric($qr_last['value']) && (string)($qr_last['year'] ?? '') === $cr_year) {
            $ratio_answer .= " Ātrās likviditātes koeficients (quick ratio): " . number_format((float)$qr_last['value'], 2, ',', ' ') . ".";
        }
        $faq[] = [
            'question' => "Kāds ir {$company_title} likviditātes koeficients?",
            'answer' => $ratio_answer,
        ];
    }

    $az_hist = $rh['altman_z_score'] ?? [];
    $az_last = !empty($az_hist) ? end($az_hist) : null;
    if (is_array($az_last) && isset($az_last['value']) && is_numeric($az_last['value'])) {
        $az_year = (string)($az_last['year'] ?? '');
        $z = (float)$az_last['value'];
        $z_val = number_format($z, 2, ',', ' ');
        if ($z < 1.8) {
            $z_zone = "bīstamajā zonā (paaugstināts maksātnespējas risks)";
        } elseif ($z < 3.0) {
            $z_zone = "pelēkajā zonā (nenoteiktības josla)";
        } else {
            $z_zone = "drošajā zonā (zems maksātnespējas risks)";
        }
        $faq[] = [
            'question' => "Kāds ir {$company_title} maksātnespējas riska novērtējums?",
            'answer' => "Altmana Z'-indekss (1983. gada modelis privātiem uzņēmumiem) {$az_year}. gadā bija {$z_val}, kas ir {$z_zone}. Aprēķins ir informatīvs un nav finanšu konsultācija.",
        ];
    }

    $pvn_info = $vid_data['pvn'] ?? null;
    if ($pvn_info !== null && !empty($pvn_info['Aktivs'])) {
        $pvn_statuss = ($pvn_info['Aktivs'] === 'ir') ? 'ir' : 'nav';
        $faq[] = [
            'question' => "Vai {$company_title} ir PVN maksātājs?",
            'answer' => "Saskaņā ar VID publiskajiem datiem, uzņēmums {$pvn_statuss} reģistrēts kā aktīvs PVN maksātājs.",
        ];
    }

    $tax_table = $vid_data['tax_table'] ?? null;
    if (!empty($tax_table)) {
        $latest_salary = $tax_table[0];
        $year = $latest_salary['Taksacijas_gads'] ?? null;
        $gross = $latest_salary['avg_gross_salary'] ?? null;
        $net = $latest_salary['avg_net_salary'] ?? null;
        // '—' = nav aprēķināms; '***' = slēpts privātuma dēļ (<3 darb.) — abos FAQ nerāda.
        if (!empty($year) && !empty($gross) && $gross !== "—" && $gross !== "***") {
            $faq[] = [
                'question' => "Kāda ir vidējā alga uzņēmumā {$company_title}?",
                'answer' => "Balstoties uz VID datiem par {$year}. gadu, aprēķinātā vidējā bruto alga (pirms nodokļiem) "
                    . "uzņēmumā bija aptuveni {$gross} EUR mēnesī. Tas atbilst aptuvenai neto algai "
                    . "('uz rokas') ap {$net} EUR. Jāņem vērā, ka šis ir aptuvens aprēķins.",
            ];
        }
    }

    // Nodokļu kopsumma no jaunākā pilnā VID gada (tax_table rindas ir gadu līmenī, jaunākais pirmais).
    $tax_row = $vid_data['tax_table'][0] ?? null;
    if (is_array($tax_row) && ($tax_row['Taksacijas_gads'] ?? null) !== null) {
        $t_year = (int)$tax_row['Taksacijas_gads'];
        $t_total_raw = $tax_row['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? null;
        $t_total = $t_total_raw !== null ? parse_vid_number((string)$t_total_raw) : 0.0;
        if ($t_total > 0) {
            $tax_answer = "{$t_year}. gadā uzņēmums valsts kopbudžetā samaksāja aptuveni " . fmt_0f($t_total * 1000) . " EUR VID administrētajos nodokļos";
            $t_iin = ($tax_row['Taja_skaita_IIN'] ?? null) !== null ? parse_vid_number((string)$tax_row['Taja_skaita_IIN']) : 0.0;
            $t_vsaoi = ($tax_row['Taja_skaita_VSAOI'] ?? null) !== null ? parse_vid_number((string)$tax_row['Taja_skaita_VSAOI']) : 0.0;
            $t_parts = [];
            if ($t_iin > 0) $t_parts[] = "iedzīvotāju ienākuma nodoklī " . fmt_0f($t_iin * 1000) . " EUR";
            if ($t_vsaoi > 0) $t_parts[] = "VSAOI " . fmt_0f($t_vsaoi * 1000) . " EUR";
            if (!empty($t_parts)) $tax_answer .= ", tostarp " . implode(" un ", $t_parts);
            $tax_answer .= " (VID atvērtie dati).";
            $faq[] = [
                'question' => "Cik nodokļos samaksāja {$company_title}?",
                'answer' => $tax_answer,
            ];
        }
    }

    $rating_info = $vid_data['rating'] ?? null;
    if (is_array($rating_info) && !empty($rating_info['Reitings'])) {
        $r_letter = (string)$rating_info['Reitings'];
        $r_expl = trim((string)($rating_info['Skaidrojums'] ?? ''));
        $faq[] = [
            'question' => "Kāds ir {$company_title} VID nodokļu maksātāja reitings?",
            'answer' => "VID nodokļu maksātāja reitings ir \"{$r_letter}\"" . ($r_expl !== '' ? " — {$r_expl}." : "."),
        ];
    }

    $is_mil = trim((string)(($page_data['dati_php_rowData'] ?? [])['regtype'] ?? '')) === 'M';

    // Nozares jautājumu uzdod TIKAI tad, ja nozare tiešām ir zināma. NACE kodu
    // atvasina no VID gada nodokļu datiem, kas ir 155 284 subjektiem no 452 454 —
    // pārējiem kods paliek noklusējuma '0000' ("Nenoteikta nozare"), un jautājums
    // "Kāda ir X galvenā darbības nozare?" atbildēja "Nenoteikta nozare".
    // Tas ir Q&A pāris bez atbildes, un tas nonāk arī schema.org FAQPage
    // marķējumā, t.i. meklētājs redz jautājumu ar tukšu atbildi.
    $nace_code_faq = trim((string)($page_data['nace_code'] ?? ''));
    $nace_zinama = $nace_code_faq !== '' && $nace_code_faq !== '0000';

    if (!empty($nace_description) && $nace_zinama) {
        $faq[] = [
            'question' => "Kāda ir {$company_title} galvenā darbības nozare?",
            'answer' => "Uzņēmuma galvenā reģistrētā darbības nozare saskaņā ar NACE klasifikatoru ir: {$nace_description}.",
        ];
    }

    if ($is_mil) {
        $reg_date = trim((string)(($page_data['dati_php_rowData'] ?? [])['registered'] ?? ''));
        $faq[] = [
            'question' => "Ko nozīmē {$company_title} statuss reģistrā?",
            'answer' => "Ieraksts masu informācijas līdzekļu reģistrā"
                . ($reg_date !== '' ? " izdarīts {$reg_date}" : "")
                . " un nav dzēsts. Reģistrs neuzskaita, vai izdevums joprojām iznāk, "
                . "tāpēc ieraksta esamība nav apliecinājums par pašreizējo darbību.",
        ];
    }

    return $faq;
}

/**
 * Rādāmais nosaukums ar juridiskās formas saīsinājumu (H1, <title>, BUJ, lede).
 *
 * UR datos forma nosaukumā ir tikai kapitālsabiedrībām ("Sabiedrība ar ierobežotu
 * atbildību "X""); biedrībām, nodibinājumiem u.c. `name` ir tikai pēdiņās liktā
 * daļa, un vecā loģika (baltais saraksts ar 8 kodiem) tās rādīja bez formas.
 * Girta 2026-08-12 prasība: forma redzama VISIEM subjektiem.
 *
 * Divi ceļi:
 *  1) kompaktais — formas tekstu pirms pēdiņām aizstāj ar vispārpieņemto
 *     saīsinājumu ('SIA "S.O.S. projekti"'); tikai tipiem, kur pirms pēdiņām ir
 *     tīra forma vai vēsturiska atrašanās vieta + forma, un tikai tad, ja aiz
 *     pēdiņām nekā nav. Nepazīstams prefikss (piem., "Zvērinātu advokātu birojs"
 *     pie PS) NAV kļūda — tas nokrīt uz 2. ceļu un paliek pilnais nosaukums;
 *  2) universālais — pilnais oficiālais nosaukums; ja tajā formas pazīmes nav
 *     nemaz (celmu saraksts), priekšā liek formas saīsinājumu vai type_text.
 *     Filiālēm/pārstāvniecībām pilnais nosaukums ir vienīgais pareizais — pēdiņās
 *     tur mēdz būt CITA komersanta vārds ('AS "SEB banka" Krāslavas filiāle').
 *
 * Tipiem bez type_text (SPO, ASF, KOR, SAA, SPA, PRO — vēsturiski) formu klusi
 * neizdomājam — nosaukums paliek kāds ir. schema.org legalName vienmēr ņem
 * neaiztikto `name`, šī funkcija ir tikai attēlošanai.
 */
function reg_company_display_title(?string $type, ?string $type_text, ?string $name, ?string $before, ?string $in_quotes, ?string $after): ?string {
    $type = trim((string)$type);
    $full = $name !== null ? trim(str_replace('""', '"', $name)) : '';
    $in_q = trim((string)$in_quotes);

    // [tips => [prefikss ('' = ņem type_text), formas pazīmju celmi nosaukumā]]
    // Celms '/.../' ir regulārā izteiksme (saīsinājumiem vajag vārda robežas),
    // pārējie — mazo burtu apakšvirknes (sedz latviešu locījumus: biedrība/-as/-u).
    static $forms = [
        'SIA' => ['SIA', ['sabiedrība ar ierobežotu atbildību', '/\bV?SIA\b/u']],
        'AS'  => ['AS', ['akciju sabiedrība', '/\b(AS|VAS|AAS)\b/u', '/\bA\/S\b/iu']],
        'IK'  => ['IK', ['individuāl', '/\bIK\b/u', '/\bI\.\s?K\.\B/iu', '/\bI\/K\b/iu']],
        'ZEM' => ['ZS', ['saimniec', '/\bZS\b/u', '/\bz\/s\b/iu']],
        'ZVJ' => ['ZvS', ['saimniec', '/\bz\/s\b/iu']],
        'IND' => ['IU', ['individuāl', '/\bIU\b/u']],
        'PS'  => ['PS', ['pilnsabiedrīb', 'advokātu biroj', '/\bPS\b/u', '/\bZAB\b/u']],
        'KS'  => ['KS', ['komandītsabiedrīb', '/\bKS\b/u', '/\bk\/s\b/iu']],
        'KB'  => ['Kooperatīvā sabiedrība', ['kooperatīv']],
        'BDR' => ['Biedrība', ['biedrīb']],
        'NOD' => ['Nodibinājums', ['nodibinājum', 'fond']],
        'MIL' => ['Masu informācijas līdzeklis', ['laikrakst', 'žurnāl', 'biļeten', 'avīz', 'katalog', 'radio', 'televīzij', 'video', 'portāl', 'vēstnes', 'mēnešrakst', 'daidžest', 'almanah', 'izdevum', 'ierakst', 'grāmat', 'internet', 'informatīv', 'licenzēt', 'licencēt', 'oriģināl', 'pielikum', 'raidījum', 'programm', 'kalendār', 'ceļved']],
        'VU'  => ['Valsts uzņēmums', ['valsts']],
        'PSV' => ['Pašvaldības uzņēmums', ['pašvaldīb']],
        'PAJ' => ['Paju sabiedrība', ['paju']],
        'DRZ' => ['Draudze', ['draudz', 'baznīc', 'misij', 'kloster', 'diecēz', 'prelatūr']],
        'KAT' => ['', ['draudz', 'baznīc', 'misij', 'kloster', 'diecēz', 'prelatūr', 'kūrij', 'seminār', 'kapitul']],
        'BAZ' => ['Baznīca', ['baznīc', 'draudz']],
        'REL' => ['', ['baznīc', 'draudz', 'misij', 'kloster', 'reliģisk']],
        'MIS' => ['Misija', ['misij']],
        'KLO' => ['Klosteris', ['kloster']],
        'DIE' => ['Diecēze', ['diecēz']],
        'ARB' => ['Arodbiedrība', ['arodbiedrīb', 'arodorganizācij', 'arodkomitej', 'arodu']],
        'ARV' => ['', ['arodbiedrīb', 'arodorganizācij', 'arodkomitej', 'arodu', 'vienīb']],
        'ARA' => ['', ['arodbiedrīb', 'arodkomitej', 'apvienīb', 'savienīb']],
        'SKT' => ['Šķīrējtiesa', ['šķīrējties', 'arbitrāž', 'ties']],
        'FIL' => ['Filiāle', ['filiāl']],
        'AKF' => ['Ārvalsts komersanta filiāle', ['filiāl', '/\bbranch\b/iu']],
        'PAR' => ['', ['pārstāvniecīb', 'pārstāv']],
        'POR' => ['', ['pārstāvniecīb', 'pārstāv']],
        'PRV' => ['Pārstāvis', ['pārstāv']],
        'PP'  => ['', ['partij', 'apvienīb', 'savienīb', 'kustīb']],
        'POL' => ['', ['partij', 'apvienīb', 'savienīb', 'kustīb']],
        'PPA' => ['', ['partij', 'apvienīb', 'savienīb']],
        'SAB' => ['Sabiedriskā organizācija', ['organizācij', 'biedrīb', 'apvienīb', 'savienīb', 'asociācij', 'klub', 'fond', 'federācij', 'nodaļ', 'centr']],
        'KBS' => ['', ['kooperatīv', 'biedrīb', 'savienīb']],
        'KSS' => ['', ['kooperatīv', 'biedrīb', 'savienīb', 'uzņēmum', 'kombināt', 'apvienīb']],
        'KBU' => ['', ['kooperatīv', 'biedrīb', 'sabiedrīb', 'uzņēmum', 'kombināt', 'apvienīb', 'punkt']],
        'SOU' => ['', ['organizācij', 'biedrīb', 'uzņēmum', 'kombināt', 'apvienīb', 'sabiedrīb']],
        'UZN' => ['', ['uzņēmum', 'firma', 'kombināt', 'apvienīb', 'centr', 'biroj', 'veikal', 'sabiedrīb']],
        'GIM' => ['Ģimenes uzņēmums', ['ģimenes', 'uzņēmum']],
        'ROI' => ['Iestāde', ['iestād']],
        'SAV' => ['Savienība', ['savienīb', 'apvienīb']],
        'SE'  => ['SE', ['/\bSE\b/u']],
        'EIG' => ['', ['interešu grup', '/\bEEIG\b/u']],
        'PAP' => ['', ['papild']], // sedz arī vēsturisko "Sabiedrība ar papildatbildību"
        'LIG' => ['', ['līgumsabiedrīb', 'sabiedrīb']],
    ];
    $stem_hit = static function (string $text, array $stems): bool {
        $tl = mb_strtolower($text, 'UTF-8');
        foreach ($stems as $stem) {
            if ($stem[0] === '/' ? (bool)preg_match($stem, $text) : (mb_strpos($tl, $stem) !== false)) {
                return true;
            }
        }
        return false;
    };

    // [tips => [noklusētais saīsinājums, [formas frāze => saīsinājums]]]
    // Frāzes pārbauda secībā — garākās (VSIA/VAS/AAS) pirms vispārīgajām. Garās
    // frāzes (≥6 zīmes vai ar atstarpi) meklē kā apakšvirkni — UR datos formas
    // teksts mēdz būt gan pirms, gan pēc vietas norādes ("Zemnieku saimniecība
    // Ogres rajona ..." un "... pagasta zemnieku saimniecība"); īsos saīsinājumus
    // ('as', 'ik') salīdzina tikai kā veselu pēdējo vārdu, lai netrāpītu vārda vidū.
    static $compact = [
        'SIA' => ['SIA', ['valsts sabiedrība ar ierobežotu atbildību' => 'VSIA', 'apdrošināšanas sabiedrība ar ierobežotu atbildību' => 'AAS', 'sabiedrība ar ierobežotu atbildību' => 'SIA', 'sia' => 'SIA', 's.i.a.' => 'SIA']],
        'AS'  => ['AS', ['apdrošināšanas akciju sabiedrība' => 'AAS', 'valsts akciju sabiedrība' => 'VAS', 'akciju sabiedrība' => 'AS', 'as' => 'AS', 'a/s' => 'AS']],
        'IK'  => ['IK', ['individuālais komersants' => 'IK', 'ik firma' => 'IK', 'ik' => 'IK', 'i.k.' => 'IK', 'i/k' => 'IK']],
        'ZEM' => ['ZS', ['zemnieku saimniecība' => 'ZS', 'zemnieka saimniecība' => 'ZS', 'z/s' => 'ZS', 'zs' => 'ZS']],
        'ZVJ' => ['ZvS', ['zvejnieku saimniecība' => 'ZvS', 'zvejnieka saimniecība' => 'ZvS', 'z/s' => 'ZvS']],
        'IND' => ['IU', ['uzņēmums' => 'IU']],
        'PS'  => ['PS', ['pilnsabiedrība' => 'PS', 'ps' => 'PS']],
        'KS'  => ['KS', ['komandītsabiedrība' => 'KS', 'ks' => 'KS', 'k/s' => 'KS']],
    ];
    $compact_match = static function (string $txt, array $phrases): ?string {
        foreach ($phrases as $phrase => $abbr) {
            $hit = (mb_strlen($phrase, 'UTF-8') >= 6 || str_contains($phrase, ' '))
                ? (mb_strpos($txt, $phrase, 0, 'UTF-8') !== false)
                : ($txt === $phrase || str_ends_with($txt, ' ' . $phrase));
            if ($hit) {
                return $abbr;
            }
        }
        return null;
    };
    if ($in_q !== '' && isset($compact[$type])) {
        $bl = mb_strtolower(trim((string)$before, " .,\t"), 'UTF-8');
        $al = mb_strtolower(trim((string)$after, " .,\t"), 'UTF-8');
        // aiz pēdiņām drīkst būt tikai nekas vai tīra forma ('"Lumix" SIA' —
        // jaunākajos ierakstos forma stāv aiz nosaukuma); citādi universālais ceļš
        $after_abbr = $al === '' ? '' : $compact_match($al, $compact[$type][1]);
        if ($after_abbr !== null) {
            if ($bl === '') {
                // ja formas pazīme jau ir pašā pēdiņu daļā ('Šimanskis-elektro IK',
                // 'KS 12/17'), prefikss to dubultotu — ejam universālo ceļu
                if (!$stem_hit($in_q, $forms[$type][1] ?? [])) {
                    return ($after_abbr !== '' ? $after_abbr : $compact[$type][0]) . " \"{$in_q}\"";
                }
            } else {
                $before_abbr = $compact_match($bl, $compact[$type][1]);
                if ($before_abbr !== null) {
                    return "{$before_abbr} \"{$in_q}\"";
                }
            }
        }
        // pirms pēdiņām kaut kas neatpazīts → pilnā nosaukuma ceļš
    }

    $title = $full !== '' ? $full : ($in_q !== '' ? "\"{$in_q}\"" : null);
    if ($title === null) {
        return null; // izsaucējs paliek pie reģ. nr.
    }
    // UR semantiskais dalījums: name === name_in_quotes nozīmē, ka viss nosaukums
    // IR pēdiņu daļa, tikai avotā pēdiņas nav pierakstītas ('Mēness 13', BDR).
    // Ja tādam liekam prefiksu, vārdisko daļu rādām pēdiņās — 'Biedrība "Mēness 13"',
    // kā šos nosaukumus raksta pats UR. Nosaukumus, kur forma jau ir iekšā vai kur
    // jau ir pēdiņas, neaiztiekam — tie paliek oficiālajā rakstībā.
    $quote_on_prefix = ($full !== '' && $full === $in_q && !str_contains($full, '"'));

    if (!isset($forms[$type])) {
        return $title; // nezināms/vēsturisks tips bez formas teksta — neizdomājam
    }
    [$prefix, $stems] = $forms[$type];
    if ($prefix === '') {
        $prefix = trim((string)$type_text);
        if ($prefix === '') {
            return $title;
        }
    }
    if ($stem_hit($title, $stems)) {
        return $title; // forma jau redzama nosaukumā
    }
    return $prefix . ' ' . ($quote_on_prefix ? "\"{$title}\"" : $title);
}

function get_company_details_for_panel($company_main_data, string $search_reg_nr, array $segment): array {
    $details = [
        'companyTitleForHtml' => $search_reg_nr,
        'formattedAddressForHtml' => '—',
        'statusText' => 'Nezināms',
        'statusClass' => 'status-unknown-bg',
        'terminatedDisplay' => '—',
        'closedDisplay' => '—',
        'closedClassModifier' => 'value-no-data-black',
        'is_liquidated' => false,
        'liquidation_date' => null,
        'show_financial_charts' => true,
        'show_summary_panel' => true,
    ];

    if (!is_array($company_main_data) || empty($company_main_data)) {
        $details['statusText'] = 'Dati nav atrasti';
        $details['show_financial_charts'] = false;
        $details['show_summary_panel'] = false;
        return $details;
    }

    if (in_array($segment['status'] ?? null, ['Likvidēts', 'Reorganizēts', 'Cits'], true)) {
        $details['is_liquidated'] = true;
        $liq = get_raw_value($company_main_data, 'terminated');
        if ($liq === null) $liq = get_raw_value($company_main_data, 'closed');
        $details['liquidation_date'] = $liq;
        $details['show_financial_charts'] = false;
        $details['show_summary_panel'] = false;
    }

    // Biedrības, nodibinājumi un partijas agrāk te tika pilnībā izslēgti no visiem
    // finanšu paneļiem. Iemesls bija pareizs (tiem nav apgrozījuma un peļņas datu),
    // bet sekas pārāk plašas: pazuda arī bilance, likviditātes rādītāji, aktīvi un
    // darbinieku skaits, kas datos IR. Tagad izslēdzam tikai to, kas tiešām prasa
    // peļņas/zaudējumu aprēķinu — to nodrošina has_financial_charts (sk. zemāk),
    // kas bez PZA datiem paliek false pats no sevis.

    $company_title = reg_company_display_title(
        get_raw_value($company_main_data, 'type'),
        get_raw_value($company_main_data, 'type_text'),
        get_raw_value($company_main_data, 'name'),
        get_raw_value($company_main_data, 'name_before_quotes'),
        get_raw_value($company_main_data, 'name_in_quotes'),
        get_raw_value($company_main_data, 'name_after_quotes')
    );
    $details['companyTitleForHtml'] = $company_title ?? $search_reg_nr;

    $address = get_raw_value($company_main_data, 'address');
    $index_val = get_raw_value($company_main_data, 'index');
    if ($address !== null) {
        $addr_str = str_replace('""', '"', (string)$address);
        $parts = array_values(array_filter(array_map('trim', explode(',', $addr_str)), fn($p) => $p !== ''));
        if (!empty($parts)) {
            $address_parts = array_reverse($parts);
            if ($index_val !== null && trim((string)$index_val) !== '' && (string)$index_val !== '0') {
                $idx = explode('.', (string)$index_val)[0];
                $address_parts[] = "LV-{$idx}";
            }
            $details['formattedAddressForHtml'] = implode(', ', $address_parts);
        }
    }

    $closed_raw = get_raw_value($company_main_data, 'closed');
    $terminated_raw = get_raw_value($company_main_data, 'terminated');

    $is_active = true;

    if ($closed_raw === 'L') {
        $details['statusText'] = "Likvidēts";
        $details['statusClass'] = "status-inactive-bg";
        $is_active = false;
        $details['closedDisplay'] = "Likvidēts";
        $details['closedClassModifier'] = "closed-l";
    } elseif ($closed_raw === 'R') {
        $details['statusText'] = "Reorganizēts";
        $details['statusClass'] = "status-process-bg";
        $is_active = false;
        $details['closedDisplay'] = "Reorganizēts";
        $details['closedClassModifier'] = "closed-r";
    } elseif ($terminated_raw !== null && trim((string)$terminated_raw) !== '' && (string)$terminated_raw !== '0000-00-00') {
        $details['statusText'] = "Darbība izbeigta";
        $details['statusClass'] = "status-process-bg";
        $is_active = false;
        $details['closedClassModifier'] = 'value-no-data-black';
    } else {
        // Masu informācijas līdzekļiem (MIL reģistrs) "Aktīvs" maldina: reģistrs
        // fiksē tikai to, ka ieraksts nav dzēsts, nevis to, ka izdevums joprojām
        // iznāk. No 3 416 nedzēstajiem MIL 1 968 reģistrēti 1990-tajos gados un
        // par to darbību datu nav. Tāpēc rādām "Reģistrēts", ne "Aktīvs".
        $is_mil = trim((string)get_raw_value($company_main_data, 'regtype')) === 'M';
        $details['statusText'] = $is_mil ? "Reģistrēts" : "Aktīvs";
        $details['statusClass'] = "status-active-bg";
        $is_active = true;
        $details['closedDisplay'] = '—';
        $details['closedClassModifier'] = 'value-no-data-black';
    }

    if ($terminated_raw !== null && trim((string)$terminated_raw) !== '' && (string)$terminated_raw !== '0000-00-00') {
        $details['terminatedDisplay'] = (string)$terminated_raw;
    } else {
        $details['terminatedDisplay'] = '—';
    }

    if ($is_active) {
        $details['closedDisplay'] = '—';
        $details['closedClassModifier'] = 'value-no-data-black';
    }

    return $details;
}

/**
 * Python `f"{x:,.0f}".replace(",", " ")` ekvivalents (noapaļo half-even uz veselo).
 */
function fmt_0f(float $v): string {
    $r = py_round($v, 0);
    return number_format($r, 0, '.', ' ');
}

function prepare_seo_metadata(array &$page_data): array {
    $segment = $page_data['segment'] ?? [];
    $company_main = $page_data['dati_php_rowData'] ?? null;
    $search_reg_nr = $page_data['search_reg_nr'] ?? '';
    $company_title = $page_data['companyTitleForHtml'] ?? '';
    $ugp = $page_data['summary_table_data_for_js']['UGP'] ?? [];
    $latest_report = !empty($ugp) ? $ugp[0] : [];

    $meta_description = "Viss par {$company_title} (reģ. nr. {$search_reg_nr}): statuss, adrese, amatpersonas, finanšu dati.";
    $status = $segment['status'] ?? null;
    $form_group = $segment['form_group'] ?? null;
    $financials = $segment['financials'] ?? null;

    if ($status === 'Likvidēts') {
        $date = $page_data['liquidation_date'] ?? 'N/A';
        if ($date === null) $date = 'N/A';
        $meta_description = "{$company_title} (reģ. nr. {$search_reg_nr}) ir likvidēts {$date}. Apskatiet vēsturiskos uzņēmuma datus.";
    } elseif ($status === 'Aktīvs') {
        // Riska apzīmogošana (audits 2026-08-26): uzņēmumam ar AKTĪVU maksātnespēju,
        // sankcijām vai VID apturēšanu SERP fragments, kas slavē peļņu, runā pretī
        // pašai lapai. Kaskāde ir tā pati, ko rāda tiesiskā sadaļa — 'risk' līmenī
        // apraksts nosauc faktu, ne peļņu. 'warn'/'past' netiek apzīmogoti (troksnis).
        $seo_risk_head = '';
        if (is_array($page_data['results'] ?? null)) {
            try {
                require_once __DIR__ . '/riska_kopsavilkums.php';
                $seo_ts = reg_tiesiskais_kopsavilkums($page_data['results']);
                if (($seo_ts['level'] ?? '') === 'risk') $seo_risk_head = (string)$seo_ts['head'];
            } catch (Throwable $e) { /* apraksts bez apzīmogojuma */ }
        }
        if ($seo_risk_head !== '') {
            $meta_description = "{$company_title} ({$search_reg_nr}): {$seo_risk_head}. Statuss, finanšu dati un reģistru ieraksti vienuviet.";
        } elseif ($form_group === 'Komercsabiedrība' && !empty($latest_report)) {
            $year = $latest_report['year'] ?? null;
            $profit = $latest_report['profit'] ?? null;
            $turnover = $latest_report['turnover'] ?? null;
            // Valūta NO PĀRSKATA, ne iekodēta: pirms-2014 pārskati ir latos, un
            // "EUR" tiem meta aprakstā bija nepatiess (~501 aktīvs uzņēmums) un
            // pretrunā ar tās pašas lapas BUJ, kur valūta jau nāca no datiem
            // (audits 2026-08-19).
            $cur = (string)($latest_report['currency'] ?? 'EUR');
            if ($cur === '') $cur = 'EUR';
            if ($financials === 'Peļņa' && $profit !== null && $turnover !== null) {
                $meta_description = "{$company_title} ({$search_reg_nr}) jaunākie dati. {$year}. gada peļņa: " . fmt_0f((float)$profit) . " {$cur} pie " . fmt_0f((float)$turnover) . " {$cur} apgrozījuma.";
            } elseif (($financials === 'Zaudējumi' || $financials === 'Bez peļņas un zaudējumiem') && $turnover !== null) {
                $meta_description = "{$company_title} ({$search_reg_nr}) jaunākie dati. {$year}. gada apgrozījums: " . fmt_0f((float)$turnover) . " {$cur}. Apskatīt pilnu finanšu pārskatu.";
            }
        } else {
            $meta_description = "{$company_title} ({$search_reg_nr}) - aktīvs. Visa UR informācija: adrese, statuss, vēsture.";
        }
    }

    $page_data['meta_description'] = $meta_description;
    $kw_parts = array_values(array_filter([
        $company_title,
        get_raw_value($company_main, 'type_text'),
        $search_reg_nr,
        'Uzņēmumu reģistrs',
        'finanšu dati',
    ], fn($x) => $x !== null && $x !== ''));
    $page_data['page_keywords'] = implode(', ', array_map('strval', $kw_parts));

    $faq_list = prepare_faq_data($page_data);
    $page_data['faq_list'] = $faq_list;

    $og_title = $page_data['current_page_title'] ?? 'Saraksts.lv';
    $seo_data = [
        'open_graph' => [
            'title' => $og_title,
            'description' => $page_data['meta_description'],
            'type' => 'website',
            'url' => BASE_DOMAIN . "/{$search_reg_nr}",
            // Reāls, produkcijā sasniedzams attēls (pārbaudīts HTTP 200). Vēsturiskais
            // /assets/img/social_logo.png NEEKSISTĒ ne lokāli, ne serverī (404) — kamēr
            // šo lauku neviens neizvadīja, tas bija miris dats; līdz ar og:image emisiju
            // katra lapa sāka reklamēt 404 sociālajiem rāpuļiem, un JSON-LD "logo" uz to
            // rādīja jau sen. 512×512 ikona der abiem; dizainētu 1200×630 baneri var
            // nomainīt šeit vienuviet.
            'image' => BASE_DOMAIN . "/registrs/assets/img/icons/web-app-manifest-512x512.png",
        ],
        'schema_org_json' => null,
        'faq_schema_json' => null,
    ];

    if ($company_main !== null) {
        $schema_type = "Organization";
        if ($form_group === 'NVO') $schema_type = "NGO";
        if ($form_group === 'Partija') $schema_type = "PoliticalParty";

        // UR adreses secība ir "Novads[, Pagasts], Pilsēta/Ciems, Iela Nr" —
        // iela ir PĒDĒJAIS elements (kā formattedAddressForHtml reversē), nevis parts[1].
        $address_val = $company_main['address'] ?? null;
        $address_str = $address_val !== null ? str_replace('""', '"', (string)$address_val) : '';
        $addr_parts = array_values(array_filter(array_map('trim', explode(',', $address_str)), fn($p) => $p !== ''));

        $street = null; $locality = null; $addr_region = null;
        $n_parts = count($addr_parts);
        if ($n_parts >= 3) {
            $street = $addr_parts[$n_parts - 1];
            $locality = $addr_parts[$n_parts - 2];
            $addr_region = implode(', ', array_slice($addr_parts, 0, $n_parts - 2));
        } elseif ($n_parts === 2) {
            $locality = $addr_parts[0];
            $street = $addr_parts[1];
        } elseif ($n_parts === 1) {
            $street = $addr_parts[0];
        }

        $postal_code = null;
        $index_raw = get_raw_value($company_main, 'index');
        if ($index_raw !== null && trim((string)$index_raw) !== '' && (string)$index_raw !== '0') {
            $postal_code = 'LV-' . explode('.', (string)$index_raw)[0];
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type" => $schema_type,
            "name" => $company_title,
            "legalName" => get_raw_value($company_main, 'name'),
            "url" => $seo_data['open_graph']['url'],
            "logo" => $seo_data['open_graph']['image'],
            "registrationDate" => get_raw_value($company_main, 'registered'),
            "foundingDate" => get_raw_value($company_main, 'registered'),
            "dateModified" => $page_data['data_updated'] ?? null,
            "taxID" => $search_reg_nr,
            "address" => array_filter([
                "@type" => "PostalAddress",
                "streetAddress" => $street,
                "addressLocality" => $locality,
                "addressRegion" => $addr_region,
                "postalCode" => $postal_code,
                "addressCountry" => "LV",
            ], fn($v) => $v !== null),
            "identifier" => [
                "@type" => "PropertyValue",
                "propertyID" => "Latvian Company Registration Number",
                "value" => $search_reg_nr,
            ],
        ];

        // vatID TIKAI aktīviem PVN maksātājiem: "LV{regcode}" ir īsts PVN numurs tikai
        // tad, ja subjekts tāds reģistrēts. Agrāk to izdomāja KATRAI lapai — arī
        // biedrībām, partijām un likvidētiem (aktīvu PVN maksātāju ir ~84 tūkst. no
        // 486 tūkst.), t.i. ~80 % lapu strukturētajos datos bija safabricēts numurs.
        if ((($page_data['vid_panel_data']['pvn']['Aktivs'] ?? null)) === 'ir') {
            $schema['vatID'] = "LV{$search_reg_nr}";
        }

        if ($status === 'Likvidēts' && !empty($page_data['liquidation_date'])) {
            $schema['dissolutionDate'] = $page_data['liquidation_date'];
        }

        // Darbinieku skaits no jaunākā gada pārskata (QuantitativeValue ar gada kontekstu).
        if (!empty($latest_report) && ($latest_report['employees'] ?? null) !== null) {
            $emp_qv = [
                "@type" => "QuantitativeValue",
                "value" => (int)(float)$latest_report['employees'],
            ];
            if (($latest_report['year'] ?? null) !== null) {
                $emp_qv['description'] = "Vidējais darbinieku skaits {$latest_report['year']}. gadā (gada pārskats)";
            }
            $schema['numberOfEmployees'] = $emp_qv;
        }

        // NACE nozare kā papildu īpašība (oficiālas NACE īpašības schema.org nav).
        $nace_code_v = $page_data['nace_code'] ?? null;
        if (!empty($nace_code_v) && $nace_code_v !== '0000') {
            $nace_prop = [
                "@type" => "PropertyValue",
                "propertyID" => "NACE",
                "name" => "Pamatdarbības nozare (NACE)",
                "value" => $page_data['nace_link_code'] ?? $nace_code_v,
            ];
            if (!empty($page_data['nace_description'])) {
                $nace_prop['description'] = $page_data['nace_description'];
            }
            if (!empty($page_data['nace_link_code'])) {
                $nace_prop['url'] = BASE_DOMAIN . '/nozare/' . $page_data['nace_link_code'];
            }
            $schema['additionalProperty'] = $nace_prop;
        }

        $clean_schema = [];
        foreach ($schema as $k => $v) {
            if ($v !== null) $clean_schema[$k] = $v;
        }
        // JSON_HEX_TAG: DB brīvteksta '</script>' citādi pārtrauktu ld+json bloku
        // un ļautu izlauzties HTML kontekstā (audits 2026-08-26, aizsardzība dziļumā).
        $seo_data['schema_org_json'] = json_encode(sanitize_for_json($clean_schema), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    if (!empty($faq_list)) {
        $entries = [];
        foreach ($faq_list as $item) {
            $entries[] = ["@type" => "Question", "name" => $item['question'], "acceptedAnswer" => ["@type" => "Answer", "text" => $item['answer']]];
        }
        $faq_schema = ["@context" => "https://schema.org", "@type" => "FAQPage", "mainEntity" => $entries];
        $seo_data['faq_schema_json'] = json_encode($faq_schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    return $seo_data;
}

/**
 * Vai reģkods tiešām eksistē reģistrā? Tabulu šūnās parādās arī kodi, kuru
 * reģistrā NAV (valsts iestādes ppi_/PVN tabulās — piem. 90000045353) — tos
 * saitēt nozīmē 404 (GSC atradums 2026-08-09). Statisks kešs lapas ietvaros;
 * bez DB konteksta (būves ceļi) — vecā uzvedība (linko).
 */
function reg_regcode_exists(string $rc): bool {
    static $cache = [];
    if (isset($cache[$rc])) return $cache[$rc];
    if (!function_exists('get_ur_db')) return $cache[$rc] = true;
    static $st = null;
    try {
        if ($st === null) $st = get_ur_db()->prepare('SELECT 1 FROM register WHERE regcode = ?');
        $st->execute([$rc]);
        $ok = $st->fetchColumn() !== false;
    } catch (Throwable $e) {
        $ok = true;
    }
    return $cache[$rc] = $ok;
}

function prepare_data_for_results_tables(array $page_data): array {
    $results = $page_data['results'] ?? [];
    if (empty($results)) return [];

    $EXCLUDE = [
        'officers' => 1, 'beneficial_owners' => 1, 'stockholders' => 1,
        'members_joint_owners' => 1, 'stockholders_joint_owners' => 1,
        'members' => 1, 'pdb_samaksato_nodoklu_kopsummas_cet' => 1,
        // iepirkumi: lielākajiem piegādātājiem ir 2400+ līgumu, un jēltabula
        // "Izmantotie dati" sadaļā pievienotu lapai ap megabaitu HTML. Datus
        // parāda iepirkumu panelis, avota saite ir tā piezīmē.
        'iepirkumi' => 1,
        // es_fondi: tas pats iemesls — lielākajiem uzņēmumiem 700+ rindas.
        'es_fondi' => 1,
        // bis: būvkomersantam līdz 24 gadu rindām + statusu vēsture; pārējās
        // rāda paneļi, un jēltabulas lapai pievienotu tikai svaru.
        'bis' => 1, 'vide' => 1, 'zva' => 1, 'vid_statusi' => 1, 'atkritumi' => 1,
    ];

    $current_reg = $page_data['search_reg_nr'] ?? '';
    $base_url = BASE_DOMAIN . "/";
    $linkable_cols = [
        'regcode' => 1, 'at_legal_entity_registration_number' => 1, 'depository_registration_number' => 1,
        'legal_entity_registration_number' => 1, 'debtor_registration_number' => 1,
        'delegatedEntityRegistrationNumber' => 1, 'registrationNumber' => 1,
        // Reorganizācijas otra puse — tieši tā ir noderīgā informācija ("kurā
        // uzņēmumā pievienots", "kas nodalīts"), tāpēc tai jābūt saitei.
        'source_entity_regcode' => 1, 'final_entity_regcode' => 1,
    ];

    $END_TABLES = [
        'members' => 'Dalībnieki (Meklētajā uzņēmumā)',
        'officers' => null,
        'beneficial_owners' => null,
        'stockholders' => 'Akcionāri (Meklētajā uzņēmumā)',
    ];
    $end_keys = array_keys($END_TABLES);

    $main_tables = [];
    $end_tables = [];

    $main_keys = array_values(array_filter(array_keys($results), fn($k) => !in_array($k, $end_keys, true)));
    usort($main_keys, function ($a, $b) {
        $ra = TABLE_DISPLAY_CONFIG[$a]['rank'] ?? 999;
        $rb = TABLE_DISPLAY_CONFIG[$b]['rank'] ?? 999;
        return $ra <=> $rb;
    });

    foreach ($main_keys as $key) {
        if (!empty($results[$key]) && !isset($EXCLUDE[$key])) {
            $main_tables[] = ['data' => $results[$key], 'key' => $key];
        }
    }

    if (!empty($page_data['member_as_entity_records'])) {
        $main_tables[] = [
            'data' => $page_data['member_as_entity_records'],
            'key' => 'members',
            'title' => "Dalība citos uzņēmumos (kā subjekts)",
        ];
        usort($main_tables, function ($a, $b) {
            $ra = TABLE_DISPLAY_CONFIG[$a['key']]['rank'] ?? 999;
            $rb = TABLE_DISPLAY_CONFIG[$b['key']]['rank'] ?? 999;
            return $ra <=> $rb;
        });
    }

    foreach ($end_keys as $key) {
        if (!empty($results[$key]) && !isset($EXCLUDE[$key])) {
            $info = ['data' => $results[$key], 'key' => $key];
            if ($END_TABLES[$key] !== null) $info['title'] = $END_TABLES[$key];
            $end_tables[] = $info;
        }
    }

    $tables_to_render = array_merge($main_tables, $end_tables);

    $processed = [];
    foreach ($tables_to_render as $tinfo) {
        $rows_in = $tinfo['data'] ?? null;
        if (empty($rows_in) || !is_array($rows_in) || empty($rows_in[0]) || !is_array($rows_in[0])) continue;

        $config_key = $tinfo['key'];
        $config_entry = TABLE_DISPLAY_CONFIG[$config_key] ?? [];
        $title = $tinfo['title'] ?? ($config_entry['title'] ?? ucwords(str_replace('_', ' ', $config_key)));

        $headers = [];
        $original_headers = [];
        foreach (array_keys($rows_in[0]) as $h) {
            if (strtolower((string)$h) === 'super_id') continue;
            $original_headers[] = (string)$h;
        }

        foreach ($original_headers as $header) {
            $tr = COLUMN_NAME_TRANSLATIONS[$header] ?? [];
            $headers[] = [
                'original' => $header,
                'short' => $tr['short'] ?? $header,
                'full' => $tr['full'] ?? '',
            ];
        }

        $rows = [];
        foreach ($rows_in as $row_dict) {
            $cells = [];
            foreach ($headers as $hinfo) {
                $col = $hinfo['original'];
                $cell_value = $row_dict[$col] ?? null;
                $final_value = null;

                $tkey = $tinfo['key'];
                if (isset(MASKING_CONFIG[$tkey]) && in_array($col, MASKING_CONFIG[$tkey], true)) {
                    if ($cell_value !== null && trim((string)$cell_value) !== '') {
                        $final_value = str_repeat('@', mb_strlen((string)$cell_value, 'UTF-8'));
                    } else {
                        $final_value = $cell_value;
                    }
                } else {
                    if ($cell_value !== null && !(is_float($cell_value) && is_nan($cell_value))) {
                        if (is_float($cell_value) && $cell_value == (int)$cell_value) {
                            $final_value = (int)$cell_value;
                        } else {
                            $final_value = $cell_value;
                        }
                    }
                }

                $cell = ['value' => $final_value, 'is_link' => false, 'url' => null];
                $vstr = trim($final_value === null ? 'None' : (is_bool($final_value) ? ($final_value ? 'True' : 'False') : (string)$final_value));

                if (isset($linkable_cols[$col]) && ctype_digit($vstr) && strlen($vstr) === 11 && $vstr !== (string)$current_reg
                    && reg_regcode_exists($vstr)) {
                    $cell['is_link'] = true;
                    $cell['url'] = $base_url . $vstr;
                }

                $cells[] = $cell;
            }

            $row_id = get_raw_value($row_dict, 'id');
            if ($row_id === null) $row_id = get_raw_value($row_dict, 'statement_id');
            $rows[] = [
                'values' => $cells,
                'data_attributes' => $row_id !== null ? ['data-row-id' => (string)$row_id] : [],
            ];
        }

        $processed[] = [
            'title' => $title,
            'link_url' => null,
            'mysql_table_name_subtitle' => '[' . ($config_entry['mysql_table_name'] ?? $config_key) . ']',
            'mysql_table_name_raw' => $config_entry['mysql_table_name'] ?? $config_key,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    return $processed;
}

function prepare_summary_table_data(array $all_processed_data, array $sankey_years, int $max_years = 10): array {
    $summary = ['UGP' => [], 'UKGP' => []];

    $relevant = $sankey_years;
    if (empty($relevant)) {
        // Rezerves ceļš tikai tad, ja PZA rindas NAV NEVIENAM gadam — tas ir biedrību
        // un nodibinājumu gadījums (UR tiem ieņēmumu/izdevumu pārskatu nepublicē).
        // Nedrīkst balstīties uz tukšu $sankey_years: guļošam uzņēmumam ar nullēs
        // aizpildītu PZA sankey nav (nav ko zīmēt), bet dati IR — tur kopsavilkums
        // jāatstāj tukšs tieši tāpat kā līdz šim, citādi rastos izdomāts
        // "apgrozījums 0 EUR" un segments nepatiesi pārslēgtos uz "Zaudējumi".
        foreach ($all_processed_data as $yr_types) {
            foreach (['UGP', 'UKGP'] as $rt) {
                if (is_array($yr_types[$rt]['income_data'] ?? null)) return $summary;
            }
        }
        $relevant = array_keys($all_processed_data);
        if (empty($relevant)) return $summary;
    }
    rsort($relevant, SORT_NUMERIC);
    $relevant = array_slice($relevant, 0, $max_years);

    foreach (['UGP', 'UKGP'] as $rt) {
        foreach ($relevant as $year) {
            $year_data = $all_processed_data[$year][$rt] ?? null;
            if (!is_array($year_data)) continue;

            $income = $year_data['income_data'] ?? [];
            if (!is_array($income)) $income = [];
            $balance = $year_data['balance_data'] ?? [];
            if (!is_array($balance)) $balance = [];
            $fs_data = $year_data['fs_data'] ?? [];
            if (!is_array($fs_data)) $fs_data = [];

            $factor_text = $fs_data['rounded_to_nearest'] ?? 'ONES';
            $factor = $factor_text === 'THOUSANDS' ? 1000 : ($factor_text === 'MILLIONS' ? 1000000 : 1);

            $turnover = get_raw_value($income, 'net_turnover');
            $profit = get_raw_value($income, 'net_income');
            $assets = get_raw_value($balance, 'total_assets');

            $summary[$rt][] = [
                'year' => $year,
                'turnover' => $turnover !== null ? $turnover * $factor : null,
                'profit' => $profit !== null ? $profit * $factor : null,
                'assets' => $assets !== null ? $assets * $factor : null,
                'employees' => get_raw_value($fs_data, 'employees'),
                'currency' => $fs_data['currency'] ?? 'EUR',
            ];
        }
    }
    return $summary;
}

function prepare_ratios_history_for_charts(array $all_processed_data, int $max_years = 5): array {
    if (empty($all_processed_data)) return [];

    $available = [];
    foreach ($all_processed_data as $year => $types) {
        if (!empty($types['UGP'])) $available[] = $year;
    }
    rsort($available, SORT_NUMERIC);
    $years = array_slice($available, 0, $max_years);
    $years = array_reverse($years);

    $history = [];
    foreach ($years as $year) {
        $ratios = $all_processed_data[$year]['UGP']['financial_ratios'] ?? [];
        if (empty($ratios)) continue;

        foreach ($ratios as $rk => $rv) {
            if (!isset($history[$rk])) $history[$rk] = [];
            $value = $rv['value'] ?? null;
            if ((is_int($value) || is_float($value))
                && (str_contains($rk, 'margin') || str_contains($rk, 'roa') || str_contains($rk, 'roe') || str_contains($rk, 'roce'))) {
                $value = $value * 100;
            }
            if ($value !== null) {
                $history[$rk][] = ['year' => (string)$year, 'value' => $value];
            }
        }
    }
    return $history;
}

function build_js_config(array $page_data): string {
    $ratios_history = $page_data['ratios_history'] ?? prepare_ratios_history_for_charts($page_data['allProcessedData'] ?? []);

    $js_config = [
        'minAutocompleteChars' => MIN_AUTOCOMPLETE_CHARS,
        'maxSearchTermLength' => MAX_SEARCH_TERM_LENGTH,
        'isLocalEnv' => false,
        'baseActionUrl' => "#",
        'assetsBasePath' => ASSETS_BASE_PATH_FOR_HTML,
        'allProcessedData' => $page_data['allProcessedData'] ?? [],
        'sankeyAvailableYears' => $page_data['sankeyAvailableYears'] ?? [],
        'dataAvailableForCharts' => !empty($page_data['sankeyAvailableYears']),
        'searchRegNr' => $page_data['search_reg_nr'] ?? null,
        'naceCode' => $page_data['nace_code'] ?? null,
        'summaryTableData' => $page_data['summary_table_data_for_js'] ?? ['UGP' => [], 'UKGP' => []],
        'ratiosHistory' => $ratios_history,
    ];
    $sanitized = sanitize_for_json($js_config);
    // Python json.dumps ar ensure_ascii=False; tukšie dict {} pret [] — JS pusē abi der.
    $json = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Tukšs allProcessedData {} vietā [] — saskaņojam ar Python ({}):
    return $json === false ? '{}' : $json;
}

function get_company_nace_info(array $results): array {
    $nace_code_str = '0000';
    $nace_description = 'Nenoteikta nozare';
    $nace_link_code = null;

    try {
        $tax_data = $results['pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata'] ?? [];

        if (!empty($tax_data)) {
            $valid = [];
            foreach ($tax_data as $row) {
                $y = $row['Taksacijas_gads'] ?? null;
                if ($y !== null && is_numeric((string)$y)) {
                    $row['Taksacijas_gads_num'] = (float)$y;
                    $valid[] = $row;
                }
            }
            usort($valid, fn($a, $b) => $b['Taksacijas_gads_num'] <=> $a['Taksacijas_gads_num']);

            foreach ($valid as $record) {
                $raw_code = get_raw_value($record, 'Pamatdarbibas_NACE_kods');
                if ($raw_code !== null && !in_array(trim((string)$raw_code), ['?', '', '0', '00', 'nan', 'None'], true)) {
                    $candidate = trim((string)$raw_code);
                    if ($candidate !== '0000') {
                        $nace_code_str = $candidate;
                        $nace_description = null;
                        break;
                    }
                }
            }
        }

        // Nozares apraksts hierarhiski: precīzais kods → grupa (3-cip.) → nodaļa (2-cip.).
        // VID nodokļu dati mēdz lietot NACE 2.0 kodus (piem. 5610, 4120, 4719), kas NACE 2.1
        // klasifikatorā (NACE_MAP) kā 4-ciparu klase neeksistē, bet vecāka līmeņa kods gandrīz
        // vienmēr ir — tā ~8.6% uzņēmumu vairs nepaliek bez nozares apraksta. Vecos 2.0 kodus,
        // kuriem ir deterministisks 2.1 pēctecis (NACE_2_0_TO_2_1), vispirms pārkartē precīzi.
        if ($nace_code_str !== '0000') {
            $desc_code = NACE_2_0_TO_2_1[$nace_code_str] ?? $nace_code_str;
            foreach ([$desc_code, substr($desc_code, 0, 3), substr($desc_code, 0, 2)] as $lookup) {
                if (strlen($lookup) < 2) continue;
                if (isset(NACE_MAP[$lookup])) {
                    $nace_description = NACE_MAP[$lookup];
                    // Saite uz /nozare/{kods} lapu: NACE_MAP atslēga ir bezpunkta (np)
                    // 2.1 klasifikatora kods — pārvēršam punktotajā formā (8110 -> 81.10).
                    $nace_link_code = strlen($lookup) === 2 ? $lookup
                        : substr($lookup, 0, 2) . '.' . substr($lookup, 2);
                    break;
                }
            }
        }
    } catch (Throwable $e) {
        // Python drukā kļūdu; šeit klusi paliekam pie noklusējuma
    }

    return ['nace_code' => $nace_code_str, 'nace_description' => $nace_description, 'nace_link_code' => $nace_link_code];
}

/**
 * cap_to_5_years — ports no build_page_data iekšējās funkcijas.
 */
function cap_to_5_years($obj) {
    if (is_array($obj) && !array_is_list($obj)) {
        // dict
        $keys = array_keys($obj);
        $all_year_keys = !empty($keys);
        foreach ($keys as $k) {
            $ks = (string)$k;
            if (!(ctype_digit($ks) && strlen($ks) === 4)) { $all_year_keys = false; break; }
        }
        if ($all_year_keys) {
            $sorted = $keys;
            rsort($sorted, SORT_NUMERIC);
            $sorted = array_slice($sorted, 0, 5);
            $new = [];
            foreach ($sorted as $k) $new[$k] = $obj[$k];
            $obj = $new;
        }

        foreach ($obj as $k => $v) {
            if (in_array((string)$k, ['data', 'yearly', 'annual'], true) && is_array($v) && array_is_list($v)) {
                $sliced = array_slice($v, 0, 5);
                $obj[$k] = array_map('cap_to_5_years', $sliced);
            } elseif (is_array($v)) {
                $obj[$k] = cap_to_5_years($v);
            }
        }
        return $obj;
    }
    if (is_array($obj)) {
        // list
        $sliced = array_slice($obj, 0, 5);
        return array_map('cap_to_5_years', $sliced);
    }
    return $obj;
}

/**
 * Renderer-līmeņa SEO (no kods/py/renderer.py process_company) — title/desc/keywords.
 */
/**
 * Reģistri, kuros nav peļņas, apgrozījuma un VID nodokļu datu.
 *
 * Pārbaudīts pret datiem: B/S/R/P/O/A reģistros kopā ir 29 peļņas-zaudējumu
 * aprēķini uz 37 603 subjektiem un NULLE VID nodokļu ierakstu (VID publicē
 * kopsummas tikai par komersantiem). Komercvirsraksts "peļņa, vidējā alga"
 * tiem sola to, kā lapā nekad nav.
 */
const SEO_BEZPELNAS_REGISTRI = [
    'B' => 1,   // Biedrību un nodibinājumu reģistrs
    'S' => 1,   // Sabiedrisko organizāciju reģistrs
    'R' => 1,   // Reliģisko organizāciju un to iestāžu reģistrs
    'P' => 1,   // Politisko partiju reģistrs
    'O' => 1,   // Politisko organizāciju un to apvienību reģistrs
    'A' => 1,   // Arodbiedrību reģistrs
];

function apply_renderer_seo(array &$final_d, array $all_res, string $reg_nr): void {
    $main = $final_d['dati_php_rowData'] ?? [];
    $t = trim((string)($main['type'] ?? ''));
    $n = trim((string)(($main['name_in_quotes'] ?? null) ?: (($main['name_before_quotes'] ?? null) ?: ($main['name'] ?? ''))));
    // <title>/og:title tas pats kanoniskais nosaukums, ko H1 un JSON-LD (audits
    // 2026-08-26): jēlā "tips + pēdiņu daļa" salikšana deva "ZEM JAUNSTRŪKAS"
    // (lasās kā vārds 'zem'), "BDR X", un filiālēm pēdiņu daļa ir CITA komersanta
    // vārds — tieši slazds, par ko brīdina reg_company_display_title doc. Vecā
    // salikšana paliek kā rezerve, ja kanoniskais iznāk tukšs.
    $seo_disp = reg_company_display_title(
        $main['type'] ?? null, $main['type_text'] ?? null, $main['name'] ?? null,
        $main['name_before_quotes'] ?? null, $main['name_in_quotes'] ?? null,
        $main['name_after_quotes'] ?? null);
    $seo_p = trim((string)$seo_disp);
    if ($seo_p === '') $seo_p = $t !== '' ? trim("$t $n") : $n;

    $regtype = trim((string)($main['regtype'] ?? ''));

    // Masu informācijas līdzekļi (M, 4 268): reģistrā ir TIKAI ieraksts — nav
    // gada pārskatu, bilanču, amatpersonu, darbības mērķu un NACE koda (pārbaudīts:
    // 0 no 4 268 visās šajās tabulās; nosaukuma vēsture ir 251). Turklāt subjekts
    // ir izdevums, nevis uzņēmums, tāpēc komercvirsraksts bija dubultā nepatiess.
    if ($regtype === 'M') {
        $veids = mb_strtolower(trim((string)($main['type_text'] ?? 'masu informācijas līdzeklis')), 'UTF-8');
        $reg_date = trim((string)($main['registered'] ?? ''));
        $gads = preg_match('/^(\d{4})/', $reg_date, $mm) ? $mm[1] : '';
        $final_d['seo'] = [
            'title' => "{$seo_p} - Reģistrācijas dati, adrese, ieraksts MIL reģistrā",
            'desc' => "{$seo_p} (reģ. nr {$reg_nr}) — {$veids}"
                . ($gads !== '' ? ", reģistrēts {$gads}. gadā" : "") . ".",
            'keywords' => "{$seo_p}, {$reg_nr}, masu informācijas līdzeklis, reģistrācijas dati",
        ];
        $final_d['meta_description'] = "{$seo_p} (reģ. nr. {$reg_nr}) — {$veids}"
            . ($gads !== '' ? ", reģistrēts {$gads}. gadā" : "")
            . ". Ieraksts MIL reģistrā: nosaukums, adrese un reģistrācijas datums. "
            . "Reģistrs neuzskaita, vai izdevums joprojām iznāk.";
        $final_d['page_keywords'] = $final_d['seo']['keywords'];
        if (isset($final_d['seo_metadata']['open_graph']['description'])) {
            $final_d['seo_metadata']['open_graph']['description'] = $final_d['meta_description'];
        }
        return;
    }

    // Šķīrējtiesas (T, 228 subjekti): nav ne gada pārskatu, ne amatpersonu, ne
    // darbības mērķu — lapā ir tikai reģistra ieraksts, šķīrējtiesu saraksts un
    // dibinātāji (arbitration_members satur TIKAI juridiskās personas, 55 no 55).
    if ($regtype === 'T') {
        $dib = [];
        foreach ($all_res['arbitration_members'] ?? [] as $r) {
            $nm = trim((string)($r['name'] ?? ''));
            if ($nm !== '' && !in_array($nm, $dib, true)) $dib[] = str_replace('""', '"', $nm);
            if (count($dib) >= 3) break;
        }
        $final_d['seo'] = [
            'title' => "{$seo_p} - Rekvizīti, dibinātāji, adrese, statuss",
            'desc' => "Šķīrējtiesa {$seo_p} (reģ. nr {$reg_nr}) — reģistrācijas dati, dibinātāji un adrese.",
            'keywords' => "{$seo_p}, {$reg_nr}, šķīrējtiesa, rekvizīti, dibinātāji",
        ];
        $final_d['meta_description'] = "{$seo_p} (reģ. nr. {$reg_nr}) — šķīrējtiesa."
            . ($dib ? ' Dibinātāji: ' . implode(', ', $dib) . '.' : '')
            . ' Reģistrācijas dati, adrese un statuss.';
        $final_d['page_keywords'] = $final_d['seo']['keywords'];
        if (isset($final_d['seo_metadata']['open_graph']['description'])) {
            $final_d['seo_metadata']['open_graph']['description'] = $final_d['meta_description'];
        }
        return;
    }

    if (isset(SEO_BEZPELNAS_REGISTRI[$regtype])) {
        // Bezpeļņas organizācijas: aprakstā jomas no biedrību/nodibinājumu jomu
        // kopas (NACE tām nav — nozares kods paliek 0000).
        $jomas = [];
        foreach ($all_res['areas_of_activity_of_associations_foundations'] ?? [] as $r) {
            $j = trim((string)($r['area_of_activity'] ?? ''));
            if ($j !== '' && !in_array($j, $jomas, true)) $jomas[] = $j;
            if (count($jomas) >= 3) break;
        }
        $jomu_txt = $jomas ? mb_strtolower(implode(', ', $jomas), 'UTF-8') . '. ' : '';
        $veids = mb_strtolower(trim((string)($main['type_text'] ?? 'organizāciju')), 'UTF-8');

        $final_d['seo'] = [
            'title' => "{$seo_p} - Rekvizīti, darbības jomas, gada pārskati, bilance",
            'desc' => "Pilna informācija par {$veids} {$seo_p} (reģ. nr {$reg_nr}). {$jomu_txt}"
                . "Uzzini rekvizītus, statūtu mērķus, amatpersonu skaitu, iesniegtos gada pārskatus un bilanci.",
            'keywords' => "{$seo_p}, {$reg_nr}, {$veids}, rekvizīti, darbības jomas, gada pārskats, bilance",
        ];
        // seo['desc'] un seo['keywords'] neviens nelasa (company.php ņem tikai
        // seo['title']) — īstie lauki ir meta_description un page_keywords, ko
        // uzlika prepare_seo_metadata. Bezpeļņas organizācijām tur bija tikai
        // vispārīgais "aktīvs. Visa UR informācija", tāpēc pārrakstām ar jomām.
        if (!empty($jomas)) {
            $final_d['meta_description'] = "{$seo_p} (reģ. nr. {$reg_nr}) — " . mb_strtolower($veids, 'UTF-8')
                . '. Darbības jomas: ' . mb_strtolower(implode(', ', $jomas), 'UTF-8')
                . '. Statūtu mērķi, gada pārskati un bilance.';
            $final_d['page_keywords'] = $final_d['seo']['keywords'];
            if (isset($final_d['seo_metadata']['open_graph']['description'])) {
                $final_d['seo_metadata']['open_graph']['description'] = $final_d['meta_description'];
            }
        }
        return;
    }

    $desc_a = [];
    foreach (array_slice($all_res['area_of_activity'] ?? [], 0, 2) as $r) {
        if (array_key_exists('nace_code', $r)) {
            $desc_a[] = (string)$r['nace_code'];
        }
    }
    $dr = "";
    foreach ($desc_a as $ac) {
        $ac_cl = str_replace('.', '', $ac);
        if (isset(NACE_MAP[$ac_cl])) {
            $dr .= mb_strtolower(NACE_MAP[$ac_cl], 'UTF-8') . ", ";
        }
    }

    $final_d['seo'] = [
        'title' => "{$seo_p} - Rekvizīti, peļņa, vidējā alga, darbinieku skaits",
        'desc' => "Pilna informācija par uzņēmumu {$seo_p} (reģ. nr {$reg_nr}). " . mb_substr($dr, 0, 100, 'UTF-8') . "... Uzzini uzņēmuma rekvizītus, finanšu bilanci, samaksātos nodokļus un aprēķināto vidējo algu.",
        'keywords' => "{$seo_p}, {$reg_nr}, rekvizīti, apgrozījums, vidējā alga, nodokļi",
    ];
}

/**
 * Galvenā lapas datu būvēšana — ports of build_page_data.
 */
function build_page_data(array $gen_data): array {
    $main = $gen_data['company_main_data'] ?? [];
    $all_res = $gen_data['all_results'] ?? [];
    $memb_ent = $gen_data['member_as_entity_records'] ?? [];
    $reg_nr = (string)($main['regcode'] ?? '');

    $final_d = [
        'search_reg_nr' => $reg_nr,
        'dati_php_rowData' => $main,
        'results' => $all_res,
        'member_as_entity_records' => $memb_ent,
        'company_super_id' => $gen_data['company_super_id'] ?? null,
        'related_businesses_regcodes' => $gen_data['related_businesses_regcodes'] ?? [],
    ];

    // Heavy processing
    $financial_data = [];
    $sankey_years = [];
    try {
        [$statements_info, $fs_ids] = get_financial_statements_info($all_res['financial_statements'] ?? [], $reg_nr);
        [$income_data, $balance_data, $cash_flow_data] = get_financial_details_data([
            'income_statements' => $all_res['income_statements'] ?? [],
            'balance_sheets' => $all_res['balance_sheets'] ?? [],
            'cash_flow_statements' => $all_res['cash_flow_statements'] ?? [],
        ], $fs_ids);
        [$financial_data, $sankey_years] = process_financial_data_for_years($statements_info, $income_data, $balance_data, $cash_flow_data);
    } catch (Throwable $e) {
        error_log("Fin engine error: " . $e->getMessage());
    }

    $final_d['allProcessedData'] = $financial_data;
    $final_d['sankeyAvailableYears'] = $sankey_years;

    // Summary pirms segmenta (segments izmanto finanšu rezultātu)
    $summary_data = prepare_summary_table_data($financial_data, $sankey_years);
    $final_d['summary_table_data_for_js'] = $summary_data;

    // Segments
    $segment = determine_company_segment(!empty($main) ? $main : null, $summary_data);
    if (array_key_exists('type', $main)) {
        $segment['company_type'] = $main['type'];
    }
    $final_d['segment'] = $segment;

    $details = get_company_details_for_panel(!empty($main) ? $main : null, $reg_nr, $segment);
    foreach ($details as $k => $v) $final_d[$k] = $v;

    // Flags
    $final_d['has_summary_data'] = !empty($summary_data) && (!empty($summary_data['UGP']) || !empty($summary_data['UKGP']));
    $final_d['has_financial_charts'] = !empty($final_d['sankeyAvailableYears']);
    // Bilances un rādītāju paneļi neprasa PZA — tiem pietiek ar vienu gadu, kur ir
    // bilance. Ļauj tos rādīt biedrībām, kurām has_financial_charts vienmēr ir false.
    $final_d['has_balance_data'] = false;
    foreach ($financial_data as $yr_types) {
        if (is_array($yr_types['UGP']['balance_data'] ?? null)
            || is_array($yr_types['UKGP']['balance_data'] ?? null)) {
            $final_d['has_balance_data'] = true;
            break;
        }
    }

    if (!array_key_exists('pregenerated_ai', $final_d)) {
        $final_d['pregenerated_ai'] = null;
    }

    $vid = prepare_vid_panel_data($all_res);
    $final_d['vid_panel_data'] = $vid;

    $nace = get_company_nace_info($all_res);
    foreach ($nace as $k => $v) $final_d[$k] = $v;

    // Rādītāju vēsture vienuviet: lieto js_config (grafiki), servera renderētā
    // vērtību tabula financial_ratios_panel.php un FAQ (tāpēc pirms prepare_seo_metadata).
    $final_d['ratios_history'] = prepare_ratios_history_for_charts($final_d['allProcessedData'] ?? []);

    // Datu svaiguma signāls (redzamā rinda faktu panelī + dateModified JSON-LD):
    // ur_data.db faila laiks = pēdējā datu ielāde no data.gov.lv.
    $final_d['data_updated'] = null;
    if (function_exists('reg_ur_db_path')) {
        $ur_db_file = reg_ur_db_path();
        if (is_file($ur_db_file)) {
            $ur_db_mtime = @filemtime($ur_db_file);
            if ($ur_db_mtime !== false) $final_d['data_updated'] = date('Y-m-d', $ur_db_mtime);
        }
    }

    // SEO (iekšēji izsauc prepare_faq_data un iestata faq_list + meta_description)
    $seo = prepare_seo_metadata($final_d);
    $final_d['seo_metadata'] = $seo;

    $rt = prepare_data_for_results_tables($final_d);
    $final_d['processed_tables_for_display'] = $rt;

    $final_d['js_config_json'] = build_js_config($final_d);

    $final_d['generationDate'] = date('d-m-Y');

    // data_version = MI atbilžu keša derīguma atslēga.
    //
    // Bāze ir jaunākais gada pārskata gads, BET ar to vien nepietiek (audits
    // 2026-08-19): maksātnespēja, darbības liegumi vai jauns VID ceturksnis
    // gada pārskata gadu nemaina, tāpēc lapa turpināja rādīt (arī SSR, indeksējamā
    // HTML) MI tekstu, kas apgalvo pretējo faktiem — piem. "nav juridisku risku"
    // uzņēmumam ar tikko reģistrētu maksātnespējas procesu. Notikumu pirkstu
    // nospiedums to izlabo: mainoties jebkuram šo faktu, keša ieraksts noveco.
    $version_year = "";
    if (!empty($summary_data['UGP'])) {
        $years = [];
        foreach ($summary_data['UGP'] as $row) {
            $y = $row['year'] ?? null;
            if ($y !== null && ctype_digit((string)$y)) $years[] = (int)$y;
        }
        if (!empty($years)) $version_year = (string)max($years);
    }
    $ev_res = $final_d['results'] ?? [];
    $ev_sig = [];
    foreach (['insolvency_legal_person_proceeding' => ['proceeding_started_on', 'proceeding_ended_on'],
              'suspensions_prohibitions'           => ['date_from', 'suspension_code'],
              'liquidations'                       => ['date_from', 'liquidation_type'],
              'securing_measures'                  => ['date_from', 'securing_measure_type']] as $tbl => $keys) {
        foreach ((array)($ev_res[$tbl] ?? []) as $row) {
            $bits = [];
            foreach ($keys as $k) $bits[] = (string)($row[$k] ?? '');
            $ev_sig[] = $tbl[0] . ':' . implode('|', $bits);
        }
    }
    // Jaunākais VID ceturksnis: MI promptā ({{VID_CETURKSNI}}) tas ir svaigākais
    // signāls par darbības apjomu un nodokļiem.
    foreach ((array)($ev_res['pdb_samaksato_nodoklu_kopsummas_cet'] ?? []) as $row) {
        $ev_sig[] = 'q:' . (string)($row['Taksacijas_gads_ceturksnis'] ?? '')
            . '/' . (string)($row['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? '');
    }
    sort($ev_sig);
    $final_d['data_version'] = $version_year
        . ($ev_sig ? '|' . substr(sha1(implode(';', $ev_sig)), 0, 10) : '');

    // AI JSON
    $clean_financial_summary = cap_to_5_years(sanitize_for_json($final_d['summary_table_data_for_js'] ?? []));
    $clean_detailed = cap_to_5_years(sanitize_for_json($final_d['allProcessedData'] ?? []));
    $clean_vid = cap_to_5_years(sanitize_for_json($final_d['vid_panel_data'] ?? []));

    $raw_filtered = [];
    foreach ($all_res as $k => $v) {
        // iepirkumi: piecas nejaušas rindas no 2400 līgumiem MI nedod neko labu, un
        // starp tām mēdz būt daudzuzvarētāju vienošanās ar pilnu kopējo summu
        // (100 milj. € par 44 piegādātājiem), ko modelis citētu kā uzņēmuma
        // rādītāju. Tā vietā zemāk padodam korektu apkopojumu ar atrunām.
        if (in_array($k, ['iepirkumi', 'es_fondi', 'bis'], true)) continue;
        if (!empty($v) && is_array($v) && array_is_list($v) && is_array($v[0] ?? null)) {
            $raw_filtered[$k] = array_slice($v, 0, 5);
        } elseif (is_array($v) && !array_is_list($v)) {
            $raw_filtered[$k] = $v;
        }
    }
    require_once __DIR__ . '/iepirkumi_kopsavilkums.php';
    $ai_iepirkumi = reg_iepirkumi_ai_kopsavilkums($all_res['iepirkumi'] ?? []);
    // ES fondos naudai ir trīs dažādas nozīmes atkarībā no lomas — jēlrindās to
    // neredz, un modelis saņemto atbalstu sajauktu ar nopelnītu piegādes līgumu.
    require_once __DIR__ . '/esfondi_kopsavilkums.php';
    $ai_esfondi = reg_esfondi_ai_kopsavilkums($all_res['es_fondi'] ?? []);
    $clean_raw = cap_to_5_years(sanitize_for_json($raw_filtered));

    $reg_date = '';
    if (is_array($main) && array_key_exists('registered', $main)) {
        $reg_date = $main['registered'] ?? '';
    }

    $seg = $final_d['segment'] ?? [];
    $status_v = $seg['status'] ?? 'Nezināms';
    $form_group = $seg['form_group'] ?? 'Cits';
    $financials_v = $seg['financials'] ?? 'Nav datu';

    $ai_dict = [
        'registration_number' => $final_d['search_reg_nr'] ?? '',
        'company_name' => $main['name'] ?? '',
        'company_type' => $main['type'] ?? '',
        'registered_date' => $reg_date,
        'area_of_activity' => [
            'nace_code' => $final_d['nace_code'] ?? '',
            'nace_description' => $final_d['nace_description'] ?? '',
        ],
        'segmentation' => [
            'status' => $status_v,
            'form_group' => $form_group,
            'financials' => $financials_v,
        ],
        'financial_summary' => $clean_financial_summary,
        'detailed_financials_by_year' => $clean_detailed,
        'vid_data' => $clean_vid,
        // 'salary_calculation' uz $final_d nekad netika uzstādīts — īstie dati dzīvo
        // vid_panel_data iekšienē, tāpēc MI šis lauks vienmēr bija tukšs masīvs.
        'salary_calculation_example' => $final_d['vid_panel_data']['salary_calculation_example'] ?? [],
        'raw_database_records' => $clean_raw,
    ];
    // Publiskie iepirkumi MI dodas kā apkopojums, ne jēlrindas (sk. augstāk).
    if ($ai_iepirkumi) $ai_dict['public_procurement'] = $ai_iepirkumi;
    if ($ai_esfondi) $ai_dict['eu_funds'] = $ai_esfondi;

    $reg_num_str = (string)($final_d['search_reg_nr'] ?? '');
    $comp_name_str = (string)($main['name'] ?? '');
    $optimized = optimize_json_for_ai($ai_dict, $reg_num_str, $comp_name_str);

    $ai_json = json_encode($optimized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $final_d['ai_json_data'] = $ai_json === false ? '{}' : $ai_json;

    // Apraksta panelis
    try {
        $c_name = $final_d['companyTitleForHtml'] ?? '';
        $ratios_dict = [];
        $latest_year = null;
        if (!empty($financial_data)) {
            $latest_year = array_key_last($financial_data);
            $fs_types = $financial_data[$latest_year];
            $best_type = isset($fs_types['UGP']) ? 'UGP' : (!empty($fs_types) ? array_key_first($fs_types) : null);
            if ($best_type !== null) {
                $ratios_dict = $fs_types[$best_type]['financial_ratios'] ?? [];
            }
        }
        $final_d['company_description'] = build_financial_description(
            $ratios_dict, $c_name, $reg_date !== '' ? (string)$reg_date : null,
            !empty($financial_data) ? (string)$latest_year : "2024"
        );
    } catch (Throwable $e) {
        error_log("Description Error: " . $e->getMessage());
        $final_d['company_description'] = "";
    }

    // Renderer-līmeņa SEO (title/desc/keywords)
    apply_renderer_seo($final_d, $all_res, $reg_nr);

    return $final_d;
}
