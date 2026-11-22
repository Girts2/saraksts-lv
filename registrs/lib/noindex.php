<?php
// registrs/lib/noindex.php — uzņēmumu lapas, ko meklētāji (Google, Bing u. c.) NEDRĪKST indeksēt.
//
// Lapa paliek pieejama (HTTP 200) cilvēkiem un saitēm, bet:
//   - company.php sūta `X-Robots-Tag: noindex` un ieliek <meta name="robots" content="noindex">;
//   - vietnes kartē tās nav (build/report_tracker.php generate_sitemaps).
// /{regnr}.json un /{regnr}/sadala/* jau ir noindex visiem uzņēmumiem (company_json.php, sadala.php).
//
// robots.txt šīs lapas NEDRĪKST bloķēt: tad robots lapu vairs nepārmeklē, neredz noindex, un adrese var
// palikt indeksā (bez apraksta). Izņemšana no indeksa notiek tikai pēc tam, kad meklētājs lapu pārmeklē.
declare(strict_types=1);

// Ieraksts: 'regnr' => 'datums; pamats'. Pilnībā izņemtie profili (izslegtie.php) ir noindex arī bez ieraksta šeit.
const REG_NOINDEX = [
];

if (!function_exists('reg_noindex')) {
    /** Vai uzņēmuma lapu nedrīkst indeksēt. Pieņem arī int (masīvu atslēgas ar cipariem PHP kļūst par int). */
    function reg_noindex(string|int $reg): bool {
        require_once __DIR__ . '/izslegtie.php';
        return isset(REG_NOINDEX[preg_replace('/\D/', '', (string)$reg)]) || reg_izslegts($reg);
    }
}

if (!function_exists('reg_noindex_ieraksts')) {
    /**
     * Noindex pēc reģistra ieraksta: SLĒGTI fizisko personu komersanti — individuālie komersanti (IK),
     * individuālie uzņēmumi (IND), zemnieku (ZEM) un zvejnieku (ZVJ) saimniecības, ģimenes uzņēmumi (GIM) (riska analīze 2026-11-20,
     * 4.5; ~45 tūkst. lapu). Lapā ir bijušā komersanta (bieži mājas) adrese un dažām maksātnespējas vēsture;
     * meklētājos tā paliktu bezgalīgi. Lapa paliek pieejama (oficiālā reģistra fakti), vietnes kartē to nav.
     * Statuss — tas pats likums, ko segmenter.php: closed L/R vai terminated ≠ tukšs/0000-00-00.
     */
    function reg_noindex_ieraksts(?array $main): bool {
        if (!$main) return false;
        $tips = strtoupper(trim((string)($main['type'] ?? '')));
        // GIM — ģimenes uzņēmums (arī fizisko personu komersants; ~100 slēgtu, audits 2026-11-22).
        if (!in_array($tips, ['IK', 'IND', 'ZEM', 'ZVJ', 'GIM'], true)) return false;
        $closed = strtoupper(trim((string)($main['closed'] ?? '')));
        $term = trim((string)($main['terminated'] ?? ''));
        return $closed === 'L' || $closed === 'R' || ($term !== '' && $term !== '0000-00-00');
    }
}
