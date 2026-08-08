<?php
// server/build/section_common.php — kopīgie palīgi sadaļu (Nozare/Struktūra/Pensionārs) būvei.
declare(strict_types=1);

if (!function_exists('sect_format_name')) {
    /**
     * Uzņēmuma nosaukums: type "name_in_quotes" (bez pandas 'nan' artefaktiem —
     * tas pats labojums, kas reģistra meklēšanas DB nosaukumiem).
     */
    function sect_format_name($type, $name_in_quotes, $name): string {
        $t = trim((string)($type ?? ''));
        $n = trim((string)($name_in_quotes ?? ''));
        if ($t !== '' && $n !== '') return "$t \"$n\"";
        if ($n !== '') return "\"$n\"";
        return trim((string)($name ?? ''));
    }
}
