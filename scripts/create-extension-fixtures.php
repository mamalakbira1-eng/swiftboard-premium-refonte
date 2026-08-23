<?php
/** Local-only extension fixtures for SwiftBoard QA. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$fixtures = array(
    array(
        'slug'    => 'qa-gutenberg-hot-topics',
        'title'   => 'QA Gutenberg Hot Topics',
        'content' => '<!-- wp:swiftboard/hot-topics /-->',
        'meta'    => array(),
    ),
    array(
        'slug'    => 'qa-shortcode-hot-topics',
        'title'   => 'QA Shortcode Hot Topics',
        'content' => '[swiftboard_block name="hot-topics"]',
        'meta'    => array(),
    ),
    array(
        'slug'    => 'qa-elementor-hot-topics',
        'title'   => 'QA Elementor Hot Topics',
        'content' => '',
        'meta'    => array(
            '_elementor_edit_mode'     => 'builder',
            '_elementor_template_type' => 'wp-page',
            '_elementor_version'       => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.2.3',
            '_elementor_data'          => wp_json_encode(
                array(
                    array(
                        'id'       => 'qa_section',
                        'elType'   => 'section',
                        'isInner'  => false,
                        'settings' => array(),
                        'elements' => array(
                            array(
                                'id'       => 'qa_column',
                                'elType'   => 'column',
                                'isInner'  => false,
                                'settings' => array(),
                                'elements' => array(
                                    array(
                                        'id'        => 'qa_widget',
                                        'elType'    => 'widget',
                                        'widgetType' => 'swiftboard_hot-topics',
                                        'settings'  => array(
                                            'title' => 'QA Elementor Hot Topics',
                                            'limit' => 6,
                                        ),
                                        'elements'  => array(),
                                    ),
                                ),
                            ),
                        ),
                    ),
                )
            ),
        ),
    ),
);
$ids = array();
foreach ( $fixtures as $fixture ) {
    $old = get_page_by_path( $fixture['slug'], OBJECT, 'page' );
    if ( $old ) {
        wp_delete_post( $old->ID, true );
    }
    $id = wp_insert_post(
        array(
            'post_title'   => $fixture['title'],
            'post_name'    => $fixture['slug'],
            'post_content' => $fixture['content'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id() ?: 1,
        ),
        true
    );
    if ( is_wp_error( $id ) ) {
        fwrite( STDERR, $id->get_error_message() . "\n" );
        exit( 1 );
    }
    foreach ( $fixture['meta'] as $key => $value ) {
        update_post_meta( $id, $key, $value );
    }
    $ids[ $fixture['slug'] ] = (int) $id;
}
wp_cache_flush();
echo wp_json_encode( $ids, JSON_PRETTY_PRINT ) . "\n";
