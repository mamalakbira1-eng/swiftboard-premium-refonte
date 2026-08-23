<?php
/**
 * Import de recette local : assemble les CSV demo-reddit dans le format du
 * pipeline interne SwiftBoard, puis appelle swiftboard_process_import().
 */
if ( ! function_exists( 'swiftboard_process_import' ) ) {
    require_once ABSPATH . 'wp-content/themes/swiftboard/inc/import-csv.php';
    require_once ABSPATH . 'wp-content/themes/swiftboard/inc/import-entities.php';
    require_once ABSPATH . 'wp-content/themes/swiftboard/inc/admin-bulk-import.php';
}

$base = ABSPATH . 'wp-content/themes/swiftboard/demo-data/demo-reddit/';
$read_csv = static function ( string $path ): array {
    $handle = fopen( $path, 'rb' );
    if ( false === $handle ) {
        throw new RuntimeException( 'Fichier illisible : ' . $path );
    }
    $header = fgetcsv( $handle );
    if ( ! is_array( $header ) ) {
        fclose( $handle );
        throw new RuntimeException( 'En-têtes absents : ' . $path );
    }
    $rows = array();
    while ( false !== ( $row = fgetcsv( $handle ) ) ) {
        if ( count( $row ) === 1 && trim( (string) $row[0] ) === '' ) {
            continue;
        }
        $row = array_pad( $row, count( $header ), '' );
        $rows[] = array_combine( $header, array_slice( $row, 0, count( $header ) ) );
    }
    fclose( $handle );
    return $rows;
};

$members = $read_csv( $base . 'membres.csv' );
$topics  = $read_csv( $base . 'sujets.csv' );
$replies = $read_csv( $base . 'reponses.csv' );
$topic_titles = array();
foreach ( $topics as $topic ) {
    $topic_titles[ (string) $topic['id'] ] = (string) $topic['titre'];
}
$reply_authors_by_topic_order = array();
foreach ( $replies as $reply ) {
    $reply_authors_by_topic_order[ (string) $reply['sujet_id'] ][ (string) $reply['ordre'] ] = (string) $reply['auteur'];
}

$tmp = tempnam( sys_get_temp_dir(), 'swiftboard-demo-' );
if ( false === $tmp ) {
    throw new RuntimeException( 'Impossible de créer le fichier temporaire.' );
}
$handle = fopen( $tmp, 'wb' );
if ( false === $handle ) {
    throw new RuntimeException( 'Impossible d’ouvrir le fichier temporaire.' );
}

fputcsv( $handle, array( '---MEMBRES---' ) );
fputcsv( $handle, array( 'identifiant', 'email', 'grade', 'avatar', 'karma', 'nom_affiche' ) );
foreach ( $members as $member ) {
    fputcsv( $handle, array(
        $member['identifiant'],
        $member['email'],
        $member['grade'],
        $member['avatar'],
        $member['karma'],
        $member['nom_affiche'],
    ) );
}

fputcsv( $handle, array( '---TOPICS---' ) );
fputcsv( $handle, array( 'forum', 'title', 'content', 'author', 'grade', 'image_url', 'votes', 'vues', 'date' ) );
foreach ( $topics as $topic ) {
    fputcsv( $handle, array(
        $topic['forum'],
        $topic['titre'],
        $topic['contenu'],
        $topic['auteur'],
        '',
        $topic['image'],
        $topic['upvotes'],
        $topic['vues'],
        $topic['date'],
    ) );
}

fputcsv( $handle, array( '---REPLIES---' ) );
fputcsv( $handle, array( 'topic_title', 'content', 'author', 'grade', 'votes', 'reply_to', 'date', 'source_key' ) );
foreach ( $replies as $reply ) {
    $parent_author = '';
    if ( (int) $reply['repond_a'] > 0 ) {
        $parent_author = $reply_authors_by_topic_order[ (string) $reply['sujet_id'] ][ (string) $reply['repond_a'] ] ?? '';
    }
    fputcsv( $handle, array(
        $topic_titles[ (string) $reply['sujet_id'] ] ?? '',
        $reply['contenu'],
        $reply['auteur'],
        '',
        $reply['upvotes'],
        $parent_author,
        '',
        'reddit:' . (string) $reply['sujet_id'] . ':' . (string) $reply['ordre'],
    ) );
}
fclose( $handle );

$size = filesize( $tmp );
$log = swiftboard_process_import(
    array(
        'name'     => 'demo-reddit-combined.csv',
        'type'     => 'text/csv',
        'tmp_name' => $tmp,
        'error'    => UPLOAD_ERR_OK,
        'size'     => $size,
    )
);
@unlink( $tmp );

echo wp_json_encode(
    array(
        'members_source' => count( $members ),
        'topics_source'  => count( $topics ),
        'replies_source' => count( $replies ),
        'log'            => $log,
    ),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . "\n";
