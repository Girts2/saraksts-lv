<?php
// server/lib/ai_json.php — ports of kods/processing/ai_json.py (optimize_json_for_ai)
// convert_to_serializable PHP pusē nav vajadzīgs (PDO dod native tipus), bet
// NaN drošībai izmantojam sanitize_for_json no formatters.php.
declare(strict_types=1);

const AI_KEYS_TO_DELETE = [
    "statement_id" => 1, "file_id" => 1, "id" => 1, "legal_entity_registration_number" => 1,
    "at_legal_entity_registration_number" => 1, "regcode" => 1,
    "last_modified_at" => 1, "created_at" => 1, "registered_on" => 1, "year_started_on" => 1, "year_ended_on" => 1,
    "name_before_quotes" => 1, "name_in_quotes" => 1, "name_after_quotes" => 1, "without_quotes" => 1,
    "regtype_text" => 1, "type_text" => 1, "source_schema" => 1, "source_type" => 1, "rounded_to_nearest" => 1,
    "equity_capital_type_text" => 1, "latvian_identity_number_masked" => 1,
    "formula" => 1, "calculation" => 1, "sankey_data" => 1, "components" => 1, "UKGP" => 1,
];

const AI_KEY_SHORTENER = [
    "by_nature_inventory_change" => "inv_chg",
    "by_nature_long_term_investment_expenses" => "lt_inv_exp",
    "by_nature_other_operating_revenues" => "oth_op_rev",
    "by_nature_material_expenses" => "mat_exp",
    "by_nature_labour_expenses" => "labour_exp",
    "by_nature_depreciation_expenses" => "dep_exp",
    "by_function_cost_of_goods_sold" => "cogs",
    "by_function_gross_profit" => "gross_prof",
    "by_function_selling_expenses" => "sell_exp",
    "by_function_administrative_expenses" => "admin_exp",
    "by_function_other_operating_revenues" => "oth_op_rev",
    "other_operating_expenses" => "oth_op_exp",
    "cfo_dm_cash_received_from_customers" => "cfo_dm_cust",
    "cfo_dm_cash_paid_to_suppliers_employees" => "cfo_dm_supp",
    "cfo_dm_other_cash_received_paid" => "cfo_dm_oth",
    "cfo_dm_operating_cash_flow" => "cfo_dm_op",
    "cfo_dm_interest_paid" => "cfo_dm_int",
    "cfo_dm_income_taxes_paid" => "cfo_dm_tax",
    "cfo_dm_extra_items_cash_flow" => "cfo_dm_extra",
    "cfo_dm_net_operating_cash_flow" => "cfo_dm_net",
    "cfo_im_income_before_income_taxes" => "cfo_im_ebt",
    "cfo_im_income_before_changes_in_working_capital" => "cfo_im_ebwc",
    "cfo_im_operating_cash_flow" => "cfo_im_op",
    "cfo_im_interest_paid" => "cfo_im_int",
    "cfo_im_income_taxes_paid" => "cfo_im_tax",
    "cfo_im_extra_items_cash_flow" => "cfo_im_ext",
    "cfo_im_net_operating_cash_flow" => "cfo_im_net",
    "cfi_acquisition_of_stocks_shares" => "cfi_acq_stk",
    "cfi_sale_proceeds_from_stocks_shares" => "cfi_sal_stk",
    "cfi_acquisition_of_fixed_assets_intangible_assets" => "cfi_acq_fa",
    "cfi_sale_proceeds_from_fixed_assets_intangible_assets" => "cfi_sal_fa",
    "cfi_loans_made" => "cfi_ln_md",
    "cfi_repayments_of_loans_received" => "cfi_ln_rcv",
    "cfi_interest_received" => "cfi_int_rcv",
    "cfi_dividends_received" => "cfi_div_rcv",
    "cfi_net_investing_cash_flow" => "cfi_net",
    "cff_proceeds_from_stocks_bonds_issuance_or_contributed_capital" => "cff_iss_cap",
    "cff_loans_received" => "cff_ln_rcv",
    "cff_subsidies_grants_donations_received" => "cff_sub_rcv",
    "cff_repayments_of_loans_made" => "cff_ln_repay",
    "cff_repayments_of_lease_obligations" => "cff_ls_repay",
    "cff_dividends_paid" => "cff_div_pd",
    "cff_net_financing_cash_flow" => "cff_net",
    "total_current_assets" => "cur_assets",
    "total_non_current_assets" => "non_cur_assets",
    "total_assets" => "assets",
    "current_liabilities" => "cur_liab",
    "non_current_liabilities" => "non_cur_liab",
    "total_equities" => "equities",
];

/**
 * Atslēgas, kurām nulle ir ATBILDE, nevis tukšums.
 *
 * Optimizācija izmet visas nulles (tokenu ekonomija). Sarakstos, ko pārveido par
 * "columns + data", izmestā atslēga rindā atgriežas kā `null` — un MI to lasa kā
 * "nav zināms", nevis "nulle". Tā gada kopsavilkumā uzņēmums ar peļņu tieši
 * 0 EUR MI pusē kļuva par "peļņa nav zināma" (parauga 3 309 lapās 700 šādu šūnu).
 * Šīm atslēgām nulli paturam; "columns + data" izklājumā tas pat ir īsāks (`0`
 * pret `null`).
 */
const AI_KEYS_KEEP_ZERO = [
    'profit' => 1, 'turnover' => 1, 'employees' => 1,
    'net_income' => 1, 'net_turnover' => 1,
];

/**
 * Vai masīvs ir "saraksts" (sekvenciālas int atslēgas no 0) — Python list ekvivalents.
 */
function ai_is_list(array $a): bool {
    return array_is_list($a);
}

function optimize_json_for_ai($data, ?string $company_reg_nr = null, ?string $company_name_str = null) {
    $process = null;
    $process = function ($node) use (&$process, $company_reg_nr, $company_name_str) {
        if (is_array($node) && !ai_is_list($node)) {
            // dict
            $optimized = [];
            foreach ($node as $k => $v) {
                $k = (string)$k;
                if (isset(AI_KEYS_TO_DELETE[$k])) {
                    continue;
                }
                $cleaned = $process($v);

                $is_empty = ($cleaned === null || $cleaned === "" || (is_array($cleaned) && count($cleaned) === 0));
                $is_zero = (is_int($cleaned) || is_float($cleaned)) && $cleaned == 0 && !is_bool($cleaned)
                    && !isset(AI_KEYS_KEEP_ZERO[$k]);

                if ($k !== 'registration_number' && $k !== 'company_name') {
                    if ($company_reg_nr !== null && is_scalar($cleaned) && !is_bool($cleaned) && (string)$cleaned === (string)$company_reg_nr && is_string($cleaned)) {
                        continue;
                    }
                    if (is_string($cleaned) && is_string($company_name_str)
                        && mb_strtolower($cleaned, 'UTF-8') === mb_strtolower($company_name_str, 'UTF-8')) {
                        continue;
                    }
                }

                if (!$is_empty && !$is_zero) {
                    $short_k = AI_KEY_SHORTENER[$k] ?? $k;
                    $optimized[$short_k] = $cleaned;
                }
            }
            return $optimized;
        }

        if (is_array($node)) {
            // list
            $cleaned_list = [];
            foreach ($node as $item) {
                $ci = $process($item);
                $is_empty = ($ci === null || $ci === "" || (is_array($ci) && count($ci) === 0));
                if (!$is_empty) $cleaned_list[] = $ci;
            }
            if (empty($cleaned_list)) return [];

            // Ja visi elementi ir dict -> "Header + Array"
            $all_dicts = true;
            foreach ($cleaned_list as $x) {
                if (!is_array($x) || ai_is_list($x)) { $all_dicts = false; break; }
            }
            if ($all_dicts) {
                $all_keys = [];
                foreach ($cleaned_list as $item) {
                    foreach (array_keys($item) as $k) {
                        if (!in_array($k, $all_keys, true)) $all_keys[] = $k;
                    }
                }
                $data_rows = [];
                foreach ($cleaned_list as $item) {
                    $row = [];
                    foreach ($all_keys as $k) $row[] = $item[$k] ?? null;
                    $data_rows[] = $row;
                }
                return ['columns' => $all_keys, 'data' => $data_rows];
            }
            return $cleaned_list;
        }

        // skalāri
        if ($node === null) return null;
        if (is_float($node) && is_nan($node)) return null;
        if (is_string($node) && trim($node) === '') return null;
        return $node;
    };

    return $process($data);
}
