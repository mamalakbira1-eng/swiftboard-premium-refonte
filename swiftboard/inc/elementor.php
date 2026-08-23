<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard v2 - Module Compatibilité Elementor
 *
 * PATCH v2 : on NE force PLUS template_include sur les pages bbPress,
 * car cela cassait les templates custom bbPress et doublait les breadcrumbs.
 * On déclare juste le support + on bloque les Google Fonts.
 *
 * @package SwiftBoard
 * @since 2.0.0
 */
// ============================================================================
// 1. SUPPORT ELEMENTOR
// ============================================================================
/**
 * @return void
 */
function swiftboard_elementor_support() {
	add_theme_support( 'elementor' );
}
add_action( 'after_setup_theme', 'swiftboard_elementor_support' );

// ============================================================================
// 2. CATÉGORIE DE WIDGETS SWIFTBOARD (optionnel)
// ============================================================================
/**
 * swiftboard_elementor_widget_category().
 *
 * @param mixed $elements_manager À documenter.
 * @return void
 */
function swiftboard_elementor_widget_category( $elements_manager ) {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return;
	}
	$elements_manager->add_category(
		'swiftboard-forum',
		array(
			'title' => __( 'SwiftBoard Forum', 'swiftboard' ),
			'icon'  => 'fa fa-comments',
		)
	);
}
add_action( 'elementor/elements/categories_registered', 'swiftboard_elementor_widget_category' );

// ============================================================================
// 3. WIDGETS ÉLÉMENTOR OPTIONNELS
// ============================================================================
// v4.6 : dead ref à elementor-widgets.php supprimée (audit 06)
// Le fichier n'a jamais été créé, le hook faisait un file_exists inutile à chaque chargement
// Pour ajouter des widgets Elementor custom, créer le fichier + décommenter le code ci-dessous :
//
// function swiftboard_register_elementor_widgets($widgets_manager) {
// $widget_file = SWIFTBOARD_DIR . '/inc/elementor-widgets.php';
// if (file_exists($widget_file)) {
// require_once $widget_file;
// }
// }
// add_action('elementor/widgets/register', 'swiftboard_register_elementor_widgets');

// ============================================================================
// 4. DÉSACTIVER LES GOOGLE FONTS D'ELEMENTOR (performance)
// ============================================================================
/**
 * @return void
 */
function swiftboard_disable_elementor_google_fonts() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return;
	}
	add_filter(
		'elementor/fonts/groups',
		function ( $font_groups ) {
			unset( $font_groups['googlefonts'] );
			return $font_groups;
		}
	);
	// Empêcher le chargement des Google Fonts sur le front
	add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
}
add_action( 'init', 'swiftboard_disable_elementor_google_fonts' );

// ============================================================================
// 5. NE PAS FORCER TEMPLATE_INCLUDE SUR BBPRESS (patch v1)
// ----------------------------------------------------------------------------
// En v1, swiftboard_force_php_forum_template() forçait page-forum.php sur
// toutes les pages bbPress via template_include:99 — c'était un bug qui
// cassait les templates custom bbPress (content-single-topic.php, etc.) et
// doublait les breadcrumbs.
//
// En v2, on laisse bbPress utiliser sa propre hiérarchie de templates,
// qui retombera naturellement sur nos fichiers dans /bbpress/ si présents.
// ============================================================================
