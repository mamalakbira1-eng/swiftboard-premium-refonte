<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Création des tables sur un nouveau site d'un réseau multisite.
 *
 * DÉFAUT CORRIGÉ — mesuré sur un réseau réel à deux sites.
 *
 * Toutes les tables du thème sont créées par `after_switch_theme`. Ce hook ne
 * se déclenche que lors d'un CHANGEMENT de thème. Or, sur un réseau :
 *
 *   1. l'administrateur active SwiftBoard sur le réseau ;
 *   2. il crée un nouveau site plus tard ;
 *   3. ce site hérite du thème réseau — sans jamais « changer » de thème.
 *
 * `after_switch_theme` n'est donc jamais appelé pour lui. Résultat mesuré :
 *
 *     site 2 : wp_2_swiftboard_votes         → ABSENTE
 *              wp_2_swiftboard_notifications → ABSENTE
 *              wp_2_swiftboard_followers     → ABSENTE
 *
 * Le premier vote sur ce site produit une erreur SQL. Un filet existait bien
 * sur `admin_init`, mais il exige qu'un administrateur visite le tableau de
 * bord DE CE SITE : le forum public plante avant.
 *
 * Ce module accroche `wp_initialize_site`, émis par WordPress à la création
 * d'un site, et ajoute un filet côté front.
 *
 * @package SwiftBoard
 */
/**
 * Déclenche la création de toutes les tables du thème pour le site courant.
 *
 * On réémet `after_switch_theme` plutôt que d'appeler chaque fonction de
 * création : les modules y sont déjà accrochés, et une liste codée en dur ici
 * se désynchroniserait au premier module ajouté.
 *
 * @return void
 */
function swiftboard_creer_tables_site() {
	// `dbDelta` n'est pas chargé hors de l'admin.
	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	do_action( 'after_switch_theme' );
}

/**
 * Les tables essentielles du thème existent-elles pour le site courant ?
 *
 * Marquée impure : elle interroge le schéma de la base. Son résultat change
 * après un appel à swiftboard_creer_tables_site(), ce que PHPStan ne peut pas
 * deviner — il concluait que le second appel était toujours faux.
 *
 * @phpstan-impure
 * @return bool
 */
function swiftboard_tables_presentes() {
	global $wpdb;

	foreach ( array( 'swiftboard_votes', 'swiftboard_notifications' ) as $suffixe ) {
		$nom = $wpdb->prefix . $suffixe;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- vérification de schéma, non cachable.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $nom ) ) !== $nom ) {
			return false;
		}
	}

	return true;
}

// Création d'un nouveau site du réseau : WordPress émet `wp_initialize_site`
// APRÈS avoir posé les tables du cœur. C'est le moment exact où les tables du
// thème doivent naître.
add_action(
	'wp_initialize_site',
	function ( $site ) {
		if ( ! is_multisite() || ! is_object( $site ) || empty( $site->blog_id ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );

		// Le thème n'est pas forcément celui du nouveau site : on ne crée rien
		// s'il n'est pas actif, pour ne pas polluer une installation tierce.
		$theme = wp_get_theme();
		$actif = ( stripos( (string) $theme->get( 'Name' ), 'swiftboard' ) !== false )
		|| ( stripos( (string) $theme->get_template(), 'swiftboard' ) !== false );

		if ( $actif && ! swiftboard_tables_presentes() ) {
			swiftboard_creer_tables_site();
		}

		restore_current_blog();
	},
	20,
	1
);

// Filet côté FRONT : un site dont les tables manquent malgré tout ne doit pas
// afficher d'erreur SQL au premier visiteur.
//
// Le contrôle est mis en cache pour la journée : un `SHOW TABLES` à chaque
// requête coûterait plus cher que le problème qu'il évite.
add_action(
	'template_redirect',
	function () {
		if ( ! is_multisite() || is_admin() ) {
			return;
		}

		$cle = 'sb_tables_ok';
		if ( get_transient( $cle ) ) {
			return;
		}

		if ( swiftboard_tables_presentes() ) {
			set_transient( $cle, 1, DAY_IN_SECONDS );
			return;
		}

		swiftboard_creer_tables_site();

		if ( swiftboard_tables_presentes() ) {
			set_transient( $cle, 1, DAY_IN_SECONDS );
		}
	},
	1
);
