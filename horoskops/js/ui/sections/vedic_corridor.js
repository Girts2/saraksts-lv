import { getTithiData } from '../../vedic_kp.js?v=10';

export function renderVedicCorridor(profile, nowMs) {
            // 5 LĪMEŅU ASIMETRISKĀS TĀLUMMAIŅAS (ZOOM) RENDERĒŠANA
            
            // Atjaunināts: 120 gadu koridors saskaņā ar lietotāja vēlmi (1. Līmenis)
            const birthTimeMs = new Date(profile.birth_info.date + "T" + profile.birth_info.time + ":00Z").getTime();
            const ageYears = (nowMs - birthTimeMs) / (365.25 * 86400000);
            let markerLeftPct = (ageYears / 120) * 100;
            if (markerLeftPct > 100) markerLeftPct = 100;
            if (markerLeftPct < 0) markerLeftPct = 0;

            const vedicColors = {
                "Jupiters": { color: "#10b981", label: "Veiksme, izaugsme (Jupiters)" }, 
                "Venera": { color: "#34d399", label: "Materiālā veiksme, bauda (Venera)" },
                "Saule": { color: "#f59e0b", label: "Ego, statuss (Saule)" },
                "Meness": { color: "#3b82f6", label: "Emocijas, sapratne (Mēness)" },
                "Merkurs": { color: "#2563eb", label: "Intelekts, bizness (Merkurs)" },
                "Marss": { color: "#ef4444", label: "Krīze, konflikts (Marss)" },
                "Ketu": { color: "#b91c1c", label: "Garīga krīze, atdalīšanās (Ketu)" },
                "Saturns": { color: "#6b7280", label: "Stagnācija, darbs (Saturns)" },
                "Rahu": { color: "#374151", label: "Stagnācija, apjukums (Rahu)" }
            };

            let trackReturnsHtml = "";
            let trackMahaHtml = "";
            let trackAntarHtml = "";
            let trackRiskHtml = "";
            let allDashasListHtml = "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 1rem;'>";
            
            if (profile.vedic.all_dashas) {
                profile.vedic.all_dashas.forEach((dasha, index) => {
                    const sTime = dasha.start.getTime();
                    const eTime = dasha.end.getTime();
                    const durationYears = (eTime - sTime) / (365.25 * 86400000);
                    const widthPct = (durationYears / 120) * 100;
                    const styleObj = vedicColors[dasha.lord] || { color: "#ccc", label: "Nezināms" };
                    
                    trackMahaHtml += `
                        <div class="g-segment" style="width: ${widthPct}%; background: ${styleObj.color};" title="${dasha.lord} (${dasha.start.toISOString().split('T')[0]} līdz ${dasha.end.toISOString().split('T')[0]})\n${styleObj.label}">
                            ${widthPct > 4 ? dasha.lord.substring(0,3).toUpperCase() : ""}
                        </div>
                    `;

                    if (dasha.antardashas && dasha.antardashas.length > 0) {
                        dasha.antardashas.forEach(ad => {
                            const adDurationYears = (ad.end.getTime() - ad.start.getTime()) / (365.25 * 86400000);
                            const globalAdWidthPct = (adDurationYears / 120) * 100;
                            const adStyleObj = vedicColors[ad.lord] || { color: "#ccc" };
                            const startStr = ad.start.toISOString().split('T')[0];
                            const endStr = ad.end.toISOString().split('T')[0];
                            trackAntarHtml += `
                                <div class="g-segment-tiny" style="width: ${globalAdWidthPct}%; background: ${adStyleObj.color}; cursor:pointer;" title="Klikšķini, lai skatītu apakšperioda (Antardasha) analīzi: ${ad.lord}\n${startStr} līdz ${endStr}" onclick="window.showAntarReading('${dasha.lord}', '${ad.lord}', '${startStr}', '${endStr}')"></div>
                            `;
                        });
                    }
                    
                    let nakFilter = "Nezināma";
                    let therapy = null;
                    if (profile.vedic.filters && profile.vedic.filters[dasha.lord]) {
                        nakFilter = profile.vedic.filters[dasha.lord].nakshatra;
                        therapy = profile.vedic.filters[dasha.lord].therapy;
                    }
                    
                    const curLord = dasha.lord;
                    const isCurrent = (profile.vedic.current_dasha && profile.vedic.current_dasha.lord === curLord);
                    const curStyle = vedicColors[curLord] || { color: "#ccc", label: "Nezināms" };
                    
                    const borderStyle = isCurrent ? `border: 2px solid ${curStyle.color}; box-shadow: 0 0 15px ${curStyle.color}40;` : `border-left: 4px solid ${curStyle.color}; border-top: 1px solid rgba(0,0,0,0.05); border-right: 1px solid rgba(0,0,0,0.05); border-bottom: 1px solid rgba(0,0,0,0.05);`;
                    const opacity = isCurrent ? "1" : "0.5";
                    const bgOpacity = isCurrent ? "rgba(0,0,0,0.6)" : "rgba(0,0,0,0.2)";
                    
                    let riskTextCol = isCurrent ? "#fca5a5" : "#dc2626";
                    let upaTextCol = isCurrent ? "#6ee7b7" : "#059669";
                    let dhaTextCol = isCurrent ? "#93c5fd" : "#2563eb";
                     
                    allDashasListHtml += `
                        <div style="padding: 1rem; background: ${bgOpacity}; ${borderStyle} border-radius: 8px; opacity: ${opacity}; transition: all 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='${opacity}'">
                            <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.4rem; font-family:monospace;">
                                ${dasha.start.toISOString().split('T')[0]} ➔ ${dasha.end.toISOString().split('T')[0]} 
                                ${isCurrent ? '<b style="color:#fbbf24; margin-left:5px;">(TAGAD)</b>' : ''}
                            </div>
                            <h4 style="margin:0 0 0.5rem 0; color: ${curStyle.color}; font-size:1.05rem;">${curLord.toUpperCase()} / ${nakFilter.toUpperCase()}</h4>
                            
                            <div style="font-size: 0.9rem; line-height: 1.4; color: #1e293b; margin-bottom: 0.8rem;">
                                <i>${therapy ? therapy.focus : curStyle.label}</i>
                            </div>

                            <div style="font-size: 0.85rem; line-height: 1.5;">
                                <div style="color: #ef4444; margin-bottom: 0.5rem; font-weight:500;">
                                    <b>⚠️ Riski:</b> <span style="color:${riskTextCol};">${therapy ? therapy.risks : "Nav norādīti."}</span>
                                </div>
                                <div style="color: #10b981; margin-bottom: 0.5rem; font-weight:500;">
                                    <b>🛠️ Upajas (Risinājumi):</b> <span style="color:${upaTextCol};">${therapy ? therapy.upaya : "Nav norādīti."}</span>
                                </div>
                                <div style="color: #3b82f6; font-weight:500;">
                                    <b>🎯 Mācība:</b> <span style="color:${dhaTextCol};">${therapy ? therapy.dharma : "Nav norādīta."}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            allDashasListHtml += "</div>";

            let vedicLegendHtml = '<div style="display: flex; flex-wrap: wrap; justify-content: flex-start; gap: 12px 24px; background: #ffffff; padding: 15px 20px; border-radius: 8px; margin-top: 25px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e2e8f0;">';
            const orderedLords = ["Jupiters", "Venera", "Saule", "Meness", "Merkurs", "Marss", "Ketu", "Saturns", "Rahu"];
            orderedLords.forEach(lord => {
                const item = vedicColors[lord];
                if (item) {
                    vedicLegendHtml += `
                        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #1e293b; font-family: sans-serif; font-weight: 500;">
                            <div style="width: 14px; height: 14px; background: ${item.color}; border-radius: 3px; box-shadow: 0 0 5px ${item.color}80;"></div>
                            <span>${item.label}</span>
                        </div>
                    `;
                }
            });
            vedicLegendHtml += '</div>';

            let superEventsLegendHtml = "";
            if (profile.vedic.super_events && profile.vedic.super_events.length > 0) {
                superEventsLegendHtml = `
                    <div style="font-size:0.8rem; color:#64748b; margin-top:10px; background:rgba(0,0,0,0.03); padding:10px 15px; border-radius:6px; border:1px solid rgba(0,0,0,0.05); display:flex; flex-wrap:wrap; gap:15px; align-items:center;">
                        <b style="color:#475569;">Likteņa pagrieziena punkti (Super-Notikumi):</b>
                        <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; background:rgba(239,68,68,0.2); border:1px solid rgba(239,68,68,0.5); border-radius:2px;"></span> Sade Sati (Saturna eksāmens)</span>
                        <span style="display:flex; align-items:center; gap:4px;">✨ Jupitera atgriešanās (Zelta gads)</span>
                        <span style="display:flex; align-items:center; gap:4px;">⚡ Ganda-Anta (Metamorfoze)</span>
                        <span style="display:flex; align-items:center; gap:4px;">🎯 Bhrigu Chakra (Fokuss)</span>
                    </div>
                `;
                
                let returnEvents = [];
                let riskEvents = [];
                profile.vedic.super_events.forEach(evt => {
                    if (evt.type === "guru_return" || evt.type === "bhrigu") {
                        returnEvents.push(evt);
                    } else {
                        riskEvents.push(evt);
                    }
                });

                returnEvents.sort((a,b) => a.startAge - b.startAge);
                
                let lastCenterPct = -100;
                let level = 0;
                
                returnEvents.forEach(evt => {
                    let startPct = (evt.startAge / 120) * 100;
                    let endPct = (evt.endAge / 120) * 100;
                    let widthPct = endPct - startPct;
                    let startYear = new Date(birthTimeMs + evt.startAge * 365.25 * 86400000).getFullYear();
                    
                    if (startPct > 100) return;
                    if (startPct + widthPct > 100) widthPct = 100 - startPct;
                    if (startPct < 0) { widthPct += startPct; startPct = 0; }
                    
                    let centerPct = startPct + (widthPct / 2);
                    let titleText = `${evt.name}\n${evt.desc}\nVecums: ${evt.startAge.toFixed(1)} - ${evt.endAge.toFixed(1)}`;
                    
                    if (centerPct - lastCenterPct < 2.5) {
                        level = (level === 0) ? 1 : 0;
                    } else {
                        level = 0;
                    }
                    lastCenterPct = centerPct;

                    let stemHeightPx = level === 1 ? 40 : 10;
                    
                    trackReturnsHtml += `
                        <div class="g-return-mark" style="left: ${centerPct}%;" title="${titleText}">
                            <div class="g-return-icon">${evt.type === "guru_return" ? "✨" : evt.icon}</div>
                            <div class="g-return-year">${startYear}</div>
                            <div class="g-return-stem" style="height: ${stemHeightPx}px;"></div>
                        </div>
                    `;
                });
                
                let lastRiskPct = -100;
                let riskLevel = 0;
                
                riskEvents.forEach(evt => {
                    let startPct = (evt.startAge / 120) * 100;
                    let endPct = (evt.endAge / 120) * 100;
                    let widthPct = endPct - startPct;
                    let startYear = new Date(birthTimeMs + evt.startAge * 365.25 * 86400000).getFullYear();
                    let endYear = new Date(birthTimeMs + evt.endAge * 365.25 * 86400000).getFullYear();
                    
                    if (startPct > 100) return;
                    if (startPct + widthPct > 100) widthPct = 100 - startPct;
                    if (startPct < 0) { widthPct += startPct; startPct = 0; }
                    
                    let titleText = `${evt.name}\n${evt.desc}\nVecums: ${evt.startAge.toFixed(1)} - ${evt.endAge.toFixed(1)}`;
                    
                    if (evt.type === "sade_sati") {
                        trackRiskHtml += `
                            <div class="g-risk-corridor" style="left: ${startPct}%; width: ${widthPct}%;" title="${titleText}">
                                <!-- Stem posms virzienā uz leju -->
                                <div style="position:absolute; top:38px; left:50%; width:1px; height:8px; background:rgba(239,68,68,0.5);"></div>
                                <!-- Uzraksts, tagad stingri fiksēts ZEM paša koridora no augšas -->
                                <div class="g-risk-label" style="position: absolute; top: 46px; left: 50%; transform: translateX(-50%); display:flex; flex-direction:column; align-items:center; line-height:1.2;">
                                    <span>SADE SATI</span>
                                    <span style="font-size:0.55rem; font-weight:normal;">${startYear}-${endYear}</span>
                                </div>
                            </div>
                        `;
                    } else if (evt.type === "ganda_anta") {
                        let centerPct = startPct + (widthPct / 2);
                        if (centerPct - lastRiskPct < 3.0) {
                            riskLevel = (riskLevel === 0) ? 1 : 0;
                        } else {
                            riskLevel = 0;
                        }
                        lastRiskPct = centerPct;
                        
                        let topOffset = riskLevel === 1 ? '58px' : '44px';
                        
                        trackRiskHtml += `
                            <div style="position: absolute; left: calc(${centerPct}% - 8px); top: 5px; font-size: 1rem; z-index: 7; cursor: help; filter: drop-shadow(0 0 4px #ef4444);" title="${titleText}">
                                ⚡
                                <div class="g-return-year" style="top: ${topOffset}; color:#ffffff; background:rgba(239,68,68,0.9); border-color: rgba(239,68,68,1); box-shadow:none;">${startYear}</div>
                            </div>
                        `;
                    }
                });
            }

            function generateTier(items, pxPerMs, colorMap) {
                let html = "";
                items.forEach(item => {
                    const startPx = (item.start - nowMs) * pxPerMs;
                    const widthPx = (item.end - item.start) * pxPerMs;
                    
                    if(startPx + widthPx > -1500 && startPx < 1500) { 
                        const styleObj = colorMap ? (colorMap[item.lord] || { color: "#ccc" }) : { color: item.color || "#ccc" };
                        let labelHtml = item.text ? item.text : (widthPx > 30 && item.lord ? item.lord : "");
                        html += `
                            <div class="v-segment" style="left: calc(50% + ${startPx}px); width: ${widthPx}px; background-color: ${styleObj.color}; ${item.extraStyle || ''}" title="${item.tooltip || ''}">
                                ${labelHtml}
                            </div>
                        `;
                    }
                });
                return html;
            }

            const transColors = {
                "Jupiters": { color: "#b45309" }, "Venera": { color: "#059669" }, "Saule": { color: "#ea580c" },
                "Meness": { color: "#0ea5e9" }, "Merkurs": { color: "#2563eb" }, "Marss": { color: "#be123c" },
                "Ketu": { color: "#9f1239" }, "Saturns": { color: "#1e3a8a" }, "Rahu": { color: "#374151" }
            };

            // I. Mahadashas (1 gads = 25px)
            const pxPms1 = 25 / (365.25 * 86400000);
            const dashaItems = profile.vedic.all_dashas.map(d => ({ start: new Date(d.start).getTime(), end: new Date(d.end).getTime(), lord: d.lord, tooltip: `Mahadasha: ${d.lord} (${new Date(d.start).toLocaleDateString()} - ${new Date(d.end).toLocaleDateString()})` }));
            
            // II. Gada Virziens (Sankranti + Gochara)
            const pxPms2 = 80 / (30 * 86400000); // 80px per 30 days roughly
            const yf = profile.vedic.yearly_forecast;
            
            const transitItems = yf.months.map((m, idx) => {
                const isCurrentMonth = (nowMs >= m.start && nowMs <= m.end);
                const zSignStr = m.tooltip.split(": ")[1]?.split("\n")[0] || "Zīme";
                
                // Vēdiskais saules mēnesis (Sankranti) sākas ap 15. datumu un ilgst līdz nākamā mēneša vidum
                const mStart = new Date(m.start);
                const mEnd = new Date(m.end);
                const lvMonths = ["Jan", "Feb", "Mar", "Apr", "Mai", "Jūn", "Jūl", "Aug", "Sep", "Okt", "Nov", "Dec"];
                
                let moStr = lvMonths[mStart.getMonth()];
                if (mStart.getMonth() !== mEnd.getMonth()) {
                    moStr = lvMonths[mStart.getMonth()] + "/" + lvMonths[mEnd.getMonth()];
                }
                
                const yrStr = mEnd.getFullYear().toString().substring(2);
                
                const dispText = isCurrentMonth ? 
                    `<span style="font-size:0.7rem; font-weight:bold; color:#475569;">${moStr} '${yrStr}</span><br><span style="font-size:0.6rem; color:#94a3b8;">(${zSignStr.trim().substring(0,3)})</span>` : 
                    `<span style="font-size:0.75rem; font-weight:bold; color:#64748b;">${moStr}</span><br><span style="font-size:0.6rem; color:#94a3b8;">'${yrStr} (${zSignStr.trim().substring(0,3)})</span>`;

                return {
                    start: m.start,
                    end: m.end,
                    color: isCurrentMonth ? "rgba(0,0,0,0.08)" : "transparent",
                    text: dispText,
                    tooltip: m.tooltip
                };
            });

            // Date boundary strings
            const leftDateStr = new Date(nowMs - (180 * 86400000)).toLocaleDateString();
            const rightDateStr = new Date(nowMs + (180 * 86400000)).toLocaleDateString();

            let tier2Html = generateTier(transitItems, pxPms2, transColors);
            
            // Add Scale Boundary Markers inside the tier container
            tier2Html += `
                <div style="position:absolute; left:2px; bottom:2px; font-size:10px; color:#64748b; font-family:monospace; z-index:2;">${leftDateStr} (-6 mēn)</div>
                <div style="position:absolute; right:2px; bottom:2px; font-size:10px; color:#64748b; font-family:monospace; z-index:2;">(+6 mēn) ${rightDateStr}</div>
            `;

            // Helper for formatting dates inside the overlays
            const formatShortDate = (ms) => {
                const d = new Date(ms);
                const lvMonths = ["Jan", "Feb", "Mar", "Apr", "Mai", "Jūn", "Jūl", "Aug", "Sep", "Okt", "Nov", "Dec"];
                return `${d.getDate()}.${lvMonths[d.getMonth()]}`;
            };

            // Retrogrades Overlay
            yf.retrogrades.forEach(r => {
                const centerOffsetMs = ((r.start + r.end) / 2) - nowMs;
                const durationMs = r.end - r.start;
                const centerPx = centerOffsetMs * pxPms2;
                const widthPx = durationMs * pxPms2;
                
                const startDateStr = formatShortDate(r.start);
                const endDateStr = formatShortDate(r.end);
                
                tier2Html += `
                    <div style="position:absolute; left:calc(50% + ${centerPx}px); width:${widthPx}px; top:0; bottom:0; transform:translateX(-50%); background: repeating-linear-gradient(45deg, rgba(239,68,68,0.15), rgba(239,68,68,0.15) 5px, rgba(255,255,255,0.6) 5px, rgba(255,255,255,0.6) 10px); z-index:10; pointer-events:none; border-left:1px dashed #ef4444; border-right:1px dashed #ef4444;">
                        <div style="position:absolute; bottom:calc(100% + 10px); left:50%; transform:translateX(-50%); display:flex; flex-direction:column; align-items:center; background:rgba(255,255,255,0.95); border:1px solid rgba(239,68,68,0.3); border-radius:4px; padding:2px 6px; box-shadow:0 2px 5px rgba(0,0,0,0.05); white-space:nowrap;">
                            <span style="color:#ef4444; font-size:0.65rem; font-weight:bold;">[ R ] ${r.planet}</span>
                            <span style="font-size:0.55rem; color:#475569; font-family:monospace; margin-top:1px;">${startDateStr} - ${endDateStr}</span>
                        </div>
                        <div style="position:absolute; top:-10px; left:50%; width:1px; height:10px; background:rgba(239,68,68,0.5);"></div>
                    </div>
                `;
            });

            // Eclipses Overlay
            yf.eclipses.forEach(e => {
                const offsetMs = e.time - nowMs;
                const leftPx = offsetMs * pxPms2;
                const eclipseDateStr = formatShortDate(e.time);
                
                tier2Html += `
                    <div style="position:absolute; left:calc(50% + ${leftPx}px); top:calc(100% + 15px); transform:translateX(-50%); z-index:15; display:flex; flex-direction:column; align-items:center; cursor:help;" title="${e.type}\nBīstams un karmisks likteņa krustpunkts. Neuzsākt neko jaunu!">
                        <div style="position:absolute; bottom:100%; left:50%; width:1px; height:15px; background:rgba(239,68,68,0.5);"></div>
                        <span style="font-size:0.9rem; text-shadow: 0 0 15px rgba(239,68,68,0.4); margin-top:-5px;">🌑</span>
                        <span style="font-size:0.55rem; font-weight:bold; color:#475569; background:rgba(255,255,255,0.95); padding:1px 4px; border-radius:3px; margin-top:2px; border:1px solid rgba(0,0,0,0.1); white-space:nowrap;">${eclipseDateStr}</span>
                    </div>
                `;
            });
            
            // III. Mēness Sinusoīda (14 dienu bioloģiskais cikls)
            const phaseAng = profile.lunar_phase_angle; 
            const daysToDraw = 40;
            let pathD = `M -500 50 `;
            let dotsHtml = "";
            let labelsHtml = ""; // HTML priekš ārpus-rāmja tekstiem
            
            for(let d = -daysToDraw; d <= daysToDraw; d++) {
                let x = d * 25; // 25px dienā
                let rad = (phaseAng + (d * 12.19));
                let radNorm = (rad % 360 + 360) % 360;
                // Jaunmēness (0 grādi) = Apakša (y=90), Pilnmēness (180 grādi) = Augša (y=10)
                let y = 50 + (Math.cos(radNorm * Math.PI / 180) * 40);
                pathD += `L ${500 + x} ${y} `;
                
                // Pievienojam Tithi punktu uz katru dienu
                const tData = getTithiData(radNorm);
                
                let rSize = 3.5;
                let dnText = d === 0 ? "Šodien" : (d > 0 ? '+'+d+' dienas' : d+' dienas');
                let tooltip = `${dnText}: ${tData.cycle_no}. Tithi (${tData.name} - ${tData.group})\n${tData.char}`;
                
                let interAction = tData.group === "Rikta" ? `cursor="pointer" onclick="alert('SARKANAIS LOGS (${tData.name}): Šodien nesāc neko jaunu vai svarīgu! Šīs fāzes enerģija ir vērsta uz destrukciju, tāpēc diena ir ideāla telpu tīrīšanai, uzkopšanai, cīņai, ienaidnieku neitralizēšanai un vecu parādu atdošanai.')"` : ``;
                let pointerStyle = tData.group === "Rikta" ? `cursor:pointer;` : ``;
                
                let dotStyle = `fill="${tData.color}" stroke="rgba(255,255,255,0.9)" stroke-width="1" ${interAction} style="${pointerStyle}"`;
                let extraHtml = "";
                
                if (tData.group === "Rikta") {
                    let dx = 500 + x;
                    extraHtml += `<ellipse cx="${dx}" cy="${y}" rx="18" ry="12" fill="rgba(239, 68, 68, 0.15)" style="pointer-events:none;" />`;
                }
                
                if (tData.name === "Ekadashi") {
                    let dx = 500 + x;
                    // Ekadashi Dimants ar mirdzumu
                    extraHtml += `
                        <circle cx="${dx}" cy="${y}" r="12" fill="#fbbf24" opacity="0.3" style="pointer-events:none;" />
                        <polygon points="${dx},${y-6} ${dx+5},${y} ${dx},${y+6} ${dx-5},${y}" fill="#fbbf24" stroke="#ca8a04" stroke-width="1" style="pointer-events:none;" />
                        <text x="${dx}" y="${y+18}" font-family="Outfit" font-size="0.55rem" fill="#ca8a04" text-anchor="middle" font-weight="800" letter-spacing="0.5" style="pointer-events:none;">ATSLODZE</text>
                    `;
                    dotStyle = `fill="#fff" stroke="#ca8a04" stroke-width="0.5"`; // mazs balts vidiņš dimantam
                    rSize = 2;
                }
                
                if (d === 0) {
                    labelsHtml += `<div style="position:absolute; left:calc(50% + ${x}px); top:108px; transform:translateX(-50%); font-family:Outfit; font-size:0.65rem; font-weight:800; color:#10b981; letter-spacing:0.5px; z-index:10; pointer-events:none; border-bottom:2px solid #10b981;">ŠODIEN</div>`;
                }
                
                let targetDate = new Date();
                targetDate.setDate(targetDate.getDate() + d);
                if (targetDate.getDay() === 1) { // Pirmdiena
                    const monthsLatvian = ["jan.", "feb.", "mar.", "apr.", "mai", "jūn.", "jūl.", "aug.", "sep.", "okt.", "nov.", "dec."];
                    let dayNum = targetDate.getDate();
                    let monthName = monthsLatvian[targetDate.getMonth()];
                    
                    extraHtml += `
                        <line x1="${500+x}" y1="0" x2="${500+x}" y2="100" stroke="rgba(100,116,139,0.2)" stroke-dasharray="4 2" stroke-width="1" style="pointer-events:none;" />
                    `;
                    labelsHtml += `<div style="position:absolute; left:calc(50% + ${x + 6}px); top:-18px; font-family:Outfit; font-size:0.55rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; pointer-events:none;">PIRMDIENA, ${dayNum}. ${monthName}</div>`;
                }
                
                dotsHtml += `${extraHtml}<circle cx="${500 + x}" cy="${y}" r="${rSize}" ${dotStyle}><title>${tooltip}</title></circle>`;
            }
            const yNow = 50 + (Math.cos(phaseAng*Math.PI/180)*40);
            const tn = profile.tithi_now;
            const tier3Html = `
            <div style="display:flex; flex-direction:column; gap: 0.8rem; margin-bottom: 0px; margin-top: 20px; width: 100%;">
                
                <!-- VISU VIĻŅA LIETU IETVARS AR OVERFLOW:HIDDEN LAPAS MALU NOGRIEŠANAI -->
                <div style="width:100%; min-width:300px; position:relative; overflow:hidden; padding-top:20px; padding-bottom:30px; box-sizing:border-box; margin-top:-5px; margin-bottom:-10px;">
                    
                    <!-- 14 Dienu Viļņa Konteiners (100px augstumā) -->
                    <div style="width:100%; position:relative; height:100px;">
                        
                        <!-- Ārpus-rāmja teksti, kas tagad tiks apgriezti, ja izlīdīs ārpus 100% rāmja platuma -->
                        ${labelsHtml}
                    
                        <!-- Wrapper ar hidden, lai pati likne neiziet ārpus rāmja (top/bottom), bet playhead tags paliktu redzams -->
                        <div style="position:absolute; top:0; left:0; width:100%; height:100px; overflow:hidden; border-radius:8px; border:1px solid rgba(0,0,0,0.1); background: linear-gradient(to top, rgba(0,0,0,0.1), rgba(255,255,255,0.7)); box-sizing:border-box; z-index:1;">
                            <!-- SVG nav apgriezts (ne-hidden) fiziski iekšā, bet parent wrapper viņu apgriezīs -->
                            <svg class="v-sine-svg" viewBox="-500 0 2000 100" preserveAspectRatio="xMidYMid slice" style="position:absolute; left:calc(50% - 1000px); width:2000px; height:100px; top:0px; overflow:visible;">
                                <line x1="-500" y1="50" x2="1500" y2="50" stroke="rgba(0,0,0,0.06)" stroke-width="1" stroke-dasharray="8 8"/>
                                <path d="${pathD}" stroke="rgba(0, 0, 0, 0.4)" stroke-width="2" fill="none" />
                                ${dotsHtml}
                            </svg>
                            
                            <!-- Tagad balti mirdzošais Mēness punkts -->
                            <div style="position:absolute; left: calc(50% - 6px); top: ${yNow - 6}px; width:12px; height:12px; background:#fff; border-radius:50%; box-shadow:0 0 15px #fff, 0 0 8px ${tn.color}; border: 2px solid ${tn.color}; z-index:10; pointer-events:none; animation: pulseMoon 2s infinite;" title="TAGAD"></div>
                        </div>
                        
                        <!-- TAGAD vertikālā līnija izbīdīta stāvu augstāk ārpus overflow:hidden wrappera! -->
                        <div class="v-local-playhead" style="left:50%; top:0; bottom:0; z-index:5;"></div>
                    </div>
                </div>

                <!-- TAGAD INFO PANELS -->
                <style>
                @keyframes pulseMoon {
                    0% { transform: scale(1); box-shadow: 0 0 15px #fff, 0 0 4px ${tn.color}; }
                    50% { transform: scale(1.3); box-shadow: 0 0 25px #fff, 0 0 12px ${tn.color}; }
                    100% { transform: scale(1); box-shadow: 0 0 15px #fff, 0 0 4px ${tn.color}; }
                }
                </style>
                <div style="width: 100%; background: rgba(255,255,255,0.6); backdrop-filter: blur(5px); border: 1px solid var(--glass-border); border-radius: 8px; padding: 12px; display:flex; flex-wrap:wrap; gap:16px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); box-sizing: border-box;">
                    
                    <!-- Kreisā Puse: Fāze un Rīcība -->
                    <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:6px;">
                        <div style="font-size: 0.75rem; color: #64748b; font-weight:800; text-transform:uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 4px; margin-bottom: 2px;">Tagadnes Pozīcija uz Viļņa:</div>
                        <div style="font-size: 0.85rem; display:flex; align-items:center; gap:6px;">
                            <span style="font-size: 1.1rem;">${tn.cycle_no === 30 ? '🌑' : (tn.cycle_no === 15 ? '🌕' : (tn.paksha.includes('aug') ? '🌙' : '🌘'))}</span>
                            <b>Fāze:</b> ${tn.paksha}
                        </div>
                        <div style="font-size: 0.85rem;"><b>Diena:</b> <span style="color:${tn.color}; font-weight:900; font-size:0.9rem;">${tn.cycle_no}. Tithi (${tn.name})</span> &mdash; ${tn.group}</div>
                        <div style="font-size: 0.85rem; color: #1e293b; line-height:1.4;"><b>Padoms:</b> ${tn.action}</div>
                        <div style="font-size: 0.8rem; color: #ef4444; font-weight:600; line-height:1.4; margin-top:2px; background:rgba(239, 68, 68, 0.1); padding: 4px 6px; border-radius:4px; border-left: 2px solid #ef4444;">⚠️ Uzmanību: <span style="font-weight:normal; color:#b91c1c;">${tn.warning}</span></div>
                    </div>
                    
                    <!-- Labā Puse: Simbolika un Veselība -->
                    <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:6px; border-left: 1px solid rgba(0,0,0,0.05); padding-left: 16px;">
                        <div style="font-size: 0.75rem; color: #64748b; font-weight:800; text-transform:uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 4px; margin-bottom: 2px;">Simbolika un Higiēna:</div>
                        <div style="font-size: 0.85rem; display:flex; align-items:center; gap:6px;">
                            <span style="font-size: 1.1rem;">${tn.element === 'Zeme' ? '🏔️' : (tn.element === 'Uguns' ? '🔥' : (tn.element === 'Ūdens' ? '💧' : (tn.element === 'Gaiss' ? '💨' : '✨')))}</span> 
                            <b>Elements:</b> <span style="color:#475569;">${tn.element}</span>
                        </div>
                        <div style="font-size: 0.85rem;"><b>Dienas Pārvaldnieks:</b> ${tn.deity}</div>
                        <div style="font-size: 0.85rem; color: #15803d; line-height:1.4; background:rgba(34, 197, 94, 0.1); padding: 4px 6px; border-radius:4px; border-left: 2px solid #22c55e;">🥗 <b>Uztura filtrs:</b> Šodien nav ieteicams uzturā lietot: <b>${tn.diet}</b></div>
                    </div>

                </div>
                
                <!-- LEĢENDA -->
                <div style="font-size:0.75rem; color:#64748b; line-height:1.5; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.03); border: 1px dashed rgba(0,0,0,0.1); display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <div style="flex:1; min-width:220px;">
                        <b style="color:#475569; font-size:0.8rem; text-transform:uppercase; display:block; margin-bottom:4px;">Pilnā Krāsu Leģenda</b>
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10b981; margin-right:4px;"></span> Zaļš = Droša Izaugsme / Stabilitāte (Nanda, Bhadra, Jaya)<br>
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#fbbf24; margin-right:4px;"></span> Zelts = Bizness / Kulminācija / Līdzsvars (Purna)<br>
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#ef4444; margin-right:4px;"></span> Sarkans = Sarkanais logs / Briesmīgi Šķēršļi (Rikta)<br>
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#64748b; margin-right:4px;"></span> Pelēks = Neitrāls fons / Nulles Punkts (Jaunmēness)<br>
                        <span style="color:#fbbf24; font-size:1.1rem; line-height:1; vertical-align:middle;">🔶</span> Zelts (Dimants) = Ekadashi (Bioloģiskās atslodzes un attīrīšanās diena)
                    </div>
                    <div style="flex:1; min-width:220px;">
                        <b style="color:#475569; font-size:0.8rem; text-transform:uppercase; display:block; margin-bottom:4px;">Viļņa Plūsmas Ritmika</b>
                        📈 <b style="color:#10b981">Vilnis kāpj uz augšu (Shukla Paksha):</b> Bioloģiskā enerģija un entuziasms sabiedrībā pieaug. Labākais laiks uzņēmuma dibināšanai, jauniem kontaktiem un sociālajai paplašināšanai.<br>
                        📉 <b style="color:#ef4444">Vilnis slīd uz leju (Krishna Paksha):</b> Masu enerģija sarūk, mazinās tolerance un pacietība. Pienācis laiks atdot parādus, tīrīt procesus un aizvērt projektus. Nelūgt palīdzību.
                    </div>
                </div>
            </div>`;

            // IV. Nakšatras 48h logs ar precīzām stundām un minūtēm
            // Lietotājs lūdza vēl platāku pa horizontālo asi. Default 150px -> 350px per diena
            const pxPms4 = 350 / 86400000;
            let currentNakStrHtml = ""; // To store current nakshatra html for the details panel
            
            const nakItems = profile.nakshatra_transits_48h.map(n => {
                let startMs = n.startMs;
                let endMs = n.endMs;
                let tBala = n.taraBala;
                let nakD = n.nakData;
                
                let isCurrent = (nowMs >= startMs && nowMs <= endMs);
                
                // Border depends on Tara Bala
                let oborderColor = (tBala.statusFrame === "red") ? "#ef4444" : (tBala.statusFrame === "gold" ? "#fbbf24" : nakD.natureBorder);
                let borderThickness = isCurrent ? "2px" : "1px";
                let styleStr = `border-left:${borderThickness} solid ${oborderColor}; border-right:${borderThickness} solid ${oborderColor}; overflow:visible !important; `;
                if(isCurrent) {
                    styleStr += `border-top:2px solid ${oborderColor}; border-bottom:2px solid ${oborderColor}; box-shadow: 0 0 15px ${oborderColor}40; z-index: 10; opacity: 1;`;
                } else {
                    styleStr += `border-top:1px dashed ${oborderColor}40; border-bottom:1px dashed ${oborderColor}40; opacity: 0.6;`;
                }
                
                // Draw vertical precise time marker on the left
                // Pabīdu vēl zemāk no augšējā teksta, lai nepārklājas (top:-28px)
                let dtStartLocal = new Date(startMs).toLocaleTimeString('lv-LV', {hour: '2-digit', minute:'2-digit', hour12: false});
                let markerHtml = `<div style="position:absolute; left:-18px; top:-28px; font-size:0.75rem; color:#475569; font-weight:bold; font-family:monospace; background:rgba(255,255,255,0.95); border:1px solid rgba(0,0,0,0.1); border-radius:4px; padding:2px 8px; z-index:20; white-space:nowrap; box-shadow:0 2px 4px rgba(0,0,0,0.05);">${dtStartLocal}</div>`;
                
                let repeatingSymbols = nakD.natureSymbol.repeat(Math.max(1, n.intensity || 1));
                
                let latvianNature = nakD.natureName;
                let matchNature = nakD.natureName.match(/\(([^)]+)\)/);
                if (matchNature) latvianNature = matchNature[1];
                
                if (isCurrent) {
                    let timeLeftMs = endMs - nowMs;
                    let hoursLeft = Math.floor(timeLeftMs / (1000 * 60 * 60));
                    let minsLeft = Math.floor((timeLeftMs % (1000 * 60 * 60)) / (1000 * 60));
                    let endTimeStr = new Date(endMs).toLocaleTimeString('lv-LV', {hour: '2-digit', minute:'2-digit', hour12: false});
                    
                    let bgUpaya = tBala.statusFrame === "red" ? "rgba(16, 185, 129, 0.1)" : "rgba(59, 130, 246, 0.1)";
                    let borderUpaya = tBala.statusFrame === "red" ? "#10b981" : "#3b82f6";
                    
                    currentNakStrHtml = `
                    <div style="width: 100%; background: linear-gradient(135deg, rgba(255,255,255,0.7), rgba(255,255,255,0.9)); backdrop-filter: blur(5px); border: 1px solid var(--glass-border); border-top: 3px solid ${oborderColor}; border-radius: 8px; padding: 16px; display:flex; flex-wrap:wrap; gap:16px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); box-sizing: border-box; margin-top:20px;">
                        
                        <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:8px;">
                            <div style="font-size: 0.75rem; color: #64748b; font-weight:800; text-transform:uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 4px; margin-bottom: 2px;">Psiholoģiskais Fokuss un Rīcība:</div>
                            <div style="font-size: 0.9rem; display:flex; align-items:center; gap:6px;">
                                <span style="font-size: 1.3rem;">${nakD.natureSymbol}</span>
                                <b>Nakšatra:</b> <span style="color:#1e293b; font-size:1.1rem;">${nakD.nakshatra} <span style="font-size:1.3rem;">${nakD.nakIcon}</span></span>
                            </div>
                            <div style="font-size: 0.9rem;"><b>Dienas kvalitāte:</b> <span style="color:${nakD.natureBorder}; font-weight:bold;">${nakD.natureName}</span></div>
                            <div style="font-size: 0.9rem; color: #1e293b; line-height:1.5;"><b>Fokuss:</b> ${nakD.natureFocus}</div>
                            
                            <div style="margin-top: 8px; background:${bgUpaya}; border-left: 3px solid ${borderUpaya}; border-radius:4px; padding:8px; font-size:0.85rem; line-height:1.4;">
                                <div style="font-weight:bold; color:${borderUpaya}; margin-bottom:4px; text-transform:uppercase; font-size:0.75rem;">🌿 Upaja (Kā harmonizēt?):</div>
                                <div style="color:#334155;">${nakD.therapy.upaya_spirit}</div>
                            </div>
                        </div>
                        
                        <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:8px; border-left: 1px solid rgba(0,0,0,0.05); padding-left: 16px;">
                            <div style="font-size: 0.75rem; color: #64748b; font-weight:800; text-transform:uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 4px; margin-bottom: 2px;">
                                Tava Personīgā Jauda:
                            </div>
                            <div style="font-size: 0.9rem; display:flex; align-items:center; gap:6px;">
                                <span style="font-size: 1.4rem;">${tBala.icon}</span> 
                                <b>Status:</b> <span style="color:${oborderColor}; font-weight:bold; font-size:1.1rem;">${tBala.title}</span>
                            </div>
                            <div style="font-size: 0.9rem;"><b>Vērtējums:</b> ${tBala.rating} <span style="color:#64748b;">(${tBala.ratingLabel})</span></div>
                            <div style="font-size: 0.9rem; color: ${oborderColor}; line-height:1.5; background:${oborderColor}15; padding: 8px; border-radius:4px; border-left: 2px solid ${oborderColor};"><b>Brīdinājums:</b> ${tBala.warning}</div>
                            
                            <div style="margin-top:auto; font-size: 0.85rem; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.05); border-radius:4px; padding:6px 10px; display:inline-block; font-family:monospace; font-weight:bold; color:#475569;">
                                ⏳ Beidzas pēc: <span style="color:#1e293b;">${hoursLeft}h ${minsLeft}min</span> (līdz ${endTimeStr})
                            </div>
                        </div>
                    </div>`;
                }
                
                return { 
                    start: startMs, 
                    end: endMs, 
                    text: `${markerHtml}<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%;"><span style="font-size:1.3rem; letter-spacing:1px; filter: drop-shadow(0 0 5px rgba(255,255,255,0.7));">${repeatingSymbols}</span><span style="font-size:0.65rem; color:#475569; margin-top:2px; font-weight:900; letter-spacing:0.5px; text-transform:uppercase;">${latvianNature}</span></div>`, 
                    color: nakD.natureColor, 
                    extraStyle: styleStr,
                    tooltip: `${nakD.nakshatra} Nakšatra (${nakD.natureName})\nStatuss: ${tBala.title}\nPrecīzs sākums: ${new Date(startMs).toLocaleString('lv-LV', {hour12: false})}` 
                };
            });

            // V. Muhurtas (ap 48 min = ~60px)
            const pxPms5 = 60 / (48 * 60000);
            
            let currentMuhStrHtml = "";
            
            const muhItems = profile.vedic.muhurtas_today.muhurtas.map(m => {
                let st = m.start;
                let en = m.end;
                let isCurrent = (nowMs >= st && nowMs <= en);
                
                let col = m.color + "40"; // 40 hex opacity
                let bcol = m.color;
                let txt = m.name; // Keep text short on timeline
                
                let styleStr = 'border-left:1px solid ' + bcol + '; border-right:1px solid ' + bcol + '; font-size:0.6rem; color:#475569; padding-top:2px; justify-content:flex-start; text-indent:4px; overflow:hidden; white-space:nowrap;';
                if(isCurrent) {
                    styleStr += `border-top:2px solid ${bcol}; border-bottom:2px solid ${bcol}; box-shadow: 0 0 10px ${bcol}40; z-index: 10; opacity: 1; font-weight:bold; color:#000;`;
                }
                
                let iconHtml = m.no === 1 ? '<div style="position:absolute; top:-20px; left:-6px; font-size:14px;" title="Saullēkts">☀️</div>' : (m.no === 16 ? '<div style="position:absolute; top:-20px; left:-6px; font-size:14px;" title="Saulriets">🌙</div>' : '');

                return { 
                    start: st, 
                    end: en, 
                    color: col, 
                    text: `${iconHtml}${txt}`, 
                    extraStyle: styleStr,
                    tooltip: `${m.no}. ${m.isDay ? 'Dienas' : 'Nakts'} Muhurta (${m.title})\nKvalitāte: ${m.qual}\nDarbība: ${m.action}` 
                };
            });
            
            // Add Rahu Kaal Overlay
            profile.vedic.muhurtas_today.rahu.forEach(r => {
                muhItems.push({
                    start: r.start,
                    end: r.end,
                    color: 'transparent',
                    text: `<div style="position:absolute; left:0; width:100%; top:0; bottom:0; background: repeating-linear-gradient(45deg, rgba(239,68,68,0.2), rgba(239,68,68,0.2) 5px, rgba(255,255,255,0) 5px, rgba(255,255,255,0) 10px); z-index:15; pointer-events:none; border-top:2px solid #ef4444; border-bottom:2px solid #ef4444;"></div>
                           <div style="position:absolute; top:-18px; left:50%; transform:translateX(-50%); font-weight:bold; color:#ef4444; font-size:0.65rem; background:#fff; padding:0 4px; border-radius:4px; border:1px solid #ef4444; white-space:nowrap;" title="Rahu Kaal = Šķēršļu josla. Laiks, kad visums izņem no sistēmas atbalstu. Svarīgus darbus nesākt!">TUKŠAIS LAIKS (RAHU KAAL)</div>`,
                    extraStyle: 'pointer-events:none; overflow:visible !important; z-index:15;'
                });
            });
            
            // Generate the details panel for Muhurta & Hora
            let activeMuh = profile.vedic.muhurtas_today.muhurtas.find(m => nowMs >= m.start && nowMs <= m.end);
            let activeRahu = profile.vedic.muhurtas_today.rahu.find(r => nowMs >= r.start && nowMs <= r.end);
            let activeHora = profile.vedic.muhurtas_today.horas.find(h => nowMs >= h.start && nowMs <= h.end);
            
            if (activeMuh && activeHora) {
                let timeLeftMs = activeMuh.end - nowMs;
                let minsLeft = Math.floor(timeLeftMs / 60000);
                
                let hTimeLeftMs = activeHora.end - nowMs;
                let hMinsLeft = Math.floor(hTimeLeftMs / 60000);
                
                let isRahuActive = !!activeRahu;
                let muhBorder = activeMuh.color;
                
                let isRahuWarningHtml = isRahuActive ? `<div style="background:#fef2f2; border:1px solid #fca5a5; border-left:4px solid #ef4444; padding:8px; border-radius:4px; margin-top:10px; color:#b91c1c; font-size:0.85rem; font-weight:bold;">🚨 Šobrīd aktīvs TUKŠAIS LAIKS (Rahu Kaal) — Šķēršļu josla! Visums šobrīd nedod atbalstu, tāpēc atliec visus sākumus. </div>` : '';
                
                let muhQualIcon = activeMuh.qual === "ZELTS" ? "⭐" : (activeMuh.qual === "Destruktīva" || activeMuh.qual.includes("Bīstama") ? "❗" : "✅");
                
                currentMuhStrHtml = `
                    <div style="width: 100%; background: linear-gradient(135deg, rgba(255,255,255,0.7), rgba(255,255,255,0.9)); backdrop-filter: blur(5px); border: 1px solid var(--glass-border); border-top: 3px solid ${muhBorder}; border-radius: 8px; padding: 16px; display:flex; flex-wrap:wrap; gap:16px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); box-sizing: border-box; margin-top:35px; margin-bottom:15px; position:relative;">
                        
                        <div style="width:100%; text-align:center; padding: 12px; background:rgba(255,255,255,0.9); border-radius:8px; border:2px solid ${muhBorder}30; box-shadow:0 2px 5px rgba(0,0,0,0.02); margin-bottom:8px;">
                            <div style="font-size:0.75rem; color:#64748b; font-weight:bold; letter-spacing:1px; text-transform:uppercase; margin-bottom:4px;">Īsais Kopsavilkums (Sinerģija)</div>
                            <div style="font-size:1.15rem; color:#1e293b; font-weight:500;">
                                <b>Šobrīd:</b> ${activeMuh.status_text} (${activeMuh.title}), izmantojot "${activeHora.focus.split(', ')[0].toLowerCase()}" kā mērķi (${activeHora.ruler.toLowerCase()}s hora).
                            </div>
                        </div>

                        <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:8px;">
                            <div style="font-size: 0.75rem; color: #64748b; font-weight:800; text-transform:uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 4px; margin-bottom: 2px; display:flex; justify-content:space-between; align-items:center;">
                                <span>Operatīvais Logs (Muhurta):</span>
                                <span style="font-size:1.2rem;">${activeMuh.status_icon}</span>
                            </div>
                            <div style="font-size: 0.9rem; display:flex; align-items:center; gap:6px;">
                                <span style="font-size: 1.3rem;">🎯</span>
                                <b>Jaudas punkts:</b> <span style="color:#1e293b; font-size:1.1rem; font-weight:bold;">${activeMuh.title}</span> <span style="font-size:0.8rem; color:#64748b;">(${activeMuh.name})</span>
                            </div>
                            <div style="font-size: 0.9rem;"><b>Kvalitāte:</b> <span style="color:${muhBorder}; font-weight:bold;">${muhQualIcon} ${activeMuh.qual}</span></div>
                            <div style="font-size: 0.9rem; color: #1e293b; line-height:1.5;"><b>Ko darīt:</b> ${activeMuh.action}</div>
                            
                            ${isRahuWarningHtml}
                            
                            <div style="margin-top:auto; font-size: 0.85rem; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.05); border-radius:4px; padding:6px 10px; display:inline-block; font-family:monospace; font-weight:bold; color:#475569;">
                                ⏳ Sāksies nākamais logs pēc: <span style="color:#1e293b;">${minsLeft} min</span> (līdz ${new Date(activeMuh.end).toLocaleTimeString('lv-LV', {hour:'2-digit', minute:'2-digit'})})
                            </div>
                        </div>
                        
                        <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:8px; border-left: 1px solid rgba(0,0,0,0.05); padding-left: 16px;">
                            <div style="font-size: 0.75rem; color: #64748b; font-weight:800; text-transform:uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 4px; margin-bottom: 2px;">
                                TĒMA: Planētu Stunda (Hora):
                            </div>
                            <div style="font-size: 0.9rem; display:flex; align-items:center; gap:6px;">
                                <span style="font-size: 1.4rem;">🪐</span> 
                                <b>Valdošā Planēta:</b> <span style="color:#7c3aed; font-weight:bold; font-size:1.1rem; text-transform:uppercase;">${activeHora.ruler}</span>
                            </div>
                            <div style="font-size: 0.9rem; color: #1e293b; line-height:1.5;"><b>Ideālais fokuss:</b> ${activeHora.focus}</div>
                            <div style="margin-top:auto; font-size: 0.85rem; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.05); border-radius:4px; padding:6px 10px; display:inline-block; font-family:monospace; font-weight:bold; color:#475569;">
                                ⏳ Stundas tēma nomainīsies pēc: <span style="color:#1e293b;">${hMinsLeft} min</span> (līdz ${new Date(activeHora.end).toLocaleTimeString('lv-LV', {hour:'2-digit', minute:'2-digit'})})
                            </div>
                        </div>
                        
                        <div style="width:100%; border-top:1px dashed rgba(0,0,0,0.1); padding-top:8px; margin-top:8px; font-size:0.75rem; color:#94a3b8; text-align:left;">
                            *Piezīme: Šis plānotājs ir piesaistīts šodienas saullēktam izvēlētajā lokācijā (un dienas/nakts garumiem), tāpēc minūšu intervāli katru dienu nedaudz mainīsies.
                        </div>
                    </div>`;
            }

            // Build BIOS UI
            function transformVedicToLatvian(pStr) {
                if(pStr.includes("Artha")) return { text: "Resursi un Peļņa", color: "#10b981", icon: "💎" };
                if(pStr.includes("Kama")) return { text: "Bauda un Statuss", color: "#f59e0b", icon: "✨" };
                if(pStr.includes("Dharma")) return { text: "Kārtība un Misija", color: "#3b82f6", icon: "⚖️" };
                if(pStr.includes("Moksha")) return { text: "Inovācija un Atbrīve", color: "#c084fc", icon: "🚀" };
                return { text: "Nezināms", color: "#ccc", icon: "❔" };
            }

            const biosDesc = profile.vedic.nakshatra.purushartha;
            const biosMeaning = profile.vedic.nakshatra.meaning;
            const biosUi = transformVedicToLatvian(biosDesc);

            const biosHtml = `
                <div style="margin-bottom: 2rem; padding: 1.5rem; background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; border-radius: 8px;">
                     <h4 style="margin:0 0 0.5rem 0; color: #2563eb; font-size:1.15rem;">👑 Klienta Dvēseles "BIOS" (Fiksēts uz mūžu)</h4>
                     <div style="font-size: 1rem; line-height: 1.6; color:#1e293b;">
                         Galvenais Dzīves Motīvs: <b style="color:${biosUi.color};">${biosUi.text.toUpperCase()}</b> <span style="font-size:0.85rem; color:#64748b;">(Zvaigzne: ${profile.vedic.nakshatra.nakshatra})</span><br>
                         Zemapziņas Instinkti: <b style="color:#fbbf24;">"${biosMeaning}"</b>
                         <p style="margin:0.5rem 0 0 0; font-size:0.9rem; color:#64748b;">Šis ir fundamentālais ieprogrammētais cilvēka dzinējs. Klients visu dzīvi pieņems lēmumus caur šo "Sistēmas" prizmu.</p>
                     </div>
                </div>
            `;

    // NB: '38 dienu emocionālais kalendārs' (calendar38 + showPanchangModal modālis) DZĒSTS
    // 2026-07-08 — panelis vairs netika rādīts (dublējās ar 'Dienas · biznesa pārskatu'),
    // un tā onclick sauca vairs neeksistējošu showPanchangModal. Rezerve: _rezerves/.

    // ── Koridors sadalīts gabalos, lai render_dashboard var sakārtot pēc laika horizonta ──
    // (finest→broadest) un sakļaut detalizēto "explorer". Lielie Cikli (Level 1) → cilnes lejā.

    const lifeCyclesPiece = `
                    <div class="vedic-5-dashboard" style="position:relative;">
                        <!-- Lielie Dzīves Cikli (120 gadi) — izcelts atsevišķi, cilnes lejā (plašākais mērogs) -->
                        <div style="margin-bottom: 0.5rem;">
                            
                            <div style="background:#f1f5f9; border-radius:6px; padding:10px 15px; margin-bottom:15px; font-size:0.8rem; color:#334155; border:1px solid #e2e8f0; display:flex; flex-direction:column; gap:10px;">
                                <div style="display:flex; gap:15px; flex-wrap:wrap; padding-bottom:5px; border-bottom: 1px solid #e2e8f0;">
                                    <div style="flex:1; min-width:200px;">
                                        <b style="color:#0ea5e9;">1. Slānis (Augšā):</b> Zvaigžņu logi. Precīzi unikālo iespēju gadi.
                                    </div>
                                    <div style="flex:1; min-width:200px;">
                                        <b style="color:#64748b;">2. Slānis (Platais):</b> Mainīgās lielo ēru enerģijas (Maha Dashas).
                                    </div>
                                    <div style="flex:1; min-width:200px;">
                                        <b style="color:#64748b;">3. Slānis (Svītrkods):</b> Lielās ēras detalizētais plāns (Antardashas).
                                    </div>
                                    <div style="flex:1; min-width:200px;">
                                        <b style="color:#ef4444;">4. Slānis (Apakšā):</b> Risku koridori un turbulences zonas.
                                    </div>
                                </div>
                                <div style="display:flex; gap:15px; flex-wrap:wrap; font-size:0.75rem;">
                                    <div style="flex:1; min-width:150px;">
                                        <b>✨ Jupitera Atgriešanās:</b> Zelta iespējas, liela veiksme un resursi.
                                    </div>
                                    <div style="flex:1; min-width:150px;">
                                        <b>🎯 Bhrigu Chakra:</b> Dzīves galvenā fokusa mērķēta maiņa.
                                    </div>
                                    <div style="flex:1; min-width:150px;">
                                        <b>⚡ Ganda-Anta:</b> Karmisks lūzums; vecās dzīves struktūras brūk (metamorfoze).
                                    </div>
                                    <div style="flex:1; min-width:150px;">
                                        <b>[ SADE SATI ]:</b> Saturna eksāmens; 7.5 gadi smaga, bet nozīmīga rakstura rūdīšana.
                                    </div>
                                </div>
                            </div>

                            <div class="genome-stack">
                                <div class="g-track g-track-returns">${trackReturnsHtml}</div>
                                <div class="g-track g-track-maha" style="display:flex;">${trackMahaHtml}</div>
                                <div class="g-track g-track-antar">${trackAntarHtml}</div>
                                <div class="g-track g-track-risk">${trackRiskHtml}</div>
                                <div class="g-scanner-beam" style="left: ${markerLeftPct}%;"></div>
                            </div>
                            
                            <!-- Vieta dinamiskajam Antardashu skaidrojumam -->
                            <div id="antar-reading-panel" style="display:none; margin-bottom: 25px; transition: all 0.3s ease-in-out;"></div>

                            <div class="scale-ticks">
                                <span>Dzimis (0)</span><span>30 gadi</span><span>60 gadi</span><span>90 gadi</span><span>120 gadi (Nāve)</span>
                            </div>
                            
                            ${vedicLegendHtml}
                            ${superEventsLegendHtml}
                            
                            <details style="margin-top: 15px;">
                                <summary style="cursor:pointer; color:#2563eb; font-size:0.85rem; font-weight:bold;">Apskatīt cikliskās kārtis (Detalizēti)</summary>
                                ${allDashasListHtml}
                            </details>
                        </div>
                    </div>`;

    const explorerPiece = `
                    <div class="vedic-5-dashboard" style="position:relative;">
                        <!-- Detalizētais laika koridors (gads → mēness → diena → muhurta); Lielie Cikli atsevišķi cilnes lejā -->
                        <div class="v-tier" style="position:relative; width:100%; margin-top:5rem; margin-bottom:14rem; height:58px;">
                            <div class="v-local-playhead" style="position:absolute; left:50%; top:0; bottom:0; z-index:5;"></div>
                            
                            <div class="v-tier-label" style="top:-100px; left:0;">
                                <div style="color: #7c3aed; font-size: 0.95rem;">Gada Virziens (1 gads)</div>
                                <div style="color: #ca8a04; font-weight: bold; font-size: 0.85rem; text-transform:none; margin-top:2px; letter-spacing:0.5px;">Gada Fokuss (Muntha): ${yf.muntha.focusTheme}${profile?.birth_info?.isTimeUnknown ? ` <span style="color:#dc2626; font-weight:700; cursor:help; white-space:nowrap;" title="Muntha nāk no Ascendanta, kas bez precīza dzimšanas laika nav nosakāms (~13% ticamība). Šī gada tēma ir tikai orientējoša.">⚠︎ orientējoši</span>` : ''}</div>
                            </div>
                            
                            ${tier2Html}
                            
                            <div style="position:absolute; left:0; bottom:-150px; display:flex; flex-direction:column; gap:6px; width:100%; box-sizing:border-box; padding:0 5px;">
                                <div style="display:flex; gap:10px;">
                                    <div style="flex:1; font-size:0.75rem; color:#475569; background:rgba(255,255,255,0.6); padding:6px; border-radius:4px; border-left:2px solid #10b981; line-height:1.3;">
                                        <b>Jupiters:</b> ${yf.jupiter_text}
                                    </div>
                                    <div style="flex:1; font-size:0.75rem; color:#475569; background:rgba(255,255,255,0.6); padding:6px; border-radius:4px; border-left:2px solid #6b7280; line-height:1.3;">
                                        <b>Saturns:</b> ${yf.saturn_text}
                                    </div>
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <div style="flex:1; font-size:0.65rem; color:#64748b; background:rgba(255, 255, 255, 0.4); padding:4px 6px; border-radius:4px; line-height:1.2; border:1px dashed rgba(239, 68, 68, 0.4);">
                                        <b style="color:#ef4444">[ R ] Retrogrāda zona:</b> Sistēmas "karāšanās". Pārpratumi, aizkavēšanās. Laiks labot vecās detaļas, <b>nesākt neko jaunu.</b>
                                    </div>
                                    <div style="flex:1; font-size:0.65rem; color:#64748b; background:rgba(255, 255, 255, 0.4); padding:4px 6px; border-radius:4px; line-height:1.2; border: 1px dashed rgba(71, 85, 105, 0.4);">
                                        <b style="color:#475569;">🌑 Aptumsums:</b> Augsta karmiskā spriedze. Trīs dienas pirms/pēc šī mirkļa nedrīkst pieņemt fundamentālus lēmumus.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="v-tier" style="background: rgba(255,255,255,0.6); margin-top:60px; height: auto; min-height: 85px; align-items: flex-start; padding: 10px 5px; flex-direction: column; box-sizing: border-box;">

                            
                            <div class="v-tier-label" style="top:-40px;">
                                <div style="color: #7c3aed; font-size: 0.95rem;">BIORITMS: Mēness Cikli</div>
                                <div style="color: #475569; font-weight: normal; font-size: 0.8rem; text-transform:none;">Tavs bioloģiskais ritms. Kad enerģija aug un kad ir laiks pabeigšanai.</div>
                            </div>
                            ${tier3Html}
                        </div>
                        
                        <!-- NB: "4. Dienas Stīga (1–2 dienas)" NOŅEMTA 2026-07-08. Vērtīgais,
                             saprotamais parametrs — nakšatras fokuss ("Kam šodiena piemērota") —
                             pārcelts uz 'Dienas · biznesa pārskatu' (Prognozes cilnes augšā).
                             Pārējais tiera saturs (Tara Bala statuss/jauda) jau bija pārskatā
                             kā "Mēness jauda". Tiera numerācija noslēgta (5→4, 6→5). -->

                        <div class="v-tier" style="margin-top:40px; height:auto; overflow:visible; flex-direction:column; align-items:flex-start;">
                            <div style="position:relative; width:100%; height:38px; overflow:visible; margin-bottom:30px; clip-path: inset(-80px 0px -40px 0px);">
                                <div class="v-local-playhead" style="z-index:20;"></div>

                                <div class="v-tier-label" style="top:-40px;">
                                    <div style="color: #7c3aed; font-size: 0.95rem;">Rīcības Mirklis (Muhurtas / ~48 min)</div>
                                    <div style="color: #475569; font-weight: normal; font-size: 0.8rem; text-transform:none;">Operatīvais logs un precīza plānošana.</div>
                                </div>
                                ${generateTier(muhItems, pxPms5, null)}
                                ${
                                    (function(){
                                        let axisHtml = '';
                                        let currentHourStart = new Date(nowMs);
                                        currentHourStart.setMinutes(0, 0, 0);
                                        let startHourMs = currentHourStart.getTime() - (12 * 3600000);
                                        let endHourMs = currentHourStart.getTime() + (12 * 3600000);
                                        for(let hMs = startHourMs; hMs <= endHourMs; hMs += 3600000) {
                                            let pxOffset = (hMs - nowMs) * pxPms5;
                                            if (pxOffset > -1500 && pxOffset < 1500) {
                                                let timeLabel = new Date(hMs).toLocaleTimeString('lv-LV', {hour: '2-digit', minute:'2-digit', hour12: false});
                                                axisHtml += `<div style="position:absolute; left:calc(50% + ${pxOffset}px); top:-5px; bottom:-25px; border-left:1px dashed rgba(0,0,0,0.15); z-index:0; pointer-events:none;"></div>`;
                                                axisHtml += `<div style="position:absolute; left:calc(50% + ${pxOffset}px - 15px); bottom:-25px; font-size:0.65rem; color:#94a3b8; font-weight:bold; width:30px; text-align:center;">${timeLabel}</div>`;
                                            }
                                        }
                                        return axisHtml;
                                    })()
                                }
                            </div>
                            
                            ${currentMuhStrHtml}
                        </div>
                    </div>`;

    const radarPiece = `
                    <!-- MAP SECTION (Pasaules Astroloģiskais Radars) -->
                    <div class="v-tier" style="margin-top:0; height:auto; overflow:visible; flex-direction:column; align-items:flex-start; background: transparent; box-shadow: none; padding: 0;">
                            <div class="v-tier-label" style="top:-20px; left:0; position:relative;">
                                <div style="color: #7c3aed; font-size: 0.95rem;">Pasaules Astroloģiskais Radars (Globālais mērogs)</div>
                                <div style="color: #475569; font-weight: normal; font-size: 0.8rem; text-transform:none;">Kā veiksmes un šķēršļu joslas ģeogrāfiski plūst pāri visai planētai.</div>
                            </div>
                            
                            <div class="slider-container" style="width: 100%; box-sizing: border-box; flex-wrap: wrap;">
                                <div style="display: flex; gap: 15px; align-items: center; justify-content: flex-start; width: 100%; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; margin-bottom: 5px;">
                                    <label>Datums:</label>
                                    <input type="date" id="map-date-picker" class="input-control" value="${new Date().toISOString().split('T')[0]}" style="padding: 0.4rem; font-size: 0.9rem; flex-grow:0; min-width: 150px;">
                                </div>
                                <div style="display: flex; width: 100%; align-items: center; gap: 15px;">
                                    <label>Laiks (St/Min):</label>
                                    <input type="range" id="map-time-slider" class="time-slider" min="0" max="1440" step="15" value="0">
                                    <span id="slider-time-display">00:00</span>
                                </div>
                            </div>
                            
                            <div id="latvia-map"></div>
                        </div>`;

    return {
        bios:        biosHtml,        // Nakšatras "dvēseles BIOS" (mūža konteksts)
        explorer:    explorerPiece,   // Detalizētais 4-līmeņu zoom (gads→muhurta) — sakļaujams
        radar:       radarPiece,      // Pasaules radars (Leaflet latvia-map)
        lifeCycles:  lifeCyclesPiece, // Lielie Dzīves Cikli (120 gadi) — cilnes lejā
    };
}
