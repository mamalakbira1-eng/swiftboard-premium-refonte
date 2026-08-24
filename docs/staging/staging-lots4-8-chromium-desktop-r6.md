# Lots 4–8 staging — PASS Chromium desktop

La sixième exécution réelle contre Hostinger a produit **2 tests passés sur 2** en 1,3 minute :

| Test | Résultat |
|---|---|
| Lot 4 — cartes, tri, pagination et responsive du fil | **PASS** |
| Lots 5 à 8 — thread, profil, forum et auth rendue | **PASS** |

Les préconditions désormais vérifiées sur le staging sont le forum `Finances`, les deux sujets aux slugs CDC, deux fils avec réponses imbriquées, le profil `sbvip` en grade maximal `Général` (clé interne `vip`), les règles du forum et `sbmember`.

La recette inclut maintenant l’attente/fermeture propre de la modale de langue différée et conserve toutes les assertions axe, runtime, thread, profil, règles, inscription et onboarding.
