/**
 * Pre-Order Form — editor block (no-build IIFE).
 *
 * The Interactivity view module only runs on the front end, so the
 * editor shows a static summary placeholder.
 */
( function ( blocks, element, blockEditor, components, data ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var ComboboxControl = components.ComboboxControl;
	var ToggleControl = components.ToggleControl;
	var Placeholder = components.Placeholder;
	var useSelect = data.useSelect;

	registerBlockType( 'lfuf/preorder-form', {
		edit: function EditPreorderForm( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var locations = useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'lfuf_location', {
					per_page: 50,
					status: 'publish',
					_fields: 'id,title',
				} ) || [];
			}, [] );

			var options = [ { value: 0, label: '(no specific location)' } ].concat(
				locations.map( function ( l ) {
					return { value: l.id, label: l.title?.rendered || '(untitled)' };
				} )
			);

			var selected = options.filter( function ( o ) {
				return o.value === ( attributes.locationId || 0 );
			} )[ 0 ];

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Pre-Order Settings', initialOpen: true },
						el( ComboboxControl, {
							label: 'Pickup location',
							value: attributes.locationId || 0,
							options: options,
							onChange: function ( val ) {
								setAttributes( { locationId: val ? Number( val ) : 0 } );
							},
							help: 'Shown in confirmations; its payment options appear after submitting.',
						} ),
						el( ToggleControl, {
							label: 'Hide sold-out products',
							checked: !! attributes.onlyAvailable,
							onChange: function ( val ) { setAttributes( { onlyAvailable: val } ); },
						} )
					)
				),
				el( 'div', blockProps,
					el( Placeholder, {
						icon: 'cart',
						label: 'Pre-Order Form',
						instructions: 'Visitors pick products and a pickup date, then pay at the stand. '
							+ 'Pickup location: ' + ( selected ? selected.label : '(none)' ) + '. '
							+ ( attributes.onlyAvailable ? 'Sold-out products are hidden.' : 'All products are shown.' )
							+ ' The full form renders on the front end and needs the Pre-Orders module enabled.',
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
