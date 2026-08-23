<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Social Login Settings (Admin)
 *
 * Settings page under SwiftBoard menu for configuring OAuth API keys.
 * Supports Google, GitHub, Facebook.
 *
 * @package SwiftBoard
 * @since 7.2.0
 */
// ============================================================================
// 1. ADMIN MENU
// ============================================================================
add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Connexion sociale', 'swiftboard' ),
			__( '🔗 Connexion sociale', 'swiftboard' ),
			'manage_options',
			'swiftboard-social-login',
			'swiftboard_social_login_page'
		);
	}
);

// ============================================================================
// 2. SETTINGS REGISTRATION
// ============================================================================
add_action(
	'admin_init',
	function () {
		register_setting(
			'swiftboard_social',
			'swiftboard_social_settings',
			array(
				'sanitize_callback' => 'swiftboard_sanitize_social_settings',
			)
		);

		// Google
		add_settings_section( 'swiftboard_google', 'Google', '__return_null', 'swiftboard-social-login' );
		add_settings_field(
			'swiftboard_google_client_id',
			'Client ID',
			'swiftboard_render_text_field',
			'swiftboard-social-login',
			'swiftboard_google',
			array(
				'label_for'   => 'google_client_id',
				'description' => 'Google Cloud Console > APIs & Services > Credentials > OAuth 2.0 Client ID',
			)
		);

		// GitHub
		add_settings_section( 'swiftboard_github', 'GitHub', '__return_null', 'swiftboard-social-login' );
		add_settings_field(
			'swiftboard_github_client_id',
			'Client ID',
			'swiftboard_render_text_field',
			'swiftboard-social-login',
			'swiftboard_github',
			array(
				'label_for'   => 'github_client_id',
				'description' => 'GitHub > Settings > Developer settings > OAuth Apps > Client ID',
			)
		);
		add_settings_field(
			'swiftboard_github_client_secret',
			'Client Secret',
			'swiftboard_render_text_field',
			'swiftboard-social-login',
			'swiftboard_github',
			array(
				'label_for'   => 'github_client_secret',
				'type'        => 'password',
				'description' => 'GitHub > OAuth Apps > Client Secret (ne jamais exposer en front)',
			)
		);

		// Facebook
		add_settings_section( 'swiftboard_facebook', 'Facebook', '__return_null', 'swiftboard-social-login' );
		add_settings_field(
			'swiftboard_facebook_app_id',
			'App ID',
			'swiftboard_render_text_field',
			'swiftboard-social-login',
			'swiftboard_facebook',
			array(
				'label_for'   => 'facebook_app_id',
				'description' => 'Meta for Developers > Apps > Settings > Basic > App ID',
			)
		);
		add_settings_field(
			'swiftboard_facebook_app_secret',
			'App Secret',
			'swiftboard_render_text_field',
			'swiftboard-social-login',
			'swiftboard_facebook',
			array(
				'label_for'   => 'facebook_app_secret',
				'type'        => 'password',
				'description' => 'Meta for Developers > Apps > Settings > Basic > App Secret',
			)
		);
	}
);

// ============================================================================
// 3. RENDER FUNCTIONS
// ============================================================================
/** @param array<string, mixed> $args */
function swiftboard_render_text_field( array $args ): void {
	$settings = get_option( 'swiftboard_social_settings', array() );
	$key      = $args['label_for'];
	$type     = $args['type'] ?? 'text';
	$value    = $settings[ $key ] ?? '';
	$desc     = $args['description'] ?? '';

	printf(
		'<input type="%s" id="%s" name="swiftboard_social_settings[%s]" value="%s" class="regular-text" />',
		esc_attr( $type ),
		esc_attr( $key ),
		esc_attr( $key ),
		esc_attr( $value )
	);
	if ( $desc ) {
		printf( '<p class="description">%s</p>', esc_html( $desc ) );
	}
}

/**
 * @param array<string, string> $input
 * @return array<string, string>
 */
function swiftboard_sanitize_social_settings( array $input ): array {
	$clean  = array();
	$fields = array( 'google_client_id', 'github_client_id', 'github_client_secret', 'facebook_app_id', 'facebook_app_secret' );
	foreach ( $fields as $f ) {
		$clean[ $f ] = isset( $input[ $f ] ) ? sanitize_text_field( $input[ $f ] ) : '';
	}
	return $clean;
}

// ============================================================================
// 4. SETTINGS PAGE HTML
// ============================================================================
function swiftboard_social_login_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings     = get_option( 'swiftboard_social_settings', array() );
	$redirect_uri = home_url( '/wp-json/swiftboard/v1/auth/callback' );
	?>
	<div class="wrap">
		<h1>🔗 <?php esc_html_e( 'Connexion sociale', 'swiftboard' ); ?></h1>

		<div class="card" style="max-width:700px;padding:20px;margin:20px 0;">
			<h2>📋 <?php esc_html_e( 'URL de redirection OAuth', 'swiftboard' ); ?></h2>
			<p><?php esc_html_e( 'Copiez cette URL dans les paramètres de chaque fournisseur OAuth :', 'swiftboard' ); ?></p>
			<code style="display:block;padding:12px;background:#f0f0f1;border-radius:4px;font-size:14px;word-break:break-all;">
				<?php echo esc_html( $redirect_uri ); ?>
			</code>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'swiftboard_social' ); ?>

			<!-- GOOGLE -->
			<div class="card" style="max-width:700px;padding:20px;margin:20px 0;">
				<h2>🔵 Google</h2>
				<p>
					<strong><?php esc_html_e( 'Étapes :', 'swiftboard' ); ?></strong><br>
					1. <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console > Credentials</a><br>
					2. <?php esc_html_e( 'Créer un identifiant OAuth 2.0 (Type: Application Web)', 'swiftboard' ); ?><br>
					3. <?php esc_html_e( 'URI de redirection autorisée :', 'swiftboard' ); ?> <code><?php echo esc_html( $redirect_uri ); ?></code><br>
					4. <?php esc_html_e( 'Copier le Client ID ci-dessous', 'swiftboard' ); ?>
				</p>
				do_settings_fields( 'swiftboard-social-login', 'swiftboard_google' );
				?>
			</div>

			<!-- GITHUB -->
			<div class="card" style="max-width:700px;padding:20px;margin:20px 0;">
				<h2>⚫ GitHub</h2>
				<p>
					<strong><?php esc_html_e( 'Étapes :', 'swiftboard' ); ?></strong><br>
					1. <a href="https://github.com/settings/developers" target="_blank">GitHub > Settings > Developer settings > OAuth Apps</a><br>
					2. <?php esc_html_e( 'Créer une OAuth App', 'swiftboard' ); ?><br>
					3. <?php esc_html_e( 'Authorization callback URL :', 'swiftboard' ); ?> <code><?php echo esc_html( $redirect_uri ); ?></code><br>
					4. <?php esc_html_e( 'Copier Client ID et Client Secret ci-dessous', 'swiftboard' ); ?>
				</p>
				<?php
				do_settings_fields( 'swiftboard-social-login', 'swiftboard_github' );
				?>
			</div>

			<!-- FACEBOOK -->
			<div class="card" style="max-width:700px;padding:20px;margin:20px 0;">
				<h2>🔵 Facebook</h2>
				<p>
					<strong><?php esc_html_e( 'Étapes :', 'swiftboard' ); ?></strong><br>
					1. <a href="https://developers.facebook.com/apps/" target="_blank">Meta for Developers > Apps</a><br>
					2. <?php esc_html_e( 'Créer une application > Type: Consommateur', 'swiftboard' ); ?><br>
					3. <?php esc_html_e( 'Produit > Facebook Login > Paramètres > URI de redirection OAuth valide :', 'swiftboard' ); ?> <code><?php echo esc_html( $redirect_uri ); ?></code><br>
					4. <?php esc_html_e( 'Copier App ID et App Secret ci-dessous', 'swiftboard' ); ?>
				</p>
				<?php
				do_settings_fields( 'swiftboard-social-login', 'swiftboard_facebook' );
				?>
			</div>

			<?php submit_button( __( 'Enregistrer les clés API', 'swiftboard' ) ); ?>
		</form>

		<!-- STATUS -->
		<div class="card" style="max-width:700px;padding:20px;margin:20px 0;">
			<h2>✅ <?php esc_html_e( 'État des connexions', 'swiftboard' ); ?></h2>
			<table class="widefat" style="max-width:400px;">
				<tr>
					<td>🔵 Google</td>
					<td><?php echo ! empty( $settings['google_client_id'] ) ? '<span style="color:green">✅ Configuré</span>' : '<span style="color:#999">⚪ Non configuré</span>'; ?></td>
				</tr>
				<tr>
					<td>⚫ GitHub</td>
					<td><?php echo ( ! empty( $settings['github_client_id'] ) && ! empty( $settings['github_client_secret'] ) ) ? '<span style="color:green">✅ Configuré</span>' : '<span style="color:#999">⚪ Non configuré</span>'; ?></td>
				</tr>
				<tr>
					<td>🔵 Facebook</td>
					<td><?php echo ( ! empty( $settings['facebook_app_id'] ) && ! empty( $settings['facebook_app_secret'] ) ) ? '<span style="color:green">✅ Configuré</span>' : '<span style="color:#999">⚪ Non configuré</span>'; ?></td>
				</tr>
			</table>
		</div>
	</div>
	<?php
}
