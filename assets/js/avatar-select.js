/**
 * SwiftBoard Avatar Selector
 *
 * Handles avatar selection on profile page and registration form.
 * CSP-safe: external file, config via data-* attributes.
 */
(function() {
    'use strict';

    function initProfileAvatar() {
        var buttons = document.querySelectorAll('.sb-avatar-option');
        if (!buttons.length) return;

        var cfgEl = document.getElementById('sb-avatar-config');
        var restUrl = cfgEl ? cfgEl.getAttribute('data-rest-url') : '/wp-json';
        var notifCfg = document.getElementById('swiftboard-notifs-config');
        var wpNonce = notifCfg ? (notifCfg.getAttribute('data-nonce') || '') : '';

        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-avatar-id');
                var nonce = btn.getAttribute('data-nonce');
                var baseUrl = restUrl.replace(/\/+$/, '');

                // Visual feedback immediately
                buttons.forEach(function(b) {
                    b.classList.remove('selected');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('selected');
                btn.setAttribute('aria-pressed', 'true');

                fetch(baseUrl + '/swiftboard/v1/avatar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ avatar_id: parseInt(id, 10), nonce: nonce })
                }).then(function(r) { return r.json(); }).then(function(d) {
                    if (!d || !d.ok) {
                        // Revert on error
                        btn.classList.remove('selected');
                        btn.setAttribute('aria-pressed', 'false');
                        console.error('Avatar save failed:', d);
                    }
                }).catch(function(err) {
                    console.error('Avatar save error:', err);
                });
            });
        });
    }

    function initRegistrationAvatar() {
        var input = document.getElementById('swiftboard_avatar_choice');
        if (!input) return;

        document.querySelectorAll('.sb-reg-avatar').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-avatar-id');
                input.value = id;
                document.querySelectorAll('.sb-reg-avatar').forEach(function(b) {
                    b.classList.remove('selected');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('selected');
                btn.setAttribute('aria-pressed', 'true');
            });
        });
    }

    function init() {
        initProfileAvatar();
        initRegistrationAvatar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
