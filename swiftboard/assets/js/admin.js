/**
 * SwiftBoard Admin — modération des images (vanilla JS, sans jQuery) - V2 REST
 * V2 restauration E3: harmonisation modération - 1 seule implémentation REST, plus admin-post
 * Routes: POST /wp-json/swiftboard/v1/images/{id}/approve|reject
 */
(function () {
    'use strict';

    if (typeof swiftboardAdmin === 'undefined') {
        return;
    }

    function moderate(id, action, btn) {
        const endpoint = swiftboardAdmin.restUrl + 'images/' + id + '/' + action;
        btn.disabled = true;

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': swiftboardAdmin.nonce,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({id: id})
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                const row = btn.closest('tr') || btn.closest('.swiftboard-moderation-card');
                if (data && (data.success || data.id)) {
                    if (row) {
                        row.style.opacity = '0.4';
                        row.querySelectorAll('button').forEach(function (b) {
                            b.disabled = true;
                        });
                        setTimeout(function(){ row.style.display='none'; }, 300);
                    }
                } else {
                    btn.disabled = false;
                }
            })
            .catch(function () {
                btn.disabled = false;
            });
    }

    // ──────────────────────────────────────────────
    // data-confirm : remplace les onsubmit="return confirm()" inline (CSP-safe)
    // ──────────────────────────────────────────────
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm')) return;
        var msg = form.getAttribute('data-confirm');
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });

    // ──────────────────────────────────────────────
    // Modération des images (REST API)
    // ──────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const approve = e.target.closest('.swiftboard-approve-btn');
        const reject  = e.target.closest('.swiftboard-reject-btn');
        if (approve) {
            e.preventDefault();
            moderate(approve.dataset.id, 'approve', approve);
        } else if (reject) {
            e.preventDefault();
            if (!confirm('Supprimer définitivement cette image ?')) return;
            moderate(reject.dataset.id, 'reject', reject);
        }
    });
})();
