<?php
// DUBULTAS IEKĻAUŠANAS SARGS. Kopš 2026-09-08 šo iekļauj footer.php (tātad KATRA
// lapa), bet 9 vietas to iekļauj arī tieši — index.php, konkursi.php, nozare.php,
// pensionars.php, lejupielade.php, struktura.php, ziedot.php, 404.php un
// templates/master_bottom.php. Bez šī sarga cookieconsent-init.js tiktu pievienots
// document.body divreiz un logs uzrādītos divkārt. Vecos tiešos izsaukumus ATSTĀJAM:
// horoskops.php un iespeja.php kājeni neiekļauj vispār, un tiem tas ir vienīgais ceļš.
if (defined('REG_COOKIE_IZVADITS')) return;
define('REG_COOKIE_IZVADITS', 1);

// ?v= = satura hash (lib/assets.php): izvietošana bez satura maiņas nemaina URL,
// citādi GSC pārskats pildījās ar viena faila daudziem ?v= variantiem.
require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/assets.php'; ?>
<link rel="stylesheet" href="<?php echo reg_asset_v('/registrs/cookie/cookieconsent.css'); ?>">

<script src="<?php echo reg_asset_v('/registrs/cookie/cookieconsent.umd.js'); ?>"></script>



<?php /* KLASISKS defer, NE type="module". Līdz 2026-09-09 init.js ielādēja inline
         modulis (createElement + appendChild). Kopš cookie.php nāk no kājenes, tas
         uzņēmumu šablonā (templates/master_bottom.php) nonāca PIRMS
         <script type="importmap"> — un importa karte, kas parādās pēc pirmā moduļa,
         Firefox (visās versijās), Safari <18.4 un Chrome <133 tiek NORAIDĪTA:
         main.js moduļi zaudē ?v= versionēšanu (audits 2026-09-09). Moduļa semantika
         te nekad nebija vajadzīga — init.js arī agrāk izpildījās kā klasisks skripts;
         defer dod to pašu "pēc parsēšanas" laiku, neaiztiekot moduļu kārtību. */ ?>
<script defer src="<?php echo reg_asset_v('/registrs/cookie/cookieconsent-init.js'); ?>"></script>
