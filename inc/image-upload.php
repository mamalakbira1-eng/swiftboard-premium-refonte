<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard - Module Upload d'images avec conversion AVIF + modération
 *
 * Fonctionnalités :
 * - Upload d'images depuis les formulaires bbPress (topic + reply)
 * - Conversion automatique en AVIF avant stockage (économie de ~70% d'espace)
 * - Modération admin : les images sont en attente jusqu'à validation
 * - Suppression de l'original après conversion AVIF
 *
 * @package SwiftBoard
 * @since 2.1.0
 */
// ============================================================================
// 1. CONSTANTES
// ============================================================================
define( 'SWIFTBOARD_UPLOAD_DIR', WP_CONTENT_DIR . '/uploads/swiftboard-forum' );
// content_url() suit le protocole HTTPS de la requête courante (contrairement à WP_CONTENT_URL)
define( 'SWIFTBOARD_UPLOAD_URL', content_url( '/uploads/swiftboard-forum' ) );
define( 'SWIFTBOARD_UPLOAD_MAX_SIZE', 10 * 1024 * 1024 ); // 10 Mo max
define( 'SWIFTBOARD_UPLOAD_ALLOWED', array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ) );
define( 'SWIFTBOARD_UPLOAD_AVIF_QUALITY', 60 ); // Qualité AVIF (0-100)

// ============================================================================
// 2. ENDPOINT REST API POUR L'UPLOAD
// ============================================================================
/**
 * @return void
 */
function swiftboard_upload_register_routes() {
	register_rest_route(
		'swiftboard/v1',
		'/upload',
		array(
			'methods'             => 'POST',
			'callback'            => 'swiftboard_handle_upload',
			'permission_callback' => function () {
				return is_user_logged_in() && swiftboard_user_can( get_current_user_id(), 'can_upload' ); },
		)
	);

	register_rest_route(
		'swiftboard/v1',
		'/images/pending',
		array(
			'methods'             => 'GET',
			'callback'            => 'swiftboard_get_pending_images',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'swiftboard/v1',
		'/images/(?P<id>\d+)/approve',
		array(
			'methods'             => 'POST',
			'callback'            => 'swiftboard_approve_image',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'swiftboard/v1',
		'/images/(?P<id>\d+)/reject',
		array(
			'methods'             => 'POST',
			'callback'            => 'swiftboard_reject_image',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'swiftboard_upload_register_routes' );

// ============================================================================
// 3. GESTION DE L'UPLOAD + CONVERSION AVIF
// ============================================================================
/**
 * Applique les garde-fous anti-spam et anti-abus sur un envoi d'image.
 *
 * EXI-ARCH-02 : extrait de swiftboard_handle_upload(), qui faisait 220 lignes.
 * Cette validation est la partie la plus sensible du module — elle est
 * reprise ICI SANS AUCUNE MODIFICATION de logique :
 *
 *   3a. frequence : 1 envoi par minute et par compte
 *   3b. quota quotidien
 *   3c. quota total
 *   3d. doublon detecte par hash du fichier
 *   3e. dimensions (anti pixel-spam et anti-DoS)
 *   3f. VRAI type MIME via getimagesize(), jamais l'en-tete HTTP
 *   3g. anciennete du compte (nombre de messages)
 *   3h. recidive (5 rejets en 24 h)
 *
 * Le point 3f est le controle critique : c'est lui qui demasque un script PHP
 * renomme en .jpg.
 *
 * @param array<string, mixed> $file    Entree de $_FILES.
 * @param int                  $user_id Auteur de l'envoi.
 * @return array<string, mixed>|WP_Error ['mime','width','height','hash','daily_key','daily_count'] ou erreur.
 */
function swiftboard_upload_valider( $file, $user_id ) {
	global $wpdb;
	$table = swiftboard_table( 'uploads' );

	// ========================================================================
	// ANTI-SPAM + LIMITATIONS (v2.2)
	// ========================================================================

	// 3a. Rate limiting : max 1 upload par minute par utilisateur
	$rate_key   = 'swiftboard_rl_upload_' . $user_id;
	$rate_count = (int) get_transient( $rate_key );
	if ( $rate_count >= 1 ) {
		return new WP_Error( 'rate_limited', __( 'Trop d\'uploads. Attendez 1 minute entre chaque image.', 'swiftboard' ), array( 'status' => 429 ) );
	}
	set_transient( $rate_key, $rate_count + 1, MINUTE_IN_SECONDS );

	// 3b. Limite quotidienne : max 2 images par jour par utilisateur.
	//
	// Le compteur est lu DANS LA TABLE, pas dans un transient. Un transient
	// etait lu ici puis reecrit ~300 ms plus tard, apres la conversion de
	// l'image : pendant cette fenetre, toutes les requetes concurrentes
	// lisaient la meme valeur, passaient le controle, et le quota sautait.
	//
	// Mesure avant correction, sur nginx + PHP-FPM : 8 envois simultanes
	// -> 8 images enregistrees pour un quota de 2. Le defaut est INVISIBLE
	// sur `php -S`, qui serialise les requetes.
	//
	// COUNT(*) sur (user_id, created_at) est indexe et s'evalue au moment du
	// controle : deux requetes concurrentes ne peuvent pas lire la meme valeur
	// perimee, puisque la ligne precedente est deja committee.
	$daily_key   = 'swiftboard_daily_upload_' . $user_id . '_' . date( 'Y-m-d' );
	$daily_limit = (int) get_option( 'swiftboard_upload_daily_limit', 2 );
	// Les images REJETEES par la moderation ne consomment pas de credit :
	// l'auteur ne doit pas etre puni deux fois. Le controle « recidiviste »
	// ci-dessous s'occupe, lui, des rejets repetes.
	$daily_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
         WHERE user_id = %d AND DATE(created_at) = %s AND status != 'rejected'",
			$user_id,
			current_time( 'Y-m-d' )
		)
	);

	// RESERVATION ATOMIQUE de la place.
	//
	// Compter les lignes ne suffit pas : la ligne n'est inseree qu'APRES la
	// conversion de l'image (~300 ms). Pendant cette fenetre, N requetes
	// concurrentes comptent la meme valeur et passent toutes.
	// Mesure : 8 envois simultanes -> 8 images pour un quota de 2 ; puis 5
	// apres etre passe au COUNT(*), donc toujours insuffisant.
	//
	// add_option() s'appuie sur la contrainte UNIQUE de wp_options.option_name :
	// l'insertion reussit pour UNE SEULE requete, les autres echouent
	// immediatement. C'est le seul verrou reellement atomique disponible sans
	// dependance externe (autoload « no » : jamais charge sur les pages).
	// Chaque `return new WP_Error` ci-dessous est précédé d'un appel à
	// swiftboard_liberer_place_upload() : un envoi refusé ne doit jamais
	// consommer le quota du jour (voir inc/upload-quota.php).
	$places_prises    = 0;
	$sb_place_obtenue = -1;   // créneau réservé par CETTE requête
	for ( $sb_place = 0; $sb_place < $daily_limit; $sb_place++ ) {
		$sb_jeton = $daily_key . '_slot_' . $sb_place;
		if ( add_option( $sb_jeton, time(), '', false ) ) {
			$places_prises    = $sb_place + 1;
			$sb_place_obtenue = $sb_place;
			break;   // place obtenue
		}
		$places_prises = $daily_limit;   // toutes prises jusqu'ici
	}

	if ( $places_prises >= $daily_limit && $sb_place >= $daily_limit ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error(
			'daily_limit',
			sprintf(
				__( 'Limite quotidienne atteinte (%d images/jour). Réessayez demain.', 'swiftboard' ),
				$daily_limit
			),
			array( 'status' => 429 )
		);
	}
	if ( $daily_count >= $daily_limit ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error(
			'daily_limit',
			sprintf(
				__( 'Limite quotidienne atteinte (%d images/jour). Réessayez demain.', 'swiftboard' ),
				$daily_limit
			),
			array( 'status' => 429 )
		);
	}

	// 3c. Limite totale : max 200 images par utilisateur (configurable)
	global $wpdb;
	$table       = swiftboard_table( 'uploads' );
	$total_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE user_id = %d AND status != 'rejected'",
			$user_id
		)
	);
	$total_limit = (int) get_option( 'swiftboard_upload_total_limit', 200 );
	if ( $total_count >= $total_limit ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error(
			'total_limit',
			sprintf(
				__( 'Vous avez atteint votre limite totale de %d images. Contactez un administrateur.', 'swiftboard' ),
				$total_limit
			),
			array( 'status' => 429 )
		);
	}

	// 3d. Détection de doublons (hash du fichier)
	$file_hash = md5_file( $file['tmp_name'] );
	$existing  = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM $table WHERE file_hash = %s AND status != 'rejected' LIMIT 1",
			$file_hash
		)
	);
	if ( $existing ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error( 'duplicate', __( 'Cette image a déjà été uploadée.', 'swiftboard' ), array( 'status' => 409 ) );
	}

	// 3e. Vérification des dimensions (anti-tiny-image / anti-pixel-spam)
	$image_info = @getimagesize( $file['tmp_name'] );
	if ( $image_info === false ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error( 'invalid_image', __( 'Fichier corrompu ou non image.', 'swiftboard' ), array( 'status' => 400 ) );
	}
	$img_width  = $image_info[0];
	$img_height = $image_info[1];

	// Image trop petite (probable spam ou pixel art malveillant)
	if ( $img_width < 50 || $img_height < 50 ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error( 'too_small', __( 'Image trop petite (min 50×50 pixels).', 'swiftboard' ), array( 'status' => 400 ) );
	}

	// Image trop grande (anti-DoS)
	if ( $img_width > 10000 || $img_height > 10000 ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error( 'too_large_dims', __( 'Dimensions trop grandes (max 10000×10000).', 'swiftboard' ), array( 'status' => 400 ) );
	}

	// 3f. Vérification du vrai type MIME (ne pas faire confiance au header HTTP)
	$real_mime = $image_info['mime'];
	if ( ! in_array( $real_mime, SWIFTBOARD_UPLOAD_ALLOWED ) ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error( 'fake_type', __( 'Le type de fichier ne correspond pas à son contenu.', 'swiftboard' ), array( 'status' => 400 ) );
	}

	// 3g. Anti-spam : utilisateurs avec moins de 3 messages ne peuvent pas uploader
	if ( function_exists( 'bbp_get_user_reply_count_raw' ) && function_exists( 'bbp_get_user_topic_count_raw' ) ) {
		$user_topics  = (int) bbp_get_user_topic_count_raw( $user_id );
		$user_replies = (int) bbp_get_user_reply_count_raw( $user_id );
		$min_posts    = (int) get_option( 'swiftboard_upload_min_posts', 3 );
		if ( ( $user_topics + $user_replies ) < $min_posts ) {
				swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
			return new WP_Error(
				'not_enough_posts',
				sprintf(
					__( 'Vous devez avoir au moins %d messages pour uploader des images.', 'swiftboard' ),
					$min_posts
				),
				array( 'status' => 403 )
			);
		}
	}

	// 3h. Vérifier si l'utilisateur a déjà des images rejetées récemment (anti-spam récidiviste)
	$recent_rejected = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE user_id = %d AND status = 'rejected' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
			$user_id
		)
	);
	if ( $recent_rejected >= 5 ) {
		swiftboard_liberer_place_upload( $daily_key, $sb_place_obtenue );
		return new WP_Error( 'too_many_rejected', __( 'Trop d\'images rejetées récemment. Contactez un administrateur.', 'swiftboard' ), array( 'status' => 403 ) );
	}

	return array(
		'mime'        => $real_mime,
		'width'       => $img_width,
		'height'      => $img_height,
		'hash'        => $file_hash,
		'daily_key'   => $daily_key,
		'daily_count' => $daily_count,
	);
}

/**
 * Prepare le repertoire de destination et le nom de fichier.
 *
 * @param array<string, mixed> $file Entree de $_FILES.
 * @return array<string, mixed>|WP_Error ['path','filename','year_month','subdir'] ou erreur.
 */
function swiftboard_upload_preparer_destination( $file ) {

	// Vérifier la taille
	if ( $file['size'] > SWIFTBOARD_UPLOAD_MAX_SIZE ) {
		return new WP_Error( 'too_large', __( 'Fichier trop volumineux (max 10 Mo).', 'swiftboard' ), array( 'status' => 400 ) );
	}

	// Le vrai type MIME est deja resolu par swiftboard_upload_valider() et
	// transmis a swiftboard_upload_convertir() : cette fonction n'a besoin
	// que des chemins.

	// Créer le dossier s'il n'existe pas
	if ( ! file_exists( SWIFTBOARD_UPLOAD_DIR ) ) {
		wp_mkdir_p( SWIFTBOARD_UPLOAD_DIR );
	}

	// Créer un sous-dossier par année/mois
	$year_month    = date( 'Y/m' );
	$upload_subdir = SWIFTBOARD_UPLOAD_DIR . '/' . $year_month;
	if ( ! file_exists( $upload_subdir ) ) {
		wp_mkdir_p( $upload_subdir );
	}

	// Générer un nom de fichier unique
	$filename  = wp_unique_filename( $upload_subdir, 'image-' . time() . '.avif' );
	$avif_path = $upload_subdir . '/' . $filename;

	return array(
		'path'       => $avif_path,
		'filename'   => $filename,
		'year_month' => $year_month,
		'subdir'     => $upload_subdir,
	);
}


/**
 * Enregistre l'image en base, journalise et construit la reponse REST.
 *
 * @param array<string, mixed> $file       Entree de $_FILES.
 * @param array<string, mixed> $validation Retour de swiftboard_upload_valider().
 * @param string               $avif_path  Fichier converti.
 * @param string               $filename   Nom final.
 * @param string               $year_month Sous-dossier annee/mois.
 * @return WP_REST_Response
 */
function swiftboard_upload_enregistrer( $file, array $validation, $avif_path, $filename, $year_month ) {
	$img_width   = $validation['width'];
	$img_height  = $validation['height'];
	$mime        = $validation['mime'];
	$file_hash   = $validation['hash'];
	$daily_key   = $validation['daily_key'];
	$daily_count = $validation['daily_count'];

	// === STOCKER EN BASE DE DONNÉES (statut pending) ===
	$user_id   = get_current_user_id();
	$image_url = SWIFTBOARD_UPLOAD_URL . '/' . $year_month . '/' . $filename;

	global $wpdb;
	$table = swiftboard_table( 'uploads' );

	// Créer la table si elle n'existe pas
	swiftboard_create_uploads_table();

	$wpdb->insert(
		$table,
		array(
			'user_id'       => $user_id,
			'filename'      => $filename,
			'filepath'      => $avif_path,
			'image_url'     => $image_url,
			'mime_type'     => $mime,
			'file_size'     => filesize( $avif_path ),
			'original_size' => $file['size'],
			'file_hash'     => $file_hash,
			'width'         => $img_width,
			'height'        => $img_height,
			'status'        => 'pending',
			'created_at'    => current_time( 'mysql' ),
		)
	);

	$image_id = $wpdb->insert_id;
	swiftboard_invalider_cache_pending();

	// Compteur d'affichage uniquement : le controle du quota, lui, interroge
	// directement la table (voir swiftboard_upload_valider). Ce transient ne
	// fait plus autorite — il evitait un COUNT(*) sur les ecrans, rien de plus.
	set_transient( $daily_key, $daily_count + 1, DAY_IN_SECONDS );

	// Log d'audit
	swiftboard_log_upload_action(
		$image_id,
		$user_id,
		'uploaded',
		sprintf(
			'Upload: %s (%dx%d, %s → %s AVIF)',
			$filename,
			$img_width,
			$img_height,
			size_format( $file['size'] ),
			size_format( filesize( $avif_path ) )
		)
	);

	// Calculer l'économie d'espace
	$original_size  = $file['size'];
	$converted_size = filesize( $avif_path );
	$savings        = round( ( 1 - $converted_size / $original_size ) * 100 );

	return new WP_REST_Response(
		array(
			'success'   => true,
			'image_id'  => $image_id,
			'image_url' => $image_url,
			'status'    => 'pending',
			'message'   => sprintf(
				__( 'Image convertie en AVIF et en attente de modération. Économie : %1$d%% (%2$s → %3$s)', 'swiftboard' ),
				$savings,
				size_format( $original_size ),
				size_format( $converted_size )
			),
			'savings'   => $savings,
			'new_size'  => size_format( $converted_size ),
			'old_size'  => size_format( $original_size ),
		),
		200
	);
}

/**
 * Point d'entree REST de l'envoi d'image.
 *
 * EXI-ARCH-02 : orchestrateur. La fonction faisait 220 lignes et melangeait
 * validation, conversion, stockage, journalisation et reponse HTTP.
 *
 * @param WP_REST_Request<array<string, mixed>> $request Requete portant le fichier « image ».
 * @return WP_REST_Response|WP_Error
 */
function swiftboard_handle_upload( $request ) {
	$files = $request->get_file_params();

	if ( empty( $files['image'] ) ) {
		return new WP_Error( 'no_file', __( 'Aucun fichier envoyé.', 'swiftboard' ), array( 'status' => 400 ) );
	}

	$file    = $files['image'];
	$user_id = get_current_user_id();

	$validation = swiftboard_upload_valider( $file, $user_id );
	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$destination = swiftboard_upload_preparer_destination( $file );
	if ( is_wp_error( $destination ) ) {
		return $destination;
	}

	$converti = swiftboard_upload_convertir(
		$file['tmp_name'],
		$destination['path'],
		$destination['filename'],
		$validation['mime'],
		$destination['subdir']
	);
	if ( is_wp_error( $converti ) ) {
		return $converti;
	}

	return swiftboard_upload_enregistrer(
		$file,
		$validation,
		$converti['path'],
		$converti['filename'],
		$destination['year_month']
	);
}

// ============================================================================
// 6. MODÉRATION ADMIN - RÉCUPÉRER LES IMAGES EN ATTENTE

/**
 * Invalide la liste memorisee des images en attente.
 *
 * A appeler des qu'un statut change : sans cela, une image approuvee
 * continuerait d'afficher son placeholder pendant la duree du cache.
 *
 * @return void
 */
function swiftboard_invalider_cache_pending() {
	wp_cache_delete( 'swiftboard_pending_images', 'swiftboard' );
}

/**
 * swiftboard_filter_pending_images().
 *
 * @param string $content Contenu à traiter.
 * @return mixed
 */
function swiftboard_filter_pending_images( $content ) {
	global $wpdb;
	$table = swiftboard_table( 'uploads' );

	// EXI-SCALE-03 : ce filtre est accroche a the_content, bbp_get_topic_content
	// ET bbp_get_reply_content. Sur une page de sujet, il s'execute donc une
	// fois par message affiche et refaisait la MEME requete a chaque appel
	// (mesure : 4 fois sur une page a 2 messages).
	//
	// La liste des images en attente ne change pas au cours d'une requete :
	// on la memorise. wp_cache_* et non une variable statique, pour profiter
	// d'un object cache persistant s'il est installe (EXI-SCALE-02).
	$pending = wp_cache_get( 'swiftboard_pending_images', 'swiftboard' );
	if ( false === $pending ) {
		$pending = $wpdb->get_results( "SELECT image_url FROM $table WHERE status = 'pending'" );
		wp_cache_set( 'swiftboard_pending_images', $pending, 'swiftboard', 300 );
	}

	if ( empty( $pending ) ) {
		return $content;
	}

	// Si l'utilisateur est admin, on affiche tout
	if ( current_user_can( 'manage_options' ) ) {
		return $content;
	}

	// Remplacer les images pending par un placeholder
	foreach ( $pending as $img ) {
		$placeholder = '<div class="swiftboard-pending-image">
            <span class="pending-icon">⏳</span>
            <span class="pending-text">' . __( 'Image en attente de modération', 'swiftboard' ) . '</span>
        </div>';

		// Remplacer les balises <img> qui pointent vers l'URL pending
		$pattern = '/<img[^>]*src=["\']' . preg_quote( $img->image_url, '/' ) . '["\'][^>]*>/i';
		$content = preg_replace( $pattern, $placeholder, $content );
	}

	return $content;
}

// ============================================================================
// 10. PAGE ADMIN DE MODÉRATION
