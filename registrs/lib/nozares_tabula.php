<?php
/**
 * nozares_tabula.php — konkurentu salīdzinājuma tabulas rindas VIENĀ vietā.
 *
 * Izcelts no view/partials/test_panel.php (audits 2026-08-26), jo tās pašas
 * rindas tagad vajag DIVIEM patērētājiem: panelim (statiskais TOP 10 + iegultie
 * dati mazām nozarēm) un /nozare_dati.php galapunktam (slinkā ielāde lielām
 * nozarēm — NACE 6820 iegultais blobs bija 482 KB uz KATRAS lapas, 45 615
 * lapas ar ≥80 KB). Loģika pārcelta burtiski; divas kopijas te nozīmētu, ka
 * panelis un galapunkts agri vai vēlu rāda dažādus skaitļus.
 *
 * Rindas formāts (rows_js): [nosaukums, reg, apgroz, peļņa, UR darb, UR alga,
 * UR algas gads, VID darb, VID alga, VID cet, UR alga slēpta, VID alga slēpta].
 * Algas tikai >=3 darbiniekiem (privātums); 'slēpta' = dati ir, bet <3 darb.
 */
declare(strict_types=1);

require_once __DIR__ . '/risk_semaphore.php';   // tst_num, tst_quarter_key/short

if (!function_exists('reg_nozares_tabula')) {
    /** Formu saīsināšana tabulas nosaukumos — tikai attēlošanai. */
    function reg_nozares_isa_forma(string $n): string {
        static $repl = [
            '/sabiedrība ar ierobežotu atbildību/iu' => 'SIA',
            '/akciju sabiedrība/iu' => 'AS',
            '/individuālais komersants/iu' => 'IK',
            '/individuālais uzņēmums/iu' => 'IU',
            '/zemnieku saimniecība/iu' => 'ZS',
            '/zvejnieku saimniecība/iu' => 'ZvS',
            '/pilnsabiedrība/iu' => 'PS',
            '/komandītsabiedrība/iu' => 'KS',
            '/kooperatīvā sabiedrība/iu' => 'Koop. sab.',
            '/ārvalsts komersanta filiāle/iu' => 'filiāle',
        ];
        $out = preg_replace(array_keys($repl), array_values($repl), $n);
        return is_string($out) ? trim($out) : $n;
    }

    /** IN (...) pa gabaliem, lai nepārsniegtu SQLite mainīgo limitu. */
    function reg_nozares_chunk_query(PDO $conn, string $sql_tpl, array $codes, int $chunk = 400): array {
        $out = [];
        foreach (array_chunk(array_values($codes), $chunk) as $part) {
            $ph = implode(',', array_fill(0, count($part), '?'));
            try {
                $st = $conn->prepare(sprintf($sql_tpl, $ph));
                $st->execute($part);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[] = $r;
            } catch (Throwable $e) {}
        }
        return $out;
    }

    /**
     * Nozares salīdzinājuma dati NACE 4 ciparu kodam.
     *
     * @return array{comps: array, total: int, generated: string, rows_js: array}|null
     *         null, ja koda nav, nace_stats trūkst vai nozarē ir <2 uzņēmumi.
     */
    function reg_nozares_tabula(PDO $conn, string $nace4): ?array {
        $nace4 = preg_replace('/\D/', '', $nace4) ?? '';
        $ns_path = (function_exists('reg_search_dir') ? reg_search_dir() : (dirname(__DIR__) . '/assets/search')) . '/nace_stats.sqlite';
        if ($nace4 === '' || $nace4 === '0000' || !is_file($ns_path)) return null;

        $np = new PDO('sqlite:' . $ns_path);
        $np->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $q = $np->prepare('SELECT json_data FROM nace_stats WHERE code = ? LIMIT 1');
        $q->execute([$nace4]);
        $jd = $q->fetchColumn();
        if (!is_string($jd) || $jd === '') return null;
        $data = json_decode($jd, true);
        $comps = is_array($data['companies'] ?? null) ? $data['companies'] : [];
        // Ieraksts: [name, regcode, profit, turnover, employees]; kārtots pēc peļņas dilstoši
        $n = count($comps);
        if ($n <= 1) return null;

        $all_codes = [];
        foreach ($comps as $c) {
            $rc = (string)($c[1] ?? '');
            if ($rc !== '') $all_codes[] = $rc;
        }

        // VID jaunākais ceturksnis (darbinieki + VSAOI -> alga/mēn) — visai nozarei
        $vid_cet = []; // reg => ['key'=>, 'q'=>, 'emp'=>, 'alga'=>]
        foreach (reg_nozares_chunk_query($conn, "SELECT Registracijas_kods AS rc, Taksacijas_gads_ceturksnis AS q, Taja_skaita_VSAOI_summa AS vs, Videjais_nodarbinato_personu_skaits_cilv AS emp FROM pdb_samaksato_nodoklu_kopsummas_cet WHERE Registracijas_kods IN (%s)", $all_codes) as $r) {
            $rc = (string)$r['rc'];
            $k = tst_quarter_key((string)$r['q']);
            if ($k === null) continue;
            if (!isset($vid_cet[$rc]) || $k > $vid_cet[$rc]['key']) {
                $emp = tst_num($r['emp'] ?? null);
                $vs = tst_num($r['vs'] ?? null);
                // Alga tikai >=3 darbiniekiem (privātums — skat. page_builder VID bloku).
                // 'hid' = dati ir, bet slēpti (<3 darb.) — tabulā rāda '***', ne '—'.
                $alga = ($emp !== null && $emp >= 3 && $vs !== null && $vs > 0)
                    ? (int)round((($vs * 1000) / 0.3409) / $emp / 3) : null;
                $vid_cet[$rc] = ['key' => $k, 'q' => tst_quarter_short((string)$r['q']),
                    'emp' => $emp !== null ? (int)$emp : null, 'alga' => $alga,
                    'hid' => ($emp !== null && $emp < 3 && $vs !== null && $vs > 0)];
            }
        }

        // VID gada dati (pdb_nm; jaunākais gads) -> gada alga/mēn — UR režīma algas kolonnai
        $vid_year = []; // reg => ['year'=>, 'alga'=>]
        foreach (reg_nozares_chunk_query($conn, "SELECT Registracijas_kods AS rc, Taksacijas_gads AS y, Taja_skaita_VSAOI AS vs, Videjais_nodarbinato_personu_skaits_cilv AS emp FROM pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata WHERE Registracijas_kods IN (%s)", $all_codes) as $r) {
            $rc = (string)$r['rc'];
            $y = (int)($r['y'] ?? 0);
            if ($y <= 0) continue;
            if (!isset($vid_year[$rc]) || $y > $vid_year[$rc]['year']) {
                $emp = tst_num($r['emp'] ?? null);
                $vs = tst_num($r['vs'] ?? null);
                // Alga tikai >=3 darbiniekiem (privātums).
                $alga = ($emp !== null && $emp >= 3 && $vs !== null && $vs > 0)
                    ? (int)round((($vs * 1000) / 0.3409) / $emp / 12) : null;
                $vid_year[$rc] = ['year' => $y, 'alga' => $alga,
                    'hid' => ($emp !== null && $emp < 3 && $vs !== null && $vs > 0)];
            }
        }

        // Kompaktas rindas JS tabulai (formātu sk. faila galvenē).
        $rows_js = [];
        foreach ($comps as $c) {
            $rc = (string)($c[1] ?? '');
            $vc = $vid_cet[$rc] ?? null;
            $vy = $vid_year[$rc] ?? null;
            $rows_js[] = [
                reg_nozares_isa_forma((string)($c[0] ?? '')),
                $rc,
                (float)($c[3] ?? 0),
                (float)($c[2] ?? 0),
                (int)($c[4] ?? 0),
                $vy['alga'] ?? null,
                $vy['year'] ?? null,
                $vc['emp'] ?? null,
                $vc['alga'] ?? null,
                $vc['q'] ?? null,
                !empty($vy['hid']) ? 1 : 0,
                !empty($vc['hid']) ? 1 : 0,
            ];
        }

        return [
            'comps' => $comps,
            'total' => $n,
            'generated' => (string)($data['meta']['generated'] ?? ''),
            'rows_js' => $rows_js,
        ];
    }
}
