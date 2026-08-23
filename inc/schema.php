<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard v2 - Module Schema.org (JSON-LD)
 *
 * Données structurées pour SEO + LLM :
 * - WebSite + SearchAction
 * - BlogPosting (article de blog)
 * - DiscussionForumPosting (topics bbPress)
 * - BreadcrumbList
 *
 * @package SwiftBoard
 * @since 2.0.0
 */
// ============================================================================
// 1. SCHEMA WEB SITE (global)
// ============================================================================
/**
 * @return void
 */
function swiftboard_schema_website() {
	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'WebSite',
		'name'            => get_bloginfo( 'name' ),
		'url'             => home_url(),
		'inLanguage'      => get_locale(),
		'potentialAction' => array(
			array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		),
	);

	// Le slogan n'est ajouté que s'il EXISTE.
	//
	// `get_bloginfo('description')` renvoie une chaîne vide tant que
	// l'administrateur n'a pas renseigné le slogan — c'est le cas par défaut
	// d'une installation neuve. Émettre `"description": ""` ne rend pas la
	// page invisible, mais disqualifie l'entité des résultats enrichis tout en
	// donnant l'illusion d'un balisage complet.
	//
	// Le guide Google « Optimizing your website for generative AI features on
	// Google Search » précise que les données structurées ne sont PAS requises
	// pour la recherche générative, mais qu'il reste judicieux de les tenir à
	// jour « as it helps with being eligible for rich results ». D'où la règle :
	// une propriété facultative se voit OMISE, jamais émise vide.
	//
	// Cette règle était déjà appliquée plus bas à `author.description` ; elle
	// ne l'était pas ici. Mesuré par SIM-10 sur le site réel.
	$slogan = trim( (string) get_bloginfo( 'description' ) );
	if ( $slogan !== '' ) {
		$schema['description'] = $slogan;
	}

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . '</script>' . "\n";
}
add_action( 'wp_head', 'swiftboard_schema_website', 5 );

// ============================================================================
// 2. SCHEMA PAR TYPE DE PAGE
// ============================================================================
/**
 * @return void
 */
function swiftboard_schema_page() {

	// Exclure la home page (front-page.php gère son propre schema WebSite)
	if ( ( is_singular( 'post' ) || is_page() ) && ! is_front_page() ) {
		swiftboard_schema_article();
	}

	if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		if ( bbp_is_single_forum() ) {
			swiftboard_schema_forum();
		} elseif ( bbp_is_single_topic() ) {
			swiftboard_schema_topic();
		} elseif ( bbp_is_forum_archive() ) {
			swiftboard_schema_forum_index();
		}
	}

	if ( ! is_front_page() ) {
		swiftboard_schema_breadcrumbs();
	}
}
add_action( 'wp_head', 'swiftboard_schema_page', 10 );

// ============================================================================
// 3. SCHEMA ARTICLE (BlogPosting)
// ============================================================================
/**
 * @return void
 */
function swiftboard_schema_article() {
	global $post;
	if ( ! $post ) {
		return;
	}

	$author_id   = $post->post_author;
	$author_name = get_the_author_meta( 'display_name', $author_id );

	$schema = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'BlogPosting',
		'@id'           => get_permalink() . '#article',
		'headline'      => get_the_title(),
		'description'   => has_excerpt() ? get_the_excerpt() : wp_trim_words( strip_shortcodes( strip_tags( $post->post_content ) ), 30 ),
		'datePublished' => get_the_date( 'c' ),
		'dateModified'  => get_the_modified_date( 'c' ),
		'author'        => array(
			'@type' => 'Person',
			'name'  => $author_name,
			'url'   => get_author_posts_url( $author_id ),
		),
		'publisher'     => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		),
		'url'           => get_permalink(),
		'inLanguage'    => get_locale(),
		'wordCount'     => str_word_count( strip_tags( $post->post_content ) ),
	);

	if ( get_the_author_meta( 'description', $author_id ) ) {
		$schema['author']['description'] = get_the_author_meta( 'description', $author_id );
	}

	if ( has_post_thumbnail() ) {
		$img_id   = get_post_thumbnail_id();
		$img_url  = wp_get_attachment_image_url( $img_id, 'large' );
		$img_data = wp_get_attachment_image_src( $img_id, 'large' );
		if ( $img_url ) {
			$schema['image'] = array(
				'@type'  => 'ImageObject',
				'url'    => $img_url,
				'width'  => $img_data[1] ?? 0,
				'height' => $img_data[2] ?? 0,
			);
		}
	}

	$categories = get_the_category();
	if ( ! empty( $categories ) ) {
		$schema['keywords'] = array_map(
			function ( $cat ) {
				return $cat->name;
			},
			$categories
		);
	}

	$clean_content         = wp_strip_all_tags( $post->post_content );
	$clean_content         = html_entity_decode( $clean_content, ENT_QUOTES, 'UTF-8' );
	$schema['articleBody'] = mb_substr( $clean_content, 0, 5000 );

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . '</script>' . "\n";
}

// ============================================================================
// 4. SCHEMA FORUM INDEX (CollectionPage)
// ============================================================================
/**
 * @return void
 */
function swiftboard_schema_forum_index() {
	// PATCH v2.1 : bbp_get_forum_archive_link() n'existe pas en 2.6.14.
	// On utilise bbp_forums_url() à la place.
	$archive_link = function_exists( 'bbp_get_forums_url' ) ? bbp_get_forums_url( '/' ) : home_url( '/forum/' );

	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'CollectionPage',
		'@id'             => $archive_link . '#collection',
		'name'            => __( 'Forum', 'swiftboard' ) . ' — ' . get_bloginfo( 'name' ),
		'description'     => __( 'Forum de discussion communautaire', 'swiftboard' ),
		'url'             => $archive_link,
		'inLanguage'      => get_locale(),
		'itemListElement' => array(), // v4.6 : Liste des forums (audit 07 — ItemList standard)
	);

	// PATCH v2.1 : bbp_get_all_forums() n'existe pas en 2.6.14.
	// On récupère les forums via WP_Query sur le post type bbp_get_forum_post_type().
	// Réduit à 20 (au lieu de 100) pour limiter la taille du JSON-LD dans le <head>.
	if ( function_exists( 'bbp_get_forum_post_type' ) ) {
		$forums_query = new WP_Query(
			array(
				'post_type'      => bbp_get_forum_post_type(),
				'posts_per_page' => 20,
				'post_parent'    => 0, // Forums racines uniquement
				'post_status'    => 'publish',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $forums_query->posts ) ) {
			// Anti-N+1 : précharger les metas bbPress en batch
			$forum_ids = wp_list_pluck( $forums_query->posts, 'ID' );
			update_meta_cache( 'post', $forum_ids );
			// v4.6 : utiliser itemListElement ListItem (audit 07) au lieu de DiscussionForumPosting dans hasPart
			// CollectionPage.hasPart doit contenir des CreativeWork, mais pour les forums
			// le standard recommandé est itemListElement ListItem pour un index de forums
			$position = 1;
			foreach ( $forums_query->posts as $forum ) {
				$schema['itemListElement'][] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'url'      => bbp_get_forum_permalink( $forum->ID ),
					'name'     => bbp_get_forum_title( $forum->ID ),
				);
			}
		}
	}

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . '</script>' . "\n";
}

// ============================================================================
// 5. SCHEMA FORUM INDIVIDUEL
// ============================================================================
/**
 * @return void
 */
function swiftboard_schema_forum() {
	$forum_id = bbp_get_forum_id();

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'DiscussionForumPosting',
		'@id'         => bbp_get_forum_permalink( $forum_id ) . '#forum',
		'headline'    => bbp_get_forum_title( $forum_id ),
		'text'        => bbp_get_forum_content( $forum_id ) ? wp_strip_all_tags( bbp_get_forum_content( $forum_id ) ) : '',
		'url'         => bbp_get_forum_permalink( $forum_id ),
		'inLanguage'  => get_locale(),
		'author'      => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		),
		'publisher'   => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		),
		'dateCreated' => get_the_date( 'c', $forum_id ),
	);

	// Permet aux modules d'enrichir le schema forum (llm-readability.php ajoute isPartOf, keywords, image)
	$schema = apply_filters( 'swiftboard_schema_forum', $schema, $forum_id );

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . '</script>' . "\n";
}

// ============================================================================
// 6. SCHEMA TOPIC
// ============================================================================
/**
 * @return void
 */
function swiftboard_schema_topic() {
	$topic_id = bbp_get_topic_id();

	// Le cache est LU ici. Il etait auparavant ecrit a chaque affichage sans
	// jamais etre relu : le commentaire annoncait « evite 150 requetes SQL »
	// alors que le cout mesure restait identique (28 requetes avec ou sans
	// cache, cache d'objets vide), plus une ecriture en base par page vue.
	// Un audit independant a mesure 15 requetes par appel sur son jeu de
	// donnees et qualifie ce correctif de plus rentable du theme pour le TTFB.
	$cache_key = 'swiftboard_schema_topic_' . $topic_id;
	$cache     = $topic_id ? get_transient( $cache_key ) : false;

	if ( is_array( $cache ) && ! empty( $cache ) ) {
		echo '<script type="application/ld+json">'
			. wp_json_encode( $cache, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS )
			. '</script>' . "\n";
		return;
	}

	$topic_title   = bbp_get_topic_title( $topic_id );
	$topic_content = bbp_get_topic_content( $topic_id );
	$author_name   = bbp_get_topic_author_display_name( $topic_id );

	$schema = array(
		'@context'             => 'https://schema.org',
		'@type'                => 'DiscussionForumPosting',
		'@id'                  => bbp_get_topic_permalink( $topic_id ) . '#topic',
		'headline'             => $topic_title,
		'text'                 => wp_strip_all_tags( $topic_content ),
		'url'                  => bbp_get_topic_permalink( $topic_id ),
		'datePublished'        => get_the_date( 'c', $topic_id ),
		'dateModified'         => get_the_modified_date( 'c', $topic_id ),
		'inLanguage'           => get_locale(),
		'author'               => array(
			'@type' => 'Person',
			'name'  => $author_name,
		),
		'publisher'            => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		),
		'interactionStatistic' => array(
			array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => 'https://schema.org/ReplyAction',
				'userInteractionCount' => (int) bbp_get_topic_reply_count( $topic_id, true ),
			),
			array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => 'https://schema.org/ViewAction',
				'userInteractionCount' => (int) get_post_meta( $topic_id, '_bbp_voice_count', true ) ?: 0,
			),
		),
		'comment'              => array(),
	);

	$replies = get_posts(
		array(
			'post_type'      => bbp_get_reply_post_type(),
			'post_parent'    => $topic_id,
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'post_status'    => function_exists( 'bbp_get_public_status_id' ) ? bbp_get_public_status_id() : 'publish',
		)
	);

	// === Anti-N+1 : précharger metas + users en batch avant la boucle ===
	if ( ! empty( $replies ) ) {
		$reply_ids = wp_list_pluck( $replies, 'ID' );
		update_meta_cache( 'post', $reply_ids );           // 1 requête pour toutes les metas bbPress
		$author_ids = array_unique( array_filter( wp_list_pluck( $replies, 'post_author' ) ) );
		if ( ! empty( $author_ids ) ) {
			cache_users( $author_ids );                     // 1 requête pour tous les auteurs
		}
	}

	// Construction des commentaires AVEC leurs votes et leur hierarchie.
	//
	// Deux manques corriges ici :
	// 1. aucun compteur de vote n'etait expose par commentaire — un LLM
	// voyait le fil mais pas ce que la communaute en pensait, donc
	// impossible de distinguer une reponse plebiscitee d'une reponse
	// rejetee ;
	// 2. le fil etait APLATI : une reponse a une reponse (_bbp_reply_to)
	// apparaissait au meme niveau que les autres, ce qui fait perdre
	// « qui repond a qui ». On imbrique donc les Comment enfants dans
	// le champ `comment` de leur parent, conformement a schema.org.
	$par_id = array();

	foreach ( $replies as $reply ) {
		$reply_author_name = bbp_get_reply_author_display_name( $reply->ID );

		$noeud = array(
			'@type'       => 'Comment',
			'@id'         => bbp_get_reply_url( $reply->ID ) . '#reply',
			'author'      => array(
				'@type' => 'Person',
				'name'  => $reply_author_name,
			),
			'text'        => wp_strip_all_tags( $reply->post_content ),
			'dateCreated' => get_the_date( 'c', $reply->ID ),
			'position'    => bbp_get_reply_position( $reply->ID ),
		);

		// Votes reels du commentaire. On expose up et down separement :
		// le score net seul ne permet pas de distinguer 0 vote d'un +50/-50.
		if ( function_exists( 'swiftboard_get_vote_breakdown' ) ) {
			$v                             = swiftboard_get_vote_breakdown( $reply->ID );
			$noeud['upvoteCount']          = (int) $v['up'];
			$noeud['downvoteCount']        = (int) $v['down'];
			$noeud['interactionStatistic'] = array(
				array(
					'@type'                => 'InteractionCounter',
					'interactionType'      => 'https://schema.org/LikeAction',
					'userInteractionCount' => (int) $v['up'],
				),
				array(
					'@type'                => 'InteractionCounter',
					'interactionType'      => 'https://schema.org/DislikeAction',
					'userInteractionCount' => (int) $v['down'],
				),
			);
		}

		$par_id[ $reply->ID ] = $noeud;
	}

	// Imbrication : on rattache chaque reponse a son parent (_bbp_reply_to).
	// Un parent absent ou hors page fait retomber la reponse a la racine
	// plutot que de la faire disparaitre du schema.
	foreach ( $replies as $reply ) {
		$parent = (int) get_post_meta( $reply->ID, '_bbp_reply_to', true );

		if ( $parent && isset( $par_id[ $parent ] ) ) {
			$par_id[ $reply->ID ]['parentItem'] = array(
				'@type' => 'Comment',
				'@id'   => bbp_get_reply_url( $parent ) . '#reply',
			);
			$par_id[ $parent ]['comment'][]     = &$par_id[ $reply->ID ];
		} else {
			$schema['comment'][] = &$par_id[ $reply->ID ];
		}
	}
	unset( $par_id );

	// Permet aux modules d'enrichir le schema topic (llm-readability.php ajoute upvoteCount, downvoteCount, isPartOf, keywords, image)
	$schema = apply_filters( 'swiftboard_schema_topic', $schema, $topic_id );

	// Cache transient court (60s au lieu de 10 min). Une duree de 10 min
	// rendait les mises a jour (reponses, votes) invisibles longtemps.
	set_transient( $cache_key, $schema, MINUTE_IN_SECONDS );

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . '</script>' . "\n";

	// FAQPage schema for solved topics (Best Answer)
	// Google FAQ Rich Results = more visibility + more clicks
	swiftboard_schema_faqpage( $topic_id );
}

/**
 * Emit FAQPage schema if topic has a Best Answer.
 * Runs alongside DiscussionForumPosting, not replacing it.
 *
 * @param int $topic_id Topic ID.
 * @return void
 */
function swiftboard_schema_faqpage( $topic_id ) {
	if ( ! function_exists( 'swiftboard_get_best_answer_id' ) ) {
		return;
	}

	$best_id = swiftboard_get_best_answer_id( $topic_id );
	if ( ! $best_id ) {
		return;
	}

	$best_reply = get_post( $best_id );
	if ( ! $best_reply ) {
		return;
	}

	$topic = get_post( $topic_id );
	if ( ! $topic ) {
		return;
	}

	$faq_schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array(
			array(
				'@type'          => 'Question',
				'name'           => $topic->post_title,
				'acceptedAnswer' => array(
					'@type'       => 'Answer',
					'text'        => wp_strip_all_tags( $best_reply->post_content ),
					'author'      => array(
						'@type' => 'Person',
						'name'  => get_the_author_meta( 'display_name', (int) $best_reply->post_author ),
					),
					'dateCreated' => get_the_date( 'c', $best_id ),
				),
			),
		),
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . '</script>' . "\n";
}

// ============================================================================
// 7. SCHEMA BREADCRUMBS
// ============================================================================
/**
 * @return void
 */
function swiftboard_schema_breadcrumbs() {
	$items    = array();
	$position = 1;

	$items[] = array(
		'@type'    => 'ListItem',
		'position' => $position++,
		'name'     => __( 'Accueil', 'swiftboard' ),
		'item'     => home_url( '/' ),
	);

	if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		// PATCH v2.1 : bbp_get_forum_archive_link() n'existe pas en 2.6.14.
		$archive_link = function_exists( 'bbp_get_forums_url' ) ? bbp_get_forums_url( '/' ) : home_url( '/forum/' );
		$items[]      = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => __( 'Forum', 'swiftboard' ),
			'item'     => $archive_link,
		);

		if ( $forum_id = bbp_get_forum_id() ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => bbp_get_forum_title( $forum_id ),
				'item'     => bbp_get_forum_permalink( $forum_id ),
			);
		}

		if ( bbp_is_single_topic() || bbp_is_single_reply() ) {
			$topic_id = bbp_get_topic_id();
			$items[]  = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => bbp_get_topic_title( $topic_id ),
				'item'     => bbp_get_topic_permalink( $topic_id ),
			);
		}
	} elseif ( is_singular( 'post' ) ) {
		$categories = get_the_category();
		if ( ! empty( $categories ) ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $categories[0]->name,
				'item'     => get_category_link( $categories[0]->term_id ),
			);
		}
	}

	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . '</script>' . "\n";
}
