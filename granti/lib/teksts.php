<?php
/**
 * granti/lib/teksts.php — KONKURSA TEKSTA STRUKTURĒŠANA.
 *
 * KĀPĒC. ES portāla `description` (mediāna 5 236 rakstz.) un `conditions` (10 411 rakstz.)
 * lapā līdz šim netika rādīti nemaz — tikai nosaukums. Bet cilvēkam, kas apsver pieteikšanos,
 * vajag trīs atbildes: KO projektam jāsasniedz, KO jādara un KAS DRĪKST pieteikties.
 *
 * KAS TEKSTOS IR (izmērīts uz 648 kešotiem failiem, 2026-09-08):
 *   description ir sadalīts ar <p class="topicdescriptionkind">X</p> marķieriem:
 *     "Expected Outcome" 511 (Apvārsnis) / "Expected Impact" (EDF, LIFE, CEF, RFCS) — ko sasniegt;
 *     "Scope" 605 — ko darīt (mediāna 3 528 rakstz., 380 ar uzskaitījumiem);
 *     "Objective" (LIFE, CEF, DIGITAL, EDF) — mērķis;
 *     "Technology Readiness Level…" 42 un "null" 147 — abi ir TRL teikums.
 *   conditions ir sadalīts ar numurētiem <h4>:
 *     "Other eligibility conditions" 642, no tiem 318 ar REĀLU saturu (Copernicus obligāts 162,
 *     ģeogrāfija 156, konsorcija sastāvs 83, drošība 75, JRC 57) — īpašās prasības pretendentam;
 *     "Specific conditions" 484, no tiem 65 ar saturu (drošība 50);
 *     "Admissibility" — lappušu limits nolasāms tikai 48 (pārējie "described in Part B").
 *
 * KAS TEKSTOS NAV — un ko tāpēc NEIZLIEKAMIES izvilkt:
 *   "Financial and operational capacity" 638 no 643 ir tikai atsauce uz vispārējo C pielikumu.
 *   Pretendenta KVALIFIKĀCIJA konkursa tekstā nav aprakstīta — tā ir standarta prasība visai
 *   programmai, un lapā to var tikai paskaidrot vispārīgi, ne nolasīt.
 *   Vērtēšanas kritēriji (5a) un laika grafiks (5c) — 6–9 gadījumos, pārējie atsauce.
 *
 * DROŠĪBA. Teksts ir trešās puses HTML. Tagu kopa datos: p a div h4 li strong u sup em ul br
 * span ol td i sub — nav script/img/iframe, bet tas ir šodienas novērojums, ne garantija.
 * gr_sanitize_html() atstāj tikai balto sarakstu (p a ul ol li strong em sup sub br table
 * thead tbody tr th td) un no atribūtiem — tikai href uz https/mailto.
 *
 * LICENCE. ES portāla saturs ir CC BY 4.0 (Lēmums C(2019) 1655). Rādot sadaļas, lapa norāda
 * avotu un to, ka teksts ir SAKĀRTOTS (grozīts) — tā ir 3.(a)(1)(B) prasība.
 */
declare(strict_types=1);

/**
 * PRASĪBU BIRKAS — loģiskās sakarības, kas atkārtojas "Other eligibility conditions" un
 * "Specific conditions" tekstos. Birka ļauj pamanīt prasību, nelasot 10 000 rakstzīmju.
 *
 * SKAITĻUS ŠEIT PĀRRĒĶINA PĒC KATRAS KĀRTULU MAIŅAS. Iepriekšējie bija no agrākas versijas un
 * vairs neatbilda kodam (solīja "valstu ierobežojumi 156", faktiski 32), un divas kārtulas
 * nostrādāja NULLE reižu — "ētikas novērtējums" (vārds "ethic" atbilstības sadaļās neparādās
 * nevienreiz 648 tēmās) un tā laika MVU kārtula. Vārts, kas nevar nostrādāt, ir sliktāks par
 * tā trūkumu: tas rada iespaidu, ka pārbaude notiek.
 *
 * Faktiskais sadalījums pēc 2026-09-08 labojumiem (248 tēmas ar vismaz vienu birku):
 * Copernicus/Galileo 162 · valstu vai dalībnieku ierobežojums 72 · drošība 55 · JRC 54 ·
 * paplašināta dalība 22 · konsorcija sastāvs 21 · turpinājums 5 · MVU 2 · dzimumu līdzsvars 1.
 * Kārtula => [birka latviski, paskaidrojums]. Secība = rādīšanas secība.
 */
const GR_PRASIBU_BIRKAS = [
    '/\b(?:at least|minimum(?: of)?)\s+(?:two|three|four|five|seven|\d+)\b[^.]{0,60}?(?:independent )?(?:legal )?entit|consortium (?:must|shall) (?:include|be composed|consist)/i'
        => ['konsorcija sastāvs noteikts', 'Konkursa teksts pats nosaka, cik un kādi partneri vajadzīgi — ne tikai vispārējie programmas noteikumi.'],
    // Vecā kārtula ("at least N SMEs", "SMEs must be") datos nesastapās nevienreiz. Īstās
    // frāzes ir citas: "Beneficiaries must be a small and medium enterprise (SME)" un
    // "At least 50% of the proposed budget must be allocated to SMEs".
    '/\bSMEs?\b[^.]{0,80}?\b(?:must|shall)\b|\b(?:must|shall)\b[^.]{0,80}?\bto SMEs?\b|beneficiar\w+ must be a (?:small|micro)/i'
        => ['MVU obligāti', 'Teksts prasa, lai pieteicējs vai daļa budžeta būtu mazajam vai vidējam uzņēmumam.'],
    // Divas PRETĒJAS lietas, ko sākumā sedza viena birka: "not eligible" sašaurina loku
    // (piem. Ķīnā dibinātas personas nedrīkst), "exceptionally eligible" to paplašina
    // (piem. trešo valstu iestādes šoreiz drīkst). Cilvēkam tās nozīmē pretējo.
    // "participation … is limited to legal entities established in …" ir tikpat bieža forma
    // kā "not eligible"; bez tās 46 sadaļas ar īstu ierobežojumu birku nedabūja.
    '/not eligible (?:to participate|for funding)|(?:are|is) not eligible|restricted to|(?:only|exclusively) (?:eligible|open to)|(?:participation|eligibility)[^.]{0,60}?\blimited to\b|\blimited to legal entities\b/i'
        => ['valstu vai dalībnieku ierobežojums', 'Daļa valstu vai organizāciju veidu šajā konkursā NEDRĪKST piedalīties — teksts tos nosauc.'],
    // "may participate as" izņemts: to jau sedz JRC birka, un abas kopā bija dublējums.
    '/exceptionally eligible|are (?:also )?eligible for funding/i'
        => ['paplašināta dalība', 'Šoreiz drīkst piedalīties arī tādi dalībnieki, kas parasti nav atbilstīgi (piem. trešo valstu organizācijas).'],
    '/Copernicus|Galileo|EGNOS/i'
        => ['Copernicus/Galileo obligāts', 'Ja projekts lieto satelītu datus, tiem jābūt no ES sistēmām Copernicus, Galileo vai EGNOS.'],
    '/classified|security[- ]sensitive|EUCI|security practitioner|security scrutiny/i'
        => ['drošības ierobežojumi', 'Projekts var skart klasificētu informāciju vai prasīt drošības praktiķu dalību — papildu procedūras un ierobežojumi.'],
    '/Joint Research Centre|\bJRC\b/i'
        => ['JRC var piedalīties', 'Komisijas Kopīgais pētniecības centrs var būt konsorcija dalībnieks bez finansējuma.'],
    '/coordinator of the (?:consortium|project) funded under|Hop-On|existing (?:grant agreement|consortium)/i'
        => ['tikai esoša projekta turpinājums', 'Pieteikumu iesniedz jau finansēta projekta koordinators — jauns pieteicējs bez tāda projekta nevar piedalīties.'],
    '/gender (?:balance|equality plan|dimension)/i'
        => ['dzimumu līdzsvara prasība', 'Konkurss īpaši prasa dzimumu līdzsvaru komandā vai dzimumu dimensiju pētījumā.'],
];

/**
 * Atgriež birkas, kas atbilst sadaļu tekstam. Meklē TIKAI eligibility un specific sadaļās —
 * Scope tekstā vārds "security" nozīmē tēmu, ne prasību.
 * @return string[] birkas latviski, rādīšanas secībā
 */
function gr_prasibu_birkas(?string $eligibilityHtml, ?string $specificHtml): array {
    $t = gr_html_text((string)$eligibilityHtml . ' ' . (string)$specificHtml);
    if ($t === '') return [];
    $out = [];
    foreach (GR_PRASIBU_BIRKAS as $re => [$birka]) if (preg_match($re, $t)) $out[] = $birka;
    return $out;
}

/** Birkas paskaidrojums (title atribūtam). */
function gr_birkas_skaidrojums(string $birka): string {
    foreach (GR_PRASIBU_BIRKAS as [$b, $sk]) if ($b === $birka) return $sk;
    return '';
}

/** Sadaļu atslēgas -> latviskie virsraksti un secība detaļu skatā. */
const GR_SADALAS = [
    'objective'   => 'Mērķis',
    'outcome'     => 'Ko projektam jāsasniedz',
    'scope'       => 'Ko jādara',
    'eligibility' => 'Īpašās prasības pieteikuma iesniedzējam',
    'specific'    => 'Īpašie nosacījumi',
];

/**
 * Atstāj tikai drošos tagus un `href` ar https/mailto. Div/span/td/i/u pārvērš vai atmet,
 * saglabājot saturu; h4 -> treknraksta rindkopa, lai nesajauktu lapas virsrakstu hierarhiju.
 */
function gr_sanitize_html(string $html): string {
    $html = preg_replace('/<(script|style|iframe|object|embed)\b.*?<\/\1>/is', '', $html) ?? '';
    $html = str_ireplace(['<i>', '</i>', '<u>', '</u>', '<b>', '</b>'],
                         ['<em>', '</em>', '<em>', '</em>', '<strong>', '</strong>'], $html);
    $html = preg_replace('/<h4\b[^>]*>(.*?)<\/h4>/is', '<p><strong>$1</strong></p>', $html) ?? '';
    // Tabulas atstājam: astoņās tēmās (t.sk. IHI TRL skalā) tās nesa saturu, un strip_tags
    // tās sabēra vienā nelasāmā rindā. Atribūtus tām tāpat nogriež kārtula zemāk.
    $html = strip_tags($html, '<p><a><ul><ol><li><strong><em><sup><sub><br><table><thead><tbody><tr><th><td>');
    // Atribūti: viss nost, izņemot href ar drošu shēmu.
    //
    // ENTITIJAS VISPIRMS ATKODĒ. Portāla HTML saites jau nāk kodētas ("...&amp;from=EN"),
    // un htmlspecialchars() tās kodēja OTRREIZ -> "&amp;amp;from=EN". Pārlūks tad ved uz
    // adresi ar burtisku "&amp;" vidū. Skarti 40 konkursi; katra atkārtota attīrīšana
    // pieliktu vēl vienu kārtu ("&amp;amp;amp;"). Atkodēšana pirms kodēšanas padara soli
    // idempotentu. Drošība nemainās: shēmu pārbauda regulārā izteiksme PIRMS atkodēšanas,
    // un jebkurš " < > no atkodēšanas tiek uzreiz aizkodēts atpakaļ.
    $html = preg_replace_callback('/<a\b([^>]*)>/i', function ($m) {
        if (preg_match('/href="((?:https?:\/\/|mailto:)[^"]*)"/i', $m[1], $h)) {
            $url = html_entity_decode($h[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="noopener" target="_blank">';
        }
        return '<a>';
    }, $html) ?? '';
    $html = preg_replace('/<(p|ul|ol|li|strong|em|sup|sub|br|table|thead|tbody|tr|th|td)\b[^>]*>/i', '<$1>', $html) ?? '';
    // Portāla zemsvītras marķieri "[[" "]]" un tukšas rindkopas.
    $html = str_replace(['[[', ']]'], ['', ''], $html);
    $html = preg_replace('/<p>\s*(?:&nbsp;|\s)*<\/p>/i', '', $html) ?? '';
    return trim($html);
}

/** HTML -> tīrs teksts (mērīšanai un "tikai atsauce" atpazīšanai). */
function gr_html_text(string $html): string {
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

/** Vai sadaļas saturs ir tikai atsauce uz pielikumu / uzsaukuma dokumentu. */
function gr_tikai_atsauce(string $text): bool {
    if (strlen($text) < 120) return true;
    return (bool)preg_match('/^.{0,40}?described in (?:General )?(?:Annex [A-Z]|section \d+ of the call document)[^.]*\.?\s*$/i', $text);
}

/**
 * Sadala description pēc topicdescriptionkind marķieriem.
 * @return array<string,string> atslēga (objective|outcome|scope|trl_text) => HTML
 */
function gr_description_sadalas(string $descHtml): array {
    $out = [];
    $parts = preg_split('/<p class="topicdescriptionkind">\s*([^<]*?)\s*<\/p>/i', $descHtml, -1, PREG_SPLIT_DELIM_CAPTURE);
    // BEZ MARĶIERIEM VISS PAZUDA. Divām tēmām (ESC-HUMAID-2021-QUAL-LABEL-FP ar 22 246
    // rakstzīmēm un EUBA-2024-PLANTS-04) portāls marķierus nedod vispār, un lapa tām rādīja
    // "strukturēts apraksts nav pieejams", kaut apraksts bija. Nesadalītu tekstu labāk rādīt
    // veselu zem "Ko jādara" nekā izmest.
    if ($parts === false || count($parts) < 3) {
        $tirs = trim(gr_html_text($descHtml));
        if ($tirs !== '' && strlen($tirs) >= 200) $out['scope'] = $descHtml;
        return $out;
    }
    for ($i = 1; $i + 1 < count($parts); $i += 2) {
        $k = strtolower(rtrim(trim($parts[$i]), ':'));
        $body = $parts[$i + 1];
        $key = match (true) {
            $k === 'scope'                                   => 'scope',
            $k === 'objective' || $k === 'objectives'        => 'objective',
            $k === 'expected outcome' || $k === 'expected impact'
                || $k === 'expected outcomes' || $k === 'expected impacts' => 'outcome',
            $k === 'null' || str_starts_with($k, 'technology readiness') => 'trl_text',
            // "Specific Challenge" ir vienīgais sastopamais nekartētais marķieris (3 WIDERA
            // dzimumu balvas). Bez tā `continue` izmeta visu to aprakstu — 3 649 rakstzīmes
            // katrai, un lapa apgalvoja, ka apraksta nav.
            str_starts_with($k, 'specific challenge')        => 'objective',
            default => null,
        };
        if ($key === null) continue;
        $out[$key] = isset($out[$key]) ? $out[$key] . $body : $body;
    }
    return $out;
}

/**
 * Sadala conditions pēc <h4> virsrakstiem.
 * @return array<string,string> normalizēts virsraksts (mazie burti, bez numura) => HTML
 */
function gr_conditions_sadalas(string $condHtml): array {
    $out = [];
    $parts = preg_split('/<h4\b[^>]*>/i', $condHtml);
    if ($parts === false) return $out;
    foreach (array_slice($parts, 1) as $p) {
        [$h, , $body] = array_pad(explode('</h4>', $p, 2) + [1 => '', 2 => ''], 3, '');
        if (!str_contains($p, '</h4>')) continue;
        $body = substr($p, strpos($p, '</h4>') + 5);
        $title = strtolower(preg_replace('/^\d+[a-z]?\.\s*/', '', gr_html_text(substr($p, 0, strpos($p, '</h4>')))) ?? '');
        $out[$title] = $body;
    }
    return $out;
}

/**
 * Lappušu limits no pielaides ("Admissibility") sadaļas.
 *
 * KĀPĒC PĀRRAKSTĪTS. Iepriekšējā versija ņēma jebkuru skaitli, kas 80 rakstzīmju attālumā
 * seko vārdam "limit", un tāpēc astoņiem Apvārsņa CL4 konkursiem ierakstīja 3 no teikuma
 * "the page limit in part B of the General Annexes is exceptionally extended by 3 pages".
 * Tur 3 ir PIELIKUMS pie limita, ne limits; īstais limits ir 43. Lapā tas rādījās kā
 * "Pieteikuma apjoms līdz 3 lpp." — sk. [[ref-varti-parse-pienemumu]]: vārti parsēja savu
 * pieņēmumu, ne lapu.
 *
 * TAGAD PRASĀM SKAIDRU SAISTĪJUMU. Skaitli pieņem tikai tad, ja teikums to nosauc par
 * limitu ("the page limit ... is N pages", "limited to N pages", "increased to N pages",
 * "maximum of N pages"). Pieaugumu formas ("extended/increased/reduced BY N pages")
 * teikumu atmet veselu — pieskaitīt to bāzei mēs nevaram, jo bāze (40 RIA/IA, 25 CSA,
 * 65 COFUND, 10 divu posmu pirmajam posmam) tekstā nav nosaukta, un uzminēt to nozīmētu
 * to pašu kļūdu no otras puses. Labāk nerādīt neko, nekā rādīt nepareizu skaitli.
 *
 * DIVU POSMU KONKURSI. Trīs IHI tēmās viens teikums nosauc DIVUS limitus: "at the first
 * stage ... 20 pages; at the second stage ... 50 pages". Vienā skaitlī tos saspiest nevar,
 * tāpēc atdodam pirmā posma limitu UN pazīmi, ka tas ir pirmā posma, lai lapa to arī tā
 * nosauc. Ja teikumā ir vairāki atšķirīgi limiti BEZ posmu norādes, neatdodam neko.
 *
 * @return array{0:?int,1:?int} [limits, vai tas ir 1. posma limits (1|null)]
 */
function gr_lappusu_limits(string $txt): array {
    // Teikumos: punkts vai semikols ir robeža, lai "20 pages; ... 50 pages" nesajuktu.
    $teikumi = preg_split('/(?<=[.;])\s+/', $txt) ?: [];

    // 1. Vispirms divu posmu forma, jo tā ir vēl konkrētāka par tēmas atkāpi.
    $pirma = null;
    if (preg_match('/first stage[^.;]{0,90}?\b(?:is|of|to)\s+(\d{1,3})\s*pages?/i', $txt, $m)) {
        $n = (int)$m[1];
        if ($n >= 2 && $n <= 200) $pirma = $n;
    }
    if ($pirma !== null && preg_match('/second stage[^.;]{0,90}?\b(?:is|of|to)\s+(\d{1,3})\s*pages?/i', $txt)) {
        return [$pirma, 1];
    }

    // 2. TĒMAS ATKĀPE no vispārīgā limita. "increased to N", "extended to a total maximum
    //    of N" ir apzināts šīs tēmas pārrakstījums, tāpēc tas ir stiprāks signāls par
    //    vispārīgiem pieminējumiem tajā pašā tekstā. Bez šī vienam CL5 konkursam pazuda
    //    īstais limits 60, jo blakus teikumā NOTE piemin 65 lpp. citam darbības veidam,
    //    un divi kandidāti viens otru izslēdza.
    //    Uzmanību: "to" un "by" te ir pretstati — "extended TO 60" ir limits, "extended
    //    BY 3" ir tikai pieaugums, ko zemāk atmet.
    $atkape = [];
    if (preg_match_all('/\b(?:increased|extended|reduced|raised)\s+to\s+(?:a\s+total\s+maximum\s+of\s+)?(\d{1,3})\s*(?:format\s*A4\s*)?pages?/i', $txt, $mm)) {
        foreach ($mm[1] as $v) { $n = (int)$v; if ($n >= 2 && $n <= 200) $atkape[$n] = true; }
    }
    if (count($atkape) === 1) return [array_key_first($atkape), null];

    $kandidati = [];
    foreach ($teikumi as $t) {
        if (stripos($t, 'page') === false) continue;
        // Pieauguma forma: skaitlis nav limits. Teikumu izlaiž veselu.
        if (preg_match('/\b(?:extend|extended|increase|increased|reduce|reduced)\s+by\s+\d{1,3}\s*pages?/i', $t)) continue;
        // Atsauces forma: "described in section 5 of the call document" — tur skaitlis ir
        // sadaļas numurs. To jau tagad neķer, jo prasām "pages" tūlīt aiz skaitļa, bet
        // skaidrības labad izslēdzam arī tieši.
        if (preg_match('/described in (?:section|annex|rules)/i', $t) && !preg_match('/\d{1,3}\s*pages?/i', $t)) continue;
        $sasaistes = [
            // 110, ne 80: darbības veida nosaukums teikuma vidū mēdz būt garš, un CSA formā
            // ("…of the Coordination and Support Action (CSA) application using lump sum is
            // 28 pages") atstarpe ir 87 rakstzīmes. Ar 80 vārts nostrādāja RIA formai un
            // klusēja CSA formai — 3 tēmas palika bez limita, kaut tekstā tas ir.
            '/\bpage limits?\b[^.;]{0,110}?\b(?:is|are|of|to)\s+(\d{1,3})\s*(?:format\s*A4\s*)?pages?/i',
            '/\blimits?\b[^.;]{0,60}?\b(?:is|are)\s+(\d{1,3})\s*(?:format\s*A4\s*)?pages?/i',
            '/\blimited to\b[^.;]{0,40}?(\d{1,3})\s*(?:format\s*A4\s*)?pages?/i',
            '/\b(?:maximum(?:\s+of)?|no more than|up to|not exceeding)\s+(\d{1,3})\s*(?:format\s*A4\s*)?pages?/i',
        ];
        foreach ($sasaistes as $re) {
            if (preg_match_all($re, $t, $mm)) {
                foreach ($mm[1] as $v) {
                    $n = (int)$v;
                    if ($n >= 2 && $n <= 200) $kandidati[$n] = true;
                }
            }
        }
    }
    $kandidati = array_keys($kandidati);
    // Vairāki atšķirīgi limiti bez posmu norādes = neviennozīmīgi. Nerādām neko.
    return count($kandidati) === 1 ? [$kandidati[0], null] : [null, null];
}

/**
 * Galvenā funkcija: no diviem HTML laukiem izvelk strukturētās sadaļas.
 *
 * @return array{objective:?string,outcome:?string,scope:?string,eligibility:?string,
 *               specific:?string,trl:?string,page_limit:?int,page_limit_stage:?int}
 */
function gr_teksta_sadalas(string $descHtml, string $condHtml): array {
    $r = ['objective' => null, 'outcome' => null, 'scope' => null,
          'eligibility' => null, 'specific' => null, 'trl' => null,
          'page_limit' => null, 'page_limit_stage' => null];

    $d = gr_description_sadalas($descHtml);
    foreach (['objective', 'outcome', 'scope'] as $k) {
        if (!empty($d[$k]) && strlen(gr_html_text($d[$k])) >= 40) $r[$k] = gr_sanitize_html($d[$k]);
    }

    $c = gr_conditions_sadalas($condHtml);
    foreach ($c as $title => $body) {
        $txt = gr_html_text($body);
        if (str_contains($title, 'other eligib') && !gr_tikai_atsauce($txt)) {
            $r['eligibility'] = gr_sanitize_html($body);
        } elseif (str_contains($title, 'specific conditions') && strlen($txt) > 150 && !gr_tikai_atsauce($txt)) {
            $r['specific'] = gr_sanitize_html($body);
        } elseif (str_starts_with($title, 'admissib')) {
            [$r['page_limit'], $r['page_limit_stage']] = gr_lappusu_limits($txt);
        }
    }

        // TRL. Divi slazdi, abi atrasti reālos datos:
    //  · GLOSĀRIJS. IHI tēmās pirmais pieminējums ir definīcija ("The Technology Readiness
    //    Level (TRL) is a scale (from TRL 1 to TRL 9)…"), un no tās sanāca TRL 1 — zemākais
    //    iespējamais līmenis, lai gan tēma prasa 3–5. Definīcijas teikumu izņemam.
    //  · DIAPAZONS DIVOS TEIKUMA GALOS. "start at TRL 5 and achieve TRL 7" deva tikai 5;
    //    29 tēmās mērķa līmenis pazuda. Tagad tas kļūst par 5–7.
    $trlSrc = gr_html_text(($d['trl_text'] ?? '') . ' ' . $descHtml);
    $trlSrc = (string)preg_replace('/[^.]*Technology Readiness Level[^.]*\bscale\b[^.]*\./i', ' ', $trlSrc);
    if (preg_match('/\bTRL\s*(\d)\b[^.]{0,70}?\b(?:achiev\w*|reach\w*|end\w*\s+at)\s+(?:a\s+)?TRL\s*(\d)\b/i', $trlSrc, $m)) {
        $r['trl'] = $m[1] === $m[2] ? $m[1] : $m[1] . '–' . $m[2];
    // "TRL 3 to TRL 5" (ar atkārtotu TRL) ir tikpat bieža forma kā "TRL 3-5"; bez neobligātā
    // otrā TRL no tās sanāca tikai apakšējais gals.
    } elseif (preg_match('/\bTRL\s*(\d)(?:\s*(?:[–\-]|to)\s*(?:TRL\s*)?(\d))?/i', $trlSrc, $m)) {
        $r['trl'] = $m[1] . (isset($m[2]) && $m[2] !== '' ? '–' . $m[2] : '');
    }
    return $r;
}
