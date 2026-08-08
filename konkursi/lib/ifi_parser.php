<?php
/**
 * konkursi/lib/ifi_parser.php — Starptautisko finanšu institūciju (IFI) parseri.
 *
 * Pasaules Banka (procnotices JSON) un EBRD (ECEPP HTML) → notices rindas. Sedz
 * AZ/AM/GE/TR, kur nacionālā plūsma nav pieejama. Tas ir donoru FINANSĒTO projektu
 * iepirkums, ne nacionālais — bet vienīgais reālais saturs šīm valstīm.
 *
 * GDPR: WB atgriež contact_name/email/phone → ks_public_org (vārds+tālr. ārā vienmēr,
 * e-pasts tikai iestādes adresei). EBRD saraksts personas datus nesniedz.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php';   // ted_truncate

// ─────────────────────────── Pasaules Banka ──────────────────────────────────

/** WB procurement_group → mūsu procure_nature. */
function wb_nature(?string $g): ?string
{
    return match (strtoupper(trim((string)$g))) {
        'CW'       => 'works',       // Civil Works
        'GO'       => 'supplies',    // Goods
        'CS', 'NC' => 'services',    // Consulting / Non-consulting Services
        default    => null,
    };
}

/** 'DD-Mon-YYYY' vai ISO 'YYYY-MM-DDThh:mm:ssZ' → 'YYYY-MM-DD' vai null. */
function wb_date(?string $s): ?string
{
    $s = trim((string)$s);
    if ($s === '') return null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {                // ISO
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? "$m[1]-$m[2]-$m[3]" : null;
    }
    $d = DateTime::createFromFormat('d-M-Y', $s);
    return $d ? $d->format('Y-m-d') : null;
}

/**
 * WB procnotices ieraksts → notices rinda vai null. $cc = mūsu ISO2 (no cikla).
 */
function wb_parse_notice(array $r, string $cc): ?array
{
    $id = trim((string)($r['id'] ?? ''));
    if ($id === '') return null;

    $type = trim((string)($r['notice_type'] ?? ''));
    $isAward = stripos($type, 'award') !== false;
    $category = $isAward ? 'rezultati' : 'iepirkumi';

    $title = trim((string)($r['bid_description'] ?? '')) ?: trim((string)($r['project_name'] ?? ''));
    if ($title === '') return null;

    $pub = wb_date($r['noticedate'] ?? null);
    $dl  = wb_date($r['submission_deadline_date'] ?? null);

    // GDPR: kontaktpersonas vārds+tālrunis ārā; e-pasts tikai iestādes adresei.
    $org = ks_public_org([
        'name'    => trim((string)($r['contact_organization'] ?? '')) ?: trim((string)($r['project_name'] ?? '')),
        'country' => $cc,
        'email'   => trim((string)($r['contact_email'] ?? '')) ?: null,
        'contact' => trim((string)($r['contact_name'] ?? '')) ?: null,
        'phone'   => trim((string)($r['contact_phone_no'] ?? '')) ?: null,
    ]);
    $buyer = (string)($org['name'] ?? 'Pasaules Bankas projekts');

    $dlt = trim((string)($r['submission_deadline_time'] ?? ''));

    return [
        'id'                 => 'WB-' . $id,
        'source'             => 'WB',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(trim((string)($r['project_name'] ?? '')) ?: null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => ted_truncate((string)($r['project_id'] ?? ''), 40) ?: null,
        'buyer_country'      => $cc,
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => wb_nature($r['procurement_group'] ?? null),
        'publication_date'   => $pub,
        'deadline_date'      => $category === 'iepirkumi' ? $dl : null,
        'deadline_time'      => $category === 'iepirkumi' && preg_match('/^\d{1,2}:\d{2}/', $dlt) ? substr($dlt, 0, 5) : null,
        'publication_number' => ted_truncate((string)($r['bid_reference_no'] ?? ''), 40) ?: null,
        'budget'             => null,   // WB sarakstā vērtības nav
        'currency'           => null,
        'document_url'       => sprintf(WB_NOTICE_URL_FMT, rawurlencode($id)),
        'buyer_profile_url'  => null,
        'procedure_type'     => ted_truncate((string)($r['procurement_method_name'] ?? ''), 80) ?: null,
        'notice_sub_type'    => ted_truncate($type, 40) ?: null,
        'notice_lang'        => 'EN',
        'issue_date'         => $pub,
        'main_nuts'          => $cc,
        'main_country'       => $cc,
        'funding_program'    => 'World Bank',
        'prev_notice_ref'    => null,
        'contract_folder_id' => ted_truncate((string)($r['project_id'] ?? ''), 60) ?: null,
        'main_cpv'           => null,   // WB izmanto savu klasifikāciju, ne CPV
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter($org, fn($v) => $v !== null && $v !== '')], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'worldbank-api',
    ];
}

// ─────────────────────────────── EBRD ECEPP ──────────────────────────────────

/** EBRD iekavu masīva vai tipa teksts → procure_nature. */
function ebrd_nature(string $blob): ?string
{
    $b = strtolower($blob);
    if (str_contains($b, 'consult'))                       return 'services';
    if (str_contains($b, 'civil work') || str_contains($b, 'works')) return 'works';
    if (str_contains($b, 'goods') || str_contains($b, 'supply'))     return 'supplies';
    return null;
}

/**
 * Sadala EBRD iekavu masīvu pa komatiem, kas NAV iekavās vai pēdiņās — klienta
 * nosaukums bieži satur iekšēju komatu (piem. 'Iller Bankasi A.S. ("ILBANK", TR)').
 * Baitu līmenī droši: pārbauda tikai ASCII (,"()), UTF-8 baiti >127 nekad nesakrīt.
 */
function ebrd_split_bracket(string $s): array
{
    $s = trim($s, "[] \t");
    $parts = []; $cur = ''; $depth = 0; $inq = false;
    for ($i = 0, $len = strlen($s); $i < $len; $i++) {
        $ch = $s[$i];
        if ($ch === '"') $inq = !$inq;
        elseif ($ch === '(') $depth++;
        elseif ($ch === ')') $depth = max(0, $depth - 1);
        if ($ch === ',' && $depth === 0 && !$inq) { $parts[] = trim($cur); $cur = ''; }
        else $cur .= $ch;
    }
    if (trim($cur) !== '') $parts[] = trim($cur);
    return $parts;
}

/** 'DD/MM/YYYY' (+ ' HH:MM') → ['YYYY-MM-DD', 'HH:MM'|null]. */
function ebrd_date(string $s): array
{
    if (!preg_match('#(\d{2})/(\d{2})/(\d{4})(?:\s+(\d{2}:\d{2}))?#', $s, $m)) return [null, null];
    if (!checkdate((int)$m[2], (int)$m[1], (int)$m[3])) return [null, $m[4] ?? null];  // DD/MM/YYYY
    return [$m[3] . '-' . $m[2] . '-' . $m[1], $m[4] ?? null];
}

/**
 * EBRD ECEPP meklēšanas HTML → notices rindas TIKAI EBRD_COUNTRIES valstīm.
 * Viss ir sarakstā (valsts title-prefiksā + pircējs iekavu masīvā) — detaļu
 * lapas nevajag.
 * @return array notices rindu masīvs
 */
function ebrd_parse_all(string $html): array
{
    $out = [];
    if (!preg_match_all('#<tr\b[^>]*>(.*?)</tr>#is', $html, $rows)) return $out;

    foreach ($rows[1] as $tr) {
        if (!preg_match('#viewNotice\.html\?displayNoticeId=(\d+)"[^>]*>([^<]+)</a>#i', $tr, $link)) continue;
        $nid = $link[1];
        $linkText = html_entity_decode(trim($link[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // "Valsts: Temats" — valsts pirms pirmā kola.
        $parts = explode(':', $linkText, 2);
        if (count($parts) < 2) continue;
        $countryName = trim($parts[0]);
        $cc = EBRD_COUNTRIES[$countryName] ?? null;
        if ($cc === null) continue;              // ne mūsu valsts — izlaiž
        $title = trim($parts[1]);
        if ($title === '') continue;

        // <td> šūnas
        preg_match_all('#<td\b[^>]*>(.*?)</td>#is', $tr, $tds);
        $cell = fn(int $i): string => isset($tds[1][$i])
            ? trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', ' ', $tds[1][$i])), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : '';

        $noticeType = $cell(1);                  // "Invitation For Tenders Single" u.c.
        [$pubDate]  = ebrd_date($cell(6) ?: $cell(3));
        [$dlDate, $dlTime] = ebrd_date($cell(4));
        $status = $cell(5);

        $isAward = stripos($noticeType, 'award') !== false || stripos($status, 'award') !== false;
        $category = $isAward ? 'rezultati' : 'iepirkumi';

        // Iekavu masīvs (pēdējā šūna): [..., klients, aģents, tips] — pircējs ir 3. no beigām.
        $blob = $cell(9);
        $buyer = 'EBRD projekts';
        if ($blob !== '') {
            $bp = ebrd_split_bracket($blob);   // [..., klients, aģents, tips]
            if (count($bp) >= 3) $buyer = $bp[count($bp) - 3] ?: $buyer;
        }

        $out[] = [
            'id'                 => 'EBRD-' . $nid,
            'source'             => 'EBRD',
            'category'           => $category,
            'title'              => ted_truncate($title, 400),
            'description'        => null,
            'buyer_name'         => ted_truncate($buyer, 300),
            'buyer_id'           => null,
            'buyer_country'      => $cc,
            'buyer_activity'     => null,
            'buyer_type'         => null,
            'procure_nature'     => ebrd_nature($noticeType . ' ' . $blob),
            'publication_date'   => $pubDate,
            'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
            'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
            'publication_number' => null,
            'budget'             => null,
            'currency'           => null,
            'document_url'       => sprintf(EBRD_NOTICE_URL_FMT, $nid),
            'buyer_profile_url'  => null,
            'procedure_type'     => ted_truncate($noticeType, 80) ?: null,
            'notice_sub_type'    => ted_truncate($noticeType, 40) ?: null,
            'notice_lang'        => 'EN',
            'issue_date'         => $pubDate,
            'main_nuts'          => $cc,
            'main_country'       => $cc,
            'funding_program'    => 'EBRD',
            'prev_notice_ref'    => null,
            'contract_folder_id' => null,
            'main_cpv'           => null,
            'cpv_codes'          => '[]',
            'lots'               => '[]',
            'organizations'      => json_encode([array_filter(['name' => $buyer, 'country' => $cc])], JSON_UNESCAPED_UNICODE),
            'notice_contact'     => '{}',
            'source_file'        => 'ebrd-ecepp',
        ];
    }
    return $out;
}

// ─────────────────────────────── UNDP ────────────────────────────────────────

/** UNDP valsts nosaukums (titula beigas) → ISO2 vai null (RU/BY/reģionālie izlaisti). */
function undp_country(string $name): ?string
{
    $u = strtoupper(trim($name));
    if (isset(UNDP_COUNTRIES[$u])) return UNDP_COUNTRIES[$u];
    // Saliktie nosaukumi ("Kosovo, UNSCR 1244 (1999)", "Republic of ...")
    foreach (UNDP_COUNTRIES as $key => $cc) {
        if (str_contains($u, $key)) return $cc;
    }
    return null;
}

/** "Application Deadline: 31 December 2026" / "31-Aug-26" → 'YYYY-MM-DD' vai null. */
function undp_deadline(string $desc): ?string
{
    if (!preg_match('/Application Deadline:\s*(.+?)\s*$/i', trim($desc), $m)) return null;
    $s = trim($m[1]);
    foreach (['j F Y', 'j-M-Y', 'j-M-y', 'd-M-Y', 'd-M-y', 'Y-m-d', 'd/m/Y'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $s);
        if ($d !== false) {
            $y = (int)$d->format('Y');
            if ($y >= 2000 && $y < 2100) return $d->format('Y-m-d');
        }
    }
    $ts = strtotime($s);
    return ($ts && (int)date('Y', $ts) >= 2000) ? date('Y-m-d', $ts) : null;
}

/**
 * UNDP RER (Europe & CIS) RSS 1.0/RDF → notices rindas mūsu-reģiona valstīm.
 * Personas datu nav (tikai title/apraksts/datums). @return array notices rindu masīvs
 */
function undp_parse_all(string $xml): array
{
    $out = [];
    if (!preg_match_all('#<item rdf:about=["\'][^"\']*["\']>(.*?)</item>#is', $xml, $items)) return $out;

    $get = fn(string $tag, string $body): string =>
        preg_match('#<' . $tag . '>(.*?)</' . $tag . '>#is', $body, $mm)
            ? trim(html_entity_decode($mm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';

    foreach ($items[1] as $body) {
        $title = $get('title', $body);
        $link  = $get('link', $body);
        if ($title === '' || $link === '') continue;

        $parts = array_map('trim', explode(' - ', $title));
        $cc = undp_country((string)end($parts));
        if ($cc === null) continue;                 // ne mūsu reģiona valsts / RU-BY

        // Notīra titulu: nost valsti un "UNDP" marķieri (bet ne "UNDP-XXX" atsauci).
        array_pop($parts);
        if ($parts && strcasecmp(end($parts), 'UNDP') === 0) array_pop($parts);
        $clean = implode(' - ', $parts) ?: $title;

        preg_match('#(?:notice_id|nego_id)=(\d+)#', $link, $idm);
        $isNego = str_contains($link, 'nego_id');
        $id = 'UNDP-' . ($isNego ? 'G' : 'N') . ($idm[1] ?? substr(md5($link), 0, 10));

        $desc = $get('description', $body);
        $dl = undp_deadline($desc);
        $date = $get('dc:date', $body);
        $pub = preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $pm) ? $pm[1] : null;

        $isAward = stripos($title, 'award') !== false || stripos($title, 'contract award') !== false;
        $category = $isAward ? 'rezultati' : 'iepirkumi';

        $out[] = [
            'id'                 => $id,
            'source'             => 'UNDP',
            'category'           => $category,
            'title'              => ted_truncate($clean, 400),
            'description'        => ted_truncate($desc ?: null, KONKURSI_DESC_MAX),
            'buyer_name'         => 'UNDP',
            'buyer_id'           => null,
            'buyer_country'      => $cc,
            'buyer_activity'     => null,
            'buyer_type'         => null,
            'procure_nature'     => ebrd_nature($clean),
            'publication_date'   => $pub,
            'deadline_date'      => $category === 'iepirkumi' ? $dl : null,
            'deadline_time'      => null,
            'publication_number' => null,
            'budget'             => null,
            'currency'           => null,
            'document_url'       => $link,
            'buyer_profile_url'  => null,
            'procedure_type'     => null,
            'notice_sub_type'    => $isNego ? 'Negotiation' : 'Notice',
            'notice_lang'        => 'EN',
            'issue_date'         => $pub,
            'main_nuts'          => $cc,
            'main_country'       => $cc,
            'funding_program'    => 'UNDP',
            'prev_notice_ref'    => null,
            'contract_folder_id' => null,
            'main_cpv'           => null,
            'cpv_codes'          => '[]',
            'lots'               => '[]',
            'organizations'      => json_encode([['name' => 'UNDP', 'country' => $cc]], JSON_UNESCAPED_UNICODE),
            'notice_contact'     => '{}',
            'source_file'        => 'undp-rss',
        ];
    }
    return $out;
}
