<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Legacy social authentication disabled.
 *
 * The former endpoint accepted a client-supplied email and email_verified flag
 * without cryptographic provider proof. Authentication is now handled only by
 * inc/oauth-callback.php with provider state and stable provider subjects.
 *
 * @package SwiftBoard
 * @since 11.0.4
 */

/**
 * Compatibility guard for code that still calls the former handler.
 *
 * @param WP_REST_Request|null $request Request, unused.
 * @return WP_Error
 */
function swiftboard_rest_handle_social_auth( $request = null ): WP_Error {
	return new WP_Error(
		'legacy_social_auth_disabled',
		'Ce flux d’authentification a été désactivé. Utilisez le fournisseur OAuth officiel.',
		array( 'status' => 410 )
	);
}
