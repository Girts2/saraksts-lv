import { MOON_SIGNS, NAKSHATRAS } from './vedic_interpretations.js?v=8';

export const CHINESE_DAY_MASTERS = {
    "Jan_Koks": { trait: "Līderis-pionieris", profile: "Stāvs, spēcīgs, grūti lokāms. Augsta izturība, bet mēdz 'salūzt' pie pārslodzes.", stress: "izjūt pilnīgu emocionālu apstāšanos, kad pārslodze pārsniedz slieksni — nespēj mainīt virzienu, bet nevar arī turpināt", strategy: "Tieša konfrontācija. Viņi neprot atkāpties." },
    "In_Koks": { trait: "Diplomāts-vītenis", profile: "Pielāgojas apstākļiem. Prot atrast ceļu ap jebkuru šķērsli, lai sasniegtu virsotni.", stress: "kļūst vēl elastīgāks ārēji, bet iekšēji zaudē sevi — meklē jaunus atbalsta punktus tik ātri, ka aizmirst par savām vajadzībām", strategy: "Izmantojiet viņu vajadzību pēc atbalsta (struktūras)." },
    "Jan_Uguns": { trait: "Saule/Gaisma", profile: "Atklāts, impulsīvs, visiem redzams. Emocijas izpauž publiski.", stress: "reaģē publiski un intensīvi — emocijas izsprāgst uz āru, bieži arī situācijās, kur to labāk nebūtu darīt", strategy: "Pabarojiet viņa ego ar publisku uzmanību." },
    "In_Uguns": { trait: "Svece/Liesma", profile: "Emocionāli svārstīgs, bet ļoti inteliģents. Spēj pārsteigt ar negaidītu reakciju.", stress: "var pārsteigt ar asu un negaidītu emocionālu reakciju — saasināta intuīcija pārvēršas aizkaitinājumā vai aukstā distancēšanās", strategy: "Uzmanieties no viņa intuīcijas uzliesmojumiem." },
    "Jan_Zeme": { trait: "Kalns", profile: "Konservatīvs, lēns, uzticams. Gandrīz neiespējami izkustināt no vietas.", stress: "noslēdzas pilnībā un kļūst komunikatīvi nesasniedzams — atteikums no sarunas ir galvenā aizsardzības reakcija", strategy: "Ilgtermiņa aplenkums. Viņš nereaģē uz ātrām provokācijām." },
    "In_Zeme": { trait: "Augsne", profile: "Rūpīgs, barojošs, bet mēdz 'noslīkt' detaļās un citu problēmās.", stress: "zaudē sevi citu vajadzībās un kļūst emocionāli izsmelts, neapzinoties, ka paša robežas jau sen pārkāptas", strategy: "Ietekmējams caur ģimenes vai kolektīva labklājību." },
    "Jan_Metāls": { trait: "Zobens", profile: "Ass, loģisks, disciplinēts. Emocijas uzskata par vājumu.", stress: "kļūst vēl bargāks un noslēgtāks — emocionālā stingrība un prasīgums pret sevi un citiem pieaug līdz neizturamas robežas", strategy: "Izmantojiet loģiskus argumentus un dzelžainu disciplīnu." },
    "In_Metāls": { trait: "Dārgakmens", profile: "Mīl luksusu un prestižu. Svarīgs ārējais mirdzums.", stress: "kļūst apsēsts ar ārējo tēlu un sabiedrisko atzinību — rūpes par statusu aizstāj reālu problēmu risināšanu", strategy: "Viegli ietekmējams, apdraudot sociālo statusu vai materiālos ieguvumus." },
    "Jan_Ūdens": { trait: "Okeāns", profile: "Milzīga enerģija, neparedzams. Spēj 'pārpludināt' jebkuru sistēmu.", stress: "kļūst pilnīgi neparedzams un nekontrolējams — enerģija izplūst visos virzienos bez skaidra fokusa vai mērķa", strategy: "Nemēģiniet apturēt – mēģiniet ievirzīt viņa enerģiju citā gultnē." },
    "In_Ūdens": { trait: "Rasa/Lietus", profile: "Ļoti uztvērīgs un izveicīgs. Iekļūst jebkurā situācijā un redz cauri virsmas slānim.", stress: "iegremdējas savā iekšējā pasaulē un kļūst emocionāli nesasniedzams — aiziet dziļi zem virsmas un pagaidām pārtrauc kontaktu", strategy: "Viņu nevar noķert ar spēku, tikai ar intelektuālu spēli." },
    "Nezināms": { trait: "Nezināms", profile: "Trūkst datu.", stress: "Nav datu", strategy: "Nezināms" }
};

export const MAYAN_SEALS = {
    "Imix": { name: "Pūķis", drive: "Sākotne un uzturēšana. Spēcīgs mātes/tēva instinkts organizācijā." },
    "Ik": { name: "Vējš", drive: "Komunikācija un gars. Spēja pārliecināt masas ar vārdiem." },
    "Akbal": { name: "Nakts", drive: "Iekšējā pasaule un intuīcija. Izcili darbam ar zemapziņu un slepenību." },
    "Kan": { name: "Sēkla", drive: "Izaugsme un precizitāte. Cilvēks-plānotājs, kurš gaida īsto brīdi." },
    "Chicchan": { name: "Čūska", drive: "Instinkts un dzīvības spēks. Fiziski bīstams, izteikta reakcija." },
    "Cimi": { name: "Nāve", drive: "Atbrīvošanās un hierarhija. Spēja pieņemt nežēlīgus, bet nepieciešamus lēmumus." },
    "Manik": { name: "Briedis", drive: "Realizācija un dziedināšana. Prasmīgs instrumentu un resursu lietotājs." },
    "Lamat": { name: "Zvaigzne", drive: "Estētika un harmonija. Spēj saskatīt sistēmas skaistumu vai kļūdu." },
    "Muluc": { name: "Ūdens", drive: "Emocionālā attīrīšana. Spēj nolasīt kolektīva noskaņojumu." },
    "Oc": { name: "Suns", drive: "Lojalitāte un sirds. Ideāls komandas biedrs, bet viegli vadāms." },
    "Chuen": { name: "Pērtiķis", drive: "Spēle un ilūzija. Izcils dezinformators un aktieris." },
    "Eb": { name: "Cilvēks", drive: "Griba un gudrība. Ļoti spēcīgs pašdisciplīnas kods." },
    "Ben": { name: "Debesu gājējs", drive: "Pētniecība un telpas paplašināšana. Spiegs, kurš iekļūst jebkur." },
    "Ix": { name: "Jaguārs", drive: "Slepenība un maģija. Darbojas naktī vai pilnīgā izolācijā." },
    "Men": { name: "Ērglis", drive: "Vīzija un prāts. Redz kopbildi no liela augstuma." },
    "Cib": { name: "Kariote", drive: "Iekšējā balss un autoritāte. Ticība savai nemaldībai." },
    "Caban": { name: "Zeme", drive: "Evolūcija un kustība. Spēj izraisīt lielas pārmaiņas sociālajā vidē." },
    "Etznab": { name: "Nazis", drive: "Patiesība un griešana. Bez žēlastības atmasko melus un vājības." },
    "Cauac": { name: "Vētra", drive: "Pašģenerācija. Spēj darboties pie maksimālas slodzes un stresa." },
    "Ahau": { name: "Saule", drive: "Apziņa un pilnība. Dabiskais līderis, kuru visi ievēro." }
};

export const TOLTEC_PROFILES = {
    "Tonal_Stabils": "Cilvēks-sistēma. Viņa dzīve ir sakārtota, viņš ir prognozējams. Vājība: Bailes no nezināmā.",
    "Tonal_Haotisks": "Cilvēks-improvizācija. Viņš ir neprognozējams, bet viegli nogurst no paša radītā haosa.",
    "Nagual_Sapņotājs": "Viņa spēks ir idejās un vīzijās. Viņu var ietekmēt caur viņa 'sapņiem'.",
    "Nagual_Vērotājs": "Viņš visu redz, bet maz runā. Viņu nevar pārsteigt nesagatavotu."
};

export function generateHybridIntelligenceReport(baziData, mayaData, vedicData) {
    // 1. Day Master matching from Bazi calculations
    const bPol = baziData.Daymaster.polarity === "Jaņ" ? "Jan" : "In";
    const dmKey = `${bPol}_${baziData.Daymaster.element}`;
    const dmData = CHINESE_DAY_MASTERS[dmKey] || CHINESE_DAY_MASTERS["Nezināms"];

    // 2. Mayan Seal matching pēc zīmoga INDEKSA (MAYAN_SEALS atslēgas ir tajā pašā
    // Tzolkin secībā kā maya_toltec.js SIGNS masīvs). Salīdzināt pēc VĀRDA nekad
    // nestrādāja — MAYAN_SEALS[key].name ir latviešu arhetipa nosaukums ("Pūķis"),
    // bet person_data.sign ir transliterētais dienas-zīmes nosaukums ("Imiš (Imix)").
    const sealKeys = Object.keys(MAYAN_SEALS);
    const sealData = MAYAN_SEALS[sealKeys[mayaData.person_data.sign_idx]]
        || { name: mayaData.person_data.sign, drive: "Nezināms dzinējspēks" };

    // 3. Vedic matching
    const moonData = MOON_SIGNS[vedicData.moon.sign] || { risk: '—', filter: '—', projection: null };
    // nakshatra index was +1 so we need it as what it is in vedicData
    const naksData = NAKSHATRAS[vedicData.moon.nakshatra] || { name: "Nezināma Nakšatra", strategy: "Nav datu" };
    const saturnData = vedicData.saturn;
    const saturnSigns = {
        0: "Aunā", 1: "Vērsī", 2: "Dvīņos", 3: "Vēzī", 4: "Lauvā", 5: "Jaunavā", 
        6: "Svaros", 7: "Skorpionā", 8: "Strēlniekā", 9: "Mežāzī", 10: "Ūdensvīrā", 11: "Zivīs"
    };

    return {
        // Hibrīda ziņojuma punkti
        deepMotivation: `Subjekta fundamentālā dzinējspēka pamatā ir Maiju ${sealData.name} kods (${sealData.drive}). Tas nozīmē, ka viņa rīcība nav tikai situatīva, bet gan dziļi sakņota nepieciešamībā pēc šī mērķa realizācijas.`,

        executionCapacity: `Operatīvā kapacitāte un izturība: ${dmData.profile} Apvienojumā ar Vēdu ${naksData.name} nakšatru, subjekts izmanto šādu stratēģiju: ${naksData.strategy}`,

        vulnerabilityMatrix: `Kritiskais vājums: ${moonData.risk} Ieteicamā ietekmēšanas metode (Strategēma): ${dmData.strategy}`,
        jungVulnerability: `Augsta stresa brīdī cilvēks ${dmData.stress}. Dziļākais ievainojamības punkts: ${moonData.risk}`,

        behavioralParadox: `Paradokss: Ārēji subjekts darbojas kā ${dmData.trait}, taču emocionālā līmenī viņu vada ${moonData.filter}`,
        
        systemVulnerability: `Savienojot Vēdu Saturnu ${saturnSigns[saturnData.sign]} ar Ķīniešu [${bPol}_${baziData.Daymaster.element}] Day Master (${dmData.trait}), redzam, ka subjekts ir visneaizsargātākais strukturētā haosā vai kad tiek iedragāts viņa ${dmData.profile.split('.')[0].toLowerCase()}.`
    };
}
