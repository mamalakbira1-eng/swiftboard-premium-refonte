/* SwiftBoard Reddit card actions. Extracted from inc/reddit-layout.php. */
(function() {
    // Toggle view (card / compact) au chargement
    function applyView() {
        var m = document.cookie.match(/(?:^|;\s*)sb_view=([^;]+)/);
        var view = m ? m[1] : 'card';
        if (view === 'compact') document.body.classList.add('sb-compact-view');
    }
    applyView();

    // Gestionnaire de clic Card/Compact (source unique)
    document.querySelectorAll('.sb-view-toggle button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var view = btn.getAttribute('data-view');
            document.cookie = 'sb_view=' + view + ';path=/;max-age=' + (60*60*24*365);
            document.body.classList.toggle('sb-compact-view', view === 'compact');
            document.querySelectorAll('.sb-view-toggle button').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
        });
    });

    // Boutons d'action (share, save, hide, follow)
    // v5.3.4 — CORRECTIF BUG REEL : `.sb-action-follow` (bouton « Suivre » des
    // sujets) n'etait PAS dans le closest() : aucun ecouteur ne s'y abonnait,
    // le clic ne produisait aucune requete REST (bouton mort, prouve par
    // capture reseau E2E : 0 requete emise). toggleFollow existait deja dans
    // window.swiftBoardUserActions — il n'etait jamais appele.
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.sb-action-share, .sb-action-save, .sb-action-hide, .sb-action-more, .sb-action-follow');
        if (!btn) return;
        e.preventDefault();

        if (btn.classList.contains('sb-action-share')) {
            var url = btn.getAttribute('data-url');
            var oldTitle = btn.getAttribute('title') || '';
            var announce = function(message, success) {
                btn.classList.toggle('active', Boolean(success));
                btn.setAttribute('title', message);
                btn.setAttribute('aria-label', message);
                window.setTimeout(function() {
                    btn.classList.remove('active');
                    btn.setAttribute('title', oldTitle);
                    btn.setAttribute('aria-label', oldTitle);
                }, 1800);
            };
            var legacyCopy = function() {
                var input = document.createElement('input');
                input.value = url || window.location.href;
                input.setAttribute('readonly', 'readonly');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                var copied = false;
                try { copied = document.execCommand('copy'); } catch (error) { copied = false; }
                input.remove();
                announce(copied ? 'Lien copié !' : 'Copiez le lien : ' + input.value, copied);
                return copied;
            };
            if (navigator.share) {
                navigator.share({url: url}).then(function() {
                    announce('Partage terminé', true);
                }).catch(function(error) {
                    if (error && error.name === 'AbortError') return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function() {
                            announce('Lien copié !', true);
                        }).catch(legacyCopy);
                    } else {
                        legacyCopy();
                    }
                });
            } else if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    announce('Lien copié !', true);
                }).catch(legacyCopy);
            } else {
                legacyCopy();
            }
        } else if (btn.classList.contains('sb-action-save')) {
            // Délégué à user-content-actions.php si chargé
            if (window.swiftBoardUserActions && window.swiftBoardUserActions.toggleSave) {
                window.swiftBoardUserActions.toggleSave(btn);
            } else {
                btn.classList.toggle('active');
                btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');
            }
        } else if (btn.classList.contains('sb-action-follow')) {
            // v5.3.4 : meme logique que « Sauvegarder » — membre = REST reel
            // (toggleFollow), anonyme = bascule visuelle non persistee.
            if (window.swiftBoardUserActions && window.swiftBoardUserActions.toggleFollow) {
                window.swiftBoardUserActions.toggleFollow(btn);
            } else {
                btn.classList.toggle('active');
                btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');
            }
        } else if (btn.classList.contains('sb-action-hide')) {
            // RETOUR VISUEL ET REVERSIBILITE.
            //
            // La carte disparaissait sans aucune confirmation ni moyen
            // d'annuler : un clic accidentel etait irreversible du point de
            // vue de l'utilisateur, et un lecteur d'ecran n'annoncait rien.
            //
            // On masque desormais la carte, on annonce l'action dans la region
            // live de la zone d'actions, et on offre une annulation.
            var carte = btn.closest('.sb-post-card');
            var zone = btn.closest('.sb-post-actions');
            var live = zone ? zone.querySelector('.sb-action-status') : null;

            if (window.swiftBoardUserActions && window.swiftBoardUserActions.toggleHide) {
                window.swiftBoardUserActions.toggleHide(btn);
            }

            if (carte) {
                carte.hidden = true;
                btn.setAttribute('aria-pressed', 'true');

                if (live) {
                    // textContent, jamais innerHTML : le titre du sujet est
                    // fourni par l'utilisateur.
                    live.textContent = '';
                    var texte = document.createElement('span');
                    texte.textContent = 'Sujet masqué. ';
                    var annuler = document.createElement('button');
                    annuler.type = 'button';
                    annuler.className = 'sb-undo';
                    annuler.textContent = 'Annuler';
                    annuler.addEventListener('click', function() {
                        carte.hidden = false;
                        btn.setAttribute('aria-pressed', 'false');
                        live.textContent = '';
                        if (window.swiftBoardUserActions && window.swiftBoardUserActions.toggleHide) {
                            window.swiftBoardUserActions.toggleHide(btn);
                        }
                        btn.focus();   // le focus ne doit pas se perdre
                    });
                    live.appendChild(texte);
                    live.appendChild(annuler);
                    annuler.focus();
                }
            }
        } else if (btn.classList.contains('sb-action-more')) {
            // Menu plus d'options (signaler, bloquer) — module modération.
            // Réservé pour une future extension, aucun comportement par défaut.
        }
    });
})();
