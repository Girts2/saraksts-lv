<?php
/**
 * registrs/footer/footer.php — vietnes kājene.
 *
 * DIVI režīmi:
 *  - minimālais (noklusējums): tikai sīkdatņu saite — uzņēmumu lapas, Iespēja,
 *    Pensionārs, Horoskops, Lejupielāde, 404 paliek kā bija;
 *  - bagātais: lapa PIRMS include iestata $footerRich = 'registrs'|'nozare'|
 *    'struktura'|'konkursi'. Tad kājenē ir sadaļu saites (iekšējais saišu tīkls),
 *    datu avoti ar atjaunošanas datumu un sadaļai specifisks datu bloks.
 *
 * SEO/MI nolūks: statisks servera renderēts HTML ar īstiem skaitļiem un aprakstošiem
 * enkuriem — meklētāji un MI asistenti no kājenes var citēt faktus un atrast sadaļas.
 * Dati nāk no footer_data.php ar dienas kešu — kājene nemaksā vaicājumus katrā ielādē.
 */
$__rich = $footerRich ?? null;
if ($__rich !== null) {
    require_once __DIR__ . '/footer_data.php';
}
?>
<?php if ($__rich !== null): ?>
<style>
.site-footer{margin-top:100px;border-top:1px solid #ddd;background:#f9f9f9;font-size:14px;color:#444}
.site-footer a{color:#3451d1;text-decoration:none}
.site-footer a:hover{text-decoration:underline}
.site-footer .sf-grid{max-width:1200px;margin:0 auto;padding:32px 20px 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:28px;text-align:left}
.site-footer h2{font-size:15px;margin:0 0 10px;color:#222}
.site-footer ul{list-style:none;margin:0;padding:0}
.site-footer li{margin:0 0 6px}
.site-footer .sf-muted{color:#777}
.site-footer .sf-inline{line-height:1.9}
.site-footer .sf-bottom{border-top:1px solid #e5e5e5;margin-top:8px;padding:14px 20px;text-align:center;color:#666}
</style>
<footer class="site-footer">
  <div class="sf-grid">

    <nav aria-label="Vietnes sadaļas">
      <h2>Sadaļas</h2>
      <ul>
        <li><a href="/">Reģistrs — uzņēmumu meklēšana un finanšu dati</a></li>
        <li><a href="/nozare.php">Nozare — Latvijas nozaru analītika</a></li>
        <li><a href="/struktura.php">Struktūra — uzņēmumu karte</a></li>
        <li><a href="/konkursi.php">Konkursi — publiskie iepirkumi</a></li>
        <li><a href="/iespeja.php">Iespēja — biznesa vietu karte</a></li>
        <li><a href="/pensionars.php">Pensionārs — ilggadēji uzņēmumi</a></li>
        <li><a href="/horoskops.php">Horoskops — astroloģijas matrica</a></li>
        <li><a href="/lejupielade.php">Lejupielāde — atvērtais pirmkods</a></li>
      </ul>
    </nav>

<?php if ($__rich === 'registrs'):
    $st = fd_registrs_stats(); $top = fd_top_uznemumi(10); ?>
    <section>
      <h2>Reģistrs skaitļos</h2>
      <ul>
        <?php if ($st): ?>
        <li><?php echo fd_num((float)$st['aktivi']); ?> uzņēmumi ar aktivitātes pazīmēm</li>
        <li><?php echo (int)$st['nozares']; ?> nozares (NACE 2.1)</li>
        <li class="sf-muted">Dati atjaunoti: <?php echo htmlspecialchars($st['atjaunots']); ?></li>
        <?php else: ?>
        <li class="sf-muted">Dati būs pieejami pēc pirmās būves.</li>
        <?php endif; ?>
      </ul>
    </section>
    <?php if ($top): ?>
    <section>
      <h2>Lielākie uzņēmumi pēc apgrozījuma</h2>
      <ul>
        <?php foreach ($top as $u): ?>
        <li><a href="/<?php echo htmlspecialchars($u['regcode']); ?>"><?php echo htmlspecialchars($u['name']); ?></a>
            <span class="sf-muted">— <?php echo fd_num((float)$u['turnover']); ?> EUR</span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

<?php elseif ($__rich === 'nozare'):
    $noz = fd_top_nozares(12); ?>
    <?php if ($noz): ?>
    <section>
      <h2>Lielākās nozares pēc uzņēmumu skaita</h2>
      <ul>
        <?php foreach ($noz as $z): ?>
        <li><a href="/nozare.php#<?php echo htmlspecialchars($z['kods']); ?>"><?php echo htmlspecialchars($z['nosaukums']); ?></a>
            <span class="sf-muted">— <?php echo fd_num((float)$z['skaits']); ?> uzņ.</span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

<?php elseif ($__rich === 'struktura'):
    $st = fd_struktura_stats(); ?>
    <?php if ($st): ?>
    <section>
      <h2>Latvijas ekonomika kartē</h2>
      <ul>
        <li><?php echo fd_num((float)$st['uznemumi']); ?> uzņēmumi kartē</li>
        <li><?php echo fd_num((float)$st['apgrozijums']); ?> EUR kopējais apgrozījums</li>
        <li><?php echo (int)$st['vietas']; ?> pilsētas un novadi</li>
        <?php if ($st['gadi']): ?><li class="sf-muted">Periods: <?php echo (int)min($st['gadi']); ?>–<?php echo (int)max($st['gadi']); ?></li><?php endif; ?>
      </ul>
    </section>
    <?php endif; ?>

<?php elseif ($__rich === 'konkursi'):
    $val = fd_konkursi_valstis(); ?>
    <?php if ($val): ?>
    <section style="grid-column: span 2; min-width: 0;">
      <h2>Aktīvie iepirkumi pa valstīm</h2>
      <p class="sf-inline">
        <?php $parts = [];
        foreach ($val as $v) {
            $parts[] = '<a href="/konkursi.php?country=' . htmlspecialchars($v['cc']) . '">'
                     . htmlspecialchars($v['nosaukums']) . '</a> <span class="sf-muted">('
                     . number_format($v['skaits'], 0, ',', ' ') . ')</span>';
        }
        echo implode(' · ', $parts); ?>
      </p>
    </section>
    <?php endif; ?>
<?php endif; ?>

    <section>
      <h2>Dati un avoti</h2>
      <ul>
        <li>Uzņēmumu reģistra un VID atvērtie dati (<a href="https://data.gov.lv" rel="noopener">data.gov.lv</a>)</li>
        <li>ES iepirkumi: <a href="https://ted.europa.eu" rel="noopener">TED</a> + nacionālie portāli 44 valstīs</li>
        <li>Kartes dati: © OpenStreetMap contributors</li>
        <li><a href="/lejupielade.php">Viss vietnes pirmkods — MIT licencē</a></li>
      </ul>
    </section>

  </div>
  <div class="sf-bottom">
    <a href="mailto:info@example.com" style="text-decoration:underline;color:#333">info@example.com</a>
    &nbsp;·&nbsp;
    <a href="/dati.php" style="text-decoration:underline;color:#333">Datu precizitāte un iebildumi</a>
    &nbsp;·&nbsp;
    <a href="#" id="open_preferences_center" style="text-decoration:underline;color:#333;cursor:pointer">Mainīt sīkdatņu iestatījumus</a>
  </div>
</footer>
<?php else: ?>
<footer style="margin-top: 100px; padding: 20px; text-align: center; border-top: 1px solid #ddd; background-color: #f9f9f9;">

    <a href="/dati.php" style="text-decoration: underline; color: #333;">
        Datu precizitāte un iebildumi
    </a>
    &nbsp;·&nbsp;
    <a href="#" id="open_preferences_center" style="text-decoration: underline; color: #333; cursor: pointer;">
        Mainīt sīkdatņu iestatījumus
    </a>
</footer>
<?php endif; ?>
