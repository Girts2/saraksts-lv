<?php
/**
 * konkursi/lib/balkan_parser.php — Rietumbalkānu (ne-ES) nacionālie avoti:
 * Bosnija (open.ejn.gov.ba OData), Ziemeļmaķedonija (e-nabavki DataTables),
 * Serbija (jnportal searchgrid), Melnkalne (CeJN API), Albānija (APP HTML).
 *
 * Visi importē TIKAI mazos/zem-sliekšņa konkursus, kas uz ES TED NEnonāk, tāpēc
 * atsevišķs dedup pret TED nav vajadzīgs.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php'; // ted_truncate()

/** ISO/OData datums → ['Y-m-d','H:i'] vai [null,null]. */
function balkan_dt(?string $iso): array {
    if (!is_string($iso) || $iso === '') return [null, null];
    try {
        $d = new DateTimeImmutable($iso);
        return [$d->format('Y-m-d'), $d->format('H:i')];
    } catch (Throwable $e) {
        return [null, null];
    }
}

// ─────────────────────────── Bosnija (open.ejn.gov.ba) ────────────────────────

/**
 * Viens NpsProcurementNotices OData ieraksts → notices rinda vai null.
 *
 * NPS = zem-sliekšņa mazie iepirkumi (nekad TED). CPV/vērtība paziņojumā nav —
 * kartē nosaukumu, pasūtītāju, pilsētu un termiņu. Kategoriju nosaka termiņš.
 */
function bosnia_nps_notice(array $x): ?array {
    $id = (int)($x['Id'] ?? 0);
    $title = trim((string)($x['NpsProcurementName'] ?? ''));
    if ($id <= 0 || $title === '') return null;

    [$dlDate, $dlTime] = balkan_dt($x['ApplicationDeadlineDateTime'] ?? null);
    [$pubDate] = balkan_dt($x['Announced'] ?? null);
    // Aktīvs = termiņš nākotnē; citādi rezultāts/arhīvs.
    $category = ($dlDate !== null && $dlDate >= konkursi_today()) ? 'iepirkumi' : 'rezultati';

    $buyer = trim((string)($x['ContractingAuthorityName'] ?? '')) ?: 'Nezināms pasūtītājs';
    $buyerId = trim((string)($x['ContractingAuthorityTaxNumber'] ?? ''));
    $city = trim((string)($x['ContractingAuthorityCityName'] ?? ''));
    $number = trim((string)($x['Number'] ?? ''));

    return [
        'id'                 => 'EJN-' . $id,
        'source'             => 'EJN',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(trim((string)($x['AdditionalInformation'] ?? '')) ?: null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $buyerId !== '' ? ted_truncate($buyerId, 60) : null,
        'buyer_country'      => 'BA',
        'buyer_activity'     => ted_truncate((string)($x['ContractingAuthorityActivityTypeName'] ?? ''), 120) ?: null,
        'buyer_type'         => ted_truncate((string)($x['ContractingAuthorityType'] ?? ''), 40) ?: null,
        'procure_nature'     => null,
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => ted_truncate($number, 40) ?: null,
        'budget'             => null,
        'currency'           => 'BAM',
        'document_url'       => EJN_VIEW_URL,
        'buyer_profile_url'  => null,
        'procedure_type'     => 'Jednostavna nabavka (NPS)',
        'notice_sub_type'    => 'NPS',
        'notice_lang'        => 'BS',
        'issue_date'         => $pubDate,
        'main_nuts'          => $city !== '' ? ted_truncate('BA ' . $city, 40) : 'BA',
        'main_country'       => 'BA',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => (string)$id,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'reg_number' => $buyerId ?: null, 'country' => 'BA'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'ejn-odata',
    ];
}

/** EJN ContractType → mūsu procure_nature. */
function bosnia_nature(?string $ct): ?string {
    return match ((string)$ct) {
        'Works'    => 'works',
        'Goods'    => 'supplies',
        'Services' => 'services',
        default    => null,
    };
}

/** EJN ProcedureType → cilvēklasāms nosaukums (virs-sliekšņa regulārās procedūras). */
function bosnia_proc(string $pt): string {
    return match ($pt) {
        'OpenProcedure'      => 'Atklāta procedūra',
        'CompetitiveRequest' => 'Konkurences pieprasījums',
        'RestrictedProcedure' => 'Slēgta procedūra',
        'NegotiatedProcedure', 'NegotiatedProcedureWithoutPublication' => 'Sarunu procedūra',
        'DesignContest'      => 'Metu konkurss',
        'CompetitiveDialogue' => 'Konkursa dialogs',
        default              => $pt !== '' ? $pt : 'Regulāra procedūra',
    };
}

/**
 * Viens ProcurementNotices OData ieraksts → notices rinda vai null.
 *
 * VIRS-SLIEKŠŅA regulārie konkursi (OpenProcedure/CompetitiveRequest u.c.). Bosnija nav
 * TED, tāpēc dedup nav vajadzīgs. Termiņš ir tieši laukā ApplicationDeadlineDateTime →
 * detaļu neprasa. Personas kontaktus (vārds/e-pasts/tālrunis) NEUZGLABĀJAM (GDPR).
 * ID prefikss EJN-PN- (atšķirīgs no NPS EJN-, lai Id nesaduras).
 */
function bosnia_open_notice(array $x): ?array {
    $id = (int)($x['Id'] ?? 0);
    $title = trim((string)($x['ProcedureName'] ?? ''));
    if ($id <= 0 || $title === '') return null;

    [$dlDate, $dlTime] = balkan_dt($x['ApplicationDeadlineDateTime'] ?? null);
    [$pubDate] = balkan_dt($x['Announced'] ?? null);
    $category = ($dlDate !== null && $dlDate >= konkursi_today()) ? 'iepirkumi' : 'rezultati';

    $buyer = trim((string)($x['ContractingAuthorityName'] ?? '')) ?: 'Nezināms pasūtītājs';
    $buyerId = trim((string)($x['ContractingAuthorityTaxNumber'] ?? ''));
    $city = trim((string)($x['ContractingAuthorityCityName'] ?? ''));
    $number = trim((string)($x['ProcedureNumber'] ?? ($x['Number'] ?? '')));
    $pt = trim((string)($x['ProcedureType'] ?? ''));

    return [
        'id'                 => 'EJN-PN-' . $id,
        'source'             => 'EJN',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $buyerId !== '' ? ted_truncate($buyerId, 60) : null,
        'buyer_country'      => 'BA',
        'buyer_activity'     => ted_truncate((string)($x['ContractingAuthorityActivityTypeName'] ?? ''), 120) ?: null,
        'buyer_type'         => ted_truncate((string)($x['ContractingAuthorityType'] ?? ''), 40) ?: null,
        'procure_nature'     => bosnia_nature($x['ContractType'] ?? null),
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => ted_truncate($number, 40) ?: null,
        'budget'             => null,
        'currency'           => 'BAM',
        'document_url'       => EJN_VIEW_URL,
        'buyer_profile_url'  => null,
        'procedure_type'     => bosnia_proc($pt),
        'notice_sub_type'    => ted_truncate($pt, 40) ?: null,
        'notice_lang'        => 'BS',
        'issue_date'         => $pubDate,
        'main_nuts'          => $city !== '' ? ted_truncate('BA ' . $city, 40) : 'BA',
        'main_country'       => 'BA',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => (string)($x['ProcedureId'] ?? $id),
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'reg_number' => $buyerId ?: null, 'city' => $city ?: null, 'country' => 'BA'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'ejn-odata',
    ];
}

// ─────────────────────────── Ziemeļmaķedonija (e-nabavki.gov.mk) ──────────────

/**
 * Viens ESJN GetGridData ieraksts → notices rinda vai null.
 *
 * Tikai mazās procedūras (TypeOfProcedure 13/14, zem ES sliekšņa → nekad TED).
 * $category nāk no izsaukuma (Status=1 aktīvie / Status=2 rezultāti). CPV/vērtība
 * režģī nav — kartē nosaukumu, pasūtītāju, veidu un termiņu.
 */
function macedonia_notice(array $x, string $category): ?array {
    $id = trim((string)($x['Id'] ?? ''));
    $title = trim((string)($x['Subject'] ?? ''));
    if ($id === '' || $title === '') return null;

    $procType = (int)($x['ProcedureType'] ?? 13);
    [$dlDate, $dlTime] = balkan_dt($x['FinalDay'] ?? null);
    [$pubDate] = balkan_dt($x['AnnouncementDate'] ?? null);

    $nature = match ((string)($x['GoodsWorksServices'] ?? '')) {
        'Goods'    => 'supplies',
        'Works'    => 'works',
        'Services' => 'services',
        default    => null,
    };
    $buyer = trim((string)($x['ContractingInstitutionName'] ?? '')) ?: 'Nezināms pasūtītājs';
    $number = trim((string)($x['ProcessNumber'] ?? ''));

    return [
        'id'                 => 'ESJN-' . $id,
        'source'             => 'ESJN',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'MK',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => ted_truncate($number, 40) ?: null,
        'budget'             => null,
        'currency'           => 'MKD',
        'document_url'       => sprintf(ESJN_VIEW_FMT, rawurlencode($id), $procType),
        'buyer_profile_url'  => null,
        'procedure_type'     => ted_truncate((string)($x['EntityProcedureType'] ?? ''), 60) ?: null,
        'notice_sub_type'    => ted_truncate((string)($x['EntityProcedureType'] ?? ''), 40) ?: null,
        'notice_lang'        => 'MK',
        'issue_date'         => $pubDate,
        'main_nuts'          => 'MK',
        'main_country'       => 'MK',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $id,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'country' => 'MK'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'esjn-gridapi',
    ];
}

// ─────────────────────────── Serbija (jnportal.ujn.gov.rs) ────────────────────

/**
 * Serbijas dokumenta tips (DocumentTypeShortName Ф-kods) → kategorija vai null.
 * Ф02/Ф05 = Јавни позив (aktīvie); Ф03/Ф06 = piešķiršana (rezultāti);
 * Ф14 = izmaiņas; pārējie (Ф27 sūdzība u.c.) → null (izlaiž).
 */
function serbia_category(string $shortName): ?string {
    // Ф02/Ф14 nāk ar piekabinātām neplīstošajām atstarpēm (U+00A0), ko trim()
    // nenoņem — tāpēc izgriežam TIKAI Ф-kodu.
    if (!preg_match('/(Ф\d+)/u', $shortName, $m)) return null;
    return match ($m[1]) {
        'Ф02', 'Ф05'         => 'iepirkumi',
        'Ф03', 'Ф06'         => 'rezultati',
        'Ф14'                => 'izmainas',
        default              => null,
    };
}

/** Viens TenderNotices searchgrid ieraksts → notices rinda vai null. */
function serbia_notice(array $x): ?array {
    $tenderId = (string)($x['TenderId'] ?? '');
    $title = trim((string)($x['TenderName'] ?? ''));
    if ($tenderId === '' || $title === '') return null;

    $category = serbia_category((string)($x['DocumentTypeShortName'] ?? ''));
    if ($category === null) return null; // ne-konkursa dokuments (sūdzība u.c.)

    [$pubDate] = balkan_dt($x['PublishDate'] ?? null);
    $buyer = trim((string)($x['ContractingBody'] ?? '')) ?: 'Nezināms pasūtītājs';
    $number = trim((string)($x['NoticeNumber'] ?? ''));
    $noticeId = (string)($x['Id'] ?? $tenderId);

    return [
        'id'                 => 'JNRS-' . $noticeId,
        'source'             => 'JNRS',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'RS',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => null,
        'publication_date'   => $pubDate,
        'deadline_date'      => null, // režģī nav termiņa
        'deadline_time'      => null,
        'publication_number' => ted_truncate($number, 40) ?: null,
        'budget'             => null,
        'currency'           => 'RSD',
        'document_url'       => sprintf(JNRS_VIEW_FMT, rawurlencode($tenderId)),
        'buyer_profile_url'  => null,
        'procedure_type'     => ted_truncate((string)($x['DocumentTypeName'] ?? ''), 120) ?: null,
        'notice_sub_type'    => ted_truncate((string)($x['DocumentTypeShortName'] ?? ''), 40) ?: null,
        'notice_lang'        => 'SR',
        'issue_date'         => $pubDate,
        'main_nuts'          => 'RS',
        'main_country'       => 'RS',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $tenderId,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'country' => 'RS'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'jnrs-searchgrid',
    ];
}

// ─────────────────────────── Melnkalne (cejn.gov.me) ──────────────────────────

/** CeJN getTenderRounds → vēlākais endOfSubmissions (iesniegšanas termiņš) vai null. */
function cejn_round_deadline(?array $rounds): ?string {
    if (!is_array($rounds)) return null;
    $list = $rounds['value'] ?? $rounds;
    if (!is_array($list)) return null;
    $best = null;
    foreach ($list as $r) {
        if (is_array($r) && !empty($r['endOfSubmissions']) && (string)$r['endOfSubmissions'] > (string)$best) {
            $best = (string)$r['endOfSubmissions'];
        }
    }
    return $best;
}

/** CeJN procedūras tips → cilvēklasāms nosaukums. */
function cejn_proc(string $c): string {
    return match ($c) {
        'Small procurement'                 => 'Jednostavna nabavka (Small procurement)',
        'Open procedure'                    => 'Atklāta procedūra (Otvoreni postupak)',
        'Framework agreement mini call-off' => 'Vispārīgā vienošanās (Framework)',
        default                             => $c !== '' ? $c : 'Procedūra',
    };
}

/**
 * Viens CeJN GetTenders ieraksts (+ neobligāti getTenderRounds dati) → notices rinda.
 *
 * Ņemam VISAS procedūras (Small + Open + Framework); Melnkalne nav TED. Kategoriju
 * nosaka termiņš (ja rounds dati ir) vai lifecycleCaption ("U toku" = aktīvs). Small
 * procurement termiņa nav (90-d heiristika); Open/Framework — īstais endOfSubmissions.
 * Atcelts (Poništen) → izlaiž.
 */
function montenegro_notice(array $x, ?array $rounds = null): ?array {
    $id = (int)($x['id'] ?? 0);
    $title = trim((string)($x['title'] ?? ''));
    if ($id <= 0 || $title === '') return null;

    $life = mb_strtolower(trim((string)($x['lifecycleCaption'] ?? '')));
    if (str_contains($life, 'ništen')) return null; // Poništen = atcelts

    [$pubDate] = balkan_dt($x['publishDate'] ?? $x['createdDate'] ?? null);
    [$dlDate, $dlTime] = balkan_dt(cejn_round_deadline($rounds));

    // Kategorija: ja ĪSTAIS termiņš zināms — pēc tā; citādi pēc lifecycle.
    if ($dlDate !== null) {
        $category = $dlDate >= konkursi_today() ? 'iepirkumi' : 'rezultati';
    } else {
        $category = ($life === 'u toku' || $life === '') ? 'iepirkumi' : 'rezultati';
    }

    $caption = (string)($x['typeOfProcedureCaption'] ?? '');
    $buyer = trim((string)($x['contractAuthority'] ?? '')) ?: 'Nezināms pasūtītājs';
    $nature = match ((string)($x['typeOfContractCaption'] ?? '')) {
        'Goods'    => 'supplies',
        'Works'    => 'works',
        'Services' => 'services',
        default    => null,
    };

    return [
        'id'                 => 'CEJN-' . $id,
        'source'             => 'CEJN',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'ME',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => (string)$id,
        'budget'             => null,
        'currency'           => 'EUR',
        'document_url'       => sprintf(CEJN_VIEW_FMT, $id),
        'buyer_profile_url'  => null,
        'procedure_type'     => cejn_proc($caption),
        'notice_sub_type'    => ted_truncate($caption, 40) ?: 'Small procurement',
        'notice_lang'        => 'CNR',
        'issue_date'         => $pubDate,
        'main_nuts'          => 'ME',
        'main_country'       => 'ME',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => (string)$id,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'country' => 'ME'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'cejn-api',
    ];
}

// ─────────────────────────── Albānija (app.gov.al) ────────────────────────────

/** Albānijas datums "23-07-2026" (dd-mm-yyyy) + laiks "09:30" → ['Y-m-d','H:i']. */
function albania_dt(?string $d, ?string $t = null): array {
    if (!is_string($d) || !preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $d, $m)) return [null, null];
    return ["$m[3]-$m[2]-$m[1]", (is_string($t) && preg_match('/^\d{1,2}:\d{2}/', $t)) ? substr($t, 0, 5) : null];
}

/**
 * Parsē APP mazo iepirkumu HTML rezultātu lapu → notices rindu masīvs.
 *
 * Katrs bloks sākas ar "Objekti i tenderit:" un satur pasūtītāju, atvēršanas un
 * slēgšanas (termiņa) datumus un REF numuru. Kategoriju nosaka termiņš.
 */
function albania_parse(string $html): array {
    $txt = preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $out = [];
    foreach (array_slice(preg_split('/Objekti i tenderit\s*:/u', $txt), 1) as $p) {
        if (!preg_match('/Numri i referenc[eë]s\s*:\s*(REF-[0-9-]+)/u', $p, $rm)) continue;
        $ref = $rm[1];
        $title = trim(preg_split('/M[eë] shum[eë] Info|Autoriteti Kontraktues/u', $p)[0]);
        if ($title === '') continue;
        // Katrs rezultāts HTML parādās DIVreiz: redzamais bloks (ar pasūtītāju)
        // un slēptā JS-veidne (bez tā). Bez pasūtītāja = veidnes dublikāts → izlaiž
        // (citādi tas ar to pašu REF-id pārrakstītu labo ierakstu upsertā).
        if (!preg_match('/Autoriteti Kontraktues\s*:\s*(.+?)(?:\||Data e hapjes)/u', $p, $am) || trim($am[1]) === '') continue;
        preg_match('/Data e hapjes\s*:\s*([0-9-]+)\s*Ora\s*:\s*([0-9:]+)/u', $p, $om);
        preg_match('/Data e mbylljes\s*:\s*([0-9-]+)\s*Ora\s*:\s*([0-9:]+)/u', $p, $cm);

        [$openDate] = albania_dt($om[1] ?? null);
        [$dlDate, $dlTime] = albania_dt($cm[1] ?? null, $cm[2] ?? null);
        $category = ($dlDate !== null && $dlDate >= konkursi_today()) ? 'iepirkumi' : 'rezultati';
        $buyer = trim($am[1]);

        $out[] = [
            'id'                 => 'APPAL-' . $ref,
            'source'             => 'APPAL',
            'category'           => $category,
            'title'              => ted_truncate($title, 400),
            'description'        => null,
            'buyer_name'         => ted_truncate($buyer, 300),
            'buyer_id'           => null,
            'buyer_country'      => 'AL',
            'buyer_activity'     => null,
            'buyer_type'         => null,
            'procure_nature'     => null,
            'publication_date'   => $openDate,
            'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
            'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
            'publication_number' => ted_truncate($ref, 40),
            'budget'             => null,
            'currency'           => 'ALL',
            'document_url'       => APPAL_VIEW_URL,
            'buyer_profile_url'  => null,
            'procedure_type'     => 'Prokurim me vlerë të vogël',
            'notice_sub_type'    => 'small',
            'notice_lang'        => 'SQ',
            'issue_date'         => $openDate,
            'main_nuts'          => 'AL',
            'main_country'       => 'AL',
            'funding_program'    => null,
            'prev_notice_ref'    => null,
            'contract_folder_id' => $ref,
            'main_cpv'           => null,
            'cpv_codes'          => '[]',
            'lots'               => '[]',
            'organizations'      => json_encode([array_filter(['name' => $buyer, 'country' => 'AL'])], JSON_UNESCAPED_UNICODE),
            'notice_contact'     => '{}',
            'source_file'        => 'app-al-html',
        ];
    }
    return $out;
}

