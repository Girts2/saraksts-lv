<?php
/**
 * registrs/nvo/nvo_panels.php — adaptīvais paneļu reģistrs.
 *
 * Sistēmas būtība: lapa nerāda fiksētu veidni, bet TIKAI tos paneļus, kuriem
 * konkrētajai biedrībai ir dati. Katrs panelis deklarē:
 *
 *   id       — unikāls atslēgvārds
 *   virsraksts
 *   grupa    — profils | darbiba | finanses | uzticamiba | beigas
 *   svars    — kārtošanas secība grupā
 *   segums   — mērītais segums % starp aktīvajām biedrībām (rāda "cik tas ir retums")
 *   avots    — datu avots (parādās zem paneļa)
 *   probe    — fn(array $ctx): mixed|null — null nozīmē "paneli nerāda"
 *   render   — fn(mixed $dati, array $ctx): string
 *
 * Jauns datu avots = viens ieraksts šajā masīvā. Lapa nav jāaiztiek.
 */
declare(strict_types=1);

require_once __DIR__ . '/nvo_data.php';
require_once __DIR__ . '/nvo_render.php';

const NVO_GRUPAS = [
    'profils'    => 'Profils',
    'darbiba'    => 'Ko biedrība dara',
    'finanses'   => 'Finanses',
    'uzticamiba' => 'Uzticamība un risks',
    'beigas'     => 'Par datiem',
];

/** Savāc visus datus vienā kontekstā (katrs vaicājums pa indeksu, viens reizi). */
function nvo_collect(PDO $ur, string $rc): array {
    $ctx = ['rc' => $rc, 'ur' => $ur];
    $ctx['pamatdati']       = nvo_pamatdati($ur, $rc);
    $ctx['merki']           = nvo_merki($ur, $rc);
    $ctx['jomas']           = nvo_jomas($ur, $rc);
    $ctx['parvaldiba']      = nvo_parvaldiba($ur, $rc);
    $ctx['parskati']        = nvo_parskati($ur, $rc);
    $ctx['bilances']        = nvo_bilances($ur, $rc);
    $ctx['nosaukumi']       = nvo_nosaukumi($ur, $rc);
    $ctx['pvn']             = nvo_pvn($ur, $rc);
    $ctx['delegetie']       = nvo_delegetie($ur, $rc);
    $ctx['riski']           = nvo_riski($ur, $rc);
    $ctx['slo']             = nvo_slo($rc);
    $ctx['parvaldnieks']    = nvo_parvaldnieks($rc);
    $ctx['deminimis']       = nvo_deminimis($rc);
    $ctx['es_projekti']     = nvo_es_projekti($rc);
    $ctx['soc_pakalpojumi'] = nvo_soc_pakalpojumi($rc);
    $ctx['lad']             = nvo_lad($rc);
    $ctx['iepirkumi']       = nvo_iepirkumi($rc);
    $ctx['etaloni']         = nvo_etaloni($ctx['jomas'] ?? []);
    return $ctx;
}

/** Paneļu reģistrs. */
function nvo_panel_registry(): array {
    return [

    // ── PROFILS ─────────────────────────────────────────────────────────────
    [
        'id' => 'pamatdati', 'virsraksts' => 'Pamatdati', 'grupa' => 'profils',
        'svars' => 10, 'segums' => 100.0, 'avots' => 'Uzņēmumu reģistrs',
        'probe' => fn(array $c) => $c['pamatdati'],
        'render' => function (array $d, array $c): string {
            $status = $d['aktivs'] ? nv_badge('Aktīva', 'ok') : nv_badge('Darbība izbeigta', 'risks');
            return nv_kv([
                'Nosaukums'            => nv_e($d['nosaukums']),
                'Reģistrācijas numurs' => nv_e($d['regcode']),
                'Juridiskā forma'      => nv_e($d['tips_teksts']),
                'Reģistrs'             => nv_e($d['registrs']),
                'Statuss'              => $status,
                'Reģistrēta'           => nv_date($d['registrets'])
                                          . ($d['vecums'] !== null ? ' <span class="nv-dim">(' . nv_gadi($d['vecums']) . ')</span>' : ''),
                'Dibināšanas lēmums'   => $d['dibinats'] ? nv_date($d['dibinats']) : null,
                'Juridiskā adrese'     => nv_e($d['adrese']),
            ]);
        },
    ],
    [
        'id' => 'merki', 'virsraksts' => 'Mērķi pēc statūtiem', 'grupa' => 'profils',
        'svars' => 20, 'segums' => 99.9, 'avots' => 'UR — tiesību subjektu darbības mērķi un veidi',
        'probe' => fn(array $c) => $c['merki'],
        'render' => function (array $d, array $c): string {
            if (count($d['punkti']) > 1) {
                $h = '<ul class="nv-list">';
                foreach ($d['punkti'] as $p) $h .= '<li>' . nv_e($p) . '</li>';
                return $h . '</ul>';
            }
            return '<p class="nv-text">' . nl2br(nv_e($d['teksts'])) . '</p>';
        },
    ],
    [
        'id' => 'jomas', 'virsraksts' => 'Darbības jomas', 'grupa' => 'profils',
        'svars' => 30, 'segums' => 81.4, 'avots' => 'UR — biedrību un nodibinājumu darbības jomas',
        'probe' => fn(array $c) => $c['jomas'],
        'render' => fn(array $d, array $c): string => nv_chips($d),
    ],
    [
        'id' => 'parvaldiba', 'virsraksts' => 'Pārvaldības struktūra', 'grupa' => 'profils',
        'svars' => 40, 'segums' => 98.6, 'avots' => 'UR — amatpersonas (rādīts tikai apkopoti)',
        'probe' => fn(array $c) => $c['parvaldiba'],
        'render' => function (array $d, array $c): string {
            $inst = [];
            foreach ($d['institucijas'] as $k => $n) {
                $nosauk = [
                    'EXECUTIVE_BODY' => 'Izpildinstitūcija (valde)', 'EXECUTIVE_BOARD' => 'Valde',
                    'EXECUTIVE_COMMITTEE' => 'Izpildkomiteja', 'SUPERVISORY_BOARD' => 'Padome',
                    'LIQUIDATOR' => 'Likvidators', 'LIQUIDATION_COMMITTEE' => 'Likvidācijas komisija',
                    'AUDITOR' => 'Revidents', 'ADMINISTRATOR' => 'Administrators',
                ][$k] ?? $k;
                $inst[] = $nosauk . ' — ' . $n;
            }
            $parst = [];
            foreach ($d['parstaviba'] as $k => $n) {
                $nosauk = ['INDIVIDUALLY' => 'katrs atsevišķi', 'WITH_AT_LEAST' => 'kopā ar citiem',
                           'JOINTLY' => 'tikai kopīgi'][$k] ?? $k;
                $parst[] = $nosauk . ' — ' . $n;
            }
            $stab = '';
            if ($d['gadi_kops'] !== null) {
                $stab = $d['gadi_kops'] >= 10
                    ? ' ' . nv_badge('sastāvs nemainīts ' . nv_gadi($d['gadi_kops']), 'brid')
                    : ' <span class="nv-dim">(pirms ' . nv_gadi($d['gadi_kops']) . ')</span>';
            }
            return nv_kv([
                'Reģistrētas amatpersonas' => nv_num($d['skaits']),
                'Institūcijas'             => nv_e(implode('; ', $inst)),
                'Pārstāvības tiesības'     => $parst ? nv_e(implode('; ', $parst)) : null,
                'Pirmais ieraksts'         => $d['pirma'] ? nv_date($d['pirma']) : null,
                'Pēdējā izmaiņa'           => $d['pedeja'] ? nv_date($d['pedeja']) . $stab : null,
            ]) . nv_note('Personu vārdi netiek rādīti — sadaļa apkopo tikai struktūru un datumus.');
        },
    ],
    [
        'id' => 'nosaukumi', 'virsraksts' => 'Nosaukuma vēsture', 'grupa' => 'profils',
        'svars' => 50, 'segums' => 15.7, 'avots' => 'UR — reģistra nosaukumu vēsture',
        'probe' => fn(array $c) => $c['nosaukumi'],
        'render' => fn(array $d, array $c): string => nv_table($d, [
            'Iepriekšējais nosaukums' => ['key' => 'name'],
            'Mainīts'                 => ['key' => 'date_to', 'fmt' => fn($v) => nv_date($v)],
        ]),
    ],

    // ── KO BIEDRĪBA DARA ────────────────────────────────────────────────────
    [
        'id' => 'soc_pakalpojumi', 'virsraksts' => 'Sniegtie sociālie pakalpojumi', 'grupa' => 'darbiba',
        'svars' => 10, 'segums' => 0.3, 'avots' => 'Labklājības ministrija — Sociālo pakalpojumu sniedzēju reģistrs',
        'probe' => fn(array $c) => $c['soc_pakalpojumi'],
        'render' => function (array $d, array $c): string {
            $h = nv_table($d['ieraksti'], [
                'Pakalpojums'      => ['key' => 'pakalpojums'],
                'Forma'            => ['key' => 'forma'],
                'Statuss'          => ['key' => 'statuss'],
                'Plānotie klienti' => ['key' => 'planotie_klienti', 'num' => true, 'fmt' => fn($v) => $v > 0 ? nv_num($v) : '—'],
            ]);
            if ($d['klienti_kopa'] > 0) {
                $h .= '<p class="nv-highlight">Kopā plānotas <strong>' . nv_num($d['klienti_kopa'])
                    . '</strong> klientu vietas.</p>';
            }
            if ($d['grupas']) $h .= '<p class="nv-sub">Klientu grupas:</p>' . nv_chips($d['grupas'], 12);
            if ($d['kategorijas']) $h .= '<p class="nv-sub">Kam pakalpojums paredzēts:</p>' . nv_chips($d['kategorijas'], 8);
            return $h;
        },
    ],
    [
        'id' => 'parvaldnieks', 'virsraksts' => 'Dzīvojamo māju pārvaldīšana', 'grupa' => 'darbiba',
        'svars' => 20, 'segums' => 2.8, 'avots' => 'Būvniecības valsts kontroles birojs — Dzīvojamo māju pārvaldnieku saraksts',
        'probe' => fn(array $c) => $c['parvaldnieks'],
        'render' => function (array $d, array $c): string {
            $st = $d['aktivs'] ? nv_badge('Aktīvs pārvaldnieks', 'ok') : nv_badge('Izslēgts no saraksta', 'brid');
            if ($d['aizliegums'] !== '' && !in_array($d['aizliegums'], ['Nē', '0'], true)) {
                $st .= ' ' . nv_badge('Aizliegts veikt darbību', 'risks');
            }
            $h = nv_kv([
                'Statuss'           => $st,
                'Reģistrēts'        => $d['registrets'] ? nv_date($d['registrets']) : null,
                'Mājas lapa'        => $d['majas_lapa'] !== ''
                    ? '<a href="' . nv_e($d['majas_lapa']) . '" rel="nofollow noopener" target="_blank">' . nv_e($d['majas_lapa']) . '</a>' : null,
            ]);
            if ($d['teritorijas']) {
                $h .= '<p class="nv-sub">Apkalpotās teritorijas (' . count($d['teritorijas']) . '):</p>'
                    . nv_chips($d['teritorijas'], 20);
            }
            return $h;
        },
    ],
    [
        'id' => 'deminimis', 'virsraksts' => 'Saņemtais de minimis atbalsts', 'grupa' => 'darbiba',
        'svars' => 30, 'segums' => 2.7, 'avots' => 'Finanšu ministrija — Piešķirtais de minimis atbalsts Latvijā',
        'probe' => fn(array $c) => $c['deminimis'],
        'render' => function (array $d, array $c): string {
            $h = '<p class="nv-highlight">Kopā <strong>' . nv_eur($d['kopa']) . '</strong> '
               . count($d['ieraksti']) . ' piešķīrumos.</p>';
            $gadi = [];
            foreach ($d['pa_gadiem'] as $y => $s) $gadi[] = $y . ': ' . nv_eur($s);
            $h .= nv_chips($gadi, 8);
            $h .= nv_table(array_slice($d['ieraksti'], 0, 25), [
                'Datums'    => ['key' => 'datums', 'fmt' => fn($v) => nv_date($v)],
                'Projekts'  => ['key' => 'projekts'],
                'Piešķīrējs'=> ['key' => 'pieskirejs'],
                'Summa'     => ['key' => 'summa', 'num' => true, 'fmt' => fn($v) => nv_eur($v)],
            ]);
            if (count($d['ieraksti']) > 25) {
                $h .= nv_note('Rādīti 25 jaunākie no ' . count($d['ieraksti']) . ' piešķīrumiem.');
            }
            return $h;
        },
    ],
    [
        'id' => 'es_projekti', 'virsraksts' => 'ES fondu projekti', 'grupa' => 'darbiba',
        'svars' => 40, 'segums' => 0.4, 'avots' => 'Centrālā finanšu un līgumu aģentūra — ES fondu projekti 2014–2020 un 2021–2027',
        'probe' => fn(array $c) => $c['es_projekti'],
        'render' => function (array $d, array $c): string {
            $h = '<p class="nv-highlight">' . count($d['projekti']) . ' projekti, ES līdzfinansējums <strong>'
               . nv_eur($d['es_kopa']) . '</strong> (kopējās izmaksas ' . nv_eur($d['kopejas']) . ').</p>';
            $h .= nv_table($d['projekti'], [
                'Periods'  => ['key' => 'periods'],
                'Projekts' => ['key' => 'nosaukums'],
                'Statuss'  => ['key' => 'statuss'],
                'Fonds'    => ['key' => 'fonds'],
                'ES fin.'  => ['key' => 'es_fin', 'num' => true, 'fmt' => fn($v) => nv_eur($v)],
            ]);
            foreach ($d['projekti'] as $p) {
                $kop = trim((string)$p['kopsavilkums']);
                if ($kop === '') continue;
                $h .= '<details class="nv-det"><summary>' . nv_e($p['nosaukums']) . '</summary>'
                    . '<p class="nv-text">' . nl2br(nv_e(mb_substr($kop, 0, 1200))) . '</p>';
                if (trim((string)$p['merka_grupa']) !== '') {
                    $h .= '<p class="nv-sub">Mērķa grupa: ' . nv_e($p['merka_grupa']) . '</p>';
                }
                $h .= '</details>';
            }
            return $h;
        },
    ],
    [
        'id' => 'lad', 'virsraksts' => 'Lauku atbalsta maksājumi', 'grupa' => 'darbiba',
        'svars' => 50, 'segums' => 0.7, 'avots' => 'Lauku atbalsta dienests — maksājumu saņēmēji',
        'probe' => fn(array $c) => $c['lad'],
        'render' => function (array $d, array $c): string {
            $k = $d['kopsavilkums'];
            $h = nv_kv([
                'ES fondu maksājumi'    => nv_eur($k['eu']),
                'Nacionālais līdzfin.'  => nv_eur($k['cofin']),
                'Novads'                => nv_e((string)$k['novads']),
            ]);
            if ($d['pasakumi']) {
                $h .= nv_table(array_slice($d['pasakumi'], 0, 15), [
                    'Pasākums' => ['key' => 'nosaukums'],
                    'Summa'    => ['key' => 'eu', 'num' => true, 'fmt' => fn($v) => nv_eur($v)],
                ]);
            }
            return $h;
        },
    ],
    [
        'id' => 'delegetie', 'virsraksts' => 'Deleģēti valsts pārvaldes uzdevumi', 'grupa' => 'darbiba',
        'svars' => 60, 'segums' => 0.2, 'avots' => 'UR — publisko personu un iestāžu saraksts',
        'probe' => fn(array $c) => $c['delegetie'],
        'render' => fn(array $d, array $c): string => nv_table($d, [
            'Deleģējusi'  => ['key' => 'deleg_iestade'],
            'No'          => ['key' => 'no_datuma', 'fmt' => fn($v) => nv_date(substr((string)$v, 0, 10))],
            'Līdz'        => ['key' => 'lidz_datumam', 'fmt' => fn($v) => trim((string)$v) === '' ? 'spēkā' : nv_date(substr((string)$v, 0, 10))],
        ]) . nv_note('Valsts vai pašvaldība ir uzticējusi biedrībai pildīt pārvaldes uzdevumu — pastarpināts uzticamības signāls.'),
    ],
    [
        'id' => 'iepirkumi', 'virsraksts' => 'Publiskie iepirkumi (kā pasūtītājam)', 'grupa' => 'darbiba',
        'svars' => 70, 'segums' => 0.1, 'avots' => 'Iepirkumu uzraudzības birojs — iepirkumu paziņojumi',
        'probe' => fn(array $c) => $c['iepirkumi'],
        'render' => fn(array $d, array $c): string => nv_table($d, [
            'Publicēts' => ['key' => 'publicets', 'fmt' => fn($v) => nv_date($v)],
            'Iepirkums' => ['key' => 'nosaukums'],
            'Budžets'   => ['key' => 'budzets', 'num' => true, 'fmt' => fn($v, $r) => $v ? nv_eur($v, (string)($r['valuta'] ?: 'EUR')) : '—'],
        ]) . nv_note('Biedrība ir izsludinājusi iepirkumu — parasti tas nozīmē publiska finansējuma izlietojumu.'),
    ],

    // ── FINANSES ────────────────────────────────────────────────────────────
    [
        'id' => 'kapacitate', 'virsraksts' => 'Kapacitāte laika gaitā', 'grupa' => 'finanses',
        'svars' => 10, 'segums' => 89.2, 'avots' => 'UR — gada pārskatu pamatinformācija un bilances',
        'probe' => function (array $c) {
            if (!$c['parskati'] || !$c['parskati']['gadi']) return null;
            $rows = [];
            foreach ($c['parskati']['gadi'] as $y => $g) {
                $b = $c['bilances']['gadi'][$y] ?? null;
                $rows[] = [
                    'gads' => $y, 'darbinieki' => $g['darbinieki'],
                    'aktivi' => $b['aktivi'] ?? null, 'fondi' => $b['fondi'] ?? null,
                    'valuta' => $g['valuta'], 'ir_bilance' => $g['ir_bilance'],
                ];
            }
            return $rows;
        },
        'render' => function (array $d, array $c): string {
            $h = nv_table($d, [
                'Gads'       => ['key' => 'gads'],
                'Darbinieki' => ['key' => 'darbinieki', 'num' => true, 'fmt' => fn($v) => $v === null ? '—' : nv_num($v, $v == (int)$v ? 0 : 1)],
                'Aktīvi'     => ['key' => 'aktivi', 'num' => true, 'fmt' => fn($v, $r) => $v === null ? '<span class="nv-na">nav</span>' : nv_eur($v, $r['valuta'])],
                'Fondi'      => ['key' => 'fondi', 'num' => true, 'fmt' => fn($v, $r) => $v === null ? '<span class="nv-na">nav</span>' : nv_eur($v, $r['valuta'])],
            ]);
            $bez = count(array_filter($d, fn($r) => !$r['ir_bilance']));
            if ($bez > 0) {
                $h .= nv_note('No ' . count($d) . ' iesniegtajiem pārskatiem ' . $bez
                    . ' gadiem atvērtajos datos ir tikai darbinieku skaits — bilances rādītāji nav publicēti.');
            }
            return $h;
        },
    ],
    [
        'id' => 'bilance', 'virsraksts' => 'Bilance un tās rādītāji', 'grupa' => 'finanses',
        'svars' => 20, 'segums' => 58.4, 'avots' => 'UR — bilances (biedrību gada pārskati)',
        'probe' => fn(array $c) => $c['bilances'],
        'render' => function (array $d, array $c): string {
            $gadi = array_slice($d['gadi'], 0, 5, true);
            $rows = array_values($gadi);
            $h = nv_table($rows, [
                'Gads'                  => ['key' => 'gads'],
                'Nauda'                 => ['key' => 'nauda', 'num' => true, 'fmt' => fn($v, $r) => nv_eur($v, $r['valuta'])],
                'Apgrozāmie'            => ['key' => 'apgroz', 'num' => true, 'fmt' => fn($v, $r) => nv_eur($v, $r['valuta'])],
                'Ilgtermiņa ieguld.'    => ['key' => 'ilgtermina', 'num' => true, 'fmt' => fn($v, $r) => nv_eur($v, $r['valuta'])],
                'Aktīvi kopā'           => ['key' => 'aktivi', 'num' => true, 'fmt' => fn($v, $r) => nv_eur($v, $r['valuta'])],
                'Īstermiņa saistības'   => ['key' => 'ist_saist', 'num' => true, 'fmt' => fn($v, $r) => nv_eur($v, $r['valuta'])],
                'Ilgtermiņa saistības'  => ['key' => 'ilg_saist', 'num' => true, 'fmt' => fn($v, $r) => nv_eur($v, $r['valuta'])],
                'Fondi'                 => ['key' => 'fondi', 'num' => true, 'fmt' => fn($v, $r) => nv_eur($v, $r['valuta'])],
            ]);
            $h .= '<p class="nv-sub">Rādītāji, ko var aprēķināt no bilances:</p>';
            $h .= nv_table($rows, [
                'Gads'                    => ['key' => 'gads'],
                'Kopējā likviditāte'      => ['key' => 'likviditate', 'num' => true, 'fmt' => fn($v) => nv_koef($v)],
                'Naudas segums'           => ['key' => 'naudas_seg', 'num' => true, 'fmt' => fn($v) => nv_koef($v)],
                'Fondu īpatsvars'         => ['key' => 'autonomija', 'num' => true, 'fmt' => fn($v) => $v === null ? '<span class="nv-na">—</span>' : nv_pct($v)],
                'Saistību slogs'          => ['key' => 'saist_slogs', 'num' => true, 'fmt' => fn($v) => $v === null ? '<span class="nv-na">—</span>' : nv_pct($v)],
                'Ilgterm. ieguld. īpatsv.'=> ['key' => 'pamatl_ipat', 'num' => true, 'fmt' => fn($v) => $v === null ? '<span class="nv-na">—</span>' : nv_pct($v)],
            ]);
            return $h . nv_note('Rentabilitātes rādītāji (peļņas marža, ROA, ROE) biedrībai netiek rēķināti — '
                . 'tiem vajadzīgs ieņēmumu un izdevumu pārskats, kas atvērtajos datos netiek publicēts.');
        },
    ],
    [
        'id' => 'rezultats', 'virsraksts' => 'Gada rezultāts (aplēse)', 'grupa' => 'finanses',
        'svars' => 30, 'segums' => 29.0, 'avots' => 'Atvasināts no UR bilancēm — fondu izmaiņa starp gadiem',
        'probe' => function (array $c) {
            if (!$c['bilances']) return null;
            $r = array_values(array_filter($c['bilances']['gadi'], fn($g) => $g['rezultats'] !== null));
            return $r ?: null;
        },
        'render' => function (array $d, array $c): string {
            $h = '<p class="nv-warn"><strong>Šī ir aplēse, nevis publicēts skaitlis.</strong> '
               . 'Biedrību ieņēmumu un izdevumu pārskats atvērtajos datos netiek publicēts, tāpēc gada '
               . 'pārpalikums vai iztrūkums šeit ir aprēķināts kā fondu izmaiņa starp diviem gadiem. '
               . 'Ja fondos ieskaitīti mērķfondi vai veikta pārskatu pārrēķināšana, aplēse var atšķirties '
               . 'no faktiskā rezultāta.</p>';
            $h .= nv_table(array_slice($d, 0, 6), [
                'Gads'   => ['key' => 'gads'],
                'Fondi gada beigās' => ['key' => 'fondi', 'num' => true, 'fmt' => fn($v, $r) => nv_eur($v, $r['valuta'])],
                'Izmaiņa pret iepr. gadu' => ['key' => 'rezultats', 'num' => true, 'fmt' => function ($v, $r) {
                    if (!$r['rezultats_ticams']) return '<span class="nv-na">nav ticami aplēšams</span>';
                    $cls = $v > 0 ? 'nv-pos' : ($v < 0 ? 'nv-neg' : '');
                    return '<span class="' . $cls . '">' . ($v > 0 ? '+' : '') . nv_eur($v, $r['valuta']) . '</span>';
                }],
                'Vērtējums' => ['key' => 'rezultats', 'fmt' => function ($v, $r) {
                    if (!$r['rezultats_ticams']) return nv_badge('ārpus ticamības robežām', 'brid');
                    return $v > 0 ? nv_badge('pārpalikums', 'ok') : ($v < 0 ? nv_badge('iztrūkums', 'brid') : nv_badge('bez izmaiņām', 'info'));
                }],
            ]);
            return $h;
        },
    ],
    [
        'id' => 'etalons', 'virsraksts' => 'Salīdzinājums ar darbības jomu', 'grupa' => 'finanses',
        'svars' => 40, 'segums' => 47.0, 'avots' => 'Aprēķināts no UR bilancēm pa darbības jomām',
        'probe' => function (array $c) {
            if (!$c['etaloni'] || !$c['bilances']) return null;
            $out = [];
            foreach ($c['etaloni'] as $e) {
                $g = $c['bilances']['gadi'][(int)$e['gads']] ?? null;
                if ($g === null || $g['valuta'] !== 'EUR') continue;
                $out[] = ['etalons' => $e, 'gads' => $g];
            }
            return $out ?: null;
        },
        'render' => function (array $d, array $c): string {
            $h = '';
            if (count($d) > 1) {
                $h .= '<p class="nv-highlight">Biedrība darbojas <strong>' . count($d)
                    . '</strong> jomās, tāpēc salīdzinājums rādīts pret katru no tām atsevišķi.</p>';
            }
            foreach ($d as $blok) {
                $e = $blok['etalons']; $g = $blok['gads'];
                $ta = (float)$g['aktivi'];
                $poz = $ta < (float)$e['aktivi_p25'] ? 'zemākajā ceturtdaļā'
                     : ($ta > (float)$e['aktivi_p75'] ? 'augstākajā ceturtdaļā' : 'vidējā pusē');
                $maks = max((float)$e['aktivi_p75'] * 1.5, $ta, 1.0);
                $h .= '<p class="nv-sub">' . nv_e($e['joma']) . ' — ' . nv_num($e['n'])
                    . ' biedrības ar bilanci par ' . nv_e($e['gads']) . '. gadu; šī biedrība pēc aktīviem ir '
                    . $poz . '</p>';
                $h .= '<div class="nv-cmp">';
                foreach ([
                    ['Šī biedrība', $ta, true],
                    ['Jomas mediāna', (float)$e['aktivi_med'], false],
                    ['Jomas 25 %', (float)$e['aktivi_p25'], false],
                    ['Jomas 75 %', (float)$e['aktivi_p75'], false],
                ] as [$lab, $val, $sav]) {
                    $h .= '<div class="nv-cmp-row' . ($sav ? ' nv-cmp-self' : '') . '">'
                        . '<span class="nv-cmp-lab">' . nv_e($lab) . '</span>'
                        . nv_bar($val / $maks)
                        . '<span class="nv-cmp-val">' . nv_eur($val) . '</span></div>';
                }
                $h .= '</div>';
                $h .= '<p class="nv-note">Jomas mediānie fondi ' . nv_eur((float)$e['fondi_med'])
                    . ' · vidēji ' . nv_num((float)$e['darbin_vid'], 2) . ' darbinieki · '
                    . nv_num((float)$e['neg_fondi_pct'], 1) . ' % biedrību ar negatīviem fondiem.</p>';
            }
            return $h . nv_note('Etaloni rēķināti tikai no tām biedrībām, kurām attiecīgajā gadā ir publicēta bilance '
                . '(aptuveni trešdaļa no visām) — mediāna raksturo šo apakškopu, ne visas jomas biedrības.');
        },
    ],

    // ── UZTICAMĪBA UN RISKS ─────────────────────────────────────────────────
    [
        'id' => 'slo', 'virsraksts' => 'Sabiedriskā labuma statuss', 'grupa' => 'uzticamiba',
        'svars' => 10, 'segums' => 5.4, 'avots' => 'Valsts ieņēmumu dienests — Sabiedriskā labuma organizāciju reģistrs',
        'probe' => fn(array $c) => $c['slo'],
        'render' => function (array $d, array $c): string {
            if ($d['speka']) {
                $h = '<p class="nv-highlight">' . nv_badge('Sabiedriskā labuma organizācija', 'ok')
                   . ' Statuss spēkā no ' . nv_date($d['speka_no']) . '</p>'
                   . '<p class="nv-text">Statuss dod ziedotājiem tiesības uz nodokļu atlaidi un nozīmē, '
                   . 'ka VID ir atzinis biedrības darbību par sabiedriski derīgu.</p>';
            } else {
                $h = '<p class="nv-highlight">' . nv_badge('Sabiedriskā labuma statuss atņemts', 'brid')
                   . ($d['atnemts_datums'] ? ' ' . nv_date($d['atnemts_datums']) : '') . '</p>'
                   . '<p class="nv-text">Biedrībai agrāk bija sabiedriskā labuma organizācijas statuss, '
                   . 'bet tas vairs nav spēkā.</p>';
            }
            if ($d['jomas']) $h .= '<p class="nv-sub">Sabiedriskā labuma jomas:</p>' . nv_chips($d['jomas']);
            $h .= nv_table($d['lemumi'], [
                'Lēmums'   => ['key' => 'lemuma_butiba'],
                'Datums'   => ['key' => 'lemuma_datums', 'fmt' => fn($v) => nv_date($v)],
                'Statuss'  => ['key' => 'statuss'],
            ]);
            return $h;
        },
    ],
    [
        'id' => 'pvn', 'virsraksts' => 'PVN maksātāja statuss', 'grupa' => 'uzticamiba',
        'svars' => 20, 'segums' => 5.4, 'avots' => 'VID — PVN maksātāju reģistrs',
        'probe' => fn(array $c) => $c['pvn'],
        'render' => fn(array $d, array $c): string => nv_kv([
            'Statuss'    => $d['aktivs'] ? nv_badge('Reģistrēts PVN maksātājs', 'ok') : nv_badge('Izslēgts no PVN reģistra', 'info'),
            'Reģistrēts' => $d['registrets'] !== '' ? nv_date($d['registrets']) : null,
            'Izslēgts'   => $d['izslegts'] !== '' ? nv_date($d['izslegts']) : null,
        ]) . nv_note('PVN reģistrācija norāda uz saimniecisko darbību — biedrība veic arī apmaksātus darījumus.'),
    ],
    [
        'id' => 'disciplina', 'virsraksts' => 'Gada pārskatu disciplīna', 'grupa' => 'uzticamiba',
        'svars' => 30, 'segums' => 100.0, 'avots' => 'UR — gada pārskatu pamatinformācija',
        'probe' => fn(array $c) => $c['parskati'],
        'render' => function (array $d, array $c): string {
            if ($d['pedejais'] === null) {
                $vec = $c['pamatdati']['vecums'] ?? null;
                $jauna = $vec !== null && $vec < 2;
                return '<p class="nv-highlight">' . nv_badge($jauna ? 'Nav pārskatu — nesen reģistrēta' : 'Nav iesniegts neviens gada pārskats', $jauna ? 'info' : 'risks') . '</p>'
                    . '<p class="nv-text">Atvērtajos datos nav neviena šīs biedrības gada pārskata.'
                    . ($jauna ? ' Biedrība reģistrēta nesen, tāpēc pirmā pārskata termiņš, iespējams, vēl nav pienācis.' : '') . '</p>';
            }
            $lab = ['kartiba' => ['Pārskati iesniegti laikus', 'ok'],
                    'aizkave' => ['Pēdējais pārskats ar nokavēšanos', 'brid'],
                    'kave'    => ['Pārskati kavēti', 'risks']][$d['statuss']];
            $h = '<p class="nv-highlight">' . nv_badge($lab[0], $lab[1]) . '</p>';
            $h .= nv_kv([
                'Pēdējais pārskats'   => 'par ' . nv_e($d['pedejais']) . '. gadu',
                'Pārskatu skaits'     => nv_num($d['kopa']),
                'Iztrūkstošie gadi'   => $d['atpaliek'] > 0 ? nv_num($d['atpaliek']) : null,
            ]);
            return $h . nv_note('Gada pārskats par iepriekšējo gadu jāiesniedz līdz 31. martam.');
        },
    ],
    [
        'id' => 'riski', 'virsraksts' => 'Reģistrētie riska ieraksti', 'grupa' => 'uzticamiba',
        'svars' => 40, 'segums' => 6.5, 'avots' => 'UR — likvidācijas, nodrošinājuma līdzekļi, apturēšanas, maksātnespēja',
        'probe' => fn(array $c) => $c['riski'],
        'render' => function (array $d, array $c): string {
            $h = '';
            $bloki = [
                'likvidacija'   => 'Likvidācijas process',
                'nodrosinajums' => 'Nodrošinājuma līdzekļi',
                'apturesana'    => 'Darbības apturēšana vai aizliegumi',
                'maksatnespeja' => 'Maksātnespējas process',
            ];
            foreach ($bloki as $k => $nos) {
                if (empty($d[$k])) continue;
                $h .= '<p class="nv-sub">' . nv_e($nos) . '</p>';
                $cols = ['Veids' => ['key' => 'veids'], 'Datums' => ['key' => 'datums', 'fmt' => fn($v) => nv_date($v)]];
                if ($k === 'nodrosinajums') $cols['Iestāde'] = ['key' => 'iestade'];
                if ($k === 'maksatnespeja') $cols['Tiesa'] = ['key' => 'tiesa'];
                if (in_array($k, ['likvidacija', 'apturesana'], true)) $cols['Pamats'] = ['key' => 'pamats'];
                $h .= nv_table($d[$k], $cols);
            }
            return $h;
        },
    ],

    // ── PAR DATIEM ──────────────────────────────────────────────────────────
    [
        'id' => 'nav_zinams', 'virsraksts' => 'Kas par šo biedrību nav zināms', 'grupa' => 'beigas',
        'svars' => 90, 'segums' => 100.0, 'avots' => '',
        'probe' => fn(array $c) => true,
        'render' => function ($d, array $c): string {
            $tr = [];
            $tr[] = '<strong>Ieņēmumi, izdevumi, ziedojumi un biedru naudas.</strong> Uzņēmumu reģistra '
                  . 'atvērtajos datos biedrību ieņēmumu un izdevumu pārskats netiek publicēts — publicēta '
                  . 'tiek tikai bilance, un arī tā ne visiem pārskatiem.';
            $tr[] = '<strong>Samaksātie nodokļi un algu fonds.</strong> VID publicē nodokļu kopsummas tikai '
                  . 'par komersantiem (SIA, AS, IK u. c.); biedrības šajos datos nav iekļautas.';
            $tr[] = '<strong>Biedru skaits un biedru saraksts.</strong> Netiek publicēts nevienā atvērto datu kopā.';
            $tr[] = '<strong>Amatpersonu vārdi.</strong> Ir pieejami Uzņēmumu reģistrā, bet šajā lapā netiek '
                  . 'rādīti — sadaļa apkopo tikai pārvaldības struktūru bez personu datiem.';
            if (empty($c['bilances'])) {
                $tr[] = '<strong>Bilances rādītāji.</strong> Šai biedrībai atvērtajos datos nav neviena '
                      . 'gada ar publicētu bilanci.';
            }
            $h = '<ul class="nv-list">';
            foreach ($tr as $t) $h .= '<li>' . $t . '</li>';
            return $h . '</ul>';
        },
    ],

    ];
}

/**
 * Palaiž visus paneļus pret kontekstu. Atgriež [atrastie, iztrukstosie].
 * "Iztrūkstošie" ir tikpat svarīgi kā atrastie — lapa parāda, KO nav.
 */
function nvo_run_panels(array $ctx): array {
    $atrasti = []; $iztrukst = [];
    foreach (nvo_panel_registry() as $p) {
        try { $dati = ($p['probe'])($ctx); } catch (Throwable $e) { $dati = null; }
        if ($dati === null || $dati === [] || $dati === false) { $iztrukst[] = $p; continue; }
        $p['dati'] = $dati;
        $atrasti[] = $p;
    }
    $kart = static function (array $a, array $b): int {
        $ga = array_search($a['grupa'], array_keys(NVO_GRUPAS), true);
        $gb = array_search($b['grupa'], array_keys(NVO_GRUPAS), true);
        return $ga === $gb ? ($a['svars'] <=> $b['svars']) : ($ga <=> $gb);
    };
    usort($atrasti, $kart);
    usort($iztrukst, $kart);
    return [$atrasti, $iztrukst];
}
