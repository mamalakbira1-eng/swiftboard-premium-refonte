# Rapport de recette senior — SwiftBoard

**Version auditée :** SwiftBoard 11.0.6 avec Lots 1 à 3 et corrections de conformité senior

**Environnement :** WordPress 6.8.3, PHP 8.3, MariaDB, bbPress 2.6.14, Docker Compose, Chromium, Firefox et WebKit

**Date de recette :** 23 août 2026

## 1. Décision

La version locale est **validée pour une mise en staging contrôlée**, mais elle ne doit pas encore être présentée comme totalement conforme à l’ensemble des Lots 1 à 9. Les Lots 1 à 3 sont présents et validés. Les scénarios supplémentaires relatifs au commentaire réel, au profil VIP, au clavier, au RTL et au SSE ont désormais une preuve automatisée locale.

Les Lots 4 à 9 restent hors périmètre d’implémentation dans cette passe. Le staging utilisateur ne doit être envisagé qu’après sauvegarde confirmée et sur une copie de recette, jamais directement sur la production.

## 2. Corrections senior appliquées

| Domaine | Correction | Vérification |
|---|---|---|
| CSP | Désactivation propre des règles WordPress `speculationrules` sous CSP stricte, sans ajout de `unsafe-inline` à `script-src` | Aucun bloc `speculationrules` et aucune erreur CSP dans la suite stricte |
| SSE | Passage de l’URL et du nonce REST par attributs `data-*`, puis lecture par EventSource | 20 notifications reçues, p95 mesuré à 37,8 ms dans la recette locale |
| HTTPS | Normalisation des URLs absolues issues d’un autre staging vers l’origine courante ; correction du chemin `assets/img/sujets/` | 0 métadonnée image HTTP restante et asset sujet-08 en HTTP 200 |
| CSS | Ajout de `@charset "UTF-8";` dans `premium-ui.css` | Lint et chargement CSS validés |
| Accessibilité | Suppression du second landmark `banner`, ajout d’une région nommée pour le hero communauté, main unique par page et hiérarchie h1/h2/h3 du profil | Axe sans violation sur les pages runtime contrôlées |
| Import | Normalisation durable dans `inc/admin-bulk-import.php`, pas uniquement dans la base de recette | Les prochains imports utilisent l’origine et le dossier d’assets corrects |
| QA | Ajout d’une suite stricte qui bloque sur console error, pageerror, requête échouée, réponse 4xx/5xx et violations axe | 6 projets navigateur réussis |

## 3. Résultats de validation

| Suite | Résultat | Périmètre |
|---|---:|---|
| Strict runtime | **6 passés, 0 échec** | Accueil, forum, sujet, profil, recherche ; console, réseau et axe |
| Runtime bbPress | **12 passés, 0 échec** | Pages clés et interactions sur Chromium, Firefox et WebKit |
| Fonctionnel CDC | **3 passés, 15 ignorés par conception** | Commentaire réel, profil VIP, clavier ; mutation exécutée une seule fois sur Chromium desktop |
| RTL arabe | **1 passé, 5 ignorés par conception** | Accueil, forum et sujet sur le snapshot arabe dédié |
| FR | **1 passé** | Accueil, forum et sujet sur le snapshot français dédié |
| EN | **1 passé** | Accueil, forum et sujet sur le snapshot anglais dédié |
| SSE | **1 passé, 5 ignorés par conception** | 20 événements réels, p95 inférieur à 5 secondes |
| Lint | **PHP et JavaScript validés** | Fichiers modifiés et client SSE |
| Lighthouse accueil | **100 / 100 / 100 / 100** | Performance / Accessibilité / Bonnes pratiques / SEO après corrections senior |

Les tests ignorés ne sont pas des échecs : ils évitent de répéter les mutations métier sur les six projets navigateur. La recette complète relance toutefois les contrôles anonymes et multi-navigateurs.

## 4. Preuves fonctionnelles

Le pipeline d’import local conserve **10 forums, 40 sujets, 160 réponses et 15 membres** après l’import corrigé. L’écart initial venait de deux contributions légitimes du même auteur avec le même texte sur le sujet 26 ; l’anti-doublon historique les confondait. L’import de démonstration porte désormais une clé stable `reddit:sujet_id:ordre` : les 160 réponses sont créées, puis un second import sans reset reste à 160 grâce à l’idempotence par clé. Les assets locaux restent en HTTPS, avec zéro métadonnée de thème en HTTP. Le compte standard a publié une réponse réelle depuis le formulaire bbPress. Le compte VIP a affiché son profil, son grade et ses onglets. La baseline arabe a été exécutée dans une base séparée avec `lang="ar"`, `dir="rtl"` et sans débordement horizontal.

Le SSE est opt-in, conformément à la contrainte d’hébergement mutualisé. Une exécution dédiée a activé temporairement `SWIFTBOARD_ENABLE_SSE`, injecté 20 notifications locales, vérifié la réception via EventSource et restauré automatiquement la baseline Reddit. Le p95 mesuré est de **37,8 ms** sur cette exécution locale ; ce résultat ne constitue pas une promesse de performance de production et devra être remesuré sur l’infrastructure de staging.

## 5. Réserves restantes

La conformité complète au CDC n’est pas encore déclarable pour les raisons suivantes :

1. Les Lots 4 à 9 n’ont pas encore été implémentés dans cette passe.
2. Le multisite, un vrai widget Elementor/Gutenberg, OAuth, la sauvegarde/restauration de production et le test de charge SSE n’ont pas encore été validés sur une infrastructure de staging.
3. Les preuves locales ne remplacent pas une recette sur le domaine, le cache, le CDN, le PHP-FPM et la base de données réels de l’utilisateur.
4. Les informations d’identification utilisées pour les comptes de recette sont locales et temporaires ; elles ne doivent jamais être réutilisées en staging ou en production.

## 6. Package de livraison

Le package installable `deliverables/swiftboard-premium-v11.0.6-senior.zip` contient uniquement le dossier du thème, sous la forme `swiftboard/`, et 309 fichiers. Son SHA-256 est `d9fc0743d606e4a1886f85d2d04955d46d906deda30df49b86a4ab08fb64b536`. L’archive de preuves `deliverables/swiftboard-senior-evidence.zip` contient les PNG et JSON sélectionnés ; son SHA-256 est `b723ea80206c67447ab3d2c2350f4cee13996feb421765084c6765991bf804d0`.
 Les dumps SQL, logs contenant des informations d’exécution, cookies, mots de passe et fichiers `.env` réels sont exclus des livrables remis et du dépôt public.

La branche de travail doit être taguée après la passe finale et le commit public doit rester dépourvu de données privées. Le déploiement staging recommandé est : sauvegarde, installation du ZIP sur staging, activation, purge des caches, recette stricte, puis décision de promotion ou rollback.

## 7. Conclusion

**Décision senior : PASS pour staging contrôlé ; NO-GO pour production immédiate ; conformité CDC complète encore en cours.** Les corrections prioritaires ont été appliquées, la régression finale consolidée est passée, le ZIP installable et l’archive de preuves sont générés, et le code assaini est publié sur GitHub. Les Lots 4 à 9 ne doivent commencer qu’après confirmation de la stratégie de staging.
