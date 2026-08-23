<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Compatibilité One Click Demo Import (OCDI)
 *
 * 9 démos disponibles : 3 thèmes × 3 langues
 * - Communauté (Reddit Blue) : FR / EN / AR
 * - Gaming (Discord Dark) : FR / EN / AR
 * - SaaS Support (Minimal Light) : FR / EN / AR
 *
 * @package SwiftBoard
 * @since 6.0.0
 */
add_filter(
	'ocdi/import_files',
	function () {
		$dir    = defined( 'SWIFTBOARD_PATH' ) ? SWIFTBOARD_PATH : get_template_directory();
		$uri    = get_template_directory_uri();
		$notice = __( 'Import en cours. Veuillez patienter ~30 secondes.', 'swiftboard' );

		// Image preview : on utilise screenshot.png pour toutes
		$preview = $uri . '/screenshot.png';

		$imports = array(
			// === COMMUNAUTÉ (Reddit Blue) ===
			array(
				'import_file_name'         => '🇫🇷 Communauté Santé (Français)',
				'categories'               => array( 'Communauté' ),
				'local_import_file'        => $dir . '/demo-data/demo-communaute/content-fr.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
			array(
				'import_file_name'         => '🇬🇧 Community Health (English)',
				'categories'               => array( 'Communauté' ),
				'local_import_file'        => $dir . '/demo-data/demo-communaute/content-en.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
			array(
				'import_file_name'         => '🇲🇦 المجتمع الصحي (العربية)',
				'categories'               => array( 'Communauté' ),
				'local_import_file'        => $dir . '/demo-data/demo-communaute/content-ar.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
			// === GAMING (Discord Dark) ===
			array(
				'import_file_name'         => '🎮 Gaming (Français)',
				'categories'               => array( 'Gaming' ),
				'local_import_file'        => $dir . '/demo-data/demo-gaming/content-fr.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
			array(
				'import_file_name'         => '🎮 Gaming (English)',
				'categories'               => array( 'Gaming' ),
				'local_import_file'        => $dir . '/demo-data/demo-gaming/content-en.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
			array(
				'import_file_name'         => '🎮 الألعاب (العربية)',
				'categories'               => array( 'Gaming' ),
				'local_import_file'        => $dir . '/demo-data/demo-gaming/content-ar.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
			// === SaaS SUPPORT (Minimal Light) ===
			array(
				'import_file_name'         => '💼 SaaS Support (Français)',
				'categories'               => array( 'SaaS' ),
				'local_import_file'        => $dir . '/demo-data/demo-saas/content-fr.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
			array(
				'import_file_name'         => '💼 SaaS Support (English)',
				'categories'               => array( 'SaaS' ),
				'local_import_file'        => $dir . '/demo-data/demo-saas/content-en.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
			array(
				'import_file_name'         => '💼 دعم SaaS (العربية)',
				'categories'               => array( 'SaaS' ),
				'local_import_file'        => $dir . '/demo-data/demo-saas/content-ar.xml',
				'import_preview_image_url' => $preview,
				'import_notice'            => $notice,
				'preview_url'              => home_url( '/' ),
			),
		);

		// Ne jamais afficher une démo dont le XML n’est pas présent dans le
		// package installé. Les définitions Gaming/SaaS restent conservées et
		// réapparaîtront automatiquement dès que leurs assets seront livrés.
		return array_values(
			array_filter(
				$imports,
				static function ( $item ) {
					return isset( $item['local_import_file'] ) && is_readable( $item['local_import_file'] );
				}
			)
		);
	}
);

add_filter(
	'ocdi/plugin_page_setup',
	function ( $args ) {
		// Personnaliser le titre de la page
		$args['page_title'] = __( 'Import de démos SwiftBoard', 'swiftboard' );
		$args['menu_title'] = __( '🎨 Démos', 'swiftboard' );
		return $args;
	}
);

add_action(
	'ocdi/after_import',
	function ( $selected ) {
		// 1. Initialisation tables MariaDB
		if ( function_exists( 'swiftboard_create_votes_table' ) ) {
			swiftboard_create_votes_table();
		}
		if ( function_exists( 'swiftboard_create_notifications_table' ) ) {
			swiftboard_create_notifications_table();
		}

		// 2. Assigner la page Politique de Confidentialité
		$privacy = get_page_by_path( 'politique-de-confidentialite' );
		if ( $privacy ) {
			update_option( 'wp_page_for_privacy_policy', $privacy->ID );
		}

		// 3. Détection de la langue de la démo importée
		// Le nom du fichier ou le nom de la démo contient "ar" ou "العربية"
		$imported_file = '';
		if ( is_array( $selected ) && isset( $selected['local_import_file'] ) ) {
			$imported_file = $selected['local_import_file'];
		} elseif ( is_array( $selected ) && isset( $selected[0]['local_import_file'] ) ) {
			$imported_file = $selected[0]['local_import_file'];
		}

		// Si le fichier contient "ar" dans son nom → passer en arabe + RTL
		if ( strpos( $imported_file, 'content-ar.xml' ) !== false ) {
			update_option( 'WPLANG', 'ar' );
			update_option( 'swiftboard_force_rtl', '1' );
		} else {
			// Démo FR ou EN → s'assurer qu'on n'est pas en RTL forcé
			delete_option( 'swiftboard_force_rtl' );
		}

		// 4. Assigner les avatars ninja + grades aux utilisateurs importés
		// Les XML OCDI créent les utilisateurs mais ne set pas les avatars/grades.
		// On lit le fichier membres-*.csv correspondant et on applique.
		$demo_dir = dirname( $imported_file );
		$lang_code = 'fr';
		if ( strpos( $imported_file, 'content-ar.xml' ) !== false ) {
			$lang_code = 'ar';
		} elseif ( strpos( $imported_file, 'content-en.xml' ) !== false ) {
			$lang_code = 'en';
		}

		$membres_csv = $demo_dir . '/membres-' . $lang_code . '.csv';
		if ( file_exists( $membres_csv ) ) {
			swiftboard_ocdi_apply_membres( $membres_csv );
		}

		// 5. Purger cache disques & rewrite rules
		if ( function_exists( 'swiftboard_page_cache_flush_all' ) ) {
			swiftboard_page_cache_flush_all();
		}
		flush_rewrite_rules();

		// 6. Purger les caches hot topics
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sb_hot_%' OR option_name LIKE '_transient_timeout_sb_hot_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sb_reputation_%'" );
	}
);

/**
 * Applique les avatars + grades depuis un fichier membres CSV
 * après un import OCDI.
 *
 * @param string $csv_path Chemin du fichier CSV.
 * @return void
 */
function swiftboard_ocdi_apply_membres( $csv_path ) {
	$content = file_get_contents( $csv_path );
	if ( ! $content ) {
		return;
	}
	// BOM
	if ( substr( $content, 0, 3 ) === "\xEF\xBB\xBF" ) {
		$content = substr( $content, 3 );
	}

	$lines = str_getcsv( $content, "\n" );
	$headers = null;

	foreach ( $lines as $line ) {
		$row = str_getcsv( $line );
		if ( ! $headers ) {
			$headers = array_map( 'strtolower', array_map( 'trim', $row ) );
			continue;
		}
		if ( count( $row ) < count( $headers ) ) {
			continue;
		}
		$data = array_combine( $headers, array_pad( $row, count( $headers ), '' ) );

		$login   = trim( $data['identifiant'] ?? '' );
		$grade   = trim( $data['grade'] ?? 'rookie' );
		$avatar  = trim( $data['avatar'] ?? '' );
		$karma   = (int) ( $data['karma'] ?? 0 );

		if ( ! $login ) {
			continue;
		}

		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			continue;
		}

		// Grade
		$grades_valides = array( 'rookie', 'member', 'pro', 'moderator', 'vip' );
		if ( ! in_array( $grade, $grades_valides, true ) ) {
			$grade = 'rookie';
		}
		update_user_meta( $user->ID, 'swiftboard_grade', $grade );

		// Avatar
		if ( is_numeric( $avatar ) ) {
			$avatar_num = (int) $avatar;
			if ( $avatar_num >= 1 && $avatar_num <= 15 ) {
				update_user_meta( $user->ID, 'swiftboard_avatar_id', $avatar_num );
				update_user_meta( $user->ID, 'swiftboard_avatar', $avatar_num );
			}
		}

		// Karma bonus
		if ( $karma > 0 ) {
			update_user_meta( $user->ID, 'swiftboard_karma_bonus', $karma );
		}

		// Invalider le cache grade
		if ( function_exists( 'swiftboard_invalidate_grade_cache' ) ) {
			swiftboard_invalidate_grade_cache( $user->ID );
		}
	}
}
