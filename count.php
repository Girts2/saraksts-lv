<?php
// count.php — VAIRS NAV SKAITĪTĀJS.
//
// Līdz 2026-07-26 šis fails skaitīja lejupielādes un pāradresēja uz ZIP/XLSM.
// Uzskaite atcelta; palicis tikai 301, lai vecās saites (arī meklētājos indeksētās
// /count.php un /count.php?file=...) neatgrieztu 404, bet aizvestu uz sadaļu.
//
// Kad Search Console vairs nerāda šos URL, šo failu var mierīgi izdzēst.

header('Location: /lejupielade.php', true, 301);
exit;
