<?php
/**
 * konkursi/lib/ocds_parser.php — ne-ES OCDS/OpenProcurement avoti: Ukraina
 * (Prozorro), Moldova (MTender), Gruzija (SPA). Visi seko OCDS 1.1 / atvērto
 * līgumu standartam un publicē VISUS iepirkumus neatkarīgi no vērtības, tāpēc
 * mazie (zem-sliekšņa) konkursi te ir pieejami un uz ES TED NEnonāk.
 *
 * Dedup pret TED nav vajadzīgs: TED satur tikai virs-sliekšņa iepirkumus, bet
 * šie parseri importē tikai mazās procedūras — kopas ir disjunktas.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php'; // ted_truncate()

/** OCDS/ISO datums ("2026-07-23T09:00:00+03:00") → ['Y-m-d','H:i'] vai [null,null]. */
function ocds_dt(?string $iso): array {
    if (!is_string($iso) || $iso === '') return [null, null];
    try {
        $d = new DateTimeImmutable($iso);
        return [$d->format('Y-m-d'), $d->format('H:i')];
    } catch (Throwable $e) {
        return [null, null];
    }
}

/**
 * Izvelk 8-ciparu CPV kodus no OCDS items[].classification (+ additionalClass.).
 * DK021 (UA), CPV (MD/GE) un ES CPV ir savstarpēji saderīgi 8-ciparu kodi.
 */
function ocds_cpv_from_items(array $items): array {
    $cpv = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        foreach (array_merge([$it['classification'] ?? null], (array)($it['additionalClassifications'] ?? [])) as $c) {
            if (is_array($c) && preg_match('/(\d{8})/', (string)($c['id'] ?? ''), $m)) $cpv[] = $m[1];
        }
    }
    return array_values(array_unique($cpv));
}

/** OCDS mainProcurementCategory → mūsu procure_nature. */
function ocds_nature(?string $cat): ?string {
    return match ((string)$cat) {
        'works'    => 'works',
        'goods'    => 'supplies',
        'services' => 'services',
        default    => null,
    };
}

// ─────────────────────────── Prozorro (Ukraina) ──────────────────────────────

/** Prozorro procurementMethodType → cilvēklasāms procedūras nosaukums. */
function prozorro_proc(string $t): ?string {
    return match ($t) {
        'belowThreshold'         => 'Zem-sliekšņa (belowThreshold)',
        'priceQuotation'         => 'Cenu aptauja (priceQuotation)',
        'reporting'              => 'Tiešais līgums (reporting)',
        'aboveThreshold',
        'aboveThresholdUA',
        'aboveThresholdEU'       => 'Atklāts konkurss (aboveThreshold)',
        'competitiveDialogueUA',
        'competitiveDialogueEU'  => 'Konkursa dialogs (competitiveDialogue)',
        'competitiveOrdering'    => 'Konkurences pasūtījums (competitiveOrdering)',
        'esco'                   => 'ESCO procedūra',
        'requestForProposal'     => 'Priekšlikumu pieprasījums (RFP)',
        'closeFrameworkAgreementUA' => 'Vispārīgā vienošanās (framework)',
        default                  => null,
    };
}

/**
 * Pilns Prozorro tender objekts (/tenders/{id} → data) → notices rinda vai null.
 * $category — 'iepirkumi' (active.*) vai 'rezultati' (complete/reporting).
 */
function prozorro_notice(array $t, string $category): ?array {
    $id = trim((string)($t['id'] ?? ''));
    $title = trim((string)($t['title'] ?? ''));
    if ($id === '' || $title === '') return null;

    $mt = (string)($t['procurementMethodType'] ?? '');
    // Drošības sieta atkārtojums: pieņemam konkurētspējīgās procedūras (arī virs-sliekšņa;
    // Ukraina nav TED). directAward/negotiation atsijā ks_sync_prozorro.
    if (!in_array($mt, PROZORRO_ACTIVE_TYPES, true)) return null;

    $tenderID = trim((string)($t['tenderID'] ?? ''));
    $cur = 'UAH'; $budget = null;
    if (is_array($t['value'] ?? null) && isset($t['value']['amount']) && is_numeric($t['value']['amount'])) {
        $budget = (float)$t['value']['amount'];
        if (!empty($t['value']['currency'])) $cur = (string)$t['value']['currency'];
    }

    $cpv = ocds_cpv_from_items((array)($t['items'] ?? []));
    [$pubDate] = ocds_dt($t['noticePublicationDate'] ?? ($t['date'] ?? null));
    [$dlDate, $dlTime] = ocds_dt($t['tenderPeriod']['endDate'] ?? null);

    $ent = is_array($t['procuringEntity'] ?? null) ? $t['procuringEntity'] : [];
    $buyer = trim((string)($ent['name'] ?? '')) ?: 'Nezināms pasūtītājs';
    $buyerId = (string)($ent['identifier']['id'] ?? '') ?: null;
    $locality = trim((string)($ent['address']['locality'] ?? ''));

    // Kontaktpersonu (vārds/e-pasts/tālrunis) NEUZGLABĀJAM (GDPR/personas dati).
    $orgs = [array_filter(['name' => $buyer, 'reg_number' => $buyerId, 'country' => 'UA'])];

    $view = $tenderID !== '' ? sprintf(PROZORRO_VIEW_FMT, rawurlencode($tenderID)) : sprintf(PROZORRO_VIEW_FMT, rawurlencode($id));

    return [
        'id'                 => 'PROZORRO-' . $id,
        'source'             => 'PROZORRO',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(trim((string)($t['description'] ?? '')) ?: null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'UA',
        'buyer_activity'     => null,
        'buyer_type'         => ted_truncate((string)($ent['kind'] ?? ''), 40) ?: null,
        'procure_nature'     => ocds_nature($t['mainProcurementCategory'] ?? null),
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => ted_truncate($tenderID, 40) ?: null,
        'budget'             => $budget,
        'currency'           => $cur,
        'document_url'       => $view,
        'buyer_profile_url'  => null,
        'procedure_type'     => prozorro_proc($mt),
        'notice_sub_type'    => ted_truncate($mt, 40) ?: null,
        'notice_lang'        => 'UK',
        'issue_date'         => $pubDate,
        'main_nuts'          => $locality !== '' ? ted_truncate('UA ' . $locality, 40) : 'UA',
        'main_country'       => 'UA',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $id,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'prozorro-api',
    ];
}

// ─────────────────────────── MTender (Moldova) ────────────────────────────────

/**
 * MTender ieraksta pakete (records[]) → notices rinda vai null.
 *
 * MTender kompilē VIENU iepirkumu vairākos apakšierakstos ar dažādu ocid (PN plāns,
 * NP paziņojums, EV izsole u.c.). Galvenie lauki (title/value/procuringEntity/CPV) ir
 * records[0].compiledRelease.tender, BET iesniegšanas termiņš (tenderPeriod.endDate)
 * un jautājumu logs (enquiryPeriod) mīt APAKŠIERAKSTOS — tāpēc tos meklējam pa visiem.
 * $item = meklēšanas API saraksta vienums (rezerves title/amount/buyer/procedureType).
 * Vērtību slieksni NELIETOJAM (Moldova nav TED → nav dublēšanās riska).
 */
function mtender_notice(array $records, string $ocid, array $item = []): ?array {
    $cr = is_array($records[0]['compiledRelease'] ?? null) ? $records[0]['compiledRelease'] : [];
    $t = is_array($cr['tender'] ?? null) ? $cr['tender'] : [];
    $title = trim((string)($t['title'] ?? ''));
    if ($title === '') $title = trim((string)($item['title'] ?? ''));
    if ($title === '') return null; // stubs bez tender posma

    $amount = null; $cur = 'MDL';
    if (is_array($t['value'] ?? null) && isset($t['value']['amount']) && is_numeric($t['value']['amount'])) {
        $amount = (float)$t['value']['amount'];
        if (!empty($t['value']['currency'])) $cur = (string)$t['value']['currency'];
    } elseif (isset($item['amount']) && is_numeric($item['amount'])) {
        $amount = (float)$item['amount'];
        if (!empty($item['currency'])) $cur = (string)$item['currency'];
    }

    // Pircējs: procuringEntity vai parties ar lomu 'buyer'; rezervē — meklēšanas vienums.
    $buyer = trim((string)($t['procuringEntity']['name'] ?? ''));
    $buyerId = (string)($t['procuringEntity']['id'] ?? '');
    if ($buyer === '') {
        foreach ((array)($cr['parties'] ?? []) as $p) {
            if (is_array($p) && in_array('buyer', (array)($p['roles'] ?? []), true)) {
                $buyer = trim((string)($p['name'] ?? '')); $buyerId = (string)($p['identifier']['id'] ?? $buyerId); break;
            }
        }
    }
    if ($buyer === '') $buyer = trim((string)($item['buyerName'] ?? ''));
    $buyer = $buyer ?: 'Nezināms pasūtītājs';

    // CPV: tender.classification (viens) + items[].classification (ja ir; arī no NP apakšieraksta).
    $cpv = [];
    if (preg_match('/(\d{8})/', (string)($t['classification']['id'] ?? ''), $m)) $cpv[] = $m[1];
    $items = (array)($t['items'] ?? []);
    foreach ($records as $rec) {
        $rt = $rec['compiledRelease']['tender'] ?? null;
        if (is_array($rt) && !empty($rt['items'])) $items = array_merge($items, (array)$rt['items']);
    }
    $cpv = array_values(array_unique(array_merge($cpv, ocds_cpv_from_items($items))));

    // Iesniegšanas termiņš: meklējam tenderPeriod.endDate pa VISIEM apakšierakstiem
    // (ņemam vēlāko), rezervē — enquiryPeriod.endDate. Bez tā = piešķirts/beidzies.
    $dlRaw = mtender_best_period_end($records);
    [$dlDate, $dlTime] = ocds_dt($dlRaw);
    [$pubDate] = ocds_dt($cr['date'] ?? ($item['modifiedDate'] ?? null));
    $category = ($dlDate !== null && $dlDate >= konkursi_today()) ? 'iepirkumi' : 'rezultati';

    $md = trim((string)($t['procurementMethodDetails'] ?? $item['procedureType'] ?? ''));
    $region = trim((string)($item['buyerRegion'] ?? ''));

    return [
        'id'                 => 'MTENDER-' . $ocid,
        'source'             => 'MTENDER',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(trim((string)($t['description'] ?? '')) ?: null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $buyerId !== '' ? ted_truncate($buyerId, 60) : null,
        'buyer_country'      => 'MD',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => ocds_nature($t['mainProcurementCategory'] ?? null),
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => ted_truncate((string)($t['id'] ?? $ocid), 40),
        'budget'             => $amount,
        'currency'           => $cur,
        'document_url'       => sprintf(MTENDER_VIEW_FMT, rawurlencode($ocid)),
        'buyer_profile_url'  => null,
        'procedure_type'     => $md !== '' ? ted_truncate($md, 60) : null,
        'notice_sub_type'    => $md !== '' ? ted_truncate($md, 40) : null,
        'notice_lang'        => strtoupper((string)($cr['language'] ?? 'ro')),
        'issue_date'         => $pubDate,
        'main_nuts'          => 'MD',
        'main_country'       => 'MD',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $ocid,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'reg_number' => $buyerId ?: null, 'region' => $region ?: null, 'country' => 'MD'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'mtender-ocds',
    ];
}

/**
 * Vēlākā iesniegšanas termiņa (tenderPeriod.endDate) meklēšana pa visiem MTender
 * apakšierakstiem; rezervē — vēlākā enquiryPeriod.endDate. Atgriež ISO virkni vai null.
 */
function mtender_best_period_end(array $records): ?string {
    foreach (['tenderPeriod', 'enquiryPeriod'] as $key) {
        $best = null;
        foreach ($records as $rec) {
            $p = $rec['compiledRelease']['tender'][$key] ?? null;
            if (is_array($p) && !empty($p['endDate']) && (string)$p['endDate'] > (string)$best) {
                $best = (string)$p['endDate'];
            }
        }
        if ($best !== null) return $best;
    }
    return null;
}
