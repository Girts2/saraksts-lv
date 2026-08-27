<?php
/**
 * registrs/lib/iepirkumi_kopsavilkums.php — EIS iepirkumu rindu apkopojums.
 *
 * Atsevišķs fails ar nolūku: to pašu apkopojumu lieto GAN panelis
 * (view/partials/sadalas/iepirkumi.php), GAN MI prompts (page_builder.php).
 * Ja katrs rēķinātu pats, agri vai vēlu tie sāktu rādīt dažādus skaitļus, un
 * tieši šai kopai kļūda ir dārga: naiva summēšana dod simtiem miljonu, kuru
 * uzņēmumam nekad nav bijis (sk. build/convert.php build_iepirkumi_table).
 *
 * GALVENAIS LIKUMS: kopsummā ieskaita TIKAI līgumus ar VIENU uzvarētāju.
 * Vienošanās, kur uzvarētāju ir vairāki, avotā atkārto pilnu (maksimālo) summu
 * katram no tiem, tāpēc tās atgriežam atsevišķi.
 */
declare(strict_types=1);

/** EIS "apjoms nav ierobežots" vērtība ir 9 999 999 999,99 — summās to neskaitām. */
const IEPIRKUMI_SENTINEL = 999999999.0;

function reg_iepirkumi_kopsavilkums(array $rows): array {
    // Apkopojumu pieprasa GAN sadaļa, GAN MI prompts (page_builder.php), tāpēc
    // bez keša tas katrā lapas renderī izpildījās divreiz (audits 2026-08-19).
    static $memo = [];
    $atsl = count($rows) . ':' . crc32(json_encode(array_slice($rows, 0, 3)) . json_encode(array_slice($rows, -3)));
    if (isset($memo[$atsl])) return $memo[$atsl];

    $k = [
        'n' => 0, 'kopsumma' => 0.0, 'no' => '', 'lidz' => '',
        'bez_summas' => 0, 'cita_val' => 0, 'izbeigti' => 0, 'vv' => 0, 'augusi' => 0,
        'sapludinatas' => 0,
        'solo' => [], 'kopigi' => [], 'pasutitaji' => [], 'gadi' => [],
    ];
    // CETURTAIS dubultošanās modelis (audits 2026-08-26; trīs zināmie ir daļas,
    // grozījumi un vairāki uzvarētāji): viena iepirkuma summa avotā atkārtojas
    // vairākos NESAISTĪTOS dokumentos ar VIENU uzvarētāju — katrs kļūst par savu
    // ķēdi, un summas saskaitījās. Dzīvais piemērs: Rīgas ūdens iepirkumam 58616
    // ir 81 identisks dokuments pa 999 000 € (griestu skaitlis, 53 vienā dienā) —
    // panelis rādīja 84,9 milj. €, no kuriem 81 milj. bija šis artefakts; visā DB
    // pārpalikums 3,59 mljrd. €. Vienādu (iepirkuma_id, summa) solo rindu skaitām
    // VIENREIZ un piezīmē pasakām, cik sapludināts — tas pats princips, kas
    // de minimis dedupam.
    $redzetas = [];
    $nosauk = static function (string $s): string {
        // Avotā nosaukumi ir ar dubultotiem apostrofiem: ''Latvijas valsts meži'' AS.
        $s = str_replace("''", '"', trim($s));
        return preg_replace('/\s{2,}/u', ' ', $s) ?? $s;
    };

    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $amt = (float)($r['summa'] ?? 0);
        $val = trim((string)($r['valuta'] ?? ''));
        $uzv = max(1, (int)($r['uzv_skaits'] ?? 1));
        $d   = substr(trim((string)($r['datums'] ?? '')), 0, 10);
        $ir_eur = ($val === 'EUR' && $amt > 0 && $amt < IEPIRKUMI_SENTINEL);
        if ($d !== '') {
            if ($k['no'] === '' || $d < $k['no']) $k['no'] = $d;
            if ($k['lidz'] === '' || $d > $k['lidz']) $k['lidz'] = $d;
        }
        $poz = [
            'amt'   => $ir_eur ? $amt : 0.0,
            'zin'   => $ir_eur,
            'd'     => $d,
            'pas'   => $nosauk((string)($r['pasutitajs'] ?? '')),
            'pasnr' => preg_replace('/\D/', '', (string)($r['pasutitaja_regnr'] ?? '')),
            'nos'   => $nosauk((string)($r['iep_nosaukums'] ?? '')),
            'iid'   => preg_replace('/\D/', '', (string)($r['iepirkuma_id'] ?? '')),
            'uzv'   => $uzv,
            'izb'   => (int)($r['izbeigts'] ?? 0) === 1,
        ];
        // Vienāda (iepirkuma_id, summa) solo rinda = tas pats līgums citā
        // dokumentā, ne jauns līgums — skaitām vienreiz (sk. komentāru augšā).
        // Tikai rindām ar zināmu iepirkuma_id un īstu EUR summu; daudzuzvarētāju
        // rindas te neietilpst (tās tāpat neskaita kopsummā).
        if ($uzv === 1 && $ir_eur && $poz['iid'] !== '') {
            $dk = $poz['iid'] . '|' . $poz['amt'];
            if (isset($redzetas[$dk])) { $k['sapludinatas']++; continue; }
            $redzetas[$dk] = true;
        }
        // izbeigti skaitām PIRMS kopīgo atzarošanas — izbeigta daudzuzvarētāju
        // vienošanās citādi piezīmē nemaz neparādījās (audits 2026-08-20).
        if ($poz['izb']) $k['izbeigti']++;
        if ($uzv > 1) { $k['kopigi'][] = $poz; continue; }
        if (!$ir_eur) {
            if ($val !== '' && $val !== 'EUR') $k['cita_val']++;
            else $k['bez_summas']++;
        } else {
            $k['kopsumma'] += $amt;
            $sak = $r['sak_summa'] ?? null;
            if ($sak !== null && $sak !== '' && (float)$sak > 0 && $amt > (float)$sak + 0.5) $k['augusi']++;
        }
        if ((int)($r['vv'] ?? 0) === 1) $k['vv']++;

        $g = $poz['pas'] !== '' ? $poz['pas'] : 'Nav norādīts';
        if (!isset($k['pasutitaji'][$g])) $k['pasutitaji'][$g] = [0.0, 0, $poz['pasnr']];
        $k['pasutitaji'][$g][0] += $poz['amt'];
        $k['pasutitaji'][$g][1]++;
        $y = substr($d, 0, 4);
        if ($y !== '') {
            if (!isset($k['gadi'][$y])) $k['gadi'][$y] = [0.0, 0];
            $k['gadi'][$y][0] += $poz['amt'];
            $k['gadi'][$y][1]++;
        }
        $k['solo'][] = $poz;
    }
    $k['n'] = count($k['solo']);
    uasort($k['pasutitaji'], static fn($a, $b) => $b[0] <=> $a[0] ?: $b[1] <=> $a[1]);
    usort($k['solo'], static fn($a, $b) => $b['amt'] <=> $a['amt']);
    usort($k['kopigi'], static fn($a, $b) => $b['amt'] <=> $a['amt']);
    krsort($k['gadi']);
    return $memo[$atsl] = $k;
}

/**
 * Kompakts kopsavilkums MI promptam. Jēlrindas MI NEDOD: piecas nejaušas rindas
 * no 2400 līgumiem ir maldinošas, un starp tām mēdz būt daudzuzvarētāju summas
 * (100 milj. € par 44 piegādātājiem), ko modelis citētu kā uzņēmuma apgrozījumu.
 */
function reg_iepirkumi_ai_kopsavilkums(array $rows): array {
    $k = reg_iepirkumi_kopsavilkums($rows);
    if ($k['n'] === 0 && !$k['kopigi']) return [];
    $lielakie = [];
    foreach (array_slice($k['solo'], 0, 5) as $x) {
        $lielakie[] = [
            'summa_eur' => $x['zin'] ? round($x['amt'], 2) : null,
            'datums' => $x['d'], 'pasutitajs' => $x['pas'], 'iepirkums' => $x['nos'],
        ];
    }
    $pas = [];
    foreach (array_slice($k['pasutitaji'], 0, 5, true) as $g => [$s, $c, $rn]) {
        $pas[] = ['pasutitajs' => $g, 'summa_eur' => round($s, 2), 'ligumu_skaits' => $c];
    }
    $gadi = [];
    foreach ($k['gadi'] as $y => [$s, $c]) $gadi[$y] = ['summa_eur' => round($s, 2), 'ligumu_skaits' => $c];
    return [
        'kopa_eur' => round($k['kopsumma'], 2),
        'ligumu_skaits' => $k['n'],
        'periods' => ['no' => $k['no'], 'lidz' => $k['lidz']],
        'lielakie_ligumi' => $lielakie,
        'pasutitaji' => $pas,
        'pa_gadiem' => $gadi,
        'bez_publicetas_summas' => $k['bez_summas'],
        'izbeigti_pirms_termina' => $k['izbeigti'],
        'visparigas_vienosanas' => $k['vv'],
        'kopigas_vienosanas_skaits' => count($k['kopigi']),
        'citas_valutas_ligumi' => $k['cita_val'],
        'piezime' => 'Summa ir līgumos nolīgtā, ne izmaksātā. Vienošanās ar vairākiem '
            . 'uzvarētājiem kopsummā NAV ieskaitītas (tur apjoms ir kopīgs un maksimālais). '
            . 'Dati: EIS publicētie dokumenti kopš 2018. gada; 2018.-2020. gads nav pilnīgs.',
    ];
}
