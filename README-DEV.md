# Reģistrs — dinamiskais PHP serveris (saraksts.lv)

`server/` mape = **gatavs domēna docroot**. Augšupielādē to saturu domēna saknē
(piem. `public_html/`), pārsauc `htaccess.txt` → `.htaccess`, un vietne strādā.

Katra uzņēmuma lapa tiek renderēta pieprasījuma brīdī no `ur_data.db` (~6 ms ar opcache).
Deploy artefakts = tikai datubāze; kods fiksēts.

Papildus reģistram viena būve apkalpo arī trīs SADAĻAS:
**Nozare** (`nozare.php` + `nozare/katalogs.sqlite`), **Struktūra** (`struktura.php` +
`struktura/data/*.json` — Latvijas uzņēmumu treemap karte) un **Pensionārs** (`pensionars.php`
+ `pensionars/pensionari.sqlite`). Visas lasa no tās pašas kopīgās `csv/` mapes / `ur_data.db`
— atsevišķas lejupielādes vairs nav vajadzīgas.

Papildus ir vēl trīs sadaļas, kas NAV atkarīgas no reģistra būves:
- **Iespēja** (`iespeja.php`) — biznesa iespēju karte; AJAX vaicā ATSEVIŠĶU MySQL telpisko DB
  (Hostinger; pieejas dati faila iekšā — kā oriģinālā). Python būves kods NAV pārnests (cits seanss).
- **Horoskops** (`horoskops.php` + `horoskops/`) — pilnīgi statiska JS astroloģijas lietotne
  (Swiss Ephemeris WASM); nav DB/servera. Kopēta neskarta, saglabāta atsevišķa (fiksēts izkārtojums).
- **Lejupielāde** (`lejupielade.php` + `lejupielade/`) — visa pirmkoda pakotne vienā ZIP
  (MIT licence) + savērsta sadaļa "Vecā versija" ar vecajiem moduļu arhīviem.
  Pakotni būvē `tools/build_download.php`. Uzskaites NAV (skat. zemāk).

Galvenes izvēlne: Reģistrs · Nozare · Struktūra · **Konkursi** · Iespēja · Pensionārs ·
**Horoskops** · Lejupielāde.

## Docroot struktūra
```
server/                     <- domēna sakne (docroot)
  index.php                 <- sākumlapa
  company.php               <- dinamiskais uzņēmuma lapas ruteris
  data_admin.php            <- SLĒPTAIS datu pārvaldības panelis (?k=token)
  admin_token.php           <- admin atslēga (NOMAINI!)
  404.php  robots.txt  htaccess.txt(→.htaccess)
  router.php                <- tikai lokālai php -S testēšanai
  sitemap/                  <- ģenerēti sitemap-*.xml + sitemap.xml (build laikā)
  nozare.php                <- SADAĻA: nozaru pārskats (lasa nozare/katalogs.sqlite — būves izvade)
  nozare/                   <- get_companies.php (AJAX) + nace-foto/ (asseti); katalogs.sqlite = būves izvade
  struktura.php             <- SADAĻA (BŪVES IZVADE, nav kodā): Latvijas uzņēmumu treemap karte
  struktura/                <- css/js aktīvi + data/ (BŪVES IZVADE: overview.json + div_XX.json)
  pensionars.php            <- SADAĻA: ilgtermiņa uzņēmumu portfelis (lasa pensionars/pensionari.sqlite = būves izvade)
  pensionars/               <- pensionari.sqlite
  iespeja.php               <- SADAĻA: biznesa iespēju karte (Leaflet); AJAX uz MySQL (Hostinger)
  horoskops.php             <- SADAĻA: astroloģijas matrica (atsevišķa statiska JS lietotne)
  horoskops/                <- css/js/onet/swisseph aktīvi horoskops.php lapai
  lejupielade.php           <- SADAĻA: pirmkoda lejupielāde (viena pakotne + "Vecā versija")
  count.php                 <- tikai 301 uz /lejupielade.php (vecais skaitītājs noņemts)
  lejupielade/              <- saraksts-lv-kods.zip (BŪVES IZVADE) + vecie moduļu arhīvi
  tools/                    <- iekšējie rīki; NAV pieejami caur URL (.htaccess 404)
    build_download.php      <- būvē publisko saraksts-lv-kods.zip
    build_download.secrets.php <- ĪSTĀS atslēgas tīrīšanai; NEKAD necommitot, nekad nepakot
  registrs/                 <- KODA faili un resursi
    header.php
    lib/                    <- PHP moduļi (db, data_fetcher, page_builder, financial_engine, ...)
    view/                   <- inner_content.php + partials/ + _tpl.php (renderēšana)
    templates/              <- master_top.php, master_bottom.php
    build/                  <- datu būvēšana (download, convert, prepare, report_tracker, build_all, cron_build)
                               + sadaļas: section_nozare/section_struktura/section_pensionars,
                               sections_cli.php, NACE.csv, templates/struktura_template.html
    assets/                 <- css, js, img, search (companies.sqlite, nace_stats.sqlite, search.php)
    head/ footer/ cookie/ mi/
    ai_cache/               <- AI atbildes apakšdirektorijās x/DD/DD/{reg}.json
```
Dati (`csv/`, `build_state/`, `vid_quarterly_history.sqlite`) un `ur_data.db` — ieteicams
LIKT ĀRPUS docroot un norādīt ar vidi `REG_DATA_DIR` (vai `UR_DB_PATH`). Noklusējumā = docroot.

## Kods vs Dati (tīrs docroot)
`server/` mape ir **tikai kods** — tajā NEGLABĀJAM neko, kas lejupielādēts no data.gov.lv vai
tā apstrādes rezultātus. Visus datus ģenerē būve uz servera. Skat. `.gitignore`.

| Artefakts | Kas tas ir | Kur tas rodas / jāliek |
|---|---|---|
| `csv/`, `ur_data.db` | izejdati + galvenā DB | būves 1.–2. posms; ieteicams ārpus docroot (`REG_DATA_DIR`) |
| `registrs/assets/search/companies.sqlite`, `nace_stats.sqlite` | meklēšana + sākumlapa | būves 3. posms (swap) → `REG_SEARCH_DIR` vai docroot |
| `nozare/katalogs.sqlite` | Nozares dati | būves 3.5 posms (swap) → docroot (apkalpo lapa) |
| `pensionars/pensionari.sqlite` | Pensionāra dati | būves 3.5 posms (swap) → docroot |
| `struktura.php` + `struktura/data/*.json` | treemap lapa + dati (overview + pa nodaļām) | būves 3.5 posms (swap) → docroot |
| `vid_quarterly_history.sqlite` | **uzkrātā** VID ceturkšņu vēsture | GLABĀT datu mapē (`REG_DATA_DIR`); NE kodā — skat. zemāk |

**Pirms pirmās būves** augšupielādē uzkrāto `vid_quarterly_history.sqlite` datu mapē
(`REG_DATA_DIR`, vai docroot, ja `REG_DATA_DIR` nav iestatīts). VID publicē tikai pēdējo
ceturksni — bez šī faila vēsture sāksies no nulles un vecie ceturkšņi būs zaudēti.

Tīrs `server/` (bez datiem): sadaļu lapas rāda "vajag būvi", līdz palaista pirmā būve;
uzņēmumu lapas strādā uzreiz, tiklīdz pieejama `ur_data.db`.

## Publiskā koda pakotne (sadaļa "Lejupielāde")

Lapa `lejupielade.php` piedāvā **vienu** failu — `lejupielade/saraksts-lv-kods.zip` — ar visu
pirmkodu MIT licencē. Lejupielāžu uzskaite noņemta 2026-07-26.

Pārbūvē ar:

```bash
php tools/build_download.php
```

Skripts pats: atlasa kodu (izlaižot visu, ko kods lejupielādē vai ģenerē), iztīra noslēpumus,
pievieno `LICENSE` + `NOTICE.md` + `README-PIRMS-SAKAM.md`, **pārbauda, ka noslēpumi tiešām ir
projām**, un tikai tad saliek ZIP. Ja kaut viens paraugs no `forbidden` saraksta izdzīvo, būve
tiek pārtraukta un ZIP netiek izveidots.

Divi faili, nevis viens, ar nolūku:

| Fails | Loma |
|---|---|
| `tools/build_download.php` | metode. **Tiek iekļauts pakotnē** caurspīdīguma dēļ |
| `tools/build_download.secrets.php` | īstās paroles/atslēgas. **Nekad nenonāk pakotnē un versiju kontrolē** |

Ja noslēpumi būtu pašā būves skriptā, tas publicētu tieši to, ko cenšas noņemt.

**Kad nomaini kādu paroli vai atslēgu produkcijā, atjauno to arī `build_download.secrets.php`** —
citādi nākamā būve to vairs neatpazīs un izlaidīs cauri.

Pakotnē apzināti IR iekļauti dati, ko kods nevar iegūt pats: `nozare/nace-foto/` (MI ģenerēti
attēli), `horoskops/onet/`, `ceturksnis/`, `konkursi/data/ca/`. Tie veido lielāko daļu no ~67 MB.

### Iespējas konveijers nāk no ārpus docroot

`iespeja.php` neko neaprēķina no failiem — visi tās dati nāk no MySQL telpiskās datubāzes, ko
uzbūvē **Python konveijers mapē `../Iespēja`** (ārpus docroot, jo tas ir būves rīks, ne tīmekļa
saturs). Bez tā pakotnē nonāktu lapa bez neviena skripta, kas tās 15 tabulas izveido.

Tāpēc būves skriptā ir `EXTRA_SOURCES`:

```php
const EXTRA_SOURCES = ['../Iespēja' => 'iespeja'];
```

Avota kopija paliek **viena** — mapi nedublējam docroot iekšā. Tieši tāda nolaidusies kopija
savulaik bija `pensionars/iespeja-x.php` ar dzīvu MySQL paroli. Ja avots pazūd, būve **krīt**,
nevis kluso izlaiž sadaļu.

Izslēgšana papildu avotiem strādā pēc ceļa **pakotnē**, tāpēc `EXCLUDE_FILES` ieraksti rakstīti
ar `iespeja/` prefiksu (`iespeja/bar.csv` u.c. — tie ir 1. soļa izvade, skripts tos uzģenerē pats).

Skrubis tabulu prefiksu `mydb_` → `mydb_` maina **gan PHP, gan Python pusē** (`py` ir
`TEXT_EXT` sarakstā), tāpēc pakotnē nosaukumi sakrīt. Pēc katras būves to ir vērts pārbaudīt:

```bash
grep -ohE "mydb_[a-z]+|[a-z]+_geo" saraksts-lv-kods/iespeja.php saraksts-lv-kods/iespeja/*.py | sort -u
```

## Laika zona

Visa sistēma strādā **Europe/Riga** laikā — `registrs/lib/timezone.php` iestata to
procesa noklusējumā, un to ielādē `registrs/build/config.php`, `konkursi/lib/config.php`,
`registrs/head/head.php` un `mi.php`. Ar to pietiek, lai segtu visus ieejas punktus.

Pirms tam PHP noklusējums bija UTC, tāpēc `build.log` un `sync.log` rādīja laiku 3 h
atpakaļ (būve, kas sākās 20:40, žurnālā bija "17:40"). Tas apgrūtināja salīdzināšanu
ar cron un vienreiz jau maldināja, meklējot iekāršanās brīdi.

Pārrakstāms ar vidi: `REG_TZ=UTC php ...` (nederīga zona → droši atkāpjas uz UTC).

Datiem tas neko nemaina: `konkursi_today()` un `ks_within_retention()` jau lietoja
skaidru Europe/Riga, `store.php` raksta `date('c')` ar nobīdi (SQLite abus pierakstus
normalizē uz vienu UTC brīdi), un ar `CURRENT_TIMESTAMP` nekur netiek salīdzināts.

## Konkursi: noturība pret lēniem un mirušiem avotiem

Sinhronizācija iet cauri ~25 ārējiem avotiem, un daļa no tiem mēdz nomirt. Trīs
aizsargi (`konkursi/lib/sync_engine.php`, konstantes `config.php`):

| Aizsargs | Ko dara |
|---|---|
| **Taimautu ķēdes pārtraucējs** | Pēc `KS_HOST_TIMEOUT_TRIP` (3) taimautiem **pēc kārtas** hostam vairs nesūta neko; atdzišana `KS_HOST_TIMEOUT_COOLDOWN_S` (1 h) glabājas meta `host_cooldown_*`, tāpēc darbojas arī nākamajās palaišanās |
| **Posma laika budžets** | `KS_STAGE_MAX_S` (10 min; per-avots `KS_STAGE_MAX_S_BY_SOURCE`). Pēc tā `ks_http_get` pārstāj sūtīt **šī posma** pieprasījumus — tāpēc budžets darbojas visos avotos, arī tajos, kuru cikli laiku paši nepārbauda |
| **Progresa atzīmes** | Garie cikli žurnalē ik pa 25 ierakstiem, lai klusums vairs nebūtu neatšķirams no iekāršanās |

**Kāpēc tas bija vajadzīgs.** `ks_http_get` nenolasīja `curl_errno`, tāpēc taimauts
deva `$code = 0`, kas nav `KS_BLOCK_CODES` sarakstā, un rindā `if ($code !== 0)` tas
izkrita arī no žurnāla. Rezultāts 2026-07-26: 86 min pilnīgi klusas karāšanās uz
`public.mtender.gov.md`. Teorētiskais maksimums bija 400 ieraksti × 5 mēģinājumi
× 45 s ≈ **25 h**.

### Moldova (MTender) — īpašā apstrāde

MTender strādā, pazūd uz ~stundu un atgriežas. Tāpēc te negaidām, bet atkāpjamies
un turpinām nākamreiz:

* `MTENDER_HTTP_TIMEOUT_S` = 15 s (bija 45), `MTENDER_HTTP_TRIES` = 2 (bija 5) —
  miris hosts maksā 30 s uz ierakstu, nevis 225 s;
* **rinda `meta.mtender_pending`** — neizdarītie `ocid` saglabājas, un nākamā
  palaišana sāk **tieši no tiem**, nevis no saraksta sākuma. Bez tā, ja avots
  regulāri krīt, saraksta aste netiktu apstrādāta nekad;
* meklēšana (`mtender.gov.md`) un detaļas (`public.mtender.gov.md`) ir **divi
  dažādi hosti** — viens var strādāt, otrs ne, tāpēc posms neatgriežas uzreiz,
  ja meklēšana klusē, bet rindā vēl kaut kas ir.

## Ko serveris PROT / NEPROT (posmu novērtējums)
Viss cikls ir **tīrs PHP** (curl + pdo_sqlite) — nav vajadzīgs Python, pandas, cron-only rīki:

| Posms | Prasa | Hostinger Cloud Startup |
|---|---|---|
| 1. download | izejošs HTTPS (curl) | ✅ PROT (saudzīgi: pauze + atkārtojumi) |
| 2. convert (CSV→SQLite) | pdo_sqlite, ~1–2 GB disks, straume | ✅ PROT (atmiņa `ini_set 2048M`, straumēts) |
| 3. prepare (FTS + NACE + ceturkšņi) | pdo_sqlite | ✅ PROT |
| 3.5 sections (Nozare/Struktūra/Pensionārs) | pdo_sqlite | ✅ PROT |
| 4. swap (atomiskā nomaiņa) | rename tajā pašā FS | ✅ PROT |
| 5. sitemap + gada-atskaišu izsekošana | faila rakstīšana | ✅ PROT |
| Fona palaišana (data_admin) | proc_open/exec + setsid/nohup | ✅ PROT (diagnostika rāda ✅); ja liegts → cron |

**Ierobežojumi / jāievēro:**
- Pilns cikls ~vairākas minūtes — palaist TIKAI fonā (web pieprasījumu LiteSpeed nokauj → 503).
- Būve tērē CPU/IO — palaista ar pazeminātu prioritāti (`nice`/`ionice`), lai netraucē vietni.
- Būves laikā vajag ~2–3 GB brīvas vietas (CSV + staging + dzīvās DB paralēli).

## Izvietošana Hostingā
1. Augšupielādē `server/` saturu domēna saknē.
2. `htaccess.txt` → pārsauc uz `.htaccess`.
3. **Nomaini `admin_token.php`** slepeno atslēgu.
4. Ieliec `ur_data.db` (un csv/) ārpus docroot; iestati `REG_DATA_DIR` (hPanel PHP env vai .htaccess `SetEnv`).
5. Pirmā datu ielāde: `data_admin.php?k=<atslēga>` → "Palaist fonā" vai iestati cron.
6. Cron (hPanel → Cron Jobs, piem. reizi nedēļā): `php /ceļš/registrs/build/cron_build.php`.

## Datu pārvaldības panelis (data_admin.php)
- **Palaist fonā** (VIENĪGAIS ielādes veids) — proc_open/popen/exec + setsid/nohup, ar
  pazeminātu prioritāti (`nice -n 10` + `ionice -c2 -n7`, ja pieejami), lai būve neizspiestu
  web procesus. Web pieprasījums tūlīt atgriežas; būve turpinās serverī. Sinhronais režīms
  NOŅEMTS — LiteSpeed garu web pieprasījumu nokauj (503), tāpēc tas nekad nebija drošs.
- **Statuss/žurnāls** — atsvaidzina viegls JS AJAX (`?ajax=status`, atgriež tikai JSON), NEVIS
  pilnas lapas meta-refresh (tas būves slodzē iekrita 503). AJAX 503/tīkla kļūmi klusi ignorē
  un mēģina vēlreiz; kad būve pabeidzas, lapu vienreiz pilnībā pārlādē.
- **STOP** — 1. klikšķis saudzīgi (build.stop karogs), 2. klikšķis piespiedu `kill`.
- **Cron ieslēgt/izslēgt** — cron_build.php pārbauda karogu.
- **Diagnostika** — exec funkciju pieejamība, PHP CLI ceļš + "Automātiski atrast PHP".
- Env priedēklis CLI procesam lieto `env VAR=val …` (NE `VAR=val …`) — jo nohup/setsid/nice
  neinterpretē VAR=val sintaksi (mēģinātu palaist "VAR=val" kā komandu).

## Būvēšanas cikls (build_all.php, ~90 s no CSV)
1. download — CSV no data.gov.lv (viens saraksts sedz arī visas sadaļas)
2. convert — CSV → ur_data.db (pandas-veida tipi)
3. prepare — ceturkšņu apvienošana + companies.sqlite (FTS) + nace_stats.sqlite
3.5 sections — **Nozare** (katalogs.sqlite ~16 s), **Struktūra** (struktura.php ~2 s),
   **Pensionārs** (pensionari.sqlite ~4 s) no tās pašas staging ur_data.db + build/NACE.csv;
   izlaižams ar `--skip-sections`
4. swap — atomiska dzīvo DB nomaiņa (pēc validācijas register ≥ 100k), ieskaitot
   nozare/katalogs.sqlite, pensionars/pensionari.sqlite un struktura.php
5. report_tracker — gada-atskaites gadu izsekošana → **sitemap lastmod** tikai mainītajiem +
   **AI keša invalidācija** uzņēmumiem ar jaunu gada pārskatu

## Sadaļu būve atsevišķi (bez pilnā cikla)
```
php registrs/build/sections_cli.php all            # visas trīs -> dzīvās docroot vietas
php registrs/build/sections_cli.php nozare /tmp/x  # viena sadaļa -> norādīta mape
```
Sadaļu PHP porti verificēti pret Python oriģināliem (zelta-diff uz 2026-07-10 CSV kopas).
Zināmās (labās) novirzes: (1) nosaukumi bez pandas 'nan' artefaktiem; (2) Nozares
avg_net_salary izmanto īsto calculate_net_salary() no lib/page_builder.php — Python vidē
trūka core.page_builder un tā klusēja uz aptuveno gross×0.70 (verifikācijai:
REG_NET_FALLBACK70=1); (3) Pensionārs joprojām NEpiemēro rounded_to_nearest reizinātāju
(saglabāts apzināti, saderībai ar Python).

## Gada-atskaites izsekošana (report_years.sqlite)
`build_state/report_years.sqlite`: regcode → pēdējais gada-pārskata gads + maiņas datums.
Pie jaunas būves: ja uzņēmumam parādās jaunāks gada pārskats → `changed_date = šodien`
(sitemap `<lastmod>`) un tā vecās AI atbildes tiek dzēstas (finanšu dati mainījušies →
lietotājs var ģenerēt jaunas). Nemainītajiem datums un AI kešs paliek.

## Verifikācija (zelta-diff pret Python etalonu)
```
export UR_DB_PATH=$PWD/csv/SQLite/ur_data.db
python3 server/tools/golden_gen.py <regcodes...>
python3 server/tools/compare_pagedata.py   # PHP page_data == Python
python3 server/tools/compare_html.py       # PHP html_inner == Python
```
Pēdējais statuss: **48/48 identiski** (ieskaitot negatīva kapitāla uzņēmumus).
PHP config (`registrs/lib/config.php`, `descriptions.php`, `nace_map.php`) AUTO-ĢENERĒTS
no Python — pārģenerē ar `tools/gen_config_php.py` / `gen_descriptions_php.py`.

## Zināmās (labās) novirzes no vecās pandas DB
- Meklēšanas nosaukumi: PHP izlabo Python bugu "IK, None" (~4088 uzņēmumi).
- ATVK kodi: PHP saglabā sākuma nulles ("031010"); pandas chunking tās daļēji zaudēja.
- Finanšu apraksts pie negatīva pašu kapitāla: GRAFIKI (D/E, ROE, ROCE) zīmējas ar īstajām
  vērtībām kā oriģinālā (2026-07-12 atjaunots pēc lietotāja prasības), bet Apraksta tekstā
  šo rādītāju maldinošos teikumus aizstāj skaidrs negatīva kapitāla brīdinājums; katra
  Apraksta rinda vienmēr sākas ar lielo burtu.
- MI atbilžu formatēšana: ja marked.min.js nav pieejams, iebūvētais rezerves pārveidotājs
  formatē **treknrakstu**, ##/### virsrakstus, sarakstus un rindas — atbilde nekad nav
  neformatēts teksts. Katras dzīvās ģenerācijas beigās ir poga "🔄 Pārģenerēt atbildi".

## VID ceturkšņu vēsture (vid_quarterly_history.sqlite + ceturksnis/)
VID publicē tikai PĒDĒJĀ ceturkšņa datus — vēsturi krāj pati būve. Katrā ciklā
`merge_quarterly_history` apvieno (secībā, dublikātos uzvar pēdējais):
1. **vēstures DB** (`vid_quarterly_history.sqlite`) — automātiski uzkrātie kvartāli;
2. **`ceturksnis/` mape** — VISI `*.csv` faili tur (VID formātā); domāta VECIEM kvartāliem,
   kas VAIRS nav lejupielādējami no data.gov.lv. Vienkārši iemet kvartāla CSV `server/ceturksnis/`
   (piem. `2025-4cet pdb_samaksato_nodoklu_kopsummas_cet.csv`) un tas paliek uz visiem laikiem;
3. **svaigais CSV** (jaunākais kvartāls no lejupielādes) — uzvar dublikātos.
Rezultāts ierakstīts gan ur_data.db (VID panelis), gan vēstures DB. Ceļu var pārrakstīt ar
`REG_QUARTER_DIR`. Ceturkšņu CSV mapi `.htaccess` bloķē no tiešas URL piekļuves (būves ievads).

**Kāpēc vēsture ir svarīga arī Struktūrai:** treemap bruto algu un darbinieku skaitu rēķina NO
CETURKŠŅIEM (VID gada tabula atpaliek ~1,5 gadu), summējot pēdējos `SEKT_SALARY_QUARTERS`
ceturkšņus — viens ceturksnis atsevišķi ir pārāk troksnains (prēmijas, sezonalitāte). Jo vairāk
kvartālu vēsturē, jo stabilāks rādītājs; ar 4 uzkrātiem tas kļūst par pilnu 12 mēnešu logu.

`server/vid_quarterly_history.sqlite` paketē (ja pievienots) ir līdz šim uzkrātie ceturkšņi —
tam jābūt DATU SAKNĒ (noklusējumā docroot; ja lieto REG_DATA_DIR — tur). Ja tā nav, bet vecie
kvartāli ir `ceturksnis/` CSV veidā, vēsture tik un tā tiek atjaunota no CSV.
