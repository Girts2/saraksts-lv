<?php
/**
 * konkursi/lib/iub_parser.php — IUB (open.iub.gov.lv) dienas JSON → notices rinda.
 *
 * PHP ports no ted/konkursi/scripts/parsers/general.py ('lv' zars), pielāgots
 * šīs sadaļas apcirstajai shēmai. SVARĪGI: paziņojumus ar procedureLegalBasis,
 * kas satur '-over' (virs ES sliekšņa), IZLAIŽ — tie tāpat nonāk TED plūsmā,
 * tāpēc IUB dod tikai unikālo saturu (zem-sliekšņa/nacionālos iepirkumus).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php'; // ted_truncate(), ted_norm_date()

/** '20/05/2026' vai '2026-05-20...' → '2026-05-20' (vai null). */
function iub_norm_date(?string $s): ?string {
    if ($s === null) return null;
    $s = trim($s);
    if ($s === '' || $s === '-') return null;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $s, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return ted_norm_date($s);
}

/** Drošs lauks no dict VAI dict sarakstiem (IUB struktūras mēdz būt abējādas). */
function iub_field($obj, string $field) {
    if (is_array($obj)) {
        // asociatīvs masīvs = dict
        if (array_key_exists($field, $obj)) return $obj[$field];
        // saraksts ar dict elementiem
        foreach ($obj as $o) {
            if (is_array($o) && array_key_exists($field, $o) && $o[$field] !== null && $o[$field] !== '') {
                return $o[$field];
            }
        }
    }
    return null;
}

/**
 * Daļa pasūtītāju documentsURL laukā ieliek EIS LABOŠANAS lapu
 * (/EKEIS/Procurement/Edit/{id}), kas anonīmam apmeklētājam ved uz EIS sākumlapu.
 * Tas pats id publiskajā shēmā atver pareizo iepirkumu, tāpēc pārliek.
 */
function iub_fix_eis_url(string $url): string {
    return preg_replace(
        '#^(https?://(?:www\.)?eis\.gov\.lv)/EKEIS/Procurement/Edit/(\d+)#i',
        '$1/EKEIS/Supplier/Procurement/$2',
        $url
    ) ?? $url;
}

/** CPV kods '45233253-7' → '45233253'. */
function iub_cpv(?string $c): ?string {
    if (!is_string($c) || $c === '') return null;
    $c = explode('-', trim($c))[0];
    return preg_match('/^\d{8}$/', $c) ? $c : null;
}

/**
 * Parsē vienu IUB paziņojumu.
 * @param array<string,mixed> $item      viens elements no dienas JSON masīva
 * @param string $sourceFile             piem. '16-07-2026.json'
 * @param string $pubDate                YYYY-MM-DD (faila diena)
 * @return array<string,mixed>|null
 */
function iub_parse_item(array $item, string $sourceFile, string $pubDate): ?array {
    // Virs-sliekšņa paziņojumi dublējas TED plūsmā — izlaiž.
    $legal = strtolower((string)($item['procedureLegalBasis'] ?? ''));
    if (str_contains($legal, '-over')) return null;

    $noticeId = $item['identifier'] ?? null;
    $cp = is_array($item['contactPoint'] ?? null) ? $item['contactPoint'] : [];
    if (!$noticeId) $noticeId = $cp['noticeId'] ?? null;
    if (!$noticeId) return null;

    $category = match ($item['formType'] ?? '') {
        'competition'         => 'iepirkumi',
        'result', 'execution' => 'rezultati',
        'cont-modif'          => 'izmainas',
        default               => 'citi',
    };

    $proj = is_array($item['procurementProject'] ?? null) ? $item['procurementProject'] : [];
    $title = (string)($item['name'] ?? '');
    if (trim($title) === '') $title = 'IUB ' . $noticeId;
    $description = $proj['description'] ?? null;

    // Budžets: aprēķinātā līgumsumma vai lotu tāme
    $budget = null;
    $currency = 'EUR';
    $calc = $item['automaticallyCalculated'] ?? null;
    $stmt = iub_field($calc, 'statementValue');
    $ncv = iub_field($stmt, 'noticeContractValue');
    if (is_array($ncv)) {
        if (isset($ncv['sum']) && is_numeric($ncv['sum'])) $budget = (float)$ncv['sum'];
        if (!empty($ncv['currency']) && is_string($ncv['currency'])) $currency = $ncv['currency'];
    }

    // Pasūtītājs
    $org = is_array($item['organizationData'] ?? null) ? $item['organizationData'] : [];
    $buyerName = (string)($org['name'] ?? 'Nezināms pasūtītājs');
    $buyerId = isset($org['identifier']) ? (string)$org['identifier'] : null;
    $bc = strtoupper(trim((string)($org['countryCode'] ?? 'LV')));
    $bc = TED_ISO3_TO_ISO2[$bc] ?? $bc;
    if ($bc === '' || strlen($bc) > 2) $bc = 'LV';
    $orgContact = is_array($org['defaultContactPoint'] ?? null) ? $org['defaultContactPoint'] : [];
    $buyerWeb = $org['internetAddress'] ?? ($org['websiteURIClient'] ?? null);

    // CPV
    $mainCpv = iub_cpv(is_string($item['cpvType'] ?? null) ? $item['cpvType'] : null);
    $cpv = $mainCpv !== null ? [$mainCpv] : [];
    foreach ((array)($item['additionalCpvType'] ?? []) as $c) {
        $cc = iub_cpv(is_string($c) ? $c : null);
        if ($cc !== null) $cpv[] = $cc;
    }
    $cpv = array_values(array_unique($cpv));
    sort($cpv);

    // Procedūra un dokumentu saite
    $tp = $item['tenderingProcess'] ?? null;
    $procedureType = iub_field($tp, 'procedureType');
    $docUrl = iub_field($tp, 'documentsURL');
    if (is_string($docUrl)) $docUrl = iub_fix_eis_url($docUrl);

    // Lotes + kopējais termiņš (vēlākais) + ES fondi
    $lots = [];
    $globalDeadline = null;
    $globalDeadlineTime = null;
    $isEu = false;
    $lotBudgetSum = null; // visu lošu tāmju summa (rezerve augšējā līmeņa budžetam)
    foreach ((array)($item['lots'] ?? []) as $i => $lot) {
        if (!is_array($lot)) continue;

        $ltp = $lot['tenderingProcess'] ?? null;
        $dlDate = iub_norm_date(is_string(iub_field($ltp, 'deadlineReceiptTendersEndDate')) ? iub_field($ltp, 'deadlineReceiptTendersEndDate') : null);
        $dlTime = iub_field($ltp, 'deadlineReceiptTendersEndTime');
        if ($dlDate !== null && ($globalDeadline === null || $dlDate > $globalDeadline)) {
            $globalDeadline = $dlDate;
            $globalDeadlineTime = is_string($dlTime) ? $dlTime : null;
        }

        $ltt = $lot['tenderingTerms'] ?? null;
        if (iub_field($ltt, 'isEuFunded') === true) $isEu = true;

        $place = $lot['place'] ?? null;
        $lotBudget = null;
        $ai = $lot['additionalInformation'] ?? null;
        foreach (['estimatedValue', 'maximumValue', 'value'] as $bf) {
            $v = iub_field($ai, $bf);
            if ($v !== null && is_numeric($v)) { $lotBudget = (float)$v; break; }
        }
        // Summē PIRMS KONKURSI_MAX_LOTS apcirpšanas — citādi zaudētu tālāko lošu vērtību.
        if ($lotBudget !== null) $lotBudgetSum = ($lotBudgetSum ?? 0.0) + $lotBudget;

        if (count($lots) < KONKURSI_MAX_LOTS) {
            $lc = strtoupper((string)(iub_field($place, 'placePerformanceCountryCode') ?? $bc));
            $lots[] = array_filter([
                'id'            => (string)($lot['id'] ?? ('Daļa ' . ($i + 1))),
                'name'          => ted_truncate(is_string($lot['name'] ?? null) ? $lot['name'] : null, 200),
                'description'   => ted_truncate(is_string($lot['description'] ?? null) ? $lot['description'] : null, KONKURSI_LOT_DESC_MAX),
                'nature'        => $proj['mainNatureType'] ?? null,
                'cpv_codes'     => $mainCpv !== null ? [$mainCpv] : null,
                'budget'        => $lotBudget,
                'nuts'          => iub_field($place, 'placePerformanceCountrySubCode'),
                'country'       => TED_ISO3_TO_ISO2[$lc] ?? ($lc !== '' ? $lc : null),
                'deadline_date' => $dlDate,
                'deadline_time' => is_string($dlTime) ? $dlTime : null,
            ], fn($v) => $v !== null && $v !== '-');
        }
    }

    // Izsludinājumiem IUB neaizpilda noticeContractValue (tas parādās tikai rezultātos),
    // bet lotēm tāmes mēdz būt — bez šīs rezerves visiem aktīvajiem konkursiem budžets
    // paliktu NULL un kārtošana pēc budžeta tos nogrūstu saraksta beigās.
    if ($budget === null && $lotBudgetSum !== null) $budget = $lotBudgetSum;

    // Publikācijas numurs
    $pubNum = null;
    if (!empty($cp['noticeId'])) $pubNum = (string)$cp['noticeId'];
    if ($pubNum === null && !empty($proj['procurementIdentifier'])) $pubNum = (string)$proj['procurementIdentifier'];

    // Kontakti
    $contact = array_filter([
        'name'  => $cp['name'] ?? ($orgContact['name'] ?? null),
        'email' => $cp['electronicMail'] ?? ($orgContact['electronicMail'] ?? null),
        'phone' => $cp['telephone'] ?? ($orgContact['telephone'] ?? null),
    ], fn($v) => is_string($v) && $v !== '' && $v !== '-');

    // ks_public_org: kontaktpersonas vārdu/tālruni izmet, e-pastu patur tikai
    // iestādes adresēm — tā pati politika kā notice_contact laukam.
    $orgList = [array_filter(ks_public_org([
        'id'         => $buyerId,
        'name'       => $buyerName,
        'reg_number' => $buyerId,
        'country'    => $bc,
        'nuts'       => $org['nutsCode'] ?? null,
        'city'       => $org['city'] ?? null,
        'postal'     => $org['postCode'] ?? null,
        'street'     => $org['street'] ?? null,
        'website'    => $buyerWeb,
        'contact'    => $orgContact['name'] ?? null,
        'email'      => $orgContact['electronicMail'] ?? null,
        'phone'      => $orgContact['telephone'] ?? null,
    ]), fn($v) => is_string($v) && $v !== '' && $v !== '-')];

    return [
        'id'                 => (string)$noticeId,
        'source'             => 'IUB',
        'category'           => $category,
        'title'              => ted_truncate($title, 400) ?? ('IUB ' . $noticeId),
        'description'        => ted_truncate(is_string($description) ? $description : null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyerName, 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => $bc,
        'buyer_activity'     => is_string($org['authorityActivity'] ?? null) ? $org['authorityActivity'] : null,
        'buyer_type'         => is_string($org['buyerLegalType'] ?? null) ? $org['buyerLegalType'] : null,
        'procure_nature'     => is_string($proj['mainNatureType'] ?? null) ? $proj['mainNatureType'] : null,
        'publication_date'   => $pubDate,
        'deadline_date'      => $globalDeadline,
        'deadline_time'      => $globalDeadlineTime,
        'publication_number' => $pubNum,
        'budget'             => $budget,
        'currency'           => $currency,
        'document_url'       => is_string($docUrl) ? $docUrl : null,
        'buyer_profile_url'  => is_string($buyerWeb) ? $buyerWeb : null,
        'procedure_type'     => is_string($procedureType) ? $procedureType : null,
        'notice_sub_type'    => is_string($item['noticeType'] ?? null) ? $item['noticeType'] : null,
        'notice_lang'        => 'LV',
        'issue_date'         => $pubDate,
        'main_nuts'          => is_string($org['nutsCode'] ?? null) ? $org['nutsCode'] : null,
        'main_country'       => $bc,
        'funding_program'    => $isEu ? 'EU' : 'no-eu-funds',
        'prev_notice_ref'    => is_string($item['previousIdentifier'] ?? null) ? $item['previousIdentifier'] : null,
        'contract_folder_id' => is_string($item['procurementProcedureIdentifier'] ?? null) ? $item['procurementProcedureIdentifier'] : null,
        'main_cpv'           => $mainCpv,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => json_encode($lots, JSON_UNESCAPED_UNICODE),
        'organizations'      => json_encode($orgList, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => json_encode(ks_public_contact($contact) ?: new stdClass(), JSON_UNESCAPED_UNICODE),
        'source_file'        => $sourceFile,
    ];
}
