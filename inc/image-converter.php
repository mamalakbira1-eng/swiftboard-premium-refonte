<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Conversion d'images vers AVIF, avec repli WebP.
 *
 * EXI-ARCH-02 : extrait de inc/image-upload.php, qui depassait 1100 lignes.
 * Module charge en FRONT : la conversion s'execute pendant l'envoi d'un
 * visiteur, pas dans l'administration.
 *
 * Trois moteurs sont tentes dans l'ordre : GD (PHP 8.1+ avec support AVIF),
 * ImageMagick, puis WebP en dernier recours. Un hebergement depourvu
 * d'encodeur AVIF reste donc servi — c'est le cas de nombreux mutualises.
 *
 * Attention : Imagick::queryFormats() annonce « AVIF » des que le DECODEUR
 * existe. L'encodeur vient d'un paquet separe (libheif-plugin-aomenc) et son
 * absence ne se manifeste qu'a l'ecriture. C'est pourquoi le code capture
 * l'exception au lieu de se fier a queryFormats().
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
/**
 * Convertit une image en AVIF avec GD (PHP 8.1+).
 *
 * @param string $source_path Chemin de fichier.
 * @param string $dest_path   Chemin de fichier.
 * @param mixed  $mime        À documenter.
 * @return mixed
 */
function swiftboard_convert_to_avif_gd( $source_path, $dest_path, $mime ) {
	// Charger l'image source selon le type
	switch ( $mime ) {
		case 'image/jpeg':
			$image = @imagecreatefromjpeg( $source_path );
			break;
		case 'image/png':
			$image = @imagecreatefrompng( $source_path );
			break;
		case 'image/gif':
			$image = @imagecreatefromgif( $source_path );
			break;
		case 'image/webp':
			$image = @imagecreatefromwebp( $source_path );
			break;
		default:
			return false;
	}

	if ( ! $image ) {
		return false;
	}

	// Convertir en true color si nécessaire (pour PNG avec palette)
	if ( ! imageistruecolor( $image ) ) {
		imagepalettetotruecolor( $image );
	}

	// Redimensionner si trop grand (max 1920px de large)
	$width  = imagesx( $image );
	$height = imagesy( $image );
	if ( $width > 1920 ) {
		$new_width  = 1920;
		$new_height = intval( $height * ( $new_width / $width ) );
		$resized    = imagecreatetruecolor( $new_width, $new_height );
		imagecopyresampled( $resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
		imagedestroy( $image );
		$image = $resized;
	}

	// Convertir en AVIF
	$result = imageavif( $image, $dest_path, SWIFTBOARD_UPLOAD_AVIF_QUALITY );
	imagedestroy( $image );

	return $result;
}
/**
 * Convertit une image en AVIF avec ImageMagick.
 *
 * @param string $source_path Chemin de fichier.
 * @param string $dest_path   Chemin de fichier.
 * @return mixed
 */
function swiftboard_convert_to_avif_imagick( $source_path, $dest_path ) {
	try {
		$image = new Imagick( $source_path );

		// Redimensionner si trop grand
		if ( $image->getImageWidth() > 1920 ) {
			$image->resizeImage( 1920, 0, Imagick::FILTER_LANCZOS, 1 );
		}

		// Convertir en AVIF
		$image->setImageFormat( 'avif' );
		$image->setImageCompressionQuality( SWIFTBOARD_UPLOAD_AVIF_QUALITY );
		$image->writeImage( $dest_path );
		$image->destroy();

		return file_exists( $dest_path );
	} catch ( Exception $e ) {
		return false;
	}
}
/**
 * Fallback : convertit en WebP si AVIF n'est pas supporté.
 *
 * @param string $source_path Chemin de fichier.
 * @param string $dest_path   Chemin de fichier.
 * @param mixed  $mime        À documenter.
 * @return mixed
 */
function swiftboard_convert_to_webp( $source_path, $dest_path, $mime ) {
	if ( ! function_exists( 'imagewebp' ) ) {
		return false;
	}

	switch ( $mime ) {
		case 'image/jpeg':
			$image = @imagecreatefromjpeg( $source_path );
			break;
		case 'image/png':
			$image = @imagecreatefrompng( $source_path );
			break;
		case 'image/gif':
			$image = @imagecreatefromgif( $source_path );
			break;
		case 'image/webp':
			$image = @imagecreatefromwebp( $source_path );
			break;
		default:
			return false;
	}

	if ( ! $image ) {
		return false;
	}

	if ( ! imageistruecolor( $image ) ) {
		imagepalettetotruecolor( $image );
	}

	// Redimensionner si trop grand
	$width  = imagesx( $image );
	$height = imagesy( $image );
	if ( $width > 1920 ) {
		$new_width  = 1920;
		$new_height = intval( $height * ( $new_width / $width ) );
		$resized    = imagecreatetruecolor( $new_width, $new_height );
		imagecopyresampled( $resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
		imagedestroy( $image );
		$image = $resized;
	}

	$result = imagewebp( $image, $dest_path, 75 );
	imagedestroy( $image );

	return $result;
}

// ============================================================================
// 5. TABLE BASE DE DONNÉES
// ============================================================================
