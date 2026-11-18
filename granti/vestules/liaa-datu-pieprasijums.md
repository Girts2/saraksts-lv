# Vēstules melnraksts: LIAA — atbalsta programmu publiskais saraksts

> MELNRAKSTS. Nav nosūtīts. Sūtītājs un paraksts jāieliek cilvēkam.
> Adresāts: jautajumi@liaa.gov.lv (kopija: pasts@liaa.gov.lv)

---

**Temats:** Lūgums par mašīnlasāmu atbalsta programmu sarakstu (business.gov.lv)

Labdien!

Vietnē saraksts.lv uzturam bezmaksas sadaļu «Granti», kas vienuviet apkopo atvērtos
neatmaksājamā finansējuma konkursus Latvijas iedzīvotājiem un uzņēmējiem — no ES portāla,
CFLA, LAD, VKKF, SIF un citām iestādēm. Katram konkursam rādām tikai faktus (nosaukumu,
termiņu, summu) un saiti uz oriģinālo lapu; pieteikšanās vienmēr notiek jūsu sistēmā.

LIAA atbalsta programmas vēlamies iekļaut, bet šobrīd to nevaram izdarīt korekti:

* programmu lapas `liaa.business.gov.lv/atbalsta-iespejas/…` jūsu `robots.txt` atļauj;
* taču to **sarakstu** var iegūt tikai caur `sitemap.xml` vai `/jsonapi/…`, un abas adreses
  pārvirza uz `office.business.gov.lv`, kura `robots.txt` ir `User-agent: * / Disallow: /`.

Mēs ievērojam `robots.txt`, tāpēc programmas no sadaļas esam izņēmuši.

Lūdzam kādu no šiem risinājumiem:

1. atļaut rāpuļiem `office.business.gov.lv/sitemap.xml` (vai tikai `/atbalsta-iespejas/`), vai
2. publicēt vietnes karti uz `liaa.business.gov.lv`, vai
3. apstiprināt, ka mēs drīkstam lietot publisko JSON:API `node/atbalsta_programma` ar
   ierobežotu biežumu (reizi diennaktī), identificējot sevi kā `SarakstsLV`.

Pieprasījumu apjoms būtu ap 70 lapu diennaktī ar vismaz 2 sekunžu pauzi un nosacīto GET
(ETag / Last-Modified), t.i. nemainītām lapām — tikai galvenes.

Paldies!

---
*Tehniskā piezīme sev:* kad atļauja ir, `gr_lv_liaa()` failā `granti/lib/avoti_lv.php` jānoņem
agrīnā `return []` un LIAA jāatgriež `GR_LV_AVOTI` sarakstā. Ievākšanas kods ir gatavs.
