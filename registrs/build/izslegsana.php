<?php
/**
 * registrs/build/izslegsana.php — izņemto profilu (registrs/lib/izslegtie.php) rindas izmet
 * no staging ur_data.db, PIRMS no tās būvē meklēšanas DB, nace_stats, Nozares/TOP katalogu,
 * Struktūru un Pensionāru (build_all.php 3. posmā, aiz vēstures apvienošanas — tā atjauno
 * VID ceturkšņu un gada rindas no vēstures failiem, tāpēc agrāk dzēst nedrīkst).
 *
 * Izmet tikai PAŠA uzņēmuma rindas (kolonna, pēc kuras uzņēmuma lapa ielasa savus datus):
 *   - SEARCH_COLUMNS_MAP_REG_NR (registrs/lib/config.php). Tabulās ar VAIRĀKĀM kolonnām (reorganizācijas:
 *     sākotnējais/pārņēmējs; sankcijas: subjekts/juridiskā persona; deleģētās iestādes) rinda ir ABU pušu
 *     ieraksts — to izmet tikai tad, ja katra aizpildītā puse ir izņemta (audits 2026-11-22: citādi
 *     izņemšana nodzēsa reorganizāciju arī otra uzņēmuma lapā);
 *   - dalībnieki/amatpersonas/akcionāri/šķīrējtiesas locekļi pēc at_legal_entity_registration_number,
 *     to kopīpašnieki pēc member_id, patiesie labuma guvēji pēc legal_entity_registration_number;
 *   - gada pārskatu bilance/peļņa/naudas plūsma pēc statement_id.
 * Citu uzņēmumu rindas, kurās izņemtais minēts kā dalībnieks, amatpersona, pasūtītājs vai reorganizācijas/
 * sankciju otra puse, PALIEK (tie ir otra uzņēmuma dati); saites veido tikai uz register rindu, tāpēc tur
 * paliek vien nosaukums kā teksts.
 * Atlikušās atsauces žurnālā saskaita (informatīvi).
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';     // SEARCH_COLUMNS_MAP_REG_NR
require_once __DIR__ . '/../lib/izslegtie.php';

/**
 * @param string[]|null $regs  izņemamie kodi (null = reg_izslegtie(); testiem var padot savus)
 * @return array{dzestas:int, pa_tabulam:array<string,int>, atsauces:array<string,int>}
 */
function build_izslegt_uznemumus(PDO $pdo, ?callable $log = null, ?array $regs = null): array {
    $log ??= static function (string $m): void {};
    $regs ??= array_keys(reg_izslegtie());
    $regs = array_values(array_unique(array_filter(array_map(
        static fn($r) => preg_replace('/\D/', '', (string)$r), $regs),
        static fn($r) => strlen($r) === 11)));
    $rez = ['dzestas' => 0, 'pa_tabulam' => [], 'atsauces' => []];
    if (!$regs) return $rez;

    $tabulas = [];
    foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'") as $r) {
        $t = (string)$r['name'];
        $kol = [];
        foreach ($pdo->query('PRAGMA table_info(' . $pdo->quote($t) . ')') as $c) $kol[(string)$c['name']] = true;
        $tabulas[$t] = $kol;
    }
    $ir = static fn(string $t, string $c): bool => isset($tabulas[$t][$c]);
    $ph = implode(',', array_fill(0, count($regs), '?'));
    $qi = static fn(string $n): string => '"' . str_replace('"', '""', $n) . '"';

    // [tabula, WHERE izteiksme ar ? vietturiem regs sarakstam]. Atvasinātās (pēc id) — PIRMS vecākiem.
    $nosacijumi = [];
    foreach (['balance_sheets', 'income_statements', 'cash_flow_statements'] as $t) {
        if ($ir($t, 'statement_id') && $ir('financial_statements', 'id')) {
            $nosacijumi[] = [$t, 'statement_id IN (SELECT id FROM financial_statements WHERE legal_entity_registration_number IN (' . $ph . '))'];
        }
    }
    foreach (['members_joint_owners' => 'members', 'stockholders_joint_owners' => 'stockholders'] as $t => $vec) {
        if ($ir($t, 'member_id') && $ir($vec, 'id') && $ir($vec, 'at_legal_entity_registration_number')) {
            $nosacijumi[] = [$t, 'member_id IN (SELECT id FROM ' . $qi($vec) . ' WHERE at_legal_entity_registration_number IN (' . $ph . '))'];
        }
    }
    $pasa = [];   // tabula => kolonnas
    foreach (SEARCH_COLUMNS_MAP_REG_NR as $t => $kols) $pasa[$t] = $kols;
    foreach (['members', 'officers', 'stockholders', 'arbitration_members'] as $t) {
        $pasa[$t] = ['at_legal_entity_registration_number'];
    }
    $pasa['beneficial_owners'] = ['legal_entity_registration_number'];
    unset($pasa['register']);                          // register — pēdējais (citi vaicājumi uz to atsaucas)
    $pasa['register'] = ['regcode'];
    foreach ($pasa as $t => $kols) {
        $kols = array_values(array_filter($kols, fn($c) => $ir($t, $c)));
        if (!$kols) continue;
        if (count($kols) === 1) {
            $nosacijumi[] = [$t, $qi($kols[0]) . ' IN (' . $ph . ')'];
            continue;
        }
        // Vismaz viena puse izņemta UN neviena aizpildīta puse nav cits (neizņemts) uzņēmums.
        $kads = []; $visi = [];
        foreach ($kols as $c) {
            $kads[] = $qi($c) . ' IN (' . $ph . ')';
            $visi[] = '(' . $qi($c) . ' IN (' . $ph . ') OR ' . $qi($c) . ' IS NULL OR TRIM(' . $qi($c) . ") = '')";
        }
        $nosacijumi[] = [$t, '(' . implode(' OR ', $kads) . ') AND ' . implode(' AND ', $visi)];
    }

    $pdo->beginTransaction();
    try {
        foreach ($nosacijumi as [$t, $kur]) {
            $n = substr_count($kur, '?') / count($regs);
            $st = $pdo->prepare('DELETE FROM ' . $qi($t) . ' WHERE ' . $kur);
            $st->execute(array_merge(...array_fill(0, (int)$n, $regs)));
            $d = $st->rowCount();
            if ($d > 0) {
                $rez['pa_tabulam'][$t] = ($rez['pa_tabulam'][$t] ?? 0) + $d;
                $rez['dzestas'] += $d;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();   // SQLite pats var būt atritinājis — neaizsedzam īsto kļūdu
        throw $e;
    }

    // Atlikušās atsauces (citu uzņēmumu rindās) — tikai žurnālam.
    $atsauces_kol = ['legal_entity_registration_number', 'at_legal_entity_registration_number', 'regcode',
        'Registracijas_kods', 'Numurs', 'source_entity_regcode', 'final_entity_regcode', 'pasutitaja_regnr',
        'sanctions_subject_registration_number', 'registrationNumber', 'delegatedEntityRegistrationNumber',
        'person_reg_nr', 'debtor_registration_number', 'institution_registration_number', 'depository_registration_number'];
    foreach ($tabulas as $t => $kol) {
        foreach ($atsauces_kol as $c) {
            if (!isset($kol[$c])) continue;
            $st =$pdo->prepare('SELECT count(*) FROM ' . $qi($t) . ' WHERE ' . $qi($c) . ' IN (' . $ph . ')');
            $st->execute($regs);
            $n = (int)$st->fetchColumn();
            if ($n > 0) $rez['atsauces']["$t.$c"] = $n;
        }
    }

    $sad = [];
    foreach ($rez['pa_tabulam'] as $t => $d) $sad[] = "$t $d";
    $log('   Izņemtie profili: ' . count($regs) . ' uzņ., izmestas ' . $rez['dzestas'] . ' rindas'
        . ($sad ? ' (' . implode(', ', $sad) . ')' : ''));
    if ($rez['atsauces']) {
        $a = [];
        foreach ($rez['atsauces'] as $k => $n) $a[] = "$k $n";
        $log('   Izņemtie profili: atsauces citu uzņēmumu rindās paliek: ' . implode(', ', $a));
    }
    return $rez;
}
