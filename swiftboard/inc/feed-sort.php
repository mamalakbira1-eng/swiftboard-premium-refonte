<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Tri du feed (Hot / New / Top / Rising + filtre temporel)
 *
 * Modifie la requête topics de bbPress pour appliquer le tri demandé :
 *  - hot     : score (votes + replies × 2) DESC sur la période
 *  - new     : post_date DESC (toutes périodes)
 *  - top     : score total DESC sur la période
 *  - rising  : score / âge (sujets qui gagnent en momentum)
 *
 * Filtre temporel : 24h, 7d, 30d, all (uniquement pour top et rising)
 *
 * Hooks :
 *  - bbp_has_topics_query : injecte les orderby + where
 *  - pre_get_posts        : backup pour les loops WordPress classiques
 *
 * @package SwiftBoard
 * @since 3.1.0
 */
// ============================================================================
// 1. RÉCUPÉRER LE TRI ACTUEL (URL ?sort=)
// ============================================================================
/**
 * @return mixed
 */
function swiftboard_get_current_sort() {
	$sort    = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'hot';
	$allowed = array( 'hot', 'new', 'top', 'rising' );
	return in_array( $sort, $allowed, true ) ? $sort : 'hot';
}

/**
 * @return mixed
 */
function swiftboard_get_current_period() {
	$period  = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'all';
	$allowed = array( '24h', '7d', '30d', 'all' );
	return in_array( $period, $allowed, true ) ? $period : 'all';
}

/**
 * swiftboard_period_to_interval().
 *
 * @param mixed $period À documenter.
 * @return null|string
 */
function swiftboard_period_to_interval( $period ) {
	switch ( $period ) {
		case '24h':
			return '1 DAY';
		case '7d':
			return '7 DAY';
		case '30d':
			return '30 DAY';
		case 'all':
			return null;  // pas de filtre temporel
		default:
			return '7 DAY';
	}
}

// ============================================================================
// 2. HOOK SUR LA REQUÊTE TOPICS bbPress
// ============================================================================
/**
 * On intercepte bbp_has_topics() pour réordonner selon le sort choisi.
 * Stratégie : on garde la requête bbPress (compat pagination) mais on
 * ajoute un ORDER BY personnalisé via meta query ou SQL direct.
 */
add_filter(
	'bbp_has_topics_query',
	function ( $args ) {
		$sort     = swiftboard_get_current_sort();
		$period   = swiftboard_get_current_period();
		$interval = swiftboard_period_to_interval( $period );

		// Pour 'new', on garde le tri par défaut de bbPress (post_date DESC)
		if ( $sort === 'new' ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			return $args;
		}

		// Pour 'hot', 'top', 'rising' : on doit utiliser un meta_query + orderby meta
		// bbPress ne permet pas facilement un ORDER BY calculé, donc on utilise
		// pre_get_posts + custom SQL via posts_orderby filter

		if ( $interval ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_date',
					'after'  => '-' . $interval,
				),
			);
		}

		return $args;
	}
);

// ============================================================================
// 3. FILTRE posts_orderby — ORDER BY personnalisé pour hot/top/rising
// ============================================================================
/**
 * JOIN postmeta pour tris hot/top/rising (évite DEPENDENT SUBQUERY dans ORDER BY).
 * CDC SQL-03 — robustesse (anti ERROR 1242 si metas dupliquées) ; perf 100k = colonne dénormalisée (backlog).
 */
/**
 * Tris nécessitant un JOIN meta.
 * CDC-PROD-FERME-04 : hot/top via _swiftboard_hot_score dénormalisé.
 */
function swiftboard_feed_sort_needs_meta_join(): bool {
	if ( is_admin() ) {
		return false;
	}
	$sort = function_exists( 'swiftboard_get_current_sort' ) ? swiftboard_get_current_sort() : 'new';
	return in_array( $sort, array( 'hot', 'top', 'rising' ), true );
}

add_filter(
	'posts_join',
	function ( $join, $query ) {
		if ( ! isset( $query->query_vars['post_type'] ) || $query->query_vars['post_type'] !== 'topic' ) {
			return $join;
		}
		if ( ! empty( $query->query_vars['swiftboard_pagination'] ) ) {
			return $join;
		}
		if ( ! swiftboard_feed_sort_needs_meta_join() ) {
			return $join;
		}
		global $wpdb;
		$sort = swiftboard_get_current_sort();

		if ( $sort === 'hot' ) {
			if ( strpos( $join, 'sb_mt_hot' ) === false ) {
				$join .= " LEFT JOIN {$wpdb->postmeta} sb_mt_hot ON (sb_mt_hot.post_id = {$wpdb->posts}.ID AND sb_mt_hot.meta_key = '_swiftboard_hot_score') ";
			}
			return $join;
		}

		// top et rising : score net de votes uniquement
		if ( strpos( $join, 'sb_mt_score' ) === false ) {
			$join .= " LEFT JOIN {$wpdb->postmeta} sb_mt_score ON (sb_mt_score.post_id = {$wpdb->posts}.ID AND sb_mt_score.meta_key = '_swiftboard_vote_score') ";
		}
		return $join;
	},
	10,
	2
);

add_filter(
	'posts_orderby',
	function ( $orderby, $query ) {
		if ( ! isset( $query->query_vars['post_type'] ) || $query->query_vars['post_type'] !== 'topic' ) {
			return $orderby;
		}
		if ( is_admin() ) {
			return $orderby;
		}
		if ( ! empty( $query->query_vars['swiftboard_pagination'] ) ) {
			return $orderby;
		}

		$sort = swiftboard_get_current_sort();
		global $wpdb;

		switch ( $sort ) {
			case 'hot':
				return 'COALESCE(sb_mt_hot.meta_value+0, 0) DESC, '
				. "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";

			case 'top':
				return 'COALESCE(sb_mt_score.meta_value+0, 0) DESC, '
				. "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";

			case 'rising':
				return '(COALESCE(sb_mt_score.meta_value+0, 0) / GREATEST(1, TIMESTAMPDIFF(HOUR, '
				. "{$wpdb->posts}.post_date, NOW()))) DESC, "
				. "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";

			default:
				return $orderby;
		}
	},
	10,
	2
);

// ============================================================================
// 4. CACHE DES METAS _swiftboard_vote_score SUR LES TOPICS
// ============================================================================
/**
 * Si le meta _swiftboard_vote_score n'existe pas sur un topic, on le calcule
 * à la volée à partir de la table swiftboard_votes (une seule fois par topic).
 * Ça permet à la requête ORDER BY de fonctionner même sur les topics anciens.
 */
/**
 * Amorce les compteurs de vote des sujets affiches — EN UN SEUL LOT.
 *
 * EXI-SCALE-03 — N+1 CORRIGE
 * --------------------------
 * La version precedente bouclait sur les sujets visibles et appelait
 * swiftboard_recount_post_votes() pour chacun. Chaque appel coute 1 SELECT
 * d'agregation + 3 update_post_meta(), et chaque update_post_meta() fait
 * lui-meme 1 SELECT + 1 UPDATE.
 *
 * Mesure sur /forums/forum/forum-general/ : 71 requetes par affichage, dont
 * 18 SELECT et 18 UPDATE sur wp_postmeta — pour une page en LECTURE seule.
 * Le seuil du cahier est de 30.
 *
 * Version corrigee :
 *   1. un seul SELECT groupe (GROUP BY post_id) pour tous les sujets ;
 *   2. update_meta_cache() en lot, pour que metadata_exists() et les lectures
 *      suivantes ne retournent pas en base ;
 *   3. on n'ECRIT que si la valeur a reellement change — une page de lecture
 *      ne doit rien ecrire.
 *
 * @return void
 */
add_action(
	'bbp_template_before_topics_loop',
	function () {
		global $wpdb, $wp_query;

		$votes_table = swiftboard_table( 'votes' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $votes_table ) ) !== $votes_table ) {
			return;
		}

		$ids = array();
		if ( $wp_query && ! empty( $wp_query->posts ) ) {
			foreach ( $wp_query->posts as $p ) {
				if ( ! empty( $p->ID ) ) {
					$ids[] = (int) $p->ID;
				}
			}
		}
		if ( empty( $ids ) ) {
			return;
		}

		// Un seul aller-retour pour amorcer le cache de metas des sujets
		// affiches : sans cela, chaque metadata_exists() ci-dessous frapperait
		// la base.
		update_meta_cache( 'post', $ids );

		// Sujets dont le score n'a jamais ete calcule.
		$manquants = array();
		foreach ( $ids as $id ) {
			if ( ! metadata_exists( 'post', $id, '_swiftboard_vote_score' ) ) {
				$manquants[] = $id;
			}
		}
		if ( empty( $manquants ) ) {
			return;
		}

		// UN SEUL SELECT groupe pour tous les sujets concernes.
		$placeholders = implode( ',', array_fill( 0, count( $manquants ), '%d' ) );
		$lignes       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id,
                    SUM(CASE WHEN vote_type = 'up'   THEN 1 ELSE 0 END) AS up_count,
                    SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) AS down_count
             FROM {$votes_table}
             WHERE post_id IN ({$placeholders})
             GROUP BY post_id",
				$manquants
			),
			ARRAY_A
		);

		$compte = array();
		foreach ( (array) $lignes as $l ) {
			$compte[ (int) $l['post_id'] ] = array(
				'up'   => (int) $l['up_count'],
				'down' => (int) $l['down_count'],
			);
		}

		foreach ( $manquants as $id ) {
			$up   = isset( $compte[ $id ] ) ? $compte[ $id ]['up'] : 0;
			$down = isset( $compte[ $id ] ) ? $compte[ $id ]['down'] : 0;

			// update_post_meta() n'ecrit pas si la valeur est identique, mais il
			// fait tout de meme un SELECT de comparaison. Le cache amorce plus
			// haut evite ce SELECT ; l'UPDATE n'a lieu qu'au premier calcul.
			update_post_meta( $id, '_swiftboard_vote_score', $up - $down );
			update_post_meta( $id, '_swiftboard_vote_up', $up );
			update_post_meta( $id, '_swiftboard_vote_down', $down );
			if ( function_exists( 'swiftboard_refresh_hot_score' ) ) {
				// no-op: recount already sets hot_score when called; keep for missing-only path
			}
		}
	}
);

// ============================================================================
// 5. ENDPOINT REST — Feed trié (alternative pour AJAX)
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/feed',
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_rest_feed',
				'permission_callback' => 'swiftboard_rest_public_permission',
				'args'                => array(
					'sort'     => array(
						'type'    => 'string',
						'enum'    => array( 'hot', 'new', 'top', 'rising' ),
						'default' => 'hot',
					),
					'period'   => array(
						'type'    => 'string',
						'enum'    => array( '24h', '7d', '30d', 'all' ),
						'default' => 'all',
					),
					'forum_id' => array( 'type' => 'integer' ),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 15,
						'maximum' => 50,
					),
				),
			)
		);
	}
);

/**
 * swiftboard_rest_feed().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return mixed
 */
function swiftboard_rest_feed( WP_REST_Request $req ) {
	$sort     = sanitize_text_field( $req->get_param( 'sort' ) );
	$period   = sanitize_text_field( $req->get_param( 'period' ) );
	$forum_id = (int) $req->get_param( 'forum_id' );
	$page     = max( 1, (int) $req->get_param( 'page' ) );
	$per_page = max( 1, min( 50, (int) $req->get_param( 'per_page' ) ) );

	$args = array(
		'post_type'      => 'topic',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $page,
	);
	if ( $forum_id ) {
		// Inclure les sous-forums (comme single-forum.php avec post_parent__in).
		$sub_forums = get_posts( array(
			'post_type'      => 'forum',
			'post_parent'    => $forum_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		$args['post_parent__in'] = array_merge( array( $forum_id ), $sub_forums );
	}

	// Période
	$interval = swiftboard_period_to_interval( $period );
	if ( $interval && $sort !== 'new' ) {
		$args['date_query'] = array(
			array(
				'column' => 'post_date',
				'after'  => '-' . $interval,
			),
		);
	}

	if ( $sort === 'new' ) {
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
	}

	$query  = new WP_Query( $args );
	$topics = array();

	// === Anti-N+1 : précharger en batch toutes les metas + users pour les posts de la loop ===
	$post_ids = wp_list_pluck( $query->posts, 'ID' );
	if ( ! empty( $post_ids ) ) {
		update_meta_cache( 'post', $post_ids );        // 1 seule requête pour toutes les metas
		_prime_post_caches( $post_ids, false, false ); // 1 seule requête pour les posts parents (forums)
		$author_ids = array_unique( array_filter( wp_list_pluck( $query->posts, 'post_author' ) ) );
		cache_users( $author_ids );                     // 1 seule requête pour tous les auteurs
	}

	while ( $query->have_posts() ) {
		$query->the_post();
		$topic_id    = get_the_ID();
		$votes       = function_exists( 'swiftboard_get_vote_count' ) ? swiftboard_get_vote_count( $topic_id ) : 0;
		$reply_count = function_exists( 'bbp_get_topic_reply_count' ) ? bbp_get_topic_reply_count( $topic_id, true ) : 0;
		$author_id   = get_post_field( 'post_author', $topic_id );
		$forum_id    = wp_get_post_parent_id( $topic_id );

		$topics[] = array(
			'id'          => $topic_id,
			'title'       => get_the_title(),
			'url'         => get_permalink(),
			'author_id'   => (int) $author_id,
			'author_name' => get_the_author_meta( 'display_name', (int) $author_id ),
			'forum_id'    => (int) $forum_id,
			'forum_name'  => $forum_id ? get_the_title( $forum_id ) : '',
			'forum_url'   => $forum_id ? get_permalink( $forum_id ) : '',
			'votes'       => (int) $votes,
			'reply_count' => (int) $reply_count,
			'date'        => get_the_date( 'c' ),
			'time_ago'    => function_exists( 'swiftboard_time_ago' ) ? swiftboard_time_ago( strtotime( get_post_field( 'post_date' ) ) ) : '',
			'excerpt'     => wp_trim_words( wp_strip_all_tags( get_the_content() ), 35, '…' ),
		);
	}
	wp_reset_postdata();

	// Cache-Control public (les topics publiés sont identiques pour tous)

	$sb_response = new WP_REST_Response(
		array(
			'sort'     => $sort,
			'period'   => $period,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) $query->found_posts,
			'pages'    => (int) $query->max_num_pages,
			'topics'   => $topics,
		),
		200
	);
	// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
	// header() emet un warning des que la sortie a commence et
	// echappe aux filtres rest_post_dispatch.
	$sb_response->header( 'Cache-Control', 'public, max-age=60' );
	return $sb_response;
}


/**
 * CDC-PROD-FERME-04 : assure _swiftboard_hot_score sur les topics de la page courante.
 */
add_action(
	'bbp_template_before_topics_loop',
	function () {
		global $wp_query;
		if ( empty( $wp_query->posts ) || ! function_exists( 'swiftboard_refresh_hot_score' ) ) {
			return;
		}
		foreach ( $wp_query->posts as $p ) {
			$id = (int) ( $p->ID ?? 0 );
			if ( $id && ! metadata_exists( 'post', $id, '_swiftboard_hot_score' ) ) {
				swiftboard_refresh_hot_score( $id );
			}
		}
	},
	20
);
