import { executeAbility } from '@wordpress/abilities';

const { createElement: el, useState, useEffect } = window.wp.element;
const { Modal, TextControl, TextareaControl, SelectControl, Button, Flex, FlexItem } = window.wp.components;
const { useDispatch } = window.wp.data;

/**
 * SystemVibe Sandbox Block Prompt
 * 
 * Zero-build React Modal using wp.element.createElement.
 */
function GeneratorModal() {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isGenerating, setIsGenerating ] = useState( false );
	
	// Form fields
	const [ blockSlug, setBlockSlug ] = useState( '' );
	const [ title, setTitle ] = useState( '' );
	const [ description, setDescription ] = useState( '' );
	const [ intent, setIntent ] = useState( '' );
	const [ profile, setProfile ] = useState( 'basic' );

	const { createSuccessNotice, createErrorNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		const handleOpen = () => {
			setIsOpen( true );
		};
		
		document.addEventListener( 'systemvibe:open-generator-modal', handleOpen );
		return () => {
			document.removeEventListener( 'systemvibe:open-generator-modal', handleOpen );
		};
	}, [] );

	const closeModal = () => {
		if ( ! isGenerating ) {
			setIsOpen( false );
			// Reset fields
			setBlockSlug( '' );
			setTitle( '' );
			setDescription( '' );
			setIntent( '' );
			setProfile( 'basic' );
		}
	};

	const handleSubmit = async ( e ) => {
		e.preventDefault();

		if ( ! blockSlug || ! title ) {
			createErrorNotice( 'Block Slug and Title are required.', { type: 'snackbar' } );
			return;
		}

		setIsGenerating( true );

		const payload = {
			block_name: 'systemvibe/' + blockSlug,
			title: title,
			description: description,
			intent: intent, // Local template parameter for now
			profile: profile
		};

		try {
			const result = await executeAbility( 'systemvibe/generate-block', payload );
			console.info( '[SystemVibe] Generate Block result:', result );

			if ( result.status === 'success' ) {
				createSuccessNotice( 
					`${result.message} (${result.files.length} files generated)`, 
					{ type: 'snackbar' } 
				);
				closeModal();
			} else {
				createErrorNotice( result.message, { type: 'snackbar' } );
			}
		} catch ( error ) {
			console.error( '[SystemVibe] Generate Block failed:', error );
			createErrorNotice( 'Block generation failed. See console.', { type: 'snackbar' } );
		} finally {
			setIsGenerating( false );
		}
	};

	if ( ! isOpen ) {
		return null;
	}

	return el(
		Modal,
		{
			title: 'Sandbox Block Prompt',
			onRequestClose: closeModal,
			style: { width: '500px' }
		},
		el(
			'form',
			{ onSubmit: handleSubmit },
			el( SelectControl, {
				label: 'Generator Profile',
				value: profile,
				options: [
					{ label: 'Basic Content Block', value: 'basic' },
					{ label: 'CTA Block', value: 'cta' },
					{ label: 'FAQ Block', value: 'faq' },
					{ label: 'Hero Block', value: 'hero' },
					{ label: 'Testimonial Block', value: 'testimonial' },
				],
				onChange: setProfile,
				disabled: isGenerating,
				__next40pxDefaultSize: true
			} ),
			el( TextControl, {
				label: 'Block Slug',
				help: 'Contain only lowercase letters and dashes (e.g. my-first-cta).',
				value: blockSlug,
				onChange: setBlockSlug,
				required: true,
				disabled: isGenerating,
				__next40pxDefaultSize: true
			} ),
			el( TextControl, {
				label: 'Title',
				value: title,
				onChange: setTitle,
				required: true,
				disabled: isGenerating,
				__next40pxDefaultSize: true
			} ),
			el( TextControl, {
				label: 'Description',
				value: description,
				onChange: setDescription,
				disabled: isGenerating,
				__next40pxDefaultSize: true
			} ),
			el( TextareaControl, {
				label: 'Intent / Notes',
				help: 'Local template parameters (future AI prompt binding).',
				value: intent,
				onChange: setIntent,
				rows: 4,
				disabled: isGenerating,
				__next40pxDefaultSize: true
			} ),
			el(
				Flex,
				{ justify: 'flex-end', style: { marginTop: '20px' } },
				el(
					FlexItem,
					null,
					el(
						Button,
						{ isSecondary: true, onClick: closeModal, disabled: isGenerating, __next40pxDefaultSize: true },
						'Cancel'
					)
				),
				el(
					FlexItem,
					null,
					el(
						Button,
						{ isPrimary: true, type: 'submit', isBusy: isGenerating, disabled: isGenerating, __next40pxDefaultSize: true },
						isGenerating ? 'Generating...' : 'Generate Block'
					)
				)
			)
		)
	);
}

// Mount the modal to the DOM injected via admin_footer
document.addEventListener( 'DOMContentLoaded', () => {
	const rootNode = document.getElementById( 'systemvibe-generator-modal-root' );
	if ( rootNode && window.wp && window.wp.element ) {
		window.wp.element.render( el( GeneratorModal ), rootNode );
	}
} );
