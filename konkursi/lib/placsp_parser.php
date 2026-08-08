<?php
/**
 * konkursi/lib/placsp_parser.php — Spānijas PLACSP (CODICE ATOM) parseris.
 *
 * PHP ports no ted/konkursi/plugins/es_nat/parser.py (pierādīti strādājošs
 * risinājums ar reāliem datiem). CODICE elementu prefiksi variē (cac, cbc,
 * cac-place-ext...), tāpēc meklēšana notiek pēc lokālā vārda, ignorējot
 * vārdtelpas — tāpat kā oriģināla find_local().
 *
 * Dedup pret TED: PLACSP satur ARĪ virs ES sliekšņa esošos (tie nonāk TED),
 * bet ATOM ierakstos nav tieša karoga — izmanto vērtības slieksni.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php';     // ted_truncate(), ted_norm_date()
require_once __DIR__ . '/nordics_parser.php'; // nord_iso_dt()

/** Pirmais pēctecis ar doto lokālo vārdu (BFS, vārdtelpas ignorē). */
function es_first(DOMNode $el, string $local): ?DOMElement {
    $queue = [$el];
    while ($queue) {
        $node = array_shift($queue);
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMElement) {
                if ($c->localName === $local) return $c;
                $queue[] = $c;
            }
        }
    }
    return null;
}

/** Visi pēcteči ar doto lokālo vārdu. @return DOMElement[] */
function es_all(DOMNode $el, string $local): array {
    $out = [];
    $queue = [$el];
    while ($queue) {
        $node = array_shift($queue);
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMElement) {
                if ($c->localName === $local) $out[] = $c;
                $queue[] = $c;
            }
        }
    }
    return $out;
}

/** Pirmā pēcteča teksts. */
function es_text(DOMNode $el, string $local): ?string {
    $n = es_first($el, $local);
    if ($n === null) return null;
    $t = trim($n->textContent);
    return $t !== '' ? $t : null;
}

/**
 * Parsē vienu ATOM <entry> (kā DOMElement).
 * @return array<string,mixed>|null null = izlaižams (virs ES sliekšņa u.tml.)
 */
function placsp_parse_entry(DOMElement $entry): ?array {
    // Atom pamatlauki (tiešie bērni)
    $atomId = null; $title = null; $summary = null; $updated = null; $linkHref = null;
    foreach ($entry->childNodes as $c) {
        if (!$c instanceof DOMElement) continue;
        switch ($c->localName) {
            case 'id': $atomId = trim($c->textContent); break;
            case 'title': $title = trim($c->textContent); break;
            case 'summary': $summary = trim($c->textContent); break;
            case 'updated': $updated = trim($c->textContent); break;
            case 'link': if ($linkHref === null) $linkHref = $c->getAttribute('href'); break;
        }
    }
    if ($atomId === null || $title === null || $title === '') return null;

    $folderId = es_text($entry, 'ContractFolderID');
    $statusCode = strtoupper((string)es_text($entry, 'ContractFolderStatusCode'));

    // Pasūtītājs
    $buyerName = null; $buyerId = null;
    $cp = es_first($entry, 'LocatedContractingParty') ?? es_first($entry, 'ContractingParty');
    if ($cp !== null) {
        $party = es_first($cp, 'Party');
        if ($party !== null) {
            $pn = es_first($party, 'PartyName');
            if ($pn !== null) $buyerName = es_text($pn, 'Name');
            foreach (es_all($party, 'PartyIdentification') as $pid) {
                $idEl = es_first($pid, 'ID');
                if ($idEl !== null && trim($idEl->textContent) !== '') {
                    $val = trim($idEl->textContent);
                    if ($idEl->getAttribute('schemeName') === 'NIF' || $buyerId === null) $buyerId = $val;
                }
            }
        }
    }

    // Projekts: veids, CPV, budžets
    $nature = null; $cpv = []; $budget = null; $currency = 'EUR';
    $proj = es_first($entry, 'ProcurementProject');
    $description = $summary;
    if ($proj !== null) {
        $projName = es_text($proj, 'Name');
        if ($projName !== null && ($description === null || mb_strlen($description) < mb_strlen($projName))) {
            $description = $projName;
        }
        $tc = mb_strtolower((string)es_text($proj, 'TypeCode'), 'UTF-8');
        if (str_contains($tc, 'obras') || str_contains($tc, 'works') || $tc === '1' || $tc === '31') $nature = 'works';
        elseif (str_contains($tc, 'suministro') || str_contains($tc, 'supplies') || $tc === '2' || $tc === '32') $nature = 'supplies';
        elseif (str_contains($tc, 'servicio') || str_contains($tc, 'services') || $tc === '8' || $tc === '33') $nature = 'services';
        foreach (es_all($proj, 'ItemClassificationCode') as $c) {
            if (preg_match('/^(\d{8})/', trim($c->textContent), $m)) $cpv[] = $m[1];
        }
        $ba = es_first($proj, 'BudgetAmount');
        if ($ba !== null) {
            $amt = es_first($ba, 'TaxExclusiveAmount') ?? es_first($ba, 'TotalAmount') ?? es_first($ba, 'EstimatedOverallContractAmount');
            if ($amt !== null && is_numeric(trim($amt->textContent))) {
                $budget = (float)trim($amt->textContent);
                if ($amt->getAttribute('currencyID') !== '') $currency = $amt->getAttribute('currencyID');
            }
        }
    }
    $cpv = array_values(array_unique($cpv));

    // Rezultāts (uzvarētājs, piešķirtā summa)
    $winnerName = null; $winnerId = null; $awardDate = null;
    $tr = es_first($entry, 'TenderResult');
    if ($tr !== null) {
        $wp = es_first($tr, 'WinningParty') ?? es_first($tr, 'WinnerParty');
        if ($wp !== null) {
            $pn = es_first($wp, 'PartyName');
            if ($pn !== null) $winnerName = es_text($pn, 'Name');
            $pi = es_first($wp, 'PartyIdentification');
            if ($pi !== null) $winnerId = es_text($pi, 'ID');
        }
        $pa = es_first($tr, 'PayableAmount');
        if ($pa !== null && is_numeric(trim($pa->textContent))) {
            $budget = (float)trim($pa->textContent);
            if ($pa->getAttribute('currencyID') !== '') $currency = $pa->getAttribute('currencyID');
        }
        $awardDate = ted_norm_date(es_text($tr, 'AwardDate'));
    }

    // Dedup pret TED: virs ES sliekšņa esošie iet uz TED plūsmu
    if ($budget !== null) {
        $limit = $nature === 'works' ? PLACSP_EU_THRESHOLD_WORKS : PLACSP_EU_THRESHOLD_SERVICES;
        if ($budget >= $limit) return null;
    }

    // Termiņš
    $dlDate = null; $dlTime = null;
    $tp = es_first($entry, 'TenderingProcess');
    if ($tp !== null) {
        $dp = es_first($tp, 'TenderSubmissionDeadlinePeriod');
        if ($dp !== null) {
            $dlDate = ted_norm_date(es_text($dp, 'EndDate'));
            $t = es_text($dp, 'EndTime');
            if (is_string($t) && preg_match('/^(\d{2}:\d{2})/', $t, $m)) $dlTime = $m[1];
        }
    }

    $pubDate = null;
    if (is_string($updated)) $pubDate = ted_norm_date($updated);
    if ($pubDate === null) $pubDate = $awardDate;

    // Kategorija: statusa kods, atkāpe — termiņa heiristika (kā oriģinālā)
    $today = konkursi_today();
    $category = match ($statusCode) {
        'PUB'         => ($dlDate === null || $dlDate >= $today) ? 'iepirkumi' : 'rezultati',
        'EV', 'ADJ', 'RES' => 'rezultati',
        'ANUL'        => 'citi',
        default       => ($dlDate !== null && $dlDate >= $today) ? 'iepirkumi' : 'rezultati',
    };

    $procRaw = (string)es_text($entry, 'ProcedureCode');
    $proc = in_array($procRaw, ['6', '9'], true) ? 'Contrato menor' : ($procRaw !== '' ? $procRaw : null);

    // Stabils id: PLACSP sindikācijas numurs no atom id URL (pēdējais ceļa posms)
    $numId = preg_match('#/(\d+)$#', $atomId, $m) ? $m[1] : md5($atomId);

    $orgs = [];
    if ($buyerName !== null) $orgs[] = array_filter(['name' => $buyerName, 'reg_number' => $buyerId, 'country' => 'ES']);
    if ($winnerName !== null) $orgs[] = array_filter(['name' => $winnerName . ' (uzvarētājs)', 'reg_number' => $winnerId, 'country' => 'ES']);

    return [
        'id'                 => 'PLACSP-' . $numId,
        'source'             => 'PLACSP',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate($description, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyerName ?? 'Nezināms pasūtītājs', 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'ES',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $folderId ?? $numId,
        'budget'             => $budget,
        'currency'           => $currency,
        'document_url'       => is_string($linkHref) && $linkHref !== '' ? $linkHref : null,
        'buyer_profile_url'  => null,
        'procedure_type'     => $proc,
        'notice_sub_type'    => $statusCode !== '' ? $statusCode : null,
        'notice_lang'        => 'ES',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'ES',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $folderId,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'placsp-atom',
    ];
}

/**
 * Parsē vienu ATOM failu; atgriež [rindas masīvs, nākamās lapas URL vai null].
 * @return array{0: array<int,array<string,mixed>>, 1: ?string}
 */
function placsp_parse_atom(string $xml): array {
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok || $doc->documentElement === null) return [[], null];

    $next = null;
    foreach ($doc->documentElement->childNodes as $c) {
        if ($c instanceof DOMElement && $c->localName === 'link' && $c->getAttribute('rel') === 'next') {
            $next = $c->getAttribute('href') ?: null;
        }
    }
    $rows = [];
    foreach ($doc->getElementsByTagName('*') as $el) {
        // ātrā filtrēšana: tikai saknes līmeņa <entry>
        if ($el->localName === 'entry' && $el->parentNode === $doc->documentElement) {
            $n = placsp_parse_entry($el);
            if ($n !== null) $rows[] = $n;
        }
    }
    return [$rows, $next];
}
