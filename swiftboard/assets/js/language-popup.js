/**
 * SwiftBoard Language Selection Popup
 *
 * Detects visitor browser language and shows a popup if different from site language.
 * Works with Polylang (free) for actual language switching.
 * Shows once per session (cookie-based).
 *
 * CSP-safe: configuration is passed via data-* attributes on a hidden element,
 * NOT via wp_localize_script (which outputs inline <script> blocked by CSP).
 *
 * @package SwiftBoard
 * @since 7.2.0
 */
(function () {
    'use strict';

    // Read config from data-* attributes (CSP-safe, no inline script needed)
    var configEl = document.getElementById('sb-lang-popup-config');
    var cfg = {};
    if (configEl) {
        cfg.siteLang = configEl.getAttribute('data-site-lang') || '';
        cfg.availableLangs = configEl.getAttribute('data-available-langs') || 'fr,en,ar';
        cfg.langUrls = configEl.getAttribute('data-lang-urls') || '';
        // Translatable strings (passed from PHP via __() )
        cfg.msgTitle = configEl.getAttribute('data-msg-title') || 'Change language?';
        cfg.msgBody = configEl.getAttribute('data-msg-body') || 'This site is also available in another language. Would you like to switch?';
        cfg.msgSwitch = configEl.getAttribute('data-msg-switch') || 'Switch';
        cfg.msgStay = configEl.getAttribute('data-msg-stay') || 'Stay here';
        cfg.msgHint = configEl.getAttribute('data-msg-hint') || 'You can change this anytime in the header.';
    }

    var siteLang = cfg.siteLang || (document.documentElement.lang || 'fr').substring(0, 2);
    var availableLangs = cfg.availableLangs.split(',');
    var langUrls = {};
    try { langUrls = JSON.parse(cfg.langUrls || '{}'); } catch(e) {}

    var langNames = {
        'fr': 'Français',
        'en': 'English',
        'ar': 'العربية',
        'es': 'Español',
        'de': 'Deutsch',
        'pt': 'Português',
        'zh': '中文',
        'ja': '日本語'
    };

    var langFlags = {
        'fr': '🇫🇷',
        'en': '🇬🇧',
        'ar': '🇸🇦',
        'es': '🇪🇸',
        'de': '🇩🇪',
        'pt': '🇵🇹',
        'zh': '🇨🇳',
        'ja': '🇯🇵'
    };

    // Don't show if already visited this session
    if (document.cookie.indexOf('sb_lang_chosen=1') !== -1) return;

    // Detect browser language
    var browserLang = (navigator.language || navigator.userLanguage || '').substring(0, 2).toLowerCase();

    // Don't show if browser language matches site language
    if (browserLang === siteLang) return;

    // Don't show if browser language is not available on this site
    if (availableLangs.indexOf(browserLang) === -1) return;

    // Don't show on admin pages
    if (document.body && document.body.classList && document.body.classList.contains('wp-admin')) return;

    // Show popup after 1 second delay
    setTimeout(function () {
        showLanguagePopup(browserLang);
    }, 1000);

    function showLanguagePopup(suggestedLang) {
        var overlay = document.createElement('div');
        overlay.id = 'sb-lang-popup-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', cfg.msgTitle);
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99998;display:flex;align-items:center;justify-content:center;';

        var popup = document.createElement('div');
        popup.className = 'sb-lang-popup';
        popup.style.cssText = 'background:#fff;border-radius:12px;padding:32px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);text-align:center;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,system-ui,sans-serif;';

        var siteLangName = langNames[siteLang] || siteLang;
        var suggestedName = langNames[suggestedLang] || suggestedLang;
        var siteFlag = langFlags[siteLang] || '🌐';
        var suggestedFlag = langFlags[suggestedLang] || '🌐';

        popup.innerHTML =
            '<div style="font-size:32px;margin-bottom:12px;">' + suggestedFlag + ' ' + siteFlag + '</div>' +
            '<h3 style="margin:0 0 8px;font-size:18px;color:#1a1a1b;">' + cfg.msgTitle + '</h3>' +
            '<p style="margin:0 0 20px;font-size:14px;color:#6b6b75;">' +
            cfg.msgBody.replace('%s', suggestedName) +
            '</p>' +
            '<div style="display:flex;gap:12px;justify-content:center;">' +
            '<button id="sb-lang-switch" type="button" style="background:#006cbd;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">' +
            suggestedFlag + ' ' + cfg.msgSwitch + ' (' + suggestedName + ')' +
            '</button>' +
            '<button id="sb-lang-stay" type="button" style="background:#f6f7f8;color:#1a1a1b;border:1px solid #d4d5d7;padding:10px 24px;border-radius:8px;font-size:14px;cursor:pointer;">' +
            siteFlag + ' ' + cfg.msgStay + ' (' + siteLangName + ')' +
            '</button>' +
            '</div>' +
            '<p style="margin:12px 0 0;font-size:11px;color:#4b5563;">' + cfg.msgHint + '</p>';

        overlay.appendChild(popup);
        document.body.appendChild(overlay);

        // Focus management for accessibility
        var switchBtn = document.getElementById('sb-lang-switch');
        if (switchBtn) switchBtn.focus();

        // Switch language button
        switchBtn.addEventListener('click', function () {
            document.cookie = 'sb_lang_chosen=1;path=/;max-age=86400';
            if (langUrls[suggestedLang]) {
                window.location.href = langUrls[suggestedLang];
            } else {
                closePopup();
            }
        });

        // Stay button
        document.getElementById('sb-lang-stay').addEventListener('click', function () {
            closePopup();
        });

        // Close on overlay click
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closePopup();
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closePopup();
        });

        function closePopup() {
            document.cookie = 'sb_lang_chosen=1;path=/;max-age=86400';
            overlay.style.opacity = '0';
            overlay.style.transition = 'opacity 0.2s';
            setTimeout(function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }, 200);
        }
    }
})();
