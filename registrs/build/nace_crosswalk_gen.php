<?php
// Ģenerē galīgo 2.0->2.1 crosswalk: empīriskais (VID 2023->2024, dominants>=90%, n>=5)
// ∪ oficiālā nodaļas-45 korespondence (struktura SEKT_LEGACY_NACE). Izvada PHP + Python.
chdir('/Users/mac/Desktop/GEMINI/_Optimizacija/server');
require_once 'registrs/lib/nace_map.php';
$p = new PDO('sqlite:csv/SQLite/ur_data.db');
$triv = ['?'=>1,''=>1,'0'=>1,'00'=>1,'0000'=>1,'nan'=>1,'None'=>1];
$y23=[];$y24=[];
foreach($p->query("SELECT Registracijas_kods rc,Taksacijas_gads y,Pamatdarbibas_NACE_kods code FROM pdb_nm_komersantu_samaksato_nodoklu_kopsumas_odata WHERE Taksacijas_gads IN ('2023','2024')") as $r){
    $rc=(string)$r['rc']; $c=str_replace('.','',trim((string)$r['code'])); if(isset($triv[$c]))continue;
    if((int)$r['y']===2024)$y24[$rc]=$c; else $y23[$rc]=$c;
}
$obs=[];
foreach($y23 as $rc=>$old){ if(isset(NACE_MAP[$old]))continue; if(!isset($y24[$rc]))continue; $new=$y24[$rc]; if(!isset(NACE_MAP[$new]))continue; $obs[$old][$new]=($obs[$old][$new]??0)+1; }

$map=[];
foreach($obs as $old=>$t){ arsort($t); $tot=array_sum($t); $top=array_key_first($t);
    if($tot>=5 && $t[$top]/$tot>=0.90) $map[$old]=$top; }

// oficiālā nodaļas-45 korespondence (NACE Rev.2 -> Rev.2.1; sk. struktura SEKT_LEGACY_NACE)
$canon45 = ['4511'=>'4781','4519'=>'4781','4531'=>'4782','4532'=>'4782','4540'=>'4783','4520'=>'9531'];
$conflicts=[];
foreach($canon45 as $o=>$n){
    if(isset($map[$o]) && $map[$o]!==$n){ $conflicts[]="$o: empīrisks {$map[$o]} vs oficiāls $n"; }
    if(!isset($map[$o])) $map[$o]=$n; // pievieno, ja empīriskais neaptvēra
}
ksort($map);

// drošība: mērķim jābūt 2.1 kartē, avotam NAV 2.1 kartē
foreach($map as $o=>$n){ if(isset(NACE_MAP[$o]) || !isset(NACE_MAP[$n])) unset($map[$o]); }

echo "Kopā ierakstu: ".count($map)."\n";
if($conflicts){ echo "KONFLIKTI (empīrisks vs oficiāls):\n  ".implode("\n  ",$conflicts)."\n"; } else echo "Konfliktu nav.\n";

// PHP izvade
$php="const NACE_2_0_TO_2_1 = [\n";
foreach($map as $o=>$n) $php.="    '$o' => '$n', // ".NACE_MAP[$n]."\n";
$php.="];\n";
file_put_contents('/private/tmp/claude-501/-Users-mac-Desktop/74e795a5-00a2-4412-830d-31a59de93ae2/scratchpad/xwalk.php.txt',$php);

// Python izvade
$py="REG_NACE_2_0_TO_2_1 = {\n";
foreach($map as $o=>$n) $py.="    '$o': '$n',  # ".NACE_MAP[$n]."\n";
$py.="}\n";
file_put_contents('/private/tmp/claude-501/-Users-mac-Desktop/74e795a5-00a2-4412-830d-31a59de93ae2/scratchpad/xwalk.py.txt',$py);

echo "\n--- pilnā karte ---\n".$php;
