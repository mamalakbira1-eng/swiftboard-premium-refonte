<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Moteur de promotion automatique des grades.
 *
 * POURQUOI CE MODULE EXISTE (EXI-ARCH-01)
 * ---------------------------------------
 * Ce code vivait dans inc/admin-settings-grades.php, un module classe
 * ADMIN-ONLY. Or il s'accroche a des hooks purement FRONT :
 *
 *     bbp_new_reply          une reponse publiee par un visiteur
 *     swiftboard_vote_cast   un vote emis depuis le forum
 *     wp                     planification du cron de rattrapage
 *
 * Un module admin-only n'est charge que si is_admin() vaut true : ces trois
 * hooks n'etaient donc jamais enregistres pour un visiteur. La promotion
 * automatique ne se declenchait que si un administrateur avait une page
 * d'admin ouverte au meme moment.
 *
 * Le critere du cahier est explicite : « aucune fonction appelee en front ne
 * doit resider dans un module admin-only ». Ce fichier est charge en front.
 *
 * Contenu : calcul du grade attendu, moteur de promotion, e-mail de
 * felicitations, declencheurs (reponse / vote) et cron de rattrapage.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 15. DÉTERMINER LE GRADE ATTENDU SELON LE SCORE
// ============================================================================
/**
 * Retourne le grade que l'utilisateur *devrait* avoir selon son score actuel.
 * Ne prend en compte que rookie / member / pro. Modérateur et VIP sont exclus
 * (jamais touchés automatiquement).
 *
 * @param int $score Score de réputation.
 * @return string
 */
function swiftboard_get_expected_grade_from_score( $score ) {
	$threshold_member = (int) get_option( 'swiftboard_autopromote_threshold_member', 5 );
	$threshold_pro    = (int) get_option( 'swiftboard_autopromote_threshold_pro', 500 );

	if ( $score >= $threshold_pro ) {
		return 'pro';
	}
	if ( $score >= $threshold_member ) {
		return 'member';
	}
	return 'rookie';
}

/**
 * Hiérarchie numérique pour comparer deux grades.
 * rookie = 1, member = 2, pro = 3, moderator = 4, vip = 5.
 */
// v4.6.1 : swiftboard_grade_level() déplacée vers inc/grades.php (chargé sur le front aussi)

// ============================================================================
// 16. PROMOTION AUTOMATIQUE D'UN UTILISATEUR
// ============================================================================
/**
 * Vérifie si l'utilisateur doit être promu et effectue la promotion si besoin.
 *
 * Règles :
 *  - Si l'auto-promotion est désactivée → ne rien faire.
 *  - Si l'utilisateur est modérateur ou VIP → ne jamais toucher.
 *  - Si le grade attendu est supérieur au grade actuel → promouvoir.
 *  - Ne jamais rétrograder : si le grade actuel > grade attendu, on garde le grade actuel.
 *
 * @param int $user_id ID de l'utilisateur à vérifier.
 * @return array<string, mixed>|false Retourne ['promoted' => true, 'from' => 'rookie', 'to' => 'member', 'score' => 7]
 *                     en cas de promotion, false sinon.
 */
function swiftboard_maybe_promote_user( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}

	// Désactivé ?
	if ( ! (int) get_option( 'swiftboard_autopromote_enabled', 1 ) ) {
		return false;
	}

	$current_grade = swiftboard_get_user_grade( $user_id );
	$current_level = swiftboard_grade_level( $current_grade );

	// Modérateur / VIP : intouchables
	if ( $current_level >= 4 ) {
		return false;
	}

	$reputation     = swiftboard_get_user_reputation_score( $user_id );
	$score          = $reputation['score'];
	$expected       = swiftboard_get_expected_grade_from_score( $score );
	$expected_level = swiftboard_grade_level( $expected );

	// Pas de promotion (ou rétrogradation interdite)
	if ( $expected_level <= $current_level ) {
		return false;
	}

	// === Promotion ===
	update_user_meta( $user_id, 'swiftboard_grade', $expected );
	swiftboard_invalidate_grade_cache( $user_id ); // EXI-TEST-02

	// Historique
	$history = get_user_meta( $user_id, 'swiftboard_promotion_history', true );
	if ( ! is_array( $history ) ) {
		$history = array();
	}
	$history[] = array(
		'from'      => $current_grade,
		'to'        => $expected,
		'score'     => $score,
		'timestamp' => current_time( 'mysql' ),
	);
	update_user_meta( $user_id, 'swiftboard_promotion_history', $history );

	// Notification email
	if ( (int) get_option( 'swiftboard_autopromote_notify_email', 1 ) ) {
		swiftboard_send_promotion_email( $user_id, $current_grade, $expected, $score );
	}

	// Hook extérieur (intégration futures : Discord, badge, etc.)
	/**
	 * Se declenche apres la promotion effective d'un membre.
	 *
	 * Point d'integration pour une annonce externe (Discord, badge, webhook).
	 * Emis APRES l'ecriture de la meta et de l'historique : l'etat en base est
	 * deja coherent quand le callback s'execute.
	 *
	 * @since 4.0.0
	 *
	 * @param int    $user_id       Membre promu.
	 * @param string $current_grade Grade quitte (rookie, member, pro).
	 * @param string $expected      Grade atteint.
	 * @param int    $score         Score de reputation ayant declenche la promotion.
	 */
	do_action( 'swiftboard_user_promoted', $user_id, $current_grade, $expected, $score );

	// Invalider le cache des permissions
	delete_transient( 'sb_perms_' . $user_id );

	return array(
		'promoted' => true,
		'from'     => $current_grade,
		'to'       => $expected,
		'score'    => $score,
	);
}
/**
 * Email de félicitations envoyé lors d'une promotion.
 *
 * @param int    $user_id    Identifiant de l'utilisateur.
 * @param string $from_grade Clé du grade.
 * @param string $to_grade   Clé du grade.
 * @param int    $score      Score de réputation.
 * @return void
 */
function swiftboard_send_promotion_email( $user_id, $from_grade, $to_grade, $score ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->user_email ) {
		return;
	}

	$grades    = swiftboard_get_grades();
	$from_info = $grades[ $from_grade ] ?? array(
		'icon' => '',
		'name' => ucfirst( $from_grade ),
	);
	$to_info   = $grades[ $to_grade ] ?? array(
		'icon' => '',
		'name' => ucfirst( $to_grade ),
	);

	$subject = sprintf(
		__( '[%1$s] 🎉 Vous êtes maintenant %2$s !', 'swiftboard' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		$to_info['name']
	);

	$message  = sprintf( __( "Bonjour %s,\n\n", 'swiftboard' ), $user->display_name ) . "\n";
	$message .= sprintf(
		__( "Félicitations ! Vous venez d'être promu au grade %1\$s sur %2\$s.\n", 'swiftboard' ),
		$to_info['icon'] . ' ' . $to_info['name'],
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
	);
	$message .= "\n";
	$message .= sprintf( __( "Grade précédent : %s\n", 'swiftboard' ), $from_info['icon'] . ' ' . $from_info['name'] );
	$message .= sprintf( __( "Nouveau grade : %s\n", 'swiftboard' ), $to_info['icon'] . ' ' . $to_info['name'] );
	$message .= sprintf( __( "Score de réputation : %d points\n", 'swiftboard' ), $score );
	$message .= "\n";
	$message .= __( "Ce grade vous débloque de nouvelles permissions (uploads, votes, création de sujets...).\n", 'swiftboard' );
	$message .= "\n";
	$message .= __( "Continuez à participer pour grimper encore plus haut !\n\n", 'swiftboard' );
	$message .= sprintf( __( "— L'équipe %s", 'swiftboard' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

	wp_mail( $user->user_email, $subject, $message );
}

// ============================================================================
// 17. HOOKS — DÉCLENCHEMENT DE LA VÉRIFICATION
// ============================================================================
/**
 * Quand une nouvelle réponse est publiée : vérifier l'auteur du topic parent.
 *
 * @param int                  $reply_id       Identifiant de la réponse.
 * @param int                  $topic_id       Identifiant du sujet.
 * @param int                  $forum_id       Identifiant du forum.
 * @param array<string, mixed> $anonymous_data Données à traiter.
 * @param mixed                $reply_author   À documenter.
 * @return void
 */
function swiftboard_on_new_reply_check_promotion( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
	if ( ! $topic_id || ! $reply_author ) {
		return;
	}
	$topic = get_post( $topic_id );
	if ( ! $topic ) {
		return;
	}
	$topic_author = (int) $topic->post_author;
	if ( $topic_author && $topic_author !== (int) $reply_author ) {
		// Celui qui a reçu la réponse (auteur du topic) gagne 1 point de réputation
		swiftboard_invalidate_reputation_cache( $topic_author );
		swiftboard_maybe_promote_user( $topic_author );
	}
}
add_action( 'bbp_new_reply', 'swiftboard_on_new_reply_check_promotion', 20, 5 );
/**
 * Quand un vote up est reçu : vérifier l'auteur du post voté.
 *
 * Ce hook doit être déclenché par le module de votes via :
 *     do_action('swiftboard_vote_cast', $post_id, 'up', $voter_user_id);
 * On écoute ce hook et on déclenche la vérification sur l'auteur du post.
 *
 * @param int    $post_id       Identifiant du contenu (sujet ou réponse).
 * @param string $vote_type     Type de vote : 'up' ou 'down'.
 * @param int    $voter_user_id Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_on_vote_cast_check_promotion( $post_id, $vote_type, $voter_user_id ) {
	if ( $vote_type !== 'up' || ! $post_id ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	$author_id = (int) $post->post_author;
	if ( $author_id && $author_id !== (int) $voter_user_id ) {
		swiftboard_invalidate_reputation_cache( $author_id );
		swiftboard_maybe_promote_user( $author_id );
	}
}
add_action( 'swiftboard_vote_cast', 'swiftboard_on_vote_cast_check_promotion', 10, 3 );

/**
 * Cron quotidien de rattrapage — au cas où un vote ou une réponse aurait été
 * manqué (cache expiré, hook non déclenché, etc.). On parcourt les
 * utilisateurs actifs des 30 derniers jours.
 *
 * @return void
 */
function swiftboard_register_autopromote_cron() {
	if ( ! wp_next_scheduled( 'swiftboard_autopromote_daily' ) ) {
		wp_schedule_event( time(), 'daily', 'swiftboard_autopromote_daily' );
	}
}
add_action( 'wp', 'swiftboard_register_autopromote_cron' );

/**
 * @return void
 */
function swiftboard_autopromote_daily_callback() {
	global $wpdb;

	// Utilisateurs actifs (au moins 1 topic OU 1 reply OU un grade défini)
	$user_ids = $wpdb->get_col(
		"SELECT DISTINCT post_author FROM {$wpdb->posts}
         WHERE post_type IN ('topic','reply')
           AND post_author > 0
         LIMIT 5000"
	);

	if ( empty( $user_ids ) ) {
		return;
	}

	foreach ( $user_ids as $uid ) {
		// NE PAS invalider ici : le transient (15 min) + les hooks vote/reply
		// gardent la fraîcheur. Invalider avant lecture = N+1 SQL garanti.
		swiftboard_maybe_promote_user( (int) $uid );
	}
}
add_action( 'swiftboard_autopromote_daily', 'swiftboard_autopromote_daily_callback' );
