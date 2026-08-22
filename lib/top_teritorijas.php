<?php
/**
 * lib/top_teritorijas.php — reģionālo TOP lapu teritoriju kopa (top.php + sitemap).
 *
 * 42 teritorijas: 7 valstspilsētas + 35 novadi (ATVK 2021; Varakļānu novads kopš
 * 2025-07-01 Madonas novadā). TP_VECIE_NOVADI kartē UR adresēs vēl sastopamos pirms-2021
 * novadus uz pašreizējiem. Tīri dati bez loģikas — to lieto top.php (lapas) un
 * registrs/build/report_tracker.php (sitemap-0 URL /top/{slug}), tāpēc konstantes
 * dzīvo šeit, nevis lapā.
 */
declare(strict_types=1);

// ── Teritorijas (7 valstspilsētas + 35 novadi; ATVK 2021 + Varakļānu→Madonas 2025) ───
// [location DB vērtība] => [rādāmais nosaukums, ģenitīvs virsrakstam, slug]
const TP_TERITORIJAS = [
    'Rīga'                => ['Rīga', 'Rīgas', 'riga'],
    'Daugavpils'          => ['Daugavpils', 'Daugavpils', 'daugavpils'],
    'Jelgava'             => ['Jelgava', 'Jelgavas', 'jelgava'],
    'Jūrmala'             => ['Jūrmala', 'Jūrmalas', 'jurmala'],
    'Liepāja'             => ['Liepāja', 'Liepājas', 'liepaja'],
    'Rēzekne'             => ['Rēzekne', 'Rēzeknes', 'rezekne'],
    'Ventspils'           => ['Ventspils', 'Ventspils', 'ventspils'],
    'Aizkraukles nov.'    => ['Aizkraukles novads', 'Aizkraukles novada', 'aizkraukles-novads'],
    'Alūksnes nov.'       => ['Alūksnes novads', 'Alūksnes novada', 'aluksnes-novads'],
    'Augšdaugavas nov.'   => ['Augšdaugavas novads', 'Augšdaugavas novada', 'augsdaugavas-novads'],
    'Ādažu nov.'          => ['Ādažu novads', 'Ādažu novada', 'adazu-novads'],
    'Balvu nov.'          => ['Balvu novads', 'Balvu novada', 'balvu-novads'],
    'Bauskas nov.'        => ['Bauskas novads', 'Bauskas novada', 'bauskas-novads'],
    'Cēsu nov.'           => ['Cēsu novads', 'Cēsu novada', 'cesu-novads'],
    'Dienvidkurzemes nov.'=> ['Dienvidkurzemes novads', 'Dienvidkurzemes novada', 'dienvidkurzemes-novads'],
    'Dobeles nov.'        => ['Dobeles novads', 'Dobeles novada', 'dobeles-novads'],
    'Gulbenes nov.'       => ['Gulbenes novads', 'Gulbenes novada', 'gulbenes-novads'],
    'Jelgavas nov.'       => ['Jelgavas novads', 'Jelgavas novada', 'jelgavas-novads'],
    'Jēkabpils nov.'      => ['Jēkabpils novads', 'Jēkabpils novada', 'jekabpils-novads'],
    'Krāslavas nov.'      => ['Krāslavas novads', 'Krāslavas novada', 'kraslavas-novads'],
    'Kuldīgas nov.'       => ['Kuldīgas novads', 'Kuldīgas novada', 'kuldigas-novads'],
    'Ķekavas nov.'        => ['Ķekavas novads', 'Ķekavas novada', 'kekavas-novads'],
    'Limbažu nov.'        => ['Limbažu novads', 'Limbažu novada', 'limbazu-novads'],
    'Līvānu nov.'         => ['Līvānu novads', 'Līvānu novada', 'livanu-novads'],
    'Ludzas nov.'         => ['Ludzas novads', 'Ludzas novada', 'ludzas-novads'],
    'Madonas nov.'        => ['Madonas novads', 'Madonas novada', 'madonas-novads'],
    'Mārupes nov.'        => ['Mārupes novads', 'Mārupes novada', 'marupes-novads'],
    'Ogres nov.'          => ['Ogres novads', 'Ogres novada', 'ogres-novads'],
    'Olaines nov.'        => ['Olaines novads', 'Olaines novada', 'olaines-novads'],
    'Preiļu nov.'         => ['Preiļu novads', 'Preiļu novada', 'preilu-novads'],
    'Rēzeknes nov.'       => ['Rēzeknes novads', 'Rēzeknes novada', 'rezeknes-novads'],
    'Ropažu nov.'         => ['Ropažu novads', 'Ropažu novada', 'ropazu-novads'],
    'Salaspils nov.'      => ['Salaspils novads', 'Salaspils novada', 'salaspils-novads'],
    'Saldus nov.'         => ['Saldus novads', 'Saldus novada', 'saldus-novads'],
    'Saulkrastu nov.'     => ['Saulkrastu novads', 'Saulkrastu novada', 'saulkrastu-novads'],
    'Siguldas nov.'       => ['Siguldas novads', 'Siguldas novada', 'siguldas-novads'],
    'Smiltenes nov.'      => ['Smiltenes novads', 'Smiltenes novada', 'smiltenes-novads'],
    'Talsu nov.'          => ['Talsu novads', 'Talsu novada', 'talsu-novads'],
    'Tukuma nov.'         => ['Tukuma novads', 'Tukuma novada', 'tukuma-novads'],
    'Valkas nov.'         => ['Valkas novads', 'Valkas novada', 'valkas-novads'],
    'Valmieras nov.'      => ['Valmieras novads', 'Valmieras novada', 'valmieras-novads'],
    'Ventspils nov.'      => ['Ventspils novads', 'Ventspils novada', 'ventspils-novads'],
];
// Vecie novadi un pilsētas, kas UR adresēs vēl sastopamas → pašreizējā teritorija.
// Avots: Administratīvo teritoriju un apdzīvoto vietu likums (2020) un tā grozījumi.
// Varakļānu novads 2021. g. palika atsevišķs (Satversmes tiesa), bet 2025-07-01 pievienots
// Madonas novadam — tāpēc tas ir ŠEIT, un teritoriju ir 42 (recenzija 2026-08-22).
const TP_VECIE_NOVADI = [
    'Varakļānu nov.' => 'Madonas nov.',
    'Daugavpils nov.' => 'Augšdaugavas nov.', 'Ilūkstes nov.' => 'Augšdaugavas nov.',
    'Riebiņu nov.' => 'Preiļu nov.', 'Vārkavas nov.' => 'Preiļu nov.', 'Aglonas nov.' => 'Preiļu nov.',
    'Kārsavas nov.' => 'Ludzas nov.', 'Ciblas nov.' => 'Ludzas nov.', 'Zilupes nov.' => 'Ludzas nov.',
    'Dagdas nov.' => 'Krāslavas nov.',
    'Viļānu nov.' => 'Rēzeknes nov.',
    'Jēkabpils' => 'Jēkabpils nov.', 'Viesītes nov.' => 'Jēkabpils nov.',
    'Valmiera' => 'Valmieras nov.', 'Burtnieku nov.' => 'Valmieras nov.', 'Kocēnu nov.' => 'Valmieras nov.',
    'Mazsalacas nov.' => 'Valmieras nov.', 'Rūjienas nov.' => 'Valmieras nov.', 'Naukšēnu nov.' => 'Valmieras nov.',
    'Beverīnas nov.' => 'Valmieras nov.', 'Strenču nov.' => 'Valmieras nov.',
    'Amatas nov.' => 'Cēsu nov.', 'Vecpiebalgas nov.' => 'Cēsu nov.', 'Pārgaujas nov.' => 'Cēsu nov.',
    'Priekuļu nov.' => 'Cēsu nov.', 'Līgatnes nov.' => 'Cēsu nov.', 'Jaunpiebalgas nov.' => 'Cēsu nov.',
    'Raunas nov.' => 'Smiltenes nov.', 'Apes nov.' => 'Smiltenes nov.',
    'Ozolnieku nov.' => 'Jelgavas nov.',
    'Viļakas nov.' => 'Balvu nov.', 'Rugāju nov.' => 'Balvu nov.',
    'Alojas nov.' => 'Limbažu nov.', 'Salacgrīvas nov.' => 'Limbažu nov.',
    'Aizputes nov.' => 'Dienvidkurzemes nov.', 'Priekules nov.' => 'Dienvidkurzemes nov.',
    'Grobiņas nov.' => 'Dienvidkurzemes nov.', 'Pāvilostas nov.' => 'Dienvidkurzemes nov.',
    'Vaiņodes nov.' => 'Dienvidkurzemes nov.', 'Durbes nov.' => 'Dienvidkurzemes nov.',
    'Rucavas nov.' => 'Dienvidkurzemes nov.', 'Nīcas nov.' => 'Dienvidkurzemes nov.',
    'Stopiņu nov.' => 'Ropažu nov.', 'Garkalnes nov.' => 'Ropažu nov.',
    'Mālpils nov.' => 'Siguldas nov.', 'Krimuldas nov.' => 'Siguldas nov.', 'Inčukalna nov.' => 'Siguldas nov.',
    'Baldones nov.' => 'Ķekavas nov.',
    'Babītes nov.' => 'Mārupes nov.',
    'Ikšķiles nov.' => 'Ogres nov.', 'Lielvārdes nov.' => 'Ogres nov.', 'Ķeguma nov.' => 'Ogres nov.',
    'Brocēnu nov.' => 'Saldus nov.',
    'Skrundas nov.' => 'Kuldīgas nov.',
    'Engures nov.' => 'Tukuma nov.', 'Kandavas nov.' => 'Tukuma nov.', 'Jaunpils nov.' => 'Tukuma nov.',
    'Ērgļu nov.' => 'Madonas nov.', 'Cesvaines nov.' => 'Madonas nov.',
    'Rundāles nov.' => 'Bauskas nov.', 'Iecavas nov.' => 'Bauskas nov.', 'Vecumnieku nov.' => 'Bauskas nov.',
    'Rojas nov.' => 'Talsu nov.', 'Dundagas nov.' => 'Talsu nov.',
    'Pļaviņu nov.' => 'Aizkraukles nov.', 'Jaunjelgavas nov.' => 'Aizkraukles nov.',
    'Auces nov.' => 'Dobeles nov.',
];
