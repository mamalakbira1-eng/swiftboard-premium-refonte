<?php
if ( ! defined( 'ABSPATH' )) exit;

/**
 * SwiftBoard — Audit Trail pour les actions de modération
 *
 * Log toutes les actions de modération (close/open/spam/trash topic, approuve/rejette image/reply)
 * dans une table dédiée pour audit RGPD + traçabilité.
 *
 * @package SwiftBoard
 * @since 4.4.0
 */
// ============================================================================
// 1. CRÉER LA TABLE D'AUDIT LOG
// ============================================================================
add_action(
	'after_switch_theme',
	function () {
		global $wpdb;
		$table           = swiftboard_table( 'audit_log' );
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        moderator_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        action       VARCHAR(50) NOT NULL DEFAULT '',
        target_type  VARCHAR(20) NOT NULL DEFAULT '',
        target_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        target_title VARCHAR(255) NOT NULL DEFAULT '',
        reason       VARCHAR(255) NOT NULL DEFAULT '',
        ip_address   VARCHAR(100) NOT NULL DEFAULT '',
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_moderator (moderator_id),
        KEY idx_target (target_type, target_id),
        KEY idx_created (created_at)
    ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
);

// ============================================================================
// 2. FONCTION HELPER POUR LOGGER
// ============================================================================
/**
 * Log une action de modération.
 *
 * @param array<string, mixed> $args {
 *   @type int    $moderator_id ID du modérateur (défaut: current user)
 *   @type string $action       Action effectuée (close, open, spam, trash, approve, reject, ban, etc.)
 *   @type string $target_type  Type de cible (topic, reply, image, user)
 *   @type int    $target_id    ID de la cible
 *   @type string $target_title Titre lisible de la cible
 *   @type string $reason       Raison optionnelle
 * }
 * @return mixed
 */
function swiftboard_log_moderation( $args ) {
	global $wpdb;
	$table = swiftboard_table( 'audit_log' );

	// Vérifier que la table existe
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return false;
	}

	$moderator_id = isset( $args['moderator_id'] ) ? (int) $args['moderator_id'] : get_current_user_id();
	$action       = isset( $args['action'] ) ? sanitize_text_field( $args['action'] ) : '';
	$target_type  = isset( $args['target_type'] ) ? sanitize_text_field( $args['target_type'] ) : '';
	$target_id    = isset( $args['target_id'] ) ? (int) $args['target_id'] : 0;
	$target_title = isset( $args['target_title'] ) ? sanitize_text_field( $args['target_title'] ) : '';
	$reason       = isset( $args['reason'] ) ? sanitize_text_field( $args['reason'] ) : '';
	$ip_address   = function_exists( 'wp_privacy_anonymize_ip' ) ? wp_privacy_anonymize_ip( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) : '';

	if ( empty( $action ) || empty( $target_type ) || ! $target_id ) {
		return false;
	}

	return $wpdb->insert(
		$table,
		array(
			'moderator_id' => $moderator_id,
			'action'       => $action,
			'target_type'  => $target_type,
			'target_id'    => $target_id,
			'target_title' => $target_title,
			'reason'       => $reason,
			'ip_address'   => $ip_address,
			'created_at'   => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
	);
}

// ============================================================================
// 3. HOOKS — LOG AUTOMATIQUE SUR LES ACTIONS DE MODÉRATION
// ============================================================================

// Log quand un topic est fermé/ouvert/épinglé/désépinglé
add_action(
	'bbp_close_topic',
	function ( $topic_id ) {
		swiftboard_log_moderation(
			array(
				'action'       => 'close_topic',
				'target_type'  => 'topic',
				'target_id'    => $topic_id,
				'target_title' => get_the_title( $topic_id ),
				'reason'       => 'Topic fermé',
			)
		);
	}
);

add_action(
	'bbp_open_topic',
	function ( $topic_id ) {
		swiftboard_log_moderation(
			array(
				'action'       => 'open_topic',
				'target_type'  => 'topic',
				'target_id'    => $topic_id,
				'target_title' => get_the_title( $topic_id ),
				'reason'       => 'Topic rouvert',
			)
		);
	}
);

add_action(
	'bbp_stick_topic',
	function ( $topic_id, $super ) {
		swiftboard_log_moderation(
			array(
				'action'       => 'stick_topic',
				'target_type'  => 'topic',
				'target_id'    => $topic_id,
				'target_title' => get_the_title( $topic_id ),
				'reason'       => $super ? 'Épinglé (super)' : 'Épinglé',
			)
		);
	},
	10,
	2
);

add_action(
	'bbp_unstick_topic',
	function ( $topic_id ) {
		swiftboard_log_moderation(
			array(
				'action'       => 'unstick_topic',
				'target_type'  => 'topic',
				'target_id'    => $topic_id,
				'target_title' => get_the_title( $topic_id ),
				'reason'       => 'Désépinglé',
			)
		);
	}
);

// Log quand un post passe en spam/trash (via wp_update_post sur post_status)
add_action(
	'transition_post_status',
	function ( $new_status, $old_status, $post ) {
		if ($new_status === $old_status) return;
		if ( ! in_array( $post->post_type, array( 'topic', 'reply' ), true )) return;
		if ( ! current_user_can( 'moderate_comments' )) return;

		$logged_status = in_array( $new_status, array( 'spam', 'trash' ), true ) ? $new_status : null;
		if ( ! $logged_status) return;

		swiftboard_log_moderation(
			array(
				'action'       => $logged_status . '_' . $post->post_type,
				'target_type'  => $post->post_type,
				'target_id'    => $post->ID,
				'target_title' => $post->post_title ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 8, '…' ),
				'reason'       => ucfirst( $logged_status ) . ' via moderation',
			)
		);
	},
	10,
	3
);

// Log quand une image est approuvée/rejetée
add_action(
	'swiftboard_image_moderated',
	function ( $image_id, $action, $moderator_id ) {
		swiftboard_log_moderation(
			array(
				'moderator_id' => $moderator_id,
				'action'       => 'image_' . $action,
				'target_type'  => 'image',
				'target_id'    => $image_id,
				'target_title' => 'Image #' . $image_id,
				'reason'       => 'Image ' . $action,
			)
		);
	},
	10,
	3
);

// ============================================================================
// 4. PAGE ADMIN — AUDIT LOG VIEWER
// ============================================================================
add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Audit Log', 'swiftboard' ),
			__( 'Audit Log', 'swiftboard' ),
			'manage_options',
			'swiftboard-audit-log',
			'swiftboard_audit_log_page'
		);
	},
	30
);

/**
 * @return void
 */
function swiftboard_audit_log_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	global $wpdb;
	$table = swiftboard_table( 'audit_log' );

	// Filtres
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin read-only filters, capability checked above.
	$moderator_filter = isset( $_GET['moderator'] ) ? (int) wp_unslash( $_GET['moderator'] ) : 0;
	$action_filter    = isset( $_GET['action_type'] ) ? sanitize_text_field( wp_unslash( $_GET['action_type'] ) ) : '';
	$per_page         = 50;
	$page             = max( 1, (int) ( isset( $_GET['paged'] ) ? wp_unslash( $_GET['paged'] ) : 1 ) );
	$offset           = ( $page - 1 ) * $per_page;

	$where = '1=1';
	if ( $moderator_filter ) {
		$where .= $wpdb->prepare( ' AND moderator_id = %d', $moderator_filter );
	}
	if ( $action_filter ) {
		$where .= $wpdb->prepare( ' AND action = %s', $action_filter );
	}

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
	$logs  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		),
		ARRAY_A
	);

	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	?>
	<div class="wrap">
		<h1>📋 Audit Log — Actions de modération</h1>

		<form method="get" style="margin:16px 0;">
			<input type="hidden" name="page" value="swiftboard-audit-log">
			<select name="moderator">
				<option value="0">Tous les modérateurs</option>
				<?php
				$moderators = $wpdb->get_results( "SELECT DISTINCT moderator_id FROM {$table} ORDER BY moderator_id" );
				foreach ( $moderators as $mod ) {
					$u = get_userdata( $mod->moderator_id );
					if ( $u ) {
						echo '<option value="' . (int) $mod->moderator_id . '" ' . selected( $moderator_filter, (int) $mod->moderator_id, false ) . '>' . esc_html( $u->display_name ) . '</option>';
					}
				}
				?>
			</select>
			<select name="action_type">
				<option value="">Toutes les actions</option>
				<?php
				$actions = array( 'close_topic', 'open_topic', 'stick_topic', 'unstick_topic', 'spam_topic', 'trash_topic', 'spam_reply', 'trash_reply', 'image_approve', 'image_reject' );
				foreach ( $actions as $a ) {
					echo '<option value="' . esc_attr( $a ) . '" ' . selected( $action_filter, $a, false ) . '>' . esc_html( $a ) . '</option>';
				}
				?>
			</select>
			<button type="submit" class="button">Filtrer</button>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th width="50">ID</th>
					<th width="180">Date</th>
					<th width="180">Modérateur</th>
					<th width="180">Action</th>
					<th width="100">Cible</th>
					<th>Titre</th>
					<th width="150">IP</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">Aucune action de modération loggée pour le moment.</td></tr>
					<?php
				else :
					foreach ( $logs as $log ) :
						?>
											<?php
											$mod      = get_userdata( $log['moderator_id'] );
											$mod_name = $mod ? $mod->display_name : 'User #' . $log['moderator_id'];
											?>
					<tr>
						<td><?php echo (int) $log['id']; ?></td>
						<td><?php echo esc_html( $log['created_at'] ); ?></td>
						<td><?php echo esc_html( $mod_name ); ?></td>
						<td><code><?php echo esc_html( $log['action'] ); ?></code></td>
						<td><?php echo esc_html( $log['target_type'] . ' #' . $log['target_id'] ); ?></td>
						<td><?php echo esc_html( $log['target_title'] ); ?></td>
						<td><code><?php echo esc_html( $log['ip_address'] ); ?></code></td>
					</tr>
									<?php
				endforeach;
endif;
				?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $page,
							'total'     => $total_pages,
							'prev_text' => '« Précédent',
							'next_text' => 'Suivant »',
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>

		<p style="margin-top:24px;color:#666;font-size:13px;">
			ℹ️ Cet audit log est conservé indéfiniment. Pour RGPD, les IP sont anonymisées via
			<code>wp_privacy_anonymize_ip()</code> au moment du log.
			Total : <strong><?php echo (int) $total; ?></strong> action(s) loggée(s).
		</p>
	</div>
	<?php
}

// ============================================================================
// 5. RGPD — ANONYMISER LES LOGS À LA SUPPRESSION D'UN USER
// ============================================================================
add_action(
	'delete_user',
	function ( $user_id ) {
		global $wpdb;
		$table = swiftboard_table( 'audit_log' );
		// Le modérateur supprimé conserve son ID dans les logs (audit trail RGPD),
		// mais on anonymise l'IP au cas où elle n'aurait pas été anonymisée
		$wpdb->update(
			$table,
			array( 'ip_address' => '' ),
			array( 'moderator_id' => (int) $user_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
);

