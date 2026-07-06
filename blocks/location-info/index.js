/**
 * Location Info — editor block (no-build IIFE).
 */
( function ( blocks, element, blockEditor, components, data ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var useState = element.useState;
	var useEffect = element.useEffect;
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var ComboboxControl = components.ComboboxControl;
	var ToggleControl = components.ToggleControl;
	var Placeholder = components.Placeholder;
	var Spinner = components.Spinner;
	var useSelect = data.useSelect;

	function getRestBase() {
		return ( window.lfufStandSettings || window.lfufSettings || {} ).restBase || '/wp-json/lfuf/v1';
	}

	registerBlockType( 'lfuf/location-info', {
		edit: function EditLocationInfo( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var locationId = attributes.locationId;
			var showVenmo = attributes.showVenmo;
			var showStatus = attributes.showStatus;

			// Location data from REST.
			var _stand = useState( null );
			var stand = _stand[0];
			var setStand = _stand[1];

			var _loading = useState( false );
			var loading = _loading[0];
			var setLoading = _loading[1];

			var _error = useState( '' );
			var error = _error[0];
			var setError = _error[1];

			// Fetch stand info when locationId changes.
			useEffect( function () {
				if ( ! locationId ) {
					setStand( null );
					return;
				}
				setLoading( true );
				setError( '' );
				fetch( getRestBase() + '/stand/' + locationId + '/info' )
					.then( function ( r ) {
						if ( ! r.ok ) throw new Error( r.status + ' ' + r.statusText );
						return r.json();
					} )
					.then( function ( data ) {
						setStand( data );
						setLoading( false );
					} )
					.catch( function () {
						setError( 'Could not load location data.' );
						setStand( null );
						setLoading( false );
					} );
			}, [ locationId ] );

			// Location list for the picker.
			var locations = useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'lfuf_location', {
					per_page: 50,
					status: 'publish',
					_fields: 'id,title',
				} ) || [];
			}, [] );

			var options = locations.map( function ( l ) {
				return { value: l.id, label: l.title?.rendered || '(untitled)' };
			} );

			var blockProps = useBlockProps( { className: 'lfuf-location-info' } );

			// Payment methods come enriched from the stand info endpoint.
			var payMethods = ( stand && stand.payment_methods ) || [];

			// No location selected — placeholder with inline picker.
			if ( ! locationId ) {
				return el( Fragment, null,
					renderInspector(),
					el( 'div', blockProps,
						el( Placeholder, { icon: 'store', label: 'Location Info' },
							el( ComboboxControl, {
								label: 'Select a location',
								value: '',
								options: options,
								onChange: function ( val ) {
									setAttributes( { locationId: val ? Number( val ) : 0 } );
								},
							} )
						)
					)
				);
			}

			// Loading.
			if ( loading ) {
				return el( Fragment, null,
					renderInspector(),
					el( 'div', blockProps,
						el( 'div', { className: 'lfuf-location-info__loading' },
							el( Spinner ), ' Loading location\u2026'
						)
					)
				);
			}

			// Error.
			if ( error || ! stand ) {
				return el( Fragment, null,
					renderInspector(),
					el( 'div', blockProps,
						el( Placeholder, {
							icon: 'warning',
							label: 'Location Info',
							instructions: error || 'Location data unavailable.',
						} )
					)
				);
			}

			// Live preview — mirrors render.php structure.
			return el( Fragment, null,
				renderInspector(),
				el( 'section', blockProps,

					// Header: title + status badge.
					el( 'div', { className: 'lfuf-location-info__header' },
						el( 'h3', { className: 'lfuf-location-info__title' }, stand.name ),
						showStatus
							? el( 'span', {
								className: 'lfuf-location-info__status lfuf-location-info__status--' +
									( stand.is_open ? 'open' : 'closed' ),
							}, stand.is_open ? 'Open Now' : 'Closed' )
							: null
					),

					// Location type.
					stand.location_type
						? el( 'span', { className: 'lfuf-location-info__type' },
							stand.location_type.charAt( 0 ).toUpperCase() + stand.location_type.slice( 1 )
						)
						: null,

					// Address.
					stand.address
						? el( 'p', { className: 'lfuf-location-info__address' }, stand.address )
						: null,

					// Hours.
					stand.hours
						? el( 'p', { className: 'lfuf-location-info__hours' }, stand.hours )
						: null,

					// Payment options.
					( showVenmo && payMethods.length )
						? el( 'div', { className: 'lfuf-location-info__payments' },
							el( 'span', { className: 'lfuf-location-info__payments-label' }, 'Payment options:' ),
							el( 'ul', { className: 'lfuf-location-info__payments-list' },
								payMethods.map( function ( m, i ) {
									var text = ( [ 'venmo', 'cashapp', 'paypal' ].indexOf( m.type ) !== -1 )
										? m.label + ' (@' + m.value + ')'
										: m.label;
									return el( 'li', {
										key: i,
										className: 'lfuf-location-info__payment lfuf-location-info__payment--' + m.type,
									},
										m.is_link
											? el( 'span', { className: 'lfuf-location-info__payment-link' }, text )
											: el( 'span', { className: 'lfuf-location-info__payment-badge' }, text )
									);
								} )
							)
						)
						: null
				)
			);

			function renderInspector() {
				return el( InspectorControls, null,
					el( PanelBody, { title: 'Location Settings', initialOpen: true },
						el( ComboboxControl, {
							label: 'Select Location',
							value: locationId || '',
							options: options,
							onChange: function ( val ) {
								setAttributes( { locationId: val ? Number( val ) : 0 } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show open/closed status',
							checked: showStatus,
							onChange: function ( val ) { setAttributes( { showStatus: val } ); },
						} ),
						el( ToggleControl, {
							label: 'Show payment options',
							checked: showVenmo,
							onChange: function ( val ) { setAttributes( { showVenmo: val } ); },
							help: 'Links and accepted-payment badges from the location\u2019s Payment Options panel.',
						} )
					)
				);
			}
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data
);
