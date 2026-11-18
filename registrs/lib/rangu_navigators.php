<?php
/**
 * registrs/lib/rangu_navigators.php — "Pozīcija nozarē" rangu navigators: statiskas saites uz uzņēmumiem,
 * kas nozares apgrozījuma rangā stāv 1, 5, 25, 125, 625 un 3 125 vietas augstāk un zemāk.
 *
 * KĀPĒC (2026-10-14): paneļa grafikos uzņēmumu nosaukumi ir tikai uznirstošos logos, ko JS zīmē pēc datu
 * ielādes, — Googlebot neklikšķina, tāpēc no grafika neviena saite nerodas. Kaimiņu bloks (saistitie.php)
 * bāreņus novērš, bet ceļš pa to ir lineārs: no nozares TOP-50 līdz saraksta vidum vajag simtiem klikšķu
 * (rangu ķēdes simulācija 670 nozarēs: ≤ 5 klikšķos tikai 20 % lapu, garākais ceļš 4 318). Lēcieni ±1, ±5,
 * ±25, ±125, ±625, ±3 125 (12 saites) to saīsina: ≤ 5 klikšķos 90 %, ≤ 7 — 97 %, garākais 11
 * (_Optimizacija/test_tools/registrs/rangu_navigatora_simulacija.py, variants D — ja saites dod KATRS saraksta
 * loceklis; tikai lapas ar paneli: ≤ 7 — 95 %). Visā vietnes grafā (_Optimizacija/test_tools/registrs/saisu_grafs.php,
 * 2026-10-14) no sākumlapas ≤ 5 klikšķos 37,7 → 65,0 %, ≤ 10 — 79,6 → 97,4 %. ±1 dod arī pārklājumu: katrs
 * saraksta loceklis ir blakus esošā lapas saitēs, ja tai ir šis panelis.
 *
 * DATI: nace_stats.sqlite tabula nace_rangs (registrs/build/prepare.php) — TĀ PATI secība, ko grafiks
 * "Apgrozījums (Rangs)" iegūst no nace_stats JSON, tātad vieta un kopskaits sakrīt ar grafika ziņu.
 * Nozares kods — kā grafikam (industry_charts.js): ciparu daļa no $page_data['nace_code'], tukšs → '0000'.
 * Nosaukumi — kā katalogā (saistitie.php), lai sakristu ar "Tuvākie uzņēmumi" rindām; ja tur nav — UR.
 *
 * DROŠĪBA: nekad nemet izņēmumu. Ja tabulas nav (vecs nace_stats pirms nakts būves) vai uzņēmums nav
 * šīs nozares sarakstā, atgriež null un navigators nerādās — klusi; error_log (vienreiz pieprasījumā)
 * tikai tad, ja tabula ir, bet vaicājums krīt.
 */
declare(strict_types=1);

require_once __DIR__ . '/saistitie.php';   // reg_saistitie_db() — kataloga nosaukumiem

const REG_RANGU_LECIENI = [1, 5, 25, 125, 625, 3125];

/** nace_stats.sqlite savienojums (viens uz pieprasījumu). null — ja faila nav vai tas nav lasāms. */
function reg_rangu_db(): ?PDO
{
    static $pdo = null, $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    $f = (function_exists('reg_search_dir') ? reg_search_dir() : dirname(__DIR__) . '/assets/search') . '/nace_stats.sqlite';
    if (!is_file($f)) return null;
    try {
        $pdo = new PDO('sqlite:' . $f);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 1);   // kā katalogam: aizslēgts fails nedrīkst turēt lapu 60 s
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

/** Grafika nozares kods: tikai cipari ('68.20' → '6820'); tukšs, 'UNDEFINED', '?' → '0000' (kā industry_charts.js). */
function reg_rangu_kods(string $nace_code): string
{
    $k = (string)preg_replace('/\D/', '', $nace_code);
    return $k === '' ? '0000' : $k;
}

/**
 * Navigatora rindas vienai lapai.
 *
 * @return array{kods:string, rangs:int, kopa:int,
 *               rindas: list<array{rangs:int, regcode:string, name:string, turnover:float, pats:bool}>}|null
 *         rindas — rangu secībā, ieskaitot pašu lapu ('pats' = true); null — ja navigatoru nerāda
 */
function reg_rangu_navigators(string $regcode, string $nace_code): ?array
{
    if (!preg_match('/^\d{11}$/', $regcode)) return null;
    $db = reg_rangu_db();
    if (!$db) return null;
    $kods = reg_rangu_kods($nace_code);

    try {
        // Līdz pirmajai nakts būvei ar jauno prepare.php tabulas nav — tas ir gaidīts stāvoklis, ne kļūda;
        // bez šīs pārbaudes katrs pieprasījums rakstītu error_log (recenzija 2026-10-14).
        static $ir_tabula = null;
        if ($ir_tabula === null) {
            $ir_tabula = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'nace_rangs'")->fetchColumn();
        }
        if (!$ir_tabula) return null;
        $st = $db->prepare('SELECT rangs FROM nace_rangs WHERE regcode = ? AND code = ?');
        $st->execute([$regcode, $kods]);
        $rangs = $st->fetchColumn();
        if ($rangs === false) return null;
        $rangs = (int)$rangs;
        $st = $db->prepare('SELECT max(rangs) FROM nace_rangs WHERE code = ?');
        $st->execute([$kods]);
        $kopa = (int)$st->fetchColumn();
        if ($kopa < 2) return null;

        $vietas = [$rangs];
        foreach (REG_RANGU_LECIENI as $j) {
            if ($rangs - $j >= 1) $vietas[] = $rangs - $j;
            if ($rangs + $j <= $kopa) $vietas[] = $rangs + $j;
        }
        $ph = implode(',', array_fill(0, count($vietas), '?'));
        $st = $db->prepare("SELECT rangs, regcode, name, turnover FROM nace_rangs WHERE code = ? AND rangs IN ($ph) ORDER BY rangs");
        $st->execute(array_merge([$kods], $vietas));
        $rindas = $st->fetchAll();
    } catch (Throwable $e) {
        static $zinots = false;
        if (!$zinots) {
            $zinots = true;
            error_log('rangu_navigators: navigators nerādās — ' . $e->getMessage());
        }
        return null;
    }

    // Kataloga nosaukumi (SIA "X", nevis UR "X SIA" / pilnā forma). Ja katalogs nav pieejams — UR nosaukums.
    $kat = [];
    $pdo = reg_saistitie_db();
    if ($pdo) {
        try {
            $kodi = array_column($rindas, 'regcode');
            $st = $pdo->prepare('SELECT regcode, name FROM companies WHERE regcode IN (' . implode(',', array_fill(0, count($kodi), '?')) . ')');
            $st->execute($kodi);
            foreach ($st->fetchAll() as $r) $kat[(string)$r['regcode']] = (string)$r['name'];
        } catch (Throwable $e) {
            $kat = [];
        }
    }

    $out = [];
    foreach ($rindas as $r) {
        $rc = (string)$r['regcode'];
        if (!preg_match('/^\d{11}$/', $rc)) continue;   // tikai numuri ar lapu
        $nos = $kat[$rc] ?? '';
        $out[] = ['rangs' => (int)$r['rangs'], 'regcode' => $rc, 'name' => $nos !== '' ? $nos : (string)$r['name'],
                  'turnover' => (float)$r['turnover'], 'pats' => $rc === $regcode];
    }
    if (count($out) < 2) return null;
    return ['kods' => $kods, 'rangs' => $rangs, 'kopa' => $kopa, 'rindas' => $out];
}
