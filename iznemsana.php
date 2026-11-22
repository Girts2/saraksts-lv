<?php
/**
 * iznemsana.php — «Profila izņemšana» (publiskā lapa; saite kājenē abos režīmos).
 *
 * Uzņēmuma pārstāvis var palūgt izņemt uzņēmuma profilu: ar formu (serveris nosūta vēstuli uz
 * IZN_SANEMEJI adresēm, atbildes adrese = pieprasītāja e-pasts) vai pats e-pastā uz info@example.com. Lapa neko neizņem
 * automātiski — pieprasījumu izskata cilvēks; izņemšana = ieraksts registrs/lib/izslegtie_saraksts.php.
 *
 * Teksts neitrāls (2026-08-12 norāde: bez uzsaukumiem iebilst/sūdzēties) — tikai kā un kas notiek.
 * Formā glabājam tikai to, kas nonāk vēstulē; biežuma limitam IP tikai kā HMAC ar slepenu atslēgu
 * (log/iznemsana_atslega.txt — log/ ir liegta pa HTTP un nav pakotnē).
 * Lokāli (php -S) vēstuli NEsūta: raksta failā (vide IZNEMSANA_VESTULE vai sistēmas pagaidu mapē).
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/applog.php';
applog_boot('registrs');

const IZN_ADRESATS = 'info@example.com';        // publiskā adrese: lapā, mailto, From
// Formas vēstule aiziet uz VISĀM šīm adresēm (vienā vēstulē, abas laukā To). Lapā redzama tikai
// IZN_ADRESATS; otra ir īpašnieka pastkaste (pakotnē to aizvieto build_download.secrets.php scrub).
const IZN_SANEMEJI = [IZN_ADRESATS, 'admin@example.com'];
const IZN_IP_STUNDA = 3;        // pieprasījumi no vienas IP stundā
const IZN_IP_DIENA = 5;         // … un diennaktī (citādi viena IP ar 3/h aizpildītu arī kopējo limitu)
const IZN_DIENA = 30;           // pieprasījumi diennaktī kopā (aizsardzība pret masveida sūtīšanu)
const IZN_MIN_SEK = 3;          // ātrāk par šo forma aizpildīta = robots

// Limitu faila ieraksti (IP HMAC + laiks) — ne ilgāk par 24 h: vecos izmet KATRĀ pieprasījumā (arī GET),
// ne tikai nākamajā sūtījumā (privātuma politikas 7. p.; 3. audits 2026-11-22).
function izn_limits_tirit(): void {
    $f = __DIR__ . '/log/iznemsana_limits.json';
    if (!is_file($f) || !($fp = @fopen($f, 'c+'))) return;
    try {
        flock($fp, LOCK_EX);
        $dati = json_decode((string)stream_get_contents($fp), true);
        $dati = is_array($dati) ? $dati : [];
        $svaigi = array_values(array_filter($dati, fn($r) => is_array($r) && ($r['t'] ?? 0) > time() - 86400));
        if (count($svaigi) !== count($dati)) {
            ftruncate($fp, 0); rewind($fp);
            fwrite($fp, json_encode($svaigi));
        }
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
izn_limits_tirit();

$izn_kluda = '';
$izn_lauki = ['reg' => '', 'nosaukums' => '', 'vards' => '', 'saistiba' => '', 'epasts' => '', 'zina' => ''];
$izn_nosutits = isset($_GET['nosutits']);

/** Vienas rindas lauks bez vadības rakstzīmēm (arī CR/LF — tie nedrīkst nonākt vēstules galvenēs). */
function izn_rinda(mixed $v, int $max): string {
    $s = is_string($v) ? $v : '';
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

/** Slepena atslēga (log/ mapē, izveido pirmajā reizē): IP jaucējsummai un formas laika zīmoga parakstam. */
function izn_atslega(): string {
    static $k = null;
    if ($k !== null) return $k;
    $f = __DIR__ . '/log/iznemsana_atslega.txt';
    $k = is_file($f) ? trim((string)@file_get_contents($f)) : '';
    if (strlen($k) < 32) {
        $k = bin2hex(random_bytes(32));
        if (@file_put_contents($f, $k, LOCK_EX) === false) {
            // Nevar saglabāt (log/ nav rakstāma): atslēga no faila īpašībām — vājāka, bet stabila starp pieprasījumiem.
            $k = hash('sha256', __FILE__ . '|' . (string)@filemtime(__FILE__) . '|' . php_uname('n'));
        }
    }
    return $k;
}

/** Formas laika zīmogs ar parakstu: robots to nevar viltot, nepaprasot lapu. */
function izn_zimogs(int $t): string {
    return $t . '.' . substr(hash_hmac('sha256', 'iznemsana-t|' . $t, izn_atslega()), 0, 20);
}

/** Biežuma limits: true = drīkst sūtīt (un pieprasījums pieskaitīts). Fails log/ mapē (htaccess liegta). */
function izn_limits_atlauj(string $ip): bool {
    $fails = __DIR__ . '/log/iznemsana_limits.json';
    $fp = @fopen($fails, 'c+');
    if ($fp === false) return true;    // limitu nevar uzskaitīt — nebloķējam īstus pieprasījumus
    try {
        flock($fp, LOCK_EX);
        $dati = json_decode((string)stream_get_contents($fp), true);
        $dati = is_array($dati) ? $dati : [];
        $tagad = time();
        $dati = array_values(array_filter($dati, fn($r) => is_array($r) && ($r['t'] ?? 0) > $tagad - 86400));
        $h = hash_hmac('sha256', 'iznemsana-ip|' . $ip, izn_atslega());
        $ip_visi = array_filter($dati, fn($r) => ($r['h'] ?? '') === $h);
        $ip_stunda = count(array_filter($ip_visi, fn($r) => $r['t'] > $tagad - 3600));
        if ($ip_stunda >= IZN_IP_STUNDA || count($ip_visi) >= IZN_IP_DIENA || count($dati) >= IZN_DIENA) return false;
        $dati[] = ['h' => $h, 't' => $tagad];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($dati));
        return true;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (['nosaukums' => 200, 'vards' => 120, 'saistiba' => 120, 'epasts' => 200] as $k => $max) {
        $izn_lauki[$k] = izn_rinda($_POST[$k] ?? '', $max);
    }
    $izn_lauki['reg'] = preg_replace('/\D/', '', izn_rinda($_POST['reg'] ?? '', 40)) ?? '';
    $zina = is_string($_POST['zina'] ?? null) ? $_POST['zina'] : '';
    $izn_lauki['zina'] = mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $zina) ?? ''), 0, 3000);

    $zimogs = is_string($_POST['t'] ?? null) ? $_POST['t'] : '';
    $laiks = (int)strtok($zimogs, '.');
    $robots = trim((string)($_POST['vietne'] ?? '')) !== ''                     // slēptais lauks aizpildīts
        || $laiks <= 0 || !hash_equals(izn_zimogs($laiks), $zimogs)             // zīmogs nav no šīs lapas
        || time() - $laiks < IZN_MIN_SEK || time() - $laiks > 86400;

    if (strlen($izn_lauki['reg']) !== 11) {
        $izn_kluda = 'Norādiet uzņēmuma reģistrācijas numuru (11 cipari).';
    } elseif ($izn_lauki['vards'] === '') {
        $izn_kluda = 'Norādiet vārdu un uzvārdu.';
    } elseif (!filter_var($izn_lauki['epasts'], FILTER_VALIDATE_EMAIL)) {
        $izn_kluda = 'Norādiet derīgu e-pasta adresi — uz to atbildēsim.';
    } elseif ($robots) {
        // Robotam neko nepaskaidrojam, bet cilvēks (piem., pārāk ātrs vai ļoti veca lapa) var mēģināt vēlreiz.
        $izn_kluda = 'Pieprasījumu neizdevās nosūtīt. Lūdzu, mēģiniet vēlreiz vai rakstiet uz ' . IZN_ADRESATS . '.';
    } else {
        require_once __DIR__ . '/lib/cloudflare_ip.php';
        if (!izn_limits_atlauj(reg_klienta_ip())) {
            $izn_kluda = 'Pārāk daudz pieprasījumu. Lūdzu, rakstiet uz ' . IZN_ADRESATS . '.';
        } else {
            $db_nosaukums = '';
            $jau_iznemts = false;
            try {
                require_once __DIR__ . '/registrs/lib/db.php';
                require_once __DIR__ . '/registrs/lib/izslegtie.php';
                $jau_iznemts = reg_izslegts($izn_lauki['reg']);
                $st = get_ur_db()->prepare('SELECT name FROM register WHERE regcode = ?');
                $st->execute([$izn_lauki['reg']]);
                $db_nosaukums = (string)($st->fetchColumn() ?: '');
            } catch (Throwable $e) {
                // Nosaukums vēstulē ir tikai ērtībai — bez tā sūtām tik un tā.
            }

            $temats = 'Profila izņemšana: ' . $izn_lauki['reg'] . ($db_nosaukums !== '' ? ' ' . $db_nosaukums : '');
            $teksts = "Pieprasījums izņemt uzņēmuma profilu (forma saraksts.lv/iznemsana.php)\n\n"
                . "Reģistrācijas numurs: {$izn_lauki['reg']}\n"
                . 'Nosaukums reģistrā: ' . ($db_nosaukums !== '' ? $db_nosaukums : '(nav atrasts)') . "\n"
                . "Nosaukums formā: {$izn_lauki['nosaukums']}\n"
                . "Lapa: https://saraksts.lv/{$izn_lauki['reg']}" . ($jau_iznemts ? ' (jau izņemts)' : '') . "\n\n"
                . "Vārds, uzvārds: {$izn_lauki['vards']}\n"
                . "Saistība ar uzņēmumu: {$izn_lauki['saistiba']}\n"
                . "E-pasts: {$izn_lauki['epasts']}\n\n"
                . "Ziņa:\n" . ($izn_lauki['zina'] !== '' ? $izn_lauki['zina'] : '(nav)') . "\n\n"
                . 'Nosūtīts: ' . date('Y-m-d H:i:s T') . "\n";
            $galvenes = 'From: Saraksts.lv <' . IZN_ADRESATS . ">\r\n"
                . 'Reply-To: ' . $izn_lauki['epasts'] . "\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n";
            // base64 ar 76 zīmju rindām: ziņa vienā rindkopā citādi dotu > 998 baitu rindu, ko daļa
            // pasta serveru atgriež. Temats — RFC 2047 vārdi ≤ 75 zīmes, salocīti (mail() to atļauj).
            $temats_kod = mb_encode_mimeheader($temats, 'UTF-8', 'B', "\r\n");
            $teksts = chunk_split(base64_encode($teksts), 76, "\r\n");

            if (PHP_SAPI === 'cli-server') {
                // Lokāli neko nesūtām: vēstule failā (testi to pārbauda).
                $fails = getenv('IZNEMSANA_VESTULE') ?: (sys_get_temp_dir() . '/iznemsana_vestule.txt');
                $ok = @file_put_contents($fails, 'To: ' . implode(', ', IZN_SANEMEJI) . "\r\nSubject: $temats_kod\r\n$galvenes\r\n$teksts") !== false;
            } else {
                $ok = @mail(implode(', ', IZN_SANEMEJI), $temats_kod, $teksts, $galvenes);
            }
            if ($ok) {
                // Post/Redirect/Get: pārlādējot lapu, vēstule neaiziet otrreiz.
                header('Location: /iznemsana.php?nosutits=1', true, 303);
                exit;
            }
            $izn_kluda = 'Vēstuli neizdevās nosūtīt. Lūdzu, rakstiet uz ' . IZN_ADRESATS . '.';
        }
    }
}

$pageTitle = 'Profila izņemšana';
$pageDesc = 'Kā palūgt izņemt uzņēmuma profilu no vietnes Saraksts.lv.';

$izn_mailto = 'mailto:' . IZN_ADRESATS . '?subject=' . rawurlencode('Profila izņemšana')
    . '&body=' . rawurlencode("Uzņēmuma nosaukums:\nReģistrācijas numurs:\nVārds, uzvārds:\nSaistība ar uzņēmumu:\n");
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

ob_start(); ?>
    <style>
      main.dp-main { max-width: 860px; margin: 0 auto; padding: 16px 20px 60px; }
      .dp-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 24px 28px; margin-bottom: 20px; font-size: 15px; line-height: 1.6; color: #1f2937; }
      .dp-panel h1 { font-size: 24px; margin: 0 0 14px; }
      .dp-panel h2 { font-size: 18px; margin: 22px 0 10px; }
      .dp-panel ul { margin: 8px 0 12px; padding-left: 22px; }
      .dp-panel li { margin-bottom: 6px; }
      .dp-panel a { color: #3451d1; }
      .dp-muted { color: #6b7280; font-size: 13.5px; }
      .izn-forma { display: grid; gap: 14px; margin-top: 8px; }
      .izn-forma label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 4px; }
      .izn-forma .izn-neob { font-weight: 400; color: #6b7280; }
      .izn-forma input[type=text], .izn-forma input[type=email], .izn-forma textarea {
        width: 100%; box-sizing: border-box; padding: 9px 11px; font: inherit; font-size: 15px;
        border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #1f2937; }
      .izn-forma input:focus, .izn-forma textarea:focus { outline: 2px solid #3451d1; outline-offset: 1px; border-color: #3451d1; }
      .izn-forma textarea { min-height: 110px; resize: vertical; }
      .izn-divi { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
      @media (max-width: 640px) { .izn-divi { grid-template-columns: 1fr; } .dp-panel { padding: 20px 16px; } }
      .izn-poga { justify-self: start; padding: 10px 22px; font: inherit; font-weight: 600; font-size: 15px;
        color: #fff; background: #3451d1; border: 0; border-radius: 6px; cursor: pointer; }
      .izn-poga:hover { background: #2a41a8; }
      .izn-slepts { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
      .izn-zinojums { padding: 12px 14px; border-radius: 6px; margin: 0 0 16px; }
      .izn-ok { background: #ecfdf3; border: 1px solid #a7e3bd; color: #14532d; }
      .izn-kl { background: #fef2f2; border: 1px solid #f5b5b5; color: #7f1d1d; }
    </style>
<?php $extraHeadContent = ob_get_clean(); ?>
<!DOCTYPE html>
<html lang="lv">
<?php include __DIR__ . '/registrs/head/head.php'; ?>
<body>
<?php include __DIR__ . '/registrs/header.php'; ?>
<main class="dp-main">
    <div class="dp-panel">
        <h1>Profila izņemšana</h1>

<?php if ($izn_nosutits): ?>
        <p class="izn-zinojums izn-ok" role="status">Pieprasījums nosūtīts. Atbildēsim uz norādīto e-pasta adresi.</p>
<?php endif; ?>

        <p>Ja vēlaties, lai jūsu uzņēmuma profils netiktu rādīts vietnē Saraksts.lv, nosūtiet pieprasījumu
        ar formu šajā lapā vai e-pastā uz <a href="<?= $h($izn_mailto) ?>"><?= IZN_ADRESATS ?></a>.</p>

        <h2>Kas jānorāda</h2>
        <ul>
            <li>uzņēmuma reģistrācijas numurs (nosaukums — pēc vēlēšanās);</li>
            <li>pieprasītāja vārds, uzvārds un, ja iespējams, saistība ar uzņēmumu (piemēram, valdes loceklis vai pilnvarota persona);</li>
            <li>e-pasta adrese atbildei.</li>
        </ul>

        <h2>Kas notiek pēc tam</h2>
        <ul>
            <li>Katru pieprasījumu izvērtējam atsevišķi — izņemšana nav automātiska.</li>
            <li>Pārliecināmies, ka pieprasījumu iesniedz uzņēmuma pārstāvis; vajadzības gadījumā lūdzam
            apstiprinājumu, piemēram, vēstuli no uzņēmuma e-pasta adreses vai dokumentu ar drošu elektronisko parakstu.</li>
            <li>Izņemta uzņēmuma lapa vietnē vairs nav pieejama, un uzņēmums netiek rādīts meklēšanā
            un nozaru un TOP sarakstos.</li>
            <li>Uzņēmuma dati paliek oficiālajos avotos — Uzņēmumu reģistrā, VID un portālā
            <a href="https://data.gov.lv" rel="noopener">data.gov.lv</a>; uz tiem šis pieprasījums neattiecas.</li>
        </ul>

        <h2 id="forma">Pieprasījuma forma</h2>
<?php if ($izn_kluda !== ''): ?>
        <p class="izn-zinojums izn-kl" role="alert"><?= $h($izn_kluda) ?></p>
<?php endif; ?>
        <form class="izn-forma" method="post" action="/iznemsana.php#forma">
            <div class="izn-divi">
                <div>
                    <label for="izn-reg">Reģistrācijas numurs</label>
                    <input type="text" id="izn-reg" name="reg" required inputmode="numeric" maxlength="20"
                           autocomplete="off" value="<?= $h($izn_lauki['reg']) ?>">
                </div>
                <div>
                    <label for="izn-nos">Uzņēmuma nosaukums <span class="izn-neob">(nav obligāts)</span></label>
                    <input type="text" id="izn-nos" name="nosaukums" maxlength="200" autocomplete="organization" value="<?= $h($izn_lauki['nosaukums']) ?>">
                </div>
            </div>
            <div class="izn-divi">
                <div>
                    <label for="izn-vards">Vārds, uzvārds</label>
                    <input type="text" id="izn-vards" name="vards" required maxlength="120" autocomplete="name" value="<?= $h($izn_lauki['vards']) ?>">
                </div>
                <div>
                    <label for="izn-sais">Saistība ar uzņēmumu <span class="izn-neob">(nav obligāts)</span></label>
                    <input type="text" id="izn-sais" name="saistiba" maxlength="120" autocomplete="organization-title"
                           placeholder="piemēram, valdes loceklis" value="<?= $h($izn_lauki['saistiba']) ?>">
                </div>
            </div>
            <div>
                <label for="izn-epasts">E-pasts atbildei</label>
                <input type="email" id="izn-epasts" name="epasts" required maxlength="200" autocomplete="email" value="<?= $h($izn_lauki['epasts']) ?>">
            </div>
            <div>
                <label for="izn-zina">Ziņa <span class="izn-neob">(nav obligāta)</span></label>
                <textarea id="izn-zina" name="zina" maxlength="3000"><?= $h($izn_lauki['zina']) ?></textarea>
            </div>
            <div class="izn-slepts" aria-hidden="true">
                <label for="izn-vietne">Neaizpildīt</label>
                <input type="text" id="izn-vietne" name="vietne" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="t" value="<?= $h(izn_zimogs(time())) ?>">
            <button type="submit" class="izn-poga">Nosūtīt pieprasījumu</button>
            <p class="dp-muted" style="margin:0">Pieprasījumu pa e-pastu saņem Saraksts.lv uzturētājs. Norādītos datus
            izmantojam tikai šī pieprasījuma izskatīšanai un atbildei.</p>
        </form>
    </div>
</main>
<?php include __DIR__ . '/registrs/footer/footer.php'; ?>
</body>
</html>
