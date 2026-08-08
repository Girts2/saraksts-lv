// ==========================================================================
// 1. DAĻA: TEKSTI (Šeit vari labot Politikas un Noteikumu saturu)
// ==========================================================================

const privacyContent = `
    <h3>Privātuma politika</h3>
    <p><strong>1. Vispārīgie noteikumi</strong><br>
    Mēs cienām Jūsu privātumu un apņemamies to sargāt. Šī politika apraksta, kā mēs ievācam un izmantojam datus.</p>
    
    <p><strong>2. Datu vākšana</strong><br>
    Mēs varam vākt tehnisko informāciju (IP adrese, pārlūka versija), lai nodrošinātu vietnes drošību un darbību.</p>

    <p><strong>3. Sīkdatnes un Analītika</strong><br>
    Ar Jūsu piekrišanu mēs izmantojam <em>Google Analytics</em>, lai analizētu apmeklētāju plūsmu. Jūs varat jebkurā brīdī mainīt savu izvēli, noklikšķinot uz "Mainīt sīkdatņu iestatījumus" lapas apakšā.</p>

    <p><strong>4. Datu nodošana trešajām pusēm</strong><br>
    Mēs nenododam Jūsu personīgos datus trešajām pusēm, izņemot likumā noteiktajos gadījumos.</p>

    <p><strong>5. Personu dati no Uzņēmumu reģistra atvērtajiem datiem</strong><br>
    Vietnē tiek atkalizmantoti Latvijas Republikas Uzņēmumu reģistra atvērtie dati no portāla data.gov.lv, tostarp uzņēmumu amatpersonu un dalībnieku vārdi, uzvārdi, amati un kapitāla daļu skaits. Šo datu publiskošanu nosaka likums "Par Latvijas Republikas Uzņēmumu reģistru" un Komerclikums. Mūsu apstrādes mērķis ir komercdarbības vides caurskatāmība un darījumu partneru pārbaude; tiesiskais pamats — Vispārīgās datu aizsardzības regulas (VDAR) 6. panta 1. punkta f) apakšpunkts (leģitīma interese). Ievērojam datu minimizāciju: personas kodus (arī daļēji maskētus) un dzimšanas datus vietnē nepublicējam. Dati tiek regulāri atjaunināti no jaunākajām Uzņēmumu reģistra datu kopām. Iespējamās fizisko personu saistības starp uzņēmumiem tiek noteiktas tikai orientējoši pēc vārda sakritības, tiek rādītas tikai pēc lietotāja pieprasījuma un var būt neprecīzas ("vārda brāļu" risks); oficiālu informāciju sniedz LR Uzņēmumu reģistrs. Likvidētu uzņēmumu lapās datu izpētes panelī fizisko personu vārdus papildu piesardzībai nerādām.</p>

    <p><strong>6. Jūsu tiesības (datu subjektiem)</strong><br>
    Jums ir tiesības pieprasīt piekļuvi saviem datiem, to labošanu, kā arī iebilst pret apstrādi (VDAR 21. pants) — īpaši, ja dati ir neprecīzi, kļūdaini sasaistīti ar citu personu vai attiecas uz sen likvidētu uzņēmumu un to pieejamība rada nopietnu apdraudējumu Jūsu tiesībām. Pamatotus pieprasījumus izskatām bez nepamatotas kavēšanās un attiecīgos ierakstus labojam, ierobežojam vai dzēšam. Pieprasījumus sūtiet uz info@example.com, norādot uzņēmuma reģistrācijas numuru un pamatojumu. Jums ir arī tiesības iesniegt sūdzību Datu valsts inspekcijā (www.dvi.gov.lv).</p>

    <p><strong>7. Kontaktinformācija</strong><br>
    Jautājumu gadījumā lūdzu sazinieties ar mums: info@example.com</p>
`;

const termsContent = `
    <h3>Lietošanas noteikumi</h3>
    <p><strong>1. Ievads</strong><br>
    Apmeklējot šo vietni, Jūs piekrītat šiem noteikumiem.</p>

    <p><strong>2. Intelektuālais īpašums</strong><br>
    Viss saturs šajā vietnē nav aizsargāts ar autortiesībām. Satura pārpublicēšana bez atļaujas ir atļauta.</p>

    <p><strong>3. Atbildības ierobežojums</strong><br>
    Vietnes īpašnieki neuzņemas atbildību par zaudējumiem, kas radušies vietnes lietošanas vai nepieejamības dēļ. Informācijai ir informatīvs raksturs.</p>

    <p><strong>4. Izmaiņas</strong><br>
    Mēs paturam tiesības jebkurā laikā mainīt šos noteikumus bez iepriekšēja brīdinājuma.</p>
`;


// ==========================================================================
// 2. DAĻA: FUNKCIJAS (Logu ģenerēšana)
// ==========================================================================

// Funkcija, kas parāda logu ar tekstu
window.showInfoModal = function(type) {
    // 1. Izvēlamies saturu
    let content = (type === 'privacy') ? privacyContent : termsContent;

    // 2. Izveidojam loga fonu (Overlay)
    let overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:99999; display:flex; justify-content:center; align-items:center; backdrop-filter: blur(2px);';
    overlay.id = 'custom-info-modal';

    // 3. Izveidojam balto kasti (Box)
    let box = document.createElement('div');
    box.style.cssText = 'background:#fff; width:90%; max-width:600px; max-height:80vh; padding:30px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.5); overflow-y:auto; position:relative; font-family: sans-serif; line-height: 1.6; color: #333;';

    // 4. Aizvēršanas poga (X)
    let closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'position:absolute; top:10px; right:15px; background:none; border:none; font-size:30px; cursor:pointer; color:#666;';
    closeBtn.onclick = function() { document.body.removeChild(overlay); };

    // 5. Ieliekam saturu
    let textContainer = document.createElement('div');
    textContainer.innerHTML = content;

    // Saliekam visu kopā
    box.appendChild(closeBtn);
    box.appendChild(textContainer);
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    // Aizvērt, ja uzklikšķina ārpusē
    overlay.onclick = function(e) {
        if (e.target === overlay) document.body.removeChild(overlay);
    };
};


// ==========================================================================
// 3. DAĻA: KONFIGURĀCIJA
// ==========================================================================

if (typeof CookieConsent === 'undefined') {
    console.error("Kļūda: CookieConsent bibliotēka nav atrasta.");
} else {

    window.CC = CookieConsent;

    CookieConsent.run({
        guiOptions: {
            consentModal: {
                layout: "box",
                position: "bottom left",
                equalWeightButtons: true,
                flipButtons: false
            },
            preferencesModal: {
                layout: "box",
                position: "right",
                equalWeightButtons: true,
                flipButtons: false
            }
        },

        categories: {
            necessary: {
                readOnly: true,
                enabled: true
            },
            tracking: {
                enabled: false,
                autoClear: {
                    cookies: [
                        { name: /^_ga/ },
                        { name: /^_gid/ },
                        { name: /^_gat/ }
                    ]
                }
            }
        },

        language: {
            default: "lv",
            autoDetect: "browser",
            translations: {
                lv: {
                    consentModal: {
                        title: "Mēs izmantojam sīkdatnes",
                        description: "Šī vietne izmanto sīkdatnes, lai uzlabotu lietotāja pieredzi un analizētu apmeklējumu statistiku.",
                        acceptAllBtn: "Pieņemt visas",
                        acceptNecessaryBtn: "Noraidīt visas",
                        showPreferencesBtn: "Pārvaldīt izvēli",
                        
                        // SAITES: Tās izsauc mūsu funkciju showInfoModal()
                        footer: `
                            <a href="#" onclick="showInfoModal('privacy'); return false;">Privātuma politika</a>
                            <span style="margin:0 10px; color:#ccc;">|</span>
                            <a href="#" onclick="showInfoModal('terms'); return false;">Lietošanas noteikumi</a>
                        `
                    },
                    preferencesModal: {
                        title: "Sīkdatņu iestatījumi",
                        acceptAllBtn: "Pieņemt visas",
                        acceptNecessaryBtn: "Noraidīt visas",
                        savePreferencesBtn: "Saglabāt izvēli",
                        closeIconLabel: "Aizvērt",
                        sections: [
                            {
                                title: "Nepieciešamās sīkdatnes",
                                description: "Šīs sīkdatnes ir būtiskas vietnes pamatdarbībai.",
                                linkedCategory: "necessary"
                            },
                            {
                                title: "Analītika un Statistika",
                                description: "Mēs izmantojam Google Analytics, lai saprastu, kā apmeklētāji lieto mūsu vietni.",
                                linkedCategory: "tracking"
                            }
                        ]
                    }
                }
            }
        }
    });

    // Poga footerī "Mainīt iestatījumus"
    window.addEventListener('load', function() {
        var settingsLink = document.getElementById("open_preferences_center");
        if (settingsLink) {
            settingsLink.addEventListener("click", function(event){
                event.preventDefault();
                CC.showPreferences();
            });
        }
    });
}