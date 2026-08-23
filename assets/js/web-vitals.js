/**
 * RUM Core Web Vitals — LCP, CLS, INP, TTFB.
 *
 * EXI-QUAL-06 : ce code etait injecte via wp_add_inline_script(), donc rendu
 * en <script> inline. Il imposait 'unsafe-inline' dans la CSP script-src et
 * empechait le passage de Content-Security-Policy en enforce.
 *
 * La configuration (endpoint, taux d'echantillonnage, gabarit) arrive
 * desormais par wp_localize_script sous le nom swiftBoardRUM.
 */
(function(){
    // Configuration par attributs data-* : pas de <script> inline, donc
    // compatible avec Content-Security-Policy en enforce.
    const _el = document.getElementById('sb-rum-config');
    if (!_el) {
        return;
    }
    const C = {
        endpoint: _el.getAttribute('data-endpoint'),
        rate: parseInt(_el.getAttribute('data-rate'), 10) || 0,
        template: _el.getAttribute('data-template') || ''
    };
    if (!C.endpoint) {
        return;
    }
    // Echantillonnage cote client : la requete n'est meme pas construite pour
    // les visiteurs non retenus.
    if (Math.random() * 100 >= C.rate) {
        return;
    }
    if (!('PerformanceObserver' in window)) {
        return;
    }

    const m = {};

    function observe(type, cb) {
        try {
            const po = new PerformanceObserver(function(l){
                l.getEntries().forEach(cb);
            });
            po.observe({ type: type, buffered: true });
            return po;
        } catch (e) {
            return null;
        }
    }

    // LCP : on garde la derniere valeur observee avant interaction.
    observe('largest-contentful-paint', function(e){
        m.lcp = Math.round(e.startTime);
    });

    // CLS : somme des decalages hors interaction utilisateur.
    let cls = 0;
    observe('layout-shift', function(e){
        if (!e.hadRecentInput) {
            cls += e.value; m.cls = Math.round(cls * 1000) / 1000;
        }
    });

    // INP : pire latence d'interaction observee.
    observe('event', function(e){
        if (e.interactionId) {
            const d = Math.round(e.duration);
            if (!m.inp || d > m.inp) {
                m.inp = d;
            }
        }
    });

    // TTFB : disponible immediatement.
    try {
        const nav = performance.getEntriesByType('navigation')[0];
        if (nav) {
            m.ttfb = Math.round(nav.responseStart);
        }
    } catch (e) {}

    let envoye = false;
    function envoyer() {
        if (envoye) {
            return;
        }
        envoye = true;
        if (!Object.keys(m).length) {
            return;
        }
        m.template = C.template;
        m.device = window.innerWidth < 768 ? 'mobile' : 'desktop';
        const body = JSON.stringify(m);
        // sendBeacon survit a la fermeture de l'onglet, contrairement a fetch.
        if (navigator.sendBeacon) {
            navigator.sendBeacon(C.endpoint, new Blob([body], { type: 'application/json' }));
        } else {
            fetch(C.endpoint, { method: 'POST', body: body, headers: { 'Content-Type': 'application/json' }, keepalive: true });
        }
    }

    // visibilitychange est plus fiable que unload, qui n'est pas declenche
    // sur mobile lors d'un changement d'application.
    document.addEventListener('visibilitychange', function(){
        if (document.visibilityState === 'hidden') {
            envoyer();
        }
    });
    window.addEventListener('pagehide', envoyer);
})();

