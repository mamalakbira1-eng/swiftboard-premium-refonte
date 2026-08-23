=== SwiftBoard ===
Contributors: swiftboard
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 11.0.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thème WordPress de forum communautaire compatible bbPress.

== Description ==
SwiftBoard fournit une interface forum responsive, le support RTL, le mode clair/nuit, des contrôles REST protégés et des outils d’importation et de migration documentés.

Le vote mutateur certifié exige une session authentifiée, une capability adaptée et un nonce REST valide. Un visiteur reçoit 401 et un utilisateur authentifié sans nonce valide reçoit 403.

== Installation ==
1. Téléverser le dossier SwiftBoard dans wp-content/themes ou installer l’archive depuis Apparence > Thèmes.
2. Activer bbPress 2.6.14 ou une version compatible.
3. Activer SwiftBoard depuis Apparence > Thèmes.
4. Vérifier la configuration du cache, des emails, du cron et des sauvegardes avant l’usage en production.

== Sécurité et données ==
Le changement de thème ne supprime pas automatiquement les données SwiftBoard. Toute suppression RGPD doit être explicitement confirmée par un administrateur après export et sauvegarde. Les contenus bbPress ne sont pas supprimés par le nettoyage SwiftBoard.

== Compatibilité ==
La validation locale renforcée a été exécutée avec PHP 8.4 et WordPress 7.0.3 dans l’environnement documenté. La validation de production exige encore la matrice de staging, la base cible, le cache authentifié, le SMTP, le rollback et le sign-off humain.

== Documentation ==
Consulter README.md, CHANGELOG.md et CAHIER-DES-CHARGES-POST-ZAP-SWIFTBOARD-11.0.4.md dans le dossier développeur.
