<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Routes REST des actions membre (sauvegarder, masquer, suivre).
 *
 * EXI-ARCH-01 : extrait de inc/user-content-actions.php. Module FRONT : ces
 * routes sont appelees depuis les pages publiques par un membre connecte.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
/**
 * swiftboard_rest_user_action().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_rest_user_action( WP_REST_Request $req ) {
	// E-3 fix: Verify nonce
	$nonce = $req->get_header( 'X-WP-Nonce' );
	if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'rest_cookie_invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
	}
	$user_id  = get_current_user_id();
	$topic_id = (int) $req->get_param( 'topic_id' );
	$action   = sanitize_text_field( $req->get_param( 'action' ) );
	$add      = (bool) $req->get_param( 'add' );

	if ( ! in_array( $action, array( 'saved', 'hidden', 'followed' ), true ) ) {
		return new WP_Error( 'invalid_action', 'Action invalide.', array( 'status' => 400 ) );
	}
	if ( ! get_post( $topic_id ) ) {
		return new WP_Error( 'not_found', 'Topic introuvable.', array( 'status' => 404 ) );
	}

	swiftboard_set_user_action( $user_id, $action, $topic_id, $add );
	return new WP_REST_Response(
		array(
			'ok'       => true,
			'action'   => $action,
			'topic_id' => $topic_id,
			'added'    => $add,
		),
		200
	);
}

/**
 * swiftboard_rest_get_user_actions().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_rest_get_user_actions( WP_REST_Request $req ) {
	$user_id = get_current_user_id();
	$action  = sanitize_text_field( $req->get_param( 'action' ) );
	if ( ! in_array( $action, array( 'saved', 'hidden', 'followed' ), true ) ) {
		return new WP_Error( 'invalid_action', 'Action invalide.', array( 'status' => 400 ) );
	}
	if ( $req->get_param( 'ids_only' ) ) {
		$ids = $action === 'saved' ? swiftboard_get_saved_topics( $user_id )
			: ( $action === 'hidden' ? swiftboard_get_hidden_topics( $user_id ) : swiftboard_get_followed_topics( $user_id ) );
		return new WP_REST_Response( array( 'topics' => array_map( 'intval', (array) $ids ) ), 200 );
	}
	$list = swiftboard_get_user_topics_list( $user_id, $action );
	return new WP_REST_Response( array( 'topics' => $list ), 200 );
}

/**
 * Récupère la liste des topics pour une action donnée avec détails.
 *
 * @param int   $user_id Identifiant de l'utilisateur.
 * @param mixed $action  À documenter.
 * @return mixed
 */
function swiftboard_get_user_topics_list( $user_id, $action ) {
	$ids = $action === 'saved' ? swiftboard_get_saved_topics( $user_id )
			: ( $action === 'hidden' ? swiftboard_get_hidden_topics( $user_id )
			: swiftboard_get_followed_topics( $user_id ) );
	if ( empty( $ids ) ) {
		return array();
	}

	$q   = new WP_Query(
		array(
			'post_type'      => 'topic',
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'posts_per_page' => 50,
			'orderby'        => 'post__in',
		)
	);
	$out = array();
	while ( $q->have_posts() ) {
		$q->the_post();
		$topic_id = get_the_ID();
		$out[]    = array(
			'id'          => $topic_id,
			'title'       => get_the_title(),
			'url'         => get_permalink(),
			'author_name' => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $topic_id ) ),
			'date'        => get_the_date( 'c' ),
			'votes'       => function_exists( 'swiftboard_get_vote_count' ) ? swiftboard_get_vote_count( $topic_id ) : 0,
			'replies'     => function_exists( 'bbp_get_topic_reply_count' ) ? bbp_get_topic_reply_count( $topic_id, true ) : 0,
		);
	}
	wp_reset_postdata();
	return $out;
}
