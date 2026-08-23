<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Mesure des Core Web Vitals en conditions réelles (RUM).
 *
 * EXI-PERF-04.
 *
 * POURQUOI DU RUM ALORS QUE LIGHTHOUSE EXISTE
 * -------------------------------------------
 * Lighthouse mesure en laboratoire : un appareil, un réseau simulé, une page
 * froide. Le RUM (Real User Monitoring) mesure ce que vivent les VRAIS
 * visiteurs, sur leurs vrais appareils et leurs vrais réseaux. Les deux
 * divergent régulièrement, et c'est le RUM que Google utilise pour le
 * classement (via le Chrome UX Report).
 *
 * L'INP en particulier ne se mesure pas correctement en laboratoire : il
 * dépend d'interactions réelles de l'utilisateur.
 *
 * CHOIX D'IMPLÉMENTATION
 * ----------------------
 * - Bibliothèque servie EN LOCAL, jamais depuis un CDN : un CDN obligerait à
 *   élargir `script-src` dans la CSP (cf. EXI-PERF-04, point de vigilance).
 * - Aucune donnée personnelle collectée : ni IP, ni identifiant, ni URL
 *   complète avec paramètres. Seulement la métrique, sa valeur, le gabarit
 *   de page et le type d'appareil.
 * - Échantillonnage configurable : inutile de tracer 100 % du trafic pour
 *   obtenir un P75 fiable, et cela évite de charger la base.
 * - Stockage en option WordPress agrégée, pas une ligne par visite : une table
 *   qui grossit sans limite est un problème de production.
 *
 * @package SwiftBoard
 */
/**
 * Taux d'échantillonnage (0 = désactivé, 100 = tous les visiteurs).
 */
if ( ! defined( 'SWIFTBOARD_RUM_SAMPLE_RATE' ) ) {
	define( 'SWIFTBOARD_RUM_SAMPLE_RATE', 10 );
}

/**
 * Le RUM est-il actif ?
 *
 * @return bool
 */
function swiftboard_rum_enabled() {
	// SWIFTBOARD_RUM_SAMPLE_RATE defaults to 10 but can be overridden to 0 in wp-config.php
	$rate   = constant( 'SWIFTBOARD_RUM_SAMPLE_RATE' );
	$actif  = is_numeric( $rate ) && (int) $rate > 0;
	return (bool) apply_filters( 'swiftboard_rum_enabled', $actif );
}

// ============================================================================
// 1. COLLECTE CÔTÉ CLIENT
// ============================================================================
/**
 * Injecte le collecteur de métriques.
 *
 * Volontairement minimal et sans dépendance : les API PerformanceObserver
 * suffisent pour LCP, CLS et INP. Charger une bibliothèque de 5 Ko pour
 * mesurer la performance serait contre-productif.
 *
 * @return void
 */
function swiftboard_rum_inject() {
	if ( ! swiftboard_rum_enabled() || is_admin() ) {
		return;
	}

	// EXI-QUAL-06 : le script vit dans assets/js/web-vitals.js. Il etait
	// auparavant injecte inline (wp_add_inline_script), ce qui imposait
	// 'unsafe-inline' dans la CSP et bloquait le passage en enforce.
	wp_enqueue_script(
		'swiftboard-web-vitals',
		SWIFTBOARD_ASSETS . '/js/web-vitals.js',
		array(),
		SWIFTBOARD_VERSION,
		true
	);
	// Configuration par attributs data-* : wp_localize_script() emettrait un
	// <script> inline, incompatible avec la CSP en enforce.
	add_action(
		'wp_footer',
		function () {
			printf(
				'<div id="sb-rum-config" hidden data-endpoint="%s" data-rate="%d" data-template="%s"></div>',
				esc_attr( esc_url_raw( rest_url( 'swiftboard/v1/vitals' ) ) ),
				(int) SWIFTBOARD_RUM_SAMPLE_RATE,
				esc_attr( swiftboard_rum_current_template() )
			);
		},
		5
	);
}
add_action( 'wp_enqueue_scripts', 'swiftboard_rum_inject', 120 );

/**
 * Gabarit de la page courante, pour agréger par type d'écran.
 *
 * @return string
 */
function swiftboard_rum_current_template() {
	if ( is_front_page() || is_home() ) {
		return 'accueil';
	}
	if ( function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic() ) {
		return 'sujet';
	}
	if ( function_exists( 'bbp_is_single_forum' ) && bbp_is_single_forum() ) {
		return 'forum';
	}
	if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		return 'bbpress';
	}
	if ( is_search() ) {
		return 'recherche';
	}
	if ( is_singular() ) {
		return 'article';
	}
	return 'autre';
}

// ============================================================================
// 2. ENDPOINT DE COLLECTE
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		if ( ! swiftboard_rum_enabled() ) {
			return;
		}

		register_rest_route(
			'swiftboard/v1',
			'/vitals',
			array(
				'methods'             => 'POST',
				// Ouvert : la mesure vient de visiteurs anonymes par nature. Aucune
				// donnee personnelle n'est acceptee (cf. sanitisation ci-dessous), et
				// le volume est borne par le rate limiting global de security.php.
				'permission_callback' => 'swiftboard_rest_public_permission',
				'callback'            => 'swiftboard_rum_collect',
			)
		);
	}
);

/**
 * Enregistre une mesure.
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête.
 * @return WP_REST_Response
 */
function swiftboard_rum_collect( WP_REST_Request $req ) {
	$data = $req->get_json_params();
	if ( ! is_array( $data ) ) {
		return new WP_REST_Response( null, 204 );
	}

	$template = sanitize_key( $data['template'] ?? 'autre' );
	$device   = in_array( ( $data['device'] ?? '' ), array( 'mobile', 'desktop' ), true )
		? $data['device'] : 'inconnu';

	$stats = get_option( 'swiftboard_rum_stats', array() );
	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	foreach ( array( 'lcp', 'cls', 'inp', 'ttfb' ) as $metrique ) {
		if ( ! isset( $data[ $metrique ] ) || ! is_numeric( $data[ $metrique ] ) ) {
			continue;
		}

		// Bornes de vraisemblance : ecarte les valeurs aberrantes (onglet en
		// arriere-plan, horloge decalee) qui fausseraient le P75.
		$valeur = (float) $data[ $metrique ];
		$max    = ( 'cls' === $metrique ) ? 10 : 120000;
		if ( $valeur < 0 || $valeur > $max ) {
			continue;
		}

		$cle = $template . '|' . $device . '|' . $metrique;
		if ( ! isset( $stats[ $cle ] ) ) {
			$stats[ $cle ] = array();
		}

		$stats[ $cle ][] = ( 'cls' === $metrique )
			? round( $valeur, 3 )
			: (int) round( $valeur );

		// Fenetre glissante : on garde les 500 dernieres mesures par cle.
		// Suffisant pour un P75 stable, et borne la taille de l'option.
		if ( count( $stats[ $cle ] ) > 500 ) {
			$stats[ $cle ] = array_slice( $stats[ $cle ], -500 );
		}
	}

	update_option( 'swiftboard_rum_stats', $stats, false );

	// 204 : pas de corps de reponse a renvoyer a un beacon.
	return new WP_REST_Response( null, 204 );
}

// ============================================================================
// 3. LECTURE DES RÉSULTATS
// ============================================================================
/**
 * P75 par gabarit et par appareil.
 *
 * Le P75 est la mesure retenue par Google : il représente l'expérience des
 * 75 % de visiteurs les mieux servis, donc ignore les cas extrêmes tout en
 * restant exigeant. Une moyenne masquerait les mauvaises expériences.
 *
 * @return list<array<string, mixed>> Une entree par metrique (nom, p75, echantillon).
 */
function swiftboard_rum_get_p75() {
	$stats = get_option( 'swiftboard_rum_stats', array() );
	if ( ! is_array( $stats ) || ! $stats ) {
		return array();
	}

	$seuils = array(
		'lcp'  => array(
			'bon'   => 2500,
			'moyen' => 4000,
		),
		'cls'  => array(
			'bon'   => 0.1,
			'moyen' => 0.25,
		),
		'inp'  => array(
			'bon'   => 200,
			'moyen' => 500,
		),
		'ttfb' => array(
			'bon'   => 800,
			'moyen' => 1800,
		),
	);

	$out = array();
	foreach ( $stats as $cle => $valeurs ) {
		if ( ! is_array( $valeurs ) || ! $valeurs ) {
			continue;
		}
		list($template, $device, $metrique) = array_pad( explode( '|', $cle ), 3, '' );

		sort( $valeurs );
		$index = (int) floor( count( $valeurs ) * 0.75 );
		$index = min( $index, count( $valeurs ) - 1 );
		$p75   = $valeurs[ $index ];

		$verdict = 'mauvais';
		if ( isset( $seuils[ $metrique ] ) ) {
			if ( $p75 <= $seuils[ $metrique ]['bon'] ) {
				$verdict = 'bon';
			} elseif ( $p75 <= $seuils[ $metrique ]['moyen'] ) {
				$verdict = 'moyen';
			}
		}

		$out[] = array(
			'template' => $template,
			'device'   => $device,
			'metrique' => $metrique,
			'p75'      => $p75,
			'mesures'  => count( $valeurs ),
			'verdict'  => $verdict,
		);
	}

	return $out;
}


// V2 restauration - Agrégateur p75 branché
add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'swiftboard_rum_get_p75' ) ) {
			return;
		}
		register_rest_route(
			'swiftboard/v1',
			'/vitals/stats',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
						return current_user_can( 'manage_options' ); },
				'callback'            => function () {
					$stats = swiftboard_rum_get_p75();
					return new WP_REST_Response(
						array(
							'p75'   => $stats,
							'count' => count( $stats ),
						),
						200
					);
				},
			)
		);
	}
);
