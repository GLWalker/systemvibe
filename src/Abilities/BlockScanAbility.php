<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\TelemetryStore;

/**
 * Block Scan Ability
 *
 * Validates the structural integrity of Gutenberg blocks for a given post.
 */
final class BlockScanAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public static function execute( $input = null ): array {
		$input = (array) $input;
		if ( empty( $input['post_id'] ) ) {
			return array(
				'message'        => 'Open a post or page in the block editor to run Block Scan.',
				'post_id'        => 0,
				'scan_id'        => '',
				'findings_count' => 0,
				'failed_count'   => 0,
			);
		}

		$post_id = (int) $input['post_id'];
		$post    = get_post( $post_id );

		$facts = array(
			'post_id' => $post_id,
		);
		$findings = array();

		// Validation: post exists
		$findings[] = array(
			'rule_id'  => 'post.exists',
			'target'   => 'post_' . $post_id,
			'passed'   => (bool) $post,
			'severity' => 'error',
		);

		if ( ! $post ) {
			return self::finalize( $post_id, $facts, $findings, 'Post not found.' );
		}

		// Validation: post type supports editor
		$supports_editor = post_type_supports( get_post_type( $post ), 'editor' );
		$findings[] = array(
			'rule_id'  => 'post.supports_editor',
			'target'   => 'post_' . $post_id,
			'passed'   => $supports_editor,
			'severity' => 'error',
		);

		if ( ! $supports_editor ) {
			return self::finalize( $post_id, $facts, $findings, 'Post type does not support the block editor.' );
		}

		// Validation: parse blocks
		$blocks = parse_blocks( $post->post_content );
		$facts['blocks'] = $blocks;

		$findings[] = array(
			'rule_id'  => 'post.parses_into_blocks',
			'target'   => 'post_' . $post_id,
			'passed'   => is_array( $blocks ) && ! empty( $blocks ),
			'severity' => 'error',
		);

		$registry = \WP_Block_Type_Registry::get_instance();

		// Traverse blocks
		self::traverse_blocks( $blocks, $registry, $findings );

		return self::finalize( $post_id, $facts, $findings, 'Block Scan complete.' );
	}

	private static function traverse_blocks( array $blocks, \WP_Block_Type_Registry $registry, array &$findings, string $current_path = '' ): void {
		foreach ( $blocks as $index => $block ) {
			// Skip purely empty whitespace elements that parse_blocks sometimes yields
			if ( empty( $block['blockName'] ) && trim( $block['innerHTML'] ) === '' ) {
				continue;
			}

			$name = $block['blockName'] ?? 'core/freeform';
			$path = $current_path === '' ? (string) $index : $current_path . '.innerBlocks.' . $index;
			
			$is_registered = $registry->is_registered( $name );
			$passed = $is_registered || $name === 'core/freeform';

			$findings[] = array(
				'rule_id'    => 'block.registered',
				'target'     => $name,
				'passed'     => $passed,
				'severity'   => 'warning',
				'block_name' => $name,
				'path'       => $path,
				'status'     => $passed ? 'passed' : 'failed',
				'reason'     => $passed ? 'Registered block type found.' : 'Unregistered or deprecated block type.',
			);

			// innerBlocks recurse
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::traverse_blocks( $block['innerBlocks'], $registry, $findings, $path );
			}
		}
	}

	private static function finalize( int $post_id, array $facts, array $findings, string $message ): array {
		$scan_id = TelemetryStore::record( $facts, $findings, 'block_integrity' );
		$failed  = count( array_filter( $findings, fn($f) => $f['passed'] === false ) );

		return array(
			'message'        => $message,
			'post_id'        => $post_id,
			'scan_id'        => $scan_id,
			'findings_count' => count( $findings ),
			'failed_count'   => $failed,
		);
	}
}
