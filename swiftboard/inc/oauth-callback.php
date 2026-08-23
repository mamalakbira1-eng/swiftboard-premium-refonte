<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — OAuth Callback Handler
 *
 * Real OAuth flow for Google, GitHub, Facebook.
 * Handles the callback after user authorizes on the provider.
 *
 * @package SwiftBoard
 * @since 7.2.0
 */
// ============================================================================
// 1. OAUTH CALLBACK ROUTE
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/auth/callback',
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_oauth_callback',
				'permission_callback' => 'swiftboard_rest_public_permission',
			)
		);

			// Google challenge : state/nonce lié au navigateur, consommable une seule fois.
			register_rest_route(
				'swiftboard/v1',
				'/auth/google-challenge',
				array(
					'methods'             => 'GET',
					'callback'            => 'swiftboard_google_challenge',
					'permission_callback' => 'swiftboard_rest_public_permission',
				)
			);

			// Google token verification (client sends ID token + state/nonce)
			register_rest_route(
				'swiftboard/v1',
				'/auth/google-verify',
			array(
				'methods'             => 'POST',
				'callback'            => 'swiftboard_google_verify',
				'permission_callback' => 'swiftboard_rest_public_permission',
			)
		);
	}
);

// ============================================================================
// 2. GOOGLE — Token verification (Google Identity Services)
// ============================================================================
/**
 * Creates a one-time browser-bound state for Google Identity Services.
 *
 * @return WP_REST_Response
 */
function swiftboard_google_challenge(): WP_REST_Response {
	$state = bin2hex( random_bytes( 32 ) );
	set_transient( 'sb_google_state_' . hash( 'sha256', $state ), true, 10 * MINUTE_IN_SECONDS );
	setcookie(
		'sb_google_state',
		$state,
		array(
			'expires'  => time() + 10 * MINUTE_IN_SECONDS,
			'path'     => COOKIEPATH ?: '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
	return rest_ensure_response( array( 'state' => $state ) );
}

/**
 * Verifies a Google ID token sent by the client.
 * Google Identity Services (GIS) handles the OAuth flow client-side.
 * The server only verifies the token.
 */
function swiftboard_google_verify( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$id_token = sanitize_text_field( $request->get_param( 'id_token' ) );
	$state    = sanitize_text_field( $request->get_param( 'state' ) );
	$cookie   = isset( $_COOKIE['sb_google_state'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['sb_google_state'] ) ) : '';
	if ( empty( $id_token ) || empty( $state ) || empty( $cookie ) || ! hash_equals( $state, $cookie ) ) {
		return new WP_Error( 'invalid_oauth_state', 'État OAuth Google invalide ou expiré.', array( 'status' => 403 ) );
	}
	$state_key = 'sb_google_state_' . hash( 'sha256', $state );
	if ( ! get_transient( $state_key ) ) {
		return new WP_Error( 'invalid_oauth_state', 'État OAuth Google invalide ou déjà utilisé.', array( 'status' => 403 ) );
	}
	delete_transient( $state_key );
	setcookie( 'sb_google_state', '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
	if ( empty( $id_token ) ) {
		return new WP_Error( 'missing_token', 'ID token manquant.', array( 'status' => 400 ) );
	}

	$settings  = get_option( 'swiftboard_social_settings', array() );
	$client_id = $settings['google_client_id'] ?? '';
	if ( empty( $client_id ) ) {
		return new WP_Error( 'not_configured', 'Google Client ID non configuré.', array( 'status' => 500 ) );
	}

	// Verify token with Google
	$response = wp_remote_get(
		'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode( $id_token ),
		array(
			'timeout' => 10,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'verify_failed', 'Impossible de vérifier le token Google.', array( 'status' => 500 ) );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! $body || empty( $body['email'] ) ) {
		return new WP_Error( 'invalid_token', 'Token Google invalide.', array( 'status' => 403 ) );
	}

	// Vérifier l’émetteur, l’audience, l’expiration, le sujet et le nonce.
	if ( ! in_array( $body['iss'] ?? '', array( 'accounts.google.com', 'https://accounts.google.com' ), true )
		|| ( $body['aud'] ?? '' ) !== $client_id
		|| empty( $body['sub'] )
		|| empty( $body['exp'] )
		|| (int) $body['exp'] < time()
		|| ( $body['nonce'] ?? '' ) !== $state
		|| ( $body['email_verified'] ?? 'false' ) !== 'true' ) {
		return new WP_Error( 'invalid_google_claims', 'Claims Google invalides.', array( 'status' => 403 ) );
	}

	// Create or login user
	return swiftboard_oauth_create_user(
		array(
			'email'          => $body['email'],
			'name'           => $body['name'] ?? '',
			'avatar_url'     => $body['picture'] ?? '',
			'email_verified'  => true,
			'provider'        => 'google',
			'provider_subject' => sanitize_text_field( $body['sub'] ),
		)
	);
}

// ============================================================================
// 3. OAUTH STATE HELPERS
// ============================================================================
/**
 * Bind provider state to the browser with a short-lived SameSite cookie.
 *
 * @param string $state State token.
 * @return void
 */
function swiftboard_oauth_set_state_cookie( string $state ): void {
	setcookie(
		'sb_oauth_state',
		$state,
		array(
			'expires'  => time() + 10 * MINUTE_IN_SECONDS,
			'path'     => COOKIEPATH ?: '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

// ============================================================================
// 4. GITHUB — Server-side OAuth flow
// ==========================================================================
/**
 * Step 1: Redirect to GitHub for authorization.
 * Called by JS: window.location.href = '/wp-json/swiftboard/v1/auth/github-login'
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/auth/github-login',
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_github_login',
				'permission_callback' => 'swiftboard_rest_public_permission',
			)
		);

		register_rest_route(
			'swiftboard/v1',
			'/auth/facebook-login',
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_facebook_login',
				'permission_callback' => 'swiftboard_rest_public_permission',
			)
		);
	}
);

/**
 * @return WP_Error|void Returns WP_Error on failure, void on successful redirect.
 */
function swiftboard_github_login() {
	$settings  = get_option( 'swiftboard_social_settings', array() );
	$client_id = $settings['github_client_id'] ?? '';
	if ( empty( $client_id ) ) {
		return new WP_Error( 'not_configured', 'GitHub Client ID non configuré.', array( 'status' => 500 ) );
	}

	$redirect_uri = home_url( '/wp-json/swiftboard/v1/auth/callback' );
	$state        = bin2hex( random_bytes( 32 ) );
	set_transient( 'sb_oauth_state_github_' . hash( 'sha256', $state ), true, 10 * MINUTE_IN_SECONDS );
	swiftboard_oauth_set_state_cookie( $state );

	$auth_url = 'https://github.com/login/oauth/authorize?'
		. http_build_query(
			array(
				'client_id'    => $client_id,
				'redirect_uri' => $redirect_uri,
				'scope'        => 'read:user user:email',
				'state'        => $state,
			)
		);

	wp_redirect( $auth_url );
	exit;
}

// ============================================================================
// 4. FACEBOOK — Redirect to Facebook for authorization
// ============================================================================
/**
 * @return WP_Error|void Returns WP_Error on failure, void on successful redirect.
 */
function swiftboard_facebook_login() {
	$settings = get_option( 'swiftboard_social_settings', array() );
	$app_id   = $settings['facebook_app_id'] ?? '';
	if ( empty( $app_id ) ) {
		return new WP_Error( 'not_configured', 'Facebook App ID non configuré.', array( 'status' => 500 ) );
	}

	$redirect_uri = home_url( '/wp-json/swiftboard/v1/auth/callback' );
	$state        = bin2hex( random_bytes( 32 ) );
	set_transient( 'sb_oauth_state_facebook_' . hash( 'sha256', $state ), true, 10 * MINUTE_IN_SECONDS );
	swiftboard_oauth_set_state_cookie( $state );

	$auth_url = 'https://www.facebook.com/v18.0/dialog/oauth?'
		. http_build_query(
			array(
				'client_id'     => $app_id,
				'redirect_uri'  => $redirect_uri,
				'scope'         => 'email,public_profile',
				'state'         => $state,
				'response_type' => 'code',
			)
		);

	wp_redirect( $auth_url );
	exit;
}

// ============================================================================
// 5. OAUTH CALLBACK — Handles redirect from all providers
// ============================================================================
/** @return WP_Error|void */
function swiftboard_oauth_callback( WP_REST_Request $request ) {
	$code  = sanitize_text_field( $request->get_param( 'code' ) );
	$state = sanitize_text_field( $request->get_param( 'state' ) );
	$error = sanitize_text_field( $request->get_param( 'error' ) );

	if ( $error ) {
		wp_redirect( home_url( '/?oauth_error=' . urlencode( $error ) ) );
		exit;
	}

	if ( empty( $code ) ) {
		return new WP_Error( 'missing_code', 'Code OAuth manquant.', array( 'status' => 400 ) );
	}

	// L’état doit provenir du même navigateur et ne peut être réutilisé.
	$browser_state = isset( $_COOKIE['sb_oauth_state'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['sb_oauth_state'] ) ) : '';
	if ( empty( $state ) || empty( $browser_state ) || ! hash_equals( $state, $browser_state ) ) {
		return new WP_Error( 'invalid_state', 'État OAuth invalide.', array( 'status' => 403 ) );
	}

	// Detect provider from the one-time server state.
	$provider = '';
	$state_hash = hash( 'sha256', $state );
	if ( get_transient( 'sb_oauth_state_github_' . $state_hash ) ) {
		$provider = 'github';
		delete_transient( 'sb_oauth_state_github_' . $state_hash );
	} elseif ( get_transient( 'sb_oauth_state_facebook_' . $state_hash ) ) {
		$provider = 'facebook';
		delete_transient( 'sb_oauth_state_facebook_' . $state_hash );
	} else {
		return new WP_Error( 'invalid_state', 'État OAuth invalide ou déjà utilisé.', array( 'status' => 403 ) );
	}
	setcookie( 'sb_oauth_state', '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );

	$settings = get_option( 'swiftboard_social_settings', array() );

	if ( $provider === 'github' ) {
		return swiftboard_github_callback( $code, $settings );
	}
	// $provider ne peut être que 'facebook' ici (les autres cas retournent plus haut)
	return swiftboard_facebook_callback( $code, $settings );
}

// ============================================================================
// 6. GITHUB CALLBACK — Exchange code for token, get user info
// ============================================================================
/** @return WP_REST_Response|never */
/**
 * @param array<string, mixed> $settings OAuth settings from wp_options.
 */
function swiftboard_github_callback( string $code, array $settings ): never {
	$client_id     = $settings['github_client_id'] ?? '';
	$client_secret = $settings['github_client_secret'] ?? '';

	// Exchange code for access token
	$token_response = wp_remote_post(
		'https://github.com/login/oauth/access_token',
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
			'body'    => array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'code'          => $code,
			),
		)
	);

	if ( is_wp_error( $token_response ) ) {
		wp_redirect( home_url( '/?oauth_error=token_exchange_failed' ) );
		exit;
	}

	$token_body   = json_decode( wp_remote_retrieve_body( $token_response ), true );
	$access_token = $token_body['access_token'] ?? '';

	if ( empty( $access_token ) ) {
		wp_redirect( home_url( '/?oauth_error=no_access_token' ) );
		exit;
	}

	// Get user info
	$user_response = wp_remote_get(
		'https://api.github.com/user',
		array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'User-Agent'    => 'SwiftBoard',
			),
		)
	);

	if ( is_wp_error( $user_response ) ) {
		wp_redirect( home_url( '/?oauth_error=user_info_failed' ) );
		exit;
	}

	$user_body = json_decode( wp_remote_retrieve_body( $user_response ), true );

	// Get email (might be private)
	$email = $user_body['email'] ?? '';
	if ( empty( $email ) ) {
		$emails_response = wp_remote_get(
			'https://api.github.com/user/emails',
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'User-Agent'    => 'SwiftBoard',
				),
			)
		);
		if ( ! is_wp_error( $emails_response ) ) {
			$emails = json_decode( wp_remote_retrieve_body( $emails_response ), true );
			if ( is_array( $emails ) ) {
				foreach ( $emails as $e ) {
					if ( ! empty( $e['primary'] ) ) {
						$email = $e['email'];
						break;
					}
				}
				if ( empty( $email ) && ! empty( $emails[0]['email'] ) ) {
					$email = $emails[0]['email'];
				}
			}
		}
	}

	if ( empty( $email ) ) {
		wp_redirect( home_url( '/?oauth_error=no_email' ) );
		exit;
	}

	$result = swiftboard_oauth_create_user(
		array(
			'email'          => $email,
			'name'           => $user_body['name'] ?? $user_body['login'] ?? '',
			'avatar_url'     => $user_body['avatar_url'] ?? '',
				'email_verified'   => true,
				'provider'         => 'github',
				'provider_subject' => (string) ( $user_body['id'] ?? '' ),
		)
	);

	if ( is_wp_error( $result ) ) {
		wp_redirect( home_url( '/?oauth_error=' . urlencode( $result->get_error_message() ) ) );
		exit;
	}

	$data = $result->get_data();
	wp_redirect( $data['redirect'] ?? home_url( '/forums/' ) );
	exit;
}

// ============================================================================
// 7. FACEBOOK CALLBACK — Exchange code for token, get user info
// ============================================================================
/** @return WP_REST_Response|never */
/**
 * @param array<string, mixed> $settings OAuth settings from wp_options.
 */
function swiftboard_facebook_callback( string $code, array $settings ): never {
	$app_id       = $settings['facebook_app_id'] ?? '';
	$app_secret   = $settings['facebook_app_secret'] ?? '';
	$redirect_uri = home_url( '/wp-json/swiftboard/v1/auth/callback' );

	// Exchange code for access token
	$token_response = wp_remote_get(
		'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query(
			array(
				'client_id'     => $app_id,
				'client_secret' => $app_secret,
				'redirect_uri'  => $redirect_uri,
				'code'          => $code,
			)
		),
		array( 'timeout' => 10 )
	);

	if ( is_wp_error( $token_response ) ) {
		wp_redirect( home_url( '/?oauth_error=token_exchange_failed' ) );
		exit;
	}

	$token_body   = json_decode( wp_remote_retrieve_body( $token_response ), true );
	$access_token = $token_body['access_token'] ?? '';

	if ( empty( $access_token ) ) {
		wp_redirect( home_url( '/?oauth_error=no_access_token' ) );
		exit;
	}

	// Get user info
	$user_response = wp_remote_get(
		'https://graph.facebook.com/v18.0/me?' . http_build_query(
			array(
				'fields'       => 'id,name,email,picture.width(200).height(200)',
				'access_token' => $access_token,
			)
		),
		array( 'timeout' => 10 )
	);

	if ( is_wp_error( $user_response ) ) {
		wp_redirect( home_url( '/?oauth_error=user_info_failed' ) );
		exit;
	}

	$user_body = json_decode( wp_remote_retrieve_body( $user_response ), true );

	$email = $user_body['email'] ?? '';
	if ( empty( $email ) ) {
		wp_redirect( home_url( '/?oauth_error=no_email' ) );
		exit;
	}

	$avatar_url = $user_body['picture']['data']['url'] ?? '';

	$result = swiftboard_oauth_create_user(
		array(
			'email'          => $email,
			'name'           => $user_body['name'] ?? '',
			'avatar_url'     => $avatar_url,
				'email_verified'   => true,
				'provider'         => 'facebook',
				'provider_subject' => (string) ( $user_body['id'] ?? '' ),
		)
	);

	if ( is_wp_error( $result ) ) {
		wp_redirect( home_url( '/?oauth_error=' . urlencode( $result->get_error_message() ) ) );
		exit;
	}

	$data = $result->get_data();
	wp_redirect( $data['redirect'] ?? home_url( '/forums/' ) );
	exit;
}

// ============================================================================
// 8. SHARED — Create or login user from OAuth data
// ============================================================================
/**
 * @param array{email: string, name: string, avatar_url: string, email_verified: bool, provider: string, provider_subject?: string} $data
 */
function swiftboard_oauth_create_user( array $data ): WP_REST_Response|WP_Error {
	$email = sanitize_email( $data['email'] );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', 'Email invalide.' );
	}

	$provider = sanitize_key( $data['provider'] ?? '' );
	$subject   = sanitize_text_field( (string) ( $data['provider_subject'] ?? '' ) );
	if ( empty( $provider ) || empty( $subject ) ) {
		return new WP_Error( 'missing_provider_identity', 'Identité fournisseur manquante.', array( 'status' => 403 ) );
	}

	$user = get_user_by( 'email', $email );
	if ( $user ) {
		$user_id         = (int) $user->ID;
		$stored_provider = sanitize_key( (string) get_user_meta( $user_id, 'sb_auth_provider', true ) );
		$stored_subject  = sanitize_text_field( (string) get_user_meta( $user_id, 'sb_auth_provider_subject', true ) );
		if ( empty( $stored_provider ) || empty( $stored_subject ) ) {
			return new WP_Error( 'provider_link_required', 'Ce compte doit être lié explicitement à ce fournisseur depuis une session authentifiée.', array( 'status' => 409 ) );
		}
		if ( $stored_provider !== $provider ) {
			return new WP_Error( 'provider_link_required', 'Ce compte est déjà lié à un autre fournisseur. Liez le nouveau fournisseur depuis votre compte.', array( 'status' => 409 ) );
		}
		if ( ! hash_equals( $stored_subject, $subject ) ) {
			return new WP_Error( 'provider_identity_mismatch', 'Identité fournisseur différente.', array( 'status' => 403 ) );
		}
	} else {
		$login = sanitize_user( current( explode( '@', $email ) ), true );
		if ( username_exists( $login ) ) {
			$login .= '_' . substr( wp_hash( $email ), 0, 4 );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $data['name'] ?: $login,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( function_exists( 'swiftboard_set_user_grade' ) ) {
			swiftboard_set_user_grade( $user_id, 'rookie' );
		}
	}

	// Update avatar from provider if available
	if ( ! empty( $data['avatar_url'] ) ) {
		update_user_meta( $user_id, 'sb_oauth_avatar', $data['avatar_url'] );
	}

	update_user_meta( $user_id, 'sb_auth_provider', $provider );
	update_user_meta( $user_id, 'sb_auth_provider_subject', $subject );

	// Login
	wp_set_auth_cookie( $user_id, true );
	wp_set_current_user( $user_id );

	return rest_ensure_response(
		array(
			'success'  => true,
			'user_id'  => $user_id,
			'provider' => $data['provider'],
			'redirect' => home_url( '/forums/' ),
		)
	);
}
