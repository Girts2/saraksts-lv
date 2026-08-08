<?php
// MI paneļa datu ielāde. Jānotiek PIRMS redzamā #ai-response-box HTML:
// $dyn_cache baro servera pusē renderēto pirmo kešoto atbildi, lai tā būtu
// redzama uzreiz pēc lapas atvēršanas un indeksējama meklētājiem.
$ai_cache_dir = $_SERVER['DOCUMENT_ROOT'] . '/registrs/ai_cache';

// Ielasām slēdža uzstādījumus (mi/switch.php)
$switch_file = $_SERVER['DOCUMENT_ROOT'] . '/registrs/mi/switch.php';
$sec_cfg = ['protection_active' => true, 'global_max_limit' => 30];
if (file_exists($switch_file)) {
    $loaded = include($switch_file);
    if (is_array($loaded)) $sec_cfg = array_merge($sec_cfg, $loaded);
}
$is_protection_active = $sec_cfg['protection_active'] ? 'true' : 'false';
$dynamic_captcha_limit = max(2, floor($sec_cfg['global_max_limit'] / 6)); // Noklusētais 30 -> 5

// CAPTCHA jautājumu ģenerēšana servera pusē un šifrēšana, lai paslēptu gudrajiem lietotājiem
$captcha_q_list = [
    ['t'=>'chip', 'q'=>"Kurš no šiem ir Latvijas koks?", 'opts'=>['Kaķis', 'Dators', 'Ozols', 'Mākonis', 'Auto'], 'a'=>'Ozols'],
    ['t'=>'chip', 'q'=>"Norādiet populāru sakņaugu:", 'opts'=>['Ābols', 'Banāns', 'Kartupelis', 'Zemene', 'Apelsīns'], 'a'=>'Kartupelis'],
    ['t'=>'chip', 'q'=>"Kura pilsēta neiederas sarakstā?", 'opts'=>['Rīga', 'Kuldīga', 'Ventspils', 'Parīze', 'Cēsis'], 'a'=>'Parīze'],
    ['t'=>'chip', 'q'=>"Cik ir viens plus trīs (rakstot vārdā)?", 'opts'=>['Viens', 'Divi', 'Četri', 'Pieci', 'Seši'], 'a'=>'Četri'],
    ['t'=>'chip', 'q'=>"Kāds burts alfabētā seko pēc 'A'?", 'opts'=>['Z', 'B', 'K', 'O', 'M'], 'a'=>'B'],
    ['t'=>'emoji', 'q'=>"Uzklikšķini uz Uguns simboliņa:", 'opts'=>['💧', '🌲', '🔥', '☁️', '🍎'], 'a'=>'🔥'],
    ['t'=>'emoji', 'q'=>"Atrodi un uzspied uz Latvijas karoga:", 'opts'=>['🇺🇸', '🇬🇧', '🇱🇻', '🇪🇪', '🇱🇹'], 'a'=>'🇱🇻'],
    ['t'=>'emoji', 'q'=>"Lūdzu norādi, kurš ir Saules simbols:", 'opts'=>['❄️', '🌧️', '🌪️', '☀️', '🌚'], 'a'=>'☀️'],
    ['t'=>'emoji', 'q'=>"Kura no šīm ir Mājas (ēkas) ikona?", 'opts'=>['🚗', '✈️', '🏠', '🚢', '🚲'], 'a'=>'🏠'],
    ['t'=>'emoji', 'q'=>"Drošībai: Atrodi sarkanu Sirdi:", 'opts'=>['🔴', '❤️', '🔺', '🛑', '💢'], 'a'=>'❤️'],
    ['t'=>'radio', 'q'=>"Pabeidziet populāru ogas vārdu: 'Zem...'", 'opts'=>['putns', 'galdi', 'ene', 'auto', 'zivs'], 'a'=>'ene'],
    ['t'=>'radio', 'q'=>"Jūra, kas apskalo Latviju, ir:", 'opts'=>['Melnā', 'Sarkanā', 'Baltijas', 'Ziemeļu', 'Kaspijas'], 'a'=>'Baltijas'],
    ['t'=>'radio', 'q'=>"Lūdzu ieklikšķiniet norādīto ciparu (7):", 'opts'=>['2', '9', '7', '4', '8'], 'a'=>'7'],
    ['t'=>'radio', 'q'=>"Kurš ir lielākais dzīvnieks no šiem:", 'opts'=>['Pele', 'Zilonis', 'Kaķis', 'Govs', 'Suns'], 'a'=>'Zilonis'],
    ['t'=>'radio', 'q'=>"Lūdzu apstipriniet, ka neesat automāts:", 'opts'=>['Esmu Robots', 'Varbūt', 'Neesmu Robots', 'Nezinu', 'Kāpēc prasi?'], 'a'=>'Neesmu Robots']
];
// Kodējam uz ASCII JSON un tad Base64 (tīrs, nesakropļots pārsūtījums)
$obfuscated_captcha_data = base64_encode(json_encode($captcha_q_list));

// Uzzinām kopējo 10 minūšu servera slodzi visā sistēmā, lai iestatītu CAPTCHA startēšanos
$global_ai_load = 0;
$ai_log_file = $ai_cache_dir . '/ai_requests_log.json';
if (file_exists($ai_log_file)) {
    $log_data = json_decode(@file_get_contents($ai_log_file), true) ?: [];
    $curr_t = time();
    foreach ($log_data as $lg) {
        if (($curr_t - $lg['time']) <= 600) { $global_ai_load++; }
    }
}

$cache_file = function_exists('reg_ai_cache_file')
    ? reg_ai_cache_file($ai_cache_dir, (string)($page_data['search_reg_nr'] ?? ''))
    : $ai_cache_dir . '/' . ($page_data['search_reg_nr'] ?? '') . '.json';
$dyn_cache = [];
if (file_exists($cache_file)) {
    $dyn_content = @file_get_contents($cache_file);
    if ($dyn_content) {
        $dyn_cache = json_decode($dyn_content, true) ?: [];
    }
}
$data_version_from_python = (string)($page_data['data_version'] ?? '');
?>
<div class="ai-facts" style="flex-basis: 100%; max-width: none; order: 6;">
    <h2 id="ai_heading">Padziļinātā izpēte</h2>
    <div class="ai-container" style="display: flex; gap: 16px; align-items: flex-start;">

        <div class="ai-controls">
            <div class="ai-controls-header">
                <h3>Analīzes opcijas</h3>
                <button id="ai-menu-toggle">Slēpt ▲</button>
            </div>

            <div id="ai-controls-body">
                <div class="ai-company-badge">
                    <strong><?= h($page_data['companyTitleForHtml'] ?? '') ?></strong><br>
                    <?= h($page_data['nace_code'] ?? '') ?> <?= h($page_data['nace_description'] ?? '') ?>
                    <div style="margin-top: 8px; padding: 4px 6px; background: #dce3ea; color: #34495e; font-size: 10px; border-radius: 4px; display: inline-block; font-weight: 600;">
                        🤖 MI Modelis: gemini-3-flash-preview
                    </div>
                </div>

                <?php foreach ($prompts as $catId => $category): ?>
                    <div class="ai-category">
                        <h4><?= htmlspecialchars($category['title']) ?></h4>
                        <?php $prev_btn_top = null; ?>
                        <?php foreach ($category['buttons'] as $btnId => $btn): ?>
                            <?php if (!empty($btn['hidden'])) continue; // čata gājiens u.c. bez UI pogas ?>
                            <?php // atstarpe starp A/B grupu un numurētajiem punktiem
                            $btn_is_top = !empty($btn['top']);
                            if ($prev_btn_top === true && !$btn_is_top): ?>
                                <div class="ai-btn-top-gap"></div>
                            <?php endif; $prev_btn_top = $btn_is_top; ?>
                            <?php if (!empty($btn['user_input'])): ?>
                                <button class="ai-btn"
                                        disabled
                                        id="btn-<?= $catId ?>-<?= $btnId ?>"
                                        data-user-input="1"
                                        data-panel-title="<?= htmlspecialchars($btn['name']) ?>"
                                        onclick="showUserQuestionForm('<?= $catId ?>', '<?= $btnId ?>', this)">
                                    <?= htmlspecialchars($btn['name']) ?>
                                </button>
                            <?php else: ?>
                                <button class="ai-btn"
                                        disabled
                                        id="btn-<?= $catId ?>-<?= $btnId ?>"
                                        onclick="askAI('<?= $catId ?>', '<?= $btnId ?>', this)">
                                    <?= htmlspecialchars($btn['name']) ?>
                                </button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ai-output-panel">
            <h3 id="ai-panel-title">Mākslīgā intelekta atbilde</h3>

            <div id="ai-captcha-box" class="ai-captcha-container" style="display:none; margin: 20px 0; border: 2px solid #3498db; background: #f0f7ff;">
                <div class="ai-captcha-header" style="font-size: 16px; color: #3498db; display: flex; align-items: center; gap: 8px;">🛡️ Drošības pārbaude</div>
                <p style="font-size: 14px; color: #555; margin-bottom: 15px; margin-top: 5px;">Lai atbloķētu Mākslīgā Intelekta funkcijas šim uzņēmumam, lūdzu, noklikšķiniet pareizo atbildi:</p>
                <div id="ai-captcha-question" class="ai-captcha-q" style="font-size: 16px; font-weight: bold; color: #1a2a3a;">Lādē jautājumu...</div>
                <div id="ai-captcha-options" class="ai-captcha-options" style="margin-bottom: 10px;"></div>
                <div id="ai-captcha-msg" class="ai-captcha-msg" style="font-size: 14px;"></div>
            </div>

            <div class="ai-loader" id="ai-loader">
                <div class="ai-loader-bar"></div>
                <div class="ai-loader-text">Gatavo analīzi... Lūdzu uzgaidi ⏳</div>
            </div>
            
            <div id="ai-response-box">
                <?php
                $first_rendered_key = null;
                if (!empty($dyn_cache)) {
                    foreach ($dyn_cache as $key => $val) {
                        if (isset($val['version']) && $val['version'] === $data_version_from_python) {
                            $first_rendered_key = $key;
                            $raw_text = htmlspecialchars($val['text']);
                            $html_text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $raw_text);
                            $html_text = preg_replace('/### (.*?)\n/s', '<h3>$1</h3>', $html_text);
                            $html_text = preg_replace('/## (.*?)\n/s', '<h2>$1</h2>', $html_text);
                            $html_text = nl2br($html_text);
                            $parts = explode('---', $key);
                            ?>
                            <div id="ai-response">
                               <div class="ai-generated-date" style="font-size: 13px; color: #7f8c8d; margin-bottom: 16px; font-style: italic;">
                                   Pēdējo reizi Mākslīgais Intelekts analizēja šos datus: <?= htmlspecialchars($val['date']) ?>
                               </div>
                               <div class="ai-markdown-content" data-raw-text="<?= $raw_text ?>">
                                   <?= $html_text ?>
                               </div>
                               <div style="text-align: right; margin-top: 20px; border-top: 1px solid #e8ecf0; padding-top: 16px;">
                                   <button class="ai-btn ai-btn-regenerate" disabled style="display: inline-block; width: auto; font-weight: 600;" onclick="forceRegenerateAI('<?= htmlspecialchars($parts[0] ?? '') ?>', '<?= htmlspecialchars($parts[1] ?? '') ?>', this)">🔄 Pārģenerēt analīzi par jaunu</button>
                               </div>
                            </div>
                            <?php
                            break;
                        }
                    }
                }
                
                // Pārbauda "python pregenerated" fall-back, ja ir statiski
                if (!$first_rendered_key && !empty($page_data['pregenerated_ai'])) {
                    foreach ($page_data['pregenerated_ai'] as $key => $val) {
                        $first_rendered_key = $key;
                        $raw_text = htmlspecialchars($val['text']);
                        $html_text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $raw_text);
                        $html_text = nl2br($html_text);
                        $parts = explode('---', $key);
                        ?>
                        <div id="ai-response">
                           <div class="ai-generated-date" style="font-size: 13px; color: #7f8c8d; margin-bottom: 16px; font-style: italic;">
                               Pēdējo reizi Mākslīgais Intelekts analizēja šos datus: <?= htmlspecialchars($val['date']) ?>
                           </div>
                           <div class="ai-markdown-content" data-raw-text="<?= $raw_text ?>">
                               <?= $html_text ?>
                           </div>
                           <div style="text-align: right; margin-top: 20px; border-top: 1px solid #e8ecf0; padding-top: 16px;">
                               <button class="ai-btn ai-btn-regenerate" disabled style="display: inline-block; width: auto; font-weight: 600;" onclick="forceRegenerateAI('<?= htmlspecialchars($parts[0] ?? '') ?>', '<?= htmlspecialchars($parts[1] ?? '') ?>', this)">🔄 Pārģenerēt analīzi par jaunu</button>
                           </div>
                        </div>
                        <?php
                        break;
                    }
                }

                if (!$first_rendered_key):
                ?>
                <div class="ai-placeholder-text" id="ai-placeholder-text-inner">
                    Izvēlies kādu no analīžu opcijām kreisajā pusē, lai sāktu.
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    window.aiFirstRenderedKey = "<?= $first_rendered_key ?: '' ?>";
</script>

<style>
/* CSS priekš AI paneļa, pielāgots no AI/index.php */
.ai-facts {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
    width: 100%;
}
.ai-facts h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 18px;
    color: #1a2a3a;
    border-bottom: 2px solid #f0f4f8;
    padding-bottom: 10px;
}
.ai-container {
    width: 100%;
}
.ai-controls {
    flex: 0 0 280px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e8ecf0;
    padding: 16px;
}
.ai-controls-header {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;
}
.ai-controls-header h3 {
    font-size: 14px; color: #1a2a3a; margin: 0;
}
#ai-menu-toggle {
    display: none; background: #fff; border: 1px solid #dce3ea; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer;
}
.ai-company-badge {
    background: #eef4ff; border: 1px solid #c5d8f5; border-radius: 6px; padding: 10px; font-size: 12px; color: #2c5282; margin-bottom: 14px; word-break: break-word;
}
.ai-category h4 {
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #888; margin: 0 0 6px 0; padding-bottom: 4px; border-bottom: 1px solid #e8ecf0;
}
button.ai-btn {
    display: block; width: 100%; background: #fff; color: #2c3e50; border: 1px solid #dce3ea; padding: 8px 10px; margin-bottom: 6px; border-radius: 6px; cursor: pointer; text-align: left; font-size: 12.5px; transition: all 0.2s;
}
button.ai-btn:hover:not(:disabled) { background: #eef4ff; border-color: #3498db; color: #1a6aad; }
button.ai-btn.active { background: #3498db; border-color: #2980b9; color: #fff; font-weight: 600; }
button.ai-btn:disabled { background: #f8fafc; color: #aab; cursor: not-allowed; }

/* 5. punkts: paplašinātā lietotāja jautājuma forma (atbilžu logā) */
.ai-user-q-form { padding: 4px 0; }
.ai-user-q-title { font-size: 15px; font-weight: 700; color: #1a2a3a; margin-bottom: 12px; }
.ai-user-q-form textarea {
    width: 100%; box-sizing: border-box; resize: vertical; min-height: 96px;
    border: 2px solid #dce3ea; border-radius: 8px; padding: 14px 16px;
    font-size: 19px; font-weight: 700; line-height: 1.45; font-family: inherit;
    color: #1a2a3a; background: #fff;
    overflow-y: hidden; /* augstumu vada qhAutoGrow — bez ritjoslas */
}
.ai-user-q-form textarea::placeholder { font-size: 14px; font-weight: 400; color: #a5b1bd; }
.ai-user-q-form textarea:focus { outline: none; border-color: #3498db; }
.ai-user-q-foot { display: flex; justify-content: space-between; align-items: center; margin: 6px 0 0; min-height: 15px; }
#ai-user-q-msg { font-size: 12px; color: #b91c1c; }
#ai-user-q-count { font-size: 12px; color: #94a3b8; }
/* Jautājumu palīgs (skatpunkts × tēma + gatavie jautājumi) */
/* Atstarpe + smalka līnija starp A/B pogām un numurētajiem punktiem */
.ai-btn-top-gap { border-bottom: 1px solid #dce3ea; margin: 10px 0; }

/* Populārie jautājumi (1. kārta) — sakļaujams čipu bloks virs konstruktora */
.ai-qh-popular { display: flex; flex-wrap: wrap; gap: 6px; }
button.ai-qh-pop {
    padding: 7px 12px; border: 1px solid #bcd8f0; border-radius: 16px; background: #f2f9ff;
    font-size: 13.5px; font-weight: 600; color: #1a2a3a; cursor: pointer; line-height: 1.3;
}
button.ai-qh-pop:hover { background: #3498db; border-color: #2980b9; color: #fff; }
button.ai-qh-surprise { border-style: dashed; border-color: #c7b6e3; background: #faf7ff; }
button.ai-qh-surprise:hover { background: #8e44ad; border-color: #7d3c98; color: #fff; }

.ai-qh { margin-bottom: 12px; }
button.ai-qh-toggle {
    display: flex; justify-content: space-between; align-items: center; gap: 10px;
    width: 100%; text-align: left; background: #f0f7ff; border: 1px solid #9ec5e8;
    border-radius: 8px; padding: 9px 12px; font-size: 12.5px; font-weight: 600; color: #1a6aad;
    cursor: pointer; font-family: inherit;
}
button.ai-qh-toggle:hover { background: #e3f0fc; }
/* Skaidra "Atvērt/Aizvērt" podziņa toggle rindas labajā malā — lai ir acīmredzami,
   ka sadaļa ir izvēršama. */
.ai-qh-open-pill {
    flex-shrink: 0; background: #3498db; color: #fff; border-radius: 12px;
    padding: 3px 11px; font-size: 12px; font-weight: 700; white-space: nowrap;
}
button.ai-qh-toggle:hover .ai-qh-open-pill { background: #2980b9; }
.ai-qh-body { border: 1px solid #dce3ea; border-top: none; border-radius: 0 0 8px 8px; padding: 12px; background: #fbfdff; }
.ai-qh.open button.ai-qh-toggle { border-radius: 8px 8px 0 0; border-style: solid; }
.ai-qh-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #888; margin: 10px 0 6px; }
.ai-qh-label:first-child { margin-top: 0; }
.ai-qh-hint { font-weight: 400; text-transform: none; letter-spacing: 0; color: #a5b1bd; }
.ai-qh-roles, .ai-qh-topics, .ai-qh-goals, .ai-qh-narrows { display: flex; flex-wrap: wrap; gap: 6px; }
button.ai-qh-role, button.ai-qh-topic, button.ai-qh-goal, button.ai-qh-narrow {
    background: #fff; border: 1px solid #dce3ea; border-radius: 16px; padding: 5px 11px;
    font-size: 12px; color: #2c3e50; cursor: pointer; transition: all .15s; font-family: inherit;
}
button.ai-qh-role:hover, button.ai-qh-topic:hover, button.ai-qh-goal:hover, button.ai-qh-narrow:hover { border-color: #3498db; background: #f6faff; }
button.ai-qh-role.selected { background: #3498db; border-color: #2980b9; color: #fff; font-weight: 600; }
button.ai-qh-goal { border-color: #ddd0ec; background: #faf7fd; font-weight: 600; }
button.ai-qh-goal:hover { border-color: #8e44ad; background: #f3ebfa; }
button.ai-qh-goal.selected { background: #8e44ad; border-color: #7d3c98; color: #fff; }
button.ai-qh-narrow { border-color: #f0dcc3; background: #fdf9f2; }
button.ai-qh-narrow:hover { border-color: #e67e22; background: #fdf2e3; }
button.ai-qh-narrow.selected { background: #e67e22; border-color: #ca6f1e; color: #fff; font-weight: 600; }
button.ai-qh-topic { border-color: #cfe3d4; background: #f6fbf7; }
button.ai-qh-topic:hover { border-color: #27ae60; background: #eaf7ee; }
.ai-qh-curated { display: flex; flex-direction: column; gap: 6px; }
button.ai-qh-question {
    text-align: left; background: #fff; border: 1px solid #e3d9c6; border-left: 3px solid #e67e22;
    border-radius: 6px; padding: 7px 10px; font-size: 12px; color: #4a4237; cursor: pointer;
    transition: all .15s; font-family: inherit; line-height: 1.4;
}
button.ai-qh-question:hover { background: #fdf6ec; border-color: #e67e22; }

/* Diagnozes turpinājuma jautājumu čipi ("Ko jautāt tālāk" atbildes beigās) */
.ai-followup-chips { display: flex; flex-direction: column; gap: 8px; margin: 10px 0 4px; }
button.ai-followup-chip {
    text-align: left; background: #f0f7ff; border: 1px solid #9ec5e8; border-left: 4px solid #3498db;
    border-radius: 8px; padding: 10px 14px; font-size: 13.5px; font-weight: 600; color: #1a6aad;
    cursor: pointer; transition: all .15s; font-family: inherit; line-height: 1.45;
}
button.ai-followup-chip::before { content: '➜ '; }
button.ai-followup-chip:hover { background: #e3f0fc; border-color: #3498db; transform: translateX(2px); }

/* Sarunas (čata) pavediens zem diagnozes */
#ai-chat-thread { margin-top: 18px; border-top: 2px solid #e8ecf0; padding-top: 14px; display: flex; flex-direction: column; gap: 10px; }
.ai-chat-q {
    align-self: flex-end; max-width: 85%; background: #3498db; color: #fff;
    padding: 10px 14px; border-radius: 14px 14px 4px 14px; font-size: 14px; font-weight: 600; line-height: 1.4;
}
.ai-chat-a {
    align-self: stretch; background: #f8fafc; border: 1px solid #e8ecf0;
    border-radius: 14px 14px 14px 4px; padding: 12px 16px; font-size: 14px; line-height: 1.6; color: #2d3748;
}
.ai-chat-a h2, .ai-chat-a h3 { font-size: 14px; color: #1a2a3a; margin: 0.8em 0 0.3em; }
.ai-chat-typing { color: #888; font-style: italic; }
/* "Domā" indikators: trīs pulsējoši punkti + laika atskaite, lai ģenerēšanas
   laikā redzams, ka process dzīvs un aptuveni cik ilgi vēl (tipiski ~15 s). */
.ai-typing-dot {
    display: inline-block; width: 5px; height: 5px; margin: 0 1px; border-radius: 50%;
    background: #94a3b8; vertical-align: middle; animation: aiTypingPulse 1.2s ease-in-out infinite;
}
.ai-typing-dot:nth-child(2) { animation-delay: 0.2s; }
.ai-typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes aiTypingPulse { 0%, 60%, 100% { opacity: 0.25; transform: scale(0.85); } 30% { opacity: 1; transform: scale(1.15); } }
.ai-typing-count { font-style: normal; color: #64748b; font-variant-numeric: tabular-nums; }
#ai-chat-inputrow { display: flex; gap: 8px; margin-top: 4px; }
#ai-chat-input {
    flex: 1; border: 2px solid #dce3ea; border-radius: 8px; padding: 10px 12px;
    font-size: 14px; font-family: inherit; resize: none; color: #1a2a3a;
}
#ai-chat-input:focus { outline: none; border-color: #3498db; }
#ai-chat-send { margin: 0; }
#ai-chat-footer { display: flex; align-items: center; gap: 8px; margin-top: 2px; flex-wrap: wrap; }
#ai-chat-counter { font-size: 11.5px; color: #94a3b8; }
#ai-chat-counter.limit { color: #b91c1c; font-weight: 600; }
button.ai-chat-tool {
    background: #fff; border: 1px solid #dce3ea; border-radius: 6px; padding: 5px 10px;
    font-size: 12px; color: #2c3e50; cursor: pointer; font-family: inherit; transition: all .15s;
}
button.ai-chat-tool:hover { border-color: #3498db; background: #f6faff; }

.ai-user-q-styles-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #888; margin: 16px 0 8px; }
.ai-user-q-styles { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 8px; }
button.ai-style-chip {
    text-align: left; background: #fff; border: 1px solid #dce3ea; border-radius: 8px;
    padding: 8px 10px; cursor: pointer; transition: all .15s; font-family: inherit;
}
button.ai-style-chip:hover { border-color: #3498db; background: #f6faff; }
button.ai-style-chip.selected { border-color: #2980b9; background: #eef4ff; box-shadow: 0 0 0 1px #2980b9 inset; }
.ai-style-name { display: block; font-size: 12.5px; font-weight: 700; color: #1a2a3a; }
.ai-style-desc { display: block; font-size: 11px; color: #7f8c8d; margin-top: 2px; }
.ai-user-q-submit-row { margin-top: 16px; text-align: right; }
button.ai-user-q-submit {
    display: inline-block; width: auto; text-align: center; font-weight: 700;
    font-size: 14px; padding: 10px 18px; background: #3498db; border-color: #2980b9; color: #fff;
}
button.ai-user-q-submit:hover:not(:disabled) { background: #2980b9; border-color: #1a6aad; color: #fff; }

.ai-output-panel {
    flex: 1; min-width: 0; background: #fff; border-radius: 8px; border: 1px solid #e8ecf0; padding: 20px; min-height: 400px; display: flex; flex-direction: column;
}
.ai-output-panel h3 {
    font-size: 16px; margin: 0 0 16px 0; padding-bottom: 10px; border-bottom: 1px solid #f0f4f8;
}
.ai-loader { display: none; padding: 20px 0; }
.ai-loader-bar { height: 3px; background: linear-gradient(90deg, #3498db, #9b59b6, #3498db); background-size: 200% 100%; border-radius: 3px; animation: shimmer 1.5s infinite linear; }
.ai-loader-text { font-size: 13px; color: #888; margin-top: 8px; text-align: center; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

#ai-response-box { flex: 1; overflow-y: auto; color: #2d3748; font-size: 14px; line-height: 1.6; }
.ai-placeholder-text { color: #aab; font-style: italic; text-align: center; padding: 40px 0; }

/* Markdown Styles inside AI panel */
#ai-response h1, #ai-response h2, #ai-response h3 { color: #1a2a3a; margin-top: 1.2em; }
#ai-response h2 { font-size: 15px; border-bottom: 1px solid #e8ecf0; padding-bottom: 4px; }
#ai-response table { border-collapse: collapse; width: 100%; margin: 1em 0; }
#ai-response th, #ai-response td { padding: 6px 10px; border: 1px solid #dce3ea; text-align: left; }
#ai-response th { background: #f8fafc; }
#ai-response blockquote { border-left: 3px solid #3498db; margin: 0; padding: 4px 12px; background: #f0f7ff; }
.ai-prompt-details { margin-bottom: 16px; background: #f8fafc; border: 1px solid #dce3ea; border-left: 4px solid #3498db; padding: 10px; border-radius: 4px; }
.ai-prompt-details summary { cursor: pointer; font-size: 12px; font-weight: 600; color: #3498db; }
.ai-prompt-details pre { font-size: 11px; white-space: pre-wrap; margin-top: 10px; max-height: 200px; overflow-y: auto; background: #fff; padding: 8px; border: 1px solid #dce3ea; }
.ai-quote-box { background: #f8fafc; border-left: 4px solid #3498db; padding: 16px 20px; margin: 20px 0; position: relative; display: flex; flex-direction: column; }
.ai-quote-text { font-size: 16px; font-style: italic; margin: 0; padding-right: 30px; }
.ai-quote-author { font-size: 13px; font-weight: 600; color: #7f8c8d; text-align: right; margin-top: 10px; }
.ai-spinner { width: 18px; height: 18px; border: 2px solid #dce3ea; border-top: 2px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; position: absolute; top: 16px; right: 16px; }
@keyframes spin { 100% { transform: rotate(360deg); } }
@media (max-width: 768px) {
    .ai-container { flex-direction: column; }
    .ai-controls { width: 100%; flex: none; }
    #ai-menu-toggle { display: block; }
    #ai-controls-body.collapsed { display: none; }
}
.ai-captcha-container { background: #f8fafc; border: 1px solid #dce3ea; border-radius: 6px; padding: 16px; margin-bottom: 20px; transition: opacity 0.3s; }
.ai-captcha-header { font-size: 13px; font-weight: 600; color: #1a2a3a; margin-bottom: 8px; }
.ai-captcha-q { font-size: 14px; color: #34495e; margin-bottom: 12px; }
.ai-captcha-options { display: flex; flex-wrap: wrap; gap: 6px; }
.ai-captcha-btn { border: 1px solid #cbd5e1; background: #fff; padding: 6px 12px; border-radius: 16px; font-size: 13px; cursor: pointer; transition: 0.2s; color:#334155;}
.ai-captcha-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
.ai-captcha-btn.wrong { background: #fee2e2; border-color: #ef4444; color: #b91c1c; }
.ai-captcha-btn.correct { background: #dcfce3; border-color: #22c55e; color: #15803d; }
.ai-captcha-emoji-btn { font-size: 24px; padding: 6px 10px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; transition: 0.2s; }
.ai-captcha-emoji-btn:hover { background: #f1f5f9; transform: scale(1.1); }
.ai-captcha-msg { font-size: 12px; margin-top: 12px; font-weight: 600; text-align: center; height: 15px; }
</style>

<?php
// 5. punkta paplašinātā jautājuma forma. Tā dzīvo paslēptā turētājā; JS to
// PĀRVIETO (appendChild) uz atbilžu logu un pirms ģenerācijas atpakaļ — tāpēc
// ievadītais teksts un izvēlētais stils saglabājas starp rādīšanas reizēm.
$user_q_cat = null; $user_q_btn = null;
foreach ($prompts as $uq_cid => $uq_cat) {
    foreach ($uq_cat['buttons'] as $uq_bid => $uq_b) {
        if (!empty($uq_b['user_input']) && empty($uq_b['hidden'])) { $user_q_cat = $uq_cid; $user_q_btn = $uq_bid; break 2; }
    }
}
?>
<?php if ($user_q_cat !== null && !empty($ai_answer_styles)): ?>
<div id="ai-user-q-holder" style="display:none;">
    <div id="ai-user-q-form" class="ai-user-q-form">
        <div class="ai-user-q-title">Uzdod savu jautājumu par: <?= h($page_data['companyTitleForHtml'] ?? '') ?></div>

        <?php if (!empty($ai_question_helper['popular'])): ?>
        <div class="ai-qh" id="ai-pop-wrap">
            <button type="button" class="ai-qh-toggle" onclick="toggleQHSection('ai-pop-body','ai-pop-wrap','ai-pop-pill')">
                <span>🔥 Ko cilvēki jautā visbiežāk — gatavie jautājumi</span>
                <span class="ai-qh-open-pill" id="ai-pop-pill">Atvērt ▾</span>
            </button>
            <div class="ai-qh-body" id="ai-pop-body" style="display:none;">
                <div class="ai-qh-popular" id="ai-qh-popular">
                    <?php foreach ($ai_question_helper['popular'] as $qh_pid => $qh_p): ?>
                    <button type="button" class="ai-qh-pop" data-q="<?= h($qh_p['question']) ?>"
                            onclick="pickPopularQ(this)"><?= h($qh_p['name']) ?></button>
                    <?php endforeach; ?>
                    <button type="button" class="ai-qh-pop ai-qh-surprise" onclick="surpriseQuestion()"
                            title="Nejauša skatpunkta un mērķu kombinācija — negaidītam, plašam jautājumam">🎲 Pārsteidz mani</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($ai_question_helper['roles']) && !empty($ai_question_helper['topics'])): ?>
        <div class="ai-qh" id="ai-qh-wrap">
            <button type="button" class="ai-qh-toggle" id="ai-qh-toggle" onclick="toggleQHSection('ai-qh-body','ai-qh-wrap','ai-qh-pill')">
                <span>🔬 Saliec detalizētu jautājumu pats: skatpunkts → mērķis → precizējums</span>
                <span class="ai-qh-open-pill" id="ai-qh-pill">Atvērt ▾</span>
            </button>
            <div class="ai-qh-body" id="ai-qh-body" style="display:none;">
                <div class="ai-qh-label">1. Kas jautā? <span class="ai-qh-hint">(izvēlies skatpunktu — nav obligāti)</span></div>
                <div class="ai-qh-roles" id="ai-qh-roles">
                    <?php foreach ($ai_question_helper['roles'] as $qh_rid => $qh_r): ?>
                    <button type="button" class="ai-qh-role" data-role="<?= h($qh_rid) ?>"
                            data-prefix="<?= h($qh_r['prefix']) ?>"
                            onclick="selectQHRole(this)"><?= h($qh_r['name']) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="ai-qh-label">2. Ko gribi panākt? <span class="ai-qh-hint">(mērķis — pielāgojas skatpunktam; var atzīmēt vairākus, katrs paplašina jautājumu)</span></div>
                <div class="ai-qh-goals" id="ai-qh-goals"></div>
                <div class="ai-qh-label" id="ai-qh-narrows-label" style="display:none;">3. Precizē jautājumu <span class="ai-qh-hint">(nav obligāti — var atzīmēt vairākus, katrs pieliek fokusu)</span></div>
                <div class="ai-qh-narrows" id="ai-qh-narrows" style="display:none;"></div>
                <div class="ai-qh-label" id="ai-qh-curated-label" style="display:none;"></div>
                <div class="ai-qh-curated" id="ai-qh-curated"></div>
                <div class="ai-qh-label">Citas tēmas <span class="ai-qh-hint">(brīvā kombinācija ar jebkuru skatpunktu — klikšķis ieliek laukā)</span></div>
                <div class="ai-qh-topics" id="ai-qh-topics">
                    <?php foreach ($ai_question_helper['topics'] as $qh_tid => $qh_t): ?>
                    <button type="button" class="ai-qh-topic" data-q="<?= h($qh_t['question']) ?>"
                            onclick="pickQHTopic(this)"><?= h($qh_t['name']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <textarea id="ai-user-q-input" rows="3" maxlength="1000"
                  placeholder="Piemēram: Vai šim uzņēmumam būtu droši piegādāt preces ar pēcapmaksu 30 dienās?"></textarea>
        <div class="ai-user-q-foot">
            <span id="ai-user-q-msg"></span>
            <span id="ai-user-q-count">0/1000</span>
        </div>
        <div class="ai-user-q-submit-row">
            <button class="ai-btn ai-user-q-submit" id="ai-user-q-send"
                    onclick="submitUserQuestion('<?= h($user_q_cat) ?>', '<?= h($user_q_btn) ?>')">Uzdot jautājumu ➤</button>
        </div>
        <div class="ai-user-q-styles-label">Atbildes stils</div>
        <div class="ai-user-q-styles" id="ai-user-q-styles">
            <?php $uq_first = true; foreach ($ai_answer_styles as $uq_sid => $uq_st): ?>
            <button type="button" class="ai-style-chip<?= $uq_first ? ' selected' : '' ?>"
                    data-style="<?= h($uq_sid) ?>" onclick="selectAIStyle(this)">
                <span class="ai-style-name"><?= h($uq_st['name']) ?></span>
                <span class="ai-style-desc"><?= h($uq_st['desc']) ?></span>
            </button>
            <?php $uq_first = false; endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="ai-pregenerated-content" style="display:none;">
   <?php /* $dyn_cache un pārējie mainīgie ielādēti faila augšā */ ?>
   <?php foreach ($dyn_cache as $key => $val): ?>
       <?php if (isset($val['version']) && $val['version'] === $data_version_from_python): ?>
           <div id="pregen-<?php echo htmlspecialchars($key); ?>">
               <div class="ai-generated-date" style="font-size: 13px; color: #7f8c8d; margin-bottom: 16px; font-style: italic;">
                   Pēdējo reizi Mākslīgais Intelekts analizēja šos datus: <?php echo htmlspecialchars($val['date']); ?>
               </div>

               <div class="ai-markdown-content" data-raw-text="<?php echo htmlspecialchars($val['text']); ?>"></div>
               <div style="text-align: right; margin-top: 20px; border-top: 1px solid #e8ecf0; padding-top: 16px;">
                   <button class="ai-btn ai-btn-regenerate" disabled style="display: inline-block; width: auto; font-weight: 600;" onclick="forceRegenerateAI('<?php $parts = explode('---', $key); echo htmlspecialchars($parts[0] ?? ''); ?>', '<?php echo htmlspecialchars($parts[1] ?? ''); ?>', this)">🔄 Pārģenerēt analīzi par jaunu</button>
               </div>
           </div>
       <?php endif; ?>
   <?php endforeach; ?>

<?php
   // pregenerated_ai (Python statiskais fallback) — mūsu konveijerā vienmēr tukšs,
   // bet portējam faithful, lai uzvedība saglabātos, ja to kādreiz aizpildīs.
   $pregen_ai = $page_data['pregenerated_ai'] ?? null;
   if (is_array($pregen_ai)):
       foreach ($pregen_ai as $key => $val):
           if (!isset($dyn_cache[$key]) || !isset($dyn_cache[$key]['version']) || $dyn_cache[$key]['version'] !== $data_version_from_python):
               $kp = explode('---', (string)$key);
   ?>
      <div id="pregen-<?= h($key) ?>">
          <div class="ai-generated-date" style="font-size: 13px; color: #7f8c8d; margin-bottom: 16px; font-style: italic;">
              Pēdējo reizi Mākslīgais Intelekts analizēja šos datus: <?= h($val['date'] ?? '') ?>
          </div>

          <div class="ai-markdown-content" data-raw-text="<?= h($val['text'] ?? '') ?>"></div>
          <div style="text-align: right; margin-top: 20px; border-top: 1px solid #e8ecf0; padding-top: 16px;">
              <button class="ai-btn ai-btn-regenerate" disabled style="display: inline-block; width: auto; font-weight: 600;" onclick="forceRegenerateAI('<?= h($kp[0] ?? '') ?>', '<?= h($kp[1] ?? '') ?>', this)">🔄 Pārģenerēt analīzi par jaunu</button>
          </div>
      </div>
   <?php
           endif;
       endforeach;
   endif;
   ?>
</div>

<script>
    const aiQuotes = [
    { text: "Bilance ir uzņēmuma medicīniskā karte – tā parāda veselību, nevis tikai pašsajūtu.", author: "Vorens Bafets" },
  { text: "Aktīvi un pasīvi vienmēr ir līdzsvarā – dabā un grāmatvedībā.", author: "Pīters Drukers" },
  { text: "Bilance neatceras solījumus. Tā atceras tikai faktus.", author: "Čārlijs Mangers" },
  { text: "Peļņas un zaudējumu aprēķins sniedz atbildi uz vienu jautājumu: vai tu esi problēmu risinātājs vai to radītājs?", author: "Džeks Velčs" },
  { text: "Ieņēmumi slēpj kļūdas. Zaudējumi tās atklāj.", author: "Pīters Drukers" },
  { text: "Peļņas aprēķins ir stāsts. Bet kurš to uzrakstīja – un kāpēc?", author: "Bendžamins Grehems" },
  { text: "Uzņēmums var izdzīvot bez peļņas. Bez naudas plūsmas – nē.", author: "Harolds Džinīns" },
  { text: "Nauda plūst kā asinis – ja apstājas plūsma, apstājas arī uzņēmums.", author: "Maikls Gerbers" },
  { text: "Naudas plūsma ir patiesība. Viss pārējais ir interpretācija.", author: "Alfrēds Rapaports" },
  { text: "Pašu kapitāls ir tas, kas paliek, kad visi citi ir saņēmuši savu daļu.", author: "Bendžamins Grehems" },
  { text: "Kapitāls nav tikai nauda – tā ir uzticība, kas pārvērsta skaitļos.", author: "Filips Kotlers" },
  { text: "Uzņēmuma vērtība nav tā aktīvos – tā ir tā spējā tos pārvērst nākotnē.", author: "Čārlijs Mangers" },
  { text: "Gada pārskats ir spogulis – vadītāji bieži skatās tajā un redz to, ko vēlas redzēt.", author: "Vorens Bafets" },
  { text: "Ja gada pārskatu nevar saprast parastais cilvēks, tas ir rakstīts, lai slēptu, nevis atklātu.", author: "Džons Bogls" },
  { text: "Labākais gada pārskats ir tas, par kuru nākamgad nav jātaisnojas.", author: "Džeks Velčs" },
  { text: "Apgrozījums ir iedomība, peļņa ir veselais saprāts, bet nauda – realitāte.", author: "Senais biznesa princips" },
  { text: "Lieli ieņēmumi slēpj daudzus grēkus – līdz brīdim, kad vairs neslēpj.", author: "Džeks Velčs" },
  { text: "Neviens uzņēmums nav izputējis pārāk lielu ieņēmumu dēļ. Bet daudzi gan – tāpēc, ka tos nepārvaldīja.", author: "Pīters Drukers" },
  { text: "Bruto peļņa parāda, vai tavam produktam ir vērtība. Neto peļņa parāda, vai tev ir disciplīna.", author: "Maikls Porters" },
  { text: "Ja bruto marža ir zema, neviena izmaksu samazināšana neglābs uzņēmumu.", author: "Harolds Džinīns" },
  { text: "Ražošanas efektivitāte sākas ar vienu jautājumu: cik izmaksā radīt to, ko pārdodam?", author: "Henrijs Fords" },
  { text: "Neto peļņa ir tas, ko uzņēmums ir patiesi nopelnījis. Viss pārējais ir tas, ko tas ir iztērējis.", author: "Bendžamins Grehems" },
  { text: "Neto peļņa ir kā rezultāts olimpiskajā finišā – svarīgs ir tikai tas, kas paliek beigās.", author: "Vorens Bafets" },
  { text: "Uzņēmums ar nulles neto peļņu nav ne veiksmīgs, ne neveiksmīgs – tas balansē uz robežas.", author: "Pīters Drukers" },
  { text: "EBITDA ir kā spidometrs bez degvielas rādītāja – tas informē, bet nepasaka visu.", author: "Vorens Bafets" },
  { text: "EBITDA var izskatīties labi uz papīra, bet sāpīgi – reālajā dzīvē.", author: "Čārlijs Mangers" },
  { text: "Ja vadītājs piemin tikai EBITDA, jautā, kur palika pārējais.", author: "Harolds Džinīns" },
  { text: "Marža nav greznība – tā ir skābeklis. Bez tās uzņēmums smok.", author: "Maikls Porters" },
  { text: "Zema marža ir brīdinājums. Negatīva marža ir diagnoze.", author: "Pīters Drukers" },
  { text: "Labāk pārdot mazāk ar labu maržu, nekā daudz ar sliktu.", author: "Endrū Kārnegijs" },
  { text: "Izmaksas ir vienīgā lieta biznesā, ko vari kontrolēt simtprocentīgi.", author: "Džeks Velčs" },
  { text: "Katrs ietaupītais dolārs ir nopelnīts dolārs.", author: "Bendžamins Franklins" },
  { text: "Izdevumi aug paši no sevis. Peļņa – tikai ar piepūli.", author: "Henrijs Fords" },
  { text: "Kontrolē izmaksas labos laikos – tad sliktajos laikos tev būs izvēles iespējas.", author: "Sems Voltons" },
  { text: "Aktīvs ir aktīvs tikai tad, kad tas rada vērtību – pretējā gadījumā tas ir slogs.", author: "Pīters Drukers" },
  { text: "Uzņēmuma spēks neslēpjas tā aktīvu sarakstā – tas slēpjas spējā tos izmantot.", author: "Čārlijs Mangers" },
  { text: "Nopirkt aktīvu ir viegli. To pārvaldīt – tas ir darbs.", author: "Henrijs Fords" },
  { text: "Saistības nav ienaidnieks – nekontrolētas saistības gan.", author: "Bendžamins Grehems" },
  { text: "Katrs pasīvs ir nākotnes rēķins, ko esi parakstījis šodien.", author: "Pīters Drukers" },
  { text: "Ja uzņēmums nepārvalda savas saistības, tās sāk pārvaldīt uzņēmumu.", author: "Džeks Velčs" },
  { text: "Īstermiņa saistības pārbauda likviditāti. Ilgtermiņa saistības pārbauda rakstura stiprumu.", author: "Vorens Bafets" },
  { text: "Uzņēmumi neizput lielu parādu dēļ – tie izput tādu parādu dēļ, kurus nespēj savlaicīgi atmaksāt.", author: "Harolds Džinīns" },
  { text: "Ilgtermiņa domāšana nozīmē atcerēties to, ko apsolīji pirms desmit gadiem.", author: "Džeks Velčs" },
  { text: "Parāds ir labs kalps, bet slikts saimnieks.", author: "Frensiss Bekons" },
  { text: "Parāds paātrina labu lēmumu atdevi un pārvērš katastrofā sliktos.", author: "Čārlijs Mangers" },
  { text: "Uzņēmums ar pārāk lielu parādu nastu ir kā akrobāts bez drošības tīkla – izskatās iespaidīgi, līdz brīdim, kad krīt.", author: "Vorens Bafets" },
  { text: "Aizņemies tikai tad, ja zini, kā atmaksāsi, nevis tad, kad tikai ceri, ka varēsi atmaksāt.", author: "Bendžamins Franklins" },
  { text: "Amortizācija ir atgādinājums, ka viss, ko nopērc šodien, rīt būs mazāk vērts.", author: "Pīters Drukers" },
  { text: "Nolietojums ir reāls izdevums – to ignorēt ir kā ignorēt pulksteņa tikšķēšanu.", author: "Vorens Bafets" },
  { text: "Uzņēmums, kas neaprēķina nolietojumu, pārdod savu nākotni par šodienas peļņu.", author: "Čārlijs Mangers" },
  { text: "Brīvā naudas plūsma ir skābeklis – uzņēmums bez tās dzīvs ir tikai šķietami.", author: "Vorens Bafets" },
  { text: "Peļņu var radīt uz papīra. Brīvo naudas plūsmu – tikai reālajā dzīvē.", author: "Čārlijs Mangers" },
  { text: "Uzņēmuma īstā vērtība nav meklējama bilancē – tā slēpjas naudā, ko tas spēj radīt brīvi un pastāvīgi.", author: "Alfrēds Rapaports" },
  { text: "Likviditāte ir kā gaiss – tu par to nedomā, kamēr tās nesāk pietrūkt.", author: "Džeks Velčs" },
  { text: "Pirmais uzņēmuma pienākums ir izdzīvot, un izdzīvošana prasa likviditāti.", author: "Pīters Drukers" },
  { text: "Tirgus var kļūt nelikvīds tieši tad, kad tev to visvairāk vajag.", author: "Alans Grīnspens" },
  { text: "Uzņēmums ar labu peļņu, bet sliktu likviditāti ir kā bagāts cilvēks bez skaidras naudas makā.", author: "Harolds Džinīns" },
  { text: "Maksātspēja nav tikai skaitlis – tā ir uzticamība, kas izteikta skaitļos.", author: "Bendžamins Grehems" },
  { text: "Uzņēmums bez peļņas var izdzīvot gadiem. Bez maksātspējas – tikai nedēļas.", author: "Pīters Drukers" },
  { text: "Maksātspējas zaudēšana nav vienreizējs notikums – tas ir process, kas ticis ignorēts pārāk ilgi.", author: "Čārlijs Mangers" },
  { text: "Apgrozāmais kapitāls ir uzņēmuma asinsrite – lēna plūsma nozīmē lēnu nāvi.", author: "Henrijs Fords" },
  { text: "Pārāk maz apgrozāmā kapitāla aptur izaugsmi. Pārāk liels tā apjoms rada izšķērdību.", author: "Maikls Porters" },
  { text: "Efektīva apgrozāmā kapitāla pārvaldīšana ir atšķirība starp uzņēmumu, kas aug, un uzņēmumu, kas nīkuļo.", author: "Sems Voltons" },
  { text: "Nepietiekams apgrozāmais kapitāls ir kā auto ar tukšu degvielas tvertni – izskatās gatavs braukt, bet uz priekšu netiks.", author: "Džeks Velčs" },
  { text: "Rentabilitāte nav mērķis – tas ir pierādījums, ka dari kaut ko pareizi.", author: "Pīters Drukers" },
  { text: "Uzņēmums bez rentabilitātes ir projekts ar termiņu, kuru neviens nezina.", author: "Maikls Porters" },
  { text: "Īstermiņa rentabilitāte, kas iznīcina ilgtermiņa vērtību, nav panākumi – tā ir lēna pašiznīcināšanās.", author: "Čārlijs Mangers" },
  { text: "ROE parāda nevis to, cik daudz esi nopelnījis, bet gan to, cik gudri esi izmantojis to, kas tev bija.", author: "Vorens Bafets" },
  { text: "Augsts ROE bez parādiem ir īsts mākslas darbs. Augsts ROE ar parādiem ir triks.", author: "Čārlijs Mangers" },
  { text: "Akcionārus neinteresē apgrozījums – viņus interesē, ko uzņēmums dara ar viņu naudu.", author: "Bendžamins Grehems" },
  { text: "ROA atklāj vienu lietu: vai tavi aktīvi strādā, vai tikai stāv dīkstāvē.", author: "Pīters Drukers" },
  { text: "Zems ROA nozīmē vienu no divām lietām – vai nu ir pārāk daudz aktīvu, vai pārāk maz rezultātu.", author: "Džeks Velčs" },
  { text: "Labākais uzņēmums ir tas, kas rada vislielāko vērtību ar vismazākajiem aktīviem.", author: "Vorens Bafets" },
  { text: "Uzņēmuma vērtējums ir tirgus cerību spogulis – un cerības bieži vien melo.", author: "Bendžamins Grehems" },
  { text: "Pārmaksāšana par uzņēmumu nozīmē, ka tirgus tic tā nākotnei. Zema cena – ka pircējs no tās baidās.", author: "Čārlijs Mangers" },
  { text: "Nopirkt uzņēmumu lēti nav liela gudrība. Gudrība ir saprast, kāpēc citi to novērtē tik zemu.", author: "Vorens Bafets" },
  { text: "Augsta parāda un kapitāla attiecība ir svira – tā paātrina gan panākumus, gan krahu.", author: "Čārlijs Mangers" },
  { text: "Uzņēmums ar pārāk lielu parādu pret kapitālu nedzīvo – tas izdzīvo.", author: "Harolds Džinīns" },
  { text: "Optimālā parāda un kapitāla attiecība ir tāda, kas ļauj naktīs mierīgi gulēt.", author: "Vorens Bafets" },
  { text: "Likviditātes koeficients ir uzņēmuma temperatūra – normāla vērtība nenozīmē veselību, bet rādītāja kritums brīdina par drudzi.", author: "Pīters Drukers" },
  { text: "Koeficients, kas ir zem viena, nav tikai skaitlis – tas ir signāls.", author: "Bendžamins Grehems" },
  { text: "Uzņēmums ar labu likviditātes koeficientu var atļauties kļūdīties. Uzņēmums bez tāda – nē.", author: "Džeks Velčs" },
  { text: "Likviditātes koeficients nepasaka, vai uzņēmums ir labs – tas pasaka, vai uzņēmums ir dzīvs.", author: "Harolds Džinīns" },
  { text: "Risks nav tas, kas notiek. Risks ir tas, ko tu nebiji gaidījis.", author: "Vorens Bafets" },
  { text: "Katrs finanšu lēmums ir likme. Gudrs investors zina, ko liek uz spēles.", author: "Bendžamins Grehems" },
  { text: "Finanšu risks nekad nepazūd – tas tikai pāriet no tiem, kas to redz, pie tiem, kas to neredz.", author: "Čārlijs Mangers" },
  { text: "Lielākais finanšu risks ir pārliecība, ka riska nav.", author: "Alans Grīnspens" },
  { text: "Operacionālie riski ir ikdienišķi – tieši tāpēc tos ignorē, līdz tie kļūst katastrofāli.", author: "Pīters Drukers" },
  { text: "Sistēma, kurai nav kļūmju, ir sistēma, kuras kļūmes vēl nav atrastas.", author: "Edvards Demings" },
  { text: "Cilvēciskā kļūda nav cēlonis – tā ir simptoms sistēmai, kas cilvēkam neļauj kļūdīties pareizi.", author: "Edvards Demings" },
  { text: "Operacionālie riski nemaksā uzreiz – tie krājas procentos.", author: "Džeks Velčs" },
  { text: "Iekšējā kontrole nav neuzticēšanās – tā ir cieņa pret cilvēka spēju kļūdīties.", author: "Pīters Drukers" },
  { text: "Uzņēmums bez iekšējās kontroles nav brīvs – tas ir neaizsargāts.", author: "Džeks Velčs" },
  { text: "Labākā kontrole ir tā, kuru neviens nepamana, jo viss darbojas pareizi.", author: "Edvards Demings" },
  { text: "Kontroles trūkums nav uzticēšanās zīme – tā ir vadītāja nolaidības zīme.", author: "Harolds Džinīns" },
  { text: "Audits nav sods – tas ir spogulis, kurā uzņēmums redz sevi tādu, kāds tas ir.", author: "Bendžamins Grehems" },
  { text: "Uzņēmums, kas baidās no audita, jau zina, ko auditors atradīs.", author: "Vorens Bafets" },
  { text: "Auditors neuzdod jaunus jautājumus – viņš atrod atbildes uz jautājumiem, kurus neviens negribēja uzdot.", author: "Čārlijs Mangers" },
  { text: "Regulārs audits ir kā profilaktiskā apskate – labāk atklāt problēmu agri nekā vēlu.", author: "Pīters Drukers" },
  { text: "Atbilstība nav birokrātija – tas ir minimums, ko sabiedrība prasa no uzņēmuma.", author: "Pīters Drukers" },
  { text: "Uzņēmums, kas apiet noteikumus, neuzvar – tas aizņemas laiku no nākotnes.", author: "Čārlijs Mangers" },
  { text: "Atbilstības izmaksas ir augstas. Neatbilstības izmaksas ir vēl augstākas.", author: "Džeks Velčs" },
  { text: "Likumu ievērošana nav konkurences priekšrocība – tā ir ieejas biļete spēlē.", author: "Maikls Porters" }    ];


    let aiQuoteSequence = [];
    let currentQuoteIndex = 0;

    function initQuotes() {
        try {
            const savedSeq = sessionStorage.getItem('aiQuoteSeq');
            const savedIdx = sessionStorage.getItem('aiQuoteIdx');
            if (savedSeq) aiQuoteSequence = JSON.parse(savedSeq);
            if (savedIdx) currentQuoteIndex = parseInt(savedIdx, 10);
        } catch(e) {}

        if (!aiQuoteSequence || aiQuoteSequence.length !== aiQuotes.length || currentQuoteIndex >= aiQuotes.length) {
            aiQuoteSequence = Array.from({length: aiQuotes.length}, (_, i) => i);
            for (let i = aiQuoteSequence.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [aiQuoteSequence[i], aiQuoteSequence[j]] = [aiQuoteSequence[j], aiQuoteSequence[i]];
            }
            currentQuoteIndex = 0;
            saveQuotesState();
        }
    }

    function saveQuotesState() {
        try {
            sessionStorage.setItem('aiQuoteSeq', JSON.stringify(aiQuoteSequence));
            sessionStorage.setItem('aiQuoteIdx', currentQuoteIndex.toString());
        } catch(e) {}
    }

    function getRandomQuote() {
        if (!aiQuoteSequence || !aiQuoteSequence.length) initQuotes();
        
        const q = aiQuotes[aiQuoteSequence[currentQuoteIndex]];
        currentQuoteIndex++;
        
        if (currentQuoteIndex >= aiQuotes.length) {
            currentQuoteIndex = 0;
            for (let i = aiQuoteSequence.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [aiQuoteSequence[i], aiQuoteSequence[j]] = [aiQuoteSequence[j], aiQuoteSequence[i]];
            }
        }
        
        saveQuotesState();
        return q;
    }
    
    initQuotes();

    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('ai-menu-toggle');
        const body = document.getElementById('ai-controls-body');
        if (toggle) {
            toggle.addEventListener('click', () => {
                body.classList.toggle('collapsed');
                toggle.textContent = body.classList.contains('collapsed') ? 'Rādīt opcijas ▼' : 'Slēpt ▲';
            });
        }

        // 5. punkta jautājuma lauks: simbolu skaitītājs + kļūdas ziņas dzēšana rakstot.
        const userQTa = document.getElementById('ai-user-q-input');
        if (userQTa) {
            userQTa.addEventListener('input', () => {
                const cnt = document.getElementById('ai-user-q-count');
                if (cnt) cnt.textContent = userQTa.value.length + '/1000';
                const msg = document.getElementById('ai-user-q-msg');
                if (msg && msg.textContent) msg.textContent = '';
                qhAutoGrow(userQTa); // lauks aug līdzi saturam
            });
        }

        const allBtns = document.querySelectorAll('.ai-btn');
        let firstPregenBtn = null;
        
        allBtns.forEach(btn => {
            if (btn.classList.contains('ai-btn-regenerate')) {
                btn.disabled = true;
                setTimeout(() => { btn.disabled = false; }, 3000);
                return;
            }
            
            const btnIdParts = btn.id.replace('btn-', '').split('-');
            if (btnIdParts.length >= 2) {
                const cacheKey = btnIdParts[0] + '---' + btnIdParts[1];
                if (document.getElementById('pregen-' + cacheKey)) {
                    btn.disabled = false;
                    if (!firstPregenBtn) firstPregenBtn = {cat: btnIdParts[0], btn: btnIdParts[1], el: btn};
                }
            }
        });
        
        const initPregenState = () => {
            if (firstPregenBtn && !window.aiFirstRenderedKey) {
                askAI(firstPregenBtn.cat, firstPregenBtn.btn, firstPregenBtn.el);
            } else if (window.aiFirstRenderedKey && firstPregenBtn) {
                // Activate button visually if it was rendered serverside
                const parts = window.aiFirstRenderedKey.split('---');
                const btn = document.getElementById('btn-' + parts[0] + '-' + parts[1]);
                if(btn) { activeAIBtn = btn; btn.classList.add('active'); document.getElementById('ai-panel-title').textContent = btn.textContent.trim(); }

                // Convert PHP markdown to JS marked just in case for perfect formatting
                const rawTextDiv = document.querySelector('#ai-response-box .ai-markdown-content');
                if (rawTextDiv) {
                     rawTextDiv.innerHTML = mdToSafeHtml(rawTextDiv.getAttribute('data-raw-text'));
                     renderFollowupChips(document.getElementById('ai-response-box'));
                }
            }
        };
        if (typeof marked === 'undefined') {
            const script = document.createElement('script');
            script.src = '/registrs/assets/js/lib/marked.min.js';
            script.onload = initPregenState;
            // Arī bez marked (404/tīkla kļūme) SSR saturs jāaktivizē — mdToSafeHtml
            // tad izmanto iebūvēto rezerves pārveidotāju.
            script.onerror = initPregenState;
            document.head.appendChild(script);
        } else {
            initPregenState();
        }
        
        const captchaRawData = "<?= $obfuscated_captcha_data ?>";
        const captchaQ = JSON.parse(atob(captchaRawData));

        window.captchaSolved = false;
        let needsCaptcha = false;

        window.initCaptcha = function() {
            const box = document.getElementById('ai-captcha-box');
            if (!box) return;
            
            const qv = captchaQ[Math.floor(Math.random() * captchaQ.length)];
            let so = [...qv.opts].sort(() => Math.random() - 0.5);
            
            const qType = qv.t;
            const qQuestion = qv.q;
            const expectedAns = btoa(unescape(encodeURIComponent(qv.a)));
            
            document.getElementById('ai-captcha-question').textContent = qQuestion;
            const oBox = document.getElementById('ai-captcha-options');
            oBox.innerHTML = '';
            oBox.style.flexDirection = qType === 'radio' ? 'column' : 'row';
            
            const msg = document.getElementById('ai-captcha-msg');
            msg.textContent = '';
            
            so.forEach(opt => {
                const el = document.createElement('label');
                el.style.margin = '0';
                if (qType === 'radio') {
                    el.style.display = 'flex'; el.style.alignItems = 'center'; el.style.gap = '8px'; el.style.fontSize = '14px'; el.style.cursor = 'pointer';
                    el.innerHTML = `<input type="radio" name="captchar" value="${opt}" style="margin:0;"> ${opt}`;
                } else {
                    const btn = document.createElement('button');
                    btn.className = qType === 'emoji' ? 'ai-captcha-emoji-btn' : 'ai-captcha-btn';
                    btn.textContent = opt;
                    el.appendChild(btn);
                }
                
                el.addEventListener(qType==='radio'?'change':'click', (e) => {
                    if (window.captchaSolved) return;
                    oBox.style.pointerEvents = 'none';
                    msg.style.color = '#3b82f6';
                    msg.textContent = 'Pārbauda atbildi... ⏳';
                    
                    setTimeout(() => {
                        const isCorrect = (btoa(unescape(encodeURIComponent(opt))) === expectedAns);
                        if (isCorrect) {
                            msg.style.color = '#15803d'; msg.textContent = '✅ Pārbaude izieta veiksmīgi!';
                            if(qType !== 'radio') el.firstChild.classList.add('correct');
                            window.captchaSolved = true;
                            setTimeout(() => {
                                box.style.opacity = 0;
                                setTimeout(() => {
                                    box.style.display = 'none';
                                    const pht = document.getElementById('ai-placeholder-text-inner');
                                    if (pht) pht.style.display = 'block';
                                }, 300);
                                document.querySelectorAll('.ai-btn').forEach(b => { if(!b.classList.contains('ai-btn-regenerate')) b.disabled = false; });
                                // CAPTCHA pārtrauktā ģenerācija — izpildām automātiski, lai
                                // lietotājam nav vēlreiz jāspiež poga (jautājums+stils jau saglabāti).
                                if (window.aiPendingAsk) {
                                    const p = window.aiPendingAsk;
                                    window.aiPendingAsk = null;
                                    if (p.chatQ && typeof sendChatMessage === 'function') {
                                        sendChatMessage(p.chatQ); // pārtrauktais čata gājiens
                                    } else {
                                        // Pārģenerēšanas pogām nav sava id — atkāpjamies uz īsto
                                        // kreisās puses pogu (tāpat kā forceRegenerateAI).
                                        const pBtn = (p.btnElId ? document.getElementById(p.btnElId) : null)
                                            || document.getElementById('btn-' + p.catId + '-' + p.btnId);
                                        if (pBtn && typeof askAI === 'function') askAI(p.catId, p.btnId, pBtn, p.forceRefresh);
                                    }
                                }
                            }, 800);
                        } else {
                            msg.style.color = '#b91c1c'; msg.textContent = '❌ Nepareizi! Ģenerēju jaunu uzdevumu...';
                            if(qType !== 'radio') el.firstChild.classList.add('wrong');
                            setTimeout(() => { oBox.style.pointerEvents = 'auto'; window.initCaptcha(); }, 1500);
                        }
                    }, 1500); // 1.5 seconds delay as requested!
                });
                oBox.appendChild(el);
            });
        }

        setTimeout(() => {
            allBtns.forEach(b => {
                const parts = b.id.replace('btn-', '').split('-');
                let isCached = false;
                if (parts.length >= 2) {
                    if (document.getElementById('pregen-' + parts[0] + '---' + parts[1])) isCached = true;
                }
                // Priviliģējam iepriekš atbildētās pogas – CAPTCHA uz tām neattiecas.
                if (isCached && !b.classList.contains('ai-btn-regenerate')) {
                    b.disabled = false;
                } else if (!b.classList.contains('ai-btn-regenerate')) {
                    needsCaptcha = true; // Mums vēl ir neģenerēti punkti.
                }
            });
            
            window.dynamicServerLoad = <?php echo $global_ai_load; ?>;
            window.isProtectionActive = <?php echo $is_protection_active; ?>;
            window.captchaThresholdLimit = <?php echo $dynamic_captcha_limit; ?>;
            
            if (needsCaptcha && window.isProtectionActive && window.dynamicServerLoad > window.captchaThresholdLimit) {
                document.getElementById('ai-captcha-box').style.display = 'block';
                const pht = document.getElementById('ai-placeholder-text-inner');
                if (pht) pht.style.display = 'none';
                window.initCaptcha();
            } else if (needsCaptcha) {
                // Ja slodze ir normāla, lapas sākšanās brīdī pogas ir vaļā
                // Mēs NIESTATAM "captchaSolved = true", lai mēs varētu pieķert liekus klikšķus ilgākam laikam lapā.
                document.querySelectorAll('.ai-btn').forEach(b => { if(!b.classList.contains('ai-btn-regenerate')) b.disabled = false; });
            }
        }, 800);
    });

    let currentAIStream = null;
    let activeAIBtn = null;

    function forceRegenerateAI(catId, btnId, btnEl) {
        const realBtn = document.getElementById('btn-' + catId + '-' + btnId);
        askAI(catId, btnId, realBtn || btnEl, true);
    }

    // 5. punkts: paplašinātā jautājuma forma dzīvo #ai-user-q-holder un tiek
    // PĀRVIETOTA uz atbilžu logu un atpakaļ (saglabājas teksts un stils).
    // Pirms jebkuras box.innerHTML maiņas forma JĀNOGLABĀ atpakaļ turētājā,
    // citādi innerHTML to iznīcinātu.
    function stashUserQForm() {
        const form = document.getElementById('ai-user-q-form');
        const holder = document.getElementById('ai-user-q-holder');
        if (form && holder && form.parentNode !== holder) holder.appendChild(form);
    }

    function showUserQuestionForm(catId, btnId, btnEl) {
        if (currentAIStream) { currentAIStream.close(); currentAIStream = null; }
        if (window.aiTimerInterval) clearInterval(window.aiTimerInterval);
        if (activeAIBtn) activeAIBtn.classList.remove('active');
        activeAIBtn = btnEl;
        btnEl.classList.add('active');

        document.getElementById('ai-loader').style.display = 'none';
        document.getElementById('ai-panel-title').textContent = (btnEl.dataset && btnEl.dataset.panelTitle) || btnEl.textContent.trim();

        const box = document.getElementById('ai-response-box');
        const form = document.getElementById('ai-user-q-form');
        box.innerHTML = '';
        if (form) {
            box.appendChild(form);
            const ta = document.getElementById('ai-user-q-input');
            if (ta) { qhAutoGrow(ta); ta.focus(); } // saglabātais teksts var būt garš
        }

        if (window.innerWidth <= 768) {
            document.getElementById('ai-controls-body').classList.add('collapsed');
            document.getElementById('ai-menu-toggle').textContent = 'Rādīt opcijas ▼';
            setTimeout(() => document.querySelector('.ai-output-panel').scrollIntoView({ behavior: 'smooth' }), 100);
        }
    }

    function selectAIStyle(chipEl) {
        document.querySelectorAll('#ai-user-q-styles .ai-style-chip').forEach(c => c.classList.remove('selected'));
        chipEl.classList.add('selected');
    }

    // ==== Jautājumu palīgs: kaskāde skatpunkts → mērķis → precizējums ====
    // Birku modelis: mērķiem ir 'roles' (kuriem skatpunktiem tos rādīt; 'generic'
    // rāda bez skatpunkta), precizējumiem — 'goals'. Katrs izvēles solis pārraksta
    // jautājumu laukā arvien detalizētāku (prefikss + mērķa kodols + precizējums).
    <?php
    $qh_export = ['roles' => [], 'goals' => $ai_question_helper['goals'] ?? [], 'narrows' => $ai_question_helper['narrows'] ?? []];
    foreach (($ai_question_helper['roles'] ?? []) as $qh_rid => $qh_r) {
        $qh_export['roles'][$qh_rid] = [
            'prefix'    => $qh_r['prefix'] ?? '',
            'base'      => $ai_question_helper['role_base'][$qh_rid] ?? '',
            'questions' => $qh_r['questions'] ?? [],
        ];
    }
    ?>
    const aiQH = <?= json_encode($qh_export, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    // Vairākizvēle: goals un narrows ir masīvi klikšķu secībā — katrs atzīmētais
    // punkts paplašina salikto jautājumu.
    const qhState = { role: null, goals: [], narrows: [] };

    // Vienotais sakļaujamo palīgu bloku slēdzis (populārie jautājumi + konstruktors).
    // Abas kārtas pēc noklusējuma ir savērstas — lietotājs sākumā redz tikai
    // ievades lauku, sūtīšanas pogu un atbildes stilus.
    function toggleQHSection(bodyId, wrapId, pillId) {
        const body = document.getElementById(bodyId);
        if (!body) return;
        const open = body.style.display === 'none';
        body.style.display = open ? 'block' : 'none';
        const wrap = wrapId ? document.getElementById(wrapId) : null;
        if (wrap) wrap.classList.toggle('open', open);
        const pill = pillId ? document.getElementById(pillId) : null;
        if (pill) pill.textContent = open ? 'Aizvērt ▴' : 'Atvērt ▾';
    }

    // 1. kārta: populārais jautājums — gatavs teksts uzreiz laukā.
    function pickPopularQ(el) {
        setUserQText(el.dataset.q);
    }

    // "🎲 Pārsteidz mani": nejaušs skatpunkts + 2 dažādi tam derīgi mērķi no
    // konstruktora kataloga → negaidīti plaša jautājuma kombinācija. Atkārtots
    // klikšķis izloza citu. qhState neaiztiek (tāpat kā tēmas un gatavie jautājumi).
    function surpriseQuestion() {
        const roleIds = Object.keys(aiQH.roles || {});
        if (!roleIds.length) return;
        const rid = roleIds[Math.floor(Math.random() * roleIds.length)];
        const r = aiQH.roles[rid];
        const gids = Object.keys(aiQH.goals || {}).filter(id => {
            const g = aiQH.goals[id];
            return (g.roles || []).indexOf(rid) !== -1 || !!g.generic;
        });
        if (!gids.length) return;
        const pick = [];
        while (pick.length < 2 && pick.length < gids.length) {
            const id = gids[Math.floor(Math.random() * gids.length)];
            if (pick.indexOf(id) === -1) pick.push(id);
        }
        setUserQText(r.prefix + ' ' + pick.map(id => aiQH.goals[id].q).join(' '));
    }

    // Lauks aug līdzi saturam, lai viss jautājums ir redzams bez ritināšanas.
    function qhAutoGrow(ta) {
        if (!ta) return;
        ta.style.height = 'auto';
        if (ta.scrollHeight) ta.style.height = (ta.scrollHeight + 2) + 'px';
    }

    // Ieliek tekstu ievades laukā (rediģējams) un atjauno skaitītāju + augstumu.
    function setUserQText(q) {
        const ta = document.getElementById('ai-user-q-input');
        if (!ta) return;
        ta.value = q.length > 1000 ? q.slice(0, 1000) : q;
        ta.dispatchEvent(new Event('input'));
        ta.focus();
    }

    // Saliek jautājumu no visām atzīmētajām izvēlēm un ieliek laukā.
    function qhComposeText() {
        const r = qhState.role ? aiQH.roles[qhState.role] : null;
        const parts = qhState.goals.map(id => aiQH.goals[id].q)
            .concat(qhState.narrows.map(id => aiQH.narrows[id].clause));
        let core = parts.join(' ');
        if (!core && r) core = r.base;
        setUserQText(r && core ? r.prefix + ' ' + core : core);
    }

    function qhRenderGoals() {
        const wrap = document.getElementById('ai-qh-goals');
        if (!wrap) return;
        wrap.innerHTML = '';
        Object.keys(aiQH.goals).forEach(id => {
            const g = aiQH.goals[id];
            const show = qhState.role ? (g.roles || []).indexOf(qhState.role) !== -1 : !!g.generic;
            if (!show) return;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'ai-qh-goal' + (qhState.goals.indexOf(id) !== -1 ? ' selected' : '');
            b.textContent = g.name;
            b.addEventListener('click', () => qhSelectGoal(id));
            wrap.appendChild(b);
        });
    }

    function qhRenderNarrows() {
        const label = document.getElementById('ai-qh-narrows-label');
        const wrap = document.getElementById('ai-qh-narrows');
        if (!wrap) return;
        wrap.innerHTML = '';
        let count = 0;
        if (qhState.goals.length) {
            Object.keys(aiQH.narrows).forEach(id => {
                const n = aiQH.narrows[id];
                // Rāda precizējumus, kas der JEBKURAM no atzīmētajiem mērķiem.
                if (!(n.goals || []).some(g => qhState.goals.indexOf(g) !== -1)) return;
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'ai-qh-narrow' + (qhState.narrows.indexOf(id) !== -1 ? ' selected' : '');
                b.textContent = n.name;
                b.addEventListener('click', () => qhSelectNarrow(id));
                wrap.appendChild(b);
                count++;
            });
        }
        label.style.display = count ? 'block' : 'none';
        wrap.style.display = count ? 'flex' : 'none';
    }

    // Gatavie jautājumi: precizējuma varianti (3. līmenis) vai lomas jautājumi (1. līmenis).
    function qhRenderCurated() {
        const label = document.getElementById('ai-qh-curated-label');
        const list = document.getElementById('ai-qh-curated');
        if (!list) return;
        list.innerHTML = '';
        let items = [], labelText = '';
        if (qhState.narrows.length) {
            qhState.narrows.forEach(nid => {
                (aiQH.narrows[nid].variants || []).forEach(v => items.push(v));
            });
            labelText = qhState.narrows.length > 1
                ? 'Gatavie jautājumu varianti izvēlētajiem precizējumiem'
                : 'Gatavie jautājumu varianti šim precizējumam';
        } else if (qhState.role) {
            items = aiQH.roles[qhState.role].questions || [];
            labelText = 'Gatavie jautājumi šim skatpunktam';
        }
        if (!items.length) { label.style.display = 'none'; return; }
        label.innerHTML = labelText + ' <span class="ai-qh-hint">(klikšķis ieliek laukā)</span>';
        label.style.display = 'block';
        items.forEach(q => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'ai-qh-question';
            b.textContent = q;
            b.addEventListener('click', () => {
                const r = qhState.role ? aiQH.roles[qhState.role] : null;
                setUserQText(r ? r.prefix + ' ' + q : q);
            });
            list.appendChild(b);
        });
    }

    function selectQHRole(el) {
        const id = el.dataset.role;
        const was = qhState.role === id;
        document.querySelectorAll('#ai-qh-roles .ai-qh-role').forEach(b => b.classList.remove('selected'));
        qhState.role = was ? null : id;   // atkārtots klikšķis noņem izvēli
        if (!was) el.classList.add('selected');
        qhState.goals = [];               // skatpunkta maiņa noresetē dziļākos līmeņus
        qhState.narrows = [];
        qhRenderGoals(); qhRenderNarrows(); qhRenderCurated(); qhComposeText();
    }

    function qhSelectGoal(id) {
        const i = qhState.goals.indexOf(id);
        if (i === -1) qhState.goals.push(id); else qhState.goals.splice(i, 1);
        // Izmet precizējumus, kas vairs nepieder nevienam atzīmētajam mērķim.
        qhState.narrows = qhState.narrows.filter(nid =>
            (aiQH.narrows[nid].goals || []).some(g => qhState.goals.indexOf(g) !== -1));
        qhRenderGoals(); qhRenderNarrows(); qhRenderCurated(); qhComposeText();
    }

    function qhSelectNarrow(id) {
        const i = qhState.narrows.indexOf(id);
        if (i === -1) qhState.narrows.push(id); else qhState.narrows.splice(i, 1);
        qhRenderNarrows(); qhRenderCurated(); qhComposeText();
    }

    // "Citas tēmas" — brīvā kombinācija ārpus kaskādes (vienreizējs ieliktnis laukā).
    function pickQHTopic(el) {
        const core = (el.dataset && el.dataset.q) || '';
        const r = qhState.role ? aiQH.roles[qhState.role] : null;
        setUserQText(r ? r.prefix + ' ' + core : core);
    }

    // ==== Izvērtējuma turpinājuma jautājumi ====
    // "A. Situācijas izvērtējums" atbilde beidzas ar sadaļu "## Ko jautāt tālāk";
    // renderFollowupChips šo sarakstu pārvērš čipos, un čipa klikšķis TURPINA
    // sarunu čata pavedienā.
    function askFollowupFromChip(q) {
        sendChatMessage(q);
    }

    // ==== Sarunas (čata) pavediens zem diagnozes ====
    // Vēsture dzīvo lapas sesijā (aiChatHistory) un aiziet uz serveri kā
    // chat_history parametrs caur fetch POST (GET URL vēsturei ir par īsu).
    const aiChatRef = { cat: 'finansu_analize', btn: 'saruna' };
    const aiChatRegNr = "<?= h($page_data['search_reg_nr'] ?? '') ?>";
    const aiChatCompany = <?= json_encode((string)($page_data['companyTitleForHtml'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    const AI_CHAT_MAX_TURNS = 10; // vienas sarunas limits — lietotāja jautājumu skaits
    let aiChatHistory = [];
    let aiChatSeeded = false;
    let aiChatBusy = false;

    function userTurnCount() {
        return aiChatHistory.length - (aiChatSeeded ? 1 : 0);
    }

    // Sarunas sākuma konteksts = diagnozes teksts (tīrais markdown no pregen keša).
    function seedChatIfNeeded() {
        if (aiChatSeeded) return;
        let diag = '';
        const pregen = document.querySelector('#pregen-' + aiChatRef.cat + '---uznemuma_diagnoze .ai-markdown-content');
        if (pregen) diag = pregen.getAttribute('data-raw-text') || '';
        if (!diag) {
            const r = document.getElementById('ai-response');
            diag = r ? r.textContent : '';
        }
        diag = (diag || '').trim();
        if (diag) {
            // Diagnozes uzvedne (ja lapā redzama) — lejupielādes pielikumam.
            const dp = document.querySelector('#ai-response-box .ai-prompt-details pre');
            aiChatHistory.push({ q: 'Parādi situācijas izvērtējumu.', a: diag, prompt: dp ? (dp.textContent || '') : '' });
            aiChatSeeded = true;
        }
    }

    function buildChatHistoryParam() {
        let txt = aiChatHistory.map(t => 'Lietotājs: ' + t.q + '\nAnalītiķis: ' + t.a).join('\n\n');
        if (txt.length > 6000) txt = '(sarunas sākums izlaists)\n' + txt.slice(-6000);
        return txt;
    }

    function setChatBusy(b) {
        aiChatBusy = b;
        const inp = document.getElementById('ai-chat-input');
        const btn = document.getElementById('ai-chat-send');
        if (inp) inp.disabled = b;
        if (btn) btn.disabled = b;
    }

    // Izveido (vai atrod) pavediena konteineri atbilžu logā; ja saruna jau bijusi,
    // atjauno iepriekšējos gājienus (pēc pogu pārslēgšanās box.innerHTML tos nodzēš).
    function ensureChatThread() {
        let thread = document.getElementById('ai-chat-thread');
        if (thread) return thread;
        const box = document.getElementById('ai-response-box');
        thread = document.createElement('div');
        thread.id = 'ai-chat-thread';
        aiChatHistory.slice(aiChatSeeded ? 1 : 0).forEach(t => {
            const qd = document.createElement('div');
            qd.className = 'ai-chat-q';
            qd.textContent = t.q;
            const ad = document.createElement('div');
            ad.className = 'ai-chat-a';
            ad.innerHTML = mdToSafeHtml(t.a);
            renderFollowupChips(ad);
            thread.appendChild(qd);
            thread.appendChild(ad);
        });
        const row = document.createElement('div');
        row.id = 'ai-chat-inputrow';
        row.innerHTML = '<textarea id="ai-chat-input" rows="1" maxlength="1000" placeholder="Turpini sarunu — ieraksti savu jautājumu..."></textarea>'
            + '<button id="ai-chat-send" class="ai-btn ai-user-q-submit" type="button">➤</button>';
        thread.appendChild(row);
        const foot = document.createElement('div');
        foot.id = 'ai-chat-footer';
        foot.innerHTML = '<span id="ai-chat-counter"></span><span style="flex:1"></span>'
            + '<button id="ai-chat-download" class="ai-chat-tool" type="button" title="Lejupielādēt visu sarunu ar pilnajām uzvednēm .txt failā">⬇ Lejupielādēt sarunu (.txt)</button>'
            + '<button id="ai-chat-reset" class="ai-chat-tool" type="button" title="Notīrīt pavedienu un sākt jaunu sarunu">🗑 Jauna saruna</button>';
        thread.appendChild(foot);
        box.appendChild(thread);
        document.getElementById('ai-chat-send').addEventListener('click', () => {
            const inp = document.getElementById('ai-chat-input');
            const v = (inp.value || '').trim();
            if (!v) { inp.focus(); return; }
            inp.value = '';
            sendChatMessage(v);
        });
        document.getElementById('ai-chat-input').addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); document.getElementById('ai-chat-send').click(); }
        });
        document.getElementById('ai-chat-download').addEventListener('click', downloadChatTranscript);
        document.getElementById('ai-chat-reset').addEventListener('click', resetChat);
        updateChatCounter();
        return thread;
    }

    // Skaitītājs "Jautājums X no 10" + ievades slēgšana pēc limita.
    function updateChatCounter() {
        const c = document.getElementById('ai-chat-counter');
        if (!c) return;
        const used = userTurnCount();
        const inp = document.getElementById('ai-chat-input');
        const s = document.getElementById('ai-chat-send');
        if (used >= AI_CHAT_MAX_TURNS) {
            c.textContent = 'Sarunas limits sasniegts (' + AI_CHAT_MAX_TURNS + ' jautājumi) — lejupielādē sarunu vai sāc jaunu.';
            c.classList.add('limit');
            if (inp) { inp.disabled = true; inp.placeholder = 'Sarunas limits sasniegts.'; }
            if (s) s.disabled = true;
        } else {
            c.textContent = 'Jautājums ' + (used + 1) + ' no ' + AI_CHAT_MAX_TURNS;
            c.classList.remove('limit');
        }
    }

    function resetChat() {
        if (!confirm('Notīrīt sarunu un sākt jaunu? Ja vēlies šo sarunu saglabāt, vispirms lejupielādē to.')) return;
        aiChatHistory = [];
        aiChatSeeded = false;
        const thread = document.getElementById('ai-chat-thread');
        if (thread) thread.remove();
    }

    // Pilns sarunas teksts .txt failam: saruna lasāmā formā + visas uzvednes pielikumā.
    function buildChatTranscript() {
        const L = [];
        L.push('ČATA SARUNA — ' + aiChatCompany + ' (reģ. nr. ' + aiChatRegNr + ')');
        L.push('Lejupielādēts: ' + new Date().toLocaleString('lv-LV'));
        L.push('Modelis: gemini-3-flash-preview · saraksts.lv "Padziļinātā izpēte"');
        L.push('');
        aiChatHistory.forEach((t, i) => {
            if (i === 0 && aiChatSeeded) {
                L.push('=== SITUĀCIJAS IZVĒRTĒJUMS (sarunas konteksts) ===');
                L.push(t.a);
            } else {
                const n = aiChatSeeded ? i : i + 1;
                L.push('=== ' + n + '. JAUTĀJUMS ===');
                L.push(t.q);
                L.push('');
                L.push('=== ' + n + '. ATBILDE ===');
                L.push(t.a);
            }
            L.push('');
        });
        if (aiChatHistory.some(t => t.prompt)) {
            L.push('========================================');
            L.push('PILNĀS UZVEDNES (tehniskais pielikums)');
            L.push('========================================');
            aiChatHistory.forEach((t, i) => {
                if (!t.prompt) return;
                const label = (i === 0 && aiChatSeeded) ? 'IZVĒRTĒJUMA UZVEDNE' : ((aiChatSeeded ? i : i + 1) + '. GĀJIENA UZVEDNE');
                L.push('');
                L.push('--- ' + label + ' ---');
                L.push(t.prompt);
            });
        }
        return L.join('\n');
    }

    function downloadChatTranscript() {
        const blob = new Blob([buildChatTranscript()], { type: 'text/plain;charset=utf-8' });
        const d = new Date();
        const pad = n => String(n).padStart(2, '0');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'saruna-' + aiChatRegNr + '-' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '-' + pad(d.getHours()) + pad(d.getMinutes()) + '.txt';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    }

    async function sendChatMessage(q) {
        q = (q || '').trim();
        if (!q || aiChatBusy) return;

        // Vienas sarunas limits: pēc 10 jautājumiem tikai lejupielāde vai jauna saruna.
        if (userTurnCount() >= AI_CHAT_MAX_TURNS) {
            ensureChatThread();
            updateChatCounter();
            const c = document.getElementById('ai-chat-counter');
            if (c) c.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        // Tā pati klienta puses slodzes aizsardzība kā askAI; bloķēto gājienu
        // saglabā un pēc CAPTCHA izpilda automātiski (skat. initCaptcha).
        window.dynamicServerLoad = (window.dynamicServerLoad || 0) + 1;
        if (window.isProtectionActive && window.dynamicServerLoad > window.captchaThresholdLimit && !window.captchaSolved) {
            window.aiPendingAsk = { chatQ: q };
            alert("Sistēmas slodzes aizsardzība: Lūdzu atrisiniet ātro drošības pārbaudi, lai turpinātu sarunu!");
            document.getElementById('ai-captcha-box').style.display = 'block';
            const oBox = document.getElementById('ai-captcha-options');
            if (!oBox.innerHTML && typeof window.initCaptcha === 'function') window.initCaptcha();
            setTimeout(() => document.querySelector('.ai-output-panel').scrollIntoView({ behavior: 'smooth' }), 100);
            return;
        }

        seedChatIfNeeded();
        const thread = ensureChatThread();
        const inputRow = document.getElementById('ai-chat-inputrow');
        const qDiv = document.createElement('div');
        qDiv.className = 'ai-chat-q';
        qDiv.textContent = q;
        const aDiv = document.createElement('div');
        aDiv.className = 'ai-chat-a';
        // Dzīvs indikators: pulsējoši punkti + 15 s atskaite (tipiskais atbildes
        // laiks); ja ievelkas ilgāk, atskaites vietā paliek nomierinošs teksts.
        aDiv.innerHTML = '<span class="ai-chat-typing">Domā<span class="ai-typing-dot"></span><span class="ai-typing-dot"></span><span class="ai-typing-dot"></span> <span class="ai-typing-count">~15 s</span></span>';
        let typingLeft = 15;
        const typingTimer = setInterval(() => {
            const c = aDiv.querySelector('.ai-typing-count');
            if (!c) { clearInterval(typingTimer); return; } // pirmais teksta gabals jau nomainījis saturu
            typingLeft--;
            c.textContent = typingLeft > 0 ? '~' + typingLeft + ' s' : 'vēl mirkli…';
            if (typingLeft <= 0) clearInterval(typingTimer);
        }, 1000);
        thread.insertBefore(qDiv, inputRow);
        thread.insertBefore(aDiv, inputRow);
        qDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setChatBusy(true);

        const body = new URLSearchParams({
            action: 'ask_ai',
            category_id: aiChatRef.cat,
            button_id: aiChatRef.btn,
            reg_nr: aiChatRegNr,
            user_q: q,
            chat_history: buildChatHistoryParam()
        });

        let fullText = '';
        let turnPrompt = ''; // pilnā uzvedne no servera 'prompt' notikuma (lejupielādei)
        try {
            const resp = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            });
            const reader = resp.body.getReader();
            const dec = new TextDecoder();
            let buf = '';
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                // Google SSE lieto \r\n rindu beigas — normalizējam, citādi bloku
                // atdalītājs '\n\n' nekad nesakrīt un viss salīp vienā blokā.
                buf += dec.decode(value, { stream: true }).replace(/\r/g, '');
                let idx;
                while ((idx = buf.indexOf('\n\n')) !== -1) {
                    const block = buf.slice(0, idx);
                    buf = buf.slice(idx + 2);
                    let ev = 'message', data = '';
                    block.split('\n').forEach(line => {
                        if (line.startsWith('event:')) ev = line.slice(6).trim();
                        else if (line.startsWith('data:')) data += line.slice(5).trim();
                    });
                    if (ev === 'server_error') {
                        let em = 'Kļūda';
                        try { em = (JSON.parse(data) || {}).error || em; } catch (e) {}
                        aDiv.innerHTML = '<span style="color:red;">⚠️ ' + escapeHtml(em) + '</span>';
                        setChatBusy(false);
                        return;
                    }
                    if (ev === 'prompt' && data) {
                        try { turnPrompt = (JSON.parse(data) || {}).text || ''; } catch (e) {}
                    }
                    if (ev === 'message' && data) {
                        try {
                            const d = JSON.parse(data);
                            const t = d.candidates?.[0]?.content?.parts?.[0]?.text;
                            if (t) { fullText += t; aDiv.innerHTML = mdToSafeHtml(fullText); }
                        } catch (e) {}
                    }
                }
            }
        } catch (e) {
            if (!fullText) aDiv.innerHTML = '<span style="color:red;">⚠️ Savienojuma kļūda.</span>';
        }

        if (fullText) {
            aDiv.innerHTML = mdToSafeHtml(fullText);
            renderFollowupChips(aDiv);
            aiChatHistory.push({ q: q, a: fullText, prompt: turnPrompt });
        }
        setChatBusy(false);
        updateChatCounter(); // pēc limita sasniegšanas ievade paliek slēgta
        // NEritinām uz ievades lauku zem atbildes — garai atbildei tas pārlektu
        // tai visai pāri un lietotājs apjuktu, kur lapa atrodas. Skats paliek pie
        // jautājuma/atbildes sākuma; ja jautājums ģenerēšanas laikā aizslīdējis
        // virs ekrāna, saudzīgi atgriežam to redzeslokā. Fokuss — bez ritināšanas.
        if (qDiv.getBoundingClientRect().top < 0) {
            qDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        const inp = document.getElementById('ai-chat-input');
        if (inp && !inp.disabled) inp.focus({ preventScroll: true });
    }

    function renderFollowupChips(container) {
        if (!container) return;
        const heads = container.querySelectorAll('h1,h2,h3,h4');
        for (const h of heads) {
            if (!/ko jautāt tālāk/i.test(h.textContent)) continue;
            let el = h.nextElementSibling;
            if (el && el.classList && el.classList.contains('ai-followup-chips')) return; // jau pārveidots
            while (el && !/^(UL|OL)$/i.test(el.tagName)) el = el.nextElementSibling;
            if (!el) return;
            const qs = [...el.querySelectorAll('li')].map(li => li.textContent.trim()).filter(t => t.length > 8);
            if (!qs.length) return;
            const wrap = document.createElement('div');
            wrap.className = 'ai-followup-chips';
            qs.forEach(q => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'ai-followup-chip';
                b.textContent = q;
                b.title = 'Turpināt sarunu ar šo jautājumu';
                b.addEventListener('click', () => askFollowupFromChip(q));
                wrap.appendChild(b);
            });
            el.replaceWith(wrap);
            return;
        }
    }

    qhRenderGoals(); // sākumstāvoklis: vispārīgie mērķi, kamēr skatpunkts nav izvēlēts

    // Validē ievadi, saglabā jautājumu + stilu un vienmēr ģenerē par jaunu
    // (forceRefresh) — jautājums katrreiz var atšķirties.
    function submitUserQuestion(catId, btnId) {
        const ta = document.getElementById('ai-user-q-input');
        const msg = document.getElementById('ai-user-q-msg');
        const q = (ta && ta.value ? ta.value : '').trim();
        if (q.length < 10) {
            if (msg) msg.textContent = 'Ierakstiet jautājumu (vismaz 10 rakstzīmes).';
            if (ta) ta.focus();
            return;
        }
        if (msg) msg.textContent = '';
        window.aiUserQuestion = q;
        const sel = document.querySelector('#ai-user-q-styles .ai-style-chip.selected');
        window.aiUserStyle = (sel && sel.dataset) ? sel.dataset.style : '';
        stashUserQForm();
        const leftBtn = document.getElementById('btn-' + catId + '-' + btnId);
        askAI(catId, btnId, leftBtn, true);
    }

    function askAI(catId, btnId, btnEl, forceRefresh = false) {
        stashUserQForm(); // atbilžu logs tūlīt tiks pārrakstīts — formu drošībā
        const cacheKey = catId + '---' + btnId;
        const pregenDiv = document.getElementById('pregen-' + cacheKey);
        const box = document.getElementById('ai-response-box');
        
        if (pregenDiv && !forceRefresh) {
            if (currentAIStream) currentAIStream.close();
            if (activeAIBtn) activeAIBtn.classList.remove('active');
            activeAIBtn = btnEl;
            btnEl.classList.add('active');
            
            document.getElementById('ai-loader').style.display = 'none';
            document.getElementById('ai-panel-title').textContent = (btnEl.dataset && btnEl.dataset.panelTitle) || btnEl.textContent.trim();

            if (window.innerWidth <= 768) {
                document.getElementById('ai-controls-body').classList.add('collapsed');
                document.getElementById('ai-menu-toggle').textContent = 'Rādīt opcijas ▼';
                setTimeout(() => document.querySelector('.ai-output-panel').scrollIntoView({ behavior: 'smooth' }), 100);
            }
            
            const rawTextDiv = pregenDiv.querySelector('.ai-markdown-content');
            if (rawTextDiv && !rawTextDiv.innerHTML) {
                 rawTextDiv.innerHTML = mdToSafeHtml(rawTextDiv.getAttribute('data-raw-text'));
            }
            box.innerHTML = pregenDiv.innerHTML;
            renderFollowupChips(box); // innerHTML kopija zaudē klausītājus — čipi jāuzbūvē no jauna
            return;
        }

        // --- SKAITĀM DZĪVO SLODZI BEZ LAPAS PĀRLĀDES ("MID-SESSION SLODZE") ---
        window.dynamicServerLoad = (window.dynamicServerLoad || 0) + 1;
        
        if (window.isProtectionActive && window.dynamicServerLoad > window.captchaThresholdLimit && !window.captchaSolved) {
            // Saglabājam bloķēto pieprasījumu (arī 5. punkta jautājumu), lai pēc
            // veiksmīgas CAPTCHA ģenerāciju izpildītu automātiski — citādi lietotāja
            // pieprasītā atbilde vienkārši pazūd.
            window.aiPendingAsk = { catId: catId, btnId: btnId, btnElId: (btnEl && btnEl.id) || '', forceRefresh: forceRefresh };
            alert("Sistēmas slodzes aizsardzība: Lūdzu atrisiniet ātro drošības pārbaudi ekrāna centrā, lai turpinātu ģenerēt un atjaunināt failus!");
            
            document.getElementById('ai-captcha-box').style.display = 'block';
            const pht = document.getElementById('ai-placeholder-text-inner');
            if (pht) pht.style.display = 'none';
            
            const oBox = document.getElementById('ai-captcha-options');
            if (!oBox.innerHTML && typeof window.initCaptcha === 'function') {
                window.initCaptcha();
            }
            
            // Atslēdzam visas izvēlnes pogas, kamēr CAPTCHA nav noklikšķināta
            document.querySelectorAll('.ai-btn').forEach(b => { if(!b.classList.contains('ai-btn-regenerate')) b.disabled = true; });
            
            setTimeout(() => document.querySelector('.ai-output-panel').scrollIntoView({ behavior: 'smooth' }), 100);
            return;
        }
        // -------------------------------------------------------------------

        const allBtns = document.querySelectorAll('.ai-btn');
        allBtns.forEach(b => b.disabled = true);
        setTimeout(() => allBtns.forEach(b => b.disabled = false), 10000);

        if (currentAIStream) currentAIStream.close();
        if (activeAIBtn) activeAIBtn.classList.remove('active');
        activeAIBtn = btnEl;
        btnEl.classList.add('active');

        document.getElementById('ai-loader').style.display = 'block';
        document.getElementById('ai-panel-title').textContent = (btnEl.dataset && btnEl.dataset.panelTitle) || btnEl.textContent.trim();

        if (window.innerWidth <= 768) {
            document.getElementById('ai-controls-body').classList.add('collapsed');
            document.getElementById('ai-menu-toggle').textContent = 'Rādīt opcijas ▼';
            setTimeout(() => document.querySelector('.ai-output-panel').scrollIntoView({ behavior: 'smooth' }), 100);
        }

        let fullText = '';
        let respDiv = null;
        
        // PIELIEKAM reg_nr, lai PHP serveris noteikti saprastu kuru JSON failu atvērt, pat ja URL tiek mainīts
        const regNr = "<?= h($page_data['search_reg_nr'] ?? '') ?>";
        // Izmantojam vienkāršu URL parametru pievienošanu esošajai lapai (drošākais variants pret rewrite likumiem)
        // Lietotāja jautājuma pogai (data-user-input) pieliekam jautājumu un stilu.
        const userQPart = (btnEl.dataset && btnEl.dataset.userInput && window.aiUserQuestion)
            ? `&user_q=${encodeURIComponent(window.aiUserQuestion)}&style=${encodeURIComponent(window.aiUserStyle || '')}` : '';
        const url = `?action=ask_ai&category_id=${encodeURIComponent(catId)}&button_id=${encodeURIComponent(btnId)}&reg_nr=${encodeURIComponent(regNr)}${forceRefresh ? '&force_refresh=true' : ''}${userQPart}`;
        currentAIStream = new EventSource(url);

        currentAIStream.addEventListener('prompt', e => {
            document.getElementById('ai-loader').style.display = 'none';
            const data = JSON.parse(e.data);
            const rq = getRandomQuote();
            box.innerHTML = `
                <details class="ai-prompt-details">
                    <summary>📋 Skatīt iesniegto uzvedni</summary>
                    <pre>${escapeHtml(data.text)}</pre>
                </details>
                <div id="ai-response">
                    <div class="ai-quote-box">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #dce3ea; padding-bottom: 8px;">
                            <div style="font-size: 13px; font-weight: 600; color: #3498db; display: flex; align-items: center; gap: 8px;">
                                <div class="ai-spinner" style="position: static; width: 14px; height: 14px;"></div>
                                Tiek ģenerēta atbilde...
                            </div>
                            <div id="ai-timer" style="font-family: monospace; font-size: 14px; font-weight: bold; color: #2c3e50;">15:00</div>
                        </div>
                        <p class="ai-quote-text">«${rq.text}»</p>
                        <p class="ai-quote-author">— ${rq.author}</p>
                    </div>
                </div>`;
            respDiv = document.getElementById('ai-response');
            
            // Taimera loģika
            let timeLeft = 15;
            let isCountingUp = false;
            const timerEl = document.getElementById('ai-timer');
            
            if (window.aiTimerInterval) clearInterval(window.aiTimerInterval);
            
            window.aiTimerInterval = setInterval(() => {
                if (!respDiv || document.getElementById('ai-timer') === null) {
                    clearInterval(window.aiTimerInterval);
                    return;
                }
                
                if (!isCountingUp) {
                    timeLeft--;
                    if (timeLeft <= 0) {
                        timeLeft = 0;
                        isCountingUp = true;
                        timerEl.style.color = '#e74c3c'; // Sarkans
                    }
                } else {
                    timeLeft++;
                }
                
                const mins = Math.floor(timeLeft / 60);
                const secs = timeLeft % 60;
                timerEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }, 1000);
        });

        currentAIStream.addEventListener('server_error', e => {
            if (window.aiTimerInterval) clearInterval(window.aiTimerInterval);
            document.getElementById('ai-loader').style.display = 'none';
            box.innerHTML = `<span style="color:red;">⚠️ Kļūda: ${escapeHtml(JSON.parse(e.data).error)}</span>`;
            currentAIStream.close();
            btnEl.classList.remove('active');
        });

        currentAIStream.onmessage = e => {
            if (!respDiv) return;
            try {
                const data = JSON.parse(e.data);
                if (data.candidates?.[0]?.content?.parts?.[0]?.text) {
                    if (window.aiTimerInterval) clearInterval(window.aiTimerInterval);
                    if (fullText === '') respDiv.innerHTML = '';
                    fullText += data.candidates[0].content.parts[0].text;
                    respDiv.innerHTML = mdToSafeHtml(fullText);
                }
            } catch (err) {}
        };

        currentAIStream.addEventListener('done', () => {
            currentAIStream.close();

            // Katras ģenerācijas beigās redzamajā atbildē pievienojam pārģenerēšanas pogu —
            // MI atbilde mēdz būt nepabeigta vai kļūdaina, tāpēc jāvar mēģināt vēlreiz.
            if (respDiv && fullText.trim() !== '' && !respDiv.querySelector('.ai-btn-regenerate')) {
                const regenWrap = document.createElement('div');
                regenWrap.style.cssText = 'text-align: right; margin-top: 20px; border-top: 1px solid #e8ecf0; padding-top: 16px;';
                const regenBtn = document.createElement('button');
                regenBtn.className = 'ai-btn ai-btn-regenerate';
                regenBtn.style.cssText = 'display: inline-block; width: auto; font-weight: 600;';
                regenBtn.textContent = '🔄 Pārģenerēt atbildi';
                regenBtn.onclick = function () { forceRegenerateAI(catId, btnId, this); };
                regenWrap.appendChild(regenBtn);
                respDiv.appendChild(regenWrap);
            }

            // Saglabājam uzģenerēto rezultātu virtuālajā kešatmiņā (DOM), lai otrreiz spiežot to pašu pogu - atbilde rādītos par brīvu.
            let pregenContainer = document.getElementById('ai-pregenerated-content');
            if (pregenContainer && fullText.trim() !== '') {
                let pregenDiv = document.getElementById('pregen-' + cacheKey);
                if (!pregenDiv) {
                    pregenDiv = document.createElement('div');
                    pregenDiv.id = 'pregen-' + cacheKey;
                    pregenContainer.appendChild(pregenDiv);
                }
                
                const now = new Date();
                const dateStr = now.getDate().toString().padStart(2, '0') + '.' + (now.getMonth()+1).toString().padStart(2, '0') + '.' + now.getFullYear();
                
                pregenDiv.innerHTML = `
                    <div class="ai-generated-date" style="font-size: 13px; color: #7f8c8d; margin-bottom: 16px; font-style: italic;">
                        Pēdējo reizi Mākslīgais Intelekts analizēja šos datus: ${dateStr} (Uzģenerēts nupat)
                    </div>

                    <div class="ai-markdown-content" data-raw-text="${escapeHtml(fullText)}">
                        ${mdToSafeHtml(fullText)}
                    </div>
                    <div style="text-align: right; margin-top: 20px; border-top: 1px solid #e8ecf0; padding-top: 16px;">
                        <button class="ai-btn ai-btn-regenerate" style="display: inline-block; width: auto; font-weight: 600;" onclick="forceRegenerateAI('${catId}', '${btnId}', this)">🔄 Pārģenerēt analīzi par jaunu</button>
                    </div>
                `;
            }

            // Diagnozes "Ko jautāt tālāk" sarakstu redzamajā atbildē pārvēršam čipos
            // (pēc pregen ieraksta, lai kešā paliek tīrais saraksts bez pogu marķējuma).
            if (respDiv) renderFollowupChips(respDiv);
        });
        currentAIStream.onerror = () => {
            if (!respDiv) {
                document.getElementById('ai-loader').style.display = 'none';
                box.innerHTML = '<span style="color:red;">⚠️ Savienojuma kļūda.</span>';
            }
            currentAIStream.close();
        };
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Attīra HTML pirms ievietošanas DOM (aizsardzība pret ļaunprātīgu HTML
    // MI izvadē, piem. prompt injection). Izņem bīstamos tagus un atribūtus.
    function sanitizeAIHtml(html) {
        const tpl = document.createElement('template');
        tpl.innerHTML = String(html == null ? '' : html);
        tpl.content.querySelectorAll('script, style, iframe, object, embed, link, meta, form, base').forEach(el => el.remove());
        tpl.content.querySelectorAll('*').forEach(el => {
            [...el.attributes].forEach(attr => {
                const name = attr.name.toLowerCase();
                const val = (attr.value || '').replace(/\s+/g, '').toLowerCase();
                if (name.startsWith('on')) {
                    el.removeAttribute(attr.name);
                } else if ((name === 'href' || name === 'src' || name === 'xlink:href') && val.startsWith('javascript:')) {
                    el.removeAttribute(attr.name);
                }
            });
        });
        return tpl.innerHTML;
    }

    // Rezerves Markdown -> HTML pārveidotājs (tie paši likumi, ko lieto PHP servera puse:
    // **treknraksts**, ## / ### virsraksti, * saraksti, jaunas rindas = <br>).
    // Lieto, ja marked.min.js nav pieejams — atbilde VIENMĒR ir formatēta kā oriģinālā.
    function simpleMarkdown(text) {
        let t = escapeHtml(text || '');
        t = t.replace(/\*\*([\s\S]*?)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/^### (.*)$/gm, '<h3>$1</h3>');
        t = t.replace(/^## (.*)$/gm, '<h2>$1</h2>');
        t = t.replace(/^# (.*)$/gm, '<h2>$1</h2>');
        t = t.replace(/^\s*[\*\-] (.*)$/gm, '<li>$1</li>');
        t = t.replace(/(<li>[\s\S]*?<\/li>)(\n(?=<li>))?/g, '$1');
        t = t.replace(/((?:<li>.*?<\/li>\n?)+)/g, '<ul>$1</ul>');
        // Jaunas rindas -> <br>, bet ne pēc bloka elementiem (virsraksti, saraksti)
        t = t.replace(/(<\/h2>|<\/h3>|<\/ul>|<\/li>)\n/g, '$1');
        t = t.replace(/\n/g, '<br>');
        return t;
    }

    // Markdown -> drošs HTML. Ja marked nav ielādēts, lieto rezerves pārveidotāju.
    function mdToSafeHtml(text) {
        const raw = (typeof marked !== 'undefined') ? marked.parse(text || '') : simpleMarkdown(text || '');
        return sanitizeAIHtml(raw);
    }
</script>