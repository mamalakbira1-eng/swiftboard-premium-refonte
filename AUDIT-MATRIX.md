# Matrice d’audit CDC — SwiftBoard v11.0.6

**Source contractuelle :** `/home/ubuntu/upload/pasted_content.txt`, cahier des charges « Refonte UX/UI Premium du thème SwiftBoard ».
**Source finale auditée :** le tag GitHub public `v11.0.6-senior-staging-final-r4` et son commit résolu; le ZIP de thème et son SHA-256 sont publiés dans la release correspondante et régénérés depuis cet arbre exact.
**Règle de statut :** `PASS` exige une preuve reproductible; `N/A justifié` exige une justification; `BLOCKED` signale une limite externe non contournée.

> **Verdict honnête :** la conformité Docker historique reste démontrée. Une recette réelle a maintenant été exécutée sur le staging WordPress Hostinger autorisé : Chromium et Firefox passent la matrice L4–L9 complète sur leurs quatre viewports; WebKit a encore une passe post-correctif à terminer après une indisponibilité hcdn. Une conformité « 100 % globale » n’est pas déclarable : Hostinger remplace publiquement la CSP par `upgrade-insecure-requests`, et la comparaison Lighthouse staging n’est pas encore prouvée. La production n’a pas été touchée.

## 1. Environnement vérifié

| Élément | Valeur |
|---|---|
| WordPress | 6.8.3 / PHP 8.3 dans Docker |
| Base de données | MariaDB 10.11 |
| Forum | bbPress 2.6.14 |
| Conteneur principal | `swiftboard-wordpress`, port 8088 |
| URL navigateur exacte | `https://8088-iusiaz3ltza0hnfunobhr-de99ba16.us5.manus.computer` |
| Matrice | Chromium, Firefox, WebKit × 375×812, 768×1024, 1440×900, 1920×1080 |
| Données restaurées | 10 forums, 40 sujets, 162 réponses, 18 utilisateurs |
| Source public exact | tag `v11.0.6-senior-staging-final-r4`; commit résolu et vérifié dans le manifeste de release |
| Lint | Tous les PHP sans erreur de syntaxe; specs Node vérifiées avec `node --check` |

## 2. Lots et commits

| Lot | Exigence CDC | Statut sandbox Docker | Commit isolé | Fichiers source principaux | Preuves |
|---|---|---:|---|---|---|
| L0 | Environnement réel, import, baseline et outillage | PASS | Baseline parent `4c92c94` | Docker Compose, importeur et fixtures | `reports/cdc-lots-4-9/baseline-parent/`, `reports/lighthouse-lot10-pre-csp/` |
| L1 | Tokens, palette, clair/sombre, contraste et persistance | PASS | Historique L1; régression finale | `assets/css/main.css`, `assets/css/premium-ui.css` | `reports/lot1/`, `reports/lot1-12-final.log`; 12/12 |
| L2 | Composants, cartes et actions | PASS contrôlé | Historique du thème | `assets/css/main.css`, `inc/ui-corrections.php`, JS d’actions | `reports/runtime/`, `reports/cdc-functional/`, `reports/runtime-pages-12-final.log` |
| L3 | Header, navigation, menus et cloche | PASS | Historique du thème | `header.php`, `inc/nav-lateral.php`, JS menus/notifications | `reports/runtime/`, `reports/cdc-lot9-authenticated/`; 12/12 authentifié |
| L4 | Feed, tri, pagination, vue compacte, responsive | PASS | `84e2367` | `front-page.php`, `assets/js/main.js`, `assets/css/premium-ui.css` | `reports/cdc-lots-4-9/lot4-*.png`, `lot4-*.json`, `reports/visual-diff-lot4-final.log` |
| L5 | Thread imbriqué, cinq tris, collapse, réponse réelle | PASS | `29f636b` + preuve fonctionnelle | `inc/nested-comments.php`, `assets/js/main.js` | `reports/cdc-lots-4-9/lot5-*.png`, `reports/cdc-functional-full-final.log`; publication 1/1 |
| L6 | Profil VIP, stats, grade, trophées et historiques | PASS | `d5ae2da` | `inc/avatars.php`, modules profil existants | `reports/cdc-lots-4-9/lot6-*.png`, `reports/cdc-functional/` |
| L7 | Hero forum, règles, about, landmark secondaire | PASS | `2c27b8f` | `inc/nav-lateral.php`, `inc/forum-rules.php`, templates forum | `reports/cdc-lots-4-9/lot7-*.png`, JSON Lots 5–8 |
| L8 | Login/signup, onboarding, CSP et OAuth contractuel | PASS local; BLOCKED proxy login | `f04dd69`, `2057e4c` | `assets/css/login.css`, `assets/css/onboarding.css`, `inc/login-branding.php`, `inc/security-headers.php`, `inc/enqueue.php`, `inc/nav-lateral.php` | `reports/cdc-lots-4-9/lot8-*.png`, `reports/oauth/`, `reports/oauth-contract-final.log`, `reports/csp-login-init-check.txt` |
| L9 | Responsive 4 viewports × 3 moteurs, axe, focus, thème, états vides, RTL | PASS sandbox; réserve CSP proxy | `8edbbd8`, `bff360d` | `assets/css/main.css` et corrections précédentes | `reports/cdc-lot9-full-12-post-csp.log`, `reports/cdc-lot9-authenticated-post-csp-final.log`, `reports/cdc-lot9-authenticated-empty-final.log`, `reports/locale-ar-rtl-final-3-engines.log` |
| L10 | Régression finale, CSP, Lighthouse et visual diff | PASS local; décision globale BLOCKED | Correctifs finaux L8/L9 | Source complet final | `reports/strict-runtime-12-final.log`, `reports/runtime-pages-12-final.log`, `reports/lighthouse-lot10/`, `reports/final-docker-error-scan.txt`, `reports/csp-final.txt` |

## 3. Scénarios obligatoires

| Scénario | Preuve exécutée | Résultat |
|---|---|---:|
| Accueil/feed | Central L4/L9, Lot1, strict-runtime | PASS |
| Forum avec hero/règles/about | Central L7, 12 projets | PASS |
| Thread et réponses imbriquées | Central L5, captures par projet | PASS |
| Publication réelle bbPress | `cdc-functional.spec.mjs` avec compte local temporaire | PASS |
| Profil VIP | Central L6 et fonctionnel | PASS |
| Login/signup | Central L8, matrice authentifiée L9 | PASS fonctionnel local |
| Onboarding | Trois étapes, e-mail invalide, contrat social non configuré | PASS |
| Vote puis retrait | POST REST HTTP 200 aux deux mutations; délai conforme au grade Rookie | PASS |
| Sauvegarde puis retrait | POST `user-action` HTTP 200; `active`, `aria-pressed` et libellés vérifiés | PASS |
| Menu overflow et Escape | `aria-expanded`, ouverture et fermeture clavier | PASS |
| Notifications avec éléments | Matrice authentifiée L9 | PASS |
| Notifications vides | Compte frais local, texte `Aucune notification`, zéro item, axe sans violation | PASS 1/1 |
| Recherche sans résultat | Page `search-empty`, axe et capture | PASS fonctionnel; `noindex` explique le SEO Lighthouse inférieur |
| RTL arabe | Chromium, Firefox, WebKit desktop | PASS 3/3 |
| OAuth réel fournisseur | Aucun Client ID/secret fourni; non exécuté | N/A justifié |
| Staging Hostinger | URL de test autorisée; fixtures forum, sujets, réponses, comptes QA et ZIP corrigé installés | Partiel; Chromium 12/12, Firefox 12/12, WebKit post-correctif à rejouer après hcdn | `docs/staging/` |
| Production | Aucun accès ni déploiement | Aucun changement |

## 4. Résultats finaux multi-navigateurs

| Suite | Projets exécutés | Résultat |
|---|---:|---:|
| Lot 9 anonyme Docker final post-CSP | 12 | 12 passed |
| Staging Hostinger L4–L9 Chromium | 12 | 12 passed |
| Staging Hostinger L4–L9 Firefox | 12 | 12 passed |
| Staging Hostinger L4–L9 WebKit | 12 | post-correctif bloqué par indisponibilité hcdn; passe intermédiaire 6/12 |
| Lot 9 authentifié final post-CSP | 12 | 12 passed |
| État vide notifications | 1 Chromium desktop | 1 passed |
| Strict runtime | 12 | 12 passed |
| Runtime pages | 24 tests | 24 passed |
| Lot1 | 12 | 12 passed |
| Fonctionnel complet | 48 instances dont 44 skips justifiés par scénario maître Chromium desktop | 4 passed, 44 skipped attendus |
| RTL arabe | 3 moteurs desktop | 3 passed |
| Extensions Gutenberg/shortcode/Elementor | scénario maître Chromium desktop | 1 passed |
| Multisite principal + `/community/` | scénario maître Chromium desktop | 1 passed |
| OAuth contractuel | scénario maître Chromium desktop | 1 passed |

Les tests fonctionnels mutatifs sont volontairement exécutés une fois sur Chromium desktop avec une fixture locale contrôlée; les états rendus et la compatibilité responsive sont couverts par les matrices 12 projets. Les skips des autres projets sont donc des gardes-fous explicites, pas des échecs masqués.

## 5. Accessibilité, erreurs et visual diff

Les suites centrales et strict-runtime vérifient axe-core, absence de débordement horizontal, console, `pageerror`, requêtes échouées et réponses HTTP défavorables. L’exclusion unique documentée est `#wpadminbar`, barre WordPress tierce hors périmètre du thème. Le scan Docker final `reports/final-docker-error-scan.txt` ne contient aucun fatal, warning, notice ou erreur PHP sur la fenêtre auditée.

La comparaison pixelmatch Lot4 utilise `threshold=0.1`, `includeAA=false` et `maxDiffPercent=1`. Les douze comparaisons sélectionnées sont PASS, avec un écart mesuré de 0,0135 % à 0,1653 %, dimensions identiques; le détail est dans `reports/cdc-lots-4-9/visual-diff/summary.json`.

## 6. Lighthouse et CSP

La recette publique staging a confirmé que Hostinger/CDN répond avec une politique réduite `Content-Security-Policy: upgrade-insecure-requests` sur l’accueil, le forum, les sujets, `wp-login.php`, l’inscription et la récupération de mot de passe. La politique stricte générée par SwiftBoard n’est donc pas observable depuis l’URL publique; ce point reste **BLOCKED infrastructure** et aucune désactivation de CSP n’a été faite pour obtenir un faux PASS.

La page `/wp-login.php` staging a par ailleurs été corrigée : le H1 « Se connecter » et le H1 de branding sont visibles, avec `login.css?ver=11.0.6-a11y1`. Les preuves sont dans `docs/staging/staging-login-heading-fixed.md`.


Lighthouse final est enregistré dans `reports/lighthouse-lot10/`. Les scores d’accessibilité et de bonnes pratiques sont à 100 sur les pages principales; le profil final observé est Performance 94, et les métriques finales conservent TBT=0 et CLS=0. Les scores de performance restent soumis à la variance du proxy; la comparaison numérique stricte Lot0 n’est donc pas revendiquée comme preuve d’amélioration. La page de recherche vide est `noindex`, ce qui explique son score SEO réduit et ne constitue pas une régression fonctionnelle.

La réponse locale Docker de `wp-login.php` émet une politique enforce avec `script-src 'self' 'nonce-…'`, sans `unsafe-inline` dans `script-src`; les scripts inline Core reçoivent le nonce. Les pages front `home`, `forum` et `topic` émettent également une CSP complète sans `unsafe-inline` dans `script-src`.

La réponse HTTPS publique exacte transmet bien la CSP complète sur les pages front, mais le proxy renvoie seulement `Content-Security-Policy: frame-ancestors 'self';` pour `wp-login.php`, `action=register` et `action=lostpassword`, même avec `Cache-Control: no-cache`. Cette divergence est enregistrée comme **BLOCKED externe**. Elle empêche une preuve de conformité CSP complète sur l’URL publique; elle ne doit pas être transformée en PASS.

## 7. Décision

**Décision : PASS staging partiel et conformité Docker démontrée; non déclarable « 100 % conforme globalement ».** Les blocages restants sont la CSP Hostinger/CDN, la passe WebKit staging à rejouer après rétablissement hcdn, la non-régression Lighthouse sur la même infrastructure et l’absence d’OAuth fournisseur réel. Aucun déploiement de production n’a été effectué.
