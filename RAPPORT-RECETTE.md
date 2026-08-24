# Rapport de recette senior — SwiftBoard v11.0.6

**Date de la dernière recette :** 24 août 2026.
**Source finale auditée :** tag GitHub public `v11.0.6-senior-staging-final-r4`; le commit résolu, l’arbre thème et le SHA-256 du ZIP sont publiés ensemble dans la release r4.
**Environnement :** WordPress 6.8.3, PHP 8.3, MariaDB 10.11, bbPress 2.6.14, Docker Compose, Playwright 1.62.1, axe-core, Lighthouse 13.4.1.
**URL Docker historique :** `https://8088-iusiaz3ltza0hnfunobhr-de99ba16.us5.manus.computer`.
**URL staging Hostinger :** `https://midnightblue-mantis-446085.hostingersite.com` — installation de test autorisée, sans aucun accès ni changement en production.

> **Décision senior :** la conformité Docker est démontrée et une recette réelle staging a été engagée. Chromium et Firefox passent les 12 scénarios L4–L9 chacun; WebKit reste à rejouer après une indisponibilité hcdn. Je ne déclare pas « 100 % conforme globalement » : la CSP publique Hostinger est remplacée par `upgrade-insecure-requests`, la comparaison Lighthouse staging n’est pas encore disponible et aucun OAuth fournisseur réel n’est activé. La production n’a pas été touchée.

## 1. Objectif et règle de preuve

Le CDC exige pour chaque lot des fichiers source identifiables, des captures PNG prises par un navigateur réel, un résultat Playwright, des rapports axe-core/Lighthouse et une liste d’erreurs console/PHP. Ce rapport ne considère pas une fonctionnalité comme prouvée par la seule lecture du code. Les preuves sont produites dans `reports/`; les bases SQL, cookies et mots de passe temporaires restent exclus des livrables publics.

La sandbox principale contient deux conteneurs actifs, `swiftboard-wordpress` et `swiftboard-db`. Après restauration contrôlée, la base contient 10 forums, 40 topics, 162 réponses et 18 utilisateurs. Le staging Hostinger a été préparé avec le forum `Finances`, les deux sujets CDC, des réponses imbriquées, les comptes QA `sbvip` et `sbmember`, puis le ZIP a11y1 a été installé et le cache SwiftBoard purgé. Les extensions et le multisite disposent de stacks Docker séparées, testées en localhost uniquement.

## 2. Commits isolés et fichiers modifiés

| Lot | Commit | Fichiers exacts du commit |
|---|---|---|
| L4 | `84e2367` | `assets/css/premium-ui.css`, `assets/js/main.js`, `front-page.php` |
| L5 | `29f636b` | commit marqueur de recette; implémentation imbriquée dans `inc/nested-comments.php` et `assets/js/main.js` déjà versionnée |
| L6 | `d5ae2da` | `inc/avatars.php` |
| L7 | `2c27b8f` | `inc/nav-lateral.php` |
| L8 historique | `f04dd69` | `assets/css/login.css`, `assets/css/onboarding.css`, `header.php`, `inc/enqueue.php`, `inc/login-branding.php`, `search.php`, `searchform.php` |
| L9 historique | `8edbbd8` | commit marqueur de recette responsive/accessibilité |
| L8 final | `2057e4c` | `assets/css/login.css`, `assets/css/onboarding.css`, `inc/enqueue.php`, `inc/login-branding.php`, `inc/nav-lateral.php`, `inc/security-headers.php` |
| L9 final | `bff360d` | `assets/css/main.css` |

L’arbre Git du thème source est propre au commit final. Les tests et rapports vivent dans le projet de recette et sont synchronisés séparément vers le dépôt GitHub après assainissement; aucun dump SQL, cookie ou mot de passe n’est copié.

## 3. Tests exécutés et résultats

| Suite | Commande ou scénario | Résultat final |
|---|---|---:|
| Lot1 | `playwright test tests/lot1.spec.mjs --workers=1` | 12/12 PASS |
| Lot4–L9 anonyme final | `SB_QA_BUST_CACHE=1 playwright test tests/cdc-lots-4-9.spec.mjs -g 'Lot 9 — pages clés'` | 12/12 PASS |
| Staging L4–L9 Chromium | `SWIFTBOARD_BASE_URL=... playwright test tests/cdc-lots-4-9.spec.mjs` | 12/12 PASS |
| Staging L4–L9 Firefox | même matrice sur 4 viewports | 12/12 PASS |
| Staging L4–L9 WebKit | même matrice sur 4 viewports | 6/12 initial; post-correctif bloqué hcdn |
| Lot9 authentifié final Docker | `playwright test tests/cdc-lot9-authenticated.spec.mjs -g 'états membre'` | 12/12 PASS |
| Notifications vides | compte `sbemptyqa` frais, puis suppression automatique | 1/1 PASS |
| Fonctionnel complet | publication, profil VIP, clavier, vote/save/menu | 4/4 PASS; 44 skips attendus sur les projets non maîtres |
| Strict runtime | pages clés, console, réseau, axe, HTTP | 12/12 PASS |
| Runtime pages | pages bbPress, profil, recherche, header/interactions | 24/24 PASS |
| RTL arabe | Chromium, Firefox, WebKit desktop | 3/3 PASS |
| Gutenberg/shortcode/Elementor | stack extensions localhost:8090 | 1/1 PASS |
| Multisite | principal + `/community/`, stack localhost:8091 | 1/1 PASS |
| OAuth contractuel | not_configured, state, invalid_state | 1/1 PASS |

Les mutations fonctionnelles sont exécutées sur Chromium desktop avec une fixture locale contrôlée; les états visuels, le responsive, l’axe et le runtime sont vérifiés sur les 12 projets. Les 44 skips de la suite fonctionnelle sont des gardes explicites de scénario maître, non des erreurs masquées.

Les logs correspondants sont `reports/lot1-12-final.log`, `reports/cdc-lot9-full-12-post-csp.log`, `reports/cdc-lot9-authenticated-post-csp-final.log`, `reports/cdc-lot9-authenticated-empty-final.log`, `reports/cdc-functional-full-final.log`, `reports/strict-runtime-12-final.log`, `reports/runtime-pages-12-final.log`, `reports/locale-ar-rtl-final-3-engines.log`, `reports/extensions-final-real.log`, `reports/multisite-final-real.log` et `reports/oauth-contract-final.log`.

## 4. Résultats par lot

### Lot 4 — Accueil et feed

Le feed trie effectivement les états hot, new, top et rising; les signatures de cartes ne retombent pas silencieusement sur hot. La pagination repose sur `sb_paged`, la vue compacte expose un bouton accessible et persistant, et la bannière mène vers l’onboarding. Les captures par moteur et viewport se trouvent dans `reports/cdc-lots-4-9/` sous les préfixes `lot4-home-*`; les JSON `lot4-*.json` conservent les critères et le runtime.

Le comparateur `reports/cdc-lots-4-9/visual-diff/summary.json` compare la baseline authentique du commit parent `4c92c94` à la version finale avec `pixelmatch threshold=0.1`, `includeAA=false` et `maxDiffPercent=1`. Les douze comparaisons sélectionnées sont PASS, avec écarts de 0,0135 % à 0,1653 % et dimensions identiques. Le log est `reports/visual-diff-lot4-final.log`.

### Lot 5 — Thread et commentaires

Le thread réel contient des réponses imbriquées, cinq tris `Best/Top/New/Controversial/Old`, un collapse clavier/souris et les actions reply open/cancel. Une réponse bbPress a été publiée réellement avec le compte membre local et rendue dans le thread. Le correctif de double-toggle entre `main.js` et `nested-comments.js` est couvert par les captures `lot5-topic-*` et la suite fonctionnelle.

### Lot 6 — Profil VIP

Le profil VIP expose hero, quatre statistiques, grade, quatre onglets publics, trophées, historiques posts/comments et avatar. Les captures `lot6-vip-*` et `lot6-vip-trophies-*` sont présentes par projet. Les titres de niveau avatar ont été corrigés en h2 pour la hiérarchie sémantique.

### Lot 7 — Forums

La page forum réelle expose le hero, la carte about, les règles et le landmark `Navigation secondaire`. Les fixtures locales de règles sont seedées avant la preuve. Les captures sont `lot7-forum-*` et le JSON Lots 5–8 conserve les critères L7-01.

### Lot 8 — Auth, onboarding et OAuth

Login et signup sont testés sur le rendu WordPress Core personnalisé. L’onboarding couvre les trois étapes, l’e-mail invalide, la fermeture, le contrat social non configuré et les boutons sociaux. Le loader de thème login est externe et enqueued; aucun nouveau JS inline de thème n’a été ajouté. Le contrat OAuth est uniquement contractuel : route non configurée, state hexadécimal de 64 caractères, callback sans état valide et faux token rejeté. Aucune connexion réelle Google/GitHub/Facebook n’est revendiquée sans credentials de développement fournis par l’utilisateur.

### Lot 9 — Responsive, accessibilité, états et RTL

La suite Docker anonyme finale exécute 12 projets et passe 12/12. Sur le staging Hostinger, la même matrice L4–L9 passe 12/12 sur Chromium et 12/12 sur Firefox. La passe WebKit initiale a passé 6/12; les causes identifiées ont été corrigées dans la recette, mais la vérification post-correctif est bloquée par l’indisponibilité temporaire de hcdn. Le H1 de `/wp-login.php` est désormais visible et la recette Chromium/Firefox ne signale plus la violation `page-has-heading-one`.

L’état vide des notifications est prouvé sur un compte local frais par appel REST HTTP 200, texte `Aucune notification`, zéro élément, axe sans violation, capture `reports/cdc-lot9-authenticated/notifications-empty-chromium-desktop.png`, puis suppression du compte. Le RTL arabe est rejoué sur les trois moteurs desktop avec restauration automatique de la base et résultat 3/3 PASS.

### Lot 10 — Régression finale

La suite strict-runtime finale est verte 12/12 sur cinq pages clés et six familles de contrôles. La suite runtime-pages finale est verte 24/24. Le scan Docker récent `reports/final-docker-error-scan.txt` ne contient aucun PHP fatal, warning, notice, parse error ou erreur d’exécution détectée sur la fenêtre observée. L’exclusion axe unique et documentée est `#wpadminbar`, barre WordPress tierce hors périmètre du thème.

## 5. Lighthouse final

Le run Lighthouse historique Docker a été généré le 24 août 2026 à `03:14:53Z` et se trouve dans `reports/lighthouse-lot10/summary.json`. Aucun run Lighthouse staging comparable n’a encore été obtenu, car hcdn est devenu non joignable après la matrice WebKit; aucune non-régression staging n’est donc revendiquée.

| Page | Performance | Accessibilité | Bonnes pratiques | SEO | FCP/LCP | TBT | CLS |
|---|---:|---:|---:|---:|---:|---:|---:|
| Home | 92 | 100 | 100 | 100 | 1329,8 / 1329,8 ms | 0 | 0 |
| Forum | 92 | 100 | 100 | 92 | 1261,0 / 1370,0 ms | 0 | 0 |
| Topic | 93 | 96 | 100 | 91 | 1260,7 / 1260,7 ms | 0 | 0 |
| Profil | 94 | 100 | 100 | 100 | 1233,7 / 1233,7 ms | 0 | 0 |
| Login | 65 | 100 | 100 | 54 | 1796,6 / 1871,6 ms | 398 ms | 0,0013 |
| Recherche vide | 94 | 100 | 100 | 63 | 1213,8 / 1213,8 ms | 0 | 0 |

Le CDC ne définit pas de seuil numérique Lighthouse autre que l’absence de régression par rapport au Lot0. Le home parent avait Performance 94 contre 92 sur cette exécution finale; plusieurs répétitions antérieures ont donné 93–94. La variance du proxy rend une non-régression numérique stricte indéfendable. Les scores SEO réduits de la recherche vide et du login proviennent des directives `noindex`; le topic a une alerte Lighthouse `target-size` malgré un axe-core sans violation, ce qui est conservé comme observation et non masqué.

## 6. CSP et sécurité

Le front local et public transmet une CSP enforce complète contenant `script-src 'self'` et des hashes/nonces, sans `unsafe-inline` dans `script-src`. `style-src 'unsafe-inline'` est volontairement séparé pour les styles historiques WordPress/bbPress. La réponse locale Docker de `wp-login.php` contient une CSP complète avec nonce et les scripts inline Core reçoivent ce nonce; les tests de login/signup restent fonctionnels.

La limite est observable sur l’URL HTTPS publique Hostinger : `curl` avec et sans `Cache-Control: no-cache` a reçu sur l’accueil, le forum, les sujets, `wp-login.php`, `action=register` et `action=lostpassword` une politique réduite `Content-Security-Policy: upgrade-insecure-requests`. La politique stricte générée par SwiftBoard n’est donc pas vérifiable au bord public. Cette différence est consignée dans les rapports `reports/staging-initial-headers.txt` et `reports/staging-csp-after-theme-install.txt`; la réponse locale Docker émet la CSP complète.

Il s’agit d’un **blocage d’infrastructure du proxy HTTPS public**, non d’une preuve de défaut de code local. Tant que le proxy ne transmet pas la politique complète, le rapport ne peut pas certifier la CSP login sur l’URL publique.

## 7. Intégrations isolées

La stack extensions sur `127.0.0.1:8090` a rendu et audité une page Gutenberg, une page shortcode et une page Elementor réelle avec axe et runtime; résultat 1/1. La stack multisite sur `127.0.0.1:8091` a vérifié le réseau principal et `/community/`; résultat 1/1. Ces stacks sont isolées et ne constituent pas une validation staging.

Le fournisseur OAuth réel n’est pas activé. La preuve actuelle est volontairement limitée au contrat anti-rejeu et aux erreurs attendues d’une configuration absente. Un test OAuth réel exige un Client ID, un secret, une redirect URI et l’autorisation explicite de l’utilisateur.

## 8. Décision finale et conditions de clôture globale

**PASS partiel du staging et conformité Docker démontrée; non déclarable « 100 % conforme globalement ».** Le commit, le tag et le ZIP r4 sont alignés sur l’arbre public exact. Restent à clôturer : la passe WebKit après rétablissement hcdn, la CSP publique Hostinger, le Lighthouse sur la même infrastructure et l’éventuel OAuth réel.

La clôture globale exige encore :

1. que l’administrateur Hostinger/CDN transmette la CSP stricte sur tous les chemins publics, notamment `wp-login.php`;
2. que la matrice WebKit staging soit rejouée après rétablissement de hcdn;
3. que l’utilisateur fournisse, s’il veut cette portée, des credentials OAuth de développement et une redirect URI autorisée;
4. qu’une mesure Lighthouse staging soit comparée dans la même infrastructure, sans comparer artificiellement Docker et Hostinger.

Aucun déploiement production n’a été effectué. Les mots de passe temporaires, cookies, dumps SQL et logs privés ne sont pas inclus dans les livrables publics.
