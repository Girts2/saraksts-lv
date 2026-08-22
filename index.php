<?php 
require_once __DIR__ . '/lib/applog.php';
applog_boot('sakums');   // kopīgais notikumu žurnāls (lib/applog.php)
// Definējam mainīgos priekš head.php
$pageTitle = "Saraksts.lv — Latvijas uzņēmumu dati: rekvizīti, finanses, nozares, iepirkumi";
$pageDesc = "Bezmaksas dati par visiem Latvijā reģistrētajiem uzņēmumiem: rekvizīti, peļņa, apgrozījums, vidējā alga, amatpersonas. Nozaru analītika, uzņēmumu karte, ES un nacionālie iepirkumu konkursi.";

// Strukturētie dati Google: kas ir šī vietne un tās galvenās sadaļas.
// Sitelinks Google izvēlas pats, bet WebSite/Organization + ItemList tam
// pasaka, ka vietnes "seja" ir sadaļas, nevis atsevišķu uzņēmumu lapas.
$pageJsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebSite',
            '@id'   => 'https://saraksts.lv/#website',
            'url'   => 'https://saraksts.lv/',
            'name'  => 'Saraksts.lv',
            'description' => 'Latvijas uzņēmumu reģistra dati, finanšu rādītāji, nozaru analītika un publisko iepirkumu konkursi.',
            'inLanguage' => 'lv',
        ],
        [
            '@type' => 'ItemList',
            'name'  => 'Vietnes sadaļas',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Reģistrs — uzņēmumu meklēšana',      'url' => 'https://saraksts.lv/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Nozare — nozaru analītika',           'url' => 'https://saraksts.lv/nozare.php'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Struktūra — uzņēmumu karte',          'url' => 'https://saraksts.lv/struktura.php'],
                ['@type' => 'ListItem', 'position' => 4, 'name' => 'Konkursi — publiskie iepirkumi',      'url' => 'https://saraksts.lv/konkursi.php'],
                ['@type' => 'ListItem', 'position' => 5, 'name' => 'Iespēja — biznesa vietu karte',       'url' => 'https://saraksts.lv/iespeja.php'],
                ['@type' => 'ListItem', 'position' => 6, 'name' => 'Pensionārs — ilggadēji uzņēmumi',     'url' => 'https://saraksts.lv/pensionars.php'],
                ['@type' => 'ListItem', 'position' => 7, 'name' => 'Horoskops — astroloģijas matrica',    'url' => 'https://saraksts.lv/horoskops.php'],
                ['@type' => 'ListItem', 'position' => 8, 'name' => 'Lejupielāde — atvērtais pirmkods',    'url' => 'https://saraksts.lv/lejupielade.php'],
            ],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="lv">

<?php include 'registrs/head/head.php'; ?>

<body>
    <?php include 'registrs/header.php'; ?>

    
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">
            Atrodi informāciju par <span class="highlight">Latvijas uzņēmumiem</span>
        </h1>
        <p class="hero-subtitle">
            Ātra un ērta piekļuve aktuālajiem datiem par jebkuru Latvijā reģistrētu uzņēmumu.
        </p>
    </div>
</div>

    <div class="container">
        

<form action="#" method="post" id="searchForm" onsubmit="return false;">
    <div class="form-input-group">
        
        <i class="fas fa-search search-icon"></i>
        
        <input type="text" id="searchInput" name="search_term"
               maxlength="60"
               value=""
               placeholder="Meklēt pēc nosaukuma vai reģistrācijas numura..."
               autocomplete="off">

        <div id="resultsDropdown" class="autocomplete-suggestions"></div>
    </div>
</form>
        
        <p class='search-prompt'>Lūdzu, ievadiet reģistrācijas numuru vai uzņēmuma nosaukumu meklēšanas formā.</p>
    </div>

<?php
// --- SADAĻU IEPAZĪŠANĀS "VITRĪNA" (index lapas bloks) ---
// Katra: URL, priekšskatījuma attēls (nodaļas ekrānuzņēmums), FA ikona, nosaukums, akcents, birka, apraksts.
$idxSections = [
    ['url'=>'#searchInput',  'img'=>'registrs',  'icon'=>'fa-magnifying-glass', 'name'=>'Reģistrs',   'a'=>'#6366f1','tag'=>'Ātrā uzziņa',         'desc'=>'Ir uzņēmums, ko gribi pārbaudīt? Ieraksti nosaukumu un redzi tā finanses, nozari un vēsturi dažās sekundēs.'],
    ['url'=>'nozare.php',     'img'=>'nozare',    'icon'=>'fa-chart-pie',        'name'=>'Nozare',     'a'=>'#14b8a6','tag'=>'Nozaru pārskats',     'desc'=>'Kura nozare aug un kura sarūk? Apskati Latvijas industrijas, to algas, peļņu un finanšu veselību vienuviet.'],
    ['url'=>'struktura.php',  'img'=>'struktura', 'icon'=>'fa-table-cells-large','name'=>'Struktūra',  'a'=>'#8b5cf6','tag'=>'Interaktīva karte',   'desc'=>'Visa Latvijas ekonomika kā uz delnas. Tuvini līdz atsevišķam uzņēmumam un salīdzini pēc 15 rādītājiem.'],
    ['url'=>'konkursi.php',   'img'=>'konkursi',  'icon'=>'fa-gavel',            'name'=>'Konkursi',   'a'=>'#f97316','tag'=>'ES + Baltija',        'desc'=>'Meklē pasūtījumus? Šeit ir aktuālie iepirkumi no visas ES un Baltijas — filtrē pēc jomas vai darbu veida.'],
    ['url'=>'iespeja.php',    'img'=>'iespeja',   'icon'=>'fa-map-location-dot', 'name'=>'Iespēja',    'a'=>'#f43f5e','tag'=>'Vietas karte',       'desc'=>'Domā, kur atvērt biznesu? Karte parāda, kur tavā tuvumā mīt visvairāk potenciālo klientu.'],
    ['url'=>'pensionars.php', 'img'=>'pensionars','icon'=>'fa-hourglass-half',   'name'=>'Pensionārs', 'a'=>'#3b82f6','tag'=>'Ilgtermiņa portfelis','desc'=>'Meklē uzņēmumu, ko pārņemt? Šeit ir stabili, ilggadēji spēlētāji — gatavi pēctecībai vai pārdošanai.'],
    ['url'=>'horoskops.php',  'img'=>'horoskops', 'icon'=>'fa-star',             'name'=>'Horoskops',  'a'=>'#d946ef','tag'=>'6 sistēmas',         'desc'=>'Mazliet atelpai no cipariem: viena dzimšanas karte, sešas astroloģijas sistēmas, viens spriedums par tevi.'],
];

// SVG "interpretācija" katrai sadaļai (nevis ekrānuzņēmums). currentColor = kartes akcents;
// treemapam apzināti zaļš/sarkans kā īstajā kartē. Zīmēts viewBox 320×150, aizpilda paneli.
function idx_viz($slug) {
    switch ($slug) {
    case 'registrs': return <<<SVG
<svg viewBox="0 0 320 150" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs><linearGradient id="rg{$slug}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="currentColor" stop-opacity=".07"/></linearGradient></defs>
  <rect x="40" y="10" width="240" height="28" rx="14" fill="#fff" stroke="currentColor" stroke-opacity=".28"/>
  <circle cx="58" cy="24" r="7" fill="none" stroke="currentColor" stroke-width="3"/><line x1="63" y1="29" x2="70" y2="36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
  <rect x="80" y="21" width="118" height="6" rx="3" fill="currentColor" fill-opacity=".28"/>
  <rect x="246" y="17" width="26" height="14" rx="7" fill="currentColor"/>
  <rect x="40" y="46" width="240" height="96" rx="12" fill="url(#rg{$slug})" stroke="currentColor" stroke-opacity=".22"/>
  <rect x="52" y="58" width="38" height="38" rx="10" fill="currentColor"/>
  <path d="M61 88 v-16 h7 v-6 h6 v22 z" fill="#fff" fill-opacity=".9"/>
  <rect x="100" y="60" width="94" height="11" rx="5.5" fill="currentColor"/>
  <rect x="100" y="77" width="58" height="6" rx="3" fill="currentColor" fill-opacity=".3"/>
  <rect x="212" y="58" width="54" height="16" rx="8" fill="#2f9e44"/><circle cx="223" cy="66" r="3" fill="#fff"/><rect x="230" y="63" width="28" height="6" rx="3" fill="#fff" fill-opacity=".9"/>
  <g fill="currentColor">
    <rect x="52" y="106" width="32" height="6" rx="3" fill-opacity=".3"/><rect x="118" y="106" width="72" height="6" rx="3" fill-opacity=".62"/>
    <rect x="52" y="120" width="40" height="6" rx="3" fill-opacity=".3"/><rect x="118" y="120" width="52" height="6" rx="3" fill-opacity=".62"/>
  </g>
  <g fill="currentColor" fill-opacity=".55">
    <rect x="212" y="120" width="8" height="12" rx="2"/><rect x="224" y="112" width="8" height="20" rx="2"/><rect x="236" y="104" width="8" height="28" rx="2"/><rect x="248" y="114" width="8" height="18" rx="2"/><rect x="260" y="100" width="8" height="32" rx="2"/>
  </g>
</svg>
SVG;
    case 'nozare': return <<<SVG
<svg viewBox="0 0 320 150" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs><linearGradient id="ng{$slug}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor"/><stop offset="1" stop-color="currentColor" stop-opacity=".5"/></linearGradient></defs>
  <g transform="translate(76,74) rotate(-90)" fill="none" stroke-width="20">
    <circle r="36" stroke="currentColor" stroke-opacity=".12"/>
    <circle r="36" stroke="currentColor" stroke-dasharray="104 122"/>
    <circle r="36" stroke="currentColor" stroke-opacity=".55" stroke-dasharray="58 168" stroke-dashoffset="-104"/>
    <circle r="36" stroke="currentColor" stroke-opacity=".3" stroke-dasharray="48 178" stroke-dashoffset="-162"/>
  </g>
  <circle cx="76" cy="74" r="18" fill="#fff"/><rect x="67" y="71" width="18" height="6" rx="3" fill="currentColor"/>
  <g>
    <circle cx="128" cy="40" r="4" fill="currentColor"/><rect x="138" y="37" width="42" height="6" rx="3" fill="currentColor" fill-opacity=".3"/>
    <circle cx="128" cy="58" r="4" fill="currentColor" fill-opacity=".55"/><rect x="138" y="55" width="30" height="6" rx="3" fill="currentColor" fill-opacity=".26"/>
    <circle cx="128" cy="76" r="4" fill="currentColor" fill-opacity=".3"/><rect x="138" y="73" width="36" height="6" rx="3" fill="currentColor" fill-opacity=".22"/>
  </g>
  <g stroke="currentColor" stroke-opacity=".12" stroke-width="1"><line x1="196" y1="96" x2="304" y2="96"/><line x1="196" y1="74" x2="304" y2="74"/></g>
  <line x1="196" y1="120" x2="304" y2="120" stroke="currentColor" stroke-opacity=".22" stroke-width="2"/>
  <path d="M202 72 L228 60 L250 52 L276 42" fill="none" stroke="currentColor" stroke-opacity=".45" stroke-width="2" stroke-dasharray="3 3"/>
  <g>
    <rect x="200" y="92" width="16" height="28" rx="2.5" fill="currentColor" fill-opacity=".35"/>
    <rect x="224" y="74" width="16" height="46" rx="2.5" fill="currentColor" fill-opacity=".55"/>
    <rect x="248" y="54" width="16" height="66" rx="2.5" fill="url(#ng{$slug})"/>
    <rect x="272" y="82" width="16" height="38" rx="2.5" fill="currentColor" fill-opacity=".7"/>
  </g>
</svg>
SVG;
    case 'struktura': return <<<SVG
<svg viewBox="0 0 320 150" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs><linearGradient id="sh{$slug}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".26"/><stop offset=".45" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".13"/></linearGradient></defs>
  <g stroke="#fff" stroke-width="2.4">
    <rect x="0" y="0" width="68" height="86" fill="#2f9e44"/><rect x="68" y="0" width="50" height="50" fill="#40b85a"/><rect x="68" y="50" width="50" height="36" fill="#e03131"/>
    <rect x="0" y="86" width="70" height="64" fill="#37a84e"/><rect x="70" y="86" width="48" height="34" fill="#2f9e44"/><rect x="70" y="120" width="48" height="30" fill="#c92a2a"/>
    <rect x="118" y="0" width="72" height="56" fill="#e03131"/><rect x="118" y="56" width="72" height="50" fill="#2f9e44"/><rect x="118" y="106" width="72" height="44" fill="#40b85a"/>
    <rect x="190" y="0" width="62" height="44" fill="#2f9e44"/><rect x="190" y="44" width="62" height="42" fill="#c92a2a"/><rect x="190" y="86" width="62" height="34" fill="#37a84e"/><rect x="190" y="120" width="62" height="30" fill="#2f9e44"/>
    <rect x="252" y="0" width="68" height="52" fill="#2f9e44"/><rect x="252" y="52" width="68" height="44" fill="#e03131"/><rect x="252" y="96" width="36" height="54" fill="#37a84e"/><rect x="288" y="96" width="32" height="28" fill="#2f9e44"/><rect x="288" y="124" width="32" height="26" fill="#f03e3e"/>
  </g>
  <rect x="0" y="0" width="320" height="150" fill="url(#sh{$slug})"/>
  <g fill="#fff" fill-opacity=".82"><rect x="6" y="8" width="42" height="5" rx="2"/><rect x="6" y="17" width="28" height="4" rx="2"/></g>
  <g fill="#fff" fill-opacity=".7"><rect x="6" y="94" width="36" height="4" rx="2"/><rect x="124" y="8" width="34" height="4" rx="2"/></g>
</svg>
SVG;
    case 'konkursi': return <<<SVG
<svg viewBox="0 0 320 150" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <rect x="40" y="8" width="182" height="22" rx="11" fill="#fff" stroke="currentColor" stroke-opacity=".22"/>
  <circle cx="54" cy="19" r="6" fill="none" stroke="currentColor" stroke-width="2.4"/><line x1="58" y1="23" x2="63" y2="28" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
  <rect x="70" y="16" width="96" height="6" rx="3" fill="currentColor" fill-opacity=".24"/>
  <rect x="228" y="8" width="24" height="22" rx="6" fill="currentColor"/><rect x="258" y="8" width="24" height="22" rx="6" fill="currentColor" fill-opacity=".32"/>
  <g>
    <rect x="40" y="38" width="240" height="30" rx="8" fill="#fff" stroke="currentColor" stroke-opacity=".16"/>
    <g><rect x="49" y="45" width="6" height="16" fill="#9d2235"/><rect x="55" y="45" width="6" height="16" fill="#fff" stroke="currentColor" stroke-opacity=".15" stroke-width=".5"/><rect x="61" y="45" width="6" height="16" fill="#9d2235"/></g>
    <rect x="76" y="45" width="108" height="6" rx="3" fill="currentColor" fill-opacity=".55"/><rect x="76" y="55" width="66" height="5" rx="2.5" fill="currentColor" fill-opacity=".28"/>
    <rect x="222" y="46" width="50" height="15" rx="7.5" fill="currentColor"/><circle cx="232" cy="53.5" r="4" fill="none" stroke="#fff" stroke-width="1.4"/><line x1="232" y1="51" x2="232" y2="53.5" stroke="#fff" stroke-width="1.4"/>
    <rect x="40" y="72" width="240" height="30" rx="8" fill="#fff" stroke="currentColor" stroke-opacity=".16"/>
    <g><rect x="49" y="79" width="6" height="16" fill="#fdb913"/><rect x="55" y="79" width="6" height="16" fill="#006a44"/><rect x="61" y="79" width="6" height="16" fill="#c1272d"/></g>
    <rect x="76" y="79" width="128" height="6" rx="3" fill="currentColor" fill-opacity=".55"/><rect x="76" y="89" width="80" height="5" rx="2.5" fill="currentColor" fill-opacity=".28"/>
    <rect x="230" y="80" width="42" height="15" rx="7.5" fill="#2f9e44"/><circle cx="240" cy="87.5" r="4" fill="none" stroke="#fff" stroke-width="1.4"/><line x1="240" y1="85" x2="240" y2="87.5" stroke="#fff" stroke-width="1.4"/>
    <rect x="40" y="106" width="240" height="30" rx="8" fill="#fff" stroke="currentColor" stroke-opacity=".16"/>
    <g><rect x="49" y="113" width="6" height="16" fill="#0072ce"/><rect x="55" y="113" width="6" height="16" fill="#111"/><rect x="61" y="113" width="6" height="16" fill="#fff" stroke="currentColor" stroke-opacity=".15" stroke-width=".5"/></g>
    <rect x="76" y="113" width="90" height="6" rx="3" fill="currentColor" fill-opacity=".55"/><rect x="76" y="123" width="58" height="5" rx="2.5" fill="currentColor" fill-opacity=".28"/>
    <rect x="216" y="114" width="56" height="15" rx="7.5" fill="currentColor" fill-opacity=".85"/><circle cx="226" cy="121.5" r="4" fill="none" stroke="#fff" stroke-width="1.4"/><line x1="226" y1="119" x2="226" y2="121.5" stroke="#fff" stroke-width="1.4"/>
  </g>
</svg>
SVG;
    case 'iespeja': return <<<SVG
<svg viewBox="0 0 320 150" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <g fill="#8bd17c" fill-opacity=".32"><ellipse cx="38" cy="34" rx="36" ry="24"/><ellipse cx="286" cy="126" rx="32" ry="22"/></g>
  <path d="M118 -8 C108 42 150 74 128 156" fill="none" stroke="#7bb8e8" stroke-opacity=".5" stroke-width="15" stroke-linecap="round"/>
  <g stroke="currentColor" stroke-opacity=".16" stroke-width="5" stroke-linecap="round">
    <line x1="0" y1="46" x2="320" y2="66"/><line x1="0" y1="106" x2="320" y2="92"/><line x1="202" y1="0" x2="226" y2="150"/><line x1="70" y1="0" x2="58" y2="150"/>
  </g>
  <g stroke="currentColor" stroke-opacity=".09" stroke-width="2"><line x1="0" y1="78" x2="320" y2="78"/><line x1="252" y1="0" x2="262" y2="150"/></g>
  <g fill="currentColor" fill-opacity=".12"><rect x="210" y="30" width="16" height="14" rx="2"/><rect x="232" y="24" width="14" height="20" rx="2"/><rect x="212" y="102" width="18" height="16" rx="2"/><rect x="240" y="106" width="14" height="12" rx="2"/><rect x="28" y="98" width="16" height="14" rx="2"/></g>
  <circle cx="168" cy="74" r="52" fill="none" stroke="currentColor" stroke-opacity=".25" stroke-dasharray="4 5"/>
  <circle cx="168" cy="74" r="33" fill="currentColor" fill-opacity=".08" stroke="currentColor" stroke-opacity=".35"/>
  <g><circle cx="132" cy="52" r="5" fill="currentColor"/><circle cx="132" cy="52" r="2" fill="#fff"/></g>
  <circle cx="200" cy="58" r="5" fill="currentColor" fill-opacity=".65"/><circle cx="146" cy="102" r="5" fill="currentColor" fill-opacity=".65"/><circle cx="196" cy="98" r="5" fill="currentColor" fill-opacity=".85"/>
  <ellipse cx="168" cy="108" rx="10" ry="3" fill="currentColor" fill-opacity=".22"/>
  <path d="M168 40 C154 40 144 51 144 65 C144 84 168 106 168 106 C168 106 192 84 192 65 C192 51 182 40 168 40 Z" fill="currentColor"/>
  <path d="M168 40 C158 40 150 48 148 58 C152 46 168 48 168 48 Z" fill="#fff" fill-opacity=".25"/>
  <circle cx="168" cy="64" r="9" fill="#fff"/><circle cx="168" cy="64" r="4.5" fill="currentColor"/>
</svg>
SVG;
    case 'pensionars': return <<<SVG
<svg viewBox="0 0 320 150" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs><linearGradient id="pg{$slug}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".28"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
  <g stroke="currentColor" stroke-opacity=".1" stroke-width="1"><line x1="40" y1="44" x2="300" y2="44"/><line x1="40" y1="72" x2="300" y2="72"/><line x1="40" y1="100" x2="300" y2="100"/></g>
  <line x1="40" y1="120" x2="300" y2="120" stroke="currentColor" stroke-opacity=".25" stroke-width="2"/>
  <g fill="currentColor" fill-opacity=".14"><rect x="52" y="96" width="14" height="24" rx="2"/><rect x="98" y="88" width="14" height="32" rx="2"/><rect x="144" y="76" width="14" height="44" rx="2"/><rect x="190" y="62" width="14" height="58" rx="2"/><rect x="236" y="48" width="14" height="72" rx="2"/><rect x="278" y="40" width="14" height="80" rx="2"/></g>
  <path d="M59 100 L105 92 L151 80 L197 66 L243 52 L285 44 L285 120 L59 120 Z" fill="url(#pg{$slug})"/>
  <polyline points="59,100 105,92 151,80 197,66 243,52 285,44" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
  <g fill="#fff" stroke="currentColor" stroke-width="3"><circle cx="105" cy="92" r="4"/><circle cx="197" cy="66" r="4"/></g>
  <circle cx="285" cy="44" r="8" fill="currentColor" fill-opacity=".2"/><circle cx="285" cy="44" r="4" fill="currentColor"/>
  <path d="M292 36 l8 -6 l-1 5 l4 -1 l-9 8 l1 -5 z" fill="currentColor" fill-opacity=".5"/>
  <g fill="currentColor" fill-opacity=".4"><rect x="51" y="126" width="16" height="5" rx="2"/><rect x="97" y="126" width="16" height="5" rx="2"/><rect x="143" y="126" width="16" height="5" rx="2"/><rect x="189" y="126" width="16" height="5" rx="2"/><rect x="235" y="126" width="16" height="5" rx="2"/></g>
</svg>
SVG;
    case 'horoskops': return <<<SVG
<svg viewBox="0 0 320 150" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs><radialGradient id="hg{$slug}"><stop offset="0" stop-color="currentColor" stop-opacity=".33"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></radialGradient></defs>
  <g transform="translate(160,75)">
    <circle r="66" fill="url(#hg{$slug})"/>
    <circle r="58" fill="none" stroke="currentColor" stroke-opacity=".3" stroke-width="1.5"/>
    <circle r="46" fill="none" stroke="currentColor" stroke-opacity=".18" stroke-width="1"/>
    <circle r="30" fill="none" stroke="currentColor" stroke-opacity=".4" stroke-width="1.5"/>
    <g stroke="currentColor" stroke-opacity=".26" stroke-width="1.4">
      <line x1="30" y1="0" x2="58" y2="0"/><line x1="25.98" y1="15" x2="50.23" y2="29"/><line x1="15" y1="25.98" x2="29" y2="50.23"/><line x1="0" y1="30" x2="0" y2="58"/><line x1="-15" y1="25.98" x2="-29" y2="50.23"/><line x1="-25.98" y1="15" x2="-50.23" y2="29"/>
      <line x1="-30" y1="0" x2="-58" y2="0"/><line x1="-25.98" y1="-15" x2="-50.23" y2="-29"/><line x1="-15" y1="-25.98" x2="-29" y2="-50.23"/><line x1="0" y1="-30" x2="0" y2="-58"/><line x1="15" y1="-25.98" x2="29" y2="-50.23"/><line x1="25.98" y1="-15" x2="50.23" y2="-29"/>
    </g>
    <g stroke="currentColor" stroke-opacity=".5" stroke-width="2">
      <line x1="55" y1="0" x2="61" y2="0"/><line x1="0" y1="-55" x2="0" y2="-61"/><line x1="-55" y1="0" x2="-61" y2="0"/><line x1="0" y1="55" x2="0" y2="61"/><line x1="47.6" y1="-27.5" x2="52.8" y2="-30.5"/><line x1="-47.6" y1="27.5" x2="-52.8" y2="30.5"/>
    </g>
    <g fill="currentColor"><circle cx="42.4" cy="18" r="3.5"/><circle cx="-35" cy="30" r="3"/><circle cx="-12" cy="-44" r="4"/></g>
    <polyline points="42.4,18 -12,-44 -35,30" fill="none" stroke="currentColor" stroke-opacity=".35" stroke-width="1"/>
    <circle r="8" fill="currentColor"/><circle r="3.5" fill="#fff"/>
    <g fill="currentColor">
      <path transform="translate(-88,-32) scale(.8)" d="M0,-7 C1,-2 2,-1 7,0 C2,1 1,2 0,7 C-1,2 -2,1 -7,0 C-2,-1 -1,-2 0,-7 Z"/>
      <path transform="translate(92,-22)" d="M0,-7 C1,-2 2,-1 7,0 C2,1 1,2 0,7 C-1,2 -2,1 -7,0 C-2,-1 -1,-2 0,-7 Z"/>
      <path transform="translate(76,40) scale(.72)" d="M0,-7 C1,-2 2,-1 7,0 C2,1 1,2 0,7 C-1,2 -2,1 -7,0 C-2,-1 -1,-2 0,-7 Z"/>
    </g>
  </g>
</svg>
SVG;
    default: return '';
    }
}
?>

<style>
    /* Vienāds fons visai lapai — sekcija bez sava fona (manto lapas --color-background). */
    .idx-explore { padding: 4rem 15px 4.5rem; }
    .idx-explore__inner { max-width: 1240px; margin: 0 auto; }
    .idx-explore__head { text-align: center; max-width: 690px; margin: 0 auto 2.75rem; }
    .idx-eyebrow {
        display: inline-flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 700;
        letter-spacing: .06em; text-transform: uppercase; color: var(--color-primary, #4f46e5);
        background: #eef2ff; border: 1px solid #e2e0f7; padding: .42rem .95rem; border-radius: 999px; margin-bottom: 1.2rem;
    }
    .idx-explore__title {
        font-size: 2.2rem; font-weight: 800; letter-spacing: -.03em; line-height: 1.16;
        color: var(--color-heading, #111827); margin: 0 0 .9rem;
    }
    .idx-explore__sub { font-size: 1.1rem; color: var(--color-text-secondary, #6b7280); margin: 0; line-height: 1.65; }

    .idx-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.4rem; }

    /* --- KARTES: augšā koda zīmēta katras nodaļas "interpretācija" (SVG), zem tās saturs --- */
    .idx-card {
        position: relative; overflow: hidden; display: flex; flex-direction: column;
        background: var(--color-surface, #fff); border: 1px solid #ececf2; border-radius: 20px;
        text-decoration: none; color: inherit;
        box-shadow: 0 1px 2px rgba(17,24,39,.05);
        transition: transform .26s cubic-bezier(.2,.7,.3,1), box-shadow .26s ease, border-color .26s ease;
        animation: idxIn .55s ease both;
    }
    .idx-card:hover, .idx-card:focus-visible {
        transform: translateY(-7px);
        border-color: color-mix(in srgb, var(--accent, #6366f1) 40%, transparent);
        box-shadow: 0 24px 44px -22px color-mix(in srgb, var(--accent, #6366f1) 55%, transparent),
                    0 8px 18px -12px rgba(17,24,39,.12);
        outline: none;
    }
    /* Ilustrācijas panelis — akcenta toņa fons, SVG motīvs currentColor krāsā */
    .idx-card__viz {
        position: relative; height: 150px; overflow: hidden; color: var(--accent, #6366f1);
        background: radial-gradient(135% 130% at 28% 0%,
            color-mix(in srgb, var(--accent, #6366f1) 22%, #fff) 0%,
            color-mix(in srgb, var(--accent, #6366f1) 7%, #fff) 68%);
        border-bottom: 1px solid color-mix(in srgb, var(--accent, #6366f1) 12%, #ececf2);
    }
    .idx-card__viz svg { position: absolute; inset: 0; width: 100%; height: 100%; display: block;
        transition: transform .5s cubic-bezier(.2,.7,.3,1); transform-origin: center; }
    .idx-card:hover .idx-card__viz svg, .idx-card:focus-visible .idx-card__viz svg { transform: scale(1.04); }

    /* Ikona — peld uz paneļa un satura robežas */
    .idx-card__icon {
        position: absolute; top: 122px; left: 20px; z-index: 5;
        width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center;
        justify-content: center; font-size: 1.25rem; color: #fff;
        background: var(--accent, #6366f1);
        background: linear-gradient(135deg, var(--accent, #6366f1), color-mix(in srgb, var(--accent, #6366f1) 58%, #fff));
        box-shadow: 0 8px 18px -6px color-mix(in srgb, var(--accent, #6366f1) 60%, transparent);
        transition: transform .26s ease;
    }
    .idx-card:hover .idx-card__icon, .idx-card:focus-visible .idx-card__icon { transform: scale(1.06) rotate(-6deg); }

    .idx-card__body { display: flex; flex-direction: column; flex: 1; padding: 32px 20px 20px; }
    .idx-card__name { font-size: 1.24rem; font-weight: 700; color: var(--color-heading, #111827); margin: 0 0 .5rem; }
    .idx-card__desc { font-size: .93rem; line-height: 1.58; color: var(--color-text-secondary, #6b7280); margin: 0 0 1.2rem; }
    .idx-card__foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
    .idx-card__tag {
        font-size: .72rem; font-weight: 700; letter-spacing: .01em; color: var(--accent, #6366f1); white-space: nowrap;
        background: color-mix(in srgb, var(--accent, #6366f1) 12%, #fff);
        padding: .34rem .72rem; border-radius: 999px;
    }
    .idx-card__go { display: inline-flex; align-items: center; gap: .4rem; font-size: .86rem; font-weight: 700; color: var(--accent, #6366f1); }
    .idx-card__go i { transition: transform .26s ease; }
    .idx-card:hover .idx-card__go i, .idx-card:focus-visible .idx-card__go i { transform: translateX(5px); }

    @keyframes idxIn { from { opacity: 0; } to { opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .idx-card, .idx-card * { animation: none !important; transition: none !important; } }
    @media (max-width: 600px) {
        .idx-explore { padding: 3rem 15px 3.25rem; }
        .idx-explore__title { font-size: 1.75rem; }
        .idx-explore__sub { font-size: 1rem; }
    }
</style>

<section class="idx-explore" aria-labelledby="idx-explore-title">
    <div class="idx-explore__inner">
        <div class="idx-explore__head">
            <span class="idx-eyebrow"><i class="fas fa-chart-line" aria-hidden="true"></i> Latvijas ekonomikas analītika</span>
            <h2 class="idx-explore__title" id="idx-explore-title">No atsevišķa uzņēmuma līdz ekonomikas kopainai</h2>
            <p class="idx-explore__sub">Katrs rīks paver citu analīzes mērogu un rakursu — no viena uzņēmuma finansēm līdz veselu nozaru likumsakarībām. Izvēlies to, kas atbilst tavam jautājumam.</p>
        </div>

        <div class="idx-grid">
            <?php foreach ($idxSections as $i => $s): ?>
            <a class="idx-card" href="<?= htmlspecialchars($s['url'], ENT_QUOTES) ?>"
               style="--accent: <?= htmlspecialchars($s['a'], ENT_QUOTES) ?>; animation-delay: <?= $i * 60 ?>ms;">
                <div class="idx-card__viz"><?= idx_viz($s['img']) ?></div>
                <span class="idx-card__icon"><i class="fas <?= htmlspecialchars($s['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i></span>
                <div class="idx-card__body">
                    <h3 class="idx-card__name"><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></h3>
                    <p class="idx-card__desc"><?= htmlspecialchars($s['desc'], ENT_QUOTES) ?></p>
                    <div class="idx-card__foot">
                        <span class="idx-card__tag"><?= htmlspecialchars($s['tag'], ENT_QUOTES) ?></span>
                        <span class="idx-card__go">Atvērt <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

    <?php $footerRich = 'registrs'; include 'registrs/footer/footer.php'; ?>

    <script type="module">
        // Pievienota PHP versija importam
        import { initLiveSearch } from '<?php echo reg_asset_v('/registrs/assets/js/live_search.js'); ?>';
        
        document.addEventListener('DOMContentLoaded', () => {
            initLiveSearch();
            // "Reģistrs" kartīte ved uz meklēšanas lauku augšā — fokusē to pēc ritināšanas.
            const regCard = document.querySelector('.idx-card[href="#searchInput"]');
            const searchInput = document.getElementById('searchInput');
            if (regCard && searchInput) {
                regCard.addEventListener('click', () => setTimeout(() => searchInput.focus(), 450));
            }
        });
    </script>

    <?php include 'registrs/cookie/cookie.php'; ?>

</body>
</html>