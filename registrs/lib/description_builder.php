<?php
// server/lib/description_builder.php — ports of kods/processing/description_builder.py
declare(strict_types=1);

require_once __DIR__ . '/descriptions.php';

/**
 * Deterministiskā izvēle zelta testiem: ja REG_DETERMINISTIC vide iestatīta,
 * vienmēr ņem pirmo variantu (etalons ģenerēts ar to pašu likumu).
 */
function pick_variant(array $list) {
    if (empty($list)) return null;
    if (getenv('REG_DETERMINISTIC')) {
        return $list[0];
    }
    return $list[array_rand($list)];
}

function get_age_text(?string $registered_date_str, string $company_name): string {
    if ($registered_date_str === null || $registered_date_str === '') return "";
    $reg_year = (int)substr((string)$registered_date_str, 0, 4);
    if ($reg_year <= 0) return "";
    $age = (int)date('Y') - $reg_year;
    foreach (AGE_DESCRIPTIONS as [$min_age, $max_age, $desc_list]) {
        if ($age >= $min_age && $age <= $max_age) {
            $chosen = pick_variant($desc_list);
            return "$company_name ir $chosen";
        }
    }
    return "";
}

/**
 * Sliekšņu izvērtēšana: $thresholds = [[limit, cat, color], ...].
 */
/**
 * Jēgpilns secinājums, kad finanšu vērtība iziet ārpus standarta diapazona (negatīvs pašu
 * kapitāls vai zaudējumi). Parastie sliekšņi šādos gadījumos ir maldinoši (piem. pie negatīva
 * kapitāla ROE zīme apgriežas un izskatās "laba"; negatīva marža nozīmē ZAUDĒJUMUS, ne tikai
 * "vāju peļņu"). Šeit lasītājam paskaidrojam, kas ar uzņēmumu notiek PATIESĪBĀ.
 * @return array{0:string,1:string}|null  [teikums (sākas ar mazo burtu), krāsa] vai null (parasts)
 */
function special_finance_note(string $key, ?float $val, array $ratios): ?array {
    $neg_equity = !empty($ratios['debt_to_equity']['negative_equity']);

    // 1) NEGATĪVS PAŠU KAPITĀLS — ROE / D-E / ROCE saucējs ir negatīvs, rādītājs maldina.
    if ($neg_equity) {
        switch ($key) {
            case 'roe':
                return ["īpašnieku ieguldītais kapitāls ir zaudēts — uzkrātie zaudējumi to ir pilnībā \"apēduši\", tāpēc atdevi no pašu kapitāla vairs nav iespējams gūt", 'red'];
            case 'debt_to_equity':
                return ["uzņēmuma saistības pārsniedz visus tā aktīvus — parādus vairs nesedz uzņēmuma manta, un kreditori faktiski finansē visu uzņēmumu", 'red'];
            case 'roce':
                return ["uzņēmums darbojas ar izsmeltu kapitāla bāzi, tāpēc ieguldītā kapitāla atdeve neatspoguļo efektivitāti, bet gan dziļas finanšu grūtības", 'red'];
        }
    }

    // 2) ZAUDĒJUMI — negatīva rentabilitāte nav "vāja peļņa", bet reāli zaudējumi.
    if ($val !== null && $val < 0) {
        switch ($key) {
            case 'net_profit_margin':
                return ["uzņēmums strādā ar zaudējumiem — izdevumi pārsniedz ieņēmumus, tāpēc peļņas vietā veidojas zaudējumi", 'red'];
            case 'roa':
                return ["uzņēmuma aktīvi šobrīd rada zaudējumus, nevis peļņu — resursi netiek izmantoti vērtības radīšanai", 'red'];
            case 'roe': // (tikai pozitīva kapitāla gadījumā; negatīvo jau apstrādāja augstāk)
                return ["pašu kapitāls rada zaudējumus — īpašnieku ieguldījums pārskata periodā ir sarucis", 'red'];
            case 'roce':
                return ["ieguldītais kapitāls rada zaudējumus, kas liecina par nerentablu pamatdarbību", 'red'];
            case 'interest_coverage':
                return ["uzņēmuma pamatdarbības peļņa ir negatīva, tāpēc tā nesedz pat procentu maksājumus — augsts maksātspējas risks", 'red'];
        }
    }
    return null;
}

function desc_eval($val, array $thresholds): ?array {
    if ($val === null) return null;
    foreach ($thresholds as [$limit, $cat, $color]) {
        if ($val < $limit) return [$cat, $color];
    }
    $last = end($thresholds);
    return [$last[1], $last[2]];
}

function build_financial_description(array $ratios_dict, string $company_name, ?string $registered_date_str, string $latest_year_str = "2024"): string {
    $text_blocks = [];

    $age_txt = get_age_text($registered_date_str, $company_name);
    if ($age_txt !== "") {
        $text_blocks[] = str_replace('<br>', '', trim($age_txt));
    }

    if (empty($ratios_dict)) {
        return implode("<br>", $text_blocks);
    }

    $INF = PHP_FLOAT_MAX;
    $mappings = [
        'current_ratio' => [[1.0, 'danger', 'red'], [1.5, 'precaution', '#d5a80b'], [3.0, 'healthy', 'green'], [$INF, 'excessive', 'blue']],
        'quick_ratio' => [[0.8, 'danger', 'red'], [1.0, 'borderline', '#d5a80b'], [2.0, 'healthy', 'green'], [$INF, 'inefficient', 'blue']],
        'net_profit_margin' => [[0, 'low', 'red'], [0.10, 'average', '#d5a80b'], [$INF, 'high', 'green']],
        'roa' => [[0.02, 'low', 'red'], [0.05, 'satisfactory', '#d5a80b'], [$INF, 'high', 'green']],
        'roe' => [[0.05, 'low', 'red'], [0.15, 'average', '#d5a80b'], [$INF, 'high', 'green']],
        'debt_to_equity' => [[1.5, 'low_risk', 'green'], [2.0, 'elevated_risk', '#d5a80b'], [$INF, 'high_risk', 'red']],
        'asset_turnover' => [[0.5, 'low', 'red'], [1.0, 'average', '#d5a80b'], [$INF, 'high', 'green']],
        'roce' => [[0.05, 'low', 'red'], [0.15, 'average', '#d5a80b'], [$INF, 'high', 'green']],
        'interest_coverage' => [[1.5, 'low', 'red'], [3.0, 'average', '#d5a80b'], [$INF, 'high', 'green']],
        'altman_z_score' => [[1.8, 'distress', 'red'], [3.0, 'grey', '#d5a80b'], [$INF, 'safe', 'green']],
    ];

    $fin_blocks = [];
    $first_fin = true;

    // Negatīva pašu kapitāla brīdinājums — svarīgāks par jebkuru attiecību un
    // aizstāj maldinošos D/E, ROE, ROCE, kas pie negatīva saucēja nav interpretējami.
    if (!empty($ratios_dict['debt_to_equity']['negative_equity'])) {
        $warn = "uzņēmuma pašu kapitāls ir negatīvs — uzkrātie zaudējumi pārsniedz "
              . "ieguldīto kapitālu, kas norāda uz nopietnu finanšu risku (tehniska maksātnespēja)";
        if ($first_fin) {
            $warn = "Pamatojoties uz {$latest_year_str}. gada datiem, {$warn}";
            $first_fin = false;
        } else {
            $warn = mb_strtoupper(mb_substr($warn, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($warn, 1, null, 'UTF-8');
        }
        $fin_blocks[] = "<span style='color: red;'>{$warn}.</span>";
    }

    // Pie negatīva pašu kapitāla ROE/D/E/ROCE grafiki paliek (kā prasīts), bet to parastie
    // sliekšņu teikumi būtu MALDINOŠI (piem. pie negatīva kapitāla ROE zīme apgriežas un izskatās
    // "labs"; negatīva marža ir ZAUDĒJUMI, ne "vāja peļņa"). special_finance_note() šādos gadījumos
    // dod jēgpilnu secinājumu, kas paskaidro reālo situāciju — lai KATRAM grafikam ir secinājums.
    foreach ($mappings as $key => $thresholds) {
        if (!isset($ratios_dict[$key])) continue;
        $val = $ratios_dict[$key]['value'] ?? null;
        if ($val === null) continue;

        $special = special_finance_note($key, (float)$val, $ratios_dict);
        if ($special !== null) {
            [$chosen, $color] = $special;
        } else {
            $cat_color = desc_eval($val, $thresholds);
            if ($cat_color === null) continue;
            [$cat, $color] = $cat_color;
            $desc_list = FINANCIAL_DESCRIPTIONS[$key][$cat] ?? [];
            if (empty($desc_list)) continue;
            $chosen = trim(str_replace('<br>', '', pick_variant($desc_list)));
        }

        if ($first_fin) {
            $first = mb_strtolower(mb_substr($chosen, 0, 1, 'UTF-8'), 'UTF-8');
            $rest = mb_substr($chosen, 1, null, 'UTF-8');
            $chosen = "Pamatojoties uz {$latest_year_str}. gada datiem, {$first}{$rest}";
            $first_fin = false;
        } else {
            // Katra nākamā rinda VIENMĒR sākas ar lielo burtu (daži varianti krājumā
            // sākas ar mazo, jo rakstīti pirmās rindas turpinājuma formā)
            $chosen = mb_strtoupper(mb_substr($chosen, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($chosen, 1, null, 'UTF-8');
        }
        $fin_blocks[] = "<span style='color: {$color};'>{$chosen}</span>";
    }

    $text_blocks = array_merge($text_blocks, $fin_blocks);
    $final_text = implode("<br>\n", $text_blocks);
    if (!empty($ratios_dict)) {
        $final_text .= "<br><br>\n<span style='font-size:0.85em; color:gray;'>" . DESCRIPTION_DISCLAIMER . "</span>";
    }
    return $final_text;
}
