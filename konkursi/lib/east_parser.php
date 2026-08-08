<?php
/**
 * konkursi/lib/east_parser.php — RO (SEAP/SICAP), BG (CAIS EOP),
 * GR (ΚΗΜΔΗΣ KIMDIS), SI (enarocanje.si) parseri.
 *
 * Visi četri avoti atrasti dzīvā izpētē 2026-07-18:
 *  - RO: api-pub JSON (Referer galvene obligāta); saraksti pilnvērtīgi.
 *  - BG: WCF JSON serviss; ProcedureType nacionālo kopa verificēta empīriski.
 *  - GR: jaunais KIMDIS OpenData REST (Swagger dokumentēts).
 *  - SI: Vue SPA grid JSON + detaļu GET; CPV caur šifranta koku.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php';     // ted_truncate(), ted_norm_date()
require_once __DIR__ . '/nordics_parser.php'; // nord_iso_dt()

// ═══════════════════════════ RO — SEAP / SICAP ═══════════════════════════════

/** 'RO 7179966 - NOSAUKUMS' vai '10874881 - NOSAUKUMS' → [id, nosaukums]. */
function seap_buyer(?string $s): array {
    if (!is_string($s) || $s === '') return [null, 'Nezināms pasūtītājs'];
    $p = explode(' - ', $s, 2);
    if (count($p) === 2) return [trim($p[0]) ?: null, trim($p[1]) ?: 'Nezināms pasūtītājs'];
    return [null, trim($s)];
}

/**
 * Paziņojuma lapas URL pēc tipa. SEAP nepareizu maršrutu neatraida ar kļūdu —
 * tas atdod tukšu rāmi ar HTTP 200, tāpēc tips jāizšķir. Visi maršruti pārbaudīti,
 * atverot attiecīgā tipa paziņojumu pašā portālā; RFD atšķiras arī pēc uzbūves.
 */
function seap_view_url(bool $isAward, string $pre, int $viewId): ?string {
    if ($viewId <= 0) return null;
    if ($isAward) return sprintf(SEAP_CAN_VIEW_FMT, $viewId);
    return match ($pre) {
        'SCN'   => sprintf(SEAP_SCN_VIEW_FMT, $viewId),
        'PC'    => sprintf(SEAP_PC_VIEW_FMT, $viewId),
        'RFQ'   => sprintf(SEAP_RFQ_VIEW_FMT, $viewId),
        'RFD'   => sprintf(SEAP_RFD_VIEW_FMT, $viewId),
        'CN'    => sprintf(SEAP_CN_VIEW_FMT, $viewId),
        default => null, // nezināms tips — labāk bez saites nekā uz tukšu lapu
    };
}

/**
 * Viens saraksta ieraksts → rinda. $isAward: CAN saraksts (rezultāti).
 * @return array<string,mixed>|null null = ES līmenis (CN/CAN → TED) vai nederīgs
 */
function seap_parse_item(array $it, bool $isAward): ?array {
    $no = (string)($it['noticeNo'] ?? '');
    if ($no === '') return null;
    $pre = preg_match('/^[A-Z]+/', $no, $m) ? $m[0] : '';
    if ($pre === 'CN' || $pre === 'CAN') return null; // ES līmenis → TED

    [$buyerId, $buyer] = seap_buyer($it['contractingAuthorityNameAndFN'] ?? null);

    $cpv = null;
    if (preg_match('/^(\d{8})/', (string)($it['cpvCodeAndName'] ?? ''), $m)) $cpv = $m[1];

    $natMap = ['Lucrari' => 'works', 'Servicii' => 'services', 'Furnizare' => 'supplies'];
    $nature = $natMap[(string)(($it['sysAcquisitionContractType'] ?? [])['text'] ?? '')] ?? null;

    $budget = null;
    foreach (['ronContractValue', 'estimatedValueRon'] as $f) {
        if (isset($it[$f]) && is_numeric($it[$f]) && (float)$it[$f] > 0) { $budget = (float)$it[$f]; break; }
    }

    [$pubDate] = nord_iso_dt($it['noticeStateDate'] ?? null);
    [$dlDate, $dlTime] = nord_iso_dt($it['minTenderReceiptDeadline'] ?? null);

    $viewId = (int)($isAward ? ($it['caNoticeId'] ?? 0) : ($it['cNoticeId'] ?? 0));
    $procState = (string)(($it['sysProcedureState'] ?? [])['text'] ?? '');
    $category = $isAward ? 'rezultati' : ($procState === 'Anulata' ? 'citi' : 'iepirkumi');

    return [
        'id'                 => 'SEAP-' . $no,
        'source'             => 'SEAP',
        'category'           => $category,
        'title'              => ted_truncate((string)($it['contractTitle'] ?? $no), 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'RO',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $isAward ? null : $dlDate,
        'deadline_time'      => $isAward ? null : $dlTime,
        'publication_number' => $no,
        'budget'             => $budget,
        'currency'           => 'RON',
        'document_url'       => seap_view_url($isAward, $pre, $viewId),
        'buyer_profile_url'  => null,
        'procedure_type'     => (string)(($it['sysProcedureType'] ?? [])['text'] ?? '') ?: null,
        'notice_sub_type'    => $pre,
        'notice_lang'        => 'RO',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'RO',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => isset($it['procedureId']) ? (string)$it['procedureId'] : null,
        'main_cpv'           => $cpv,
        'cpv_codes'          => json_encode($cpv !== null ? [$cpv] : [], JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'reg_number' => $buyerId, 'country' => 'RO'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => $isAward ? 'seap-can' : 'seap-cn',
    ];
}

// ═══════════════════════════ BG — CAIS EOP ═══════════════════════════════════

/** WCF '/Date(1784318490750)/' vai ar nobīdi → [YYYY-MM-DD, HH:MM] pēc Sofijas laika. */
function eop_wcf_date(?string $s): array {
    if (!is_string($s) || !preg_match('#/Date\((\d+)#', $s, $m)) return [null, null];
    $dt = (new DateTimeImmutable('@' . intdiv((int)$m[1], 1000)))->setTimezone(new DateTimeZone('Europe/Sofia'));
    return [$dt->format('Y-m-d'), $dt->format('H:i')];
}

/**
 * Viens GetPublishedTendersBySpecified ieraksts → rinda.
 * @return array<string,mixed>|null null = ES līmeņa procedūra (→ TED)
 */
function eop_parse_item(array $it): ?array {
    $pt = (int)($it['ProcedureType'] ?? 0);
    // Status=2 (slēgtie): PT=0 ir noslēgtais LĪGUMS (Договор...), PT=19 —
    // tirgus konsultācijas. Atvērtajā sarakstā šo tipu nav, tāpēc tie neietekmē
    // aktīvo plūsmu.
    $status = (int)($it['Status'] ?? 1);
    $isClosed = $status === 2;
    $extraPt = $isClosed && in_array($pt, [0, 19], true);
    if (!$extraPt && !in_array($pt, EOP_NATIONAL_PT, true)) return null;
    $tenderId = (int)($it['TenderId'] ?? 0);
    if ($tenderId <= 0) return null;

    $title = trim((string)($it['TenderName'] ?? ''));
    if ($title === '') return null;
    $desc = trim(html_entity_decode(strip_tags((string)($it['TenderDescription'] ?? '')), ENT_QUOTES | ENT_HTML5));

    [$pubDate] = eop_wcf_date($it['PublicationDate'] ?? null);
    [$dlDate, $dlTime] = eop_wcf_date($it['Deadline'] ?? null);

    $buyer = trim((string)($it['OrganizationName'] ?? '')) ?: 'Nezināms pasūtītājs';

    // Slēgtā procedūra: līgums/pabeigta procedūra → 'rezultati';
    // tirgus konsultācija (PT=19) → 'citi'.
    $category = !$isClosed ? 'iepirkumi' : ($pt === 19 ? 'citi' : 'rezultati');

    return [
        'id'                 => 'EOP-' . $tenderId,
        'source'             => 'EOP',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate($desc !== '' ? $desc : null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'BG',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => null,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => (string)($it['SpecialNumber'] ?? $tenderId),
        'budget'             => null,
        'currency'           => 'EUR',
        'document_url'       => sprintf(EOP_PAGE_URL_FMT, $tenderId),
        'buyer_profile_url'  => null,
        'procedure_type'     => EOP_PT_NAMES[$pt] ?? ($extraPt ? null : (string)$pt),
        'notice_sub_type'    => (string)$pt,
        'notice_lang'        => 'BG',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'BG',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => isset($it['SpecialNumber']) ? (string)$it['SpecialNumber'] : null,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([['name' => $buyer, 'country' => 'BG']], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'eop-today',
    ];
}

// ═══════════════════════════ GR — ΚΗΜΔΗΣ (KIMDIS) ════════════════════════════

/** {key,value} pāra vērtība vai null. */
function kimdis_kv($x): ?string {
    return (is_array($x) && isset($x['value']) && trim((string)$x['value']) !== '') ? trim((string)$x['value']) : null;
}

/** Pirmā netukšā skalārā vērtība (lauks var būt gan virkne, gan masīvs). */
function kimdis_scalar($x): ?string {
    if (is_array($x)) {
        foreach ($x as $v) { $r = kimdis_scalar($v); if ($r !== null) return $r; }
        return null;
    }
    if (!is_scalar($x)) return null;
    $s = trim((string)$x);
    return $s !== '' ? $s : null;
}

/**
 * Viens /notice vai /contract ieraksts → rinda. $kind: 'notice'|'contract'.
 * @return array<string,mixed>|null null = tiešais piešķīrums / virs ES sliekšņa / nederīgs
 */
function kimdis_parse_item(array $it, string $kind): ?array {
    $ref = (string)($it['referenceNumber'] ?? '');
    if ($ref === '') return null;
    // Paziņojumiem tips ir typeOfProcedure, līgumiem — procedureType
    $tp = (is_array($it['typeOfProcedure'] ?? null) && ($it['typeOfProcedure']['key'] ?? null) !== null)
        ? $it['typeOfProcedure'] : ($it['procedureType'] ?? null);
    $tpKey = is_array($tp) ? (string)($tp['key'] ?? '') : '';
    if ($tpKey === '' || $tpKey === KIMDIS_DIRECT_AWARD_KEY) return null; // tiešie piešķīrumi / bez tipa — troksnis

    $ctText = kimdis_kv($it['contractType'] ?? null) ?? '';
    $nature = null;
    if (mb_stripos($ctText, 'Έργα', 0, 'UTF-8') !== false) $nature = 'works';
    elseif (mb_stripos($ctText, 'Προμήθειες', 0, 'UTF-8') !== false) $nature = 'supplies';
    elseif ($ctText !== '') $nature = 'services';

    $budget = null;
    if (isset($it['totalCostWithoutVAT']) && is_numeric($it['totalCostWithoutVAT']) && (float)$it['totalCostWithoutVAT'] > 0) {
        $budget = (float)$it['totalCostWithoutVAT'];
    }
    // Dedup pret TED: virs ES sliekšņa esošie tāpat nonāk TED (kā Spānijai)
    if ($budget !== null) {
        $limit = $nature === 'works' ? PLACSP_EU_THRESHOLD_WORKS : PLACSP_EU_THRESHOLD_SERVICES;
        if ($budget >= $limit) return null;
    }

    $desc = null; $cpv = [];
    foreach ((array)($it['objectDetails'] ?? $it['objectDetailsList'] ?? []) as $od) {
        if (!is_array($od)) continue;
        if ($desc === null && trim((string)($od['shortDescription'] ?? '')) !== '') $desc = trim((string)$od['shortDescription']);
        foreach ((array)($od['cpvs'] ?? []) as $c) {
            $code = is_array($c) ? (string)($c['key'] ?? ($c['value'] ?? '')) : (string)$c;
            if (preg_match('/(\d{8})/', $code, $m)) $cpv[] = $m[1];
        }
    }
    $cpv = array_values(array_unique($cpv));

    [$pubDate] = nord_iso_dt($it['submissionDate'] ?? null);
    if ($pubDate === null) [$pubDate] = nord_iso_dt($it['signedDate'] ?? null);
    [$dlDate, $dlTime] = nord_iso_dt($it['finalSubmissionDate'] ?? null);

    $category = $kind === 'contract' ? 'rezultati' : 'iepirkumi';
    if (!empty($it['cancelled'])) $category = 'izmainas';

    $buyer = kimdis_kv($it['organization'] ?? null) ?? 'Nezināms pasūtītājs';
    $nuts = null;
    if (is_array($it['nutsCode'] ?? null)) $nuts = (string)($it['nutsCode']['key'] ?? '') ?: null;

    $orgs = [array_filter(['name' => $buyer, 'reg_number' => $it['organizationVatNumber'] ?? null, 'country' => 'GR'])];
    foreach ((array)((($it['contractingDataDetails'] ?? [])['contractingMembersDataList'] ?? [])) as $w) {
        $wn = is_array($w) ? trim((string)($w['name'] ?? '')) : '';
        if ($wn !== '') $orgs[] = array_filter(['name' => $wn . ' (uzvarētājs)', 'reg_number' => $w['vatNumber'] ?? null, 'country' => 'GR']);
    }

    return [
        'id'                 => 'KIMDIS-' . $ref,
        'source'             => 'KIMDIS',
        'category'           => $category,
        'title'              => ted_truncate((string)($it['title'] ?? $ref), 400),
        'description'        => ted_truncate($desc, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => isset($it['organizationVatNumber']) ? (string)$it['organizationVatNumber'] : null,
        'buyer_country'      => 'GR',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $kind === 'notice' ? $dlDate : null,
        'deadline_time'      => $kind === 'notice' ? $dlTime : null,
        'publication_number' => $ref,
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => sprintf(KIMDIS_ATTACH_FMT, $kind, rawurlencode($ref)),
        'buyer_profile_url'  => null,
        'procedure_type'     => kimdis_kv($tp),
        'notice_sub_type'    => strtoupper($kind),
        'notice_lang'        => 'EL',
        'issue_date'         => $pubDate,
        'main_nuts'          => $nuts,
        'main_country'       => 'GR',
        'funding_program'    => null,
        'prev_notice_ref'    => isset($it['amendsNoticeRefNo']) && $it['amendsNoticeRefNo'] ? (string)$it['amendsNoticeRefNo'] : null,
        // aaht dažkārt ir masīvs (vairāki iestādes kodi) — ņem pirmo skalāro,
        // citādi (string) taisa literālu 'Array' un met brīdinājumu.
        'contract_folder_id' => kimdis_scalar($it['aaht'] ?? null),
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'kimdis-' . $kind,
    ];
}

// ═══════════════════════════ SI — enarocanje.si ══════════════════════════════

/** CPV šifranta koks {id,label:'03000000 - ...',children:[]} → [id => 8 ciparu kods]. */
function enar_cpv_map(array $tree): array {
    $map = [];
    $stack = $tree;
    while ($stack) {
        $n = array_pop($stack);
        if (!is_array($n)) continue;
        if (isset($n['id'], $n['label']) && preg_match('/^(\d{8})/', (string)$n['label'], $m)) {
            $map[(int)$n['id']] = $m[1];
        }
        foreach ((array)($n['children'] ?? []) as $c) $stack[] = $c;
    }
    return $map;
}

/** 'JN005453/2026-SL3/01-P03' → stabilā bāze 'JN005453/2026-SL3' (labojumi pārraksta). */
function enar_ident_base(string $ident): string {
    return preg_replace('#/\d+(-P\d+)?$#', '', $ident) ?? $ident;
}

/**
 * Grid rinda + detaļa (var būt null) → rinda.
 * @return array<string,mixed>|null null = ES forma (→ TED) vai nederīgs
 */
function enar_build_notice(array $row, ?array $det, array $cpvMap): ?array {
    $oznaka = (string)($row['sifObrazecOznaka'] ?? '');
    if ($oznaka === '' || str_starts_with($oznaka, 'EU')) return null; // ES formas nāk caur TED
    $ident = (string)($row['objavaIdent'] ?? '');
    $idObrazec = (int)($row['idObrazec'] ?? 0);
    if ($ident === '' || $idObrazec <= 0) return null;

    $faza = (string)($row['sifPostopekFazaNaziv'] ?? '');
    $category = match (true) {
        $faza === 'Naročilo'           => 'iepirkumi',
        $faza === 'Rezultat'           => 'rezultati',
        str_starts_with($faza, 'Sprememba') => 'izmainas',
        default                        => 'citi',
    };
    // popravki (-P sufikss) pārraksta bāzes id — jaunākā versija uzvar upsertā
    [$pubDate] = nord_iso_dt($row['objavaDejanskaDatum'] ?? null);

    $desc = null; $dlDate = null; $dlTime = null; $budget = null; $cpv = []; $orgs = [];
    $buyer = trim((string)($row['narocnikNaziv'] ?? '')) ?: 'Nezināms pasūtītājs';
    $buyerId = isset($row['narocnikMaticna']) ? (string)$row['narocnikMaticna'] : null;
    $orgs[] = array_filter(['name' => $buyer, 'reg_number' => $buyerId, 'country' => 'SI']);

    if ($det !== null) {
        $desc = trim((string)($det['kratekOpis'] ?? '')) ?: null;
        [$dlDate, $dlTime] = nord_iso_dt($det['datumDoPonudba'] ?? null);
        foreach (['vrednostSkupna', 'vrednostOcenjenaBrezDDV'] as $f) {
            if (isset($det[$f]) && is_numeric($det[$f]) && (float)$det[$f] > 0) { $budget = (float)$det[$f]; break; }
        }
        $cid = (int)($det['idSifCpv'] ?? 0);
        if (isset($cpvMap[$cid])) $cpv[] = $cpvMap[$cid];
        foreach ((array)($det['obrazecSklop'] ?? []) as $sk) {
            $sid = (int)(($sk['idSifCpv'] ?? 0));
            if (isset($cpvMap[$sid])) $cpv[] = $cpvMap[$sid];
        }
        $cpv = array_values(array_unique($cpv));
        foreach ((array)($det['obrazecOddaja'] ?? []) as $od) {
            foreach ((array)($od['obrazecOddajaSubjekt'] ?? []) as $sub) {
                $wn = trim((string)($sub['naziv'] ?? ''));
                if ($wn !== '') $orgs[] = ['name' => $wn . ' (uzvarētājs)', 'country' => 'SI'];
            }
        }
    }

    return [
        'id'                 => 'ENAR-' . enar_ident_base($ident),
        'source'             => 'ENAR',
        'category'           => $category,
        'title'              => ted_truncate((string)($row['naslov'] ?? $ident), 400),
        'description'        => ted_truncate($desc, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'SI',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => null,
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => $ident,
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => sprintf(ENAR_PAGE_URL_FMT, $idObrazec),
        'buyer_profile_url'  => null,
        'procedure_type'     => null,
        'notice_sub_type'    => $oznaka,
        'notice_lang'        => 'SL',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'SI',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => isset($row['idDosje']) ? (string)$row['idDosje'] : null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode(array_slice($orgs, 0, KONKURSI_MAX_ORGS), JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'enar-grid',
    ];
}
