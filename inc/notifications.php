<?php
if ( ! defined( 'ABSPATH' )) exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Notifications in-app
 *
 * Système léger de notifications pour les events sociaux :
 *  - Upvote reçu sur un de mes posts
 *  - Réponse reçue sur un de mes sujets
 *  - Promotion de grade
 *
 * Architecture Hostinger-safe :
 *  - Table dédiée {prefix}swiftboard_notifications (index sur user_id+is_read)
 *  - Compteur "non-lues" mis en cache transient (5 min) → 0 requête DB sur page load
 *  - Hook sur swiftboard_vote_cast + bbp_new_reply + swiftboard_user_promoted
 *  - Auto-nettoyage hebdo via WP-Cron (notifs > 30 jours supprimées)
 *  - Liste limitée à 20 par page (pas de pagination lourde)
 *  - REST API pour marquer comme lu en AJAX
 *
 * @package SwiftBoard
 * @since 2.6.0
 */
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL queries use internal $wpdb->prefix variables (safe)

// ============================================================================
// 1. CRÉATION DE LA TABLE
// ============================================================================
/**
 * @return void
 */
function swiftboard_create_notifications_table() {
	global $wpdb;
	$table           = swiftboard_table( 'notifications' );
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id      BIGINT(20) UNSIGNED NOT NULL,
        actor_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        type         VARCHAR(30) NOT NULL,
        post_id      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        post_type    VARCHAR(20) NOT NULL DEFAULT '',
        title        VARCHAR(255) NOT NULL DEFAULT '',
        excerpt      VARCHAR(255) NOT NULL DEFAULT '',
        is_read      TINYINT(1) NOT NULL DEFAULT 0,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_user_read (user_id, is_read),
        KEY idx_user_created (user_id, created_at),
        KEY idx_created (created_at)
    ) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
add_action(
	'admin_init',
	function () {
		global $wpdb;
		$table = swiftboard_table( 'notifications' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			swiftboard_create_notifications_table();
		}
	}
);
add_action( 'after_switch_theme', 'swiftboard_create_notifications_table' );

// ============================================================================
// 2. CRÉER UNE NOTIFICATION
// ============================================================================
/**
 * Insère une notification.
 *
 * @param array<string, mixed> $args {
 *   @type int    $user_id   Destinataire (obligatoire)
 *   @type int    $actor_id  Émetteur (0 = système)
 *   @type string $type      'upvote' | 'reply' | 'promotion' | 'mention'
 *   @type int    $post_id   Post concerné
 *   @type string $post_type 'topic' | 'reply'
 *   @type string $title     Titre court
 *   @type string $excerpt   Extrait optionnel
 * }
 * @return int|false ID de la notification ou false
 */
function swiftboard_add_notification( $args ) {
	global $wpdb;
	$table = swiftboard_table( 'notifications' );

	$user_id = (int) ( $args['user_id'] ?? 0 );
	if ( ! $user_id) return false;

	// Ne pas se notifier soi-même
	$actor_id = (int) ( $args['actor_id'] ?? 0 );
	if ($actor_id && $actor_id === $user_id) return false;

	$wpdb->insert(
		$table,
		array(
			'user_id'    => $user_id,
			'actor_id'   => $actor_id,
			'type'       => sanitize_text_field( $args['type'] ?? 'generic' ),
			'post_id'    => (int) ( $args['post_id'] ?? 0 ),
			'post_type'  => sanitize_text_field( $args['post_type'] ?? '' ),
			'title'      => sanitize_text_field( $args['title'] ?? '' ),
			'excerpt'    => sanitize_text_field( $args['excerpt'] ?? '' ),
			'is_read'    => 0,
			'created_at' => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
	);

	$insert_id = $wpdb->insert_id;
	if ( $insert_id ) {
		// Invalider le cache du compteur non-lues
		delete_transient( 'sb_notif_unread_' . $user_id );
	}
	return $insert_id;
}

// ============================================================================
// 3. HOOKS — GÉNÉRATION AUTO DES NOTIFICATIONS
// ============================================================================

// 3.1 — Upvote reçu
add_action(
	'swiftboard_vote_cast',
	function ( $post_id, $vote_type, $voter_user_id ) {
		if ($vote_type !== 'up') return;
		$post = get_post( $post_id );
		if ( ! $post) return;
		$author_id = (int) $post->post_author;
		if ( ! $author_id || $author_id === (int) $voter_user_id) return;

		$title = wp_trim_words( wp_strip_all_tags( $post->post_title ?: $post->post_content ), 8, '…' );

		swiftboard_add_notification(
			array(
				'user_id'   => $author_id,
				'actor_id'  => (int) $voter_user_id,
				'type'      => 'upvote',
				'post_id'   => $post_id,
				'post_type' => $post->post_type,
				'title'     => $title,
				'excerpt'   => sprintf( '▲ Un upvote sur votre %s', ( $post->post_type === 'topic' ) ? 'sujet' : 'réponse' ),
			)
		);
	},
	20,
	3
);

// 3.2 — Réponse reçue sur un de mes sujets
add_action(
	'bbp_new_reply',
	function ( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
		if ( ! $topic_id || ! $reply_author) return;
		$topic = get_post( $topic_id );
		if ( ! $topic) return;
		$topic_author = (int) $topic->post_author;
		if ( ! $topic_author || $topic_author === (int) $reply_author) return;

		$title = wp_trim_words( wp_strip_all_tags( $topic->post_title ), 8, '…' );

		swiftboard_add_notification(
			array(
				'user_id'   => $topic_author,
				'actor_id'  => (int) $reply_author,
				'type'      => 'reply',
				'post_id'   => $topic_id,
				'post_type' => 'topic',
				'title'     => $title,
				'excerpt'   => '💬 Nouvelle réponse sur votre sujet',
			)
		);

		// 3.2b — Notifier aussi les FOLLOWERS du sujet (batch INSERT, pas N+1)
		if ( function_exists( 'swiftboard_get_topic_followers' ) ) {
			$followers = swiftboard_get_topic_followers( $topic_id );
			if ( ! empty( $followers ) ) {
				$batch = array();
				foreach ( $followers as $follower_id ) {
					$follower_id = (int) $follower_id;
					if ( $follower_id === $topic_author || $follower_id === (int) $reply_author ) {
						continue;
					}
					$batch[] = array(
						'user_id'   => $follower_id,
						'actor_id'  => (int) $reply_author,
						'type'      => 'reply',
						'post_id'   => $topic_id,
						'post_type' => 'topic',
						'title'     => $title,
						'excerpt'   => '💬 Nouvelle réponse sur un sujet que vous suivez',
					);
				}
				if ( ! empty( $batch ) && function_exists( 'swiftboard_add_notifications_batch' ) ) {
					swiftboard_add_notifications_batch( $batch );
				}
			}
		}
	},
	20,
	5
);

// 3.3 — Promotion de grade
add_action(
	'swiftboard_user_promoted',
	function ( $user_id, $from, $to, $score ) {
		$grades  = swiftboard_get_grades();
		$to_info = $grades[ $to ] ?? array(
			'icon' => '',
			'name' => ucfirst( $to ),
		);

		swiftboard_add_notification(
			array(
				'user_id'   => (int) $user_id,
				'actor_id'  => 0, // système
				'type'      => 'promotion',
				'post_id'   => 0,
				'post_type' => '',
				'title'     => sprintf( 'Promotion : %s %s', $to_info['icon'], $to_info['name'] ),
				'excerpt'   => sprintf( '🎉 Vous êtes maintenant %s !', $to_info['name'] ),
			)
		);
	},
	10,
	4
);

// ============================================================================
// 4. LECTURE — COMPTEUR NON-LUES (caché)
// ============================================================================
/**
 * swiftboard_get_unread_count().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return mixed
 */
function swiftboard_get_unread_count( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id) return 0;

	$cache_key = 'sb_notif_unread_' . $user_id;
	$cached    = get_transient( $cache_key );
	if ( $cached !== false && is_numeric( $cached ) ) {
		return (int) $cached;
	}

	global $wpdb;
	$table = swiftboard_table( 'notifications' );
	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
			$user_id
		)
	);

	set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );
	return $count;
}

/**
 * swiftboard_get_notifications().
 *
 * @param int  $user_id     Identifiant de l'utilisateur.
 * @param int  $limit       Nombre maximal d'éléments. Optionnel.
 * @param int  $offset      Décalage de pagination. Optionnel.
 * @param bool $only_unread À documenter. Optionnel.
 * @return mixed
 */
function swiftboard_get_notifications( $user_id, $limit = 20, $offset = 0, $only_unread = false ) {
	global $wpdb;
	$table   = swiftboard_table( 'notifications' );
	$user_id = (int) $user_id;
	$limit   = max( 1, min( 50, (int) $limit ) );
	$offset  = max( 0, (int) $offset );

	$where  = 'user_id = %d';
	$params = array( $user_id );
	if ( $only_unread ) {
		$where .= ' AND is_read = 0';
	}

	$sql = $wpdb->prepare(
		"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
		array_merge( $params, array( $limit, $offset ) )
	);
	return $wpdb->get_results( $sql, ARRAY_A );
}

// ============================================================================
// 5. MARQUER COMME LU
// ============================================================================
/**
 * swiftboard_mark_notification_read().
 *
 * @param int $notif_id Identifiant.
 * @param int $user_id  Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_mark_notification_read( $notif_id, $user_id ) {
	global $wpdb;
	$table = swiftboard_table( 'notifications' );
	$wpdb->update(
		$table,
		array( 'is_read' => 1 ),
		array(
			'id'      => (int) $notif_id,
			'user_id' => (int) $user_id,
		),
		array( '%d' ),
		array( '%d', '%d' )
	);
	delete_transient( 'sb_notif_unread_' . $user_id );
}

/**
 * swiftboard_mark_all_read().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_mark_all_read( $user_id ) {
	global $wpdb;
	$table = swiftboard_table( 'notifications' );
	$wpdb->update(
		$table,
		array( 'is_read' => 1 ),
		array(
			'user_id' => (int) $user_id,
			'is_read' => 0,
		),
		array( '%d' ),
		array( '%d', '%d' )
	);
	delete_transient( 'sb_notif_unread_' . $user_id );
}






/**
 * swiftboard_notif_icon().
 *
 * @param string $type Type de contenu.
 * @return mixed
 */
function swiftboard_notif_icon( $type ) {
	$icons = array(
		'upvote'    => '▲',
		'reply'     => '💬',
		'promotion' => '🎉',
		'welcome'   => '👋',
		'approved'  => '✅',
		'mention'   => '@',
	);
	return $icons[ $type ] ?? '🔔';
}

// ============================================================================
// 7. LOCALISATION JS POUR LA CLOCHE
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		add_action(
			'wp_footer',
			static function () {
				printf(
					'<div id="swiftboard-notifs-config" hidden data-rest-url="%s" data-nonce="%s" data-unread-count="%d" data-poll-interval="60" data-mark-all-read="%s" data-no-notifs="%s" data-loading="%s" data-error="%s"></div>',
					esc_attr( esc_url_raw( rest_url( 'swiftboard/v1/' ) ) ),
					esc_attr( wp_create_nonce( 'wp_rest' ) ),
					(int) swiftboard_get_unread_count( get_current_user_id() ),
					esc_attr( __( 'Tout marquer comme lu', 'swiftboard' ) ),
					esc_attr( __( 'Aucune notification', 'swiftboard' ) ),
					esc_attr( __( 'Chargement…', 'swiftboard' ) ),
					esc_attr( __( 'Erreur de chargement', 'swiftboard' ) )
				);
			},
			4
		);
	},
	30
);

// ============================================================================
// 8. RENDU — CLOCHE DANS LA BARRE DE NAVIGATION
// ============================================================================
/**
 * Affiche la cloche de notifications. À appeler dans header.php ou via un hook.
 * On l'injecte juste avant </body> via wp_footer pour qu'elle s'affiche partout
 * sans modification du thème.
 *
 * @return void
 */
function swiftboard_render_notification_bell() {
	if ( ! is_user_logged_in()) return;
	$unread = swiftboard_get_unread_count( get_current_user_id() );
	?>
	<div id="sb-notif-bell" class="sb-notif-bell">
		<button type="button" class="sb-notif-btn"
				aria-haspopup="dialog"
				aria-expanded="false"
				aria-controls="sb-notif-dropdown"
				aria-label="<?php esc_attr_e( 'Notifications', 'swiftboard' ); ?>">
			<span class="sb-notif-icon">🔔</span>
			<?php if ( $unread > 0 ) : ?>
				<span class="sb-notif-badge" data-count="<?php echo (int) $unread; ?>">
                    <?php echo $unread > 99 ? '99+' : intval($unread); /* phpcs:ignore */ ?>
				</span>
			<?php endif; ?>
		</button>
		<div class="sb-notif-dropdown" id="sb-notif-dropdown" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Notifications', 'swiftboard' ); ?>">
			<div class="sb-notif-header">
				<strong><?php esc_html_e( 'Notifications', 'swiftboard' ); ?></strong>
				<button type="button" class="sb-notif-markall" id="sb-notif-markall">
					<?php esc_html_e( 'Tout lire', 'swiftboard' ); ?>
				</button>
			</div>
			<div class="sb-notif-list" id="sb-notif-list">
				<div class="sb-notif-empty"><?php esc_html_e( 'Aucune notification', 'swiftboard' ); ?></div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'swiftboard_render_notification_bell' );

// Notification de bienvenue à l'inscription
add_action(
	'user_register',
	function ( $user_id ) {
		if ( function_exists( 'swiftboard_add_notification' ) ) {
			swiftboard_add_notification(
				array(
					'user_id'  => $user_id,
					'actor_id' => 0,
					'type'     => 'welcome',
					'title'    => 'Bienvenue sur ' . get_bloginfo( 'name' ),
					'excerpt'  => '👋 Découvrez nos forums et créez votre premier sujet !',
				)
			);
		}
	}
);

// ============================================================================
// 9. CRON HEBDO — NETTOYAGE DES NOTIFICATIONS > 30 JOURS
// ============================================================================
add_action(
	'wp',
	function () {
		if ( ! wp_next_scheduled( 'swiftboard_notif_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'swiftboard_notif_cleanup' );
		}
	}
);
add_action(
	'swiftboard_notif_cleanup',
	function () {
		global $wpdb;
		$table = swiftboard_table( 'notifications' );
		// Supprimer les notifs de plus de 30 jours
		$wpdb->query(
			"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);
		// Optimiser la table (Hostinger appréciera)
		// OPTIMIZE TABLE removed — can lock DB on Hostinger shared hosting
	}
);

// ============================================================================
// 10. SUPPRESSION RGPD À LA SUPPRESSION D'UN USER
// ============================================================================
add_action(
	'delete_user',
	function ( $user_id ) {
		global $wpdb;
		$table = swiftboard_table( 'notifications' );
		// Supprimer les notifs reçues par cet user
		$wpdb->delete( $table, array( 'user_id' => (int) $user_id ), array( '%d' ) );
		// Anonymiser les notifs émises par cet user (conserve l'historique)
		$wpdb->update( $table, array( 'actor_id' => 0 ), array( 'actor_id' => (int) $user_id ), array( '%d' ), array( '%d' ) );
	}
);
// ============================================================================
// NOTIFICATION A L'APPROBATION D'UN CONTENU (decouvert en simulation admin)
// ============================================================================
/**
 * Previent l'auteur quand un moderateur publie son sujet ou sa reponse.
 *
 * Sans ce hook, un membre dont le contenu passe en moderation n'est jamais
 * informe de sa publication : il doit revenir verifier manuellement.
 *
 * @param string  $new_status Nouveau statut.
 * @param string  $old_status Ancien statut.
 * @param WP_Post $post       Publication concernee.
 * @return void
 */
function swiftboard_notify_on_approval( $new_status, $old_status, $post ) {
	if ( $new_status !== 'publish' || $old_status === 'publish' ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'topic', 'reply' ), true ) ) {
		return;
	}
	// Statuts de moderation uniquement (pas une creation directe)
	if ( ! in_array( $old_status, array( 'pending', 'draft', 'spam' ), true ) ) {
		return;
	}
	$author_id = (int) $post->post_author;
	if ( ! $author_id ) {
		return;
	}
	// Ne pas notifier si l'auteur s'approuve lui-meme
	if ( $author_id === get_current_user_id() ) {
		return;
	}

	$is_topic = ( $post->post_type === 'topic' );

	swiftboard_add_notification(
		array(
			'user_id'   => $author_id,
			'actor_id'  => get_current_user_id(),
			'type'      => 'approved',
			'post_id'   => $post->ID,
			'post_type' => $post->post_type,
			'title'     => $is_topic
				? __( 'Votre sujet a été publié', 'swiftboard' )
				: __( 'Votre réponse a été publiée', 'swiftboard' ),
			'excerpt'   => wp_trim_words( wp_strip_all_tags( $post->post_title ), 12, '…' ),
		)
	);
}
add_action( 'transition_post_status', 'swiftboard_notify_on_approval', 10, 3 );

