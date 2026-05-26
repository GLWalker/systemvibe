<?php
declare( strict_types = 1 );

namespace SystemVibe\Engine;

/**
 * The Forensic Scanner
 *
 * Gathers raw, read-only facts about the WordPress runtime environment.
 * Does not mutate state or evaluate rules.
 */
final class Scanner {

	/**
	 * Runs a full forensic scan and returns a structured array of facts.
	 *
	 * @return array
	 */
	public static function scan(): array {
		return array(
			'plugins'     => self::scan_plugins(),
			'themes'      => self::scan_themes(),
			'abilities'   => self::scan_abilities(),
			'blocks'      => self::scan_blocks(),
			'rest_routes' => self::scan_rest_routes(),
		);
	}

	private static function scan_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		return array(
			'installed' => get_plugins(),
			'active'    => get_option( 'active_plugins', array() ),
			'mu'        => get_mu_plugins(),
		);
	}

	private static function scan_themes(): array {
		$themes = wp_get_themes();
		$active = wp_get_theme();
		
		$installed = array();
		foreach ( $themes as $slug => $theme ) {
			$installed[ $slug ] = array(
				'Name'    => $theme->get( 'Name' ),
				'Version' => $theme->get( 'Version' ),
			);
		}

		return array(
			'installed' => $installed,
			'active'    => $active->get_stylesheet(),
		);
	}

	private static function scan_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$abilities = array();

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! $ability instanceof \WP_Ability ) {
				continue;
			}

			$name = $ability->get_name();

			$abilities[ $name ] = array(
				'name'          => $name,
				'label'         => $ability->get_label(),
				'description'   => $ability->get_description(),
				'category'      => $ability->get_category(),
				'input_schema'  => $ability->get_input_schema(),
				'output_schema' => $ability->get_output_schema(),
				'meta'          => $ability->get_meta(),
				'show_in_rest'  => (bool) $ability->get_meta_item( 'show_in_rest', false ),
			);
		}

		return $abilities;
	}

	private static function scan_blocks(): array {
		if ( class_exists( '\WP_Block_Type_Registry' ) ) {
			$registry = \WP_Block_Type_Registry::get_instance();
			return array_keys( $registry->get_all_registered() );
		}
		return array();
	}

	private static function scan_rest_routes(): array {
		if ( function_exists( 'rest_get_server' ) ) {
			$server = rest_get_server();
			if ( $server ) {
				// Return route paths to keep the payload size manageable
				return array_keys( $server->get_routes() );
			}
		}
		return array();
	}
}
