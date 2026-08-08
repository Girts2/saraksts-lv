// horoskops/js/logic/kuta_descriptions.js
// Ashta Kuta apraksti ikdienas valodā — bez astroloģijas žargona
// Katrai Kutai 3 slāņi: summary, detail, practice

export const KUTA_DESCRIPTIONS = {

    // ── 1. VARNA ─────────────────────────────────────────────────────────────
    varna: {
        title: 'Dzīves virziens',
        subtitle: 'Vai jūs tiecaties uz līdzīgiem mērķiem?',
        desc(score, max, { v1, v2, vM, vF } = {}) {
            if (score === 1) return {
                summary: 'Jūsu dzīves ambīcijas ir saskaņotas vai vīrietis ir vērsts augstāk.',
                detail: 'Jūs abi intuitīvi saprotiet, ko nozīmē "panākumi" un "jēgpilna dzīve". Pat ja ceļi atšķiras, pamatvirziens ir kopīgs — tas samazina slepenu spriedzi par to, kurš upurējas vairāk.',
                practice: 'Lēmumi par karjeru, pārcelšanos vai dzīvesveida maiņu tiek pieņemti vieglāk, jo abi redzat vienu horizontu.'
            };
            // Viendzimuma pāris (0.5): klasiskās līgavainis/līgava lomas nav piemērojamas —
            // neitrāls teksts bez apgalvojuma, kurš ir "augstāk".
            if (score === 0.5) return {
                summary: 'Jūsu dzīves ambīciju virzieni daļēji atšķiras.',
                detail: `Jūsu Mēness zīmes norāda uz atšķirīgu "sabiedrisko virzību" (${v1 || '—'} ↔ ${v2 || '—'}). Vienam no jums statusa un izaugsmes jautājumi var būt svarīgāki nekā otram — tas nav šķērslis, bet atšķirība, ko vērts apzināties.`,
                practice: 'Svarīgi atklāti runāt par katra prioritātēm — karjeru, ambīcijām, vēlmi pēc statusa vai miera. Bez šīs sarunas veidojas klusas cerības, kas vēlāk pārvēršas pārmetumos.'
            };
            return {
                summary: 'Jūsu dzīves ambīciju virzieni būtiski atšķiras.',
                detail: `${vF || v2 || 'Sievietes'} Mēness zīme norāda uz augstāku "sabiedrisko virzību" nekā ${vM || v1 || 'vīrieša'}. Tas var radīt situācijas, kur vīrietis jūtas nesaprasts savos mērķos vai sieviete — nepamanīta savā izaugsmē.`,
                practice: 'Svarīgi atklāti runāt par katra prioritātēm — karjeru, ambīcijām, vēlmi pēc statusa vai miera. Bez šīs sarunas veidojas klusas cerības, kas vēlāk pārvēršas pārmetumos.'
            };
        }
    },

    // ── 2. VASYA ─────────────────────────────────────────────────────────────
    vasya: {
        title: 'Dabiskā pievilcība',
        subtitle: 'Vai jūs dabiski gravitējat viens pret otru?',
        desc(score, max, { g1, g2 } = {}) {
            if (score === 2) return {
                summary: 'Spēcīga dabiska pievilcība un harmoniska mijiedarbība.',
                detail: 'Jūs vienkārši "plūstat" kopā — nav vajadzīgs īpašs pūlis, lai justos labi viens otra klātbūtnē. Tas izpaužas kā viegli sarunas, kopīga komforta sajūta un tas, ka otra cilvēka klātbūtne remdē, nevis nogurdina.',
                practice: 'Šis faktors palīdz pārvarēt grūtus periodus — pat konflikta laikā paliek kāda magnētiska saikne, kas ved atpakaļ.'
            };
            if (score === 1) return {
                summary: 'Mērena pievilcība — harmonija pastāv, bet prasa apzinātu uzturēšanu.',
                detail: 'Jūs abi varat justies labi kopā, taču tas nenotiek pilnīgi automātiski. Dažreiz viens no partneriem jūtas "vairāk ieguldošais" vai ir jāpielāgojas otra ritmam.',
                practice: 'Kopīgas tradīcijas — vakariņas, pastaiga, rutīna — palīdz uzturēt saikni, kad spontānā ķīmija pagurst.'
            };
            return {
                summary: 'Dabiskā pievilcība ir vāja — saikne jāveido apzināti.',
                detail: `${g1 || 'Jūsu'} un ${g2 || 'partnera'} enerģijas ir ļoti atšķirīgas — otra cilvēka klātbūtne dažkārt var just kā traucējoša, ne ļauni, vienkārši cita viļņa garuma.`,
                practice: 'Kopīgas aktivitātes un rutīnas palīdz veidot saikni. Apziniet, kas katram dod enerģiju, un respektējiet atšķirīgās vajadzības pēc tuvības.'
            };
        }
    },

    // ── 3. TARA ──────────────────────────────────────────────────────────────
    tara: {
        title: 'Liktenis kopā',
        subtitle: 'Vai dzīve jums pienes vai atņem, esot kopā?',
        desc(score, max, { fwd, bwd } = {}) {
            if (score === 3) return {
                summary: 'Abpusēji labvēlīgs liktenis — jūs abi izaugat kopā.',
                detail: 'Gan vīrietis, gan sieviete atrodas labvēlīgā pozīcijā attiecībā pret otra nakšatru. Attiecības veicina katra personīgo izaugsmi, ne tikai kopējo labklājību.',
                practice: 'Kopīgi lēmumi — pārcelšanās, investīcijas, bērni — biežāk nes labus augļus nekā vidēji. Pamatplūsma ir uz priekšu.'
            };
            if (score === 1) return {
                summary: 'Vienpusējs labvēlīgums — vienam klājas labāk attiecībās nekā otram.',
                detail: 'Viens partneris atrodas labvēlīgā pozīcijā, otrs — ne. Var rasties sajūta, ka viens vienmēr "dod vairāk" vai ka attiecības vienai pusei nes vairāk iespēju.',
                practice: 'Sekojiet, vai abi jūtaties vienlīdz piepildīti. Atklātas sarunas par to, kas katram dod un ko paņem, novērš uzkrātu neapmierinātību.'
            };
            return {
                summary: 'Zvaigžņu plūsma nav labvēlīga — attiecības prasa papildu pūles.',
                detail: 'Neviena nakšatru pozīcija nav labvēlīga. Tas nenozīmē, ka attiecības ir lemtas neveiksmei, bet kopīgā dzīve prasīs apzinātāku darbu.',
                practice: 'Regulāra "attiecību higiēna" — pateicības prakse, kopīgi mērķi, terapija — palīdz kompensēt šo faktoru.'
            };
        }
    },

    // ── 4. YONI ──────────────────────────────────────────────────────────────
    yoni: {
        title: 'Fiziskā ķīmija',
        subtitle: 'Cik dziļa ir jūsu fiziskā un instinktīvā saikne?',
        desc(score, max, { a1, a2 } = {}) {
            if (score === 4) return {
                summary: 'Izcila fiziskā ķīmija — dziļa, instinktīva saikne.',
                detail: 'Jūsu simboliskie dzīvnieki ir vienādi — tas ir augstākais Yoni rezultāts. Fiziskā un emocionālā tuvība nāk dabiski, ķermenis "atpazīst" otru.',
                practice: 'Fiziskā intimitāte ir šo attiecību stiprā puse. Pat spriedzes periodos ķermeniskā saikne palīdz atjaunot emocionālo tuvību.'
            };
            if (score === 3) return {
                summary: 'Laba fiziskā saderība — dabiski draudzīga enerģija.',
                detail: 'Jūsu simboliskie dzīvnieki ir dabiski draudzīgs pāris. Fiziskā tuvība ir mīļa un ērta.',
                practice: 'Šīs attiecības bieži ir siltuma un drošības avots — kā ar labu draugu, kas vienlaikus ir arī partneris.'
            };
            if (score === 2) return {
                summary: 'Neitrāla fiziskā ķīmija — saderīgi, bet bez īpašas dzirksts.',
                detail: `${a1 || 'Jūsu'} un ${a2 || 'partnera'} simboli ir neitrāli — nav dabiskas pretrunas, bet arī nav spēcīgas vilkmes.`,
                practice: 'Fiziskā intimitāte var prasīt apzinātu uzturēšanu. Kopīgas fiziskas aktivitātes veido tuvumu, kas ne vienmēr nāk spontāni.'
            };
            return {
                summary: 'Fiziskā ķīmija ir grūta — instinktīva nesaderība.',
                detail: `${a1 || 'Jūsu'} un ${a2 || 'partnera'} simboli ir dabiski "pretinieki". Tas var izpausties kā instinktīva spriedze vai neizskaidrojama kairinājuma sajūta.`,
                practice: 'Palīdz skaidra komunikācija par fiziskām vajadzībām un robežām, kā arī atsevišķa personīgā telpa.'
            };
        }
    },

    // ── 5. GRAHA MAITRI ──────────────────────────────────────────────────────
    maitri: {
        title: 'Prātu saderība',
        subtitle: 'Vai jūs saprotat viens otra domāšanas veidu?',
        desc(score, max, { l1, l2 } = {}) {
            if (score === 5) return {
                summary: 'Izcila intelektuālā saderība — domāt kopā ir viegli.',
                detail: 'Jūs intuitīvi saprotat, kā otrs apstrādā informāciju, pieņem lēmumus un reaģē uz stresu. Pat nesaskaņojoties, gandrīz vienmēr sapratīsiet, kāpēc otrs domā tā.',
                practice: 'Garās sarunas, kopīgi plāni un lēmumu pieņemšana noritēs viegli. Šis faktors padara jūs par labu komandu.'
            };
            if (score === 4) return {
                summary: 'Laba intelektuālā saderība — viens draudzīgs, otrs neitrāls.',
                detail: 'Vienam partnerim otras domāšana ir dabiska, otram — saprotama, bet ne vienmēr tuva. Ir brīži, kad justos "dažādu viļņu garumā".',
                practice: 'Aktīva klausīšanās palīdz — nevis gaidi savu kārtu runāt, bet tiešām interesējies, kā otrs nonāca pie sava secinājuma.'
            };
            if (score === 3) return {
                summary: 'Neitrāla intelektuālā saderība — funkcionāla, bet ne iedvesmojoša.',
                detail: 'Jūsu domāšanas veidi ir pietiekami līdzīgi lieliem pārpratumiem, bet nav pietiekami tuvi, lai radītu intelektuālu dzirksti.',
                practice: 'Kopīgas intereses vai hobiji, kur abi mācāties kaut ko jaunu, var radīt intelektuālo tuvību.'
            };
            if (score === 1) return {
                summary: 'Intelektuālā saderība ir sarežģīta — viens draudzīgs, otrs naidīgs.',
                detail: 'Vienam otras domāšana šķiet sveša vai kaitinoša, otram — pievilcīga. Tas rada nevienlīdzīgu dinamiku sarunās.',
                practice: 'Noderīgi vienoties par "neitrāliem laukiem" — tēmām, kur abi jūtaties droši, lai saglabātu savstarpējo cieņu.'
            };
            return {
                summary: 'Intelektuālā saderība ir ļoti zema — domāšanas veidi konfliktē.',
                detail: 'Var rasties pastāvīga sajūta, ka otrs "nesaprot" vai "domā nepareizi" — pat par sīkumiem.',
                practice: 'Skaidri nodaliet lēmumu pieņemšanas zonas. Mēģinājumi pārliecināt viens otru bieži noved pretējā rezultātā.'
            };
        }
    },

    // ── 6. GANA ──────────────────────────────────────────────────────────────
    gana: {
        title: 'Temperaments un dzīves temps',
        subtitle: 'Vai jūsu iekšējais ritms ir saderīgs?',
        desc(score, max, { g1, g2 } = {}) {
            if (score === 6) return {
                summary: 'Ideāli saderīgs temperaments — jūs esat vienā ritmā.',
                detail: 'Jūsu dabiskais "dzīves temps" — cik ātri pieņemat lēmumus, cik intensīvi pārdzīvojat emocijas, cik svarīga ir kārtība — ir ļoti līdzīgs.',
                practice: 'Jūs retāk nonāksiet situācijās, kur viens grib mīlēt klusi un otrs — skaļi. Tas ir viens no visnovērtētākajiem saderības faktoriem ikdienā.'
            };
            if (score === 5) return {
                summary: 'Ļoti laba temperamenta saderība — nelielas, viegli pārvaramas atšķirības.',
                detail: 'Viens ir mierīgāks un strukturētāks, otrs — elastīgāks un emocionālāks. Šīs atšķirības bieži papildina viena otru.',
                practice: 'Mierīgākais partneris var kalpot kā enkurs, emocionālākais — kā vēja lāde. Ja abi to apzinās, tā ir priekšrocība.'
            };
            if (score === 1) return {
                summary: 'Temperamenta atšķirības ir ievērojamas — prasa lielu savstarpēju pieļāvību.',
                detail: `${g1 || 'Viens'} un ${g2 || 'otrs'} temperaments ikdienas ritmā atšķiras būtiski. Viens var šķist pārāk intensīvs, otrs — pārāk pasīvs.`,
                practice: 'Galvenais jautājums nav "kas ir pareizi", bet "kā mēs varam dzīvot blakus, nepieprasot, ka otrs mainās".'
            };
            return {
                summary: 'Temperamenta nesaderība ir nopietna — fundamentāli atšķirīgi ritmi.',
                detail: `${g1 || 'Jūsu'} un ${g2 || 'partnera'} enerģijas bieži konfliktē ikdienā — cik skaļi svinēt, kā reaģēt uz konfliktu, cik daudz laika pavadīt atsevišķi.`,
                practice: 'Atsevišķa personīgā telpa (laiks, hobiji, draugi) nav greznība — tā ir nepieciešamība. Bez tās viens ilgtermiņā zaudēs sevi.'
            };
        }
    },

    // ── 7. BHAKUT ────────────────────────────────────────────────────────────
    bhakut: {
        title: 'Kopīgā plūsma',
        subtitle: 'Vai attiecības nes labklājību un emocionālu bagātību?',
        desc(score, max, { rashi1, rashi2, rel } = {}) {
            if (score === 7) {
                // calcBhakut dod 7pt gan maksimālajām (1/1, 7/7), gan labvēlīgajām (3/11, 4/10)
                // pozīcijām. Tikai 1/1 un 7/7 ir patiesi "vislabvēlīgākās"; pārējās — labas, ne maksimums.
                const isPeak = rel === '1/1' || rel === '7/7';
                if (isPeak) return {
                    summary: 'Izcila kopīgā plūsma — attiecības bagātina abus.',
                    detail: 'Jūsu Mēness zīmes atrodas vislabvēlīgākajā savstarpējā pozīcijā (1/1 vai 7/7). Kopīgā dzīve nes emocionālu pārpilnību un sajūtu, ka "kopā ir labāk nekā atsevišķi".',
                    practice: 'Kopīgi lēmumi parasti nāk pie labiem rezultātiem. Šis faktors "pavelk augšup" visas pārējās attiecību jomas.'
                };
                return {
                    summary: 'Laba kopīgā plūsma — labvēlīga zīmju attiecība.',
                    detail: `${rashi1 || 'Jūsu'} un ${rashi2 || 'partnera'} zīmes ir ${rel || '3/11 vai 4/10'} pozīcijā — labvēlīga, kas veicina sadarbību un kopīgu progresu, kaut tas nav absolūtais maksimums (1/1 vai 7/7).`,
                    practice: 'Attiecības nes augļus īpaši tad, kad strādājat kopīgu mērķu virzienā — ne tikai "esiet kopā", bet "veidojiet kaut ko kopā".'
                };
            }
            return {
                summary: 'Bhakuta Dosha — kopīgā plūsma ir apgrūtināta.',
                detail: `${rashi1 || 'Jūsu'} un ${rashi2 || 'partnera'} zīmes ir ${rel || 'nelabvēlīgā'} pozīcijā. Var rasties emocionāla distancēšanās vai sajūta, ka "kopā ir grūtāk nekā atsevišķi".`,
                practice: 'Regulāra "attiecību inventarizācija" — ko esam sasnieguši, ko vēlamies — palīdz uzturēt kopīgās plūsmas sajūtu. Daudzas attiecības ar šo Doshu ir ilgstošas, ja abi apzinās izaicinājumu.'
            };
        }
    },

    // ── 8. NADI ──────────────────────────────────────────────────────────────
    nadi: {
        title: 'Dzīvības enerģija',
        subtitle: 'Vai jūsu fundamentālās enerģijas papildina viena otru?',
        desc(score, max, { n1, n2 } = {}) {
            if (score === 8) return {
                summary: 'Izcila enerģētiskā saderība — jūsu enerģijas papildina viena otru.',
                detail: `Jūsu Nadi tips ir atšķirīgs (${n1 || '?'} un ${n2 || '?'}). Atšķirīgas Nadi ir nepieciešamais nosacījums veselīgām attiecībām — tāpat kā magnētu pretēji poli viens otru piesaista un pastiprina.`,
                practice: 'Šis ir vissvarīgākais no 8 faktoriem. Pilnie 8 punkti sniedz spēcīgu pamatu, uz kura balstās visas pārējās attiecību jomas.'
            };
            return {
                summary: 'Nadi Dosha — jūsu fundamentālās enerģijas ir identiskas.',
                detail: `Jums abiem ir ${n1 || 'vienāda'} Nadi. Vēdiskā tradīcija uzskata, ka identiskas Nadi var radīt enerģētisku "apsīkumu" ilgtermiņā — var izpausties kā veselības izaicinājumi vai pakāpeniska distancēšanās.`,
                practice: 'Nadi Dosha ir nopietns tradicionāls brīdinājums, taču ne "liegums". Pievērsiet uzmanību enerģētiskai atjaunošanai — katram atsevišķi un kopā.'
            };
        }
    }
};

// ── Palīgfunkcija ─────────────────────────────────────────────────────────────
export function getKutaExplanation(kuta, score, max, extra = {}) {
    const k = KUTA_DESCRIPTIONS[kuta];
    if (!k) return { title: '', subtitle: '', summary: '', detail: '', practice: '' };
    const result = k.desc(score, max, extra);
    return { title: k.title, subtitle: k.subtitle, ...result };
}
