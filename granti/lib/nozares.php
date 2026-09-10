<?php
/**
 * granti/lib/nozares.php — grantu NOZARU klasifikators (kopīgs būvei un lapai).
 *
 * KĀPĒC ŠIS FAILS VISPĀR IR. Lapa sākotnēji grupēja konkursus pēc PROGRAMMAS, bet
 * programma nav nozare: 484 no 638 aktuālajiem ES konkursiem ir "Apvārsnis Eiropa",
 * t.i. viena plāksne saturētu 76 % no visa saraksta un neko nešķirotu. Cilvēks, kas
 * meklē grantu, domā nozarēs ("vide", "veselība", "digitalizācija"), nevis ES
 * finanšu instrumentos.
 *
 * KUR NOZARE SLĒPJAS DATOS. ES portāla topicDetails laukā `programmeDivision` ir
 * HIERARHISKS masīvs (HORIZON, HORIZON.2, HORIZON.2.6, HORIZON.2.6.3 — visi četri
 * vienā ierakstā). Apvāršņa 2. līmeņa dalījums FAKTISKI ir nozaru klasteri:
 *   HORIZON.2.1 Veselība · 2.2 Kultūra un sabiedrība · 2.3 Civilā drošība ·
 *   2.4 Digitālā joma un rūpniecība · 2.5 Klimats un enerģētika · 2.6 Pārtika un vide.
 * Pārējām programmām nozare izriet no pašas programmas (LIFE = vide, EDF = aizsardzība).
 *
 * PRIORITĀTES SECĪBA IR NOZĪMĪGA: 91 konkurss atbilst vairāk nekā vienai kārtulai
 * (piem. HORIZON.2.6.2 "Biodiversity" ir gan Vide, gan — caur vecāku 2.6 — Lauksaimniecība).
 * Uzvar PIRMĀ atbilstība sarakstā, tāpēc šaurākās kārtulas stāv pirms plašākajām.
 *
 * Pārbaudīts uz 638 kešotajiem topicDetails: piešķirti 638/638, bez "cita" grozа.
 */
declare(strict_types=1);

/**
 * Nozaru definīcijas. Katra: [nosaukums, ikona, krāsa, programmeDivision prefiksi,
 * programmu abreviatūras]. Secība = prioritāte (šaurākās pirmās) UN plākšņu secība.
 */
const GR_NOZARES = [
    'veseliba' => ['Veselība un medicīna', '🩺', '#be123c',
        ['HORIZON.2.1'], ['EU4H', '3HP']],
    'drosiba' => ['Drošība un aizsardzība', '🛡️', '#475569',
        ['HORIZON.2.3'], ['EDF', 'ISF', 'ISFP', 'ISFB', 'BMVI', 'UCPM', 'UCPM2027', 'PERI', 'EDIDP']],
    // RFCS (ogļu un tērauda pētniecības fonds) ir metalurģija — "Steel Research projects",
    // "Steel Pilot and demonstration" — tātad rūpniecība, ne klimats, kur tas stāvēja sākumā.
    'digitala' => ['Digitālā joma, rūpniecība un kosmoss', '💻', '#7c3aed',
        ['HORIZON.2.4', 'CEF-2.3'], ['DIGITAL', 'RFCS2027', 'RFCS']],
    // LIFE-1-1 = klimats, LIFE-1-3 = tīrās enerģijas pāreja (16 konkursi) — abas
    // pēc būtības ir enerģētika/klimats, nevis vide, tāpēc tās izķer šeit, pirms
    // zemāk visa pārējā LIFE programma aiziet uz "Vide".
    'klimats' => ['Klimats, enerģētika un transports', '⚡', '#0e7490',
        ['HORIZON.2.5', 'LIFE-1-1', 'LIFE-1-3', 'CEF-2.2'],
        ['CEF2027', 'RENEWFM', 'EURATOM2027', 'JTM', 'INNOVFUND']],
    // Vide stāv PIRMS lauksaimniecības: abas dzīvo zem HORIZON.2.6, un dabas/jūru/
    // aprites apakšnodaļas pēc būtības ir vide, ne lauksaimniecība.
    'vide' => ['Vide, daba un bioloģiskā daudzveidība', '🌿', '#15803d',
        ['HORIZON.2.6.1', 'HORIZON.2.6.2', 'HORIZON.2.6.4', 'HORIZON.2.6.7'], ['LIFE2027']],
    // Programmu abreviatūras portālā mainās ar periodu (AGRIP -> AGRIP2027, AMIF -> AMIF2027,
    // UCPM -> UCPM2027): bulk failā ir ABI varianti, un bez otrā jauns konkurss klusi
    // nokļūtu "cita" grozā, ko plāksnes nerāda.
    'lauksaimnieciba' => ['Lauksaimniecība, pārtika un bioekonomika', '🌾', '#a16207',
        ['HORIZON.2.6', 'Food', 'EFSA'], ['AGRIP', 'AGRIP2027', 'EMFAF2027', 'EMFAF']],
    'kultura' => ['Kultūra, sabiedrība un demokrātija', '🏛️', '#c2410c',
        ['HORIZON.2.2'], ['CREA2027', 'CREA', 'CERV', 'JUST2027', 'JUST', 'ED', 'EP']],
    'zinatne' => ['Zinātne un pētniecības izcilība', '🔬', '#1d4ed8',
        ['HORIZON.1', 'HORIZON.4'], []],
    'inovacija' => ['Inovācija un uzņēmējdarbība', '🚀', '#0f766e',
        ['HORIZON.3'], ['SMP', 'I3', 'COSME']],
    'izglitiba' => ['Izglītība, jaunatne un sports', '🎓', '#9333ea',
        [], ['ERASMUS2027', 'EPLUS', 'ESC2027', 'ESC']],
    // SOCPL = Social Prerogative and Specific Competencies Lines — ES sociālās politikas
    // tiešā finansējuma līnija; 2026-09-07 būvē bija pirmais dzīvais konkurss un tas
    // nokļuva "cita" grozā, kam plāksnes nav.
    'sociala' => ['Sociālā joma un iekļaušana', '🤝', '#9f1239',
        [], ['ESF', 'AMIF', 'AMIF2027', 'PPPA2027', 'PPPA', 'EUBA', 'SOCPL', 'REC', 'EFC']],
];

/** SIF konkursu nozare pēc programmas nosaukuma (SIF programmeDivision nav). */
const GR_SIF_SOCIALA = ['sociāl', 'ukrain', 'nenodrošinātīb', 'senior', 'reemigr', 'iekļauš'];

/**
 * Nosaka nozares atslēgu vienam konkursam.
 * @param ?string $prog  frameworkProgramme abreviatūra (HORIZON, LIFE2027, SIF...)
 * @param string[] $divs programmeDivision abreviatūras (visas hierarhijas pakāpes)
 * @param string $label  programmas nosaukums (SIF atpazīšanai)
 */
function gr_nozare(?string $prog, array $divs, string $label = '', string $idf = ''): string {
    // STARPNOZARU TĒMAS. Jaunajam Eiropas Bauhaus (HORIZON-NEB-*) un misiju konkursiem
    // portāls piešķir VISUS sešus Apvāršņa klasterus (2.1-2.6) vienlaikus. "Pirmā atbilstība
    // uzvar" tos visus 31 iemeta "Veselībā" tikai tāpēc, ka tā ir saraksta sākumā —
    // "Addressing homelessness through housing-led approaches" plāksnē "Veselība un medicīna".
    // Tāpēc starpnozaru tēmas atpazīst PIRMS klasteru kārtulām, pēc identifikatora.
    // Starpnozaru tēmu atpazīst pēc DATIEM, ne pēc programmas nosaukuma: ja portāls tēmai
    // ir piešķīris trīs vai vairāk 2. līmeņa klasterus, neviens no tiem nav "tas īstais",
    // un klasteru kārtula zemāk vienkārši paņemtu pirmo pēc saraksta secības. Misiju
    // konkursi ar SKAIDRU klasteri caur šo zaru neiet un tiek klasificēti normāli.
    $lvl2 = 0;
    foreach ($divs as $d) if (str_starts_with($d, 'HORIZON.2.') && substr_count($d, '.') === 2) $lvl2++;
    if ($lvl2 >= 3 && $idf !== '') {
        $u = strtoupper($idf);
        if (str_contains($u, 'CANCER')) return 'veseliba';
        if (str_contains($u, 'SOIL') || str_contains($u, 'OCEAN') || str_contains($u, 'WATER')
            || str_contains($u, 'BIODIV')) return 'vide';
        if (str_contains($u, 'CLIMA') || str_contains($u, 'ADAPT') || str_contains($u, 'CITIES')) return 'klimats';
        // Jaunais Eiropas Bauhaus: arhitektūra, publiskā telpa, kopiena.
        if (str_contains($u, 'NEB')) return 'kultura';
        return 'kultura';   // pārējās starpnozaru tēmas ir par sabiedrību, ne par vienu jomu
    }
    if ($prog === 'SIF') {
        $l = mb_strtolower($label);
        foreach (GR_SIF_SOCIALA as $w) if (mb_strpos($l, $w) !== false) return 'sociala';
        return 'kultura';
    }
    foreach (GR_NOZARES as $key => [, , , $prefixes, $programmes]) {
        foreach ($divs as $d) {
            foreach ($prefixes as $p) {
                // Hierarhijas atdalītājs portālā nav viens: Apvārsnis lieto punktu
                // (HORIZON.2.6.3), LIFE un DIGITAL — defisi (LIFE-1-3-2). Bez otrā
                // varianta 16 LIFE tīrās enerģijas konkursi nokļuva zem "Vide".
                if ($d === $p || str_starts_with($d, $p . '.') || str_starts_with($d, $p . '-')) return $key;
            }
        }
        if ($prog !== null && in_array($prog, $programmes, true)) return $key;
    }
    return 'cita';
}

/** Nozares etiķete / ikona / krāsa; nezināmai atslēgai — neitrāls rezerves variants. */
function gr_nozare_info(string $key): array {
    if (!isset(GR_NOZARES[$key])) return ['Cita joma', '📄', '#64748b'];
    [$name, $icon, $color] = GR_NOZARES[$key];
    return [$name, $icon, $color];
}
