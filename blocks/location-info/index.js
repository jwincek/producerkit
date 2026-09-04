/**
 * Location Info — editor block (no-build IIFE).
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
	const Fragment = element.Fragment;
	const useState = element.useState;
	const useEffect = element.useEffect;
	const registerBlockType = blocks.registerBlockType;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const PanelBody = components.PanelBody;
	const ComboboxControl = components.ComboboxControl;
	const ToggleControl = components.ToggleControl;
	const Placeholder = components.Placeholder;
	const Spinner = components.Spinner;
	const useSelect = data.useSelect;

	function getRestBase() {
		return (
			( window.pkitStandSettings || window.pkitSettings || {} )
				.restBase || '/wp-json/producerkit/v1'
		);
	}

	registerBlockType( 'producerkit/location-info', {
		edit: function EditLocationInfo( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const locationId = attributes.locationId;
			const showVenmo = attributes.showVenmo;
			const showStatus = attributes.showStatus;
			const showQR = attributes.showQR;

			// Location data from REST.
			const _stand = useState( null );
			const stand = _stand[ 0 ];
			const setStand = _stand[ 1 ];

			const _loading = useState( false );
			const loading = _loading[ 0 ];
			const setLoading = _loading[ 1 ];

			const _error = useState( '' );
			const error = _error[ 0 ];
			const setError = _error[ 1 ];

			// Fetch stand info when locationId changes.
			useEffect(
				function () {
					if ( ! locationId ) {
						setStand( null );
						return;
					}
					setLoading( true );
					setError( '' );
					fetch( getRestBase() + '/stand/' + locationId + '/info' )
						.then( function ( r ) {
							if ( ! r.ok ) {
								throw new Error(
									r.status + ' ' + r.statusText
								);
							}
							return r.json();
						} )
						.then( function ( payload ) {
							setStand( payload );
							setLoading( false );
						} )
						.catch( function () {
							setError(
								__(
									'Could not load location data.',
									'producerkit'
								)
							);
							setStand( null );
							setLoading( false );
						} );
				},
				[ locationId ]
			);

			// Location list for the picker.
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

			const options = locations.map( function ( l ) {
				return {
					value: l.id,
					label:
						l.title?.rendered || __( '(untitled)', 'producerkit' ),
				};
			} );

			const blockProps = useBlockProps( {
				className: 'pkit-location-info',
			} );

			// Payment methods come enriched from the stand info endpoint.
			const payMethods = ( stand && stand.payment_methods ) || [];

			// No location selected — placeholder with inline picker.
			if ( ! locationId ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el(
							Placeholder,
							{
								icon: 'store',
								label: __( 'Location Info', 'producerkit' ),
							},
							el( ComboboxControl, {
								label: __( 'Select a location', 'producerkit' ),
								value: '',
								options,
								onChange( val ) {
									setAttributes( {
										locationId: val ? Number( val ) : 0,
									} );
								},
							} )
						)
					)
				);
			}

			// Loading.
			if ( loading ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el(
							'div',
							{ className: 'pkit-location-info__loading' },
							el( Spinner ),
							' Loading location\u2026'
						)
					)
				);
			}

			// Error.
			if ( error || ! stand ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el( Placeholder, {
							icon: 'warning',
							label: __( 'Location Info', 'producerkit' ),
							instructions:
								error ||
								__(
									'Location data unavailable.',
									'producerkit'
								),
						} )
					)
				);
			}

			// Live preview — mirrors render.php structure.
			return el(
				Fragment,
				null,
				renderInspector(),
				el(
					'section',
					blockProps,

					// Header: title + status badge.
					el(
						'div',
						{ className: 'pkit-location-info__header' },
						el(
							'h3',
							{ className: 'pkit-location-info__title' },
							stand.name
						),
						showStatus
							? el(
									'span',
									{
										className:
											'pkit-location-info__status pkit-location-info__status--' +
											( stand.is_open
												? 'open'
												: 'closed' ),
									},
									stand.is_open
										? __( 'Open Now', 'producerkit' )
										: __( 'Closed', 'producerkit' )
							  )
							: null
					),

					// Location type.
					stand.location_type
						? el(
								'span',
								{ className: 'pkit-location-info__type' },
								stand.location_type.charAt( 0 ).toUpperCase() +
									stand.location_type.slice( 1 )
						  )
						: null,

					// Address.
					stand.address
						? el(
								'p',
								{ className: 'pkit-location-info__address' },
								stand.address
						  )
						: null,

					// Hours.
					stand.hours
						? el(
								'p',
								{ className: 'pkit-location-info__hours' },
								stand.hours
						  )
						: null,

					// Payment options.
					showVenmo && payMethods.length
						? el(
								'div',
								{ className: 'pkit-location-info__payments' },
								el(
									'span',
									{
										className:
											'pkit-location-info__payments-label',
									},
									__( 'Payment options:', 'producerkit' )
								),
								showQR &&
									payMethods.some( function ( m ) {
										return m.is_link;
									} )
									? el(
											'div',
											{
												className:
													'pkit-location-info__qr pkit-location-info__qr--editor',
											},
											el(
												'div',
												{
													className:
														'pkit-location-info__qr-code',
													style: {
														width: '96px',
														height: '96px',
														display: 'flex',
														alignItems: 'center',
														justifyContent:
															'center',
														border: '1px dashed #9ca3af',
														borderRadius: '4px',
														fontSize: '11px',
														color: '#6b7280',
														textAlign: 'center',
													},
												},
												__(
													'QR renders on the front end',
													'producerkit'
												)
											)
									  )
									: null,
								el(
									'ul',
									{
										className:
											'pkit-location-info__payments-list',
									},
									payMethods.map( function ( m, i ) {
										const text =
											[
												'venmo',
												'cashapp',
												'paypal',
											].indexOf( m.type ) !== -1
												? m.label +
												  ' (@' +
												  m.value +
												  ')'
												: m.label;
										return el(
											'li',
											{
												key: i,
												className:
													'pkit-location-info__payment pkit-location-info__payment--' +
													m.type,
											},
											m.is_link
												? el(
														'span',
														{
															className:
																'pkit-location-info__payment-link',
														},
														text
												  )
												: el(
														'span',
														{
															className:
																'pkit-location-info__payment-badge',
														},
														text
												  )
										);
									} )
								)
						  )
						: null
				)
			);

			function renderInspector() {
				return el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Location Settings', 'producerkit' ),
							initialOpen: true,
						},
						el( ComboboxControl, {
							label: __( 'Select Location', 'producerkit' ),
							value: locationId || '',
							options,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
						} ),
						el( ToggleControl, {
							label: __(
								'Show open/closed status',
								'producerkit'
							),
							checked: showStatus,
							onChange( val ) {
								setAttributes( { showStatus: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show payment options', 'producerkit' ),
							checked: showVenmo,
							onChange( val ) {
								setAttributes( { showVenmo: val } );
							},
							help: __(
								'Links and accepted-payment badges from the location\u2019s Payment Options panel.',
								'producerkit'
							),
						} ),
						el( ToggleControl, {
							label: __( 'Show payment QR code', 'producerkit' ),
							checked: !! showQR,
							onChange( val ) {
								setAttributes( { showQR: val } );
							},
							help: __(
								'A scannable code for the first payment link \u2014 handy for signage at the stand.',
								'producerkit'
							),
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
	window.wp.data,
	window.wp.i18n
);
