# NOTICE — trešo pušu sastāvdaļas un to licences

Projekta autora kods ir MIT licencē (skat. `LICENSE`). Šajā pakotnē tomēr ir
iekļautas arī citu autoru bibliotēkas un datu kopas, kurām ir **savas licences**.
MIT licence uz tām **neattiecas** un autors nevar piešķirt tiesības, kas tam
nepieder. Zemāk ir pilns saraksts.

---

## ⚠️ Vissvarīgākais: Swiss Ephemeris (AGPL-3.0)

**Atrašanās vieta:** `horoskops/swisseph/`
**Autors:** Astrodienst AG, Šveice
**Licence:** GNU Affero General Public License v3 (`horoskops/swisseph/LICENSE`)

Swiss Ephemeris ir **duāli licencēts**: AGPL-3.0 **vai** maksas Professional licence
no Astrodienst. Astrodienst formulējums: izvēle jāizdara *pirms* koda izplatīšanas
citiem **un pirms tiek aktivizēts jebkurš publisks serviss**, kas to lieto.

Sadaļa `horoskops/` izsauc Swiss Ephemeris (WASM), tāpēc **uz šo apakšsistēmu
attiecas AGPL-3.0, nevis MIT**. Pārējās projekta daļas Swiss Ephemeris neizmanto.

### Ja tu publicē Horoskopu tīmeklī

Tev jāizvēlas viens no trim ceļiem:

1. **AGPL ceļš (bez maksas).** Piedāvā sava Horoskopa pilnu pirmkodu AGPL-3.0
   licencē tiem, kas lapu lieto. AGPL 13. pants prasa šo piedāvājumu izdarīt
   *redzami* — praktiski pietiek ar skaidru saiti uz pirmkodu pašā lapā.
   MIT ir AGPL-saderīga, tāpēc kopīgie MIT gabali (galvene, kājene) nav problēma:
   apvienoto darbu izplati ar AGPL.
2. **Maksas ceļš.** Nopērc Swiss Ephemeris Professional licenci
   (https://www.astro.com/swisseph/) — tad pirmkods nav jāatklāj.
3. **Izņem ārā.** Izdzēs mapi `horoskops/` un `horoskops.php`. Reģistrs, Nozare,
   Struktūra, Konkursi, Iespēja un Pensionārs no tās nav atkarīgi un paliek MIT.

### Ko licence NEIEROBEŽO: rezultātus

Licence attiecas uz **programmatūru**, nevis uz to, ko tā aprēķina. Horoskopi,
kartes, interpretācijas un planētu pozīcijas, ko lietotne uzģenerē, tev pieder —
tos drīksti publicēt, pārdot un izmantot bez ierobežojumiem. Planētu pozīcijas
turklāt ir fakti, un fakti nav aizsargājami ar autortiesībām.

### Papildu nosacījums

Astrodienst licence prasa saglabāt autortiesību paziņojumus visās kopijās un
aizliedz izmantot autoru vai Astrodienst vārdu savas programmatūras, produkta
vai pakalpojuma reklamēšanai.

> Šis ir praktisks kopsavilkums, nevis juridiska konsultācija. Ja par savu
> gadījumu neesi drošs, jautā Astrodienst — viņi atbild publiskajā sarakstē
> https://groups.io/g/swisseph

---

## Iekļautās programmbibliotēkas

| Sastāvdaļa | Atrašanās vieta | Licence | Pienākums |
|---|---|---|---|
| Swiss Ephemeris | `horoskops/swisseph/` | AGPL-3.0 vai komerciāla | skat. augšā |
| Moment.js | `horoskops/js/timezone/moment.min.js` | MIT | saglabāt paziņojumu |
| Moment Timezone | `horoskops/js/timezone/moment-timezone-with-data.js` | MIT | saglabāt paziņojumu |
| D3.js | `registrs/assets/js/lib/d3.min.js` | ISC | saglabāt paziņojumu |
| d3-sankey | `registrs/assets/js/lib/d3-sankey.min.js` | BSD-3-Clause | saglabāt paziņojumu |
| Chart.js | `registrs/assets/js/lib/chart.umd.min.js` | MIT | saglabāt paziņojumu |
| marked | `registrs/assets/js/lib/marked.min.js` | MIT | saglabāt paziņojumu |
| CookieConsent (Orest Bida) | `registrs/cookie/cookieconsent.umd.js`, `.css` | MIT | saglabāt paziņojumu |

Bibliotēkas, ko lapas ielādē no CDN un kas **nav** šajā pakotnē: Font Awesome
(CC BY 4.0 ikonas / MIT kods), Leaflet (BSD-2-Clause), Google Fonts (OFL).

## Iekļautās datu kopas

| Datu kopa | Atrašanās vieta | Avots un licence |
|---|---|---|
| O\*NET profesiju dati | `horoskops/onet/*.csv` | O\*NET® datubāze, ASV Darba departaments. **CC BY 4.0 — prasa atsauci uz O\*NET.** O\*NET ir ASV DOL reģistrēta preču zīme. |
| NACE klasifikators | `registrs/build/NACE.csv`, `nozare/`, `pensionars/` | Eurostat / CSP saimniecisko darbību klasifikācija — publiska |
| Nozaru attēli | `nozare/nace-foto/*.webp` | Ģenerēti ar MI (Midjourney). Iekļauti tāpēc, ka kods tos nevar lejupielādēt. Pārbaudi sava MI rīka noteikumus, ja tos izplati tālāk. |
| CA starpsertifikāti | `konkursi/data/ca/*.pem` | publiski sertificēšanas iestāžu sertifikāti |
| Tūrisma objekti | `iespeja/Turisma objekti.txt` | © OpenStreetMap contributors, **ODbL 1.0** — skat. zemāk |

### OpenStreetMap dati (ODbL 1.0)

Fails `iespeja/Turisma objekti.txt` ir OpenStreetMap izgūtne (Overpass API), un
Iespējas konveijera 1. un 9. solis lejupielādē vēl vairāk OSM datu. Uz tiem attiecas
**Open Database License 1.0**, nevis MIT:

* publicējot rezultātus, jānorāda **© OpenStreetMap contributors**;
* ja izplati **atvasinātu datubāzi** (piemēram, savu MySQL slāni), ODbL prasa to
  darīt ar tādiem pašiem noteikumiem;
* aprēķinātus rezultātus (kartes attēlus, aplēses) drīksti publicēt ar atsauci.

https://www.openstreetmap.org/copyright

Iespējas konveijers lejupielādē arī **VZD kadastra atvērtos datus** (data.gov.lv,
grafws.kadastrs.lv) — tiem savi lietošanas noteikumi.

## Publiskās API atslēgas, kas palikušas kodā

Šīs **nav** autora personiskās atslēgas — tās ir publiski dokumentētas testa /
parauga atslēgas, un tās ir atstātas, lai kods darbotos uzreiz:

* `konkursi/lib/config.php` — `CVPIS_API_KEY` (Lietuvas VPT publiskā testa atslēga;
  produkcijai pieprasi savu: pagalba@vpt.lt)
* `konkursi/lib/config.php` — `HILMA_API_KEY` (Somijas bezmaksas izstrādātāju portāla atslēga)
* `konkursi/lib/config.php` — `BOSA_CLIENT_SECRET` (Beļģijas anonīmais Keycloak klients;
  publicēts viņu pašu `env.config.js`)

Nopietnai lietošanai iegūsti savas atslēgas.
