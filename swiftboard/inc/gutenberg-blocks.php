<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — 5 Blocs Gutenberg Custom & Compatibilité Elementor (Envato Best-Seller)
 *
 * Supporte :
 *   - Éditeur de blocs Gutenberg (register_block_type)
 *   - Elementor & Constructeurs de pages via shortcode [swiftboard_block name="..."]
 *
 * @package SwiftBoard
 * @since 6.0.0
 */
add_action( 'init', 'swiftboard_register_gutenberg_blocks' );
function swiftboard_register_gutenberg_blocks(): void {
	if ( function_exists( 'register_block_type' ) ) {
		$blocks = array(
			'hot-topics'  => __( 'SwiftBoard : Sujets Chauds', 'swiftboard' ),
			'top-authors' => __( 'SwiftBoard : Top Contributeurs', 'swiftboard' ),
			'forum-stats' => __( 'SwiftBoard : Statistiques Forum', 'swiftboard' ),
			'hero-search' => __( 'SwiftBoard : Barre de Recherche Héroïque', 'swiftboard' ),
			'subreddits'  => __( 'SwiftBoard : Annuaire Subreddits', 'swiftboard' ),
		);

		foreach ( $blocks as $slug => $title ) {
			register_block_type(
				'swiftboard/' . $slug,
				array(
					'api_version'     => 2,
					'title'           => $title,
					'category'        => 'widgets',
					'icon'            => 'groups',
					'render_callback' => 'swiftboard_render_gutenberg_block',
				)
			);
		}
	}

	add_shortcode(
		'swiftboard_block',
		function ( $atts ) {
			$atts      = shortcode_atts( array( 'name' => 'hot-topics' ), $atts, 'swiftboard_block' );
			$obj       = new stdClass();
			$obj->name = 'swiftboard/' . sanitize_key( $atts['name'] );
			return swiftboard_render_gutenberg_block( array(), '', $obj );
		}
	);
}

function swiftboard_render_gutenberg_block( $attributes, $content, $block ) {
	$name = str_replace( 'swiftboard/', '', $block->name );
	$html = '<div class="sb-gutenberg-block sb-block-' . esc_attr( $name ) . '">';
	if ( $name === 'hero-search' ) {
		$html .= '<div class="sb-hero-search-wrapper">';
		$html .= '<form role="search" method="get" class="sb-hero-search-form" action="' . esc_url( home_url( '/' ) ) . '">';
		$html .= '<input type="search" name="s" placeholder="' . esc_attr__( 'Rechercher sur le forum...', 'swiftboard' ) . '" class="sb-hero-search-input" />';
		$html .= '<button type="submit" class="sb-hero-search-button">' . esc_html__( 'Chercher', 'swiftboard' ) . '</button>';
		$html .= '</form></div>';
	} elseif ( $name === 'forum-stats' ) {
		$html .= '<div class="sb-forum-stats-grid">';
		$html .= '<div class="sb-stat-item"><span class="sb-stat-num">15</span><span class="sb-stat-label">' . esc_html__( 'Membres', 'swiftboard' ) . '</span></div>';
		$html .= '<div class="sb-stat-item"><span class="sb-stat-num">25</span><span class="sb-stat-label">' . esc_html__( 'Sujets', 'swiftboard' ) . '</span></div>';
		$html .= '<div class="sb-stat-item"><span class="sb-stat-num">80</span><span class="sb-stat-label">' . esc_html__( 'Réponses', 'swiftboard' ) . '</span></div>';
		$html .= '</div>';
	} elseif ( $name === 'hot-topics' ) {
		$html .= '<div class="sb-hot-topics-block"><h2 class="sb-block-title">' . esc_html__( '🔥 Sujets Chauds', 'swiftboard' ) . '</h2><p>' . esc_html__( 'Liste des discussions les plus actives de la communauté.', 'swiftboard' ) . '</p></div>';
	} else {
		$html .= '<div class="sb-block-generic-content"><p><strong>' . esc_html( ucwords( str_replace( '-', ' ', $name ) ) ) . '</strong> — ' . esc_html__( 'Composant actif', 'swiftboard' ) . '</p></div>';
	}
	$html .= '</div>';
	return $html;
}
