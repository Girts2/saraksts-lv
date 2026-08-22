<?php // ?v= = satura hash (lib/assets.php): izvietošana bez satura maiņas nemaina URL,
      // citādi GSC pārskats pildījās ar viena faila daudziem ?v= variantiem.
      require_once $_SERVER['DOCUMENT_ROOT'] . '/registrs/lib/assets.php'; ?>
<link rel="stylesheet" href="<?php echo reg_asset_v('/registrs/cookie/cookieconsent.css'); ?>">

<script src="<?php echo reg_asset_v('/registrs/cookie/cookieconsent.umd.js'); ?>"></script>



<script type="module">
    var script = document.createElement('script');
    script.src = '<?php echo reg_asset_v('/registrs/cookie/cookieconsent-init.js'); ?>';
    document.body.appendChild(script);
</script>
