<link rel="stylesheet" href="/registrs/cookie/cookieconsent.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/registrs/cookie/cookieconsent.css'); ?>">

<script src="/registrs/cookie/cookieconsent.umd.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/registrs/cookie/cookieconsent.umd.js'); ?>"></script>



<script type="module">
    var script = document.createElement('script');
    // Nomainīts no JS timestamp uz PHP filemtime (servera faila laiks)
    script.src = '/registrs/cookie/cookieconsent-init.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/registrs/cookie/cookieconsent-init.js'); ?>';
    document.body.appendChild(script);
</script>