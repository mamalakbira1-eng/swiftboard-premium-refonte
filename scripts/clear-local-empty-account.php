<?php
if ( ! defined( 'ABSPATH' ) ) { exit( 'WordPress non chargé' ); }
global $wpdb;
$username = 'sbempty';
$user = get_user_by( 'login', $username );
if ( ! $user ) { exit( "Compte sbempty absent\n" ); }
$user_id = (int) $user->ID;
$table = swiftboard_table( 'notifications' );
$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d", $user_id ) );
foreach ( array( 'swiftboard_saved_topics', 'swiftboard_hidden_topics', 'swiftboard_followed_topics' ) as $key ) {
    delete_user_meta( $user_id, $key );
}
echo wp_json_encode( array( 'user_id' => $user_id, 'notifications_deleted' => (int) $deleted ), JSON_PRETTY_PRINT ), PHP_EOL;
