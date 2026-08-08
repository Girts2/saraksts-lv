/* ───────────────────────────────────────────────────────────────────────────
   konkursi.js — ES publisko iepirkumu sadaļas klienta loģika.
   Dati: konkursi.php?action=list|detail|countries|cpv|stats (lokālā SQLite).
─────────────────────────────────────────────────────────────────────────── */

'use strict';

(function () {

    const API = 'konkursi.php';
    const IUB_GUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
    const DEFAULT_SOURCE = 'IUB,MODTI,RSTI,ASTI,LDZ'; // primārā izvēle = Latvija (IUB + tirgus izpētes); '' būtu "Visi avoti"
    // Tirgus izpētes avoti (ārpus PIL) — dzeltenā kartiņa + "Tirgus izpēte" nozīmīte.
    const TI_SOURCES = new Set(['MODTI', 'RSTI', 'ASTI', 'LDZ']);

    // ── Stāvoklis ────────────────────────────────────────────────────────────
    const state = {
        cat: 'iepirkumi',
        page: 1,
        // Kartiņu virsrakstu valoda: 'lv' (title_lv, ja iztulkots) vai 'orig'.
        // Saglabājas localStorage; noklusējums LV (lapa Latvijas lietotājiem).
        lang: (function () { try { return localStorage.getItem('kk_lang') === 'orig' ? 'orig' : 'lv'; } catch (e) { return 'lv'; } })(),
        q: '',
        source: DEFAULT_SOURCE,
        srcKey: DEFAULT_SOURCE,   // izceltā avotu rinda; atšķiras no source tikai valsts rindām
        country: '',
        nature: '',
        activity: '',
        cpv: '',
        buyer: '',
        sort: 'jaunakie',
        loading: false,
        hasMore: false,
        detailId: '',   // atvērtā ieraksta id — izcēluma atjaunošanai pēc pārrenderēšanas
    };

    // ── DOM ──────────────────────────────────────────────────────────────────
    const $ = (id) => document.getElementById(id);
    const listEl = $('kk-list');
    const sentinelEl = $('kk-sentinel');
    const moreStatusEl = $('kk-more-status');
    const scrollEl = $('kk-main'); // saraksta ritināmais konteiners
    const searchEl = $('kk-search');
    const sourcesListEl = $('kk-sources-list');
    const detailBodyEl = $('kk-detail-body');
    const countryEl = $('kk-country');
    const cpvEl = $('kk-cpv');
    const buyerEl = $('kk-buyer');
    const buyerPopEl = $('kk-buyer-pop');
    const buyerToggleEl = $('kk-buyer-toggle');
    const buyerClearEl = $('kk-buyer-clear');
    const natureEl = $('kk-nature');
    const sortEl = $('kk-sort');
    const modal = $('kk-modal');
    const modalBody = $('kk-modal-body');

    // ── Starta momentuzņēmums (iegults HTML) ─────────────────────────────────
    // konkursi.php iegulž konkursi/data/start.json (<script type="application/json"
    // id="kk-start">, atjauno katra sinhronizācija): gatavie skaiti + pirmās
    // kartiņas katrai avotu paneļa rindai. Startā un avotu pārslēgšanā tos parāda
    // MOMENTĀ, bez API gaidīšanas; īstās API atbildes pēc brīža klusi nomaina
    // saturu. Vecāku par 7 dienām ignorē (sinhronizācija acīmredzot stāv) —
    // labāk ielādes ritenis nekā sen beigušies "aktīvie" konkursi.
    let snap = null;
    try {
        const snapEl = document.getElementById('kk-start');
        if (snapEl) {
            const s = JSON.parse(snapEl.textContent);
            if (s && s.lists && Date.now() - Date.parse(s.generated || 0) < 7 * 86400000) snap = s;
        }
    } catch (e) { snap = null; }

    // ── Vārdnīcas (LV) ───────────────────────────────────────────────────────
    const COUNTRY = {
        LV: ['🇱🇻', 'Latvija'], LT: ['🇱🇹', 'Lietuva'], EE: ['🇪🇪', 'Igaunija'],
        PL: ['🇵🇱', 'Polija'], FI: ['🇫🇮', 'Somija'], SE: ['🇸🇪', 'Zviedrija'],
        DK: ['🇩🇰', 'Dānija'], DE: ['🇩🇪', 'Vācija'], FR: ['🇫🇷', 'Francija'],
        NL: ['🇳🇱', 'Nīderlande'], BE: ['🇧🇪', 'Beļģija'], LU: ['🇱🇺', 'Luksemburga'],
        IE: ['🇮🇪', 'Īrija'], AT: ['🇦🇹', 'Austrija'], CZ: ['🇨🇿', 'Čehija'],
        SK: ['🇸🇰', 'Slovākija'], HU: ['🇭🇺', 'Ungārija'], SI: ['🇸🇮', 'Slovēnija'],
        HR: ['🇭🇷', 'Horvātija'], RO: ['🇷🇴', 'Rumānija'], BG: ['🇧🇬', 'Bulgārija'],
        GR: ['🇬🇷', 'Grieķija'], IT: ['🇮🇹', 'Itālija'], ES: ['🇪🇸', 'Spānija'],
        PT: ['🇵🇹', 'Portugāle'], CY: ['🇨🇾', 'Kipra'], MT: ['🇲🇹', 'Malta'],
        NO: ['🇳🇴', 'Norvēģija'], IS: ['🇮🇸', 'Islande'], LI: ['🇱🇮', 'Lihtenšteina'],
        CH: ['🇨🇭', 'Šveice'], GB: ['🇬🇧', 'Lielbritānija'], UA: ['🇺🇦', 'Ukraina'],
        MD: ['🇲🇩', 'Moldova'], RS: ['🇷🇸', 'Serbija'], BA: ['🇧🇦', 'Bosnija un Hercegovina'],
        AL: ['🇦🇱', 'Albānija'], MK: ['🇲🇰', 'Ziemeļmaķedonija'], ME: ['🇲🇪', 'Melnkalne'],
        TR: ['🇹🇷', 'Turcija'], GE: ['🇬🇪', 'Gruzija'],
        AZ: ['🇦🇿', 'Azerbaidžāna'], AM: ['🇦🇲', 'Armēnija'],
        XK: ['🇽🇰', 'Kosova'],
    };

    const NATURE = { works: 'Būvdarbi', supplies: 'Piegādes', services: 'Pakalpojumi' };

    const ACTIVITY = {
        'gen-pub': 'Valsts / Pašvaldība', 'defence': 'Aizsardzība', 'pub-order': 'Drošība',
        'pub-os': 'Drošība / Sabiedriskā kārtība', 'env-protect': 'Vides aizsardzība',
        'env-pro': 'Vides aizsardzība', 'econ-aff': 'Finanses / Ekonomika',
        'health': 'Veselības aprūpe', 'soc-prot': 'Sociālā palīdzība',
        'soc-pro': 'Sociālā palīdzība', 'rcr-cult': 'Kultūra / Reliģija',
        'rcr': 'Kultūra / Reliģija', 'education': 'Izglītība',
        'housing-com': 'Komunālie pakalpojumi', 'hc-am': 'Mājokļu un komunālie pakalpojumi',
        'gas-heat': 'Gāze un siltumapgāde', 'electricity': 'Elektroenerģija',
        'gas-oil': 'Nafta un gāze', 'solid-fuel': 'Cietais kurināmais',
        'water': 'Ūdensapgāde', 'postal': 'Pasts', 'post': 'Pasta pakalpojumi',
        'railway': 'Dzelzceļš', 'rail': 'Dzelzceļa pakalpojumi',
        'urban-transport': 'Transports', 'urttb': 'Pilsētas transports',
        'port': 'Ostas', 'airport': 'Lidostas',
    };

    const PROC = {
        'open': 'Atklāta procedūra', 'restricted': 'Slēgta procedūra',
        'negotiated': 'Sarunu procedūra', 'competitive-dialog': 'Konkurences dialogs',
        'neg-w-call': 'Sarunu procedūra ar iepriekšēju publikāciju',
        'neg-wo-call': 'Sarunu procedūra bez iepriekšējas publikācijas',
        'comp-dial': 'Konkurences dialogs', 'innovation': 'Inovāciju partnerība',
        'oth-single': 'Vienkāršota procedūra', 'oth-mult': 'Cita daudzposmu procedūra',
        // Vācijas eForms-DE nacionālie kodi
        'de-open': 'Atklāta procedūra (Öffentliche Ausschreibung)',
        'de-restricted-w-call': 'Slēgta procedūra ar publikāciju',
        'de-restricted-wo-call': 'Slēgta procedūra bez publikācijas',
        'de-comp-w-call': 'Sarunu procedūra ar publikāciju',
        'de-comp-wo-call': 'Sarunu procedūra bez publikācijas',
    };

    // eForms buyer-legal-type kodu saraksts
    const BUYER_TYPE = {
        'cga': 'Centrālā valsts iestāde', 'cga-min': 'Ministrija',
        'ra': 'Reģionālā iestāde', 'rl-aut': 'Reģionālā / vietējā iestāde',
        'la': 'Pašvaldība / vietējā iestāde', 'la-main': 'Reģionālā / vietējā iestāde', 'la-sub': 'Vietējā iestāde',
        'nat-main': 'Valsts pārvaldes iestāde', 'nat-sub': 'Valsts aģentūra',
        'body-pl': 'Publisko tiesību subjekts', 'body-pl-cga': 'Publisko tiesību subjekts (valsts)',
        'body-pl-la': 'Publisko tiesību subjekts (pašvaldība)', 'body-pl-ra': 'Publisko tiesību subjekts (reģions)',
        'pub-undert': 'Publisks uzņēmums', 'pub-undert-cga': 'Publisks uzņēmums (valsts)',
        'pub-undert-la': 'Publisks uzņēmums (pašvaldība)', 'pub-undert-ra': 'Publisks uzņēmums (reģions)',
        'eu-ins': 'ES iestāde / aģentūra', 'eu-ins-bod-ag': 'ES iestāde / aģentūra',
        'int-org': 'Starptautiska organizācija', 'grp-p-aut': 'Publisko iestāžu apvienība',
        'org-sub': 'Publiskā sektora organizācija', 'org-sub-cga': 'Publiskā sektora organizācija (valsts)',
        'org-sub-la': 'Publiskā sektora organizācija (pašvaldība)', 'org-sub-ra': 'Publiskā sektora organizācija (reģions)',
        'def-cont': 'Aizsardzības iestāde', 'spec-rights-entity': 'Subjekts ar īpašām tiesībām',
    };

    // CPV nodaļas (pirmie 2 cipari) latviski
    const CPV_DIV = {
        '03': 'Lauksaimniecības un dabas produkti', '09': 'Degviela un enerģija',
        '14': 'Izrakteņi un izejvielas', '15': 'Pārtika un dzērieni',
        '16': 'Lauksaimniecības tehnika', '18': 'Apģērbi un apavi',
        '19': 'Āda, tekstils, plastmasa', '22': 'Iespieddarbi',
        '24': 'Ķīmiskie produkti', '30': 'Biroja un datortehnika',
        '31': 'Elektroiekārtas', '32': 'Radio, TV un sakaru iekārtas',
        '33': 'Medicīnas iekārtas un farmācija', '34': 'Transportlīdzekļi',
        '35': 'Drošības un aizsardzības aprīkojums', '37': 'Mūzika, sports, atpūta',
        '38': 'Laboratorijas un optiskās iekārtas', '39': 'Mēbeles un saimniecības preces',
        '41': 'Ūdens', '42': 'Rūpnieciskās iekārtas', '43': 'Ieguves un būvniecības tehnika',
        '44': 'Būvmateriāli un konstrukcijas', '45': 'Būvdarbi', '48': 'Programmatūra',
        '50': 'Remonts un apkope', '51': 'Uzstādīšanas pakalpojumi',
        '55': 'Viesnīcas un ēdināšana', '60': 'Transporta pakalpojumi',
        '63': 'Transporta atbalsta pakalpojumi', '64': 'Pasts un telekomunikācijas',
        '65': 'Komunālie pakalpojumi', '66': 'Finanses un apdrošināšana',
        '70': 'Nekustamais īpašums', '71': 'Arhitektūra un inženierija',
        '72': 'IT pakalpojumi', '73': 'Pētniecība un izstrāde',
        '75': 'Valsts pārvaldes pakalpojumi', '76': 'Naftas un gāzes nozares pakalpojumi',
        '77': 'Lauksaimniecība un mežsaimniecība', '79': 'Uzņēmējdarbības pakalpojumi',
        '80': 'Izglītības pakalpojumi', '85': 'Veselība un sociālā aprūpe',
        '90': 'Vide un atkritumi', '92': 'Kultūra un sports', '98': 'Citi pakalpojumi',
    };

    const CAT_LABEL = {
        iepirkumi: 'Aktīvs konkurss', rezultati: 'Rezultāts / Lēmums',
        izmainas: 'Grozījumi', citi: 'Cits paziņojums',
    };

    // ── Palīgi ───────────────────────────────────────────────────────────────
    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fv(v) {
        if (v === null || v === undefined || String(v).trim() === '' || v === '-') return '–';
        return String(v);
    }
    // ISO3 atkāpe vecākiem/neapstrādātiem kodiem (piem., organizāciju laukos)
    const ISO3 = {
        DEU: 'DE', FRA: 'FR', ITA: 'IT', ESP: 'ES', POL: 'PL', NLD: 'NL', BEL: 'BE',
        SWE: 'SE', DNK: 'DK', FIN: 'FI', AUT: 'AT', PRT: 'PT', CZE: 'CZ', ROU: 'RO',
        HUN: 'HU', GRC: 'GR', BGR: 'BG', HRV: 'HR', SVK: 'SK', SVN: 'SI', LUX: 'LU',
        CYP: 'CY', MLT: 'MT', IRL: 'IE', LVA: 'LV', LTU: 'LT', EST: 'EE', NOR: 'NO',
        ISL: 'IS', CHE: 'CH', GBR: 'GB', UKR: 'UA', SRB: 'RS', TUR: 'TR',
    };
    function countryLabel(code) {
        let cc = (code || '').toUpperCase();
        if (ISO3[cc]) cc = ISO3[cc];
        const c = COUNTRY[cc];
        return c ? c[0] + ' ' + c[1] : (code || '–');
    }
    function fmtMoney(amount, currency) {
        if (amount === null || amount === undefined) return '–';
        try {
            return new Intl.NumberFormat('lv-LV', { style: 'currency', currency: currency || 'EUR', maximumFractionDigits: 0 }).format(amount);
        } catch (e) {
            return Math.round(amount).toLocaleString('lv-LV') + ' ' + (currency || 'EUR');
        }
    }
    function fmtDate(d) {
        if (!d) return '–';
        const p = String(d).split('-');
        if (p.length !== 3) return d;
        return p[2] + '.' + p[1] + '.' + p[0] + '.';
    }
    /** Kompakts datumu diapazons '29.06.–21.07.' (gadu rāda tikai tad, ja tas nav tekošais). */
    function fmtRange(f, t) {
        if (!f || !t) return '';
        const yNow = String(new Date().getFullYear());
        const withYear = f.slice(0, 4) !== t.slice(0, 4) || t.slice(0, 4) !== yNow;
        const one = (d) => {
            const p = String(d).split('-');
            if (p.length !== 3) return d;
            return p[2] + '.' + p[1] + '.' + (withYear ? p[0].slice(2) + '.' : '');
        };
        return f === t ? one(f) : one(f) + '–' + one(t);
    }
    function daysLeft(d) {
        if (!d) return null;
        const dt = new Date(d + 'T23:59:59');
        if (isNaN(dt.getTime())) return null;
        return Math.floor((dt - new Date()) / 86400000);
    }
    function cpvDivLabel(cpv) {
        if (!cpv || cpv.length < 2) return null;
        return CPV_DIV[cpv.substring(0, 2)] || null;
    }
    function tedUrl(n) {
        if (n.publication_number && n.publication_number !== '-') {
            return 'https://ted.europa.eu/lv/notice/-/detail/' + encodeURIComponent(n.publication_number);
        }
        return 'https://ted.europa.eu/lv/notice/-/detail/' + encodeURIComponent(n.id);
    }
    const SOURCE_LABEL = {
        COMDIA: 'Comdia (DK pašvaldības)', KOMMERS: 'Kommers Annons eLite (SE nacionālais)', ISUTB: 'Útboðsvefur (IS nacionālais)', TED: 'TED (ES)', IUB: 'IUB (LV nacionālais)', MODTI: 'AM tirgus izpētes (mod.gov.lv)', RSTI: 'Rīgas satiksmes tirgus izpētes', ASTI: 'Austrumu slimnīcas tirgus izpētes', LDZ: 'LDz tirgus izpētes/apspriedes', CVPIS: 'CVP IS (LT nacionālais)',
        RHR: 'RHR (EE nacionālais)', HILMA: 'Hilma (FI nacionālais)',
        DOFFIN: 'Doffin (NO nacionālais)', UDBUD: 'udbud.dk (DK nacionālais)',
        BZP: 'BZP (PL nacionālais)', BKMS: 'Vergabe (DE nacionālais)',
        BOAMP: 'BOAMP (FR nacionālais)', ETENDERS: 'eTenders (IE nacionālais)', TENDERNED: 'TenderNed (NL nacionālais)',
        PLACSP: 'PLACSP (ES nacionālais)',
        VVZ: 'VVZ (CZ nacionālais)', UVO: 'ÚVO (SK nacionālais)',
        BOSA: 'BDA (BE nacionālais)', ATKD: 'Kerndaten (AT nacionālais)',
        SEAP: 'SEAP (RO nacionālais)', EOP: 'ЦАИС ЕОП (BG nacionālais)',
        KIMDIS: 'ΚΗΜΔΗΣ (GR nacionālais)', ENAR: 'PJN (SI nacionālais)',
        EOJN: 'EOJN (HR nacionālais)', EKR: 'EKR (HU nacionālais)', BASE: 'BASE (PT nacionālais)',
        ANAC: 'ANAC (IT nacionālais)', CYPRUS: 'data.gov.cy (CY rezultāti)',
        UKFTS: 'Find a Tender (AK centrālais)', UKCF: 'Contracts Finder (AK)', UKPCS: 'Public Contracts Scotland',
        SIMAP: 'simap.ch (CH nacionālais)', PROZORRO: 'Prozorro (UA nacionālais)',
        LIVERG: 'vergaben.llv.li (LI nacionālais)', MTENDER: 'MTender (MD nacionālais)',
        EJN: 'e-Nabavke (BA nacionālais)', ESJN: 'ЕСЈН (MK nacionālais)', JNRS: 'Portal ЈН (RS nacionālais)',
        CEJN: 'CeJN (ME nacionālais)', APPAL: 'APP (AL nacionālais)',
        WB: 'Pasaules Banka (projekti)', EBRD: 'EBRD (projekti)', UNDP: 'UNDP (ANO projekti)',
    };
    const SOURCE_PREFIX = { TED: 'TED', IUB: 'IUB', MODTI: 'AM', RSTI: 'Rīgas satiksme', ASTI: 'Austrumu sl.', LDZ: 'LDz', CVPIS: 'CVP IS', RHR: 'RHR', HILMA: 'Hilma', DOFFIN: 'Doffin', UDBUD: 'DK', BZP: 'BZP', BKMS: 'DE', BOAMP: 'BOAMP', ETENDERS: 'IE', TENDERNED: 'TN', PLACSP: 'ES', VVZ: 'VVZ', UVO: 'ÚVO', BOSA: 'BDA', ATKD: 'AT', SEAP: 'SEAP', EOP: 'BG', KIMDIS: 'GR', ENAR: 'SI', EOJN: 'HR', EKR: 'EKR', BASE: 'PT', ANAC: 'IT', CYPRUS: 'CY', UKFTS: 'FTS', UKCF: 'UK', UKPCS: 'Scotland', COMDIA: 'Comdia', KOMMERS: 'Kommers', ISUTB: 'Útboð', SIMAP: 'CH', PROZORRO: 'UA', LIVERG: 'LI', MTENDER: 'MD', EJN: 'BA', ESJN: 'MK', JNRS: 'RS', CEJN: 'ME', APPAL: 'AL', WB: 'WB', EBRD: 'EBRD', UNDP: 'UNDP' };
    // Nacionālo avotu primārās saites etiķete + avota piezīme detaļās
    const SOURCE_LINK_LABEL = {
        MODTI: '🇱🇻 Oficiālā tirgus izpētes lapa (mod.gov.lv)',
        RSTI: '🇱🇻 Oficiālā tirgus izpētes lapa (rigassatiksme.lv)',
        ASTI: '🇱🇻 Oficiālā tirgus izpētes lapa (aslimnica.lv)',
        LDZ: '🇱🇻 Oficiālā izpētes/apspriedes lapa (ldz.lv)',
        CVPIS: '🇱🇹 Oficiālais paziņojums CVP IS portālā',
        RHR: '🇪🇪 Oficiālais paziņojums Riigihanked reģistrā',
        HILMA: '🇫🇮 Oficiālais paziņojums Hilma portālā',
        DOFFIN: '🇳🇴 Oficiālais paziņojums Doffin portālā',
        UDBUD: '🇩🇰 Oficiālais paziņojums udbud.dk portālā',
        COMDIA: '🇩🇰 Konkurss pašvaldības portālā (Comdia)',
        KOMMERS: '🇸🇪 Oficiālais paziņojums Kommers Annons eLite',
        ISUTB: '🇮🇸 Oficiālais paziņojums Útboðsvefur',
        BZP: '🇵🇱 Oficiālais paziņojums e-Zamówienia portālā',
        BKMS: '📄 Iepirkuma dokumentācija',
        BOAMP: '🇫🇷 Oficiālais paziņojums BOAMP portālā',
        ETENDERS: '🇮🇪 Oficiālais paziņojums eTenders portālā',
        TENDERNED: '🇳🇱 Oficiālais paziņojums TenderNed portālā',
        PLACSP: '🇪🇸 Oficiālais paziņojums PLACSP portālā',
        VVZ: '🇨🇿 Oficiālais formulārs VVZ vēstnesī',
        UVO: '🇸🇰 Oficiālais paziņojums ÚVO vestníkā',
        BOSA: '🇧🇪 Oficiālais paziņojums e-Procurement platformā',
        ATKD: '🇦🇹 Oficiālais paziņojums iepirkuma platformā',
        SEAP: '🇷🇴 Oficiālais paziņojums SEAP platformā',
        EOP: '🇧🇬 Oficiālais paziņojums ЦАИС ЕОП platformā',
        KIMDIS: '🇬🇷 Oficiālais dokuments (ΚΗΜΔΗΣ PDF)',
        ENAR: '🇸🇮 Oficiālais paziņojums PJN portālā',
        EOJN: '🇭🇷 Oficiālais paziņojums EOJN portālā',
        EKR: '🇭🇺 Oficiālais paziņojums EKR sistēmā',
        BASE: '🇵🇹 Oficiālais paziņojums BASE portālā',
        CYPRUS: '🇨🇾 Paziņojums eProcurement portālā',
        UKFTS: '🇬🇧 Paziņojums Find a Tender portālā',
        UKCF: '🇬🇧 Paziņojums Contracts Finder portālā',
        UKPCS: '🏴󠁧󠁢󠁳󠁣󠁴󠁿 Paziņojums Public Contracts Scotland portālā',
        SIMAP: '🇨🇭 Oficiālais paziņojums simap.ch portālā',
        PROZORRO: '🇺🇦 Oficiālais paziņojums Prozorro portālā',
        LIVERG: '🇱🇮 Oficiālais paziņojums vergaben.llv.li portālā',
        MTENDER: '🇲🇩 Oficiālais paziņojums MTender portālā',
        EJN: '🇧🇦 Meklēt paziņojumu e-Nabavke portālā',
        ESJN: '🇲🇰 Oficiālais paziņojums ЕСЈН portālā',
        JNRS: '🇷🇸 Oficiālais paziņojums Portalā ЈН',
        CEJN: '🇲🇪 Oficiālais paziņojums CeJN portālā',
        APPAL: '🇦🇱 Meklēt paziņojumu APP portālā',
        WB: '🏦 Paziņojums Pasaules Bankas projektu portālā',
        EBRD: '🏦 Paziņojums EBRD ECEPP portālā',
        UNDP: '🇺🇳 Paziņojums UNDP iepirkumu portālā',
    };
    // Avota PORTĀLA saites (papildu poga detaļās) avotiem, kur per-paziņojuma web
    // lapas avotā nav vai tā viena pati nepietiek. Funkcija saņem paziņojumu (gadam u.c.).
    const SOURCE_PORTAL = {
        MODTI: () => ({
            label: '🇱🇻 Visas AM tirgus izpētes (mod.gov.lv)',
            url: 'https://www.mod.gov.lv/lv/tirgus-izpetes',
        }),
        RSTI: () => ({
            label: '🇱🇻 Visas Rīgas satiksmes tirgus izpētes',
            url: 'https://www.rigassatiksme.lv/lv/par-mums/iepirkumi/tirgus-izpetes/',
        }),
        ASTI: () => ({
            label: '🇱🇻 Visas Austrumu slimnīcas tirgus izpētes',
            url: 'https://aslimnica.lv/iepirkumi/tirgus-izpetes/',
        }),
        LDZ: () => ({
            label: '🇱🇻 Visas LDz tirgus izpētes un apspriedes',
            url: 'https://ldz.lv/lv/iepirkumi',
        }),
        // ANAC per-CIG lapa ir salauzta pašā ANAC pusē (WAF "Request Rejected") — vienīgā
        // dzīvā publiskā lapa ir gada CIG datu kopa atvērto datu portālā (pārbaudīts 2026-07-24).
        ANAC: (n) => ({
            label: '🇮🇹 ANAC atvērto datu portāls (CIG datu kopa)',
            url: 'https://dati.anticorruzione.it/opendata/dataset/cig-' + String(n.publication_date || '2026').slice(0, 4),
        }),
        // ΚΗΜΔΗΣ publiskais reģistrs — web meklēšana pēc ΑΔΑΜ numura (papildus PDF saitei).
        KIMDIS: () => ({
            label: '🇬🇷 ΚΗΜΔΗΣ publiskais reģistrs (meklē pēc ΑΔΑΜ)',
            url: 'https://cerpp.eprocurement.gov.gr/upgkimdis/unprotected/home.xhtml',
        }),
    };
    // buyer_profile_url (eForms BT-508) nav viens saites veids: PT tur liek paša
    // paziņojuma PDF, FI — iepirkuma lapu, bet EE un DE bieži tikai pasūtītāja
    // mājaslapu. Sola to, kas tur tiešām ir, nevis "dokumentāciju" visiem.
    function buyerProfileLabel(url) {
        let path = '';
        try { const u = new URL(url); path = u.pathname + u.search; } catch (e) { return '📄 Iepirkuma dokumentācija'; }
        if (/\.pdf(\?|$)/i.test(path)) return '📄 Paziņojuma dokuments (PDF)';
        if (path === '' || path === '/') return '🏢 Pasūtītāja mājaslapa';
        return '📄 Iepirkuma dokumentācija';
    }
    // Reģionu virsraksti nacionālo avotu panelī. Secība = attēlošanas secība:
    // Latvijas lietotājam tuvākais reģions augšā, tālākais apakšā.
    const REGION_LABEL = {
        baltija:       'Baltija',
        ziemeleiropa:  'Ziemeļeiropa',
        centraleiropa: 'Centrāleiropa',
        austrumeiropa: 'Austrumeiropa',
        rietumeiropa:  'Rietumeiropa',
        dienvideiropa: 'Dienvideiropa',
        balkani:       'Dienvidaustrumeiropa (Balkāni)',
        kaukazs:       'Kaukāzs un Turcija',
    };
    // Kreisā avotu paneļa rindas (secība = attēlošanas secība; region maina grupu).
    // '#'-koda rindas filtrē pēc pasūtītāja valsts, ne avota (nacionālās plūsmas nav
    // vai ir tikai daļa — TED/IFI dati tāpat redzami), un stāv savā reģionā.
    const SOURCE_META = [
        { code: '',       flag: '🌍', name: 'Visi avoti',  sub: 'TED + nacionālie' },
        { code: 'TED',    flag: '🇪🇺', name: 'ES TED',      sub: 'lielie konkursi' },
        // ── Baltija ──
        // Latvijai vairāki avoti vienā rindā (kā Dānijai): IUB formālie iepirkumi +
        // tirgus izpētes (MODTI, Rīgas satiksme, Austrumu slimnīca, LDz).
        // Izpētes kartiņas izceļ dzeltenā tonī + nozīmīte.
        { code: 'IUB,MODTI,RSTI,ASTI,LDZ', codes: ['IUB', 'MODTI', 'RSTI', 'ASTI', 'LDZ'], flag: '🇱🇻', name: 'Latvija',
          sub: 'IUB + tirgus izpētes', region: 'baltija' },
        { code: 'CVPIS',  flag: '🇱🇹', name: 'Lietuva',     sub: 'CVP IS nacionālie', region: 'baltija' },
        { code: 'RHR',    flag: '🇪🇪', name: 'Igaunija',    sub: 'RHR nacionālie', region: 'baltija' },
        // ── Ziemeļeiropa ──
        { code: 'HILMA',  flag: '🇫🇮', name: 'Somija',      sub: 'Hilma nacionālie', region: 'ziemeleiropa' },
        // Zviedrijai nav valsts reģistra — Kommers ir reģistrēta privātā datubāze (sk. SE_NOTE)
        { code: 'KOMMERS', flag: '🇸🇪', name: 'Zviedrija',   sub: 'Kommers Annons eLite', region: 'ziemeleiropa' },
        // Dānijai ir DIVI nacionālie avoti: udbud.dk (valsts reģistrs) un Comdia
        // (pašvaldību platforma). Panelī tā ir viena rinda — 'codes' saskaita abus,
        // 'code' aiziet uz API kā saraksts.
        { code: 'UDBUD,COMDIA', codes: ['UDBUD', 'COMDIA'], flag: '🇩🇰', name: 'Dānija',
          sub: 'udbud.dk + pašvaldības', region: 'ziemeleiropa' },
        { code: 'DOFFIN', flag: '🇳🇴', name: 'Norvēģija',   sub: 'Doffin nacionālie', region: 'ziemeleiropa' },
        { code: 'ISUTB',  flag: '🇮🇸', name: 'Islande',     sub: 'Útboðsvefur nacionālie', region: 'ziemeleiropa' },
        // ── Centrāleiropa ──
        { code: 'BZP',    flag: '🇵🇱', name: 'Polija',      sub: 'BZP nacionālie', region: 'centraleiropa' },
        { code: 'BKMS',   flag: '🇩🇪', name: 'Vācija',      sub: 'Vergabe nacionālie', region: 'centraleiropa' },
        { code: 'VVZ',    flag: '🇨🇿', name: 'Čehija',      sub: 'VVZ nacionālie', region: 'centraleiropa' },
        { code: 'UVO',    flag: '🇸🇰', name: 'Slovākija',   sub: 'ÚVO nacionālie', region: 'centraleiropa' },
        { code: 'ATKD',   flag: '🇦🇹', name: 'Austrija',    sub: 'Kerndaten', region: 'centraleiropa' },
        { code: 'EKR',    flag: '🇭🇺', name: 'Ungārija',    sub: 'EKR nacionālie', region: 'centraleiropa' },
        { code: 'SIMAP',  flag: '🇨🇭', name: 'Šveice',      sub: 'simap.ch mazie konkursi', region: 'centraleiropa' },
        { code: 'LIVERG', flag: '🇱🇮', name: 'Lihtenšteina', sub: 'vergaben.llv.li (USB)', region: 'centraleiropa' },
        // ── Austrumeiropa ──
        { code: 'PROZORRO', flag: '🇺🇦', name: 'Ukraina',    sub: 'Prozorro mazie iepirkumi', region: 'austrumeiropa' },
        { code: 'MTENDER', flag: '🇲🇩', name: 'Moldova',    sub: 'MTender aktīvie konkursi', region: 'austrumeiropa' },
        // ── Rietumeiropa ──
        { code: 'TENDERNED', flag: '🇳🇱', name: 'Nīderlande', sub: 'TenderNed nacionālie', region: 'rietumeiropa' },
        { code: 'BOSA',   flag: '🇧🇪', name: 'Beļģija',     sub: 'BDA nacionālie', region: 'rietumeiropa' },
        { code: 'BOAMP',  flag: '🇫🇷', name: 'Francija',    sub: 'BOAMP nacionālie', region: 'rietumeiropa' },
        { code: '#GB',   flag: '🇬🇧', name: 'Lielbritānija', sub: 'Find a Tender + CF + Skotija', country: 'GB', region: 'rietumeiropa' },
        { code: 'ETENDERS', flag: '🇮🇪', name: 'Īrija',     sub: 'eTenders nacionālie', region: 'rietumeiropa' },
        // ── Dienvideiropa ──
        { code: 'ANAC',   flag: '🇮🇹', name: 'Itālija',     sub: 'ANAC CIG aktīvie', region: 'dienvideiropa' },
        { code: 'PLACSP', flag: '🇪🇸', name: 'Spānija',     sub: 'PLACSP nacionālie', region: 'dienvideiropa' },
        { code: 'BASE',   flag: '🇵🇹', name: 'Portugāle',   sub: 'BASE anúncios', region: 'dienvideiropa' },
        { code: 'KIMDIS', flag: '🇬🇷', name: 'Grieķija',    sub: 'ΚΗΜΔΗΣ nacionālie', region: 'dienvideiropa' },
        { code: '#MT',   flag: '🇲🇹', name: 'Malta',       sub: 'tikai TED', country: 'MT', region: 'dienvideiropa' },
        { code: '#CY',   flag: '🇨🇾', name: 'Kipra',       sub: 'TED + piešķirtie līgumi', country: 'CY', region: 'dienvideiropa' },
        // ── Dienvidaustrumeiropa (Balkāni) ──
        { code: 'ENAR',   flag: '🇸🇮', name: 'Slovēnija',   sub: 'PJN nacionālie', region: 'balkani' },
        { code: 'EOJN',   flag: '🇭🇷', name: 'Horvātija',   sub: 'EOJN nacionālie', region: 'balkani' },
        { code: 'SEAP',   flag: '🇷🇴', name: 'Rumānija',    sub: 'SEAP nacionālie', region: 'balkani' },
        { code: 'EOP',    flag: '🇧🇬', name: 'Bulgārija',   sub: 'ЦАИС ЕОП nacionālie', region: 'balkani' },
        { code: 'JNRS',   flag: '🇷🇸', name: 'Serbija',     sub: 'Portal ЈН nacionālie', region: 'balkani' },
        { code: 'EJN',    flag: '🇧🇦', name: 'Bosnija un Hercegovina', sub: 'e-Nabavke NPS mazie', region: 'balkani' },
        { code: 'ESJN',   flag: '🇲🇰', name: 'Ziemeļmaķedonija', sub: 'ЕСЈН visi iepirkumi', region: 'balkani' },
        { code: 'CEJN',   flag: '🇲🇪', name: 'Melnkalne',   sub: 'CeJN visi iepirkumi', region: 'balkani' },
        { code: 'APPAL',  flag: '🇦🇱', name: 'Albānija',    sub: 'APP mazie iepirkumi', region: 'balkani' },
        // ── Kaukāzs un Turcija ──
        { code: '#TR',   flag: '🇹🇷', name: 'Turcija',     sub: 'TED + WB/EBRD/UNDP projekti', country: 'TR', region: 'kaukazs' },
        { code: '#GE',   flag: '🇬🇪', name: 'Gruzija',     sub: 'TED + WB/EBRD/UNDP projekti', country: 'GE', region: 'kaukazs' },
        { code: '#AZ',   flag: '🇦🇿', name: 'Azerbaidžāna', sub: 'WB/EBRD/UNDP projekti', country: 'AZ', region: 'kaukazs' },
        { code: '#AM',   flag: '🇦🇲', name: 'Armēnija',    sub: 'WB/EBRD/UNDP projekti', country: 'AM', region: 'kaukazs' },
    ];

    // Paskaidrojums valstīm, kurām nacionālo mazo konkursu sadaļa ir tukša vai
    // daļēja. Katram OBLIGĀTI saite, kur konkursi tomēr ir skatāmi.
    const COUNTRY_NOTE = {
        '#SE': '<strong>🇸🇪 Zviedrijā nav valsts iepirkumu reģistra.</strong> ' +
            'Sludinājumi tiek publicēti privātās datubāzēs, kuru dati nav brīvi pieejami, ' +
            'tāpēc šeit redzami tikai virs ES sliekšņa esošie konkursi no TED. ' +
            'Vienotu nacionālo datubāzi ar bezmaksas piekļuvi gatavo Statskontoret — ' +
            'gala priekšlikums <strong>līdz 2027. gada 31. maijam</strong>.',
        '#MT': '<strong>🇲🇹 Maltas mazos konkursus nav atļauts iegūt automatizēti.</strong> ' +
            'Portāla etenders.gov.mt lietošanas noteikumi (2.4. punkts un aizliegums “Data mining ' +
            'the Services”) skaidri aizliedz datu ievākšanu ar programmu vai algoritmu, tāpēc ' +
            'nacionālos konkursus šeit nerādām — redzami tikai virs ES sliekšņa esošie no TED. ' +
            'Maltas nacionālie konkursi ir skatāmi oficiālajā portālā: ' +
            '<a href="https://www.etenders.gov.mt/" target="_blank" rel="noopener noreferrer">etenders.gov.mt</a>.',
        '#CY': '<strong>🇨🇾 Kiprai pieejami TED konkursi un piešķirtie līgumi.</strong> ' +
            'Aktīvo nacionālo konkursu meklēšana portālā eprocurement.gov.cy ir aiz CAPTCHA, ' +
            'ko apzināti neapejam, tāpēc “Aktīvie” satur tikai TED konkursus. ' +
            '“Rezultāti” nāk no Valsts kases atvērtajiem datiem (data.gov.cy). ' +
            'Kipras nacionālie konkursi ir skatāmi oficiālajā portālā: ' +
            '<a href="https://www.eprocurement.gov.cy/" target="_blank" rel="noopener noreferrer">eprocurement.gov.cy</a>.',
        '#GE': '<strong>🇬🇪 Gruzijai redzami TED konkursi un Pasaules Bankas, EBRD un ANO (UNDP) projektu iepirkumi.</strong> ' +
            'Tie ir starptautisko banku finansēto projektu iepirkumi (infrastruktūra, konsultācijas), nevis ' +
            'nacionālie mazie konkursi: valsts atvērto datu API (odapi.spa.ge) nedarbojas kopš 2020. gada, un ' +
            'vienotais portāls ir aiz botu aizsardzības, ko apzināti neapejam. Nacionālie iepirkumi ir skatāmi oficiālajā portālā: ' +
            '<a href="https://tenders.procurement.gov.ge/" target="_blank" rel="noopener noreferrer">tenders.procurement.gov.ge</a>.',
        '#TR': '<strong>🇹🇷 Turcijai redzami TED konkursi un Pasaules Bankas, EBRD un ANO (UNDP) projektu iepirkumi.</strong> ' +
            'Tie ir starptautisko banku projektu iepirkumi, nevis nacionālie mazie konkursi (Doğrudan Temin): ' +
            'EKAP portāls ir aiz botu aizsardzības un CAPTCHA, ko apzināti neapejam. Nacionālie konkursi ir skatāmi oficiālajā portālā: ' +
            '<a href="https://ekap.kik.gov.tr/" target="_blank" rel="noopener noreferrer">ekap.kik.gov.tr</a>.',
        '#AZ': '<strong>🇦🇿 Azerbaidžānai redzami Pasaules Bankas, EBRD un ANO (UNDP) projektu iepirkumi.</strong> ' +
            'Tie ir starptautisko banku projektu iepirkumi. Nacionālais portāls etender.gov.az ir ģeobloķēts ' +
            '(savienojums no ārvalstīm tiek atteikts), ko apzināti neapejam ar starpniekserveri, un Azerbaidžāna nav TED daļa. ' +
            'Nacionālie konkursi ir skatāmi oficiālajā portālā (no Azerbaidžānas): ' +
            '<a href="https://etender.gov.az/" target="_blank" rel="noopener noreferrer">etender.gov.az</a>.',
        '#AM': '<strong>🇦🇲 Armēnijai redzami Pasaules Bankas, EBRD un ANO (UNDP) projektu iepirkumi.</strong> ' +
            'Tie ir starptautisko banku projektu iepirkumi. Nacionālais OCDS API (armeps.am) ir tik lēns un nestabils, ' +
            'ka to intensīvi slogot būtu pretēji atbildīgai lietošanai, un Armēnija nav TED daļa. ' +
            'Nacionālie konkursi ir skatāmi oficiālajā portālā: ' +
            '<a href="https://armeps.am/" target="_blank" rel="noopener noreferrer">armeps.am</a>.',
    };

    const SOURCE_NOTE = {
        MODTI: 'Avots: mod.gov.lv (Aizsardzības ministrija). ⚠️ Šī ir TIRGUS IZPĒTE, nevis Publisko iepirkumu likuma iepirkums: iestāde apzina tirgu un var noslēgt zemsliekšņa līgumu bez formālas procedūras vai izmantot atbildes tikai cenu apzināšanai pirms nākamā iepirkuma. Pārsūdzības kārtības nav. Piedāvājums jāsūta iestādei tieši — kontakti un iesniegšanas kārtība redzami oficiālajā lapā.',
        RSTI: 'Avots: rigassatiksme.lv (RP SIA "Rīgas satiksme"). ⚠️ Šī ir TIRGUS IZPĒTE, nevis Publisko iepirkumu likuma iepirkums: uzņēmums apzina tirgu un var noslēgt zemsliekšņa līgumu bez formālas procedūras vai izmantot atbildes tikai cenu apzināšanai. Pārsūdzības kārtības nav. Piedāvājuma formas un iesniegšanas kārtība (e-pasts) redzamas oficiālajā lapā.',
        ASTI: 'Avots: aslimnica.lv (SIA "Rīgas Austrumu klīniskā universitātes slimnīca"). ⚠️ Šī ir TIRGUS IZPĒTE, nevis Publisko iepirkumu likuma iepirkums: slimnīca apzina tirgu un var noslēgt zemsliekšņa līgumu bez formālas procedūras vai izmantot atbildes tikai cenu apzināšanai pirms nākamā iepirkuma. Pārsūdzības kārtības nav. Piedāvājuma iesniegšanas kārtība un kontakti redzami oficiālajā lapā.',
        LDZ: 'Avots: ldz.lv (VAS "Latvijas dzelzceļš" / SIA "LDz Cargo"). ⚠️ Šī ir TIRGUS IZPĒTE vai APSPRIEDE pirms iepirkuma, nevis Publisko iepirkumu likuma iepirkums: uzņēmums apzina tirgu vai cenu pirms formālas procedūras un var noslēgt zemsliekšņa līgumu bez tās. Pārsūdzības kārtības nav. Formālie LDz konkursi šeit netiek rādīti — tie ir IUB/TED plūsmā. Piedāvājuma iesniegšanas kārtība un kontakti redzami oficiālajā lapā.',
        IUB: 'Avots: open.iub.gov.lv (Iepirkumu uzraudzības birojs). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams IUB / EIS sistēmā.',
        CVPIS: 'Avots: viesiejipirkimai.lt (Lietuvas Viešųjų pirkimų tarnyba, CVP IS). Šis ir informatīvs kopsavilkums — pilnais paziņojums un dokumentācija pieejama CVP IS portālā.',
        RHR: 'Avots: riigihanked.riik.ee (Igaunijas riigihangete register, CC BY-SA 3.0). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams reģistrā.',
        HILMA: 'Avots: hankintailmoitukset.fi (Hilma, CC BY 4.0). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams Hilma portālā.',
        DOFFIN: 'Avots: doffin.no (Norvēģijas DFØ). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams Doffin portālā.',
        UDBUD: 'Avots: udbud.dk (Konkurrence- og Forbrugerstyrelsen). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams udbud.dk portālā.',
        ISUTB: 'Avots: utbodsvefur.is — Islandes vienotais sludinājumu dēlis. Reglugerð nr. 260/2020 prasa, lai visi nacionālie iepirkumi būtu publicēti šeit; dokumentus un pieteikšanos parasti administrē TendSign. Islandei nav atvērto datu API, tāpēc šis kopsavilkums veidots no publiskās sludinājumu lapas; oficiālais un juridiski saistošais paziņojums vienmēr ir utbodsvefur.is.',
        KOMMERS: 'Avots: Kommers Annons eLite (kommersannons.se — Primona AB) — Konkurrensverket reģistrēta sludinājumu datubāze. Zviedrijā zem-sliekšņa iepirkumi jāizsludina reģistrētā datubāzē; virs-sliekšņa paziņojumi nāk caur TED.',
        COMDIA: 'Avots: comdia.com — Dānijas pašvaldību iepirkumu platforma. Šis avots publiski nesniedz ne termiņu, ne CPV, ne līgumcenu, tāpēc šeit ir tikai nosaukums un saite; visas detaļas skatāmas oriģinālā.',
        BZP: 'Avots: ezamowienia.gov.pl (Urząd Zamówień Publicznych, BZP). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams e-Zamówienia portālā.',
        BKMS: 'Avots: oeffentlichevergabe.de (Datenservice Öffentlicher Einkauf, © BMI). Šis ir informatīvs kopsavilkums.',
        BOAMP: 'Avots: boamp.fr (DILA, atvērto datu licence). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams BOAMP portālā.',
        ETENDERS: 'Avots: etenders.gov.ie (Office of Government Procurement). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams eTenders portālā.',
        TENDERNED: 'Avots: tenderned.nl (Ministerie van Economische Zaken). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams TenderNed portālā.',
        PLACSP: 'Avots: Plataforma de Contratación del Sector Público (PLACSP). Datus ņemam no oficiālās CODICE ATOM sindikācijas plūsmas contrataciondelsectorpublico.gob.es; publiskais meklēšanas portāls ir contrataciondelestado.es. Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams PLACSP portālā.',
        VVZ: 'Avots: vvz.nipez.cz (Věstník veřejných zakázek, Ministerstvo pro místní rozvoj ČR). Šis ir informatīvs kopsavilkums — pilnais formulārs pieejams VVZ portālā.',
        UVO: 'Avots: uvo.gov.sk (Úrad pre verejné obstarávanie, Vestník verejného obstarávania). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams ÚVO vestníkā.',
        BOSA: 'Avots: publicprocurement.be (BOSA e-Procurement, Bulletin der Aanbestedingen / Bulletin des Adjudications). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams e-Procurement platformā.',
        ATKD: 'Avots: data.gv.at Kerndaten plūsmas (ANKÖ, vemap, BBG, Ausschreibung.at — BVergG 2018 §66). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams attiecīgajā iepirkumu platformā.',
        SEAP: 'Avots: e-licitatie.ro (SEAP/SICAP, Agenția pentru Agenda Digitală a României). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams SEAP platformā.',
        EOP: 'Avots: app.eop.bg (ЦАИС ЕОП, Агенция по обществени поръчки). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams ЦАИС ЕОП platformā. Rezultātu sarakstā ЦАИС ЕОП nesniedz uzvarētāju un līgumcenu — tie redzami tikai paziņojuma lapā.',
        KIMDIS: 'Avots: eprocurement.gov.gr ΚΗΜΔΗΣ atvērto datu API. Šis ir informatīvs kopsavilkums — oficiālais dokuments pieejams kā ΚΗΜΔΗΣ pielikums (PDF).',
        ENAR: 'Avots: enarocanje.si (Portal javnih naročil, Ministrstvo za javno upravo). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams PJN portālā.',
        EOJN: 'Avots: eojn.hr (Elektronički oglasnik javne nabave RH, Narodne novine). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams EOJN portālā.',
        EKR: 'Avots: ekr.gov.hu (Elektronikus Közbeszerzési Rendszer). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams EKR sistēmā.',
        BASE: 'Avots: base.gov.pt (IMPIC, Portal BASE). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams BASE portālā un Diário da República.',
        CYPRUS: 'Avots: data.gov.cy (Γενικό Λογιστήριο — Kipras Valsts kase, atvērtie dati). Kiprai pieejami TIKAI piešķirtie līgumi: aktīvo konkursu meklēšana eprocurement.gov.cy ir aiz CAPTCHA, tāpēc tos šeit nerādām — tie skatāmi oficiālajā portālā.',
        UKFTS: 'Avots: find-tender.service.gov.uk — AK centrālā platforma kopš 2025. gada Procurement Act; tur nonāk arī Velsas paziņojumi. Satur publiskā sektora informāciju, kas licencēta ar Open Government Licence v3.0.',
        UKCF: 'Avots: contractsfinder.service.gov.uk. Satur publiskā sektora informāciju, kas licencēta ar Open Government Licence v3.0.',
        UKPCS: 'Avots: publiccontractsscotland.gov.uk. Skotijas zem-sliekšņa konkursi uz AK centrālo platformu neplūst, tāpēc tie nāk tieši no PCS. Satur publiskā sektora informāciju, kas licencēta ar Open Government Licence v3.0.',
        SIMAP: 'Avots: simap.ch (Verein simap.ch — Konfederācijas un kantonu kopīgā platforma), publiskais REST API. Šeit ir TIKAI mazie/nacionālie konkursi: virs-sliekšņa iepirkumi (Staatsvertragsbereich) dublējas uz TED, tāpēc tos izlaižam. CPV Šveices vietējiem konkursiem bieži nav aizpildīts (izmanto BKP/NPK klasifikāciju), tāpēc dažiem nozares filtrs var nedarboties — meklē pēc nosaukuma. Vērtība un termiņš oriģinālā simap.ch lapā. Šī nav oficiālā publikācija — juridiski saistošais avots ir simap.ch.',
        PROZORRO: 'Avots: prozorro.gov.ua publiskais OpenProcurement/OCDS API (Transparency International Ukraine iniciatīva). Šeit ir TIKAI mazie iepirkumi — zem-sliekšņa procedūras (belowThreshold, priceQuotation) un tiešie līgumi (reporting). Virs-sliekšņa iepirkumi nonāk TED, tāpēc tos neimportējam. Vērtība UAH; pilns paziņojums un dokumenti oriģinālā Prozorro lapā.',
        LIVERG: 'Avots: vergaben.llv.li (Fachstelle Öffentliches Auftragswesen, ANKÖ platforma). Šeit ir TIKAI zem-sliekšņa (USB — Unterschwellenbereich) mazie konkursi; virs-sliekšņa (OSB) iepirkumi nonāk TED, tāpēc tos neimportējam. CPV Lihtenšteinas būvniecībā bieži nav (izmanto BKP kodus), tāpēc nozares filtrs var nedarboties — meklē pēc nosaukuma. Lihtenšteina ir maza valsts, tāpēc konkursu skaits ir neliels.',
        MTENDER: 'Avots: mtender.gov.md publiskais OCDS API (Moldovas valsts iepirkumu sistēma). Rādām AKTĪVOS konkurētspējīgos konkursus, kuriem vēl atvērts iesniegšanas termiņš (atklātie, mazās un mikro procedūras, cenu aptaujas); tiešos līgumus (directAward) bez konkurences izlaižam. Moldova nav ES/TED, tāpēc vērtību neierobežojam — iekļaujam arī lielos konkursus. Kartējam nosaukumu, vērtību (MDL), CPV, pasūtītāju un termiņu; pilns paziņojums un dokumenti oriģinālā MTender lapā.',
        EJN: 'Avots: open.ejn.gov.ba oficiālais OData API (Bosnijas un Hercegovinas Publisko iepirkumu aģentūra) — gan zem-sliekšņa mazie iepirkumi (NPS — jednostavne nabavke), gan regulārās procedūras, kas uz ES TED NEnonāk. CPV un līgumcena paziņojumā publiski nav — kartē nosaukumu, pasūtītāju, pilsētu un termiņu. E-Nabavke publiskajam portālam nav tiešu per-paziņojuma saišu, tāpēc poga ved uz portāla meklēšanu — konkrēto paziņojumu atrodi pēc nosaukuma vai pasūtītāja (redzami augstāk).',
        ESJN: 'Avots: e-nabavki.gov.mk (Ziemeļmaķedonijas Publisko iepirkumu birojs, ЕСЈН). Rādām VISAS procedūras — mazās vērtības un vienkāršotās atklātās, kā arī atklātās (Open) procedūras. Ziemeļmaķedonija nav ES/TED. Iesniegšanas termiņš ir īsts (FinalDay režģī). CPV un līgumcena sarakstā publiski nav — kartē nosaukumu, pasūtītāju, veidu un termiņu; pilns paziņojums oriģinālā ЕСЈН lapā.',
        JNRS: 'Avots: jnportal.ujn.gov.rs (Serbijas Publisko iepirkumu birojs, Portal javnih nabavki). Nacionālie konkursi (Јавни позив) un piešķiršanas paziņojumi. Termiņš, līgumcena un CPV paziņojumu sarakstā publiski nav — kartē nosaukumu, pasūtītāju, paziņojuma veidu un datumu; pilns paziņojums, termiņš un vērtība oriģinālā Portal ЈН lapā.',
        CEJN: 'Avots: cejn.gov.me (Crnogorske Elektronske Javne Nabavke, Melnkalnes e-iepirkumu sistēma). Rādām VISUS iepirkumus — gan mazos (Jednostavna nabavka / Small procurement), gan atklātās procedūras (Otvoreni postupak) un vispārīgās vienošanās. Melnkalne nav ES/TED. Atklātajām procedūrām iesniegšanas termiņš ir īsts (no procedūras raundiem); mazajiem termiņa sistēmā nav. Līgumcena un CPV sarakstā publiski nav — pilns paziņojums oriģinālā CeJN lapā.',
        APPAL: 'Avots: app.gov.al (Agjencia e Prokurimit Publik, Albānijas Publisko iepirkumu aģentūra). Šeit ir TIKAI mazie iepirkumi (prokurimet me vlerë të vogël), kas ir zem ES sliekšņa un TED NEnonāk. Portāls rāda visus mazos iepirkumus vienā sarakstā (bez tiešām per-paziņojuma saitēm), tāpēc poga ved uz šo sarakstu — konkrēto konkursu atrodi pēc REF numura vai nosaukuma (redzami augstāk). Līgumcena un CPV — pilnajā paziņojumā APP portālā.',
        ANAC: 'Avots: dati.anticorruzione.it (ANAC atvērto datu CIG kopa, CC BY-SA 4.0). Šis ir informatīvs kopsavilkums — pilnā dokumentācija pieejama pasūtītāja platformā (CIG meklēšanai izmantojiet ANAC portālu).',
        TED: 'Avots: ted.europa.eu (© Eiropas Savienība). Šis ir informatīvs kopsavilkums — pilnais paziņojums pieejams TED portālā.',
        WB: 'Avots: search.worldbank.org procnotices API (Pasaules Banka, CC BY 4.0). Pasaules Bankas FINANSĒTO projektu iepirkums (infrastruktūra, konsultācijas, preces) — nevis nacionālais iepirkums. Vērtību banka sarakstā nesniedz; pilnā dokumentācija un vērtība oriģinālā paziņojumā projects.worldbank.org.',
        EBRD: 'Avots: ecepp.ebrd.com (EBRD Client e-Procurement Portal). EBRD FINANSĒTO projektu iepirkums; šie paziņojumi tiek publicēti arī TED. Vērtība un pilnā informācija oriģinālā ECEPP paziņojumā.',
        UNDP: 'Avots: procurement-notices.undp.org (ANO Attīstības programma, Europe & CIS reģions). ANO aģentūras PAŠU iepirkums (preces, pakalpojumi, darbi projektos), nevis nacionālais. Pilnā informācija un pieteikšanās oriģinālā UNDP paziņojumā.',
    };
    // Zviedrija: vienīgā valsts bez nacionālā reģistra, tāpēc rādām tikai ES līmeņa
    // konkursus. Piezīme paskaidro, kāpēc un no kura brīža to varēs papildināt.
    const SE_NOTE = 'Avots: ted.europa.eu (© Eiropas Savienība). Zviedrijai nav valsts iepirkumu ' +
        'reģistra — pēc Lag (2019:668) om upphandlingsstatistik sludinājumi tiek publicēti privātās ' +
        'datubāzēs (Mercell, e-Avrop, KommersAnnons, Clira, Konstpool), kuru dati nav brīvi ' +
        'pieejami. Tāpēc šeit redzami tikai virs ES sliekšņa esošie konkursi, kas nonāk TED. ' +
        'Statskontoret gatavo vienotu nacionālo datubāzi ar bezmaksas piekļuvi — gala priekšlikums ' +
        'iesniedzams līdz 2027. gada 31. maijam, un pēc tā ieviešanas šeit varēs rādīt arī ' +
        'Zviedrijas mazos, zem sliekšņa esošos iepirkumus.';
    function numLabel(n) {
        let num = n.publication_number && n.publication_number !== '-' ? n.publication_number : n.id;
        // UUID formāta numurus rāda saīsināti (pilnais vienmēr redzams detaļās/saitē)
        if (/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f-]{18,}$/i.test(num)) num = num.substring(0, 8).toUpperCase();
        return (SOURCE_PREFIX[n.source] || n.source) + ' ' + num;
    }

    async function apiGet(params, signal) {
        const usp = new URLSearchParams(params);
        const resp = await fetch(API + '?' + usp.toString(), signal ? { signal } : undefined);
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        return resp.json();
    }

    // ── Statistika galvenē ───────────────────────────────────────────────────
    function renderStats(d) {
        const el = $('kk-stats');
        if (!el) return;
        if (!d.total) {
            el.innerHTML = '<span class="kk-stat-warn">Datubāze vēl nav sinhronizēta — paziņojumi parādīsies pēc pirmās datu ielādes.</span>';
            return;
        }
        const src = d.sources || {};
        const nationalTotal = Object.keys(src).filter((k) => k !== 'TED').reduce((s, k) => s + src[k], 0);
        // Nacionālo valstu skaits = distinktās ne-TED valstis (serveris; nevis avotu
        // atslēgu skaits — UK=3, DK=2 avoti + IFI nav valstis). Fallback uz avotiem.
        const natCountries = d.national_countries
            || Object.keys(src).filter((k) => k !== 'TED' && src[k] > 0).length;
        el.innerHTML =
            '<span><strong>' + d.total.toLocaleString('lv-LV') + '</strong> paziņojumi</span>' +
            '<span><strong>' + (d.countries || 0) + '</strong> valstis</span>' +
            (src.TED ? '<span>TED (ES): <strong>' + src.TED.toLocaleString('lv-LV') + '</strong></span>' : '') +
            (nationalTotal ? '<span>nacionālie (' + natCountries + ' valstis): <strong>' + nationalTotal.toLocaleString('lv-LV') + '</strong></span>' : '') +
            (d.last_sync ? '<span>atjaunināts <strong>' + esc(d.last_sync) + '</strong></span>' : '');
    }

    async function loadStats() {
        try {
            renderStats(await apiGet({ action: 'stats' }));
        } catch (e) { /* statistika nav kritiska */ }
    }

    // ── Avotu panelis (kreisā kolonna) ───────────────────────────────────────
    function fmtN(v) { return (v || 0).toLocaleString('lv-LV'); }

    function renderSources(d) {
        const by = d.sources || {};
        // "Visi avoti" = summa pa visiem ĪSTAJIEM avotiem. '#'-prefiksa rindas
        // (#SE/#MT/#CY/#GB/#GE/#TR/#AZ/#AM) ir TED pārgriezumi pa valstīm (valstīm
        // bez nacionālās plūsmas) — tie jau ieskaitīti TED, tāpēc kopsummā tos
        // IZLAIŽAM, lai nedubultotos (citādi kopā rāda ~2200 par daudz).
        const total = { iepirkumi: 0, rezultati: 0, izmainas: 0, citi: 0 };
        Object.keys(by).forEach((s) => {
            if (s.charAt(0) === '#') return;
            ['iepirkumi', 'rezultati', 'izmainas', 'citi'].forEach((c) => { total[c] += by[s][c] || 0; });
        });

        const frag = document.createDocumentFragment();
        let natHeaderAdded = false;
        let lastRegion = null;
        SOURCE_META.forEach((m) => {
            let counts;
            if (m.code === '') counts = total;
            else if (m.codes) {
                counts = { iepirkumi: 0, rezultati: 0, izmainas: 0, citi: 0 };
                m.codes.forEach((cd) => {
                    const b = by[cd] || {};
                    ['iepirkumi', 'rezultati', 'izmainas', 'citi'].forEach((c) => { counts[c] += b[c] || 0; });
                });
            } else {
                counts = by[m.code] || {};
            }
            if (m.code !== '' && m.code !== 'TED' && !natHeaderAdded) {
                const h = document.createElement('div');
                h.className = 'kk-sources-group';
                h.textContent = 'Nacionālie (mazie) konkursi';
                frag.appendChild(h);
                natHeaderAdded = true;
            }
            // Reģiona apakšvirsraksts, kad sākas jauns reģions (Baltija augšā —
            // Latvijas lietotājam tuvākais; tālāk pēc attāluma no Latvijas).
            if (m.region && m.region !== lastRegion) {
                const rh = document.createElement('div');
                rh.className = 'kk-sources-region';
                rh.textContent = REGION_LABEL[m.region] || m.region;
                frag.appendChild(rh);
                lastRegion = m.region;
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'kk-src' + (state.srcKey === m.code ? ' active' : '');
            btn.dataset.source = m.code;
            btn.innerHTML =
                '<span class="kk-src-head"><span class="kk-src-flag">' + m.flag + '</span>' +
                '<span class="kk-src-name">' + esc(m.name) + '</span></span>' +
                '<span class="kk-src-sub">' + esc(m.sub) + '</span>' +
                '<span class="kk-src-counts">' +
                    '<span class="kk-cnt kk-cnt-a" title="Aktīvie konkursi">' + fmtN(counts.iepirkumi) + '</span>' +
                    '<span class="kk-cnt kk-cnt-r" title="Rezultāti">' + fmtN(counts.rezultati) + '</span>' +
                    '<span class="kk-cnt kk-cnt-g" title="Grozījumi">' + fmtN(counts.izmainas) + '</span>' +
                    '<span class="kk-cnt kk-cnt-c" title="Citi paziņojumi">' + fmtN(counts.citi) + '</span>' +
                '</span>';
            btn.addEventListener('click', () => {
                // Valsts rinda (Zviedrija): avota filtra nav, filtrē pēc pasūtītāja valsts
                state.srcKey = m.code;
                state.source = m.country ? '' : m.code;
                state.country = m.country || '';
                countryEl.value = state.country;
                // Avota maiņa atceļ meklēšanu — citādi ielāde un vecais atslēgvārds
                // strādātu reizē (arī vēl nenostrādājušais debounce taimeris).
                clearTimeout(debounce);
                searchEl.value = '';
                state.q = '';
                // Sinhroni, nevis loadBuyers() iekšienē: resetAndFetch() zemāk izpildās
                // uzreiz, un citādi tas vēl paspētu filtrēt pēc cita avota pasūtītāja.
                state.buyer = '';
                buyerEl.value = '';
                buyerClearEl.classList.add('hidden');
                closeBuyerPop();
                sourcesListEl.querySelectorAll('.kk-src').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                loadCountries();
                loadCpv();
                loadBuyers();
                resetAndFetch();
            });
            frag.appendChild(btn);
        });
        sourcesListEl.innerHTML = '';
        sourcesListEl.appendChild(frag);
    }

    async function loadSources() {
        try {
            renderSources(await apiGet({ action: 'sources' }));
        } catch (e) {
            // Ja panelis jau uzzīmēts (no momentuzņēmuma), kļūda to neaizstāj —
            // labāk nedaudz novecojuši skaiti nekā strādājoša paneļa nodzēšana.
            if (!sourcesListEl.querySelector('.kk-src')) {
                sourcesListEl.innerHTML = '<div class="kk-empty">Avotus neizdevās ielādēt.</div>';
            }
        }
    }

    // ── Filtru izvēlnes ──────────────────────────────────────────────────────
    // Kārtas nr. kā fetchPage/fetchBuyers: ātri pārslēdzot avotu un cilni, divas
    // atbildes ir lidojumā, un lēnākā (vecā) citādi pārrakstītu jaunāko —
    // izvēlnē paliktu citas kategorijas skaiti un diapazoni.
    let countriesSeq = 0;
    async function loadCountries() {
        try {
            const saved = state.country;
            const seq = ++countriesSeq;
            const d = await apiGet({ action: 'countries', cat: state.cat, source: state.source, q: state.q });
            if (seq !== countriesSeq) return; // novecojusi atbilde
            countryEl.innerHTML = '<option value="">Visas valstis</option>';
            (d.countries || []).forEach((c) => {
                const o = document.createElement('option');
                o.value = c.code;
                // Rezultātiem skaits bez loga maldina: 1000 griesti katrai valstij
                // nogriež citu dziļumu (DE ~4 dienas, LV viss 60 d) — rāda diapazonu.
                const range = state.cat === 'rezultati' ? fmtRange(c.from, c.to) : '';
                o.textContent = countryLabel(c.code) + ' (' + c.count.toLocaleString('lv-LV') +
                    (range ? ' · ' + range : '') + ')';
                countryEl.appendChild(o);
            });
            countryEl.value = saved;
            if (countryEl.value !== saved) { state.country = ''; }
        } catch (e) { /* atstāj esošo */ }
    }

    let cpvSeq = 0;
    async function loadCpv() {
        try {
            const saved = state.cpv;
            const seq = ++cpvSeq;
            const d = await apiGet({ action: 'cpv', cat: state.cat, source: state.source, q: state.q });
            if (seq !== cpvSeq) return; // novecojusi atbilde
            cpvEl.innerHTML = '<option value="">Visas jomas</option>';
            (d.divisions || []).forEach((r) => {
                const name = CPV_DIV[r.div];
                if (!name) return;
                const o = document.createElement('option');
                o.value = r.div;
                o.textContent = name + ' (' + Number(r.cnt).toLocaleString('lv-LV') + ')';
                cpvEl.appendChild(o);
            });
            cpvEl.value = saved;
            if (cpvEl.value !== saved) { state.cpv = ''; }
        } catch (e) { /* atstāj esošo */ }
    }

    // ── Pasūtītāja kombinētais lauks ─────────────────────────────────────────
    // Nolaižamā poga rāda biežākos (KONKURSI_BUYER_SUGGEST_MAX), bet rakstīšana meklē
    // serverī pa VISIEM attiecīgā avota/valsts pasūtītājiem, ne tikai tiem biežākajiem.
    let buyerTop = [];      // biežākie — nolaižamajam sarakstam
    let buyerOpts = [];     // pašlaik redzamie varianti
    let buyerActive = -1;   // iezīmētais variants bultiņu navigācijai
    let buyerDebounce = null;
    let buyerSeq = 0;       // meklējuma kārtas nr.; novecojušas atbildes atmet

    async function fetchBuyers(bq) {
        const seq = ++buyerSeq;
        const d = await apiGet({
            action: 'buyers', cat: state.cat, source: state.source,
            country: state.country, bq: bq || '', q: state.q,
        });
        return seq === buyerSeq ? (d.buyers || []) : null;
    }

    /** Pārlādē biežāko sarakstu — pēc avota, valsts vai kategorijas maiņas. */
    async function loadBuyers() {
        try {
            const list = await fetchBuyers('');
            if (list) buyerTop = list;
        } catch (e) { /* atstāj esošo */ }
    }

    /** Ietonē sakritušo daļu variantā. */
    function buyerHighlight(name, q) {
        if (!q) return esc(name);
        const i = name.toLowerCase().indexOf(q.toLowerCase());
        if (i < 0) return esc(name);
        return esc(name.slice(0, i)) + '<mark>' + esc(name.slice(i, i + q.length)) + '</mark>'
             + esc(name.slice(i + q.length));
    }

    function openBuyerPop(list, query) {
        buyerOpts = list;
        buyerActive = -1;
        buyerPopEl.innerHTML = list.length
            ? list.map((b, i) =>
                '<li class="kk-combo-opt" role="option" data-i="' + i + '"' +
                (b.name === state.buyer ? ' aria-selected="true"' : '') + '>' +
                    '<span>' + buyerHighlight(String(b.name), query) + '</span>' +
                    '<span class="kk-combo-cnt">' + Number(b.cnt).toLocaleString('lv-LV') + '</span>' +
                '</li>').join('')
            : '<li class="kk-combo-note">Nav atbilstošu pasūtītāju.</li>';
        buyerPopEl.classList.remove('hidden');
        buyerEl.setAttribute('aria-expanded', 'true');
    }

    function closeBuyerPop() {
        buyerPopEl.classList.add('hidden');
        buyerEl.setAttribute('aria-expanded', 'false');
        buyerActive = -1;
    }

    /** Iestata filtru uz izvēlēto pasūtītāju (vai noņem, ja b == null). */
    function pickBuyer(b) {
        const name = b ? String(b.name) : '';
        const changed = name !== state.buyer;
        state.buyer = name;
        buyerEl.value = name;
        buyerClearEl.classList.toggle('hidden', name === '');
        closeBuyerPop();
        if (changed) resetAndFetch();
    }

    // ── Saraksts ─────────────────────────────────────────────────────────────
    // Katram pieprasījumam sava kārtas nr. Filtru maiņa to palielina, tāpēc lidojumā
    // esošas atbildes kļūst novecojušas un tiek atmestas — citādi lēnākais pieprasījums
    // pārrakstītu jaunākā rezultātu (piem., pasūtītāja izvēle un avota maiņa cita pēc citas).
    let reqSeq = 0;
    let listAbort = null;
    let snapProvisional = false; // saraksts pašlaik rāda momentuzņēmuma kartiņas

    /** Momentuzņēmuma ieraksts pašreizējam skatam vai null, ja skats nav "tīrs"
     *  noklusējums (meklēšana/filtri/kārtošana/cita cilne — tiem kartiņu failā nav). */
    function snapshotEntry() {
        if (!snap) return null;
        if (state.cat !== 'iepirkumi' || state.sort !== 'jaunakie') return null;
        if (state.q || state.nature || state.activity || state.cpv || state.buyer) return null;
        const e = snap.lists[state.srcKey];
        if (!e || !Array.isArray(e.notices)) return null;
        // Valsts rindām (#GB) valsts filtrs pieder pašai rindai; citur tam jābūt tukšam.
        const rowCountry = state.srcKey.charAt(0) === '#' ? state.srcKey.slice(1) : '';
        if (state.country !== rowCountry) return null;
        return e;
    }

    /** Uzzīmē momentuzņēmuma kartiņas un ciļņu skaitus — tas pats izskats, ko pēc
     *  brīža atnesīs īstā API atbilde, tāpēc nomaiņa ir nemanāma. */
    function renderSnapshotList(e) {
        ['iepirkumi', 'rezultati', 'izmainas', 'citi'].forEach((c) => {
            const el = $('kk-cnt-' + c);
            if (el) el.textContent = (e.counts && e.counts[c] != null ? e.counts[c] : 0).toLocaleString('lv-LV');
        });
        listEl.innerHTML = '';
        const noteHtml = COUNTRY_NOTE[state.srcKey];
        if (noteHtml) {
            const b = document.createElement('div');
            b.className = 'kk-se-banner';
            b.innerHTML = noteHtml;
            listEl.appendChild(b);
        }
        if (e.notices.length) {
            renderCards(e.notices);
        } else {
            listEl.insertAdjacentHTML('beforeend',
                '<div class="kk-empty">Pēc norādītajiem kritērijiem nekas netika atrasts.</div>');
        }
        // Diskrēts indikators zem priekšskata, ka pilnais saraksts vēl ceļā.
        setMoreStatus(e.counts && e.counts.iepirkumi > e.notices.length
            ? '<span class="kk-spinner"></span>Ielādē pilno sarakstu…' : '');
    }

    function resetAndFetch() {
        state.page = 1;
        state.hasMore = false;
        state.loading = false; // atbrīvo sargu: iepriekšējā atbilde tiks atmesta pēc kārtas nr.
        reqSeq++;
        if (listAbort) listAbort.abort(); // pārtrauc lidojumā esošo — lai novecojušie nekrājas
        // Noklusējuma skatiem kartiņas ir gatavas jau HTML (momentuzņēmums) — rāda
        // tās uzreiz ritenīša vietā; īstā atbilde pēc brīža klusi nomaina saturu.
        const snapE = snapshotEntry();
        snapProvisional = !!snapE;
        if (snapE) {
            renderSnapshotList(snapE);
        } else {
            listEl.innerHTML = '<div class="kk-loading"><div class="kk-spinner"></div>Ielādē…</div>';
            setMoreStatus('');
        }
        resetDetailPane();
        fetchPage();
    }

    async function fetchPage() {
        if (state.loading) return;
        state.loading = true;
        const seq = reqSeq;
        listAbort = new AbortController();
        try {
            const d = await apiGet({
                action: 'list', cat: state.cat, page: state.page, q: state.q,
                source: state.source, country: state.country, nature: state.nature,
                activity: state.activity, cpv: state.cpv, buyer: state.buyer, sort: state.sort,
            }, listAbort.signal);
            if (seq !== reqSeq) return; // filtri pa to laiku mainījušies — atbilde novecojusi

            ['iepirkumi', 'rezultati', 'izmainas', 'citi'].forEach((c) => {
                const el = $('kk-cnt-' + c);
                if (el && d.counts) el.textContent = (d.counts[c] ?? 0).toLocaleString('lv-LV');
            });

            if (state.page === 1) {
                snapProvisional = false; // īstie dati nomaina momentuzņēmuma priekšskatu
                listEl.innerHTML = '';
                // Valstu saraksti izskatās nepilnīgi bez paskaidrojuma, kāpēc tur nav
                // mazo konkursu — rāda to uzreiz, negaidot, kamēr lietotājs atver ierakstu.
                const noteHtml = COUNTRY_NOTE[state.srcKey];
                if (noteHtml) {
                    const b = document.createElement('div');
                    b.className = 'kk-se-banner';
                    b.innerHTML = noteHtml;
                    listEl.appendChild(b);
                }
            }

            if (!d.notices || d.notices.length === 0) {
                if (state.page === 1) {
                    // Pievieno, nevis pārraksta — citādi pazustu Zviedrijas paskaidrojums
                    listEl.insertAdjacentHTML('beforeend',
                        '<div class="kk-empty">Pēc norādītajiem kritērijiem nekas netika atrasts.</div>');
                }
                state.hasMore = false;
            } else {
                renderCards(d.notices);
                // Ja lietotājs jau atvēra ierakstu no momentuzņēmuma kartiņas, pilnā
                // saraksta pārrenderēšana izcēlumu nodzēstu — atjauno to pēc id.
                if (state.page === 1 && state.detailId) {
                    const act = listEl.querySelector('.kk-card[data-id="' + CSS.escape(state.detailId) + '"]');
                    if (act) act.classList.add('active');
                }
                state.hasMore = d.has_more === true;
                state.page++;
            }
            setMoreStatus(state.hasMore ? '' : (listEl.children.length ? 'Saraksta beigas.' : ''));
        } catch (err) {
            if (err && err.name === 'AbortError') return; // apzināti pārtraukts — kluss
            if (seq !== reqSeq) return;
            state.hasMore = false;
            if (state.page === 1) {
                if (snapProvisional) {
                    // Momentuzņēmuma kartiņas paliek — sākotnējie dati ir labāki par
                    // tukšu kļūdas ekrānu; brīdinām tikai statusa rindā.
                    setMoreStatus('⚠️ Neizdevās ielādēt pilno sarakstu (' + esc(err.message) + ') — rādu sākotnējos datus.');
                } else {
                    listEl.innerHTML = '<div class="kk-empty">⚠️ Neizdevās ielādēt datus (' + esc(err.message) + ').</div>';
                }
            } else {
                setMoreStatus('⚠️ Neizdevās ielādēt vairāk (' + esc(err.message) + ').');
            }
        } finally {
            if (seq === reqSeq) state.loading = false;
            // Ja saraksts ir īsāks par ekrānu, sensors paliek redzams un IntersectionObserver
            // vairs neizraisās (tas reaģē tikai uz pāreju) — tāpēc pārbauda pats.
            maybeFetchMore();
        }
    }

    /** Statusa rinda zem saraksta: tukša, ielādes indikators vai beigu/kļūdas ziņa. */
    function setMoreStatus(html) {
        moreStatusEl.innerHTML = html;
    }

    /**
     * Ielādē nākamo lapu, ja sensors ir tuvu redzamībai un ir ko ielādēt.
     * Platajos ekrānos saraksts ritinās savā kolonnā (.kk-main ir overflow-y: auto),
     * tāpēc mēra pret konteineru. Šaurajos (≤1100px) CSS uzliek overflow-y: visible —
     * konteiners aug līdzi saturam un tā apakša sakrīt ar satura apakšu, tāpēc mērot
     * pret to nosacījums būtu patiess vienmēr un ķēdē ielādētu visas lapas; tur
     * ritināmais konteksts ir logs, un mēra pret window.innerHeight.
     */
    function maybeFetchMore() {
        if (state.loading || !state.hasMore || !sentinelEl) return;
        const winH = window.innerHeight || document.documentElement.clientHeight || 0;
        const box = (scrollEl && getComputedStyle(scrollEl).overflowY !== 'visible')
            ? scrollEl.getBoundingClientRect().bottom : winH;
        if (sentinelEl.getBoundingClientRect().top <= box + 400) {
            setMoreStatus('<span class="kk-spinner"></span>Ielādē vairāk…');
            fetchPage();
        }
    }

    function renderCards(notices) {
        const frag = document.createDocumentFragment();
        notices.forEach((n) => {
            const card = document.createElement('article');
            card.className = 'kk-card' + (TI_SOURCES.has(n.source) ? ' kk-card-ti' : '');
            card.tabIndex = 0;
            card.setAttribute('role', 'button');
            card.dataset.id = n.id; // izcēluma atjaunošanai pēc saraksta pārrenderēšanas

            const days = daysLeft(n.deadline_date);
            let deadlineHtml = '';
            if (n.category === 'rezultati') {
                deadlineHtml = n.budget != null
                    ? '<span class="kk-deadline kk-sum">' + esc(fmtMoney(n.budget, n.currency)) + '</span>' : '';
            } else if (n.deadline_date) {
                const urgent = days !== null && days <= 3;
                deadlineHtml = '<span class="kk-deadline' + (urgent ? ' urgent' : '') + '">termiņš ' + esc(fmtDate(n.deadline_date)) +
                    (days !== null && days >= 0 ? ' (' + (days === 0 ? 'šodien' : 'vēl ' + days + ' d.') + ')' : '') + '</span>';
            }

            let divName = cpvDivLabel(n.main_cpv);
            if (divName && divName === NATURE[n.procure_nature]) divName = null; // nedublē vienādas nozīmītes
            const euBadge = (n.funding_program && n.funding_program !== '-' && n.funding_program !== 'no-eu-funds')
                ? '<span class="kk-badge kk-badge-eu">ES fondi</span>' : '';

            card.innerHTML =
                '<div class="kk-card-top">' +
                    '<span class="kk-card-num' + (n.source !== 'TED' ? ' kk-num-nat' : '') + '">' + esc(numLabel(n)) + '</span>' +
                    '<span class="kk-card-country">' + esc(countryLabel(n.buyer_country)) + '</span>' +
                '</div>' +
                '<h3 class="kk-card-title" data-orig="' + esc(n.title || '–') + '"' + (n.title_lv ? ' data-lv="' + esc(n.title_lv) + '"' : '') + '>'
                    + esc((state.lang === 'lv' && n.title_lv) ? n.title_lv : (n.title || '–')) + '</h3>' +
                '<div class="kk-card-buyer">' + esc(n.buyer_name || '–') + '</div>' +
                '<div class="kk-card-badges">' +
                    (TI_SOURCES.has(n.source) ? '<span class="kk-badge kk-badge-ti">Tirgus izpēte</span>' : '') +
                    (NATURE[n.procure_nature] ? '<span class="kk-badge">' + NATURE[n.procure_nature] + '</span>' : '') +
                    (divName ? '<span class="kk-badge kk-badge-cpv">' + esc(divName) + '</span>' : '') +
                    (ACTIVITY[n.buyer_activity] ? '<span class="kk-badge kk-badge-act">' + ACTIVITY[n.buyer_activity] + '</span>' : '') +
                    euBadge +
                '</div>' +
                '<div class="kk-card-footer">' +
                    (deadlineHtml || '<span></span>') +
                    // Comdia datumus nesniedz vispār — tukša 'publicēts –' izskatītos pēc kļūdas
                    (n.publication_date
                        ? '<span class="kk-card-pub">publicēts ' + esc(fmtDate(n.publication_date)) + '</span>'
                        : '<span class="kk-card-pub">termiņš avota lapā</span>') +
                '</div>';

            const open = () => {
                listEl.querySelectorAll('.kk-card.active').forEach((c) => c.classList.remove('active'));
                card.classList.add('active');
                openDetail(n.id);
            };
            card.addEventListener('click', open);
            card.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
            frag.appendChild(card);
        });
        listEl.appendChild(frag);
    }

    // ── Detaļas: darbvirsmā labais panelis, mobilajā modālis ─────────────────
    function detailTarget() {
        return window.matchMedia('(min-width: 1100px)').matches ? detailBodyEl : modalBody;
    }

    // ── Deep-link uz konkrētu konkursu ───────────────────────────────────────
    // URL atspoguļo atvērto konkursu (?id=<paziņojuma id>), lai to varētu koplietot.
    // Atverot lapu ar šādu saiti, konkrētais konkurss tiek automātiski parādīts.
    function noticeShareUrl(id) {
        return location.origin + location.pathname + '?id=' + encodeURIComponent(id);
    }
    function setNoticeUrl(id) {
        try { history.replaceState(null, '', location.pathname + '?id=' + encodeURIComponent(id)); } catch (e) { /* ignorē (piem. file://) */ }
    }
    function clearNoticeUrl() {
        try { if (new URLSearchParams(location.search).has('id')) history.replaceState(null, '', location.pathname); } catch (e) { /* ignorē */ }
    }

    async function openDetail(id) {
        state.detailId = id;
        const target = detailTarget();
        if (target === modalBody) {
            modal.classList.remove('hidden');
            document.body.classList.add('kk-noscroll');
        } else {
            $('kk-detailpane').scrollTop = 0;
        }
        target.innerHTML = '<div class="kk-loading"><div class="kk-spinner"></div>Ielādē…</div>';
        try {
            const d = await apiGet({ action: 'detail', id: id });
            if (d.notice) { renderDetail(d.notice, target); setNoticeUrl(id); }
            else target.innerHTML = '<div class="kk-empty">Paziņojums netika atrasts.</div>';
        } catch (e) {
            target.innerHTML = '<div class="kk-empty">Neizdevās ielādēt datus.</div>';
        }
    }

    function resetDetailPane() {
        state.detailId = '';
        detailBodyEl.innerHTML =
            '<div class="kk-empty-detail">' +
                '<svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">' +
                    '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/>' +
                    '<line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="11" y2="17"/></svg>' +
                '<p>Izvēlies konkursu sarakstā,<br>lai apskatītu atšifrējumu</p>' +
            '</div>';
    }

    function closeDetail() {
        modal.classList.add('hidden');
        document.body.classList.remove('kk-noscroll');
        modalBody.innerHTML = '';
        clearNoticeUrl();
    }

    function row(label, value, isHtml) {
        const v = isHtml ? (value || '–') : esc(fv(value));
        if (v === '–') return '';
        return '<div class="kk-drow"><div class="kk-dlabel">' + esc(label) + '</div><div class="kk-dvalue">' + v + '</div></div>';
    }

    function renderDetail(n, target) {
        target = target || modalBody;
        const lots = Array.isArray(n.lots) ? n.lots : [];
        const orgs = Array.isArray(n.organizations) ? n.organizations : [];
        const cpv = Array.isArray(n.cpv_codes) ? n.cpv_codes : [];
        const contact = n.notice_contact && typeof n.notice_contact === 'object' ? n.notice_contact : {};

        const cpvHtml = cpv.length
            ? cpv.map((c) => {
                const dn = cpvDivLabel(c);
                return '<span class="kk-cpv-tag" title="' + esc(dn || '') + '">' + esc(c) + '</span>';
              }).join(' ')
            : null;

        const days = daysLeft(n.deadline_date);
        const deadlineTxt = n.deadline_date
            ? fmtDate(n.deadline_date) + (n.deadline_time ? ' ' + String(n.deadline_time).substring(0, 5) : '') +
              (days !== null && days >= 0 ? ' — ' + (days === 0 ? 'šodien!' : 'atlikušas ' + days + ' dienas') : ' (termiņš beidzies)')
            : null;

        const lotsHtml = lots.length
            ? lots.map((l, i) =>
                '<div class="kk-lot">' +
                    '<div class="kk-lot-title">' + esc(l.id || ('Daļa ' + (i + 1))) + (l.name ? ' — ' + esc(l.name) : '') + '</div>' +
                    (l.description ? '<p class="kk-lot-desc">' + esc(l.description) + '</p>' : '') +
                    '<div class="kk-lot-meta">' +
                        (NATURE[l.nature] ? '<span>' + NATURE[l.nature] + '</span>' : '') +
                        // budget<=0 = avota sentinels "nav atklāts" (kā virslīmeņa sargs store.php)
                        (l.budget != null && l.budget > 0 ? '<span>' + esc(fmtMoney(l.budget, n.currency)) + '</span>' : '') +
                        (l.deadline_date ? '<span>termiņš ' + esc(fmtDate(l.deadline_date)) + '</span>' : '') +
                        ((l.cpv_codes || []).length ? '<span>CPV ' + esc(l.cpv_codes.join(', ')) + '</span>' : '') +
                    '</div>' +
                '</div>').join('')
            : '';

        const orgsHtml = orgs.length
            ? orgs.map((o) =>
                '<div class="kk-org">' +
                    '<div class="kk-org-name">' + esc(o.name || o.id || '–') + '</div>' +
                    '<div class="kk-org-meta">' +
                        (o.reg_number ? '<span>Reģ. Nr. ' + esc(o.reg_number) + '</span>' : '') +
                        ((o.city || o.country) ? '<span>' + esc([o.city, countryLabel(o.country)].filter(Boolean).join(', ')) + '</span>' : '') +
                        (o.email ? '<span><a href="mailto:' + esc(o.email) + '">' + esc(o.email) + '</a></span>' : '') +
                        (o.phone ? '<span>' + esc(o.phone) + '</span>' : '') +
                        (o.website ? '<span><a href="' + esc(o.website.startsWith('http') ? o.website : 'https://' + o.website) + '" target="_blank" rel="noopener nofollow">mājaslapa</a></span>' : '') +
                    '</div>' +
                '</div>').join('')
            : '';

        let links = '';
        if (SOURCE_LINK_LABEL[n.source]) {
            if (n.document_url && n.document_url !== '-') {
                // Tikai http(s) — avota XML/JSON laukā teorētiski var būt cita shēma
                const du = n.document_url.startsWith('http') ? n.document_url : 'https://' + n.document_url.replace(/^[a-z+.-]+:\/*/i, '');
                links += '<a class="kk-link-btn kk-link-primary" href="' + esc(du) + '" target="_blank" rel="noopener nofollow">' + SOURCE_LINK_LABEL[n.source] + '</a>';
            }
            if (n.buyer_profile_url && n.buyer_profile_url !== '-' && n.buyer_profile_url !== n.document_url) {
                const u2 = n.buyer_profile_url.startsWith('http') ? n.buyer_profile_url : 'https://' + n.buyer_profile_url;
                // Comdia otrā saite ved uz pašvaldības konkursu SARAKSTU, nevis uz
                // dokumentāciju — vispārīgā etiķete te maldinātu.
                const lbl2 = n.source === 'COMDIA' ? '🏛 Visi šīs pašvaldības konkursi' : buyerProfileLabel(u2);
                links += '<a class="kk-link-btn" href="' + esc(u2) + '" target="_blank" rel="noopener nofollow">' + lbl2 + '</a>';
            }
        } else if (n.source === 'IUB') {
            let hasPrimary = false;
            if (n.document_url && n.document_url !== '-') {
                const u = n.document_url.startsWith('http') ? n.document_url : 'https://' + n.document_url;
                const isEis = u.includes('eis.gov.lv');
                // ~2% pasūtītāju IUB paziņojumā ieliek EIS organizācijas lapu, nevis konkrēto
                // iepirkumu. Tad saite ved uz sarakstu — nesolām dokumentāciju, ko tā nedod,
                // un primārā paliek IUB reģistra saite.
                const isOrgList = isEis && /\/Organizer\//i.test(u);
                const label = isOrgList ? '🏢 Pasūtītāja iepirkumi EIS sistēmā'
                            : isEis     ? '📄 Iepirkuma dokumentācija (EIS)'
                            :             '📄 Iepirkuma dokumentācija';
                hasPrimary = !isOrgList;
                links += '<a class="kk-link-btn' + (hasPrimary ? ' kk-link-primary' : '') +
                    '" href="' + esc(u) + '" target="_blank" rel="noopener nofollow">' + label + '</a>';
            }
            // notices.id IR paziņojuma GUID IUB eForms skatītājā → tieša saite bez meklēšanas.
            // Ja id nav GUID formā (cits imports), atkāpjas uz veco meklētāju.
            const nr = n.publication_number && n.publication_number !== '-' ? ' (Nr. ' + esc(n.publication_number) + ')' : '';
            if (IUB_GUID_RE.test(String(n.id || ''))) {
                // Ja EIS saite ved tikai uz sarakstu, tuvākais avots ir šis — tad tas ir primārais.
                links += '<a class="kk-link-btn' + (hasPrimary ? '' : ' kk-link-primary') +
                    '" href="https://eformsb.pvs.iub.gov.lv/show/' + encodeURIComponent(n.id) + '" target="_blank" rel="noopener">🇱🇻 Paziņojums IUB reģistrā' + nr + '</a>';
            } else if (nr) {
                links += '<a class="kk-link-btn" href="https://info.iub.gov.lv/lv/meklet?q=' + encodeURIComponent(n.publication_number) + '" target="_blank" rel="noopener">🇱🇻 IUB publikāciju meklētājs' + nr + '</a>';
            }
        } else if (n.source === 'TED') {
            links += '<a class="kk-link-btn kk-link-primary" href="' + esc(tedUrl(n)) + '" target="_blank" rel="noopener">🇪🇺 Oficiālais paziņojums TED portālā</a>';
            if (n.document_url && n.document_url !== '-') {
                const u = n.document_url.startsWith('http') ? n.document_url : 'https://' + n.document_url;
                links += '<a class="kk-link-btn" href="' + esc(u) + '" target="_blank" rel="noopener nofollow">📄 Iepirkuma dokumentācija</a>';
            }
        }
        // ANAC (un jebkurš cits avots bez SOURCE_LINK_LABEL, kas nav IUB/TED) apzināti
        // paliek bez ārējās saites: ANAC per-CIG lapa ir salauzta ANAC pusē, un šie
        // paziņojumi TED NEnonāk — tāpēc NErādām nederīgu TED saiti. CIG (publication_
        // number) redzams atšifrējumā, lietotājs to var atrast ANAC meklētājā.
        if (!SOURCE_LINK_LABEL[n.source] && n.buyer_profile_url && n.buyer_profile_url !== '-' && n.buyer_profile_url !== n.document_url) {
            const u = n.buyer_profile_url.startsWith('http') ? n.buyer_profile_url : 'https://' + n.buyer_profile_url;
            links += '<a class="kk-link-btn" href="' + esc(u) + '" target="_blank" rel="noopener nofollow">🏛 Pasūtītāja profils</a>';
        }
        // Avota portāla papildu saite (ANAC — vienīgā dzīvā web lapa; KIMDIS — web
        // meklēšana papildus PDF). Sk. SOURCE_PORTAL definīciju.
        if (SOURCE_PORTAL[n.source]) {
            const p = SOURCE_PORTAL[n.source](n);
            links += '<a class="kk-link-btn" href="' + esc(p.url) + '" target="_blank" rel="noopener nofollow">' + p.label + '</a>';
        }

        target.innerHTML =
            '<div class="kk-detail">' +
                // Tulkošanas pamācības poga (augšējais kreisais stūris) — kā pilno
                // sludinājumu iztulkot ar lietotāja pārlūka iebūvēto tulkotāju.
                '<div class="kk-translate-tip">' +
                    '<button type="button" class="kk-ttip-btn" aria-expanded="false" title="Kā iztulkot pilno sludinājumu latviski">ⓘ Tulkot latviski</button>' +
                    '<div class="kk-ttip-pop hidden" role="dialog" aria-label="Tulkošanas pamācība">' +
                        '<button type="button" class="kk-ttip-close" aria-label="Aizvērt">×</button>' +
                        '<div class="kk-ttip-title">Kā iztulkot pilno sludinājumu latviski</div>' +
                        '<p>Virsraksti sarakstā jau ir tulkoti automātiski. Pilno aprakstu un oficiālo paziņojumu (atverot saiti zemāk) vari iztulkot ar sava pārlūka iebūvēto tulkotāju:</p>' +
                        '<ul>' +
                            '<li><strong>Google Chrome:</strong> labais peles klikšķis lapā → <em>“Tulkot uz latviešu valodu”</em> (vai tulkotāja ikona adreses joslā).</li>' +
                            '<li><strong>Microsoft Edge:</strong> labais peles klikšķis → <em>“Tulkot latviski”</em> (vai ikona <em>“aβ”</em> adreses joslā).</li>' +
                            '<li><strong>Safari (Mac/iPhone):</strong> poga <em>“aA”</em> adreses joslā → <em>“Tulkot vietni”</em>.</li>' +
                            '<li><strong>Firefox:</strong> tulkotāja ikona adreses joslā → izvēlies latviešu valodu.</li>' +
                        '</ul>' +
                        '<p class="kk-ttip-note">Tulkojums notiek tavā pārlūkā — tas darbojas arī ārējā portāla lapā, kur pieejama pilnā dokumentācija.</p>' +
                    '</div>' +
                '</div>' +
                '<div class="kk-dbadges">' +
                    '<span class="kk-badge kk-badge-cat kk-cat-' + esc(n.category) + '">' + (CAT_LABEL[n.category] || esc(n.category)) + '</span>' +
                    (TI_SOURCES.has(n.source) ? '<span class="kk-badge kk-badge-ti">Tirgus izpēte</span>' : '') +
                    '<span class="kk-badge' + (n.source !== 'TED' ? ' kk-badge-nat' : '') + '">' + (SOURCE_LABEL[n.source] || esc(n.source)) + '</span>' +
                    '<span class="kk-badge">' + esc(countryLabel(n.buyer_country)) + '</span>' +
                    (NATURE[n.procure_nature] ? '<span class="kk-badge">' + NATURE[n.procure_nature] + '</span>' : '') +
                '</div>' +
                '<h2 class="kk-dtitle">' + esc(n.title || '–') + '</h2>' +
                '<div class="kk-dmeta">' +
                    (n.publication_number ? '<span>' + (SOURCE_PREFIX[n.source] || n.source) + ' Nr. <strong>' + esc(n.publication_number) + '</strong></span>' : '') +
                    (n.publication_date
                        ? '<span>publicēts <strong>' + esc(fmtDate(n.publication_date)) + '</strong></span>'
                        : '<span>datumus šis avots nesniedz — sk. oriģinālu</span>') +
                '</div>' +

                '<div class="kk-dsection"><div class="kk-dsection-title">Galvenā informācija</div>' +
                    row('Pieteikšanās termiņš', deadlineTxt) +
                    row('Paredzamā summa', n.budget != null ? fmtMoney(n.budget, n.currency) : null) +
                    row('Pasūtītājs', n.buyer_name) +
                    row('Pasūtītāja reģ. Nr.', n.buyer_id) +
                    row('Pasūtītāja veids', BUYER_TYPE[n.buyer_type] || (n.buyer_type && n.buyer_type !== '-' ? n.buyer_type : null)) +
                    row('Pamatdarbība', ACTIVITY[n.buyer_activity] || (n.buyer_activity && n.buyer_activity !== '-' ? n.buyer_activity : null)) +
                    row('Procedūra', PROC[n.procedure_type] || (n.procedure_type && n.procedure_type !== '-' ? n.procedure_type : null)) +
                    row('Izpildes vieta', n.main_nuts && n.main_nuts !== '-' ? n.main_nuts : null) +
                    row('ES finansējums', n.funding_program && n.funding_program !== '-'
                        ? (n.funding_program === 'no-eu-funds' ? 'Nav ES fondu'
                            : (n.funding_program === 'EU' ? 'ES fondu līdzfinansējums' : n.funding_program)) : null) +
                    row('CPV kodi', cpvHtml, true) +
                '</div>' +

                (n.description ? '<div class="kk-dsection"><div class="kk-dsection-title">Apraksts</div><p class="kk-ddesc">' + esc(n.description) + '</p></div>' : '') +

                // Kontaktpersonas vārdu un tālruni NERĀDA (sk. ks_public_contact):
                // paziņojumos tie mēdz būt konkrēta darbinieka personas dati, un
                // 49 000 tādu ierakstu apkopojums ir cits lietojums nekā viens
                // paziņojums oriģinālā. Rāda tikai iestādes e-pastu; kontaktpersona
                // vienmēr redzama oriģinālajā paziņojumā, uz kuru ved saite.
                (contact.email
                    ? '<div class="kk-dsection"><div class="kk-dsection-title">Kontakti</div>' +
                        row('E-pasts', '<a href="mailto:' + esc(contact.email) + '">' + esc(contact.email) + '</a>', true) +
                      '</div>'
                    : '') +

                (lotsHtml ? '<div class="kk-dsection"><div class="kk-dsection-title">Iepirkuma daļas (' + lots.length + ')</div>' + lotsHtml + '</div>' : '') +
                (orgsHtml ? '<div class="kk-dsection"><div class="kk-dsection-title">Iesaistītās organizācijas</div>' + orgsHtml + '</div>' : '') +

                '<div class="kk-dlinks">' + (links || '<span class="kk-dsource">Nav ārējo saišu</span>') +
                    '<button type="button" class="kk-link-btn kk-copy-link" data-id="' + esc(n.id) +
                    '" title="Kopēt koplietojamu saiti uz šo konkursu saraksts.lv sadaļā">🔗 Kopēt saiti</button>' +
                '</div>' +
                '<p class="kk-dsource">' +
                    (n.buyer_country === 'SE' && n.source === 'TED'
                        ? SE_NOTE
                        : (SOURCE_NOTE[n.source] || SOURCE_NOTE.TED)) + '</p>' +
            '</div>';
    }

    // ── Notikumi ─────────────────────────────────────────────────────────────
    document.querySelectorAll('.kk-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.kk-tab').forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            state.cat = tab.dataset.cat;
            loadCountries();
            loadCpv();
            loadBuyers();
            resetAndFetch();
        });
    });

    // Virsrakstu valodas pārslēgs: pārslēdz jau ielādēto kartiņu virsrakstus uz vietas
    // (abas versijas ir data-atribūtos) — bez jauna API pieprasījuma.
    function rerenderList() {
        document.querySelectorAll('.kk-card-title').forEach((h) => {
            const lv = h.dataset.lv;
            h.textContent = (state.lang === 'lv' && lv) ? lv : (h.dataset.orig || '–');
        });
    }
    function syncLangButtons() {
        document.querySelectorAll('.kk-lang-btn').forEach((b) => {
            b.classList.toggle('active', b.dataset.lang === state.lang);
        });
    }
    document.querySelectorAll('.kk-lang-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (state.lang === btn.dataset.lang) return;
            state.lang = btn.dataset.lang;
            try { localStorage.setItem('kk_lang', state.lang); } catch (e) { /* privātais režīms */ }
            syncLangButtons();
            rerenderList();
        });
    });
    syncLangButtons();

    let debounce;
    searchEl.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            const q = searchEl.value.trim();
            // Meklē tikai no 3 rakstzīmēm — īsāki prefiksi ir dārgi FTS indeksam.
            // Īsāka ievade = nefiltrēts saraksts (tas pats, kas tukša meklēšana).
            const eff = q.length >= 3 ? q : '';
            if (eff === state.q) return;
            state.q = eff;
            // Izvēlņu skaitļi seko atslēgvārdam: iekavās rāda atrasto, ne kopējo.
            loadCountries();
            loadCpv();
            loadBuyers();
            resetAndFetch();
        }, 1500);
    });
    countryEl.addEventListener('change', () => {
        state.country = countryEl.value;
        // Valsts rindas izcēlums der tikai tad, ja valsts filtrs joprojām ir tā valsts
        const cur = SOURCE_META.find((m) => m.code === state.srcKey);
        if (cur && cur.country && cur.country !== state.country) {
            state.srcKey = state.source;
            sourcesListEl.querySelectorAll('.kk-src').forEach((b) => {
                b.classList.toggle('active', b.dataset.source === state.srcKey);
            });
        }
        loadBuyers(); // pasūtītāju ieteikumi seko izvēlētajai valstij
        resetAndFetch();
    });
    // Meklēšana sākas ar pirmo ievadīto burtu.
    buyerEl.addEventListener('input', () => {
        const v = buyerEl.value.trim();
        buyerClearEl.classList.toggle('hidden', v === '');
        clearTimeout(buyerDebounce);
        if (v === '') {
            if (state.buyer !== '') { state.buyer = ''; resetAndFetch(); }
            openBuyerPop(buyerTop, '');
            return;
        }
        buyerDebounce = setTimeout(async () => {
            try {
                const list = await fetchBuyers(v);
                if (list) openBuyerPop(list, v);
            } catch (e) { /* klusi — saraksts paliek iepriekšējais */ }
        }, 200);
    });

    buyerToggleEl.addEventListener('click', () => {
        if (!buyerPopEl.classList.contains('hidden')) { closeBuyerPop(); return; }
        openBuyerPop(buyerTop, '');
        buyerEl.focus();
    });

    buyerClearEl.addEventListener('click', () => { pickBuyer(null); buyerEl.focus(); });

    // mousedown, nevis click: citādi blur aizvērtu sarakstu, pirms klikšķis nostrādā.
    buyerPopEl.addEventListener('mousedown', (e) => {
        const li = e.target.closest('.kk-combo-opt');
        if (!li) return;
        e.preventDefault();
        pickBuyer(buyerOpts[Number(li.dataset.i)]);
    });

    buyerEl.addEventListener('keydown', (e) => {
        const open = !buyerPopEl.classList.contains('hidden');
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!open) { openBuyerPop(buyerTop, ''); return; }
            const n = buyerOpts.length;
            if (!n) return;
            buyerActive = e.key === 'ArrowDown'
                ? (buyerActive + 1) % n
                : (buyerActive <= 0 ? n - 1 : buyerActive - 1);
            const items = buyerPopEl.querySelectorAll('.kk-combo-opt');
            items.forEach((el, i) => el.classList.toggle('active', i === buyerActive));
            if (items[buyerActive]) items[buyerActive].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && open && buyerActive >= 0) {
            e.preventDefault();
            pickBuyer(buyerOpts[buyerActive]);
        } else if (e.key === 'Escape' && open) {
            e.preventDefault();
            closeBuyerPop();
        }
    });

    buyerEl.addEventListener('focus', () => {
        if (buyerEl.value.trim() === '') openBuyerPop(buyerTop, '');
    });

    buyerEl.addEventListener('blur', () => {
        setTimeout(() => {
            closeBuyerPop();
            // Nepabeigts teksts laukā nedrīkst palikt — atjauno faktisko izvēli.
            if (buyerEl.value !== state.buyer) buyerEl.value = state.buyer;
            buyerClearEl.classList.toggle('hidden', state.buyer === '');
        }, 150);
    });
    cpvEl.addEventListener('change', () => { state.cpv = cpvEl.value; resetAndFetch(); });
    natureEl.addEventListener('change', () => { state.nature = natureEl.value; resetAndFetch(); });
    sortEl.addEventListener('change', () => { state.sort = sortEl.value; resetAndFetch(); });

    $('kk-reset').addEventListener('click', () => {
        state.q = state.country = state.nature = state.activity = state.cpv = state.buyer = '';
        state.source = state.srcKey = DEFAULT_SOURCE;
        state.sort = 'jaunakie';
        searchEl.value = '';
        countryEl.value = '';
        cpvEl.value = '';
        natureEl.value = '';
        buyerEl.value = '';
        buyerClearEl.classList.add('hidden');
        closeBuyerPop();
        sortEl.value = 'jaunakie';
        sourcesListEl.querySelectorAll('.kk-src').forEach((b) => {
            b.classList.toggle('active', b.dataset.source === DEFAULT_SOURCE);
        });
        loadCountries();
        loadCpv();
        loadBuyers();
        resetAndFetch();
    });

    // Bezgalīgā ritināšana: sensors iedarbojas 400px pirms saraksta beigām.
    // root = ritināmais konteiners, citādi rootMargin attiektos uz logu.
    if (sentinelEl && 'IntersectionObserver' in window) {
        new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) maybeFetchMore();
        }, { root: scrollEl || null, rootMargin: '400px 0px' }).observe(sentinelEl);
    }
    // Ritināšanas notikums ar droseli — gan atkāpe vecākiem pārlūkiem, gan drošības
    // tīkls, ja sensors paliek redzams un IntersectionObserver vairs neizraisās.
    // Klausās gan konteineru (platie ekrāni), gan logu — šaurajos ekrānos .kk-main
    // neritinās (overflow-y: visible) un vienīgais scroll avots ir pats logs.
    {
        let t = null;
        const onScroll = () => {
            if (t) return;
            t = setTimeout(() => { t = null; maybeFetchMore(); }, 150);
        };
        if (scrollEl) scrollEl.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    $('kk-modal-close').addEventListener('click', closeDetail);
    modal.querySelector('.kk-modal-backdrop').addEventListener('click', closeDetail);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeDetail();
    });

    // Drošības tīkls: ikviena ārējā (http/https) saite VIENMĒR atveras jaunā cilnē —
    // arī tad, ja kādam nākotnē pievienotam <a> aizmirstos target="_blank". Deleģēts
    // klikšķa apstrādātājs iestata target pirms pārlūka noklusējuma navigācijas.
    // Iekšējās saites (relatīvie href, mailto:) netiek skartas.
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href^="http"]');
        if (a && !a.target) {
            a.target = '_blank';
            if (!a.rel) a.rel = 'noopener';
        }
    });

    // "ⓘ Tulkot latviski" pamācības popup (deleģēts — atšifrējums pārrenderējas).
    // Poga pārslēdz; × vai klikšķis ārpus loga aizver.
    document.addEventListener('click', (e) => {
        const pop = document.querySelector('.kk-ttip-pop');
        if (!pop) return;
        const btn = e.target.closest('.kk-ttip-btn');
        if (btn) {
            const open = pop.classList.toggle('hidden') === false;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }
        if (e.target.closest('.kk-ttip-close') ||
            (!pop.classList.contains('hidden') && !e.target.closest('.kk-translate-tip'))) {
            pop.classList.add('hidden');
            const b = document.querySelector('.kk-ttip-btn');
            if (b) b.setAttribute('aria-expanded', 'false');
        }
    });

    // "Kopēt saiti" poga atšifrējumā (deleģēts — atšifrējums pārrenderējas katru reizi).
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.kk-copy-link');
        if (!btn) return;
        e.preventDefault();
        const url = noticeShareUrl(btn.dataset.id);
        const flash = () => { btn.textContent = '✓ Saite nokopēta'; setTimeout(() => { btn.textContent = '🔗 Kopēt saiti'; }, 1800); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(flash).catch(() => window.prompt('Kopē saiti:', url));
        } else {
            window.prompt('Kopē saiti:', url);
        }
    });

    // Datu avota paziņojums — noklusējumā sakļauts; poga to izvērš/sakļauj (+/−).
    (function () {
        const notice = document.querySelector('.data-source-notice');
        const toggle = document.querySelector('.notice-toggle');
        if (!notice || !toggle) return;
        toggle.addEventListener('click', () => {
            notice.classList.toggle('expanded');
            toggle.textContent = notice.classList.contains('expanded') ? '−' : '+';
        });
    })();

    // ── Starts ───────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        // Deep-link: ?country=DE atver lapu ar valsts filtru (kājenes valstu saites,
        // ārējās atsauces). Valsts filtrs iet pa VISIEM avotiem, ne noklusējuma
        // Latvijas izlasi — citādi, piem., Vācijai rādītos tukšums.
        const urlCountry = (new URLSearchParams(location.search).get('country') || '').toUpperCase();
        if (/^[A-Z]{2}$/.test(urlCountry)) {
            state.country = urlCountry;
            state.source = '';
            state.srcKey = '';
        }
        // Momentuzņēmums: galvene un avotu panelis parādās uzreiz no iegultajiem
        // datiem (bez API gaidīšanas); loadStats/loadSources zemāk tos pēc brīža
        // klusi atsvaidzina ar īstajām atbildēm. Kartiņas tāpat momentā uzzīmē
        // resetAndFetch (sk. snapshotEntry).
        if (snap) {
            if (snap.stats) renderStats(snap.stats);
            if (snap.sources) renderSources(snap.sources);
        }
        loadStats();
        loadSources();
        loadCountries();
        loadCpv();
        loadBuyers();
        resetAndFetch();
        // Deep-link: ja URL satur ?id=<paziņojuma id>, atver konkrēto konkursu.
        const deepId = new URLSearchParams(location.search).get('id');
        if (deepId) openDetail(deepId);
    });

})();
