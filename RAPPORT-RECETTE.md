# Rapport de recette — SwiftBoard v11.0.6 / préparation r5

**Auteur :** Manus AI
**Date de recette finale locale :** 25 août 2026 UTC
**Référence de travail :** commit public r4 `733cd39` plus corrections non commités au moment des tests ; le commit r5 exact sera ajouté après la validation du dépôt.
**Périmètre de sécurité :** reproduction Docker locale uniquement. La production n’a reçu aucune modification.

## Décision exécutive

La version de travail est **fonctionnellement démontrée sur la reproduction locale** pour les scénarios du CDC Lots 1 à 9. Les matrices critiques ont été exécutées sans skip silencieux : Lot 1 `12/12`, Lots 4 à 9 consolidés `36/36` par combinaison d’une relance globale `33/33` et d’une relance isolée WebKit large `3/3`, fonctionnel authentifié `48/48`, Lot 9 authentifié `24/24`, runtime `24/24`, strict runtime `12/12`, LTR `12/12`, RTL `12/12`, extensions `12/12`, multisite `12/12`, contrat OAuth `12/12` et SSE `24/24`.

Cette décision **ne vaut pas certification globale à 100 %**. Le staging Hostinger autorisé ne fournit pas de certificat SSL valide pour le sous-domaine `hostingersite.com`, ce qui bloque la recette HTTPS publique, la vérification CSP publique, SSE public et la comparaison Lighthouse sur cette infrastructure. L’OAuth fournisseur réel est également non exécuté, car aucun credential développeur valide n’a été fourni ; aucun faux secret n’a été utilisé.

> **Verdict honnête :** PASS local démontré, livraison r5 techniquement préparée ; BLOCKED pour la clôture HTTPS staging, OAuth réel et validation publique. Il serait incorrect d’écrire « 100 % conforme globalement » tant que ces dépendances externes ne sont pas levées.

## Environnement et méthode

| Élément | Valeur |
|---|---|
| Serveur | Docker local, réseau hôte, HTTP `http://127.0.0.1` |
| CMS / runtime | WordPress, PHP 8.3, MariaDB 10.11 |
| Extensions | bbPress 2.6.14, BuddyPress 14.5.2, Elementor 4.2.3 |
| Navigateurs | Chromium, Firefox, WebKit |
| Viewports | Mobile `375×812`, tablette `768×1024`, desktop `1440×900`, large `1920×1080` |
| Locale | fr_FR LTR ; arabe RTL lors de la matrice dédiée |
| Exécution | Playwright séquentiel `--workers=1`, sans exécution concurrente |
| Comptes | Visiteur, membre QA, VIP QA, notification QA, administrateur local |
| Commit de référence | `733cd39` + arbre de travail corrigé ; r5 non encore créé au moment des tests |

Les états mutatifs ont été réinitialisés avant la matrice finale par `scripts/reset-local-qa-actions.php`. Le reset efface les votes, sauvegardes, suivis, métadonnée anti-flood de recette, transients de rate-limit et compteur quotidien de votes, sans désactiver les protections du produit. Les scénarios de publication et de vote attendent ensuite les intervalles métier bbPress/SwiftBoard afin de tester le comportement réel plutôt que de le contourner.

## Résultats par lot

| Lot | Vérification | Résultat | Preuves |
|---|---|---:|---|
| Lot 1 | Accueil, cartes, thème clair/sombre, navigation et accessibilité de base | 12/12 PASS | `reports/lot1-matrix-local-final.txt`, `reports/lot1/` |
| Lot 4 | Feed, cartes, `hot/new/top/rising`, pagination/scroll, vue compacte, responsive et diff pixel | PASS | `reports/cdc-lots-4-9/lot4-*.json`, PNG et `visual-diff/summary.json` |
| Lot 5 | Thread, cinq tris de réponses, imbrication, collapse, reply open/cancel, focus et publication | PASS | `reports/cdc-lots-4-9/lot5-*.json`, `reports/cdc-functional/` |
| Lot 6 | Profil membre/VIP, grade, badge, statistiques, onglets et historiques | PASS | `reports/cdc-lots-4-9/lot6-*.json`, `reports/cdc-functional/` |
| Lot 7 | Forum, hero, about, règles et navigation secondaire | PASS | `reports/cdc-lots-4-9/lot7-*.json` |
| Lot 8 | Login, inscription, onboarding et contrat OAuth anti-rejeu | PASS local ; OAuth réel BLOCKED | `reports/oauth-contract-matrix-local-no-skip.txt`, captures Lot 8 |
| Lot 9 | Responsive, clair/sombre, RTL, axe, focus, landmarks, erreurs, états vides, FOUC | PASS local | `reports/strict-runtime-matrix-local-final.txt`, `reports/lot9-authenticated-matrix-local-no-skip.txt`, locales |
| Intégrations | Gutenberg, shortcode, vrai widget Elementor | 12/12 PASS | `reports/extensions-matrix-local-restored-r4.txt` |
| Multisite | Réseau principal et sous-site `/community/` avec URLs assets corrigées | 12/12 PASS | `reports/multisite-matrix-local-no-skip.txt` |
| SSE | 20 notifications réelles, p95, reconnexion et fallback polling après cinq erreurs contrôlées | 24/24 PASS local | `reports/cdc-sse-matrix-local-20-fallback.txt` |
| Lot 10 | Lighthouse local, schema, lint, CSP front | PASS avec réserves | `reports/lighthouse-lot10/`, `reports/schema-validation-final-2026-08-25.json`, `reports/CSP-FINAL-SUMMARY.md` |

## Contrôles normatifs détaillés

Le scénario de publication remplit réellement le formulaire bbPress authentifié, capture le `302` retourné par le POST et son en-tête `Location`, extrait l’identifiant du reply puis ouvre le permalink WordPress exact afin de vérifier le texte publié. La preuve finale exécute cette séquence sur les douze projets navigateur/viewport et termine à `48 passed`.

Les actions de vote vérifient les deux POST REST, les statuts HTTP `200`, l’état `aria-pressed`, la classe active et le retrait. La sauvegarde vérifie la mutation, le libellé accessible, l’état actif puis le retrait. Les délais de cinq secondes pour les votes et de dix secondes pour bbPress sont respectés explicitement entre projets partageant un compte QA.

L’accessibilité vérifie le skip-link, le focus visible, l’ordre de tabulation ciblé, les landmarks, les titres, les textes alternatifs, les contrastes, l’absence d’overflow horizontal et les états d’erreur/vides. L’exclusion Axe documentée est limitée à `#wpadminbar`, barre WordPress Core tierce hors périmètre du thème. Aucun scénario critique n’a été marqué skip dans les matrices finales exigées.

## Lighthouse local

Le run daté a couvert l’accueil, le forum, le sujet, le profil, le login et la recherche vide. Les fichiers JSON bruts sont dans `reports/lighthouse-lot10/` et le résumé dans `reports/lighthouse-lot10/summary.json`. Le test reste une référence HTTP locale, non une certification HTTPS publique. Les résultats sont sensibles à la variance de l’environnement ; aucune amélioration numérique artificielle n’est revendiquée.

| Route | Performance | Accessibilité | Bonnes pratiques | SEO | Observation |
|---|---:|---:|---:|---:|---|
| Accueil | 92 | 100 | 100 | 100 | Référence locale |
| Forum | 92 | 100 | 100 | 92 | Référence locale |
| Sujet | 82 | 97 | 100 | 91 | Quelques audits Lighthouse d’interaction, sans violation Axe bloquante |
| Profil | 94 | 100 | 100 | 100 | Référence locale |
| Login | mesuré localement | mesuré localement | mesuré localement | réduit | Page Core ; `noindex`/rendu spécifique |
| Recherche vide | mesuré localement | mesuré localement | mesuré localement | réduit | `noindex` attendu pour une recherche sans résultat |

## CSP et limites d’infrastructure

Sur neuf routes locales front et intégration, la réponse HTTP contient une CSP complète avec `script-src` et sans `unsafe-inline` dans cette directive. `style-src 'unsafe-inline'` reste séparé pour les styles historiques WordPress/bbPress. Elementor reçoit les nonces nécessaires et le cache HTML est désactivé lorsqu’il pourrait provoquer un mismatch nonce/CSP.

La sonde locale du 25 août 2026 a obtenu HTTP 200 sur toutes les routes testées. Pour `/wp-login.php`, la réponse observée localement reste limitée à `frame-ancestors 'self'` au lieu de la politique complète ; cette réserve est conservée en **BLOCKED / non prouvée**, non transformée en PASS. La cause doit être traitée au niveau du chemin WordPress Core ou d’un composant d’infrastructure chargé d’émettre les headers ; le thème ne doit pas ajouter `unsafe-inline` pour masquer le problème.

Le sous-domaine Hostinger actuel ne dispose d’aucun certificat SSL valide et le flux d’installation SSL le refuse. Il est donc impossible de certifier honnêtement la CSP finale, SSE et OAuth sur l’URL HTTPS publique. Aucun correctif n’a été déployé sur ce staging tant qu’un domaine personnalisé SSL et une sauvegarde staging confirmée ne sont pas disponibles.

## Qualité du code et confidentialité

Le lint PHP complet exécuté dans le conteneur WordPress a vérifié 190 fichiers sans erreur de syntaxe. `node --check` a vérifié 39 fichiers JavaScript/ESM hors dépendances. `git diff --check` est PASS. Le secret scan final n’a trouvé aucun littéral de clé privée, jeton connu ou valeur de mot de passe à haute confiance dans le source suivi.

Les credentials QA résident uniquement dans `/home/ubuntu/work/swiftboard-live/secrets/qa-credentials.local` avec permissions privées. Ils ne doivent jamais être ajoutés à Git, au ZIP, aux rapports, aux traces ou aux captures. Les traces brutes susceptibles de contenir cookies/états de session ne sont pas destinées à la livraison publique ; seules les preuves assainies et les résumés normatifs peuvent être publiés.

## Conditions de clôture globale

| Condition | Statut |
|---|---|
| Tests fonctionnels locaux Lots 1–9 | PASS démontré |
| Extensions réelles, multisite, SSE local et OAuth contractuel | PASS démontré selon portée respective |
| Domaine staging personnalisé avec SSL valide | BLOCKED — à fournir par l’utilisateur/Hostinger |
| Recette HTTPS publique sur staging | BLOCKED — dépend du SSL |
| CSP publique login/register/lostpassword | BLOCKED — en-tête public non prouvé |
| OAuth Google/GitHub/Facebook réel | BLOCKED — credentials développeur absents |
| SSE public sur l’infrastructure staging | BLOCKED — HTTPS staging indisponible |
| Lighthouse staging comparable | BLOCKED — infrastructure publique non certifiable |
| Production | Aucun changement |

La clôture globale pourra être rejouée après fourniture d’un domaine/sous-domaine personnalisé associé au staging, certificat SSL actif, sauvegarde staging confirmée et, si la portée OAuth réelle est exigée, credentials développeur de test avec redirect URI autorisée. Cette étape devra être conduite sur le staging seulement ; la production restera hors périmètre.

## Références de fichiers

La matrice détaillée est dans [`AUDIT-MATRIX.md`](AUDIT-MATRIX.md). Les corrections reproductibles sont dans `scripts/`, les tests dans `qa/tests/`, les résultats normatifs dans `reports/` et la preuve CSP assainie dans [`reports/CSP-FINAL-SUMMARY.md`](reports/CSP-FINAL-SUMMARY.md). Le tag r5 et son commit exact seront renseignés dans ce rapport après l’assainissement et la validation de l’archive.
