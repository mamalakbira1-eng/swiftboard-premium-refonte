/**
 * SwiftBoard — Anti-FOUC theme loader
 *
 * Applique le thème (light/dark) AVANT le rendu pour éviter le flash de contenu.
 * Doit être chargé sans defer/async dans le <head> (render-blocking intentionnel).
 *
 * @package SwiftBoard
 */
(function() {
    'use strict';
    let stored = null;
    try {
        stored = localStorage.getItem('swiftboard-theme');
    } catch(e) {
        // localStorage peut être désactivé (mode privé) — utiliser la préférence système.
    }
    const valid = stored === 'light' || stored === 'dark';
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = valid ? stored : (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
})();

