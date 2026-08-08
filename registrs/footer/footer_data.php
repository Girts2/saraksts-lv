<?php
/**
 * registrs/footer/footer_data.php — dati bagātajam kājenes blokam.
 *
 * Kājene rādās katrā lapas ielādē, tāpēc NEVIENS vaicājums šeit nedrīkst maksāt
 * vairāk par vienreiz dienā: visas funkcijas iet caur fd_cache(), kas rezultātu
 * glabā sys_get_temp_dir() dienas failā. Ja datu faila nav (svaigs deploy pirms
 * pirmās būves), funkcijas klusi atgriež tukšu masīvu — kājene bloku vienkārši
 * nerāda, lapa nemirst.
 */
declare(strict_types=1);

/** Dienas kešs: viens JSON fails uz atslēgu; rīt automātiski pārrēķina. */
function fd_cache(string $key, callable $fn): array {
    $file = sys_get_temp_dir() . '/saraksts_footer_' . $key . '_' . date('Y-m-d') . '.json';
    if (is_file($file)) {
        $d = json_decode((string)@file_get_contents($file), true);
        if (is_array($d)) return $d;
    }
    try { $d = $fn(); } catch (Throwable $e) { $d = []; }
    if (!is_array($d)) $d = [];
    @file_put_contents($file, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $d;
}

function fd_sqlite(string $path): ?PDO {
    if (!is_file($path)) return null;
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

/** Lielākās nozares (NACE 2.1 nodaļas) pēc uzņēmumu skaita: [kods, nosaukums, skaits]. */
function fd_top_nozares(int $n = 12): array {
    return fd_cache('nozares', function () use ($n): array {
        $pdo = fd_sqlite($_SERVER['DOCUMENT_ROOT'] . '/nozare/katalogs.sqlite');
        if ($pdo === null) return [];
        $names = [];
        foreach ($pdo->query("SELECT code, name FROM nace WHERE level = 2") as $r) {
            $names[$r['code']] = $r['name'];
        }
        $rows = $pdo->query(
            "SELECT substr(nace_code_np, 1, 2) AS div, COUNT(*) AS c FROM companies
             WHERE nace_code_np != 'UNDEFINED' GROUP BY div ORDER BY c DESC LIMIT " . (int)$n
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['kods' => $r['div'], 'nosaukums' => $names[$r['div']] ?? $r['div'], 'skaits' => (int)$r['c']];
        }
        return $out;
    });
}

/** Lielākie uzņēmumi pēc pēdējā gada apgrozījuma: [regcode, name, turnover]. */
function fd_top_uznemumi(int $n = 10): array {
    return fd_cache('uznemumi', function () use ($n): array {
        $pdo = fd_sqlite($_SERVER['DOCUMENT_ROOT'] . '/nozare/katalogs.sqlite');
        if ($pdo === null) return [];
        return $pdo->query(
            "SELECT regcode, name, turnover FROM companies
             WHERE turnover IS NOT NULL ORDER BY turnover DESC LIMIT " . (int)$n
        )->fetchAll(PDO::FETCH_ASSOC);
    });
}

/** Reģistra kopskaitļi kājenei: uzņēmumu skaits ar aktivitāti + datu datums. */
function fd_registrs_stats(): array {
    return fd_cache('registrs', function (): array {
        $path = $_SERVER['DOCUMENT_ROOT'] . '/nozare/katalogs.sqlite';
        $pdo = fd_sqlite($path);
        if ($pdo === null) return [];
        return [
            'aktivi'    => (int)$pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn(),
            'nozares'   => (int)$pdo->query("SELECT COUNT(DISTINCT substr(nace_code_np,1,2)) FROM companies WHERE nace_code_np != 'UNDEFINED'")->fetchColumn(),
            'atjaunots' => date('Y-m-d', (int)filemtime($path)),
        ];
    });
}

/** Struktūras kopskaitļi no būves overview.json. */
function fd_struktura_stats(): array {
    return fd_cache('struktura', function (): array {
        $path = $_SERVER['DOCUMENT_ROOT'] . '/struktura/data/overview.json';
        if (!is_file($path)) return [];
        $d = json_decode((string)file_get_contents($path), true);
        if (!is_array($d)) return [];
        return [
            'apgrozijums' => (float)($d['totalT'] ?? 0),
            'uznemumi'    => (int)($d['totalN'] ?? 0),
            'gadi'        => $d['years'] ?? [],
            'vietas'      => is_array($d['regions'] ?? null) ? count($d['regions']) : 0,
        ];
    });
}

/** Aktīvie iepirkumi pa valstīm: [cc, nosaukums, skaits], dilstoši. */
function fd_konkursi_valstis(): array {
    return fd_cache('valstis', function (): array {
        $pdo = fd_sqlite($_SERVER['DOCUMENT_ROOT'] . '/konkursi/db/tenders.db');
        if ($pdo === null) return [];
        // Nosaukumi latviski — kopija no konkursi.php $COUNTRY_LV (tas dzīvo AJAX
        // zarā un nav iekļaujams atsevišķi; mainot tur, atjauno arī šeit).
        $lv = [
            'LV' => 'Latvija', 'LT' => 'Lietuva', 'EE' => 'Igaunija', 'PL' => 'Polija',
            'FI' => 'Somija', 'SE' => 'Zviedrija', 'DK' => 'Dānija', 'DE' => 'Vācija',
            'FR' => 'Francija', 'NL' => 'Nīderlande', 'BE' => 'Beļģija', 'LU' => 'Luksemburga',
            'IE' => 'Īrija', 'AT' => 'Austrija', 'CZ' => 'Čehija', 'SK' => 'Slovākija',
            'HU' => 'Ungārija', 'SI' => 'Slovēnija', 'HR' => 'Horvātija', 'RO' => 'Rumānija',
            'BG' => 'Bulgārija', 'GR' => 'Grieķija', 'IT' => 'Itālija', 'ES' => 'Spānija',
            'PT' => 'Portugāle', 'CY' => 'Kipra', 'MT' => 'Malta', 'NO' => 'Norvēģija',
            'IS' => 'Islande', 'LI' => 'Lihtenšteina', 'CH' => 'Šveice', 'GB' => 'Lielbritānija',
            'UA' => 'Ukraina', 'MD' => 'Moldova', 'RS' => 'Serbija', 'BA' => 'Bosnija un Hercegovina',
            'AL' => 'Albānija', 'MK' => 'Ziemeļmaķedonija', 'ME' => 'Melnkalne', 'TR' => 'Turcija',
            'GE' => 'Gruzija', 'AZ' => 'Azerbaidžāna', 'AM' => 'Armēnija',
            'AD' => 'Andora', 'MC' => 'Monako', 'SM' => 'Sanmarīno', 'XK' => 'Kosova',
        ];
        $rows = $pdo->query(
            "SELECT buyer_country AS cc, COUNT(*) AS c FROM notices
             WHERE category = 'iepirkumi' AND buyer_country IS NOT NULL AND buyer_country != ''
             GROUP BY cc ORDER BY c DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $cc = strtoupper((string)$r['cc']);
            if (!isset($lv[$cc])) continue;   // nezināms/nekorekts kods — kājenē nerādām
            $out[] = ['cc' => $cc, 'nosaukums' => $lv[$cc], 'skaits' => (int)$r['c']];
        }
        return $out;
    });
}

/** Skaitļa formatēšana kājenei: 12 345 / 1,2 milj. / 3,4 mljrd. */
function fd_num(float $n): string {
    if ($n >= 1e9) return number_format($n / 1e9, 1, ',', ' ') . ' mljrd.';
    if ($n >= 1e6) return number_format($n / 1e6, 1, ',', ' ') . ' milj.';
    return number_format($n, 0, ',', ' ');
}
