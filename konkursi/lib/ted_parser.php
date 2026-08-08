<?php
/**
 * konkursi/lib/ted_parser.php — TED eForms (UBL 2.3) XML → notices rinda.
 *
 * PHP ports no ted/konkursi/scripts/import_from_explorer.py parse_ted_xml(),
 * ar labojumiem: korekta budžeta nolasīšana (cbc:EstimatedOverallContractAmount
 * ir pats summas elements ar @currencyID), termiņš = VĒLĀKAIS no lotu termiņiem
 * (konkurss aktīvs, kamēr atvērta kaut viena daļa), datumi normalizēti uz
 * YYYY-MM-DD, kontakti no pasūtītāja organizācijas.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const TED_XML_NS = [
    'cac'  => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
    'cbc'  => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    'efac' => 'http://data.europa.eu/p27/eforms-ubl-extension-aggregate-components/1',
    'efbc' => 'http://data.europa.eu/p27/eforms-ubl-extension-basic-components/1',
    'ext'  => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
];

const TED_ISO3_TO_ISO2 = [
    'LVA' => 'LV', 'LTU' => 'LT', 'EST' => 'EE', 'DEU' => 'DE', 'FRA' => 'FR',
    'ITA' => 'IT', 'ESP' => 'ES', 'POL' => 'PL', 'NLD' => 'NL', 'BEL' => 'BE',
    'SWE' => 'SE', 'DNK' => 'DK', 'FIN' => 'FI', 'AUT' => 'AT', 'PRT' => 'PT',
    'CZE' => 'CZ', 'ROU' => 'RO', 'HUN' => 'HU', 'GRC' => 'GR', 'BGR' => 'BG',
    'HRV' => 'HR', 'SVK' => 'SK', 'SVN' => 'SI', 'LUX' => 'LU', 'CYP' => 'CY',
    'MLT' => 'MT', 'IRL' => 'IE', 'NOR' => 'NO', 'ISL' => 'IS', 'CHE' => 'CH',
    'GBR' => 'GB', 'TUR' => 'TR', 'SRB' => 'RS', 'ALB' => 'AL', 'MKD' => 'MK',
    'MNE' => 'ME', 'BIH' => 'BA', 'UKR' => 'UA', 'MDA' => 'MD', 'GEO' => 'GE',
    'LIE' => 'LI', 'AND' => 'AD', 'MCO' => 'MC', 'SMR' => 'SM',
];

/** '2026-06-28+02:00' / '2026-06-28Z' / '2026-06-28 10:00' → '2026-06-28' (vai null). */
function ted_norm_date(?string $s): ?string {
    if ($s === null) return null;
    $s = trim($s);
    if ($s === '' || $s === '-') return null;
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) return null;
    return $m[1];
}

/** Apcērt UTF-8 tekstu līdz $max, pie vārda robežas, pieliek daudzpunkti. */
function ted_truncate(?string $s, int $max): ?string {
    if ($s === null) return null;
    $s = trim($s);
    if ($s === '' || $s === '-') return null;
    if (mb_strlen($s, 'UTF-8') <= $max) return $s;
    $cut = mb_substr($s, 0, $max, 'UTF-8');
    $sp = mb_strrpos($cut, ' ', 0, 'UTF-8');
    if ($sp !== false && $sp > (int)($max * 0.6)) $cut = mb_substr($cut, 0, $sp, 'UTF-8');
    return rtrim($cut) . '…';
}

/**
 * Parsē vienu TED eForms XML dokumentu.
 * @param string|null $defaultCountry ISO-2 valsts atkāpe, ja pircēja valsts XML nav
 *                                    norādīta (nacionālās vienkāršotās formas, piem., DE);
 *                                    null = strikti (bez valsts → null, kā TED plūsmai)
 * @return array<string,mixed>|null notices rindas masīvs vai null, ja neizmantojams
 */
function ted_parse_xml(string $xml, string $sourceFile, ?string $defaultCountry = null): ?array {
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok || $doc->documentElement === null) return null;

    $xp = new DOMXPath($doc);
    foreach (TED_XML_NS as $p => $uri) $xp->registerNamespace($p, $uri);

    // Pirmā atbilstošā mezgla teksts (vai $def)
    $gv = function (string $expr, ?DOMNode $ctx = null, ?string $def = null) use ($xp): ?string {
        $list = $xp->query($expr, $ctx);
        if ($list !== false && $list->length > 0) {
            $t = trim($list->item(0)->textContent);
            if ($t !== '') return $t;
        }
        return $def;
    };
    $gn = function (string $expr, ?DOMNode $ctx = null) use ($xp): ?DOMNode {
        $list = $xp->query($expr, $ctx);
        return ($list !== false && $list->length > 0) ? $list->item(0) : null;
    };

    $noticeId = $gv('/*/cbc:ID') ?? $gv('//cbc:ID');
    if ($noticeId === null) return null;

    // Kategorija pēc saknes elementa + izmaiņu pazīmes
    $rootTag = $doc->documentElement->localName;
    if ($xp->query('//efac:Changes')->length > 0) {
        $category = 'izmainas';
    } elseif ($rootTag === 'ContractNotice') {
        $category = 'iepirkumi';
    } elseif ($rootTag === 'ContractAwardNotice') {
        $category = 'rezultati';
    } else {
        $category = 'citi';
    }

    $title = $gv('/*/cac:ProcurementProject/cbc:Name') ?? $gv('//cac:ProcurementProject/cbc:Name');
    if ($title === null) $title = 'TED ' . $noticeId;
    $description = $gv('/*/cac:ProcurementProject/cbc:Description')
        ?? $gv('//cac:ProcurementProject/cbc:Description');

    // Budžets: galvenā tāme; rezultātiem — kopējā piešķirtā summa
    $budget = null;
    $currency = 'EUR';
    $amtNode = $gn('/*/cac:ProcurementProject/cac:RequestedTenderTotal/cbc:EstimatedOverallContractAmount')
        ?? $gn('//cac:RequestedTenderTotal/cbc:EstimatedOverallContractAmount')
        ?? $gn('//efac:NoticeResult/cbc:TotalAmount');
    if ($amtNode instanceof DOMElement) {
        $v = trim($amtNode->textContent);
        // TED lieto -1 (un dažkārt 0) kā sentinelu "vērtība nav atklāta" — tā nav
        // īsta summa; glabājam null, citādi kartiņā parādās "−1 €".
        if ($v !== '' && is_numeric($v) && (float)$v > 0) $budget = (float)$v;
        $cur = $amtNode->getAttribute('currencyID');
        if ($cur !== '') $currency = $cur;
    }

    // Organizācijas (id → dati) — vajadzīgas pasūtītāja atšifrēšanai un sarakstam
    $orgs = [];
    foreach ($xp->query('//efac:Organizations/efac:Organization') as $orgEl) {
        $comp = $gn('efac:Company', $orgEl);
        if (!$comp instanceof DOMNode) continue;
        $oid = $gv('cac:PartyIdentification/cbc:ID', $comp);
        if ($oid === null) continue;
        $oc = strtoupper((string)$gv('cac:PostalAddress/cac:Country/cbc:IdentificationCode', $comp));
        $orgs[$oid] = [
            'id'         => $oid,
            'name'       => $gv('cac:PartyName/cbc:Name', $comp),
            'reg_number' => $gv('cac:PartyLegalEntity/cbc:CompanyID', $comp),
            'country'    => TED_ISO3_TO_ISO2[$oc] ?? ($oc !== '' ? $oc : null),
            'nuts'       => $gv('cac:PostalAddress/cbc:CountrySubentityCode', $comp),
            'city'       => $gv('cac:PostalAddress/cbc:CityName', $comp),
            'postal'     => $gv('cac:PostalAddress/cbc:PostalZone', $comp),
            'street'     => $gv('cac:PostalAddress/cbc:StreetName', $comp),
            'website'    => $gv('cbc:WebsiteURI', $comp),
            'contact'    => $gv('cac:Contact/cbc:Name', $comp),
            'email'      => $gv('cac:Contact/cbc:ElectronicMail', $comp),
            'phone'      => $gv('cac:Contact/cbc:Telephone', $comp),
        ];
    }

    // Pasūtītājs
    $buyerName = 'Nezināms pasūtītājs';
    $buyerCountry = null;
    $buyerId = null;
    $buyerWeb = null;
    $contact = [];
    $buyerPartyId = $gv('//cac:ContractingParty/cac:Party/cac:PartyIdentification/cbc:ID');
    if ($buyerPartyId !== null && isset($orgs[$buyerPartyId])) {
        $o = $orgs[$buyerPartyId];
        $buyerName = $o['name'] ?? $buyerName;
        $buyerCountry = $o['country'];
        $buyerId = $o['reg_number'] ?? $buyerPartyId;
        $contact = array_filter([
            'name'  => $o['contact'],
            'email' => $o['email'],
            'phone' => $o['phone'],
        ], fn($v) => $v !== null && $v !== '-');
    } else {
        // Atkāpe: vienkāršotās eForms formas (piem., Vācijas eForms-DE nacionālās)
        // pasūtītāju apraksta tieši ContractingParty/Party, bez efac:Organizations
        $party = $gn('//cac:ContractingParty/cac:Party');
        if ($party instanceof DOMNode) {
            $buyerName = $gv('cac:PartyName/cbc:Name', $party) ?? $buyerName;
            $buyerCountry = $gv('cac:PostalAddress/cac:Country/cbc:IdentificationCode', $party);
            $buyerId = $gv('cac:PartyLegalEntity/cbc:CompanyID', $party);
            $buyerWeb = $gv('cbc:WebsiteURI', $party);
            $contact = array_filter([
                'name'  => $gv('cac:Contact/cbc:Name', $party),
                'email' => $gv('cac:Contact/cbc:ElectronicMail', $party),
                'phone' => $gv('cac:Contact/cbc:Telephone', $party),
            ], fn($v) => $v !== null && $v !== '-');
            $orgs['__buyer'] = array_filter([
                'id'      => 'ORG-BUYER',
                'name'    => $buyerName,
                'country' => $buyerCountry,
                'city'    => $gv('cac:PostalAddress/cbc:CityName', $party),
                'street'  => $gv('cac:PostalAddress/cbc:StreetName', $party),
                'postal'  => $gv('cac:PostalAddress/cbc:PostalZone', $party),
                'website' => $buyerWeb,
                'email'   => $contact['email'] ?? null,
                'phone'   => $contact['phone'] ?? null,
            ], fn($v) => $v !== null);
        }
    }

    // Valsts koda normalizācija (ISO3 → ISO2); atkāpes: izpildes vieta → noklusējums
    if ($buyerCountry === null) {
        $buyerCountry = $gv('/*/cac:ProcurementProject/cac:RealizedLocation/cac:Address/cac:Country/cbc:IdentificationCode');
    }
    $bc = strtoupper(trim((string)$buyerCountry));
    if (isset(TED_ISO3_TO_ISO2[$bc])) $bc = TED_ISO3_TO_ISO2[$bc];
    if ($bc === '' || $bc === '-' || strlen($bc) > 2) {
        if ($defaultCountry === null) return null;
        $bc = $defaultCountry;
    }

    // CPV kodi
    $mainCpv = null;
    $cpv = [];
    foreach ($xp->query('//cac:MainCommodityClassification/cbc:ItemClassificationCode') as $c) {
        if ($c instanceof DOMElement && strtoupper($c->getAttribute('listName')) === 'CPV') {
            $v = trim($c->textContent);
            if ($v !== '') {
                $cpv[] = $v;
                if ($mainCpv === null) $mainCpv = $v;
            }
        }
    }
    // Galvenais CPV — prioritāri no galvenā projekta, ne no lotēm
    $mainProjCpv = $gv('/*/cac:ProcurementProject/cac:MainCommodityClassification/cbc:ItemClassificationCode');
    if ($mainProjCpv !== null) $mainCpv = $mainProjCpv;
    foreach ($xp->query('//cac:AdditionalCommodityClassification/cbc:ItemClassificationCode') as $c) {
        if ($c instanceof DOMElement && strtoupper($c->getAttribute('listName')) === 'CPV') {
            $v = trim($c->textContent);
            if ($v !== '') $cpv[] = $v;
        }
    }
    $cpv = array_values(array_unique($cpv));
    sort($cpv);

    // Publikācijas numurs un datums
    $pubId = $gv('//efac:Publication/efbc:NoticePublicationID') ?? $gv('//efbc:NoticePublicationID');
    $publicationNumber = null;
    if ($pubId !== null) {
        $parts = explode('-', $pubId);
        if (count($parts) === 2 && ctype_digit($parts[0])) {
            $publicationNumber = ((int)$parts[0]) . '-' . $parts[1]; // '00412345-2026' → '412345-2026'
        } else {
            $publicationNumber = $pubId;
        }
    }
    $pubDate = ted_norm_date($gv('//efac:Publication/efbc:PublicationDate'))
        ?? ted_norm_date($gv('/*/cbc:IssueDate'));

    // Lotes (apcirstas) + kopējais termiņš = vēlākais no lotu termiņiem
    $lots = [];
    $globalDeadline = null;
    $globalDeadlineTime = null;
    $globalFunding = null;
    foreach ($xp->query('//cac:ProcurementProjectLot') as $lotEl) {
        $lotId = $gv('cbc:ID', $lotEl);
        if ($lotId === null) continue;
        $lotProc = $gn('cac:TenderingProcess', $lotEl);
        $lotProj = $gn('cac:ProcurementProject', $lotEl);
        $lotTerms = $gn('cac:TenderingTerms', $lotEl);

        $dlDate = null; $dlTime = null;
        if ($lotProc instanceof DOMNode) {
            $dlDate = ted_norm_date($gv('cac:TenderSubmissionDeadlinePeriod/cbc:EndDate', $lotProc));
            $dlTime = $gv('cac:TenderSubmissionDeadlinePeriod/cbc:EndTime', $lotProc);
            if ($dlDate === null) {
                $dlDate = ted_norm_date($gv('cac:ParticipationRequestReceptionPeriod/cbc:EndDate', $lotProc));
                $dlTime = $gv('cac:ParticipationRequestReceptionPeriod/cbc:EndTime', $lotProc);
            }
        }
        if ($dlDate !== null && ($globalDeadline === null || $dlDate > $globalDeadline)) {
            $globalDeadline = $dlDate;
            $globalDeadlineTime = $dlTime;
        }

        $fp = $lotTerms instanceof DOMNode ? $gv('cbc:FundingProgramCode', $lotTerms) : null;
        if ($fp !== null) $globalFunding = $fp;

        $lotCpv = [];
        $lotBudget = null;
        if ($lotProj instanceof DOMNode) {
            foreach ($xp->query('cac:MainCommodityClassification/cbc:ItemClassificationCode', $lotProj) as $c) {
                if ($c instanceof DOMElement && strtoupper($c->getAttribute('listName')) === 'CPV') {
                    $v = trim($c->textContent);
                    if ($v !== '') $lotCpv[] = $v;
                }
            }
            $lb = $gn('cac:RequestedTenderTotal/cbc:EstimatedOverallContractAmount', $lotProj);
            if ($lb instanceof DOMElement && is_numeric(trim($lb->textContent)) && (float)trim($lb->textContent) > 0) {
                $lotBudget = (float)trim($lb->textContent);
            }
        }

        $lc = $lotProj ? strtoupper((string)$gv('cac:RealizedLocation/cac:Address/cac:Country/cbc:IdentificationCode', $lotProj)) : '';
        if (count($lots) < KONKURSI_MAX_LOTS) {
            $lots[] = array_filter([
                'id'            => $lotId,
                'name'          => $lotProj ? ted_truncate($gv('cbc:Name', $lotProj), 200) : null,
                'description'   => $lotProj ? ted_truncate($gv('cbc:Description', $lotProj), KONKURSI_LOT_DESC_MAX) : null,
                'nature'        => $lotProj ? $gv('cbc:ProcurementTypeCode', $lotProj) : null,
                'cpv_codes'     => $lotCpv ?: null,
                'budget'        => $lotBudget,
                'nuts'          => $lotProj ? $gv('cac:RealizedLocation/cac:Address/cbc:CountrySubentityCode', $lotProj) : null,
                'country'       => TED_ISO3_TO_ISO2[$lc] ?? ($lc !== '' ? $lc : null),
                'deadline_date' => $dlDate,
                'deadline_time' => $dlTime,
                'funding_program' => $fp,
            ], fn($v) => $v !== null);
        }
    }

    // Organizāciju saraksts (apcirsts). ks_public_org noņem kontaktpersonas
    // vārdu/tālruni un personiskos e-pastus — tāpat kā notice_contact laukam.
    $orgList = [];
    foreach ($orgs as $o) {
        if (count($orgList) >= KONKURSI_MAX_ORGS) break;
        $orgList[] = array_filter(ks_public_org($o), fn($v) => $v !== null && $v !== '-');
    }

    return [
        'id'                 => $noticeId,
        'source'             => 'TED',
        'category'           => $category,
        'title'              => ted_truncate($title, 400) ?? ('TED ' . $noticeId),
        'description'        => ted_truncate($description, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyerName, 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => $bc,
        'buyer_activity'     => $gv('//cac:ContractingParty/cac:ContractingActivity/cbc:ActivityTypeCode'),
        'buyer_type'         => $gv('//cac:ContractingParty/cac:ContractingPartyType/cbc:PartyTypeCode'),
        'procure_nature'     => $gv('/*/cac:ProcurementProject/cbc:ProcurementTypeCode')
                                ?? $gv('//cac:ProcurementProject/cbc:ProcurementTypeCode'),
        'publication_date'   => $pubDate,
        'deadline_date'      => $globalDeadline,
        'deadline_time'      => $globalDeadlineTime,
        'publication_number' => $publicationNumber,
        'budget'             => $budget,
        'currency'           => $currency,
        'document_url'       => $gv('//cac:CallForTendersDocumentReference/cac:Attachment/cac:ExternalReference/cbc:URI'),
        'buyer_profile_url'  => $gv('//cac:ContractingParty/cbc:BuyerProfileURI') ?? $buyerWeb,
        'procedure_type'     => $gv('//cac:TenderingProcess/cbc:ProcedureCode'),
        'notice_sub_type'    => $gv('//efac:NoticeSubType/cbc:SubTypeCode'),
        'notice_lang'        => $gv('/*/cbc:NoticeLanguageCode'),
        'issue_date'         => ted_norm_date($gv('/*/cbc:IssueDate')),
        'main_nuts'          => $gv('/*/cac:ProcurementProject/cac:RealizedLocation/cac:Address/cbc:CountrySubentityCode'),
        'main_country'       => $gv('/*/cac:ProcurementProject/cac:RealizedLocation/cac:Address/cac:Country/cbc:IdentificationCode'),
        'funding_program'    => $globalFunding,
        'prev_notice_ref'    => $gv('//cac:TenderingProcess/cac:NoticeDocumentReference/cbc:ID'),
        'contract_folder_id' => $gv('/*/cbc:ContractFolderID'),
        'main_cpv'           => $mainCpv,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => json_encode($lots, JSON_UNESCAPED_UNICODE),
        'organizations'      => json_encode($orgList, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => json_encode(ks_public_contact($contact) ?: new stdClass(), JSON_UNESCAPED_UNICODE),
        'source_file'        => $sourceFile,
    ];
}
