<?php
/**
 * granti/lib/avoti_kandidati.php — KANDIDĀTU AVOTU reģistrs (zondei, ne būvei).
 *
 * Šis fails neko neievāc. Tas ir saraksts, ko zonde (granti/bin/zonde.php) pārbauda un
 * pēc kura pieņem lēmumu, vai avots vispār ir nolasāms. Būve to nelieto: avots pāriet uz
 * būvi tikai tad, kad tam ir apstiprināta licence UN uzrakstīts izvilcējs.
 *
 * LAUKI, UN KĀPĒC KATRS:
 *   licence — kas atļauj MUMS glabāt saturu. '?' nozīmē "nav pārbaudīts", un tas ir
 *     BLOĶĒJOŠS: bez licences drīkst rādīt tikai faktus (nosaukums, termiņš, summa, saite),
 *     nevis pārpublicēt aprakstu. Sk. gr_avots_drikst_glabat().
 *   mi_liegts — vietnes robots.txt nosauc MI rāpuļus (ClaudeBot u.tml.). Tādas vietnes
 *     NEZONDĒJAM no izstrādes sesijas; tās pārbauda pats serveris ar savu marķieri, kuram
 *     robots.txt aizlieguma nav. Tas nav formalitātes trūkums, bet tā pati kārtula, kāpēc
 *     ievācējā nav --ignore karodziņa.
 *   tips_ceribas — ko gaidām atrast. Zonde atdod, kas tur patiesībā ir.
 */
declare(strict_types=1);

/** @return array<int,array<string,mixed>> */
function gr_avotu_kandidati(): array {
    return [
        // ── Latvija: vienotā valsts Drupal platforma (JSON:API atvērts anonīmi) ──────
        ['kods' => 'cfla.api', 'iestade' => 'CFLA', 'nos' => 'ES fondu atlases (ziņu plūsma)',
         'url' => 'https://www.cfla.gov.lv/jsonapi/node/article?page%5Blimit%5D=5&sort=-created',
         'tips_ceribas' => 'jsonapi', 'licence' => '?'],
        ['kods' => 'cfla.lapa', 'iestade' => 'CFLA', 'nos' => 'Jaunumu lapa (HTML)',
         'url' => 'https://www.cfla.gov.lv/lv/jaunumi', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'lzp.api', 'iestade' => 'LZP', 'nos' => 'Zinātnes konkursi (ziņu plūsma)',
         'url' => 'https://www.lzp.gov.lv/jsonapi/node/article?page%5Blimit%5D=5&sort=-created',
         'tips_ceribas' => 'jsonapi', 'licence' => '?'],
        ['kods' => 'vkkf.api', 'iestade' => 'VKKF', 'nos' => 'Kultūras konkursi (ziņu plūsma)',
         'url' => 'https://www.vkkf.lv/jsonapi/node/article?page%5Blimit%5D=5&sort=-created',
         'tips_ceribas' => 'jsonapi', 'licence' => '?'],
        ['kods' => 'vkkf.grafiks', 'iestade' => 'VKKF', 'nos' => 'Konkursu grafiks (termiņi!)',
         'url' => 'https://www.vkkf.lv/lv/konkursu-grafiks', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'lad.api', 'iestade' => 'LAD', 'nos' => 'Lauku atbalsta pasākumi (ziņu plūsma)',
         'url' => 'https://www.lad.gov.lv/jsonapi/node/article?page%5Blimit%5D=5&sort=-created',
         'tips_ceribas' => 'jsonapi', 'licence' => '?'],
        ['kods' => 'nva.api', 'iestade' => 'NVA', 'nos' => 'Nodarbinātības atbalsts (ziņu plūsma)',
         'url' => 'https://www.nva.gov.lv/jsonapi/node/article?page%5Blimit%5D=5&sort=-created',
         'tips_ceribas' => 'jsonapi', 'licence' => '?'],
        ['kods' => 'viaa.api', 'iestade' => 'VIAA', 'nos' => 'Erasmus+ nacionālās atlases',
         'url' => 'https://www.viaa.gov.lv/jsonapi/node/article?page%5Blimit%5D=5&sort=-created',
         'tips_ceribas' => 'jsonapi', 'licence' => '?'],
        // JSPA ir WordPress, ne valsts Drupal. Atsevišķs ieraksta tips 'competition' REST API
        // NAV atklāts (show_in_rest=false), TOMĒR tas ir savā vietnes kartē — tātad konkursu
        // adrešu sarakstu var iegūt bez skrāpēšanas, un skrāpēt vajag tikai pašas lapas.
        ['kods' => 'jspa.karte', 'iestade' => 'JSPA', 'nos' => 'Konkursu vietnes karte (XML)',
         'url' => 'https://jaunatne.gov.lv/competition-sitemap.xml', 'tips_ceribas' => 'xml', 'licence' => '?'],
        ['kods' => 'altum.wp', 'iestade' => 'ALTUM', 'nos' => 'WordPress REST (ziņas)',
         'url' => 'https://www.altum.lv/wp-json/wp/v2/posts?per_page=3', 'tips_ceribas' => 'json', 'licence' => '?'],
        ['kods' => 'riga.api', 'iestade' => 'Rīgas dome', 'nos' => 'Pašvaldības grantu konkursi',
         'url' => 'https://www.riga.lv/jsonapi/node/article?page%5Blimit%5D=5&sort=-created',
         'tips_ceribas' => 'jsonapi', 'licence' => '?'],

        // ── Latvija: pārējie ────────────────────────────────────────────────────────
        ['kods' => 'sif.api', 'iestade' => 'SIF', 'nos' => 'JAU LIETOTS — kontrolparaugs',
         'url' => 'https://sif.map.gov.lv/data/konkurss_public_list?offset=0&limit=3',
         'tips_ceribas' => 'json', 'licence' => 'nav deklarēta (valsts iestādes dati)'],
        ['kods' => 'liaa.api', 'iestade' => 'LIAA', 'nos' => 'Atbalsta programmas (JSON:API)',
         'url' => 'https://liaa.business.gov.lv/jsonapi/node/atbalsta_programma',
         'tips_ceribas' => 'jsonapi', 'licence' => '?'],
        ['kods' => 'business.lapa', 'iestade' => 'LIAA', 'nos' => 'business.gov.lv atbalsta saraksts',
         'url' => 'https://business.gov.lv/lv/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'altum', 'iestade' => 'ALTUM', 'nos' => 'Programmu saraksts',
         'url' => 'https://www.altum.lv/pakalpojumi/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'esfondi', 'iestade' => 'FM/esfondi.lv', 'nos' => 'ES fondu portāls',
         'url' => 'https://www.esfondi.lv/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'kkf.sistema', 'iestade' => 'VKKF', 'nos' => 'Projektu pieteikumu sistēma',
         'url' => 'https://kkf.kulturaskarte.lv/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'lad.eps', 'iestade' => 'LAD', 'nos' => 'Elektroniskā pieteikšanās sistēma',
         'url' => 'https://eps.lad.gov.lv/login', 'tips_ceribas' => 'auth', 'licence' => '?'],


        // ── 2026-09-11 pētījuma PĀRBAUDĪTIE atradumi (test_tools/granti/parbaudi_petijumu.php).
        // Pētījums deva 25 avotus ar "izmērītiem" baitiem; neviens izmērs neatbilda, 7 adreses
        // atdeva 404. Šeit ir tikai tas, ko pārbaude apstiprināja ar savu mērījumu.
        ['kods' => 'cfla.tabula', 'iestade' => 'CFLA', 'nos' => 'Atlašu tabula ar termiņiem (36 datumi)',
         'url' => 'https://www.cfla.gov.lv/lv/2021-2027-programmas', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'lad.termini', 'iestade' => 'LAD', 'nos' => 'Pieteikšanās termiņi EJZAF (181 datums)',
         'url' => 'https://www.lad.gov.lv/lv/pieteiksanas-termini-uz-pasakumu-ejzaf-2023-2027',
         'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'jspa.lapa', 'iestade' => 'JSPA', 'nos' => 'Konkursu saraksts (īstā adrese)',
         'url' => 'https://jaunatne.gov.lv/konkursi/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'riga.kultura', 'iestade' => 'Rīgas dome', 'nos' => 'Kultūras finansējuma konkursi',
         'url' => 'https://kultura.riga.lv/lv/finansejuma-konkursi', 'tips_ceribas' => 'html', 'licence' => '?'],
        // ES Oficiālā Vēstneša C sērija: vienīgais avots, kas atnāca ar īstu, dokumentētu API.
        ['kods' => 'cellar', 'iestade' => 'ES Publikāciju birojs', 'nos' => 'CELLAR SPARQL (OV C sērija)',
         'url' => 'http://publications.europa.eu/webapi/rdf/sparql?query=SELECT%20%3Fs%20WHERE%20%7B%3Fs%20%3Fp%20%3Fo%7D%20LIMIT%201&format=application%2Fsparql-results%2Bjson',
         'tips_ceribas' => 'json', 'licence' => 'Lēmums 2011/833/ES (atkalizmantojams ar atsauci)'],
        ['kods' => 'keep', 'iestade' => 'Interact/keep.eu', 'nos' => 'Interreg uzsaukumu apkopotājs',
         'url' => 'https://keep.eu/calls/', 'tips_ceribas' => 'html', 'licence' => '?'],

        // ── Eiropa un citi (ne-SEDIA) ───────────────────────────────────────────────
        ['kods' => 'eea', 'iestade' => 'EEZ/Norvēģijas granti', 'nos' => 'Uzsaukumi',
         'url' => 'https://eeagrants.org/news', 'tips_ceribas' => 'html', 'licence' => '?',
         'mi_liegts' => true, 'signals' => 'search=yes, ai-train=no'],
        ['kods' => 'interreg.bsr', 'iestade' => 'Interreg Baltic Sea Region', 'nos' => 'Calls',
         'url' => 'https://interreg-baltic.eu/calls/', 'tips_ceribas' => 'html', 'licence' => '?',
         'mi_liegts' => true],
        ['kods' => 'interreg.cb', 'iestade' => 'Interreg Central Baltic', 'nos' => 'Calls',
         'url' => 'https://centralbaltic.eu/for-applicants/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'cost', 'iestade' => 'COST', 'nos' => 'Open Call',
         'url' => 'https://www.cost.eu/funding/', 'tips_ceribas' => 'html', 'licence' => '?',
         'mi_liegts' => true],
        ['kods' => 'nordplus', 'iestade' => 'Nordplus', 'nos' => 'Programmas un termiņi',
         'url' => 'https://www.nordplusonline.org/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'nkk', 'iestade' => 'Nordic Culture Point', 'nos' => 'Grants',
         'url' => 'https://www.nordiskkulturkontakt.org/en/funding/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'eit', 'iestade' => 'EIT', 'nos' => 'KIC uzsaukumi',
         'url' => 'https://eit.europa.eu/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'diana', 'iestade' => 'NATO DIANA', 'nos' => 'Challenges',
         'url' => 'https://www.diana.nato.int/', 'tips_ceribas' => 'html', 'licence' => '?'],
        ['kods' => 'euroaccess', 'iestade' => 'euro-access.eu', 'nos' => 'Konkurents/agregators',
         'url' => 'https://www.euro-access.eu/en/calls', 'tips_ceribas' => 'html', 'licence' => 'nav (autortiesības)'],
    ];
}

/**
 * Vai no šī avota drīkst GLABĀT saturu (aprakstu), vai tikai faktus?
 *
 * Fakts (nosaukums, termiņš, summa, saite) nav autortiesību objekts, un tieši to sadaļa
 * rāda sarakstā. Apraksta teksts ir. Tāpēc bez skaidras licences avots var ienākt sadaļā
 * TIKAI kā faktu rinda ar saiti uz oriģinālu — nekad ar pārkopētu aprakstu.
 * (Datubāzes sui generis tiesības ir atsevišķs jautājums: arī faktu masveida kopēšana no
 * aizsargātas datubāzes var būt pārkāpums, tāpēc pie katra avota ir jāzina, kas to uztur.)
 */
function gr_avots_drikst_glabat(array $avots): bool {
    $l = (string)($avots['licence'] ?? '?');
    return $l !== '?' && !str_starts_with($l, 'nav (');
}
