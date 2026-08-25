<?php
/**
 * SwiftBoard v2 - Functions & Theme Setup
 *
 * Thème forum ultra-performant, design Reddit-inspired, SEO-first, LLM-readable.
 * Compatible bbPress + Elementor.
 *
 * @package SwiftBoard
 * @since 2.0.0
 */

// ============================================================================
// SÉCURITÉ
// ============================================================================

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marque l’installation SwiftBoard comme éligible à une revue de nettoyage.
 *
 * Un changement de thème ne doit jamais supprimer silencieusement des données.
 * La suppression RGPD reste une action explicite, confirmée par un administrateur.
 *
 * @return void
 */
function swiftboard_register_cleanup_review() {
	if ( current_user_can( 'manage_options' ) ) {
		update_option( 'swiftboard_cleanup_review_pending', time(), false );
	}
}
add_action( 'after_switch_theme', 'swiftboard_register_cleanup_review' );

// v8.3 — Forcer la locale arabe TRÈS TÔT (avant any text domain loading)
// Doit être ici, pas dans after_setup_theme, pour que load_theme_textdomain
// voie déjà la bonne locale au premier appel.
if ( get_option( 'swiftboard_force_rtl' ) === '1' ) {
	add_filter(
		'locale',
		function () {
			return 'ar';
		},
		1
	);
}
// ============================================================================
// 1. CONSTANTES
// ============================================================================
define( 'SWIFTBOARD_VERSION', '11.0.6' );
if ( ! defined( 'SWIFTBOARD_DIR' ) ) {
	$sb_dir = get_template_directory();
	// Fallback: if get_template_directory() returns wrong path, use __DIR__.
	if ( ! is_dir( $sb_dir . '/inc' ) && is_dir( __DIR__ . '/inc' ) ) {
		$sb_dir = __DIR__;
	}
	define( 'SWIFTBOARD_DIR', $sb_dir );
}
if ( ! defined( 'SWIFTBOARD_URI' ) ) {
	$swiftboard_uri = get_template_directory_uri();
	if ( is_multisite() && function_exists( 'network_site_url' ) ) {
		// En réseau sous-directory, site_url() inclut /community/ alors que
		// wp-content reste à la racine du réseau. Les assets doivent donc
		// utiliser l’URL réseau, sinon chaque JS/CSS du sous-site devient 404.
		$theme_relative = str_replace( wp_normalize_path( WP_CONTENT_DIR ), '', wp_normalize_path( get_template_directory() ) );
		$swiftboard_uri = network_site_url( '/wp-content/' . trim( $theme_relative, '/' ) );
	}
	define( 'SWIFTBOARD_URI', untrailingslashit( $swiftboard_uri ) );
}
if ( ! defined( 'SWIFTBOARD_ASSETS' ) ) {
	define( 'SWIFTBOARD_ASSETS', SWIFTBOARD_URI . '/assets' );
}

// ============================================================================
// 1b. HELPER — nom de table custom (anti-duplication, 68 occurrences)
// ============================================================================

if ( ! function_exists( 'swiftboard_table' ) ) {
	/**
	 * Retourne le nom complet d'une table custom SwiftBoard.
	 *
	 * @param string $suffix Le suffixe de la table (ex: 'votes', 'notifications').
	 * @return string Le nom complet avec prefix (ex: 'wp_swiftboard_votes').
	 */
	function swiftboard_table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'swiftboard_' . $suffix;
	}
}

/**
 * Permission callback explicite pour les routes REST publiques en lecture.
 * Les routes de mutation doivent utiliser une permission dédiée et un nonce.
 *
 * @param WP_REST_Request|null $request Requête REST éventuelle.
 * @return bool
 */
function swiftboard_rest_public_permission( $request = null ) {
	unset( $request );
	return true;
}

// ============================================================================
// 2. AUTOLOAD DES MODULES
// ============================================================================
// Modules are split into front + admin arrays below (v4.0 lazy loading)

// Front modules (always loaded).
$swiftboard_front_modules = array(
	'inc/page-cache.php',   // Cache de pages anonymes (tenue en charge).
	'inc/performance.php',
	'inc/web-vitals.php',   // EXI-PERF-04 : RUM Core Web Vitals.
	'inc/seo.php',
	'inc/schema.php',
	'inc/schema-cache.php',   // EXI-ARCH-01 : invalidation du cache schema.org.
	'inc/forum.php',
	'inc/elementor.php',
	'inc/votes-social.php',
	'inc/votes-counters.php',        // EXI-ARCH-01 : compteurs lus a chaque affichage.
	'inc/votes-rest.php',            // EXI-ARCH-01 : couche HTTP des votes.
	'inc/notifications.php',
	'inc/notifications-batch.php',
	'inc/notifications-rest.php',    // EXI-ARCH-01 : cloche interrogee en front.
	'inc/top-weekly-responder.php',
	'inc/hot-topics.php',
	'inc/reddit-layout.php',
	'inc/join-button.php',
	'inc/icons.php',
	'inc/nav-lateral.php',
	'inc/forum-rules.php',
	'inc/author-line.php',
	'inc/custom-css-guard.php',
	'inc/demo-blog.php',
	'inc/feed-sort.php',
	'inc/nested-comments.php',
	'inc/user-content-actions.php',
	'inc/user-content-rest.php',     // EXI-ARCH-01 : routes membre.
	'inc/reddit-profile.php',
	'inc/profile-sections.php',      // EXI-ARCH-01 : onglets du profil.
	'inc/forum-customizer.php',
	'inc/llm-readability.php',
	'inc/llm-rest.php',              // EXI-ARCH-01 : crawlers anonymes.
	'inc/security.php',
	'inc/security-headers.php',      // EXI-ARCH-01 : en-tetes HTTP + CSP.
	'inc/content-kses.php',          // CDC-PROD-FERME-01 : KSES sortie topic/reply.
	'inc/class-swiftboard-popular-topics-widget.php',
	'inc/db-optimizations.php',
	'inc/retention-donnees.php',   // Purge des croissances non bornees (G2/G4).
	'inc/suppression-compte.php',  // RGPD art. 17 : effacement en self-service.
	'inc/cursor-pagination.php',
	'inc/sse-notifications.php',
	'inc/fulltext-search.php',
	'inc/fulltext-search-rest.php',  // CDC-RESTANT-01 : REST search + suggest + assets.
	'inc/audit-trail.php',
	'inc/sitemap-bbpress.php',
	'inc/grades.php', // v4.6.1 : grades + réputation (front-end, extrait de admin-settings-grades.php)
	// EXI-ARCH-01 : le moteur de promotion s'accroche a bbp_new_reply,
	// swiftboard_vote_cast et wp — des hooks FRONT. Depuis un module
	// admin-only, ils n'etaient jamais enregistres pour un visiteur.
	'inc/promotion.php',
	'inc/login-branding.php', // v5.0 EXI-AUTH-01 : branding de /wp-login.php.
	'inc/avatars.php',          // Avatars du forum (Reddit-style).
	'inc/onboarding-wizard.php',
	'inc/auth-social.php',
	'inc/customizer.php',
	'inc/ocdi-demo-import.php',
	'inc/native-demo-import.php',
	'inc/best-answer.php',
	'inc/reporting.php',
	'inc/custom-badges.php',
	'inc/vip-memberships.php',
	'inc/gutenberg-blocks.php',
	'inc/elementor-widgets.php',
	'inc/subreddits.php',       // Subreddits (communautés rejointes).
);

// ----------------------------------------------------------------------------
// v5.0 EXI-BLOQ-03 : reclassement des modules
//
// RÈGLE : un module n'est admin-only que s'il ne contient AUCUN hook front
// (rest_api_init, wp_head, wp_footer, wp_enqueue_scripts, template_redirect,
// init, user_register, bbp_new_*, send_headers, the_content, body_class).
//
// Ces 4 modules contiennent des hooks front et étaient chargés uniquement
// en admin — cause racine de 6 bugs (routes REST 404, couleurs jamais
// émises, désabonnement RGPD inopérant, auto-promotion morte).
// Ils sont désormais chargés partout ; leurs écrans admin restent protégés
// par current_user_can() et add_menu_page (qui ne s'exécutent qu'en admin).
// ----------------------------------------------------------------------------
$swiftboard_mixed_modules = array(
	'inc/admin.php',                  // wp_head -> swiftboard_custom_colors().
	'inc/admin-settings-grades.php',  // user_register, bbp_new_reply, swiftboard_vote_cast.
	'inc/upload-quota.php',           // Réservation atomique du quota (requis par image-upload).
	'inc/upload-zone-front.php',      // zone d'upload dans les formulaires bbPress.
	'inc/ui-corrections.php',         // titre profil, aria-pressed, zone d'actions unifiée.
	'inc/multisite-tables.php',       // création des tables sur un nouveau site du réseau.
	'inc/image-upload.php',           // rest_api_init, init, the_content
	// EXI-ARCH-02 : module eclate. Ces trois-la restent en FRONT — la
	// conversion s'execute pendant l'envoi d'un visiteur, et une route REST
	// enregistree depuis un module admin-only renverrait 404.
	'inc/image-upload-schema.php',
	// CDC-PROD-FERME-05/06 : helpers admin chargés hors is_admin() pour WP-CLI/tests.
	'inc/admin-pagination.php',
	'inc/admin-user-stats.php',
	'inc/admin-moderation-badge.php', // CDC-CI-02 : badge indépendant du module admin-only.
	'inc/image-converter.php',
	'inc/upload-conversion.php',   // EXI-ARCH-01 : conversion AVIF extraite.
	'inc/image-moderation-api.php',
	'inc/email-digest.php',           // template_redirect (désabonnement RGPD).
	'inc/admin-social-login.php',     // OAuth API key settings page.
	'inc/oauth-callback.php',         // Real OAuth callback handlers (Google/GitHub/Facebook).
	// EXI-ARCH-01 : digest eclate. Le desabonnement DOIT rester en front :
	// le lien est ouvert depuis une boite mail, par un destinataire
	// generalement deconnecte.
	'inc/digest-data.php',           // EXI-ARCH-01 : selection et agregation.
	'inc/digest-render.php',
	'inc/digest-unsubscribe.php',
);

// Admin modules (purs : aucun hook front — chargés uniquement en admin).
$swiftboard_admin_modules = array(
	'inc/admin-dashboard.php',
	// EXI-ARCH-01 : écrans extraits de admin-settings-grades.php (rendu HTML pur).
	'inc/admin-grades-ui.php',
	'inc/admin-reputation-ui.php',
	'inc/admin-test-autopromote.php',
	'inc/admin-bulk-import.php',
	// EXI-ARCH-03 : import découpé (écran / entités).
	'inc/admin-image-moderation.php',
	'inc/admin-digest-ui.php',
	'inc/admin-dashboard-listes.php',
	'inc/admin-security-ui.php',
	'inc/admin-test-scenario.php',
	'inc/admin-import-ui.php',
	'inc/import-entities.php',
	'inc/import-csv.php',
	'inc/search-console.php',
);

foreach ( $swiftboard_front_modules as $module ) {
	$module_path = SWIFTBOARD_DIR . '/' . $module;
	if ( file_exists( $module_path ) ) {
		require_once $module_path;
	}
}

// Modules mixtes : chargés en front ET en admin (hooks front à l'intérieur).
foreach ( $swiftboard_mixed_modules as $module ) {
	$module_path = SWIFTBOARD_DIR . '/' . $module;
	if ( file_exists( $module_path ) ) {
		require_once $module_path;
	}
}


// TGMPA - Envato requirement.
if ( file_exists( SWIFTBOARD_DIR . '/inc/tgmpa/class-tgm-plugin-activation.php' ) ) {
	require_once SWIFTBOARD_DIR . '/inc/tgmpa/class-tgm-plugin-activation.php';
	require_once SWIFTBOARD_DIR . '/inc/tgmpa-config.php';
}

if ( is_admin() ) {
	foreach ( $swiftboard_admin_modules as $module ) {
		$module_path = SWIFTBOARD_DIR . '/' . $module;
		if ( file_exists( $module_path ) ) {
			require_once $module_path;
		}
	}
}

// ============================================================================
// 3. THEME SETUP
// ============================================================================
/**
 * Configure les supports et menus du thème.
 *
 * @return void
 */
function swiftboard_theme_setup() {
	load_theme_textdomain( 'swiftboard', SWIFTBOARD_DIR . '/languages' );

	// v8.3 — La locale ar est forcée en haut du fichier.
	// Le rechargement se fait dans init (priorité 0) avec load_textdomain direct.

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 32,
			'width'       => 32,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'navigation-widgets',
			'style',
			'script',
		)
	);

	// Pas de styles Gutenberg.
	remove_theme_support( 'core-block-patterns' );
	remove_theme_support( 'block-templates' );

	// Menus.
	register_nav_menus(
		array(
			'primary' => __( 'Navigation principale', 'swiftboard' ),
			'footer'  => __( 'Navigation pied de page', 'swiftboard' ),
			'forum'   => __( 'Menu forum', 'swiftboard' ),
		)
	);

	// Tailles d'image.
	add_image_size( 'swiftboard-avatar', 96, 96, true );
	add_image_size( 'swiftboard-thumb', 300, 200, true );
}
add_action( 'after_setup_theme', 'swiftboard_theme_setup' );

// ============================================================================
// Enqueue loaded from inc/enqueue.php
require_once SWIFTBOARD_DIR . '/inc/enqueue.php';

// 5. RETIRER LE CSS BPRESS (une seule fois — fixe le doublon de la v1)
// ============================================================================
/**
 * Retire les feuilles de style bbPress remplacées par SwiftBoard.
 *
 * @return void
 */
function swiftboard_remove_bbpress_css() {
	// bbPress charge bbp-default sur TOUTES les pages (bbp_register_styles).
	// On dequeue dès que bbPress est installé, pas uniquement sur les pages bbPress.
	if ( function_exists( 'is_bbpress' ) ) {
		wp_dequeue_style( 'bbp-default' );
		wp_dequeue_style( 'bbp-default-rtl' );
		wp_dequeue_style( 'bbp-rtl' );
	}
}
add_action( 'wp_enqueue_scripts', 'swiftboard_remove_bbpress_css', 999 );

// ============================================================================
// 6. SIDEBARS
// ============================================================================
/**
 * Enregistre les sidebars du forum et du blog.
 *
 * @return void
 */
function swiftboard_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Sidebar Forum', 'swiftboard' ),
			'id'            => 'forum-sidebar',
			'description'   => __( 'Apparaît sur les pages du forum', 'swiftboard' ),
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);

	register_sidebar(
		array(
			'name'          => __( 'Sidebar Blog', 'swiftboard' ),
			'id'            => 'blog-sidebar',
			'description'   => __( 'Apparaît sur les pages du blog', 'swiftboard' ),
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'swiftboard_widgets_init' );

// ============================================================================
// 7. BODY CLASS
// ============================================================================
/**
 * Ajoute les classes contextuelles aux pages bbPress.
 *
 * @param mixed $classes Classes existantes.
 * @return mixed
 */
function swiftboard_body_class( $classes ) {
	if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		$classes[] = 'is-forum-page';
	}
	return $classes;
}
add_filter( 'body_class', 'swiftboard_body_class' );

// ============================================================================
// 7.5. CONTENT-TYPE FIX (v4.6.1 — bug FrankenPHP : bloginfo('charset') retourne vide)
// ============================================================================
add_action(
	'template_redirect',
	function () {
		if ( is_feed() || is_trackback() || is_robots() || defined( 'REST_REQUEST' ) ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		header( 'Content-Type: text/html; charset=UTF-8' );
	},
	1
);

// ============================================================================
// 8. HELPERS
// ============================================================================

/**
 * Récupère une option du thème avec valeur par défaut.
 *
 * @param string $key     Clé dans le tableau d'options du thème.
 * @param mixed  $default Valeur rendue si la clé est absente. Le type suit
 *                        l'option : chaine, entier (largeurs, delais) ou
 *                        booleen (bascules). L'annotation « string », deduite
 *                        de la valeur par defaut, etait trop etroite.
 * @return mixed
 */
// Helpers loaded from inc/helpers.php (extracted for code organization).
require_once SWIFTBOARD_DIR . '/inc/helpers.php';

// Display role loaded from inc/display-role.php.
require_once SWIFTBOARD_DIR . '/inc/display-role.php';

// ============================================================================
// SEO v7.2.0 — Titres 160 caractères + optimisations
// ============================================================================

/**
 * Limite la longueur des titres de sujets bbPress à 160 caractères.
 * 160 = longueur max d'une meta description Google.
 * Au-delà : le titre est coupé dans les SERPs et le design casse.
 * Seulement en affichage, pas en édition (sinon on perd des données).
 */
add_filter(
	'bbp_get_topic_title',
	function ( $title ) {
		if ( is_admin() ) {
			return $title;
		}
		$max = 160;
		if ( mb_strlen( $title ) > $max ) {
			return mb_substr( $title, 0, $max - 1 ) . '…';
		}
		return $title;
	},
	10,
	2
);

/**
 * Compteur de caractères côté formulaire (compatible CSP).
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( function_exists( 'is_bbpress' ) && is_bbpress() && ! is_admin() ) {
			wp_enqueue_script(
				'swiftboard-title-counter',
				SWIFTBOARD_ASSETS . '/js/topic-title-counter.js',
				array(),
				SWIFTBOARD_VERSION,
				true
			);
		}
	},
	50
);

/**
 * Optimise le <title> pour les sujets bbPress.
 * Format : "Titre du sujet | Nom du forum | Nom du site"
 */
add_filter(
	'document_title_parts',
	function ( $title ) {
		if ( function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic() ) {
			$title['title'] = bbp_get_topic_title();
			$title['site']  = get_bloginfo( 'name' );
			$forum_id       = bbp_get_topic_forum_id();
			if ( $forum_id ) {
				$title['tagline'] = bbp_get_forum_title( $forum_id );
			}
		}
		return $title;
	}
);


// ============================================================================
// LANGUAGE POPUP — Browser language detection + switcher
// ============================================================================

/**
 * Enqueue language popup script with Polylang URLs.
 * Shows a popup if visitor browser language differs from site language.
 * Requires Polylang (free) for actual language switching.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$locale    = get_locale();
		$site_lang = substr( $locale, 0, 2 ); // Extract the language prefix from the locale.

		// Available languages from Polylang or theme translations.
		$available = array( 'fr', 'en', 'ar' );
		if ( function_exists( 'pll_the_languages' ) ) {
			$pll_langs = pll_the_languages(
				array(
					'raw'                    => 1,
					'hide_if_no_translation' => 1,
				)
			);
			if ( ! empty( $pll_langs ) ) {
				$available = array_keys( $pll_langs );
			}
		}

		wp_enqueue_script(
			'swiftboard-lang-popup',
			SWIFTBOARD_ASSETS . '/js/language-popup.js',
			array(),
			SWIFTBOARD_VERSION,
			true
		);

		// CSP-safe: pass config via data-* attributes on a hidden element,
		// NOT via wp_localize_script (which outputs inline <script> blocked by CSP).
		$lang_urls = array();

		// Get Polylang URLs for each language.
		if ( function_exists( 'pll_the_languages' ) ) {
			$pll_langs = pll_the_languages( array( 'raw' => 1 ) );
			if ( ! empty( $pll_langs ) ) {
				foreach ( $pll_langs as $lang ) {
					$lang_urls[ $lang['slug'] ] = $lang['url'];
				}
			}
		}

		// Translatable popup strings are passed via data attributes so JavaScript does not need __().
		$popup_config = sprintf(
			'<div id="sb-lang-popup-config" hidden'
			. ' data-site-lang="%s"'
			. ' data-available-langs="%s"'
			. ' data-lang-urls="%s"'
			. ' data-msg-title="%s"'
			. ' data-msg-body="%s"'
			. ' data-msg-switch="%s"'
			. ' data-msg-stay="%s"'
			. ' data-msg-hint="%s"'
			. '></div>',
			esc_attr( $site_lang ),
			esc_attr( implode( ',', $available ) ),
			esc_attr( wp_json_encode( $lang_urls ) ),
			esc_attr( __( 'Change language?', 'swiftboard' ) ),
			/* translators: %s: comma-separated list of available languages. */
				esc_attr( __( 'This site is also available in %s. Would you like to switch?', 'swiftboard' ) ),
			esc_attr( __( 'Switch', 'swiftboard' ) ),
			esc_attr( __( 'Stay here', 'swiftboard' ) ),
			esc_attr( __( 'You can change this anytime in the header.', 'swiftboard' ) )
		);

		// Output the configuration element in the footer before the script loads.
		add_action(
			'wp_footer',
			function () use ( $popup_config ) {
				// La configuration est construite exclusivement avec des valeurs déjà échappées.
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON HTML pré-échappé
				echo $popup_config;
			},
			5
		);
	}
);


// ============================================================================
// Online users counter — REMOVED (product decision).
// Le compteur "🟢 X en ligne" a été supprimé du thème.
// Seul le nombre de vues par sujet (_bbp_voice_count) est affiché.
// ============================================================================

// Sandbox hook removed (LOT 1).

/**
 * Enregistrer les views bbPress manquantes (statistics, topics).
 * bbPress ne fournit que 'popular' et 'no-replies' par défaut.
 */
add_action(
	'bbp_register_views',
	function () {
		// Register the archive view for all topics.
		if ( function_exists( 'bbp_register_view' ) ) {
			bbp_register_view(
				'all-topics',
				__( 'Tous les sujets', 'swiftboard' ),
				array(
					'post_type'      => 'topic',
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
		}
	}
);

// ============================================================================
// v8.2 — FORCER RTL QUAND IMPORT DÉMO ARABE
// ============================================================================
add_filter(
	'language_attributes',
	function ( $output ) {
		if ( get_option( 'swiftboard_force_rtl' ) === '1' ) {
			// Remplacer dir="ltr" par dir="rtl" ou ajouter dir="rtl".
			if ( strpos( $output, 'dir="ltr"' ) !== false ) {
				$output = str_replace( 'dir="ltr"', 'dir="rtl"', $output );
			} elseif ( strpos( $output, 'dir="rtl"' ) === false ) {
				$output .= ' dir="rtl"';
			}
			// Forcer lang="ar".
			if ( strpos( $output, 'lang="fr' ) !== false ) {
				$output = preg_replace( '/lang="fr[^"]*"/', 'lang="ar"', $output );
			} elseif ( strpos( $output, 'lang="ar"' ) === false ) {
				$output = preg_replace( '/lang="[^"]*"/', 'lang="ar"', $output );
			}
		}
		return $output;
	},
	999
);

// ============================================================================
// v8.3 — RECHARGER LES TRADUCTIONS ARABES APRÈS init
// Le filtre locale est enregistré en haut du fichier, mais load_theme_textdomain
// peut avoir déjà chargé les traductions FR avant. On recharge en ar.
// ============================================================================
add_action(
	'init',
	function () {
		if ( get_option( 'swiftboard_force_rtl' ) === '1' ) {
			// load_theme_textdomain met en cache et ne recharge pas.
			// load_textdomain avec chemin direct force le rechargement.
			unload_textdomain( 'swiftboard' );
			load_textdomain( 'swiftboard', SWIFTBOARD_DIR . '/languages/swiftboard-ar.mo' );
		}
	},
	0
);
