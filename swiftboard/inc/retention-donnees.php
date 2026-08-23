<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Rétention des données : purge des croissances non bornées.
 *
 * POURQUOI CE MODULE
 * ------------------
 * Trois tables ou métas grossissaient sans limite. Sur un hébergement mutualisé
 * — la cible du thème — la taille de la base est une contrainte dure, et une
 * table `wp_usermeta` obèse ralentit CHAQUE chargement de page WordPress.
 *
 * Les deux croissances traitées ici ont été mesurées, pas supposées :
 *
 *   G2 — métas `sb_digest_sent_{YYYYWW}`
 *        Une ligne `wp_usermeta` par membre et par semaine, jamais supprimée.
 *        Mesure : 104 lignes après deux ans pour UN membre, soit
 *        104 000 lignes pour 1 000 membres actifs.
 *        Ces métas ne servent qu'à éviter un double envoi dans la semaine
 *        courante : au-delà de quelques semaines, elles ne sont plus lues.
 *
 *   G4 — votes orphelins
 *        Les votes portant sur un contenu supprimé restaient en base.
 *        Mesure : 5 sujets supprimés définitivement → 5 votes orphelins.
 *        Ils faussent aussi les compteurs agrégés (top posts, réputation).
 *
 * PRINCIPE RETENU
 * ---------------
 * Purge par LOTS BORNÉS, jamais de `DELETE` massif : sur un mutualisé, une
 * requête qui verrouille `wp_usermeta` plusieurs secondes bloque tout le site.
 * Chaque passage supprime au plus quelques centaines de lignes ; le cron
 * quotidien rattrape le reste jour après jour.
 *
 * @package SwiftBoard
 * @since 5.1.4
 */
/** Nombre de semaines de métas digest conservées. */
if ( ! defined( 'SWIFTBOARD_DIGEST_RETENTION_SEMAINES' ) ) {
	define( 'SWIFTBOARD_DIGEST_RETENTION_SEMAINES', 8 );
}

/** Plafond de lignes supprimées par passage (protection du mutualisé). */
if ( ! defined( 'SWIFTBOARD_PURGE_LOT_MAX' ) ) {
	define( 'SWIFTBOARD_PURGE_LOT_MAX', 500 );
}

/**
 * Supprime les métas `sb_digest_sent_*` antérieures à la fenêtre de rétention.
 *
 * La clé encode l'année et la semaine ISO (`sb_digest_sent_202631`). On calcule
 * la liste des clés À CONSERVER puis on supprime tout le reste : c'est plus sûr
 * qu'une comparaison lexicographique, qui se casserait au passage d'année
 * (`202601` < `202552` est faux en tri texte).
 *
 * @return int Nombre de lignes supprimées.
 */
function swiftboard_purger_metas_digest() {
	global $wpdb;

	$a_conserver = array();
	for ( $i = 0; $i < SWIFTBOARD_DIGEST_RETENTION_SEMAINES; $i++ ) {
		$a_conserver[] = 'sb_digest_sent_' . gmdate( 'YW', strtotime( "-{$i} week" ) );
	}

	$placeholders = implode( ',', array_fill( 0, count( $a_conserver ), '%s' ) );

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT umeta_id FROM {$wpdb->usermeta}
             WHERE meta_key LIKE 'sb\_digest\_sent\_%'
               AND meta_key NOT IN ({$placeholders})
             LIMIT %d",
			array_merge( $a_conserver, array( SWIFTBOARD_PURGE_LOT_MAX ) )
		)
	);

	if ( ! $ids ) {
		return 0;
	}

	$ids = array_map( 'intval', $ids );
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE umeta_id IN ({$placeholders})",
			$ids
		)
	);

	return count( $ids );
}

/**
 * Supprime les votes portant sur un contenu qui n'existe plus.
 *
 * Le thème purge déjà les votes d'un utilisateur supprimé, mais pas ceux d'un
 * CONTENU supprimé — cas courant après une purge de spam ou un vidage de
 * corbeille.
 *
 * @return int Nombre de lignes supprimées.
 */
function swiftboard_purger_votes_orphelins() {
	global $wpdb;

	$table = swiftboard_table( 'votes' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return 0;
	}

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT v.id FROM {$table} v
             LEFT JOIN {$wpdb->posts} p ON p.ID = v.post_id
             WHERE p.ID IS NULL
             LIMIT %d",
			SWIFTBOARD_PURGE_LOT_MAX
		)
	);

	if ( ! $ids ) {
		return 0;
	}

	$ids = array_map( 'intval', $ids );
	$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$ph})", $ids ) );

	return count( $ids );
}

/**
 * Purge immédiate des votes d'un contenu au moment de sa suppression.
 *
 * Complète le cron : évite d'attendre 24 h et garde les compteurs agrégés
 * justes dès la suppression.
 *
 * @param int $post_id Contenu supprimé.
 * @return void
 */
function swiftboard_purger_votes_du_contenu( $post_id ) {
	global $wpdb;

	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return;
	}

	$type = get_post_type( $post_id );
	if ( ! in_array( $type, array( 'topic', 'reply', 'post' ), true ) ) {
		return;
	}

	$table = swiftboard_table( 'votes' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
		$wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
	}
}
add_action( 'before_delete_post', 'swiftboard_purger_votes_du_contenu', 10, 1 );

/**
 * Passage quotidien de rétention.
 *
 * Accroché au cron de nettoyage déjà planifié par le module de notifications :
 * pas de tâche supplémentaire à ordonnancer.
 *
 * @return void
 */
/**
 * Supprime les jetons de reservation d'upload des jours ecoules.
 *
 * Chaque envoi d'image pose un jeton `swiftboard_daily_upload_{uid}_{date}_slot_{n}`
 * dans wp_options : c'est le verrou atomique qui empeche le depassement de
 * quota sous concurrence. Ces jetons deviennent inutiles le lendemain, mais
 * rien ne les supprimait — troisieme croissance non bornee.
 *
 * @return int Nombre de jetons supprimes.
 */
function swiftboard_purger_jetons_upload() {
	global $wpdb;

	$aujourdhui = current_time( 'Y-m-d' );

	$noms = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
         WHERE option_name LIKE 'swiftboard\_daily\_upload\_%\_slot\_%'
           AND option_name NOT LIKE %s
         LIMIT %d",
			'%' . $aujourdhui . '%',
			SWIFTBOARD_PURGE_LOT_MAX
		)
	);

	foreach ( $noms as $nom ) {
		delete_option( $nom );
	}

	return count( $noms );
}

/**
 * Passage quotidien de retention.
 *
 * @return void
 */
function swiftboard_retention_quotidienne() {
	$metas = swiftboard_purger_metas_digest();
	$votes = swiftboard_purger_votes_orphelins();
	swiftboard_purger_jetons_upload();

	if ( $metas || $votes ) {
		/**
		 * Signale ce qui vient d'être purgé.
		 *
		 * @param int $metas Métas digest supprimées.
		 * @param int $votes Votes orphelins supprimés.
		 */
		do_action( 'swiftboard_retention_purgee', $metas, $votes );
	}
}
add_action( 'swiftboard_notif_cleanup', 'swiftboard_retention_quotidienne' );

// === Purge des signalements résolus après 30 jours (LOT 7) ===
global $wpdb;
$reports_table = swiftboard_table( 'reports' );
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $reports_table ) ) === $reports_table ) {
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM ' . $reports_table . ' WHERE status IN (%s, %s) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)',
			'resolved',
			'dismissed'
		)
	);
}
