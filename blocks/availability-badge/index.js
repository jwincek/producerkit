/**
 * Availability Badge — editor block (no-build IIFE).
 * @param blocks
 * @param element
 * @param blockEditor
 * @param components
 * @param data
 */
( function ( blocks, element, blockEditor, components, data ) {
	'use strict';

	const el = element.createElement;
	const { registerBlockType } = blocks;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, ComboboxControl, Placeholder } = components;
	const { useSelect } = data;

	registerBlockType( 'lfuf/availability-badge', {
		edit: function EditAvailabilityBadge( props ) {
			const { attributes, setAttributes } = props;
			const { productId, locationId } = attributes;
			const blockProps = useBlockProps();

			const products = useSelect( function ( select ) {
				return (
					select( 'core' ).getEntityRecords(
						'postType',
						'lfuf_product',
						{
							per_page: 100,
							status: 'publish',
							_fields: 'id,title',
						}
					) || []
				);
			}, [] );

			const locations = useSelect( function ( select ) {
				return (
					select( 'core' ).getEntityRecords(
						'postType',
						'lfuf_location',
						{
							per_page: 50,
							status: 'publish',
							_fields: 'id,title',
						}
					) || []
				);
			}, [] );

			const productOptions = products.map( function ( p ) {
				return {
					value: p.id,
					label: p.title?.rendered || '(untitled)',
				};
			} );

			const locationOptions = [
				{ value: 0, label: '— Any location —' },
			].concat(
				locations.map( function ( l ) {
					return {
						value: l.id,
						label: l.title?.rendered || '(untitled)',
					};
				} )
			);

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Badge Settings', initialOpen: true },
						el( ComboboxControl, {
							label: 'Product',
							value: productId || '',
							options: productOptions,
							onChange( val ) {
								setAttributes( {
									productId: val ? Number( val ) : 0,
								} );
							},
						} ),
						el( ComboboxControl, {
							label: 'Location (optional)',
							value: locationId || '',
							options: locationOptions,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					! productId
						? el( Placeholder, {
								icon: 'visibility',
								label: 'Availability Badge',
								instructions:
									'Select a product in the sidebar.',
						  } )
						: el(
								'span',
								{
									className:
										'lfuf-availability-badge lfuf-availability-badge--available',
								},
								'Available (preview)'
						  )
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
