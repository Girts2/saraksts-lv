<?php
/**
 * konkursi/lib/cvpis_parser.php — Lietuvas CVP IS (viesiejipirkimai.lt) parseri.
 *
 * Divi soļi:
 *   1. cvpis_parse_csv()    — portāla iebūvētā CSV eksporta ("Naujausi pirkimai")
 *                             rindas → saraksta ieraksti ar resourceId.
 *   2. cvpis_parse_detail() — publiskās detaļu lapas (prepareViewCfTWS.do)
 *                             <dt>/<dd> pāri → pilnie lauki, ieskaitot
 *                             "Virš arba žemiau tarptautinio pirkimo vertės ribos"
 *                             (Virš = virs ES sliekšņa → dublējas TED → izlaiž).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php'; // ted_truncate()

// Java Date.toString() mēnešu kodi ('Thu Jul 16 18:59:05 EEST 2026')
const CVPIS_MONTHS = [
    'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04', 'May' => '05', 'Jun' => '06',
    'Jul' => '07', 'Aug' => '08', 'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12',
];

/** 'Thu Jul 16 18:59:05 EEST 2026' → ['2026-07-16', '18:59'] (vai [null, null]). */
function cvpis_java_date(?string $s): array {
    if (!is_string($s)) return [null, null];
    if (preg_match('/^[A-Z][a-z]{2} ([A-Z][a-z]{2}) (\d{1,2}) (\d{2}):(\d{2}):\d{2} \S+ (\d{4})$/', trim($s), $m)
        && isset(CVPIS_MONTHS[$m[1]])) {
        return [sprintf('%s-%s-%02d', $m[5], CVPIS_MONTHS[$m[1]], (int)$m[2]), $m[3] . ':' . $m[4]];
    }
    return [null, null];
}

/** '31/07/2026 09:00' → ['2026-07-31', '09:00']. */
function cvpis_lt_date(?string $s): array {
    if (!is_string($s)) return [null, null];
    if (preg_match('#(\d{2})/(\d{2})/(\d{4})(?:\s+(\d{2}:\d{2}))?#', trim($s), $m)) {
        return [$m[3] . '-' . $m[2] . '-' . $m[1], $m[4] ?? null];
    }
    return [null, null];
}

/**
 * Parsē "Naujausi pirkimai" CSV eksportu.
 * @return array<int,array{resource_id:string,kind:string,title:string,epps_id:string,buyer:string,
 *                         pub_date:?string,deadline_date:?string,deadline_time:?string,
 *                         procedure:string,status:string,value:?float}>
 */
function cvpis_parse_csv(string $csv): array {
    $rows = [];
    $lines = preg_split('/\r\n|\n|\r/', $csv) ?: [];
    array_shift($lines); // galvene
    $buf = '';
    foreach ($lines as $line) {
        $buf = $buf === '' ? $line : $buf . "\n" . $line;
        // nepāra skaits neekranētu pēdiņu = citāts turpinās nākamajā rindā
        $q = preg_match_all('/(?<!\\\\)"/', $buf);
        if ($q % 2 !== 0) continue;
        $line = $buf;
        $buf = '';
        if (trim($line) === '') continue;

        $f = str_getcsv($line, ',', '"', '\\');
        if (count($f) < 12) continue;

        // 1. kolonna: <a href="/epps/cft/prepareViewCfTWS.do?resourceId=NNN">Nosaukums</a>
        if (!preg_match('#/epps/(cft|dps)/[^"\']*resourceId=(\d+)#', $f[1], $m)) continue;
        $kind = $m[1];
        $resourceId = $m[2];
        $title = trim(html_entity_decode(strip_tags(stripslashes($f[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        [$pubDate] = cvpis_java_date($f[5]);
        [$dlDate, $dlTime] = cvpis_java_date($f[6]);

        $value = null;
        $rawVal = str_replace([',', ' '], '', trim($f[11] ?? ''));
        if ($rawVal !== '' && is_numeric($rawVal)) $value = (float)$rawVal;

        $rows[] = [
            'resource_id'   => $resourceId,
            'kind'          => $kind,                 // 'cft' | 'dps'
            'title'         => $title,
            'epps_id'       => trim($f[2]),
            'buyer'         => trim(html_entity_decode(strip_tags(stripslashes($f[3])), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'pub_date'      => $pubDate,
            'deadline_date' => $dlDate,
            'deadline_time' => $dlTime,
            'procedure'     => trim($f[7]),
            'status'        => trim($f[8]),
            'value'         => $value,
        ];
    }
    return $rows;
}

/** Detaļu lapas <dt>etiķete</dt><dd>vērtība</dd> pāri → masīvs [etiķete => vērtība]. */
function cvpis_parse_detail(string $html): array {
    $out = [];
    if (!preg_match_all('#<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>#su', $html, $mm, PREG_SET_ORDER)) {
        return $out;
    }
    foreach ($mm as $m) {
        $label = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $label = rtrim($label, " :\u{00A0}");
        // <br/> → jaunas rindas (BVPŽ kodu sarakstam)
        $val = preg_replace('#<br\s*/?>#i', "\n", $m[2]);
        $val = trim(preg_replace('/[ \t]+/u', ' ', html_entity_decode(strip_tags($val), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $val = trim(preg_replace('/\n\s+/u', "\n", $val));
        if ($label !== '') $out[$label] = $val;
    }
    return $out;
}

/** Lietuviešu statuss → mūsu kategorija. */
function cvpis_category(string $status): string {
    $s = mb_strtolower($status, 'UTF-8');
    if (str_contains($s, 'teikimas') || str_contains($s, 'sukurtas')) return 'iepirkumi';
    if (str_contains($s, 'vertinim') || str_contains($s, 'laimėtoj') || str_contains($s, 'sudaryt')
        || str_contains($s, 'įvykdyt') || str_contains($s, 'baigt')) return 'rezultati';
    if (str_contains($s, 'atšaukt') || str_contains($s, 'nutraukt')) return 'citi';
    return 'iepirkumi';
}

/** Lietuviešu procedūras nosaukums → eForms kods (nezināmam paliek oriģināls). */
function cvpis_procedure(string $p): string {
    $s = mb_strtolower($p, 'UTF-8');
    if (str_contains($s, 'atviras')) return 'open';
    if (str_contains($s, 'ribot')) return 'restricted';
    if (str_contains($s, 'neskelbiam')) return 'neg-wo-call';
    if (str_contains($s, 'deryb')) return 'neg-w-call';
    if (str_contains($s, 'mažos vertės') || str_contains($s, 'supaprastint')) return 'oth-single';
    return $p;
}

/** Lietuviešu objekta tips → nature kods. */
function cvpis_nature(?string $t): ?string {
    if (!is_string($t)) return null;
    $s = mb_strtolower($t, 'UTF-8');
    if (str_contains($s, 'darbai')) return 'works';
    if (str_contains($s, 'prek')) return 'supplies';
    if (str_contains($s, 'paslaug')) return 'services';
    return null;
}

/** Atrod detaļu lauku pēc etiķetes prefiksa (garās etiķetes mēdz būt apcirstas). */
function epps_field(array $d, string $prefix): ?string {
    foreach ($d as $label => $val) {
        if (stripos($label, $prefix) === 0) return $val !== '' ? $val : null;
    }
    return null;
}

// ── Īrija (etenders.gov.ie — tā pati e-PPS platforma, angļu etiķetes) ─────────

/** Angļu statuss → kategorija. */
function etenders_category(string $status): string {
    $s = strtolower($status);
    if (str_contains($s, 'submission') || str_contains($s, 'created') || str_contains($s, 'open')) return 'iepirkumi';
    if (str_contains($s, 'evaluat') || str_contains($s, 'award') || str_contains($s, 'conclu') || str_contains($s, 'establish') || str_contains($s, 'closed')) return 'rezultati';
    if (str_contains($s, 'cancel') || str_contains($s, 'terminat')) return 'citi';
    return 'iepirkumi';
}

/**
 * Parsē noticeFTS HTML rezultātu lapu (paziņojumu reģistrs).
 * CSV eksportu lietot NEVAR — tas vienmēr atgriež 1. lapu (sk. config piezīmi).
 * Šūnas: #, Notice ID (hidden uuid + <a href=downloadNoticeForES>id</a>),
 * Notice Type, Notice Title, CA, Publication Date.
 * @return array<int,array{notice_id:string,url:?string,type:string,title:string,buyer:string,pub_date:?string}>
 */
function etenders_parse_notices_html(string $html): array {
    $rows = [];
    if (!preg_match_all('#<tr>\s*<td>\d+</td>(.*?)</tr>#s', $html, $trs)) return $rows;
    foreach ($trs[1] as $tr) {
        // Katrai rindai ir external_id; ES lejupielādes saite ir tikai nacionālajiem
        // (TED publicētie linko uz ted.europa.eu). Rindu atgriež VIENMĒR — izsaucējs
        // pēc atgriezto skaita nosaka, vai lapa bija pēdējā.
        if (!preg_match('/external_id_(\d+)/', $tr, $mId)) continue;
        if (!preg_match_all('#<td[^>]*>(.*?)</td>#s', $tr, $tds) || count($tds[1]) < 5) continue;
        $txt = static fn(string $c): string =>
            trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($c), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $url = null;
        if (preg_match('#href="(/epps/notices/downloadNoticeForES\.do\?[^"]+)"#', $tr, $m)) {
            $url = 'https://www.etenders.gov.ie' . html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        [$pubDate] = cvpis_java_date($txt($tds[1][4]));
        $rows[] = [
            'notice_id' => $mId[1],
            'url'       => $url,
            'type'      => $txt($tds[1][1]),
            'title'     => $txt($tds[1][2]),
            // CA vārdam pievienots iekšējais id ('Roscommon County Council_424')
            'buyer'     => preg_replace('/_\d+$/', '', $txt($tds[1][3])) ?? '',
            'pub_date'  => $pubDate,
        ];
    }
    return $rows;
}

/**
 * Vai reģistra tips ir NACIONĀLS piešķīrums? TED publicētie ('Contract award
 * notice - general directive' bez '(no TED publication)') nāk TED plūsmā.
 */
function etenders_is_national_award(string $type): bool {
    return stripos($type, 'award notice') !== false
        && stripos($type, 'no TED publication') !== false;
}

/** Reģistra rinda → notices rinda ('rezultati'; minimāli lauki + saite uz PDF). */
function etenders_award_notice(array $r): ?array {
    if ($r['title'] === '' || $r['pub_date'] === null || $r['url'] === null) return null;
    return [
        'id'                 => 'ETENDERS-N' . $r['notice_id'],
        'source'             => 'ETENDERS',
        'category'           => 'rezultati',
        'title'              => ted_truncate($r['title'], 400),
        'description'        => null,
        'buyer_name'         => ted_truncate($r['buyer'] !== '' ? $r['buyer'] : 'Nezināms pasūtītājs', 300),
        'buyer_id'           => null,
        'buyer_country'      => 'IE',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => null,
        'publication_date'   => $r['pub_date'],
        'deadline_date'      => null,
        'deadline_time'      => null,
        'publication_number' => $r['notice_id'],
        'budget'             => null,
        'currency'           => 'EUR',
        'document_url'       => $r['url'],
        'buyer_profile_url'  => null,
        'procedure_type'     => null,
        'notice_sub_type'    => ted_truncate($r['type'], 120),
        'notice_lang'        => 'EN',
        'issue_date'         => $r['pub_date'],
        'main_nuts'          => null,
        'main_country'       => 'IE',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $r['buyer'] !== '' ? $r['buyer'] : null, 'country' => 'IE'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'etenders-notices',
    ];
}

/**
 * TED paziņojumu numuri no detaļu lapas HTML (piem. '85300-2026').
 * Redzamais 'TED links' teksts lapā ir apcirsts ('...NOTICE:588984-202...'),
 * tāpēc numurus ņem no href, ne no parsētā lauka.
 * @return list<string>
 */
function etenders_ted_refs(string $html): array {
    preg_match_all('~NOTICE:(\d{1,8}-\d{4})|ted\.europa\.eu/[^"\']*?/(\d{1,8}-\d{4})~', $html, $m);
    return array_values(array_unique(array_filter(array_merge($m[1], $m[2]))));
}

/**
 * Īrijas saraksta rinda + detaļu lauki → notices rinda.
 * TED/sliekšņa dublikātu lēmumu pieņem ks_sync_etenders (pēc tā, vai atsauktais
 * TED paziņojums tiešām ir mūsu DB) — šī funkcija tikai būvē rindu.
 */
function etenders_build_notice(array $row, array $d): ?array {
    $cpv = [];
    $mainCpv = null;
    foreach (preg_split('/\n/', (string)(epps_field($d, 'CPV Codes') ?? '')) as $lineCpv) {
        if (preg_match('/^(\d{8})/', trim($lineCpv), $m)) {
            $cpv[] = $m[1];
            if ($mainCpv === null) $mainCpv = $m[1];
        }
    }
    $cpv = array_values(array_unique($cpv));

    [$dlDate, $dlTime] = cvpis_lt_date(epps_field($d, 'Time-limit for receipt of tenders'));
    if ($dlDate === null) { $dlDate = $row['deadline_date']; $dlTime = $row['deadline_time']; }
    [$pubDate] = cvpis_lt_date(epps_field($d, 'Date of publication'));
    if ($pubDate === null) $pubDate = $row['pub_date'];

    $budget = $row['value'];
    $rawVal = str_replace([',', ' '], '', (string)(epps_field($d, 'Estimated value') ?? ''));
    $rawVal = rtrim($rawVal, '.');
    if ($rawVal !== '' && is_numeric($rawVal)) $budget = (float)$rawVal;

    $nature = match (strtolower((string)(epps_field($d, 'Procurement Type') ?? ''))) {
        'services' => 'services',
        'supplies' => 'supplies',
        'works'    => 'works',
        default    => null,
    };
    $procRaw = (string)(epps_field($d, 'Procedure') ?? $row['procedure']);
    $proc = match (true) {
        stripos($procRaw, 'open') !== false       => 'open',
        stripos($procRaw, 'restricted') !== false => 'restricted',
        stripos($procRaw, 'negotiat') !== false   => 'neg-w-call',
        default                                   => ($procRaw !== '' ? $procRaw : null),
    };

    $title = epps_field($d, 'Title') ?? $row['title'];
    $buyer = epps_field($d, 'Name of Contracting Authority') ?? $row['buyer'];
    $nuts = trim((string)(epps_field($d, 'NUTS codes') ?? ''));

    return [
        'id'                 => 'ETENDERS-' . $row['resource_id'],
        'source'             => 'ETENDERS',
        'category'           => etenders_category($row['status']),
        'title'              => ted_truncate($title, 400) ?? ('eTenders ' . $row['resource_id']),
        'description'        => ted_truncate(epps_field($d, 'Description'), KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'IE',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $row['epps_id'] !== '' ? $row['epps_id'] : $row['resource_id'],
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => sprintf(ETENDERS_DETAIL_URL_FMT, $row['resource_id']),
        'buyer_profile_url'  => null,
        'procedure_type'     => $proc,
        'notice_sub_type'    => null,
        'notice_lang'        => 'EN',
        'issue_date'         => $pubDate,
        'main_nuts'          => $nuts !== '' ? ted_truncate($nuts, 40) : null,
        'main_country'       => 'IE',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => epps_field($d, 'CfT CA Unique ID'),
        'main_cpv'           => $mainCpv,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'country' => 'IE'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'etenders-list',
    ];
}

/**
 * Saraksta rinda + detaļu lauki → notices rinda.
 * Atgriež null, ja pirkums IZLAIŽAMS (virs ES sliekšņa vai ar TED atsauci —
 * tas pats paziņojums jau nāk TED plūsmā).
 */
function cvpis_build_notice(array $row, array $d): ?array {
    // Dedup pret TED: "Virš" = virs starptautiskā sliekšņa
    $threshold = $d['Virš arba žemiau tarptautinio pirkimo vertės ribos'] ?? '';
    if (mb_stripos($threshold, 'virš') !== false) return null;
    $tedRefs = $d['TED nuorodos į paskelbtus pranešimus'] ?? '';
    if (trim($tedRefs) !== '') return null;

    // CPV kodi no "BVPŽ kodai" ('45454100-Restauravimo darbai' pa rindai)
    $cpv = [];
    $mainCpv = null;
    foreach (preg_split('/\n/', $d['BVPŽ kodai'] ?? '') as $lineCpv) {
        if (preg_match('/^(\d{8})/', trim($lineCpv), $m)) {
            $cpv[] = $m[1];
            if ($mainCpv === null) $mainCpv = $m[1];
        }
    }
    $cpv = array_values(array_unique($cpv));

    // Termiņš: precīzāks no detaļām, atkāpe uz saraksta kolonnu
    [$dlDate, $dlTime] = cvpis_lt_date($d['Pasiūlymų arba paraiškų dalyvauti pirkime pateikimo terminas'] ?? null);
    if ($dlDate === null) { $dlDate = $row['deadline_date']; $dlTime = $row['deadline_time']; }

    [$pubDate] = cvpis_lt_date($d['Paskelbimo ir (arba) kvietimo data'] ?? null);
    if ($pubDate === null) $pubDate = $row['pub_date'];

    $budget = $row['value'];
    $rawVal = str_replace([',', ' '], '', (string)($d['Numatoma vertė (EUR)'] ?? ''));
    $rawVal = rtrim($rawVal, '.');
    if ($rawVal !== '' && is_numeric($rawVal)) $budget = (float)$rawVal;

    $euFund = mb_stripos((string)($d['ES finansavimas'] ?? ''), 'taip') !== false;
    $title = $d['Pavadinimas'] ?? $row['title'];
    $buyer = $d['Pirkimo vykdytojo pavadinimas'] ?? $row['buyer'];
    $detailUrl = sprintf(CVPIS_DETAIL_URL_FMT, $row['resource_id']);

    return [
        'id'                 => 'CVPIS-' . $row['resource_id'],
        'source'             => 'CVPIS',
        'category'           => cvpis_category($row['status']),
        'title'              => ted_truncate($title, 400) ?? ('CVP IS ' . $row['resource_id']),
        'description'        => ted_truncate($d['Aprašymas'] ?? null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'LT',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => cvpis_nature($d['Pirkimo objekto tipas'] ?? null),
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $row['epps_id'] !== '' ? $row['epps_id'] : $row['resource_id'],
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => $detailUrl,
        'buyer_profile_url'  => null,
        'procedure_type'     => cvpis_procedure($row['procedure']),
        'notice_sub_type'    => null,
        'notice_lang'        => 'LT',
        'issue_date'         => $pubDate,
        'main_nuts'          => (($n = trim((string)($d['NUTS kodai'] ?? ''))) !== '') ? ted_truncate($n, 40) : null,
        'main_country'       => 'LT',
        'funding_program'    => $euFund ? 'EU' : 'no-eu-funds',
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => $mainCpv,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter([
                                    'name'    => $buyer,
                                    'country' => 'LT',
                                ])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'cvpis-list',
    ];
}

// ── CVP IS integrācijas API (epps-integration) ────────────────────────────────
// Oficiālā REST saskarne aizstāj CSV+HTML skrāpēšanu. Atšķirībā no "Naujausi
// pirkimai" CSV plūsmas, kurā ir tikai atvērtie pirkumi (divi statusi), API dod
// pilnu dzīves ciklu — tāpēc no šejienes rodas rezultāti, atceltie un grozījumi.

/** API statuss → mūsu kategorija. */
function cvpis_api_category(string $status, string $procedure): string {
    // Tirgus konsultācijas nav iepirkumi, kuriem var pieteikties.
    if ($procedure === 'Preliminary Market Consultation') return 'citi';
    return match ($status) {
        'Awarded', 'Concluded', 'Closed' => 'rezultati',
        'Cancelled', 'Terminated'        => 'citi',
        // Evaluation/Proposal Submission Closed = piedāvājumi iesniegti, vērtē —
        // rezultāta vēl NAV. Aktīvo cilni tie pamet paši, kad paiet termiņš.
        default                          => 'iepirkumi',
    };
}

/**
 * Paziņojuma lapas URL pēc pirkuma veida. CVP IS lieto trīs atsevišķus ceļus, un
 * nepareizais klusi aizved uz pieteikšanās lapu, nevis paziņo par kļūdu.
 */
function cvpis_api_doc_url(string $procedure, string $id): string {
    $fmt = match ($procedure) {
        'Preliminary Market Consultation'                 => CVPIS_PMC_URL_FMT,
        'DPS Specific Contract', 'Dynamic Purchasing System' => CVPIS_DPS_URL_FMT,
        default                                           => CVPIS_DETAIL_URL_FMT,
    };
    return sprintf($fmt, rawurlencode($id));
}

/** 'DD-MM-YYYY HH:MM:SS' → ['YYYY-MM-DD', 'HH:MM'] (vai [null, null]). */
function cvpis_api_date(?string $s): array {
    if (!is_string($s) || $s === '') return [null, null];
    if (!preg_match('/^(\d{2})-(\d{2})-(\d{4})(?:\s+(\d{2}):(\d{2}))?/', trim($s), $m)) return [null, null];
    // Avotā gadās bojāti gadi (piem. '14-06-0028') — tādus izmet.
    if ((int)$m[3] < 2000 || (int)$m[3] > 2100) return [null, null];
    return ["$m[3]-$m[2]-$m[1]", isset($m[4]) ? "$m[4]:$m[5]" : null];
}

/**
 * Parsē vienu API ierakstu → notices rinda.
 * @param array<string,mixed> $r  viens elements no cft-details-export atbildes
 * @return array<string,mixed>|null  null = izlaižams (virs sliekšņa / DPS sistēma)
 */
function cvpis_api_parse_item(array $r): ?array {
    $id = $r['id'] ?? null;
    if ($id === null || $id === '') return null;

    // Dedup pret TED: virs-sliekšņa pirkumi jau nāk TED plūsmā.
    if (($r['aboveThreshold'] ?? null) === true) return null;
    // tedLink klātbūtne nozīmē to pašu arī tad, ja karogs nav uzstādīts.
    if (!empty($r['tedLink'])) return null;

    $procedure = is_string($r['procedure'] ?? null) ? $r['procedure'] : '';
    // Pati dinamiskā iepirkumu sistēma nav konkurss (termiņi 2030+); konkrētie
    // pirkumi tās ietvaros ('DPS Specific Contract') gan ir un paliek.
    if ($procedure === 'Dynamic Purchasing System') return null;

    $status = is_string($r['status'] ?? null) ? $r['status'] : '';
    [$pubDate, ] = cvpis_api_date(is_string($r['datePublished'] ?? null) ? $r['datePublished'] : null);
    if ($pubDate === null) return null; // bez publikācijas datuma nav ko rādīt
    [$dlDate, $dlTime] = cvpis_api_date(is_string($r['tenderSubmissionDeadline'] ?? null) ? $r['tenderSubmissionDeadline'] : null);

    $cpv = [];
    foreach (explode(',', (string)($r['cpvCodes'] ?? '')) as $c) {
        $c = trim(explode('-', trim($c))[0]);
        if (preg_match('/^\d{8}$/', $c)) $cpv[] = $c;
    }
    $cpv = array_values(array_unique($cpv));
    sort($cpv);

    $nature = match (strtolower((string)($r['procurementType'] ?? ''))) {
        'works'    => 'works',
        'supplies' => 'supplies',
        'services' => 'services',
        default    => null,
    };

    $budget = null;
    if (isset($r['estimatedValue']) && is_numeric($r['estimatedValue'])) {
        $budget = (float)$r['estimatedValue'];
        if ($budget <= 0) $budget = null;
    }

    $title = trim((string)($r['title'] ?? ''));
    if ($title === '') $title = 'CVP IS ' . $id;
    $buyer = trim((string)($r['caName'] ?? ''));
    if ($buyer === '') $buyer = 'Nezināms pasūtītājs';

    return [
        'id'                 => 'CVPIS-' . $id,
        'source'             => 'CVPIS',
        'category'           => cvpis_api_category($status, $procedure),
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(is_string($r['description'] ?? null) ? $r['description'] : null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'LT',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => (string)$id,
        'budget'             => $budget,
        'currency'           => 'EUR',
        // /ppss/publicpurchases/{id} izskatās pareizi, bet atbild ar 302 → home.do.
        'document_url'       => cvpis_api_doc_url($procedure, (string)$id),
        'buyer_profile_url'  => null,
        'procedure_type'     => $procedure !== '' ? $procedure : null,
        'notice_sub_type'    => $status !== '' ? $status : null,
        'notice_lang'        => 'LT',
        'issue_date'         => $pubDate,
        'main_nuts'          => is_string($r['nutsCode'] ?? null) && $r['nutsCode'] !== '' ? $r['nutsCode'] : null,
        'main_country'       => 'LT',
        'funding_program'    => ($r['euFunding'] ?? null) === true ? 'EU' : 'no-eu-funds',
        'prev_notice_ref'    => null,
        'contract_folder_id' => is_string($r['cftCaUniqueId'] ?? null) ? $r['cftCaUniqueId'] : null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter([
                                    'name'    => $buyer,
                                    'country' => 'LT',
                                ])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'cvpis-api',
    ];
}
