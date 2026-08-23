<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Compatibilité Monétisation & VIP (WooCommerce / Paid Memberships Pro)
 *
 * @package SwiftBoard
 * @since 6.0.0
 */
/**
 * Vérifie si un membre est VIP (grade VIP ou abonnement WooCommerce actif).
 */
function swiftboard_is_user_vip( int $user_id = 0 ): bool {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return false;
	}
	$grade = get_user_meta( $user_id, 'swiftboard_grade', true );
	if ( $grade === 'vip' ) {
		return true;
	}
	// Hook pour filtres de plugins externes (WooCommerce Memberships / Paid Memberships Pro)
	return (bool) apply_filters( 'swiftboard_user_has_vip_membership', false, $user_id );
}

/**
 * Masque les encarts publicitaires pour les membres VIP.
 */
add_filter(
	'swiftboard_show_advertisement_banner',
	function ( $show ) {
		if ( swiftboard_is_user_vip() ) {
			return false;
		}
		return $show;
	}
);
