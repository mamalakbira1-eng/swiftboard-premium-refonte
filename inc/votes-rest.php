<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Routes REST du systeme de vote.
 *
 * EXI-ARCH-01 : extrait de inc/votes-social.php (700 lignes). Module FRONT :
 * ces routes servent les votes des visiteurs ; enregistrees depuis un module
 * admin-only, elles renverraient 404 a tout appel, meme authentifie.
 *
 * La logique metier — rate limiting par grade, bascule du vote, recomptage —
 * reste dans inc/votes-social.php. Ce fichier ne porte que la couche HTTP.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
/**
 * @return void
 */
function swiftboard_register_vote_routes() {
	register_rest_route(
		'swiftboard/v1',
		'/vote',
		array(
			array(
				'methods'             => 'POST',
				'callback'            => 'swiftboard_rest_cast_vote',
				'permission_callback' => 'swiftboard_rest_vote_mutation_permission',
				'args'                => array(
					'post_id'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'vote_type' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'up', 'down' ),
					),
				),
			),
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_rest_get_vote',
				'permission_callback' => 'swiftboard_rest_public_permission',
				'args'                => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => 'swiftboard_rest_delete_vote',
				'permission_callback' => 'swiftboard_rest_vote_mutation_permission',
				'args'                => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
		)
	);

	// Endpoint batch : récupérer les votes de l'utilisateur courant sur une liste de posts
	register_rest_route(
		'swiftboard/v1',
		'/votes/mine',
		array(
			'methods'             => 'GET',
			'callback'            => 'swiftboard_rest_get_my_votes',
				'permission_callback' => 'swiftboard_rest_authenticated_vote_permission',
			'args'                => array(
				'post_ids' => array(
					'type'     => 'string',
					'required' => false,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'swiftboard_register_vote_routes' );

/**
 * Toute mutation de vote doit présenter un nonce REST valide.
 * Les lectures publiques restent accessibles sans authentification.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return bool|WP_Error
 */
function swiftboard_rest_vote_mutation_permission( WP_REST_Request $request ) {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'rest_not_logged_in', 'Authentication is required to vote.', array( 'status' => 401 ) );
	}
	if ( ! current_user_can( 'read' ) ) {
		return new WP_Error( 'rest_cannot_vote', 'The current user cannot vote.', array( 'status' => 403 ) );
	}
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'rest_cookie_invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Only authenticated users may access their personal vote state.
 *
 * @return bool|WP_Error
 */
function swiftboard_rest_authenticated_vote_permission() {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'rest_not_logged_in', 'Authentication is required.', array( 'status' => 401 ) );
	}
	return current_user_can( 'read' )
		? true
		: new WP_Error( 'rest_cannot_read_votes', 'The current user cannot read personal votes.', array( 'status' => 403 ) );
}
/**
 * POST /swiftboard/v1/vote
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return mixed
 */
function swiftboard_rest_cast_vote( WP_REST_Request $request ) {
	$post_id   = (int) $request->get_param( 'post_id' );
	$vote_type = sanitize_text_field( $request->get_param( 'vote_type' ) );

	$result = swiftboard_cast_vote( $post_id, $vote_type );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return new WP_REST_Response( $result, 200 );
}
/**
 * GET /swiftboard/v1/vote?post_id=123
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_rest_get_vote( WP_REST_Request $request ) {
	$post_id = (int) $request->get_param( 'post_id' );
	if ( ! $post_id ) {
		return new WP_Error( 'missing_post_id', 'post_id requis.', array( 'status' => 400 ) );
	}
	$counts  = swiftboard_get_post_vote_score( $post_id );
	$my_vote = swiftboard_get_my_vote( $post_id );

	return new WP_REST_Response(
		array(
			'post_id'   => $post_id,
			'score'     => $counts['score'],
			// `formatted` DOIT figurer ici, comme dans la réponse du POST.
			//
			// C'est cette route que `votes.js` interroge à l'initialisation de la
			// page (votes.js:171). Le JS lit `data.formatted` et ne retombe sur
			// `data.score` qu'à défaut. Comme `formatted` était absent, le repli
			// s'activait systématiquement :
			//
			// score en base              : -1
			// rendu par le serveur (PHP) :  0   (règle anti-troll)
			// après exécution du JS      : -1   ← le score change à l'écran
			//
			// Le correctif avait été appliqué côté JS mais pas au contrat de
			// l'API : GET et POST doivent renvoyer les mêmes champs, sans quoi le
			// repli réintroduit l'incohérence qu'on croyait corrigée.
			//
			// Mesuré au navigateur (qa/verif-dev/verif-navigateur-dev.js).
			'formatted' => function_exists( 'swiftboard_format_count' )
				? swiftboard_format_count( $counts['score'] )
				: (string) $counts['score'],
			'up'        => $counts['up'],
			'down'      => $counts['down'],
			'my_vote'   => $my_vote, // vote courant : up, down ou null
		),
		200
	);
}
/**
 * DELETE /swiftboard/v1/vote?post_id=123
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_rest_delete_vote( WP_REST_Request $request ) {
	global $wpdb;
	$post_id = (int) $request->get_param( 'post_id' );
	if ( ! $post_id ) {
		return new WP_Error( 'missing_post_id', 'post_id requis.', array( 'status' => 400 ) );
	}

	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	$hash    = swiftboard_get_voter_hash();
	$table   = swiftboard_table( 'votes' );

	$wpdb->delete(
		$table,
		array(
			'post_id'    => $post_id,
			'user_id'    => $user_id,
			'voter_hash' => $hash,
		),
		array( '%d', '%d', '%s' )
	);

	$counts = swiftboard_recount_post_votes( $post_id );

	return new WP_REST_Response(
		array(
			'action'    => 'removed',
			'my_vote'   => null,
			'score'     => $counts['score'],
			'formatted' => function_exists( 'swiftboard_format_count' ) ? swiftboard_format_count( $counts['score'] ) : $counts['score'],
			'up'        => $counts['up'],
			'down'      => $counts['down'],
		),
		200
	);
}
/**
 * GET /swiftboard/v1/votes/mine?post_ids=1,2,3
 * Récupère d'un coup tous mes votes sur une liste de posts (utile au chargement
 * d'une page de liste pour pré-remplir les boutons actifs).
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return \WP_REST_Response
 */
function swiftboard_rest_get_my_votes( WP_REST_Request $request ) {
	global $wpdb;
	$post_ids_raw = $request->get_param( 'post_ids' );
	if ( ! $post_ids_raw ) {
		return new WP_REST_Response( array( 'votes' => array() ), 200 );
	}
	$post_ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $post_ids_raw ) ) ) ) );
	$post_ids = array_slice( $post_ids, 0, 100 );
	if ( empty( $post_ids ) ) {
		return new WP_REST_Response( array( 'votes' => array() ), 200 );
	}

	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	$hash    = swiftboard_get_voter_hash();
	$table   = swiftboard_table( 'votes' );

	$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
	// `$table` vient exclusivement du préfixe WordPress interne ; les valeurs sont
	// toutes passées par les placeholders et le tableau d’arguments préparé.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	$sql = $wpdb->prepare(
		"SELECT post_id, vote_type FROM {$table}
	         WHERE voter_hash = %s AND user_id = %d AND post_id IN ({$placeholders})",
		array_merge( array( $hash, $user_id ), $post_ids )
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is prepared immediately above.

	$votes = array();
	foreach ( $rows as $r ) {
		$votes[ (int) $r['post_id'] ] = $r['vote_type'];
	}

	return new WP_REST_Response( array( 'votes' => $votes ), 200 );
}
