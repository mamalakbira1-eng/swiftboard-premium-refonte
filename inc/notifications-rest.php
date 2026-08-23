<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Routes REST des notifications.
 *
 * EXI-ARCH-01 : extrait de inc/notifications.php. Module FRONT : la cloche
 * interroge ces routes depuis les pages publiques. Enregistrees depuis un
 * module admin-only, elles renverraient 404 et la cloche resterait muette.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 6. ENDPOINTS REST API
// ============================================================================
/**
 * @return void
 */
function swiftboard_register_notification_routes() {
	register_rest_route(
		'swiftboard/v1',
		'/notifications',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_rest_get_notifications',
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'limit'  => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'offset' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			),
		)
	);

	register_rest_route(
		'swiftboard/v1',
		'/notifications/unread-count',
		array(
			'methods'             => 'GET',
			'callback'            => 'swiftboard_rest_unread_count',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	register_rest_route(
		'swiftboard/v1',
		'/notifications/(?P<id>\d+)/read',
		array(
			'methods'             => 'POST',
			'callback'            => 'swiftboard_rest_mark_read',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
			'args'                => array(
				'id' => array(
					'type'     => 'integer',
					'required' => true,
				),
			),
		)
	);

	register_rest_route(
		'swiftboard/v1',
		'/notifications/read-all',
		array(
			'methods'             => 'POST',
			'callback'            => 'swiftboard_rest_mark_all_read',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);
}
add_action( 'rest_api_init', 'swiftboard_register_notification_routes' );

/**
 * swiftboard_rest_get_notifications().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return mixed
 */
function swiftboard_rest_get_notifications( WP_REST_Request $req ) {
	$user_id = get_current_user_id();
	$limit   = (int) $req->get_param( 'limit' );
	$offset  = (int) $req->get_param( 'offset' );
	$rows    = swiftboard_get_notifications( $user_id, $limit, $offset );

	// === Anti-N+1 : précharger tous les acteurs en une seule requête ===
	$actor_ids    = array_unique( array_filter( array_column( $rows, 'actor_id' ) ) );
	$actors_cache = array();
	if ( ! empty( $actor_ids ) ) {
		// cache_users primes WP's internal user cache in ONE query
		cache_users( $actor_ids );
		foreach ( $actor_ids as $aid ) {
			$u                    = get_userdata( $aid );
			$actors_cache[ $aid ] = $u ? $u->display_name : __( 'Quelqu\'un', 'swiftboard' );
		}
	}

	// Précharger les permaliens en batch (évite N requêtes get_permalink)
	$post_ids        = array_unique( array_filter( array_column( $rows, 'post_id' ) ) );
	$permalink_cache = array();
	foreach ( $post_ids as $pid ) {
		$permalink_cache[ $pid ] = get_permalink( $pid );
	}

	// Enrichir avec les infos acteur
	foreach ( $rows as &$r ) {
		if ( ! empty( $r['actor_id'] ) ) {
			$r['actor_name']   = $actors_cache[ $r['actor_id'] ] ?? __( 'Quelqu\'un', 'swiftboard' );
			$r['actor_avatar'] = function_exists( 'get_avatar_url' )
				? get_avatar_url( $r['actor_id'], array( 'size' => 32 ) )
				: '';
		} else {
			$r['actor_name']   = 'SwiftBoard';
			$r['actor_avatar'] = '';
		}
		$r['url']      = $r['post_id'] ? ( $permalink_cache[ $r['post_id'] ] ?? '' ) : '';
		$r['time_ago'] = function_exists( 'swiftboard_time_ago' )
			? swiftboard_time_ago( strtotime( $r['created_at'] ) )
			: $r['created_at'];
		$r['icon']     = swiftboard_notif_icon( $r['type'] );

		// Defense en profondeur : les champs texte sont nettoyes A LA SORTIE.
		//
		// WordPress strippe deja le markup a l'ENTREE (display_name, titres via
		// wp_insert_post) — verifie par la mesure. Mais la table est ecrite par
		// du SQL direct dans plusieurs modules, et rien n'empeche un plugin
		// tiers, un import ou une migration d'y deposer du markup. Il ressortait
		// alors intact et atterrissait dans innerHTML cote client.
		//
		// Ces champs sont du TEXTE affiche : aucun balisage n'y est legitime.
		foreach ( array( 'title', 'excerpt', 'actor_name' ) as $sb_champ_texte ) {
			if ( isset( $r[ $sb_champ_texte ] ) ) {
				$r[ $sb_champ_texte ] = wp_strip_all_tags( (string) $r[ $sb_champ_texte ] );
			}
		}
	}

	// Cache-Control : privé (data utilisateur) — pas de cache CDN/proxy

	$sb_response = new WP_REST_Response( array( 'notifications' => $rows ), 200 );
	// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
	// header() emet un warning des que la sortie a commence et
	// echappe aux filtres rest_post_dispatch.
	$sb_response->header( 'Cache-Control', 'private, max-age=30' );
	return $sb_response;
}

/**
 * @return \WP_REST_Response
 */
function swiftboard_rest_unread_count() {
	return new WP_REST_Response(
		array(
			'count' => swiftboard_get_unread_count( get_current_user_id() ),
		),
		200
	);
}

/**
 * swiftboard_rest_mark_read().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_rest_mark_read( WP_REST_Request $req ) {
	// E-3 fix: Verify nonce
	$nonce = $req->get_header( 'X-WP-Nonce' );
	if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'rest_cookie_invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
	}
	// E-3 fix: Verify ownership — user can only mark THEIR notifications as read
	$notif_id = (int) $req->get_param( 'id' );
	$user_id  = get_current_user_id();
	global $wpdb;
	$table = swiftboard_table( 'notifications' );
	$owner = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE id = %d", $notif_id ) );
	if ( $owner != $user_id ) {
		return new WP_Error( 'forbidden', 'You can only mark your own notifications', array( 'status' => 403 ) );
	}
	$notif_id = (int) $req->get_param( 'id' );
	swiftboard_mark_notification_read( $notif_id, get_current_user_id() );
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * swiftboard_rest_mark_all_read().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_rest_mark_all_read( WP_REST_Request $req ) {
	// E-3 fix: Verify nonce
	$nonce = $req->get_header( 'X-WP-Nonce' );
	if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'rest_cookie_invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
	}
	swiftboard_mark_all_read( get_current_user_id() );
	return new WP_REST_Response(
		array(
			'ok'    => true,
			'count' => 0,
		),
		200
	);
}
