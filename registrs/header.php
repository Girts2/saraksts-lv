<?php
// Saglabājam jūsu oriģinālo PHP loģiku aktīvās lapas noteikšanai
$current_page = basename($_SERVER['PHP_SELF']);

// Navigācija ar ikonām (tās pašas, kas sākumlapas sadaļu kartītēs — vizuāla konsekvence)
$nav_items = [
    ['index.php',      'Reģistrs',    'fa-magnifying-glass'],
    ['nozare.php',     'Nozare',      'fa-chart-pie'],
    ['struktura.php',  'Struktūra',   'fa-table-cells-large'],
    ['konkursi.php',   'Konkursi',    'fa-gavel'],
    ['iespeja.php',    'Iespēja',     'fa-map-location-dot'],
    ['pensionars.php', 'Pensionārs',  'fa-hourglass-half'],
    ['horoskops.php',  'Horoskops',   'fa-star'],
    ['lejupielade.php','Lejupielāde', 'fa-download'],
];

// "Test ..." sadaļas — TIKAI lokālā testa vide. Agrāk vārti bija "failu produkcijā
// nav", bet pilnā server/ augšupielāde tos aiznes līdzi (GSC 2026-08-09 atklāja
// saites publiski) — tagad izšķir VIDE (lib/test_env.php: tikai localhost/CLI),
// un pašas testa lapas publiskā hostā atdod 404 (reg_test_gate katrā failā).
$test_nav_items = [];
require_once ($_SERVER['DOCUMENT_ROOT'] ?: dirname(__DIR__)) . '/lib/test_env.php';
if (reg_test_env()) {
    // Failu nosaukumi bez diakritikām (macOS NFD slazds) → izvēlnes etiķetes ar tām.
    // NB: skenē TIKAI docroot saknes test_*.php — detaļlapu veidnes (viena profesija,
    // vienas zāles) dzīvo test_lapas/ apakšmapē, jo bez parametra tās atgriež 404 un
    // izvēlnē būtu lauztas saites.
    $__test_labels = ['nodokli' => 'Nodokļi', 'darijumi' => 'Darījumi', 'nolemumi' => 'Nolēmumi',
                      'zales' => 'Zāles', 'biedribas' => 'Biedrības'];
    foreach (glob(($_SERVER['DOCUMENT_ROOT'] ?: dirname(__DIR__)) . '/test_*.php') ?: [] as $__tf) {
        $__slug = preg_replace('/^test_|\.php$/', '', basename($__tf));   // profesijas
        $__label = $__test_labels[$__slug] ?? ucfirst(str_replace('_', ' ', $__slug));
        $test_nav_items[] = [basename($__tf), 'Test ' . $__label];
    }
}
// Font Awesome nāk no head/head.php, bet dažas lapas (piem. horoskops.php) to neiekļauj —
// tur ikonas nerādītos, tāpēc ielādējam rezervi tikai tad, ja head.php nav bijis.
if (!defined('REG_FA_LOADED')) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"'
       . ' integrity="sha384-3B6NwesSXE7YJlcLI9RpRqGf2p/EgVH8BgoKTaUrmKNDkHPStTQ3EyoYjCGXaOTS"'
       . ' crossorigin="anonymous" referrerpolicy="no-referrer">' . "\n";
    define('REG_FA_LOADED', true);
}
?>

<?php // PIEZĪME: šeit agrāk bija otrs <!DOCTYPE html>. Katra lapa, kas iekļauj šo failu,
      // savu DOCTYPE jau ir izdevusi pirms <body>, tāpēc otrs nonāca <body> iekšpusē un
      // padarīja HTML nevalidējamu. Pārbaudīts: visas 10 lapas, kas iekļauj header.php,
      // savu DOCTYPE deklarē pašas. ?>
<style>
    /* --- GALVENES PAMATA STILS --- */
    .main-header {
        background-color: #140a3f; /* Tumši zils fons */
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        
        /* KRITISKIE LABOJUMI NOBĪDEI: */
        /* Izmantojam !important, lai 'nozare.php' stili nevarētu šo pārrakstīt */
        position: fixed !important; 
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        z-index: 99999 !important; /* Lai vienmēr būtu virs visa cita */
        
        /* Šis novērš problēmu, ja 'nozare.php' body elementam ir transformācija/animācija */
        transform: none !important; 
        
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        transition: padding 0.3s ease-in-out; /* Animējam tikai padding */
        box-sizing: border-box;
    }

    /* Logo stils */
    .logo-wrapper .logo {
        color: white;
        text-decoration: none;
        font-size: 24px;
        font-weight: bold;
        transition: font-size 0.3s ease-in-out;
        display: block; /* Drošībai */
    }

    /* --- HAMBURGERA POGA (Reset + Stils) --- */
    .menu-toggle {
        display: none; /* Uz datora paslēpts */
        
        /* NOŅEMAM JEBKĀDUS VECOS STILUS */
        background: none !important;
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
        margin: 0 !important;
        
        /* JAUNAIS STILS */
        color: white;
        font-size: 30px;
        cursor: pointer;
        padding: 0 10px;
        line-height: 1;
    }

    /* --- NAVIGĀCIJA (Dators) --- */
    .main-nav ul {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        gap: 12px;
    }

    .main-nav a {
        color: white;
        text-decoration: none;
        font-size: 15px;
        padding: 5px 8px;
        transition: color 0.3s;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        white-space: nowrap; /* nosaukums nekad nelaužas divās rindās */
    }

    /* Sadaļu ikonas — vieglākai vizuālai uztverei */
    .main-nav a i {
        font-size: 0.92em;
        opacity: 0.75;
        transition: opacity 0.3s, transform 0.3s;
    }

    .main-nav a:hover,
    .main-nav a.active {
        color: #4CAF50; /* Zaļa aktīvā krāsa */
        font-weight: bold;
    }
    .main-nav a:hover i,
    .main-nav a.active i { opacity: 1; }
    .main-nav a:hover i { transform: translateY(-1px); }

    /* Šaurākos datora logos saspiežam atstarpes, lai izvēlne ietilptu ilgāk */
    @media (max-width: 1150px) {
        .main-nav ul { gap: 6px; }
        .main-nav a { font-size: 14px; padding: 5px 6px; gap: 6px; }
    }

    /* --- MOBILAIS/PLANŠETES SKATS ---
       Slieksnis 1080px (nevis 768px): ar 8 sadaļām + ikonām izvēlne pārplūda jau ap ~1050px,
       un pēdējie punkti ("Horoskops", "Lejupielāde") pazuda aiz lapas malas. */
    @media (max-width: 1080px) {
        .menu-toggle {
            display: block; /* Parādām pogu */
        }

        .main-nav {
            display: none; /* Pēc noklusējuma slēpts */
            position: absolute;
            /* Piesaistām tieši zem header (kas var mainīties), bet sākam ar standarta */
            top: 100%; 
            left: 0;
            width: 100%;
            background-color: #140a3f;
            flex-direction: column;
            padding-bottom: 20px;
            box-shadow: 0 5px 10px rgba(0,0,0,0.3);
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        /* Šo klasi pievieno JavaScript */
        .main-nav.active {
            display: flex;
        }

        .main-nav ul {
            flex-direction: column;
            width: 100%;
            text-align: center;
            gap: 0;
        }

        .main-nav a {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 18px;
        }
        /* Mobilajā izvēlnē ikonas vienā kolonnā — vienāds platums, lai teksts sakrīt */
        .main-nav a i { width: 22px; text-align: center; font-size: 1em; opacity: .85; }
    }

    /* Lai saturs nepazustu zem fiksētā header */
    /* !important nodrošina, ka šis strādās arī nozare.php */
    body {
        padding-top: 80px !important; 
    }
</style>

<header class="main-header" id="main-header">
    <div class="logo-wrapper">
        <?php /* Saites saknes-absolūtas ("/..."): lapas dzīvo arī apakšceļos
                 (/nozare/{kods}), kur relatīvs "index.php" atrisinātos uz
                 /nozare/index.php → 404 (GSC atradums 2026-08-09). */ ?>
        <a href="/index.php" class="logo" id="site-logo">Saraksts.lv</a>
    </div>

    <button class="menu-toggle" id="menu-toggle" aria-label="Atvērt izvēlni">
        &#9776;
    </button>

    <nav class="main-nav" id="main-nav">
        <ul>
            <?php foreach ($nav_items as [$href, $label, $icon]):
                $is_active = ($current_page === $href) || ($href === 'index.php' && $current_page === '');
            ?>
            <li><a href="/<?= $href ?>" class="<?= $is_active ? 'active' : '' ?>"><i class="fas <?= $icon ?>" aria-hidden="true"></i><?= $label ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>

<?php if (!empty($test_nav_items)): ?>
    <?php // Testa sadaļu rinda — pilna platuma josla ZEM galvenās izvēlnes (tikai testa vidē). ?>
    <style>
        /* _layout.css uzspiež height:35px — testa vidē headerim jāaug līdzi otrajai rindai */
        .main-header { flex-wrap: wrap; height: auto !important; }
        .test-nav { flex-basis: 100%; border-top: 1px solid rgba(255,255,255,0.12); margin-top: 10px; padding-top: 6px; }
        .test-nav ul { margin: 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 4px 14px; justify-content: center; }
        /* Aktīvo sadaļu izceļ pildīta gaiša poga ar tumšu tekstu, nevis krāsas
           maiņa uz tumša fona — zaļais/pelēkais teksts te nebija salasāms. */
        .test-nav a { color: #ffd54f; text-decoration: none; font-size: 13.5px; padding: 3px 10px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; border-radius: 12px; }
        .test-nav a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .test-nav a.active { background: #ffd54f; color: #17173d; font-weight: 700; }
        .test-nav a i { font-size: 0.9em; opacity: 0.8; }
        .test-nav a.active i { opacity: 1; }
        body { padding-top: 116px !important; }
        @media (max-width: 1080px) { body { padding-top: 118px !important; } }
    </style>
    <nav class="test-nav" aria-label="Testa sadaļas">
        <ul>
            <?php foreach ($test_nav_items as [$href, $label]): ?>
            <li><a href="/<?= $href ?>" class="<?= $current_page === $href ? 'active' : '' ?>"><i class="fas fa-flask" aria-hidden="true"></i><?= htmlspecialchars($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menu-toggle');
    const mainNav = document.getElementById('main-nav');
    const mainHeader = document.getElementById('main-header');
    const siteLogo = document.getElementById('site-logo');

    // 1. HAMBURGERA KLIKŠĶIS
    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation(); // Novērš konfliktus ar citiem klikšķiem
            mainNav.classList.toggle('active');
        });
        
        // Aizveram, ja klikšķina ārpusē
        document.addEventListener('click', function(e) {
            if (!mainHeader.contains(e.target) && mainNav.classList.contains('active')) {
                mainNav.classList.remove('active');
            }
        });
    }

    // 2. SKROLLĒŠANAS SAMAZINĀŠANA
    window.addEventListener('scroll', function() {
        let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 50) {
            mainHeader.style.padding = '5px 20px';
            if(siteLogo) siteLogo.style.fontSize = '20px';
        } else {
            mainHeader.style.padding = '15px 20px';
            if(siteLogo) siteLogo.style.fontSize = '24px';
        }
    });
});
</script>