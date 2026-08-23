<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Corrections d'accessibilité, d'utilisabilité et de référencement.
 *
 * Trois défauts signalés par un audit externe, reproduits au navigateur puis
 * corrigés ici. Ils sont « mineurs » par leur ampleur, pas par leur nature.
 *
 * @package SwiftBoard
 */
// ============================================================================
// 1. TITRE DES PAGES DE PROFIL
// ============================================================================
/**
 * Construit le titre d'une page de profil.
 *
 * DÉFAUT CORRIGÉ — les trois pages de profil renvoyaient le MÊME titre :
 *
 *     /forums/users/tester/   → « SwiftBoard Test »
 *     /forums/users/membre2/  → « SwiftBoard Test »
 *     /forums/users/admin/    → « SwiftBoard Test »
 *
 * Google traite des pages au titre identique comme des quasi-doublons. Et le
 * guide « Optimizing your website for generative AI features » insiste sur
 * l'attribution d'auteur : une page d'auteur qui ne le nomme pas dans son
 * titre ne peut pas la porter.
 *
 * @param string $titre   Titre courant.
 * @param int    $user_id Membre concerné, 0 hors page de profil.
 * @return string
 */
function swiftboard_titre_profil( $titre, $user_id ) {
	$user_id = (int) $user_id;

	// Hors profil : on ne touche à rien. Sans cette porte, la correction
	// déborderait sur tout le site.
	if ( $user_id <= 0 ) {
		return $titre;
	}

	$membre = get_userdata( $user_id );
	if ( ! $membre ) {
		return $titre;
	}

	$nom  = $membre->display_name !== '' ? $membre->display_name : $membre->user_login;
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	/* translators: 1 : nom du membre, 2 : nom du site */
	return sprintf( __( 'Profil de %1$s – %2$s', 'swiftboard' ), $nom, $site );
}

add_filter(
	'pre_get_document_title',
	function ( $titre ) {
		if ( ! function_exists( 'bbp_is_single_user' ) || ! bbp_is_single_user() ) {
			return $titre;
		}

		$uid = function_exists( 'bbp_get_displayed_user_id' ) ? (int) bbp_get_displayed_user_id() : 0;
		if ( ! $uid ) {
			return $titre;
		}

		// `pre_get_document_title` court-circuite : il faut donc fournir un titre
		// complet, pas un fragment. On repart de celui du site.
		$base = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		return swiftboard_titre_profil( $base, $uid );
	},
	20
);

// ============================================================================
// 2. ARIA-PRESSED DES BOUTONS DE VOTE
// ============================================================================
/**
 * Détermine si un bouton de vote doit être annoncé comme pressé.
 *
 * DÉFAUT CORRIGÉ — mesuré au navigateur sur un membre ayant déjà voté :
 *
 *     après rechargement : aria-pressed="false" · classe active=true
 *
 * L'état VISUEL était restauré, l'état ACCESSIBLE non. Un lecteur d'écran
 * annonçait « non pressé » sur un bouton actif : l'utilisateur ne savait pas
 * qu'il avait déjà voté et pouvait l'annuler sans le vouloir.
 *
 * Le JS repeuplait bien l'attribut, mais seulement APRÈS un aller-retour REST.
 * Entre le premier octet et la réponse, l'information était fausse — et
 * définitivement fausse si le script ne s'exécute pas.
 *
 * @param int    $post_id Contenu voté.
 * @param string $type    'up' ou 'down'.
 * @return string 'true' ou 'false' (valeur d'attribut HTML).
 */
function swiftboard_aria_pressed( $post_id, $type ) {
	$type = ( $type === 'down' ) ? 'down' : 'up';

	if ( ! function_exists( 'swiftboard_get_my_vote' ) ) {
		return 'false';
	}

	$mon_vote = swiftboard_get_my_vote( (int) $post_id );

	return ( $mon_vote === $type ) ? 'true' : 'false';
}

// ============================================================================
// 3. ZONE D'ACTIONS DES CARTES
// ============================================================================
/**
 * Produit la zone d'actions d'une carte de sujet.
 *
 * DÉFAUT CORRIGÉ — deux gabarits construisaient cette zone séparément, avec
 * des boutons DIFFÉRENTS :
 *
 *     inc/reddit-layout.php : commentaires · partager · sauvegarder · cacher · plus
 *     front-page.php        : commentaires · partager · sauvegarder
 *
 * Le handler JS de « Cacher » existait donc pour un bouton jamais rendu sur
 * l'accueil. Mesuré : `curl` en voyait 2 sur la liste des forums, 0 sur
 * l'accueil connecté.
 *
 * Une seule fonction produit désormais la zone : les deux gabarits ne peuvent
 * plus diverger.
 *
 * Le bouton « Cacher » porte `aria-pressed` (c'est une bascule) et la zone
 * embarque une région `aria-live` : sans elle, un utilisateur de lecteur
 * d'écran ne saurait pas que la carte a disparu.
 *
 * @param int    $post_id      Identifiant du sujet.
 * @param string $url          Permalien du sujet.
 * @param int    $nb_reponses  Nombre de réponses.
 * @return string
 */
function swiftboard_actions_carte_html( $post_id, $url, $nb_reponses = 0 ) {
	static $menu_instance = 0;
	$menu_instance++;
	$menu_suffix = '-' . (string) $menu_instance;
	$post_id = (int) $post_id;
	$is_saved = is_user_logged_in() && function_exists( 'swiftboard_is_saved' )
		? swiftboard_is_saved( get_current_user_id(), $post_id )
		: false;

	// v5.3.1 — barre d'actions unifiee sur le modele des commentaires :
	// [pilule de vote mobile ▲ n ▼] 💬 ↩ ↗ 🔖 , icones SVG seules,
	// une seule ligne. « Cacher » est retire a la demande produit.
	$html = '<div class="sb-post-actions">';

	// Pilule de vote INLINE : visible uniquement sous 640px, ou le bloc de
	// vote vertical de la carte (.sb-post-votes) est masque. Memes classes
	// que la barre des commentaires -> votes.js la gere nativement, et un
	// patch de synchronisation garde les deux pilules a jour.
	if ( function_exists( 'swiftboard_get_vote_count' ) ) {
		$votes   = swiftboard_get_vote_count( $post_id );
		$my_vote = ( function_exists( 'swiftboard_get_my_vote' ) && is_user_logged_in() )
			? swiftboard_get_my_vote( $post_id )
			: null;
		$cnt_cls = 'sb-comment-vote-count';
		if ( $my_vote === 'up' ) {
			$cnt_cls .= ' up'; }
		if ( $my_vote === 'down' ) {
			$cnt_cls .= ' down'; }
		$html .= '<span class="sb-comment-votes sb-card-votes-inline">'
			. '<button class="sb-comment-vote-btn up' . ( $my_vote === 'up' ? ' active' : '' ) . '" data-post-id="' . esc_attr( (string) $post_id ) . '" data-vote="up"'
			. ' aria-label="' . esc_attr__( 'Upvoter', 'swiftboard' ) . '" aria-pressed="' . esc_attr( function_exists( 'swiftboard_aria_pressed' ) ? swiftboard_aria_pressed( $post_id, 'up' ) : 'false' ) . '">'
			. '<svg class="sb-icon" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>'
			. '</button>'
			. '<span class="' . esc_attr( $cnt_cls ) . '">' . esc_html( swiftboard_format_count( $votes ) ) . '</span>'
			. '<button class="sb-comment-vote-btn down' . ( $my_vote === 'down' ? ' active' : '' ) . '" data-post-id="' . esc_attr( (string) $post_id ) . '" data-vote="down"'
			. ' aria-label="' . esc_attr__( 'Downvoter', 'swiftboard' ) . '" aria-pressed="' . esc_attr( function_exists( 'swiftboard_aria_pressed' ) ? swiftboard_aria_pressed( $post_id, 'down' ) : 'false' ) . '">'
			. '<svg class="sb-icon" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>'
			. '</button>'
			. '</span>';
	}

	// 1. Commentaires (bulle + nombre uniquement — C.11 icones)
	$html .= '<a href="' . esc_url( $url ) . '" class="sb-action-btn sb-action-comment"'
		. ' aria-label="' . esc_attr(
			sprintf(
			/* translators: %d : nombre de réponses */
				_n( '%d commentaire', '%d commentaires', (int) $nb_reponses, 'swiftboard' ),
				(int) $nb_reponses
			)
		) . '" title="' . esc_attr__( 'Commentaires', 'swiftboard' ) . '">'
		. '<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>'
		. '<span class="sb-action-count">' . esc_html( swiftboard_format_count( (int) $nb_reponses ) ) . '</span>'
		. '</a>';

	// 2. Reply (sujets bbPress uniquement) — ancre vers le formulaire de reponse
	if ( function_exists( 'bbp_is_topic' ) && get_post_type( $post_id ) === 'topic' ) {
		$html .= '<a href="' . esc_url( $url ) . '#bbp-reply-form" class="sb-action-btn sb-action-reply"'
			. ' aria-label="' . esc_attr__( 'Répondre', 'swiftboard' ) . '" title="' . esc_attr__( 'Répondre', 'swiftboard' ) . '">'
			. '<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>'
			. '</a>';
	}

	// 3. Partager
	$html .= '<button type="button" class="sb-action-btn sb-action-share"'
		. ' data-url="' . esc_url( $url ) . '"'
		. ' aria-label="' . esc_attr__( 'Partager', 'swiftboard' ) . '" title="' . esc_attr__( 'Partager', 'swiftboard' ) . '">'
		. '<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>'
		. '</button>';

	// 4. « Sauvegarder » — regroupe dans le menu overflow « ⋯ » <= 640px
	// (EXI-MENU-01), visible en ligne sur desktop. L'etat serveur
	// (aria-pressed) et les gestionnaires JS (data-post-id) sont preserves :
	// c'est le MEME element dans les deux rendus.
	$html .= '<span class="sb-actions-overflow">'
		. '<button type="button" id="sb-more-toggle-' . esc_attr( (string) $post_id . $menu_suffix ) . '" class="sb-action-btn sb-more-toggle" aria-haspopup="menu" aria-expanded="false"'
		
		. ' aria-label="' . esc_attr__( 'Plus d’actions', 'swiftboard' ) . '" title="' . esc_attr__( 'Plus d’actions', 'swiftboard' ) . '">'
		. '<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>'
		. '</button>'
		. '<span class="sb-more-menu" id="sb-more-menu-card-' . esc_attr( (string) $post_id . $menu_suffix ) . '" role="menu" aria-labelledby="sb-more-toggle-' . esc_attr( (string) $post_id . $menu_suffix ) . '">'
			. '<button type="button"'
			. ' role="menuitemcheckbox" data-post-id="' . esc_attr( (string) $post_id ) . '"'
			. ( $is_saved ? ' class="sb-action-btn sb-action-save active"' : ' class="sb-action-btn sb-action-save"' )
			. ' aria-checked="' . ( $is_saved ? 'true' : 'false' ) . '"'
			. ' aria-label="' . esc_attr( $is_saved ? __( 'Sauvegardé', 'swiftboard' ) : __( 'Sauvegarder', 'swiftboard' ) ) . '" title="' . esc_attr( $is_saved ? __( 'Sauvegardé', 'swiftboard' ) : __( 'Sauvegarder', 'swiftboard' ) ) . '">'
		. '<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>'
			. '<span class="sb-more-label">' . esc_html( $is_saved ? __( 'Sauvegardé', 'swiftboard' ) : __( 'Sauvegarder', 'swiftboard' ) ) . '</span>'
		. '</button>'
			// Copier le lien : action universelle, disponible meme deconnecte.
			. '<button type="button" role="menuitem" class="sb-action-btn sb-action-copy"'
			. ' data-url="' . esc_url( $url ) . '"'
			. ' aria-label="' . esc_attr__( 'Copier le lien', 'swiftboard' ) . '">'
			. '<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
			. '<span class="sb-more-label">' . esc_html__( 'Copier le lien', 'swiftboard' ) . '</span>'
			. '</button>'
			// Signaler : reserve aux membres connectes (la route REST exige un nonce).
			. ( is_user_logged_in()
				? '<button type="button" role="menuitem" class="sb-action-btn sb-action-report"'
					. ' data-post-id="' . esc_attr( (string) $post_id ) . '"'
					. ' aria-label="' . esc_attr__( 'Signaler', 'swiftboard' ) . '">'
					. '<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>'
					. '<span class="sb-more-label">' . esc_html__( 'Signaler', 'swiftboard' ) . '</span>'
					. '</button>'
				: '' )
		. '</span>'
		. '</span>';

	// Liens de modération bbPress (fermer, épingler, fusionner, supprimer).
	//
	// Le rendu « Reddit » remplace `content-single-topic-lead.php`, où bbPress
	// les place normalement. Sans cet appel, ces actions étaient impossibles
	// depuis le front — même pour un keymaster. `bbp_topic_admin_links()`
	// n'émet rien sans droit de modération : aucun risque de fuite.
	// `bbp_topic_admin_links()` ECHO toujours : elle ignore un `echo => false`
	// et se contente d'appeler `echo bbp_get_topic_admin_links()`. L'affecter
	// renvoie NULL et imprime les liens hors de leur conteneur, au milieu du
	// flux. C'est le défaut EXI-BBP-01 que la suite de tests surveille — il
	// s'est glissé ici et a été rattrapé par `SansBbpressTest`.
	//
	// On utilise donc la variante `bbp_get_*()`, qui RETOURNE la chaîne.
	if ( function_exists( 'bbp_get_topic_admin_links' ) ) {
		$mod = bbp_get_topic_admin_links(
			array(
				'before' => '<span class="sb-action-btn sb-mod-links">',
				'after'  => '</span>',
				'sep'    => ' · ',
				'id'     => $post_id,
			)
		);
		if ( is_string( $mod ) ) {
			// v5.3.1 : le lien « Reply » des liens admin ferait doublon avec
			// l'icone ↩ ajoutee en 2e position — on le retire du markup de
			// moderation (il reste accessible dans la page sujet).
			$mod = (string) preg_replace( '#<a[^>]*class="[^"]*bbp-topic-reply-link[^"]*"[^>]*>.*?</a>#', '', $mod );

			// v11.0.5 — parite Reddit : ces liens (Modifier, Fusionner, Fermer,
			// Epingler, Corbeille, Indesirable) etaient imprimes A PLAT dans la
			// barre d'actions. Un moderateur voyait donc six libelles bruts en
			// bout de chaque carte, la ou Reddit n'expose qu'un menu compact.
			// On les replie dans le menu « ⋯ » deja present, sans toucher aux
			// URL ni aux nonces generes par bbPress : seul le conteneur change.
			$mod = (string) preg_replace( '#^<span class="sb-action-btn sb-mod-links">#', '', $mod );
			$mod = (string) preg_replace( '#</span>$#', '', $mod );
			$mod = str_replace( ' · ', '', $mod );
			$mod = trim( $mod );

			if ( '' !== $mod ) {
				$bloc_mod = '<span class="sb-more-sep" role="separator"></span>'
					. '<span class="sb-more-mod">' . $mod . '</span>';
				// Insertion avant la fermeture du menu overflow.
				$pos = strrpos( $html, '</span></span>' );
				if ( false !== $pos ) {
					$html = substr( $html, 0, $pos ) . $bloc_mod . substr( $html, $pos );
				}
			}
		}
	}

	// Région live : annonce le masquage et porte le lien d'annulation.
	// `aria-live="polite"` plutôt qu'`assertive` : l'information est utile,
	// pas urgente, et ne doit pas interrompre la lecture en cours.
	$html .= '<span class="sb-action-status" role="status" aria-live="polite"'
		. ' data-post-id="' . esc_attr( (string) $post_id ) . '"></span>';

	$html .= '</div>';

	return $html;
}
