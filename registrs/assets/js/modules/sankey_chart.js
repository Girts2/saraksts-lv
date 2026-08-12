// projects/python_generator/templates/assets/js/modules/sankey_chart.js

/**
 * D3 Sankey Chart Module
 * Atbild par Sankey diagrammas zīmēšanu.
 * Versija ar "Lazy Loading" - meklē D3 tikai izpildes brīdī.
 */
// projects/python_generator/templates/assets/js/modules/sankey_chart.js

/**
 * D3 Sankey Chart Module
 * Atbild par Sankey diagrammas zīmēšanu.
 * Versija ar "Lazy Loading" - meklē D3 tikai izpildes brīdī.
 */

const sankeyChartAreaId = 'd3_sankey_chart_area';
let svg, sankeyGenerator;
const chartWidth = 800;
const chartHeight = 450;
let d3color = null;

// Palīgfunkcija, lai droši iegūtu D3 objektu
function getD3() {
    if (typeof window.d3 !== 'undefined') {
        return window.d3;
    }
    return null;
}

export function initializeSVG() {
    const d3 = getD3();
    
    // Pārbaude: Vai D3 un Sankey spraudnis ir pieejams?
    if (!d3 || typeof d3.sankey !== 'function') {
        console.warn("Sankey Chart: D3.js vai d3.sankey nav ielādēts.");
        return false;
    }

    const container = d3.select("#" + sankeyChartAreaId);
    if (container.empty()) return false;
    
    // Notīra iepriekšējo saturu
    container.select("svg").remove();
    container.selectAll("*").remove(); // Drošības pēc iztīra visu
    
    try {
        svg = container.append("svg")
            .attr("width", "100%")
            .attr("height", "100%")
            .attr("viewBox", `0 0 ${chartWidth} ${chartHeight}`)
            .attr("preserveAspectRatio", "xMidYMid meet");

        sankeyGenerator = d3.sankey()
            .nodeId(d => d.id)
            .nodeWidth(20)
            .nodePadding(30)
            .iterations(64)
            .nodeSort(null)
            .extent([[10, 10], [chartWidth - 10, chartHeight - 25]]);
        
        // Mēģinām iestatīt krāsu skalu, ja pieejama
        if (d3.scaleOrdinal && d3.schemeTableau10) {
            d3color = d3.scaleOrdinal(d3.schemeTableau10);
        } else {
            d3color = () => "#4e79a7"; // Noklusējuma zila krāsa
        }
        
        return true;
    } catch (e) {
        console.error("Kļūda inicializējot SVG:", e);
        return false;
    }
}

// Funkcija teksta iezīmju (labels) sakārtošanai, lai tās nepārklātos
function adjustTextLabels() {
    const d3 = getD3();
    if (!svg || !d3) return;
    
    const nodeTextElements = svg.selectAll(".d3-sankey-node text").nodes();
    if (nodeTextElements.length === 0) return;

    const chartCenter = chartWidth / 2;
    const padding = -3;

    const leftLabels = nodeTextElements.filter(el => parseFloat(d3.select(el).attr('x')) < chartCenter);
    const rightLabels = nodeTextElements.filter(el => parseFloat(d3.select(el).attr('x')) > chartCenter);

    const processSide = (labels) => {
        if (labels.length < 2) return;
        // Kārtojam pēc Y pozīcijas
        labels.sort((a, b) => {
            try { return a.getBBox().y - b.getBBox().y; } catch(e) { return 0; }
        });
        
        for (let i = 1; i < labels.length; i++) {
            try {
                const prevBbox = labels[i - 1].getBBox();
                const currentBbox = labels[i].getBBox();
                const prevBottomY = prevBbox.y + prevBbox.height;
                
                if (currentBbox.y < prevBottomY + padding) {
                    const deltaY = (prevBottomY + padding) - currentBbox.y;
                    const labelSelection = d3.select(labels[i]);
                    const currentY = parseFloat(labelSelection.attr('y'));
                    labelSelection.attr('y', currentY + deltaY);
                }
            } catch (e) {
                // Ignorējam kļūdas aprēķinos (piem., ja elements nav redzams)
            }
        }
    };

    processSide(leftLabels);
    processSide(rightLabels);
}

export function drawD3Sankey(graphData, currencySymbol = 'EUR') {
    const d3 = getD3();
    const containerEl = document.getElementById(sankeyChartAreaId);
    
    // Ja D3 nav pieejams, parādam kļūdu panelī
    if (!d3) {
        if (containerEl) containerEl.innerHTML = '<p class="no-data" style="padding:20px;">D3.js bibliotēka nav ielādēta.</p>';
        return;
    }

    // Inicializācija
    if (!svg || containerEl.childElementCount === 0) {
        if (!initializeSVG()) {
             if (containerEl) containerEl.innerHTML = '<p class="no-data" style="padding:20px;">Kļūda inicializējot diagrammu.</p>';
             return;
        }
    }
    
    // Notīra veco zīmējumu
    svg.selectAll("*").remove();

    // Pārbauda datus
    if (!graphData || !graphData.nodes || graphData.nodes.length === 0 || !graphData.links || graphData.links.length === 0) {
        svg.append("text")
           .attr("x", chartWidth / 2)
           .attr("y", chartHeight / 2)
           .attr("text-anchor", "middle")
           .attr("font-family", "sans-serif")
           .attr("font-size", "14px")
           .attr("fill", "#6b7280") // Pelēka krāsa
           .text("Nav datu plūsmas diagrammai šim periodam.");
        return;
    }

    // Formatētājs (aizstāj d3.format)
    const localFormatCurrency = d => `${Math.round(d).toLocaleString('lv-LV')} ${currencySymbol}`;

    try {
        // Dziļā kopija, lai nemodificētu oriģinālos datus
        const graphCopy = JSON.parse(JSON.stringify(graphData));
        
        // Ģenerējam Sankey koordinātas
        const { nodes, links } = sankeyGenerator(graphCopy);

        // Zīmējam saites (links)
        const linkGroup = svg.append("g")
            .attr("fill", "none")
            .attr("stroke-opacity", 0.35)
            .selectAll("g")
            .data(links)
            .join("g")
            .attr("class", d => d.isLoss ? "d3-sankey-link is-loss" : "d3-sankey-link")
            .style("mix-blend-mode", "multiply");

        linkGroup.append("path")
            .attr("d", d3.sankeyLinkHorizontal())
            .attr("stroke", d => d.isLoss ? "#e53e3e" : (d3color ? d3color(d.source.id.toString()) : "#999"))
            .attr("stroke-width", d => Math.max(1.5, d.width))
            .append("title")
            .text(d => `${d.source.name} → ${d.target.name}\n${localFormatCurrency(d.originalValue)}`);

        // Zīmējam mezglus (nodes)
        const nodeGroup = svg.append("g")
            .selectAll("g")
            .data(nodes)
            .join("g")
            .attr("class", "d3-sankey-node");

        nodeGroup.append("rect")
            .attr("x", d => d.x0)
            .attr("y", d => d.y0)
            .attr("height", d => Math.max(1, d.y1 - d.y0))
            .attr("width", d => d.x1 - d.x0)
            .attr("fill", d => d3color ? d3color(d.id.toString()) : "#4e79a7")
            .attr("stroke", "#555")
            .attr("stroke-width", 0.5)
            .append("title")
            // d.value ir d3-sankey ABSOLŪTO saišu summa — zaudējumu saitēm (isLoss,
            // value=|x|) tā uzpūš skaitli. Rādām parakstīto summu kā vērtību etiķetē.
            .text(d => {
                const signed = d.targetLinks.length > 0
                    ? d3.sum(d.targetLinks, l => l.originalValue)
                    : d3.sum(d.sourceLinks, l => l.originalValue);
                return `${d.name}\nKopā: ${localFormatCurrency(signed)}`;
            });

        // Zīmējam tekstus
        const nodeText = nodeGroup.append("text")
            .attr("x", d => d.x0 < chartWidth / 2 ? d.x1 + 6 : d.x0 - 6)
            .attr("y", d => (d.y1 + d.y0) / 2)
            .attr("dy", "-0.2em")
            .attr("text-anchor", d => d.x0 < chartWidth / 2 ? "start" : "end")
            .attr("font-size", "11px")
            .attr("font-family", "sans-serif")
            .attr("fill", "#333");

        nodeText.append("tspan")
            .text(d => d.name);

        nodeText.append("tspan")
            .attr("x", d => d.x0 < chartWidth / 2 ? d.x1 + 6 : d.x0 - 6)
            .attr("dy", "1.2em")
            .attr("font-weight", "bold")
            .text(d => {
                // Rāda vērtību tikai, ja mezgls nav pārāk mazs vai ir pietiekami svarīgs
                if ((d.y1 - d.y0) > 10) {
                    // Avota mezgliem (bez ieejošām saitēm) NEDRĪKST lietot d.value:
                    // tas ir |saišu| summa, un pie negatīvas bruto peļņas saite
                    // net_turnover→gross nes |zaudējumus| → "Neto Apgrozījums"
                    // rādīja cogs+|bruto| = vairāk nekā dubultu apgrozījumu
                    // (statement 758946: 14 108 EUR īsto 6 800 vietā; skar
                    // 297 911 by_function pārskatus ar negatīvu bruto peļņu).
                    // Parakstītā izejošo saišu summa dod īsto vērtību.
                    const displayValue = d.targetLinks.length > 0
                        ? d3.sum(d.targetLinks, link => link.originalValue)
                        : d3.sum(d.sourceLinks, link => link.originalValue);
                    return localFormatCurrency(displayValue);
                }
                return "";
            });

        // Sakārtojam tekstu, lai nepārklājas
        adjustTextLabels();

        // Servera renderētais PZA kopsavilkums ir domāts lasītājiem bez JS;
        // tiklīdz diagramma ir uzzīmēta, to paslēpjam (skat. sankey_panel.php).
        const ssrSummary = document.getElementById('pza_summary_ssr');
        if (ssrSummary) ssrSummary.style.display = 'none';

    } catch (error) {
        console.error("D3 Sankey zīmēšanas kļūda:", error);
        if (containerEl) containerEl.innerHTML = `<p class="no-data" style="padding:20px; color:red;">Kļūda zīmējot: ${error.message}</p>`;
    }
}