# Rapport de recette senior — SwiftBoard

**Version auditée :** SwiftBoard 11.0.6, Lots 1 à 3 déjà appliqués, corrections senior de conformité et validations avancées sandbox.

**Environnements :** WordPress 6.8.3, PHP 8.3, MariaDB 10.11, bbPress 2.6.14, BuddyPress 14.5.2, Elementor 4.2.3, Docker Compose, Chromium, Firefox et WebKit.

**Date de recette :** 23 août 2026.

## 1. Décision

La version locale reste **PASS pour un staging contrôlé** et **NO-GO pour une mise en production immédiate**. Les corrections CSP/HTTPS/accessibilité, l’import à 160 réponses, le runtime strict, les scénarios métier, les locales FR/EN/AR, le SSE, le multisite, le rendu d’un vrai shortcode/bloc et le rendu d’un vrai widget Elementor ont maintenant une validation sandbox ciblée.

La conformité complète du CDC v3 n’est pas déclarée. Les fonctionnalités déjà présentes dans la source et rattachées aux Lots 4 à 9 doivent encore être rapprochées des critères détaillés du CDC v3, lot par lot, avec leurs preuves dédiées. OAuth fournisseur réel n’est pas validé sans identifiants de développement et URL de callback appartenant à l’utilisateur. Aucun accès au WordPress utilisateur n’a été effectué.

## 2. Corrections senior appliquées

| Domaine | Correction | Vérification |
|---|---|---|
| CSP WordPress | Désactivation propre des règles `speculationrules` sous CSP stricte, sans `unsafe-inline` dans `script-src` | Aucun bloc de spéculation injecté dans le runtime contrôlé |
| CSP Elementor | Nonce par requête pour les scripts inline Elementor, buffer ciblé pour les sorties tierces et exclusion des pages Elementor du page-cache | 7 scripts inline contrôlés, tous porteurs d’un nonce ; aucune erreur CSP dans la suite d’intégration |
| Compatibilité multisite | Guard sur les fonctions bbPress absentes afin d’éviter un fatal sur une installation réseau sans bbPress encore chargé | HTTP 200 sur le réseau principal et le sous-site ; aucun nouveau log PHP |
| HTTPS/import | Normalisation des URLs absolues et correction du dossier `assets/img/sujets/` | Zéro métadonnée d’image de thème en HTTP ; assets en HTTP 200 |
| CSS | Ajout de `@charset "UTF-8"` à `premium-ui.css` | Lint et chargement CSS validés |
| Accessibilité | Main unique, région nommée, placement du skip-link dans un landmark et hiérarchie h1/h2/h3 corrigée | Axe vert sur les pages strictes et intégrations contrôlées, hors barre tierce BuddyPress documentée |
| Import | Clé stable `reddit:sujet_id:ordre` pour distinguer deux réponses légitimement identiques | 160 réponses créées ; second import idempotent à 160 |
| QA | Suites strictes pour console, pageerror, réseau, statuts HTTP, axe, locales, extensions, multisite et OAuth contractuel | Résultats détaillés ci-dessous |

## 3. Résultats de validation

| Suite | Résultat | Périmètre |
|---|---:|---|
| Régression primaire finale | **36 passés, 0 échec** | Baseline, Lot 1, runtime et strict runtime sur Chromium, Firefox et WebKit |
| Strict runtime | **6 passés, 0 échec** | Accueil, forum, sujet, profil, recherche ; console, réseau, axe et landmark main |
| Runtime bbPress | **12 passés, 0 échec** | Pages clés et interactions |
| Fonctionnel CDC | **3 passés, 15 ignorés par conception** | Commentaire réel, profil VIP, clavier et thème |
| Locales FR/EN/AR | **3 passés, 15 ignorés par conception** | Smoke-tests LTR français/anglais et RTL arabe, sans débordement |
| SSE | **1 passé, 5 ignorés par conception** | 20 notifications reçues, p95 local de 37,8 ms |
| Gutenberg / shortcode / Elementor | **1 passé, 0 échec** | Trois pages WordPress réelles rendues avec axe, CSP, réseau et captures PNG |
| Multisite | **1 passé, 0 échec** | Réseau principal et sous-site `/community/`, assets et thème SwiftBoard |
| OAuth contractuel | **1 passé, 0 échec** | Routes non configurées, state navigateur, anti-rejeu et erreurs attendues 500/403 |
| Lighthouse accueil | **100 / 100 / 100 / 100** | Performance / Accessibilité / Bonnes pratiques / SEO après corrections |
| Lint | **PASS** | PHP du thème et fichiers modifiés |

Les tests ignorés évitent de répéter les mutations métier sur les six projets navigateur. Ils ne représentent pas des échecs.

## 4. Installations sandbox avancées

Une stack d’extension isolée est disponible sur `http://127.0.0.1:8090`, avec volumes Docker distincts, baseline Reddit privée et les plugins bbPress, BuddyPress et Elementor aux versions contrôlées. Trois pages publiées réellement ont été testées : une page Gutenberg utilisant `<!-- wp:swiftboard/hot-topics /-->`, une page shortcode utilisant `[swiftboard_block name="hot-topics"]` et une page Elementor contenant le widget `swiftboard_hot-topics`. Le rendu serveur, les captures, l’axe et la CSP stricte sont validés.

Une seconde stack multisite isolée est disponible sur `http://127.0.0.1:8091`. Elle contient un réseau en sous-répertoires, le site principal et le sous-site `/community/`. Le thème SwiftBoard est activé sur les deux sites, les assets du sous-site sont servis en HTTP 200 et les deux pages passent le test navigateur axe/runtime. Les règles Apache multisite de recette sont locales à cette stack et ne sont pas incluses automatiquement dans le ZIP du thème.

Le contrat OAuth est validé sans fournisseur externe : GitHub non configuré renvoie l’erreur attendue, le callback sans state renvoie 403, le challenge Google crée un state lié au navigateur, et la vérification d’un faux token est rejetée lorsque le Client ID n’est pas configuré. Une authentification réelle Google/GitHub/Facebook nécessite des credentials de développement, une redirect URI autorisée et une décision explicite avant toute redirection externe.

## 5. Preuves fonctionnelles et import

Le pipeline local conserve **10 forums, 40 sujets, 160 réponses et 15 membres** après import corrigé. Deux réponses légitimement identiques du sujet 26 sont désormais distinguées par leur ordre source. Un second import sans reset reste à 160 grâce à l’idempotence par clé stable. Les assets locaux utilisent HTTPS.

Le compte standard a publié une réponse réelle depuis le formulaire bbPress. Le compte VIP a affiché son profil, son grade et ses onglets. La baseline arabe a été exécutée sur une base séparée avec `lang="ar"`, `dir="rtl"` et sans débordement horizontal. Le SSE reste opt-in et a été restauré automatiquement après sa mesure.

## 6. Réserves restantes

La conformité complète au CDC v3 reste conditionnelle aux points suivants :

1. Les critères détaillés des Lots 4 à 9 doivent être rejoués et signés lot par lot ; les modules existants ne suffisent pas à eux seuls comme preuve de conformité.
2. OAuth fournisseur réel n’est pas démontré sans credentials de développement fournis par l’utilisateur. Le contrat local ne doit pas être présenté comme une connexion réelle.
3. La recette multisite, Elementor et Gutenberg est validée dans la sandbox dédiée ; elle doit être rejouée sur l’infrastructure de staging réelle avec son cache, son CDN, son PHP-FPM, ses règles Apache/Nginx et ses extensions.
4. Le p95 SSE de 37,8 ms est un résultat local et ne constitue pas une garantie de production ; il doit être remesuré sur staging.
5. Les mots de passe, cookies, dumps SQL et logs privés restent exclus du dépôt public et du package remis.

## 7. Package et traçabilité

Le package installable final `deliverables/swiftboard-premium-v11.0.6-senior-extensions.zip` contient uniquement le thème validé sous la forme `swiftboard/`, avec 309 fichiers. Son SHA-256 est `b7e8c0d956ebbc1bee67cdbf95b0d1783aa1f9194c5ef5c6b12cf8e3755ea4f9`. L’archive a été testée par `unzip -t`.

Les nouveaux fichiers de recette avancée sont également synchronisés au dépôt public après audit de secrets et commit séparé.

Les preuves locales sont dans `reports/extensions/`, `reports/multisite/`, `reports/oauth/` et les suites Playwright correspondantes. Le plan d’extension est documenté dans `reports/sandbox-extension-plan.md`. Les snapshots SQL et les volumes Docker restent privés.

La procédure staging recommandée est : sauvegarde vérifiée, installation du ZIP sur une copie staging, activation de bbPress et des extensions prévues, correction des règles multisite si nécessaire, purge des caches, recette stricte, mesure Lighthouse/SSE, puis décision de promotion ou rollback.

## 8. Conclusion senior

**Verdict : PASS pour staging contrôlé ; NO-GO pour production immédiate ; conformité CDC complète non déclarée.** Les intégrations réelles demandées sont maintenant installées et testées dans la sandbox. La raison détaillée du statut non audité des Lots 4 à 9, lot par lot, est publiée dans [`AUDIT-LOTS-4-9-STATUS.md`](AUDIT-LOTS-4-9-STATUS.md). La prochaine étape est d’exécuter cette matrice exigence par exigence dès que le CDC v3 détaillé est disponible dans un format exploitable.
