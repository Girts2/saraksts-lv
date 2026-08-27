<?php
/**
 * riska_kopsavilkums.php — tiesiskā statusa kaskādes VIENA aprēķina vieta.
 *
 * Izcelts no view/partials/sadalas/tiesiskais.php (2026-08-25), lai to pašu
 * karogu bez dublēšanās var rādīt arī riska josla lapas augšā: divi neatkarīgi
 * aprēķini agri vai vēlu sāk runāt pretrunās (tā paša iemesla dēļ MI prompti
 * lieto lib/risk_semaphore.php, ne savu kopiju). Loģika pārcelta BURTISKI —
 * uzvedības izmaiņas šeit nav, to pierāda zelta diff pār 41 kategoriju lapu.
 *
 * Lieto: view/partials/sadalas/tiesiskais.php (panelis) un
 *        view/partials/riska_josla.php (kopsavilkuma josla).
 */
declare(strict_types=1);

require_once __DIR__ . '/sadalu_formats.php';

if (!function_exists('reg_ts_form')) {
    /** proceeding_form → [cilvēklasāms nosaukums, īsais tips]. */
    function reg_ts_form(?string $f): array {
        switch (strtoupper(trim((string)$f))) {
            case 'INSOLVENCY':                    return ['Maksātnespējas process', 'mn'];
            case 'LEGAL_PROTECTION':              return ['Tiesiskās aizsardzības process (TAP)', 'tap'];
            case 'OUT_OF_COURT_LEGAL_PROTECTION': return ['Ārpustiesas tiesiskās aizsardzības process', 'tap'];
            default:                              return ['Process', 'cits'];
        }
    }

    /** UR datuma lauks → 'YYYY-MM-DD' vai '' (tukšs/0000-00-00 = nav). */
    function reg_ts_date($v): string {
        $s = trim((string)$v);
        return ($s === '' || $s === '0000-00-00') ? '' : substr($s, 0, 10);
    }
}

if (!function_exists('reg_tiesiskais_kopsavilkums')) {
    /**
     * Tiesiskā statusa kopsavilkums no $page_data['results'] masīva.
     *
     * @return array{
     *   has: bool, level: string, head: string,
     *   chips: array<int, array{0:string,1:string}>,
     *   rows: array, old_procs: int, n: int,
     *   active_mn: int, active_tap: int, past_mn: int, past_tap: int,
     *   vid_akt: int, pt_melns_n: int, pt_sods_n: int,
     *   proc: array, liq: array, susp: array, sec: array,
     *   sank: array, vid: array, ptac: array
     * }
     */
    function reg_tiesiskais_kopsavilkums(array $res): array {
        $proc = is_array($res['insolvency_legal_person_proceeding'] ?? null) ? $res['insolvency_legal_person_proceeding'] : [];
        $liq  = is_array($res['liquidations'] ?? null) ? $res['liquidations'] : [];
        $susp = is_array($res['suspensions_prohibitions'] ?? null) ? $res['suspensions_prohibitions'] : [];
        $sec  = is_array($res['securing_measures'] ?? null) ? $res['securing_measures'] : [];
        // Sankciju riski (UR/CC0). Rindas jau izgājušas scrub_sanctions() — personu
        // vārdu, dzimšanas datumu un personas kodu tur nav, tikai uzņēmuma fakts.
        $sank = is_array($res['sanctions'] ?? null) ? $res['sanctions'] : [];
        // VID saimnieciskās darbības apturēšanas vēsture (periodi + atjaunošana).
        $vid  = is_array($res['pdb_saimndarbibaaptureta_odata'] ?? null) ? $res['pdb_saimndarbibaaptureta_odata'] : [];
        // PTAC uzraudzība: melnais saraksts, lēmumi ar soda naudu, apņemšanās.
        $ptac = [];
        foreach (is_array($res['ptac'] ?? null) ? $res['ptac'] : [] as $p) {
            if (in_array(trim((string)($p['veids'] ?? '')), ['melnais', 'lemums', 'apnemsanas'], true)) $ptac[] = $p;
        }

        $out = [
            'has' => (bool)($proc || $liq || $susp || $sec || $sank || $vid || $ptac),
            'proc' => $proc, 'liq' => $liq, 'susp' => $susp, 'sec' => $sec,
            'sank' => $sank, 'vid' => $vid, 'ptac' => $ptac,
            'rows' => [], 'old_procs' => 0,
            'active_mn' => 0, 'active_tap' => 0, 'past_mn' => 0, 'past_tap' => 0,
            'vid_akt' => 0, 'pt_melns_n' => 0, 'pt_sods_n' => 0,
            'level' => 'past', 'head' => '', 'chips' => [], 'n' => 0,
        ];
        if (!$out['has']) return $out;

        // --- Procesu sagatavošana: jaunākie pirmie, aktīvie atsevišķi ------------
        // 10 gadu logs: aktīvos rāda VIENMĒR; pabeigtos — tikai pēdējo 10 gadu.
        $cutoff = date('Y-m-d', strtotime('-10 years'));
        foreach ($proc as $p) {
            [$label, $kind] = reg_ts_form($p['proceeding_form'] ?? null);
            $start = reg_ts_date($p['proceeding_started_on'] ?? '');
            $end   = reg_ts_date($p['proceeding_ended_on'] ?? '');
            $active = ($start !== '' && $end === '');
            if (!$active && $end !== '' && $end < $cutoff) { $out['old_procs']++; continue; }
            if ($active) { $kind === 'tap' ? $out['active_tap']++ : $out['active_mn']++; }
            else         { $kind === 'tap' ? $out['past_tap']++   : $out['past_mn']++; }
            $out['rows'][] = [
                'label' => $label, 'kind' => $kind, 'active' => $active,
                'start' => $start, 'end' => $end,
                'court' => trim((string)($p['court_name'] ?? '')),
                'case'  => trim((string)($p['court_case_initial_number'] ?? '')),
                'res'   => trim((string)($p['proceeding_resolution_name'] ?? '')),
            ];
        }
        usort($out['rows'], static function ($a, $b) {
            if ($a['active'] !== $b['active']) return $a['active'] ? -1 : 1;   // aktīvie augšā
            return strcmp($b['start'], $a['start']);                            // tad jaunākie
        });

        // --- Kopsavilkuma karogs --------------------------------------------------
        $active_liq = count($liq);
        foreach ($ptac as $p) {
            $pv = trim((string)($p['veids'] ?? ''));
            if ($pv === 'melnais') $out['pt_melns_n']++;
            if ($pv === 'lemums' && (float)($p['soda_nauda'] ?? 0) > 0) $out['pt_sods_n']++;
        }
        // VID apturējums ir AKTĪVS, ja avotā nav ne beigu, ne atjaunošanas datuma.
        foreach ($vid as $v) {
            $lidz = trim((string)($v['Aizliegts_veikt_darijumus_lidz'] ?? ''));
            $atj  = trim((string)($v['Lemuma_par_atjaunosanu_datums'] ?? ''));
            if ($lidz === '' && $atj === '') $out['vid_akt']++;
        }
        if ($sank) {
            // Sankcijas ir smagākais signāls: darījumu aizliegums, ne tikai risks.
            $out['level'] = 'risk';
            $out['head']  = 'Sankciju risks';
        } elseif ($out['active_mn'] > 0 || $active_liq > 0) {
            $out['level'] = 'risk';
            $out['head']  = $out['active_mn'] > 0 ? 'Aktīvs maksātnespējas process' : 'Uzsākta likvidācija';
        } elseif ($out['active_tap'] > 0) {
            $out['level'] = 'warn';
            $out['head']  = 'Aktīvs tiesiskās aizsardzības process';
        } elseif ($out['pt_melns_n'] > 0) {
            $out['level'] = 'risk';
            $out['head']  = 'PTAC melnajā sarakstā';
        } elseif ($out['vid_akt'] > 0) {
            $out['level'] = 'risk';
            $out['head']  = 'Apturēta saimnieciskā darbība (VID)';
        } elseif ($susp || $sec) {
            $out['level'] = 'warn';
            $out['head']  = 'Reģistrēti darbības ierobežojumi';
        } elseif ($out['pt_sods_n'] > 0) {
            $out['level'] = 'warn';
            $out['head']  = 'PTAC lēmums ar soda naudu';
        } else {
            $out['level'] = 'past';
            $out['head']  = 'Vēsturiski procesi — šobrīd aktīvu nav';
        }

        // --- Čipi -----------------------------------------------------------------
        $chips = [];
        if ($sank)              $chips[] = ['risk', count($sank) . (count($sank) === 1 ? ' sankciju ieraksts' : ' sankciju ieraksti')];
        if ($out['active_mn'])  $chips[] = ['risk', $out['active_mn'] . pd_dsk($out['active_mn'], ' aktīvs maksātnespējas process', ' aktīvi maksātnespējas procesi')];
        if ($out['active_tap']) $chips[] = ['warn', $out['active_tap'] . pd_dsk($out['active_tap'], ' aktīvs TAP', ' aktīvi TAP')];
        if ($out['past_mn'])    $chips[] = ['past', $out['past_mn'] . pd_dsk($out['past_mn'], ' pabeigts maksātnespējas process', ' pabeigti maksātnespējas procesi')];
        if ($out['past_tap'])   $chips[] = ['past', $out['past_tap'] . pd_dsk($out['past_tap'], ' pabeigts TAP', ' pabeigti TAP')];
        if ($active_liq)        $chips[] = ['risk', 'likvidācija'];
        if ($susp)              $chips[] = ['warn', count($susp) . pd_dsk(count($susp), ' darbības liegums/apturēšana', ' darbības liegumi/apturēšanas')];
        if ($sec)               $chips[] = ['warn', count($sec) . pd_dsk(count($sec), ' nodrošinājuma līdzeklis', ' nodrošinājuma līdzekļi')];
        if ($ptac) {
            $pt_melns = 0; $pt_lem = 0;
            foreach ($ptac as $p) {
                $pv = trim((string)($p['veids'] ?? ''));
                if ($pv === 'melnais') $pt_melns++;
                if ($pv === 'lemums') $pt_lem++;
            }
            if ($pt_melns) $chips[] = ['risk', 'PTAC melnajā sarakstā'];
            if ($pt_lem)   $chips[] = ['warn', $pt_lem . ($pt_lem === 1 ? ' PTAC lēmums' : ' PTAC lēmumi')];
        }
        // "vecāki par 10 gadiem", ne "pirms {gads}. g.": robeža ir šodiena mīnus
        // 10 gadi (audits 2026-08-20).
        if ($out['old_procs']) $chips[] = ['past', $out['old_procs'] . ($out['old_procs'] === 1 ? ' senāks process (vecāks par 10 gadiem)' : ' senāki procesi (vecāki par 10 gadiem)')];
        $out['chips'] = $chips;

        $out['n'] = count($proc) + count($liq) + count($susp) + count($sec)
                  + count($sank) + count($vid) + count($ptac);
        return $out;
    }
}
