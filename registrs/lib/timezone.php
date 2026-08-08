<?php
/**
 * registrs/lib/timezone.php — vienota laika zona visai sistēmai.
 *
 * KĀPĒC: PHP noklusējums šeit bija UTC, bet serveris un lietotāji dzīvo Rīgas laikā,
 * tāpēc VISI žurnāli (build.log, konkursi sync.log) rādīja laiku 3 h atpakaļ. Būve,
 * kas sākās 20:40, žurnālā bija "17:40" — salīdzināt ar cron vai citiem notikumiem
 * bija jārēķina galvā, un tas jau vienreiz maldināja, meklējot iekāršanās brīdi.
 *
 * KĀPĒC TAS IR DROŠI (pārbaudīts pirms maiņas):
 *   · `konkursi_today()` jau lieto skaidru Europe/Riga → dienas budžets nemainās;
 *   · `ks_within_retention()` arī rēķina ar skaidru Europe/Riga;
 *   · `store.php` raksta `date('c')` — ISO 8601 AR nobīdi, tāpēc vecie ieraksti
 *     (+00:00) un jaunie (+03:00) apzīmē to pašu brīdi, un SQLite date() abus
 *     normalizē uz UTC → grupēšana pa dienām nemainās;
 *   · nekur netiek salīdzināts ar SQLite CURRENT_TIMESTAMP (pārbaudīts — nav lietots).
 *
 * Pārrakstāms ar vidi: REG_TZ=UTC php ...
 */
declare(strict_types=1);

if (!function_exists('reg_init_timezone')) {

    /**
     * Iestata procesa noklusējuma laika zonu. Droši izsaukt vairākkārt.
     * @return string faktiski iestatītā zona
     */
    function reg_init_timezone(string $fallback = 'Europe/Riga'): string
    {
        static $set = null;
        if ($set !== null) return $set;

        $want = getenv('REG_TZ');
        if (!is_string($want) || $want === '') $want = $fallback;

        // Nederīgu zonu nepieņemam klusi — labāk paliekam pie UTC nekā strādājam
        // ar nejaušu nobīdi.
        if (!in_array($want, timezone_identifiers_list(), true)) $want = 'UTC';

        date_default_timezone_set($want);
        return $set = $want;
    }
}

reg_init_timezone();
