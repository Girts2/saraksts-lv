// ════════════════════════════════════════════════════════════════════════════
// ASTROLOĢISKAIS LOKATORS (cilne "Atvaļinājums" — pārcelts no "Karte"/t6 2026-07-05)
// Personalizēta pasaules karte — parāda, cik "rezonē" katra Zemes vieta ar
// lietotāja horoskopu. Divi astroloģiski slāņi apvienoti vienā nepārtrauktā
// laukā (nevis diskrētas astrokartogrāfijas līnijas — vizuāli lasāmāk kā
// krāsu karte, loģika tā pati: tuvums leņķim):
//   1) STATISKAIS (relocēta dzimšanas karte): cik tuvu natālās planētas krīt
//      ASC/MC/DSC/IC, ja lietotājs "dzimtu" tur — laika-neatkarīgs.
//   2) DINAMISKAIS (tranzīts): cik tuvu PAŠREIZĒJĀS/vidējotās planētas ir
//      TIEM PAŠIEM relocētajiem leņķiem izvēlētajā brīdī/periodā.
//
// Kalibrācija (2026-07-02 audits, tools/locator_audit.py):
//   • scoreCell CENTRĒTS — atņem gaidāmo bāzi (svaru asimetrija +21/−35 citādi
//     nobīda visu skalu ≈ −7 punkti); 0 = "kā vidēji jebkurā vietā/brīdī".
//   • Perioda paraugi STRATIFICĒTI pa siderālo fāzi (citādi 30 d solis ≈ viena
//     diennakts fāze → "vidējais" bija viena patvaļīga brīža karte).
//   • Perioda režīmos dinamiskais slānis = 50% LUNĀCIJAS (jaunā Mēness) karte +
//     50% perioda vidējais — stabila ģeogrāfiskā struktūra mundānās prakses garā.
//   • Karte NAV notikumu riska prognoze: katastrofu vietas/brīži empīriski
//     neatšķiras no nejaušības fona (8 notikumu tests ≈ chance-level).
//
// Veiktspēja: planētu garumi NEMAINĀS pēc lokācijas (tikai pēc laika), tāpēc
// tie tiek rēķināti VIENU reizi (pilna efemerīda), un katrai režģa šūnai tiek
// izsaukts TIKAI lētais swisseph.calculateHouses(jd,lat,lon,hsys) — NEVIS
// pilna getAstronomicalData ar visām 11 planētām katrai šūnai (skat.
// core_astro.js). Nedēļas/2-nedēļu vidējotājam paraugu skaits ir FIKSĒTS
// (nevis lineāri aug ar dienu skaitu), lai aprēķins paliktu ātrs neatkarīgi
// no perioda garuma; platāks orbis (ORB_DYNAMIC_AVG) daļēji kompensē retāko
// laika paraugošanu.
// ════════════════════════════════════════════════════════════════════════════
import { getJulianDay, getAstronomicalData } from '../core_astro.js?v=9';
import { aspectFalloff } from '../logic/aspect_utils.js?v=1';

// Labvēlīgu/nelabvēlīgu planētu svari — saskaņoti ar electional.js
// scoreHourBase() vispārējo benefic/malefic konvenciju (Jupiters/Venera
// pozitīvi, Saturns/Marss/mezgli negatīvi). Ārplanētas pievienotas ar mērenu
// svaru — to "intensitāte" ceļojuma kontekstā drīzāk prasa apzinātību, ne
// draudus, tāpēc svars ir neliels, ne dramatisks.
const PLANET_WEIGHT = {
    "Jupiters": 7, "Venera": 6, "Saule": 3, "Merkurs": 3, "Meness": 2,
    "Urans": -3, "Neptuns": -2, "Plutons": -3,
    "Saturns": -8, "Marss": -9, "Rahu": -5, "Ketu": -5
};
const NATAL_KEYS = Object.keys(PLANET_WEIGHT);

// Režģis: platuma/garuma solis — detalizācijas/veiktspējas kompromiss.
const LAT_MIN = -64, LAT_MAX = 80, LAT_STEP = 8;
const LON_MIN = -180, LON_MAX = 180, LON_STEP = 10;

const ORB_STATIC = 9;        // orbis relocētās dzimšanas kartes slānim
const ORB_DYNAMIC_NOW = 9;   // orbis reāllaika tranzītam
const ORB_DYNAMIC_AVG = 14;  // platāks orbis vidējotajam režīmam (kompensē retāku laika paraugošanu)
const NORMALIZE_MAX_RAW = 22; // "izejas" summa, kas tiek kartēta uz ±100 (kalibrēts empīriski)
const YEAR_SLIDER_DAYS = 1826; // ±5 gadi ≈ 1826 dienas (slīdņa diapazons "Sākot no" datumam)
// Atvaļinājuma dienu limits = astronomijas dzinēja TEORĒTISKĀS robežas, ne patvaļīgs
// skaitlis: swisseph lieto Moshier efemerīdu (karogs 4), kuras derīguma diapazons ir
// 3000 p.m.ē. – 3000 m.ē. Perioda BEIGAS nedrīkst pārsniegt augšējo robežu, tāpēc
// maksimālais dienu skaits ir atkarīgs no izvēlētā sākuma datuma (maxCustomDays()).
const EPHEM_MAX_JD = 2816787.5; // JD 3000-01-01 (Gregora kalendārs) — Moshier augšējā robeža
const MAX_SAMPLES = 10;        // maks. laika paraugu skaits jebkuram periodam (veiktspējai)
const UNKNOWN_TIME_SAMPLES = 12; // nezināma laika 24h paraugi statiskajam slānim (ik 2h)
const SID_DEG_PER_DAY = 360.98564736629; // siderālā rotācija °/dienā (tas pats koef. kā GMST formulā)
const SYNODIC_DEG_PER_DAY = 12.1907;     // Mēness-Saules elongācijas vidējais pieaugums °/dienā

// "Sākot no" datuma robežas: Moshier efemerīda derīga 3000 p.m.ē. – 3000 m.ē. custom
// režīms jau klampē dienu skaitu (maxCustomDays), BET 'now'/'week'/'2weeks' NElieto to,
// tāpēc pats sākuma datums jāierobežo pie ievada — citādi swisseph ārpus diapazona met
// izņēmumu → planētas paliek undefined → karte kļūst visur neitrāla (klusa degradācija).
// Augšējo robežu atstāj ar rezervi (2 ned. = +14 d), apakšējo — droši sen.
const START_DATE_MIN = '0100-01-01';
const START_DATE_MAX = '2999-12-01';
function clampDateStr(ds) {
    if (!ds) return ds;
    return ds < START_DATE_MIN ? START_DATE_MIN : (ds > START_DATE_MAX ? START_DATE_MAX : ds);
}

// Viens kopīgs verdikta slieksnis (score −100..100) — lieto GAN kartes hover-verdikts
// (verdictFor), GAN valstu badge krāsa (bcls), lai teksts un krāsa vienmēr sakristu.
// Saskaņots ar sky_chart Klasiskā Riteņa joslu (Harmonija/Neitrāls/Spriedze).
const VERDICT_THRESHOLD = 15;

// Rūtiņu izšķirtspējas (km). 'global' = fiksētais pasaules režģis (8°×10° ≈ 900 km).
// Smalkās izšķirtspējas režģi būvē TIKAI redzamajam kartes skatam — viss globuss
// 10×10 km būtu ~5,1 miljons šūnu (nerenderējams). Ja skats par lielu izvēlētajai
// izšķirtspējai, rāda tikai skata centrālo daļu (MAX_FINE_CELLS griesti) + norādi pietuvināt.
const RESOLUTIONS = { r500: 500, r100: 100, r10: 10 };
const MAX_FINE_CELLS = 2400;

// Galamērķu centroīdi TOP sarakstam (tūrisma reģiona punkts, ne ģeometriskais centrs).
// LIELĀS valstis (izmērs > ~1500 km vai attālas salas) sadalītas reģionos: valsts +
// štats/reģions/apgabals — jo astroloģiskais lauks tik lielā attālumā reāli atšķiras.
// cont: na=Ziemeļamerika, sa=Dienvidamerika, eu=Eiropa, af=Āfrika, as=Āzija, oc=Okeānija.
const COUNTRIES = [
    // ── Eiropa ──
    { n: 'Spānija (kontinentālā)', f: '🇪🇸', cont: 'eu', lat: 40.3, lon: -3.7 },
    { n: 'Spānija (Kanāriju salas)', f: '🇪🇸', cont: 'eu', lat: 28.3, lon: -16.5 },
    { n: 'Spānija (Maljorka)', f: '🇪🇸', cont: 'eu', lat: 39.6, lon: 3.0 },
    { n: 'Portugāle (Lisabona)', f: '🇵🇹', cont: 'eu', lat: 38.7, lon: -9.1 },
    { n: 'Portugāle (Madeira)', f: '🇵🇹', cont: 'eu', lat: 32.7, lon: -17.0 },
    { n: 'Francija (Parīze)', f: '🇫🇷', cont: 'eu', lat: 48.8, lon: 2.3 },
    { n: 'Francija (Azūra krasts)', f: '🇫🇷', cont: 'eu', lat: 43.7, lon: 7.2 },
    { n: 'Itālija (Toskāna)', f: '🇮🇹', cont: 'eu', lat: 43.4, lon: 11.0 },
    { n: 'Itālija (Sicīlija)', f: '🇮🇹', cont: 'eu', lat: 37.5, lon: 14.2 },
    { n: 'Grieķija (kontinentālā)', f: '🇬🇷', cont: 'eu', lat: 39.0, lon: 22.0 },
    { n: 'Grieķija (Krēta)', f: '🇬🇷', cont: 'eu', lat: 35.2, lon: 24.9 },
    { n: 'Horvātija', f: '🇭🇷', cont: 'eu', lat: 45.2, lon: 15.5 },
    { n: 'Melnkalne', f: '🇲🇪', cont: 'eu', lat: 42.7, lon: 19.3 },
    { n: 'Albānija', f: '🇦🇱', cont: 'eu', lat: 41.0, lon: 20.0 },
    { n: 'Kipra', f: '🇨🇾', cont: 'eu', lat: 35.0, lon: 33.0 },
    { n: 'Malta', f: '🇲🇹', cont: 'eu', lat: 35.9, lon: 14.4 },
    { n: 'Austrija', f: '🇦🇹', cont: 'eu', lat: 47.5, lon: 14.5 },
    { n: 'Šveice', f: '🇨🇭', cont: 'eu', lat: 46.8, lon: 8.2 },
    { n: 'Vācija', f: '🇩🇪', cont: 'eu', lat: 51.1, lon: 10.4 },
    { n: 'Polija', f: '🇵🇱', cont: 'eu', lat: 52.0, lon: 19.4 },
    { n: 'Čehija', f: '🇨🇿', cont: 'eu', lat: 49.8, lon: 15.5 },
    { n: 'Ungārija', f: '🇭🇺', cont: 'eu', lat: 47.2, lon: 19.5 },
    { n: 'Bulgārija', f: '🇧🇬', cont: 'eu', lat: 42.7, lon: 25.5 },
    { n: 'Rumānija', f: '🇷🇴', cont: 'eu', lat: 45.9, lon: 25.0 },
    { n: 'Apvienotā Karaliste (Anglija)', f: '🇬🇧', cont: 'eu', lat: 52.5, lon: -1.5 },
    { n: 'Apvienotā Karaliste (Skotija)', f: '🇬🇧', cont: 'eu', lat: 56.8, lon: -4.2 },
    { n: 'Īrija', f: '🇮🇪', cont: 'eu', lat: 53.4, lon: -8.0 },
    { n: 'Islande', f: '🇮🇸', cont: 'eu', lat: 64.9, lon: -18.6 },
    { n: 'Norvēģija (fjordi)', f: '🇳🇴', cont: 'eu', lat: 60.4, lon: 5.3 },
    { n: 'Norvēģija (Tromsē)', f: '🇳🇴', cont: 'eu', lat: 69.6, lon: 18.9 },
    { n: 'Zviedrija', f: '🇸🇪', cont: 'eu', lat: 62.0, lon: 15.0 },
    { n: 'Somija', f: '🇫🇮', cont: 'eu', lat: 64.0, lon: 26.0 },
    { n: 'Dānija', f: '🇩🇰', cont: 'eu', lat: 56.0, lon: 10.0 },
    { n: 'Nīderlande', f: '🇳🇱', cont: 'eu', lat: 52.2, lon: 5.3 },
    { n: 'Latvija', f: '🇱🇻', cont: 'eu', lat: 56.9, lon: 24.6 },
    { n: 'Lietuva', f: '🇱🇹', cont: 'eu', lat: 55.3, lon: 23.9 },
    { n: 'Igaunija', f: '🇪🇪', cont: 'eu', lat: 58.7, lon: 25.0 },
    { n: 'Krievija (Maskava)', f: '🇷🇺', cont: 'eu', lat: 55.75, lon: 37.6 },
    { n: 'Krievija (Pēterburga)', f: '🇷🇺', cont: 'eu', lat: 59.9, lon: 30.3 },
    { n: 'Krievija (Kaļiņingrada)', f: '🇷🇺', cont: 'eu', lat: 54.7, lon: 20.5 },
    { n: 'Krievija (Soči)', f: '🇷🇺', cont: 'eu', lat: 43.6, lon: 39.7 },
    { n: 'Baltkrievija', f: '🇧🇾', cont: 'eu', lat: 53.9, lon: 27.6 },
    { n: 'Ukraina (Kijiva)', f: '🇺🇦', cont: 'eu', lat: 50.45, lon: 30.5 },
    { n: 'Ukraina (Odesa)', f: '🇺🇦', cont: 'eu', lat: 46.5, lon: 30.7 },
    { n: 'Moldova', f: '🇲🇩', cont: 'eu', lat: 47.0, lon: 28.9 },
    { n: 'Serbija', f: '🇷🇸', cont: 'eu', lat: 44.0, lon: 21.0 },
    { n: 'Bosnija un Hercegovina', f: '🇧🇦', cont: 'eu', lat: 43.9, lon: 18.4 },
    { n: 'Ziemeļmaķedonija', f: '🇲🇰', cont: 'eu', lat: 41.6, lon: 21.7 },
    { n: 'Kosova', f: '🇽🇰', cont: 'eu', lat: 42.6, lon: 21.0 },
    { n: 'Slovākija', f: '🇸🇰', cont: 'eu', lat: 48.7, lon: 19.5 },
    { n: 'Slovēnija', f: '🇸🇮', cont: 'eu', lat: 46.1, lon: 14.8 },
    { n: 'Beļģija', f: '🇧🇪', cont: 'eu', lat: 50.6, lon: 4.7 },
    { n: 'Luksemburga', f: '🇱🇺', cont: 'eu', lat: 49.6, lon: 6.1 },
    { n: 'Lihtenšteina', f: '🇱🇮', cont: 'eu', lat: 47.1, lon: 9.5 },
    { n: 'Monako', f: '🇲🇨', cont: 'eu', lat: 43.7, lon: 7.4 },
    { n: 'Andora', f: '🇦🇩', cont: 'eu', lat: 42.5, lon: 1.5 },
    { n: 'Sanmarīno', f: '🇸🇲', cont: 'eu', lat: 43.9, lon: 12.5 },
    { n: 'Vatikāns', f: '🇻🇦', cont: 'eu', lat: 41.9, lon: 12.45 },
    // ── Āzija ──
    { n: 'Turcija (Antālija)', f: '🇹🇷', cont: 'as', lat: 36.9, lon: 30.7 },
    { n: 'Turcija (Stambula)', f: '🇹🇷', cont: 'as', lat: 41.0, lon: 29.0 },
    { n: 'Turcija (Kapadokija)', f: '🇹🇷', cont: 'as', lat: 38.6, lon: 34.8 },
    { n: 'Gruzija', f: '🇬🇪', cont: 'as', lat: 42.0, lon: 43.5 },
    { n: 'Armēnija', f: '🇦🇲', cont: 'as', lat: 40.3, lon: 45.0 },
    { n: 'Azerbaidžāna', f: '🇦🇿', cont: 'as', lat: 40.3, lon: 47.7 },
    { n: 'Izraēla', f: '🇮🇱', cont: 'as', lat: 31.4, lon: 35.0 },
    { n: 'Jordānija', f: '🇯🇴', cont: 'as', lat: 31.3, lon: 36.4 },
    { n: 'AAE (Dubaija)', f: '🇦🇪', cont: 'as', lat: 25.2, lon: 55.3 },
    { n: 'Indija (Goa)', f: '🇮🇳', cont: 'as', lat: 15.3, lon: 74.0 },
    { n: 'Indija (Kerala)', f: '🇮🇳', cont: 'as', lat: 9.5, lon: 76.9 },
    { n: 'Indija (Radžastāna)', f: '🇮🇳', cont: 'as', lat: 26.9, lon: 75.8 },
    { n: 'Indija (Himalaji)', f: '🇮🇳', cont: 'as', lat: 32.2, lon: 77.2 },
    { n: 'Šrilanka', f: '🇱🇰', cont: 'as', lat: 7.9, lon: 80.8 },
    { n: 'Nepāla', f: '🇳🇵', cont: 'as', lat: 28.2, lon: 84.0 },
    { n: 'Maldīvija', f: '🇲🇻', cont: 'as', lat: 3.2, lon: 73.2 },
    { n: 'Taizeme (Bangkoka)', f: '🇹🇭', cont: 'as', lat: 13.7, lon: 100.5 },
    { n: 'Taizeme (Puketa)', f: '🇹🇭', cont: 'as', lat: 7.9, lon: 98.4 },
    { n: 'Taizeme (Čiangmaja)', f: '🇹🇭', cont: 'as', lat: 18.8, lon: 99.0 },
    { n: 'Vjetnama (Hanoja)', f: '🇻🇳', cont: 'as', lat: 21.0, lon: 105.8 },
    { n: 'Vjetnama (dienvidi)', f: '🇻🇳', cont: 'as', lat: 10.8, lon: 106.7 },
    { n: 'Malaizija', f: '🇲🇾', cont: 'as', lat: 4.2, lon: 102.0 },
    { n: 'Singapūra', f: '🇸🇬', cont: 'as', lat: 1.35, lon: 103.8 },
    { n: 'Indonēzija (Bali)', f: '🇮🇩', cont: 'as', lat: -8.4, lon: 115.2 },
    { n: 'Indonēzija (Java)', f: '🇮🇩', cont: 'as', lat: -6.2, lon: 106.8 },
    { n: 'Filipīnas (Manila)', f: '🇵🇭', cont: 'as', lat: 14.6, lon: 121.0 },
    { n: 'Filipīnas (Sebu)', f: '🇵🇭', cont: 'as', lat: 10.3, lon: 123.9 },
    { n: 'Japāna (Tokija)', f: '🇯🇵', cont: 'as', lat: 35.7, lon: 139.7 },
    { n: 'Japāna (Okinava)', f: '🇯🇵', cont: 'as', lat: 26.3, lon: 127.8 },
    { n: 'Dienvidkoreja', f: '🇰🇷', cont: 'as', lat: 36.5, lon: 128.0 },
    { n: 'Krievija (Sibīrija)', f: '🇷🇺', cont: 'as', lat: 55.0, lon: 82.9 },
    { n: 'Krievija (Baikāls)', f: '🇷🇺', cont: 'as', lat: 53.0, lon: 107.0 },
    { n: 'Krievija (Tālie Austrumi)', f: '🇷🇺', cont: 'as', lat: 43.1, lon: 131.9 },
    { n: 'Krievija (Kamčatka)', f: '🇷🇺', cont: 'as', lat: 53.0, lon: 158.7 },
    { n: 'Ķīna (Pekina)', f: '🇨🇳', cont: 'as', lat: 39.9, lon: 116.4 },
    { n: 'Ķīna (Šanhaja)', f: '🇨🇳', cont: 'as', lat: 31.2, lon: 121.5 },
    { n: 'Ķīna (Guandžou)', f: '🇨🇳', cont: 'as', lat: 23.1, lon: 113.3 },
    { n: 'Ķīna (Tibeta)', f: '🇨🇳', cont: 'as', lat: 29.7, lon: 91.1 },
    { n: 'Ķīna (Honkonga)', f: '🇭🇰', cont: 'as', lat: 22.3, lon: 114.2 },
    { n: 'Taivāna', f: '🇹🇼', cont: 'as', lat: 23.7, lon: 121.0 },
    { n: 'Ziemeļkoreja', f: '🇰🇵', cont: 'as', lat: 39.0, lon: 125.8 },
    { n: 'Mongolija', f: '🇲🇳', cont: 'as', lat: 47.9, lon: 106.9 },
    { n: 'Kazahstāna (Almati)', f: '🇰🇿', cont: 'as', lat: 43.2, lon: 76.9 },
    { n: 'Kazahstāna (Astana)', f: '🇰🇿', cont: 'as', lat: 51.2, lon: 71.4 },
    { n: 'Uzbekistāna (Samarkanda)', f: '🇺🇿', cont: 'as', lat: 39.7, lon: 66.9 },
    { n: 'Kirgizstāna', f: '🇰🇬', cont: 'as', lat: 42.9, lon: 74.6 },
    { n: 'Tadžikistāna', f: '🇹🇯', cont: 'as', lat: 38.6, lon: 68.8 },
    { n: 'Turkmenistāna', f: '🇹🇲', cont: 'as', lat: 37.9, lon: 58.4 },
    { n: 'Afganistāna', f: '🇦🇫', cont: 'as', lat: 34.5, lon: 69.2 },
    { n: 'Pakistāna', f: '🇵🇰', cont: 'as', lat: 33.7, lon: 73.1 },
    { n: 'Bangladeša', f: '🇧🇩', cont: 'as', lat: 23.8, lon: 90.4 },
    { n: 'Butāna', f: '🇧🇹', cont: 'as', lat: 27.5, lon: 89.6 },
    { n: 'Mjanma', f: '🇲🇲', cont: 'as', lat: 16.8, lon: 96.2 },
    { n: 'Laosa', f: '🇱🇦', cont: 'as', lat: 18.0, lon: 102.6 },
    { n: 'Kambodža (Ankora)', f: '🇰🇭', cont: 'as', lat: 13.4, lon: 103.9 },
    { n: 'Bruneja', f: '🇧🇳', cont: 'as', lat: 4.9, lon: 114.9 },
    { n: 'Austrumtimora', f: '🇹🇱', cont: 'as', lat: -8.6, lon: 125.6 },
    { n: 'Saūda Arābija', f: '🇸🇦', cont: 'as', lat: 24.7, lon: 46.7 },
    { n: 'Katara', f: '🇶🇦', cont: 'as', lat: 25.3, lon: 51.5 },
    { n: 'Kuveita', f: '🇰🇼', cont: 'as', lat: 29.4, lon: 48.0 },
    { n: 'Bahreina', f: '🇧🇭', cont: 'as', lat: 26.2, lon: 50.6 },
    { n: 'Omāna', f: '🇴🇲', cont: 'as', lat: 23.6, lon: 58.5 },
    { n: 'Jemena', f: '🇾🇪', cont: 'as', lat: 15.4, lon: 44.2 },
    { n: 'Irāka', f: '🇮🇶', cont: 'as', lat: 33.3, lon: 44.4 },
    { n: 'Irāna', f: '🇮🇷', cont: 'as', lat: 35.7, lon: 51.4 },
    { n: 'Sīrija', f: '🇸🇾', cont: 'as', lat: 33.5, lon: 36.3 },
    { n: 'Libāna', f: '🇱🇧', cont: 'as', lat: 33.9, lon: 35.5 },
    // ── Āfrika ──
    { n: 'Ēģipte (Hurgada)', f: '🇪🇬', cont: 'af', lat: 27.2, lon: 33.8 },
    { n: 'Ēģipte (Kaira)', f: '🇪🇬', cont: 'af', lat: 30.0, lon: 31.2 },
    { n: 'Maroka (Marakeša)', f: '🇲🇦', cont: 'af', lat: 31.6, lon: -8.0 },
    { n: 'Tunisija', f: '🇹🇳', cont: 'af', lat: 34.0, lon: 9.5 },
    { n: 'Kenija (Mombasa)', f: '🇰🇪', cont: 'af', lat: -4.0, lon: 39.7 },
    { n: 'Tanzānija (Zanzibāra)', f: '🇹🇿', cont: 'af', lat: -6.2, lon: 39.2 },
    { n: 'Dienvidāfrika (Keiptauna)', f: '🇿🇦', cont: 'af', lat: -33.9, lon: 18.4 },
    { n: 'Dienvidāfrika (Krīgera parks)', f: '🇿🇦', cont: 'af', lat: -24.0, lon: 31.5 },
    { n: 'Maurīcija', f: '🇲🇺', cont: 'af', lat: -20.3, lon: 57.5 },
    { n: 'Seišelas', f: '🇸🇨', cont: 'af', lat: -4.7, lon: 55.5 },
    { n: 'Kaboverde', f: '🇨🇻', cont: 'af', lat: 16.0, lon: -24.0 },
    { n: 'Alžīrija', f: '🇩🇿', cont: 'af', lat: 36.75, lon: 3.06 },
    { n: 'Lībija', f: '🇱🇾', cont: 'af', lat: 32.9, lon: 13.2 },
    { n: 'Sudāna', f: '🇸🇩', cont: 'af', lat: 15.6, lon: 32.5 },
    { n: 'Dienvidsudāna', f: '🇸🇸', cont: 'af', lat: 4.85, lon: 31.6 },
    { n: 'Etiopija', f: '🇪🇹', cont: 'af', lat: 9.0, lon: 38.7 },
    { n: 'Eritreja', f: '🇪🇷', cont: 'af', lat: 15.3, lon: 38.9 },
    { n: 'Džibutija', f: '🇩🇯', cont: 'af', lat: 11.6, lon: 43.1 },
    { n: 'Somālija', f: '🇸🇴', cont: 'af', lat: 2.05, lon: 45.3 },
    { n: 'Uganda', f: '🇺🇬', cont: 'af', lat: 0.3, lon: 32.6 },
    { n: 'Ruanda', f: '🇷🇼', cont: 'af', lat: -1.95, lon: 30.06 },
    { n: 'Burundi', f: '🇧🇮', cont: 'af', lat: -3.4, lon: 29.4 },
    { n: 'Kongo DR', f: '🇨🇩', cont: 'af', lat: -4.3, lon: 15.3 },
    { n: 'Kongo', f: '🇨🇬', cont: 'af', lat: -4.27, lon: 15.28 },
    { n: 'Gabona', f: '🇬🇦', cont: 'af', lat: 0.4, lon: 9.45 },
    { n: 'Ekvatoriālā Gvineja', f: '🇬🇶', cont: 'af', lat: 3.75, lon: 8.78 },
    { n: 'Kamerūna', f: '🇨🇲', cont: 'af', lat: 3.87, lon: 11.5 },
    { n: 'Nigērija', f: '🇳🇬', cont: 'af', lat: 9.06, lon: 7.5 },
    { n: 'Nigēra', f: '🇳🇪', cont: 'af', lat: 13.5, lon: 2.1 },
    { n: 'Čada', f: '🇹🇩', cont: 'af', lat: 12.1, lon: 15.05 },
    { n: 'Mali', f: '🇲🇱', cont: 'af', lat: 12.65, lon: -8.0 },
    { n: 'Burkinafaso', f: '🇧🇫', cont: 'af', lat: 12.35, lon: -1.5 },
    { n: 'Benina', f: '🇧🇯', cont: 'af', lat: 6.5, lon: 2.6 },
    { n: 'Togo', f: '🇹🇬', cont: 'af', lat: 6.1, lon: 1.2 },
    { n: 'Gana', f: '🇬🇭', cont: 'af', lat: 5.6, lon: -0.2 },
    { n: 'Kotdivuāra', f: '🇨🇮', cont: 'af', lat: 5.35, lon: -4.0 },
    { n: 'Libērija', f: '🇱🇷', cont: 'af', lat: 6.3, lon: -10.8 },
    { n: 'Sjerraleone', f: '🇸🇱', cont: 'af', lat: 8.5, lon: -13.2 },
    { n: 'Gvineja', f: '🇬🇳', cont: 'af', lat: 9.6, lon: -13.6 },
    { n: 'Gvineja-Bisava', f: '🇬🇼', cont: 'af', lat: 11.9, lon: -15.6 },
    { n: 'Senegāla', f: '🇸🇳', cont: 'af', lat: 14.7, lon: -17.4 },
    { n: 'Gambija', f: '🇬🇲', cont: 'af', lat: 13.45, lon: -16.6 },
    { n: 'Mauritānija', f: '🇲🇷', cont: 'af', lat: 18.1, lon: -15.95 },
    { n: 'Centrālāfrikas Republika', f: '🇨🇫', cont: 'af', lat: 4.4, lon: 18.6 },
    { n: 'Zambija', f: '🇿🇲', cont: 'af', lat: -15.4, lon: 28.3 },
    { n: 'Zimbabve (Viktorijas ūdenskritums)', f: '🇿🇼', cont: 'af', lat: -17.9, lon: 25.8 },
    { n: 'Malāvija', f: '🇲🇼', cont: 'af', lat: -13.96, lon: 33.8 },
    { n: 'Mozambika', f: '🇲🇿', cont: 'af', lat: -25.9, lon: 32.6 },
    { n: 'Angola', f: '🇦🇴', cont: 'af', lat: -8.8, lon: 13.2 },
    { n: 'Namībija', f: '🇳🇦', cont: 'af', lat: -22.6, lon: 17.1 },
    { n: 'Botsvāna', f: '🇧🇼', cont: 'af', lat: -24.6, lon: 25.9 },
    { n: 'Lesoto', f: '🇱🇸', cont: 'af', lat: -29.3, lon: 27.5 },
    { n: 'Svatini', f: '🇸🇿', cont: 'af', lat: -26.3, lon: 31.1 },
    { n: 'Madagaskara', f: '🇲🇬', cont: 'af', lat: -18.9, lon: 47.5 },
    { n: 'Komoras', f: '🇰🇲', cont: 'af', lat: -11.7, lon: 43.25 },
    { n: 'Santome un Prinsipi', f: '🇸🇹', cont: 'af', lat: 0.34, lon: 6.7 },
    // ── Ziemeļamerika ──
    { n: 'ASV (Florida)', f: '🇺🇸', cont: 'na', lat: 28.1, lon: -81.6 },
    { n: 'ASV (Kalifornija)', f: '🇺🇸', cont: 'na', lat: 36.8, lon: -119.4 },
    { n: 'ASV (Ņujorka)', f: '🇺🇸', cont: 'na', lat: 40.7, lon: -74.0 },
    { n: 'ASV (Aļaska)', f: '🇺🇸', cont: 'na', lat: 61.2, lon: -149.9 },
    { n: 'Kanāda (Britu Kolumbija)', f: '🇨🇦', cont: 'na', lat: 49.3, lon: -123.1 },
    { n: 'Kanāda (Ontārio)', f: '🇨🇦', cont: 'na', lat: 43.7, lon: -79.4 },
    { n: 'Meksika (Kankuna)', f: '🇲🇽', cont: 'na', lat: 21.2, lon: -86.8 },
    { n: 'Meksika (Loskabosa)', f: '🇲🇽', cont: 'na', lat: 22.9, lon: -109.9 },
    { n: 'Kuba', f: '🇨🇺', cont: 'na', lat: 21.5, lon: -79.5 },
    { n: 'Dominikāna', f: '🇩🇴', cont: 'na', lat: 18.9, lon: -70.5 },
    { n: 'Kostarika', f: '🇨🇷', cont: 'na', lat: 9.7, lon: -84.0 },
    { n: 'Gvatemala', f: '🇬🇹', cont: 'na', lat: 14.6, lon: -90.5 },
    { n: 'Beliza', f: '🇧🇿', cont: 'na', lat: 17.5, lon: -88.2 },
    { n: 'Hondurasa', f: '🇭🇳', cont: 'na', lat: 14.1, lon: -87.2 },
    { n: 'Salvadora', f: '🇸🇻', cont: 'na', lat: 13.7, lon: -89.2 },
    { n: 'Nikaragva', f: '🇳🇮', cont: 'na', lat: 12.1, lon: -86.3 },
    { n: 'Panama', f: '🇵🇦', cont: 'na', lat: 9.0, lon: -79.5 },
    { n: 'Jamaika', f: '🇯🇲', cont: 'na', lat: 18.0, lon: -76.8 },
    { n: 'Haiti', f: '🇭🇹', cont: 'na', lat: 18.5, lon: -72.3 },
    { n: 'Bahamu salas', f: '🇧🇸', cont: 'na', lat: 25.0, lon: -77.4 },
    { n: 'Trinidāda un Tobāgo', f: '🇹🇹', cont: 'na', lat: 10.7, lon: -61.5 },
    { n: 'Barbadosa', f: '🇧🇧', cont: 'na', lat: 13.1, lon: -59.6 },
    { n: 'Antigva un Barbuda', f: '🇦🇬', cont: 'na', lat: 17.1, lon: -61.8 },
    { n: 'Sentlūsija', f: '🇱🇨', cont: 'na', lat: 13.9, lon: -61.0 },
    { n: 'Grenāda', f: '🇬🇩', cont: 'na', lat: 12.1, lon: -61.7 },
    { n: 'Sentvinsenta un Grenadīnas', f: '🇻🇨', cont: 'na', lat: 13.25, lon: -61.2 },
    { n: 'Sentkitsa un Nevisa', f: '🇰🇳', cont: 'na', lat: 17.3, lon: -62.7 },
    { n: 'Dominika', f: '🇩🇲', cont: 'na', lat: 15.4, lon: -61.35 },
    // ── Dienvidamerika ──
    { n: 'Brazīlija (Riodežaneiro)', f: '🇧🇷', cont: 'sa', lat: -22.9, lon: -43.2 },
    { n: 'Brazīlija (Bahija)', f: '🇧🇷', cont: 'sa', lat: -13.0, lon: -38.5 },
    { n: 'Brazīlija (Amazone)', f: '🇧🇷', cont: 'sa', lat: -3.1, lon: -60.0 },
    { n: 'Argentīna (Buenosairesa)', f: '🇦🇷', cont: 'sa', lat: -34.6, lon: -58.4 },
    { n: 'Argentīna (Patagonija)', f: '🇦🇷', cont: 'sa', lat: -41.1, lon: -71.3 },
    { n: 'Čīle (Santjago)', f: '🇨🇱', cont: 'sa', lat: -33.4, lon: -70.7 },
    { n: 'Čīle (Atakama)', f: '🇨🇱', cont: 'sa', lat: -22.9, lon: -68.2 },
    { n: 'Peru (Kusko)', f: '🇵🇪', cont: 'sa', lat: -13.5, lon: -72.0 },
    { n: 'Kolumbija (Kartahena)', f: '🇨🇴', cont: 'sa', lat: 10.4, lon: -75.5 },
    { n: 'Venecuēla', f: '🇻🇪', cont: 'sa', lat: 10.5, lon: -66.9 },
    { n: 'Gajāna', f: '🇬🇾', cont: 'sa', lat: 6.8, lon: -58.2 },
    { n: 'Surinama', f: '🇸🇷', cont: 'sa', lat: 5.85, lon: -55.2 },
    { n: 'Ekvadora (Kito)', f: '🇪🇨', cont: 'sa', lat: -0.2, lon: -78.5 },
    { n: 'Ekvadora (Galapagu salas)', f: '🇪🇨', cont: 'sa', lat: -0.7, lon: -90.3 },
    { n: 'Bolīvija', f: '🇧🇴', cont: 'sa', lat: -16.5, lon: -68.1 },
    { n: 'Paragvaja', f: '🇵🇾', cont: 'sa', lat: -25.3, lon: -57.6 },
    { n: 'Urugvaja', f: '🇺🇾', cont: 'sa', lat: -34.9, lon: -56.2 },
    // ── Okeānija ──
    { n: 'Austrālija (Sidneja)', f: '🇦🇺', cont: 'oc', lat: -33.9, lon: 151.2 },
    { n: 'Austrālija (Kvīnslenda)', f: '🇦🇺', cont: 'oc', lat: -16.9, lon: 145.8 },
    { n: 'Austrālija (Perta)', f: '🇦🇺', cont: 'oc', lat: -31.9, lon: 115.9 },
    { n: 'Jaunzēlande (Ziemeļsala)', f: '🇳🇿', cont: 'oc', lat: -36.8, lon: 174.8 },
    { n: 'Jaunzēlande (Dienvidsala)', f: '🇳🇿', cont: 'oc', lat: -43.5, lon: 170.0 },
    { n: 'Fidži', f: '🇫🇯', cont: 'oc', lat: -17.8, lon: 178.0 },
    { n: 'ASV (Havaju salas)', f: '🇺🇸', cont: 'oc', lat: 20.8, lon: -156.3 },
    { n: 'Papua-Jaungvineja', f: '🇵🇬', cont: 'oc', lat: -9.4, lon: 147.2 },
    { n: 'Zālamana salas', f: '🇸🇧', cont: 'oc', lat: -9.4, lon: 160.0 },
    { n: 'Vanuatu', f: '🇻🇺', cont: 'oc', lat: -17.7, lon: 168.3 },
    { n: 'Samoa', f: '🇼🇸', cont: 'oc', lat: -13.8, lon: -171.8 },
    { n: 'Tonga', f: '🇹🇴', cont: 'oc', lat: -21.1, lon: -175.2 },
    { n: 'Kiribati', f: '🇰🇮', cont: 'oc', lat: 1.3, lon: 173.0 },
    { n: 'Tuvalu', f: '🇹🇻', cont: 'oc', lat: -8.5, lon: 179.2 },
    { n: 'Nauru', f: '🇳🇷', cont: 'oc', lat: -0.5, lon: 166.9 },
    { n: 'Palau', f: '🇵🇼', cont: 'oc', lat: 7.5, lon: 134.6 },
    { n: 'Māršala salas', f: '🇲🇭', cont: 'oc', lat: 7.1, lon: 171.4 },
    { n: 'Mikronēzija', f: '🇫🇲', cont: 'oc', lat: 6.9, lon: 158.2 }
];

// Kontinentu izkārtojums TOP sarakstam: 3 kolonnas — kreisā (Z+D Amerika),
// vidējā (Eiropa+Āfrika), labā (Āzija+Okeānija). Katram kontinentam savs TOP.
const CONTINENT_META = { na: 'Ziemeļamerika', sa: 'Dienvidamerika', eu: 'Eiropa', af: 'Āfrika', as: 'Āzija', oc: 'Okeānija' };
const CONTINENT_COLS = [['na', 'sa'], ['eu', 'af'], ['as', 'oc']];
const TOP_PER_CONTINENT = 5;

const COLOR_STOPS = [
    { t: -100, c: [153, 27, 27] },
    { t: -40,  c: [239, 68, 68] },
    { t: 0,    c: [148, 163, 184] },
    { t: 40,   c: [34, 197, 94] },
    { t: 100,  c: [5, 150, 105] }
];

let _profile = null;
let _map = null;
let _gridCells = null;       // [{latC, lonC, bounds}] — pārbūvē, mainot izšķirtspēju/skatu
let _cellLayers = [];        // Leaflet L.rectangle, tāda pati kārtība kā _gridCells
let _moveT = null;           // debounce taimeris kartes pārbīdei smalkajos režīmos
let _squelchMove = 0;        // līdz šim laikam ignorē moveend (programmatiska setView, ne lietotāja)
let _lastCellResults = null; // [{latC, lonC, total, staticNorm, dynamicNorm, staticFactors, dynamicFactors}]
let _lastRanking = null;    // pēdējais valstu rangs — pārrenderēšanai pie +/− izvēršanas
let _expandedConts = {};    // kuri kontinenti izvērsti pilnā sarakstā
let _computeToken = 0;
// resolution noklusēti 'r10' (lietotāja izvēle 2026-07-05): pasaules skatā rūtiņas
// adaptīvi rupjākas (MAX_FINE_CELLS griesti), pietuvinot sasniedz īstos 10×10 km.
let _state = { mode: 'now', startDate: null, customDays: 7, layer: 'personal', resolution: 'r10' };
let _cal = null; // "Kalendārs" filtra dialoga stāvoklis (null = vēl nav atvērts)

function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
function sleep0() { return new Promise(r => setTimeout(r, 0)); }

// ── Analītiskais ASC/MC (bez WASM) ────────────────────────────────────────────
// KRITISKI VEIKTSPĒJAI: agrāk katrai režģa šūnai tika izsaukts swisseph.calculateHouses
// (WASM, ~3ms), t.i. 648 šūnas × līdz 16 paraugiem ≈ 10 000 izsaukumu = ~17s katram
// pārrēķinam → karte šķita "iesalusi" (lietotājs nesagaidīja jauno rezultātu).
// ASC/MC ir tikai debespuses — tos var iegūt no vienkāršas astronomiskās formulas
// (mean obliquity + GMST pēc Meeus). Validēts pret swisseph: maks. kļūda ASC 0.04°,
// MC 0.005° (2280 testa šūnas, 5 datumi) — nemanāms režģim (šūnas 8–10°, orbs 9–14°).
// Rezultāts: pārrēķins ~17s → <100ms (tīrs JS, WASM tikai planētu garumiem 1×/paraugs).
const D2R = Math.PI / 180, R2D = 180 / Math.PI;
function calcAscMc(jd, latDeg, lonDeg) {
    const T = (jd - 2451545.0) / 36525;
    const eps = (23.4392911 - 0.0130042 * T) * D2R;                       // ekliptikas slīpums
    let gmst = 280.46061837 + 360.98564736629 * (jd - 2451545.0)
             + 0.000387933 * T * T - (T * T * T) / 38710000;              // Griničas sider. laiks (°)
    gmst = ((gmst % 360) + 360) % 360;
    const ramc = ((((gmst + lonDeg) % 360) + 360) % 360) * D2R;          // lokālais sider. laiks = MC rekt. asc.
    const phi = latDeg * D2R;
    let mc = Math.atan2(Math.sin(ramc), Math.cos(ramc) * Math.cos(eps)) * R2D;
    mc = ((mc % 360) + 360) % 360;
    let asc = Math.atan2(Math.cos(ramc), -(Math.sin(ramc) * Math.cos(eps) + Math.tan(phi) * Math.sin(eps))) * R2D;
    asc = ((asc % 360) + 360) % 360;
    // Kvadranta korekcija: ASC vienmēr austrumu puslodē (MC..MC+180 zodiaka secībā).
    if ((((asc - mc) % 360) + 360) % 360 > 180) asc = (asc + 180) % 360;
    return { asc, mc };
}

function circDist(a, b) {
    let d = Math.abs(a - b) % 360;
    return d > 180 ? 360 - d : d;
}

function bestAngleMatch(planetLon, ascDeg, mcDeg, orb) {
    const dscDeg = (ascDeg + 180) % 360;
    const icDeg = (mcDeg + 180) % 360;
    const angles = [["ASC", ascDeg], ["MC", mcDeg], ["DSC", dscDeg], ["IC", icDeg]];
    let best = { weight: 0, angle: null };
    for (const [name, deg] of angles) {
        const w = aspectFalloff(circDist(planetLon, deg), orb);
        if (w > best.weight) best = { weight: w, angle: name };
    }
    return best;
}

// Vienas režģa šūnas score + top faktori vienam planētu-garumu kopumam (natālam vai tranzīta).
// Summa tiek CENTRĒTA: katrai planētai atņem gaidāmo vidējo devumu nejaušā vietā/brīdī
// (varbūtība būt orbī ap kādu no 4 leņķiem × vidējais raised-cosine kritiens =
// (4·2·orb/360)·0.5 = orb/90). Bez centrēšanas svaru asimetrija (+21 pret −35) nobīdīja
// visu skalu par ≈ −6…−10 punktiem, un ~40% pasaules bija "Spriedze" jebkurā nejaušā
// brīdī (verificēts: tools/locator_audit.py, E tests). Pēc centrēšanas 0 = īsta
// neitralitāte ("kā vidēji jebkur"). Faktoru saraksts paliek necentrēts — tas rāda
// konkrēto planētu reālo devumu, ne statistisko bāzi.
function scoreCell(lonsByPlanet, ascDeg, mcDeg, orb) {
    let sum = 0;
    let base = 0;
    const factors = [];
    for (const p of NATAL_KEYS) {
        const lon = lonsByPlanet[p];
        if (lon == null) continue;
        base += PLANET_WEIGHT[p] * (orb / 90);
        const m = bestAngleMatch(lon, ascDeg, mcDeg, orb);
        if (m.weight <= 0.01) continue;
        const contrib = PLANET_WEIGHT[p] * m.weight;
        sum += contrib;
        factors.push({ planet: p, angle: m.angle, contrib });
    }
    factors.sort((a, b) => Math.abs(b.contrib) - Math.abs(a.contrib));
    return { sum: sum - base, factors };
}

function buildGrid() {
    const cells = [];
    for (let lat = LAT_MIN; lat < LAT_MAX; lat += LAT_STEP) {
        for (let lon = LON_MIN; lon < LON_MAX; lon += LON_STEP) {
            cells.push({
                latC: lat + LAT_STEP / 2,
                lonC: lon + LON_STEP / 2,
                bounds: [[lat, lon], [lat + LAT_STEP, lon + LON_STEP]]
            });
        }
    }
    return cells;
}

// Smalkais režģis (500/100/10 km) — pašreizējam kartes skatam. VIENMĒR pārklāj VISU
// redzamo logu (arī pasaules skatā): ja izvēlētais rūtiņas izmērs prasītu vairāk par
// MAX_FINE_CELLS šūnām, rūtiņas proporcionāli PALIELINA (coarsen), nevis apgriež
// pārklājumu — un atgriež effectiveKm, ko UI parāda kā godīgu norādi ("pietuvini,
// lai sasniegtu izvēlēto izmēru"). Pietuvinot skats sarūk → effectiveKm tuvojas km.
function buildFineGrid(km) {
    const b = _map.getBounds();
    const south = clamp(b.getSouth(), -84, 84);
    const north = clamp(b.getNorth(), -84, 84);
    let west = b.getWest(), east = b.getEast();
    if (east - west > 360) { west = -180; east = 180; } // pilns aplis — normalizē

    let latStep = km / 111.32; // 1° platuma ≈ 111,32 km
    const midLat = (south + north) / 2;
    // Garuma grāds sarūk ar cos(lat); min 0.15, lai pie poliem rūtiņas nekļūst absurdi platas.
    let lonStep = km / (111.32 * Math.max(0.15, Math.cos(midLat * D2R)));

    let coarsened = false;
    let effectiveKm = km;
    const need = Math.ceil((north - south) / latStep) * Math.ceil((east - west) / lonStep);
    if (need > MAX_FINE_CELLS) {
        const factor = Math.sqrt(need / MAX_FINE_CELLS);
        latStep *= factor;
        lonStep *= factor;
        effectiveKm = Math.round(km * factor);
        coarsened = true;
    }

    const cells = [];
    for (let lat = south; lat < north; lat += latStep) {
        for (let lon = west; lon < east; lon += lonStep) {
            cells.push({
                latC: lat + latStep / 2,
                lonC: lon + lonStep / 2, // calcAscMc korekti apstrādā lon ārpus ±180 (mod 360)
                bounds: [[lat, lon], [lat + latStep, lon + lonStep]]
            });
        }
    }
    return { cells, coarsened, effectiveKm };
}

// Statiskā (relocētās dzimšanas kartes) slāņa LAIKA PARAUGI.
//   • Zināms laiks → viens paraugs (dzimšanas brīdis) → uzvedība kā agrāk.
//   • Nezināms laiks → UNKNOWN_TIME_SAMPLES paraugi vienmērīgi pa dzimšanas LOKĀLO
//     diennakti (jdNoon ± 12h = lokālā pusnakts–pusnakts, jo nezināma laika jd =
//     lokālais pusdienlaiks). Katram paraugam natālās planētas pārrēķina no jauna
//     (Mēness ~13°/dienā), tāpēc katrs paraugs ir pilnvērtīga hipotētiskā karte.
//     ASC/MC pa 24h apiet pilnu apli → vidējais garumā izlīdzinās (personīgā
//     ģeogrāfija kļūst aptuvena) — godīgs nezināma laika attēlojums.
// Planētu garumi atkarīgi TIKAI no dzimšanas dienas (nemainīga sesijā), tāpēc
// paraugus var droši kešot; getAstronomicalData (WASM) izsauc tikai N reižu.
function buildStaticSamples(jdNoon, isUnknown, natalLons) {
    if (!isUnknown) return [{ jd: jdNoon, lons: natalLons }];
    const out = [];
    for (let s = 0; s < UNKNOWN_TIME_SAMPLES; s++) {
        const jd = jdNoon - 0.5 + (s + 0.5) / UNKNOWN_TIME_SAMPLES;
        const t = getAstronomicalData(jd, 0, 0).tropical;
        const lons = {};
        for (const p of NATAL_KEYS) if (typeof t[p] === 'number') lons[p] = t[p];
        out.push({ jd, lons });
    }
    return out;
}

// Statiskais slānis, vidējots pa `samples` (1 paraugs zināmam laikam, 24h nezināmam).
// Faktorus ņem no vidus (≈pusdienlaika) parauga — ilustratīvai vienas hipotēzes kartei.
function computeStaticLayer(cells, samples) {
    const n = samples.length;
    const mid = Math.floor(n / 2);
    const out = new Array(cells.length);
    for (let i = 0; i < cells.length; i++) {
        const c = cells[i];
        let sum = 0, factors = [];
        for (let s = 0; s < n; s++) {
            const h = calcAscMc(samples[s].jd, c.latC, c.lonC);
            const r = scoreCell(samples[s].lons, h.asc, h.mc, ORB_STATIC);
            sum += r.sum;
            if (s === mid) factors = r.factors;
        }
        out[i] = { sum: sum / n, factors };
    }
    return out;
}

async function computeDynamicLayer(cells, sampleJds, orb, onProgress) {
    const sums = new Array(cells.length).fill(0);
    const factorsBySample0 = new Array(cells.length).fill(null);
    const astroBySample = []; // planētu garumi per paraugs — atkārtoti lieto valstu rangam
    for (let s = 0; s < sampleJds.length; s++) {
        const jd = sampleJds[s];
        // Planētu garumi nav atkarīgi no lat/lon — rēķina VIENU reizi šim paraugam (WASM).
        const astro = getAstronomicalData(jd, 0, 0);
        astroBySample.push(astro.tropical);
        for (let i = 0; i < cells.length; i++) {
            const c = cells[i];
            const h = calcAscMc(jd, c.latC, c.lonC); // analītiski, bez WASM
            const r = scoreCell(astro.tropical, h.asc, h.mc, orb);
            sums[i] += r.sum;
            if (s === 0) factorsBySample0[i] = r.factors; // pirmais paraugs kā ilustratīvie faktori
        }
        // Progress + yield pēc katra parauga (WASM getAstronomicalData ir lēnākā daļa jaunam datumam).
        if (onProgress) { onProgress((s + 1) / sampleJds.length); await sleep0(); }
    }
    return {
        results: cells.map((c, i) => ({ sum: sums[i] / sampleJds.length, factors: factorsBySample0[i] || [] })),
        astroBySample
    };
}

// ── TOP valstu rangs ─────────────────────────────────────────────────────────
// Vērtē katras valsts centroīdu ar TIEM PAŠIEM dzinējiem kā kartes šūnas:
// personīgais = 50% relocētā dzimšanas karte + 50% tranzīts; vispārīgais = tikai
// tranzīts (visiem cilvēkiem vienāds). % skala: 0..100, kur 50% = neitrāli.
function toPct(score) { return Math.round((clamp(score, -100, 100) + 100) / 2); }

function computeCountryRanking(staticSamples, sampleJds, orb, astroBySample, lun) {
    return COUNTRIES.map(c => {
        // Statiskais slānis — tas pats laika paraugu kopums kā kartes šūnām
        // (viens dzimšanas brīdis, vai 24h vidējais nezināmam laikam).
        let stSum = 0;
        for (const smp of staticSamples) {
            const hB = calcAscMc(smp.jd, c.lat, c.lon);
            stSum += scoreCell(smp.lons, hB.asc, hB.mc, ORB_STATIC).sum;
        }
        const st = { sum: stSum / staticSamples.length };
        let dynSum = 0;
        for (let s = 0; s < sampleJds.length; s++) {
            const h = calcAscMc(sampleJds[s], c.lat, c.lon);
            dynSum += scoreCell(astroBySample[s], h.asc, h.mc, orb).sum;
        }
        const staticNorm = normalize(st.sum);
        let dynamicNorm = normalize(dynSum / sampleJds.length);
        if (lun) { // tas pats lunācijas piejaukums kā režģa šūnām — badge sakrīt ar karti
            const h = calcAscMc(lun.jd, c.lat, c.lon);
            dynamicNorm = clamp(0.5 * normalize(scoreCell(lun.lons, h.asc, h.mc, ORB_DYNAMIC_NOW).sum) + 0.5 * dynamicNorm, -100, 100);
        }
        const personal = clamp(0.5 * staticNorm + 0.5 * dynamicNorm, -100, 100);
        return { ...c, personalPct: toPct(personal), generalPct: toPct(dynamicNorm) };
    }).sort((a, b) => {
        // "labam jābūt ABIEM vērtējumiem" → kārto pēc ZEMĀKĀ no abiem, tad pēc summas
        const mA = Math.min(a.personalPct, a.generalPct), mB = Math.min(b.personalPct, b.generalPct);
        if (mB !== mA) return mB - mA;
        return (b.personalPct + b.generalPct) - (a.personalPct + a.generalPct);
    });
}

// Cilvēkam lasāms perioda apraksts TOP panelim — par kādu laiku rangs rēķināts.
function fmtLvDate(ms) {
    const d = new Date(ms);
    return `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()}.`;
}
function periodLabel() {
    const startStr = _state.startDate || new Date().toISOString().split('T')[0];
    const startMs = new Date(startStr + 'T12:00:00').getTime();
    if (_state.mode === 'now') {
        const now = new Date();
        return `${fmtLvDate(startMs)} plkst. ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')} — viens mirklis`;
    }
    let days, label;
    if (_state.mode === 'week')        { days = 7;  label = 'nedēļas vidējais'; }
    else if (_state.mode === '2weeks') { days = 14; label = '2 nedēļu vidējais'; }
    else {
        days = Math.max(1, Math.min(maxCustomDays(), _state.customDays || 7));
        label = `atvaļinājums, ${days.toLocaleString('lv-LV')} d.`;
    }
    if (days <= 1) return `${fmtLvDate(startMs)} — ${label}`;
    return `${fmtLvDate(startMs)} – ${fmtLvDate(startMs + (days - 1) * 86400000)} — ${label}`;
}

function renderCountryList(ranked) {
    const el = document.getElementById('aloc-countries');
    if (!el) return;
    _lastRanking = ranked; // saglabā, lai +/− izvēršana var pārrenderēt bez pārrēķina
    // Badge krāsas slieksnis atvasināts no VERDICT_THRESHOLD (score ±15 ↔ pct 57,5/42,5),
    // lai valsts badge un kartes hover-verdikts vienmēr saskan (agrāk badge bija 60/40).
    const GOOD_PCT = 50 + VERDICT_THRESHOLD / 2, BAD_PCT = 50 - VERDICT_THRESHOLD / 2;
    const bcls = p => p >= GOOD_PCT ? 'good' : (p <= BAD_PCT ? 'bad' : 'mid');
    // ranked jau sakārtots pēc MIN(personīgais, vispārīgais) — kontinentu grupas to manto.
    const groupHtml = (contId) => {
        const all = ranked.filter(c => c.cont === contId);
        if (!all.length) return '';
        const expanded = !!_expandedConts[contId];
        const items = expanded ? all : all.slice(0, TOP_PER_CONTINENT);
        const rows = items.map((c, idx) => `
            <button class="aloc-cty" onclick="window.alocGotoCountry(${c.lat}, ${c.lon})" title="Parādīt šo vietu kartē">
                <span class="aloc-cty-rank">${idx + 1}.</span>
                <span class="aloc-cty-name">${c.f} ${c.n}</span>
                <span class="aloc-cty-badges">
                    <span class="aloc-cty-badge aloc-cty-badge--${bcls(c.personalPct)}">Tev ${c.personalPct}%</span>
                    <span class="aloc-cty-badge aloc-cty-badge--${bcls(c.generalPct)}">Visiem ${c.generalPct}%</span>
                </span>
            </button>`).join('');
        return `<div class="aloc-cty-group">
            <h6 class="aloc-cty-cont">
                <span>${CONTINENT_META[contId]}</span>
                <button class="aloc-cty-more" onclick="window.alocToggleCont('${contId}')"
                    title="${expanded ? `Sakļaut līdz TOP ${TOP_PER_CONTINENT}` : `Rādīt visu sarakstu (${all.length})`}">${expanded ? '−' : '+'}</button>
            </h6>${rows}</div>`;
    };
    el.style.display = 'block';
    el.innerHTML = `
        <h5 class="aloc-cty-title">🏆 Labākie galamērķi izvēlētajā laikā — pa kontinentiem (TOP ${TOP_PER_CONTINENT})</h5>
        <div class="aloc-cty-period">📅 ${periodLabel()}</div>
        <div class="aloc-cty-cols">
            ${CONTINENT_COLS.map(col => `<div class="aloc-cty-col">${col.map(groupHtml).join('')}</div>`).join('')}
        </div>
        <p class="aloc-cty-sub"><b>Tev</b> = Tavs personīgais vērtējums, <b>Visiem</b> = vispārīgais planētas fons. Sarindots pēc zemākā no abiem (50% = neitrāli, virs ~58% = labvēlīgi). Lielās valstis sadalītas reģionos. Klikšķis parāda vietu kartē; <b>+</b> izvērš pilno kontinenta sarakstu.</p>`;
}

function normalize(raw) {
    return clamp((raw / NORMALIZE_MAX_RAW) * 100, -100, 100);
}

function colorForScore(score) {
    const s = clamp(score, -100, 100);
    for (let i = 0; i < COLOR_STOPS.length - 1; i++) {
        const a = COLOR_STOPS[i], b = COLOR_STOPS[i + 1];
        if (s >= a.t && s <= b.t) {
            const f = (s - a.t) / (b.t - a.t);
            const r = Math.round(a.c[0] + f * (b.c[0] - a.c[0]));
            const g = Math.round(a.c[1] + f * (b.c[1] - a.c[1]));
            const bch = Math.round(a.c[2] + f * (b.c[2] - a.c[2]));
            return `rgb(${r},${g},${bch})`;
        }
    }
    return 'rgb(148,163,184)';
}

// Verdiktu nosaukumi saskaņoti ar Klasiskā Riteņa joslu (Harmonija/Neitrāls/Spriedze).
function verdictFor(score) {
    if (score >= VERDICT_THRESHOLD) return { emoji: '✅', label: 'Harmonija', cls: 'good' };
    if (score <= -VERDICT_THRESHOLD) return { emoji: '⚠️', label: 'Spriedze', cls: 'bad' };
    return { emoji: '➖', label: 'Neitrāli', cls: 'neutral' };
}

function mergedFactors(result) {
    const tagged = [
        ...(result.staticFactors || []).map(f => ({ ...f, layer: 'Relocētā dzimšanas karte' })),
        ...(result.dynamicFactors || []).map(f => ({ ...f, layer: 'Vispārīgais fons (periodā)' }))
    ];
    tagged.sort((a, b) => Math.abs(b.contrib) - Math.abs(a.contrib));
    return tagged;
}

// Aktīvā skata vērtība un faktori (terminoloģija kā sky_chart Klasiskajā Ritenī):
// 'sky'      = "Vispārīgais" — TIKAI tranzīta slānis (debess stāvoklis katrā vietā,
//              vienāds visiem cilvēkiem — dzimšanas karte netiek izmantota);
// 'natal'    = "Tikai tavas līnijas" — TIKAI relocētā dzimšanas karte (tīrs personīgais
//              slānis, laika-neatkarīgs; NEsatur kopīgo debesu fonu → tiešām unikāls katram);
// 'personal' = "Tev personīgi" — kopējais (relokācija + tranzīts pret Tavu karti, 50/50).
function layerScore(r) {
    if (_state.layer === 'sky') return r.dynamicNorm;
    if (_state.layer === 'natal') return r.staticNorm;
    return r.total;
}
function layerFactors(result) {
    if (_state.layer === 'sky') return (result.dynamicFactors || []).map(f => ({ ...f, layer: 'Vispārīgais fons (periodā)' }));
    if (_state.layer === 'natal') return (result.staticFactors || []).map(f => ({ ...f, layer: 'Relocētā dzimšanas karte' }));
    return mergedFactors(result);
}

// Hover-teksts cilvēkam BEZ astroloģijas priekšzināšanām — atvaļinājuma valodā,
// bez ASC/MC/planētu žargona (tehniskās detaļas paliek klikšķa info panelī).
const VACAY_GOOD = {
    Jupiters: 'veiksmes un plašu iespēju sajūta',
    Venera: 'bauda, romantika un vieglums',
    Saule: 'enerģija un laba pašsajūta',
    Merkurs: 'raita saziņa un pārvietošanās',
    Meness: 'emocionāls komforts un mājīgums'
};
const VACAY_BAD = {
    Saturns: 'iespējami kavējumi un ierobežojumi',
    Marss: 'steiga, berze un konfliktu risks',
    Urans: 'negaidīti plānu pavērsieni',
    Neptuns: 'miglainība un pārpratumi',
    Plutons: 'nogurdinoša intensitāte',
    Rahu: 'apjukums un pārprastas gaidas',
    Ketu: 'izklaidība un enerģijas kritumi'
};
function tooltipText(result) {
    const v = verdictFor(layerScore(result));
    const facs = layerFactors(result);
    const isNatal = _state.layer === 'natal'; // tīrās natālās līnijas → personīgā, laika-neatkarīgā valoda
    if (v.cls === 'good') {
        const top = facs.find(f => f.contrib > 0);
        const phrase = (top && VACAY_GOOD[top.planet]) || 'kopumā atbalstošs fons';
        return isNatal
            ? `✅ Tava stiprā vieta\nŠeit izceļas: ${phrase}.`
            : `✅ Labvēlīga vieta atpūtai\nGaidāms: ${phrase}.`;
    }
    if (v.cls === 'bad') {
        const top = facs.find(f => f.contrib < 0);
        const phrase = (top && VACAY_BAD[top.planet]) || 'kopumā saspringtāks fons';
        return isNatal
            ? `⚠️ Tava saspringtā vieta\nŠeit pastiprinās: ${phrase} — apzinies to.`
            : `⚠️ Saspringtāka vieta\nIespējams: ${phrase} — ieplāno elastību.`;
    }
    return isNatal
        ? `➖ Neitrāla vieta Tavai kartei\nŠeit nav izteiktu Tavas dzimšanas kartes līniju.`
        : `➖ Neitrāla vieta\nNav izteiktu plusu vai mīnusu — plāno droši pēc saviem ieskatiem.`;
}

// Maksimālais atvaļinājuma dienu skaits no izvēlētā sākuma datuma līdz Moshier
// efemerīdas augšējai robežai (3000. gads). Apakšējā robeža — 1 diena.
function maxCustomDays() {
    const startStr = _state.startDate || new Date().toISOString().split('T')[0];
    const jdStart = getJulianDay(startStr + 'T12:00:00Z');
    return Math.max(1, Math.floor(EPHEM_MAX_JD - jdStart));
}

// Sinhronizē dienu ievada max/title ar pašreizējo sākuma datumu; noklampē vērtību,
// ja datuma pārbīde limitu samazinājusi zem jau ievadītā.
function syncDaysLimit() {
    const el = document.getElementById('aloc-days');
    const max = maxCustomDays();
    if (el) {
        el.max = String(max);
        el.title = `1 – ${max.toLocaleString('lv-LV')} dienas (Moshier efemerīda: aprēķini iespējami līdz 3000. gadam)`;
    }
    if ((_state.customDays || 0) > max) {
        _state.customDays = max;
        if (el) el.value = String(max);
    }
}

function buildSampleJds() {
    if (_state.mode === 'now') {
        // "Tagad" = viens mirklis, BET datums ir ritināms ("Sākot no" + ±5g slīdnis):
        // pulksteņa laiks = pašreizējais, diena = izvēlētā. Ja datums = šodiena, tas
        // ir burtiski tagadējais brīdis.
        const startStr = _state.startDate || new Date().toISOString().split('T')[0];
        const now = new Date();
        const d = new Date(startStr + 'T12:00:00');
        d.setHours(now.getHours(), now.getMinutes(), 0, 0);
        return [getJulianDay(d.toISOString())];
    }
    let days, count;
    if (_state.mode === 'week')        { days = 7;  count = 6; }
    else if (_state.mode === '2weeks') { days = 14; count = 8; }
    else { // 'custom' — lietotāja ievadītais atvaļinājuma dienu skaits
        days = Math.max(1, Math.min(maxCustomDays(), _state.customDays || 7));
        // Paraugu skaits FIKSĒTS (maks. MAX_SAMPLES) — veiktspējai (garš periods → retāka paraugošana).
        count = Math.max(2, Math.min(MAX_SAMPLES, Math.round(days)));
    }
    const startStr = _state.startDate || new Date().toISOString().split('T')[0];
    const startMs = new Date(startStr + 'T00:00:00Z').getTime();
    const stepMs = (days * 86400000) / count;
    // SIDERĀLĀS FĀZES STRATIFIKĀCIJA (aliasinga labojums): leņķi (ASC/MC) rotē 360°
    // diennaktī, tāpēc vienmērīgs solis DIENĀS var trāpīt gandrīz vienā diennakts
    // fāzē — piem., 30 d / 10 paraugi = 72 h solis nobīda siderālo fāzi tikai par
    // ~3°/paraugu, un "perioda vidējais" faktiski bija VIENA patvaļīga brīža karte
    // (verificēts: tools/locator_audit.py, D tests). Tāpēc katru paraugu pabīda
    // (≤ ±pussolis un ≤ ±12 h) tā, lai paraugu siderālās fāzes vienmērīgi pārklātu
    // diennakts apli: planētu garumiem šāda nobīde ir nenozīmīga, leņķiem — izšķiroša.
    const jds = [];
    const maxShiftDays = Math.min(0.5, (stepMs / 86400000) / 2);
    for (let i = 0; i < count; i++) {
        const t = startMs + i * stepMs + stepMs / 2;
        const jd0 = getJulianDay(new Date(t).toISOString());
        // Fāze = GMST mod 360 līdz konstantei — stratifikācijai konstante nav svarīga.
        const curPhase = ((jd0 * SID_DEG_PER_DAY) % 360 + 360) % 360;
        const targetPhase = (i + 0.5) * (360 / count);
        let dPhase = (((targetPhase - curPhase) % 360) + 360) % 360;
        if (dPhase > 180) dPhase -= 360; // īsākais virziens
        jds.push(jd0 + clamp(dPhase / SID_DEG_PER_DAY, -maxShiftDays, maxShiftDays));
    }
    return jds;
}

// ── Lunācijas enkurs perioda režīmiem ────────────────────────────────────────
// Mundānās astroloģijas standarta prakse: perioda "vispārīgo fonu" vietai nolasa no
// FIKSĒTA brīža kartes — jaunā Mēness (lunācijas) pirms perioda sākuma. Šāda karte
// vietas atšķir un ir stabila līdz nākamajai lunācijai (~29,5 d) — atšķirībā no
// patvaļīgu mirkļu vidējošanas, kas matemātiski tiecas uz vienmērīgu pelēkumu
// (leņķi rotē 360°/diennaktī, tāpēc godīgs perioda vidējais ģeogrāfisko struktūru
// izmazgā; verificēts: tools/locator_audit.py). Perioda režīmos dinamiskais slānis =
// 50% lunācijas karte (stabilā, vietu atšķirošā daļa) + 50% perioda vidējais.
function findLastNewMoonJd(jdRef) {
    let jd = jdRef;
    for (let k = 0; k < 6; k++) {
        const t = getAstronomicalData(jd, 0, 0).tropical;
        let e = (t["Meness"] - t["Saule"]) % 360;
        if (e < 0) e += 360; // elongācija kopš pēdējā jaunā Mēness (0..360)
        if (k === 0) {
            jd -= e / SYNODIC_DEG_PER_DAY; // lēciens atpakaļ pie iepriekšējās lunācijas
        } else {
            const corr = (e > 180 ? e - 360 : e) / SYNODIC_DEG_PER_DAY; // Ņūtona precizēšana
            jd -= corr;
            if (Math.abs(corr) < 1e-4) break; // ~9 s precizitāte — režģim vairāk nevajag
        }
    }
    return jd;
}

// ── "Sākot no" datuma ↔ nobīdes dienās (no šodienas) konvertācija ──────────────
// Rēķina pusdienlaikā (12:00 lokāli) abās pusēs, lai izvairītos no DST/pusnakts
// datuma "pārlēkšanas".
function fmtDateLocal(ms) {
    const d = new Date(ms);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function todayNoonMs() {
    const d = new Date(); d.setHours(12, 0, 0, 0); return d.getTime();
}
function offsetToDateStr(days) {
    return fmtDateLocal(todayNoonMs() + days * 86400000);
}
function dateStrToOffset(dateStr) {
    if (!dateStr) return 0;
    const target = new Date(dateStr + 'T12:00:00').getTime();
    return Math.round((target - todayNoonMs()) / 86400000);
}
function fmtYearOffset(days) {
    if (!days) return 'šodien';
    const sign = days > 0 ? '+' : '−';
    const abs = Math.abs(days);
    const y = Math.floor(abs / 365);
    const m = Math.floor((abs % 365) / 30);
    const parts = [];
    if (y) parts.push(`${y} g.`);
    if (m) parts.push(`${m} mēn.`);
    if (!parts.length) parts.push(`${abs} d.`);
    return `${sign}${parts.join(' ')}`;
}
function syncYearSliderFromDate() {
    const off = dateStrToOffset(_state.startDate);
    const slider = document.getElementById('aloc-year-slider');
    if (slider) slider.value = String(Math.max(-YEAR_SLIDER_DAYS, Math.min(YEAR_SLIDER_DAYS, off)));
    const lEl = document.getElementById('aloc-year-offset');
    if (lEl) lEl.textContent = fmtYearOffset(off);
}

function setStatus(text, busy) {
    const el = document.getElementById('aloc-status');
    if (el) el.textContent = text || '';
    const mapEl = document.getElementById('aloc-map');
    if (mapEl) mapEl.style.opacity = busy ? '0.55' : '1';
}

// Progresa josla (aizpildās, kamēr rit aprēķins). frac ∈ [0,1]; null = paslēpt.
function setProgress(frac) {
    const fill = document.getElementById('aloc-progress-fill');
    const wrap = document.getElementById('aloc-progress-wrap');
    if (!fill || !wrap) return;
    if (frac == null) { wrap.classList.remove('aloc-progress--on'); fill.style.width = '0%'; return; }
    wrap.classList.add('aloc-progress--on');
    fill.style.width = Math.round(clamp(frac, 0, 1) * 100) + '%';
}

function paintCells() {
    if (!_lastCellResults) return;
    _cellLayers.forEach((rect, i) => {
        const r = _lastCellResults[i];
        const col = colorForScore(layerScore(r));
        rect.setStyle({ fillColor: col, color: col });
    });
}

// ── Viens koplietots hover lodziņš (nevis 648 Leaflet tooltip) ────────────────
function ensureHoverBox() {
    const mapEl = document.getElementById('aloc-map');
    if (!mapEl) return null;
    let box = document.getElementById('aloc-hover');
    if (!box) {
        box = document.createElement('div');
        box.id = 'aloc-hover';
        mapEl.appendChild(box); // Leaflet konteineram ir position:relative
        mapEl.addEventListener('mouseleave', hideHover); // drošības tīkls: pele pamet karti
    }
    return box;
}
function showHover(cellIdx, pt) {
    const box = ensureHoverBox();
    if (!box || !_lastCellResults || !_lastCellResults[cellIdx]) return;
    box.textContent = tooltipText(_lastCellResults[cellIdx]);
    box.style.display = 'block';
    const mapEl = document.getElementById('aloc-map');
    // virs kursora, pie malām nospiež iekšpusē
    box.style.left = Math.max(10, Math.min((mapEl?.clientWidth || 400) - 10, pt.x)) + 'px';
    box.style.top = Math.max(10, pt.y - 14) + 'px';
}
function hideHover() {
    const box = document.getElementById('aloc-hover');
    if (box) box.style.display = 'none';
}

function syncModeUI() {
    document.querySelectorAll('.aloc-mode-btn').forEach(btn => {
        btn.classList.toggle('aloc-mode-btn--active', btn.getAttribute('data-mode') === _state.mode);
    });
    // "Sākot no" + gadu slīdnis redzami VISOS režīmos (arī 'Tagad' — mirkļa datums
    // ritināms). Dienu ievads — tikai pielāgotajā (atvaļinājuma) režīmā.
    const customRow = document.getElementById('aloc-custom-row');
    if (customRow) customRow.style.display = (_state.mode === 'custom') ? 'flex' : 'none';
}

async function recompute() {
    if (!_profile || !_gridCells) return;
    syncDaysLimit(); // datuma maiņa maina arī maks. dienu limitu (efemerīdas robeža fiksēta)
    const myToken = ++_computeToken;
    setStatus('Aprēķina karti…', true);
    await sleep0(); // ļauj "busy" aptumšojumam uzzīmēties pirms (ātrā, sinhronā) aprēķina
    if (myToken !== _computeToken) return;

    const natalLons = {};
    for (const p of NATAL_KEYS) {
        const pd = _profile.western?.planets?.[p];
        if (pd && typeof pd.longitude === 'number') natalLons[p] = pd.longitude;
    }
    const jdBirth = _profile.birth_info.jd;
    // Nezināms dzimšanas laiks → statisko slāni vidējo pa 24h dzimšanas diennakti
    // (buildStaticSamples), nevis lieto pusdienlaika pieņēmumu. Zināmam laikam —
    // viens paraugs (uzvedība kā agrāk).
    const isUnknownTime = !!_profile.birth_info.isTimeUnknown;
    const staticSamples = buildStaticSamples(jdBirth, isUnknownTime, natalLons);
    // Statisko slāni rēķina katru reizi bez keša: analītiskais calcAscMc ir tik lēts
    // (~3 µs/šūna), ka kešs neatmaksājas, un smalkajos režīmos režģis mainās ar katru
    // kartes pārbīdi — kešs pēc novecojuša režģa būtu tikai kļūdu avots.
    const staticResults = computeStaticLayer(_gridCells, staticSamples);

    const sampleJds = buildSampleJds();
    const orb = _state.mode === 'now' ? ORB_DYNAMIC_NOW : ORB_DYNAMIC_AVG;
    setProgress(0);
    const dyn = await computeDynamicLayer(_gridCells, sampleJds, orb,
        frac => { if (myToken === _computeToken) setProgress(frac); });
    if (myToken !== _computeToken) return;
    setProgress(null);
    const dynamicResults = dyn.results;

    // Lunācijas enkurs — tikai perioda režīmiem ('Tagad' paliek tīrs viena mirkļa skats).
    let lun = null;
    if (_state.mode !== 'now') {
        try {
            const jdLun = findLastNewMoonJd(sampleJds[0]);
            lun = { jd: jdLun, lons: getAstronomicalData(jdLun, 0, 0).tropical };
        } catch (e) { console.warn('[ALOC] lunācijas enkurs neizdevās:', e); }
    }

    _lastCellResults = _gridCells.map((c, i) => {
        const st = staticResults[i];
        const dy = dynamicResults[i];
        const staticNorm = normalize(st.sum);
        let dynamicNorm = normalize(dy.sum);
        let dynamicFactors = dy.factors;
        if (lun) {
            const h = calcAscMc(lun.jd, c.latC, c.lonC);
            const r = scoreCell(lun.lons, h.asc, h.mc, ORB_DYNAMIC_NOW);
            dynamicNorm = clamp(0.5 * normalize(r.sum) + 0.5 * dynamicNorm, -100, 100);
            // Ilustratīvos faktorus ņem no lunācijas kartes — tā ir stabilā, vietu
            // atšķirošā perioda daļa (vidējotie paraugi faktorus izsmērē).
            if (r.factors.length) dynamicFactors = r.factors;
        }
        const total = clamp(0.5 * staticNorm + 0.5 * dynamicNorm, -100, 100);
        return { latC: c.latC, lonC: c.lonC, total, staticNorm, dynamicNorm, staticFactors: st.factors, dynamicFactors };
    });

    paintCells();
    setStatus('', false);

    // TOP valstu saraksts — neatkarīgs no rūtiņu režģa (rēķina valstu centroīdos ar
    // tiem pašiem laika paraugiem); atjaunojas ar katru datuma/režīma maiņu.
    try {
        renderCountryList(computeCountryRanking(staticSamples, sampleJds, orb, dyn.astroBySample, lun));
    } catch (e) { console.warn('[ALOC] valstu rangs neizdevās:', e); }
}

// Pārbūvē režģa taisnstūrus atbilstoši izvēlētajai izšķirtspējai un (smalkajos
// režīmos) pašreizējam kartes skatam. Pēc izsaukuma vienmēr jāseko recompute().
function buildGridLayers() {
    if (!_map) return;
    _cellLayers.forEach(l => { try { _map.removeLayer(l); } catch (e) {} });
    _cellLayers = [];
    _lastCellResults = null;
    hideHover();

    let fine = null;
    if (_state.resolution === 'global') {
        _gridCells = buildGrid();
    } else {
        fine = buildFineGrid(RESOLUTIONS[_state.resolution]);
        _gridCells = fine.cells;
    }

    const hintEl = document.getElementById('aloc-res-hint');
    if (hintEl) {
        if (!fine) hintEl.textContent = '';
        else if (fine.coarsened) hintEl.textContent = `Šajā mērogā rūtiņas ir ~${fine.effectiveKm} km (viss logs vienmēr nokrāsots) — pietuvini karti, lai sasniegtu ${RESOLUTIONS[_state.resolution]} km.`;
        else hintEl.textContent = 'Režģis seko kartes skatam — pārbīdi vai pietuvini, un tas pārzīmēsies.';
    }

    _gridCells.forEach((c, i) => {
        const rect = L.rectangle(c.bounds, {
            color: 'rgba(100,116,139,0.15)',
            weight: 0.5,
            fillColor: 'rgba(100,116,139,0.15)',
            fillOpacity: 0.55,
            className: 'aloc-grid-cell'
        });
        // NAV Leaflet bindTooltip: simtiem atsevišķu tooltip dzīvescikls blīvā režģī bija
        // trausls (izlaists mouseout ātrā kustībā → "iesaluši" lodziņi, kas uzkrājas).
        // Tā vietā VIENS koplietots hover lodziņš (#aloc-hover), ko vada mousemove —
        // viens elements pēc definīcijas nevar uzkrāties.
        rect.on('mousemove', (e) => showHover(i, e.containerPoint));
        rect.on('mouseout', hideHover);
        rect.addTo(_map);
        _cellLayers.push(rect);
    });
}

function setupMap() {
    const mapEl = document.getElementById('aloc-map');
    if (!mapEl) return;
    if (_map) { try { _map.remove(); } catch (e) {} _map = null; _cellLayers = []; }

    _map = L.map('aloc-map').setView([25.0, 15.0], 2);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CartoDB',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(_map);

    // Smalkajos režīmos režģis seko skatam: pēc pārbīdes/tālummaiņas pārbūvē (debounce,
    // lai vairāki moveend pēc kārtas nesāktu lieku darbu).
    _map.on('moveend', () => {
        if (Date.now() < _squelchMove) return; // programmatiska setView (piem. izšķirtspējas maiņa) — jau pārbūvēts
        if (_state.resolution === 'global') return;
        clearTimeout(_moveT);
        _moveT = setTimeout(() => { buildGridLayers(); recompute(); }, 300);
    });

    buildGridLayers();
}

// ════════════════════════════════════════════════════════════════════════════
// "KALENDĀRS" FILTRA DIALOGS (Google Flights stila datumu izvēle)
// Divas cilnes: "Konkrēti datumi" (izlidošana/atgriešanās + 2 mēnešu kalendārs ar
// diapazona izvēli) un "Elastīgi datumi" (mēnešu čipi + ilguma čipi). "Gatavs"
// pārtulko izvēli uz esošo perioda dzinēju: mode='custom' + startDate + customDays.
// ════════════════════════════════════════════════════════════════════════════
const LV_MONTHS = ['janvāris', 'februāris', 'marts', 'aprīlis', 'maijs', 'jūnijs', 'jūlijs', 'augusts', 'septembris', 'oktobris', 'novembris', 'decembris'];
const LV_MONTHS_LOC = ['janvārī', 'februārī', 'martā', 'aprīlī', 'maijā', 'jūnijā', 'jūlijā', 'augustā', 'septembrī', 'oktobrī', 'novembrī', 'decembrī'];
const LV_WEEKDAYS = ['P', 'O', 'T', 'C', 'P', 'S', 'S']; // pirmdiena..svētdiena
const CAL_DUR_DAYS = { weekend: 3, week: 7, twoweeks: 14 }; // nogale = Pk–Sv (3 d.)
const CAL_MAX_NAV_MONTHS = 11; // cik tālu uz priekšu var šķirt kalendāru

function calDefaultState() {
    const t = new Date();
    return {
        open: false, tab: 'dates',
        selStart: null, selEnd: null,               // 'YYYY-MM-DD' (konkrētie datumi)
        viewY: t.getFullYear(), viewM: t.getMonth(), // pirmais redzamais mēnesis
        months: {}, all6: true, dur: 'week'          // elastīgie datumi
    };
}
function calState() { if (!_cal) _cal = calDefaultState(); return _cal; }

// Nākamie 6 mēneši čipiem (sākot ar tekošo) — kā Google Flights "Elastīgi datumi".
function calFlexMonths() {
    const out = [], t = new Date();
    for (let i = 0; i < 6; i++) {
        const y = t.getFullYear() + Math.floor((t.getMonth() + i) / 12);
        const m = (t.getMonth() + i) % 12;
        out.push({ key: `${y}-${String(m + 1).padStart(2, '0')}`, y, m, name: LV_MONTHS[m] });
    }
    return out;
}

function calFmtField(ds) { // 'trešd., 15. jūl.'
    return new Intl.DateTimeFormat('lv-LV', { weekday: 'short', day: 'numeric', month: 'short' }).format(new Date(ds + 'T12:00:00'));
}

// Cilvēkvalodas kopsavilkums elastīgajai izvēlei ("Vienu nedēļu garš ceļojums jūlijā").
function calFlexSummary(s) {
    const durTxt = { weekend: 'Nedēļas nogales', week: 'Vienu nedēļu garš', twoweeks: 'Divas nedēļas garš' }[s.dur] || '';
    let per = 'nākamo sešu mēnešu laikā';
    if (!s.all6) {
        const names = calFlexMonths().filter(m => s.months[m.key]).map(m => LV_MONTHS_LOC[m.m]);
        if (names.length) per = names.join(', ');
    }
    return `${durTxt} ceļojums ${per}`;
}

// Viena mēneša režģis. Pagājušās dienas pelēkas un neklikšķināmas; izvēlētais
// diapazons: sākums = pildīts aplis, beigas = kontūras aplis, vidus = gaiša josla.
function calMonthHtml(y, m) {
    const s = calState();
    const todayDs = fmtDateLocal(todayNoonMs());
    const lead = (new Date(y, m, 1, 12).getDay() + 6) % 7; // pirmdiena pirmā
    const dim = new Date(y, m + 1, 0).getDate();
    let cells = '';
    for (let i = 0; i < lead; i++) cells += '<span class="aloc-cal-day aloc-cal-day--blank"></span>';
    for (let d = 1; d <= dim; d++) {
        const ds = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        let cls = 'aloc-cal-day';
        if (ds < todayDs) cls += ' aloc-cal-day--past';
        if (ds === s.selStart) cls += ' aloc-cal-day--start';
        else if (ds === s.selEnd) cls += ' aloc-cal-day--end';
        else if (s.selStart && s.selEnd && ds > s.selStart && ds < s.selEnd) cls += ' aloc-cal-day--in';
        cells += (ds < todayDs)
            ? `<span class="${cls}">${d}</span>`
            : `<button class="${cls}" onclick="window.alocCalPick('${ds}')">${d}</button>`;
    }
    return `<div class="aloc-cal-month"><div class="aloc-cal-mname">${LV_MONTHS[m]}</div>
        <div class="aloc-cal-grid">${LV_WEEKDAYS.map(w => `<span class="aloc-cal-wd">${w}</span>`).join('')}${cells}</div></div>`;
}

function calModalHtml() {
    const s = calState();
    const isDates = s.tab === 'dates';
    const valid = isDates ? !!s.selStart : true;

    let head;
    if (isDates) {
        const fld = (ds, which) => ds
            ? `<span class="aloc-cal-fld"><b>${calFmtField(ds)}</b>
                 <button class="aloc-cal-step" title="Dienu atpakaļ" onclick="window.alocCalShift('${which}',-1)">‹</button>
                 <button class="aloc-cal-step" title="Dienu uz priekšu" onclick="window.alocCalShift('${which}',1)">›</button></span>`
            : `<span class="aloc-cal-fld aloc-cal-fld--ph"></span>`;
        head = `<div class="aloc-cal-fields">🗓️ ${fld(s.selStart, 'start')}<span class="aloc-cal-sep"></span>${fld(s.selEnd, 'end')}</div>`;
    } else {
        head = `<div class="aloc-cal-fields">🗓️ <span class="aloc-cal-fld"><b>${calFlexSummary(s)}</b></span></div>`;
    }

    let body;
    if (isDates) {
        const t = new Date();
        const minYm = t.getFullYear() * 12 + t.getMonth();
        const curYm = s.viewY * 12 + s.viewM;
        const y2 = s.viewM === 11 ? s.viewY + 1 : s.viewY, m2 = (s.viewM + 1) % 12;
        body = `<div class="aloc-cal-months">
            <button class="aloc-cal-nav" title="Iepriekšējais mēnesis" onclick="window.alocCalNav(-1)" ${curYm <= minYm ? 'disabled' : ''}>‹</button>
            ${calMonthHtml(s.viewY, s.viewM)}${calMonthHtml(y2, m2)}
            <button class="aloc-cal-nav" title="Nākamais mēnesis" onclick="window.alocCalNav(1)" ${curYm >= minYm + CAL_MAX_NAV_MONTHS - 1 ? 'disabled' : ''}>›</button>
        </div>`;
    } else {
        const chip = (act, on, lbl) => `<button class="aloc-cal-chip${act ? ' aloc-cal-chip--on' : ''}" onclick="${on}">${act ? '✓ ' : ''}${lbl}</button>`;
        const mchips = calFlexMonths().map(m => chip(!s.all6 && !!s.months[m.key], `window.alocCalMonth('${m.key}')`, m.name)).join('');
        body = `<div class="aloc-cal-flex">
            <div class="aloc-cal-chips">${chip(s.all6, 'window.alocCalAll6()', 'Nākamie 6 mēneši')}${mchips}</div>
            <div class="aloc-cal-chips aloc-cal-chips--dur">
                ${chip(s.dur === 'weekend', "window.alocCalDur('weekend')", 'Nedēļas nogale')}
                ${chip(s.dur === 'week', "window.alocCalDur('week')", '1 nedēļa')}
                ${chip(s.dur === 'twoweeks', "window.alocCalDur('twoweeks')", 'Divas nedēļas')}
            </div>
        </div>`;
    }

    return `<div class="aloc-cal-backdrop" onclick="if(event.target===this)window.alocCalClose()">
      <div class="aloc-cal">
        <div class="aloc-cal-tabs">
            <button class="aloc-cal-tabbtn${isDates ? ' aloc-cal-tabbtn--on' : ''}" onclick="window.alocCalTab('dates')">Konkrēti datumi</button>
            <button class="aloc-cal-tabbtn${!isDates ? ' aloc-cal-tabbtn--on' : ''}" onclick="window.alocCalTab('flex')">Elastīgi datumi</button>
        </div>
        ${head}
        ${body}
        <div class="aloc-cal-foot">
            <button class="aloc-cal-reset" onclick="window.alocCalReset()">Atiestatīt</button>
            <button class="aloc-cal-done" onclick="window.alocCalApply()" ${valid ? '' : 'disabled'}>Gatavs</button>
        </div>
      </div></div>`;
}

function renderCal() {
    const el = document.getElementById('aloc-cal-root');
    if (el) el.innerHTML = (_cal && _cal.open) ? calModalHtml() : '';
}

function scaffold() {
    // Nezināms dzimšanas laiks → "Tev personīgi" statiskā daļa izlīdzinās un skats kļūst
    // ļoti tuvs "Vispārīgajam" (empīriski korelācija ≈0,98 pret ≈0,63 zināmam laikam).
    // Tāpēc pogu iezīmē ar "≈" + paskaidrojošu tooltipu, lai lietotājs nesagaida atšķirīgu karti.
    const isUnknown = !!_profile?.birth_info?.isTimeUnknown;
    return `
    <style>
        .aloc-panel { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--glass-border, #e2e8f0); }
        .aloc-title { font-size: 1.1rem; color: #1e293b; font-weight: 800; display:flex; align-items:center; gap:8px; margin:0; }
        .aloc-sub { color:#475569; font-size:0.85rem; margin: 4px 0 14px 0; }
        .aloc-controls { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom: 10px; }
        .aloc-mode-btn {
            padding: 0.5rem 1rem; border-radius: 999px; border: 1px solid #cbd5e1;
            background:#f8fafc; color:#475569; font-weight:700; font-size:0.85rem; cursor:pointer;
        }
        .aloc-mode-btn--active { background:#4f46e5; border-color:#4f46e5; color:#fff; }
        #aloc-custom-row { display:none; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap; }
        #aloc-custom-row label, #aloc-date-row label { font-size:0.85rem; color:#475569; font-weight:700; white-space:nowrap; }
        /* Dienu ievads — palielināts (līdz 6 cipariem, jo limits = efemerīdas robeža). */
        .aloc-days-input { width:calc(9ch + 20px); padding:0.55rem; font-size:0.95rem; text-align:center; }
        .aloc-days-unit { font-size:0.9rem; color:#475569; font-weight:700; }
        /* Progresa josla (piezīmes vietā) — cik daudz aprēķina pabeigts. */
        .aloc-progress-wrap { flex:1 1 160px; min-width:120px; height:10px; border-radius:6px; background:#e2e8f0; overflow:hidden; opacity:0; transition:opacity 0.2s; }
        .aloc-progress-wrap.aloc-progress--on { opacity:1; }
        .aloc-progress-fill { height:100%; width:0%; border-radius:6px; background:linear-gradient(90deg,#818cf8,#4f46e5); transition:width 0.12s linear; }
        .aloc-layers { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin: 2px 0 12px 0; }
        .aloc-layers-lbl { font-size:0.78rem; color:#64748b; font-weight:700; }
        .aloc-layer-btn { padding:0.35rem 0.7rem; border-radius:999px; border:1px solid #cbd5e1; background:#f8fafc; color:#475569; font-weight:700; font-size:0.78rem; cursor:pointer; }
        .aloc-layer-btn--active { background:#0f766e; border-color:#0f766e; color:#fff; }
        /* "≈" iezīme pie "Tev personīgi", ja dzimšanas laiks nezināms (skats tuvs Vispārīgajam). */
        .aloc-approx { display:inline-block; margin-left:2px; font-weight:900; color:#f59e0b; cursor:help; }
        .aloc-layer-btn--active .aloc-approx { color:#fde68a; }
        /* Izcelts rāmītis jaunajam "Tikai tavas līnijas" skatam — atšķiras no pārējiem diviem. */
        .aloc-natal-box { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin: 2px 0 10px 0;
            padding:8px 12px; background:rgba(15,118,110,0.06); border:1px dashed rgba(15,118,110,0.45);
            border-radius:10px; max-width:640px; }
        .aloc-natal-btn { flex:0 0 auto; }
        .aloc-natal-desc { flex:1 1 260px; min-width:0; font-size:0.75rem; color:#475569; line-height:1.4; }
        .aloc-res-btn { padding:0.35rem 0.7rem; border-radius:999px; border:1px solid #cbd5e1; background:#f8fafc; color:#475569; font-weight:700; font-size:0.78rem; cursor:pointer; }
        .aloc-res-btn--active { background:#4f46e5; border-color:#4f46e5; color:#fff; }
        .aloc-res-row { display:flex; gap:6px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
        .aloc-res-hint { font-size:0.72rem; color:#92400e; font-weight:600; text-align:right; max-width:400px; }
        /* TOP valstu saraksts */
        .aloc-countries { margin-top:12px; background:rgba(255,255,255,0.6); border:1px solid var(--glass-border,#e2e8f0); border-radius:12px; padding:14px 16px; }
        .aloc-cty-title { margin:0 0 4px 0; font-size:0.95rem; color:#1e293b; font-weight:800; }
        /* Perioda datums — lielākā fontā, lai uzreiz redzams, PAR KĀDU laiku rangs rēķināts. */
        .aloc-cty-period { margin:0 0 12px 0; font-size:1.05rem; font-weight:800; color:#4f46e5;
            background:rgba(79,70,229,0.08); border:1px solid rgba(79,70,229,0.2); border-radius:8px;
            padding:6px 12px; display:inline-block; }
        .aloc-cty-sub { margin:12px 0 0 0; font-size:0.75rem; color:#64748b; border-top:1px solid #e2e8f0; padding-top:8px; }
        /* 3 kolonnas: Z+D Amerika | Eiropa+Āfrika | Āzija+Okeānija (šaurā ekrānā sakrājas) */
        .aloc-cty-cols { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; align-items:start; }
        @media (max-width: 900px) { .aloc-cty-cols { grid-template-columns:1fr; } }
        .aloc-cty-col { display:flex; flex-direction:column; gap:14px; min-width:0; }
        .aloc-cty-group { display:flex; flex-direction:column; gap:5px; }
        .aloc-cty-cont { margin:0 0 3px 0; font-size:0.8rem; color:#4f46e5; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; border-bottom:1px solid #e2e8f0; padding-bottom:3px; display:flex; align-items:center; justify-content:space-between; gap:6px; }
        .aloc-cty-more { border:1px solid #c7d2fe; background:#eef2ff; color:#4f46e5; font-weight:900; font-size:0.9rem; line-height:1; width:20px; height:20px; border-radius:6px; cursor:pointer; padding:0; flex:0 0 auto; }
        .aloc-cty-more:hover { background:#e0e7ff; }
        .aloc-cty { display:flex; align-items:center; flex-wrap:wrap; gap:6px; width:100%; text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 8px; cursor:pointer; font-family:inherit; }
        .aloc-cty:hover { background:#eef2ff; }
        .aloc-cty-rank { font-weight:800; color:#94a3b8; font-size:0.75rem; min-width:18px; }
        .aloc-cty-name { font-weight:700; color:#1e293b; font-size:0.8rem; flex:1 1 auto; min-width:0; }
        .aloc-cty-badges { display:flex; gap:4px; margin-left:auto; }
        .aloc-cty-badge { font-size:0.68rem; font-weight:800; padding:2px 7px; border-radius:999px; white-space:nowrap; }
        .aloc-cty-badge--good { background:rgba(34,197,94,0.15); color:#15803d; }
        .aloc-cty-badge--mid { background:rgba(148,163,184,0.2); color:#475569; }
        .aloc-cty-badge--bad { background:rgba(239,68,68,0.15); color:#b91c1c; }
        .aloc-warn { margin: 2px 0 10px 0; padding: 8px 12px; background: rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.35); border-radius:8px; color:#92400e; font-size:0.78rem; line-height:1.4; }
        /* Vadības izkārtojums: pogas KREISAJĀ pusē, laika ritināšana + rūtiņu izvēle
           fiksētas LABAJĀ pusē (savā kastē). */
        .aloc-ctl { display:flex; gap:14px; align-items:stretch; flex-wrap:wrap; margin-bottom:10px; }
        .aloc-ctl-left { flex:1 1 340px; display:flex; flex-direction:column; gap:8px; align-items:flex-start; }
        .aloc-ctl-right { flex:0 1 420px; margin-left:auto; display:flex; flex-direction:column; gap:8px; align-items:flex-end;
            background:rgba(255,255,255,0.5); border:1px solid var(--glass-border,#e2e8f0); border-radius:10px; padding:10px 12px; }
        #aloc-date-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
        /* "Sākot no" lauks šaurs — pietiek ~12 simboliem, ne visas lapas platumā. */
        .aloc-date-input { width:13ch; min-width:0; flex:0 0 auto; padding:0.4rem; font-size:0.85rem; }
        .aloc-year-shift { display:flex; align-items:center; gap:8px; width:100%; min-width:220px; }
        .aloc-year-shift-cap { font-size:0.72rem; color:#94a3b8; font-weight:700; white-space:nowrap; }
        #aloc-year-slider { flex:1 1 auto; min-width:100px; accent-color:#4f46e5; cursor:pointer; }
        .aloc-year-offset { font-family:monospace; font-size:0.78rem; font-weight:800; color:#4f46e5; background:rgba(79,70,229,0.1); padding:3px 8px; border-radius:6px; min-width:70px; text-align:center; }
        .aloc-legend { display:flex; flex-direction:column; gap:4px; margin: 6px 0 14px 0; max-width: 420px; }
        .aloc-legend-bar { height: 10px; border-radius: 6px; background: linear-gradient(90deg, rgb(153,27,27), rgb(239,68,68), rgb(148,163,184), rgb(34,197,94), rgb(5,150,105)); }
        .aloc-legend-labels { display:flex; justify-content:space-between; font-size:0.72rem; color:#64748b; }
        #aloc-map { width:100%; height:460px; border-radius:12px; border:1px solid var(--glass-border,#e2e8f0); box-shadow:0 4px 15px rgba(0,0,0,0.1); background:#1e293b; transition: opacity 0.2s; }
        /* Viens koplietots hover lodziņš (aizstāj Leaflet per-šūnas tooltip) */
        #aloc-hover { position:absolute; display:none; z-index:800; pointer-events:none; transform:translate(-50%,-100%);
            background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff;
            font-size:0.8rem; padding:8px 12px; text-align:center; white-space:pre-line; line-height:1.4;
            box-shadow:0 4px 15px rgba(0,0,0,0.5); max-width:240px; }
        #aloc-status { min-height: 1.2em; font-size:0.8rem; color:#4f46e5; font-weight:700; margin: 8px 0; }
        .aloc-footer { margin-top: 12px; font-size:0.72rem; color:#94a3b8; line-height:1.4; }
        /* ── "Kalendārs" filtra dialogs (Google Flights stils) ── */
        .aloc-cal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,0.35); z-index:2100; display:flex; align-items:flex-start; justify-content:center; padding:6vh 16px; }
        .aloc-cal { background:#fff; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,0.35); width:min(780px,100%); max-height:86vh; overflow:auto; padding-bottom:12px; }
        .aloc-cal-tabs { display:flex; gap:6px; border-bottom:1px solid #e2e8f0; padding:6px 18px 0; }
        .aloc-cal-tabbtn { background:none; border:none; padding:12px 14px 10px; font-size:0.95rem; font-weight:700; color:#334155; cursor:pointer; border-bottom:3px solid transparent; font-family:inherit; }
        .aloc-cal-tabbtn--on { color:#1a73e8; border-bottom-color:#1a73e8; }
        .aloc-cal-fields { display:flex; align-items:center; gap:12px; margin:14px 18px; border:1px solid #cbd5e1; border-radius:8px; padding:12px 14px; font-size:0.95rem; color:#475569; max-width:560px; }
        .aloc-cal-fld { display:flex; align-items:center; gap:4px; color:#1e293b; white-space:nowrap; }
        .aloc-cal-fld--ph { color:#94a3b8; }
        .aloc-cal-sep { width:1px; align-self:stretch; background:#e2e8f0; }
        .aloc-cal-step { border:none; background:none; color:#475569; font-size:1.1rem; cursor:pointer; padding:0 5px; line-height:1; }
        .aloc-cal-step:hover { color:#1a73e8; }
        .aloc-cal-months { display:flex; gap:20px; align-items:flex-start; justify-content:center; padding:6px 14px; }
        .aloc-cal-nav { border:1px solid #e2e8f0; background:#fff; width:34px; height:34px; border-radius:50%; cursor:pointer; font-size:1.1rem; color:#475569; margin-top:70px; flex:0 0 auto; }
        .aloc-cal-nav:hover:not(:disabled) { background:#f1f5f9; }
        .aloc-cal-nav:disabled { opacity:0.3; cursor:default; }
        .aloc-cal-month { flex:0 1 300px; }
        .aloc-cal-mname { text-align:center; font-weight:700; font-size:1.05rem; color:#1e293b; margin-bottom:8px; }
        .aloc-cal-grid { display:grid; grid-template-columns:repeat(7,38px); justify-content:center; row-gap:2px; }
        .aloc-cal-wd { text-align:center; font-size:0.75rem; color:#64748b; font-weight:700; padding:4px 0; }
        .aloc-cal-day { width:38px; height:38px; display:flex; align-items:center; justify-content:center; border:none; background:none; border-radius:50%; font-size:0.85rem; font-weight:600; color:#1e293b; cursor:pointer; font-family:inherit; padding:0; }
        .aloc-cal-day:hover:not(.aloc-cal-day--past):not(.aloc-cal-day--blank) { box-shadow:inset 0 0 0 1px #94a3b8; }
        .aloc-cal-day--blank, .aloc-cal-day--past { cursor:default; }
        .aloc-cal-day--past { color:#cbd5e1; }
        .aloc-cal-day--start { background:#1a73e8; color:#fff; }
        .aloc-cal-day--end { background:#fff; color:#1e293b; box-shadow:inset 0 0 0 2px #5f6368; }
        .aloc-cal-day--in { background:#e8f0fe; border-radius:0; }
        .aloc-cal-chips { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; padding:14px 18px; }
        .aloc-cal-chips--dur { border-top:0; padding-top:4px; }
        .aloc-cal-chip { border:1px solid #dadce0; background:#fff; color:#3c4043; border-radius:10px; padding:10px 16px; font-size:0.9rem; font-weight:600; cursor:pointer; font-family:inherit; }
        .aloc-cal-chip:hover { background:#f8fafc; }
        .aloc-cal-chip--on { background:#e8f0fe; border-color:#e8f0fe; color:#1a73e8; }
        .aloc-cal-foot { display:flex; justify-content:flex-end; align-items:center; gap:20px; border-top:1px solid #e2e8f0; padding:14px 20px 4px; margin-top:8px; }
        .aloc-cal-reset { border:none; background:none; color:#1a73e8; font-weight:700; font-size:0.95rem; cursor:pointer; font-family:inherit; }
        .aloc-cal-done { border:none; background:#1a73e8; color:#fff; font-weight:700; font-size:0.95rem; border-radius:999px; padding:11px 28px; cursor:pointer; font-family:inherit; }
        .aloc-cal-done:hover:not(:disabled) { background:#1765cc; }
        .aloc-cal-done:disabled { background:#e2e8f0; color:#94a3b8; cursor:default; }
    </style>
    <div class="aloc-panel">
        <h4 class="aloc-title">🏖️ Atvaļinājumu karte</h4>
        <p class="aloc-sub">Divi skati: <b>Tev personīgi</b> — kā katra pasaules vieta rezonē tieši ar Tavu horoskopu; <b>Vispārīgais horoskops</b> — visas planētas kosmiskais fons katrā vietā, vienāds visiem cilvēkiem (nav saistīts ar Tavu dzimšanas karti). Brauc ar peli pār karti īsam verdiktam; apakšā — labāko galamērķu TOP pa kontinentiem.</p>

        <div class="aloc-ctl">
            <div class="aloc-ctl-left">
                <div class="aloc-controls">
                    <button class="aloc-mode-btn" data-mode="now" onclick="window.setAlocMode('now')">🕐 Tagad (mirklis)</button>
                    <button class="aloc-mode-btn" data-mode="week" onclick="window.setAlocMode('week')">📅 Nedēļas vidējais</button>
                    <button class="aloc-mode-btn" data-mode="2weeks" onclick="window.setAlocMode('2weeks')">📅 2 nedēļu vidējais</button>
                    <button class="aloc-mode-btn" data-mode="custom" onclick="window.setAlocMode('custom')">🏖️ Atvaļinājums (dienas)</button>
                    <button class="aloc-mode-btn" onclick="window.alocCalOpen()" title="Konkrēti vai elastīgi ceļojuma datumi">🗓️ Kalendārs</button>
                </div>
                <div id="aloc-custom-row" style="display:none;">
                    <label>Atvaļinājuma ilgums:</label>
                    <input type="number" id="aloc-days" class="input-control aloc-days-input" min="1" value="7"
                           oninput="if(this.value.length>6)this.value=this.value.slice(0,6)" onchange="window.setAlocDays(this.value)">
                    <span class="aloc-days-unit">dienas</span>
                    <div class="aloc-progress-wrap" id="aloc-progress-wrap" title="Aprēķina progress"><div class="aloc-progress-fill" id="aloc-progress-fill"></div></div>
                </div>
                <div class="aloc-layers">
                    <span class="aloc-layers-lbl">Skats:</span>
                    <button class="aloc-layer-btn aloc-layer-btn--active" data-layer="personal" onclick="window.setAlocLayer('personal')"${isUnknown ? ' title="Bez precīza dzimšanas laika šis skats ir izlīdzināts un ļoti tuvs &quot;Vispārīgajam&quot; — personīgā ģeogrāfija nav droši nosakāma."' : ''}>🧬 Tev personīgi${isUnknown ? ' <span class="aloc-approx" title="≈ tuvs Vispārīgajam bez precīza laika">≈</span>' : ''}</button>
                    <button class="aloc-layer-btn" data-layer="sky" onclick="window.setAlocLayer('sky')">🌐 Vispārīgais horoskops (visiem)</button>
                </div>
                <!-- Atsevišķs izcelts rāmītis: jaunais tīrā personīgā slāņa skats atšķiras no pārējiem diviem. -->
                <div class="aloc-natal-box">
                    <button class="aloc-layer-btn aloc-natal-btn" data-layer="natal" onclick="window.setAlocLayer('natal')" title="Tikai Tavas dzimšanas kartes līnijas — laika-neatkarīgas un unikālas tieši Tev (bez kopīgā debesu fona, ko satur &quot;Tev personīgi&quot;).${isUnknown ? ' Bez precīza dzimšanas laika šīs līnijas ir izlīdzinātas → karte būs gandrīz vienlaidus.' : ''}">🧭 Tikai tavas līnijas${isUnknown ? ' <span class="aloc-approx" title="bez precīza laika līnijas izlīdzinās">≈</span>' : ''}</button>
                    <span class="aloc-natal-desc">Rāda <b>tikai</b> Tavas dzimšanas kartes līnijas — tīro, laika-neatkarīgo personīgo slāni, kas tiešām atšķiras katram cilvēkam (bez kopīgā debesu fona, ko satur “Tev personīgi”).${isUnknown ? ' <i>Bez precīza dzimšanas laika šīs līnijas izlīdzinās — karte būs gandrīz vienlaidus.</i>' : ''}</span>
                </div>
            </div>
            <div class="aloc-ctl-right">
                <div id="aloc-date-row">
                    <label>Sākot no:</label>
                    <input type="date" id="aloc-date" class="input-control aloc-date-input" min="${START_DATE_MIN}" max="${START_DATE_MAX}" onchange="window.setAlocDate(this.value)">
                </div>
                <div class="aloc-year-shift" title="Pārbīdi atskaites datumu ±5 gadus">
                    <span class="aloc-year-shift-cap">−5 g.</span>
                    <input type="range" id="aloc-year-slider" min="-${YEAR_SLIDER_DAYS}" max="${YEAR_SLIDER_DAYS}" step="1" value="0"
                           oninput="window.alocYearSliderInput(this.value)" onchange="window.alocYearSliderChange()">
                    <span class="aloc-year-shift-cap">+5 g.</span>
                    <span id="aloc-year-offset" class="aloc-year-offset">šodien</span>
                </div>
                <div class="aloc-res-row">
                    <span class="aloc-layers-lbl">Rūtiņa:</span>
                    <button class="aloc-res-btn${_state.resolution === 'global' ? ' aloc-res-btn--active' : ''}" data-res="global" onclick="window.setAlocRes('global')">🌍 Pasaule (~900 km)</button>
                    <button class="aloc-res-btn${_state.resolution === 'r500' ? ' aloc-res-btn--active' : ''}" data-res="r500" onclick="window.setAlocRes('r500')">500×500 km</button>
                    <button class="aloc-res-btn${_state.resolution === 'r100' ? ' aloc-res-btn--active' : ''}" data-res="r100" onclick="window.setAlocRes('r100')">100×100 km</button>
                    <button class="aloc-res-btn${_state.resolution === 'r10' ? ' aloc-res-btn--active' : ''}" data-res="r10" onclick="window.setAlocRes('r10')">10×10 km</button>
                </div>
                <span id="aloc-res-hint" class="aloc-res-hint"></span>
            </div>
        </div>
        ${_profile?.birth_info?.isTimeUnknown ? `<div class="aloc-warn">⚠️ Dzimšanas laiks nav norādīts — skata <b>"Tev personīgi"</b> nemainīgā daļa (Tavas kartes "spēka joslas") ir <b>vidējota pa visām 24 dzimšanas dienas stundām</b>. Tāpēc tā ir izlīdzināta un konkrētas ģeogrāfiskas "spēka līnijas" bez precīza laika nav nosakāmas; ar precīzu laiku karte kļūtu asāka. Skats <b>"Vispārīgais horoskops"</b> no dzimšanas laika nav atkarīgs.</div>` : ''}

        <div class="aloc-legend">
            <div class="aloc-legend-bar"></div>
            <div class="aloc-legend-labels"><span>⚠️ Spriedze</span><span>➖ Neitrāli</span><span>✅ Harmonija</span></div>
        </div>

        <div id="aloc-status"></div>
        <div id="aloc-map"></div>
        <div id="aloc-countries" class="aloc-countries" style="display:none;"></div>
        <div id="aloc-cal-root"></div>

        <p class="aloc-footer"><b>Tev personīgi</b> = 50% Tava dzimšanas karte, projicēta uz šo vietu (nemainīga uz mūžu — tāpēc daļa raksta nemainās ar datumu) + 50% debesu stāvoklis izvēlētajā periodā (mainās ar datumu). <b>Tikai tavas līnijas</b> = tā pati dzimšanas karte BEZ kopīgā debesu fona — tīrs, laika-neatkarīgs personīgais slānis, kas tiešām atšķiras katram cilvēkam (izmanto to, ja gribi redzēt, kas kartē ir <i>tikai</i> Tavs; bez precīza dzimšanas laika tas ir izlīdzināts). <b>Vispārīgais horoskops</b> = debesu stāvoklis katrā vietā — vienāds visiem cilvēkiem; perioda režīmos puse vērtējuma nāk no perioda <b>lunācijas</b> (jaunā Mēness pirms perioda sākuma) kartes — mundānās astroloģijas enkurs, kas ir stabils līdz nākamajai lunācijai, otra puse — no perioda vidējā fona. Skala centrēta: 50% = "kā vidēji jebkurā vietā un brīdī". TOP valstis sarindotas pēc zemākā no abiem vērtējumiem. Tendences, ne diagnozes vai ceļojuma aizliegumi — karte <b>nav</b> notikumu (dabas stihiju, konfliktu, negadījumu) riska prognoze un tos neparedz.</p>
    </div>
    `;
}

export function initAstroLocator(profile) {
    _profile = profile || window.currentProfile1 || null;
    const root = document.getElementById('astro-locator-root');
    if (!root) { console.warn('[ALOC] nav #astro-locator-root'); return; }
    if (!_profile) { root.innerHTML = ''; return; }

    // Stāvokļa atiestate PIRMS scaffold() — HTML aktīvās pogas (piem. rūtiņu izmērs)
    // tiek renderētas no _state, tāpēc secība ir svarīga atkārtotā init.
    const todayStr = new Date().toISOString().split('T')[0];
    _state.startDate = todayStr;
    _state.mode = 'now';
    _state.customDays = 7;
    _state.layer = 'personal';
    _state.resolution = 'r10'; // noklusētais rūtiņu izmērs — 10×10 km
    _lastRanking = null;
    _expandedConts = {};
    _cal = null;

    root.innerHTML = scaffold();

    window.setAlocMode = (mode) => {
        _state.mode = mode;
        if (mode === 'now') {
            // "Tagad" = burtiski tagad: laika ritināšana atgriežas 0 pozīcijā ("šodien").
            _state.startDate = new Date().toISOString().split('T')[0];
            const dEl = document.getElementById('aloc-date');
            if (dEl) dEl.value = _state.startDate;
            syncYearSliderFromDate();
        }
        syncModeUI();
        recompute();
    };
    window.setAlocDate = (val) => {
        if (!val) return;
        const clamped = clampDateStr(val); // efemerīdas robeža — 'now'/'week'/'2weeks' neklampē paši
        _state.startDate = clamped;
        const dEl = document.getElementById('aloc-date');
        if (dEl && dEl.value !== clamped) dEl.value = clamped; // atspoguļo klampēto vērtību ievadā
        syncYearSliderFromDate(); // manuāls datums → slīdnis + nobīdes uzraksts sekojoši
        recompute();
    };
    window.setAlocDays = (val) => {
        let n = parseInt(val, 10);
        if (isNaN(n)) n = 7;
        n = Math.max(1, Math.min(maxCustomDays(), n));
        _state.customDays = n;
        const el = document.getElementById('aloc-days');
        if (el && el.value !== String(n)) el.value = String(n);
        recompute();
    };
    // Slīdņa vilkšana: dzīvi atjauno datumu+uzrakstu (lēti), BET pārrēķina tikai atlaižot
    // (onchange) — lai vilkšana nesāktu desmitiem smago režģa pārrēķinu.
    window.alocYearSliderInput = (val) => {
        const days = parseInt(val, 10) || 0;
        const dateStr = offsetToDateStr(days);
        _state.startDate = dateStr;
        const dEl = document.getElementById('aloc-date'); if (dEl) dEl.value = dateStr;
        const lEl = document.getElementById('aloc-year-offset'); if (lEl) lEl.textContent = fmtYearOffset(days);
    };
    window.alocYearSliderChange = () => { recompute(); };
    // Slāņu pārslēdzējs — TIKAI pārkrāso (vērtības jau saglabātas _lastCellResults), NEpārrēķina.
    window.setAlocLayer = (layer) => {
        _state.layer = layer;
        document.querySelectorAll('.aloc-layer-btn').forEach(btn => {
            btn.classList.toggle('aloc-layer-btn--active', btn.getAttribute('data-layer') === layer);
        });
        paintCells();
    };
    // Izšķirtspējas pārslēdzējs — VIENMĒR atgriež pasaules skatu (rezultātu logs sākas
    // pasaules mērogā jebkurā rūtiņu izmērā; viss logs nokrāsots — smalkajiem izmēriem
    // ar adaptīvi palielinātām rūtiņām, līdz lietotājs pietuvina).
    window.setAlocRes = (res) => {
        _state.resolution = res;
        document.querySelectorAll('.aloc-res-btn').forEach(btn => {
            btn.classList.toggle('aloc-res-btn--active', btn.getAttribute('data-res') === res);
        });
        if (_map) {
            _squelchMove = Date.now() + 600; // setView izraisīs moveend — nepārbūvēt dubulti
            _map.setView([25.0, 15.0], 2, { animate: false });
        }
        buildGridLayers();
        recompute();
    };
    // Klikšķis TOP valstu sarakstā — parāda vietu kartē (smalkajos režīmos moveend pārbūvēs režģi).
    window.alocGotoCountry = (lat, lon) => {
        if (_map) _map.setView([lat, lon], _state.resolution === 'global' ? 4 : 5);
        document.getElementById('aloc-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    // +/− poga kontinenta virsrakstā — izvērš/sakļauj pilno saraksta versiju (bez pārrēķina).
    window.alocToggleCont = (contId) => {
        _expandedConts[contId] = !_expandedConts[contId];
        if (_lastRanking) renderCountryList(_lastRanking);
    };

    // ── "Kalendārs" dialoga notikumi ─────────────────────────────────────────
    window.alocCalOpen = () => { calState().open = true; renderCal(); };
    window.alocCalClose = () => { if (_cal) _cal.open = false; renderCal(); };
    window.alocCalTab = (t) => { calState().tab = t; renderCal(); };
    window.alocCalNav = (dir) => {
        const s = calState();
        const ym = s.viewY * 12 + s.viewM + dir;
        s.viewY = Math.floor(ym / 12); s.viewM = ((ym % 12) + 12) % 12;
        renderCal();
    };
    // Diapazona izvēle: 1. klikšķis = izlidošana, 2. = atgriešanās; klikšķis pirms
    // sākuma pārceļ sākumu; pēc pabeigta diapazona jauns klikšķis sāk no jauna.
    window.alocCalPick = (ds) => {
        const s = calState();
        if (!s.selStart || s.selEnd) { s.selStart = ds; s.selEnd = null; }
        else if (ds < s.selStart) s.selStart = ds;
        else if (ds !== s.selStart) s.selEnd = ds;
        renderCal();
    };
    // ‹ › bultiņas pie datuma laukiem — pabīda datumu par dienu (sākums ne agrāk
    // par šodienu; beigas ne agrāk par sākumu).
    window.alocCalShift = (which, dir) => {
        const s = calState();
        const todayDs = fmtDateLocal(todayNoonMs());
        const shift = ds => fmtDateLocal(new Date(ds + 'T12:00:00').getTime() + dir * 86400000);
        if (which === 'start' && s.selStart) {
            const ns = shift(s.selStart);
            if (ns < todayDs) return;
            s.selStart = ns;
            if (s.selEnd && s.selEnd <= s.selStart) s.selEnd = null;
        } else if (which === 'end' && s.selEnd) {
            const ne = shift(s.selEnd);
            if (ne <= s.selStart) return;
            s.selEnd = ne;
        }
        renderCal();
    };
    window.alocCalMonth = (key) => {
        const s = calState();
        s.all6 = false;
        s.months[key] = !s.months[key];
        if (!Object.values(s.months).some(Boolean)) s.all6 = true; // neviens mēnesis → atpakaļ uz "visi 6"
        renderCal();
    };
    window.alocCalAll6 = () => { const s = calState(); s.all6 = true; s.months = {}; renderCal(); };
    window.alocCalDur = (d) => { calState().dur = d; renderCal(); };
    window.alocCalReset = () => {
        const s = calState();
        if (s.tab === 'dates') { s.selStart = null; s.selEnd = null; }
        else { s.all6 = true; s.months = {}; s.dur = 'week'; }
        renderCal();
    };
    // "Gatavs" — izvēli pārtulko uz perioda dzinēju (mode='custom' + sākums + dienas).
    // Elastīgajā cilnē sākums = šodiena vai agrākā izvēlētā mēneša 1. datums;
    // "Nedēļas nogale" pielec pie tuvākās piektdienas.
    window.alocCalApply = () => {
        const s = calState();
        let startDs, days;
        if (s.tab === 'dates') {
            if (!s.selStart) return;
            startDs = s.selStart;
            const endDs = s.selEnd || s.selStart;
            days = Math.round((new Date(endDs + 'T12:00:00') - new Date(startDs + 'T12:00:00')) / 86400000) + 1;
        } else {
            days = CAL_DUR_DAYS[s.dur] || 7;
            let startMs = todayNoonMs();
            if (!s.all6) {
                const first = calFlexMonths().find(m => s.months[m.key]);
                if (first) startMs = Math.max(startMs, new Date(first.y, first.m, 1, 12).getTime());
            }
            if (s.dur === 'weekend') startMs += ((5 - new Date(startMs).getDay() + 7) % 7) * 86400000;
            startDs = fmtDateLocal(startMs);
        }
        _state.mode = 'custom';
        _state.startDate = startDs;
        _state.customDays = Math.max(1, Math.min(maxCustomDays(), days));
        const dEl = document.getElementById('aloc-date'); if (dEl) dEl.value = startDs;
        const daysEl = document.getElementById('aloc-days'); if (daysEl) daysEl.value = String(_state.customDays);
        syncYearSliderFromDate();
        syncModeUI();
        window.alocCalClose();
        recompute();
    };

    const dateEl = document.getElementById('aloc-date');
    if (dateEl) dateEl.value = todayStr;
    syncYearSliderFromDate();
    syncModeUI();

    setupMap();
    recompute();
}
