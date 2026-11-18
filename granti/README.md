# Granti — moduļa uzbūve un palaišana

Publiskā lapa ir `granti.php` docroot saknē; adreses `/granti/` un `/granti/{slug}`.
Šī mape ir viss pārējais.

    granti/bin/   CLI ieejas punkti (būve, tulkošana, cron)
    granti/lib/   bibliotēkas, ko lieto gan lapa, gan būve
    granti/db/    SQLite faili — izvietošana tos NESŪTA, serveris būvē pats
    granti/data/  slēdzenes, žurnāls, stāvokļa JSON, karodziņi, lejupielāžu kešs

## Avoti

| Avots | Ko dod | Kā |
|---|---|---|
| 🇪🇺 ES portāls (SEDIA) | ~630 konkursi ar budžetiem un aprakstiem | bulk JSON + topicDetails |
| 🇱🇻 SIF | ~83 konkursi | publiskais JSON API |
| 🇱🇻 CFLA | ~26 ES fondu atlases | HTML tabula `/lv/2021-2027-programmas` |
| 🇱🇻 VKKF | ~21 konkurss un mērķprogramma | HTML tabula `/lv/konkursu-grafiks` |
| 🇱🇻 JSPA | jaunatnes konkursi | `competition-sitemap.xml` + lapu parsēšana |
| 🇱🇻 LAD | lauku un zivsaimniecības kārtas | katalogs `/lv/katalogs` (facetes + lapošana) |
| 🇱🇻 LZP | zinātnes konkursi | valsts Drupal ziņu plūsma (JSON:API) |
| 🇱🇻 Rīgas dome | pašvaldības finansēšanas konkursi | tabulas četrās nozaru sadaļās, ar lapošanu |
| 🇱🇻 VALSTS: VIAA, NVA | Erasmus+ nacionālā daļa, nodarbinātība | ziņu plūsma (JSON:API) |
| 🇱🇻 PASV · LEADER · VALSTS | 9 pašvaldības, 33 LEADER grupas, 10 iestādes un fondi | `lib/vietnes.php` — vispārīgs meklētājs ar rotāciju |

### Vietnes bez struktūras (`granti/lib/vietnes.php`)

Viens meklētājs visām vietnēm, kurām nav ne API, ne tabulas: sākumlapa + sēklas → saites ar
izsludināšanas zīmēm → viens solis dziļāk pa "Izsludinātie konkursi" saitēm → līdz 3 sarakstu
izvēršanai. Kandidātus kārto pēc prioritātes (sēkla, šī gada skaitlis, izsludināšanas darbības
vārds), nevis dokumenta secībā; lapošanas saites un lejupielādes (`/download`, `?attachment`,
PDF/DOCX) izmet. Saites atrisina pēc RFC 3986 (`gr_iev_abs_url`); www un bez-www ir viena vietne; http → https.

**Vārti lapai** — `gr_vt_lapas_ieraksts()`, bez tīkla, tāpēc testējami un mērāmi uz keša:

1. adresē nav rezultātu, izsoles, iepirkuma, vakances, arhīva; tā nav WordPress kategorija vai birka;
2. sākotnējā `<h1>` nav NE zīmes (rezultāti, izsole, iepirkums, bērnudārzs, apspriede,
   "noslēgušās kārtas", "konkursa ietvaros top …") — pārbauda PIRMS virsraksta aizstāšanas;
3. virsraksts nes konkursa vārdu un nav saraksta virsraksts ("Izsludinātie konkursi",
   "EJZAF projektu konkursi (2023-2027)"); sākumlapa ir ieraksts tikai ar tiešu paziņojumu virsrakstā;
4. saturs nav saraksts (< 3 citu izsludināšanas saišu, < 4 dažādu pieteikšanās termiņu), un tā
   sākumā nav "Noslēgusies N. kārta" vai "sarindotie projekti";
5. pieteikšanās termiņš nav pagātnē (sk. nākamo sadaļu); atvēršanas datums nākotnē → statuss "gaidāms";
6. grantu šķīrējs (`lib/grantu_filtrs.php`: kārtulas, "nezinu" → MI).

**Šķīrēja virsraksta veto un saistīto ziņu griezums** (2026-09-15). Legālajā ne-grantu kopā (`test_data/granti/ne_granti`)
šķīrējs viens pats ielaida 7 no 31 lapas: granta vārdi tur bija, bet virsraksts nosauca ko citu. Tagad `nav`, ja virsraksta
SĀKUMĀ nominatīvā ir instruments ("Mājokļu garantija …", "Īstermiņa aizdevums …"; ne, ja virsrakstā ir arī grants), pasākums
PAR konkursu ("Konsultācijas par … projektu konkursa V kārtu", arī aiz īsa vietnes vārda "Abulas - …"), pārskata daudzskaitlis
bez galvas vārda ("Pašvaldības atbalsta programmas jelgavniekiem", "Finansējuma iespējas", "Projektu konkursi") vai iepirkumu
saraksts ("Iepirkumi"); aile "Paredzamā līgumcena <summa>" ir iepirkums. Granta pazīmes neskaita aiz bloka "Citas ziņas" /
"Saistītie raksti" — tur ir citu lapu konkursi. Mērījums: 117 marķējumi FP 7 → 0 (FN 0, TP 63 nemainās); 279 keša lapās mainījās
6 verdikti (ISIP aizdevums, CFLA iepirkumi, 4 LEADER saraksta lapas, ko vārti jau izmeta); vietņu ieraksti un laika ceļojums identiski.
Audits 2026-09-16: pasākuma un pārskata veto neattiecas uz vienveidīgiem katalogiem (JSPA, VKKF, SIF, EU), un pasākuma vārds nav veto,
ja virsraksts nominatīvā nosauc konkursu ("Darbnīcas bērniem – līdzfinansējuma konkurss"); 301 tests, katru jauno kārtulu noķer mutāciju
tests. **Ierobežojums:** mērīts uz tām pašām lapām, kurām kārtulas pielāgotas, un ar tīru virsrakstu — ar vietņu plūsmas aizstāto
virsrakstu (logo `<h1>` → teikums no satura) 4 no 7 lapām šķīrējs joprojām ielaistu; tās aptur citi vārti.
Lietotāja 5. punkts (lapa ir ziņnesis — rādām galveno un saiti uz avotu): lapas saturam (`gr_lv_saturs_pec_h1`, vietņu vārti) jebkurā garumā un `gr_lv_teksts()` pārējiem tekstiem virs 400 zīmēm izgriež
sīkdatņu logus, dalīšanās joslas, kājenes un pieejamības rīkus (`gr_lv_bez_troksna`). Tīrītājs ir idempotents — atkārto kārtas, līdz teksts nemainās ("Facebook Dalīties Twitter" pēc vienas kārtas bija "Facebook Twitter"). Ne-grantu kopā tas ir −6 % kopā un līdz −47 % trokšņainākajās lapās;
7000–12000 zīmju logā ietilpst vairāk satura, un neviens verdikts nemainījās. MI verdiktu kešs daļai lapu kļūst nederīgs (atslēga ir teksta jaucējkods).

**Lietotāja 1., 2. un 4. punkts (2026-09-16).** (1) Šķīrējs saņem virsraksta KANDIDĀTUS — `<h1>`, tīrīto `<title>` un satura
sākumu —, un vietņu vārti ņem tekstu bez saistīto ziņu bloka; ar plūsmas virsrakstu ne-grantu kopas 19, 20, 22 un 24 vairs nav
"grants". Papildus kārtula "pasākums PAR konkursu tekstā" (aicina/notiks + konsultācijas/seminārs + `par` + konkurss) ķer lapas,
kur `<h1>` ir logo un `<title>` ir "Blog Post"; tā nenostrādā, ja kāds virsraksta kandidāts pats izsludina kārtu.
(2) Pārskata lapa var kļūt par VAIRĀKIEM ierakstiem (`gr_vt_virslapas_ieraksti`), bet tikai `<h2>`/`<h3>` blokiem, kuros termiņš
ir AR GADU: ar lapas publicēšanas datumu kā enkuru Jelgavas lapa (publicēta 2024-03-13, saturs par 2026. gadu) dotu desmit
ierakstus ar 2024.–2025. gada termiņiem. Uz 465 kešotām lapām šodien tas nedod nevienu ierakstu — kārtula ir nākotnes lapām.
(4) Ziņu saraksta virsraksts ("Jaunumi", "Aktualitātes 2026") ir virslapa, bet TIKAI lapas virsrakstam: reālu paziņojumu lapās
`<h1>` mēdz būt sadaļas nosaukums (Dobeles partnerībai tas ir "Aktualitātes").

**Mērījumi 2026-09-16:** iesaldētais korpuss 136 marķējumi — TP 63, TN 31, FP 1, FN 0, nezinu 41; neredzētā kontrolkopa
(19 lapas no 8 nelietotām vietnēm) — FP 1; bez korpusa (kešs + DB) 81 — FP 0, FN 0; testi 326; mutāciju tests 25 mutanti bez
izdzīvojušajiem; vietņu ieraksti 9 un laika ceļojums nemainīgi. Zināmās robežas: kandavaspartneriba.lv skaidrojošā lapa par
LEADER (vārti to aptur, jo nav termiņa) un Cēsu "Atbalsts uzņēmējiem" pārskata lapa, ja tai būtu termiņš.

**Pilnais audits 2026-09-17 un tā labojumi (LOKĀLI).** Četri recenzenti un skripti; visi atradumi pārbaudīti ar komandu.
Kopīgais cēlonis četriem kritiskajiem: parsētāji ņēma "pirmo" vai "otro" datumu lapā, bet avoti uzskaita kārtu VĒSTURI hronoloģiski.

* **Kārtas intervāls = tuvākais nepagājušais** (`gr_lv_intervali` + `gr_lv_tuvakais_intervals`, `lib/avoti_lv.php`): visi intervāli lapā
  (domuzīme, "no D līdz D", datumi vārdiem, divciparu gads), no tiem agrākās beigas ≥ šodien; ja visi pagājuši — pēdējais. LAD
  (`gr_lv_lad_karta`): LA 16 rādīja 6. kārtu līdz 15.09., lapā bija 7. kārta 02.11.–01.12.2026 ar SAVU budžetu 992 217 EUR (budžetu
  ņem pie izvēlētās kārtas; sveša kārtas summa netiek aizņemta). Uz 52 kešotām dzīvām kartītēm: ar dzīvu termiņu 18 → 24. Kartītes bez
  nolasāma intervāla tagad ir būves žurnālā. VKKF: "Radošo braucienu atbalsts" 29.03. → 11.12.2026; ID no pirmā perioda sākuma.
* **Publikācijas zīmogs nav termiņš**: "pieteikuma veidlapa Pievienots: 15.09.2026." būves dienā kļuva par NKC konkursa termiņu (īstais
  15.10.). `Pievienots/Atjaunināts/Atjaunots/Aktualizēts/Ievietots` ir "ne" vārdi; zīmogu virsraksta sākumā nogriež `gr_vt_tirit_virsrakstu`.
* **CFLA** (`gr_lv_cfla_lauki`): "Atlikušais finansējums (datums) – summa" ar domuzīmi deva budžetu 0,0 (īstais 4 837 008,55 EUR);
  saņēmēja etiķetei kols ir obligāts. `gr_lv_summa`: "miljardi" ×1e9. Rīga lapo līdz tukšai lapai (griesti 15), žurnālā unikālo rindu skaits.
* **Ievācējs**: satura pieprasījumos curl pārvirzēm vairs neseko pats — `gr_iev_get` iet lēcienu pa lēcienam (līdz 5), un katrs lēciens
  PIRMS pieprasījuma iziet robots.txt (izstrādē arī Anthropic grupas) un pauzi. Agrāk galamērķi pārbaudīja pēc atbildes.
* **Vietnes**: pārejoša sēklas vai saraksta lapas kļūme (taimauts, 5xx, 429, griesti) = daļējs apmeklējums, rindas pārnes. LEADER
  kārtām atgriezta EPS pieteikšanās saite (`build.php` rakstīja cietu null). Šķīrējs: "samazināta nodokļa likme" ir nodokļu atlaide.
* **Būve**: shutdown funkcija neaiztiek galīgu stāvokli un @-apslāpētus brīdinājumus par kļūdu neuzdod (`granti_shutdown_labojums`,
  `lib/config.php`); nepabeigtas būves `.tmp` tiek izdzēsts; LV "puse no iepriekšējā" slieksnis salīdzina dzīvas rindas ar dzīvām, datums
  no PHP (ne SQLite `localtime`). Ķēdes solis ar aizņemtu slēdzeni iziet ar 3 (`lib/job_run.php --kede`), tāpēc `granti.diena` nesāk
  maksas tulkošanu, ja būve jau iet citā procesā.
* **Lapa** (`granti.php`): programmu izvēlnes skaitļi rēķināti AR cilni un BEZ pašas programmas ("Apvārsnis Eiropa (432)" atvēra 168;
  izvēlne sabruka līdz vienai opcijai); nozaru plāksnes saite patur programmu; "beigušies" — jaunākie pirmie; viens `<h1>` detaļu lapā;
  404 lapai savs `<title>`, bez saraksta kanoniskās adreses un ItemList. Šie seši labojumi ir sagatavoti arī kā ATSEVIŠĶS variants
  serverī esošajai versijai (`_Optimizacija/izvietosana_2026-09-17/1_lapa/granti.php`), jo lokālā lapa jau sola LV avotus.
* **Recenzija uz šīm izmaiņām (tā pati diena) atrada un salaboti:** LAD kārtai vajag ENKURU (kārta/atvērta/izsludināta/pieteikšanās…)
  un tieši pirms tās nedrīkst būt perioda vārds — citādi uzvarēja atskaites periods ("par laikposmu no 01.07.2025. līdz 30.06.2026."),
  īstenošanas termiņš vai Valsts Zivju fonda MAKSĀJUMU pieteikumu kārta (2000 EUR); jau ATVĒRTA kārta ir pārāka par īsāku nākotnes
  kārtu; divciparu gads tikai pilnā formā `dd.mm.yy` ("5.1.25.-5.1.27." ir punktu numuri); zīmogs ir zīmogs tikai TIEŠI pirms datuma
  ("pieņemšana atjaunota no 01.10. līdz 31.10." ir īsts termiņš); ķēde paliek vesela arī tad, ja panelī atzīmēta gan būve, gan ķēde
  (`admin_launch_grupas`); pārvirzes lēcieni skaitās vietņu pieprasījumu griestos; atteikta pārvirze nes priedēkli `robots:`.
  Dzīvā būve papildus parādīja: ALTUM "aizdevumu ar kapitāla atlaidi veidā" (35 milj. €) bija sadaļā kā grants — instrumenta teikums
  ir 760. zīmē, aiz 600 zīmju loga (jauns šaurs veto 1500 zīmju logā); Rīgas vietne ārpus diapazona atkārto pēdējo lapu (714 rindas,
  210 unikālas) — lapošana apstājas pēc DIVĀM lapām pēc kārtas bez jaunām rindām (viena nepietiek: 1. lapa mēdz atkārtot 0. lapu); 60 → 27 pieprasījumi, 207 unikālas rindas.
* **Testi**: `filtra_testi.php` 397, pastāvīgais komplekts 157, mutāciju tests 50 mutanti bez izdzīvojušajiem (~6 s); tīkls testos
  aizstājams ar tabulu (`$GLOBALS['gr_iev_jelgs_aizstajejs']`), tāpēc pārvirzes un vietņu kandidātus testē bez interneta. Mērījumi
  nemainījās (korpuss FP 1, kontrolkopa FP 1/19, bez korpusa FP 0, laika bumbas 0); vietņu ieraksti 9 → 8 (ALTUM aizdevums prom); kešā mainījās 1 verdikts (nodokļa likme).
* **Atlikušie sīkumi** (nav laboti): karte lieto būves `status='open'` (34 beigušās ES rindas ar `lastmod` šodien); `?lapa=N` uz konkursu
  saitēm; angļu atkāpei nav `lang="en"`; router.php lokāli atdod `granti/db/*.sqlite`; LAD `keywords` nes garu teikumu; `gr_tulk_tiri`
  dzēš arī lidojošos Batch tekstus; tūlītējais ceļš un Batch tur dažādas slēdzenes; Batch atbilde bez atpazītas atslēgas netiek
  pieskaitīta tēriņam; rotācijas zīmogs pie izņēmuma; pārneses izslēgšana salīdzina `www.` burtiski; STOP LV fāzē nepārbauda.
  Lietotāja lēmumi, kas vēl atvērti: "lipīgs" Anthropic liegums izstrādē; LIAA faili 001–032.
* **Būves brīdinājums tikai administratoram** (lietotāja lēmums 2026-09-17): dzeltenais uzraksts ("nesasniedzamas vietnes: …") ir
  operatora ziņa ar svešu vietņu nosaukumiem, tāpēc apmeklētājs to neredz. Administrators to redz adresē `/granti/?k=<atslēga no
  admin_token.php>` — tā pati atslēga, ar ko atveras paneļi; sīkdatņu nav. Atslēga neiet ne saitēs, ne kanoniskajā adresē, un ar `?k=`
  atbildei ir `Referrer-Policy: no-referrer`, lai klikšķis uz ārēju saiti to neaiznestu svešai vietnei.

Līdzdalības budžets (virsrakstā vai satura sākumā) dabū modeli **💡 `ideju_konkurss`** — ideju īsteno pašvaldība,
nauda pieteicējam netiek pārskaitīta; lapā tā ir filtra atzīme, "Kam der" aile un redzama piezīme detaļu lapā
(lietotāja lēmums 2026-09-15: rādīt ar atzīmi, ne izmest).

Viena konkursa dublikāti vienā vietnē apvienojas (`gr_vt_dedup`): tā pati adrese; tas pats virsraksts un termiņš;
sākumlapa + ziņa ar tiem pašiem datumiem; divi 💡 līdzdalības budžeti ar vienu termiņu (pašvaldībai periodā ir viens —
paliek ieraksts ar summu). Vienādi datumi uz divām konkrētām lapām paši par sevi dublikāts NAV (ELFLA un EJZAF kārta).
Pārnestā rinda, kas dublē svaigu (tā pati normalizētā adrese ar citu ID), būvē tiek izmesta.

**Rotācija.** Katrā būvē apmeklē `--vietnu-budzets=N` (noklusēti 16) senāk apmeklētās vietnes;
neapmeklēto vietņu rindas pārnes no iepriekšējās DB pēc hosta (kolonna `division`), bet tikai
ar vēl nepagājušu termiņu. Stāvoklis: `data/vietnu_rotacija.json`. LEADER grupu saraksts nāk no
LAD lapas un kešojas uz nedēļu (`data/leader_grupas.json`); ja LAD neatbild, lieto veco kešu.
**Griesti**: 600 pieprasījumi un 45 min vienā būvē — atlikušās vietnes nākamajā būvē.
**"Daļēji"** (vietnes rindas pārnes) ir TIKAI pārejoša kļūme — tīkls, 5xx, 429, griesti; salauzta
saite (404), 403 un robots liegums nav "daļēji", citādi vecās rindas pārnestos bez gala.

**Izstrādes režīms (noklusējums bez `granti/data/serveris.flag`, vai ar `--izstrade`).** Ievācējs katrai
adresei papildus mūsu grupai ievēro arī Anthropic rāpuļu (ClaudeBot, Claude-User, Claude-SearchBot,
anthropic-ai, Claude-Web) PRECĪZĀS robots.txt grupas pēc RFC 9309: ja tādas grupas kārtula ceļu liedz,
pieprasījums neaiziet (`robots: izstrādes režīms: …`). Šādu vietņu vecās rindas izstrādē netiek pārnestas.
Serverī karodziņš `serveris.flag` to izslēdz — tur rāpulis ir SarakstsLV, un noteicošā ir tā vai `*` grupa.
Noklusējums ir stingrākais: aizmirsts karodziņš serverī nozīmē dažas vietnes mazāk, nevis pretējo.
Vārds vien nav liegums: ventspils.lv raksta `ClaudeBot / Allow: /`, ziedzeme.lv (Squarespace) liek ClaudeBot
vienā grupā ar `*` — abas tiek apmeklētas.

### Termiņš no brīva teksta (`gr_lv_termina_intervals` failā `lib/avoti_lv.php`)

Lieto vietnes, LZP/VIAA/NVA ziņas un JSPA rezervē (JSPA pamatā ir lauks "Beigu datums").
2026-09-13 recenzents iepriekšējo redakciju pārbaudīja uz 15 formulējumiem, un 14 bija nepareizi;
visi ir testos. Kārtulas:

* teikums pa teikumam; atslēgvārdam PIRMS datuma jābūt pieteikšanās vārdam (pieteikt, iesniegt/iesniedz, pieņem,
  termiņš, kārta, noslēdzās, pagarināts — ne divdabis "iesniegto"), un tas attiecas TIKAI uz pirmo datumu aiz tā;
  3 vārdos pirms tā nedrīkst būt "īstenošanas, līguma, rezultātu, vērtēšanas, realizēšanas, ilguma, iepriekš …";
* grafiks "DATUMS – etiķete": nozīmi dod etiķete aiz datuma ("30.10.2026 – pieteikumu iesniegšanas termiņš");
* intervāls "no A līdz B" / "A–B" / tabulas "A B" → beigas (dec → jan); "no A", "sāksies A", "– sākums" → atvēršana;
  datums pasākuma pieteikšanai ("aizpildot anketu", "uz semināru") nav termiņš;
* pagarinājums aizstāj tikai IEPRIEKŠ minētu tās pašas rīcības/kārtas termiņu (92 d logs); no vairākiem uzvar agrākais
  nepagājušais (dzīvā kārta), citādi vēlākais;
* **datumam bez gada vajag GADA ENKURU**: publicēšanas datumu (`gr_lv_publicesanas_datums`: meta, `<time pubdate>`,
  adrese `/2024/12/17/`, "Publicēts:", vecs gads adresē) vai skaidru datumu tajā pašā tekstā (zīmogs "03.09.2026",
  "20. augusts, 2026"). Bez enkura datumu nepieņem: pret šodienu atrisināts "no 20. decembra līdz 20. janvārim"
  katru gadu atdzima (recenzents 2 atrada 12 tādas kešotas lapas). Pagātnes vārdi (bija, notika, pieņēma, noslēdzās)
  nozīmē "ne vēlāk par enkuru".

Laika bumbas mēra `php test_tools/granti/laika_celojums.php` (kešotās lapas ik 14 dienas gadu uz priekšu).

Pēc izmaiņām mēri uz reālām lapām bez tīkla: `php test_tools/granti/vietnes_no_kesa.php`
(2026-09-13 pēc divām recenzijām: 211 kešotas lapas 45 vietnēs → 7 ieraksti bez tīkla, visi pārbaudīti pret lapas tekstu; būvē 8 — Daugavkrasts gada enkuru dabū no WordPress REST datuma; pirms labojumiem bija 31).

Visi LV avoti un vietnes iet caur `granti/lib/ievacejs.php`: robots.txt pārbaude pirms katra
pieprasījuma (ceļš normalizēts — `../` un `%2e` nevar apiet kārtulu), Crawl-delay, nosacītais GET
un audita žurnāls `data/ievacejs.log`. Neatbildējis robots.txt (tīkls/5xx) nozīmē "nevelkam", bet
kļūda ir `robots-nepieejams:`, nevis īpašnieka liegums, un diskā netiek kešota. Nederīgu UTF-8
labo pa bojātajiem baitiem; lapas bez UTF-8 pārkodē no deklarētā kodējuma vai windows-1257.
Licence šiem avotiem nav deklarēta, tāpēc glabājam TIKAI faktus (nosaukums, datumi, summa,
saite) — aprakstu nekopējam, un lapa to arī pasaka.

### Kas vēl nav ievākts un kāpēc (stāvoklis 2026-09-13)

**Juridiski — vietne pati liedz vai prasa atļauju:**

* **LIAA** — atslēgts. Programmu lapas `liaa.business.gov.lv` robots.txt atļauj, bet sarakstu
  var iegūt tikai caur `office.business.gov.lv` (`sitemap.xml`, `/jsonapi`), kur `Disallow: /`.
  Vēstules melnraksts: `granti/vestules/liaa-datu-pieprasijums.md` (nav nosūtīts).
* **ziemelkurzeme.lv** (LEADER) — robots.txt `User-agent: * / Disallow: /`.
* **aizkrauklespartneriba.lv** (LEADER) — HTTP 403 mūsu rāpulim (WAF); sistēmas curl tiek cauri.
  Neapejam — tas ir vietnes lēmums.
* **EKII** (`www.ekii.lv`), **Daugavpils** (`www.daugavpils.lv`), **Kandavas partnerība**
  (`www.kandavaspartneriba.lv`) — robots.txt `User-agent: ClaudeBot / Disallow: /`, bet `*` atļauj. Izstrādes
  režīmā ievācējs tās neapmeklē; serverī apmeklē. **Atklāts 2026-09-13:** pirms ievācēja līmeņa pārbaudes
  (statiskais karodziņš bija tikai EKII) šīs sesijas būves no tām ievāca 13 lapas; to kešs un rindas no
  izstrādes datiem izņemti. Satura pārbaude pirmajā servera būvē.

**Tehniski — salūzis viņu vai mūsu pusē:**

* **Latvijas Vides aizsardzības fonds** (`lvafa.gov.lv`) — beidzies TLS sertifikāts; reģistrā
  `atslegts`. TLS verifikāciju neatslēdzam.
* **Liepāja** (`www.liepaja.lv`) — PHP curl sertifikāta kļūda (errno 60), sistēmas curl iet
  cauri (nepilna ķēde vai CA saišķis). Serverī var uzvesties citādi.
* **Aktīvo iedzīvotāju fonds** (`aif.lv`), **Rietumu Bankas labdarības fonds** — no izstrādes
  mašīnas DNS neatrisinās; jāpārbauda no servera.

**Dati ir, bet nav mašīnlasāmi ar šo pieeju:**

* **Pašvaldību konkursi** — meklētājs tos atrod (Ogre, Valmiera, Cēsis), bet 2026-09-13 visiem
  vai nu termiņš pagājis, vai tas ir tikai nolikuma PDF/DOCX. PDF lasīšanas nav.
* **VIAA un NVA** — uzsaukumi ir, bet termiņi lielākoties PDF vadlīnijās (VIAA 1 no 3, NVA 0 no 3).
* **EIT, Interreg Central Baltic, Nordplus, keep.eu** — saraksts nāk ar JavaScript.
* **COST, Interreg BSR, EEZ/Norvēģijas granti** — robots.txt nosauc MI rāpuļus; zondē no servera.
* **CELLAR SPARQL** (ES OV C sērija) — galapunkts strādā un licence atļauj, vaicājums nav uzrakstīts.

## Kas notiek katru dienu

1. `granti/bin/build.php` — lejupielādē ES bulk failu (~124 MB; atkārtoti velk tikai tad,
   ja kešotais vecāks par 20 h), paņem topicDetails budžetiem, pievieno SIF konkursus un
   atomāri pārceļ jauno datubāzi pāri vecajai. Tur savu slēdzeni; gaita — `data/build_state.json`.
2. `granti/bin/tulko.php` — jaunos tekstus iztulko latviski caur **Gemini Batch API**
   (puse cenas). Tulkojumu kešs ir ATSEVIŠĶS fails `db/tulkojumi.sqlite`, jo būve savu
   datubāzi pārraksta pilnībā. Dienas griesti 2,50 € ir kodā (`lib/tulkojumi.php`).

### Beigušies ES konkursi un 410 (`lib/beigusie.php`, 2026-09-23)

ES portāls dažas dienas pēc termiņa konkursu pārslēdz uz Closed, un bulk plūsmā tā vairs nav. Līdz šim
būve to vienkārši neieraudzīja, un `/granti/{slug}` atdeva 404 (GSC: 111 → 6 000 lapu). Tagad:

* **Pārnese.** ES rindu, kuras šonakt nav, bet kuras termiņš (`COALESCE(deadline_apply, deadline_last,
  deadline_date)`, tas pats, ko lapa lieto dzīvumam) beidzās pēdējo `GR_BEIGUSO_DIENAS` = 183 dienu laikā,
  pārnes no iepriekšējās DB ar statusu `closed`. Termiņš priekšā → nepārnes (atsaukts; lapa to rādītu kā
  atvērtu). Termiņš ŠODIEN → pārnes ar veco statusu (portāls var slēgt termiņa dienā, un būve ārpus
  grafika to citādi pazaudētu uz visiem laikiem). Bez termiņa → nepārnes. Kļūme = apturēta būve.
* **Dublikāti pēc pārneses** (`gr_parnesto_dublikati`). Alias apvienošana notiek pirms pārneses, tāpēc
  pārnests ID ar gadu + portāla "Forthcoming" zombijs bez gada (EIC Pathfinder) dotu divas lapas. Tās pašas
  kārtulas A/B un tā pati izvēle, ko paturēt (saites turētāju; ID, kas sākas ar call_id; abus, ja sākas abi
  vai neviens). Svaigo izmet tikai, ja tas beidzies vai tā termiņš nav vēlāks par pārnestā (būve termiņa
  dienā!); abi pāra locekļi nepazūd nekad. Pārnestos padod sarakstā, ne pēc statusa. 3 recenzijas kārtas.
* **Adreses no jauna.** Pirms slug cilpas `UPDATE grants SET slug = NULL`: pārnestā rinda citādi atnes
  vakardienas slug, un svaigs ID ar to pašu normalizēto adresi uzskrietu uz UNIQUE indeksa.
* **410.** ES adrese, kas vakar bija un šodien nav, nonāk tabulā `izbeigtas_adreses` (slug, id, pirmā diena).
  Tabula mantojas no būves uz būvi; atgriezusies adrese no tās izņemta. Lapa: 410 + noindex; nezināma → 404.
  LV/SIF adreses tur NENONĀK: izlaists LV avots beigušās kārtas uz nakti pazaudē un rīt atdod atpakaļ.
* **Tulkošana.** `gr_tulk_sinhronize()` rindā neliek `status='closed'`: gatavs tulkojums paliek, netulkots
  kļūst par bāreni. Pēc `GR_TULK_VERSIJA` maiņas beigušie paliek angliski — apzināti.
* **Lapa.** Beidzies konkurss: 200, indeksējams, bez pieteikšanās pogas, saite uz atvērtajiem tās pašas
  nozares konkursiem; cilnē "Beigušies" un vietnes kartē (`changefreq monthly`). Galvas "latviski ir N no M"
  skaita tikai nebeigušos.
* **Testi:** `php test_tools/granti/beigusie_testi.php` (61; bez servera) — palaiž arī `parbaudes.php` 15. sadaļa.
* LV avotu beigušās kārtas netiek pārnestas (to lapas ir plānas un noindex) — tās tāpat kā līdz šim pazūd ar 404.

## Kā to ieslēgt uz servera

Divi soļi, abi vienreizēji:

1. **Grafiks.** Panelī `/admin.php?k=<token>` ieplāno DIVUS darbus: `granti.diena` (būve +
   tulkošana) uz **01:20** — pirms nakts kopējās būves 02:00, lai vietnes kopkarte tajā pašā
   naktī redz jaunos datus — un `granti.savac` (tikai savāc gatavos Batch darbus, neko nesūta)
   uz **13:00**, lai apmaksāts darbs tiek savākts arī tad, ja nakts būve ir kritusi. Grafiks
   glabājas `admin_state/schedule.json`, ko ik 5 minūtes lasa `cron/dispatch.php` — hostinga
   cron rindā jābūt tikai tam vienam ierakstam.
2. **Karodziņš** (tikai ja lieto tiešo crontab rindu, nevis paneli):

       touch granti/data/cron_enabled.flag
       10 5 * * *  php /ceļš/uz/docroot/granti/bin/cron_build.php

Bez karodziņa `cron_build.php` neko nedara — tā cron var apturēt, neaiztiekot servera
konfigurāciju.

## Kā izvietot serverī (pirmā reize, secība ir svarīga)

Sadaļa serverī vēl nav bijusi. Šie soļi izriet no koda un 2026-09-10 audita; katram ir
pārbaude. `S=` ir ssh uz Hostinger kontu, docroot `domains/saraksts.lv/public_html`.

0. **Priekšpārbaude serverī** (nekas nemainās): `php -v` (CLI 8.3), `php -m | grep -iE "curl|pdo_sqlite|mbstring"`,
   un ES CDN slazds: 80 reizes pēc kārtas `curl -s -o /dev/null -w "%{http_code} %{size_download}\n"`
   uz vienu topicDetails adresi. Būve to iztur arī tad, ja slazds ir (2026-09-10 mērīts: 580 no
   633 pieprasījumiem nogriezti, visi pieņemti ar pilnu ķermeni), bet zināt ir labāk.
1. **Sēkla** (ieteicams, izlaiž 124 MB lejupielādi, 648 tēmu vilkšanu, 503 logu un ~1,20 €
   par tulkojumiem): `scp -p` uz serveri `granti/db/granti.sqlite`, `granti/db/tulkojumi.sqlite`,
   `granti/data/cache/grantsTenders.json` un mapi `granti/data/cache/topics/` (kopā ~180 MB).
   `-p` patur datumus, tāpēc bulk fails skaitās svaigs (< 20 h) un pirmā būve ir silta.
   Bez sēklas serveris tulko visu no jauna caur Batch API: 2115 teksti, aplēse 1,20 €, viss
   vienā dienā (dienas griesti 2,50 €), un korpuss būs vienādi ar v3 uzvedni.
2. **rsync** koda kā vienmēr (filtrs `*.php *.js *.css *.woff2 .htaccess llms.txt`; `*.sqlite`
   neiet). Sadaļas saite galvenē ir dzīva tajā pašā mirklī, tāpēc 3. solis — uzreiz pēc tam.
3. **`.htaccess`**: `diff` pret servera kopiju, rezerves kopija, tad `scp htaccess.txt` uz `.htaccess`.
   Bez tā `/granti/` atdod **403** (reāla mape apēno maršrutu). Pārbaude: `curl -sI https://saraksts.lv/granti/`
   → 200 (ar sēklu) vai 503 (bez); `/granti` → 301 uz `/granti/`; `/granti/db/granti.sqlite`,
   `/granti/lib/config.php`, `/granti/data/build.log` → 404.
4. **Servera karodziņš**: `touch granti/data/serveris.flag` (rsync to nesūta, un tā tam jābūt). Bez tā ievācējs
   strādā izstrādes režīmā un izlaiž vietnes, kuru robots.txt liedz Anthropic rāpuļiem. Pārbaude: būves
   žurnālā rinda `Ievācēja režīms: serveris`.
5. **Pirmā būve** ar roku, atdalīti: `nohup php granti/bin/build.php > /tmp/granti-first.out 2>&1 &`
   un `tail -f granti/data/build.log`. Gaidāmā beigu rinda: `Gatavs: … TLS pārrāvumi ar pilnu
   ķermeni: N, atkāpes uz sistēmas curl: 0`, `build_state.json` → `gatavs/ok`. Bez sēklas
   ilgums ~8–10 min (mērīts 456 s).
6. **Lapas pārbaude**: `/granti/`, viens konkurss no `sitemap-granti.xml`, `?secibaa=nosaukums`,
   `?val=en` → visi 200; `/granti/nav-tada` → 404. 503 šeit nozīmē vai nu "nav uzbūvēts", vai
   veca SQLite tīmekļa PHP (lapa lieto `json_each`) — skat. `log/<datums>.log`.
7. **Tulkošana**: `php granti/bin/tulko.php --statuss` (rāda rindu un aplēsi, nemaksā), tad
   `php granti/bin/tulko.php`. Ar sēklu rinda ir 0–20 tekstu; bez sēklas — 11 Batch darbi,
   ko savāc `--savac` pēc 30–60 min (vai `granti.savac` grafikā).
8. **Grafiks panelī** `/admin.php?k=<token>`: `granti.diena` 01:20 un `granti.savac` 13:00.
   Pārbaude: `admin_state/schedule.json` satur abus, `dispatch_last.txt` ir svaigāks par 5 min.
9. **Nākamajā rītā**: `admin_state/granti.build.log` un `granti.tulko.log` beidzas ar `rc=0`;
   `sitemap.xml` satur `sitemap-granti.xml` (to ieliek nakts kopējā būve 02:00).

### Atjauninājums: Latvijas avoti un vietnes (sagatavots 2026-09-15, NAV izvietots)

Sadaļa serverī jau darbojas (ES + SIF kopš 2026-09-10); šis ir koda atjauninājums, ne pirmā izvietošana.

1. **Tikai Grantu faili, ar sarakstu** — nevis viss `server/`: citu sadaļu lokālās izmaiņas nedrīkst aizceļot līdzi.
   `rsync -rt --checksum --backup --backup-dir=… --files-from=<saraksts>` ar šiem 15:
   `granti.php`, `granti/bin/build.php`, `granti/bin/zonde.php`, `granti/bin/izvilkums_demo.php`,
   `granti/lib/avoti_kandidati.php`, `granti/lib/avoti_lv.php`, `granti/lib/config.php`, `granti/lib/grantu_filtrs.php`,
   `granti/lib/ievacejs.php`, `granti/lib/modelis.php`, `granti/lib/vietnes.php`, `granti/vestules/.htaccess`,
   `lib/admin_jobs.php`, `lib/job_run.php` (kopš 2026-09-17: ķēdes solis `--kede`), plus `htaccess.txt` → servera `.htaccess` (diff pirms tam; tajā ir `granti/(…|vestules)` un `test_tools` 404).
   `test_tools/` NESŪTĪT.
2. **`touch granti/data/serveris.flag`** serverī — citādi ievācējs strādā izstrādes režīmā.
3. **Pirmā būve ar roku**: `php granti/bin/build.php --skip-eu` (ES pārnes). Žurnālā jābūt `Ievācēja režīms: serveris`,
   LV avotu skaitiem un vietņu rindai. **Risks:** lad.gov.lv/vid.gov.lv WAF agrāk bloķēja Hostinger CLI TLS
   (atmiņa ref-valsts-waf-hostinger-proksijs) — ja LAD dod 0, tas ir tīkls, ne kods; būve LAD rindas pārnes, ja tās bija.
4. **Pārbaude**: `/granti/?avots=LEADER`, `/granti/?avots=LAD`, viena LV detaļu lapa (noindex, "tikai fakti"),
   `/granti/lib/vietnes.php` un `/test_tools/granti/filtra_testi.php` → 404.
5. EKII, Daugavpils un Kandavas partnerība pirmoreiz tiek apmeklētas tikai serverī — pārbaudīt to rindas pēc būves.

Izejas kodi būvei: 0 labi · 1 datu/IO kļūme (dzīvā DB paliek) · 3 slēdzene aizņemta ·
4 STOP · 5 publicēts, bet ES fails > 72 h vecs un neatjaunojas (ķēde apstājas, panelī kļūda).

## Noderīgās komandas

    php granti/bin/build.php --skip-details      # ātrā pārbūve bez budžetu vilkšanas
    php granti/bin/build.php --skip-eu           # tikai SIF + LV avoti (ES pārnes no vecās DB)
    php granti/bin/build.php --skip-lv           # bez Latvijas avotiem
    php granti/bin/build.php --jspa-limit=20     # cik JSPA lapu vienā palaišanā (noklusēti 60)
    php granti/bin/zonde.php                     # kandidātu avotu klasifikators
    php granti/bin/build.php --izstrade          # piespiež izstrādes režīmu (noklusēti bez data/serveris.flag)
    php granti/bin/build.php --vietnu-budzets=4  # ātra pārbaude uz 4 vietnēm
    php granti/bin/build.php --skip-vietnes      # bez pašvaldībām/LEADER/iestādēm (rindas pārnes)
    php test_tools/granti/filtra_testi.php       # regresijas testi (bez tīkla)
    php test_tools/granti/vietnes_no_kesa.php    # vietņu vārti uz kešotajām lapām (bez tīkla)
    php test_tools/granti/laika_celojums.php     # vai kešotas lapas nākotnē neatdzimst kā atvērtas (bez tīkla)
    php test_tools/granti/mera_filtru.php test_tools/granti/markejumi.json [--bez-liaa]   # iesaldētais korpuss (test_data/granti: korpuss, lad, ne_granti);
    php test_tools/granti/mera_filtru.php test_tools/granti/markejumi.json --kopa=kontrolkopa   # neredzētā kontrolkopa (FP bez pašpārbaudes)
                                                 # --bez-liaa bez LIAA teksta no liegta ceļa (legālais ne-grantu aizstājējs: ne_granti, 31)
    php test_tools/granti/mera_filtru_no_kesa.php  # pret šodienas legālajiem datiem (kešs + DB), bez LIAA — tā korpuss ir no liegta ceļa
    php test_tools/granti/filtra_salidzinajums.php <vecais_filtrs.php> > a.jsonl   # šķīrēja verdikti uz keša
    php test_tools/granti/filtra_salidzinajums.php --diff a.jsonl b.jsonl          # kas mainījās starp versijām
    php test_tools/granti/mutaciju_tests.php     # vai testi noķer katru šķīrēja kārtulu (mutanti, bez tīkla, ~1 min)
    php test_tools/granti/plusmas_virsraksti.php # ne-grantu kopa ar vietņu plūsmas virsrakstu, ne tīro marķējuma virsrakstu
    php granti/bin/tulko.php --statuss           # rinda, izmaksu aplēse, Batch darbi
    php granti/bin/tulko.php --savac             # tikai savākt gatavos Batch darbus
    php granti/bin/tulko.php --tulits --limit=5  # tūlītējais ceļš (pilna cena) pārbaudei
    touch granti/data/stop.flag                  # apturēt notiekošo būvi

## Kas jāatceras

* `db/` un `data/` publiski nav pieejamas: `htaccess.txt` rindā ir
  `RedirectMatch 404 ^/granti/(lib|bin|data|db)/`, un abās mapēs ir savs `.htaccess`.
* Adreses `slug` glabājas datubāzē. Vietnes karte lasa to pašu kolonnu, ko lapa, tāpēc
  kartē nevar nokļūt adrese, kas atdotu 404.
* Maršruts ir DIVĀS vietās: `router.php` (lokālais `php -S`) un `htaccess.txt` (produkcija).
  Mainot vienu, jāmaina otrs.
