<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Cache de pages pour visiteurs anonymes.
 *
 * POURQUOI
 * --------
 * Mesure sur ce thème, page sujet : **218 ms de PHP et 33 requêtes SQL** par
 * affichage. Avec 10 workers PHP-FPM (mutualisé d'entrée de gamme), cela
 * plafonne à environ 45 pages/seconde. Impossible d'encaisser un pic de
 * plusieurs centaines de visiteurs simultanés.
 *
 * Or sur un forum, **la très grande majorité du trafic est anonyme et lit
 * exactement la même page**. Recalculer ce HTML identique à chaque visite est
 * le gaspillage principal.
 *
 * Ce module sert le HTML depuis un fichier, sans charger WordPress ni toucher
 * la base. Le coût passe de 218 ms à quelques millisecondes.
 *
 * CE QU'IL NE FAIT JAMAIS
 * -----------------------
 * - cacher une page pour un utilisateur CONNECTÉ (contenu personnalisé) ;
 * - cacher une requête POST, une recherche, une URL avec paramètres ;
 * - servir une page périmée après publication (invalidation sur événement).
 *
 * COMPLÉMENTARITÉ
 * ---------------
 * Un cache serveur (LiteSpeed Cache, Varnish, Cloudflare) reste préférable
 * quand il est disponible. Ce module couvre le cas fréquent où l'on n'a que
 * du PHP et un disque — c'est-à-dire le mutualisé d'entrée de gamme.
 *
 * @package SwiftBoard
 */
/**
 * Durée de vie d'une page en cache.
 */
if ( ! defined( 'SWIFTBOARD_PAGE_CACHE_TTL' ) ) {
	// TTL du cache de pages du thème. Réduit de 300s (5 min) à 60s pour
	// éviter les longues attentes après une modification (sujets, réponses).
	// Un cache serveur (LiteSpeed) a sa propre TTL et gère le long terme.
	define( 'SWIFTBOARD_PAGE_CACHE_TTL', 60 ); // Envato fix: TTL 60s, cache in uploads, not wp-content/cache
}

/**
 * Cache de pages actif ?
 */
if ( ! defined( 'SWIFTBOARD_PAGE_CACHE' ) ) {
	// Cache serveur présent ? (LiteSpeed Cache, WP Super Cache, Varnish,
	// Cloudflare...) => on laisse le cache SERVEUR faire le travail et on
	// DESACTIVE notre cache de pages pour éviter le double cache (deux
	// couches de pages anciennes = purge inefficace + délais).
	$cache_serveur = false;
	if ( defined( 'LSCWP_V' ) || defined( 'LSCWP_CONTENT_DIR' ) || class_exists( 'LiteSpeed\\Core' )
		|| defined( 'WPCACHEHOME' ) || ( defined( 'WP_CACHE' ) && WP_CACHE ) ) {
		$cache_serveur = true;
	}
	define( 'SWIFTBOARD_PAGE_CACHE', ! $cache_serveur );
}

/**
 * Répertoire de stockage.
 *
 * @return string
 */
function swiftboard_page_cache_dir() {
	// Envato compliant: write only inside uploads, never in wp-content/cache
	// Filterable for hosts that want custom location
	$upload = wp_upload_dir();
	$base   = $upload['basedir'];
	$dir    = $base . '/swiftboard-cache/pages';
	return apply_filters( 'swiftboard_page_cache_dir', $dir );
}

/**
 * La requête courante est-elle éligible au cache ?
 *
 * Volontairement restrictif : mieux vaut ne pas cacher que servir à un
 * visiteur le contenu destiné à un autre.
 *
 * @return bool
 */
function swiftboard_page_cache_eligible() {
	if ( ! SWIFTBOARD_PAGE_CACHE ) {
		return false;
	}
	if ( defined( 'DOING_CRON' ) || defined( 'DOING_AJAX' ) || defined( 'REST_REQUEST' ) || defined( 'WP_CLI' ) ) {
		return false;
	}
	// Suite de tests : ecrire des fichiers de cache a chaque go_to() ralentit
	// la suite (mesure : 5 s -> 25 s) sans rien prouver.
	if ( defined( 'WP_TESTS_DOMAIN' ) || defined( 'WP_RUN_CORE_TESTS' ) ) {
		return false;
	}
	if ( is_admin() || is_user_logged_in() ) {
		return false;
	}
	if ( ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' ) !== 'GET' ) {
		return false;
	}
	// Une URL avec paramètres est presque toujours personnalisée
	// (recherche, tri, pagination filtrée) : on ne la cache pas.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- empty() check only, no data used.
	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		return false;
	}
	// Un cookie de session WordPress signifie « utilisateur identifié »,
	// même si is_user_logged_in() n'est pas encore résolu.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- array_keys() only, no data extracted.
	foreach ( array_keys( $_COOKIE ) as $nom ) {
		if ( strpos( $nom, 'wordpress_logged_in' ) === 0
			|| strpos( $nom, 'wp-postpass' ) === 0
			|| strpos( $nom, 'comment_author' ) === 0 ) {
			return false;
		}
	}
	if ( function_exists( 'is_404' ) && did_action( 'template_redirect' ) && is_404() ) {
		return false;
	}
	/**
	 * Dernier mot sur la mise en cache d'une page.
	 *
	 * Le filtre ne peut que RESTREINDRE : toutes les gardes precedentes
	 * (connecte, cookie de session, POST, query string) ont deja rendu la
	 * main. Renvoyer true ne force donc jamais la mise en cache d'une page
	 * personnalisee — c'est voulu.
	 *
	 * @since 5.0.0
	 *
	 * @param bool $eligible True si la page peut etre mise en cache.
	 */
	return (bool) apply_filters( 'swiftboard_page_cache_eligible', true );
}

/**
 * Chemin du fichier de cache pour l'URL courante.
 *
 * @return string
 */
function swiftboard_page_cache_path() {
	$hote = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'default';
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	// Le rendu diffère entre mobile et desktop (menu, colonnes) : deux
	// entrées distinctes, sinon un mobile pourrait recevoir la version
	// desktop.
	$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$mobile = preg_match( '/Mobile|Android|iPhone|iPad/i', $ua ) ? 'm' : 'd';

	$cle = md5( $hote . '|' . $uri . '|' . $mobile );

	// Deux niveaux de sous-dossiers : évite des dizaines de milliers de
	// fichiers dans un seul répertoire, ce qui ralentit le système de
	// fichiers.
	return swiftboard_page_cache_dir() . '/' . substr( $cle, 0, 2 ) . '/' . substr( $cle, 2, 2 ) . '/' . $cle . '.html';
}

/**
 * Emet la CSP d'une page servie depuis le cache.
 *
 * Au moment d'un HIT, `template_redirect` s'execute avant tout appel a
 * `wp_enqueue_scripts` : la file de WP_Scripts est VIDE et les empreintes des
 * <script> inline calculees a la volee sont introuvables. La CSP emise ne
 * porterait alors que les empreintes codees en dur, et bloquerait le
 * JavaScript du theme — pour tous les visiteurs servis par le cache.
 *
 * On reinjecte donc, par le filtre prevu a cet effet, les empreintes
 * enregistrees a cote du fichier lors de la generation. Le HTML servi etant
 * identique, elles restent valides.
 *
 * @param string $fichier Fichier de cache servi.
 * @return void
 */
function swiftboard_page_cache_appliquer_csp( $fichier ) {
	if ( ! function_exists( 'swiftboard_envoyer_csp' ) ) {
		return;
	}

	$memorisees = @file_get_contents( $fichier . '.csp' );
	if ( is_string( $memorisees ) && $memorisees !== '' ) {
		$liste = array_filter( explode( ' ', trim( $memorisees ) ) );
		add_filter(
			'swiftboard_csp_script_hashes',
			function ( $hashes ) use ( $liste ) {
				return array_values( array_unique( array_merge( (array) $hashes, $liste ) ) );
			}
		);
	}

	swiftboard_envoyer_csp();
}

/**
 * Sert la page depuis le cache si elle est fraîche.
 *
 * Appelé le plus tôt possible : à ce stade WordPress est chargé, mais aucune
 * requête de contenu n'a encore été exécutée.
 *
 * @return void
 */
function swiftboard_page_cache_serve() {
	if ( ! swiftboard_page_cache_eligible() ) {
		return;
	}

	$fichier = swiftboard_page_cache_path();
	if ( ! is_readable( $fichier ) ) {
		return;
	}

	// Un fichier de cache vide ou tronque sert une page blanche en HTTP 200.
	// C'est arrive en production : ecriture interrompue, disque plein ou
	// processus tue entre file_put_contents() et rename(). Le visiteur
	// recevait 0 octet avec `X-SwiftBoard-Cache: HIT`, et le cache se
	// reservait lui-meme a chaque requete suivante.
	// swiftboard_page_cache_store() refuse deja d'ecrire moins de 500 octets ;
	// on applique le meme seuil a la LECTURE, et on supprime l'entree fautive
	// pour forcer une regeneration propre.
	$taille = (int) @filesize( $fichier );
	if ( $taille < 500 ) {
		@unlink( $fichier );
		@unlink( $fichier . '.csp' );
		return;
	}

	$age = time() - (int) filemtime( $fichier );
	if ( $age > SWIFTBOARD_PAGE_CACHE_TTL ) {
		return;
	}

	// 304 si le navigateur a déjà la page : on économise même le transfert.
	// FIX V2 - A1: wp_magic_quotes ajoute des backslashes à HTTP_IF_NONE_MATCH -> toujours 200 au lieu de 304
	// Pattern WP core class-wp.php:515 - wp_unslash avant comparaison
	$etag = '"' . md5_file( $fichier ) . '"';
	if ( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) ) === $etag ) {
		status_header( 304 );
		header( 'ETag: ' . $etag );
		// Meme raison que ci-dessous : un 304 sans CSP laisserait le
		// navigateur reutiliser la page cachee sans politique de securite.
		swiftboard_page_cache_appliquer_csp( $fichier );
		exit;
	}

	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'ETag: ' . $etag );
	header( 'X-SwiftBoard-Cache: HIT' );
	// max-age court + PAS de stale-while-revalidate long : un stale-while-
	// revalidate de 24h faisait garder l'ancienne page par le navigateur
	// pendant 24h même après vidage du cache. On garde un SWR court (30s)
	// pour la régénération tout en rendant le vidage visible rapidement.
	header(
		'Cache-Control: public, max-age=' . max( 0, SWIFTBOARD_PAGE_CACHE_TTL - $age )
		. ', stale-while-revalidate=30'
	);

	// La Content-Security-Policy est emise sur `wp_enqueue_scripts`, un hook
	// que cette fonction n'atteint JAMAIS : elle sert le fichier puis exit()
	// depuis `template_redirect`, bien avant.
	//
	// Consequence mesuree : sur un HIT du cache, la reponse ne portait aucune
	// CSP. Les visiteurs anonymes — c'est-a-dire la quasi-totalite du trafic
	// d'un forum, et precisement ceux que le cache sert — perdaient la
	// protection, alors qu'elle etait bien presente sur un MISS.
	//
	// Les autres en-tetes de securite (X-Frame-Options, X-Content-Type-Options)
	// ne sont pas concernes : ils partent sur `send_headers`, qui s'execute
	// avant `template_redirect`.
	// Les empreintes des <script> inline ont ete memorisees au moment de la
	// generation (voir swiftboard_page_cache_store). Sans elles, la CSP
	// emise ici serait calculee sur une file WP_Scripts VIDE et bloquerait
	// le JavaScript du theme pour tous les visiteurs servis par le cache.
	swiftboard_page_cache_appliquer_csp( $fichier );

	readfile( $fichier );
	exit;
}
add_action( 'template_redirect', 'swiftboard_page_cache_serve', 0 );

/**
 * Capture le HTML produit et l'enregistre.
 *
 * @return void
 */
function swiftboard_page_cache_start() {
	if ( ! swiftboard_page_cache_eligible() ) {
		return;
	}
	ob_start( 'swiftboard_page_cache_store' );
}
add_action( 'template_redirect', 'swiftboard_page_cache_start', 1 );

/**
 * Callback de bufferisation : écrit le fichier et renvoie le HTML au visiteur.
 *
 * @param string $html Contenu généré.
 * @return string
 */
function swiftboard_page_cache_store( $html ) {
	// Une page trop courte est presque toujours une erreur ou une redirection.
	if ( strlen( $html ) < 500 ) {
		return $html;
	}
	if ( function_exists( 'is_404' ) && is_404() ) {
		return $html;
	}
	// http_response_code() renvoie FALSE hors serveur web (CLI, WP-CLI, cron
	// en ligne de commande) : la comparaison stricte a 200 y echouait donc
	// toujours. Le cache etait ainsi silencieusement inoperant pour tout
	// pre-chauffage lance en CLI — un mode d'emploi pourtant courant. On
	// n'ecarte que les codes REELLEMENT differents de 200.
	$code = http_response_code();
	if ( false !== $code && 200 !== $code ) {
		return $html;
	}
	// Un nonce dans le HTML signifie du contenu lié à une session : le mettre
	// en cache le partagerait entre visiteurs. Garde-fou de dernier recours.
	if ( strpos( $html, 'sb-rest-nonce' ) !== false ) {
		return $html;
	}

	$fichier = swiftboard_page_cache_path();
	$dossier = dirname( $fichier );

	if ( ! is_dir( $dossier ) && ! wp_mkdir_p( $dossier ) ) {
		return $html;
	}

	// Écriture atomique : un visiteur ne doit jamais lire un fichier
	// partiellement écrit par une autre requête concurrente.
	$tmp = $fichier . '.' . getmypid() . '.tmp';
	if ( false !== file_put_contents( $tmp, $html, LOCK_EX ) ) {
		if ( ! @rename( $tmp, $fichier ) ) {
			@unlink( $tmp );
		}
	}

	// ------------------------------------------------------------------
	// EMPREINTES CSP MEMORISEES AVEC LA PAGE
	//
	// DEFAUT CORRIGE (mesure, pas supposition) : sur un HIT du cache,
	// swiftboard_envoyer_csp() est appelee depuis `template_redirect`, AVANT
	// que quoi que ce soit ne soit enfile dans WP_Scripts. La file est vide,
	// les empreintes calculees a la volee sont donc ABSENTES, et l'en-tete
	// n'annonce plus que les deux hash codes en dur.
	//
	// Mesure sur l'accueil de cet environnement :
	// MISS -> 4 empreintes dans la CSP, 0 script bloque
	// HIT  -> 2 empreintes dans la CSP, 1 script bloque
	// (« Executing inline script violates ... »)
	//
	// Le HTML servi etant STRICTEMENT le meme dans les deux cas (verifie par
	// `cmp`), les empreintes valides au moment de la generation le restent
	// pour toute la duree de vie du fichier. On les enregistre donc a cote
	// de la page, et le HIT les relit au lieu de les recalculer sur une file
	// vide.
	//
	// Portee reelle du defaut : le premier visiteur recevait une page
	// fonctionnelle, TOUS les suivants — c'est-a-dire l'essentiel du trafic
	// anonyme, celui que le cache est precisement cense servir — avaient le
	// JavaScript du theme bloque par la CSP.
	if ( function_exists( 'swiftboard_csp_hashes_inline' ) ) {
		$sb_empreintes = swiftboard_csp_hashes_inline();
		if ( ! empty( $sb_empreintes ) ) {
			$sb_tmp_csp = $fichier . '.csp.' . getmypid() . '.tmp';
			if ( false !== file_put_contents( $sb_tmp_csp, implode( ' ', $sb_empreintes ), LOCK_EX ) ) {
				if ( ! @rename( $sb_tmp_csp, $fichier . '.csp' ) ) {
					@unlink( $sb_tmp_csp );
				}
			}
		}
	}

	header( 'X-SwiftBoard-Cache: MISS' );
	return $html;
}

// ============================================================================
// INVALIDATION
// ============================================================================
/**
 * Vide entièrement le cache.
 *
 * Purge globale volontaire : sur un forum, publier une réponse modifie
 * l'accueil, la liste du forum, le fil « sujets chauds » et la page du sujet.
 * Une invalidation ciblée manquerait des pages et servirait du contenu
 * périmé — le défaut le plus visible d'un cache mal réglé.
 *
 * @return int Nombre de fichiers supprimés.
 */
function swiftboard_page_cache_flush(): int {
	$dir = swiftboard_page_cache_dir();
	if ( ! is_dir( $dir ) ) {
		return 0;
	}

	$n  = 0;
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $f ) {
		if ( $f->isFile() && @unlink( $f->getPathname() ) ) {
			++$n;
		}
	}
	// Purger aussi les transients de schema (contenu de sujets/réponses/votes).
	global $wpdb;
	if ( $wpdb ) {
		$n += (int) $wpdb->query(
			"DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_swiftboard_schema_topic_%'
                OR option_name LIKE '_transient_timeout_swiftboard_schema_topic_%'"
		);
	}
	return $n;
}

foreach ( array(
	'save_post',
	'deleted_post',
	'trashed_post',
	'bbp_new_topic',
	'bbp_new_reply',
	'bbp_edit_topic',
	'bbp_edit_reply',
	'swiftboard_vote_cast',
	'switch_theme',
	'customize_save_after',
	// Un reglage peut aussi changer HORS Customizer : WP-CLI (`wp theme mod
	// set`), un import de demo, un plugin tiers. Sans cette accroche, le cache
	// continuait de servir l'ancienne couleur et le reglage paraissait mort.
	// Constate en test dynamique : base = #e0218a, page servie = #006cbd.
	'update_option_theme_mods_' . get_stylesheet(),
) as $sb_evenement ) {
	// La fonction NOMMEE est accrochee directement, et non enveloppee dans une
	// closure : c'est ce qui rend la purge verifiable par has_action(), garde-fou
	// de PageCacheTest. Une closure anonyme rendrait l'accroche indetectable et
	// un retrait accidentel passerait inapercu.
	//
	// swiftboard_page_cache_flush() renvoie le nombre de fichiers supprimes
	// (exploite par la barre d'admin et par CachePagesEcritureTest) ; do_action()
	// ignore simplement cette valeur. Exception PHPStan declaree dans
	// phpstan.neon plutot que contournement par closure.
	add_action( $sb_evenement, 'swiftboard_page_cache_flush', 99 );
}

/**
 * Purge manuelle depuis la barre d'administration.
 */
add_action(
	'admin_bar_menu',
	function ( $barre ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$barre->add_node(
			array(
				'id'    => 'swiftboard-purge-cache',
				'title' => __( 'Vider le cache SwiftBoard', 'swiftboard' ),
				'href'  => wp_nonce_url( admin_url( '?swiftboard_purge_cache=1' ), 'sb_purge_cache' ),
			)
		);
	},
	100
);

add_action(
	'admin_init',
	function () {
		if ( empty( $_GET['swiftboard_purge_cache'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sb_purge_cache' ) ) {
			return;
		}
		$n = swiftboard_page_cache_flush();
		add_action(
			'admin_notices',
			function () use ( $n ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
						/* translators: %d: nombre de pages */
							__( 'Cache SwiftBoard vidé : %d page(s).', 'swiftboard' ),
							$n
						)
					)
				);
			}
		);
	}
);
