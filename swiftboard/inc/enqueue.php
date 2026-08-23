<?php
/**
 * SwiftBoard — Script & Style Enqueuing
 * @package SwiftBoard
 * @since 7.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 4. ENQUEUE SCRIPTS & STYLES
// ============================================================================
/**
 * Precharge le CSS critique (EXI-PERF-01).
 *
 * Le navigateur ne decouvre main.css qu'apres avoir parse le <head>. Le
 * preload le lui signale des le premier octet, ce qui supprime un
 * aller-retour reseau sur le chemin critique du rendu.
 *
 * Emis en priorite 1 pour passer AVANT les <link> generes par wp_head().
 *
 * @return void
 */
function swiftboard_preload_critical_assets() {
	$critical = array(
		SWIFTBOARD_ASSETS . '/css/main.css?ver=' . SWIFTBOARD_VERSION,
		// La couche Reddit porte la composition de la page : sans preload elle
		// arrive apres le premier rendu et provoque un decalage visible.
		SWIFTBOARD_ASSETS . '/css/reddit-refonte.css?ver=' . SWIFTBOARD_VERSION,
	);
	foreach ( $critical as $css ) {
		printf(
			'<link rel="preload" href="%s" as="style" fetchpriority="high">' . "\n",
			esc_url( $css )
		);
	}
}
add_action( 'wp_head', 'swiftboard_preload_critical_assets', 1 );

/**
 * @return void
 */
function swiftboard_enqueue_assets() {
	// CSS principal
	wp_enqueue_style(
		'swiftboard-main',
		SWIFTBOARD_ASSETS . '/css/main.css',
		array(),
		SWIFTBOARD_VERSION
	);

	// Couche UI Reddit : composition, cartes, pilules de vote et responsive.
	// Chargée APRES main.css dont elle depend pour les tokens --color-*.
	// Elle ne masque aucun contenu : le mobile reagence, il ne cache pas.
	wp_enqueue_style(
		'swiftboard-reddit-refonte',
		SWIFTBOARD_ASSETS . '/css/reddit-refonte.css',
		array( 'swiftboard-main' ),
		SWIFTBOARD_VERSION
	);

	// RTL: CSS logical properties in main.css handle direction automatically.
	// No rtl.css needed — WordPress adds dir="rtl" to <html> for Arabic.
	// [dir="rtl"] rules at end of main.css handle flex-direction overrides.
	// CSS forum (uniquement sur les pages bbPress)
	if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		wp_enqueue_style(
			'swiftboard-bbpress',
			SWIFTBOARD_ASSETS . '/css/forum.css',
			array( 'swiftboard-main' ),
			SWIFTBOARD_VERSION
		);
		// CSS bbPress 100% coverage (toutes les classes complémentaires)
		wp_enqueue_style(
			'swiftboard-bbpress-complete',
			SWIFTBOARD_ASSETS . '/css/bbpress-complete.css',
			array( 'swiftboard-main', 'swiftboard-bbpress' ),
			SWIFTBOARD_VERSION
		);
	}

	// CSS BuddyPress custom (harmonisation Reddit-like de Members, Activity, Groups)
	if ( function_exists( 'buddypress' ) || class_exists( 'BuddyPress' ) || ( function_exists( 'is_buddypress' ) && is_buddypress() ) ) {
		wp_enqueue_style(
			'swiftboard-buddypress',
			SWIFTBOARD_ASSETS . '/css/buddypress.css',
			array( 'swiftboard-main' ),
			SWIFTBOARD_VERSION
		);
	}

	if ( ! is_user_logged_in() ) {
		wp_enqueue_style(
			'swiftboard-onboarding',
			SWIFTBOARD_ASSETS . '/css/onboarding.css',
			array( 'swiftboard-main' ),
			SWIFTBOARD_VERSION
		);
		wp_enqueue_script(
			'swiftboard-onboarding',
			SWIFTBOARD_ASSETS . '/js/onboarding.js',
			array(),
			SWIFTBOARD_VERSION,
			true
		);

			// OAuth public IDs are transported as inert data attributes, never as executable JS.
			$social_settings = get_option( 'swiftboard_social_settings', array() );
			add_action(
				'wp_footer',
				static function () use ( $social_settings ) {
					printf(
						'<div id="swiftboard-oauth-config" hidden data-google-client-id="%s" data-github-client-id="%s" data-facebook-app-id="%s"></div>',
						esc_attr( (string) ( $social_settings['google_client_id'] ?? '' ) ),
						esc_attr( (string) ( $social_settings['github_client_id'] ?? '' ) ),
						esc_attr( (string) ( $social_settings['facebook_app_id'] ?? '' ) )
					);
				},
				4
			);
        }

        // Couche premium des composants — chargée en dernier pour limiter les
        // régressions de cascade tout en réutilisant les contrats .sb-*.
        wp_enqueue_style(
            'swiftboard-premium-ui',
            SWIFTBOARD_ASSETS . '/css/premium-ui.css',
            array( 'swiftboard-reddit-refonte' ),
            SWIFTBOARD_VERSION
        );

        // JS principal — defer, footer, zero dependance.
	// Le handle est deja DECLARE par swiftboard_enregistrer_scripts_tot()
	// (priorite 1) : on se contente ici de l'enfiler, ce qui preserve les
	// scripts inline que les modules y ont attaches entre-temps.
	if ( ! wp_script_is( 'swiftboard-main', 'registered' ) ) {
		wp_register_script(
			'swiftboard-main',
			SWIFTBOARD_ASSETS . '/js/main.js',
			array(),
			SWIFTBOARD_VERSION,
			true
		);
	}
	wp_enqueue_script( 'swiftboard-main' );

	// Validation du formulaire d'inscription (CSP-safe, externalise).
	wp_enqueue_script( 'swiftboard-register', SWIFTBOARD_ASSETS . '/js/register-validate.js', array( 'swiftboard-main' ), SWIFTBOARD_VERSION, true );

	// v5.0 EXI-BLOQ-01 : vrai moteur de vote (REST)
	wp_enqueue_script( 'swiftboard-votes', SWIFTBOARD_ASSETS . '/js/votes.js', array( 'swiftboard-main' ), SWIFTBOARD_VERSION, true );
	// Actions du menu « ⋯ » des cartes : copier le lien, signaler.
	wp_enqueue_script( 'swiftboard-card-menu', SWIFTBOARD_ASSETS . '/js/card-menu.js', array( 'swiftboard-main' ), SWIFTBOARD_VERSION, true );

	// v5.0 EXI-QUAL-03 : upload d'images (utilisateurs connectes uniquement)
	if ( is_user_logged_in() ) {
		wp_enqueue_script( 'swiftboard-upload', SWIFTBOARD_ASSETS . '/js/upload.js', array( 'swiftboard-main' ), SWIFTBOARD_VERSION, true );

		// EXI-QUAL-06 : configuration par attributs data-* et non par
		// wp_localize_script(), qui emettrait un <script> inline incompatible
		// avec la CSP en enforce. La note detaillee se trouve dans le module
		// des votes (le nonce CSP y est inutilisable : le theme sert un cache
		// de pages HTML, dont le nonce fige diverge de l'en-tete regeneree).
		add_action(
			'wp_footer',
			function () {
				printf(
					'<div id="sb-upload-config" hidden data-rest-url="%s" data-nonce="%s"></div>',
					esc_attr( esc_url_raw( rest_url() ) ),
					esc_attr( wp_create_nonce( 'wp_rest' ) )
				);
			},
			5
		);
	}

	// JS anti-FOUC — render-blocking dans <head>, applique data-theme avant le rendu
	// (v4.6 : extrait du inline_script pour permettre CSP strict sans 'unsafe-inline')
	wp_enqueue_script(
		'swiftboard-anti-fouc',
		SWIFTBOARD_ASSETS . '/js/anti-fouc.js',
		array(),
		SWIFTBOARD_VERSION,
		false // dans <head>, pas defer
	);

	// Anti-FOUC : assure par assets/js/anti-fouc.js, charge ci-dessus dans le
	// <head>. L'ancien helper add_inline_script_for_theme() etait garde par un
	// `if (false)` depuis le passage a la CSP stricte (le JS inline y est
	// refuse) : bloc et helper supprimes, ils ne pouvaient plus s'executer.

	// Injecter le nonce WP REST pour les appels frontend (notifications, votes)
	// v4.6 : via body class au lieu de inline script (CSP strict compatible)

	// Désactiver le superflu
	if ( ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
		wp_deregister_style( 'dashicons' );
	}

	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	// wp_shortlink_wp_head aussi retiré dans inc/security.php (hardening).
	// Gardé ici pour la cohérence du bloc cleanup head (emojis + REST + oEmbed).
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'wp_enqueue_scripts', 'swiftboard_enqueue_assets', 99 );

/**
 * Enregistre swiftboard-main AVANT que les modules y attachent leur code.
 *
 * POURQUOI CE HOOK SEPARE
 * -----------------------
 * swiftboard_enqueue_assets() tourne en priorite 99, mais quatre modules
 * (reddit-layout, forum-customizer, nested-comments, user-content-actions)
 * appellent wp_add_inline_script('swiftboard-main', ...) en priorite 30.
 * A ce moment-la le handle n'existe pas encore : wp_add_inline_script()
 * renvoie false et TOUT leur JavaScript est perdu, sans la moindre erreur.
 *
 * Symptomes constates dans un vrai navigateur avant correction : les boutons
 * Sauvegarder / Cacher / Partager ne declenchaient aucune requete, les
 * commentaires imbriques et la synchronisation des listes utilisateur etaient
 * inertes.
 *
 * wp_register_script() se contente de DECLARER le script : il n'est envoye au
 * navigateur que si swiftboard_enqueue_assets() l'enfile ensuite. Enregistrer
 * tot ne charge donc rien de plus, cela ouvre seulement le point d'accroche.
 *
 * @return void
 */
function swiftboard_enregistrer_scripts_tot() {
	wp_register_script(
		'swiftboard-main',
		SWIFTBOARD_ASSETS . '/js/main.js',
		array(),
		SWIFTBOARD_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'swiftboard_enregistrer_scripts_tot', 1 );

// ----------------------------------------------------------------------------
// v5.0 EXI-BLOQ-05 : nonce REST expose au JS
//
// Ces hooks etaient enregistres DEPUIS swiftboard_enqueue_assets(), laquelle
// s'execute pendant wp_head priorite 1. WordPress avait deja depasse ce point :
// le callback etait ajoute trop tard et n'etait JAMAIS execute.
// Consequence mesuree : <meta sb-rest-nonce> absente -> 401 sur les appels REST
// authentifies (notifications, "tout marquer comme lu").
// Enregistrement au niveau racine du fichier = execution garantie.
// ----------------------------------------------------------------------------
add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_user_logged_in() ) {
			$classes[] = 'sb-rest-ready';
		}
		return $classes;
	}
);

add_action(
	'wp_head',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		echo '<meta name="sb-rest-nonce" content="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">' . "\n";
	},
	1
);

// Ajout defer sur les JS thème non critiques (PAS anti-fouc : doit rester bloquant en <head>).
add_filter(
	'script_loader_tag',
	function ( $tag, $handle ) {
		$defer_handles = array(
			'swiftboard-main',
			'swiftboard-votes',
			'swiftboard-upload',
			'swiftboard-search-suggest',
			'swiftboard-web-vitals',
			'swiftboard-load-more',
			'swiftboard-sse',
		);
		if ( in_array( $handle, $defer_handles, true ) && strpos( $tag, ' defer' ) === false ) {
			return str_replace( ' src', ' defer src', $tag );
		}
		return $tag;
	},
	10,
	2
);

// ============================================================================

/**
 * CSP bridge for bbPress engagements.
 * Keeps the bbPress plugin JavaScript and behavior intact while replacing
 * wp_localize_script() executable data with an inert DOM configuration node.
 *
 * @return void
 */
function swiftboard_externalize_bbpress_engagement_config() {
	if ( ! ( bbp_is_single_forum() || bbp_is_single_topic() ) ) {
		return;
	}
	global $wp_scripts;
	if ( ! $wp_scripts instanceof WP_Scripts || ! isset( $wp_scripts->registered['bbpress-engagements'] ) ) {
		return;
	}
	$script = $wp_scripts->registered['bbpress-engagements'];
	$data = isset( $script->extra['data'] ) ? (string) $script->extra['data'] : '';
	if ( ! $data ) {
		return;
	}
	$object_id = function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic()
		? (int) bbp_get_topic_id()
		: ( function_exists( 'bbp_get_forum_id' ) ? (int) bbp_get_forum_id() : (int) get_queried_object_id() );
	$ajax_url = bbp_get_ajax_url();
	$error    = __( 'Something went wrong. Refresh your browser and try again.', 'bbpress' );
	$script->extra['data'] = '';
	wp_enqueue_script(
		'swiftboard-bbpress-engagement-config',
		SWIFTBOARD_ASSETS . '/js/bbpress-engagement-config.js',
		array(),
		SWIFTBOARD_VERSION,
		false
	);
	printf(
		'<div id="swiftboard-bbp-engagement-config" hidden data-object-id="%d" data-ajax-url="%s" data-error="%s"></div>',
		$object_id,
		esc_attr( esc_url_raw( $ajax_url ) ),
		esc_attr( $error )
	);
}
add_action( 'wp_enqueue_scripts', 'swiftboard_externalize_bbpress_engagement_config', 999 );
