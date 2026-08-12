<?php
/**
 * registrs/nvo/nvo_render.php — formatēšanas un HTML palīgi "Test Biedrības" paneļiem.
 * Visas vērtības iziet caur nv_e() — paneļu saturs nāk no atvērtajiem datiem.
 */
declare(strict_types=1);

function nv_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** 12345.6 -> "12 346" ; null -> "—" */
function nv_num($v, int $dec = 0): string {
    if ($v === null || $v === '') return '—';
    return number_format((float)$v, $dec, ',', ' ');
}

/** Nauda ar valūtu; lielām summām saīsina. */
function nv_eur($v, string $cur = 'EUR'): string {
    if ($v === null) return '—';
    $v = (float)$v;
    $sym = $cur === 'LVL' ? 'Ls' : '€';
    if (abs($v) >= 1000000) return number_format($v / 1000000, 2, ',', ' ') . ' milj. ' . $sym;
    return number_format($v, 0, ',', ' ') . ' ' . $sym;
}

/** Koeficients ar 2 zīmēm; null -> "nav aprēķināms" */
function nv_koef($v): string { return $v === null ? '<span class="nv-na">nav aprēķināms</span>' : nv_num($v, 2); }

/** 0.4237 -> "42,4 %" */
function nv_pct($v, int $dec = 1): string {
    return $v === null ? '—' : number_format((float)$v * 100, $dec, ',', ' ') . ' %';
}

/** "2024-03-15" -> "15.03.2024." */
function nv_date($v): string {
    $s = trim((string)$v);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return $s === '' ? '—' : nv_e($s);
    return "$m[3].$m[2].$m[1].";
}

/** Skaitlis + latviešu locījums: 1 gads / 2 gadi / 21 gads */
function nv_gadi(?int $n): string {
    if ($n === null) return '—';
    $forma = ($n % 10 === 1 && $n % 100 !== 11) ? 'gads' : 'gadi';
    return $n . ' ' . $forma;
}

/** Statusa nozīmīte: veids = ok | brid | risks | info */
function nv_badge(string $teksts, string $veids = 'info'): string {
    return '<span class="nv-badge nv-' . nv_e($veids) . '">' . nv_e($teksts) . '</span>';
}

/** Čipu rinda no virkņu masīva. */
function nv_chips(array $items, int $limit = 0): string {
    if (!$items) return '';
    $more = 0;
    if ($limit > 0 && count($items) > $limit) { $more = count($items) - $limit; $items = array_slice($items, 0, $limit); }
    $h = '<div class="nv-chips">';
    foreach ($items as $i) $h .= '<span class="nv-chip">' . nv_e($i) . '</span>';
    if ($more > 0) $h .= '<span class="nv-chip nv-chip-more">+' . $more . '</span>';
    return $h . '</div>';
}

/** Atslēga–vērtība tabula (vērtības jau drīkst saturēt HTML). */
function nv_kv(array $pairs): string {
    $h = '<table class="nv-kv">';
    foreach ($pairs as $k => $v) {
        if ($v === null || $v === '') continue;
        $h .= '<tr><th>' . nv_e($k) . '</th><td>' . $v . '</td></tr>';
    }
    return $h . '</table>';
}

/**
 * Datu tabula. $cols = ['Virsraksts' => ['key' => 'lauks', 'num' => bool, 'fmt' => callable]]
 */
function nv_table(array $rows, array $cols, string $class = ''): string {
    $h = '<div class="nv-scroll"><table class="nv-table ' . nv_e($class) . '"><thead><tr>';
    foreach ($cols as $title => $c) {
        $h .= '<th' . (!empty($c['num']) ? ' class="num"' : '') . '>' . nv_e($title) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr>';
        foreach ($cols as $c) {
            $raw = $r[$c['key']] ?? null;
            $val = isset($c['fmt']) ? ($c['fmt'])($raw, $r) : nv_e((string)($raw ?? '—'));
            $h .= '<td' . (!empty($c['num']) ? ' class="num"' : '') . '>' . $val . '</td>';
        }
        $h .= '</tr>';
    }
    return $h . '</tbody></table></div>';
}

/** Neliela piezīme zem paneļa (avots, ierobežojumi). */
function nv_note(string $t): string { return '<p class="nv-note">' . $t . '</p>'; }

/** Horizontāla josla salīdzinājumam ar etalonu (0..1 aizpildījums). */
function nv_bar(float $frac, string $label = ''): string {
    $w = max(0.0, min(1.0, $frac)) * 100;
    return '<div class="nv-bar"><span style="width:' . number_format($w, 1, '.', '') . '%"></span></div>'
        . ($label !== '' ? '<div class="nv-bar-lab">' . nv_e($label) . '</div>' : '');
}
