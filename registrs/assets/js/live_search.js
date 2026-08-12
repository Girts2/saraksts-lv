// projects/python_generator/templates/assets/js/live_search.js

export function initLiveSearch() {
    const searchInput = document.getElementById('searchInput');
    const resultsDropdown = document.getElementById('resultsDropdown');
    if (!searchInput || !resultsDropdown) {
        console.error("Meklēšanas lauks vai rezultātu bloks nav atrasts.");
        return;
    }

    let searchTimeout;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str == null ? '' : String(str)));
        return div.innerHTML;
    }

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        clearTimeout(searchTimeout);

        if (searchTerm.length >= 1) {
            searchTimeout = setTimeout(() => {
                const searchUrl = `/registrs/assets/search/search.php?term=${encodeURIComponent(searchTerm)}`;
                fetch(searchUrl)
                    .then(response => {
                        if (!response.ok) throw new Error('Tīkla atbildes kļūda');
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            console.error('Servera kļūda:', data.error);
                            displayError('Servera kļūda ielādējot datus.');
                        } else {
                            displayResults(data);
                        }
                    })
                    .catch(error => {
                        console.error('Meklēšanas kļūda:', error);
                        displayError('Kļūda ielādējot datus.');
                    });
            }, 250);
        } else {
            resultsDropdown.innerHTML = '';
            resultsDropdown.style.display = 'none';
        }
    });

    function displayError(message) {
        resultsDropdown.innerHTML = `<div class="error-message">${message}</div>`;
        resultsDropdown.style.display = 'block';
    }

    function displayResults(data) {
        resultsDropdown.innerHTML = '';
        if (data.length === 0 && searchInput.value.trim().length > 0) {
            resultsDropdown.innerHTML = '<div>Nav rezultātu.</div>';
        } else if (data.length > 0) {
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'suggestion-item'; 
                div.innerHTML = `<span class="name">${escapeHtml(item.original_name)}</span><span class="regcode">${escapeHtml(item.regcode)}</span>`;

                div.addEventListener('click', function() {
                    // ABSOLŪTS ceļš: relatīvais 'NNN' no lapas /40003032949/ (htaccess
                    // pieņem arī slīpsvītras formu) aizvestu uz /40003032949/NNN → 404.
                    window.location.href = '/' + item.regcode;
                });
                resultsDropdown.appendChild(div);
            });
        }
        resultsDropdown.style.display = (data.length > 0 || (searchInput.value.trim().length > 0 && data.length === 0)) ? 'block' : 'none';
    }

    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !resultsDropdown.contains(event.target)) {
            resultsDropdown.style.display = 'none';
        }
    });
}