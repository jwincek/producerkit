/**
 * Stand Status Banner — editor block (no-build IIFE).
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
							setError(
								__(
									'Could not load stand data.',
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

			// Derive display values from stand data.
			const statusSlug = stand
				? stand.is_open
					? 'open'
					: 'closed'
				: 'closed';
			const statusLabel = stand
				? stand.is_open
					? __( 'Open Now', 'producerkit' )
					: __( 'Closed', 'producerkit' )
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
							{
								icon: 'store',
								label: __(
									'Stand Status Banner',
									'producerkit'
								),
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
							label: __( 'Stand Status Banner', 'producerkit' ),
							instructions:
								error ||
								__(
									'Stand data unavailable. Check that this location is still published.',
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
									sprintf(
										/* translators: %s: the next date the stand opens. */
										__( 'Next open: %s', 'producerkit' ),
										stand.next_open
									)
							  )
							: null,
						timeAgo
							? el(
									'span',
									{ className: 'pkit-stand-banner__updated' },
									sprintf(
										/* translators: %s: a relative time, e.g. "4 hours ago". */
										__( 'Updated %s', 'producerkit' ),
										timeAgo
									)
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
									sprintf(
										/* translators: 1: season start date, 2: season end date. */
										__(
											'Our season runs %1$s – %2$s. See you then!',
											'producerkit'
										),
										formatDate(
											stand.season_start,
											'long'
										),
										formatDate( stand.season_end, 'long' )
									)
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
									sprintf(
										/* translators: 1: season start date, 2: season end date. */
										__(
											'Season: %1$s – %2$s',
											'producerkit'
										),
										formatDate(
											stand.season_start,
											'short'
										),
										formatDate( stand.season_end, 'short' )
									)
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
									sprintf(
										/* translators: %s: a Venmo handle without the @. */
										__(
											'Pay with Venmo (@%s)',
											'producerkit'
										),
										stand.venmo_handle.replace( /^@/, '' )
									)
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
						{
							title: __( 'Stand Selection', 'producerkit' ),
							initialOpen: true,
						},
						el( ComboboxControl, {
							label: __( 'Location', 'producerkit' ),
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
						{
							title: __( 'Display Options', 'producerkit' ),
							initialOpen: true,
						},
						el( SelectControl, {
							label: __( 'Layout', 'producerkit' ),
							value: layout,
							options: [
								{
									label: __( 'Full Banner', 'producerkit' ),
									value: 'banner',
								},
								{
									label: __( 'Compact Strip', 'producerkit' ),
									value: 'compact',
								},
								{
									label: __( 'Card', 'producerkit' ),
									value: 'card',
								},
							],
							onChange( val ) {
								setAttributes( { layout: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show address', 'producerkit' ),
							checked: showAddress,
							onChange( val ) {
								setAttributes( { showAddress: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show hours', 'producerkit' ),
							checked: showHours,
							onChange( val ) {
								setAttributes( { showHours: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show Venmo link', 'producerkit' ),
							checked: showVenmo,
							onChange( val ) {
								setAttributes( { showVenmo: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show season dates', 'producerkit' ),
							checked: showSeasonDates,
							onChange( val ) {
								setAttributes( { showSeasonDates: val } );
							},
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Live Updates', 'producerkit' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __(
								'Auto-refresh status (polls every 60s)',
								'producerkit'
							),
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
	window.wp.data,
	window.wp.i18n
);
