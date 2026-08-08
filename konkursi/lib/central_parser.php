<?php
/**
 * konkursi/lib/central_parser.php — CZ (VVZ NIPEZ), SK (ÚVO Vestník),
 * BE (BOSA e-Procurement), AT (Kerndaten KDQ) parseri.
 *
 * Visi četri avoti atrasti dzīvā izpētē 2026-07-17 (DigiWhist-ēras ceļi miruši):
 *  - CZ: api.vvz.nipez.cz JSON API; pilnie eForms dati bērna iesniegumā (BT koki).
 *  - SK: uvo.gov.sk vestnik dienas HTML lapa + detaļu lapas ar BT etiķetēm.
 *  - BE: api/sea/search/publications JSON (Keycloak anonīmais tokens).
 *  - AT: BRZ KDQ XML rindas (vecā TED F-formu stila dokumenti).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php';     // ted_truncate(), ted_norm_date()
require_once __DIR__ . '/nordics_parser.php'; // nord_iso_dt(), nord_cpv_list()
require_once __DIR__ . '/placsp_parser.php';  // es_first()/es_all()/es_text() (localName BFS)

// ═══════════════════════════ CZ — VVZ NIPEZ ══════════════════════════════════

/**
 * Kategorija no druhFormulare koda (portāla paša grupējums: 10-24+CZ02/CZ07 =
 * zakázku dibinošie, 29-37 = uzvarētāji, zrušena/oprava = izmaiņas).
 */
function vvz_category(string $druh, array $data): string {
    if (!empty($data['zakazkaZrusena']) || !empty($data['formularOpravuje'])) return 'izmainas';
    if (ctype_digit($druh)) {
        $n = (int)$druh;
        if ($n <= 9) return 'citi';          // plānošana (PIN)
        if ($n <= 24) return 'iepirkumi';    // konkursa paziņojumi (CN)
        if ($n <= 37) return 'rezultati';    // rezultāti (CAN)
        return 'izmainas';                    // 38-40 līguma grozījumi
    }
    // Vecās F-formas un CZ nacionālās formas
    if (in_array($druh, ['F02','F05','F12','F17','F21','F22','F23','F24','CZ02','CZ07'], true)) return 'iepirkumi';
    if (in_array($druh, ['F03','F06','F13','F25','CZ03'], true)) return 'rezultati';
    if (in_array($druh, ['F14','F20','CZ04'], true)) return 'izmainas';
    return 'citi';
}

/**
 * Saplacina eForms BT-lauku JSON koku: katrs BT/OPT lauks → vērtību saraksts.
 * Vērtības ar mērvienībām ({_value,_currencyID,...}) glabā kā masīvus.
 */
function vvz_flatten($node, array &$out): void {
    if (!is_array($node)) return;
    foreach ($node as $k => $v) {
        if (is_int($k)) { vvz_flatten($v, $out); continue; }
        if (is_array($v)) {
            if (array_key_exists('_value', $v) || array_key_exists('_currencyID', $v)) {
                $out[$k][] = $v;
            } else {
                vvz_flatten($v, $out);
            }
        } elseif ($v !== null && $v !== '') {
            $out[$k][] = $v;
        }
    }
}

/** Pirmā vērtība laukam, kura nosaukums sākas ar prefiksu (piem. 'BT-131(d)'). */
function vvz_bt(array $flat, string $prefix): mixed {
    foreach ($flat as $k => $vals) {
        if (str_starts_with($k, $prefix)) return $vals[0] ?? null;
    }
    return null;
}

/** Visas vērtības laukiem ar prefiksu. */
function vvz_bt_all(array $flat, string $prefix): array {
    $out = [];
    foreach ($flat as $k => $vals) {
        if (str_starts_with($k, $prefix)) $out = array_merge($out, $vals);
    }
    return $out;
}

/**
 * Būvē rindu no VVZ saraksta ieraksta ($sub) + bērna eForms datiem ($childData,
 * var būt null — tad tikai kopsavilkuma lauki).
 */
function vvz_build_notice(array $sub, ?array $childData): ?array {
    $d = $sub['data'] ?? [];
    $formNr = (string)($sub['variableId'] ?? '');
    if ($formNr === '') return null;
    $druh = (string)($d['druhFormulare'] ?? '');
    $category = vvz_category($druh, $d);

    $title = trim((string)($d['nazevZakazky'] ?? ''));
    $desc = null; $nature = null; $budget = null; $currency = 'EUR';
    $cpv = []; $nuts = null; $procType = null; $dlDate = null; $dlTime = null;

    // Saraksta ierakstā termiņš ir tieši (konkursa formām)
    [$dlDate, $dlTime] = nord_iso_dt($d['lhutaNabidkyZadosti'] ?? null);

    if ($childData !== null) {
        $flat = [];
        vvz_flatten($childData, $flat);
        $t2 = vvz_bt($flat, 'BT-21-');
        if (is_string($t2) && $t2 !== '' && $title === '') $title = trim($t2);
        $desc = vvz_bt($flat, 'BT-24-');
        $nat = mb_strtolower((string)vvz_bt($flat, 'BT-23-'), 'UTF-8');
        if (in_array($nat, ['works','services','supplies'], true)) $nature = $nat;
        foreach (array_merge(vvz_bt_all($flat, 'BT-262'), vvz_bt_all($flat, 'BT-263')) as $c) {
            if (is_string($c) && preg_match('/^(\d{8})/', $c, $m)) $cpv[] = $m[1];
        }
        $cpv = array_values(array_unique($cpv));
        // Vērtība: rezultātiem kopsumma, konkursiem aplēse
        foreach (['BT-720-', 'BT-161-', 'BT-27-'] as $bt) {
            $v = vvz_bt($flat, $bt);
            if (is_array($v) && isset($v['_value']) && is_numeric($v['_value'])) {
                $budget = (float)$v['_value'];
                if (!empty($v['_currencyID'])) $currency = (string)$v['_currencyID'];
                break;
            }
        }
        $nuts = vvz_bt($flat, 'BT-5071-');
        $procType = vvz_bt($flat, 'BT-105-');
        // Termiņš: MAX no lotu termiņiem (kā TED parserī)
        $dls = array_filter(vvz_bt_all($flat, 'BT-131(d)'), 'is_string');
        if ($dls) {
            sort($dls);
            $dlDate = ted_norm_date(end($dls)) ?? $dlDate;
            $t = vvz_bt($flat, 'BT-131(t)');
            if (is_string($t) && preg_match('/^(\d{2}:\d{2})/', $t, $m)) $dlTime = $m[1];
        }
    }
    if ($title === '') $title = (string)($d['uzivatelskyNazevFormulare'] ?? 'Bez nosaukuma');

    $buyer = null; $buyerId = null; $orgs = [];
    foreach ((array)($d['zadavatele'] ?? []) as $z) {
        $nm = trim((string)($z['nazev'] ?? ''));
        if ($nm === '') continue;
        if ($buyer === null) { $buyer = $nm; $buyerId = $z['ico'] ?? null; }
        $orgs[] = array_filter(['name' => $nm, 'reg_number' => $z['ico'] ?? null, 'country' => 'CZ']);
    }
    foreach ((array)($d['dodavatele'] ?? []) as $w) {
        $nm = trim((string)($w['nazev'] ?? ''));
        if ($nm !== '') $orgs[] = array_filter(['name' => $nm . ' (uzvarētājs)', 'reg_number' => $w['ico'] ?? null, 'country' => 'CZ']);
    }

    $pubDate = ted_norm_date($d['datumUverejneniVvz'] ?? null);

    return [
        'id'                 => 'VVZ-' . $formNr,
        'source'             => 'VVZ',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(is_string($desc) ? $desc : null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer ?? 'Nezināms pasūtītājs', 300),
        'buyer_id'           => is_scalar($buyerId) ? (string)$buyerId : null,
        'buyer_country'      => 'CZ',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $formNr,
        'budget'             => $budget,
        'currency'           => $currency,
        'document_url'       => sprintf(VVZ_FORM_URL_FMT, (string)($sub['id'] ?? '')),
        'buyer_profile_url'  => null,
        'procedure_type'     => is_string($procType) ? $procType : null,
        'notice_sub_type'    => $druh !== '' ? $druh : null,
        'notice_lang'        => 'CS',
        'issue_date'         => $pubDate,
        'main_nuts'          => is_string($nuts) ? $nuts : null,
        'main_country'       => 'CZ',
        'funding_program'    => null,
        'prev_notice_ref'    => isset($d['evCisloVvzSouvisejicihoFormulare']) ? (string)$d['evCisloVvzSouvisejicihoFormulare'] : null,
        'contract_folder_id' => isset($d['evCisloZakazkyVvz']) ? (string)$d['evCisloZakazkyVvz'] : null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode(array_slice($orgs, 0, KONKURSI_MAX_ORGS), JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'vvz-api',
    ];
}

// ═══════════════════════════ SK — ÚVO Vestník ════════════════════════════════

/**
 * Izvelk nacionālos ierakstus no vestnik dienas lapas HTML.
 * @return array<int,array{id:string,num:string,code:string,buyer:string,title:string,category:string}>
 */
function uvo_parse_day(string $html): array {
    // Sekciju robežas: id="vestnik-0-XX" ... līdz nākamajai sekcijai.
    // I = Redakčné opravy (ņem tikai IO* kodus — pārējie I-ieraksti ir procesuālas informācijas)
    // K = koncesijas (ņem tikai KP* = podlimitné, nacionālās; KO* nadlimitné nāk caur TED)
    $sections = ['WY' => 'iepirkumi', 'K' => 'iepirkumi', 'IP' => 'rezultati', 'DO' => 'izmainas', 'I' => 'izmainas'];
    $out = [];
    if (!preg_match_all('/id="vestnik-0-(\w+)"/', $html, $mm, PREG_OFFSET_CAPTURE)) return $out;
    $marks = $mm[1];
    foreach ($marks as $i => [$code, $pos]) {
        if (!isset($sections[$code])) continue;
        $end = isset($marks[$i + 1]) ? $marks[$i + 1][1] : strlen($html);
        $chunk = substr($html, $pos, $end - $pos);
        if (!preg_match_all(
            '#<a class="ul-link" href="/vestnik-a-registre/vestnik/oznamenie/detail/(\d+)[^"]*">\s*(\d+)\s*-\s*(\w+)\s*:\s*([^<]+?)\s*<br\s*/?>\s*<span>([^<]*)</span>#u',
            $chunk, $items, PREG_SET_ORDER)) continue;
        foreach ($items as $it) {
            if ($code === 'I' && !str_starts_with($it[3], 'IO')) continue;
            if ($code === 'K' && !str_starts_with($it[3], 'KP')) continue;
            $out[] = [
                'id'       => $it[1],
                'num'      => $it[2],
                'code'     => $it[3],
                'buyer'    => html_entity_decode(trim($it[4]), ENT_QUOTES | ENT_HTML5),
                'title'    => html_entity_decode(trim($it[5]), ENT_QUOTES | ENT_HTML5),
                'category' => $sections[$code],
            ];
        }
    }
    return $out;
}

/** Detaļu lapas <li>Etiķete: vērtība</li> pāri → multimap (pirmā vērtība paliek). */
function uvo_parse_detail(string $html): array {
    $out = [];
    if (preg_match_all('#<li>([^<:]{2,120}?):\s*([^<]+)</li>#u', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $k = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
            $v = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5);
            if ($v !== '' && !isset($out[$k])) $out[$k] = $v;
        }
    }
    return $out;
}

/** Pirmā vērtība, kuras etiķete satur kādu no adatām. */
function uvo_field(array $d, array $needles): ?string {
    foreach ($d as $k => $v) {
        foreach ($needles as $n) {
            if (mb_stripos($k, $n, 0, 'UTF-8') !== false) return $v;
        }
    }
    return null;
}

/** 'DD.MM.YYYY' → 'YYYY-MM-DD'. */
function uvo_norm_date(?string $s): ?string {
    if (!is_string($s) || !preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $s, $m)) return null;
    return $m[3] . '-' . $m[2] . '-' . $m[1];
}

/**
 * Būvē rindu no saraksta ieraksta + detaļu lauku kartes.
 * @return array<string,mixed>|null null = izlaižams (nadlimitné → TED)
 */
function uvo_build_notice(array $row, array $det, string $pubDate): ?array {
    // Nadlimitné pazīme detaļās: atsauce uz ES Oficiālo Vēstnesi / D24-D25 direktīvām
    $joined = implode(' | ', array_map(fn($k, $v) => "$k: $v", array_keys($det), $det));
    if (preg_match('/Ú\.\s*v\.\s*EÚ|2014\/24|2014\/25|32014L002[45]/u', $joined)
        && $row['category'] !== 'iepirkumi') {
        // WY sekcija vienmēr ir podlimitné; pārējām sekcijām ES atsauce = TED dublikāts
        return null;
    }

    $natMap = ['T' => 'supplies', 'S' => 'services', 'P' => 'works'];
    $nature = $natMap[substr($row['code'], -1)] ?? null;

    $budget = null; $currency = 'EUR';
    $val = uvo_field($det, ['Predpokladaná hodnota (BT-27', 'Hodnota rámcovej dohody', 'hodnota obstarávania']);
    if ($val !== null && preg_match('/([\d\s\xC2\xA0]+[.,]?\d*)/u', $val, $m)) {
        $num = (float)str_replace([',', ' ', "\u{00A0}"], ['.', '', ''], $m[1]);
        if ($num > 0) $budget = $num;
    }
    $men = uvo_field($det, ['(mena)']);
    if (is_string($men) && stripos($men, 'eur') !== false) $currency = 'EUR';

    $cpv = [];
    foreach ($det as $k => $v) {
        if (mb_stripos($k, 'CPV', 0, 'UTF-8') !== false && preg_match('/(\d{8})/', $v, $m)) $cpv[] = $m[1];
    }
    $cpv = array_values(array_unique($cpv));

    $dlDate = uvo_norm_date(uvo_field($det, ['Lehota na predkladanie ponúk (dátum']));
    $dlTime = null;
    $t = uvo_field($det, ['Lehota na predkladanie ponúk (čas']);
    if (is_string($t) && preg_match('/(\d{1,2}:\d{2})/', $t, $m)) $dlTime = strlen($m[1]) === 4 ? '0' . $m[1] : $m[1];

    $desc = uvo_field($det, ['Opis obstarávania', 'Opis (', 'Opis:']) ?? uvo_field($det, ['Opis']);
    $buyerId = uvo_field($det, ['IČO']);
    $winner = uvo_field($det, ['Názov víťaza', 'úspešného uchádzača']);

    $orgs = [array_filter(['name' => $row['buyer'], 'reg_number' => $buyerId, 'country' => 'SK'])];
    if (is_string($winner) && $winner !== '') {
        $orgs[] = ['name' => $winner . ' (uzvarētājs)', 'country' => 'SK'];
    }

    return [
        'id'                 => 'UVO-' . $row['id'],
        'source'             => 'UVO',
        'category'           => $row['category'],
        'title'              => ted_truncate($row['title'] !== '' ? $row['title'] : $row['buyer'], 400),
        'description'        => ted_truncate($desc, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($row['buyer'], 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'SK',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $row['num'] . ' - ' . $row['code'],
        'budget'             => $budget,
        'currency'           => $currency,
        'document_url'       => sprintf(UVO_DETAIL_URL_FMT, $row['id']),
        'buyer_profile_url'  => null,
        'procedure_type'     => uvo_field($det, ['Druh postupu']),
        'notice_sub_type'    => $row['code'],
        'notice_lang'        => 'SK',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'SK',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'uvo-vestnik',
    ];
}

// ═══════════════════════════ BE — BOSA e-Procurement ═════════════════════════

/** Pirmais teksts vēlamajā valodu secībā no [{language,text}] saraksta. */
function bosa_text($list, array $langs = ['NL', 'FR', 'EN', 'DE']): ?string {
    if (!is_array($list) || !$list) return null;
    foreach ($langs as $lg) {
        foreach ($list as $e) {
            if (is_array($e) && ($e['language'] ?? '') === $lg && trim((string)($e['text'] ?? '')) !== '') {
                return trim((string)$e['text']);
            }
        }
    }
    $first = $list[0];
    return is_array($first) ? (trim((string)($first['text'] ?? '')) ?: null) : null;
}

/** Kategorija no noticeSubType (E1-E6 = BE nacionālās formas, skaitļi = eForms). */
function bosa_category(string $st): string {
    if ($st === 'E1') return 'citi';                       // plānošana (PLANNING)
    if ($st === 'E2' || $st === 'E3') return 'iepirkumi';  // konkursi (COMPETITION)
    if (in_array($st, ['E4', 'E5', 'E6'], true)) return 'rezultati'; // piešķiršanas (AWARD)
    if (ctype_digit($st)) {
        $n = (int)$st;
        if ($n <= 9) return 'citi';
        if ($n <= 24) return 'iepirkumi';
        if ($n <= 37) return 'rezultati';
        return 'izmainas';
    }
    return 'citi';
}

/**
 * Publikācijas ieraksts no meklēšanas API → rinda.
 * @return array<string,mixed>|null null = TED dublikāts
 */
function bosa_parse_publication(array $p): ?array {
    if (($p['tedPublished'] ?? false) === true) return null;
    if (!empty($p['publicationReferenceNumbersTED'])) return null;

    $wsId = (string)($p['publicationWorkspaceId'] ?? '');
    if ($wsId === '') return null;
    $st = (string)($p['noticeSubType'] ?? '');
    $category = bosa_category($st);

    $dossier = $p['dossier'] ?? [];
    $title = bosa_text($dossier['titles'] ?? null) ?? bosa_text($dossier['descriptions'] ?? null);
    if ($title === null) return null;
    $desc = bosa_text($dossier['descriptions'] ?? null);

    $natMap = ['WORKS' => 'works', 'SUPPLIES' => 'supplies', 'SERVICES' => 'services'];
    $nature = $natMap[strtoupper((string)(($p['natures'] ?? [null])[0] ?? ''))] ?? null;

    $cpv = [];
    $mainCpv = $p['cpvMainCode']['code'] ?? null;
    if (is_string($mainCpv) && preg_match('/^(\d{8})/', $mainCpv, $m)) $cpv[] = $m[1];
    foreach ((array)($p['cpvAdditionalCodes'] ?? []) as $c) {
        if (is_array($c) && preg_match('/^(\d{8})/', (string)($c['code'] ?? ''), $m)) $cpv[] = $m[1];
    }
    $cpv = array_values(array_unique($cpv));

    $buyer = bosa_text($p['organisation']['organisationNames'] ?? null) ?? 'Nezināms pasūtītājs';
    $nuts = (array)($p['nutsCodes'] ?? []);

    $lots = [];
    foreach (array_slice((array)($p['lots'] ?? []), 0, KONKURSI_MAX_LOTS) as $lot) {
        if (!is_array($lot)) continue;
        $lt = bosa_text($lot['titles'] ?? null);
        $ld = bosa_text($lot['descriptions'] ?? null);
        if ($lt !== null || $ld !== null) {
            $lots[] = array_filter(['title' => $lt, 'description' => ted_truncate($ld, KONKURSI_LOT_DESC_MAX)]);
        }
    }

    return [
        'id'                 => 'BOSA-' . $wsId,
        'source'             => 'BOSA',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate($desc, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'BE',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => ted_norm_date($p['publicationDate'] ?? null),
        // Termiņš ir tepat sarakstā (vaultSubmissionDeadline) — workspace
        // pieprasījums sync posmā paliek tikai kā atkāpe, ja te tukšs.
        'deadline_date'      => nord_iso_dt(is_string($p['vaultSubmissionDeadline'] ?? null) ? $p['vaultSubmissionDeadline'] : null)[0],
        'deadline_time'      => nord_iso_dt(is_string($p['vaultSubmissionDeadline'] ?? null) ? $p['vaultSubmissionDeadline'] : null)[1],
        'publication_number' => (string)($p['referenceNumber'] ?? $wsId),
        'budget'             => null,
        'currency'           => 'EUR',
        'document_url'       => sprintf(BOSA_PAGE_URL_FMT, $wsId),
        'buyer_profile_url'  => null,
        'procedure_type'     => isset($dossier['procurementProcedureType']) ? (string)$dossier['procurementProcedureType'] : null,
        'notice_sub_type'    => $st !== '' ? $st : null,
        'notice_lang'        => strtoupper((string)(($p['publicationLanguages'] ?? ['NL'])[0] ?? 'NL')),
        'issue_date'         => ted_norm_date($p['dispatchDate'] ?? null),
        'main_nuts'          => is_string($nuts[0] ?? null) ? $nuts[0] : null,
        'main_country'       => 'BE',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => isset($dossier['number']) ? (string)$dossier['number'] : null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => json_encode($lots, JSON_UNESCAPED_UNICODE),
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'country' => 'BE'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'bosa-api',
    ];
}

// ═══════════════════════════ AT — Kerndaten KDQ ══════════════════════════════

/**
 * KDQ indekss → [[id, lastmod, url], ...].
 * @return array<int,array{0:string,1:string,2:string}>
 */
function atkd_parse_index(string $xml): array {
    $out = [];
    if (preg_match_all('#<item\b[^>]*?\bid="([^"]+)"[^>]*?\blastmod="([^"]+)"[^>]*>\s*<url>([^<]+)</url>#s', $xml, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $out[] = [$m[1], $m[2], html_entity_decode(trim($m[3]), ENT_QUOTES | ENT_HTML5)];
        }
    }
    return $out;
}

/**
 * Viens Kerndaten dokuments (KD_* sakne, vecais TED F-formu stils) → rinda.
 * @return array<string,mixed>|null null = virs ES sliekšņa (TED) vai neparsējams
 */
function atkd_parse_item(string $xml, string $feed, string $itemId, string $itemUrl, string $lastmod): ?array {
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok || $doc->documentElement === null) return null;
    $root = $doc->documentElement;
    $rootName = $root->localName;

    if (es_first($root, 'ABOVETHRESHOLD') !== null) return null; // virs sliekšņa → TED

    $title = null;
    $tEl = es_first($root, 'TITLE');
    if ($tEl !== null) $title = trim($tEl->textContent);
    if ($title === null || $title === '') return null;

    $descEl = es_first($root, 'SHORT_DESCR');
    $desc = $descEl !== null ? trim($descEl->textContent) : null;

    $buyer = null; $buyerId = null;
    $cb = es_first($root, 'ADDRESS_CONTRACTING_BODY');
    if ($cb !== null) {
        $buyer = es_text($cb, 'OFFICIALNAME');
        $buyerId = es_text($cb, 'NATIONALID');
    }

    $cpv = [];
    foreach (es_all($root, 'CPV_CODE') as $c) {
        $code = $c->getAttribute('CODE');
        if (preg_match('/^(\d{8})/', $code, $m)) $cpv[] = $m[1];
    }
    $cpv = array_values(array_unique($cpv));

    $nature = null;
    $tc = es_first($root, 'TYPE_CONTRACT');
    if ($tc !== null) {
        $nature = ['WORKS' => 'works', 'SUPPLIES' => 'supplies', 'SERVICES' => 'services'][strtoupper($tc->getAttribute('CTYPE'))] ?? null;
    }

    $nutsEl = es_first($root, 'NUTS');
    $nuts = $nutsEl !== null ? ($nutsEl->getAttribute('CODE') ?: null) : null;

    // Vērtība: piešķirtā kopsumma vai aplēse
    $budget = null; $currency = 'EUR';
    foreach (['VAL_TOTAL', 'VAL_ESTIMATED_TOTAL', 'VAL_OBJECT'] as $tag) {
        $v = es_first($root, $tag);
        if ($v !== null && is_numeric(trim($v->textContent))) {
            $budget = (float)trim($v->textContent);
            if ($v->getAttribute('CURRENCY') !== '') $currency = $v->getAttribute('CURRENCY');
            break;
        }
    }

    $award = es_first($root, 'AWARD_CONTRACT');
    $category = ($award !== null || str_starts_with($rootName, 'KD_8_2') || str_starts_with($rootName, 'KD_8_3'))
        ? 'rezultati' : 'iepirkumi';

    $orgs = [];
    if ($buyer !== null) $orgs[] = array_filter(['name' => $buyer, 'reg_number' => $buyerId, 'country' => 'AT']);
    $awardDate = null;
    if ($award !== null) {
        $awardDate = ted_norm_date(es_text($award, 'DATE_CONCLUSION_CONTRACT'));
        foreach (es_all($award, 'ADDRESS_CONTRACTOR') as $ac) {
            $wn = es_text($ac, 'OFFICIALNAME');
            if ($wn !== null) $orgs[] = ['name' => $wn . ' (uzvarētājs)', 'country' => 'AT'];
        }
    }

    // Termiņš (ja shēmā dots). vemap KDQ lieto DATETIME_RECEIPT_TENDERS
    // ('2026-08-07T12:00:00'); ANKÖ un ausschreibung.at dokumentos termiņa
    // NAV vispār — tiem paliek 'termiņš avota lapā'.
    $dlDate = null; $dlTime = null;
    $dtEl = es_first($root, 'DATETIME_RECEIPT_TENDERS');
    if ($dtEl !== null) {
        [$dlDate, $dlTime] = nord_iso_dt(trim($dtEl->textContent));
    }
    foreach (['DATE_RECEIPT_TENDERS', 'DEADLINE_RECEIPT_TENDERS', 'DATE_TENDER'] as $tag) {
        if ($dlDate !== null) break;
        $el = es_first($root, $tag);
        if ($el !== null) {
            $dlDate = ted_norm_date(trim($el->textContent));
        }
    }
    $tt = es_first($root, 'TIME_RECEIPT_TENDERS');
    if ($tt !== null && preg_match('/(\d{1,2}:\d{2})/', $tt->textContent, $m)) {
        $dlTime = strlen($m[1]) === 4 ? '0' . $m[1] : $m[1];
    }

    $pubDate = ted_norm_date(es_text($root, 'DATE_FIRST_PUBLICATION')) ?? $awardDate ?? ted_norm_date($lastmod);

    $docUrl = es_text($root, 'URL_DOCUMENT');
    $ref = es_text($root, 'REFERENCE_NUMBER');

    return [
        'id'                 => 'ATKD-' . $feed . '-' . $itemId,
        'source'             => 'ATKD',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate($desc, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer ?? 'Nezināms pasūtītājs', 300),
        'buyer_id'           => $buyerId,
        'buyer_country'      => 'AT',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $ref ?? $itemId,
        'budget'             => $budget,
        'currency'           => $currency,
        'document_url'       => ($docUrl !== null && str_starts_with($docUrl, 'http')) ? $docUrl : $itemUrl,
        'buyer_profile_url'  => null,
        'procedure_type'     => null,
        'notice_sub_type'    => $rootName,
        'notice_lang'        => 'DE',
        'issue_date'         => $pubDate,
        'main_nuts'          => $nuts,
        'main_country'       => 'AT',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => null,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode(array_slice($orgs, 0, KONKURSI_MAX_ORGS), JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'atkd-' . $feed,
    ];
}
