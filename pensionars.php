<?php
require_once __DIR__ . '/lib/applog.php';
applog_boot('pensionars');   // kopīgais notikumu žurnāls (lib/applog.php)
ini_set('display_errors', 0);
error_reporting(0);

$db_file = __DIR__ . '/pensionars/pensionari.sqlite';
$companies_data = [];
$error_message = null;

try {
    if (!file_exists($db_file)) {
        throw new Exception("Datubāzes fails 'pensionari.sqlite' netika atrasts.");
    }
    
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // GDPR datu minimizācija: owner_name/owner_pk (PLG vārds un maskētais personas
    // kods) NETIEK atlasīti — tie kalpo tikai builda atlases filtram aizmugursistēmā,
    // un viss šis masīvs zemāk nonāk lapas avotā kā JSON (companiesData).
    $stmt = $pdo->query("SELECT regcode, name, location, turnover, employees, profit, founded_year, nace_description, financial_health_string, balance_year, total_assets, total_non_current_assets, total_current_assets, equity, provisions, non_current_liabilities, current_liabilities, valuation_multiplier, history_turnover, history_profit, history_employees, tax_rating FROM companies");
    $companies_data = $stmt->fetchAll();

    // Get unique locations for filter
    $stmt_loc = $pdo->query("SELECT DISTINCT location FROM companies WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
    $locations = $stmt_loc->fetchAll(PDO::FETCH_COLUMN);

    // Get unique industries for filter
    $stmt_ind = $pdo->query("SELECT DISTINCT nace_description FROM companies WHERE nace_description IS NOT NULL AND nace_description != '' ORDER BY nace_description ASC");
    $industries = $stmt_ind->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $error_message = "Sistēmas kļūda: " . $e->getMessage();
}

// Definējam lapas mainīgos priekš head.php
$pageTitle = "Ilgtermiņa Uzņēmumu Portfelis - Saraksts.lv";
$pageDesc = "Latvijas uzņēmumi ar ilggadēju darbības vēsturi un stabilitāti. Identificēti potenciālie biznesa pēctecības un iegādes mērķi.";
?>
<?php
ob_start();
?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
      :root { --color-border: #e5e7eb; }
      
      /* Apply Inter font and tabular numbers globally within the container */
      .pensionars-container, .pensionars-container * {
          font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
          font-feature-settings: "tnum";
          font-variant-numeric: tabular-nums;
      }
      
      .main-content-wrapper { padding-top: 20px; padding-bottom: 60px; background-color: #f9f9f9; min-height: 80vh;}
      .pensionars-container { background-color: #fff; border: 1px solid var(--color-border); border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
      
      h1 { margin-top: 0; font-weight: 700; letter-spacing: -0.5px; }
      
      /* Info Alert Styles */
      .alert { padding: 20px; margin-bottom: 25px; border: 1px solid transparent; border-radius: 6px; line-height: 1.6; }
      .alert-info { color: #2c3e50; background-color: #eaf2f8; border-color: #d6eaf8; }
      .alert-heading { margin-top: 0; color: #2980b9; font-weight: 700; display: flex; align-items: center; gap: 8px; }
      .alert hr { border-top-color: #d6eaf8; margin: 15px 0; }
      
      /* Read More Functionality */
      .read-more-content { display: none; margin-top: 15px; animation: fadeIn 0.5s; }
      .read-more-btn { background: none; border: none; color: #2980b9; cursor: pointer; padding: 0; font-weight: 600; text-decoration: underline; font-size: 14px; margin-top: 10px; }
      .read-more-btn:hover { color: #1a5276; }
      .section-title { font-weight: 600; margin-top: 20px; margin-bottom: 8px; color: #2c3e50; font-size: 15px; border-bottom: 1px solid #d6eaf8; padding-bottom: 4px; display: inline-block;}
      
      /* Table Layout */
      .table-responsive-wrapper { overflow-x: auto; }
      /* table-layout: fixed => kolonnu platumi ir FIKSĒTI (no zemāk noteiktajiem %), NEATKARĪGI
         no šūnu satura. Bez tā (auto) gara nozare/lokācija izplētās kolonnu un saspieda pārējās. */
      .company-table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 1150px; }
      .company-table th, .company-table td { border-bottom: 1px solid #e2e8f0; padding: 12px 20px; text-align: left; white-space: nowrap; vertical-align: middle; font-size: 15px; }

      /* Fiksēti kolonnu platumi (%). Teksts satilpst/pārnesas kolonnā; platumi NEMAINĀS
         atkarībā no satura (piem., filtrējot nozari ar garu, daudzvārdu nosaukumu). */
      .company-table th:nth-child(1),  .company-table td:nth-child(1)  { width: 13%;   white-space: normal; overflow-wrap: break-word; word-break: break-word; } /* Nosaukums */
      .company-table th:nth-child(2),  .company-table td:nth-child(2)  { width: 10.5%; white-space: normal; overflow-wrap: break-word; word-break: break-word; } /* Lokācija */
      .company-table th:nth-child(3),  .company-table td:nth-child(3)  { width: 13%;   white-space: normal; overflow-wrap: break-word; word-break: break-word; } /* Nozare */
      .company-table th:nth-child(4),  .company-table td:nth-child(4)  { width: 10%; }   /* Apgrozījums */
      .company-table th:nth-child(5),  .company-table td:nth-child(5)  { width: 6%; }    /* Darbinieki */
      .company-table th:nth-child(6),  .company-table td:nth-child(6)  { width: 9%; }    /* Peļņa */
      .company-table th:nth-child(7),  .company-table td:nth-child(7)  { width: 21.5%; white-space: normal; overflow-wrap: break-word; } /* Bilance (platāka — lai rindas satilpst vienā rindā) */
      .company-table th:nth-child(8),  .company-table td:nth-child(8)  { width: 10.5%; white-space: normal; overflow-wrap: break-word; } /* Cena */
      .company-table th:nth-child(9),  .company-table td:nth-child(9)  { width: 3.5%; } /* Stāvoklis */
      .company-table th:nth-child(10), .company-table td:nth-child(10) { width: 3%; }   /* saite */
      
      .company-table th { background-color: #f7fafc; font-weight: 600; }
      .company-table td:nth-child(5), .company-table td:nth-child(6), .company-table td:nth-child(7) { text-align: right; }
      
      /* Balance column style — white-space: normal (nevis nowrap), lai gara rinda pārnesas
         fiksētā kolonnā, nevis pārplūst blakus kolonnā. */
      .company-table td.balance-cell { font-size: 13px; line-height: 1.4; color: #555; white-space: normal; overflow-wrap: break-word; vertical-align: middle; text-align: left; }
      .company-table td.balance-cell strong { color: #333; }
      
      .company-table td:nth-last-child(2) { text-align: center; } /* Status column */
      .company-table td:last-child { text-align: center; width: 50px; } /* Link column */
      
      /* Centering for requested columns */
      .company-table th:nth-child(1), .company-table td:nth-child(1),
      .company-table th:nth-child(3), .company-table td:nth-child(3),
      .company-table th:nth-child(8), .company-table td:nth-child(8) {
          text-align: center;
      }
      
      .company-name-block { display: flex; flex-direction: column; gap: 2px; }
      .company-name { font-weight: 600; font-size: 15px; color: #2d3748; }
      .company-regcode a, .company-regcode { color: #555; text-decoration: none; font-size: 13px; }
      .company-regcode a:hover { text-decoration: underline; }
      
      /* Link styles */
      .company-regcode a, .profile-link { color: #007bff; }
      .company-regcode a:hover, .profile-link:hover { color: #0056b3; text-decoration: underline; }
      
      .profile-link { 
          display: inline-block; 
          width: 30px; 
          height: 30px; 
          line-height: 30px; 
          border-radius: 50%; 
          background-color: #f0f0f0; 
          color: #007bff; 
          text-align: center; 
          text-decoration: none; 
          font-size: 14px; 
          transition: background-color 0.2s, color 0.2s;
      }
      .profile-link:hover { background-color: #e0e0e0; color: #0056b3; text-decoration: none; }
      
      /* Sorting */
      .company-table th.sortable { cursor: pointer; position: relative; padding-right: 35px; }
      .company-table th.sortable .sort-arrows { 
          position: absolute; 
          right: 12px; 
          top: 35%; 
          transform: translateY(-50%); 
          display: flex; 
          flex-direction: column; 
          opacity: 0.3; 
      }
      .company-table th.sortable .arrow-up { width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 5px solid #2d3748; margin-bottom: 2px; }
      .company-table th.sortable .arrow-down { width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 5px solid #2d3748; }
      .company-table th.sortable.sorted-asc .sort-arrows, .company-table th.sortable.sorted-desc .sort-arrows { opacity: 1; }
      .company-table th.sortable.sorted-asc .arrow-up { opacity: 1; } .company-table th.sortable.sorted-asc .arrow-down { opacity: 0.3; }
      .company-table th.sortable.sorted-desc .arrow-down { opacity: 1; } .company-table th.sortable.sorted-desc .arrow-up { opacity: 0.3; }

      /* Neon Grid & LED Styles */
      .neon-grid-container { width: 60px; height: 60px; border-radius: 8px; display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(3, 1fr); gap: 4px; padding: 5px; transition: box-shadow 0.3s ease-in-out, background-color 0.3s ease-in-out, filter 0.2s ease-in-out; box-sizing: border-box; border: 1px solid #ddd; background-color: rgba(0,0,0,0.02); cursor: help; }
      .company-table .neon-grid-container { width: 30px; height: 30px; gap: 2px; padding: 3px; margin: 0 auto; }
      .company-table .neon-grid-container:hover { filter: brightness(150%); }

      .neon-grid-container.frame-g { background-color: rgba(76, 175, 80, 0.1); border-color: #4CAF50; box-shadow: 0 0 8px #4CAF50, 0 0 12px #4CAF50, inset 0 0 5px rgba(76, 175, 80, 0.5); }
      .neon-grid-container.frame-o { background-color: rgba(255, 152, 0, 0.1); border-color: #FF9800; box-shadow: 0 0 8px #FF9800, 0 0 12px #FF9800, inset 0 0 5px rgba(255, 152, 0, 0.5); }
      .neon-grid-container.frame-r { background-color: rgba(244, 67, 54, 0.1); border-color: #F44336; box-shadow: 0 0 8px #F44336, 0 0 12px #F44336, inset 0 0 5px rgba(244, 67, 54, 0.5); }
      .neon-grid-container.frame-b { background-color: rgba(33, 150, 243, 0.1); border-color: #2196F3; box-shadow: 0 0 8px #2196F3, 0 0 12px #2196F3, inset 0 0 5px rgba(33, 150, 243, 0.5); }
      
      .led-wrapper { display: flex; align-items: center; justify-content: center; }
      .led { width: 12px; height: 12px; border-radius: 50%; background-color: #ddd; transition: background-color 0.3s, box-shadow 0.3s; }
      .company-table .led { width: 6px; height: 6px; }

      .led.g { background-color: #4CAF50; box-shadow: 0 0 5px #4CAF50; } 
      .led.o { background-color: #FF9800; box-shadow: 0 0 5px #FF9800; }
      .led.r { background-color: #F44336; box-shadow: 0 0 5px #F44336; } 
      .led.b { background-color: #2196F3; box-shadow: 0 0 5px #2196F3; }
      .led-sample { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; vertical-align: middle; border: 1px solid #ccc; }
      
      /* Filter Styles */
      #locationFilterPanel, #industryFilterPanel {
          display: none;
          background-color: #fff;
          border: 1px solid #ddd;
          border-radius: 8px;
          padding: 15px;
          margin-bottom: 15px;
          box-shadow: 0 4px 6px rgba(0,0,0,0.1);
          max-height: 300px;
          overflow-y: auto;
      }
      .filter-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
          gap: 10px;
      }
      .filter-btn {
          background-color: #f8f9fa;
          border: 1px solid #e2e8f0;
          padding: 8px 12px;
          border-radius: 4px;
          cursor: pointer;
          text-align: left;
          font-size: 13px;
          color: #333;
          transition: background-color 0.2s;
          position: relative;
      }
      .filter-btn:hover { background-color: #e2e8f0; }
      .filter-btn.active { background-color: #3182ce; color: white; border-color: #3182ce; }

      .count-badge {
          position: absolute;
          top: -8px;
          right: -8px;
          background-color: #e53e3e;
          color: white;
          font-size: 10px;
          font-weight: 700;
          width: 20px;
          height: 20px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 2px 4px rgba(0,0,0,0.2);
          border: 2px solid #fff;
          z-index: 10;
      }
      
      .clear-filter-btn {
          margin-bottom: 10px;
          padding: 6px 12px;
          background-color: #e53e3e;
          color: white;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          font-size: 13px;
          display: none;
      }
      
      .filter-select-box {
          background-color: #fff;
          border: 1px solid #d1d5db;
          border-radius: 4px;
          padding: 6px 24px 6px 10px;
          font-size: 13px;
          color: #333;
          margin-top: 5px;
          cursor: pointer;
          position: relative;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          font-weight: 500;
          text-align: left;
          width: 100%;
          box-sizing: border-box;
          box-shadow: 0 1px 2px rgba(0,0,0,0.05);
      }
      .filter-select-box::after {
          content: '▼';
          position: absolute;
          right: 8px;
          top: 50%;
          transform: translateY(-50%);
          font-size: 10px;
          color: #666;
      }
      .filter-select-box:hover { border-color: #adadad; }

      .clear-selection-btn {
          position: absolute;
          right: 10px; 
          top: 65%; 
          transform: translateY(-50%);
          color: #cbd5e0; 
          cursor: default;
          font-weight: bold;
          font-size: 12px;
          display: block;
          z-index: 5;
          line-height: 1;
      }
      .clear-selection-btn.active { 
          color: #e53e3e; 
          cursor: pointer;
      }
      .clear-selection-btn:hover { color: #c53030; }

      /* Infinite Scroll Loader */
      .loading-container {
          text-align: center;
          padding: 20px 0 40px 0;
          display: none;
      }
      .loading-spinner {
          display: inline-block;
          width: 30px;
          height: 30px;
          border: 3px solid rgba(0,0,0,0.1);
          border-radius: 50%;
          border-top-color: #3182ce;
          animation: spin 1s ease-in-out infinite;
      }
      @keyframes spin { to { transform: rotate(360deg); } }
      @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
      .showing-count {
          display: block;
          margin-top: 10px;
          font-size: 12px;
          color: #718096;
      }

      /* Modal Styles */
      .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 2000; display: none; align-items: center; justify-content: center; }
      .modal-content { background-color: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; max-width: 90%; width: 680px; }
      .modal-close { position: absolute; top: 10px; right: 15px; font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; } .modal-close:hover { color: #333; }
      .modal-header h2 { margin-top: 0; color: #333; }
      .explanation-container { display: flex; align-items: flex-start; gap: 30px; margin-top: 10px; }
      .explanation-grid-wrapper { text-align: center; }
      #explanation-grid-dynamic { width: 120px; height: 120px; gap: 8px; padding: 10px; }
      #explanation-grid-dynamic .led { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px; text-shadow: 0 0 2px rgba(0,0,0,0.5); }
      .label-frame { margin-top: 10px; font-size: 14px; color: #555; font-weight: bold; }
      .explanation-text { display: flex; flex-direction: column; flex: 1; }
      .color-legend { list-style: none; padding-left: 0; margin: 10px 0 0 0; text-align: left;}
      .color-legend li { margin-bottom: 5px; font-size: 14px; }
      .explanation-list { list-style-type: none; padding-left: 0; margin-left: 0; column-count: 2; column-gap: 20px; }
      .explanation-list li { margin-bottom: 6px; font-size: 13px; font-weight: 500;}
      .explanation-list li b { font-weight: bold; }
      .explanation-list li.color-b { color: #2196F3; }
      .explanation-list li.color-g { color: #4CAF50; }
      .explanation-list li.color-o { color: #FF9800; }
      .explanation-list li.color-r { color: #F44336; }
      .explanation-list li.color-n { color: #333; }
      
            @media (max-width: 767px) {
      
                .explanation-list { column-count: 1; }
      
                .explanation-container { flex-direction: column; align-items: center; }
      
            }
      
      
      
      /* Large Desktop View (> 1600px) - Restore original larger fonts and padding */
      /* Piezīme: kolonnu platumi NETIEK pārrakstīti — tie nāk no % noteikumiem augšā
         (table-layout: fixed), lai platumi paliktu fiksēti visos izmēros. */
      @media (min-width: 1601px) {
          .company-table th, .company-table td { padding: 12px 20px; font-size: 16px; } /* Explicitly set a larger font size */

          .company-name { font-size: 18px; } /* Make company name larger */
          .company-regcode { font-size: 14px; } /* Slightly larger regcode */
          .company-table td.balance-cell { font-size: 13px; } /* Balance cell font size */
          .company-table .neon-grid-container { transform: scale(1); margin: 0 auto; } /* Restore original size */
      }
      
      
      
            /* Compact Desktop/Laptop View (1101px - 1600px) */
      
            @media (min-width: 1101px) and (max-width: 1600px) {
          body, main { padding: 10px; max-width: 100%; }
          
          /* Allow horizontal scroll if it gets REALLY tight, but try to fit */
          .table-responsive-wrapper { overflow-x: auto; }
          
          /* Spacing kompakts, bet fonti lielāki nekā agrāk (12→14px) — teksts pārnesas
             fiksētajās kolonnās, tāpēc vairs nav jāsamazina fonts, lai satilptu. */
          .company-table th, .company-table td { padding: 7px 10px; font-size: 14px; }

          .company-name { font-size: 14px; }
          .company-regcode { font-size: 12px; }

          /* Compact Balance Cell (10→12px) */
          .company-table td.balance-cell { font-size: 12px; line-height: 1.35; }
          
          /* Scale down visual elements */
          .company-table .neon-grid-container { transform: scale(0.8); margin: 0 auto; }
          .led-sample { width: 10px; height: 10px; }
      }

      /* Mobile & Tablet View (< 1100px) - Switches to Card View earlier */
      @media (max-width: 1100px) {
          body { padding: 10px; }
          main { padding: 10px; }
          
          /* Mobile Control Bar */
          .mobile-controls { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
          .mobile-btn { flex: 1; padding: 10px; background: #f8f9fa; border: 1px solid #ccc; border-radius: 6px; font-weight: 600; text-align: center; position: relative; }
          .mobile-btn.active { background-color: #ebf8ff; border-color: #3182ce; color: #2c5282; }
          .mobile-select { flex: 1; padding: 10px; border-radius: 6px; border: 1px solid #ccc; background: white; min-width: 150px; }
          
          /* Hide Standard Headers and Filters */
          .company-table thead { display: none; }

          /* Karšu skatā atceļam fiksētās tabulas platumu — citādi min-width: 1150px liktu
             kartēm būt 1150px platām (horizontāls ritinājums telefonā). Šūnu platumu uz 100%
             atiestata zemākais 'width: 100% !important' noteikums. */
          .company-table { table-layout: auto; min-width: 0; }

          /* Card View Transformation */
          .company-table, .company-table tbody, .company-table tr, .company-table td { display: block; width: 100%; }
          
          .company-table tr {
              background: #fff;
              border: 1px solid #e2e8f0;
              border-radius: 8px;
              margin-bottom: 15px;
              box-shadow: 0 2px 4px rgba(0,0,0,0.05);
              padding: 15px;
              position: relative;
              box-sizing: border-box; /* lai 15px padding neizspiestu karti pāri ekrāna platumam */
          }
          
          .company-table td {
              text-align: left !important; /* Override specific center aligns */
              padding: 6px 0;
              border-bottom: 1px solid #f7fafc;
              display: flex;
              justify-content: space-between;
              align-items: center;
              width: 100% !important; /* Override fixed widths */
          }
          
          .company-table td:last-child { border-bottom: none; width: 100%; text-align: center !important; margin-top: 10px; }
          
          /* Add Labels via CSS */
          .company-table td::before {
              content: attr(data-label);
              font-weight: 600;
              color: #718096;
              font-size: 13px;
              margin-right: 10px;
              text-align: left;
          }

          /* Special styling for Name (Header of the card) */
          .company-table td[data-label="Nosaukums"] {
              display: block;
              border-bottom: 2px solid #3182ce;
              margin-bottom: 10px;
              padding-bottom: 10px;
          }
          .company-table td[data-label="Nosaukums"]::before { display: none; }
          .company-name { font-size: 18px; color: #2b6cb0; }
          
          /* Special styling for Balance */
          .company-table td[data-label="Bilance"] { display: block; }
          .company-table td[data-label="Bilance"]::before { display: block; margin-bottom: 5px; }
          
          /* Adjust Neon Grid */
          .company-table .neon-grid-container { margin: 0; }
          
          /* Filter Panels on Mobile */
          #locationFilterPanel, #industryFilterPanel { position: fixed; top: 50px; left: 10px; right: 10px; z-index: 1000; max-height: 80vh; overflow-y: auto; width: auto; box-shadow: 0 0 100px rgba(0,0,0,0.5); border: 2px solid #3182ce; }
          
          /* Profile Link Button */
          .profile-link { width: 100%; border-radius: 6px; background-color: #ebf8ff; }
      }
      
      /* Desktop only helper */
      @media (min-width: 1101px) {
          .mobile-controls { display: none; }
      }
    </style>
<?php 
$extraHeadContent = ob_get_clean(); 
?>
<!DOCTYPE html>
<html lang="lv">
<?php 
// 1. Iekļaujam centrālo HEAD moduli
include $_SERVER['DOCUMENT_ROOT'] . '/registrs/head/head.php'; 
?>
<body>

    <?php 
    // 2. Iekļaujam HEADER
    include $_SERVER['DOCUMENT_ROOT'] . '/registrs/header.php'; 
    ?>

    <main class="main-content-wrapper">
        <div class="fixed-width-container pensionars-container">
        <div class="alert alert-info" role="alert">
            <h4 class="alert-heading">⚠️ Svarīgi: Šis ir teorētisks modelis, kas balstīts uz automatizētu publiski pieejamo datu analīzi un neatspoguļo oficiālus pārdošanas piedāvājumus.</h4>
            
            <p>
                <strong>Īsumā:</strong> Nopirkt strādājošu biznesu ir drošāk kā dibināt jaunu – jūs uzreiz iegūstat gan klientus, gan ienākumus.
            </p>
            
            <button id="toggleInfoBtn" class="read-more-btn">Lasīt vairāk &#9660;</button>
            
            <div id="moreInfo" class="read-more-content">
                <hr>
                <div class="section-title">Plašāk par sarakstu</div>
                <p>
                    Šajā sarakstā apkopoti Latvijas uzņēmumi ar <strong>ilggadēju darbības vēsturi</strong> (dibināti pirms 2016. gada), kas ir stabili savas nozares spēlētāji. 
                    Izmantojot algoritmisku pieeju, no Latvijas uzņēmumu reģistra datiem ir izdalīts specifisks segments – stabili uzņēmumi ar ilggadēju vēsturi un vienu noteicošo īpašnieku. Šī izlase fokusējas uz biznesiem, kuros ir augsts pēctecības potenciāls.
                    Dati liecina, ka šo uzņēmumu vadītāji ir veiksmīgi vadījuši savu biznesu cauri dažādiem ekonomikas cikliem, taču, iespējams, ir sasnieguši posmu, kad prioritātes dabiski mainās.
                </p>
                <p>
                    Tā vietā, lai cīnītos par kārtējo tirgus paplašināšanu, dienas kārtībā biežāk parādās vēlme veltīt laiku ģimenei, mazbērniem, veselībai vai mierīgai dārza kopšanai. 
                    Šī saraksta mērķis ir identificēt šos uzņēmumus, lai nodrošinātu to <strong>biznesa pēctecību</strong> – iespēju jaunai enerģijai turpināt iesākto, saglabājot iepriekšējā īpašnieka radīto vērtību un darba vietas.
                </p>


<div class="section-title">Biznesa pēctecības ieguvumi:</div>
<ul>
	<li><strong>Vērtības Saglabāšana (Pārdevējam):</strong> Iespēja nodot savu dzīves darbu rokās, kas to attīstīs tālāk, nevis vienkārši likvidēt uzņēmumu.</li>
	<li><strong>Stabilitāte (Pircējam):</strong> Atšķirībā no jauna biznesa, šeit ir pārbaudīts modelis, lojāla klientu bāze un reāla naudas plūsma no pirmās dienas.</li>
	<li><strong>Modernizācijas Potenciāls:</strong> Ilggadēji uzņēmumi bieži piedāvā iespēju strauji celt efektivitāti, ieviešot mūsdienīgas tehnoloģijas un digitalizāciju, ko iepriekšējā vadība, iespējams, atlika.</li>
</ul>
                <div class="section-title">📊 Kā tiek rēķināta indikatīvā vērtība?</div>
                <p>Tabulā redzamā <strong>"Cena (Teorēt.)"</strong> ir aptuvens biznesa vērtības aprēķins, izmantojot "VAI NU / VAI" pieeju (izvēloties augstāko no diviem rādītājiem):</p>
                <ul>
                    <li><strong>Peļņas metode (Naudas plūsma):</strong> Gada peļņa reizināta ar nozares koeficientu. Tas atspoguļo uzņēmuma spēju ģenerēt nākotnes ienākumus.</li>
                    <li><strong>Aktīvu metode (Likvidācijas vērtība):</strong> Pašu kapitāls. Ja uzņēmuma peļņa ir zema, tā vērtība balstās uz uzkrātajiem līdzekļiem un īpašumiem.</li>
                </ul>
                <p style="font-size: 13px; color: #555;">
                    <em>Nozaru Koeficienti: IT/Tehnoloģijas: 7.0x, Ražošana: 3.5x, Būvniecība: 2.5x u.c.</em>
                </p>

                <div class="section-title">🤝 Kā iegādāties uzņēmumu? Praktiskie soļi</div>
                <p>Biežākais šķērslis nav piemērota uzņēmuma atrašana, bet gan maldīgs uzskats – "lai nopirktu uzņēmumu, man uzreiz vajag visu summu kontā".</p>
                
                <strong>1. Finansēšanas modeļi</strong>
                <ul>
                    <li>
                        <strong>Pārdevēja finansējums:</strong> Šis ir visbiežāk izmantotais modelis mazo un vidējo uzņēmumu iegādē. Pircējs samaksā tikai daļu summas (piemēram, 20-30%) kā pirmo iemaksu, bet atlikušo daļu izmaksā pakāpeniski no uzņēmuma nākotnes peļņas 3–5 gadu laikā. Tas ir izdevīgi abām pusēm: pircējam nav nepieciešama milzīga sākuma kapitāla, bet pārdevējs saņem "pensiju" un ir ieinteresēts palīdzēt jaunajam īpašniekam veiksmīgi pārņemt vadību.
                    </li>
                    <li><strong>Banku līdzfinansējums:</strong> Ja uzņēmumam ir vērtīgi aktīvi (nekustamais īpašums, tehnika), banka var piešķirt kredītu to iegādei. Tomēr bankas bieži ir piesardzīgas, finansējot uzņēmuma reputāciju un naudas plūsmu bez ķīlas.</li>
                    <li><strong>Investoru piesaiste:</strong> Ja uzņēmumam ir liels modernizācijas potenciāls, darījumam var piesaistīt privātos investorus, kuri nodrošina kapitālu apmaiņā pret daļām uzņēmumā.</li>
                    
                </ul>

                <strong>2. Kam pievērst uzmanību? (Padziļinātā izpēte)</strong>
                <ul>
                    <li><strong>"Neaizvietojamā īpašnieka" risks:</strong> Vai klienti pērk no <em>uzņēmuma zīmola</em> vai no <em>Jāņa/Annas personīgi</em>? Ja īpašnieks ir vienīgais, kurš pārzina procesus un pazīst klientus, viņam aizejot, biznesa vērtība var kristies. Risinājums: Vienoties par pārejas periodu (6–12 mēneši), kurā bijušais īpašnieks apmāca jauno vadību.</li>
                    <li>
                         <strong>Vienoties par pārejas periodu (6–12 mēneši):</strong> Biznesa nodošana nav tikai atslēgu atdošana. Lai jaunais īpašnieks nezaudētu klientus, nepieciešams process trīs fāzēs:
                         <br>• <em>Aktīvā fāze (1.–3. mēn):</em> Iepriekšējais īpašnieks nāk uz darbu ikdienā, bet lēmumus pieņem kopā ar jauno īpašnieku. Šajā laikā notiek iepazīstināšana ar galvenajiem klientiem ("Sveiki, šis ir Jānis, viņš turpinās iesākto..."). Tas nomierina partnerus.
                         <br>• <em>Atbalsta fāze (3.–6. mēn):</em> Bijušais īpašnieks vairs nav iesaistīts ikdienas operatīvajā darbā, bet ir pieejams telefoniski vai klātienē reizi nedēļā sarežģītu jautājumu risināšanai.
                         <br>• <em>Konsultatīvā fāze:</em> Bijušais īpašnieks darbojas kā padomdevējs ārkārtas situācijās.
                    <br>• <em>Ieteikums:</em> Bieži vien pēdējo daļu no pirkuma summas (piemēram, 10%) izmaksā tikai pēc veiksmīga pārejas perioda beigām. Tas motivē pārdevēju godprātīgi nodot visas zināšanas.
                    
                    </li>
                    <li><strong>Slēptās saistības & Tehnoloģiskais parāds:</strong> Vienmēr pārbaudiet, vai nav aktīvu tiesvedību. Un daudzi ilgtermiņa uzņēmumi strādā ar novecojušām sistēmām. Pircējam jāierēķina izmaksas, kas būs nepieciešamas procesu digitalizācijai un modernizācijai.</li>
                </ul>

                <div class="section-title">⚖️ Ētika un sarunu vešana</div>
                <ul>
                    <li><strong>Cieņa pret mantojumu:</strong> Pērkot uzņēmumu no cilvēka, kurš to vadījis 20+ gadus, darījums nekad nav tikai par naudu. Tā ir viņa dzīve. 
                    Pircējam jādemonstrē cieņa pret padarīto un jārada pārliecība, ka uzņēmums nonāks gādīgās rokās. Agresīva "haizivs" pieeja un kritika par "vecmodīgiem procesiem" šeit nestrādās.</li>
                <li><strong>Godīga saruna:</strong> Vislabākie darījumi notiek tad, kad abas puses atklāti runā par saviem mērķiem. Iespējams, pārdevējs vēlas nevis pārdot 100%, bet saglabāt mazu daļu un turpināt darboties kā konsultants.</li>
                <li><strong>Juridiskā drošība:</strong> Lai gan savstarpēja uzticēšanās ir svarīga, visi nosacījumi (īpaši par atliktajiem maksājumiem un atbildību) ir jafiksē juridiski korektā pirkuma līgumā.</li>

                <div class="section-title">📞 Tavs pirmais solis</div>
                <p>
                    Vai sarakstā ieraudzīji uzņēmumu, kas tevi uzrunā? <strong>Pirmais solis ir vienkārši piezvanīt.</strong> 
                    Tev nav uzreiz jāizsaka pirkuma piedāvājums. Piezvani, lai aprunātos par nozari vai piedāvātu sadarbību. Veiksmīgi darījumi sākas ar cilvēcīgu sarunu.
                </p>

                <hr>
                <p style="font-size: 12px; color: #7f8c8d; line-height: 1.4;">
                    <em><strong>Atruna:</strong> Šis rīks ir informācijas avots, kas balstīts uz publiski pieejamu vēsturisko datu analīzi (UR, VID), ievērojot VDAR (GDPR) principus. Portāla veidotājs nav finanšu konsultants vai starpnieks un neuzņemas atbildību par lēmumiem, kas pieņemti, balstoties uz šiem datiem. Pirms jebkura uzņēmuma iegādes vai investīciju veikšanas, obligāti konsultējieties ar profesionālu grāmatvedi un juristu.</em>
                </p>
            </div>
        </div>

        <h1>Ilgtermiņa Uzņēmumu Portfelis</h1>
        
        <div class="mobile-controls">
            <button class="mobile-btn" id="mobLocBtn">📍 Lokācija</button>
            <button class="mobile-btn" id="mobIndBtn">🏭 Nozare</button>
            <select class="mobile-select" id="mobSortSelect">
                <option value="employees|desc">Darbinieki (↓)</option>
                <option value="turnover|desc">Apgrozījums (↓)</option>
                <option value="profit|desc">Peļņa (↓)</option>
                <option value="theoretical_price|desc">Cena (↓)</option>
                <option value="name|asc">Nosaukums (A-Z)</option>
            </select>
        </div>
        
        <div id="locationFilterPanel">
            <button class="clear-filter-btn" id="clearLocationFilter">Notīrīt lokācijas filtru</button>
            <div class="filter-grid" id="locationGrid"></div>
        </div>
        
        <div id="industryFilterPanel">
            <button class="clear-filter-btn" id="clearIndustryFilter">Notīrīt nozares filtru</button>
            <div class="filter-grid" id="industryGrid"></div>
        </div>

        <div class="table-responsive-wrapper">
            <table class="company-table" id="pensionariTable">
                <thead>
                    <tr>
                        <th class="sortable" data-sort-key="name">Nosaukums <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                        <th class="sortable" data-sort-key="location">
                            Lokācija 
                            <div id="locationFilterBox" class="filter-select-box" title="Filtrēt pēc lokācijas">
                                <span id="locationFilterText">Visas lokācijas</span>
                            </div>
                            <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span>
                            <span id="clearLocationBox" class="clear-selection-btn" title="Notīrīt filtru">❌</span>
                        </th>
                        <th class="sortable" data-sort-key="nace_description">
                            Nozare 
                            <div id="industryFilterBox" class="filter-select-box" title="Filtrēt pēc nozares">
                                <span id="industryFilterText">Visas nozares</span>
                            </div>
                            <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span>
                            <span id="clearIndustryBox" class="clear-selection-btn" title="Notīrīt filtru">❌</span>
                        </th>
                        <th class="sortable" data-sort-key="turnover" data-sort-type="number">Apgrozījums <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                        <th class="sortable" data-sort-key="employees" data-sort-type="number">Darbinieki <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                        <th class="sortable" data-sort-key="profit" data-sort-type="number">Peļņa <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                        <th>Bilance</th>
                        <th class="sortable" data-sort-key="theoretical_price" data-sort-type="number">Cena (Teorēt.) <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                        <th>Stāvoklis</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        
        <div id="infiniteScrollTrigger" class="loading-container">
            <div class="loading-spinner"></div>
            <span id="showingCountLabel" class="showing-count"></span>
        </div>
        </div>
    </main>
    
    <?php 
    // 3. Iekļaujam FOOTER
    include $_SERVER['DOCUMENT_ROOT'] . '/registrs/footer/footer.php'; 
    ?>

    <?php 
    // 4. Iekļaujam COOKIE moduli
    include $_SERVER['DOCUMENT_ROOT'] . '/registrs/cookie/cookie.php'; 
    ?>
    
  <div id="grid-explanation-modal" class="modal-overlay">
      <div class="modal-content">
          <span class="modal-close">&times;</span>
          <div class="modal-header"><h2>Finanšu veselības režģa skaidrojums</h2></div>
          <div class="modal-body">
              <div class="explanation-container">
                  <div class="explanation-grid-wrapper">
                      <div id="explanation-grid-dynamic" class="neon-grid-container"></div>
                      <div class="label-frame">&uarr; 10 </div>
                      <ul class="color-legend">
                          <li><span class="led-sample" style="background-color: #2196F3;"></span><b>Zils:</b> Pārmērīgi</li>
                          <li><span class="led-sample" style="background-color: #4CAF50;"></span><b>Zaļš:</b> Labi</li>
                          <li><span class="led-sample" style="background-color: #FF9800;"></span><b>Oranžs:</b> Vidēji</li>
                          <li><span class="led-sample" style="background-color: #F44336;"></span><b>Sarkans:</b> Slikti</li>
                          <li><span class="led-sample" style="background-color: #ddd;"></span><b>Pelēks:</b> Nav datu</li>
                      </ul>
                  </div>
                  <div class="explanation-text">
                      <ol class="explanation-list"></ol>
                  </div>
              </div>
          </div>
      </div>
  </div>
    <script>
        const companiesData = <?php echo $error_message ? '[]' : json_encode($companies_data, JSON_UNESCAPED_UNICODE); ?>;
        const locationsList = <?php echo json_encode($locations, JSON_UNESCAPED_UNICODE); ?>;
        const industriesList = <?php echo json_encode($industries, JSON_UNESCAPED_UNICODE); ?>;
        const errorMessage = <?php echo $error_message ? json_encode($error_message) : 'null'; ?>;
        
        // Configurations and Dictionaries from nozare.php
        const symbolToClass = { 'r': 'r', 'o': 'o', 'g': 'g', 'b': 'b', 'n': '' };
        const symbolToColorKey = { 'r': 'red', 'o': 'orange', 'g': 'green', 'b': 'blue', 'n': 'grey', '': 'grey' };
        const symbolToCssClass = { 'b': 'color-b', 'g': 'color-g', 'o': 'color-o', 'r': 'color-r', 'n': 'color-n' };

        // HTML ekranēšana pret injekciju/nepareizu renderēšanu (tāpat kā nozare.php) — visi
        // datu bāzes teksta lauki (nosaukums, lokācija, nozare, reitings) iet cauri šai funkcijai.
        const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g,
            m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

        const explanations = {
            1: { title: "1. Likviditāte (CR)", blue: "Līdzekļi stāv neizmantoti uzņēmuma attīstībai", green: "Uzņēmums spēj segt savas īstermiņa saistības", orange: "Uzņēmumam var rasties problēmas segt saistības", red: "Uzņēmums nespēj segt savas īstermiņa saistības", grey: "Nav datu" },
            2: { title: "2. Likviditāte (QR)", blue: "Līdzekļi stāv neizmantoti uzņēmuma attīstībai", green: "Uzņēmums spēj segt saistības bez krājumu pārdošanas", orange: "Uzņēmumam var rasties problēmas segt saistības", red: "Uzņēmums nespēj segt savas īstermiņa saistības", grey: "Nav datu" },
            3: { title: "3. Rentabilitāte (NPM)", green: "Augsta peļņas marža, efektīva izmaksu kontrole", orange: "Vidēja peļņas marža, jāuzlabo efektivitāte", red: "Zema peļņas marža vai zaudējumi", grey: "Nav datu" },
            4: { title: "4. Efektivitāte (ROA)", green: "Efektīva aktīvu izmantošana peļņas gūšanai", orange: "Vidēja aktīvu atdeve, iespējami uzlabojumi", red: "Neefektīva aktīvu izmantošana, zema peļņa", grey: "Nav datu" },
            5: { title: "5. Efektivitāte (ROE)", green: "Laba kapitāla atdeve, efektīva peļņas reinvestēšana", orange: "Vidēja kapitāla atdeve, jāuzlabo rentabilitāte", red: "Zema kapitāla atdeve, risks investoriem", grey: "Nav datu" },
            6: { title: "6. Efektivitāte (AT)", green: "Efektīva aktīvu izmantošana apgrozījuma radīšanai", orange: "Vidēja aktīvu izmantošana, iespējama neefektivitāte", red: "Zems apgrozījums no aktīviem, nepietiekama pārdošana", grey: "Nav datu" },
            7: { title: "7. Maksātspēja (D/E)", green: "Zems saistību līmenis, stabils uzņēmums", orange: "Mērens saistību līmenis, pieņemams risks", red: "Augsts saistību līmenis, augsts risks", grey: "Nav datu" },
            8: { title: "8. Maksātspēja (ROCE)", green: "Efektīva kapitāla izmantošana peļņas gūšanai", orange: "Vidēja kapitāla atdeve, iespējami uzlabojumi", red: "Neefektīva kapitāla izmantošana, zema peļņa", grey: "Nav datu" },
            9: { title: "9. Maksātspēja (IC)", green: "Uzņēmums stabili sedz procentu maksājumus", orange: "Nelielas grūtības segt procentu maksājumus", red: "Uzņēmums nespēj segt procentu maksājumus", grey: "Nav datu" },
            10: { title: "10. Kopējais risks (Altman Z)", green: "Stabils uzņēmums, zems bankrota risks", orange: "Uzņēmums 'pelēkajā zonā', iespējamas problēmas", red: "Augsts bankrota risks", grey: "Nav datu<br><span style='font-size:10px; color:#888;'>* Aprēķins pēc Altmana 1983.g. modeļa privātiem uzņēmumiem.</span>" }
        };

        document.addEventListener("DOMContentLoaded", () => {
            // Read More Toggle Logic
            const toggleBtn = document.getElementById('toggleInfoBtn');
            const moreContent = document.getElementById('moreInfo');
            
            if(toggleBtn && moreContent) {
                toggleBtn.addEventListener('click', () => {
                    const isHidden = moreContent.style.display === 'none' || moreContent.style.display === '';
                    if (isHidden) {
                        moreContent.style.display = 'block';
                        toggleBtn.innerHTML = 'Aizvērt &#9650;';
                    } else {
                        moreContent.style.display = 'none';
                        toggleBtn.innerHTML = 'Lasīt vairāk &#9660;';
                    }
                });
            }

            const tableBody = document.querySelector("#pensionariTable tbody");
            const infiniteScrollTrigger = document.getElementById('infiniteScrollTrigger');
            const showingCountLabel = document.getElementById('showingCountLabel');

            let currentSort = { key: 'employees', dir: 'desc' };
            let currentLocationFilter = null;
            let currentIndustryFilter = null;
            let filteredData = [...companiesData]; // Working copy
            
            // Pagination State
            const ITEMS_PER_BATCH = 50;
            let visibleItems = ITEMS_PER_BATCH;
            let isLoading = false;
            
            // Pre-calculate theoretical price
            companiesData.forEach(c => {
                const p = parseFloat(c.profit) || 0;
                const e = parseFloat(c.equity) || 0;
                const mult = parseFloat(c.valuation_multiplier) || 3;
                c.theoretical_price = Math.max(p * mult, e);
            });

            // Initialize filtered data
            filterAndSortData();

            // --- Infinite Scroll Observer ---
            const observerOptions = {
                root: null,
                rootMargin: '100px', // Load 100px before reaching the bottom
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !isLoading && filteredData.length > visibleItems) {
                        loadMoreItems();
                    }
                });
            }, observerOptions);

            if (infiniteScrollTrigger) {
                observer.observe(infiniteScrollTrigger);
            }

            function loadMoreItems() {
                isLoading = true;
                // Simulate small delay for visual effect
                setTimeout(() => {
                    visibleItems += ITEMS_PER_BATCH;
                    renderTable(filteredData, true); 
                    isLoading = false;
                }, 50);
            }

            // --- Filter Helper Logic ---
            function setupFilter(panelId, toggleId, gridId, clearBtnId, clearBoxId, textSpanId, list, applyCallback, type, defaultText) {
                const panel = document.getElementById(panelId);
                const toggleBox = document.getElementById(toggleId);
                const grid = document.getElementById(gridId);
                const clearPanelBtn = document.getElementById(clearBtnId);
                const clearBoxBtn = document.getElementById(clearBoxId);
                const textSpan = document.getElementById(textSpanId);

                function updateUI(selectedItem) {
                    if (textSpan) textSpan.textContent = selectedItem ? selectedItem : defaultText;
                    
                    if (clearBoxBtn) {
                        if (selectedItem) {
                            clearBoxBtn.classList.add('active');
                        } else {
                            clearBoxBtn.classList.remove('active');
                        }
                    }

                    if (clearPanelBtn) clearPanelBtn.style.display = selectedItem ? 'inline-block' : 'none';

                    grid.querySelectorAll('.filter-btn').forEach(b => {
                        if (selectedItem && b.dataset.value === selectedItem) { 
                             b.classList.add('active');
                        } else {
                            b.classList.remove('active');
                        }
                    });
                }

                if (toggleBox && panel && grid) {
                    toggleBox.addEventListener('click', (e) => {
                        if (e.target === clearBoxBtn) return;

                        e.stopPropagation();
                        if (type === 'location') {
                            const indPanel = document.getElementById('industryFilterPanel');
                            if (indPanel) indPanel.style.display = 'none';
                        }
                        if (type === 'industry') {
                            const locPanel = document.getElementById('locationFilterPanel');
                            if (locPanel) locPanel.style.display = 'none';
                        }
                        
                        const isVisible = panel.style.display === 'block';
                        panel.style.display = isVisible ? 'none' : 'block';
                    });
                    
                    if (clearBoxBtn) {
                        clearBoxBtn.addEventListener('click', (e) => {
                            e.stopPropagation(); 
                            if (!clearBoxBtn.classList.contains('active')) return;
                            applyCallback(null);
                            updateUI(null);
                            panel.style.display = 'none';
                        });
                    }

                    if (list && list.length > 0) {
                        list.forEach(item => {
                            const btn = document.createElement('div');
                            btn.className = 'filter-btn';
                            btn.dataset.value = item; // Store value for dynamic updates
                            const textNode = document.createTextNode(item);
                            btn.appendChild(textNode);
                            
                            // Badge placeholder (will be populated by updateDynamicBadges)
                            const badge = document.createElement('span');
                            badge.className = 'count-badge';
                            badge.style.display = 'none'; // Hidden by default
                            btn.appendChild(badge);

                            btn.addEventListener('click', () => {
                                applyCallback(item);
                                updateUI(item);
                                panel.style.display = 'none';
                            });
                            grid.appendChild(btn);
                        });
                    }

                    if (clearPanelBtn) {
                        clearPanelBtn.addEventListener('click', () => {
                            applyCallback(null);
                            updateUI(null);
                            panel.style.display = 'none';
                        });
                    }
                }
            }

            function updateDynamicBadges() {
                // 1. Calculate Location Counts (based on current INDUSTRY filter)
                const locCounts = {};
                // Filter data by industry only to see what locations are available in that industry
                const locData = companiesData.filter(c => !currentIndustryFilter || c.nace_description === currentIndustryFilter);
                locData.forEach(c => {
                    if (c.location) locCounts[c.location] = (locCounts[c.location] || 0) + 1;
                });

                // Update Location Grid Badges
                const locGrid = document.getElementById('locationGrid');
                if (locGrid) {
                    locGrid.querySelectorAll('.filter-btn').forEach(btn => {
                        const val = btn.dataset.value;
                        const count = locCounts[val] || 0;
                        const badge = btn.querySelector('.count-badge');
                        if (badge) {
                            badge.textContent = count;
                            badge.style.display = count > 0 ? 'flex' : 'none';
                        }
                        // Optional: Dim button if count is 0
                        btn.style.opacity = count > 0 ? '1' : '0.5';
                        btn.style.pointerEvents = count > 0 ? 'auto' : 'none'; // Disable click if 0
                    });
                }

                // 2. Calculate Industry Counts (based on current LOCATION filter)
                const indCounts = {};
                // Filter data by location only to see what industries are available in that location
                const indData = companiesData.filter(c => !currentLocationFilter || c.location === currentLocationFilter);
                indData.forEach(c => {
                    if (c.nace_description) indCounts[c.nace_description] = (indCounts[c.nace_description] || 0) + 1;
                });

                // Update Industry Grid Badges
                const indGrid = document.getElementById('industryGrid');
                if (indGrid) {
                    indGrid.querySelectorAll('.filter-btn').forEach(btn => {
                        const val = btn.dataset.value;
                        const count = indCounts[val] || 0;
                        const badge = btn.querySelector('.count-badge');
                        if (badge) {
                            badge.textContent = count;
                            badge.style.display = count > 0 ? 'flex' : 'none';
                        }
                        // Optional: Dim button if count is 0
                        btn.style.opacity = count > 0 ? '1' : '0.5';
                        btn.style.pointerEvents = count > 0 ? 'auto' : 'none';
                    });
                }
            }

            // Setup Location Filter
            setupFilter(
                'locationFilterPanel', 'locationFilterBox', 'locationGrid', 'clearLocationFilter', 
                'clearLocationBox', 'locationFilterText',
                locationsList, (loc) => {
                    currentLocationFilter = loc;
                    visibleItems = ITEMS_PER_BATCH;
                    filterAndSortData();
                    renderTable(filteredData);
                    updateDynamicBadges(); // Update counts on change

                    // Update Mobile Button State (simetriski nozares filtram)
                    const mobBtn = document.getElementById('mobLocBtn');
                    if (mobBtn) {
                        if (loc) { mobBtn.classList.add('active'); mobBtn.textContent = '📍 ' + loc; }
                        else { mobBtn.classList.remove('active'); mobBtn.textContent = '📍 Lokācija'; }
                    }
                }, 'location', 'Visas lokācijas'
            );

            // Setup Industry Filter
            setupFilter(
                'industryFilterPanel', 'industryFilterBox', 'industryGrid', 'clearIndustryFilter', 
                'clearIndustryBox', 'industryFilterText',
                industriesList, (ind) => {
                    currentIndustryFilter = ind;
                    visibleItems = ITEMS_PER_BATCH;
                    filterAndSortData();
                    renderTable(filteredData);
                    updateDynamicBadges(); // Update counts on change
                    
                    // Update Mobile Button State
                    const mobBtn = document.getElementById('mobIndBtn');
                    if(mobBtn) {
                        if(ind) { mobBtn.classList.add('active'); mobBtn.textContent = '🏭 ' + ind; }
                        else { mobBtn.classList.remove('active'); mobBtn.textContent = '🏭 Nozare'; }
                    }
                }, 'industry', 'Visas nozares'
            );
            
            // --- Mobile Controls Logic ---
            const mobLocBtn = document.getElementById('mobLocBtn');
            const mobIndBtn = document.getElementById('mobIndBtn');
            const mobSortSelect = document.getElementById('mobSortSelect');
            
            if (mobLocBtn) {
                mobLocBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const panel = document.getElementById('locationFilterPanel');
                    const otherPanel = document.getElementById('industryFilterPanel');
                    if (otherPanel) otherPanel.style.display = 'none';
                    if (panel) panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
                });
            }
            
            if (mobIndBtn) {
                mobIndBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const panel = document.getElementById('industryFilterPanel');
                    const otherPanel = document.getElementById('locationFilterPanel');
                    if (otherPanel) otherPanel.style.display = 'none';
                    if (panel) panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
                });
            }
            
            if (mobSortSelect) {
                mobSortSelect.addEventListener('change', (e) => {
                    const [key, dir] = e.target.value.split('|');
                    currentSort.key = key;
                    currentSort.dir = dir;
                    
                    // Also update desktop headers visual state
                    document.querySelectorAll('th.sortable').forEach(th => {
                         th.classList.remove('sorted-asc', 'sorted-desc');
                         if (th.dataset.sortKey === key) th.classList.add(dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                    });
                    
                    filterAndSortData();
                    renderTable(filteredData);
                });
            }
            
            // (Mobilās lokācijas pogas sinhronizācija tagad notiek tieši lokācijas filtra
            //  callback'ā augšā — iepriekšējais MutationObserver risinājums vairs nav vajadzīgs.)

            function filterAndSortData() {
                // 1. Filter
                filteredData = companiesData.filter(c => {
                    const locMatch = !currentLocationFilter || c.location === currentLocationFilter;
                    const indMatch = !currentIndustryFilter || c.nace_description === currentIndustryFilter;
                    return locMatch && indMatch;
                });

                // 2. Sort — sortType nolasām VIENREIZ (nevis katrā komparatora izsaukumā, kas
                // radīja ~100k DOM vaicājumu vienā kārtošanā).
                const sortTh = document.querySelector(`th[data-sort-key="${currentSort.key}"]`);
                const sortType = sortTh ? (sortTh.dataset.sortType || 'string') : 'string';

                filteredData.sort((a, b) => {
                    const valA = a[currentSort.key]; const valB = b[currentSort.key];
                    if (valA === null) return 1; if (valB === null) return -1;

                    let compare = 0;
                    if (sortType === 'number') {
                        compare = (parseFloat(valA) || 0) - (parseFloat(valB) || 0);
                    } else {
                        compare = (valA || '').toString().localeCompare((valB || '').toString(), 'lv');
                    }
                    return currentSort.dir === 'asc' ? compare : -compare;
                });
            }
            
            // Modal elements
            const modal = document.getElementById('grid-explanation-modal');
            const closeModalBtn = modal ? modal.querySelector('.modal-close') : null;
            const explanationGrid = modal ? document.getElementById('explanation-grid-dynamic') : null;

            // --- Core Grid Generation Logic ---
            function generateNeonGridHtml(healthString, isExplanation = false) {
                if (!healthString || healthString.length !== 10) healthString = 'nnnnnnnnnn';
                let ledSymbols = healthString.slice(0, 9).split('');
                let frameSymbol = healthString.slice(9, 10);
                
                if (isExplanation) {
                     ledSymbols = ledSymbols.map((symbol, index) => {
                        const indicatorNum = index + 1;
                        if (indicatorNum > 2 && symbol === 'b') {
                            return 'g'; 
                        }
                        return symbol;
                    });
                    if (frameSymbol === 'b') {
                        frameSymbol = 'g';
                    }
                }

                const frameClassLetter = symbolToClass[frameSymbol] || '';
                const frameClass = frameClassLetter ? `frame-${frameClassLetter}` : '';
                
                let ledsHtml = '';
                ledSymbols.forEach((symbol, index) => {
                    const ledClass = symbolToClass[symbol] || '';
                    const number = isExplanation ? `${index + 1}` : '';
                    ledsHtml += `<div class="led-wrapper"><div class="led ${ledClass}">${number}</div></div>`;
                });
                
                if (isExplanation) {
                    return { html: ledsHtml, frameClass: frameClass };
                }
                
                const originalFrameClassLetter = symbolToClass[healthString.slice(9, 10)] || '';
                const originalFrameClass = originalFrameClassLetter ? `frame-${originalFrameClassLetter}` : '';
                return `<div class="neon-grid-container ${originalFrameClass}" data-health-string="${healthString}">${ledsHtml}</div>`;
            }

            // --- Modal Logic ---
            function openModal(healthString) {
                if (!explanationGrid) return;
                if (!healthString || healthString.length !== 10) healthString = 'nnnnnnnnnn';
                
                const { html, frameClass } = generateNeonGridHtml(healthString, true);
                explanationGrid.innerHTML = html;
                explanationGrid.className = 'neon-grid-container ' + frameClass;
                
                const explanationList = modal.querySelector('.explanation-list');
                if (!explanationList) return; 
                explanationList.innerHTML = '';

                const ledSymbols = healthString.slice(0, 9).split('');
                const frameSymbol = healthString.slice(9, 10);

                ledSymbols.forEach((symbol, index) => {
                    const indicatorNum = index + 1;
                    const originalSymbol = symbol; 
                    let currentSymbol = symbol;

                    if (indicatorNum > 2 && currentSymbol === 'b') {
                        currentSymbol = 'g';
                    }
                    
                    const data = explanations[indicatorNum];
                    const colorKey = symbolToColorKey[currentSymbol] || 'grey';
                    const description = data[colorKey] || data['grey']; 
                    
                    const cssClass = symbolToCssClass[originalSymbol] || 'color-n';
                    explanationList.innerHTML += `<li class="${cssClass}"><b>${data.title}</b><br>${description}</li>`;
                });

                const originalFrameSymbol = frameSymbol; 
                let currentFrameSymbol = frameSymbol;
                if (currentFrameSymbol === 'b') {
                    currentFrameSymbol = 'g';
                }

                const data10 = explanations[10];
                const colorKey10 = symbolToColorKey[currentFrameSymbol] || 'grey';
                const description10 = data10[colorKey10] || data10['grey'];
                
                const cssClass10 = symbolToCssClass[originalFrameSymbol] || 'color-n';
                explanationList.innerHTML += `<li class="${cssClass10}"><b>${data10.title}</b><br>${description10}</li>`;
                
                modal.style.display = 'flex';
            }
            
            function closeModal() { 
                if(modal) modal.style.display = 'none';
            }

            if (modal) {
                document.body.addEventListener('click', function(event) {
                    const grid = event.target.closest('.neon-grid-container');
                    if (grid && grid.dataset.healthString) {
                        openModal(grid.dataset.healthString);
                    }
                });
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', closeModal);
                }
                modal.addEventListener('click', function(event) { if (event.target === modal) closeModal(); });
                window.addEventListener('keydown', function(event) { if (event.key === 'Escape' && modal.style.display === 'flex') closeModal(); });
            }

            // --- Table Logic ---
            function formatNumber(value) {
                if (value === null || typeof value === 'undefined' || isNaN(value)) return '—';
                return new Intl.NumberFormat('lv-LV', { maximumFractionDigits: 0 }).format(value);
            }

            function renderHistory(jsonStr, isCurrency) {
                if (!jsonStr) return '';
                try {
                    const history = JSON.parse(jsonStr);
                    if (!Array.isArray(history) || history.length <= 1) return '';
                    
                    const pastYears = history.slice(1, 5);
                    
                    let html = '<div style="font-size: 11px; color: #888; margin-top: 4px; line-height: 1.2;">';
                    pastYears.forEach(item => {
                        const val = formatNumber(item.v) + (isCurrency ? ' €' : '');
                        html += `<div>${item.y}: ${val}</div>`;
                    });
                    html += '</div>';
                    return html;
                } catch (e) { return ''; }
            }

            function renderTable(data, append = false) {
                if (errorMessage) {
                    tableBody.innerHTML = `<tr><td colspan="10" style="color: red; text-align: center;">${errorMessage}</td></tr>`;
                    if(infiniteScrollTrigger) infiniteScrollTrigger.style.display = 'none';
                    return;
                }
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="10" style="text-align: center;">Netika atrasti uzņēmumi, kas atbilstu kritērijiem.</td></tr>`;
                    if(infiniteScrollTrigger) infiniteScrollTrigger.style.display = 'none';
                    return;
                }
                
                let startIndex = 0;
                if (append) {
                    startIndex = Math.max(0, visibleItems - ITEMS_PER_BATCH);
                }
                
                const dataToShow = data.slice(startIndex, visibleItems);
                
                let rowsHtml = '';
                dataToShow.forEach(company => {
                    let balanceBlock = `<strong>Dati par ${company.balance_year || '-'} gadu</strong><br>`;
                    
                    if (company.total_assets) balanceBlock += `Aktīvi kopā: ${formatNumber(company.total_assets)} €<br>`;
                    if (company.total_non_current_assets) balanceBlock += `Ilgtermiņa ieguldījumi: ${formatNumber(company.total_non_current_assets)} €<br>`;
                    if (company.total_current_assets) balanceBlock += `Apgrozāmie līdzekļi: ${formatNumber(company.total_current_assets)} €<br>`;
                    
                    if (company.equity) balanceBlock += `Pašu kapitāls: <span style="color: #2e7d32;">${formatNumber(company.equity)} €</span><br>`;
                    if (company.provisions) balanceBlock += `Uzkrājumi: ${formatNumber(company.provisions)} €<br>`;
                    if (company.non_current_liabilities) balanceBlock += `Ilgtermiņa saistības: <span style="color: #c62828;">${formatNumber(company.non_current_liabilities)} €</span><br>`;
                    if (company.current_liabilities) balanceBlock += `Īstermiņa saistības: <span style="color: #c62828;">${formatNumber(company.current_liabilities)} €</span><br>`;

                    const mult = parseFloat(company.valuation_multiplier) || 3;

                    rowsHtml += `
                        <tr>
                            <td data-label="Nosaukums">
                                <div class="company-name-block">
                                    <div class="company-name">${escapeHtml(company.name)}</div>
                                    <div class="company-regcode"><a href="/${encodeURIComponent(company.regcode)}" target="_blank" rel="noopener">${escapeHtml(company.regcode)}</a></div>
                                    <div style="font-size: 11px; color: #888;">Dibināts: ${company.founded_year || '—'}</div>
                                    ${company.tax_rating ? `<div style="font-size: 11px; color: #888;">Nodokļu maksātāja reitings: <strong>${escapeHtml(company.tax_rating)}</strong></div>` : ''}
                                </div>
                            </td>
                            <td data-label="Lokācija">${escapeHtml(company.location)}</td>
                            <td data-label="Nozare">${escapeHtml(company.nace_description) || 'Nav norādīts'}</td>

                            <td data-label="Apgrozījums">
                                <strong>${formatNumber(company.turnover)} €</strong>
                                ${renderHistory(company.history_turnover, true)}
                            </td>

                            <td data-label="Darbinieki">
                                <strong>${formatNumber(company.employees)}</strong>
                                ${renderHistory(company.history_employees, false)}
                            </td>

                            <td data-label="Peļņa">
                                <strong>${formatNumber(company.profit)} €</strong>
                                ${renderHistory(company.history_profit, true)}
                            </td>
                            
                            <td data-label="Bilance" class="balance-cell">${balanceBlock}</td>
                            
                            <td data-label="Cena (Teorēt.)" class="balance-cell">
                                <strong style="font-size: 15px; color: #2d3748;">${(company.theoretical_price > 0) ? formatNumber(company.theoretical_price) + ' €' : '—'}</strong><br>
                                <span style="font-size: 11px; color: #777;">
                                    ${(company.profit * mult >= company.equity) ? '✅' : '❌'} Peļņa x${mult}: ${formatNumber(company.profit * mult)} €<br>
                                    ${(company.equity > company.profit * mult) ? '✅' : '❌'} Kapitāls: ${formatNumber(company.equity)} €
                                </span>
                            </td>
                            
                            <td data-label="Stāvoklis">${generateNeonGridHtml(company.financial_health_string)}</td>
                            <td><a href="/${encodeURIComponent(company.regcode)}" target="_blank" rel="noopener" class="profile-link" title="Skatīt profilu">&#8599;</a></td>
                        </tr>
                    `;
                });
                
                if (append) {
                    tableBody.insertAdjacentHTML('beforeend', rowsHtml);
                } else {
                    tableBody.innerHTML = rowsHtml;
                }
                
                if (infiniteScrollTrigger && showingCountLabel) {
                    const currentDisplayed = Math.min(visibleItems, data.length);
                    
                    if (data.length > visibleItems) {
                        infiniteScrollTrigger.style.display = 'block';
                        showingCountLabel.textContent = `Rāda ${currentDisplayed} no ${data.length} uzņēmumiem`;
                    } else {
                        infiniteScrollTrigger.style.display = 'none';
                    }
                }
            }

            function updateSortIcons() {
                document.querySelectorAll('th.sortable').forEach(th => {
                    th.classList.remove('sorted-asc', 'sorted-desc');
                    if (th.dataset.sortKey === currentSort.key) {
                        th.classList.add(currentSort.dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                    }
                });
            }

            document.querySelectorAll('th.sortable').forEach(th => {
                th.addEventListener('click', (e) => {
                    if (e.target.classList.contains('filter-select-box')) return;

                    const newSortKey = th.dataset.sortKey;
                    if (currentSort.key === newSortKey) {
                        currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
                    } else {
                        currentSort.key = newSortKey;
                        // Teksta kolonnām noklusējums A-Z (asc); skaitļiem lielākais pirmais (desc).
                        // Iepriekš 'string' salīdzinājums bija vienmēr false (nav data-sort-type="string"),
                        // tāpēc teksts pirmajā klikšķī nepareizi kārtojās Z-A.
                        currentSort.dir = ((th.dataset.sortType || 'string') === 'string') ? 'asc' : 'desc';
                    }
                    filterAndSortData();
                    renderTable(filteredData);
                    updateSortIcons();
                });
            });

            // Initial render
            filterAndSortData();
            renderTable(filteredData);
            updateSortIcons();
            updateDynamicBadges();
        });
    </script>
</body>
</html>