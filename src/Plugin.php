<?php
declare( strict_types = 1 );

namespace SystemVibe;

use SystemVibe\Abilities\AbilityRegistry;
use SystemVibe\Services\CommandPaletteBridge;
use SystemVibe\Services\Migration;

/**
 * Plugin entry class. Wires all hooks. No logic lives here.
 */
final class Plugin {

	public static function init(): void {
		// Migration: run version-based data migrations on every load.
		$installed_version = get_option( 'systemvibe_version', '0.0.0' );
		if ( version_compare( $installed_version, SYSTEMVIBE_VERSION, '<' ) ) {
			Migration::run( $installed_version );
			update_option( 'systemvibe_version', SYSTEMVIBE_VERSION );
		}

		// Phase 1: Abilities API lifecycle hooks.
		add_action( 'wp_abilities_api_categories_init', [ AbilityRegistry::class, 'register_categories' ] );
		add_action( 'wp_abilities_api_init',            [ AbilityRegistry::class, 'register_abilities' ] );

		// Phase 1: Command Palette JS bridge.
		add_action( 'admin_enqueue_scripts', [ CommandPaletteBridge::class, 'enqueue' ] );
	}
}
