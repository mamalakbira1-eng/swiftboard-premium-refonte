<?php
if ( ! defined( 'ABSPATH' ) ) { exit( 'WordPress non chargé' ); }
global $wpdb;
$user = get_user_by( 'login', 'sbmember' );
$actor = get_user_by( 'login', 'sbadmin' );
if ( ! $user ) { exit( "Compte sbmember absent\n" ); }
$user_id = (int) $user->ID;
$actor_id = $actor ? (int) $actor->ID : 1;
$table = swiftboard_table( 'notifications' );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d AND title LIKE %s", $user_id, 'QA SSE %' ) );
for ( $i = 1; $i <= 20; $i++ ) {
    $wpdb->insert(
        $table,
        array(
            'user_id'    => $user_id,
            'actor_id'   => $actor_id,
            'type'       => 'mention',
            'post_id'    => 0,
            'post_type'  => 'topic',
            'title'      => 'QA SSE ' . $i,
            'excerpt'    => 'Synthetic local SSE validation event ' . $i,
            'is_read'    => 0,
            'created_at' => current_time( 'mysql' ),
        ),
        array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
    );
}
echo wp_json_encode( array( 'user_id' => $user_id, 'inserted' => 20, 'last_id' => (int) $wpdb->insert_id ), JSON_PRETTY_PRINT ), PHP_EOL;
