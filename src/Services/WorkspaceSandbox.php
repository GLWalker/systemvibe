<?php
declare( strict_types = 1 );

namespace SystemVibe\Services;

/**
 * Workspace Sandbox
 *
 * Provides a strictly quarantined environment for code generation.
 * Prevents activation, mutation, and directory traversal.
 */
final class WorkspaceSandbox {

	private static string $sandbox_dir = '';

	/** Maximum sandbox file size in bytes (64KB). Prevents oversized artifact attacks. */
	private static int $max_file_size_bytes = 65536;

	/** Maximum age of a validated artifact in days before Apply rejects it. */
	private static int $staleness_days = 7;

	public static function init(): void {
		if ( empty( self::$sandbox_dir ) ) {
			self::$sandbox_dir = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/generated/' );
		}
		if ( ! is_dir( self::$sandbox_dir ) ) {
			wp_mkdir_p( self::$sandbox_dir );
		}
		// Apache guard
		$htaccess = self::$sandbox_dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" );
		}
		// IIS guard
		$web_config = self::$sandbox_dir . 'web.config';
		if ( ! file_exists( $web_config ) ) {
			$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
			$xml .= '<configuration><system.webServer><security><requestFiltering>';
			$xml .= '<denyUrlSequences>';
			$xml .= '<add sequence=".json" /><add sequence=".php" />';
			$xml .= '</denyUrlSequences>';
			$xml .= '</requestFiltering></security></system.webServer></configuration>';
			file_put_contents( $web_config, $xml );
		}
		// Fallback PHP silence
		$index = self::$sandbox_dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Retrieves all generation manifests from the sandbox.
	 *
	 * @return array List of manifest arrays, sorted newest first.
	 */
	public static function get_manifests(): array {
		self::init();
		
		$manifests = array();
		$dirs = glob( self::$sandbox_dir . '*', GLOB_ONLYDIR );
		
		if ( ! $dirs ) {
			return $manifests;
		}

		foreach ( $dirs as $dir ) {
			$manifest_file = $dir . '/manifest.json';
			if ( file_exists( $manifest_file ) ) {
				$data = json_decode( file_get_contents( $manifest_file ), true );
				
				if ( ! is_array( $data ) ) {
					continue;
				}
				
				if ( empty( $data['generation_id'] ) ) {
					continue;
				}
				
				$manifests[] = $data;
			}
		}

		usort( $manifests, function( $a, $b ) {
			return strtotime( $b['created_at'] ?? '0' ) <=> strtotime( $a['created_at'] ?? '0' );
		} );

		return array_values( array_filter( $manifests, 'is_array' ) );
	}

	/**
	 * Retrieves the contents of a generated file from the sandbox safely.
	 *
	 * @param string $generation_id
	 * @param string $relative_path
	 * @return array Result array with status, content, and metadata
	 */
	public static function get_file_contents( string $generation_id, string $relative_path ): array {
		self::init();
		
		// 1. Basic validation
		if ( ! preg_match( '/^[a-f0-9\-]+$/', $generation_id ) ) {
			return array( 'status' => 'error', 'message' => 'Invalid generation ID.' );
		}

		if ( strpos( $relative_path, '..' ) !== false ) {
			return array( 'status' => 'error', 'message' => 'Path traversal detected.' );
		}

		$session_dir = self::$sandbox_dir . $generation_id . '/';
		if ( ! is_dir( $session_dir ) ) {
			return array( 'status' => 'error', 'message' => 'Generation session not found.' );
		}

		$real_session_dir = wp_normalize_path( realpath( $session_dir ) );
		$full_path        = wp_normalize_path( $session_dir . $relative_path );

		// 2. Strict bounds check
		$real_file_path = wp_normalize_path( realpath( $full_path ) );
		if ( ! $real_file_path || strpos( $real_file_path, $real_session_dir ) !== 0 || ! file_exists( $real_file_path ) ) {
			return array( 'status' => 'error', 'message' => 'File not found or out of bounds.' );
		}

		if ( is_dir( $real_file_path ) ) {
			return array( 'status' => 'error', 'message' => 'Requested path is a directory.' );
		}

		$content = file_get_contents( $real_file_path );
		$stat    = stat( $real_file_path );

		return array(
			'status'   => 'success',
			'content'  => $content,
			'metadata' => array(
				'size'       => $stat['size'] ?? 0,
				'created_at' => gmdate( 'Y-m-d H:i:s', $stat['ctime'] ?? time() ),
			),
		);
	}

	public static function generate_id(): string {
		return wp_generate_uuid4();
	}

	/**
	 * Persists content hashes into the manifest after a successful validation pass.
	 *
	 * @param string $generation_id
	 * @param array  $hashes  file => sha256 string
	 */
	public static function store_validation_hashes( string $generation_id, array $hashes ): void {
		self::init();
		$session_dir   = self::$sandbox_dir . $generation_id . '/';
		$manifest_file = $session_dir . 'manifest.json';
		if ( ! file_exists( $manifest_file ) ) {
			return;
		}
		$manifest = json_decode( file_get_contents( $manifest_file ), true );
		if ( ! is_array( $manifest ) ) {
			return;
		}
		$manifest['validated_at']    = current_time( 'mysql', true );
		$manifest['content_hashes']  = $hashes;
		$encoded                     = wp_json_encode( $manifest, JSON_PRETTY_PRINT );
		file_put_contents( $manifest_file, $encoded, LOCK_EX );

		// Write manifest.sha256 sidecar AFTER the manifest is finalised
		file_put_contents( $session_dir . 'manifest.sha256', hash( 'sha256', $encoded ), LOCK_EX );
	}

	/**
	 * Verifies current file contents match the hashes stored at validation time.
	 *
	 * @param string $generation_id
	 * @return array { passed: bool, errors: string[] }
	 */
	public static function verify_hashes( string $generation_id ): array {
		self::init();
		$session_dir   = self::$sandbox_dir . $generation_id . '/';
		$manifest_file = $session_dir . 'manifest.json';
		if ( ! file_exists( $manifest_file ) ) {
			return array( 'passed' => false, 'errors' => array( 'Manifest not found.' ) );
		}
		$manifest_encoded = file_get_contents( $manifest_file );
		$manifest         = json_decode( $manifest_encoded, true );
		if ( empty( $manifest['content_hashes'] ) ) {
			return array( 'passed' => false, 'errors' => array( 'No validation hashes found. Run Validate first.' ) );
		}

		$errors = array();

		// Staleness gate: reject artifacts validated more than N days ago
		if ( ! empty( $manifest['validated_at'] ) ) {
			$staleness_limit = apply_filters( 'systemvibe_staleness_days', self::$staleness_days );
			$cutoff          = strtotime( "-{$staleness_limit} days" );
			if ( strtotime( $manifest['validated_at'] ) < $cutoff ) {
				$errors[] = "Artifact is stale (validated more than {$staleness_limit} days ago). Re-validate before applying.";
				return array( 'passed' => false, 'errors' => $errors );
			}
		}

		// Manifest integrity: verify manifest.sha256 sidecar
		$sha256_file = $session_dir . 'manifest.sha256';
		if ( file_exists( $sha256_file ) ) {
			$expected_manifest_hash = trim( file_get_contents( $sha256_file ) );
			$actual_manifest_hash   = hash( 'sha256', $manifest_encoded );
			if ( ! hash_equals( $expected_manifest_hash, $actual_manifest_hash ) ) {
				return array( 'passed' => false, 'errors' => array( 'Manifest integrity check failed — manifest.json was modified after validation.' ) );
			}
		}

		// Individual file hash verification
		foreach ( $manifest['content_hashes'] as $file => $expected_hash ) {
			$result = self::get_file_contents( $generation_id, $file );
			if ( $result['status'] !== 'success' ) {
				$errors[] = "Cannot read file for hash check: {$file}";
				continue;
			}
			$actual_hash = hash( 'sha256', $result['content'] );
			if ( ! hash_equals( $expected_hash, $actual_hash ) ) {
				$errors[] = "Hash mismatch — file was modified after validation: {$file}";
			}
		}
		return array( 'passed' => empty( $errors ), 'errors' => $errors );
	}

	/**
	 * Writes a batch of files to the sandbox for a given session.
	 * 
	 * @param string $generation_id
	 * @param string $ability_name
	 * @param string $type
	 * @param array  $files Array of relative_path => content
	 * @return array Result with status and paths
	 */
	public static function write_batch( string $generation_id, string $ability_name, string $type, array $files, array $metadata = array() ): array {
		self::init();
		
		$session_dir = self::$sandbox_dir . $generation_id . '/';
		if ( ! is_dir( $session_dir ) ) {
			wp_mkdir_p( $session_dir );
		}

		$real_session_dir = wp_normalize_path( realpath( $session_dir ) );
		$written_files = array();
		$allowed_extensions = array( 'json', 'php', 'js', 'css', 'md', 'txt' );
		$errors = array();

		foreach ( $files as $relative_path => $content ) {
			// 1. Reject traversal
			if ( strpos( $relative_path, '..' ) !== false ) {
				$errors[] = "Path traversal detected: $relative_path";
				continue;
			}

			// 2. Validate extension
			$ext = pathinfo( $relative_path, PATHINFO_EXTENSION );
			if ( ! in_array( strtolower( $ext ), $allowed_extensions, true ) ) {
				$errors[] = "Extension not allowed: $relative_path";
				continue;
			}

			// 3. Enforce file size limit
			$size_limit = (int) apply_filters( 'systemvibe_max_file_size_bytes', self::$max_file_size_bytes );
			if ( strlen( $content ) > $size_limit ) {
				$errors[] = "File exceeds maximum size limit ({$size_limit} bytes): {$relative_path}";
				continue;
			}

			$full_path = wp_normalize_path( $session_dir . $relative_path );
			$dir = dirname( $full_path );
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			// 3. Resolve real paths and enforce boundary
			$real_dir = wp_normalize_path( realpath( $dir ) );
			if ( ! $real_dir || strpos( $real_dir, $real_session_dir ) !== 0 ) {
				$errors[] = "Path resolution error (out of bounds): $relative_path";
				continue;
			}

			// 4. Write
			if ( file_put_contents( $full_path, $content, LOCK_EX ) !== false ) {
				$written_files[] = $relative_path;
			} else {
				$errors[] = "Failed to write: $relative_path";
			}
		}

		// 5. Write Manifest
		$manifest = array(
			'generation_id' => $generation_id,
			'type'          => $type,
			'created_at'    => current_time( 'mysql', true ),
			'ability'       => $ability_name,
			'files'         => $written_files,
		);
		if ( ! empty( $metadata ) ) {
			$manifest['metadata'] = $metadata;
		}

		file_put_contents( $session_dir . 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ), LOCK_EX );

		// 6. Log Telemetry
		$facts = array(
			'generation_id' => $generation_id,
			'type'          => $type,
			'ability'       => $ability_name,
			'files_written' => count( $written_files ),
		);
		$facts = array_merge( $facts, $metadata );

		$findings = array();

		foreach ( $errors as $error ) {
			$findings[] = array(
				'rule_id'    => 'sandbox.write',
				'target'     => 'filesystem',
				'passed'     => false,
				'severity'   => 'error',
				'block_name' => '',
				'path'       => '',
				'status'     => 'failed',
				'reason'     => $error,
			);
		}

		if ( empty( $errors ) ) {
			$findings[] = array(
				'rule_id'    => 'sandbox.write',
				'target'     => 'filesystem',
				'passed'     => true,
				'severity'   => 'info',
				'block_name' => '',
				'path'       => '',
				'status'     => 'passed',
				'reason'     => 'All files written successfully to sandbox.',
			);
		}

		TelemetryStore::record( $facts, $findings, 'generation' );

		return array(
			'generation_id' => $generation_id,
			'written'       => $written_files,
			'errors'        => $errors,
		);
	}
}
