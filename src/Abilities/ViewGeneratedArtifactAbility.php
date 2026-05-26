<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\WorkspaceSandbox;

/**
 * View Generated Artifact Ability
 *
 * Retrieves the contents of a specific file from a generation sandbox.
 */
final class ViewGeneratedArtifactAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public static function execute( $input = null ): array {
		$input = (array) $input;
		if ( empty( $input['generation_id'] ) || empty( $input['file'] ) ) {
			return array(
				'status'   => 'error',
				'message'  => 'generation_id and file are required.',
				'content'  => '',
				'metadata' => array(),
			);
		}

		$generation_id = sanitize_text_field( $input['generation_id'] );
		$file          = sanitize_text_field( $input['file'] );

		$result = WorkspaceSandbox::get_file_contents( $generation_id, $file );

		if ( $result['status'] === 'error' ) {
			return array(
				'status'   => 'error',
				'message'  => $result['message'],
				'content'  => '',
				'metadata' => array(),
			);
		}

		return array(
			'status'   => 'success',
			'message'  => "File retrieved: {$file}",
			'content'  => $result['content'],
			'metadata' => $result['metadata'],
		);
	}
}
