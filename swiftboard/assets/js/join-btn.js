/**
 * SwiftBoard Subreddit Join Button
 *
 * Handles subscribe/unsubscribe toggle on forum pages.
 * CSP-safe: external file, config via data-* attributes.
 */
(function() {
    'use strict';

    function init() {
        var buttons = document.querySelectorAll('.sb-join-btn');
        if (!buttons.length) return;

        var cfgEl = document.getElementById('sb-join-config');
        var restUrl = cfgEl ? cfgEl.getAttribute('data-rest-url') : '/wp-json';
        var labelJoined = cfgEl ? cfgEl.getAttribute('data-label-joined') : '✓ Abonné';
        var labelJoin = cfgEl ? cfgEl.getAttribute('data-label-join') : '＋ S’abonner';

        buttons.forEach(function(btn) {
            if (btn.tagName === 'A') return; // not logged in → link to login

            btn.addEventListener('click', function() {
                var forumId = btn.getAttribute('data-forum-id');
                var nonce = btn.getAttribute('data-nonce');
                var baseUrl = restUrl.replace(/\/+$/, '');

                fetch(baseUrl + '/swiftboard/v1/subreddit', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ forum_id: forumId })
                }).then(function(r) { return r.json(); }).then(function(d) {
                    // Handle both {success, data:{joined}} and {joined, member_count} formats
                    var joined = d && (d.joined || (d.data && d.data.joined));
                    var memberCount = d && (d.member_count || (d.data && d.data.member_count));

                    if (joined) {
                        btn.classList.add('active');
                        btn.setAttribute('aria-pressed', 'true');
                        btn.innerHTML = '<span class="sb-join-check">✓</span> ' + labelJoined;
                    } else {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-pressed', 'false');
                        btn.innerHTML = '<span class="sb-join-plus">＋</span> ' + labelJoin;
                    }

                    var counter = document.querySelector('.sb-subreddit-members[data-forum-id="' + forumId + '"]');
                    if (counter && memberCount) counter.textContent = memberCount;
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
