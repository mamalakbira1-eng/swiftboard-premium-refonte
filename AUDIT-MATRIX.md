# Matrice d’audit CDC — SwiftBoard v11.0.6

**Périmètre :** reproduction Docker locale WordPress/PHP/MariaDB avec bbPress, BuddyPress et Elementor. **Aucune production n’a été touchée.**

**Base de code testée :** commit public `733cd39` (r4) augmenté des corrections non encore commités au moment de la recette finale ; le hash r5 exact sera renseigné après le commit de livraison. **Date des dernières preuves :** 25 août 2026 UTC. **URL locale :** `http://127.0.0.1`.

Les douze projets Playwright sont : Chromium, Firefox et WebKit sur mobile `375×812`, tablette `768×1024`, desktop `1440×900` et large desktop `1920×1080`. Les preuves utilisent `--workers=1`. Les comptes sont désignés par leur rôle ; aucun mot de passe, cookie ou état de session n’est inclus.

| ID CDC | Scénario et preuve attendue | URL / périmètre | Compte | Navigateurs / viewport | Statut | Preuve principale |
|---|---|---|---|---|---|---|
| L1-01 | Accueil, feed, thème clair/sombre, titre et cartes | `/` | Visiteur | 12 projets | PASS | `reports/lot1/lot1-*.json`, PNG `homepage-*` |
| L1-02 | Header, navigation, skip-link et premier rendu | `/` | Visiteur | 12 projets | PASS | `reports/lot1/`, `reports/runtime-pages-matrix-local-final.txt` |
| L1-03 | Lot 1 complet sans scénario critique ignoré | `/` | Visiteur | 12 projets | PASS | `reports/lot1-matrix-local-final.txt` — 12/12 |
| L4-01 | Cartes du feed et tris `hot`, `new`, `top`, `rising` avec signatures distinctes | `/?sort=...` | Visiteur | 12 projets | PASS | `reports/cdc-lots-4-9/lot4-*.json` |
| L4-02 | Pagination ou scroll, absence d’overflow et responsive | `/` | Visiteur | 12 projets | PASS | `reports/cdc-lots-4-9/`, matrice Lot 4–9 |
| L4-03 | Vue compacte si contrôle exposé par le produit | `/` | Visiteur | 12 projets | PASS / N/A justifié selon DOM | `reports/cdc-lots-4-9/lot4-*.json` ; N/A seulement si aucun contrôle produit n’existe |
| L4-04 | Diff pixel avant/après sur les deux thèmes | `/` | Visiteur | 12 projets | PASS avec réserve de provenance | `reports/cdc-lots-4-9/visual-diff/summary.json` et PNG diff ; baseline locale `reports/lot1`, sans commit parent vérifiable |
| L5-01 | Thread réel, réponses imbriquées, ordre `Best/Top/New/Controversial/Old` | `/forums/topic/par-ou-commencer-une-epargne-d-urgence/` | Visiteur puis membre | 12 projets | PASS | `reports/cdc-lots-4-9/lot5-*.json` |
| L5-02 | Collapse clavier/souris, reply open/cancel et focus textarea | même topic | Membre QA | 12 projets | PASS | `reports/cdc-lots-4-9/` |
| L5-03 | Réponse réellement publiée, 302 bbPress puis permalink exact vérifié | topic puis `/?p=<reply_id>` | Membre QA | 12 projets | PASS | `reports/cdc-functional/`, `reports/cdc-functional-matrix-local-final-48.txt` — 48/48 |
| L6-01 | Profil membre, statistiques, grade, onglets et contenus | `/forums/users/sbmember/` | Membre QA | 12 projets | PASS | `reports/cdc-lots-4-9/lot6-*.json` |
| L6-02 | Profil VIP et badge accessible | `/forums/users/sbvip/` | VIP QA | 12 projets | PASS | `reports/cdc-functional/`, `reports/cdc-lots-4-9/lot6-vip-*` |
| L7-01 | Forum réel, about, règles et navigation secondaire | `/forums/forum/finances/` | Visiteur | 12 projets | PASS | `reports/cdc-lots-4-9/lot7-*.json` |
| L8-01 | Login WordPress Core personnalisé et erreurs d’authentification | `/wp-login.php` | Visiteur / membre | 12 projets | PASS fonctionnel ; CSP réservée | `reports/runtime-pages-matrix-local-final.txt`, PNG login |
| L8-02 | Inscription, onboarding en trois étapes, e-mail invalide et fermeture | `/register/` | Visiteur | 12 projets | PASS | `reports/cdc-lots-4-9/lot8-*.json` |
| L8-03 | OAuth callback invalide, état manquant, état consommé et replay | routes OAuth REST | Sans fournisseur réel | 12 projets | PASS contractuel | `reports/oauth-contract-matrix-local-no-skip.txt`, `reports/oauth/` |
| L8-04 | Connexion OAuth Google/GitHub/Facebook réelle | fournisseur externe | Credentials développeur absents | N/A — portée non activée | BLOCKED | Aucun faux secret utilisé ; credentials développeur et redirect URI requis |
| L9-01 | Pages clés, axe-core, titres, landmarks, alt, contrastes, focus et overflow | accueil, forum, topic, profil, login, register, recherche | Visiteur | 12 projets | PASS | `reports/strict-runtime-matrix-local-final.txt` — 12/12 ; axe exclut uniquement `#wpadminbar` Core |
| L9-02 | États authentifiés, menu, cloche, dropdown, Escape, focus, FOUC | `/` et routes membre | Membre QA | 12 projets | PASS | `reports/lot9-authenticated-matrix-local-no-skip.txt` — 24/24 |
| L9-03 | Notifications vides et erreurs | `/` / REST notifications | Compte QA vide | 12 projets | PASS | `reports/cdc-lot9-authenticated/notifications-empty-*` |
| L9-04 | Thème clair/sombre et persistance | pages clés | Visiteur / membre | 12 projets | PASS | `reports/cdc-locale-ltr-matrix-local.txt`, runtime, Lot 9 |
| L9-05 | RTL arabe | pages clés | Visiteur | 12 projets configurés RTL | PASS | `reports/cdc-locale-rtl-matrix-local.txt` — 12/12 |
| L9-06 | Gutenberg, shortcode et vrai widget Elementor | `/qa-gutenberg-hot-topics/`, `/qa-shortcode-hot-topics/`, `/qa-elementor-hot-topics/` | Visiteur | 12 projets | PASS | `reports/extensions-matrix-local-restored-r4.txt` — 12/12 |
| L9-07 | Multisite réseau principal et `/community/` | `/`, `/community/` | Administrateur / visiteur | 12 projets ciblés | PASS | `reports/multisite-matrix-local-no-skip.txt` — 12/12 |
| L9-08 | SSE, 20 notifications réelles, p95, reconnexion et fallback polling | REST notifications stream | Compte notification QA | 12 projets | PASS local ; public réservé | `reports/cdc-sse-matrix-local-20-fallback.txt` — 24/24, 20 notifications, p95 < 5 s |
| L9-09 | Régression runtime pages et console/réseau | accueil, forum, topic, profil, recherche | Visiteur | 12 projets | PASS | `reports/runtime-pages-matrix-local-final.txt` — 24/24 |
| Lot 10 | Lighthouse local de référence | accueil, forum, topic, profil, login, recherche | Visiteur | Desktop simulé | PASS avec réserves | `reports/lighthouse-lot10/*.json`, `summary.json` |
| SEC-01 | CSP front, nonce Elementor, absence de `unsafe-inline` dans `script-src` | 9 routes publiques et intégrations | Visiteur | HTTP local | PASS | `reports/CSP-FINAL-SUMMARY.md` |
| SEC-02 | CSP `wp-login.php` réellement prouvée en HTTPS public | login staging | — | — | BLOCKED | Staging `hostingersite.com` sans certificat SSL valide ; en local, l’en-tête login reste partiel dans la réponse observée |
| OPS-01 | Recette sur staging HTTPS Hostinger | domaine staging | — | — | BLOCKED | Domaine actuel non éligible au SSL ; aucun correctif déployé |

## Matrice d’environnement

| Élément | Valeur prouvée |
|---|---|
| WordPress / PHP / DB | Docker local, WordPress avec PHP 8.3 et MariaDB 10.11 selon l’environnement QA |
| Extensions | bbPress 2.6.14, BuddyPress 14.5.2, Elementor 4.2.3 |
| Schéma | votes et notifications v1.1.0 ; colonnes attendues et index vérifiés |
| Moteurs | Chromium, Firefox, WebKit |
| Viewports | 375×812, 768×1024, 1440×900, 1920×1080 |
| Locales | fr_FR LTR et arabe RTL rejouées localement |
| Réseau | HTTP `127.0.0.1` en réseau Docker hôte ; aucun HTTPS public local revendiqué |

## Lecture honnête des statuts

**PASS** signifie qu’un scénario a été réellement exécuté et qu’une preuve correspondante existe. **BLOCKED** désigne une dépendance extérieure non disponible, principalement le SSL du staging, les en-têtes HTTPS publics et les credentials OAuth développeur. **N/A justifié** n’est employé que lorsqu’un contrôle n’est pas exposé par le produit ou qu’une portée n’a pas été activée ; il ne remplace pas une assertion critique.

Les traces Playwright brutes et états de session ne sont pas inclus dans la livraison lorsqu’ils contiennent cookies ou données privées. Les PNG, JSON et résumés assainis sont conservés séparément selon la politique de confidentialité du livrable.
