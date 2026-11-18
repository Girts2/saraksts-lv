<?php
/**
 * Tuvākie uzņēmumi — iekšējās sasaistes bloks uzņēmuma lapas beigās.
 *
 * Dati un pamatojums: registrs/lib/saistitie.php. Īsi: Google 149 638 lapas bija
 * "atradis, bet nav pārmeklējis", jo uz tām neveda neviena iekšēja saite. Šis bloks
 * dod katrai lapai līdz 15 tematiski pamatotas izejošās saites (tā pati nozare tajā pašā
 * novadā) un ceļu uz /nozare/{kods} un /top/{teritorija}. Lapai ar apgrozījumu pirmā
 * grupa ir kaimiņi pēc apgrozījuma (lapa pati — treknrakstā savā vietā); ja savā teritorijā
 * tā ir vienīgā, — tās pašas nozares vieninieki citās teritorijās (arī ar savu rindu); citām — lielākie.
 *
 * Panelis pats klusi iziet, ja kataloga nav vai uzņēmums tajā nav atrodams.
 *
 * @var array $page_data
 */
require_once dirname(__DIR__, 2) . '/lib/saistitie.php';
// Šis panelis prot attēlot kaimiņus un vieninieku riņķi (3. versija); bez šīs konstantes bibliotēka tos
// neatgriež — tā vecs panelis ar jaunu bibliotēku (vai otrādi) nekad nerāda nepareizu virsrakstu.
if (!defined('REG_SAISTITIE_PANELIS')) define('REG_SAISTITIE_PANELIS', 3);

$sa_reg = (string)($page_data['search_reg_nr'] ?? '');
$sa     = $sa_reg !== '' ? reg_saistitie_uznemumi($sa_reg) : null;

if ($sa !== null && ($sa['tuvakie'] || $sa['valsti'])):
    $sa_ter   = $sa['teritorija']['nosaukums'] ?? '';
    $sa_nozare = $sa['nace']['nosaukums'] ?? '';
    // Lapai ar apgrozījumu saraksts ir kaimiņi pēc apgrozījuma, un lapa pati stāv savā vietā starp tiem.
    $sa_kaimini = ($sa['tuvaku_atlase'] ?? '') === 'kaimini' && !empty($sa['pats']);
    // Vieninieks savā teritorijā: tās pašas nozares vieninieki citās teritorijās — arī ar savu rindu,
    // un katrai rindai teritorija (tās visas ir dažādas, un bez tās saraksts neko nepasaka).
    $sa_vieni  = ($sa['tuvaku_atlase'] ?? '') === 'vieninieki' && !empty($sa['pats']);
    $sa_ar_sevi = $sa_kaimini || $sa_vieni;
    $sa_ter_txt = static fn(array $u): string => $sa_vieni && ($u['teritorija'] ?? '') !== ''
        ? ' <span class="sa-sis">— ' . h($u['teritorija']) . '</span>' : '';
    // Viena iezīme abām vietām (saraksta vidū un beigās). Nosaukums — kā katalogā, lai sakristu ar
    // kaimiņu rindām; ka tā ir šī pati lapa, pasaka teksts un aria-current, ne treknraksts vien.
    $sa_pats_li = $sa_ar_sevi
        ? '<li class="sa-pats" aria-current="true"><span>' . h($sa['pats']['name'])
          . ' <span class="sa-sis">(šis uzņēmums)</span>' . $sa_ter_txt($sa['pats']) . '</span>'
          . '<span class="sa-apgroz">' . tpl_num($sa['pats']['turnover'], '€') . '</span></li>'
        : '';
?>
<style>
/* .balance-facts ir flex konteiners (citiem paneļiem tas ir vajadzīgs) — bez šī
   virsraksts, piezīme un grupas sakārtojas RINDĀ, nevis viena zem otras. */
.saistitie-panel { display: block; }
.saistitie-panel .sa-grupa { margin-bottom: 14px; }
.saistitie-panel .sa-grupa:last-of-type { margin-bottom: 6px; }
.saistitie-panel h3 { font-size: 15px; margin: 0 0 6px; color: #334; font-weight: 600; }
.saistitie-panel ul { list-style: none; margin: 0; padding: 0; }
.saistitie-panel li { padding: 4px 0; border-bottom: 1px solid #f0f1f5; display: flex;
    justify-content: space-between; gap: 12px; align-items: baseline; }
.saistitie-panel li:last-child { border-bottom: 0; }
.saistitie-panel li a { text-decoration: none; }
.saistitie-panel li a:hover { text-decoration: underline; }
.saistitie-panel .sa-apgroz { color: #6b7280; font-size: 13px; white-space: nowrap; }
.saistitie-panel li.sa-pats { font-weight: 600; }
.saistitie-panel .sa-sis { font-weight: 400; color: #6b7280; font-size: 13px; }
.saistitie-panel .sa-celi { margin: 10px 0 0; font-size: 14px; }
.saistitie-panel .sa-celi a { font-weight: 600; }
.saistitie-panel .sa-piezime { color: #6b7280; font-size: 13px; margin: 0 0 12px; }
</style>
<div class="balance-facts saistitie-panel">
    <h2>Tuvākie uzņēmumi</h2>
    <p class="sa-piezime">Apgrozījums no katra uzņēmuma jaunākā iesniegtā gada pārskata.</p>

<?php if ($sa['tuvakie']): ?>
    <div class="sa-grupa">
        <h3>
<?php if ($sa_kaimini && $sa['tuvaku_veids'] === 'nozare-teritorija'): ?>
            Blakus pēc apgrozījuma: <?= $sa_nozare !== '' ? h($sa_nozare) : 'tā pati nozare' ?><?= $sa_ter !== '' ? ' — ' . h($sa_ter) : '' ?>
<?php elseif ($sa_kaimini): /* nozare nav zināma: saraksts ir TIKAI no tādiem pašiem, un virsraksts to pasaka */ ?>
            Blakus pēc apgrozījuma: bez norādītas nozares<?= $sa_ter !== '' ? ' — ' . h($sa_ter) : '' ?>
<?php elseif ($sa_vieni): ?>
            Blakus pēc apgrozījuma: <?= $sa['tuvaku_veids'] === 'nozare-valsts' ? ($sa_nozare !== '' ? h($sa_nozare) : 'tā pati nozare') : 'bez norādītas nozares' ?> — citās teritorijās
<?php elseif ($sa['tuvaku_veids'] === 'nozare-teritorija'): ?>
            <?= $sa_nozare !== '' ? h($sa_nozare) : 'Tā pati nozare' ?><?= $sa_ter !== '' ? ' — ' . h($sa_ter) : '' ?>
<?php else: ?>
            Lielākie uzņēmumi<?= $sa_ter !== '' ? ' — ' . h($sa_ter) : '' ?>
<?php endif; ?>
        </h3>
        <ul>
<?php foreach ($sa['tuvakie'] as $sa_i => $sa_u): ?>
<?php if ($sa_ar_sevi && $sa_i === $sa['pats']['aiz']): ?>
            <?= $sa_pats_li ?>

<?php endif; ?>
<?php if ($sa_vieni): ?>
            <li><span><a href="/<?= h($sa_u['regcode']) ?>"><?= h($sa_u['name']) ?></a><?= $sa_ter_txt($sa_u) ?></span>
                <span class="sa-apgroz"><?= tpl_num($sa_u['turnover'], '€') ?></span></li>
<?php else: ?>
            <li><a href="/<?= h($sa_u['regcode']) ?>"><?= h($sa_u['name']) ?></a>
                <span class="sa-apgroz"><?= tpl_num($sa_u['turnover'], '€') ?></span></li>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($sa_ar_sevi && $sa['pats']['aiz'] >= count($sa['tuvakie'])): ?>
            <?= $sa_pats_li ?>

<?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($sa['valsti']): ?>
    <div class="sa-grupa">
        <h3><?= ($sa['valsti_pilns'] ?? true) ? 'Lielākie' : 'Citi lielākie' ?> šajā nozarē Latvijā</h3>
        <ul>
<?php foreach ($sa['valsti'] as $sa_u): ?>
            <li><a href="/<?= h($sa_u['regcode']) ?>"><?= h($sa_u['name']) ?></a>
                <span class="sa-apgroz"><?= tpl_num($sa_u['turnover'], '€') ?></span></li>
<?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($sa['bez_parskata'])): ?>
    <div class="sa-grupa">
        <h3>Tuvumā, bez publicēta gada pārskata</h3>
        <ul>
<?php foreach ($sa['bez_parskata'] as $sa_u): ?>
            <li><a href="/<?= h($sa_u['regcode']) ?>"><?= h($sa_u['name']) ?></a>
                <span class="sa-apgroz">pārskata nav</span></li>
<?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($sa['nace'] !== null || $sa['teritorija'] !== null): ?>
    <p class="sa-celi">
<?php if ($sa['nace'] !== null): ?>
        <a href="<?= h($sa['nace']['url']) ?>">Visa nozare<?= $sa_nozare !== '' ? ': ' . h($sa_nozare) : '' ?></a>
<?php endif; ?>
<?php if ($sa['nace'] !== null && $sa['teritorija'] !== null): ?> · <?php endif; ?>
<?php if ($sa['teritorija'] !== null): ?>
        <a href="<?= h($sa['teritorija']['url']) ?>"><?= h($sa['teritorija']['nosaukums']) ?> — lielākie uzņēmumi</a>
<?php endif; ?>
    </p>
<?php endif; ?>
</div>
<?php endif; ?>
