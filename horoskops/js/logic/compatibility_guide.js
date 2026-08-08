// horoskops/js/logic/compatibility_guide.js

function getLoveLanguage(profile) {
    const sun       = profile.astro_base?.tropical?.sun    || '';
    const moon      = profile.astro_base?.tropical?.moon   || '';
    const venus     = profile.astro_base?.tropical?.venus  || '';
    // BaZi Daymaster elements jau ir latviski (piem. 'Uguns', 'Zeme') — normalizācija nav vajadzīga
    const daymaster = profile.bazi?.Daymaster?.element || '';

    // ── Mēness zīme (tropiskā; mīlestības valodu modelis ir Rietumu konstrukts — emocionālā drošība) ──
    if (moon.includes('Mežāzis') || moon.includes('Jaunava')) {
        return { type: 'Pakalpojumi un Palīdzība', desc: 'Saturna/Merkura Mēness — mīlestību mēra darbos un praktiskā rūpē, nevis vārdos.' };
    }
    if (moon.includes('Vēzis') || moon.includes('Zivis')) {
        return { type: 'Kvalitatīvs Laiks', desc: 'Emocionālais Mēness — jūtas mīlēts, kad partneris pilnībā klāt un emocionāli atvērts.' };
    }

    // ── Venēras zīme (kā cilvēks pauž un vēlas saņemt mīlestību) ──
    if (venus.includes('Vērsis') || venus.includes('Zivis')) {
        return { type: 'Pieskārieni', desc: 'Venēra Vērsī/Zivīs — jūtas mīlēts caur juteklisko tuvību, fizisko siltumu un maigumu.' };
    }
    if (venus.includes('Svari') || venus.includes('Dvīņi') || venus.includes('Ūdensvīrs')) {
        return { type: 'Atzinības vārdi', desc: 'Venēra Gaisa zīmē — mīlestību uzņem un pauž caur vārdiem, intelektuālu saikni un atzinību.' };
    }
    if (venus.includes('Vēzis') || venus.includes('Jaunava') || venus.includes('Mežāzis')) {
        return { type: 'Pakalpojumi un Palīdzība', desc: 'Venēra rūpīgā zīmē — mīlestība izpaužas caur praktiskām darbībām un uzticamu atbalstu.' };
    }
    if (venus.includes('Auns') || venus.includes('Skorpions') || venus.includes('Lauva')) {
        return { type: 'Kvalitatīvs Laiks', desc: 'Venēra Uguns/Ūdens zīmē — kaislīga klātbūtne un dziļa uzmanība ir mīlestības mērs.' };
    }
    if (venus.includes('Strēlnieks')) {
        return { type: 'Dāvanas un Pārsteigumi', desc: 'Venēra Jupitera zīmē — mīlestību jūt caur dāsnumu, pārsteigumiem un kopīgiem piedzīvojumiem.' };
    }
    if (moon.includes('Auns') || moon.includes('Skorpions')) {
        return { type: 'Pieskārieni', desc: 'Marsa Mēness — fiziskā tuvība un kaislība ir galvenais drošuma pamats.' };
    }
    if (moon.includes('Lauva') || moon.includes('Strēlnieks')) {
        return { type: 'Atzinības vārdi', desc: 'Saules/Jupitera Mēness — jūtas mīlēts caur atzinību, cieņu un entuziasmu.' };
    }
    if (moon.includes('Svari') || moon.includes('Ūdensvīrs') || moon.includes('Dvīņi')) {
        return { type: 'Atzinības vārdi', desc: 'Gaisa Mēness — mīl dziļas sarunas, intelektuālu saikni un skaidru atzinību.' };
    }
    if (moon.includes('Vērsis')) {
        return { type: 'Dāvanas un Pārsteigumi', desc: 'Venēras Mēness — taustāmi apliecinājumi un jutekliska rūpe rada drošuma sajūtu.' };
    }

    // ── Saules zīme (rezerves loģika) ──
    if (sun.includes('Dvīņi') || sun.includes('Svari') || sun.includes('Ūdensvīrs')) {
        return { type: 'Atzinības vārdi', desc: 'Jūtas mīlēts, dzirdot skaidru atzinību, komplimentus un dziļas sarunas.' };
    }
    if (sun.includes('Strēlnieks') || daymaster === 'Zeme' || daymaster === 'earth') {
        return { type: 'Dāvanas un Pārsteigumi', desc: 'Jūtas mīlēts caur uzmanības apliecinājumiem un taustāmām rūpēm.' };
    }
    if (sun.includes('Auns') || sun.includes('Vērsis') || daymaster === 'Uguns' || daymaster === 'fire') {
        return { type: 'Pieskārieni', desc: 'Fiziskā tuvība, apskāvieni un klātbūtne ir galvenais drošības garants.' };
    }
    if (sun.includes('Vēzis')) {
        return { type: 'Kvalitatīvs Laiks', desc: 'Jūtas mīlēts, kad partneris noliek malā ikdienas steigu un nedalīti velta uzmanību.' };
    }
    return { type: 'Pakalpojumi un Palīdzība', desc: 'Mīlestību mēra darbos. Praktiska palīdzība ikdienas solī šķiet vērtīgāka par tūkstoš vārdiem.' };
}

export function generateRelationshipGuide(p1, p2, compScores) {
    const p1Lang = getLoveLanguage(p1);
    const p2Lang = getLoveLanguage(p2);

    // ── Kritiskais punkts ──
    let criticalPoint = 'Šim pārim ir spēcīgs pamats, taču krīzes situācijās viedokļi var atšķirties. Ieteikums: runājiet atklāti un izvairieties no klusēšanas.';

    if (compScores.interaction.pct < 40) {
        criticalPoint = 'Šim pārim var būt grūtības ikdienas komunikācijā un pienākumu sadalē — viņi mēdz runāt "dažādās valodās". Ieteikums: skaidri nodalīt atbildības zonas un izrunāt visu bez apvainojumiem.';
    } else if (compScores.values.pct < 40) {
        criticalPoint = 'Nākotnes vīzijas un finansiālo prioritāšu atšķirības var radīt spriedzi. Ieteikums: regulāri saskaņot lielos mērķus un pieņemt, ka var būt dažādi ceļi uz tiem.';
    } else if (compScores.emotional.pct > 80 && compScores.interaction.pct < 60) {
        criticalPoint = 'Dziļa mīlestība, bet neprasme sadalīt ikdienas sīkumus. Varat sastrīdēties par trauku mazgāšanu, lai gan būtu gatavi atdot viens par otru dzīvību.';
    }

    // Brīdinājums ja saderība iegūta caur Dosha Parihara neitralizāciju
    const nadiItem   = compScores.kutaBreakdown?.find(k => k.name === 'Nadi');
    const bhakutItem = compScores.kutaBreakdown?.find(k => k.name === 'Bhakut');
    // Specifisks brīdinājums atkarībā no tā, kura Dosha tika neitralizēta
    if (nadiItem?.neutralized && bhakutItem?.neutralized) {
        criticalPoint += ' 🛡️ Gan Nadi, gan Bhakoot Dosha ir neitralizētas. Saderība ir augsta, taču pārim ieteicams apzināts darbs: regulāras veselības pārbaudes, kopīgas meditācijas vai jogas prakses un emociju apzināta pārvaldīšana krīzes brīžos.';
    } else if (nadiItem?.neutralized) {
        criticalPoint += ' 🛡️ Nadi Dosha ir neitralizēta (dažādas Mēness zīmes), taču enerģētiskā rezonanse prasa uzmanību: ieteicamas regulāras veselības pārbaudes, pietiekams miegs, kopīgas relaksācijas prakses un izvairīšanās no hroniska stresa — jo Nadi konflikts var izpausties kā enerģētisks izsīkums vai veselības traucējumi ilgtermiņā.';
    } else if (bhakutItem?.neutralized) {
        criticalPoint += ' 🛡️ Bhakoot Dosha ir neitralizēta (zīmju lordi ir draugi). Materiālā un ilgtermiņa saderība ir laba, taču ieteicams regulāri saskaņot kopīgos dzīves mērķus un finansiālās prioritātes, lai novērstu potenciālus nesaprašanās brīžus nākotnē.';
    }


    // ── Konfliktu svira ──
    let conflictLever = 'Krīzes brīdī dodiet viens otram laiku atdzist, pirms pieņemat lēmumus.';

    if (compScores.interaction.pct < 40) {
        conflictLever = 'Komunikācija ir šī pāra lielākā izaicinājums. Ieteicams noteikt "konfliktu protokolu" — piemēram, 24h pauze pirms svarīga lēmuma pieņemšanas.';
    } else if (p1.bazi?.Daymaster?.element === 'Ūdens' || p2.bazi?.Daymaster?.element === 'Ūdens') {
        conflictLever = 'Krīzes brīdī viens mēdz noslēgties (Ūdens komponente). Nespiediet partneri runāt uzreiz — dodiet laiku "atdzist".';
    } else if (p1.bazi?.Daymaster?.element === 'Uguns' && p2.bazi?.Daymaster?.element === 'Uguns') {
        conflictLever = 'Strīdi var būt kā sprādziens — ātri un skaļi. Iemācieties paņemt "time-out" mirklī, kad emocijas sit augstu vilni.';
    }

    return {
        criticalPoint,
        loveLanguages: { p1: p1Lang, p2: p2Lang },
        conflictLever
    };
}
