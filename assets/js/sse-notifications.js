/**
 * SwiftBoard — client SSE des notifications (Server-Sent Events).
 *
 * POURQUOI CE FICHIER EST EXTERNE
 * -------------------------------
 * Ce code etait imprime en <script> inline dans wp_footer. Le theme sert une
 * CSP `script-src 'self'` en ENFORCE : un bloc inline non hache est refuse par
 * le navigateur, silencieusement.
 *
 * Mesure avant correction, SSE active, dans Chromium :
 *     window.swiftboardSSEActive = undefined
 *     scripts inline bloques par la CSP : 2
 *     polling : 1 requete · flux SSE : 0
 *
 * Il n'y avait donc pas de « double fetch » : il n'y avait AUCUN flux SSE. Le
 * drapeau cense couper le polling et le client SSE tombaient ensemble, ce qui
 * masquait la panne — la fonctionnalite etait entierement inerte une fois
 * activee.
 *
 * Le drapeau passe desormais par wp_localize_script (attribut de donnees), et
 * ce fichier est servi par 'self', donc autorise sans empreinte a maintenir.
 *
 * @package SwiftBoard
 * @since 5.1.3
 */
(function() {
    'use strict';
    if (typeof EventSource === 'undefined') {
        return;
    } // navigateur non supporté
    const bell = document.getElementById('sb-notif-bell');
    if (!bell) {
        return;
    }

    let badge = bell.querySelector('.sb-notif-badge');
    const dropdown = document.getElementById('sb-notif-dropdown');
    const list = document.getElementById('sb-notif-list');
    let lastSeenId = 0;
    let sse = null;
    let reconnectDelay = 1000;
    const maxReconnectDelay = 30000;
    let reconnectTimer = null;
    let connectionAttempts = 0;
    const maxAttempts = 5;

    function connectSSE() {
        // Pause si onglet non visible (économie ressources serveur)
        if (document.hidden) {
            scheduleReconnect(5000);
            return;
        }

        try {
            sse = new EventSource('/wp-json/swiftboard/v1/notifications/stream?last_seen_id=' + lastSeenId, {
                withCredentials: true
            });
        } catch(e) {
            console.warn('SSE init failed', e);
            scheduleReconnect(reconnectDelay);
            return;
        }

        sse.addEventListener('ready', function() {
            reconnectDelay = 1000; // reset backoff
            connectionAttempts = 0;
        });

        sse.addEventListener('notification', function(e) {
            try {
                const n = JSON.parse(e.data);
                if (n.id && n.id > lastSeenId) {
                    lastSeenId = n.id;
                }
                // Update badge
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'sb-notif-badge';
                    bell.querySelector('.sb-notif-btn').appendChild(badge);
                }
                let currentCount = parseInt(badge.getAttribute('data-count'), 10) || 0;
                currentCount++;
                badge.setAttribute('data-count', currentCount);
                badge.textContent = currentCount > 99 ? '99+' : currentCount;

                // Prépend dans le dropdown si ouvert
                if (dropdown && dropdown.style.display === 'block' && list) {
                    const item = document.createElement('div');
                    item.className = 'sb-notif-item is-unread';
                    // Construction DOM (textContent) — aligné sur main.js loadNotifications.
                    // INTERDIT: innerHTML avec title/actor_name (DOM XSS).
                    const iconEl = document.createElement('span');
                    iconEl.className = 'sb-notif-item-icon';
                    iconEl.textContent = n.icon || '🔔';

                    const bodyEl = document.createElement('div');
                    bodyEl.className = 'sb-notif-item-body';

                    const titleEl = document.createElement('div');
                    titleEl.className = 'sb-notif-item-title';
                    titleEl.textContent = n.title || '';

                    const metaEl = document.createElement('div');
                    metaEl.className = 'sb-notif-item-meta';

                    const actorEl = document.createElement('span');
                    actorEl.textContent = n.actor_name || '';

                    metaEl.appendChild(actorEl);
                    bodyEl.appendChild(titleEl);
                    bodyEl.appendChild(metaEl);
                    item.appendChild(iconEl);
                    item.appendChild(bodyEl);
                    // Retirer empty state
                    const empty = list.querySelector('.sb-notif-empty, .sb-notif-loading');
                    if (empty) {
                        empty.remove();
                    }
                    list.insertBefore(item, list.firstChild);
                }

                // Notification native du navigateur si permission accordée
                if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
                    new Notification(n.title || 'Notification', {
                        body: n.excerpt || '',
                        icon: '',
                        tag: 'sb-notif-' + n.id
                    });
                }
            } catch(err) {
                console.warn('SSE parse error', err);
            }
        });

        sse.addEventListener('unread', function(e) {
            try {
                const data = JSON.parse(e.data);
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
            } catch(err) {}
        });

        sse.addEventListener('reconnect', function(e) {
            try {
                const data = JSON.parse(e.data);
                if (data.last_id) {
                    lastSeenId = data.last_id;
                }
            } catch(err) {}
            sse.close();
            scheduleReconnect(100); // reconnect immédiat
        });

        sse.onerror = function() {
            connectionAttempts++;
            sse.close();
            if (connectionAttempts >= maxAttempts) {
                console.warn('SSE failed ' + maxAttempts + ' times, falling back to polling');
                fallbackToPolling();
            } else {
                scheduleReconnect(reconnectDelay);
                reconnectDelay = Math.min(reconnectDelay * 2, maxReconnectDelay);
            }
        };
    }

    function scheduleReconnect(delay) {
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
        }
        reconnectTimer = setTimeout(connectSSE, delay);
    }

    // Fallback : polling 120s (comportement v4.2)
    function fallbackToPolling() {
        if (sse) {
            sse.close(); sse = null;
        }
        setInterval(function() {
            if (document.hidden) {
                return;
            }
            // Route « unread-count », pas « unread » : la seconde n'existe pas
            // et renvoyait 404. Le defaut etait invisible tant que ce script
            // etait bloque par la CSP — l'externaliser l'a mis au jour.
            fetch('/wp-json/swiftboard/v1/notifications/unread-count', { credentials: 'same-origin' })
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
                .catch(function() {});
        }, 120000);
    }

    // Demander permission pour notifications natives
    if ('Notification' in window && Notification.permission === 'default') {
        // Ne demande qu'au premier clic user (UX)
        document.addEventListener('click', function once() {
            Notification.requestPermission();
            document.removeEventListener('click', once);
        });
    }

    // Pause SSE quand onglet non visible, reconnect au retour
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (sse) {
                sse.close(); sse = null;
            }
        } else {
            if (!sse) {
                connectSSE();
            }
        }
    });

    // Démarrer SSE
    connectSSE();
})();

