<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard v2 - Module Forum (bbPress Integration)
 *
 * Templates et optimisations pour bbPress.
 * HTML5 sémantique, zéro bloat, 100% lisible par les LLM.
 *
 * PATCHS v2 :
 * - REST API protégée par rate limiting + auth optionnelle
 * - Hook bbPress corrigé (bbp_template_include_always n'existe pas)
 * - Markup custom des topics réellement ACTIVÉ
 * - Breadcrumb bbPress échappé proprement
 *
 * @package SwiftBoard
 * @since 2.0.0
 */
// ============================================================================
// 1. SUPPORT BPRESS
// ============================================================================
/**
 * @return void
 */
function swiftboard_bbpress_setup() {
	add_theme_support( 'bbpress' );
}
add_action( 'after_setup_theme', 'swiftboard_bbpress_setup', 20 );

// ============================================================================
// 2. WRAPPERS HTML5 SÉMANTIQUES - SUPPRIMÉS V2 (code mort qui créait double <main>)
// ============================================================================
// AVANT: swiftboard_bbp_before_main / after_main ouvraient <main id="forum-main">
// Les gabarits archive-forum.php, content-single-forum.php, content-single-topic.php
// ont DÉJÀ leur propre <main id="primary">. Après restauration des hooks en LOT A,
// on aurait eu 2 balises <main> imbriquées = HTML invalide + violation WCAG 1.3.1
// + 2 landmarks main pour lecteur d'écran.
// La suppression EST la correction (pas de garde fiable possible).
// Voir ANALYSE-CODE-MORT-FINALE.md §2 et RAPPORT-VALIDATION-CODE-MORT.md
// Les hooks bbp_template_before/after_* restent utiles pour hot-topics, hero, tri, etc.
// Seuls les wrappers <main> sont retirés.

// ============================================================================
// 3. ARIA SUR LES FORMULAIRES BPRESS — via templates directs (v2.2)
// ============================================================================
// PATCH v2.2 : L'output buffer était incompatible avec les plugins de cache
// (LiteSpeed, WP Rocket, etc.) qui servent du HTML statique avant que
// ob_end_flush() ne s'exécute.
//
// Solution : les ARIA labels sont désormais placés directement dans les
// templates /bbpress/form-topic.php, form-reply.php et form-search.php.
// Aucun output buffer nécessaire — c'est plus fiable, plus rapide, et
// compatible avec tous les plugins de cache.

// ============================================================================
// 4. MARKUP TOPIC OPTIMISÉ LLM — ACTIVÉ (était commenté en v1)
// ============================================================================
// REMOVED v4.0: swiftboard_bbp_topic_markup was dead code (never hooked)
// REMOVED v4.6: closure inutile sur bbp_get_topic_content (audit 06 — code mort)
// (return $content sans altération = add_filter mort)

// ============================================================================
// 5. RECHERCHE FORUM — désactivé (hook bbp_get_search_form inexistant en 2.6.14)
// ============================================================================
// PATCH v2.1 : Le filtre bbp_get_search_form n'existe pas dans bbPress 2.6.14.
// L'amélioration du champ de recherche est désormais gérée via output buffer
// dans swiftboard_bbp_aria_filter_callback() (section 3 ci-dessus).
// On garde aussi le template override /bbpress/form-search.php si besoin.

// ============================================================================
// 6. BREADCRUMB BPRESS — échappement propre (patch v1)
// ============================================================================
/**
 * swiftboard_bbp_breadcrumb().
 *
 * @param mixed $trail  À documenter.
 * @param mixed $crumbs À documenter.
 * @param mixed $r      À documenter.
 * @return mixed
 */
function swiftboard_bbp_breadcrumb( $trail, $crumbs, $r ) {
	if (empty( $crumbs )) return $trail;

	$output  = '<nav aria-label="' . esc_attr__( 'Fil d\'ariane du forum', 'swiftboard' ) . '" class="bbp-breadcrumb">';
	$output .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';

	$position = 1;
	foreach ( $crumbs as $crumb ) {
		if ( ! empty( $crumb ) ) {
			$output .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
			// v4.6 : wp_kses_post sur le crumb (audit 05) au cas où bbPress évolue
			// et injecte du HTML non échappé dans le futur
			$output .= '<span itemprop="name">' . wp_kses_post( $crumb ) . '</span>';
			$output .= '<meta itemprop="position" content="' . $position . '">';
			$output .= '</li>';
			++$position;
		}
	}

	$output .= '</ol></nav>';
	return $output;
}
add_filter( 'bbp_get_breadcrumb', 'swiftboard_bbp_breadcrumb', 10, 3 );

// ============================================================================
// 7. DÉSACTIVER LE SUPERFLU BPRESS
// ============================================================================
// PATCH v2.1 : Les hooks bbp_show_topic_signatures / bbp_show_reply_signatures
// N'EXISTENT PAS dans bbPress 2.6.14 (les signatures ne sont pas une feature native).
// Section supprimée — aucune action à désactiver.
// Si tu utilises un plugin tiers de signatures (ex: bbP Signature), il faudra
// le désactiver depuis ses propres réglages.

// ============================================================================
// 8. COMMENTAIRES HTML POUR LLM
// ============================================================================
/**
 * swiftboard_bbp_reply_markup().
 *
 * @param int $reply_id Identifiant de la réponse. Optionnel.
 * @param int $topic_id Identifiant du sujet. Optionnel.
 * @return void
 */
function swiftboard_bbp_reply_markup( $reply_id = 0, $topic_id = 0 ) {
	if ( ! $reply_id && function_exists( 'bbp_get_reply_id' ) ) {
		$reply_id = bbp_get_reply_id();
	}
	if ( ! $reply_id) return;

	$pos    = function_exists( 'bbp_get_reply_position' ) ? bbp_get_reply_position( $reply_id ) : 0;
	$author = function_exists( 'bbp_get_reply_author_display_name' ) ? bbp_get_reply_author_display_name( $reply_id ) : '';
	?>
	<!-- Reply #<?php echo intval( $pos ); ?> by <?php echo esc_html( $author ); ?> -->
	<?php
}
add_action( 'bbp_theme_before_reply_content', 'swiftboard_bbp_reply_markup', 10, 2 );

// ============================================================================
// 9. PAGINATION ARIA
// ============================================================================
/**
 * swiftboard_bbp_pagination_aria().
 *
 * @param array<string, mixed> $args Arguments, fusionnés avec les valeurs par défaut.
 * @return mixed
 */
function swiftboard_bbp_pagination_aria( $args ) {
	$args['prev_text'] = '<span role="img" aria-label="' . esc_attr__( 'Page précédente', 'swiftboard' ) . '">←</span>';
	$args['next_text'] = '<span role="img" aria-label="' . esc_attr__( 'Page suivante', 'swiftboard' ) . '">→</span>';
	return $args;
}
add_filter( 'bbp_topic_pagination', 'swiftboard_bbp_pagination_aria' );
add_filter( 'bbp_replies_pagination', 'swiftboard_bbp_pagination_aria' );
// PATCH v2.1 : bbp_forum_pagination n'existe pas dans bbPress 2.6.14.
// Les forums utilisent bbp_topic_pagination pour leurs sous-sujets.
// Voir : https://developer.bbpress.org/reference-hooks/bbp_topic_pagination/

// ============================================================================
// 10. REST API POUR LLM — SÉCURISÉE (patch v1)
// ============================================================================
/**
 * @return void
 */
function swiftboard_forum_llm_endpoint() {
	// EXI-BBP-02 : ces deux routes exposent des SUJETS de forum. Sans bbPress,
	// leurs callbacks appellent bbp_get_topic_post_type() et provoquent un
	// fatal (HTTP 500), verifie en desactivant le plugin.
	//
	// On n'enregistre donc pas la route plutot que de la laisser repondre 500 :
	// un endpoint absent renvoie un 404 propre, qui est la reponse correcte
	// quand la fonctionnalite forum n'existe pas sur le site.
	if ( ! function_exists( 'bbp_get_topic_post_type' ) ) {
		return;
	}

	register_rest_route(
		'swiftboard/v1',
		'/forum/topics',
		array(
			'methods'             => 'GET',
			'callback'            => 'swiftboard_llm_forum_topics',
			'permission_callback' => 'swiftboard_rest_public_permission', // public read
			'args'                => array(
				'per_page' => array(
					'type'    => 'integer',
					'default' => 20,
				),
				'page'     => array(
					'type'    => 'integer',
					'default' => 1,
				),
				'forum_id' => array( 'type' => 'integer' ),
			),
		)
	);

	register_rest_route(
		'swiftboard/v1',
		'/forum/topic/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'swiftboard_llm_forum_single_topic',
			'permission_callback' => 'swiftboard_rest_public_permission',
			'args'                => array(
				'id' => array(
					'type'     => 'integer',
					'required' => true,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'swiftboard_forum_llm_endpoint' );

// Rate limiting simple via transients
/**
 * Limite le nombre de requetes par IP anonymisee.
 *
 * @param string $identifier Cle de comptage (ex. « topics », « llm_api »).
 *                           Le type documente etait « int », en contradiction
 *                           avec la valeur par defaut et tous les appelants.
 * @return true|WP_Error Vrai si la requete passe, WP_Error 429 sinon.
 */
function swiftboard_check_rate_limit( $identifier = 'llm_api' ) {
	$ip    = wp_privacy_anonymize_ip( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0' );
	$key   = 'swiftboard_rl_' . $identifier . '_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 60 ) { // 60 req / 60s
		return new WP_Error(
			'rate_limited',
			__( 'Trop de requêtes. Réessayez dans une minute.', 'swiftboard' ),
			array( 'status' => 429 )
		);
	}
	set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	return true;
}

/**
 * swiftboard_llm_forum_topics().
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return mixed
 */
function swiftboard_llm_forum_topics( $request ) {
	$rl = swiftboard_check_rate_limit( 'topics' );
	if (is_wp_error( $rl )) return $rl;

	$per_page = min( max( (int) ( $request->get_param( 'per_page' ) ?: 20 ), 1 ), 20 ); // max 20 (v4.5 : réduit de 50 à 20)
	$page     = max( (int) ( $request->get_param( 'page' ) ?: 1 ), 1 );
	$forum_id = $request->get_param( 'forum_id' );

	// Cache transient 5 min (un LLM qui crawle 1000 topics = 250k requêtes sans cache)
	$cache_key = 'swiftboard_llm_topics_' . md5( wp_json_encode( array( $per_page, $page, $forum_id ) ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		$sb_response = new WP_REST_Response( $cached, 200 );
		// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
		// header() emet un warning des que la sortie a commence et
		// echappe aux filtres rest_post_dispatch.
		$sb_response->header( 'Cache-Control', 'public, max-age=300' );
		return $sb_response;
	}

	$args = array(
		'post_type'      => bbp_get_topic_post_type(),
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'post_status'    => 'publish',
		'no_found_rows'  => false,
	);

	if ( $forum_id ) {
		$args['meta_key']   = '_bbp_forum_id';
		$args['meta_value'] = $forum_id;
	}

	$query  = new WP_Query( $args );
	$topics = array();

	// === Anti-N+1 : précharger metas + users en batch avant la boucle ===
	if ( ! empty( $query->posts ) ) {
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		update_meta_cache( 'post', $post_ids );             // 1 requête pour toutes les metas bbPress
		_prime_post_caches( $post_ids, false, false );      // 1 requête pour les posts parents
		$author_ids = array_unique( array_filter( wp_list_pluck( $query->posts, 'post_author' ) ) );
		if ( ! empty( $author_ids ) ) {
			cache_users( $author_ids );                      // 1 requête pour tous les auteurs
		}
	}

	foreach ( $query->posts as $post ) {
		$topic_id = $post->ID;
		$topics[] = array(
			'id'             => $topic_id,
			'title'          => bbp_get_topic_title( $topic_id ),
			'content'        => mb_substr( wp_strip_all_tags( bbp_get_topic_content( $topic_id ) ), 0, 2000 ),
			'url'            => bbp_get_topic_permalink( $topic_id ),
			'author'         => bbp_get_topic_author_display_name( $topic_id ),
			'date_published' => get_the_date( 'c', $topic_id ),
			'reply_count'    => (int) bbp_get_topic_reply_count( $topic_id ),
			// ISO 8601 au lieu de "4 minutes ago" (audit 07)
			'last_active'    => get_post_modified_time( 'c', false, $topic_id ),
		);
	}

	// v4.6 : format schema.org optionnel (audit 07) — ?schema=1 retourne DiscussionForumPosting[]
	$schema_mode = $request->get_param( 'schema' ) === '1';
	if ( $schema_mode ) {
		$schema_topics = array_map(
			function ( $t ) {
				return array(
					'@type'                => 'DiscussionForumPosting',
					'@id'                  => $t['url'] . '#topic',
					'url'                  => $t['url'],
					'headline'             => $t['title'],
					'articleBody'          => $t['content'],
					'author'               => array(
						'@type' => 'Person',
						'name'  => $t['author'],
					),
					'datePublished'        => $t['date_published'],
					'dateModified'         => $t['last_active'],
					'interactionStatistic' => array(
						array(
							'@type'                => 'InteractionCounter',
							'interactionType'      => 'https://schema.org/ReplyAction',
							'userInteractionCount' => $t['reply_count'],
						),
					),
				);
			},
			$topics
		);
		$response      = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'itemListElement' => array_map(
				function ( $t, $i ) {
					return array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'url'      => $t['url'],
						'name'     => $t['headline'],
					);
				},
				$schema_topics,
				array_keys( $schema_topics )
			),
			'numberOfItems'   => (int) $query->found_posts,
		);
	} else {
		$response = array(
			'topics'   => $topics,
			'total'    => (int) $query->found_posts,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

	$sb_response = new WP_REST_Response( $response, 200 );
	// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
	// header() emet un warning des que la sortie a commence et
	// echappe aux filtres rest_post_dispatch.
	$sb_response->header( 'Cache-Control', 'public, max-age=300' );
	return $sb_response;
}

/**
 * swiftboard_llm_forum_single_topic().
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return mixed
 */
function swiftboard_llm_forum_single_topic( $request ) {
	$rl = swiftboard_check_rate_limit( 'topic' );
	if (is_wp_error( $rl )) return $rl;

	$topic_id = (int) $request->get_param( 'id' );

	// Cache transient 5 min
	$cache_key = 'swiftboard_llm_topic_' . $topic_id;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		$sb_response = new WP_REST_Response( $cached, 200 );
		// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
		// header() emet un warning des que la sortie a commence et
		// echappe aux filtres rest_post_dispatch.
		$sb_response->header( 'Cache-Control', 'public, max-age=300' );
		return $sb_response;
	}

	$topic = get_post( $topic_id );

	if ( ! $topic || $topic->post_type !== bbp_get_topic_post_type() ) {
		return new WP_Error( 'not_found', __( 'Sujet introuvable', 'swiftboard' ), array( 'status' => 404 ) );
	}

	// PATCH v1 : limiter à 50 réponses au lieu de 200 (anti-DoS)
	$replies = get_posts(
		array(
			'post_type'      => bbp_get_reply_post_type(),
			'post_parent'    => $topic_id,
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		)
	);

	// Anti-N+1 : précharger metas + users en batch
	if ( ! empty( $replies ) ) {
		$reply_ids = wp_list_pluck( $replies, 'ID' );
		update_meta_cache( 'post', $reply_ids );
		$author_ids = array_unique( array_filter( wp_list_pluck( $replies, 'post_author' ) ) );
		// Inclure aussi l'auteur du topic
		$author_ids[] = (int) $topic->post_author;
		$author_ids   = array_unique( array_filter( $author_ids ) );
		if ( ! empty( $author_ids ) ) {
			cache_users( $author_ids );
		}
	}

	$reply_data = array();
	foreach ( $replies as $reply ) {
		$reply_id     = $reply->ID;
		$reply_data[] = array(
			'id'       => $reply_id,
			'position' => bbp_get_reply_position( $reply_id ),
			'author'   => bbp_get_reply_author_display_name( $reply_id ),
			'content'  => mb_substr( wp_strip_all_tags( $reply->post_content ), 0, 2000 ),
			'date'     => get_the_date( 'c', $reply_id ),
		);
	}

	$response = array(
		'topic'       => array(
			'id'             => $topic_id,
			'title'          => bbp_get_topic_title( $topic_id ),
			'content'        => mb_substr( wp_strip_all_tags( bbp_get_topic_content( $topic_id ) ), 0, 5000 ),
			'url'            => bbp_get_topic_permalink( $topic_id ),
			'author'         => bbp_get_topic_author_display_name( $topic_id ),
			'date_published' => get_the_date( 'c', $topic_id ),
		),
		'replies'     => $reply_data,
		'reply_count' => count( $reply_data ),
	);

	set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

	$sb_response = new WP_REST_Response( $response, 200 );
	// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
	// header() emet un warning des que la sortie a commence et
	// echappe aux filtres rest_post_dispatch.
	$sb_response->header( 'Cache-Control', 'public, max-age=300' );
	return $sb_response;
}

// ============================================================================
// INVALIDATION DES CACHES LLM + SCHEMA QUAND UN TOPIC/REPLY EST MODIFIÉ
// ============================================================================
add_action(
	'save_post_topic',
	function ( $post_id, $post, $update ) {
		delete_transient( 'swiftboard_llm_topic_' . $post_id );
		delete_transient( 'swiftboard_schema_topic_' . $post_id );
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_swiftboard_llm_topics_%'" );
	},
	10,
	3
);

add_action(
	'save_post_reply',
	function ( $post_id, $post, $update ) {
		$topic_id = (int) $post->post_parent;
		if ( $topic_id ) {
			delete_transient( 'swiftboard_llm_topic_' . $topic_id );
			delete_transient( 'swiftboard_schema_topic_' . $topic_id );
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_swiftboard_llm_topics_%'" );
		}
	},
	10,
	3
);

