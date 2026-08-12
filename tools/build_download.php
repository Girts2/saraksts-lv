<?php
/**
 * tools/build_download.php — būvē publisko pirmkoda ZIP sadaļai "Lejupielāde".
 *
 * Palaišana:   php tools/build_download.php
 * Rezultāts:   lejupielade/saraksts-lv-kods.zip
 *
 * Ko dara:
 *   1. Savāc koda failus no docroot, izlaižot visu, ko kods pats lejupielādē vai ģenerē.
 *   2. Nokopē uz pagaidu mapi un IZTĪRA noslēpumus (API atslēgas, DB paroles, admin tokenu,
 *      analītikas ID, personiskās e-pasta adreses).
 *   3. Pievieno LICENSE + NOTICE.md + README-PIRMS-SAKAM.md.
 *   4. PĀRBAUDA, ka neviens noslēpums nav palicis — ja ir, būve tiek pārtraukta.
 *   5. Saliek vienā ZIP failā.
 *
 * Šo skriptu drīkst palaist atkārtoti — tas vienmēr būvē no nulles.
 * Mape tools/ ir bloķēta .htaccess (RedirectMatch 404 ^/tools/), tāpēc caur URL nav pieejama.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Šis skripts ir paredzēts tikai komandrindai.\n");
}

const PKG_NAME = 'saraksts-lv-kods';

$ROOT    = dirname(__DIR__);
$OUT_ZIP = $ROOT . '/lejupielade/' . PKG_NAME . '.zip';
$STAGE   = sys_get_temp_dir() . '/' . PKG_NAME . '-stage-' . getmypid();

// ─────────────────────────────────────────────────────────────────────────────
// 1. KO NEIEKĻAUT
// ─────────────────────────────────────────────────────────────────────────────

/** Mapes (ceļa prefikss no docroot saknes), ko izlaist pilnībā. */
const EXCLUDE_DIRS = [
    'csv/',                 // no data.gov.lv — kods lejupielādē pats (registrs/build/download.php)
    'build_state/',         // būves stāvoklis un žurnāli
    'sitemap/',             // ģenerē būve
    'lejupielade/',         // pati lejupielādes mape (citādi ZIP saturētu sevi)
    'konkursi/db/',         // tenders.db + rezerves kopijas (ģenerē sinhronizācija)
    'konkursi/data/tmp/',   // sinhronizācijas sīkdatņu burkas u.c. izpildlaika stāvoklis
    'registrs/ai_cache/',   // MI atbilžu kešs — izpildlaika stāvoklis, var saturēt personas datus
    'struktura/data/',      // treemap JSON — ģenerē registrs/build/section_struktura.php
    'tools/',               // iekšējie audita/būves rīki (šis fails tiek pievienots atsevišķi)
    'temp/',                // Iespējas konveijera darba mape (~6 GB jēldatu; pilnībā atjaunojama)
    'log/',                 // vienotā žurnāla mape (applog) — izpildlaika stāvoklis
    'admin_state/',         // darbu slēdzenes un izvades žurnāli — izpildlaika stāvoklis
    'Iespēja/',             // TIKAI saknes apstaigāšanai: pakotnē šī mape nonāk caur
                            // EXTRA_SOURCES kā ASCII 'iespeja/' — bez šī ieraksta tā būtu divreiz
    '.git/',
    '.claude/',            // lokālā izstrādes rīka (Claude Code) konfigurācija — nav produkta daļa
];

/** Mapes, kas paliek, lai gan atrodas izslēgtā mapē (pārbauda pirms EXCLUDE_DIRS). */
const KEEP_DIRS = [
    'konkursi/data/ca/',    // manuāli pievienotie starpsertifikāti — kods tos nevar iegūt pats
];

/**
 * Papildu avoti: avota mape (relatīvi pret docroot) => mape pakotnē.
 *
 * Iespējas datu konveijers (kopš 2026-07-28 pilnībā PHP) dzīvo docroot mapē
 * `Iespēja/`, jo to palaiž cron/admin panelis uz paša hostinga. BEZ tā
 * lejupielādētais `iespeja.php` ir tukša lapa: visi tās dati nāk no MySQL
 * tabulām, ko uzbūvē vienīgi šie skripti. Pakotnē mapi pārsaucam par ASCII
 * `iespeja/` (ZIP ieraksti ar ē dažos atspiedējos kropļojas); saknes
 * apstaigāšanā `Iespēja/` tāpēc ir EXCLUDE_DIRS — citādi tā būtu divreiz.
 */
const EXTRA_SOURCES = [
    'Iespēja' => 'iespeja',
];

/** Konkrēti faili (ceļš no saknes), ko izlaist. */
const EXCLUDE_FILES = [
    'struktura.php',                    // BŪVES IZVADE (skat. .gitignore) — ģenerē section_struktura.php
    'vid_quarterly_history.sqlite',
    'tenders.db',
    'konkursi/data/ca-bundle.pem',      // ģenerē ks_ca_bundle() no ca/*.pem + sistēmas saišķa
    'konkursi/data/sync_state.json',    // izpildlaika stāvoklis — sinhronizācija to raksta pati
    '.DS_Store',

    // MAPES precīzs sakritums (filtrs mapēm padod 'ceļš/'): docroot `iespeja/` ir
    // Python dev konveijers, kam pakotnē nav vietas — bet EXCLUDE_DIRS prefikss
    // 'iespeja/' nogrieztu arī EXTRA_SOURCES pārsaukto saturu (Iespēja→iespeja/...).
    // Precīzais sakritums trāpa tikai pašai saknes mapei, pārsauktajiem bērniem ne.
    'iespeja/',

    // "Test ..." sadaļas — tikai lokālā testa vide, pakotnē/repo neiet, kamēr
    // Girts nav apstiprinājis publicēšanu (skat. test_tools/ ārpus docroot).
    'test_lapas/',      // detaļlapu veidnes (/profesija/*, /zales/*)
    'registrs/view/partials/test_atbalsts_panel.php',
    'registrs/view/partials/test_tiesiskais_panel.php',
    'test_profesijas.php',
    'test_zales.php',
    'test_atbalsts.php',
    'test_nodokli.php',
    'test_darijumi.php',
];

/**
 * Faila nosaukuma šabloni, ko izlaist jebkurā mapē.
 * Attiecas arī uz MAPĒM (is_excluded ņem basename arī no mapes ceļa), tāpēc
 * `__pycache__` te nogriež Python kešu jebkurā dziļumā — tas parādās, tiklīdz
 * Iespējas konveijeru palaiž uz vietas.
 */
const EXCLUDE_GLOBS = [
    '*.sqlite', '*.db', '*.db.*', '*.sqlite3',
    '.DS_Store', '._*', 'Thumbs.db',
    '*.log', '*.log.prev', '*.out', '*.bak', '*.orig', '*.swp',
    '__pycache__', '*.pyc', '*.lock',
];

// ─────────────────────────────────────────────────────────────────────────────
// 2. NOSLĒPUMU TĪRĪŠANA
// ─────────────────────────────────────────────────────────────────────────────

/** Paplašinājumi, kuros meklējam un aizstājam tekstu. */
const TEXT_EXT = ['php', 'js', 'json', 'md', 'txt', 'html', 'htm', 'css', 'xml', 'py', 'sh', 'pem'];

/**
 * Aizstājamie noslēpumi un aizliegtie paraugi nāk no ATSEVIŠĶA faila, kas nekad
 * netiek pakots (tools/ ir izslēgta mape, un pakotnē pievienojam tikai šo skriptu).
 *
 * Iemesls: šis būves skripts TIEK iekļauts publiskajā ZIP, lai saņēmējs redz, kā
 * pakotne tapusi. Ja atslēgas un paroles būtu šeit, skripts nodotu tālāk tieši to,
 * ko cenšas noņemt — pirmajā mēģinājumā tieši tā arī notika.
 */
function secrets(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $path = __DIR__ . '/build_download.secrets.php';
    if (!is_file($path)) {
        fail("trūkst noslēpumu saraksta: $path\n"
           . "    Šis fails apzināti nav pakotnē un nav versiju kontrolē.\n"
           . "    Izveido to pēc parauga (skat. tools/build_download.php komentārus):\n"
           . "        <?php return ['scrub' => ['SLEPENAIS' => 'VIETTURIS'], 'forbidden' => ['/SLEPENAIS/' => 'apraksts']];");
    }
    $cfg = require $path;
    if (!is_array($cfg) || !isset($cfg['scrub'], $cfg['forbidden'])) {
        fail("nederīgs noslēpumu saraksts: $path (jāatgriež masīvs ar 'scrub' un 'forbidden')");
    }
    return $cache = $cfg;
}

/**
 * Faili, kurus aizstājam PILNĪBĀ ar drošu paraugu.
 * Funkciju paraksti saglabāti, lai kods turpinātu darboties bez atslēgām.
 */
function stub_files(): array
{
    return [
        'registrs/mi/key.php' => <<<'PHP'
<?php
// registrs/mi/key.php — Google Gemini API atslēgas.
//
// PUBLISKAJĀ IZLAIDUMĀ ATSLĒGAS IR NOŅEMTAS. Ieliec savas, lai ieslēgtu MI funkcijas
// (uzņēmumu lapu MI panelis, iepirkumu virsrakstu tulkošana). Bez tām pārējā sistēma
// strādā normāli — MI daļa vienkārši klusē.
//
// Atslēgu iegūsti bez maksas: https://aistudio.google.com/apikey
//
// Kāpēc te ir XOR+base64, nevis vienkāršs teksts: oriģinālā šis fails glabājas ārpus
// versiju kontroles, un obfuskācija pasargā tikai no automātiskiem GitHub skeneriem.
// TĀ NAV ŠIFRĒŠANA. Ja tavs projekts ir publisks, glabā atslēgu vides mainīgajā
// (getenv), nevis šajā failā.

/** Maksas / galvenā atslēga. */
function _get_g_key(): string
{
    return (string) (getenv('GEMINI_API_KEY') ?: '');
}

/**
 * Bezmaksas līmeņa atslēga (atsevišķs Google projekts) — ikdienas iepirkumu
 * virsrakstu tulkošanai cron sinhronizācijā. Drīkst būt tā pati, kas augšā.
 */
function _get_g_key_free(): string
{
    return (string) (getenv('GEMINI_API_KEY_FREE') ?: _get_g_key());
}

$gemini_api_key = _get_g_key();
PHP,

        'admin_token.php' => <<<'PHP'
<?php
// admin_token.php — slēpto administratora paneļu piekļuves atslēga.
//
// Lieto: /data_admin.php?k=..., /mi.php?k=..., /konkursi_admin.php?k=...
//
// OBLIGĀTI NOMAINI PIRMS PUBLICĒŠANAS. Ģenerē garu nejaušu virkni, piemēram:
//     php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
//
// Vēl labāk — ņem no vides mainīgā, lai atslēga nenonāk versiju kontrolē.

return getenv('ADMIN_TOKEN') ?: 'NOMAINI-SO-UZ-SAVU-SLEPENO-TOKENU';
PHP,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// PALĪGFUNKCIJAS
// ─────────────────────────────────────────────────────────────────────────────

function say(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
function fail(string $msg): never { fwrite(STDERR, "\n✗ KĻŪDA: $msg\n"); exit(1); }

function is_excluded(string $rel): bool
{
    // macOS failu sistēma vārdus glabā dekomponētā Unicode formā (NFD: "e"+kombinējošā
    // garumzīme), bet šī faila konstantes ir NFC ("ē" viens kodpunkts) — baitu
    // salīdzinājums tāpēc klusi nesakrita un 'Iespēja/' izslēgšana nedarbojās.
    // Normalizējam ceļu uz NFC; bez intl paplašinājuma — vismaz ē gadījumu.
    $rel = class_exists('Normalizer')
        ? (Normalizer::normalize($rel, Normalizer::FORM_C) ?: $rel)
        : str_replace("e\xCC\x84", "\xC4\x93", $rel);

    foreach (KEEP_DIRS as $keep) {
        if (str_starts_with($rel, $keep)) return false;
    }
    foreach (EXCLUDE_DIRS as $dir) {
        if (str_starts_with($rel, $dir)) return true;
    }
    if (in_array($rel, EXCLUDE_FILES, true)) return true;

    $base = basename($rel);
    foreach (EXCLUDE_GLOBS as $glob) {
        if (fnmatch($glob, $base)) return true;
    }
    return false;
}

/**
 * Rekursīvi savāc iekļaujamos failus. Atgriež [ceļš pakotnē => pilnais ceļš avotā].
 * Izslēgtās mapes tiek nogrieztas jau apstaigāšanas laikā (citādi skripts
 * velti apstaigātu 1,9 GB csv/ un 3,3 GB konkursi/db/).
 *
 * $targetPrefix ļauj savākt avotu, kas pakotnē nonāk citā mapē (EXTRA_SOURCES).
 * Izslēgšanu vienmēr pārbaudām pēc ceļa PAKOTNĒ, lai visiem avotiem būtu viens
 * mehānisms — EXCLUDE_FILES ieraksti tāpēc rakstīti ar mērķa mapes prefiksu.
 */
function collect(string $root, string $targetPrefix = ''): array
{
    $prefix = strlen($root) + 1;
    $inner  = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);

    $filter = new RecursiveCallbackFilterIterator(
        $inner,
        static function (SplFileInfo $info) use ($prefix, $targetPrefix): bool {
            $rel = $targetPrefix . str_replace('\\', '/', substr($info->getPathname(), $prefix));
            return !is_excluded($info->isDir() ? $rel . '/' : $rel);
        }
    );

    $out = [];
    foreach (new RecursiveIteratorIterator($filter) as $path => $info) {
        if (!$info->isFile()) continue;
        $out[$targetPrefix . str_replace('\\', '/', substr((string)$path, $prefix))] = (string)$path;
    }
    ksort($out);
    return $out;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $p) { $p->isDir() ? @rmdir((string)$p) : @unlink((string)$p); }
    @rmdir($dir);
}

function human(int $bytes): string
{
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float)$bytes;
    while ($n >= 1024 && $i < count($u) - 1) { $n /= 1024; $i++; }
    return sprintf('%.1f %s', $n, $u[$i]);
}

// ─────────────────────────────────────────────────────────────────────────────
// BŪVE
// ─────────────────────────────────────────────────────────────────────────────

say("Būvēju " . PKG_NAME . ".zip no: $ROOT");

if (!class_exists(ZipArchive::class)) fail('trūkst PHP paplašinājuma "zip".');

rrmdir($STAGE);
if (!mkdir($STAGE, 0755, true)) fail("neizdevās izveidot pagaidu mapi: $STAGE");

// --- 1. Savācam un kopējam ---------------------------------------------------
$files = collect($ROOT);
say('  Atlasīti faili: ' . count($files));

foreach (EXTRA_SOURCES as $srcRel => $dstDir) {
    $abs = realpath($ROOT . '/' . $srcRel);
    if ($abs === false || !is_dir($abs)) {
        fail("papildu avots nav atrasts: $ROOT/$srcRel\n"
           . "    Bez tā pakotnē nonāktu iespeja.php bez neviena skripta, kas uzbūvē\n"
           . "    tā MySQL tabulas — lejupielādētājam sadaļa nestrādātu.\n"
           . "    Ja mape pārvietota, izlabo EXTRA_SOURCES šī skripta augšā.");
    }
    $extra = collect($abs, $dstDir . '/');
    if (!$extra) fail("papildu avots ir tukšs: $abs");
    $files += $extra;
    say("  Papildu avots $srcRel → $dstDir/: " . count($extra) . ' faili');
}

$stubs   = stub_files();
$scrubbed = [];
$copied  = 0;

foreach ($files as $rel => $src) {
    $dst = $STAGE . '/' . PKG_NAME . '/' . $rel;
    $dir = dirname($dst);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) fail("neizdevās izveidot mapi: $dir");

    // Pilnībā aizstājamie faili.
    if (isset($stubs[$rel])) {
        file_put_contents($dst, $stubs[$rel] . "\n");
        $scrubbed[$rel] = 'aizstāts ar paraugu';
        $copied++;
        continue;
    }

    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, TEXT_EXT, true)) {
        if (!copy($src, $dst)) fail("neizdevās nokopēt: $rel");
        $copied++;
        continue;
    }

    // Teksta fails — piemērojam aizstāšanas.
    $body = (string)file_get_contents($src);
    $hits = [];
    foreach (secrets()['scrub'] as $needle => $replacement) {
        $n = substr_count($body, $needle);
        if ($n > 0) {
            $body = str_replace($needle, $replacement, $body);
            $hits[] = "$needle ×$n";
        }
    }
    if ($hits) $scrubbed[$rel] = implode(', ', $hits);
    file_put_contents($dst, $body);
    $copied++;
}
say("  Nokopēti: $copied");

// --- 2. Iekļaujam pašu būves skriptu (caurspīdīgumam) ------------------------
@mkdir($STAGE . '/' . PKG_NAME . '/tools', 0755, true);
copy(__FILE__, $STAGE . '/' . PKG_NAME . '/tools/build_download.php');

// --- 3. Juridiskie un pavadošie dokumenti -----------------------------------
$docs = docs();
foreach ($docs as $name => $text) {
    file_put_contents($STAGE . '/' . PKG_NAME . '/' . $name, $text);
}
say('  Pievienoti dokumenti: ' . implode(', ', array_keys($docs)));

// --- 4. Tīrīšanas atskaite ---------------------------------------------------
if ($scrubbed) {
    say('  Iztīrīti noslēpumi ' . count($scrubbed) . ' failos:');
    foreach ($scrubbed as $rel => $what) say("      · $rel — $what");
} else {
    say('  Brīdinājums: nekas netika iztīrīts (vai avots jau ir tīrs?).');
}

// --- 5. Drošības pārbaude ----------------------------------------------------
say('  Pārbaudu, vai nav palikuši noslēpumi...');
$leaks = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($STAGE, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $p) {
    if (!$p->isFile()) continue;
    $ext = strtolower($p->getExtension());
    if (!in_array($ext, TEXT_EXT, true)) continue;
    $body = (string)file_get_contents((string)$p);
    foreach (secrets()['forbidden'] as $re => $label) {
        if (preg_match($re, $body)) {
            $rel = substr((string)$p, strlen($STAGE) + 1);
            $leaks[] = "$rel — $label";
        }
    }
}
if ($leaks) {
    rrmdir($STAGE);
    fail("pakotnē palikuši noslēpumi, ZIP netika izveidots:\n    - " . implode("\n    - ", $leaks));
}
say('  ✓ Noslēpumi nav atrasti.');

// --- 6. ZIP ------------------------------------------------------------------
@unlink($OUT_ZIP);
$zip = new ZipArchive();
if ($zip->open($OUT_ZIP, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    rrmdir($STAGE);
    fail("neizdevās izveidot ZIP: $OUT_ZIP");
}
$n = 0;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($STAGE, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $p) {
    if (!$p->isFile()) continue;
    $rel = str_replace('\\', '/', substr((string)$p, strlen($STAGE) + 1));
    $zip->addFile((string)$p, $rel);
    $n++;
}
$zip->close();
rrmdir($STAGE);

say('');
say('✓ Gatavs: ' . $OUT_ZIP);
say('  Faili ZIP: ' . $n);
say('  Izmērs:    ' . human((int)filesize($OUT_ZIP)));

// ─────────────────────────────────────────────────────────────────────────────
// PAVADOŠIE DOKUMENTI
// ─────────────────────────────────────────────────────────────────────────────

function docs(): array
{
    $year = date('Y');

    $license = <<<TXT
MIT licence

Autortiesības (c) $year Saraksts.lv

Ar šo bez maksas tiek dota atļauja jebkurai personai, kas iegūst šīs
programmatūras un ar to saistīto dokumentācijas failu ("Programmatūra") kopiju,
rīkoties ar Programmatūru bez ierobežojumiem, tostarp bez ierobežojumiem
izmantot, kopēt, pārveidot, apvienot, publicēt, izplatīt, apakšlicencēt un/vai
pārdot Programmatūras kopijas, un atļaut to darīt personām, kurām Programmatūra
tiek nodota, ievērojot šādus nosacījumus:

Iepriekš minētajam autortiesību paziņojumam un šim atļaujas paziņojumam jābūt
iekļautam visās Programmatūras kopijās vai būtiskās tās daļās.

PROGRAMMATŪRA TIEK PIEGĀDĀTA "TĀDA, KĀDA TĀ IR", BEZ JEBKĀDA VEIDA TIEŠĀM VAI
NETIEŠĀM GARANTIJĀM, TOSTARP, BET NE TIKAI, GARANTIJĀM PAR PIEMĒROTĪBU PĀRDOŠANAI,
ATBILSTĪBU KONKRĒTAM MĒRĶIM UN TIESĪBU NEPĀRKĀPŠANU. AUTORI VAI AUTORTIESĪBU
ĪPAŠNIEKI NEKĀDĀ GADĪJUMĀ NEATBILD PAR JEBKĀDĀM PRASĪBĀM, ZAUDĒJUMIEM VAI CITU
ATBILDĪBU, VAI TĀ BŪTU LĪGUMA, DELIKTA VAI CITA VEIDA PRASĪBA, KAS IZRIET NO
PROGRAMMATŪRAS VAI TĀS IZMANTOŠANAS, VAI CITĀM DARBĪBĀM AR PROGRAMMATŪRU.

---

ENGLISH (authoritative for international use)

MIT License

Copyright (c) $year Saraksts.lv

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

SVARĪGI: MIT licence attiecas uz ŠĪ PROJEKTA AUTORA rakstīto kodu.
Pakotnē ir iekļautas arī trešo pušu sastāvdaļas ar CITĀM licencēm — īpaši
horoskops/swisseph/ (AGPL-3.0), kas uzliek papildu pienākumus.
Pilnu sarakstu skat. NOTICE.md. Pirms izplatīšanas izlasi to.
TXT;

    $notice = <<<MD
# NOTICE — trešo pušu sastāvdaļas un to licences

Projekta autora kods ir MIT licencē (skat. `LICENSE`). Šajā pakotnē tomēr ir
iekļautas arī citu autoru bibliotēkas un datu kopas, kurām ir **savas licences**.
MIT licence uz tām **neattiecas** un autors nevar piešķirt tiesības, kas tam
nepieder. Zemāk ir pilns saraksts.

---

## ⚠️ Vissvarīgākais: Swiss Ephemeris (AGPL-3.0)

**Atrašanās vieta:** `horoskops/swisseph/`
**Autors:** Astrodienst AG, Šveice
**Licence:** GNU Affero General Public License v3 (`horoskops/swisseph/LICENSE`)

Swiss Ephemeris ir **duāli licencēts**: AGPL-3.0 **vai** maksas Professional licence
no Astrodienst. Astrodienst formulējums: izvēle jāizdara *pirms* koda izplatīšanas
citiem **un pirms tiek aktivizēts jebkurš publisks serviss**, kas to lieto.

Sadaļa `horoskops/` izsauc Swiss Ephemeris (WASM), tāpēc **uz šo apakšsistēmu
attiecas AGPL-3.0, nevis MIT**. Pārējās projekta daļas Swiss Ephemeris neizmanto.

### Ja tu publicē Horoskopu tīmeklī

Tev jāizvēlas viens no trim ceļiem:

1. **AGPL ceļš (bez maksas).** Piedāvā sava Horoskopa pilnu pirmkodu AGPL-3.0
   licencē tiem, kas lapu lieto. AGPL 13. pants prasa šo piedāvājumu izdarīt
   *redzami* — praktiski pietiek ar skaidru saiti uz pirmkodu pašā lapā.
   MIT ir AGPL-saderīga, tāpēc kopīgie MIT gabali (galvene, kājene) nav problēma:
   apvienoto darbu izplati ar AGPL.
2. **Maksas ceļš.** Nopērc Swiss Ephemeris Professional licenci
   (https://www.astro.com/swisseph/) — tad pirmkods nav jāatklāj.
3. **Izņem ārā.** Izdzēs mapi `horoskops/` un `horoskops.php`. Reģistrs, Nozare,
   Struktūra, Konkursi, Iespēja un Pensionārs no tās nav atkarīgi un paliek MIT.

### Ko licence NEIEROBEŽO: rezultātus

Licence attiecas uz **programmatūru**, nevis uz to, ko tā aprēķina. Horoskopi,
kartes, interpretācijas un planētu pozīcijas, ko lietotne uzģenerē, tev pieder —
tos drīksti publicēt, pārdot un izmantot bez ierobežojumiem. Planētu pozīcijas
turklāt ir fakti, un fakti nav aizsargājami ar autortiesībām.

### Papildu nosacījums

Astrodienst licence prasa saglabāt autortiesību paziņojumus visās kopijās un
aizliedz izmantot autoru vai Astrodienst vārdu savas programmatūras, produkta
vai pakalpojuma reklamēšanai.

> Šis ir praktisks kopsavilkums, nevis juridiska konsultācija. Ja par savu
> gadījumu neesi drošs, jautā Astrodienst — viņi atbild publiskajā sarakstē
> https://groups.io/g/swisseph

---

## Iekļautās programmbibliotēkas

| Sastāvdaļa | Atrašanās vieta | Licence | Pienākums |
|---|---|---|---|
| Swiss Ephemeris | `horoskops/swisseph/` | AGPL-3.0 vai komerciāla | skat. augšā |
| Moment.js | `horoskops/js/timezone/moment.min.js` | MIT | saglabāt paziņojumu |
| Moment Timezone | `horoskops/js/timezone/moment-timezone-with-data.js` | MIT | saglabāt paziņojumu |
| D3.js | `registrs/assets/js/lib/d3.min.js` | ISC | saglabāt paziņojumu |
| d3-sankey | `registrs/assets/js/lib/d3-sankey.min.js` | BSD-3-Clause | saglabāt paziņojumu |
| Chart.js | `registrs/assets/js/lib/chart.umd.min.js` | MIT | saglabāt paziņojumu |
| marked | `registrs/assets/js/lib/marked.min.js` | MIT | saglabāt paziņojumu |
| CookieConsent (Orest Bida) | `registrs/cookie/cookieconsent.umd.js`, `.css` | MIT | saglabāt paziņojumu |

Bibliotēkas, ko lapas ielādē no CDN un kas **nav** šajā pakotnē: Font Awesome
(CC BY 4.0 ikonas / MIT kods), Leaflet (BSD-2-Clause), Google Fonts (OFL).

## Iekļautās datu kopas

| Datu kopa | Atrašanās vieta | Avots un licence |
|---|---|---|
| O\\*NET profesiju dati | `horoskops/onet/*.csv` | O\\*NET® datubāze, ASV Darba departaments. **CC BY 4.0 — prasa atsauci uz O\\*NET.** O\\*NET ir ASV DOL reģistrēta preču zīme. |
| NACE klasifikators | `registrs/build/NACE.csv`, `nozare/`, `pensionars/` | Eurostat / CSP saimniecisko darbību klasifikācija — publiska |
| Nozaru attēli | `nozare/nace-foto/*.webp` | Ģenerēti ar MI (Midjourney). Iekļauti tāpēc, ka kods tos nevar lejupielādēt. Pārbaudi sava MI rīka noteikumus, ja tos izplati tālāk. |
| CA starpsertifikāti | `konkursi/data/ca/*.pem` | publiski sertificēšanas iestāžu sertifikāti |
| Tūrisma objekti | `iespeja/Turisma objekti.txt` | © OpenStreetMap contributors, **ODbL 1.0** — skat. zemāk |

### OpenStreetMap dati (ODbL 1.0)

Fails `iespeja/Turisma objekti.txt` ir OpenStreetMap izgūtne (Overpass API), un
Iespējas konveijera 1. un 9. solis lejupielādē vēl vairāk OSM datu. Uz tiem attiecas
**Open Database License 1.0**, nevis MIT:

* publicējot rezultātus, jānorāda **© OpenStreetMap contributors**;
* ja izplati **atvasinātu datubāzi** (piemēram, savu MySQL slāni), ODbL prasa to
  darīt ar tādiem pašiem noteikumiem;
* aprēķinātus rezultātus (kartes attēlus, aplēses) drīksti publicēt ar atsauci.

https://www.openstreetmap.org/copyright

Iespējas konveijers lejupielādē arī **VZD kadastra atvērtos datus** (data.gov.lv,
grafws.kadastrs.lv) — tiem savi lietošanas noteikumi.

## Publiskās API atslēgas, kas palikušas kodā

Šīs **nav** autora personiskās atslēgas — tās ir publiski dokumentētas testa /
parauga atslēgas, un tās ir atstātas, lai kods darbotos uzreiz:

* `konkursi/lib/config.php` — `CVPIS_API_KEY` (Lietuvas VPT publiskā testa atslēga;
  produkcijai pieprasi savu: pagalba@vpt.lt)
* `konkursi/lib/config.php` — `HILMA_API_KEY` (Somijas bezmaksas izstrādātāju portāla atslēga)
* `konkursi/lib/config.php` — `BOSA_CLIENT_SECRET` (Beļģijas anonīmais Keycloak klients;
  publicēts viņu pašu `env.config.js`)

Nopietnai lietošanai iegūsti savas atslēgas.
MD;

    $readme = <<<MD
# Saraksts.lv — pilnais pirmkods

Šī ir **dāvana**. Kods ir brīvi izmantojams — attīsti tālāk, pārveido, izmanto
savām darba vajadzībām vai komerciāli. Sīkāk: `LICENSE` un `NOTICE.md`.

Pirms sākt, izlasi **`NOTICE.md`** — īpaši sadaļu par Swiss Ephemeris.

---

## Kas ir iekšā

Viena PHP koda bāze, kas apkalpo astoņas sadaļas:

| Sadaļa | Fails | Ko dara |
|---|---|---|
| Reģistrs | `index.php`, `company.php` | LV uzņēmumu meklēšana un individuālās lapas |
| Nozare | `nozare.php` | nozaru analītika pēc NACE kodiem |
| Struktūra | (būves izvade) | uzņēmumu treemap karte |
| Konkursi | `konkursi.php` | iepirkumi no TED (ES) + ~46 nacionāliem/IFI avotiem, 44 valstis (~200 tūkst. paziņojumu pēc sinhronizācijas) |
| Iespēja | `iespeja.php` + `iespeja/` | ģeotelpiska biznesa potenciāla karte |
| Pensionārs | `pensionars.php` | ilgtermiņa uzņēmumu portfelis |
| Horoskops | `horoskops.php` | statiska astroloģijas lietotne (AGPL — skat. NOTICE.md) |
| Lejupielāde | `lejupielade.php` | šī lapa |

## Kas NAV iekšā (un kāpēc)

Pakotnē ir **tikai kods un tie dati, ko kods nevar iegūt pats**. Nav iekļauts:

* `csv/` — atvērtie dati no data.gov.lv. **Kods tos lejupielādē pats**
  (`registrs/build/download.php` vai admin panelis).
* `*.sqlite`, `*.db` — visas datubāzes. Tās **ģenerē būve** no lejupielādētajiem CSV.
* `struktura/data/`, `struktura.php`, `sitemap/` — būves izvade.
* `registrs/ai_cache/` — MI atbilžu kešs.
* `konkursi/db/tenders.db` — iepirkumu datubāze; to uzbūvē sinhronizācija.

Iekļauts tāpēc, ka **kods to nevar lejupielādēt**: `nozare/nace-foto/` (MI
ģenerēti attēli), `horoskops/onet/` (O\\*NET CSV), `ceturksnis/` (VID ceturkšņu
CSV, kas vairs nav pieejami data.gov.lv), `konkursi/data/ca/` (sertifikāti),
`iespeja/Turisma objekti.txt` (OSM Overpass eksports — vienīgais Iespējas datu
fails, ko konveijers neizgūst pats).

## Sadaļa Iespēja prasa atsevišķu soli

`iespeja.php` neko neaprēķina no failiem — visi tās dati nāk no **MySQL telpiskās
datubāzes**. Mapē **`iespeja/`** ir datu konveijers (9 soļi, tīrs PHP — Python
nevajag), kas to datubāzi uzbūvē no OpenStreetMap un VZD kadastra atvērtajiem
datiem. Bez tā palaišanas lapa atveras, bet katrs klikšķis kartē atgriež tukšumu.

Konveijeram vajag to pašu PHP 8.1+, MySQL 8+ ar telpisko atbalstu, ~7 GB brīvas
vietas starpfailiem un ~2–4 h. Soli pa solim: **`iespeja/README.md`**.

## Noslēpumi ir noņemti

No šīs pakotnes ir izņemtas visas autora atslēgas un paroles. Lai sistēma pilnībā
strādātu, ieliec savas:

| Fails | Kas jāieliek |
|---|---|
| `admin_token.php` | tavs slepenais admin tokens (vai `ADMIN_TOKEN` vides mainīgais) |
| `registrs/mi/key.php` | Google Gemini API atslēga (vai `GEMINI_API_KEY`) — neobligāti, MI funkcijām |
| `iespeja.php` (augšā) | MySQL resursdators / DB / lietotājs / parole telpiskajai datubāzei |
| `iespeja/php/config.php` | tie paši MySQL dati konveijeram (vai `IESPEJA_DB_*` vides mainīgie) |

Papildus **noteikti nomaini**, ja publicē vietni:

* Google Analytics ID `G-XXXXXXXXXX` (vai izdzēs gtag blokus).
* E-pasta adreses `info@example.com` / `admin@example.com`.
* `registrs/cookie/cookieconsent-init.js` — sīkdatņu un VDAR teksti ir rakstīti
  konkrēti saraksts.lv. **Tie ir juridiski saistoši tavai vietnei** — pārraksti tos.

## Uzstādīšana īsumā

1. Vajag **PHP 8.1+** (ieteicams 8.3+) ar `pdo_sqlite`, `zip`, `curl`, `mbstring`.
2. Augšupielādē saturu domēna saknē (`public_html/`).
3. Pārsauc `htaccess.txt` → `.htaccess`.
4. Nomaini `admin_token.php`.
5. Atver `/data_admin.php?k=<tavs tokens>` un palaid būvi ("Palaist fonā").
   Pirmā būve lejupielādē ~2 GB atvērto datu un aizņem vairākas stundas.
6. Konkursu sadaļai: `/konkursi_admin.php?k=<tavs tokens>` → sinhronizācija.
7. Sadaļai Iespēja — atsevišķi: MySQL datubāze + `iespeja/README.md`.
   Pārējās septiņas sadaļas no tās nav atkarīgas; ja Iespēja nav vajadzīga,
   izdzēs `iespeja.php` un mapi `iespeja/`.

Sīkāk skat. `README.md` (izstrādātāja piezīmes) un komentārus pašā kodā.

## Godīgi par kvalitāti

Šo kodu praktiski pilnībā ir uzrakstījis mākslīgais intelekts (Claude un Gemini),
attēlus — Midjourney. Kods apstrādā lielus datu apjomus, un visas rezultātu
variācijas ir grūti prognozēt. **Kodā var būt loģikas kļūdas, kas dod nepareizu
rezultātu.** Garantiju nav (skat. `LICENSE`). Pārbaudi rezultātus, pirms uz tiem
balsties.

## Par personas datiem — izlasi obligāti

Vairāki skripti lejupielādē no data.gov.lv **fizisku personu datus** (amatpersonas,
patiesā labuma guvēji, dalībnieki — vārdi, uzvārdi, maskēti personas kodi):

* `registrs/build/download.php` — `officers.csv`, `beneficial_owners.csv`, `members.csv`
* būves soļi sadaļām Reģistrs, Struktūra un Pensionārs

Tiklīdz tu šos datus lejupielādē, **tu kļūsti par datu pārzini VDAR (GDPR) izpratnē**.
Tev pašam jānodrošina apstrādes tiesiskais pamats, glabāšanas termiņi, datu subjektu
tiesību ievērošana un informēšana. Pārliecinies, ka tev ir pamatots mērķis
(piemēram, AML/KYC pārbaude). Šī pakotne tev tādu pamatu **nedod**.

Saraksts.lv produkcijā personas datu attēlošana ir apzināti ierobežota. Ja tu
kodu palaid nemainītu, tas var apstrādāt vairāk datu, nekā tev ir tiesības.
MD;

    return [
        'LICENSE'                => $license . "\n",
        'NOTICE.md'              => $notice . "\n",
        'README-PIRMS-SAKAM.md'  => $readme . "\n",
    ];
}
