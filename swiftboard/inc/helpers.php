<?php
/**
 * SwiftBoard — Helper Functions
 *
 * Utility functions used across the theme.
 * Extracted from functions.php for better code organization.
 *
 * @package SwiftBoard
 * @since 7.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function swiftboard_get_option( string $key, mixed $default = '' ): mixed {
	$opts = get_option( 'swiftboard_options', array() );
	return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
}

/**
 * Affiche le logo du site (texte ou image).
 *
 * @return void
 */
function swiftboard_logo() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	$name    = get_bloginfo( 'name' );
	$initial = mb_substr( $name, 0, 1 );
	echo '<span class="site-logo">' . esc_html( $initial ) . '</span>';
	echo '<span class="site-title"><a href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . esc_html( $name ) . '</a></span>';
}

/**
 * Avatar SVG inline si pas de Gravatar (évite les requêtes externes).
 *
 * @param mixed $user_id
 * @param int   $size
 * @return string
 */
function swiftboard_get_avatar( $user_id, $size = 32 ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';
	}

	// LOT 11 : vérifier si l'utilisateur a un avatar assigné
	$avatar_id     = (int) get_user_meta( $user_id, 'swiftboard_avatar_id', true );
	$custom_avatar = get_user_meta( $user_id, 'swiftboard_custom_avatar', true );

	if ( $custom_avatar ) {
		$upload_dir = wp_upload_dir();
		$avatar_url = $upload_dir['baseurl'] . '/' . $custom_avatar;
		return '<img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $user->display_name ) . '" '
			. 'width="' . intval( $size ) . '" height="' . intval( $size ) . '" '
			. 'style="border-radius:50%;object-fit:cover;" '
			. 'class="sb-avatar-custom" loading="lazy">';
	}

	if ( $avatar_id >= 1 && $avatar_id <= 15 ) {
		$avatar_file = 'avatar-' . str_pad( (string) $avatar_id, 2, '0', STR_PAD_LEFT ) . '.webp';
		$avatar_url  = SWIFTBOARD_URI . '/assets/img/avatars/' . $avatar_file;
		return '<img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $user->display_name ) . '" '
			. 'width="' . intval( $size ) . '" height="' . intval( $size ) . '" '
			. 'style="border-radius:50%;object-fit:cover;" '
			. 'class="sb-avatar-themed" loading="lazy">';
	}

	// Fallback Reddit-like (cercle + initiale + couleur Customizer)
	if ( function_exists( 'swiftboard_avatar_reddit_fallback' ) ) {
		return swiftboard_avatar_reddit_fallback( $user_id, $size );
	}

	// Fallback ultime (ancien système avatar-mock)
	$initial = mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) );
	$colors  = array( '#006cbd', '#0090e0', '#46a609', '#ec4899', '#d97706', '#dc2626' );
	$color   = $colors[ abs( $user_id ) % count( $colors ) ];
	return '<span class="avatar-mock" role="img" aria-label="' . esc_attr( $user->display_name )
		. '" style="background:' . esc_attr( $color ) . ';width:' . intval( $size ) . 'px;height:' . intval( $size ) . 'px">'
		. esc_html( $initial ) . '</span>';
}

/**
 * Compte les votes (mock — à brancher sur un plugin de vote si besoin).
 *
 * @param mixed $post_id
 * @return mixed
 */
function swiftboard_get_vote_count( $post_id ) {
	$filtered = apply_filters( 'swiftboard_get_vote_count', null, $post_id );
	if ( $filtered !== null ) {
		return (int) $filtered;
	}
	$votes = (int) get_post_meta( $post_id, '_swiftboard_votes', true );
	return $votes ?: 0;
}

/**
 * Formatage compact des nombres (1.2k, 12.5k, etc.)
 *
 * @param mixed $n
 * @return string
 */
function swiftboard_format_count( $n ) {
	$n = (int) $n;
	// v4.6.1 : les votes négatifs sont affichés comme 0 (anti-troll, test PHPUnit)
	if ( $n < 0 ) {
		return '0';
	}
	if ( $n >= 1000000 ) {
		return round( $n / 1000000, 1 ) . 'M';
	}
	if ( $n >= 1000 ) {
		return round( $n / 1000, 1 ) . 'k';
	}
	return (string) $n;
}

/**
 * Temps relatif en français ("il y a 2 h", "il y a 3 j").
 *
 * @param mixed $date
 * @return string
 */
function swiftboard_time_ago( $date ) {
	$now  = time();
	$time = is_numeric( $date ) ? (int) $date : strtotime( $date );
	if ( $time === false || $time < 0 ) {
		return __( 'Date invalide', 'swiftboard' );
	}
	$diff = $now - $time;

	if ( $diff < 60 ) {
		return __( 'à l\'instant', 'swiftboard' );
	}
	if ( $diff < 3600 ) {
		$m = (int) floor( $diff / 60 );
		return sprintf( _n( 'il y a %d min', 'il y a %d min', $m, 'swiftboard' ), $m );
	}
	if ( $diff < 86400 ) {
		$h = (int) floor( $diff / 3600 );
		return sprintf( _n( 'il y a %d h', 'il y a %d h', $h, 'swiftboard' ), $h );
	}
	if ( $diff < 604800 ) {
		$d = (int) floor( $diff / 86400 );
		return sprintf( _n( 'il y a %d j', 'il y a %d j', $d, 'swiftboard' ), $d );
	}
	return date_i18n( get_option( 'date_format' ), $time );
}

/**
 * Rendu d'un bouton de vote.
 *
 * @param mixed $post_id
 * @return mixed
 */
function swiftboard_vote_html( $post_id ) {
	$count     = swiftboard_get_vote_count( $post_id );
	$count_fmt = swiftboard_format_count( $count );

	// Construction par concatenation plutot que par bufferisation.
	// Cette fonction est appelee DEPUIS les templates (index.php, single.php),
	// donc pendant que le page-cache bufferise deja la sortie. Un ob_start()
	// atteint depuis un callback de buffer leve une erreur fatale PHP et
	// renvoie une page vide en HTTP 200 — defaut deja rencontre sur ce theme.
	//
	// Les fleches sont des traces SVG et non des caracteres ▲▼ : ces derniers
	// dependent d'une police installee cote client et s'affichaient en carre
	// vide sur les systemes sans jeu de symboles complet.
	$fleche_haut = '<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>';
	$fleche_bas  = '<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>';

	return sprintf(
		'<div class="vote-column" data-post-id="%1$s">'
			. '<button class="vote-btn upvote" aria-label="%2$s" aria-pressed="false">%3$s</button>'
			. '<span class="vote-count" aria-live="polite" aria-atomic="true">%4$s</span>'
			. '<button class="vote-btn downvote" aria-label="%5$s" aria-pressed="false">%6$s</button>'
			. '</div>',
		esc_attr( (string) $post_id ),
		esc_attr__( 'Upvoter', 'swiftboard' ),
		$fleche_haut,
		esc_html( $count_fmt ),
		esc_attr__( 'Downvoter', 'swiftboard' ),
		$fleche_bas
	);
}
