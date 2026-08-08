<?php
/**
 * konkursi/lib/lvti_parser.php — Latvijas uzņēmumu/iestāžu tirgus izpētes
 * ārpus IUB/TED/EIS plūsmām. Trīs avoti:
 *
 *   RSTI — RP SIA "Rīgas satiksme" tirgus izpētes
 *          (rigassatiksme.lv/lv/par-mums/iepirkumi/tirgus-izpetes/)
 *   ASTI — SIA "Rīgas Austrumu klīniskā universitātes slimnīca" tirgus izpētes
 *          (aslimnica.lv/iepirkumi/tirgus-izpetes/)
 *   LDZ  — VAS "Latvijas dzelzceļš" / SIA "LDz Cargo" tirgus izpētes un apspriedes
 *          (ldz.lv/lv/iepirkumi — TIKAI izpētes/apspriedes, formālie konkursi = IUB/TED)
 *
 * Neviens NAV PIL iepirkums (zemsliekšņa/pirmsiepirkuma izpētes) — tas pats
 * nodalījums kā MODTI: kategorija 'iepirkumi' (ir pieteikšanās termiņš),
 * procedure_type 'Tirgus izpēte (ārpus PIL)', UI dzeltenā "Tirgus izpēte"
 * nozīmīte + brīdinājums detaļās.
 *
 * Slodze: katrs — 1 saraksta lapa; RSTI ver detaļas TIKAI jauniem id (apraksts),
 * ASTI/LDZ detaļu lapas NEver vispār (viss vajadzīgais ir sarakstā).
 *
 * GDPR: RSTI detaļu aprakstos ir personu vārdi ar e-pastiem ("sazinieties ar …
 * pa e-pastu: …") — teikumus ar e-pastu izgriež pilnībā (rsti_scrub), pāri
 * palikušos e-pastus/tālruņus aizstāj kā modti_scrub. ASTI/LDZ aprakstu neņem.
 *
 * Sadales tīkls un Latvenergo NAV šeit: Latvenergo grupas WAF atdod HTTP 451
 * mūsu identificētajam bota UA — tā apiešana ar UA viltošanu ir pretrunā projekta
 * principiem, tāpēc šie avoti apzināti izlaisti.
 */
declare(strict_types=1);

// ───────────────────────────── RSTI (Rīgas satiksme) ─────────────────────────

/** Saraksta lapa → rindas [['id','url','title','deadline'], …]. */
function rsti_parse_list(string $html): array {
    // <table class="researchProjects"> … rindā: <a href="…/tirgus-izpetes/slug/"
    // title="…">Nosaukums</a> … <td class="date">DD.MM.YYYY.&nbsp;</td>
    $p = strpos($html, 'class="researchProjects"');
    if ($p === false) return [];
    $seg = substr($html, $p);
    $end = strpos($seg, '</table>');
    if ($end !== false) $seg = substr($seg, 0, $end);

    $rows = [];
    // Pa <tr> rindām — anchor un datums vienmēr paliek pārī arī tad, ja kādai
    // rindai datuma nav (regex pāri rindu robežai te mis-pārotu).
    foreach (preg_split('#<tr[ >]#', $seg) as $tr) {
        if (!preg_match('#<a href="(https?://[^"]*/tirgus-izpetes/([^"/]+)/?)"[^>]*>(.*?)</a>#s', $tr, $m)) continue;
        $title = trim(preg_replace('/\s+/u', ' ',
            html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $title = trim($title, "“”\"„ .");
        if ($title === '') continue;
        // "30.07.2026." → "2026-07-30"; nederīgu/tukšu datumu atstāj bez termiņa
        $dl = null;
        if (preg_match('#<td class="date">\s*(\d{2})\.(\d{2})\.(\d{4})#', $tr, $d)
            && checkdate((int)$d[2], (int)$d[1], (int)$d[3])) {
            $dl = "{$d[3]}-{$d[2]}-{$d[1]}";
        }
        $rows[] = [
            'id'       => 'RSTI-' . $m[2],
            'url'      => $m[1],
            'title'    => $title,
            'deadline' => $dl,
        ];
    }
    return $rows;
}

/** GDPR: izgriež TEIKUMUS ar e-pastu/kontaktu, tad aizstāj paliekas. */
function rsti_scrub(string $t): string {
    // Viss aiz "Jautājumu gadījumā…" ir kontaktu bloks — nogriež.
    $t = preg_replace('/Jautājumu\s+gadījumā.*$/isu', '', $t);
    // Teikumi, kuros ir e-pasts vai "sazinieties" — ārā ar visu personas vārdu.
    $t = preg_replace('/[^.!?\n]*(?:@|sazinieties|kontaktperson)[^.!?\n]*[.!?]?/imu', '', $t);
    // Drošības tīkls — tas pats, ko dara modti_scrub.
    $t = preg_replace('/[\w.+-]+@[\w.-]+\.\w{2,}/u', '[e-pasts oficiālajā lapā]', $t);
    $t = preg_replace('/(\+?371[\s\-]?)?\b\d{8}\b/u', '[tālr. oficiālajā lapā]', $t);
    $t = preg_replace('/[ \t]+/u', ' ', $t);
    return trim(preg_replace('/\n{2,}/u', "\n", $t));
}

/** Detaļu lapa → apraksts (bez kontaktiem) vai null. */
function rsti_parse_detail(string $html): ?string {
    // Satura bloks sākas aiz <h2 class="title">…</h2>; beidzas pirms kājenes.
    if (!preg_match('#<h2 class="title">.*?</h2>(.*?)(?:<footer|<div class="footer|</main)#s', $html, $m)) {
        return null;
    }
    $txt = html_entity_decode(strip_tags(preg_replace('#<(p|br|div|li|tr)[^>]*>#i', "\n", $m[1])),
        ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = str_replace("\u{a0}", ' ', $txt);
    // Sīkdatņu joslas teksts mēdz sekot saturam — nogriež.
    $txt = preg_replace('/Mēs izmantojam sīkdatnes.*$/isu', '', $txt);
    $desc = rsti_scrub($txt);
    return $desc === '' ? null : mb_substr($desc, 0, 4000, 'UTF-8');
}

/** @return int importēto (jauno + versiju) skaits */
function ks_sync_rigassatiksme(PDO $pdo): int {
    $writer = ks_upsert_stmt($pdo);
    // publication_date jāpārnes uz jauno versiju (termiņa pagarinājums citādi
    // to noskalotu ar NULL — tas pats slazds, kas bija SIMAP).
    $dlSt = $pdo->prepare("SELECT deadline_date, publication_date FROM notices WHERE id = ? AND source = 'RSTI'");

    $html = ks_http_get(RSTI_LIST_URL);
    if ($html === null) { ks_log('  ⚠ RSTI: saraksts nav pieejams.'); return 0; }
    $rows = rsti_parse_list($html);
    if (!$rows) { ks_log('  ⚠ RSTI: sarakstā nav nevienas izpētes (struktūra mainīta?).'); return 0; }

    // Lapa satur VISU arhīvu līdz pat 2020. gadam (~1200 rindas) — ņem tikai
    // sadaļas darba logu (termiņš ne senāks par beigušos glabāšanas robežu),
    // citādi detaļu budžets izšķiestu uz arhīvu un saraksts pieplūstu ar veco.
    $edge = date('Y-m-d', strtotime('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days'));
    $rows = array_filter($rows, fn($r) => $r['deadline'] !== null && $r['deadline'] >= $edge);

    $imported = 0; $details = 0;
    foreach ($rows as $r) {
        if (ks_stop_requested()) break;
        // Detaļas tikai jauniem id vai ja sarakstā redzams cits termiņš (pagarinājums).
        $dlSt->execute([$r['id']]);
        $known = $dlSt->fetch(PDO::FETCH_ASSOC);
        $dlSt->closeCursor();
        if ($known !== false && (string)($known['deadline_date'] ?? '') === (string)$r['deadline']) continue;

        $desc = null;
        if ($details < RSTI_MAX_DETAILS) {
            usleep(RSTI_DELAY_MS * 1000);
            $dHtml = ks_http_get($r['url']);
            $details++;
            if ($dHtml !== null) $desc = rsti_parse_detail($dHtml);
        }

        $res = $writer->execute([
            'id'               => $r['id'],
            'source'           => 'RSTI',
            'category'         => 'iepirkumi',
            'title'            => $r['title'],
            'description'      => $desc,
            'buyer_name'       => 'RP SIA "Rīgas satiksme"',
            'buyer_id'         => null,
            'buyer_country'    => 'LV',
            'buyer_activity'   => 'transport',
            'buyer_type'       => null,
            'procure_nature'   => null,
            // Publikācijas datums sarakstā nav — pirmoreiz ieraugot fiksē šodienu,
            // versijās pārnes esošo (nevis noskalo ar NULL).
            'publication_date' => $known === false
                ? konkursi_today()
                : (($known['publication_date'] ?? null) ?: konkursi_today()),
            'deadline_date'    => $r['deadline'],
            'deadline_time'    => null,
            'publication_number' => null,
            'budget'           => null,
            'currency'         => null,
            'document_url'     => $r['url'],
            'buyer_profile_url' => null,
            'procedure_type'   => 'Tirgus izpēte (ārpus PIL)',
            'notice_sub_type'  => null,
            'notice_lang'      => 'LV',
            'issue_date'       => null,
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
            'source_file'      => 'rsti',
        ]);
        if ($res !== 'unchanged') $imported++;
    }
    ks_log('  · RSTI: sarakstā ' . count($rows) . ", detaļas $details, {$writer->summary()}.");
    return $imported;
}

// ─────────────────────────── ASTI (Austrumu slimnīca) ────────────────────────

/** Saraksta lapa → rindas [['id','url','title','pub','deadline'], …]. */
function asti_parse_list(string $html): array {
    // Katrs ieraksts: <div class="date-published">…DD.MM.YYYY</div> …
    // <div class="name">…<a href="URL">Nosaukums</a></div> …
    // <div class="date-submitted">…DD.MM.YYYY</div>
    // Sadalām pa 'date-published' — katrs gabals aiz tā ir viens ieraksts.
    $chunks = preg_split('#class="date-published"#', $html);
    array_shift($chunks); // pirms pirmā ieraksta
    $rows = [];
    foreach ($chunks as $c) {
        if (!preg_match('#<div class="name">.*?<a href="([^"]+)"[^>]*>(.*?)</a>#s', $c, $mn)) continue;
        $title = trim(preg_replace('/\s+/u', ' ',
            html_entity_decode(strip_tags($mn[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $title = trim($title, "“”\"„ .");
        if ($title === '') continue;
        // Pirmais datums gabalā (līdz name blokam) = izsludināts.
        $head = substr($c, 0, strpos($c, '<div class="name"') ?: null);
        $pub = preg_match('#(\d{2})\.(\d{2})\.(\d{4})#', $head, $d)
            && checkdate((int)$d[2], (int)$d[1], (int)$d[3]) ? "{$d[3]}-{$d[2]}-{$d[1]}" : null;
        // date-submitted (aiz name bloka) = iesniegšanas termiņš.
        $dl = null;
        if (preg_match('#class="date-submitted".*?(\d{2})\.(\d{2})\.(\d{4})#s', $c, $d)
            && checkdate((int)$d[2], (int)$d[1], (int)$d[3])) {
            $dl = "{$d[3]}-{$d[2]}-{$d[1]}";
        }
        $slug = rtrim($mn[1], '/');
        $slug = substr($slug, strrpos($slug, '/') + 1);
        $rows[] = [
            'id'       => 'ASTI-' . $slug,
            'url'      => $mn[1],
            'title'    => $title,
            'pub'      => $pub,
            'deadline' => $dl,
        ];
    }
    return $rows;
}

/** @return int importēto (jauno + versiju) skaits */
function ks_sync_aslimnica(PDO $pdo): int {
    $writer = ks_upsert_stmt($pdo);
    $html = ks_http_get(ASTI_LIST_URL);
    if ($html === null) { ks_log('  ⚠ ASTI: saraksts nav pieejams.'); return 0; }
    $rows = asti_parse_list($html);
    if (!$rows) { ks_log('  ⚠ ASTI: sarakstā nav nevienas izpētes (struktūra mainīta?).'); return 0; }

    // Lapa satur arī veco (līdz vairākiem gadiem) — ņem tikai darba logu.
    $edge = date('Y-m-d', strtotime('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days'));
    $rows = array_filter($rows, fn($r) => $r['deadline'] !== null && $r['deadline'] >= $edge);

    $imported = 0;
    foreach ($rows as $r) {
        if (ks_stop_requested()) break;
        $res = $writer->execute([
            'id'               => $r['id'],
            'source'           => 'ASTI',
            'category'         => 'iepirkumi',
            'title'            => $r['title'],
            'description'      => null,   // detaļā ir kontakti + saturs tēmā → neņemam
            'buyer_name'       => 'SIA "Rīgas Austrumu klīniskā universitātes slimnīca"',
            'buyer_id'         => null,
            'buyer_country'    => 'LV',
            'buyer_activity'   => 'health',
            'buyer_type'       => null,
            'procure_nature'   => null,
            'publication_date' => $r['pub'],
            'deadline_date'    => $r['deadline'],
            'deadline_time'    => null,
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
            'source_file'      => 'asti',
        ]);
        if ($res !== 'unchanged') $imported++;
    }
    ks_log('  · ASTI: sarakstā ' . count($rows) . ", {$writer->summary()}.");
    return $imported;
}

// ─────────────────────────────── LDZ (Latvijas dzelzceļš) ────────────────────

/**
 * Saraksta lapa → rindas [['id','url','title','buyer','deadline','deadline_time'], …].
 * Ņem TIKAI tirgus izpētes / tirgus cenu izpētes / apspriedes — formālos konkursus
 * (Atklāts konkurss, Iepirkums ar publikāciju) izlaiž, jo tie jau ir IUB/TED plūsmā.
 */
function ldz_parse_list(string $html): array {
    $rows = [];
    foreach (preg_split('#<tr[ >]#', $html) as $tr) {
        if (!preg_match('#views-field-title[^>]*>\s*<a href="([^"]+)"[^>]*>(.*?)</a>#s', $tr, $mt)) continue;
        $title = trim(preg_replace('/\s+/u', ' ',
            html_entity_decode(strip_tags($mt[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $title = trim($title, "“”\"„ .");
        if ($title === '') continue;
        // Tikai izpētes/apspriedes (nevis formālie konkursi = IUB/TED dublikāti).
        if (!preg_match('/^(Tirgus\s+(cenu\s+)?izpēte|Apspriede)/ui', $title)) continue;

        $buyer = preg_match('#views-field-field-uznemums[^>]*>\s*([^<]+)#s', $tr, $mb)
            ? trim(html_entity_decode($mb[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : 'VAS "Latvijas dzelzceļš"';
        $dl = null; $tm = null;
        if (preg_match('#views-field-field-iesniegsanas-termins.*?content="(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})#s', $tr, $md)) {
            $dl = $md[1];
            if ($md[2] !== '23:59' && $md[2] !== '00:00') $tm = $md[2]; // 23:59 = dienas beigu sentinel
        }
        $href = html_entity_decode($mt[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url  = str_starts_with($href, 'http') ? $href : LDZ_BASE_URL . $href;
        // id = slug (dekodēts, garuma dēļ apgriezts + jaucējs unikalitātei)
        $slug = rawurldecode(rtrim($href, '/'));
        $slug = substr($slug, strrpos($slug, '/') + 1);
        $id   = 'LDZ-' . substr(preg_replace('/[^a-z0-9]+/i', '-', $slug), 0, 60) . '-' . substr(md5($href), 0, 6);
        $rows[] = [
            'id' => $id, 'url' => $url, 'title' => $title,
            'buyer' => $buyer, 'deadline' => $dl, 'deadline_time' => $tm,
        ];
    }
    return $rows;
}

/** @return int importēto (jauno + versiju) skaits */
function ks_sync_ldz(PDO $pdo): int {
    $writer = ks_upsert_stmt($pdo);
    $pubSt = $pdo->prepare("SELECT publication_date FROM notices WHERE id = ? AND source = 'LDZ'");

    $html = ks_http_get(LDZ_LIST_URL);
    if ($html === null) { ks_log('  ⚠ LDZ: saraksts nav pieejams.'); return 0; }
    $rows = ldz_parse_list($html);
    if (!$rows) { ks_log('  · LDZ: sarakstā nav izpēšu/apspriežu (tikai formālie konkursi).'); return 0; }

    // Termiņš vienmēr sarakstā; retention pēc tā (arhīvu nerāda).
    $edge = date('Y-m-d', strtotime('-' . KONKURSI_KEEP_EXPIRED_DAYS . ' days'));
    $rows = array_filter($rows, fn($r) => $r['deadline'] !== null && $r['deadline'] >= $edge);

    $imported = 0;
    foreach ($rows as $r) {
        if (ks_stop_requested()) break;
        $pubSt->execute([$r['id']]);
        $known = $pubSt->fetch(PDO::FETCH_ASSOC);
        $pubSt->closeCursor();
        // LDz sarakstā publikācijas datuma nav → pirmoreiz fiksē šodienu, versijās pārnes.
        $pub = $known === false ? konkursi_today() : (($known['publication_date'] ?? null) ?: konkursi_today());
        $isApspriede = stripos($r['title'], 'Apspriede') === 0;

        $res = $writer->execute([
            'id'               => $r['id'],
            'source'           => 'LDZ',
            'category'         => 'iepirkumi',
            'title'            => $r['title'],
            'description'      => null,
            'buyer_name'       => $r['buyer'],
            'buyer_id'         => null,
            'buyer_country'    => 'LV',
            'buyer_activity'   => 'rail',
            'buyer_type'       => null,
            'procure_nature'   => null,
            'publication_date' => $pub,
            'deadline_date'    => $r['deadline'],
            'deadline_time'    => $r['deadline_time'],
            'publication_number' => null,
            'budget'           => null,
            'currency'         => null,
            'document_url'     => $r['url'],
            'buyer_profile_url' => null,
            'procedure_type'   => $isApspriede ? 'Apspriede pirms iepirkuma' : 'Tirgus izpēte (ārpus PIL)',
            'notice_sub_type'  => null,
            'notice_lang'      => 'LV',
            'issue_date'       => null,
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
            'source_file'      => 'ldz',
        ]);
        if ($res !== 'unchanged') $imported++;
    }
    ks_log('  · LDZ: izpētes/apspriedes ' . count($rows) . ", {$writer->summary()}.");
    return $imported;
}
