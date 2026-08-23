<?php
/**
 * SwiftBoard — Jeu d'icones SVG inline.
 *
 * Remplace les emojis (🏠 🔥 🆕 📝 …) par un trait vectoriel homogene.
 * Les emojis dependent de la police systeme : ils changent d'aspect entre
 * Windows, macOS, Android et Linux, ne suivent pas la couleur du texte et
 * cassent l'alignement vertical. Un SVG inline herite de `currentColor`,
 * s'aligne au pixel et reste identique partout.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retourne le balisage SVG d'une icone.
 *
 * @param string $nom    Identifiant de l'icone.
 * @param int    $taille Cote du carre, en pixels.
 * @return string SVG pret a l'affichage, ou chaine vide si inconnu.
 */
function swiftboard_icon( $nom, $taille = 20 ) {
	$traces = array(
		// Navigation
		'home'      => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
		'popular'   => '<path d="M3 17l6-6 4 4 8-8"/><path d="M21 7v5h-5"/>',
		'new'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'explore'   => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
		'community' => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5.8"/><path d="M17.5 20a5.5 5.5 0 0 0-3-4.9"/>',
		// Tris
		'hot'       => '<path d="M12 3c0 3-3 4-3 7a3 3 0 0 0 6 0c0-1-.5-2-1-2.5 2 1 4 3 4 6a6 6 0 0 1-12 0c0-5 6-6 6-10.5z"/>',
		'top'       => '<path d="M12 20V6"/><path d="M6 12l6-6 6 6"/>',
		'rising'    => '<path d="M4 18l6-7 4 4 6-8"/><path d="M20 3v4h-4"/>',
		// Sections
		'recent'    => '<circle cx="12" cy="12" r="9"/><path d="M12 6.5V12l4 2.5"/>',
		'flame'     => '<path d="M12 3c0 3-3 4-3 7a3 3 0 0 0 6 0c0-1-.5-2-1-2.5 2 1 4 3 4 6a6 6 0 0 1-12 0c0-5 6-6 6-10.5z"/>',
		'groups'    => '<rect x="3" y="4" width="7" height="7" rx="1.5"/><rect x="14" y="4" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		// Types de contenu
		'article'   => '<path d="M6 3h9l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/><path d="M8.5 13h7"/><path d="M8.5 17h5"/>',
		'theme'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
		'more'      => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
		'check'     => '<path d="M20 6 9 17l-5-5"/>',
		'reply'     => '<path d="M9 17l-5-5 5-5"/><path d="M4 12h11a5 5 0 0 1 5 5v2"/>',
		'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		'star'      => '<path d="M12 3l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5L2.6 9.8l6.5-.9z"/>',
		'link'      => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
		'discuss'   => '<path d="M20 12a7.5 7.5 0 0 1-10.9 6.7L4 20l1.3-4.1A7.5 7.5 0 1 1 20 12z"/>',
	);

	if ( ! isset( $traces[ $nom ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="sb-i sb-i-%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none"'
		. ' stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
		. ' aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $nom ),
		(int) $taille,
		$traces[ $nom ]
	);
}
