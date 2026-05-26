<?php
declare( strict_types = 1 );

namespace SystemVibe\Services;

/**
 * Migration Service
 *
 * Handles data and path migrations between SystemVibe versions.
 * Hooked to plugins_loaded via Plugin.php.
 */
final class Migration {

	/**
	 * Runs pending migrations based on the currently installed version.
	 *
	 * @param string $installed_version The version recorded in the database option.
	 */
	public static function run( string $installed_version ): void {
		// Future migrations added here in ascending version order.

		// 0.0.0 → 1.0.0: Migrate old runtime/ telemetry from plugin dir to systemvibe-private/
		if ( version_compare( $installed_version, '1.0.0', '<' ) ) {
			self::migrate_telemetry_to_private_dir();
		}
	}

	/**
	 * Copies telemetry scans from the old plugin-internal runtime/ directory
	 * to the new wp-content/systemvibe-private/ location and removes old files.
	 */
	private static function migrate_telemetry_to_private_dir(): void {
		$old_dir = wp_normalize_path( WP_PLUGIN_DIR . '/systemvibe/runtime/telemetry/scans/' );
		$new_dir = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/telemetry/scans/' );

		if ( ! is_dir( $old_dir ) ) {
			return; // Nothing to migrate.
		}

		if ( ! is_dir( $new_dir ) ) {
			wp_mkdir_p( $new_dir );
		}

		$files = glob( $old_dir . '*.json' );
		if ( ! $files ) {
			return;
		}

		foreach ( $files as $file ) {
			$dest = $new_dir . basename( $file );
			if ( ! file_exists( $dest ) ) {
				copy( $file, $dest );
			}
			unlink( $file );
		}

		// Remove old directory if now empty
		$remaining = glob( $old_dir . '*' );
		if ( empty( $remaining ) ) {
			@rmdir( $old_dir );
		}
	}
}
