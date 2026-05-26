<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\TelemetryStore;

/**
 * Latest Scan Summary Ability
 *
 * Reads the latest telemetry JSON from the index and returns the failed findings, 
 * scan ID, timestamp, and summary counts.
 */
final class LatestScanSummaryAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return array
	 */
	public static function execute( $input = null ): array {
		$summary = TelemetryStore::get_latest_scan_summary();

		if ( ! $summary ) {
			return array(
				'message'         => 'No telemetry scans found.',
				'scan_id'         => '',
				'created_at'      => '',
				'findings_count'  => 0,
				'failed_count'    => 0,
				'failed_findings' => array(),
			);
		}

		return array(
			'message'         => 'Latest scan summary retrieved.',
			'scan_id'         => $summary['scan_id'],
			'created_at'      => $summary['created_at'],
			'findings_count'  => $summary['findings_count'],
			'failed_count'    => $summary['failed_count'],
			'failed_findings' => $summary['failed_findings'],
		);
	}
}
