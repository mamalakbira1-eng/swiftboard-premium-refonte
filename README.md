# SwiftBoard — Refonte UX/UI Premium

Ce dépôt contient le thème WordPress **SwiftBoard 11.0.6** et les premiers lots de refonte UX/UI appliqués dans un environnement WordPress réel avec bbPress. La priorité est de préserver les contrats fonctionnels existants — classes `.sb-*`, votes, profils, notifications, OAuth, CSP et intégrations bbPress — tout en améliorant progressivement la hiérarchie visuelle, les surfaces, les transitions et l’accessibilité.

> **Statut :** Lots 1 à 3 appliqués et validés localement sur Chromium, Firefox et WebKit. Aucun accès à un WordPress distant n’a été utilisé pour ces changements.

## Contenu

Le dossier `swiftboard/` contient le thème. La couche `premium-ui.css` est chargée après les feuilles de style historiques afin de limiter les régressions de cascade. Le dossier `qa/` contient la configuration Playwright multi-navigateurs, les audits axe-core, Lighthouse et le diff pixel. Le dossier `scripts/` contient le script de recette qui appelle le pipeline d’import interne de SwiftBoard.

Les dumps SQL, captures de recette et journaux locaux ne sont volontairement pas versionnés. Ils doivent rester dans un espace d’audit privé et ne doivent jamais être publiés avec des identifiants ou des données de site réel.

## Démarrage local

Prérequis : Docker avec Compose v2, Node.js 22 ou supérieur et pnpm.

```bash
cp .env.example .env
pnpm --dir qa install
pnpm --dir qa exec playwright install --with-deps chromium firefox webkit
docker compose -f docker-compose.qa.yml up -d
```

Après le démarrage de WordPress, installer et activer bbPress puis activer le thème `swiftboard`. Pour le contenu de démonstration, utiliser l’importeur interne du thème et documenter séparément tout réglage appliqué manuellement. Ne pas mélanger les baselines Reddit, FR, EN et AR dans une seule base.

## Validation QA

Définir `SWIFTBOARD_BASE_URL` vers l’URL de recette, puis exécuter :

```bash
SWIFTBOARD_BASE_URL=http://localhost:8088 pnpm --dir qa exec playwright test
pnpm --dir qa exec lighthouse http://localhost:8088/ --output=json --output-path=reports/lighthouse.json
```

Les scénarios couvrent les modes clair/sombre, les viewports mobile/tablette/desktop/large desktop, les moteurs Chromium/Firefox/WebKit, l’accueil, les pages bbPress, le profil, la recherche et les interactions de header. Chaque lot doit archiver les captures, le rapport axe-core, le rapport Lighthouse, les erreurs console et le diff visuel avant/après.

## Lots versionnés

| Commit | Lot | Résultat |
|---|---|---|
| `be67f00` | Baseline du thème 11.0.6 | Archive source versionnée |
| `6ca6835` | Fondations tokens, contraste, ombres et motion | Validation axe-core et persistance du thème |
| `9378080` | Cartes, boutons, votes, pills et champs | Validation multi-navigateurs |
| `dfa2328` | Header, recherche, navigation et surfaces | Validation pages/interactions |

## Sécurité et déploiement

Ne pas committer de fichier `.env`, de dump SQL, de zip contenant des données de recette, de clé OAuth, de mot de passe ou de token. Tester d’abord dans un staging restaurable. La production ne doit être modifiée qu’après validation du diff, sauvegarde confirmée et accord explicite du propriétaire du site.

## Licence

Consulter `swiftboard/license.txt` et les fichiers de licence inclus dans l’archive source.
