<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — SSE (Server-Sent Events) pour notifications temps réel
 *
 * Remplace le polling 120s par une connexion SSE persistante qui pousse les
 * nouvelles notifications en < 5 secondes.
 *
 * Stratégie :
 * 1. Endpoint REST /wp-json/swiftboard/v1/notifications/stream qui ouvre une
 *    connexion SSE (text/event-stream) et poll la DB toutes les 3s
 * 2. JS EventSource qui écoute ce flux et met à jour la cloche en temps réel
 * 3. Fallback automatique vers polling 120s si SSE échoue
 *
 * Note technique : FrankenPHP + Caddy supportent les connexions longues.
 * Le serveur envoie un heartbeat toutes les 30s pour maintenir la connexion.
 *
 * @package SwiftBoard
 * @since 4.3.0
 */
// ============================================================================
// 0. INTERRUPTEUR — SSE DESACTIVE PAR DEFAUT (EXI-BLOQ-08)
// ============================================================================
/*
 * POURQUOI LE SSE EST OPT-IN
 * --------------------------
 * swiftboard_sse_notifications_stream() maintient une connexion ouverte 50 s
 * (while(true) + sleep(1)), avec set_time_limit(0) et ignore_user_abort(true).
 * Le client se reconnecte immediatement apres chaque timeout : chaque
 * utilisateur connecte MONOPOLISE donc un worker PHP-FPM en permanence.
 *
 * Un hebergement mutualise alloue 10 a 30 workers. Au-dela d'environ
 * 20 connectes simultanes, il ne reste plus un seul worker pour servir les
 * pages : le site entier renvoie des 502/504 — y compris aux visiteurs
 * anonymes qui ne se servent pas des notifications.
 *
 * Le README annonce « optimise pour Hostinger » (mutualise), alors que le SSE
 * exige FrankenPHP, un VPS ou des workers dedies. Le defaut doit donc
 * correspondre a la cible affichee : polling.
 *
 * Pour activer sur une infrastructure adaptee, dans wp-config.php :
 *     define('SWIFTBOARD_ENABLE_SSE', true);
 */
if ( ! defined( 'SWIFTBOARD_ENABLE_SSE' ) ) {
	define( 'SWIFTBOARD_ENABLE_SSE', false );
}

/**
 * Le flux SSE est-il actif ?
 *
 * Filtrable pour permettre une activation conditionnelle (par exemple selon
 * la charge, ou pour une fraction des utilisateurs lors d'un deploiement
 * progressif).
 *
 * @return bool
 */
function swiftboard_sse_enabled() {
	return (bool) apply_filters( 'swiftboard_sse_enabled', SWIFTBOARD_ENABLE_SSE );
}

// Aucun hook n'est enregistre si le SSE est desactive : ni la route REST, ni
// le script client, ni le flag qui coupe le polling. Le theme retombe alors
// integralement sur le polling de main.js.
if ( ! swiftboard_sse_enabled() ) {
	return;
}

// ============================================================================
// 1. ENDPOINT SSE — STREAM DE NOTIFICATIONS
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/notifications/stream',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'callback'            => 'swiftboard_sse_notifications_stream',
			)
		);
	}
);
/**
 * Stream SSE : envoie des events "notification:new" + heartbeat toutes les 30s.
 * Poll DB toutes les 3s pour les nouvelles notifs non lues.
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return \WP_REST_Response
 */
function swiftboard_sse_notifications_stream( WP_REST_Request $req ) {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return new WP_REST_Response( array( 'error' => 'unauthorized' ), 401 );
	}

	// Headers SSE
	header( 'Content-Type: text/event-stream' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Connection: keep-alive' );
	header( 'X-Accel-Buffering: no' ); // Désactive le buffering Nginx/Caddy
	header( 'Access-Control-Allow-Origin: ' . home_url() );
	header( 'Access-Control-Allow-Credentials: true' );

	// Désactive le timeout PHP (limite serveur = 120s sur Hostinger)
	// On utilise @ini_set car set_time_limit peut être désactivé
	@ini_set( 'max_execution_time', '0' );
	@set_time_limit( 0 );
	@ignore_user_abort( true );

	// État initial
	$last_seen_id   = isset( $_GET['last_seen_id'] ) ? (int) $_GET['last_seen_id'] : 0;
	$last_heartbeat = time();
	$last_poll      = 0;
	$start_time     = time();
	$max_duration   = 50; // 50s max par connexion (avant reconnect client)

	// Premier event : confirmation de connexion
	echo "event: ready\ndata: " . wp_json_encode(
		array(
			'user_id' => $user_id,
			'ts'      => time(),
		)
	) . "\n\n";
	@ob_flush();
	@flush();

	// Boucle SSE
	while ( true ) {
		// Vérifier si le client est encore connecté
		if ( connection_aborted() ) {
			break;
		}

		// Limite de durée pour éviter les connexions infinies
		if ( time() - $start_time > $max_duration ) {
			echo "event: reconnect\ndata: " . wp_json_encode(
				array(
					'reason'  => 'timeout',
					'last_id' => $last_seen_id,
				)
			) . "\n\n";
			@ob_flush();
			@flush();
			break;
		}

		$now = time();

		// Poll DB toutes les 3s
		if ( $now - $last_poll >= 3 ) {
			$last_poll  = $now;
			$new_notifs = swiftboard_sse_get_new_notifications( $user_id, $last_seen_id );
			if ( ! empty( $new_notifs ) ) {
				foreach ( $new_notifs as $notif ) {
					$payload = array(
						'id'         => (int) $notif['id'],
						'type'       => $notif['type'],
						'title'      => $notif['title'],
						'excerpt'    => $notif['excerpt'],
						'actor_name' => $notif['actor_name'] ?? '',
						'url'        => $notif['url'] ?? '',
						'icon'       => swiftboard_notif_icon( $notif['type'] ),
						'ts'         => strtotime( $notif['created_at'] ),
					);
					echo "event: notification\ndata: " . wp_json_encode( $payload ) . "\n\n";
					if ( (int) $notif['id'] > $last_seen_id ) {
						$last_seen_id = (int) $notif['id'];
					}
				}
				// Compteur unread mis à jour
				$unread = swiftboard_get_unread_count( $user_id );
				echo "event: unread\ndata: " . wp_json_encode( array( 'count' => $unread ) ) . "\n\n";
				@ob_flush();
				@flush();
			}
		}

		// Heartbeat toutes les 30s
		if ( $now - $last_heartbeat >= 30 ) {
			$last_heartbeat = $now;
			echo ': heartbeat ' . $now . "\n\n";
			@ob_flush();
			@flush();
		}

		// Sleep 1s entre les polls pour ne pas cramer CPU
		sleep( 1 );
	}

	exit; // Important : empêche WP REST de rajouter des headers JSON
}
/**
 * Récupère les notifications plus récentes que $last_seen_id.
 *
 * @param int $user_id      Identifiant de l'utilisateur.
 * @param int $last_seen_id Identifiant.
 * @return mixed
 */
function swiftboard_sse_get_new_notifications( $user_id, $last_seen_id ) {
	global $wpdb;
	$table = swiftboard_table( 'notifications' );

	// Vérifier que la table existe
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return array();
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, type, title, excerpt, actor_id, post_id, created_at
         FROM {$table}
         WHERE user_id = %d
           AND id > %d
         ORDER BY id ASC
         LIMIT 20",
			$user_id,
			$last_seen_id
		),
		ARRAY_A
	);

	if ( empty( $rows ) ) {
		return array();
	}

	// Enrichir avec actor_name + url (en batch pour éviter N+1)
	$actor_ids = array_unique( array_filter( array_column( $rows, 'actor_id' ) ) );
	if ( ! empty( $actor_ids ) ) {
		cache_users( $actor_ids );
	}
	foreach ( $rows as &$r ) {
		if ( ! empty( $r['actor_id'] ) ) {
			$u               = get_userdata( $r['actor_id'] );
			$r['actor_name'] = $u ? $u->display_name : __( 'Quelqu\'un', 'swiftboard' );
		}
		if ( ! empty( $r['post_id'] ) ) {
			$r['url'] = get_permalink( (int) $r['post_id'] );
		}
	}
	return $rows;
}

// ============================================================================
// 2. JS — EVENTSOURCE POUR ÉCOUTER LE FLUX SSE
// ============================================================================
// Le client SSE vit dans assets/js/sse-notifications.js.
//
// Il etait auparavant imprime en <script> inline dans wp_footer, donc refuse
// par la CSP `script-src 'self'` en ENFORCE. Mesure avant correction, SSE
// active dans Chromium : window.swiftboardSSEActive = undefined, 2 scripts
// inline bloques, 0 flux SSE. La fonctionnalite etait entierement inerte.
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script(
			'swiftboard-sse',
			SWIFTBOARD_ASSETS . '/js/sse-notifications.js',
			array(),
			SWIFTBOARD_VERSION,
			true
		);
	},
	100
);

// ============================================================================
// 3. DÉSACTIVER LE POLLING JS V4.2 QUAND SSE EST ACTIF
// ============================================================================
// Le JS v4.2 (initNotificationsPolling dans main.js) doit être désactivé
// pour éviter les doubles fetch. On override en supprimant le bell ID pour
// le script main.js, mais on le garde pour le script SSE.
//
// En pratique : main.js initNotificationsPolling() s'arrete si le drapeau est
// leve. Il passait par wp_add_inline_script(), donc par un <script> inline —
// refuse par la CSP `script-src 'self'` en enforce. Resultat mesure : le
// drapeau restait `undefined` et le polling tournait quand meme.
//
// Il passe desormais par wp_localize_script(), qui produit lui aussi un bloc
// inline MAIS dont l'empreinte SHA-256 est calculee et publiee par
// swiftboard_csp_hashes_inline() : le navigateur l'accepte.
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! wp_script_is( 'swiftboard-main', 'registered' ) ) {
			return;
		}

		add_action(
			'wp_footer',
			static function () {
				echo '<div id="swiftboard-sse-config" hidden data-active="1"></div>';
			},
			4
		);
	},
	9
);
