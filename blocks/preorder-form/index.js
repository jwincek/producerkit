/**
 * Pre-Order Form — editor block (no-build IIFE).
 *
 * The Interactivity view module only runs on the front end, so the
 * editor shows a static summary placeholder.
 * @param blocks
 * @param element
 * @param blockEditor
 * @param components
 * @param data
 * @param i18n
 */
( function ( blocks, element, blockEditor, components, data, i18n ) {
	'use strict';

	const el = element.createElement;
	const __ = i18n.__;
	const sprintf = i18n.sprintf;
	const Fragment = element.Fragment;
	const registerBlockType = blocks.registerBlockType;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const PanelBody = components.PanelBody;
	const ComboboxControl = components.ComboboxControl;
	const ToggleControl = components.ToggleControl;
	const Placeholder = components.Placeholder;
	const useSelect = data.useSelect;

	registerBlockType( 'producerkit/preorder-form', {
		edit: function EditPreorderForm( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const blockProps = useBlockProps();

			const locations = useSelect( function ( select ) {
				return (
					select( 'core' ).getEntityRecords(
						'postType',
						'pkit_location',
						{
							per_page: 50,
							status: 'publish',
							_fields: 'id,title',
						}
					) || []
				);
			}, [] );

			const options = [
				{
					value: 0,
					label: __( '(no specific location)', 'producerkit' ),
				},
			].concat(
				locations.map( function ( l ) {
					return {
						value: l.id,
						label: l.title?.rendered || '(untitled)',
					};
				} )
			);

			const selected = options.filter( function ( o ) {
				return o.value === ( attributes.locationId || 0 );
			} )[ 0 ];

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Pre-Order Settings', 'producerkit' ),
							initialOpen: true,
						},
						el( ComboboxControl, {
							label: __( 'Pickup location', 'producerkit' ),
							value: attributes.locationId || 0,
							options,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
							help: __(
								'Shown in confirmations; its payment options appear after submitting.',
								'producerkit'
							),
						} ),
						el( ToggleControl, {
							label: __(
								'Hide sold-out products',
								'producerkit'
							),
							checked: !! attributes.onlyAvailable,
							onChange( val ) {
								setAttributes( { onlyAvailable: val } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( Placeholder, {
						icon: 'cart',
						label: __( 'Pre-Order Form', 'producerkit' ),
						// Built with sprintf rather than concatenation: a
						// translator needs the whole sentence, because word
						// order is not the same in every language and the
						// fragments alone cannot be reassembled correctly.
						instructions: sprintf(
							/* translators: 1: pickup location name, 2: a sentence about whether sold-out products are shown. */
							__(
								'Visitors pick products and a pickup date, then pay at the stand. Pickup location: %1$s. %2$s The full form renders on the front end and needs the Pre-Orders module enabled.',
								'producerkit'
							),
							selected
								? selected.label
								: __( '(none)', 'producerkit' ),
							attributes.onlyAvailable
								? __(
										'Sold-out products are hidden.',
										'producerkit'
								  )
								: __( 'All products are shown.', 'producerkit' )
						),
					} )
				)
			);
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data,
	window.wp.i18n
);
