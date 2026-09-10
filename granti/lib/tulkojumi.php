<?php
/**
 * granti/lib/tulkojumi.php — grantu tekstu TULKOJUMU KEŠS un tulkotājs.
 *
 * KĀPĒC ATSEVIŠĶA DATUBĀZE. granti_build.php raksta TUKŠĀ pagaidu failā un beigās to
 * atomāri pārceļ pāri dzīvajai DB (sk. "atomāra publicēšana" būves beigās). Ja tulkojumi
 * dzīvotu grants tabulas kolonnās, KATRA būve tos iznīcinātu un nākamā tulkošana no jauna
 * samaksātu par to pašu tekstu — 1,12 € katrā palaišanā, nevis vienreiz. Tāpēc kešs ir
 * PATSTĀVĪGS fails blakus, ko būve nekad nepārraksta; grants kolonnas lv_* ir tikai
 * IZVILKUMS no keša, ko pēc katras būves atjauno gr_tulk_aizpildi().
 *
 * KĀPĒC ATSLĒGA IR SATURA JAUCĒJKODS, NE GRANTA ID. Trīs iemesli, visi izmērīti:
 *   1) 636 tēmām ir tikai 174 atšķirīgi konkursa nosaukumi (callTitle) un 1379 atšķirīgas
 *      sadaļas no 1621 — pa ID tulkotu 242 sadaļas lieki.
 *   2) Ja ES portāls tēmas tekstu izlabo, jaucējkods mainās un pārtulko TIKAI to gabalu.
 *   3) Ja tēma pazūd un pēc mēneša atgriežas, teksts kešā jau ir — otrreiz nemaksājam.
 *
 * DIVKĀRŠAS TULKOŠANAS SARGI (visi vajadzīgi, katrs sedz citu ceļu):
 *   · PRIMARY KEY(hash) + INSERT ... ON CONFLICT DO UPDATE — rinda nekad nedublējas, un
 *     atkārtota ielikšana rindā NENODZĒŠ jau esošu tulkojumu vai kļūmju skaitītāju.
 *   · neizdevas < GR_TULK_MAX_KLUMES — teksts, kas trīs reizes nav izturējis pārbaudi,
 *     vairs netiek sūtīts; lapa tam rāda oriģinālu. Bez šī tas maksātu katrā palaišanā.
 *   · flock() uz slēdzenes faila — divas paralēlas palaišanas citādi tulkotu vienu un to
 *     pašu rindu vienlaikus (kešs vēl tukšs, abi redz to pašu darbu).
 *   · Dienas griesti eiro — pat ar kļūdu kodā vairāk par GR_TULK_DIENAS_EUR neiztērē.
 *
 * DIVAS VERSIJAS, KATRA AR SAVU NOZĪMI:
 *   · GR_TULK_VERSIJA iet JAUCĒJKODĀ. Tās maiņa nozīmē, ka neviens vecais tulkojums vairs
 *     neder un viss korpuss tiek pirkts no jauna (mērīts: 2,00 €). Tas ir APZINĀTS solis.
 *   · GR_TULK_UZVEDNE iet tikai rindas KOLONNĀ. Uzvednes uzlabojums (glosārijs, modalitāte)
 *     jau iztulkoto NEinvalidē: vecie teksti paliek ar veco uzvedni, jaunie nāk ar jauno,
 *     un kolonna rāda, kurš ar kuru. Tāds bija operatora lēmums 2026-09-09 — nemaksāt
 *     par jau lietojamu tulkojumu otrreiz, bet turpmāk tulkot labāk.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../registrs/mi/gemini_client.php';
// gr_sanitize_html() — modeļa izvadi attīra TIEŠI TĀPAT kā oriģinālu (sk. pārbaudes vārtus).
require_once __DIR__ . '/teksts.php';

/**
 * KEŠA versija (iet jaucējkodā). Maiņa = pilna pārtulkošana par pilnu cenu, UN lapa uz
 * laiku atgriežas angļu valodā, jo jaunajiem jaucējkodiem tulkojuma vēl nav — atkāpes uz
 * iepriekšējo versiju gr_tulk_aizpildi() NAV. Tāpēc uzvednes uzlabojumiem ir sava versija
 * zemāk, kas kešu neaiztiek.
 * v1 -> v2 (2026-09-08): "akronīmus atstāj oriģinālā" modelis attiecināja arī uz
 * vispārīgiem vārdiem — "Standalone projects" kļuva par "Standalone projekti".
 */
const GR_TULK_VERSIJA = 'v2';
/**
 * UZVEDNES versija — rakstās rindā (kolonna uzvedne), jaucējkodā NEIET.
 * v3 (2026-09-09): glosārijs + modalitātes kārtula + četras biežākās nozīmes kļūdas pēc
 * aklās vērtēšanas (nozīme 7,5/10, valoda 7/10; "labuma guvējs" 104×, SME netulkots 226×,
 * "tiek veicināts" 118×). Esošie 2123 v2 tulkojumi paliek — pārtulkot nolemts NE.
 * Sadalījumu pa uzvednēm rāda `granti_tulko.php --statuss`.
 */
const GR_TULK_UZVEDNE = 'v3';
/**
 * Uzvednes pieskaitījums tokenos uz VIENU pieprasījumu (bez paša teksta), mērīts ar
 * countTokens 2026-09-09 pēc glosārija ielikšanas (v2 bija 161). Lieto aplēsēm. Uz visu
 * korpusu tas ir ~0,28 € virsū, jauniem grantiem — sīkums.
 * Dūmu tests uz 24 izlases tekstiem (21 unikālas rindas): 21/21 izturēja vārtus, 0,022 €;
 * beneficiary 3/3 -> "finansējuma saņēmējs", SME 2/2 -> MVU, call 8/8 -> uzsaukums,
 * disruptive 4/4 -> pārveidojošs, encouraged 7/9 -> "ir vēlams", Annex 5/7 -> pielikums.
 */
const GR_TULK_UZVEDNES_TOKENI = ['html' => 709, 'virsraksts' => 414];
/** Modelis, par kuru ir cenu konstantes zemāk. Cits modelis => brīdinājums žurnālā. */
const GR_TULK_MODELIS = 'gemini-3.1-flash-lite';
/** Cenas $/1M tokenu. Avots: konkursi/lib/config.php (tas pats modelis, tā pati atslēga). */
const GR_TULK_IN_USD_1M  = 0.25;
const GR_TULK_OUT_USD_1M = 1.50;
const GR_TULK_USD_EUR    = 0.95;   // apzināti konservatīvs kurss budžetam
/** Dienas griesti. Viss korpuss (3,9 milj. rakstz.) tūlītējā ceļā maksā ~2,25 €. */
const GR_TULK_DIENAS_EUR = 2.50;
/**
 * VIENAS PALAIŠANAS griesti. Dienas skaitītājs ir piesiets kalendāra datumam, tāpēc plkst.
 * 23:50 sākta palaišana pusnaktī dabū svaigus dienas griestus un var iztērēt divkārt, operatoram
 * neko nesakot. Šie griesti nav atkarīgi no pulksteņa.
 */
const GR_TULK_PALAISANAS_EUR = 3.00;
/** Cik reižu mēģināt vienu tekstu, pirms to atstāj angliski. */
const GR_TULK_MAX_KLUMES = 3;
/** Cik pieprasījumu vienlaikus (curl_multi). */
const GR_TULK_PARALELI = 4;
/** Garākus tekstus par šo nesūta — izvade pārsniegtu maxOutputTokens. */
const GR_TULK_MAX_RAKSTZ = 60000;

/** Keša DB ceļš (blakus granti.sqlite, BET būve to nekad nepārraksta). */
function gr_tulk_db_path(): string { return granti_tulk_db_path(); }

/** Atver (un pirmajā reizē izveido) keša DB. */
function gr_tulk_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $p = gr_tulk_db_path();
    if (!is_dir(dirname($p))) @mkdir(dirname($p), 0775, true);
    // Statisko piešķir TIKAI beigās. Ja shēmas izveide krīt (tikai lasāms fails, pilns disks),
    // pusceļā piešķirts static atdotu DB bez meta tabulas, un budžeta sargs vēlāk krastu ar
    // "no such table: meta" — kļūda parādītos kā pavisam cita problēma.
    $jauns = new PDO('sqlite:' . $p);
    $pdo = $jauns;
    $pdo = null;                     // līdz shēma ir gatava, static paliek tukšs
    $jauns->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $jauns->exec("PRAGMA journal_mode=WAL");
    $jauns->exec("PRAGMA busy_timeout=10000");
    $jauns->exec("CREATE TABLE IF NOT EXISTS tulkojumi (
        hash TEXT PRIMARY KEY,
        veids TEXT NOT NULL,          -- html | virsraksts
        lauks TEXT,                   -- informatīvi: scope, outcome, title, call_title...
        avots TEXT NOT NULL,          -- oriģināls (lai var pārbaudīt un pārtulkot)
        lv TEXT,                      -- NULL = vēl rindā
        modelis TEXT,
        neizdevas INTEGER DEFAULT 0, kluda TEXT,
        pievienots TEXT NOT NULL, iztulkots TEXT, pedejo_reizi TEXT,
        versija TEXT,
        uzvedne TEXT                  -- ar kuru uzvednes versiju lv iegūts (GR_TULK_UZVEDNE)
    )");
    // Vecākas DB migrācija: kolonna pievienota 2026-09-08 (sk. zemāk, kāpēc tā vajadzīga).
    $cols = array_column($jauns->query("PRAGMA table_info(tulkojumi)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('versija', $cols, true)) $jauns->exec("ALTER TABLE tulkojumi ADD COLUMN versija TEXT");
    // KĀPĒC VERSIJA IR ATSEVIŠĶA KOLONNA, KAUT TĀ JAU IR JAUCĒJKODĀ. Bez tās GR_TULK_VERSIJA
    // maiņa atstāj vecās versijas rindas darba rindā (lv IS NULL) uz visiem laikiem, un
    // nākamā tulkošana par tām samaksā vēlreiz — ar veco, tieši nomainīto uzvedni.
    // Mērīts: v1 -> v2 maiņa rindu izaudzēja no 2127 uz 3762 un aplēsi no 2,19 uz 4,35 €.
    // Rindām, kas radās pirms kolonnas, versiju nosaka precīzi: ja pārrēķinātais jaucējkods
    // sakrīt ar saglabāto, rinda ir no ŠĪS versijas; ja ne — no kādas vecākas.
    $nav = $jauns->query("SELECT hash, veids, avots FROM tulkojumi WHERE versija IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    if ($nav) {
        $u = $jauns->prepare("UPDATE tulkojumi SET versija=? WHERE hash=?");
        $jauns->beginTransaction();
        foreach ($nav as $r) {
            $sava = hash('sha256', GR_TULK_VERSIJA . "\x00" . $r['veids'] . "\x00" . $r['avots']) === $r['hash'];
            $u->execute([$sava ? GR_TULK_VERSIJA : 'pirms-' . GR_TULK_VERSIJA, $r['hash']]);
        }
        $jauns->commit();
    }
    // Kolonna pievienota 2026-09-09 (glosārija uzvedne v3 bez keša invalidēšanas). Jau
    // iztulkotās rindas iegūst uzvedni VIENREIZ pēc tā laika stāvokļa: keša v2 rindas tulkoja
    // uzvedne v2, 'pirms-v2' rindas — v1. Literāļi ar nolūku, ne konstantes: tā ir vēsture.
    if (!in_array('uzvedne', $cols, true)) {
        $jauns->exec("ALTER TABLE tulkojumi ADD COLUMN uzvedne TEXT");
        $jauns->exec("UPDATE tulkojumi SET uzvedne = CASE WHEN versija = 'v2' THEN 'v2' ELSE 'v1' END
                      WHERE lv IS NOT NULL AND uzvedne IS NULL");
    }
    // CREATE INDEX IF NOT EXISTS NEAIZSTĀJ indeksu ar citu definīciju: vecajās DB idx_rinda
    // palika bez 'versija' slejas un tāpēc darba rindas vaicājumu nesedza. Jauns nosaukums ir
    // vienīgais, kas droši nostrādā arī esošajās datubāzēs.
    $jauns->exec("DROP INDEX IF EXISTS idx_rinda");
    $jauns->exec("CREATE INDEX IF NOT EXISTS idx_rinda_v2 ON tulkojumi(versija, lv, neizdevas)");
    $jauns->exec("CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)");
    $pdo = $jauns;
    return $pdo;
}

/** Satura atslēga. Versija un veids iekšā, lai uzvednes maiņa un lauku tipi nesajaucas. */
function gr_tulk_hash(string $veids, string $teksts): string {
    return hash('sha256', GR_TULK_VERSIJA . "\x00" . $veids . "\x00" . $teksts);
}

/**
 * Ieliek tekstu rindā (ja tā vēl nav). NEKAD nenodzēš esošu tulkojumu vai kļūmju
 * skaitītāju — atjauno tikai 'pedejo_reizi', lai vēlāk var iztīrīt pamestos.
 * @return string jaucējkods
 */
function gr_tulk_rinda(string $veids, string $lauks, string $teksts, ?string $zimogs = null): string {
    $h = gr_tulk_hash($veids, $teksts);
    $st = gr_tulk_db()->prepare(
        "INSERT INTO tulkojumi (hash, veids, lauks, avots, pievienots, pedejo_reizi, versija)
         VALUES (?,?,?,?,?,?,?)
         ON CONFLICT(hash) DO UPDATE SET pedejo_reizi = excluded.pedejo_reizi");
    $now = date('c');
    $st->execute([$h, $veids, $lauks, $teksts, $now, $zimogs ?? $now, GR_TULK_VERSIJA]);
    return $h;
}

/** Cik tekstu gaida rindā (un cik no tiem jau atmesti pēc kļūmēm). */
function gr_tulk_rindas_stats(): array {
    $d = gr_tulk_db();
    $v = $d->quote(GR_TULK_VERSIJA);
    return [
        'rinda'   => (int)$d->query("SELECT COUNT(*) FROM tulkojumi WHERE versija=$v AND lv IS NULL AND neizdevas < " . GR_TULK_MAX_KLUMES)->fetchColumn(),
        'atmesti' => (int)$d->query("SELECT COUNT(*) FROM tulkojumi WHERE versija=$v AND lv IS NULL AND neizdevas >= " . GR_TULK_MAX_KLUMES)->fetchColumn(),
        'gatavi'  => (int)$d->query("SELECT COUNT(*) FROM tulkojumi WHERE versija=$v AND lv IS NOT NULL")->fetchColumn(),
        'vecas'   => (int)$d->query("SELECT COUNT(*) FROM tulkojumi WHERE versija<>$v")->fetchColumn(),
        // Gatavie pa uzvednes versijām, piem. ['v2' => 2123, 'v3' => 40] — jaukta korpusa uzskaite.
        'uzvednes' => array_map('intval', $d->query("SELECT COALESCE(uzvedne, '?') AS u, COUNT(*) FROM tulkojumi
            WHERE versija=$v AND lv IS NOT NULL GROUP BY u ORDER BY u")->fetchAll(PDO::FETCH_KEY_PAIR)),
    ];
}

// ── Dienas budžets ───────────────────────────────────────────────────────────
/** Šodien iztērētais (EUR). */
function gr_tulk_diena_terets(): float {
    $st = gr_tulk_db()->prepare("SELECT value FROM meta WHERE key=?");
    $st->execute(['terets_' . date('Y-m-d')]);
    return (float)($st->fetchColumn() ?: 0);
}
/** Pieskaita izdevumus šai dienai. */
function gr_tulk_diena_pieskaiti(float $eur): void {
    $d = gr_tulk_db();
    $k = 'terets_' . date('Y-m-d');
    $st = $d->prepare("INSERT INTO meta (key,value) VALUES (?,?)
                       ON CONFLICT(key) DO UPDATE SET value = CAST(CAST(meta.value AS REAL) + ? AS TEXT)");
    $st->execute([$k, (string)$eur, $eur]);
}
/** Tokenu izmaksas eiro. */
function gr_tulk_cena(int $in, int $out): float {
    return ($in / 1e6 * GR_TULK_IN_USD_1M + $out / 1e6 * GR_TULK_OUT_USD_1M) * GR_TULK_USD_EUR;
}

// ── Uzvednes ─────────────────────────────────────────────────────────────────
/**
 * Glosārijs abām uzvednēm (uzvedne v3). Avots: aklā vērtēšana 2026-09-09, valodas tiesneša
 * pamatojums no VIAA/CFLA/EUR-Lex lietojuma. Katrs termins te ir tāpēc, ka korpusā bija
 * IZMĒRĪTA nekonsekvence vai kļūda: beneficiary = "labuma guvējs" 104× (latviski tas ir
 * UR patiesais labuma guvējs, cits jēdziens), SME netulkots 226×, "tiek veicināts" 118×,
 * "graujošs" 35×, call for proposals četros vārdos (aicinājums 19, uzaicinājums 14,
 * konkurss 8, uzsaukums 7). Programmu un partnerību nosaukumi ar nolūku paliek angliski —
 * oficiālu LV nosaukumu ir tikai lielajām programmām, un modelis pārējos izdomātu.
 */
const GR_TULK_GLOSARIJS =
      "GLOSĀRIJS — lieto tieši šos atveidojumus, visā tekstā vienādi:\n"
    . "- call (for proposals) = uzsaukums; topic = tēma; grant = grants; proposal = projekta pieteikums\n"
    . "- applicant = pieteikuma iesniedzējs; beneficiary = finansējuma saņēmējs (nekad 'labuma guvējs')\n"
    . "- consortium = konsorcijs; coordinator = koordinators; work package = darba pakete; deliverable = nodevums\n"
    . "- eligibility = atbilstība; eligible costs = attiecināmās izmaksas; lump sum = vienreizējs maksājums (lump sum)\n"
    . "- SME, SMEs = MVU; Annex A/B/C = A/B/C pielikums; disruptive = pārveidojošs; breakthrough = revolucionārs\n"
    . "- 'is/are encouraged to' = 'ir vēlams' (ne 'tiek veicināts')\n";
/** Modalitāte un četras nozīmes kļūdas, ko abi tiesneši atrada neatkarīgi (tikai HTML sadaļām). */
const GR_TULK_MODALITATE =
      "MODALITĀTE — atšķir obligāto no ieteicamā: must / shall / required = 'ir jā-' vai 'obligāti'; "
    . "should = 'būtu jā-' vai 'ieteicams'; must not = 'nedrīkst'. Abus vienā formā neizlīdzini.\n"
    . "BIEŽĀKĀS KĻŪDAS, no kurām izvairies: 'must not duplicate' = 'nedrīkst dublēt' (ne 'nav jādublē'); "
    . "'inform decisions/policy' = 'kalpot par pamatu lēmumiem' (ne 'informēt par lēmumiem'); "
    . "'capture rate' = 'uztveršanas pakāpe' (ne 'ātrums'); "
    . "'first-of-a-kind demonstrator' = 'pirmais šāda veida demonstrācijas projekts'.\n";

function gr_tulk_uzvedne_html(string $teksts): string {
    return "Iztulko šo ES grantu konkursa sadaļu no angļu valodas latviešu valodā.\n"
        . "NOTEIKUMI:\n"
        . "- Saglabā HTML iezīmes (<p>, <ul>, <ol>, <li>, <strong>, <em>, <sup>, <a href>) tieši tādas pašas, tikpat daudz un tādā pašā secībā.\n"
        . "- Nemaini nevienu href vērtību.\n"
        . "- Tulko TIKAI tekstu starp iezīmēm.\n"
        . "- Oriģinālā atstāj TIKAI īpašvārdus un akronīmus: programmu, fondu, misiju un iniciatīvu nosaukumus (Horizon Europe, LIFE, Soil Deal for Europe, GenAI4EU), iestāžu nosaukumus un saīsinājumus (TRL, JRC).\n"
        . "- Visus pārējos vārdus tulko latviski, arī tad, ja tie ir daļa no garāka nosaukuma ('Standalone projects' = 'Atsevišķi projekti', ne 'Standalone projekti').\n"
        . "- Lieto lietišķu, skaidru latviešu valodu; nepārstāsti un neīsini.\n"
        . GR_TULK_GLOSARIJS . GR_TULK_MODALITATE
        . "- Atdod TIKAI iztulkoto HTML, bez paskaidrojumiem un bez ``` iezīmēm.\n\nTEKSTS:\n" . $teksts;
}
function gr_tulk_uzvedne_virsraksts(string $teksts): string {
    return "Iztulko šo ES grantu konkursa nosaukumu no angļu valodas latviešu valodā.\n"
        . "NOTEIKUMI:\n"
        . "- Atdod TIKAI nosaukumu vienā rindā, bez pēdiņām, bez paskaidrojumiem.\n"
        . "- Oriģinālā atstāj TIKAI īpašvārdus un akronīmus: programmu, fondu, misiju un iniciatīvu nosaukumus (Horizon Europe, LIFE, Soil Deal for Europe, GenAI4EU) un saīsinājumus (TRL, JRC).\n"
        . "- Visus pārējos vārdus tulko latviski, arī tad, ja tie ir daļa no garāka nosaukuma ('Standalone projects' = 'Atsevišķi projekti', ne 'Standalone projekti').\n"
        . GR_TULK_GLOSARIJS
        . "- Nelieto HTML.\n\nNOSAUKUMS:\n" . $teksts;
}

// ── Pārbaudes vārti ──────────────────────────────────────────────────────────
/** Iezīmju virkne (tikai vārdi, secībā) — struktūras salīdzināšanai. */
function gr_tulk_iezimes(string $h): array {
    preg_match_all('~</?([a-z0-9]+)~i', $h, $m);
    return array_map('strtolower', $m[1]);
}
/** Bloka iezīmes — teksta uzbūve, kas nedrīkst mainīties. */
function gr_tulk_bloki(array $iez): array {
    return array_values(array_filter($iez, fn($t) => in_array($t, ['p','ul','ol','li'], true)));
}
function gr_tulk_saites(string $h): array {
    preg_match_all('~href="([^"]*)"~i', $h, $m);
    return $m[1];
}

/**
 * Pārbauda tulkojumu. Atdod [ok, tirs, piezime].
 *
 * Divi līmeņi ar nolūku. Stingrais (identiskas visas iezīmes) 16 sadaļu mērījumā izturēja
 * 14/16; abos kritienos pazuda pa divām IEKŠĒJĀM iezīmēm (<sup> vēres cipars, <strong>).
 * Bloku uzbūve un saites tur bija pareizas, tāpēc tekstu izmest un maksāt vēlreiz nozīmētu
 * tērēt naudu par kosmētiku. Saites turpretī jāsakrīt VIENMĒR — mainīts href ved cilvēku
 * uz svešu lapu.
 */
function gr_tulk_parbaudi_html(string $avots, string $atbilde): array {
    $t = trim($atbilde);
    // Ietvaru un pavadtekstu meklē VISĀ atbildē, ne tikai malās. Noenkurots ^```/```$ palaida
    // garām "Here is the translation:\n```html\n<p>…", jo tur ietvars nav pirmajā rindā —
    // preambula un burtiskas atpakaļvērstās pēdiņas nonāca lapā kā teksts. Iezīmju pārbaude
    // to nevar noķert, jo iezīmes paliek tās pašas.
    if (str_contains($t, '```')) return [false, '', 'atbildē ir ``` ietvars'];
    $pirms = strpos($t, '<');
    if ($pirms === false) return [false, '', 'atbildē nav HTML'];
    if ($pirms > 3) return [false, '', 'pirms HTML ir pavadteksts (' . $pirms . ' rakstz.)'];
    if ($pirms > 0) $t = substr($t, $pirms);
    if ($t === '') return [false, '', 'tukša atbilde'];
    // Sanitizē TĀPAT kā oriģinālu: modelis var atdot skriptu vai atribūtu, ko baltais
    // saraksts neatļauj. Bez šī modeļa izvade apietu būves XSS aizsardzību.
    $t = gr_sanitize_html($t);
    if (trim(strip_tags($t)) === '') return [false, '', 'pēc attīrīšanas tukšs'];

    // SAITES ATJAUNO NO ORIĢINĀLA, nevis salīdzina baitus.
    // Iepriekš jebkura atšķirība bija noraidījums, un četras sadaļas krita tāpēc, ka avotā
    // href bija "…&amp;amp;from=EN", bet modelis to normalizēja uz "…&amp;from=EN" — tā ir TĀ PATI
    // adrese. Katrs noraidījums maksā vēlreiz, un pēc trim mēģinājumiem teksts paliek angliski.
    // Tagad adreses pārrakstām no oriģināla pēc kārtas numura: rezultāts ir stingrāks (modeļa
    // href lapā nenonāk NEKAD), un naudu netērē. Noraida tikai tad, ja saišu SKAITS atšķiras.
    $sA = gr_tulk_saites($avots); $sB = gr_tulk_saites($t);
    if (count($sA) !== count($sB)) return [false, '', 'saišu skaits ' . count($sA) . ' -> ' . count($sB)];
    if ($sA !== $sB) {
        // PĒC ADRESES, NE PĒC KĀRTAS NUMURA. Iepriekšējā versija lika avota adreses tulkojuma
        // saitēm pēc kārtas, un, ja modelis divas saites bija samainījis vietām (teikuma
        // pārkārtojums), B teksts dabūja A adresi — pārbaudīts. Tagad: abas puses normalizē
        // (entītijas), un, ja tā ir TĀ PATI adrešu kopa, tulkojuma secība paliek (teksts paliek
        // pie savas saites) un katrai adresei ieliek avota rakstību. Ja kopa atšķiras, modelis
        // adresi ir mainījis vai izdomājis — to noraida. Otrreiz kodēt nedrīkst (sk. attīrītāju).
        $norm = fn(string $u): string => html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $nA = array_map($norm, $sA); $nB = array_map($norm, $sB);
        $kA = $nA; sort($kA); $kB = $nB; sort($kB);
        if ($kA !== $kB) return [false, '', 'mainīta saites adrese'];
        $avotaRakstiba = [];
        foreach ($sA as $i => $u) $avotaRakstiba[$nA[$i]] = $u;
        $i = 0;
        $t = preg_replace_callback('~href="[^"]*"~', function () use (&$i, $nB, $avotaRakstiba) {
            return 'href="' . ($avotaRakstiba[$nB[$i++]] ?? '') . '"';
        }, $t) ?? $t;
    }

    $ia = gr_tulk_iezimes($avots); $ib = gr_tulk_iezimes($t);
    if ($ia === $ib) return [true, $t, ''];
    if (gr_tulk_bloki($ia) === gr_tulk_bloki($ib)) return [true, $t, 'atšķiras iekšējās iezīmes'];
    return [false, '', 'atšķiras bloku uzbūve (' . count($ia) . ' -> ' . count($ib) . ')'];
}

/**
 * Virsraksta pārbaude. Šķiro FORMA, ne garums.
 *
 * Garums vien nešķir: uz 12 875 īstiem EN->LV virsrakstu pāriem 99,8 % tulkojumu ir
 * 0,54–2,65 reizes oriģināla garumā, un modeļa paskaidrojošā atbilde ("Atkarībā no
 * konteksta ... 1) ... 2) ...") iekļaujas tajā pašā logā — mērīta 2,43x. Tāpēc atsevišķi
 * pārbauda rindkopas, markdown treknrakstu un numurētus variantus.
 *
 * Rindu pārbaudei jānotiek PIRMS atstarpju sablīvēšanas: preg_replace('~\s+~') pārvērš
 * jaunrindas par atstarpēm, tāpēc pēc tās str_contains($t, "\n") nekad nevar nostrādāt.
 */
function gr_tulk_parbaudi_virsrakstu(string $avots, string $atbilde): array {
    $jel = trim($atbilde);
    if ($jel === '') return [false, '', 'tukša atbilde'];
    if (preg_match('~[\r\n]~', $jel))        return [false, '', 'vairākas rindas'];
    if (str_contains($jel, '**'))             return [false, '', 'markdown paskaidrojums'];
    if (preg_match_all('~\d\)~', $jel) >= 2) return [false, '', 'numurēti varianti'];
    $t = trim((string)preg_replace('~\s+~u', ' ', strip_tags($jel)));
    $t = trim($t, " \t\n\r\0\x0B\"'");
    if ($t === '') return [false, '', 'pēc attīrīšanas tukšs'];
    $la = mb_strlen($avots); $lb = mb_strlen($t);
    // Augšējā robeža = mērītā sadalījuma p99,9 (2,65). Apakšējo apzināti atlaižam līdz
    // 0,40 (mērītais p0,1 ir 0,54), jo īsi akronīmu nosaukumi latviski mēdz saīsināties.
    // Ārpus tām 1 no 500 īstiem
    // tulkojumiem tiks noraidīts un mēģināts vēlreiz — lētāk nekā rādīt paskaidrojumu.
    if ($lb < $la * 0.40 || $lb > $la * 2.65) return [false, '', "garums $la -> $lb"];
    return [true, $t, ''];
}

// ── Tulkotājs ────────────────────────────────────────────────────────────────
/**
 * Iztulko rindā gaidošos tekstus. Atdod kopsavilkumu.
 *
 * @param int      $limit  cik tekstu ne vairāk kā šajā palaišanā (0 = bez ierobežojuma)
 * @param callable $log    ziņojumu izvade
 */
function gr_tulk_darbs(int $limit = 0, ?callable $log = null): array {
    $log ??= static function (string $m): void {};
    $d = gr_tulk_db();

    if (reg_gemini_key() === '') { $log('Gemini atslēgas nav — tulkošana izlaista.'); return ['tulkoti'=>0,'klumes'=>0,'eur'=>0.0,'api_apstasanas'=>false]; }
    if (reg_gemini_model() !== GR_TULK_MODELIS)
        $log('  ⚠ modelis ' . reg_gemini_model() . ', bet cenu konstantes ir par ' . GR_TULK_MODELIS . ' — budžeta aplēse var būt nepareiza.');

    // Slēdzene: divas paralēlas palaišanas redzētu vienu un to pašu tukšo rindu un
    // tulkotu to abas. Neblokējoša — otrā palaišana vienkārši paiet garām.
    $lockPath = granti_tulk_lock_path();
    $lock = fopen($lockPath, 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        $log('Tulkošana jau notiek citā procesā — izlaists.');
        return ['tulkoti'=>0,'klumes'=>0,'eur'=>0.0,'api_apstasanas'=>false];
    }

    try {
        $tulkoti = 0; $klumes = 0; $eurKopa = 0.0; $apiApstasanas = false;
        $opts = reg_gemini_thinking_min() + ['temperature' => 0.1, 'maxOutputTokens' => 32768, 'timeout' => 240];

        while (true) {
            $terets = gr_tulk_diena_terets();
            if ($terets >= GR_TULK_DIENAS_EUR) {
                $log(sprintf('Dienas griesti sasniegti (%.2f / %.2f €) — apstājas.', $terets, GR_TULK_DIENAS_EUR));
                break;
            }
            if ($eurKopa >= GR_TULK_PALAISANAS_EUR) {
                $log(sprintf('Palaišanas griesti sasniegti (%.2f / %.2f €) — apstājas.', $eurKopa, GR_TULK_PALAISANAS_EUR));
                break;
            }
            if ($limit > 0 && $tulkoti + $klumes >= $limit) break;

            $n = GR_TULK_PARALELI;
            if ($limit > 0) $n = min($n, $limit - $tulkoti - $klumes);
            $n = max(1, (int)$n);   // LIMIT iekļauj skaitli tieši: saistīts parametrs te kļūtu par virkni
            // LENGTH() ir izteiksme BEZ kolonnas afinitātes, tāpēc PDO noklusējuma TEXT
            // parametrs SQLite salīdzinājumā vienmēr ir lielāks par skaitli un vārts nekad
            // nenostrādāja: 100 000 rakstzīmju teksts izgāja cauri pie robežas 60 000
            // (pārbaudīts). Blakus esošais neizdevas < ? strādā, jo TAI kolonnai afinitāte IR.
            // Sk. [[ref-pdo-sqlite-tipu-slazdi]].
            // LIDOJUMĀ ESOŠIE NE. Batch darbam nodotie teksti ir jau apmaksāti; ja tos
            // paņemtu arī tūlītējais ceļš, par vienu tekstu samaksātu divas reizes.
            // Tabulas esamību pārbaudām, jo šis fails par batch moduli neko nezina un
            // strādā arī bez tā (piem. testa DB, kur batch nekad nav palaists).
            $arBatch = (int)$d->query("SELECT COUNT(*) FROM sqlite_master
                                       WHERE type='table' AND name='granti_batch_teksti'")->fetchColumn() > 0;
            $lido = $arBatch ? ' AND hash NOT IN (SELECT hash FROM granti_batch_teksti)' : '';
            $q = $d->prepare("SELECT hash, veids, lauks, avots FROM tulkojumi
                              WHERE versija = ? AND lv IS NULL AND neizdevas < ? AND LENGTH(avots) <= ?
                              $lido
                              ORDER BY neizdevas, LENGTH(avots) LIMIT $n");
            $q->bindValue(1, GR_TULK_VERSIJA, PDO::PARAM_STR);
            $q->bindValue(2, GR_TULK_MAX_KLUMES, PDO::PARAM_INT);
            $q->bindValue(3, GR_TULK_MAX_RAKSTZ, PDO::PARAM_INT);
            $q->execute();
            $vilnis = $q->fetchAll(PDO::FETCH_ASSOC);
            if (!$vilnis) break;

            $prompts = [];
            foreach ($vilnis as $i => $r)
                $prompts[$i] = $r['veids'] === 'virsraksts'
                    ? gr_tulk_uzvedne_virsraksts((string)$r['avots'])
                    : gr_tulk_uzvedne_html((string)$r['avots']);

            $u0 = reg_gemini_usage_total();
            $in0 = $u0['in']; $out0 = $u0['out'] + $u0['thoughts'];
            $res = reg_gemini_generate_multi($prompts, $opts, GR_TULK_PARALELI);
            $u1 = reg_gemini_usage_total();
            $eur = gr_tulk_cena($u1['in'] - $in0, ($u1['out'] + $u1['thoughts']) - $out0);
            gr_tulk_diena_pieskaiti($eur);
            $eurKopa += $eur;

            $okSt = $d->prepare("UPDATE tulkojumi SET lv=?, modelis=?, iztulkots=?, kluda=NULL, uzvedne=? WHERE hash=?");
            $noSt = $d->prepare("UPDATE tulkojumi SET neizdevas = neizdevas + 1, kluda=? WHERE hash=?");
            $apiSt = $d->prepare("UPDATE tulkojumi SET kluda=? WHERE hash=?");
            $apiKlumes = 0;
            // Avārija viļņa vidū citādi nozīmētu, ka par jau apmaksātajiem tulkojumiem
            // samaksāts, bet tie nav saglabāti, un nākamā palaišana tos pirktu vēlreiz.
            $d->beginTransaction();
            foreach ($vilnis as $i => $r) {
                $atb = $res[$i] ?? null;
                if ($atb === null || $atb === '') {
                    // API vai tīkla kļūme NAV teksta vaina. Agrāk tā palielināja neizdevas,
                    // tāpēc viena stunda ar 429/503 pēc trim apļiem uz visiem laikiem atzīmēja
                    // VISU rindu kā atmestu (907 teksti, ~2721 lieks pieprasījums), un dienas
                    // griesti to neaptur, jo neveiksmīgs izsaukums maksā 0 €. Skaitītāju
                    // netaisām; tā vietā apstājamies, ja vilnis nedeva NEVIENU rezultātu.
                    // Bet 4xx (izņemot 429) ir ŠĪ teksta vaina — piemēram 400 par pārāk garu
                    // uzvedni vai satura filtrs. Tāds teksts katrā vilnī krīt no jauna, un bez
                    // skaitītāja tas paliktu rindā mūžīgi (nemaksā, bet rinda nekad neiztukšojas).
                    $kods = (int)(reg_gemini_last_error()['code'] ?? 0);
                    if ($kods >= 400 && $kods < 500 && $kods !== 429) {
                        $noSt->execute(['API ' . $kods . ' (teksta vaina): ' . (reg_gemini_error_brief() ?: '?'), $r['hash']]);
                        $klumes++; continue;
                    }
                    $apiSt->execute(['API: ' . (reg_gemini_error_brief() ?: 'atbildes nav'), $r['hash']]);
                    $apiKlumes++; continue;
                }
                [$ok, $tirs, $piez] = $r['veids'] === 'virsraksts'
                    ? gr_tulk_parbaudi_virsrakstu((string)$r['avots'], (string)$atb)
                    : gr_tulk_parbaudi_html((string)$r['avots'], (string)$atb);
                if ($ok) {
                    $okSt->execute([$tirs, reg_gemini_model(), date('c'), GR_TULK_UZVEDNE, $r['hash']]);
                    $tulkoti++;
                    if ($piez !== '') $log('  · ' . $r['lauks'] . ': ' . $piez . ' (pieņemts)');
                } else {
                    $noSt->execute([$piez, $r['hash']]);
                    $klumes++;
                    $log('  ! ' . $r['lauks'] . ': ' . $piez);
                }
            }
            $d->commit();
            if ($apiKlumes === count($vilnis)) {
                // Neviena atbilde visā vilnī = avots nav pieejams. Turpināt nozīmētu sist
                // pa sienu un iztukšot rindas kļūmju budžetu; nākamā palaišana turpinās.
                $log(sprintf('  visas %d atbildes neizdevās (%s) — apstājas, rinda paliek neskarta.',
                    $apiKlumes, reg_gemini_error_brief() ?: 'nezināms iemesls'));
                $apiApstasanas = true;
                break;
            }
            if ($apiKlumes > 0) $log("  · $apiKlumes atbildes neizdevās API dēļ (skaitītājs netiek palielināts)");
            $log(sprintf('  tulkoti %d, kļūmes %d, šodien %.3f €', $tulkoti, $klumes, gr_tulk_diena_terets()));
        }
        return ['tulkoti' => $tulkoti, 'klumes' => $klumes, 'eur' => $eurKopa,
                'api_apstasanas' => $apiApstasanas];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

// ── Sasaiste ar grants DB ────────────────────────────────────────────────────
/** Kolonnu -> keša veida karte. Virsraksti ir vienkāršs teksts, sadaļas — HTML. */
const GR_TULK_LAUKI = [
    'title'       => 'virsraksts',
    'call_title'  => 'virsraksts',
    'objective'   => 'html',
    'outcome'     => 'html',
    'scope'       => 'html',
    'eligibility' => 'html',
    'specific'    => 'html',
];

/** Grants DB kolonnas nosaukums avotam (sadaļām priekšā ir sec_). */
function gr_tulk_avota_kolonna(string $lauks): string {
    return in_array($lauks, ['title', 'call_title'], true) ? $lauks : 'sec_' . $lauks;
}

/**
 * Ieliek rindā visu, kas šajā grants DB vēl nav tulkots, un uzreiz aizpilda lv_* no keša.
 * Tulko TIKAI source='EU' — SIF konkursi jau ir latviski.
 * @return array{rinda:int,aizpilditi:int}
 */
function gr_tulk_sinhronize(PDO $grants, ?callable $log = null, bool $aizpildit = true): array {
    $log ??= static function (string $m): void {};
    $cols = implode(', ', array_map('gr_tulk_avota_kolonna', array_keys(GR_TULK_LAUKI)));
    $rows = $grants->query("SELECT id, $cols FROM grants WHERE source='EU'")->fetchAll(PDO::FETCH_ASSOC);
    $jauni = 0; $gari = 0;
    $cache = gr_tulk_db();
    // BĀREŅU ATPAZĪŠANA PĒC KOPAS, NE PĒC LAIKA. Sākotnēji šim lietoju laika zīmogu ("dzēs
    // to, kas nav pieskarts šajā sinhronizācijā"), bet date('c') precizitāte ir sekunde, un
    // divas darbības vienā sekundē kļūst neatšķiramas — tests to noķēra: bārenis palika.
    // Tāpēc pierakstām pieskarto jaucējkodu KOPU un dzēšam pēc tās. Bārenis ir teksts, kura
    // dzīvajā DB vairs nav (piem. mainījās būves attīrītājs, tāpēc mainījās jaucējkods);
    // mērīts, ka pēc viena attīrītāja labojuma rindā palika 40 tādu par ~0,22 €.
    $zimogs = date('c');
    $cache->exec("CREATE TEMP TABLE IF NOT EXISTS pieskarti (hash TEXT PRIMARY KEY)");
    $cache->exec("DELETE FROM pieskarti");
    $atzime = $cache->prepare("INSERT OR IGNORE INTO pieskarti (hash) VALUES (?)");
    $cache->beginTransaction();
    foreach ($rows as $r) {
        foreach (GR_TULK_LAUKI as $lauks => $veids) {
            $v = (string)($r[gr_tulk_avota_kolonna($lauks)] ?? '');
            if ($v === '') continue;
            // mb_strlen, ne strlen: SQL puse (LENGTH) skaita RAKSTZĪMES, un ziņojums arī
            // runā par rakstzīmēm. Ar baitiem 40 000 latviešu rakstzīmju tekstu izmestu kā
            // "garāku par 60 000 rakstzīmēm".
            if (mb_strlen($v) > GR_TULK_MAX_RAKSTZ) { $gari++; continue; }
            $atzime->execute([gr_tulk_rinda($veids, $lauks, $v, $zimogs)]);
            $jauni++;
        }
    }
    $cache->commit();
    // Bāreņus dzēš TIKAI netulkotos. Gatavs tulkojums ir samaksāts aktīvs: to paturam, jo
    // teksts var atgriezties (konkurss atkal parādās portālā) un tad tas ir bez maksas.
    // Drošinātājs: ja sinhronizācija neko nepieskāra, DB acīmredzot ir tukša vai nepareiza —
    // tad neko nedzēšam.
    // Dzēš TIKAI rindas bez kļūmju vēstures (neizdevas = 0). Rinda ar 3 kļūmēm ir samaksāta
    // informācija: ja to izdzēš un nākamā sinhronizācija ieliek atpakaļ ar nulli, par to
    // tekstu maksā vēlreiz trīsreiz — tā pati kļūdu klase, kas vakar bija pie --tiri.
    // Rindas ar vēsturi, kuru teksta vairs nav, novāc laika tīrīšana (gr_tulk_tiri, 180 d.).
    $bareni = 0;
    if ($jauni > 0) {
        // LIDOJUMĀ ESOŠIE PALIEK. Batch darbam nodotais teksts ir jau apmaksāts, un tā rinda
        // ir vienīgā vieta, kur glabājas avots, ar ko atbildi pārbaudīt. Ja būve to izmestu
        // (piemēram, konkurss uz dienu pazuda no ES plūsmas), savākšana atrastu jaucējkodu
        // bez rindas, tulkojums aizietu zudumā, un nākamā palaišana par to samaksātu vēlreiz.
        // Tabulas esamību pārbaudām, jo šis fails par batch moduli neko nezina.
        $arBatch = (int)$cache->query("SELECT COUNT(*) FROM sqlite_master
                                       WHERE type='table' AND name='granti_batch_teksti'")->fetchColumn() > 0;
        $lido = $arBatch ? ' AND hash NOT IN (SELECT hash FROM granti_batch_teksti)' : '';
        $del = $cache->prepare("DELETE FROM tulkojumi WHERE versija = ? AND lv IS NULL AND neizdevas = 0
                                  AND hash NOT IN (SELECT hash FROM pieskarti)$lido");
        $del->execute([GR_TULK_VERSIJA]);
        $bareni = $del->rowCount();
    }
    $cache->exec("DROP TABLE IF EXISTS pieskarti");
    $st = gr_tulk_rindas_stats();
    $log(sprintf('Tulkojumu kešs: rindā %d, gatavi %d, atmesti %d (pieskarti %d lauki).',
        $st['rinda'], $st['gatavi'], $st['atmesti'], $jauni));
    // Kluss izlaidums ir sliktāks par skaļu: šie lauki lapā paliks angliski uz visiem laikiem.
    if ($gari > 0) $log("  ⚠ $gari lauki garāki par " . GR_TULK_MAX_RAKSTZ . " rakstzīmēm — netiek tulkoti.");
    if ($bareni > 0) $log("  · izmesti $bareni bāreņi (teksts dzīvajā DB vairs neeksistē).");
    // --statuss / --aplese ir LASĪŠANAS režīmi: tie nedrīkst pieskarties dzīvajai grants DB
    // (ALTER TABLE + UPDATE pār visām ES rindām), kaut rindu kešā atsvaidzināt vajag, lai
    // aplēse būtu patiesa.
    return ['rinda' => $st['rinda'], 'aizpilditi' => $aizpildit ? gr_tulk_aizpildi($grants, $log) : 0];
}

/**
 * Pārraksta grants.lv_* kolonnas no keša. Idempotents: to var palaist pēc katras būves
 * un pēc katras tulkošanas kārtas, un rezultāts ir tas pats.
 */
function gr_tulk_aizpildi(PDO $grants, ?callable $log = null): int {
    $log ??= static function (string $m): void {};
    $have = array_column($grants->query("PRAGMA table_info(grants)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (array_keys(GR_TULK_LAUKI) as $lauks) {
        if (!in_array('lv_' . $lauks, $have, true)) $grants->exec("ALTER TABLE grants ADD COLUMN lv_$lauks TEXT");
    }
    // Ja kešā šai versijai nav neviena gatava tulkojuma, bet grants DB tie JAU ir, tad
    // gandrīz droši vien kešs ir pazudis vai GRANTI_TULK_DB rāda nepareizi. Pārrakstīšana
    // tādā gadījumā klusi iztukšotu visas lv_* slejas, un vietne bez viena vārda atgrieztos
    // pie angļu valodas. Labāk neko nedarīt un skaļi pateikt.
    $gatavi = (int)gr_tulk_db()->query("SELECT COUNT(*) FROM tulkojumi WHERE versija="
        . gr_tulk_db()->quote(GR_TULK_VERSIJA) . " AND lv IS NOT NULL")->fetchColumn();
    $jauIr = in_array('lv_title', $have, true)
        ? (int)$grants->query("SELECT COUNT(*) FROM grants WHERE lv_title IS NOT NULL")->fetchColumn() : 0;
    if ($gatavi === 0 && $jauIr > 0) {
        $log("  ⚠ Kešā nav neviena tulkojuma, bet DB tie ir $jauIr — lv_* NETIEK pārrakstīts. Pārbaudi GRANTI_TULK_DB.");
        return $jauIr;
    }
    $cols = implode(', ', array_map('gr_tulk_avota_kolonna', array_keys(GR_TULK_LAUKI)));
    $rows = $grants->query("SELECT id, $cols FROM grants WHERE source='EU'")->fetchAll(PDO::FETCH_ASSOC);
    $get = gr_tulk_db()->prepare("SELECT lv FROM tulkojumi WHERE hash=?");
    $sets = [];
    foreach (array_keys(GR_TULK_LAUKI) as $l) $sets[] = "lv_$l = ?";
    $upd = $grants->prepare("UPDATE grants SET " . implode(', ', $sets) . " WHERE id = ?");
    $n = 0;
    $grants->beginTransaction();
    foreach ($rows as $r) {
        $vals = []; $ir = false;
        foreach (GR_TULK_LAUKI as $lauks => $veids) {
            $src = (string)($r[gr_tulk_avota_kolonna($lauks)] ?? '');
            $lv = null;
            if ($src !== '') {
                $get->execute([gr_tulk_hash($veids, $src)]);
                $v = $get->fetchColumn();
                if ($v !== false && $v !== null && $v !== '') { $lv = (string)$v; $ir = true; }
            }
            $vals[] = $lv;
        }
        $vals[] = $r['id'];
        $upd->execute($vals);
        if ($ir) $n++;
    }
    $grants->commit();
    $log("Aizpildīti lv_* lauki: $n konkursiem no " . count($rows) . '.');
    return $n;
}

/** Izmet pamestos rindas ierakstus, kas nav pieskarti ilgāk par $dienas. */
function gr_tulk_tiri(int $dienas = 180): int {
    $st = gr_tulk_db()->prepare("DELETE FROM tulkojumi WHERE lv IS NULL AND pedejo_reizi < ?");
    $st->execute([date('c', time() - $dienas * 86400)]);
    return $st->rowCount();
}

/**
 * Izmet VECO versiju rindas. Netulkotās nekad nebūs vajadzīgas; gatavos tulkojumus
 * pēc noklusējuma PATUR — tie neko nemaksā un ļauj atgriezties pie iepriekšējās
 * uzvednes bez atkārtotas maksas.
 */
function gr_tulk_tiri_versijas(bool $arGatavajiem = false): int {
    $sql = "DELETE FROM tulkojumi WHERE versija <> ?" . ($arGatavajiem ? '' : ' AND lv IS NULL');
    $st = gr_tulk_db()->prepare($sql);
    $st->execute([GR_TULK_VERSIJA]);
    return $st->rowCount();
}
