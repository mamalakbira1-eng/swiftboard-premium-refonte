<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard v2 - Module Performance
 *
 * Désactive tout ce qui ralentit WordPress.
 * Optimisations agressives pour un score PageSpeed 95+.
 *
 * @package SwiftBoard
 * @since 2.0.0
 */
// ============================================================================
// 1. DÉSACTIVER LE SUPERFLU
// ============================================================================

// Désactiver jQuery Migrate
/**
 * swiftboard_remove_jquery_migrate().
 *
 * @param mixed $scripts À documenter.
 * @return void
 */
function swiftboard_remove_jquery_migrate( $scripts ) {
	if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
		$script = $scripts->registered['jquery'];
		if ( $script->deps ) {
			$script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
		}
	}
}
add_action( 'wp_default_scripts', 'swiftboard_remove_jquery_migrate' );

// Note : xmlrpc_enabled est géré dans inc/security.php (hardening).
// Le filtre xmlrpc_enabled bloque les méthodes authentifiées mais PAS
// pingback.ping (anonyme) — voir xmlrpc_methods dans security.php.

// Désactiver les pingbacks/trackbacks (sortants)
/**
 * swiftboard_disable_pingbacks().
 *
 * @param mixed $links À documenter.
 * @return void
 */
function swiftboard_disable_pingbacks( &$links ) {
	// v4.6 : check sur le host plutôt que strpos (audit 01)
	// évite faux positif sur domaine légitime contenant "xmlrpc"
	foreach ( $links as $l => $link ) {
		$host = wp_parse_url( $link, PHP_URL_HOST );
		if ( ! $host ) {
			// URL invalide → on la skip par sécurité
			unset( $links[ $l ] );
			continue;
		}
		// Allowlist : on garde seulement les hosts qui ne contiennent pas "xmlrpc" comme sous-domaine
		// (on garde la logique simple : si "xmlrpc" apparaît dans le host, on le skip)
		if ( strpos( $host, 'xmlrpc' ) !== false ) {
			unset( $links[ $l ] );
		}
	}
}
add_action( 'pre_ping', 'swiftboard_disable_pingbacks' );

// Limiter les révisions
if ( ! defined( 'WP_POST_REVISIONS' ) ) {
	define( 'WP_POST_REVISIONS', 3 );
}
if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {
	define( 'AUTOSAVE_INTERVAL', 300 );
}
// DISABLE_WP_CRON removed in v4.0 — SwiftBoard needs WP-Cron for 6 scheduled tasks

// ============================================================================
// 2. CLEANUP DU <head>
// ============================================================================
/**
 * @return void
 */
function swiftboard_cleanup_head() {
	// RSD + wlwmanifest retirés dans inc/security.php (hardening).
	// wp_generator retiré dans inc/security.php (hardening, avec the_generator filter).
	// wp_shortlink_wp_head retiré dans inc/security.php (hardening).
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
	remove_action( 'wp_head', 'wp_resource_hints', 2 );
}
add_action( 'init', 'swiftboard_cleanup_head' );

// Supprimer la version WP des URLs
/**
 * swiftboard_remove_version().
 *
 * @param mixed $src À documenter.
 * @return mixed
 */
function swiftboard_remove_version( $src ) {
	if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) !== false ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}
add_filter( 'script_loader_src', 'swiftboard_remove_version', 15 );
add_filter( 'style_loader_src', 'swiftboard_remove_version', 15 );

// ============================================================================
// 3. LAZY LOADING
// ============================================================================
/**
 * swiftboard_add_lazy_loading().
 *
 * @param string $content Contenu à traiter.
 * @return mixed
 */
function swiftboard_add_lazy_loading( $content ) {
	$content = str_replace( '<iframe', '<iframe loading="lazy"', $content );
	return $content;
}
add_filter( 'the_content', 'swiftboard_add_lazy_loading' );
add_filter( 'bbp_get_topic_content', 'swiftboard_add_lazy_loading' );
add_filter( 'bbp_get_reply_content', 'swiftboard_add_lazy_loading' );

// ============================================================================
// 4. CACHE HEADERS — v4.6 : activé par défaut (audit 10)
// Cache public 1h pour visiteurs anonymes, no-cache pour users connectés
// (déjà géré par security.php pour les users connectés)
// ============================================================================
/**
 * @return void
 */
function swiftboard_cache_headers() {
	if ( is_admin() || is_user_logged_in() ) {
		return; // security.php gère déjà le no-cache pour users connectés
	}
	// Seulement sur les pages publiques (pas sur wp-login, wp-admin, REST)
	if ( is_feed() || is_trackback() || is_robots() || defined( 'REST_REQUEST' ) ) {
		return;
	}
	// Cache-Control court + PAS de stale-while-revalidate long.
	// L'ancien 'stale-while-revalidate=86400' (24h) faisait garder la page
	// perimee par le navigateur pendant 24h meme apres vidage du cache.
	// On reduit a 60s et on supprime le SWR long pour rendre les mises a
	// jour visibles rapidement. Le cache de pages du theme (page-cache.php)
	// gere le cache deja-cache par son propre TTL.
	if ( ! headers_sent() ) {
		header( 'Cache-Control: public, max-age=60, s-maxage=60' );
	}
}
add_action( 'send_headers', 'swiftboard_cache_headers' );

// ============================================================================
// 5. DÉSACTIVER LES WIDGETS INUTILES
// ============================================================================
/**
 * @return void
 */
function swiftboard_unregister_widgets() {
	unregister_widget( 'WP_Widget_Pages' );
	unregister_widget( 'WP_Widget_Calendar' );
	unregister_widget( 'WP_Widget_Archives' );
	unregister_widget( 'WP_Widget_Links' );
	unregister_widget( 'WP_Widget_Meta' );
	unregister_widget( 'WP_Widget_RSS' );
	unregister_widget( 'WP_Widget_Tag_Cloud' );
}
add_action( 'widgets_init', 'swiftboard_unregister_widgets', 1 );

// ============================================================================
// 6. AVATAR FALLBACK — empêcher les requêtes Gravatar externes
// ============================================================================
/**
 * swiftboard_avatar_fallback().
 *
 * @param mixed  $avatar      À documenter.
 * @param string $id_or_email Adresse e-mail.
 * @param mixed  $size        À documenter.
 * @param mixed  $default     À documenter.
 * @param mixed  $alt         À documenter.
 * @return mixed
 */
function swiftboard_avatar_fallback( $avatar, $id_or_email, $size, $default, $alt ) {
	// Si on a un user, générer un SVG inline avec initiales
	$user_id = 0;
	if ( is_numeric( $id_or_email ) ) {
		$user_id = (int) $id_or_email;
	} elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
		$user_id = (int) $id_or_email->user_id;
	} elseif ( is_string( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
		if ( $user ) {
			$user_id = $user->ID;
		}
	}

	if ( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$initial   = mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) );
			$colors    = array( '#006cbd', '#0090e0', '#46a609', '#ec4899', '#d97706', '#dc2626' );
			$color     = $colors[ abs( $user_id ) % count( $colors ) ];
			$size_attr = intval( $size );
			// role="img" est OBLIGATOIRE : aria-label est ignore sur un <span>
			// au role implicite "generic", et le HTML est invalide sans lui
			// (erreur W3C reelle sur la page topic). Avec role="img", la
			// pastille est annoncee comme une image nommee par son aria-label,
			// ce qui est exactement ce qu'elle represente : un avatar de repli.
			return sprintf(
				'<span class="avatar-mock" role="img" style="background:%s;width:%dpx;height:%dpx;font-size:%dpx" aria-label="%s">%s</span>',
				esc_attr( $color ),
				$size_attr,
				$size_attr,
				max( 10, intval( $size / 2 ) ),
				esc_attr( $alt ?: $user->display_name ),
				esc_html( $initial )
			);
		}
	}
	return $avatar;
}
add_filter( 'get_avatar', 'swiftboard_avatar_fallback', 10, 5 );

// ============================================================================
// 7. NETTOYER LES RESOURCE HINTS
// ============================================================================
/**
 * swiftboard_remove_resource_hints().
 *
 * @param mixed  $hints         À documenter.
 * @param string $relation_type Type de contenu.
 * @return mixed
 */
function swiftboard_remove_resource_hints( $hints, $relation_type ) {
	if ( 'dns-prefetch' === $relation_type ) {
		return array_filter(
			$hints,
			function ( $url ) {
				return strpos( $url, '//s.w.org' ) === false;
			}
		);
	}
	return $hints;
}
add_filter( 'wp_resource_hints', 'swiftboard_remove_resource_hints', 10, 2 );
// ============================================================================
// JQUERY SUR LE FRONT — chargement conditionnel (EXI-PERF-05, complement)
// ============================================================================
/**
 * Retire les scripts bbPress inutiles a la page, et jQuery avec eux.
 *
 * MESURE LIGHTHOUSE (page sujet, mobile 4G simulee) :
 *   jquery.min.js = 86 Ko, 1512 ms de blocage du rendu.
 *   C'est de loin la premiere ressource bloquante de la page.
 *
 * EXI-PERF-05 avait retire jQuery des scripts du THEME, mais bbPress le
 * reintroduit par ses propres dependances :
 *   - bbpress-editor      (974 o)  -> deps: jquery
 *   - bbpress-engagements (1084 o) -> deps: jquery
 *
 * Soit environ 2 Ko de code utile qui tirent 86 Ko de dependance.
 *
 * Ces scripts ne servent que dans des cas precis :
 *   - editor      : boutons de la zone de saisie -> uniquement si un
 *                   formulaire de reponse/sujet est affiche, donc connecte
 *   - engagements : favoris/abonnements en AJAX -> uniquement connecte
 *
 * Un visiteur anonyme n'a ni formulaire ni bouton d'engagement : lui servir
 * 86 Ko bloquants est du pur gaspillage. Or c'est precisement le visiteur
 * anonyme que Google mesure, et celui qui decide de rester ou non.
 *
 * On ne retire donc RIEN pour les membres connectes : aucune fonctionnalite
 * n'est degradee.
 *
 * @return void
 */
function swiftboard_alleger_jquery_front() {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}

	// Laisse une porte de sortie si un plugin tiers en depend.
	if ( ! apply_filters( 'swiftboard_dequeue_bbpress_jquery', true ) ) {
		return;
	}

	foreach ( array( 'bbpress-editor', 'bbpress-engagements' ) as $handle ) {
		wp_dequeue_script( $handle );
	}

	// jQuery n'est retire que s'il ne reste AUCUN script pour en dependre :
	// un plugin tiers peut legitimement l'exiger.
	global $wp_scripts;
	if ( ! $wp_scripts instanceof WP_Scripts ) {
		return;
	}

	foreach ( $wp_scripts->queue as $handle ) {
		if ( 'jquery' === $handle || 'jquery-core' === $handle ) {
			continue;
		}
		$obj = $wp_scripts->registered[ $handle ] ?? null;
		if ( $obj && array_intersect( array( 'jquery', 'jquery-core' ), (array) $obj->deps ) ) {
			return; // un script en a encore besoin : on ne touche a rien
		}
	}

	wp_dequeue_script( 'jquery' );
	wp_dequeue_script( 'jquery-core' );
	wp_dequeue_script( 'jquery-migrate' );
}
add_action( 'wp_enqueue_scripts', 'swiftboard_alleger_jquery_front', 100 );
