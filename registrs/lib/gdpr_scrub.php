<?php
/**
 * registrs/lib/gdpr_scrub.php — fizisko personu vārdu izņemšana no UR atvērtajiem datiem.
 *
 * Atsevišķs fails ar nolūku: skrubi lieto GAN galvenais lapu ceļš (data_fetcher.php),
 * GAN "Test Biedrības" sadaļa (nvo/nvo_data.php), kas vaicā UR DB tieši. Ja tas dzīvotu
 * data_fetcher.php iekšienē, otrais to klusi neatrastu un vārdi paliktu lapā.
 */
declare(strict_types=1);

/**
 * Amatpersonas vārda izņemšana no iestādes nosaukuma (`securing_measures.institution_name`).
 *
 * UR šajā laukā līdzās iestādēm (VID nodaļas, tiesas, policija, prokuratūra) ieraksta arī
 * konkrētu FIZISKU personu: "Zvērināta tiesu izpildītāja Evita Eistere". 158 zvērināti
 * tiesu izpildītāji 14 440 ierakstos + 7 maksātnespējas administratori — kopā 7 944 lapas.
 * Amats ir jēgpilna informācija ("kurš uzlika aizliegumu": VID vai tiesu izpildītājs),
 * tāpēc IZŅEMAM TIKAI VĀRDU, saglabājot amatu un iestādes kvalifikatoru.
 *
 * `institution_identifier` paliek — to pašu lauku lieto arī VID (1000057137) un tiesas
 * (1000361696), tas nav personas kods; `institution_registration_number` tiesu
 * izpildītājiem ir tukšs visās 14 441 rindā.
 *
 * Metode apzināti NAV "izgriež vārdu no teksta": paņem TIKAI atpazīto amata frāzi un visu
 * pārējo izmet. Tā nezināms teksta gabals nekad neizspruks cauri. Ja amata atslēgvārda
 * nav vispār, vērtību neaiztiekam — tā ir iestāde, ne cilvēks.
 */
function scrub_amatpersonas_vards(string $name): string {
    $n = trim(preg_replace('/\s+/u', ' ', $name));
    if ($n === '') return $n;

    // Iestādes kvalifikators, ja tāds ir priekšā ("Latgales apgabaltiesas ...").
    $prefix = '';
    if (preg_match('/^(.*?\b(?:apgabal)?tiesas)\s+/u', $n, $m)) {
        $prefix = $m[1] . ' ';
    }

    // Aizvietotājs (15 ieraksti): avotā ir DIVI personvārdi — pats izpildītājs ģenitīvā UN
    // aizvietotājs ("Zvērinātas tiesu izpildītājas Vitas Brences aizvietotāja Aelita
    // Lasmane"), dažkārt vēl "palīgs" trešajā vietā. Vispārīgais ceļš zemāk gan abus vārdus
    // noņemtu, bet atstātu ģenitīvā karājošos "Zvērinātas tiesu izpildītājas", tāpēc amatu
    // šeit saliekam no jauna. Galotni ņemam no avota: aizvietotāj-a / aizvietotāj-s.
    if (preg_match('/zvērināt[ao]?s?\s+tiesu\s+izpild[īi]tāj[ao]?s?\b.*?\baizvietotāj([ao]?s?)/iu', $n, $m)) {
        $gal = mb_strtolower($m[1], 'UTF-8');
        $loma = 'zvērinātas tiesu izpildītājas aizvietotāj' . ($gal === '' ? 'a' : $gal);
        return $prefix !== ''
            ? $prefix . $loma
            : mb_strtoupper(mb_substr($loma, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($loma, 1, null, 'UTF-8');
    }

    // Amatu frāzes, garākā vispirms. 'izpildītaja' bez garumzīmes = UR datu drukas kļūda
    // (1 ieraksts) — atveidojam kā avotā, nevis izlabojam klusi.
    $lomas = [
        '/zvērināt[ao]?s?\s+tiesu\s+izpild[īi]tāj[ao]?s?/iu',
        '/tiesu\s+izpild[īi]t[āa]j[ao]?s?/iu',
        '/sertificēts\s+maksātnespējas\s+administrator\w*/iu',
        '/maksātnespējas\s+proces\w*\s+administrator\w*/iu',
        '/maksātnespējas\s+administrator\w*/iu',
        '/administrator\w*/iu',
    ];
    foreach ($lomas as $re) {
        if (preg_match($re, $n, $m)) {
            $loma = mb_strtolower($m[0], 'UTF-8');
            if ($prefix === '') {
                $loma = mb_strtoupper(mb_substr($loma, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($loma, 1, null, 'UTF-8');
            }
            return $prefix . $loma;
        }
    }
    return $n;   // iestāde bez amata atslēgvārda — neaiztiekam
}

/**
 * Fizisko personu vārdu izņemšana no likvidācijas pamatojuma brīvteksta
 * (`liquidations.grounds_for_liquidation`).
 *
 * Lauks ir brīvs teksts ("14.02.2024. Vienīgā biedrības biedra Daces Pastares
 * lēmums Nr. 1-2024") un tiek rādīts KATRAM apmeklētājam "Izmantotie dati"
 * tabulās (+ Tiesiskais panelis, + nvo sadaļa, + /{regnr}.json). 2026-08-12
 * pilnā 9 593 rindu skenēšanā personvārdi atrasti ~15 rindās, trīs veidolos:
 *   1) loma ģenitīvā + vārds: "dalībnieka Vladimira Pribiļeva lēmums"
 *   2) loma + iniciālis+uzvārds: "personas J.Cepurīts pieteikums"
 *   3) iecelšana: "par likvidatoru iecelts Nikolajs Mačinovskis"
 *
 * Noteikumi (apzināti šauri, lai neizkropļo tekstu):
 *  - aiz lomas/iecelšanas vārda izmet TIKAI (a) iniciāļa formu (1 tokens pietiek —
 *    "J.Cepurīts" nevar būt teikuma vārds) vai (b) ≥2 kapitalizētus vārdus pēc
 *    kārtas, kas nav juridiskajā vārdnīcā. VIENS kapitalizēts vārds paliek —
 *    citādi "persona Pēc publikācijas..." zaudētu teikuma sākumu (reāls gadījums).
 *  - atkārto līdz fikspunktam: "īpašnieka A. B. pilnvarotās personas C. D. lēmums"
 *    satur divas ķēdes.
 *  - subjekta PAŠA nosaukumu neaiztiek ("Rīgas pilsētas Nikolaja Kočujevska
 *    individuālais uzņēmums") — tas ir publiskais reģistrētais nosaukums, ko lapa
 *    tāpat rāda H1 (IK/IU nosaukumos īpašnieka vārds ir ar likumu).
 */
function scrub_likvidacijas_pamats(string $text): string {
    static $vocab = ['lēmums'=>1,'lēmumu'=>1,'lēmuma'=>1,'lēmumi'=>1,'protokols'=>1,'protokola'=>1,
        'sapulces'=>1,'sapulce'=>1,'kopsapulces'=>1,'kopsapulce'=>1,'pieteikums'=>1,'pieteikuma'=>1,
        'iesniegums'=>1,'iesnieguma'=>1,'spriedums'=>1,'sprieduma'=>1,'nolēmums'=>1,'nolēmuma'=>1,
        'vienīgā'=>1,'vienīgās'=>1,'vienīgais'=>1,'ārkārtas'=>1,'atkārtotās'=>1,'atkārtota'=>1,
        'valdes'=>1,'padomes'=>1,'kongresa'=>1,'likvidācijas'=>1,'maksātnespējas'=>1,'procesā'=>1,
        'ieinteresētās'=>1,'ieinteresētā'=>1,'pilnvarotās'=>1,'pilnvarotā'=>1,'dalībnieku'=>1,
        'biedru'=>1,'akcionāru'=>1,'uzņēmuma'=>1,'sabiedrības'=>1,'biedrības'=>1,'nodibinājuma'=>1];
    $C  = '[A-ZĀČĒĢĪĶĻŅŠŪŽ]';
    $c_ = '[a-zāčēģīķļņšūž]';
    // Loma ģenitīvā (jebkurš reģistrs pirmajam burtam) vai iecelšanas forma.
    $role = '(?:[Bb]iedr(?:a|as|es)|[Dd]alībniek(?:a|as)|[Dd]alībnieces|[Īī]pašniek(?:a|as)|[Īī]pašnieces'
        . '|[Pp]ersonas?|[Ll]ikvidator(?:a|as|es)|[Aa]dministrator(?:a|as|es)|[Ll]ocek[ļl](?:a|es)'
        . '|[Kk]omersant(?:a|as)|iecelt[sa]|iecelts\s+par\s+likvidatoru)';
    $capword  = $C . $c_ . '{2,}';
    $initname = $C . '\.\s?' . $capword;

    $prev = null;
    while ($prev !== $text) {
        $prev = $text;
        $text = preg_replace_callback(
            '/\b(' . $role . ')((?:\s+(?:' . $initname . '|' . $capword . '))+)/u',
            function ($m) use ($vocab, $C, $c_) {
                $toks = preg_split('/\s+/u', trim($m[2]));
                $drop = 0;
                foreach ($toks as $t) {
                    $base = mb_strtolower(trim($t, '.,;'), 'UTF-8');
                    if (isset($vocab[$base])) break;
                    if (preg_match('/^' . $C . '\./u', $t)) { $drop++; continue; }   // iniciālis
                    if (preg_match('/^' . $C . $c_ . '/u', $t)) { $drop++; continue; }
                    break;
                }
                // Iniciāļa forma = vārds jau ar 1 tokenu; citādi vajag ≥2 pēc kārtas.
                $has_init = $drop > 0 && preg_match('/^' . $C . '\./u', $toks[0]);
                if ($drop === 0 || (!$has_init && $drop < 2)) return $m[0];
                $rest = array_slice($toks, $drop);
                return $m[1] . ($rest ? ' ' . implode(' ', $rest) : '');
            },
            $text
        );
    }
    return $text;
}

/** Piemēro pamatojuma skrubi liquidations rindām (atslēga `grounds_for_liquidation`). */
function scrub_liquidations(array $rows): array {
    foreach ($rows as $i => $r) {
        if (array_key_exists('grounds_for_liquidation', $r) && is_string($r['grounds_for_liquidation'])) {
            $rows[$i]['grounds_for_liquidation'] = scrub_likvidacijas_pamats($r['grounds_for_liquidation']);
        }
    }
    return $rows;
}

/** Piemēro vārda izņemšanu securing_measures rindām (atslēga `institution_name`). */
function scrub_securing_measures(array $rows): array {
    foreach ($rows as $i => $r) {
        if (array_key_exists('institution_name', $r) && is_string($r['institution_name'])) {
            $tirs = scrub_amatpersonas_vards($r['institution_name']);
            $rows[$i]['institution_name'] = $tirs;
            // Ja vārds tika izņemts (= ieraksts bija par fizisku personu), izņemam arī
            // institution_identifier: tas ir 1:1 pseidonīma atslēga uz to pašu cilvēku
            // (164 identifikatori / 158 vārdi) — ar to personu var izsekot pa lapām tāpat
            // kā ar vārdu. Iestādēm (VID, tiesas) identifikators paliek, tur tas nav
            // personas dati. Tukšo vārdu 21 rindas identifikatori ar izpildītāju
            // identifikatoriem nepārklājas (pārbaudīts 2026-08-11) — tās neaiztiekam.
            if ($tirs !== trim(preg_replace('/\s+/u', ' ', $r['institution_name']))
                && array_key_exists('institution_identifier', $r)) {
                $rows[$i]['institution_identifier'] = null;
            }
        }
    }
    return $rows;
}
