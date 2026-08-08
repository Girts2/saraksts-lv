<?php
// switch.php - Drošības Galvenais Slēdzis
// Šo failu Tu vari brīvi rediģēt caur Hostinger File Manager.
// Piekļūstot pa tiešo (saraksts.lv/switch.php), interneta lietotājs redzēs tikai tukšu, baltu lapu.

// 1. AIZSARDZĪBAS STATUSS
// true = IESLĒGTA (Sistēma pilnvērtīgi sargā no botiem)
// false = IZSLĒGTA (Sistēma laiž cauri visus apmeklētājus bez CAPTCHAS un nebloķē pieprasījumus)
$protection_active = true;

// 2. GALVENAIS SISTĒMAS LIMITS (Giljotīna)
// Noklusējums: 30.
// Ja 1 vai 10 minūšu laikā reģistrētā lietotāju slodze pārsniedz šo skaitli,
// sistēma to uzskata par uzbrukumu un iedarbina aso 30 minūšu servera pilno bloķēšanu.
$global_max_limit = 30;

// =========================================================================
// NEAIZTIKT ZEMĀK ESOŠO KODU (Tas nodod iestatījumus galvenajam dzinējam)
return [
    'protection_active' => $protection_active,
    'global_max_limit' => $global_max_limit
];
?>
