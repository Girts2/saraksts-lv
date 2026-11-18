<?php
/**
 * granti/lib/avoti_lv.php — LATVIJAS avotu izvilcēji (CFLA, VKKF, LIAA, JSPA).
 *
 * KĀPĒC ŠIE ČETRI UN NE VAIRĀK. 2026-09-11 zonde pārbaudīja 33 kandidātus. Šie četri ir
 * vienīgie, kuros konkursa nosaukums UN termiņš ir nolasāmi no struktūras (tabulas šūnas,
 * JSON lauka, viena teikuma ar atslēgvārdu), nevis jāmin no brīva teksta. Pārējos — LAD
 * prozā, pašvaldības PDF nolikumos — sk. README sadaļu "Kas vēl nav ievākts un kāpēc".
 *
 * KO GLABĀJAM UN KO NE. Visām četrām vietnēm licence NAV deklarēta. Tāpēc glabājam TIKAI
 * faktus — nosaukumu, datumus, summu, saiti uz oriģinālu —, kas nav autortiesību objekts,
 * un NEKAD nekopējam aprakstu (sec_* paliek tukši). Lapa tādu ierakstu jau prot parādīt:
 * plānās lapas nes noindex un vietnes kartē neiet. Sk. gr_avots_drikst_glabat().
 *
 * KĀPĒC TERMIŅŠ NĀK AR TUVUMA VĀRTU. Datums lapā nav termiņš: tas var būt publicēšanas
 * diena, rezultātu diena vai projekta īstenošanas beigas. Tāpēc no brīva teksta datumu
 * pieņemam TIKAI tad, ja tā tuvumā ir vārds, kas to padara par termiņu ("iesniegšanas
 * termiņš", "pieteikumus pieņem līdz"). Nepareizs termiņš šajā sadaļā ir sliktāks par
 * iztrūkstošu konkursu — cilvēks nokavē iesniegšanu.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ievacejs.php';
require_once __DIR__ . '/grantu_filtrs.php';

/** Avotu etiķetes lapai un būvei. Atslēga = grants.source vērtība. */
const GR_LV_AVOTI = [
    'CFLA' => ['🇱🇻 CFLA', 'ES fondu projektu atlases', 'https://www.cfla.gov.lv/lv/2021-2027-programmas'],
    'VKKF' => ['🇱🇻 VKKF', 'Valsts kultūrkapitāla fonds', 'https://www.vkkf.lv/lv/konkursu-grafiks'],
    // LIAA šeit vairs nav: bez tā būve tā rindas "pārnestu" no vecās DB mūžīgi, t.i.
    // turpinātu publicēt datus, kas iegūti caur hostu, kurš rāpuļus liedz. Sk. gr_lv_liaa().
    'JSPA' => ['🇱🇻 JSPA', 'Jaunatnes projektu konkursi', 'https://jaunatne.gov.lv/konkursi/'],
    'LAD'  => ['🇱🇻 LAD', 'Lauku un zivsaimniecības atbalsta kārtas', 'https://www.lad.gov.lv/lv/katalogs'],
    'LZP'  => ['🇱🇻 LZP', 'Zinātnes projektu konkursi', 'https://www.lzp.gov.lv/lv/izsludinatie-konkursi'],
    'RIGA' => ['🇱🇻 Rīgas dome', 'Pašvaldības finansēšanas konkursi', 'https://kultura.riga.lv/lv/kultura/finansesanas-konkursi'],
];

function gr_lv_log(?callable $log, string $m): void { if ($log) $log($m); }

/**
 * Adreses beigas -> ID daļa ne garāka par $max. NOGRIEZT NEDRĪKST: 2026-09-13 JSPA adreses
 * ".../…-darbinieku-mobilitate" (2025) un ".../…-darbinieku-mobilitate-2" (2026) sakrita pirmajās 60 zīmēs, INSERT OR
 * REPLACE paturēja vienu, un dzīvs 1,42 milj. € konkurss pazuda (kešā 8 tādas sadursmes). Garām — prefikss + jaucējkods.
 */
function gr_lv_id_dala(string $s, int $max = 60): string {
    return strlen($s) <= $max ? $s : substr($s, 0, $max - 9) . '-' . substr(md5($s), 0, 8);
}

/** "15.07.2026." -> "2026-07-15". Atdod null, ja datums nav derīgs kalendārā. */
function gr_lv_dat(string $s): ?string {
    if (!preg_match('~(\d{1,2})\.\s*(\d{1,2})\.\s*(20\d{2})~', $s, $m)) return null;
    [$d, $mn, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    return checkdate($mn, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mn, $d) : null;
}

/** "2026. gada 15. oktobrī" -> "2026-10-15" (mēnesis jebkurā locījumā). */
function gr_lv_dat_vardiem(string $s): ?string {
    $men = ['janvār' => 1, 'februār' => 2, 'mart' => 3, 'aprīl' => 4, 'maij' => 5, 'jūnij' => 6,
            'jūlij' => 7, 'august' => 8, 'septembr' => 9, 'oktobr' => 10, 'novembr' => 11, 'decembr' => 12];
    if (!preg_match('~(20\d{2})\.\s*gada\s*(\d{1,2})\.\s*(\p{L}+)~u', $s, $m)) return null;
    foreach ($men as $sak => $n)
        if (str_starts_with(mb_strtolower($m[3]), $sak) && checkdate($n, (int)$m[2], (int)$m[1]))
            return sprintf('%04d-%02d-%02d', (int)$m[1], $n, (int)$m[2]);
    return null;
}

/**
 * VISI datumu intervāli tekstā dokumenta secībā (2026-09-17 audits). Trīs pieraksti, ko lieto LAD un VKKF:
 * "05.10.2026.-03.11.2026.", "20.03.2026. līdz 01.11.2026" un "2026.gada 21.septembra līdz 2026.gada 20.oktobrim";
 * divciparu gads ("25.04.26. - 23.08.26.") nozīmē 20xx. `pos`/`beigas` ir BAITU pozīcijas (logus griez ar mb_strcut).
 * @return list<array{no:string,lidz:string,pos:int,beigas:int}>
 */
function gr_lv_intervali(string $t): array {
    $out = [];
    $dat = static function (string $d, string $m, string $y): ?string {
        $g = strlen($y) === 2 ? 2000 + (int)$y : (int)$y;
        return checkdate((int)$m, (int)$d, $g) ? sprintf('%04d-%02d-%02d', $g, (int)$m, (int)$d) : null;
    };
    $gads = '(20\d{2}|\d{2})(?!\d)';
    if (preg_match_all('~(?<![\d.])(\d{1,2})\.\s*(\d{1,2})\.\s*' . $gads . '\.?\s*(?:[-–—]|līdz)\s*(\d{1,2})\.\s*(\d{1,2})\.\s*' . $gads . '~u',
                       $t, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($m as $x) {
            // DIVCIPARU GADS TIKAI PILNĀ FORMĀ "25.04.26.": ar vienciparu dienu vai mēnesi tas ir punktu numurs vai versija
            // ("noteikumu 5.1.25.-5.1.27. apakšpunktu", "Versija 1.2.26 - 1.3.27" — recenzents 2026-09-17).
            $isais = strlen($x[3][0]) === 2 || strlen($x[6][0]) === 2;
            if ($isais && (strlen($x[1][0]) !== 2 || strlen($x[2][0]) !== 2 || strlen($x[4][0]) !== 2 || strlen($x[5][0]) !== 2)) continue;
            $no = $dat($x[1][0], $x[2][0], $x[3][0]); $lidz = $dat($x[4][0], $x[5][0], $x[6][0]);
            if ($no !== null && $lidz !== null && $no <= $lidz)
                $out[] = ['no' => $no, 'lidz' => $lidz, 'pos' => $x[0][1], 'beigas' => $x[0][1] + strlen($x[0][0])];
        }
    if (preg_match_all('~(20\d{2}\.\s*gada\s*\d{1,2}\.\s*\p{L}+)\s+līdz\s+(20\d{2}\.\s*gada\s*\d{1,2}\.\s*\p{L}+)~u',
                       $t, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($m as $x) {
            $no = gr_lv_dat_vardiem($x[1][0]); $lidz = gr_lv_dat_vardiem($x[2][0]);
            if ($no !== null && $lidz !== null && $no <= $lidz)
                $out[] = ['no' => $no, 'lidz' => $lidz, 'pos' => $x[0][1], 'beigas' => $x[0][1] + strlen($x[0][0])];
        }
    usort($out, fn($a, $b) => $a['pos'] <=> $b['pos']);
    return $out;
}

/**
 * No vairākiem intervāliem — TUVĀKAIS NEPAGĀJUŠAIS: agrākās beigas >= šodien, vienādām beigām pirmais tekstā.
 * Ja visi pagājuši — tas, kurš beidzās pēdējais (rinda tad ir godīgi "beigusies"). Avotu lapas uzskaita kārtu VĒSTURI
 * hronoloģiski, tāpēc "pirmais intervāls lapā" ir vecākā kārta: LAD LA 16 rādīja 6. kārtu (līdz 15.09.2026), kaut lapā
 * bija 7. kārta 02.11.–01.12.2026; Valsts zivju fonds — 02.04. pret dzīvo 30.09.; VKKF radošie braucieni — 29.03. pret 11.12.
 */
function gr_lv_tuvakais_intervals(array $iv, string $sodien): ?array {
    // JAU ATVĒRTA KĀRTA IR PĀRĀKA PAR NĀKOTNES KĀRTU (recenzents 2026-09-17): "Kārta atvērta 17.08.–15.12. … 2.kārta 01.10.–31.10."
    // ar likumu "agrākās beigas" rādītu oktobra kārtu kā GAIDĀMU, un atvērtā kārta pazustu no cilnes "Atvērtie".
    // Secība: sākušās (no <= šodien) pēc agrākajām beigām → nākotnes pēc agrākā sākuma → pagājušās pēc vēlākajām beigām.
    $atv = null; $nak = null; $pag = null;
    foreach ($iv as $i) {
        if ($i['lidz'] < $sodien) { if ($pag === null || $i['lidz'] > $pag['lidz']) $pag = $i; }
        elseif ($i['no'] <= $sodien) { if ($atv === null || $i['lidz'] < $atv['lidz']) $atv = $i; }
        elseif ($nak === null || $i['no'] < $nak['no']) $nak = $i;
    }
    return $atv ?? $nak ?? $pag;
}

/** HTML -> tīrs teksts. */
function gr_lv_teksts(string $html): string {
    // NEDERĪGS UTF-8 KLUSI IZTUKŠOJA VISU. Viens cp1252 baits (Word domuzīme 0x96) liek
    // preg_replace ar /u atdot null, un '?? \'\'' pārvērta visu lapu par tukšu virkni:
    // termiņš pazuda, CFLA ierobežotā atlase tika publicēta kā parasta. Tāpēc vispirms
    // izlabojam kodējumu, un rezerve ir teksts BEZ sablīvēšanas, ne tukšums.
    // Visas lapas kodējumu jau izlabo ievācējs (gr_iev_utf8). Šeit paliek tikai atsevišķi bojāti
    // baiti fragmentos — tos NOMAINĀM; visa teksta pārkodēšana no cp1252 sabojāja visu diakritiku.
    if (!mb_check_encoding($html, 'UTF-8')) $html = mb_scrub($html, 'UTF-8');
    $t = preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
    $t = preg_replace('~<[^>]+>~', ' ', $t) ?? $t;
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = trim(preg_replace('~\s+~u', ' ', $t) ?? preg_replace('~[ \t\r\n]+~', ' ', $t) ?? $t);
    // TEHNISKAIS TROKSNIS ĀRĀ (lietotāja lēmums 2026-09-16: lapa ir ziņnesis — rādām galveno un saiti uz avotu, ne visu lapu).
    // Tikai gariem tekstiem: virsraksti, <h1> un kartīšu nosaukumi iet caur to pašu funkciju, un tur šo frāžu nav.
    return mb_strlen($t) > 400 ? gr_lv_bez_troksna($t) : $t;
}

/**
 * Sīkdatņu logi, dalīšanās joslas, kājenes un pieejamības rīki nav lapas saturs. Ne-grantu kopā tie bija 12 % no 120 000
 * zīmēm (02, 03 un 17 — līdz 37 %), un tie aizņem vietu 7000–12000 zīmju logā, kurā šķīrējs meklē pieteikumu un termiņu.
 * Griež TIKAI nosauktas frāzes un teikumus ar sīkdatņu vārdiem — nekad veselus blokus pēc garuma. Atsevišķi bieži vārdi
 * ("Meklēt", "Aizvērt", "Statistika", "Nepieciešamās") apzināti NAV sarakstā: tie mēdz būt arī saturā.
 */
const GR_LV_TROKSNIS = [
    // Sīkdatņu un piekrišanas logi (LV un EN) — visa teikuma garumā.
    '~[^.!?]*\b(?:sīkdatn\w*|cookies?|Manage (?:options|services|consent|vendors)|The technical storage|Always active|Read more about these purposes)\b[^.!?]*[.!?]?~ui',
    '~\b(?:Piekrītu visām sīkdatnēm|Noraidīt visas|Apstiprināt izvēli|Preferences(?:\s+Preferences)+)\b~ui',
    // Dalīšanās joslas: tikai divu vai vairāku tīklu virkne, ne atsevišķs vārds.
    '~Izvēlies sociālo tīklu[^.!?]*[.!?]?~ui',
    '~\b(?:Facebook|X / Twitter|Twitter|Linkedin|LinkedIn|Threads|Draugiem(?:\.lv)?|WhatsApp|Buffer|Print|Email)(?:\s+(?:Facebook|X / Twitter|Twitter|Linkedin|LinkedIn|Threads|Draugiem(?:\.lv)?|WhatsApp|Buffer|Print|Email))+~u',
    '~\bDalīties(?:\s+Dalīties)*~ui',
    // Kājene un autortiesības.
    '~\bKājene\b[^.!?]*[.!?]?~ui',
    '~©\s*20\d{2}[^.!?]{0,120}?(?:visas tiesības aizsargātas|all rights reserved)\.?~ui',
    '~\bVisi kontakti Sekojiet mums\b~ui',
    // Pieejamības rīki un atsauksmju pogas.
    '~\b(?:Atskaņot tekstu|Klausīties|Balss ātrums|Skip to content|Vai šī informācija bija noderīga\??)~ui',
];
function gr_lv_bez_troksna(string $t): string {
    // IDEMPOTENCE: viena kārta nav fiksētais punkts — "Facebook Dalīties Twitter" pēc "Dalīties" izgriešanas kļūst par jaunu
    // tīklu virkni, ko izgrieztu tikai nākamā kārta. Tāpēc atkārto, līdz teksts vairs nemainās (f(f(x)) === f(x)).
    for ($i = 0; $i < 5; $i++) {
        $pirms = $t;
        foreach (GR_LV_TROKSNIS as $re) $t = preg_replace($re, ' ', $t) ?? $t;
        $t = trim(preg_replace('~\s{2,}~u', ' ', $t) ?? $t);
        if ($t === $pirms) break;
    }
    return $t;
}

/** Mēnešu saknes latviešu datumiem jebkurā locījumā. */
const GR_LV_MENESI = ['janvār' => 1, 'februār' => 2, 'mart' => 3, 'aprīl' => 4, 'maij' => 5, 'jūnij' => 6,
                      'jūlij' => 7, 'august' => 8, 'septembr' => 9, 'oktobr' => 10, 'novembr' => 11, 'decembr' => 12];
/**
 * Vārdi, kas datumu padara par PIETEIKŠANĀS datumu. "termiņš" un "kārta" paši ir divdomīgi ("īstenošanas termiņš"),
 * tāpēc tos pārbauda kopā ar kvalifikatoru (gr_lv_termina_atslega). Divdabis "iesniegto/iesniegtie" NAV pieteikšanās:
 * "Iesniegto projektu realizēšanas laiks ir līdz 2027. gada 23. februārim" (ventspils.lv) būvē 2026-09-13 kļuva par termiņu.
 */
const GR_LV_TERM_JA = '~pieteik\w*|iesnieg(?!t(?:o|ie|ajiem|ajā|ais|ās|ā|ajām)\b)\w*|iesniedz\w*|pieņem\w*|termiņ\w*|kārt(?:a|as|ai|u|ā|ām|ās)\b|noslēdz\w*|noslēgu\w*|noslēgsies|beigsies|beidzas|beidzās|beigusies|beidzies|pagarin\w*~ui';
/** Pagātne pie datuma bez gada: datums nav vēlāks par gada enkuru ("pieņēma no 10. februāra", "5.aprīlī noslēdzās"). */
const GR_LV_TERM_PAGATNE_DARB = '~\b(?:bija|notika|pieņēma|noritēja|ilga|varēja|noslēdzās|beidzās|noslēdzies|noslēgusies|noslēgušies|beigusies|beidzies)\b~ui';
/** Tas pats, bet AIZ datuma: tikai darbības frāze ("… līdz 6. oktobrim var iesniegt pieteikumus"),
 *  jo "Līdz 15.10. iesniegti 25 projekti" ir rezultāts, ne termiņš. */
const GR_LV_TERM_JA_PEC = '~(?:var|varēs|drīkst|aicin\w*|jā)\s+(?:\p{L}+\s+)?(?:iesniegt|pieteikt|pieteikties)\b|pieņem\s+pieteikum~ui';
/**
 * Vārdi, kas datumu padara par CITU datumu. VĀRDA SĀKUMĀ (\b): bez tā "aizpildot" saturēja "izpild" un derīgs termiņš
 * pazuda (recenzents 2). Pasākumi PAR pieteikšanos (seminārs, "uz semināru") un "iepriekš" arī nav termiņš.
 * Tikai NOMINATĪVS/LOKATĪVS semināram: "Semināru rīkošanas projektu … termiņš" (ģenitīvs) ir konkursa priekšmets.
 */
const GR_LV_TERM_NE = '~\bīsteno\w*|\blīgum\w*|\brezultāt\w*|\bbalso\w*|\bvērtē\w*|\batskai\w*|\bpārskat\w*|\bmaksājum\w*|\blēmum\w*|\bnorise\w*'
    . '|\bizpild\w*|\bpaziņo\w*|\bpublicē\w*|\biebildum\w*|\bapstrīd\w*|\bprecizē\w*|\brealizē\w*|\bilgum\w*|\bpabeig\w*|\bizlieto\w*|\biepriekš\w*'
    . '|\bseminār(?:s|ā|i|os)\b|\bvebinār(?:s|ā|i|os)\b|\bkonsultācij(?:a|ā)\b|\bdarbnīc(?:a|ā)\b|\btikšanās\b|\bsanāksm(?:e|ē)\b'
    . '|informatīv(?:ā|ais)\s+(?:diena|dienā|pasākums|pasākumā)|\buz\s+(?:semināru|vebināru|mācībām|konsultāciju|darbnīcu|pasākumu|tikšanos)\b~ui';

/**
 * Visi datumi teikumā ar baitu pozīcijām: "2026. gada 15. oktobrī", "20. augusts, 2026", "15.10.2026", "15.10.26.",
 * "2026-10-15", "15. oktobrī" (bez gada — y=null). Pirmais atrastais uzvar, ja pozīcijas pārklājas.
 * @return list<array{pos:int,beig:int,y:?int,m:int,d:int}>
 */
function gr_lv_datumi_teikuma(string $s): array {
    $out = [];
    $pieliek = static function (int $pos, string $viss, ?int $y, int $m, int $d) use (&$out): void {
        foreach ($out as $o) if ($pos >= $o['pos'] && $pos < $o['beig']) return;
        $out[] = ['pos' => $pos, 'beig' => $pos + strlen($viss), 'y' => $y, 'm' => $m, 'd' => $d];
    };
    $menesis = static function (string $v): ?int {
        foreach (GR_LV_MENESI as $sak => $n) if (str_starts_with(mb_strtolower($v), $sak)) return $n;
        return null;
    };
    if (preg_match_all('~(20\d{2})\.\s*gada\s*(\d{1,2})\.\s*(\p{L}+)~u', $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($m as $x) if (($n = $menesis($x[3][0])) !== null) $pieliek($x[0][1], $x[0][0], (int)$x[1][0], $n, (int)$x[2][0]);
    // "20. augusts, 2026" — Kandavas datuma zīmogs. Bez šīs formas tas bija datums bez gada un "atdzima" nākamgad (recenzents K-A).
    if (preg_match_all('~(?<![\d.])(\d{1,2})\.\s*(\p{L}+),?\s+(20\d{2})(?!\d)(?!\.\s*gad)~u', $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($m as $x) if (($n = $menesis($x[2][0])) !== null) $pieliek($x[0][1], $x[0][0], (int)$x[3][0], $n, (int)$x[1][0]);
    if (preg_match_all('~(?<![\d.])(\d{1,2})\.\s?(\d{1,2})\.\s?(20\d{2})(?!\d)~', $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($m as $x) $pieliek($x[0][1], $x[0][0], (int)$x[3][0], (int)$x[2][0], (int)$x[1][0]);
    if (preg_match_all('~(?<![\d.])(\d{1,2})\.(\d{1,2})\.([23]\d)\.(?!\d)~', $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($m as $x) $pieliek($x[0][1], $x[0][0], 2000 + (int)$x[3][0], (int)$x[2][0], (int)$x[1][0]);
    if (preg_match_all('~(?<![\d-])(20\d{2})-(\d{2})-(\d{2})(?![\d-])~', $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($m as $x) $pieliek($x[0][1], $x[0][0], (int)$x[1][0], (int)$x[2][0], (int)$x[3][0]);
    if (preg_match_all('~(?<![\d.])(\d{1,2})\.\s*(\p{L}+)~u', $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($m as $x) if (($n = $menesis($x[2][0])) !== null) $pieliek($x[0][1], $x[0][0], null, $n, (int)$x[1][0]);
    usort($out, fn($a, $b) => $a['pos'] <=> $b['pos']);
    return array_values(array_filter($out, fn($o) => $o['m'] >= 1 && $o['m'] <= 12 && checkdate($o['m'], $o['d'], $o['y'] ?? 2024)));
}

/** Teikumi. "līdz 15. Oktobrim" sadalītos pie "15." (lielais burts) — tādu robežu saliek atpakaļ. */
function gr_lv_teikumi(string $t): array {
    $out = [];
    foreach (preg_split('~(?<=[.!?;])\s+(?=\p{Lu})~u', $t) ?: [] as $g) {
        $n = count($out);
        if ($n > 0 && preg_match('~(?<![\d.])\d{1,2}\.$~', $out[$n - 1]) && preg_match('~^(?:janvār|februār|mart|aprīl|maij|jūnij|jūlij|august|septembr|oktobr|novembr|decembr)~ui', $g))
            $out[$n - 1] .= ' ' . $g;
        else $out[] = $g;
    }
    return $out;
}

/** Romiešu vai arābu kārtas numurs -> int ("VI" -> 6). */
function gr_lv_skaitlis(string $x): int {
    if (ctype_digit($x)) return (int)$x;
    $v = ['I' => 1, 'V' => 5, 'X' => 10]; $n = 0; $x = strtoupper($x);
    for ($i = 0; $i < strlen($x); $i++) { $c = $v[$x[$i]] ?? 0; $n += (isset($x[$i + 1]) && ($v[$x[$i + 1]] ?? 0) > $c) ? -$c : $c; }
    return $n;
}

/**
 * Pieteikšanās atslēgvārds datumam (intervālam) pozīcijā $pos, vai null. Īpašā atbilde 'ATVĒRŠANA' — grafika sākuma datums.
 *
 * 1) "DATUMS – etiķete" (grafiks): nozīmi dod etiķete AIZ datuma. Vārds pirms tā pieder iepriekšējam vienumam — recenzents K-B:
 *    "30.10.2026 – pieteikumu iesniegšanas termiņš; 30.11.2026 – rezultātu paziņošana" deva 2026-11-30.
 * 2) Citādi tuvākais vārds PIRMS datuma tajā pašā teikumā, un tas attiecas TIKAI uz pirmo datumu aiz tā (K-C: "Pieteikšanās
 *    termiņš 15.10.2026 Projekta ilgums līdz 31.12.2027" deva 2027-12-31). "Ne" vārds datīvā aiz atslēgvārda ir nolūks, ne
 *    cita nozīme ("pieņemšana stratēģijas īstenošanai no …"). Kvalifikators — līdz 3 vārdiem tieši pirms atslēgvārda.
 * 3) Bez vārda pirms datuma — darbības frāze aiz tā. Logus griež ar mb_strcut (nederīgs UTF-8 /u izteiksmei = "nav vārda").
 */
function gr_lv_termina_atslega(string $s, int $pos, int $beig): ?string {
    // PUBLIKĀCIJAS ZĪMOGS NAV TERMIŅŠ — bet tikai zīmoga FORMĀ, kad vārds stāv TIEŠI pirms datuma: "pieteikuma veidlapa
    // Pievienots: 15.09.2026." (NKC lapā tas būves dienā kļuva par termiņu). Kā vispārīgs "ne" vārds tas nogalināja īstus
    // termiņus: "Projektu iesniegumu pieņemšana atjaunota no 01.10.2026. līdz 31.10.2026." (recenzents 2026-09-17).
    if (preg_match('~\b(?:pievienot|atjaunināt|atjaunot|aktualizēt|ievietot|publicēt)[sa]\s*:?\s*$~ui', mb_strcut($s, max(0, $pos - 40), min(40, $pos), 'UTF-8'))) return null;
    $aiz = mb_strcut($s, $beig, 110, 'UTF-8');
    if (preg_match('~^\.?\s*(?:\(?plkst\.?\s*[\d.:]+\)?\s*)?[-–—:]\s*(\p{L}[^;.\d]{0,80})~u', $aiz, $em)) {
        if (preg_match('~\bsākum\w*|\batvēr\w*~ui', $em[1])) return 'ATVĒRŠANA';
        return (preg_match(GR_LV_TERM_JA, $em[1], $jm) && !preg_match(GR_LV_TERM_NE, $em[1])) ? $jm[0] : null;
    }
    $sak = max(0, $pos - 120);
    $pirms = mb_strcut($s, $sak, $pos - $sak, 'UTF-8');
    $ja = preg_match_all(GR_LV_TERM_JA, $pirms, $mj, PREG_OFFSET_CAPTURE) ? end($mj[0]) : null;
    $neVisi = preg_match_all(GR_LV_TERM_NE, $pirms, $mn, PREG_OFFSET_CAPTURE) ? $mn[0] : [];
    if ($ja !== null) $neVisi = array_values(array_filter($neVisi, fn($x) => $x[1] < $ja[1] || !preg_match('~(?:ai|ām|iem)$~u', $x[0])));
    $ne = $neVisi ? end($neVisi) : null;
    if ($ja !== null || $ne !== null) {
        if ($ja === null || ($ne !== null && $ne[1] > $ja[1])) return null;
        if (gr_lv_datumi_teikuma(substr($pirms, $ja[1] + strlen($ja[0])))) return null;
        $pirmsJa = substr($pirms, 0, $ja[1]);
        $rob = preg_match_all('~[,;:(|–—]~u', $pirmsJa, $pm, PREG_OFFSET_CAPTURE) ? end($pm[0])[1] + strlen(end($pm[0])[0]) : 0;
        $vardi = preg_split('~\s+~u', trim(substr($pirmsJa, $rob))) ?: [];
        return preg_match(GR_LV_TERM_NE, implode(' ', array_slice($vardi, -3))) ? null : $ja[0];
    }
    $pec = mb_strcut($s, $beig, 60, 'UTF-8');
    return (preg_match(GR_LV_TERM_JA_PEC, $pec, $pm2) && !preg_match(GR_LV_TERM_NE, $pec)) ? $pm2[0] : null;
}

/** Tuvākais šim datumam gads (iepriekšējais, šis vai nākamais). */
function gr_lv_tuvakais_gads(int $m, int $d, string $baze): ?string {
    $g = (int)substr($baze, 0, 4); $b = strtotime($baze); $lab = null; $att = PHP_INT_MAX;
    foreach ([$g - 1, $g, $g + 1] as $y) {
        if (!checkdate($m, $d, $y)) continue;
        $x = sprintf('%04d-%02d-%02d', $y, $m, $d);
        if (($a = abs(strtotime($x) - $b)) < $att) { $att = $a; $lab = $x; }
    }
    return $lab;
}

/**
 * GADA ENKURS datumiem bez gada: ['Y-m-d', 'publ'|'zimogs'|'nakotne'] vai null.
 * Publicēšanas datums; citādi jaunākais skaidrais datums tekstā, kas nav nākotnē (zīmogs "03.09.2026", "20. augusts, 2026");
 * citādi agrākais skaidrais nākotnes datums. JA TEKSTĀ NAV NEVIENA GADA — datumus bez gada NEPIEŅEM (recenzents K-A): pret
 * šodienu atrisināts "no 20. decembra līdz 20. janvārim" katru gadu atdzima, un 12 kešotas lapas nākamgad kļūtu par atvērtām.
 */
function gr_lv_gada_enkurs(string $t, ?string $publ, string $sodien): ?array {
    if ($publ !== null) return [$publ, 'publ'];
    $past = null; $fut = null;
    foreach (gr_lv_datumi_teikuma($t) as $d) {
        if ($d['y'] === null) continue;
        $x = sprintf('%04d-%02d-%02d', $d['y'], $d['m'], $d['d']);
        if ($x <= $sodien) $past = max($past ?? $x, $x); else $fut = min($fut ?? $x, $x);
    }
    return $past !== null ? [$past, 'zimogs'] : ($fut !== null ? [$fut, 'nakotne'] : null);
}

/** Datums bez gada pret enkuru. Nākotnes enkuram — tuvākais; pagātnē — pēdējais ≤ enkurs; citādi pirmais ≥ enkurs − $atkape d. */
function gr_lv_gads_pec_enkura(int $m, int $d, ?array $enk, int $atkape, bool $pagatne): ?string {
    if ($enk === null) return null;
    [$e, $veids] = $enk;
    if ($veids === 'nakotne') return gr_lv_tuvakais_gads($m, $d, $e);
    $g = (int)substr($e, 0, 4);
    if ($pagatne) {
        foreach ([$g, $g - 1] as $y) if (checkdate($m, $d, $y) && ($x = sprintf('%04d-%02d-%02d', $y, $m, $d)) <= $e) return $x;
        return null;
    }
    if ($veids === 'zimogs') {
        // ZĪMOGS NAV ŠĪ PAZIŅOJUMA PUBLICĒŠANA: jaunākais datums lapā mēdz būt sānjoslas vai ziņu lentes zīmogs (lrpartneriba.lv
        // "20. August, 2026" pie maija kārtas deva 2027-05-30; Cēsis 11.09.2026 pie "līdz 21. jūlija" — 2027-07-21). Tāpēc logs
        // [−300 d, +120 d] ap zīmogu (atvēršanai +60 d) un tuvākais gads logā; ārpus loga datumu nepieņem.
        $lab = null; $att = PHP_INT_MAX; $t0 = strtotime($e);
        foreach ([$g - 1, $g, $g + 1] as $y) {
            if (!checkdate($m, $d, $y)) continue;
            $x = sprintf('%04d-%02d-%02d', $y, $m, $d);
            $dt = (strtotime($x) - $t0) / 86400;
            if ($dt < -300 || $dt > ($atkape > 7 ? 60 : 120)) continue;
            if (abs($dt) < $att) { $att = abs($dt); $lab = $x; }
        }
        return $lab;
    }
    $rob = date('Y-m-d', strtotime("$e -$atkape days"));
    foreach ([$g, $g + 1] as $y) if (checkdate($m, $d, $y) && ($x = sprintf('%04d-%02d-%02d', $y, $m, $d)) >= $rob) return $x;
    return null;
}

/** Atsevišķs datums -> 'Y-m-d' (bez gada: sk. gr_lv_gads_pec_enkura, atkāpe 7 d — termiņš nestāv pirms paziņojuma). */
function gr_lv_dat_ar_gadu(array $d, ?array $enk, bool $pagatne = false): ?string {
    if ($d['y'] !== null) return checkdate($d['m'], $d['d'], $d['y']) ? sprintf('%04d-%02d-%02d', $d['y'], $d['m'], $d['d']) : null;
    return gr_lv_gads_pec_enkura($d['m'], $d['d'], $enk, 7, $pagatne);
}

/** Intervāla abi gali. Sākums bez gada — atkāpe 60 d (kārtu izsludina pirms atvēršanas); beigas manto gadu (+1 dec -> jan). */
function gr_lv_intervala_datumi(array $a, array $b, ?array $enk, bool $pagatne = false): array {
    if ($a['y'] !== null)      $yA = $a['y'];
    elseif ($b['y'] !== null)  $yA = $b['y'] - (($a['m'] > $b['m'] || ($a['m'] === $b['m'] && $a['d'] > $b['d'])) ? 1 : 0);
    else {
        $x = gr_lv_gads_pec_enkura($a['m'], $a['d'], $enk, 60, $pagatne);
        if ($x === null) return [null, null];
        $yA = (int)substr($x, 0, 4);
    }
    $yB = $b['y'] ?? ($yA + (($b['m'] < $a['m'] || ($b['m'] === $a['m'] && $b['d'] < $a['d'])) ? 1 : 0));
    return [checkdate($a['m'], $a['d'], $yA) ? sprintf('%04d-%02d-%02d', $yA, $a['m'], $a['d']) : null,
            checkdate($b['m'], $b['d'], $yB) ? sprintf('%04d-%02d-%02d', $yB, $b['m'], $b['d']) : null];
}

/**
 * PIETEIKŠANĀS intervāls no brīva teksta: ['no' => ?'Y-m-d', 'lidz' => ?'Y-m-d'].
 *
 * Divas recenzijas 2026-09-13 (15 + 41 formulējums, visi testos): teikums pa teikumam; datumam jābūt pieteikšanās kontekstā
 * (gr_lv_termina_atslega); datumiem bez gada vajag gada enkuru; PAGARINĀJUMS aizstāj tikai IEPRIEKŠ minētu tās pašas
 * rīcības/kārtas termiņu 92 dienu logā; no atlikušajiem uzvar agrākais nepagājušais (dzīvā kārta), citādi vēlākais.
 * "no A", "sāksies A", grafika "– sākums" ir atvēršana (lauks 'no').
 *
 * @param ?string $publ   publicēšanas datums (ziņām un lapām, kur tas zināms)
 * @param ?string $sodien testiem; noklusēti date('Y-m-d')
 * @param ?int    $skaits dažādu termiņu skaits (saraksta lapas pazīme)
 */
function gr_lv_termina_intervals(string $t, ?string $publ = null, ?string $sodien = null, ?int &$skaits = null): array {
    $skaits = 0;
    $sodien ??= date('Y-m-d');
    $t = preg_replace('~\s+~u', ' ', $t) ?? $t;
    $enk = gr_lv_gada_enkurs($t, $publ, $sodien);
    $kand = []; $atv = []; $baze = 0;
    foreach (gr_lv_teikumi($t) as $s) {
        $sB = $baze; $baze += strlen($s) + 1;
        $dat = gr_lv_datumi_teikuma($s);
        if (!$dat) continue;
        $ids = [];
        if (preg_match_all('~\b(\d+(?:\.\d+)+)\.?\s*rīcīb~ui', $s, $im)) foreach ($im[1] as $x) $ids[] = "r$x";
        if (preg_match_all('~\b(\d{1,2}|[IVX]{1,5})\.?\s*kārt~u', $s, $im)) foreach ($im[1] as $x) $ids[] = 'k' . gr_lv_skaitlis($x);
        $pagatne = static function (int $i) use ($s, $dat): bool {
            $no = $i > 0 ? $dat[$i - 1]['beig'] : 0;
            return preg_match(GR_LV_TERM_PAGATNE_DARB, substr($s, $no, $dat[$i]['pos'] - $no)) === 1
                || preg_match('~^\s*(?:ir\s+)?(?:noslēdzās|noslēdzies|noslēgusies|beidzās|beigusies|beidzies)\b~ui', mb_strcut($s, $dat[$i]['beig'], 40, 'UTF-8')) === 1;
        };
        $izm = [];
        // 1) "pagarināts līdz X", "pagarināts no A uz B" — X/B, ja pagarina pieteikšanos, ne līgumu.
        if (preg_match_all('~pagarin\w*(?:\s+(?!līdz\b|uz\b)\S+){0,6}?\s+(?:līdz|uz)\s+~ui', $s, $pm, PREG_OFFSET_CAPTURE))
            foreach ($pm[0] as [$frag, $off]) {
                foreach ($dat as $i => $d) {
                    if ($d['pos'] < $off + strlen($frag) - 1) continue;
                    if (!isset($izm[$i])) {
                        foreach ($dat as $j => $d2) if ($d2['pos'] >= $off && $d2['pos'] <= $d['pos']) $izm[$j] = true;
                        if (gr_lv_termina_atslega($s, $off, $off) !== null && ($x = gr_lv_dat_ar_gadu($d, $enk)) !== null)
                            $kand[] = ['no' => null, 'lidz' => $x, 'pag' => true, 'poz' => $sB + $off, 'ids' => $ids];
                    }
                    break;
                }
            }
        // 2) Intervāli "no A līdz B", "A–B", "A (plkst. 9.00) līdz B" un tabulas "A B" (tikai atstarpe, ≤ 370 d).
        for ($i = 0; $i < count($dat) - 1; $i++) {
            if (isset($izm[$i]) || isset($izm[$i + 1])) continue;
            $a = $dat[$i]; $b = $dat[$i + 1];
            $starpa = substr($s, $a['beig'], $b['pos'] - $a['beig']);
            $tukss = $starpa !== '' && trim($starpa) === '';
            if (strlen($starpa) > 60 || (!$tukss && !preg_match('~^\.?\s*(?:\(?plkst\.?\s*[\d.:]+\)?\s*)?(?:\([^)]{0,30}\)\s*)?(?:[-–—]|līdz)\s*$~u', $starpa))) continue;
            if (($kw = gr_lv_termina_atslega($s, $a['pos'], $b['beig'])) === null) { if (!$tukss) $izm[$i] = $izm[$i + 1] = true; continue; }
            [$no, $lidz] = gr_lv_intervala_datumi($a, $b, $enk, $pagatne($i));
            if ($no === null || $lidz === null || $lidz < $no || ($tukss && strtotime($lidz) - strtotime($no) > 370 * 86400)) { if (!$tukss) $izm[$i] = $izm[$i + 1] = true; continue; }
            $izm[$i] = $izm[$i + 1] = true;
            $kand[] = ['no' => $no, 'lidz' => $lidz, 'pag' => stripos($kw, 'pagarin') === 0, 'poz' => $sB + $a['pos'], 'ids' => $ids];
        }
        // 3) Atsevišķi datumi ("termiņš ir 15.10.2026.", "pieņem līdz 20. novembrim").
        foreach ($dat as $i => $d) {
            if (isset($izm[$i]) || ($kw = gr_lv_termina_atslega($s, $d['pos'], $d['beig'])) === null) continue;
            if (($x = gr_lv_dat_ar_gadu($d, $enk, $pagatne($i))) === null) continue;
            // Datums PASĀKUMA pieteikšanai ("… līdz 17. augustam, aizpildot anketu" — JSPA darbnīcas). Pieteikuma veidlapa paliek termiņš.
            if (preg_match('~^[^.;]{0,50}?(?:aizpildot\s+(?!pieteikuma|projekta)\p{L}*\s*anket|\b(?:darbnīc|seminār|vebinār|mācīb)\w*)~ui', mb_strcut($s, $d['beig'], 60, 'UTF-8'))) continue;
            $tuvu = mb_strcut($s, max(0, $d['pos'] - 24), min(24, $d['pos']), 'UTF-8');
            if ($kw === 'ATVĒRŠANA' || preg_match('~(?:\bno|sāk\w*|sākot(?:\s+ar)?|atvēr\w*)\s*$~ui', $tuvu)) { $atv[] = $x; continue; }
            $kand[] = ['no' => null, 'lidz' => $x, 'pag' => stripos($kw, 'pagarin') === 0, 'poz' => $sB + $d['pos'], 'ids' => $ids];
        }
    }
    if (!$kand) return ['no' => null, 'lidz' => null];
    $skaits = count(array_unique(array_column($kand, 'lidz')));
    foreach (array_values(array_filter($kand, fn($k) => $k['pag'])) as $p) {
        $rob = date('Y-m-d', strtotime($p['lidz'] . ' -92 days'));
        $kand = array_values(array_filter($kand, fn($k) => $k['pag'] || !($k['poz'] < $p['poz'] && $k['lidz'] < $p['lidz'] && $k['lidz'] >= $rob
            && (!$k['ids'] || !$p['ids'] || array_intersect($k['ids'], $p['ids'])))));
    }
    $nak = array_values(array_filter($kand, fn($k) => $k['lidz'] >= $sodien));
    if ($nak) { usort($nak, fn($x, $y) => strcmp($x['lidz'], $y['lidz'])); $c = $nak[0]; }
    else      { usort($kand, fn($x, $y) => strcmp($y['lidz'], $x['lidz'])); $c = $kand[0]; }
    if ($c['no'] === null && $atv) {
        $rob = date('Y-m-d', strtotime($c['lidz'] . ' -183 days'));
        $der = array_filter($atv, fn($x) => $x <= $c['lidz'] && $x >= $rob);
        if ($der) $c['no'] = max($der);
    }
    return ['no' => $c['no'], 'lidz' => $c['lidz']];
}

/**
 * Lapas publicēšanas datums (Y-m-d) vai null: meta article:published_time / JSON-LD datePublished /
 * <time pubdate|itemprop|class=publish…> / adrese ".../2024/12/17/..." / etiķete "Publicēts:" / gads adresē,
 * kas vecāks par šo ("…/remigracijas-konkurss-2019", "erasmus-2024-gada-oktobra-…" -> "2019-01-01").
 * VAJADZĪGS DATUMIEM BEZ GADA. NĀKOTNES DATUMS NAV PUBLICĒŠANA: sateka.lv lapā <time> bija nākamā
 * pasākuma datums (2026-09-16), un "5.aprīlī noslēdzās" kļuva par 2027. gada aprīli.
 */
function gr_lv_publicesanas_datums(string $html, string $url, string $sodien): ?string {
    $galva = substr($html, 0, 300000);
    $cels = rawurldecode((string)parse_url($url, PHP_URL_PATH));
    $der = static fn(string $y, string $m, string $d): ?string =>
        checkdate((int)$m, (int)$d, (int)$y) && ($x = sprintf('%04d-%02d-%02d', $y, $m, $d)) <= $sodien ? $x : null;
    foreach (['~<meta\b[^>]*(?:property|name|itemprop)\s*=\s*["\'](?:article:published_time|datePublished|pubdate|dcterms\.date)["\'][^>]*\bcontent\s*=\s*["\'](20\d{2})-(\d{2})-(\d{2})~i',
              '~<meta\b[^>]*\bcontent\s*=\s*["\'](20\d{2})-(\d{2})-(\d{2})[^"\']*["\'][^>]*(?:property|name|itemprop)\s*=\s*["\'](?:article:published_time|datePublished)["\']~i',
              '~"datePublished"\s*:\s*"(20\d{2})-(\d{2})-(\d{2})~',
              '~<time\b(?=[^>]*(?:\bpubdate\b|itemprop\s*=\s*["\']datePublished|class\s*=\s*["\'][^"\']*(?:publish|posted|entry-date|post-date|created)))[^>]*\bdatetime\s*=\s*["\'](20\d{2})-(\d{2})-(\d{2})~i'] as $re)
        if (preg_match($re, $galva, $m) && ($x = $der($m[1], $m[2], $m[3])) !== null) return $x;
    if (preg_match('~/(20\d{2})/(\d{2})/(\d{2})(?:/|$)~', $cels, $m) && ($x = $der($m[1], $m[2], $m[3])) !== null) return $x;
    if (preg_match('~(?:Publicēts|Publicēšanas datums|Pievienots|Izveidots)\s*:?\s*(\d{1,2})\.(\d{1,2})\.(20\d{2})~u', strip_tags($galva), $m)
        && ($x = $der($m[3], $m[2], $m[1])) !== null) return $x;
    $gads = (int)substr($sodien, 0, 4);
    if (preg_match_all('~(?<!\d)(20\d{2})(?!\d)~', $cels, $ym) && ($max = max(array_map('intval', $ym[1]))) < $gads) return "$max-01-01";
    return null;
}

/** Pieteikšanās termiņš (intervāla beigas vai atsevišķs datums). Sk. gr_lv_termina_intervals(). */
function gr_lv_terminš(string $t, ?string $publ = null, ?string $sodien = null): ?string {
    return gr_lv_termina_intervals($t, $publ, $sodien)['lidz'];
}

/**
 * Lapas SATURS bez vietnes izvēlnes: teksts no pirmā <h1> līdz beigām (ierobežots).
 * Pašvaldību CMS nemarķē <main>/<article> (Cēsu lapa: 71 834 rakstzīmes, no tām konkursa
 * teksts ir neliela daļa), un izvēlnē ir vārdi "konkurss", "termiņš", "EUR" no citām lapām.
 * Virsraksts ir vienīgais universālais enkurs, kas visās CMS ir tieši pirms satura.
 *
 * BEZ <h1> (recenzents 2026-09-13: cesis.lv lapās h1 nav vispār, un 12 000 zīmju "satura"
 * bija sīkdatņu josla un izvēlne — 12 Cēsu kandidāti bez termiņa katrā būvē). Tad enkurs ir
 * <main>/<article>/role="main"; un jebkurā gadījumā izmetam nav/header/footer/aside un
 * sīkdatņu joslas, kas stāv aiz enkura.
 */
function gr_lv_saturs_pec_h1(string $html, int $maks = 12000): array {
    [$virsraksts, $sat] = gr_lv_satura_html($html);
    // Lapas saturs tiek tīrīts JEBKURĀ garumā: gr_lv_teksts() tīra tikai tekstus virs 400 zīmēm (virsrakstu dēļ), un īsas lapas
    // (nkc.gov.lv "Iepirkumi", 303 zīmes) paturēja "Atskaņot tekstu … Dalīties Dalīties". Dubulta tīrīšana ir droša (idempotents).
    return [$virsraksts, mb_substr(gr_lv_bez_troksna(gr_lv_teksts($sat)), 0, $maks)];
}

/** Tas pats enkurs, bet atdod satura HTML (vietnes tajā skaita saites): [virsraksts, html]. */
function gr_lv_satura_html(string $html): array {
    $virsraksts = '';
    if (preg_match('~<h1\b[^>]*>(.*?)</h1>~is', $html, $m, PREG_OFFSET_CAPTURE)) {
        $virsraksts = gr_lv_teksts($m[1][0]);
        $html = substr($html, $m[0][1]);
    } elseif (preg_match('~<(?:main|article)\b[^>]*>|<[a-z]+\b[^>]*\brole\s*=\s*["\']main["\'][^>]*>'
                       . '|<(?:div|section)\b[^>]*\bclass\s*=\s*["\'][^"\']*\b(?:body-content|text-body|formatedtext|entry-content|article-content'
                       . '|page-content|post-content|node__content|field--name-body|content-body|main-content)\b~i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $html = substr($html, $m[0][1]);
        // Šādās CMS virsraksts ir <h2> satura sākumā (cesis.lv: div.body-content > h2.mheading,
        // un pirms tā — 500 KB izvēlnes, kurā ir visu citu konkursu nosaukumi).
        if (preg_match('~<h2\b[^>]*>(.*?)</h2>~is', substr($html, 0, 4000), $hm)) $virsraksts = gr_lv_teksts($hm[1]);
    }
    $html = preg_replace('~<(nav|header|footer|aside)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
    $html = preg_replace('~<(div|section)\b[^>]*\b(?:id|class)\s*=\s*["\'][^"\']*(?:cookie|consent|gdpr|sikdatn)[^"\']*["\'][^>]*>.*?</\1>~is', ' ', $html) ?? $html;
    return [$virsraksts, $html];
}

/** Lielākā summa eiro tekstā (aploksne). */
function gr_lv_summa(string $t): ?float {
    $max = null;
    // "milj" sakrita arī ar "miljard": "1,5 miljardi EUR" kļuva par 1 500 000 (2026-09-17 audits).
    if (preg_match_all('~(\d[\d\s\x{00A0}]*(?:[.,]\d+)?)\s*(miljard|mljrd|milj|mlj)~ui', $t, $mm, PREG_SET_ORDER))
        foreach ($mm as $m) $max = max($max ?? 0, (float)str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $m[1])
            * (preg_match('~^(?:miljard|mljrd)~ui', $m[2]) ? 1e9 : 1e6));
    // TRĪS PIERAKSTI UN VIENS SLAZDS. "1 232 992,44 EUR" (komats), "281 652.49 EUR" (punkts —
    // Ropažu Salaspils partnerība; vecais šablons no tā paņēma tikai "49" un lapā rādīja 49 €),
    // "1.234.567 EUR" (punkts kā tūkstošu atdalītājs). Slazds: "03.11.2026 EUR" — gads datuma
    // galā kļuva par 2026 € budžetu; skaitlis tieši aiz punkta vai cipara netiek ņemts.
    if (preg_match_all('~(?<![\d.,])(\d{1,3}(?:[\s\x{00A0}]\d{3})+(?:[.,]\d{1,2})?'
                     . '|\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?'
                     . '|\d+(?:[.,]\d{1,2})?)\s*(?:EUR|€|euro|eiro)~ui', $t, $mm, PREG_SET_ORDER))
        foreach ($mm as $m) {
            $sk = str_replace([' ', "\u{00A0}"], '', $m[1]);
            if (preg_match('~^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$~', $sk)) $sk = str_replace('.', '', $sk);   // 1.234.567
            $max = max($max ?? 0, (float)str_replace(',', '.', $sk));
        }
    return $max !== null && $max > 0 ? $max : null;
}

/** Nozare pēc atslēgvārdiem nosaukumā. Tas ir MŪSU vērtējums, ne avota lauks. */
function gr_lv_nozare(string $t, string $noklusejums): string {
    $k = [
        'veseliba' => ['veselīb', 'ārstniec', 'medicīn', 'slimnīc', 'ģimenes ārst'],
        'izglitiba' => ['izglītīb', 'skol', 'student', 'jaunat', 'jaunieš', 'mācīb', 'sport', 'erasmus'],
        'zinatne' => ['pētniecīb', 'pētījum', 'zinātn', 'doktor'],
        'klimats' => ['energoefektiv', 'enerģ', 'siltumnīc', 'emisij', 'transport', 'dzelzceļ', 'elektrovilcien'],
        'vide' => ['vide', 'vides', 'atkritum', 'ūden', 'kūdr', 'bioloģisk', 'dab'],
        'lauksaimnieciba' => ['lauksaimniec', 'zvejniec', 'zivsaimniec', 'lauku attīstīb', 'pārtik'],
        'kultura' => ['kultūr', 'māksl', 'mūzik', 'literatūr', 'film', 'teātr', 'mantojum', 'dizain'],
        'digitala' => ['digitalizāc', 'digitāl', 'informācijas tehnoloģ', 'mākslīg'],
        'sociala' => ['sociāl', 'nodarbināt', 'iekļauj', 'atstumtīb', 'ģimen', 'bērn'],
        'inovacija' => ['uzņēmēj', 'inovāc', 'eksport', 'komersant', 'investīc', 'tūrism'],
        'drosiba' => ['drošīb', 'aizsardzīb', 'robež', 'patvertn'],
    ];
    $t = mb_strtolower($t);
    foreach ($k as $noz => $vardi) foreach ($vardi as $v) if (str_contains($t, $v)) return $noz;
    return $noklusejums;
}

/** Tabulas rindas -> masīvs ar šūnām (teksts + pirmā saite). */
function gr_lv_tabulas(string $html): array {
    preg_match_all('~<table.*?</table>~is', $html, $t);
    $out = [];
    foreach ($t[0] as $tab) {
        preg_match_all('~<tr[^>]*>(.*?)</tr>~is', $tab, $rr);
        $rindas = [];
        foreach ($rr[1] as $r) {
            preg_match_all('~<t[hd][^>]*>(.*?)</t[hd]>~is', $r, $cc);
            $sunas = [];
            foreach ($cc[1] as $c)
                $sunas[] = ['t' => gr_lv_teksts($c),
                            'href' => preg_match('~href="([^"]+)"~i', $c, $lm) ? $lm[1] : null];
            if ($sunas) $rindas[] = $sunas;
        }
        if ($rindas) $out[] = $rindas;
    }
    return $out;
}

/** Relatīvu adresi padara absolūtu. */
function gr_lv_abs(?string $href, string $baze): ?string {
    if ($href === null || $href === '') return null;
    if (str_starts_with($href, 'http')) return $href;
    return rtrim($baze, '/') . '/' . ltrim($href, '/');
}

// ── CFLA ─────────────────────────────────────────────────────────────────────
/**
 * CFLA atlases no /lv/2021-2027-programmas.
 * Lapā ir TRĪS tabulas: [0] izsludinātās (sākas | beidzas | nosaukums | statuss),
 * [1] beigušās (beidzās | nosaukums), [2] visas programmas BEZ datumiem — pēdējo neņemam,
 * jo ieraksts bez termiņa šajā sadaļā ir tukša rinda, ne iespēja.
 */
function gr_lv_cfla(?callable $log = null, bool $arMi = false): array {
    $url = 'https://www.cfla.gov.lv/lv/2021-2027-programmas';
    $g = gr_iev_get($url, 30);
    if (!$g['ok']) { gr_lv_log($log, "  ! CFLA: {$g['kapec']}"); return []; }
    $tabs = gr_lv_tabulas($g['body']);
    $out = []; $izmestiC = [];
    foreach ([0 => 'atverts', 1 => 'beidzies'] as $idx => $rezims) {
        foreach ($tabs[$idx] ?? [] as $r) {
            if ($rezims === 'atverts') {
                if (count($r) < 4) continue;
                [$sak, $beig, $nos, $st] = [$r[0]['t'], $r[1]['t'], $r[2], $r[3]['t']];
            } else {
                if (count($r) < 2) continue;
                [$sak, $beig, $nos, $st] = ['', $r[0]['t'], $r[1], ''];
            }
            $d1 = gr_lv_dat($sak); $d2 = gr_lv_dat($beig);
            if ($d2 === null || trim($nos['t']) === '') continue;     // galvene un tukšas rindas
            $saite = gr_lv_abs($nos['href'], 'https://www.cfla.gov.lv');
            // ATLASES VEIDS IR IZŠĶIROŠS. Sarakstā tabula to nerāda, bet atlases lapā ir lauks
            // "Atlases veids: atklāta / ierobežota". Ierobežotā iesniedzēju nosaka normatīvie
            // akti (piem. 1.3.1.1. — tikai konkrēta iestāde), tāpēc bez šī lauka sadaļa
            // cilvēkam solītu iespēju, uz kuru viņš nekad nevar pieteikties. Turpat ir arī
            // "Projekta iesniedzējs" un "Atlikušais finansējums" — abi noder vairāk par
            // tabulas statusa vārdu.
            $veids = null; $iesn = null; $atlikums = null; $lt = null;
            // Beigušos atlašu lapu nevelk: no tās ņemtie lauki (atlases veids, atlikušais
            // finansējums) ierakstam ar statusu "closed" neko nemaina, bet tās ir 18 lieki
            // pieprasījumi ik nakti.
            if ($rezims === 'beidzies') $saite = $saite;   // saiti paturam, lapu neprasām
            if ($saite !== null && $rezims === 'atverts') {
                $lapa = gr_iev_get($saite, 25);
                if ($lapa['ok']) {
                    $lt = gr_lv_teksts($lapa['body']);
                    ['veids' => $veids, 'iesn' => $iesn, 'atlikums' => $atlikums] = gr_lv_cfla_lauki($lt);
                }
            }
            // Identifikators no adreses, citādi no nosaukuma: tam jābūt stabilam starp būvēm,
            // jo no tā aug publiskā adrese (/granti/{slug}).
            $ids = $saite !== null ? basename(parse_url($saite, PHP_URL_PATH) ?: '') : substr(md5($nos['t']), 0, 12);
            // ŠĶĪRĒJS ARĪ CFLA. Filtra galva sola, ka jauktie katalogi iet caur pierādījumu,
            // un CFLA ir jaukts: līdzās atklātajām atlasēm tur ir ierobežotās, un nekas
            // negarantē, ka rīt tabulā neparādīsies finanšu instruments.
            // ŠĶĪRĒJU SAUC TIKAI TAD, JA IR KO ŠĶIROT. Ja detaļlapa neatbildēja, filtrs
            // redzētu tikai atlases numuru un nosaukumu — mērīts: 24 no 26 tādā gadījumā
            // dod "nezinu" un ieraksts tiktu izmests. Tukšs teksts nav pierādījums pret
            // ierakstu; tad paļaujamies uz avotu (CFLA tabula ir ES fondu atlašu saraksts)
            // un rakstām to žurnālā.
            if ($lt !== null && $lt !== '') {
                // Šķīrējam SATURS pēc virsraksta, ne visa lapa: izvēlnē ir "Vakances",
                // "Iepirkumi", "Aizdevumi" citām sadaļām, un veto vārdi no izvēlnes pirmajā
                // redakcijā izmeta visas atvērtās atlases.
                [, $ltSat] = gr_lv_saturs_pec_h1($lapa['body']);
                $fvC = gr_filtrs_pilns(['avots' => 'CFLA', 'nosaukums' => $nos['t'],
                    'teksts' => $ltSat !== '' ? $ltSat : $lt, 'termins' => $d2], $arMi, $log);
                if ($fvC['verdikts'] !== 'grants') { $izmestiC[] = mb_substr($nos['t'], 0, 40) . ' (' . $fvC['veids'] . ')'; continue; }
            } elseif ($rezims === 'atverts') {
                gr_lv_log($log, '  · CFLA: ' . mb_substr($nos['t'], 0, 40) . ' — detaļlapa neatbildēja, šķīrējs izlaists.');
            }
            $out[] = [
                'id' => 'CFLA:' . $ids, 'source' => 'CFLA',
                'status' => $rezims === 'atverts' ? 'open' : 'closed',
                'title' => $nos['t'], 'programme' => 'CFLA', 'programme_label' => 'ES fondi (CFLA)',
                'opening_date' => $d1, 'deadline' => $d2,
                'budget' => $atlikums, 'url' => $saite ?? $url,
                'nozare' => gr_lv_nozare($nos['t'], 'inovacija'),
                // Iesniedzēju glabājam atslēgvārdos: tas ir fakts no avota lapas un vienīgais,
                // pēc kā cilvēks var saprast, vai atlase attiecas uz viņu ("pašvaldība",
                // "komersanti"). Meklēšana lapā iet arī pa šo kolonnu.
                'keywords' => $iesn !== null ? $iesn : '',
                'model' => $veids === 'ierobežota' ? 'ierobezota' : 'atkariba',
                'submit_url' => null,
            ];
        }
    }
    gr_lv_log($log, 'CFLA: ' . count($out) . ' atlases'
        . ($izmestiC ? ' (izmesti ' . count($izmestiC) . ': ' . implode('; ', array_slice($izmestiC, 0, 3)) . ')' : '') . '.');
    return $out;
}

// ── VKKF ─────────────────────────────────────────────────────────────────────
/**
 * VKKF konkursu grafiks. Divas tabulas: [0] trīs regulārie konkursi (attiecas uz visām
 * deviņām nozarēm), [1] mērķprogrammas. Datumi ir vienā šūnā formā
 * "no 05.01.2026. līdz 23.01.2026. plkst. 23:59" — un vietām bez atstarpes ("2026.līdz"),
 * tāpēc abus datumus ņemam pēc kārtas, nevis ar "līdz" kā atdalītāju.
 */
function gr_lv_vkkf(?callable $log = null, bool $arMi = false): array {
    $url = 'https://www.vkkf.lv/lv/konkursu-grafiks';
    $g = gr_iev_get($url, 30);
    if (!$g['ok']) { gr_lv_log($log, "  ! VKKF: {$g['kapec']}"); return []; }
    $tabs = gr_lv_tabulas($g['body']);
    $out = [];
    foreach ([0, 1] as $idx) {
        foreach ($tabs[$idx] ?? [] as $r) {
            if (count($r) < 2) continue;
            $nos = trim($r[0]['t']);
            if ($nos === '' || $nos === "\u{00A0}" || stripos($r[1]['t'], 'pieņem') !== false) continue;
            // Mērķprogrammu tabulā ir starprindas, kuru pirmajā šūnā ir nevis programmas
            // nosaukums, bet norāde uz kārtām ("1. konkurss 2. konkurss"). Tās nav konkursi,
            // un bez šī vārta sadaļā parādījās rinda "VKKF mērķprogramma: 1. konkurss 2. konkurss".
            if ($idx === 1 && preg_match('~^\d+\.\s*konkurss~ui', $nos)) continue;
            // VISI PERIODI ŠŪNĀ. "24.01.2026. - 29.03.2026.; 25.04.26. - 23.08.26.; 12.09.26. - 11.12.2026." — vecais kods ņēma
            // otro četrciparu datumu (29.03.), un "Radošo braucienu atbalsts" skaitījās beidzies, kaut pieņem līdz 11.12.
            $ivV = gr_lv_intervali($r[1]['t']);
            $izv = gr_lv_tuvakais_intervals($ivV, date('Y-m-d'));
            if ($izv === null) continue;
            $d1 = $izv['no']; $d2 = $izv['lidz'];
            $pilns = $idx === 0
                ? "VKKF $nos — projektu konkurss visās deviņās kultūras nozarēs"
                : "VKKF mērķprogramma: $nos";
            $out[] = [
                // ID no PIRMĀ perioda sākuma, ne no izvēlētā termiņa: izvēlētais periods gada gaitā mainās (03-29 → 08-23 → 12-11),
                // un ar termiņu ID iekšā katra maiņa dotu jaunu adresi. Pirmā perioda sākums grafikā nemainās.
                'id' => 'VKKF:' . substr(md5($nos . $ivV[0]['no']), 0, 12), 'source' => 'VKKF',
                'status' => 'open', 'title' => $pilns,
                'programme' => 'VKKF', 'programme_label' => 'Valsts kultūrkapitāla fonds',
                'opening_date' => $d1, 'deadline' => $d2, 'budget' => null,
                'url' => $idx === 0 ? 'https://www.vkkf.lv/lv/regularie-projektu-konkursi' : $url,
                'nozare' => 'kultura', 'keywords' => 'kultūra, projektu konkurss',
                'submit_url' => 'https://kkf.kulturaskarte.lv/',
            ];
        }
    }
    gr_lv_log($log, 'VKKF: ' . count($out) . ' konkursi un mērķprogrammas.');
    return $out;
}

/**
 * CFLA atlases lapas lauki no teksta: atlases veids, iesniedzējs, atlikušais finansējums. Tīra funkcija — testē uz kešotās lapas.
 * @return array{veids:?string,iesn:?string,atlikums:?float}
 */
function gr_lv_cfla_lauki(string $lt): array {
    $veids = null; $iesn = null; $atlikums = null;
    if (preg_match('~Atlases veids\s*:?\s*(atklāta|ierobežota)~ui', $lt, $vm)) $veids = mb_strtolower($vm[1]);
    // KOLS IR OBLIGĀTS: bez tā /i trāpīja brīvā tekstā ("…projekta iesniedzējs var būt arī Latvijas Zinātnes padome."),
    // un etiķetes "Atbalsta saņēmējs:" / "Iesniedzējs:" netika atpazītas vispār (2026-09-17 audits).
    if (preg_match('~(?:Projekta iesniedzējs|Finansējuma saņēmējs|Atbalsta saņēmējs|Iesniedzējs)\s*:\s*(.{5,180}?)(?:Atlikušais|Projektu atlases|Vērtēšanas|Lejupielādēt|$)~ui', $lt, $im))
        $iesn = trim($im[1]);
    // "Atlikušais finansējums (03.09.2026.) – 4 837 008,55 EUR": ar domuzīmi kola vietā vecais "[^:]*:" aizskrēja līdz
    // NĀKAMĀ lauka kolam, notvēra tukšumu un ierakstīja budžetu 0,0.
    if (preg_match('~Atlikušais finansējums\s*(?:\([^)]{0,24}\))?\s*[:–—-]?\s*(\d{1,3}(?:[ \x{00A0}]\d{3})+(?:,\d+)?|\d+(?:,\d+)?)(?![\d.])~ui', $lt, $am))
        $atlikums = (float)str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $am[1]);
    return ['veids' => $veids, 'iesn' => $iesn, 'atlikums' => $atlikums];
}

// ── LIAA ─────────────────────────────────────────────────────────────────────
/**
 * LIAA atbalsta programmas no PUBLISKAJĀM lapām (sitemap -> /atbalsta-iespejas/*).
 *
 * KĀPĒC NE JSON:API. `liaa.business.gov.lv/jsonapi/...` pārvirza uz `office.business.gov.lv`,
 * un TĀ hosta robots.txt ir `User-agent: * / Disallow: /`. Sākotnēji mēs to nemanījām, jo
 * curl pārvirzei seko klusi un robots pārbaudījām tikai sākotnējai adresei. 2026-09-12 audits
 * to atklāja; ievācējs tagad pārbauda arī galamērķi (RFC 9309 prasa pārvērtēt pēc pārvirzes).
 * Publiskās programmu lapas ir uz liaa.business.gov.lv, kur robots.txt ir `Allow: /`, un tās
 * dod pat vairāk: lauku "Atbalsta veids" ar tiešu vārdu "Finanšu (grants)" vai "Aizdevums".
 *
 * Adrešu saraksts nāk no vietnes kartes, ne no saraksta lapas: `/atbalsta-iespejas` ir JS
 * čaula (1 103 rakstzīmes teksta), bet `sitemap.xml?page=1..4` satur visas 70 lapas.
 */
function gr_lv_liaa(?callable $log = null, bool $arMi = false): array {
    // ATSLĒGTS 2026-09-12. Programmu LAPAS uz liaa.business.gov.lv ir atļautas (robots.txt
    // `Allow: /`), bet UZSKAITĪT tās nav kā: gan `/jsonapi/...`, gan `sitemap.xml` pārvirza
    // uz office.business.gov.lv, kura robots.txt ir `User-agent: * / Disallow: /`, un pati
    // liaa robots.txt savu Sitemap norāda tieši uz to liegto hostu. Saraksta lapa
    // `/atbalsta-iespejas` ir JS čaula bez saitēm, un programmu lapas cita uz citu nesaista.
    // Tātad legāla adrešu avota nav. Kods paliek gatavs dienai, kad LIAA atbild uz vēstuli
    // (tas pats ceļš, kas nostrādāja ar Grieķijas ΓΕΜΗ).
    gr_lv_log($log, 'LIAA: atslēgts — adrešu uzskaite iespējama tikai caur office.business.gov.lv, kur robots.txt liedz visu.');
    return [];
    // phpcs:disable — zemāk gaida ievākšanas ceļš, kas ieslēdzas, kolīdz ir legāls saraksts.
    $urls = [];
    for ($lapa = 1; $lapa <= 6; $lapa++) {
        $g = gr_iev_get("https://liaa.business.gov.lv/sitemap.xml?page=$lapa", 25);
        if (!$g['ok']) break;
        if (!preg_match_all('~<loc>([^<]*/atbalsta-iespejas/[^<]*)</loc>~', $g['body'], $m)) continue;
        foreach ($m[1] as $u) $urls[$u] = true;
    }
    $urls = array_keys($urls);
    if (!$urls) { gr_lv_log($log, '  ! LIAA: vietnes kartē nav programmu adrešu.'); return []; }

    $out = []; $izlaisti = [];
    foreach ($urls as $u) {
        $p = gr_iev_get($u, 25);
        if (!$p['ok']) continue;
        $t = gr_lv_teksts($p['body']);
        $nos = preg_match('~<h1[^>]*>(.*?)</h1>~is', $p['body'], $hm) ? gr_lv_teksts($hm[1]) : '';
        if ($nos === '') continue;
        // Lapas lauki ir tekstā ar etiķetēm; ņemam līdz nākamajai zināmajai etiķetei.
        $lauks = function (string $et) use ($t): string {
            return preg_match('~' . $et . '\s*:?\s*(.{2,180}?)\s*(?:Atbalsta (?:veids|apjoms)|Pieejamais finansējums|Kam paredzēta|Pieteikšanās|Šobrīd aktuāli|Apraksts)~u', $t, $m)
                ? trim($m[1]) : '';
        };
        $veids   = $lauks('Atbalsta veids');
        $apjoms  = $lauks('Atbalsta apjoms');
        $fonds   = $lauks('Pieejamais finansējums');
        $kam     = $lauks('Kam paredzēta\??');
        $piet    = $lauks('Pieteikšanās');
        // Pašdeklarēti slēgtās programmas: lapa pati saka "Pieteikšanās slēgta" vai nosaukumā
        // ir "(noslēgta)". Tās nav ne iespēja, ne vēsture ar datumu.
        if (stripos($nos, 'noslēgta') !== false || preg_match('~slēgta|noslēgusies~ui', $piet)) {
            $izlaisti[] = mb_substr($nos, 0, 38) . ' (slēgta)';
            continue;
        }
        $dl = gr_lv_terminš($t) ?? gr_lv_dat($piet) ?? gr_lv_dat_vardiem($piet);
        $summa = gr_lv_summa($apjoms) ?? gr_lv_summa($fonds);
        if ($dl === null && $summa === null) { $izlaisti[] = mb_substr($nos, 0, 38) . ' (nav ne termiņa, ne summas)'; continue; }
        // Šķīrējam padodam TIEŠI to lauku, kurā avots pats nosauc instrumentu — tas ir
        // stiprākais pierādījums, kāds šajā katalogā vispār ir.
        $fv = gr_filtrs_pilns(['avots' => 'LIAA', 'nosaukums' => $nos,
            'finansejums' => trim($veids . ' ' . $apjoms), 'sanemejs' => $kam,
            'teksts' => mb_substr($t, 0, 6000), 'termins' => $dl], $arMi, $log);
        if ($fv['verdikts'] !== 'grants') { $izlaisti[] = mb_substr($nos, 0, 38) . ' (' . $fv['veids'] . ')'; continue; }
        $out[] = [
            'id' => 'LIAA:' . gr_lv_id_dala(basename((string)parse_url($u, PHP_URL_PATH))),
            'source' => 'LIAA', 'status' => 'open', 'title' => $nos,
            'programme' => 'LIAA', 'programme_label' => 'LIAA atbalsts uzņēmējiem',
            'opening_date' => null, 'deadline' => $dl, 'budget' => $summa, 'url' => $u,
            'nozare' => gr_lv_nozare($nos, 'inovacija'),
            'keywords' => trim($kam . ($apjoms !== '' ? '; ' . $apjoms : '')),
            'submit_url' => null,
        ];
    }
    gr_lv_log($log, 'LIAA: ' . count($out) . ' granti no ' . count($urls) . ' publiskajām lapām (izlaisti '
        . count($izlaisti) . ': ' . implode('; ', array_slice($izlaisti, 0, 3)) . ').');
    return $out;
}

// ── JSPA ─────────────────────────────────────────────────────────────────────
/**
 * JSPA konkursi. WordPress ieraksta tips 'competition' REST API nav atklāts, TOMĒR tam ir
 * sava vietnes karte — tātad adrešu sarakstu var iegūt bez skrāpēšanas, un skrāpēt vajag
 * tikai pašas lapas.
 *
 * DIVI IEROBEŽOJUMI, abi ar nolūku:
 *   · $maks lapas vienā palaišanā — sveša vietne nav jāapstaigā 200 reižu naktī. Nosacītais
 *     GET nozīmē, ka nākamajās reizēs lielākā daļa atbild 304.
 *   · atmetam konkursus, kuru termiņš ir vecāks par 400 dienām: JSPA arhīvā ir 2021. gada
 *     uzsaukumi, un sadaļa nav vēstures krātuve.
 */
function gr_lv_jspa(?callable $log = null, int $maks = 60): array {
    $g = gr_iev_get('https://jaunatne.gov.lv/competition-sitemap.xml', 30);
    if (!$g['ok']) { gr_lv_log($log, "  ! JSPA karte: {$g['kapec']}"); return []; }
    preg_match_all('~<loc>([^<]+)</loc>~', $g['body'], $m);
    // Pati saraksta lapa "/konkursi/" kartē arī ir, un tās "Beigu datums" ir pirmās kartītes termiņš — tas nav konkurss.
    $urls = array_values(array_filter($m[1], fn($u) => !str_contains($u, '%') && str_contains($u, '/konkursi/')
                                                     && trim((string)parse_url($u, PHP_URL_PATH), '/') !== 'konkursi'));
    // Jaunākie vispirms: kartē tie ir pēc lastmod, bet droši ir apgriezt kārtību — vecie
    // uzsaukumi kartes sākumā citādi apēstu visu limitu.
    $urls = array_slice(array_reverse($urls), 0, $maks);
    $slieksnis = date('Y-m-d', time() - 400 * 86400);
    $out = [];
    foreach ($urls as $u) {
        $p = gr_iev_get($u, 25);
        if (!$p['ok']) continue;
        $t = gr_lv_teksts($p['body']);
        // STRUKTURĒTAIS LAUKS "Beigu datums 01.10.2025 13:00" ir JSPA pašas termiņš. Brīvais teksts ir tikai rezerve:
        // tur blakus stāv darbnīcu pieteikšanās ("Pieteikšanās līdz 17. augustam, aizpildot anketu"), kas 2025. gada
        // oktobra konkursam iedeva 2026-08-17. Rezervē — satura daļa (bez sānjoslas) un publicēšanas datums.
        $dl = preg_match('~Beigu datums\s*(\d{1,2}\.\d{1,2}\.20\d{2})~u', $t, $bm) ? gr_lv_dat($bm[1]) : null;
        $d1 = preg_match('~Sākuma datums\s*(\d{1,2}\.\d{1,2}\.20\d{2})~u', $t, $sm) ? gr_lv_dat($sm[1]) : null;
        if ($dl === null) {
            [, $jSat] = gr_lv_saturs_pec_h1($p['body']);
            $dl = gr_lv_terminš($jSat !== '' ? $jSat : $t, gr_lv_publicesanas_datums($p['body'], $u, date('Y-m-d')));
        }
        if ($dl === null || $dl < $slieksnis) continue;
        $nos = preg_match('~<h1[^>]*>(.*?)</h1>~is', $p['body'], $hm)
            ? gr_lv_teksts($hm[1])
            : (preg_match('~<title>(.*?)</title>~is', $p['body'], $tm) ? gr_lv_teksts($tm[1]) : '');
        $nos = trim(preg_replace('~\s*[|–-]\s*(JSPA|Jaunatnes.*)$~u', '', $nos) ?? $nos);
        if ($nos === '') continue;
        $out[] = [
            'id' => 'JSPA:' . gr_lv_id_dala(basename(rtrim((string)parse_url($u, PHP_URL_PATH), '/'))),
            'source' => 'JSPA', 'status' => ($d1 !== null && $d1 > date('Y-m-d')) ? 'forthcoming' : 'open', 'title' => $nos,
            'programme' => 'JSPA', 'programme_label' => 'Jaunatnes starptautisko programmu aģentūra',
            'opening_date' => $d1, 'deadline' => $dl, 'budget' => gr_lv_summa($t),
            'url' => $u, 'nozare' => gr_lv_nozare($nos, 'izglitiba'),
            'keywords' => 'jaunatne, projektu konkurss', 'submit_url' => null,
        ];
    }
    gr_lv_log($log, 'JSPA: ' . count($out) . ' konkursi (no ' . count($urls) . ' pārbaudītām lapām).');
    return $out;
}


// ── LAD ──────────────────────────────────────────────────────────────────────
/**
 * LAD atbalsta veidu KATALOGS (/lv/katalogs) — nevis "pieteikšanās termiņu" lapa.
 *
 * KĀPĒC TIEŠI ŠIS. Sākumā par avotu biju izvēlējies
 * /lv/pieteiksanas-termini-uz-pasakumu-elfla-2023-2027, jo tur ir 1280 datumu. Atverot to
 * pārlūkā, izrādījās, ka 776 no tiem ir dokumentu zīmogi ("Pievienots: …") un lapa pati ir
 * LEADER vietējo rīcības grupu saraksts ar stratēģiju PDF, nevis kārtu saraksts. Datumu
 * skaits mērīja manu vārtu, ne saturu.
 *
 * Katalogs turpretī ir īsts klasifikators: katrai kartītei ir Statuss ("Kārta atvērta",
 * "Kārta izsludināta", "Kārta noslēgusies"), un atvērtā ieraksta lapā ir kārtas datumu
 * intervāls, pieejamais finansējums, atbalsta saņēmēja veids un nozare. Tāpēc sarakstu
 * izlasām vienā pieprasījumā un ieraksta lapu velkam TIKAI tām kārtām, kas ir dzīvas.
 */
function gr_lv_lad(?callable $log = null, bool $arMi = false): array {
    // 539 = Projekti un investīcijas, 533 = Valsts atbalsts. Platību maksājumi (380) un
    // pastāvīgie pakalpojumi šeit neder: tie nav konkursi ar kārtu.
    $tipi = [539 => 'Projekti un investīcijas', 533 => 'Valsts atbalsts'];
    $kartites = [];
    foreach ($tipi as $tips => $nos) {
      // LAPOŠANA. Katalogs vienā lapā rāda 20 kartītes no 101, un bez tās mums bija tikai
      // pirmās divdesmit — t.i. tieši tās kārtas, kuras LAD pašlaik liek augšā. Ejam, kamēr
      // lapa vairs nedod jaunas kartītes; 12 lapu griesti ir drošinātājs pret bezgalīgu cilpu.
      $piretipa = 0;   // šī satura tipa kartītes; globāls skaitītājs pārtrauktu otro tipu uzreiz
      for ($lapa = 0; $lapa < 12; $lapa++) {
        $g = gr_iev_get("https://www.lad.gov.lv/lv/katalogs?progra_catalog_vid_1%5B$tips%5D=$tips&page=$lapa", 30);
        if (!$g['ok']) { gr_lv_log($log, "  ! LAD katalogs ($nos, lapa $lapa): {$g['kapec']}"); break; }
        $pirms = count($kartites); $pirmsTipa = $piretipa;
        // Katra kartīte ir <div class="views-row"> ar h3 > a un lauku pāriem
        // <div class="field-label">Statuss</div><div><span>Kārta atvērta</span></div>.
        foreach (preg_split('~<div class="views-row">~', $g['body']) as $bloks) {
            if (!preg_match('~<h3>\s*<a href="(/lv/katalogs/[^"]+)"[^>]*>(.*?)</a>~is', $bloks, $m)) continue;
            $st = preg_match('~field-label">\s*Statuss\s*</div>\s*<div>\s*<span>\s*([^<]+)~is', $bloks, $sm)
                ? trim($sm[1]) : '';
            $kartites[$m[1]] = ['nos' => gr_lv_teksts($m[2]), 'statuss' => $st, 'tips' => $nos];
            $piretipa++;
        }
        // Pārtraucam, ja ŠĪ LAPA nedeva nevienu kartīti. Agrāk salīdzinājām kopējo skaitu,
        // tāpēc otrais satura tips, kura pirmā lapa pārklājas ar jau savākto, apstājās uzreiz
        // un visa "Valsts atbalsts" kategorija pazuda.
        if ($piretipa === $pirmsTipa) break;
      }
    }
    $out = []; $dzivas = 0; $izmesti = []; $bezTermina = [];
    foreach ($kartites as $cels => $k) {
        // Tikai dzīvās kārtas. "Kārta noslēgusies" ir vēsture, un to LAD tur pašā katalogā —
        // mums tā būtu 60 tukšu rindu, kuru termiņš jau sen pagājis.
        if (!preg_match('~kārta (atvērta|izsludināta)~ui', $k['statuss'])) continue;
        $dzivas++;
        $u = 'https://www.lad.gov.lv' . $cels;
        $p = gr_iev_get($u, 25);
        if (!$p['ok']) continue;
        $t = gr_lv_teksts($p['body']);
        // KĀRTAS INTERVĀLS = TUVĀKAIS NEPAGĀJUŠAIS NO VISIEM LAPĀ (2026-09-17 audits). LAD lapa uzskaita kārtu vēsturi
        // hronoloģiski, un "pirmais intervāls" bija vecākā kārta; formas "no D līdz D" un datumi vārdiem netika lasīti vispār.
        $karta = gr_lv_lad_karta($t, date('Y-m-d'));
        if ($karta === null) { $bezTermina[] = $k['nos']; continue; }   // bez termiņa ieraksts šai sadaļai neder
        $d1 = $karta['no']; $d2 = $karta['lidz'];
        // ── GRANTS VAI NE ───────────────────────────────────────────────────────
        // Šķīrējs ir kopīgs visiem avotiem (lib/grantu_filtrs.php) un mērīts pret 86
        // marķētiem ierakstiem: uz izšķirtajiem 100 %, izšķir 87 %. Katalogā "Projekti un
        // investīcijas" līdzās grantiem dzīvo iepirkumi (LAD pērk konsultācijas) un likmju
        // shēmas (piens skolai) — abus filtrs atpazīst pēc lapas pazīmēm, ne pēc nosaukuma.
        $sat = ($__p = mb_strpos($t, 'Publicēts:')) !== false ? mb_substr($t, $__p) : $t;
        $fv = gr_filtrs_pilns(['avots' => 'LAD', 'nosaukums' => $k['nos'], 'teksts' => $sat,
                               'termins' => $d2], $arMi, $log);
        if ($fv['verdikts'] !== 'grants') {
            $izmesti[] = $k['nos'] . ' (' . $fv['veids'] . ': ' . mb_substr($fv['skaidrojums'], 0, 60) . ')';
            continue;
        }
        $bud = $karta['budzets'];
        $sanem = preg_match('~Atbalsta saņēmējs\s*(.{3,120}?)\s*(?:Iesniegšanas veids|Kārta|Nozare|Pieejamais)~ui', $t, $sm)
            ? trim($sm[1]) : '';
        $nozTeksts = preg_match('~Nozare\s*(.{3,90}?)\s*(?:Atbalsta saņēmējs|Iesniegšanas veids|Kārta)~ui', $t, $nm)
            ? trim($nm[1]) : '';
        $pretendents = preg_match('~Atbalsta pretendent[si]?[^.]{5,150}~ui', $t, $pm)
            ? trim(preg_replace('~\s+~u', ' ', $pm[0])) : '';
        $out[] = [
            'id' => 'LAD:' . gr_lv_id_dala(basename($cels)), 'source' => 'LAD',
            // Statusu izšķir datumi, ne vārds: lapa to tāpat pārrēķina katrā pieprasījumā.
            'status' => ($d1 !== null && $d1 > date('Y-m-d')) ? 'forthcoming' : 'open',
            'title' => $k['nos'],
            'programme' => 'LAD', 'programme_label' => 'Lauku atbalsta dienests',
            'opening_date' => $d1, 'deadline' => $d2, 'budget' => $bud, 'url' => $u,
            'nozare' => gr_lv_nozare($k['nos'] . ' ' . $nozTeksts, 'lauksaimnieciba'),
            // Atbalsta saņēmēja veids ir vienīgais, kas cilvēkam pasaka, vai kārta attiecas
            // uz viņu ("Privātpersona", "Zemnieku saimniecība"), tāpēc tas iet meklējamā ailē.
            // Pretendentu loku ņem no sadaļas "Atbalsta pretendents", ja tāda ir: galvenes
            // lauks mēdz melot (LA 16 tur raksta "Eiropas Inovāciju partnerība", kas nav
            // juridiska forma, un tirdzniecības veicināšanā tas noklusē ražotājus).
            'keywords' => trim(($pretendents !== '' ? $pretendents . '; ' : '') . $sanem
                             . ($nozTeksts !== '' ? '; ' . $nozTeksts : '')),
            'submit_url' => 'https://eps.lad.gov.lv/login',
        ];
    }
    gr_lv_log($log, 'LAD: ' . count($out) . ' grantu kārtas (dzīvas ' . $dzivas . ', katalogā ' . count($kartites) . ').');
    // Klusi izmesti ieraksti ir sliktāki par skaļi izmestiem: bez šīs rindas neviens nepamana,
    // ka filtrs kļuvis par platu un sāk ēst īstus konkursus.
    if ($izmesti) gr_lv_log($log, '  · nav granti: ' . implode('; ', array_slice($izmesti, 0, 6))
        . (count($izmesti) > 6 ? ' …(' . count($izmesti) . ')' : ''));
    // Dzīva kartīte bez nolasāma kārtas intervāla agrāk pazuda bez pēdām (2026-09-17 audits: 29 no 52).
    if ($bezTermina) gr_lv_log($log, '  · bez nolasāma kārtas intervāla (' . count($bezTermina) . '): '
        . implode('; ', array_map(fn($x) => mb_substr($x, 0, 40), array_slice($bezTermina, 0, 5))) . (count($bezTermina) > 5 ? ' …' : ''));
    return $out;
}

/**
 * LAD kataloga lapas KĀRTA no teksta: tuvākais nepagājušais intervāls un TĀS kārtas budžets. Tīra funkcija — testē uz keša.
 * BUDŽETS PIE IZVĒLĒTĀS KĀRTAS: LA 16 lapā 6. kārtai ir 5 000 000 EUR un 7. kārtai 992 217 EUR. Vispirms etiķete tieši aiz
 * izvēlētā intervāla, tad vēstures tabulas "Paziņojums N"; lapas pirmo etiķeti ņem tikai tad, ja izvēlēts ir lapas pirmais
 * intervāls — citādi tā pieder citai kārtai, un tukšums ir godīgāks par svešu summu.
 * @return ?array{no:string,lidz:string,budzets:?float}
 */
function gr_lv_lad_karta(string $t, string $sodien): ?array {
    // TIKAI PIETEIKŠANĀS KĀRTAS (recenzents 2026-09-17). Lapā ir arī intervāli, kas nav kārtas: "par laikposmu no 01.07.2025.
    // līdz 30.06.2026." (LA 4.1.6 atskaites periods — jūnijā tas uzvarētu īsto "Pieteikšanās atvērta 01.07.–31.07.") un
    // "Projekta īstenošanas termiņš: 01.01.2027.–31.12.2028.". Intervālu patur, ja ~120 baitos pirms tā ir kārtas enkurs
    // (kārta / atvērta / izsludināta / pieteikšanās / pieņemšana / iesniegšana) un tieši pirms tā (~70 baiti) nav perioda vārda.
    $iv = array_values(array_filter(gr_lv_intervali($t), static function (array $i) use ($t): bool {
        $pirms = mb_strcut($t, max(0, $i['pos'] - 120), min(120, $i['pos']), 'UTF-8');
        // "Tieši pirms" = līdz iepriekšējam datumam vai teikuma beigām, ne visi 70 baiti: citādi iepriekšējā teikuma "par laikposmu
        // no … līdz 30.06.2026." izmestu arī īsto kārtu, kas seko uzreiz aiz tā ("Pieteikšanās atvērta 01.07.2026.-31.07.2026.").
        $gab = preg_split('~\d{2,4}\.?\s+|[.;!?]\s+(?=\p{Lu})~u', mb_strcut($t, max(0, $i['pos'] - 70), min(70, $i['pos']), 'UTF-8')) ?: [''];
        $tiesi = (string)end($gab);
        return preg_match('~kārt\w*|atvērt\w*|izsludināt\w*|pieteikšan\w*|pieņemšan\w*|iesniegšan\w*~ui', $pirms) === 1
            // "maksājumu pieteikumiem par 2025.gadu) 1. kārta atvērta 01.09.–30.09.2026." (Valsts Zivju fonds, 2000 EUR) ir jau
            // apstiprinātu saņēmēju MAKSĀJUMU kārta, ne projektu uzsaukums: rinda "atvērts līdz 30.09." solītu to, kā nav.
            && preg_match('~laikposm\w*|\bperiod\w*|īstenošan\w*|\bilgum\w*|\batskait\w*|maksājum\w*\s+pieteikum\w*~ui', $tiesi) !== 1;
    }));
    if (!$iv) return null;
    $izv = gr_lv_tuvakais_intervals($iv, $sodien);
    if ($izv === null) return null;
    $skaitlis = '(\d{1,3}(?:[ \x{00A0}]\d{3})+|\d{4,})';
    $aiz = mb_strcut($t, $izv['beigas'], 160, 'UTF-8');
    $bud = null;
    if (preg_match('~^[.\s]*Pieejamais (?:publiskais )?finansējums\s*:?\s*' . $skaitlis . '\s*EUR~ui', $aiz, $bm)
        || preg_match('~^[.\s]*Paziņojums\s*' . $skaitlis . '(?![\d.])~ui', $aiz, $bm)
        || ($izv['pos'] === $iv[0]['pos'] && preg_match('~Pieejamais finansējums\s*' . $skaitlis . '\s*EUR~ui', $t, $bm)))
        $bud = (float)str_replace([' ', "\u{00A0}"], '', $bm[1]);
    return ['no' => $izv['no'], 'lidz' => $izv['lidz'], 'budzets' => $bud];
}

/** Visi LV avoti vienā sarakstā. Viena avota kļūme neaptur pārējos. */
function gr_lv_savac(?callable $log = null, int $jspaMaks = 60, bool $arMi = false): array {
    $visi = [];
    foreach (['gr_lv_cfla', 'gr_lv_vkkf', 'gr_lv_liaa', 'gr_lv_lad', 'gr_lv_lzp', 'gr_lv_riga'] as $fn) {
        try { $visi = array_merge($visi, $fn($log, $arMi)); }
        catch (Throwable $e) { gr_lv_log($log, "  ! $fn: " . $e->getMessage()); }
    }
    // VIAA (Erasmus+ nacionālā daļa) un NVA: tā pati valsts Drupal ziņu plūsma kā LZP.
    // Grupas kods VALSTS, lai avotu izvēlne nesadrumstalotos; iestāde ir programmas ailē.
    foreach ([['www.viaa.gov.lv', 'Valsts izglītības attīstības aģentūra', 'izglitiba'],
              ['www.nva.gov.lv', 'Nodarbinātības valsts aģentūra', 'sociala']] as [$h, $nosI, $noz]) {
        try { $visi = array_merge($visi, gr_lv_zinu_avots('VALSTS', $h, $nosI, $noz, $log, $arMi)); }
        catch (Throwable $e) { gr_lv_log($log, "  ! $h: " . $e->getMessage()); }
    }
    try { $visi = array_merge($visi, gr_lv_jspa($log, $jspaMaks)); }
    catch (Throwable $e) { gr_lv_log($log, '  ! gr_lv_jspa: ' . $e->getMessage()); }
    return $visi;
}

// ── LZP (un citi valsts Drupal avoti, kur konkurss ir ZIŅA) ──────────────────
/**
 * Vai ziņas virsraksts IZSLUDINA konkursu?
 *
 * Šķiro darbības vārds, ne tēma: "izsludina konkursu" un "aicina pieteikties" ir uzsaukums,
 * bet "konkursa rezultāti", "noslēgusies atlase" un "vebinārs par konkursu" ir ziņa PAR
 * konkursu. Bez šī šķīruma puse sadaļas būtu vēsture un pasākumu afišas.
 */
function gr_lv_zina_ir_uzsaukums(string $v): bool {
    $v = mb_strtolower($v);
    foreach (['rezultāt', 'noslēgusies', 'noslēdzies', 'apstiprināt', 'piešķirt', 'vebinār', 'seminār',
              'tiešraide', 'godin', 'izvērtēšan', 'pagarināts termiņš', 'atgādina'] as $p)
        if (str_contains($v, $p)) return false;
    foreach (['izsludina', 'izsludināt', 'aicina pieteikties', 'var pieteikties', 'atvērts konkurss',
              'atverts konkurss', 'pieņem pieteikumus', 'sāk pieteikšanos', 'atvērta pieteikšanās'] as $p)
        if (str_contains($v, $p)) return true;
    return false;
}

/**
 * Vienotās valsts Drupal platformas ziņu plūsma kā avots.
 *
 * Šīm vietnēm (LZP, un tāda pati uzbūve ir CFLA, NVA, VIAA) konkursu saraksta lapas NAV —
 * uzsaukums ir ziņu raksts. Teksts NAV raksta laukos, bet Paragraphs vienībās, tāpēc prasa
 * ?include=field_article_content un lasa included[].field_text_content.value; bez tā
 * izvilkšana mēra tikai virsrakstus (mērīts: 5 % pret 71 %).
 *
 * Termiņu ņem TIKAI ar tuvuma vārtu (gr_lv_terminš), un pagājušos izmet: ziņu plūsmā ir
 * arī 2021. gada uzsaukumi, un sadaļa nav arhīvs.
 */
function gr_lv_zinu_avots(string $avots, string $hosts, string $programme, string $nozare,
                          ?callable $log = null, bool $arMi = false, int $limits = 50): array {
    $url = "https://$hosts/jsonapi/node/article?page%5Blimit%5D=$limits&sort=-created&include=field_article_content";
    $g = gr_iev_get($url, 40);
    if (!$g['ok']) { gr_lv_log($log, "  ! $avots: {$g['kapec']}"); return []; }
    $j = json_decode($g['body'], true);
    // Veiksmes atzīme pa hostiem: būve pēc tās izšķir, vai VIAA/NVA vecās rindas drīkst
    // aizstāt ar šīs nakts rezultātu. Bez tās plūsmas kļūme klusi izdzēsa rindas.
    if (is_array($j['data'] ?? null)) $GLOBALS['gr_lv_zinas_ok'][$hosts] = true;
    $pgr = [];
    foreach (($j['included'] ?? []) as $inc)
        foreach (($inc['attributes'] ?? []) as $k => $v)
            if (is_array($v) && isset($v['value']) && str_contains($k, 'text'))
                $pgr[(string)$inc['id']] = ($pgr[(string)$inc['id']] ?? '') . ' ' . (string)$v['value'];

    $sodien = date('Y-m-d');
    $out = []; $uzsaukumi = 0; $bezTermina = 0;
    foreach (($j['data'] ?? []) as $r) {
        $a = $r['attributes'] ?? [];
        $nos = trim((string)($a['title'] ?? ''));
        if ($nos === '' || !gr_lv_zina_ir_uzsaukums($nos)) continue;
        $uzsaukumi++;
        $saturs = '';
        foreach (($r['relationships']['field_article_content']['data'] ?? []) as $ref)
            $saturs .= ' ' . ($pgr[(string)($ref['id'] ?? '')] ?? '');
        $teksts = $nos . ' ' . gr_lv_teksts($saturs);
        // ES PORTĀLA UZSAUKUMUS NEDUBLĒ. Nacionālais kontaktpunkts par tiem raksta ziņas
        // ("Atvērta pieteikšanās EIC Pathfinder"), bet tie paši konkursi jau ir sadaļā no
        // SEDIA plūsmas ar budžetu, sadaļām un pieteikšanās saiti — divi ieraksti par vienu
        // konkursu ir sliktāki par vienu labu.
        // Zīmola vārdi ir otrā puse tai pašai kārtulai: "EIC Pathfinder", "Apvārsnis Eiropa"
        // un ERC/MSCA konkursi VISI nāk no SEDIA plūsmas, lai kurš par tiem raksta ziņu.
        if (preg_match('~ec\.europa\.eu/info/funding-tenders|funding-tenders/opportunities~i', $saturs)
            || preg_match('~\b(EIC|ERC|MSCA|Apvārsnis Eiropa|Horizon Europe)\b~u', $nos)) {
            gr_lv_log($log, '  · ' . $avots . ': izlaists ES portāla uzsaukums — ' . mb_substr($nos, 0, 45));
            continue;
        }
        $ivZ = gr_lv_termina_intervals($teksts, substr((string)($a['created'] ?? ''), 0, 10) ?: null);
        $dl = $ivZ['lidz'];
        // Bez termiņa vai ar pagājušu termiņu ierakstam sadaļā nav vietas: te statusu
        // nerēķina no ziņas datuma, jo ziņa var būt vecāka par gadu.
        if ($dl === null || $dl < $sodien) { $bezTermina++; continue; }
        $fv = gr_filtrs_pilns(['avots' => $avots, 'nosaukums' => $nos, 'teksts' => $teksts,
                               'termins' => $dl], $arMi, $log);
        if ($fv['verdikts'] !== 'grants') continue;
        $alias = (string)($a['path']['alias'] ?? '');
        // Identifikators ar hostu un mezgla numuru: VIAA un NVA abi raksta source=VALSTS, un
        // ar tikai adreses beigām divu iestāžu ziņas ar vienādu aliasu sadurtos. Ja aliasa
        // nav, basename() uz virsraksta dotu "LZP:inovāciju" — tāpēc te numurs, ne teksts.
        $out[] = [
            'id' => $avots . ':' . substr(preg_replace('~^www\.~', '', $hosts), 0, 20) . ':'
                  . (string)($a['drupal_internal__nid'] ?? substr(md5($nos), 0, 10)),
            'source' => $avots, 'status' => ($ivZ['no'] !== null && $ivZ['no'] > $sodien) ? 'forthcoming' : 'open', 'title' => $nos,
            'programme' => $avots, 'programme_label' => $programme,
            'opening_date' => $ivZ['no'], 'deadline' => $dl, 'budget' => gr_lv_summa($teksts),
            'url' => 'https://' . $hosts . ($alias !== '' ? $alias : '/lv'),
            'nozare' => gr_lv_nozare($nos, $nozare), 'keywords' => '', 'submit_url' => null,
            // Hosts rotācijas pārnesei (sk. build.php "Vietņu rindu pārnese pa hostiem").
            'division' => $hosts,
        ];
    }
    gr_lv_log($log, "$avots: " . count($out) . " uzsaukumi ar dzīvu termiņu (ziņās atrasti $uzsaukumi, bez derīga termiņa $bezTermina).");
    return $out;
}

/** LZP zinātnes konkursi no ziņu plūsmas. */
function gr_lv_lzp(?callable $log = null, bool $arMi = false): array {
    return gr_lv_zinu_avots('LZP', 'www.lzp.gov.lv', 'Latvijas Zinātnes padome', 'zinatne', $log, $arMi);
}

// ── Rīgas pašvaldība ─────────────────────────────────────────────────────────
/**
 * Rīgas domes finansēšanas konkursi (kultura.riga.lv četras nozaru sadaļas).
 *
 * Tabula ir tīra: Nosaukums | Joma | Pieteikšanās termiņš | Norises laiks | Statuss.
 * Beigušos konkursu termiņa ailē ir vārds "Beidzies", nevis datums — tieši tas ir šķīrējs,
 * un tas nozīmē, ka dzīvos var atlasīt bez nevienas papildu lapas.
 */
function gr_lv_riga(?callable $log = null, bool $arMi = false): array {
    $sadalas = ['kultura' => 'kultura', 'sports' => 'izglitiba',
                'izglitiba' => 'izglitiba', 'rd-iksd' => 'kultura'];
    $out = []; $rindas = 0; $redzeti = []; $unikalas = []; $tuksasPecKartas = 0;
    foreach ($sadalas as $sl => $noz) {
      // LAPOŠANA. Tabulā ir 20 rindu, un sadaļai ir ?page=0..4 — bez tām jebkurš dzīvs
      // konkurss zem 20. rindas ir strukturāli neredzams. Ejam, kamēr lapa dod rindas.
      // Griesti 15, ne 6: sporta un izglītības sadaļā lapotājs 5. lapā rāda saiti uz 6. (2026-09-17 audits), un cilpu tāpat
      // aptur pirmā lapa bez rindām. Unikālo rindu skaits žurnālā rāda, ja vietnes lapas pārklājas.
      for ($lapa = 0; $lapa < 15; $lapa++) {
        $g = gr_iev_get("https://kultura.riga.lv/lv/$sl/finansesanas-konkursi" . ($lapa ? "?page=$lapa" : ''), 25);
        if (!$g['ok']) { gr_lv_log($log, "  ! Rīga/$sl (lapa $lapa): {$g['kapec']}"); break; }
        $pirmsR = $rindas; $pirmsU = count($unikalas);
        if ($lapa === 0) $tuksasPecKartas = 0;   // jauna sadaļa — skaitītājs no nulles
        foreach (gr_lv_tabulas($g['body']) as $tab) {
            foreach ($tab as $r) {
                if (count($r) < 3) continue;
                $rindas++;
                $unikalas[md5($r[0]['t'] . '|' . $r[2]['t'])] = true;
                $nos = $r[0]['t'];
                $dl = gr_lv_dat($r[2]['t']);          // "Beidzies" -> null
                if ($nos === '' || $dl === null || $dl < date('Y-m-d')) continue;
                // Viens un tas pats konkurss stāv vairākās nozaru sadaļās (izglītība un
                // rd-iksd rāda to pašu rindu) — otro reizi lapu vairs neprasām.
                $atsl = md5($nos);
                if (isset($redzeti[$atsl])) continue;
                $redzeti[$atsl] = true;
                $saite = gr_lv_abs($r[0]['href'], 'https://kultura.riga.lv');
                // KONKURSA LAPU VELK TIKAI DZĪVAJIEM. Tabulā ir tikai nosaukums un joma, un
                // ar to filtram nepietiek: pirmajā redakcijā vienīgais atvērtais Rīgas
                // konkurss ("Skola – kopienā", līdz 5000 EUR) tika izmests, jo šķīrējs
                // neredzēja ne summu, ne pieteikšanās kārtību. Beigušajiem lapu neprasām —
                // tās ir 80 rindas, kuras sadaļā tāpat nenonāks.
                $lapasT = $nos . ' ' . ($r[1]['t'] ?? '');
                $summa = null;
                if ($saite !== null) {
                    $lp = gr_iev_get($saite, 25);
                    if ($lp['ok']) {
                        $lt = gr_lv_teksts($lp['body']);
                        $lapasT .= ' ' . mb_substr($lt, 0, 6000);
                        $summa = gr_lv_summa($lt);
                    }
                }
                $fv = gr_filtrs_pilns(['avots' => 'RIGA', 'nosaukums' => $nos,
                    'teksts' => $lapasT, 'termins' => $dl], $arMi, $log);
                if ($fv['verdikts'] !== 'grants') { gr_lv_log($log, "  · Rīga izlaists: " . mb_substr($nos, 0, 40) . ' (' . $fv['veids'] . ')'); continue; }
                $out[] = [
                    'id' => 'RIGA:' . substr(md5($nos), 0, 12), 'source' => 'RIGA',
                    'status' => 'open', 'title' => 'Rīgas pašvaldība: ' . $nos,
                    'programme' => 'RIGA', 'programme_label' => 'Rīgas valstspilsētas pašvaldība',
                    'opening_date' => null, 'deadline' => $dl, 'budget' => $summa,
                    'url' => $saite ?? "https://kultura.riga.lv/lv/$sl/finansesanas-konkursi",
                    'nozare' => gr_lv_nozare($nos . ' ' . ($r[1]['t'] ?? ''), $noz),
                    'keywords' => trim((string)($r[1]['t'] ?? '')), 'submit_url' => null,
                ];
            }
        }
        // Apstājas pēc DIVĀM lapām pēc kārtas bez nevienas JAUNAS rindas. Vietne ārpus diapazona atdod to pašu pēdējo lapu
        // (2026-09-17: ar griestiem 15 tas bija 714 rindas, 210 unikālas, ~35 lieki pieprasījumi), tāpēc "lapa bez rindām" te
        // neiestājas nekad. Viena lapa bez jaunām rindām vēl NAV beigas: lapošana ir nestabila, un 1. lapa mēdz atkārtot 0. lapu,
        // kamēr 2. lapā ir jaunas rindas; un sadaļas pārklājas savā starpā (izglītība un rd-iksd).
        if ($rindas === $pirmsR) break;
        $tuksasPecKartas = count($unikalas) === $pirmsU ? $tuksasPecKartas + 1 : 0;
        if ($tuksasPecKartas >= 2) break;
      }
    }
    gr_lv_log($log, 'Rīga: ' . count($out) . ' atvērti konkursi (tabulās ' . $rindas . ' rindas, unikālas ' . count($unikalas) . ').');
    return $out;
}
