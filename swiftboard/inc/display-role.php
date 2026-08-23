<?php
/**
 * SwiftBoard — Display Role Filter
 * @package SwiftBoard
 * @since 7.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// AFFICHAGE DU ROLE bbPRESS — un seul axe de statut par utilisateur
// ============================================================================
/**
 * Masque le role bbPress quand il n'apporte aucune information de confiance.
 *
 * PROBLEME (releve en revue visuelle)
 * -----------------------------------
 * Une reponse affichait deux badges cote a cote pour la meme personne :
 *   « MEMBER »  = role de permission bbPress
 *   « ROOKIE »  = grade de reputation SwiftBoard
 * L'utilisateur ne peut pas deviner que ce sont deux axes differents ; il en
 * conclut que le site est incoherent. Or la credibilite du systeme de grades
 * est le moteur d'engagement du forum : des badges qui se contredisent ne
 * motivent plus personne.
 *
 * Pire, « Member » est la valeur de REPLI de bbPress
 * (bbp_get_user_display_role() : « No role found so default to generic
 * Member »). Verifie sur cette instance : l'administrateur a un bbp_role VIDE
 * et affiche pourtant « Member ». Le badge affichait donc frequemment une
 * valeur qui ne veut rien dire.
 *
 * REGLE APPLIQUEE
 * ---------------
 * Le grade SwiftBoard est le seul badge par defaut. Le role bbPress ne
 * s'affiche que s'il signale une AUTORITE reelle (Keymaster, Moderator, ou
 * administrateur WordPress) — la seule information que le grade ne porte pas
 * et qui est utile au lecteur pour juger un message.
 *
 * Les roles sans valeur informative (Participant, Spectator, Member) sont
 * masques. « Blocked » et « Inactive » sont CONSERVES : ce sont des signaux
 * de moderation utiles.
 *
 * Implementation par filtre, sans surcharge de template : rien a re-merger
 * lors d'une mise a jour de bbPress.
 *
 * @param string $role    Libelle du role calcule par bbPress.
 * @param int    $user_id ID de l'utilisateur.
 * @return string Libelle a afficher, ou chaine vide pour masquer le badge.
 */
function swiftboard_filter_display_role( $role, $user_id = 0 ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return $role;
	}

	// Signaux de moderation : toujours affiches.
	if ( function_exists( 'bbp_is_user_inactive' ) && bbp_is_user_inactive( $user_id ) ) {
		return $role;
	}

	$bbp_role = function_exists( 'bbp_get_user_role' ) ? bbp_get_user_role( $user_id ) : '';

	$roles_d_autorite = array();
	foreach ( array( 'bbp_get_keymaster_role', 'bbp_get_moderator_role', 'bbp_get_blocked_role' ) as $fn ) {
		if ( function_exists( $fn ) ) {
			$roles_d_autorite[] = $fn();
		}
	}

	if ( $bbp_role && in_array( $bbp_role, $roles_d_autorite, true ) ) {
		return $role;
	}

	// Un administrateur WordPress sans role bbPress explicite reste une
	// autorite : sans ce cas, l'admin du site n'aurait aucun badge.
	//
	// On ne renvoie PAS $role tel quel : quand bbp_role est vide, bbPress
	// retombe sur le libelle generique « Member », ce qui est trompeur pour un
	// administrateur (constate sur cette instance : admin a un bbp_role vide
	// et s'affichait « Member »). On rend alors le libelle Keymaster, qui
	// decrit reellement son autorite.
	if ( user_can( $user_id, 'manage_options' ) ) {
		if ( ! $bbp_role && function_exists( 'bbp_get_dynamic_role_name' ) ) {
			$keymaster = bbp_get_dynamic_role_name( bbp_get_keymaster_role() );
			if ( $keymaster ) {
				return $keymaster;
			}
		}
		return $role;
	}

	// Aucune autorite : le grade SwiftBoard suffit.
	return '';
}
add_filter( 'bbp_get_user_display_role', 'swiftboard_filter_display_role', 10, 2 );

// C.4 : le dequeue de global-styles doit tourner APRES l'enfilement du noyau
// (wp_enqueue_global_styles se hook a priorite 10 ; on passe en 120 pour
// garantir la suppression effective du bloc CSS inline global).
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	},
	120
);

// BUG 6 : WordPress core peut re-imprimer global-styles dans wp_footer via
// wp_print_footer_scripts(). On dequeue aussi au footer (priorite 0, avant
// l'impression) pour garantir qu'aucun <style> du core n'apparaisse dans le <body>.
add_action(
	'wp_print_footer_scripts',
	function () {
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
	},
	0
);


