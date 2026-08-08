# Iespēja — datu konveijers

Sadaļa **Iespēja** (`iespeja.php` pakotnes saknē) ir ģeotelpiska biznesa potenciāla
karte: klikšķini uz vietas kartē, un tā aplēš apmeklētāju plūsmu ap to un 12 biznesa
tipu ienesīgumu.

`iespeja.php` **pati neko neaprēķina no failiem** — visi dati nāk no **MySQL telpiskās
datubāzes**. Šī mape satur skriptus, kas to datubāzi uzbūvē. Bez tiem lapa atvērsies,
bet katrs klikšķis atgriezīs tukšumu.

**Visi deviņi soļi ir PHP** (`php/` apakšmape) — konveijeram nevajag ne `python3`,
ne nevienu Python pakotni, tāpēc visu ķēdi var dzīt cron uz koplietotā hostinga.
Vecie `.py` oriģināli pārcelti ārpus docroot (salīdzināšana pabeigta —
skat. `php/tools/`).

---

## Kas tev vajadzīgs

* **PHP 8.0+** ar `pdo_mysql`, `pdo_sqlite`, `xmlreader`, `zip`, `curl`, `mbstring`, `intl`
* **MySQL 8.0+** vai MariaDB 10.5+ — obligāti ar telpisko tipu atbalstu
  (`POINT`, `SPATIAL INDEX`, `ST_Distance_Sphere`). Skripti tabulas izveido paši.
* **~7 GB brīvas vietas** starpfailiem (`Building.gml` vien ir ~5,5 GB) un
  ~2–4 h laika pilnai ķēdei.
Python un tā pakotnes (`pandas`, `numpy`, `lxml`, `shapely`, `osmium`) vairs nav
vajadzīgas vispār.

## Datubāzes pieslēgums

PHP soļiem tā ir **viena vieta** — `php/config.php`. Prioritāte: vides mainīgie →
`php/config.local.php` → noklusējumi failā. Uz servera ieteicams vides mainīgie:

```
IESPEJA_DB_HOST  IESPEJA_DB_PORT  IESPEJA_DB_NAME
IESPEJA_DB_USER  IESPEJA_DB_PASS
IESPEJA_COUNTRY      — valsts kods (noklusējums: lv)
IESPEJA_DATA_DIR     — mape, kur meklēt ievades failus (citādi darba mape)
```

Administratīvais panelis (`admin.php`) šos mainīgos fona procesam padod pats.

Soļiem 1.–4. pieslēgums nav vajadzīgs — tie raksta tikai failus.
`tools/calibrate_new.py` ir vienīgais palikušais Python rīks; tam `DB` bloks
joprojām ir faila augšā.

`iespeja.php` pieslēgumu un tabulu nosaukumus **iekļauj no šīs pašas vietas**
(`php/config.php` + `php/schema.php`) — savas kopijas tam vairs nav, tāpēc nekas
nav jāsinhronizē ar roku.

## Valsts profils

Viss, kas kodā ir atkarīgs no valsts, dzīvo **`php/countries/<kods>.php`**:
reģionu karte (ēku slāņa šķēlumi lielām valstīm), biroju/iestāžu kalibrācijas
koeficienti, tūrisma svari un POI tipu reģistrs ar OSM selektoriem. Tabulu
nosaukumus no valsts koda saliek `php/schema.php` (`ie_table()`, `ie_regions()`,
`ie_shards_for_bbox()`).

Jauna valsts = nokopēt `countries/lv.php`, aizpildīt reģionus un koeficientus,
un uzrakstīt datu ielasīšanas soļus tās avotiem. Shēma, vaicājumi un frontends
nemainās. Pirktspējas līmeņi (A–E) tiek rēķināti kā **svērtās kvantiles no pašas
valsts datiem**, nevis absolūti € sliekšņi, tāpēc tie pārkalibrējas paši.

---

## Palaišanas kārtība

Skriptu numuri ir izpildes secība. Palaid tos **no šīs mapes**, jo starpfaili tiek
rakstīti blakus.

| # | Skripts | Ko dara | Ievade | Izvade |
|---|---|---|---|---|
| 1 | `php/step1_poi.php` | prasa 4 pamata POI tipus no Overpass | Overpass API | `bar.csv`, `cofe.csv`, `food.csv`, `frizieri.csv` |
| 2 | `php/step2_leaflet.php` | lejupielādē Leaflet jaunāko laidienu | GitHub | `leaflet/` |
| 3 | `php/step3_summ_level.php` | lejupielādē VZD kadastra atvērtos datus un saskaita iedzīvotājus/stāvus pa ēkām | data.gov.lv | `out-summ-level.csv` |
| 4 | `php/step4_all.php` | lejupielādē ēku ģeometriju (INSPIRE GML) un pieliek koordinātes | kadastrs.lv Atom | `out-all.csv` |
| 5 | `php/step5_upload.php` | ieraksta MySQL ēku slāni + pamata POI tipus | 1. un 4. solis | `lv_buildings` + `lv_poi` (4 tipi) |
| 6 | `php/step6_offices.php` | biroju/komerciālo ēku slānis (dienas darbinieku plūsma) | 3. un 4. solis | `lv_offices` |
| 7 | `php/step7_tourism.php` | tūrisma objektu slānis (tūristu plūsma) | `Turisma objekti.txt` | `lv_tourism` |
| 8 | `php/step8_institutions.php` | iestāžu slānis — skolas, slimnīcas, stacijas, sports | 3. un 4. solis | `lv_institutions` |
| 9 | `php/step9_competitors.php` | pārējo biznesa tipu konkurenti no Overpass | Overpass API | `lv_poi` (7 tipi) |

Vecie Python soļi ir saglabāti salīdzināšanai, bet vairs netiek palaisti — administratīvais panelis
dzen PHP versijas. Soļi 5.–9. prot **sauso režīmu**, kas MySQL neaiztiek:

```bash
php php/step5_upload.php --dry-run=/tmp/parbaude
```

## OSM avots: PBF vai Overpass

1. un 9. solis POI ievāc no avota, ko nosaka valsts profila `osm.source`:

| | `pbf` (noklusējums) | `overpass` |
|---|---|---|
| Avots | Geofabrik izgriezums, `php/pbf.php` | Overpass API |
| Atkarības | nav (tikai `zlib`) | nav |
| Latvijas laiks | **~15 s** (1. solis 25 s ar lejupielādi) | 43 min pēdējā palaišanā |
| Mērogs uz DE | strādā, pa federālajām zemēm | nestrādā |

`php/pbf.php` ir pilnībā PHP rakstīts `.osm.pbf` lasītājs (protobuf varint +
zlib bloki), tāpēc **nav vajadzīgs ne `osmium`, ne root, ne kompilators** — tas
darbojas arī tur, kur var tikai augšupielādēt failus. Atmiņas patēriņš ~40 MB
neatkarīgi no faila izmēra: bloki tiek straumēti pa vienam.

Lielai valstij `pbf_url` liek katram reģionam atsevišķi — Geofabrik publicē
izgriezumus pa federālajām zemēm, un tie sakrīt ar ēku slāņa šķēlumiem.

**Robeža.** Geofabrik izgriezums pārkar pāri valsts robežai, tāpēc Python laikā
konkurentos nonāca **16 ārzemju uzņēmumi** — Valgas (Igaunija) puse Valkas
dvīņupilsētā, Ruhnu sala un viens Lietuvā. To atrisina **precīza robeža no paša
PBF faila**: administratīvās robežas relācija ar `ISO3166-1` (Latvijai 503 ceļi,
35 470 punkti) — tas ir tas pats objekts, ko Overpass lieto `area[…]` filtrā.

> Geofabrik `.poly` fails tam **neder**: tajā ir tikai 151 punkts, un pārbaudē
> Valga iekrita Latvijas iekšpusē. Turklāt izgriezumā trūkst 13 no 503 robežas
> ceļiem (jūras posmi ārpus izgriešanas apgabala), tāpēc gredzenu noslēgšana
> aizlāpa robus ģeometriski — pār jūru, kur uzņēmumu nav.

Pārbaude pret Overpass rezultātu: **6741 pret 6743 objektiem (−0,03 %)**, un
per-tipa atšķirības sakrīt ar OSM dienas dreifu starp momentuzņēmumu un tiešo
vaicājumu.

### 1. solis: kas mainījās kopš Python

**Elementu tipi.** Python apstrādāja tikai OSM `node` — restorāns, kas kartē
uzzīmēts kā ēkas kontūra (`way`), pazuda. **Noklusējums šeit ir tas pats**, lai
ports paliek ports un konkurentu skaits nemainās.

`--with-ways` pieliek arī `way` un `relation`, tāpat kā 9. solī:

| Fails | osmium (PY) | PHP noklusējums | PHP `--with-ways` |
|---|---|---|---|
| `bar.csv` | 341 | **339** | 373 |
| `cofe.csv` | 722 | **717** | 835 |
| `food.csv` | 1118 | **1109** | 1271 |
| `frizieri.csv` | 353 | **353** | 365 |

Paplašinātais režīms dod +326 objektus (+12,9 %) un padara visus 11 konkurentu
slāņus savstarpēji salīdzināmus — 9. solis `way`/`relation` ņem vienmēr, tāpēc
šiem četriem tipiem konkurentu ir sistemātiski mazāk. Bet tā **jau ir datu izmaiņa,
ne ports**: pēc tās 12 biznesa tipu konkurences koeficienti `iespeja.php` var būt
jāpārkalibrē (`tools/calibrate_new.py`). Tāpēc tas ir aiz karoga.

**2. solis nav obligāts.** Pašreizējā `iespeja.php` Leaflet ņem no CDN
(`unpkg.com/leaflet@1.9.4`). Skripts noder tikai tad, ja gribi Leaflet lokāli —
tad `iespeja.php` jāizlabo arī `<script>` un `<link>` rindas. Atšķirībā no Python
versijas veco `leaflet/` tas izdzēš tikai tad, kad jaunais saturs jau ir gatavs,
tāpēc pārtraukta lejupielāde lapu neatstāj bez kartes.

Soļi 6–9 ir neatkarīgi cits no cita — kad 3., 4. un 5. solis nostrādājis, tos drīkst
palaist jebkurā secībā vai atkārtot atsevišķi. Katrs sākas ar `TRUNCATE`, tāpēc
atkārtota palaišana neveido dublikātus.

---

## Kādas tabulas top

`iespeja.php` prasa **5 tabulas** (Latvijai — lielā valstī ēku slānis dalās pa
reģioniem un tabulu ir vairāk). Visas izveido skripti (`CREATE TABLE IF NOT
EXISTS`), atsevišķs SQL dumps nav vajadzīgs. Nosaukums = `<valsts>_<slānis>`,
un to saliek `php/schema.php` — **nevienā citā failā tabulu vārdi nav ierakstīti**.

**Plūsmas slāņi** — no tiem aprēķina apmeklētāju skaitu ap izvēlēto punktu:

| Tabula | Solis | Nozīme |
|---|---|---|
| `lv_buildings` | 5 | dzīvojamās ēkas: iedzīvotāju skaits, stāvu līmenis |
| `lv_offices` | 6 | biroji un komercplatības: aplēstie darbinieki |
| `lv_tourism` | 7 | naktsmītnes un apskates objekti, svērti pēc populāritātes |
| `lv_institutions` | 8 | skolas, slimnīcas, stacijas, izklaide, sports |

Lapā tie atbilst četriem ieslēdzamajiem avotiem (iedzīvotāji / biroji / tūrisms / iestādes).

**Konkurentu slānis** — viena tabula `lv_poi` ar `ptype` kolonnu (4 tipi no
5. soļa: `cafe`, `restaurant`, `bar`, `hairdresser`; 7 tipi no 9. soļa: `bakery`,
`pharmacy`, `beauty`, `minimarket`, `dentist`, `fastfood`, `fitness`). Jauns
biznesa tips = viena rinda valsts profila `poi` sadaļā, tabulas nav jāveido.
Viesnīcas konkurenti nāk no `lv_tourism` (`ttype` naktsmītņu filtrs), ne no POI.

**Migrācija no vecajiem nosaukumiem** (`buildings_geo`, `mydb_*` u.c.):
`php tools/migrate_tables.php` — kopē serverī ar INSERT…SELECT, vecās tabulas
nedzēš (DROP sarakstu izdrukā beigās, palaid to ar roku pēc pārbaudes).

---

## `Turisma objekti.txt`

Vienīgais datu fails, ko konveijers **nelejupielādē pats** — tāpēc tas ir pakotnē
(2,2 MB, 8282 objekti, izgūts 2026-07-22). Tas ir Overpass API eksports.

Atsvaidzināt vari [overpass-turbo.eu](https://overpass-turbo.eu) ar šo vaicājumu,
saglabājot rezultātu ar to pašu nosaukumu:

```
[out:json][timeout:180];
area["ISO3166-1"="LV"][admin_level=2]->.lv;
(node["tourism"](area.lv);way["tourism"](area.lv);relation["tourism"](area.lv););
out center tags;
```

`7 Tourism.py` no tā atlasa 13 plūsmu ģenerējošos tipus un izmet informācijas dēļus,
skulptūras un piknika vietas.

---

## Kā pārbaudīts, ka PHP dara to pašu (`php/tools/`)

Ports nav ticams tikai tāpēc, ka izpildās. Divi rīki to pierāda ar skaitļiem:

| Rīks | Ko pierāda |
|---|---|
| `php/tools/gml_centroid_diff.php` | PHP centroīdu pret **shapely**. Etalons ir `out-all.csv`, ko uzrakstīja 4. solis ar shapely, tāpēc katra tā rinda ir shapely atbilde uz to pašu poligonu. Rezultāts: **337 323 no 337 334 identiski līdz 8 zīmēm**, 11 atšķiras par ≤0,6 mm. |
| `php/tools/pyshim/` + `php/tools/rows_diff.php` | Visu 5.–9. soļa rindu pret Python. `pyshim` ir viltus `mysql.connector`, kas ierakstus izraksta failā MySQL vietā, tāpēc salīdzinājums neaiztiek ražošanas datubāzi. Rezultāts: **396 868 rindas 13 tabulās, viens lauks atšķiras** (garuma grāds 8. zīmē, 0,6 mm). |

Salīdzinājuma atkārtošana:

```bash
PYSHIM_OUT=/tmp/py PYTHONPATH=php/tools/pyshim python3 "6 Offices.py"
php php/step6_offices.php --dry-run=/tmp/php
php php/tools/rows_diff.php /tmp/php/offices_geo.csv /tmp/py/offices_geo.csv
```

9. solim abām pusēm jāredz viena Overpass atbilde, citādi atšķirības rada OSM
izmaiņas, ne kods. To dod `IESPEJA_OVERPASS_FIXTURES` (PHP puse saglabā atbildi
diskā) kopā ar `php/tools/fakecurl` PATH sākumā (Python puse to nolasa).

## Kalibrācijas rīki (`tools/`)

Nav vajadzīgi, lai sistēma strādātu — ar tiem tika iestatīti 12 biznesa tipu skaitļi
`iespeja.php` (apmeklētāji uz 1000 iedzīvotājiem, vidējais čeks, sākotnējā investīcija).

| Rīks | Ko dara |
|---|---|
| `tools/calibrate_new.py` | salīdzina modeļa aplēses ar reāliem uzņēmumu apgrozījumiem un iesaka koeficientus |
| `tools/audit12.py` | pārbauda visus 12 biznesa tipus pēc kārtas |
| `tools/audit_edge.py` | robežgadījumi — tukši lauki, retas vietas, ekstrēmi rādiusi |

Ja pielāgo citai valstij vai citiem biznesa tipiem, sāc ar `calibrate_new.py`.
Skaitļi `iespeja.php` ir kalibrēti pret **Latvijas** datiem un citur būs nepareizi.

---

## Datu licences

Konveijers lejupielādē un šī mape satur trešo pušu datus. Uz tiem **MIT licence
neattiecas**:

* **OpenStreetMap** (`Turisma objekti.txt`, 1. un 9. solis) — © OpenStreetMap
  contributors, **ODbL 1.0**. Publicējot rezultātus, jānorāda avots; atvasinātām
  datubāzēm ODbL prasa tās dalīties ar tādiem pašiem noteikumiem.
  https://www.openstreetmap.org/copyright
* **VZD kadastra atvērtie dati** (3. un 4. solis) — data.gov.lv un
  grafws.kadastrs.lv lietošanas noteikumi.

Ēku dati satur adreses un platības, bet **ne personu vārdus**. Aprēķinātais
iedzīvotāju skaits ir statistiska aplēse pēc platības un stāvu skaita, nevis reāli
deklarētie cilvēki.
