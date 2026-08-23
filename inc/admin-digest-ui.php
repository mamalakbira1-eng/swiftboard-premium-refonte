<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Ecran d'administration du digest.
 *
 * EXI-ARCH-01 : extrait de inc/email-digest.php. Module ADMIN-ONLY : rendu
 * d'interface uniquement. L'envoi, le consentement et le desabonnement
 * restent charges en front — le lien de desabonnement d'un e-mail est ouvert
 * par un destinataire deconnecte.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
/**
 * @return void
 */
function swiftboard_digest_admin_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	$settings = swiftboard_digest_get_settings();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing — checked below
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized — all values are (int) cast
	if ( isset( $_POST['save_digest_settings'] ) && check_admin_referer( 'swiftboard_digest_settings' ) ) {
		$settings['enabled']          = isset( $_POST['enabled'] ) ? 1 : 0;
		$settings['day_of_week']      = sanitize_text_field( wp_unslash( $_POST['day_of_week'] ) );
		$settings['send_hour']        = (int) $_POST['send_hour'];
		$settings['batch_size']       = max( 5, min( 100, (int) $_POST['batch_size'] ) );
		$settings['from_name']        = sanitize_text_field( wp_unslash( $_POST['from_name'] ) );
		$settings['subject_template'] = sanitize_text_field( wp_unslash( $_POST['subject_template'] ) );
		$settings['footer_text']      = sanitize_text_field( wp_unslash( $_POST['footer_text'] ) );
		update_option( 'swiftboard_digest_settings', $settings );
		echo '<div class="notice notice-success is-dismissible"><p>✅ Réglages enregistrés.</p></div>';
		$settings = swiftboard_digest_get_settings();
	}

	// Test envoi à l'admin courant
	if ( isset( $_POST['send_test'] ) && check_admin_referer( 'swiftboard_digest_test' ) ) {
		$result = swiftboard_digest_send_to_user( get_current_user_id() );
		if ( $result === 'sent' ) {
			echo '<div class="notice notice-success is-dismissible"><p>✅ Email de test envoyé à votre adresse.</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>❌ Échec envoi test (' . esc_html( $result ) . '). Vérifiez la config SMTP.</p></div>';
		}
	}

	// Reset complet (re-déclenche le digest pour tout le monde)
	if ( isset( $_POST['reset_sent'] ) && check_admin_referer( 'swiftboard_digest_reset' ) ) {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->usermeta}
             WHERE meta_key LIKE 'sb_digest_sent_%'"
		);
		echo '<div class="notice notice-success is-dismissible"><p>🔄 Tous les flags "envoyé" ont été réinitialisés.</p></div>';
	}

	$last_log                               = get_option( 'swiftboard_digest_last_log', array() );
	if ( ! is_array( $last_log )) $last_log = array();

	$days = array(
		'monday'    => 'Lundi',
		'tuesday'   => 'Mardi',
		'wednesday' => 'Mercredi',
		'thursday'  => 'Jeudi',
		'friday'    => 'Vendredi',
		'saturday'  => 'Samedi',
		'sunday'    => 'Dimanche',
	);
	?>
	<div class="wrap">
		<h1>📧 Email Digest Hebdomadaire</h1>
		<p class="description">
			Envoie chaque semaine un résumé personnalisé à vos utilisateurs actifs :
			top répondeurs, sujets chauds, statistiques personnelles, promotions.
		</p>

		<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h3 style="margin-top:0;">🛡️ Optimisé Hostinger</h3>
			<ul style="margin:0;padding-left:20px;">
				<li><strong><?php esc_html_e( 'Batch processing :', 'swiftboard' ); ?></strong> <?php echo SB_DIGEST_BATCH_SIZE; ?> emails par tick (5 min entre chaque batch)</li>
				<li><strong><?php esc_html_e( 'Quota horaire :', 'swiftboard' ); ?></strong> max <?php echo SB_DIGEST_MAX_PER_HOUR; ?> emails/heure (sécurité)</li>
				<li><strong><?php esc_html_e( 'Opt-in utilisateur :', 'swiftboard' ); ?></strong> chaque user peut se désabonner</li>
				<li><strong><?php esc_html_e( 'Skip si vide :', 'swiftboard' ); ?></strong> aucun email envoyé si l'user n'a aucune activité</li>
			</ul>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'swiftboard_digest_settings' ); ?>
			<h2 class="title"><?php esc_html_e( 'Configuration', 'swiftboard' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Activer le digest', 'swiftboard' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?>>
							Activer l'envoi hebdomadaire
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="day_of_week"><?php esc_html_e( 'Jour d\'envoi', 'swiftboard' ); ?></label></th>
					<td>
						<select name="day_of_week" id="day_of_week">
							<?php foreach ( $days as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['day_of_week'], $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="send_hour"><?php esc_html_e( 'Heure d\'envoi', 'swiftboard' ); ?></label></th>
					<td>
						<select name="send_hour" id="send_hour">
							<?php for ( $h = 0; $h < 24; $h++ ) : ?>
								<option value="<?php echo intval( $h ); ?>" <?php selected( $settings['send_hour'], $h ); ?>>
									<?php printf( '%02d:00', $h ); ?>
								</option>
							<?php endfor; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Heure locale du serveur.', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="batch_size"><?php esc_html_e( 'Taille de batch', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="batch_size" id="batch_size"
								value="<?php echo (int) $settings['batch_size']; ?>" min="5" max="100" class="small-text">
						<p class="description"><?php esc_html_e( 'Emails envoyés par cycle (5-100). Défaut :', 'swiftboard' ); ?><?php echo SB_DIGEST_BATCH_SIZE; ?>.</p>
					</td>
				</tr>
				<tr>
					<th><label for="from_name"><?php esc_html_e( 'Nom expéditeur', 'swiftboard' ); ?></label></th>
					<td>
						<input type="text" name="from_name" id="from_name"
								value="<?php echo esc_attr( $settings['from_name'] ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th><label for="subject_template"><?php esc_html_e( 'Sujet de l\'email', 'swiftboard' ); ?></label></th>
					<td>
						<input type="text" name="subject_template" id="subject_template"
								value="<?php echo esc_attr( $settings['subject_template'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Variable :', 'swiftboard' ); ?><code>{site_name}</code></p>
					</td>
				</tr>
				<tr>
					<th><label for="footer_text"><?php esc_html_e( 'Pied de page', 'swiftboard' ); ?></label></th>
					<td>
						<input type="text" name="footer_text" id="footer_text"
								value="<?php echo esc_attr( $settings['footer_text'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Variable :', 'swiftboard' ); ?><code>{site_name}</code></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="save_digest_settings" class="button button-primary">💾 Enregistrer</button>
			</p>
		</form>

		<h2 class="title"><?php esc_html_e( 'Tests & maintenance', 'swiftboard' ); ?></h2>
		<div style="display:flex;gap:12px;flex-wrap:wrap;">
			<form method="post" action="">
				<?php wp_nonce_field( 'swiftboard_digest_test' ); ?>
				<button type="submit" name="send_test" value="1" class="button button-secondary">
					📨 M'envoyer un email de test
				</button>
			</form>
			<form method="post" action="" data-confirm="Réinitialiser tous les flags envoyé ? Le prochain cron renverra le digest à TOUS les users actifs.">
				<?php wp_nonce_field( 'swiftboard_digest_reset' ); ?>
				<button type="submit" name="reset_sent" value="1" class="button button-secondary">
					🔄 Réinitialiser les flags "envoyé"
				</button>
			</form>
		</div>

		<?php if ( ! empty( $last_log ) ) : ?>
		<h2>📜 Derniers envois</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'Date', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Offset', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Envoyés', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Total traité', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( array_reverse( $last_log ) as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( $entry['time'] ); ?></td>
					<td><?php echo (int) $entry['offset']; ?></td>
					<td><strong style="color:#16a34a;"><?php echo (int) $entry['sent']; ?></strong></td>
					<td><?php echo (int) $entry['total']; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
	<?php
}

