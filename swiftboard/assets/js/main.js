/**
 * SwiftBoard v2 — JavaScript minimal
 *
 * Pas de jQuery. Juste le strict nécessaire :
 * - Menu toggle mobile
 * - Skip link focus
 * - Dark mode toggle (avec persistance localStorage)
 * - Vote buttons (visuel uniquement, à brancher sur l'API REST au besoin)
 * - Smooth scroll pour ancres internes
 * - aria-current auto
 *
 * @package SwiftBoard
 * @since 2.0.0
 */
(function() {
    'use strict';

    // v5.0 EXI-BLOQ-04 : configuration REST portée par un conteneur DOM inerte.
    const sbNotifConfig = document.getElementById('swiftboard-notifs-config');
    const sbRestBase = (sbNotifConfig && sbNotifConfig.dataset.restUrl)
        ? sbNotifConfig.dataset.restUrl
        : ((typeof swiftBoardVotes !== 'undefined' && swiftBoardVotes.restUrl)
            ? swiftBoardVotes.restUrl
            : '/wp-json/swiftboard/v1/');

    // Helper : récupère le nonce REST depuis le meta tag (v4.6 — CSP strict compatible)
    function getRestNonce() {
        const meta = document.querySelector('meta[name="sb-rest-nonce"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // =========================================================================
    // 1. DARK MODE TOGGLE
    // =========================================================================
    function initDarkMode() {
        const toggle = document.querySelector('.theme-toggle');
        const root = document.documentElement;
        let stored = null;
        try {
            stored = localStorage.getItem('swiftboard-theme');
        } catch(e) {}

        // Appliquer au chargement : toujours normaliser vers light ou dark.
        const validStored = stored === 'light' || stored === 'dark';
        const normalized = validStored ? stored : currentSystemTheme();
        root.setAttribute('data-theme', normalized);

        if (!toggle) {
            return;
        }

        // Mettre à jour l'icône
        updateToggleIcon(toggle, normalized);

        toggle.addEventListener('click', function() {
            const current = root.getAttribute('data-theme');
            const systemDark = currentSystemTheme() === 'dark';
            const isDark = current ? current === 'dark' : systemDark;
            const next = isDark ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try {
                localStorage.setItem('swiftboard-theme', next);
            } catch(e) {}
            updateToggleIcon(toggle, next);
        });
    }

    function currentSystemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark' : 'light';
    }

    // Traces SVG identiques a ceux de inc/icons.php : le serveur rend deja
    // l'icone, ce JS ne fait que la permuter au changement de theme.
    // Auparavant `textContent` y ecrivait un EMOJI, ce qui ecrasait le SVG
    // rendu cote serveur et affichait un carre vide partout ou la police
    // emoji est absente (serveurs, navigateurs headless, certains Linux).
    const ICONE_LUNE = '<svg class="sb-i sb-i-theme" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
    const ICONE_SOLEIL = '<svg class="sb-i sb-i-theme" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';

    function updateToggleIcon(toggle, theme) {
        // innerHTML sur une constante litterale definie ci-dessus : aucune
        // donnee externe n'entre ici, donc pas de vecteur d'injection.
        toggle.innerHTML = theme === 'dark' ? ICONE_SOLEIL : ICONE_LUNE;
        toggle.setAttribute('aria-label', theme === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre');
    }

    // =========================================================================
    // 1bis. VUE COMPACTE DU FIL (Lot 4)
    // =========================================================================
    function initCompactView() {
        const buttons = document.querySelectorAll('[data-compact-toggle]');
        if (!buttons.length) {
            return;
        }
        let active = false;
        try {
            active = localStorage.getItem('swiftboard-compact-view') === '1';
        } catch (e) {}

        function apply(value) {
            active = Boolean(value);
            document.body.classList.toggle('sb-compact-view', active);
            buttons.forEach(function (button) {
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                button.classList.toggle('is-active', active);
            });
        }

        apply(active);
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                apply(!active);
                try {
                    localStorage.setItem('swiftboard-compact-view', active ? '1' : '0');
                } catch (e) {}
            });
        });
    }

    // =========================================================================
    // 2. MENU MOBILE TOGGLE
    // =========================================================================
    function initMenuToggle() {
        const toggle = document.querySelector('.menu-toggle');
        const nav = document.querySelector('.main-navigation');
        if (!toggle || !nav) {
            return;
        }

        // Le bouton contrôle la navigation principale via aria-controls/expanded.
        // Il ne s’agit pas d’un popup menu ARIA.

        toggle.addEventListener('click', function() {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !expanded);
            nav.classList.toggle('is-open');
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!nav.contains(e.target) && !toggle.contains(e.target) && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });

        // Close menu on Escape (accessibilité clavier — audit 08)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus(); // retour focus au bouton pour navigation clavier
            }
        });
    }

    // =========================================================================
    // 3. SKIP LINK FOCUS
    // =========================================================================
    function initSkipLink() {
        const skipLink = document.querySelector('.skip-link');
        if (!skipLink) {
            return;
        }
        skipLink.addEventListener('click', function(e) {
            const href = skipLink.getAttribute('href');
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.setAttribute('tabindex', '-1');
                target.focus();
            }
        });
    }

    // =========================================================================
    // 4bis. PROFIL — "Tout marquer comme lu" (EXI-MBR-01)
    // =========================================================================
    function initProfileMarkAll() {
        const btn = document.querySelector('.sb-notif-markall-profile');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            btn.disabled = true;
            fetch(sbRestBase + 'notifications/read-all', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': getRestNonce() }
            })
                .then(function (r) {
                    return r.ok ? r.json() : Promise.reject(r.status);
                })
                .then(function () {
                    document.querySelectorAll('.sb-notif-item.is-unread')
                        .forEach(function (el) {
                            el.classList.remove('is-unread');
                        });
                    const badge = document.querySelector('.sb-tab-badge');
                    if (badge) {
                        badge.remove();
                    }
                    const head = document.querySelector('.sb-profile-notifs-head h3');
                    if (head) {
                        head.textContent = 'Notifications';
                    }
                    btn.remove();
                })
                .catch(function () {
                    btn.disabled = false;
                });
        });
    }

    // =========================================================================
    // 4ter. MENU UTILISATEUR (EXI-MBR-04) — clavier + ARIA
    // =========================================================================
    function initUserMenu() {
        const wrap = document.querySelector('.sb-user-menu');
        if (!wrap) {
            return;
        }
        const toggle = wrap.querySelector('.sb-user-menu-toggle');
        const list = wrap.querySelector('.sb-user-menu-list');
        if (!toggle || !list) {
            return;
        }
        const items = Array.prototype.slice.call(list.querySelectorAll('[role="menuitem"]'));

        function open() {
            wrap.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            if (items[0]) {
                items[0].focus();
            }
        }

        function close(refocus) {
            wrap.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            if (refocus) {
                toggle.focus();
            }
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (wrap.classList.contains('is-open')) {
                close(false);
            } else {
                open();
            }
        });

        // Escape ferme et rend le focus au declencheur
        wrap.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && wrap.classList.contains('is-open')) {
                e.preventDefault();
                close(true);
                return;
            }
            if (!wrap.classList.contains('is-open')) {
                return;
            }
            const idx = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                items[(idx + 1 + items.length) % items.length].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                items[(idx - 1 + items.length) % items.length].focus();
            }
        });

        // Clic exterieur
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target) && wrap.classList.contains('is-open')) {
                close(false);
            }
        });
    }

    // =========================================================================
    // 5. ARIA-CURRENT AUTO
    // =========================================================================
    function initAriaCurrent() {
        const currentUrl = window.location.pathname;
        const links = document.querySelectorAll('.main-navigation a, .footer-navigation a');
        links.forEach(function(link) {
            if (link.pathname === currentUrl && link.getAttribute('href') !== '#') {
                link.setAttribute('aria-current', 'page');
            }
        });
    }

    // =========================================================================
    // 6. NOTIFICATIONS POLLING (120s — pause quand onglet non visible)
    //    DÉSACTIVÉ si SSE est actif (window.swiftboardSSEActive = true)
    // =========================================================================
    function initNotificationsPolling() {
        // Si SSE est actif, on ne poll pas (le module SSE gère tout)
        const sseConfig = document.getElementById('swiftboard-sse-config');
        const sseActif = (sseConfig && sseConfig.getAttribute('data-active') === '1')
            || window.swiftboardSSEActive;
        // SSE désactive uniquement le polling périodique ; le dropdown et
        // le chargement à la demande restent nécessaires dans tous les modes.
        const bell = document.getElementById('sb-notif-bell');
        if (!bell) {
            return;
        } // user non connecté ou bell pas affiché

        let badge = bell.querySelector('.sb-notif-badge');
        const dropdown = document.getElementById('sb-notif-dropdown');
        const list = document.getElementById('sb-notif-list');
        const markAllBtn = document.getElementById('sb-notif-markall');
        let isOpen = false;
        let loadedOnce = false;
        let pollInterval = null;
        const POLL_DELAY = 120000; // 120s — plus raisonnable que 60s pour 10k+ users

        // Rate-limit polling : pause quand onglet non visible
        function startPolling() {
            stopPolling();
            pollInterval = setInterval(fetchUnreadCount, POLL_DELAY);
        }
        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
            } else {
                fetchUnreadCount(); // refresh immédiat au retour
                startPolling();
            }
        });

        // Fetch unread count
        function fetchUnreadCount() {
            // Bail si user non connecté (REST renverrait 401)
            if (!document.body.classList.contains('logged-in')) {
                return;
            }

            fetch(sbRestBase + 'notifications/unread-count', {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': getRestNonce() }
            })
                .then(function(r) {
                    return r.ok ? r.json() : null;
                })
                .then(function(data) {
                    if (!data) {
                        return;
                    }
                    const count = parseInt(data.count, 10) || 0;
                    if (count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'sb-notif-badge';
                            bell.querySelector('.sb-notif-btn').appendChild(badge);
                        }
                        badge.setAttribute('data-count', count);
                        badge.textContent = count > 99 ? '99+' : count;
                    } else if (badge) {
                        badge.remove();
                        badge = null;
                    }
                })
                .catch(function() { /* silencieux */ });
        }

        // Open dropdown + lazy load notifications
        bell.querySelector('.sb-notif-btn').addEventListener('click', function(e) {
            e.preventDefault();
            isOpen = !isOpen;
            dropdown.style.display = isOpen ? 'block' : 'none';
            const bellBtn = bell.querySelector('.sb-notif-btn');
            if (bellBtn) {
                bellBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
            if (isOpen && !loadedOnce) {
                loadNotifications();
                loadedOnce = true;
            }
        });

        // Escape ferme le panneau et rend le focus au declencheur (A11Y-03)
        bell.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen) {
                e.preventDefault();
                isOpen = false;
                dropdown.style.display = 'none';
                const escBtn = bell.querySelector('.sb-notif-btn');
                if (escBtn) {
                    escBtn.setAttribute('aria-expanded', 'false');
                    escBtn.focus();
                }
            }
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
            if (isOpen && !bell.contains(e.target)) {
                isOpen = false;
                dropdown.style.display = 'none';
                const outBtn = bell.querySelector('.sb-notif-btn');
                if (outBtn) {
                    outBtn.setAttribute('aria-expanded', 'false');
                }
                bell.setAttribute('aria-expanded', 'false');
            }
        });

        // Load notifications list via REST
        function loadNotifications() {
            if (!list) {
                return;
            }
            list.innerHTML = '<div class="sb-notif-loading">Chargement…</div>';
            fetch(sbRestBase + 'notifications?limit=20', {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': getRestNonce() }
            })
                .then(function(r) {
                    return r.ok ? r.json() : null;
                })
                .then(function(data) {
                    if (!data || !data.notifications || !data.notifications.length) {
                        list.innerHTML = '<div class="sb-notif-empty">Aucune notification</div>';
                        return;
                    }
                    // Construction par noeuds DOM, jamais par concatenation.
                    //
                    // Les champs title et actor_name viennent de la base sans
                    // re-echappement a la sortie : une ecriture directe (plugin
                    // tiers, import SQL) traversait la route REST intacte et
                    // atterrissait dans innerHTML. Mesure dans Chromium avant
                    // correction : une VRAIE balise <img> injectee dans le DOM,
                    // neutralisee par la seule CSP.
                    //
                    // textContent ne peut pas interpreter de markup : la donnee
                    // reste une donnee, quelle que soit son origine.
                    list.textContent = '';

                    data.notifications.forEach(function(n) {
                        const item = document.createElement('div');
                        item.className = 'sb-notif-item' + (n.is_read ? '' : ' is-unread');

                        const icone = document.createElement('span');
                        icone.className = 'sb-notif-item-icon';
                        icone.textContent = n.icon || '🔔';

                        const corps = document.createElement('div');
                        corps.className = 'sb-notif-item-body';

                        const titre = document.createElement('div');
                        titre.className = 'sb-notif-item-title';
                        titre.textContent = n.title || '';

                        const meta = document.createElement('div');
                        meta.className = 'sb-notif-item-meta';

                        const acteur = document.createElement('span');
                        acteur.textContent = n.actor_name || '';

                        const separateur = document.createTextNode(' · ');

                        const moment = document.createElement('span');
                        moment.textContent = n.time_ago || '';

                        meta.appendChild(acteur);
                        meta.appendChild(separateur);
                        meta.appendChild(moment);
                        corps.appendChild(titre);
                        corps.appendChild(meta);
                        item.appendChild(icone);
                        item.appendChild(corps);
                        list.appendChild(item);
                    });
                })
                .catch(function() {
                    list.innerHTML = '<div class="sb-notif-empty">Erreur de chargement</div>';
                });
        }

        // Mark all as read
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                fetch(sbRestBase + 'notifications/read-all', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': getRestNonce(),
                        'Content-Type': 'application/json'
                    }
                })
                    .then(function(r) {
                        return r.ok ? r.json() : null;
                    })
                    .then(function() {
                        if (badge) {
                            badge.remove();
                        }
                        badge = null;
                        const items = list.querySelectorAll('.sb-notif-item.is-unread');
                        items.forEach(function(it) {
                            it.classList.remove('is-unread');
                        });
                    });
            });
        }

        // Start : le flux SSE remplace seulement le polling périodique.
        if (!sseActif) {
            startPolling();
        }
    }

    // =========================================================================
    // 7. POST ACTIONS — SHARE / SAVE / REPORT (audit 06)
    // =========================================================================
    function initPostActions() {
        // Partager : navigator.share si disponible, sinon copy to clipboard
        document.addEventListener('click', function(e) {
            const shareBtn = e.target.closest('.sb-share-btn');
            if (shareBtn) {
                e.preventDefault();
                const url = shareBtn.getAttribute('data-share-url');
                const title = shareBtn.getAttribute('data-share-title') || document.title;
                if (navigator.share) {
                    navigator.share({ title: title, url: url }).catch(function() {});
                } else if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function() {
                        shareBtn.textContent = '✓ Copié !';
                        setTimeout(function() {
                            shareBtn.textContent = '🔗 Partager';
                        }, 2000);
                    });
                } else {
                    // Fallback sans prompt (EXI-QUAL-03 no-alert) : champ temporaire
                    const tmp = document.createElement('textarea');
                    tmp.value = url;
                    tmp.setAttribute('readonly', '');
                    tmp.style.cssText = 'position:absolute;left:-9999px';
                    document.body.appendChild(tmp);
                    tmp.select();
                    try {
                        document.execCommand('copy');
                    } catch (copyErr) { /* noop */ }
                    document.body.removeChild(tmp);
                    shareBtn.textContent = '✓ Copié !';
                    setTimeout(function () {
                        shareBtn.textContent = '🔗 Partager';
                    }, 2000);
                }
                return;
            }

            // Enregistrer : toggle via REST endpoint
            const saveBtn = e.target.closest('.sb-save-btn');
            if (saveBtn) {
                e.preventDefault();
                const postId = parseInt(saveBtn.getAttribute('data-post-id'), 10);
                if (!postId) {
                    return;
                }
                const isSaved = saveBtn.classList.contains('is-saved');
                saveBtn.disabled = true;
                fetch(sbRestBase + 'user-action', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': getRestNonce()
                    },
                    body: JSON.stringify({
                        action: isSaved ? 'unsave' : 'save',
                        post_id: postId
                    })
                }).then(function(r) {
                    return r.ok ? r.json() : null;
                })
                    .then(function(data) {
                        saveBtn.disabled = false;
                        if (data && data.success) {
                            saveBtn.classList.toggle('is-saved', !isSaved);
                            saveBtn.textContent = isSaved ? '⭐ Enregistrer' : '✓ Enregistré';
                        }
                    }).catch(function() {
                        saveBtn.disabled = false;
                    });
                return;
            }

            // Signaler : mailto avec pré-rempli
            const reportBtn = e.target.closest('.sb-report-btn');
            if (reportBtn) {
                e.preventDefault();
                const pid = reportBtn.getAttribute('data-post-id');
                const subject = encodeURIComponent('Signalement contenu #' + pid);
                const body = encodeURIComponent('Bonjour,\n\nJe signale le contenu suivant :\nURL : ' + window.location.href + '\nID : ' + pid + '\n\nRaison : ');
                window.location.href = 'mailto:' + (window.swiftboardReportEmail || 'moderation@example.com') + '?subject=' + subject + '&body=' + body;
                return;
            }
        });
    }

    // =========================================================================
    // DOM READY
    // =========================================================================
    /**
     * Actions des commentaires — ecoute DELEGUEE.
     *
     * EXI-QUAL-06 : ces trois interactions passaient par des attributs
     * onclick= dans le markup (nested-comments.php). Un gestionnaire inline
     * exige 'unsafe-inline' dans la CSP script-src, ce qui interdisait de
     * passer Content-Security-Policy de Report-Only a enforce.
     *
     * La delegation sur document couvre aussi les commentaires ajoutes
     * dynamiquement, ce que les onclick d'origine ne faisaient pas.
     */
    function initCommentActions() {
        function agir(cible) {
            const action = cible.getAttribute('data-sb-action');
            if (action === 'collapse') {
                // Le clic est géré par nested-comments.js, qui persiste aussi
                // l’identifiant du fil. Retourner false ici évite que les deux
                // écouteurs délégués se neutralisent en basculant deux fois.
                return false;
            }
            if (action === 'reply-open') {
                const bloc = cible.closest('.sb-comment');
                const form = bloc && bloc.querySelector('.sb-comment-reply-form');
                if (form) {
                    form.style.display = 'block';
                    const champ = form.querySelector('textarea');
                    if (champ) {
                        champ.focus();
                    }
                }
                return true;
            }
            if (action === 'reply-cancel') {
                const f = cible.closest('.sb-comment-reply-form');
                if (f) {
                    f.style.display = 'none';
                }
                return true;
            }
            return false;
        }

        document.addEventListener('click', function (e) {
            const cible = e.target.closest('[data-sb-action]');
            if (cible && agir(cible)) {
                e.preventDefault();
            }
        });

        // La barre de repli est un role="button" : elle doit repondre au
        // clavier, ce que l'ancien onclick sur un <div> ne permettait pas.
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
                        const cible = e.target.closest('[data-sb-action]');
            if (!cible) {
                return;
            }
            if (cible.getAttribute('data-sb-action') === 'collapse') {
                // Réutilise le gestionnaire click de nested-comments.js afin
                // que le clavier bénéficie de la même persistance localStorage.
                cible.click();
                e.preventDefault();
                return;
            }
            if (agir(cible)) {
                e.preventDefault();
            }
        });
    }
    function initDataConfirm() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-confirm]');
            if (!btn) return;
            if (!confirm(btn.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    }

    function init() {
        initDarkMode();
        initCompactView();
        initCommentActions();
        initMenuToggle();
        initSkipLink();
        initProfileMarkAll();
        initUserMenu();
        initAriaCurrent();
        initNotificationsPolling();
        initPostActions();
        initDataConfirm();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

