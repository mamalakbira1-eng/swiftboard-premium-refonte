/**
 * SwiftBoard — Votes persistants (API REST)
 *
 * Remplace le JS mock par de vrais votes sauvegardés en base.
 * Anonymes autorisés (1 vote/min/IP).
 */
(function() {
    'use strict';

    // EXI-QUAL-06 : la configuration arrive par des attributs data-* sur
    // #sb-vote-config, et non plus par un <script> inline (wp_localize_script).
    // Un bloc inline aurait exige 'unsafe-inline' dans la CSP script-src ; le
    // nonce CSP est inutilisable ici car le theme sert un cache de pages
    // (le HTML fige et l'en-tete regeneree divergent).
    const CFG = (function () {
        const el = document.getElementById('sb-vote-config');
        if (!el) {
            // Repli sur l'ancienne variable globale : une extension tierce ou
            // un gabarit surcharge peut encore l'exposer.
            return (typeof swiftBoardVotes !== 'undefined') ? swiftBoardVotes : {};
        }
        return {
            restUrl:       el.getAttribute('data-rest-url') || '',
            nonce:         el.getAttribute('data-nonce') || '',
            userId:        el.getAttribute('data-user-id') || '0',
            loginRequired: el.getAttribute('data-login-required') || '',
            rateLimited:   el.getAttribute('data-rate-limited') || '',
            dailyLimit:    el.getAttribute('data-daily-limit') || '',
            error:         el.getAttribute('data-error') || ''
        };
    })();

    const restUrl = CFG.restUrl || '/wp-json/swiftboard/v1/';
    const nonce = CFG.nonce || '';

    function initVoteButtons() {
        // EXI-BLOQ-01d : TROIS conventions de markup coexistent.
        //   Reddit sujet   -> .sb-vote-btn          (conteneur .sb-post-votes)
        //   legacy         -> .vote-btn             (conteneur .vote-column)
        //   Reddit reponse -> .sb-comment-vote-btn  (conteneur .sb-comment-votes)
        // La troisieme manquait : les boutons de vote des REPONSES etaient
        // rendus mais aucun ecouteur n'y etait attache. Cliquer n'emettait
        // aucune requete — vote totalement inerte sur tout le fil.
        document.querySelectorAll('.sb-vote-btn, .vote-btn, .bbp-reply-action.vote-btn, .sb-comment-vote-btn').forEach(function(btn) {
            // Skip if already initialized
            if (btn.dataset.voteInit === '1') {
                return;
            }
            btn.dataset.voteInit = '1';

            btn.addEventListener('click', function(e) {
                e.preventDefault();

                const container = btn.closest('.sb-post-votes, .vote-column, .topic-vote, .sb-comment-votes');
                if (!container) {
                    return;
                }

                // Sur le markup des reponses, data-post-id est porte par le
                // BOUTON, pas par le conteneur : sans ce repli, postId restait
                // nul et la fonction sortait avant d'emettre la requete.
                let postId = container.getAttribute('data-post-id') || btn.getAttribute('data-post-id');
                if (!postId) {
                    // Try to find post ID from the article
                    const article = btn.closest('article, .bbp-reply, .topic-item');
                    if (article) {
                        const idMatch = (article.id || '').match(/(\d+)$/);
                        if (idMatch) {
                            postId = idMatch[1];
                        }
                    }
                }
                if (!postId) {
                    return;
                }

                // EXI-BLOQ-01c : deux conventions de markup coexistent
                //   Reddit  -> .sb-vote-btn.up   / .sb-vote-btn.down
                //   legacy  -> .vote-btn.upvote  / .vote-btn.downvote
                // Ne tester que 'upvote' renvoyait 'down' sur tout le markup Reddit.
                const voteType = (btn.classList.contains('upvote') || btn.classList.contains('up'))
                    ? 'up'
                    : 'down';

                // Optimistic UI
                const countEl = container.querySelector('.sb-vote-count, .vote-count, .sb-comment-vote-count');
                const upBtn = container.querySelector('.sb-vote-btn.up, .vote-btn.upvote, .bbp-reply-action.upvote, .sb-comment-vote-btn.up');
                const downBtn = container.querySelector('.sb-vote-btn.down, .vote-btn.downvote, .bbp-reply-action.downvote, .sb-comment-vote-btn.down');
                // Send vote to API
                fetch(restUrl + 'vote', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce,
                    },
                    body: JSON.stringify({
                        post_id: parseInt(postId),
                        vote_type: voteType,
                    }),
                })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        if (data.code && data.code !== 'success') {
                        // Error
                            const msg = data.message || 'Erreur';
                            if (data.code === 'rate_limited') {
                                showToast('⏳ ' + msg, 'warning');
                            } else {
                                showToast('❌ ' + msg, 'error');
                            }
                            return;
                        }

                        // Success — update UI
                        if (countEl) {
                            // EXI-BLOQ-01b : utiliser 'formatted' (la valeur que le
                            // serveur affichera au rechargement), pas 'score' brut.
                            // swiftboard_format_count() ramene les negatifs a 0 et
                            // abrege les milliers (1.2k) : afficher data.score ici
                            // creait un ecart entre l'affichage immediat et le reload.
                            countEl.textContent = (typeof data.formatted !== 'undefined')
                                ? data.formatted
                                : data.score;
                            countEl.classList.remove('is-up', 'is-down');
                            if (data.score > 0) {
                                countEl.classList.add('is-up');
                            } else if (data.score < 0) {
                                countEl.classList.add('is-down');
                            }

                            // v5.3.1 : synchroniser le doublon de pilule de vote.
                            // Sur les cartes d'accueil, une pilule desktop
                            // (.sb-post-votes) et une pilule mobile inline
                            // (.sb-comment-votes) coexistent pour le MEME post :
                            // sans cela, voter sur l'une laissait l'autre a
                            // l'ancienne valeur jusqu'au rechargement.
                            const scope = btn.closest('article, .sb-post-card, .sb-home-card, .bbp-reply') || document;
                            const twins = scope.querySelectorAll('.sb-vote-btn[data-post-id="' + postId + '"], .sb-comment-vote-btn[data-post-id="' + postId + '"]');
                            twins.forEach(function(twin) {
                                const twinBox = twin.closest('.sb-post-votes, .sb-comment-votes, .vote-column, .topic-vote');
                                if (!twinBox || twinBox === container) {
                                    return;
                                }
                                const twinCount = twinBox.querySelector('.sb-vote-count, .vote-count, .sb-comment-vote-count');
                                if (twinCount) {
                                    twinCount.textContent = countEl.textContent;
                                    twinCount.classList.remove('is-up', 'is-down', 'up', 'down');
                                    if (countEl.classList.contains('is-up')) {
                                        twinCount.classList.add('up');
                                    } else if (countEl.classList.contains('is-down')) {
                                        twinCount.classList.add('down');
                                    }
                                }
                                const twinIsUp = twin.classList.contains('up') || twin.classList.contains('upvote');
                                const twinActive = (twinIsUp && data.my_vote === 'up') || (!twinIsUp && data.my_vote === 'down');
                                twin.classList.toggle('active', twinActive);
                                twin.classList.toggle('is-active', twinActive);
                                twin.setAttribute('aria-pressed', twinActive ? 'true' : 'false');
                            });
                        }

                        // Update active states + aria-pressed (accessibilité)
                        if (upBtn) {
                            upBtn.classList.remove('is-active');
                            upBtn.setAttribute('aria-pressed', 'false');
                        }
                        if (downBtn) {
                            downBtn.classList.remove('is-active');
                            downBtn.setAttribute('aria-pressed', 'false');
                        }

                        if (data.my_vote === 'up' && upBtn) {
                            upBtn.classList.add('is-active');
                            upBtn.setAttribute('aria-pressed', 'true');
                        } else if (data.my_vote === 'down' && downBtn) {
                            downBtn.classList.add('is-active');
                            downBtn.setAttribute('aria-pressed', 'true');
                        }

                        showToast(getVoteMessage(data), 'success');
                    })
                    .catch(function () {
                        showToast('❌ Erreur réseau', 'error');
                    });
            });
        });

        // Load initial vote states on page load
        loadVoteStates();
    }

    function loadVoteStates() {
        document.querySelectorAll('.vote-column[data-post-id], .topic-vote[data-post-id]').forEach(function(container) {
            const postId = container.getAttribute('data-post-id');
            if (!postId) {
                return;
            }

            fetch(restUrl + 'vote?post_id=' + postId, {
                headers: { 'X-WP-Nonce': nonce },
            })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.code) {
                        return;
                    } // Error

                    const countEl = container.querySelector('.sb-vote-count, .vote-count');
                    const upBtn = container.querySelector('.vote-btn.upvote');
                    const downBtn = container.querySelector('.vote-btn.downvote');

                    if (countEl) {
                        // Meme regle qu'au clic (ligne ~123) : c'est 'formatted'
                        // qui fait foi. Utiliser 'score' ici reintroduisait une
                        // incoherence — le serveur rend « 0 » (regle anti-troll
                        // v4.6.1) et le JS d'initialisation reecrivait « -1 ».
                        // Signale par un audit externe, verifie au navigateur.
                        countEl.textContent = (typeof data.formatted !== 'undefined')
                            ? data.formatted
                            : data.score;
                        countEl.classList.remove('is-up', 'is-down');
                        if (data.score > 0) {
                            countEl.classList.add('is-up');
                        } else if (data.score < 0) {
                            countEl.classList.add('is-down');
                        }
                    }

                    // L'etat ACCESSIBLE doit suivre l'etat VISUEL.
                    //
                    // Seule la classe `is-active` etait posee ici : apres un
                    // rechargement, un lecteur d'ecran annoncait « non presse »
                    // sur un bouton actif. L'utilisateur ne savait pas qu'il
                    // avait deja vote et pouvait l'annuler sans le vouloir.
                    //
                    // Le rendu serveur pose desormais la bonne valeur des le
                    // premier octet (swiftboard_aria_pressed) ; on la maintient
                    // ici pour les cartes rendues dynamiquement.
                    if (upBtn) {
                        upBtn.setAttribute('aria-pressed', data.my_vote === 'up' ? 'true' : 'false');
                    }
                    if (downBtn) {
                        downBtn.setAttribute('aria-pressed', data.my_vote === 'down' ? 'true' : 'false');
                    }

                    if (data.my_vote === 'up' && upBtn) {
                        upBtn.classList.add('is-active');
                    } else if (data.my_vote === 'down' && downBtn) {
                        downBtn.classList.add('is-active');
                    }
                })
                .catch(function() {});
        });
    }

    function getVoteMessage(data) {
        switch (data.action) {
        case 'inserted':
            return data.vote_type === 'up' ? '▲ Upvoté !' : '▼ Downvoté';
        case 'changed':
            return 'Vote changé → ' + (data.vote_type === 'up' ? '▲' : '▼');
        case 'removed':
            return 'Vote retiré';
        default:
            return 'OK';
        }
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'swiftboard-toast swiftboard-toast-' + (type || 'info');
        toast.textContent = message;
        toast.style.cssText = [
            'position:fixed',
            'bottom:20px',
            'right:20px',
            'background:' + (type === 'error' ? '#dc2626' : type === 'warning' ? '#d97706' : '#16a34a'),
            'color:#fff',
            'padding:10px 20px',
            'border-radius:8px',
            'font-weight:600',
            'font-size:0.875rem',
            'z-index:99999',
            'box-shadow:0 4px 12px rgba(0,0,0,0.2)',
            'transition:opacity 0.3s',
        ].join(';');
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 2500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVoteButtons);
    } else {
        initVoteButtons();
    }
})();

