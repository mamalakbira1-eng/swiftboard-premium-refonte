<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Module Votes & Social
 *
 * 1. Création de la table {prefix}swiftboard_votes à l'activation du thème
 * 2. REST API : POST /swiftboard/v1/vote  — émettre/changer un vote
 *                GET  /swiftboard/v1/vote  — récupérer le score + mon vote
 *                DELETE /swiftboard/v1/vote — retirer mon vote
 * 3. Logique métier :
 *    - 1 vote par utilisateur connecté ET 1 vote par IP pour les anonymes
 *    - Toggle : si je re-vote pareil → on retire (unvote)
 *    - Si je change up→down ou down→up → on remplace
 *    - Rate limit : 1 vote / 5 sec pour les rookies, 1 / sec pour les autres
 * 4. Hook : do_action('swiftboard_vote_cast', $post_id, $vote_type, $voter_user_id)
 *    → branché par admin-settings-grades.php pour la montée de grade auto
 * 5. Meta _swiftboard_vote_score mis à jour sur le post (cache pour le widget
 *    "popular topics" et pour admin-dashboard)
 *
 * @package SwiftBoard
 * @since 2.4.0
 */
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL queries use internal $wpdb->prefix variables (safe)

// ============================================================================
// 1. CRÉATION DE LA TABLE À L'ACTIVATION
// ============================================================================
global $wpdb;
$swiftboard_votes_db_version = '1.0.0';

/**
 * @return void
 */
function swiftboard_create_votes_table() {
	global $wpdb, $swiftboard_votes_db_version;
	$table_name      = swiftboard_table( 'votes' );
	$charset_collate = $wpdb->get_charset_collate();

	// post_type permet de filtrer rapidement topic vs reply
	// voter_ip stocke l'IP (anonymisée partie par partie via wp_privacy_anonymize_ip)
	$sql = "CREATE TABLE {$table_name} (
        id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id       BIGINT(20) UNSIGNED NOT NULL,
        post_type     VARCHAR(20) NOT NULL DEFAULT 'topic',
        post_author   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        vote_type     VARCHAR(10) NOT NULL DEFAULT 'up',
        user_id       BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        voter_ip      VARCHAR(100) NOT NULL DEFAULT '',
        voter_hash    VARCHAR(64) NOT NULL DEFAULT '',
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_voter (post_id, user_id, voter_hash),
        KEY idx_post (post_id),
        KEY idx_author (post_author),
        KEY idx_type (vote_type),
        KEY idx_user (user_id)
    ) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'swiftboard_votes_db_version', $swiftboard_votes_db_version );
}
// Crée la table au prochain chargement admin si absente
add_action(
	'admin_init',
	function () {
		global $wpdb;
		$table  = swiftboard_table( 'votes' );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		if ( ! $exists ) {
			swiftboard_create_votes_table();
		}
	}
);
// Aussi au changement de thème (activation)
add_action( 'after_switch_theme', 'swiftboard_create_votes_table' );

// ============================================================================
// 2. HELPERS — HASH VOTER & IP
// ============================================================================
/**
 * Retourne un identifiant stable du votant :
 *  - utilisateur connecté : "u:{user_id}"
 *  - anonyme : hash sha1 de l'IP + sel (pour empêcher le re-vote anonyme)
 *
 * @return string
 */
function swiftboard_get_voter_hash() {
	if ( is_user_logged_in() ) {
		return 'u:' . get_current_user_id();
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	// Anonymisation partielle (RGPD) avant hash
	$anon = function_exists( 'wp_privacy_anonymize_ip' ) ? wp_privacy_anonymize_ip( $ip ) : $ip;
	return 'a:' . hash( 'sha1', $anon . '|' . wp_salt() );
}

/**
 * @return mixed
 */
function swiftboard_get_voter_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return function_exists( 'wp_privacy_anonymize_ip' ) ? wp_privacy_anonymize_ip( $ip ) : $ip;
}





// ============================================================================
// 4. ÉMETTRE / CHANGER / RETIRER UN VOTE
// ============================================================================
/**
 * Logique centrale :
 *  - Si l'utilisateur n'a pas voté → insère
 *  - S'il a voté pareil → retire (unvote)
 *  - S'il a voté différent → update (change)
 *
 * @param int    $post_id
 * @param string $vote_type 'up' ou 'down'
 * @return array<string, mixed>|WP_Error
 */
function swiftboard_cast_vote( $post_id, $vote_type ) {
	global $wpdb;
	$table     = swiftboard_table( 'votes' );
	$post_id   = (int) $post_id;
	$vote_type = ( $vote_type === 'up' ) ? 'up' : 'down';

	if ( ! $post_id ) {
		return new WP_Error( 'invalid_post', 'Post ID invalide.', array( 'status' => 400 ) );
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'not_found', 'Post introuvable.', array( 'status' => 404 ) );
	}

	// Vérifier que les votes sont activés
	if ( ! (int) get_option( 'swiftboard_enable_votes', 1 ) ) {
		return new WP_Error( 'votes_disabled', 'Les votes sont désactivés.', array( 'status' => 403 ) );
	}

	// Permission (utilise le système de grades)
	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	if ( $user_id ) {
		if ( ! swiftboard_user_can( $user_id, 'can_vote' ) ) {
			return new WP_Error( 'no_perm', 'Votre grade ne permet pas de voter.', array( 'status' => 403 ) );
		}
	}
	// Les anonymes peuvent voter si le système est activé (le hash IP les empêche de frauder facilement)

	// Rate limit par votant
	$rl_key       = 'sb_vote_rl_' . swiftboard_get_voter_hash();
	$last         = (int) get_transient( $rl_key );
	$min_interval = $user_id ? swiftboard_get_vote_min_interval( $user_id ) : 5;
	if ( $last && ( time() - $last ) < $min_interval ) {
		return new WP_Error(
			'rate_limited',
			sprintf( 'Veuillez attendre %d secondes entre deux votes.', $min_interval ),
			array( 'status' => 429 )
		);
	}

	// Limite quotidienne par grade
	if ( $user_id ) {
		$daily_limit = swiftboard_get_user_daily_vote_limit( $user_id );
		if ( $daily_limit > 0 ) {
			$today_key   = 'sb_vote_today_' . $user_id . '_' . date( 'Y-m-d' );
			$today_count = (int) get_transient( $today_key );
			if ( $today_count >= $daily_limit ) {
				return new WP_Error(
					'daily_limit',
					sprintf( 'Limite quotidienne de votes atteinte (%d).', $daily_limit ),
					array( 'status' => 429 )
				);
			}
		}
	}

	$voter_hash = swiftboard_get_voter_hash();
	$voter_ip   = swiftboard_get_voter_ip();

	// Vote existant ?
	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT vote_type FROM {$table}
         WHERE post_id = %d AND user_id = %d AND voter_hash = %s
         LIMIT 1",
			$post_id,
			$user_id,
			$voter_hash
		)
	);

	$action_taken = '';

	if ( $existing === null ) {
		// Pas de vote → insère
		$inserted = $wpdb->insert(
			$table,
			array(
				'post_id'     => $post_id,
				'post_type'   => $post->post_type,
				'post_author' => (int) $post->post_author,
				'vote_type'   => $vote_type,
				'user_id'     => $user_id,
				'voter_ip'    => $voter_ip,
				'voter_hash'  => $voter_hash,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error(
				'vote_write_failed',
				__( 'Impossible d’enregistrer le vote (conflit ou erreur base).', 'swiftboard' ),
				array( 'status' => 409 )
			);
		}
		$action_taken = 'inserted';

		// Hook de promotion (uniquement sur upvote)
		if ( $vote_type === 'up' ) {
			/**
		 * Se declenche a chaque upvote enregistre.
		 *
		 * Uniquement sur un vote POSITIF : un downvote ne doit pas nourrir la
		 * reputation de l'auteur. Emis apres l'ecriture en base et le
		 * recomptage des compteurs.
		 *
		 * @since 4.0.0
		 *
		 * @param int $post_id   Contenu vote (sujet ou reponse).
		 * @param string $vote_type Toujours 'up' pour ce hook.
		 * @param int $user_id   Auteur du vote, 0 si anonyme.
		 */
			do_action( 'swiftboard_vote_cast', $post_id, 'up', $user_id );
		}
	} elseif ( $existing === $vote_type ) {
		// Même vote → retire (unvote)
		$deleted = $wpdb->delete(
			$table,
			array(
				'post_id'    => $post_id,
				'user_id'    => $user_id,
				'voter_hash' => $voter_hash,
			),
			array( '%d', '%d', '%s' )
		);
		if ( false === $deleted ) {
			return new WP_Error(
				'vote_write_failed',
				__( 'Impossible de retirer le vote.', 'swiftboard' ),
				array( 'status' => 500 )
			);
		}
		$action_taken = 'removed';

	} else {
		// Vote différent → update
		$updated = $wpdb->update(
			$table,
			array(
				'vote_type'  => $vote_type,
				'created_at' => current_time( 'mysql' ),
			),
			array(
				'post_id'    => $post_id,
				'user_id'    => $user_id,
				'voter_hash' => $voter_hash,
			),
			array( '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);
		if ( false === $updated ) {
			return new WP_Error(
				'vote_write_failed',
				__( 'Impossible de modifier le vote.', 'swiftboard' ),
				array( 'status' => 500 )
			);
		}
		$action_taken = 'changed';

		// Si on passe à up, on déclenche le hook
		if ( $vote_type === 'up' ) {
			/**
		 * Se declenche a chaque upvote enregistre.
		 *
		 * Uniquement sur un vote POSITIF : un downvote ne doit pas nourrir la
		 * reputation de l'auteur. Emis apres l'ecriture en base et le
		 * recomptage des compteurs.
		 *
		 * @since 4.0.0
		 *
		 * @param int $post_id   Contenu vote (sujet ou reponse).
		 * @param string $vote_type Toujours 'up' pour ce hook.
		 * @param int $user_id   Auteur du vote, 0 si anonyme.
		 */
			do_action( 'swiftboard_vote_cast', $post_id, 'up', $user_id );
		}
	}

	// Rate limit : marquer le dernier vote
	set_transient( $rl_key, time(), 5 * MINUTE_IN_SECONDS );

	// Compteur quotidien
	if ( $user_id && $action_taken === 'inserted' ) {
		$today_key   = 'sb_vote_today_' . $user_id . '_' . date( 'Y-m-d' );
		$today_count = (int) get_transient( $today_key );
		set_transient( $today_key, $today_count + 1, DAY_IN_SECONDS );
	}

	// Recompter et renvoyer
	$counts = swiftboard_recount_post_votes( $post_id );

	return array(
		'action'    => $action_taken,
		'my_vote'   => ( $action_taken === 'removed' ) ? null : $vote_type,
		'score'     => $counts['score'],
		'formatted' => function_exists( 'swiftboard_format_count' ) ? swiftboard_format_count( $counts['score'] ) : $counts['score'],
		'up'        => $counts['up'],
		'down'      => $counts['down'],
	);
}
/**
 * Intervalle minimal entre deux votes selon le grade.
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return int
 */
function swiftboard_get_vote_min_interval( $user_id ) {
	$perms = swiftboard_get_user_permissions( $user_id );
	$daily = $perms['daily_vote_limit'] ?? 50;
	// 0 = illimité → intervalle court
	if ( $daily === 0 ) {
		return 1;
	}
	// Rookie : 5 sec, Membre : 3 sec, Pro : 1 sec
	$grade = swiftboard_get_user_grade( $user_id );
	switch ( $grade ) {
		case 'rookie':
			return 5;
		case 'member':
			return 3;
		case 'pro':
			return 1;
		case 'moderator':
		case 'vip':
			return 1;
		default:
			return 5;
	}
}

/**
 * swiftboard_get_user_daily_vote_limit().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return int
 */
function swiftboard_get_user_daily_vote_limit( $user_id ) {
	$perms = swiftboard_get_user_permissions( $user_id );
	return (int) ( $perms['daily_vote_limit'] ?? 50 );
}

// ============================================================================
// 5. ENDPOINTS REST API

// ============================================================================
// 6. NONCE & LOCALISATION DU SCRIPT VOTE
// ============================================================================
/**
 * On injecte les données nécessaires dans main.js via wp_localize_script.
 * Cherche le handle 'swiftboard-main' (déclaré dans functions.php).
 */
/**
 * Expose la configuration des votes SANS <script> inline.
 *
 * EXI-QUAL-06 — pourquoi pas wp_localize_script() :
 * elle emet un bloc `<script>var swiftBoardVotes = {...}</script>`, donc du
 * JS inline, incompatible avec une CSP `script-src 'self'` en enforce.
 *
 * Pourquoi pas non plus un NONCE CSP : le theme sert un cache de pages HTML
 * (inc/page-cache.php). Le nonce de l'en-tete est regenere a chaque requete
 * alors que le HTML servi est fige : les deux divergent et le navigateur
 * bloque le script. Mesure a l'appui — deux requetes consecutives, nonce
 * d'en-tete different, nonce HTML identique.
 *
 * Pourquoi pas un HASH CSP : la charge contient un nonce WP (`wp_rest`), qui
 * change a chaque session. Le hash ne serait pas stable.
 *
 * Solution : les donnees passent par des attributs `data-*` sur une balise
 * vide. Aucun script a executer, donc rien a autoriser dans la CSP, et la
 * valeur reste correcte meme servie depuis le cache car elle est rendue avec
 * le HTML.
 *
 * @return void
 */
function swiftboard_render_vote_config() {
	if ( is_admin() ) {
		return;
	}

	// Les attributs sont ecrits EN CLAIR et non generes par une boucle : un
	// controle statique (SwiftBoardAuditTest::test_2_2) verifie que chaque
	// attribut emis ici est bien lu par assets/js/votes.js. Une divergence
	// donnerait un nonce vide, donc un 403 sur tout vote connecte.
	printf(
		'<div id="sb-vote-config" hidden'
		. ' data-rest-url="%s"'
		. ' data-nonce="%s"'
		. ' data-user-id="%d"'
		. ' data-login-required="%s"'
		. ' data-rate-limited="%s"'
		. ' data-daily-limit="%s"'
		. ' data-error="%s"'
		. '></div>',
		esc_attr( esc_url_raw( rest_url( 'swiftboard/v1/' ) ) ),
		esc_attr( wp_create_nonce( 'wp_rest' ) ),
		(int) get_current_user_id(),
		esc_attr__( 'Vous devez être connecté pour voter.', 'swiftboard' ),
		esc_attr__( 'Veuillez patienter entre deux votes.', 'swiftboard' ),
		esc_attr__( 'Limite de votes quotidienne atteinte.', 'swiftboard' ),
		esc_attr__( 'Une erreur est survenue.', 'swiftboard' )
	);
}
add_action( 'wp_footer', 'swiftboard_render_vote_config', 5 );

// ============================================================================
// 7. FILTRER swiftboard_get_vote_count() POUR UTILISER LA VRAIE TABLE
// ============================================================================
/**
 * On remplace la fonction mock dans functions.php par la vraie lecture DB.
 * Comme swiftboard_get_vote_count() est déjà définie, on hook sur le filtre
 * 'swiftboard_get_vote_count' (ajouté rétroactivement dans functions.php).
 */
add_filter(
	'swiftboard_get_vote_count',
	function ( $value, $post_id ) {
		$counts = swiftboard_get_post_vote_score( $post_id );
		return $counts['score'];
	},
	10,
	2
);

// ============================================================================
// 8. NETTOYAGE RGPD À LA SUPPRESSION D'UN UTILISATEUR
// ============================================================================
/**
 * Quand un utilisateur est supprimé, on anonymise ses votes (on garde le score
 * mais on détache l'user_id). Plus conforme RGPD que la suppression pure.
 */
add_action(
	'delete_user',
	function ( $user_id ) {
		global $wpdb;
		$table = swiftboard_table( 'votes' );
		$wpdb->update(
			$table,
			array( 'user_id' => 0 ),
			array( 'user_id' => (int) $user_id ),
			array( '%d' ),
			array( '%d' )
		);
	}
);
