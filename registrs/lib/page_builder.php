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
    ];

    $year_to_use = isset($params[$year]) ? $year : 2025;
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
        $best = $pvn_data[0];
        foreach ($pvn_data as $row) {
            if (($row['Aktivs'] ?? null) === 'ir') { $best = $row; break; }
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

            // Python: if not all([...]): continue  (0/''/None -> izlaiž)
            if (!py_truthy($year_val) || !py_truthy($vsaoi_val) || !py_truthy($employees_val)) continue;

            $year = (int)$year_val;
            $vsaoi_thousands = is_string($vsaoi_val) ? parse_vid_number($vsaoi_val) : (float)$vsaoi_val;
            $employees = (int)parse_vid_number((string)$employees_val);

            $avg_gross = 0.0;
            $salary_details = [];
            // Privātums: <3 darbiniekiem "vidējā alga" faktiski atklāj konkrētas personas algu
            // (GDPR atvasinātie dati) — aprēķinu tad nerāda (2026-08-07 tiesiskais izvērtējums).
            if ($employees >= 3 && $vsaoi_thousands > 0) {
                $avg_gross = (($vsaoi_thousands * 1000) / 0.3409) / $employees / 12;
                $salary_details = calculate_net_salary($avg_gross, $year);
            }
            // '***' = dati VID ir, bet aprēķinu slēpjam (privātums); '—' = aprēķins nav
            // iespējams (VSAOI vērtība 0 vai negatīva — korekcijas rinda).
            $salary_hidden = ($employees < 3 && $vsaoi_thousands > 0);

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
                    'iin_rate_percentage' => fmt_g(($salary_details['iin_rate_used'] ?? 0.20) * 100),
                    'iin_rate_decimal' => rtrim(sprintf('%.3f', $salary_details['iin_rate_used'] ?? 0.20), '0'),
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
            if (!py_truthy($vsaoi_val) || !py_truthy($employees_val)) continue;

            $vsaoi_thousands = (is_int($vsaoi_val) || is_float($vsaoi_val)) ? (float)$vsaoi_val : parse_vid_number((string)$vsaoi_val);
            $employees = (int)parse_vid_number((string)$employees_val);

            $avg_gross = 0.0;
            $salary_details = [];
            $calc_year = 2025;
            if (preg_match('/(\d{4})/', (string)$quarter, $m)) $calc_year = (int)$m[1];

            // Tas pats <3 darbinieku privātuma slieksnis kā gada rindām (skat. augstāk).
            if ($employees >= 3 && $vsaoi_thousands > 0) {
                $avg_gross = (($vsaoi_thousands * 1000) / 0.3409) / $employees / 3;
                $salary_details = calculate_net_salary($avg_gross, $calc_year);
            }
            $salary_hidden = ($employees < 3 && $vsaoi_thousands > 0);

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

    if (!empty($nace_description)) {
        $faq[] = [
            'question' => "Kāda ir {$company_title} galvenā darbības nozare?",
            'answer' => "Uzņēmuma galvenā reģistrētā darbības nozare saskaņā ar NACE klasifikatoru ir: {$nace_description}.",
        ];
    }

    return $faq;
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

    if (in_array($segment['form_group'] ?? null, ['NVO', 'Partija'], true)) {
        $details['show_financial_charts'] = false;
        $details['show_summary_panel'] = false;
    }

    $type_code = get_raw_value($company_main_data, 'type');
    $name_in_quotes = get_raw_value($company_main_data, 'name_in_quotes');
    $full_name = get_raw_value($company_main_data, 'name');
    $prefix_type_codes = ['SIA', 'AS', 'IK', 'ZS', 'ZEM', 'PS', 'PA', 'AROD'];
    $company_title = $search_reg_nr;

    if ($name_in_quotes !== null) {
        $prefix = null;
        if ($type_code !== null && in_array((string)$type_code, $prefix_type_codes, true)) {
            $prefix = ((string)$type_code === 'ZEM') ? 'ZS' : (string)$type_code;
        }
        $company_title = $prefix !== null ? "{$prefix} \"{$name_in_quotes}\"" : "\"{$name_in_quotes}\"";
    } elseif ($full_name !== null) {
        $company_title = str_replace('""', '"', (string)$full_name);
    }
    $details['companyTitleForHtml'] = $company_title;

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
        $details['statusText'] = "Aktīvs";
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
        if ($form_group === 'Komercsabiedrība' && !empty($latest_report)) {
            $year = $latest_report['year'] ?? null;
            $profit = $latest_report['profit'] ?? null;
            $turnover = $latest_report['turnover'] ?? null;
            if ($financials === 'Peļņa' && $profit !== null && $turnover !== null) {
                $meta_description = "{$company_title} ({$search_reg_nr}) jaunākie dati. {$year}. gada peļņa: " . fmt_0f((float)$profit) . " EUR pie " . fmt_0f((float)$turnover) . " EUR apgrozījuma.";
            } elseif ($financials === 'Zaudējumi' && $turnover !== null) {
                $meta_description = "{$company_title} ({$search_reg_nr}) jaunākie dati. {$year}. gada apgrozījums: " . fmt_0f((float)$turnover) . " EUR. Apskatīt pilnu finanšu pārskatu.";
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
            'image' => BASE_DOMAIN . "/assets/img/social_logo.png",
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
            "vatID" => "LV{$search_reg_nr}",
            "identifier" => [
                "@type" => "PropertyValue",
                "propertyID" => "Latvian Company Registration Number",
                "value" => $search_reg_nr,
            ],
        ];

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
        $seo_data['schema_org_json'] = json_encode(sanitize_for_json($clean_schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    if (!empty($faq_list)) {
        $entries = [];
        foreach ($faq_list as $item) {
            $entries[] = ["@type" => "Question", "name" => $item['question'], "acceptedAnswer" => ["@type" => "Answer", "text" => $item['answer']]];
        }
        $faq_schema = ["@context" => "https://schema.org", "@type" => "FAQPage", "mainEntity" => $entries];
        $seo_data['faq_schema_json'] = json_encode($faq_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    return $seo_data;
}

function prepare_data_for_results_tables(array $page_data): array {
    $results = $page_data['results'] ?? [];
    if (empty($results)) return [];

    $EXCLUDE = [
        'officers' => 1, 'beneficial_owners' => 1, 'stockholders' => 1,
        'members_joint_owners' => 1, 'stockholders_joint_owners' => 1,
        'members' => 1, 'pdb_samaksato_nodoklu_kopsummas_cet' => 1,
    ];

    $current_reg = $page_data['search_reg_nr'] ?? '';
    $base_url = BASE_DOMAIN . "/";
    $linkable_cols = [
        'regcode' => 1, 'at_legal_entity_registration_number' => 1, 'depository_registration_number' => 1,
        'legal_entity_registration_number' => 1, 'debtor_registration_number' => 1,
        'delegatedEntityRegistrationNumber' => 1, 'registrationNumber' => 1,
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

                if (isset($linkable_cols[$col]) && ctype_digit($vstr) && strlen($vstr) === 11 && $vstr !== (string)$current_reg) {
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
    if (empty($sankey_years)) return $summary;

    $relevant = $sankey_years;
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
function apply_renderer_seo(array &$final_d, array $all_res, string $reg_nr): void {
    $main = $final_d['dati_php_rowData'] ?? [];
    $t = trim((string)($main['type'] ?? ''));
    $n = trim((string)(($main['name_in_quotes'] ?? null) ?: (($main['name_before_quotes'] ?? null) ?: ($main['name'] ?? ''))));
    $seo_p = $t !== '' ? trim("$t $n") : $n;

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

    // data_version = max UGP gads
    $version_year = "";
    if (!empty($summary_data['UGP'])) {
        $years = [];
        foreach ($summary_data['UGP'] as $row) {
            $y = $row['year'] ?? null;
            if ($y !== null && ctype_digit((string)$y)) $years[] = (int)$y;
        }
        if (!empty($years)) $version_year = (string)max($years);
    }
    $final_d['data_version'] = $version_year;

    // AI JSON
    $clean_financial_summary = cap_to_5_years(sanitize_for_json($final_d['summary_table_data_for_js'] ?? []));
    $clean_detailed = cap_to_5_years(sanitize_for_json($final_d['allProcessedData'] ?? []));
    $clean_vid = cap_to_5_years(sanitize_for_json($final_d['vid_panel_data'] ?? []));

    $raw_filtered = [];
    foreach ($all_res as $k => $v) {
        if (!empty($v) && is_array($v) && array_is_list($v) && is_array($v[0] ?? null)) {
            $raw_filtered[$k] = array_slice($v, 0, 5);
        } elseif (is_array($v) && !array_is_list($v)) {
            $raw_filtered[$k] = $v;
        }
    }
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
        'salary_calculation_example' => $final_d['salary_calculation'] ?? [],
        'raw_database_records' => $clean_raw,
    ];

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
