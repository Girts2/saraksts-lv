<?php
/**
 * granti/lib/sitemap.php — sadaļas vietnes kartes rakstīšana.
 *
 * KĀPĒC ATSEVIŠĶI. Šo sauc DIVAS puses: nakts kopējā būve (registrs/build/report_tracker.php
 * generate_sitemaps()) un pati grantu dienas būve. Otrais ir tas, kas nozīmē: grantu dati
 * mainās katru dienu savā laikā, un bez šī karte vienmēr atpaliktu līdz nākamajai kopējai
 * būvei. Pirmajā automātiskajā palaišanā ES plūsma pameta 3 konkursus — to adreses kartē
 * palika, bet lapas jau atdeva 404, un tieši tā ir kļūda, ko meklētājs pieraksta vietnei.
 *
 * KO NELIEK KARTĒ. Plānās lapas (zem 500 rakstzīmēm apraksta — visi SIF ieraksti un daži ES)
 * pašas nes noindex; kartē tām nav vietas. Beigušies konkursi PALIEK: adrese dzīvo, kamēr
 * ieraksts ir datubāzē, un vēsturisks konkurss joprojām atbild uz meklējumu pēc nosaukuma.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Uzraksta sitemap-granti.xml. Atgriež ierakstīto adrešu skaitu (0 = fails netika rakstīts).
 * Kļūda te NEDRĪKST apturēt saucēju: gan nakts būve, gan dienas būve turpina bez kartes.
 */
function gr_raksti_vietnes_karti(string $sitemapDir, string $baseDomain = 'https://saraksts.lv',
                                 ?callable $log = null): int {
    $db = granti_db_path();
    if (!is_file($db)) return 0;
    try {
        $pdo = new PDO('sqlite:' . $db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout=3000');   // nakts būve var sakrist ar tulkošanas rakstīšanu
        $lastmod = date('Y-m-d', @filemtime($db) ?: time());
        @mkdir($sitemapDir, 0775, true);
        // Raksta pagaidu failā un pārceļ: meklētājs nedrīkst uzķert pusuzrakstītu XML.
        $mērķis = $sitemapDir . '/sitemap-granti.xml';
        $tmp = $mērķis . '.tmp';
        $fh = fopen($tmp, 'w');
        if ($fh === false) return 0;
        fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        fwrite($fh, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
        $n = 0;
        $q = $pdo->query("SELECT slug, status FROM grants
            WHERE slug IS NOT NULL AND slug <> ''
              AND LENGTH(COALESCE(sec_objective,'') || COALESCE(sec_outcome,'')
                       || COALESCE(sec_scope,'') || COALESCE(sec_eligibility,'')
                       || COALESCE(sec_specific,'')) >= 500
            ORDER BY slug");
        foreach ($q as $g) {
            // lastmod TIKAI atvērtajiem: datubāzi pārraksta katru dienu, tāpēc faila datums
            // būtu "šodien" arī gadu veciem slēgtiem konkursiem, un meklētājs iemācās to ignorēt.
            $atverts = (string)$g['status'] === 'open';
            fwrite($fh, "<url><loc>{$baseDomain}/granti/{$g['slug']}</loc>"
                      . ($atverts ? "<lastmod>{$lastmod}</lastmod><changefreq>daily</changefreq>" : "<changefreq>monthly</changefreq>")
                      . "</url>\n");
            $n++;
        }
        fwrite($fh, '</urlset>' . "\n");
        fclose($fh);
        if (!rename($tmp, $mērķis)) { @unlink($tmp); return 0; }
        if ($log) $log("   sitemap-granti.xml: $n konkursu lapas");
        return $n;
    } catch (Throwable $e) {
        if ($log) $log('   sitemap-granti.xml IZLAISTS: ' . $e->getMessage());
        return 0;
    }
}
