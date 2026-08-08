<?php
/**
 * konkursi/lib/west_parser.php — FR (BOAMP) un NL (TenderNed) parseri.
 *
 * BOAMP: DILA OpenDataSoft API ieraksts → notices rinda (famille='JOUE' = ES
 * Oficiālais Vēstnesis → dublējas TED → izlaiž).
 * TenderNed: publiskā papi ieraksts → notices rinda (europees=true → izlaiž).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ted_parser.php';     // ted_truncate(), ted_norm_date()
require_once __DIR__ . '/nordics_parser.php'; // nord_iso_dt()

// ── FR: BOAMP ─────────────────────────────────────────────────────────────────

/**
 * Rekursīvi meklē CPV kodus donnees struktūrā.
 *
 * Kods NAV cpv-atslēgas tiešais bērns, bet divus līmeņus dziļāk:
 *   codeCPV/objetPrincipal/classPrincipale = "66510000"
 * Tāpēc, ieejot cpv-mezglā, jāsavāc VISAS 8-ciparu virknes zem tā (nevis
 * jārekursē ar to pašu atslēgas nosacījumu — apakšatslēgās 'cpv' vairs nav).
 */
function boamp_find_cpv($node, array &$out, int $depth = 0): void {
    if ($depth > 8 || count($out) >= 10) return;
    if (!is_array($node)) return;
    foreach ($node as $k => $v) {
        if (is_string($k) && stripos($k, 'cpv') !== false) {
            boamp_collect_codes($v, $out);
        } elseif (is_array($v)) {
            boamp_find_cpv($v, $out, $depth + 1);
        }
    }
}

/** Savāc visas 8-ciparu CPV virknes koka zarā (bez atslēgu nosacījumiem). */
function boamp_collect_codes($node, array &$out, int $depth = 0): void {
    if ($depth > 6 || count($out) >= 10) return;
    if (is_array($node)) {
        foreach ($node as $v) boamp_collect_codes($v, $out, $depth + 1);
        return;
    }
    if (is_string($node) && preg_match('/^(\d{8})$/', trim($node), $m)) $out[] = $m[1];
}

/**
 * Atgriež donnees satura sakni. Katrai formu saimei sava shēma
 * (FNSimple / MAPA / DSP / DIVERS), bet apakšstruktūra ir līdzīga.
 * @return array<string,mixed>
 */
function boamp_donnees(array $it): array {
    $d = $it['donnees'] ?? null;
    if (is_string($d)) $d = json_decode($d, true);
    if (!is_array($d)) return [];
    foreach ($d as $v) { if (is_array($v)) return $v; }
    return [];
}

/** Pirmā netukšā skalārā vērtība zarā (BOAMP lauks var būt virkne VAI masīvs). */
function boamp_scalar($v): string {
    if (is_array($v)) {
        foreach ($v as $x) { $r = boamp_scalar($x); if ($r !== '') return $r; }
        return '';
    }
    return is_scalar($v) ? trim((string)$v) : '';
}

/** Pirmā netukšā vērtība no vairākiem ceļiem: boamp_pick($f, ['a','b'], ['c']). */
function boamp_pick(array $root, array ...$paths): string {
    foreach ($paths as $path) {
        $n = $root;
        foreach ($path as $seg) {
            if (!is_array($n) || !array_key_exists($seg, $n)) { $n = null; break; }
            $n = $n[$seg];
        }
        $s = boamp_scalar($n);
        if ($s !== '') return $s;
    }
    return '';
}

/** @return array<string,mixed>|null */
function boamp_parse_item(array $it): ?array {
    if (strtoupper((string)($it['famille'] ?? '')) === 'JOUE') return null; // ES OV → TED
    $idweb = (string)($it['idweb'] ?? '');
    $title = trim((string)($it['objet'] ?? ''));
    if ($idweb === '' || $title === '') return null;

    $category = match (strtoupper((string)($it['nature'] ?? ''))) {
        'APPEL_OFFRE'                  => 'iepirkumi',
        'ATTRIBUTION'                  => 'rezultati',
        'RECTIFICATIF', 'MODIFICATION' => 'izmainas',
        default                        => 'citi',
    };

    $tm = $it['type_marche'] ?? null;
    $tm0 = strtoupper((string)(is_array($tm) ? ($tm[0] ?? '') : $tm));
    $nature = match ($tm0) {
        'SERVICES'  => 'services',
        'FOURNITURES' => 'supplies',
        'TRAVAUX'   => 'works',
        default     => null,
    };

    $proc = match (strtoupper((string)($it['type_procedure'] ?? ''))) {
        'OUVERT'   => 'open',
        'RESTREINT' => 'restricted',
        'NEGOCIE'  => 'neg-w-call',
        'ADAPTE'   => 'oth-single',
        default    => null,
    };

    [$dlDate, $dlTime] = nord_iso_dt($it['datelimitereponse'] ?? null);
    $pubDate = ted_norm_date(is_string($it['dateparution'] ?? null) ? $it['dateparution'] : null);

    // donnees satur visu pārējo (CPV, aprakstu, SIRET, daļas, vērtību) — API
    // saknes līmenī šo lauku nav.
    $f = boamp_donnees($it);

    $cpv = [];
    boamp_find_cpv($f, $cpv);
    $cpv = array_values(array_unique($cpv));

    $desc = boamp_pick($f,
        ['initial', 'natureMarche', 'description'],      // FNSimple
        ['initial', 'description', 'objet'],             // MAPA
        ['initial', 'descriptionMarche', 'objetMarche'], // DSP
        ['initial', 'caracteristiques', 'principales']);

    // codeIdentificationNational = pircēja SIRET (14 cipari) — Francijā tā ir
    // universālā atslēga uz uzņēmumu reģistru un TED buyer_id.
    $siret = preg_replace('/\D/', '', boamp_pick($f,
        ['organisme', 'codeIdentificationNational'],
        ['organisme', 'typeIdentificationNational', 'siret'])) ?? '';
    if (strlen($siret) !== 14) $siret = '';

    // Pircēja iekšējā procedūras atsauce. Kopā ar SIRET tā identificē procedūru:
    // vienu un to pašu iepirkumu BOAMP mēdz publicēt ar diviem idweb (termiņa
    // pagarinājums bez RECTIFICATIF), un tikai šis pāris tos sasaista.
    $ref = boamp_pick($f,
        ['initial', 'communication', 'identifiantInterne'],
        ['initial', 'renseignements', 'idMarche']);
    $cfid = ($siret !== '' && $ref !== '') ? $siret . '/' . mb_substr($ref, 0, 60) : null;

    $budget = null;
    $valRaw = boamp_pick($f,
        ['initial', 'natureMarche', 'valeurEstimee', 'valeur'],
        ['initial', 'natureMarche', 'valeurEstimee', 'fourchette', 'valeurHaute'],
        ['initial', 'descriptionMarche', 'valeurEstimee', 'valeur']);
    if ($valRaw !== '') {
        $v = (float)str_replace([' ', ',', "\u{a0}"], ['', '.', ''], $valRaw);
        if ($v > 0 && $v < 1e12) $budget = $v;
    }

    $profile = boamp_pick($f,
        ['initial', 'communication', 'urlProfilAch'],
        ['organisme', 'urlProfilAcheteur']);
    if ($profile !== '' && !preg_match('~^https?://~i', $profile)) $profile = '';

    // Daļas (lots): tikai numurs + apraksts — pārējais ir BOAMP oriģinālā.
    $lots = [];
    $lotNode = $f['initial']['lots']['lot'] ?? null;
    if (is_array($lotNode)) {
        $list = array_is_list($lotNode) ? $lotNode : [$lotNode];
        foreach (array_slice($list, 0, 40) as $i => $l) {
            if (!is_array($l)) continue;
            $ld = boamp_scalar($l['description'] ?? '');
            if ($ld === '') continue;
            $lots[] = array_filter([
                'number' => boamp_scalar($l['numLot'] ?? '') ?: (string)($i + 1),
                'title'  => ted_truncate($ld, 300),
            ]);
        }
    }

    $url = is_string($it['url_avis'] ?? null) && $it['url_avis'] !== '' ? $it['url_avis'] : null;
    $buyer = trim((string)($it['nomacheteur'] ?? ''));
    $dep = $it['code_departement'] ?? null;
    $depStr = is_array($dep) ? implode(' ', array_filter($dep, 'is_string')) : (string)$dep;

    return [
        'id'                 => 'BOAMP-' . $idweb,
        'source'             => 'BOAMP',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => $desc !== '' ? ted_truncate($desc, 2000) : null,
        'buyer_name'         => ted_truncate($buyer !== '' ? $buyer : 'Nezināms pasūtītājs', 300),
        'buyer_id'           => $siret !== '' ? $siret : null,
        'buyer_country'      => 'FR',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => $idweb,
        'budget'             => $budget,
        'currency'           => 'EUR',
        'document_url'       => $url,
        'buyer_profile_url'  => $profile !== '' ? $profile : null,
        'procedure_type'     => $proc,
        'notice_sub_type'    => is_string($it['famille'] ?? null) ? $it['famille'] : null,
        'notice_lang'        => 'FR',
        'issue_date'         => $pubDate,
        'main_nuts'          => $depStr !== '' ? ted_truncate('FR-' . $depStr, 40) : null,
        'main_country'       => 'FR',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => is_string($it['contractfolderid'] ?? null) && $it['contractfolderid'] !== ''
                                ? $it['contractfolderid'] : $cfid,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => json_encode($lots, JSON_UNESCAPED_UNICODE),
        'organizations'      => json_encode([array_filter([
                                    'name'    => $buyer !== '' ? $buyer : null,
                                    'id'      => $siret !== '' ? $siret : null,
                                    'city'    => boamp_pick($f, ['organisme', 'ville'], ['organisme', 'adr', 'ville']) ?: null,
                                    'country' => 'FR',
                                ])], JSON_UNESCAPED_UNICODE),
        // MAPA formā ir correspondantPRM/nom un coord/tel — personas dati,
        // kurus apzināti NEIEVĀC (sk. ks_public_contact).
        'notice_contact'     => '{}',
        'source_file'        => 'boamp-api',
    ];
}

// ── NL: TenderNed ─────────────────────────────────────────────────────────────

/** @return array<string,mixed>|null */
function tenderned_parse_item(array $it): ?array {
    if (($it['europees'] ?? false) === true) return null; // ES līmenis → TED
    $id = (string)($it['publicatieId'] ?? '');
    $title = trim((string)($it['aanbestedingNaam'] ?? ''));
    if ($id === '' || $title === '') return null;

    $typeCode = strtoupper((string)(($it['typePublicatie'] ?? [])['code'] ?? ''));
    $pubDesc = mb_strtolower((string)(($it['publicatiecode'] ?? [])['omschrijving'] ?? '')
        . ' ' . (string)(($it['typePublicatie'] ?? [])['omschrijving'] ?? ''), 'UTF-8');
    if ($typeCode === 'REC' || str_contains($pubDesc, 'rectificatie') || str_contains($pubDesc, 'wijziging')) {
        $category = 'izmainas';
    } elseif (str_contains($pubDesc, 'gegunde') || str_contains($pubDesc, 'gunning') || str_contains($pubDesc, 'resultaat')) {
        $category = 'rezultati';
    } elseif (str_contains($pubDesc, 'vooraankondiging') || str_contains($pubDesc, 'marktconsultatie')) {
        $category = 'citi';
    } else {
        $category = 'iepirkumi';
    }

    $nature = match (strtoupper((string)(($it['typeOpdracht'] ?? [])['code'] ?? ''))) {
        'D'     => 'services',
        'L'     => 'supplies',
        'W'     => 'works',
        default => null,
    };
    $proc = match (strtoupper((string)(($it['procedure'] ?? [])['code'] ?? ''))) {
        'OPE'   => 'open',
        'RES', 'NIE' => 'restricted',
        'OND'   => 'neg-w-call',
        default => null,
    };

    [$dlDate, $dlTime] = nord_iso_dt($it['sluitingsDatum'] ?? null);
    $pubDate = ted_norm_date(is_string($it['publicatieDatum'] ?? null) ? $it['publicatieDatum'] : null);
    $url = (($it['link'] ?? [])['href'] ?? null);

    return [
        'id'                 => 'TENDERNED-' . $id,
        'source'             => 'TENDERNED',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(is_string($it['opdrachtBeschrijving'] ?? null) ? $it['opdrachtBeschrijving'] : null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate(trim((string)($it['opdrachtgeverNaam'] ?? 'Nezināms pasūtītājs')), 300),
        'buyer_id'           => null,
        'buyer_country'      => 'NL',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $dlDate,
        'deadline_time'      => $dlTime,
        'publication_number' => is_string($it['kenmerk'] ?? null) || is_int($it['kenmerk'] ?? null) ? (string)$it['kenmerk'] : $id,
        'budget'             => null,
        'currency'           => 'EUR',
        'document_url'       => is_string($url) ? $url : null,
        'buyer_profile_url'  => null,
        'procedure_type'     => $proc,
        'notice_sub_type'    => (($it['publicatiecode'] ?? [])['code'] ?? null),
        'notice_lang'        => 'NL',
        'issue_date'         => $pubDate,
        'main_nuts'          => null,
        'main_country'       => 'NL',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        // kenmerk = TenderNed dosjē numurs, kopīgs oriģinālam un korekcijām —
        // bez tā ks_dedupe_notices tos neredz (orģināls+korekcija abi 'iepirkumi')
        'contract_folder_id' => is_string($it['kenmerk'] ?? null) || is_int($it['kenmerk'] ?? null) ? (string)$it['kenmerk'] : null,
        'main_cpv'           => null, // saraksta API CPV nedod
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $it['opdrachtgeverNaam'] ?? null, 'country' => 'NL'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'tenderned-api',
    ];
}

// ═══════════════════════ UK — OCDS (Contracts Finder / PCS) ══════════════════

/** OCDS ISO datums → ['YYYY-MM-DD', 'HH:MM'] */
function uk_ocds_dt($s): array {
    if (!is_string($s) || !preg_match('/^(\d{4}-\d{2}-\d{2})(?:T(\d{2}:\d{2}))?/', $s, $m)) return [null, null];
    return [$m[1], $m[2] ?? null];
}

/**
 * OCDS release (Contracts Finder vai PCS) → notices rinda.
 *
 * Abi avoti dod OCDS 1.1 ar vienādu formu, tāpēc viens parseris. Kategoriju
 * nosaka 'tag': tender = konkurss, award/contract = rezultāts.
 * @param string $src 'UKCF' | 'UKPCS'
 * @return array<string,mixed>|null
 */
function uk_ocds_notice(array $r, string $src): ?array {
    $ocid = trim((string)($r['ocid'] ?? ''));
    $t = is_array($r['tender'] ?? null) ? $r['tender'] : [];
    $title = trim((string)($t['title'] ?? ''));
    if ($ocid === '' || $title === '') return null;

    $tags = array_map('strval', (array)($r['tag'] ?? []));
    $category = 'iepirkumi';
    foreach ($tags as $tag) {
        $tl = strtolower($tag);
        if ($tl === 'award' || $tl === 'awardupdate' || $tl === 'contract') { $category = 'rezultati'; break; }
        if ($tl === 'planning') { $category = 'citi'; break; }
    }

    // Nolikuma/līguma vērtība; PCS un CF abi lieto GBP
    $budget = null; $currency = 'GBP';
    foreach ([$t['value'] ?? null, ($r['awards'][0]['value'] ?? null)] as $v) {
        if (is_array($v) && isset($v['amount']) && is_numeric($v['amount']) && (float)$v['amount'] > 0) {
            $budget = (float)$v['amount'];
            if (!empty($v['currency'])) $currency = (string)$v['currency'];
            break;
        }
    }

    $cpv = [];
    foreach ([$t['classification'] ?? null] as $c) {
        if (is_array($c) && preg_match('/^(\d{8})/', (string)($c['id'] ?? ''), $m)) $cpv[] = $m[1];
    }
    foreach ((array)($t['items'] ?? []) as $it) {
        if (is_array($it) && preg_match('/^(\d{8})/', (string)($it['classification']['id'] ?? ''), $m)) $cpv[] = $m[1];
        foreach ((array)($it['additionalClassifications'] ?? []) as $ac) {
            if (is_array($ac) && preg_match('/^(\d{8})/', (string)($ac['id'] ?? ''), $m)) $cpv[] = $m[1];
        }
    }
    $cpv = array_values(array_unique($cpv));

    $nature = match (strtolower((string)($t['mainProcurementCategory'] ?? ''))) {
        'works'   => 'works',
        'goods'   => 'supplies',
        'services' => 'services',
        default   => null,
    };

    [$pubDate] = uk_ocds_dt($t['datePublished'] ?? ($r['date'] ?? null));
    [$dlDate, $dlTime] = uk_ocds_dt($t['tenderPeriod']['endDate'] ?? null);

    $buyer = trim((string)($r['buyer']['name'] ?? '')) ?: 'Nezināms pasūtītājs';
    $orgs = [array_filter(['name' => $buyer, 'reg_number' => (string)($r['buyer']['id'] ?? '') ?: null, 'country' => 'GB'])];
    foreach ((array)($r['awards'] ?? []) as $aw) {
        foreach ((array)($aw['suppliers'] ?? []) as $sup) {
            $sn = trim((string)($sup['name'] ?? ''));
            if ($sn !== '') $orgs[] = ['name' => $sn . ' (uzvarētājs)', 'country' => 'GB'];
        }
    }

    // Pasta indekss ir vienīgā ģeogrāfija, ko abi avoti dod stabili
    $post = null;
    foreach ((array)($t['items'] ?? []) as $it) {
        foreach ((array)($it['deliveryAddresses'] ?? []) as $ad) {
            if (!empty($ad['postalCode'])) { $post = (string)$ad['postalCode']; break 2; }
        }
    }

    // Web saites (pārbaudīts pārlūkā 2026-07-24): FTS lapa lieto paziņojuma numuru-gadu
    // (releases[].id, piem. '069803-2026') — /Notice/{ocid} dod "Page not found"; CF lapa
    // lieto paziņojuma UUID (releases[].id bez skaitliskā sufiksa '-908105'). Ocid atkāpe
    // paliek tikai gadījumam, ja release id formāts negaidīti mainās.
    $relId = trim((string)($r['id'] ?? ''));
    // PCS: release id 'rls-1-JUL560766' → lapas ID ir bez 'rls-N-' prefiksa (ar prefiksu
    // search_view.aspx dod "Bad Page parameters").
    $pcsId = preg_match('/^rls-\d+-(.+)$/', $relId, $pm) ? $pm[1] : ($relId !== '' ? $relId : $ocid);
    $view = match ($src) {
        'UKPCS' => sprintf(UK_PCS_VIEW_FMT, rawurlencode($pcsId)),
        'UKFTS' => sprintf(UK_FTS_VIEW_FMT, rawurlencode(preg_match('/^\d{5,7}-\d{4}$/', $relId) ? $relId : $ocid)),
        default => sprintf(UK_CF_VIEW_FMT, rawurlencode(preg_match('/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})-\d+$/i', $relId, $m) ? $m[1] : $ocid)),
    };

    return [
        'id'                 => 'UK-' . $ocid,
        '_rdate'             => (string)($r['date'] ?? ''), // release datums — ks_sync_uk agrēgācijai (store ignorē)
        'source'             => $src,
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(trim((string)($t['description'] ?? '')) ?: null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => (string)($r['buyer']['id'] ?? '') ?: null,
        'buyer_country'      => 'GB',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => (string)($t['id'] ?? $ocid),
        'budget'             => $budget,
        'currency'           => $currency,
        'document_url'       => $view,
        'buyer_profile_url'  => null,
        'procedure_type'     => ted_truncate((string)($t['procurementMethodDetails'] ?? ''), 120) ?: null,
        'notice_sub_type'    => $tags ? ted_truncate(implode(',', $tags), 40) : null,
        'notice_lang'        => 'EN',
        'issue_date'         => $pubDate,
        'main_nuts'          => $post !== null ? ted_truncate('GB-' . $post, 40) : null,
        'main_country'       => 'GB',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $ocid,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => match ($src) {
            'UKPCS' => 'pcs-ocds',
            'UKFTS' => 'findatender-ocds',
            default => 'contractsfinder-ocds',
        },
    ];
}

// ─────────────────────────── SIMAP (Šveice) ──────────────────────────────────

/**
 * Izvēlas pirmo aizpildīto valodu no simap Translation objekta ({de,fr,it,en}).
 * Šveices dati ir reģiona valodā (Cīrihe=de, Ženēva=fr, Ticino=it), tāpēc rādām
 * oriģinālu, nevis tulkojam. Atgriež [teksts|null, valodasKods].
 */
function simap_pick(array $tr): array {
    foreach (['de', 'fr', 'it', 'en'] as $l) {
        $v = trim((string)($tr[$l] ?? ''));
        if ($v !== '') return [$v, $l];
    }
    return [null, 'de'];
}

/** simap ISO ("2026-08-31T00:00:00+02:00") → ['Y-m-d','H:i'] vai [null,null]. */
function simap_dt(?string $iso): array {
    if (!is_string($iso) || $iso === '') return [null, null];
    try {
        $d = new DateTimeImmutable($iso);
        return [$d->format('Y-m-d'), $d->format('H:i')];
    } catch (Throwable $e) {
        return [null, null];
    }
}

/** Izvelk 8-ciparu CPV kodu no simap cpvCode objekta ({code,label}) vai virknes. */
function simap_cpv_code($c): ?string {
    $raw = is_array($c) ? (string)($c['code'] ?? '') : (string)$c;
    return preg_match('/(\d{8})/', $raw, $m) ? $m[1] : null;
}

/**
 * simap projekts (+ neobligātā detaļa) → notices rinda vai null.
 *
 * $p       — viens project-search ieraksts (nosaukums, birojs, kantons, datums).
 * $detail  — publication-details atbilde (dod termiņu + CPV + aprakstu); ja null,
 *            kartē tikai saraksta laukus (rezultātiem termiņš nav vajadzīgs).
 * $category— 'iepirkumi' vai 'rezultati' (nosaka izsaucošais gājiens).
 */
function simap_notice(array $p, ?array $detail, string $category): ?array {
    $pid = trim((string)($p['id'] ?? ''));
    [$title, $lang] = simap_pick((array)($p['title'] ?? []));
    if ($pid === '' || $title === null) return null;

    [$office] = simap_pick((array)($p['procOfficeName'] ?? []));
    $addr = is_array($p['orderAddress'] ?? null) ? $p['orderAddress'] : [];
    $canton = strtoupper(trim((string)($addr['cantonId'] ?? '')));

    $nature = match ((string)($p['projectSubType'] ?? '')) {
        'construction' => 'works',
        'supply'       => 'supplies',
        'service', 'project_study', 'idea_study', 'request_for_information' => 'services',
        default        => null,
    };
    $proc = match ((string)($p['processType'] ?? '')) {
        'open'       => 'Atklāta procedūra',
        'selective'  => 'Selektīva procedūra',
        'invitation' => 'Uzaicinājuma procedūra',
        'direct'     => 'Tiešā piešķiršana',
        default      => null,
    };

    [$dlDate, $dlTime] = simap_dt($detail['dates']['offerDeadline'] ?? null);

    // Apraksts + CPV tikai no detaļas. CPV Šveices vietējos bieži nav aizpildīts
    // (izmanto BKP/NPK), tāpēc tas var palikt tukšs — tas nav kļūda.
    $desc = null;
    $cpv = [];
    if (is_array($detail)) {
        $pr = is_array($detail['procurement'] ?? null) ? $detail['procurement'] : [];
        [$od] = simap_pick((array)($pr['orderDescription'] ?? []));
        if ($od !== null) $desc = trim(html_entity_decode(strip_tags($od), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($c = simap_cpv_code($pr['cpvCode'] ?? null)) $cpv[] = $c;
        foreach ((array)($pr['additionalCpvCodes'] ?? []) as $ac) {
            if ($c = simap_cpv_code($ac)) $cpv[] = $c;
        }
        foreach ((array)($detail['lots'] ?? []) as $lot) {
            if (is_array($lot) && ($c = simap_cpv_code($lot['procurement']['cpvCode'] ?? null))) $cpv[] = $c;
        }
    }
    $cpv = array_values(array_unique($cpv));

    $orgs = [];
    if ($office !== null) $orgs[] = array_filter(['name' => $office, 'country' => 'CH']);

    $pubDate = ted_truncate((string)($p['publicationDate'] ?? ''), 10) ?: null;
    $langUrl = in_array($lang, ['de', 'fr', 'it', 'en'], true) ? $lang : 'de';

    return [
        'id'                 => 'SIMAP-' . $pid,
        'source'             => 'SIMAP',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => $desc !== null ? ted_truncate($desc, KONKURSI_DESC_MAX) : null,
        'buyer_name'         => ted_truncate($office ?? 'Nezināms pasūtītājs', 300),
        'buyer_id'           => null,
        'buyer_country'      => 'CH',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => $pubDate,
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => ted_truncate((string)($p['publicationNumber'] ?? $p['projectNumber'] ?? ''), 40) ?: null,
        'budget'             => null,
        'currency'           => 'CHF',
        'document_url'       => sprintf(SIMAP_VIEW_FMT, $langUrl, rawurlencode($pid)),
        'buyer_profile_url'  => null,
        'procedure_type'     => $proc,
        'notice_sub_type'    => ted_truncate((string)($p['pubType'] ?? ''), 40) ?: null,
        'notice_lang'        => strtoupper($lang),
        'issue_date'         => $pubDate,
        'main_nuts'          => $canton !== '' ? ted_truncate('CH-' . $canton, 40) : null,
        'main_country'       => 'CH',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $pid,
        'main_cpv'           => $cpv[0] ?? null,
        'cpv_codes'          => json_encode($cpv, JSON_UNESCAPED_UNICODE),
        'lots'               => '[]',
        'organizations'      => json_encode($orgs, JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'simap-rest',
    ];
}

// ─────────────────────────── vergaben.llv.li (Lihtenšteina) ───────────────────

/** Lihtenšteinas ISO datums ("2026-08-12T17:00:00") → ['Y-m-d','H:i'] vai [null,null].
 *  "0001-01-01" (neaizpildīts) → [null,null]. */
function liverg_dt(?string $iso): array {
    if (!is_string($iso) || $iso === '' || str_starts_with($iso, '0001')) return [null, null];
    try {
        $d = new DateTimeImmutable($iso);
        return [$d->format('Y-m-d'), $d->format('H:i')];
    } catch (Throwable $e) {
        return [null, null];
    }
}

/**
 * Viens vergaben.llv.li Find ieraksts → notices rinda vai null.
 *
 * Importē TIKAI USB (tresholdTypeId=1, zem-sliekšņa) — tie uz TED NEnonāk. CPV te
 * publiski nav (būvniecībā izmanto BKP kodus), tāpēc nozares filtrs var nedarboties;
 * kartē nosaukumu, pasūtītāju, termiņu un līguma veidu. Kategoriju nosaka formName.
 */
function liverg_notice(array $x): ?array {
    if ((int)($x['tresholdTypeId'] ?? 0) !== 1) return null; // tikai USB (mazie)
    $id = (int)($x['id'] ?? 0);
    $title = trim((string)($x['name'] ?? $x['contractName'] ?? ''));
    if ($id <= 0 || $title === '') return null;

    $form = (string)($x['formName'] ?? '');
    if (str_contains($form, 'Bekanntgabe vergebener')) {
        $category = 'rezultati';
    } elseif (str_contains($form, 'Vorinformation')) {
        $category = 'citi';
    } else {
        $category = 'iepirkumi'; // Auftragsbekanntmachung, Wettbewerbsbekanntmachung
    }

    $nature = match ((int)($x['contractTypeId'] ?? 0)) {
        1       => 'works',      // Bauauftrag
        2       => 'supplies',   // Lieferauftrag
        3, 4    => 'services',   // Dienstleistung, Wettbewerb
        default => null,
    };

    [$dlDate, $dlTime] = liverg_dt($x['submitDeadline'] ?? null);
    $docNr = trim((string)($x['documentNumber'] ?? ''));
    $buyer = trim((string)($x['contAuthOfficialName'] ?? '')) ?: 'Nezināms pasūtītājs';
    $nuts = trim((string)($x['nutsCode'] ?? '')) ?: 'LI';

    return [
        'id'                 => 'LIVERG-' . $id,
        'source'             => 'LIVERG',
        'category'           => $category,
        'title'              => ted_truncate($title, 400),
        'description'        => ted_truncate(trim((string)($x['contractDescription'] ?? '')) ?: null, KONKURSI_DESC_MAX),
        'buyer_name'         => ted_truncate($buyer, 300),
        'buyer_id'           => null,
        'buyer_country'      => 'LI',
        'buyer_activity'     => null,
        'buyer_type'         => null,
        'procure_nature'     => $nature,
        'publication_date'   => null, // Find sarakstā nav ticama publicēšanas datuma
        'deadline_date'      => $category === 'iepirkumi' ? $dlDate : null,
        'deadline_time'      => $category === 'iepirkumi' ? $dlTime : null,
        'publication_number' => ted_truncate($docNr, 40) ?: null,
        'budget'             => null,
        'currency'           => 'CHF',
        'document_url'       => sprintf(LIVERG_VIEW_FMT, $id),
        'buyer_profile_url'  => null,
        'procedure_type'     => ted_truncate($form, 120) ?: null,
        'notice_sub_type'    => 'USB',
        'notice_lang'        => 'DE',
        'issue_date'         => null,
        'main_nuts'          => ted_truncate($nuts, 40),
        'main_country'       => 'LI',
        'funding_program'    => null,
        'prev_notice_ref'    => null,
        'contract_folder_id' => $docNr ?: (string)$id,
        'main_cpv'           => null,
        'cpv_codes'          => '[]',
        'lots'               => '[]',
        'organizations'      => json_encode([array_filter(['name' => $buyer, 'country' => 'LI'])], JSON_UNESCAPED_UNICODE),
        'notice_contact'     => '{}',
        'source_file'        => 'vergaben-llv-find',
    ];
}
