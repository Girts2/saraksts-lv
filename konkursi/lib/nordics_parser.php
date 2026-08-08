<?php
/**
 * konkursi/lib/nordics_parser.php — FI (Hilma), NO (Doffin), DK (udbud.dk) parseri.
 *
 * Katrs *_parse_item() saņem vienu API atbildes elementu un atgriež notices
 * rindu vai null (ja paziņojums dublējas TED plūsmā vai nav izmantojams).
 * EE (RHR) atsevišķa parsera nav — Igaunijas dumps ir eForms XML, to apstrādā
 * esošais ted_parse_xml() (skat. ks_sync_rhr sync_engine.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php'; // ted_truncate(), ted_norm_date()

/** ISO datums-laiks → [YYYY-MM-DD, HH:MM] (vai [null, null]). */
function nord_iso_dt(?string $s): array {
    if (!is_string($s) || $s === '') return [null, null];
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:T(\d{2}:\d{2}))?/', trim($s), $m)) return [null, null];
    return [$m[1], $m[2] ?? null];
}

/** 'CPV1 CPV2 …' vai 'CPV1, CPV2' → masīvs ar derīgiem 8 ciparu kodiem. */
function nord_cpv_list(?string $s): array {
    if (!is_string($s)) return [];
    $out = [];
    foreach (preg_split('/[\s,;]+/', $s) ?: [] as $t) {
        if (preg_match('/^(\d{8})/', trim($t), $m)) $out[] = $m[1];
    }
    return array_values(array_unique($out));
}

// ── FI: Hilma (eformnotices indekss) ──────────────────────────────────────────

/** @return array<string,mixed>|null */
function hilma_parse_item(array $it): ?array {
    if (($it['isNationalProcurement'] ?? false) !== true) return null; // ES līmenis → TED
    if (($it['isPlan'] ?? false) === true) return null;               // plāni nav paziņojumi
    $id = (string)($it['id'] ?? '');
    if ($id === '') return null;

    [$dlDate, $dlTime] = nord_iso_dt($it['deadline'] ?? ($it['expirationDate'] ?? null));

    $mainType = (string)($it['mainType'] ?? '');
    // Grozīts, BET joprojām atvērts izsludinājums paliek 'iepirkumi': Somijā ~40
    // procedūrām grozījums ir vienīgais paziņojums ar nākotnes termiņu, un ciļņā
    // "Grozījumi" tās pazustu no piesakāmo konkursu saraksta.
    $stillOpen = $mainType === 'ContractNotices' && $dlDate !== null && $dlDate >= konkursi_today();
    // Tipu pārbauda PIRMS grozījuma pazīmes: grozīts piešķīrums ir rezultāts un
    // grozīts PIN joprojām ir tikai iepriekšējs info — ne piesakāms konkurss.
    // Citādi tie nokļūtu 'izmainas' ar nākotnes expirationDate, un centrālā
    // ks_recategorize_open() tos uzskatītu par atvērtiem konkursiem.
    if (($it['isCancelled'] ?? false) === true) $category = 'citi';
    elseif (str_contains($mainType, 'Award') || str_contains($mainType, 'DirectPurchase')) $category = 'rezultati';
    elseif (str_contains($mainType, 'Prior')) $category = 'citi';
    elseif (($it['isCorrigendum'] ?? false) === true) $category = $stillOpen ? 'iepirkumi' : 'izmainas';
    else $category = 'iepirkumi';

    $pick = function (string $base) use ($it): ?string {
        foreach (['Fi', 'Sv', 'En', 'Other'] as $sfx) {
            $v = $it[$base . $sfx] ?? null;
            if (is_string($v) && trim($v) !== '') return trim($v);
        }
        return null;
    };
    $title = $pick('title');
    $buyer = $pick('organisationName');
    if ($title === null) return null;

    [$pubDate] = nord_iso_dt($it['datePublished'] ?? null);
    $cpv = nord_cpv_list($it['cpvCodes'] ?? null);

    $budget = null;
    if (isset($it['estimatedValue']) && is_numeric($it['estimatedValue']) && (float)$it['estimatedValue'] > 0) {
        $budget = (float)$it['estimatedValue'];
    }

    // Publiskā Hilma lapa (kanoniskā saite)
    $hilmaUrl = null;
    if (!empty($it['procedureId']) && !empty($it['noticeId'])) {
        $hilmaUrl = 'https://www.hankintailmoitukset.fi/fi/public/procurement/'
            . (int)$it['procedureId'] . '/notice/' . (int)$it['noticeId'] . '/overview';
    }
    $docsUrl = is_string($it['procurementDocumentsUrl'] ?? null) && trim((string)$it['procurementDocumentsUrl']) !== ''
        ? trim((string)$it['procurementDocumentsUrl']) : null;

    $nuts = is_string($it['organisationNutsCode'] ?? null) ? trim((string)$it['organisationNutsCode']) : '';

    return [
        'id'                 => 'HILMA-' . $id,
        'source'             => 'HILMA',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate($pick('description'), KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer ?? 'Nezināms pasūtītājs', 300),
        'buyer_id'           => is_string($it['organisationNationalRegistrationNumber'] ?? null) ? $it['organisationNationalRegistrationNumber'] : null,
        'buyer_country'      => 'FI',
        'buyer_activity'     => null,
        'buyer_type'         => is_string($it['organisationType'] ?? null) ? $it['organisationType'] : null,
        'procure_nature'     => in_array($it['procurementTypeCode'] ?? '', ['works', 'supplies', 'services'], true) ? $it['procurementTypeCode'] : null,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => is_string($it['noticeNumber'] ?? null) && $it['noticeNumber'] !== '' ? $it['noticeNumber'] : $id,
        'budget'             => $budget,
        'currency'           => is_string($it['currency'] ?? null) && $it['currency'] !== '' ? $it['currency'] : 'EUR',
        'document_url'       => $hilmaUrl,
        'buyer_profile_url'  => $docsUrl,
        'procedure_type'     => is_string($it['procedureType'] ?? null) && $it['procedureType'] !== '' ? $it['procedureType'] : null,
        'notice_sub_type'    => is_string($it['type'] ?? null) ? $it['type'] : null,
        'notice_lang'        => 'FI',
        'issue_date'         => $pubDate,
        'main_nuts'          => $nuts !== '' ? $nuts : null,
        'main_country'       => 'FI',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => is_string($it['contractFolderId'] ?? null) ? $it['contractFolderId'] : null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter([
                                    'name' => $buyer, 'country' => 'FI',
                                    'reg_number' => $it['organisationNationalRegistrationNumber'] ?? null,
                                ])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'hilma-api',
    ];
}

// ── NO: Doffin ────────────────────────────────────────────────────────────────

/** @return array<string,mixed>|null (null arī tad, ja sentToTed — dublējas TED) */
function doffin_parse_item(array $it): ?array {
    if (($it['sentToTed'] ?? false) === true) return null;
    $id = (string)($it['id'] ?? '');
    $title = trim((string)($it['heading'] ?? ''));
    if ($id === '' || $title === '') return null;

    $primary = strtoupper((string)($it['type'] ?? ''));
    $types = implode(' ', array_merge([$primary], (array)($it['allTypes'] ?? [])));
    $status = strtoupper((string)($it['status'] ?? ''));
    if ($status === 'CANCELLED' || str_contains($types, 'CANCELLATION')) $category = 'citi';
    elseif ($status === 'AWARDED' || str_contains($types, 'RESULT') || str_contains($types, 'AWARD')) $category = 'rezultati';
    // PRIMĀRAIS tips izšķir pirms allTypes maisījuma: ~10 atvērtiem konkursiem
    // allTypes ir ['PLANNING','COMPETITION','ANNOUNCEMENT_OF_COMPETITION',
    // 'NOTICE_ON_BUYER_PROFILE'], un PLANNING pārbaude tos aizsūtīja uz 'citi',
    // kaut type='ANNOUNCEMENT_OF_COMPETITION' un termiņš vēl nav pagājis.
    elseif (str_contains($primary, 'COMPETITION')) $category = 'iepirkumi';
    elseif (str_contains($types, 'PLANNING') || str_contains($types, 'PLAN')) $category = 'citi';
    elseif (str_contains($types, 'COMPETITION')) $category = 'iepirkumi';
    else $category = 'citi';

    $buyerNames = array_values(array_filter(array_map(
        fn($b) => is_array($b) ? trim((string)($b['name'] ?? '')) : '',
        (array)($it['buyer'] ?? [])
    ), fn($n) => $n !== ''));
    $buyerId = null;
    foreach ((array)($it['buyer'] ?? []) as $b) {
        if (is_array($b) && !empty($b['organizationId'])) { $buyerId = (string)$b['organizationId']; break; }
    }

    [$dlDate, $dlTime] = nord_iso_dt($it['deadline'] ?? null);
    $pubDate = ted_norm_date(is_string($it['publicationDate'] ?? null) ? $it['publicationDate'] : null)
        ?? nord_iso_dt($it['issueDate'] ?? null)[0];

    $budget = null;
    if (isset($it['estimatedValue']) && is_numeric($it['estimatedValue']) && (float)$it['estimatedValue'] > 0) {
        $budget = (float)$it['estimatedValue'];
    }
    $nuts = implode(' ', array_filter((array)($it['locationId'] ?? []), 'is_string'));

    return [
        'id'                 => 'DOFFIN-' . $id,
        'source'             => 'DOFFIN',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(is_string($it['description'] ?? null) ? $it['description'] : null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyerNames[0] ?? 'Nezināms pasūtītājs', 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'NO',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => null, // Doffin saraksta API CPV/veidu nedod
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $id,
        'budget'             => $budget,
        'currency'           => 'NOK',
        'document_url'       => sprintf(DOFFIN_NOTICE_URL_FMT, rawurlencode($id)),
        'buyer_profile_url'  => null,
        'procedure_type'     => null,
        'notice_sub_type'    => is_string($it['type'] ?? null) ? $it['type'] : null,
        'notice_lang'        => 'NO',
        'issue_date'         => $pubDate,
        'main_nuts'          => $nuts !== '' ? ted_truncate($nuts, 40) : null,
        'main_country'       => 'NO',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode(array_map(
                                    fn($n) => ['name' => $n, 'country' => 'NO'],
                                    array_slice($buyerNames, 0, KONKURSI_MAX_ORGS)
                                ), JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'doffin-api',
    ];
}

// ── DK: udbud.dk ──────────────────────────────────────────────────────────────

/** @return array<string,mixed>|null — elements ir {noticeId, noticeVersion, dataDa:{…}} */
function udbud_parse_item(array $el): ?array {
    $noticeId = (string)($el['noticeId'] ?? '');
    $d = is_array($el['dataDa'] ?? null) ? $el['dataDa'] : (is_array($el['dataEn'] ?? null) ? $el['dataEn'] : null);
    if ($noticeId === '' || $d === null) return null;
    $title = trim((string)($d['titel'] ?? ''));
    if ($title === '') return null;

    $ftype = strtolower((string)($d['formulartypeKode'] ?? ''));
    if (($d['erAendring'] ?? false) === true) $category = 'izmainas';
    elseif (str_contains($ftype, 'result')) $category = 'rezultati';
    elseif (str_contains($ftype, 'plan') || str_contains($ftype, 'prior')) $category = 'citi';
    else $category = 'iepirkumi';

    // '16-07-2026' → '2026-07-16'
    $pubDate = null;
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', trim((string)($d['publiceringsdato'] ?? '')), $m)) {
        $pubDate = $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    // tuvākais nākotnes termiņš no tidsfrister (ISO saraksts)
    $dlDate = null; $dlTime = null;
    foreach ((array)($d['tidsfrister'] ?? []) as $t) {
        [$td, $tt] = nord_iso_dt(is_string($t) ? $t : null);
        if ($td !== null && ($dlDate === null || $td < $dlDate)) { $dlDate = $td; $dlTime = $tt; }
    }

    $budget = null;
    $rawVal = (string)($d['anslaaetVaerdi'] ?? '');
    if ($rawVal !== '' && is_numeric($rawVal) && (float)$rawVal > 0) $budget = (float)$rawVal;

    $cpv = nord_cpv_list((string)($d['cpvKode'] ?? ''));
    $pubNum = (string)($el['noticePublicationNumber'] ?? '');
    $version = (string)($el['noticeVersion'] ?? '01');

    return [
        'id'                 => 'UDBUD-' . $noticeId,
        'source'             => 'UDBUD',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(is_string($d['beskrivelse'] ?? null) ? $d['beskrivelse'] : null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate((string)($d['ordregiver'] ?? 'Nezināms pasūtītājs'), 300),
        'buyer_id'           => is_string($d['ordregiverId'] ?? null) && $d['ordregiverId'] !== '' ? $d['ordregiverId'] : null,
        'buyer_country'      => 'DK',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => null,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $pubNum !== '' ? $pubNum : strtoupper(substr($noticeId, 0, 8)),
        'budget'             => $budget,
        'currency'           => is_string($d['anslaaetVaerdiValuta'] ?? null) && $d['anslaaetVaerdiValuta'] !== '' ? $d['anslaaetVaerdiValuta'] : 'DKK',
        'document_url'       => sprintf(UDBUD_NOTICE_URL_FMT, rawurlencode($noticeId), rawurlencode($version)),
        'buyer_profile_url'  => null,
        'procedure_type'     => null,
        'notice_sub_type'    => is_string($d['bkSubTypeKode'] ?? null) ? $d['bkSubTypeKode'] : null,
        'notice_lang'        => 'DA',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'DK',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter([
                                    'name' => $d['ordregiver'] ?? null, 'country' => 'DK',
                                    'reg_number' => $d['ordregiverId'] ?? null,
                                ])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'udbud-api',
    ];
}

// ── DK: Comdia (Dānijas pašvaldību platforma) ────────────────────────────────

/**
 * Comdia konkursa rinda. Apzināti minimāla: avots anonīmam apmeklētājam dod
 * TIKAI nosaukumu, pasūtītāju un saiti — ne datumus, ne CPV, ne vērtību.
 *
 * Termiņu te NEIZDOMĀ. Udbudsloven zem sliekšņa fiksētu termiņu nenosaka
 * ("passende frist"), tāpēc jebkurš pieņēmums (piem. "30 dienas") būtu minējums,
 * ko lietotājs uztvertu kā faktu un plānotu pēc tā. Tukšs lauks ir godīgs;
 * nepareizs datums ir sliktāks par tukšu. Vai konkurss vēl ir atvērts, izšķir
 * ks_sync_comdia pēc detaļu lapas statusa, nevis pēc datuma.
 *
 * @param string $slug  organizācijas ceļa daļa (piem. 'aalborg-kommune')
 * @param string $org   pasūtītāja nosaukums (piem. 'Aalborg Kommune')
 * @return array<string,mixed>|null
 */
function comdia_parse_item(string $slug, string $id, string $title, string $org): ?array {
    $title = trim($title);
    if ($id === '' || $title === '') return null;
    // Comdia atceltos atzīmē ar sufiksu pašā nosaukumā
    if (preg_match('/\s[-–]\s*Cancelled\s*$/i', $title)) return null;

    return [
        'id'                 => 'COMDIA-' . $id,
        'source'             => 'COMDIA',
        'category'           => 'iepirkumi',
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($org !== '' ? $org : 'Nezināms pasūtītājs', 300),
        'buyer_id'           => null,
        'buyer_country'      => 'DK',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => null,
        'publication_date'   => null,  // avotā nav — sk. faila komentāru
        'deadline_date'      => null,  // avotā nav — NEIZDOMĀT
        'deadline_time'      => null,
        'publication_number' => $id,
        'budget'             => null,
        'currency'           => 'DKK',
        'document_url'       => sprintf(COMDIA_DETAIL_FMT, $slug, $id),
        'buyer_profile_url'  => sprintf(COMDIA_LIST_FMT, $slug),
        'procedure_type'     => null,
        'notice_sub_type'    => null,
        'notice_lang'        => 'DA',
        'issue_date'         => null,
        'main_nuts'          => null,
        'main_country'       => 'DK',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([['name' => $org, 'country' => 'DK']], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'comdia:' . $slug,
    ];
}

// ── SE: KommersAnnons (Zviedrijas reģistrētā sludinājumu datubāze) ───────────

/**
 * Detaļu lapas 'label → value' pāri. Marķējums ir vienveidīgs:
 *   <span class="label">Nosaukums</span><span class="text">: </span><span class="value">Vērtība</span>
 * @return array<string,string[]> etiķete => visas vērtības (secībā)
 */
function kommers_fields(string $html): array {
    $out = [];
    // Grupas NEDRĪKST šķērsot tagus: ar '(.*?)' un /s regex pārlec pāri <span>
    // robežām un salipina attālas etiķetes ('Värde … Beräknat värde'), pa ceļam
    // apēdot īstos laukus — tā pazuda termiņš un vērtība.
    if (!preg_match_all(
        '#<span class="label">([^<]*)</span>\s*<span class="text">[^<]*</span>\s*<span class="(?:value|dynamic-label)">([^<]*)</span>#',
        $html, $m, PREG_SET_ORDER
    )) return $out;
    foreach ($m as $x) {
        $k = trim(html_entity_decode(strip_tags($x[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $v = trim(preg_replace('#\s+#u', ' ',
             html_entity_decode(strip_tags($x[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($k !== '' && $v !== '') $out[$k][] = $v;
    }
    return $out;
}

/** Pirmā vērtība pēc etiķetes vai null. */
function kommers_val(array $f, string $key): ?string {
    return isset($f[$key][0]) ? $f[$key][0] : null;
}

/**
 * KommersAnnons paziņojums.
 *
 * Saraksta lapa dod tikai id, nosaukumu un publicēšanas datumu; pasūtītājs,
 * termiņš, CPV, NUTS un vērtība nāk no detaļu lapas (eForms struktūra).
 * Termiņš tur ir DD/MM/YYYY, nevis ISO.
 *
 * @param string $kind  'TenderNotice' | 'AwardNotice' | 'PriorInfoNotice'
 * @param string $detail detaļu lapas HTML
 * @return array<string,mixed>|null
 */
function kommers_parse_item(string $kind, string $id, string $listTitle, ?string $pubDate, string $detail): ?array {
    $f = kommers_fields($detail);

    $title = kommers_val($f, 'Titel') ?? trim($listTitle);
    // Saraksta nosaukumam priekšā ir pasūtītāja atsauces numurs ('A674.859/2025 - ')
    $ref = null;
    if (preg_match('#^\s*([\w./-]{2,24})\s+-\s+#u', $listTitle, $rm)) $ref = $rm[1];
    if ($title === '') return null;

    $category = match ($kind) {
        'AwardNotice'     => 'rezultati',
        'PriorInfoNotice' => 'citi',
        default           => 'iepirkumi',
    };

    // 'Tidsfrist för mottagande av anbud' → DD/MM/YYYY
    $dlDate = null;
    $rawDl = kommers_val($f, 'Tidsfrist för mottagande av anbud')
          ?? kommers_val($f, 'Tidsfrist för mottagande av anbudsansökningar');
    if ($rawDl !== null && preg_match('#(\d{2})/(\d{2})/(\d{4})#', $rawDl, $dm)) {
        $dlDate = $dm[3] . '-' . $dm[2] . '-' . $dm[1];
    }

    $cpv = nord_cpv_list(implode(' ', $f['Huvudsaklig klassificering (cpv)'] ?? []));
    if (!$cpv) $cpv = array_values(array_unique(preg_match_all('#\b(\d{8})\b#', $detail, $cm) ? $cm[1] : []));

    $budget = null;
    $rawVal = kommers_val($f, 'Beräknat värde exklusive moms');
    if ($rawVal !== null) {
        $n = (float)preg_replace('#[^\d.]#', '', str_replace(',', '.', preg_replace('#\s#u', '', $rawVal)));
        if ($n > 0) $budget = $n;
    }

    $buyer = kommers_val($f, 'Officiellt namn') ?? 'Nezināms pasūtītājs';

    return [
        'id'                 => 'KOMMERS-' . $id,
        'source'             => 'KOMMERS',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(kommers_val($f, 'Beskrivning'), KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => kommers_val($f, 'Registreringsnummer'),
        'buyer_country'      => 'SE',
        'buyer_activity'     => null,
        'buyer_type'         => kommers_val($f, 'Köparens rättsliga status'),
        'procure_nature'     => match (kommers_val($f, 'Typ av kontrakt')) {
                                    'Byggentreprenader' => 'works',
                                    'Varor'             => 'supplies',
                                    'Tjänster'          => 'services',
                                    default             => null,
                                },
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => null,
        'publication_number' => $ref ?? $id,
        'budget'             => $budget,
        'currency'           => 'SEK',
        'document_url'       => sprintf(KOMMERS_DETAIL_FMT, $kind, $id),
        'buyer_profile_url'  => null,
        'procedure_type'     => kommers_val($f, 'Förfarande'),
        'notice_sub_type'    => $kind,
        'notice_lang'        => 'SV',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'SE',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([['name' => $buyer, 'country' => 'SE']], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'kommersannons',
    ];
}

// ── IS: Útboðsvefur (Islandes vienotais sludinājumu dēlis) ───────────────────

/**
 * Islandes datums 'DD.MM.YYYY kl. HH:MM' → [YYYY-MM-DD, HH:MM].
 */
function isutb_dt(?string $s): array {
    if (!is_string($s) || !preg_match('#(\d{2})\.(\d{2})\.(\d{4})(?:\s*kl\.\s*(\d{2}:\d{2}))?#u', $s, $m)) {
        return [null, null];
    }
    return [$m[3] . '-' . $m[2] . '-' . $m[1], $m[4] ?? null];
}

/**
 * Útboðsvefur konkursa lapa. Dati ir tīrā tabulā <div class="content-details">:
 *   Númer | Útboðsaðili (pasūtītājs) | Tegund (veids) | Skilafrestur (termiņš)
 *
 * CPV kodu avotā nav — Islandes portāls tos nepublicē.
 *
 * @return array<string,mixed>|null
 */
function isutb_parse_item(string $url, string $html): ?array {
    // Lapā ir VAIRĀKI <h1> (vietnes galvene + konkursa nosaukums), tāpēc meklē
    // tikai satura blokā — citādi virsrakstā nonāk 'Útboðsvefur.is - Opinber útboð'.
    $body = preg_match('#<div class="content-text">(.*?)</table>#s', $html, $bm) ? $bm[1] : $html;
    $title = preg_match('#<h1>(.*?)</h1>#s', $body, $tm)
        ? trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    if ($title === '') return null;

    // Lauku tabula: <td class="title">Etiķete</td><td>Vērtība</td>
    $f = [];
    if (preg_match_all('#<td class="title">(.*?)</td>\s*<td>(.*?)</td>#s', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $k = rtrim(trim(html_entity_decode(strip_tags($x[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), ':');
            $v = trim(preg_replace('#\s+#u', ' ',
                 html_entity_decode(strip_tags($x[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($k !== '' && $v !== '') $f[$k] = $v;
        }
    }
    if (!$f) return null;

    $kind = $f['Tegund'] ?? '';
    // Forauglýsing = iepriekšējs paziņojums, Markaðskönnun/RFI = tirgus izpēte,
    // VEAT = caurskatāmības paziņojums — neviens no tiem nav piesakāms konkurss.
    $category = (str_contains($kind, 'Forauglýsing') || str_contains($kind, 'Markaðskönnun')
              || str_contains($kind, 'RFI') || str_contains($kind, 'VEAT')) ? 'citi' : 'iepirkumi';

    $nature = match (true) {
        str_contains($kind, 'Framkvæmd') => 'works',
        str_contains($kind, 'Vörukaup')  => 'supplies',
        str_contains($kind, 'Þjónusta')  => 'services',
        default                          => null,
    };

    [$dlDate, $dlTime] = isutb_dt($f['Skilafrestur'] ?? null);
    [$pubDate] = isutb_dt($f['Útboðsgögn afhent'] ?? null);

    // Apraksts: pirmās rindkopas aiz lauku tabulas
    $desc = null;
    if (preg_match('#</table>\s*</div>(.*?)(?:<div|<footer|</article)#s', $html, $dm)) {
        $desc = trim(preg_replace('#\s+#u', ' ',
                html_entity_decode(strip_tags($dm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    $slug = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
    $buyer = $f['Útboðsaðili'] ?? 'Nezināms pasūtītājs';

    return [
        'id'                 => 'ISUTB-' . $slug,
        'source'             => 'ISUTB',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate($desc !== '' ? $desc : null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'IS',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $f['Númer'] ?? null,
        'budget'             => null,
        'currency'           => 'ISK',
        'document_url'       => $url,
        'buyer_profile_url'  => null,
        'procedure_type'     => $kind !== '' ? $kind : null,
        'notice_sub_type'    => $kind !== '' ? $kind : null,
        'notice_lang'        => 'IS',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'IS',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([['name' => $buyer, 'country' => 'IS']], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'utbodsvefur',
    ];
}
