<?php
// server/lib/segmenter.php — ports of kods/processing/segmenter.py
declare(strict_types=1);

require_once __DIR__ . '/formatters.php';

function determine_company_segment($company_main_data, array $financial_data): array {
    $segment = [
        'status' => 'Nezināms',
        'form_group' => 'Cits',
        'financials' => 'Nav datu',
    ];

    if (!is_array($company_main_data) || empty($company_main_data)) {
        return $segment;
    }

    // 1. Statuss
    $closed = get_raw_value($company_main_data, 'closed');
    $terminated = get_raw_value($company_main_data, 'terminated');

    if ($closed === 'L') {
        $segment['status'] = 'Likvidēts';
    } elseif ($closed === 'R') {
        $segment['status'] = 'Reorganizēts';
    } elseif ($terminated !== null && trim((string)$terminated) !== '' && (string)$terminated !== '0000-00-00') {
        $segment['status'] = 'Cits';
    } else {
        $segment['status'] = 'Aktīvs';
    }

    // 2. Juridiskā forma
    $type_text = get_raw_value($company_main_data, 'type_text');
    if ($type_text !== null) {
        $lower = mb_strtolower((string)$type_text, 'UTF-8');
        $any = function (array $needles) use ($lower): bool {
            foreach ($needles as $n) {
                if (mb_strpos($lower, $n) !== false) return true;
            }
            return false;
        };
        if ($any(['sabiedrība', 'komersants', 'saimniecība', 'uzņēmums'])) {
            $segment['form_group'] = 'Komercsabiedrība';
        } elseif ($any(['biedrība', 'nodibinājums'])) {
            $segment['form_group'] = 'NVO';
        } elseif (mb_strpos($lower, 'partija') !== false) {
            $segment['form_group'] = 'Partija';
        }
    }

    // 3. Finanšu segments (tikai aktīviem komercuzņēmumiem)
    if ($segment['status'] === 'Aktīvs' && $segment['form_group'] === 'Komercsabiedrība') {
        $ugp = $financial_data['UGP'] ?? [];
        if (!empty($ugp)) {
            $latest = $ugp[0];
            $profit = $latest['profit'] ?? null;
            if ($profit !== null) {
                $segment['financials'] = $profit > 0 ? 'Peļņa' : 'Zaudējumi';
            }
        }
    }

    return $segment;
}
