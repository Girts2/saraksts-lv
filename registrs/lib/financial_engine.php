<?php
// server/lib/financial_engine.php — ports of kods/processing/financial_engine.py
declare(strict_types=1);

require_once __DIR__ . '/formatters.php';

/**
 * Python round() atdarināšana: half-to-even (banker's rounding).
 * Python: round(0.5)=0, round(1.5)=2, round(2.675, 2)=2.67 (float reprezentācijas dēļ).
 */
function py_round(float $v, int $precision = 0): float {
    $mult = 10 ** $precision;
    $x = $v * $mult;
    $floor = floor($x);
    $diff = $x - $floor;
    // Salīdzinām ar 0.5 ar nelielu toleranci pret float troksni tāpat kā Python (kurš strādā ar īsto float vērtību)
    if (abs($diff - 0.5) < PHP_FLOAT_EPSILON * max(1.0, abs($x))) {
        // half-to-even
        $rounded = (fmod($floor, 2.0) == 0.0) ? $floor : $floor + 1.0;
    } elseif ($diff < 0.5) {
        $rounded = $floor;
    } else {
        $rounded = $floor + 1.0;
    }
    return $rounded / $mult;
}

/**
 * Python f"{x:,.2f}" ar atstarpi kā tūkstošu atdalītāju.
 */
function py_format_2f(float $v): string {
    return number_format($v, 2, '.', ' ');
}

/**
 * Aprēķina galvenos finanšu rādītājus. $income_data/$balance_data — asociatīvi masīvi (rindas) vai null.
 */
function calculate_financial_ratios($income_data, $balance_data): array {
    if (!is_array($income_data) || !is_array($balance_data)) {
        return [];
    }

    $get_val = function ($row, $key) {
        $v = prepare_for_chart(get_raw_value($row, $key));
        return $v !== null ? $v : 0.0;
    };

    $i = [];
    foreach (array_keys($income_data) as $k) { $i[$k] = $get_val($income_data, $k); }
    $b = [];
    foreach (array_keys($balance_data) as $k) { $b[$k] = $get_val($balance_data, $k); }
    // Nodrošinām atslēgas, ko Python piekļūst tieši (b['x'] mestu KeyError, ja kolonnas nav —
    // bet SQLite tabulās kolonnas vienmēr ir; drošībai 0.0)
    foreach (['total_current_assets','current_liabilities','inventories','total_assets','equity','non_current_liabilities'] as $k) {
        if (!isset($b[$k])) $b[$k] = 0.0;
    }
    foreach (['net_income','net_turnover','income_before_income_taxes','interest_expenses'] as $k) {
        if (!isset($i[$k])) $i[$k] = 0.0;
    }

    $ratios = [];

    $calc = function (string $name, string $formula_str, callable $logic, array $components) use (&$ratios) {
        try {
            $value = $logic();
            if ($value === null || !is_numeric($value) || !is_finite((float)$value)) {
                $value = null;
            } else {
                $value = (float)$value;
            }
        } catch (DivisionByZeroError|TypeError $e) {
            $value = null;
        }

        $formula_parts = explode(' / ', $formula_str);
        if (count($formula_parts) === 2) {
            [$num, $den] = $formula_parts;
            $formula_html = '<math display="inline" style="font-size: 1.1em;"><mfrac><mrow><mtext>' . $num . '</mtext></mrow><mrow><mtext>' . $den . '</mtext></mrow></mfrac></math>';
        } else {
            $formula_html = $formula_str;
        }

        $all_numeric = count($components) === 2;
        foreach ($components as $c) {
            if (!is_int($c) && !is_float($c)) { $all_numeric = false; break; }
        }
        if ($value !== null && $all_numeric) {
            $formatted_num = py_format_2f((float)$components[0]);
            $formatted_den = py_format_2f((float)$components[1]);
            $calculation_html = '<math display="inline" style="font-size: 1.1em;"><mfrac><mrow><mtext>' . $formatted_num . '</mtext></mrow><mrow><mtext>' . $formatted_den . '</mtext></mrow></mfrac></math>';
        } else {
            $calculation_html = 'aprēķins nav iespējams';
        }

        $comp_assoc = [];
        $letters = ['A', 'B', 'C'];
        foreach ($components as $idx => $c) {
            if ($idx > 2) break;
            $comp_assoc[$letters[$idx]] = $c;
        }

        $ratios[$name] = [
            'value' => $value,
            'formula' => $formula_html,
            'calculation' => $calculation_html,
            'components' => $comp_assoc,
        ];
    };

    $div = function (float $a, float $b): ?float {
        if ($b == 0.0) throw new DivisionByZeroError();
        return $a / $b;
    };

    // 1. Likviditāte
    $calc('current_ratio', "Apgrozāmie Līdzekļi / Īstermiņa Saistības",
        fn() => $div($b['total_current_assets'], $b['current_liabilities']),
        [$b['total_current_assets'], $b['current_liabilities']]);

    $quick_assets = $b['total_current_assets'] - ($b['inventories'] ?? 0.0);
    $calc('quick_ratio', "(Apgrozāmie Līdzekļi - Krājumi) / Īstermiņa Saistības",
        fn() => $div($quick_assets, $b['current_liabilities']),
        [$quick_assets, $b['current_liabilities']]);

    // 2. Rentabilitāte
    $calc('net_profit_margin', "Tīrā Peļņa / Neto Apgrozījums",
        fn() => $div($i['net_income'], $i['net_turnover']),
        [$i['net_income'], $i['net_turnover']]);

    $calc('roa', "Tīrā Peļņa / Aktīvi Kopā",
        fn() => $div($i['net_income'], $b['total_assets']),
        [$i['net_income'], $b['total_assets']]);

    // ROE: rēķina arī pie negatīva pašu kapitāla (kā oriģinālā — grafiks vienmēr zīmējas);
    // maldinošos teikumus Apraksta tekstā šādā gadījumā aizstāj negatīva kapitāla brīdinājums.
    $calc('roe', "Tīrā Peļņa / Pašu Kapitāls",
        fn() => $div($i['net_income'], $b['equity']),
        [$i['net_income'], $b['equity']]);

    // 3. Maksātspēja
    $total_liabilities = $b['current_liabilities'] + $b['non_current_liabilities'];
    $calc('debt_to_equity', "Saistības Kopā / Pašu Kapitāls",
        fn() => $div($total_liabilities, $b['equity']),
        [$total_liabilities, $b['equity']]);
    // Negatīva pašu kapitāla pazīme — patiess augsts risks (tehniskā maksātnespēja).
    $ratios['debt_to_equity']['negative_equity'] = ($b['equity'] < 0);

    $ebit = $i['income_before_income_taxes'] + $i['interest_expenses'];
    $calc('interest_coverage', "EBIT / Procentu Izmaksas",
        fn() => $div($ebit, $i['interest_expenses']),
        [$ebit, $i['interest_expenses']]);

    // 4. Efektivitāte
    $calc('asset_turnover', "Neto Apgrozījums / Aktīvi Kopā",
        fn() => $div($i['net_turnover'], $b['total_assets']),
        [$i['net_turnover'], $b['total_assets']]);

    // 5. Altman Z'-Score (1983)
    try {
        if ($b['total_assets'] > 0) {
            $A = ($b['total_current_assets'] - $b['current_liabilities']) / $b['total_assets'];
            $B = $b['equity'] / $b['total_assets'];
            $C = $ebit / $b['total_assets'];
            $D = $total_liabilities > 0 ? $b['equity'] / $total_liabilities : 0.0;
            $E = $i['net_turnover'] / $b['total_assets'];

            $z_score = 0.717 * $A + 0.847 * $B + 3.107 * $C + 0.420 * $D + 0.998 * $E;
            $z_calc_str = sprintf("0.717*%.2f + 0.847*%.2f + 3.107*%.2f + 0.420*%.2f + 0.998*%.2f", $A, $B, $C, $D, $E);

            $ratios['altman_z_score'] = [
                'value' => $z_score,
                'formula' => "Z' (1983) = 0.717*A + 0.847*B + 3.107*C + 0.420*D + 0.998*E",
                'calculation' => $z_calc_str,
                'components' => ['A' => $A, 'B' => $B, 'C' => $C, 'D' => $D, 'E' => $E],
            ];
        } else {
            throw new DivisionByZeroError();
        }
    } catch (DivisionByZeroError|TypeError $e) {
        $ratios['altman_z_score'] = ['value' => null, 'formula' => "Z aprēķins nav iespējams", 'calculation' => 'Nav datu'];
    }

    // 6. ROCE: rēķina arī pie negatīva ieguldītā kapitāla (kā oriģinālā — grafiks vienmēr zīmējas)
    $iegulditais_kapitals = $b['total_assets'] - $b['current_liabilities'];
    $calc('roce', "EBIT / Ieguldītais Kapitāls",
        fn() => $div($ebit, $iegulditais_kapitals),
        [$ebit, $iegulditais_kapitals]);

    return $ratios;
}

/**
 * Sankey "ūdenskrituma" dati. $income_row — asociatīvs masīvs vai null.
 */
function prepare_waterfall_sankey_data($income_row, $rounding_multiplier = 1): ?array {
    if (!is_array($income_row) || empty($income_row)) {
        return null;
    }

    $get_val = function (string $key) use ($income_row, $rounding_multiplier): float {
        $raw = prepare_for_chart($income_row[$key] ?? null);
        $raw = $raw !== null ? $raw : 0.0;
        return $raw * $rounding_multiplier;
    };

    $use_by_function = $get_val('by_function_cost_of_goods_sold') != 0.0 || $get_val('by_function_gross_profit') != 0.0;
    $nodes = [];
    $links = [];
    $nodes_added = [];

    $add_node = function (string $id, string $name) use (&$nodes, &$nodes_added) {
        if (!isset($nodes_added[$id])) {
            $nodes[] = ['id' => $id, 'name' => $name];
            $nodes_added[$id] = true;
        }
    };

    $add_link = function (string $source, string $target, float $value, array $node_map) use (&$links, $add_node) {
        if ($value == 0.0) return;
        $add_node($source, $node_map[$source] ?? $source);
        $add_node($target, $node_map[$target] ?? $target);
        $links[] = [
            'source' => $source, 'target' => $target,
            'value' => abs(py_round($value)), 'originalValue' => py_round($value),
            'isLoss' => $value < 0,
        ];
    };

    $net_income_final = $get_val('net_income');

    if ($use_by_function) {
        $net_turnover = $get_val('net_turnover');
        $cogs = $get_val('by_function_cost_of_goods_sold');
        $gross_profit = $get_val('by_function_gross_profit');
        if ($gross_profit == 0.0 && ($net_turnover != 0.0 || $cogs != 0.0)) {
            $gross_profit = $net_turnover - $cogs;
        }
        $selling_exp = $get_val('by_function_selling_expenses');
        $admin_exp = $get_val('by_function_administrative_expenses');
        $other_op_rev = $get_val('by_function_other_operating_revenues');
        $other_op_exp = $get_val('other_operating_expenses');
        $interest_revenue = $get_val('other_interest_revenues');
        $interest_exp = $get_val('interest_expenses');
        $tax = $get_val('provision_for_income_taxes');
        $ebit = $gross_profit - $selling_exp - $admin_exp - $other_op_exp + $other_op_rev;
        $node_map = [
            "net_turnover_source" => "Neto Apgrozījums", "gross_profit_calc" => "Bruto Peļņa",
            "cogs_exp" => "Pārdotās prod. pašizmaksa", "selling_exp" => "Pārdošanas izmaksas",
            "admin_exp" => "Administratīvās izmaksas", "other_op_rev_source" => "Pārējie saimn. darb. ieņēmumi",
            "other_op_exp_exp" => "Pārējās saimn. darb. izmaksas", "ebit_calc" => "Peļņa pirms proc. un nodokļiem (EBIT)",
            "interest_rev_source" => "Procentu Ieņēmumi", "interest_exp" => "Procentu Izmaksas",
            "ebt_calc" => "Peļņa pirms nodokļiem (EBT)", "tax_exp" => "UIN Nodoklis",
            "net_income_final" => "Tīrā Peļņa" . ($net_income_final < 0 ? ' (Zaudējumi)' : ''),
        ];
        $add_link("net_turnover_source", "cogs_exp", $cogs, $node_map);
        $add_link("net_turnover_source", "gross_profit_calc", $gross_profit, $node_map);
        $add_link("gross_profit_calc", "selling_exp", $selling_exp, $node_map);
        $add_link("gross_profit_calc", "admin_exp", $admin_exp, $node_map);
        $add_link("gross_profit_calc", "other_op_exp_exp", $other_op_exp, $node_map);
        $add_link("gross_profit_calc", "ebit_calc", $ebit, $node_map);
        if ($other_op_rev != 0.0) $add_link("other_op_rev_source", "ebit_calc", $other_op_rev, $node_map);
        $add_link("ebit_calc", "ebt_calc", $ebit, $node_map);
        if ($interest_revenue != 0.0) $add_link("interest_rev_source", "ebt_calc", $interest_revenue, $node_map);
        $add_link("ebt_calc", "interest_exp", $interest_exp, $node_map);
        $add_link("ebt_calc", "tax_exp", $tax, $node_map);
        $add_link("ebt_calc", "net_income_final", $net_income_final, $node_map);
    } else {
        $net_turnover = $get_val('net_turnover');
        $other_op_revenue = $get_val('by_nature_other_operating_revenues');
        $inventory_change = $get_val('by_nature_inventory_change');
        $material_costs = $get_val('by_nature_material_expenses');
        $labour_costs = $get_val('by_nature_labour_expenses');
        $other_op_exp = $get_val('other_operating_expenses');
        $depreciation = $get_val('by_nature_depreciation_expenses');
        $interest_revenue = $get_val('other_interest_revenues');
        $interest_exp = $get_val('interest_expenses');
        $tax = $get_val('provision_for_income_taxes');
        $total_op_revenue = $net_turnover + $other_op_revenue + $inventory_change;
        $total_op_costs = $material_costs + $labour_costs + $other_op_exp;
        $ebitda = $total_op_revenue - $total_op_costs;
        $ebit = $total_op_revenue - $total_op_costs - $depreciation;
        $node_map = [
            "net_turnover_source" => "Neto Apgrozījums", "other_rev_source" => "Pārējie Ieņēmumi & Krāj. Izm.",
            "total_rev_calc" => "Kopējie Darb. Ieņēmumi", "material_costs_exp" => "Materiālu Izmaksas",
            "labour_costs_exp" => "Personāla Izmaksas", "other_op_costs_exp" => "Pārējās Operat. Izmaksas",
            "inventory_cost_exp" => "Krājumu izmaiņa (neto izmaksa)",
            "ebitda_calc" => "EBITDA", "depreciation_exp" => "Amortizācija (D&A)", "ebit_calc" => "EBIT",
            "interest_rev_source" => "Procentu Ieņēmumi", "interest_exp" => "Procentu Izmaksas",
            "ebt_calc" => "EBT", "tax_exp" => "UIN Nodoklis",
            "net_income_final" => "Tīrā Peļņa" . ($net_income_final < 0 ? ' (Zaudējumi)' : ''),
        ];
        $add_link("net_turnover_source", "total_rev_calc", $net_turnover, $node_map);
        // Pārējie ieņēmumi + krājumu izmaiņa: pozitīvi = ieplūde ieņēmumu pusē.
        // NEGATĪVI (tirgotājs pārdod no krājumiem, piem. Latvijas Gāze 2025: −60,2M)
        // = pēc būtības pārdotā pašizmaksa → rādām IZDEVUMU pusē kā joslu, citādi
        // diagramma zīmē milzu "ieplūdi" ar |vērtību| un ieņēmumu puse izskatās
        // daudzkārt lielāka par izdevumu pusi ("kur palika nauda?"). Aritmētiku
        // (total_op_revenue, EBITDA) tas nemaina — mainās tikai plūsmas virziens.
        $other_rev_net = $other_op_revenue + $inventory_change;
        if ($other_rev_net >= 0) {
            $add_link("other_rev_source", "total_rev_calc", $other_rev_net, $node_map);
        } else {
            $add_link("total_rev_calc", "inventory_cost_exp", -$other_rev_net, $node_map);
        }
        $add_link("total_rev_calc", "material_costs_exp", $material_costs, $node_map);
        $add_link("total_rev_calc", "labour_costs_exp", $labour_costs, $node_map);
        $add_link("total_rev_calc", "other_op_costs_exp", $other_op_exp, $node_map);
        $add_link("total_rev_calc", "ebitda_calc", $ebitda, $node_map);
        $add_link("ebitda_calc", "depreciation_exp", $depreciation, $node_map);
        $add_link("ebitda_calc", "ebit_calc", $ebit, $node_map);
        $add_link("ebit_calc", "ebt_calc", $ebit, $node_map);
        if ($interest_revenue != 0.0) $add_link("interest_rev_source", "ebt_calc", $interest_revenue, $node_map);
        $add_link("ebt_calc", "interest_exp", $interest_exp, $node_map);
        $add_link("ebt_calc", "tax_exp", $tax, $node_map);
        $add_link("ebt_calc", "net_income_final", $net_income_final, $node_map);
    }

    return ['nodes' => $nodes, 'links' => $links];
}

/**
 * Pārskatu info: [statements_info(id=>row ar int year), fs_ids secībā].
 */
function get_financial_statements_info(array $fs_rows, string $reg_nr): array {
    if (empty($fs_rows)) return [[], []];
    $company_fs = [];
    foreach ($fs_rows as $row) {
        if ((string)($row['legal_entity_registration_number'] ?? '') !== (string)$reg_nr) continue;
        $year = $row['year'] ?? null;
        if ($year === null || !is_numeric((string)$year)) continue;  // to_numeric + dropna
        $row['year'] = (int)(float)$year;
        $company_fs[] = $row;
    }
    if (empty($company_fs)) return [[], []];
    // Stabila kārtošana pēc gada augoši (PHP 8 usort ir stabils)
    usort($company_fs, fn($a, $b) => $a['year'] <=> $b['year']);

    $statements_info = [];
    $ids = [];
    foreach ($company_fs as $row) {
        $sid = get_raw_value($row, 'id');
        if ($sid !== null) {
            $sid_str = (string)$sid;
            if (!isset($statements_info[$sid_str])) {
                $statements_info[$sid_str] = $row;
                $ids[] = $sid_str;
            }
        }
    }
    return [$statements_info, $ids];
}

/**
 * Detaļu rindas pa statement_id: [income, balance, cash_flow] katrs id=>row.
 */
function get_financial_details_data(array $dataframes, array $fs_ids): array {
    $income = []; $balance = []; $cash = [];
    if (empty($fs_ids)) return [$income, $balance, $cash];
    $idset = array_flip(array_map('strval', $fs_ids));
    foreach ([['income_statements', &$income], ['balance_sheets', &$balance], ['cash_flow_statements', &$cash]] as [$table, &$dict]) {
        $rows = $dataframes[$table] ?? [];
        foreach ($rows as $row) {
            $sid = (string)($row['statement_id'] ?? '');
            if ($sid !== '' && isset($idset[$sid]) && !isset($dict[$sid])) {  // drop_duplicates keep='first'
                $dict[$sid] = $row;
            }
        }
        unset($dict);
    }
    return [$income, $balance, $cash];
}

/**
 * Apstrādā finanšu datus pa gadiem: [all_processed_data (year=>type=>...), sankey_years].
 */
function process_financial_data_for_years(array $statements_info, array $income_data, array $balance_data, array $cash_flow_data): array {
    $all = [];
    if (empty($statements_info)) return [[], []];

    $fs_ids = array_keys($statements_info);
    // Stabili kārtojam pēc gada
    usort($fs_ids, fn($a, $b) => ($statements_info[$a]['year'] ?? 0) <=> ($statements_info[$b]['year'] ?? 0));

    foreach ($fs_ids as $fs_id) {
        $info = $statements_info[$fs_id];
        $year = $info['year'] ?? null;
        if ($year === null) continue;

        $cur_income = $income_data[$fs_id] ?? null;
        $cur_balance = $balance_data[$fs_id] ?? null;
        $cur_cash = $cash_flow_data[$fs_id] ?? null;

        if (!is_array($cur_income) && !is_array($cur_balance)) continue;

        $rounding_multiplier = 1;
        $rounded_str = strtoupper(trim((string)($info['rounded_to_nearest'] ?? 'ONES')));
        if ($rounded_str === 'THOUSANDS') $rounding_multiplier = 1000;
        elseif ($rounded_str === 'MILLIONS') $rounding_multiplier = 1000000;

        $report_type = strtoupper(trim((string)($info['source_type'] ?? 'UGP')));
        // Python: str(None) -> 'NONE' pēc upper; SQLite null -> ?? 'UGP'. Saskaņā ar Python `info_entry.get('source_type','UGP')`
        // null vērtība .get atgriež None -> str -> 'None' -> 'NONE'. Atkārtojam:
        if (array_key_exists('source_type', $info) && $info['source_type'] === null) {
            $report_type = 'NONE';
        }

        $year_data_output = [
            'currency' => trim((string)($info['currency'] ?? 'EUR')),
            'rounded_to_nearest' => $rounded_str,
            'employees' => get_raw_value($info, 'employees'),
        ];
        // Python: currency null -> str(None)='None'
        if (array_key_exists('currency', $info) && $info['currency'] === null) {
            $year_data_output['currency'] = 'None';
        }

        $source_ids = [
            'financial_statement_id' => $fs_id,
            'income_statement_id' => get_raw_value($cur_income, 'id') ?? get_raw_value($cur_income, 'statement_id'),
            'balance_sheet_id' => get_raw_value($cur_balance, 'id') ?? get_raw_value($cur_balance, 'statement_id'),
            'cash_flow_statement_id' => get_raw_value($cur_cash, 'id') ?? get_raw_value($cur_cash, 'statement_id'),
        ];
        foreach ($source_ids as $k => $v) {
            if ($v !== null) $source_ids[$k] = (string)$v;
        }

        if (!isset($all[$year])) $all[$year] = [];
        $valid_type_key = $report_type !== 'UKGP' ? 'UGP' : 'UKGP';

        $sankey_prepared = null;
        if (is_array($cur_income)) {
            $sankey_prepared = prepare_waterfall_sankey_data($cur_income, $rounding_multiplier);
        }

        $financial_ratios = calculate_financial_ratios($cur_income, $cur_balance);

        $all[$year][$valid_type_key] = [
            'fs_data' => array_merge($year_data_output, ['type_from_db' => $report_type]),
            'income_data' => is_array($cur_income) ? $cur_income : null,
            'balance_data' => is_array($cur_balance) ? $cur_balance : null,
            'cash_flow_data' => is_array($cur_cash) ? $cur_cash : null,
            'sankey_prepared' => $sankey_prepared,
            'financial_ratios' => $financial_ratios,
            'source_ids' => $source_ids,
        ];
    }

    ksort($all, SORT_NUMERIC);

    $sankey_years = [];
    foreach ($all as $year => $types) {
        $has = false;
        foreach (['UGP', 'UKGP'] as $t) {
            if (isset($types[$t]['sankey_prepared']['links']) && !empty($types[$t]['sankey_prepared']['links'])) {
                $has = true;
            }
        }
        if ($has) $sankey_years[] = $year;
    }
    sort($sankey_years, SORT_NUMERIC);

    return [$all, $sankey_years];
}
