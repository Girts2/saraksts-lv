const DASHA_ORDER = ["Ketu", "Venera", "Saule", "Meness", "Marss", "Rahu", "Jupiters", "Saturns", "Merkurs"];
const DASHA_YEARS = {
    "Ketu": 7, "Venera": 20, "Saule": 6, "Meness": 10, "Marss": 7, 
    "Rahu": 18, "Jupiters": 16, "Saturns": 19, "Merkurs": 17
};

export const NAKSHATRA_NAMES = [
    "Ashwini", "Bharani", "Krittika", "Rohini", "Mrigashira", "Ardra", "Punarvasu", "Pushya", "Ashlesha",
    "Magha", "Purva Phalguni", "Uttara Phalguni", "Hasta", "Chitra", "Swati", "Vishakha", "Anuradha", "Jyeshtha",
    "Mula", "Purva Ashadha", "Uttara Ashadha", "Shravana", "Dhanishta", "Shatabhisha", "Purva Bhadrapada", "Uttara Bhadrapada", "Revati"
];

export const NATURE_DICT = {
    "Dhruva (Stabils)": { symbol: "🌿🌿🌿", color: "rgba(16, 185, 129, 0.2)", border: "#10b981", desc: "Būvniecība, ilgtermiņa investīcijas, stādīšana." },
    "Chara (Kustīgs)": { symbol: "🚗 / 🌬️", color: "rgba(59, 130, 246, 0.2)", border: "#3b82f6", desc: "Ceļojumi, transports, ātras izmaiņas, sports." },
    "Mridu (Maigs)": { symbol: "🌸 / ✨", color: "rgba(236, 72, 153, 0.2)", border: "#ec4899", desc: "Māksla, randiņi, jaunas drēbes, draudzība." },
    "Kshipra (Ātrs)": { symbol: "🏹 / ⚡", color: "rgba(234, 179, 8, 0.2)", border: "#eab308", desc: "Mācības, īsi braucieni, amatniecība, tirdzniecība." },
    "Ugra (Sūrs/Stingrs)": { symbol: "🔥🔥", color: "rgba(249, 115, 22, 0.2)", border: "#f97316", desc: "Ieroči, nojaukšana, tiesu darbi, disciplīna." },
    "Tikshna (Ass)": { symbol: "🗡️ / ❗", color: "rgba(239, 68, 68, 0.2)", border: "#ef4444", desc: "Konkurence, strīdi, atbrīvošanās no ļaunā." },
    "Mishra (Jaukts)": { symbol: "🎭", color: "rgba(100, 116, 139, 0.2)", border: "#64748b", desc: "Ikdienas rutīna, parasti darbi." }
};

export const NAKSHATRA_EXTENDED = {
    "Ashwini": { nature: "Kshipra (Ātrs)", focus: "Ātra rīcība, sports, ārstniecība, jauna transporta pirkšana." },
    "Bharani": { nature: "Ugra (Sūrs/Stingrs)", focus: "Transformācija, disciplīna, tīrīšana, lieli lēmumi." },
    "Krittika": { nature: "Mishra (Jaukts)", focus: "Gatavošana, administratīvie darbi, krāsu/uguns rituāli." },
    "Rohini": { nature: "Dhruva (Stabils)", focus: "Bizness, investīcijas, radošums, mājas būvniecība." },
    "Mrigashira": { nature: "Mridu (Maigs)", focus: "Randiņi, iepirkšanās, māksla, jaunu draudzību veidošana." },
    "Ardra": { nature: "Tikshna (Ass)", focus: "Vecā nojaukšana, pētījumi, sarežģītu problēmu risināšana." },
    "Punarvasu": { nature: "Chara (Kustīgs)", focus: "Ceļojumi, darba maiņa, jaunu projektu restarts." },
    "Pushya": { nature: "Kshipra (Ātrs)", focus: "Vissvētākā: Veiksme, bizness, izglītība, labdarība." },
    "Ashlesha": { nature: "Tikshna (Ass)", focus: "Konkurence, slepeni darbi, stratēģiskā plānošana." },
    "Magha": { nature: "Ugra (Sūrs/Stingrs)", focus: "Statusa celšana, dzimtas godināšana, darbs ar pagātni." },
    "Purva Phalguni": { nature: "Ugra (Sūrs/Stingrs)", focus: "Atpūta, baudīšana, diplomātija caur autoritāti." },
    "Uttara Phalguni": { nature: "Dhruva (Stabils)", focus: "Līgumu parakstīšana, ilgtermiņa partnerības, ģimene." },
    "Hasta": { nature: "Kshipra (Ātrs)", focus: "Rokdarbi, tirdzniecība, smalkas detaļas, manipulācijas." },
    "Chitra": { nature: "Mridu (Maigs)", focus: "Dizains, interjers, ārējais tēls, estētikas uzlabošana." },
    "Swati": { nature: "Chara (Kustīgs)", focus: "Komunikācija, tirdzniecība, diplomātija, loģistika." },
    "Vishakha": { nature: "Mishra (Jaukts)", focus: "Fokusēšanās uz mērķi, svinības, lieli publiski soļi." },
    "Anuradha": { nature: "Mridu (Maigs)", focus: "Grupu darbs, uzticība, garīgas prakses, pasākumi." },
    "Jyeshtha": { nature: "Tikshna (Ass)", focus: "Vadība, krīžu risināšana, varas nostiprināšana." },
    "Mula": { nature: "Tikshna (Ass)", focus: "Sakņu meklēšana, izpēte, atbrīvošanās no liekā." },
    "Purva Ashadha": { nature: "Ugra (Sūrs/Stingrs)", focus: "Uzvara caur konfliktu, intensīvs darbs, sporta sacīkstes." },
    "Uttara Ashadha": { nature: "Dhruva (Stabils)", focus: "Jaunu organizāciju dibināšana, liela mēroga plāni." },
    "Shravana": { nature: "Chara (Kustīgs)", focus: "Mācīšanās, klausīšanās, pasniegšana, jaunas informācijas ieguve." },
    "Dhanishta": { nature: "Chara (Kustīgs)", focus: "Ritms, mūzika, komandas darbs, publiska atzinība." },
    "Shatabhisha": { nature: "Chara (Kustīgs)", focus: "Netradicionālā medicīna, meditācija, tehnoloģijas." },
    "Purva Bhadrapada": { nature: "Ugra (Sūrs/Stingrs)", focus: "Askēze, disciplīna, grūtu uzdevumu veikšana." },
    "Uttara Bhadrapada": { nature: "Dhruva (Stabils)", focus: "Meditācija, miers, ilgtermiņa uzkrājumu veidošana." },
    "Revati": { nature: "Mridu (Maigs)", focus: "Ceļojumu pabeigšana, dāsnums, garīgais piepildījums." }
};

export const NAKSHATRA_MEANINGS = {
    "Ashwini": "Ātrs sākums, impulss, dziedināšana. Trūkst pacietības pabeigt lietas, bet izcils iniciators.",
    "Bharani": "Galējības un fanātisms. Radīšana un milzīgi panākumi nāk caur smagu iekšēju cīņu un dzemdību mokām.",
    "Krittika": "Tieša, griezīga analīze. Cilvēks-zobens, kurš sadedzina ilūzijas un aizsargā patiesību.",
    "Rohini": "Kaisle pret estētiku. Auglība, magnētisms, spēcīgas materiālā īpašuma alkas.",
    "Mrigashira": "Nepārtraukta pētniecība, svārstīgums, ceļotāja gars, kurš vienmēr meklē 'kaut ko labāku'.",
    "Ardra": "Vētra un iznīcība intelektā. Asaras, masīva transformācija un spēja pārvaldīt haosu.",
    "Punarvasu": "Atgriešanās pie saknēm un gaisma. Spēja bezgalīgi atjaunot izpostītus projektus un dziedināt citus.",
    "Pushya": "Barošana un lēna izaugsme. Visveiksmīgākā zvaigzne biznesam, likumpaklausībai un pārpilnības radīšanai.",
    "Ashlesha": "Magnētiska manipulācija un okultisms. Instinktīva cīņa par izdzīvošanu – var kļūt par indi vai zālēm citiem.",
    "Magha": "Bosa/Valdnieka sindroms. Milzīgs ego, cieņa pret tradīcijām un vajadzība pēc elitāra statusa.",
    "Purva Phalguni": "Bezpūļu veiksme. Izklaide, baudkāre un spēja atslābt, ļaujot lietām notikt pašām no sevis.",
    "Uttara Phalguni": "Sistemātisks darbs un patronāža. Spēcīga atbildības sajūta, lojalitāte sociālajām struktūrām.",
    "Hasta": "Perfekcionisms detaļās. Spēja visus datus (vai klientus) paņemt 'savās rokās' kā meistaram vai... žonglierim.",
    "Chitra": "Arhitekts un mākslinieks. Dizaina un formas ģēnijs, mīl spodrināt ārieni un būt uzmanības centrā.",
    "Swati": "Pilnīga neatkarība (kā vējš). Diplomātija biznesā, bet milzīga un nelokāma stūrgalvība iekšienē.",
    "Vishakha": "Ekstremāla mērķtiecība. Apsēstība ar panākumiem – uzvara prasa skarbākos upurus un ilgu gaidīšanu.",
    "Anuradha": "Tīklojums pāri robežām. Klusie un pacietīgie panākumi caur izkoptu rīcību un komandas sasaisti.",
    "Jyeshtha": "Autoritāšu sadursmes. Vēlme dominēt laukā un uzņemties atbildi kā 'gudrākajam mājā'.",
    "Mula": "Rakšana līdz saknēm. Spēja iznīcināt absolūti visu savā ceļā, lai sāktu pavisam no nulles un atbrīvotos.",
    "Purva Ashadha": "Neuzvarama pārliecība. Nespēj un atsakās pieņemt sakāvi – sasniedz mērķus, piesaistot masu atbalstu.",
    "Uttara Ashadha": "Pastāvīgs līderis. Nepārtraukts, pašaizliedzīgs darbs sabiedrības labā bez gaidām uz ātru slavu.",
    "Shravana": "Zināšanu uztvērējs. Māk uzklausīt pasaules tendences, sargā reputāciju un izplata mācības.",
    "Dhanishta": "Simfonijas diriģents un naudas magnēts. Ritmiskums un spēja pulcēt ap sevi 100% lojālus pūļus.",
    "Shatabhisha": "Ārstu un pētnieku zvaigznājs. Noslēpumainība, inovatīvu tehnoloģiju radīšana un dziedināšana nepopulāros veidos.",
    "Purva Bhadrapada": "Ugunīga transformācija. Ideālistiski un fanātiski uzskati, mīlestība pret pētīšanu sabiedrības 'ēnās'.",
    "Uttara Bhadrapada": "Zemes dziļākā gudrība. Nostāda stikla sienu starp sevi un chaosu, apveltīti ar kosmisku pacietību.",
    "Revati": "Ceļojuma beigas. Bagātības virsotne, bet arī liels sapņotājs, kurš spēj uzbūvēt debesis vai laiski izšķīst ilūzijās."
};

export const PURUSHARTHA_MAP = {
    "Ashwini": "Dharma (Misija/Sistēma)", "Bharani": "Artha (Resursi/Stabilitāte)", "Krittika": "Kama (Bauda/Ambīcija)", "Rohini": "Moksha (Atbrīvošanās/Inovācija)",
    "Mrigashira": "Moksha (Atbrīvošanās/Inovācija)", "Ardra": "Kama (Bauda/Ambīcija)", "Punarvasu": "Artha (Resursi/Stabilitāte)", "Pushya": "Dharma (Misija/Sistēma)", "Ashlesha": "Moksha (Atbrīvošanās/Inovācija)",
    "Magha": "Artha (Resursi/Stabilitāte)", "Purva Phalguni": "Kama (Bauda/Ambīcija)", "Uttara Phalguni": "Moksha (Atbrīvošanās/Inovācija)", "Hasta": "Moksha (Atbrīvošanās/Inovācija)", "Chitra": "Kama (Bauda/Ambīcija)", "Swati": "Artha (Resursi/Stabilitāte)", "Vishakha": "Dharma (Misija/Sistēma)", "Anuradha": "Dharma (Misija/Sistēma)", "Jyeshtha": "Artha (Resursi/Stabilitāte)",
    "Mula": "Kama (Bauda/Ambīcija)", "Purva Ashadha": "Moksha (Atbrīvošanās/Inovācija)", "Uttara Ashadha": "Moksha (Atbrīvošanās/Inovācija)", "Shravana": "Artha (Resursi/Stabilitāte)", "Dhanishta": "Dharma (Misija/Sistēma)", "Shatabhisha": "Dharma (Misija/Sistēma)", "Purva Bhadrapada": "Artha (Resursi/Stabilitāte)", "Uttara Bhadrapada": "Kama (Bauda/Ambīcija)", "Revati": "Moksha (Atbrīvošanās/Inovācija)"
};

export const PLANET_THERAPY = {
    "Ketu": {
        focus: "Garīgā atbrīvošanās un izolācija. Vecās karmas noslēgšana.",
        risks: "Apjukums, bezmērķība, materiālā sabrukuma sajūta, norobežošanās no realitātes.",
        upaya_action: "Ziedot pārtiku klaiņojošiem suņiem. Praktizēt dziļu meditāciju un askēzi. Valkāt kaķaci.",
        dharma: "Iemācīties atlaist to, kas vairs nekalpo, negūstot no tā piesaisti."
    },
    "Venera": {
        focus: "Baudas, resursi, mīlestība un greznība. Izaugsme caur attiecībām.",
        risks: "Pārmērīga baudkāre, finansiāla izšķērdība, atkarību riski un virspusējība.",
        upaya_action: "Piektdienās valkāt sudrabu un baltas/rozā krāsas. Ziedot sieviešu organizācijām. Rūpēties par estētiku.",
        dharma: "Iemācīties sagādāt un baudīt piepildījumu pasaulē bez smagas alkatības, radot harmoniju sev apkārt."
    },
    "Saule": {
        focus: "Publiskais statuss, līderība un ietekme sabiedrībā.",
        risks: "Autoritārs bosa sindroms, arogance, aklums pret padoto un tuvo vajadzībām.",
        upaya_action: "Godināt tēvu un figūras, kas formē personību. Svētdienās nesākt niekus, bet nēsāt rubīnu vai sarkanu.",
        dharma: "Savu autoritāti noturēs tikai tad, ja spēsi kalpot augstākam, cēlākam komandas mērķim, nevis tikai savam ego."
    },
    "Meness": {
        focus: "Emocionālā dziļuma un iekšējā miera harmonizēšana. Rūpes un psiholoģija.",
        risks: "Emocionālas svārstības, pāraprūpe vai sevis pazaudēšana mēģinot 'izglābt' citus, fobijas.",
        upaya_action: "Pavadīt laiku pie ūdeņiem. Pirmdienās cienāt citus ar pārtiku. Palīdzēt mātēm vai emocionāli nestabiliem cilvēkiem.",
        dharma: "Iemācīties būt emocionāli drosmīgam un pieņemošam, stabilizējot un neizbēgot no prāta vētrām."
    },
    "Marss": {
        focus: "Enerģija, caursitība, konflikta pārvarēšana un karjeras 'iekarošana'.",
        risks: "Pārmērīga agresija, izdegšana, bezkompromisu cīņas stāvoklis, kas var sagraut attiecības.",
        upaya_action: "Fiziska disciplīna (sports/austrumu cīņas) Otrdienās. Palīdzēt aizsardzības iestādēm/brīvprātīgo darbs.",
        dharma: "Kontrolēt agresiju un pārvērst to instrumentā, neievainojot tos, kurus jāsargā."
    },
    "Rahu": {
        focus: "Ekstremāla ekspansija, inovatīvas tehnoloģijas un slāpes pēc nezināmā.",
        risks: "Ilūzijas, apsēstība ar panākumiem jebkuriem līdzekļiem, greizu lēmumu pieņemšana miglā.",
        upaya_action: "Uzturēt vidi perfekti tīru, izvairīties no haosa. Labdarībā pievērsties ubagiem sabiedrības ēnās.",
        dharma: "Piedzīvot izaugsmes vai izgudrojumu galējas robežas, lai pēc tam iemācītos atmest materiālās ilūzijas."
    },
    "Jupiters": {
        focus: "Ekspansija caur autoritāti, zināšanām un padomdevēja lomu.",
        risks: "Arogance un nespēja atzīt kļūdas, aklums pret pavalstnieku vai citu viedokļiem, naivs optimisms.",
        upaya_action: "Ceturtdienās nēsāt dzeltenu. Ziedošana skolotājiem vai izglītībai. Pazemības prakses apzināta trenēšana.",
        dharma: "Statusu un zināšanas var noturēt ilgstoši tikai tie, kuri dod padomus, nekļūstot par diktatoriem."
    },
    "Saturns": {
        focus: "Karmas noslēgšana caur rutīnas darbu, ierobežojumiem un stingru disciplīnu.",
        risks: "Dziļa depresija, resursu trūkums un ilgstoša stagnācijas sajūta. Bailes no nākotnes.",
        upaya_action: "Sestdienās pabarot vārnas, suņus vai vecus cilvēkus. Radikāla personīgā režīma ievērošana bez žēlabām.",
        dharma: "Apgūt pacietību un saprast, ka caur smagu darbu bez īsceļiem iegūtais kalpo gadu desmitiem."
    },
    "Merkurs": {
        focus: "Intelekts, tīklojums, komunikācija, tirdzniecība un sistemātiska datu apstrāde.",
        risks: "Hronisks stresa sindroms no pārslodzes, virspusējība pētniecībā vai manipulēšana un krāpšana faktu bāzē.",
        upaya_action: "Trešdienās nēsāt zaļu nokrāsu. Ziedot bērnu iestādēm, stādīt dabu. Ik pa laikam īstenot prāta un digitālo detoksu.",
        dharma: "Sapulcēt informāciju un dalīties ar to vienkārši bez intelektuālas augstprātības un meliem."
    }
};

export const NAKSHATRA_THERAPY = {
    "Ashwini": { shadow: "Bēgšana no vienkāršiem/pelēkiem darbiem un procesu pamešana pusceļā.", upaya_spirit: "Trenēt elpošanas disciplīnu un mazināt impulsivitāti sarunās." },
    "Bharani": { shadow: "Tirānija vai kontroles zudums un histērija, kad viss nenotiek ideāli.", upaya_spirit: "Meditēt uz dabisko pārmaiņu ciklu (neizbēgamo beigu pieņemšana)." },
    "Krittika": { shadow: "Nemitīga citu kritizēšana un verbāla 'sadedzināšana'.", upaya_spirit: "Praktizēt uguns rituālus vai ugunskuru baudīšanu emociju izlādei." },
    "Rohini": { shadow: "Klaustrofobiska mantkārība un milzīga piesaiste ērtai dzīvei.", upaya_spirit: "Mākslinieciskas prakses un došanās atklātā dabas telpā." },
    "Mrigashira": { shadow: "Iekšēja neapmierinātība ar radīto telpu, nepatstāvība, nervozitāte.", upaya_spirit: "Fokusēt apziņu – strādāt ar vienu darbu bez 'multitasking'." },
    "Ardra": { shadow: "Pasaules skatījuma radikalizācija un skepses/iznīcības vairošana.", upaya_spirit: "Savas dusmas pārceļot mērķtiecīgā inženierijas vai asā mentālā darbā (IT)." },
    "Punarvasu": { shadow: "Apmātība ar vecajām un idealizētajām pagātnes dienām; sentimenta lamatas.", upaya_spirit: "Fiziska lieko lietu un drēbju ziedošana/izmešana, atvieglojot māju." },
    "Pushya": { shadow: "Liels kūtrums uzņemties risku stabilas ikdienas rutīnas dēļ.", upaya_spirit: "Barot un palīdzēt citiem ikdienas rituāla ietvaros svētvietas vai darba fonā." },
    "Ashlesha": { shadow: "Zemapziņas smagums, aizdomīgums, tendence manipulatīvi 'smacēt' sev tuvos.", upaya_spirit: "Turēt noslēpumus un pievērsties dziļi pētāmām hermētiskām disciplīnām." },
    "Magha": { shadow: "Megalomānija, pārmērīga lepnība un apvainošanās par statusa kritumu.", upaya_spirit: "Dzimtas darbs: godināt senčus un izstrādāt dziļu pazemību pret saknēm." },
    "Purva Phalguni": { shadow: "Slinkuma atkarības (hedonisms) un apātija tērējot dzīves resursus.", upaya_spirit: "Noliegt stresa kultūru, taču meklēt harmoniju baudā nedarot pāri savam grafikam." },
    "Uttara Phalguni": { shadow: "Beznosacījuma/piespiedu patronāžas un pienākumu uzspiešana apkārtnei.", upaya_spirit: "Apzināti atlaist pienākumus, veicinot patstāvību komandā vai ģimenē." },
    "Hasta": { shadow: "Izteikta tieksme pēc mikromenedžmenta vai shēmošanas 'caur pirkstiem'.", upaya_spirit: "Amatu, žonglēšanas vai smalka rokas darba trenēšana, nervozitātes noņemšanai." },
    "Chitra": { shadow: "Ilūziju būvēšana un pastāvīga vilšanās cilvēku iekšienē.", upaya_spirit: "Kaut kā fiziski, arhitektoniski koša iekārtošana; telpu reāls dizains un kārtošana." },
    "Swati": { shadow: "Sociālā apātija strīdīgās komandās un milzīga izolācijas pretestība.", upaya_spirit: "Ļauties 'vēja plūsmai', praktizēt klusas un apzinātas diplomātijas mēnešus." },
    "Vishakha": { shadow: "Kopienas sašķelšana savu izteikti orientēto mērķu un uzvaras alkā.", upaya_spirit: "Klusībā iemācīties apzināti priecāties par sāncenša reibinošo uzvaru." },
    "Anuradha": { shadow: "Melanholija, nomākstība dēļ nesekošanas savai sociālajai sirds balsij.", upaya_spirit: "Vadonīgi organizēt grupas un iepludināt uzticību tajās." },
    "Jyeshtha": { shadow: "Vecākā/Pieredzējušākā iedomības sindroms, kas liedz klausīties tālāk.", upaya_spirit: "Pilnīgi brīvprātīgi darboties kā mentoram zemāka profila vai jaunu profesiju pārstāvjiem." },
    "Mula": { shadow: "Sevis paša upurēšana līdz pašatteiksmei cerot izbēgt no problēmām.", upaya_spirit: "Apgūt terapiju/botāniku jeb sakņu būšanu un nesagraut pastāvošos stabilos objektus." },
    "Purva Ashadha": { shadow: "Eiforija bez loģiskas argumentācijas un dusmas konfrontējot reālus faktus.", upaya_spirit: "Nenodot spoguļošanu, bet piedalieties lielās kustībās." },
    "Uttara Ashadha": { shadow: "Slogi, vientulības sajūta ilgstoši uzturot komandu vai organizāciju viens pats.", upaya_spirit: "Regulāri klusēšanas retrīti sev un pienākumu mērķtiecīga dalīšana masās." },
    "Shravana": { shadow: "Neizturama klusuma badošanās, tendence apaugt ar bezdarbīgām baumām.", upaya_spirit: "Klausīties ilgos un padziļinātos podkāstos un mantrās koncentrējot fokusu uz mācīšanos." },
    "Dhanishta": { shadow: "Aizmāršīga emocionālā kurlība, kad viss tiek rēķināts biznesa ritmā.", upaya_spirit: "Pilnveidot spēlēšanu uz sišamajiem instrumentiem izbaudot brīvu tempu, nevis datus." },
    "Shatabhisha": { shadow: "Kliniska noslēgtība, pesimisma akas un slimas tehnoloģiju piesasites.", upaya_spirit: "Pētījumi ēnās bez paškaitnieciskas izturēšanās – piemēram medicīnā, astroloģijā, kosmosā." },
    "Purva Bhadrapada": { shadow: "Tumša apmātība un atriebības tieksme par 'ideāla mērķa' sabrukumu.", upaya_spirit: "Radikāli ļaut sev un citiem kļūdīties bez dusmu un agresijas parādīšanas." },
    "Uttara Bhadrapada": { shadow: "Auksts cinisms – dzīves norobežošanās aiz stikla sienas, 'tāpat visi reiz...'", upaya_spirit: "Garīgas sarunas, klusa meditācija stacionāros apstākļos vai svētceļojums." },
    "Revati": { shadow: "Sistemātisks realitātes noliegums uzturot neeksistējošus māla stabus.", upaya_spirit: "Pašaustrības saglabāšana klusumā; lieli labdarības pasākumi cilvēkiem bez mājas, nevis mājai." }
};

export const JUPITER_TRANSIT_MEANING = [
    "Auns: Sociālais lēciens, paškāpšanās un jaunu personīgo robežu paplašināšana.", // 0
    "Vērsis: Finansiālā izaugsme, ieguldījumi un materiālās bāzes nostiprināšana.", // 1
    "Dvīņi: Izaugsme caur komunikāciju un zināšanām. Iespējami jauni kursi vai komandējumi.", // 2
    "Vēzis: Emocionālā paplašināšanās, ģimenes pieaugums vai mājokļa uzlabošana.", // 3
    "Lauva: Radošuma eksplozija, veiksme investīcijās un redzamība sabiedrībā.", // 4
    "Jaunava: Veselības rutīnas sakārtošana un jauni darba modeļi ar paaugstinātu analītiku.", // 5
    "Svari: Partnerības ziedēšana, laulības vai lieli, stratēģiski publiski līgumi.", // 6
    "Skorpions: Slēptas transformācijas, lieli mantojumi (vai investīcijas) un ezotēriskas atklāsmes.", // 7
    "Strēlnieks: Veiksme studijās, augstākajos ideālos, liela mēroga un ārzemju ceļojumos.", // 8
    "Mežāzis: Iespējamas buksēšanas karjerā ar lielu slodzi; jāmācās profesionāla pazemība.", // 9
    "Ūdensvīrs: Sapņu piepildīšanās caur tīklošanu, jauni draugi un strauja izaugsme grupās.", // 10
    "Zivis: Garīga izaugsme, izolācija klusumā, ziedošana un milzīgu karmisko lūzumu atvieglošanās." // 11
];

export const SATURN_TRANSIT_MEANING = [
    "Auns: Identitātes lūzums. Ātri panākumi apstājas, liekot no jauna definēt, 'kas es esmu'.", // 0
    "Vērsis: Resursu audits. Finanšu plūsmas var tikt ierobežotas, lai iemācītu ietaupīt.", // 1
    "Dvīņi: Komunikācijas disciplīna. Koncentrēšanās uz nopietnām studijām, lauzti lieki kontakti.", // 2
    "Vēzis: Emocionālā atbildība. Iespējama vientulības sajūta savās mājās vai ģimenes pienākumu smagums.", // 3
    "Lauva: Radošuma pārbaude. Grūtības gūt atzinību, mīlas saiknēs jābūt ārkārtīgi reāliem.", // 4
    "Jaunava: Darba askēze. Smags darbs ikdienā, biežas rutīnas slimības vai birokrātijas šķēršļi.", // 5
    "Svari: Partnerības struktūra. Attiecības bez ilgtermiņa bāzes sabrūk; izturīgās – laulājas.", // 6
    "Skorpions: Slēptās bailes. Psiholoģisko traumu augšāmcelšanās un cīņa ar varas sadalījumu.", // 7
    "Strēlnieks: Pārliecības krīze. Filozofijas un skolotāju maiņa, grūtības tieslietās vai ārzemēs.", // 8
    "Mežāzis: Lēna un pārliecinoša kāpšana karjerā balstoties spēku izsīkumā, kas vainagojas zeltā.", // 9
    "Ūdensvīrs: Sociālo struktūru restrukturizācija. Paliek tikai paši uzticamākie draugi un reāli mērķi.", // 10
    "Zivis: Iekšējā disciplīna un emocionālo parādu kārtošana. Laiks noslēgt un apbedīt vecos ciklus." // 11
];

export function calculateMuntha(ascSiderealDeg, ageYrs) {
    const ascSign = Math.floor(ascSiderealDeg / 30);
    const munthaSign = (ascSign + Math.floor(ageYrs)) % 12;
    const zodiacSigns = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", 
                    "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];
    const focalTopics = [
        "Personība, fiziskais ķermenis, drosme sevi parādīt", // 1. māja/Auns
        "Resursi, ēdiens, naudas iekrājumi, balss", // 2. māja/Vērsis
        "Komunikācija, īsie ceļojumi, brāļi vai drosmes trūkums", // 3. māja/Dvīņi
        "Sirds miers, māte, mājoklis, emocionālā stabilitāte", // 4. māja/Vēzis
        "Intelekts, bērni, romantika, veiksme investējot", // 5. māja/Lauva
        "Ienaidnieki, slimības, rutīnas darbs un ikdienas parādi", // 6. māja/Jaunava
        "Biznesa līgumi, laulība, dzīves partneris vai tiešie sāncenši", // 7. māja/Svari
        "Slēptā pasaule, krīzes, citu nauda, pēkšņi satricinājumi", // 8. māja/Skorpions
        "Dharma, tālāki ceļojumi, veiksme, skolotāji vai izglītība", // 9. māja/Strēlnieks
        "Publiskā reputācija, karjera, boss jeb tēva figūra", // 10. māja/Mežāzis
        "Ienākumi, lielas grupas, vecāks brālis, ambīciju sasniegšana", // 11. māja/Ūdensvīrs
        "Zaudējumi, garīgā atbrīve, izolācija, gulta un ārzemes" // 12. māja/Zivis
    ];
    
    // Klasiskā Vedic Varshaphala Muntha interpretējas caur to, kādā mājā (zīmē no Ascendanta) tā ienāk,
    // Bet, lai saglabātu vienkāršību, mēs atgriežam fokusu, ko pārstāv šī māja. 
    // Mājas numurs attiecībā pret Ascendant = (munthaSign - ascSign) ...
    let houseIndex = (munthaSign - ascSign + 12) % 12; 
    
    return {
        signName: zodiacSigns[munthaSign],
        houseNo: houseIndex + 1,
        focusTheme: focalTopics[houseIndex]
    };
}

export function getNakshatraData(moonSiderealDeg) {
    // #5 labojums: 360/27 precīzāk par 13.333333 (bez noapaļošanas kļūdas robeżvērtībās)
    const nakIdx = Math.floor(moonSiderealDeg / (360/27));
    const nakName = NAKSHATRA_NAMES[nakIdx];
    
    // Valdnieks
    const lordIdx = nakIdx % 9;
    const lord = DASHA_ORDER[lordIdx];
    
    // Cik iztērēts no Nakšatras
    const elapsedFactor = (moonSiderealDeg % (360/27)) / (360/27);
    
    const ext = NAKSHATRA_EXTENDED[nakName] || { nature: "Mishra (Jaukts)", focus: "Nav informācijas" };
    const ndata = NATURE_DICT[ext.nature] || NATURE_DICT["Mishra (Jaukts)"];
    
    const NAK_ICONS = {
       "Ashwini": "🐎", "Bharani": "🐘", "Krittika": "🔥", "Rohini": "🐂", "Mrigashira": "🦌",
       "Ardra": "💧", "Punarvasu": "🏹", "Pushya": "🐄", "Ashlesha": "🐍", "Magha": "👑",
       "Purva Phalguni": "🛏️", "Uttara Phalguni": "🛋️", "Hasta": "✋", "Chitra": "💎",
       "Swati": "🌬️", "Vishakha": "🌿", "Anuradha": "🌸", "Jyeshtha": "🌂", "Mula": "🌲",
       "Purva Ashadha": "🌊", "Uttara Ashadha": "🐘", "Shravana": "👂", "Dhanishta": "🥁",
       "Shatabhisha": "⭕", "Purva Bhadrapada": "⚔️", "Uttara Bhadrapada": "🐍", "Revati": "🐟"
    };
    
    const therapy = NAKSHATRA_THERAPY[nakName] || { shadow: "Nav informācijas", upaya_spirit: "Saglabāt vienkāršību un mieru." };
    
    return {
        index: nakIdx,
        nakshatra: nakName,
        lord: lord,
        meaning: NAKSHATRA_MEANINGS[nakName] || "Papildus nozīme nav norādīta.",
        purushartha: PURUSHARTHA_MAP[nakName] || "Nezināms",
        elapsed_factor: elapsedFactor,
        natureName: ext.nature,
        natureFocus: ext.focus,
        natureSymbol: ndata.symbol,
        natureColor: ndata.color,
        natureBorder: ndata.border,
        natureDesc: ndata.desc,
        nakIcon: NAK_ICONS[nakName] || "✨",
        therapy: therapy
    };
}

export function calculateTaraBalaStatus(birthNakIdx, todayNakIdx) {
    // (Šodienas - Dzimšanas + 1)
    let distance = (todayNakIdx - birthNakIdx + 27) % 27;
    let calcDist = distance + 1;
    let remainder = calcDist % 9;
    if (remainder === 0) remainder = 9;
    
    let isBad = [1, 3, 5, 7].includes(remainder);
    
    let taraNames = {
        1: "Fokuss (Janma) / Tava Dzimšanas Zvaigzne",
        2: "Labklājība (Sampat) / Izaugsme",
        3: "Šķēršļi (Vipat) / Pēkšņi pavērsieni",
        4: "Miers (Kshema) / Drošība un Veselība",
        5: "Pretestība (Pratyak) / Aizkavēšanās",
        6: "Sniegums (Sadhaka) / Darbs rēķinās",
        7: "Lūzums (Naidhana) / Krīze vai briesmas",
        8: "Harmonija (Mitra) / Sabiedroto spēks",
        9: "Atbalsts (Ati Mitra) / Cietoksnis"
    };
    
    let taraName = taraNames[remainder] || "Nezināms";
    
    if (isBad) {
        return {
            statusFrame: "red",
            icon: "⚠️",
            title: taraName,
            rating: "⭐⭐ (2/5)",
            ratingLabel: "Bīstama / Saspringta diena",
            warning: "Šī diena tev personīgi nes šķēršļus, agresiju vai zaudējuma riskus (Sarkanais režīms). Nogaidi, neuzņemies tādus riskus, par kuriem nāksies maksāt."
        };
    } else {
         return {
            statusFrame: "gold",
            icon: "✅",
            title: taraName,
            rating: "⭐⭐⭐⭐⭐ (5/5)",
            ratingLabel: "Lieliska diena",
            warning: "Tev personīgi šī diena sola peļņu, palīdzību un veiksmi (Zaļais režīms). Dodies uz priekšu un izmanto šo pavējo, lai īstenotu ambīcijas!"
        };
    }
}

// PANCHANG BIZNESA ALGORITMS (Dienas Orākuls)
export function calculatePanchangSummary(sunDeg, moonDeg, dateObj, taraBalaType) {
    let diff = (moonDeg - sunDeg + 360) % 360;
    
    // 1. Tithi (1-30, Mēness fāze)
    let tithiVal = Math.floor(diff / 12) + 1;
    let isRikta = [4, 9, 14, 19, 24, 29].includes(tithiVal);
    let isAmavasya = (tithiVal === 30);
    
    // 2. Karana (1-60, Darbības ieteikums)
    let karanaVal = Math.floor(diff / 6) + 1;
    let isVishti = [8, 15, 22, 29, 36, 43, 50, 57].includes(karanaVal); // Toksiskā Vishti (Bhadra)
    
    // 3. Yoga (1-27, Iekšējā saikne)
    let sum = (moonDeg + sunDeg) % 360;
    // #5 labojums: 360/27 precīzāk par 13.333333
    let yogaIdx = Math.floor(sum / (360/27)) + 1;
    let isBadYoga = [1, 6, 9, 10, 15, 17, 19, 27].includes(yogaIdx); 
    
    // 4. Vara (Nedēļas diena)
    let dayOfWeek = dateObj.getDay(); // 0(Svētd) - 6(Sestd)
    let isMarsSaturn = (dayOfWeek === 2 || dayOfWeek === 6);
    
    let score = 0;
    let recommend = [];
    let avoid = [];
    let bulletPoints = [];
    
    // Bāzes filtri
    if (isRikta || isAmavasya) {
        score -= 2;
        avoid.push("Sākt jaunus projektus vai organizēt atklāšanas");
        avoid.push("Veikt lielas ilgtermiņa investīcijas");
        recommend.push("Dzēst parādus, veikt inventarizāciju, atlaist vai izmest lieko");
        bulletPoints.push("🌑 Tukšais Mēness (Rikta/Amavasya): Projekti, kas sākti šodien, būs 'tukši' un nesīs vilšanos.");
    } else {
        score += 1;
        recommend.push("Slēgt līgumus un lūkoties pēc liela mēroga attīstības");
    }
    
    if (isBadYoga) {
        score -= 1.5;
        avoid.push("Rīkot svarīgas pārrunas (liels šķēršļu risks)");
        bulletPoints.push("⚔️ Spriedzes Yoga: Kosmiskais lūzums – liels iekšējs pretestības spēks un riski saistībā ar avārijām/kavējumiem.");
    } else {
        score += 1;
    }
    
    if (isVishti) {
        score -= 2;
        avoid.push("Diplomātiska komunikācija un naudas pārskaitījumi");
        bulletPoints.push("☠️ Toksiskā (Vishti) Karana: Aktīvs indīgs fons. Ieteicams izmantot agresiju tikai savai pašaizsardzībai.");
    }
    
    if (isMarsSaturn && !isRikta) {
        recommend.push("Smags un monotons rutīnas darbs vai agresīva vienreizēja rīcība (nevis diplomātija)");
    }
    
    if (taraBalaType === "bad" || taraBalaType === "critical") {
        score -= 2;
        bulletPoints.push(`🧬 Jūsu DNS / Tara Bala: Zvaigžņu sabiedrība šodien VĒRŠAS PRET JUMS (${taraBalaType === "critical" ? 'Iznīcināšanas spēks' : 'Smagi šķēršļi'}). Esiet divkārt piesardzīgi!`);
    } else if (taraBalaType === "good") {
        score += 1;
        bulletPoints.push(`🧬 Jūsu DNS / Tara Bala: Kosmiskā vide Jūs pilnībā ATBALSTA.`);
    }
    
    // Fināla rezultāts
    let statusColor = "";
    let summaryText = "";
    
    if (score <= -2) {
        statusColor = "#ef4444"; 
        summaryText = "🔴 KRITISKA DIENA: Kopējais spriedums – spēcīgi negatīvs matemātiskais fons. Absolūti aizliegti jauni vērienīgi sākumi. Ideāli piemērots laiks darbam vienatnē vai 'ugunsgrēku' dzēšanai.";
    } else if (score >= 2) {
        statusColor = "#10b981"; 
        summaryText = "🟢 IZCILA DIENA: Gluda un masīva enerģētika. Lielisks laiks uzņemties riskus un izmantot labvēlīgo 'zaļo gaismu'.";
    } else {
        statusColor = "#fbbf24"; 
        summaryText = "🟡 NEITRĀLA DIENA: Pārejas posms ar jaukta tipa ietekmēm. Piesardzīga plānošana.";
    }
    
    let uniqRec = [...new Set(recommend)].slice(0, 3);
    let uniqAvoid = [...new Set(avoid)].slice(0, 3);
    if(uniqRec.length === 0) uniqRec.push("Darīt parastos un uzticamos sistēmas uzturēšanas ikdienas darbus");
    if(uniqAvoid.length === 0) uniqAvoid.push("Pārmērīga vilcināšanās – rīkojieties droši!");
    
    return {
        score: score,
        color: statusColor,
        summary: summaryText,
        recommend: uniqRec,
        avoid: uniqAvoid,
        bullets: bulletPoints
    };
}

export function calculateVimshottariPeriods(birthDateStrOrMs, nakData) {
    const periods = [];
    let birthTimeMs;
    if (typeof birthDateStrOrMs === 'number') {
        // UNIX milliseconds padots tieši (no calibration_engine vai ar UTC laikspiedolu)
        birthTimeMs = birthDateStrOrMs;
    } else if (birthDateStrOrMs.includes('T')) {
        // Pilns ISO 8601 datums-laiks ("1980-06-02T09:00:00Z") — izmantojam tieši
        birthTimeMs = new Date(birthDateStrOrMs).getTime();
    } else {
        // Tikai datums bez laika ("1980-06-02") — noklusējums 12:00 UTC kā fallback
        birthTimeMs = new Date(birthDateStrOrMs + "T12:00:00Z").getTime();
    }
    
    const startLordIdx = DASHA_ORDER.indexOf(nakData.lord);
    const totalYearsFirstDasha = DASHA_YEARS[nakData.lord];
    const remainingYearsFirstDasha = totalYearsFirstDasha * (1 - nakData.elapsed_factor);
    
    // Teorētiskais pirmās Mahadashas sākums (no kura reāli sākas antardashu skaitīšana)
    let theoStartMs = birthTimeMs - (totalYearsFirstDasha * nakData.elapsed_factor * 365.25 * 86400000);
    
    let currentStartDateMs = birthTimeMs;
    
    for (let i = 0; i < 9; i++) {
        const idx = (startLordIdx + i) % 9;
        const lord = DASHA_ORDER[idx];
        const mahaYears = DASHA_YEARS[lord];
        
        const durationYears = (i === 0) ? remainingYearsFirstDasha : mahaYears;
        const durationMs = durationYears * 365.25 * 86400000;
        const endDateMs = currentStartDateMs + durationMs;
        
        // --- Antardashas aprēķins ---
        let antardashas = [];
        let adStartMs = (i === 0) ? theoStartMs : currentStartDateMs;
        let adLordIdx = DASHA_ORDER.indexOf(lord);
        
        for (let j = 0; j < 9; j++) {
            let subIdx = (adLordIdx + j) % 9;
            let subLord = DASHA_ORDER[subIdx];
            let adYears = (mahaYears * DASHA_YEARS[subLord]) / 120;
            let adDurationMs = adYears * 365.25 * 86400000;
            let adEndMs = adStartMs + adDurationMs;
            
            // Pievieno, ja posms beidzas pēc dzimšanas brīža (vai šobrīd)
            if (adEndMs > birthTimeMs && adStartMs < endDateMs) {
                antardashas.push({
                    lord: subLord,
                    start: new Date(Math.max(birthTimeMs, adStartMs)),
                    end: new Date(Math.min(endDateMs, adEndMs))
                });
            }
            adStartMs = adEndMs;
        }
        
        periods.push({
            lord: lord,
            start: new Date(currentStartDateMs),
            end: new Date(endDateMs),
            antardashas: antardashas
        });
        
        currentStartDateMs = endDateMs;
    }
    
    return periods;
}

export function getCurrentDasha(periods, targetDateStr) {
    const targetDt = new Date(targetDateStr + "T12:00:00Z").getTime();
    for (let p of periods) {
        if (p.start.getTime() <= targetDt && targetDt <= p.end.getTime()) {
            return p;
        }
    }
    return null;
}

export function getSunriseSunset(lat, lon, dateObj) {
    const PI = Math.PI;
    const rad = (deg) => deg * PI / 180;
    const deg = (rad) => rad * 180 / PI;

    const start = new Date(Date.UTC(dateObj.getUTCFullYear(), 0, 0));
    const diff = dateObj.getTime() - start.getTime();
    const oneDay = 1000 * 60 * 60 * 24;
    const dayOfYear = Math.floor(diff / oneDay);

    const gamma = (2 * PI / 365) * (dayOfYear - 1 + (12 - 12) / 24); 
    const eqTime = 229.18 * (0.000075 + 0.001868 * Math.cos(gamma) - 0.032077 * Math.sin(gamma) - 0.014615 * Math.cos(2*gamma) - 0.040849 * Math.sin(2*gamma));
    const decl = 0.006918 - 0.399912 * Math.cos(gamma) + 0.070257 * Math.sin(gamma) - 0.006758 * Math.cos(2*gamma) + 0.000907 * Math.sin(2*gamma) - 0.002697 * Math.cos(3*gamma) + 0.00148 * Math.sin(3*gamma);

    let haArg = (Math.cos(rad(90.833)) / (Math.cos(rad(lat)) * Math.cos(decl))) - Math.tan(rad(lat)) * Math.tan(decl);
    if (haArg < -1) haArg = -1; 
    if (haArg > 1) haArg = 1; 
    const ha = deg(Math.acos(haArg));

    const sunriseUTCMin = 720 - 4*(lon + ha) - eqTime;
    const sunsetUTCMin = 720 - 4*(lon - ha) - eqTime;

    // Absolūtais brīdis = attiecīgās UTC dienas sākums + minūtes kopš UTC pusnakts.
    // Minūtes drīkst būt negatīvas vai > 1440 (vietas saullēkts UTC izteiksmē var
    // iekrist blakus dienā — austrumu/rietumu puslode); aritmētika to apstrādā dabiski.
    // IEPRIEKŠĒJAIS variants (setUTCHours(Math.floor(min/60)) + setUTCMinutes(Math.floor(min%60)))
    // pie negatīvām minūtēm deva TIEŠI par 60 min agrāku laiku, jo floor-dalīšana (uz −∞)
    // un JS % (patur zīmi) nesader: −180.5 min → stundas −4, bet minūtes −0.5 (nevis +59.5).
    // Sekas: saullēkts/saulriets ±1 h visai austrumu puslodei → muhurtu logi un Rahu Kaal
    // Pasaules Radarā tur bija nobīdīti par stundu.
    const dayStartMs = Date.UTC(dateObj.getUTCFullYear(), dateObj.getUTCMonth(), dateObj.getUTCDate());
    return {
        sunrise: new Date(dayStartMs + sunriseUTCMin * 60000),
        sunset: new Date(dayStartMs + sunsetUTCMin * 60000)
    };
}

const MUHURTA_DB = [
    { no: 1, name: "Rudra", title: "Asais grieziens", qual: "Destruktīva", action: "Lielisks laiks revīzijai, nevajadzīgo lietu dzēšanai, asām sarunām.", color: "#ef4444", status_icon: "🔴", status_text: "Pabeidz iesākto!" },
    { no: 2, name: "Ahi", title: "Zemspriegums", qual: "Bīstama", action: "Zema enerģija. Sargā sevi un neko svarīgu nesāc.", color: "#f97316", status_icon: "🟠", status_text: "Gaidīšanas režīms" },
    { no: 3, name: "Mitra", title: "Draudzības tilts", qual: "Labvēlīga", action: "Ideāls laiks sarunām, komandas darbam un kontaktiem.", color: "#10b981", status_icon: "🟢", status_text: "Sāc tagad!" },
    { no: 4, name: "Pitru", title: "Sakņu spēks", qual: "Neitrāla", action: "Rutīnas darbi, atgriešanās pie pamatiem, vēstures kārtošana.", color: "#64748b", status_icon: "⚪", status_text: "Rutīnas darbi" },
    { no: 5, name: "Vasu", title: "Materiālais lādiņš", qual: "Labvēlīga", action: "Viss, kas saistīts ar finansēm, pirkumiem un resursiem.", color: "#10b981", status_icon: "🟢", status_text: "Sāc tagad!" },
    { no: 6, name: "Vara", title: "Veiksmes starts", qual: "Labvēlīga", action: "Lielisks pozitīvs impulss jebkurai rīcībai.", color: "#10b981", status_icon: "🟢", status_text: "Sāc tagad!" },
    { no: 7, name: "Vishwadeva", title: "Kārtības stunda", qual: "Labvēlīga", action: "Administratīvie darbi, plānošana, mērķu strukturēšana.", color: "#10b981", status_icon: "🟢", status_text: "Sāc tagad!" },
    { no: 8, name: "Abhijit", title: "ZELTA LOGS", qual: "ZELTS", action: "Dienas jaudīgākais brīdis. Sāc svarīgāko tagad!", color: "#fbbf24", status_icon: "⭐", status_text: "Pilna gāze!" },
    { no: 9, name: "Vidhi", title: "Ideju manifests", qual: "Labvēlīga", action: "Radošums, dizains, jaunu koncepciju un formātu radīšana.", color: "#10b981", status_icon: "🟢", status_text: "Sāc tagad!" },
    { no: 10, name: "Satamukhi", title: "Komunikācijas vilnis", qual: "Neitrāla", action: "Tirdzniecība, mārketings un publiskas uzstāšanās.", color: "#64748b", status_icon: "⚪", status_text: "Rutīnas darbi" },
    { no: 11, name: "Puruhuta", title: "Augstspriegums", qual: "Destruktīva", action: "Smags, asu darbu posms un intensīvs sprints. Nepieciešams fokuss.", color: "#ef4444", status_icon: "🔴", status_text: "Pabeidz iesākto!" },
    { no: 12, name: "Vahni", title: "Ugunīgā jauda", qual: "Karsta", action: "Ātra rīcība, tehnoloģiju procesi, enerģisks izrāviens.", color: "#f97316", status_icon: "🔥", status_text: "Aktīva rīcība" },
    { no: 13, name: "Naktanchara", title: "Noskaņas maiņa", qual: "Neitrāla", action: "Pārejas laiks, dienas rutīnas noslēgšana.", color: "#64748b", status_icon: "⚪", status_text: "Rutīnas darbi" },
    { no: 14, name: "Varuna", title: "Miera osta", qual: "Labvēlīga", action: "Veselība, atjaunojošas procedūras un iekšējs miers.", color: "#10b981", status_icon: "🟢", status_text: "Sāc tagad!" },
    { no: 15, name: "Aryaman", title: "Stabilitātes enkurs", qual: "Labvēlīga", action: "Ilgtermiņa līgumi un drošas, ilgtspējīgas partnerības.", color: "#10b981", status_icon: "🟢", status_text: "Sāc tagad!" },
    
    // Night
    { no: 16, name: "Bhaga", title: "Baudas mirklis", qual: "Labvēlīga", action: "Atpūta, sabiedrība, patīkama vide un atbrīvošanās.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 17, name: "Girisha", title: "Mājas siltums", qual: "Labvēlīga", action: "Ģimenes sarunas un mājokļa labiekārtošana.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 18, name: "Ajapada", title: "Dienas atbalss", qual: "Neitrāla", action: "Dienas kopsavilkums un iekšēja analīze.", color: "#64748b", status_icon: "⚪", status_text: "Izvērtē" },
    { no: 19, name: "Ahirbudhnya", title: "Dziļā nirtne", qual: "Labvēlīga", action: "Padziļināta izpēte, sarežģītu tēmu izzināšana.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 20, name: "Pusha", title: "Atjaunojošais miegs", qual: "Labvēlīga", action: "Spēku uzkrāšana, higiēna un mentāla elpa.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 21, name: "Ashwini", title: "Atsperiena punkts", qual: "Labvēlīga", action: "Ātra un fokusēta mentālā reģenerācija.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 22, name: "Agni", title: "Iekšējā uguns", qual: "Karsta", action: "Emocionālā transformācija, dziedēšana caur intensitāti.", color: "#f97316", status_icon: "🔥", status_text: "Esi uzmanīgs" },
    { no: 23, name: "Vidhatru", title: "Likteņa kalve", qual: "Labvēlīga", action: "Lēmumi, kas ietekmē nākamo dienu vai pat nākotni.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 24, name: "Kanda", title: "Spēka sakne", qual: "Labvēlīga", action: "Fundamentālu, būtisku atziņu gūšana naktī.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 25, name: "Aditi", title: "Bezgalības telpa", qual: "Labvēlīga", action: "Brīvība no trauksmes un dziļš miers.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 26, name: "Jiva", title: "Dzīvības elpa", qual: "Labvēlīga", action: "Veselības resursu un prāta līdzsvars nakts stundā.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 27, name: "Vishnu", title: "Pasaules balsts", qual: "Labvēlīga", action: "Drošības sajūta, aizsardzība dvēselei.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" },
    { no: 28, name: "Yumigadya", title: "Disciplīnas sargs", qual: "Neitrāla", action: "Klusa sagatavošanās rītdienas fiksētajam režīmam.", color: "#64748b", status_icon: "⚪", status_text: "Prāta sagatavošana" },
    { no: 29, name: "Brahma", title: "Gudrības rītausma", qual: "ZĪDA (Rītausma)", action: "Stratēģija, skaidrākās idejas un mērķu vizualizācija.", color: "#c084fc", status_icon: "🧘", status_text: "Fokusējies!" },
    { no: 30, name: "Samudra", title: "Gatavības cikls", qual: "Labvēlīga", action: "Pēdējais, visaptverošais solis pirms jaunas dienas sākuma rītausmā.", color: "#10b981", status_icon: "🟢", status_text: "Lielisks fons" }
];

const RAHU_KAAL_PARTS = [7, 1, 6, 4, 5, 3, 2]; // Sun to Sat
const HORA_RULERS = ["Saule", "Venera", "Merkurs", "Meness", "Saturns", "Jupiters", "Marss"];
const HORA_START_IDXS = [0, 3, 6, 2, 5, 1, 4];
const HORA_FOCUS = {
    "Saule": "Līderība, statuss, administrācija, priekšniecība, prezentācijas.",
    "Venera": "Māksla, randiņi, attiecības, rotaslietas, dizains.",
    "Merkurs": "Tirdzniecība, e-pasti, grāmatvedība, sarunas, programmēšana.",
    "Meness": "Rūpes, uzturs, mājoklis, emocionālas sarunas, atpūta.",
    "Saturns": "Smags, rutīnas darbs, reorganizācija, izturība.",
    "Jupiters": "Finanses, lielie līgumi, konsultācijas, filozofija, padomi.",
    "Marss": "Konkurence, sports, asumi, 'ugunsgrēku' dzēšana."
};

export function calculateDailyMuhurtas(dateStr, lat, lon) {
    const defaultLat = Number(lat) || 56.9496;
    const defaultLon = Number(lon) || 24.1052;
    const baseDate = new Date(dateStr + "T12:00:00Z");
    
    // We get sunrise/sunset for Today, Tomorrow, and Yesterday
    let yd = new Date(baseDate.getTime() - 86400000);
    let tm = new Date(baseDate.getTime() + 86400000);
    
    let ssToday = getSunriseSunset(defaultLat, defaultLon, baseDate);
    let ssTomo = getSunriseSunset(defaultLat, defaultLon, tm);
    let ssYest = getSunriseSunset(defaultLat, defaultLon, yd);
    
    // Create continuous Muhurta map spanning from Yesterday's sunrise to Tomorrow's sunset
    const allSegments = [];
    const pushMuhurtas = (sunriseDate, sunsetDate, nextSunriseDate) => {
        let srMs = sunriseDate.getTime();
        let ssMs = sunsetDate.getTime();
        let nSrMs = nextSunriseDate.getTime();
        
        let dayDurationMs = ssMs - srMs;
        let nightDurationMs = nSrMs - ssMs;
        let muhDayLength = dayDurationMs / 15;
        let muhNightLength = nightDurationMs / 15;
        
        for(let i=0; i<15; i++) {
            let mData = MUHURTA_DB[i];
            allSegments.push({ ...mData, start: srMs + (i * muhDayLength), end: srMs + ((i+1) * muhDayLength), isDay: true });
        }
        for(let i=0; i<15; i++) {
            let mData = MUHURTA_DB[i+15];
            allSegments.push({ ...mData, start: ssMs + (i * muhNightLength), end: ssMs + ((i+1) * muhNightLength), isDay: false });
        }
    };
    
    pushMuhurtas(ssYest.sunrise, ssYest.sunset, ssToday.sunrise);
    pushMuhurtas(ssToday.sunrise, ssToday.sunset, ssTomo.sunrise);
    
    // Vēdiskā nedēļas diena = VIETAS lokālā kalendārā diena saullēkta brīdī (aptuvenā
    // laika josla no garuma: lon/15 h — kalendārai dienai pietiekami precīzi).
    // IEPRIEKŠ šeit bija sunriseDate.getDay() = nedēļas diena PĀRLŪKA laika joslā:
    // vietām aiz datuma robežas (piem., Tokija, kuras saullēkts UTC izteiksmē vēl ir
    // "vakardienā") sanāca iepriekšējā diena → nepareiza Rahu Kaal astotdaļa un horu
    // sākuma planēta, turklāt rezultāts mainījās līdzi skatītāja laika joslai.
    const localDayOfWeek = (sunriseMs) => new Date(sunriseMs + (defaultLon / 15) * 3600000).getUTCDay();

    // Calculate Rahu Kaal for yesterday, today, and tomorrow
    const pushRahu = (sunriseDate, sunsetDate) => {
        let srMs = sunriseDate.getTime();
        let ssMs = sunsetDate.getTime();
        let dayOfWeek = localDayOfWeek(srMs);
        let partIdx = RAHU_KAAL_PARTS[dayOfWeek];
        let dayDurationMs = ssMs - srMs;
        let pLength = dayDurationMs / 8;
        return { start: srMs + (partIdx * pLength), end: srMs + ((partIdx + 1) * pLength) };
    };
    
    const rahuSegments = [
        pushRahu(ssYest.sunrise, ssYest.sunset),
        pushRahu(ssToday.sunrise, ssToday.sunset),
        pushRahu(ssTomo.sunrise, ssTomo.sunset)
    ];
    
    // Calculate Planetu Horas (60 minutes each starting from sunrise)
    const horas = [];
    const pushHoras = (sunriseDate) => {
        let srMs = sunriseDate.getTime();
        let dayOfWeek = localDayOfWeek(srMs); // tā pati vietas-lokālās dienas loģika kā Rahu Kaal
        let startIdx = HORA_START_IDXS[dayOfWeek];
        for (let i=0; i<48; i++) { // Generate 48 horas (48 hours) from sunrise to be safe
            let hPlanet = HORA_RULERS[(startIdx + i) % 7];
            horas.push({
                start: srMs + (i * 3600000), 
                end: srMs + ((i+1) * 3600000), 
                ruler: hPlanet,
                focus: HORA_FOCUS[hPlanet]
            });
        }
    };
    
    pushHoras(ssYest.sunrise);
    pushHoras(ssToday.sunrise);
    
    // Filter to only return data relevant to the next 24-48h window from Now
    return { muhurtas: allSegments, rahu: rahuSegments, horas: horas };
}

export const TITHI_DB = {
    1: { name: "Pratipada", group: "Nanda", char: "Sākums", action: "Fiziskas aktivitātes, svinības, jaunas idejas.", warning: "Nav piemērota ceļojumiem.", element: "Uguns", deity: "Agni (Rīcība/Griba)", diet: "Ķirbis" },
    2: { name: "Dwitiya", group: "Bhadra", char: "Stabilitāte", action: "Investīcijas, pamatu likšana, laulības, mājas darbi.", warning: "Izvairīties no eļļas masāžām.", element: "Zeme", deity: "Brahma (Radīšana)", diet: "Baklažāni" },
    3: { name: "Tritiya", group: "Jaya", char: "Uzvara", action: "Māksla, mūzika, drosmīgi soļi, pirmie randiņi.", warning: "Nav piemērota skūšanai/frizierim.", element: "Gaiss", deity: "Gauri (Prieks/Auglība)", diet: "Sāls (pārmērīgs)" },
    4: { name: "Chaturthi", group: "Rikta", char: "Šķēršļi", action: "Tīrīšana, pretinieku neitralizēšana, vecu lietu izmešana.", warning: "Sarkanais logs: Nesākt neko jaunu.", element: "Ūdens", deity: "Ganesha (Šķēršļi)", diet: "Redīsi" },
    5: { name: "Panchami", group: "Purna", char: "Labklājība", action: "Ārstniecība, mācības, administrācija, bizness.", warning: "Izvairīties no aizdevumiem.", element: "Ēters", deity: "Naga (Gudrība/Intuīcija)", diet: "Zaļie lapu dārzeņi" },
    6: { name: "Shashti", group: "Nanda", char: "Slava", action: "Jaunu apģērbu pirkšana, draudzība, svētki.", warning: "Nav piemērota nekustamajam īpašumam.", element: "Uguns", deity: "Kartikeya (Drosme)", diet: "Rūgtās saknes/Nīms" },
    7: { name: "Saptami", group: "Bhadra", char: "Veselība", action: "Ceļojumi, transporta pirkšana, jauni paziņas.", warning: "Nav piemērota bērēm vai sērām.", element: "Zeme", deity: "Surya (Gaisma/Gars)", diet: "Kokosrieksti" },
    8: { name: "Ashtami", group: "Jaya", char: "Jauda", action: "Intelektuāls darbs, aizstāvēšana, rakstīšana.", warning: "Nav piemērota seksuālām aktivitātēm.", element: "Gaiss", deity: "Shiva (Transformācija)", diet: "Gaļa / Smags ēdiens" },
    9: { name: "Navami", group: "Rikta", char: "Agresija", action: "Konkurence, ieroču/instrumentu apkope, tiesu darbi.", warning: "Sarkanais logs: Augsts strīdu risks.", element: "Ūdens", deity: "Durga (Aizsardzība)", diet: "Pudeles ķirbis (Lauki)" },
    10: { name: "Dashami", group: "Purna", char: "Izaugsme", action: "Tikšanās ar svarīgām personām, reliģiozas prakses.", warning: "Nav piemērota lētiem darbiem.", element: "Ēters", deity: "Yama (Disciplīna)", diet: "Pākšaugi" },
    11: { name: "Ekadashi", group: "Nanda", char: "Attīrīšanās", action: "Atslodze, gavēnis, meditācija, garīgais darbs.", warning: "Zelta logs: Neēst graudaugus un pākšaugus.", element: "Uguns", deity: "Vishvadevas (Tīrība)", diet: "Visi graudaugi un pākšaugi" },
    12: { name: "Dwadashi", group: "Bhadra", char: "Veiksme", action: "Labdarība, dāvināšana, reliģiozi rituāli.", warning: "Nav piemērota jaunas mājas sākšanai.", element: "Zeme", deity: "Vishnu (Uzturēšana)", diet: "Zaļumi un salāti" },
    13: { name: "Trayodashi", group: "Jaya", char: "Bauda", action: "Sensoras baudas, jaunas drēbes, bauda, mīlestība.", warning: "Nav piemērota skumjām.", element: "Gaiss", deity: "Kamadeva (Vēlmes)", diet: "Sīpoli un ķiploki" },
    14: { name: "Chaturdashi", group: "Rikta", char: "Nāve/Beigas", action: "Maģiskas prakses, pabeigšana, galīgais 'nē'.", warning: "Sarkanais logs: Ļoti nestabils prāts.", element: "Ūdens", deity: "Shiva (Noslēgums)", diet: "Rūgtie produkti" },
    15: { name: "Purnima", group: "Purna", char: "Kulminācija", action: "Svētki, kulminācija.", warning: "Neveikt operācijas. Augsts emocionālais vilnis.", element: "Ēters", deity: "Chandra (Emociju pilnība)", diet: "Jebkurš smags, trekns ēdiens" },
    30: { name: "Amavasya", group: "Purna", char: "Klusums", action: "Senču godināšana, attīrīšanās.", warning: "Nekādā gadījumā nesākt jaunus projektus.", element: "Ēters", deity: "Pitri (Senči)", diet: "Jebkādi smagi ēdieni" }
};

export function getTithiData(diffDegrees) {
    // Tithi 1-30
    const rawTithi = Math.floor(diffDegrees / 12) + 1;
    let tithiCycle = rawTithi;
    let paksha = "Shukla Paksha (Enerģija aug)";
    
    if (rawTithi > 15 && rawTithi < 30) {
        tithiCycle = rawTithi - 15;
        paksha = "Krishna Paksha (Enerģija dilst)";
    } else if (rawTithi === 30) {
        tithiCycle = 30; // Amavasya
        paksha = "Krishna Paksha (Jaunmēness/Enerģijas nulles punkts)";
    } else if (rawTithi === 15) {
        paksha = "Shukla Paksha (Pilnmēness/100%)";
    }

    const tData = TITHI_DB[tithiCycle] || TITHI_DB[1];

    let colorCode = "#0ea5e9"; // Default zizli
    if (tData.group === "Nanda" || tData.group === "Bhadra" || tData.group === "Jaya") colorCode = "#10b981"; // Zaļš
    if (tData.group === "Purna") colorCode = "#fbbf24"; // Zelts
    if (tData.group === "Rikta") colorCode = "#ef4444"; // Sarkans
    if (tithiCycle === 30) colorCode = "#64748b"; // Jaunmēness pelēks

    return {
        tithi_no: rawTithi,
        cycle_no: tithiCycle,
        paksha: paksha,
        name: tData.name,
        group: tData.group,
        char: tData.char,
        action: tData.action,
        warning: tData.warning,
        element: tData.element,
        deity: tData.deity,
        diet: tData.diet,
        color: colorCode
    };
}

export function calculateNavamsha(siderealDeg) {
    const TROP_SIGNS = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];
    const signIdx = Math.floor(siderealDeg / 30);
    const degreeInSign = siderealDeg % 30;
    const navamshaIdxInSign = Math.floor(degreeInSign / (3 + 1/3)); 
    
    let startSignIdx = 0;
    if (signIdx % 4 === 0) startSignIdx = 0; // Fire
    else if (signIdx % 4 === 1) startSignIdx = 9; // Earth
    else if (signIdx % 4 === 2) startSignIdx = 6; // Air
    else if (signIdx % 4 === 3) startSignIdx = 3; // Water

    const navamshaSignIdx = (startSignIdx + navamshaIdxInSign) % 12;
    return TROP_SIGNS[navamshaSignIdx];
}

export function calculateVedicAspects(planets) {
    const TROP_SIGNS = ["Auns", "Vērsis", "Dvīņi", "Vēzis", "Lauva", "Jaunava", "Svari", "Skorpions", "Strēlnieks", "Mežāzis", "Ūdensvīrs", "Zivis"];
    let aspects = [];
    const pNames = Object.keys(planets);
    
    for (let p of pNames) {
        if (planets[p] === undefined || p === "Ascendant" || p === "MC") continue;
        let deg = planets[p];
        let signIdx = Math.floor(deg / 30);
        
        let aspectsTo = [7];
        if (p === "Marss") aspectsTo.push(4, 8);
        if (p === "Jupiters" || p === "Rahu" || p === "Ketu") aspectsTo.push(5, 9);
        if (p === "Saturns") aspectsTo.push(3, 10);
        
        for (let a of aspectsTo) {
            let targetSignIdx = (signIdx + a - 1) % 12;
            
            for (let t of pNames) {
                if (t === p || planets[t] === undefined || t === "Ascendant" || t === "MC") continue;
                let tDeg = planets[t];
                let tSignIdx = Math.floor(tDeg / 30);
                if (tSignIdx === targetSignIdx) {
                    aspects.push({ source: p, target: t, aspectHouse: a, sign: TROP_SIGNS[targetSignIdx] });
                }
            }
        }
    }
    return aspects;
}

export function calculateYogas(planets, ascendantDeg) {
    let yogas = [];
    if (ascendantDeg === undefined) return yogas;
    const ascSignIdx = Math.floor(ascendantDeg / 30);
    
    const getHouse = (deg) => {
        let signIdx = Math.floor(deg / 30);
        return ((signIdx - ascSignIdx + 12) % 12) + 1;
    };
    
    const signLords = ["Marss", "Venera", "Merkurs", "Meness", "Saule", "Merkurs", "Venera", "Marss", "Jupiters", "Saturns", "Saturns", "Jupiters"];
    
    let houses = {};
    for (let p in planets) {
        if (planets[p] !== undefined && p !== "Ascendant" && p !== "MC") houses[p] = getHouse(planets[p]);
    }
    
    const isKendra = (h) => [1,4,7,10].includes(h);
    const isTrikona = (h) => [1,5,9].includes(h);
    
    const exaltations = {"Marss": 9, "Merkurs": 5, "Jupiters": 3, "Venera": 11, "Saturns": 6}; 
    const ownSigns1 = {"Marss": 0, "Merkurs": 2, "Jupiters": 8, "Venera": 1, "Saturns": 9};
    const ownSigns2 = {"Marss": 7, "Merkurs": 5, "Jupiters": 11, "Venera": 6, "Saturns": 10};
    
    for (let p of ["Marss", "Merkurs", "Jupiters", "Venera", "Saturns"]) {
        if (planets[p] !== undefined) {
            let sign = Math.floor(planets[p] / 30);
            let h = houses[p];
            if (isKendra(h)) {
                if (sign === exaltations[p] || sign === ownSigns1[p] || sign === ownSigns2[p]) {
                    yogas.push({ name: `Pancha Mahapurusha (${p})`, type: "Izcils Raksturs/Veiksme", desc: `${p} spēcīgs kendrā (leņķī). Sniedz slavu, varenību un ietekmi savā sfērā.` });
                }
            }
        }
    }
    
    let houseLords = {}; 
    for (let i = 1; i <= 12; i++) {
        let sign = (ascSignIdx + i - 1) % 12;
        houseLords[i] = signLords[sign];
    }
    
    let planetsInHouse = {};
    for (let p in houses) {
        let h = houses[p];
        if (!planetsInHouse[h]) planetsInHouse[h] = [];
        planetsInHouse[h].push(p);
    }
    
    let rajaYogaFound = false;
    for (let h in planetsInHouse) {
        let occ = planetsInHouse[h];
        if (occ.length > 1) {
            // #3 labojums: parseInt + isNaN nodrošina, ka Rahu/Ketu (kas nav houseLords vērtībās) nedod NaN
            let hasKendraLord = occ.some(p => { const h = parseInt(Object.keys(houseLords).find(k => houseLords[k] === p)); return !isNaN(h) && isKendra(h); });
            let hasTrikonaLord = occ.some(p => { const h = parseInt(Object.keys(houseLords).find(k => houseLords[k] === p)); return !isNaN(h) && isTrikona(h); });
            if (hasKendraLord && hasTrikonaLord) {
                rajaYogaFound = true;
            }
        }
    }
    if (rajaYogaFound) yogas.push({ name: "Raja Yoga", type: "Statuss/Vara", desc: "Kendra (darbība) un Trikona (veiksme) valdnieku saikne. Liela varbūtība sasniegt augstu sociālo statusu."});
    
    let dhanaYogaFound = false;
    const dhanaHouses = [1, 2, 5, 9, 11];
    for (let h in planetsInHouse) {
        let occ = planetsInHouse[h];
        if (occ.length > 1) {
            // #3 labojums: tā paša NaN aizsardzība Dhana Yoga aprēķinam
            let countDhana = occ.filter(p => { const h = parseInt(Object.keys(houseLords).find(k => houseLords[k] === p)); return !isNaN(h) && dhanaHouses.includes(h); }).length;
            if (countDhana > 1) {
                dhanaYogaFound = true;
            }
        }
    }
    if (dhanaYogaFound) yogas.push({ name: "Dhana Yoga", type: "Bagātība", desc: "Resursu un izaugsmes māju valdnieku saikne. Norāda uz finansiālo veiksmi un spēju uzkrāt resursus."});

    return yogas;
}

export function calculateAshtakavarga(planets, ascendantDeg) {
    if (ascendantDeg === undefined) return new Array(12).fill(28);
    const ascSignIdx = Math.floor(ascendantDeg / 30);
    let sav = new Array(12).fill(28); 
    
    const getHouse = (deg) => {
        let signIdx = Math.floor(deg / 30);
        return ((signIdx - ascSignIdx + 12) % 12);
    };
    
    const benefics = ["Jupiters", "Venera", "Meness", "Merkurs"];
    const malefics = ["Saule", "Marss", "Saturns", "Rahu", "Ketu"];
    
    for (let p in planets) {
        if (planets[p] === undefined || p === "Ascendant" || p === "MC") continue;
        let h = getHouse(planets[p]);
        
        if (benefics.includes(p)) {
            sav[h] += 2;
            sav[(h+4)%12] += 1;
            sav[(h+8)%12] += 1;
        }
        if (malefics.includes(p)) {
            if ([2, 5, 9, 10].includes(h)) sav[h] += 3;
            else sav[h] -= 1;
        }
    }
    
    for (let i=0; i<12; i++) {
        if (sav[i] > 45) sav[i] = 45;
        if (sav[i] < 15) sav[i] = 15;
    }
    
    return sav; 
}

export function calculateNityaYogaAndKarana(sunDeg, moonDeg) {
    let diff = (moonDeg - sunDeg + 360) % 360;
    let sum = (moonDeg + sunDeg) % 360;
    
    const NITYA_YOGAS = [
        "Vishkumbha", "Priti", "Ayushman", "Saubhagya", "Shobhana", "Atiganda", "Sukarma", "Dhriti", "Shula",
        "Ganda", "Vriddhi", "Dhruva", "Vyaghata", "Harshana", "Vajra", "Siddhi", "Vyatipata", "Variyan",
        "Parigha", "Shiva", "Siddha", "Sadhya", "Shubha", "Shukla", "Brahma", "Indra", "Vaidhriti"
    ];
    // #5 labojums: 360/27 precīzāk par 13.333333
    let yogaIdx = Math.floor(sum / (360/27));
    
    const KARANAS = [
        "Bava", "Balava", "Kaulava", "Taitila", "Gara", "Vanija", "Vishti", 
        "Shakuni", "Chatushpada", "Naga", "Kintughna"
    ];
    
    let karanaRaw = Math.floor(diff / 6) + 1; 
    let karanaName = "";
    
    if (karanaRaw === 1) karanaName = "Kintughna";
    else if (karanaRaw >= 58) {
        if (karanaRaw === 58) karanaName = "Shakuni";
        else if (karanaRaw === 59) karanaName = "Chatushpada";
        else karanaName = "Naga";
    } else {
        let cyclicIdx = (karanaRaw - 2) % 7;
        karanaName = KARANAS[cyclicIdx];
    }
    
    return {
        yoga: NITYA_YOGAS[yogaIdx],
        karana: karanaName
    };
}
