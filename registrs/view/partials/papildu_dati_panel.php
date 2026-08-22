<?php
/**
 * papildu_dati_panel.php — "Papildu reģistri un dati" (publisks konteiners).
 *
 * KĀPĒC ŠIS EKSISTĒ (Girta 2026-08-19 lēmums): katra jaunā datu kopa sākumā bija
 * atsevišķs pilna platuma panelis. Astoņas tādas sadaļas izstiepa lapu un lielākajai
 * daļai uzņēmumu deva tukšus laukumus — vairumam ir dati tikai vienā vai divās no
 * tām. Tagad visas ir VIENĀ blokā, pēc noklusējuma SAKĻAUTAS, katrai blakus
 * ierakstu skaits; apmeklētājs atver tikai to, kas viņu interesē.
 *
 * SADAĻAS LĪGUMS (partials/sadalas/*.php): katrs fails
 *   - uzstāda $pd_nos (virsraksts), $pd_n (ierakstu skaits), $pd_kops (īss kopsavilkums),
 *   - izvada TIKAI savu saturu (bez ārējā konteinera un bez novietojuma CSS),
 *   - ja datu nav, atgriežas uzreiz un neizvada neko.
 * Konteiners sadaļu ar $pd_n = 0 vai tukšu saturu izlaiž pavisam.
 */
/** @var array $page_data */

require_once __DIR__ . '/../_tpl.php';

$pd_dir = __DIR__ . '/sadalas/';
// Secība: nauda no valsts, tad regulējums un reģistri.
// Secība: vispirms tiesiskais stāvoklis un saistības (svarīgākais par uzņēmumu),
// tad nauda no valsts, tad regulējums un reģistri.
$pd_faili = ['tiesiskais.php', 'saistibas.php',
             'iepirkumi.php', 'esfondi.php', 'atbalsts.php', 'bis.php',
             'vide.php', 'atkritumi.php', 'ptac.php', 'zva.php', 'vid_statusi.php'];

$pd_reg = preg_replace('/\D/', '', (string)($page_data['search_reg_nr'] ?? ''));
// pd_vairak() saitēm vajag reg. nr., bet parametru ķēdīt cauri 20 izsaukumiem
// visos sadaļu failos būtu trauslāk par vienu labi dokumentētu globālu.
$GLOBALS['pd_aktivais_reg'] = $pd_reg;
$pd_sadalas = [];
foreach ($pd_faili as $pd_f) {
    if (!is_file($pd_dir . $pd_f)) continue;
    // $pd_limenis: sadaļa var pieteikt savu smaguma līmeni ('risk'/'warn'/'past').
    // Tiesiskais statuss to dara, lai sakļautā rinda būtu sarkana un lietotājs
    // problēmu redzētu, sadaļu neatverot (Girta 2026-08-20).
    $pd_nos = ''; $pd_n = 0; $pd_kops = ''; $pd_limenis = '';
    ob_start();
    try {
        include $pd_dir . $pd_f;
    } catch (Throwable $e) {
        ob_end_clean();
        continue;   // viena sadaļa nedrīkst nogāzt visu bloku
    }
    $pd_saturs = ob_get_clean();
    if ($pd_n <= 0 || trim($pd_saturs) === '' || $pd_nos === '') continue;
    $pd_sadalas[] = ['nos' => $pd_nos, 'n' => $pd_n, 'kops' => $pd_kops,
                     'limenis' => $pd_limenis, 'atsl' => basename($pd_f, '.php'),
                     'saturs' => $pd_saturs];
}
if (!$pd_sadalas) return;
$pd_kopa = 0;
foreach ($pd_sadalas as $pd_s) $pd_kopa += $pd_s['n'];
?>
<div class="balance-facts papildu-facts">
    <div class="pd-head">
        <h2>Papildu reģistri un dati</h2>
        <span class="pd-chip"><?= count($pd_sadalas) ?> <?= count($pd_sadalas) % 10 === 1 && count($pd_sadalas) % 100 !== 11 ? 'sadaļa' : 'sadaļas' ?></span>
        <span class="pd-chip pd-chip-n"><?= (int)$pd_kopa ?> <?= $pd_kopa % 10 === 1 && $pd_kopa % 100 !== 11 ? 'ieraksts' : 'ieraksti' ?></span>
        <span class="pd-hint">atveriet sadaļu, lai redzētu datus</span>
    </div>
    <div class="pd-grid">
<?php foreach ($pd_sadalas as $pd_i => $pd_s): ?>
<?php
    // Sarkanais ietvars tikai tad, ja ir REĀLS risks vai brīdinājums. Uzņēmumam,
    // kam tiesiskajā ir tikai vēsturiski, sen pabeigti procesi ('past'), sarkans
    // būtu pārspīlējums — tur paliek klusinātā sarkanā svītra bez uzsvara.
    $pd_kl = 'pd-item';
    if ($pd_s['limenis'] === 'risk' || $pd_s['limenis'] === 'warn') $pd_kl .= ' pd-item-risk';
    elseif ($pd_s['limenis'] === 'past') $pd_kl .= ' pd-item-past';
?>
        <details class="<?= h($pd_kl) ?>" data-reg="<?= h($pd_reg) ?>" data-sadala="<?= h($pd_s['atsl']) ?>">
            <summary><span class="pd-ikona" aria-hidden="true"></span><span class="pd-nos"><?= h($pd_s['nos']) ?></span><span class="pd-n"><?= (int)$pd_s['n'] ?></span><?php if ($pd_s['kops'] !== ''): ?><span class="pd-kops"><?= h($pd_s['kops']) ?></span><?php endif; ?></summary>
            <div class="pd-saturs"><?= $pd_s['saturs'] ?></div>
        </details>
<?php endforeach; ?>
    </div>
</div>
