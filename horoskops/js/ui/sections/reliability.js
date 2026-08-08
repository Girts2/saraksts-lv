import { makeBar, makeSection } from '../ui_helpers.js';
import { calculateVedicSaturnReliability } from '../../calculations/vedic/saturn_uzticamiba.js';
import { calculateVedicSunIntegrity } from '../../calculations/vedic/sun_integritate.js';
import { calculateVedicMoonEarthStability } from '../../calculations/vedic/moon_zeme_stabilitate.js';
import { calculateBaZiEarthStability } from '../../calculations/bazi/bazi_zeme_stabilitate.js';
import { calculateBaZiOfficerExecution } from '../../calculations/bazi/bazi_amatpersona_izpildijums.js';
import { calculateBaZiResourceIntegrity } from '../../calculations/bazi/bazi_resurss_integritate.js';
import { calculateWesternSaturnDiscipline } from '../../calculations/western/western_saturn_disciplina.js';
import { calculateWesternSunJupiterEthics } from '../../calculations/western/western_saule_etika.js';
import { calculateWesternElementsStability } from '../../calculations/western/western_noturiba_elementi.js';
import { calculateHellenisticSectSaturn } from '../../calculations/hellenistic/hellenistic_sect_saturn.js';
import { calculateHellenisticSpiritIntent } from '../../calculations/hellenistic/hellenistic_spirit_intent.js';
import { calculateHellenisticPhasisAction } from '../../calculations/hellenistic/hellenistic_phasis_action.js';
import { calculateMayanReliabilityFinal } from '../../mayan_interpretations.js';

export function renderReliabilitySection(profile) {
    let fullHtml = '<div style="display:grid; grid-template-columns: 1fr; gap: 1rem;">';
                        // ===== 0. Uzticamība un Atbildība =====
                        let h0 = "";
                        
                        // Bāzes līniju mainīgie (lai nesaistītu ar stundu un mājām)
                        let baziBaseStr = JSON.stringify({ year: profile.bazi?.gods?.Year, month: profile.bazi?.gods?.Month, day_hidden: profile.bazi?.hiddenGod });
                        let mayanStr = JSON.stringify(profile.maya_profile || {});
                        
                        // Vēdu Saturns - 7 slāņu algoritms
                        let satData = profile.vedic?.planets?.["Saturns"] || {};
                        let sunData = profile.vedic?.planets?.["Saule"] || {};
                        let moonData = profile.vedic?.planets?.["Meness"] || {};
                        let ascData = profile.vedic?.planets?.["Ascendant"] || {};
                        
                        let ashtakavargaArr = profile.vedic?.ashtakavarga || [];
                        let nakDataSat = profile.vedic?.filters?.["Saturns"] || {};
                        let isNightBirth = (sunData.house >= 1 && sunData.house <= 6);
                        
                        // 1. Saturns (Izpildījums)
                        let vedicSaturnParams = {
                            satDeg: satData.sidereal_deg || 0,
                            satHouse: satData.house || 1,
                            isRetro: satData.isRetrograde || false,
                            sunHouse: sunData.house || 1,
                            ashtHousePts: ashtakavargaArr[(satData.house || 1) - 1] || 28,
                            nakLord: nakDataSat.lord || "Nezināms",
                            nakName: nakDataSat.nakshatra || "Nezināms",
                            d9Sign: profile.vedic?.navamsha?.["Saturns"] || "Nezināms",
                            isDetailedUI: true
                        };
                        let vResultSaturn = calculateVedicSaturnReliability(vedicSaturnParams);
                        
                        // 2. Saule (Integritāte)
                        let vedicSunParams = {
                            sunDeg: sunData.sidereal_deg || 0,
                            sunHouse: sunData.house || 1,
                            isNightBirth: isNightBirth,
                            isDetailedUI: true
                        };
                        let vResultSun = calculateVedicSunIntegrity(vedicSunParams);
                        
                        // 3. Mēness / Zeme (Ikdienas stabilitāte)
                        // Extract all planetary sidereal degrees
                        let allPlanetsSidereal = {};
                        if (profile.vedic?.planets) {
                            for (let p in profile.vedic.planets) {
                                allPlanetsSidereal[p] = profile.vedic.planets[p]?.sidereal_deg;
                            }
                        }
                        let vedicMoonParams = {
                            moonDeg: moonData.sidereal_deg || 0,
                            ascDeg: ascData.sidereal_deg || 0,
                            allPlanetsSideral: allPlanetsSidereal,
                            isDetailedUI: true
                        };
                        let vResultMoon = calculateVedicMoonEarthStability(vedicMoonParams);

                        // Vēdu Summārais + Grupējums
                        let vedicBlockHtml = `<div style="background: rgba(99, 102, 241, 0.03); border-left: 3px solid #6366f1; border-radius: 8px; padding: 12px; margin-bottom: 12px; border-top: 1px solid rgba(99, 102, 241, 0.1); border-right: 1px solid rgba(99, 102, 241, 0.1); border-bottom: 1px solid rgba(99, 102, 241, 0.1);">`;
                        let vedicSummaryScore = Math.floor((vResultSaturn.score * 0.5) + (vResultSun.score * 0.3) + (vResultMoon.score * 0.2));
                        
                        let genCategory = "";
                        let genCatColor = "";
                        if (vedicSummaryScore >= 80) {
                            genCategory = "Zelta Standarts";
                            genCatColor = "#10b981"; 
                        } else if (vedicSummaryScore >= 50) {
                            genCategory = "Situatīvā uzticamība";
                            genCatColor = "#f59e0b"; 
                        } else {
                            genCategory = "Augsta riska zona";
                            genCatColor = "#ef4444"; 
                        }

                        let genCatText = "";
                        if (vedicSummaryScore >= 80) genCatText = "Sistēmas balsts. Cilvēks, uz kura var būvēt biznesu vai dzīvi.";
                        else if (vedicSummaryScore >= 50) genCatText = "Viņš būs uzticams, ja apstākļi būs labvēlīgi (piemēram, labs vadītājs vai skaidrs plāns).";
                        else genCatText = "Šeit uzticamība ir nejaušība, nevis sistēma.";

                        let getLvl = (s) => (s >= 71 ? "H" : (s <= 35 ? "L" : "M"));
                        let sunLvl = getLvl(vResultSun.score);
                        let satLvl = getLvl(vResultSaturn.score);
                        let mooLvl = getLvl(vResultMoon.score);
                        
                        let arcName = "Jaukts Profils";
                        let arcDesc = "Vidēji stabils izpildījums bez vienas dominējošas galējības.";
                        
                        if (sunLvl === "H" && satLvl === "L" && mooLvl === "L") {
                            arcName = "Cēlais Teoretiķis";
                            arcDesc = "Runā par godu un principiem, bet nespēj ne piecelties laikā, ne pabeigt darbu. Solījumi ir patiesi brīdī, kad tos izsaka, bet pazūd realitātes grūtībās.";
                        } else if (sunLvl === "L" && satLvl === "H" && mooLvl === "H") {
                            arcName = "Uzticamais Algotnis";
                            arcDesc = "Viņam nav lielu ideālu vai lepnības, bet viņš ir kā robots. Ja apsolīja — izdarīs, jo tā ir ieraduma spēks un disciplīna. Strādā naudas vai procesa dēļ.";
                        } else if (sunLvl === "H" && satLvl === "H" && mooLvl === "L") {
                            arcName = "Pārdegušais Varonis";
                            arcDesc = "Viņš grib būt labākais un strādā kā tanks, bet viņa iekšējais haoss viņu 'noēd'. Viņš var pazust uz nedēļu, jo 'psiholoģiski salūza', lai gan darbs ir izdarīts par 90%.";
                        } else if (sunLvl === "L" && satLvl === "L" && mooLvl === "H") {
                            arcName = "Mierīgais Vērotājs";
                            arcDesc = "Viņš ir jauks un emocionāli stabils, bet viņam nav nedz ambīciju (Saule), nedz gribasspēka (Saturns). Viņš ir uzticams 'nekā nedarīšanā'.";
                        } else if (sunLvl === "H" && satLvl === "L" && mooLvl === "H") {
                            arcName = "Iedvesmojošais Mākonis";
                            arcDesc = "Godīgs, stabils un mīļš, bet pilnīgi nespējīgs uz smagu darbu vai termiņu ievērošanu. Viņš 'aizmirst', jo trūkst Saturna dzelzs tvēriena.";
                        } else if (sunLvl === "H" && satLvl === "H" && mooLvl === "H") {
                            arcName = "Neiznīcināmais Titāns";
                            arcDesc = "Izcils godaprāts, dzelzsbetona darba ētika un nesatricināma psihe. Perfekts līderis un stratēģis jebkuros apstākļos.";
                        } else if (sunLvl === "L" && satLvl === "L" && mooLvl === "L") {
                            arcName = "Sabrukuma Risks";
                            arcDesc = "Sistēma nedarbojas. Nav nedz gribasspēka, nedz motivācijas, nedz emocionālās bāzes. Nav ieteicams paļauties uz šo personu atbildīgos procesos.";
                        } else {
                            let top = []; if(sunLvl==="H") top.push("godaprāts"); if(satLvl==="H") top.push("disciplīna"); if(mooLvl==="H") top.push("emocionālā noturība");
                            let weak = []; if(sunLvl==="L") weak.push("motivācija"); if(satLvl==="L") weak.push("noturība slodzē"); if(mooLvl==="L") weak.push("emocionālā reaktivitāte");
                            
                            arcName = "Hibrīda Raksturs";
                            arcDesc = "Situatīvs modelis. ";
                            if (top.length > 0) arcDesc += "Spēcīgi resursi: " + top.join(", ") + ". ";
                            if (weak.length > 0) arcDesc += "Vājie punkti: " + weak.join(", ") + ".";
                            if (top.length === 0 && weak.length === 0) arcDesc = "Stabils vidusmēra izpildītājs bez ekstrēmām novirzēm.";
                        }

                        let vedicSummaryDesc = `
                            <div style="margin-top:6px; padding:10px; border-left:3px solid ${genCatColor}; background:rgba(0,0,0,0.02); border-radius:4px;">
                                <b style="color:${genCatColor}; font-size:1rem;">Kategorija: ${genCategory}</b><br>
                                <span style="font-size:0.85rem; color:#475569;">${genCatText}</span>
                            </div>
                            <div style="margin-top:10px; padding:10px; border-radius:4px; border: 1px solid rgba(99, 102, 241, 0.2); background:rgba(99, 102, 241, 0.05);">
                                <b style="color:#4f46e5; font-size:0.95rem;">Arhetips: "${arcName}"</b><br>
                                <span style="font-size:0.85rem; color:#334155;">${arcDesc}</span>
                            </div>
                        `;
                        vedicBlockHtml += makeBar(vedicSummaryScore, "Vēdu Astroloģija (Kopvērtējums)", vedicSummaryDesc, "vedic_summary");
                        
                        vedicBlockHtml += `<div style="margin-top: 15px; padding-left: 12px; border-left: 2px dashed rgba(99, 102, 241, 0.3);">`;
                        vedicBlockHtml += makeBar(vResultSaturn.score, "Vēdu Astroloģija (Saturns) - Izpildījums", vResultSaturn.html, "vedic_saturn");
                        vedicBlockHtml += makeBar(vResultSun.score, "Vēdu Astroloģija (Saule) - Integritāte", vResultSun.html, "vedic_sun");
                        vedicBlockHtml += makeBar(vResultMoon.score, "Vēdu Astroloģija (Mēness/Zeme) - Stabilitāte", vResultMoon.html, "vedic_moon");
                        vedicBlockHtml += `</div></div>`;

                        // BaZi (Trīs pīlāru prioritāte + Sinerģija)
                        let baziParams = {
                            earthElementPercent: profile.bazi?.elements?.Zeme || 0,
                            mainGod: profile.bazi?.mainGod || "",
                            hiddenGod: profile.bazi?.hiddenGod || "",
                            gods: profile.bazi?.gods || [],
                            conflicts: profile.bazi?.conflicts || [],
                            combinations: profile.bazi?.combinations || [],
                            isDetailedUI: true
                        };

                        let bResultEarth = calculateBaZiEarthStability(baziParams);
                        let bResultOfficer = calculateBaZiOfficerExecution(baziParams);
                        let bResultResource = calculateBaZiResourceIntegrity(baziParams);

                        // Sadursmju modifikators
                        let numConflicts = baziParams.conflicts.length;
                        let numCombos = baziParams.combinations.length;
                        let modifierScore = (numCombos * 10) - (numConflicts * 15);
                        let modifierText = "";
                        if (modifierScore < 0) modifierText = `<div style="margin-top:6px; color:#ef4444; font-size:0.8rem;"><b>Sadursmes (-${Math.abs(modifierScore)}%):</b> Kartē ir ${numConflicts} sadursmes, kas rada iekšēju haosu un traucē stabilitāti.</div>`;
                        else if (modifierScore > 0) modifierText = `<div style="margin-top:6px; color:#10b981; font-size:0.8rem;"><b>Kombinācijas (+${modifierScore}%):</b> Kartē ir ${numCombos} harmoniskas kombinācijas, kas palīdz pārvarēt krīzes.</div>`;
                        else modifierText = `<div style="margin-top:6px; color:#475569; font-size:0.8rem;">Struktūra ir neitrāla, bez lielām sadursmēm vai kombinācijām.</div>`;

                        let bScoreRaw = Math.floor((bResultEarth.score * 0.3) + (bResultOfficer.score * 0.4) + (bResultResource.score * 0.3));
                        let bScore = Math.floor(bScoreRaw + modifierScore);
                        if (bScore > 100) bScore = 100;
                        if (bScore < 0) bScore = 0;

                        let baziBlockHtml = `<div style="background: rgba(245, 158, 11, 0.03); border-left: 3px solid #f59e0b; border-radius: 8px; padding: 12px; margin-bottom: 12px; border-top: 1px solid rgba(245, 158, 11, 0.1); border-right: 1px solid rgba(245, 158, 11, 0.1); border-bottom: 1px solid rgba(245, 158, 11, 0.1);">`;
                        let eScoreVal = bResultEarth.score;
                        let oScoreVal = bResultOfficer.score;
                        let rScoreVal = bResultResource.score;
                        let comboText = "";

                        if (eScoreVal > 66 && oScoreVal > 66 && rScoreVal > 66) {
                            comboText = "<b>🏆 Sistēmas Mugurkauls</b><br>Šī persona ir izcilas enerģētiskās arhitektūras piemērs. Viņas uzticamība ir balstīta uz dabisku 'Zemes' stabilitāti, ko papildina spēcīga paškontrole un skaidra sirdsapziņa. Persona ir uzticama ilgtermiņā, jo viņas raksturs neļauj rīkoties neparedzami vai negodīgi. Viņš vai viņa ir jebkuras stabilas struktūras pamats.";
                        } else if (oScoreVal > 66 && rScoreVal < 36) {
                            comboText = "<b>🏛️ Disciplinētais Tehnokrāts</b><br>Personas uzticamība balstās uz striktu noteikumu ievērošanu un procesuālo disciplīnu. Viņš vai viņa pilda solījumus, jo augstu vērtē kārtību un hierarhiju, nevis emocionālu piesaisti. Ja nav skaidru instrukciju vai kontroles, personas iekšējais morāles kompass var kļūt svārstīgs, taču darba izpildes ziņā šī persona ir nekļūdīga.";
                        } else if (rScoreVal > 66 && oScoreVal < 36) {
                            comboText = "<b>🧘 Klusa Sirdsapziņa</b><br>Persona ir ārkārtīgi godīga un integritātes vadīta, taču viņai kritiski trūkst praktiskās disciplīnas un spējas iekļauties termiņos. Viņš vai viņa nekad nekaitēs tīšām, taču solījumi var palikt neizpildīti personas nespējas dēļ organizēt sevi vai pretoties rutīnas spiedienam. Uzticamība ir morāla, nevis tehniska.";
                        } else if (eScoreVal > 66 && oScoreVal < 40) {
                            comboText = "<b>🗿 Klusējošā Klints</b><br>Personu raksturo milzīga inerce un stabilitāte. Viņš vai viņa ir uzticams tajā ziņā, ka nemaina savu nostāju, taču viņam trūkst iniciatīvas uzņemties jaunu atbildību. Persona var būt uzticams palīgs, bet nevar kalpot par dzinējspēku, jo viņas 'Zemes' enerģija vairāk vērsta uz esošā saglabāšanu, nevis aktīvu pienākumu izpildi.";
                        } else if (bScore < 40) {
                            comboText = "<b>🌊 Impulsīvais Optimists</b><br>Personas uzticamība ir augsta riska zonā. Viņa raksturs ir pārāk kustīgs un nepastāvīgs, lai nodrošinātu prognozējamu rezultātu. Persona bieži rīkojas pēc garastāvokļa vai acumirklīga izdevīguma, viegli atrodot attaisnojumus nepildītiem solījumiem. Partnerība ar šo personu prasa pastāvīgu ārēju monitoringu.";
                        } else {
                            comboText = "<b>⚖️ Situatīvais Līdzsvars</b><br>Persona ir uzticama standarta apstākļos. Viņš vai viņa spēj pielāgoties prasībām un saglabāt godaprātu, kamēr netiek izdarīts ekstremāls spiediens. Personas raksturam trūkst viena izteikta 'enkura', tāpēc viņa uzticamība ir tiešā mērā atkarīga no vides stabilitātes un skaidri definētiem spēles noteikumiem.";
                        }

                        let baziSummaryDesc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>
                            <b>Svaru ietekme formulā:</b><br>
                            • Zemes Elements (Stabilitāte): 30%<br>
                            • Amatpersona (Izpildījums): 40%<br>
                            • Resurss (Integritāte): 30%<br>
                            </div>
                            ${modifierText}
                            <br><b style="color:#64748b;">Aprēķins:</b> (Zeme: ${bResultEarth.score} * 0.3) + (Amatpersona: ${bResultOfficer.score} * 0.4) + (Resurss: ${bResultResource.score} * 0.3) + (Modifikators: ${modifierScore}) = <b>${bScore}%</b>.
                            <div style="margin-top:10px; padding:10px; border-left:3px solid #f59e0b; background:rgba(245, 158, 11, 0.05); color:#1e293b; font-size:0.85rem; line-height:1.4;">
                                <span style="color:#b45309; text-transform:uppercase; font-weight:700; font-size:0.75rem; display:block; margin-bottom:4px;">BaZi Uzticamības Arhetips</span>
                                ${comboText}
                            </div>`;
                        
                        baziBlockHtml += makeBar(bScore, "Ķīniešu BaZi (Kopvērtējums)", baziSummaryDesc, "bazi_summary");
                        
                        baziBlockHtml += `<div style="margin-top: 15px; padding-left: 12px; border-left: 2px dashed rgba(245, 158, 11, 0.3);">`;
                        baziBlockHtml += makeBar(bResultEarth.score, "Ķīniešu BaZi (Zeme) - Stabilitāte", bResultEarth.html, "bazi_earth");
                        baziBlockHtml += makeBar(bResultOfficer.score, "Ķīniešu BaZi (Amatpersona) - Izpildījums", bResultOfficer.html, "bazi_officer");
                        baziBlockHtml += makeBar(bResultResource.score, "Ķīniešu BaZi (Resurss) - Integritāte", bResultResource.html, "bazi_resource");
                        baziBlockHtml += `</div></div>`;

                        // Sinerģijas Audits (Vēdas + BaZi)
                        let synColor = "#4f46e5";
                        let synTitle = "Sinhronais Uzticamības Audits";
                        let synDesc = "Dati tiek saskaņoti starp Vēdu un BaZi sistēmām.";
                        
                        let isVedicHigh = vedicSummaryScore >= 70;
                        let isVedicLow = vedicSummaryScore <= 35;
                        let isBaziHigh = bScore >= 70;
                        let isBaziLow = bScore <= 35;

                        let showSynergy = true;
                        if (isVedicHigh && isBaziHigh) {
                            synColor = "#10b981";
                            synTitle = "Dzelžains izpildītājs (Dubultais apstiprinājums)";
                            synDesc = "Abas lielās sistēmas (Vēdas un BaZi) apstiprina ārkārtīgi augstu uzticamību un stabilitāti. Šis ir sistēmas balsts.";
                        } else if (isVedicLow && isBaziLow) {
                            synColor = "#ef4444";
                            synTitle = "Sistēmiski neuzticams (100% apstiprinājums no abām sistēmām)";
                            synDesc = "Abas sistēmas uzrāda kritiskus iztrūkumus noturībā, godaprātā un stabilitātē. Augsts risks biznesā un personīgajās saistībās.";
                        } else if (isVedicHigh && isBaziLow || isVedicLow && isBaziHigh) {
                            synColor = "#f59e0b";
                            synTitle = "Mainīgs raksturs / Pretrunīgs Profils";
                            synDesc = "Sistēmas nedod viennozīmīgu atbildi. Uzticamība var būt atkarīga no ārējiem apstākļiem, motivācijas vai specifiska darba veida.";
                        } else {
                            showSynergy = false;
                        }

                        let synergyHtml = "";
                        if (showSynergy) {
                            synergyHtml = `<div style="background: rgba(0,0,0, 0.02); border-left: 4px solid ${synColor}; border-radius: 4px; padding: 14px; margin-top: 20px; margin-bottom: 20px;">
                                    <h4 style="margin:0 0 6px 0; color:${synColor}; display:flex; align-items:center; gap:8px;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        ${synTitle}
                                    </h4>
                                    <span style="font-size:0.9rem; color:#334155; line-height:1.5;">${synDesc}</span>
                                   </div>`;
                        }

                        // Rietumu (Planētas virs Mājām) -> Jaunais 3 slāņu modelis
                        let wParams = {
                            planets: profile.western?.planets || {},
                            isDetailedUI: true
                        };

                        let wResSaturn = calculateWesternSaturnDiscipline(wParams);
                        let wResEthics = calculateWesternSunJupiterEthics(wParams);
                        let wResElements = calculateWesternElementsStability(wParams);

                        let wScoreRaw = Math.floor((wResSaturn.score * 0.4) + (wResEthics.score * 0.3) + (wResElements.score * 0.3));
                        let wScore = wScoreRaw;
                        if (wScore > 100) wScore = 100;
                        if (wScore < 0) wScore = 0;

                        let wComboText = "";
                        if (wResSaturn.score > 66 && wResEthics.score > 66 && wResElements.score > 66) {
                            wComboText = "<b>💎 Dzelzs Raksturs</b><br>Ideāla Rietumu astroloģiskā arhitektūra. Persona ir izcili strukturēta (Saturns), apveltīta ar augstu morāli (Saule/Jupiters) un milzīgu noturību pret ārējiem kairinājumiem (Zemes/Fiksētais pārsvars). Īstens stabilitātes etalons.";
                        } else if (wResSaturn.score > 66 && wResEthics.score < 36) {
                            wComboText = "<b>⚙️ Perfekts Mehānisms</b><br>Ļoti organizēta un uz noteikumiem orientēta persona, taču ētika var tikt upurēta izdevīguma vai 'kārtības saglabāšanas' vārdā. Sistēma strādā nevainojami, taču motivācija ir mehāniska, nevis morāla.";
                        } else if (wResEthics.score > 66 && wResSaturn.score < 36) {
                            wComboText = "<b>🕊️ Haotiskais Ideālists</b><br>Sirds ir pareizajā vietā. Persona ir godīga, taču ikdienā var kavēt termiņus un cīnīties ar paškontroli. Solījumi tiek doti labticīgi, bet realitātē Saturns liedz tos izpildīt laikā.";
                        } else if (wResElements.score < 36 && wScore > 50) {
                            wComboText = "<b>🍃 Vēja Zvans</b><br>Lai gan ir mēģinājumi saglabāt gan disciplīnu, gan ētiku, enerģētiskā struktūra ir pārāk mainīga (Gaisa/Mutablo zīmju pārsvars). Dzīves pagriezienos bieži maina virzienu un nevar ilgi noturēt vienu kursu.";
                        } else if (wScore < 40) {
                            wComboText = "<b>🌪️ Neprognozējamais Hameleons</b><br>Raksturīga izteikta izvairīšanās no atbildības, morāls relatīvisms un nespēja noturēt uzmanību. Sadarbība prasa pastāvīgu uzraudzību, citādi cilvēks 'izslīdēs' caur pirkstiem.";
                        } else {
                            wComboText = "<b>⚖️ Adaptīvā Noturība</b><br>Rādītāji atrodas mērenā zonā. Persona pilda savus pienākumus, kad tie ir skaidri nodefinēti un vide ir stabila. Netiek demonstrēta ne ekstrēma stūrgalvība, ne pilnīgs haoss.";
                        }

                        let westernBlockHtml = `<div style="background: rgba(99, 102, 241, 0.03); border-left: 3px solid #6366f1; border-radius: 8px; padding: 12px; margin-bottom: 12px; border-top: 1px solid rgba(99, 102, 241, 0.1); border-right: 1px solid rgba(99, 102, 241, 0.1); border-bottom: 1px solid rgba(99, 102, 241, 0.1);">`;
                        
                        let wSummaryDesc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>
                            <b>Svaru ietekme formulā:</b><br>
                            • Rietumu Saturns (Disciplīna): 40%<br>
                            • Saules/Jupitera Ētika: 30%<br>
                            • Elementu Noturība: 30%<br>
                            </div>
                            <br><b style="color:#64748b;">Aprēķins:</b> (Saturns: ${wResSaturn.score} * 0.4) + (Ētika: ${wResEthics.score} * 0.3) + (Elementi: ${wResElements.score} * 0.3) = <b>${wScore}%</b>.
                            <div style="margin-top:10px; padding:10px; border-left:3px solid #6366f1; background:rgba(99, 102, 241, 0.05); color:#1e293b; font-size:0.85rem; line-height:1.4;">
                                <span style="color:#4f46e5; text-transform:uppercase; font-weight:700; font-size:0.75rem; display:block; margin-bottom:4px;">Rietumu Psiholoģiskais Arhetips</span>
                                ${wComboText}
                            </div>`;
                        
                        westernBlockHtml += makeBar(wScore, "Rietumu Astroloģija (Kopvērtējums)", wSummaryDesc, "western_summary");
                        westernBlockHtml += makeBar(wResSaturn.score, "Rietumu Saturns - Disciplīna", wResSaturn.html, "western_saturn");
                        westernBlockHtml += makeBar(wResEthics.score, "Saules un Jupitera Ētika", wResEthics.html, "western_ethics");
                        westernBlockHtml += makeBar(wResElements.score, "Elementu un Modalitāšu Noturība", wResElements.html, "western_elements");
                        westernBlockHtml += `</div>`;

                        // Hellēnistiskā -> Jaunais 3 slāņu modelis
                        let hParams = {
                            sect: profile.hellenistic?.data?.sect,
                            planets: profile.western?.planets || {},
                            spiritLotSign: profile.hellenistic?.data?.spiritLot?.sign,
                            isSaturnRetro: profile.hellenistic?.data?.isSaturnRetro,
                            isDetailedUI: true
                        };

                        let hResSect = calculateHellenisticSectSaturn(hParams);
                        let hResSpirit = calculateHellenisticSpiritIntent(hParams);
                        let hResPhasis = calculateHellenisticPhasisAction(hParams);

                        let hScoreRaw = Math.floor((hResSect.score * 0.4) + (hResSpirit.score * 0.3) + (hResPhasis.score * 0.3));
                        let hScore = hScoreRaw;
                        if (hScore > 100) hScore = 100;
                        if (hScore < 0) hScore = 0;

                        let hComboText = "";
                        if (hResSect.score > 66 && hResSpirit.score > 66 && hResPhasis.score > 66) {
                            hComboText = "<b>🏛️ Dzelzs Likumdevējs</b><br>Ideāla hellēnistiskā arhitektūra. Persona dabiski pieņem sociālo kārtību (Dienas Saturns), pilnībā kontrolē savus lēmumus (Valdnieks stūra mājā) un spēj realizēt apsolīto fiziskajā realitātē bez mistiskiem šķēršļiem. Augstākā ranga atbildība.";
                        } else if (hResSect.score < 40 && hResSpirit.score > 66) {
                            hComboText = "<b>⚔️ Nemiernieks ar Principiem</b><br>Griba ir dzelžaina un cilvēks kontrolē situāciju, bet viņš ienīst ārējos likumus un rāmjus (Nakts Saturns). Pildīs savu gribu, nevis to, ko no viņa prasa sistēma.";
                        } else if (hResSect.score > 66 && hResSpirit.score < 40) {
                            hComboText = "<b>⛓️ Sistēmas Vergs</b><br>Dabiski iekļaujas likumos un sistēmās (Dienas Saturns), taču pašam trūkst iekšējā gribas vektora un viņš bieži kļūst par 'apstākļu upuri' (Gara Lotes valdnieks krītošajā mājā). Laba izpildvaras detaļa, bet ne līderis.";
                        } else if (hResPhasis.score < 40 && hScore > 50) {
                            hComboText = "<b>🌫️ Miglas Gūsteknis</b><br>Griba un pienākuma apziņa ir laba, taču rīcība objektīvi 'iestrēgst' vai netiek pamanīta (Saturns ir sadedzis vai retrogrāds). Persona pastāvīgi cīnās ar apstākļiem, kas liedz realizēt plānus.";
                        } else if (hScore < 40) {
                            hComboText = "<b>☠️ Haosa Aģents</b><br>Sistēma fiksē milzīgu iekšēju pretestību atbildībai, gribas trūkumu lēmumos un objektīvu nespēju realizēt solīto fiziskajā realitātē. Sadarbība ir ārkārtīgi riskanta.";
                        } else {
                            hComboText = "<b>⚖️ Funkcionāls Izpildītājs</b><br>Rādītāji ir mērenā zonā. Persona nav izteikts likumdevējs, bet arī nav haosa aģents. Rīcība ir atkarīga no situācijas specifikas un resursiem.";
                        }

                        let hellenisticBlockHtml = `<div style="background: rgba(16, 185, 129, 0.03); border-left: 3px solid #10b981; border-radius: 8px; padding: 12px; margin-bottom: 12px; border-top: 1px solid rgba(16, 185, 129, 0.1); border-right: 1px solid rgba(16, 185, 129, 0.1); border-bottom: 1px solid rgba(16, 185, 129, 0.1);">`;
                        
                        let hSummaryDesc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>
                            <b>Svaru ietekme formulā:</b><br>
                            • Sektas Kods (Saturns): 40%<br>
                            • Gara Lotes Valdnieks (Griba): 30%<br>
                            • Planētu Fāze (Rīcībspēja): 30%<br>
                            </div>
                            <br><b style="color:#64748b;">Aprēķins:</b> (Sekte: ${hResSect.score} * 0.4) + (Gara Lote: ${hResSpirit.score} * 0.3) + (Fāze: ${hResPhasis.score} * 0.3) = <b>${hScore}%</b>.
                            <div style="margin-top:10px; padding:10px; border-left:3px solid #10b981; background:rgba(16, 185, 129, 0.05); color:#1e293b; font-size:0.85rem; line-height:1.4;">
                                <span style="color:#059669; text-transform:uppercase; font-weight:700; font-size:0.75rem; display:block; margin-bottom:4px;">Hellēnistiskais Psiholoģiskais Arhetips</span>
                                ${hComboText}
                            </div>`;
                        
                        hellenisticBlockHtml += makeBar(hScore, "Hellēnistiskā Astroloģija (Kopvērtējums)", hSummaryDesc, "hellenistic_summary");
                        hellenisticBlockHtml += makeBar(hResSect.score, "Sektas Kods (Saturna Funkcija)", hResSect.html, "hellenistic_sect");
                        hellenisticBlockHtml += makeBar(hResSpirit.score, "Gara Lotes Valdnieks (Gribas Jauda)", hResSpirit.html, "hellenistic_spirit");
                        hellenisticBlockHtml += makeBar(hResPhasis.score, "Planētu Fāze (Rīcībspēja un Redzamība)", hResPhasis.html, "hellenistic_phasis");
                        hellenisticBlockHtml += `</div>`;

                        // Maiju / Tolteku (Ritmiskais Audits)
                        let mSealIdx = profile.maya_profile?.basic?.sign_idx || 0;
                        let mTone = profile.maya_profile?.basic?.tone || 1;
                        let mOracle = profile.maya_profile?.oracle || { guide: 0, antipode: 0, analog: 0, occult: 0 };
                        let mColor = profile.maya_profile?.basic?.color || "N/A";
                        
                        let mReliability = calculateMayanReliabilityFinal(mSealIdx, mTone, mOracle);
                        let mScore = mReliability.score;
                        
                        let mayanBlockHtml = `<div style="background: rgba(16, 185, 129, 0.03); border-left: 3px solid #10b981; border-radius: 8px; padding: 12px; margin-bottom: 12px; border-top: 1px solid rgba(16, 185, 129, 0.1); border-right: 1px solid rgba(16, 185, 129, 0.1); border-bottom: 1px solid rgba(16, 185, 129, 0.1);">`;
                        
                        let mSummaryDesc = `<div style='font-size:0.8rem; color:#475569; margin-top:4px; line-height:1.4;'>
                            <b>Svaru ietekme formulā:</b><br>
                            • Nawal (Bāzes Enerģija): 40%<br>
                            • Tonal (Enerģētiskais Svars): 30%<br>
                            • Likteņa Krusts (Papildu zīmes): 30%<br>
                            </div>
                            <br><b style="color:#64748b;">Aprēķins:</b> (Nawal: ${mReliability.nawal.score} * 0.4) + (Tonal: ${mReliability.tonal.score} * 0.3) + (Krusts: ${mReliability.cross.score} * 0.3) = <b>${mScore}%</b>.
                            <div style="margin-top:10px; padding:10px; border-left:3px solid #10b981; background:rgba(16, 185, 129, 0.05); color:#1e293b; font-size:0.85rem; line-height:1.4;">
                                <span style="color:#059669; text-transform:uppercase; font-weight:700; font-size:0.75rem; display:block; margin-bottom:4px;">${mReliability.archetype}</span>
                                ${mReliability.desc}
                            </div>`;
                        
                        let mnDesc = `<b>${mReliability.nawal.category}:</b> ${mReliability.nawal.desc}`;
                        let mtDesc = `<b>${mReliability.tonal.category} (Bāze ${mReliability.tonal.baseScore}%, Bīde ${(mTone % 2 === 0) ? '+5' : '-5'}%):</b> Enerģētiskais spēks un atbildības masa.`;
                        let mcDesc = `<b>Likteņa Krusta Harmonija:</b> ${mReliability.cross.modDesc} Nākotnes sargs: ${mReliability.cross.futureCategory}.`;

                        mayanBlockHtml += makeBar(mScore, "Maiju Sistēma (Ritmiskais Audits)", mSummaryDesc, "maya");
                        mayanBlockHtml += makeBar(mReliability.nawal.score, "Nawal Essence (Dienas Sargs)", mnDesc);
                        mayanBlockHtml += makeBar(mReliability.tonal.score, "Tonal Power (Skaitliskā Masa)", mtDesc);
                        mayanBlockHtml += makeBar(mReliability.cross.score, "Destiny Cross (Likteņa Krusts)", mcDesc);
                        mayanBlockHtml += `</div>`;

                        let intentScore = Math.round((vResultSun.score + bResultResource.score + wResEthics.score + hResSpirit.score + mReliability.nawal.score) / 5);
                        let execScore = Math.round((vResultSaturn.score + bResultOfficer.score + wResSaturn.score + hResSect.score + mReliability.tonal.score) / 5);
                        let consScore = Math.round((vResultMoon.score + bResultEarth.score + wResElements.score + hResPhasis.score + mReliability.cross.score) / 5);
                        
                        let masterTotal = Math.round((vedicSummaryScore * 0.25) + (bScore * 0.15) + (wScore * 0.25) + (hScore * 0.15) + (mScore * 0.20));
                        
                        let P1_x = 120;
                        let P1_y = 110 - (intentScore / 100 * 80);
                        let P2_x = 120 + (execScore / 100 * 80) * 0.866025;
                        let P2_y = 110 + (execScore / 100 * 80) * 0.5;
                        let P3_x = 120 - (consScore / 100 * 80) * 0.866025;
                        let P3_y = 110 + (consScore / 100 * 80) * 0.5;

                        let badgeName = "Hibrīda Profils";
                        let badgeDesc = "Parametri ir mērenā zonā bez ekstrēmām novirzēm.";
                        
                        let isIHigh = intentScore >= 80;
                        let isILow = intentScore <= 40;
                        let isJHigh = execScore >= 80;
                        let isJLow = execScore <= 40;
                        let isSHigh = consScore >= 80;
                        let isSLow = consScore <= 40;
                        
                        let contrastLogic = "";

                        if (isIHigh && isJLow && isSLow) {
                            badgeName = "Iedvesmojošais Fantooms";
                            badgeDesc = "Cēli nodomi, bet nespēja tos realizēt enerģijas trūkuma un haosa dēļ.";
                            contrastLogic = "Secinājums: Viņš tevi nepievils ļaunprātīgi, bet vienkārši 'neizvilks' solīto enerģijas trūkuma vai haosa dēļ.";
                        } else if (isILow && isJHigh && isSLow) {
                            badgeName = "Haotiskais Algotnis";
                            badgeDesc = "Milzīga darba jauda, taču nav ne sirdsapziņas, ne stabilitātes filtra.";
                            contrastLogic = "Secinājums: Izpildīs uzdevumu brutāli ātri, bet var tikpat ātri pazust vai mainīt puses, ja parādīsies cits impulss.";
                        } else if (isILow && isJLow && isSHigh) {
                            badgeName = "Statais Vērotājs";
                            badgeDesc = "Kā klints, bet bez gribas un jaudas. Uzticama nekustība.";
                            contrastLogic = "Secinājums: Šī persona nekur nepazudīs, bet viņai nav ne gribas, ne jaudas kaut ko reāli paveikt. Ideāls pasīviem procesiem.";
                        } else if (isIHigh && isJHigh && isSLow) {
                            badgeName = "Pārdegušais Varonis";
                            badgeDesc = "Godīgs un jaudīgs, bet trūkst iekšējā enkura. Kritisks izdegšanas risks.";
                            contrastLogic = "Secinājums: Viņš centīsies izdarīt visu, bet krīzes brīdī var emocionāli salūzt, jo nervu sistēma netur paša uzstādīto latiņu.";
                        } else if (isILow && isJHigh && isSHigh) {
                            badgeName = "Aukstais Profesionālis";
                            badgeDesc = "Ideāls izpildītājs, bet lojalitātes deficīts. Stabils, bet bez saiknes ar doto vārdu.";
                            contrastLogic = "Secinājums: Viņš būs uzticams tikai tik ilgi, kamēr tas ir tehniski iespējams un izdevīgi. Nekādu personīgu sentimentu.";
                        } else if (isIHigh && isJLow && isSHigh) {
                            badgeName = "Lēnais Ideālists";
                            badgeDesc = "Sirdsapziņas un stabilitātes paraugs, taču vājš dzinējs. Darbi virzīsies lēni.";
                            contrastLogic = "Secinājums: Viņš nekad tevi nenodos, bet darbi virzīsies ārkārtīgi lēni. Nepieciešams pastāvīgs ārējs menedžeris.";
                        } else if (intentScore >= 80 && execScore >= 80 && consScore >= 80) {
                            badgeName = "Zelta Standarts";
                            badgeDesc = "Spēki pilnīgā harmonijā. Drošākais profils ilgtermiņa partnerībai.";
                            contrastLogic = "Secinājums: Uzticamība ir dabisks stāvoklis, nevis piepūle. Absolūti uzticams kritiskās pozīcijās.";
                        } else if (intentScore <= 40 && execScore <= 40 && consScore <= 40) {
                            badgeName = "Sarkanā Zona";
                            badgeDesc = "Augsts neparedzamības risks. Trūkst morālās bāzes, jaudas un stabilitātes.";
                            contrastLogic = "Secinājums: Persona nevar kalpot par drošu balstu nekādos procesos. Sadarbība ir ārkārtīgi riskanta.";
                        } else if (intentScore >= 40 && intentScore <= 80 && execScore >= 40 && execScore <= 80 && consScore >= 40 && consScore <= 80) {
                            badgeName = "Situatīvais Partneris";
                            badgeDesc = "Relatīvā līdzsvarā. Uzticamība būs atkarīga no vides stabilitātes.";
                            contrastLogic = "Secinājums: Ja apstākļi būs labi un skaidri, arī cilvēka atdeve un godaprāts būs līmenī.";
                        } else {
                            let maxDiffI = intentScore - ((execScore + consScore)/2);
                            let maxDiffJ = execScore - ((intentScore + consScore)/2);
                            let maxDiffS = consScore - ((intentScore + execScore)/2);
                            
                            if (maxDiffI >= 30) contrastLogic = "Secinājums: Teorētiskais godīgums ir augsts, bet praktiskā un emocionālā atbalsta trūkst.";
                            else if (maxDiffJ >= 30) contrastLogic = "Secinājums: Aukstā rīcība dominē pār ētiku un stabilitāti. Riskants sadarbības partneris.";
                            else if (maxDiffS >= 30) contrastLogic = "Secinājums: Izteikta pasivitāte un spēja nogaidīt. Stabilitāte bez liela pienesuma.";
                            else if (maxDiffI <= -30) contrastLogic = "Secinājums: Rīcība var būt aktīva un prognozējama, bet kritiski trūkst iekšējās ētikas un sirdsapziņas. Liels risks.";
                            else if (maxDiffJ <= -30) contrastLogic = "Secinājums: Morālie standarti ir augstāki par spēju rīkoties. Pievils tevi, jo nejaudās pabeigt iesākto.";
                            else if (maxDiffS <= -30) contrastLogic = "Secinājums: Impulsivitātes risks. Labi nodomi un aktīva rīcība var pēkšņi sabrukt emocionālā lūzuma dēļ.";
                        }

                        let getI = (s) => s<=20?"amorāla (pilnīgi trūkst morālo robežu un rīcību vada izdevīgums)":s<=40?"oportūniska (viegli atrod attaisnojumus solījumu laušanai)":s<=60?"pragmātiska savos nodomos":s<=80?"ļoti godprātīga (ir spēcīgs iekšējais kods)":"absolūti ideālistiska (nespēj melot vai pievilt)";
                        let getJ = (s) => s<=20?"pilnīgi pasīvs izpildījums un disciplīnas trūkums":s<=40?"vājš dzinējs un grūtības uzturēt fokusu":s<=60?"stabila izpildītāja kapacitāte":s<=80?"augsta pašdisciplīna un 'pašgājēja' dzinējs":"'dzelzs mašīnas' darba jauda";
                        let getS = (s) => s<=20?"pilnīgi haotiska un neparedzama inerce":s<=40?"svārstīga un garastāvoklim pakļauta stabilitāte":s<=60?"līdzsvarota rīcība mierīgā vidē":s<=80?"droša 'enkura' paredzamība ilgtermiņā":"nesatricināmas 'klints' stabilitāte krīzēs";

                        let dynamicWarning = `<b>Sintēze:</b> Persona ir ${getI(intentScore)}, ko pavada ${getJ(execScore)}, un to visu fiksē ${getS(consScore)}. <br><br><b>${contrastLogic}</b>`;

                        let warningBg = "#eff6ff";
                        let warningBorder = "#3b82f6";
                        let warningText = "#1e40af";
                        let warningIcon = "🧭 Kopsavilkums";

                        if (intentScore >= 60 && execScore >= 60 && consScore >= 60) {
                            warningBg = "#ecfdf5";
                            warningBorder = "#10b981";
                            warningText = "#065f46";
                            warningIcon = "✅ Sistēmas Apstiprinājums";
                        } else if (intentScore < 20 || execScore < 20 || consScore < 20) {
                            warningBg = "#fef2f2";
                            warningBorder = "#ef4444";
                            warningText = "#7f1d1d";
                            warningIcon = "🚨 Strukturāls Lūzums";
                        } else if (intentScore < 40 || execScore < 40 || consScore < 40) {
                            warningBg = "#fffbeb";
                            warningBorder = "#f59e0b";
                            warningText = "#92400e";
                            warningIcon = "⚠️ Vājais posms";
                        }

                        let radarHtml = `
                        <div style="width: 100%; box-sizing: border-box; background: white; padding: 13px 16px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 14px; border-top: 4px solid #4f46e5;">
                            <!-- Virsraksts + Kopējais Indekss vienā rindā -->
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; margin-bottom: 0.8rem;">
                                <h4 style="margin:0; font-size:1.05rem; color:#1e293b; display: flex; align-items: center; gap: 0.5rem;">📊 Uzticamības un Atbildības (Sintezēts Indekss)</h4>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 0.72rem; color: #64748b; font-weight: bold; text-transform: uppercase;">Kopējais Indekss</span>
                                    <span style="font-size: 1.7rem; font-weight: 900; color: #4f46e5; line-height: 1;">${masterTotal}%</span>
                                    <span style="display: inline-block; padding: 4px 13px; background: #1e293b; color: white; border-radius: 20px; font-size: 0.78rem; font-weight: bold; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">${badgeName}</span>
                                </div>
                            </div>
                            <!-- 3 kolonnas: radars | skalas | apraksts -->
                            <div style="display: grid; grid-template-columns: 250px minmax(0, 1fr) minmax(0, 1fr); gap: 1rem 1.3rem; align-items: start;">
                                <div style="display: flex; justify-content: center; align-items: flex-start;">
                                    <svg width="240" height="220" viewBox="0 0 240 220" style="overflow:visible;">
                                            <polygon points="120,30 189.28,150 50.72,150" fill="rgba(0,0,0,0.03)" stroke="#cbd5e1" stroke-width="1" />
                                            <polygon points="120,46 175.42,142 64.58,142" fill="none" stroke="#e2e8f0" stroke-width="1" />
                                            <polygon points="120,62 161.57,134 78.43,134" fill="none" stroke="#e2e8f0" stroke-width="1" />
                                            <polygon points="120,78 147.71,126 92.29,126" fill="none" stroke="#e2e8f0" stroke-width="1" />
                                            <polygon points="120,94 133.86,118 106.14,118" fill="none" stroke="#e2e8f0" stroke-width="1" />
                                            
                                            <circle cx="120" cy="110" r="40" fill="rgba(239, 68, 68, 0.05)" stroke="#fca5a5" stroke-width="1" stroke-dasharray="3" />
                                            <text x="120" y="113" font-size="7" font-weight="900" fill="#f87171" text-anchor="middle" letter-spacing="0.5px">RISKA ZONA</text>
                                            
                                            <line x1="120" y1="110" x2="120" y2="30" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3" />
                                            <line x1="120" y1="110" x2="189.28" y2="150" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3" />
                                            <line x1="120" y1="110" x2="50.72" y2="150" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3" />
                                            
                                            <polygon points="${P1_x},${P1_y} ${P2_x},${P2_y} ${P3_x},${P3_y}" fill="rgba(79, 70, 229, 0.25)" stroke="#4f46e5" stroke-width="2" />
                                            
                                            <circle cx="${P1_x}" cy="${P1_y}" r="4" fill="#4f46e5" />
                                            <circle cx="${P2_x}" cy="${P2_y}" r="4" fill="#4f46e5" />
                                            <circle cx="${P3_x}" cy="${P3_y}" r="4" fill="#4f46e5" />
                                            
                                            <text x="120" y="18" font-size="11" font-weight="900" fill="#f59e0b" text-anchor="middle">INTEGRITĀTE</text>
                                            <text x="210" y="165" font-size="11" font-weight="900" fill="#10b981" text-anchor="middle">JAUDA</text>
                                            <text x="30" y="165" font-size="11" font-weight="900" fill="#3b82f6" text-anchor="middle">STABILITĀTE</text>
                                        </svg>
                                </div>

                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #f59e0b;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Integritāte (Nodoms)</div>
                                            <div style="font-size: 1rem; font-weight: 900; color: #f59e0b;">${intentScore}%</div>
                                        </div>
                                        <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${intentScore}%; height: 100%; background: #f59e0b;"></div></div>
                                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Cik godīgi ir viņa nodomi (Saule, Resurss, Ētika, Griba, Nawal)</div>
                                    </div>
                                    <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #10b981;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Jauda (Izpildījums)</div>
                                            <div style="font-size: 1rem; font-weight: 900; color: #10b981;">${execScore}%</div>
                                        </div>
                                        <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${execScore}%; height: 100%; background: #10b981;"></div></div>
                                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Cik spēcīga ir disciplīna (Saturns, Amatpersona, Sekte, Tonāls)</div>
                                    </div>
                                    <div style="background: rgba(0,0,0,0.02); padding: 8px 11px; border-radius: 6px; border-left: 3px solid #3b82f6;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <div style="font-size: 0.85rem; font-weight: bold; color: #1e293b; text-transform: uppercase;">Stabilitāte (Noturība)</div>
                                            <div style="font-size: 1rem; font-weight: 900; color: #3b82f6;">${consScore}%</div>
                                        </div>
                                        <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: ${consScore}%; height: 100%; background: #3b82f6;"></div></div>
                                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.3;">Cik prognozējams viņš būs (Mēness, Zeme, Inerce, Fāze, Krusts)</div>
                                    </div>
                                </div>
                                
                                <div style="padding: 9px 12px; background: ${warningBg}; border-left: 4px solid ${warningBorder}; border-radius: 6px;">
                                    <div style="font-size: 0.9rem; font-weight: bold; color: ${warningText}; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">${warningIcon}</div>
                                    <div style="font-size: 0.85rem; color: #1e293b; line-height: 1.4;">${dynamicWarning}</div>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 5px; line-height: 1.4;">${badgeDesc}</div>
                                </div>
                            </div>
                        </div>`;

                        h0 = radarHtml;

                        window.lastUzticamibaScores = [vResultSaturn.score, bScore, wScore, hScore, mScore];
    fullHtml += h0 + '</div>';
    return fullHtml;
}
