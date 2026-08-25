<?php
if ( ! defined( 'ABSPATH' ) ) { exit( 'WordPress non chargé' ); }
swiftboard_create_votes_table();
swiftboard_create_notifications_table();
global $wpdb;
$tables = array(
    'votes'         => swiftboard_table( 'votes' ),
    'notifications' => swiftboard_table( 'notifications' ),
);
$result = array(
    'versions' => array(
        'votes'         => get_option( 'swiftboard_votes_db_version', '0' ),
        'notifications' => get_option( 'swiftboard_notifications_db_version', '0' ),
    ),
    'schema' => array(),
);
foreach ( $tables as $key => $table ) {
    $result['schema'][ $key ] = array(
        'columns' => $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A ),
        'indexes' => $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A ),
    );
}
echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), PHP_EOL;
