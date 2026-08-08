<?php
/**
 * cron/dispatch.php — VIENĪGAIS sistēmas cron ieejas punkts visām sadaļām.
 *
 * KĀPĒC TĀ. Koplietotā hostingā (Hostinger hPanel) PHP nevar droši rakstīt īsto
 * crontab — crontab binārs mēdz būt liegts, un pat kad nav, izmaiņas nepārdzīvo
 * konta migrācijas. Tāpēc hPanel ieliek VIENU rindu, kas šo failu sauc ik 5 minūtes,
 * bet KO tieši un CIKOS palaist, glabājas admin_state/schedule.json, ko pārvalda
 * admin.php. Grafika maiņai vairs nav jāaiztiek servera konfigurācija.
 *
 * hPanel cron rinda (Rīgas laiks jāiestata konta līmenī):
 *     *&#47;5 * * * *  php /home/<konts>/public_html/cron/dispatch.php
 *
 * Ja rinda nav ieliktas, panelis to pasaka — grafiks tad vienkārši nekad neizpildās.
 *
 * Lietošana arī manuāli:
 *     php cron/dispatch.php            # izpilda to, kam pienācis laiks
 *     php cron/dispatch.php --dry      # tikai parāda, ko darītu
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/applog.php';
require_once __DIR__ . '/../lib/admin_jobs.php';

set_time_limit(0);

const DISPATCH_STEP_MINUTES = 5;   // jāsakrīt ar hPanel cron biežumu

$dry = in_array('--dry', $argv ?? [], true);
$now = (new DateTimeImmutable('now', new DateTimeZone('Europe/Riga')))->format('Y-m-d H:i:s');

// Dispečera pēdas — lai panelī var redzēt, vai sistēmas cron tiešām strādā.
@mkdir(admin_state_dir(), 0775, true);
@file_put_contents(admin_state_dir() . '/dispatch_last.txt', $now);

$sched = admin_schedule_load();
if (!$sched) {
    echo "[$now] Grafiks tukšs — nekas nav ieplānots.\n";
    exit(0);
}

$all = admin_all_jobs();
$due = [];
foreach ($sched as $id => $entry) {
    if (!isset($all[$id])) continue;                       // darbs pa vidu izņemts
    if (!admin_schedule_due($entry, DISPATCH_STEP_MINUTES)) continue;
    if (admin_job_running($all[$id])) {
        echo "[$now] $id — jau darbojas, izlaižu.\n";
        applog_job($id, 'ok', ['piezime' => 'cron-izlaists-jau-iet']);
        continue;
    }
    $due[] = $id;
}

if (!$due) {
    echo "[$now] Nekas nav pienācis.\n";
    exit(0);
}

echo "[$now] Pienākuši: " . implode(', ', $due) . "\n";
if ($dry) { echo "(--dry — neko nepalaižu)\n"; exit(0); }

[$method, $err] = admin_launch($due);
if ($method === null) {
    echo "[$now] KĻŪDA: $err\n";
    applog_event('ERROR', 'cron', 'palaisana', (string)$err, true);
    exit(1);
}

// Atzīmē palaišanu, lai tajā pašā dienā otrreiz nepalaistu.
foreach ($due as $id) $sched[$id]['last_run'] = $now;
admin_schedule_save($sched);

applog_event('INFO', 'cron', 'palaists', implode(',', $due) . " (metode=$method)");
echo "[$now] Palaists fonā (metode: $method).\n";
