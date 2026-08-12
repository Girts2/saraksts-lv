# Saraksts.lv — pilnais pirmkods

Šī ir **dāvana**. Kods ir brīvi izmantojams — attīsti tālāk, pārveido, izmanto
savām darba vajadzībām vai komerciāli. Sīkāk: `LICENSE` un `NOTICE.md`.

Pirms sākt, izlasi **`NOTICE.md`** — īpaši sadaļu par Swiss Ephemeris.

---

## Kas ir iekšā

Viena PHP koda bāze, kas apkalpo astoņas sadaļas:

| Sadaļa | Fails | Ko dara |
|---|---|---|
| Reģistrs | `index.php`, `company.php` | LV uzņēmumu meklēšana un individuālās lapas |
| Nozare | `nozare.php` | nozaru analītika pēc NACE kodiem |
| Struktūra | (būves izvade) | uzņēmumu treemap karte |
| Konkursi | `konkursi.php` | iepirkumi no TED (ES) + ~46 nacionāliem/IFI avotiem, 44 valstis (~200 tūkst. paziņojumu pēc sinhronizācijas) |
| Iespēja | `iespeja.php` + `iespeja/` | ģeotelpiska biznesa potenciāla karte |
| Pensionārs | `pensionars.php` | ilgtermiņa uzņēmumu portfelis |
| Horoskops | `horoskops.php` | statiska astroloģijas lietotne (AGPL — skat. NOTICE.md) |
| Lejupielāde | `lejupielade.php` | šī lapa |

## Kas NAV iekšā (un kāpēc)

Pakotnē ir **tikai kods un tie dati, ko kods nevar iegūt pats**. Nav iekļauts:

* `csv/` — atvērtie dati no data.gov.lv. **Kods tos lejupielādē pats**
  (`registrs/build/download.php` vai admin panelis).
* `*.sqlite`, `*.db` — visas datubāzes. Tās **ģenerē būve** no lejupielādētajiem CSV.
* `struktura/data/`, `struktura.php`, `sitemap/` — būves izvade.
* `registrs/ai_cache/` — MI atbilžu kešs.
* `konkursi/db/tenders.db` — iepirkumu datubāze; to uzbūvē sinhronizācija.

Iekļauts tāpēc, ka **kods to nevar lejupielādēt**: `nozare/nace-foto/` (MI
ģenerēti attēli), `horoskops/onet/` (O\*NET CSV), `ceturksnis/` (VID ceturkšņu
CSV, kas vairs nav pieejami data.gov.lv), `konkursi/data/ca/` (sertifikāti),
`iespeja/Turisma objekti.txt` (OSM Overpass eksports — vienīgais Iespējas datu
fails, ko konveijers neizgūst pats).

## Sadaļa Iespēja prasa atsevišķu soli

`iespeja.php` neko neaprēķina no failiem — visi tās dati nāk no **MySQL telpiskās
datubāzes**. Mapē **`iespeja/`** ir datu konveijers (9 soļi, tīrs PHP — Python
nevajag), kas to datubāzi uzbūvē no OpenStreetMap un VZD kadastra atvērtajiem
datiem. Bez tā palaišanas lapa atveras, bet katrs klikšķis kartē atgriež tukšumu.

Konveijeram vajag to pašu PHP 8.1+, MySQL 8+ ar telpisko atbalstu, ~7 GB brīvas
vietas starpfailiem un ~2–4 h. Soli pa solim: **`iespeja/README.md`**.

## Noslēpumi ir noņemti

No šīs pakotnes ir izņemtas visas autora atslēgas un paroles. Lai sistēma pilnībā
strādātu, ieliec savas:

| Fails | Kas jāieliek |
|---|---|
| `admin_token.php` | tavs slepenais admin tokens (vai `ADMIN_TOKEN` vides mainīgais) |
| `registrs/mi/key.php` | Google Gemini API atslēga (vai `GEMINI_API_KEY`) — neobligāti, MI funkcijām |
| `iespeja.php` (augšā) | MySQL resursdators / DB / lietotājs / parole telpiskajai datubāzei |
| `iespeja/php/config.php` | tie paši MySQL dati konveijeram (vai `IESPEJA_DB_*` vides mainīgie) |

Papildus **noteikti nomaini**, ja publicē vietni:

* Google Analytics ID `G-XXXXXXXXXX` (vai izdzēs gtag blokus).
* E-pasta adreses `info@example.com` / `admin@example.com`.
* `registrs/cookie/cookieconsent-init.js` — sīkdatņu un VDAR teksti ir rakstīti
  konkrēti saraksts.lv. **Tie ir juridiski saistoši tavai vietnei** — pārraksti tos.

## Uzstādīšana īsumā

1. Vajag **PHP 8.1+** (ieteicams 8.3+) ar `pdo_sqlite`, `zip`, `curl`, `mbstring`.
2. Augšupielādē saturu domēna saknē (`public_html/`).
3. Pārsauc `htaccess.txt` → `.htaccess`.
4. Nomaini `admin_token.php`.
5. Atver `/data_admin.php?k=<tavs tokens>` un palaid būvi ("Palaist fonā").
   Pirmā būve lejupielādē ~2 GB atvērto datu un aizņem vairākas stundas.
6. Konkursu sadaļai: `/konkursi_admin.php?k=<tavs tokens>` → sinhronizācija.
7. Sadaļai Iespēja — atsevišķi: MySQL datubāze + `iespeja/README.md`.
   Pārējās septiņas sadaļas no tās nav atkarīgas; ja Iespēja nav vajadzīga,
   izdzēs `iespeja.php` un mapi `iespeja/`.

Sīkāk skat. `README.md` (izstrādātāja piezīmes) un komentārus pašā kodā.

## Godīgi par kvalitāti

Šo kodu praktiski pilnībā ir uzrakstījis mākslīgais intelekts (Claude un Gemini),
attēlus — Midjourney. Kods apstrādā lielus datu apjomus, un visas rezultātu
variācijas ir grūti prognozēt. **Kodā var būt loģikas kļūdas, kas dod nepareizu
rezultātu.** Garantiju nav (skat. `LICENSE`). Pārbaudi rezultātus, pirms uz tiem
balsties.

## Par personas datiem — izlasi obligāti

Vairāki skripti lejupielādē no data.gov.lv **fizisku personu datus** (amatpersonas,
patiesā labuma guvēji, dalībnieki — vārdi, uzvārdi, maskēti personas kodi):

* `registrs/build/download.php` — `officers.csv`, `beneficial_owners.csv`, `members.csv`
* būves soļi sadaļām Reģistrs, Struktūra un Pensionārs

Tiklīdz tu šos datus lejupielādē, **tu kļūsti par datu pārzini VDAR (GDPR) izpratnē**.
Tev pašam jānodrošina apstrādes tiesiskais pamats, glabāšanas termiņi, datu subjektu
tiesību ievērošana un informēšana. Pārliecinies, ka tev ir pamatots mērķis
(piemēram, AML/KYC pārbaude). Šī pakotne tev tādu pamatu **nedod**.

Saraksts.lv produkcijā personas datu attēlošana ir apzināti ierobežota. Ja tu
kodu palaid nemainītu, tas var apstrādāt vairāk datu, nekā tev ir tiesības.
