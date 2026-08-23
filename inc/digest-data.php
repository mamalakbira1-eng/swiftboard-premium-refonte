<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Construction des donnees du digest hebdomadaire.
 *
 * EXI-ARCH-01 : extrait de inc/email-digest.php. Selection des destinataires
 * par lots et agregation du contenu personnalise de chaque envoi.
 *
 * swiftboard_digest_get_next_batch() ne retient que les membres ACTIFS
 * (jointure sur un contenu publie) puis filtre l'opt-in en PHP : un lot vide
 * ne signifie donc pas la fin de la pagination.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 5. CONSTRUCTION DU CONTENU DU DIGEST
// ============================================================================
/**
 * Construit les données du digest pour un utilisateur.
 *
 * @return array {
 *   @type array  $top_responders Top 3 répondeurs de la semaine
 *   @type array  $hot_topics     Top 3 sujets par score
 *   @type array  $my_stats       Stats perso (upvotes, replies, score, grade)
 *   @type string $promotion      Promotion éventuelle cette semaine (ou null)
 * }
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return array<string, mixed> Voir la forme detaillee ci-dessus.
 */
function swiftboard_digest_build_data( $user_id ) {
	// Top 3 répondeurs (depuis le module top-weekly-responder.php)
	$top_data       = function_exists( 'swiftboard_get_weekly_top' ) ? swiftboard_get_weekly_top() : array( 'top' => array() );
	$top_responders = array();
	foreach ( $top_data['top'] as $entry ) {
		$user = get_userdata( $entry['user_id'] );
		if ( $user ) {
			$top_responders[] = array(
				'name'  => $user->display_name,
				'count' => $entry['count'],
				'rank'  => $entry['rank'],
			);
		}
	}

	// Top 3 sujets chauds (par score de votes sur les 7 derniers jours)
	$hot_topics = swiftboard_digest_get_hot_topics( 3 );

	// Stats perso
	$my_stats = array(
		'upvotes' => 0,
		'replies' => 0,
		'score'   => 0,
		'grade'   => swiftboard_get_user_grade( $user_id ),
	);
	if ( function_exists( 'swiftboard_get_user_reputation_score' ) ) {
		$rep                 = swiftboard_get_user_reputation_score( $user_id );
		$my_stats['upvotes'] = $rep['upvotes'];
		$my_stats['replies'] = $rep['replies'];
		$my_stats['score']   = $rep['score'];
	}

	// Promotion éventuelle cette semaine
	$promotion = null;
	$history   = get_user_meta( $user_id, 'swiftboard_promotion_history', true );
	if ( is_array( $history ) ) {
		$week_ago = strtotime( '-7 days' );
		foreach ( $history as $h ) {
			if ( strtotime( $h['timestamp'] ) >= $week_ago ) {
				$promotion = $h;
				break;
			}
		}
	}

	return array(
		'top_responders' => $top_responders,
		'hot_topics'     => $hot_topics,
		'my_stats'       => $my_stats,
		'promotion'      => $promotion,
	);
}

/**
 * Top N sujets chauds sur les 7 derniers jours (par score de votes).
 *
 * @param int $limit Nombre maximal d'éléments. Optionnel.
 * @return mixed
 */
function swiftboard_digest_get_hot_topics( $limit = 3 ) {
	global $wpdb;
	$votes_table = swiftboard_table( 'votes' );
	$limit       = max( 1, min( 10, (int) $limit ) );

	// Vérifier que la table existe
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $votes_table ) ) !== $votes_table ) {
		return array();
	}

	$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT v.post_id,
                SUM(CASE WHEN v.vote_type = 'up' THEN 1 ELSE -1 END) as score,
                COUNT(*) as total_votes
         FROM {$votes_table} v
         INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id
         WHERE p.post_type = 'topic'
           AND p.post_status = 'publish'
           AND v.created_at >= %s
         GROUP BY v.post_id
         ORDER BY score DESC
         LIMIT %d",
			$since,
			$limit
		),
		ARRAY_A
	);

	$topics = array();
	foreach ( $rows as $row ) {
		$post = get_post( $row['post_id'] );
		if ( ! $post ) {
			continue;
		}
		$topics[] = array(
			'id'     => (int) $row['post_id'],
			'title'  => wp_trim_words( wp_strip_all_tags( $post->post_title ), 12, '…' ),
			'url'    => get_permalink( $row['post_id'] ),
			'score'  => (int) $row['score'],
			'votes'  => (int) $row['total_votes'],
			'author' => get_the_author_meta( 'display_name', (int) $post->post_author ),
		);
	}
	return $topics;
}

// ============================================================================
// 4. RÉCUPÉRER LES UTILISATEURS À NOTIFIER (par batch)
// ============================================================================
/**
 * Récupère le prochain batch d'utilisateurs à qui envoyer le digest.
 *
 * @param int $offset Offset de pagination
 * @param int $limit  Taille du batch
 * @return array<string, mixed> IDs d'utilisateurs
 */
function swiftboard_digest_get_next_batch( $offset, $limit ) {
	global $wpdb;

	// Utilisateurs actifs (au moins 1 topic OU reply) ET qui n'ont pas déjà reçu
	// le digest de cette semaine (meta _digest_sent_YWW).
	$week_key      = 'sb_digest_sent_' . gmdate( 'YW' );
	$existing_meta = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta}
         WHERE meta_key = %s",
			$week_key
		)
	);
	$exclude       = ! empty( $existing_meta ) ? implode( ',', array_map( 'intval', $existing_meta ) ) : '0';

	// L'opt-in est filtre DANS le SQL, donc AVANT le LIMIT.
	//
	// Il etait auparavant applique en PHP sur le resultat deja tronque : si les
	// N premiers membres actifs etaient tous desinscrits, le lot revenait vide
	// et swiftboard_digest_send_batch() en concluait « tous les membres ont ete
	// traites », puis s'arretait sans reprogrammer la suite. Avec un opt-in
	// explicite, le digest pouvait ne partir pour PERSONNE.
	//
	// La jointure LEFT sur usermeta reste peu couteuse : elle porte sur une cle
	// indexee (user_id, meta_key) et le lot est plafonne par $limit.
	$meta_optin = 'swiftboard_email_digest_enabled';

	$sql = $wpdb->prepare(
		"SELECT DISTINCT u.ID
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->posts} p ON p.post_author = u.ID
            AND p.post_type IN ('topic','reply')
            AND p.post_status = 'publish'
         LEFT JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = %s
         WHERE u.ID NOT IN ({$exclude})
           AND m.meta_value = '1'
         ORDER BY u.ID
         LIMIT %d OFFSET %d",
		$meta_optin,
		$limit,
		$offset
	);

	return array_map( 'intval', $wpdb->get_col( $sql ) );
}
