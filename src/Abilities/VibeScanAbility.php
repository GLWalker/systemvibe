<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

/**
 * Vibe Scan ability: the Phase 1 diagnostic handshake.
 *
 * PHP is the source of truth for all ability logic.
 * Returns a structured { message, status } object per the registered output_schema.
 */
final class VibeScanAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return array
	 */
	public static function execute( $input = null ): array {
		return array(
			'message' => 'Cyber-Vic is online. SystemVibe handshake confirmed.',
			'status'  => 'ok',
		);
	}
}
