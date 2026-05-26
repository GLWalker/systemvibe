<?php
declare( strict_types = 1 );

namespace SystemVibe\Services;

/**
 * Enqueues the Command Palette JS bridge.
 *
 * Responsibilities:
 *  - Loads wp-commands (traditional global: window.wp.commands, window.wp.data).
 *  - Loads the ES module bridge as a Script Module with @wordpress/abilities
 *    and @wordpress/core-abilities as declared dependencies.
 *
 * The JS bridge MUST NOT call registerAbility(). PHP is the source of truth.
 * The bridge waits for @wordpress/core-abilities REST hydration, then binds
 * the already-hydrated ability into the Command Palette store.
 */
final class CommandPaletteBridge {

	public static function enqueue(): void {
		// Ensure window.wp.commands, window.wp.data, and window.wp.notices globals are available.
		wp_enqueue_script( 'wp-commands' );
		wp_enqueue_script( 'wp-notices' );
		
		// For the React Generator Modal
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_style( 'wp-components' );

		// Hook to inject the React DOM root for the Modal
		add_action( 'admin_footer', function() {
			echo '<div id="systemvibe-generator-modal-root"></div>';
		} );

		// Register and enqueue the ES module bridge.
		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module(
				'@systemvibe/command-palette',
				SYSTEMVIBE_URL . 'assets/js/command-palette.js',
				array(
					'@wordpress/abilities',
					'@wordpress/core-abilities',
				),
				SYSTEMVIBE_VERSION
			);
			
			// Enqueue the Generator Modal logic
			wp_enqueue_script_module(
				'@systemvibe/generator-modal',
				SYSTEMVIBE_URL . 'assets/js/generator-modal.js',
				array(
					'@wordpress/abilities'
				),
				SYSTEMVIBE_VERSION
			);
		}
	}
}
