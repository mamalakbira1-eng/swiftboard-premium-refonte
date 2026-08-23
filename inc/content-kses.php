<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — défense XSS en profondeur sur le contenu forum.
 *
 * CDC-PROD-FERME-01 : ne pas se fier uniquement au KSES à l'entrée
 * (unfiltered_html, import, wp-cli). On re-filtre à la sortie.
 *
 * @package SwiftBoard
 */
/**
 * Tags/attributs HTML autorisés dans un message de forum.
 *
 * @return array<string, array<int, string>>
 */
function swiftboard_forum_allowed_html() {
	$allowed = wp_kses_allowed_html( 'post' );

	// Images : nécessaires aux uploads thème + lazy-load.
	$allowed['img'] = array(
		'src'      => true,
		'alt'      => true,
		'title'    => true,
		'class'    => true,
		'id'       => true,
		'width'    => true,
		'height'   => true,
		'loading'  => true,
		'decoding' => true,
		'srcset'   => true,
		'sizes'    => true,
	);

	// Pas d'event handlers nulle part (ceinture).
	foreach ( $allowed as $tag => $attrs ) {
		if ( ! is_array( $attrs ) ) {
			continue;
		}
		foreach ( array_keys( $attrs ) as $attr ) {
			if ( stripos( (string) $attr, 'on' ) === 0 ) {
				unset( $allowed[ $tag ][ $attr ] );
			}
		}
		unset( $allowed[ $tag ]['style'] ); // réduit XSS CSS / expression
	}

	/**
	 * Allowlist HTML forum (sortie).
	 *
	 * @param array $allowed Allowlist wp_kses.
	 */
	return apply_filters( 'swiftboard_forum_allowed_html', $allowed );
}

/**
 * Filtre de sortie topic/reply.
 *
 * @param string $content HTML brut.
 * @return string HTML assaini.
 */
function swiftboard_kses_forum_content( $content ) {
	if ( ! in_array( get_post_type(), array( 'topic', 'reply', 'forum' ), true ) && ! is_bbpress() ) {
		return $content;
	}
	if ( ! is_string( $content ) || $content === '' ) {
		return $content;
	}
	return wp_kses( $content, swiftboard_forum_allowed_html() );
}

// Après pending images (5) et avant headings LLM (20).
add_filter( 'bbp_get_topic_content', 'swiftboard_kses_forum_content', 15 );
add_filter( 'bbp_get_reply_content', 'swiftboard_kses_forum_content', 15 );
// Filet pour tout contenu WP classique éventuel sur le forum.
add_filter( 'the_content', 'swiftboard_kses_forum_content', 15 );

/**
 * Défense en profondeur : filtre aussi le contenu AVANT écriture en base.
 *
 * Le filtrage à la sortie (ci-dessus) reste la protection principale : il
 * couvre le contenu déjà en base avant l'activation du thème, et rejoue le
 * nettoyage à chaque affichage même si la ligne DB a été modifiée par un
 * autre moyen (import, wp-cli, requête SQL directe).
 *
 * Ce filtre complète en assainissant aussi ce qui est réellement écrit,
 * pour les types de contenu du forum (topic, reply) uniquement — le reste
 * du contenu WordPress (pages, articles) garde le comportement natif.
 *
 * @param array $data    Données du post sur le point d'être enregistrées.
 * @param array $postarr Données brutes soumises.
 * @return array
 */
/**
 * @param array<string, mixed> $data
 * @param array<string, mixed> $postarr
 * @return array<string, mixed>
 */
function swiftboard_kses_forum_content_avant_ecriture( $data, $postarr ) {
	$types_forum = array( 'topic', 'reply' );
	if ( ! in_array( $data['post_type'] ?? '', $types_forum, true ) ) {
		return $data;
	}
	if ( isset( $data['post_content'] ) && is_string( $data['post_content'] ) && $data['post_content'] !== '' ) {
		$data['post_content'] = wp_kses( $data['post_content'], swiftboard_forum_allowed_html() );
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'swiftboard_kses_forum_content_avant_ecriture', 10, 2 );
