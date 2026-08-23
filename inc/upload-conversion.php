<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Conversion d'image vers AVIF, avec repli WebP.
 *
 * EXI-ARCH-01 : extrait de inc/image-upload.php, qui repassait au-dessus de
 * 500 lignes apres l'ajout de la reservation atomique de quota.
 *
 * Trois moteurs sont tentes dans l'ordre : GD (PHP 8.1+), ImageMagick, puis
 * repli WebP. Un hebergement sans encodeur AVIF reste servi par le repli.
 *
 * @package SwiftBoard
 * @since 5.1.7
 */
/**
 * Convertit l'image vers AVIF, avec repli WebP.
 *
 * Trois moteurs tentes dans l'ordre : GD (PHP 8.1+), ImageMagick, puis WebP.
 * Un hebergement sans encodeur AVIF reste servi par le repli.
 *
 * @param string $source_path Fichier temporaire recu.
 * @param string $avif_path   Destination souhaitee.
 * @param string $filename    Nom de fichier, ajuste si repli WebP.
 * @param string $mime        Type MIME reel de la source.
 * @param string $subdir      Dossier annee/mois de destination. Requis par le
 *                            repli WebP, qui doit ecrire a cote de l'AVIF.
 * @return array<string, string>|WP_Error ['path','filename'] ou erreur.
 */
function swiftboard_upload_convertir( $source_path, $avif_path, $filename, $mime, $subdir = '' ) {
	// $source_path arrive en parametre : la ligne d'origine le reassignait
	// depuis $file, variable qui n'existe plus dans ce scope.
	//
	// EXI-ARCH-04 : $subdir aussi. Le decoupage avait laisse le repli WebP
	// lire $upload_subdir, variable restee dans preparer_destination() : sur
	// un hebergement sans encodeur AVIF, le WebP s'ecrivait dans « /nom.webp »
	// (racine du disque) et l'envoi echouait systematiquement. Detecte par
	// PHPStan (variable.undefined), pas par les tests — la machine de QA
	// encode l'AVIF, donc le repli n'y est jamais emprunte.
	if ( $subdir === '' ) {
		$subdir = dirname( $avif_path );
	}

	$avif_created = false;

	// Méthode 1 : GD avec AVIF support (PHP 8.1+)
	if ( function_exists( 'imagecreatefromjpeg' ) && function_exists( 'imageavif' ) ) {
		$avif_created = swiftboard_convert_to_avif_gd( $source_path, $avif_path, $mime );
	}

	// Méthode 2 : ImageMagick si GD ne marche pas
	if ( ! $avif_created && extension_loaded( 'imagick' ) ) {
		$avif_created = swiftboard_convert_to_avif_imagick( $source_path, $avif_path );
	}

	// Méthode 3 : Si AVIF pas supporté, fallback WebP
	if ( ! $avif_created ) {
		$filename_webp = str_replace( '.avif', '.webp', $filename );
		$webp_path     = $subdir . '/' . $filename_webp;
		$avif_created  = swiftboard_convert_to_webp( $source_path, $webp_path, $mime );
		if ( $avif_created ) {
			$avif_path = $webp_path;
			$filename  = $filename_webp;
		}
	}

	if ( ! $avif_created ) {
		return new WP_Error( 'convert_failed', __( 'Impossible de convertir l\'image. Contactez l\'administrateur.', 'swiftboard' ), array( 'status' => 500 ) );
	}

	// L'original temporaire est détruit automatiquement par PHP
	// On ne stocke QUE la version AVIF/WebP — l'original n'est jamais sauvegardé
	return array(
		'path'     => $avif_path,
		'filename' => $filename,
	);
}
