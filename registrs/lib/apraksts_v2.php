<?php
// server/registrs/lib/apraksts_v2.php — sadaļas «Apraksts» noteikumi.
//
// Lapā: page_builder.php → reg_apraksts2_teksts(reg_apraksts2_konteksts(...)); īsais režīms, bez_dublikatiem.
// Paraugi: test_tools/registrs/apraksts_v2/paraugi.php; invarianti: .../parbaude.php.
//
// Kāpēc jauns: vecais teksts (description_builder.php) vienu skaitli pārvērta vairākos
// sarkanos verdiktos ("tehniska maksātnespēja", "apēduši", "dziļas grūtības"), izvēlējās
// nejaušu variantu katrā ielādē, vienotus mācību grāmatu sliekšņus lietoja visām nozarēm
// (likviditāte > 3 = "neefektīva" 37 % uzņēmumu) un vecuma teikumā visiem solīja
// "atzinību tirgū". Uz to 2026-11-16 atnāca pirmstiesas pretenzija.
//
// Noteikumi:
//  1. Katrs teikums balstās uz skaitli vai faktu no lapas datiem. Nav prognožu, diagnožu
//     ("maksātnespēja", "grūtības") un metaforu.
//  2. Viens fakts — viens teikums. ROA, ROE, ROCE, saistības/kapitāls un aktīvu aprite ir
//     tās pašas peļņas un kapitāla dalījumi — tekstā tos neliek. Altmana Z' ir modeļa
//     vērtējums, ne fakts. Visi paliek tabulā «Finanšu rādītāji un riska analīze».
//  3. Mērogs: lielumu salīdzina ar bilanci, iepriekšējo gadu, vēsturi vai nozari.
//  4. Stiprās puses un uzmanības punkti rodas tikai no datiem; vājības neizdomā līdzsvara dēļ.
//  5. Vecums maina to, ko interpretē (pirmais periods, nav apgrozījuma), nevis toni.
//     2026-11-19 mērīts: negatīvs pašu kapitāls ir ~30 % uzņēmumu jebkurā vecumā.
//  6. Teksts ir deterministisks: tie paši dati → tas pats teksts.
//  7. Krāsas (lietotāja lēmums 2026-11-19): virsraksti «Stiprās puses:» un «Kam pievērst uzmanību:» teksta
//     pamatkrāsā; to punkti zaļi / sarkani — riska joslas palete (#1e7d34 / #b3261e), kontrasts uz balta
//     5,2:1 un 6,4:1 (WCAG AA ≥ 4,5:1). Tiesiskie ieraksti savu krāsu nenes — punktus krāso viens span.
//  8. Salīdzina tikai salīdzināmo: nepilnu periodu apgrozījumu — pēc mēneša vidējā (3035
//     uzņēmumiem iepriekšējais periods ir nepilns; 551 "pieaugums" bija tikai garāks
//     periods), peļņu — tikai pilniem gadiem; latus pārrēķina eiro pēc oficiālā kursa;
//     katru gadu pēc SAVA pārskata mērvienības (Air Baltic: līdz 2017 eiro, no 2018 tūkst.).
declare(strict_types=1);

require_once __DIR__ . '/riska_kopsavilkums.php';
require_once __DIR__ . '/iepirkumi_kopsavilkums.php';
require_once __DIR__ . '/esfondi_kopsavilkums.php';
require_once __DIR__ . '/rangu_navigators.php';

// Ģenitīvs: "{nosaukums} ir Uzņēmumu reģistrā kopš 2025. gada februāra" — teikums bez
// dzimtes saskaņošanas (SIA/AS ir sieviešu, IK un nodibinājums — vīriešu dzimtē).
const APR2_MENESI_GEN = [1 => 'janvāra', 'februāra', 'marta', 'aprīļa', 'maija', 'jūnija',
    'jūlija', 'augusta', 'septembra', 'oktobra', 'novembra', 'decembra'];

// Sliekšņi vienuviet, lai tos var apspriest un mainīt bez teksta pārrakstīšanas.
const APR2_LVL_EUR           = 0.702804; // 1 EUR = 0,702804 LVL (oficiālais pārejas kurss)
const APR2_NEDAUDZ_NEG_KAP   = 0.05;  // |pašu kapitāls| < 5 % no aktīviem → "nedaudz negatīvs"
const APR2_KAPITALS_STIPRS   = 0.30;  // pašu kapitāls ≥ 30 % no aktīviem → stiprā puse
const APR2_LIKVID_STIPRA     = 1.2;   // apgrozāmie / īstermiņa saistības ≥ 1,2 → stiprā puse vai fakts
const APR2_NAUDA_DALA        = 0.5;   // nauda ≥ 50 % apgrozāmo → likviditāte naudā (stiprā puse);
                                      // citādi tie ir debitori/krājumi → tikai fakts
const APR2_PIEAUGUMS_STIPRS  = 0.10;  // apgrozījums +10 % → stiprā puse
const APR2_KRITUMS_UZMANIBA  = -0.20; // apgrozījums −20 % → uzmanības punkts
const APR2_PELNA_IZMAINA     = 0.30;  // peļņa/zaudējumi ±30 % → stiprā puse / uzmanības punkts
const APR2_PELNA_MIN         = 1000.0; // mazākas iepriekšējā gada summas procentos neizsaka
const APR2_RENTAB_STIPRA     = 0.10;  // peļņa ≥ 10 % no apgrozījuma → stiprā puse
const APR2_PILNS_GADS_MEN    = 11;    // salīdzināms gads: 11–13 mēn.; īsāks VAI garāks (pirmais
const APR2_PILNS_GADS_MAX    = 13;    // periods līdz 18 mēn.) — apgrozījumu pēc mēneša vidējā, peļņu nesalīdzina
const APR2_MENESI_DAT = [1 => 'janvārim', 'februārim', 'martam', 'aprīlim', 'maijam', 'jūnijam',
    'jūlijam', 'augustam', 'septembrim', 'oktobrim', 'novembrim', 'decembrim']; // "līdz 2024. gada 30. maijam"
const APR2_VESTURE_MIN_GADI  = 5;     // vēstures teikums tikai no 5 gadiem
const APR2_RANGS_MIN_KOPA    = 5;     // vieta nozarē tikai, ja nozarē ir vismaz 5 uzņēmumi
const APR2_ALGA_MIN_DARBIN   = 5;     // mazākam skaitam vidējā alga atklāj konkrētu cilvēku algu
const APR2_VALSTS_DALA       = 0.01;  // iepirkumi / ES piegādes ≥ 1 % no gada apgrozījuma
const APR2_VALSTS_MIN        = 10000.0;
const APR2_MAX_UZMANIBA      = 5;
const APR2_VIENIBU_LECIENS   = 300;   // blakus gadu apgrozījums ≥ 300× (abi ≥ 100 000) = avota mērvienības kļūda
// 2026-11-20 simulācija (100 + 500 + 3000 uzņēmumu, test_tools/registrs/apraksts_v2/simulacija_*):
const APR2_PILNS_MIN_PREC    = 11.5;  // salīdzināms gads pēc PRECĪZIEM mēnešiem: 15.02.–31.12. (10,5 mēn.) noapaļojās
const APR2_PILNS_MAX_PREC    = 12.5;  // uz 11 un tika salīdzināts tieši (+67 % vietā pēc mēneša vidējā +47 %)
const APR2_STIPRA_MIN_EUR    = 5000.0; // pašu kapitāla / naudas stiprā puse tikai no 5 000 EUR ("100 % no 147 EUR aktīviem")
const APR2_LIKV_UZM_MAX      = 0.9;   // apgrozāmie sedz < 90 % īstermiņa saistību UN trūkst ≥ 1 000 EUR
const APR2_LIKV_UZM_MIN_EUR  = 1000.0; // (sarkanais punkts par "397 245 pret 397 504 EUR" un "15 pret 50 EUR" bija troksnis)
const APR2_PELNA_IZM_MIN_EUR = 1000.0; // peļņas izmaiņa tikai, ja |Δ| ≥ 1 000 EUR UN ≥ 2 % apgrozījuma
const APR2_PELNA_IZM_APGR    = 0.02;  // ("peļņa samazinājusies par 62 %" bija −676 EUR)
const APR2_ZAUD_SERIJA       = 3;     // zaudējumi ≥ 3 pēdējos gadus pēc kārtas → uzmanības punkts
const APR2_PELNA_SERIJA      = 5;     // peļņa ≥ 5 pēdējos gadus pēc kārtas → stiprā puse
const APR2_APGR_BAZE_MIN     = 10000.0; // apgrozījuma izmaiņa tikai no ≥ 10 000 EUR bāzes ("17,7 reizes" no 560 EUR, +618 EUR)
const APR2_APGR_SERIJA       = 3;     // apgrozījums aug / sarūk ≥ 3 gadus pēc kārtas UN kopā ≥ 20 % (lēns kritums, ko
const APR2_APGR_SERIJA_IZM   = 0.20;  // gada slieksnis −20 % neredz: 339 000 → 181 208 EUR 4 gados)
const APR2_NAV_APGR_SERIJA   = 2;     // apgrozījuma nav ≥ 2 pēdējos gadus pēc kārtas → uzmanības punkts
const APR2_ZAUD_UZM_DALA     = 0.05;  // šī gada zaudējumi ≥ 1 000 EUR un ≥ 5 % apgrozījuma (rentabilitātes spoguļattēls)
const APR2_PARSKATS_KAVE_MEN = 19;    // nākamajam pārskatam jābūt ne vēlāk kā 12 + 7 mēn. pēc perioda beigām
const APR2_MAX_STIPRAS       = 5;     // kā uzmanības punktiem (partija 2: 7 zaļi punkti, trīs par vienu peļņu)
const APR2_SALIDZ_MIN_MEN    = 6;     // mēneša vidējo salīdzina tikai no 6 mēn. perioda (3 mēn. sezona ≠ tendence)
const APR2_PVN_IZSL_MEN      = 24;    // izslēgšana no PVN reģistra pēdējos 24 mēnešos → uzmanības punkts
const APR2_VID_B_UZMANIBA    = true;  // VID reitings B sarkanajā — lietotāja lēmums 2026-11-20. Dzīvajā lapā (bez_dublikatiem)
                                      // VID reitingu aprakstā nerāda vispār: B redzams riska joslas čipā "VID reitings: B".

if (!function_exists('apr2_eur')) {
    /** 4.97 → "5", 6.63 → "6,6" (bez nozīmes nulles aiz komata). */
    function apr2_skaitlis(float $v, int $dec = 1): string {
        $s = number_format($v, $dec, ',', "\u{00A0}");
        if ($dec > 0 && str_contains($s, ',')) $s = rtrim(rtrim($s, '0'), ',');
        return $s;
    }

    /** Vesels skaitlis ar atstarpi bez pārnesuma: 2772 → "2 772". */
    function apr2_sk(int $n): string {
        return number_format($n, 0, ',', "\u{00A0}");
    }

    /** 1441290 → "1 441 290 EUR"; no 10 milj. → "775,3 milj. EUR"; mīnuss kā "−". */
    function apr2_eur(float $v, string $val = 'EUR'): string {
        $a = abs(round($v));
        $s = $a >= 10000000
            ? apr2_skaitlis($a / 1000000, 1) . "\u{00A0}milj."
            : number_format($a, 0, ',', "\u{00A0}");
        return ($v < 0 && $a > 0 ? '−' : '') . $s . "\u{00A0}" . $val;
    }

    /** Daļa → procenti latviski: 0.000697 → "0,07 %", 0.47 → "47 %". */
    function apr2_proc(float $dala): string {
        $p = abs($dala) * 100;
        // Ne "0 %" faktam, kas nav nulle; un ne "<0,01 %" — "<" HTML tekstā sāk tagu (strip_tags nogrieza visu līdz beigām).
        if ($p > 0 && $p < 0.005) return "mazāk nekā 0,01\u{00A0}%";
        // Ne "100 %" daļai, kas nav vesela: "samazinājies par 100 %", kad palika 385 EUR (recenzija 2026-11-19).
        if ($p >= 99.5 && $p < 100) return "vairāk nekā 99\u{00A0}%";
        $dec = $p < 1 ? 2 : ($p < 10 ? 1 : 0);
        $s = number_format($p, $dec, ',', "\u{00A0}");
        if (str_contains($s, ',')) $s = rtrim(rtrim($s, '0'), ','); // "4,0" → "4", "0,00" → "0"
        return $s . "\u{00A0}%";
    }

    /** Vienmēr miljonos ar vienu zīmi: 4084465 → "4,1 milj. EUR" (pāros ar ≥ 10 milj. lielu otru summu). */
    function apr2_eur_milj(float $v, string $val = 'EUR', bool $fiks = false): string {
        $a = abs(round($v));
        // $fiks netiek lietots: «,0» apzināti nogriež (vārti «nenogriezta nulle», 2026-11-19), arī pārī — "18 → 13,6 milj."
        $sk = $fiks ? number_format($a / 1000000, 1, ',', "\u{00A0}") : apr2_skaitlis($a / 1000000, 1);
        return ($v < 0 && $a > 0 ? '−' : '') . $sk . "\u{00A0}milj.\u{00A0}" . $val;
    }

    /** Vienmēr pilnos ciparos (arī ≥ 10 milj.) — punktā, kur otra summa ir maza ("6 740 EUR pret 73 897 412 EUR"). */
    function apr2_eur_pilns(float $v, string $val = 'EUR'): string {
        $a = abs(round($v));
        return ($v < 0 && $a > 0 ? '−' : '') . number_format($a, 0, ',', "\u{00A0}") . "\u{00A0}" . $val;
    }

    /** "21.04.2020" / " 2020-04-21 " → "2020-04-21"; cits → "" (tiesisko ierakstu datumi salīdzināmi kā virknes). */
    function apr2_iso(string $d): string {
        $d = trim($d);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $d, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        return '';
    }

    /** Procenti aiz zīmes ("apgrozījums −99,7 %"): apr2_proc vārdi "vairāk nekā 99 %" aiz "−" neder. */
    function apr2_proc_z(float $dala): string {
        $p = abs($dala) * 100;
        if ($p >= 99.5 && $p < 100) {
            $s = number_format($p, $p >= 99.95 ? 2 : 1, ',', "\u{00A0}");
            return ($s === '100,00' ? '99,99' : $s) . "\u{00A0}%";
        }
        return apr2_proc($dala);
    }

    /** Latviešu skaitļa saskaņošana: 1, 21, 101 → vienskaitlis (izņemot 11, 111). */
    function apr2_vsk(int $n): bool {
        return $n % 10 === 1 && $n % 100 !== 11;
    }

    /** "2024-03-31" → UTC pusnakts laikspiedols. Dienu starpībām: lapa (Europe/Riga) un CLI (UTC) citādi atšķīrās
     *  par vasaras laika stundu — 470,04 pret 470 dienām, "6 782" pret "6 783 EUR mēnesī" (audits 4). */
    function apr2_t(string $ymd) {
        $ymd = substr(trim($ymd), 0, 10);
        return $ymd === '' ? false : strtotime($ymd . ' 00:00:00 UTC');
    }

    function apr2_datums_iss(string $ymd): string {
        $t = strtotime($ymd);
        return $t === false ? $ymd : date('d.m.', $t);
    }

    /** "2004-09-02" → "2004. gada septembra" (ģenitīvā, teikumam "kopš …"). */
    function apr2_kops(string $ymd): string {
        $t = $ymd !== '' ? strtotime($ymd) : false;
        return $t === false ? '' : date('Y', $t) . '. gada ' . APR2_MENESI_GEN[(int)date('n', $t)];
    }

    function apr2_menesi(string $no, string $lidz): ?int {
        $a = apr2_t($no); $b = apr2_t($lidz);
        if ($a === false || $b === false || $b < $a) return null;
        return (int)round(($b - $a) / (86400 * 30.4375));
    }

    /** Precīzs mēnešu skaits (ieskaitot abas dienas) — mēneša vidējam; apaļie mēneši to kropļo. */
    function apr2_menesi_prec(string $no, string $lidz): ?float {
        $a = apr2_t($no); $b = apr2_t($lidz);
        if ($a === false || $b === false || $b < $a) return null;
        return (($b - $a) / 86400 + 1) / 30.4375;
    }

    /** Perioda garums tekstam: ", 7 mēn." vai īsam periodam ", 12 dienas" (ne "0 mēn."). */
    function apr2_garums(string $no, string $lidz, ?int $men): string {
        if ($men === null) return '';
        if ($men >= 1) return ", {$men} mēn.";
        $d = (int)round((apr2_t($lidz) - apr2_t($no)) / 86400) + 1;
        return ', ' . $d . (apr2_vsk($d) ? ' diena' : ' dienas');
    }

    /** "2,9 reizes", bet "21 reizi", "131 reizi" (vienskaitlis skaitļiem, kas beidzas ar 1, ne 11). */
    function apr2_reizes(float $v): string {
        $s = apr2_skaitlis($v, $v >= 10 ? 0 : 1); // "119,4 reizes" — lieka precizitāte
        if (str_contains($s, ',')) return $s . "\u{00A0}reizes"; // nedalāmā atstarpe kā summās (audits 3)
        return $s . (apr2_vsk((int)str_replace("\u{00A0}", '', $s)) ? "\u{00A0}reizi" : "\u{00A0}reizes");
    }

    function apr2_reizinatajs(?string $rtn): float {
        switch (strtoupper(trim((string)$rtn))) {
            case 'THOUSANDS': return 1000.0;
            case 'MILLIONS':  return 1000000.0;
            default:          return 1.0;
        }
    }

    /** Teikuma sākums ar lielo burtu ("periodā …" → "Periodā …"). */
    function apr2_ucfirst(string $s): string {
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
    }

    /** Pirmais burts mazais, ja tas nav saīsinājums ("IT pakalpojumi" paliek). */
    function apr2_lcfirst(string $s): string {
        $a = mb_substr($s, 0, 1, 'UTF-8');
        $b = mb_substr($s, 1, 1, 'UTF-8');
        return ($b !== '' && mb_strtolower($b, 'UTF-8') === $b)
            ? mb_strtolower($a, 'UTF-8') . mb_substr($s, 1, null, 'UTF-8') : $s;
    }

    /** "7112" → "71.12"; tukšs vai "0000" → "". */
    function apr2_nace(string $kods): string {
        $d = (string)preg_replace('/\D/', '', $kods);
        if ($d === '' || trim($d, '0') === '') return '';
        return strlen($d) > 2 ? substr($d, 0, 2) . '.' . substr($d, 2) : $d;
    }

    /** VID tabulas skaitlis ("51 261.62", "~ 1 140.06") → float. */
    function apr2_vid_sk($v): ?float {
        $s = (string)preg_replace('/[^\d.\-]/', '', (string)$v);
        return ($s === '' || !is_numeric($s)) ? null : (float)$s;
    }

    /**
     * Pasūtītāja nosaukums teikumam. EIS dažām iestādēm raksta "RD iestāde: … RD iestādes
     * kods: 230" un VISUS LIELOS BURTUS — tos nelokām (latviski īpašvārdu reģistru nevar
     * atjaunot automātiski), bet izlaižam.
     */
    function apr2_pasutitajs(string $s): string {
        $s = (string)preg_replace('/^\s*RD iestāde:\s*/u', '', $s);
        $s = (string)preg_replace('/\.?\s*RD iestādes kods:\s*\d+\s*$/u', '', $s);
        $s = trim((string)preg_replace('/\s{2,}/u', ' ', $s));
        $burti = mb_strlen((string)preg_replace('/[^\p{L}]/u', '', $s));
        $lielie = mb_strlen((string)preg_replace('/[^\p{Lu}]/u', '', $s));
        return ($burti === 0 || $lielie > 0.6 * $burti) ? '' : $s;
    }
}

/**
 * Uzņēmumi, kuru lapā sadaļu «Apraksts» NERĀDA (tukšs teksts → description_panel.php paneli neizvada).
 * Saraksts kodā, ne datubāzē: nakts būve to nepārraksta, tāpēc apraksts atkārtoti neparādās.
 * Katram ierakstam — iemesls un datums.
 */
const REG_APRAKSTS_IZSLEGTI = [
];

if (!function_exists('reg_apraksts_izslegts')) {
    function reg_apraksts_izslegts(string $reg): bool {
        // Pilnībā izņemtiem profiliem (izslegtie.php) apraksta nav arī bez ieraksta šeit.
        require_once __DIR__ . '/izslegtie.php';
        return isset(REG_APRAKSTS_IZSLEGTI[trim($reg)]) || reg_izslegts($reg);
    }
}

if (!function_exists('reg_apraksts2_konteksts')) {
    /**
     * Fakti aprakstam no build_page_data() rezultāta. Atsevišķi no teksta, lai
     * noteikumus var testēt ar sintētiskiem datiem bez datubāzes.
     * Visas naudas summas — pēdējā pārskata valūtā, jau pareizinātas ar mērvienību.
     */
    function reg_apraksts2_konteksts(array $pd): array {
        $main = $pd['dati_php_rowData'] ?? [];
        $res = (array)($pd['results'] ?? []);
        $ctx = [
            'reg' => (string)($pd['search_reg_nr'] ?? ($main['regcode'] ?? '')),
            'nosaukums' => (string)($pd['companyTitleForHtml'] ?? ($main['name'] ?? '')),
            'registrets' => substr(trim((string)($main['registered'] ?? '')), 0, 10),
            // Likvidēts (L) vai reorganizācijā izslēgts (R): vairāk nekā puse lapu. Tādam
            // "ir reģistrā kopš …" un "stiprās puses" ir nepatiesi.
            // Lapas paša pazīme (statuss Likvidēts/Reorganizēts/Cits) + karogs, ja to nav: 646 MIL
            // ierakstiem ir izslēgšanas datums bez L/R, un lapa tos rāda kā likvidētus.
            'izslegts' => !empty($pd['is_liquidated']) || in_array(trim((string)($main['closed'] ?? '')), ['L', 'R'], true),
            'izslegts_dat' => substr(trim((string)($main['terminated'] ?? '')), 0, 10),
            // Biedrībām un nodibinājumiem nav īpašnieku — "(īpašnieku daļa)" tikai komersantiem.
            'tips' => trim((string)($main['type'] ?? '')),
            // Lietotāja lēmums 2026-11-20: «Apraksts» nedublē «Papildu reģistri un dati» brīdinājumus un paziņojumus —
            // riska josla jau rāda tiesisko karogu (procesi, sankcijas, nodrošinājumi / "Nav reģistrētu procesu"), VID
            // reitingu, PVN statusu, gada pārskata statusu un pašu kapitāla statusu ar summu. false = vecā uzvedība (testiem).
            'bez_dublikatiem' => true,
            'komersants' => in_array(trim((string)($main['type'] ?? '')), ['SIA', 'AS', 'IK', 'IND', 'KS', 'PS', 'ZEM', 'ZVJ', 'KB', 'SE', 'EIG'], true),
            'periods_men_prec' => null, 'iepr_men_prec' => null, 'konsolidets' => false,
            'tiesiskie' => [], // visi AKTĪVIE tiesiskie ieraksti: [['lvl' => risk|warn, 't' => teksts, 'no' => Y-m-d]]
            // Īsais sākums (lietotāja lēmums 2026-11-19): pirms sarakstiem tikai reģistrācija un PVN — pārējais
            // (gada rezultāts, vieta nozarē, darbinieki, vēsture, VID nodokļi, iepirkumi, NACE) ir lapas tabulās.
            // false = pilnais teksts — NAV validēts kopš 2026-11-20 punktu formāta (APR2_PILNS=1 dod atkārtotas summas:
            // stāstījuma teikums + punkts); pirms ieslēgšanas jāpielāgo.
            'isais' => true,
            'gads' => null, 'veids' => null, 'valuta' => 'EUR',
            'periods_no' => '', 'periods_lidz' => '', 'periods_men' => null,
            'pirmais_periods' => false, 'kalendars' => true,
            'apgrozijums' => null, 'rezultats' => null, 'pirms_nodokliem' => null, 'procenti' => null,
            'gads_iepr' => null, 'apgrozijums_iepr' => null, 'rezultats_iepr' => null, 'iepr_men' => null,
            'aktivi' => null, 'pasu_kapitals' => null, 'apgrozamie' => null, 'iss_saistibas' => null,
            'nauda' => null, 'debitori' => null, 'krajumi' => null,
            'darbinieki' => null,
            'vesture' => [], // gads => ['apgr' => ?float, 'rez' => ?float, 'men' => ?int, 'latos' => bool]
            'nace_kods' => '', 'nace_nosaukums' => '', 'pvn_no' => '', 'rangs' => null,
            'vid_reitings' => '', 'vid_skaidrojums' => '', 'vid_nodokli' => null,
            'iepirkumi' => null, 'esfondi' => null, 'sankcijas' => [],
            'tiesiskais' => ['has' => false, 'level' => 'ok', 'head' => '', 'no' => ''],
        ];

        $fd = $pd['allProcessedData'] ?? [];
        $fs_rows = [];
        foreach ((array)($pd['results']['financial_statements'] ?? []) as $r) {
            if (isset($r['id'])) $fs_rows[(string)$r['id']] = $r;
        }
        $periods = function (array $t) use ($fs_rows): array {
            $sid = (string)($t['balance_data']['statement_id'] ?? ($t['income_data']['statement_id'] ?? ''));
            $r = $fs_rows[$sid] ?? [];
            return [substr((string)($r['year_started_on'] ?? ''), 0, 10), substr((string)($r['year_ended_on'] ?? ''), 0, 10)];
        };
        $num = function ($arr, string $k, float $m): ?float {
            $v = is_array($arr) ? ($arr[$k] ?? null) : null;
            return is_numeric($v) ? (float)$v * $m : null;
        };

        if (!empty($fd)) {
            // Kopīgais likums (parskata_izvele.php 2. p.): ja uzņēmumam ir KAUT VIENS paša pārskats (UGP),
            // konsolidētos neņem vispār. Agrāk ņēma pēdējā gada pirmo veidu — 40203339923 rādīja koncerna
            // 159,9 milj. EUR un 536 darbiniekus kā paša (paša UGP: 0 un 0).
            $ir_ugp = false;
            foreach ($fd as $gt) if (isset($gt['UGP'])) { $ir_ugp = true; break; }
            $veids = $ir_ugp ? 'UGP' : (!empty($fd[array_key_last($fd)]) ? array_key_first($fd[array_key_last($fd)]) : null);
            $yk = null;
            foreach ($fd as $gk => $gt) if ($veids !== null && isset($gt[$veids])) $yk = $gk;
            $y = (int)$yk;
            $types = $yk !== null ? $fd[$yk] : [];
            $ctx['konsolidets'] = $veids !== null && $veids !== 'UGP' && str_contains(strtoupper((string)$veids), 'UKGP');
            if ($veids !== null && $yk !== null) {
                $t = $types[$veids];
                $m = apr2_reizinatajs($t['fs_data']['rounded_to_nearest'] ?? null);
                $b = $t['balance_data'] ?? null;
                $i = $t['income_data'] ?? null;
                [$no, $lidz] = $periods($t);
                $ctx['gads'] = $y;
                $ctx['veids'] = $veids;
                $ctx['valuta'] = (string)($t['fs_data']['currency'] ?? 'EUR') ?: 'EUR';
                $ctx['periods_no'] = $no;
                $ctx['periods_lidz'] = $lidz;
                $ctx['periods_men'] = ($no !== '' && $lidz !== '') ? apr2_menesi($no, $lidz) : null;
                $ctx['periods_men_prec'] = ($no !== '' && $lidz !== '') ? apr2_menesi_prec($no, $lidz) : null;
                $ctx['kalendars'] = $no === '' || $lidz === ''
                    || (substr($no, 5) === '01-01' && substr($lidz, 5) === '12-31' && substr($no, 0, 4) === substr($lidz, 0, 4));
                $ctx['apgrozijums'] = $num($i, 'net_turnover', $m);
                $ctx['rezultats'] = $num($i, 'net_income', $m);
                $ctx['pirms_nodokliem'] = $num($i, 'income_before_income_taxes', $m);
                $ctx['procenti'] = $num($i, 'interest_expenses', $m);
                $ctx['aktivi'] = $num($b, 'total_assets', $m);
                $ctx['pasu_kapitals'] = $num($b, 'equity', $m);
                $ctx['apgrozamie'] = $num($b, 'total_current_assets', $m);
                $ctx['iss_saistibas'] = $num($b, 'current_liabilities', $m);
                $ctx['ilgt_saistibas'] = $num($b, 'non_current_liabilities', $m);
                $ctx['nauda'] = $num($b, 'cash', $m);
                $ctx['debitori'] = $num($b, 'accounts_receivable', $m);
                $ctx['krajumi'] = $num($b, 'inventories', $m);
                $e = $t['fs_data']['employees'] ?? null;
                $ctx['darbinieki'] = is_numeric($e) ? (int)round((float)$e) : null;

                // Pirmais periods: sākas ne vēlāk kā 45 dienas pēc reģistrācijas UN agrāka pārskata nav
                // (~660 uzņēmumu: īss 2024. periods 19.11.–31.12., un 2025. gads tika saukts par pirmo).
                $reg_t = apr2_t($ctx['registrets']);
                $no_t = $no !== '' ? apr2_t($no) : false;
                $ctx['pirmais_periods'] = $reg_t !== false && $no_t !== false && $no_t - $reg_t <= 45 * 86400
                    && (int)array_key_first(array_filter($fd, fn($gt) => isset($gt[$veids]))) === $y;

                // Vēsture: tikai tā paša veida pārskati (UGP ar UGP), katrs gads pēc SAVAS
                // mērvienības; lati — pēc oficiālā kursa. Citas valūtas nesalīdzinām.
                foreach ($fd as $gy => $gt) {
                    $tt = $gt[$veids] ?? null;
                    if (!is_array($tt)) continue;
                    $gval = (string)($tt['fs_data']['currency'] ?? 'EUR') ?: 'EUR';
                    if ($gval === $ctx['valuta']) $kurss = 1.0;
                    elseif ($gval === 'LVL' && $ctx['valuta'] === 'EUR') $kurss = 1 / APR2_LVL_EUR;
                    else continue;
                    $mm = apr2_reizinatajs($tt['fs_data']['rounded_to_nearest'] ?? null) * $kurss;
                    [$hno, $hlidz] = $periods($tt);
                    $ctx['vesture'][(int)$gy] = [
                        'apgr' => $num($tt['income_data'] ?? null, 'net_turnover', $mm),
                        'rez' => $num($tt['income_data'] ?? null, 'net_income', $mm),
                        'men' => ($hno !== '' && $hlidz !== '') ? apr2_menesi($hno, $hlidz) : null,
                        'men_prec' => ($hno !== '' && $hlidz !== '') ? apr2_menesi_prec($hno, $hlidz) : null,
                        'latos' => $kurss !== 1.0,
                        // Katram gadam SAVS kalendāra karogs: 12.10.2022–31.12.2023 nav "2023. gadā", lai
                        // gan pēdējais gads ir kalendārs (recenzija: 118 no 15 000).
                        'no' => $hno, 'lidz' => $hlidz,
                        'kal' => $hno === '' || $hlidz === '' ? null
                            : (substr($hno, 5) === '01-01' && substr($hlidz, 5) === '12-31' && substr($hno, 0, 4) === substr($hlidz, 0, 4)),
                    ];
                }
                ksort($ctx['vesture']);
                // Avota mērvienības kļūdas: 50003050931 2010.–2011. gadā ~130 tūkst. LVL ar atzīmi ONES, 2012. —
                // 122 milj. (agrākie acīmredzot tūkstošos). Blakus gadi, abi ≥ 100 000, ar lēcienu ≥ 300× nav
                // salīdzināmi — vēsturi un salīdzinājumus ņem tikai no pēdējā lēciena līdz šim gadam.
                $iepr_v = null;
                foreach ($ctx['vesture'] as $gy => $vv) {
                    if ($iepr_v !== null && $vv['apgr'] !== null && $iepr_v['apgr'] !== null
                        && $vv['apgr'] >= 1e5 && $iepr_v['apgr'] >= 1e5
                        && ($vv['apgr'] / $iepr_v['apgr'] >= APR2_VIENIBU_LECIENS || $iepr_v['apgr'] / $vv['apgr'] >= APR2_VIENIBU_LECIENS)) {
                        $ctx['vesture'] = array_filter($ctx['vesture'], fn($g) => $g >= $gy, ARRAY_FILTER_USE_KEY);
                    }
                    $iepr_v = $vv;
                }
                $iepr = $ctx['vesture'][$y - 1] ?? null;
                if ($iepr !== null) {
                    $ctx['gads_iepr'] = $y - 1;
                    $ctx['apgrozijums_iepr'] = $iepr['apgr'];
                    $ctx['rezultats_iepr'] = $iepr['rez'];
                    $ctx['iepr_men'] = $iepr['men'];
                    $ctx['iepr_men_prec'] = $iepr['men_prec'];
                }
            }
        }

        $ctx['nace_kods'] = apr2_nace((string)($pd['nace_code'] ?? ''));
        $ctx['nace_nosaukums'] = trim((string)($pd['nace_description'] ?? ''));

        $vid = $pd['vid_panel_data'] ?? [];
        $rating = $vid['rating'] ?? null;
        if (is_array($rating)) {
            $ctx['vid_reitings'] = trim((string)($rating['Reitings'] ?? ''));
            $ctx['vid_skaidrojums'] = trim((string)($rating['Skaidrojums'] ?? ''));
        }
        $pvn = $vid['pvn'] ?? null;
        if (is_array($pvn) && trim((string)($pvn['Aktivs'] ?? '')) === 'ir' && trim((string)($pvn['Izslegts'] ?? '')) === ''
            && preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', (string)($pvn['Registrets'] ?? ''), $pm)) {
            $ctx['pvn_no'] = "{$pm[3]}-{$pm[2]}-{$pm[1]}";
        }
        $ctx['pvn_izslegts'] = '';
        if (is_array($pvn) && trim((string)($pvn['Aktivs'] ?? '')) !== 'ir'
            && preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', (string)($pvn['Izslegts'] ?? ''), $pm)) {
            $ctx['pvn_izslegts'] = "{$pm[3]}-{$pm[2]}-{$pm[1]}";
        }
        $labakais = null;
        foreach ((array)($vid['tax_table'] ?? []) as $row) {
            if (!is_array($row) || (int)($row['Taksacijas_gads'] ?? 0) <= 0) continue;
            if ($labakais === null || (int)$row['Taksacijas_gads'] > (int)$labakais['Taksacijas_gads']) $labakais = $row;
        }
        if ($labakais !== null) {
            $s = apr2_vid_sk($labakais['Samaksato_VID_administreto_nodoklu_kopsumma_tukst_EUR'] ?? null);
            $dn = $labakais['Videjais_nodarbinato_personu_skaits_cilv'] ?? null;
            if ($s !== null && $s > 0) {
                $ctx['vid_nodokli'] = [
                    'gads' => (int)$labakais['Taksacijas_gads'],
                    'summa' => round($s * 1000), // VID dati tūkstošos eiro
                    'alga' => apr2_vid_sk($labakais['avg_gross_salary'] ?? null),
                    'darbinieki' => is_numeric($dn) ? (int)$dn : null,
                ];
            }
        }

        // Vieta nozarē — tikai, ja rangs rēķināts no TĀ PAŠA apgrozījuma (citādi tas ir cits gads).
        // Izslēgtam uzņēmumam vieta starp tagad strādājošajiem nav fakts par šodienu.
        // nace_rangs ņem katra uzņēmuma PĒDĒJO pārskatu (jebkura gada, lati nepārrēķināti) — tāpēc
        // vietu sakām tikai par svaigu eiro pārskatu (recenzija: "2009. gadā … 351 416 LVL … 14. vieta").
        if (!$ctx['izslegts'] && $ctx['valuta'] === 'EUR' && $ctx['gads'] !== null && $ctx['gads'] >= (int)date('Y') - 2
            && $ctx['apgrozijums'] !== null && $ctx['apgrozijums'] > 0 && $ctx['nace_kods'] !== ''
            && preg_match('/^\d{11}$/', $ctx['reg'])) {
            try {
                $rdb = reg_rangu_db();
                if ($rdb) {
                    $kods = reg_rangu_kods((string)($pd['nace_code'] ?? ''));
                    $st = $rdb->prepare('SELECT rangs, turnover FROM nace_rangs WHERE regcode = ? AND code = ?');
                    $st->execute([$ctx['reg'], $kods]);
                    $rr = $st->fetch(PDO::FETCH_NUM);
                    if ($rr) {
                        $st = $rdb->prepare('SELECT max(rangs) FROM nace_rangs WHERE code = ?');
                        $st->execute([$kods]);
                        $kopa = (int)$st->fetchColumn();
                        if ($kopa >= APR2_RANGS_MIN_KOPA
                            && abs((float)$rr[1] - $ctx['apgrozijums']) <= max(1.0, 0.005 * $ctx['apgrozijums'])) {
                            $ctx['rangs'] = ['vieta' => (int)$rr[0], 'kopa' => $kopa];
                        }
                    }
                }
            } catch (Throwable $e) { /* bez vietas nozarē */ }
        }

        // Iepirkumi — TIKAI caur kopīgo apkopojumu (naiva summa dod simtiem miljonu, kuru nav).
        $ik = reg_iepirkumi_ai_kopsavilkums((array)($res['iepirkumi'] ?? []));
        if ($ik) {
            $n = (int)$ik['ligumu_skaits'] - (int)$ik['bez_publicetas_summas'] - (int)$ik['citas_valutas_ligumi'];
            $l = $ik['lielakie_ligumi'][0] ?? null;
            if ($n > 0 && (float)$ik['kopa_eur'] > 0) {
                // Sākuma gads — tikai no kopsummā ieskaitītajiem līgumiem (ar summu, viens uzvarētājs):
                // perioda "no" ietver arī līgumus bez summas (875 no 3000: "kopš 2020", lai gan līgums 2022).
                $gadi_ar_summu = array_keys(array_filter((array)($ik['pa_gadiem'] ?? []), fn($g) => (float)($g['summa_eur'] ?? 0) > 0));
                $ctx['iepirkumi'] = [
                    'n' => $n, 'kopa' => (float)$ik['kopa_eur'], 'no' => $gadi_ar_summu ? (string)min($gadi_ar_summu) : '',
                    'lielakais' => (is_array($l) && $l['summa_eur'] !== null) ? (float)$l['summa_eur'] : null,
                    'lielakais_gads' => is_array($l) ? substr((string)$l['datums'], 0, 4) : '',
                    'pasutitajs' => is_array($l) ? apr2_pasutitajs((string)$l['pasutitajs']) : '',
                ];
            }
        }
        $ek = reg_esfondi_ai_kopsavilkums((array)($res['es_fondi'] ?? []));
        if ($ek) {
            $san = $ek['ka_finansejuma_sanemejs'] ?? null;
            // Izpildītāja līgumi: skaita TIKAI ar summu (473 tekstos skaitā bija līgumi bez summas).
            $izp_l = array_filter((array)(reg_esfondi_kopsavilkums((array)($res['es_fondi'] ?? []))['izpilditajs']['ligumi'] ?? []),
                fn($l) => (float)($l['summa'] ?? 0) > 0);
            $ctx['esfondi'] = [
                'san_n' => is_array($san) ? (int)$san['projektu_skaits'] : 0,
                'san_es' => is_array($san) ? (float)$san['es_finansejums_eur'] : 0.0,
                'izp_n' => count($izp_l),
                'izp_summa' => (float)array_sum(array_map(fn($l) => (float)$l['summa'], $izp_l)),
            ];
        }

        // Sankcijas: rindas piesaistītas uzņēmumam (sanctions_subject_registration_number);
        // position_text_lv ir sankcionētās personas loma TAJĀ (patiesais labuma guvējs u. c.).
        foreach ((array)($res['sanctions'] ?? []) as $s) {
            if (!is_array($s)) continue;
            $l = trim((string)($s['list'] ?? ''));
            $saraksts = $l === 'ES_SANCTIONS' ? 'ES' : ($l === 'SDN_OFAC_SANCTIONS' ? 'ASV OFAC'
                : (trim((string)($s['list_text'] ?? '')) ?: $l));
            // "Īpašnieks (IK, IND, ZEM, ZVJ)" → "Īpašnieks": kodi iekavās ir avota klasifikators, ne teksts.
            $loma = trim((string)preg_replace('/\s*\((?:\p{Lu}{2,4}(?:,\s*)?)+\)\s*$/u', '', trim((string)($s['position_text_lv'] ?? ''))));
            $ctx['sankcijas'][$loma][$saraksts] = true;
        }
        // Lomas → saraksti. Personu skaitu NESKAITĀM: pēc VDAR skrubja vārdu nav, un viena persona
        // mēdz būt vairākos sarakstos (recenzija: "personas" daudzskaitlī par vienu cilvēku).
        ksort($ctx['sankcijas']);
        $ctx['sankcijas'] = array_map(fn($a) => array_keys($a), $ctx['sankcijas']);

        // Riska joslas "Pašu kapitāls" vērtējums (tas pats reg_risk_semaphore, ko rāda josla): ja josla brīdina (sarucis
        // ≥ 30 %, zem puses pamatkapitāla), zaļais pašu kapitāla punkts tai runātu pretī (audits 3: 9 no 377).
        $ctx['pk_josla'] = '';
        try {
            if (!function_exists('reg_risk_semaphore') && is_file(__DIR__ . '/risk_semaphore.php')) require_once __DIR__ . '/risk_semaphore.php';
            if (function_exists('reg_risk_semaphore')) {
                foreach ((array)(reg_risk_semaphore($pd)['signals'] ?? []) as $sg) {
                    if (is_array($sg) && ($sg['label'] ?? '') === 'Pašu kapitāls') $ctx['pk_josla'] = (string)($sg['status'] ?? '');
                }
            }
        } catch (Throwable $e) { /* bez joslas vērtējuma */ }

        $ts = reg_tiesiskais_kopsavilkums($res);
        $no = '';
        foreach ((array)($ts['proc'] ?? []) as $p) {
            if (reg_ts_date($p['proceeding_ended_on'] ?? '') !== '') continue;
            $s = reg_ts_date($p['proceeding_started_on'] ?? '');
            if ($s !== '' && ($no === '' || $s < $no)) $no = $s;
        }
        // Kaskāde dod VIENU virsrakstu; recenzija atrada, ka tā slēpa likvidāciju aiz sankcijām un VID
        // apturēšanu aiz TAP (ar stiprajām pusēm blakus). Tāpēc aprakstā — katrs aktīvais ieraksts.
        $min_no = function (array $rindas, string $lauks): string {
            $d = array_filter(array_map(fn($r) => apr2_iso(reg_ts_date($r[$lauks] ?? '') ?: (string)($r[$lauks] ?? '')), $rindas));
            return $d ? min($d) : '';
        };
        $akt_no = fn(string $kind): string => (string)(min(array_merge(['9999-12-31'], array_map(fn($r) => $r['start'],
            array_filter((array)($ts['rows'] ?? []), fn($r) => $r['active'] && ($kind === 'tap') === ($r['kind'] === 'tap') && $r['start'] !== '')))));  // 'cits' skaitās kā mn (kā kaskādē)
        $tk = [];
        if (!empty($ts['sank'])) $tk[] = ['lvl' => 'risk', 't' => 'sankcijas', 'no' => ''];
        if ((int)($ts['active_mn'] ?? 0) > 0) $tk[] = ['lvl' => 'risk', 't' => 'aktīvs maksātnespējas process', 'no' => $akt_no('mn')];
        if (!empty($ts['liq'])) {
            $tips = trim((string)($ts['liq'][0]['liquidation_type_text'] ?? ''));
            $tk[] = ['lvl' => 'risk', 't' => $tips !== '' ? apr2_lcfirst($tips) : 'likvidācija', 'no' => $min_no($ts['liq'], 'date_from')];
        }
        if ((int)($ts['active_tap'] ?? 0) > 0) $tk[] = ['lvl' => 'risk', 't' => 'aktīvs tiesiskās aizsardzības process', 'no' => $akt_no('tap')];
        if ((int)($ts['pt_melns_n'] ?? 0) > 0) $tk[] = ['lvl' => 'risk', 't' => 'PTAC melnajā sarakstā', 'no' => ''];
        if ((int)($ts['vid_akt'] ?? 0) > 0) {
            $vid_akt_r = array_filter((array)($ts['vid'] ?? []), fn($v) => trim((string)($v['Aizliegts_veikt_darijumus_lidz'] ?? '')) === ''
                && trim((string)($v['Lemuma_par_atjaunosanu_datums'] ?? '')) === '');
            $tk[] = ['lvl' => 'risk', 't' => 'VID apturējis saimniecisko darbību', 'no' => $min_no($vid_akt_r, 'Lemuma_datums')];
        }
        if (!empty($ts['susp']) && (int)($ts['vid_akt'] ?? 0) === 0) {
            // UR reģistrētā apturēšana bieži ir TĀ PATI, ko VID tabula — tad tikai viena rinda.
            $tips = trim((string)($ts['susp'][0]['suspension_code_text'] ?? ''));
            $tk[] = ['lvl' => 'risk', 't' => 'reģistrēta ' . ($tips !== '' ? mb_strtolower($tips, 'UTF-8') : 'darbības apturēšana'),
                     'no' => $min_no($ts['susp'], 'date_from')];
        }
        if (!empty($ts['sec'])) {
            $n = count($ts['sec']);
            $veidi = array_values(array_unique(array_filter(array_map(fn($r) => mb_strtolower(trim((string)($r['securing_measure_type_text'] ?? '')), 'UTF-8'), $ts['sec']))));
            // Kas uzlicis (2026-11-20): VID aizliegums un tiesu izpildītāja aizliegums lasītājam nozīmē dažādu.
            // Tikai iestādes VEIDS — tiesu izpildītāja vārds ir personas dati (gdpr_scrub to jau izņem).
            $iest = array_values(array_unique(array_filter(array_map(function ($r): string {
                $n = mb_strtolower(trim((string)($r['institution_name'] ?? '')), 'UTF-8');
                if ($n === '') return '';
                if (str_contains($n, 'valsts ieņēmumu') || str_starts_with($n, 'vid ') || $n === 'vid') return 'VID';
                if (str_contains($n, 'tiesu izpild')) return 'tiesu izpildītājs';
                if (str_contains($n, 'tiesa')) return 'tiesa';
                return '';
            }, $ts['sec']))));
            $veidi = array_merge($veidi, $iest ? [implode(' un ', $iest)] : []);
            $tk[] = ['lvl' => 'warn', 'no' => $min_no($ts['sec'], 'date_from'), 'det' => implode(', ', $veidi),
                     't' => $n === 1 ? 'reģistrēts nodrošinājuma līdzeklis' : 'reģistrēti ' . $n . (apr2_vsk($n) ? ' nodrošinājuma līdzeklis' : ' nodrošinājuma līdzekļi')];
        }
        if ((int)($ts['pt_sods_n'] ?? 0) > 0) $tk[] = ['lvl' => 'warn', 't' => 'PTAC lēmums ar soda naudu', 'no' => ''];
        foreach ($tk as &$x) if ($x['no'] === '9999-12-31') $x['no'] = '';
        unset($x);
        $ctx['tiesiskie'] = $tk;
        $ctx['tiesiskais'] = [
            'has' => (bool)($ts['has'] ?? false),
            'level' => (string)($ts['level'] ?? 'ok'),
            'head' => (string)($ts['head'] ?? ''),
            'no' => $no,
            // Kaskāde rāda vienu virsrakstu; sankcijas ir pirms maksātnespējas, un aktīvs process
            // citādi pazuda (40103552058: maksātnespēja kopš 2021, tekstā tikai sankcijas).
            'mn' => (int)($ts['active_mn'] ?? 0) > 0,
            'tap' => (int)($ts['active_tap'] ?? 0) > 0,
        ];
        return $ctx;
    }
}

if (!function_exists('reg_apraksts2_teksts')) {
    /** Apraksta HTML (tiek ievietots <p class="description-text">, tāpēc bez blokelementiem). */
    function reg_apraksts2_teksts(array $c): string {
        $val = $c['valuta'] ?: 'EUR';
        $eur = fn(float $v): string => apr2_eur($v, $val);
        $h = fn(string $s): string => htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $vards = $h((string)$c['nosaukums']);
        $gads = $c['gads'];
        $ir_parskats = $gads !== null;
        $rindas = [];
        $avoti = ['UR' => true];
        $stipras = [];
        $uzm = [];
        $iepr_vieturis = null; // sk. vēsturi: summu ieliek tikai, ja tā nav teikta citur
        $iepr_summa = '';
        $ts = $c['tiesiskais'];
        // Aktīva procesa (risk) laikā stipro pušu nav: pārskats parasti ir no laika
        // pirms procesa, un "pašu kapitāls 43 %" blakus maksātnespējai maldina.
        // Katrs aktīvais tiesiskais ieraksts ar SAVU datumu (kaskādes virsraksts slēpa pārējos).
        // Jebkurš 'risk' ieraksts (sankcijas, maksātnespēja, likvidācija, apturēšana, PTAC melnais
        // saraksts) noņem stiprās puses — arī tad, ja kaskādes virsraksts ir 'warn' (TAP).
        $tiesiskie = (array)($c['tiesiskie'] ?? []);
        $process_aktivs = (bool)array_filter($tiesiskie, fn($x) => $x['lvl'] === 'risk');
        $proc_kas = '';
        // Pelēkajai piezīmei — agrākais JEBKURA līmeņa ieraksts PĒC pārskata perioda beigām (partija 4: TAP kopš
        // 02.02.2026 blakus zaļajam "apgrozījums +42 %" bez piezīmes).
        $ier_datums = '';
        foreach ($tiesiskie as $x) {
            if ($x['no'] !== '' && $c['periods_lidz'] !== '' && $x['no'] > $c['periods_lidz'] && ($ier_datums === '' || $x['no'] < $ier_datums)) { $ier_datums = $x['no']; $proc_kas = $x['t']; }
        }
        // Bez dublikātiem piezīme atsaucas uz joslu kopumā — tad VISIEM aktīvajiem ierakstiem jābūt datētiem un pēc perioda
        // (audits 1: maksātnespēja kopš 09.06.2025 perioda iekšā, bet piezīme par 2026. g. nodrošinājumu).
        $visi_pec = $tiesiskie && !array_filter($tiesiskie, fn($x) => $x['no'] === '' || $c['periods_lidz'] === '' || $x['no'] <= $c['periods_lidz']);

        // --- 1. Kas tas ir ----------------------------------------------------------
        $r1 = [];
        $izslegts = !empty($c['izslegts']);
        $beigu_t = ($izslegts && ($c['izslegts_dat'] ?? '') !== '') ? strtotime($c['izslegts_dat']) : false;
        if ($izslegts) {
            // "bija … no … līdz …" — bez dzimtes saskaņošanas (SIA/AS — sieviešu, IK — vīriešu).
            $no_txt = apr2_kops($c['registrets']);
            if ($beigu_t !== false) {
                $r1[] = "{$vards} bija Uzņēmumu reģistrā" . ($no_txt !== '' ? " no {$no_txt}" : '') . ' līdz '
                    . date('Y', $beigu_t) . '. gada ' . (int)date('j', $beigu_t) . '. ' . APR2_MENESI_DAT[(int)date('n', $beigu_t)] . '.';
            } else {
                $r1[] = ($no_txt !== '' ? "{$vards} bija Uzņēmumu reģistrā kopš {$no_txt}. " : '') . 'Uzņēmums no reģistra ir izslēgts.';
            }
        } elseif (apr2_kops($c['registrets']) !== '') {
            $r1[] = "{$vards} ir Uzņēmumu reģistrā kopš " . apr2_kops($c['registrets']) . '.';
        }
        $isais = $c['isais'] ?? true;
        $bez = (bool)($c['bez_dublikatiem'] ?? false); // sk. reg_apraksts2_konteksts
        // "Šodiena" injicējama (ctx 'sodien' = Y-m-d): testi ar fiksētiem gadiem citādi kristu 2027./2028. gadā (audits 2).
        $sod_t = (($c['sodien'] ?? '') !== '' ? strtotime((string)$c['sodien']) : false) ?: time();
        $sod_y = (int)date('Y', $sod_t);
        if (!$isais && $c['nace_kods'] !== '' && $c['nace_nosaukums'] !== '') {
            $r1[] = 'Pamatdarbība — ' . $h(apr2_lcfirst($c['nace_nosaukums'])) . " (NACE {$c['nace_kods']}).";
        }
        if (!$izslegts && !$bez && apr2_kops($c['pvn_no']) !== '') { // izslēgtam VID PVN dati mēdz būt novecojuši; PVN čips joslā
            $r1[] = 'PVN maksātājs kopš ' . apr2_kops($c['pvn_no']) . '.';
            $avoti['VID'] = true;
        }
        // Īsajā tekstā gada teikuma nav, tāpēc punktiem bez salīdzinājuma ("pašu kapitāls … no aktīviem") gads
        // jāpasaka vienreiz (2026-11-20: 6 % aktīvo jaunākais pārskats ir 2024., ne 2025.). Novecojušam to
        // saka pelēkā piezīme beigās.
        $kave = $gads !== null && !$izslegts && $c['periods_lidz'] !== '' && (int)$gads >= $sod_y - 2
            && strtotime($c['periods_lidz'] . ' +' . APR2_PARSKATS_KAVE_MEN . ' months') < $sod_t;
        if ($isais && $gads !== null && !$izslegts && (int)$gads >= $sod_y - 2 && (!$kave || $bez)) {
            $pno = $c['periods_no']; $plidz = $c['periods_lidz'];
            $viens = $pno !== '' && $plidz !== '' && substr($pno, 0, 4) === substr($plidz, 0, 4);
            $perioda = ($pno !== '' && $plidz !== '')
                ? ($viens ? apr2_datums_iss($pno) : date('d.m.Y', strtotime($pno))) . '–' . date('d.m.Y', strtotime($plidz)) : '';
            if ($c['pirmais_periods'] && $perioda !== '') $r1[] = "Pirmais gada pārskats — par periodu {$perioda}.";
            elseif (!$c['kalendars'] && $perioda !== '') $r1[] = "Jaunākais gada pārskats — par periodu {$perioda}.";
            else $r1[] = "Jaunākais gada pārskats — par {$gads}. gadu.";
        }
        if ($isais && !empty($c['konsolidets'])) $r1[] = 'Finanšu dati — no koncerna konsolidētā pārskata (uzņēmuma paša pārskata nav).';
        if ($r1) $rindas[] = implode(' ', $r1);

        // --- 2. Gada rezultāts un bilance ----------------------------------------------
        $ak = $c['aktivi'];
        $pk = $c['pasu_kapitals'];
        $ap = $c['apgrozamie'];
        $is = $c['iss_saistibas'];
        $likv = ($ap !== null && $is !== null && $is > 0) ? $ap / $is : null;
        if ($ir_parskats) {
            $reg_t = $c['registrets'] !== '' ? apr2_t($c['registrets']) : false;
            $agraks_apgr = (bool)array_filter($c['vesture'], fn($v, $g) => $g < $gads && ($v['apgr'] ?? 0) > 0, ARRAY_FILTER_USE_BOTH);
            $jauns = !$agraks_apgr && ($c['pirmais_periods'] || ($reg_t !== false && (int)$gads - (int)date('Y', $reg_t) <= 1));
            // Novecojis pārskats (≥ 3 gadus vecs): tagadnes "stiprās puses" un "uzmanība" no tā maldina.
            $svaigs = (int)$gads >= $sod_y - 2;
            $ir_dat = $c['periods_no'] !== '' && $c['periods_lidz'] !== '';
            // Salīdzināms ir tikai 11–13 mēnešu periods. Pirmais periods mēdz būt līdz 18 mēn.
            // (2 042 uzņēmumiem iepriekšējais > 13 mēn. tika salīdzināts tieši ar 12 mēnešiem).
            // Pēc precīziem mēnešiem (2026-11-20): apaļie 15.02.–31.12. (10,5 mēn.) padarīja par "11" = pilnu gadu.
            $nesal = function ($mp, $m): bool {
                if ($mp !== null) return $mp < APR2_PILNS_MIN_PREC || $mp > APR2_PILNS_MAX_PREC;
                return $m !== null && ($m < APR2_PILNS_GADS_MEN || $m > APR2_PILNS_GADS_MAX);
            };
            $dalejs = $nesal($c['periods_men_prec'] ?? null, $c['periods_men']);
            $idal = $nesal($c['iepr_men_prec'] ?? null, $c['iepr_men']);
            $men_txt = $dalejs ? apr2_garums($c['periods_no'], $c['periods_lidz'], $c['periods_men']) : ''; // ne "0 mēn."
            // UR "gads" nekalendāram periodam nav viennozīmīgs (01.07.2024–30.06.2025 ir 2024,
            // 01.10.2023–30.09.2024 arī 2024) — tad saka tieši to, ko dati saka: "2023. gada pārskatā".
            $gl = function ($g) use ($c): string {
                $v = $c['vesture'][(int)$g] ?? null;
                if ($v === null || ($v['kal'] ?? null) === null) return $c['kalendars'] ? "{$g}. gadā" : "{$g}. gada pārskatā";
                return $v['kal'] ? "{$g}. gadā" : 'periodā ' . date('d.m.Y', strtotime($v['no'])) . '–' . date('d.m.Y', strtotime($v['lidz']));
            };
            // "2024. gadā 1 505 EUR" / "periodā 12.10.2022–31.12.2023: 9 472 EUR" (mēneši tad lieki — datumi tos saka).
            $gla = fn($g, string $summa, string $men = ''): string => str_starts_with($gl($g), 'periodā ') ? $gl($g) . ': ' . $summa : $gl($g) . ' ' . $summa . $men;
            // Sākuma datumam gads vajadzīgs, ja periods šķērso gadu mijas ("15.09.2024–31.12.2025").
            $per = '';
            if ($ir_dat) {
                $viena_gada = substr($c['periods_no'], 0, 4) === substr($c['periods_lidz'], 0, 4);
                $per = ' (' . ($viena_gada ? apr2_datums_iss($c['periods_no']) : date('d.m.Y', strtotime($c['periods_no'])))
                    . '–' . date('d.m.Y', strtotime($c['periods_lidz'])) . $men_txt . ')';
            }
            if ($c['pirmais_periods']) {
                $ievads = 'Pirmajā pārskata periodā' . $per;
            } elseif ($ir_dat && substr($c['periods_no'], 0, 4) !== substr($c['periods_lidz'], 0, 4)) {
                // Nekalendārs finanšu gads: "2025. gadā" maldinātu — rāda abus datumus.
                $ievads = 'Pārskata periodā' . $per;
            } else {
                $ievads = "{$gads}. gadā" . (($dalejs || !$c['kalendars']) ? $per : '');
            }

            // Apgrozījuma izmaiņa: pilniem gadiem — tieši, nepilniem — pēc mēneša vidējā ar PRECĪZIEM
            // mēnešiem (apaļie kropļoja: −28 % kļuva par −17 %) un tikai no 3 mēnešiem (12 dienu
            // perioda "mēneša vidējais" ir troksnis).
            $ai = $c['apgrozijums_iepr'];
            $izm = null;
            $izm_videjais = false;
            // Abi gadi identiski (apgrozījums UN rezultāts) = avota dublikāts, ne "0 % izmaiņa" (partija 3).
            $dublikats = $ai !== null && $c['apgrozijums'] !== null && (float)$c['apgrozijums'] == (float)$ai && (float)$ai > 0
                && $c['rezultats'] !== null && (float)$c['rezultats'] == (float)($c['rezultats_iepr'] ?? NAN);
            if (!$dublikats && $c['apgrozijums'] !== null && $ai !== null && $ai >= APR2_APGR_BAZE_MIN) {
                if ($dalejs || $idal) {
                    $pm = (float)($c['periods_men_prec'] ?? 0);
                    $im = (float)($c['iepr_men_prec'] ?? 0);
                    if ($pm >= APR2_SALIDZ_MIN_MEN && $im >= APR2_SALIDZ_MIN_MEN) {
                        $izm = ($c['apgrozijums'] / $pm) / ($ai / $im) - 1;
                        $izm_videjais = true;
                    }
                } else {
                    $izm = $c['apgrozijums'] / $ai - 1;
                }
            }
            $iepr_apg = $ai !== null ? $gla($c['gads_iepr'], $eur($ai), $idal ? ", {$c['iepr_men']} mēn." : '') : '';
            // Punktu formāts (lietotājs 2026-11-20: "punkti, nevis teikumi kā stāsts"): katrs punkts pats par sevi —
            // rādītājs, izmaiņa un ABAS summas ar gadiem: "apgrozījums +40 % (2024: 24 725 EUR → 2025: 34 512 EUR)".
            // Gads nekalendāram periodam — ar datumiem (UR "gads" tad nav viennozīmīgs).
            // Nepilnam vai garam periodam — datumi vai mēneši ("2024" par 16 mēnešu periodu maldinātu).
            $lb = function ($g) use ($c, $nesal): string {
                $v = $c['vesture'][(int)$g] ?? null;
                $ir_iepr = $c['gads_iepr'] !== null && (int)$g === (int)$c['gads_iepr'];
                $mp = $v['men_prec'] ?? ($ir_iepr ? ($c['iepr_men_prec'] ?? null) : ((int)$g === (int)$c['gads'] ? ($c['periods_men_prec'] ?? null) : null));
                $m = $v['men'] ?? ($ir_iepr ? $c['iepr_men'] : ((int)$g === (int)$c['gads'] ? $c['periods_men'] : null));
                $ned = $nesal($mp, $m);
                if ($v !== null && (($v['kal'] ?? null) === false || $ned) && ($v['no'] ?? '') !== '' && ($v['lidz'] ?? '') !== '') {
                    $viens = substr($v['no'], 0, 4) === substr($v['lidz'], 0, 4);
                    return ($viens ? apr2_datums_iss($v['no']) : date('d.m.Y', strtotime($v['no']))) . '–' . date('d.m.Y', strtotime($v['lidz']));
                }
                return $ned && $m !== null ? "{$g} ({$m} mēn.)" : (string)$g;
            };
            // Viena mērvienība visām punkta summām: visas ≥ 1 milj. un kāda ≥ 10 milj. → miljoni; kāda ≥ 10 milj., bet
            // cita < 1 milj. → pilni cipari (recenzija B: "6 740 EUR pret 73,9 milj. EUR"); citādi parastais apr2_eur.
            $rezims = function (float ...$v): string {
                $a = array_map('abs', $v);
                return max($a) < 10000000 ? '' : (min($a) >= 1000000 ? 'm' : 'p');
            };
            $fmt = fn(float $x, string $r): string => $r === 'm' ? apr2_eur_milj($x, $val) : ($r === 'p' ? apr2_eur_pilns($x, $val) : $eur($x));
            $eur2 = function (float $a, float $b) use ($rezims, $fmt): array {
                $r = $rezims($a, $b);
                if ($r === 'm' && round($a) != round($b) && $fmt($a, $r) === $fmt($b, $r)) $r = 'p';
                return [$fmt($a, $r), $fmt($b, $r)];
            };
            // Pārī viena mērvienība: "10,8 milj. EUR → 4,1 milj. EUR", ne "10,8 milj. EUR → 4 084 465 EUR".
            // $ag = [gads, summa] — bāzes efekts: "; 2023. gadā bija 48 450 EUR" (recenzija B: trešā summa bez vārda).
            $pari = function ($gi, float $vi, $g, float $v, string $sfx = '', ?array $ag = null) use ($lb, $rezims, $fmt, $c): string {
                $r = $ag !== null ? $rezims($vi, $v, (float)$ag[1]) : $rezims($vi, $v);
                // Miljonos noapaļotas vienādas summas ("27,9 → 27,9 milj.") — tad pilni cipari (audits 1).
                if ($r === 'm' && round($vi) != round($v) && $fmt($vi, $r) === $fmt($v, $r)) $r = 'p';
                $agl = $ag !== null ? $lb($ag[0]) : '';
                $lati = array_values(array_filter([$gi, $ag[0] ?? null], fn($y) => $y !== null && !empty($c['vesture'][(int)$y]['latos'])));
                return $lb($gi) . ': ' . $fmt($vi, $r) . $sfx . ' → ' . $lb($g) . ': ' . $fmt($v, $r) . $sfx
                    . ($ag !== null ? (ctype_digit($agl) ? "; {$agl}. gadā bija " : "; periodā {$agl} bija ") . $fmt((float)$ag[1], $r) : '')
                    . ($lati ? '; ' . implode(', ', $lati) . ' — pārrēķināts no latiem' : ''); // audits 1: 76 punkti bez norādes
            };
            // Izaugsme pēc nepilna PIRMĀ perioda ir darbības uzsākšana, ne izaugsme ("21,6 reizes" pret 3 mēnešiem).
            // Kritumu pēc tā gan sakām (RE24: 12 826 EUR 8 mēnešos → 215 EUR gadā).
            $iv = $c['gads_iepr'] !== null ? ($c['vesture'][(int)$c['gads_iepr']] ?? null) : null;
            $iepr_pirmais = $idal && $iv !== null && (int)$c['gads_iepr'] === (int)array_key_first($c['vesture'])
                && $reg_t !== false && ($iv['no'] ?? '') !== '' && apr2_t($iv['no']) - $reg_t <= 45 * 86400;
            $izm_uzm = $izm !== null && $izm <= APR2_KRITUMS_UZMANIBA;
            $pm_v = (float)($c['periods_men_prec'] ?? 0);
            $im_v = (float)($c['iepr_men_prec'] ?? 0);
            // Bāzes efekts (partija 2: 16 izaugsmes punkti no kritiena gada): ja pirms 2–3 gadiem bija vismaz tikpat,
            // punktam pievieno to gadu — "(2024: 1 679 EUR → 2025: 38 617 EUR; 2023: 48 450 EUR)".
            $agrak = function (string $k, float $tagad) use ($c, $gads, $nesal): ?array {
                foreach ([(int)$gads - 2, (int)$gads - 3] as $g) {
                    $v = $c['vesture'][$g] ?? null;
                    if ($v !== null && $v[$k] !== null && !$nesal($v['men_prec'] ?? null, $v['men'] ?? null) && (float)$v[$k] >= $tagad) return [$g, (float)$v[$k]];
                }
                return null;
            };
            // Pirms 2–3 gadiem ≥ 1,25 × tagad → "izaugsme" ir atgūšanās pēc krituma, ne stiprā puse (recenzija B: 11 no 25).
            $atgusanas = fn(?array $ag, float $tagad): bool => $ag !== null && $ag[1] >= 1.25 * $tagad;
            $izm_stipra = $izm !== null && $izm >= APR2_PIEAUGUMS_STIPRS && !$iepr_pirmais
                && !$atgusanas($agrak('apgr', (float)$c['apgrozijums']), (float)$c['apgrozijums']);
            $izm_txt = function (float $x) use ($izm_videjais, $c, $pari, $pm_v, $im_v, $agrak): string {
                if ((float)$c['apgrozijums'] == 0.0) {
                    return 'apgrozījuma nav (' . $pari($c['gads_iepr'], (float)$c['apgrozijums_iepr'], $c['gads'], 0.0) . ')';
                }
                $nos = $izm_videjais ? 'vidējais mēneša apgrozījums' : 'apgrozījums';
                $sk = $x >= 1.0 ? $nos . ' ' . apr2_reizes($x + 1) . ' lielāks' : $nos . ' ' . ($x >= 0 ? '+' : '−') . apr2_proc_z($x);
                $p = $izm_videjais
                    ? $pari($c['gads_iepr'], (float)$c['apgrozijums_iepr'] / $im_v, $c['gads'], (float)$c['apgrozijums'] / $pm_v, ' mēnesī')
                    : $pari($c['gads_iepr'], (float)$c['apgrozijums_iepr'], $c['gads'], (float)$c['apgrozijums'], '',
                            $x > 0 ? $agrak('apgr', (float)$c['apgrozijums']) : null);
                return "{$sk} ({$p})";
            };

            $r2 = [];
            if (!empty($c['konsolidets'])) $r2[] = 'Finanšu dati ir no koncerna konsolidētā pārskata (uzņēmuma paša pārskata nav).';
            $apg = null;
            if ($c['apgrozijums'] !== null) {
                $apg = $c['apgrozijums'] == 0.0
                    ? ($jauns ? 'apgrozījuma vēl nebija' : 'apgrozījuma nebija')
                    // Izmaiņa "klusajā zonā" netiek vērtēta, bet iepriekšējo gadu pasaka kā faktu.
                    : 'apgrozījums bija ' . $eur($c['apgrozijums'])
                        . ($izm !== null && !$izm_stipra && !$izm_uzm ? " ({$iepr_apg})" : '');
            }
            $rez = null;
            if ($c['rezultats'] !== null) {
                $r = $c['rezultats'];
                $rez = $r > 0 ? ['peļņa', $eur($r)] : ($r < 0 ? ['zaudējumi', $eur(-$r)] : null);
            }
            if ($apg !== null && $rez !== null) {
                $r2[] = $c['apgrozijums'] == 0.0
                    ? "{$ievads} {$apg}, {$rez[0]} bija {$rez[1]}."
                    : "{$ievads} {$apg} un {$rez[0]} {$rez[1]}.";
            } elseif ($apg !== null) {
                $r2[] = "{$ievads} {$apg}.";
            } elseif ($rez !== null) {
                $r2[] = "{$ievads} {$rez[0]} bija {$rez[1]}.";
            }
            if ($c['rangs'] !== null) {
                $kopa = (int)$c['rangs']['kopa'];
                // nace_rangs salīdzina katra uzņēmuma PĒDĒJO pārskatu — tā arī saka.
                $r2[] = "Pēc pēdējo pārskatu apgrozījuma — {$c['rangs']['vieta']}. vieta no " . apr2_sk($kopa)
                    . (apr2_vsk($kopa) ? ' nozares uzņēmuma.' : ' nozares uzņēmumiem.');
            }
            if ($c['darbinieki'] !== null && $c['darbinieki'] > 0) {
                $n = $c['darbinieki'];
                // Bez gada teikuma (tikai bilance) darbinieku teikumam vajag savu periodu.
                $r2[] = ($r2 ? 'Vidēji strādāja ' : "{$ievads} vidēji strādāja ") . apr2_sk($n) . (apr2_vsk($n) ? ' darbinieks.' : ' darbinieki.');
            }

            // Likviditāte: ja apgrozāmie līdzekļi galvenokārt ir nauda — stiprā puse ar naudas
            // summu; ja debitori vai krājumi — tikai fakts (nauda vēl jāiekasē vai jāpārdod).
            $nd = ($likv !== null && $c['nauda'] !== null && $ap > 0) ? $c['nauda'] / $ap : null;
            $likv_stipra = null;
            if ($likv !== null && $likv >= APR2_LIKVID_STIPRA) {
                // Punkts = abas summas (lasītājs attiecību redz pats); no 5 000 EUR — "532 EUR 3,9 reizes pārsniedz
                // īstermiņa saistības" stiprā puse nebija (2026-11-20).
                if ($nd !== null && ($nd >= APR2_NAUDA_DALA || $c['nauda'] >= $is)) {
                    if ($c['nauda'] >= APR2_STIPRA_MIN_EUR && $is >= APR2_LIKV_UZM_MIN_EUR) { // "pārsniedz 50 EUR saistības" nav fakts
                        $r3 = $rezims((float)$ap, (float)$c['nauda'], (float)$is); // viena mērvienība punktā (audits 1)
                        // Lietotāja formulējums 2026-11-20: "nauda bankā un kasē X pārsniedz īstermiņa saistības Y" — bez iekavām,
                        // ar darbības vārdu ("pret" neko nepateica); "pārsniedz" tikai, ja tiešām lielāka (>, ne >=).
                        [$fn, $fi] = $eur2((float)$c['nauda'], (float)$is);
                        $likv_stipra = $c['nauda'] > $is
                            ? "nauda bankā un kasē {$fn} pārsniedz īstermiņa saistības {$fi}"
                            : 'apgrozāmie līdzekļi ' . $fmt((float)$ap, $r3) . ", t.\u{00A0}sk. nauda bankā un kasē " . $fmt((float)$c['nauda'], $r3)
                              . ', pārsniedz īstermiņa saistības ' . $fmt((float)$is, $r3);
                    }
                } elseif ($nd === null) {
                    if ($ap >= APR2_STIPRA_MIN_EUR && $is >= APR2_LIKV_UZM_MIN_EUR) $likv_stipra = 'apgrozāmie līdzekļi ' . implode(' pārsniedz īstermiņa saistības ', $eur2((float)$ap, (float)$is));
                } else {
                    $sast = '';
                    if ($c['debitori'] !== null && $c['debitori'] >= 0.5 * $ap) {
                        $sast = '; lielāko daļu veido debitoru parādi (' . $eur($c['debitori']) . ')';
                    } elseif ($c['krajumi'] !== null && $c['krajumi'] >= 0.5 * $ap) {
                        $sast = '; lielāko daļu veido krājumi (' . $eur($c['krajumi']) . ')';
                    }
                    $pb = ($r2 || $c['periods_lidz'] === '') ? 'Perioda beigās' : 'Pārskata perioda beigās (' . date('d.m.Y', strtotime($c['periods_lidz'])) . ')';
                    $r2[] = $pb . ' apgrozāmie līdzekļi ' . (($likv >= 2 && $likv < 10)
                        ? apr2_reizes($likv) . ' pārsniedza īstermiņa saistības'
                        : 'pārsniedza īstermiņa saistības (' . $eur($ap) . ' pret ' . $eur($is) . ')') . $sast . '.';
                }
            }
            if ($r2 && !$isais) $rindas[] = implode(' ', $r2);

            // --- 3. Vēsture ---------------------------------------------------------------
            $rez_v = array_filter(array_map(fn($v) => $v['rez'], $c['vesture']), fn($v) => $v !== null);
            $nr = count($rez_v);
            if ($nr >= APR2_VESTURE_MIN_GADI) {
                // "Pārskati ir par N gadiem" — visi gadi ar datiem (arī bez rezultāta rindas); "visos" tikai,
                // ja rezultāts zināms KATRAM no tiem.
                $gadi = array_keys(array_filter($c['vesture'], fn($v) => $v['rez'] !== null || $v['apgr'] !== null));
                $nv = count($gadi);
                $pelna_g = array_keys(array_filter($rez_v, fn($v) => $v > 0));
                $k = count($pelna_g);
                $s = 'Pārskati ir par ' . apr2_sk($nv) . (apr2_vsk($nv) ? ' gadu' : ' gadiem') . ' (' . min($gadi) . '–' . max($gadi) . ')';
                if ($k === $nv) {
                    $s .= ', un peļņa bija visos.';
                } elseif ($nr === $nv && count(array_filter($rez_v, fn($v) => $v < 0)) === $nv) {
                    $s .= ', un visos bija zaudējumi.';
                } elseif ($k === 0) {
                    $s .= $nr === $nv ? ', un peļņas nebija nevienā.' : '.';
                } else {
                    $ped = max($pelna_g);
                    $s .= ', un peļņa bija ' . apr2_sk($k) . ' no tiem' . ($ped < max($gadi) ? ', pēdējo reizi ' . $gl($ped) : '') . '.';
                }
                // Sākuma gads un rekords — tikai no salīdzināma garuma (11–13 mēn.) gadiem: LIBERI "2010. gadā
                // 39 853 EUR" bija 8 mēnešu periods; 16 mēnešu periods var kļūt par "lielāko" tikai garuma dēļ.
                $apg_v = array_filter($c['vesture'], fn($v) => $v['apgr'] !== null && $v['apgr'] > 0
                    && !$nesal($v['men_prec'] ?? null, $v['men'] ?? null));
                if (count($apg_v) >= APR2_VESTURE_MIN_GADI) {
                    $yf = array_key_first($apg_v);
                    $yr = $yf;
                    foreach ($apg_v as $gy => $v) if ($v['apgr'] > $apg_v[$yr]['apgr']) $yr = $gy;
                    $lat = fn(int $g): string => $apg_v[$g]['latos'] ? ', pārrēķinot no latiem' : '';
                    if ($yf !== $gads) {
                        $s .= ' ' . apr2_ucfirst($gl($yf)) . ' apgrozījums bija ' . $eur($apg_v[$yf]['apgr'])
                            . ($apg_v[$yf]['latos'] ? ' (pārrēķinot no latiem)' : '');
                        if ($yr === $gads) {
                            $s .= ", un {$gads}. gada apgrozījums ir lielākais šajā laikā.";
                        } elseif ($yr !== $yf) {
                            if ($yr === $c['gads_iepr']) {
                                // Iepriekšējā gada summu var jau teikt 2. rinda vai izmaiņas punkts, bet
                                // to zina tikai beigās (sarakstus var noņemt process vai izslēgšana).
                                $iepr_summa = $eur($apg_v[$yr]['apgr']);
                                $iepr_vieturis = ' (' . $iepr_summa . $lat($yr) . ')';
                                $s .= ', un lielākais tas bija ' . $gl($yr) . "\x01.";
                            } else {
                                $s .= ', un lielākais tas bija ' . $gl($yr) . ' (' . $eur($apg_v[$yr]['apgr']) . $lat($yr) . ').';
                            }
                        } else {
                            $s .= '.';
                        }
                    }
                }
                if (!$isais) $rindas[] = $s;
            }

            // --- Peļņas izmaiņa (tikai pilniem gadiem) -------------------------------------
            // Punktos abas summas ar gadiem; būtiskums (2026-11-20): |Δ| ≥ 1 000 EUR UN ≥ 2 % apgrozījuma.
            $r = $c['rezultats'];
            $rp = $c['rezultats_iepr'];
            $pel_stipra = null;
            $pel_uzm = null;
            // Rezultāts lielāks par apgrozījumu = vienreizējs ienākums (partija 1, 3: peļņa 3,2× apgrozījums) — nesalīdzina.
            $anomals = ($r !== null && abs((float)$r) > max(0.0, (float)($c['apgrozijums'] ?? 0))
                    && ((float)($c['apgrozijums'] ?? 0) > 0 || abs((float)$r) >= APR2_PELNA_MIN)) // peļņa bez apgrozījuma (recenzija A: 2A Invest)
                || ($rp !== null && $c['apgrozijums_iepr'] !== null && $rp > 0 && $rp > (float)$c['apgrozijums_iepr']);
            $apgr_b = max(0.0, (float)($c['apgrozijums'] ?? 0));
            $baze = max(APR2_PELNA_MIN, 0.01 * $apgr_b);
            $puse = max(APR2_PELNA_MIN, 0.005 * $apgr_b);
            if (!$dalejs && !$idal && !$anomals && !$dublikats && $r !== null && $rp !== null && $c['gads_iepr'] !== null) {
                $gi = $c['gads_iepr'];
                $d = abs($r - $rp);
                $butiska = $d >= APR2_PELNA_IZM_MIN_EUR && $d >= APR2_PELNA_IZM_APGR * max(0.0, (float)($c['apgrozijums'] ?? 0));
                $pr = $pari($gi, (float)$rp, $gads, (float)$r);
                // "kopš" tikai no nepārtrauktas gadu virknes (recenzija A: 2010.–2014. g. pārskatu nav, bet "kopš 2009").
                $rez_run = []; for ($yy = (int)$gads; isset($rez_v[$yy]); $yy--) $rez_run[$yy] = $rez_v[$yy];
                $rekords = (count($rez_run) >= APR2_VESTURE_MIN_GADI && $rp > 0 && $rp >= max($rez_run))
                    ? '; ' . $lb($gi) . ' — lielākā peļņa ' . min(array_keys($rez_run)) . '.–' . $gads . '. gadā' : ''; // ne "kopš" (audits 3)
                if (!$butiska) {
                    // nebūtiska izmaiņa — nav ne stiprā puse, ne uzmanības punkts
                } elseif ($rp > 0 && $r > 0 && $rp >= $baze) {
                    $x = $r / $rp - 1;
                    if ($x >= APR2_PELNA_IZMAINA) {
                        $agr = $agrak('rez', (float)$r);
                        if (!$atgusanas($agr, (float)$r)) {
                            $pel_stipra = ($x >= 1.0 ? 'peļņa ' . apr2_reizes($x + 1) . ' lielāka' : 'peļņa +' . apr2_proc_z($x)) . ' (' . $pari($gi, (float)$rp, $gads, (float)$r, '', $agr) . ')';
                        }
                    } elseif ($x <= -APR2_PELNA_IZMAINA) {
                        $pel_uzm = 'peļņa −' . apr2_proc_z($x) . " ({$pr}{$rekords})";
                    }
                } elseif ($rp > 0 && $r < 0 && -$r >= $puse && $rp >= $puse) {
                    $pel_uzm = "no peļņas uz zaudējumiem ({$pr})";
                } elseif ($rp < 0 && $r > 0 && $r >= $puse && -$rp >= $puse) {
                    $pel_stipra = "no zaudējumiem uz peļņu ({$pr})";
                } elseif ($rp < 0 && $r < 0 && -$rp >= $baze) {
                    $x = $r / $rp - 1; // > 0: zaudējumi auga
                    $pra = $pari($gi, (float)-$rp, $gads, (float)-$r); // bez mīnusa — vārds "zaudējumi" saka zīmi (audits 3)
                    // Zaudējumi mazāki, jo darbība apstājusies (apgrozījums 0 vai krities) — tā nav stiprā puse.
                    if ($x <= -APR2_PELNA_IZMAINA && (float)$c['apgrozijums'] > 0 && !$izm_uzm) {
                        $pel_stipra = 'zaudējumi par ' . apr2_proc($x) . " mazāki ({$pra})";
                    } elseif ($x >= APR2_PELNA_IZMAINA) {
                        $pel_uzm = ($x >= 1.0 ? 'zaudējumi ' . apr2_reizes($x + 1) . ' lielāki' : 'zaudējumi par ' . apr2_proc($x) . ' lielāki') . " ({$pra})";
                    }
                }
            }

            // --- Vairāku gadu sērijas (2026-11-20): īsajā tekstā vēstures nebija vispār; 19 % aktīvo ir
            // zaudējumi ≥ 3 pēdējos gadus pēc kārtas (AS GTECH — visos 8 gados, aprakstā ne vārda).
            // Sērija sākas pēdējā gadā un pārtrūkst pie gada bez rezultāta. "visos" — ja tā aptver visus gadus ar rezultātu.
            $serija = function (bool $pelna) use ($c, $gads): array {
                $n = 0; $y = (int)$gads;
                while (isset($c['vesture'][$y]) && $c['vesture'][$y]['rez'] !== null
                    && ($pelna ? $c['vesture'][$y]['rez'] > 0 : $c['vesture'][$y]['rez'] < 0)) { $n++; $y--; }
                return [$n, $y + 1];
            };
            $ser_txt = function (string $kas, int $n, int $no) use ($nr, $gads): string {
                // "visos 21 gadā" nesaskaņojas, un pirmais periods mēdz būt 3 mēn. — tāpēc "visos pārskatos" (audits 3).
                return $n === $nr
                    ? "{$kas} visos pārskatos ({$no}–{$gads})"
                    : "{$kas} " . apr2_sk($n) . (apr2_vsk($n) ? "\u{00A0}gadu" : "\u{00A0}gadus") . " pēc kārtas ({$no}–{$gads})";
            };
            [$zn, $zno] = $serija(false);
            [$pn, $pno] = $serija(true);
            // Būtiskums (recenzija B): "zaudējumi 8 gadus pēc kārtas (…; 2025: 26 EUR)" un "peļņa 8 gadus (…; 204 EUR)" bija troksnis.
            $zaud_sum = 0.0;
            for ($yy = $zno; $yy <= (int)$gads; $yy++) $zaud_sum += -(float)($c['vesture'][$yy]['rez'] ?? 0);
            $zaud_serija = ($zn >= APR2_ZAUD_SERIJA && $zaud_sum >= APR2_PELNA_MIN) ? $ser_txt('zaudējumi', $zn, $zno) : null;
            $pelna_serija = ($pn >= APR2_PELNA_SERIJA && $r !== null && $r >= max(APR2_PELNA_MIN, 0.01 * max(0.0, (float)($c['apgrozijums'] ?? 0))))
                ? $ser_txt('peļņa', $pn, $pno) : null;
            // Lielākais apgrozījums — tikai salīdzināma garuma gadi un tikai, ja arī pēdējais tāds ir.
            $apg_sal = array_filter($c['vesture'], fn($v) => $v['apgr'] !== null && $v['apgr'] > 0
                && !$nesal($v['men_prec'] ?? null, $v['men'] ?? null));
            $rekords_apgr = null;
            // Lielākais arī pret nepilniem/gariem periodiem — citādi "kopš" nebūtu patiess.
            $visi_apgr = [];
            for ($yy = (int)$gads; isset($c['vesture'][$yy]) && $c['vesture'][$yy]['apgr'] !== null; $yy--) $visi_apgr[$yy] = (float)$c['vesture'][$yy]['apgr'];
            ksort($visi_apgr); // nepārtraukta virkne līdz pēdējam gadam ("kopš" pāri robiem nav patiess)
            if (!$dalejs && isset($apg_sal[(int)$gads]) && count($apg_sal) >= APR2_VESTURE_MIN_GADI && count($visi_apgr) >= APR2_VESTURE_MIN_GADI
                && $apg_sal[(int)$gads]['apgr'] > 1.02 * max(array_diff_key($visi_apgr, [(int)$gads => 0]) ?: [INF])) { // ≥ 2 % virs iepriekšējā (audits 3: +0,17 %)
                $iepr_max = array_keys($citi_r = array_diff_key($visi_apgr, [(int)$gads => 0]), max($citi_r))[0];
                // "kopš 2009. gada" lasījās kā "2009. gadā bija vairāk" (audits 3) — tagad periods: "2009.–2025. gadā".
                $rekords_apgr = 'lielākais apgrozījums ' . array_key_first($visi_apgr) . '.–' . $gads . '. gadā (' . $pari($iepr_max, $visi_apgr[$iepr_max], $gads, (float)$visi_apgr[(int)$gads]) . ')';
            }
            // Apgrozījuma sērija: salīdzināmi pilni gadi pēc kārtas līdz pēdējam, katru gadu tajā pašā virzienā.
            $apgr_serija = null; $apgr_serija_virz = 0; $apgr_serija_no = null;
            $ys = []; $y = (int)$gads;
            while (isset($c['vesture'][$y]) && $c['vesture'][$y]['apgr'] !== null && !$nesal($c['vesture'][$y]['men_prec'] ?? null, $c['vesture'][$y]['men'] ?? null)) { $ys[] = $y; $y--; }
            $ys = array_reverse($ys);
            foreach ([1, -1] as $virz) {
                $n = 0;
                for ($i = count($ys) - 1; $i > 0; $i--) {
                    $a = (float)$c['vesture'][$ys[$i - 1]]['apgr']; $b = (float)$c['vesture'][$ys[$i]]['apgr'];
                    if ($a > 0 && ($virz > 0 ? $b > $a : $b < $a)) $n++; else break;
                }
                if ($n >= APR2_APGR_SERIJA) {
                    $y0 = $ys[count($ys) - 1 - $n];
                    $a0 = (float)$c['vesture'][$y0]['apgr']; $b0 = (float)$c['vesture'][(int)$gads]['apgr'];
                    if ($a0 >= APR2_APGR_BAZE_MIN && abs($b0 / $a0 - 1) >= APR2_APGR_SERIJA_IZM) {
                        $apgr_serija = 'apgrozījums ' . ($virz > 0 ? 'audzis' : 'sarucis') . " katru gadu kopš {$y0}. gada (" . $pari($y0, $a0, $gads, $b0) . ')';
                        $apgr_serija_virz = $virz;
                        $apgr_serija_no = $y0;
                    }
                }
            }
            // Apgrozījuma nav ≥ 2 pēdējos gadus (9 no 117 partijā: "13 gadi, aprakstā tikai «nav procesu»").
            $nav_apgr = null; $y = (int)$gads; $n0 = 0;
            while (isset($c['vesture'][$y]) && $c['vesture'][$y]['apgr'] !== null && (float)$c['vesture'][$y]['apgr'] == 0.0) { $n0++; $y--; }
            // Ja nulle ir kopš PIRMĀ gada ar datiem — "nevienā pārskatā" (recenzija B: "kopš 2009" lasās kā apstāšanās).
            // Ar peļņu (holdingi, kooperatīvi: neto apgrozījums 0 ir parasts) sarkano punktu neliek.
            $pirmais_apgr = array_key_first(array_filter($c['vesture'], fn($v) => $v['apgr'] !== null));
            if ($n0 >= APR2_NAV_APGR_SERIJA && !($c['rezultats'] !== null && (float)$c['rezultats'] >= APR2_PELNA_MIN)) { // ne 1 EUR peļņa (audits 5)
                $nav_apgr = ($y + 1) === (int)$pirmais_apgr ? 'apgrozījuma nav nevienā pārskatā (' . ($y + 1) . '–' . $gads . ')'
                    : 'apgrozījuma nav kopš ' . ($y + 1) . '. gada';
            }

            if ($pel_stipra !== null && $zaud_serija !== null && str_starts_with($pel_stipra, 'zaudējumi')) $pel_stipra = null;
            // Sērijas punktam pēdējā gada summa, ja to nesaka cits punkts (GAIZIŅŠ: "zaudējumi 3 gadus pēc kārtas" bez −1 015 945 EUR).
            if ($r !== null && $pel_stipra === null && $pel_uzm === null) {
                if ($zaud_serija !== null && $r < 0) $zaud_serija = substr($zaud_serija, 0, -1) . '; ' . $lb($gads) . ': ' . $eur(-$r) . ')';
                if ($pelna_serija !== null && $r > 0) $pelna_serija = substr($pelna_serija, 0, -1) . '; ' . $lb($gads) . ': ' . $eur($r) . ')';
            }

            // --- Stiprās puses (konkrētās pirms vispārīgajām) ----------------------------------
            $rent_txt = null;
            if ($c['apgrozijums'] !== null && $c['apgrozijums'] > 0 && $r !== null && $r > 0) {
                $rent = $r / $c['apgrozijums'];
                // Zaļais no 5 000 EUR kā kapitālam un naudai (recenzija B: "peļņa 47 EUR jeb 26 %").
                if ($rent >= APR2_RENTAB_STIPRA && $rent <= 1.0 && $r >= APR2_STIPRA_MIN_EUR) $rent_txt = apr2_proc($rent) . ' no apgrozījuma';
            }
            $rek_no = $rekords_apgr !== null ? (int)array_key_first($visi_apgr) : null;
            if ($apgr_serija_virz > 0) {
                // (agrāk gadu nolasīja no teksta ar baitu nobīdi — "š" ir 2 baiti, un rekords nekad netika pievienots)
                $stipras[] = $apgr_serija . ($rek_no !== null && $rek_no < (int)$apgr_serija_no ? '; lielākais ' . $rek_no . '.–' . $gads . '. gadā' : '');
            } elseif ($izm_stipra) {
                $stipras[] = $izm_txt($izm) . ($rek_no !== null ? '; lielākais ' . $rek_no . '.–' . $gads . '. gadā' : '');
            } elseif ($rekords_apgr !== null) {
                $stipras[] = $rekords_apgr;
            }
            // Rentabilitāte pie peļņas punkta, ja tāds ir (viena peļņa — viens punkts).
            // Ja peļņa kritusies, tās summa jau ir sarkanajā punktā — zaļajā tikai daļa (ne divreiz tā pati summa).
            // "zaudējumi mazāki" ≥ 3 zaudējumu gadu sērijas vidū nav stiprā puse — sarkanā sērija to pasaka (partija 1).
            if ($pel_stipra !== null) $stipras[] = $pel_stipra . ($rent_txt !== null ? ', ' . $rent_txt : '');
            // Peļņa kritusies → daļa no apgrozījuma pie sarkanā punkta, ne zaļajā (recenzija A: 233/8000 viena peļņa abos sarakstos).
            elseif ($rent_txt !== null && $pel_uzm !== null) $pel_uzm .= ', ' . $rent_txt;
            elseif ($rent_txt !== null) $stipras[] = $pelna_serija !== null ? 'peļņa veido ' . $rent_txt : 'peļņa ' . $eur($r) . ' jeb ' . $rent_txt;
            if ($pelna_serija !== null) $stipras[] = $pelna_serija;
            // pk > ak nozīmē negatīvas saistības — datu kļūda, ne stiprā puse ("249 % no aktīviem").
            if ($pk !== null && $ak !== null && $ak > 0 && $pk <= $ak && $pk / $ak >= APR2_KAPITALS_STIPRS && $pk >= APR2_STIPRA_MIN_EUR
                && !($r !== null && $r < 0 && -$r >= 0.5 * $pk)
                && !in_array($c['pk_josla'] ?? '', ['warn', 'risk'], true)) { // zaudējumi 562 699 EUR pie 305 992 EUR kapitāla nav "stiprā puse"
                // IK, IND, ZEM, ZVJ — viens īpašnieks (audits 3: "īpašnieku" daudzskaitlī)
                $kas = ($c['tips'] ?? '') === 'KB' ? ' (biedru daļa)'
                    : (in_array($c['tips'] ?? '', ['IK', 'IND', 'ZEM', 'ZVJ'], true) ? ' (īpašnieka daļa)' : (!empty($c['komersants']) ? ' (īpašnieku daļa)' : ''));
                $stipras[] = 'pašu kapitāls' . $kas . ' ' . $eur($pk) . ' jeb ' . apr2_proc($pk / $ak) . ' no aktīviem';
            }
            // Nauda pret saistībām nav stiprā puse, ja pašu kapitāls < 5 % aktīvu (blakus "saistības pārsniedz aktīvus").
            if ($likv_stipra !== null && $pk !== null && $ak !== null && $ak > 0 && $pk >= APR2_NEDAUDZ_NEG_KAP * $ak) $stipras[] = $likv_stipra;

            // Bez apgrozījuma ≥ 2 gadus un bez peļņas bilances "stiprās puses" maldina (partijas 2, 3: Nordikon "izskatās vesels").
            if ($nav_apgr !== null && !($r !== null && $r >= APR2_PELNA_MIN)) {
                $stipras = array_values(array_filter($stipras, fn($x) => !preg_match('/^(pašu kapitāls|nauda|apgrozāmie|zaudējumi)/u', $x)));
            }

            // --- Uzmanības punkti (finanšu) — secību nosaka kārtošana zemāk ---------------------
            // $ak >= 0, ne > 0: uzņēmumam ar 0 EUR aktīviem negatīvais kapitāls ir tieši tas,
            // kas jāpasaka (2026-11-19 parbaude.php: SIA ar −42 175 EUR to izlaida).
            // Viens deficīts, divi punkti (2026-11-20: 12 % aprakstu): ja nav ilgtermiņa aktīvu un visas saistības
            // ir īstermiņa, negatīvais kapitāls = apgrozāmo līdzekļu iztrūkums — tad viens punkts ar abām summām.
            $dubults = $pk !== null && $pk < 0 && $ak !== null && $ak >= 0 && $ap !== null && $is !== null && $is > 0
                && abs($ak - $ap) <= 1 && abs((-$pk) - ($is - $ap)) <= max(1.0, 0.01 * $is);
            // Aktīvi 0, saistības 0, kapitāls < 0 = nesabalansēta (likvidācijas) bilance, ne "saistības pārsniedz aktīvus".
            $nesab = $pk !== null && $pk < 0 && (float)$ak == 0.0 && (float)($is ?? 0) == 0.0 && (float)($c['ilgt_saistibas'] ?? 0) == 0.0;
            if ($pk !== null && $pk < 0 && $ak !== null && $ak >= 0 && !$nesab && !$bez) { // joslā "Pašu kapitāls: negatīvs" ar summu
                if ($ak > 0 && -$pk / $ak < APR2_NEDAUDZ_NEG_KAP) {
                    $uzm['kapitals'] = 'negatīvs pašu kapitāls: ' . $eur($pk) . ' (−' . apr2_proc($pk / $ak) . ' no aktīviem)';
                } else {
                    $uzm['kapitals'] = 'negatīvs pašu kapitāls: ' . $eur($pk)
                        . ($dubults ? ($ak > 0 ? ' (aktīvi ' . $eur($ak) . ', saistības ' . $eur($is) . ')' : ' (aktīvu nav, saistības ' . $eur($is) . ')')
                                    : (-$pk >= APR2_LIKV_UZM_MIN_EUR ? ' (saistības pārsniedz aktīvus)' : ''));
                }
            }
            // Likviditāte tikai, ja segums < 90 % UN trūkst ≥ 1 000 EUR ("397 245 pret 397 504 EUR" bija troksnis).
            // Bez kapitāla punkta (bez_dublikatiem) likviditāte ir vienīgā, arī ja deficīts sakrīt (audits 3).
            if ((!$dubults || $bez) && $likv !== null && $likv < APR2_LIKV_UZM_MAX && ($is - $ap) >= APR2_LIKV_UZM_MIN_EUR) {
                [$fa, $fi] = $eur2((float)$ap, (float)$is);
                $uzm['likviditate'] = $ap <= 0 ? 'apgrozāmo līdzekļu nav; īstermiņa saistības ' . $fi // partija 1: "sedz 0 % (0 EUR pret …), naudas nebija"
                    : 'apgrozāmie līdzekļi sedz ' . apr2_proc($likv) . " īstermiņa saistību ({$fa} pret {$fi})" . (($c['nauda'] !== null && $c['nauda'] <= 0) ? '; naudas bankā un kasē nav' : '');
            }
            // Zaudējumus rada procenti: pamatdarbība pelna, bet parādu izmaksas to pārsniedz.
            if ($c['procenti'] !== null && $c['procenti'] > 0 && $c['pirms_nodokliem'] !== null && $c['pirms_nodokliem'] < 0
                && -$c['pirms_nodokliem'] >= max(APR2_PELNA_MIN, APR2_ZAUD_UZM_DALA * max(0.0, (float)($c['apgrozijums'] ?? 0)))) { // audits 3: 239 EUR
                $ebit = $c['pirms_nodokliem'] + $c['procenti'];
                if ($ebit > 0) {
                    // EBT + procentu IZDEVUMI: procentu ienākumi paliek iekšā, tāpēc "pirms procentu izdevumiem".
                    [$fp, $fe] = $eur2((float)$c['procenti'], (float)$ebit);
                    // Summa aiz vārda bez iekavām (lietotāja likums 2026-11-20: iekavas tikai salīdzinājumam).
                    $uzm['procenti'] = 'procentu izdevumi ' . $fp . ' pārsniedz peļņu pirms procentu izdevumiem un nodokļiem ' . $fe;
                }
            }
            if ($nav_apgr !== null) $uzm['nav_apgr'] = $nav_apgr;
            if ($zaud_serija !== null) $uzm['serija'] = $zaud_serija;
            if ($pel_uzm !== null) $uzm['pelna'] = $pel_uzm;
            // Šī gada zaudējumi, ja neviens cits punkts par tiem nerunā (8 no 117: −35 633 EUR, sarkanajā tikai VID B).
            // Ja "zaudējumi mazāki" zaļo vēlāk noņems process/izslēgšana, zaudējumi citādi nebūtu nekur (audits 1: −72 653 EUR).
            if ($r !== null && $r < 0 && -$r >= APR2_PELNA_MIN && $zaud_serija === null && $pel_uzm === null
                && ($pel_stipra === null || $process_aktivs || $izslegts)
                && ((float)($c['apgrozijums'] ?? 0) <= 0 || -$r >= APR2_ZAUD_UZM_DALA * (float)$c['apgrozijums'])) {
                $ag = (float)($c['apgrozijums'] ?? 0); // "4 833 % no apgrozījuma" nav lasāms — tad apgrozījuma summa
                [$fz, $fa] = $eur2((float)-$r, $ag);
                $uzm['zaudejumi'] = 'zaudējumi ' . ($ag <= 0 ? $eur(-$r) : (-$r > $ag ? $fz . ' pārsniedz apgrozījumu ' . $fa : $eur(-$r) . ' (' . apr2_proc(-$r / $ag) . ' no apgrozījuma)'));
            }
            $min_no = null;
            // Arī pret nulles gadiem (recenzija B: "mazākais kopš 2010", lai gan 2021. gadā bija 0).
            $citi = array_diff_key($visi_apgr, [(int)$gads => 0]);
            if (!$dalejs && count($apg_sal) >= APR2_VESTURE_MIN_GADI && count($visi_apgr) >= APR2_VESTURE_MIN_GADI && (float)($c['apgrozijums'] ?? 0) > 0 && $citi && (float)$c['apgrozijums'] < 0.98 * min($citi)) $min_no = (int)array_key_first($visi_apgr);
            $min_txt = $min_no !== null ? '; mazākais ' . $min_no . '.–' . $gads . '. gadā' : '';
            if ($apgr_serija_virz < 0) $uzm['apgrozijums'] = $apgr_serija . $min_txt;
            elseif ($izm_uzm && $nav_apgr === null) $uzm['apgrozijums'] = $izm_txt($izm) . $min_txt;
            elseif ($min_no !== null) {
                $iepr_min = array_keys($citi, min($citi))[0];
                $uzm['apgrozijums'] = 'mazākais apgrozījums ' . $min_no . '.–' . $gads . '. gadā (' . $pari($iepr_min, (float)$citi[$iepr_min], $gads, (float)$c['apgrozijums']) . ')';
            }
            if ($kave && !$bez) { // joslā "Gada pārskats: nav iesniegts"
                $uzm['parskats'] = $c['kalendars'] ? 'nav ' . ((int)$gads + 1) . ". gada pārskata (jaunākais — par {$gads}. gadu)"
                    : 'nav jaunāka gada pārskata (jaunākais — par periodu ' . date('d.m.Y', strtotime($c['periods_no'])) . '–' . date('d.m.Y', strtotime($c['periods_lidz'])) . ')';
            }
            // Apgrozījuma summa jau ir apgrozījuma punktā — zaudējumu punktā tikai salīdzinājums.
            if (isset($uzm['zaudejumi'], $uzm['apgrozijums']) && str_contains($uzm['zaudejumi'], ' pārsniedz apgrozījumu ')) {
                $uzm['zaudejumi'] = preg_replace('/ pārsniedz apgrozījumu .*$/u', ' pārsniedz apgrozījumu', $uzm['zaudejumi']);
            }
            if (!$svaigs) {
                // 143 aktīvi uzņēmumi ar 2018.–2022. g. pārskatu rādīja "pašu kapitāls veido 100 %" tagadnē.
                $stipras = [];
                foreach (['nav_apgr', 'kapitals', 'likviditate', 'procenti', 'serija', 'pelna', 'zaudejumi', 'apgrozijums', 'parskats'] as $kk) unset($uzm[$kk]);
            }
        }

        // --- 4. Nodokļi un darbinieki (VID) ------------------------------------------------
        if ($c['vid_nodokli'] !== null) {
            $vn = $c['vid_nodokli'];
            $t = "{$vn['gads']}. gadā VID administrētajos nodokļos samaksāti " . apr2_eur((float)$vn['summa'], 'EUR') . '.';
            if ($vn['alga'] !== null && $vn['alga'] > 0 && $vn['darbinieki'] !== null && $vn['darbinieki'] >= APR2_ALGA_MIN_DARBIN) {
                $t .= ' Aplēstā vidējā bruto darba samaksa — ~' . apr2_eur((float)$vn['alga'], 'EUR') . ' mēnesī.';
            }
            if (!$isais) { $rindas[] = $t; $avoti['VID'] = true; }
        }

        // --- 5. Darbs ar valsti (tikai būtiski pret apgrozījumu) -------------------------------
        $apgr_ref = ($c['apgrozijums'] !== null && $c['apgrozijums'] > 0) ? (float)$c['apgrozijums'] : 0.0;
        $butiski = fn(float $s): bool => $s >= APR2_VALSTS_MIN && $s >= APR2_VALSTS_DALA * $apgr_ref;
        $r5 = [];
        if ($c['iepirkumi'] !== null && $butiski($c['iepirkumi']['kopa'])) {
            $ip = $c['iepirkumi'];
            $n = (int)$ip['n'];
            $no_g = substr($ip['no'], 0, 4);
            $vsk = apr2_vsk($n);
            $lig = $n === 1 ? 'publiskā iepirkuma līgums' : ($vsk ? 'publisko iepirkumu līgums' : 'publisko iepirkumu līgumi');
            $t = ($no_g !== '' ? "Kopš {$no_g}. gada " . ($vsk ? 'noslēgts' : 'noslēgti') : ($vsk ? 'Noslēgts' : 'Noslēgti'))
                . ' ' . apr2_sk($n) . " {$lig} par " . apr2_eur($ip['kopa'], 'EUR') . '.';
            if ($n >= 2 && $ip['lielakais'] !== null) {
                $d = array_filter([$ip['lielakais_gads'], $ip['pasutitajs'] !== '' ? 'pasūtītājs — ' . $h($ip['pasutitajs']) : '']);
                $t .= ' Lielākais — ' . apr2_eur($ip['lielakais'], 'EUR') . ($d ? ' (' . implode(', ', $d) . ')' : '') . '.';
            }
            $r5[] = $t;
            $avoti['EIS'] = true;
        }
        if ($c['esfondi'] !== null) {
            $e = $c['esfondi'];
            if ($e['san_n'] > 0) {
                $r5[] = 'ES fondu finansējuma saņēmējs ' . apr2_sk($e['san_n']) . (apr2_vsk($e['san_n']) ? ' projektā' : ' projektos')
                    . ($e['san_es'] > 0 ? ' (ES finansējums ' . apr2_eur($e['san_es'], 'EUR') . ')' : '') . '.';
                $avoti['CFLA'] = true;
            }
            // Izpildītāja līgumi lielākoties ir TIE PAŠI EIS līgumi (2 588 sakritības) — ja EIS teikums
            // jau ir, otrreiz neskaita. "piegādes" bija izdomāts: iep_veids ir procedūra, ne līguma priekšmets.
            if (empty($avoti['EIS']) && $e['izp_n'] > 0 && $butiski($e['izp_summa'])) {
                $vsk = apr2_vsk($e['izp_n']);
                $r5[] = 'ES fondu projektos ' . ($vsk ? 'noslēgts ' : 'noslēgti ') . apr2_sk($e['izp_n'])
                    . ($vsk ? ' līgums' : ' līgumi') . ' par ' . apr2_eur($e['izp_summa'], 'EUR') . '.';
                $avoti['CFLA'] = true;
            }
        }
        if ($r5 && !$isais) $rindas[] = implode(' ', $r5);
        if ($isais) unset($avoti['EIS'], $avoti['CFLA']); // avotos tikai tas, kas tekstā redzams

        // --- Stiprās un uzmanības punkti, kas nav atkarīgi no pārskata -------------------------
        if ($c['vid_reitings'] === 'A' && !$bez) {
            // Skaidrojums ir VID vārdi, ne vietnes vērtējums — pēdiņās (recenzija B).
            $stipras[] = 'VID nodokļu maksātāja reitings A'
                . ($c['vid_skaidrojums'] !== '' ? ' («' . $h(mb_strtolower($c['vid_skaidrojums'], 'UTF-8')) . '»)' : '');
        }
        if (!$tiesiskie && !$bez) { // joslā zaļais "Nav reģistrētu procesu vai liegumu"
            // !has = nav ne sankciju, ne procesu, likvidācijas, apturēšanas, nodrošinājumu, PTAC. Sankcijas šeit
            // NENOSAUC (2026-11-20): par tām raksta tikai tad, ja uzņēmums ir sankciju sarakstā — sarkanajā sarakstā.
            $stipras[] = !empty($ts['has']) ? 'nav aktīvu maksātnespējas, tiesiskās aizsardzības vai citu procesu'
                : 'nav reģistrētu maksātnespējas, tiesiskās aizsardzības vai citu procesu';
        }
        $stipras_bija = count($stipras);
        if ($process_aktivs || $izslegts) $stipras = [];
        if (count($stipras) > APR2_MAX_STIPRAS) {
            $vispar = array_values(array_filter($stipras, fn($x) => preg_match('/^(VID nodokļu|nav (reģistrētu|aktīvu))/u', $x)));
            $fin = array_values(array_filter($stipras, fn($x) => !preg_match('/^(VID nodokļu|nav (reģistrētu|aktīvu))/u', $x)));
            $stipras = array_merge(array_slice($fin, 0, APR2_MAX_STIPRAS - count($vispar)), $vispar);
        }

        // Katrs aktīvais tiesiskais ieraksts — savs punkts ar SAVU datumu (sk. reg_apraksts2_konteksts).
        $tiesiskie_uzm = [];
        foreach ($tiesiskie as $x) {
            if ($x['t'] === 'sankcijas') {
                // "Sankciju risks" ir vērtējums; dati saka, KĀDA loma kurā sarakstā. Personu skaitu NESAKA
                // (pēc skrubja vārdu nav; viena persona mēdz būt vairākos sarakstos). Tukša loma =
                // sankciju subjekts ir pats uzņēmums (40103651284, OFAC SDN).
                $d = [];
                foreach ((array)$c['sankcijas'] as $loma => $saraksti) {
                    $d[] = ((string)$loma !== '' ? apr2_lcfirst((string)$loma) : 'pats uzņēmums') . ' (' . implode(' un ', (array)$saraksti) . ')';
                }
                $tx = $d ? 'sankciju sarakstos: ' . implode(', ', $d) : 'sankciju sarakstos';
            } else {
                $tx = $x['t'];
            }
            // apr2_lcfirst, ne mb_strtolower: "PTAC melnajā sarakstā" nedrīkst kļūt par "pTAC …".
            // Viena iekava: "(aizliegums, kopš 22.04.2025)", ne "(aizliegums) (kopš …)".
            $det = array_filter([(string)($x['det'] ?? ''), $x['no'] !== '' ? 'kopš ' . date('d.m.Y', strtotime($x['no'])) : '']);
            $tiesiskie_uzm[] = $h(apr2_lcfirst($tx)) . ($det ? ' (' . $h(implode(', ', $det)) . ')' : '');
        }
        if (!$izslegts && !$bez && ($c['pvn_izslegts'] ?? '') !== '' && strtotime($c['pvn_izslegts'] . ' +' . APR2_PVN_IZSL_MEN . ' months') >= $sod_t) {
            $uzm['pvn'] = 'izslēgts no PVN maksātāju reģistra (' . date('d.m.Y', strtotime($c['pvn_izslegts'])) . ')';
        }
        if (!$bez && ($c['vid_reitings'] === 'C' || ($c['vid_reitings'] === 'B' && APR2_VID_B_UZMANIBA))) { // joslā "VID reitings: B"
            $uzm['vid'] = "VID nodokļu maksātāja reitings {$c['vid_reitings']}"
                . ($c['vid_skaidrojums'] !== '' ? ' («' . $h(mb_strtolower($c['vid_skaidrojums'], 'UTF-8')) . '»)' : '');
        }
        // Secība pēc nozīmes lasītājam: tiesiskais → pārskats → bilance → rezultāts/sērijas → izmaiņas → PVN → VID.
        $uzm_seciba = $bez ? [] : $tiesiskie_uzm; // tiesiskie ieraksti ir joslas karogā un sadaļā «Tiesiskais statuss»
        foreach (['parskats', 'nav_apgr', 'kapitals', 'likviditate', 'procenti', 'serija', 'zaudejumi', 'pelna', 'apgrozijums', 'pvn', 'vid'] as $kk) {
            if (isset($uzm[$kk])) $uzm_seciba[] = $uzm[$kk];
        }
        $uzm_seciba = $izslegts ? [] : array_slice($uzm_seciba, 0, APR2_MAX_UZMANIBA);

        $saraksts = fn(array $a): string => implode('', array_map(fn($x) => '<br>– ' . $x . ';', array_slice($a, 0, -1)))
            . '<br>– ' . end($a) . '.';
        // Virsraksts teksta pamatkrāsā (melns), punkti krāsainā <span> (apraksts ir <p> iekšā — bez blokelementiem).
        if ($stipras)       $rindas[] = "<b>Stiprās puses:</b><span style='color: #1e7d34;'>" . $saraksts($stipras) . '</span>';
        if ($uzm_seciba)    $rindas[] = "<b>Kam pievērst uzmanību:</b><span style='color: #b3261e;'>" . $saraksts($uzm_seciba) . '</span>';
        // Lietotājs 2026-11-20: ja punktu nav (mazi, pirmais gads, bez apgrozījuma), pelēkā tekstā pasaka, kas ar uzņēmumu
        // notiek — gada apgrozījums un rezultāts ar summām — un ka pēc noteikumiem nekas nav izcelts. Novecojušam pārskatam
        // un aktīvam procesam jau ir sava pelēkā piezīme. Iekavās — salīdzinājums ar iepriekšējo gadu (summu likums).
        $situacija = false;
        if ($bez && $isais && $ir_parskats && !$izslegts && !$stipras && !$uzm_seciba && !empty($svaigs)) {
            $pirmais = !empty($c['pirmais_periods']) || $c['gads_iepr'] === null;
            // "vēl nav" tikai, ja agrāka pārskata vispār nav; citādi trūkst tikai iepriekšējā gada (audits 5).
            $agrak_ir = (bool)array_filter(array_keys($c['vesture']), fn($g) => (int)$g < (int)$gads);
            $gi = $c['gads_iepr'];
            // Pārī viena mērvienība (audits 5: "10,2 milj. EUR (2024: 9 950 000 EUR)").
            $rez_txt = fn(float $v, string $rm): string => $v > 0 ? 'peļņa ' . $fmt($v, $rm) : ($v < 0 ? 'zaudējumi ' . $fmt(-$v, $rm) : 'rezultāts ' . $fmt(0.0, $rm));
            $fakti = [];
            if ($c['apgrozijums'] !== null) {
                $ar = $rezims((float)$c['apgrozijums'], (float)($pirmais ? $c['apgrozijums'] : ($c['apgrozijums_iepr'] ?? $c['apgrozijums'])));
                $fakti[] = ((float)$c['apgrozijums'] == 0.0 ? 'apgrozījuma nebija' : 'apgrozījums ' . $fmt((float)$c['apgrozijums'], $ar))
                    . (!$pirmais && $c['apgrozijums_iepr'] !== null ? ' (' . $lb($gi) . ': ' . $fmt((float)$c['apgrozijums_iepr'], $ar) . ')' : '');
            }
            if ($c['rezultats'] !== null) {
                $rr = $rezims(abs((float)$c['rezultats']), abs((float)($pirmais ? $c['rezultats'] : ($c['rezultats_iepr'] ?? $c['rezultats']))));
                $fakti[] = $rez_txt((float)$c['rezultats'], $rr)
                    . (!$pirmais && $c['rezultats_iepr'] !== null ? ' (' . $lb($gi) . ': ' . $rez_txt((float)$c['rezultats_iepr'], $rr) . ')' : '');
            }
            if ($fakti) {
                $kad = !empty($c['pirmais_periods']) ? 'Pirmajā pārskata periodā' : apr2_ucfirst($gl($gads));
                // Aktīvs process (piem. likvidācija): iemesls ir tas, nevis sliekšņi — atsauce uz joslu, kas to rāda.
                // "netiek izceltas" tikai, ja process tiešām noņēma stiprās puses; citādi iemesls ir sliekšņi (audits 5).
                $rindas[] = "<span style='color: gray;'>" . $h("{$kad} " . implode(', ', $fakti) . '.')
                    . ($pirmais ? ($agrak_ir ? ' ' . ((int)$gads - 1) . '. gada pārskata datu nav.' : ' Iepriekšējā gada salīdzinājumam datu vēl nav.') : '')
                    . (($process_aktivs && $stipras_bija > 0) ? ' Spēkā esošu riska ierakstu dēļ (sk. sadaļu «Papildu reģistri un dati») stiprās puses netiek izceltas.'
                        : ' Pēc apraksta noteikumiem šie rādītāji nav izcelti kā stiprās puses vai uzmanības punkti.'
                          . ($process_aktivs ? ' Spēkā esošie riska ieraksti — sadaļā «Papildu reģistri un dati».' : '')) . '</span>';
                $situacija = true;
            }
        }
        if (!$rindas) return '';

        // Novecojušam pārskatam pietiek ar otro piezīmi (2026-11-20: divas pelēkas rindas pēc kārtas teica to pašu).
        if ($ir_parskats && !$izslegts && $ier_datums !== '' && (!isset($svaigs) || $svaigs)
            && (!$bez || ($visi_pec && ($stipras_bija > 0 || $uzm_seciba)))) { // bez finanšu punktiem piezīme neko neskaidro
            // Apturēšana, likvidācija un nodrošinājums nav "process" (ZS "CĪŅA": "pirms procesa sākuma" par apturēšanu).
            $pirms = str_contains($proc_kas, 'apturē') ? 'darbības apturēšanas'
                : (str_contains($proc_kas, 'likvid') ? 'likvidācijas sākuma'
                : (str_contains($proc_kas, 'nodrošinājuma') ? 'nodrošinājuma līdzekļa reģistrēšanas' : 'procesa sākuma'));
            // Bez dublikātiem ierakstu nenosauc (to rāda josla) — tikai norāde uz to.
            if ($bez) $pirms = 'sadaļā «Papildu reģistri un dati» norādītā ieraksta';
            $rindas[] = "<span style='color: gray;'>Finanšu dati ir no {$gads}. gada pārskata, kas aptver laiku pirms {$pirms}.</span>";
        }
        // Kāpēc stipro pušu nav — tiesiskais ieraksts pats aprakstā vairs nav (to rāda josla).
        if ($bez && $process_aktivs && !$izslegts && $stipras_bija > 0 && !$situacija) { // situācijas teikumā tas jau ir
            $rindas[] = "<span style='color: gray;'>Spēkā esošu riska ierakstu dēļ (sk. sadaļu «Papildu reģistri un dati») stiprās puses netiek izceltas.</span>";
        }
        if ($ir_parskats && !$izslegts && isset($svaigs) && !$svaigs) {
            $rindas[] = "<span style='color: gray;'>Jaunākais pieejamais gada pārskats ir par {$gads}. gadu, tāpēc tā rādītāji netiek izcelti kā stiprās puses vai uzmanības punkti.</span>";
        }

        $teksts = implode('<br>', $rindas);
        if ($iepr_vieturis !== null) {
            $bez_viet = str_replace("\x01", '', $teksts); // ne $bez — tas ir bez_dublikatiem karogs
            $teksts = str_replace("\x01", str_contains($bez_viet, $iepr_summa) ? '' : $iepr_vieturis, $teksts);
        }
        // Avotu sarakstā VID tikai tad, ja tekstā TIEŠĀM ir VID fakts (reitings "N" netiek rādīts).
        // (ne vienkārši "VID" — tas ir arī nosaukumos, piem. "VIDZEME")
        $vid_fakts = false;
        foreach (['VID nodokļu maksātāja reitings', 'VID administrētajos', 'PVN maksātājs', 'darbība (VID)'] as $vf) {
            if (str_contains($teksts, $vf)) $vid_fakts = true;
        }
        if ($vid_fakts) $avoti['VID'] = true;
        else unset($avoti['VID']);
        $av = ['Uzņēmumu reģistrs' . ($ir_parskats ? " ({$gads}. gada pārskats)" : '')];
        foreach (['VID', 'EIS', 'CFLA'] as $a) if (isset($avoti[$a])) $av[] = $a;
        $piezime = 'Apraksts veidots automātiski pēc fiksētiem noteikumiem no atvērtajiem datiem: ' . implode(', ', $av)
            . '. Tas nav kredītreitings un neņem vērā nozares specifiku.'
            . ($ir_parskats ? ' Aprēķini — sadaļā «Finanšu rādītāji un riska analīze».' : '');
        return $teksts . "<br><br><span style='font-size:0.85em; color:gray;'>* {$piezime}</span>";
    }
}
