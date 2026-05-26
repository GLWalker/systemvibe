<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\WorkspaceSandbox;
use SystemVibe\Services\TelemetryStore;

/**
 * Apply Generated Artifact Ability
 *
 * Securely writes generated artifacts from the sandbox to the live
 * systemvibe-generated plugin directory using WP_Filesystem.
 */
final class ApplyGeneratedArtifactAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'edit_plugins' ) && current_user_can( 'manage_options' );
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
				'files'   => array(),
			);
		}

		$generation_id = sanitize_text_field( $input['generation_id'] );

		// 1. Hash verification gate — proves files have not been tampered with since validation
		$hash_check = WorkspaceSandbox::verify_hashes( $generation_id );
		if ( ! $hash_check['passed'] ) {
			return array(
				'message' => 'Apply aborted: ' . implode( ' ', $hash_check['errors'] ),
				'status'  => 'error',
				'files'   => array(),
			);
		}

		// 2. Get deployment plan from preview (runs validation again as secondary gate)
		$preview = PreviewApplyArtifactAbility::execute( array( 'generation_id' => $generation_id ) );
		
		if ( $preview['status'] !== 'success' || empty( $preview['plan']['validation_passed'] ) ) {
			return array(
				'message' => 'Preview/Validation failed. Aborting apply.',
				'status'  => 'error',
				'files'   => array(),
			);
		}

		$plan = $preview['plan'];

		// 2. WP_Filesystem Preflight
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) || $wp_filesystem->method !== 'direct' ) {
			return array(
				'message' => 'Apply aborted: WordPress filesystem credentials are required. Configure FS_METHOD=direct or apply manually.',
				'status'  => 'error',
				'files'   => array(),
			);
		}

		// 3. Establish and lock the target root
		$target_root = trailingslashit( WP_PLUGIN_DIR ) . 'systemvibe-generated';

		if ( ! $wp_filesystem->is_dir( $target_root ) ) {
			if ( ! $wp_filesystem->mkdir( $target_root, FS_CHMOD_DIR ) ) {
				return array(
					'message' => 'Failed to create target plugin directory.',
					'status'  => 'error',
					'files'   => array(),
				);
			}
		}

		// Resolve root once, after it is guaranteed to exist
		$target_root_real = realpath( $target_root );
		if ( $target_root_real === false ) {
			return array(
				'message' => 'Boundary error: could not resolve target plugin directory.',
				'status'  => 'error',
				'files'   => array(),
			);
		}
		$target_root_real = wp_normalize_path( $target_root_real );

		$written_files = array();
		$errors        = array();

		// 4. Process each planned file
		foreach ( $plan['files'] as $file_plan ) {
			if ( $file_plan['action'] === 'skip' ) {
				continue;
			}

			// Derive relative path from the 'file' key (already relative, e.g. blocks/demo-block/index.js)
			$relative_path = ltrim( $file_plan['file'], '/' );

			// Reject empty, traversal, absolute, or null-byte paths
			if (
				'' === $relative_path ||
				str_contains( $relative_path, '..' ) ||
				str_starts_with( $relative_path, '/' ) ||
				str_contains( $relative_path, "\0" )
			) {
				$errors[] = "Rejected invalid relative path: {$relative_path}";
				continue;
			}

			$target_file = $target_root . '/' . $relative_path;
			$target_dir  = dirname( $target_file );

			// Create parent directory recursively via WP_Filesystem before resolving realpath
			if ( ! self::wpfs_mkdir_p( $target_dir, $target_root, $target_root_real, $wp_filesystem ) ) {
				$errors[] = "Could not create parent directory for: {$relative_path}";
				continue;
			}

			// Boundary-check the parent DIRECTORY (not the file — it may not exist yet)
			$target_dir_real = realpath( $target_dir );
			if ( $target_dir_real === false ) {
				$errors[] = "Could not resolve parent directory for: {$relative_path}";
				continue;
			}
			$target_dir_real = wp_normalize_path( $target_dir_real );

			if ( strpos( trailingslashit( $target_dir_real ), trailingslashit( $target_root_real ) ) !== 0 ) {
				$errors[] = "Boundary violation for: {$relative_path}";
				continue;
			}

			// Determine content
			$content = '';
			if ( $relative_path === 'systemvibe-generated.php' ) {
				$content = self::get_plugin_scaffold();
			} else {
				$sandbox_result = WorkspaceSandbox::get_file_contents( $generation_id, $relative_path );
				if ( $sandbox_result['status'] === 'success' ) {
					$content = $sandbox_result['content'];
				} else {
					$errors[] = "Failed to read sandbox artifact: {$relative_path}";
					continue;
				}
			}

			// Write via WP_Filesystem
			if ( $wp_filesystem->put_contents( $target_file, $content, FS_CHMOD_FILE ) ) {
				$written_files[] = $relative_path;
			} else {
				$errors[] = "Failed to write: {$relative_path}";
			}
		}

		// 4. Telemetry Log
		$passed = empty( $errors );
		$facts = array(
			'generation_id' => $generation_id,
			'target_plugin' => 'systemvibe-generated',
			'files_written' => count( $written_files ),
			'success'       => $passed,
		);

		$findings = array();
		foreach ( $errors as $err ) {
			$findings[] = array(
				'rule_id'    => 'apply.write',
				'target'     => 'filesystem',
				'status'     => 'failed',
				'severity'   => 'error',
				'passed'     => false,
				'reason'     => $err,
				'block_name' => '',
				'path'       => '',
			);
		}
		if ( $passed && ! empty( $written_files ) ) {
			$findings[] = array(
				'rule_id'    => 'apply.write',
				'target'     => 'filesystem',
				'status'     => 'passed',
				'severity'   => 'info',
				'passed'     => true,
				'reason'     => 'Successfully applied sandbox files to runtime.',
				'block_name' => '',
				'path'       => '',
			);
		}

		TelemetryStore::record( $facts, $findings, 'apply_gate' );

		if ( ! $passed ) {
			return array(
				'message' => 'Apply failed with errors: ' . implode( ', ', $errors ),
				'status'  => 'error',
				'files'   => $written_files,
			);
		}

		// 5. Update blocks/index.json so the container plugin doesn't glob on every request (P2-2)
		self::update_blocks_index( $target_root, $plan['files'] );

		// 6. Bump generated plugin version on re-apply so WordPress detects the change (P2-3)
		self::bump_plugin_version( $target_root, $generation_id );

		return array(
			'message' => 'Successfully applied ' . count( $written_files ) . ' file(s) to systemvibe-generated plugin.',
			'status'  => 'success',
			'files'   => $written_files,
		);
	}

	/**
	 * Recursive directory creation using WP_Filesystem.
	 */
	private static function wpfs_mkdir_p( $path, $target_root, $target_root_real, $wp_filesystem ): bool {
		$path = untrailingslashit( $path );

		if ( is_dir( $path ) ) {
			return true;
		}

		$parent = dirname( $path );

		if ( $parent === $path || '' === $parent ) {
			return false;
		}

		if ( ! self::wpfs_mkdir_p( $parent, $target_root, $target_root_real, $wp_filesystem ) ) {
			return false;
		}

		if ( ! $wp_filesystem->mkdir( $path, FS_CHMOD_DIR ) && ! is_dir( $path ) ) {
			return false;
		}

		$real = realpath( $path );

		return (
			false !== $real &&
			0 === strpos(
				trailingslashit( $real ),
				trailingslashit( $target_root_real )
			)
		);
	}

	/**
	 * Writes or merges blocks/index.json with currently known block directories.
	 */
	private static function update_blocks_index( string $target_dir, array $files_plan ): void {
		$blocks_dir   = $target_dir . '/blocks';
		$index_file   = $blocks_dir . '/index.json';
		$block_dirs   = array();

		// Scan for block.json files to collect valid block directory names
		if ( is_dir( $blocks_dir ) ) {
			foreach ( glob( $blocks_dir . '/*/block.json' ) as $block_json_path ) {
				$block_dirs[] = basename( dirname( $block_json_path ) );
			}
		}

		$block_dirs = array_values( array_unique( $block_dirs ) );
		file_put_contents( $index_file, wp_json_encode( $block_dirs, JSON_PRETTY_PRINT ), LOCK_EX );
	}

	/**
	 * Bumps the Version: header in systemvibe-generated.php after each apply.
	 */
	private static function bump_plugin_version( string $target_dir, string $generation_id ): void {
		$plugin_file = $target_dir . '/systemvibe-generated.php';
		if ( ! file_exists( $plugin_file ) ) {
			return;
		}
		$timestamp   = gmdate( 'Y.m.d.His' );
		$content     = file_get_contents( $plugin_file );
		$content     = preg_replace( '/\* Version:.*/', "* Version:      {$timestamp}", $content );
		file_put_contents( $plugin_file, $content, LOCK_EX );
	}

	private static function get_plugin_scaffold(): string {
		return <<<PHP
<?php
/**
 * Plugin Name: SystemVibe Generated
 * Description: Runtime container for SystemVibe-generated artifacts.
 * Version: 0.1.0
 * Author: SystemVibe
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function() {
	\$index_file = __DIR__ . '/blocks/index.json';

	if ( ! file_exists( \$index_file ) ) {
		return;
	}

	\$block_dirs = json_decode( file_get_contents( \$index_file ), true );
	if ( ! is_array( \$block_dirs ) ) {
		return;
	}

	foreach ( \$block_dirs as \$dir_name ) {
		\$block_path = __DIR__ . '/blocks/' . \$dir_name;
		if ( is_dir( \$block_path ) && file_exists( \$block_path . '/block.json' ) ) {
			register_block_type( \$block_path );
		}
	}
} );
PHP;
	}
}
