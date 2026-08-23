/* SwiftBoard — repli et depli des fils de commentaires.
 *
 * HISTORIQUE DU DEFAUT (regle R10)
 * Le repli n'etait qu'une bascule de classe : l'etat disparaissait des que
 * l'utilisateur changeait de page ou rechargeait. Sur un sujet long, il
 * devait replier les memes fils a chaque visite.
 *
 * CORRECTION (sans suppression)
 * L'etat est desormais conserve dans localStorage et restaure au chargement.
 * Il s'agit d'une preference d'affichage strictement locale : elle n'a pas a
 * remonter au serveur, un appel REST serait une requete inutile a chaque clic.
 *
 * La liste est bornee a 200 entrees, purgees en premier entre par premier
 * sorti : sans plafond, le stockage grossirait indefiniment sur un forum actif.
 */
(function () {
    'use strict';

    var CLE = 'swiftboard_fils_replies';
    var MAX = 200;

    function lire() {
        try {
            var brut = window.localStorage.getItem(CLE);
            var liste = brut ? JSON.parse(brut) : [];
            return Array.isArray(liste) ? liste : [];
        } catch (e) {
            // Mode navigation privee ou stockage sature : on degrade en
            // repli non persistant plutot que de casser l'interaction.
            return [];
        }
    }

    function ecrire(liste) {
        try {
            if (liste.length > MAX) {
                liste = liste.slice(liste.length - MAX);
            }
            window.localStorage.setItem(CLE, JSON.stringify(liste));
        } catch (e) {
            /* stockage indisponible : le repli reste actif pour la session */
        }
    }

    /** Identifiant stable du fil, pour le retrouver au rechargement. */
    function identifiant(conteneur) {
        if (!conteneur) {
            return '';
        }
        var id = conteneur.getAttribute('data-comment-id')
            || conteneur.getAttribute('data-reply-id')
            || conteneur.id;
        return id ? String(id) : '';
    }

    function restaurer() {
        var replies = lire();
        if (!replies.length) {
            return;
        }
        replies.forEach(function (id) {
            var el = document.querySelector(
                '[data-comment-id="' + id + '"], [data-reply-id="' + id + '"], #' + CSS.escape(id)
            );
            if (el) {
                el.classList.add('collapsed');
                var bar = el.querySelector('.sb-comment-collapse-bar');
                if (bar) {
                    bar.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }

    document.addEventListener('click', function (e) {
        var bar = e.target.closest('.sb-comment-collapse-bar');
        if (!bar) {
            return;
        }
        e.stopPropagation();

        var conteneur = bar.parentElement;
        var replie = conteneur.classList.toggle('collapsed');
        bar.setAttribute('aria-expanded', replie ? 'false' : 'true');

        var id = identifiant(conteneur);
        if (!id) {
            return;
        }

        var liste = lire();
        var pos = liste.indexOf(id);
        if (replie && pos === -1) {
            liste.push(id);
        } else if (!replie && pos !== -1) {
            liste.splice(pos, 1);
        }
        ecrire(liste);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restaurer);
    } else {
        restaurer();
    }
})();
