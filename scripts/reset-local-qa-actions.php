<?php
if ( ! defined( 'ABSPATH' ) ) { exit( 'WordPress non chargé' ); }
global $wpdb;
$usernames = array( 'sbmember', 'sbvip', 'sbmoderator', 'sbnotify' );
$reset = array();
$votes_table = swiftboard_table( 'votes' );
foreach ( $usernames as $username ) {
    $user = get_user_by( 'login', $username );
    if ( ! $user ) { continue; }
    $uid = (int) $user->ID;
    $deleted_votes = $wpdb->query( $wpdb->prepare( "DELETE FROM {$votes_table} WHERE user_id = %d", $uid ) );
    delete_user_meta( $uid, 'swiftboard_saved_topics' );
    delete_user_meta( $uid, 'swiftboard_hidden_topics' );
    delete_user_meta( $uid, 'swiftboard_followed_topics' );
    // Repartir d'un état reproductible pour la publication bbPress : on ne
    // désactive pas le throttle, on efface uniquement le timestamp de recette.
    $last_posted_cleared = delete_user_option( $uid, '_bbp_last_posted' );
    // Le compteur quotidien est aussi remis à zéro pour que la matrice puisse
    // exercer une nouvelle journée de votes sans modifier la limite métier.
    $daily_pattern = '_transient_sb_vote_today_' . $uid . '_%';
    $daily_timeout_pattern = '_transient_timeout_sb_vote_today_' . $uid . '_%';
    $daily_transients = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $daily_pattern,
            $daily_timeout_pattern
        )
    );
    $reset[ $username ] = array(
        'user_id'                       => $uid,
        'votes_deleted'                 => (int) $deleted_votes,
        'last_posted_cleared'           => (bool) $last_posted_cleared,
        'daily_vote_transients_deleted' => (int) $daily_transients,
    );
}
$transients = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sb_vote_rl_%' OR option_name LIKE '_transient_timeout_sb_vote_rl_%'" );
echo wp_json_encode( array( 'accounts' => $reset, 'rate_limit_transients_deleted' => (int) $transients ), JSON_PRETTY_PRINT ), PHP_EOL;
