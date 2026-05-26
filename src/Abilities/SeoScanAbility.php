<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\TelemetryStore;

/**
 * SEO Scan Ability
 *
 * Validates foundational SEO heuristics using pure WordPress Core AST.
 */
final class SeoScanAbility {

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
				'message'        => 'Open a post or page in the block editor to run SEO Scan.',
				'post_id'        => 0,
				'scan_id'        => '',
				'findings_count' => 0,
				'failed_count'   => 0,
			);
		}

		$post_id = (int) $input['post_id'];
		$post    = get_post( $post_id );
		
		$facts    = array( 'post_id' => $post_id );
		$findings = array();

		if ( ! $post ) {
			return self::finalize( $post_id, $facts, $findings, 'Post not found.' );
		}

		// 1. Slug
		$has_slug = ! empty( $post->post_name );
		$findings[] = array(
			'rule_id'  => 'seo.slug.present',
			'target'   => 'post_name',
			'passed'   => $has_slug,
			'severity' => 'error',
			'status'   => $has_slug ? 'passed' : 'failed',
			'reason'   => $has_slug ? 'Post has a URL slug.' : 'Post URL slug is missing.',
		);

		// 2. Excerpt
		$has_excerpt = ! empty( $post->post_excerpt );
		$findings[] = array(
			'rule_id'  => 'seo.excerpt.present',
			'target'   => 'post_excerpt',
			'passed'   => $has_excerpt,
			'severity' => 'warning',
			'status'   => $has_excerpt ? 'passed' : 'failed',
			'reason'   => $has_excerpt ? 'Post has a manual excerpt.' : 'Post lacks a manual excerpt. Search engines may generate a suboptimal snippet.',
		);

		$blocks = parse_blocks( $post->post_content );
		$facts['blocks'] = $blocks;

		$context = array(
			'word_count'    => 0,
			'has_h2_h3'     => false,
			'first_heading' => null,
			'generic_links' => 0,
		);

		self::traverse_blocks( $blocks, $context );

		// 3. Title repeated as first heading
		$title = trim( $post->post_title );
		$repeated = false;
		if ( $title !== '' && $context['first_heading'] !== null ) {
			$repeated = strcasecmp( $context['first_heading'], $title ) === 0;
		}
		
		$findings[] = array(
			'rule_id'  => 'seo.title.not_repeated_as_first_heading',
			'target'   => 'post_title',
			'passed'   => ! $repeated,
			'severity' => 'warning',
			'status'   => ! $repeated ? 'passed' : 'failed',
			'reason'   => ! $repeated ? 'First heading does not duplicate the post title.' : 'The first heading exactly duplicates the post title. This causes redundant H1/H2 stacking.',
		);

		// 4. Word count
		$wc = $context['word_count'];
		$wc_passed = $wc >= 300;
		$findings[] = array(
			'rule_id'  => 'seo.word_count.minimum',
			'target'   => 'post_content',
			'passed'   => $wc_passed,
			'severity' => 'notice',
			'status'   => $wc_passed ? 'passed' : 'failed',
			'reason'   => $wc_passed ? "Content contains {$wc} words." : "Content contains {$wc} words; recommended minimum is 300.",
		);

		// 5. Heading structure
		$has_h2_h3 = $context['has_h2_h3'];
		$findings[] = array(
			'rule_id'  => 'seo.heading.has_h2_or_h3',
			'target'   => 'headings',
			'passed'   => $has_h2_h3,
			'severity' => 'warning',
			'status'   => $has_h2_h3 ? 'passed' : 'failed',
			'reason'   => $has_h2_h3 ? 'Post contains at least one H2 or H3 for structure.' : 'Post lacks H2 or H3 headings. Break up text with subheadings.',
		);

		// 6. Generic link text
		$generic_count = $context['generic_links'];
		$links_passed = $generic_count === 0;
		$findings[] = array(
			'rule_id'  => 'seo.link_text.not_generic',
			'target'   => 'links',
			'passed'   => $links_passed,
			'severity' => 'warning',
			'status'   => $links_passed ? 'passed' : 'failed',
			'reason'   => $links_passed ? 'No generic link text detected.' : "Found {$generic_count} link(s) with generic text (e.g. 'click here'). Use descriptive anchor text.",
		);

		return self::finalize( $post_id, $facts, $findings, 'SEO Scan complete.' );
	}

	private static function traverse_blocks( array $blocks, array &$context ): void {
		$generic_phrases = array( 'click here', 'read more', 'learn more', 'here', 'more', 'link' );

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) && trim( $block['innerHTML'] ) === '' ) {
				continue;
			}

			$name  = $block['blockName'] ?? 'core/freeform';
			$attrs = $block['attrs'] ?? array();
			$html  = $block['innerHTML'] ?? '';

			// Word count accumulation
			$text = wp_strip_all_tags( $html );
			if ( trim( $text ) !== '' ) {
				$context['word_count'] += str_word_count( $text );
			}

			// Headings analysis
			if ( $name === 'core/heading' ) {
				$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
				if ( ! isset( $attrs['level'] ) && preg_match( '/<h([1-6])/', $html, $matches ) ) {
					$level = (int) $matches[1];
				}

				if ( $level === 2 || $level === 3 ) {
					$context['has_h2_h3'] = true;
				}

				if ( $context['first_heading'] === null ) {
					$context['first_heading'] = trim( $text );
				}
			}

			// Link text analysis
			if ( preg_match_all( '/<a[^>]*>(.*?)<\/a>/is', $html, $matches ) ) {
				foreach ( $matches[1] as $anchor_text ) {
					$clean_text = strtolower( trim( wp_strip_all_tags( $anchor_text ) ) );
					if ( in_array( $clean_text, $generic_phrases, true ) ) {
						$context['generic_links']++;
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::traverse_blocks( $block['innerBlocks'], $context );
			}
		}
	}

	private static function finalize( int $post_id, array $facts, array $findings, string $message ): array {
		$scan_id = TelemetryStore::record( $facts, $findings, 'seo_heuristic' );
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
