<?php
/**
 * registrs/lib/esfondi_kopsavilkums.php — CFLA ES fondu rindu apkopojums.
 *
 * Tāpat kā iepirkumiem: apkopojumu lieto GAN panelis, GAN MI prompts, tāpēc tas
 * dzīvo vienuviet. Šeit tas ir vēl svarīgāk, jo naudai ir TRĪS dažādas nozīmes
 * atkarībā no lomas, un tās nedrīkst saskaitīt kopā:
 *   sanemejs    — saņemtais atbalsts (ES līdzfinansējums projektam),
 *   partneris   — dalība projektā BEZ naudas datiem avotā,
 *   izpilditajs — nopelnīts piegādes līgums projekta ietvaros.
 *
 * `izmaksats` NAV ES daļa: veikto maksājumu summa attiecas pret projekta kopējām
 * ATTIECINĀMAJĀM izmaksām (mediāna 1,00), ne pret ES līdzfinansējumu (1,18).
 */
declare(strict_types=1);

function reg_esfondi_kopsavilkums(array $rows): array {
    // Apkopojumu pieprasa GAN sadaļa, GAN MI prompts (page_builder.php), tāpēc
    // bez keša tas katrā lapas renderī izpildījās divreiz (audits 2026-08-19).
    static $memo = [];
    $atsl = count($rows) . ':' . crc32(json_encode(array_slice($rows, 0, 3)) . json_encode(array_slice($rows, -3)));
    if (isset($memo[$atsl])) return $memo[$atsl];

    $k = [
        'no' => '', 'lidz' => '', 'periodi' => [], 'fondi' => [],
        'sanemejs'    => ['n' => 0, 'es_fin' => 0.0, 'izmaksas' => 0.0, 'izmaksats' => 0.0,
                          'izmaksats_n' => 0, 'pabeigti' => 0, 'projekti' => []],
        'partneris'   => ['n' => 0, 'projekti' => []],
        'izpilditajs' => ['n' => 0, 'summa' => 0.0, 'bez_summas' => 0, 'ligumi' => [], 'pasutitaji' => []],
    ];
    $teksts = static function ($v): string {
        $s = trim((string)$v);
        return preg_replace('/\s{2,}/u', ' ', $s) ?? $s;
    };

    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $loma = trim((string)($r['loma'] ?? ''));
        if (!isset($k[$loma]) || !is_array($k[$loma]) || !isset($k[$loma]['n'])) continue;
        $per = trim((string)($r['periods'] ?? ''));
        if ($per !== '') $k['periodi'][$per] = true;
        foreach ([$r['sakums'] ?? '', $r['beigas'] ?? '', $r['datums'] ?? ''] as $d) {
            $d = substr(trim((string)$d), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) continue;
            if ($k['no'] === '' || $d < $k['no']) $k['no'] = $d;
            if ($k['lidz'] === '' || $d > $k['lidz']) $k['lidz'] = $d;
        }
        $poz = [
            'nr'      => $teksts($r['projekta_nr'] ?? ''),
            'nos'     => $teksts($r['projekta_nosaukums'] ?? ''),
            'pasak'   => $teksts($r['pasakums'] ?? ''),
            'fonds'   => $teksts($r['fonds'] ?? ''),
            'statuss' => $teksts($r['statuss'] ?? ''),
            'periods' => $per,
            'sakums'  => substr(trim((string)($r['sakums'] ?? '')), 0, 10),
            'beigas'  => substr(trim((string)($r['beigas'] ?? '')), 0, 10),
            'sanem'   => $teksts($r['sanemejs'] ?? ''),
        ];
        if ($loma === 'sanemejs') {
            $es = (float)($r['es_fin'] ?? 0);
            $iz = (float)($r['izmaksas'] ?? 0);
            $poz['es'] = $es;
            $poz['izmaksas'] = $iz;
            $k['sanemejs']['n']++;
            $k['sanemejs']['es_fin'] += $es;
            $k['sanemejs']['izmaksas'] += $iz;
            if ($poz['statuss'] === 'Pabeigts') $k['sanemejs']['pabeigti']++;
            $m = $r['izmaksats'] ?? null;
            if ($m !== null && $m !== '' && (float)$m > 0) {
                $k['sanemejs']['izmaksats'] += (float)$m;
                $k['sanemejs']['izmaksats_n']++;
            }
            if ($poz['fonds'] !== '' && $es > 0) $k['fondi'][$poz['fonds']] = ($k['fondi'][$poz['fonds']] ?? 0) + $es;
            $k['sanemejs']['projekti'][] = $poz;
        } elseif ($loma === 'partneris') {
            $k['partneris']['n']++;
            $k['partneris']['projekti'][] = $poz;
        } else {
            $s = (float)($r['summa'] ?? 0);
            $poz['summa'] = $s;
            $poz['veids'] = $teksts($r['iep_veids'] ?? '');
            $poz['datums'] = substr(trim((string)($r['datums'] ?? '')), 0, 10);
            $k['izpilditajs']['n']++;
            if ($s > 0) $k['izpilditajs']['summa'] += $s; else $k['izpilditajs']['bez_summas']++;
            $g = $poz['sanem'] !== '' ? $poz['sanem'] : 'Nav norādīts';
            if (!isset($k['izpilditajs']['pasutitaji'][$g])) $k['izpilditajs']['pasutitaji'][$g] = [0.0, 0];
            $k['izpilditajs']['pasutitaji'][$g][0] += max(0.0, $s);
            $k['izpilditajs']['pasutitaji'][$g][1]++;
            $k['izpilditajs']['ligumi'][] = $poz;
        }
    }
    usort($k['sanemejs']['projekti'], static fn($a, $b) => $b['es'] <=> $a['es']);
    usort($k['izpilditajs']['ligumi'], static fn($a, $b) => $b['summa'] <=> $a['summa']);
    usort($k['partneris']['projekti'], static fn($a, $b) => strcmp($b['sakums'], $a['sakums']));
    uasort($k['izpilditajs']['pasutitaji'], static fn($a, $b) => $b[0] <=> $a[0] ?: $b[1] <=> $a[1]);
    arsort($k['fondi']);
    krsort($k['periodi']);
    return $memo[$atsl] = $k;
}

/** Kompakts kopsavilkums MI promptam (jēlrindas MI nedodam — sk. page_builder.php). */
function reg_esfondi_ai_kopsavilkums(array $rows): array {
    $k = reg_esfondi_kopsavilkums($rows);
    $out = ['periods' => ['no' => $k['no'], 'lidz' => $k['lidz']],
            'piezime' => 'Trīs dažādas lomas, kuru naudu NEDRĪKST saskaitīt kopā: saņēmējs = saņemtais '
                . 'ES atbalsts, partneris = dalība bez naudas datiem, izpildītājs = nopelnīts piegādes '
                . 'līgums projektā. "izmaksats_eur" ir izmaksātais projekta ATTIECINĀMAIS finansējums '
                . '(visi avoti kopā), nevis tikai ES daļa. Avots: CFLA, periodi 2014-2020, 2021-2027, '
                . 'Atveseļošanas fonds.'];
    if ($k['sanemejs']['n'] > 0) {
        $pr = [];
        foreach (array_slice($k['sanemejs']['projekti'], 0, 5) as $p) {
            $pr[] = ['projekts' => $p['nos'], 'programma' => $p['pasak'], 'fonds' => $p['fonds'],
                     'es_finansejums_eur' => round($p['es'], 2), 'attiecinamas_izmaksas_eur' => round($p['izmaksas'], 2),
                     'statuss' => $p['statuss'], 'sakums' => $p['sakums'], 'beigas' => $p['beigas']];
        }
        $out['ka_finansejuma_sanemejs'] = [
            'projektu_skaits' => $k['sanemejs']['n'],
            'pabeigti' => $k['sanemejs']['pabeigti'],
            'es_finansejums_eur' => round($k['sanemejs']['es_fin'], 2),
            'attiecinamas_izmaksas_eur' => round($k['sanemejs']['izmaksas'], 2),
            'izmaksats_eur' => round($k['sanemejs']['izmaksats'], 2),
            'izmaksats_projektiem' => $k['sanemejs']['izmaksats_n'],
            'lielakie_projekti' => $pr,
        ];
    }
    if ($k['partneris']['n'] > 0) {
        $out['ka_sadarbibas_partneris'] = ['projektu_skaits' => $k['partneris']['n']];
    }
    if ($k['izpilditajs']['n'] > 0) {
        $lig = [];
        foreach (array_slice($k['izpilditajs']['ligumi'], 0, 5) as $p) {
            $lig[] = ['summa_bez_pvn_eur' => round($p['summa'], 2), 'datums' => $p['datums'],
                      'pasutitajs' => $p['sanem'], 'projekts' => $p['nos']];
        }
        $out['ka_izpilditajs_projektos'] = [
            'ligumu_skaits' => $k['izpilditajs']['n'],
            'kopa_bez_pvn_eur' => round($k['izpilditajs']['summa'], 2),
            'lielakie_ligumi' => $lig,
        ];
    }
    return (count($out) > 2) ? $out : [];
}
