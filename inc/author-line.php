<?php
/**
 * SwiftBoard — Ligne auteur des cartes de feed.
 *
 * Reddit affiche « [avatar] u/pseudo » et rien d'autre. Le theme utilisait
 * trois formulations differentes selon le template (« Par X », « Posté par
 * X », avatar present ou non), ce qui donnait un feed incoherent. Cette
 * fonction unique fixe le format partout.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Affiche « [avatar] u/pseudo » pour un auteur donne.
 *
 * @param int    $user_id  Identifiant de l'auteur. 0 si inconnu.
 * @param string $repli    Nom a utiliser quand l'utilisateur n'existe plus.
 * @param int    $taille   Taille de l'avatar en pixels.
 * @return void
 */
function swiftboard_render_author_line( $user_id, $repli = '', $taille = 20 ) {
	$user_id = (int) $user_id;
	$user    = $user_id ? get_userdata( $user_id ) : false;

	// Un contenu importe ou dont l'auteur a ete supprime a post_author = 0.
	// On affiche alors un pseudo neutre plutot qu'un « Par » orphelin.
	$pseudo = $user ? $user->user_nicename : ( $repli ? $repli : __( 'utilisateur-supprimé', 'swiftboard' ) );
	$profil = ( $user && function_exists( 'bbp_get_user_profile_url' ) )
		? bbp_get_user_profile_url( $user_id )
		: '';

	echo '<span class="sb-post-author">';

	if ( $user_id && function_exists( 'swiftboard_get_avatar' ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- balise <img> deja echappee par le helper.
		echo swiftboard_get_avatar( $user_id, $taille );
	}

	$libelle = '<span class="sb-u-prefix">u/</span>' . esc_html( $pseudo );

	if ( $profil ) {
		printf( '<a class="sb-author-link" href="%s">%s</a>', esc_url( $profil ), $libelle ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $libelle est echappe ci-dessus.
	} else {
		printf( '<span class="sb-author-link">%s</span>', $libelle ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem.
	}

	echo '</span>';
}
