<?php
/**
 * granti/lib/grantu_filtrs.php — AUTOMĀTISKS ŠĶĪRĒJS: grants vai nav grants.
 *
 * KĀPĒC. Sadaļa saucas "Granti — projektu finansējums, kas nav jāatmaksā", bet avotu
 * katalogos līdzās grantiem dzīvo vēl četras lietas, kas tajos saucas tāpat vārdā "atbalsts":
 * aizdevumi ar kapitāla atlaidi (LIAA), iepirkumi (LAD pērk konsultācijas), likmju shēmas
 * (piens skolai — 6,61 € uz skolēnu) un sadaļu virslapas bez konkursa. 2026-09-11 auditā
 * no 32 LIAA mezgliem granti bija 19, no 22 LAD kārtām — 19. Bez šķīrēja sadaļa solītu
 * finansējumu, kas ir jāatmaksā, vai konkursu, kura nav.
 *
 * KĀPĒC KĀRTULAS, NE MI. Šķīrums ir leksisks un strukturāls: avota lapa pati nosauc
 * instrumentu ("Aizdevums līdz 30%", "Iepirkuma numurs", "Projektu atlases kritēriji").
 * Kārtulas ir bezmaksas, atkārtojamas, un tās var izmērīt pret marķēto kopu. MI paliek
 * rezervē tikai "nezinu" joslai (sk. gr_filtrs_nezinamie()).
 *
 * TRĪS IZNĀKUMI, NE DIVI. 'nezinu' nav neveiksme: tā ir atzīšanās, ka pazīmju nepietiek.
 * Šie ieraksti iet cilvēkam vai MI, nevis klusi vienā vai otrā pusē. Kļūdu cena ir
 * asimetriska — ne-grants sarakstā maldina cilvēku, kurš uz to piesakās; izmests grants
 * vienkārši nav redzams —, tāpēc vārti ir stingrāki pret ienākšanu nekā pret izmešanu.
 */
declare(strict_types=1);

/**
 * Veto pazīmes. VISAS prasa INSTRUMENTA kontekstu, ne tikai vārdu kaut kur lapā — pirmā
 * redakcija izmeta divus īstus grantus: "Inovāciju vaučeri" (tekstā bija "garantijas
 * vēstule" kā norēķinu mehānisms) un "Atbalsts inovāciju iepirkuma ietvaros" (grants sedz
 * 50 % no iepirkuma līgumcenas — vārds "iepirkums" tur ir projekta priekšmets, ne veids).
 */
const GR_F_VETO = [
    // Aizdevumu atpazīst pēc tā, ka instruments TĀ saucas: "Aizdevums līdz 30%",
    // "Atbalsta veids: kapitāla atlaide". Garantijas vēstule un garantijas termiņš ir
    // norēķinu un kvalitātes jēdzieni, ne finansējuma veids.
    // NOLIEGUMS NAV INSTRUMENTS. Nolikumos mēdz būt "finansējumu nedrīkst apvienot ar
    // aizdevumu" un "atbalsts nav apvienojams ar garantijas programmu" — tie ir izslēgšanas
    // teikumi par CITU instrumentu, un pirmā redakcija tos lasīja kā pašu instrumentu.
    // Tāpēc: instrumenta vārdam jāstāv teikuma sākumā vai tūlīt aiz "atbalsta veids/forma",
    // un starp tiem nedrīkst būt nolieguma vārds. \D{0,40} vietā [^.\n]{0,40} — punkts un
    // jaunrinda vairs neļauj pārlēkt teikuma robežai.
    'aizdevums' => '~(?:^|[.;:]\s*|atbalsta (?:veids|forma)[^.\n]{0,30})'
                 . '(?!(?:[^.\n]{0,40})?(?:nedrīkst|nav apvienojam|nesavienojam|neattiecas))'
                 . '\s*(aizdevum\w*|kapitāla atlaid\w*|kredītprocentu dzēšan\w*|līzing\w*)~ui',
    'garantija' => '~(?:^|[.;:]\s*|atbalsta (?:veids|forma)[^.\n]{0,30})\s*garantij'
                 . '(?!as vēstul|as termiņ|as remont)\w*\s+(?:līdz|apmērā|instrument)~ui',
    // Iepirkumu nodod TABULAS KOLONNAS, ne vārds tekstā: "Līgumcena" kā aile stāv atsevišķi
    // (aiz tās uzreiz nāk summa), bet "Projekta līgumcena nedrīkst pārsniegt" ir parasts
    // teikums, kas pirmajā redakcijā izmeta veselu grantu.
    // "Paredzamā līgumcena 91747.46" ir iepirkumu saraksta aile (CFLA /lv/iepirkumi, ne-grantu kopa 2026-09-15) — arī
    // tur summa stāv tūlīt aiz ailes vārda; "grants sedz 50 % no paredzamās līgumcenas" to neizpilda.
    'iepirkums' => '~(iepirkuma\s+(?:numurs|identifikācijas numurs)'
                 . '|(?:^|[|\n]\s*)Līgumcena\s*[:|]?\s*\d|paredzamā\s+līgumcena\s*[:|]?\s*\d)~ui',
    // AIZDEVUMS TEIKUMA VIDŪ (recenzents K3). ALTUM lapa: "… aizdevumu ar kapitāla atlaidi …" —
    // instruments nosaukts teikuma vidū, un sākuma kārtula to palaida garām. Tikai frāzes, kas
    // apraksta PAŠU instrumentu; noliegumi ("nevar saņemt aizdevuma veidā") izslēgti.
    'aizdevums_teksta' => '~aizdevum\w*\s+ar\s+kapitāla\s+atlaid\w*|kapitāla\s+atlaid\w*\s+(?:veidā|apmērā)'
                 . '|(?:sniedz|piešķir|izsniedz|piedāvā)\w*\s+aizdevum\w*'
                 . '|aizdevum\w*\s+(?:veidā|apmērā\s+līdz|procentu\s+likm)~ui',
    // INSTRUMENTS NOSAUKTS AIZ 600 ZĪMJU LOGA (2026-09-17, atrasts dzīvajā būvē). ALTUM "Ekonomikas ministrija izsludina atklātu
    // projektu iesniegumu atlases kārtu …": teikums "Finansējums būs pieejams aizdevumu ar kapitāla atlaidi veidā" ir 760. zīmē
    // (pirms tā datums, birka un MK noteikumu nosaukums), un lapa ar 35 milj. € aizdevumu programmu stāvēja sadaļā kā grants.
    // Tikai šī VIENA, viennozīmīgā frāze ("… veidā"), un tikai pirmajās 1500 zīmēs — platākās aizdevuma frāzes paliek 600 zīmju logā.
    'aizdevums_kapitala_veida' => '~aizdevum\w*\s+ar\s+kapitāla\s+atlaid\w*\s+veidā~ui',
    // "samazināta nodokļa likme": LAD "Samazinātas nodokļa likmes piemērošana dabasgāzei" līdz 2026-09-17 izkrita pirms šķīrēja
    // (lapā nebija nolasāma kārtas intervāla); kopš intervālus lasa visus, to aptur šis veto, nevis "nezinu" + MI.
    'nodoklu_atlaide' => '~\b(nodokļa atlaid|akcīzes nodokļa atmaks|nodokļu atmaks|samazināt\w*\s+(?:akcīzes\s+)?nodokļa\s+likm)~ui',
    // IZSOLE nav grants nekādā nozīmē: "izsole par grants ieguves tiesībām", nomas tiesību izsoles.
    'izsole' => '~\bizsol(?:e|es|ē|i|ei|u)\b[^.\n]{0,100}(?:tiesīb|nomas|īpašum|nosacīt\w*\s+(?:sākum)?cen|objekt|transportlīdzek|nekustam)'
              . '|nosacītā\s+(?:sākum)?cena|izsoles\s+(?:noteikum|solis|dalībniek|sākum)~ui',
    // GRANTS KĀ MATERIĀLS TIRDZNIECĪBĀ: "grants ieguves tiesības", "grants piegāde pagasta ceļiem" — izsole vai iepirkums.
    // TIKAI komerciālā darbība, ne materiāls projektā: pirmā redakcija ar "smilts un grants" izmeta īstu LAD grantu
    // "LA 4.5. Neienesīgās investīcijas – Mākslīgu mitrāju izveide" (būvmateriāli aprakstā; būve 2026-09-13).
    'grants_materials' => '~\b(?:grant[su]?|šķemb\w*|smilt\w*)\s+(?:un\s+\p{L}+\s+)?(?:ieguves\s+tiesīb\w*|piegād\w*)~ui',
    // VIENREIZĒJS SOCIĀLAIS ATBALSTS nav projekta finansējums ("vienreizējam atbalstam senioriem … 100 EUR" — recenzents 2).
    // Veto, NE pazīmes "vienreizējs maksājums" izņemšana: to izņemot, LAD "Atbalsts ciltsdarba pasākumiem" kļuva par "nezinu".
    'socialais_pabalsts' => '~vienreizēj\w*\s+(?:pabalst\w*|atbalst\w*|maksājum\w*)\s+(?:senioriem|pensionāriem|ģimenēm|bērniem|jaundzimuš\w*|represēt\w*|daudzbērnu\w*|trūcīg\w*|maznodrošināt\w*)'
              . '|\bpabalst\w*\s+(?:senioriem|pensionāriem|ģimenēm|trūcīg\w*|maznodrošināt\w*)~ui',
    // RADOŠIE UN SKOLĒNU KONKURSI ar "naudas balvām 100 EUR" nav projektu finansējums.
    // "projektu konkurss bērniem … nometņu organizēšanai" IR grants (organizētājam) — tāpēc tikai nosaukti radošo darbu veidi.
    'radosais_konkurss' => '~(?:radošo\s+darbu|zīmējumu|fotogrāfiju|foto|eseju|video|literāro\s+darbu|dzejas|zīmēšanas)\s*konkurs\w*~ui',
    // AMATA KONKURSS. Pašvaldību vietnēs vārds "konkurss" visbiežāk ir vakance: "izsludina
    // konkursu uz VPII Strautiņš vadītāja amatu" (ar mēnešalgu 1 300 €, ko summas vārts
    // nolasa kā finansējumu). Tas nav grants nekādā nozīmē.
    // Tikai KANONISKĀ vakances frāze. Pirmā redakcija veto lika arī uz atsevišķiem vārdiem
    // "vakance", "mēnešalga", "darba vieta" — un izmeta VISAS 8 atvērtās CFLA atlases, jo CFLA
    // lapas izvēlnē ir saite "Vakances", bet ES fondu projektu rādītājos — "radītās darba
    // vietas". Algas vārdi paši par sevi neizšķir: grants var segt darbinieku algas.
    // "amat" tikai vārda "amats" locījumos (\bamat\w* ķēra "amatiermākslas", "amatniecības" — M4), un
    // "konkurss uz … direktora vietu" arī ir vakance (recenzents K3: "Pretendentu atlases nolikums").
    'amata_konkurss' => '~konkurs\w*\s+(?:uz|par)\s+[^.\n]{0,60}?(?:(?:vadītāj|direktor|speciālist|vietniek|darbiniek|pārvaldniek|inspektor|skolotāj|metodiķ|referent|grāmatved|juriskonsult|sekretār|priekšsēdētāj)\w*\s+amat(?:u|a|am|ā|s)\b|\bamata\s+(?:vietu|vieta|pienākum\w*)|vakan\w*'
                      . '|(?:direktor|vadītāj|speciālist|vietniek|darbiniek|pārvaldniek|inspektor|skolotāj|metodiķ|referent|grāmatved|juriskonsult)\w*\s+viet(?:u|as)\b)~ui',
];
/**
 * Veto, ko meklē TIKAI nosaukumā, lapas pirmajās 600 zīmēs un finansējuma laukā. Recenzents 2: skenējot visu saturu, sānjoslas
 * "bērnu zīmējumu konkurss" vai teikums "ja projekta īstenošanai saņem aizdevumu kredītiestādē" izmeta īstus grantus.
 */
const GR_F_VETO_SAKUMAM = ['aizdevums_teksta', 'izsole', 'grants_materials', 'radosais_konkurss', 'socialais_pabalsts'];
/** Veto, ko meklē platākā sākuma logā (1500 zīmes): frāze ir pietiekami viennozīmīga, bet lapas galva mēdz būt gara. */
const GR_F_VETO_PLATS_SAKUMS = ['aizdevums_kapitala_veida'];
/**
 * Veto, ko meklē TIKAI VIRSRAKSTĀ. Legālā ne-grantu kopa (test_data/granti/ne_granti, 2026-09-15) atrada lapas, kurās
 * granta pazīmes ir, bet virsraksts nosauc KO CITU: ALTUM "Mājokļu garantija ģimenēm ar bērniem" ("grantam" tikai
 * veidlapas nosaukumā), LEADER "Konsultācijas par … projektu konkursa V kārtu" (konkurss ir tas, PAR ko konsultē),
 * Jelgavas "Pašvaldības atbalsta programmas jelgavniekiem" un KM "Finansējuma iespējas" (pārskats ar vairākiem
 * konkursiem), CFLA "Iepirkumi" (iepirkumu nosaukumos "projektu iesniegumu vērtēšanai").
 * Vārdam jāstāv virsraksta SĀKUMĀ un nominatīvā: "Atbalsts konsultāciju pakalpojumiem", "Konsultāciju pakalpojumu atbalsts"
 * un "Atbalsta programmas nolikums" (vienskaitļa ģenitīvs + galvas vārds) neatbilst. Pirms ieviešanas pārbaudīts pret
 * 1795 DB un tulkojumu virsrakstiem un 465 keša lapām: trāpīja tikai saraksti, garantijas, aizdevumi un iepirkumi.
 */
const GR_F_VETO_VIRSRAKSTAM = [
    // Instruments ir galvenais vārds (līdz trim vārdiem pirms tā): "Eksporta kredīta garantijas …", "Īstermiņa aizdevums no ISIP".
    // Virsraksts ar granta vārdu ("Granti un aizdevumi …") ir jaukts piedāvājums — to izlaiž gr_filtrs().
    'aizdevums_virsraksta' => '~^(?:[\p{L}\d"„“”«».,:()-]+\s+){0,3}?(?:garantij(?:a|as)|aizdevum(?:s|i)|kredīt(?:s|i|līnija|līnijas)'
                            . '|mikrokredīt(?:s|i)|līzings|faktorings)\b~ui',
    // OTRĀ ALTERNATĪVA (audits 2026-09-16): pasākums PAR konkursu arī ģenitīvā — "Konsultāciju dienas par projektu konkursu".
    // Īstu konkursu no tās pasargā gr_filtrs() izņēmums: ja virsrakstā ir "konkurss" nominatīvā, veto nenostrādā
    // ("Atbalsts semināriem par uzņēmējdarbību – projektu konkurss"). Mutantam jāizslēdz ABAS alternatīvas.
    'pasakums' => '~^(?:(?:bezmaksas|individuāl\w*|informatīv\w*|tiešsaistes|klātienes|praktisk\w*)\s+)*'
                . '(?:konsultācij(?:a|as)|seminār(?:s|i)|vebinār(?:s|i)|informācijas\s+dien(?:a|as)|info\s*dien(?:a|as)'
                . '|informatīv\w*\s+(?:pasākum\w*|sanāksm\w*|dien\w*)|tikšanās|darbnīc(?:a|as)|apmācīb(?:a|as)|mācīb(?:a|as))\b'
                . '|^(?:\p{L}+\s+){0,2}?(?:konsultācij\w*|seminār\w*|vebinār\w*|apmācīb\w*|mācīb\w*|darbnīc\w*|tikšan\w*|informatīv\w*\s+dien\w*)'
                . '\s+(?:\p{L}+\s+){0,2}?par\s+[^.\n]{0,60}?(?:konkurs\w*|kārt\w*|projektu\s+iesniegum\w*|pieteikum\w*)~ui',
    // Daudzskaitlis BEZ galvas vārda aiz tā: aiz "programmas/iespējas/konkursi" drīkst būt tikai adresāts datīvā
    // ("jelgavniekiem", "nevalstiskajām organizācijām") vai gads. Trešā alternatīva — sadaļas virslapa "Konkursi un granti".
    'parskata_lapa' => '~^(?:(?:(?:pašvaldības|valsts|pieejamās|aktuālās|citas)\s+)?(?:atbalsta|finansējuma|finanšu|līdzfinansējuma|grantu)\s+(?:programmas|iespējas|avoti|instrumenti)'
                     . '|(?:izsludinātie|aktuālie|pieejamie|atvērtie|projektu)\s+(?:projektu\s+)?konkursi'
                     . '|(?:konkursi|granti|stipendijas)\s+un\s+(?:granti|konkursi|stipendijas|atbalsts))'
                     . '(?:\s+(?:un\s+)?(?:\p{L}+(?:iem|ām)|20\d{2}\.?(?:\s*gad\p{L}*)?))*\s*$~ui',
    'iepirkums_virsraksta' => '~^(?:(?:publiskie|aktuālie|aktīvie|izsludinātie)\s+)?iepirkumi\b|^iepirkum(?:u|a)\s+(?:plāns|saraksts|rezultāti|procedūr\w*|paziņojum\w*)\b~ui',
    // ZIŅU SARAKSTA VIRSRAKSTS (neredzētā kontrolkopa 2026-09-16: jurkante.lv/category/jaunumi/ ar īstu paziņojumu izvilkumā).
    // Vārdi tie paši, ko gr_vt_ir_saraksta_virsraksts() vietņu vārtos. TIKAI $nos kandidātam (sk. gr_filtrs): reālu paziņojumu
    // lapās <h1> mēdz būt sadaļas nosaukums ("Aktualitātes" — Dobeles partnerība), un plašāka kārtula izmestu īstu kārtu.
    'zinu_saraksts' => '~^(?:[\p{L}\p{N}"“”„.-]+\s+){0,4}(?:jaunumi|aktualitātes|ziņas|sludinājumi|notikumi|publikācijas|arhīvs)'
                     . '(?:\s*\(?\s*20\d{2}(?:\s*[-–—]\s*20\d{2})?\s*\)?)?\s*$~ui',
];
/** Veto, ko pārbauda TIKAI lapas virsrakstā ($nos), ne <h1>/<title>/satura sākuma kandidātos. */
const GR_F_VETO_TIKAI_NOS = ['zinu_saraksts'];
/** Virsraksta veto → [veids (tie paši vārdi, ko MI uzvedne un būves žurnāls), skaidrojums]. */
const GR_F_VIRSRAKSTA_VEIDS = [
    'aizdevums_virsraksta' => ['aizdevums_garantija', 'Virsraksts nosauc atmaksājamu instrumentu vai garantiju — tas nav neatmaksājams projektu grants.'],
    'pasakums'             => ['pakalpojums_bez_naudas', 'Virsraksts ir pasākums PAR konkursu (konsultācijas, seminārs, mācības), ne pats konkurss.'],
    'parskata_lapa'        => ['virslapa', 'Virsraksts ir pārskata lapa ar vairākām programmām vai konkursiem — katrs ir savā lapā.'],
    'iepirkums_virsraksta' => ['iepirkums', 'Virsraksts ir iepirkumu saraksts — iestāde pērk pakalpojumus, nevis piešķir finansējumu.'],
    'zinu_saraksts'        => ['virslapa', 'Virsraksts ir ziņu vai sludinājumu saraksts — konkurss ir atsevišķā lapā.'],
];
/**
 * PASĀKUMS PAR KONKURSU TEKSTĀ (audits 2026-09-16). Brasla lapā `<h1>` ir logo un `<title>` ir "Blog Post", tāpēc neviens
 * virsraksta kandidāts nerāda, ka lapa ir par konsultācijām: "Vidzemes lauku partnerība “Brasla” aicina uz konsultācijām
 * par izsludināto LEADER projektu konkursa V kārtu". Ķer TIKAI uzaicinājumu uz pasākumu PAR konkursu (darbības vārds +
 * pasākums + "par" + konkurss vai kārta), un tikai tad, ja neviens virsraksta kandidāts pats neizsludina konkursu:
 * īstā kārtas ziņā teikums "Aicinām uz konsultācijām par kārtas nosacījumiem" ir papildinājums, ne lapas būtība.
 */
const GR_F_PASAKUMS_TEKSTA = '~\b(?:aicin\w*|notiks|norisināsies|rīko\w*|organizē\w*|piedāvā\w*|piesakies|gaida\w*)\s+(?:\p{L}+\s+){0,4}?'
    . '(?:konsultācij\w*|seminār\w*|vebinār\w*|tīmekļseminār\w*|apmācīb\w*|mācīb\w*|darbnīc\w*|tikšan\w*|informatīv\w*\s+(?:pasākum\w*|dien\w*))'
    . '\s+(?:par|pirms)\s+[^.\n]{0,90}?(?:konkurs\w*|kārt[au]\w*|projektu\s+iesniegum\w*|pieteikum\w*)~ui';
/** Lapa pati izsludina konkursu — tad pasākuma teikums tekstā ir papildinājums, ne lapas būtība. */
const GR_F_PATS_IZSLUDINA = '~izsludin|paziņojum\w*\s+par\s+atklāt|pieņemšanas\s+(?:\d+\.|[IVX]+)\s*kārt|iesniegumu\s+konkurs~ui';
/**
 * SAISTĪTO ZIŅU BLOKS ir citu lapu virsraksti un izvilkumi, tāpēc granta pazīmes tajā nav šīs lapas pazīmes. Rēzeknes
 * partnerības ziņai "Neformālās apmācības jauniešiem" satura tekstā nav vispār (virsraksts, datums, "Citas ziņas" un citu
 * ziņu izvilkumi), un abas pieteikuma pēdas nāca no 10. kārtas izvilkuma. Tās pašas vietnes 10. kārtas ziņā bloks sākas aiz
 * satura (2804. no 4520 zīmēm) — griezums pēdas neņem. Tikai bloka virsraksta forma ar lielo sākumburtu: "… lasiet citas
 * ziņas par …" teikumā nav bloks. Veto kārtulas griezums neskar (tās ir virsrakstā, sākumā vai prasa instrumenta kontekstu).
 */
const GR_F_SAISTITAS_ZINAS = '~\b(?:Citas|CITAS|Saistītās|SAISTĪTĀS|Līdzīgās|Līdzīgas|LĪDZĪGĀS|Jaunākās|JAUNĀKĀS|Pēdējās|PĒDĒJĀS)\s+(?:ziņas|ZIŅAS)\b'
    . '|\b(?:Citi|CITI|Saistītie|SAISTĪTIE|Līdzīgie|LĪDZĪGIE|Jaunākie|JAUNĀKIE)\s+(?:jaunumi|raksti|JAUNUMI|RAKSTI)\b|\bVairāk\s+(?:ziņu|jaunumu)\b~u';

/**
 * Virsraksta varianti virsraksta veto kārtulām: pats virsraksts un — ja priekšā ir vietnes vārds (<title> bez <h1>:
 * "Abulas - Konsultācijas …") — tā turpinājums. Priedēklis ir vietnes vārds tikai tad, ja tas ir īss (≤ 3 vārdi), bez
 * cipariem un bez konkursa vārdiem: "Projektu konkurss - seminārs dalībniekiem" nav vietnes vārds + pasākums.
 */
function gr_f_virsraksti(string $nos): array {
    $nos = trim(preg_replace('~\s+~u', ' ', $nos) ?? $nos);
    $o = [$nos];
    if (preg_match('~^([^|–—]{2,40}?)\s+[|–—-]\s+(\S.{4,})$~u', $nos, $m) && count(explode(' ', trim($m[1]))) <= 3
        && !preg_match('~\d|konkurs|grant|kārt|atbalst|projekt|izsludin|pieteik|finansēj|programm~ui', $m[1])) $o[] = trim($m[2]);
    return $o;
}

/** Teksts līdz saistīto ziņu blokam (sk. GR_F_SAISTITAS_ZINAS); ja bloka nav — viss teksts. */
function gr_f_bez_saistitajam(string $t): string {
    return preg_match(GR_F_SAISTITAS_ZINAS, $t, $m, PREG_OFFSET_CAPTURE) ? substr($t, 0, $m[0][1]) : $t;
}
/**
 * Avoti, kuru katalogs ir VIENVEIDĪGS: tur viss ir grantu konkursi, un to nosaka avots pats,
 * ne mūsu minējums. ES portāls: type=1 plūsmā ir tikai grantu uzsaukumi. SIF, VKKF un JSPA
 * publicē tikai projektu konkursus. Jauktie katalogi (LIAA, LAD, CFLA) prasa pierādījumu
 * tekstā — tieši tajos dzīvo aizdevumi, iepirkumi un likmju shēmas.
 */
const GR_F_VIENVEIDIGI = ['EU' => 1, 'SIF' => 1, 'VKKF' => 1, 'JSPA' => 1];
/** Granta pazīmes: pieteikums, ko vērtē. */
const GR_F_PLUS = [
    // "izsludina LEADER projektu iesniegšanas 5. kārtu", "projektu iesniegumu pieņemšanas VI kārta" — pieteikšanās KĀRTA ir
    // tā pati pēda, kas iesniegums (Daugavkrasts 2026-09-13: bez šīs formas kārtulas deva "nezinu" un ierakstu turēja MI).
    'iesniegums' => '~projekt(?:a|u|iem)?\s+iesniegum|projektu\s+(?:iesniegšanas|pieņemšanas)\s+(?:\d+\.\s*|[IVX]+\s+)kārt~ui',
    'atlase'     => '~projektu atlases kritērij|atlases nolikum|sarindo.{0,30}projekt~ui',
    'konkurss'   => '~projektu konkurs|pieteikumu konkurs|call for proposals|uzsaukum~ui',
    // "GRANTS" LATVISKI IR ARĪ CEĻA SEGUMS. Cēsu sākumlapā: "Grants seguma ceļiem noteikts
    // transporta masas ierobežojums". Pašvaldību lapās grants ceļi, grants karjeri un
    // "grantēti" ceļi ir biežāki par finansējumu, tāpēc vārds "grants" skaitās pazīme TIKAI,
    // ja tam neseko seguma, ceļa vai karjera konteksts.
    'grants'     => '~\b((?:mikro)?grant(?:s|a|am|u|ā|i|iem|us|os)?\b(?!\s*(?:seguma|segum|ceļ|karjer|materiāl|atradn|ieguv|piegād|smilt|šķemb|un\s+šķemb))|dotācij|neatmaksājam|vienreizējs maksājums)~ui',
    // BALVAS IR GRANTI ŠAJĀ SADAĻĀ. ES pusē tām ir pat savs modelis (GR_MODELI 'balva':
    // Erasmus sporta balvas, WIDERA dzimumu balvas), tāpēc LV balvas izmest būtu divi
    // dažādi likumi vienā sarakstā. Prasām naudu tuvumā: vārds "balva" bez summas ir
    // atzinības raksts, ne finansējums.
    'balva'      => '~(naudas balva|balvu fond|balva\s+(?!20\d{2}\b)\d|balva[^.]{0,60}\d[\d\s]*\s*(?:EUR|€))~ui',
    'intensitate'=> '~atbalsta intensitāt|attiecināmo izmaksu \d|% no attiecināmaj~ui',
];
/** Pazīmes, kas norāda uz likmju shēmu bez pieteikuma vērtēšanas. */
const GR_F_SHEMA = '~likme (par|uz) (vienu |katru )?(skolēn|ha|hektār|dzīvniek|tonn|litr)|fiksēta likme uz~ui';
/** Teksti, kas nozīmē "naudas šeit nav". */
const GR_F_NAV_NAUDAS = '~^\s*(neattiecas|-|–|—)?\s*$~ui';

/**
 * Šķir vienu ierakstu.
 *
 * @param array $i ['avots','nosaukums','teksts','finansejums','sanemejs','termins','budzets']
 * @return array{verdikts:string,veids:string,pazimes:array,skaidrojums:string}
 */
function gr_filtrs(array $i): array {
    $avots = (string)($i['avots'] ?? '');
    $nos   = (string)($i['nosaukums'] ?? '');
    $fin   = (string)($i['finansejums'] ?? '');
    $tek   = $nos . "\n" . (string)($i['teksts'] ?? '') . "\n" . $fin . "\n" . (string)($i['sanemejs'] ?? '');
    $paz   = [];
    $sakums = $nos . "\n" . mb_substr((string)($i['teksts'] ?? ''), 0, 600) . "\n" . $fin;

    // 1) Naudas nav vispār (LIAA "neattiecas", tukšs finansējuma lauks).
    if ($fin !== '' && preg_match(GR_F_NAV_NAUDAS, $fin))
        return ['verdikts' => 'nav', 'veids' => 'pakalpojums_bez_naudas', 'pazimes' => ['fin:tukšs'],
                'skaidrojums' => 'Finansējuma lauks ir tukšs vai "neattiecas" — pakalpojums vai virslapa.'];

    // 2a) Virsraksta veto (sk. GR_F_VETO_VIRSRAKSTAM): lapa ir PAR instrumentu, pasākumu, pārskatu vai iepirkumiem.
    // KANDIDĀTI, NE TIKAI $nos (audits 2026-09-16): vietņu plūsma logo <h1> vai īsas <h1> gadījumā par virsrakstu ņem teikumu
    // no satura, un tad veto neredz ne "Konsultācijas 9.kārtas …" (abulas.lv <title>), ne "Finansējuma iespējas" (km.gov.lv
    // <h1> ar diviem vārdiem). Saucējs padod `virsraksti`: <h1>, tīrīto <title> un satura sākumu. Uz 9 kešotajām ierakstu
    // lapām neviens veto ar šiem kandidātiem nenostrādā (test_tools/granti/plusmas_virsraksti.php).
    $kandidati = [$nos];
    foreach ((array)($i['virsraksti'] ?? []) as $x) { $x = trim((string)$x); if ($x !== '' && !in_array($x, $kandidati, true)) $kandidati[] = $x; }
    foreach ($kandidati as $ki => $kand) foreach (gr_f_virsraksti($kand) as $v) foreach (GR_F_VETO_VIRSRAKSTAM as $veids => $re) {
        // Daļa veto ir tikai lapas virsrakstam: <h1> mēdz būt sadaļas nosaukums ("Aktualitātes"), ne lapas virsraksts.
        if ($ki > 0 && in_array($veids, GR_F_VETO_TIKAI_NOS, true)) continue;
        if (!preg_match($re, $v, $m)) continue;
        // "Granti un aizdevumi jaunajiem uzņēmējiem" — jaukts piedāvājums, ne tikai instruments.
        if ($veids === 'aizdevums_virsraksta' && preg_match('~grant|dotācij|neatmaksājam|subsīdij|projektu\s+konkurs~ui', $v)) continue;
        // AUDITS 2026-09-16. Pasākuma un pārskata veto neattiecas uz vienveidīgiem katalogiem — tur ir tikai konkursi (JSPA
        // "Mācības jaunatnes darbiniekiem", VKKF "Darbnīcas un meistarklases"). Instrumenta un iepirkuma veto paliek visiem.
        if (($veids === 'pasakums' || $veids === 'parskata_lapa') && isset(GR_F_VIENVEIDIGI[$avots])) continue;
        // Pasākuma vārds nav pasākums, ja virsraksts pats nosauc izsludināmo lietu NOMINATĪVĀ: "Darbnīcas bērniem –
        // līdzfinansējuma konkurss", "Tikšanās vietu labiekārtošanas projektu konkurss" ("tikšanās" ir arī ģenitīvs).
        // "Konsultācijas par … projektu konkursa V kārtu" to neizpilda — tur konkurss ir ģenitīvā. "atbalsts" apzināti nav
        // sarakstā: "Konsultācijas un atbalsts pieteikumu sagatavošanā" ir pakalpojums (ielaist ne-grantu ir dārgāk).
        if ($veids === 'pasakums' && preg_match('~\b(?:konkurss|mērķprogramma|grants|granti|dotācija)\b~ui', $v)) continue;
        [$veidsV, $kapec] = GR_F_VIRSRAKSTA_VEIDS[$veids];
        return ['verdikts' => 'nav', 'veids' => $veidsV, 'pazimes' => [$veids . ':' . mb_strtolower(trim($m[0]))],
                'skaidrojums' => $kapec . ' Virsraksts: "' . $v . '".'];
    }

    // 2b) Pasākums PAR konkursu tekstā, kad neviens virsraksta kandidāts to nerāda (sk. GR_F_PASAKUMS_TEKSTA).
    $patsIzsludina = false;
    foreach ($kandidati as $kand) if (preg_match(GR_F_PATS_IZSLUDINA, $kand)) { $patsIzsludina = true; break; }
    if (!$patsIzsludina && preg_match(GR_F_PASAKUMS_TEKSTA, $sakums, $m))
        return ['verdikts' => 'nav', 'veids' => 'pakalpojums_bez_naudas', 'pazimes' => ['pasakums_teksta:' . mb_strtolower(trim($m[0]))],
                'skaidrojums' => 'Lapa aicina uz pasākumu PAR konkursu, un pati konkursu neizsludina: "' . trim($m[0]) . '".'];

    // 2c) Veto pazīmes tekstā.
    foreach (GR_F_VETO as $veids => $re) {
        $hay = in_array($veids, GR_F_VETO_SAKUMAM, true) ? $sakums
             : (in_array($veids, GR_F_VETO_PLATS_SAKUMS, true) ? $nos . "\n" . mb_substr((string)($i['teksts'] ?? ''), 0, 1500) : $tek);
        if (preg_match($re, $hay, $m, PREG_OFFSET_CAPTURE)) {
            [$fr, $off] = $m[0];
            // NOLIEGUMS TAJĀ PAŠĀ TEIKUMĀ: "Atbalstu nevar saņemt aizdevuma veidā" saka, kas NAV atļauts.
            if (($veids === 'aizdevums_teksta' || $veids === 'aizdevums_kapitala_veida')
                && preg_match('~\b(?:nevar\w*|nedrīkst|netiek|neattiecas|nav|izņemot|bez)\b[^.\n]*$~u', mb_strcut($hay, max(0, $off - 80), min(80, $off), 'UTF-8'))) continue;
            $paz[] = $veids . ':' . mb_strtolower(trim($fr));
            // Nodokļu atlaide bieži ir PAPILDU rinda pie īsta granta ("grants + nodokļu
            // atvieglojumi"), tāpēc tā viena pati neizšķir — veto tikai bez granta pazīmēm.
            if ($veids === 'nodoklu_atlaide' && gr_f_ir_plus($tek)) continue;
            return ['verdikts' => 'nav', 'veids' => str_starts_with($veids, 'aizdevums') ? 'aizdevums_garantija' : $veids,
                    'pazimes' => $paz, 'skaidrojums' => 'Atrasta pazīme "' . $fr . '" — tas nav neatmaksājams projektu grants.'];
        }
    }

    // 3) Likmju shēma bez pieteikuma vērtēšanas. Granta pazīmes — bez saistīto ziņu bloka (GR_F_SAISTITAS_ZINAS):
    // tur ir CITU lapu konkursi.
    $irPlus = gr_f_plus_saraksts($nos . "\n" . gr_f_bez_saistitajam((string)($i['teksts'] ?? '')) . "\n" . $fin . "\n" . (string)($i['sanemejs'] ?? ''));
    if (preg_match(GR_F_SHEMA, $tek, $m) && !array_intersect(['iesniegums', 'atlase', 'konkurss'], array_keys($irPlus)))
        return ['verdikts' => 'nav', 'veids' => 'likmju_shema', 'pazimes' => ['shema:' . trim($m[0])],
                'skaidrojums' => 'Fiksēta likme uz vienību un nav ne iesnieguma, ne atlases — shēma, ne konkurss.'];

    // 4) Vienveidīgo avotu prioritāte — pēc veto, ne pirms tā: ja kādā dienā arī SIF
    // katalogā parādīsies aizdevums, veto to noķers, bet tukša teksta dēļ ierakstu
    // neizmetīsim.
    if (isset(GR_F_VIENVEIDIGI[$avots]))
        return ['verdikts' => 'grants', 'veids' => 'grants', 'pazimes' => array_merge($paz, ['avots:' . $avots]),
                'skaidrojums' => 'Avota katalogā ir tikai grantu konkursi, un veto pazīmju nav.'];

    // 5) Granta pazīmes.
    $paz = array_merge($paz, array_map(fn($k, $v) => "$k:$v", array_keys($irPlus), $irPlus));
    // Pieteikuma pēdas (iesniegums/atlase/konkurss) ir tā pazīme, kas šķir grantu no
    // visa pārējā; pašas par sevi "intensitāte" un vārds "grants" var būt arī aizdevuma
    // aprakstā, tāpēc ar tām vien pietiek tikai tad, ja ir arī termiņš.
    $stipras = array_intersect(['iesniegums', 'atlase', 'konkurss'], array_keys($irPlus));
    if ($stipras) return ['verdikts' => 'grants', 'veids' => 'grants', 'pazimes' => $paz,
        'skaidrojums' => 'Ir pieteikuma un atlases pēdas (' . implode(', ', $stipras) . ').'];

    $irTermins = ($i['termins'] ?? '') !== '' && $i['termins'] !== null;
    // Konkrēta summa vai intensitāte finansējuma laukā ir pierādījums, ka atbalsts vispār
    // ir nauda (pretstatā "neattiecas" pakalpojumiem), un kopā ar termiņu ar to pietiek.
    $irApjoms = preg_match('~\d[\d\s\x{00A0}]*\s*(?:EUR|€|%)~u', $fin) === 1;
    if ($irApjoms) $paz[] = 'apjoms:' . mb_substr(trim($fin), 0, 30);
    if (($irPlus || $irApjoms) && $irTermins) return ['verdikts' => 'grants', 'veids' => 'grants', 'pazimes' => $paz,
        'skaidrojums' => 'Ir naudas apjoms un termiņš; atmaksas vai iepirkuma pazīmju nav.'];

    // 6) Nezinu: nav ne veto, ne pietiekamu granta pazīmju.
    return ['verdikts' => 'nezinu', 'veids' => 'cits', 'pazimes' => $paz,
            'skaidrojums' => 'Pazīmju nepietiek — jāapskata cilvēkam vai jāprasa MI.'];
}

function gr_f_plus_saraksts(string $t): array {
    $out = [];
    foreach (GR_F_PLUS as $k => $re) if (preg_match($re, $t, $m)) $out[$k] = mb_strtolower(trim($m[0]));
    // BALVA AR SĪKU SUMMU nav finansējums: "naudas balvas 100 EUR" skolēnu konkursā (recenzents K3).
    // Ja pie vārda "balva" nav nevienas summas (balvu fonds bez skaitļa), pazīme paliek.
    if (isset($out['balva']) && ($b = gr_f_balvas_summa($t)) !== null && $b < 1000) unset($out['balva']);
    return $out;
}

/** Lielākā summa eiro 120 zīmju attālumā no vārda "balva" (jebkurā pusē), vai null. */
function gr_f_balvas_summa(string $t): ?float {
    $max = null;
    if (preg_match_all('~balv\w*[^.]{0,120}?(\d[\d\s\x{00A0}.]*(?:,\d+)?)\s*(?:EUR|€|eiro)|(\d[\d\s\x{00A0}.]*(?:,\d+)?)\s*(?:EUR|€|eiro)[^.]{0,60}?balv~ui', $t, $mm, PREG_SET_ORDER))
        foreach ($mm as $m) {
            $sk = (string)(($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? ''));
            $v = (float)str_replace([' ', "\u{00A0}", '.', ','], ['', '', '', '.'], $sk);
            $max = max($max ?? 0.0, $v);
        }
    return $max;
}
function gr_f_ir_plus(string $t): bool { return gr_f_plus_saraksts($t) !== []; }

// ── MI atkāpe "nezinu" joslai ────────────────────────────────────────────────
/**
 * Kārtulas izšķir ~87 % ierakstu. Pārējiem prasām modelim — bet TIKAI vienreiz par katru
 * tekstu: verdikts glabājas kešā pēc satura jaucējkoda, tieši tāpat kā tulkojumi
 * (sk. lib/tulkojumi.php galvu). Tāpēc dienas būve par to pašu ierakstu vairs nemaksā, un
 * jauns ieraksts maksā ap 0,0001 €.
 *
 * KEŠS IR TAJĀ PAŠĀ FAILĀ, KAS TULKOJUMI. Iemesls tas pats: granti.sqlite katrā būvē tiek
 * pārrakstīta no nulles, tāpēc verdikts tur nepārdzīvotu nakti.
 */
// v2 (2026-09-12): uzvednē pielikta balvu kārtula. Versijas maiņa atmet vecos verdiktus —
// tie tapa ar citu definīciju, un "Eksporta un inovācijas balva" tur ir nepareizi izmesta.
const GR_FMI_VERSIJA = 'v2';
/**
 * IZMAKSU GRIESTI. Tulkošanai tie ir kopš pirmās dienas (2,50 €/dienā), šķīrējam tie
 * pirmajā redakcijā pietrūka: ja avots kādu nakti atdotu 500 nezināmus ierakstus, kods
 * tos visus aizsūtītu modelim bez neviena vārta. Viens šķīrums ir ~0,0001 €, tāpēc 200
 * izsaukumi dienā ir gan ar rezervi, gan robeža, aiz kuras kaut kas ir noticis nepareizi.
 */
const GR_FMI_DIENAS_IZSAUKUMI = 200;
/** Cik kļūmju pēc kārtas dienā, pirms MI šķirotājs apstājas pavisam. */
const GR_FMI_MAX_KLUMES = 5;

function gr_filtrs_mi_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    require_once __DIR__ . '/config.php';
    $p = new PDO('sqlite:' . granti_tulk_db_path());
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->exec('PRAGMA busy_timeout=10000');
    $p->exec("CREATE TABLE IF NOT EXISTS grantu_verdikti (
        hash TEXT PRIMARY KEY, avots TEXT, nosaukums TEXT,
        verdikts TEXT, veids TEXT, pamatojums TEXT,
        versija TEXT, kad TEXT)");
    // Mēģinājumu skaitītājs atsevišķi: verdiktu tabulā rinda rodas TIKAI sekmīgā ceļā, tāpēc
    // pret to skaitīti griesti nenostrādā tieši tad, kad tie vajadzīgi — kad katrs izsaukums
    // krīt (beigusies atslēga, 429), skaitītājs paliktu nullē un cikls turpinātos visu nakti.
    $p->exec("CREATE TABLE IF NOT EXISTS grantu_mi_skaititajs (diena TEXT PRIMARY KEY, megin INTEGER DEFAULT 0, klumes INTEGER DEFAULT 0)");
    return $pdo = $p;
}

/**
 * Atdod MI verdiktu vai null (ja atslēgas nav, modelis klusē vai atbilde nav saprotama).
 * Null nozīmē "paliek nezinu" — nekad neizdomā verdiktu no tā, ka izsaukums neizdevās.
 */
function gr_filtrs_mi(array $i, ?callable $log = null): ?array {
    $nos = (string)($i['nosaukums'] ?? '');
    $ievade = $nos . "\n" . (string)($i['finansejums'] ?? '') . "\n" . mb_substr((string)($i['teksts'] ?? ''), 0, 4000);
    $h = hash('sha256', GR_FMI_VERSIJA . "\x00" . $ievade);
    $db = gr_filtrs_mi_db();
    $st = $db->prepare("SELECT verdikts, veids, pamatojums FROM grantu_verdikti WHERE hash = ?");
    $st->execute([$h]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        return ['verdikts' => $r['verdikts'], 'veids' => $r['veids'],
                'pazimes' => ['mi:kešs'], 'skaidrojums' => (string)$r['pamatojums']];
    }
    // Dienas skaitītājs dzīvo tajā pašā kešā, tāpēc tas iztur procesa restartu un ir
    // redzams arī cilvēkam (SELECT ... WHERE kad LIKE '<diena>%').
    $diena = date('Y-m-d');
    $st2 = $db->prepare("SELECT megin, klumes FROM grantu_mi_skaititajs WHERE diena = ?");
    $st2->execute([$diena]);
    $sk = $st2->fetch(PDO::FETCH_ASSOC) ?: ['megin' => 0, 'klumes' => 0];
    if ((int)$sk['megin'] >= GR_FMI_DIENAS_IZSAUKUMI) {
        if ($log) $log('  ! MI šķirotājs: sasniegti dienas griesti (' . GR_FMI_DIENAS_IZSAUKUMI . ') — pārējie paliek "nezinu".');
        return null;
    }
    // PĀRTRAUCĒJS. Ja pēc kārtas krīt daudzi izsaukumi, avots ir nost (atslēga, kvota,
    // tīkls), un turpināt nozīmē katru nakti gaidīt 90 s taimautu reizes N. Tulkošanas pusē
    // tas pats princips ir gr_tulk_darbs() beigās.
    if ((int)$sk['klumes'] >= GR_FMI_MAX_KLUMES) {
        if ($log) $log('  ! MI šķirotājs: ' . $sk['klumes'] . ' kļūmes pēc kārtas — šonakt vairs nesauc.');
        return null;
    }
    $db->prepare("INSERT INTO grantu_mi_skaititajs (diena, megin) VALUES (?,1)
                  ON CONFLICT(diena) DO UPDATE SET megin = megin + 1")->execute([$diena]);
    require_once __DIR__ . '/../../registrs/mi/gemini_client.php';
    if (reg_gemini_key() === '') return null;

    $uzvedne = "Tu šķiro publiskā atbalsta ierakstus vietnei, kuras sadaļa saucas \"Granti — "
        . "projektu finansējums, kas nav jāatmaksā\".\n\n"
        . "GRANTS ir tad, ja izpildās visi trīs nosacījumi:\n"
        . "1) nav jāatmaksā (nav aizdevums, garantija, kapitāla atlaide, nodokļu atlaide);\n"
        . "2) piešķir pieteikuma kārtā ar termiņu, kur pieteikumus vērtē ierobežota budžeta ietvaros\n"
        . "   (nevis fiksēta likme uz vienību bez vērtēšanas, nevis iepirkums, kurā iestāde pērk pakalpojumu);\n"
        . "3) pieteikties var ārējs pieteicējs (uzņēmums, saimniecība, biedrība, pašvaldība, privātpersona).\n"
        . "Sadaļas virslapa bez konkrēta atbalsta arī NAV grants.\n"
        . "BALVA ar naudas fondu, ko piešķir pēc pieteikšanās un vērtēšanas, IR grants "
        . "(sadaļā tādas jau ir ES pusē).\n\n"
        . "Atbildi TIKAI ar JSON: {\"verdikts\":\"grants\"|\"nav\",\"veids\":\"grants\"|\"aizdevums_garantija\"|"
        . "\"iepirkums\"|\"likmju_shema\"|\"pakalpojums_bez_naudas\"|\"virslapa\"|\"cits\",\"pamatojums\":\"viens teikums\"}\n"
        . "Ja teksts neļauj izšķirt, verdikts ir \"nav\" — sadaļā drīkst nokļūt tikai tas, kas ir pierādīts.\n\n"
        . "IERAKSTS:\n" . $ievade;

    $atb = reg_gemini_generate($uzvedne, ['temperature' => 0, 'maxOutputTokens' => 300]);
    if ($atb === null) {
        if ($log) $log('  ! MI šķirotājs klusē: ' . reg_gemini_error_brief());
        $db->prepare("UPDATE grantu_mi_skaititajs SET klumes = klumes + 1 WHERE diena = ?")->execute([$diena]);
        return null;
    }
    // Rekursīvs šablons, ne mantkārīgais '~\{.*\}~s': ja modelis ap JSON uzraksta tekstu ar
    // vēl vienu figūriekavu, mantkārīgais satver no pirmās { līdz pēdējai } un neparsējas.
    $j = preg_match('~\{(?:[^{}]|(?R))*\}~s', $atb, $m) ? json_decode($m[0], true) : null;
    if (!is_array($j) || !in_array($j['verdikts'] ?? '', ['grants', 'nav'], true)) {
        // NEPARSĒJAMU ATBILDI KEŠO. Ar temperature=0 nākamais izsaukums dos to pašu, tāpēc
        // bez šī ieraksta par vienu un to pašu tekstu maksātu katru nakti mūžīgi.
        if ($log) $log('  ! MI šķirotājs: neparsējama atbilde — kešo kā "nav" (' . mb_substr(trim((string)$atb), 0, 60) . ')');
        $db->prepare("INSERT OR REPLACE INTO grantu_verdikti
            (hash, avots, nosaukums, verdikts, veids, pamatojums, versija, kad) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$h, (string)($i['avots'] ?? ''), mb_substr($nos, 0, 200), 'nav', 'cits',
                      'MI atbilde nebija saprotama; ieraksts sadaļā nenonāk, kamēr nemainās teksts vai uzvednes versija.',
                      GR_FMI_VERSIJA, date('c')]);
        return ['verdikts' => 'nav', 'veids' => 'cits', 'pazimes' => ['mi:neparsējams'],
                'skaidrojums' => 'MI atbilde nebija saprotama.'];
    }

    $ins = $db->prepare("INSERT OR REPLACE INTO grantu_verdikti
        (hash, avots, nosaukums, verdikts, veids, pamatojums, versija, kad) VALUES (?,?,?,?,?,?,?,?)");
    $ins->execute([$h, (string)($i['avots'] ?? ''), mb_substr($nos, 0, 200), $j['verdikts'],
        (string)($j['veids'] ?? 'cits'), mb_substr((string)($j['pamatojums'] ?? ''), 0, 400),
        GR_FMI_VERSIJA, date('c')]);
    return ['verdikts' => $j['verdikts'], 'veids' => (string)($j['veids'] ?? 'cits'),
            'pazimes' => ['mi:jauns'], 'skaidrojums' => (string)($j['pamatojums'] ?? '')];
}

/**
 * Pilnais šķīrums: kārtulas, un tikai "nezinu" gadījumā — MI (ja $arMi).
 * Bez MI "nezinu" paliek "nezinu", un saucējam jāizlemj pašam; būve to neieliek sadaļā,
 * bet pieraksta atsevišķā sarakstā, lai neviens ieraksts nepazustu klusi.
 */
function gr_filtrs_pilns(array $i, bool $arMi = false, ?callable $log = null): array {
    $r = gr_filtrs($i);
    // Bezsaistes mērījumu rīkiem (test_tools/granti/vietnes_no_kesa.php): tikai MI keša lasīšana, nekad API.
    if ($r['verdikts'] === 'nezinu' && !$arMi && is_callable($GLOBALS['gr_vt_mi_kesa_lasitajs'] ?? null))
        return ($GLOBALS['gr_vt_mi_kesa_lasitajs'])($i) ?? $r;
    if ($r['verdikts'] !== 'nezinu' || !$arMi) return $r;
    // MI atkāpe nedrīkst nogāzt izvilcēju: SQLite izņēmums (aizņemts fails, pilns disks) te
    // izlidotu cauri līdz gr_lv_savac() try-blokam un paņemtu līdzi VISAS jau savāktās šī
    // avota rindas — LAD gadījumā pēc simts izvilktām lapām.
    try { $mi = gr_filtrs_mi($i, $log); }
    catch (Throwable $e) { if ($log) $log('  ! MI šķirotājs kritis: ' . $e->getMessage()); $mi = null; }
    return $mi ?? $r;
}
