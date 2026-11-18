# Uzvedne nākamajam pētījumam: grantu datu AVOTI (ne programmas)

> Kopējams teksts pētniecības rīkam. Mērķis nav uzzināt, kādas finansējuma programmas pastāv —
> tas mums jau ir. Mērķis ir uzzināt, KUR tās stāv mašīnlasāmā formā un ko drīkst ar tām darīt.

---

Tu esi datu inženieris, ne finansējuma konsultants. Es uzturu publisku vietni, kas apkopo
**atvērtos grantu konkursus ar termiņu** un katru nakti tos pārbūvē ar PHP skriptu. Man jau ir:
ES Funding & Tenders portāla bulk JSON (visas centralizētās EK programmas — Apvārsnis, LIFE,
CERV, Radošā Eiropa, Erasmus+ centralizētā daļa, Inovāciju fonds) un SIF publiskais JSON API.

**NEVAJAG** aprakstus par to, kas ir granti, kādas ir atbalsta intensitātes, kādi ir vērtēšanas
kritēriji vai kā rakstīt pieteikumu. Katra rindiņa par to ir nederīga.

## Ko tieši vajag

Par katru zemāk nosaukto avotu atbildi ar VIENU tabulas rindu un pierādījumu:

| Lauks | Prasība |
|---|---|
| `avots` | iestāde/programma |
| `url` | **precīza adrese**, kas atdod konkursu sarakstu (ne mājaslapa) |
| `http` | statusa kods, ko TU redzēji, + `Content-Type` + atbildes izmērs baitos |
| `formats` | JSON API / JSON:API / REST / RSS / XML sitemap / HTML tabula / HTML ziņas / PDF / tikai autorizētiem |
| `izraksts` | 200 rakstzīmju **burtisks** fragments no atbildes (ne pārstāsts) |
| `lauki` | vai atbildē ir atsevišķi lauki: nosaukums, atvēršanas datums, **termiņš**, budžets, pieteikšanās saite. Uzskaiti lauku nosaukumus tā, kā tie ir datos |
| `biezums` | cik konkursu gadā un cik bieži saraksts mainās |
| `robots` | `robots.txt` rindas, kas attiecas uz šo ceļu (burtiski), vai vietne nosauc MI rāpuļus, vai ir `Content-Signal` |
| `licence` | adrese uz lietošanas noteikumiem + burtisks citāts par atkalizmantošanu. Ja nav — raksti "nav deklarēta" |
| `kontakts` | e-pasts, kam rakstīt, ja datus vajag ar atļauju |

## Avoti, kas jāpārbauda

**Latvija (prioritāte):** CFLA (ES fondu atlases), LIAA/business.gov.lv, ALTUM, LAD, LZP, VKKF,
VIAA (Erasmus+ nacionālās atlases), JSPA, NVA, EKII/Vides investīciju fonds, Rīgas dome un vēl
3–5 lielākās pašvaldības, Latvijas Vēstnesis / TAP portāls (vai atlases tiek izsludinātas oficiāli).

**Eiropa, kas NAV ES Funding & Tenders portālā:** EEZ/Norvēģijas granti, Interreg (Baltic Sea
Region, Central Baltic), COST, Nordplus, Nordic Culture Point, EIT KIC akceleratori,
Eiropas Savienības Oficiālais Vēstnesis C sērija (vai "call for proposals" ir nolasāmi caur
EUR-Lex/CELLAR API), NATO DIANA.

## Atsevišķi jautājumi, uz kuriem gribu atbildi

1. Vai kādai no LV iestādēm konkursi ir **atsevišķs datu tips**, nevis ziņu raksts? (Man zināms:
   LIAA ir `atbalsta_programma` ar saviem laukiem; JSPA ir `competition` tips savā vietnes kartē.)
2. Vai kāda no tām publicē **RSS** vai ievieto datus data.gov.lv? (Es pārbaudīju: data.gov.lv ir
   tikai finansējuma SAŅĒMĒJI, ne konkursi — apstiprini vai atspēko.)
3. Vai ES programmu **decentralizētās** daļas (Erasmus+ KA1/KA2 nacionālās atlases) kaut kur ir
   mašīnlasāmas, vai tikai kā nacionālās aģentūras PDF?
4. Vai pastāv kāds **oficiāls apkopotājs** (EK, Ziemeļu Ministru padome, valsts), ko drīkst
   pārpublicēt, pretstatā komerciālajiem (euro-access.eu u.c.)?
5. Kurām no šīm iestādēm ir **atvērto datu politika vai API ceļvedis** (dokuments, ne solījums)?

## Kvalitātes noteikumi (bez tiem atbilde ir nederīga)

* Katrs apgalvojums ar **adresi, kas atveras**. Nav adreses — raksti `NEPĀRBAUDĪTS`.
* Nekādu izdomātu citātu. Ja nevari nolasīt lapu, saki to; neizdomā tās saturu.
* **Portāls un API ir divi dažādi hosti.** Nesajauc `iestade.gov.lv` ar `api.iestade.gov.lv`.
* HTTP kods vien nepierāda, ka dati ir: 200 ar tukšu JSON un 200 ar JS čaulu ir divas dažādas
  kļūmes. Pasaki, kas atbildē patiesībā bija.
* Ja adrese prasa autorizāciju vai captcha — tas ir derīgs rezultāts, tikai to nosauc tieši.
* Beigās uzskaiti atsevišķi: (a) **gatavi lietošanai**, (b) **nolasāmi ar parsēšanu**,
  (c) **nav pieejami un kāpēc**, (d) **kam jāraksta vēstule**.
