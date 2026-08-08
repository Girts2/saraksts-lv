<?php
/**
 * Iespēja/php/pbf.php — OpenStreetMap .osm.pbf lasītājs TĪRĀ PHP.
 *
 * KĀPĒC TĪRĀ PHP, NEVIS `osmium`. Mērķa vide ir Hostinger Cloud, kur nav root,
 * tāpēc ne `apt install osmium-tool`, ne kompilators nav pieejams. Binārs rīks
 * nozīmētu, ka konveijeru vairs nevar palaist tur, kur tas dzīvo. Šeit nav
 * nevienas ārējas atkarības — tikai `zlib` (blobu atspiešanai), kas PHP ir
 * praktiski vienmēr.
 *
 * KO TAS AIZSTĀJ. 1. un 9. solis POI ievāca no Overpass API. Tā ir brīvprātīgo
 * uzturēta infrastruktūra, un tā klūp: pēdējā palaišanā galvenā instance bija
 * pilnībā nesasniedzama, un 9. solis caur rezerves serveriem vilkās 43 minūtes.
 * Šis lasītājs to pašu izdara no viena faila, ko lejupielādē vienreiz.
 * Lielajām valstīm tā vairs nav ērtība, bet vienīgais ceļš — `shop=convenience`
 * visā Vācijā vienā Overpass vaicājumā nepabeidzas nekad.
 *
 * IZVADES FORMĀTS SAKRĪT AR OVERPASS. Atgriež `['elements' => [...]]`, kur katrs
 * elements ir `['type','id','lat','lon','tags']` — tieši tas, ko gaida
 * ie_osm_latlon() un abi soļi. Tāpēc avota nomaiņa soļos ir viena rinda.
 *
 * ── PBF FORMĀTS ĪSUMĀ ──────────────────────────────────────────────────────
 *
 *   [4 baiti BE: BlobHeader garums]
 *   BlobHeader { 1: type ("OSMHeader"|"OSMData"), 3: datasize }
 *   Blob       { 1: raw | 2: raw_size | 3: zlib_data }
 *
 *   OSMData atspiests = PrimitiveBlock {
 *       1: stringtable { 1: repeated bytes s }
 *       2: repeated primitivegroup { 1: nodes, 2: dense, 3: ways, 4: relations }
 *      17: granularity (100)   19: lat_offset   20: lon_offset
 *   }
 *
 * Koordinātas: grāds = 1e-9 * (offset + granularity * summētā delta).
 */
declare(strict_types=1);

/** Cik baitu vienā fread blokā meklējot blobus. */
const IE_PBF_HDR_MAX = 64 * 1024;

// ── Protobuf primitīvi ──────────────────────────────────────────────────────

/**
 * Varint no virknes. $pos tiek pavirzīts.
 *
 * Karstajās cilpās (blīvie mezgli) šis ir ielīmēts uz vietas — izsaukums uz
 * katru no ~30 miljoniem varint būtu pati dārgākā rinda visā konveijerā.
 */
function pbf_varint(string $b, int &$pos): int
{
    $r = 0; $s = 0;
    do {
        $c = ord($b[$pos++]);
        $r |= ($c & 0x7F) << $s;
        $s += 7;
    } while ($c >= 0x80);
    return $r;
}

/** Zigzag → parakstīts (protobuf sint32/sint64). */
function pbf_zigzag(int $n): int
{
    return ($n >> 1) ^ -($n & 1);
}

/**
 * Protobuf lauku iterators vienam ziņojumam.
 * @return Generator<array{0:int,1:int,2:mixed}> [lauks, wire tips, vērtība]
 *         wire 2 gadījumā vērtība ir [sākums, beigas] intervāls $b iekšienē.
 */
function pbf_fields(string $b, int $pos, int $end): Generator
{
    while ($pos < $end) {
        $key   = pbf_varint($b, $pos);
        $field = $key >> 3;
        $wire  = $key & 7;
        switch ($wire) {
            case 0: yield [$field, 0, pbf_varint($b, $pos)]; break;
            case 1: yield [$field, 1, substr($b, $pos, 8)]; $pos += 8; break;
            case 2:
                $len = pbf_varint($b, $pos);
                yield [$field, 2, [$pos, $pos + $len]];
                $pos += $len;
                break;
            case 5: yield [$field, 5, substr($b, $pos, 4)]; $pos += 4; break;
            default: throw new RuntimeException("nezināms wire tips $wire");
        }
    }
}

// ── Blobu straume ───────────────────────────────────────────────────────────

/**
 * Atspiestie OSMData bloki pa vienam. Atmiņā vienlaikus ir tikai viens bloks
 * (~8 MB), tāpēc faila izmērs atmiņas patēriņu neietekmē — tas pats princips,
 * ar kādu ie_gml_centroids() apstrādā 5,5 GB GML.
 *
 * @return Generator<string>
 */
function pbf_blocks(string $path): Generator
{
    $fh = fopen($path, 'rb');
    if ($fh === false) throw new RuntimeException("nevar atvērt: $path");

    try {
        while (true) {
            $lenBuf = fread($fh, 4);
            if ($lenBuf === false || strlen($lenBuf) < 4) break;   // faila beigas

            $hdrLen = unpack('N', $lenBuf)[1];
            if ($hdrLen <= 0 || $hdrLen > IE_PBF_HDR_MAX) {
                throw new RuntimeException("bojāts BlobHeader garums: $hdrLen");
            }
            $hdr = fread($fh, $hdrLen);

            $type = null; $dataSize = 0;
            $p = 0;
            foreach (pbf_fields($hdr, 0, strlen($hdr)) as [$f, $w, $v]) {
                if ($f === 1 && $w === 2) $type = substr($hdr, $v[0], $v[1] - $v[0]);
                elseif ($f === 3 && $w === 0) $dataSize = $v;
            }
            if ($dataSize <= 0) throw new RuntimeException('Blob bez datasize');

            $blob = fread($fh, $dataSize);
            if ($type !== 'OSMData') continue;                     // OSMHeader izlaižam

            $raw = null;
            foreach (pbf_fields($blob, 0, strlen($blob)) as [$f, $w, $v]) {
                if ($w !== 2) continue;
                $chunk = substr($blob, $v[0], $v[1] - $v[0]);
                if ($f === 1)      $raw = $chunk;                  // nesaspiests
                elseif ($f === 3)  $raw = @gzuncompress($chunk);   // zlib
            }
            if ($raw === null || $raw === false) {
                throw new RuntimeException('Blob nav atspiežams (nav zlib?)');
            }
            yield $raw;
        }
    } finally {
        fclose($fh);
    }
}

// ── PrimitiveBlock ──────────────────────────────────────────────────────────

/**
 * Viena bloka apstrāde.
 *
 * $onNode(int $id, float $lat, float $lon, array $tags)
 * $onWay(int $id, array $tags, int[] $refs)
 * $onRel(int $id, array $tags, array $members)  members: [['type'=>..,'ref'=>..]]
 *
 * Atgriezes izsaukumi tiek saukti TIKAI tad, ja tie padoti (null = izlaist),
 * un ways/relations gadījumā tikai objektiem AR tagiem — bez tagiem tie mūs
 * neinteresē, un to ir lielākā daļa.
 */
function pbf_read_block(string $b, ?callable $onNode, ?callable $onWay, ?callable $onRel,
                        bool $allNodes = false): void
{
    $strings = [];
    $granularity = 100; $latOff = 0; $lonOff = 0;
    $groups = [];

    foreach (pbf_fields($b, 0, strlen($b)) as [$f, $w, $v]) {
        if ($f === 1 && $w === 2) {                       // stringtable
            foreach (pbf_fields($b, $v[0], $v[1]) as [$sf, $sw, $sv]) {
                if ($sf === 1 && $sw === 2) $strings[] = substr($b, $sv[0], $sv[1] - $sv[0]);
            }
        } elseif ($f === 2 && $w === 2) {
            $groups[] = $v;
        } elseif ($f === 17 && $w === 0) { $granularity = $v; }
        elseif ($f === 19 && $w === 0)   { $latOff = pbf_zigzag($v); }
        elseif ($f === 20 && $w === 0)   { $lonOff = pbf_zigzag($v); }
    }
    // lat_offset/lon_offset protobuf shēmā ir int64 (nevis sint64) — praksē tie
    // gandrīz vienmēr ir 0, bet nolasām tos kā parastu varint, ja zigzag deva
    // dīvainu rezultātu. Geofabrik failos abi ir 0.
    if ($latOff !== 0 || $lonOff !== 0) { /* reti; vērtības jau nolasītas */ }

    foreach ($groups as [$gStart, $gEnd]) {
        foreach (pbf_fields($b, $gStart, $gEnd) as [$gf, $gw, $gv]) {
            if ($gw !== 2) continue;
            if ($gf === 2 && $onNode !== null) {
                pbf_dense($b, $gv[0], $gv[1], $strings, $granularity, $latOff, $lonOff,
                          $onNode, $allNodes);
            } elseif ($gf === 3 && $onWay !== null) {
                pbf_way($b, $gv[0], $gv[1], $strings, $onWay);
            } elseif ($gf === 4 && $onRel !== null) {
                pbf_relation($b, $gv[0], $gv[1], $strings, $onRel);
            } elseif ($gf === 1 && $onNode !== null) {
                pbf_plain_node($b, $gv[0], $gv[1], $strings, $granularity, $latOff, $lonOff, $onNode);
            }
        }
    }
}

/**
 * DenseNodes — te ir 95 % no visa darba, tāpēc varint dekodēšana ir ielīmēta.
 *
 * Delta kodējums nozīmē, ka VISI mezgli jāizlasa secīgi, arī tie, kas mūs
 * neinteresē: izlaist nevar, jo katra nākamā vērtība ir relatīva pret iepriekšējo.
 * Bet neinteresantos mezglus mēs neuzglabājam, tāpēc atmiņa paliek plakana.
 */
function pbf_dense(string $b, int $start, int $end, array $strings,
                   int $granularity, int $latOff, int $lonOff, callable $onNode,
                   bool $allNodes = false): void
{
    $ids = null; $lats = null; $lons = null; $kv = null;

    foreach (pbf_fields($b, $start, $end) as [$f, $w, $v]) {
        if ($w !== 2) continue;
        if     ($f === 1)  $ids  = $v;
        elseif ($f === 8)  $lats = $v;
        elseif ($f === 9)  $lons = $v;
        elseif ($f === 10) $kv   = $v;
    }
    if ($ids === null || $lats === null || $lons === null) return;

    $pi = $ids[0];  $pe = $ids[1];
    $ai = $lats[0]; $ae = $lats[1];
    $oi = $lons[0]; $oe = $lons[1];
    $ki = $kv !== null ? $kv[0] : 0;
    $ke = $kv !== null ? $kv[1] : 0;

    $id = 0; $lat = 0; $lon = 0;
    $scale = $granularity * 1e-9;

    while ($pi < $pe) {
        // --- id (delta, zigzag) ---
        $r = 0; $s = 0;
        do { $c = ord($b[$pi++]); $r |= ($c & 0x7F) << $s; $s += 7; } while ($c >= 0x80);
        $id += ($r >> 1) ^ -($r & 1);

        // --- lat (delta, zigzag) ---
        $r = 0; $s = 0;
        do { $c = ord($b[$ai++]); $r |= ($c & 0x7F) << $s; $s += 7; } while ($c >= 0x80);
        $lat += ($r >> 1) ^ -($r & 1);

        // --- lon (delta, zigzag) ---
        $r = 0; $s = 0;
        do { $c = ord($b[$oi++]); $r |= ($c & 0x7F) << $s; $s += 7; } while ($c >= 0x80);
        $lon += ($r >> 1) ^ -($r & 1);

        // --- tagi: pāri key,val līdz 0 ---
        $tags = [];
        if ($ki < $ke) {
            while (true) {
                $r = 0; $s = 0;
                do { $c = ord($b[$ki++]); $r |= ($c & 0x7F) << $s; $s += 7; } while ($c >= 0x80);
                if ($r === 0) break;                        // mezgla tagu beigas
                $kIdx = $r;
                $r = 0; $s = 0;
                do { $c = ord($b[$ki++]); $r |= ($c & 0x7F) << $s; $s += 7; } while ($c >= 0x80);
                $tags[$strings[$kIdx] ?? ''] = $strings[$r] ?? '';
            }
        }
        // Parasti mūs interesē tikai mezgli ar tagiem. Izņēmums ir gājiens, kurā
        // meklējam ceļu virsotņu koordinātas — tās gandrīz nekad nav tagotas.
        if (!$allNodes && !$tags) continue;

        $onNode($id, ($latOff + $granularity * $lat) * 1e-9,
                     ($lonOff + $granularity * $lon) * 1e-9, $tags);
    }
}

/** Atsevišķi (ne-blīvi) mezgli — Geofabrik tos praktiski nelieto, bet formāts atļauj. */
function pbf_plain_node(string $b, int $start, int $end, array $strings,
                        int $granularity, int $latOff, int $lonOff, callable $onNode): void
{
    $id = 0; $lat = 0; $lon = 0; $keys = []; $vals = [];
    foreach (pbf_fields($b, $start, $end) as [$f, $w, $v]) {
        if     ($f === 1 && $w === 0) $id  = pbf_zigzag($v);
        elseif ($f === 8 && $w === 0) $lat = pbf_zigzag($v);
        elseif ($f === 9 && $w === 0) $lon = pbf_zigzag($v);
        elseif ($f === 2 && $w === 2) $keys = pbf_packed($b, $v[0], $v[1]);
        elseif ($f === 3 && $w === 2) $vals = pbf_packed($b, $v[0], $v[1]);
    }
    $tags = [];
    foreach ($keys as $i => $k) $tags[$strings[$k] ?? ''] = $strings[$vals[$i] ?? 0] ?? '';
    if (!$tags) return;
    $onNode($id, ($latOff + $granularity * $lat) * 1e-9,
                 ($lonOff + $granularity * $lon) * 1e-9, $tags);
}

/** Iepakots varint masīvs (bez delta). @return int[] */
function pbf_packed(string $b, int $start, int $end): array
{
    $out = [];
    $p = $start;
    while ($p < $end) {
        $r = 0; $s = 0;
        do { $c = ord($b[$p++]); $r |= ($c & 0x7F) << $s; $s += 7; } while ($c >= 0x80);
        $out[] = $r;
    }
    return $out;
}

/** Iepakots delta+zigzag varint masīvs (mezglu atsauces, dalībnieki). @return int[] */
function pbf_packed_delta(string $b, int $start, int $end): array
{
    $out = [];
    $p = $start; $acc = 0;
    while ($p < $end) {
        $r = 0; $s = 0;
        do { $c = ord($b[$p++]); $r |= ($c & 0x7F) << $s; $s += 7; } while ($c >= 0x80);
        $acc += ($r >> 1) ^ -($r & 1);
        $out[] = $acc;
    }
    return $out;
}

function pbf_way(string $b, int $start, int $end, array $strings, callable $onWay): void
{
    $id = 0; $keys = []; $vals = []; $refs = [];
    foreach (pbf_fields($b, $start, $end) as [$f, $w, $v]) {
        if     ($f === 1 && $w === 0) $id = $v;
        elseif ($f === 2 && $w === 2) $keys = pbf_packed($b, $v[0], $v[1]);
        elseif ($f === 3 && $w === 2) $vals = pbf_packed($b, $v[0], $v[1]);
        elseif ($f === 8 && $w === 2) $refs = pbf_packed_delta($b, $v[0], $v[1]);
    }
    if (!$keys) return;                                    // bez tagiem = neinteresē
    $tags = [];
    foreach ($keys as $i => $k) $tags[$strings[$k] ?? ''] = $strings[$vals[$i] ?? 0] ?? '';
    $onWay($id, $tags, $refs);
}

function pbf_relation(string $b, int $start, int $end, array $strings, callable $onRel): void
{
    $id = 0; $keys = []; $vals = []; $memIds = []; $memTypes = []; $roles = [];
    foreach (pbf_fields($b, $start, $end) as [$f, $w, $v]) {
        if     ($f === 1 && $w === 0) $id = $v;
        elseif ($f === 2 && $w === 2) $keys = pbf_packed($b, $v[0], $v[1]);
        elseif ($f === 3 && $w === 2) $vals = pbf_packed($b, $v[0], $v[1]);
        elseif ($f === 8 && $w === 2) $roles = pbf_packed($b, $v[0], $v[1]);
        elseif ($f === 9 && $w === 2) $memIds = pbf_packed_delta($b, $v[0], $v[1]);
        elseif ($f === 10 && $w === 2) $memTypes = pbf_packed($b, $v[0], $v[1]);
    }
    if (!$keys) return;
    $tags = [];
    foreach ($keys as $i => $k) $tags[$strings[$k] ?? ''] = $strings[$vals[$i] ?? 0] ?? '';

    $members = [];
    foreach ($memIds as $i => $mid) {
        $members[] = ['type' => $memTypes[$i] ?? 0,      // 0=node 1=way 2=rel
                      'ref'  => $mid,
                      'role' => $strings[$roles[$i] ?? 0] ?? ''];
    }
    $onRel($id, $tags, $members);
}

// ── Robežas poligons ────────────────────────────────────────────────────────

/**
 * Geofabrik .poly faila nolasīšana.
 *
 * KĀPĒC TAS VAJADZĪGS. Geofabrik izgriezums pārkaras pār valsts robežu: tas
 * iekļauj veselus objektus, kas robežu šķērso, un blakus esošas apdzīvotas
 * vietas. Python laikā tieši tāpēc konkurentos nonāca 16 ārzemju uzņēmumi —
 * Valgas (Igaunija) puse Valkas dvīņupilsētā, Ruhnu sala un viens Lietuvā.
 * Overpass to atrisināja ar `area["ISO3166-1"="LV"]`; šeit to atrisina šis
 * poligons, ko Geofabrik publicē blakus pašam izgriezumam.
 *
 * Formāts: nosaukums, tad viens vai vairāki gredzeni ("!" priekšā = caurums),
 * katrā "lon lat" pāri, "END" gredzena beigās, "END" faila beigās.
 *
 * @return array{outer:array<int,array<int,array{0:float,1:float}>>,
 *               inner:array<int,array<int,array{0:float,1:float}>>}
 */
function pbf_read_poly(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) throw new RuntimeException("nevar lasīt: $path");

    $outer = []; $inner = [];
    $ring = null; $isHole = false;

    foreach ($lines as $i => $line) {
        $t = trim($line);
        if ($t === '' || $i === 0) continue;              // pirmā rinda = nosaukums
        if (strtoupper($t) === 'END') {
            if ($ring !== null) {
                if ($isHole) $inner[] = $ring; else $outer[] = $ring;
                $ring = null;
            }
            continue;
        }
        if (!preg_match('/^-?[\d.]/', $t) && $t[0] !== '!') {
            continue;                                      // cita atslēga, izlaižam
        }
        if ($ring === null && !preg_match('/^\s*-?[\d.]/', $line)) {
            $isHole = str_starts_with($t, '!');            // gredzena virsraksts
            $ring = [];
            continue;
        }
        $p = preg_split('/\s+/', $t, -1, PREG_SPLIT_NO_EMPTY);
        if ($p === false || count($p) < 2) continue;
        if ($ring === null) { $ring = []; }
        $ring[] = [(float)$p[0], (float)$p[1]];            // lon, lat
    }
    if ($ring !== null) { if ($isHole) $inner[] = $ring; else $outer[] = $ring; }

    if (!$outer) throw new RuntimeException("poligonā nav neviena gredzena: $path");
    return ['outer' => $outer, 'inner' => $inner];
}

/** Punkts gredzenā — staru metode. */
function pbf_in_ring(float $lon, float $lat, array $ring): bool
{
    $in = false;
    $n = count($ring);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        [$xi, $yi] = $ring[$i];
        [$xj, $yj] = $ring[$j];
        if (($yi > $lat) !== ($yj > $lat)
            && $lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
            $in = !$in;
        }
    }
    return $in;
}

/** Punkts valsts poligonā (ārējos gredzenos, bet ne caurumos). */
function pbf_in_poly(float $lon, float $lat, array $poly): bool
{
    foreach ($poly['inner'] as $ring) if (pbf_in_ring($lon, $lat, $ring)) return false;
    foreach ($poly['outer'] as $ring) if (pbf_in_ring($lon, $lat, $ring)) return true;
    return false;
}

// ── Izvilkšana Overpass formātā ─────────────────────────────────────────────

/** Vai objekta tagi atbilst kādam selektoram [atslēga, vērtība]? */
function pbf_match(array $tags, array $selectors): bool
{
    foreach ($selectors as [$k, $v]) {
        if (($tags[$k] ?? null) === $v) return true;
    }
    return false;
}

/**
 * POI izvilkšana no .pbf — IZVADE SAKRĪT AR ie_overpass().
 *
 * Trīs gājieni pār failu (katrs ~3 s Latvijai), jo PBF secība ir mezgli → ceļi →
 * relācijas, un ceļa centram vajag virsotņu koordinātas, kas failā jau ir aiz
 * muguras. Straumēšana nozīmē, ka atmiņā nekad nav vairāk par vienu bloku plus
 * atlasīto objektu sarakstu.
 *
 *   1. gājiens — atlasa mezglus/ceļus/relācijas ar atbilstošiem tagiem
 *   2. gājiens — relāciju dalībnieku ceļiem savāc virsotņu atsauces
 *   3. gājiens — savāc TIKAI vajadzīgo virsotņu koordinātas
 *
 * Centrs ceļiem un relācijām = ietverošā taisnstūra centrs, tieši tāpat kā
 * Overpass `out center`, lai abu avotu rezultāti būtu salīdzināmi.
 *
 * @param array      $selectors  [[atslēga, vērtība], …]
 * @param string[]   $types      'node' | 'way' | 'relation'
 * @param bool       $requireName tikai nosaukti objekti (1. soļa Python paritāte)
 * @param array|null $poly       robežas poligons no pbf_read_poly(), vai null
 * @return array{elements:array}
 */
function ie_pbf_extract(string $path, array $selectors,
                        array $types = ['node', 'way', 'relation'],
                        bool $requireName = false, ?array $poly = null): array
{
    $wantNode = in_array('node', $types, true);
    $wantWay  = in_array('way', $types, true);
    $wantRel  = in_array('relation', $types, true);

    $elements   = [];
    $wayTags    = [];   // ceļa id → tagi (atlasītie)
    $wayRefs    = [];   // ceļa id → virsotņu id
    $relTags    = [];   // relācijas id → tagi
    $relWays    = [];   // relācijas id → dalībnieku ceļu id
    $relNodes   = [];   // relācijas id → dalībnieku mezglu id
    $needWays   = [];   // ceļa id → true (relāciju dalībnieki)
    $needNodes  = [];   // mezgla id → true

    // ── 1. gājiens ──────────────────────────────────────────────────────────
    foreach (pbf_blocks($path) as $blk) {
        pbf_read_block($blk,
            $wantNode ? function (int $id, float $lat, float $lon, array $tags)
                        use (&$elements, $selectors, $requireName, $poly): void {
                if (!pbf_match($tags, $selectors)) return;
                if ($requireName && ($tags['name'] ?? '') === '') return;
                if ($poly !== null && !pbf_in_poly($lon, $lat, $poly)) return;
                $elements[] = ['type' => 'node', 'id' => $id,
                               'lat' => $lat, 'lon' => $lon, 'tags' => $tags];
            } : null,
            $wantWay ? function (int $id, array $tags, array $refs)
                       use (&$wayTags, &$wayRefs, &$needNodes, $selectors, $requireName): void {
                if (!pbf_match($tags, $selectors)) return;
                if ($requireName && ($tags['name'] ?? '') === '') return;
                if (!$refs) return;
                $wayTags[$id] = $tags;
                $wayRefs[$id] = $refs;
                foreach ($refs as $r) $needNodes[$r] = true;
            } : null,
            $wantRel ? function (int $id, array $tags, array $members)
                       use (&$relTags, &$relWays, &$relNodes, &$needWays, &$needNodes,
                            $selectors, $requireName): void {
                if (!pbf_match($tags, $selectors)) return;
                if ($requireName && ($tags['name'] ?? '') === '') return;
                $ws = []; $ns = [];
                foreach ($members as $m) {
                    if ($m['type'] === 1)      { $ws[] = $m['ref']; $needWays[$m['ref']] = true; }
                    elseif ($m['type'] === 0)  { $ns[] = $m['ref']; $needNodes[$m['ref']] = true; }
                }
                if (!$ws && !$ns) return;
                $relTags[$id]  = $tags;
                $relWays[$id]  = $ws;
                $relNodes[$id] = $ns;
            } : null);
    }

    // ── 2. gājiens: relāciju ceļu virsotnes ─────────────────────────────────
    if ($needWays) {
        foreach (pbf_blocks($path) as $blk) {
            pbf_read_block($blk, null,
                function (int $id, array $tags, array $refs) use (&$wayRefs, &$needWays, &$needNodes): void {
                    if (!isset($needWays[$id])) return;
                    $wayRefs[$id] = $refs;
                    foreach ($refs as $r) $needNodes[$r] = true;
                }, null);
        }
    }

    // ── 3. gājiens: vajadzīgo virsotņu koordinātas ──────────────────────────
    $coords = [];
    if ($needNodes) {
        foreach (pbf_blocks($path) as $blk) {
            pbf_read_block($blk,
                function (int $id, float $lat, float $lon, array $tags) use (&$coords, &$needNodes): void {
                    if (isset($needNodes[$id])) $coords[$id] = [$lat, $lon];
                }, null, null, true);          // true = arī mezgli bez tagiem
        }
    }

    /** Ietverošā taisnstūra centrs no virsotņu id saraksta. */
    $center = static function (array $nodeIds) use (&$coords): ?array {
        $minLa = $minLo = INF; $maxLa = $maxLo = -INF;
        foreach ($nodeIds as $nid) {
            if (!isset($coords[$nid])) continue;
            [$la, $lo] = $coords[$nid];
            if ($la < $minLa) $minLa = $la;
            if ($la > $maxLa) $maxLa = $la;
            if ($lo < $minLo) $minLo = $lo;
            if ($lo > $maxLo) $maxLo = $lo;
        }
        if ($minLa === INF) return null;
        return [($minLa + $maxLa) / 2, ($minLo + $maxLo) / 2];
    };

    foreach ($wayTags as $id => $tags) {
        $c = $center($wayRefs[$id] ?? []);
        if ($c === null) continue;
        if ($poly !== null && !pbf_in_poly($c[1], $c[0], $poly)) continue;
        $elements[] = ['type' => 'way', 'id' => $id,
                       'center' => ['lat' => $c[0], 'lon' => $c[1]], 'tags' => $tags];
    }

    foreach ($relTags as $id => $tags) {
        $ids = $relNodes[$id] ?? [];
        foreach (($relWays[$id] ?? []) as $w) {
            foreach (($wayRefs[$w] ?? []) as $r) $ids[] = $r;
        }
        $c = $center($ids);
        if ($c === null) continue;
        if ($poly !== null && !pbf_in_poly($c[1], $c[0], $poly)) continue;
        $elements[] = ['type' => 'relation', 'id' => $id,
                       'center' => ['lat' => $c[0], 'lon' => $c[1]], 'tags' => $tags];
    }

    return ['elements' => $elements];
}

// ── Precīzā valsts robeža no paša PBF ───────────────────────────────────────

/**
 * Valsts robežas poligons no OSM administratīvās robežas relācijas failā.
 *
 * KĀPĒC NE Geofabrik .poly. Tas ir izgriešanas robeža, nevis valsts robeža —
 * Latvijai tajā ir tikai 151 punkts, un pārbaudē Valga (Igaunija) tajā iekrita
 * IEKŠĀ. Tieši tāda pārkare Python laikā ielaida 16 ārzemju uzņēmumus.
 *
 * Īstā robeža ir pašā failā: relācija `boundary=administrative`,
 * `admin_level=2`, `ISO3166-1=<kods>` — tas pats objekts, ko Overpass izmanto
 * `area["ISO3166-1"="LV"]`. Tāpēc filtrs šeit un Overpass filtrs ir viens un
 * tas pats, un abu avotu rezultāti kļūst salīdzināmi.
 *
 * Relācijas ceļi failā nāk patvaļīgā secībā un virzienā, tāpēc tos savieno
 * ķēdēs pēc galapunktiem, līdz gredzens noslēdzas (salas = atsevišķi gredzeni).
 *
 * @return array{outer:array,inner:array}|null null, ja robeža nav atrasta
 */
function ie_pbf_country_polygon(string $path, string $iso): ?array
{
    // 1. gājiens — robežas relācija
    $wantWays = [];      // ceļa id → loma
    foreach (pbf_blocks($path) as $blk) {
        pbf_read_block($blk, null, null,
            function (int $id, array $tags, array $members) use (&$wantWays, $iso): void {
                if (($tags['boundary'] ?? '') !== 'administrative') return;
                if (($tags['admin_level'] ?? '') !== '2') return;
                if (strcasecmp($tags['ISO3166-1'] ?? '', $iso) !== 0) return;
                foreach ($members as $m) {
                    if ($m['type'] !== 1) continue;                    // tikai ceļi
                    $role = $m['role'] === '' ? 'outer' : $m['role'];
                    if ($role !== 'outer' && $role !== 'inner') continue;
                    $wantWays[$m['ref']] = $role;
                }
            });
    }
    if (!$wantWays) return null;

    // 2. gājiens — ceļu virsotnes
    $wayNodes = []; $need = [];
    foreach (pbf_blocks($path) as $blk) {
        pbf_read_block($blk, null,
            function (int $id, array $tags, array $refs) use (&$wayNodes, &$need, $wantWays): void {
                if (!isset($wantWays[$id]) || !$refs) return;
                $wayNodes[$id] = $refs;
                foreach ($refs as $r) $need[$r] = true;
            }, null);
    }

    // 3. gājiens — koordinātas
    $coords = [];
    foreach (pbf_blocks($path) as $blk) {
        pbf_read_block($blk,
            function (int $id, float $lat, float $lon, array $tags) use (&$coords, &$need): void {
                if (isset($need[$id])) $coords[$id] = [$lon, $lat];
            }, null, null, true);
    }

    // Ceļu savienošana gredzenos
    $rings = ['outer' => [], 'inner' => []];
    foreach (['outer', 'inner'] as $role) {
        $segs = [];
        foreach ($wayNodes as $wid => $refs) {
            if (($wantWays[$wid] ?? '') === $role) $segs[$wid] = $refs;
        }
        while ($segs) {
            $wid = array_key_first($segs);
            $chain = $segs[$wid];
            unset($segs[$wid]);

            $guard = 0;
            while ($chain[0] !== $chain[count($chain) - 1] && $segs && $guard++ < 100000) {
                $tail = $chain[count($chain) - 1];
                $hit = null;
                foreach ($segs as $sid => $s) {
                    if ($s[0] === $tail)                     { $hit = [$sid, $s, false]; break; }
                    if ($s[count($s) - 1] === $tail)         { $hit = [$sid, $s, true];  break; }
                }
                if ($hit === null) break;                    // gredzens nenoslēdzas
                [$sid, $s, $rev] = $hit;
                unset($segs[$sid]);
                if ($rev) $s = array_reverse($s);
                array_shift($s);                             // kopīgā virsotne nav jādublē
                foreach ($s as $n) $chain[] = $n;
            }

            $ring = [];
            foreach ($chain as $n) if (isset($coords[$n])) $ring[] = $coords[$n];
            if (count($ring) >= 4) $rings[$role][] = $ring;
        }
        $rings[$role] = pbf_close_rings($rings[$role]);
    }

    return $rings['outer'] ? $rings : null;
}

/**
 * Atvērto ķēžu savienošana un noslēgšana.
 *
 * KĀPĒC TAS VAJADZĪGS. Geofabrik izgriezumā daļa robežas relācijas ceļu vienkārši
 * nav — Latvijai trūkst 13 no 503, un tie ir jūras robežas posmi ārpus izgriešanas
 * apgabala. Rezultātā precīzā ķēdēšana pēc virsotņu id apstājas pie katra roba, un
 * sanāk 11 atvērtu fragmentu, nevis viens gredzens. Uz atvērtas lauztas līnijas
 * staru metode dod nejaušu atbildi — tieši tāpēc pirmajā pārbaudē Rīga un Liepāja
 * iznāca "ārpus Latvijas".
 *
 * Robus aizlāpām ģeometriski: savienojam tuvākos brīvos galus, kamēr attālums ir
 * mazāks par slieksni. Trūkstošie posmi iet pa jūru, tāpēc taisne pār robu no POI
 * filtrēšanas viedokļa ir nekaitīga — tur uzņēmumu nav. Salas paliek atsevišķi
 * gredzeni, jo tās no cietzemes ir tālāk par slieksni.
 *
 * @param array $rings saraksts ar [lon,lat] punktu masīviem
 * @return array noslēgti gredzeni
 */
function pbf_close_rings(array $rings, float $maxGapDeg = 1.0): array
{
    // Atdalām jau noslēgtos — tos aiztikt nevajag.
    $open = []; $done = [];
    foreach ($rings as $r) {
        if (count($r) < 4) continue;
        if ($r[0] === $r[count($r) - 1]) $done[] = $r; else $open[] = $r;
    }

    // Alkatīgi savienojam tuvākos galus.
    while (count($open) > 1) {
        $best = null; $bestD = INF;
        foreach ($open as $i => $a) {
            $aEnd = $a[count($a) - 1];
            foreach ($open as $j => $b) {
                if ($i === $j) continue;
                foreach ([[false, $b[0]], [true, $b[count($b) - 1]]] as [$rev, $bPt]) {
                    $dx = $aEnd[0] - $bPt[0];
                    $dy = $aEnd[1] - $bPt[1];
                    $d  = $dx * $dx + $dy * $dy;
                    if ($d < $bestD) { $bestD = $d; $best = [$i, $j, $rev]; }
                }
            }
        }
        if ($best === null || sqrt($bestD) > $maxGapDeg) break;   // pārāk tālu = cits gredzens

        [$i, $j, $rev] = $best;
        $b = $open[$j];
        if ($rev) $b = array_reverse($b);
        foreach ($b as $pt) $open[$i][] = $pt;
        unset($open[$j]);
        $open = array_values($open);
    }

    // Noslēdzam, kas palicis.
    foreach ($open as $r) {
        if ($r[0] !== $r[count($r) - 1]) $r[] = $r[0];
        if (count($r) >= 4) $done[] = $r;
    }
    return $done;
}

/**
 * VISU POI tipu izvilkšana VIENĀ failа caurskatē.
 *
 * ie_pbf_extract() vienam tipam veic trīs gājienus. Vienpadsmit tipiem tas ir
 * 33 gājieni — Latvijā 89 sekundes, kas vēl ir panesami, bet Vācijā, kur viens
 * gājiens ir minūtes, tas kļūtu par pusstundu. Šeit gājieni ir tie paši trīs, un
 * katrs objekts tiek pārbaudīts pret visiem tipiem uzreiz.
 *
 * Tipi drīkst pārklāties, un tas ir pareizi: `amenity=fast_food` iekrīt gan
 * `restaurant`, gan `fastfood` sarakstā, tieši tāpat kā diviem atsevišķiem
 * Overpass vaicājumiem.
 *
 * @param array $defs ptype => ['selectors'=>[[k,v],…], 'types'=>[…], 'requireName'=>bool]
 * @return array ptype => ['elements'=>[…]]
 */
function ie_pbf_extract_many(string $path, array $defs, ?array $poly = null): array
{
    $out = [];
    foreach ($defs as $pt => $_) $out[$pt] = ['elements' => []];

    $wayHits = [];   // ceļa id → [ptype, …]
    $relHits = [];
    $wayTags = []; $wayRefs = [];
    $relTags = []; $relWays = []; $relNodes = [];
    $needWays = []; $needNodes = [];

    // ── 1. gājiens ──────────────────────────────────────────────────────────
    foreach (pbf_blocks($path) as $blk) {
        pbf_read_block($blk,
            function (int $id, float $lat, float $lon, array $tags)
                    use (&$out, $defs, $poly): void {
                $inside = null;
                foreach ($defs as $pt => $d) {
                    if (!in_array('node', $d['types'], true)) continue;
                    if (!pbf_match($tags, $d['selectors'])) continue;
                    if (!empty($d['requireName']) && ($tags['name'] ?? '') === '') continue;
                    // Poligona pārbaude ir dārgākā daļa, tāpēc to darām vienreiz
                    // uz mezglu un tikai tad, kad kāds tips tiešām sakrita.
                    if ($poly !== null) {
                        if ($inside === null) $inside = pbf_in_poly($lon, $lat, $poly);
                        if (!$inside) return;
                    }
                    $out[$pt]['elements'][] = ['type' => 'node', 'id' => $id,
                                               'lat' => $lat, 'lon' => $lon, 'tags' => $tags];
                }
            },
            function (int $id, array $tags, array $refs)
                    use (&$wayHits, &$wayTags, &$wayRefs, &$needNodes, $defs): void {
                if (!$refs) return;
                $hits = [];
                foreach ($defs as $pt => $d) {
                    if (!in_array('way', $d['types'], true)) continue;
                    if (!pbf_match($tags, $d['selectors'])) continue;
                    if (!empty($d['requireName']) && ($tags['name'] ?? '') === '') continue;
                    $hits[] = $pt;
                }
                if (!$hits) return;
                $wayHits[$id] = $hits;
                $wayTags[$id] = $tags;
                $wayRefs[$id] = $refs;
                foreach ($refs as $r) $needNodes[$r] = true;
            },
            function (int $id, array $tags, array $members)
                    use (&$relHits, &$relTags, &$relWays, &$relNodes,
                         &$needWays, &$needNodes, $defs): void {
                $hits = [];
                foreach ($defs as $pt => $d) {
                    if (!in_array('relation', $d['types'], true)) continue;
                    if (!pbf_match($tags, $d['selectors'])) continue;
                    if (!empty($d['requireName']) && ($tags['name'] ?? '') === '') continue;
                    $hits[] = $pt;
                }
                if (!$hits) return;
                $ws = []; $ns = [];
                foreach ($members as $m) {
                    if ($m['type'] === 1)     { $ws[] = $m['ref']; $needWays[$m['ref']] = true; }
                    elseif ($m['type'] === 0) { $ns[] = $m['ref']; $needNodes[$m['ref']] = true; }
                }
                if (!$ws && !$ns) return;
                $relHits[$id]  = $hits;
                $relTags[$id]  = $tags;
                $relWays[$id]  = $ws;
                $relNodes[$id] = $ns;
            });
    }

    // ── 2. gājiens: relāciju dalībnieku ceļi ────────────────────────────────
    if ($needWays) {
        foreach (pbf_blocks($path) as $blk) {
            pbf_read_block($blk, null,
                function (int $id, array $tags, array $refs)
                        use (&$wayRefs, &$needNodes, $needWays): void {
                    if (!isset($needWays[$id]) || !$refs) return;
                    $wayRefs[$id] = $refs;
                    foreach ($refs as $r) $needNodes[$r] = true;
                }, null);
        }
    }

    // ── 3. gājiens: virsotņu koordinātas ────────────────────────────────────
    $coords = [];
    if ($needNodes) {
        foreach (pbf_blocks($path) as $blk) {
            pbf_read_block($blk,
                function (int $id, float $lat, float $lon, array $tags)
                        use (&$coords, $needNodes): void {
                    if (isset($needNodes[$id])) $coords[$id] = [$lat, $lon];
                }, null, null, true);
        }
    }

    $center = static function (array $ids) use (&$coords): ?array {
        $minLa = $minLo = INF; $maxLa = $maxLo = -INF;
        foreach ($ids as $nid) {
            if (!isset($coords[$nid])) continue;
            [$la, $lo] = $coords[$nid];
            if ($la < $minLa) $minLa = $la;
            if ($la > $maxLa) $maxLa = $la;
            if ($lo < $minLo) $minLo = $lo;
            if ($lo > $maxLo) $maxLo = $lo;
        }
        return $minLa === INF ? null : [($minLa + $maxLa) / 2, ($minLo + $maxLo) / 2];
    };

    foreach ($wayHits as $id => $pts) {
        $c = $center($wayRefs[$id] ?? []);
        if ($c === null) continue;
        if ($poly !== null && !pbf_in_poly($c[1], $c[0], $poly)) continue;
        foreach ($pts as $pt) {
            $out[$pt]['elements'][] = ['type' => 'way', 'id' => $id,
                'center' => ['lat' => $c[0], 'lon' => $c[1]], 'tags' => $wayTags[$id]];
        }
    }

    foreach ($relHits as $id => $pts) {
        $ids = $relNodes[$id] ?? [];
        foreach (($relWays[$id] ?? []) as $w) {
            foreach (($wayRefs[$w] ?? []) as $r) $ids[] = $r;
        }
        $c = $center($ids);
        if ($c === null) continue;
        if ($poly !== null && !pbf_in_poly($c[1], $c[0], $poly)) continue;
        foreach ($pts as $pt) {
            $out[$pt]['elements'][] = ['type' => 'relation', 'id' => $id,
                'center' => ['lat' => $c[0], 'lon' => $c[1]], 'tags' => $relTags[$id]];
        }
    }

    return $out;
}
