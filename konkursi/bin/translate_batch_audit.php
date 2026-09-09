<?php
/**
 * konkursi/bin/translate_batch_audit.php — Batch tulkošanas konveijera audits.
 *
 * Pārbauda tieši tos ceļus, kur var pazust NAUDA vai DATI: dubultā apmaksa,
 * budžeta griesti (arī iesniegtais, vēl nesavāktais), virsrakstu atbrīvošana pēc
 * neizdevušās darba, elementu skaita un saskanības pārbaude, esošo tulkojumu
 * nepārrakstīšana, UTF-8 uzvednes sargs, bāreņu sakopšana.
 *
 * NEIZSAUC API (izņemot vienu stāvokļa GET uz neeksistējošu darbu — 404 ceļa tests).
 * Visas DB izmaiņas, ko audits izdara, tas pats atliek atpakaļ (momentuzņēmums pirms
 * katras izmaiņas). Ņem to pašu slēdzeni, ko konveijers — nesadursies ar sinhronizāciju.
 *
 *   php konkursi/bin/translate_batch_audit.php
 *
 * Iziet ar 0, ja viss zaļš; ar 1, ja kaut kas krita. Serverī, kur translate_mode=batch,
 * 8. sadaļas tests par noklusējumu tiek ziņots kā informācija, ne kļūme.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__, 2));
require 'konkursi/lib/config.php';
require 'konkursi/lib/db.php';
require 'konkursi/lib/translate_batch.php';
require 'registrs/mi/gemini_client.php';

$pdo = konkursi_db();
ks_tb_schema($pdo);
$ok = 0; $bad = 0;
function t(string $name, bool $pass, string $detail = ''): void {
    printf("%s %-56s %s\n", $pass ? '  ✓' : '  ✗', $name, $detail);
    $pass ? $GLOBALS['ok']++ : $GLOBALS['bad']++;
}
$why = null;
$lock = ks_tb_lock($why);
if ($lock === null) { fwrite(STDERR, "✗ Auditu nevar palaist: $why\n"); exit(1); }

// Momentuzņēmums + atlikšana: audits NEKAD nedrīkst atstāt DB citādāku, nekā atrada.
$snap = [];
function nullify(PDO $pdo, string $title): void {
    global $snap;
    if (!array_key_exists($title, $snap)) {
        $snap[$title] = $pdo->query("SELECT title_lv FROM notices WHERE title=" . $pdo->quote($title) . " LIMIT 1")->fetchColumn();
    }
    $pdo->prepare("UPDATE notices SET title_lv=NULL WHERE title=?")->execute([$title]);
}
function restore_all(PDO $pdo): void {
    global $snap;
    foreach ($snap as $title => $lv) {
        $pdo->prepare("UPDATE notices SET title_lv=? WHERE title=?")->execute([$lv === false ? null : $lv, $title]);
    }
    $snap = [];
}
$termQ = "'" . implode("','", KS_TB_TERMINAL) . "'";
$foreign = fn(int $n) => $pdo->query("SELECT title FROM notices WHERE title_lv IS NOT NULL AND title_lv!=''
    AND source NOT IN ('IUB','MODTI','RSTI','ASTI','LDZ') GROUP BY title LIMIT $n")->fetchAll(PDO::FETCH_COLUMN);
$candidates = fn() => (int)$pdo->query("SELECT COUNT(*) FROM (SELECT title FROM notices
    WHERE title_lv IS NULL AND title IS NOT NULL AND title != ''
      AND title NOT IN (SELECT title FROM translate_batch_titles) GROUP BY title)")->fetchColumn();
$metaK = 'translate_paid_spend_' . konkursi_today();
$metaSave = konkursi_meta_get($pdo, $metaK);

try {
echo "1. SHĒMA\n";
$cols = array_column($pdo->query("PRAGMA table_info(translate_batches)")->fetchAll(PDO::FETCH_ASSOC), 'name');
t('translate_batches ar visām kolonnām (arī est_eur, display_name)',
  count(array_diff(['job','state','model','requests','titles','written','in_tokens','out_tokens','eur','created','updated','note','est_eur','display_name'], $cols)) === 0);
$idx = $pdo->query("PRAGMA index_list(translate_batch_titles)")->fetchAll(PDO::FETCH_ASSOC);
t('indekss uz translate_batch_titles.title', (bool)array_filter($idx, fn($i) => str_contains((string)$i['name'], 'tbt_title')));

echo "\n2. CENAS UN BUDŽETS\n";
[$pin, $pout] = ks_tb_prices();
t('batch cena ir puse no parastās', abs($pin - KONKURSI_GEMINI_IN_USD_1M / 2) < 1e-9 && abs($pout - KONKURSI_GEMINI_OUT_USD_1M / 2) < 1e-9, sprintf('$%.3f/$%.3f', $pin, $pout));
t('cenu konstantes piesietas aktīvajam modelim', reg_gemini_model() === KONKURSI_GEMINI_PRICED_MODEL, reg_gemini_model());
$vt = $foreign(5);
foreach ($vt as $x) nullify($pdo, $x);
t('testam ir īsti netulkoti virsraksti', $candidates() >= count($vt), 'kandidāti: ' . $candidates());
konkursi_meta_set($pdo, $metaK, sprintf('%.6f', KONKURSI_TRANSLATE_PAID_DAILY_EUR));
$r = ks_translate_batch_submit($pdo, 10);
t('pie IZTĒRĒTA dienas budžeta neiesniedz', $r['job'] === null && str_contains((string)($r['why'] ?? ''), 'budžet'), (string)($r['why'] ?? ''));
// Iesniegtais, vēl nesavāktais darbs skaitās budžetā (est_eur)
konkursi_meta_set($pdo, $metaK, '0');
$pdo->prepare("INSERT INTO translate_batches (job,state,model,requests,titles,created,est_eur) VALUES (?,?,?,?,?,?,?)")
    ->execute(['batches/AUDITS-INFLIGHT', 'BATCH_STATE_RUNNING', reg_gemini_model(), 1, 1, date('c'), KONKURSI_TRANSLATE_PAID_DAILY_EUR]);
$bidInf = (int)$pdo->lastInsertId();
$r2 = ks_translate_batch_submit($pdo, 10);
t('ceļā esoša darba est_eur skaitās budžetā', $r2['job'] === null && str_contains((string)($r2['why'] ?? ''), 'ceļā'), (string)($r2['why'] ?? ''));
$pdo->exec("DELETE FROM translate_batches WHERE id=$bidInf");
// Atomāra pieskaitīšana
konkursi_meta_set($pdo, $metaK, '0.5');
konkursi_meta_add($pdo, $metaK, 0.25); konkursi_meta_add($pdo, $metaK, 0.25);
t('konkursi_meta_add pieskaita atomāri', abs((float)konkursi_meta_get($pdo, $metaK) - 1.0) < 1e-6, konkursi_meta_get($pdo, $metaK));
konkursi_meta_set($pdo, $metaK, $metaSave === null ? '0' : $metaSave);
restore_all($pdo);

echo "\n3. DUBULTĀS APMAKSAS SARGS\n";
// Relatīvi, ne absolūti: serverī var būt arī citi īsti netulkoti virsraksti.
$title = $foreign(1)[0];
$base = $candidates();
nullify($pdo, $title);
t('nodzēsts tulkojums parādās kandidātos', $candidates() === $base + 1, 'kandidāti: ' . $candidates());
$pdo->prepare("INSERT INTO translate_batches (job,state,model,requests,titles,created) VALUES (?,?,?,?,?,?)")
    ->execute(['batches/AUDITS-TESTS', 'BATCH_STATE_RUNNING', reg_gemini_model(), 1, 1, date('c')]);
$bid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO translate_batch_titles (batch_id,chunk,pos,title) VALUES (?,0,0,?)")->execute([$bid, $title]);
t('atvērtā darbā esošs virsraksts netiek atkārtoti izvēlēts (Batch)', $candidates() === $base, 'kandidāti: ' . $candidates());
$src = file_get_contents('konkursi/lib/sync_engine.php');
t('tūlītējais ceļš (ks_translate_new_titles) izslēdz atvērtā darbā esošos', str_contains($src, "NOT IN (SELECT title FROM translate_batch_titles)"));
$tt = file_get_contents('konkursi/bin/translate_titles.php');
t('translate_titles.php batch režīmā iet caur Batch ceļu', str_contains($tt, 'ks_translate_batch_run($pdo, 900, $batch)'));
$cli = file_get_contents('konkursi/bin/translate_batch.php');
t('CLI --submit/--collect ņem slēdzeni', str_contains($cli, "if (\$has('--submit') || \$has('--collect'))"));

echo "\n4. NEIZDEVUŠOS UN NESASNIEDZAMU DARBU APSTRĀDE\n";
// Mērīts: nederīgs darba vārds → HTTP 400 INVALID_ARGUMENT; labi formēts, bet neeksistējošs → 404.
// Abos gadījumos nolasīt nekad neizdosies → jāatbrīvo ar kļūmes atzīmi (ne 30 h bloķēšana).
$res = ks_tb_collect_one($pdo, $pdo->query("SELECT * FROM translate_batches WHERE id=$bid")->fetch(PDO::FETCH_ASSOC));
$after = (int)$pdo->query("SELECT COUNT(*) FROM translate_batch_titles WHERE batch_id=$bid")->fetchColumn();
$st = (string)$pdo->query("SELECT state FROM translate_batches WHERE id=$bid")->fetchColumn();
t('nederīgs darba vārds (400 INVALID_ARGUMENT) atbrīvo virsrakstus', $res === null && $after === 0 && $st === 'RELEASED', "state=$st");
$pdo->prepare("INSERT INTO translate_batch_titles (batch_id,chunk,pos,title) VALUES (?,0,0,?)")->execute([$bid, $title]);
$pdo->prepare("UPDATE translate_batches SET job=?, state='BATCH_STATE_RUNNING' WHERE id=?")->execute(['batches/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $bid]);
$res = ks_tb_collect_one($pdo, $pdo->query("SELECT * FROM translate_batches WHERE id=$bid")->fetch(PDO::FETCH_ASSOC));
$after = (int)$pdo->query("SELECT COUNT(*) FROM translate_batch_titles WHERE batch_id=$bid")->fetchColumn();
$st = (string)$pdo->query("SELECT state FROM translate_batches WHERE id=$bid")->fetchColumn();
$f  = (int)$pdo->query("SELECT COALESCE(MAX(fails),0) FROM translate_batch_fails WHERE title=" . $pdo->quote($title))->fetchColumn();
t('neeksistējošs darbs (404) atbrīvo ar kļūmes atzīmi', $after === 0 && $st === 'RELEASED' && $f >= 1, "state=$st fails=$f");
t('atbrīvotais virsraksts atgriežas kandidātos', $candidates() === $base + 1, 'kandidāti: ' . $candidates());
printf("  ℹ %-56s %s\n", 'pārejoša API kļūda (5xx/429) → NEatbrīvo līdz 30 h', 'bez tīkla simulācijas netestē; sk. ks_tb_collect_one');
// Atbrīvošana vienā transakcijā, stāvoklis pēdējais
$pdo->prepare("INSERT INTO translate_batch_titles (batch_id,chunk,pos,title) VALUES (?,0,0,?)")->execute([$bid, $title]);
$pdo->prepare("UPDATE translate_batches SET state='BATCH_STATE_RUNNING' WHERE id=?")->execute([$bid]);
$n = ks_tb_release($pdo, $bid, 'BATCH_STATE_EXPIRED', 'audits');
t('ks_tb_release atbrīvo un pieraksta stāvokli', $n === 1 && (string)$pdo->query("SELECT state FROM translate_batches WHERE id=$bid")->fetchColumn() === 'BATCH_STATE_EXPIRED');
// Bāreņi: virsraksti ar terminālu vecāku tiek sakopti
$pdo->prepare("INSERT INTO translate_batch_titles (batch_id,chunk,pos,title) VALUES (?,0,0,?)")->execute([$bid, $title]);
$orph = (int)$pdo->exec("DELETE FROM translate_batch_titles WHERE batch_id NOT IN (SELECT id FROM translate_batches WHERE state NOT IN ($termQ))");
t('bāreņu virsraksti (termināls vecāks) tiek sakopti', $orph === 1, "sakopti: $orph");
t('SUBMITTING nav termināls, RELEASED/UNREADABLE/REJECTED ir',
  !in_array('SUBMITTING', KS_TB_TERMINAL, true) && in_array('RELEASED', KS_TB_TERMINAL, true)
  && in_array('UNREADABLE', KS_TB_TERMINAL, true) && in_array('REJECTED', KS_TB_TERMINAL, true));
$pdo->exec("DELETE FROM translate_batch_fails WHERE title=" . $pdo->quote($title));
$pdo->exec("DELETE FROM translate_batch_titles WHERE batch_id=$bid");
$pdo->exec("DELETE FROM translate_batches WHERE id=$bid");
restore_all($pdo);

echo "\n5. NEPAREIZU TULKOJUMU NOVĒRŠANA\n";
t('parse atgriež null, ja rindu skaits nesakrīt', reg_gemini_titles_parse('["a","b"]', 3) === null);
t('parse atgriež masīvu, ja skaits sakrīt', is_array(reg_gemini_titles_parse('["a","b","c"]', 3)));
t('parse tiek galā ar ```json ietvaru', is_array(reg_gemini_titles_parse("```json\n[\"a\"]\n```", 1)));
t('JSON objekts ar N atslēgām tiek noraidīts (nobīde)', reg_gemini_json_array('{"1":"b","0":"a"}') === null);
t('saskanība: 2 dažādi virsraksti ar vienādu tulkojumu → noraida', reg_gemini_titles_consistent(['a','b'], ['x','x']) === false);
t('saskanība: normāls gadījums iziet', reg_gemini_titles_consistent(['a','b'], ['x','y']) === true);
t('sadursmes pozīcijas: tikai sadursmē esošās', reg_gemini_titles_conflicts(['Brennholz','Дрво за огрев','Strom'], ['Malka','Malka','Elektroenerģija']) === [0,1]);
t('deģenerēta atbilde (viss vienāds) — sadursmē visi', reg_gemini_titles_conflicts(['a','b','c','d'], ['x','x','x','x']) === [0,1,2,3]);
t('skaita nesakritība — sadursmē visi', reg_gemini_titles_conflicts(['a','b','c'], ['x','y']) === [0,1,2]);
t('tukši tulkojumi nav sadursme', reg_gemini_titles_conflicts(['a','b'], ['','']) === []);
// Īstie 2026-09-05 nakts pāri: gandrīz identiski oriģināli ar pareizi vienādu tulkojumu
$alike = [
  ['Grubenentleerung in der VG Bad Ems ? Nassau 2027 + 2028', 'Grubenentleerung in der VG Bad Ems – Nassau 2027 + 2028'],
  ['Supply of Interventional Radiology Products', 'Supply of Radiology Interventional Products'],
  ['Residential detoxificaton & Rehabilitation service for residents', 'Residential detoxification rehabilitation service for residents'],
  ["Remont i modernizacja placówki oświatowej,\nwyznaczonej jako podmiot ochrony ludności", 'Remont i modernizacja placówki oświatowej, wyznaczonej jako podmiot ochrony ludności'],
  ['Устройство футбольной площадки с искусственным покрытие в районе Кукурузный мун. Комрат', 'Устройство футбольной площадки с искусственным покрытием в районе Кукурузный мун. Комрат'],
  ['DLR Standort 89081 Ulm, Wilhelm-Runge-Straße 10; TI-Bauunterhalt ? 94 200 04; RV Maler- und Lackierarbeiten (26-0246_B)', 'DLR Standort 89081 Ulm, Wilhelm-Runge-Straße 10; TI-Bauunterhalt – 94 200 04; RV Maler- und Lackierarbeiten (26-0246_B)'],
];
foreach ($alike as $i => [$a, $b]) t('gandrīz identiski oriģināli pielaisti #' . ($i + 1), reg_gemini_titles_conflicts([$a, $b, 'Strom'], ['Tulk', 'Tulk', 'Elektroenerģija']) === [], mb_substr($a, 0, 40));
t('atšķirīgi skaitļi (1. un 2. daļa) NAV viens virsraksts', reg_gemini_titles_alike('Lot 1: Construction of school', 'Lot 2: Construction of school') === false);
t('dažādu valodu oriģināli NAV viens virsraksts', reg_gemini_titles_alike('Brennholz', 'Дрво за огрев') === false);
t('pavisam cits virsraksts tajā pašā valodā NAV viens', reg_gemini_titles_alike('Lieferung von Büromöbeln', 'Lieferung von Feuerwehrfahrzeugen') === false);
// Recenzijas 2026-09-05 bīstamie pāri: dažādi iepirkumi, ko virknes līdzība pielaida
$danger = [
  ['Превоз ученика', 'Превоз радника'],
  ['Услуге мобилне телефоније', 'Услуга фиксне телефоније'],
  ['Remont drogi rejon I', 'Remont drogi rejon II'],
  ['Rekonstrukce Hradec Králové II', 'Rekonstrukce Hradec Králové'],
  ['Supply of equipment with installation', 'Supply of equipment without installation'],
  ['Services subject to VAT', 'Services not subject to VAT'],
  ['Renovation of primary school', 'Renovation of secondary school'],
  ['Budowa kompleksu A', 'Budowa kompleksu B'],
];
foreach ($danger as $i => [$a, $b]) t('dažādi iepirkumi NAV viens virsraksts #' . ($i + 1), reg_gemini_titles_alike($a, $b) === false, mb_substr($a, 0, 40));
t('romiešu cipari skaitās skaitļi, parasti vārdi ne', reg_gemini_title_is_number('iii') && reg_gemini_title_is_number('2026') && !reg_gemini_title_is_number('civil') && !reg_gemini_title_is_number('mid'));   // "mix" = M+IX = 1009, īsts romiešu skaitlis

// --- Recenzija 2026-09-05: marķieri, priedēkļi, rakstzīmju attālums, nobīdes detektors ---
$markers = [
  ['SEN19047-SEN-TAXI-Bowman Academy', 'SEN19049-SEN-TAXI-Bowman Academy'],
  ['2025/1589 - NINTEDANIB [100MG CÁPS]', '2025/1589 - NINTEDANIB [150MG CÁPS]'],
  ['Плита вологостійка OSB/3, 2500х1250х12 мм', 'Плита вологостійка OSB/3, 2500х1250х10 мм'],
  ['A 060 Cable - HVDC-Kabelsystems_Los04', 'A 060 Cable - HVDC-Kabelsystems_Los05'],
  ['Świadczenie usług cateringowych (2026r.)', 'Świadczenie usług cateringowych (2025r.)'],
  ['Roboty budowlane etap XVII', 'Roboty budowlane etap XVIII'],
];
foreach ($markers as $i => [$a, $b]) t('marķieris ar ciparu NAV drukas kļūda #' . ($i + 1), reg_gemini_titles_alike($a, $b) === false, mb_substr($a, 0, 40));
$prefixes = [
  ['Dostawa wyposażenia medycznego', 'Dostawa wyposażenia niemedycznego'],
  ['Текуће поправке и одржавање немедицинске опреме', 'Текуће поправке и одржавање медицинске опреме'],
  ['Drivmedel stationstankning - Obemannad station', 'Drivmedel stationstankning - Bemannad station'],
  ['Dostawa rowerów', 'Dostawa serwerów'],
  ['Eriarstiabi teenus (neuroloogia)', 'Eriarstiabi teenus (uroloogia)'],
  ['Rohbauarbeiten Feuerwache', 'Stahlbauarbeiten Feuerwache'],
  ['Levering kantinemeubilair', 'Levering kantoormeubilair'],
  ['NAKUP NENUJNEGA REŠEVALNEGA VOZILA', 'NAKUP NUJNEGA REŠEVALNEGA VOZILA'],
];
foreach ($prefixes as $i => [$a, $b]) t('priedēklis/celma maiņa NAV drukas kļūda #' . ($i + 1), reg_gemini_titles_alike($a, $b) === false, mb_substr($a, 0, 40));
t('rakstzīmju attālums, ne baitu: latīņu un kirilicas viena maiņa uzvedas VIENĀDI',
  reg_gemini_titles_alike('Sala tehnika', 'Salz tehnika') === reg_gemini_titles_alike('Резни алати', 'Резни алата'));
t('attālums mēra rakstzīmes', reg_gemini_word_distance('алати', 'алата', 1) === 1 && reg_gemini_word_distance('detoxificaton', 'detoxification', 1) === 1
  && reg_gemini_word_distance('kantine', 'kantoor', 1) === 2);
t('kodu izvilcējs ņem tikai marķierus ar ciparu', reg_gemini_title_codes('Piegāde 443-TL11R un maize') === ['443-TL11R']);
// Nobīdes detektors: pakete ar pielaistu blakus sadursmi, bet izvade nobīdīta par 1
$shIn = ['Maršruts CDIA05A skola', 'Maršruts CDIA05A skola.', 'Piegāde 443-TL11R', 'Remonts 2026/77', 'Maize'];
$shOut = ['Maršruts CDIA05A skola', 'Maršruts CDIA05A skola', 'Maršruts CDIA05A skola', 'Piegāde 443-TL11R', 'Remonts 2026/77'];
t('nobīde aiz pielaistas sadursmes tiek noķerta pēc kodiem', reg_gemini_titles_shifted($shIn, $shOut) === true
  && reg_gemini_titles_consistent($shIn, $shOut) === false, json_encode(reg_gemini_titles_conflicts($shIn, $shOut)));
$okOut = ['Maršruts CDIA05A skola', 'Maršruts CDIA05A skola', 'Piegāde 443-TL11R', 'Remonts 2026/77', 'Maize'];
t('īsta pakete ar pielaistu sadursmi NETIEK apzīmēta par nobīdītu', reg_gemini_titles_shifted($shIn, $okOut) === false
  && reg_gemini_titles_consistent($shIn, $okOut) === true);
$d = ks_tb_decide([1 => 'B', 0 => 'A', 2 => 'C'], ['a', 'b', '']);
t('ks_tb_decide sakārto pēc pozīcijas, tukšo liek kļūmē', $d['write'] === [['A','a'],['B','b']] && $d['bump'] === ['C'] && $d['conflicts'] === 0, json_encode($d, JSON_UNESCAPED_UNICODE));
$d = ks_tb_decide(['Brennholz','Дрво за огрев','Strom'], ['Malka','Malka','Elektroenerģija']);
t('ks_tb_decide: īsta sadursme = nobīdes pazīme → krīt visa pakete', $d['write'] === [] && $d['bump'] === ['Brennholz','Дрво за огрев','Strom'] && $d['conflicts'] === 2, json_encode($d, JSON_UNESCAPED_UNICODE));
$d = ks_tb_decide(['A','B','C','D','E'], ['a','a','b','c','d']);
t('ks_tb_decide: dublikāts + nobīde NEIERAKSTA kaimiņu tulkojumus', $d['write'] === [] && count($d['bump']) === 5, json_encode($d));
$d = ks_tb_decide(['Supply of Interventional Radiology Products', 'Supply of Radiology Interventional Products', 'Strom'], ['Piegāde', 'Piegāde', 'Elektroenerģija']);
t('ks_tb_decide: gandrīz identiski oriģināli — visa pakete rakstīta', count($d['write']) === 3 && $d['bump'] === [], json_encode($d, JSON_UNESCAPED_UNICODE));
$d = ks_tb_decide(['a','b'], null);
t('ks_tb_decide: null atbilde — visi kļūmē', $d['write'] === [] && $d['bump'] === ['a','b']);
$d = ks_tb_decide(['a','b'], ['x']);
t('ks_tb_decide: skaita nesakritība — visi kļūmē', $d['write'] === [] && $d['bump'] === ['a','b']);
t('savākšana lieto ks_tb_decide, ne paketes noraidīšanu', str_contains(file_get_contents(__DIR__ . '/../lib/translate_batch.php'), '$d = ks_tb_decide($chunk, $lv);'));
$badTitle = "UNDP-TEST \xE2\x80 nederīgs baits";
$prompt = reg_gemini_titles_prompt([$badTitle, 'Lieferung von Büromöbeln']);
t('nederīgs UTF-8 vienā virsrakstā NEIZMET pārējos no uzvednes', str_contains($prompt, 'Lieferung von'));
t('tūlītējais ceļš arī lieto saskanības pārbaudi (paketes līmenī)', str_contains($src, '!reg_gemini_titles_consistent(array_values($chunk), $lv)) { $failed += count($chunk); continue; }'));

echo "\n6. NEPĀRRAKSTA ESOŠOS TULKOJUMUS\n";
$t2 = $foreign(1)[0];
$was = (string)$pdo->query("SELECT title_lv FROM notices WHERE title=" . $pdo->quote($t2) . " LIMIT 1")->fetchColumn();
$pdo->prepare('UPDATE notices SET title_lv = ? WHERE title = ? AND title_lv IS NULL')->execute(['SABOJĀTS', $t2]);
$now = (string)$pdo->query("SELECT title_lv FROM notices WHERE title=" . $pdo->quote($t2) . " LIMIT 1")->fetchColumn();
t('UPDATE ar title_lv IS NULL nepārraksta esošu tulkojumu', $now === $was);

echo "\n7. SLĒDZENE\n";
$w2 = null; $l2 = ks_tb_lock($w2);
t('otrs process slēdzeni nedabū un saņem iemeslu', $l2 === null && $w2 === 'cits process jau strādā', (string)$w2);

echo "\n8. SINHRONIZĀCIJAS SLĒDZIS\n";
t("sync sauc batch ceļu tikai pie translate_mode='batch'", str_contains($src, "konkursi_meta_get(\$pdo, 'translate_mode') === 'batch'"));
$mode = konkursi_meta_get($pdo, 'translate_mode') ?: 'immediate';
printf("  ℹ %-56s %s\n", 'translate_mode šajā vidē', $mode);
t('tūlītējais ceļš tēriņu pieskaita atomāri', str_contains($src, 'konkursi_meta_add($pdo, $metaK, $eur)'));

echo "\n9. PAKOTNE\n";
$b = file_get_contents('tools/build_download.php');
t('*.lock, *.key un ab_*.json netiek pakoti', str_contains($b, "'*.lock'") && str_contains($b, "'*.key'") && str_contains($b, "'ab_*.html.json'"));
} finally {
    restore_all($pdo);
    konkursi_meta_set($pdo, $metaK, $metaSave === null ? '0' : $metaSave);
    // Audita mākslīgo darbu virsraksti jādzēš PIRMS pašiem darbiem — citādi paliek bāreņi.
    $pdo->exec("DELETE FROM translate_batch_titles WHERE batch_id IN
                (SELECT id FROM translate_batches WHERE job LIKE 'batches/AUDITS-%' OR job LIKE 'batches/nav-tada-darba%')");
    $pdo->exec("DELETE FROM translate_batches WHERE job LIKE 'batches/AUDITS-%' OR job LIKE 'batches/nav-tada-darba%'");
    flock($lock, LOCK_UN); fclose($lock);
}
printf("\nKOPĀ: %d ✓ / %d ✗\n", $ok, $bad);
exit($bad > 0 ? 1 : 0);
