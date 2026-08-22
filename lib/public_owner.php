<?php
/**
 * lib/public_owner.php — publiskā īpašnieka (valsts / pašvaldība) atpazīšana.
 *
 * UR datos valsts un pašvaldības ir īpašnieki BEZ reģistrācijas numura:
 * entity_type=FOREIGN_ENTITY, legal_entity_registration_number tukšs, ir tikai
 * nosaukums. Tāpēc tām nav /{regnr} lapas, un līdz 2026-08-19 uzņēmuma lapā
 * tās rādījās ar maldinošu atzīmi "(ārvalstu)".
 *
 * Šis fails ir VIENĪGAIS avots abiem lietotājiem — ipasnieks.php (profila lapa)
 * un registrs/view/partials/sadalas/saistibas.php (saite uz to) — lai atpazīšanas
 * noteikumi un slug veidošana nesašķeltos.
 */
declare(strict_types=1);

/** Publiskā īpašnieka veids pēc nosaukuma: 'valsts' | 'pašvaldība' | '' (nav publisks). */
function reg_public_owner_type(string $name): string {
    $n = mb_strtolower(trim($name), 'UTF-8');
    if ($n === '') return '';
    if (preg_match('/^latvijas republika\b/u', $n)
        || preg_match('/\bministrija\b/u', $n)
        || str_contains($n, 'valsts kanceleja')
        || str_contains($n, 'saeima')) {
        return 'valsts';
    }
    if (preg_match('/\b(pašvaldība|novada dome|pilsētas dome|valstspilsēt)\w*/u', $n)) {
        return 'pašvaldība';
    }
    return '';
}

/** Nosaukums → URL slug (latviešu diakritika → ASCII, tikai [a-z0-9-]). */
function reg_public_owner_slug(string $name): string {
    static $map = ['ā'=>'a','č'=>'c','ē'=>'e','ģ'=>'g','ī'=>'i','ķ'=>'k','ļ'=>'l','ņ'=>'n','š'=>'s','ū'=>'u','ž'=>'z',
                   'Ā'=>'a','Č'=>'c','Ē'=>'e','Ģ'=>'g','Ī'=>'i','Ķ'=>'k','Ļ'=>'l','Ņ'=>'n','Š'=>'s','Ū'=>'u','Ž'=>'z'];
    $s = strtr(mb_strtolower(trim($name), 'UTF-8'), $map);
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    return trim((string)$s, '-');
}
