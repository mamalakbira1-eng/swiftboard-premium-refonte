# Matrice Chromium staging — r2

Après déploiement du ZIP `a11y1`, la matrice Chromium a produit **8 tests passés sur 12**.

| Groupe | Résultat |
|---|---|
| L4 — quatre viewports | **4/4 PASS** |
| L9 — quatre viewports, dont axe login et onboarding | **4/4 PASS** |
| L5–L8 — quatre viewports | **0/4 finalisés** |

Les quatre échecs L5–L8 ne signalent ni axe ni assertion fonctionnelle : les navigations `networkidle` vers le profil ou l’inscription dépassent le timeout global de 45 secondes sur le staging CDN. Le timeout de ce scénario sera porté à 120 secondes, comme L9, sans supprimer d’assertion.
