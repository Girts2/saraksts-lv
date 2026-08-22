<?php
require_once __DIR__ . '/lib/applog.php';
applog_boot('nozare');   // kopīgais notikumu žurnāls (lib/applog.php)
// nozare.php
// VERSIJA 13: veselības virknes tikai no DB (builderis agregē arī sekcijas), servera puses
// lapojoša uzņēmumu tabula, izmests mirušais '0000' kods.

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Iestatām lapas virsrakstu priekš head.php
$pageTitle = "Latvijas Nozaru Pārskats";

// Ceļš uz datubāzi 'nace' apakšmapē
$db_file = __DIR__ . '/nozare/katalogs.sqlite';
$pdo = null;
$nodes = [];
$counts = [];
$data_for_js = [];

try {
    if (!file_exists($db_file)) {
        error_log("nozare.php: trūkst $db_file");
        die("Kļūda: nozaru datubāze pagaidām nav pieejama. Mēģiniet vēlāk.");
    }
    
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. Ielasām visas nozares (ieskaitot 0000, kas tagad ir DB)
    $stmt_nodes = $pdo->query("SELECT code, name, parent_code, level, health_string FROM nace ORDER BY code");
    $nodes_data = $stmt_nodes->fetchAll();
    
    if (empty($nodes_data)) {
        die("Kļūda: nace tabula ('nace') datubāzē ir tukša.");
    }
    
    foreach ($nodes_data as $row) {
        // 'UNDEFINED' arī ir derīgs mezgls (uzņēmumi bez NACE koda VID datos)
        $nodes[$row['code']] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'parent_code' => $row['parent_code'] ?: null,
            'level' => $row['level'] ?: 9,
            'description' => $row['name'],
            'health_string' => $row['health_string'] ?: 'nnnnnnnnnn',
            'children' => [],
            'company_count' => 0, 
            'total_turnover' => 0, 'prev_total_turnover' => 0,
            'total_employees' => 0, 'prev_total_employees' => 0,
            'total_profit' => 0, 'prev_total_profit' => 0,
            'total_net_payroll' => 0, 'prev_total_net_payroll' => 0,
            'total_employees_for_salary' => 0, 'prev_total_employees_for_salary' => 0
        ];
    }

    // 2. Ielasām statistiku (grupētu pēc koda)
    // Tagad izmantojam saglabātās 'prev_' kolonnas precīzākai tendenču noteikšanai
    $stmt_counts = $pdo->query("
        SELECT 
            nace_code_np, 
            COUNT(*) as count, 
            SUM(turnover) as total_turnover, 
            SUM(prev_turnover) as prev_total_turnover,
            
            SUM(employees) as total_employees,
            SUM(prev_employees) as prev_total_employees,

            SUM(profit) as total_profit, 
            SUM(prev_profit) as prev_total_profit,

            SUM(avg_net_salary * employees) as total_net_payroll,
            SUM(prev_salary * prev_employees) as prev_total_net_payroll,
            
            SUM(CASE WHEN avg_net_salary IS NOT NULL THEN employees ELSE 0 END) as total_employees_for_salary,
            SUM(CASE WHEN prev_salary IS NOT NULL THEN prev_employees ELSE 0 END) as prev_total_employees_for_salary

        FROM companies 
        WHERE employees > 0 OR turnover > 0 OR profit != 0
        GROUP BY nace_code_np
    ");
    
    while ($row = $stmt_counts->fetch()) {
        $counts[$row['nace_code_np']] = $row;
    }
    
    // (ŠEIT IEPREKŠ BIJA MANUĀLS 'UNDEFINED' BLOKS - TAS IR IZŅEMTS, JO DATI TAGAD IR MAIN TABULĀS)

    // 3. Būvējam koku
    $tree = [];
    foreach ($nodes as $code => &$node) {
        $parent_code = $node['parent_code'];
        if ($parent_code && isset($nodes[$parent_code])) {
            $nodes[$parent_code]['children'][] = &$node;
        } elseif (!$parent_code) {
            $tree[] = &$node;
        }
    }
    unset($node);

    // 4. Rekursīva skaitīšana un agregācija
    $counted_nodes = [];
    $calculate_counts = function(&$node) use (&$calculate_counts, $counts, &$counted_nodes) {
        if (isset($counted_nodes[$node['code']])) return $node;
        
        $code_np = str_replace('.', '', $node['code']);
        
        // Tiešie dati
        $direct_data = $counts[$code_np] ?? [
            'count' => 0, 
            'total_turnover' => 0, 'prev_total_turnover' => 0,
            'total_employees' => 0, 'prev_total_employees' => 0,
            'total_profit' => 0, 'prev_total_profit' => 0,
            'total_net_payroll' => 0, 'prev_total_net_payroll' => 0,
            'total_employees_for_salary' => 0, 'prev_total_employees_for_salary' => 0
        ];
        
        $node['company_count'] = $direct_data['count'];
        $node['total_turnover'] = $direct_data['total_turnover'];
        $node['prev_total_turnover'] = $direct_data['prev_total_turnover'] ?? 0;
        
        $node['total_employees'] = $direct_data['total_employees'];
        $node['prev_total_employees'] = $direct_data['prev_total_employees'] ?? 0;
        
        $node['total_profit'] = $direct_data['total_profit'];
        $node['prev_total_profit'] = $direct_data['prev_total_profit'] ?? 0;
        
        $node['total_net_payroll'] = $direct_data['total_net_payroll'];
        $node['prev_total_net_payroll'] = $direct_data['prev_total_net_payroll'] ?? 0;
        
        $node['total_employees_for_salary'] = $direct_data['total_employees_for_salary'];
        $node['prev_total_employees_for_salary'] = $direct_data['prev_total_employees_for_salary'] ?? 0;

        // Bērnu dati (ja ir). health_string NEpārrēķinām — builderis to jau agregē pa visiem
        // uzņēmumiem (arī burtu sekcijām); bērnu-mezglu vairākums te dotu citu (sliktāku) rezultātu.
        if (!empty($node['children'])) {
            foreach ($node['children'] as &$child_node) {
                $child_data = $calculate_counts($child_node);

                $node['company_count'] += $child_data['company_count'];

                $node['total_turnover'] += $child_data['total_turnover'];
                $node['prev_total_turnover'] += $child_data['prev_total_turnover'];

                $node['total_employees'] += $child_data['total_employees'];
                $node['prev_total_employees'] += $child_data['prev_total_employees'];

                $node['total_profit'] += $child_data['total_profit'];
                $node['prev_total_profit'] += $child_data['prev_total_profit'];

                $node['total_net_payroll'] += $child_data['total_net_payroll'];
                $node['prev_total_net_payroll'] += $child_data['prev_total_net_payroll'];

                $node['total_employees_for_salary'] += $child_data['total_employees_for_salary'];
                $node['prev_total_employees_for_salary'] += $child_data['prev_total_employees_for_salary'];
            }
        }
        $counted_nodes[$node['code']] = true;
        return $node;
    };

    foreach ($tree as &$root_node) {
        $calculate_counts($root_node);
    }
    unset($root_node);

    // 5. Sagatavojam datus priekš JS
    foreach ($nodes as $code => $node) {
        $avg_net_salary_category = 0;
        $prev_avg_net_salary_category = 0;

        if ($node['total_employees_for_salary'] > 0) {
            $avg_net_salary_category = $node['total_net_payroll'] / $node['total_employees_for_salary'];
        }
        if ($node['prev_total_employees_for_salary'] > 0) {
            $prev_avg_net_salary_category = $node['prev_total_net_payroll'] / $node['prev_total_employees_for_salary'];
        }
        
        // Aprēķinam izmaiņas %
        $calc_change = function($curr, $prev) {
            if ($prev == 0) return null;
            return (($curr - $prev) / abs($prev)) * 100;
        };

        $change_turnover = $calc_change($node['total_turnover'], $node['prev_total_turnover']);
        $change_employees = $calc_change($node['total_employees'], $node['prev_total_employees']);
        $change_profit = $calc_change($node['total_profit'], $node['prev_total_profit']);
        $change_salary = $calc_change($avg_net_salary_category, $prev_avg_net_salary_category);

        $data_for_js[] = [
            'Kods' => $node['code'],
            'Nosaukums' => $node['name'],
            'Vecāka kods' => $node['parent_code'],
            'Līmenis' => $node['level'], 
            'Apraksts' => $node['description'], 
            'VeselibasVirkne' => $node['health_string'],
            'UznemumuSkaits' => $node['company_count'], 
            'KopApgrozijums' => $node['total_turnover'], 'IzmainasApgrozijums' => $change_turnover,
            'KopDarbinieki' => $node['total_employees'], 'IzmainasDarbinieki' => $change_employees,
            'KopPela' => $node['total_profit'], 'IzmainasPela' => $change_profit,
            'VidejaNetoAlga' => $avg_net_salary_category, 'IzmainasAlga' => $change_salary
        ];
    }
    
} catch (Exception $e) {
    error_log('nozare.php: ' . $e->getMessage());
    die("Kļūda datu sagatavošanā. Mēģiniet vēlāk.");
}
$pageDesc = "Informatīvs Latvijas nozaru katalogs. Apskati populārākās, vidējās un retākās industrijas, uzņēmumu finanšu veselību un nodarbinātības rādītājus.";
?>
<?php
ob_start();
?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
      * { box-sizing: border-box; }
      html { background-color: #f0f0f0; }
      body { 
          margin: 0 auto;
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
          padding-top: 80px;
          background-color: #f9f9f9; 
          color: #333;
          max-width: 1881px; 
          min-width: 320px; 
      }
      main { padding: 20px; }
      main h1 { margin-top: 0; }
      #breadcrumbs { margin-bottom: 20px; font-size: 16px; color: #555; padding: 10px; background-color: #fff; border: 1px solid #ddd; border-radius: 8px; }
      #breadcrumbs a { color: #007bff; text-decoration: none; } #breadcrumbs a:hover { text-decoration: underline; }
      #breadcrumbs span.current-page { color: #333; font-weight: bold; }
      #breadcrumbs .separator { margin: 0 8px; color: #888; }
      
      .gallery { 
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(390px, 1fr));
          gap: 20px;
          margin-bottom: 20px;
      }
      
      .industry-card { 
          background-color: #fff;
          border: 1px solid #ddd; 
          border-radius: 8px; 
          box-shadow: 0 4px 8px rgba(0,0,0,0.08); 
          transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; 
          display: flex;
          min-width: 320px; 
      }
      .industry-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.2); }
      
      .main-card-content { width: 340px; border-right: 1px solid #eee; cursor: pointer; }
      .industry-header { position: relative; height: 200px; }
      .industry-header img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px 0 0 0; }
      .description { position: absolute; bottom: 0; left: 0; right: 0; background-color: rgba(0, 0, 0, 0.5); color: white; padding: 10px 10px 25px 10px; margin: 0; font-size: 14px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; max-height: 80px; }
      .card-badge { position: absolute; top: 10px; right: 10px; background-color: #e53e3e; color: white; border-radius: 50%; padding: 4px 8px; font-size: 14px; font-weight: bold; z-index: 10; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5); min-width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
      .card-body { display: flex; align-items: center; justify-content: space-between; margin-top: 15px; border-top: 1px solid #eee; padding: 15px; }
      
      .industry-data { width: auto; display: grid; grid-template-columns: auto 1fr; column-gap: 8px; row-gap: 10px; align-items: center; }
      .data-item-label { font-size: 14px; font-weight: 500; color: #007bff; text-align: right; white-space: nowrap; }
      .data-item-value { font-family: 'Roboto Condensed', sans-serif; font-size: 15px; font-weight: 700; color: #007bff; text-align: left; display: flex; align-items: center; gap: 2px; white-space: nowrap; overflow: hidden; }

      .financial-indicators-side { flex-shrink: 0; margin-left: 15px; cursor: help; }
      .neon-grid-container { width: 60px; height: 60px; border-radius: 8px; display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(3, 1fr); gap: 4px; padding: 5px; transition: box-shadow 0.3s ease-in-out, background-color 0.3s ease-in-out, filter 0.2s ease-in-out; box-sizing: border-box; border: 1px solid #ddd; background-color: rgba(0,0,0,0.02); }
      .financial-indicators-side:hover .neon-grid-container, .company-table .neon-grid-container:hover { filter: brightness(150%); }
      
      .neon-grid-container.frame-g { background-color: rgba(76, 175, 80, 0.1); border-color: #4CAF50; box-shadow: 0 0 8px #4CAF50, 0 0 12px #4CAF50, inset 0 0 5px rgba(76, 175, 80, 0.5); }
      .neon-grid-container.frame-o { background-color: rgba(255, 152, 0, 0.1); border-color: #FF9800; box-shadow: 0 0 8px #FF9800, 0 0 12px #FF9800, inset 0 0 5px rgba(255, 152, 0, 0.5); }
      .neon-grid-container.frame-r { background-color: rgba(244, 67, 54, 0.1); border-color: #F44336; box-shadow: 0 0 8px #F44336, 0 0 12px #F44336, inset 0 0 5px rgba(244, 67, 54, 0.5); }
      .neon-grid-container.frame-b { background-color: rgba(33, 150, 243, 0.1); border-color: #2196F3; box-shadow: 0 0 8px #2196F3, 0 0 12px #2196F3, inset 0 0 5px rgba(33, 150, 243, 0.5); }
      
      .led-wrapper { display: flex; align-items: center; justify-content: center; }
      .led { width: 12px; height: 12px; border-radius: 50%; background-color: #ddd; transition: background-color 0.3s, box-shadow 0.3s; }
      .led.g { background-color: #4CAF50; box-shadow: 0 0 5px #4CAF50; } .led.o { background-color: #FF9800; box-shadow: 0 0 5px #FF9800; }
      .led.r { background-color: #F44336; box-shadow: 0 0 5px #F44336; } .led.b { background-color: #2196F3; box-shadow: 0 0 5px #2196F3; }
      
      .subcategory-preview-pane { width: 70px; display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 10px 0; background-color: #fcfcfc; border-radius: 0 8px 8px 0; }
      .sub-preview-item { position: relative; }
      .sub-preview-img { width: 50px; height: 50px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px; background-color: #f0f0f0; }
      .sub-preview-badge { position: absolute; top: -5px; right: -5px; background-color: #e53e3e; color: white; border-radius: 50%; font-size: 10px; font-weight: bold; padding: 2px 4px; z-index: 10; border: 1px solid white; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
      .sub-preview-more { font-size: 14px; color: #888; font-weight: bold; padding-top: 5px; }
      
      #company-display { background-color: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; overflow: hidden; }
      #company-display h2 { margin-top: 0; color: #2d3748; padding: 20px 20px 0 20px; }
      
      .table-responsive-wrapper { overflow-x: auto; }
      .company-table { width: 100%; border-collapse: collapse; }
      .company-table th, .company-table td { border-bottom: 1px solid #e2e8f0; padding: 12px 20px; text-align: left; white-space: nowrap; vertical-align: middle; }
      .company-table th:first-child, .company-table td:first-child { width: 450px; white-space: normal; vertical-align: top; }
      .company-table th:nth-child(2), .company-table td:nth-child(2) { width: 200px; white-space: normal; }

      .company-name-block { display: flex; flex-direction: column; gap: 2px; }
      .company-name { font-weight: 600; font-size: 15px; color: #2d3748; }
      .company-regcode { font-size: 13px; color: #555; }
      .company-regcode a { color: #007bff; text-decoration: none; } .company-regcode a:hover { text-decoration: underline; }

      .company-table th { background-color: #f7fafc; font-weight: 600; }
      .company-table tr:last-child td { border-bottom: none; }
      .company-table td:nth-child(3), .company-table td:nth-child(5), .company-table td:nth-child(6) { text-align: right; }
      .company-table td:nth-child(4), .company-table td:nth-child(7) { text-align: center; }

      .no-data { color: #718096; padding: 20px; }
      .table-note { color: #718096; padding: 0 20px 10px 20px; font-size: 14px; margin: 0; }
      .load-more-wrap { padding: 15px 20px 20px 20px; text-align: center; }
      /* Virsraksta rinda ar pogu "Latvijas TOP" labajā pusē (Girts 2026-08-22). */
      .nz-titlerow { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; }
      .nz-titlerow h1 { margin: 0; }
      .nz-top-btn { display: inline-block; background: #1a365d; color: #fff; border-radius: 6px; padding: 9px 16px; font-size: 14px; font-weight: 600; text-decoration: none; white-space: nowrap; }
      .nz-top-btn:hover { background: #2c5282; color: #fff; text-decoration: none; }
      .load-more-btn { background: #007bff; color: #fff; border: none; border-radius: 6px; padding: 10px 22px; font-size: 14px; cursor: pointer; }
      .load-more-btn:hover { background: #0066d6; }
      .load-more-btn:disabled { background: #a0aec0; cursor: wait; }
      .load-more-info { color: #718096; font-size: 13px; margin-top: 6px; }
      .company-table th.sortable { cursor: pointer; position: relative; padding-right: 25px; }
      .company-table th.sortable .sort-arrows { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; opacity: 0.3; }
      .company-table th.sortable .arrow-up { width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 5px solid #2d3748; margin-bottom: 2px; }
      .company-table th.sortable .arrow-down { width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 5px solid #2d3748; }
      .company-table th.sortable.sorted-asc .sort-arrows, .company-table th.sortable.sorted-desc .sort-arrows { opacity: 1; }
      .company-table th.sortable.sorted-asc .arrow-up { opacity: 1; } .company-table th.sortable.sorted-asc .arrow-down { opacity: 0.3; }
      .company-table th.sortable.sorted-desc .arrow-down { opacity: 1; } .company-table th.sortable.sorted-desc .arrow-up { opacity: 0.3; }
      .company-table .neon-grid-container { width: 30px; height: 30px; gap: 2px; padding: 3px; margin: 0 auto; cursor: help; }
      .company-table .led { width: 6px; height: 6px; }
      
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
      .led-sample { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; vertical-align: middle; border: 1px solid #ccc; }

      @media (max-width: 767px) {
          .company-table thead { display: none; }
          .company-table, .company-table tbody, .company-table tr, .company-table td { display: block; width: 100% !important; }
          .company-table tr { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; }
          .company-table td { border-bottom: 1px solid #eee; padding-left: 50%; position: relative; text-align: right !important; white-space: normal; min-height: 40px; display: flex; align-items: center; justify-content: flex-end; }
          .company-table tr:last-child td:last-child { border-bottom: none; }
          .company-table td:before { content: attr(data-label); position: absolute; left: 15px; width: calc(50% - 30px); font-weight: bold; text-align: left; white-space: normal; color: #333; font-size: 13px; }
          .company-table td:first-child { padding-left: 15px; text-align: left !important; background-color: #f9f9f9; vertical-align: middle; }
          .company-table td:first-child:before { display: none; }
          .company-table td[data-label="Lokācija"] { vertical-align: middle; }
          .company-table td[data-label="Lokācija"]:before { align-self: center; }
          .company-table td[data-label="Stāvoklis"] { justify-content: center; }
          .company-table td[data-label="Stāvoklis"]:before { align-self: center; }
          .company-table .neon-grid-container { margin: 0; }
          .explanation-list { column-count: 1; }
      }
      
      /* Jauni stili tendencēm */
      .trend-indicator { display: inline-block; font-size: 10px; margin-left: 3px; font-weight: 600; white-space: nowrap; position: relative; top: -4px; }
      .company-table .trend-indicator { font-size: 12px; top: -5px; }
      .trend-up { color: #4CAF50; } /* Zaļš */
      .trend-down { color: #F44336; } /* Sarkans */
      .trend-neutral { color: #999; font-weight: normal; }
    </style>
<?php 
$extraHeadContent = ob_get_clean(); 
?>
<!DOCTYPE html>
<html lang="lv">
<?php include 'registrs/head/head.php'; ?>
<body>
    <?php include 'registrs/header.php'; ?>
  
    <main>
    <div class="nz-titlerow">
        <h1>Latvijas Ekonomikas Nozaru Pārskats</h1>
        <?php // "Latvijas TOP" — lielākie uzņēmumi pa novadiem un pilsētām (top.php, publicēts 2026-08-22). ?>
        <a class="nz-top-btn" href="/top/" title="Lielākie uzņēmumi pēc apgrozījuma, peļņas un darbiniekiem — Latvijā un katrā novadā">Latvijas TOP</a>
    </div>
    <p style="margin-bottom: 25px; font-size: 15px; color: #555;">Katalogs piedāvā padziļinātu informāciju par dažādām nozarēm, rādot to uzņēmumu skaitu, finanšu efektivitāti, un nodarbinātību.</p>
    
    <nav id="breadcrumbs" aria-label="breadcrumb"></nav>
    <div class="gallery"></div>
    <div id="company-display" class="company-list" style="display: none;"></div>
  </main>
  
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

  <?php $footerRich = 'nozare'; include 'registrs/footer/footer.php'; ?>

  <script>
    
    const naceData = <?php echo json_encode($data_for_js, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    // DB būves versija kešu atslēgai — pēc diennakts pārbūves lapošana nejauc vecas/jaunas atbildes
    const dbVersion = <?php echo (int)@filemtime($db_file); ?>;
    
    if (!naceData || !Array.isArray(naceData) || naceData.length === 0) {
         if (document.body) {
            document.body.innerHTML = "<h1>Kļūda: Nevarēja ielādēt datus no PHP.</h1><p>Iespējamais iemesls: PHP kļūda servera pusē (piem., nevar nolasīt .sqlite failu) vai datubāze ir tukša.</p>";
         }
         throw new Error("Datu ielāde no PHP neizdevās. Skripts tiek apturēts.");
    }

    const gallery = document.querySelector('.gallery');
    const companyDisplay = document.getElementById('company-display');
    const breadcrumbsContainer = document.getElementById('breadcrumbs');
    let currentCategory = null;
    // Uzņēmumu tabulas stāvoklis (servera puses kārtošana + lapošana)
    let tableState = null; // { kods, displayName, sort, dir, offset, total, loaded }
    let tableRequestId = 0; // sacensību sargs: novecojušas atbildes tiek izmestas

    // HTML escape visam, kas nāk no datiem (uzņēmumu nosaukumi, lokācijas u.c.)
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g,
        m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

    const symbolToClass = { 'r': 'r', 'o': 'o', 'g': 'g', 'b': 'b', 'n': '' };
    function generateNeonGridHtml(healthString, isExplanation = false) {
        if (!/^[rogbn]{10}$/.test(healthString || '')) healthString = 'nnnnnnnnnn';
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
        return `<div class="neon-grid-container ${frameClass}" data-health-string="${healthString}">${ledsHtml}</div>`;
    }
    
    const modal = document.getElementById('grid-explanation-modal');
    const closeModalBtn = modal ? modal.querySelector('.modal-close') : null;
    const explanationGrid = modal ? document.getElementById('explanation-grid-dynamic') : null;

    function openModal(healthString) {
        if (!explanationGrid) return;
        if (!healthString || healthString.length !== 10) healthString = 'nnnnnnnnnn';
        const { html, frameClass } = generateNeonGridHtml(healthString, true);
        explanationGrid.innerHTML = html;
        explanationGrid.className = 'neon-grid-container ' + frameClass;
        
        const explanationList = modal.querySelector('.explanation-list');
        if (!explanationList) return; 
        explanationList.innerHTML = '';
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
            10: { title: "10. Kopējais risks (Altman Z)", green: "Stabils uzņēmums, zems bankrota risks", orange: "Uzņēmums 'pelēkajā zonā', iespējamas problēmas", red: "Augsts bankrota risks", grey: "Nav datu" }
        };
        
        const symbolToColorKey = { 'r': 'red', 'o': 'orange', 'g': 'green', 'b': 'blue', 'n': 'grey', '': 'grey' };
        const symbolToCssClass = { 'b': 'color-b', 'g': 'color-g', 'o': 'color-o', 'r': 'color-r', 'n': 'color-n' };

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
    
    const fmtNumber = (val) => (val !== null && typeof val !== 'undefined') ? new Intl.NumberFormat('lv-LV', {maximumFractionDigits: 0}).format(val) : 'Nav datu';
    const fmtInt = (val) => (val !== null && typeof val !== 'undefined') ? new Intl.NumberFormat('lv-LV').format(val) : 'Nav datu';
    const fmtNetSalary = (val) => (val !== null && typeof val !== 'undefined' && val > 0) ? new Intl.NumberFormat('lv-LV', {maximumFractionDigits: 0}).format(val) : 'Nav datu';

    // Pret kādu periodu ir izmaiņu %: darbinieki/alga atkarībā no datu avota, finanses vienmēr gads
    const PERIOD_TITLES = { cet: 'Izmaiņas pret iepriekšējo pieejamo ceturksni', gads: 'Izmaiņas pret iepriekšējo gadu' };
    const YEAR_TITLE = 'Izmaiņas pret iepriekšējo gada pārskatu';

    const renderChange = (val, periodTitle) => {
        if (val === null || typeof val === 'undefined') return '<span class="trend-indicator trend-neutral">—</span>';
        const numVal = parseFloat(val);
        if (isNaN(numVal)) return '<span class="trend-indicator trend-neutral">—</span>';

        const t = periodTitle ? ` title="${escapeHtml(periodTitle)}"` : '';
        const formatted = Math.abs(numVal).toFixed(1) + '%';
        if (numVal > 0) return `<span class="trend-indicator trend-up"${t}>▲ ${formatted}</span>`;
        if (numVal < 0) return `<span class="trend-indicator trend-down"${t}>▼ ${formatted}</span>`;
        return `<span class="trend-indicator trend-neutral"${t}>0.0%</span>`;
    };

    function companyRowsHtml(companies) {
        let html = '';
        companies.forEach(c => {
            const empPeriod = PERIOD_TITLES[c.change_period] || '';
            html += `
                <tr>
                    <td data-label="Nosaukums">
                        <div class="company-name-block">
                            <div class="company-name">${escapeHtml(c.name) || '-'}</div>
                            <div class="company-regcode"><a href="/${encodeURIComponent(c.regcode)}" target="_blank" rel="noopener">${escapeHtml(c.regcode) || '-'}</a></div>
                        </div>
                    </td>
                    <td data-label="Lokācija">${escapeHtml(c.location) || '-'}</td>
                    <td data-label="Apgrozījums (EUR)">${fmtNumber(c.turnover)}${renderChange(c.turnover_change, YEAR_TITLE)}</td>
                    <td data-label="Darbinieki">${fmtInt(c.employees)}${renderChange(c.employees_change, empPeriod)}</td>
                    <td data-label="Peļņa (EUR)">${fmtNumber(c.profit)}${renderChange(c.profit_change, YEAR_TITLE)}</td>
                    <td data-label="Vid. Neto Alga (EUR)">${c.salary_hidden ? '***' : fmtNetSalary(c.avg_net_salary)}${renderChange(c.salary_change, empPeriod)}</td>
                    <td data-label="Stāvoklis">${generateNeonGridHtml(c.financial_health_string || 'nnnnnnnnnn')}</td>
                </tr>`;
        });
        return html;
    }

    function updateSortIcons() {
        if (!companyDisplay || !tableState) return;
        companyDisplay.querySelectorAll('th.sortable').forEach(th => {
            th.classList.remove('sorted-asc', 'sorted-desc');
            if (th.dataset.sortKey === tableState.sort) { th.classList.add(tableState.dir === 'asc' ? 'sorted-asc' : 'sorted-desc'); }
        });
    }

    function addSortEventListeners() {
        if (!companyDisplay) return;
        companyDisplay.querySelectorAll('th.sortable').forEach(th => {
            th.addEventListener('click', () => {
                if (!tableState) return;
                const key = th.dataset.sortKey;
                if (tableState.sort === key) { tableState.dir = tableState.dir === 'asc' ? 'desc' : 'asc'; } else {
                    tableState.sort = key;
                    tableState.dir = (key === 'name' || key === 'location') ? 'asc' : 'desc';
                }
                tableState.offset = 0;
                updateSortIcons();
                fetchCompaniesPage('replace');
            });
        });
    }

    function updateLoadMore() {
        if (!companyDisplay || !tableState) return;
        const wrap = companyDisplay.querySelector('.load-more-wrap');
        if (!wrap) return;
        const st = tableState;
        const btn = wrap.querySelector('.load-more-btn');
        const info = wrap.querySelector('.load-more-info');
        if (st.loaded < st.total) {
            wrap.style.display = '';
            if (btn) { btn.disabled = false; btn.textContent = `Ielādēt vēl ${Math.min(st.limit, st.total - st.loaded)}`; }
            if (info) info.textContent = `Rādīti ${st.loaded} no ${st.total}`;
        } else {
            wrap.style.display = 'none';
        }
    }

    function renderTableShell(data) {
        const st = tableState;
        let html = `<h2>${escapeHtml(st.displayName)} (Uzņēmumi: ${fmtInt(data.total)})</h2>`;
        if (st.kods === 'UNDEFINED') {
            html += `<p class="table-note">Šeit apkopoti uzņēmumi, kuriem VID datos (vēl) nav norādīts pamatdarbības NACE kods — pārsvarā jauni uzņēmumi, par kuriem gada dati vēl nav publicēti.</p>`;
        }
        if (data.total > 0) {
            html += `
            <div class="table-responsive-wrapper">
                <table class="company-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort-key="name">Nosaukums <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                            <th class="sortable" data-sort-key="location">Lokācija <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                            <th class="sortable" data-sort-key="turnover">Apgrozījums (EUR) <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                            <th class="sortable" data-sort-key="employees">Darbinieki <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                            <th class="sortable" data-sort-key="profit">Peļņa (EUR) <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                            <th class="sortable" data-sort-key="avg_net_salary">Vid. Neto Alga (EUR) <span class="sort-arrows"><span class="arrow-up"></span><span class="arrow-down"></span></span></th>
                            <th>Stāvoklis</th>
                        </tr>
                    </thead>
                    <tbody>${companyRowsHtml(data.companies)}</tbody>
                </table>
            </div>
            <div class="load-more-wrap" style="display:none"><button class="load-more-btn" type="button">Ielādēt vēl</button><div class="load-more-info"></div></div>
            <p class="table-note">*** — uzņēmumiem ar mazāk nekā 3 darbiniekiem algas aprēķins tiek slēpts privātuma aizsardzībai.</p>`;
        } else {
            html += '<p class="no-data">Šajā kategorijā nav atrasts neviens aktīvs uzņēmums.</p>';
        }
        companyDisplay.innerHTML = html;
        if (data.total > 0) {
            addSortEventListeners();
            updateSortIcons();
            const btn = companyDisplay.querySelector('.load-more-btn');
            if (btn) btn.addEventListener('click', () => {
                if (!tableState) return;
                btn.disabled = true;
                tableState.offset = tableState.loaded;
                fetchCompaniesPage('append');
            });
        }
    }

    function fetchCompaniesPage(mode) {
        const st = tableState;
        if (!st || !companyDisplay) return;
        const reqId = ++tableRequestId; // vecākas atbildes vairs netiks pieņemtas
        const url = `nozare/get_companies.php?kods=${encodeURIComponent(st.kods)}&sort=${encodeURIComponent(st.sort)}&dir=${st.dir}&offset=${st.offset}&v=${dbVersion}`;
        fetch(url)
            .then(response => { if (!response.ok) throw new Error('HTTP ' + response.status); return response.json(); })
            .then(data => {
                if (reqId !== tableRequestId || st !== tableState) return; // novecojusi atbilde
                st.total = data.total;
                st.limit = data.limit;
                if (mode === 'init') {
                    renderTableShell(data);
                } else {
                    const tbody = companyDisplay.querySelector('tbody');
                    if (tbody) {
                        if (mode === 'append') tbody.insertAdjacentHTML('beforeend', companyRowsHtml(data.companies));
                        else tbody.innerHTML = companyRowsHtml(data.companies);
                    }
                }
                st.loaded = st.offset + data.companies.length;
                updateLoadMore();
            })
            .catch(error => {
                if (reqId !== tableRequestId) return;
                console.error('Kļūda, ielādējot uzņēmumu datus:', error);
                companyDisplay.innerHTML = `<h2>Kļūda</h2><p class="no-data">Nevarēja ielādēt datus no servera.</p>`;
            });
    }

    function trackPageView(kods, nosaukums) {
        if (typeof gtag !== 'function') return;
        const ga_id = 'G-XXXXXXXXXX';
        let path = window.location.pathname;
        let title = 'Nozaru Pārskats - Sākums';
        if (kods && nosaukums) {
            if (naceData && Array.isArray(naceData)) {
                const subCategories = naceData.filter(cat => cat['Vecāka kods'] === kods);
                path = `${window.location.pathname}#${kods}`;
                title = `${kods} - ${nosaukums} ${subCategories.length === 0 ? '(Uzņēmumi)' : '(Nozare)'}`;
            }
        }
        
        gtag('config', ga_id, {
            'page_path': path,
            'page_title': title
        });
    }

    function navigateTo(kods, pushHistory = true) {
        if (!naceData || !Array.isArray(naceData) || !gallery || !companyDisplay || !breadcrumbsContainer) {
            console.error("navigateTo tika izsaukta, bet trūkst datu vai HTML elementu.");
            return;
        }

        if (!kods) {
            currentCategory = null;
            tableRequestId++; tableState = null; // atceļ vēl gaidošas tabulas atbildes
            const mainCategories = naceData.filter(cat => cat.Līmenis === 1);
            renderCards(mainCategories);
            renderBreadcrumbs(null);
            gallery.style.display = 'grid';
            companyDisplay.style.display = 'none';
            if (pushHistory) {
                history.pushState({ kods: null }, '', window.location.pathname);
            }
            trackPageView(null, null);
            return;
        }

        const category = naceData.find(c => c.Kods === kods);
        if (!category) {
            navigateTo(null, pushHistory);
            return;
        }

        const subCategories = naceData.filter(cat => cat['Vecāka kods'] === category.Kods);
        // Ja nav apakškategoriju (arī UNDEFINED), rādām tikai uzņēmumu sarakstu
        if (subCategories.length === 0) {
            currentCategory = category.Kods;
            renderBreadcrumbs(category.Kods);
            gallery.style.display = 'none';
            loadCompanyTable(category.Kods, category.Nosaukums);
        } else {
            currentCategory = category.Kods;
            renderCards(subCategories);
            renderBreadcrumbs(category.Kods);
            gallery.style.display = 'grid';

            if (category.Līmenis >= 2) {
                companyDisplay.style.display = 'block';
                loadCompanyTable(category.Kods, category.Nosaukums);
            } else {
                tableRequestId++; tableState = null; // atceļ vēl gaidošas tabulas atbildes
                companyDisplay.style.display = 'none';
            }
        }
        
        if (pushHistory) {
            history.pushState({ kods: kods }, '', '#' + kods);
        }
        trackPageView(kods, category.Nosaukums);
    }

    function loadCompanyTable(kods, nosaukums) {
        if (!companyDisplay) return;
        const displayName = (kods === 'UNDEFINED') ? nosaukums : `${kods} - ${nosaukums}`;
        tableState = { kods, displayName, sort: 'employees', dir: 'desc', offset: 0, total: 0, loaded: 0, limit: 500 };
        companyDisplay.innerHTML = `<h2>${escapeHtml(displayName)} (Uzņēmumi)</h2><p class="no-data">Ielādē datus...</p>`;
        companyDisplay.style.display = 'block';
        fetchCompaniesPage('init');
    }

    function renderBreadcrumbs(kods) {
        if (!breadcrumbsContainer) return;
        breadcrumbsContainer.innerHTML = ''; 
        
        if (!naceData || !Array.isArray(naceData)) return;

        let path = []; let currentKods = kods;
        while (currentKods) { const cat = naceData.find(c => c.Kods === currentKods); if (cat) { path.unshift(cat); currentKods = cat['Vecāka kods']; } else { currentKods = null; } }

        const homeLink = document.createElement('a');
        homeLink.href = '#'; homeLink.textContent = 'Sākums';
        homeLink.addEventListener('click', (e) => { e.preventDefault(); navigateTo(null, true); });
        breadcrumbsContainer.appendChild(homeLink);
        path.forEach(cat => {
            const separator = document.createElement('span'); separator.className = 'separator'; separator.textContent = '>'; breadcrumbsContainer.appendChild(separator);
            const categoryName = cat.Nosaukums || ''; const categoryCode = cat.Kods || ''; const displayText = (categoryCode === 'UNDEFINED') ? categoryName : `${categoryCode} - ${categoryName}`;
            
            if (cat.Kods === kods) { 
                const currentText = document.createElement('span'); currentText.className = 'current-page'; currentText.textContent = displayText; breadcrumbsContainer.appendChild(currentText); 
            } else {
                const parentLink = document.createElement('a'); parentLink.href = '#' + cat.Kods; parentLink.textContent = displayText; parentLink.dataset.kods = cat.Kods;
                parentLink.addEventListener('click', (e) => { e.preventDefault(); navigateTo(e.currentTarget.dataset.kods, true); }); 
                breadcrumbsContainer.appendChild(parentLink);
            }
        });
    }

    function renderCards(categories) {
      if (!gallery) return;
      gallery.innerHTML = ''; gallery.style.display = 'grid';
      categories.sort((a, b) => (a.Kods || '').localeCompare(b.Kods || ''));
      const numFormatter = (val) => new Intl.NumberFormat('lv-LV', {maximumFractionDigits: 0}).format(val || 0);
      // 0/null summa = neviens uzņēmums nav devis datus — rādām 'Nav datu', tāpat kā tabulā
      const fmtCardMoney = (val) => (val !== null && typeof val !== 'undefined' && val !== 0) ? `€ ${numFormatter(val)}` : 'Nav datu';
      const fmtCardSalary = (val) => (val && val > 0) ? `€ ${numFormatter(val)}` : 'Nav datu';
      const CARD_PERIOD = 'Izmaiņas pret iepriekšējo pieejamo periodu';
      categories.forEach(cat => {
        const imageUrl = (cat.Kods === 'UNDEFINED') ? `nozare/nace-foto/x.webp` : `nozare/nace-foto/${cat.Kods.toLowerCase()}.webp`;
        const { UznemumuSkaits, KopApgrozijums, KopDarbinieki, KopPela, VidejaNetoAlga, Apraksts, Nosaukums, VeselibasVirkne, IzmainasApgrozijums, IzmainasDarbinieki, IzmainasPela, IzmainasAlga } = cat;
        const description = escapeHtml(Apraksts || Nosaukums);
        let subHtml = '';
        if (cat.Līmenis < 4 && cat.Kods !== 'UNDEFINED' && naceData && Array.isArray(naceData)) {
            const subCategories = naceData.filter(subCat => subCat['Vecāka kods'] === cat.Kods);
            if (subCategories.length > 0) { // tukšu paneli nerādām
                subCategories.sort((a, b) => (a.Kods || '').localeCompare(b.Kods || ''));
                subHtml = '<div class="subcategory-preview-pane">';
                const previewLimit = 6;
                subCategories.slice(0, previewLimit).forEach(subCat => {
                    const subImageUrl = `nozare/nace-foto/${subCat.Kods.toLowerCase()}.webp`;
                    const subCount = subCat.UznemumuSkaits;
                    subHtml += '<div class="sub-preview-item">'; if (subCount > 0) { subHtml += `<div class="sub-preview-badge">${subCount}</div>`; }
                    subHtml += `<img src="${escapeHtml(subImageUrl)}" class="sub-preview-img" title="${escapeHtml(subCat.Nosaukums || '')} (${escapeHtml(subCat.Kods)})" onerror="this.onerror=null;this.src='nozare/nace-foto/a.webp';">`;
                    subHtml += '</div>';
                });
                if (subCategories.length > previewLimit) { subHtml += `<div class="sub-preview-more">+${subCategories.length - previewLimit}</div>`; }
                subHtml += '</div>';
            }
        }
        const card = document.createElement('div');
        card.className = 'industry-card';

        card.innerHTML = `
          <div class="main-card-content" data-kods="${escapeHtml(cat.Kods)}">
              <div class="industry-header">${UznemumuSkaits > 0 ? `<div class="card-badge">${UznemumuSkaits}</div>` : ''}
                  <img src="${escapeHtml(imageUrl)}"
     				onerror="this.onerror=null;this.src='nozare/nace-foto/a.webp';"
     				alt="Nozares ${escapeHtml(cat.Kods)} attēls: ${description}"
     				title="${description}">
				  <p class="description" title="${description}">${description}</p>
              </div>
              <div class="card-body">
                  <div class="industry-data">
                      <span class="data-item-label" title="Kopējais apgrozījums">Apgrozījums:</span>
                      <span class="data-item-value">${fmtCardMoney(KopApgrozijums)}${renderChange(IzmainasApgrozijums, CARD_PERIOD)}</span>

                      <span class="data-item-label" title="Kopējais darbinieku skaits">Darbinieki:</span>
                      <span class="data-item-value">${numFormatter(KopDarbinieki)}${renderChange(IzmainasDarbinieki, CARD_PERIOD)}</span>

                      <span class="data-item-label" title="Kopējā peļņa">Peļņa:</span>
                      <span class="data-item-value">${fmtCardMoney(KopPela)}${renderChange(IzmainasPela, CARD_PERIOD)}</span>

                      <span class="data-item-label" title="Vidējā neto alga">Vid. alga:</span>
                      <span class="data-item-value">${fmtCardSalary(VidejaNetoAlga)}${renderChange(IzmainasAlga, CARD_PERIOD)}</span>

                  </div>
                  <div class="financial-indicators-side" title="Skaidrojums">${generateNeonGridHtml(VeselibasVirkne)}</div>
              </div>
              <a href="/nozare/${escapeHtml(cat.Kods)}" onclick="event.stopPropagation()"
                 style="display:block; padding:6px 12px 8px; font-size:12.5px; color:#007bff; text-decoration:none;"
                 title="Nozares ${escapeHtml(cat.Kods)} pārskata lapa: top uzņēmumi, apgrozījums, algas">Nozares lapa ar top uzņēmumiem →</a>
          </div>
          ${subHtml}`;
        gallery.appendChild(card);
      });
      gallery.querySelectorAll('.main-card-content').forEach(el => el.addEventListener('click', (e) => {
          if (naceData && Array.isArray(naceData)) {
              handleCardClick(naceData.find(c => c.Kods === e.currentTarget.dataset.kods))
          }
      }));
    }
    
    function handleCardClick(category) {
        if(category) {
            navigateTo(category.Kods, true);
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        if (naceData && Array.isArray(naceData) && naceData.length > 0) {
            const kods = window.location.hash.substring(1); 
            navigateTo(kods, false); 
        }
    });
    window.addEventListener('popstate', (event) => {
        if (naceData && Array.isArray(naceData) && naceData.length > 0) {
            const kods = event.state ? event.state.kods : window.location.hash.substring(1);
            navigateTo(kods, false); 
        }
    });
  </script>
  
  <?php include 'registrs/cookie/cookie.php'; ?>

  </body>
</html>