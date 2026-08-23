<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Système de « Subreddits » (communautés rejointes)
 *
 * Transforme les forums/sous-forums bbPress en subreddits :
 *  - Bouton « ➕ Rejoindre » / « ✓ Abonné » sur chaque forum
 *  - Endpoint AJAX pour rejoindre/quitter
 *  - Compteur de membres
 *  - Feed personnalisé « Mes subreddits » sur la home (connecté)
 *
 * STOCKAGE DE REPLI ROBUSTE (v2)
 * -----------------------------
 * L'abonnement est stocké dans `user_meta('swiftboard_subreddits')` = tableau
 * de forum_ids. Ce stockage est INDÉPENDANT de bbPress :
 *  - Il fonctionne même si la table `bbp_engagements` n'existe pas.
 *  - Il fonctionne quelle que soit la stratégie d'engagements de bbPress
 *    (`meta` ou `user`), et même si bbPress (version alpha) a un bug d'écriture.
 *  - Plus de dépendance fragile à l'implémentation interne de bbPress.
 *
 * @package SwiftBoard
 */
// ============================================================================
// 0. STOCKAGE DE REPLI (usermeta) — source de vérité
// ============================================================================
/**
 * Récupère les IDs des subreddits (forums) rejoints par un utilisateur.
 *
 * @param int $user_id ID de l'utilisateur.
 * @return int[] Tableau de forum_ids.
 */
function swiftboard_subreddit_get_followed( int $user_id = 0 ): array {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id) return array();
	$followed = get_user_meta( $user_id, 'swiftboard_subreddits', true );
	if ( ! is_array( $followed )) return array();
	return array_values( array_map( 'intval', array_filter( $followed ) ) );
}

/**
 * Vérifie si un utilisateur a rejoint un subreddit.
 *
 * @param int $user_id  ID de l'utilisateur.
 * @param int $forum_id ID du forum.
 * @return bool
 */
function swiftboard_subreddit_is_member( int $user_id, int $forum_id ): bool {
	return in_array( (int) $forum_id, swiftboard_subreddit_get_followed( $user_id ), true );
}

/**
 * Ajoute un utilisateur à un subreddit.
 *
 * @param int $user_id  ID de l'utilisateur.
 * @param int $forum_id ID du forum.
 * @return bool
 */
function swiftboard_subreddit_add( int $user_id, int $forum_id ): bool {
	$user_id  = (int) $user_id;
	$forum_id = (int) $forum_id;
	if ( ! $user_id || ! $forum_id) return false;

	$followed = swiftboard_subreddit_get_followed( $user_id );
	if ( in_array( $forum_id, $followed, true ) ) {
		return true; // déjà membre
	}
	$followed[] = $forum_id;
	return update_user_meta( $user_id, 'swiftboard_subreddits', $followed );
}

/**
 * Retire un utilisateur d'un subreddit.
 *
 * @param int $user_id  ID de l'utilisateur.
 * @param int $forum_id ID du forum.
 * @return bool
 */
function swiftboard_subreddit_remove( int $user_id, int $forum_id ): bool {
	$user_id  = (int) $user_id;
	$forum_id = (int) $forum_id;
	if ( ! $user_id || ! $forum_id) return false;

	$followed = swiftboard_subreddit_get_followed( $user_id );
	$followed = array_values( array_diff( $followed, array( $forum_id ) ) );
	return update_user_meta( $user_id, 'swiftboard_subreddits', $followed );
}

// ============================================================================
// 1. BOUTON « S’ABONNER / ABONNÉ »
// ============================================================================
/**
 * Affiche le bouton d'abonnement (subreddit) pour un forum.
 *
 * @param int $forum_id ID du forum.
 * @return string HTML du bouton.
 */
function swiftboard_subreddit_join_button( int $forum_id ): string {
	$forum_id = (int) $forum_id;
	if ( ! $forum_id) return '';

	// Non connecté : bouton vers la connexion.
	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( get_permalink( $forum_id ) );
		return '<a href="' . esc_url( $login_url ) . '" class="sb-join-btn" data-forum-id="' . esc_attr( $forum_id ) . '">'
			. '<span class="sb-join-plus">＋</span> ' . esc_html__( 'S’abonner', 'swiftboard' ) . '</a>';
	}

	$uid       = get_current_user_id();
	$is_member = swiftboard_subreddit_is_member( $uid, $forum_id );

	$nonce = wp_create_nonce( 'sb_subreddit_' . $uid );

	if ( $is_member ) {
		return '<button type="button" class="sb-join-btn active" data-forum-id="' . esc_attr( $forum_id )
			. '" data-nonce="' . esc_attr( $nonce ) . '" aria-pressed="true">'
			. '<span class="sb-join-check">✓</span> ' . esc_html__( 'Abonné', 'swiftboard' ) . '</button>';
	}

	return '<button type="button" class="sb-join-btn" data-forum-id="' . esc_attr( $forum_id )
		. '" data-nonce="' . esc_attr( $nonce ) . '" aria-pressed="false">'
		. '<span class="sb-join-plus">＋</span> ' . esc_html__( 'S’abonner', 'swiftboard' ) . '</button>';
}

// ============================================================================
// 2. COMPTEUR DE MEMBRES
// ============================================================================
/**
 * Nombre de membres abonnés à un forum.
 *
 * On compte les usermeta `swiftboard_subreddits` qui contiennent le forum_id.
 * C'est indépendant de la table bbPress.
 *
 * @param int $forum_id ID du forum.
 * @return int Nombre d'abonnés.
 */
function swiftboard_subreddit_member_count_raw( int $forum_id ): int {
	global $wpdb;
	$forum_id = (int) $forum_id;
	if ( ! $forum_id) return 0;

	// v9.4 — Nombre de membres fixable par l'admin (meta _swiftboard_fake_members)
	$fake = (int) get_post_meta( $forum_id, '_swiftboard_fake_members', true );
	if ( $fake > 0 ) {
		return $fake;
	}

	// Compter via la meta LIKE : tous les users dont swiftboard_subreddits
	// contient ce forum_id. On cherche la sérialisation PHP.
	// format: a:N:{...i:X;i:20;...}  -> on cherche "i:20;"
	$pattern = $wpdb->esc_like( 'i:' . $forum_id . ';' );
	$count   = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
         WHERE meta_key = 'swiftboard_subreddits'
           AND meta_value LIKE %s",
			'%' . $pattern . '%'
		)
	);
	return (int) $count;
}

/**
 * Compteur formaté de membres d'un subreddit.
 *
 * @param int $forum_id ID du forum.
 * @return string Ex. « 1,2k membres ».
 */
function swiftboard_subreddit_member_count( int $forum_id ): string {
	$count = swiftboard_subreddit_member_count_raw( $forum_id );
	$fmt   = function_exists( 'swiftboard_format_count' ) ? swiftboard_format_count( $count ) : number_format_i18n( $count );
	return sprintf( __( '%s membres', 'swiftboard' ), $fmt );
}

// ============================================================================
// 3. ENDPOINT REST — rejoindre / quitter
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/subreddit',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'callback'            => function ( WP_REST_Request $req ) {
					// Connexion requise (déjà garantie par permission_callback, double garde).
					if ( ! is_user_logged_in() ) {
						return new WP_REST_Response( array( 'message' => 'not_logged_in' ), 403 );
					}

					$uid      = get_current_user_id();
					$forum_id = (int) $req->get_param( 'forum_id' );

					// Vérification nonce (direct, sans sanitize qui casse le nonce).
					$nonce = sanitize_text_field( (string) $req->get_param( 'nonce' ) );
					if ( ! wp_verify_nonce( $nonce, 'sb_subreddit_' . $uid ) ) {
						return new WP_REST_Response( array( 'message' => 'bad_nonce' ), 403 );
					}

					if ( ! $forum_id || get_post_type( $forum_id ) !== 'forum' ) {
						return new WP_REST_Response( array( 'message' => 'invalid_forum' ), 400 );
					}

					// Toggle via notre stockage usermeta.
					$was_member = swiftboard_subreddit_is_member( $uid, $forum_id );
					if ( $was_member ) {
						swiftboard_subreddit_remove( $uid, $forum_id );
						$joined = false;
					} else {
						swiftboard_subreddit_add( $uid, $forum_id );
						$joined = true;
					}

					return new WP_REST_Response(
						array(
							'joined'       => $joined,
							'member_count' => swiftboard_subreddit_member_count( $forum_id ),
						),
						200
					);
				},
			)
		);
	}
);

// ============================================================================
// 4. JS — gestion du clic sur « Rejoindre »
// CSP-safe: externalisé vers assets/js/join-btn.js, config via data-*.
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		wp_enqueue_script(
			'swiftboard-join-btn',
			SWIFTBOARD_ASSETS . '/js/join-btn.js',
			array(),
			SWIFTBOARD_VERSION,
			true
		);
		// Config via data-* (CSP-safe)
		add_action( 'wp_footer', 'swiftboard_print_join_config', 5 );
	}
);

// ============================================================================
// 5. FEED PERSONNALISÉ « MES SUBREDDITS » (home, connecté)
// ============================================================================
/**
 * Récupère les derniers sujets des forums rejoints par l'utilisateur.
 *
 * @param int $user_id ID de l'utilisateur.
 * @param int $limit   Nombre de sujets max.
 * @return array<int, int> IDs des sujets (triés par date DESC).
 */
function swiftboard_get_followed_subreddit_topics( int $user_id, int $limit = 15 ): array {
	$user_id = (int) $user_id;
	if ( ! $user_id) return array();

	// Forums rejoints (via notre stockage usermeta robuste).
	$forums = swiftboard_subreddit_get_followed( $user_id );
	if (empty( $forums )) return array();

	$query = new WP_Query(
		array(
			'post_type'              => 'topic',
			'post_status'            => 'publish',
			'post_parent__in'        => $forums,
			'posts_per_page'         => $limit,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		)
	);

	return wp_list_pluck( $query->posts, 'ID' );
}

// ============================================================================
// 6. SUBREDDITS POPULAIRES (pour la home, tout le monde)
// ============================================================================
/**
 * Retourne les subreddits (forums + sous-forums) les plus populaires.
 * Trié par nombre de sujets décroissant (popularité simple).
 *
 * @param int $limit Nombre max de subreddits.
 * @return array<int, array{id:int,title:string,url:string,topic_count:int,parent_title:string}>
 */
function swiftboard_get_popular_subreddits( int $limit = 8 ): array {
	global $wpdb;
	$limit = max( 1, min( 20, (int) $limit ) );

	// Compter les sujets par forum (y compris sous-forums via post_parent)
	$sql  = $wpdb->prepare(
		"SELECT f.ID,
                f.post_title,
                f.post_parent,
                (SELECT COUNT(*) FROM {$wpdb->posts} t
                 WHERE t.post_type = 'topic' AND t.post_status = 'publish'
                   AND t.post_parent = f.ID) AS topic_count
         FROM {$wpdb->posts} f
         WHERE f.post_type = 'forum' AND f.post_status = 'publish'
         ORDER BY topic_count DESC, f.post_title ASC
         LIMIT %d",
		$limit
	);
	$rows = $wpdb->get_results( $sql );

	if (empty( $rows )) return array();

	// Précharger les titres des parents pour la hiérarchie
	$parent_ids    = array_filter( array_map( 'intval', wp_list_pluck( $rows, 'post_parent' ) ) );
	$parent_titles = array();
	if ( ! empty( $parent_ids ) ) {
		$ph = implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) );
		$parents = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ({$ph})", $parent_ids ) );
		foreach ( $parents as $p ) {
			$parent_titles[ (int) $p->ID ] = $p->post_title;
		}
	}

	$result = array();
	foreach ( $rows as $r ) {
		$parent   = (int) $r->post_parent;
		$result[] = array(
			'id'           => (int) $r->ID,
			'title'        => $r->post_title,
			'parent_title' => isset( $parent_titles[ $parent ] ) ? $parent_titles[ $parent ] : '',
			'url'          => get_permalink( (int) $r->ID ),
			'topic_count'  => (int) $r->topic_count,
		);
	}
	return $result;
}

// ============================================================================
// v9.4 — Champ "Nombre de membres" dans l'éditeur de forum (WP Admin)
// ============================================================================
add_action('add_meta_boxes', function() {
    add_meta_box(
        'swiftboard_forum_members',
        __('Nombre de membres (communauté)', 'swiftboard'),
        'swiftboard_forum_members_metabox',
        'forum',
        'side',
        'default'
    );
});

function swiftboard_forum_members_metabox($post) {
    wp_nonce_field('swiftboard_forum_members', 'swiftboard_forum_members_nonce');
    $fake_members = get_post_meta($post->ID, '_swiftboard_fake_members', true);
    echo '<p>';
    echo '<label for="swiftboard_fake_members"><strong>' . esc_html__('Nombre de membres affiché :', 'swiftboard') . '</strong></label><br>';
    echo '<input type="number" id="swiftboard_fake_members" name="swiftboard_fake_members" value="' . esc_attr($fake_members) . '" min="0" style="width:100%;margin-top:4px;">';
    echo '</p>';
    echo '<p class="description">' . esc_html__('Laissez vide pour le nombre réel. Mettez un nombre (ex: 1500) pour afficher un nombre fixe de membres.', 'swiftboard') . '</p>';
}

add_action('save_post_forum', function($post_id) {
    if (!isset($_POST['swiftboard_forum_members_nonce']) || !wp_verify_nonce($_POST['swiftboard_forum_members_nonce'], 'swiftboard_forum_members')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fake = (int) ($_POST['swiftboard_fake_members'] ?? 0);
    if ($fake > 0) {
        update_post_meta($post_id, '_swiftboard_fake_members', $fake);
    } else {
        delete_post_meta($post_id, '_swiftboard_fake_members');
    }
});

/**
 * Imprime la div de config pour le bouton Rejoindre (CSP-safe, data-*).
 */
function swiftboard_print_join_config() {
	printf(
		'<div id="sb-join-config" hidden data-rest-url="%s" data-label-joined="%s" data-label-join="%s"></div>',
		esc_attr( esc_url_raw( rest_url() ) ),
		esc_attr( __( '✓ Abonné', 'swiftboard' ) ),
		esc_attr( __( '＋ S’abonner', 'swiftboard' ) )
	);
}
