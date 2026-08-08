<?php
/**
 * konkursi/lib/config.php — Konkursu sadaļas konfigurācija.
 *
 * Datu avots: TED (Tenders Electronic Daily) — ES Oficiālā Vēstneša S sērijas
 * oficiālās dienas paketes https://ted.europa.eu/packages/daily/{GGGGNNNNN}.
 * Viena pakete = viena publikācijas diena (visas ES valstis vienā tar.gz failā),
 * tāpēc stabilā režīmā TED serverim tiek veikts tikai ~1 pieprasījums dienā.
 */
declare(strict_types=1);

// Vienota laika zona — bez šī sync.log rādīja laiku 3 h atpakaļ (PHP noklusējums UTC).
require_once __DIR__ . '/../../registrs/lib/timezone.php';

function konkursi_root(): string { return dirname(__DIR__); }          // .../konkursi
function konkursi_docroot(): string { return dirname(konkursi_root()); } // docroot (server/)

function konkursi_db_path(): string {
    $env = getenv('KONKURSI_DB_PATH');
    return ($env !== false && $env !== '') ? $env : konkursi_root() . '/db/tenders.db';
}
function konkursi_data_dir(): string  { return konkursi_root() . '/data'; }
function konkursi_tmp_dir(): string   { return konkursi_data_dir() . '/tmp'; }
function konkursi_lock_path(): string { return konkursi_data_dir() . '/sync.lock'; }
function konkursi_log_path(): string  { return konkursi_data_dir() . '/sync.log'; }
function konkursi_state_path(): string{ return konkursi_data_dir() . '/sync_state.json'; }
function konkursi_cron_flag(): string { return konkursi_data_dir() . '/cron_enabled.flag'; }
function konkursi_stop_flag(): string { return konkursi_data_dir() . '/stop.flag'; }

/**
 * Publiskojamā kontaktinformācija paziņojumam.
 *
 * Iepirkumu paziņojumos pasūtītāji bieži norāda KONKRĒTU DARBINIEKU — vārdu,
 * personisko darba e-pastu un mobilo. Oriģinālajā avotā tas ir publisks, bet
 * 49 000 tādu ierakstu apkopojums ar mailto saitēm pēc rakstura ir adresātu
 * saraksts, un mūsu mērķim — atrast konkursus — vārds un tālrunis nav vajadzīgs.
 * Kontaktpersonu vienmēr var redzēt oriģinālajā paziņojumā, uz kuru ved saite.
 *
 * Tāpēc: vārdu un tālruni izmet vienmēr; e-pastu patur TIKAI tad, ja tas ir
 * iestādes adrese (vergabestelle@, iepirkumi@, hankinta@ u.tml.), nevis
 * personiska (vards.uzvards@). Saraksts ir apzināti konservatīvs — ja šaubas,
 * e-pasts izkrīt.
 *
 * @param array<string,mixed> $c parsera savāktais kontakts
 * @return array<string,string> tukšs vai ['email' => ...]
 */
function ks_public_contact(array $c): array {
    $email = trim((string)($c['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return [];
    $local = strtolower(explode('@', $email)[0]);

    // Ja lokālajā daļā atpazīstams kontaktpersonas vārds → personiska adrese
    $name = (string)($c['name'] ?? '');
    if ($name !== '') {
        foreach (preg_split('/[\s,]+/u', mb_strtolower($name, 'UTF-8')) ?: [] as $tok) {
            $tok = preg_replace('/[^\p{L}]/u', '', $tok) ?? '';
            if (mb_strlen($tok) >= 4 && str_contains($local, $tok)) return [];
        }
    }

    // Iestādes pazīmes vairākās valodās (LV/DE/EN/FI/EE/LT/PL/CZ/SK/SE/NO/DK/
    // IS/FR/ES/IT/GR/SI/HR/HU/RO/BG/NL/PT)
    static $generic = [
        'info', 'kontakt', 'contact', 'office', 'mail', 'post', 'sekretariat',
        'admin', 'webmaster', 'reception', 'kanceleja', 'pasts',
        'iepirkum', 'vergabe', 'einkauf', 'beschaffung', 'ausschreibung',
        'procurement', 'tender', 'bids', 'purchasing', 'hankinta', 'kirjaamo',
        'riigihanked', 'hanked', 'pirkimai', 'viesieji', 'zamowieni', 'przetarg',
        'zakazky', 'verejne', 'upphandling', 'inkop', 'innkjop', 'anskaffelser',
        'udbud', 'indkob', 'utbod', 'innkaup', 'achats', 'marches', 'contratacion',
        'compras', 'appalti', 'gare', 'promitheies', 'narocila', 'nabava',
        'kozbeszerzes', 'achizitii', 'porachki', 'aanbesteding', 'inkoop',
        'firmapost', 'kommune', 'gemeinde', 'amt', 'stadt', 'bauamt',
    ];
    foreach ($generic as $g) {
        if (str_contains($local, $g)) return ['email' => $email];
    }
    return [];  // šaubu gadījumā — neko
}

/**
 * Organizācijas ieraksts bez personas datiem — tā pati politika kā
 * ks_public_contact, piemērota `organizations` kolonnai: kontaktpersonas
 * vārdu un tālruni izmet vienmēr, e-pastu patur tikai iestādes adresēm.
 * Bez šī organizāciju sarakstā nonāca personvārdi un personiskie darba
 * e-pasti/tālruņi (atrasts 2026-07-21 auditā: 41 781 ieraksts ar 'contact').
 * @param array<string,mixed> $o parsera savāktā organizācija
 * @return array<string,mixed>
 */
function ks_public_org(array $o): array {
    $email = ks_public_contact([
        'email' => $o['email'] ?? null,
        'name'  => $o['contact'] ?? null,
    ])['email'] ?? null;
    unset($o['contact'], $o['phone'], $o['email']);
    if ($email !== null) $o['email'] = $email;
    return $o;
}

/** Šodienas datums pēc Latvijas laika (YYYY-MM-DD). */
function konkursi_today(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Riga')))->format('Y-m-d');
}

/**
 * Displeja loga SQL nosacījumi [aktīvie, rezultāti] — VIENĪGĀ vieta, kur tie
 * definēti: tos lieto gan konkursi.php API (list/countries/cpv/buyers/sources),
 * gan lib/snapshot.php starta momentuzņēmums. Ja abas puses tos rēķinātu katra
 * savādāk, momentuzņēmuma skaitļi nesakristu ar īstajiem un lapa "lēkātu".
 * $t — tabulas alias prefikss ('n.' vai '').
 * Datumi ir servera date() izvade → droši inlainot SQL virknē.
 */
function konkursi_display_conds(bool $archive, string $t = 'n.'): array {
    $today = konkursi_today();
    $tz = new DateTimeZone('Europe/Riga');
    $cutNoDl   = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_NODEADLINE_DAYS . ' days')->format('Y-m-d');
    $cutResult = (new DateTimeImmutable($today, $tz))->modify('-' . KONKURSI_KEEP_RESULTS_DAYS . ' days')->format('Y-m-d');
    // Aktīvie: termiņš vēl nav pagājis; bezdatuma avotiem (Comdia) — pēc pirmās
    // redzēšanas recency, lai vecie mūžīgi nekrājas skatā.
    $active = $archive
        ? "({$t}deadline_date IS NULL OR {$t}deadline_date >= '$today')"
        : "({$t}deadline_date >= '$today' OR ({$t}deadline_date IS NULL AND COALESCE({$t}publication_date, {$t}first_seen) >= '$cutNoDl'))";
    // Rezultāti/grozījumi/citi: noklusējumā pēdējie KONKURSI_KEEP_RESULTS_DAYS.
    $result = $archive
        ? '1=1'
        : "({$t}publication_date IS NULL OR {$t}publication_date >= '$cutResult')";
    return [$active, $result];
}

// ── Dziļā (vienreizējā) vēstures aizpilde ─────────────────────────────────────
// Konkurss dzīvo līdz ~60 dienām, tāpēc "aktīvo" aina ir pilnīga tikai tad, ja
// avots ir ielādēts ~60 dienas atpakaļ. Ikdienas sinhronizācija strādā ar maziem
// logiem/limitiem (saudzīgi pret avotiem); dziļo aizpildi palaiž VIENREIZ manuāli:
//   KONKURSI_DEEP=60 php konkursi/bin/sync.php --only=hilma
// Dziļajā režīmā logi = N dienas un limiti = *_DEEP vērtības; vecos
// rezultātus/grozījumus (publicēti >14 d atpakaļ) neimportē — arhīvs pildās
// dabiski uz priekšu, un DB nepiebriest (skat. ks_backfill_keep).

/** Cik dienu dziļa aizpilde pieprasīta (0 = parastais režīms). */
function konkursi_deep_days(): int {
    $v = getenv('KONKURSI_DEEP');
    return $v === false ? 0 : max(0, min(90, (int)$v));
}
function konkursi_deep(): bool { return konkursi_deep_days() > 0; }

/** Limits pēc režīma: parastais vai dziļās aizpildes. */
function ks_cap(int $normal, int $deep): int {
    return konkursi_deep() ? $deep : $normal;
}

/** Loga sākums: dziļajā režīmā -N dienas, citādi $fallback (kursors/mazais logs). */
function ks_window_start(string $fallback): string {
    if (!konkursi_deep()) return $fallback;
    return (new DateTimeImmutable(konkursi_today(), new DateTimeZone('Europe/Riga')))
        ->modify('-' . konkursi_deep_days() . ' days')->format('Y-m-d');
}

const KONKURSI_ACTIVE_WINDOW_DAYS = 60; // aktīvā konkursa maksimālais mūžs (sākotnējais aizpildes logs)
const KONKURSI_BACKFILL_FRESH_DAYS = 14; // dziļajā režīmā rezultātus/grozījumus ņem tikai tik svaigus

// ── Virsrakstu LV tulkošana (Gemini caur registrs/mi/gemini_client.php) ───────
// Slēdzis glabājas meta tabulā ('translate_on_sync', admin panelī); šeit — limiti.
const KONKURSI_TRANSLATE_BATCH   = 40;   // virsraksti vienā API pieprasījumā
// Paralēlie API izsaukumi vienā vilnī (curl_multi): sienas laiks ≈ lēnākais no
// viļņa, ne summa. Maksas flash līmeņa RPM limiti 4 vienlaicīgus atļauj ar uzviju.
const KONKURSI_TRANSLATE_PARALLEL = 4;
const KONKURSI_TRANSLATE_MAX_RUN = 15000; // max virsraksti vienā sinhronizācijā — nosedz organisko
                                          // darbadienu (~5–8k) ar rezervi; free tier: 375 piepr./d
const KONKURSI_TRANSLATE_DELAY_MS = 500; // pauze starp API pieprasījumiem

// ── AM tirgus izpētes (mod.gov.lv) — zemsliekšņa/pirmsiepirkuma izpētes ──────
const MODTI_LIST_URL  = 'https://www.mod.gov.lv/lv/tirgus-izpetes?page=%d';
const MODTI_BASE_URL  = 'https://www.mod.gov.lv';
const MODTI_MAX_PAGES = 5;    // saraksta lapu griesti (šobrīd ~3 lapas / 22 ieraksti)
const MODTI_MAX_DETAILS = 40; // detaļu pieprasījumu griesti vienā palaišanā
const MODTI_DELAY_MS  = 800;  // pauze pirms katras detaļu lapas

// ── Rīgas satiksmes tirgus izpētes (rigassatiksme.lv) — zemsliekšņa izpētes ──
// Viena servera-HTML saraksta lapa (visas aktīvās izpētes vienuviet, ~50 rindas);
// detaļu lapu (apraksts) ver TIKAI jauniem id — tāds pats raksts kā MODTI.
const RSTI_LIST_URL    = 'https://www.rigassatiksme.lv/lv/par-mums/iepirkumi/tirgus-izpetes/';
const RSTI_MAX_DETAILS = 40;  // detaļu pieprasījumu griesti vienā palaišanā
const RSTI_DELAY_MS    = 800; // pauze pirms katras detaļu lapas

// Sadales tīkls (sadalestikls.lv) NAV ievākts: Latvenergo grupas WAF atdod HTTP 451
// mūsu identificētajam bota UA (200 tikai pārlūka UA). Bota filtrēšanas apiešana ar
// UA viltošanu ir pretrunā projekta principiem — avots izlaists (sk. lēmumu atmiņā).

// ── Austrumu slimnīcas tirgus izpētes (aslimnica.lv) — zemsliekšņa izpētes ───
// WordPress saraksts; katrā rindā date-published/name/date-submitted. Detaļu lapā
// ir kontakti + saturs dziļi tēmā → aprakstu NEņemam (tikai virsraksts+datumi+links,
// GDPR-drošs). Reti (~5-10/gadā), tāpēc 1 saraksta lapa/palaišanā.
const ASTI_LIST_URL = 'https://aslimnica.lv/iepirkumi/tirgus-izpetes/';

// ── LDz (Latvijas dzelzceļš) tirgus izpētes/apspriedes (ldz.lv) ──────────────
// Viens saraksts (Drupal tabula: uzņēmums / virsraksts / ISO iesniegšanas termiņš).
// Ņemam TIKAI tirgus izpētes, tirgus cenu izpētes un apspriedes — formālie iepirkumi
// (Atklāts konkurss, Iepirkums ar publikāciju) jau ir mūsu IUB/TED plūsmā (dublikāti).
// Detaļu lapas neveram; robots.txt Crawl-delay: 10 — mēs tik un tā 1 pieprasījums.
const LDZ_LIST_URL = 'https://ldz.lv/lv/iepirkumi';
const LDZ_BASE_URL = 'https://ldz.lv';

// Tulkošana iet ar MAKSAS atslēgu, nepārsniedzot šos dienas griestus (uzskaite
// meta 'translate_paid_spend_YYYY-MM-DD'). Bezmaksas atslēgas mēģinājums izņemts
// 2026-08-04: tās kvota (~20 piepr./d ≈ 800 virsraksti ≈ €0.10) nosedza <10% no
// apjoma, bet 503/taimautu ceļš maksāja ~14 min katrā palaišanā.
const KONKURSI_TRANSLATE_PAID_DAILY_EUR = 3.0;
const KONKURSI_GEMINI_IN_USD_1M  = 0.50; // gemini-3-flash-preview ievades cena $/1M tokenu
const KONKURSI_GEMINI_OUT_USD_1M = 3.00; // izvades (+ domāšanas) cena $/1M tokenu
const KONKURSI_USD_TO_EUR = 0.95;        // apzināti konservatīvs kurss budžetam (reālais ~0.92)

// Inkrementālās ievākšanas pārklājums: kolektors ievāc no (ūdenszīme − šis) līdz
// šodienai, dedup pēc id. Neliels pārklājums noķer novēloti/atpakaļejoši publicētos
// paziņojumus, joprojām izvairoties no jau izpētītu dienu smagas atkārtotas skenēšanas.
const KONKURSI_OVERLAP_DAYS = 3;

// ── TED lejupielādes politika (saudzīga pret TED un savu serveri) ─────────────
const TED_PACKAGE_URL_FMT = 'https://ted.europa.eu/packages/daily/%s'; // %s = piem. 202600123
const TED_USER_AGENT      = 'Mozilla/5.0 (compatible; SarakstsKonkursi/1.0; +https://saraksts.lv/konkursi.php)';
const TED_MAX_PACKAGES_PER_RUN = 3;   // ne vairāk kā 3 dienas paketes vienā palaišanā
const TED_INITIAL_BACKFILL     = 3;   // pirmajā palaišanā paņem tikai pēdējās N paketes
const TED_REQUEST_DELAY_S      = 3;   // pauze starp HTTP pieprasījumiem
const TED_HTTP_TIMEOUT_S       = 300; // pilnas paketes lejupielādei (~20 MB)
const TED_PROBE_TIMEOUT_S      = 20;  // HEAD eksistences pārbaudei
const TED_PROBE_MISS_STOP      = 3;   // pēc N secīgiem 404 pieņem, ka jaunāku pakešu vēl nav

// ── IUB (open.iub.gov.lv) — Latvijas nacionālie iepirkumi ─────────────────────
// Dienas JSON faili; ņem TIKAI zem-sliekšņa/nacionālos paziņojumus (legalBasis
// bez '-over'), jo virs-sliekšņa paziņojumi tāpat nonāk TED plūsmā (bez dublikātiem).
const IUB_URL_FMT          = 'https://open.iub.gov.lv/data/notice/%s/%s/%s.json'; // gads, mēnesis, dd-mm-gggg
const IUB_BACKFILL_DAYS    = 60; // cik dienas atpakaļ pārbauda trūkstošos failus (aktīvā loga dziļums)
const IUB_REQUEST_DELAY_S  = 1;  // pauze starp IUB pieprasījumiem
const IUB_SKIP_404_AFTER_DAYS = 3; // 404 senākām dienām atzīmē kā tukšas (vairs nezondē)

// ── CVP IS (viesiejipirkimai.lt) — Lietuvas nacionālie iepirkumi ──────────────
// Saraksts nāk no portāla iebūvētā CSV eksporta ("Naujausi pirkimai" tabula,
// kārtota pēc publicēšanas datuma dilstoši); katram JAUNAM pirkumam nolasa
// publisko detaļu lapu (bez sesijas). Ņem TIKAI zem starptautiskā sliekšņa
// esošos ("Žemiau") — virs-sliekšņa pirkumi tāpat nonāk TED plūsmā.
// UZMANĪBU: CSV eksports IGNORĒ lapošanas parametru (katra "lapa" = tās pašas
// jaunākās rindas) — lapot nevar; toties T01_ps var palielināt un eksportēt
// VISU sarakstu vienā piegājienā (dziļā aizpilde to dara ar ps=3000).
// ── CVP IS integrācijas API (oficiālā REST saskarne) ─────────────────────────
// Aizstāj CSV+HTML skrāpēšanu: dod pilnu dzīves ciklu (arī rezultātus un
// atceltos), aboveThreshold karogu TED dedupam un ~1 pieprasījumu uz 500 ierakstiem.
// Ieraksti sakārtoti AUGOŠI pēc publikācijas datuma, tāpēc jaunākie ir beigu lapās.
// UZMANĪBU: šī ir VPT publiski dokumentētā TESTA atslēga. Tā darbojas, bet to var
// atsaukt bez brīdinājuma — produkcijai jāpieprasa sava atslēga (pagalba@vpt.lt).
const CVPIS_API_URL      = 'https://viesiejipirkimai.lt/epps-integration/api/cft-details-export';
const CVPIS_API_KEY      = 'acec29bd-687c-4609-b211-c01b6cf51b55';
const CVPIS_API_PAGE     = 500; // ierakstu vienā lapā
const CVPIS_API_MAX_PAGES = 40; // cik lapu (no beigām atpakaļ) drīkst vienā palaišanā
const CVPIS_API_DELAY_S  = 1;   // pauze starp lapām

const CVPIS_LIST_URL_FMT  = 'https://viesiejipirkimai.lt/epps/quickSearchAction.do?searchType=cftFTS&latest=true&T01_ps=100&d-3680175-p=%d&d-3680175-s=datePublished&d-3680175-o=1&d-3680175-e=1&6578706f7274=1';
// CVP IS paziņojuma lapai ir TRĪS shēmas atkarībā no pirkuma veida — nepareizā
// shēma neatgriež kļūdu, bet aizved uz pieteikšanās lapu, tāpēc izvēle ir svarīga.
const CVPIS_DETAIL_URL_FMT = 'https://viesiejipirkimai.lt/epps/cft/prepareViewCfTWS.do?resourceId=%s';
const CVPIS_PMC_URL_FMT    = 'https://viesiejipirkimai.lt/epps/pmc/viewPmc.do?resourceId=%s';        // tirgus konsultācijas
const CVPIS_DPS_URL_FMT    = 'https://viesiejipirkimai.lt/epps/dps/prepareViewCfTDPSWS.do?resourceId=%s'; // dinamiskās sistēmas
const CVPIS_LIST_PAGES_MAX     = 1;   // CSV eksportam ir tikai 1 reāla lapa (sk. augstāk)
const CVPIS_MAX_DETAILS_PER_RUN = 120; // jaunu detaļu lapu limits vienā palaišanā
const CVPIS_REQUEST_DELAY_S    = 1;   // pauze starp detaļu pieprasījumiem

// ── RHR (riigihanked.riik.ee) — Igaunijas reģistra atvērtie dati ──────────────
// Mēneša eForms UBL XML dumps (tas pats formāts kā TED!) — 1 pieprasījums dienā.
// Dedup: EE virs-sliekšņa paziņojumiem UUID sakrīt ar TED → ja ieraksts jau ir
// no TED, izlaiž; ja TED nāk vēlāk, tas pārraksta (viena rinda, bez dubultiem).
// Divas plūsmas: izsludinājumi (notice) un procedūru rezultāti (notice_award).
// Bez notice_award Igaunijai vispār nebūtu sadaļas "Rezultāti" — zem ES sliekšņa
// piešķīrumi uz TED neaiziet, tie ir tikai šeit. Mēnesis BEZ vadošās nulles.
const RHR_MONTH_URL_FMT       = 'https://riigihanked.riik.ee/rhr/api/public/v1/opendata/notice/%d/month/%d/xml';
const RHR_AWARD_MONTH_URL_FMT = 'https://riigihanked.riik.ee/rhr/api/public/v1/opendata/notice_award/%d/month/%d/xml';
// Reģistra iepirkuma lapa (rezultātiem — pārmantota no izsludinājuma rindas)
const RHR_PROCUREMENT_URL_FMT = 'https://riigihanked.riik.ee/rhr-web/#/procurement/%s/general-info';

// ── Hilma (hankintailmoitukset.fi) — Somijas atvērto datu API ─────────────────
// Azure Search POST; ņem TIKAI isNationalProcurement=true (ES līmeņa ir TED).
// Atslēga ir bezmaksas (developer portāls, produkts avp-read); šī nāk no ted parauga.
const HILMA_SEARCH_URL = 'https://api.hankintailmoitukset.fi/avp/eformnotices/docs/search';
const HILMA_API_KEY    = '5086b1b87bca4739a2d9e0167d84d542';
const HILMA_BACKFILL_DAYS = 14;  // pirmajā palaišanā
const HILMA_MAX_PAGES     = 5;   // pa 100 vienā palaišanā
// Ilgtermiņa aste: ietvarvienošanās/DPS ar gariem termiņiem, kas publicēti pirms
// publikācijas loga, bet joprojām ir atvērti. Publikācijas kursors tos nekad
// nesasniedz; ks_prune tos patur, jo 'iepirkumi' dzīvo pēc deadline_date.
const HILMA_TAIL_MAX_PAGES = 3;

// ── Doffin (doffin.no) — Norvēģijas paziņojumu datubāze ───────────────────────
// Publiskais webclient meklēšanas API (JSON POST).
// Dedup: sentToTed=true → izlaiž (nāk caur TED, Norvēģija ir EEZ).
//
// Filtrus API pieņem TIKAI zem 'facets.<lauks>.checkedItems' (noskaidrots no
// doffin.no frontend bundle); plakans {"status":["ACTIVE"]} tiek klusi ignorēts.
// Kārtošanas noklusējums ir RELEVANCE — bez skaidra sortBy lapošana nav
// hronoloģiska. Arhīvā ir ~157k paziņojumu, no tiem ACTIVE ~1550, un 75% no
// tiem ir sentToTed, tāpēc bez statusa filtra lapas aizpildās ar izmetamo.
const DOFFIN_SEARCH_URL = 'https://api.doffin.no/webclient/api/v2/search-api/search';
const DOFFIN_MAX_PAGES  = 3;    // svaigā lente (rezultāti/atcelšanas) — pa 100
const DOFFIN_ACTIVE_MAX_PAGES = 10; // aktīvo šķēle — API lapošanas griesti ir 1000
const DOFFIN_NOTICE_URL_FMT = 'https://www.doffin.no/notices/%s';

// ── udbud.dk — Dānijas iepirkumu portāls ──────────────────────────────────────
// Publiskais meklēšanas API (JSON POST). Dedup: filtrs formularType=NATIONALE_UDBUD
// (ES formu paziņojumi tāpat nāk caur TED).
const UDBUD_SEARCH_URL = 'https://udbud.dk/soegning/public/soegeresultat';
const UDBUD_MAX_PAGES  = 10;    // pa 100; nacionālā plūsma kopā ~870 ierakstu
const UDBUD_NOTICE_URL_FMT = 'https://udbud.dk/detaljevisning?noticeId=%s&noticeVersion=%s';

// ── Comdia (comdia.com) — Dānijas pašvaldību iepirkumu platforma ──────────────
// 52 no Dānijas 98 pašvaldībām + komunālie uzņēmumi; ~660 atvērtu konkursu, no
// kuriem ~90% NAV udbud.dk, jo zem-sliekšņa iepirkumiem bez pārrobežu intereses
// publicēšanas pienākuma nav (KFST: obligāti tikai no 100 000 DKK ar skaidru
// pārrobežu interesi). Bez šī avota Dānijai sadaļā ir tikai ~59 konkursi.
//
// UZMANĪBU — datu kvalitāte: Comdia anonīmam apmeklētājam NERĀDA nevienu datumu.
// Nav ne termiņa, ne publicēšanas datuma, ne CPV, ne vērtības. Termiņu izsecināt
// NEDRĪKST: Udbudsloven zem sliekšņa fiksētu termiņu nenosaka ("passende frist"),
// reālie svārstās no nedēļām līdz mēnešiem, un nepareizs datums lietotājam ir
// sliktāks par tukšu lauku. Vienīgā ticamā pazīme ir binārs atvērts/slēgts, ko
// detaļu lapa pasaka ar ziņu "deadline for participation ... exceeded".
// Tāpēc šie ieraksti ir apzināti minimāli: nosaukums, pasūtītājs un SAITE.
//
// Tiesiskais pamats: comdia.com/robots.txt liedz tikai /keepAlive.aspx, un
// noteikumi saka "Processio holds no copyright to the information ... shared
// between Users via Comdia" un jau paredz trešo pušu lejupielādi.
const COMDIA_BASE_URL   = 'https://www.comdia.com';
const COMDIA_LIST_FMT   = 'https://www.comdia.com/%s/aktuelleudbud.aspx';
const COMDIA_DETAIL_FMT = 'https://www.comdia.com/%s/tenderinformationshow.aspx?Id=%s&List=0';
// Organizāciju sarakstu nolasa dinamiski no jebkuras lapas izvēlnes; šī ir lapa,
// no kuras to ņem (jebkura derētu).
const COMDIA_SEED_PATH  = '/aalborg-kommune/aktuelleudbud.aspx';
const COMDIA_DELAY_MS   = 250;  // pauze starp pieprasījumiem
// Detaļu pārbaudes limits vienā palaišanā: saraksta apļi ir lēti (~52), bet
// katra konkursa atvērts/slēgts pārbaude prasa savu pieprasījumu (~690).
const COMDIA_MAX_DETAILS = 250;
const COMDIA_RECHECK_HOURS = 20; // cik bieži pārbaudīt jau zināma konkursa statusu

// ── KommersAnnons (kommersannons.se) — Zviedrijas reģistrēta sludinājumu DB ───
// Zviedrijā kopš 2021. g. zem-sliekšņa iepirkumi jāizsludina REĢISTRĒTĀ
// sludinājumu datubāzē; Konkurrensverket uztur reģistru, un tajā ir tikai piecas:
// e-Avrop, KommersAnnons, Mercell, Konstpool, Clira. Tāpēc 290 pašvaldību lapas
// nav jāskrāpē — pietiek ar šīm.
//
// Ņemam TIKAI KommersAnnons: tās robots.txt ir 'allow: /', noteikumu lapas nav,
// un likums prasa bezmaksas publisku meklēšanu. e-Avrop (lielākā, ~1200 konkursu)
// APZINĀTI izlaista — tās robots.txt ir 'Disallow: /', atļauta tikai /Places.aspx.
//
// Divi soļi: saraksts (POST lapošana ar __RequestVerificationToken) dod id +
// nosaukumu + publicēšanas datumu; detaļu lapa dod pasūtītāju, absolūto termiņu,
// CPV kodus, NUTS un aplēsto vērtību. Detaļu ņem VIENREIZ uz paziņojumu — te ir
// īsts termiņš, tāpēc beigšanos apstrādā ks_prune, nevis atkārtota aptauja.
const KOMMERS_LIST_URL   = 'https://www.kommersannons.se/Notices/TenderNotices';
const KOMMERS_DETAIL_FMT = 'https://www.kommersannons.se/Notices/%s/%s';
const KOMMERS_MAX_PAGES  = 25;   // pa 40; kopums ~620
const KOMMERS_MAX_DETAILS = 300; // jaunu detaļu limits vienā palaišanā
const KOMMERS_DELAY_MS   = 300;

// ── Útboðsvefur (utbodsvefur.is) — Islandes vienotais sludinājumu dēlis ───────
// Reglugerð nr. 260/2020: visiem Islandes publiskajiem iepirkumiem virs
// nacionālā sliekšņa OBLIGĀTI jābūt šeit, arī tiem, ko iestādes tehniski vada
// TendSign vidē. Tāpēc viens avots dod pilnu nacionālo pārklājumu.
//
// Tiesiskais pamats un robots.txt (lēmums 2026-07-21): utbodsvefur.is/robots.txt
// ir 'Disallow: /' — atšķirībā no citiem avotiem te NAV mašīnlasāmas
// atļaujas. Apzināti paturam, jo: (1) robots.txt ir rāpuļu konvencija, ne licence
// vai likums; (2) šie ir publiski iepirkumu paziņojumi, kuru publiskošana pati ir
// likumā noteikts pienākums (Reglugerð nr. 260/2020); (3) neapejam autentifikāciju,
// ievērojam ks_http_throttle pauzes un neradām slodzi; (4) datus neizmantojam
// pieteikšanās aizstāšanai — to administrē TendSign. Islandei nav atvērto datu API
// (paraugkoda rikiskaup.is/api ir miris, sk. zemāk), tāpēc šis ir vienīgais
// nacionālais avots; virs-sliekšņa konkursi tāpat nonāk TED. Avota piezīmē
// (konkursi.js SOURCE_NOTE.ISUTB) par to ir godīga atruna. Alternatīva būtu prasīt
// Fjársýslan/Ríkiskaup rakstisku atļauju.
//
// Vietne ir WordPress; sākumlapa satur TIKAI aktīvos (~80), bet WP API
// (/wp-json/wp/v2/posts) rāda visus 6280 vēsturiskos — tāpēc aktīvo kopu ņem no
// sākumlapas, nevis no API.
//
// UZMANĪBU: parauga kods (GEMINI/ted plugins/is) lieto rikiskaup.is/api/notices.
// Tas ir MIRIS — Ríkiskaup likvidēts, funkcijas nodotas Fjársýslan, un galapunkts
// atgriež 301 uz island.is. Neizmantot.
const ISUTB_BASE_URL   = 'https://utbodsvefur.is';
const ISUTB_MAX_ITEMS  = 200;  // drošības griesti (reāli ~80)
const ISUTB_DELAY_MS   = 300;
const ISUTB_RECHECK_HOURS = 18; // detaļu-lapu re-fetch drosele (jau redzētos nepārlādē biežāk)
// Sākumlapa VIEN nav pilna: konkursi ar saliktu tipu (piem. "Vörukaup,
// Markaðskönnun (RFI)") tajā neparādās, kaut termiņš vēl nav pagājis. Tāpēc
// papildus apstaigā katru tipa filtru — tas atrod tos, ko noklusējuma skats izlaiž.
const ISUTB_TYPES = ['Framkvæmd', 'Vörukaup', 'Þjónusta', 'Leiguhúsnæði',
                     'Forauglýsing', 'Markaðskönnun (RFI)',
                     'Gagnvirkt innkaupakerfi (DPS)', 'Verðfyrirspurn'];

// ── BZP / e-Zamówienia (ezamowienia.gov.pl) — Polijas nacionālie iepirkumi ────
// Oficiālais UZP publiskais API (JSON, lapots). BZP publicē tikai zem ES
// sliekšņa esošos (virs-sliekšņa iet uz TED); papildus filtrs isTenderAmountBelowEU.
// PIEZĪME: API lapošanas parametru nav (PageNumber tiek ignorēts!) — rezultāti
// kārtoti augoši pēc publicationDate, tāpēc "lapo" ar datuma kursoru: nākamais
// pieprasījums sākas no iepriekšējās lapas pēdējā ieraksta laika.
const BZP_API_URL_FMT = 'https://ezamowienia.gov.pl/mo-board/api/v1/notice?NoticeType=%s&PublicationDateFrom=%s&PublicationDateTo=%s&PageSize=100';
const BZP_NOTICE_URL_FMT = 'https://ezamowienia.gov.pl/mo-client-board/bzp/notice-details/%s';
const BZP_BACKFILL_DAYS = 2;  // pirmajā palaišanā (BZP ir liels apjoms, ~400+ dienā)
const BZP_MAX_PAGES     = 40; // pa 100 vienam tipam; Polija publicē ~1100-1500
                              // paziņojumu DIENĀ, tāpēc ar 12 lapām (1200) palaišana
                              // tik tikko noturēja šodienu un vēsturi nekad nepanāca.

// ── BKMS / Datenservice Öffentlicher Einkauf (oeffentlichevergabe.de) — Vācija ─
// Oficiālais dienas eksports: ZIP ar eForms UBL XML (tas pats formāts kā TED!).
// Dienā ~600 nacionālie (RegulatoryDomain de-*) + ~750 ES līmeņa (nāk caur TED,
// UUID sakrīt → dabisks dedup kā Igaunijai).
const BKMS_EXPORT_URL_FMT = 'https://oeffentlichevergabe.de/api/notice-exports?pubDay=%s&format=eforms.zip';
const BKMS_LOOKBACK_DAYS  = 3; // cik dienas atpakaļ pārbauda neimportētus dienas failus

// ── BOAMP (boamp.fr) — Francijas oficiālais biļetens (DILA, atvērtie dati) ────
// OpenDataSoft API; dedup: famille='JOUE' (ES Oficiālais Vēstnesis → TED) izslēdz,
// paliek FNS/MAPA/DSP/DIVERS (nacionālie).
const BOAMP_API_URL = 'https://www.boamp.fr/api/explore/v2.1/catalog/datasets/boamp/records';
const BOAMP_BACKFILL_DAYS = 2;
// Pa 100; ODS offset limits ir 10000. Francijā ~150 nacionālie dienā (maks. 298),
// tātad 2 dienu logs ≈ 6 lapas — bet, ja kāda sinhronizācija izlaista, logs aug.
// 20 lapas = 2000 ieraksti ≈ 13 dienu rezerve.
const BOAMP_MAX_PAGES     = 20;

// ── eTenders (etenders.gov.ie) — Īrija (tā pati e-PPS platforma kā LT CVP IS) ─
// T01_ps=300: saraksts kārtots pēc publicēšanas datuma dilstoši, un dienas
// sinhronizācija redz tikai pirmo lapu — 100 rindas nosegtu tikai ~2 dienas.
const ETENDERS_LIST_URL_FMT  = 'https://www.etenders.gov.ie/epps/quickSearchAction.do?searchType=cftFTS&latest=true&T01_ps=300&d-3680175-p=%d&d-3680175-s=datePublished&d-3680175-o=1&d-3680175-e=1&6578706f7274=1';
const ETENDERS_DETAIL_URL_FMT = 'https://www.etenders.gov.ie/epps/cft/prepareViewCfTWS.do?resourceId=%s';
const ETENDERS_LIST_PAGES_MAX      = 1; // CSV eksports nelapo — tā pati piezīme kā CVPIS
const ETENDERS_MAX_DETAILS_PER_RUN = 120;
const ETENDERS_REQUEST_DELAY_S     = 1;
// Paziņojumu reģistrs (noticeFTS) — vienīgā vieta, kur redzami NACIONĀLIE
// piešķīrumi '(no TED publication)'. UZMANĪBU: CSV eksports un kārtošanas
// parametri (d-...-s/o/e) lapošanu KLUSI restartē uz 1. lapu — lapot drīkst
// tikai HTML skatu vienā cepumu sesijā ar minimālo saiti (pārbaudīts 2026-07-19).
// %d = lapa, %d = lapas izmērs (T01_ps).
const ETENDERS_NOTICES_URL_FMT = 'https://www.etenders.gov.ie/epps/quickSearchAction.do?d-7094782-p=%d&searchType=noticeFTS&latest=true&T01_ps=%d';
const ETENDERS_NOTICES_PAGES_DEEP = 45; // pa 500; ~14 dienas lapā → 6 mēneši ≈ 36

// Rezultātu glabāšanas izņēmumi pa avotiem (dienas). Īrijai 6 mēneši:
// nacionālie piešķīrumi citur nav pieejami, un apjoms (~100/d) to atļauj.
const KONKURSI_KEEP_RESULTS_BY_SOURCE = ['ETENDERS' => 180];

// ── TenderNed (tenderned.nl) — Nīderlandes oficiālais publiskais API ──────────
// nationaalOfEuropees=NL filtrē servera pusē (ES līmeņa nāk caur TED) —
// bez tā ~90% lapas satura būtu izmetami un 5 lapas nosegtu tikai ~1 dienu.
// Dedup: europees=true (ES līmenis → TED) izlaiž, paliek nacionālie.
const TENDERNED_API_URL_FMT = 'https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties?page=%d&size=100&nationaalOfEuropees=NL';
const TENDERNED_MAX_PAGES   = 5;
// Vēl atvērtie vecie nacionālie (DAS, groslijsten, open-house no 2018.-2025. g.,
// termiņi līdz pat 2030) — ikdienas saraksts pēc publicēšanas datuma tos nekad
// nesasniedz; dziļajā aizpildē ņem atsevišķi. %d=lapa, %s=rītdienas datums.
const TENDERNED_OPEN_URL_FMT = 'https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties?page=%d&size=100&nationaalOfEuropees=NL&sluitingsDatumVanaf=%s';

// ── PLACSP (contrataciondelsectorpublico.gob.es) — Spānijas oficiālā platforma ─
// Ritošā ATOM sindikācijas plūsma (CODICE XML; adaptēts no ted parauga strādājošā
// es_nat risinājuma). Dedup pret TED: virs ES sliekšņa vērtības izlaiž.
const PLACSP_FEED_URL = 'https://contrataciondelsectorpublico.gob.es/sindicacion/sindicacion_643/licitacionesPerfilesContratanteCompleto3.atom';
// 17 autonomo apgabalu platformu AGREGĀTS (Katalonija, Basku zeme, Galīsija,
// Madride...) — tur mītošie pasūtītāji galvenajā plūsmā NEparādās nekad.
// Tas pats CODICE ATOM formāts, saites ved uz oriģinālo reģiona platformu.
const PLACSP_AGG_FEED_URL = 'https://contrataciondelsectorpublico.gob.es/sindicacion/sindicacion_1044/PlataformasAgregadasSinMenores.atom';
const PLACSP_MAX_PAGES = 5;        // ritošās plūsmas lapas (katrā ~260 ieraksti; agregātā ~300/dienā)
// EUR — virs šī pakalpojumi/piegādes iet TED. 221k = PAŠVALDĪBU slieksnis:
// lielākā daļa PLACSP pasūtītāju ir zem-centrālie, un to 143–221k konkursi TED
// nenonāk nekad — ar 143k tos pazaudētu pavisam. Centrālās valdības 143–221k
// dubultos pret TED noņem nosaukums+pircējs dedup solis sinhronizācijā.
const PLACSP_EU_THRESHOLD_SERVICES = 221000.0;
const PLACSP_EU_THRESHOLD_WORKS    = 5538000.0; // EUR — virs šī būvdarbi iet TED

// ── VVZ NIPEZ (vvz.nipez.cz) — Čehijas oficiālais vēstnesis ───────────────────
// Publiskais JSON API (bez atslēgas). Dedup: uverejnitTed=false jau vaicājumā —
// ES līmeņa formulāri (uverejnitTed=true) nāk caur TED. Pilnie eForms dati ir
// bērna iesniegumā (children/search) kā BT-lauku JSON koks.
const VVZ_SEARCH_URL   = 'https://api.vvz.nipez.cz/api/submissions/search';
const VVZ_CHILDREN_URL = 'https://api.vvz.nipez.cz/api/submissions/children/search';
const VVZ_FORM_URL_FMT = 'https://vvz.nipez.cz/vyhledat-formular/%s';
const VVZ_MAX_PAGES            = 4;   // saraksta lapas pa 100 vienā palaišanā
const VVZ_MAX_DETAILS_PER_RUN  = 200; // bērnu iesniegumu (pilno datu) limits (~280 nac./dienā)
const VVZ_BACKFILL_DAYS        = 2;   // pirmajā palaišanā neiet dziļāk par N dienām

// ── ÚVO Vestník (uvo.gov.sk) — Slovākijas oficiālais vēstnesis ────────────────
// Dienas lapa ?date=DD.MM.YYYY ar kategoriju sekcijām; nacionālās: WY (výzvy =
// podlimitné izsludinājumi), IP (podlimitné rezultāti), DO (līguma grozījumi).
// Nadlimitné (M/V sekcijas, D24/D25) nāk caur TED — izlaiž.
const UVO_DAY_URL_FMT    = 'https://www.uvo.gov.sk/vestnik-a-registre/vestnik?date=%s';
const UVO_DETAIL_URL_FMT = 'https://www.uvo.gov.sk/vestnik-a-registre/vestnik/oznamenie/detail/%s';
const UVO_LOOKBACK_DAYS       = 5;  // cik dienu vestníkus pārbauda atpakaļ
const UVO_MAX_DETAILS_PER_RUN = 80; // detaļu lapu limits (~20-30 nac./dienā)
const UVO_REQUEST_DELAY_S     = 1;

// ── BOSA e-Procurement (publicprocurement.be) — Beļģijas oficiālā platforma ───
// Publiskais meklēšanas API; anonīmais Keycloak tokens ar klienta datiem, ko
// BOSA pati publicē env.config.js (paredzētā publiskā piekļuve). Katram
// pieprasījumam vajag unikālu BelGov-Trace-Id UUID. Dedup: tedPublished=true →
// izlaiž (nāk caur TED). Termiņš aktīvajiem — no workspace vault.submissionDeadline.
const BOSA_TOKEN_URL     = 'https://www.publicprocurement.be/auth/realms/supplier/protocol/openid-connect/token';
const BOSA_CLIENT_ID     = 'frontend-public';
const BOSA_CLIENT_SECRET = 'dOgiVdH2CdB7sfwunDgWQ6FY4hkVAZTPUGGj4gcAtAw'; // publisks (env.config.js)
const BOSA_SEARCH_URL    = 'https://www.publicprocurement.be/api/sea/search/publications';
const BOSA_WS_URL_FMT    = 'https://www.publicprocurement.be/api/dos/publication-workspaces/%s?includeDrafts=false';
const BOSA_PAGE_URL_FMT  = 'https://www.publicprocurement.be/publication-workspaces/%s/general';
const BOSA_MAX_PAGES      = 4;  // pa 100, kārtots pēc publicationDate DESC
const BOSA_MAX_WS_PER_RUN = 60; // workspace (termiņa) pieprasījumu limits

// ── Kerndaten KDQ (data.gv.at) — Austrijas platformu atvērtie dati ────────────
// BVergG 2018 §66: platformas publicē standartizētas KDQ rindas (BRZ shēma,
// vecā TED F-formu stila XML dokumenti). Dzīvās plūsmas: ANKÖ (~40/d), vemap,
// BBG, Ausschreibung.at; eVergabe.at un Wiener Zeitung plūsmas ir mirušas (2024).
// Dedup: <ABOVETHRESHOLD/> → izlaiž (virs ES sliekšņa → TED).
// Secība: mazākās plūsmas vispirms, lai ANKÖ vēstures aizture tās nebadina
// (globālais limits; ANKÖ atlikums izsūcas pa vairākām palaišanām).
const ATKD_FEEDS = [
    'aus'   => 'https://www.ausschreibung.at/OpenData/kdq?id=87BA5ED1',
    'bbg'   => 'https://opendata.bbg.gv.at/kerndaten/bbg_kerndaten_viii-2-1.xml',
    'vemap' => 'https://bekanntmachungen.vemap.com/vemap-kdq-01.xml',
    'anko'  => 'http://ogd.ankoe.at/api/v1/notices',
];
const ATKD_MAX_ITEMS_PER_RUN = 200; // dokumentu pieprasījumu limits vienā palaišanā
const ATKD_ITEM_DELAY_MS     = 300;

// ── SEAP / SICAP (e-licitatie.ro) — Rumānijas oficiālā platforma ──────────────
// Publiskais api-pub JSON API (vajag tikai Referer galveni!). Saraksti CN
// (paziņojumi) + CAN (rezultāti) satur VISU (termiņš, CPV, vērtība) — bez
// detaļu pieprasījumiem. Dedup: noticeNo prefikss CN/CAN = ES līmenis (TED);
// nacionālie: SCN/PC/RFQ/RFD (procedura simplificata u.c.).
const SEAP_CN_URL  = 'https://e-licitatie.ro/api-pub/NoticeCommon/GetCNoticeList/';
const SEAP_CAN_URL = 'https://e-licitatie.ro/api-pub/NoticeCommon/GetCANoticeList/';
const SEAP_REFERER = 'https://e-licitatie.ro/pub';
// SEAP katram paziņojuma tipam ir savs maršruts, un nepareizais atdod TUKŠU lapu
// (nevis kļūdu), tāpēc tips jāņem vērā. CN/CAN šeit neienāk — tie iet uz TED —
// tāpēc nacionālie ir SCN (lielākā daļa), PC, RFD un RFQ.
const SEAP_CN_VIEW_FMT  = 'https://e-licitatie.ro/pub/notices/c-notice/v2/view/%d';          // CN
const SEAP_SCN_VIEW_FMT = 'https://e-licitatie.ro/pub/notices/simplified-notice/v2/view/%d'; // SCN
const SEAP_PC_VIEW_FMT  = 'https://e-licitatie.ro/pub/notices/pc-notice/v2/view/%d';         // PC (koncesijas)
const SEAP_RFQ_VIEW_FMT = 'https://e-licitatie.ro/pub/notices/rfq-invitation/v2/view/%d';    // RFQ
const SEAP_RFD_VIEW_FMT = 'https://e-licitatie.ro/pub/rfqInvitationSad/view/%d';             // RFD (DIS; cita uzbūve!)
const SEAP_CAN_VIEW_FMT = 'https://e-licitatie.ro/pub/notices/ca-notices/view-c/%d';         // CAN/rezultāti
const SEAP_BACKFILL_DAYS = 2;
const SEAP_MAX_PAGES     = 5; // pa 200 katram sarakstam

// ── Apvienotā Karaliste — OCDS avoti ─────────────────────────────────────────
// AK vairs nav ES, tāpēc TED tur gandrīz nav (9 rindas) un dedup pret TED nav
// vajadzīgs. Sistēma ir devolvēta: Contracts Finder = Anglija + AK mēroga
// iestādes; Skotijas zem-sliekšņa konkursi uz centru NEPLŪST, tāpēc PCS ir
// atsevišķs obligāts avots. Abi dod tīru OCDS 1.1; atslēga = ocid (novērš
// dublēšanos starp portāliem). Licence: Open Government Licence v3.0.
// Sell2Wales API 2026-07-20 atbild ar HTTP 500 ("Error converting data type")
// — Velsas dati tāpat plūst uz centrālo platformu, tāpēc to neiekļaujam.
// Find a Tender (FTS) — kopš 2025-02-24 CENTRĀLĀ platforma pēc Procurement Act
// 2023; tur nonāk arī Velsas paziņojumi. Bez tā trūka ~226 aktīvo (mērījums
// 2026-07-21). Contracts Finder paliek, jo Anglijas zem-sliekšņa paziņojumi
// (659/60d) tur ir pilnīgāki.
const UK_FTS_URL       = 'https://www.find-tender.service.gov.uk/api/1.0/ocdsReleasePackages';
const UK_FTS_VIEW_FMT  = 'https://www.find-tender.service.gov.uk/Notice/%s';
// Plūsma jaunākie-pirmie → lapu limits nogriež loga veco galu. Ejam divas
// mērķtiecīgas reizes (stages=tender / stages=award), tāpēc lapas netiek tērētas
// svešai kategorijai: 60d konkursiem vajag ~65 lapu, rezultātiem pietiek ar
// dažām desmitām, jo tos tāpat ierobežo KONKURSI_RESULTS_CAP.
const UK_FTS_MAX_PAGES       = 90; // stages=tender, pa 100, kursora lapošana
const UK_FTS_AWARD_MAX_PAGES = 25; // stages=award, pa 100
const UK_CF_SEARCH_URL = 'https://www.contractsfinder.service.gov.uk/Published/Notices/OCDS/Search';
const UK_CF_VIEW_FMT   = 'https://www.contractsfinder.service.gov.uk/Notice/%s';
const UK_PCS_URL_FMT   = 'https://api.publiccontractsscotland.gov.uk/v1/Notices?dateFrom=%s&noticeType=%d&outputType=0';
const UK_PCS_VIEW_FMT  = 'https://www.publiccontractsscotland.gov.uk/search/show/search_view.aspx?ID=%s';
// 102 = lokālie paziņojumi, 104 = "Quick Quote" cenu aptaujas (abi zem-sliekšņa)
const UK_PCS_NOTICE_TYPES = [102, 104];
const UK_CF_MAX_PAGES = 60; // pa 100, kursora lapošana

// ── simap.ch — Šveices centrālā iepirkumu platforma (Verein simap.ch) ─────────
// TIKAI mazie/nacionālie konkursi. Virs-sliekšņa iepirkumi (Staatsvertragsbereich,
// WTO/GPA) tiek dublēti uz TED, tāpēc tos IZLAIŽAM: TED CH paziņojuma document_url
// satur simap.ch redirect saiti ar base64-kodētu projectId — no tā uzbūvējam
// dedup kopu un neimportējam nevienu simap projektu, kas jau ir TED (sk.
// ks_sync_simap). Publiskais REST API, anonīma piekļuve, GET query filtri —
// pētījuma norādītais POST /project-search dod 405 (metode ir GET).
// Lapošana ir "rolling": pagination.lastItem no atbildes → nākamās lapas &lastItem.
const SIMAP_SEARCH_URL = 'https://www.simap.ch/api/publications/v2/project/project-search';
const SIMAP_DETAIL_FMT = 'https://www.simap.ch/api/publications/v1/project/%s/publication-details/%s';
// UZMANĪBU: frontend ceļš ir /project-detail/ (vecais /project/ SPA čaulā dod 200,
// bet lapa rāda "404 Nicht gefunden" — pārbaudīts pārlūkā 2026-07-23).
const SIMAP_VIEW_FMT   = 'https://www.simap.ch/%s/project-detail/%s'; // valoda, projectId
const SIMAP_MAX_PAGES        = 120; // aktīvie, pa 20 = līdz 2400
const SIMAP_AWARD_MAX_PAGES  = 70;  // rezultāti, pa 20 (tāpat ierobežo RESULTS_CAP)
// Termiņu + CPV dod tikai detaļu pieprasījums; to sūtām TIKAI aktīviem konkursiem
// un TIKAI jauniem (jau importētiem detaļu neatkārto), ar griestu, lai viens
// palaidums nesūta serverim tūkstošiem pieprasījumu. Steady-state = daži/dienā.
const SIMAP_DETAIL_CAP_NORMAL = 300;
const SIMAP_DETAIL_CAP_DEEP   = 1500;

// ── Prozorro (Ukraina) — publiskais OpenProcurement/OCDS API ──────────────────
// Dedup pret TED NAV vajadzīgs: TED satur TIKAI virs-sliekšņa (aboveThreshold),
// bet mēs importējam TIKAI mazos tipus (belowThreshold/priceQuotation/reporting)
// — kopas ir disjunktas, tāpēc tipa filtrs pats garantē, ka TED nedublējas.
// Plūsma jādzen ar descending=1 (noklusējums ir AUGOŠS no 2015. gada!), lapošana
// pa next_page.offset. Saraksts atgriež tikai id+dateModified+status+tipu (title
// opt_fields KLUSI IGNORĒ), tāpēc nosaukumu/vērtību/CPV ņem no pilnā objekta —
// bet TIKAI kandidātiem (mazie tipi), nevis visiem.
const PROZORRO_FEED_URL   = 'https://public-api.prozorro.gov.ua/api/2.5/tenders';
const PROZORRO_TENDER_FMT = 'https://public-api.prozorro.gov.ua/api/2.5/tenders/%s';
const PROZORRO_VIEW_FMT   = 'https://prozorro.gov.ua/tender/%s'; // tenderID
// Rezultātiem ("complete"/tiešie līgumi) ņemam TIKAI mazās procedūras (kā agrāk).
const PROZORRO_SMALL_TYPES = ['belowThreshold', 'priceQuotation', 'reporting'];
// AKTĪVAJIEM konkursiem ņemam VISAS konkurētspējīgās procedūras, arī VIRS-SLIEKŠŅA:
// Ukraina NAV ES/TED (TED satur tikai 12 UA ierakstus), tāpēc agrākā pieņēmuma
// "virs-sliekšņa jau TED" dēļ mēs nepamatoti izmetām lielos atklātos konkursus
// (~18 aktīvi aboveThreshold uz katriem 300 skenētiem). directAward/negotiation
// izlaižam (nav konkurences).
const PROZORRO_ACTIVE_TYPES = [
    'belowThreshold', 'priceQuotation',
    'aboveThreshold', 'aboveThresholdUA', 'aboveThresholdEU',
    'competitiveDialogueUA', 'competitiveDialogueEU', 'competitiveOrdering',
    'esco', 'requestForProposal', 'closeFrameworkAgreementUA',
];
const PROZORRO_MAX_PAGES_NORMAL = 60;   // pa 100 = 6000 ierakstu skenēti
const PROZORRO_MAX_PAGES_DEEP   = 400;  // pa 100 = 40000 ierakstu skenēti
const PROZORRO_DETAIL_CAP_NORMAL = 700; // pilnie objekti (title/CPV) uz palaidumu (virs-sliekšņa dēļ celts)
const PROZORRO_DETAIL_CAP_DEEP   = 2500;

// ── vergaben.llv.li (Lihtenšteina) — ANKÖ Kendo/Angular portāls ───────────────
// Pētījums apgalvoja "tikai skrāpēšana, nav API" — NEPAREIZI: aiz Angular SPA ir
// tīrs JSON galapunkts POST api/Procurement/Notice/Find/ (bez CAPTCHA, bez token,
// tukšs {} atgriež visu publisko sarakstu). Dedup pret TED NAV vajadzīgs: ņemam
// TIKAI tresholdTypeId=1 (USB = Unterschwellenbereich, zem-sliekšņa), kas uz TED
// NEnonāk; tresholdTypeId=2 (OSB) ir virs-sliekšņa un dublējas TED — to izlaižam.
// Lihtenšteina ir sīka (kopā ~29 paziņojumi), tāpēc lapošana nav vajadzīga.
const LIVERG_FIND_URL = 'https://vergaben.llv.li/api/Procurement/Notice/Find/';
const LIVERG_VIEW_FMT = 'https://vergaben.llv.li/Detail/%d'; // notice id

// ── MTender (Moldova) — publiskais OCDS API ───────────────────────────────────
// STRATĒĢIJA (2026-07-22 pārbūve): agrākā /tenders/?offset= AUGOŠĀ plūsma ir 89%
// directAward (jau piešķirti tiešie līgumi BEZ iesniegšanas termiņa) — tāpēc rādīja
// 0 aktīvo. Turklāt termiņš (tenderPeriod.endDate) mīt APAKŠIERAKSTOS, ne records[0].
// Tagad izmantojam portāla meklēšanas API (mtender.gov.md/search/tenders), kas atbalsta
// servera-puses filtrus: proceduresTypes (izmetam directAward), periodOffer=[tagad,nākotne]
// (tikai aktīvie ar atvērtu iesniegšanas termiņu), proceduresOwnerships. Tas atgriež
// bagātu sarakstu (title/amount/buyer/procedureType/ocid) BEZ detaļu prasīšanas → filtrējam
// lēti. Detaļu (compiledRelease ar termiņu) prasa TIKAI jaunajiem konkurētspējīgajiem.
// Dedup pret TED NAV vajadzīgs: Moldova nav ES/TED, tāpēc vērtību slieksni NELIETOJAM
// (agrākais 2M MDL nepamatoti izmeta lielos atklātos konkursus).
const MTENDER_SEARCH_URL = 'https://mtender.gov.md/search/tenders';
const MTENDER_DETAIL_FMT = 'https://public.mtender.gov.md/tenders/%s';
const MTENDER_VIEW_FMT   = 'https://mtender.gov.md/tenders/%s'; // ocid
// Konkurētspējīgās procedūras (izlaižam directAward = tiešie līgumi bez konkurences).
const MTENDER_COMPETITIVE_TYPES = '["openTender","smallValue","microValue","requestForQuotations","restrictedTender"]';
const MTENDER_SEARCH_PAGE_SIZE  = 100;
const MTENDER_LIST_MAX_PAGES  = 40;   // meklēšanas lapas pa 100 (aktīvo kopa ~300)
const MTENDER_DETAIL_CAP_NORMAL = 400;
const MTENDER_DETAIL_CAP_DEEP   = 1200;
// Moldovai ĪPAŠA pieeja: hosts strādā, tad uz ~stundu pazūd, tad atkal strādā.
// Tāpēc te NEVIS gaidām ilgi, bet ātri atkāpjamies un turpinām nākamajā palaišanā:
//   · īss taimauts un maz mēģinājumu → miris hosts maksā 30 s, nevis 225 s vienam ierakstam;
//   · neizdarītie ocid paliek rindā (meta 'mtender_pending') → nākamā palaišana turpina
//     tieši no tās vietas, nevis sāk no gala. Progress uzkrājas pa palaišanām.
const MTENDER_HTTP_TIMEOUT_S = 15;  // bija 45
const MTENDER_HTTP_TRIES     = 2;   // bija 5
const MTENDER_PENDING_MAX    = 2000; // cik ocid maks. glabāt rindā (lai meta neaug bezgalīgi)

// ── open.ejn.gov.ba (Bosnija un Hercegovina) — oficiālais OData API ────────────
// Pētījums šoreiz bija PAREIZS: brīvi pieejams OData v4 bez atslēgas. NpsProcurement
// = zem-sliekšņa mazie (Direktni sporazum / Konkurentski zahtjev), kas uz TED
// NEnonāk → dedup nav vajadzīgs. Servera-puses $filter (Announced) + $orderby +
// $top/$skip → ņemam tikai svaigos, bez detaļu pieprasījumiem. CPV/vērtība
// paziņojumā nav (skatāmi avota lapā).
const EJN_BASE_URL   = 'https://open.ejn.gov.ba';
// Per-notice deep-linku NAV: publiskais portāls (www.ejn.gov.ba) lieto Angular SPA
// ar iekšējiem announcement/procedure ID un opakiem PDF GUID (docs.ejn.gov.ba/{Tips}/
// {GUID}.pdf), kas NEsaskan ar open.ejn.gov.ba OData ID. Tāpēc saitējam uz meklēšanas
// rīku (lietotājs atrod pēc nosaukuma/pasūtītāja; numurs un nosaukums ir mūsu detaļās).
const EJN_VIEW_URL   = 'https://www.ejn.gov.ba/Announcement/Search';
const EJN_PAGE_SIZE  = 50; // serveris ierobežo $top uz 50 (pat ja prasa vairāk)
const EJN_MAX_PAGES  = 60; // pa 50
// Virs-sliekšņa REGULĀRIE konkursi (OpenProcedure/CompetitiveRequest u.c.) — atsevišķa
// OData entītija ar iesniegšanas termiņu (ApplicationDeadlineDateTime) tieši sarakstā.
// Bosnija NAV ES/TED (TED satur tikai 5 BA), tāpēc arī šos rādām (agrāk tikai NPS mazie).
const EJN_OPEN_ENTITY = 'ProcurementNotices';
const EJN_OPEN_MAX_PAGES = 60; // pa 50 = līdz 3000 aktīvo

// ── jnportal.ujn.gov.rs (Serbija) — DevExpress searchgrid endpoints ───────────
// Aktuālais portāls (open-data XLSX iesalis 2020). Publiskais režģis
// GET /api/searchgrid/TenderNotices/get (sort/filter/skip/take) prasa `UserToken`
// galveni + ASP.NET sesijas cepumu: token nāk no lapas slēptā lauka #uiUserToken.
// reCAPTCHA NAV ieslēgta (grecaptcha nav lapā) → tā ir parasta sesijas-token
// plūsma, ne botu apiešana. Dokumentu tipi (Ф-kodi): Ф02/Ф05 = Јавни позив
// (aktīvie), Ф03/Ф06 = piešķiršana (rezultāti), Ф14 = izmaiņas. Termiņa/vērtības/
// CPV režģī nav. Nacionālie konkursi (11 TED RS = niecīga pārklāšanās).
const JNRS_PAGE_URL = 'https://jnportal.ujn.gov.rs/oglasi-svi';
const JNRS_API_URL  = 'https://jnportal.ujn.gov.rs/api/searchgrid/TenderNotices/get';
const JNRS_VIEW_FMT = 'https://jnportal.ujn.gov.rs/tender-eo/%s'; // TenderId
const JNRS_PAGE_SIZE = 100;
const JNRS_MAX_PAGES_NORMAL = 20; // pa 100
const JNRS_MAX_PAGES_DEEP   = 70; // pa 100

// ── e-nabavki.gov.mk (Ziemeļmaķedonija) — ESJN DataTables endpoints ───────────
// Publiskais AngularJS portāls sarakstu ielādē caur ASMX DataTables servera-puses
// endpointu (POST /Services/Notices.asmx/GetGridData, form-encoded DataTables v10
// params + JSON Discriminator). Bez atslēgas/cepumiem/CSRF (pārbaudīts PHP pusē).
// Mazie = TypeOfProcedure 13 (LowEstimatedValueProcedure) + 14 (SimplifiedOpen),
// abi zem ES sliekšņa → uz TED NEnonāk (dedup nav vajadzīgs). Discriminator Status:
// 1=aktīvie, 2=pabeigtie. Kārto pēc AnnouncementDate desc → jaunākie pirmie.
const ESJN_URL      = 'https://e-nabavki.gov.mk/Services/Notices.asmx/GetGridData';
const ESJN_VIEW_FMT = 'https://e-nabavki.gov.mk/PublicAccess/home.aspx#/dossie/%s/%d'; // Id, procType
const ESJN_SMALL_PROC_TYPES = [13, 14]; // LowEstimatedValue + SimplifiedOpen
const ESJN_PAGE_SIZE = 100;
const ESJN_ACTIVE_MAX_PAGES = 15; // pa 100
const ESJN_RESULT_MAX_PAGES = 12; // pa 100 (rezultātus ierobežo arī RESULTS_CAP)

// ── cejn.gov.me (Melnkalne) — CeJN Angular portāla JSON API ───────────────────
// Publiskais POST /api/cadocuments/GetTenders (JSON body: skip/top/statuses/...)
// bez atslēgas/sesijas (pārbaudīts PHP pusē). Noklusējuma kārtība = jaunākie pirmie.
// 2026-07-22: ņemam VISAS procedūras (Small + Open procedure + Framework) — Melnkalne
// NAV ES/TED (TED satur 0 ME), tāpēc agrākais "Open procedure = TED, izlaist" bija aplams
// un izmeta ~28% aktīvo konkurētspējīgo. Termiņš sarakstā nav; "Small" tam nav vispār
// (kā Moldovas directAward → 90-d heiristika), bet konkurētspējīgajām (Open/Framework)
// ĪSTAIS iesniegšanas termiņš ir getTenderRounds → endOfSubmissions.
const CEJN_URL        = 'https://cejn.gov.me/api/cadocuments/GetTenders';
const CEJN_ROUNDS_FMT = 'https://cejn.gov.me/api/caDocuments/getTenderRounds?tenderId=%d&isPublic=true&isIncludePrice=false';
const CEJN_VIEW_FMT   = 'https://cejn.gov.me/tenders/view-tender/%d';
const CEJN_STATUSES = '1,512,64,4,8';
const CEJN_SMALL_CAPTION = 'Small procurement';
const CEJN_PAGE_SIZE = 100;
const CEJN_MAX_PAGES_NORMAL = 15; // pa 100
const CEJN_MAX_PAGES_DEEP   = 45; // pa 100
const CEJN_ROUNDS_CAP_NORMAL = 300; // getTenderRounds (termiņš) pieprasījumu limits
const CEJN_ROUNDS_CAP_DEEP   = 1000;

// ── app.gov.al (Albānija) — APP mazo iepirkumu portāls (Umbraco form-POST) ────
// Nav JSON API — servera-renderēta HTML meklēšanas forma (Umbraco). Publiska,
// parasts pieprasījums (ne proxy-apiešana): GET lapa → __RequestVerificationToken
// → POST ar DateFrom/DateTo (formāts dd-mm-yyyy — dd/mm tiek IGNORĒTS!) → HTML
// rezultāti (Objekti/Autoriteti/Data e hapjes/Data e mbylljes/Numri i referencës).
// TIKAI mazie iepirkumi (prokurimet me vlerë të vogël) pēc portāla definīcijas →
// zem ES sliekšņa, TED NEnonāk. Portāls rāda ~jaunākos 24 (dziļas lapošanas nav).
const APPAL_URL      = 'https://www.app.gov.al/prokurimet-me-vlere-te-vogel/';
const APPAL_VIEW_URL = 'https://www.app.gov.al/prokurimet-me-vlere-te-vogel/';

// ── Starptautisko finanšu institūciju (IFI) iepirkumi — AZ/AM/GE/TR ──────────
// Šīm 4 valstīm nav pieejama nacionālā zem-sliekšņa plūsma (AZ etender aiz F5 WAF,
// GE odapi cert miris 2020, AM armeps API par lēnu). Bet attīstības BANKU FINANSĒTO
// projektu iepirkumi tur ir publiski un atklāti — tas nav nacionālais iepirkums, bet
// donoru projektu iepirkums (infrastruktūra, USD/EUR, ietver arī EOI/priekškvalifikāciju).
// Vienīgais reālais saturs šīm 4 valstīm. Abi avoti dedupē pret TED (EBRD plūst uz TED).

// Pasaules Banka — procnotices JSON API (CC BY 4.0). Valsts filtrs = pats lauka
// nosaukums project_ctry_name; Turcija tur ir 'Turkiye' (pārsaukta 2022, NE 'Turkey').
const WB_API_URL        = 'https://search.worldbank.org/api/v2/procnotices';
const WB_NOTICE_URL_FMT = 'https://projects.worldbank.org/en/projects-operations/procurement-detail/%s';
const WB_COUNTRIES      = ['AZ' => 'Azerbaijan', 'AM' => 'Armenia', 'GE' => 'Georgia', 'TR' => 'Turkiye'];
const WB_ROWS           = 120;   // jaunākie per valsts vienā palaišanā (srt=noticedate desc)
const WB_DELAY_MS       = 300;

// EBRD — ECEPP publiskā meklēšanas lapa (servera-HTML, robots.txt Disallow: tukšs).
// Viena lapa satur VISUS ~4000 paziņojumus ar pilniem laukiem (valsts title-prefiksā
// + iekavu masīvā) → viens pieprasījums, filtrē AZ/AM/GE/TR klienta pusē, detaļu
// lapas NEvajag. EBRD sarakstā Turcija ir 'Turkey'.
const EBRD_SEARCH_URL     = 'https://ecepp.ebrd.com/delta/noticeSearchResults.html';
const EBRD_NOTICE_URL_FMT = 'https://ecepp.ebrd.com/delta/viewNotice.html?displayNoticeId=%s';
const EBRD_COUNTRIES      = ['Azerbaijan' => 'AZ', 'Armenia' => 'AM', 'Georgia' => 'GE', 'Turkey' => 'TR'];

// UNDP — ANO Attīstības programmas iepirkumi (ANO aģentūras pašu procurement, cita
// garša nekā banku projekti). Statisks RSS 1.0/RDF fails pa reģioniem; RER = Europe &
// CIS. Ņemam TIKAI ES-perspektīvas valstis: Kaukāzs, Balkāni, A-Eiropa, Turcija.
// Centrālāziju (KZ/UZ/TM/KG/TJ) un RU/BY apzināti IZLAIŽAM. Viens pieprasījums;
// valsts titula beigās ("... - UNDP - UKRAINE").
// Piezīme: ADB (Āzijas Attīstības banka) apzināti NETIEK pievienota — visi tās
// endpointi ir aiz Cloudflare/Akamai bot-aizsardzības, ko neapejam (2026-07-21).
const UNDP_FEED_URL = 'https://procurement-notices.undp.org/rss_feeds/RER.xml';
const UNDP_COUNTRIES = [
    'ALBANIA' => 'AL', 'ARMENIA' => 'AM', 'AZERBAIJAN' => 'AZ', 'BOSNIA AND HERZEGOVINA' => 'BA',
    'CYPRUS' => 'CY', 'DENMARK' => 'DK', 'GEORGIA' => 'GE', 'GERMANY' => 'DE', 'ITALY' => 'IT',
    'KOSOVO' => 'XK', 'MOLDOVA' => 'MD', 'REPUBLIC OF MOLDOVA' => 'MD', 'MONTENEGRO' => 'ME',
    'NORTH MACEDONIA' => 'MK', 'REPUBLIC OF NORTH MACEDONIA' => 'MK', 'SERBIA' => 'RS',
    'SPAIN' => 'ES', 'TURKEY' => 'TR', 'TURKIYE' => 'TR', 'UKRAINE' => 'UA',
];  // BELARUS/RUSSIA + Centrālāzija (KZ/UZ/TM/KG/TJ) apzināti izlaisti — nav ES perspektīvas

// ── CYSTAT / data.gov.cy — Kipras piešķirto līgumu CSV ───────────────────────
// TIKAI REZULTĀTI. Aktīvo konkursu meklēšana eprocurement.gov.cy ir aiz CAPTCHA
// (pārbaudīts 2026-07-20) — to apzināti neapejam, tāpēc Kiprai 'Aktīvie' paliek
// tukši, un kartītē ir norāde uz oficiālo portālu. Valsts kase pusgada CSV
// publicē atvērto datu portālā: CFTID, uzvarētājs, summa, CPV, un
// 'Above or Below threshold' = gatavs TED dedup ('Κάτω' = zem sliekšņa).
// Resursa faila nosaukums mainās ik pusgadu → meklē datukopas lapā.
const CYPRUS_DATASET_URL = 'https://www.data.gov.cy/el/dataset/katalogos-dimosion-symbaseon-poy-katakyrothikan';
const CYPRUS_VIEW_URL_FMT = 'https://www.eprocurement.gov.cy/epps/cft/prepareViewCfTWS.do?resourceId=%s';

// ── CAIS EOP (app.eop.bg) — Bulgārijas centrālā platforma ─────────────────────
// WCF JSON serviss GetPublishedTendersBySpecified (Status=1 = atvērtie).
// TIKAI aktīvie konkursi (rezultātu saraksta publiskajā /today nav). Dedup:
// ProcedureType — nacionālie {12,13,14,15} (Публично състезание, Пряко
// договаряне, Събиране на оферти с обява, Покана до определени лица);
// pārbaudīts empīriski pret detaļu lapām; 2/8/9/21 = ES līmenis → TED.
const EOP_SEARCH_URL = 'https://service.eop.bg/NX1Service.svc/GetPublishedTendersBySpecified';
const EOP_PAGE_URL_FMT = 'https://app.eop.bg/today/%d';
// 2026-07-20 mērījums (1501 atvērtie, sakritība ar TED BG nosaukumiem):
// PT 2=80%, 4=100%, 5=95% → ES līmenis, izslēgt. PT 12/13/14/15 = 0% (bija jau).
// PT 17 (52 gab., 4%) un 18 (157 gab., 9%) — komunālo uzņēmumu kvalifikācijas
// sistēmas un dinamiskās iepirkumu sistēmas; tiem ROP reģistrācijas numura NAV
// (SpecialNumber tukšs 209/209) = nacionāls līmenis. Tie vienkārši nebija
// klasificēti. 3/11/22 — vienreizēji, 0% sakritība. 8/9/21 paliek izslēgti
// (agrāk pārbaudīti pret detaļu lapām). Atlikušo pārklājumu tīra ks_dedupe_vs_ted.
const EOP_NATIONAL_PT  = [3, 11, 12, 13, 14, 15, 17, 18, 22];
const EOP_PT_NAMES = [12 => 'Публично състезание', 13 => 'Пряко договаряне',
    14 => 'Събиране на оферти с обява', 15 => 'Покана до определени лица',
    17 => 'Квалификационна система', 18 => 'Динамична система за покупки',
    3 => 'Национална процедура', 11 => 'Конкурс за проект', 22 => 'Национална процедура'];
const EOP_MAX_PAGES = 5; // pa 100, kārtots pēc publicēšanas dilstoši

// ── ΚΗΜΔΗΣ / KIMDIS (cerpp.eprocurement.gov.gr) — Grieķijas reģistrs ──────────
// Oficiālais OpenData REST API (2025; limits 350 pieprasījumi/min). /notice =
// izsludinājumi, /contract = noslēgtie līgumi. Filtrs: typeOfProcedure key=6
// (Απευθείας ανάθεση = tiešie piešķīrumi, ~90% apjoma) → izlaiž. Dedup pret
// TED: vērtības slieksnis kā Spānijai (KIMDIS tiešu TED karogu nedod).
const KIMDIS_NOTICE_URL   = 'https://cerpp.eprocurement.gov.gr/khmdhs-opendata/notice';
const KIMDIS_CONTRACT_URL = 'https://cerpp.eprocurement.gov.gr/khmdhs-opendata/contract';
const KIMDIS_ATTACH_FMT   = 'https://cerpp.eprocurement.gov.gr/khmdhs-opendata/%s/attachment/%s';
const KIMDIS_BACKFILL_DAYS = 3;
const KIMDIS_MAX_PAGES     = 6; // pa 50 katram sarakstam
const KIMDIS_DIRECT_AWARD_KEY = '6';

// ── Portal javnih naročil (enarocanje.si) — Slovēnijas oficiālais portāls ─────
// JSON grid API (obligāti startRow/endRow — tukšs ķermenis = servera timeout!)
// + detaļu GET obrazecGet?id=. Dedup: sifObrazecOznaka 'EU*' formas = TED;
// nacionālās SL1 (izsludinājums), SL2/SL4 (rezultāti), SL3. CPV šifrants
// (id koks → 8 ciparu kodi) jāielādē atsevišķi katrā palaišanā.
const ENAR_GRID_URL   = 'https://www.enarocanje.si/api/obrazec/objava/obrazecGetGrid';
const ENAR_DETAIL_URL = 'https://www.enarocanje.si/api/obrazec/objava/obrazecGet?id=%d';
const ENAR_CPV_URL    = 'https://www.enarocanje.si/api/sifObrazec/sifCpvGetList?aktivnost=true';
const ENAR_PAGE_URL_FMT = 'https://www.enarocanje.si/#/pregled-objav/%d';
const ENAR_BACKFILL_DAYS = 3;
const ENAR_MAX_ROWS      = 400; // grid rindu logs vienā palaišanā
const ENAR_MAX_DETAILS_PER_RUN = 120;

// ── EOJN RH (eojn.hr) — Horvātijas oficiālais oglasnik ────────────────────────
// JSON grid API; sesijas sāknēšana: lapas GET (cepumi + uiUserToken slēptajā
// laukā), tad API ar UserToken+X-Requested-With+Referer galvenēm. Dedup:
// AboveThreshold=true → TED. TendersPublic + TendersSimple (jednostavna) =
// iepirkumi; VAwardDecisions = rezultāti TIKAI mūsu importētajiem konkursiem.
const EOJN_BOOT_URL     = 'https://eojn.hr/procurements-public';
const EOJN_GRID_URL_FMT = 'https://eojn.hr/api/searchgrid/%s/get?skip=%d&take=100';
const EOJN_VIEW_URL_FMT = 'https://eojn.hr/tender-eo/%d';
const EOJN_MAX_PAGES    = 3; // pa 100 katram gridam

// ── EKR (ekr.gov.hu) — Ungārijas oficiālā sistēma ─────────────────────────────
// Publiskais JSON API (bez atslēgas). Dedup: tedAzonosito nav null VAI tips
// sākas ar 'Uniós' → TED. Detaļas dod CPV, uzvarētāju, veidu, NUTS.
const EKR_LIST_URL       = 'https://ekr.gov.hu/api/publikus/kozbeszerzesi-hirdetmenyek';
const EKR_DETAIL_URL_FMT = 'https://ekr.gov.hu/api/publikus/kozbeszerzesi-hirdetmenyek/%s';
const EKR_VIEW_URL_FMT   = 'https://ekr.gov.hu/portal/kozbeszerzes/hirdetmenyek/%s/reszletek';
const EKR_MAX_PAGES           = 5;  // pa 100, kārtots pēc publicēšanas dilstoši
const EKR_MAX_DETAILS_PER_RUN = 80; // (~30 nacionālie/dienā)

// ── BASE (base.gov.pt) — Portugāles līgumu portāls (IMPIC) ────────────────────
// Base4 form-POST JSON (X-Requested-With galvene); anúncios DR = izsludinājumi.
// Dedup pret TED: vērtības slieksnis (kā ES/GR). Detaļu POST dod CPV, NIF,
// oficiālo DRE PDF un platformas saiti.
const BASE_RESULTS_URL     = 'https://www.base.gov.pt/Base4/pt/resultados/';
const BASE_DETAIL_PAGE_FMT = 'https://www.base.gov.pt/Base4/pt/detalhe/?type=anuncios&id=%d';
const BASE_CONTRACT_PAGE_FMT = 'https://www.base.gov.pt/Base4/pt/detalhe/?type=contratos&id=%d';
const BASE_MAX_PAGES           = 4;   // pa 100, sort=-drPublicationDate
const BASE_MAX_DETAILS_PER_RUN = 100; // (~100 anúncios/dienā)
// dados.gov.pt gada lielapjoma fails (IMPIC, CC BY 4.0, atjaunināts reizi
// nedēļā). Satur CPV/cenu/NIF/DRE saiti — meklēšanas API tos dod tikai pa
// vienam detaļu pieprasījumam, un tā ātruma ierobežojumi 60 d logu nesasniedz.
// %d = gads. Resursa URL satur versijas mapi, tāpēc to meklē caur datasets API.
const BASE_BULK_DATASET_URL = 'https://dados.gov.pt/api/1/datasets/?q=Contratos%20Publicos%20Portal%20Base%20IMPIC%20Anuncios&page_size=5';

// ── ANAC Open Data (dati.anticorruzione.it) — Itālijas CIG datu kopa ──────────
// Mēneša delta ZIP CSV (visi tajā mēnesī perfekcionētie CIG, ~200 MB CSV).
// Ņem TIKAI aktīvos (termiņš nākotnē) konkurences iepirkumus: AFFIDAMENTO*
// (tiešie piešķīrumi, ~73% apjoma) izlaiž; dedup pret TED: vērtības slieksnis
// (importo_complessivo_gara) kā ES/GR/PT. Lotes grupē pa numero_gara (līdz 53
// CSV rindām vienai garai!). Failu pārimportē tikai tad, ja mainījies
// Last-Modified (atslēgā). Ikdienā: tekošais mēnesis (+iepriekšējais līdz 3.
// datumam); dziļajā režīmā: tekošais + 2 iepriekšējie.
const ANAC_ZIP_URL_FMT = 'https://dati.anticorruzione.it/opendata/download/dataset/cig/filesystem/%s-cig_csv.zip'; // %s = GGGGMM01
const ANAC_SEARCH_URL  = 'https://dati.anticorruzione.it/opendata/dataset/cig-%d'; // gada datu kopas lapa

// ── Glabāšanas politika (lai DB neizaug — pilnie dati vienmēr paliek TED) ─────
const KONKURSI_KEEP_RESULTS_DAYS    = 60; // rezultāti/grozījumi/citi pēc publikācijas
// 'Rezultāti' CIETĀ dzēšana: pēc šī daudzuma dienām rezultātus fiziski izdzēš
// (gan `notices`, gan `notice_versions` žurnāls) — lai DB neaug bezgalīgi. Slieksnis
// > displeja loga (60 d), tāpēc paliek 30 d buferis (redzams ar &archive=1), un
// > 60 d ievākšanas vārtiem (ks_within_retention), tāpēc izdzēstos nekad neievāc atpakaļ.
const KONKURSI_KEEP_RESULTS_HARD_DAYS = 90; // pēc cik dienām rezultātu fiziski dzēš
// 'Rezultāti' sadaļai katrai valstij pietiek ar jaunākajiem N (lietotāja lēmums
// 2026-07-20) — dziļie arhīvi kalpo tikai aktīvo konkursu logam. Nacionālajiem
// avotiem N uz avotu, TED — N uz valsti (viena TED sadaļa, 27 valstis).
const KONKURSI_RESULTS_CAP = 1000;

// ── Pieklājības aiztures pret avotu serveriem (ks_http_throttle) ──────────────
// Bez šī 2026-07-20 divi avoti mūs nobloķēja: eojn.hr → HTTP 429, base.gov.pt →
// WebKnight ugunsmūris (HTTP 999) uz VISU domēnu, tā ka apstājās arī nesaistīti
// posmi. Aizture ir uz hostu un darbojas visos HTTP palīgos, tāpēc to nevar
// apiet ne cilpa, ne atkārtojums, ne cita sinhronizācijas fāze.
const KS_HOST_MIN_INTERVAL_MS = 700; // noklusējums visiem avotiem
// Bloķēšanas riska minimizēšana (centrāli, visos HTTP palīgos):
//  · jitter — pauzei pieliek nejaušus 0..25%, lai kadence nav robotiski vienmērīga;
//  · soda reizinātājs — pēc katra bloka signāla (429/403/503/999) hosta pauze ×2 (līdz ×8);
//  · ķēdes pārtraucējs — pēc KS_HOST_BLOCK_TRIP signāliem hostam šajā palaišanā vairs
//    nesūta NEVIENU pieprasījumu (labāk nepilni dati šodien nekā IP bloks uz nedēļu);
//  · atdzišana — nobloķēts hosts tiek izlaists arī nākamajās palaišanās, līdz beidzas
//    KS_HOST_COOLDOWN_S (vai avota Retry-After, ja garāks). Glabā meta 'host_cooldown_*'.
const KS_HTTP_JITTER_PCT   = 25;   // nejaušā piedeva pauzei (% no intervāla)
const KS_HOST_BLOCK_TRIP   = 3;    // bloka signāli, pēc kuriem atver ķēdi
const KS_HOST_COOLDOWN_S   = 6 * 3600; // cik ilgi izlaist hostu pēc ķēdes atvēršanās
const KS_BLOCK_CODES       = [403, 429, 503, 999]; // WAF/limitu atbildes = bloka signāls

// ── Taimautu ķēdes pārtraucējs ────────────────────────────────────────────────
// Hosts, kas PIEŅEM TCP savienojumu, bet neatbild, neatdod HTTP kodu — tāpēc tas
// neiekļuva KS_BLOCK_CODES un ķēdes pārtraucējs to neredzēja. 2026-07-26 tas maksāja
// 86 min klusas karāšanās uz public.mtender.gov.md (curl poll(), 0% CPU, 0 žurnāla
// rindu). Tagad taimauts ir tāds pats bloka signāls kā 503.
const KS_TIMEOUT_ERRNOS        = [6, 7, 28, 35]; // DNS / connect / timeout / SSL-connect
const KS_HOST_TIMEOUT_TRIP     = 3;              // taimauti PĒC KĀRTAS, pēc kuriem atver ķēdi
const KS_HOST_TIMEOUT_COOLDOWN_S = 3600;         // 1 h (īsāk nekā WAF blokam — hosts mēdz atgriezties)

// ── Viena avota posma laika budžets ───────────────────────────────────────────
// Cietais griests vienam avotam. Kad tas pārsniegts, ks_http_get pārstāj sūtīt
// ŠĪ POSMA pieprasījumus, cikli dabiski iztukšojas un sinhronizācija iet tālāk.
// Bez tā MTender detaļu cikls teorētiski varēja griezties 400 × 5 × 45 s ≈ 25 h.
// ── Avotu ielādes paralēlizācija ──────────────────────────────────────────────
// Strādnieku procesu skaits avotu ielādei (1 = secīgi kā agrāk). Dažādi avoti =
// dažādi hosti, tāpēc pieklājības pauzes un ķēdes pārtraucēji (visi per-host)
// paliek korekti. SQLite WAL ar rindas līmeņa rakstīšanu (store.php bez garām
// transakcijām) vairākus rakstītājus iztur ar busy_timeout. Env
// KONKURSI_SYNC_WORKERS konstanti pārraksta (sk. ks_sync_workers).
const KONKURSI_SYNC_WORKERS = 4;
// Cietais griests vienam strādniekam — sargs pret karājošos bērnprocesu (katru
// atsevišķu avotu tāpat ierobežo KS_STAGE_MAX_S* budžeti ks_http_get līmenī).
const KONKURSI_SYNC_WORKER_MAX_S = 2700;

const KS_STAGE_MAX_S       = 600;  // 10 min noklusējums
const KS_STAGE_MAX_S_BY_SOURCE = [ // atsevišķiem avotiem citādi
    'mtender'  => 300,  // Moldova mēdz nomirt uz ~stundu — negaidām ilgi
    'prozorro' => 900,  // Ukraina godīgi strādā, tikai lēni (skenē ~1700 ierakstus)
    'ted'      => 1800, // TED paketes ir lielas un tās ir galvenais avots
];
// Avoti, kas jau reāli ir bloķējuši vai skaidri brīdina par ierobežojumiem.
const KS_HOST_INTERVAL_MS = [
    'www.base.gov.pt'             => 4000, // WebKnight; bloķēja visu domēnu
    // FTS pats pasaka limitu: "Rate limit of 12 exceeded" → ne biežāk kā 10/min
    'www.find-tender.service.gov.uk' => 6000,
    'www.contractsfinder.service.gov.uk' => 1500,
    'api.publiccontractsscotland.gov.uk' => 1500,
    'eojn.hr'                     => 3000, // "prevelik broj uzastopnih zahtjeva" (429)
    'dati.anticorruzione.it'      => 3000, // ANAC dokumentēti ierobežojumi + IP bloki
    'cerpp.eprocurement.gov.gr'   => 2000, // KIMDIS: dokumentēts 350 pieprasījumi/min
    'www.etenders.gov.ie'         => 2000, // e-PPS sesija, lēns serveris
    'ekr.gov.hu'                  => 1500,
    'www.enarocanje.si'           => 1500,
    'api.vvz.nipez.cz'            => 1500,
    'www.uvo.gov.sk'              => 1500,
    'e-licitatie.ro'              => 1500,
    'service.eop.bg'              => 1500,
    'www.publicprocurement.be'    => 1000,
    'contrataciondelsectorpublico.gob.es' => 1000,
    'www.simap.ch'                => 600, // robusts valsts API, bet daudz detaļu pieprasījumu → pieklājīgi
    'public-api.prozorro.gov.ua'  => 700, // dokumentēts 429; "ne biežāk kā reizi 5 min pēc tukšas lapas"
    'vergaben.llv.li'             => 1200, // ANKÖ platforma; sīks avots, viens pieprasījums
    'public.mtender.gov.md'       => 500,  // OCDS API; ~960 ierakstu/dienā, daudz detaļu
    'open.ejn.gov.ba'             => 800,  // OData; servera-puses filtrs, maz pieprasījumu
    'e-nabavki.gov.mk'            => 800,  // ASMX DataTables; ~25 POST uz palaidumu
    'jnportal.ujn.gov.rs'         => 1000, // DevExpress searchgrid; sesijas token
    'cejn.gov.me'                 => 800,  // CeJN JSON API; POST lapošana
    'www.app.gov.al'              => 1500, // Umbraco form-POST HTML; maz pieprasījumu
];
const KONKURSI_KEEP_EXPIRED_DAYS    = 14; // beigušies konkursi pēc termiņa
const KONKURSI_KEEP_NODEADLINE_DAYS = 90; // konkursi bez termiņa pēc publikācijas

// ── Teksta apciršana (pilnais teksts vienmēr pieejams TED oriģinālā) ──────────
const KONKURSI_DESC_MAX     = 1200;
const KONKURSI_LOT_DESC_MAX = 400;
const KONKURSI_MAX_LOTS     = 30;
const KONKURSI_MAX_ORGS     = 30;

// ── API ────────────────────────────────────────────────────────────────────────
// 100 (agrāk 30): JĀSAKRĪT ar KONKURSI_SNAPSHOT_CARDS — kad lēnā pilnā atbilde
// beidzot atnāk, tās 1. lapa nomaina iegultās momentuzņēmuma kartiņas; ja lapa
// būtu mazāka, saraksts lietotājam acu priekšā saruktu no 100 uz 30.
const KONKURSI_PAGE_SIZE = 100;
// Pasūtītāju izvēle. Nolaižamā saraksta (bez meklēšanas) apjoms — Latvijai tas
// nosedz visus ~535, pāri visiem avotiem to ir ~42 000, tāpēc tur redzami biežākie.
const KONKURSI_BUYER_SUGGEST_MAX = 600;
// Meklējot saraksts nav ierobežots ar augšējiem 600 — meklē pa visiem pasūtītājiem,
// bet atgriež tikai atbilstošākos, lai izkrītošais saraksts paliek pārskatāms.
const KONKURSI_BUYER_SEARCH_MAX  = 50;
const KONKURSI_BUYER_QUERY_MAX   = 120; // ievades garuma griesti filtram
