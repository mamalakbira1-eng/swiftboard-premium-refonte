# H1 login — correctif vérifié staging

Après installation de `swiftboard-v11.0.6-senior-staging-a11y1.zip`, une navigation fraîche vers `/wp-login.php?sbqa=a11y1` confirme :

| Élément | Résultat |
|---|---|
| H1 cœur « Se connecter » | `display: block`, visible pour lecteur d’écran |
| H1 `.sb-login-sitename` « ocutus » | `display: block`, visible |
| H1 logo WordPress masqué | `display: none`, attendu car doublon de branding |
| Feuille CSS | `login.css?ver=11.0.6-a11y1` |

Le défaut `page-has-heading-one` observé sur Firefox/Chromium tablet/large est corrigé sans masquer le titre sémantique.
