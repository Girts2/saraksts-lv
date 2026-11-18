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
    // 2026-11-18: sankey_prepared ir grafika mezgli/saites (~2,5 KB uz uzņēmumu): income_data
    // rindas un no tām atvasinātas starpsummas (bruto peļņa, EBIT, EBT; izdevumu-pēc-veida
    // pārskatiem arī EBITDA — formulas financial_engine.php prepare_waterfall_sankey_data).
    // Atsevišķi tās MI JSON nav, bet ir izrēķināmas no income_data. source_ids ir iekšējie
    // pārskatu ID. Grafiki abus ņem no allProcessedData (js_config_json), ne no šī JSON.
    "sankey_prepared" => 1, "source_ids" => 1,
];

/**
 * Aprēķinu "troksnis" peldošajos skaitļos: koeficienti nāk ar 15–16 zīmēm aiz
 * komata (4.723509288528992), un katra lieka zīme ir tokens. Apaļojam tikai tos,
 * kam ir vairāk par 6 zīmēm aiz komata — avota vērtības (1493.6 tūkst. EUR,
 * 27163.92 EUR) paliek neskartas. Precizitāte: 4 zīmīgie cipari, bet ne mazāk par
 * 2 zīmēm aiz komata, tāpēc mazas daļas nepazūd (0.005386 → 0.005386,
 * -0.0793576 → -0.07936) un nulle no nenulles nekad nerodas.
 */
function ai_round_float(float $x): float {
    if ($x == 0.0 || !is_finite($x)) return $x;
    // Zīmes aiz komata skaitām ĪSĀKAJĀ atgriezeniskajā pierakstā (%h nav atkarīgs ne no
    // lokāles, ne no precision/serialize_precision). round($x, 6) == $x te neder: PHP 8.4+
    // round(6540455814.958, 6) atdod 6540455814.958001, un avota vērtība tiktu apaļota.
    foreach ([15, 16, 17] as $p) {
        $s = sprintf("%.{$p}h", $x);
        if ((float)$s === $x) break;
    }
    if (stripos($s, 'e') === false) {
        $dot = strpos($s, '.');
        if ($dot === false || strlen($s) - $dot - 1 <= 6) return $x;
    }
    $dec = max(2, 3 - (int)floor(log10(abs($x))));
    if ($dec > 50) return $x;   // sprintf precizitātes griesti (53 zīmes); tik mazu vērtību JSON nav
    // sprintf %F apaļo pēc precīzās binārās vērtības (zend_dtoa) visās PHP versijās. round()
    // PHP 8.3 (servera CLI) vēl dara "pre-rounding" līdz 15 zīmīgajiem: 1600325224.7349954 → .74
    // (8.4+ un pareizi → .73), tātad CLI un tīmekļa izvade atšķirtos.
    return (float)sprintf("%.{$dec}F", $x);
}

/**
 * VID ceturkšņi MI JSON bez dublikātiem (2026-11-18).
 *
 * Kopsummu, IIN, VSAOI un darbinieku skaitu MI saņem vid_data.quarterly_taxes; jēlrindās
 * (pdb_samaksato_nodoklu_kopsummas_cet) tie paši skaitļi bija otrreiz. Vienīgais, kā
 * vid_data nav, ir PVN iemaksa un atmaksa — tās pievienojam TAI PAŠAI ceturkšņa rindai
 * vid_data (arī nulles: "PVN 0" ir atbilde). Jēlrindās paliek tikai ceturkšņi, kuru
 * vid_data NAV (pilnīgi nulliski, ko VID panelis izlaiž, vai aiz 5 rindu robežas), un tie
 * paliek pilni. Tā vienā columns+data tabulā nekad nesajaucas pilnas un saīsinātas rindas
 * (tukšās šūnas MI lasa kā "nav zināms").
 *
 * Atlikumu ņem TIKAI no pirmajām $logs jēlrindām — tā paša loga, ko MI saņēma līdz šim
 * (page_builder griež 5). Citādi, kad DB būs >5 ceturkšņu, jēlrindās parādītos vecāki
 * ceturkšņi, kurus MI agrāk neredzēja (audits 2026-11-18). PVN meklē visās rindās.
 *
 * @param array $quarterly_taxes ceturkšņu saraksts, kas nonāk MI vid_data
 * @param array $raw_rows        VISAS jēlrindas no $all_res (pirms 5 rindu griezuma)
 * @param int   $logs            cik pirmās jēlrindas MI saņem (atlikuma logs)
 * @return array{0: array, 1: array} [quarterly_taxes ar PVN, atlikušās jēlrindas]
 */
function ai_vid_ceturksni_apvienot(array $quarterly_taxes, array $raw_rows, int $logs = 5): array {
    $pvn = ['Taja_skaita_PVN_iemaksa', 'Taja_skaita_PVN_atmaksa'];
    // Tikai ceturkšņi, kas jēlrindās ir VIENREIZ. Atkārtotu (būve to liedz ar UNIQUE
    // reg|ceturksnis, bet avots var mainīties) neapvienojam — visas tā rindas paliek
    // pilnas, lai neviena PVN vērtība nepazustu (audits 2026-11-18).
    $raw_by_label = [];
    $dup = [];
    foreach ($raw_rows as $r) {
        if (!is_array($r)) continue;
        $l = (string)($r['Taksacijas_gads_ceturksnis'] ?? '');
        if ($l === '') continue;
        if (isset($raw_by_label[$l]) || isset($dup[$l])) {
            $dup[$l] = true;
            unset($raw_by_label[$l]);
            continue;
        }
        $raw_by_label[$l] = $r;
    }
    $merged = [];
    foreach ($quarterly_taxes as $i => $q) {
        if (!is_array($q) || !isset($q['Taksacijas_gads_ceturksnis'])) continue;
        $l = (string)$q['Taksacijas_gads_ceturksnis'];
        if (!isset($raw_by_label[$l])) continue;
        $merged[$l] = true;
        foreach ($pvn as $k) {
            if (array_key_exists($k, $raw_by_label[$l])) $quarterly_taxes[$i][$k] = $raw_by_label[$l][$k];
        }
    }
    $atlikums = [];
    foreach (array_slice($raw_rows, 0, max(0, $logs)) as $r) {
        if (is_array($r) && isset($merged[(string)($r['Taksacijas_gads_ceturksnis'] ?? '')])) continue;
        $atlikums[] = $r;
    }
    return [$quarterly_taxes, $atlikums];
}

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
    // VID panelis darbinieku skaitu dod kā int; "0 darbinieku" līdz 2026-11-18 MI redzēja
    // tikai jēlrindās (virkne "0"). Ceturkšņu jēlrindu dublikāti ir izmesti, tāpēc nulli
    // paturam vid_data gada un ceturkšņu rindās.
    'Videjais_nodarbinato_personu_skaits_cilv' => 1,
];

/**
 * Vai masīvs ir "saraksts" (sekvenciālas int atslēgas no 0) — Python list ekvivalents.
 */
function ai_is_list(array $a): bool {
    return array_is_list($a);
}

function optimize_json_for_ai($data, ?string $company_reg_nr = null, ?string $company_name_str = null) {
    $process = null;
    // $round: troksni apaļojam tikai aprēķinātajos laukos. raw_database_records ir avota
    // ieraksti (publiskajā JSON — CC0), tos atstājam kā avotā (piem. atkritumu tonnas ar
    // 9 zīmēm aiz komata; audits 2026-11-18).
    $process = function ($node, bool $round = true) use (&$process, $company_reg_nr, $company_name_str) {
        if (is_array($node) && !ai_is_list($node)) {
            // dict
            $optimized = [];
            foreach ($node as $k => $v) {
                $k = (string)$k;
                if (isset(AI_KEYS_TO_DELETE[$k])) {
                    continue;
                }
                $cleaned = $process($v, $round && $k !== 'raw_database_records');

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
                $ci = $process($item, $round);
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
        if (is_float($node)) return $round ? ai_round_float($node) : $node;
        if (is_string($node) && trim($node) === '') return null;
        return $node;
    };

    return $process($data);
}
