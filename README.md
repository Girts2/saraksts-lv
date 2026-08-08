# saraksts.lv — Latvijas uzņēmumu datu platformas pirmkods

Pilns [saraksts.lv](https://saraksts.lv) pirmkods: bezmaksas Latvijas uzņēmumu
katalogs ar ~232 000 lapām — gada pārskati, finanšu koeficienti, Altmana Z-indekss,
vidējo algu aprēķins no VID datiem, nozaru analītika, publisko iepirkumu meklētājs
44 valstīs un ģeotelpiska biznesa potenciāla karte.

Viss būvēts uz [data.gov.lv](https://data.gov.lv) atvērtajiem datiem
(Uzņēmumu reģistrs, VID) — kods datus lejupielādē un apstrādā pats.

## Ar ko sākt

| Dokuments | Kas tajā ir |
|---|---|
| [README-PIRMS-SAKAM.md](README-PIRMS-SAKAM.md) | **Sāc šeit**: kas ir iekšā, uzstādīšana, noslēpumu konfigurācija, VDAR piezīmes |
| [README-DEV.md](README-DEV.md) | Izstrādātāja piezīmes: docroot struktūra, būves konveijers, admin paneļi |
| [NOTICE.md](NOTICE.md) | Trešo pušu licences — **obligāti izlasi** sadaļu par Swiss Ephemeris (AGPL) |
| [LICENSE](LICENSE) | MIT licence (autora kodam) |

Īsā versija: vajag PHP 8.1+ un vienu domēnu; pirmā datu būve lejupielādē ~2 GB
atvērto datu un aizņem dažas stundas. Sadaļai «Iespēja» papildus vajag MySQL —
skat. [iespeja/README.md](iespeja/README.md).

## Statuss: produkcijas spogulis

Šis repozitorijs ir **saraksts.lv produkcijas koda spogulis**. Izmaiņas nāk no
produkcijas vides; ārējie labojumu pieprasījumi (pull requests) netiek aktīvi
skatīti. Kļūdu ziņojumi ir laipni gaidīti — Issues šeit vai info@saraksts.lv.
Par datu precizitāti un iebildumiem: https://saraksts.lv/dati.php

## Licences kopsavilkums

Autora kods — **MIT** (dari ko gribi, saglabā paziņojumu). Izņēmumi:
`horoskops/swisseph/` ir **AGPL-3.0** (vai Astrodienst komerclicence), OpenStreetMap
dati — **ODbL 1.0**, O\*NET dati — **CC BY 4.0**. Detaļas: [NOTICE.md](NOTICE.md).

No pakotnes ir izņemtas visas API atslēgas, paroles un analītikas ID
(aizstāti ar vietturiem) — ko un kā skrubē, redzams
[tools/build_download.php](tools/build_download.php).

---

## English

Full source code of [saraksts.lv](https://saraksts.lv) — a free Latvian company
data platform: ~232,000 company pages with annual reports, financial ratios,
Altman Z-scores, salary estimates from tax data, industry analytics, a public
procurement search covering 44 countries, and a geospatial business-opportunity map.

Built entirely on Latvian open data ([data.gov.lv](https://data.gov.lv)); the
code downloads and processes all datasets itself. PHP 8.1+, SQLite, no framework.

Author's code is **MIT-licensed**; see [NOTICE.md](NOTICE.md) for third-party
components (notably Swiss Ephemeris under AGPL-3.0) and
[README-PIRMS-SAKAM.md](README-PIRMS-SAKAM.md) for setup (Latvian). This
repository is a production mirror — bug reports welcome, PRs not actively reviewed.
