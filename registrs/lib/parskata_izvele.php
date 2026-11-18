<?php
/**
 * parskata_izvele.php — gada pārskata izvēles likums VIENĀ vietā visai vietnei.
 *
 * KĀPĒC (2026-10-22): koncerniem vienam gadam ir divi pārskati — paša (UGP) un konsolidētais (UKGP) —,
 * un daudziem uzņēmumiem viena gada pārskats iesniegts vairākkārt (labojumi). Katra vieta izvēlējās pa
 * savam (rowid kārta, pirmā UGP rinda, UKGP izslēgšana tikai pēc source_type), tāpēc viena uzņēmuma
 * skaitļi profilā, nozaru statistikā, Nozarē, Struktūrā un Pensionārā atšķīrās: 40103815302 konkurentu
 * panelī 11,2 M€ (koncerns) pret profila 8,1 M€; 40003042400 profila "Individuālais" 2016. g. rādīja
 * koncerna 1 794 429 € paša 1 420 304 € vietā; Nozare 40003461829 ņēma 3 mēnešu pārejas periodu (1,1 M€
 * pret pilnā gada 7,8 M€).
 *
 * Likums:
 *   1) konsolidēts = source_type 'UKGP' (atstarpes un burtu lielums neskaitās) VAI source_schema sākas
 *      ar 'DokUKGP' — līdz 2015. g. source_type ir tukšs, pazīme ir tikai shēmā (DokUKGP2008v1);
 *   2) uzņēmumam ar kaut vienu paša pārskatu (jebkurā gadā) konsolidētos neņem vispār — tos ņem tikai,
 *      ja paša pārskata nav nevienam gadam (citādi nozaru summās mātes un meitas skaitītos divreiz);
 *   3) viena gada un veida pārskatu vidū uzvar GARĀKAIS pārskata periods (pilni mēneši no year_started_on
 *      līdz year_ended_on), tad jaunākais iesniegums: lielākais created_at (NULL un '' = vecākais; ISO
 *      teksts, strcmp kārto pareizi), tad lielākais id kā SKAITLIS (kolonna ir TEXT — teksta kārtā '9'
 *      pārspētu '10'). Rindas bez skaitliska gada neskaitās.
 *      Periods pirms datuma tāpēc, ka tikai ~30 % viena gada dublikātu ir īsti labojumi (tas pats periods);
 *      pārējie ir finanšu gada maiņa — pilnais gads un īss pārejas periods ar to pašu gada etiķeti, un
 *      pārejas periodu bieži iesniedz vēlāk (44103123225 2024. g.: 12 mēn. 5,87 M€ un vēlāk iesniegti
 *      3 mēn. 0,99 M€; 105 grupās "jaunākais iesniegums" bija 1 mēneša periods). Arī ar VIENĀDU sākumu
 *      (192 grupas) tie nav labojumi, bet pārskats līdz likvidācijas/reorganizācijas datumam blakus gada
 *      pārskatam, iesniegti abās secībās — gada skaitlim ņem garāko neatkarīgi no secības. Garākais ir arī
 *      pilns sakārtojums (pāru likums "pārklājas → jaunākais, citādi garākais" var veidot ciklu).
 *
 * Profils (financial_engine.php) rāda abus veidus atsevišķos kausos, tāpēc lieto 1) un 3); kopsavilkuma
 * veida izvēle (summary_panel.php: UGP, ja tāds ir kopsavilkuma 10 gados) tur ir 2). Būves sadaļas un
 * īpašnieka lapa lieto reg_fs_izvele_plusma(), kas dara visus trīs.
 *
 * Testi: _Optimizacija/test_tools/registrs/parskata_izvele_testi.php (un nace_parskata_izvele_testi.php).
 */
declare(strict_types=1);

if (!function_exists('reg_fs_konsolidets')) {
    /** 1) Vai pārskats ir konsolidētais (koncerna). */
    function reg_fs_konsolidets($source_type, $source_schema): bool {
        return strtoupper(trim((string)$source_type)) === 'UKGP'
            || str_starts_with(strtoupper(trim((string)$source_schema)), 'DOKUKGP');
    }

    /**
     * Pārskata perioda garums pilnos mēnešos (dienas / 30,4375, noapaļots: 365 un 366 dienas = 12).
     * Nederīgs vai trūkstošs datums → 0 (zaudē jebkuram periodam ar datumiem).
     */
    function reg_fs_perioda_menesi($sakums, $beigas): int {
        $t = [];
        foreach ([$sakums, $beigas] as $v) {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim((string)$v), $m)
                || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return 0;
            $t[] = gmmktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        }
        if ($t[1] < $t[0]) return 0;
        return (int)round((($t[1] - $t[0]) / 86400 + 1) / 30.4375);
    }

    /**
     * 3) Kārta viena gada un veida ietvaros: < 0, ja $a ir SLIKTĀKS par $b — īsāks periods, vienāda garuma
     * periodiem agrāk iesniegts (labākais ir kārtas beigās — der usort, kur pēdējā rinda pārraksta
     * iepriekšējās). $k — kolonnu atslēgas (der arī PDO::FETCH_NUM indeksi).
     */
    function reg_fs_iesniegumu_cmp(array $a, array $b, array $k = []): int {
        $k += ['year_started_on' => 'year_started_on', 'year_ended_on' => 'year_ended_on',
               'created_at' => 'created_at', 'id' => 'id'];
        $p = reg_fs_perioda_menesi($a[$k['year_started_on']] ?? null, $a[$k['year_ended_on']] ?? null)
            <=> reg_fs_perioda_menesi($b[$k['year_started_on']] ?? null, $b[$k['year_ended_on']] ?? null);
        if ($p !== 0) return $p;
        $c = strcmp((string)($a[$k['created_at']] ?? ''), (string)($b[$k['created_at']] ?? ''));
        if ($c !== 0) return $c;
        return (int)($a[$k['id']] ?? 0) <=> (int)($b[$k['id']] ?? 0);
    }

    /**
     * 2)+3) Viena uzņēmuma pārskatu rindas → [gads => izvēlētā rinda], gadi augoši (jaunākais pēdējais).
     * $k — kolonnu atslēgas; der arī PDO::FETCH_NUM indeksi.
     */
    function reg_fs_izvele_pa_gadiem(array $rindas, array $k = []): array {
        $k += ['year' => 'year', 'source_type' => 'source_type', 'source_schema' => 'source_schema',
               'year_started_on' => 'year_started_on', 'year_ended_on' => 'year_ended_on',
               'created_at' => 'created_at', 'id' => 'id'];
        $pa_veidiem = [[], []]; // 0 = paša, 1 = konsolidētais; katrā gads => rinda
        foreach ($rindas as $r) {
            $y = $r[$k['year']] ?? null;
            if ($y === null || !is_numeric((string)$y)) continue;
            $y = (int)(float)$y;
            $v = reg_fs_konsolidets($r[$k['source_type']] ?? null, $r[$k['source_schema']] ?? null) ? 1 : 0;
            $b = $pa_veidiem[$v][$y] ?? null;
            if ($b === null || reg_fs_iesniegumu_cmp($r, $b, $k) > 0) $pa_veidiem[$v][$y] = $r;
        }
        $izv = $pa_veidiem[0] ?: $pa_veidiem[1];
        ksort($izv, SORT_NUMERIC);
        return $izv;
    }

    /**
     * Plūsma: rindas, kas SQL kārtotas pēc uzņēmuma (ORDER BY legal_entity_registration_number — BINARY
     * kārta = strcmp) → reg => [gads => rinda]. Ja kārta nav ievērota, viens uzņēmums parādītos divreiz
     * ar daļējām rindām — tāpēc izņēmums, ne klusa kļūda.
     */
    function reg_fs_izvele_plusma(iterable $rindas, $k_reg = 'legal_entity_registration_number', array $k = []): Generator {
        $cur = null; $buf = [];
        foreach ($rindas as $r) {
            $reg = (string)$r[$k_reg];
            if ($reg !== $cur) {
                if ($cur !== null) {
                    if (strcmp($reg, $cur) < 0) throw new LogicException("pārskatu rindas nav kārtotas pēc uzņēmuma ($cur → $reg)");
                    yield $cur => reg_fs_izvele_pa_gadiem($buf, $k);
                }
                $cur = $reg; $buf = [];
            }
            $buf[] = $r;
        }
        if ($cur !== null) yield $cur => reg_fs_izvele_pa_gadiem($buf, $k);
    }
}
