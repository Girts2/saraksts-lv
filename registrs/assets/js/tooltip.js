// projects/python_generator/templates/assets/js/tooltip.js

export function initializeTooltips() {
    const tooltip = document.getElementById('custom-tooltip');
    if (!tooltip) {
        console.error("Tooltip elements 'custom-tooltip' nav atrasts.");
        return;
    }

    // Atrod visus tabulu galvenes elementus, kuriem ir paskaidrojuma atribūts
    const headersWithTooltip = document.querySelectorAll('th[data-original-name]');

    headersWithTooltip.forEach(header => {
        header.addEventListener('mouseenter', handleMouseEnter);
        header.addEventListener('mouseleave', handleMouseLeave);
        header.addEventListener('mousemove', handleMouseMove);
    });

    // Paslēpj tooltip, ja pele pamet logu
    document.body.addEventListener('mouseleave', handleMouseLeave);

    function handleMouseEnter(event) {
        const header = event.target.closest('th'); // Nodrošina, ka mērķis ir th
        if (!header) return;
        
        const originalName = header.getAttribute('data-original-name');
        const fullTranslation = header.getAttribute('data-full-translation');

        tooltip.innerHTML = ''; // Notīra iepriekšējo saturu

        if (originalName) {
            const originalNameSpan = document.createElement('strong');
            originalNameSpan.textContent = originalName;
            tooltip.appendChild(originalNameSpan);

            if (fullTranslation) {
                tooltip.appendChild(document.createElement('br'));
                tooltip.appendChild(document.createTextNode(fullTranslation));
            }
            tooltip.style.display = 'block';
        }
    }

    function handleMouseLeave() {
        tooltip.style.display = 'none';
    }

    function handleMouseMove(event) {
        if (tooltip.style.display === 'none') return;

        // Loģika, lai tooltip nepazustu aiz ekrāna malām
        const margin = 15; // Atstarpe no loga malām
        let tooltipX = event.clientX + margin;
        let tooltipY = event.clientY + margin;
        
        // Pielāgo pozīciju, ja tooltip iet ārpus labās vai apakšējās malas
        if (tooltipX + tooltip.offsetWidth > window.innerWidth - margin) {
            tooltipX = event.clientX - tooltip.offsetWidth - margin;
        }
        if (tooltipY + tooltip.offsetHeight > window.innerHeight - margin) {
            tooltipY = event.clientY - tooltip.offsetHeight - margin;
        }

        tooltip.style.left = tooltipX + 'px';
        tooltip.style.top = tooltipY + 'px';
    }
}