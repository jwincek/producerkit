/**
 * Commission Request Form — editor block (no-build IIFE).
 *
 * The form only does anything on the front end, so the editor shows a static
 * summary rather than a live copy: a rendered form invites someone to try
 * filling it in from the editor, which cannot work.
 *
 * @param blocks
 * @param element
 * @param blockEditor
 * @param components
 * @param i18n
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	const el = element.createElement;
	const Fragment = element.Fragment;
	const __ = i18n.__;
	const registerBlockType = blocks.registerBlockType;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const RichText = blockEditor.RichText;
	const PanelBody = components.PanelBody;
	const ToggleControl = components.ToggleControl;
	const Placeholder = components.Placeholder;

	registerBlockType( 'producerkit/commission-form', {
		edit: function EditCommissionForm( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const blockProps = useBlockProps();

			const fields = [
				__( 'Name', 'producerkit' ),
				__( 'Email', 'producerkit' ),
			];
			if ( attributes.showBudget ) {
				fields.push( __( 'Budget', 'producerkit' ) );
			}
			if ( attributes.showDeadline ) {
				fields.push( __( 'Needed by', 'producerkit' ) );
			}

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Form fields', 'producerkit' ) },
						el( ToggleControl, {
							label: __( 'Ask for a budget', 'producerkit' ),
							help: __(
								'A rough range helps you quote without a back-and-forth.',
								'producerkit'
							),
							checked: !! attributes.showBudget,
							onChange( value ) {
								setAttributes( { showBudget: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Ask when they need it', 'producerkit' ),
							checked: !! attributes.showDeadline,
							onChange( value ) {
								setAttributes( { showDeadline: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( RichText, {
						tagName: 'h2',
						value: attributes.heading,
						allowedFormats: [],
						placeholder:
							( window.pkitSettings &&
							window.pkitSettings.requestWords
								? window.pkitSettings.requestWords.action
								: null ) ||
							__( 'Commission a piece', 'producerkit' ),
						onChange( value ) {
							setAttributes( { heading: value } );
						},
					} ),
					el( RichText, {
						tagName: 'p',
						value: attributes.intro,
						allowedFormats: [ 'core/bold', 'core/italic' ],
						placeholder: __(
							'Tell visitors what you take on, and roughly how long it takes.',
							'producerkit'
						),
						onChange( value ) {
							setAttributes( { intro: value } );
						},
					} ),
					el(
						Placeholder,
						{
							icon: 'clipboard',
							label: __(
								'Commission Request Form',
								'producerkit'
							),
							instructions: __(
								'The form appears on the front end. Type and material options come from your producer profile.',
								'producerkit'
							),
						},
						el(
							'p',
							{ style: { margin: 0, opacity: 0.75 } },
							fields.join( ' · ' ) +
								' · ' +
								__( 'What would you like made?', 'producerkit' )
						)
					)
				)
			);
		},

		// Server-rendered.
		save() {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
