<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Services\WorkspaceSandbox;

/**
 * Generate Block Ability
 *
 * Scaffolds a custom Gutenberg block inside the Workspace Sandbox.
 */
final class GenerateBlockAbility {

	public static function can_execute( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public static function execute( $input = null ): array {
		$input = (array) $input;
		if ( empty( $input['block_name'] ) || empty( $input['title'] ) ) {
			return array(
				'message' => 'block_name and title are required.',
				'status'  => 'error',
				'files'   => array(),
			);
		}

		$name   = sanitize_text_field( $input['block_name'] );
		$title  = sanitize_text_field( $input['title'] );
		$desc   = isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : '';
		$intent = isset( $input['intent'] ) ? sanitize_textarea_field( $input['intent'] ) : '';

		if ( strpos( $name, '/' ) === false ) {
			return array(
				'message' => 'block_name must include a namespace (e.g., systemvibe/demo-block).',
				'status'  => 'error',
				'files'   => array(),
			);
		}

		list( $namespace, $slug ) = explode( '/', $name, 2 );

		// P1-4: Strict allowlist — only lowercase alphanumeric + hyphens, enforced namespace prefix
		if ( ! preg_match( '/^[a-z][a-z0-9\-]{0,63}$/', $namespace ) ) {
			return array(
				'message' => 'block_name namespace contains invalid characters.',
				'status'  => 'error',
				'files'   => array(),
			);
		}
		if ( ! preg_match( '/^[a-z][a-z0-9\-]{0,63}$/', $slug ) ) {
			return array(
				'message' => 'block_name slug contains invalid characters.',
				'status'  => 'error',
				'files'   => array(),
			);
		}
		if ( $namespace !== 'systemvibe' ) {
			return array(
				'message' => 'block_name must use the systemvibe/ namespace.',
				'status'  => 'error',
				'files'   => array(),
			);
		}

		// Sandbox path prefix
		$prefix = "blocks/{$slug}/";

		$final_desc = $desc;
		if ( ! empty( $intent ) ) {
			$final_desc .= ( $final_desc ? ' | ' : '' ) . 'Intent: ' . str_replace( array( "\r", "\n" ), ' ', $intent );
		}

		$allowed_profiles = array( 'basic', 'cta', 'faq', 'hero', 'testimonial' );
		$profile = isset( $input['profile'] ) && in_array( $input['profile'], $allowed_profiles, true ) ? $input['profile'] : 'basic';

		$template = self::get_profile_template( $profile, array(
			'namespace' => $namespace,
			'slug'      => $slug,
			'title'     => $title,
			'name'      => $name,
		) );

		$block_json = array(
			'$schema'      => 'https://schemas.wp.org/trunk/block.json',
			'apiVersion'   => 3,
			'name'         => $name,
			'version'      => '0.1.0',
			'title'        => $title,
			'category'     => 'widgets',
			'description'  => $final_desc,
			'textdomain'   => $namespace,
			'attributes'   => $template['attributes'],
			'editorScript' => 'file:./index.js',
		);

		$index_php = <<<PHP
<?php
/**
 * Block Name: {$title}
 */
// This is a sandboxed PHP file. It is not loaded by WordPress.
PHP;

		$index_asset_php = <<<PHP
<?php
return array(
	'dependencies' => array( 'wp-blocks', 'wp-element' ),
	'version'      => '0.1.0',
);
PHP;

		$files = array(
			$prefix . 'block.json'      => wp_json_encode( $block_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			$prefix . 'index.php'       => $index_php,
			$prefix . 'index.js'        => $template['index_js'],
			$prefix . 'index.asset.php' => $index_asset_php,
		);

		$generation_id = WorkspaceSandbox::generate_id();
		$metadata = array(
			'block_name' => $name,
			'profile'    => $profile,
		);
		$result = WorkspaceSandbox::write_batch( $generation_id, 'systemvibe/generate-block', 'block', $files, $metadata );

		if ( ! empty( $result['errors'] ) ) {
			return array(
				'message' => 'Generation completed with sandbox errors. Check telemetry.',
				'status'  => 'warning',
				'files'   => $result['written'],
			);
		}

		return array(
			'message' => "Successfully generated {$title} into sandbox {$generation_id}.",
			'status'  => 'success',
			'files'   => $result['written'],
		);
	}

	private static function get_profile_template( string $profile, array $data ): array {
		$namespace = $data['namespace'];
		$slug      = $data['slug'];
		$title     = $data['title'];
		$name      = $data['name'];
		$class     = "wp-block-{$namespace}-{$slug}";

		$attributes = new \stdClass();
		$edit_body  = "return el('div', { className: '{$class}' }, 'Editing: {$title}');";
		$save_body  = "return el('div', { className: '{$class}' }, 'Saved: {$title}');";

		if ( $profile === 'cta' ) {
			$attributes = array(
				'heading'    => array( 'type' => 'string', 'default' => 'Call to Action' ),
				'body'       => array( 'type' => 'string', 'default' => 'Join us today.' ),
				'buttonText' => array( 'type' => 'string', 'default' => 'Click Here' ),
				'buttonUrl'  => array( 'type' => 'string', 'default' => '#' ),
			);
			$edit_body = "return el('div', { className: '{$class}' }, 
				el('h2', null, props.attributes.heading),
				el('p', null, props.attributes.body),
				el('a', { href: props.attributes.buttonUrl, className: 'button' }, props.attributes.buttonText)
			);";
			$save_body = "return el('div', { className: '{$class}' }, 
				el('h2', null, props.attributes.heading),
				el('p', null, props.attributes.body),
				el('a', { href: props.attributes.buttonUrl, className: 'button' }, props.attributes.buttonText)
			);";
		} elseif ( $profile === 'faq' ) {
			$attributes = array(
				'question' => array( 'type' => 'string', 'default' => 'What is this?' ),
				'answer'   => array( 'type' => 'string', 'default' => 'This is the answer.' ),
			);
			$edit_body = "return el('div', { className: '{$class}' },
				el('h3', null, props.attributes.question),
				el('p', null, props.attributes.answer)
			);";
			$save_body = "return el('div', { className: '{$class}' },
				el('h3', null, props.attributes.question),
				el('p', null, props.attributes.answer)
			);";
		} elseif ( $profile === 'hero' ) {
			$attributes = array(
				'heading'    => array( 'type' => 'string', 'default' => 'Hero Heading' ),
				'subheading' => array( 'type' => 'string', 'default' => 'Hero Subheading' ),
			);
			$edit_body = "return el('div', { className: '{$class}' },
				el('h1', null, props.attributes.heading),
				el('p', null, props.attributes.subheading)
			);";
			$save_body = "return el('div', { className: '{$class}' },
				el('h1', null, props.attributes.heading),
				el('p', null, props.attributes.subheading)
			);";
		} elseif ( $profile === 'testimonial' ) {
			$attributes = array(
				'quote'  => array( 'type' => 'string', 'default' => 'This is great!' ),
				'author' => array( 'type' => 'string', 'default' => 'John Doe' ),
			);
			$edit_body = "return el('blockquote', { className: '{$class}' },
				el('p', null, props.attributes.quote),
				el('cite', null, props.attributes.author)
			);";
			$save_body = "return el('blockquote', { className: '{$class}' },
				el('p', null, props.attributes.quote),
				el('cite', null, props.attributes.author)
			);";
		}

		$index_js = <<<JS
/**
 * Block: {$title}
 * Profile: {$profile}
 */
( function( blocks, element ) {
	const el = element.createElement;

	blocks.registerBlockType( '{$name}', {
		edit: function( props ) {
			{$edit_body}
		},
		save: function( props ) {
			{$save_body}
		}
	} );
} )( window.wp.blocks, window.wp.element );
JS;

		return array(
			'attributes' => empty( $attributes ) ? new \stdClass() : $attributes,
			'index_js'   => $index_js,
		);
	}
}
