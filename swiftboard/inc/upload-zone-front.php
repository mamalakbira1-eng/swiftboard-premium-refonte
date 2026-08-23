<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zone d'upload d'images dans les formulaires bbPress (front).
 *
 * POURQUOI CE MODULE EXISTE
 * -------------------------
 * `assets/js/upload.js` était enfilé pour tout membre connecté et sa
 * configuration émise (`#sb-upload-config`), mais AUCUN gabarit ne rendait les
 * éléments qu'il pilote. Le script appelle :
 *
 *     initUpload('swiftboard-upload-reply', 'swiftboard-file-reply',
 *                'swiftboard-preview-reply', 'bbp_reply_content');
 *
 * et sort immédiatement si l'un manque :
 *
 *     if (!fileInput || !preview || !zone) { return; }
 *
 * Mesure au navigateur (membre connecté, page d'un sujet) :
 *   upload.js chargé : oui · #sb-upload-config : oui · input[type=file] : 0
 *
 * Le script était donc téléchargé, parsé, exécuté — et incapable d'agir. La
 * fonctionnalité annoncée était inaccessible. Signalé par un audit externe,
 * reproduit, corrigé ici.
 *
 * Les identifiants sont ceux qu'attend le JS : les changer casserait le lien.
 *
 * @package SwiftBoard
 */
/**
 * Construit la zone d'upload pour un formulaire donné.
 *
 * Renvoie une chaîne VIDE si l'utilisateur n'a pas le droit d'envoyer une
 * image : afficher le champ promettrait une action que la route REST
 * refuserait ensuite.
 *
 * @param string $contexte 'topic' (nouveau sujet) ou 'reply' (réponse).
 * @return string HTML de la zone, ou chaîne vide.
 */
function swiftboard_upload_zone_html( $contexte = 'reply' ) {
	$contexte = in_array( $contexte, array( 'topic', 'reply' ), true ) ? $contexte : 'reply';

	if ( ! is_user_logged_in() ) {
		return '';
	}

	// La capacité est vérifiée ICI comme elle le sera côté REST : les deux
	// doivent répondre la même chose, sinon l'interface ment.
	if ( function_exists( 'swiftboard_user_can' )
		&& ! swiftboard_user_can( get_current_user_id(), 'can_upload' ) ) {
		return '';
	}

	$zone   = 'swiftboard-upload-' . $contexte;
	$champ  = 'swiftboard-file-' . $contexte;
	$apercu = 'swiftboard-preview-' . $contexte;

	$limite = (int) get_option( 'swiftboard_upload_daily_limit', 2 );

	$html  = '<div class="swiftboard-upload-zone" id="' . esc_attr( $zone ) . '">';
	$html .= '<label for="' . esc_attr( $champ ) . '" class="swiftboard-upload-label">';
	$html .= '🖼️ ' . esc_html__( 'Ajouter une image', 'swiftboard' );
	$html .= '</label>';

	// `multiple` est volontairement absent : le quota est de quelques images
	// par jour, et un envoi groupé le consommerait sans que l'auteur s'en
	// aperçoive.
	$html .= '<input type="file" id="' . esc_attr( $champ ) . '"'
		. ' class="swiftboard-upload-input"'
		. ' accept="image/jpeg,image/png,image/webp,image/avif,image/gif"'
		. ' aria-describedby="' . esc_attr( $champ ) . '-aide">';

	$html .= '<p class="swiftboard-upload-aide" id="' . esc_attr( $champ ) . '-aide">';
	$html .= esc_html(
		sprintf(
		/* translators: %d : nombre d'images autorisées par jour */
			_n(
				'JPEG, PNG, WebP ou AVIF — %d image par jour, modérée avant publication.',
				'JPEG, PNG, WebP ou AVIF — %d images par jour, modérées avant publication.',
				$limite,
				'swiftboard'
			),
			$limite
		)
	);
	$html .= '</p>';

	// `aria-live` : le résultat de l'envoi arrive de façon asynchrone. Sans
	// cela, un lecteur d'écran n'annoncerait ni la progression ni l'échec.
	$html .= '<div class="swiftboard-upload-preview" id="' . esc_attr( $apercu ) . '"'
		. ' aria-live="polite"></div>';

	$html .= '</div>';

	return $html;
}

/**
 * Affiche la zone d'upload sous le champ de saisie d'une réponse.
 *
 * @return void
 */
function swiftboard_upload_zone_reply() {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit et échappé dans swiftboard_upload_zone_html().
	echo swiftboard_upload_zone_html( 'reply' );
}

/**
 * Affiche la zone d'upload sous le champ de saisie d'un nouveau sujet.
 *
 * @return void
 */
function swiftboard_upload_zone_topic() {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit et échappé dans swiftboard_upload_zone_html().
	echo swiftboard_upload_zone_html( 'topic' );
}

// Les hooks bbPress qui suivent immédiatement l'éditeur de chaque formulaire.
// On les pose sur les DEUX variantes disponibles : selon le gabarit actif,
// bbPress n'émet pas toujours la même. Une garde interne empêche le doublon.
add_action( 'bbp_theme_after_reply_form_content', 'swiftboard_upload_zone_reply' );
add_action( 'bbp_theme_after_topic_form_content', 'swiftboard_upload_zone_topic' );

/**
 * Filet de sécurité : si aucun des hooks ci-dessus n'est émis par le gabarit
 * actif, la zone est injectée directement dans le HTML du formulaire.
 *
 * C'est exactement le piège qui a rendu le bouton « Plus de sujets » inerte :
 * un callback posé sur un hook qu'aucun gabarit n'émet ne s'exécute jamais, et
 * rien ne le signale.
 *
 * @param string $html HTML du formulaire.
 * @return string
 */
function swiftboard_upload_zone_filet( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}

	// Déjà présente : ne rien faire (les hooks ont fonctionné).
	if ( strpos( $html, 'swiftboard-upload-zone' ) !== false ) {
		return $html;
	}

	$contexte = ( strpos( $html, 'bbp_topic_content' ) !== false ) ? 'topic' : 'reply';
	$zone     = swiftboard_upload_zone_html( $contexte );

	if ( $zone === '' ) {
		return $html;
	}

	// Insérée juste avant le bouton d'envoi, à sa place logique.
	$position = strrpos( $html, '<button' );
	if ( $position === false ) {
		$position = strrpos( $html, '<input type="submit"' );
	}

	if ( $position === false ) {
		return $html . $zone;
	}

	return substr( $html, 0, $position ) . $zone . substr( $html, $position );
}
add_filter( 'bbp_get_template_part', 'swiftboard_upload_zone_filet', 20 );
