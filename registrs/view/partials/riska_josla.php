<?php
/**
 * riska_josla.php — Riska josla: kompakts riska kopsavilkums (PUBLISKA kopš
 * 2026-08-27 pēc Girta rīkojuma; līdz tam "Test Riska josla" aiz vārtiem).
 *
 * NOVIETOJUMS (Girta 2026-08-26 lēmums): "Papildu reģistri un dati" paneļa galvā,
 * tūlīt zem virsraksta rindas — iekļauj papildu_dati_panel.php (ob buferī, ar
 * try/catch). Uzņēmumam bez nevienas sadaļas panelis rādās tikai ar joslu.
 * Karoga čips ir enkurs uz tiesiskā statusa sadaļu tajā pašā blokā (ar JS arī
 * atver). CSS dzīvo _components.css ("Riska josla" sadaļa; assets.php versionē);
 * čipu pilnos skaidrojumus skārienekrāniem rāda papildu_dati.js pieskāriena
 * apstrādātājs (title uznirstošais tur nedarbojas).
 *
 * KĀPĒC: profesionāļa pirmās pārbaudes tests ir "nodokļu parāds, maksātnespēja,
 * ķīlas un finanses — sekundēs" (LinkedIn diskusija 2026-08). Kaskāde un semafors
 * jau eksistē, bet lietotājam neredzami: tiesiskā kaskāde slēpjas sakļautā blokā,
 * semaforu (lib/risk_semaphore.php) lieto tikai MI prompti. Šī josla abus attēlo
 * vienuviet un NEKO nerēķina pati — tikai rāda koplietojamos aprēķinus, lai lapa,
 * MI un josla nekad nerunā pretrunās.
 *
 * GODĪGUMA PIEZĪME datu robežai: nodokļu parāda SUMMA un komercķīlas atvērtajos
 * datos NAV publicētas (pārbaudīts 2026-08-25: VID parādnieku datubāze ir tikai
 * meklētājs ar drošības kodu, komercķīlu kopas nav ne UR, ne data.gov.lv katalogā)
 * — tāpēc josla to pasaka un dod saites uz oficiālajiem meklētājiem, nevis izliekas,
 * ka šo faktu nav. SAITES (recenzija 2026-08-25): parādnieku modulis ir
 * www6.vid.gov.lv/NPAR — NEJAUKT ar /VAD, tas ir amatpersonu deklarāciju meklētājs;
 * komercķīlas pārbauda info.ur.gov.lv subjekta kartītē (ur.gov.lv "registre" sadaļā
 * ķīlu pārbaudes lapas nav — tikai reģistrēšanas pamācības, un tas URL bija 404).
 *
 * RISKA LĪMEŅI (2026-11-22, lib/risk_semaphore.php galvene): sarkans «Konstatēti nopietni riska signāli» tikai
 * no reģistru faktiem (tiesiskā karoga kaskāde — maksātnespēja, likvidācija, sankcijas, PTAC, VID apturēšana) un
 * VID reitinga C; dinamika un gada pārskats — ne augstāk par dzeltenu un tikai virs sliekšņiem / formām ar pārskatiem.
 *
 * APZINĀTA DIVERĢENCE no MI semafora: vēsturiski (pabeigti) procesi joslas
 * kopvērtējumu NEpasliktina — Girta 2026-08-20 lēmums papildu blokā ir "past =
 * klusināts, sarkans signalizētu problēmu, kur tās nav", un josla tam seko;
 * MI semafors (lib/risk_semaphore.php) tos pašus vēsturiskos procesus vērtē 'warn'.
 * Karoga čips "Vēsturiski procesi" joslā paliek redzams, un kopvērtējuma teksts
 * vēsturi piemin, tāpēc lietotājam pretruna neizskatās kā noklusēšana.
 *
 */
/** @var array $page_data */

require_once __DIR__ . '/../_tpl.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/riska_kopsavilkums.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/risk_semaphore.php';

$rj_res = $page_data['results'] ?? [];
if (!is_array($rj_res)) $rj_res = [];

try {
    $rj_ts  = reg_tiesiskais_kopsavilkums($rj_res);
    $rj_sem = reg_risk_semaphore($page_data);
} catch (Throwable $e) {
    return; // josla ir kopsavilkums; kļūdai šeit nav tiesību nogāzt lapu
}

// --- Čipu atlase no semafora --------------------------------------------------
// Semafora signālus nepārrēķinām — tikai saīsinām tekstu čipam; pilnais teksts
// paliek title atribūtā. 'Juridiskie riski' izlaižam: to aizstāj tiesiskā karoga
// kaskāde, kas ir plašāka (sankcijas, PTAC, VID apturēšana).
$rj_sig = [];
foreach (($rj_sem['signals'] ?? []) as $s) {
    if (is_array($s)) $rj_sig[(string)($s['label'] ?? '')] = $s;
}
$rj_chips = [];

// Darbību izbeigušajiem (likvidēts/reorganizēts/pārtraukts — tas pats karogs, kas
// rāda lapas brīdinājuma joslu) tagadnes čipi būtu nepatiesi apgalvojumi: "Gada
// pārskats: nav iesniegts" un "Komerclikums prasa rīcību" uzņēmumam, kas likvidēts
// pirms 14 gadiem, vai zaļš "riska signālu nav" likvidētam (audits 2026-08-26 abos
// virzienos). Tādiem rādām tikai statusu + tiesisko vēsturi, bez semafora čipiem.
$rj_izbeigts = py_truthy($page_data['is_liquidated'] ?? null);

if (!$rj_izbeigts) {
$rj_rate = $rj_sig['VID nodokļu maksātāja reitings'] ?? null;
$rj_rate_val = strtoupper(trim((string)($page_data['vid_panel_data']['rating']['Reitings'] ?? '')));
// N semaforā ir 'na' (kopš 2026-11-22), tāpēc to pārbauda PIRMS 'na' filtra — citādi neitrālais čips pazustu.
if ($rj_rate !== null && $rj_rate_val !== '' && ($rj_rate['status'] !== 'na' || $rj_rate_val === 'N')) {
    if ($rj_rate_val === 'N') {
        // 'N' = neaktīvs nodokļu maksātājs (~39 tūkst., pārsvarā snaudoši vai
        // likvidēti). Semafors to MI vajadzībām vērtē 'risk', bet joslā sarkans
        // "reitings N" blakus zaļam karogam lasītos kā pārkāpums — neaktivitāte
        // ir statuss, ne pārkāpums (recenzija 2026-08-25). Tas pats princips,
        // kas PVN izslēgšanai zemāk: neitrāli pelēks, kopvērtējumu nemaina.
        $rj_chips[] = ['na', 'VID: neaktīvs nodokļu maksātājs',
            'VID reitings N — neaktīvs nodokļu maksātājs. Tas ir statuss (nav deklarētas aktivitātes), ne pārkāpums.'];
    } else {
        $rj_chips[] = [$rj_rate['status'], 'VID reitings: ' . $rj_rate_val, (string)$rj_rate['text']];
    }
}

// Gada pārskats. Pirmā pārskata termiņa sargs un formu saraksts kopš 2026-11-22 ir semaforā
// (lib/risk_semaphore.php) — tie dod 'na', un 'na' čipu nerāda; tāpēc te vairs nekas nav jāslēpj.
$rj_gp = $rj_sig['Gada pārskats'] ?? null;
if ($rj_gp !== null && $rj_gp['status'] !== 'na') {
    // Semafors pārskatam dod augstākais 'warn' (arī «nav neviena», «N gadi bez jaunāka»), tāpēc
    // «vēl nav iesniegts» — tikai zaram pirms 31.07. termiņa (tā tekstā ir tieši šie vārdi).
    $rj_gp_isais = $rj_gp['status'] === 'ok' ? 'iesniegts'
        : (str_contains((string)$rj_gp['text'], 'vēl nav iesniegts') ? 'vēl nav iesniegts' : 'nav iesniegts');
    $rj_chips[] = [$rj_gp['status'], 'Gada pārskats: ' . $rj_gp_isais, (string)$rj_gp['text']];
}

$rj_pk = $rj_sig['Pašu kapitāls'] ?? null;
if ($rj_pk !== null && $rj_pk['status'] !== 'na') {
    $rj_pk_isais = (string)($rj_pk['isais'] ?? (['ok' => 'pozitīvs', 'warn' => 'brīdinājums', 'risk' => 'negatīvs'][$rj_pk['status']] ?? ''));
    $rj_chips[] = [$rj_pk['status'], 'Pašu kapitāls: ' . $rj_pk_isais, (string)$rj_pk['text']];
}

// Dinamikas signāli tikai tad, ja tie brīdina — zaļi tie būtu troksnis.
foreach ([['Nodokļu maksājumu dinamika', 'Nodokļu maksājumi: kritums'],
          ['Darbinieku skaits', 'Darbinieku skaits: kritums'],
          ['Algu/apgrozījuma šķēre', 'Algu/apgrozījuma šķēre']] as [$rj_l, $rj_isais]) {
    $s = $rj_sig[$rj_l] ?? null;
    if ($s !== null && in_array($s['status'], ['warn', 'risk'], true)) {
        $rj_chips[] = [$s['status'], $rj_isais, (string)$s['text']];
    }
}

// PVN statuss (vid_panel_data jau izvēlējies aktuālāko rindu). Izslēgšana var būt
// gan pēc paša lūguma, gan ar VID lēmumu — atvērtie dati iemeslu nenorāda, tāpēc
// čips ir neitrāli pelēks, ne brīdinošs.
$rj_pvn = $page_data['vid_panel_data']['pvn'] ?? null;
if (is_array($rj_pvn) && !empty($rj_pvn['Aktivs'])) {
    if ($rj_pvn['Aktivs'] === 'ir') {
        $rj_chips[] = ['ok', 'PVN maksātājs', 'Reģistrēts kā aktīvs PVN maksātājs (VID publiskie dati).'];
    } elseif (trim((string)($rj_pvn['Izslegts'] ?? '')) !== '') {
        $rj_chips[] = ['na', 'Nav PVN reģistrā', 'Izslēgts no PVN maksātāju reģistra ' . trim((string)$rj_pvn['Izslegts'])
            . '. Izslēgšana var būt gan pēc paša lūguma, gan ar VID lēmumu — iemeslu atvērtie dati nenorāda.'];
    }
}
} else {
    $rj_status = trim((string)($page_data['statusText'] ?? ''));
    if ($rj_status === '') $rj_status = 'Darbība izbeigta';
    $rj_datums = trim((string)($page_data['liquidation_date'] ?? ''));
    $rj_chips[] = ['na', $rj_status . ($rj_datums !== '' && $rj_datums !== 'N/A' ? ' ' . $rj_datums : ''),
        'Uzņēmuma darbība ir izbeigta — finanšu un nodokļu signāli attiecas tikai uz vēsturi, tāpēc tos šeit nerādām.'];
}

// --- Karogs un kopvērtējums -----------------------------------------------------
if ($rj_ts['has']) {
    $rj_flag_lvl  = (string)$rj_ts['level'];              // risk | warn | past
    $rj_flag_txt  = (string)$rj_ts['head'];
} else {
    $rj_flag_lvl  = 'ok';
    // Sankcijas zaļajā NENOSAUC (2026-11-20, kā aprakstā): par tām raksta tikai, ja uzņēmums ir sarakstā (sarkanā).
    $rj_flag_txt  = 'Nav reģistrētu procesu vai liegumu';
}

// Kopvērtējums: sliktākais no karoga un čipiem. 'past' (tikai sen pabeigti procesi)
// kopvērtējumu nepasliktina — tas pats princips, kas papildu bloka sarkanajam
// ietvaram (sarkans tikai pie reāla riska).
$rj_overall = 'ok';
$rj_status_pool = [$rj_flag_lvl === 'past' ? 'ok' : $rj_flag_lvl];
foreach ($rj_chips as $c) $rj_status_pool[] = $c[0];
foreach ($rj_status_pool as $st) {
    if ($st === 'risk') { $rj_overall = 'risk'; break; }
    if ($st === 'warn') $rj_overall = 'warn';
}
$rj_overall_txt = [
    'ok'   => 'Būtiski riska signāli nav konstatēti',
    'warn' => 'Ir brīdinājuma signāli',
    'risk' => 'Konstatēti nopietni riska signāli',
][$rj_overall];
// Zaļš kopvērtējums + vēsturiski procesi: vēsturi pieminam tekstā, lai zaļais
// nelasās kā "nekad nekā nav bijis" (sk. galvenes piezīmi par diverģenci no MI).
if ($rj_overall === 'ok' && $rj_flag_lvl === 'past') {
    $rj_overall_txt .= ' (ir vēsturiski procesi)';
}
// Izbeigtai darbībai kopvērtējums ir neitrāli pelēks ar skaidru tekstu — ne zaļš
// "riska nav" (maldina), ne sarkans par vēsturi (biedē bez pamata).
if ($rj_izbeigts) {
    $rj_overall = 'izb';
    $rj_overall_txt = 'Darbība izbeigta — signāli ir vēsturiski';
}
?>
<div class="riska-josla rj-<?= h($rj_overall) ?>">
    <div class="rj-rinda">
        <span class="rj-kopa"><span class="rj-punkts" aria-hidden="true"></span><?= h($rj_overall_txt) ?></span>
<?php if ($rj_ts['has']): ?>
        <?php /* Karoga čips ir ENKURS uz tiesiskā statusa sadaļu zemāk (details id
                 liek papildu_dati_panel.php): bez JS tas joprojām aizved līdz
                 sadaļai (sakļautai), ar JS — arī atver (audits 2026-08-26; agrākā
                 <button> bez JS nedarīja neko). */ ?>
        <a class="rj-chip rj-chip-<?= h($rj_flag_lvl) ?> rj-chip-karogs" href="#pd-tiesiskais"
            title="Atvērt sadaļu “Tiesiskais statuss”"
            onclick="var d=document.getElementById('pd-tiesiskais');if(d){d.open=true;}">
            <?= h($rj_flag_txt) ?></a>
<?php else: ?>
        <span class="rj-chip rj-chip-ok"><?= h($rj_flag_txt) ?></span>
<?php endif; ?>
<?php foreach ($rj_chips as [$rj_st, $rj_txt, $rj_pilns]): ?>
        <span class="rj-chip rj-chip-<?= h($rj_st) ?>" title="<?= h($rj_pilns) ?>"><?= h($rj_txt) ?></span>
<?php endforeach; ?>
    </div>
    <p class="rj-piezime">Signāli no atvērtajiem datiem (UR, VID, PTAC). Nodokļu parāda summa un komercķīlas
        atvērtajos datos nav publicētas — tās pārbaudiet
        <a href="https://www6.vid.gov.lv/NPAR" rel="noopener nofollow" target="_blank">VID parādnieku datubāzē</a> un
        <a href="https://info.ur.gov.lv/" rel="noopener nofollow" target="_blank">UR informācijas portālā</a>.</p>
</div>
