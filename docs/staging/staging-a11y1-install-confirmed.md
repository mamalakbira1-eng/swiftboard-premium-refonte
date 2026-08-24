# Installation staging — a11y1

WordPress confirme l’installation de `swiftboard-v11.0.6-senior-staging-a11y1.zip` avec le message « Le thème a bien été mis à jour ».

Cette itération contient : le H1 login explicitement affiché, une version CSS login `11.0.6-a11y1` pour invalider le cache d’asset, les garde-fous de contraste déjà installés et les corrections de robustesse de la recette Playwright.

La purge du cache SwiftBoard a ensuite été exécutée. Une vérification fraîche de la route publique `/wp-login.php` et du CSS versionné est requise avant relance de la matrice.
