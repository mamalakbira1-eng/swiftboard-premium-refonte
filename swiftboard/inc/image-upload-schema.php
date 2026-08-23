<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Schema de la table des envois d'images.
 *
 * EXI-ARCH-02 : extrait de inc/image-upload.php pour passer sous le seuil de
 * 500 lignes. La creation de table est appelee depuis plusieurs contextes
 * (activation du theme, premier envoi, suite de tests) : l'isoler la rend
 * trouvable au premier coup d'oeil.
 *
 * dbDelta() est idempotent : l'appeler plusieurs fois est sans effet de bord.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 4. FONCTIONS DE CONVERSION AVIF
/**
 * @return void
 */
function swiftboard_create_uploads_table() {
	global $wpdb;
	$table           = swiftboard_table( 'uploads' );
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        post_id BIGINT(20) UNSIGNED DEFAULT NULL,
        filename VARCHAR(255) NOT NULL,
        filepath TEXT NOT NULL,
        image_url TEXT NOT NULL,
        mime_type VARCHAR(50) NOT NULL,
        file_size BIGINT(20) UNSIGNED DEFAULT 0,
        original_size BIGINT(20) UNSIGNED DEFAULT 0,
        file_hash VARCHAR(32) DEFAULT NULL,
        width INT UNSIGNED DEFAULT 0,
        height INT UNSIGNED DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        moderated_by BIGINT(20) UNSIGNED DEFAULT NULL,
        moderated_at DATETIME DEFAULT NULL,
        moderator_note TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY post_id (post_id),
        KEY file_hash (file_hash)
    ) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
// EXI-SEC-05 : register_activation_hook() est une API PLUGIN, sans effet dans
// un theme (et le chemin pointait vers inc/style.css, inexistant). La table
// wp_swiftboard_uploads pouvait donc ne jamais etre creee.
add_action( 'after_switch_theme', 'swiftboard_create_uploads_table' );

// Créer la table si absente — ADMIN ONLY (évite SHOW TABLES sur chaque hit front).
// La création nominale reste after_switch_theme (ci-dessus).
add_action(
	'admin_init',
	function () {
		global $wpdb;
		$table = swiftboard_table( 'uploads' );
		if ( get_transient( 'swiftboard_uploads_table_ok' ) ) {
			return;
		}
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			set_transient( 'swiftboard_uploads_table_ok', 1, DAY_IN_SECONDS );
			return;
		}
		swiftboard_create_uploads_table();
		set_transient( 'swiftboard_uploads_table_ok', 1, DAY_IN_SECONDS );
	}
);

// ============================================================================
/**
 * swiftboard_log_upload_action().
 *
 * @param int    $image_id Identifiant de l'image.
 * @param int    $user_id  Identifiant de l'utilisateur.
 * @param mixed  $action   À documenter.
 * @param string $note     À documenter. Optionnel.
 * @return void
 */
function swiftboard_log_upload_action( $image_id, $user_id, $action, $note = '' ) {
	$log   = get_option( 'swiftboard_upload_audit_log', array() );
	$log[] = array(
		'image_id'  => (int) $image_id,
		'user_id'   => (int) $user_id,
		'action'    => $action, // uploaded, approved, rejected
		'note'      => $note,
		'timestamp' => current_time( 'mysql' ),
		'ip'        => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown',
	);
	// Garder seulement les 500 dernières entrées
	if ( count( $log ) > 500 ) {
		$log = array_slice( $log, -500 );
	}
	update_option( 'swiftboard_upload_audit_log', $log );
}
