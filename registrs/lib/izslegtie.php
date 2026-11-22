<?php
// registrs/lib/izslegtie.php — uzņēmumi, kuru profils no vietnes ir IZŅEMTS pilnībā.
//
// Atšķirībā no noindex.php (lapa paliek, tikai meklētāji to neindeksē) izņemtam uzņēmumam:
//   - /{regnr}, /{regnr}.json un /{regnr}/sadala/* atdod HTTP 410 Gone (company.php,
//     company_json.php, sadala.php) — tātad arī MI panelis (tas iet caur company.php);
//   - meklēšana to neatgriež (registrs/assets/search/search.php);
//   - nakts būve izmet tā rindas no ur_data.db PIRMS meklēšanas, nozaru, TOP, Struktūras,
//     Pensionāra katalogu būves (registrs/build/izslegsana.php). Tāpēc tas pazūd arī no
//     sarakstiem citu uzņēmumu lapās (tuvākie uzņēmumi, rangu navigators, konkurenti), no vietnes
//     kartes un kājenes TOP. Citu uzņēmumu PAŠU datos (dalībnieks, amatpersona, reorganizācijas otra
//     puse) nosaukums paliek kā teksts bez saites — tie ir otra uzņēmuma dati.
//
// PATS SARAKSTS ir atsevišķā failā izslegtie_saraksts.php, kas NAV publiskajā pirmkoda pakotnē
// (tools/build_download.php EXCLUDE_FILES): saraksts ar uzņēmumiem, kas lūguši profilu izņemt,
// pats ir informācija par tiem. Ja faila nav (lejupielādēts kods), saraksts ir tukšs.
//
// Kā izņemt jaunu uzņēmumu: (1) ieraksts izslegtie_saraksts.php; (2) izvietot šo failu serverī —
// lapa atdod 410 uzreiz; (3) būve (nakts 02:00 vai registrs.build_nodl) — pazūd no katalogiem;
// (4) MI keša fails registrs/ai_cache/x/DD/DD/{regnr}.json jāizdzēš serverī ar roku.
declare(strict_types=1);

if (!function_exists('reg_izslegtie')) {
    /** @return array<string,string> regnr (11 cipari) => piezīme (datums, pamats) */
    function reg_izslegtie(): array {
        static $saraksts = null;
        if ($saraksts !== null) return $saraksts;
        $saraksts = [];
        $fails = __DIR__ . '/izslegtie_saraksts.php';
        if (is_file($fails)) {
            $dati = require $fails;
            if (is_array($dati)) {
                foreach ($dati as $reg => $piezime) {
                    // Masīva atslēgas ar cipariem PHP kļūst par int — atjaunojam 11 ciparu virkni.
                    $r = preg_replace('/\D/', '', (string)$reg);
                    if (strlen($r) === 11) $saraksts[$r] = (string)$piezime;
                }
            }
        }
        return $saraksts;
    }
}

if (!function_exists('reg_izslegts')) {
    /** Vai uzņēmuma profils no vietnes izņemts. Pieņem arī int un kodu ar atstarpēm. */
    function reg_izslegts(string|int|null $reg): bool {
        if ($reg === null) return false;
        $r = preg_replace('/\D/', '', (string)$reg);
        return $r !== '' && isset(reg_izslegtie()[$r]);
    }
}

if (!function_exists('reg_izslegts_410_html')) {
    /**
     * Izvada HTML lapu «profils nav pieejams» ar HTTP 410. 410 (ne 404) meklētājiem
     * pasaka, ka adrese izņemta apzināti un neatgriezīsies — Google to izmet ātrāk.
     */
    function reg_izslegts_410_html(): void {
        if (!headers_sent()) {
            http_response_code(410);
            header('X-Robots-Tag: noindex');
            header('Cache-Control: no-store');
        }
        $f = dirname(__DIR__, 2) . '/410.php';   // docroot (registrs/lib → ../..), nav atkarīgs no DOCUMENT_ROOT
        if (is_file($f)) {
            require $f;
        } else {
            echo '<!DOCTYPE html><html lang="lv"><head><meta charset="UTF-8"><meta name="robots" content="noindex">'
                . '<title>Profils nav pieejams - Saraksts.lv</title></head><body>'
                . '<p>Šī uzņēmuma profils vietnē nav pieejams.</p><p><a href="/">Sākumlapa</a></p></body></html>';
        }
    }
}
