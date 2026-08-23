<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Invalidation du cache de schema.org.
 *
 * EXI-ARCH-01 : extrait de inc/schema.php, qui depassait 500 lignes apres
 * l'ajout de la lecture du transient.
 *
 * Le schema d'un sujet depend de son titre, de ses reponses et de ses votes :
 * un cache non invalide exposerait aux moteurs des donnees perimees pendant
 * toute la duree de vie du transient (10 minutes).
 *
 * @package SwiftBoard
 * @since 5.1.3
 */
// ============================================================================
// 8. SIGNAL LLM
// ============================================================================
// REMOVED v4.0: swiftboard_llm_meta was empty dead code
// ============================================================================
// 9. INVALIDATION DU CACHE DE SCHEMA
// ============================================================================
/**
 * Purge le schema mis en cache quand le sujet change.
 *
 * Sans cela, un sujet edite — ou qui recoit une reponse, un vote — continue
 * d'exposer aux moteurs un schema perime pendant toute la duree du transient.
 * Le `commentCount` et le tableau `comment[]` en dependent directement.
 *
 * @param int $topic_id Identifiant du sujet a purger.
 * @return void
 */
function swiftboard_purger_cache_schema( $topic_id ) {
	$topic_id = (int) $topic_id;
	if ( $topic_id ) {
		delete_transient( 'swiftboard_schema_topic_' . $topic_id );
	}
}

/**
 * Resout le sujet concerne puis purge son cache.
 *
 * Une reponse modifie le schema de son sujet PARENT : c'est lui qu'il faut
 * purger, pas la reponse.
 *
 * @param int $post_id Sujet ou reponse.
 * @return void
 */
function swiftboard_purger_cache_schema_depuis_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return;
	}

	$type = get_post_type( $post_id );

	if ( function_exists( 'bbp_get_reply_post_type' ) && $type === bbp_get_reply_post_type() ) {
		swiftboard_purger_cache_schema( (int) get_post_meta( $post_id, '_bbp_topic_id', true ) );
		return;
	}

	swiftboard_purger_cache_schema( $post_id );
}

// ============================================================================
// ACCROCHES
// ============================================================================
foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $sb_evt_schema ) {
	add_action( $sb_evt_schema, 'swiftboard_purger_cache_schema_depuis_post', 10, 1 );
}

// bbp_new_reply passe ($reply_id, $topic_id, ...) : c'est le SECOND argument
// qui identifie le sujet dont le schema change.
add_action(
	'bbp_new_reply',
	function ( $reply_id, $topic_id ) {
		swiftboard_purger_cache_schema( $topic_id );
	},
	10,
	2
);

add_action( 'bbp_edit_topic', 'swiftboard_purger_cache_schema', 10, 1 );

// Un vote change upvoteCount/downvoteCount dans le schema enrichi.
add_action( 'swiftboard_vote_cast', 'swiftboard_purger_cache_schema_depuis_post', 10, 1 );
