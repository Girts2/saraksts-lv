<?php
/**
 * konkursi/bin/scrub_orgs.php — VIENREIZĒJA migrācija: notīra personas datus
 * no notices.organizations (2026-07-21 audita atradums).
 *
 * ted_parser/iub_parser organizāciju sarakstā glabāja kontaktpersonas vārdu
 * ('contact'), e-pastu un tālruni. Jaunie importi to vairs nedara
 * (ks_public_org); šis skripts pielīdzina jau savāktās rindas tai pašai
 * politikai: 'contact'+'phone' ārā vienmēr, 'email' paliek tikai iestādes
 * adresēm (ks_public_contact heiristika).
 *
 * Lietošana:
 *   php konkursi/bin/scrub_orgs.php          # sausā palaišana (tikai saskaita)
 *   php konkursi/bin/scrub_orgs.php --apply  # tiešām pārraksta
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

$apply = in_array('--apply', $argv, true);
$pdo = konkursi_db();

$sel = $pdo->query("SELECT rowid, id, organizations FROM notices
                    WHERE organizations LIKE '%\"contact\"%'
                       OR organizations LIKE '%\"phone\"%'
                       OR organizations LIKE '%\"email\"%'");
$upd = $pdo->prepare('UPDATE notices SET organizations = ? WHERE rowid = ?');

$rows = 0; $changed = 0; $keptEmails = 0; $droppedEmails = 0;
$pdo->beginTransaction();
foreach ($sel as $r) {
    $rows++;
    $orgs = json_decode((string)$r['organizations'], true);
    if (!is_array($orgs)) continue;
    $dirty = false;
    foreach ($orgs as $i => $o) {
        if (!is_array($o)) continue;
        if (!isset($o['contact']) && !isset($o['phone']) && !isset($o['email'])) continue;
        $hadEmail = isset($o['email']);
        $clean = ks_public_org($o);
        if ($clean !== $o) { $orgs[$i] = $clean; $dirty = true; }
        if ($hadEmail) { isset($clean['email']) ? $keptEmails++ : $droppedEmails++; }
    }
    if ($dirty) {
        $changed++;
        if ($apply) $upd->execute([json_encode(array_values($orgs), JSON_UNESCAPED_UNICODE), $r['rowid']]);
    }
}
$apply ? $pdo->commit() : $pdo->rollBack();

printf("%s: apskatītas %d rindas, %s %d; e-pasti — paturēti (iestādes) %d, izmesti (personiski/neskaidri) %d.\n",
    $apply ? 'IZPILDĪTS' : 'SAUSĀ PALAIŠANA',
    $rows, $apply ? 'pārrakstītas' : 'pārrakstāmas', $changed, $keptEmails, $droppedEmails);
