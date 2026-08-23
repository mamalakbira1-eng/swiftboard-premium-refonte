<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Optimisations DB v4.2
 *
 * Ajoute les index composites recommandés par l'audit UX/Perf pour la grande échelle :
 * - wp_posts : index composite (post_type, post_status, post_date) pour accélérer les loops bbPress
 * - wp_postmeta : index composite (meta_key, post_id) pour `_swiftboard_vote_score` et `_bbp_reply_count`
 * - swiftboard_votes : index composite (post_author, vote_type) pour les requêtes de réputation
 * - swiftboard_notifications : index composite (user_id, is_read, created_at) pour la pagination cursor
 *
 * Idempotent : ne crée l'index que s'il n'existe pas déjà.
 *
 * @package SwiftBoard
 */
/**
 * Crée les index manquants sur la DB.
 * À appeler une fois (activation du thème ou via WP-CLI).
 *
 * @return array<string, mixed>
 */
function swiftboard_apply_db_indexes() {
	global $wpdb;

	$indexes = array(
		// wp_posts : accélère "ORDER BY post_date DESC WHERE post_type='topic' AND post_status='publish'"
		// → utile pour /forums/ et la home feed
		'wp_posts_idx_type_status_date'  => array(
			'table' => $wpdb->posts,
			'sql'   => 'ALTER TABLE %1$s ADD INDEX %2$s (post_type, post_status, post_date)',
		),
		// wp_postmeta : accélère ORDER BY meta_value_num sur _swiftboard_vote_score
		'wp_postmeta_idx_key_value'      => array(
			'table' => $wpdb->postmeta,
			'sql'   => 'ALTER TABLE %1$s ADD INDEX %2$s (meta_key, meta_value(32))',
		),
		// swiftboard_votes : accélère "SELECT COUNT(*) WHERE p.post_author=X AND v.vote_type='up'"
		'wp_votes_idx_author_type'       => array(
			'table' => swiftboard_table( 'votes' ),
			'sql'   => 'ALTER TABLE %1$s ADD INDEX %2$s (post_author, vote_type)',
		),
		// swiftboard_notifications : accélère la pagination cursor par user
		'wp_notif_idx_user_read_created' => array(
			'table' => swiftboard_table( 'notifications' ),
			'sql'   => 'ALTER TABLE %1$s ADD INDEX %2$s (user_id, is_read, created_at)',
		),
	);

	$applied = 0;
	$skipped = 0;
	$errors  = array();

	foreach ( $indexes as $index_name => $def ) {
		$table = $def['table'];

		// Vérifier que la table existe
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		) === $table;
		if ( ! $table_exists ) {
			$errors[] = "Table $table n'existe pas (skip $index_name)";
			continue;
		}

		// Vérifier si l'index existe déjà
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name = %s
               AND index_name = %s',
				$table,
				$index_name
			)
		);

		if ( $existing > 0 ) {
			++$skipped;
			continue;
		}

		// Créer l'index
		// EXI-SEC-03 — safe : $table et $index_name proviennent d'un tableau
		// de definitions codees en dur dans ce fichier (aucune entree
		// utilisateur). $wpdb->prepare() ne sait pas traiter les identifiants
		// SQL (noms de table/index), seulement les valeurs.
		$sql = sprintf( $def['sql'], $table, $index_name );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( $sql );
		if ( $result === false ) {
			$errors[] = "Échec création $index_name sur $table : " . $wpdb->last_error;
		} else {
			++$applied;
		}
	}

	return array(
		'applied' => $applied,
		'skipped' => $skipped,
		'errors'  => $errors,
	);
}

// Appliquer les index à l'activation du thème.
// swiftboard_apply_db_indexes() renvoie un rapport (index crees / ignores /
// erreurs), exploite par l'ecran d'administration ci-dessous ; un callback
// d'action ne doit rien retourner, d'ou l'enveloppe. Contrairement a
// swiftboard_page_cache_flush(), aucune suite ne verifie cette accroche par
// has_action() : l'enveloppe ne desarme donc aucun garde-fou.
add_action(
	'after_switch_theme',
	static function () {
		swiftboard_apply_db_indexes();
	}
);

// Aussi via un endpoint admin dédié (déclenchable manuellement)
add_action(
	'admin_init',
	function () {
		if ( isset( $_GET['swiftboard_apply_indexes'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'sb_indexes' ) ) {
			$result = swiftboard_apply_db_indexes();
			set_transient( 'swiftboard_indexes_result', $result, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=swiftboard-admin' ) );
			exit;
		}
	}
);


// Composite indexes for frequent queries
// v7.3.1 — déplacé de init vers after_switch_theme + admin_init
// (init = chaque page load = SHOW INDEX sur chaque page = SQL inutile)
add_action('after_switch_theme', 'swiftboard_db_optimize_on_switch');

/**
 * Crée les index composites sur les tables custom après activation du thème.
 */
function swiftboard_db_optimize_on_switch() {
    global $wpdb;

    $tables = [
        'swiftboard_votes' => [
            'idx_votes_post_user' => '(post_id, user_id)',
            'idx_votes_type' => '(vote_type, post_id)',
        ],
        'swiftboard_notifications' => [
            'idx_notif_user_read' => '(user_id, is_read)',
        ],
        'swiftboard_reports' => [
            'idx_reports_status' => '(status, created_at)',
        ],
    ];

    foreach ($tables as $table => $indexes) {
        $full_table = $wpdb->prefix . $table;

        // Check if table exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s", $full_table
        ));
        if (!$exists) continue;

        foreach ($indexes as $index_name => $columns) {
            // Check if index already exists
            $existing = $wpdb->get_var(
                "SHOW INDEX FROM {$full_table} WHERE Key_name = '{$index_name}'"
            );
            if ($existing) continue;

            $wpdb->query("CREATE INDEX {$index_name} ON {$full_table} {$columns}");
        }
    }
}

// Vérification en admin seulement (pas en front)
add_action('admin_init', 'swiftboard_db_optimize_on_admin');

/**
 * Vérifie et crée les index composites manquants en contexte admin.
 */
function swiftboard_db_optimize_on_admin() {
    if (!current_user_can('manage_options')) return;
    swiftboard_db_optimize_on_switch();
}
