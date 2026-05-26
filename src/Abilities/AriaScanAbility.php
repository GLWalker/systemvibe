<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\TelemetryStore;

/**
 * ARIA Scan Ability
 *
 * Validates accessibility and ARIA rules for a given post's blocks.
 */
final class AriaScanAbility {

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
				'message'        => 'Open a post or page in the block editor to run ARIA Scan.',
				'post_id'        => 0,
				'scan_id'        => '',
				'findings_count' => 0,
				'failed_count'   => 0,
			);
		}

		$post_id = (int) $input['post_id'];
		$post    = get_post( $post_id );
		
		$facts = array( 'post_id' => $post_id );
		$findings = array();

		if ( ! $post ) {
			return self::finalize( $post_id, $facts, $findings, 'Post not found.' );
		}

		$blocks = parse_blocks( $post->post_content );
		$facts['blocks'] = $blocks;

		$context = array(
			'last_heading_level' => 1, // Assume page title is H1 conceptually
		);

		self::traverse_blocks( $blocks, $findings, $context );

		return self::finalize( $post_id, $facts, $findings, 'ARIA Scan complete.' );
	}

	private static function traverse_blocks( array $blocks, array &$findings, array &$context, string $current_path = '' ): void {
		foreach ( $blocks as $index => $block ) {
			if ( empty( $block['blockName'] ) && trim( $block['innerHTML'] ) === '' ) {
				continue;
			}

			$name  = $block['blockName'] ?? 'core/freeform';
			$path  = $current_path === '' ? (string) $index : $current_path . '.innerBlocks.' . $index;
			$attrs = $block['attrs'] ?? array();
			$html  = $block['innerHTML'] ?? '';

			// 1. core/image requires alt unless decorative
			if ( $name === 'core/image' ) {
				$alt = $attrs['alt'] ?? '';
				$has_alt = $alt !== '' || strpos( $html, 'alt=""' ) !== false;
				
				$findings[] = array(
					'rule_id'    => 'aria.image.alt',
					'target'     => $name,
					'passed'     => $has_alt,
					'severity'   => 'warning',
					'block_name' => $name,
					'path'       => $path,
					'status'     => $has_alt ? 'passed' : 'failed',
					'reason'     => $has_alt ? 'Image has alt attribute.' : 'Image lacks alt text. Mark as decorative if intentional.',
				);
			}

			// 2. core/gallery images require alt review
			if ( $name === 'core/gallery' ) {
				$findings[] = array(
					'rule_id'    => 'aria.gallery.review',
					'target'     => $name,
					'passed'     => false,
					'severity'   => 'warning',
					'block_name' => $name,
					'path'       => $path,
					'status'     => 'failed', // Manual review required
					'reason'     => 'Galleries require manual review of inner image alt texts.',
				);
			}

			// 3. interactive blocks should not invent ARIA roles
			// A crude read-only check looking for hardcoded custom roles
			if ( strpos( $html, 'role="' ) !== false ) {
				$findings[] = array(
					'rule_id'    => 'aria.roles.native',
					'target'     => $name,
					'passed'     => false,
					'severity'   => 'warning',
					'block_name' => $name,
					'path'       => $path,
					'status'     => 'failed',
					'reason'     => 'Role attribute detected. Interactive blocks should not invent ARIA roles if native HTML fits.',
				);
			}

			// 4. heading levels should not skip structurally
			if ( $name === 'core/heading' ) {
				$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
				if ( ! isset( $attrs['level'] ) && preg_match( '/<h([1-6])/', $html, $matches ) ) {
					$level = (int) $matches[1];
				}

				$passed = $level <= ( $context['last_heading_level'] + 1 );
				
				$findings[] = array(
					'rule_id'    => 'aria.heading.hierarchy',
					'target'     => $name,
					'passed'     => $passed,
					'severity'   => 'warning',
					'block_name' => $name,
					'path'       => $path,
					'status'     => $passed ? 'passed' : 'failed',
					'reason'     => $passed ? "Heading level {$level} follows valid hierarchy." : "Heading level skipped from {$context['last_heading_level']} to {$level}.",
				);

				$context['last_heading_level'] = $level;
			}

			// 5. buttons/links should have readable text
			if ( $name === 'core/button' || $name === 'core/navigation-link' ) {
				$text = isset( $attrs['text'] ) ? wp_strip_all_tags( $attrs['text'] ) : wp_strip_all_tags( $html );
				$passed = trim( $text ) !== '';
				
				$findings[] = array(
					'rule_id'    => 'aria.button.text',
					'target'     => $name,
					'passed'     => $passed,
					'severity'   => 'error',
					'block_name' => $name,
					'path'       => $path,
					'status'     => $passed ? 'passed' : 'failed',
					'reason'     => $passed ? 'Interactive element has text.' : 'Interactive element is empty and requires readable text.',
				);
			}

			// innerBlocks recurse
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::traverse_blocks( $block['innerBlocks'], $findings, $context, $path );
			}
		}
	}

	private static function finalize( int $post_id, array $facts, array $findings, string $message ): array {
		$scan_id = TelemetryStore::record( $facts, $findings, 'accessibility' );
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
