<?php
// registrs/lib/altman.php — Altmana Z' (1983) un Z'' (1995) VIENUVIET: uzņēmuma lapa
// (financial_engine.php → tabula, grafiks, BUJ), Nozare (build/section_nozare.php) un Pensionārs
// (build/section_pensionars.php). Agrāk katrs rēķināja pats, un lapa lietoja ORIĢINĀLĀ Z (1968, biržas
// ražotāji) robežas 1,8 / 3,0 kopā ar Z' formulu: 4 490 uzņēmumi bija "bīstamajā zonā", lai gan pēc
// Z' tie ir nenoteiktības zonā (mērīts 2026-11-19).
//
// Modeļi:  Z'  = 0,717·X1 + 0,847·X2 + 3,107·X3 + 0,420·X4 + 0,998·X5 (Altman 1983, privāti ražotāji)
//          Z'' = 6,56·X1 + 3,26·X2 + 6,72·X3 + 1,05·X4             (Altman 1995, neražotāji; bez X5)
//   X1 apgrozāmais kapitāls / aktīvi   X2 nesadalītā peļņa / aktīvi   X3 EBIT / aktīvi
//   X4 pašu kapitāls / saistības       X5 apgrozījums / aktīvi
// Robežas Z': < 1,23 / 1,23–2,90 / > 2,90; Z'': < 1,10 / 1,10–2,60 / > 2,60 (līdzīgs bankrotējušo grupai /
// nenoteiktības zona / stabilo grupai). Modelis pēc NACE — reg_altman_modelis(). Tas ir diskriminanta
// skaitlis, NE bankrota varbūtība.
declare(strict_types=1);

const ALTMAN_ZP_SARKANA  = 1.23;
const ALTMAN_ZP_ZALA     = 2.90;
const ALTMAN_ZPP_SARKANA = 1.10;  // Z'' (1995, neražotājiem): bez X5 (apgrozījums/aktīvi — nozares atkarīgs)
const ALTMAN_ZPP_ZALA    = 2.60;
// Modeļi lapai (pārslēdzis grafikā) un būves sadaļām. Noklusējums pēc NACE — reg_altman_modelis().
const ALTMAN_MODELI = [
    'zp'  => ['nosaukums' => "Z' (1983)", 'kam' => 'privātiem ražošanas uzņēmumiem', 'sarkana' => ALTMAN_ZP_SARKANA, 'zala' => ALTMAN_ZP_ZALA,
              'formula' => "Z' (1983) = 0.717*A + 0.847*B + 3.107*C + 0.420*D + 0.998*E"],
    'zpp' => ['nosaukums' => "Z'' (1995)", 'kam' => 'neražotājiem (tirdzniecība, pakalpojumi u. c.)', 'sarkana' => ALTMAN_ZPP_SARKANA, 'zala' => ALTMAN_ZPP_ZALA,
              'formula' => "Z'' (1995) = 6.56*A + 3.26*B + 6.72*C + 1.05*D"],
];
const ALTMAN_LVL_EUR    = 0.702804; // 1 EUR = 0,702804 LVL

if (!function_exists('reg_altman_zp')) {
    /**
     * @param array $v  ta, tca, cl, ncl, equity, ibt, ie, turnover — VIENĀ mērvienībā un valūtā;
     *                  pamatkapitals — tajā pašā mērvienībā vai null (sk. reg_altman_pamatkapitals()).
     * @return array{z: float, A: float, B: float, C: float, D: float, E: float, b_no_nesadalitas: bool}|null
     *   null — ja aktīvi ≤ 0, trūkst ievaddatu vai saistību nav: X4 = pašu kapitāls / saistības tad nav
     *   definēts (agrāk lapa lika X4 = 0 un sodīja 2 368 uzņēmumus bez parādiem; Nozare/Pensionārs jau
     *   tad deva null — tagad visur vienādi).
     */
    function reg_altman_zp(array $v): ?array {
        $k = reg_altman_komponentes($v);
        if ($k === null) return null;
        return ['z' => 0.717 * $k['A'] + 0.847 * $k['B'] + 3.107 * $k['C'] + 0.420 * $k['D'] + 0.998 * $k['E']] + $k;
    }

    /** Z'' (1995) — tie paši X1–X4, bez X5. Altmans to iesaka neražotājiem; robežas 1,10 / 2,60. */
    function reg_altman_zpp(array $v): ?array {
        $k = reg_altman_komponentes($v);
        if ($k === null) return null;
        return ['z' => 6.56 * $k['A'] + 3.26 * $k['B'] + 6.72 * $k['C'] + 1.05 * $k['D']] + $k;
    }

    /**
     * Noklusējuma modelis pēc NACE (lietotājs grafikā var pārslēgt):
     *   C ražošana (10–33) → Z' (1983) — modelis veidots ražotājiem;
     *   finanses (64–66: bankas, apdrošināšana, holdingi; NACE 2.1 sadaļa L, 2.0 — K) → null — modeļi nav piemēroti;
     *   pārējie un nezināms NACE → Z'' (1995) — 92 % zināmo nav ražotāji (mērīts 2026-11-19).
     */
    function reg_altman_modelis(?string $nace): ?string {
        $d = substr((string)preg_replace('/\D/', '', (string)$nace), 0, 2);
        if ($d === '' || $d === '00') return 'zpp';
        $n = (int)$d;
        if ($n >= 10 && $n <= 33) return 'zp';
        if ($n >= 64 && $n <= 66) return null;
        return 'zpp';
    }

    /** Komponentes X1–X5 (sk. faila galveni); null — ja aktīvi ≤ 0, trūkst datu vai saistību nav. */
    function reg_altman_komponentes(array $v): ?array {
        foreach (['ta', 'tca', 'cl', 'equity', 'ibt', 'turnover'] as $k) {
            if (!is_numeric($v[$k] ?? null)) return null;
        }
        $ta = (float)$v['ta'];
        $saistibas = (float)$v['cl'] + (float)($v['ncl'] ?? 0);
        if ($ta <= 0 || $saistibas <= 0) return null;
        $eq = (float)$v['equity'];
        // X2 modelī ir NESADALĪTĀ (uzkrātā) peļņa. Atvērtajos datos tās nav; tuvinājums — pašu kapitāls
        // mīnus apmaksātais pamatkapitāls (= nesadalītā peļņa + rezerves). Bez pamatkapitāla — pašu kapitāls
        // (kā agrāk), un to atzīmē b_no_nesadalitas = false.
        $pk = $v['pamatkapitals'] ?? null;
        $ar_pk = is_numeric($pk) && (float)$pk > 0;
        $A = ((float)$v['tca'] - (float)$v['cl']) / $ta;
        $B = ($ar_pk ? $eq - (float)$pk : $eq) / $ta;
        $C = ((float)$v['ibt'] + (float)($v['ie'] ?? 0)) / $ta;
        $D = $eq / $saistibas;
        $E = (float)$v['turnover'] / $ta;
        return ['A' => $A, 'B' => $B, 'C' => $C, 'D' => $D, 'E' => $E, 'b_no_nesadalitas' => $ar_pk];
    }

    /**
     * Apmaksātais pamatkapitāls pārskata mērvienībā, ja tas reģistrēts LĪDZ perioda beigām (UR datos ir tikai
     * pašreizējais kapitāls ar date_from — agrākiem gadiem, pirms tā reģistrācijas, to nezinām → null).
     * Valūta: EUR↔LVL pēc oficiālā kursa; citas — null.
     */
    function reg_altman_pk_vieniba($summa, ?string $valuta, ?string $no, string $perioda_beigas, string $parskata_valuta, float $mervieniba): ?float {
        if (!is_numeric($summa) || (float)$summa <= 0 || $mervieniba <= 0) return null;
        $no = substr(trim((string)$no), 0, 10);
        $beigas = substr(trim($perioda_beigas), 0, 10);
        if ($no === '' || $beigas === '' || $no > $beigas) return null;
        $v = strtoupper(trim((string)$valuta)) ?: 'EUR';
        $p = strtoupper(trim($parskata_valuta)) ?: 'EUR';
        $s = (float)$summa;
        if ($v !== $p) {
            if ($v === 'EUR' && $p === 'LVL') $s *= ALTMAN_LVL_EUR;
            elseif ($v === 'LVL' && $p === 'EUR') $s /= ALTMAN_LVL_EUR;
            else return null;
        }
        return $s / $mervieniba;
    }

    /** No UR equity_capitals rindām: jaunākais PAID_UP_EQUITY ieraksts līdz perioda beigām. */
    function reg_altman_pamatkapitals(array $equity_capitals, string $perioda_beigas, string $parskata_valuta, float $mervieniba): ?float {
        $lab = null;
        foreach ($equity_capitals as $r) {
            if (!is_array($r) || strtoupper(trim((string)($r['equity_capital_type'] ?? ''))) !== 'PAID_UP_EQUITY') continue;
            $no = substr(trim((string)($r['date_from'] ?? '')), 0, 10);
            if ($no === '' || $no > substr($perioda_beigas, 0, 10)) continue;
            if ($lab === null || $no > $lab['no']) $lab = ['no' => $no, 'r' => $r];
        }
        return $lab === null ? null : reg_altman_pk_vieniba($lab['r']['amount'] ?? null, (string)($lab['r']['currency'] ?? ''),
            $lab['no'], $perioda_beigas, $parskata_valuta, $mervieniba);
    }

    /** Vai NACE kods ir zināms (citādi noklusējums Z'' un teksti nedrīkst teikt «pēc nozares»). */
    function reg_altman_nace_zinama(?string $nace): bool {
        $d = substr((string)preg_replace('/\D/', '', (string)$nace), 0, 2);
        return strlen($d) === 2 && $d !== '00';
    }

    /**
     * Vai Altmana indekss vispār piemērots (pretenzija 2026-11-16): vajag vismaz 2 pārskata gadus
     * izvēlētajā sērijā (parskata_izvele.php 2. p.) un pozitīvu apgrozījumu jaunākajā gadā. Kopīgs
     * uzņēmuma lapai (page_builder reg_altman_piemerots) un būvēm (Nozare, Pensionārs).
     */
    function reg_altman_piemerots_vertibas(int $gadu_skaits, $apgrozijums_jaunakais): bool {
        return $gadu_skaits >= 2 && is_numeric($apgrozijums_jaunakais) && (float)$apgrozijums_jaunakais > 0;
    }

    /**
     * Skaitlis tekstam ar komatu: 2 zīmes aiz komata, bet pie zonas robežas tik daudz, lai noapaļotais
     * skaitlis paliek tajā pašā zonā (2,897 nevis «2,90: starp 1,23 un 2,90»).
     */
    function reg_altman_skaitlis(float $z, string $modelis = 'zp'): string {
        $zona = reg_altman_zona($z, $modelis);
        for ($d = 2; $d < 6; $d++) {
            if (reg_altman_zona(round($z, $d), $modelis) === $zona) break;
        }
        return number_format($z, $d, ',', ' ');
    }

    /** 'r' | 'o' | 'g' pēc modeļa robežām (sarkans / nenoteiktība / zaļš); noklusējums Z'. */
    function reg_altman_zona(float $z, string $modelis = 'zp'): string {
        $m = ALTMAN_MODELI[$modelis] ?? ALTMAN_MODELI['zp'];
        // Altman: zem apakšējās robežas — bankrotējušo grupa; robežas ieskaitot — nenoteiktība; VIRS augšējās — stabilo.
        return $z < $m['sarkana'] ? 'r' : ($z <= $m['zala'] ? 'o' : 'g');
    }
}
