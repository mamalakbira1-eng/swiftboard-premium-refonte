<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Branding de la page de connexion WordPress
 *
 * EXI-AUTH-01 / EXI-AUTH-02 (cahier 13).
 *
 * /wp-login.php est un point d'entree du COEUR WordPress : il ne charge ni
 * header.php, ni main.css, ni les variables CSS du theme. Le personnaliser
 * passe obligatoirement par les hooks dedies ci-dessous.
 *
 * Note : bbpress/form-user-login.php existe deja, mais il n'est utilise que
 * par le shortcode bbPress — jamais par /wp-login.php.
 *
 * @package SwiftBoard
 * @since 5.0.0
 */
// ============================================================================
// 1. FEUILLE DE STYLE DEDIEE
// ============================================================================
add_action(
	'login_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'swiftboard-login',
			SWIFTBOARD_ASSETS . '/css/login.css',
			array(),
			SWIFTBOARD_VERSION . '-a11y1'
		);
		// Loader externe et CSP-safe : pose data-theme avant le rendu du formulaire.
		wp_enqueue_script(
			'swiftboard-login-theme',
			SWIFTBOARD_ASSETS . '/js/anti-fouc.js',
			array(),
			SWIFTBOARD_VERSION,
			false
		);
	}
);

// ============================================================================
// 2. LANDMARK PRINCIPAL — wp-login.php ne charge pas header.php du thème.
// ============================================================================
add_action(
	'login_header',
	function () {
		echo '<main id="sb-login-main" class="sb-login-main" role="main" aria-label="' . esc_attr__( 'Authentification', 'swiftboard' ) . '">';
	},
	1
);

add_action(
	'login_footer',
	function () {
		echo '</main>';
	},
	99
);

// ============================================================================
// 3. LOGO : pointe vers l'accueil du site, pas vers wordpress.org
// ============================================================================
add_filter(
	'login_headerurl',
	function () {
		return home_url( '/' );
	}
);

add_filter(
	'login_headertext',
	function () {
		return get_bloginfo( 'name' );
	}
);

// ============================================================================
// 4. MESSAGE D'ACCUEIL CONTEXTUEL (login / register / lostpassword)
// ============================================================================
add_filter(
	'login_message',
	function ( $message ) {
		// WordPress remplit deja $message sur certains ecrans (lostpassword, resetpass).
		// On PREFIXE le branding au lieu de sortir, sinon il disparait sur ces ecrans.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login';

		$texts = array(
			'login'        => __( 'Connectez-vous pour participer aux discussions.', 'swiftboard' ),
			'register'     => __( 'Rejoignez la communauté en quelques secondes.', 'swiftboard' ),
			'lostpassword' => __( 'Indiquez votre e-mail pour réinitialiser votre mot de passe.', 'swiftboard' ),
			'rp'           => __( 'Choisissez un nouveau mot de passe.', 'swiftboard' ),
			'resetpass'    => __( 'Choisissez un nouveau mot de passe.', 'swiftboard' ),
		);

		$out  = '<div class="sb-login-brand">';
		$out .= '<span class="sb-login-logo" aria-hidden="true">'
			. esc_html( mb_substr( get_bloginfo( 'name' ), 0, 1 ) ) . '</span>';
		$out .= '<h1 class="sb-login-sitename">' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
		$out .= '</div>';

		// Notre accroche seulement si WP n'affiche pas deja ses propres consignes
		if ( empty( $message ) && isset( $texts[ $action ] ) ) {
			$out .= '<p class="sb-login-intro">' . esc_html( $texts[ $action ] ) . '</p>';
		}

		return $out . $message;
	}
);

// ============================================================================
// 5. REDIRECTION APRES CONNEXION
// Les membres vont sur leur profil, les admins gardent wp-admin.
// ============================================================================
add_filter(
	'login_redirect',
	function ( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
			return $redirect_to;
		}

		if ( in_array( 'administrator', $user->roles, true ) ) {
			return $redirect_to;
		}

		// Redirection explicite demandee (ex : retour sur un sujet) : on la respecte
		if ( ! empty( $requested_redirect_to ) && $requested_redirect_to !== admin_url() ) {
			return $requested_redirect_to;
		}

		return function_exists( 'bbp_get_user_profile_url' )
		? bbp_get_user_profile_url( $user->ID )
		: home_url( '/' );
	},
	10,
	3
);

// ============================================================================
// 6. MESSAGE D'ERREUR NEUTRE
// Ne pas reveler si c'est l'identifiant ou le mot de passe qui est faux
// (evite l'enumeration de comptes).
// ============================================================================
add_filter(
	'login_errors',
	function ( $errors ) {
		// On ne masque que les erreurs d'identification, pas les autres messages
		// (mot de passe reinitialise, compte cree, etc.)
		if ( is_string( $errors ) && preg_match( '/(password|username|e-?mail|identifiant|utilisateur)/i', $errors ) ) {
			// v5.3.4 — CORRECTIF BUG REEL : le masquage s'appliquait AUSSI sur le
			// formulaire d'inscription. « Cet identifiant est déjà enregistré » ou
			// « e-mail invalide » devenaient « Identifiants incorrects » : un
			// visiteur ne comprenait jamais pourquoi son inscription echouait
			// (prouve en E2E). Le formulaire d'inscription revele de toute facon
			// l'existence d'un compte par nature ; seul l'ecran de CONNEXION doit
			// rester opaque (anti-enumeration).
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
			if ( 'register' === $action || 'lostpassword' === $action || 'rp' === $action || 'resetpass' === $action ) {
				return $errors;
			}
			return __( 'Identifiants incorrects. Réessayez.', 'swiftboard' );
		}
		return $errors;
	}
);

// ============================================================================
// 7. LIEN DE RETOUR AU SITE
// ============================================================================
add_filter(
	'login_site_html_link',
	function () {
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( home_url( '/' ) ),
			esc_html__( '← Retour au forum', 'swiftboard' )
		);
	}
);
