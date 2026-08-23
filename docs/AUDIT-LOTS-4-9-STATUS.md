# Statut d’audit CDC — Lots 4 à 9 SwiftBoard

**Date :** 23 août 2026  
**Version :** SwiftBoard 11.0.6  
**Statut :** audit CDC détaillé **non déclaré**, validations techniques partielles réalisées.

## Pourquoi l’audit complet n’a pas été déclaré

Le périmètre exécuté initialement portait sur les Lots 1 à 3 et la remise en conformité senior. La règle de preuve retenue exige qu’un lot ne soit déclaré conforme que si chaque exigence possède une preuve reproductible : scénario navigateur, capture PNG, résultat Playwright, axe/Lighthouse lorsque pertinent, absence ou qualification des erreurs console/PHP, fichiers et diff exacts, et commit associé.

Pour les Lots 4 à 9, la sandbox contenait des modules et des marqueurs de fonctionnalités, mais pas une copie exploitable et non ambiguë des critères détaillés du CDC v3 dans les fichiers versionnés. La présence d’un fichier PHP ou d’un hook ne prouve pas que le comportement respecte chaque critère d’acceptation. Déclarer ces lots conformes aurait donc été une extrapolation, contraire à la règle de preuve.

## Matrice honnête des Lots 4 à 9

| Lot | Indice dans la source | Ce qui a été réellement vérifié | Statut CDC |
|---|---|---|---|
| Lot 4 | Fonctionnalités métier réparties dans plusieurs modules | Runtime strict, commentaire bbPress réel, profil VIP, clavier et intégrations réelles ; pas de rapprochement exigence par exigence | **Non audité** |
| Lot 5 | Fonctionnalités métier et endpoints déjà présents | Régressions anonymes et scénarios métier ciblés ; pas de matrice CDC complète ni preuve dédiée de chaque exigence | **Non audité** |
| Lot 6 | Composants métier présents dans la source | Import Reddit réconcilié à 160 réponses, idempotence, locales et SSE validés ; cela ne constitue pas l’audit complet du Lot 6 | **Non audité** |
| Lot 7 | `inc/retention-donnees.php` présent | Aucun scénario complet versionné démontrant purge, permissions, mode simulation et périmètre de suppression | **Non audité** |
| Lot 8 | `inc/seo.php` présent, notamment le title tag | Lighthouse SEO global validé sur l’accueil, mais pas une recette dédiée title/canonical/robots sur home/forum/topic selon le CDC | **Non audité** |
| Lot 9 | Critères non identifiés de façon fiable dans les documents disponibles | Aucune implémentation ou test ne doit être inventé à partir d’un simple numéro de lot | **Bloqué — critères à fournir** |

## Validations avancées qui ne doivent pas être confondues avec l’audit des Lots 4 à 9

La sandbox a bien validé des intégrations transverses : pages Gutenberg et shortcode réels, widget Elementor réel sous CSP stricte, réseau multisite principal et sous-site, contrat OAuth non configuré et protection d’état, ainsi que la régression primaire 36/36. Ces résultats prouvent la qualité de certains points techniques, mais ils ne transforment pas automatiquement les Lots 4 à 9 en lots conformes.

OAuth fournisseur réel reste distinct du contrat local : il nécessite un Client ID, un Secret et une redirect URI de développement appartenant à l’utilisateur. Le test local ne prétend pas avoir effectué une connexion Google, GitHub ou Facebook.

## Ce qu’un audit complet doit encore produire

Pour chaque Lot 4 à 9, il faut d’abord associer les critères exacts du CDC v3 à des fichiers et comportements observables. Il faut ensuite créer les fixtures nécessaires, exécuter les parcours authentifiés et anonymes, capturer les PNG avant/après, archiver les résultats Playwright/axe/Lighthouse, relever les logs console/PHP, effectuer un test négatif et un rollback lorsque la donnée est mutée, puis créer un commit isolé et mettre à jour la matrice.

La décision actuelle est donc volontairement stricte : **validations techniques avancées PASS ; conformité CDC complète des Lots 4 à 9 non démontrée**. Cette formulation protège le projet contre une fausse déclaration de conformité et indique exactement le travail restant.
