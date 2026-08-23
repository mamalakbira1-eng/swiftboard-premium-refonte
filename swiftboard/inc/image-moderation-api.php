<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Routes REST de moderation des images.
 *
 * EXI-ARCH-02 : extrait de inc/image-upload.php. Ces callbacks servent les
 * routes /images/pending, /images/N/approve et /images/N/reject. Ils vivent
 * en FRONT : une route enregistree depuis un module admin-only renverrait 404
 * a tout appel REST, y compris authentifie.
 *
 * Chaque route est protegee par un permission_callback exigeant
 * manage_options ; le rejet supprime aussi le fichier du disque, tout en
 * conservant l'enregistrement pour l'audit.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
/**
 * swiftboard_get_pending_images().
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return \WP_REST_Response
 */
function swiftboard_get_pending_images( $request ) {
	global $wpdb;
	$table = swiftboard_table( 'uploads' );

	$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
	$per_page = 20;
	$offset   = ( $page - 1 ) * $per_page;

	$images = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		)
	);

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" );

	$result = array();
	foreach ( $images as $img ) {
		$user     = get_userdata( $img->user_id );
		$result[] = array(
			'id'            => (int) $img->id,
			'image_url'     => $img->image_url,
			'filename'      => $img->filename,
			'user_name'     => $user ? $user->display_name : 'Unknown',
			'original_size' => size_format( $img->original_size ),
			'file_size'     => size_format( $img->file_size ),
			'savings'       => $img->original_size > 0 ? round( ( 1 - $img->file_size / $img->original_size ) * 100 ) . '%' : 'N/A',
			'created_at'    => $img->created_at,
		);
	}

	return new WP_REST_Response(
		array(
			'images'   => $result,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		),
		200
	);
}

// ============================================================================
// 7. MODÉRATION ADMIN - APPROUVER UNE IMAGE
// ============================================================================
/**
 * swiftboard_approve_image().
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_approve_image( $request ) {
	global $wpdb;
	$table    = swiftboard_table( 'uploads' );
	$image_id = (int) $request->get_param( 'id' );

	$updated = $wpdb->update(
		$table,
		array(
			'status'       => 'approved',
			'moderated_by' => get_current_user_id(),
			'moderated_at' => current_time( 'mysql' ),
		),
		array(
			'id'     => $image_id,
			'status' => 'pending',
		)
	);

	if ( $updated === false ) {
		return new WP_Error( 'db_error', __( 'Erreur de base de données.', 'swiftboard' ), array( 'status' => 500 ) );
	}

	if ( $updated === 0 ) {
		return new WP_Error( 'not_found', __( 'Image introuvable ou déjà modérée.', 'swiftboard' ), array( 'status' => 404 ) );
	}

	// Log d'audit
	$image = $wpdb->get_row( $wpdb->prepare( "SELECT user_id, filename FROM $table WHERE id = %d", $image_id ) );
	if ( $image ) {
		swiftboard_log_upload_action( $image_id, $image->user_id, 'approved', 'Approuvée par admin #' . get_current_user_id() . ': ' . $image->filename );
	}

	swiftboard_invalider_cache_pending();

	return new WP_REST_Response(
		array(
			'success' => true,
			'message' => __( 'Image approuvée. Elle est maintenant visible.', 'swiftboard' ),
		),
		200
	);
}

// ============================================================================
// 8. MODÉRATION ADMIN - REJETER UNE IMAGE (supprimer le fichier)
// ============================================================================
/**
 * swiftboard_reject_image().
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requête REST entrante.
 * @return \WP_Error|\WP_REST_Response
 */
function swiftboard_reject_image( $request ) {
	global $wpdb;
	$table    = swiftboard_table( 'uploads' );
	$image_id = (int) $request->get_param( 'id' );

	// Récupérer le chemin du fichier
	$image = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND status = 'pending'", $image_id ) );

	if ( ! $image ) {
		return new WP_Error( 'not_found', __( 'Image introuvable ou déjà modérée.', 'swiftboard' ), array( 'status' => 404 ) );
	}

	// Supprimer le fichier physique
	if ( file_exists( $image->filepath ) ) {
		unlink( $image->filepath );
	}

	// Marquer comme rejeté (garder l'enregistrement pour audit)
	$wpdb->update(
		$table,
		array(
			'status'       => 'rejected',
			'moderated_by' => get_current_user_id(),
			'moderated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $image_id )
	);
	swiftboard_invalider_cache_pending();

	// Log d'audit
	swiftboard_log_upload_action( $image_id, $image->user_id, 'rejected', 'Rejetée et supprimée par admin #' . get_current_user_id() . ': ' . $image->filename );

	return new WP_REST_Response(
		array(
			'success' => true,
			'message' => __( 'Image rejetée et supprimée du serveur.', 'swiftboard' ),
		),
		200
	);
}

// ============================================================================
// 9. FILTRER L'AFFICHAGE DES IMAGES DANS LE CONTENU
// ============================================================================
// Les images pending affichent un placeholder au lieu de l'image
add_filter( 'the_content', 'swiftboard_filter_pending_images', 5 );
add_filter( 'bbp_get_topic_content', 'swiftboard_filter_pending_images', 5 );
add_filter( 'bbp_get_reply_content', 'swiftboard_filter_pending_images', 5 );
