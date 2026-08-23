<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Recherche FULLTEXT : couche REST + assets autocomplete.
 *
 * CDC-RESTANT-01 / EXI-ARCH : extrait de inc/fulltext-search.php pour respecter
 * le seuil ArchitectureTest (500 lignes / fichier inc/).
 *
 * @package SwiftBoard
 */
// ============================================================================
// 4. AJOUTER LE SCORE DE PERTINENCE DANS LES RÉSULTATS (REST + frontend)
// ============================================================================
/**
 * Pour la REST API search endpoint, on expose le score FULLTEXT.
 * Permet au frontend d'afficher un badge "pertinence: 8.5/10".
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'swiftboard_rest_public_permission',
				'callback'            => 'swiftboard_rest_fulltext_search',
				'args'                => array(
					's'         => array(
						'type'     => 'string',
						'required' => true,
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'topic',
						'enum'    => array( 'topic', 'reply', 'forum', 'post', 'any' ),
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
						'maximum' => 100,
					),
					'cursor'    => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}
);

/**
 * swiftboard_rest_fulltext_search().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return mixed
 */
function swiftboard_rest_fulltext_search( WP_REST_Request $req ) {
	global $wpdb;

	$s         = sanitize_text_field( $req->get_param( 's' ) );
	$post_type = sanitize_text_field( $req->get_param( 'post_type' ) );
	$per_page  = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
	$cursor    = (int) $req->get_param( 'cursor' );

	if ( empty( $s ) || mb_strlen( $s ) < 3 ) {
		return new WP_REST_Response(
			array(
				'error'   => 'Query trop courte (min 3 caractères)',
				'results' => array(),
			),
			400
		);
	}

	// Vérifier l'index FULLTEXT
	$index_exists = get_transient( 'swiftboard_fulltext_ok' );
	if ( $index_exists === false ) {
		$check = $wpdb->get_var(
			"SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name = '{$wpdb->posts}'
               AND index_name = 'swiftboard_fulltext'"
		);
		set_transient( 'swiftboard_fulltext_ok', (int) $check, HOUR_IN_SECONDS );
		$index_exists = (int) $check;
	}

	// Construire la requête FULLTEXT
	$boolean_terms = array();
	preg_match_all( '/"([^"]+)"|(\S+)/', $s, $matches, PREG_SET_ORDER );
	foreach ( $matches as $m ) {
		if ( ! empty( $m[1] ) ) {
			$boolean_terms[] = '"' . $m[1] . '"';
		} elseif ( ! empty( $m[2] ) ) {
			$word = $m[2];
			if ( mb_strlen( $word ) < 3 ) {
				continue;
			}
			$boolean_terms[] = $word . '*';
		}
	}
	if ( empty( $boolean_terms ) ) {
		return new WP_REST_Response(
			array(
				'results' => array(),
				'message' => 'Aucun terme valide',
			),
			200
		);
	}
	$against = implode( ' ', $boolean_terms );

	// Construire la clause WHERE
	$where = "post_status = 'publish'";
	if ( $post_type !== 'any' ) {
		$where .= $wpdb->prepare( ' AND post_type = %s', $post_type );
	} else {
		$where .= " AND post_type IN ('topic', 'reply', 'forum', 'post')";
	}
	if ( $cursor > 0 ) {
		$where .= $wpdb->prepare( ' AND ID < %d', $cursor );
	}

	$engine  = 'fulltext';
	$results = array();

	if ( $index_exists ) {
		// Requête FULLTEXT avec score de pertinence
		$sql     = $wpdb->prepare(
			"SELECT ID, post_title, post_type, post_date,
                    MATCH(post_title, post_content) AGAINST(%s IN BOOLEAN MODE) AS relevance
             FROM {$wpdb->posts}
             WHERE {$where}
               AND MATCH(post_title, post_content) AGAINST(%s IN BOOLEAN MODE)
             ORDER BY relevance DESC, post_date DESC
             LIMIT %d",
			$against,
			$against,
			$per_page
		);
		$results = $wpdb->get_results( $sql );
		if ( ! empty( $wpdb->last_error ) ) {
			$index_exists     = 0;
			$results          = array();
			$wpdb->last_error = '';
		}
	}

	if ( ! $index_exists ) {
		$engine  = 'fallback_like';
		$like    = '%' . $wpdb->esc_like( $s ) . '%';
		$sql     = $wpdb->prepare(
			"SELECT ID, post_title, post_type, post_date,
                    0 AS relevance
             FROM {$wpdb->posts}
             WHERE {$where}
               AND (post_title LIKE %s OR post_content LIKE %s)
             ORDER BY post_date DESC
             LIMIT %d",
			$like,
			$like,
			$per_page
		);
		$results = $wpdb->get_results( $sql );
	}

	// Enrichir avec metas + auteur (en batch, anti-N+1)
	$post_ids = wp_list_pluck( $results, 'ID' );
	if ( ! empty( $post_ids ) ) {
		update_meta_cache( 'post', $post_ids );
		_prime_post_caches( $post_ids, false, false );
	}

	$output = array();
	foreach ( $results as $r ) {
		$output[] = array(
			'id'        => (int) $r->ID,
			'title'     => $r->post_title,
			'url'       => get_permalink( $r->ID ),
			'type'      => $r->post_type,
			'date'      => $r->post_date,
			'relevance' => round( (float) $r->relevance, 2 ),
			'excerpt'   => wp_trim_words( wp_strip_all_tags( get_the_content( null, false, $r->ID ) ), 35, '…' ),
		);
	}

	// Prochain cursor
	$next_cursor = 0;
	if ( count( $output ) >= $per_page ) {
		$next_cursor = end( $output )['id'];
	}

	$sb_response = new WP_REST_Response(
		array(
			'query'       => $s,
			'results'     => $output,
			'total'       => count( $output ),
			'next_cursor' => $next_cursor,
			'has_more'    => count( $output ) >= $per_page,
			'engine'      => $engine,
		),
		200
	);
	// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
	// header() emet un warning des que la sortie a commence et
	// echappe aux filtres rest_post_dispatch.
	$sb_response->header( 'Cache-Control', 'public, max-age=30' );
	return $sb_response;
}

// ============================================================================
// 5. AUTOCOMPLETE RAPIDE (AUTOSUGGEST) — POUR LA SEARCH BAR
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/search/suggest',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'swiftboard_rest_public_permission',
				'callback'            => 'swiftboard_rest_search_suggest',
				'args'                => array(
					's' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}
);

/**
 * swiftboard_rest_search_suggest().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return mixed
 */
function swiftboard_rest_search_suggest( WP_REST_Request $req ) {
	global $wpdb;
	$s = sanitize_text_field( $req->get_param( 's' ) );

	if ( mb_strlen( $s ) < 2 ) {
		return new WP_REST_Response( array( 'suggestions' => array() ), 200 );
	}

	// Suggestions façon Reddit : subreddits (forums/sous-forums) avec leur
	// chemin hiérarchique r/... + sujets. Le forum est mis en avant avec un
	// badge « r/ » et le chemin complet.
	$suggestions = array();

	// 1. Forums / sous-forums (subreddits) — avec hiérarchie
	$forums = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_title, post_parent
         FROM {$wpdb->posts}
         WHERE post_status = 'publish'
           AND post_type = 'forum'
           AND post_title LIKE %s
         ORDER BY post_parent ASC, post_title ASC
         LIMIT 6",
			$s . '%'
		)
	);

	// Construire le chemin hiérarchique complet (r/Casablanca/Wydad/Allemagne)
	$path_cache = array();
	$build_path = function ( $forum_id ) use ( &$build_path, &$path_cache ) {
		if ( isset( $path_cache[ $forum_id ] ) ) {
			return $path_cache[ $forum_id ];
		}
		$p = get_post( $forum_id );
		if ( ! $p ) {
			return '';
		}
		$name = $p->post_title;
		if ( $p->post_parent ) {
			$parent = $build_path( $p->post_parent );
			$full   = $parent ? $parent . '/' . $name : $name;
		} else {
			$full = $name;
		}
		$path_cache[ $forum_id ] = $full;
		return $full;
	};

	foreach ( $forums as $f ) {
		$path          = $build_path( (int) $f->ID );
		$suggestions[] = array(
			'type'  => 'subreddit',
			'title' => $path,
			'url'   => get_permalink( (int) $f->ID ),
		);
	}

	// 2. Sujets (topics) — en complément
	$topics = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_title
         FROM {$wpdb->posts}
         WHERE post_status = 'publish'
           AND post_type = 'topic'
           AND post_title LIKE %s
         ORDER BY post_date DESC
         LIMIT 6",
			$s . '%'
		)
	);
	foreach ( $topics as $t ) {
		$suggestions[] = array(
			'type'  => 'topic',
			'title' => $t->post_title,
			'url'   => get_permalink( (int) $t->ID ),
		);
	}

	// Limiter à 8 suggestions au total
	$suggestions = array_slice( $suggestions, 0, 8 );

	// Cache court (60s) — autocomplete n'a pas besoin de fraîcheur max

	$sb_response = new WP_REST_Response(
		array(
			'suggestions' => $suggestions,
		),
		200
	);
	// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
	// header() emet un warning des que la sortie a commence et
	// echappe aux filtres rest_post_dispatch.
	$sb_response->header( 'Cache-Control', 'public, max-age=60' );
	return $sb_response;
}

// ============================================================================
// 6. JS — AUTOCOMPLETE SUR LA SEARCH BAR
// ============================================================================
/*
 * EXI-QUAL-06 — l'autocomplete etait injecte en <script> inline dans
 * wp_footer, ce qui imposait 'unsafe-inline' dans script-src et bloquait le
 * passage de la CSP en enforce. Le code vit desormais dans
 * assets/js/search-suggest.js ; l'endpoint est passe par wp_localize_script.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() ) {
			return;
		}
		wp_enqueue_script(
			'swiftboard-search-suggest',
			SWIFTBOARD_ASSETS . '/js/search-suggest.js',
			array(),
			SWIFTBOARD_VERSION,
			true
		);
	},
	99
);

/**
 * Configuration de l'autocomplete par attributs data-*.
 *
 * wp_localize_script() emettrait un <script> inline, incompatible avec la CSP
 * en enforce (voir la note detaillee dans inc/votes-social.php).
 *
 * @return void
 */
add_action(
	'wp_footer',
	function () {
		if ( is_admin() ) {
			return;
		}
		printf(
			'<div id="sb-search-config" hidden data-endpoint="%s" data-home="%s"></div>',
			esc_attr( esc_url_raw( rest_url( 'swiftboard/v1/search/suggest' ) ) ),
			esc_attr( home_url( '/' ) )
		);
	},
	5
);
