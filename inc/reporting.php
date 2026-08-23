<?php
if ( ! defined( 'ABSPATH' ))exit;

/**
 * SwiftBoard — Système de Modération Front-End (Report / Flagging)
 *
 * @package SwiftBoard
 * @since 6.0.0
 */
function swiftboard_create_reports_table(): void {
	global $wpdb;
	$table   = swiftboard_table( 'reports' );
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        reporter_id bigint(20) unsigned NOT NULL,
        reason varchar(100) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'open',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_post (post_id),
        KEY idx_status (status)
    ) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
add_action( 'after_switch_theme', 'swiftboard_create_reports_table' );
add_action(
	'init',
	function () {
		global $wpdb;
		$table = swiftboard_table( 'reports' );
		if (get_transient( 'sb_reports_tbl_ok' )) return;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			swiftboard_create_reports_table();
		}
		set_transient( 'sb_reports_tbl_ok', 1, DAY_IN_SECONDS );
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/report',
			array(
				'methods'             => 'POST',
				'callback'            => 'swiftboard_rest_handle_report',
				'permission_callback' => function ( WP_REST_Request $request ) {
					if ( ! is_user_logged_in() ) {
						return new WP_Error( 'rest_forbidden', __( 'Authentification requise.', 'swiftboard' ), array( 'status' => 401 ) );
					}

					$nonce = $request->get_header( 'X-WP-Nonce' );
					if ( ! $nonce ) {
						$nonce = $request->get_param( '_wpnonce' );
					}
					if ( ! wp_verify_nonce( sanitize_text_field( (string) $nonce ), 'wp_rest' ) ) {
						return new WP_Error( 'rest_cookie_invalid_nonce', __( 'Nonce REST invalide.', 'swiftboard' ), array( 'status' => 403 ) );
					}

					if ( ! current_user_can( 'read' ) ) {
						return new WP_Error( 'rest_forbidden', __( 'Permission insuffisante.', 'swiftboard' ), array( 'status' => 403 ) );
					}

					return true;
				},
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'reason'  => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}
);

function swiftboard_rest_handle_report( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	global $wpdb;
	$post_id = (int) $request->get_param( 'post_id' );
	$reason  = sanitize_text_field( $request->get_param( 'reason' ) );
	$user_id = get_current_user_id();

	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( 'topic', 'reply' ), true ) ) {
		return new WP_Error( 'invalid_post', __( 'Contenu invalide.', 'swiftboard' ), array( 'status' => 404 ) );
	}

	$table = swiftboard_table( 'reports' );
	$wpdb->insert(
		$table,
		array(
			'post_id'     => $post_id,
			'reporter_id' => $user_id,
			'reason'      => substr( $reason, 0, 100 ),
			'status'      => 'open',
			'created_at'  => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%s', '%s', '%s' )
	);

	return rest_ensure_response(
		array(
			'success'   => true,
			'report_id' => $wpdb->insert_id,
			'message'   => __( 'Signalement envoyé aux modérateurs.', 'swiftboard' ),
		)
	);
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Signalements', 'swiftboard' ),
			__( '🚩 Signalements', 'swiftboard' ),
			'moderate_comments',
			'swiftboard-reports',
			'swiftboard_reports_page_render'
		);
	}
);

function swiftboard_reports_page_render(): void {
	global $wpdb;
	if ( ! current_user_can( 'moderate_comments' )) return;
	$table = swiftboard_table( 'reports' );
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'open' ORDER BY id DESC LIMIT 50" );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '🚩 Modération : Contenus Signalés', 'swiftboard' ); ?></h1>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>ID</th>
					<th>Post ID</th>
					<th>Signaleur</th>
					<th>Motif</th>
					<th>Date</th>
					<th>Statut</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Aucun signalement en attente. Excellent !', 'swiftboard' ); ?></td></tr>
					<?php
				else :
					foreach ( $rows as $r ) :
						?>
					<tr>
						<td><?php echo (int) $r->id; ?></td>
						<td><?php echo (int) $r->post_id; ?></td>
						<td><?php echo (int) $r->reporter_id; ?></td>
						<td><?php echo esc_html( $r->reason ); ?></td>
						<td><?php echo esc_html( $r->created_at ); ?></td>
						<td><?php echo esc_html( $r->status ); ?></td>
					</tr>
									<?php
				endforeach;
endif;
				?>
			</tbody>
		</table>
	</div>
	<?php
}
