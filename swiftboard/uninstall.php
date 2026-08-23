<?php
/**
 * SwiftBoard — Désinstallation (RGPD art. 17 : droit à l'effacement).
 *
 * Ce fichier n'est pas exécuté automatiquement lors de la suppression d'un thème.
 * Il conserve une routine de nettoyage explicite, appelable uniquement par un
 * mécanisme administrateur contrôlé ou une procédure de migration validée.
 * Il ne supprime PAS les posts (forums/topics/replies) — c'est bbPress qui
 * les gère, et l'admin peut vouloir garder le contenu.
 *
 * @package SwiftBoard
 */

// Ne jamais exécuter cette routine par accès HTTP ou changement de thème.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Supprimer les tables custom du thème.
$custom_tables = array(
	'swiftboard_votes',
	'swiftboard_followers',
	'swiftboard_notifications',
	'swiftboard_uploads',
	'swiftboard_reports',
	'swiftboard_audit_log',
);

foreach ( $custom_tables as $table_suffix ) {
	$table = $wpdb->prefix . $table_suffix;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Destruction explicite des tables SwiftBoard après confirmation administrateur.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// 2. Supprimer les options du thème.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Lecture contrôlée des options SwiftBoard à supprimer.
$options = $wpdb->get_col(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'swiftboard_%' OR option_name LIKE '_transient_swiftboard_%' OR option_name LIKE '_transient_sb_%' OR option_name LIKE '_transient_timeout_swiftboard_%' OR option_name LIKE '_transient_timeout_sb_%'"
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// 3. Supprimer les user metas du thème.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Suppression explicite des métadonnées SwiftBoard après confirmation.
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'swiftboard_%'"
);

// 4. Supprimer les post metas du thème.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Suppression explicite des métadonnées SwiftBoard après confirmation.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_swiftboard_%'"
);

// 5. Vider le cache de pages.
$cache_dir = WP_CONTENT_DIR . '/uploads/swiftboard-cache';
if ( is_dir( $cache_dir ) ) {
	// Récursif : supprimer tous les fichiers et dossiers.
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $cache_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Suppression d’un dossier cache privé après confirmation.
			rmdir( $file->getRealPath() );
		} else {
			wp_delete_file( $file->getRealPath() );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Suppression du répertoire cache privé après confirmation.
	rmdir( $cache_dir );
}
