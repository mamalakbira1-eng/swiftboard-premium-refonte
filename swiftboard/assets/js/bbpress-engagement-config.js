/* SwiftBoard CSP bridge for the unmodified bbPress engagements script. */
(function() {
    var cfg = document.getElementById('swiftboard-bbp-engagement-config');
    if (!cfg) return;
    window.bbpEngagementJS = {
        object_id: cfg.getAttribute('data-object-id') || '',
        bbp_ajaxurl: cfg.getAttribute('data-ajax-url') || '',
        generic_ajax_error: cfg.getAttribute('data-error') || 'Something went wrong. Refresh your browser and try again.'
    };
})();
