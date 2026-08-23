<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quota d'upload — réservation atomique et libération.
 *
 * Extrait de `image-upload.php`, qui dépassait 500 lignes (règle
 * d'architecture du thème : aucun module au-delà).
 *
 * POURQUOI UNE RÉSERVATION ATOMIQUE
 * ---------------------------------
 * Compter les lignes déjà enregistrées ne suffit pas : la ligne n'est insérée
 * qu'APRÈS la conversion de l'image (~300 ms). Pendant cette fenêtre, N
 * requêtes concurrentes lisent la même valeur et passent toutes le contrôle.
 * Mesuré sur nginx + PHP-FPM : 8 envois simultanés → 8 images pour un quota
 * de 2. Le défaut est invisible sur `php -S`, qui sérialise les requêtes.
 *
 * `add_option()` s'appuie sur la contrainte UNIQUE de `wp_options.option_name` :
 * l'insertion réussit pour UNE SEULE requête, les autres échouent
 * immédiatement. C'est le seul verrou réellement atomique disponible sans
 * dépendance externe.
 *
 * @package SwiftBoard
 */
/**
 * Relâche un créneau de quota réservé par add_option().
 *
 * RÉGRESSION CORRIGÉE — la réservation atomique (correctif de la course TOCTOU)
 * posait un jeton AVANT de valider le fichier, et aucun chemin d'échec ne le
 * relâchait. Neuf sorties en erreur existent après la réservation : fichier
 * corrompu, faux type MIME, dimensions hors bornes, doublon, quota total,
 * publications insuffisantes, trop de rejets…
 *
 * Conséquence mesurée : un membre qui se trompe deux fois de format épuisait
 * son quota quotidien sans avoir publié une seule image, et restait bloqué
 * 24 heures.
 *
 * La libération est CIBLÉE sur le créneau réellement obtenu par CETTE requête.
 * Relâcher tous les créneaux rouvrirait la course : deux envois simultanés
 * obtiendraient le même.
 *
 * @param string $cle_jour Préfixe du jour (`swiftboard_daily_upload_<uid>_<date>`).
 * @param int    $place    Index du créneau à libérer.
 * @return void
 */
function swiftboard_liberer_place_upload( $cle_jour, $place ) {
	if ( $cle_jour === '' || $place < 0 ) {
		return;
	}
	delete_option( $cle_jour . '_slot_' . (int) $place );
}
