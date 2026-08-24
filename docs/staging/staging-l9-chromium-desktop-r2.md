# Lot 9 staging — Chromium desktop

Le test réel `cdc-lots-4-9.spec.mjs`, projet `chromium-desktop`, a été relancé contre `https://midnightblue-mantis-446085.hostingersite.com` avec `SB_QA_BUST_QUERY=1`.

Résultat : **PASS**, 1 test passé en 1,4 minute. La couverture comprend les sept pages clés en thèmes clair/sombre, axe, overflow, onboarding, clavier et persistance du thème.

Le premier échec n’était pas une violation produit : la modale de langue apparaissait pendant l’onboarding et interceptait le clic de fermeture. La recette a été durcie pour fermer cette modale via son bouton produit avant de fermer l’onboarding; aucun contrôle d’accessibilité n’a été supprimé.
