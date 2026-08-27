<?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/footer/footer.php'; ?>

    <p class="load-time">Lapas ģenerēšanas datums: <?php echo htmlspecialchars($page_data['generation_date'] ?? ''); ?></p>

    <script>
        const config = <?php echo $js_config_json ?? '{}'; ?>;
    </script>
    
    <?php // Import map: ES moduļu importi (arī moduļu IEKŠIENĒ) dabū ?v=filemtime —
          // bez tā apmeklētāju kešs moduļus turēja līdz 7 dienām (skat. lib/assets.php).
          require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/assets.php'; ?>
    <script type="importmap"><?php echo reg_js_importmap(); ?></script>
    <script type="module" src="<?php echo reg_asset_v('/registrs/assets/js/main.js'); ?>"></script>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/registrs/cookie/cookie.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
         const sankeyChartArea = document.getElementById('d3_sankey_chart_area');
        if (!sankeyChartArea) return;
        const observer = new MutationObserver(() => {
            if (typeof d3 === 'undefined') return;
            if (!config) return;
            const svg = d3.select(sankeyChartArea).select("svg");
            if (svg.empty()) return;
            svg.selectAll(".d3-sankey-node").style('display', function(d) { return (d.value === 0) ? 'none' : null; });
            svg.selectAll(".d3-sankey-link").style('display', function(d) { return (d.value === 0) ? 'none' : null; });
        });
        observer.observe(sankeyChartArea, { childList: true, subtree: true });
    });
    </script>
    <?php /* Aizver master_top.php atvērto <div class="container">. Tas ir NESOŠS:
             .container (max-width 1320, centrēšana) apzināti ietver visu lapas
             saturu līdz pat kājenei — parseris līdz šim to aizvēra implicīti pie
             </body> (audits 2026-08-26: +1 div katrā lapā). Aizveram tieši tur pat,
             tāpēc izkārtojums nemainās; NEaizvērt agrāk (piem., aiz meklēšanas
             formas) — tas atņemtu platuma ierobežojumu visiem paneļiem. */ ?>
    </div>
</body>
</html>
