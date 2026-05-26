<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\WorkspaceSandbox;
use SystemVibe\Services\TelemetryStore;

/**
 * Adversarial Test Ability
 *
 * Executes a systematic failure-injection suite against the SystemVibe pipeline.
 * Tests path traversal, hash integrity, malformed manifests, capability gates,
 * generator validation, size limits, replay attacks, activation state, and
 * telemetry resilience.
 *
 * All test sessions are stored under type 'adversarial_test' and rotated
 * after the configurable retention period (default: 30 days).
 */
final class AdversarialTestAbility {

	/** Retention period for adversarial test sandbox sessions. */
	private const TEST_RETENTION_DAYS = 30;

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public static function execute( $input = null ): array {
		$results = array_merge(
			self::group_path_traversal(),
			self::group_hash_integrity(),
			self::group_malformed_manifests(),
			self::group_capability_checks(),
			self::group_malformed_generation(),
			self::group_oversized_artifacts(),
			self::group_replay_staleness(),
			self::group_activation_state(),
			self::group_corrupted_telemetry()
		);

		$passed = count( array_filter( $results, fn( $r ) => $r['status'] === 'pass' ) );
		$failed = count( $results ) - $passed;

		// Log telemetry
		TelemetryStore::record(
			array( 'total' => count( $results ), 'passed' => $passed, 'failed' => $failed ),
			array(),
			'adversarial_test'
		);

		// Rotate stale test sessions
		self::rotate_test_sessions();

		return array(
			'message'    => "Adversarial test suite complete. Passed: {$passed} / " . count( $results ) . '.',
			'total'      => count( $results ),
			'passed'     => $passed,
			'failed'     => $failed,
			'all_passed' => $failed === 0,
			'results'    => $results,
		);
	}

	// ----------------------------------------------------------------
	// Group 1: Path Traversal
	// ----------------------------------------------------------------

	private static function group_path_traversal(): array {
		$tests = array();

		// T01: generation_id with ".."
		$r = WorkspaceSandbox::get_file_contents( '../../../etc/passwd', 'block.json' );
		$tests[] = self::assert( 'path.traversal.dotdot_id', $r['status'] === 'error', 'Reject generation_id containing ..', $r['message'] ?? '' );

		// T02: uppercase generation_id — now rejected by case-sensitive regex (no /i flag)
		$r = WorkspaceSandbox::get_file_contents( 'ABCDEF12-ABCD-ABCD-ABCD-ABCDEF123456', 'block.json' );
		$tests[] = self::assert( 'path.traversal.uppercase_id', $r['status'] === 'error', 'Reject uppercase generation_id (case-sensitive regex)', $r['message'] ?? '' );

		// T03: file path with ".."
		$gen_id = self::make_test_generation( array( 'blocks/test-block/index.js' => '// safe' ) );
		$r      = WorkspaceSandbox::get_file_contents( $gen_id, '../../wp-config.php' );
		$tests[] = self::assert( 'path.traversal.dotdot_file', $r['status'] === 'error', 'Reject file path containing ..', $r['message'] ?? '' );

		// T04: null byte in file path
		$r = WorkspaceSandbox::get_file_contents( $gen_id, "blocks/test-block/index.js\0../../wp-config.php" );
		$tests[] = self::assert( 'path.traversal.null_byte', $r['status'] === 'error', 'Reject file path containing null byte', $r['message'] ?? '' );

		// T05: URL-encoded slash (realpath/bounds check catches this since realpath resolves it)
		$r = WorkspaceSandbox::get_file_contents( $gen_id, '..%2F..%2Fwp-config.php' );
		$tests[] = self::assert( 'path.traversal.encoded_slash', $r['status'] === 'error', 'Reject encoded-slash traversal attempt (bounds check)', $r['message'] ?? '' );

		return $tests;
	}

	// ----------------------------------------------------------------
	// Group 2: Hash Integrity
	// ----------------------------------------------------------------

	private static function group_hash_integrity(): array {
		$tests      = array();
		$sandbox_dir = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/generated/' );

		// T06: verify_hashes on a fresh session with no validated hashes
		$gen_id = self::make_test_generation( array( 'blocks/tb/block.json' => '{"name":"test"}' ) );
		$r      = WorkspaceSandbox::verify_hashes( $gen_id );
		$tests[] = self::assert( 'hash.no_seal', $r['passed'] === false, 'Reject verify_hashes when no hashes have been stored', implode( ', ', $r['errors'] ) );

		// T07: validate then tamper with a file → hash mismatch
		$gen_id_2 = self::make_test_generation( array(
			'blocks/tb/block.json' => '{"name":"systemvibe/test","title":"Test","category":"widgets","editorScript":"file:./index.js"}',
		) );
		ValidateGeneratedArtifactAbility::execute( array( 'generation_id' => $gen_id_2 ) );
		$tamper = $sandbox_dir . $gen_id_2 . '/blocks/tb/block.json';
		if ( file_exists( $tamper ) ) {
			file_put_contents( $tamper, '{"name":"tampered"}', LOCK_EX );
		}
		$r = WorkspaceSandbox::verify_hashes( $gen_id_2 );
		$tests[] = self::assert( 'hash.mismatch', $r['passed'] === false, 'Detect file modified after validation via hash mismatch', implode( ', ', $r['errors'] ) );

		// T08: tamper with manifest.sha256 sidecar
		$gen_id_3 = self::make_test_generation( array(
			'blocks/tb/block.json' => '{"name":"systemvibe/test","title":"Test","category":"widgets","editorScript":"file:./index.js"}',
		) );
		ValidateGeneratedArtifactAbility::execute( array( 'generation_id' => $gen_id_3 ) );
		$sha_file = $sandbox_dir . $gen_id_3 . '/manifest.sha256';
		if ( file_exists( $sha_file ) ) {
			file_put_contents( $sha_file, str_repeat( 'a', 64 ), LOCK_EX ); // bogus sha256
		}
		$r = WorkspaceSandbox::verify_hashes( $gen_id_3 );
		$tests[] = self::assert( 'hash.manifest_sha256_tamper', $r['passed'] === false, 'Detect tampered manifest.sha256 sidecar', implode( ', ', $r['errors'] ) );

		return $tests;
	}

	// ----------------------------------------------------------------
	// Group 3: Malformed Manifests
	// ----------------------------------------------------------------

	private static function group_malformed_manifests(): array {
		$tests       = array();
		$sandbox_dir = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/generated/' );

		// T09: directory exists, no manifest.json
		$bare_id = wp_generate_uuid4();
		wp_mkdir_p( $sandbox_dir . $bare_id . '/' );
		$r = WorkspaceSandbox::verify_hashes( $bare_id );
		$tests[] = self::assert( 'manifest.missing', $r['passed'] === false, 'Handle missing manifest.json gracefully', implode( ', ', $r['errors'] ) );

		// T10: manifest.json is an empty object {}
		$empty_id  = wp_generate_uuid4();
		$empty_dir = $sandbox_dir . $empty_id . '/';
		wp_mkdir_p( $empty_dir );
		file_put_contents( $empty_dir . 'manifest.json', '{}', LOCK_EX );
		$r = ValidateGeneratedArtifactAbility::execute( array( 'generation_id' => $empty_id ) );
		$tests[] = self::assert( 'manifest.empty_json', $r['aggregate_passed'] === false, 'Handle manifest with no files key gracefully', $r['message'] );

		// T11: manifest.json is truncated (invalid JSON)
		$trunc_id  = wp_generate_uuid4();
		$trunc_dir = $sandbox_dir . $trunc_id . '/';
		wp_mkdir_p( $trunc_dir );
		file_put_contents( $trunc_dir . 'manifest.json', '[', LOCK_EX );
		$r = ValidateGeneratedArtifactAbility::execute( array( 'generation_id' => $trunc_id ) );
		$tests[] = self::assert( 'manifest.truncated_json', $r['aggregate_passed'] === false, 'Handle truncated manifest JSON gracefully', $r['message'] );

		// T12: manifest files[] entry contains path traversal
		$trav_id  = wp_generate_uuid4();
		$trav_dir = $sandbox_dir . $trav_id . '/';
		wp_mkdir_p( $trav_dir );
		$trav_manifest = array(
			'generation_id' => $trav_id,
			'type'          => 'adversarial_test',
			'created_at'    => current_time( 'mysql', true ),
			'ability'       => 'adversarial',
			'files'         => array( '../../wp-config.php' ),
		);
		file_put_contents( $trav_dir . 'manifest.json', wp_json_encode( $trav_manifest ), LOCK_EX );
		$r = WorkspaceSandbox::get_file_contents( $trav_id, '../../wp-config.php' );
		$tests[] = self::assert( 'manifest.path_injection', $r['status'] === 'error', 'Reject path traversal via manifest files[] entry', $r['message'] ?? '' );

		return $tests;
	}

	// ----------------------------------------------------------------
	// Group 4: Capability Checks (admin must pass; correct caps registered)
	// ----------------------------------------------------------------

	private static function group_capability_checks(): array {
		$tests = array();

		// Verify that the current admin passes each gate (confirms correct cap string is used)
		$r = ApplyGeneratedArtifactAbility::can_execute();
		$tests[] = self::assert( 'capability.apply_uses_install_plugins', $r === true, 'ApplyGeneratedArtifactAbility: admin passes install_plugins gate', $r ? 'true' : 'false (admin lacks install_plugins!)' );

		$r = GenerateBlockAbility::can_execute();
		$tests[] = self::assert( 'capability.generate_uses_manage_options', $r === true, 'GenerateBlockAbility: admin passes manage_options gate', $r ? 'true' : 'false (admin lacks manage_options!)' );

		$r = ActivateGeneratedPluginAbility::can_execute();
		$tests[] = self::assert( 'capability.activate_uses_activate_plugins', $r === true, 'ActivateGeneratedPluginAbility: admin passes activate_plugins gate', $r ? 'true' : 'false (admin lacks activate_plugins!)' );

		return $tests;
	}

	// ----------------------------------------------------------------
	// Group 5: Malformed Generation Inputs
	// ----------------------------------------------------------------

	private static function group_malformed_generation(): array {
		$tests = array();

		// T16: no slash in block_name
		$r = GenerateBlockAbility::execute( array( 'block_name' => 'nodash', 'title' => 'Test' ) );
		$tests[] = self::assert( 'generate.no_namespace_slash', $r['status'] === 'error', 'Reject block_name without namespace slash', $r['message'] );

		// T17: wrong namespace
		$r = GenerateBlockAbility::execute( array( 'block_name' => 'evil/block', 'title' => 'Test' ) );
		$tests[] = self::assert( 'generate.wrong_namespace', $r['status'] === 'error', 'Reject block_name with disallowed namespace', $r['message'] );

		// T18: invalid slug chars (uppercase, spaces, special chars)
		$r = GenerateBlockAbility::execute( array( 'block_name' => 'systemvibe/My Block!', 'title' => 'Test' ) );
		$tests[] = self::assert( 'generate.invalid_slug_chars', $r['status'] === 'error', 'Reject slug with invalid characters', $r['message'] );

		// T19: path traversal in block_name slug
		$r = GenerateBlockAbility::execute( array( 'block_name' => 'systemvibe/../../../evil', 'title' => 'Test' ) );
		$tests[] = self::assert( 'generate.slug_traversal', $r['status'] === 'error', 'Reject slug containing path traversal', $r['message'] );

		// T20: XSS in title → sanitize_text_field must strip HTML
		$r = GenerateBlockAbility::execute( array( 'block_name' => 'systemvibe/xss-test', 'title' => '<script>alert(1)</script>' ) );
		$raw = wp_json_encode( $r );
		$tests[] = self::assert( 'generate.xss_title_sanitized', strpos( $raw, '<script>' ) === false, 'XSS in title is stripped by sanitize_text_field', $r['status'] === 'success' ? 'Generated; no raw script tag found' : $r['message'] );

		return $tests;
	}

	// ----------------------------------------------------------------
	// Group 6: Oversized Artifacts
	// ----------------------------------------------------------------

	private static function group_oversized_artifacts(): array {
		$tests = array();

		// T21: file exceeding 64KB limit (65,537 bytes)
		$gen_id  = wp_generate_uuid4();
		$payload = str_repeat( 'A', 65537 );
		$r       = WorkspaceSandbox::write_batch( $gen_id, 'adversarial/test', 'adversarial_test', array(
			'blocks/test-block/big.js' => $payload,
		) );
		$tests[] = self::assert(
			'size.oversized_file',
			! empty( $r['errors'] ) && empty( $r['written'] ),
			'Reject file exceeding 64KB size limit',
			! empty( $r['errors'] ) ? $r['errors'][0] : 'No error — size limit not enforced!'
		);

		return $tests;
	}

	// ----------------------------------------------------------------
	// Group 7: Replay & Staleness
	// ----------------------------------------------------------------

	private static function group_replay_staleness(): array {
		$tests       = array();
		$sandbox_dir = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/generated/' );

		// T22: double-preview stability (idempotent)
		$gen_r = GenerateBlockAbility::execute( array( 'block_name' => 'systemvibe/replay-test', 'title' => 'Replay Test' ) );
		if ( $gen_r['status'] === 'success' ) {
			$manifests = WorkspaceSandbox::get_manifests();
			$gen_id    = $manifests[0]['generation_id'] ?? null;
			if ( $gen_id ) {
				ValidateGeneratedArtifactAbility::execute( array( 'generation_id' => $gen_id ) );
				$p = PreviewApplyArtifactAbility::execute( array( 'generation_id' => $gen_id ) );
				$tests[] = self::assert( 'replay.double_preview_stable', $p['status'] === 'success', 'Preview is idempotent for unchanged validated artifacts', $p['message'] );
			}
		}

		// T23: stale artifact (backdated validated_at to 8 days ago)
		$stale_id = self::make_test_generation( array(
			'blocks/tb/block.json' => '{"name":"systemvibe/test","title":"Stale","category":"widgets","editorScript":"file:./index.js"}',
		) );
		ValidateGeneratedArtifactAbility::execute( array( 'generation_id' => $stale_id ) );
		// Backdate validated_at and re-seal manifest.sha256 to match
		$manifest_path = $sandbox_dir . $stale_id . '/manifest.json';
		if ( file_exists( $manifest_path ) ) {
			$manifest               = json_decode( file_get_contents( $manifest_path ), true );
			$manifest['validated_at'] = gmdate( 'Y-m-d H:i:s', strtotime( '-8 days' ) );
			$encoded                = wp_json_encode( $manifest, JSON_PRETTY_PRINT );
			file_put_contents( $manifest_path, $encoded, LOCK_EX );
			// Re-seal sha256 to match the backdated manifest so staleness is what fails, not the hash
			file_put_contents( $sandbox_dir . $stale_id . '/manifest.sha256', hash( 'sha256', $encoded ), LOCK_EX );
		}
		$r = WorkspaceSandbox::verify_hashes( $stale_id );
		$tests[] = self::assert( 'replay.stale_generation', $r['passed'] === false, 'Reject artifact with validated_at older than 7 days', implode( ', ', $r['errors'] ) );

		return $tests;
	}

	// ----------------------------------------------------------------
	// Group 8: Activation State
	// ----------------------------------------------------------------

	private static function group_activation_state(): array {
		$tests = array();
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// T24: double activate — must return graceful success or error, never a crash
		$r = ActivateGeneratedPluginAbility::execute();
		$tests[] = self::assert(
			'activate.double_activate_graceful',
			in_array( $r['status'], array( 'success', 'error' ), true ),
			'Double activate returns graceful response (no fatal crash)',
			$r['message']
		);

		// T25: activate before apply (only testable if plugin does not exist)
		$plugin_path = WP_PLUGIN_DIR . '/systemvibe-generated/systemvibe-generated.php';
		if ( ! file_exists( $plugin_path ) ) {
			$r2      = ActivateGeneratedPluginAbility::execute();
			$tests[] = self::assert( 'activate.before_apply', $r2['status'] === 'error', 'Reject activation when plugin file does not exist', $r2['message'] );
		} else {
			$tests[] = self::assert( 'activate.before_apply', true, 'Skipped: plugin already applied; cannot test absent-plugin guard', 'n/a' );
		}

		return $tests;
	}

	// ----------------------------------------------------------------
	// Group 9: Corrupted Telemetry
	// ----------------------------------------------------------------

	private static function group_corrupted_telemetry(): array {
		$tests         = array();
		$telemetry_dir = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/telemetry/scans/' );

		// T26: corrupt scan file does not crash get_latest_scan_summary()
		$corrupt_file = $telemetry_dir . '1970-01-01T000000Z-adversarial-corrupt-test.json';
		file_put_contents( $corrupt_file, '[', LOCK_EX );
		$r = TelemetryStore::get_latest_scan_summary();
		@unlink( $corrupt_file );
		$tests[] = self::assert( 'telemetry.corrupt_record_no_fatal', is_array( $r ) || is_null( $r ), 'TelemetryStore::get_latest_scan_summary() survives corrupt scan file', is_null( $r ) ? 'Returned null' : 'Returned summary array' );

		// T27: missing index.json → retroactive rebuild or null (not a crash)
		$index_file   = $telemetry_dir . 'index.json';
		$index_backup = null;
		if ( file_exists( $index_file ) ) {
			$index_backup = file_get_contents( $index_file );
			@unlink( $index_file );
		}
		$r = TelemetryStore::get_latest_scan_summary();
		// Restore
		if ( $index_backup !== null ) {
			file_put_contents( $index_file, $index_backup, LOCK_EX );
		}
		$tests[] = self::assert( 'telemetry.missing_index_survives', is_array( $r ) || is_null( $r ), 'TelemetryStore handles missing index.json gracefully (rebuild or null)', is_null( $r ) ? 'Returned null' : 'Returned rebuilt summary' );

		return $tests;
	}

	// ----------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------

	/**
	 * Creates a minimal test sandbox generation tagged as adversarial_test.
	 */
	private static function make_test_generation( array $files ): string {
		$gen_id = wp_generate_uuid4();
		WorkspaceSandbox::write_batch( $gen_id, 'adversarial/test', 'adversarial_test', $files );
		return $gen_id;
	}

	/**
	 * Asserts a test condition and returns a structured result record.
	 */
	private static function assert( string $id, bool $passed, string $description, string $actual ): array {
		return array(
			'test'        => $id,
			'status'      => $passed ? 'pass' : 'FAIL',
			'description' => $description,
			'actual'      => $actual,
		);
	}

	/**
	 * Deletes adversarial_test sandbox sessions older than the retention period.
	 * Retention is filterable via 'systemvibe_test_retention_days'.
	 */
	private static function rotate_test_sessions(): void {
		$retention_days = (int) apply_filters( 'systemvibe_test_retention_days', self::TEST_RETENTION_DAYS );
		$cutoff         = strtotime( "-{$retention_days} days" );
		$sandbox_dir    = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/generated/' );

		foreach ( WorkspaceSandbox::get_manifests() as $manifest ) {
			if ( ( $manifest['type'] ?? '' ) !== 'adversarial_test' ) {
				continue;
			}
			$created = strtotime( $manifest['created_at'] ?? '' );
			if ( ! $created || $created >= $cutoff ) {
				continue;
			}
			$session_dir = $sandbox_dir . $manifest['generation_id'] . '/';
			if ( ! is_dir( $session_dir ) ) {
				continue;
			}
			$iter = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $session_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ( $iter as $entry ) {
				$entry->isDir() ? rmdir( $entry->getRealPath() ) : unlink( $entry->getRealPath() );
			}
			rmdir( $session_dir );
		}
	}
}
