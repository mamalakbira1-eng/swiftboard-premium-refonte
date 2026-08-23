<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Endpoints REST lisibles par les moteurs et les LLM.
 *
 * EXI-ARCH-01 : extrait de inc/llm-readability.php. Module FRONT : ces routes
 * sont appelees par des crawlers anonymes.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
/**
 * swiftboard_rest_llm_topic().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_rest_llm_topic( WP_REST_Request $req ) {
	$topic_id = (int) $req->get_param( 'id' );
	if ( ! $topic_id ) {
		// Fallback pour les appels directs (tests CLI)
		$url_params = $req->get_url_params();
		$topic_id   = (int) ( $url_params['id'] ?? 0 );
	}
	if ( ! $topic_id ) {
		// Dernier fallback : get_query_var
		$topic_id = (int) get_query_var( 'id' );
	}
	$topic = get_post( $topic_id );
	if ( ! $topic || $topic->post_type !== 'topic' ) {
		return new WP_Error( 'not_found', 'Topic introuvable (id=' . $topic_id . ')', array( 'status' => 404 ) );
	}

	$author_id   = (int) $topic->post_author;
	$grade       = swiftboard_get_user_grade( $author_id );
	$grades      = swiftboard_get_grades();
	$grade_info  = $grades[ $grade ] ?? null;
	$vote_score  = swiftboard_get_vote_count( $topic_id );
	$reply_count = function_exists( 'bbp_get_topic_reply_count' ) ? (int) bbp_get_topic_reply_count( $topic_id, true ) : 0;
	$forum_id    = wp_get_post_parent_id( $topic_id );
	$up          = (int) get_post_meta( $topic_id, '_swiftboard_vote_up', true );
	$down        = (int) get_post_meta( $topic_id, '_swiftboard_vote_down', true );

	// Récupérer toutes les réplies
	$replies = get_posts(
		array(
			'post_type'      => 'reply',
			'post_status'    => 'publish',
			'post_parent'    => $topic_id,
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	);

	$reply_list = array();
	foreach ( $replies as $r ) {
		$r_author_id  = (int) $r->post_author;
		$r_grade      = swiftboard_get_user_grade( $r_author_id );
		$r_grade_info = $grades[ $r_grade ] ?? null;
		$r_up         = (int) get_post_meta( $r->ID, '_swiftboard_vote_up', true );
		$reply_to     = (int) get_post_meta( $r->ID, '_bbp_reply_to', true );

		$reply_list[] = array(
			'id'         => (int) $r->ID,
			'author'     => array(
				'name'        => get_the_author_meta( 'display_name', $r_author_id ),
				'grade'       => $r_grade,
				'grade_label' => $r_grade_info ? $r_grade_info['icon'] . ' ' . $r_grade_info['name'] : '',
			),
			'content'    => wp_strip_all_tags( $r->post_content ),
			'date'       => mysql2date( 'c', $r->post_date_gmt ),
			// up ET down : un score net seul ne distingue pas « aucun vote »
			// d'un « +50 / -50 » tout aussi net a zero.
			'upvotes'    => $r_up,
			'downvotes'  => function_exists( 'swiftboard_get_vote_breakdown' )
				? (int) swiftboard_get_vote_breakdown( $r->ID )['down']
				: (int) get_post_meta( $r->ID, '_swiftboard_vote_down', true ),
			'vote_score' => function_exists( 'swiftboard_get_vote_breakdown' )
				? (int) swiftboard_get_vote_breakdown( $r->ID )['score']
				: 0,
			'reply_to'   => $reply_to ?: null,
			'url'        => get_permalink( $topic_id ) . '#reply-' . $r->ID,
		);
	}

	return new WP_REST_Response(
		array(
			'@type'          => 'DiscussionForumPosting',
			'@context'       => 'https://schema.org',
			'id'             => (int) $topic_id,
			'title'          => $topic->post_title,
			'content'        => wp_strip_all_tags( $topic->post_content ),
			'url'            => get_permalink( $topic_id ),
			'author'         => array(
				'name'        => get_the_author_meta( 'display_name', $author_id ),
				'grade'       => $grade,
				'grade_label' => $grade_info ? $grade_info['icon'] . ' ' . $grade_info['name'] : '',
				'profile_url' => function_exists( 'bbp_get_user_profile_url' ) ? bbp_get_user_profile_url( $author_id ) : '',
			),
			'forum'          => array(
				'id'   => (int) $forum_id,
				'name' => $forum_id ? get_the_title( $forum_id ) : '',
				'url'  => $forum_id ? get_permalink( $forum_id ) : '',
			),
			'date_published' => mysql2date( 'c', $topic->post_date_gmt ),
			'date_modified'  => mysql2date( 'c', $topic->post_modified_gmt ),
			'upvotes'        => $up,
			'downvotes'      => $down,
			'vote_score'     => $vote_score,
			'reply_count'    => $reply_count,
			'replies'        => $reply_list,
			'image'          => get_post_meta( $topic_id, '_swiftboard_image_url', true ) ?: null,
			'tags'           => wp_get_post_terms( $topic_id, 'topic-tag', array( 'fields' => 'names' ) ),
			'in_language'    => 'fr-FR',
		),
		200
	);
}

/**
 * swiftboard_rest_llm_forum().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_rest_llm_forum( WP_REST_Request $req ) {
	$forum_id = (int) $req->get_param( 'id' );
	if ( ! $forum_id ) {
		$url_params = $req->get_url_params();
		$forum_id   = (int) ( $url_params['id'] ?? 0 );
	}
	$forum = get_post( $forum_id );
	if ( ! $forum || $forum->post_type !== 'forum' ) {
		return new WP_Error( 'not_found', 'Forum introuvable', array( 'status' => 404 ) );
	}

	// Voir plus haut : sans meta_query, l'INNER JOIN masque tous les sujets
	// jamais votes. Un crawler LLM ne verrait qu'une fraction du forum.
	$args_topics = array(
		'post_type'      => 'topic',
		'post_status'    => 'publish',
		'post_parent'    => $forum_id,
		'posts_per_page' => 100,
		'orderby'        => 'meta_value_num',
		'meta_key'       => '_swiftboard_vote_score',
		'order'          => 'DESC',
	);
	if ( function_exists( 'swiftboard_trier_par_meta_numerique' ) ) {
		swiftboard_trier_par_meta_numerique( $args_topics, '_swiftboard_vote_score' );
	}
	$topics = get_posts( $args_topics );

	$topic_list = array();
	foreach ( $topics as $t ) {
		$vote_score   = swiftboard_get_vote_count( $t->ID );
		$reply_count  = function_exists( 'bbp_get_topic_reply_count' ) ? (int) bbp_get_topic_reply_count( $t->ID, true ) : 0;
		$author_grade = swiftboard_get_user_grade( (int) $t->post_author );

		$topic_list[] = array(
			'id'           => (int) $t->ID,
			'title'        => $t->post_title,
			'url'          => get_permalink( $t->ID ),
			'author'       => get_the_author_meta( 'display_name', (int) $t->post_author ),
			'author_grade' => $author_grade,
			'date'         => mysql2date( 'c', $t->post_date_gmt ),
			'upvotes'      => $vote_score,
			'replies'      => $reply_count,
			'excerpt'      => wp_trim_words( wp_strip_all_tags( $t->post_content ), 30 ),
		);
	}

	return new WP_REST_Response(
		array(
			'@type'       => 'CollectionPage',
			'@context'    => 'https://schema.org',
			'id'          => (int) $forum_id,
			'name'        => $forum->post_title,
			'description' => wp_strip_all_tags( $forum->post_content ),
			'url'         => get_permalink( $forum_id ),
			'topic_count' => count( $topic_list ),
			'topics'      => $topic_list,
		),
		200
	);
}

/**
 * @return \WP_REST_Response
 */
function swiftboard_rest_llm_sitemap() {
	$forums = get_posts(
		array(
			'post_type'      => 'forum',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		)
	);

	$sitemap = array(
		'site'         => home_url( '/' ),
		'name'         => get_bloginfo( 'name' ),
		'generated_at' => current_time( 'c' ),
		'forums'       => array(),
		'endpoints'    => array(
			'llm_index'       => home_url( '/llm-index.json' ),
			'rest_topic'      => rest_url( 'swiftboard/v1/llm/topic/{id}' ),
			'rest_forum'      => rest_url( 'swiftboard/v1/llm/forum/{id}' ),
			'rest_sitemap'    => rest_url( 'swiftboard/v1/llm/sitemap' ),
			'rest_hot_topics' => rest_url( 'swiftboard/v1/hot-topics' ),
			'rest_feed'       => rest_url( 'swiftboard/v1/feed' ),
		),
	);

	foreach ( $forums as $f ) {
		$topics = get_posts(
			array(
				'post_type'      => 'topic',
				'post_status'    => 'publish',
				'post_parent'    => $f->ID,
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);

		$sitemap['forums'][] = array(
			'id'          => (int) $f->ID,
			'name'        => $f->post_title,
			'url'         => get_permalink( $f->ID ),
			'llm_url'     => rest_url( 'swiftboard/v1/llm/forum/' . $f->ID ),
			'topic_count' => count( $topics ),
			'topic_ids'   => array_map( 'intval', $topics ),
		);
	}

	return new WP_REST_Response( $sitemap, 200 );
}
