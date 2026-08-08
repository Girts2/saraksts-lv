// projects/python_generator/templates/assets/js/autocomplete.js

export function init(config_param) {
    // Pārbaude, vai konfigurācija ir nodota pareizi
    if (!config_param || typeof config_param.baseActionUrl === 'undefined') {
        console.error("Kļūda: Autocomplete konfigurācija (config_param) vai tās baseActionUrl nav definēts!");
        return;
    }

    const searchTermInput = document.getElementById('search_term');
    const suggestionsList = document.getElementById('autocomplete-suggestions');

    if (!searchTermInput || !suggestionsList) {
        console.error("Autocomplete: nevar atrast 'search_term' ievadlaukumu vai 'autocomplete-suggestions' sarakstu.");
        return;
    }

    const minChars = config_param.minAutocompleteChars;
    const maxChars = config_param.maxSearchTermLength;
    const debounceDelay = 250;
    let debounceTimer;
    let fetchController = null;
    let currentActiveIndex = -1;

    function escapeHTML(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function showSuggestions() {
        if (suggestionsList.children.length > 0) {
            suggestionsList.style.display = 'block';
        }
    }

    function hideSuggestions() {
        suggestionsList.style.display = 'none';
        currentActiveIndex = -1;
        if (suggestionsList) {
            suggestionsList.querySelectorAll('li.active').forEach(item => item.classList.remove('active'));
        }
    }

    // PIEZĪME: Statiskajā versijā šī `fetch` daļa nedarbosies, jo nav servera,
    // kas atbildētu uz pieprasījumu. Tas ir atstāts kā piemērs, ja sistēma
    // nākotnē tiktu mainīta uz dinamisku vai ja tiktu ieviesta lokāla meklēšana no JSON faila.
    searchTermInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        suggestionsList.innerHTML = '';
        hideSuggestions();

        if (query.length >= minChars && query.length <= maxChars && !/^\d{11}$/.test(query)) {
            debounceTimer = setTimeout(() => {
                if (fetchController) {
                    fetchController.abort();
                }
                fetchController = new AbortController();
                const signal = fetchController.signal;
                const formData = new FormData();
                formData.append('suggest_name', query);

                // Šis pieprasījums statiskā lapā izgāzīsies.
                fetch('', { method: 'POST', body: formData, signal: signal })
                    .then(response => response.json())
                    .then(data => {
                        suggestionsList.innerHTML = '';
                        if (data && Array.isArray(data) && data.length > 0) {
                            data.forEach(suggestion => {
                                const li = document.createElement('li');
                                // ... (loģika ieteikumu attēlošanai)
                                li.addEventListener('click', function() {
                                    const selectedRegcode = this.dataset.regcode;
                                    if (selectedRegcode) {
                                        // Statiskajā versijā URL tiek veidots kā "REGCODE.html"
                                        const newUrl = config_param.baseActionUrl + encodeURIComponent(selectedRegcode) + '.html';
                                        window.location.href = newUrl;
                                    }
                                });
                                suggestionsList.appendChild(li);
                            });
                            showSuggestions();
                        }
                    })
                    .catch(error => {
                        if (error.name !== 'AbortError') {
                            console.error('Kļūda, ielādējot ieteikumus:', error);
                        }
                    });
            }, debounceDelay);
        }
    });

    document.addEventListener('click', function(event) {
        setTimeout(() => {
            if (suggestionsList && searchTermInput) {
                if (!suggestionsList.contains(event.target) && event.target !== searchTermInput) {
                    hideSuggestions();
                }
            }
        }, 100);
    });

    searchTermInput.addEventListener('keydown', function(e) {
        const items = suggestionsList.querySelectorAll('li');
        if (suggestionsList.style.display !== 'block' || items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentActiveIndex = (currentActiveIndex + 1) % items.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentActiveIndex = (currentActiveIndex - 1 + items.length) % items.length;
        } else if (e.key === 'Enter') {
            if (currentActiveIndex > -1) {
                e.preventDefault();
                items[currentActiveIndex].click();
            }
        } else if (e.key === 'Escape') {
            hideSuggestions();
            e.preventDefault();
        }
        updateActiveSuggestion(items);
    });

    function updateActiveSuggestion(items) {
        items.forEach((item, index) => {
            if (index === currentActiveIndex) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }
}