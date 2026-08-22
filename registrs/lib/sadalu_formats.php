<?php
/**
 * registrs/lib/sadalu_formats.php — kopīgi formatētāji "Papildu reģistri un dati"
 * sadaļām. Agrāk katrs panelis definēja savu $x_eur un $x_dsk closure — astoņām
 * sadaļām tas būtu astoņas identiskas kopijas.
 */
declare(strict_types=1);

/** Summa cilvēklasāmā formā: 1,23 milj. € / 12 345 € / 12,34 €. */
function pd_eur(float $v): string {
    if (abs($v) >= 1e6) return number_format($v / 1e6, 2, ',', ' ') . ' milj. €';
    if (abs($v) >= 1000) return number_format($v, 0, ',', ' ') . ' €';
    return number_format($v, 2, ',', ' ') . ' €';
}

/** Latviešu skaitļa forma: 21 līgumAM, bet 22 un 11 līgumIEM. */
function pd_dsk(int $n, string $viensk, string $dsk): string {
    return ($n % 10 === 1 && $n % 100 !== 11) ? $viensk : $dsk;
}

/** Datums YYYY-MM vai tukšums. */
function pd_men(?string $d): string {
    $d = substr(trim((string)$d), 0, 7);
    return preg_match('/^\d{4}-\d{2}$/', $d) ? $d : '';
}

/**
 * Cik ierakstu rādīt sarakstā. Lapā — īss saraksts; pilnajā skatā (sadala.php)
 * viss, bet ar drošības griestiem: pat pilnā skatā 5 000 rindu HTML nevienam
 * nepalīdz, un pārlūkam tas ir smags.
 */
function pd_limits(bool $pilns, int $isais, int $pilnais = 400): int {
    return $pilns ? $pilnais : $isais;
}

/**
 * Rinda "… un vēl N" saraksta beigās.
 *
 * Lapā tā ir POGA, kas ielādē sadaļas pilno saturu (papildu_dati.js →
 * /{regnr}/sadala/{atslega}); pilnajā skatā vai bez JavaScript tā paliek
 * vienkāršs teksts, tāpēc funkcionalitāte bez skripta nepazūd, tikai kļūst statiska.
 */
function pd_vairak(int $atlikums, string $atslega, bool $pilns): string {
    if ($atlikums <= 0) return '';
    $t = '… un vēl ' . $atlikums;
    if ($pilns) {
        return '<li class="pd-muted">' . h($t) . '</li>';
    }
    // Godīgs uzraksts: pilnajā skatā ir griesti (pd_limits), tāpēc pie ļoti gara
    // saraksta poga nesola "visus" — tā sola "vairāk", un pēc ielādes atlikums
    // atkal ir redzams kā teksts.
    $sola = $atlikums > 400 ? ' — rādīt vairāk' : ' — rādīt visus';
    // SAITE, ne poga: bez JavaScript tā atver /{regnr}/sadala/{atslega} kā parastu
    // lapu (sadala.php navigācijai uzliek minimālu ietvaru), ar JavaScript
    // papildu_dati.js klikšķi pārtver un saturu ielādē turpat sadaļā. Agrāk šeit
    // vienmēr bija <button>, kas bez skripta neko nedarīja (audits 2026-08-20).
    $reg = preg_replace('/\D/', '', (string)($GLOBALS['pd_aktivais_reg'] ?? ''));
    if (strlen($reg) === 11) {
        return '<li><a class="pd-vairak" data-sadala="' . h($atslega) . '" href="/' . h($reg)
            . '/sadala/' . h($atslega) . '" rel="nofollow">' . h($t . $sola) . '</a></li>';
    }
    return '<li><button type="button" class="pd-vairak" data-sadala="' . h($atslega) . '">'
        . h($t . $sola) . '</button></li>';
}
