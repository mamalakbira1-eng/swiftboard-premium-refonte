<?php
/**
 * SwiftBoard — Bouton « Rejoindre » de carte (parite Reddit).
 *
 * Chaque carte de feed porte un bouton d'adhesion aligne a droite, comme sur
 * Reddit. Il s'appuie sur l'abonnement natif bbPress : le nonce, la
 * redirection et la persistance sont geres par le coeur du plugin. On evite
 * ainsi un bouton purement decoratif qui ne persisterait rien.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Affiche le bouton « Rejoindre » pour un forum donne.
 *
 * Ne rend rien si l'utilisateur est deconnecte, si bbPress est absent ou si
 * les abonnements sont desactives : dans ces cas Reddit lui-meme n'affiche
 * pas d'action d'adhesion exploitable.
 *
 * @param int $forum_id Identifiant du forum concerne.
 * @return void
 */
function swiftboard_render_join_button( $forum_id ) {
	$forum_id = (int) $forum_id;

	if (
		! $forum_id
		|| ! is_user_logged_in()
		|| ! function_exists( 'bbp_is_subscriptions_active' )
		|| ! bbp_is_subscriptions_active()
		|| ! function_exists( 'bbp_get_forum_subscription_link' )
	) {
		return;
	}

	$est_abonne = function_exists( 'bbp_is_user_subscribed_to_forum' )
		&& bbp_is_user_subscribed_to_forum( get_current_user_id(), $forum_id );

	$lien = bbp_get_forum_subscription_link(
		array(
			'forum_id'    => $forum_id,
			'before'      => '',
			'after'       => '',
			'subscribe'   => esc_html__( 'Rejoindre', 'swiftboard' ),
			'unsubscribe' => esc_html__( 'Rejoint', 'swiftboard' ),
		)
	);

	if ( ! $lien ) {
		return;
	}

	printf(
		'<span class="sb-r-join-wrap%s">%s</span>',
		$est_abonne ? ' is-joined' : '',
		wp_kses_post( $lien )
	);
}
