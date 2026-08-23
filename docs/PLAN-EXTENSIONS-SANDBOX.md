# Plan d’extension sandbox — SwiftBoard

## Décision d’architecture

Les extensions seront installées dans une copie de la stack Docker locale existante. La base actuellement validée ne sera pas modifiée directement : elle sera clonée vers un environnement d’extension dédié, avec un snapshot SQL avant chaque lot et un rollback automatique après les tests destructifs.

| Périmètre | État constaté dans la source | Validation sandbox prévue |
|---|---|---|
| Lots 4 à 6 | Fonctionnalités métier déjà présentes par modules, mais critères exacts du CDC v3 non recopiés dans les fichiers locaux | Cartographier chaque hook, endpoint et template avant toute modification ; ajouter tests ciblés |
| Lot 7 | Module de rétention des données présent dans `inc/retention-donnees.php` | Vérifier purge, permissions, mode simulation et absence de suppression hors périmètre |
| Lot 8 | SEO présent dans `inc/seo.php`, avec marqueur de title tag | Vérifier title, canonical, robots et rendu home/forum/topic |
| Lot 9 | À confirmer après lecture du CDC v3 ; ne pas l’inventer à partir des seuls marqueurs source | Bloquer l’implémentation jusqu’à critère explicite et testable |
| Gutenberg | Shortcode `[swiftboard_block name="hot-topics"]` et enregistrement associé présents | Créer une vraie page WordPress avec le shortcode, publier, rendre et tester le HTML |
| Elementor | Catégorie et classes de widget présentes dans `inc/elementor-widgets.php` | Activer Elementor dans l’environnement isolé, créer une vraie page avec le widget et contrôler le rendu |
| Multisite | `inc/multisite-tables.php` présent, mais aucune preuve de sous-site | Créer une installation multisite dédiée, tester tables, activation thème et rendu sur sous-site |
| OAuth | Routes GitHub/Facebook et configuration UI présentes ; aucun credential réel disponible | Tester d’abord le contrat désactivé/non configuré et les routes d’erreur ; OAuth fournisseur réel seulement avec Client ID/Secret et redirect URI fournis |

## Limite importante

La sandbox peut valider les intégrations WordPress, les routes, les widgets et les comportements de sécurité. Elle ne peut pas prouver une authentification OAuth fournisseur réelle sans identifiants de développement appartenant à l’utilisateur. Cette partie sera donc séparée en contrat simulé local, puis test réel facultatif.

## Séquence senior

La séquence est : snapshot, activation d’un seul périmètre, test ciblé, test strict global, capture PNG/JSON, journal PHP/console, rollback, puis commit isolé. Aucun changement ne sera envoyé vers le staging utilisateur pendant cette phase.
