<?php
/**
 * granti/lib/modelis.php — PIETEIKŠANĀS MODELIS un konkursa atzīmes.
 *
 * KĀPĒC. Meklēšanas kritēriju izpēte (2026-09-06) parādīja, ka Latvijas MVU un biedrībai
 * svarīgākais atbilstības jautājums nav nozare, bet "vai es vispār drīkstu pieteikties viens".
 * No 638 aktuālajiem ES konkursiem ~59 % prasa starptautisku konsorciju, un bez šīs atzīmes
 * saraksts rāda 638 "iespējas", no kurām mazai organizācijai der desmitiem reižu mazāk.
 *
 * DIVI SIGNĀLI, PRIORITĀTES SECĪBĀ. Pirmā doma bija lasīt atbilstību tikai no `conditions`
 * (vidēji 10 411 rakstz.). Kā VIENĪGAIS avots tas neder: no 191 HORIZON-RIA konkursa frāze par
 * vismaz trim juridiskajām personām parādās divos, jo 481 no 638 tekstiem ir tikai atsauce
 * "described in Annex B of the Work Programme General Annexes".
 * Bet tur, kur teksts prasību TOMĒR nosauc (17 konkursos "at least three legal entities",
 * 9 — "single legal entity"), tas ir autoritatīvs un uzvar pār vispārinājumu: bez tā deviņi
 * CSA konkursi ar tiešu triju personu prasību rādījās "vieglākajā" kategorijā, un EIC
 * Pathfinder, kur atļauts arī viens saņēmējs, — kā tīrs konsorcija projekts.
 *
 * TĀPĒC: vispirms teksta signāls, un tikai tad `actions[].types[].typeOfAction` pēc publiski zināmajiem
 * programmu noteikumiem. Tas ir MŪSU vērtējums, nevis portāla lauks, un lapā tas jāmarķē kā
 * tāds. Kur noteikumi pieļauj abus variantus (LIFE projekti, CEF, DIGITAL, COFUND, EIC
 * Pathfinder), modelis apzināti ir "atkarīgs no konkursa" — labāk godīgs "nezinām" nekā
 * pārliecinoša kļūda, kuras dēļ kāds neiesniedz pieteikumu vai velti raksta 40 lappuses.
 */
declare(strict_types=1);

/** Modeļi: atslēga => [īsais nosaukums, ikona, krāsa, paskaidrojums]. */
const GR_MODELI = [
    'viens' => ['Var pieteikties viens', '👤', '#15803d',
        'Pēc programmas noteikumiem pieteikties var viena organizācija vai persona — konsorcijs nav vajadzīgs.'],
    'balva' => ['Balva vai atzinība', '🏆', '#a16207',
        'Nav klasisks projekta pieteikums: novērtē jau paveikto, un pieteicējs parasti ir viens.'],
    'csa' => ['Koordinācija un atbalsts', '🤝', '#0e7490',
        'Koordinācijas un atbalsta darbība (CSA): parasti īsāks pieteikums nekā pētniecības projektā, bet partneru prasība atšķiras — daļā CSA konkursu tāpat vajag vismaz trīs organizācijas no trim valstīm.'],
    'konsorcijs' => ['Vajag konsorciju', '👥', '#b45309',
        'Pēc programmas noteikumiem vienam pieteicējam ar šo konkursu nepietiek — vajadzīgi partneri. Apvārsnī tas parasti nozīmē vismaz trīs neatkarīgas juridiskās personas no trim dažādām valstīm, bet daļā konkursu (piemēram, Hop-On) prasība ir citāda, tāpēc precīzo skaitli skaties konkursa dokumentā.'],
    'atkariba' => ['Atkarīgs no konkursa', '❓', '#64748b',
        'Programmas noteikumi pieļauj gan vienu pieteicēju, gan partneru grupu — precīzā prasība jāskatās konkursa dokumentā.'],
];

/**
 * typeOfAction prefikss => modelis. Sakritību meklē pēc GARĀKĀ prefiksa, tāpēc
 * HORIZON-EIC-ACC (viens pieteicējs) neaiziet zem HORIZON-EIC (atkarīgs).
 * Viss, kas šeit nav uzskaitīts, paliek 'atkariba' — saraksts ir apzināti īss.
 */
const GR_TOA_MODELIS = [
    // Individuālie granti: stipendijas un ERC — viens saņēmējs pēc definīcijas.
    'HORIZON-TMA-MSCA-PF' => 'viens',   // pēcdoktorantūras stipendijas (EF/GF)
    'HORIZON-ERC'         => 'viens',   // ERC granti un Proof of Concept
    'HORIZON-EIC-ACC'     => 'viens',   // EIC Accelerator — viens MVU
    'HORIZON-EIC-EQU'     => 'viens',   // EIC tikai kapitāla ieguldījums
    'LIFE-FPA-OG'         => 'viens',   // NVO darbības dotācijas
    'ERASMUS-CERT'        => 'viens',   // akreditācija/harta
    'ESC-CERT'            => 'viens',

    'HORIZON-RPr'         => 'balva',
    'ERASMUS-PRIZE'       => 'balva',

    'HORIZON-CSA'         => 'csa',
    'HORIZON-JU-CSA'      => 'csa',
    'DIGITAL-CSA'         => 'csa',
    'DIGITAL-JU-CSA'      => 'csa',
    'EURATOM-CSA'         => 'csa',

    // Pētniecības un inovācijas darbības, aizsardzības fonds, doktorantūras tīkli,
    // personāla apmaiņa un pirmskomercializācijas iepirkums — visiem vajag partnerus.
    'HORIZON-RIA'         => 'konsorcijs',
    'HORIZON-IA'          => 'konsorcijs',
    'HORIZON-JU-RIA'      => 'konsorcijs',
    'HORIZON-JU-IA'       => 'konsorcijs',
    'EURATOM-RIA'         => 'konsorcijs',
    'EURATOM-IA'          => 'konsorcijs',
    'EDF-RA'              => 'konsorcijs',
    'EDF-DA'              => 'konsorcijs',
    'EDF-LS'              => 'konsorcijs',
    'HORIZON-TMA-MSCA-DN' => 'konsorcijs',
    'HORIZON-TMA-MSCA-SE' => 'konsorcijs',
    'HORIZON-PCP'         => 'konsorcijs',
    'HORIZON-PPI'         => 'konsorcijs',
];

/** Darbības veidu latviskie nosaukumi (rādīšanai; nepilnīgs saraksts ir pieļaujams). */
const GR_TOA_LV = [
    'HORIZON-RIA' => 'pētniecības un inovācijas darbība',
    'HORIZON-IA' => 'inovācijas darbība',
    'HORIZON-CSA' => 'koordinācija un atbalsts',
    'HORIZON-JU-RIA' => 'pētniecības darbība (kopuzņēmums)',
    'HORIZON-JU-IA' => 'inovācijas darbība (kopuzņēmums)',
    'HORIZON-JU-CSA' => 'koordinācija un atbalsts (kopuzņēmums)',
    'HORIZON-RPr' => 'atzinības balva',
    'HORIZON-COFUND' => 'programmas līdzfinansējums',
    'HORIZON-PCP' => 'pirmskomercializācijas iepirkums',
    'HORIZON-PPI' => 'inovatīvu risinājumu iepirkums',
    'HORIZON-EIC-ACC' => 'EIC Accelerator (MVU)',
    'HORIZON-EIC' => 'EIC grants',
    'HORIZON-EIC-EQU' => 'EIC kapitāla ieguldījums',
    'HORIZON-ERC' => 'ERC pētniecības grants',
    'HORIZON-ERC-POC' => 'ERC koncepcijas pārbaude',
    'HORIZON-TMA-MSCA-PF-EF' => 'MSCA pēcdoktorantūras stipendija',
    'HORIZON-TMA-MSCA-PF-GF' => 'MSCA globālā stipendija',
    'HORIZON-TMA-MSCA-DN' => 'MSCA doktorantūras tīkls',
    'HORIZON-TMA-MSCA-DN-JD' => 'MSCA kopīgā doktorantūra',
    'HORIZON-TMA-MSCA-DN-ID' => 'MSCA industriālā doktorantūra',
    'HORIZON-TMA-MSCA-SE' => 'MSCA personāla apmaiņa',
    'LIFE-PJG' => 'LIFE projekta grants',
    'LIFE-FPA-OG' => 'LIFE darbības dotācija (NVO)',
    'EDF-DA' => 'aizsardzības izstrādes darbība',
    'EDF-RA' => 'aizsardzības pētniecības darbība',
    'EDF-LS' => 'aizsardzības grants (vienreizējs maksājums)',
    'CEF-INFRA' => 'infrastruktūras projekts',
    'ERASMUS-PRIZE' => 'Erasmus+ balva',
    'ERASMUS-CERT' => 'Erasmus+ akreditācija',
    'DIGITAL-SIMPLE' => 'Digitālās Eiropas grants',
    'DIGITAL-LS' => 'Digitālās Eiropas grants (vienreizējs maksājums)',
    'DIGITAL-GFS' => 'grants finansiālam atbalstam trešām personām',
];

/**
 * Nosaka pieteikšanās modeli no darbības veidu kopas.
 * Ja tēmai ir vairāki veidi ar ATŠĶIRĪGIEM modeļiem, atbilde ir 'atkariba' —
 * apgalvot vienu no diviem būtu minēšana.
 *
 * @param string[] $toa typeOfAction abreviatūras (piem. ['HORIZON-RIA'])
 */
function gr_modelis(array $toa, string $conditionsHtml = ''): string {
    // TEKSTA SIGNĀLS IR STIPRĀKS PAR KARTĒJUMU. Nosacījumu tekstā atbilstības prasība ir
    // reti (481 no 638 tur ir tikai atsauce uz B pielikumu), BET kur tā IR, tā ir
    // autoritatīva un uzvar pār darbības veida vispārinājumu. Bez šī 9 CSA konkursi, kuru
    // tekstā tieši prasīts vismaz trīs juridiskās personas, rādījās "vieglākajā" kategorijā,
    // un EIC konkursi, kur atļauts viens saņēmējs, — kā konsorcija projekti.
    if ($conditionsHtml !== '') {
        $t = preg_replace('/<[^>]+>/', ' ', $conditionsHtml) ?? '';
        // "minimum three and maximum five" ir tas pats noteikums citiem vārdiem — bez šī
        // varianta EIC Transition (kur atļauts GAN viens, GAN konsorcijs) tika marķēts
        // vienpusīgi kā "var pieteikties viens".
        // Starp "three" un "legal entities" var stāvēt vēl vārdi: EIC Transition raksta
        // "minimum three and maximum five independent legal entities".
        $treji = (bool)preg_match('/(?:at least|minimum(?: of)?)\s+three\b[^.]{0,45}?legal entities'
            . '|three legal entities/i', $t);
        // Izņēmums nav noteikums. EuroHPC tekstā stāv "The consortium may EXCEPTIONALLY be
        // composed of a single legal entity ... which is an SME" — tas ir šaurs izņēmums no
        // B pielikuma pamatnoteikuma, nevis atļauja pieteikties vienam. Bez šī vārtiem
        // konkurss no "koordinācija un atbalsts" kļuva par "konsorcijs nav vajadzīgs".
        $viens = false;
        if (preg_match('/single legal entit|sole beneficiar|mono-beneficiar|one legal entity established/i',
                       $t, $mm, PREG_OFFSET_CAPTURE)) {
            $pirms = substr($t, max(0, $mm[0][1] - 140), min(140, $mm[0][1]));
            $viens = !preg_match('/\b(exceptional\w*|unless|derogat\w*|by way of exception)\b/i', $pirms);
        }
        // Abi signāli vienlaikus (EIC Pathfinder Challenges: "single entity OR consortium of
        // at least three") nozīmē tieši to, ko saka — atļauti abi varianti.
        if ($treji && $viens) return 'atkariba';
        if ($treji) return 'konsorcijs';
        if ($viens) return 'viens';
    }
    $found = [];
    foreach ($toa as $t) {
        $t = (string)$t;
        $best = null; $bestLen = -1;
        foreach (GR_TOA_MODELIS as $prefix => $model) {
            if (($t === $prefix || str_starts_with($t, $prefix . '-')) && strlen($prefix) > $bestLen) {
                $best = $model; $bestLen = strlen($prefix);
            }
        }
        $found[$best ?? 'atkariba'] = true;
    }
    if (count($found) === 1) return (string)array_key_first($found);
    return 'atkariba';
}

/** Modeļa etiķete / ikona / krāsa / paskaidrojums. */
function gr_modelis_info(string $key): array {
    return GR_MODELI[$key] ?? GR_MODELI['atkariba'];
}

/** Darbības veida latviskais nosaukums; nezināmam atdod pašu kodu. */
function gr_toa_lv(string $toa): string {
    return GR_TOA_LV[$toa] ?? $toa;
}

/**
 * Kaskādes finansējuma (FSTP) pazīme un maksimālā summa vienai trešajai personai.
 * ES projektu konsorciji pārdala daļu naudas tālāk maziem uzņēmumiem un pētniekiem —
 * parasti bez konsorcija, ar īsu pieteikumu. 158 no 638 konkursiem to paredz; summa
 * tekstā nolasāma 56 gadījumos, biežākā ir 60 000 €.
 *
 * @return array{0:bool,1:?float} [vai ir FSTP, maksimālā summa vai null]
 */
function gr_fstp(string $conditionsHtml): array {
    $txt = preg_replace('/<[^>]+>/', ' ', $conditionsHtml) ?? '';
    if (!preg_match('/financial support to third part/i', $txt)) return [false, null];
    $max = null;
    // Divi formulējumi vienam faktam: "maximum amount to be granted to each third party is
    // EUR 60 000" un "FSTP that may be awarded to any single third party is set at EUR 10 million".
    // Otrais nesa lielākās summas (3–10 milj.) un bez "million" reizinātāja tika izlaists.
    if (preg_match('/(?:maximum amount (?:of FSTP )?(?:to be granted|that may be awarded) to (?:each|any single) third party'
                 . '|FSTP (?:to be granted|that may be awarded) to (?:each|any single) third party)'
                 . '[^.]{0,60}?EUR\s*([\d][\d\s.,]*)\s*(million|m\b)?/i', $txt, $m)) {
        // Formāti jaukti: "60 000.", "100,000,", "2,550,000.", "10.00 million" — ar "million"
        // punkts ir decimāldaļa, bez tā — tūkstošu atdalītājs.
        $raw = trim($m[1]);
        if (!empty($m[2])) {
            $v = (float)str_replace([' ', ','], ['', '.'], preg_replace('/\.(?=\d{3}\b)/', '', $raw) ?? $raw) * 1e6;
        } else {
            $digits = preg_replace('/\D/', '', $raw) ?? '';
            $v = $digits !== '' ? (float)$digits : 0.0;
        }
        if ($v >= 1000 && $v <= 20000000) $max = $v;
    }
    return [true, $max];
}

/**
 * Tēmas budžets no nosacījumu teksta ("Indicative budget: EUR 16 000 000").
 *
 * KĀPĒC TAS VAJADZĪGS. Daļā programmu (EDF, CBE, EIC) budgetOverviewJSONItem katrai
 * konkursa tēmai nes VIENU UN TO PAŠU summu — tā ir visa konkursa aploksne, ne tēmas
 * budžets: EDF-2026-RA septiņām tēmām tur ir 110 milj., bet katras tēmas teksts nosauc
 * savu (16, 20, 14, 15 milj. …). Bez šī izvilkuma lapa rādīja konkursa aploksni kā tēmas
 * budžetu 89 ierakstos no 638.
 *
 * Piesardzība: pieņem tikai tad, ja tekstā ir VIENA nepārprotama summa. Vairākas dažādas
 * summas ar to pašu frāzi nozīmē, ka teksts runā par vairākiem posmiem — tad labāk nekas.
 *
 * @return ?float summa eiro, vai null
 */
function gr_temas_budzets(string $conditionsHtml, string $descriptionHtml = ''): ?float {
    $txt = preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', ' ', $conditionsHtml . ' ' . $descriptionHtml) ?? '') ?? '';
    if (!preg_match_all('/\bIndicative budget\s*:?\s*(?:for (?:the|this) topic\s*(?:is)?\s*)?EUR\s*([\d][\d\s.,]*)/i',
                        $txt, $m)) return null;
    $vals = [];
    foreach ($m[1] as $raw) {
        $d = preg_replace('/\D/', '', rtrim(trim($raw), '.')) ?? '';
        if ($d === '') continue;
        $v = (float)$d;
        if ($v >= 1000 && $v <= 5e9) $vals[(string)$v] = $v;
    }
    return count($vals) === 1 ? array_values($vals)[0] : null;
}

/** Termiņa modeļa latviskais nosaukums (single-stage / two-stage / multiple cut-off). */
function gr_termina_modelis_lv(?string $m): ?string {
    return match ($m) {
        'two-stage'        => 'divu posmu pieteikums',
        'multiple cut-off' => 'vairāki griezuma datumi',
        'continuous'       => 'nepārtraukta iesniegšana',
        default            => null,   // single-stage ir noklusējums, to nerāda
    };
}
