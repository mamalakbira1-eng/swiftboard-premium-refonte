<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — badge admin "modération images en attente".
 *
 * CDC-CI-02 : extrait de admin-image-moderation.php pour être chargeable
 * hors contexte admin-only (PHPUnit front bootstrap, WP-CLI, etc.).
 * Le hook admin_menu ne s'exécute que dans l'admin : sans effet en front.
 *
 * @package SwiftBoard
 */
/**
 * Ajoute un compteur "en attente" sur le menu de modération images.
 *
 * @return void
 */
function swiftboard_moderation_badge() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb, $menu;
	if ( ! is_array( $menu ?? null ) ) {
		return;
	}
	$table = swiftboard_table( 'uploads' );
	// Table peut ne pas exister en début d'install / tests partiels.
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists !== $table ) {
		return;
	}
	$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );

	if ( $pending > 0 ) {
		foreach ( $menu as $key => $item ) {
			if ( ( $item[2] ?? '' ) === 'swiftboard-moderation' ) {
				$menu[ $key ][0] .= ' <span class="awaiting-mod count-' . $pending . '"><span class="pending-count">' . $pending . '</span></span>';
				break;
			}
		}
	}
}
add_action( 'admin_menu', 'swiftboard_moderation_badge', 999 );
