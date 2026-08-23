/**
 * Suggestions de recherche (autocomplete).
 *
 * EXI-QUAL-06 : ce code etait injecte en <script> inline dans wp_footer, ce
 * qui imposait 'unsafe-inline' dans la CSP. Externalise ici pour permettre le
 * passage de Content-Security-Policy de Report-Only a enforce.
 *
 * L'endpoint et les libelles arrivent par wp_localize_script (swiftBoardSearch).
 */
(function () {
    'use strict';

    // Configuration par attributs data-* (voir inc/votes-social.php pour le
    // detail : un <script> inline serait bloque par la CSP en enforce).
    const el = document.getElementById('sb-search-config');
    const endpoint = (el && el.getAttribute('data-endpoint'))
		|| '/wp-json/swiftboard/v1/search/suggest';
    const accueil = (el && el.getAttribute('data-home')) || '/';

    const searchInput = document.querySelector(
        '.header-search .search-field, .search-form .search-field'
    );
    if (!searchInput) {
        return;
    }

    let suggestBox = null;
    let debounceTimer = null;
    let currentRequest = null;

    function createSuggestBox() {
        if (suggestBox) {
            return suggestBox;
        }
        suggestBox = document.createElement('div');
        suggestBox.className = 'sb-search-suggest';
        // Le style vit desormais dans la CSS du theme : un style="" inline sur
        // un element est autorise par style-src 'unsafe-inline', mais autant
        // ne pas en dependre.
        searchInput.parentElement.style.position = 'relative';
        searchInput.parentElement.appendChild(suggestBox);
        return suggestBox;
    }

    function showSuggestions(items) {
        const box = createSuggestBox();
        if (!items || !items.length) {
            box.style.display = 'none';
            return;
        }
        // Construction par le DOM : textContent echappe nativement.
        box.textContent = '';
        items.forEach(function (item) {
            const a = document.createElement('a');
            a.className = 'sb-suggest-item';
            if (item.url) {
                a.href = item.url; // lien direct (subreddit ou sujet)
            } else {
                a.href = accueil + '?s=' + encodeURIComponent(item.title);
            }
            if (item.type === 'subreddit') {
                // Badge r/ pour les subreddits (façon Reddit)
                const badge = document.createElement('span');
                badge.className = 'sb-suggest-badge';
                badge.textContent = 'r/';
                a.appendChild(badge);
                const span = document.createElement('span');
                span.className = 'sb-suggest-title';
                span.textContent = item.title;
                a.appendChild(span);
            } else {
                a.textContent = item.title;
            }
            box.appendChild(a);
        });
        box.style.display = 'block';
    }

    searchInput.addEventListener('input', function () {
        const q = searchInput.value.trim();
        if (q.length < 2) {
            if (suggestBox) {
                suggestBox.style.display = 'none';
            }
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            if (currentRequest) {
                currentRequest.abort();
            }
            currentRequest = new XMLHttpRequest();
            currentRequest.open('GET', endpoint + '?s=' + encodeURIComponent(q), true);
            currentRequest.onreadystatechange = function () {
                if (currentRequest.readyState !== 4) {
                    return;
                }
                if (currentRequest.status !== 200) {
                    return;
                }
                try {
                    const data = JSON.parse(currentRequest.responseText);
                    showSuggestions(data.suggestions || []);
                } catch (e) {}
            };
            currentRequest.send();
        }, 200);
    });

    document.addEventListener('click', function (e) {
        if (suggestBox && !searchInput.parentElement.contains(e.target)) {
            suggestBox.style.display = 'none';
        }
    });

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && suggestBox) {
            suggestBox.style.display = 'none';
        }
    });
})();

