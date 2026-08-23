<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Ecrans de moderation des images.
 *
 * EXI-ARCH-02 : extrait de inc/image-upload.php. Module ADMIN-ONLY : il ne
 * contient que des ecrans, un handler AJAX d'administration et le badge du
 * menu. Les routes REST d'envoi et d'approbation restent en front, sans quoi
 * elles renverraient 404 aux visiteurs (defaut releve par le cahier).
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
/**
 * @return void
 */
function swiftboard_moderation_admin_menu() {
	add_menu_page(
		__( 'Modération images', 'swiftboard' ),
		__( 'Images forum', 'swiftboard' ),
		'manage_options',
		'swiftboard-moderation',
		'swiftboard_moderation_page',
		'dashicons-format-image',
		30
	);
}
add_action( 'admin_menu', 'swiftboard_moderation_admin_menu' );

/**
 * @return void
 */
function swiftboard_moderation_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'moderate_comments' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	global $wpdb;
	$table = swiftboard_table( 'uploads' );

	// Statistiques
	$pending_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" );
	$approved_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'approved'" );
	$rejected_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'rejected'" );

	// Économie d'espace totale
	$total_original  = (int) $wpdb->get_var( "SELECT COALESCE(SUM(original_size), 0) FROM $table WHERE status IN ('approved','pending')" );
	$total_converted = (int) $wpdb->get_var( "SELECT COALESCE(SUM(file_size), 0) FROM $table WHERE status IN ('approved','pending')" );
	$total_savings   = $total_original > 0 ? round( ( 1 - $total_converted / $total_original ) * 100 ) : 0;

	?>
	<div class="wrap">
		<h1>🖼️ Modération des images du forum</h1>

		<div style="display: flex; gap: 20px; margin: 20px 0;">
			<div class="swiftboard-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; flex: 1; text-align: center;">
				<div style="font-size: 2rem; font-weight: bold; color: #d97706;">⏳ <?php echo intval( $pending_count ); ?></div>
				<div>En attente</div>
			</div>
			<div class="swiftboard-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; flex: 1; text-align: center;">
				<div style="font-size: 2rem; font-weight: bold; color: #16a34a;">✅ <?php echo intval( $approved_count ); ?></div>
				<div><?php esc_html_e( 'Approuvées', 'swiftboard' ); ?></div>
			</div>
			<div class="swiftboard-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; flex: 1; text-align: center;">
				<div style="font-size: 2rem; font-weight: bold; color: #dc2626;">❌ <?php echo intval( $rejected_count ); ?></div>
				<div><?php esc_html_e( 'Rejetées', 'swiftboard' ); ?></div>
			</div>
			<div class="swiftboard-stat-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; flex: 1; text-align: center;">
				<div style="font-size: 2rem; font-weight: bold; color: #006cbd;">💾 <?php echo intval( $total_savings ); ?>%</div>
				<div><?php esc_html_e( 'Économie AVIF (', 'swiftboard' ); ?><?php echo size_format( $total_original ); ?> → <?php echo size_format( $total_converted ); ?>)</div>
			</div>
		</div>

		<?php if ( $pending_count > 0 ) : ?>
		<h2><?php esc_html_e( 'Images en attente de validation', 'swiftboard' ); ?></h2>
		<div id="swiftboard-moderation-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
			<?php
			$images = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at DESC LIMIT 50" );
			foreach ( $images as $img ) :
				$user    = get_userdata( $img->user_id );
				$savings = $img->original_size > 0 ? round( ( 1 - $img->file_size / $img->original_size ) * 100 ) : 0;
				?>
			<div class="swiftboard-moderation-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
				<div style="background: #f8fafc; padding: 8px; text-align: center;">
					<img src="<?php echo esc_url( $img->image_url ); ?>" style="max-width: 100%; max-height: 200px; object-fit: contain;" alt="">
				</div>
				<div style="padding: 12px;">
					<div style="font-size: 0.85rem; color: #666;">
						<strong><?php echo esc_html( $user ? $user->display_name : 'Inconnu' ); ?></strong><br>
						<?php echo esc_html( $img->created_at ); ?><br>
						<span style="color: #16a34a;">💾 -<?php echo intval( $savings ); ?>% (<?php echo size_format( $img->original_size ); ?> → <?php echo size_format( $img->file_size ); ?>)</span>
					</div>
					<div style="display: flex; gap: 8px; margin-top: 10px;">
						<button class="button button-primary swiftboard-approve-btn" data-id="<?php echo esc_attr( (int) $img->id ); ?>">
							✅ Approuver
						</button>
						<button class="button button-link-delete swiftboard-reject-btn" data-id="<?php echo esc_attr( (int) $img->id ); ?>">
							❌ Rejeter
						</button>
					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.swiftboard-approve-btn').on('click', function() {
				var id = $(this).data('id');
				var card = $(this).closest('.swiftboard-moderation-card');
				$.post(swiftboardAdmin.ajaxUrl, {
					action: 'swiftboard_moderate',
					nonce: swiftboardAdmin.nonce,
					image_id: id,
					decision: 'approve'
				}, function(response) {
					if (response.success) {
						card.fadeOut(300, function() { card.remove(); });
					}
				});
			});

			$('.swiftboard-reject-btn').on('click', function() {
				if (!confirm('Supprimer définitivement cette image ?')) return;
				var id = $(this).data('id');
				var card = $(this).closest('.swiftboard-moderation-card');
				$.post(swiftboardAdmin.ajaxUrl, {
					action: 'swiftboard_moderate',
					nonce: swiftboardAdmin.nonce,
					image_id: id,
					decision: 'reject'
				}, function(response) {
					if (response.success) {
						card.fadeOut(300, function() { card.remove(); });
					}
				});
			});
		});
		</script>
		<?php else : ?>
		<div style="text-align: center; padding: 60px; background: #fff; border-radius: 8px; margin-top: 20px;">
			<div style="font-size: 3rem;">✅</div>
			<h2><?php esc_html_e( 'Aucune image en attente', 'swiftboard' ); ?></h2>
			<p><?php esc_html_e( 'Toutes les images ont été modérées.', 'swiftboard' ); ?></p>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

// ============================================================================
// 11. AJAX HANDLER POUR LA MODÉRATION
// ============================================================================
/**
 * @return void
 */
function swiftboard_admin_ajax() {
	wp_enqueue_script( 'swiftboard-admin', get_template_directory_uri() . '/assets/js/admin.js', array(), SWIFTBOARD_VERSION, true );
	wp_localize_script(
		'swiftboard-admin',
		'swiftboardAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'restUrl' => esc_url_raw( rest_url( 'swiftboard/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'swiftboard_admin_ajax' );

/**
 * @return void
 */
function swiftboard_ajax_moderate() {
	check_ajax_referer( 'swiftboard_moderate', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied', 403 );
	}

	$image_id = (int) $_POST['image_id'];
	$decision = sanitize_text_field( wp_unslash( $_POST['decision'] ) );

	global $wpdb;
	$table = swiftboard_table( 'uploads' );

	if ( $decision === 'approve' ) {
		$wpdb->update(
			$table,
			array(
				'status'       => 'approved',
				'moderated_by' => get_current_user_id(),
				'moderated_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $image_id,
				'status' => 'pending',
			)
		);
		wp_send_json_success( array( 'message' => 'Image approuvée' ) );
	} elseif ( $decision === 'reject' ) {
		$image = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $image_id ) );
		if ( $image && file_exists( $image->filepath ) ) {
			unlink( $image->filepath );
		}
		$wpdb->update(
			$table,
			array(
				'status'       => 'rejected',
				'moderated_by' => get_current_user_id(),
				'moderated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $image_id )
		);
		wp_send_json_success( array( 'message' => 'Image rejetée et supprimée' ) );
	}

	wp_send_json_error( 'Action invalide', 400 );
}
add_action( 'wp_ajax_swiftboard_moderate', 'swiftboard_ajax_moderate' );


// Badge de moderation : voir inc/admin-moderation-badge.php (CDC-CI-02).

// ============================================================================
// 13. LOG D'AUDIT (audit trail)

// ============================================================================
// 14. PAGE DE RÉGLAGES (limites + anti-spam)
// ============================================================================
/**
 * @return void
 */
function swiftboard_upload_settings_menu() {
	add_submenu_page(
		'swiftboard-moderation',
		__( 'Réglages upload', 'swiftboard' ),
		__( 'Réglages', 'swiftboard' ),
		'manage_options',
		'swiftboard-upload-settings',
		'swiftboard_upload_settings_page'
	);
}
add_action( 'admin_menu', 'swiftboard_upload_settings_menu' );

/**
 * @return void
 */
function swiftboard_upload_settings_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	if ( isset( $_POST['swiftboard_save_settings'] ) && check_admin_referer( 'swiftboard_upload_settings' ) ) {
		update_option( 'swiftboard_upload_daily_limit', (int) $_POST['daily_limit'] );
		update_option( 'swiftboard_upload_total_limit', (int) $_POST['total_limit'] );
		update_option( 'swiftboard_upload_min_posts', (int) $_POST['min_posts'] );
		update_option( 'swiftboard_upload_max_rejected', (int) $_POST['max_rejected'] );
		update_option( 'swiftboard_upload_avif_quality', (int) $_POST['avif_quality'] );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Réglages enregistrés.', 'swiftboard' ) . '</p></div>';
	}

	$daily_limit  = (int) get_option( 'swiftboard_upload_daily_limit', 2 );
	$total_limit  = (int) get_option( 'swiftboard_upload_total_limit', 200 );
	$min_posts    = (int) get_option( 'swiftboard_upload_min_posts', 3 );
	$max_rejected = (int) get_option( 'swiftboard_upload_max_rejected', 5 );
	$avif_quality = (int) get_option( 'swiftboard_upload_avif_quality', 60 );
	?>
	<div class="wrap">
		<h1>⚙️ Réglages upload d'images</h1>
		<form method="post" action="">
			<?php wp_nonce_field( 'swiftboard_upload_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="daily_limit"><?php esc_html_e( 'Limite quotidienne par utilisateur', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="daily_limit" id="daily_limit" value="<?php echo intval( $daily_limit ); ?>" min="1" max="1000" class="small-text">
						<p class="description"><?php esc_html_e( 'Nombre maximum d\'images qu\'un utilisateur peut uploader par jour.', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="total_limit"><?php esc_html_e( 'Limite totale par utilisateur', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="total_limit" id="total_limit" value="<?php echo intval( $total_limit ); ?>" min="1" max="10000" class="small-text">
						<p class="description"><?php esc_html_e( 'Nombre total maximum d\'images par utilisateur (toutes confondues).', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="min_posts"><?php esc_html_e( 'Messages minimum requis', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="min_posts" id="min_posts" value="<?php echo intval( $min_posts ); ?>" min="0" max="100" class="small-text">
						<p class="description">Un utilisateur doit avoir au moins ce nombre de messages (sujets + réponses) pour uploader des images. 0 = désactivé.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="max_rejected"><?php esc_html_e( 'Seuil de rejets (anti-spam)', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="max_rejected" id="max_rejected" value="<?php echo intval( $max_rejected ); ?>" min="1" max="100" class="small-text">
						<p class="description">Si un utilisateur a eu ce nombre d'images rejetées dans les dernières 24h, il ne peut plus uploader.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="avif_quality"><?php esc_html_e( 'Qualité AVIF (0-100)', 'swiftboard' ); ?></label></th>
					<td>
						<input type="range" name="avif_quality" id="avif_quality" value="<?php echo intval( $avif_quality ); ?>" min="10" max="100" step="5" oninput="document.getElementById('avif_quality_val').textContent = this.value">
						<span id="avif_quality_val" style="font-weight:bold;"><?php echo intval( $avif_quality ); ?></span>
						<p class="description">Qualité de compression AVIF. Plus bas = plus petit fichier mais moins de qualité. Recommandé : 50-70.</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="swiftboard_save_settings" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'swiftboard' ); ?></button>
			</p>
		</form>

		<h2>📋 Journal d'audit (50 dernières actions)</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Image ID', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Utilisateur', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Action', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Note', 'swiftboard' ); ?></th>
					<th>IP</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$log = get_option( 'swiftboard_upload_audit_log', array() );
				$log = array_reverse( array_slice( $log, -50 ) );
				foreach ( $log as $entry ) :
					$user = get_userdata( $entry['user_id'] );
					?>
				<tr>
					<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
					<td>#<?php echo (int) $entry['image_id']; ?></td>
					<td><?php echo esc_html( $user ? $user->display_name : 'User #' . $entry['user_id'] ); ?></td>
					<td>
						<?php
						$action_labels = array(
							'uploaded' => '<span style="color:#d97706;">📤 Uploadé</span>',
							'approved' => '<span style="color:#16a34a;">✅ Approuvé</span>',
							'rejected' => '<span style="color:#dc2626;">❌ Rejeté</span>',
						);
						echo $action_labels[ $entry['action'] ] ?? esc_html( $entry['action'] );
						?>
					</td>
					<td style="font-size:0.85rem;color:#666;"><?php echo esc_html( $entry['note'] ); ?></td>
					<td style="font-family:monospace;font-size:0.8rem;"><?php echo esc_html( $entry['ip'] ); ?></td>
				</tr>
				<?php endforeach; ?>
				<?php if ( empty( $log ) ) : ?>
				<tr><td colspan="6" style="text-align:center;color:#999;"><?php esc_html_e( 'Aucune action enregistrée.', 'swiftboard' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

// ============================================================================
// JOURNALISATION — DEPLACEE (EXI-ARCH-02)
// ============================================================================
// swiftboard_log_upload_action() est appelee par swiftboard_upload_enregistrer(),
// qui s'execute pendant l'envoi d'un VISITEUR. La laisser dans ce module
// admin-only produisait « Call to undefined function » en front.
// Elle vit desormais dans inc/image-upload-schema.php, charge en front.

