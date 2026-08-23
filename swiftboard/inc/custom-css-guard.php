<?php
/**
 * SwiftBoard — Garde-fou du CSS additionnel du Customizer.
 *
 * Constat en production : le champ « CSS additionnel » contenait un correctif
 * de pré-production laissé en place, tronqué en pleine règle et suivi de huit
 * accolades orphelines. Un tel bloc n'est pas seulement inutile — il arrête le
 * parseur CSS du navigateur : TOUTE déclaration écrite après lui est ignorée
 * silencieusement, sans le moindre message d'erreur.
 *
 * Un utilisateur qui ajoute une couleur dans ce champ voit donc son CSS ne
 * rien faire, sans comprendre pourquoi. Le coût de diagnostic est élevé et la
 * cause invisible depuis l'interface.
 *
 * Ce module agit sur trois plans :
 *
 *   1. PRÉVENTION — à l'enregistrement, le CSS est analysé. Les accolades sont
 *      rééquilibrées et un commentaire non refermé est clos. L'utilisateur est
 *      averti de ce qui a été corrigé.
 *   2. RÉPARATION — au chargement de l'admin, un champ déjà corrompu est
 *      détecté et signalé, avec un bouton de nettoyage en un clic.
 *   3. PROTECTION DU RENDU — à l'affichage, un CSS déséquilibré est assaini
 *      avant d'atteindre le navigateur, pour qu'une base existante ne casse
 *      pas la mise en page.
 *
 * Aucune règle valide n'est supprimée : on ne touche qu'à ce qui empêche le
 * parseur d'avancer.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retire commentaires et chaines litterales pour ne garder que la structure.
 *
 * Seules les accolades hors commentaire et hors chaine delimitent un bloc.
 * Les compter sans ce filtrage produit un diagnostic faux.
 *
 * @param string $css Feuille de style.
 * @return string Squelette structurel.
 */
function swiftboard_css_squelette( $css ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) $css );
	// Chaines double puis simple quote, echappements pris en compte.
	$css = (string) preg_replace( '/"(?:[^"\\\\]|\\\\.)*"/s', '""', $css );
	$css = (string) preg_replace( "/'(?:[^'\\\\]|\\\\.)*'/s", "''", $css );
	return $css;
}

/**
 * Analyse un CSS et retourne son diagnostic.
 *
 * @param string $css Feuille de style à analyser.
 * @return array{equilibre:bool,ouvrantes:int,fermantes:int,commentaire_ouvert:bool,tronque:bool}
 */
function swiftboard_css_diagnostic( $css ) {
	$css = (string) $css;

	// On retire d'abord les commentaires ET les chaines litterales : les uns
	// comme les autres peuvent contenir des accolades qui ne structurent rien.
	// Cas reel : `content: "}"` faisait compter une fermante de trop, le CSS
	// etait declare casse et l'assainisseur ajoutait une accolade parasite —
	// le garde-fou corrompait un fichier parfaitement valide.
	$sans_commentaires = swiftboard_css_squelette( $css );

	$ouvrantes = substr_count( (string) $sans_commentaires, '{' );
	$fermantes = substr_count( (string) $sans_commentaires, '}' );

	// Un commentaire ouvert et jamais refermé avale tout le reste du fichier.
	$commentaire_ouvert = ( substr_count( $css, '/*' ) > substr_count( $css, '*/' ) );

	// Sélecteur tronqué : du texte suivi d'aucun bloc, juste avant une
	// accolade fermante orpheline. C'est la signature d'un copier-coller
	// interrompu, exactement le cas rencontré en production.
	$tronque = (bool) preg_match( '/[A-Za-z0-9_\-\.\#]\s*\n\s*\}/', (string) $sans_commentaires )
		&& $fermantes > $ouvrantes;

	return array(
		'equilibre'          => ( $ouvrantes === $fermantes ) && ! $commentaire_ouvert,
		'ouvrantes'          => $ouvrantes,
		'fermantes'          => $fermantes,
		'commentaire_ouvert' => $commentaire_ouvert,
		'tronque'            => $tronque,
	);
}

/**
 * Assainit un CSS déséquilibré sans détruire les règles valides.
 *
 * @param string $css Feuille de style à réparer.
 * @return string CSS assaini.
 */
function swiftboard_css_assainir( $css ) {
	$css = (string) $css;

	// 1. Fermer un commentaire resté ouvert.
	if ( substr_count( $css, '/*' ) > substr_count( $css, '*/' ) ) {
		$css .= " */\n";
	}

	// 2. Retirer les accolades fermantes orphelines, ligne par ligne, en
	//    suivant la profondeur réelle. Une ligne qui referme un bloc jamais
	//    ouvert est écartée ; tout le reste est conservé intact.
	$lignes     = explode( "\n", $css );
	$profondeur = 0;
	$gardees    = array();

	foreach ( $lignes as $ligne ) {
		$nue = swiftboard_css_squelette( $ligne );
		$o   = substr_count( (string) $nue, '{' );
		$f   = substr_count( (string) $nue, '}' );

		if ( 0 === $o && $f > 0 && ( $profondeur - $f ) < 0 && '' === trim( str_replace( '}', '', (string) $nue ) ) ) {
			// Ligne composée uniquement d'accolades fermantes en trop.
			$profondeur = max( 0, $profondeur - $f );
			continue;
		}

		$profondeur += $o - $f;
		if ( $profondeur < 0 ) {
			$profondeur = 0;
		}
		$gardees[] = $ligne;
	}

	$css = implode( "\n", $gardees );

	// 3. Refermer les blocs restés ouverts, pour que la dernière règle soit
	//    valide plutôt qu'abandonnée.
	$sans      = swiftboard_css_squelette( $css );
	$manquants = substr_count( $sans, '{' ) - substr_count( $sans, '}' );
	if ( $manquants > 0 ) {
		$css .= "\n" . str_repeat( '}', $manquants ) . "\n";
	}

	return $css;
}

/**
 * Nettoie le CSS à l'enregistrement depuis le Customizer.
 *
 * @param string $css CSS soumis.
 * @return string CSS validé.
 */
function swiftboard_css_filtrer_sauvegarde( $css ) {
	$diag = swiftboard_css_diagnostic( $css );

	if ( $diag['equilibre'] ) {
		return $css;
	}

	$repare = swiftboard_css_assainir( $css );

	// On mémorise l'intervention pour l'annoncer dans l'admin : une
	// correction silencieuse serait plus déroutante que le problème.
	set_transient(
		'swiftboard_css_repare',
		array(
			'date'      => current_time( 'mysql' ),
			'ouvrantes' => $diag['ouvrantes'],
			'fermantes' => $diag['fermantes'],
		),
		DAY_IN_SECONDS
	);

	return $repare;
}
add_filter( 'update_custom_css_data', 'swiftboard_css_filtrer_sauvegarde_data', 10, 2 );

/**
 * Point d'entrée du filtre WordPress (signature à deux arguments).
 *
 * @param array $data Données du CSS personnalisé.
 * @param array $args Arguments de contexte.
 * @return array Données filtrées.
 */
function swiftboard_css_filtrer_sauvegarde_data( $data, $args ) {
	unset( $args );
	if ( isset( $data['css'] ) ) {
		$data['css'] = swiftboard_css_filtrer_sauvegarde( $data['css'] );
	}
	return $data;
}

/**
 * Protège le rendu : un CSS déjà corrompu en base ne doit pas casser la page.
 *
 * @param string $css CSS sur le point d'être affiché.
 * @return string CSS assaini si nécessaire.
 */
function swiftboard_css_filtrer_rendu( $css ) {
	$diag = swiftboard_css_diagnostic( $css );
	return $diag['equilibre'] ? $css : swiftboard_css_assainir( $css );
}
add_filter( 'wp_get_custom_css', 'swiftboard_css_filtrer_rendu', 20 );

/**
 * Signale dans l'admin un CSS additionnel corrompu et propose son nettoyage.
 *
 * @return void
 */
function swiftboard_css_avis_admin() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$repare = get_transient( 'swiftboard_css_repare' );
	if ( $repare ) {
		delete_transient( 'swiftboard_css_repare' );
		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'SwiftBoard :', 'swiftboard' ),
			esc_html__( 'le CSS additionnel comportait des accolades déséquilibrées. Elles ont été corrigées à l’enregistrement, sinon toute règle écrite ensuite aurait été ignorée par le navigateur.', 'swiftboard' )
		);
	}

	// Détection d'un champ déjà corrompu, indépendamment de toute sauvegarde.
	$css = wp_get_custom_css();
	if ( ! $css ) {
		return;
	}

	// On analyse la valeur BRUTE en base, pas la version filtrée au rendu.
	$post = wp_get_custom_css_post();
	$brut = $post ? $post->post_content : $css;
	$diag = swiftboard_css_diagnostic( $brut );

	if ( $diag['equilibre'] ) {
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=swiftboard_nettoyer_css' ),
		'swiftboard_nettoyer_css'
	);

	printf(
		'<div class="notice notice-error"><p><strong>%s</strong> %s</p><p>%s</p><p><a href="%s" class="button button-primary">%s</a> <a href="%s" class="button">%s</a></p></div>',
		esc_html__( 'SwiftBoard — CSS additionnel invalide', 'swiftboard' ),
		esc_html(
			sprintf(
				/* translators: 1: nombre d'accolades ouvrantes, 2: nombre d'accolades fermantes. */
				__( 'Le champ « CSS additionnel » contient %1$d accolades ouvrantes pour %2$d fermantes.', 'swiftboard' ),
				$diag['ouvrantes'],
				$diag['fermantes']
			)
		),
		esc_html__( 'Un déséquilibre arrête le parseur CSS du navigateur : toute règle placée après le point de rupture est ignorée, sans message d’erreur. C’est une cause fréquente de « mon CSS ne s’applique pas ».', 'swiftboard' ),
		esc_url( $url ),
		esc_html__( 'Corriger automatiquement', 'swiftboard' ),
		esc_url( admin_url( 'customize.php?autofocus[section]=custom_css' ) ),
		esc_html__( 'Ouvrir le champ', 'swiftboard' )
	);
}
add_action( 'admin_notices', 'swiftboard_css_avis_admin' );

/**
 * Applique le nettoyage demandé depuis l'avis d'administration.
 *
 * @return void
 */
function swiftboard_css_nettoyer() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Permission refusée.', 'swiftboard' ) );
	}

	check_admin_referer( 'swiftboard_nettoyer_css' );

	$post = wp_get_custom_css_post();
	$brut = $post ? $post->post_content : '';

	if ( $brut ) {
		// Sauvegarde de la version d'origine : le nettoyage doit rester
		// réversible, l'utilisateur peut vouloir récupérer un fragment.
		update_option( 'swiftboard_css_sauvegarde', $brut, false );
		wp_update_custom_css_post( swiftboard_css_assainir( $brut ) );
	}

	wp_safe_redirect( add_query_arg( 'swiftboard_css', 'nettoye', admin_url( 'themes.php' ) ) );
	exit;
}
add_action( 'admin_post_swiftboard_nettoyer_css', 'swiftboard_css_nettoyer' );

/**
 * Confirme le nettoyage et rappelle où retrouver la sauvegarde.
 *
 * @return void
 */
function swiftboard_css_avis_nettoye() {
	// phpcs:ignore WordSecurity.Security.NonceVerification.Recommended -- simple affichage d'un message après redirection.
	if ( ! isset( $_GET['swiftboard_css'] ) || 'nettoye' !== $_GET['swiftboard_css'] ) {
		return;
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'SwiftBoard :', 'swiftboard' ),
		esc_html__( 'le CSS additionnel a été assaini. La version d’origine est conservée dans l’option « swiftboard_css_sauvegarde ».', 'swiftboard' )
	);
}
add_action( 'admin_notices', 'swiftboard_css_avis_nettoye' );
