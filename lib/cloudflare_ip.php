<?php
/**
 * lib/cloudflare_ip.php — apmeklētāja IP aiz Cloudflare.
 *
 * reg_ip_no_cloudflare() ir TĀ PATI funkcija, kas registrs/templates/master_top.php
 * (MI paneļa limiti, audits 2026-08-19) — abās vietās aiz function_exists sarga,
 * tāpēc tā, kura ielādējas pirmā, derīga abām. master_top.php apzināti NEAIZTIEKAM
 * (uzņēmumu lapu veidne; jauns require tur nozīmētu jaunu atteices punktu visai
 * vietnei). Mainot Cloudflare diapazonus — mainīt ABĀS vietās.
 */
declare(strict_types=1);

if (!function_exists('reg_ip_no_cloudflare')) {
    /**
     * Vai pieprasījums nāk no Cloudflare tīkla? Tikai tad drīkst ticēt
     * CF-Connecting-IP galvenei (to var uzlikt jebkurš, kas sasniedz origin tieši).
     * Diapazoni: cloudflare.com/ips (pārbaudīti 2026-08-19).
     */
    function reg_ip_no_cloudflare(string $ip): bool {
        static $v4 = ['173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22'];
        static $v6 = ['2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32'];

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $n = ip2long($ip);
            if ($n === false) return false;
            foreach ($v4 as $cidr) {
                [$net, $bits] = explode('/', $cidr);
                $mask = -1 << (32 - (int)$bits);
                if ((ip2long($net) & $mask) === ($n & $mask)) return true;
            }
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = @inet_pton($ip);
            if ($bin === false) return false;
            foreach ($v6 as $cidr) {
                [$net, $bits] = explode('/', $cidr);
                $netBin = @inet_pton($net);
                if ($netBin === false) continue;
                $bytes = intdiv((int)$bits, 8);
                $rest  = (int)$bits % 8;
                if ($bytes > 0 && strncmp($bin, $netBin, $bytes) !== 0) continue;
                if ($rest === 0) return true;
                $m = chr(0xFF << (8 - $rest) & 0xFF);
                if ((substr($bin, $bytes, 1) & $m) === (substr($netBin, $bytes, 1) & $m)) return true;
            }
        }
        return false;
    }
}

/**
 * Apmeklētāja IP: CF-Connecting-IP tikai tad, ja pieprasījums tiešām nāk no
 * Cloudflare tīkla (to galveni var uzlikt ikviens, kas sasniedz serveri tieši).
 */
function reg_klienta_ip(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && reg_ip_no_cloudflare($ip)) return $cf;
    return $ip;
}
