<?php
/** Fixtures topics/replies locales uniquement. */
if ( ! defined( 'ABSPATH' ) ) { exit( 'WordPress non chargé' ); }
$find_by_slug = function ( $title, $post_type ) {
    $posts = get_posts(
        array(
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'name'           => sanitize_title( $title ),
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        )
    );
    return $posts ? $posts[0] : null;
};
$forum = $find_by_slug( 'Finances', 'forum' );
if ( ! $forum ) { exit( "Forum Finances absent\n" ); }
$users = array();
foreach ( array( 'sbvip', 'sbmember', 'sbnotify' ) as $login ) {
    $user = get_user_by( 'login', $login );
    if ( $user ) { $users[ $login ] = (int) $user->ID; }
}
$ensure_topic = function ( $title, $content, $author ) use ( $forum, $find_by_slug ) {
    $existing = $find_by_slug( $title, 'topic' );
    if ( $existing ) { return (int) $existing->ID; }
    $id = bbp_insert_topic(
        array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => $author,
            'post_parent'  => (int) $forum->ID,
        ),
        array( 'forum_id' => (int) $forum->ID )
    );
    if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Topic impossible : ' . $title ); }
    return (int) $id;
};
$ensure_reply = function ( $topic_id, $content, $author, $parent = 0 ) use ( $forum ) {
    $existing = get_posts(
        array(
            'post_type'      => 'reply',
            'post_status'    => 'publish',
            'post_parent'    => (int) $topic_id,
            'posts_per_page' => 50,
            'fields'         => 'ids',
        )
    );
    foreach ( $existing as $reply_id ) {
        if ( trim( get_post_field( 'post_content', $reply_id ) ) === trim( $content ) ) { return (int) $reply_id; }
    }
    $reply_meta = array( 'topic_id' => (int) $topic_id, 'forum_id' => (int) $forum->ID );
    if ( $parent > 0 ) { $reply_meta['reply_to'] = (int) $parent; }
    $id = bbp_insert_reply(
        array(
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => $author,
            'post_parent'  => (int) $topic_id,
        ),
        $reply_meta
    );
    if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Réponse impossible' ); }
    return (int) $id;
};
$admin = get_current_user_id() ?: 1;
$member = $users['sbmember'] ?? $admin;
$vip = $users['sbvip'] ?? $admin;
$notify = $users['sbnotify'] ?? $admin;
$battery = $ensure_topic( "Comment prolonger la batterie d'un portable", 'Conseils vérifiables pour prolonger la durée de vie de la batterie.', $vip );
update_post_meta(
    (int) $forum->ID,
    '_swiftboard_forum_rules',
    "Respecter les autres membres\nPublier dans le forum approprié\nNe pas partager de données personnelles"
);
update_post_meta( (int) $forum->ID, '_swiftboard_cdc_fixture', 'lots-4-9' );
$savings = $ensure_topic( "Par où commencer une épargne d'urgence", 'Une discussion structurée sur les premières étapes d’une épargne de précaution.', $vip );
$battery_reply = $ensure_reply( $battery, 'Je commence par mesurer les cycles et la température avant de changer mes habitudes.', $member );
$r1 = $ensure_reply( $savings, 'Je commence par définir un montant mensuel réaliste.', $member );
$r2 = $ensure_reply( $savings, 'Je suis d’accord : une petite réserve régulière est plus simple à maintenir.', $notify, $r1 );
$r3 = $ensure_reply( $savings, 'Et je vérifie ensuite l’objectif tous les trois mois.', $vip, $r2 );
// Dates fixes : elles rendent new/old distincts et la fixture reproductible.
foreach ( array(
	$r1 => '2026-07-01 10:00:00',
	$r2 => '2026-08-01 10:00:00',
	$r3 => '2026-08-15 10:00:00',
	$battery_reply => '2026-07-20 10:00:00',
) as $reply_id => $reply_date ) {
	wp_update_post( array( 'ID' => (int) $reply_id, 'post_date' => $reply_date, 'post_date_gmt' => get_gmt_from_date( $reply_date ), 'edit_date' => true ) );
}
if ( function_exists( 'swiftboard_refresh_hot_score' ) ) {
    swiftboard_refresh_hot_score( $battery );
    swiftboard_refresh_hot_score( $savings );
}
wp_update_post( array( 'ID' => $battery ) );
wp_update_post( array( 'ID' => $savings ) );
echo wp_json_encode( array( 'forum' => (int) $forum->ID, 'battery' => $battery, 'battery_reply' => $battery_reply, 'savings' => $savings, 'replies' => array( $r1, $r2, $r3 ) ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), PHP_EOL;
