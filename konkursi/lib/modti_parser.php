<?php
/**
 * konkursi/lib/modti_parser.php — Aizsardzības ministrijas tirgus izpētes
 * (https://www.mod.gov.lv/lv/tirgus-izpetes), avots MODTI.
 *
 * Tirgus izpēte NAV PIL iepirkums: iestāde apzina tirgu (zemsliekšņa iegāde vai
 * cenu aptauja pirms formāla iepirkuma) un var noslēgt līgumu bez procedūras.
 * Ievāc kā atsevišķu avotu; kategorija 'iepirkumi', jo ierakstiem ir pieteikšanās
 * termiņš; UI marķē ar "Tirgus izpēte" nozīmīti + paskaidrojumu detaļās.
 *
 * Slodze: ≤MODTI_MAX_PAGES saraksta lapas (Drupal servera HTML, šobrīd 3);
 * detaļu lapu ver TIKAI jauniem id vai ja sarakstā mainījies termiņš — ikdienā
 * tas ir 1–3 saraksta pieprasījumi un parasti 0 detaļu.
 *
 * GDPR: kontaktpersonu rindas, e-pasta adreses un tālruņi no glabātā apraksta
 * tiek izgriezti — kontakti redzami oficiālajā lapā (document_url).
 */
declare(strict_types=1);

/** Saraksta lapa → rindas [['id','url','title','pub','deadline'], …]. */
function modti_parse_list(string $html): array {
    if (!preg_match_all('#<article class="[^"]*node--type-market-research[^"]*".*?</article>#s', $html, $arts)) {
        return [];
    }
    $rows = [];
    foreach ($arts[0] as $a) {
        if (!preg_match('#<a href="(/lv/tirgus-izpete/([^"]+))"[^>]*>(.*?)</a>#s', $a, $m)) continue;
        $title = trim(preg_replace('/\s+/u', ' ',
            html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        // Dekoratīvās pēdiņas un beigu punkts ap virsrakstu (avotā nekonsekventi)
        $title = trim($title, "“”\"„ .");
        if ($title === '') continue;
        $pub = preg_match('#field-date-of-issue.*?datetime="(\d{4}-\d{2}-\d{2})#s', $a, $d) ? $d[1] : null;
        $dl  = preg_match('#field-expiration-date.*?datetime="(\d{4}-\d{2}-\d{2})#s', $a, $d) ? $d[1] : null;
        $rows[] = [
            'id'       => 'MODTI-' . $m[2],
            'url'      => MODTI_BASE_URL . $m[1],
            'title'    => $title,
            'pub'      => $pub,
            'deadline' => $dl,
        ];
    }
    return $rows;
}

/** Sabalansēti izgriež <div class="…$classMarker…"> saturu (ķermenī ir ligzdoti div). */
function modti_div_text(string $html, string $classMarker): ?string {
    $p = strpos($html, $classMarker);
    if ($p === false) return null;
    $start = strpos($html, '>', $p);
    if ($start === false) return null;
    $depth = 1; $i = $start + 1; $end = strlen($html);
    while ($depth > 0) {
        if (!preg_match('#<(/?)div\b#i', $html, $m, PREG_OFFSET_CAPTURE, $i)) break;
        $depth += $m[1][0] === '/' ? -1 : 1;
        if ($depth === 0) { $end = $m[0][1]; break; }
        $i = $m[0][1] + 4;
    }
    return substr($html, $start + 1, $end - $start - 1);
}

/** GDPR: izgriež kontaktpersonas, e-pastus un tālruņus no glabātā apraksta. */
function modti_scrub(string $t): string {
    $t = preg_replace('/^.*kontaktperson.*$/imu', '', $t);
    $t = preg_replace('/[\w.+-]+@[\w.-]+\.\w{2,}/u', '[e-pasts oficiālajā lapā]', $t);
    $t = preg_replace('/(\+?371[\s\-]?)?\b\d{8}\b/u', '[tālr. oficiālajā lapā]', $t);
    $t = preg_replace('/[ \t]+/u', ' ', $t);
    return trim(preg_replace('/\n{2,}/u', "\n", $t));
}

/** Detaļu lapa → ['description','buyer','deadline_time'] (viss neobligāts). */
function modti_parse_detail(string $html): array {
    $out = ['description' => null, 'buyer' => null, 'deadline_time' => null];
    $body = modti_div_text($html, 'field--name-body');
    if ($body === null) return $out;
    $txt = html_entity_decode(strip_tags(preg_replace('#<(p|br|div|li|tr)[^>]*>#i', "\n", $body)),
        ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = str_replace("\u{a0}", ' ', $txt);
    if (preg_match('/Pasūtītājs:\s*([^\n]+)/u', $txt, $m)) $out['buyer'] = trim($m[1]);
    if (preg_match('/plkst\.?\s*(\d{1,2})[:.](\d{2})/u', $txt, $m)) {
        $out['deadline_time'] = sprintf('%02d:%s', (int)$m[1], $m[2]);
    }
    $desc = modti_scrub($txt);
    if ($desc !== '') $out['description'] = mb_substr($desc, 0, 4000, 'UTF-8');
    return $out;
}

/** @return int importēto (jauno + versiju) skaits */
function ks_sync_modti(PDO $pdo): int {
    $writer = ks_upsert_stmt($pdo);
    $dlSt = $pdo->prepare("SELECT deadline_date FROM notices WHERE id = ? AND source = 'MODTI'");

    $imported = 0; $details = 0; $seen = 0;
    for ($page = 0; $page < MODTI_MAX_PAGES; $page++) {
        if (ks_stop_requested()) break;
        $html = ks_http_get(sprintf(MODTI_LIST_URL, $page));
        if ($html === null) {
            if ($page === 0) ks_log('  ⚠ MODTI: saraksts nav pieejams.');
            break;
        }
        $rows = modti_parse_list($html);
        if (!$rows) break;   // aiz pēdējās lapas
        $seen += count($rows);

        foreach ($rows as $r) {
            if (ks_stop_requested() || $details >= MODTI_MAX_DETAILS) break 2;
            // Detaļas tikai jauniem id vai ja sarakstā redzams cits termiņš
            // (pagarinājums) — pārējie ir fiksēti žurnālā, nav ko pārpētīt.
            $dlSt->execute([$r['id']]);
            $known = $dlSt->fetch(PDO::FETCH_ASSOC);
            $dlSt->closeCursor();
            if ($known !== false && (string)($known['deadline_date'] ?? '') === (string)$r['deadline']) continue;

            usleep(MODTI_DELAY_MS * 1000);
            $d = modti_fetch_detail($r['url']);
            $details++;
            if ($d === null) continue;   // detaļa nenolasās — mēģinās nākamreiz

            $res = $writer->execute([
                'id'               => $r['id'],
                'source'           => 'MODTI',
                'category'         => 'iepirkumi',
                'title'            => $r['title'],
                'description'      => $d['description'],
                'buyer_name'       => $d['buyer'] ?? 'Aizsardzības ministrija',
                'buyer_id'         => null,
                'buyer_country'    => 'LV',
                'buyer_activity'   => 'defence',
                'buyer_type'       => null,
                'procure_nature'   => null,
                'publication_date' => $r['pub'],
                'deadline_date'    => $r['deadline'],
                'deadline_time'    => $d['deadline_time'],
                'publication_number' => null,
                'budget'           => null,
                'currency'         => null,
                'document_url'     => $r['url'],
                'buyer_profile_url' => null,
                'procedure_type'   => 'Tirgus izpēte (ārpus PIL)',
                'notice_sub_type'  => null,
                'notice_lang'      => 'LV',
                'issue_date'       => $r['pub'],
                'main_nuts'        => null,
                'main_country'     => 'LV',
                'funding_program'  => null,
                'prev_notice_ref'  => null,
                'contract_folder_id' => null,
                'main_cpv'         => null,
                'cpv_codes'        => null,
                'lots'             => null,
                'organizations'    => null,
                'notice_contact'   => null,
                'source_file'      => 'modti',
            ]);
            if ($res !== 'unchanged') $imported++;
        }
    }
    if ($seen > 0 || $imported > 0) {
        ks_log("  · MODTI: sarakstā $seen, detaļas $details, {$writer->summary()}.");
    }
    return $imported;
}

/** Detaļu lapas ielāde caur kopējo HTTP slāni (anti-blok aizsardzība mantojas). */
function modti_fetch_detail(string $url): ?array {
    $html = ks_http_get($url);
    return $html === null ? null : modti_parse_detail($html);
}
