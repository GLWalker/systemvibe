<?php
declare( strict_types = 1 );

namespace SystemVibe\Abilities;

use SystemVibe\Abilities\VibeScanAbility;
use SystemVibe\Abilities\ReadOnlyScanAbility;
use SystemVibe\Abilities\LatestScanSummaryAbility;
use SystemVibe\Abilities\BlockScanAbility;
use SystemVibe\Abilities\AriaScanAbility;
use SystemVibe\Abilities\SeoScanAbility;
use SystemVibe\Abilities\GenerateBlockAbility;
use SystemVibe\Abilities\ListGeneratedArtifactsAbility;
use SystemVibe\Abilities\ViewGeneratedArtifactAbility;
use SystemVibe\Abilities\ValidateGeneratedArtifactAbility;
use SystemVibe\Abilities\PreviewApplyArtifactAbility;
use SystemVibe\Abilities\ApplyGeneratedArtifactAbility;
use SystemVibe\Abilities\ActivateGeneratedPluginAbility;
use SystemVibe\Abilities\AdversarialTestAbility;

/**
 * Registers all SystemVibe ability categories and abilities with the WP 7.0 Abilities API.
 *
 * Called exclusively from Plugin::init() via the correct lifecycle hooks:
 *   - wp_abilities_api_categories_init → register_categories()
 *   - wp_abilities_api_init            → register_abilities()
 */
final class AbilityRegistry {

	public static function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'systemvibe',
			array(
				'label'       => __( 'SystemVibe', 'systemvibe' ),
				'description' => __( 'Armstrong-native SystemVibe expert system abilities.', 'systemvibe' ),
			)
		);
	}

	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'systemvibe/vibe-scan',
			array(
				'label'               => __( 'Vibe Scan', 'systemvibe' ),
				'description'         => __( 'Run the SystemVibe diagnostic handshake.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( VibeScanAbility::class, 'execute' ),
				'permission_callback' => array( VibeScanAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => (object) array(),
					'additionalProperties' => false,
					'default'              => (object) array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
					),
					'required'             => array( 'message', 'status' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/block-scan',
			array(
				'label'               => __( 'Block Scan', 'systemvibe' ),
				'description'         => __( 'Validates the structural integrity of Gutenberg blocks for a given post.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( BlockScanAbility::class, 'execute' ),
				'permission_callback' => array( BlockScanAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type' => 'integer',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message'         => array( 'type' => 'string' ),
						'post_id'         => array( 'type' => 'integer' ),
						'scan_id'         => array( 'type' => 'string' ),
						'findings_count'  => array( 'type' => 'integer' ),
						'failed_count'    => array( 'type' => 'integer' ),
					),
					'required'             => array( 'message', 'post_id', 'scan_id', 'findings_count', 'failed_count' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/aria-scan',
			array(
				'label'               => __( 'ARIA Scan', 'systemvibe' ),
				'description'         => __( 'Validates accessibility and ARIA rules for a given post\'s blocks.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( AriaScanAbility::class, 'execute' ),
				'permission_callback' => array( AriaScanAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type' => 'integer',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message'         => array( 'type' => 'string' ),
						'post_id'         => array( 'type' => 'integer' ),
						'scan_id'         => array( 'type' => 'string' ),
						'findings_count'  => array( 'type' => 'integer' ),
						'failed_count'    => array( 'type' => 'integer' ),
					),
					'required'             => array( 'message', 'post_id', 'scan_id', 'findings_count', 'failed_count' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/seo-scan',
			array(
				'label'               => __( 'SEO Scan', 'systemvibe' ),
				'description'         => __( 'Validates foundational SEO heuristics using pure WordPress Core AST.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( SeoScanAbility::class, 'execute' ),
				'permission_callback' => array( SeoScanAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type' => 'integer',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message'         => array( 'type' => 'string' ),
						'post_id'         => array( 'type' => 'integer' ),
						'scan_id'         => array( 'type' => 'string' ),
						'findings_count'  => array( 'type' => 'integer' ),
						'failed_count'    => array( 'type' => 'integer' ),
					),
					'required'             => array( 'message', 'post_id', 'scan_id', 'findings_count', 'failed_count' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/generate-block',
			array(
				'label'               => __( 'Generate Block', 'systemvibe' ),
				'description'         => __( 'Scaffolds a custom Gutenberg block inside the Workspace Sandbox.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( GenerateBlockAbility::class, 'execute' ),
				'permission_callback' => array( GenerateBlockAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'block_name'  => array( 'type' => 'string' ),
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'intent'      => array( 'type' => 'string' ),
						'profile'     => array(
							'type'    => 'string',
							'enum'    => array( 'basic', 'cta', 'faq', 'hero', 'testimonial' ),
							'default' => 'basic',
						),
					),
					'required'             => array( 'block_name', 'title' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'files'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'             => array( 'message', 'status', 'files' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/list-generated-artifacts',
			array(
				'label'               => __( 'List Generated Artifacts', 'systemvibe' ),
				'description'         => __( 'Retrieves the list of generated artifact manifests from the sandbox.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( ListGeneratedArtifactsAbility::class, 'execute' ),
				'permission_callback' => array( ListGeneratedArtifactsAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => (object) array(),
					'additionalProperties' => false,
					'default'              => (object) array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message'   => array( 'type' => 'string' ),
						'count'     => array( 'type' => 'integer' ),
						'manifests' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'object',
							),
						),
					),
					'required'             => array( 'message', 'count', 'manifests' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/view-generated-artifact',
			array(
				'label'               => __( 'View Generated Artifact', 'systemvibe' ),
				'description'         => __( 'Retrieves the contents of a specific file from a generation sandbox.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( ViewGeneratedArtifactAbility::class, 'execute' ),
				'permission_callback' => array( ViewGeneratedArtifactAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'generation_id' => array( 'type' => 'string' ),
						'file'          => array( 'type' => 'string' ),
					),
					'required'             => array( 'generation_id', 'file' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'status'   => array( 'type' => 'string' ),
						'message'  => array( 'type' => 'string' ),
						'content'  => array( 'type' => 'string' ),
						'metadata' => array(
							'type' => 'object',
						),
					),
					'required'             => array( 'status', 'message', 'content', 'metadata' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/validate-generated-artifact',
			array(
				'label'               => __( 'Validate Generated Artifact', 'systemvibe' ),
				'description'         => __( 'Validates the files in a generated artifact sandbox.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( ValidateGeneratedArtifactAbility::class, 'execute' ),
				'permission_callback' => array( ValidateGeneratedArtifactAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'generation_id' => array( 'type' => 'string' ),
					),
					'required'             => array( 'generation_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message'          => array( 'type' => 'string' ),
						'aggregate_passed' => array( 'type' => 'boolean' ),
						'results'          => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'object',
							),
						),
					),
					'required'             => array( 'message', 'aggregate_passed', 'results' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/preview-apply-artifact',
			array(
				'label'               => __( 'Preview Apply Artifact', 'systemvibe' ),
				'description'         => __( 'Calculates the deployment plan for a sandbox artifact against the systemvibe-generated plugin.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( PreviewApplyArtifactAbility::class, 'execute' ),
				'permission_callback' => array( PreviewApplyArtifactAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'generation_id' => array( 'type' => 'string' ),
					),
					'required'             => array( 'generation_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'plan'    => array(
							'type'                 => 'object',
							'properties'           => array(
								'generation_id'        => array( 'type' => 'string' ),
								'target_plugin'        => array( 'type' => 'string' ),
								'target_path'          => array( 'type' => 'string' ),
								'is_writable_direct'   => array( 'type' => 'boolean' ),
								'filesystem_method'    => array( 'type' => 'string' ),
								'validation_passed'    => array( 'type' => 'boolean' ),
								'plugin_header_needed' => array( 'type' => 'boolean' ),
								'files'                => array(
									'type'  => 'array',
									'items' => array(
										'type' => 'object',
									),
								),
							),
							'required'             => array(
								'generation_id',
								'target_plugin',
								'target_path',
								'is_writable_direct',
								'filesystem_method',
								'validation_passed',
								'plugin_header_needed',
								'files',
							),
							'additionalProperties' => false,
						),
					),
					'required'             => array( 'message', 'status', 'plan' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/apply-generated-artifact',
			array(
				'label'               => __( 'Apply Generated Artifact', 'systemvibe' ),
				'description'         => __( 'Writes a validated sandbox artifact to the runtime plugin container.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( ApplyGeneratedArtifactAbility::class, 'execute' ),
				'permission_callback' => array( ApplyGeneratedArtifactAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'generation_id' => array( 'type' => 'string' ),
					),
					'required'             => array( 'generation_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'files'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'             => array( 'message', 'status', 'files' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/activate-generated-plugin',
			array(
				'label'               => __( 'Activate Generated Plugin', 'systemvibe' ),
				'description'         => __( 'Activates the systemvibe-generated runtime container plugin.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( ActivateGeneratedPluginAbility::class, 'execute' ),
				'permission_callback' => array( ActivateGeneratedPluginAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => (object) array(),
					'additionalProperties' => false,
					'default'              => (object) array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
					),
					'required'             => array( 'message', 'status' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/run-adversarial-tests',
			array(
				'label'               => __( 'Run Adversarial Tests', 'systemvibe' ),
				'description'         => __( 'Executes a failure-injection suite against the SystemVibe pipeline.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( AdversarialTestAbility::class, 'execute' ),
				'permission_callback' => array( AdversarialTestAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => (object) array(),
					'additionalProperties' => false,
					'default'              => (object) array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message'    => array( 'type' => 'string' ),
						'total'      => array( 'type' => 'integer' ),
						'passed'     => array( 'type' => 'integer' ),
						'failed'     => array( 'type' => 'integer' ),
						'all_passed' => array( 'type' => 'boolean' ),
						'results'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
					),
					'required'             => array( 'message', 'total', 'passed', 'failed', 'all_passed', 'results' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/latest-scan-summary',
			array(
				'label'               => __( 'Latest Scan Summary', 'systemvibe' ),
				'description'         => __( 'Read the latest telemetry JSON and return the failed findings, scan ID, timestamp, and summary counts.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( LatestScanSummaryAbility::class, 'execute' ),
				'permission_callback' => array( LatestScanSummaryAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => (object) array(),
					'additionalProperties' => false,
					'default'              => (object) array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message'         => array( 'type' => 'string' ),
						'scan_id'         => array( 'type' => 'string' ),
						'created_at'      => array( 'type' => 'string' ),
						'findings_count'  => array( 'type' => 'integer' ),
						'failed_count'    => array( 'type' => 'integer' ),
						'failed_findings' => array( 
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'rule_id'  => array( 'type' => 'string' ),
									'target'   => array( 'type' => 'string' ),
									'passed'   => array( 'type' => 'boolean' ),
									'severity' => array( 'type' => 'string' ),
								),
							),
						),
					),
					'required'             => array( 'message', 'scan_id', 'created_at', 'findings_count', 'failed_count', 'failed_findings' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'systemvibe/read-only-scan',
			array(
				'label'               => __( 'Read-Only Scan', 'systemvibe' ),
				'description'         => __( 'Run a forensic read-only scan of the WordPress runtime environment.', 'systemvibe' ),
				'category'            => 'systemvibe',
				'execute_callback'    => array( ReadOnlyScanAbility::class, 'execute' ),
				'permission_callback' => array( ReadOnlyScanAbility::class, 'can_execute' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => (object) array(),
					'additionalProperties' => false,
					'default'              => (object) array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'message'        => array( 'type' => 'string' ),
						'scan_id'        => array( 'type' => 'string' ),
						'findings_count' => array( 'type' => 'integer' ),
						'failed_count'   => array( 'type' => 'integer' ),
					),
					'required'             => array( 'message', 'scan_id', 'findings_count', 'failed_count' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}
}
