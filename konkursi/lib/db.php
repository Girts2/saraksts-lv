<?php
/**
 * konkursi/lib/db.php — SQLite savienojums + shēma Konkursu sadaļai.
 *
 * Viena datubāze (konkursi/db/tenders.db) ar `notices` tabulu — glabā tikai
 * atlasītos/apcirstos laukus (pilnais paziņojums vienmēr pieejams TED oriģinālā).
 * Meklēšanai FTS5 (ja SQLite būvē pieejams) ar LIKE atkāpi.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const KONKURSI_SCHEMA_SQL = <<<'SQL'
CREATE TABLE IF NOT EXISTS notices (
    id TEXT PRIMARY KEY,
    source TEXT NOT NULL DEFAULT 'TED',-- 'TED' (ES) | 'IUB' (LV nacionālie)
    category TEXT NOT NULL,            -- 'iepirkumi' | 'rezultati' | 'izmainas' | 'citi'
    title TEXT NOT NULL,
    description TEXT,
    buyer_name TEXT,
    buyer_id TEXT,
    buyer_country TEXT,                -- ISO-2, piem. 'LV'
    buyer_activity TEXT,
    buyer_type TEXT,
    procure_nature TEXT,               -- 'works' | 'supplies' | 'services'
    publication_date TEXT,             -- YYYY-MM-DD
    deadline_date TEXT,                -- YYYY-MM-DD vai NULL
    deadline_time TEXT,
    publication_number TEXT,           -- TED numurs, piem. '412345-2026'
    budget REAL,
    currency TEXT DEFAULT 'EUR',
    document_url TEXT,
    buyer_profile_url TEXT,
    procedure_type TEXT,
    notice_sub_type TEXT,
    notice_lang TEXT,
    issue_date TEXT,
    main_nuts TEXT,
    main_country TEXT,
    funding_program TEXT,
    prev_notice_ref TEXT,
    contract_folder_id TEXT,
    main_cpv TEXT,                     -- galvenais CPV kods filtriem
    cpv_codes TEXT DEFAULT '[]',       -- JSON masīvs
    lots TEXT DEFAULT '[]',            -- JSON masīvs (apcirsts)
    organizations TEXT DEFAULT '[]',   -- JSON masīvs (apcirsts)
    notice_contact TEXT DEFAULT '{}',  -- JSON objekts {name,email,phone}
    source_file TEXT,                  -- piem. '202600123'
    -- Kad MĒS ierakstu pirmoreiz ieraudzījām. Nav avota dati, tāpēc to nerāda;
    -- vajadzīgs kārtošanai avotiem, kas datumus nesniedz vispār (Comdia).
    first_seen TEXT,
    -- Virsraksta LV tulkojums (Gemini caur registrs/mi/gemini_client.php). ATVASINĀTI
    -- dati, ne avota — tāpēc TIKAI pašreizējā skatā, NE notice_versions žurnālā.
    -- KsWriter to neaiztiek upsertā (nav KS_NOTICE_COLS), bet NULLē, ja mainās title.
    title_lv TEXT
);

CREATE INDEX IF NOT EXISTS idx_notices_cat_pubdate  ON notices(category, publication_date DESC, id DESC);
-- Kārtošanai 'jaunākie vispirms': publikācijas datums, bet, ja tā nav (Comdia),
-- pirmās redzēšanas datums. Bez šī izteiksmes indeksa saraksts kārtotu ~66k rindu.
CREATE INDEX IF NOT EXISTS idx_notices_cat_sortdate ON notices(category, COALESCE(publication_date, first_seen) DESC, id DESC);
CREATE INDEX IF NOT EXISTS idx_notices_cat_deadline ON notices(category, deadline_date);
CREATE INDEX IF NOT EXISTS idx_notices_cat_budget   ON notices(category, budget DESC);

-- ── Displeja loga indeksi (lapas starta karstais ceļš) ──────────────────────
-- Katrs skats filtrē pēc "displeja loga": aktīvie = deadline_date >= šodien VAI bez
-- termiņa un pietiekami svaigs (COALESCE(publication_date, first_seen)); rezultāti/
-- grozījumi/citi = publication_date logā. Sk. konkursi.php $activeCond/$resultCond.
--   Šīs kolonnas nebija nevienā indeksā, tāpēc katrs cilnes skaitītājs, avotu panelis
-- un fasete izskrēja cauri ~106k `iepirkumi` rindām, katrai taisot atsevišķu rindas
-- lasījumu ~1 GB failā. Lapas startā tas notiek 5 vaicājumos paralēli → ~5 s līdz
-- lapa kļūst lietojama. Ar loga kolonnām indeksos plāni kļūst par COVERING INDEX un
-- tabula netiek aiztikta vispār.
--   Mērījumi (182k rindas, 960 MB): action=sources 3339 → 156 ms, "Aktīvie" skaitītājs
-- 2878 → 9 ms, pasūtītāju izvēlne 1198 → 27 ms, valstu izvēlne 106 → 7 ms.
--   Kārtošanu tas neaiztiek — 'jaunākie' joprojām iet pa idx_notices_cat_sortdate.
--   UZMANĪBU: idx_notices_cat_country un idx_notices_cat_cpv ŠEIT ir paplašinātie.
-- Esošā datubāzē tie jau pastāv ar veco (šauro) definīciju, un CREATE INDEX IF NOT
-- EXISTS tos NEPĀRBŪVĒ — pārbūvi veic konkursi/bin/migrate_display_index.php.

-- Skaitītājiem, avotu panelim un pasūtītāju izvēlnei: category (vienādība) →
-- deadline_date (diapazons pirmajam OR zaram) → publication_date/first_seen (otrais
-- zars + rezultātu logs) → GROUP BY kolonnas.
CREATE INDEX IF NOT EXISTS idx_notices_display ON notices(
    category, deadline_date, publication_date, first_seen,
    source, buyer_country, main_cpv, buyer_name);
-- Valstu un CPV izvēlnēm grupēšanas kolonnai jābūt PIRMS loga kolonnām, citādi
-- plānotājs izvēlas šauro indeksu un atgriežas pie rindu lasījumiem tabulā.
CREATE INDEX IF NOT EXISTS idx_notices_cat_country ON notices(
    category, buyer_country, deadline_date, publication_date, first_seen);
CREATE INDEX IF NOT EXISTS idx_notices_cat_cpv     ON notices(
    category, main_cpv, deadline_date, publication_date, first_seen);
-- Dublikātu meklēšanai (ks_dedupe_notices): bez šī grupēšana pa procedūras
-- identifikatoru uz ~160k rindām prasa minūtes.
CREATE INDEX IF NOT EXISTS idx_notices_cat_folder   ON notices(category, contract_folder_id);
-- TED↔nacionālo dedup (ks_dedupe_vs_ted) EXISTS-apakšvaicājumam: bez šī izteiksmes
-- indeksa 7 valstu skenēšana pār ~146k rindām prasa 15+ MINŪTES katrā sinhronizācijā.
CREATE INDEX IF NOT EXISTS idx_notices_dedup_ted ON notices(
    source, buyer_country, category, lower(trim(title)), lower(trim(buyer_name)));

-- Kuras TED paketes jau importētas (inkrementālais sync + TED netiek prasīts atkārtoti)
CREATE TABLE IF NOT EXISTS imported_files (
    file_key TEXT PRIMARY KEY,         -- piem. 'TED:202600123'
    imported_at TEXT NOT NULL,
    notice_count INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS meta (
    k TEXT PRIMARY KEY,
    v TEXT
);

-- NEMAINĪGS (append-only) versiju žurnāls: katra novērotā ieraksta versija.
-- Pēc ievietošanas rindu NEKAD neaiztiek (ne UPDATE, ne DELETE) — tā ir "fiksētā"
-- patiesība. `notices` augstāk ir tikai atvasināts pašreizējais skats (jaunākā
-- versija/id), ko lasa web lapa + FTS. Sk. lib/store.php (KsWriter).
CREATE TABLE IF NOT EXISTS notice_versions (
    id TEXT NOT NULL,
    version_no INTEGER NOT NULL,       -- 1, 2, 3 … pieaug ar katru satura izmaiņu
    observed_at TEXT NOT NULL,         -- ISO-8601: kad MĒS šo versiju ieraudzījām
    content_hash TEXT NOT NULL,        -- sha256 pār saturu (identisks = tā pati versija)
    source TEXT,
    category TEXT,
    title TEXT,
    description TEXT,
    buyer_name TEXT,
    buyer_id TEXT,
    buyer_country TEXT,
    buyer_activity TEXT,
    buyer_type TEXT,
    procure_nature TEXT,
    publication_date TEXT,
    deadline_date TEXT,
    deadline_time TEXT,
    publication_number TEXT,
    budget REAL,
    currency TEXT,
    document_url TEXT,
    buyer_profile_url TEXT,
    procedure_type TEXT,
    notice_sub_type TEXT,
    notice_lang TEXT,
    issue_date TEXT,
    main_nuts TEXT,
    main_country TEXT,
    funding_program TEXT,
    prev_notice_ref TEXT,
    contract_folder_id TEXT,
    main_cpv TEXT,
    cpv_codes TEXT,
    lots TEXT,
    organizations TEXT,
    notice_contact TEXT,
    source_file TEXT,
    PRIMARY KEY (id, version_no)
);
CREATE INDEX IF NOT EXISTS idx_versions_observed ON notice_versions(observed_at);

-- Per-avots ūdenszīme: kolektors ievāc tikai jaunāko par (watermark − pārklājums),
-- tā izvairoties no jau izpētītu dienu atkārtotas skenēšanas. Sk. lib/store.php.
CREATE TABLE IF NOT EXISTS source_state (
    source TEXT PRIMARY KEY,
    watermark_date TEXT,               -- YYYY-MM-DD: jaunākais sekmīgi ievāktais publication_date
    last_cursor TEXT,                  -- avota-specifisks kursors (ja avotam tāds ir)
    last_run_at TEXT,                  -- ISO-8601
    last_new INTEGER DEFAULT 0,
    last_versions INTEGER DEFAULT 0,
    last_unchanged INTEGER DEFAULT 0
);
SQL;

// Paceļ, kad mainās notices_fts definīcija — atvēršana pārbūvēs indeksu.
const KONKURSI_FTS_SCHEMA_VER = '2';

const KONKURSI_FTS_SQL = <<<'SQL'
-- prefix='2 3': īsu prefiksu meklēšana ("me"*, "gaz"*) kļūst par tiešu indeksa
-- lasījumu — bez tā FTS5 apvieno tūkstošiem termu sarakstus (sekundes→minūtes uz
-- lēna servera diska). detail='none' + columnsize=0: mēs nelietojam ne frāžu
-- meklēšanu, ne bm25 ranžēšanu (kārtojam pēc datuma), tāpēc pozīciju/izmēru dati
-- ir balasts — bez tiem indekss ir ~uz pusi mazāks un visi vaicājumi lētāki.
CREATE VIRTUAL TABLE IF NOT EXISTS notices_fts USING fts5(
    title, title_lv, buyer_name, description,
    content='notices', content_rowid='rowid',
    tokenize='unicode61 remove_diacritics 2',
    prefix='2 3',
    detail='none',
    columnsize=0
);
CREATE TRIGGER IF NOT EXISTS notices_fts_ai AFTER INSERT ON notices BEGIN
    INSERT INTO notices_fts(rowid, title, title_lv, buyer_name, description)
    VALUES (new.rowid, new.title, new.title_lv, new.buyer_name, new.description);
END;
CREATE TRIGGER IF NOT EXISTS notices_fts_ad AFTER DELETE ON notices BEGIN
    INSERT INTO notices_fts(notices_fts, rowid, title, title_lv, buyer_name, description)
    VALUES ('delete', old.rowid, old.title, old.title_lv, old.buyer_name, old.description);
END;
CREATE TRIGGER IF NOT EXISTS notices_fts_au AFTER UPDATE ON notices BEGIN
    INSERT INTO notices_fts(notices_fts, rowid, title, title_lv, buyer_name, description)
    VALUES ('delete', old.rowid, old.title, old.title_lv, old.buyer_name, old.description);
    INSERT INTO notices_fts(rowid, title, title_lv, buyer_name, description)
    VALUES (new.rowid, new.title, new.title_lv, new.buyer_name, new.description);
END;
SQL;

/** Atver DB (izveido failu + shēmu, ja vēl nav). */
function konkursi_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $path = konkursi_db_path();
    $isNew = !is_file($path) || filesize($path) < 1024;
    if ($isNew) @mkdir(dirname($path), 0775, true);

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout=8000');
    if ($isNew) {
        // auto_vacuum jāiestata PIRMS pirmās tabulas — ļauj vēlāk atbrīvot vietu bez pilna VACUUM
        $pdo->exec('PRAGMA auto_vacuum=INCREMENTAL');
    }
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA synchronous=NORMAL');
    // Lasīšanas ceļš uz lēna servera diska: mmap samazina sistēmizsaukumus un ļauj
    // OS kešam apkalpot atkārtotus lasījumus; lielāks lapu kešs (64 MB; noklusējums
    // ~2 MB) notur FTS indeksa karstās lapas starp vaicājumiem viena procesa ietvaros.
    $pdo->exec('PRAGMA mmap_size=268435456');
    $pdo->exec('PRAGMA cache_size=-65536');
    $pdo->exec(KONKURSI_SCHEMA_SQL);

    // Migrācija: 'source' kolonna DB failiem, kas izveidoti pirms IUB plūsmas pievienošanas.
    $hasSource = false;
    foreach ($pdo->query('PRAGMA table_info(notices)') as $col) {
        if (($col['name'] ?? '') === 'source') { $hasSource = true; break; }
    }
    if (!$hasSource) {
        $pdo->exec("ALTER TABLE notices ADD COLUMN source TEXT NOT NULL DEFAULT 'TED'");
    }

    // Migrācija: 'first_seen' DB failiem, kas izveidoti pirms Comdia avota.
    $hasSeen = false;
    foreach ($pdo->query('PRAGMA table_info(notices)') as $col) {
        if (($col['name'] ?? '') === 'first_seen') { $hasSeen = true; break; }
    }
    if (!$hasSeen) {
        $pdo->exec('ALTER TABLE notices ADD COLUMN first_seen TEXT');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notices_cat_sortdate ON notices(category, COALESCE(publication_date, first_seen) DESC, id DESC)');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notices_source_cat ON notices(source, category)');

    // Migrācija: 'title_lv' DB failiem, kas izveidoti pirms tulkošanas atbalsta.
    $hasLv = false;
    foreach ($pdo->query('PRAGMA table_info(notices)') as $col) {
        if (($col['name'] ?? '') === 'title_lv') { $hasLv = true; break; }
    }
    if (!$hasLv) {
        $pdo->exec('ALTER TABLE notices ADD COLUMN title_lv TEXT');
    }
    // Tulkošanai (ks_translate_new_titles): UPDATE ... WHERE title = ? AND title_lv
    // IS NULL bez šī skenēja VISU tabulu katram virsrakstam (~2 s × 40 paketē —
    // lauvas tiesa no ~3 h tulkošanas fāzes). Daļējais indekss satur tikai
    // netulkotās rindas: mazs, un pats sarūk līdz tukšam, kad viss iztulkots.
    // ŠEIT (ne KONKURSI_SCHEMA_SQL), jo vecā DB bez title_lv uz to nokristu.
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notices_title_untr ON notices(title) WHERE title_lv IS NULL');

    // FTS5 var nebūt iekompilēts katrā SQLite būvē — mēģina, atceras rezultātu meta tabulā.
    $fts = konkursi_meta_get($pdo, 'fts');
    if ($fts === null) {
        try {
            $pdo->exec(KONKURSI_FTS_SQL);
            konkursi_meta_set($pdo, 'fts', '1');
            konkursi_meta_set($pdo, 'fts_schema', KONKURSI_FTS_SCHEMA_VER);
        } catch (Throwable $e) {
            konkursi_meta_set($pdo, 'fts', '0');
        }
    }

    // Migrācija: FTS ar vecāku shēmu (bez title_lv, bez prefix/detail opcijām) →
    // pārbūvē. Versiju glabā meta, jo virtuālās tabulas opcijas PRAGMA neatklāj.
    if (konkursi_fts_enabled($pdo) && konkursi_meta_get($pdo, 'fts_schema') !== KONKURSI_FTS_SCHEMA_VER) {
        $pdo->exec('DROP TRIGGER IF EXISTS notices_fts_ai');
        $pdo->exec('DROP TRIGGER IF EXISTS notices_fts_ad');
        $pdo->exec('DROP TRIGGER IF EXISTS notices_fts_au');
        $pdo->exec('DROP TABLE IF EXISTS notices_fts');
        $pdo->exec(KONKURSI_FTS_SQL);
        $pdo->exec("INSERT INTO notices_fts(notices_fts) VALUES('rebuild')");
        konkursi_meta_set($pdo, 'fts_schema', KONKURSI_FTS_SCHEMA_VER);
    }
    return $pdo;
}

function konkursi_meta_get(PDO $pdo, string $k): ?string {
    $st = $pdo->prepare('SELECT v FROM meta WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string)$v;
}

function konkursi_meta_set(PDO $pdo, string $k, string $v): void {
    $st = $pdo->prepare('INSERT INTO meta(k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
    $st->execute([$k, $v]);
}

function konkursi_fts_enabled(PDO $pdo): bool {
    return konkursi_meta_get($pdo, 'fts') === '1';
}

/**
 * Noņem latviešu lietvārdu/īpašības vārdu galotni, lai prefiksa meklēšana atrod
 * visus locījumus ('medikamentu' → 'medikament' → medikamenti/-u/-iem…).
 * Celms vienmēr ir termina prefikss, tāpēc rezultātu kopa tikai paplašinās.
 */
function konkursi_fts_stem(string $t): string {
    static $suffixes = [
        'ajiem', 'ajām', 'ajās', 'ajos', 'ajam', 'ajai',
        'iem', 'ais', 'ajā',
        'ām', 'ās', 'ēm', 'ēs', 'īm', 'īs', 'am', 'ai', 'as', 'ie', 'ei', 'es', 'os', 'us', 'im', 'is', 'um', 'ū',
        'a', 'ā', 'e', 'ē', 'i', 'ī', 'o', 's', 'š', 'u',
    ];
    $lower = mb_strtolower($t, 'UTF-8');
    foreach ($suffixes as $suf) {
        if (str_ends_with($lower, $suf)) {
            $stem = mb_substr($lower, 0, mb_strlen($lower, 'UTF-8') - mb_strlen($suf, 'UTF-8'), 'UTF-8');
            if (mb_strlen($stem, 'UTF-8') >= 4) return $stem;
            break; // galotnes sakārtotas no garākās — īsāka tikai saīsinātu celmu vēl vairāk
        }
    }
    return $lower;
}

/** Lietotāja meklēšanas frāze → droša FTS5 MATCH virkne ("celms"* UN "celms"*).
 *  1 rakstzīmes termi tiek izlaisti: tiem nav prefiksu indeksa (prefix='2 3'),
 *  un galvenajā indeksā tie apvienotu simtiem tūkstošu termu sarakstus. */
function konkursi_fts_query(string $q): string {
    $terms = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $parts = [];
    foreach (array_slice($terms, 0, 8) as $t) {
        $t = konkursi_fts_stem(str_replace('"', '', $t));
        if (mb_strlen($t, 'UTF-8') < 2) continue;
        $parts[] = '"' . $t . '"*';
    }
    return implode(' ', $parts);
}
