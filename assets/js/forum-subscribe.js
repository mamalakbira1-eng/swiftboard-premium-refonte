/* SwiftBoard — abonnement au forum depuis le hero de communaute.
 *
 * HISTORIQUE DU DEFAUT (regle R10)
 * Ce fichier se contentait de basculer une classe et de changer le libelle :
 *     var subscribed = btn.classList.toggle('subscribed');
 *     btn.textContent = subscribed ? '✓ Abonné' : '+ S\'abonner';
 * Aucune requete n'etait emise, le data-forum-id etait lu puis jete
 * (void forumId). L'utilisateur croyait s'etre abonne ; au rechargement,
 * l'etat avait disparu.
 *
 * CORRECTION (sans suppression)
 * Le bouton delegue desormais au lien d'abonnement natif bbPress rendu par
 * inc/join-button.php, qui porte le nonce et l'URL corrects. Deux cas :
 *   - membre connecte : on declenche le lien bbPress reel, l'etat est persiste ;
 *   - visiteur anonyme : aucune bascule trompeuse, on invite a se connecter.
 *
 * On ne supprime pas ce fichier : le hero de communaute possede son propre
 * emplacement et son propre style, et inc/join-button.php ne rend rien pour
 * un visiteur anonyme. Le retirer laisserait un trou dans l'interface.
 */
(function () {
    'use strict';

    /**
     * Retrouve le lien d'abonnement bbPress associe au forum.
     * inc/join-button.php le rend dans .sb-r-join-wrap.
     */
    function lienNatif(forumId) {
        var candidats = document.querySelectorAll(
            '.sb-r-join-wrap a, .bbp-forum-subscription-link, a.subscription-toggle'
        );
        for (var i = 0; i < candidats.length; i++) {
            var href = candidats[i].getAttribute('href') || '';
            if (!forumId || href.indexOf(forumId) !== -1) {
                return candidats[i];
            }
        }
        return candidats.length ? candidats[0] : null;
    }

    function estConnecte() {
        return document.body.classList.contains('logged-in');
    }

    /** Message non bloquant, lu par les lecteurs d'ecran. */
    function annoncer(btn, texte) {
        var zone = document.getElementById('sb-subscribe-live');
        if (!zone) {
            zone = document.createElement('span');
            zone.id = 'sb-subscribe-live';
            zone.setAttribute('role', 'status');
            zone.setAttribute('aria-live', 'polite');
            zone.style.cssText =
                'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;';
            document.body.appendChild(zone);
        }
        zone.textContent = '';
        window.setTimeout(function () {
            zone.textContent = texte;
        }, 60);
        var ancien = btn.getAttribute('title') || '';
        btn.setAttribute('title', texte);
        window.setTimeout(function () {
            btn.setAttribute('title', ancien);
        }, 2600);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.sb-forum-hero-subscribe');
        if (!btn) {
            return;
        }
        e.preventDefault();

        var forumId = btn.getAttribute('data-forum-id') || '';

        // Visiteur anonyme : aucune bascule visuelle. Un bouton qui change
        // d'apparence sans rien enregistrer est le defaut R10 d'origine.
        if (!estConnecte()) {
            var connexion = btn.getAttribute('data-login-url');
            annoncer(btn, 'Connectez-vous pour rejoindre cette communaute.');
            if (connexion) {
                window.location.href = connexion;
            }
            return;
        }

        var lien = lienNatif(forumId);

        // Sans lien natif (abonnements bbPress desactives), on n'invente pas
        // un etat local : on le dit.
        if (!lien) {
            btn.setAttribute('aria-disabled', 'true');
            annoncer(btn, 'Les abonnements ne sont pas actives sur ce forum.');
            return;
        }

        btn.setAttribute('aria-busy', 'true');
        annoncer(btn, 'Enregistrement en cours...');

        // Le lien bbPress porte le nonce et l'URL de retour : on le laisse
        // faire le travail plutot que de reconstruire la requete a la main.
        lien.click();
    });
})();
