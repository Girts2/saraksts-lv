<?php
// server/lib/data_fetcher.php — ports of kods/core/data_fetcher.py
// Visi vaicājumi parametrizēti (kā auditā salabotajā Python versijā).
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/gdpr_scrub.php';

/**
 * ATVK koda atjaunošana: DB kolonna vēsturiski būvēta kā REAL, un VISI 486 675 ATVK
 * kodi sākas ar 0 (Rīga = 0010000) — katrs zaudēja vadošo nulli un lapā rādījās kā
 * "10000". Kodi ir tieši 7 cipari; kamēr DB nav pārbūvēta ar ALWAYS_STRING_COLS
 * labojumu (build/config.php), atjaunojam ielādes brīdī. Teksta vērtībai (pēc
 * pārbūves) is_int/is_float sargs neko nedara — normalizētājs paliek nekaitīgs.
 */
function fix_register_atvk(array $row): array {
    $v = $row['atvk'] ?? null;
    if (is_int($v) || is_float($v)) {
        $row['atvk'] = sprintf('%07d', (int)$v);
    }
    return $row;
}

/**
 * Reorganizāciju partnera kodu atjaunošana: tā pati REAL kolonnas problēma — vecie
 * 9 ciparu kodi ar vadošo nulli ('010100292') kļuva par 10100292 (2 427 rindas).
 *
 * Polsterējam uz 9 TIKAI, ja ciparu ir ≤8. CSV avota analīze (2026-08-12): vadošā
 * nulle ir tikai daļai 9 zīmju kodu; visi 10 zīmju kodi sākas ar '1' un visi
 * 11 zīmju — ar '4'/'5'/'9', t.i. ≥9 ciparu int NEKAD nav zaudējis nulli. Pirmā
 * versija ("≤9→9, citādi→11") 155 desmitzīmju kodiem FABRICĒTU nulli priekšā.
 * Blakusieguvums: atjaunotie kodi atkal kļūst par saitēm (linkable prasa 11 ciparus).
 */
function fix_reorg_codes(array $rows): array {
    foreach ($rows as $i => $r) {
        foreach (['source_entity_regcode', 'final_entity_regcode'] as $k) {
            $v = $r[$k] ?? null;
            if (is_int($v) || is_float($v)) {
                $s = sprintf('%.0f', $v);
                $rows[$i][$k] = strlen($s) <= 8 ? str_pad($s, 9, '0', STR_PAD_LEFT) : $s;
            }
        }
    }
    return $rows;
}

/**
 * Galvenā uzņēmuma rinda no register (vai null).
 */
function fetch_main_company_data(PDO $conn, string $reg_nr): ?array {
    try {
        $stmt = $conn->prepare("SELECT * FROM register WHERE regcode = ?");
        $stmt->execute([$reg_nr]);
        $row = $stmt->fetch();
        return $row !== false ? fix_register_atvk($row) : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Kārto vēsturiskos nosaukumus pēc date_to dilstoši (nederīgie datumi beigās).
 */
function sort_historical_names(array $rows): array {
    if (empty($rows) || !array_key_exists('date_to', $rows[0])) {
        return $rows;
    }
    usort($rows, function ($a, $b) {
        $ta = isset($a['date_to']) && $a['date_to'] !== null && $a['date_to'] !== '' ? strtotime((string)$a['date_to']) : false;
        $tb = isset($b['date_to']) && $b['date_to'] !== null && $b['date_to'] !== '' ? strtotime((string)$b['date_to']) : false;
        // na_position='last' + descending
        if ($ta === false && $tb === false) return 0;
        if ($ta === false) return 1;   // a bez datuma -> beigās
        if ($tb === false) return -1;
        return $tb <=> $ta;            // dilstoši
    });
    return $rows;
}

/**
 * Kārto reorganizācijas pēc `registered` dilstoši (jaunākā augšā), nederīgie datumi beigās.
 *
 * Vaicājums ir "source = ? OR final = ?", tāpēc rindu secība bez šī bija tikai
 * glabāšanas blakusefekts un mainījās līdz ar vaicājuma plānu (indeksa
 * pievienošana 2026-08-11 pārkārtoja 18 no 3 309 parauga lapām, nemainot pašas
 * rindas). Sakārtojot pēc datuma, secība ir noteikta un jēgpilna neatkarīgi no plāna.
 */
function sort_reorganizations(array $rows): array {
    if (empty($rows) || !array_key_exists('registered', $rows[0])) {
        return $rows;
    }
    usort($rows, function ($a, $b) {
        $ta = isset($a['registered']) && $a['registered'] !== null && $a['registered'] !== '' ? strtotime((string)$a['registered']) : false;
        $tb = isset($b['registered']) && $b['registered'] !== null && $b['registered'] !== '' ? strtotime((string)$b['registered']) : false;
        if ($ta === false && $tb === false) return 0;
        if ($ta === false) return 1;
        if ($tb === false) return -1;
        if ($tb !== $ta) return $tb <=> $ta;
        // Vienā dienā reģistrētas vairākas — sakārtojam pēc id, lai secība ir noteikta.
        return strcmp((string)($b['id'] ?? ''), (string)($a['id'] ?? ''));
    });
    return $rows;
}

/**
 * Visi saistītie dati par reģ.nr. — atgriež [table_name => [rows...]].
 */
function fetch_all_data_for_reg_nr(PDO $conn, string $reg_nr, ?array $table_names = null): array {
    if ($table_names === null) {
        $table_names = array_keys(SEARCH_COLUMNS_MAP_REG_NR);
    }
    $all_results = [];
    $reg = (string)$reg_nr;

    foreach ($table_names as $table_name) {
        if (!isset(SEARCH_COLUMNS_MAP_REG_NR[$table_name])) {
            continue;
        }
        // Tabulu/kolonnu nosaukumi no fiksētas konfigurācijas (uzticami).
        $cols = SEARCH_COLUMNS_MAP_REG_NR[$table_name];
        $conditions = implode(' OR ', array_map(fn($c) => "$c = ?", $cols));
        $query = "SELECT * FROM $table_name WHERE $conditions";
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute(array_fill(0, count($cols), $reg));
            $data = $stmt->fetchAll();
            if (!empty($data)) {
                if ($table_name === 'register_name_history') {
                    $data = sort_historical_names($data);
                } elseif ($table_name === 'register') {
                    $data = array_map('fix_register_atvk', $data);
                } elseif ($table_name === 'reorganizations') {
                    $data = fix_reorg_codes($data);
                    $data = sort_reorganizations($data);
                } elseif ($table_name === 'securing_measures') {
                    // Vienuviet, tūlīt pēc ielādes: no šejienes dati aiziet GAN uz HTML
                    // tabulām, GAN uz ai_json_data (kas ir iegults lapā un ko atdod
                    // /{regnr}.json), tāpēc skrubis nedrīkst būt tikai veidnē.
                    $data = scrub_securing_measures($data);
                } elseif ($table_name === 'liquidations') {
                    // grounds_for_liquidation brīvtekstā mēdz būt lēmuma pieņēmēja
                    // (dalībnieka, biedra, likvidatora) personvārds — sk. gdpr_scrub.php.
                    $data = scrub_liquidations($data);
                }
                $all_results[$table_name] = $data;
            }
        } catch (Throwable $e) {
            // izlaižam
        }
    }

    // Finanšu detaļu tabulas pēc statement_id
    if (isset($all_results['financial_statements'])) {
        $fs_ids = [];
        foreach ($all_results['financial_statements'] as $row) {
            $v = get_raw_value($row, 'id');
            if ($v !== null) $fs_ids[] = (string)$v;
        }
        if (!empty($fs_ids)) {
            $ph = implode(',', array_fill(0, count($fs_ids), '?'));
            foreach (['income_statements', 'balance_sheets', 'cash_flow_statements'] as $detail) {
                try {
                    $stmt = $conn->prepare("SELECT * FROM $detail WHERE statement_id IN ($ph)");
                    $stmt->execute($fs_ids);
                    $linked = $stmt->fetchAll();
                    if (!empty($linked)) $all_results[$detail] = $linked;
                } catch (Throwable $e) {}
            }
        }
    }

    // Kopīpašnieki
    foreach ([['members', 'members_joint_owners'], ['stockholders', 'stockholders_joint_owners']] as [$parent, $joint]) {
        if (isset($all_results[$parent])) {
            $pids = [];
            foreach ($all_results[$parent] as $row) {
                $v = get_raw_value($row, 'id');
                if ($v !== null) $pids[] = (string)$v;
            }
            if (!empty($pids)) {
                $ph = implode(',', array_fill(0, count($pids), '?'));
                try {
                    $stmt = $conn->prepare("SELECT * FROM $joint WHERE member_id IN ($ph)");
                    $stmt->execute($pids);
                    $jd = $stmt->fetchAll();
                    if (!empty($jd)) $all_results[$joint] = $jd;
                } catch (Throwable $e) {}
            }
        }
    }

    return $all_results;
}

/**
 * Dalība citos uzņēmumos (kā subjekts).
 */
function fetch_member_as_entity_data(PDO $conn, string $reg_nr): array {
    try {
        $stmt = $conn->prepare("SELECT * FROM members WHERE legal_entity_registration_number = ?");
        $stmt->execute([(string)$reg_nr]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function fetch_super_id(PDO $conn, string $reg_nr): ?int {
    try {
        $stmt = $conn->prepare("SELECT super_id FROM register WHERE regcode = ?");
        $stmt->execute([(string)$reg_nr]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $val = get_raw_value($row, 'super_id');
            if ($val !== null) return (int)(float)$val;
        }
    } catch (Throwable $e) {}
    return null;
}

function fetch_regcodes_by_super_ids(PDO $conn, array $super_ids, bool $active_only = false): array {
    if (empty($super_ids)) return [];
    try {
        $ph = implode(',', array_fill(0, count($super_ids), '?'));
        $stmt = $conn->prepare("SELECT regcode, closed, terminated FROM register WHERE super_id IN ($ph)");
        $stmt->execute(array_map('strval', $super_ids));
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            if ($active_only) {
                $closed = (string)($r['closed'] ?? '');
                $term = (string)($r['terminated'] ?? '');
                $is_active = !in_array($closed, ['L', 'R'], true)
                    && ($term === '' || $term === '0000-00-00');
                if (!$is_active) continue;
            }
            $rc = trim((string)($r['regcode'] ?? ''));
            if ($rc !== '') $out[] = $rc;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}
