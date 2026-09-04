/**
 * Availability Badge — editor block (no-build IIFE).
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
	const { registerBlockType } = blocks;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, ComboboxControl, Placeholder } = components;
	const { useSelect } = data;

	registerBlockType( 'producerkit/availability-badge', {
		edit: function EditAvailabilityBadge( props ) {
			const { attributes, setAttributes } = props;
			const { productId, locationId } = attributes;
			const blockProps = useBlockProps();

			const products = useSelect( function ( select ) {
				return (
					select( 'core' ).getEntityRecords(
						'postType',
						'pkit_product',
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
						'pkit_location',
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
					label:
						p.title?.rendered || __( '(untitled)', 'producerkit' ),
				};
			} );

			const locationOptions = [
				{ value: 0, label: __( '— Any location —', 'producerkit' ) },
			].concat(
				locations.map( function ( l ) {
					return {
						value: l.id,
						label:
							l.title?.rendered ||
							__( '(untitled)', 'producerkit' ),
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
						{
							title: __( 'Badge Settings', 'producerkit' ),
							initialOpen: true,
						},
						el( ComboboxControl, {
							label: __( 'Product', 'producerkit' ),
							value: productId || '',
							options: productOptions,
							onChange( val ) {
								setAttributes( {
									productId: val ? Number( val ) : 0,
								} );
							},
						} ),
						el( ComboboxControl, {
							label: __( 'Location (optional)', 'producerkit' ),
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
								label: __(
									'Availability Badge',
									'producerkit'
								),
								instructions: __(
									'Select a product in the sidebar.',
									'producerkit'
								),
						  } )
						: el(
								'span',
								{
									className:
										'pkit-availability-badge pkit-availability-badge--available',
								},
								__( 'Available (preview)', 'producerkit' )
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
	window.wp.data,
	window.wp.i18n
);
