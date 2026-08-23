/* SwiftBoard user content actions. Externalized from inc/user-content-actions.php. */
window.swiftBoardUserActions = (function() {
    var config = document.getElementById('sb-user-actions-config');
    var restUrl = config ? config.getAttribute('data-rest-url') : '';
    var nonce = config ? config.getAttribute('data-nonce') : '';

    function callApi(topicId, action, add) {
        return fetch(restUrl + 'user-action', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({
                topic_id: parseInt(topicId, 10),
                action: action,
                add: add
            })
        }).then(function(r) { return r.json(); });
    }

    // v5.3.3 — C.11 STRICT : plus AUCUNE injection de texte dans les boutons
    // (avant : btn.innerHTML = '✓ Sauvegardé' → l'icone SVG disparaissait).
    // Etat = classe .active + aria-pressed + title. L'icone ne bouge jamais.
    function applyState(btn, pressed, labelOn, labelOff) {
        btn.classList.toggle('active', pressed);
        btn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        btn.setAttribute('title', pressed ? labelOn : labelOff);
        btn.setAttribute('aria-label', pressed ? labelOn : labelOff);
    }

    function toggleSave(btn) {
        var topicId = btn.getAttribute('data-post-id');
        if (!topicId) return;
        var wasActive = btn.classList.contains('active');
        applyState(btn, !wasActive, 'Sauvegardé', 'Sauvegarder');
        callApi(topicId, 'saved', !wasActive).catch(function() {
            // Rollback
            applyState(btn, wasActive, 'Sauvegardé', 'Sauvegarder');
        });
    }

    function toggleHide(btn) {
        var topicId = btn.getAttribute('data-post-id');
        if (!topicId) return;
        if (!confirm('Cacher ce sujet ? Il ne apparaîtra plus dans votre feed.')) return;
        callApi(topicId, 'hidden', true).then(function() {
            var card = btn.closest('.sb-post-card, .post-card');
            if (card) {
                card.style.transition = 'opacity 0.3s';
                card.style.opacity = '0';
                setTimeout(function() { card.style.display = 'none'; }, 300);
            }
        });
    }

    function toggleFollow(btn) {
        var topicId = btn.getAttribute('data-post-id');
        if (!topicId) return;
        var wasActive = btn.classList.contains('active');
        applyState(btn, !wasActive, 'Suivi', 'Suivre');
        callApi(topicId, 'followed', !wasActive).catch(function() {
            applyState(btn, wasActive, 'Suivi', 'Suivre');
        });
    }

    // L’état initial est rendu côté serveur par swiftboard_actions_carte_html().
    // Aucun preload REST n’est nécessaire : cela évite une requête lourde et
    // tardive sur le feed, tout en conservant le POST protégé au clic.
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.sb-action-save[data-post-id]').forEach(function(btn) {
            var pressed = btn.getAttribute('aria-pressed') === 'true';
            applyState(btn, pressed, 'Sauvegardé', 'Sauvegarder');
        });
    });

    return { toggleSave: toggleSave, toggleHide: toggleHide, toggleFollow: toggleFollow };
})();
