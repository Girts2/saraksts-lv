<?php
/**
 * konkursi/lib/south_parser.php — HR (EOJN RH), HU (EKR), PT (BASE) parseri.
 *
 * Dzīvā izpēte 2026-07-18. Neieviesti (nav mašīnlasāma oficiāla ceļa):
 * LU (PRADO postback portāls), MT/CY (e-PPS saraksti aiz CAPTCHA; MT OCDS
 * paketes satur 1 ierakstu mēnesī — nefunkcionālas).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php';     // ted_truncate(), ted_norm_date()
require_once __DIR__ . '/nordics_parser.php'; // nord_iso_dt()

// ═══════════════════════════ HR — EOJN RH ════════════════════════════════════

/**
 * TendersPublic/TendersSimple grid rinda → rinda.
 * @return array<string,mixed>|null null = virs ES sliekšņa (→ TED) vai nederīgs
 */
function eojn_parse_tender(array $it): ?array {
    $id = (int)($it['Id'] ?? 0);
    if ($id <= 0) return null;
    if (($it['AboveThreshold'] ?? false) === true) return null; // → TED

    $title = trim((string)($it['Name'] ?? ''));
    if ($title === '') return null;

    $natMap = ['Robe' => 'supplies', 'Usluge' => 'services', 'Radovi' => 'works'];
    $nature = $natMap[(string)($it['TypeContract'] ?? '')] ?? null;

    $cpv = null;
    if (preg_match('/^(\d{8})/', (string)($it['CPVExtended'] ?? ''), $m)) $cpv = $m[1];

    $budget = null;
    if (isset($it['EstimatedValue']) && is_numeric($it['EstimatedValue']) && (float)$it['EstimatedValue'] > 0) {
        $budget = (float)$it['EstimatedValue'];
    }

    [$pubDate] = nord_iso_dt($it['NoticePublishDate'] ?? null);
    [$dlDate, $dlTime] = nord_iso_dt($it['SubmissionDeadline'] ?? null);

    $buyer = trim((string)($it['ContractingBody'] ?? '')) ?: 'Nezināms pasūtītājs';
    $nuts = null;
    if (preg_match('/^(HR\w{2,3})/', (string)($it['Nuts'] ?? ''), $m)) $nuts = $m[1];

    return [
        'id'                 => 'EOJN-' . $id,
        'source'             => 'EOJN',
        'category'           => 'iepirkumi',
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(trim((string)($it['NameENG'] ?? '')) ?: null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'HR',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => (string)($it['ReferenceNumber'] ?? $id),
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => sprintf(EOJN_VIEW_URL_FMT, $id),
        'buyer_profile_url'  => null,
        'procedure_type'     => trim((string)($it['ProcedureType'] ?? '')) ?: null,
        'notice_sub_type'    => null,
        'notice_lang'        => 'HR',
        'issue_date'         => $pubDate,
        'main_nuts'          => $nuts,
        'main_country'       => 'HR',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => isset($it['PlanItemId']) ? (string)$it['PlanItemId'] : null,
        'main_cpv'           => $cpv,
        'cpv_codes'          => json_encode($cpv !== null ? [$cpv] : [], JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([['name' => $buyer, 'country' => 'HR']], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'eojn-grid',
    ];
}

/**
 * VAwardDecisions rinda → rezultāta rinda (tikai zem-sliekšņa konkursiem —
 * saistību pārbauda sync posms). $tenderId = saistītais EOJN konkursa Id.
 */
function eojn_parse_decision(array $it, int $tenderId): ?array {
    $decId = (int)($it['TenderDecisionId'] ?? 0);
    if ($decId <= 0) return null;
    $title = trim((string)($it['TenderName'] ?? ''));
    if ($title === '') return null;

    [$pubDate] = nord_iso_dt($it['PublishDate'] ?? $it['DecisionDate'] ?? null);
    $buyer = trim((string)($it['OfficialName'] ?? '')) ?: 'Nezināms pasūtītājs';

    $orgs = [['name' => $buyer, 'country' => 'HR']];
    $winners = trim((string)($it['ContractorsNames'] ?? ''));
    if ($winners !== '') {
        foreach (array_slice(preg_split('/\s*[;|]\s*/', $winners) ?: [], 0, 10) as $w) {
            if (trim($w) !== '') $orgs[] = ['name' => trim($w) . ' (uzvarētājs)', 'country' => 'HR'];
        }
    }

    return [
        'id'                 => 'EOJN-D' . $decId,
        'source'             => 'EOJN',
        'category'           => 'rezultati',
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(trim((string)($it['LotTitle'] ?? '')) ?: null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'HR',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => null,
        'publication_date'   => $pubDate,
        'deadline_date'      => null,
        'deadline_time'      => null,
        'publication_number' => 'ODL-' . $decId,
        'budget'             => null,
        'currency'           => 'EUR',
        'document_url'       => sprintf(EOJN_VIEW_URL_FMT, $tenderId),
        'buyer_profile_url'  => null,
        'procedure_type'     => null,
        'notice_sub_type'    => 'ODLUKA',
        'notice_lang'        => 'HR',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'HR',
        'funding_program'    => null,
        'prev_notice_ref'    => 'EOJN-' . $tenderId,
        'contract_folder_id' => null,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'eojn-decisions',
    ];
}

// ═══════════════════════════ HU — EKR ════════════════════════════════════════

/** Kategorija no hirdetmenyTipusa astes ('Nemzeti, Ajánlati felhívás' u.tml.). */
function ekr_category(string $tipusa): string {
    $t = mb_strtolower($tipusa, 'UTF-8');
    if (str_contains($t, 'módosítás')) return 'izmainas';
    if (str_contains($t, 'eredmény')) return 'rezultati';
    if (str_contains($t, 'felhívás')) return 'iepirkumi';
    return 'citi';
}

/**
 * Saraksta ieraksts + detaļa (var būt null) → rinda.
 * @return array<string,mixed>|null null = ES līmenis (TED) vai nederīgs
 */
function ekr_build_notice(array $it, ?array $det): ?array {
    $id = (string)($it['id'] ?? '');
    if ($id === '') return null;
    if (!empty($it['tedAzonosito'])) return null; // ir TED numurs → dublikāts
    $tipusa = (string)($it['hirdetmenyTipusa'] ?? '');
    if (str_starts_with($tipusa, 'Uniós')) return null; // ES formas → TED

    $title = trim((string)($it['eljarasTargya'] ?? ''));
    if ($title === '') return null;
    $buyer = trim(str_replace(["\t", '-  '], ' ', (string)($it['ajanlatkeroNeve'] ?? ''))) ?: 'Nezināms pasūtītājs';
    $buyer = preg_replace('/^[-\s]+/u', '', $buyer);

    [$pubDate] = nord_iso_dt($it['hirdetmenyKozzetetelDatuma'] ?? null);
    [$dlDate, $dlTime] = nord_iso_dt($it['eljarasAjanlatteteliHatarido'] ?? null);
    $category = ekr_category($tipusa);

    $cpv = []; $nature = null; $nuts = null; $orgs = [['name' => $buyer, 'country' => 'HU']];
    if ($det !== null) {
        foreach ((array)($det['eljarasCPVkod'] ?? []) as $c) {
            if (preg_match('/(\d{8})/', (string)$c, $m)) $cpv[] = $m[1];
        }
        $cpv = array_values(array_unique($cpv));
        $bt = (string)($det['eljarasBeszerzesTargya'] ?? '');
        $nature = match (true) {
            str_contains($bt, 'Építési') => 'works',
            str_contains($bt, 'Árubeszerzés') => 'supplies',
            $bt !== '' => 'services',
            default => null,
        };
        if (preg_match('/^(HU\w{2,3})/', (string)($det['eljarasTeljesitesHelye'] ?? ''), $m)) $nuts = $m[1];
        $w = trim((string)($det['eljarasNyertesAjanlattevoNeve'] ?? ''));
        if ($w !== '') $orgs[] = ['name' => $w . ' (uzvarētājs)', 'country' => 'HU'];
    }

    return [
        'id'                 => 'EKR-' . $id,
        'source'             => 'EKR',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'HU',
        'buyer_activity'     => null,
        'buyer_type'         => isset($det['ajanlatkeroTipus']) ? ted_truncate((string)$det['ajanlatkeroTipus'], 120) : null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => (string)($it['hirdetmenyEKRazonosito'] ?? $id),
        'budget'             => null,
        'currency'           => 'HUF',
        'document_url'       => sprintf(EKR_VIEW_URL_FMT, $id),
        'buyer_profile_url'  => null,
        'procedure_type'     => isset($det['eljarasEljarasTipusa']) ? ted_truncate((string)$det['eljarasEljarasTipusa'], 200) : null,
        'notice_sub_type'    => ted_truncate($tipusa, 120),
        'notice_lang'        => 'HU',
        'issue_date'         => $pubDate,
        'main_nuts'          => $nuts,
        'main_country'       => 'HU',
        'funding_program'    => null,
        'prev_notice_ref'    => isset($det['eljarasTechnikaiAzonosito']) ? (string)$det['eljarasTechnikaiAzonosito'] : null,
        'contract_folder_id' => isset($it['hirdetmenyIktatoszam']) ? (string)$it['hirdetmenyIktatoszam'] : null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'ekr-api',
    ];
}

// ═══════════════════════════ PT — BASE ═══════════════════════════════════════

/** '300.000,00 €' → 300000.0 */
function base_price(?string $s): ?float {
    if (!is_string($s) || !preg_match('/[\d.,]+/', $s, $m)) return null;
    $n = (float)str_replace(['.', ','], ['', '.'], $m[0]);
    return $n > 0 ? $n : null;
}

/** 'DD-MM-YYYY' → 'YYYY-MM-DD' */
function base_date(?string $s): ?string {
    if (!is_string($s) || !preg_match('/(\d{2})-(\d{2})-(\d{4})/', $s, $m)) return null;
    return $m[3] . '-' . $m[2] . '-' . $m[1];
}

/**
 * Anúncio saraksta ieraksts + detaļa (var būt null) → rinda.
 * @return array<string,mixed>|null null = virs ES sliekšņa (→ TED) vai nederīgs
 */
function base_build_notice(array $it, ?array $det): ?array {
    $id = (int)($it['id'] ?? 0);
    if ($id <= 0) return null;
    $title = trim((string)($it['contractDesignation'] ?? ''));
    if ($title === '') return null;

    $ctText = (string)(($det['contractType'] ?? null) ?? '');
    $nature = match (true) {
        str_contains($ctText, 'Empreitada') || str_contains($ctText, 'obras') => 'works',
        str_contains($ctText, 'Aquisição de bens') || str_contains($ctText, 'Locação') => 'supplies',
        $ctText !== '' => 'services',
        default => null,
    };

    $budget = base_price($it['basePrice'] ?? null);
    // Dedup pret TED: virs ES sliekšņa esošie tāpat nonāk TED plūsmā
    if ($budget !== null) {
        $limit = $nature === 'works' ? PLACSP_EU_THRESHOLD_WORKS : PLACSP_EU_THRESHOLD_SERVICES;
        if ($budget >= $limit) return null;
    }

    $type = (string)($it['type'] ?? '');
    $category = (mb_stripos($type, 'prorrogação', 0, 'UTF-8') !== false
        || mb_stripos($type, 'retifica', 0, 'UTF-8') !== false) ? 'izmainas' : 'iepirkumi';

    $cpv = [];
    if ($det !== null && preg_match_all('/(\d{8})/', (string)($det['cpvs'] ?? ''), $mm)) {
        $cpv = array_values(array_unique($mm[1]));
    }

    $buyer = trim((string)($it['contractingEntity'] ?? '')) ?: 'Nezināms pasūtītājs';
    $buyerId = null;
    if (is_array($det['contractingEntities'] ?? null) && isset($det['contractingEntities'][0]['nif'])) {
        $buyerId = (string)$det['contractingEntities'][0]['nif'];
    }

    // Rindas atslēga = nAnuncio (kopīga ar lielapjoma failu; sk. base_notice_id).
    // Ja detaļa neatbildēja, atkāpjas uz iekšējo id — tāda rinda ir bez CPV, un
    // lielapjoma imports to vēlāk aizstās ar pilnu versiju.
    $nAnuncio = trim((string)($det['announcementNumber'] ?? ''));
    return [
        'id'                 => $nAnuncio !== '' ? base_notice_id($nAnuncio) : 'BASE-' . $id,
        'source'             => 'BASE',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'PT',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => base_date($it['drPublicationDate'] ?? null),
        'deadline_date'      => $category === 'iepirkumi' ? base_date($it['proposalDeadline'] ?? null) : null,
        'deadline_time'      => null,
        'publication_number' => (string)(($det['announcementNumber'] ?? null) ?? $id),
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => sprintf(BASE_DETAIL_PAGE_FMT, $id),
        'buyer_profile_url'  => isset($det['reference']) && str_starts_with((string)$det['reference'], 'http') ? (string)$det['reference'] : null,
        'procedure_type'     => trim((string)($it['contractingProcedureType'] ?? '')) ?: null,
        'notice_sub_type'    => ted_truncate($type, 80) ?: null,
        'notice_lang'        => 'PT',
        'issue_date'         => base_date($it['drPublicationDate'] ?? null),
        'main_nuts'          => null,
        'main_country'       => 'PT',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => isset($det['contractingProcedureId']) ? (string)$det['contractingProcedureId'] : null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'reg_number' => $buyerId, 'country' => 'PT'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'base-anuncios',
    ];
}

/** 'nAnuncio' ('18452/2026') → stabils rindas id. Kopīgs abām PT plūsmām. */
function base_notice_id(string $nAnuncio): string {
    return 'BASE-' . str_replace('/', '-', trim($nAnuncio));
}

/** '21/04/2030' → '2030-04-21' (dados.gov.pt lielapjoma faila datumu formāts). */
function base_bulk_date($s): ?string {
    return preg_match('#^(\d{2})/(\d{2})/(\d{4})#', (string)$s, $m) ? "$m[3]-$m[2]-$m[1]" : null;
}

/**
 * dados.gov.pt gada lielapjoma faila ieraksts → notices rinda.
 *
 * Šis fails satur VISU, ko citādi vāc ar detaļu pieprasījumiem (CPV, cena,
 * pircēja NIF, DRE PDF), un vienā lejupielādē — BASE meklēšanas API agresīvi
 * ierobežo ātrumu, tāpēc lapošana 60 d logu nesasniedz (mērījums 2026-07-20:
 * 575 no 983 atvērtajiem konkursiem iztrūka).
 * @return array<string,mixed>|null
 */
function base_bulk_notice(array $x): ?array {
    $nAnuncio = trim((string)($x['nAnuncio'] ?? ''));
    $title = trim((string)($x['descricaoAnuncio'] ?? ''));
    if ($nAnuncio === '' || $title === '') return null;

    $tipos = (array)($x['tiposContrato'] ?? []);
    $ctText = implode(' ', array_filter($tipos, 'is_string'));
    $nature = match (true) {
        str_contains($ctText, 'Empreitada') || str_contains($ctText, 'obras') => 'works',
        str_contains($ctText, 'Aquisição de bens') || str_contains($ctText, 'Locação') => 'supplies',
        $ctText !== '' => 'services',
        default => null,
    };

    // 'PrecoBase': parasti tīrs decimālskaitlis ar PUNKTU ('509079.55'), retāk
    // portugāļu formāts ('509.079,55 €') vai 'Inexistente'. Ja ir komats — tas
    // ir decimālais atdalītājs; ja nav — punkts jau ir decimālais (to nedrīkst
    // izmest kā tūkstošu atdalītāju, citādi summa uzpūšas ×100).
    $budget = null;
    $pb = (string)($x['PrecoBase'] ?? '');
    if ($pb !== '' && stripos($pb, 'inexistente') === false) {
        $num = preg_replace('/[^\d,.]/', '', $pb) ?? '';
        $num = str_contains($num, ',')
            ? str_replace(['.', ','], ['', '.'], $num)
            : $num;
        if (is_numeric($num) && (float)$num > 0) $budget = (float)$num;
    }
    // Dedup pret TED pēc vērtības (papildus ks_dedupe_vs_ted pēc nosaukuma)
    if ($budget !== null) {
        $limit = $nature === 'works' ? PLACSP_EU_THRESHOLD_WORKS : PLACSP_EU_THRESHOLD_SERVICES;
        if ($budget >= $limit) return null;
    }

    $tipoActo = (string)($x['tipoActo'] ?? '');
    $category = (mb_stripos($tipoActo, 'Alteração', 0, 'UTF-8') !== false
        || mb_stripos($tipoActo, 'retifica', 0, 'UTF-8') !== false) ? 'izmainas' : 'iepirkumi';

    $cpv = [];
    foreach ((array)($x['CPVs'] ?? []) as $c) {
        if (preg_match('/(\d{8})/', (string)$c, $m)) $cpv[] = $m[1];
    }
    $cpv = array_values(array_unique($cpv));

    $buyer = trim((string)($x['designacaoEntidade'] ?? '')) ?: 'Nezināms pasūtītājs';
    $buyerId = trim((string)($x['nifEntidade'] ?? '')) ?: null;
    $url = trim((string)($x['url'] ?? ''));

    return [
        'id'                 => base_notice_id($nAnuncio),
        'source'             => 'BASE',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'PT',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => base_bulk_date($x['dataPublicacao'] ?? null),
        'deadline_date'      => $category === 'iepirkumi' ? base_bulk_date($x['DataLimitePropostas'] ?? null) : null,
        'deadline_time'      => null,
        'publication_number' => $nAnuncio,
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => str_starts_with($url, 'http') ? $url : null,
        'buyer_profile_url'  => null,
        'procedure_type'     => $ctText !== '' ? ted_truncate($ctText, 120) : null,
        'notice_sub_type'    => ted_truncate($tipoActo, 80) ?: null,
        'notice_lang'        => 'PT',
        'issue_date'         => base_bulk_date($x['dataPublicacao'] ?? null),
        'main_nuts'          => null,
        'main_country'       => 'PT',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'reg_number' => $buyerId, 'country' => 'PT'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'base-bulk',
    ];
}

/**
 * BASE 'search_contratos' ieraksts → notices rinda ('rezultati').
 *
 * Atšķirībā no vairuma nacionālo avotu te ir gan pircējs, gan UZVARĒTĀJS, gan
 * līgumcena. ~80% ir 'Ajuste Direto' (tiešais piešķīrums bez publiska konkursa) —
 * tos paturam, jo tirgus analītikai tie ir vērtīgākā daļa; procedūras veids
 * paliek redzams kartītē.
 * @return array<string,mixed>|null
 */
function base_contract_notice(array $it): ?array {
    $id = (int)($it['id'] ?? 0);
    $title = trim((string)($it['objectBriefDescription'] ?? ''));
    if ($id <= 0 || $title === '') return null;

    $buyer = trim((string)($it['contracting'] ?? '')) ?: 'Nezināms pasūtītājs';
    $winner = trim((string)($it['contracted'] ?? ''));
    $proc = trim((string)($it['contractingProcedureType'] ?? ''));

    $nature = match (true) {
        str_contains($proc, 'Empreitada') => 'works',
        default => null,
    };

    $orgs = [array_filter(['name' => $buyer, 'country' => 'PT'])];
    if ($winner !== '') $orgs[] = ['name' => $winner . ' (uzvarētājs)', 'country' => 'PT'];

    $pub = base_date($it['publicationDate'] ?? null);
    return [
        'id'                 => 'BASE-C' . $id,
        'source'             => 'BASE',
        'category'           => 'rezultati',
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'PT',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pub,
        'deadline_date'      => null,
        'deadline_time'      => null,
        'publication_number' => (string)$id,
        'budget'             => base_price(is_string($it['initialContractualPrice'] ?? null) ? $it['initialContractualPrice'] : null),
        'currency'           => 'EUR',
        'document_url'       => sprintf(BASE_CONTRACT_PAGE_FMT, $id),
        'buyer_profile_url'  => null,
        'procedure_type'     => $proc !== '' ? ted_truncate($proc, 120) : null,
        'notice_sub_type'    => 'contrato',
        'notice_lang'        => 'PT',
        'issue_date'         => base_date($it['signingDate'] ?? null) ?? $pub,
        'main_nuts'          => null,
        'main_country'       => 'PT',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => null, // saraksta API CPV nedod
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'base-contratos',
    ];
}

// ═══════════════════════════ CY — data.gov.cy (rezultāti) ════════════════════

/** '30-12-2025 00:00:00' → '2025-12-30' */
function cyprus_date($s): ?string {
    return preg_match('#^(\d{2})-(\d{2})-(\d{4})#', trim((string)$s), $m) ? "$m[3]-$m[2]-$m[1]" : null;
}

/**
 * Kipras piešķirto līgumu CSV rinda → notices rinda ('rezultati').
 *
 * Dedup pret TED: lauks 'Above or Below threshold' — 'Κάτω' (zem sliekšņa) ir
 * nacionāls, 'Άνω' iet uz TED. Tas pats princips kā Īrijas e-PPS 'Below'.
 * @param array<string,int> $ix kolonnu nosaukums → indekss
 * @return array<string,mixed>|null
 */
function cyprus_award_notice(array $r, array $ix): ?array {
    $get = static fn(string $k): string => isset($ix[$k], $r[$ix[$k]]) ? trim((string)$r[$ix[$k]]) : '';

    $cftId = $get('CFTID');
    $title = $get('CFTTITLE');
    if ($cftId === '' || $title === '') return null;

    // Virs ES sliekšņa esošie nāk TED plūsmā. Lauka faktiskās vērtības
    // (2026-07-20): 'Κάτω' 2417, 'Πάνω' 506, tukšs 26 — NE 'Άνω', kā varētu
    // gaidīt; tukšos patur un atlikušo pārklājumu tīra ks_dedupe_vs_ted.
    $thr = $get('Above or Below threshold');
    foreach (['Πάνω', 'Άνω'] as $above) {
        if (mb_strpos($thr, $above, 0, 'UTF-8') !== false) return null;
    }

    $ct = $get('Contract Type El');
    $nature = match (true) {
        mb_strpos($ct, 'Έργα', 0, 'UTF-8') !== false        => 'works',
        mb_strpos($ct, 'Προμήθειες', 0, 'UTF-8') !== false  => 'supplies',
        $ct !== ''                                          => 'services',
        default                                             => null,
    };

    $cpv = [];
    foreach (preg_split('/[,;\s]+/', $get('CPVCODES')) ?: [] as $c) {
        if (preg_match('/^(\d{8})/', trim($c), $m)) $cpv[] = $m[1];
    }
    $cpv = array_values(array_unique($cpv));

    $budget = null;
    foreach (['AWARDEDCONTRVALUE', 'Estimated Value'] as $f) {
        $v = str_replace([' ', ','], ['', '.'], $get($f));
        if ($v !== '' && is_numeric($v) && (float)$v > 0) { $budget = (float)$v; break; }
    }

    $buyer = $get('ORGANIZATIONNAME') ?: 'Nezināms pasūtītājs';
    $winner = $get('EONAME') ?: $get('EONAMES');
    $orgs = [array_filter(['name' => $buyer, 'country' => 'CY'])];
    if ($winner !== '') $orgs[] = ['name' => $winner . ' (uzvarētājs)', 'country' => 'CY'];

    $award = cyprus_date($get('AWARDDATE'));
    $pub = cyprus_date($get('Date Published'));

    return [
        'id'                 => 'CYPRUS-' . $cftId,
        '_winner'            => $winner, // agrēgācijai ks_sync_cyprus (store to ignorē)
        'source'             => 'CYPRUS',
        'category'           => 'rezultati',
        'title'              => ted_truncate($title, 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'CY',
        'buyer_activity'     => ted_truncate($get('CA_TYPE_EL'), 120) ?: null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        // Kārtošanai der piešķiršanas datums — tas ir notikums, ko rāda kartītē
        'publication_date'   => $award ?? $pub,
        'deadline_date'      => null,
        'deadline_time'      => null,
        'publication_number' => $get('CfT CA Unique ID') ?: $cftId,
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => sprintf(CYPRUS_VIEW_URL_FMT, $cftId),
        'buyer_profile_url'  => null,
        'procedure_type'     => ted_truncate($get('Procedure El'), 120) ?: null,
        'notice_sub_type'    => 'award',
        'notice_lang'        => 'EL',
        'issue_date'         => $pub,
        'main_nuts'          => 'CY000',
        'main_country'       => 'CY',
        'funding_program'    => mb_strpos($get('EU funding'), 'Ναι', 0, 'UTF-8') !== false ? 'ES līdzfinansējums' : null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $get('CfT CA Unique ID') ?: null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'cyprus-awarded',
    ];
}

// ═══════════════════════════ IT — ANAC Open Data ═════════════════════════════

/** '158214.24' / '' / '0' → float|null (0 = nav norādīts) */
function anac_num(?string $s): ?float {
    if ($s === null) return null;
    $s = trim($s);
    if ($s === '' || !is_numeric($s)) return null;
    $v = (float)$s;
    return $v > 0 ? $v : null;
}

/**
 * Agregēta gara (visas lotes kopā) → paziņojums.
 * $g atslēgas: gara,title,desc,buyer,buyer_id,nature,pub,deadline,budget,
 * cpv[],main_cpv,lots[],cig,provincia,procedure
 * @return array<string,mixed>|null null = virs ES sliekšņa (→ TED) vai nederīgs
 */
function anac_build_notice(array $g): ?array {
    if ($g['gara'] === '' || $g['title'] === '') return null;

    $natMap = ['LAVORI' => 'works', 'SERVIZI' => 'services', 'FORNITURE' => 'supplies'];
    $nature = $natMap[strtoupper((string)$g['nature'])] ?? null;

    // Dedup pret TED: virs ES sliekšņa esošās gares tāpat nonāk TED plūsmā
    if ($g['budget'] !== null) {
        $limit = $nature === 'works' ? PLACSP_EU_THRESHOLD_WORKS : PLACSP_EU_THRESHOLD_SERVICES;
        if ($g['budget'] >= $limit) return null;
    }

    $buyer = trim((string)$g['buyer']) ?: 'Nezināms pasūtītājs';
    return [
        'id'                 => 'ANAC-' . $g['gara'],
        'source'             => 'ANAC',
        'category'           => 'iepirkumi',
        'title'              => ted_truncate((string)$g['title'], 400),
        'description'        => $g['desc'] !== null ? ted_truncate((string)$g['desc'], KONKURSI_DESC_MAX) : null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $g['buyer_id'] !== '' ? (string)$g['buyer_id'] : null,
        'buyer_country'      => 'IT',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $g['pub'],
        'deadline_date'      => $g['deadline'],
        'deadline_time'      => null,
        'publication_number' => (string)$g['cig'],
        'budget'             => $g['budget'],
        'currency'           => 'EUR',
        // Bez saites ar nolūku: ANAC /superset/dashboard/dettaglio_cig/ lapa ir salauzta
        // pašā ANAC pusē — tās React pakotne (/assets/index-*.js) atbild ar 404, tāpēc
        // saturs neielādējas nevienam pārlūkam. Per-CIG publiskas lapas ANAC portālā nav,
        // un uz šo lapu nesaista arī paši atvērto datu katalogi. CIG numurs redzams
        // atšifrējumā, tāpēc lietotājs to var atrast ANAC meklētājā pats.
        'document_url'       => null,
        'buyer_profile_url'  => null,
        'procedure_type'     => ted_truncate((string)$g['procedure'], 80) ?: null,
        'notice_sub_type'    => null,
        'notice_lang'        => 'IT',
        'issue_date'         => $g['pub'],
        'main_nuts'          => null,
        'main_country'       => 'IT',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => (string)$g['gara'],
        'main_cpv'           => $g['main_cpv'],
        'cpv_codes'          => json_encode(array_values(array_unique($g['cpv'])), JSON_UNESCAPED_UNICODE),
        'lots'               => json_encode(array_slice($g['lots'], 0, KONKURSI_MAX_LOTS), JSON_UNESCAPED_UNICODE),
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'reg_number' => (string)$g['buyer_id'], 'country' => 'IT'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => json_encode(array_filter(['address' => trim((string)$g['provincia']) ?: null]), JSON_UNESCAPED_UNICODE),
        'source_file'        => 'anac-cig',
    ];
}
