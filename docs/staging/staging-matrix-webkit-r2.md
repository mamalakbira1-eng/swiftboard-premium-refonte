# Matrice WebKit staging — passe intermédiaire

La passe initiale WebKit a donné **6/12 tests passés** avant les derniers ajustements de recette : les quatre L9 et deux L4 ont franchi les contrôles. Les échecs restants combinaient :

- modale de langue encore visible pendant l’audit de certaines pages sombres, avec son ancien état de contraste mis en évidence par axe ;
- interaction de repli/annulation de réponse sensible au timing WebKit ;
- plusieurs navigations `hcdn` annulées/détachées.

Après modification de la recette, le staging est devenu temporairement non joignable depuis la sandbox : la page du sujet et même la racine ont dépassé 30 secondes au contrôle HTTP. Une nouvelle passe WebKit ne sera interprétée qu’après rétablissement HTTP et devra confirmer ces causes séparément.
