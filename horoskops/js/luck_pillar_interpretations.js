// BaZi Veiksmes Pīlāru interpretācijas
// ──────────────────────────────────────
// Katrs Luck Pillar = stem + branch + 10-Dieva attiecība pret personas Daymaster.
// Šis modulis pārveido tehnisko datumu (piem., "Wu Hai · 43-52 gadi") par
// stratēģisku biznesa naratīvu: kas apgādājas ar enerģiju, kā strādāt ar šo
// fāzi efektīvi un kādas kļūdas ir nepieciešams izvairīties.

import { calculateTenGods } from './bazi.js?v=12';

// ── 10 Debesu Stumbri (天干) — debesu enerģijas raksturs ─────────────────────

export const STEM_MEANINGS = {
    Jia:  { symbol: "Lielais koks (ozols)", character: "Strukturāla izaugsme un pionieris — laiks atvērt jaunus virzienus, dibināt platformas, uzņemties vadošo iniciatīvu" },
    Yi:   { symbol: "Vītne, zāle",           character: "Pielāgojamība un sociāla tīklošanās — smalka pārliecināšana, sadarbības meklēšana, izaugsme caur citiem" },
    Bing: { symbol: "Saule",                 character: "Pārredzamība un harizma — publiska klātbūtne, ietekmes paplašināšana, atklāta vadība" },
    Ding: { symbol: "Sveces liesma",         character: "Iedvesma un precīza fokusa enerģija — mentorēšana, individuāla apmācība, jutīga ietekme uz mazu grupu" },
    Wu:   { symbol: "Kalns, cietoksnis",     character: "Stabilitāte un pamatojums — ilgtermiņa platformas, drošības veidošana, izaugsme caur pacietību" },
    Ji:   { symbol: "Lauks, augsne",         character: "Rūpīga audzēšana un detaļas — mērena, soli pa solim izaugsme, pamatu uzturēšana, pakāpenisks rezultāts" },
    Geng: { symbol: "Kara cirvis",           character: "Lēmumu pieņemšana un autoritāte — atdalīšana no liekā, struktūru veidošana, taisnīgums pār komfortu" },
    Xin:  { symbol: "Pulēta rota",           character: "Smalka kvalitāte un prestižs — augstvērtīgi produkti, prezentācijas precizitāte, reputācijas slīpēšana" },
    Ren:  { symbol: "Okeāns",                character: "Stratēģiska redze un plūsma — lielas sistēmas, ilgtermiņa kustība, neapstājama virzīšanās uz priekšu" },
    Gui:  { symbol: "Lietus",                character: "Adaptācija un slēpta iedvesma — intuīcija, neredzami procesi, ietekme caur klusumu un pacietību" }
};

// ── 12 Zemes Zari (地支) — zemes enerģijas raksturs ──────────────────────────

export const BRANCH_MEANINGS = {
    Zi:   { latvian: "Žurka",   character: "Sākumu enerģija un slēptais potenciāls — ideju ģenerēšana, nakts darbs, neredzami sākumi, kas vēlāk uzplauks" },
    Chou: { latvian: "Vērsis",  character: "Lēna, pamatīga uzkrāšana — izturība, lauksaimnieciska disciplīna, resursu konsolidācija pirms lielā soļa" },
    Yin:  { latvian: "Tīģeris", character: "Strauja izaugsme un drosme — jaunu virzienu atvēršana, agresīva ekspansija, pirmā soļa enerģija" },
    Mao:  { latvian: "Trusis",  character: "Sociāla tīklošanās un daiļums — mīksta diplomātija, sadarbības veidošana, reputācijas izveide caur attiecībām" },
    Chen: { latvian: "Pūķis",   character: "Transformācija un varas pacelšana — slēptie resursi, dziļas pārmaiņas, varas pozīcijas pārkārtošana" },
    Si:   { latvian: "Čūska",   character: "Stratēģija un intelekts — slēptas ietekmes spēles, asprātīgi plāni, garais skats uz tirgu" },
    Wu:   { latvian: "Zirgs",   character: "Maksimālā enerģija un sasniegumu fāze — publiska aktivitāte, ātri rezultāti, redzamība virsotnē" },
    Wei:  { latvian: "Kaza",    character: "Mākslinieciskā briedums un kompromisi — mīksta vadība, jutīgas attiecības, kvalitātes pilnveidošana" },
    Shen: { latvian: "Pērtiķis", character: "Adaptācija un asprātīgs risinājums — biznesa darījumi, ātra reakcija, taktiska veiklība" },
    You:  { latvian: "Gailis",  character: "Precizitāte un profesionāla atzīšana — struktūras pārbaude, kvalitātes kontrole, eksperta sertifikācija" },
    Xu:   { latvian: "Suns",    character: "Lojalitāte un aizsardzība — drošības un aizsardzības lomas, ētiskās robežas, pretrunu nostiprināšana" },
    Hai:  { latvian: "Cūka",    character: "Resursu uzkrāšana un miers — intuitīvi lēmumi, mājīga drošība, slēptas iespējas mierīgā fāzē" }
};

// ── 10 Dievu Stratēģijas — kā strādāt ar fāzē apgādāto enerģiju ──────────────

export const GOD_STRATEGIES = {
    Friend: {
        label: "Komandas partneris",
        phase: "Sadarbības un partnerības fāze",
        focus: "Aliansu veidošana, resursu dalīšana un līdzdibinātāju meklēšana — kolektīvā rīcība šajā periodā nes vairāk nekā solo cīņa",
        avoid: "Atklāta konkurence ar līdziniekiem; mēģinājumi visu izdarīt vienam; aizvainojums, ka citiem tiek tas pats"
    },
    Rob_Wealth: {
        label: "Tirgus konkurents / Inovators",
        phase: "Mēroga un resursu apguves fāze",
        focus: "Drosmīga ekspansija, aktīva pārdošana, sportiska konkurence — kapacitāte ir paaugstināta, bet resursu sadalījums stingri jāuzrauga",
        avoid: "Bezatbildīga azartspēle; partneru paļaušanās bez līgumiem; finansiālie konflikti ar tuviniekiem vai brāļiem"
    },
    Eating_God: {
        label: "Dziļās meistarības eksperts",
        phase: "Ekspertīzes un radošuma fāze",
        focus: "Dziļā ekspertīze, autorprojekti, apmācības, grāmatas, kvalitatīva produkta radīšana — laiks 'cept savu maizi' nevis tikai pildīt cita receptes",
        avoid: "Pārmērīga komforta zonas izvēle; izvairīšanās no nepieciešamiem grūtiem lēmumiem; pārāk ilga 'gatavošanās' bez publiska izlaiduma"
    },
    Hurting_Officer: {
        label: "Ideju virzītājs / Inovators",
        phase: "Ideju prezentēšanas un pārmaiņu fāze",
        focus: "Radošums, autoritāšu apšaubīšana, nekonvencionāli risinājumi, mākslinieciska izpausme — sistēmas ir lokanas, un to var izmantot",
        avoid: "Tieši konflikti ar priekšniekiem, regulatoriem un likumdevējiem; impulsīva reputācijas riska uzņemšanās publiskās platformās"
    },
    Indirect_Wealth: {
        label: "Iespēju stratēģis",
        phase: "Iespēju vadības un investīciju fāze",
        focus: "Investīcijas, biznesa darījumi, nekustamais īpašums, blakus ienākumi, 'svešas naudas' kalvēšana — risks apzināti atmaksājas",
        avoid: "Pilnīga paļaušanās uz algoto darbu; netradicionālu peļņas iespēju ignorēšana; pārāk konservatīvs portfelis"
    },
    Direct_Wealth: {
        label: "Operacionālais pragmātiķis",
        phase: "Operacionālā pragmatisma un finanšu fāze",
        focus: "Algotais darbs, ilgtermiņa kontrakti, finanšu disciplīna, uzkrājumi un budžetu plānošana — laiks nostiprināt bāzi nevis riskēt",
        avoid: "Spekulatīvas investīcijas; lielas hipotēkas; finansiālā impulsivitāte; aizdevumi 'draugu uzņēmumiem'"
    },
    Seven_Killings: {
        label: "Krīžu vadītājs / Taktiskais līderis",
        phase: "Krīžu vadības un operativitātes fāze",
        focus: "Augsta riska iespējas, krīžu menedžments, sacensība, militārā/policijas/glābšanas karjera — naudas kalve caur konfrontāciju",
        avoid: "Solo darbs bez atbalsta sistēmas; brīdinājumu par fizisko un emocionālo izsīkumu ignorēšana; vienlaicīga vairāku kauju vešana"
    },
    Direct_Officer: {
        label: "Sistēmas un procesu vadītājs",
        phase: "Procesu pārvaldības un kvalitātes fāze",
        focus: "Korporatīvā izaugsme, vadošu amatu pieņemšana, formālas atzīšanas iegūšana, autoritātes nostiprināšana — sistēma atbalsta kāpienu",
        avoid: "Nepiemērotu solo projektu sākšana; autoritāšu izaicināšana bez gatava plāna; protokolu ignorēšana"
    },
    Indirect_Resource: {
        label: "Stratēģiskais analītiķis",
        phase: "Stratēģiskās analīzes un pētniecības fāze",
        focus: "Pētniecība, ezotēriski lauki, mentora atrašana, alternatīvas metodes, teorija pār praksi — laiks dziļi mācīties pirms producēt",
        avoid: "Pāragra komercializācija; sociāla izolācija; intelektuāls 'plīvurs', kas neļauj rezultātu izlaist publikai"
    },
    Direct_Resource: {
        label: "Zināšanu pārvaldnieks",
        phase: "Sistemātiskās drošības un faktu fāze",
        focus: "Formāla izglītība, sertifikācijas, atbalsta sistēmas meklēšana, mājas un mātes lomas stiprināšana — lēna, droša izaugsme",
        avoid: "Pārmērīga atkarība no autoritātēm; pasivitāte savas izaugsmes ziņā; pārāk ilga 'studenta' loma"
    }
};

// ── Galvenā interpretācijas funkcija ─────────────────────────────────────────

export function interpretLuckPillar(pillar, daymaster) {
    if (!pillar || !pillar.stem || !daymaster) return null;

    const stemObj = (typeof pillar.stem === 'object') ? pillar.stem : null;
    if (!stemObj) return null;

    const branchKey = (typeof pillar.branch === 'string')
        ? pillar.branch.split(' ')[0]
        : (pillar.branch?.name || pillar.branch);

    const stemMeta   = STEM_MEANINGS[stemObj.name]   || { symbol: '—', character: '—' };
    const branchMeta = BRANCH_MEANINGS[branchKey]    || { latvian: '—', character: '—' };
    const godKey     = calculateTenGods(daymaster, stemObj);
    const strategy   = GOD_STRATEGIES[godKey]        || { label: godKey || '—', phase: '—', focus: '—', avoid: '—' };

    return {
        stemName:    stemObj.name,
        stemSymbol:  stemMeta.symbol,
        stemCharacter: stemMeta.character,
        branchKey,
        branchLatvian: branchMeta.latvian,
        branchCharacter: branchMeta.character,
        polarity:    stemObj.polarity,
        element:     stemObj.element,
        godKey,
        godLabel:    strategy.label,
        phase:       strategy.phase,
        focus:       strategy.focus,
        avoid:       strategy.avoid
    };
}
