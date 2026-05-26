<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\WorkspaceSandbox;

/**
 * Preview Apply Artifact Ability
 *
 * Runs validation, then calculates the deployment plan and diffs against
 * the wp-content/plugins/systemvibe-generated/ directory.
 * No files are moved or executed.
 */
final class PreviewApplyArtifactAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public static function execute( $input = null ): array {
		$input = (array) $input;
		if ( empty( $input['generation_id'] ) ) {
			return array(
				'message' => 'generation_id is required.',
				'status'  => 'error',
				'plan'    => array(),
			);
		}

		$generation_id = sanitize_text_field( $input['generation_id'] );

		// 1. Re-run validation as a strict gate
		$validation_result = ValidateGeneratedArtifactAbility::execute( array( 'generation_id' => $generation_id ) );
		
		if ( ! $validation_result['aggregate_passed'] ) {
			return array(
				'message' => 'Validation failed. Cannot preview apply for an unsafe artifact.',
				'status'  => 'error',
				'plan'    => array(
					'validation_errors' => $validation_result['results']
				),
			);
		}

		// 2. Preflight Filesystem Check
		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		$plugin_dir  = wp_normalize_path( WP_PLUGIN_DIR );
		$target_dir  = wp_normalize_path( WP_PLUGIN_DIR . '/systemvibe-generated' );
		
		$is_writable_direct = is_writable( $target_dir ) || ( ! file_exists( $target_dir ) && is_writable( $plugin_dir ) );
		$filesystem_method  = get_filesystem_method( array(), $target_dir );
		
		// 3. Get Manifest & Calculate Plan
		$manifests = WorkspaceSandbox::get_manifests();
		$manifest  = null;
		foreach ( $manifests as $m ) {
			if ( $m['generation_id'] === $generation_id ) {
				$manifest = $m;
				break;
			}
		}

		if ( ! $manifest || empty( $manifest['files'] ) ) {
			return array(
				'message' => 'Artifact manifest not found or has no files.',
				'status'  => 'error',
				'plan'    => array(),
			);
		}

		$files_plan           = array();
		$plugin_header_needed = false;

		// Check for main plugin file scaffolding
		$main_plugin_file = $target_dir . '/systemvibe-generated.php';
		if ( ! file_exists( $main_plugin_file ) ) {
			$plugin_header_needed = true;
			$files_plan[] = array(
				'file'        => 'systemvibe-generated.php',
				'target_path' => $main_plugin_file,
				'action'      => 'create',
				'status'      => 'new',
			);
		}

		foreach ( $manifest['files'] as $file ) {
			$target_file = wp_normalize_path( $target_dir . '/' . $file );
			
			$status = 'new';
			$action = 'create';

			if ( file_exists( $target_file ) ) {
				$sandbox_result = WorkspaceSandbox::get_file_contents( $generation_id, $file );
				$live_content   = file_get_contents( $target_file );
				
				if ( $sandbox_result['status'] === 'success' && $sandbox_result['content'] === $live_content ) {
					$status = 'unchanged';
					$action = 'skip';
				} else {
					$status = 'modified';
					$action = 'overwrite';
				}
			}

			$files_plan[] = array(
				'file'        => $file,
				'target_path' => $target_file,
				'action'      => $action,
				'status'      => $status,
			);
		}

		return array(
			'message' => 'Apply preview generated successfully.',
			'status'  => 'success',
			'plan'    => array(
				'generation_id'        => $generation_id,
				'target_plugin'        => 'systemvibe-generated',
				'target_path'          => $target_dir,
				'is_writable_direct'   => $is_writable_direct,
				'filesystem_method'    => $filesystem_method,
				'validation_passed'    => true, // Would have returned early if false
				'plugin_header_needed' => $plugin_header_needed,
				'files'                => $files_plan,
			),
		);
	}
}
