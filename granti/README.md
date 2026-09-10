# Granti — moduļa uzbūve un palaišana

Publiskā lapa ir `granti.php` docroot saknē; adreses `/granti/` un `/granti/{slug}`.
Šī mape ir viss pārējais.

    granti/bin/   CLI ieejas punkti (būve, tulkošana, cron)
    granti/lib/   bibliotēkas, ko lieto gan lapa, gan būve
    granti/db/    SQLite faili — izvietošana tos NESŪTA, serveris būvē pats
    granti/data/  slēdzenes, žurnāls, stāvokļa JSON, karodziņi, lejupielāžu kešs

## Kas notiek katru dienu

1. `granti/bin/build.php` — lejupielādē ES bulk failu (~124 MB; atkārtoti velk tikai tad,
   ja kešotais vecāks par 20 h), paņem topicDetails budžetiem, pievieno SIF konkursus un
   atomāri pārceļ jauno datubāzi pāri vecajai. Tur savu slēdzeni; gaita — `data/build_state.json`.
2. `granti/bin/tulko.php` — jaunos tekstus iztulko latviski caur **Gemini Batch API**
   (puse cenas). Tulkojumu kešs ir ATSEVIŠĶS fails `db/tulkojumi.sqlite`, jo būve savu
   datubāzi pārraksta pilnībā. Dienas griesti 2,50 € ir kodā (`lib/tulkojumi.php`).

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
4. **Pirmā būve** ar roku, atdalīti: `nohup php granti/bin/build.php > /tmp/granti-first.out 2>&1 &`
   un `tail -f granti/data/build.log`. Gaidāmā beigu rinda: `Gatavs: … TLS pārrāvumi ar pilnu
   ķermeni: N, atkāpes uz sistēmas curl: 0`, `build_state.json` → `gatavs/ok`. Bez sēklas
   ilgums ~8–10 min (mērīts 456 s).
5. **Lapas pārbaude**: `/granti/`, viens konkurss no `sitemap-granti.xml`, `?secibaa=nosaukums`,
   `?val=en` → visi 200; `/granti/nav-tada` → 404. 503 šeit nozīmē vai nu "nav uzbūvēts", vai
   veca SQLite tīmekļa PHP (lapa lieto `json_each`) — skat. `log/<datums>.log`.
6. **Tulkošana**: `php granti/bin/tulko.php --statuss` (rāda rindu un aplēsi, nemaksā), tad
   `php granti/bin/tulko.php`. Ar sēklu rinda ir 0–20 tekstu; bez sēklas — 11 Batch darbi,
   ko savāc `--savac` pēc 30–60 min (vai `granti.savac` grafikā).
7. **Grafiks panelī** `/admin.php?k=<token>`: `granti.diena` 01:20 un `granti.savac` 13:00.
   Pārbaude: `admin_state/schedule.json` satur abus, `dispatch_last.txt` ir svaigāks par 5 min.
8. **Nākamajā rītā**: `admin_state/granti.build.log` un `granti.tulko.log` beidzas ar `rc=0`;
   `sitemap.xml` satur `sitemap-granti.xml` (to ieliek nakts kopējā būve 02:00).

Izejas kodi būvei: 0 labi · 1 datu/IO kļūme (dzīvā DB paliek) · 3 slēdzene aizņemta ·
4 STOP · 5 publicēts, bet ES fails > 72 h vecs un neatjaunojas (ķēde apstājas, panelī kļūda).

## Noderīgās komandas

    php granti/bin/build.php --skip-details      # ātrā pārbūve bez budžetu vilkšanas
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
