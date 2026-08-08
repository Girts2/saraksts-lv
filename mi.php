<?php
/**
 * mi.php — SLĒPTAIS AI Satiksmes & Drošības panelis (ports no Reģistrs/kods/mi.php).
 * Rāda, kurš (IP), par kuru uzņēmumu un ar kādu analīzes pogu lieto MI pēdējās 24 h.
 *
 * Piekļuve: /mi.php?k=<token no admin_token.php>  (tas pats token, ko lieto data_admin.php).
 * Atšķirībā no vecā Reģistrs mi.php šis NAV publisks — žurnālā ir IP adreses (personas dati).
 */
declare(strict_types=1);

require_once __DIR__ . '/registrs/lib/timezone.php'; // šī lapa rāda laikus; bez tā UTC

// --- Autentifikācija (tāda pati kā data_admin.php) ---
$TOKEN = require __DIR__ . '/admin_token.php';
$k = $_GET['k'] ?? $_POST['k'] ?? '';
if (!is_string($k) || !hash_equals($TOKEN, $k)) {
    http_response_code(404); // slēpjamies kā 404
    exit;
}

$log_file = __DIR__ . '/registrs/ai_cache/ai_requests_log.json';
$requests = [];

if (file_exists($log_file)) {
    $content = file_get_contents($log_file);
    $requests = json_decode($content, true) ?: [];
}

// Drošības slēdža uzstādījumi — tie paši, ko lieto MI dzinējs (master_top.php),
// lai panelis rādītu reālos, nevis iekodētos sliekšņus.
$sec_cfg = ['protection_active' => true, 'global_max_limit' => 30];
$switch_file = __DIR__ . '/registrs/mi/switch.php';
if (file_exists($switch_file)) {
    $loaded = include($switch_file);
    if (is_array($loaded)) $sec_cfg = array_merge($sec_cfg, $loaded);
}
$limit_max = max(5, (int)$sec_cfg['global_max_limit']);          // giljotīna
$limit_l1  = max(2, (int)floor($limit_max / 6));                 // 5s aizture + CAPTCHA slieksnis
$limit_l2  = max(5, (int)floor($limit_max / 2));                 // 15s aizture

// Apgriežam datus, lai garantētu tikai pēdējās 24h
$current_time = time();
$requests = array_filter($requests, function($req) use ($current_time) {
    return ($current_time - $req['time']) <= 86400;
});

// Atdalām pēdējās 10 minūtes
$recent_requests = array_filter($requests, function($req) use ($current_time) {
    return ($current_time - $req['time']) <= 600;
});

$count_10m = count($recent_requests);
$count_24h = count($requests);

$esc_lock_file = __DIR__ . '/registrs/ai_cache/escalation_block.time';
$is_hard_blocked = false;
$hard_block_min_left = 0;
if (file_exists($esc_lock_file)) {
    $esc_expires = (int)@file_get_contents($esc_lock_file);
    if ($current_time < $esc_expires) {
        $is_hard_blocked = true;
        $hard_block_min_left = (int)ceil(($esc_expires - $current_time) / 60);
    }
}

$email_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    $to = "admin@example.com";
    $subject = "TESTS: AI API Satiksme & Drošība (" . date('H:i') . ")";
    $msg = "Šis ir mēģinājuma e-pasts no mi.php statistikas paneļa.\n\n";
    $msg .= "Pēdējo 10 minūšu dzīvā slodze: " . $count_10m . " / {$limit_max} max\n";
    $msg .= "Kopējā 24 stundu slodze: " . $count_24h . " pieprasījumi\n\n";

    $msg .= "Pēdējie notikumi (līdz 10 gab):\n";

    $tmp_req = $requests;
    usort($tmp_req, function($a, $b) { return $b['time'] <=> $a['time']; });

    $latest = array_slice($tmp_req, 0, 10);
    foreach ($latest as $r) {
        $msg .= "- [" . date('H:i:s', $r['time']) . "] IP: {$r['ip']} | Reģ.Nr: {$r['reg_nr']} | {$r['category']}\n";
    }

    $headers = "From: info@example.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($to, $subject, $msg, $headers);
    $email_sent = true;
}

// Sakārtojam dilstošā secībā (jaunākie augšā)
usort($requests, function($a, $b) {
    return $b['time'] <=> $a['time'];
});

// TOP lietotāji (24h): grupējam pēc IP — atbild uz "kurš lieto un cik daudz".
$ip_stats = [];
foreach ($requests as $req) {
    $ip = $req['ip'] ?? 'Nezināms';
    if (!isset($ip_stats[$ip])) {
        $ip_stats[$ip] = ['count' => 0, 'last_time' => 0, 'categories' => [], 'reg_nrs' => []];
    }
    $ip_stats[$ip]['count']++;
    $ip_stats[$ip]['last_time'] = max($ip_stats[$ip]['last_time'], (int)$req['time']);
    $cat = $req['category'] ?? '?';
    $ip_stats[$ip]['categories'][$cat] = ($ip_stats[$ip]['categories'][$cat] ?? 0) + 1;
    $ip_stats[$ip]['reg_nrs'][$req['reg_nr'] ?? '?'] = true;
}
uasort($ip_stats, function($a, $b) { return $b['count'] <=> $a['count']; });
$top_ips = array_slice($ip_stats, 0, 8, true);

// Aprēķinam "Spidometra" rādītāju
$gauge_percent = min(100, ($count_10m / $limit_max) * 100);
$gauge_color = '#2ecc71'; // Zaļš
if ($count_10m >= $limit_l1) $gauge_color = '#f1c40f'; // Dzeltens
if ($count_10m >= $limit_l2) $gauge_color = '#e67e22'; // Oranžs
if ($count_10m >= $limit_max) $gauge_color = '#e74c3c'; // Sarkans
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>AI API Satiksmes Panelis</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --panel-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --accent: #3b82f6;
        }
        body {
            margin: 0; padding: 20px;
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
        }
        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0; font-size: 24px;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .card {
            background: var(--panel-bg);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .card h2 {
            margin-top: 0; margin-bottom: 15px; font-size: 16px; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 1px;
        }
        .stat-value {
            font-size: 48px; font-weight: 700; line-height: 1; margin-bottom: 10px;
        }
        .gauge-container {
            width: 100%;
            height: 20px;
            background: var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 20px;
        }
        .gauge-bar {
            height: 100%;
            width: <?= $gauge_percent ?>%;
            background-color: <?= $gauge_color ?>;
            transition: width 0.5s ease-out;
        }
        .gauge-labels {
            display: flex;
            justify-content: space-between;
            font-size: 12px; color: var(--text-muted);
            margin-top: 8px;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%; border-collapse: collapse; text-align: left;
        }
        th, td {
            padding: 12px 16px; border-bottom: 1px solid var(--border-color);
        }
        th {
            background: #0f172a;
            color: var(--text-muted);
            font-weight: 600; font-size: 13px; text-transform: uppercase;
        }
        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        td {
            font-size: 14px;
        }
        .badge {
            display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;
            background: var(--border-color); color: var(--text-main);
        }
        .ip-badge { background: #3b82f620; border: 1px solid #3b82f640; color: #60a5fa; }
        a.reg-link { color: #60a5fa; text-decoration: none; }
        a.reg-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <div>
                <h1>🛡️ AI API Satiksme & Drošība</h1>
                <div style="color: var(--text-muted); margin-top: 5px;">
                    Pēdējais atjauninājums: <?= date('H:i:s') ?> ·
                    Aizsardzība: <?= $sec_cfg['protection_active'] ? '<span style="color:#2ecc71;">IESLĒGTA</span>' : '<span style="color:#e74c3c;">IZSLĒGTA (switch.php)</span>' ?>
                </div>
            </div>
            <div>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="k" value="<?= htmlspecialchars($k) ?>">
                    <button type="submit" name="send_test_email" style="background: var(--accent); color: white; border: none; padding: 10px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='var(--accent)'">
                        ✉️ Sūtīt Testa Pārskatu
                    </button>
                </form>
            </div>
        </div>

        <?php if($is_hard_blocked): ?>
        <div style="background: rgba(231, 76, 60, 0.15); border: 2px solid #e74c3c; color: #e74c3c; padding: 20px; border-radius: 8px; margin-bottom: 30px; font-weight: 700; font-size: 18px; text-align: center; text-transform: uppercase;">
            🚨 IESLĒGTA 30 MIN ESKALĀCIJAS AIZSARDZĪBA 🚨<br>
            <span style="font-size: 14px; font-weight: 600; color: #c0392b; text-transform: none; display: block; margin-top: 8px;">Visi AI API zvani šobrīd ir pilnībā apturēti. Sistēma atjaunosies pēc <?= $hard_block_min_left ?> minūtēm.</span>
        </div>
        <?php endif; ?>

        <?php if($email_sent): ?>
        <div style="background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; color: #2ecc71; padding: 15px; border-radius: 8px; margin-bottom: 30px; font-weight: 600;">
            ✅ Testa e-pasts veiksmīgi sagatavots un nosūtīts uz <strong>admin@example.com</strong>! Lūdzu pārbaudi savu e-pastkastīti.
        </div>
        <?php endif; ?>

        <div class="metrics-grid">
            <div class="card">
                <h2>Dzīvā slodze (Pēdējās 10 min)</h2>
                <div class="stat-value" style="color: <?= $gauge_color ?>;"><?= $count_10m ?> <span style="font-size: 16px; color: var(--text-muted); font-weight: normal;">/ <?= $limit_max ?> max</span></div>
                <div>Līmenis:
                    <?php if($count_10m < $limit_l1): ?>🟢 Miers (0s delay)
                    <?php elseif($count_10m < $limit_l2): ?>🟡 Uzmanība (5s delay + CAPTCHA)
                    <?php elseif($count_10m < $limit_max): ?>🟠 Briesmas (15s delay)
                    <?php else: ?>🔴 BLOĶĒTS (Stop Poga)<?php endif; ?>
                </div>
                <div class="gauge-container">
                    <div class="gauge-bar"></div>
                </div>
                <div class="gauge-labels">
                    <span>0</span>
                    <span><?= $limit_l2 ?></span>
                    <span><?= $limit_max ?>+ (Kill Switch)</span>
                </div>
            </div>

            <div class="card">
                <h2>Ilgtermiņa slodze (24 Stundas)</h2>
                <div class="stat-value"><?= $count_24h ?></div>
                <div style="color: var(--text-muted);">Kopējie AI pieprasījumi</div>
                <div style="margin-top: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px; padding: 10px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.2);">
                    <div style="font-size: 20px;">💡</div>
                    Pārmēru augsts skaits šeit norāda uz naudas makas strauju iztukšošanu, pat ja 10 minūšu logs netika pārkāpts.
                </div>
            </div>

            <div class="card">
                <h2>TOP lietotāji (24h, pēc IP)</h2>
                <?php if (empty($top_ips)): ?>
                    <div style="color: var(--text-muted);">Nav datu.</div>
                <?php else: ?>
                    <table style="font-size: 13px;">
                        <?php foreach ($top_ips as $ip => $st): ?>
                            <tr>
                                <td style="padding: 6px 8px;"><span class="badge ip-badge"><?= htmlspecialchars((string)$ip) ?></span></td>
                                <td style="padding: 6px 8px; font-weight: 700;"><?= $st['count'] ?>×</td>
                                <td style="padding: 6px 8px; color: var(--text-muted); white-space: nowrap;"><?= count($st['reg_nrs']) ?> uzņ. · <?= date('H:i', $st['last_time']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <h2 style="padding: 24px 24px 0 24px;">Pēdējo 24 stundu API Pieprasījumi</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Laiks</th>
                            <th>IP Adrese</th>
                            <th>Reģ. Nr. (Lapa)</th>
                            <th>Kategorija (mērķis)</th>
                            <th>Aģents (Pārlūks)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($requests)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">Nav reģistrētu pieprasījumu pēdējās 24 stundās.</td></tr>
                        <?php else: ?>
                            <?php foreach($requests as $req): ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?= date('H:i:s / d.m.', $req['time']) ?></td>
                                    <td><span class="badge ip-badge"><?= htmlspecialchars($req['ip']) ?></span></td>
                                    <td>
                                        <?php $rn = preg_replace('/\D/', '', (string)($req['reg_nr'] ?? '')); ?>
                                        <?php if (strlen($rn) === 11): ?>
                                            <a class="reg-link" href="/<?= $rn ?>" target="_blank"><span class="badge"><?= htmlspecialchars($req['reg_nr']) ?></span></a>
                                        <?php else: ?>
                                            <span class="badge"><?= htmlspecialchars($req['reg_nr']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($req['category']) ?></td>
                                    <td style="font-size: 12px; color: var(--text-muted); max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($req['agent']) ?>">
                                        <?= htmlspecialchars($req['agent']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
