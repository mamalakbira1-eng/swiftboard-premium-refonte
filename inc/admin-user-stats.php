<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — stats utilisateurs admin (agrégats sans sous-requêtes corrélées).
 * CDC-PROD-FERME-05
 */
/**
 * @param int  $limit         Max rows.
 * @param int  $offset        Offset SQL.
 * @param bool $force_refresh Ignore transient.
 * @return array{rows: array<int, object>, total: int}
 */
function swiftboard_admin_query_user_stats( $limit = 100, $offset = 0, $force_refresh = false ) {
	global $wpdb;
	$limit  = max( 1, min( 200, (int) $limit ) );
	$offset = max( 0, (int) $offset );

	$cache_key = 'sb_admin_user_stats_v1_' . $limit . '_' . $offset;
	if ( ! $force_refresh ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['rows'], $cached['total'] ) ) {
			return $cached;
		}
	}

	$votes_table   = swiftboard_table( 'votes' );
	$uploads_table = swiftboard_table( 'uploads' );

	$total = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts}
         WHERE post_type IN ('topic','reply') AND post_author > 0"
	);

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from $wpdb.
	$sql = $wpdb->prepare(
		"SELECT u.ID, u.display_name, u.user_email, u.user_registered,
                COALESCE(t.topics, 0) AS topics,
                COALESCE(r.replies, 0) AS replies,
                COALESCE(i.images, 0) AS images,
                COALESCE(v.votes, 0) AS votes,
                (COALESCE(t.topics, 0) + COALESCE(r.replies, 0)) AS activity
         FROM {$wpdb->users} u
         INNER JOIN (
            SELECT DISTINCT post_author AS uid
            FROM {$wpdb->posts}
            WHERE post_type IN ('topic','reply') AND post_author > 0
         ) a ON a.uid = u.ID
         LEFT JOIN (
            SELECT post_author AS uid, COUNT(*) AS topics
            FROM {$wpdb->posts}
            WHERE post_type = 'topic' AND post_status = 'publish'
            GROUP BY post_author
         ) t ON t.uid = u.ID
         LEFT JOIN (
            SELECT post_author AS uid, COUNT(*) AS replies
            FROM {$wpdb->posts}
            WHERE post_type = 'reply' AND post_status = 'publish'
            GROUP BY post_author
         ) r ON r.uid = u.ID
         LEFT JOIN (
            SELECT user_id AS uid, COUNT(*) AS images
            FROM {$uploads_table}
            GROUP BY user_id
         ) i ON i.uid = u.ID
         LEFT JOIN (
            SELECT user_id AS uid, COUNT(*) AS votes
            FROM {$votes_table}
            GROUP BY user_id
         ) v ON v.uid = u.ID
         ORDER BY activity DESC, u.ID ASC
         LIMIT %d OFFSET %d",
		$limit,
		$offset
	);

	$rows = $wpdb->get_results( $sql );
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	$out = array(
		'rows'  => $rows,
		'total' => $total,
	);
	set_transient( $cache_key, $out, 5 * MINUTE_IN_SECONDS );
	return $out;
}

/**
 * Invalide le cache des stats users admin.
 *
 * @return void
 */
function swiftboard_admin_flush_user_stats_cache() {
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_sb_admin_user_stats_v1_%'
            OR option_name LIKE '_transient_timeout_sb_admin_user_stats_v1_%'"
	);
}
