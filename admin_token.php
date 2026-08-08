<?php
// admin_token.php — slēpto administratora paneļu piekļuves atslēga.
//
// Lieto: /data_admin.php?k=..., /mi.php?k=..., /konkursi_admin.php?k=...
//
// OBLIGĀTI NOMAINI PIRMS PUBLICĒŠANAS. Ģenerē garu nejaušu virkni, piemēram:
//     php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
//
// Vēl labāk — ņem no vides mainīgā, lai atslēga nenonāk versiju kontrolē.

return getenv('ADMIN_TOKEN') ?: 'NOMAINI-SO-UZ-SAVU-SLEPENO-TOKENU';
