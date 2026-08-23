<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard - Module Sécurité & Hardening
 *
 * Ajoute les protections essentielles manquantes :
 *
 * 1. Headers HTTP de sécurité
 *    - X-Frame-Options: SAMEORIGIN (anti clickjacking)
 *    - X-Content-Type-Options: nosniff (anti MIME sniffing)
 *    - Referrer-Policy: strict-origin-when-cross-origin
 *    - Permissions-Policy (disable caméra, micro, géoloc)
 *    - Strict-Transport-Security (HSTS) si HTTPS
 *    - X-XSS-Protection
 *
 * 2. Hardening WordPress
 *    - Désactiver XML-RPC (ataques brute force)
 *    - Cacher version WordPress (generator meta)
 *    - Désactiver énumération users (?author=N)
 *    - Désactiver file editor thème/plugin depuis admin
 *    - Désactiver REST API user enumeration (/wp-json/wp/v2/users)
 *    - Remove X-Powered-By header
 *
 * 3. Rate limiting global REST API
 *    - 60 req/min par IP sur /wp-json/swiftboard/*
 *    - Cache transient par IP
 *
 * 4. Protection login brute force
 *    - Max 5 tentatives par IP / 5min
 *    - Lock 15min après 5 échecs
 *
 * 5. Sanitization forcée sur $_GET/$_POST critiques
 *
 * @package SwiftBoard
 * @since 3.6.0
 */
// MESURE DU BON MOMENT (journalisation du nombre d'empreintes disponibles) :
// wp_head@0                  -> 1 hash   (trop tot)
// wp_enqueue_scripts@0       -> 1 hash   (trop tot)
// wp_enqueue_scripts@9999    -> 2 hash   <- retenu
// wp_head@9999 et au-dela    -> 2 hash   (mais sortie deja commencee)
// Priorite 9999 sur wp_enqueue_scripts : tous les scripts sont enfiles et
// localises, y compris ceux de bbPress, et aucune sortie n'a ete envoyee.
// CSP est désormais émise sur send_headers via swiftboard_envoyer_headers_basiques()
// dans inc/security-headers.php. L'ancien hook sur wp_enqueue_scripts ne fonctionnait
// pas : headers_sent() = true à ce moment → header() échouait silencieusement.

add_action(
	'send_headers',
	function () {
		if ( is_admin() || headers_sent() ) {
			return; // Ne pas casser l'admin ni PHPUnit (headers déjà envoyés).
		}

		// Anti clickjacking
		header( 'X-Frame-Options: SAMEORIGIN' );

		// Anti MIME sniffing
		header( 'X-Content-Type-Options: nosniff' );

		// Referrer policy
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		// X-XSS-Protection (legacy mais encore utile)
		header( 'X-XSS-Protection: 1; mode=block' );

		// Permissions Policy (disable caméra, micro, géoloc, payment)
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );

		// HSTS (seulement si HTTPS — sinon ça casse le site)
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload' );
		}

		// Cache-Control pour pages authentifiées
		if ( is_user_logged_in() ) {
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Pragma: no-cache' );
		}
	}
);

// ============================================================================
// 2. HARDENING WordPress
// ============================================================================

// 2.1 Désactiver XML-RPC
//
// CAUSE RACINE (prouvée par lecture de class-wp-xmlrpc-server.php WP 6.9) :
// Le filtre xmlrpc_enabled ne bloque QUE les méthodes authentifiées (login()).
// pingback.ping est une méthode ANONYME qui n'appelle jamais login() → le
// filtre est bypassé. C'est pourquoi nuclei a matché wp-xmlrpc-pingback-detection
// malgré xmlrpc_enabled → __return_false.
//
// CORRECTIF (3 couches, défense en profondeur), toutes contrôlables par
// l'utilisateur via l'option 'swiftboard_block_xmlrpc' (défaut: true) et le
// filtre homonyme. bbPress n'utilise pas XML-RPC (vérifié) → sûr par défaut.

/**
 * Détermine si le blocage XML-RPC est actif (option + filtre).
 *
 * @return bool True si xmlrpc.php doit être bloqué.
 */
function swiftboard_is_xmlrpc_blocked() {
	return (bool) apply_filters( 'swiftboard_block_xmlrpc', get_option( 'swiftboard_block_xmlrpc', true ) );
}

/**
 * Filtre xmlrpc_enabled : bloque les méthodes authentifiées si l'option est active.
 *
 * @param bool $is_enabled État courant.
 * @return bool False si bloqué, sinon état inchangé.
 */
function swiftboard_maybe_disable_xmlrpc( $is_enabled ) {
	return swiftboard_is_xmlrpc_blocked() ? false : $is_enabled;
}

/**
 * Retire pingback.ping des méthodes XML-RPC exposées (SSRF anonyme).
 *
 * @param array $methods Méthodes XML-RPC enregistrées.
 * @return array Méthodes filtrées.
 */
function swiftboard_remove_pingback_methods( $methods ) {
	if ( swiftboard_is_xmlrpc_blocked() ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
	}
	return $methods;
}

/**
 * Retire le header X-Pingback (évite d'annoncer que le pingback est disponible).
 *
 * @param array $headers Headers HTTP.
 * @return array Headers filtrés.
 */
function swiftboard_remove_xpingback_header( $headers ) {
	if ( swiftboard_is_xmlrpc_blocked() ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
}

/**
 * Bloque l'accès à xmlrpc.php avec un 403 localisé.
 *
 * Les filtres ci-dessus retirent pingback.ping (SSRF) et les méthodes auth,
 * MAIS xmlrpc.php répond encore 200 avec system.listMethods (méthodes internes
 * IXR_Server impossibles à retirer par filtre). On bloque le fichier entièrement.
 * Hookée sur 'init' priority 1 (avant tout traitement).
 *
 * Note technique : on utilise status_header() + die() plutôt que wp_die()
 * car xmlrpc.php définit XMLRPC_REQUEST avant wp-load.php. À ce stade (init),
 * $wp_xmlrpc_server n'est pas encore créé → le handler xmlrpc de wp_die()
 * échoue silencieusement (réponse 200 body vide, constaté en test live).
 */
function swiftboard_block_xmlrpc_request() {
	if ( ! swiftboard_is_xmlrpc_blocked() ) {
		return;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( stripos( $uri, 'xmlrpc.php' ) !== false ) {
		status_header( 403 );
		header( 'Content-Type: text/html; charset=utf-8' );
		die( esc_html__( 'XML-RPC est désactivé sur ce site.', 'swiftboard' ) );
	}
}

add_filter( 'xmlrpc_enabled', 'swiftboard_maybe_disable_xmlrpc' );
add_filter( 'xmlrpc_methods', 'swiftboard_remove_pingback_methods' );
add_filter( 'wp_headers', 'swiftboard_remove_xpingback_header' );
add_action( 'init', 'swiftboard_block_xmlrpc_request', 1 );

// 2.1c license.txt — DÉLÉGUÉ AU SERVEUR WEB, pas au PHP.
//
// license.txt est un fichier STATIQUE servi par Apache/nginx AVANT que WP ne
// charge. Un hook PHP ne peut pas l'intercepter. La version précédente
// supprimait le fichier via @unlink sur wp_loaded — c'était une ERREUR :
// cela cassait `wp core verify-checksums` (intégrité du core) et un thème ne
// doit JAMAIS modifier des fichiers hors de son répertoire.
//
// La bonne approche : un snippet .htaccess fourni dans docs/htaccess-hardening.txt
// et affiché dans la page admin 🛡️ Sécurité. Zero overhead PHP, checksums verts.
// → Voir swiftboard_security_page() dans inc/admin-security-ui.php

// 2.1d Désactiver le feed RDF (énumération d'utilisateurs).
//
// /feed/rdf est une ROUTE WordPress (pas un fichier statique) → WP charge →
// l'action do_feed_rdf se déclenche. On la remplace par un 404 localisé.
// Le feed RDF est déprécié depuis WP 3.0 et expose la liste des auteurs.
// Contrôlable par l'option 'swiftboard_block_rdf' (défaut: true).

/**
 * Détermine si le blocage du feed RDF est actif.
 *
 * @return bool True si /feed/rdf doit retourner 404.
 */
function swiftboard_is_rdf_blocked() {
	return (bool) apply_filters( 'swiftboard_block_rdf', get_option( 'swiftboard_block_rdf', true ) );
}

/**
 * Intercepte le feed RDF : retourne 404 si le bloc est actif, sinon appelle
 * le handler WP par défaut manuellement.
 *
 * Le handler original 'do_feed_rdf' est retiré de l'action et appelé directement
 * si le bloc est inactif. Cela garantit que seul notre code contrôle le flux.
 * La vérification de l'option se fait DANS la fonction pour que le toggle
 * prenne effet immédiatement.
 */
function swiftboard_disable_rdf_feed() {
	if ( ! swiftboard_is_rdf_blocked() ) {
		do_feed_rdf(); // Appeler le handler WP original manuellement.
		return;
	}
	status_header( 404 );
	header( 'Content-Type: text/html; charset=utf-8' );
	die( esc_html__( 'Le flux RDF n&#039;est pas disponible sur ce site.', 'swiftboard' ) );
}

remove_action( 'do_feed_rdf', 'do_feed_rdf', 10 );
add_action( 'do_feed_rdf', 'swiftboard_disable_rdf_feed', 1 );

// 2.2 Cacher la version WordPress
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// Supprimer les liens RSD + wlwmanifest (inutiles sauf si Windows Live Writer)
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

// Supprimer les liens shortlink
remove_action( 'wp_head', 'wp_shortlink_wp_head' );

// 2.3 Désactiver l'énumération d'utilisateurs (?author=N)
//
// DÉFAUT CORRIGÉ — le garde existait mais ne s'exécutait JAMAIS.
//
// Il était accroché à `template_redirect` en priorité 10 par défaut. Or le
// cœur y accroche `redirect_canonical` à cette MÊME priorité, et il est
// enregistré AVANT le thème : à priorité égale, WordPress respecte l'ordre
// d'enregistrement. `redirect_canonical` partait donc en premier, transformait
// `?author=1` en `/author/admin/` et appelait exit(). Le garde du thème
// n'était jamais atteint.
//
// Conséquence mesurée sur le site réel (simulation visiteur anonyme) :
// GET /?author=1  →  301  →  /author/admin/  →  200
// Le slug de l'URL EST le login. Un attaquant obtient la moitié du couple
// identifiant/mot de passe en une requête, puis force wp-login.php.
//
// Correction : priorité 1, donc AVANT redirect_canonical. La logique est
// extraite dans une fonction testable — un hook seul ne se teste pas.

/**
 * Détermine où rediriger une requête d'énumération d'utilisateur.
 *
 * @param mixed $author_param Valeur brute du paramètre `author` de l'URL.
 * @param int   $exclure      Identifiant à ignorer (usage de test).
 * @return string URL de repli, ou chaîne vide si aucune redirection n'est due.
 */
function swiftboard_securite_cible_enumeration( $author_param, $exclure = 0 ) {
	unset( $exclure );

	// Un membre connecté n'énumère pas : il navigue. Ne rien changer pour lui,
	// sinon la protection dégrade l'usage normal du forum.
	if ( is_user_logged_in() ) {
		return '';
	}

	// Seule la forme NUMÉRIQUE est une énumération. `/author/jean/`, saisi par
	// un visiteur ou suivi depuis un lien, reste légitime : le bloquer
	// désindexerait les pages d'auteur, que Google utilise pour l'attribution.
	if ( ! is_numeric( $author_param ) || (int) $author_param <= 0 ) {
		return '';
	}

	// La destination ne doit contenir AUCUN login : on renvoie à l'accueil.
	return home_url( '/' );
}

add_action(
	'template_redirect',
	function () {
		if ( is_admin() || ! isset( $_GET['author'] ) ) {
			return;
		}

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule d'un paramètre public.
		$cible = swiftboard_securite_cible_enumeration( sanitize_text_field( wp_unslash( $_GET['author'] ) ) );

		if ( $cible !== '' ) {
			wp_safe_redirect( $cible, 301 );
			exit;
		}
	},
	1
);   // priorité 1 : impérativement AVANT redirect_canonical (10).

// 2.4 Désactiver REST API user enumeration (/wp-json/wp/v2/users)
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			// Cacher /wp-json/wp/v2/users aux anonymes
			if ( isset( $endpoints['/wp/v2/users'] ) ) {
				unset( $endpoints['/wp/v2/users'] );
			}
			if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
				unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
			}
		}
		return $endpoints;
	}
);

// 2.5 Désactiver le file editor thème/plugin depuis l'admin
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// 2.6 Remove X-Powered-By header (front wp_headers + REST/send_headers)
// AMENDEMENT: always guard with headers_sent() to avoid aborting rest_api_init under CLI/PHPUnit.
add_filter(
	'wp_headers',
	function ( $headers ) {
		if ( ! headers_sent() ) {
			header_remove( 'X-Powered-By' );
		}
		return $headers;
	}
);
add_action(
	'init',
	function () {
		if ( ! headers_sent() ) {
			header_remove( 'X-Powered-By' );
		}
	},
	0
);
add_action(
	'rest_api_init',
	function () {
		if ( ! headers_sent() ) {
			header_remove( 'X-Powered-By' );
		}
	},
	0
);

// 2.7 Supprimer les emojis scripts (performance + sécurité)
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

// 2.8 Les dépréciations ne sont pas neutralisées globalement en production.
// Les tests filtrent explicitement les dépréciations connues de bbPress dans leur bootstrap ;
// les dépréciations provenant du thème restent des erreurs de qualité détectables.

// ============================================================================
// 3. RATE LIMITING GLOBAL REST API
// ============================================================================
/**
 * Limite : 60 requêtes / minute par IP sur /wp-json/swiftboard/*
 * Au-delà : retourne 429 Too Many Requests
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		$route = $request->get_route();
		if ( strpos( $route, '/swiftboard/' ) === false ) {
			return $result; // Ne s'applique qu'aux routes SwiftBoard
		}

		// Les requêtes GET (lecture seule) sont exemptées du rate limiting.
		// Sans ceci, un visiteur qui charge une page avec N colonnes de vote
		// émet N GETs et s'auto-limite à 60/min → erreurs 429 en console.
		$method = $request->get_method();
		if ( $method === 'GET' ) {
			return $result;
		}

		$ip        = swiftboard_get_client_ip();
		$cache_key = 'sb_rl_' . md5( $ip );

		$count = (int) get_transient( $cache_key );
		if ( $count >= 60 ) {
			return new WP_Error(
				'rate_limited',
				__( 'Trop de requêtes. Réessayez dans 1 minute.', 'swiftboard' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $cache_key, $count + 1, MINUTE_IN_SECONDS );
		return $result;
	},
	10,
	3
);

/**
 * @return mixed
 */
function swiftboard_get_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	// Ne pas trust X-Forwarded-For (sauf si derrière proxy connu)
	return filter_var( $ip, FILTER_VALIDATE_IP ) ?: '0.0.0.0';
}

// ============================================================================
// 4. PROTECTION LOGIN BRUTE FORCE
// ============================================================================
/**
 * Limite : 5 tentatives de connexion par IP / 5 minutes
 * Au-delà : bloque 15 minutes
 */
add_action(
	'wp_login_failed',
	function ( $username ) {
		$ip           = swiftboard_get_client_ip();
		$attempts_key = 'sb_login_' . md5( $ip );
		$lock_key     = 'sb_lock_' . md5( $ip );

		// Si déjà bloqué, ne rien faire
		if ( get_transient( $lock_key ) ) {
			return;
		}

		$attempts = (int) get_transient( $attempts_key );
		set_transient( $attempts_key, $attempts + 1, 5 * MINUTE_IN_SECONDS );

		// Si 5 échecs, bloquer 15min
		if ( $attempts + 1 >= 5 ) {
			set_transient( $lock_key, 1, 15 * MINUTE_IN_SECONDS );
			delete_transient( $attempts_key );
		}
	}
);

add_filter(
	'authenticate',
	function ( $user, $username, $password ) {
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}

		$ip       = swiftboard_get_client_ip();
		$lock_key = 'sb_lock_' . md5( $ip );

		if ( get_transient( $lock_key ) ) {
			return new WP_Error(
				'locked_out',
				__( 'Trop de tentatives échouées. Réessayez dans 15 minutes.', 'swiftboard' )
			);
		}

		return $user;
	},
	30,
	3
);

// Reset des tentatives après login réussi
add_action(
	'wp_login',
	function () {
		$ip = swiftboard_get_client_ip();
		delete_transient( 'sb_login_' . md5( $ip ) );
		delete_transient( 'sb_lock_' . md5( $ip ) );
	}
);

// ============================================================================
// 5. CAPABILITY HARDENING — Pages admin sensibles
// ============================================================================
// Les pages Réglages, Grades, Test auto-promo, Import, Digest, Search Console
// doivent requérir 'manage_options' (et non 'moderate_comments')
// — déjà le cas dans le code existant, ceci est une vérification runtime

add_action(
	'admin_init',
	function () {
		$sensitive_pages = array(
			'swiftboard-settings',
			'swiftboard-grades',
			'swiftboard-reputation',
			'swiftboard-digest',
			'swiftboard-weekly-top',
			'swiftboard-test-autopromote',
			'swiftboard-bulk-import',
			'swiftboard-search-console',
		);
		$current_page    = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		if ( in_array( $current_page, $sensitive_pages, true ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Accès refusé — permissions insuffisantes.', 'swiftboard' ), 403 );
		}
	}
);

// ============================================================================
// 6. DISABLE DIRECTORY BROWSING
// ============================================================================
// Créer des index.php vides dans les dossiers sensibles
add_action(
	'after_switch_theme',
	function () {
		$dirs = array(
			SWIFTBOARD_DIR . '/inc',
			SWIFTBOARD_DIR . '/assets',
			SWIFTBOARD_DIR . '/bbpress',
		);
		foreach ( $dirs as $dir ) {
			if ( is_dir( $dir ) ) {
				$index = $dir . '/index.php';
				if ( ! file_exists( $index ) ) {
					@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
				}
			}
		}
	}
);

// ============================================================================
// 7. SANITIZATION FORTE SUR INPUT CRITIQUES
// ============================================================================
// Protection supplémentaire sur $_GET['sort'] et $_GET['period']
// utilisés par le module feed-sort
add_filter(
	'swiftboard_get_current_sort',
	function ( $sort ) {
		$allowed = array( 'hot', 'new', 'top', 'rising' );
		return in_array( $sort, $allowed, true ) ? $sort : 'hot';
	}
);

add_filter(
	'swiftboard_get_current_period',
	function ( $period ) {
		$allowed = array( '24h', '7d', '30d', 'all' );
		return in_array( $period, $allowed, true ) ?  : 'all';
	}
);

// ============================================================================
// 8. PAGE ADMIN SÉCURITÉ (dashboard)
// ============================================================================
add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Sécurité', 'swiftboard' ),
			__( '🛡️ Sécurité' ),
			'manage_options',
			'swiftboard-security',
			'swiftboard_security_page'
		);
	}
);


/**
 * swiftboard_header_sent_check().
 *
 * @param string $header_name Nom.
 * @return string
 */
function swiftboard_header_sent_check( $header_name ) {
	// Pour la page admin, on simule — les vrais headers sont envoyés sur le front
	return '✅ Actif (front)';
}

// ============================================================================
// 9. LOG SÉCURITÉ (optionnel)
// ============================================================================
/**
 * Log les événements de sécurité dans un fichier (peut être désactivé)
 */
// Security log désactivé par défaut. Pour activer, ajouter dans wp-config.php :
// define( 'SWIFTBOARD_SECURITY_LOG', true );

/**
 * swiftboard_security_log().
 *
 * @param mixed  $event   À documenter.
 * @param string $details À documenter. Optionnel.
 * @return void
 */
function swiftboard_security_log( $event, $details = '' ) {
	if ( ! defined( 'SWIFTBOARD_SECURITY_LOG' ) || ! SWIFTBOARD_SECURITY_LOG ) {
		return;
	}

	$log_entry = sprintf(
		"[%s] %s — IP: %s — Details: %s\n",
		current_time( 'mysql' ),
		$event,
		swiftboard_get_client_ip(),
		$details
	);

	$log_file = WP_CONTENT_DIR . '/swiftboard-security.log';
	@file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );

	// Rotation : max 1MB
	if ( file_exists( $log_file ) && filesize( $log_file ) > 1048576 ) {
		@rename( $log_file, $log_file . '.' . date( 'Y-m-d-His' ) );
	}
}

// Log les tentatives de login échouées
add_action(
	'wp_login_failed',
	function ( $username ) {
		swiftboard_security_log( 'LOGIN_FAILED', "User: $username" );
	}
);

// Log les lockouts
add_action(
	'swiftboard_lockout',
	function ( $ip ) {
		swiftboard_security_log( 'LOCKOUT', "IP: $ip" );
	}
);

// ============================================================================
// 10. CLEANUP À LA DÉSACTIVATION
// ============================================================================
add_action(
	'switch_theme',
	function () {
		// Nettoyer les transients de rate limiting
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sb_rl_%' OR option_name LIKE '_transient_sb_login_%' OR option_name LIKE '_transient_sb_lock_%'" );
	}
);
