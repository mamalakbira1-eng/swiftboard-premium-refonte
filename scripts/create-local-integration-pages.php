<?php
if ( ! defined( 'ABSPATH' ) ) { exit( 'WordPress non chargé' ); }

function sbqa_find_page( string $slug ): int {
    $query = new WP_Query(
        array(
            'post_type'      => 'page',
            'name'           => $slug,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );
    return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
}

$pages = array(
    array(
        'slug'    => 'qa-gutenberg-hot-topics',
        'title'   => 'QA Gutenberg Hot Topics',
        'content' => '<!-- wp:swiftboard/hot-topics /-->',
    ),
    array(
        'slug'    => 'qa-shortcode-hot-topics',
        'title'   => 'QA Shortcode Hot Topics',
        'content' => '[swiftboard_block name="hot-topics"]',
    ),
    array(
        'slug'    => 'qa-elementor-hot-topics',
        'title'   => 'QA Elementor Hot Topics',
        'content' => '',
    ),
);
$elementor_data = wp_json_encode(
    array(
        array(
            'id'         => 'qa-hot-topics',
            'elType'     => 'widget',
            'widgetType' => 'swiftboard_hot-topics',
            'settings'   => array(
                'title' => 'Sujets Chauds',
                'limit' => 6,
            ),
            'elements'   => array(),
        ),
    )
);
$results = array();
foreach ( $pages as $page ) {
    $id = sbqa_find_page( $page['slug'] );
    $postarr = array(
        'post_title'   => $page['title'],
        'post_name'    => $page['slug'],
        'post_content' => $page['content'],
        'post_status'  => 'publish',
        'post_type'    => 'page',
    );
    if ( $id ) {
        $postarr['ID'] = $id;
        $id = wp_update_post( wp_slash( $postarr ), true );
    } else {
        $id = wp_insert_post( wp_slash( $postarr ), true );
    }
    if ( is_wp_error( $id ) ) {
        $results[] = array( 'slug' => $page['slug'], 'error' => $id->get_error_message() );
        continue;
    }
    if ( $page['slug'] === 'qa-elementor-hot-topics' ) {
        update_post_meta( $id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.2.3' );
        update_post_meta( $id, '_elementor_data', wp_slash( $elementor_data ) );
    }
    $results[] = array( 'slug' => $page['slug'], 'id' => (int) $id, 'url' => get_permalink( $id ) );
}
echo wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), PHP_EOL;
