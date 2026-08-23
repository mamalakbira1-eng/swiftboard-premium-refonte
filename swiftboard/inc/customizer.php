<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Intégration au WordPress Customizer (Envato Requirement)
 *
 * Supporte :
 *   - Logo & Titre (Natif WP Customizer)
 *   - Palette de couleurs (primaire, secondaire, mode clair/sombre/auto)
 *   - Typographie (police système ou Google Fonts)
 *   - Disposition (card vs compact)
 *   - Activation/Désactivation de modules en 1 clic (votes, onboarding, cache, RUM)
 *
 * @package SwiftBoard
 * @since 6.0.0
 */
add_action( 'customize_register', 'swiftboard_customize_register' );
function swiftboard_customize_register( \WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'swiftboard_options',
		array(
			'title'       => __( 'Réglages SwiftBoard (Envato)', 'swiftboard' ),
			'priority'    => 30,
			'description' => __( 'Personnalisez les couleurs, polices, dispositions et modules de votre forum Reddit-like.', 'swiftboard' ),
		)
	);

	// 1. Couleur primaire
	$wp_customize->add_setting(
		'swiftboard_primary_color',
		array(
			'default'           => '#0073aa',
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'postMessage',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'swiftboard_primary_color',
			array(
				'label'   => __( 'Couleur Primaire (Boutons & Liens)', 'swiftboard' ),
				'section' => 'swiftboard_options',
			)
		)
	);

	// 2. Couleur secondaire
	$wp_customize->add_setting(
		'swiftboard_secondary_color',
		array(
			'default'           => '#1877f2',
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'postMessage',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'swiftboard_secondary_color',
			array(
				'label'   => __( 'Couleur Secondaire (Badges & Accents)', 'swiftboard' ),
				'section' => 'swiftboard_options',
			)
		)
	);

	// 3. Typographie (Font Family)
	$wp_customize->add_setting(
		'swiftboard_font_family',
		array(
			'default'           => 'system',
			'sanitize_callback' => function ( $val ) {
				return in_array( $val, array( 'system', 'inter', 'roboto', 'poppins' ), true ) ? $val : 'system';
			},
		)
	);
	$wp_customize->add_control(
		'swiftboard_font_family',
		array(
			'label'   => __( 'Police d\'écriture du Forum', 'swiftboard' ),
			'section' => 'swiftboard_options',
			'type'    => 'select',
			'choices' => array(
				'system'  => __( 'Système par défaut (Ultra rapide - 0 requête)', 'swiftboard' ),
				'inter'   => __( 'Inter (Moderne sans-serif)', 'swiftboard' ),
				'roboto'  => __( 'Roboto (Classique Clean)', 'swiftboard' ),
				'poppins' => __( 'Poppins (Géométrique)', 'swiftboard' ),
			),
		)
	);

	// 4. Vue par défaut
	$wp_customize->add_setting(
		'swiftboard_default_view',
		array(
			'default'           => 'card',
			'sanitize_callback' => function ( $val ) {
				return in_array( $val, array( 'card', 'compact' ), true ) ? $val : 'card';
			},
		)
	);
	$wp_customize->add_control(
		'swiftboard_default_view',
		array(
			'label'   => __( 'Disposition Forum par Défaut', 'swiftboard' ),
			'section' => 'swiftboard_options',
			'type'    => 'select',
			'choices' => array(
				'card'    => __( 'Vue Cartes (Reddit-style)', 'swiftboard' ),
				'compact' => __( 'Vue Compacte (Dense)', 'swiftboard' ),
			),
		)
	);

	// 4b. Presets Design 10/10 - 3 skins
	$wp_customize->add_setting(
		'swiftboard_preset',
		array(
			'default'           => 'reddit-blue',
			'sanitize_callback' => function ( $val ) {
				return in_array( $val, array( 'reddit-blue', 'discord-dark', 'minimal-light' ), true ) ? $val : 'reddit-blue';
			},
		)
	);
	$wp_customize->add_control(
		'swiftboard_preset',
		array(
			'label'   => __( 'Preset Design (10/10)', 'swiftboard' ),
			'section' => 'swiftboard_options',
			'type'    => 'select',
			'choices' => array(
				'reddit-blue'   => __( 'Reddit Blue (Default)', 'swiftboard' ),
				'discord-dark'  => __( 'Discord Dark', 'swiftboard' ),
				'minimal-light' => __( 'Minimal Light', 'swiftboard' ),
			),
		)
	);

	// 5. Modules Toggles
	$toggles = array(
		'swiftboard_enable_votes'      => array(
			'default' => '1',
			'label'   => __( 'Activer le système de Votes & Karma', 'swiftboard' ),
		),
		'swiftboard_enable_onboarding' => array(
			'default' => '1',
			'label'   => __( 'Activer la modale d\'Onboarding Reddit (3 étapes)', 'swiftboard' ),
		),
		'swiftboard_enable_page_cache' => array(
			'default' => '1',
			'label'   => __( 'Activer le cache de pages anonyme sur disque', 'swiftboard' ),
		),
		'swiftboard_enable_rum'        => array(
			'default' => '1',
			'label'   => __( 'Activer la collecte RUM anonymisée (Core Web Vitals)', 'swiftboard' ),
		),
	);

	foreach ( $toggles as $id => $info ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $info['default'],
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $info['label'],
				'section' => 'swiftboard_options',
				'type'    => 'checkbox',
			)
		);
	}
}

/**
 * Génère le CSS inline customizer pour la couleur primaire, secondaire et typographie.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$primary   = get_theme_mod( 'swiftboard_primary_color', '#0073aa' );
		$secondary = get_theme_mod( 'swiftboard_secondary_color', '#1877f2' );
		$font      = get_theme_mod( 'swiftboard_font_family', 'system' );

		$font_stack = "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif";
		if ( $font === 'inter' ) {
			$font_stack = "'Inter', " . $font_stack;
		}
		if ( $font === 'roboto' ) {
			$font_stack = "'Roboto', " . $font_stack;
		}
		if ( $font === 'poppins' ) {
			$font_stack = "'Poppins', " . $font_stack;
		}

		// Les tokens reellement consommes par le theme sont --color-*. Les alias
		// --sb-* sont conserves pour la retrocompatibilite des surcharges client.
		$css  = ":root { --wp--preset--color--vivid-cyan-blue: {$primary} !important; "
			. "--sb-primary: {$primary}; --sb-secondary: {$secondary}; --sb-font: {$font_stack}; "
			. "--color-primary: {$primary}; --color-accent: {$primary}; "
			. "--color-primary-text: {$primary}; --color-upvote: {$primary}; "
			. "--color-secondary: {$secondary}; } ";
		$css .= 'body { font-family: var(--sb-font); }';
		wp_add_inline_style( 'swiftboard-main', $css );
	},
	100
);

// === LOT 11 : Couleur des avatars par défaut (fallback Reddit-like) ===
add_action(
	'customize_register',
	function ( $wp_customize ) {
		$wp_customize->add_setting(
			'swiftboard_avatar_fallback_color',
			array(
				'default'           => '#006cbd',
				'sanitize_callback' => 'sanitize_hex_color',
				'transport'         => 'postMessage',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'swiftboard_avatar_fallback_color',
				array(
					'label'    => __( 'Couleur des avatars par défaut', 'swiftboard' ),
					'section'  => 'swiftboard_options',
					'priority' => 25,
				)
			)
		);
	}
);

// Émettre la variable CSS pour le fallback avatar
add_action(
	'wp_head',
	function () {
		$color = get_theme_mod( 'swiftboard_avatar_fallback_color', '#006cbd' );
		echo '<style>:root { --sb-avatar-fallback-bg: ' . esc_attr( $color ) . '; }</style>';
	},
	5
);
