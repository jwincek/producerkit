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
 */
( function ( blocks, element, blockEditor, components, data ) {
	'use strict';

	const el = element.createElement;
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
				{ value: 0, label: '(no specific location)' },
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
						{ title: 'Pre-Order Settings', initialOpen: true },
						el( ComboboxControl, {
							label: 'Pickup location',
							value: attributes.locationId || 0,
							options,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
							help: 'Shown in confirmations; its payment options appear after submitting.',
						} ),
						el( ToggleControl, {
							label: 'Hide sold-out products',
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
						label: 'Pre-Order Form',
						instructions:
							'Visitors pick products and a pickup date, then pay at the stand. ' +
							'Pickup location: ' +
							( selected ? selected.label : '(none)' ) +
							'. ' +
							( attributes.onlyAvailable
								? 'Sold-out products are hidden.'
								: 'All products are shown.' ) +
							' The full form renders on the front end and needs the Pre-Orders module enabled.',
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
	window.wp.data
);
