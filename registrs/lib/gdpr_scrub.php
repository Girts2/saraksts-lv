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
    // 'mantiniek-' pievienots 2026-08-19 (audits): "mirušā dalībnieka mantinieka
    // A.Gurkovska iesniegums" — bez šīs formas vārds palika divās publiskās lapās.
    $role = '(?:[Bb]iedr(?:a|as|es)|[Dd]alībniek(?:a|as)|[Dd]alībnieces|[Īī]pašniek(?:a|as)|[Īī]pašnieces'
        . '|[Mm]antiniek(?:a|as|es|u)|[Pp]ersonas?|[Ll]ikvidator(?:a|as|es)|[Aa]dministrator(?:a|as|es)'
        . '|[Ll]ocek[ļl](?:a|es)|[Kk]omersant(?:a|as)|iecelt[sa]|iecelts\s+par\s+likvidatoru)';
    $capword  = $C . $c_ . '{2,}';
    $initname = $C . '\.\s?' . $capword;

    // APGRIEZTĀ KĀRTĪBA: vārds PIRMS lomas — "I.Zirnīša - testamentārā mantinieka
    // lēmums" (audits 2026-08-19). Apzināti ĻOTI šaurs: tikai iniciāļa forma
    // ("I.Zirnīša" nevar būt teikuma vārds) un tikai tieši pirms lomas ģenitīva ar
    // ne vairāk kā vienu vārdu starpā ("testamentārā"). Visā UR kopā (9593 rindas)
    // šis šablons trāpa 1 rindā, bet formu "divi kapitalizēti vārdi pirms lomas"
    // NEIEVIEŠAM — datos tādu nav, un tā riskētu apgriezt vietvārdus nosaukumos.
    $role_gen = '(?:mantiniek|īpašniek|dalībniek|likvidator|administrator|biedr|komersant)\w*';
    $text = preg_replace(
        '/\b' . $initname . '\s*[-–—]?\s*((?:' . $c_ . '+\s+)?' . $role_gen . ')/u',
        '$1',
        $text
    ) ?? $text;

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

/**
 * Mantiskā ieguldījuma vērtētāju saraksts (`property_investment_appraisers_list`).
 *
 * UR šajā kopā publicē FIZISKAS PERSONAS ar pilnu kontaktkomplektu: vārds, uzvārds,
 * personīgais mobilais, privātais e-pasts (gmail/inbox.lv) un adrese — 150 personām
 * 110 uzņēmumu lapās, turklāt 69 personām adrese atšķiras no uzņēmuma juridiskās,
 * t.i., tā ir dzīvesvieta. Tabula bija SEARCH_COLUMNS_MAP_REG_NR, bet neviens skrubis
 * to neaizsniedza (audits 2026-08-19), tāpēc dati nonāca HTML tabulā, /{regnr}.json
 * eksportā UN Gemini promptā.
 *
 * BALTAIS saraksts, ne melnais: paturam tikai laukus, kas raksturo UZŅĒMUMU (jomas un
 * teritorijas, kurās tam ir vērtētāji). Ja UR kopai kādreiz pieliks jaunu kolonnu, tā
 * pēc noklusējuma NEIZKĻŪS — melnais saraksts šādā gadījumā klusi noplūstu.
 * Izmestais: valuer_id (pseidonimizēts personas identifikators valsts vērtētāju
 * reģistrā — ļauj atpazīt personu), forename, surname, phone, email, address un
 * izglītības dokumenta lauki.
 */
const APPRAISER_PUBLIC_COLS = ['area', 'territory', 'region',
    'legal_entity_registration_number', 'legal_entity_name'];

function scrub_appraisers(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $tirs = [];
        foreach (APPRAISER_PUBLIC_COLS as $col) {
            if (array_key_exists($col, $r)) $tirs[$col] = $r[$col];
        }
        if ($tirs !== []) $out[] = $tirs;
    }
    return $out;
}

/**
 * Sankciju risku kopa (`sanctions`, UR/CC0).
 *
 * Kopā ir FIZISKU personu vārdi (58), dzimšanas datumi (56) un personas kodu
 * maskas (24) — sankcionētās personas. Sankciju saraksti paši par sevi ir
 * publiski, BET šī vietne uzņēmumu lapās fizisko personu datus nerāda (Girta
 * 2026-07-24 lēmums), un novecojis vārds pie "sankcijas" ir smags apgalvojums.
 * Tāpēc paturam TIKAI uzņēmuma līmeņa faktu: kurš saraksts, kāda ir saistītās
 * personas LOMA (bez vārda), kad un uz kāda juridiskā pamata.
 *
 * BALTAIS saraksts — jauna UR kolonna pēc noklusējuma neizkļūs.
 */
const SANCTIONS_PUBLIC_COLS = ['sanctions_subject_registration_number', 'sanctions_subject_name',
    'sanctions_subject_legal_form_code', 'position', 'position_text_lv', 'list', 'list_text',
    'program', 'entry_date', 'registered_on', 'legal_base', 'legal_base_url', 'entity_type'];

function scrub_sanctions(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $tirs = [];
        foreach (SANCTIONS_PUBLIC_COLS as $col) {
            if (array_key_exists($col, $r)) $tirs[$col] = $r[$col];
        }
        // entity_type paliek tikai kā pazīme "persona vai uzņēmums", bez identitātes.
        if ($tirs !== []) $out[] = $tirs;
    }
    return $out;
}

/**
 * de minimis rindas — baltais saraksts (otrais aizsardzības slānis).
 *
 * Galvenā aizsardzība ir būvē (convert.php fix_deminimis_personas: fizisko personu
 * rindas dzēstas, project_title notīrīts). Šis slānis sargā gadījumam, ja datubāze
 * nāk no vecākas būves vai avots iegūst jaunu brīvteksta kolonnu: cauri laižam
 * TIKAI zināmi drošos laukus.
 */
// project_title te ir DROŠS: būve tā vietā ieraksta 8 zīmju hešu (convert.php),
// kas nesatur personas datus, bet ļauj panelim atšķirt divus vienādā dienā
// piešķirtus vienāda apjoma atbalstus dažādiem projektiem.
const DEMINIMIS_PUBLIC_COLS = ['person_reg_nr', 'assign_date', 'assigned_amount',
    'assigner_title', 'program_title', 'instrument_title', 'regul_title', 'project_title'];

function scrub_deminimis(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $tirs = [];
        foreach (DEMINIMIS_PUBLIC_COLS as $c) {
            if (array_key_exists($c, $r)) $tirs[$c] = $r[$c];
        }
        if ($tirs !== []) $out[] = $tirs;
    }
    return $out;
}

/**
 * EIS iepirkumu rindas — baltais saraksts (otrais aizsardzības slānis).
 *
 * Galvenā aizsardzība ir būvē: build_iepirkumi_table() atstāj tikai uzvarētājus,
 * kas ir `register` tabulā, tāpēc fizisku personu (saimnieciskās darbības veicēju
 * ar personas kodu vārda vietā) rindas datubāzē nenonāk vispār. Šis slānis sargā
 * gadījumam, ja datubāze nāk no vecākas būves vai tabula iegūst jaunu kolonnu:
 * neviens lauks ar brīvtekstu par cilvēkiem te nav un netiks pievienots klusi.
 *
 * NEIEKĻAUTS APZINĀTI: avota `Izbeigsanas_iemesls` un `Iepirkuma_dalas_nosaukums` —
 * brīvteksti, kuros jau atrasti gan līgumslēdzēju stāsti, gan personas koda formāta
 * numuri. Tos nesaglabā arī būve.
 */
const IEPIRKUMI_PUBLIC_COLS = ['regcode', 'chain_id', 'iepirkuma_id', 'iep_nosaukums',
    'pasutitajs', 'pasutitaja_regnr', 'proc_veids', 'summa', 'sak_summa', 'valuta',
    'vv', 'uzv_skaits', 'dok_skaits', 'datums', 'pedejais', 'izbeigts'];

function scrub_iepirkumi(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $tirs = [];
        foreach (IEPIRKUMI_PUBLIC_COLS as $c) {
            if (array_key_exists($c, $r)) $tirs[$c] = $r[$c];
        }
        if ($tirs !== []) $out[] = $tirs;
    }
    return $out;
}

/**
 * Fiziskas personas vārda izņemšana no ES fonda projekta NOSAUKUMA.
 *
 * CFLA projektu nosaukums ir brīvteksts, un ģimenes ārstu praksēm tajā ierakstīts
 * konkrēta cilvēka pilns vārds: "Agneses Ķirses ģimenes ārsta prakses modernizācija",
 * "Lasmanes Madaras, ģimenes ārstes, veicamo procedūru pieejamības uzlabošana",
 * "Karlsone Aija - ģimenes ārsta prakse un Ņeborakova Inga - ģimenes ārsta prakse".
 * Šis nosaukums tiek PĀRŅEMTS partnera un izpildītāja rindās, tāpēc bez skrubja
 * trešās personas vārds parādās SVEŠU uzņēmumu lapās, /{regnr}.json eksportā un MI
 * promptā (audits 2026-08-19: 988 rindas, 251 nosaukums, 442 uzņēmumi).
 *
 * Rindu filtrs (subjekts ir register) to NESEDZ — tas pārbauda rindas uzņēmumu, nevis
 * tekstu, ko rindā iekopē no projekta rindas.
 *
 * Metode apzināti šaura: griež TIKAI kapitalizētu vārdu pāri, kas stāv blakus prakses
 * marķierim ("ģimenes ārst..."), abos virzienos. Vietvārda pāris ("Vecumnieku Novada")
 * teorētiski var trāpīties, bet vietvārda nomaskēšana ir nesalīdzināmi mazāks kaitējums
 * nekā cilvēka vārda publicēšana svešā lapā.
 */
function scrub_projekta_nosaukums(string $t): string {
    if ($t === '') return $t;
    $C  = '[A-ZĀČĒĢĪĶĻŅŠŪŽ]';
    $c_ = '[a-zāčēģīķļņšūž]';
    // Marķieris: jebkura "ārst..." forma KĀ APAKŠVIRKNE (sedz "ģimenes ārsts",
    // "ārsta prakse" bez vārda "ģimenes", "Zobārstes", "veterinārārsts") + "doktorāts",
    // "feldšeris", "vecmāte". (?i:) — reģistrnejutība TIKAI marķierim: PCRE2 /u režīmā
    // tā saloka arī Ģ↔ģ (stripos to nedarīja — "Ģimenes ārstes..." gāja cauri neskarts,
    // audits 2026-08-20). Personvārdu šabloni PALIEK reģistrjutīgi, citādi divi parasti
    // mazo burtu vārdi ("prakses modernizācija") arī izskatītos pēc persona pāra.
    $marker = '(?i:(?:ģimenes\\s+)?(?:ārst' . $c_ . '*|doktorāt' . $c_ . '*|feldšer' . $c_ . '*|vecmāt' . $c_ . '*))';
    if (!preg_match('/' . $marker . '/u', $t)) return $t;
    $sakotnejais = $t;
    $vards = $C . $c_ . '{2,}';
    // Personvārda formas: "Vārds Uzvārds", "V. Uzvārds" (arī "V.Uzvārds"), "Uzvārds V."
    // — iniciāļa formas pievienotas 2026-08-20 (audits: "J.Sprūdes", "Šķirmantes E.").
    // Iniciālis var būt arī divburtu ("Dz.", "Dž.").
    $inic = $C . $c_ . '?\.';
    $persona = '(?:' . $inic . '\s*' . $vards . '|' . $vards . '\s+' . $inic . '|' . $vards . '\s+' . $vards . ')';
    // UZMANĪBU: PCRE  un \w bez UCP ir ASCII, tāpēc pie latviešu burtiem tie
    // vārda robežu neatpazīst — robežu rakstām skaidri (arī tipogrāfiskās pēdiņas
    // “ ” ‘ ’ ‚ — "SIA “Ausmas Balodes..." ar ASCII klasi gāja cauri). Un mainīga
    // garuma lookbehind PCRE nav atļauts, tāpēc otrajā likumā marķieris ir parasta grupa.
    $sak = '(^|[\s"«„“”‘’‚\'(,\-–—])';
    // Ķēde: "A.Kauliņas un L.Rancānes ārsta praksēs" — bez tās paliktu pirmais loceklis.
    $kede = '(?:\s*(?:un|,)\s*' . $persona . ')*';
    // (a) persona (vai personu ķēde) PIRMS marķiera.
    // aiz ķēdes drīkst palikt arī saiklis: "A.Kauliņas un ⟨ārsta praksēs⟩".
    $t = preg_replace('/' . $sak . $persona . $kede . '(?:\s*(?:un|,|[\-–—]))*\s*(?=' . $marker . ')/u',
        '$1', $t) ?? $t;
    // (b) persona (vai personu ķēde) PĒC marķiera.
    $t = preg_replace('/(' . $marker . ')(\s*[,\-–—]?\s*)' . $persona . $kede . '/u',
        '$1$2', $t) ?? $t;
    // (c) VĀRDU SARAKSTS aiz partnera marķiera: "sadarbības partneru Karlsone Aija
    //     un Ņeborakova Inga ģimenes ārstu praksēs". (a) likums noņem tikai to pāri,
    //     kas ir tieši pirms marķiera; pārējie saraksta locekļi paliek.
    $partneris = 'sadarbības\s+partner' . $c_ . '*';
    $t = preg_replace('/(' . $partneris . ')(\s*(?:' . $persona
        . ')(?:\s*(?:un|,)\s*(?:' . $persona . '))*)/u', '$1', $t) ?? $t;
    // Atkārtojam līdz fikspunktam: vienā teikumā mēdz būt vairākas ķēdes.
    $t = preg_replace('/\s{2,}/u', ' ', $t) ?? $t;
    if ($t !== $sakotnejais) $t = scrub_projekta_nosaukums($t);
    // trim() ar daudzbaitu simboliem charlist griež BAITUS un sabojā burtu — regex.
    return preg_replace('/^[\s,\-–—]+|[\s,\-–—]+$/u', '', $t) ?? $t;
}

/** Vai nosaukumā vēl PALICIS personvārda pāris blakus prakses marķierim (fail-closed pārbaudei). */
function ir_personvards_projekta_nosaukuma(string $t): bool {
    return $t !== scrub_projekta_nosaukums($t);
}

/**
 * CFLA ES fondu rindas — baltais saraksts (otrais aizsardzības slānis).
 *
 * Galvenā aizsardzība ir būvē (build_es_fondi_table: cauri tikai register
 * uzņēmumi, projekta īpašnieka vārds tikai ar juridiskas personas kodu).
 * NEIEKĻAUTS APZINĀTI: avota `FinansejumaSanemejaEpasts` un
 * `IesniedzejaJuridiskaAdrese` — fiziskām personām tie ir privātais e-pasts un
 * dzīvesvieta, un CFLA tos NEDZĒŠ pat tad, kad dzēš vārdu; tos neglabā arī būve.
 */
const ESFONDI_PUBLIC_COLS = ['regcode', 'loma', 'periods', 'projekta_nr', 'projekta_nosaukums',
    'pasakums', 'fonds', 'iestade', 'statuss', 'sakums', 'beigas', 'izmaksas', 'es_fin',
    'izmaksats', 'sanemejs', 'summa', 'iep_veids', 'datums'];

function scrub_esfondi(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $tirs = [];
        foreach (ESFONDI_PUBLIC_COLS as $c) {
            if (array_key_exists($c, $r)) $tirs[$c] = $r[$c];
        }
        // Projekta nosaukums ir brīvteksts ar fizisku personu vārdiem — skrubis arī te,
        // lai vecāka datubāze (bez būves labojuma) nepalaiž tos cauri.
        if (isset($tirs['projekta_nosaukums']) && is_string($tirs['projekta_nosaukums'])) {
            $tirs['projekta_nosaukums'] = scrub_projekta_nosaukums($tirs['projekta_nosaukums']);
        }
        if ($tirs !== []) $out[] = $tirs;
    }
    return $out;
}

/**
 * PTAC rindas — baltais saraksts (otrais aizsardzības slānis).
 *
 * NEIEKĻAUTS APZINĀTI: avota `Notikuma apraksts` — 50 lēmumu aprakstos ir fiziskas
 * personas vārds kopā ar saitēm uz viņa personīgajiem sociālo tīklu profiliem; kā
 * arī adreses, tālruņi un e-pasti, kas individuālajiem komersantiem ir privāti.
 * Tos neglabā arī būve (build_ptac_table).
 */
const PTAC_PUBLIC_COLS = ['regcode', 'veids', 'statuss', 'numurs', 'no_dat', 'lidz_dat',
    'apturets', 'anulets', 'joma', 'soda_nauda', 'papildus'];

function scrub_ptac(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $tirs = [];
        foreach (PTAC_PUBLIC_COLS as $c) {
            if (array_key_exists($c, $r)) $tirs[$c] = $r[$c];
        }
        if ($tirs !== []) $out[] = $tirs;
    }
    return $out;
}

/**
 * BIS / VVD / ZVA / VID papildu reģistru baltie saraksti (otrais slānis).
 *
 * Būvē (build_papildu_tabulas) šo tabulu subjekti jau atsijāti pret register, un
 * operatoru vārdi, adreses, koordinātas, e-pasti, tālruņi un ZVA atbildīgo personu
 * vārdi netiek glabāti vispār. Šis slānis to nostiprina arī tad, ja datubāze nāk
 * no vecākas būves.
 */
const PAPILDU_PUBLIC_COLS = [
    'bis' => ['regcode', 'veids', 'gads', 'ienemumi', 'apjoms_lv', 'apjoms_arpus',
              'nodarb', 'datums', 'statuss', 'bis_nr'],
    'vide' => ['regcode', 'veids', 'numurs', 'statuss', 'no_dat', 'lidz_dat',
               'apturets', 'nosaukums', 'apraksts'],
    'zva' => ['regcode', 'veids', 'kods', 'statuss', 'no_dat', 'lidz_dat', 'objekts'],
    'vid_statusi' => ['regcode', 'veids', 'statuss', 'no_dat', 'lidz_dat', 'papildus'],
    'atkritumi' => ['regcode', 'gads', 'nosaukums', 'klase', 'radits', 'parstradats',
                    'apglabats', 'nodots', 'nodots_apglabasanai',
                    'izvests_parstradei', 'izvests_apglabasanai', 'uzkrats'],
];

function scrub_papildu(array $rows, string $tabula): array {
    $balts = PAPILDU_PUBLIC_COLS[$tabula] ?? null;
    if ($balts === null) return [];
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $tirs = [];
        foreach ($balts as $c) {
            if (array_key_exists($c, $r)) $tirs[$c] = $r[$c];
        }
        if ($tirs !== []) $out[] = $tirs;
    }
    return $out;
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
