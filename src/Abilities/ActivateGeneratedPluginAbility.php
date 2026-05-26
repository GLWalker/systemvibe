<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

/**
 * Activate Generated Plugin Ability
 *
 * Safely activates the systemvibe-generated plugin if it exists.
 */
final class ActivateGeneratedPluginAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'activate_plugins' );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public static function execute( $input = null ): array {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_path = 'systemvibe-generated/systemvibe-generated.php';
		$full_path   = wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_path );

		if ( ! file_exists( $full_path ) ) {
			return array(
				'message' => 'The systemvibe-generated plugin does not exist yet. Please apply an artifact first.',
				'status'  => 'error',
			);
		}

		if ( is_plugin_active( $plugin_path ) ) {
			return array(
				'message' => 'The systemvibe-generated plugin is already active.',
				'status'  => 'success',
			);
		}

		$result = activate_plugin( $plugin_path );

		if ( is_wp_error( $result ) ) {
			return array(
				'message' => 'Failed to activate plugin: ' . $result->get_error_message(),
				'status'  => 'error',
			);
		}

		return array(
			'message' => 'Successfully activated systemvibe-generated plugin.',
			'status'  => 'success',
		);
	}
}
