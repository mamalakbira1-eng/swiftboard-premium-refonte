# État de l’environnement local SwiftBoard

## Statut

**Environnement réel opérationnel et prêt pour la suite de la recette.** Aucun fichier n’a été modifié sur le WordPress de l’utilisateur.

## Stack installée

| Élément | État |
|---|---|
| WordPress | 6.8.3, installé dans Docker |
| PHP | 8.3.28 dans le conteneur Apache |
| Base de données | MariaDB 10.11 |
| WP-CLI | 2.12.0 |
| Thème | SwiftBoard 11.0.6, actif |
| bbPress | 2.6.14, actif |
| Playwright | 1.62.1 |
| Moteurs | Chromium, Firefox et WebKit installés |
| axe-core | `@axe-core/playwright` installé |
| Lighthouse | Lighthouse 13.4.1 et LHCI installés |
| Diff visuel | `pixelmatch` + `pngjs` installés |

URL temporaire de recette locale : `https://8088-iusiaz3ltza0hnfunobhr-de99ba16.us5.manus.computer`

## Données installées

La démo Reddit a été importée par le pipeline interne du thème après assemblage contrôlé des CSV fournis. Le résultat constaté est de 10 forums, 40 sujets, 159 réponses publiées et 15 membres importés, en plus des comptes de recette locaux. Le journal complet est conservé dans `reports/demo-reddit-import.json`.

La différence entre les 160 lignes annoncées par le fichier source et les 159 réponses publiées doit être auditée avant de déclarer la baseline de contenu parfaite. Elle n’empêche pas les tests UI actuels, mais elle reste explicitement documentée. Des snapshots SQL séparés FR, EN et AR ont également été produits. Pour le scénario RTL, WordPress accepte le code langue `ar` et rend bien `<html dir="rtl" lang="ar">`; `ar_SA` n’est pas un code de pack disponible dans cette installation et ne doit donc pas être prescrit comme valeur obligatoire.

## Lots appliqués

Le dépôt Git de travail se trouve dans `source/`.

| Lot | Modification | Commit | Validation |
|---|---|---|---|
| Initial | Archive source v11.0.6 versionnée | `be67f00` | Source intacte archivie |
| Lot 1 | Token danger contrasté, ombres premium, tokens motion, transitions | `6ca6835` | 6/6 tests multi-navigateurs passés |
| Lot 2 | Couche `premium-ui.css` pour cartes, boutons, votes, pills et champs | `9378080` | 6/6 tests multi-navigateurs passés |
| Lot 3 | Header, recherche, navigation et scrollbar premium | `dfa2328` | 12/12 scénarios pages/interactions passés |

## Résultats de recette

Les tests Playwright ont passé sur Chromium, Firefox et WebKit. Les viewports couverts sont mobile, tablette, desktop et large desktop pour les scénarios de baseline. Les pages forum, sujet, profil, recherche et les interactions de header ont également été chargées sur les six projets navigateur.

La baseline initiale avait détecté un défaut axe-core sérieux sur le CTA d’inscription desktop : texte blanc sur fond `#ff3d00`, contraste mesuré à 3,54. Le Lot 1 a remplacé cette teinte par `#b42318`. Les tests stricts axe-core du Lot 1 passent maintenant sur les six projets navigateur.

Lighthouse sur l’accueil après les lots donne les scores suivants : Performance 100, Accessibilité 100, Bonnes pratiques 92 et SEO 100. Le diff pixel desktop est de 1,16 % en clair et 1,29 % en sombre ; ces écarts sont cohérents avec les changements ciblés de surface, d’ombre et de couleur.

## Limites actuelles

Les snapshots SQL Reddit, FR, EN et AR sont montés séparément. Un smoke test BuddyPress isolé a rendu `/`, `/members/` et `/activity/` en HTTP 200 avec BuddyPress 14.5.2 actif, puis la base primaire a été restaurée. Un smoke test Elementor avec Elementor 4.2.3 actif a rendu l’accueil, un forum et un sujet en HTTP 200, puis la base primaire a également été restaurée. Le scénario multisite et le rendu réel d’un bloc Gutenberg/Elementor restent à approfondir avant la validation complète du CDC.

## Prochaine étape

La suite logique est d’auditer la réponse manquante, d’approfondir multisite et le rendu de bloc, puis de poursuivre les lots UI sur le staging local ou sur une copie de recette utilisateur. L’accès au WordPress utilisateur ne doit viser qu’un staging ou une copie de recette, jamais la production directement.
