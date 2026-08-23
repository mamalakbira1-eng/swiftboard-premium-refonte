<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard - TGMPA Required Plugins
 * Envato Requirement: Recommend bbPress and OCDI, not force
 *
 * @package SwiftBoard
 */
add_action(
	'tgmpa_register',
	function () {
		$plugins = array(
			array(
				'name'             => 'bbPress',
				'slug'             => 'bbpress',
				'required'         => false,
				'force_activation' => false,
			),
			array(
				'name'     => 'One Click Demo Import',
				'slug'     => 'one-click-demo-import',
				'required' => false,
			),
		);
		$config  = array(
			'id'           => 'swiftboard',
			'default_path' => '',
			'menu'         => 'tgmpa-install-plugins',
			'parent_slug'  => 'themes.php',
			'capability'   => 'edit_theme_options',
			'has_notices'  => true,
			'dismissable'  => true,
			'dismiss_msg'  => '',
			'is_automatic' => false,
			'message'      => '',
		);
		tgmpa( $plugins, $config );
	}
);
