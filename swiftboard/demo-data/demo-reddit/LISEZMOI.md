# Jeu de démonstration « Communautés » — SwiftBoard

Contenu prêt à importer : 10 communautés, 15 membres, 40 sujets, 160 réponses.

| Fichier | Lignes | Colonnes |
|---|---|---|
| `forums.csv` | 10 | nom, description |
| `membres.csv` | 15 | identifiant, nom_affiche, email, grade, avatar, karma |
| `sujets.csv` | 40 | id, forum, titre, contenu, auteur, image, upvotes, vues, date |
| `reponses.csv` | 160 | sujet_id, auteur, contenu, upvotes, repond_a, ordre |

## Caractéristiques

- **Grades variés** : rookie, member, pro, moderator, vip — répartis sur les 15 membres
- **Avatars** : chaque membre pointe vers un des 15 avatars du thème (`assets/img/avatars/`)
- **Upvotes** : tous supérieurs à 12, jusqu'à ~4800 pour les sujets, ~1900 pour les réponses
- **Vues** : de 120 à 52 000, toutes différentes
- **Images** : 1 sujet sur 2 porte une image (10 visuels WebP dans `assets/img/sujets/`)
- **Réponses imbriquées** : la colonne `repond_a` référence `ordre` d'une réponse du même sujet (0 = réponse racine)

## Images fournies

10 visuels WebP 1200×675, qualité 86 — `assets/img/sujets/` :
`sujet-01-teletravail` · `02-mealprep` · `03-running` · `04-lecture` · `05-gaming`
`06-voyage` · `07-plantes` · `08-finances` · `09-art` · `10-tech`

## Import

Les CSV sont lisibles par l'importeur du thème. L'ordre à respecter :
forums → membres → sujets → réponses, puis recalcul des compteurs bbPress
(`bbp_update_topic_reply_count`, `bbp_update_forum_topic_count`).
