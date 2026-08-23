/**
 * SwiftBoard — Validation du formulaire d'inscription (CSP-safe).
 * Externalisé depuis bbpress/form-user-register.php pour respecter la CSP.
 * Les traductions sont passées via data-* attributes sur le <form>.
 */
(function () {
    'use strict';

    function init() {
        var form = document.querySelector('.bbp-login-form');
        if (!form) return;

        var notice = form.querySelector('.sb-reg-notice');
        var msgMismatch = form.getAttribute('data-msg-mismatch') || 'Passwords do not match.';
        var msgShort = form.getAttribute('data-msg-short') || 'Password must be at least 8 characters.';

        function showMsg(msg, field) {
            if (notice) {
                notice.textContent = msg;
                notice.style.display = 'block';
            }
            if (field) field.focus();
        }

        form.addEventListener('submit', function (e) {
            var pass = document.getElementById('user_pass');
            var confirm = document.getElementById('user_pass_confirm');
            if (notice) notice.style.display = 'none';

            if (pass && confirm && pass.value !== confirm.value) {
                e.preventDefault();
                showMsg(msgMismatch, confirm);
                return false;
            }
            if (pass && pass.value.length < 8) {
                e.preventDefault();
                showMsg(msgShort, pass);
                return false;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
