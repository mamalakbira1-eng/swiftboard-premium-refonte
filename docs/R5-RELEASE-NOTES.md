# SwiftBoard r5 — notes de version

## Portée

Cette version rassemble les corrections post-r4 appliquées et vérifiées dans la reproduction Docker locale. Elle ne contient aucun déploiement staging ou production. Le commit exact de livraison sera celui qui porte ce document après validation Git.

## Corrections produit

La migration des tables votes et notifications est idempotente et répare les colonnes historiques attendues. Les transients de rate-limit des votes utilisent une durée en secondes correcte. Les tris feed `hot`, `top` et `rising` utilisent les scores SwiftBoard attendus. L’import de réponses recalcule le score hot et respecte le schéma HTTP courant pour les URLs d’images.

La CSP front reste enforce et stricte : `script-src` n’ajoute pas `unsafe-inline`, Elementor reçoit les nonces nécessaires et le cache HTML est désactivé dans le contexte où il pourrait provoquer une divergence nonce/CSP. Les attributs accessibles des contrôles de mot de passe sont présents. L’activation SSE ne désactive plus le dropdown de notifications ni son chargement à la demande ; les cinq erreurs de flux simulées provoquent un fallback polling contrôlé.

Les assets utilisent une URL correcte en multisite sous-directory. Les tests et fixtures d’intégration couvrent un vrai widget Elementor, un bloc Gutenberg, un shortcode, des comptes QA, le multisite et les notifications SSE.

## Corrections de recette

La suite fonctionnelle ferme la popup de langue avant les interactions sensibles. Elle respecte les intervalles métier bbPress et SwiftBoard au lieu de neutraliser leurs protections. La publication réelle capture le 302 bbPress et le `Location` du reply, puis vérifie le contenu via son permalink exact ; la matrice finale est `48/48 PASS`.

Les scénarios Lot 4–9 ont été durcis contre les faux positifs WebKit : pages isolées, overflow mesuré avant Axe, résultat Axe limité aux violations et routes Lot 9 séparées. Le diff pixel Lot 4 est exécuté sur les douze projets et conserve la provenance de sa baseline dans son résumé.

## Vérification finale

| Contrôle | Résultat |
|---|---:|
| Lot 1 | 12/12 PASS |
| Lots 4–9 consolidés | 36/36 PASS, global 33/33 + WebKit large isolé 3/3 |
| Fonctionnel authentifié | 48/48 PASS |
| Lot 9 authentifié | 24/24 PASS |
| Runtime / strict runtime | 24/24 et 12/12 PASS |
| LTR / RTL | 12/12 et 12/12 PASS |
| Extensions / multisite | 12/12 et 12/12 PASS |
| SSE 20 notifications + fallback | 24/24 PASS local |
| OAuth contractuel | 12/12 PASS |
| Lint PHP / JavaScript | 190 / 39 fichiers PASS |

## Réserves obligatoires

Le staging Hostinger autorisé utilise un sous-domaine sans certificat SSL valide et reste inutilisable pour la recette HTTPS. La CSP publique, SSE public et Lighthouse staging sont donc **BLOCKED**. Le fournisseur OAuth réel est **BLOCKED** sans credentials développeur valides et redirect URI autorisée. Ces réserves ne sont pas transformées en PASS et aucune production n’est touchée.
