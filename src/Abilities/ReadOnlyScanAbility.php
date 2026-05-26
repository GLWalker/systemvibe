<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Engine\Scanner;
use SystemVibe\Engine\RuleEvaluator;
use SystemVibe\Services\TelemetryStore;

/**
 * Read-Only Scan ability: The forensic scanner pipeline.
 *
 * Gathers facts, evaluates rules, stores telemetry, and returns the scan ID and findings summary.
 */
final class ReadOnlyScanAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return array{ message: string, scan_id: string, findings_count: int, failed_count: int }
	 */
	public static function execute( $input = null ): array {
		$facts     = Scanner::scan();
		$evaluator = new RuleEvaluator();
		$findings  = $evaluator->evaluate( $facts );
		
		$scan_id = TelemetryStore::record( $facts, $findings );

		$failed_count = count( array_filter( $findings, fn( $f ) => $f['passed'] === false ) );

		return array(
			'message'        => 'SystemVibe Read-Only Scan complete. Telemetry recorded.',
			'scan_id'        => $scan_id,
			'findings_count' => count( $findings ),
			'failed_count'   => $failed_count,
		);
	}
}
