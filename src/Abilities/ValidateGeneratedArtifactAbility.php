<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\WorkspaceSandbox;
use SystemVibe\Services\TelemetryStore;

/**
 * Validate Generated Artifact Ability
 *
 * Runs non-executing heuristic validations on sandbox artifacts.
 */
final class ValidateGeneratedArtifactAbility {

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
				'message'          => 'generation_id is required.',
				'aggregate_passed' => false,
				'results'          => array(),
			);
		}

		$generation_id = sanitize_text_field( $input['generation_id'] );
		
		// 1. Get the manifest
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
				'message'          => 'Artifact manifest not found or has no files.',
				'aggregate_passed' => false,
				'results'          => array(),
			);
		}

		$results = array();
		$aggregate_passed = true;

		// 2. Validate each file
		foreach ( $manifest['files'] as $file ) {
			$file_result = WorkspaceSandbox::get_file_contents( $generation_id, $file );
			if ( $file_result['status'] === 'error' ) {
				$results[] = self::create_result( $file, 'unknown', false, 'error', 'Failed to read file: ' . $file_result['message'] );
				$aggregate_passed = false;
				continue;
			}

			$content = $file_result['content'];
			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

			$validation = array( 'passed' => true, 'severity' => 'info', 'reason' => 'File valid.' );

			if ( $ext === 'json' ) {
				$validation = self::validate_json( $file, $content );
			} elseif ( $ext === 'php' ) {
				$validation = self::validate_php( $file, $content );
			} elseif ( $ext === 'js' ) {
				$validation = self::validate_js( $file, $content );
			}

			if ( ! $validation['passed'] ) {
				$aggregate_passed = false;
			}

			$results[] = self::create_result( $file, $ext, $validation['passed'], $validation['severity'], $validation['reason'] );
		}

		// 3. Log Telemetry
		$facts = array(
			'generation_id'    => $generation_id,
			'aggregate_passed' => $aggregate_passed,
		);

		TelemetryStore::record( $facts, $results, 'sandbox_validation' );

		// 4. Store content hashes in manifest (only on pass) to enable apply-time tamper detection
		if ( $aggregate_passed ) {
			self::store_validation_hashes( $generation_id, $manifest );
		}

		return array(
			'message'          => $aggregate_passed ? 'All files passed validation.' : 'Validation failed for one or more files.',
			'aggregate_passed' => $aggregate_passed,
			'results'          => $results,
		);
	}

	/**
	 * Re-reads each manifest file and stores its SHA-256 hash back into the manifest.json.
	 */
	private static function store_validation_hashes( string $generation_id, array $manifest ): void {
		$hashes = array();
		foreach ( $manifest['files'] as $file ) {
			$result = WorkspaceSandbox::get_file_contents( $generation_id, $file );
			if ( $result['status'] === 'success' ) {
				$hashes[ $file ] = hash( 'sha256', $result['content'] );
			}
		}
		WorkspaceSandbox::store_validation_hashes( $generation_id, $hashes );
	}

	private static function create_result( string $file, string $type, bool $passed, string $severity, string $reason ): array {
		return array(
			'file'       => $file,
			'type'       => $type,
			'passed'     => $passed,
			'severity'   => $severity,
			'reason'     => $reason,
			'rule_id'    => "validate.{$type}",
			'target'     => $file,
			'block_name' => '',
			'path'       => '',
			'status'     => $passed ? 'passed' : 'failed',
		);
	}

	private static function validate_json( string $file, string $content ): array {
		$decoded = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'passed' => false, 'severity' => 'error', 'reason' => 'Invalid JSON syntax: ' . json_last_error_msg() );
		}

		// Specific block.json checks
		if ( basename( $file ) === 'block.json' ) {
			$required = array( 'name', 'title', 'category', 'editorScript' );
			foreach ( $required as $req ) {
				if ( empty( $decoded[ $req ] ) ) {
					return array( 'passed' => false, 'severity' => 'error', 'reason' => "block.json missing required field: {$req}" );
				}
			}
			return array( 'passed' => true, 'severity' => 'info', 'reason' => 'Valid block.json structure.' );
		}

		return array( 'passed' => true, 'severity' => 'info', 'reason' => 'Valid JSON structure.' );
	}

	private static function validate_php( string $file, string $content ): array {
		$content = trim( $content );
		if ( strpos( $content, '<?php' ) !== 0 ) {
			return array( 'passed' => false, 'severity' => 'error', 'reason' => 'PHP file must start exactly with <?php' );
		}

		if ( strpos( $content, '?>' ) !== false ) {
			return array( 'passed' => false, 'severity' => 'error', 'reason' => 'PHP file must not contain closing ?> tags.' );
		}

		$tokens = token_get_all( $content );
		
		$denylist = array(
			'eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec',
			'assert', 'create_function', 'file_put_contents', 'unlink', 'rmdir', 'rename',
			'copy', 'chmod', 'chown', 'curl_exec',
			'call_user_func', 'call_user_func_array', 'register_shutdown_function',
			'register_tick_function', 'set_error_handler', 'set_exception_handler',
		);

		$prev_token = null;
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) ) {
				$id   = $token[0];
				$text = strtolower( trim( $token[1] ) );

				if ( $id === T_EVAL || $id === T_INCLUDE || $id === T_INCLUDE_ONCE || $id === T_REQUIRE || $id === T_REQUIRE_ONCE ) {
					return array( 'passed' => false, 'severity' => 'error', 'reason' => "Blocked PHP construct detected: {$text}" );
				}

				if ( $id === T_STRING && in_array( $text, $denylist, true ) ) {
					return array( 'passed' => false, 'severity' => 'error', 'reason' => "Blocked PHP function detected: {$text}" );
				}

				// Block variable-variable ($$var) patterns
				if ( $id === T_VARIABLE && $prev_token !== null && is_array( $prev_token ) && $prev_token[0] === T_VARIABLE ) {
					return array( 'passed' => false, 'severity' => 'error', 'reason' => 'Variable-variable ($$var) pattern detected.' );
				}

				$prev_token = $token;
			} else {
				$prev_token = $token;
			}
		}

		return array( 'passed' => true, 'severity' => 'info', 'reason' => 'PHP structurally safe.' );
	}

	/**
	 * JS Validation is an allowlist/lint gate, not a security sandbox.
	 * Only SystemVibe-generated templates are eligible for apply.
	 * No arbitrary user-authored JS/PHP should be accepted.
	 */
	private static function validate_js( string $file, string $content ): array {
		$denylist = array(
			'eval(', 'new Function(', 'document.write', 'innerHTML =', 
			'localStorage', 'sessionStorage', 'fetch(', 'XMLHttpRequest'
		);

		foreach ( $denylist as $bad ) {
			if ( strpos( $content, $bad ) !== false ) {
				return array( 'passed' => false, 'severity' => 'error', 'reason' => "Blocked JS pattern detected: {$bad}" );
			}
		}

		return array( 'passed' => true, 'severity' => 'info', 'reason' => 'JS visually safe.' );
	}
}
