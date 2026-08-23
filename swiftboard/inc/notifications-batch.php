<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — batch notifications inserts (CDC SQL-05).
 * Extracted to keep notifications.php under ArchitectureTest 500-line cap.
 */
/**
 * Insert groupé de notifications (followers fan-out).
 *
 * @param array<int, array<string, mixed>> $rows Liste d'args style swiftboard_add_notification.
 * @return int Nombre de lignes tentées.
 */
function swiftboard_add_notifications_batch( array $rows ) {
	global $wpdb;
	$table = swiftboard_table( 'notifications' );
	if ( empty( $rows ) ) {
		return 0;
	}

	$clean = array();
	$now   = current_time( 'mysql' );
	foreach ( $rows as $args ) {
		$user_id  = (int) ( $args['user_id'] ?? 0 );
		$actor_id = (int) ( $args['actor_id'] ?? 0 );
		if ( ! $user_id ) {
			continue;
		}
		if ( $actor_id && $actor_id === $user_id ) {
			continue;
		}
		$clean[] = array(
			$user_id,
			$actor_id,
			sanitize_text_field( $args['type'] ?? 'generic' ),
			(int) ( $args['post_id'] ?? 0 ),
			sanitize_text_field( $args['post_type'] ?? '' ),
			sanitize_text_field( $args['title'] ?? '' ),
			sanitize_text_field( $args['excerpt'] ?? '' ),
			0,
			$now,
		);
	}
	if ( empty( $clean ) ) {
		return 0;
	}

	$row_sql = '(%d, %d, %s, %d, %s, %s, %s, %d, %s)';
	foreach ( array_chunk( $clean, 50 ) as $batch ) {
		$placeholders = implode( ', ', array_fill( 0, count( $batch ), $row_sql ) );
		$params       = array();
		foreach ( $batch as $row ) {
			foreach ( $row as $v ) {
				$params[] = $v;
			}
		}
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (user_id, actor_id, type, post_id, post_type, title, excerpt, is_read, created_at) VALUES {$placeholders}",
				$params
			)
		);
		foreach ( $batch as $row ) {
			delete_transient( 'sb_notif_unread_' . (int) $row[0] );
		}
	}
	return count( $clean );
}
