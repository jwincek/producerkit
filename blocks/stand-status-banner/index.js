/**
 * Stand Status Banner — editor block (no-build IIFE).
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
	const useState = element.useState;
	const useEffect = element.useEffect;
	const registerBlockType = blocks.registerBlockType;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const PanelBody = components.PanelBody;
	const ToggleControl = components.ToggleControl;
	const SelectControl = components.SelectControl;
	const ComboboxControl = components.ComboboxControl;
	const Placeholder = components.Placeholder;
	const Spinner = components.Spinner;
	const useSelect = data.useSelect;

	function getRestBase() {
		return (
			( window.pkitStandSettings || window.pkitSettings || {} )
				.restBase || '/wp-json/producerkit/v1'
		);
	}

	/**
	 * Format an ISO 8601 timestamp as relative time, matching view.js logic.
	 * @param isoString
	 */
	function formatTimeAgo( isoString ) {
		if ( ! isoString ) {
			return '';
		}
		const then = new Date( isoString );
		const now = new Date();
		const diff = Math.floor( ( now - then ) / 1000 );

		if ( diff < 60 ) {
			return 'just now';
		}
		if ( diff < 3600 ) {
			const mins = Math.floor( diff / 60 );
			return mins + ' minute' + ( mins === 1 ? '' : 's' ) + ' ago';
		}
		if ( diff < 86400 ) {
			const hrs = Math.floor( diff / 3600 );
			return hrs + ' hour' + ( hrs === 1 ? '' : 's' ) + ' ago';
		}
		const days = Math.floor( diff / 86400 );
		return days + ' day' + ( days === 1 ? '' : 's' ) + ' ago';
	}

	/**
	 * Format a YYYY-MM-DD date string for display.
	 * short = "Apr 15", long = "April 15".
	 * @param dateStr
	 * @param style
	 */
	function formatDate( dateStr, style ) {
		if ( ! dateStr ) {
			return '';
		}
		const d = new Date( dateStr + 'T00:00:00' );
		if ( isNaN( d ) ) {
			return dateStr;
		}
		const month = style === 'short' ? 'short' : 'long';
		return d.toLocaleDateString( undefined, {
			month,
			day: 'numeric',
		} );
	}

	registerBlockType( 'producerkit/stand-status-banner', {
		edit: function EditBanner( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const locationId = attributes.locationId;
			const showAddress = attributes.showAddress;
			const showHours = attributes.showHours;
			const showVenmo = attributes.showVenmo;
			const showSeasonDates = attributes.showSeasonDates;
			const layout = attributes.layout;
			const pollingEnabled = attributes.pollingEnabled;

			// Stand data from REST.
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
							setError( 'Could not load stand data.' );
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
					label: l.title?.rendered || '(untitled)',
				};
			} );

			// Derive display values from stand data.
			const statusSlug = stand
				? stand.is_open
					? 'open'
					: 'closed'
				: 'closed';
			const statusLabel = stand
				? stand.is_open
					? 'Open Now'
					: 'Closed'
				: '';
			const timeAgo = stand ? formatTimeAgo( stand.last_toggled ) : '';
			const venmoUrl =
				stand && stand.venmo_handle
					? 'https://venmo.com/' +
					  stand.venmo_handle.replace( /^@/, '' )
					: '';
			const hasSeasonDates =
				stand && stand.season_start && stand.season_end;

			const blockProps = useBlockProps( {
				className:
					'pkit-stand-banner pkit-stand-banner--' +
					layout +
					( stand ? ' pkit-stand-banner--' + statusSlug : '' ),
			} );

			// --- Render ---

			// No location selected: show placeholder with inline picker.
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
							{ icon: 'store', label: 'Stand Status Banner' },
							el( ComboboxControl, {
								label: 'Select a location',
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

			// Loading state.
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
							{ className: 'pkit-stand-banner__loading' },
							el( Spinner ),
							' Loading stand status…'
						)
					)
				);
			}

			// Error state.
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
							label: 'Stand Status Banner',
							instructions:
								error ||
								'Stand data unavailable. Check that this location is still published.',
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

					// Main status area.
					el(
						'div',
						{ className: 'pkit-stand-banner__main' },
						el(
							'div',
							{ className: 'pkit-stand-banner__status-row' },
							el( 'span', {
								className:
									'pkit-stand-banner__indicator pkit-stand-banner__indicator--' +
									statusSlug,
							} ),
							el(
								'span',
								{
									className:
										'pkit-stand-banner__status-label',
								},
								statusLabel
							)
						),
						el(
							'h2',
							{ className: 'pkit-stand-banner__name' },
							stand.name
						),
						stand.status_message
							? el(
									'p',
									{ className: 'pkit-stand-banner__message' },
									stand.status_message
							  )
							: null,
						! stand.is_open && stand.next_open
							? el(
									'p',
									{
										className:
											'pkit-stand-banner__next-open',
									},
									'Next open: ' + stand.next_open
							  )
							: null,
						timeAgo
							? el(
									'span',
									{ className: 'pkit-stand-banner__updated' },
									'Updated ' + timeAgo
							  )
							: null
					),

					// Details area.
					el(
						'div',
						{ className: 'pkit-stand-banner__details' },
						// Off-season notice.
						! stand.in_season && showSeasonDates && hasSeasonDates
							? el(
									'p',
									{
										className:
											'pkit-stand-banner__off-season',
									},
									'Our season runs ' +
										formatDate(
											stand.season_start,
											'long'
										) +
										' – ' +
										formatDate( stand.season_end, 'long' ) +
										'. See you then!'
							  )
							: null,
						// In-season note.
						stand.in_season && showSeasonDates && hasSeasonDates
							? el(
									'p',
									{
										className:
											'pkit-stand-banner__season-note',
									},
									'Season: ' +
										formatDate(
											stand.season_start,
											'short'
										) +
										' – ' +
										formatDate( stand.season_end, 'short' )
							  )
							: null,
						// Address.
						showAddress && stand.address
							? el(
									'p',
									{ className: 'pkit-stand-banner__address' },
									el(
										'span',
										{
											className:
												'pkit-stand-banner__icon',
											'aria-hidden': 'true',
										},
										'\uD83D\uDCCD'
									),
									stand.address
							  )
							: null,
						// Hours.
						showHours && stand.hours
							? el(
									'p',
									{ className: 'pkit-stand-banner__hours' },
									el(
										'span',
										{
											className:
												'pkit-stand-banner__icon',
											'aria-hidden': 'true',
										},
										'\uD83D\uDD50'
									),
									stand.hours
							  )
							: null,
						// Venmo.
						showVenmo && venmoUrl
							? el(
									'span',
									{
										className:
											'pkit-stand-banner__venmo-link',
									},
									el(
										'span',
										{
											className:
												'pkit-stand-banner__icon',
											'aria-hidden': 'true',
										},
										'\uD83D\uDCB8'
									),
									'Pay with Venmo (@' +
										stand.venmo_handle.replace( /^@/, '' ) +
										')'
							  )
							: null
					)
				)
			);

			/**
			 * Sidebar inspector — extracted to avoid repeating in every branch.
			 */
			function renderInspector() {
				return el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Stand Selection', initialOpen: true },
						el( ComboboxControl, {
							label: 'Location',
							value: locationId || '',
							options,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
						} )
					),
					el(
						PanelBody,
						{ title: 'Display Options', initialOpen: true },
						el( SelectControl, {
							label: 'Layout',
							value: layout,
							options: [
								{ label: 'Full Banner', value: 'banner' },
								{ label: 'Compact Strip', value: 'compact' },
								{ label: 'Card', value: 'card' },
							],
							onChange( val ) {
								setAttributes( { layout: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show address',
							checked: showAddress,
							onChange( val ) {
								setAttributes( { showAddress: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show hours',
							checked: showHours,
							onChange( val ) {
								setAttributes( { showHours: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show Venmo link',
							checked: showVenmo,
							onChange( val ) {
								setAttributes( { showVenmo: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show season dates',
							checked: showSeasonDates,
							onChange( val ) {
								setAttributes( { showSeasonDates: val } );
							},
						} )
					),
					el(
						PanelBody,
						{ title: 'Live Updates', initialOpen: false },
						el( ToggleControl, {
							label: 'Auto-refresh status (polls every 60s)',
							checked: pollingEnabled,
							onChange( val ) {
								setAttributes( { pollingEnabled: val } );
							},
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
