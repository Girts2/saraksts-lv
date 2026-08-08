<?php
// server/lib/formatters.php
// PHP ports of kods/processing/formatters.py — identiska uzvedība ar Python versiju.
// Rindas ($row) šeit vienmēr ir asociatīvi masīvi (PDO FETCH_ASSOC), nevis pandas Series.

declare(strict_types=1);

/**
 * Python "truthiness": None/False/0/0.0/''/[]/{} = false; STRING "0" = true (atšķiras no PHP empty()!).
 * Lieto visur, kur Python kods izmanto `if X` vai `all([...])` uz vērtībām.
 */
function py_truthy($v): bool {
    if ($v === null || $v === false) return false;
    if ($v === 0 || $v === 0.0) return false;
    if ($v === '') return false;
    if (is_array($v) && count($v) === 0) return false;
    return true;
}

/**
 * Jinja2/MarkupSafe savietojama ekranēšana (lai baitu izvade sakristu ar Python etalonu).
 * Jinja: & -> &amp;  < -> &lt;  > -> &gt;  ' -> &#39;  " -> &#34;
 * (PHP htmlspecialchars dotu &quot; / &#039;, kas atšķiras — tāpēc pašu funkcija.)
 */
function jinja_e($value): string {
    if ($value === null) {
        return 'None'; // Jinja noklusējums None objektam ir "None"
    }
    if (is_bool($value)) {
        return $value ? 'True' : 'False';
    }
    if (is_float($value)) {
        $s = py_str_float($value);
    } else {
        $s = (string)$value;
    }
    $s = str_replace('&', '&amp;', $s);
    $s = str_replace('<', '&lt;', $s);
    $s = str_replace('>', '&gt;', $s);
    $s = str_replace("'", '&#39;', $s);
    $s = str_replace('"', '&#34;', $s);
    return $s;
}

/**
 * Atdarina Python str(float): īsākā round-trip virkne, kas SAGLABĀ '.0' veseliem
 * (0.0 -> "0.0", 1.0 -> "1.0", 0.03 -> "0.03", -0.05 -> "-0.05").
 * PHP (string)float dotu "0"/"1", tāpēc lietojam serialize_precision=-1 (json_encode)
 * un pievienojam '.0', ja iznāk vesels bez punkta/eksponentes.
 */
function py_str_float(float $v): string {
    if (is_nan($v)) return 'nan';
    if (is_infinite($v)) return $v < 0 ? '-inf' : 'inf';
    $s = json_encode($v); // serialize_precision=-1 -> īsākais round-trip
    if ($s === false) $s = (string)$v;
    // Ja nav ne decimāldaļas, ne eksponentes -> pievieno '.0' (kā Python)
    if (strpbrk($s, '.eEnN') === false) {
        $s .= '.0';
    }
    // Python eksponente: 1e+20 (PHP json: 1.0e+20). Praktiskajā datu diapazonā nesastopas.
    return $s;
}

/**
 * Rekursīvi aizstāj ne-JSON saderīgas vērtības (NaN/inf) ar null.
 */
function sanitize_for_json($obj) {
    if (is_array($obj)) {
        $out = [];
        foreach ($obj as $k => $v) {
            $out[$k] = sanitize_for_json($v);
        }
        return $out;
    }
    if (is_float($obj) && !is_finite($obj)) {
        return null;
    }
    return $obj;
}

/**
 * Iegūst "tīro" vērtību no asociatīva masīva. Apstrādā null, tukšas virknes,
 * un datumu laukiem '0000-00-00'.
 * Atgriež oriģinālo vērtību (nevis string), lai saglabātu tipu — tāpat kā Python.
 */
function get_raw_value($data_row, string $key) {
    if ($data_row === null || !is_array($data_row)) {
        return null;
    }
    if (!array_key_exists($key, $data_row)) {
        return null;
    }
    $value = $data_row[$key];
    if ($value === null) {
        return null;
    }
    // NaN pārbaude (SQLite reti, bet drošībai)
    if (is_float($value) && is_nan($value)) {
        return null;
    }
    $s_value = trim((string)$value);
    if ($s_value === '') {
        return null;
    }
    if (in_array($key, ['registered', 'terminated', 'reregistration_term'], true)) {
        if ($s_value === '0000-00-00' || str_starts_with($s_value, '0000-00-00')) {
            return null;
        }
    }
    return $value;
}

/**
 * Formatē datumu uz YYYY-MM-DD.
 */
function format_raw_date($data_row, string $key): ?string {
    $value = get_raw_value($data_row, $key);
    if ($value === null) {
        return null;
    }
    $s = (string)$value;
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
        return substr($s, 0, 10);
    }
    $ts = strtotime($s);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }
    return $s;
}

/**
 * Sagatavo skaitlisku vērtību: atgriež float vai null.
 * Atbalsta komatu kā decimāldaļu atdalītāju (kā Python versijā).
 */
function prepare_for_chart($value): ?float {
    if ($value === null) {
        return null;
    }
    if (is_float($value) && is_nan($value)) {
        return null;
    }
    $s = trim((string)$value);
    if ($s === '') {
        return null;
    }
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) {
        return null;
    }
    $num = (float)$s;
    if (is_nan($num) || is_infinite($num)) {
        return null;
    }
    return $num;
}

/**
 * Formatē skaitli ar atstarpēm tūkstošiem un valūtu.
 * Atdarina Python format_number_data (atstarpe = tūkstošu atdalītājs).
 */
function format_number_data($value, string $currency = ' ', $rounding_factor = 1): string {
    $num = prepare_for_chart($value);
    if ($num === null) {
        return '—';
    }
    $actual = $num * $rounding_factor;
    // Noapaļo līdz veselam (Python: f"{...:,.0f}")
    $formatted = number_format($actual, 0, '.', ' ');
    $cur = trim($currency);
    return $formatted . ' ' . $cur;
}

/**
 * Jinja filtrs format_plain_number: vesels skaitlis ar atstarpēm, citādi '—'.
 */
function format_plain_number($val): string {
    if ($val === null) {
        return '—';
    }
    if (is_int($val) || (is_float($val) && $val == (int)$val)) {
        return number_format((float)$val, 0, '.', ' ');
    }
    if (is_numeric($val)) {
        return number_format((float)$val, 0, '.', ' ');
    }
    return (string)$val;
}
