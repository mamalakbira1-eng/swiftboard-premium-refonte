<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard - Actions utilisateur sur le contenu
 *
 * Permet à un utilisateur de :
 *  - 🔖 Sauvegarder un sujet (page "Saved")
 *  - 👁️ Cacher un sujet (disparaît du feed)
 *  - ⭐ Suivre un sujet (notifications sur nouvelles réponses)
 *
 * Stockage : meta user avec tableau sérialisé (un seul meta key par action).
 *  - swiftboard_saved_topics   (array of topic IDs)
 *  - swiftboard_hidden_topics  (array of topic IDs)
 *  - swiftboard_followed_topics (array of topic IDs)
 *
 * Limite : 1000 sujets par action (rotation FIFO au-delà).
 *
 * @package SwiftBoard
 * @since 3.3.0
 */
// ============================================================================
// 0. TABLE DÉDIÉE swiftboard_followers (anti N+1)
// ============================================================================
/**
 * @return void
 */
function swiftboard_create_followers_table() {
	global $wpdb;
	$table           = swiftboard_table( 'followers' );
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        topic_id    BIGINT(20) UNSIGNED NOT NULL,
        user_id     BIGINT(20) UNSIGNED NOT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_follow (topic_id, user_id),
        KEY idx_topic (topic_id),
        KEY idx_user (user_id)
    ) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
add_action(
	'admin_init',
	function () {
		global $wpdb;
		$table = swiftboard_table( 'followers' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			swiftboard_create_followers_table();
		}
	}
);
add_action( 'after_switch_theme', 'swiftboard_create_followers_table' );

/**
 * Migrer les followers depuis usermeta vers la table dédiée.
 * Appelé une fois à l'activation du thème.
 *
 * @return void
 */
function swiftboard_migrate_followers_to_table() {
	global $wpdb;
	$table = swiftboard_table( 'followers' );

	// Vérifier si la migration a déjà été faite
	if ( get_option( 'swiftboard_followers_migrated', false ) ) {
		return;
	}

	// Récupérer tous les users qui ont la meta swiftboard_followed_topics
	$users = get_users(
		array(
			'meta_key'     => 'swiftboard_followed_topics',
			'meta_compare' => 'EXISTS',
			'number'       => 5000,
			'fields'       => 'ID',
		)
	);

	foreach ( $users as $uid ) {
		$followed = swiftboard_get_followed_topics( $uid );
		if ( ! is_array( $followed ) ) {
			continue;
		}
		foreach ( $followed as $topic_id ) {
			$wpdb->replace(
				$table,
				array(
					'topic_id' => (int) $topic_id,
					'user_id'  => (int) $uid,
				),
				array( '%d', '%d' )
			);
		}
	}

	update_option( 'swiftboard_followers_migrated', true, false );
}
add_action( 'after_switch_theme', 'swiftboard_migrate_followers_to_table', 20 );
/**
 * Récupère les followers d'un topic via la table dédiée (1 requête au lieu de N).
 *
 * @param int $topic_id Identifiant du sujet.
 * @return mixed
 */
function swiftboard_get_topic_followers( $topic_id ) {
	global $wpdb;
	$table = swiftboard_table( 'followers' );
	return $wpdb->get_col(
		$wpdb->prepare(
			"SELECT user_id FROM {$table} WHERE topic_id = %d",
			$topic_id
		)
	);
}

// ============================================================================
// 1. HELPERS — Get / Set / Toggle
// ============================================================================
/**
 * swiftboard_get_saved_topics().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return mixed
 */
function swiftboard_get_saved_topics( $user_id ) {
	$list = get_user_meta( $user_id, 'swiftboard_saved_topics', true );
	return is_array( $list ) ? $list : array();
}
/**
 * swiftboard_get_hidden_topics().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return mixed
 */
function swiftboard_get_hidden_topics( $user_id ) {
	$list = get_user_meta( $user_id, 'swiftboard_hidden_topics', true );
	return is_array( $list ) ? $list : array();
}
/**
 * swiftboard_get_followed_topics().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return mixed
 */
function swiftboard_get_followed_topics( $user_id ) {
	$list = get_user_meta( $user_id, 'swiftboard_followed_topics', true );
	return is_array( $list ) ? $list : array();
}

/**
 * swiftboard_set_user_action().
 *
 * @param int   $user_id  Identifiant de l'utilisateur.
 * @param mixed $action   À documenter.
 * @param int   $topic_id Identifiant du sujet.
 * @param mixed $add      À documenter.
 * @return mixed
 */
function swiftboard_set_user_action( $user_id, $action, $topic_id, $add ) {
	$meta_key = 'swiftboard_' . $action . '_topics';
	$list     = get_user_meta( $user_id, $meta_key, true );
	if ( ! is_array( $list ) ) {
		$list = array();
	}

	if ( $add ) {
		if ( ! in_array( (int) $topic_id, $list, true ) ) {
			array_unshift( $list, (int) $topic_id );
			if ( count( $list ) > 1000 ) {
				$list = array_slice( $list, 0, 1000 );
			}
		}
	} else {
		$list = array_values( array_diff( $list, array( (int) $topic_id ) ) );
	}
	update_user_meta( $user_id, $meta_key, $list );

	// Double écriture vers la table dédiée pour les followers (anti N+1)
	if ( $action === 'followed' ) {
		global $wpdb;
		$table = swiftboard_table( 'followers' );
		if ( $add ) {
			$wpdb->replace(
				$table,
				array(
					'topic_id' => (int) $topic_id,
					'user_id'  => (int) $user_id,
				),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->delete(
				$table,
				array(
					'topic_id' => (int) $topic_id,
					'user_id'  => (int) $user_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	return $add;
}

/**
 * swiftboard_is_saved().
 *
 * @param int $user_id  Identifiant de l'utilisateur.
 * @param int $topic_id Identifiant du sujet.
 * @return mixed
 */
function swiftboard_is_saved( $user_id, $topic_id ) {
	return in_array( (int) $topic_id, swiftboard_get_saved_topics( $user_id ), true );
}
/**
 * swiftboard_is_hidden().
 *
 * @param int $user_id  Identifiant de l'utilisateur.
 * @param int $topic_id Identifiant du sujet.
 * @return mixed
 */
function swiftboard_is_hidden( $user_id, $topic_id ) {
	return in_array( (int) $topic_id, swiftboard_get_hidden_topics( $user_id ), true );
}
/**
 * swiftboard_is_followed().
 *
 * @param int $user_id  Identifiant de l'utilisateur.
 * @param int $topic_id Identifiant du sujet.
 * @return mixed
 */
function swiftboard_is_followed( $user_id, $topic_id ) {
	return in_array( (int) $topic_id, swiftboard_get_followed_topics( $user_id ), true );
}

// ============================================================================
// 2. FILTRER LE FEED — Cacher les topics "hidden"
// ============================================================================
add_filter(
	'bbp_has_topics_query',
	function ( $args ) {
		if ( ! is_user_logged_in() ) {
			return $args;
		}
		if ( is_admin() ) {
			return $args;
		}
		$user_id = get_current_user_id();
		$hidden  = swiftboard_get_hidden_topics( $user_id );
		if ( ! empty( $hidden ) ) {
			$args['post__not_in'] = ! empty( $args['post__not_in'] ) ? array_merge( $args['post__not_in'], $hidden ) : $hidden;
		}
		return $args;
	}
);

// ============================================================================
// 3. ENDPOINTS REST API
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/user-action',
			array(
				'methods'             => 'POST',
				'callback'            => 'swiftboard_rest_user_action',
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'topic_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'action'   => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'saved', 'hidden', 'followed' ),
					),
					'add'      => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'swiftboard/v1',
			'/user-actions/(?P<action>[a-z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => 'swiftboard_rest_get_user_actions',
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}
);



// ============================================================================
// 4. JAVASCRIPT — Branchement des boutons save/hide/follow
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		wp_enqueue_script(
			'swiftboard-user-content-actions',
			SWIFTBOARD_URI . '/assets/js/user-content-actions.js',
			array( 'swiftboard-main' ),
			defined( 'SWIFTBOARD_VERSION' ) ? SWIFTBOARD_VERSION : null,
			true
		);
		add_action(
			'wp_footer',
			static function () {
				printf(
					'<div id="sb-user-actions-config" hidden data-rest-url="%s" data-nonce="%s"></div>',
					esc_attr( esc_url_raw( rest_url( 'swiftboard/v1/' ) ) ),
					esc_attr( wp_create_nonce( 'wp_rest' ) )
				);
			},
			5
		);
	},
	40
);

// ============================================================================
// 5. PAGES "SAVED" / "HIDDEN" / "FOLLOWING"
// ============================================================================
/*
 * FILTRE MORT SUPPRIME — 'bbp_get_template_includes' n'existe pas.
 *
 * Le code posait un add_filter() sur ce nom de hook. bbPress 2.6.14 ne le
 * declenche NULLE PART : les filtres reellement exposes autour de la pile de
 * gabarits sont 'bbp_get_template_stack', 'bbp_get_template_part' et
 * 'bbp_get_template_locations'. Le callback n'etait donc jamais appele.
 *
 * Ce n'est pas une regression a corriger mais du code mort a retirer :
 * l'interception des vues /saved, /hidden et /following est deja assuree par
 * le hook 'template_redirect' ci-dessous, qui rend la page lui-meme.
 * Verifie sur WordPress reel, connecte : les trois vues renvoient bien leur
 * conteneur .sb-user-content-list et leur titre, sans ce filtre.
 *
 * Detecte par le controle de compatibilite qa/bbpress-compat.php, qui croise
 * les 32 hooks utilises par le theme avec ceux reellement declenches par la
 * version de bbPress installee.
 */

/**
 * Page dédiée pour la liste. URL : /forum/?sb_user_view=saved
 * On hook aussi sur template_redirect pour intercepter avant bbPress.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET['sb_user_view'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) );
			exit;
		}
		$view = sanitize_text_field( wp_unslash( $_GET['sb_user_view'] ) );
		if ( ! in_array( $view, array( 'saved', 'hidden', 'following' ), true ) ) {
			return;
		}

		get_header();
		echo '<div class="sb-user-content-list">';
		$titles = array(
			'saved'     => '🔖 Sujets sauvegardés',
			'hidden'    => '👁️ Sujets cachés',
			'following' => '⭐ Sujets suivis',
		);
		// $view est deja restreint aux trois cles ci-dessus par le in_array()
		// strict en debut de fonction : le repli etait mort.
		echo '<h1>' . esc_html( $titles[ $view ] ) . '</h1>';

		$topics = swiftboard_get_user_topics_list( get_current_user_id(), $view );
		if ( empty( $topics ) ) {
			echo '<p style="text-align:center;color:#6b6b75;padding:40px;">' . esc_html__( 'Aucun sujet dans cette liste pour le moment.', 'swiftboard' ) . '</p>';
		} else {
			echo '<div class="sb-user-list">';
			foreach ( $topics as $t ) {
				echo '<article class="sb-user-list-item">';
				echo '<h2><a href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['title'] ) . '</a></h2>';
				echo '<div style="font-size:12px;color:#6b6b75;">';
				echo 'par ' . esc_html( $t['author_name'] ) . ' · ▲ ' . (int) $t['votes'] . ' · 💬 ' . (int) $t['replies'];
				echo '</div>';
				echo '</article>';
			}
			echo '</div>';
		}
		echo '</div>';
		get_footer();
		exit;
	}
);

// ============================================================================
// 6. MENU DANS LA BARRE DE NAVIGATION
// ============================================================================
add_action(
	'wp_nav_menu_items',
	function ( $items, $args ) {
		if ( ! is_user_logged_in() ) {
			return $items;
		}
		if ( $args->theme_location !== 'primary' && $args->theme_location !== 'swiftboard-primary' ) {
			return $items;
		}
		$user_url = home_url( '/?sb_user_view=saved' );
		$items   .= '<li class="menu-item"><a href="' . esc_url( $user_url ) . '">🔖 Sauvegardés</a></li>';
		return $items;
	},
	10,
	2
);

// ============================================================================
// 7. NOTIFICATION SUR NOUVELLE RÉPONSE POUR LES "FOLLOWED"
// ============================================================================
// Bloc RETIRE : il notifiait les abonnes sur bbp_new_reply (priorite 30) alors
// que inc/notifications.php le fait deja (priorite 20), sans aucune garde entre
// les deux. Chaque abonne recevait donc DEUX notifications par reponse —
// constate en base : 2 lignes pour un seul do_action('bbp_new_reply').
//
// La version conservee est celle de notifications.php : elle est plus stricte
// (elle exclut l'auteur du sujet, deja notifie par ailleurs, en plus de l'auteur
// de la reponse) et elle utilise la meme table dediee swiftboard_followers,
// donc sans regression de performance (1 requete, pas de N+1).
//
// Non-regression couverte par BugsRapportTiersTest.
