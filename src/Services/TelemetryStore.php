<?php
declare( strict_types = 1 );

namespace SystemVibe\Services;

/**
 * Immutable Telemetry Persistence
 *
 * Saves forensic records of SystemVibe scans to a protected directory.
 * Keeps records outside of the WordPress content workflow.
 */
final class TelemetryStore {

	/**
	 * @var string
	 */
	private static string $telemetry_dir;

	private static int $max_scans = 20;

	/**
	 * Writes a scan record to the telemetry directory.
	 *
	 * @param array  $facts     Raw facts gathered by Scanner.
	 * @param array  $findings  Evaluation findings from RuleEvaluator.
	 * @param string $scan_type Type of scan (e.g. system_scan, block_integrity).
	 * @return string The UUID of the scan.
	 */
	public static function record( array $facts, array $findings, string $scan_type = 'system_scan' ): string {
		self::init();
		self::ensure_directory();

		$scan_id = wp_generate_uuid4();
		$time    = current_time( 'mysql', true );
		// ISO-8601-ish timestamp prefix for sorting: 2026-05-22T230000Z-uuid.json
		$date_prefix = gmdate( 'Y-m-d\THis\Z' ); 
		
		// For plugin and theme hashes, we'll extract them from the facts if available.
		$plugin_hashes = isset( $facts['plugins'] ) ? md5( wp_json_encode( $facts['plugins'] ) ) : '';
		$theme_hashes  = isset( $facts['themes'] ) ? md5( wp_json_encode( $facts['themes'] ) ) : '';

		$record = array(
			'scan_id'       => $scan_id,
			'scan_type'     => $scan_type,
			'created_at'    => $time,
			'wp_version'    => get_bloginfo( 'version' ),
			'site_url_hash' => hash( 'sha256', site_url() ),
			'plugin_hashes' => $plugin_hashes,
			'theme_hashes'  => $theme_hashes,
			'facts'         => $facts,
			'findings'      => $findings,
		);

		$filename = sprintf( '%s-%s.json', $date_prefix, $scan_id );
		$filepath = self::$telemetry_dir . $filename;

		file_put_contents( $filepath, wp_json_encode( $record, JSON_PRETTY_PRINT ), LOCK_EX );
		
		self::update_index( $scan_id, $filename, $time, count( $findings ), count( array_filter( $findings, fn($f) => $f['passed'] === false ) ), $scan_type );
		self::rotate_scans();

		return $scan_id;
	}

	/**
	 * Retrieves the latest scan summary from the index and extracts its failed findings.
	 */
	public static function get_latest_scan_summary(): ?array {
		self::init();
		$index_file = self::$telemetry_dir . 'index.json';
		if ( ! file_exists( $index_file ) ) {
			return null;
		}
		
		$index = json_decode( file_get_contents( $index_file ), true );
		if ( empty( $index ) ) {
			return null;
		}
		
		// The index is naturally ordered chronologically if we append, but let's just get the last one.
		$latest = end( $index );
		
		$failed_findings = array();
		$filepath = self::$telemetry_dir . $latest['filename'];
		if ( file_exists( $filepath ) ) {
			$record = json_decode( file_get_contents( $filepath ), true );
			if ( isset( $record['findings'] ) ) {
				$failed_findings = array_values( array_filter( $record['findings'], fn($f) => $f['passed'] === false ) );
			}
		}
		
		return array(
			'scan_id'         => $latest['scan_id'],
			'created_at'      => $latest['created_at'],
			'findings_count'  => $latest['findings_count'],
			'failed_count'    => $latest['failed_count'],
			'failed_findings' => $failed_findings,
		);
	}

	private static function update_index( string $scan_id, string $filename, string $time, int $total, int $failed, string $scan_type = 'system_scan' ): void {
		$index_file = self::$telemetry_dir . 'index.json';
		$index = array();
		if ( file_exists( $index_file ) ) {
			$index = json_decode( file_get_contents( $index_file ), true ) ?: array();
		}
		
		$index[] = array(
			'scan_id'        => $scan_id,
			'scan_type'      => $scan_type,
			'filename'       => $filename,
			'created_at'     => $time,
			'findings_count' => $total,
			'failed_count'   => $failed,
		);
		
		file_put_contents( $index_file, wp_json_encode( $index, JSON_PRETTY_PRINT ), LOCK_EX );
	}
	
	private static function rotate_scans(): void {
		$index_file = self::$telemetry_dir . 'index.json';
		if ( ! file_exists( $index_file ) ) {
			return;
		}
		
		$index = json_decode( file_get_contents( $index_file ), true ) ?: array();
		if ( count( $index ) <= self::$max_scans ) {
			return;
		}
		
		// Sort by created_at ascending just in case
		usort( $index, fn($a, $b) => strcmp( (string) $a['created_at'], (string) $b['created_at'] ) );
		
		$to_delete = array_slice( $index, 0, count( $index ) - self::$max_scans );
		$to_keep   = array_slice( $index, -self::$max_scans );
		
		foreach ( $to_delete as $scan ) {
			$filepath = self::$telemetry_dir . $scan['filename'];
			if ( file_exists( $filepath ) ) {
				unlink( $filepath );
			}
		}
		
		file_put_contents( $index_file, wp_json_encode( $to_keep, JSON_PRETTY_PRINT ), LOCK_EX );
	}

	private static function init(): void {
		if ( ! isset( self::$telemetry_dir ) ) {
			self::$telemetry_dir = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/telemetry/scans/' );
		}
	}

	private static function ensure_directory(): void {
		if ( ! is_dir( self::$telemetry_dir ) ) {
			wp_mkdir_p( self::$telemetry_dir );
		}

		// Protect the private root directory on all major server stacks
		$root_dir = wp_normalize_path( WP_CONTENT_DIR . '/systemvibe-private/' );
		self::write_server_guards( $root_dir );
		
		// Retroactive index build if index.json is missing but scans exist
		$index_file = self::$telemetry_dir . 'index.json';
		if ( ! file_exists( $index_file ) ) {
			self::build_retroactive_index();
		}
	}

	/**
	 * Writes server access guard files (.htaccess, web.config, index.php) to the given directory.
	 */
	private static function write_server_guards( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Apache
		$htaccess = $dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n", LOCK_EX );
		}

		// IIS
		$web_config = $dir . 'web.config';
		if ( ! file_exists( $web_config ) ) {
			$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
			$xml .= '<configuration><system.webServer><security><requestFiltering>';
			$xml .= '<denyUrlSequences>';
			$xml .= '<add sequence=".json" /><add sequence=".php" />';
			$xml .= '</denyUrlSequences>';
			$xml .= '</requestFiltering></security></system.webServer></configuration>';
			file_put_contents( $web_config, $xml, LOCK_EX );
		}

		// Fallback PHP silence
		$index = $dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX );
		}
	}
	
	private static function build_retroactive_index(): void {
		$files = glob( self::$telemetry_dir . '*.json' );
		if ( ! $files ) {
			return;
		}
		
		$index = array();
		foreach ( $files as $file ) {
			if ( basename( $file ) === 'index.json' ) {
				continue;
			}
			$data = json_decode( file_get_contents( $file ), true );
			if ( $data && isset( $data['scan_id'] ) ) {
				$index[] = array(
					'scan_id'        => $data['scan_id'],
					'scan_type'      => $data['scan_type'] ?? 'system_scan',
					'filename'       => basename( $file ),
					'created_at'     => $data['created_at'] ?? (string) filemtime( $file ),
					'findings_count' => isset( $data['findings'] ) ? count( $data['findings'] ) : 0,
					'failed_count'   => isset( $data['findings'] ) ? count( array_filter( $data['findings'], fn($f) => $f['passed'] === false ) ) : 0,
				);
			}
		}
		
		usort( $index, fn($a, $b) => strcmp( (string) $a['created_at'], (string) $b['created_at'] ) );
		file_put_contents( self::$telemetry_dir . 'index.json', wp_json_encode( $index, JSON_PRETTY_PRINT ), LOCK_EX );
	}
}
