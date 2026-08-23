<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Lecture et recomptage des compteurs de vote.
 *
 * EXI-ARCH-01 : extrait de inc/votes-social.php. Ces fonctions sont lues a
 * chaque affichage de sujet : elles doivent rester en front.
 *
 * Point de vigilance : swiftboard_get_post_vote_score() teste l'EXISTENCE des
 * metas et non leur valeur. Un score de 0 est une reponse legitime, pas un
 * cache manquant — la version precedente relancait un recomptage complet a
 * chaque affichage d'un contenu sans vote (71 requetes SQL par page).
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 3. LIRE LE SCORE D'UN POST (depuis la table réelle)
// ============================================================================
/**
 * Retourne le score net (up - down) et les compteurs séparés.
 * Utilise le meta _swiftboard_vote_score comme cache (mis à jour à chaque vote).
 *
 * @param int $post_id Identifiant du contenu (sujet ou réponse).
 * @return mixed
 */
function swiftboard_get_post_vote_score( $post_id ) {
	$post_id = (int) $post_id;

	$cached   = get_post_meta( $post_id, '_swiftboard_vote_score', true );
	$up_raw   = get_post_meta( $post_id, '_swiftboard_vote_up', true );
	$down_raw = get_post_meta( $post_id, '_swiftboard_vote_down', true );

	// EXI-SCALE-03 — N+1 CORRIGE (page en lecture qui ECRIVAIT en base).
	//
	// L'ancienne condition etait `if ($up || $down)`. Pour un contenu SANS
	// AUCUN VOTE, up et down valent 0 : la condition echouait et la fonction
	// repartait sur swiftboard_recount_post_votes(), qui fait 1 SELECT
	// d'agregation + 3 update_post_meta(). A chaque affichage, pour chaque
	// contenu non vote.
	//
	// Mesure sur /forums/forum/forum-general/ : 71 requetes, dont 18 SELECT et
	// 18 UPDATE sur wp_postmeta — sur une page de simple lecture. Un forum
	// neuf, ou la plupart des sujets n'ont aucun vote, etait le pire cas.
	//
	// On teste desormais l'EXISTENCE des metas ('' = absente) et non leur
	// valeur : un score de 0 est une reponse legitime, pas un cache manquant.
	if ( $cached !== '' && is_numeric( $cached ) && $up_raw !== '' && $down_raw !== '' ) {
		return array(
			'score' => (int) $cached,
			'up'    => (int) $up_raw,
			'down'  => (int) $down_raw,
		);
	}

	// Cache reellement absent ou incomplet : recalcul depuis la table.
	return swiftboard_recount_post_votes( $post_id );
}
/**
 * Recompte les votes d'un post depuis la DB et met à jour les metas.
 *
 * @param int $post_id Identifiant du contenu (sujet ou réponse).
 * @return array<string, mixed>
 */
function swiftboard_recount_post_votes( $post_id ) {
	global $wpdb;
	$table   = swiftboard_table( 'votes' );
	$post_id = (int) $post_id;

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
            SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) as up_count,
            SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) as down_count
         FROM {$table} WHERE post_id = %d",
			$post_id
		),
		ARRAY_A
	);

	$up    = (int) ( $row['up_count'] ?? 0 );
	$down  = (int) ( $row['down_count'] ?? 0 );
	$score = $up - $down;

	update_post_meta( $post_id, '_swiftboard_vote_score', $score );
	update_post_meta( $post_id, '_swiftboard_vote_up', $up );
	update_post_meta( $post_id, '_swiftboard_vote_down', $down );

	// CDC-PROD-FERME-04 : score de tri hot/top dénormalisé (vote + 2×replies).
	$replies = 0;
	if ( function_exists( 'bbp_get_topic_reply_count' ) && get_post_type( $post_id ) === 'topic' ) {
		$replies = (int) bbp_get_topic_reply_count( $post_id );
	} else {
		$replies = (int) get_post_meta( $post_id, '_bbp_reply_count', true );
	}
	update_post_meta( $post_id, '_swiftboard_hot_score', (int) $score + ( 2 * $replies ) );

	return array(
		'score' => $score,
		'up'    => $up,
		'down'  => $down,
	);
}

/**
 * Detail des votes d'un post : up et down separement.
 *
 * Le score net seul est ambigu pour un lecteur automatique : 0 peut signifier
 * « aucun vote » comme « +50 / -50 ». On expose donc les deux compteurs, ce
 * qui permet notamment au JSON-LD (inc/schema.php) de publier upvoteCount et
 * downvoteCount conformement a schema.org.
 *
 * Lit les metas de cache alimentees par swiftboard_recount_post_votes(), et
 * retombe sur un COUNT direct si elles n'ont jamais ete calculees.
 *
 * @param int $post_id ID du post.
 * @return array{score:int,up:int,down:int}
 */
function swiftboard_get_vote_breakdown( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return array(
			'score' => 0,
			'up'    => 0,
			'down'  => 0,
		);
	}

	$up   = get_post_meta( $post_id, '_swiftboard_vote_up', true );
	$down = get_post_meta( $post_id, '_swiftboard_vote_down', true );

	// Metas absentes : on recompte depuis la table (cas d'un contenu importe
	// ou vote avant la mise en place du cache).
	if ( '' === $up && '' === $down ) {
		return swiftboard_recount_post_votes( $post_id );
	}

	$up   = (int) $up;
	$down = (int) $down;

	return array(
		'score' => $up - $down,
		'up'    => $up,
		'down'  => $down,
	);
}
/**
 * Récupère le vote du votant courant sur un post (null = pas voté).
 *
 * @param int $post_id Identifiant du contenu (sujet ou réponse).
 * @return mixed
 */
function swiftboard_get_my_vote( $post_id ) {
	global $wpdb;
	$table   = swiftboard_table( 'votes' );
	$post_id = (int) $post_id;
	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	$hash    = swiftboard_get_voter_hash();

	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT vote_type FROM {$table}
         WHERE post_id = %d AND user_id = %d AND voter_hash = %s
         LIMIT 1",
			$post_id,
			$user_id,
			$hash
		)
	);
}

// ============================================================================
// 9. API PUBLIQUE POUR LES DÉVELOPPEURS
// ============================================================================
/**
 * Récupère les top posts par score.
 *
 * @param string $post_type 'topic' ou 'reply' ou 'any'
 * @param int    $limit
 * @return array<string, mixed> [['post_id'=>..,'score'=>..,'up'=>..,'down'=>..], ...]
 */
function swiftboard_get_top_voted_posts( $post_type = 'topic', $limit = 20 ) {
	global $wpdb;
	$table = swiftboard_table( 'votes' );
	$limit = max( 1, min( 200, (int) $limit ) );

	$allowed = array( 'topic', 'reply', 'any' );
	if ( ! in_array( $post_type, $allowed, true ) ) {
		$post_type = 'topic';
	}

	if ( $post_type === 'any' ) {
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id,
                        SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) as up,
                        SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) as down,
                        SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END) as score
                 FROM {$table}
                 GROUP BY post_id
                 ORDER BY score DESC
                 LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id,
                    SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) as up,
                    SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) as down,
                    SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END) as score
             FROM {$table}
             WHERE post_type = %s
             GROUP BY post_id
             ORDER BY score DESC
             LIMIT %d",
			$post_type,
			$limit
		),
		ARRAY_A
	);
}


/**
 * Recalcule _swiftboard_hot_score d'un topic après évolution des replies.
 *
 * @param int $topic_id ID topic.
 * @return void
 */
function swiftboard_refresh_hot_score( $topic_id ) {
	$topic_id = (int) $topic_id;
	if ( $topic_id <= 0 || get_post_type( $topic_id ) !== 'topic' ) {
		return;
	}
	if ( function_exists( 'swiftboard_recount_post_votes' ) ) {
		swiftboard_recount_post_votes( $topic_id );
		return;
	}
	$score   = (int) get_post_meta( $topic_id, '_swiftboard_vote_score', true );
	$replies = function_exists( 'bbp_get_topic_reply_count' )
		? (int) bbp_get_topic_reply_count( $topic_id )
		: (int) get_post_meta( $topic_id, '_bbp_reply_count', true );
	update_post_meta( $topic_id, '_swiftboard_hot_score', $score + ( 2 * $replies ) );
}

add_action(
	'bbp_new_reply',
	function ( $reply_id, $topic_id ) {
		swiftboard_refresh_hot_score( (int) $topic_id );
	},
	30,
	2
);

add_action(
	'bbp_deleted_reply',
	function ( $reply_id ) {
		if ( function_exists( 'bbp_get_reply_topic_id' ) ) {
			swiftboard_refresh_hot_score( (int) bbp_get_reply_topic_id( $reply_id ) );
		}
	},
	30
);


// V2 restauration - Top voted API branchée
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/top-voted',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'swiftboard_rest_public_permission',
				'callback'            => function ( $req ) {
					$limit = max( 1, min( 50, (int) ( $req->get_param( 'limit' ) ?: 10 ) ) );
					$posts = swiftboard_get_top_voted_posts( 'topic', $limit );
					return new WP_REST_Response( array( 'top' => $posts ), 200 );
				},
				'args'                => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 10,
					),
				),
			)
		);
	}
);
