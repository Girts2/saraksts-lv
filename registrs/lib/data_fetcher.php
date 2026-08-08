<?php
// server/lib/data_fetcher.php — ports of kods/core/data_fetcher.py
// Visi vaicājumi parametrizēti (kā auditā salabotajā Python versijā).
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/formatters.php';

/**
 * Galvenā uzņēmuma rinda no register (vai null).
 */
function fetch_main_company_data(PDO $conn, string $reg_nr): ?array {
    try {
        $stmt = $conn->prepare("SELECT * FROM register WHERE regcode = ?");
        $stmt->execute([$reg_nr]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
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
