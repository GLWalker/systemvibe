<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\WorkspaceSandbox;

/**
 * List Generated Artifacts Ability
 *
 * Retrieves the list of generated artifact manifests from the sandbox.
 */
final class ListGeneratedArtifactsAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public static function execute( $input = null ): array {
		$manifests = WorkspaceSandbox::get_manifests();
		$count     = count( $manifests );

		if ( $count === 0 ) {
			return array(
				'message'   => 'No generated artifacts found in the sandbox.',
				'count'     => 0,
				'manifests' => array(),
			);
		}

		return array(
			'message'   => "Found {$count} generated artifact(s) in the sandbox.",
			'count'     => $count,
			'manifests' => $manifests,
		);
	}
}
