<?php
/**
 * konkursi/lib/bzp_parser.php — Polijas BZP (ezamowienia.gov.pl mo-board API) parseris.
 *
 * BZP (Biuletyn Zamówień Publicznych) satur TIKAI zem ES sliekšņa esošos
 * iepirkumus — virs-sliekšņa Polija publicē TED. Drošībai papildus pārbauda
 * isTenderAmountBelowEU. API atbild ar JSON sarakstu; htmlBody lauku ignorē.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php';    // ted_truncate()
require_once __DIR__ . '/nordics_parser.php'; // nord_iso_dt()

/**
 * @param array<string,mixed> $it   viens API saraksta elements
 * @param string $category          'iepirkumi' (ContractNotice) | 'rezultati' (TenderResultNotice)
 * @return array<string,mixed>|null
 */
function bzp_parse_item(array $it, string $category): ?array {
    if (($it['isTenderAmountBelowEU'] ?? true) !== true) return null; // virs sliekšņa → TED
    $objectId = (string)($it['objectId'] ?? '');
    $title = trim((string)($it['orderObject'] ?? ''));
    if ($objectId === '' || $title === '') return null;

    // 'zmiana ogłoszenia' — numura sufikss /02+ nozīmē labojumu
    $noticeNumber = (string)($it['noticeNumber'] ?? '');
    if ($category === 'iepirkumi' && preg_match('#/0*([2-9]\d*)$#', $noticeNumber)) {
        $category = 'izmainas';
    }

    // 'Konserwacja...' cpvCode formāts: '50760000-0 (Nosaukums)'
    $mainCpv = null;
    if (preg_match('/^(\d{8})/', (string)($it['cpvCode'] ?? ''), $m)) $mainCpv = $m[1];

    [$pubDate] = nord_iso_dt($it['publicationDate'] ?? null);
    [$dlDate, $dlTime] = nord_iso_dt($it['submittingOffersDate'] ?? null);

    $nature = match (strtolower((string)($it['orderType'] ?? ''))) {
        'services'              => 'services',
        'deliveries', 'supplies' => 'supplies',
        'works', 'constructionworks' => 'works',
        default                 => null,
    };

    $buyer = trim((string)($it['organizationName'] ?? ''));
    $city = trim((string)($it['organizationCity'] ?? ''));
    $nuts = trim((string)($it['organizationProvince'] ?? ''));

    return [
        'id'                 => 'BZP-' . $objectId,
        'source'             => 'BZP',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => null, // saraksta API apraksta lauku nedod (tikai htmlBody)
        'buyer_name'         => ted_truncate($buyer !== '' ? $buyer : 'Nezināms pasūtītājs', 300),
        'buyer_id'           => is_string($it['organizationNationalId'] ?? null) && $it['organizationNationalId'] !== '' ? $it['organizationNationalId'] : null,
        'buyer_country'      => 'PL',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $noticeNumber !== '' ? $noticeNumber : $objectId,
        'budget'             => null,
        'currency'           => 'PLN',
        'document_url'       => $noticeNumber !== '' ? sprintf(BZP_NOTICE_URL_FMT, rawurlencode($noticeNumber)) : null,
        'buyer_profile_url'  => null,
        'procedure_type'     => null,
        'notice_sub_type'    => is_string($it['tenderType'] ?? null) ? $it['tenderType'] : null,
        'notice_lang'        => 'PL',
        'issue_date'         => $pubDate,
        'main_nuts'          => $nuts !== '' ? $nuts : null,
        'main_country'       => 'PL',
        'funding_program'    => null,
        'prev_notice_ref'    => is_string($it['bzpNumber'] ?? null) ? $it['bzpNumber'] : null,
        'contract_folder_id' => is_string($it['tenderId'] ?? null) ? $it['tenderId'] : null,
        'main_cpv'           => $mainCpv,
        'cpv_codes'          => json_encode($mainCpv !== null ? [$mainCpv] : [], JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter([
                                    'name'       => $buyer !== '' ? $buyer : null,
                                    'reg_number' => $it['organizationNationalId'] ?? null,
                                    'city'       => $city !== '' ? $city : null,
                                    'country'    => 'PL',
                                ])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'bzp-api',
    ];
}
