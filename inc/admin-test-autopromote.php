<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
/**
 * SwiftBoard - Page de test : Simulation complète d'auto-promotion
 *
 * Cette page permet de valider le système de montée de grade automatique
 * en conditions réelles sans avoir à créer manuellement des utilisateurs
 * et des votes.
 *
 * Scénario de test :
 *  1. Crée 3 utilisateurs fictifs : 1 auteur (Rookie) + 2 votants
 *  2. Crée 1 topic par l'auteur
 *  3. Crée 1 réponse d'un votant sur le topic (1 pt réputation)
 *  4. Déclenche 4 upvotes via swiftboard_cast_vote() (4 pts réputation)
 *     → Score total = 4 upvotes + 1 réponse = 5 pts
 *     → Seuil Rookie → Membre (5) atteint → promotion automatique
 *  5. Vérifie que l'auteur est bien passé de Rookie à Membre
 *  6. Affiche un rapport détaillé (score, historique, email envoyé)
 *
 * Sécurité :
 *  - Capacité manage_options requise
 *  - Nonce sur chaque action
 *  - Préfixe sb_test_ sur tous les identifiants pour faciliter le cleanup
 *  - Bouton "Nettoyer les données de test" séparé
 *
 * @package SwiftBoard
 * @since 2.5.0
 */
// ============================================================================
// 1. MENU ADMIN
// ============================================================================
/**
 * @return void
 */
function swiftboard_test_autopromote_menu() {
	add_submenu_page(
		'swiftboard-dashboard',
		__( 'Test auto-promotion', 'swiftboard' ),
		__( '🧪 Test auto-promo', 'swiftboard' ),
		'manage_options',
		'swiftboard-test-autopromote',
		'swiftboard_test_autopromote_page'
	);
}
add_action( 'admin_menu', 'swiftboard_test_autopromote_menu' );

// ============================================================================
// 2. HELPERS DE TEST
// ============================================================================
/**
 * swiftboard_test_create_user().
 *
 * @param string $login        Identifiant de connexion.
 * @param string $email        Adresse e-mail.
 * @param string $display_name Nom.
 * @return mixed
 */
function swiftboard_test_create_user( $login, $email, $display_name ) {
	// Si l'utilisateur existe déjà, on le réutilise
	$existing = get_user_by( 'login', $login );
	if ( $existing ) {
		return $existing->ID;
	}
	$user_id = wp_create_user( $login, wp_generate_password( 24, true ), $email );
	if ( is_wp_error( $user_id ) ) {
		return 0;
	}
	wp_update_user(
		array(
			'ID'           => $user_id,
			'display_name' => $display_name,
			'role'         => 'subscriber',
		)
	);
	// Forcer le grade Rookie
	update_user_meta( $user_id, 'swiftboard_grade', 'rookie' );
	swiftboard_invalidate_grade_cache( $user_id ); // EXI-TEST-02
	// Marquer comme utilisateur de test
	update_user_meta( $user_id, '_swiftboard_test_user', 1 );
	return $user_id;
}

/**
 * swiftboard_test_create_topic().
 *
 * @param int    $author_id Identifiant de l'auteur.
 * @param string $title     Titre.
 * @param string $content   Contenu à traiter.
 * @return mixed
 */
function swiftboard_test_create_topic( $author_id, $title, $content ) {
	// Trouver un forum bbPress existant, sinon en créer un de test
	$forum_id = swiftboard_test_get_or_create_forum();
	if ( ! $forum_id ) {
		return 0;
	}

	$topic_id = bbp_insert_topic(
		array(
			'post_parent'  => $forum_id,
			'post_author'  => $author_id,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'topic',
		)
	);
	return $topic_id;
}

/**
 * @return mixed
 */
function swiftboard_test_get_or_create_forum() {
	// Cherche un forum existant
	$forums = get_posts(
		array(
			'post_type'      => 'forum',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	if ( ! empty( $forums ) ) {
		return $forums[0];
	}
	// Crée un forum de test
	if ( ! function_exists( 'bbp_insert_forum' ) ) {
		return 0;
	}
	$forum_id = bbp_insert_forum(
		array(
			'post_title'   => 'Forum de test SwiftBoard',
			'post_content' => 'Forum temporaire pour les tests d\'auto-promotion.',
			'post_status'  => 'publish',
			'post_type'    => 'forum',
		)
	);
	if ( $forum_id ) {
		update_post_meta( $forum_id, '_swiftboard_test_data', 1 );
	}
	return $forum_id;
}

/**
 * swiftboard_test_create_reply().
 *
 * @param int    $author_id Identifiant de l'auteur.
 * @param int    $topic_id  Identifiant du sujet.
 * @param string $content   Contenu à traiter.
 * @return mixed
 */
function swiftboard_test_create_reply( $author_id, $topic_id, $content ) {
	if ( ! function_exists( 'bbp_insert_reply' ) ) {
		return 0;
	}
	$forum_id = wp_get_post_parent_id( $topic_id );
	$reply_id = bbp_insert_reply(
		array(
			'post_parent'  => $topic_id,
			'post_author'  => $author_id,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'reply',
		),
		array(
			'forum_id' => $forum_id,
			'topic_id' => $topic_id,
		)
	);

	// bbp_insert_reply ne déclenche PAS l'action bbp_new_reply (contrairement au
	// formulaire frontal). On la déclenche manuellement pour activer les hooks
	// de notification + auto-promotion.
	if ( $reply_id ) {
		do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, false, $author_id );
	}

	return $reply_id;
}

/**
 * @return array<string, mixed>
 */
function swiftboard_test_cleanup() {
	global $wpdb;

	// 1. Supprimer les utilisateurs de test
	$test_users = get_users(
		array(
			'meta_key'   => '_swiftboard_test_user',
			'meta_value' => '1',
			'number'     => 100,
			'fields'     => 'ID',
		)
	);
	foreach ( $test_users as $uid ) {
		// Anonymiser les votes plutôt que supprimer (conserve le score des posts)
		$wpdb->update(
			swiftboard_table( 'votes' ),
			array( 'user_id' => 0 ),
			array( 'user_id' => (int) $uid ),
			array( '%d' ),
			array( '%d' )
		);
		// Supprimer les notifications de test
		$wpdb->delete(
			swiftboard_table( 'notifications' ),
			array( 'user_id' => (int) $uid ),
			array( '%d' )
		);
		// Supprimer l'utilisateur WP
		wp_delete_user( $uid, null );
	}

	// 2. Supprimer les topics de test
	$test_topics = get_posts(
		array(
			'post_type'      => array( 'topic', 'reply', 'forum' ),
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'meta_key'       => '_swiftboard_test_data',
			'fields'         => 'ids',
		)
	);
	foreach ( $test_topics as $pid ) {
		wp_delete_post( $pid, true );
	}

	// 3. Supprimer les votes liés aux posts supprimés
	if ( ! empty( $test_topics ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $test_topics ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}swiftboard_votes WHERE post_id IN ({$placeholders})",
				$test_topics
			)
		);
	}

	return array(
		'users_removed' => count( $test_users ),
		'posts_removed' => count( $test_topics ),
	);
}
