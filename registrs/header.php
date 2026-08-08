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
        <a href="index.php" class="logo" id="site-logo">Saraksts.lv</a>
    </div>

    <button class="menu-toggle" id="menu-toggle" aria-label="Atvērt izvēlni">
        &#9776;
    </button>

    <nav class="main-nav" id="main-nav">
        <ul>
            <?php foreach ($nav_items as [$href, $label, $icon]):
                $is_active = ($current_page === $href) || ($href === 'index.php' && $current_page === '');
            ?>
            <li><a href="<?= $href ?>" class="<?= $is_active ? 'active' : '' ?>"><i class="fas <?= $icon ?>" aria-hidden="true"></i><?= $label ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
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