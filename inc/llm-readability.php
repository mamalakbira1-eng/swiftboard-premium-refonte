<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard - LLM Readability Enhancer
 *
 * Améliore la lisibilité du contenu par les LLMs (ChatGPT, Claude, Gemini) :
 *
 * 1. JSON-LD enrichi pour chaque topic (DiscussionForumPosting avec :
 *    - upvoteCount, downvoteCount
 *    - commentCount + nested comments
 *    - author avec @type Person + grade
 *    - datePublished + dateModified
 *    - keywords (topic tags)
 *    - inLanguage
 *
 * 2. JSON-LD pour chaque reply (Comment avec :
 *    - parentItem (topic)
 *    - upvoteCount
 *    - author + grade
 *    - text content nettoyé)
 *
 * 3. JSON-LD pour chaque forum (CollectionPage avec :
 *    - numberOfItems
 *    - itemListElement)
 *
 * 4. Meta tags additionnels pour LLMs :
 *    - article:section, article:tag, article:published_time
 *    - citation_title, citation_author (pour les LLMs académiques)
 *
 * 5. Fichier /llm-index.json — index complet du forum pour crawling LLM
 *
 * 6. Endpoint REST /swiftboard/v1/llm/topic/{id} — contenu structuré
 *    pour ingestion facile par un LLM
 *
 * @package SwiftBoard
 * @since 3.5.0
 */
// ============================================================================
// 1. JSON-LD ENRICHI POUR TOPIC (DiscussionForumPosting complet)
// ============================================================================
add_filter(
	'swiftboard_schema_topic',
	function ( $schema, $topic_id ) {
		$topic = get_post( $topic_id );
		if ( ! $topic ) {
			return $schema;
		}

		$author_id = (int) $topic->post_author;
		// v4.6.1 : guard function_exists (swiftboard_get_user_grade est dans admin-settings-grades.php, admin-only)
		$grade      = function_exists( 'swiftboard_get_user_grade' ) ? swiftboard_get_user_grade( $author_id ) : 'member';
		$grades     = function_exists( 'swiftboard_get_grades' ) ? swiftboard_get_grades() : array();
		$grade_info = $grades[ $grade ] ?? null;

		$vote_score  = function_exists( 'swiftboard_get_vote_count' ) ? swiftboard_get_vote_count( $topic_id ) : 0;
		$reply_count = function_exists( 'bbp_get_topic_reply_count' ) ? (int) bbp_get_topic_reply_count( $topic_id, true ) : 0;

		// Récupérer upvotes et downvotes séparément
		$up   = (int) get_post_meta( $topic_id, '_swiftboard_vote_up', true );
		$down = (int) get_post_meta( $topic_id, '_swiftboard_vote_down', true );

		// Tags
		$tags     = wp_get_post_terms( $topic_id, 'topic-tag', array( 'fields' => 'names' ) );
		$keywords = is_array( $tags ) ? implode( ', ', $tags ) : '';

		// Forum parent
		$forum_id   = wp_get_post_parent_id( $topic_id );
		$forum_name = $forum_id ? get_the_title( $forum_id ) : '';

		// Image attachée
		$image_url = get_post_meta( $topic_id, '_swiftboard_image_url', true );

		// Enrichir le schema existant
		$schema['@type']         = 'DiscussionForumPosting';
		$schema['@id']           = get_permalink( $topic_id ) . '#topic';
		$schema['headline']      = $topic->post_title;
		$schema['text']          = wp_strip_all_tags( $topic->post_content );
		$schema['url']           = get_permalink( $topic_id );
		$schema['inLanguage']    = 'fr-FR';
		$schema['datePublished'] = mysql2date( 'c', $topic->post_date_gmt );
		$schema['dateModified']  = mysql2date( 'c', $topic->post_modified_gmt );
		$schema['author']        = array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $author_id ),
			'url'   => function_exists( 'bbp_get_user_profile_url' ) ? bbp_get_user_profile_url( $author_id ) : '',
		);

		// Grade de l'auteur (extension Schema.org)
		$schema['author']['identifier']  = $grade;
		$schema['author']['description'] = $grade_info ? $grade_info['icon'] . ' ' . $grade_info['name'] : '';

		// Votes
		$schema['upvoteCount']          = $up;
		$schema['downvoteCount']        = $down;
		$schema['interactionStatistic'] = array(
			array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => 'https://schema.org/LikeAction',
				'userInteractionCount' => $up,
			),
			array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => 'https://schema.org/ReplyAction',
				'userInteractionCount' => $reply_count,
			),
		);

		// Commentaires (nested)
		$schema['commentCount'] = $reply_count;
		$schema['comment']      = swiftboard_get_topic_comments_schema( $topic_id );

		// Forum (isPartOf)
		if ( $forum_id ) {
			$schema['isPartOf'] = array(
				'@type' => 'CollectionPage',
				'name'  => $forum_name,
				'url'   => get_permalink( $forum_id ),
			);
		}

		// Keywords
		if ( $keywords ) {
			$schema['keywords'] = $keywords;
		}

		// Image
		if ( $image_url ) {
			$schema['image'] = $image_url;
		}

		return $schema;
	},
	10,
	2
);

// ============================================================================
// 2. SCHEMA POUR LES COMMENTAIRES (nested)
// ============================================================================
/**
 * swiftboard_get_topic_comments_schema().
 *
 * @param int $topic_id Identifiant du sujet.
 * @return mixed
 */
function swiftboard_get_topic_comments_schema( $topic_id ) {
	if ( ! function_exists( 'bbp_get_topic' ) ) {
		return array();
	}

	$replies = get_posts(
		array(
			'post_type'      => 'reply',
			'post_status'    => 'publish',
			'post_parent'    => $topic_id,
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	);

	if ( empty( $replies ) ) {
		return array();
	}

	// Alignement du JSON-LD sur le rendu visible : le fil est trié par "Best"
	// (score de votes) par défaut. Pour que les moteurs/IA lisent le même ordre
	// qu'un visiteur, on trie les replies par score de votes décroissant.
	// Les contenus sans score (jamais votés) tombent en fin de liste.
	$replies = array_map(
		function ( $r ) {
			$r->swiftboard_score = (int) get_post_meta( $r->ID, '_swiftboard_vote_score', true );
			return $r;
		},
		$replies
	);
	usort(
		$replies,
		function ( $a, $b ) {
			return $b->swiftboard_score - $a->swiftboard_score;
		}
	);

	$comments = array();
	$noeuds   = array();  // index par ID, pour rattacher les reponses imbriquees
	foreach ( $replies as $reply ) {
		$author_id  = (int) $reply->post_author;
		$grade      = swiftboard_get_user_grade( $author_id );
		$grades     = swiftboard_get_grades();
		$grade_info = $grades[ $grade ] ?? null;

		// up ET down : un score net seul est ambigu pour un lecteur
		// automatique (0 = aucun vote, ou +50/-50 ?).
		$v    = function_exists( 'swiftboard_get_vote_breakdown' )
			? swiftboard_get_vote_breakdown( $reply->ID )
			: array(
				'up'   => (int) get_post_meta( $reply->ID, '_swiftboard_vote_up', true ),
				'down' => 0,
			);
		$up   = (int) $v['up'];
		$down = (int) $v['down'];

		$noeuds[ $reply->ID ] = array(
			'@type'                => 'Comment',
			'@id'                  => get_permalink( $topic_id ) . '#reply-' . $reply->ID,
			'text'                 => wp_strip_all_tags( $reply->post_content ),
			'datePublished'        => mysql2date( 'c', $reply->post_date_gmt ),
			'author'               => array(
				'@type'       => 'Person',
				'name'        => get_the_author_meta( 'display_name', $author_id ),
				'identifier'  => $grade,
				'description' => $grade_info ? $grade_info['icon'] . ' ' . $grade_info['name'] : '',
			),
			'upvoteCount'          => $up,
			'downvoteCount'        => $down,
			'interactionStatistic' => array(
				array(
					'@type'                => 'InteractionCounter',
					'interactionType'      => 'https://schema.org/LikeAction',
					'userInteractionCount' => $up,
				),
				array(
					'@type'                => 'InteractionCounter',
					'interactionType'      => 'https://schema.org/DislikeAction',
					'userInteractionCount' => $down,
				),
			),
			'url'                  => get_permalink( $topic_id ) . '#reply-' . $reply->ID,
		);
	}

	// Imbrication : une reponse a une reponse (_bbp_reply_to) doit apparaitre
	// DANS son parent, pas au meme niveau. Sans cela le fil est aplati et un
	// LLM ne peut plus reconstituer « qui repond a qui ».
	// Un parent absent fait retomber la reponse a la racine plutot que de la
	// faire disparaitre.
	foreach ( $replies as $reply ) {
		$parent = (int) get_post_meta( $reply->ID, '_bbp_reply_to', true );

		if ( $parent && isset( $noeuds[ $parent ] ) ) {
			$noeuds[ $reply->ID ]['parentItem'] = array(
				'@type' => 'Comment',
				'@id'   => get_permalink( $topic_id ) . '#reply-' . $parent,
			);
			$noeuds[ $parent ]['comment'][]     = &$noeuds[ $reply->ID ];
		} else {
			$comments[] = &$noeuds[ $reply->ID ];
		}
	}

	return $comments;
}

// ============================================================================
// 3. JSON-LD POUR FORUM (CollectionPage enrichie)
// ============================================================================
add_filter(
	'swiftboard_schema_forum',
	function ( $schema, $forum_id ) {
		$forum = get_post( $forum_id );
		if ( ! $forum ) {
			return $schema;
		}

		$topic_count = function_exists( 'bbp_get_forum_topic_count' ) ? (int) bbp_get_forum_topic_count( $forum_id, true, true ) : 0;
		$reply_count = function_exists( 'bbp_get_forum_reply_count' ) ? (int) bbp_get_forum_reply_count( $forum_id, true, true ) : 0;

		// Liste des topics (top 20)
		// meta_key + orderby=meta_value_num produit un INNER JOIN : tout sujet
		// jamais vote (donc sans _swiftboard_vote_score) DISPARAIT du resultat.
		// Meme defaut que celui corrige dans nested-comments.php.
		$args_topics = array(
			'post_type'      => 'topic',
			'post_status'    => 'publish',
			'post_parent'    => $forum_id,
			'posts_per_page' => 20,
			'orderby'        => 'meta_value_num',
			'meta_key'       => '_swiftboard_vote_score',
			'order'          => 'DESC',
		);
		if ( function_exists( 'swiftboard_trier_par_meta_numerique' ) ) {
			swiftboard_trier_par_meta_numerique( $args_topics, '_swiftboard_vote_score' );
		}
		$topics = get_posts( $args_topics );

		$item_list = array();
		foreach ( $topics as $i => $t ) {
			$vote_score  = function_exists( 'swiftboard_get_vote_count' ) ? swiftboard_get_vote_count( $t->ID ) : 0;
			$item_list[] = array(
				'@type'       => 'ListItem',
				'position'    => $i + 1,
				'url'         => get_permalink( $t->ID ),
				'name'        => $t->post_title,
				'upvoteCount' => $vote_score,
			);
		}

		$schema['@type']           = 'CollectionPage';
		$schema['@id']             = get_permalink( $forum_id ) . '#forum';
		$schema['name']            = $forum->post_title;
		$schema['description']     = wp_strip_all_tags( $forum->post_content );
		$schema['url']             = get_permalink( $forum_id );
		$schema['inLanguage']      = 'fr-FR';
		$schema['numberOfItems']   = $topic_count;
		$schema['commentCount']    = $reply_count;
		$schema['itemListElement'] = $item_list;

		return $schema;
	},
	10,
	2
);

// ============================================================================
// 4. META TAGS ADDITIONNELS POUR LLMs
// ============================================================================
add_action(
	'wp_head',
	function () {
		if ( ! is_singular() && ! function_exists( 'bbp_is_single_topic' ) ) {
			return;
		}

		$topic_id = 0;
		if ( function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic() ) {
			$topic_id = bbp_get_topic_id();
		} elseif ( function_exists( 'bbp_is_single_forum' ) && bbp_is_single_forum() ) {
			$topic_id = bbp_get_forum_id();
		}

		if ( ! $topic_id ) {
			return;
		}

		$post = get_post( $topic_id );
		if ( ! $post ) {
			return;
		}

		// Article meta tags (utiles pour LLMs)
		echo '<meta property="article:published_time" content="' . esc_attr( mysql2date( 'c', $post->post_date_gmt ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( mysql2date( 'c', $post->post_modified_gmt ) ) . '">' . "\n";
		echo '<meta name="citation_title" content="' . esc_attr( get_the_title( $topic_id ) ) . '">' . "\n";
		echo '<meta name="citation_author" content="' . esc_attr( get_the_author_meta( 'display_name', (int) $post->post_author ) ) . '">' . "\n";

		// Forum name as section
		$forum_id = wp_get_post_parent_id( $topic_id );
		if ( $forum_id ) {
			echo '<meta property="article:section" content="' . esc_attr( get_the_title( $forum_id ) ) . '">' . "\n";
		}

		// Tags
		$tags = wp_get_post_terms( $topic_id, 'topic-tag', array( 'fields' => 'names' ) );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				echo '<meta property="article:tag" content="' . esc_attr( $tag ) . '">' . "\n";
			}
		}

		// Citation meta (pour LLMs académiques)
	},
	20
);

// ============================================================================
// 5. FICHIER /llm-index.json — INDEX COMPLET DU FORUM
// ============================================================================
/**
 * Génère un index JSON du forum pour crawling facile par les LLMs.
 * Accessible sur /llm-index.json
 */
add_action(
	'init',
	function () {
		add_rewrite_rule( '^llm-index\.json$', 'index.php?swiftboard_llm_index=1', 'top' );
	}
);

/**
 * Empeche l'ajout d'un slash final a /llm-index.json.
 *
 * DEFAUT CORRIGE (mesure au curl, pas supposition).
 *
 * `redirect_canonical()` du coeur ajoute un slash final a toute URL qu'il ne
 * reconnait pas comme un fichier. La regle de reecriture ci-dessus n'etant
 * pas un vrai fichier sur le disque, il transformait :
 *
 *     GET /llm-index.json   ->  301  ->  /llm-index.json/
 *
 * Mesure avant correction : HTTP 301 sur l'URL documentee, 200 seulement sur
 * la variante avec slash. Un crawler qui ne suit pas les redirections — et
 * beaucoup de clients d'API n'en suivent pas — recevait un corps vide.
 * L'URL annoncee par `swiftboard_rest_llm_sitemap()` est pourtant bien
 * `/llm-index.json`, sans slash.
 *
 * @param string $redirection URL vers laquelle le coeur veut rediriger.
 * @return string|false Chaine vide/false pour annuler la redirection.
 */
function swiftboard_llm_index_sans_slash( $redirection ) {
	if ( get_query_var( 'swiftboard_llm_index' ) ) {
		return false;
	}
	return $redirection;
}
add_filter( 'redirect_canonical', 'swiftboard_llm_index_sans_slash' );
add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'swiftboard_llm_index';
		return $vars;
	}
);
add_action(
	'template_redirect',
	function () {
		if ( ! get_query_var( 'swiftboard_llm_index' ) ) {
			return;
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );

		$forums = get_posts(
			array(
				'post_type'      => 'forum',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);

		$index = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'WebSite',
			'name'        => get_bloginfo( 'name' ),
			'url'         => home_url( '/' ),
			'description' => get_bloginfo( 'description' ),
			'inLanguage'  => 'fr-FR',
			'lastUpdated' => current_time( 'c' ),
			'forums'      => array(),
		);

		foreach ( $forums as $forum ) {
			$topic_count = function_exists( 'bbp_get_forum_topic_count' ) ? (int) bbp_get_forum_topic_count( $forum->ID, true, true ) : 0;

			$topics = get_posts(
				array(
					'post_type'      => 'topic',
					'post_status'    => 'publish',
					'post_parent'    => $forum->ID,
					'posts_per_page' => 50,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);

			$topic_list = array();
			foreach ( $topics as $t ) {
				$vote_score  = function_exists( 'swiftboard_get_vote_count' ) ? swiftboard_get_vote_count( $t->ID ) : 0;
				$reply_count = function_exists( 'bbp_get_topic_reply_count' ) ? (int) bbp_get_topic_reply_count( $t->ID, true ) : 0;

				$topic_list[] = array(
					'id'      => (int) $t->ID,
					'title'   => $t->post_title,
					'url'     => get_permalink( $t->ID ),
					'author'  => get_the_author_meta( 'display_name', (int) $t->post_author ),
					'date'    => mysql2date( 'c', $t->post_date_gmt ),
					'upvotes' => $vote_score,
					'replies' => $reply_count,
					'excerpt' => wp_trim_words( wp_strip_all_tags( $t->post_content ), 30 ),
				);
			}

			$index['forums'][] = array(
				'@type'      => 'CollectionPage',
				'id'         => (int) $forum->ID,
				'name'       => $forum->post_title,
				'url'        => get_permalink( $forum->ID ),
				'topicCount' => $topic_count,
				'topics'     => $topic_list,
			);
		}

		echo wp_json_encode( $index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}
);

// ============================================================================
// 6. ENDPOINT REST — /swiftboard/v1/llm/topic/{id}
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/llm/topic/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_rest_llm_topic',
				'permission_callback' => 'swiftboard_rest_public_permission',
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
			'/llm/forum/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_rest_llm_forum',
				'permission_callback' => 'swiftboard_rest_public_permission',
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
			'/llm/sitemap',
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_rest_llm_sitemap',
				'permission_callback' => 'swiftboard_rest_public_permission',
			)
		);
	}
);




// ============================================================================
// 7. FLUSH REWRITE RULES À L'ACTIVATION
// ============================================================================
add_action(
	'after_switch_theme',
	function () {
		flush_rewrite_rules();
	}
);
