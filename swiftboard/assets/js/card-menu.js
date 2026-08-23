/* SwiftBoard — actions du menu « ⋯ » des cartes : copier le lien, signaler.
   Le bouton « Sauvegarder » et les liens de moderation bbPress gardent leurs
   propres gestionnaires : on ne les intercepte pas. */
(function () {
    'use strict';

    function nonce() {
        var m = document.querySelector('meta[name="sb-rest-nonce"]');
        return m ? m.getAttribute('content') : '';
    }

    function feedback(btn, texte) {
        var label = btn.querySelector('.sb-more-label');
        if (!label) { return; }
        var initial = label.textContent;
        label.textContent = texte;
        setTimeout(function () { label.textContent = initial; }, 1800);
    }

    function fermerTous(sauf) {
        document.querySelectorAll('.sb-more-toggle[aria-expanded="true"]').forEach(function (t) {
            if (t !== sauf) { t.setAttribute('aria-expanded', 'false'); }
        });
    }

    // Ouverture/fermeture du menu « ⋯ ».
    // Aucun gestionnaire n'existait : le bouton etait rendu mais inerte, et
    // le menu restait invisible quel que soit le clic.
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.sb-more-toggle');
        if (toggle) {
            e.preventDefault();
            e.stopPropagation();
            var ouvert = toggle.getAttribute('aria-expanded') === 'true';
            fermerTous(toggle);
            toggle.setAttribute('aria-expanded', ouvert ? 'false' : 'true');
            return;
        }
        // Clic hors d'un menu ouvert : on referme.
        if (!e.target.closest('.sb-more-menu')) { fermerTous(null); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { fermerTous(null); }
    });

    document.addEventListener('click', function (e) {
        // --- Copier le lien -------------------------------------------------
        var copie = e.target.closest('.sb-action-copy');
        if (copie) {
            e.preventDefault();
            var url = copie.getAttribute('data-url') || window.location.href;

            var ok = function () { feedback(copie, 'Lien copié'); };
            var ko = function () { feedback(copie, 'Échec de la copie'); };

            // navigator.clipboard exige HTTPS ou localhost : sur un site en
            // HTTP simple il est absent, d'ou le repli execCommand.
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(ok).catch(ko);
            } else {
                var champ = document.createElement('textarea');
                champ.value = url;
                champ.setAttribute('readonly', '');
                champ.style.position = 'fixed';
                champ.style.opacity = '0';
                document.body.appendChild(champ);
                champ.select();
                try { document.execCommand('copy') ? ok() : ko(); } catch (err) { ko(); }
                document.body.removeChild(champ);
            }
            return;
        }

        // --- Signaler -------------------------------------------------------
        var report = e.target.closest('.sb-action-report');
        if (!report) { return; }
        e.preventDefault();

        if (report.dataset.sending === '1') { return; }

        var raison = window.prompt('Motif du signalement (facultatif) :', '');
        if (raison === null) { return; }

        report.dataset.sending = '1';
        feedback(report, 'Envoi…');

        fetch('/wp-json/swiftboard/v1/report', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce()
            },
            body: JSON.stringify({
                post_id: parseInt(report.getAttribute('data-post-id'), 10),
                reason: raison
            })
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function () { feedback(report, 'Signalé'); })
            .catch(function () { feedback(report, 'Échec'); })
            .finally(function () { report.dataset.sending = '0'; });
    });
})();
