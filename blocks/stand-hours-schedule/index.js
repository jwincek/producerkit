/**
 * Stand Hours Schedule — editor block (no-build IIFE).
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
	const ComboboxControl = components.ComboboxControl;
	const ToggleControl = components.ToggleControl;
	const Placeholder = components.Placeholder;
	const Spinner = components.Spinner;
	const useSelect = data.useSelect;

	const DAY_NAMES = [
		'Sunday',
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
	];

	function getRestBase() {
		return (
			( window.lfufStandSettings || window.lfufSettings || {} )
				.restBase || '/wp-json/lfuf/v1'
		);
	}

	/**
	 * Format a time string like "14:00" to "2:00 PM".
	 * @param timeStr
	 */
	function formatTime( timeStr ) {
		if ( ! timeStr ) {
			return '';
		}
		const parts = timeStr.split( ':' );
		let h = parseInt( parts[ 0 ], 10 );
		const m = parts[ 1 ] || '00';
		const ampm = h >= 12 ? 'PM' : 'AM';
		h = h % 12 || 12;
		return h + ':' + m + ' ' + ampm;
	}

	/**
	 * Parse schedule array into a map of day index → time strings.
	 * @param schedule
	 */
	function buildScheduleByDay( schedule ) {
		const byDay = {};
		if ( ! Array.isArray( schedule ) ) {
			return byDay;
		}
		schedule.forEach( function ( entry ) {
			const day = parseInt( entry.day, 10 );
			if ( day >= 0 && day <= 6 ) {
				if ( ! byDay[ day ] ) {
					byDay[ day ] = [];
				}
				const open = formatTime( entry.open || '00:00' );
				const close = formatTime( entry.close || '23:59' );
				byDay[ day ].push( open + ' \u2013 ' + close );
			}
		} );
		return byDay;
	}

	registerBlockType( 'lfuf/stand-hours-schedule', {
		edit: function EditSchedule( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const locationId = attributes.locationId;
			const highlightToday = attributes.highlightToday;

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
							setError( 'Could not load schedule data.' );
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
						'lfuf_location',
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

			const blockProps = useBlockProps( {
				className: 'lfuf-stand-schedule',
			} );
			const today = new Date().getDay();

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
							{ icon: 'clock', label: 'Stand Hours Schedule' },
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
							{ className: 'lfuf-stand-schedule__loading' },
							el( Spinner ),
							' Loading schedule\u2026'
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
							label: 'Stand Hours Schedule',
							instructions: error || 'Schedule data unavailable.',
						} )
					)
				);
			}

			// Parse schedule.
			const byDay = buildScheduleByDay( stand.schedule );
			const hasSchedule = Object.keys( byDay ).length > 0;

			// No schedule — show fallback or empty message.
			if ( ! hasSchedule ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'section',
						blockProps,
						stand.hours
							? el(
									'p',
									{
										className:
											'lfuf-stand-schedule__fallback',
									},
									'\uD83D\uDD50 ' + stand.hours
							  )
							: el(
									'p',
									{ className: 'lfuf-stand-schedule__empty' },
									'No schedule set yet.'
							  )
					)
				);
			}

			// Live preview — table structure matching render.php.
			return el(
				Fragment,
				null,
				renderInspector(),
				el(
					'section',
					blockProps,
					el(
						'table',
						{
							className: 'lfuf-stand-schedule__table',
							role: 'table',
						},
						el(
							'tbody',
							null,
							DAY_NAMES.map( function ( dayName, d ) {
								const isToday = highlightToday && d === today;
								const hasHours = !! byDay[ d ];
								let classes = 'lfuf-stand-schedule__day';
								if ( isToday ) {
									classes +=
										' lfuf-stand-schedule__day--today';
								}
								if ( ! hasHours ) {
									classes +=
										' lfuf-stand-schedule__day--closed';
								}

								return el(
									'tr',
									{
										key: d,
										className: classes,
									},
									el(
										'th',
										{
											scope: 'row',
											className:
												'lfuf-stand-schedule__day-label',
										},
										dayName,
										isToday
											? el(
													'span',
													{
														className:
															'lfuf-stand-schedule__today-badge',
													},
													'Today'
											  )
											: null
									),
									el(
										'td',
										{
											className:
												'lfuf-stand-schedule__day-hours',
										},
										hasHours
											? byDay[ d ].join( ', ' )
											: 'Closed'
									)
								);
							} )
						)
					)
				)
			);

			function renderInspector() {
				return el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Schedule Settings', initialOpen: true },
						el( ComboboxControl, {
							label: 'Location',
							value: locationId || '',
							options,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
						} ),
						el( ToggleControl, {
							label: 'Highlight today',
							checked: highlightToday,
							onChange( val ) {
								setAttributes( { highlightToday: val } );
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
