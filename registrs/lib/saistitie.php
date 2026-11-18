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
 * uzņēmumus. Lapai ar apgrozījumu — kaimiņus pēc apgrozījuma (reg_saistitie_kaimini);
 * izņēmums ir 4 084 lapas, kas savā nozarē un teritorijā ir vienīgās ar apgrozījumu — tās
 * rāda tās pašas nozares vieniniekus citās teritorijās (reg_saistitie_vieninieki), un tikai,
 * ja nozarē tādu nav, — lielākos. Lapai bez pārskata: grupas lielākos; ja
 * NACE kods nav zināms ('UNDEFINED' — 87 748 no 220 038 ieraksta) vai nozarē tur neviena nav,
 * atkāpjas uz teritorijas lielākajiem, lai arī pamatlapa dabū saites. Lapai AR apgrozījumu,
 * bet bez nozares (10 667) grupa ir 'UNDEFINED' × teritorija, nevis visa teritorija.
 * Papildus atdod saites uz /nozare/{kods} un /top/{teritorija}.
 *
 * ĀTRUMS: balstās uz indeksiem idx_comp_nace_loc un idx_comp_loc_turn, ko veido
 * registrs/build/section_nozare.php. Bez tiem šie vaicājumi ir 60–105 ms uz KATRU
 * uzņēmuma lapu (mērīts uz 220 038 rindām), ar tiem — 0,1 ms. "Nozares līderi Latvijā" —
 * uz daļēja idx_comp_nace_turn (WHERE jāsatur tā nosacījums). Grupa "bez pārskata" —
 * uz idx_comp_bez_parskata* (reg_saistitie_bez_parskata): 0,02 ms Rīgā, kur iepriekšējā
 * versija kārtoja ~28 000 rindu un tērēja ~9 ms uz lapu (mērīts 2026-10-07). Kaimiņi —
 * uz idx_comp_nace_loc, četri indeksa diapazona lasījumi katram teritorijas nosaukumam.
 *
 * DROŠĪBA: reg_saistitie_uznemumi() nekad nemet izņēmumu uz augšu (reg_saistitie_kaimini()
 * met ar nolūku — to noķer izsaucējs). Ja kataloga nav (piemēram, lejupielādes pakotnē vai
 * pirms pirmās būves), atgriež null un panelis vienkārši nerādās.
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
            // PDO noklusējums ir gaidīt 60 s, ja katalogs ir aizslēgts rakstīšanai (sections_cli.php
            // nozare raksta tieši dzīvajā failā; nakts būve — ar rename, tā neslēdz). Bloks nav tā
            // vērts, lai lapa karātos: pēc 1 s vaicājums krīt, un panelis šoreiz nerādās (audits 2026-10-13).
            $pdo->setAttribute(PDO::ATTR_TIMEOUT, 1);
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
 * Kaimiņi BEZ publicēta gada pārskata — grupa "Tuvumā, bez publicēta gada pārskata".
 *
 * KĀPĒC: katalogā 87 204 uzņēmumiem (11 cipari) apgrozījuma nav, un VISI vietnes saraksti
 * (TOP, nozare, arī reg_saistitie_uznemumi() augšējās grupas) kārto pēc apgrozījuma — tātad
 * tieši šīs lapas citādi nesaņem nevienu ienākošo iekšējo saiti. GSC tās tur statusā
 * "Atrasta — pašlaik nav pievienota rādītājam" (125 415 uz 2026-10-04).
 *
 * KĀ: katram uzņēmumam katalogā ir stabils hash `link_hash` (to aprēķina būve no regcode).
 * Grupas locekļi hash secībā veido riņķi; lapa ņem $limit locekļus, kas riņķī seko TĀS
 * PAŠAS hash vērtībai (pēc pēdējā — atkal no sākuma). Ja lapa pati ir grupas loceklis, tā
 * saista uz saviem nākamajiem kaimiņiem. Zināmas nozares grupā visi locekļi vaicā šo pašu
 * riņķi, tātad KATRS saņem saites no saviem $limit priekštečiem neatkarīgi no grupas izmēra.
 * Teritorijas riņķī (NACE nezināms) ir arī zināmas nozares uzņēmumi, kas paši vaicā savas
 * nozares riņķi — tāpēc tur saiti nesaņem tie, kam visi $limit priekšteči ir tādi (2026-10-07:
 * 245; vēl 1 nesaņem, jo ir viens savā nozares grupā un teritorijas riņķī nav trāpīts).
 * Teritorijas riņķī tos neizslēdzam apzināti: ~2 300 zināmas nozares uzņēmumu savā
 * grupā ir vieni un saites saņem tikai no šejienes (izslēdzot pārklājums kristos). Lapas ar
 * apgrozījumu krīt riņķī nejaušā punktā un pieliek vēl. Mērīts 2026-10-07 visās 216 622
 * lapās: saiti saņem 86 958 no 87 204; iepriekšējā versija (ORDER BY name_sort OFFSET
 * crc32 % 20) — 11 659, jo deva saites tikai pirmajiem ~23 nosaukumiem katrā grupā, un
 * Rīgā tā kārtoja ~28 000 rindu uz lapu.
 *
 * Grupa ir tā pati, kas citur šajā failā: zināms NACE → (nozare × teritorija), citādi
 * teritorija vien. Teritorijā var būt vairāki katalogā sastopami nosaukumi (vecie novadi),
 * tāpēc katram nosaukumam ņem $limit nākamos un tos apvieno — pa vienam, lai katrs
 * vaicājums ir indeksa diapazona lasījums (idx_comp_bez_parskata*, registrs/build/
 * section_nozare.php), nevis visas grupas kārtošana. INDEXED BY: ja indeksa vai kolonnas
 * nav (vecs katalogs pirms nakts būves), prepare krīt un grupa ir tukša — nevis lēna; tad
 * error_log vienreiz pieprasījumā, lai tukšums nav kluss. Tāpēc izvietot: VISPIRMS būve
 * (section_nozare.php) + kataloga pārbūve, TAD šis fails.
 *
 * length(regcode) = 11: katalogā ir arī 3 416 masu informācijas līdzekļu ar 9 ciparu
 * numuru (avīze "Ostas avīze" = 000703321) — bez lapas, /{9 cipari} ir 404.
 *
 * @param string|null $nace_kods nace_code_np bez punkta; null — grupa ir teritorija vien
 * @param string[]    $ter_visi  reg_saistitie_teritorija() trešais elements (netukšs)
 * @return array<int,array{regcode:string,name:string}> rādīšanai sakārtoti pēc nosaukuma
 */
function reg_saistitie_bez_parskata(PDO $pdo, string $regcode, ?string $nace_kods, array $ter_visi, int $limit = 3): array
{
    try {
        $st = $pdo->prepare('SELECT link_hash FROM companies WHERE regcode = ?');
        $st->execute([$regcode]);
        $punkts = $st->fetchColumn();
        if ($punkts === false || $punkts === null) return [];

        [$indekss, $nosac] = $nace_kods !== null
            ? ['idx_comp_bez_parskata', 'nace_code_np = ? AND ']
            : ['idx_comp_bez_parskata_loc', ''];
        $sql = "SELECT regcode, name, name_sort, link_hash FROM companies INDEXED BY $indekss
                WHERE {$nosac}location = ? AND turnover IS NULL AND length(regcode) = 11
                  AND regcode <> ? AND link_hash %s ?
                ORDER BY link_hash LIMIT " . max(1, $limit);

        $izvele = [];
        foreach (['>', '<='] as $op) {   // vispirms aiz punkta, tad riņķa sākums
            $st   = $pdo->prepare(sprintf($sql, $op));
            $dala = [];
            foreach ($ter_visi as $loc) {
                $i = 1;
                if ($nace_kods !== null) $st->bindValue($i++, $nace_kods);
                $st->bindValue($i++, (string)$loc);
                $st->bindValue($i++, $regcode);
                $st->bindValue($i++, (int)$punkts, PDO::PARAM_INT);
                $st->execute();
                array_push($dala, ...$st->fetchAll());
            }
            usort($dala, static fn($a, $b) => [$a['link_hash'], $a['regcode']] <=> [$b['link_hash'], $b['regcode']]);
            $izvele = array_slice(array_merge($izvele, $dala), 0, $limit);
            if (count($izvele) >= $limit) break;
        }

        usort($izvele, static fn($a, $b) => [(string)$a['name_sort'], $a['regcode']] <=> [(string)$b['name_sort'], $b['regcode']]);
        return array_map(static fn($r) => ['regcode' => (string)$r['regcode'], 'name' => (string)$r['name']], $izvele);
    } catch (Throwable $e) {
        static $zinots = false;
        if (!$zinots) {
            $zinots = true;
            error_log('saistitie: "bez pārskata" grupa tukša — ' . $e->getMessage());
        }
        return [];
    }
}

/**
 * Kaimiņi pēc apgrozījuma — grupa "Blakus pēc apgrozījuma" lapām, kurām apgrozījums ir.
 * Nosaukums ar nolūku nesola "līdzīgu lielumu": retā grupā blakus stāvošais var būt simtiem
 * reižu mazāks (8,7 % lapu nevienam kaimiņam apgrozījums nav trīskārša robežās; 2026-10-13).
 *
 * KĀPĒC: līdz 2026-10-13 visas grupas lapas rādīja TOS PAŠUS lielākos (ORDER BY turnover DESC
 * LIMIT 8), tāpēc saiti saņēma tikai grupas virsotne. Mērīts visās 216 622 lapās kopā ar
 * /nozare/ TOP-50, /top/ un īpašnieku saitēm: 73 405 no 129 418 lapām ar apgrozījumu nesaņēma
 * nevienu ienākošo iekšējo saiti, bet 1 % lapu saņēma 59 % visu saišu. Arī apmeklētājam
 * uzņēmums ar 200 tūkst. € blakus nozares milžiem neko nepasaka — blakus stāvošie ir salīdzināmāki.
 *
 * KĀ: grupa = (nace_code_np × teritorija). 'UNDEFINED' te ir grupa kā jebkura cita: 10 667 lapām
 * ir apgrozījums, bet nav nozares, un teritorijas kopsarakstā tās savā starpā gandrīz nesatiktos.
 * Grupas locekļi indeksa idx_comp_nace_loc secībā (turnover DESC, rowid ASC) ir saraksts; lapa
 * ņem $limit/2 tuvākos virs sevis un pārējos zem sevis (saraksta galā trūkstošos — no otras
 * puses). Katra lapa ir sava blakus esošā kaimiņa logā (ar $limit ≥ 2 tas ņem vismaz vienu no
 * katras puses, kur kāds ir), tātad KATRS grupas loceklis (grupā ≥ 2) saņem saiti neatkarīgi no
 * grupas lieluma.
 *
 * rowid izšķir vienādus apgrozījumus: 32 378 lapām apgrozījums ir tieši 0 (vienā grupā līdz
 * 1 108), un bez izšķīrēja tās visas rādītu vienus un tos pašus. rowid ir indeksa pēdējā
 * atslēga, tāpēc izšķiršana neko nemaksā; link_hash te prasītu kārtot visu vienādo kopu.
 * Cena: rowid ir UR datnes rindu secība, un starp būvēm tā nedaudz mainās (recenzijā mērīts
 * 08-30 → 10-07: relatīvo vietu mainīja 0,63 % uzņēmumu) — vienāda apgrozījuma kopās kaimiņi
 * lēnām pārbīdās.
 *
 * Savu apgrozījumu vaicājumi nolasa paši (apakšvaicājums pēc rowid), nevis saņem no PHP:
 * PDO peldošo skaitli sien kā tekstu ar 14 zīmīgiem cipariem, un "turnover = ?" tad nesakristu.
 *
 * Katram teritorijas nosaukumam (vecie novadi) četri lasījumi — vienāds apgrozījums un stingri
 * mazāks/lielāks, katrā pusē — un apvienošana PHP, lai katrs ir indeksa diapazons bez kārtošanas.
 * INDEXED BY: bez indeksa prepare krīt (izsaucējs tad rāda grupas lielākos), nevis klusi kārto.
 *
 * @param int      $rid      lapas pašas rowid katalogā
 * @param string[] $ter_visi reg_saistitie_teritorija() trešais elements (netukšs)
 * @return array{0: array<int,array{regcode:string,name:string,turnover:float}>, 1: int}
 *         [kaimiņi apgrozījuma dilšanas secībā, cik no tiem ir VIRS lapas]
 */
function reg_saistitie_kaimini(PDO $pdo, int $rid, string $nace_kods, array $ter_visi, int $limit = 8): array
{
    $limit = max(1, $limit);
    $pats  = '(SELECT turnover FROM companies WHERE rowid = :r)';
    $sql   = "SELECT rowid AS rid, regcode, name, turnover FROM companies INDEXED BY idx_comp_nace_loc
              WHERE nace_code_np = :n AND location = :l AND length(regcode) = 11 AND %s LIMIT $limit";
    $dalas = [   // katrā pusē: vispirms tāds pats apgrozījums, tad nākamie
        ['zem',  "turnover = $pats AND rowid > :r ORDER BY rowid ASC"],
        ['zem',  "turnover < $pats ORDER BY turnover DESC, rowid ASC"],
        ['virs', "turnover = $pats AND rowid < :r ORDER BY rowid DESC"],
        ['virs', "turnover > $pats ORDER BY turnover ASC, rowid DESC"],
    ];

    $puses = ['virs' => [], 'zem' => []];
    foreach ($dalas as [$puse, $nosac]) {
        $st = $pdo->prepare(sprintf($sql, $nosac));
        foreach ($ter_visi as $loc) {
            $st->bindValue(':n', $nace_kods);
            $st->bindValue(':l', (string)$loc);
            $st->bindValue(':r', $rid, PDO::PARAM_INT);
            $st->execute();
            foreach ($st->fetchAll() as $r) {
                $puses[$puse][] = ['rid' => (int)$r['rid'], 'regcode' => (string)$r['regcode'],
                                   'name' => (string)$r['name'], 'turnover' => (float)$r['turnover']];
            }
        }
    }
    // Tuvākie lapai — pirmie: zem tās lielākais apgrozījums un mazākais rowid, virs tās otrādi.
    usort($puses['zem'],  static fn($a, $b) => [$b['turnover'], $a['rid']] <=> [$a['turnover'], $b['rid']]);
    usort($puses['virs'], static fn($a, $b) => [$a['turnover'], $b['rid']] <=> [$b['turnover'], $a['rid']]);

    $n_virs = min(intdiv($limit, 2), count($puses['virs']));
    $n_zem  = min($limit - $n_virs, count($puses['zem']));
    $n_virs = min($limit - $n_zem, count($puses['virs']));

    $izvele = array_merge(array_reverse(array_slice($puses['virs'], 0, $n_virs)), array_slice($puses['zem'], 0, $n_zem));
    return [array_map(static fn($r) => ['regcode' => $r['regcode'], 'name' => $r['name'], 'turnover' => $r['turnover']], $izvele),
            $n_virs];
}

/**
 * Vieninieku riņķis — grupa "Blakus pēc apgrozījuma: {nozare} — citās teritorijās" lapām, kas savā
 * (nozare × teritorija) grupā ir VIENĪGĀS ar apgrozījumu (2026-10-13: 4 084 lapas).
 *
 * KĀPĒC: kaimiņu tām nav, tāpēc tās rādīja teritorijas lielākos (citas nozares) un pašas nesaņēma
 * nevienu kaimiņa saiti — tieši no tām bija visas 1 168 lapas ar apgrozījumu, uz kurām pēc kaimiņu
 * bloka joprojām neveda neviena iekšēja saite (audits 2026-10-13). Ārēja kopa to nelabo: valsts
 * nozares kaimiņi saiti dotu tikai 64 %, jo tie, kam savā teritorijā kaimiņi ir, atpakaļ nesaista.
 *
 * KĀ: tās pašas nozares vieninieki VISĀ valstī ir slēgta kopa. Sakārtoti kā kaimiņi (turnover DESC,
 * rowid ASC), un lapa rāda logu ap sevi ($limit/2 virs, pārējie zem; galā — no otras puses). Katrs
 * kopas loceklis ir sava blakus esošā logā, tātad saiti saņem KATRS vieninieks nozarē, kur tādu ir
 * ≥ 2 (mērīts 2026-10-14: 4 017 no 4 084). Visi pārējie kopā ir citās teritorijās — divi vieninieki
 * vienā teritorijā nevar būt (tad neviens no tiem nebūtu viens). 'UNDEFINED' ir nozare kā jebkura cita.
 *
 * Kopa: viens idx_comp_nace_loc gājiens pa nozari (location, skaits) bez tabulas lasīšanas, teritorijas
 * nosaukumus (vecie novadi) apvieno PHP ar reg_saistitie_teritorija() — tieši tā, kā kaimiņu grupa.
 * length(regcode) = 11 skaitīšanā nav (tas lasītu tabulu): 9 ciparu numuriem apgrozījuma nav
 * (tests to pārbauda); rindas pašas to filtrē. INDEXED BY: bez indeksa prepare krīt, nevis kārto.
 *
 * @param int $rid lapas pašas rowid katalogā
 * @return array{0: array<int,array{regcode:string,name:string,turnover:float,teritorija:string}>, 1: int, 2: string}
 *         [logs apgrozījuma dilšanas secībā, cik no tiem ir VIRS lapas, lapas pašas teritorija];
 *         [[], 0, ''] — ja lapa nav vieninieks vai nozarē citu vieninieku nav
 */
function reg_saistitie_vieninieki(PDO $pdo, int $rid, string $nace_kods, int $limit = 8): array
{
    $limit = max(1, $limit);
    $st = $pdo->prepare('SELECT location, count(*) AS c, min(rowid) AS rid FROM companies INDEXED BY idx_comp_nace_loc
                         WHERE nace_code_np = ? AND turnover IS NOT NULL GROUP BY location');
    $st->execute([$nace_kods]);
    $ter = [];   // teritorija → [skaits, rowid, rādāmais nosaukums]
    foreach ($st->fetchAll() as $r) {
        [$nos, , $visi] = reg_saistitie_teritorija((string)$r['location']);
        if (!$visi) continue;
        $k = $visi[0];
        $ter[$k] = [($ter[$k][0] ?? 0) + (int)$r['c'], (int)$r['rid'], $nos !== '' ? $nos : $k];
    }
    $vieni = [];
    foreach ($ter as [$c, $r_id, $nos]) if ($c === 1) $vieni[$r_id] = $nos;
    if (count($vieni) < 2 || !isset($vieni[$rid])) return [[], 0, ''];

    $ph = implode(',', array_fill(0, count($vieni), '?'));
    $st = $pdo->prepare("SELECT rowid AS rid, regcode, name, turnover FROM companies
                         WHERE rowid IN ($ph) AND length(regcode) = 11");
    $st->execute(array_keys($vieni));
    $s = $st->fetchAll();
    usort($s, static fn($a, $b) => [(float)$b['turnover'], (int)$a['rid']] <=> [(float)$a['turnover'], (int)$b['rid']]);
    $i = array_search($rid, array_map('intval', array_column($s, 'rid')), true);
    if ($i === false) return [[], 0, ''];

    $n_virs = min(intdiv($limit, 2), $i);
    $n_zem  = min($limit - $n_virs, count($s) - 1 - $i);
    $n_virs = min($limit - $n_zem, $i);
    $out = [];
    for ($j = $i - $n_virs; $j <= $i + $n_zem; $j++) {
        if ($j === $i) continue;
        $out[] = ['regcode' => (string)$s[$j]['regcode'], 'name' => (string)$s[$j]['name'],
                  'turnover' => (float)$s[$j]['turnover'], 'teritorija' => $vieni[(int)$s[$j]['rid']]];
    }
    return [$out, $n_virs, $vieni[$rid]];
}

/**
 * Saistīto uzņēmumu kopa vienai uzņēmuma lapai.
 *
 * @return array|null null — ja kataloga nav vai uzņēmums tajā nav atrodams.
 *   ['nace' => ?['kods','nosaukums','url'], 'teritorija' => ?['nosaukums','url'],
 *    'tuvakie' => [['regcode','name','turnover'(,'teritorija')], ...],
 *    'tuvaku_veids' => 'nozare-teritorija'|'teritorija' | 'nozare-valsts'|'valsts' (tikai ar 'vieninieki'),
 *    'tuvaku_atlase' => 'kaimini' (blakus pēc apgrozījuma, reg_saistitie_kaimini) | 'vieninieki' (tās pašas
 *       nozares vieninieki citās teritorijās, reg_saistitie_vieninieki; rindām ir 'teritorija') | 'lielakie' (grupas lielākie),
 *    'pats' => ?['name','turnover','aiz'(,'teritorija')] — ar 'kaimini' un 'vieninieki': lapas pašas rinda un cik ir virs tās,
 *    'valsti' => [['regcode','name','turnover'], ...], 'valsti_pilns' => bool, 'bez_parskata' => [['regcode','name'], ...]]
 *   UZMANĪBU: 'tuvaku_veids' = 'teritorija' nozīmē divas dažādas grupas — ar 'lielakie' tā ir VISA
 *   teritorija, ar 'kaimini' tikai uzņēmumi bez nozares tajā ('UNDEFINED' × teritorija). Panelim
 *   virsraksts jāizvēlas pēc abām atslēgām; vecais panelis (bez 'tuvaku_atlase') 10 667 lapām rakstītu
 *   "Lielākie uzņēmumi" virs kaimiņu saraksta, un ar 'vieninieki' — "Lielākie uzņēmumi — {ter}" virs citu
 *   teritoriju vieniniekiem. Tāpēc 'kaimini' un 'vieninieki' atgriež TIKAI, ja izsaucējs definējis
 *   REG_SAISTITIE_PANELIS ≥ 3 (saistitie_panel.php kopš 2026-10-14); bez tā — 'lielakie', kā līdz 10-13,
 *   un izvietošanas secība (panelis/bibliotēka) vairs nav svarīga.
 *   'valsti_pilns' = false, ja no "valsti" izslēgta pati lapa vai pirmās grupas rinda, kas ir lielāka
 *   par sarakstā pēdējo: tad tie nav nozares lielākie, bet "citi lielākie".
 */
function reg_saistitie_uznemumi(string $regcode, int $limit_tuvakie = 8, int $limit_valsti = 4): ?array
{
    if (!preg_match('/^\d{11}$/', $regcode)) return null;
    $pdo = reg_saistitie_db();
    if (!$pdo) return null;

    try {
        $st = $pdo->prepare('SELECT rowid AS rid, name, nace_code_np, location, turnover FROM companies WHERE regcode = ?');
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
        $atlase  = 'lielakie';
        $pats_r  = null;
        // Lapa ar apgrozījumu: kaimiņi pēc apgrozījuma savā grupā ('UNDEFINED' arī ir grupa).
        // Ja grupā citu ar apgrozījumu nav (4 084 lapas) — tās pašas nozares vieninieki citās
        // teritorijās; ja arī tādu nav — tālāk, uz teritorijas lielākajiem;
        // ja vaicājums krīt (katalogs bez indeksa, aizslēgts) — uz grupas lielākajiem.
        // Tikai panelim, kas šos sarakstus prot attēlot (REG_SAISTITIE_PANELIS ≥ 3 definē saistitie_panel.php):
        // vecais panelis tos rādītu zem "Lielākie uzņēmumi — {teritorija}" un bez pašas rindas, un rsync
        // augšupielādē registrs/lib/ PIRMS registrs/view/ — tā izvietošanas secība vairs nav svarīga.
        $panelis_prot = defined('REG_SAISTITIE_PANELIS') && REG_SAISTITIE_PANELIS >= 3;
        if ($panelis_prot && $pats['turnover'] !== null && $nace_kods !== '' && $ter_visi) {
            $kaimini_krita = false;
            try {
                [$tuvakie, $virs] = reg_saistitie_kaimini($pdo, (int)$pats['rid'], $nace_kods, $ter_visi, $limit_tuvakie);
            } catch (Throwable $e) {
                static $zinots = false;
                if (!$zinots) {
                    $zinots = true;
                    error_log('saistitie: kaimiņu vaicājums krita, rāda grupas lielākos — ' . $e->getMessage());
                }
                $tuvakie = [];
                $kaimini_krita = true;
            }
            if ($tuvakie) {
                $atlase = 'kaimini';
                $veids  = $nace !== null ? 'nozare-teritorija' : 'teritorija';
                $pats_r = ['name' => (string)$pats['name'], 'turnover' => (float)$pats['turnover'], 'aiz' => $virs];
            } elseif (!$kaimini_krita) {
                try {
                    [$tuvakie, $virs, $pats_ter] = reg_saistitie_vieninieki($pdo, (int)$pats['rid'], $nace_kods, $limit_tuvakie);
                } catch (Throwable $e) {
                    static $zinots_r = false;
                    if (!$zinots_r) {
                        $zinots_r = true;
                        error_log('saistitie: vieninieku riņķa vaicājums krita, rāda grupas lielākos — ' . $e->getMessage());
                    }
                    $tuvakie = [];
                }
                if ($tuvakie) {
                    $atlase = 'vieninieki';
                    $veids  = $nace !== null ? 'nozare-valsts' : 'valsts';
                    $pats_r = ['name' => (string)$pats['name'], 'turnover' => (float)$pats['turnover'], 'aiz' => $virs,
                               'teritorija' => $pats_ter];
                }
            }
        }
        // Apgrozījums var būt NULL (nav iesniegts pārskats) — tādi uzņēmumi saraksta
        // beigās neko nepasaka, tāpēc ņemam tikai tos, kam skaitlis ir.
        if (!$tuvakie && $nace !== null && $ter_visi) {
            // NEpievienot length(regcode) = 11: tad šis vaicājums varētu lietot daļējo
            // idx_comp_nace_turn un ar vairākiem location nosaukumiem lasītu visu nozari (3,8 ms).
            // 9 ciparu numuriem apgrozījuma nav (turnover IS NOT NULL tos jau izslēdz); tests 9).
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
            // length(regcode) = 11 — tikai numuri ar lapu (kā reg_saistitie_bez_parskata). Tas ir
            // arī daļējā indeksa idx_comp_nace_turn nosacījums: bez tā vaicājums to nevar lietot
            // un kārto visu nozari (4,3 ms NACE 6820). Pārējos vaicājumus šis indekss nedrīkst
            // ietekmēt — sk. section_nozare.php.
            $sql = "SELECT regcode, name, turnover FROM companies
                    WHERE nace_code_np = ? AND regcode NOT IN ($ph) AND turnover IS NOT NULL AND length(regcode) = 11
                    ORDER BY turnover DESC LIMIT " . max(1, $limit_valsti);
            $st = $pdo->prepare($sql);
            $st->execute(array_merge([$nace_kods], $jau));
            $valsti = $st->fetchAll();
        }

        // Vai "valsti" tiešām ir nozares lielākie. No tā vaicājuma izslēgta pati lapa un pirmās grupas
        // rindas; ja kāda no tām ir lielāka par sarakstā pēdējo, saraksts ir "citi lielākie" (Sadales
        // tīkla lapā nozares lielākais ir tā pati — zem "Lielākie šajā nozarē" tad stāvēja 2.–5. vieta).
        // Pirmās grupas rindas ir tajā pašā nozarē tikai ar veidu 'nozare-teritorija' un 'nozare-valsts'
        // (vieninieki); teritorijas lielāko sarakstā šīs nozares uzņēmumu nav (citādi tie būtu atrasti
        // nozares vaicājumā).
        $valsti_pilns = true;
        if ($valsti) {
            $pedejais  = (float)$valsti[count($valsti) - 1]['turnover'];
            $izslegtie = in_array($veids, ['nozare-teritorija', 'nozare-valsts'], true) ? array_column($tuvakie, 'turnover') : [];
            if ($pats['turnover'] !== null) $izslegtie[] = $pats['turnover'];
            foreach ($izslegtie as $t) {
                if ((float)$t > $pedejais) { $valsti_pilns = false; break; }
            }
        }

        $bez_parskata = $ter_visi
            ? reg_saistitie_bez_parskata($pdo, $regcode, $nace !== null ? $nace_kods : null, $ter_visi)
            : [];

        if (!$tuvakie && !$valsti && !$bez_parskata && $nace === null && $ter_url === null) return null;

        return [
            'bez_parskata' => $bez_parskata,
            'nace'         => $nace,
            'teritorija'   => $ter_url !== null ? ['nosaukums' => $ter_nos, 'url' => $ter_url] : null,
            'pats'         => $pats_r,
            'tuvakie'      => $tuvakie,
            'tuvaku_atlase' => $atlase,
            'tuvaku_veids' => $veids,
            'valsti'       => $valsti,
            'valsti_pilns' => $valsti_pilns,
        ];
    } catch (Throwable $e) {
        return null;   // panelis nekad nedrīkst lauzt uzņēmuma lapu
    }
}
