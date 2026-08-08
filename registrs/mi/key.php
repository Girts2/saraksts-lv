<?php
// registrs/mi/key.php — Google Gemini API atslēgas.
//
// PUBLISKAJĀ IZLAIDUMĀ ATSLĒGAS IR NOŅEMTAS. Ieliec savas, lai ieslēgtu MI funkcijas
// (uzņēmumu lapu MI panelis, iepirkumu virsrakstu tulkošana). Bez tām pārējā sistēma
// strādā normāli — MI daļa vienkārši klusē.
//
// Atslēgu iegūsti bez maksas: https://aistudio.google.com/apikey
//
// Kāpēc te ir XOR+base64, nevis vienkāršs teksts: oriģinālā šis fails glabājas ārpus
// versiju kontroles, un obfuskācija pasargā tikai no automātiskiem GitHub skeneriem.
// TĀ NAV ŠIFRĒŠANA. Ja tavs projekts ir publisks, glabā atslēgu vides mainīgajā
// (getenv), nevis šajā failā.

/** Maksas / galvenā atslēga. */
function _get_g_key(): string
{
    return (string) (getenv('GEMINI_API_KEY') ?: '');
}

/**
 * Bezmaksas līmeņa atslēga (atsevišķs Google projekts) — ikdienas iepirkumu
 * virsrakstu tulkošanai cron sinhronizācijā. Drīkst būt tā pati, kas augšā.
 */
function _get_g_key_free(): string
{
    return (string) (getenv('GEMINI_API_KEY_FREE') ?: _get_g_key());
}

$gemini_api_key = _get_g_key();
