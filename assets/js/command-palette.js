/**
 * SystemVibe Command Palette Bridge
 *
 * Authority: JavaScript owns Command Palette binding only.
 * PHP (AbilityRegistry + VibeScanAbility) is the source of truth for ability logic.
 *
 * This module:
 *   1. Imports @wordpress/core-abilities and waits for REST hydration (ready promise).
 *   2. Confirms systemvibe/vibe-scan exists in the hydrated store.
 *   3. Registers the Command Palette command via @wordpress/commands store.
 *   4. Executes the ability server-side via @wordpress/abilities executeAbility().
 *
 * MUST NOT call registerAbility(). The ability is registered server-side via PHP.
 */

import { store as abilitiesStore } from '@wordpress/abilities';
import { executeAbility } from '@wordpress/abilities';
import { ready } from '@wordpress/core-abilities';

// Wait for @wordpress/core-abilities to finish fetching abilities from the REST API.
await ready;

// Pull the Command Palette globals (loaded by wp_enqueue_script('wp-commands')).
const commandsStore = window.wp?.commands?.store || 'core/commands';
const rawCommandsDispatch = window.wp?.data?.dispatch( commandsStore );

if ( ! rawCommandsDispatch?.registerCommand ) {
	console.warn( '[SystemVibe] Commands store unavailable.' );
}

const commandsDispatch = rawCommandsDispatch?.registerCommand
	? rawCommandsDispatch
	: { registerCommand: () => {} };

const { select } = window.wp.data;

// Confirm the ability was hydrated from the server before binding.
const hydrated = select( abilitiesStore ).getAbilities();
const exists   = Array.isArray( hydrated ) && hydrated.some( ( a ) => a.name === 'systemvibe/vibe-scan' );

if ( ! exists ) {
	console.warn( '[SystemVibe] systemvibe/vibe-scan was not found in the hydrated abilities store. Check that show_in_rest is true and the user has manage_options.' );
}

commandsDispatch.registerCommand( {
	name:     'systemvibe/vibe-scan-command',
	label:    'SystemVibe: Vibe Scan',
	category: 'command',
	keywords: [ 'systemvibe', 'vibe', 'scan', 'cyber-vic' ],
	callback: async ( { close } ) => {
		try {
			const result = await executeAbility( 'systemvibe/vibe-scan', {} );
			console.info( '[SystemVibe] Vibe Scan result:', result );
			window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( result.message, {
				type: 'snackbar',
			} );
		} catch ( error ) {
			console.error( '[SystemVibe] Vibe Scan failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Vibe Scan failed', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/read-only-scan-command',
	label:    'SystemVibe: Read-Only Scan',
	category: 'command',
	keywords: [ 'systemvibe', 'scan', 'forensic', 'telemetry' ],
	callback: async ( { close } ) => {
		try {
			const result = await executeAbility( 'systemvibe/read-only-scan', {} );
			console.info( '[SystemVibe] Read-Only Scan result:', result );
			window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
				`${result.message} (ID: ${result.scan_id}, Findings: ${result.findings_count}, Failed: ${result.failed_count})`, 
				{ type: 'snackbar' } 
			);
		} catch ( error ) {
			console.error( '[SystemVibe] Read-Only Scan failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Read-Only Scan failed', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/generate-block-command',
	label:    'SystemVibe: Open Block Generator',
	category: 'command',
	keywords: [ 'systemvibe', 'generate', 'block', 'sandbox', 'scaffold', 'prompt' ],
	callback: ( { close } ) => {
		document.dispatchEvent( new Event( 'systemvibe:open-generator-modal' ) );
		close();
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/list-generated-artifacts-command',
	label:    'SystemVibe: List Generated Artifacts',
	category: 'command',
	keywords: [ 'systemvibe', 'list', 'artifacts', 'sandbox', 'generation' ],
	callback: async ( { close } ) => {
		try {
			const result = await executeAbility( 'systemvibe/list-generated-artifacts', {} );
			
			console.info( '[SystemVibe] List Generated Artifacts:', result );
			
			if ( result.count > 0 ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${result.message} Check browser console for full manifest details.`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( result.message, { type: 'snackbar' } );
			}
		} catch ( error ) {
			console.error( '[SystemVibe] List Generated Artifacts failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Failed to list artifacts', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/view-generated-artifact-command',
	label:    'SystemVibe: View Generated Artifact (Latest)',
	category: 'command',
	keywords: [ 'systemvibe', 'view', 'artifact', 'sandbox', 'generation' ],
	callback: async ( { close } ) => {
		try {
			// Step 1: Fetch the latest generation dynamically
			const listResult = await executeAbility( 'systemvibe/list-generated-artifacts', {} );
			
			if ( ! listResult.count || listResult.manifests.length === 0 ) {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					'No generated artifacts found. Generate something first.', 
					{ type: 'snackbar' } 
				);
				close();
				return;
			}

			const latestManifest = listResult.manifests[0];
			const generationId   = latestManifest.generation_id;
			const targetFile     = latestManifest.files[0]; // Just grab the first file to prove the pipeline

			if ( ! targetFile ) {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					'Latest generation has no files.', 
					{ type: 'snackbar' } 
				);
				close();
				return;
			}

			// Step 2: Read the actual file contents via the new ability
			const viewResult = await executeAbility( 'systemvibe/view-generated-artifact', {
				generation_id: generationId,
				file: targetFile
			} );
			
			console.info( '[SystemVibe] View Generated Artifact:', viewResult );
			
			if ( viewResult.status === 'success' ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${viewResult.message} (${viewResult.metadata.size} bytes). Check console to read contents.`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( viewResult.message, { type: 'snackbar' } );
			}
		} catch ( error ) {
			console.error( '[SystemVibe] View Generated Artifact failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Failed to view artifact', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/validate-generated-artifact-command',
	label:    'SystemVibe: Validate Generated Artifact (Latest)',
	category: 'command',
	keywords: [ 'systemvibe', 'validate', 'artifact', 'sandbox', 'generation' ],
	callback: async ( { close } ) => {
		try {
			// Fetch the latest generation dynamically
			const listResult = await executeAbility( 'systemvibe/list-generated-artifacts', {} );
			
			if ( ! listResult.count || listResult.manifests.length === 0 ) {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					'No generated artifacts found. Generate something first.', 
					{ type: 'snackbar' } 
				);
				close();
				return;
			}

			const latestManifest = listResult.manifests[0];
			const generationId   = latestManifest.generation_id;

			const valResult = await executeAbility( 'systemvibe/validate-generated-artifact', {
				generation_id: generationId
			} );
			
			console.info( '[SystemVibe] Validate Generated Artifact:', valResult );
			
			if ( valResult.aggregate_passed ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${valResult.message} Check console for results.`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					valResult.message, 
					{ type: 'snackbar' } 
				);
			}
		} catch ( error ) {
			console.error( '[SystemVibe] Validate Generated Artifact failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Failed to validate artifact', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/preview-apply-artifact-command',
	label:    'SystemVibe: Preview Apply Artifact (Latest)',
	category: 'command',
	keywords: [ 'systemvibe', 'preview', 'apply', 'artifact', 'sandbox', 'generation' ],
	callback: async ( { close } ) => {
		try {
			const listResult = await executeAbility( 'systemvibe/list-generated-artifacts', {} );
			
			if ( ! listResult.count || listResult.manifests.length === 0 ) {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					'No generated artifacts found.', 
					{ type: 'snackbar' } 
				);
				close();
				return;
			}

			const latestManifest = listResult.manifests[0];
			const generationId   = latestManifest.generation_id;

			const previewResult = await executeAbility( 'systemvibe/preview-apply-artifact', {
				generation_id: generationId
			} );
			
			console.info( '[SystemVibe] Preview Apply Artifact:', previewResult );
			
			if ( previewResult.status === 'success' ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${previewResult.message} Check console for diff plan.`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					previewResult.message, 
					{ type: 'snackbar' } 
				);
			}
		} catch ( error ) {
			console.error( '[SystemVibe] Preview Apply failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Failed to preview apply.', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/apply-generated-artifact-command',
	label:    'SystemVibe: Apply Generated Artifact (Latest)',
	category: 'command',
	keywords: [ 'systemvibe', 'apply', 'artifact', 'sandbox', 'generation', 'write' ],
	callback: async ( { close } ) => {
		try {
			const listResult = await executeAbility( 'systemvibe/list-generated-artifacts', {} );
			
			if ( ! listResult.count || listResult.manifests.length === 0 ) {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					'No generated artifacts found.', 
					{ type: 'snackbar' } 
				);
				close();
				return;
			}

			const latestManifest = listResult.manifests[0];
			const generationId   = latestManifest.generation_id;

			const applyResult = await executeAbility( 'systemvibe/apply-generated-artifact', {
				generation_id: generationId
			} );
			
			console.info( '[SystemVibe] Apply Artifact:', applyResult );
			
			if ( applyResult.status === 'success' ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${applyResult.message}`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					applyResult.message, 
					{ type: 'snackbar' } 
				);
			}
		} catch ( error ) {
			console.error( '[SystemVibe] Apply failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Failed to apply artifact.', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/activate-generated-plugin-command',
	label:    'SystemVibe: Activate Generated Plugin',
	category: 'command',
	keywords: [ 'systemvibe', 'activate', 'plugin', 'runtime' ],
	callback: async ( { close } ) => {
		try {
			const activateResult = await executeAbility( 'systemvibe/activate-generated-plugin', {} );
			
			console.info( '[SystemVibe] Activate Plugin:', activateResult );
			
			if ( activateResult.status === 'success' ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${activateResult.message}`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					activateResult.message, 
					{ type: 'snackbar' } 
				);
			}
		} catch ( error ) {
			console.error( '[SystemVibe] Activate failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Failed to activate plugin.', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/run-adversarial-tests-command',
	label:    'SystemVibe: Run Adversarial Tests',
	category: 'command',
	keywords: [ 'systemvibe', 'adversarial', 'test', 'security', 'hardening', 'injection' ],
	callback: async ( { close } ) => {
		try {
			window.wp.data.dispatch( 'core/notices' ).createInfoNotice(
				'Running adversarial test suite…',
				{ type: 'snackbar', id: 'sv-adversarial-running' }
			);

			const result = await executeAbility( 'systemvibe/run-adversarial-tests', {} );
			console.info( '[SystemVibe] Adversarial Test Results:', result );

			const failedTests = result.results.filter( r => r.status === 'FAIL' );
			if ( failedTests.length > 0 ) {
				console.warn( '[SystemVibe] FAILED tests:', failedTests );
			}

			window.wp.data.dispatch( 'core/notices' ).removeNotice( 'sv-adversarial-running' );

			if ( result.all_passed ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice(
					`${result.message} All ${result.total} tests passed.`,
					{ type: 'snackbar' }
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice(
					`${result.message} ${result.failed} test(s) FAILED — see console.`,
					{ type: 'snackbar' }
				);
			}
		} catch ( error ) {
			console.error( '[SystemVibe] Adversarial test runner failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Adversarial test runner failed.', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/seo-scan-command',
	label:    'SystemVibe: SEO Scan',
	category: 'command',
	keywords: [ 'systemvibe', 'scan', 'seo', 'optimization', 'content' ],
	callback: async ( { close } ) => {
		try {
			const postId = window.wp?.data?.select( 'core/editor' )?.getCurrentPostId?.();
			const result = await executeAbility( 'systemvibe/seo-scan', {
				post_id: postId ? Number( postId ) : undefined,
			} );
			
			console.info( '[SystemVibe] SEO Scan result:', result );
			
			if ( result.failed_count > 0 ) {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					`${result.message} (${result.failed_count} SEO issues)`, 
					{ type: 'snackbar' } 
				);
			} else if ( result.scan_id ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${result.message} (Clean: ${result.findings_count} findings)`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( result.message, { type: 'snackbar' } );
			}
		} catch ( error ) {
			console.error( '[SystemVibe] SEO Scan failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'SEO Scan failed', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/aria-scan-command',
	label:    'SystemVibe: ARIA Scan',
	category: 'command',
	keywords: [ 'systemvibe', 'scan', 'aria', 'accessibility', 'a11y' ],
	callback: async ( { close } ) => {
		try {
			const postId = window.wp?.data?.select( 'core/editor' )?.getCurrentPostId?.();
			const result = await executeAbility( 'systemvibe/aria-scan', {
				post_id: postId ? Number( postId ) : undefined,
			} );
			
			console.info( '[SystemVibe] ARIA Scan result:', result );
			
			if ( result.failed_count > 0 ) {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					`${result.message} (${result.failed_count} a11y issues)`, 
					{ type: 'snackbar' } 
				);
			} else if ( result.scan_id ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${result.message} (Clean: ${result.findings_count} findings)`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( result.message, { type: 'snackbar' } );
			}
		} catch ( error ) {
			console.error( '[SystemVibe] ARIA Scan failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'ARIA Scan failed', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/block-scan-command',
	label:    'SystemVibe: Block Scan',
	category: 'command',
	keywords: [ 'systemvibe', 'scan', 'block', 'editor', 'gutenberg' ],
	callback: async ( { close } ) => {
		try {
			const postId = window.wp?.data?.select( 'core/editor' )?.getCurrentPostId?.();
			const result = await executeAbility( 'systemvibe/block-scan', {
				post_id: postId ? Number( postId ) : undefined,
			} );
			
			console.info( '[SystemVibe] Block Scan result:', result );
			
			if ( result.failed_count > 0 ) {
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					`${result.message} (${result.failed_count} failures)`, 
					{ type: 'snackbar' } 
				);
			} else if ( result.scan_id ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`${result.message} (Clean: ${result.findings_count} findings)`, 
					{ type: 'snackbar' } 
				);
			} else {
				// E.g. "Open a post or page in the block editor to run Block Scan."
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( result.message, { type: 'snackbar' } );
			}
		} catch ( error ) {
			console.error( '[SystemVibe] Block Scan failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Block Scan failed', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );

commandsDispatch.registerCommand( {
	name:     'systemvibe/latest-scan-summary-command',
	label:    'SystemVibe: Latest Scan Summary',
	category: 'command',
	keywords: [ 'systemvibe', 'scan', 'summary', 'telemetry', 'review' ],
	callback: async ( { close } ) => {
		try {
			const result = await executeAbility( 'systemvibe/latest-scan-summary', {} );
			console.info( '[SystemVibe] Latest Scan Summary:', result );
			
			if ( result.failed_count > 0 ) {
				console.warn( `[SystemVibe] Scan ${result.scan_id} had ${result.failed_count} failures:`, result.failed_findings );
				window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 
					`Scan ${result.scan_id} on ${result.created_at} had ${result.failed_count} failures. See console for details.`, 
					{ type: 'snackbar' } 
				);
			} else if ( result.scan_id ) {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( 
					`Scan ${result.scan_id} on ${result.created_at} passed cleanly (${result.findings_count} findings).`, 
					{ type: 'snackbar' } 
				);
			} else {
				window.wp.data.dispatch( 'core/notices' ).createSuccessNotice( result.message, { type: 'snackbar' } );
			}
		} catch ( error ) {
			console.error( '[SystemVibe] Latest Scan Summary failed:', error );
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice( 'Latest Scan Summary failed', {
				type: 'snackbar',
			} );
		} finally {
			close();
		}
	},
} );
