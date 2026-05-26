<?php
/**
 * Plugin Name:       SystemVibe
 * Plugin URI:        https://github.com/GLWalker
 * Description:       Armstrong-native Expert System. PHP owns abilities. JavaScript owns Command Palette binding.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Text Domain:       systemvibe
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SYSTEMVIBE_VERSION', '1.0.0' );
define( 'SYSTEMVIBE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SYSTEMVIBE_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
spl_autoload_register( function ( string $class ): void {
	if ( strpos( $class, 'SystemVibe\\' ) !== 0 ) {
		return;
	}
	$relative = str_replace( 'SystemVibe\\', '', $class );
	$file     = SYSTEMVIBE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

SystemVibe\Plugin::init();
