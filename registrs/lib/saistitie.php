<?php
/**
 * registrs/lib/saistitie.php — "Tuvākie uzņēmumi" bloka dati (iekšējā sasaiste).
 *
 * KĀPĒC: 2026-09-08 Search Console rādīja 149 638 lapas statusā "Atrasta — pašlaik
 * nav pievienota rādītājam": Google tās zina no vietnes kartes, bet nav pat
 * pārmeklējis. Mērījums kodā parādīja iemeslu — uz uzņēmumu lapām gandrīz neved
 * iekšējas saites, un pamatlapām (bez finanšu datiem) izejošo saišu ir NULLE.
 * XML karte lapu piesaka, bet nedod ne ceļu, ne enkurtekstu, ne kontekstu.
 *
 * KO DARA: no nozares kataloga izvēlas TĀS PAŠAS nozares un TĀS PAŠAS teritorijas
 * uzņēmumus pēc apgrozījuma. Ja NACE kods nav zināms ('UNDEFINED' — 87 748 no
 * 220 038 ieraksta), atkāpjas uz teritoriju vien, lai arī pamatlapa dabū saites.
 * Papildus atdod saites uz /nozare/{kods} un /top/{teritorija}.
 *
 * ĀTRUMS: balstās uz indeksiem idx_comp_nace_loc un idx_comp_loc_turn, ko veido
 * registrs/build/section_nozare.php. Bez tiem šie vaicājumi ir 60–105 ms uz KATRU
 * uzņēmuma lapu (mērīts uz 220 038 rindām), ar tiem — 0,1 ms.
 *
 * DROŠĪBA: nekad nemet izņēmumu uz augšu. Ja kataloga nav (piemēram, lejupielādes
 * pakotnē vai pirms pirmās būves), atgriež null un panelis vienkārši nerādās.
 */
declare(strict_types=1);

/** Kataloga savienojums (viens uz pieprasījumu). null — ja faila nav vai tas nav lasāms. */
function reg_saistitie_db(): ?PDO
{
    static $pdo = null, $tried = false;
    if ($tried) return $pdo;
    $tried = true;

    $kandidati = [
        dirname(__DIR__, 2) . '/nozare/katalogs.sqlite',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/nozare/katalogs.sqlite',
    ];
    foreach ($kandidati as $f) {
        if ($f === '/nozare/katalogs.sqlite' || !is_file($f)) continue;
        try {
            $pdo = new PDO('sqlite:' . $f);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (Throwable $e) {
            $pdo = null;
        }
    }
    return $pdo;
}

/**
 * NACE kods ar punktu. Katalogā ir DIVI formāti, un tos sajaukt ir viegli:
 * `companies.nace_code_np` glabā '8110' (bez punkta), bet `nace.code` un lapas URL
 * /nozare/{kods} — '81.10' (ar punktu). Pirmajā versijā nosaukumu meklēja pēc
 * nepunktētā koda, un tāpēc virsraksts sanāca bez nozares nosaukuma.
 * Sekcija (viens burts) šeit netiek lietota — uz to ved tikai /nozare.php.
 */
function reg_saistitie_nace_punkti(string $kods): ?string
{
    $kods = trim($kods);
    if ($kods === '' || $kods === 'UNDEFINED' || !ctype_digit($kods)) return null;
    $len = strlen($kods);
    if ($len < 2 || $len > 4) return null;
    return $len === 2 ? $kods : substr($kods, 0, 2) . '.' . substr($kods, 2);
}

/**
 * Teritorijas nosaukums, kāds tas ir katalogā, → [rādāmais nosaukums, /top/ URL,
 * visi katalogā sastopamie nosaukumi, kas pieder tai pašai teritorijai].
 *
 * Trešais elements ir vajadzīgs tāpēc, ka UR adresēs joprojām sastopami pirms-2021
 * novadi (TP_VECIE_NOVADI): uzņēmums ar 'Viļānu nov.' un uzņēmums ar 'Rēzeknes nov.'
 * šodien ir vienā teritorijā, un kaimiņu sarakstā tiem jābūt kopā.
 */
function reg_saistitie_teritorija(string $location): array
{
    $location = trim($location);
    if ($location === '') return ['', null, []];

    $lib = dirname(__DIR__, 2) . '/lib/top_teritorijas.php';
    if (!defined('TP_TERITORIJAS') && is_file($lib)) require_once $lib;
    if (!defined('TP_TERITORIJAS')) return ['', null, [$location]];

    $pasreizeja = $location;
    if (!isset(TP_TERITORIJAS[$pasreizeja]) && defined('TP_VECIE_NOVADI')) {
        $pasreizeja = TP_VECIE_NOVADI[$location] ?? $location;
    }
    if (!isset(TP_TERITORIJAS[$pasreizeja])) return ['', null, [$location]];

    $nosaukums = TP_TERITORIJAS[$pasreizeja][0];
    $url       = '/top/' . TP_TERITORIJAS[$pasreizeja][2];

    $visi = [$pasreizeja];
    if (defined('TP_VECIE_NOVADI')) {
        foreach (TP_VECIE_NOVADI as $vecais => $jaunais) {
            if ($jaunais === $pasreizeja) $visi[] = $vecais;
        }
    }
    return [$nosaukums, $url, array_values(array_unique($visi))];
}

/**
 * Saistīto uzņēmumu kopa vienai uzņēmuma lapai.
 *
 * @return array|null null — ja kataloga nav vai uzņēmums tajā nav atrodams.
 *   ['nace' => ?['kods','nosaukums','url'], 'teritorija' => ?['nosaukums','url'],
 *    'tuvakie' => [['regcode','name','turnover'], ...], 'tuvaku_veids' => 'nozare-teritorija'|'teritorija',
 *    'valsti' => [['regcode','name','turnover'], ...]]
 */
function reg_saistitie_uznemumi(string $regcode, int $limit_tuvakie = 8, int $limit_valsti = 4): ?array
{
    if (!preg_match('/^\d{11}$/', $regcode)) return null;
    $pdo = reg_saistitie_db();
    if (!$pdo) return null;

    try {
        $st = $pdo->prepare('SELECT nace_code_np, location FROM companies WHERE regcode = ?');
        $st->execute([$regcode]);
        $pats = $st->fetch();
        if (!$pats) return null;

        $nace_kods   = trim((string)($pats['nace_code_np'] ?? ''));
        $nace_punkti = reg_saistitie_nace_punkti($nace_kods);
        [$ter_nos, $ter_url, $ter_visi] = reg_saistitie_teritorija((string)($pats['location'] ?? ''));

        $nace = null;
        if ($nace_punkti !== null) {
            $ns = $pdo->prepare('SELECT name FROM nace WHERE code = ?');
            $ns->execute([$nace_punkti]);   // nace.code ir ar punktu, companies.nace_code_np — bez
            $nace = ['kods' => $nace_kods, 'nosaukums' => (string)($ns->fetchColumn() ?: ''),
                     'url' => '/nozare/' . $nace_punkti];
        }

        $tuvakie = [];
        $veids   = '';
        // Apgrozījums var būt NULL (nav iesniegts pārskats) — tādi uzņēmumi saraksta
        // beigās neko nepasaka, tāpēc ņemam tikai tos, kam skaitlis ir.
        if ($nace !== null && $ter_visi) {
            $ph  = implode(',', array_fill(0, count($ter_visi), '?'));
            $sql = "SELECT regcode, name, turnover FROM companies
                    WHERE nace_code_np = ? AND location IN ($ph) AND regcode <> ? AND turnover IS NOT NULL
                    ORDER BY turnover DESC LIMIT " . max(1, $limit_tuvakie);
            $st = $pdo->prepare($sql);
            $st->execute(array_merge([$nace_kods], $ter_visi, [$regcode]));
            $tuvakie = $st->fetchAll();
            $veids   = 'nozare-teritorija';
        }
        if (!$tuvakie && $ter_visi) {
            // Pamatlapas (NACE nav zināms) un retas nozares mazā novadā: teritorija vien.
            $ph  = implode(',', array_fill(0, count($ter_visi), '?'));
            $sql = "SELECT regcode, name, turnover FROM companies
                    WHERE location IN ($ph) AND regcode <> ? AND turnover IS NOT NULL
                    ORDER BY turnover DESC LIMIT " . max(1, $limit_tuvakie);
            $st = $pdo->prepare($sql);
            $st->execute(array_merge($ter_visi, [$regcode]));
            $tuvakie = $st->fetchAll();
            $veids   = 'teritorija';
        }

        $valsti = [];
        if ($nace !== null && $limit_valsti > 0) {
            $jau = array_column($tuvakie, 'regcode');
            $jau[] = $regcode;
            $ph  = implode(',', array_fill(0, count($jau), '?'));
            $sql = "SELECT regcode, name, turnover FROM companies
                    WHERE nace_code_np = ? AND regcode NOT IN ($ph) AND turnover IS NOT NULL
                    ORDER BY turnover DESC LIMIT " . max(1, $limit_valsti);
            $st = $pdo->prepare($sql);
            $st->execute(array_merge([$nace_kods], $jau));
            $valsti = $st->fetchAll();
        }

        // Kaimiņi BEZ publicēta pārskata. KĀPĒC: katalogā 90 620 no 220 038 uzņēmumu
        // apgrozījuma nav, un VISI vietnes saraksti (TOP, nozare, arī augšējie divi
        // vaicājumi) kārto pēc apgrozījuma — tātad tieši šīs lapas nesaņem nevienu
        // ienākošo iekšējo saiti. Google tās 2026-09-08 turēja statusā "Atrasta —
        // pašlaik nav pievienota rādītājam" 149 638 gab. Nobīde pēc crc32(regcode)
        // izkliedē saites pa visu kopu: citādi visas lapas saistītu uz tiem pašiem
        // trim vārdiem alfabēta sākumā.
        $bez_parskata = [];
        if ($ter_visi) {
            $nosac = $nace !== null ? 'nace_code_np = ? AND ' : '';
            $ph    = implode(',', array_fill(0, count($ter_visi), '?'));
            $args  = $nace !== null ? [$nace_kods] : [];
            $args  = array_merge($args, $ter_visi, [$regcode]);
            $sql   = "SELECT regcode, name FROM companies
                      WHERE {$nosac}location IN ($ph) AND regcode <> ? AND turnover IS NULL
                      ORDER BY name_sort LIMIT 3 OFFSET " . (int)(crc32($regcode) % 20);
            $st = $pdo->prepare($sql);
            $st->execute($args);
            $bez_parskata = $st->fetchAll();
            if (!$bez_parskata) {   // nobīde pārsniedza kopas izmēru — bez nobīdes
                $st = $pdo->prepare(str_replace(' OFFSET ' . (int)(crc32($regcode) % 20), '', $sql));
                $st->execute($args);
                $bez_parskata = $st->fetchAll();
            }
        }

        if (!$tuvakie && !$valsti && !$bez_parskata && $nace === null && $ter_url === null) return null;

        return [
            'bez_parskata' => $bez_parskata,
            'nace'         => $nace,
            'teritorija'   => $ter_url !== null ? ['nosaukums' => $ter_nos, 'url' => $ter_url] : null,
            'tuvakie'      => $tuvakie,
            'tuvaku_veids' => $veids,
            'valsti'       => $valsti,
        ];
    } catch (Throwable $e) {
        return null;   // panelis nekad nedrīkst lauzt uzņēmuma lapu
    }
}
