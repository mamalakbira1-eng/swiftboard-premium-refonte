<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard v2 - Module Admin (page d'options du thème)
 *
 * Nouveauté v2 : permet de personnaliser les couleurs, le logo, etc.
 * sans toucher au CSS. Accessible via Apparence → SwiftBoard.
 *
 * @package SwiftBoard
 * @since 2.0.0
 */
// ============================================================================
// 1. MENU ADMIN
// ============================================================================
/**
 * @return void
 */
function swiftboard_admin_menu() {
	add_theme_page(
		__( 'SwiftBoard — Réglages', 'swiftboard' ),
		__( 'SwiftBoard', 'swiftboard' ),
		'manage_options',
		'swiftboard-options',
		'swiftboard_options_page'
	);
}
add_action( 'admin_menu', 'swiftboard_admin_menu' );

// ============================================================================
// 2. PAGE D'OPTIONS
// ============================================================================
/**
 * @return void
 */
function swiftboard_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Permissions insuffisantes.', 'swiftboard' ) );
	}

	// Sauvegarde
	if ( isset( $_POST['swiftboard_save'] ) && check_admin_referer( 'swiftboard_options', 'swiftboard_nonce' ) ) {
		$opts = array(
			'color_primary'   => sanitize_hex_color( wp_unslash( $_POST['color_primary'] ?? '#006cbd' ) ),
			'color_accent'    => sanitize_hex_color( wp_unslash( $_POST['color_accent'] ?? '#006cbd' ) ),
			'default_theme'   => in_array( sanitize_text_field( wp_unslash( $_POST['default_theme'] ?? 'auto' ) ), array( 'auto', 'light', 'dark' ), true ) ? sanitize_text_field( wp_unslash( $_POST['default_theme'] ) ) : 'auto',
			'show_vote_count' => isset( $_POST['show_vote_count'] ) ? 1 : 0,
			'footer_text'     => sanitize_text_field( wp_unslash( $_POST['footer_text'] ?? '' ) ),
		);
		update_option( 'swiftboard_options', $opts );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Réglages enregistrés.', 'swiftboard' ) . '</p></div>';
	}

	$opts = wp_parse_args(
		get_option( 'swiftboard_options', array() ),
		array(
			'color_primary'   => '#006cbd',
			'color_accent'    => '#006cbd',
			'default_theme'   => 'auto',
			'show_vote_count' => 1,
			'footer_text'     => '',
		)
	);
	?>
	<div class="wrap">
		<h1>⚡ SwiftBoard — Réglages</h1>
		<form method="post" action="">
			<?php wp_nonce_field( 'swiftboard_options', 'swiftboard_nonce' ); ?>

			<h2 class="title"><?php esc_html_e( 'Apparence', 'swiftboard' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="color_primary"><?php esc_html_e( 'Couleur principale', 'swiftboard' ); ?></label></th>
					<td>
						<input type="color" name="color_primary" id="color_primary" value="<?php echo esc_attr( $opts['color_primary'] ); ?>">
						<p class="description"><?php esc_html_e( 'Couleur des boutons, liens actifs, upvotes (défaut : rouge Reddit).', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="color_accent"><?php esc_html_e( 'Couleur accent', 'swiftboard' ); ?></label></th>
					<td>
						<input type="color" name="color_accent" id="color_accent" value="<?php echo esc_attr( $opts['color_accent'] ); ?>">
						<p class="description"><?php esc_html_e( 'Liens, hover (défaut : bleu Reddit).', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="default_theme"><?php esc_html_e( 'Thème par défaut', 'swiftboard' ); ?></label></th>
					<td>
						<select name="default_theme" id="default_theme">
							<option value="auto"  <?php selected( $opts['default_theme'], 'auto' ); ?>><?php esc_html_e( 'Auto (selon préférence système)', 'swiftboard' ); ?></option>
							<option value="light" <?php selected( $opts['default_theme'], 'light' ); ?>><?php esc_html_e( 'Clair', 'swiftboard' ); ?></option>
							<option value="dark"  <?php selected( $opts['default_theme'], 'dark' ); ?>><?php esc_html_e( 'Sombre', 'swiftboard' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Forum', 'swiftboard' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Afficher le compteur de votes', 'swiftboard' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="show_vote_count" value="1" <?php checked( $opts['show_vote_count'], 1 ); ?>>
							<?php esc_html_e( 'Activer les boutons upvote/downvote sur les sujets', 'swiftboard' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Footer', 'swiftboard' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="footer_text"><?php esc_html_e( 'Texte personnalisé du footer', 'swiftboard' ); ?></label></th>
					<td>
						<input type="text" name="footer_text" id="footer_text" class="regular-text" value="<?php echo esc_attr( $opts['footer_text'] ); ?>">
						<p class="description"><?php esc_html_e( 'Affiché à côté du copyright. Laisser vide pour masquer.', 'swiftboard' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Enregistrer', 'swiftboard' ), 'primary', 'swiftboard_save' ); ?>
		</form>

		<h2><?php esc_html_e( 'À propos', 'swiftboard' ); ?></h2>
		<p><?php esc_html_e( 'SwiftBoard v2.0.0 — Thème WordPress forum ultra-performant, design Reddit-inspired, SEO + LLM-ready.', 'swiftboard' ); ?></p>
	</div>
	<?php
}

// ============================================================================
// 3. INJECTER LES COULEURS CUSTOM DANS LE <head>
// ============================================================================
/**
 * @return void
 */
function swiftboard_custom_colors() {
	$opts    = get_option( 'swiftboard_options', array() );
	$primary = isset( $opts['color_primary'] ) ? $opts['color_primary'] : '#006cbd';
	$accent  = isset( $opts['color_accent'] ) ? $opts['color_accent'] : '#006cbd';

	$needs_override = ( $primary !== '#006cbd' || $accent !== '#006cbd' );
	if ( ! $needs_override) return;

	// Calculer les variants
	$primary_hover = swiftboard_adjust_color( $primary, -15 );
	$primary_light = swiftboard_adjust_color( $primary, 90 );
	$accent_hover  = swiftboard_adjust_color( $accent, -10 );
	$accent_light  = swiftboard_adjust_color( $accent, 90 );

	echo '<style id="swiftboard-custom-colors">
:root {
    --color-primary: ' . esc_attr( $primary ) . ';
    --color-primary-hover: ' . esc_attr( $primary_hover ) . ';
    --color-primary-light: ' . esc_attr( $primary_light ) . ';
    --color-accent: ' . esc_attr( $accent ) . ';
    --color-accent-hover: ' . esc_attr( $accent_hover ) . ';
    --color-accent-light: ' . esc_attr( $accent_light ) . ';
    --color-upvote: ' . esc_attr( $primary ) . ';
    --color-link: ' . esc_attr( $accent ) . ';
    --color-link-hover: ' . esc_attr( $accent_hover ) . ';
}
</style>' . "\n";
}
add_action( 'wp_head', 'swiftboard_custom_colors', 100 );

// Helper : éclaircir/foncer une couleur hex
/**
 * swiftboard_adjust_color().
 *
 * @param string $hex     Couleur au format hexadécimal.
 * @param int    $percent Pourcentage appliqué.
 * @return mixed
 */
function swiftboard_adjust_color( $hex, $percent ) {
	$hex = ltrim( $hex, '#' );
	if (strlen( $hex ) !== 6) return $hex;
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	if ( $percent >= 0 ) {
		$r = (int) min( 255, $r + ( 255 - $r ) * $percent / 100 );
		$g = (int) min( 255, $g + ( 255 - $g ) * $percent / 100 );
		$b = (int) min( 255, $b + ( 255 - $b ) * $percent / 100 );
	} else {
		$r = (int) max( 0, $r * ( 1 + $percent / 100 ) );
		$g = (int) max( 0, $g * ( 1 + $percent / 100 ) );
		$b = (int) max( 0, $b * ( 1 + $percent / 100 ) );
	}
	return '#' . str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT )
				. str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT )
				. str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );
}

