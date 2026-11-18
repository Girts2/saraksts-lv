<?php
/**
 * granti/lib/beigusie.php — beigušos ES konkursu PĀRNESE un izņemto adrešu saraksts.
 *
 * KĀPĒC (2026-09-23). Būve no ES portāla ņem tikai Open/Forthcoming ierakstus. Dažas dienas
 * pēc termiņa portāls konkursu pārslēdz uz Closed, nākamā nakts būve to vairs neredz, un
 * /granti/{slug} atdeva 404. GSC to redzēja kā 111 -> 6 000 lapu "Nav atrasta (404)": 104
 * konkursi ar termiņiem 15.–22.09, katrs ar ~60 vecajām filtru adresēm. Cilne "Beigušies" ES
 * konkursiem palika praktiski tukša, un iztulkota lapa pazuda tieši tad, kad cilvēks meklē
 * nākamo kārtu vai rezultātus.
 *
 * TRĪS SOĻI, pret iepriekšējo (dzīvo) datubāzi:
 *   1) gr_parnes_beigusos_es() — ES rindas, kuru šonakt nav, bet kuru termiņš beidzās pēdējo
 *      GR_BEIGUSO_DIENAS dienu laikā, pārnes ar statusu 'closed'. Tieši tā pati rinda, ko lapa
 *      rādīja vakar; tulkojumi jau samaksāti un nāk no keša (gr_tulk_aizpildi).
 *   2) gr_parnesto_dublikati() — pārnestā rinda nedrīkst atdzīvināt alias-dublikātu, ko svaigā
 *      būve jau izmeta (EIC Pathfinder: tā pati tēma ar ID bez gada paliek portālā "Forthcoming").
 *   3) gr_izbeigtas_adreses() — ES adrese, kas kādreiz bija un vairs nav, nonāk tabulā
 *      izbeigtas_adreses. Lapa pēc tās atbild 410 Gone, nevis 404: "bija, apzināti izņemts".
 *
 * KO NEPĀRNES:
 *   · termiņš vēl PRIEKŠĀ — portāls konkursu izņēma pirms termiņa (atsaukts, pārsaukts). Lapa to
 *     rādītu kā atvērtu (gr_statuss(): datums uzvar saglabāto 'closed'), tātad melotu;
 *   · termiņa nav vispār — nevar pateikt, cik sen tas beidzās;
 *   · SIF un LV avotus — SIF plūsma pati dod arī slēgtos, LV avotiem ir sava pārnese build.php.
 * Termiņš ŠODIEN pārnes ar VECO statusu: portāls var konkursu slēgt jau termiņa dienā (17:00 Briselē),
 * un būve ārpus grafika (ar roku, --bulk) to citādi neredzētu ne šodien, ne rīt — rīt vecajā DB tā vairs
 * nav. Lapa to līdz dienas beigām rāda tāpat kā jebkuru citu konkursu ar šodienas termiņu.
 */
declare(strict_types=1);

/** Cik dienas pēc termiņa beidzies ES konkurss paliek sadaļā (cilne "Beigušies", sava lapa). */
const GR_BEIGUSO_DIENAS = 183;

/**
 * Termiņš, pēc kura rinda skaitās beigusies — TĀ PATI izteiksme, ko lapa lieto $dzivs
 * (granti.php): deadline_apply pirmais, jo divu posmu konkursā pēc 1. posma jauns pieteikums
 * vairs nav iespējams. Kolonnas ņem tikai tās, kas vecajā DB IR (vecāka shēma nedrīkst gāzt būvi).
 */
function gr_beig_termina_sql(array $kolonnas): ?string {
    $ir = array_values(array_intersect(['deadline_apply', 'deadline_last', 'deadline_date'], $kolonnas));
    if (!$ir) return null;
    return count($ir) === 1 ? $ir[0] : 'COALESCE(' . implode(', ', $ir) . ')';
}

/** @return list<string> tabulas kolonnas (tukšs saraksts, ja tabulas nav). */
function gr_beig_kolonnas(PDO $db, string $tabula): array {
    return array_column($db->query('PRAGMA table_info(' . $tabula . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
}

/**
 * Pārnes beigušos ES konkursus no vecās DB uz jauno (ar statusu 'closed').
 *
 * INSERT OR IGNORE: ja portāls rindu šonakt tomēr deva, svaigā paliek neskarta. Kolonnu
 * šķēlums, ne jaunās shēmas saraksts — tāpat kā izlaisto avotu pārnesē build.php.
 * Izņēmumu met tālāk: saucējam (būvei) tas jāpārvērš par apturētu būvi, citādi atomiskā
 * nomaiņa klusi izdzēstu visas beigušās lapas.
 *
 * @param list<string>|null $ieliktie  šeit atgriež tiešām ielikto rindu ID (gr_parnesto_dublikati vajag
 *                                     tieši šo sarakstu: termiņa dienā pārnestā rinda nav 'closed')
 * @return int tiešām ielikto rindu skaits
 */
function gr_parnes_beigusos_es(PDO $jauna, PDO $veca, string $sodien, int $dienas = GR_BEIGUSO_DIENAS,
                               ?array &$ieliktie = null): int {
    $ieliktie = [];
    $vecasK = gr_beig_kolonnas($veca, 'grants');
    $kop = array_values(array_intersect(gr_beig_kolonnas($jauna, 'grants'), $vecasK));
    foreach (['id', 'source', 'status'] as $vajag)
        if (!in_array($vajag, $kop, true)) throw new RuntimeException("vecajā vai jaunajā DB nav kolonnas $vajag");
    $dl = gr_beig_termina_sql($vecasK);
    if ($dl === null) return 0;
    $robeza = date('Y-m-d', strtotime($sodien . ' -' . $dienas . ' days'));
    // Pēdējā kolonna ir termiņš — pēc tā izšķir statusu, pirms INSERT to noņem.
    $sel = $veca->prepare('SELECT ' . implode(',', $kop) . ", $dl FROM grants
        WHERE source = 'EU' AND $dl IS NOT NULL AND $dl <= ? AND $dl >= ? ORDER BY id");
    $sel->execute([$sodien, $robeza]);
    $ins = $jauna->prepare('INSERT OR IGNORE INTO grants (' . implode(',', $kop) . ') VALUES ('
        . rtrim(str_repeat('?,', count($kop)), ',') . ')');
    $stIdx = array_search('status', $kop, true);
    $idIdx = array_search('id', $kop, true);
    $jauna->beginTransaction();
    try {
        foreach ($sel->fetchAll(PDO::FETCH_NUM) as $rinda) {
            // 'closed', ne vecais statuss: portāla 'open' pēc termiņa ir tikai nobīde, un tulkošanas
            // rinda (gr_tulk_sinhronize) pēc šī statusa zina, ka par šo tekstu vairs nemaksā.
            // Termiņš šodien -> vecais statuss (sk. faila galvu): 'closed' ar šodienas datumu lapa
            // pārvērstu atpakaļ par 'open' (gr_statuss), tātad tas būtu tikai nepatiess ieraksts.
            if ((string)array_pop($rinda) < $sodien) $rinda[$stIdx] = 'closed';
            $ins->execute($rinda);
            if ($ins->rowCount() > 0) $ieliktie[] = (string)$rinda[$idIdx];
        }
        $jauna->commit();
    } catch (Throwable $e) {
        $jauna->rollBack();
        $ieliktie = [];
        throw $e;
    }
    return count($ieliktie);
}

/**
 * Pārnestā rinda, kas dublē svaigu, ir lieka (recenzents 2026-09-23). Svaigā būve alias-dublikātus
 * izmet PIRMS pārneses (build.php "Alias-dublikātu apvienošana"), tāpēc pāri, kurā viens ir pārnests,
 * tā neredz. Reāls gadījums: EIC Pathfinder tēma ar ID bez gada portālā paliek "Forthcoming" arī
 * pēc termiņa, bet ID ar gadu pēc termiņa aiziet uz Closed — bez šī pēc 2026-10-28 lapā būtu divi
 * vienādi konkursi.
 *
 * TĀS PAŠAS kārtulas un TĀ PATI izvēle, ko build.php, ar vienu ierobežojumu — svaigo rindu izmet tikai
 * tad, ja tā jau beigusies vai tās termiņš nav vēlāks par pārnestās (tad izmet pārnesto):
 *   A) rindas ?topic= saite ved uz otras ID un nosaukumi sakrīt -> paturam to, KAM saite ir.
 *      (EIC-2026-PATHFINDERCHALLENGES-01-02 un četras Euratom tēmas ved uz savu aliasu.)
 *   B) tas pats call_id, nosaukums, termiņš un budžets -> paturam to, kura ID sākas ar call_id;
 *      ja sākas abi vai neviens, paturam ABUS (JUST-2027-JACC-EJUSTICE-DIGITAL/-RIGHTS ir īsti
 *      dažādas tēmas ar vienu nosaukumu).
 * Pāris, kuru jau atrisināja A, B vairs neskata. Svaigo izmet tikai par labu pārnestajam, kas pats
 * paliek — citādi pazustu abi.
 *
 * @param list<string> $parnestie  gr_parnes_beigusos_es() ieliktie ID; pārējās ES rindas ir svaigas
 * @return array<string,string> izmestais ID => iemesls
 */
function gr_parnesto_dublikati(PDO $db, string $sodien, array $parnestie): array {
    if (!$parnestie) return [];
    $db->exec('CREATE TEMP TABLE IF NOT EXISTS gr_beig_parnestie (id TEXT PRIMARY KEY)');
    $db->exec('DELETE FROM gr_beig_parnestie');
    $insP = $db->prepare('INSERT OR IGNORE INTO gr_beig_parnestie (id) VALUES (?)');
    foreach ($parnestie as $id) $insP->execute([$id]);
    $pari = "FROM grants c JOIN grants f ON c.title = f.title AND c.id <> f.id
        WHERE c.id IN (SELECT id FROM gr_beig_parnestie)
          AND f.source = 'EU' AND f.id NOT IN (SELECT id FROM gr_beig_parnestie)";
    $dlSql = "COALESCE(f.deadline_apply, f.deadline_last, f.deadline_date) fdl,
              COALESCE(c.deadline_apply, c.deadline_last, c.deadline_date) cdl";
    // Svaigo drīkst izmest, ja tas jau beidzies VAI tā termiņš nav vēlāks par pārnestā (recenzents, 3. kārta):
    // būvē termiņa dienā novecojušais alias ar šodienas termiņu vēl skaitās dzīvs, un bez otrā nosacījuma
    // izmestu galveno (ar gadu) — rīt tā vecajā DB vairs nebūtu, un adrese kļūtu par 410 uz visiem laikiem.
    // Pārnestais ar to pašu termiņu to pašu konkursu rāda līdz pusnaktij, tātad nekas nepazūd.
    $fDrikst = static fn(array $r): bool => $r['fdl'] !== null
        && ((string)$r['fdl'] < $sodien || ($r['cdl'] !== null && (string)$r['fdl'] <= (string)$r['cdl']));

    $izmest = []; $fPret = [];
    // Viens lēmums pārim: dzēst svaigo (tikai, ja $fDrikst) vai pārnesto.
    $lem = static function (string $cid, string $fid, bool $svaigo, string $kapec) use (&$izmest, &$fPret): void {
        if (isset($izmest[$cid]) || isset($izmest[$fid])) return;   // pāris jau atrisināts
        if ($svaigo) { $izmest[$fid] = $kapec; $fPret[$fid] = $cid; }
        else           $izmest[$cid] = $kapec;
    };
    $uz = static fn(?string $url): string =>
        preg_match('/[?&]topic=([^&]+)$/i', (string)$url, $m) ? 'EU:' . rawurldecode($m[1]) : '';

    // A: saite.
    $q = $db->query("SELECT c.id cid, c.submit_url cs, f.id fid, f.submit_url fs, $dlSql $pari
          AND (c.submit_url IS NOT NULL OR f.submit_url IS NOT NULL) ORDER BY c.id, f.id");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cid = (string)$r['cid']; $fid = (string)$r['fid'];
        $fIzmetams = $fDrikst($r);
        if (strcasecmp($uz($r['fs']), $cid) === 0)
            $lem($cid, $fid, false, 'svaigā saite ved uz pārnesto (paturam svaigo ' . $fid . ')');
        elseif (strcasecmp($uz($r['cs']), $fid) === 0)
            $lem($cid, $fid, $fIzmetams, $fIzmetams ? 'pārnestā saite ved uz šo aliasu (' . $cid . ')'
                                                    : 'svaigais alias vēl dzīvs (' . $fid . ')');
    }
    // B: tā pati tēma ar diviem ID.
    $q = $db->query("SELECT c.id cid, f.id fid, c.call_id, $dlSql $pari
          AND c.call_id IS NOT NULL AND c.call_id = f.call_id
          AND COALESCE(c.deadline_date, '') = COALESCE(f.deadline_date, '')
          AND COALESCE(c.budget_total, -1) = COALESCE(f.budget_total, -1) ORDER BY c.id, f.id");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cid = (string)$r['cid']; $fid = (string)$r['fid'];
        $cSak = str_starts_with(substr($cid, 3), (string)$r['call_id']);
        $fSak = str_starts_with(substr($fid, 3), (string)$r['call_id']);
        $fIzmetams = $fDrikst($r);
        if ($cSak && !$fSak)
            $lem($cid, $fid, $fIzmetams, $fIzmetams ? 'novecojis ID pret pārnesto ' . $cid
                                                    : 'svaigais novecojušais ID vēl dzīvs (' . $fid . ')');
        elseif ($fSak && !$cSak)
            $lem($cid, $fid, false, 'pārnestais ir novecojis ID pret ' . $fid);
    }
    foreach ($fPret as $fid => $cid) if (isset($izmest[$cid])) unset($izmest[$fid]);
    if ($izmest) {
        $del = $db->prepare('DELETE FROM grants WHERE id = ?');
        foreach (array_keys($izmest) as $id) $del->execute([$id]);
    }
    $db->exec('DROP TABLE IF EXISTS gr_beig_parnestie');
    return $izmest;
}

/**
 * Uztur tabulu izbeigtas_adreses jaunajā DB: vecās tabulas saturs + vecās DB ES adreses, kuru
 * jaunajā vairs nav, mīnus adreses, kas jaunajā IR (konkurss atgriezās). Jāsauc PĒC slug
 * piešķiršanas. Datums ir diena, kad adrese pirmo reizi pazuda; atkārtota būve to nepārraksta.
 *
 * TIKAI ES (recenzents 2026-09-23). LV avota izlaišana (--skip-lv, pārejoša kļūme, avots atdod 0)
 * pārnes tikai tā DZĪVĀS rindas, tāpēc beigušās LV kārtas uz vienu nakti pazūd un nākamajā pilnajā
 * būvē atgriežas. Ar 410 tās meklētājam teiktu "izņemts uz visiem laikiem" un nākamajā dienā atkal
 * 200 — sliktāk par 404. ES pusē pazušana ir galīga: to lemj portāls, un beigušos sedz pārnese.
 *
 * Tabula neaug neierobežoti: ~100 adrešu mēnesī, rinda ~60 baitu.
 *
 * @return array{kopa:int,jaunas:int}
 */
function gr_izbeigtas_adreses(PDO $jauna, ?PDO $veca, string $sodien): array {
    $jauna->exec('CREATE TABLE IF NOT EXISTS izbeigtas_adreses (
        slug TEXT PRIMARY KEY, id TEXT, izbeigta TEXT NOT NULL)');
    if ($veca !== null) {
        $ins = $jauna->prepare('INSERT OR IGNORE INTO izbeigtas_adreses (slug, id, izbeigta) VALUES (?,?,?)');
        $jauna->beginTransaction();
        try {
            // Vispirms vecā tabula: tā nes sākotnējo datumu, un OR IGNORE to nepārrakstīs ar šodienu.
            if (count(array_intersect(['slug', 'id', 'izbeigta'], gr_beig_kolonnas($veca, 'izbeigtas_adreses'))) === 3)
                foreach ($veca->query('SELECT slug, id, izbeigta FROM izbeigtas_adreses')->fetchAll(PDO::FETCH_NUM) as $r)
                    $ins->execute($r);
            if (in_array('slug', gr_beig_kolonnas($veca, 'grants'), true))
                foreach ($veca->query("SELECT slug, id FROM grants WHERE slug IS NOT NULL AND slug <> '' AND id LIKE 'EU:%'")->fetchAll(PDO::FETCH_NUM) as $r)
                    $ins->execute([$r[0], $r[1], $sodien]);
            // Dzīva adrese nekad nav "izbeigta" — arī tad, ja to tagad nes cits konkurss.
            $jauna->exec("DELETE FROM izbeigtas_adreses WHERE slug IN (SELECT slug FROM grants WHERE slug IS NOT NULL)");
            $jauna->commit();
        } catch (Throwable $e) {
            $jauna->rollBack();
            throw $e;
        }
    }
    $kopa = (int)$jauna->query('SELECT COUNT(*) FROM izbeigtas_adreses')->fetchColumn();
    $st = $jauna->prepare('SELECT COUNT(*) FROM izbeigtas_adreses WHERE izbeigta = ?');
    $st->execute([$sodien]);
    return ['kopa' => $kopa, 'jaunas' => (int)$st->fetchColumn()];
}
