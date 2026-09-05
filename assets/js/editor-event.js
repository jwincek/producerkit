/**
 * Event Editor Sidebar — custom panels for pkit_event meta.
 *
 * Panels:
 *   1. Event Details   — date/time pickers, location, donation link
 *   2. RSVP Settings   — enable, cap, label, close, headcount display
 *   3. Event Info      — cost note, what to bring, cancelled toggle
 */
( function () {
	'use strict';

	const el = wp.element.createElement;
	const __ = wp.i18n.__;
	const registerPlugin = wp.plugins.registerPlugin;
	const PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
	const useEntityProp = wp.coreData.useEntityProp;
	const useSelect = wp.data.useSelect;
	const TextControl = wp.components.TextControl;
	const SelectControl = wp.components.SelectControl;
	const ToggleControl = wp.components.ToggleControl;
	const RangeControl = wp.components.RangeControl;
	const Notice = wp.components.Notice;
	const Spinner = wp.components.Spinner;
	const useState = wp.element.useState;
	const useEffect = wp.element.useEffect;
	const apiFetch = wp.apiFetch;
	const Button = wp.components.Button;

	/* ─────────────────────────────────────────────
	 * Helpers
	 * ───────────────────────────────────────────── */

	/**
	 * Split ISO datetime "2026-06-06T18:00:00" into { date, time }.
	 * @param iso
	 */
	function splitDatetime( iso ) {
		if ( ! iso ) {
			return { date: '', time: '' };
		}
		const parts = iso.split( 'T' );
		return {
			date: parts[ 0 ] || '',
			time: ( parts[ 1 ] || '' ).substring( 0, 5 ), // HH:MM
		};
	}

	/**
	 * Combine date + time into ISO string.
	 * @param date
	 * @param time
	 */
	function joinDatetime( date, time ) {
		if ( ! date ) {
			return '';
		}
		return date + 'T' + ( time || '00:00' ) + ':00';
	}

	/* ─────────────────────────────────────────────
	 * Panel 1: Event Details
	 * ───────────────────────────────────────────── */

	function EventDetailsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'pkit_event' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'pkit_event', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		function updateMeta( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		}

		// Parse start/end into date + time.
		const start = splitDatetime( meta._pkit_start_datetime );
		const end = splitDatetime( meta._pkit_end_datetime );

		// Fetch locations for the selector.
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

		const locationOptions = [
			{ label: __( '— Select Location —', 'producerkit' ), value: 0 },
		].concat(
			locations.map( function ( loc ) {
				return {
					label:
						loc.title?.rendered ||
						__( '(untitled)', 'producerkit' ),
					value: loc.id,
				};
			} )
		);

		// Validation.
		const noStartDate = ! meta._pkit_start_datetime;

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-event-details',
				title: __( 'Event Details', 'producerkit' ),
				initialOpen: true,
				icon: 'calendar-alt',
			},

			noStartDate
				? el(
						Notice,
						{
							status: 'warning',
							isDismissible: false,
							style: { marginBottom: '12px' },
						},
						__(
							'Set a start date before publishing.',
							'producerkit'
						)
				  )
				: null,

			// Start date/time.
			el(
				'p',
				{ style: { fontWeight: 600, marginBottom: '4px' } },
				__( 'Start', 'producerkit' )
			),
			el(
				'div',
				{
					style: {
						display: 'flex',
						gap: '8px',
						marginBottom: '12px',
					},
				},
				el( TextControl, {
					label: __( 'Date', 'producerkit' ),
					type: 'date',
					value: start.date,
					onChange( val ) {
						updateMeta(
							'_pkit_start_datetime',
							joinDatetime( val, start.time )
						);
					},
					style: { flex: 1 },
				} ),
				el( TextControl, {
					label: __( 'Time', 'producerkit' ),
					type: 'time',
					value: start.time,
					onChange( val ) {
						updateMeta(
							'_pkit_start_datetime',
							joinDatetime( start.date, val )
						);
					},
					style: { flex: 1 },
				} )
			),

			// End date/time.
			el(
				'p',
				{ style: { fontWeight: 600, marginBottom: '4px' } },
				__( 'End', 'producerkit' )
			),
			el(
				'div',
				{
					style: {
						display: 'flex',
						gap: '8px',
						marginBottom: '12px',
					},
				},
				el( TextControl, {
					label: __( 'Date', 'producerkit' ),
					type: 'date',
					value: end.date || start.date, // Default to same day.
					onChange( val ) {
						updateMeta(
							'_pkit_end_datetime',
							joinDatetime( val, end.time )
						);
					},
					style: { flex: 1 },
				} ),
				el( TextControl, {
					label: __( 'Time', 'producerkit' ),
					type: 'time',
					value: end.time,
					onChange( val ) {
						updateMeta(
							'_pkit_end_datetime',
							joinDatetime( end.date || start.date, val )
						);
					},
					style: { flex: 1 },
				} )
			),

			// Location.
			el( SelectControl, {
				label: __( 'Location', 'producerkit' ),
				value: meta._pkit_event_location_id || 0,
				options: locationOptions,
				onChange( val ) {
					updateMeta(
						'_pkit_event_location_id',
						parseInt( val, 10 )
					);
				},
				help: __( 'Where is this event happening?', 'producerkit' ),
			} ),

			// Donation link.
			el( TextControl, {
				label: __( 'Donation / Payment Link', 'producerkit' ),
				value: meta._pkit_donation_link || '',
				onChange( val ) {
					updateMeta( '_pkit_donation_link', val );
				},
				placeholder: __(
					'https://venmo.com/examplefarm',
					'producerkit'
				),
				help: __( 'Venmo link or other payment URL.', 'producerkit' ),
				type: 'url',
			} )
		);
	}

	/* ─────────────────────────────────────────────
	 * Panel 2: RSVP Settings
	 * ───────────────────────────────────────────── */

	function EventRsvpPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'pkit_event' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'pkit_event', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		function updateMeta( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		}

		const rsvpEnabled = !! meta._pkit_em_rsvp_enabled;

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-event-rsvp',
				title: __( 'RSVP Settings', 'producerkit' ),
				initialOpen: false,
				icon: 'groups',
			},

			el( ToggleControl, {
				label: __( 'Enable RSVPs', 'producerkit' ),
				checked: rsvpEnabled,
				onChange( val ) {
					updateMeta( '_pkit_em_rsvp_enabled', val );
				},
				help: __(
					'Allow visitors to RSVP on the front end.',
					'producerkit'
				),
			} ),

			rsvpEnabled
				? el(
						wp.element.Fragment,
						null,
						el( RangeControl, {
							label: __( 'RSVP Cap', 'producerkit' ),
							value: meta._pkit_rsvp_cap || 0,
							onChange( val ) {
								updateMeta( '_pkit_rsvp_cap', val );
							},
							min: 0,
							max: 200,
							help: __(
								'0 = unlimited. Total headcount including party sizes.',
								'producerkit'
							),
						} ),

						el( TextControl, {
							label: __( 'Button Label', 'producerkit' ),
							value: meta._pkit_em_rsvp_label || '',
							onChange( val ) {
								updateMeta( '_pkit_em_rsvp_label', val );
							},
							placeholder: "I'm coming!",
							help: __(
								'Custom text for the RSVP button.',
								'producerkit'
							),
						} ),

						el( ToggleControl, {
							label: __( 'Manually Close RSVPs', 'producerkit' ),
							checked: !! meta._pkit_em_rsvp_closed,
							onChange( val ) {
								updateMeta( '_pkit_em_rsvp_closed', val );
							},
							help: __(
								'Close RSVPs regardless of the cap.',
								'producerkit'
							),
						} )
				  )
				: null
		);
	}

	/* ─────────────────────────────────────────────
	 * Panel 3: Event Info
	 * ───────────────────────────────────────────── */

	function EventInfoPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'pkit_event' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'pkit_event', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		function updateMeta( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-event-info',
				title: __( 'Event Info', 'producerkit' ),
				initialOpen: false,
				icon: 'info-outline',
			},

			el( TextControl, {
				label: __( 'Cost / Donation Note', 'producerkit' ),
				value: meta._pkit_em_cost_note || '',
				onChange( val ) {
					updateMeta( '_pkit_em_cost_note', val );
				},
				placeholder: __(
					'Donation-based — suggested $10/person',
					'producerkit'
				),
				help: __( 'Shown on the event card.', 'producerkit' ),
			} ),

			el( TextControl, {
				label: __( 'What to Bring', 'producerkit' ),
				value: meta._pkit_em_what_to_bring || '',
				onChange( val ) {
					updateMeta( '_pkit_em_what_to_bring', val );
				},
				placeholder: __(
					'A side dish or dessert to share',
					'producerkit'
				),
				help: __(
					'Shown on the event card with a 🧺 icon.',
					'producerkit'
				),
			} ),

			el( ToggleControl, {
				label: __( 'Event Cancelled', 'producerkit' ),
				checked: !! meta._pkit_em_cancelled,
				onChange( val ) {
					updateMeta( '_pkit_em_cancelled', val );
				},
				help: __(
					'Mark this event as cancelled. It will show a cancelled badge.',
					'producerkit'
				),
			} ),

			!! meta._pkit_em_cancelled
				? el(
						Notice,
						{
							status: 'error',
							isDismissible: false,
						},
						__(
							'This event is marked as cancelled. It will display with a cancelled badge on the front end.',
							'producerkit'
						)
				  )
				: null
		);
	}

	/* ─────────────────────────────────────────────
	 * Panel 4: Featured Products
	 *
	 * _pkit_featured_product_ids has been returned by two REST responses
	 * since it was registered and written by nothing, so a client could read
	 * an array that was always empty. This is the writer.
	 * ───────────────────────────────────────────── */

	function EventFeaturedProductsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		const _meta = useEntityProp( 'postType', 'pkit_event', 'meta' );
		const meta = _meta[ 0 ] || {};
		const setMeta = _meta[ 1 ];

		const featuredIds = meta._pkit_featured_product_ids || [];

		// Extracted so the dependency is a plain value the linter can check;
		// an expression inline in the array cannot be verified statically.
		const featuredKey = featuredIds.join( ',' );

		const allProducts = useSelect( function ( select ) {
			return (
				select( 'core' ).getEntityRecords( 'postType', 'pkit_product', {
					per_page: 50,
					status: 'publish',
					_fields: 'id,title',
				} ) || []
			);
		}, [] );

		// Kept separate from allProducts: a featured product beyond the first
		// fifty, or one since unpublished, still has to render as a row the
		// producer can remove rather than vanishing from the panel while
		// staying in the meta.
		const featuredProducts = useSelect(
			function ( select ) {
				if ( ! featuredIds.length ) {
					return [];
				}

				return featuredIds
					.map( function ( id ) {
						return select( 'core' ).getEntityRecord(
							'postType',
							'pkit_product',
							id
						);
					} )
					.filter( Boolean );
			},
			[ featuredKey ]
		);

		if ( postType !== 'pkit_event' ) {
			return null;
		}

		function setFeatured( ids ) {
			setMeta(
				Object.assign( {}, meta, { _pkit_featured_product_ids: ids } )
			);
		}

		const available = allProducts.filter( function ( product ) {
			return featuredIds.indexOf( product.id ) === -1;
		} );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-event-featured-products',
				title: __( 'Featured Products', 'producerkit' ),
				initialOpen: false,
				icon: 'tag',
			},

			el(
				'p',
				{
					className: 'components-base-control__help',
					style: { marginTop: 0 },
				},
				__(
					'What you are bringing to this one. Shown on the event in the API, so a booth listing can name the three things worth the trip.',
					'producerkit'
				)
			),

			featuredProducts.length > 0
				? el(
						'div',
						{ style: { marginBottom: '12px' } },
						featuredProducts.map( function ( product ) {
							return el(
								'div',
								{
									key: product.id,
									style: {
										display: 'flex',
										justifyContent: 'space-between',
										alignItems: 'center',
										padding: '6px 8px',
										background: '#f0fdf4',
										borderRadius: '4px',
										marginBottom: '4px',
										fontSize: '13px',
									},
								},
								el(
									'span',
									null,
									product.title?.rendered ||
										__( '(untitled)', 'producerkit' )
								),
								el( Button, {
									isSmall: true,
									isDestructive: true,
									icon: 'no-alt',
									label: __( 'Remove', 'producerkit' ),
									onClick() {
										setFeatured(
											featuredIds.filter(
												function ( id ) {
													return id !== product.id;
												}
											)
										);
									},
								} )
							);
						} )
				  )
				: null,

			available.length > 0
				? el( SelectControl, {
						value: '',
						options: [
							{
								label: __( '— Add a product —', 'producerkit' ),
								value: '',
							},
						].concat(
							available.map( function ( product ) {
								return {
									label:
										product.title?.rendered ||
										__( '(untitled)', 'producerkit' ),
									value: product.id,
								};
							} )
						),
						onChange( value ) {
							const id = parseInt( value, 10 );

							if ( id && featuredIds.indexOf( id ) === -1 ) {
								setFeatured( featuredIds.concat( [ id ] ) );
							}
						},
				  } )
				: el(
						'p',
						{ className: 'components-base-control__help' },
						allProducts.length === 0
							? __(
									'No products created yet. Add them under Catalog in the sidebar.',
									'producerkit'
							  )
							: __(
									'Every product is already featured.',
									'producerkit'
							  )
				  )
		);
	}

	/* ─────────────────────────────────────────────
	 * Panel: Recurrence
	 *
	 * Most producers should never see an RRULE. The patterns below cover
	 * what a market, a class or a pickup day actually does; the raw field is
	 * there for the rest, behind a toggle.
	 *
	 * The server is the only thing that decides whether a rule is valid.
	 * Reimplementing RFC 5545 here would mean two parsers to keep agreeing
	 * with each other forever, and the one that disagreed would be this one.
	 * ───────────────────────────────────────────── */

	const WEEKDAY_CODES = [ 'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA' ];

	/**
	 * Build a rule from a pattern and the event's own start date.
	 *
	 * @param pattern
	 * @param startIso
	 */
	function ruleFor( pattern, startIso ) {
		const start = startIso ? new Date( startIso ) : new Date();
		const day = WEEKDAY_CODES[ start.getDay() ];

		switch ( pattern ) {
			case 'weekly':
				return 'FREQ=WEEKLY;BYDAY=' + day;
			case 'fortnightly':
				return 'FREQ=WEEKLY;INTERVAL=2;BYDAY=' + day;
			case 'monthly-date':
				return 'FREQ=MONTHLY';
			case 'monthly-first':
				return 'FREQ=MONTHLY;BYDAY=1' + day;
			case 'monthly-last':
				return 'FREQ=MONTHLY;BYDAY=-1' + day;
			case 'yearly':
				return 'FREQ=YEARLY';
			default:
				return '';
		}
	}

	/**
	 * Which pattern, if any, a stored rule came from — so reopening the event
	 * shows the choice the producer made rather than resetting to Custom.
	 *
	 * @param rule
	 * @param startIso
	 */
	function patternFor( rule, startIso ) {
		if ( ! rule ) {
			return 'none';
		}

		const patterns = [
			'weekly',
			'fortnightly',
			'monthly-date',
			'monthly-first',
			'monthly-last',
			'yearly',
		];

		for ( let i = 0; i < patterns.length; i++ ) {
			if ( ruleFor( patterns[ i ], startIso ) === rule ) {
				return patterns[ i ];
			}
		}

		return 'custom';
	}

	function EventRecurrencePanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		const _meta = useEntityProp( 'postType', 'pkit_event', 'meta' );
		const meta = _meta[ 0 ] || {};
		const setMeta = _meta[ 1 ];

		const rule = meta._pkit_recurrence_rule || '';
		const startIso = meta._pkit_start_datetime || '';

		const _preview = useState( null );
		const preview = _preview[ 0 ];
		const setPreview = _preview[ 1 ];

		const _busy = useState( false );
		const busy = _busy[ 0 ];
		const setBusy = _busy[ 1 ];

		const _advanced = useState( false );
		const advanced = _advanced[ 0 ];
		const setAdvanced = _advanced[ 1 ];

		// Ask the server what the rule means, whenever it or the start moves.
		useEffect(
			function () {
				if ( ! rule ) {
					setPreview( null );
					return;
				}

				let cancelled = false;
				setBusy( true );

				apiFetch( {
					path: '/producerkit/v1/recurrence/preview',
					method: 'POST',
					data: { rule, start: startIso },
				} )
					.then( function ( result ) {
						if ( ! cancelled ) {
							setPreview( result );
							setBusy( false );
						}
					} )
					.catch( function () {
						if ( ! cancelled ) {
							// A failed request is not a failed rule; say so
							// rather than showing a refusal nobody caused.
							setPreview( null );
							setBusy( false );
						}
					} );

				return function () {
					cancelled = true;
				};
			},
			[ rule, startIso ]
		);

		if ( postType !== 'pkit_event' ) {
			return null;
		}

		function setRule( value ) {
			setMeta(
				Object.assign( {}, meta, { _pkit_recurrence_rule: value } )
			);
		}

		const pattern = patternFor( rule, startIso );

		const children = [
			el( SelectControl, {
				key: 'pattern',
				label: __( 'Repeats', 'producerkit' ),
				value: pattern,
				options: [
					{
						label: __( 'Does not repeat', 'producerkit' ),
						value: 'none',
					},
					{
						label: __( 'Every week', 'producerkit' ),
						value: 'weekly',
					},
					{
						label: __( 'Every other week', 'producerkit' ),
						value: 'fortnightly',
					},
					{
						label: __( 'Every month, on this date', 'producerkit' ),
						value: 'monthly-date',
					},
					{
						label: __(
							'Every month, on the first of this weekday',
							'producerkit'
						),
						value: 'monthly-first',
					},
					{
						label: __(
							'Every month, on the last of this weekday',
							'producerkit'
						),
						value: 'monthly-last',
					},
					{
						label: __( 'Every year', 'producerkit' ),
						value: 'yearly',
					},
					{
						label: __( 'Custom rule…', 'producerkit' ),
						value: 'custom',
					},
				],
				onChange( value ) {
					if ( value === 'custom' ) {
						setAdvanced( true );
						return;
					}

					setAdvanced( false );
					setRule( ruleFor( value, startIso ) );
				},
				help: startIso
					? __(
							'Worked out from this event’s own start date.',
							'producerkit'
					  )
					: __(
							'Set a start date first — the pattern is worked out from it.',
							'producerkit'
					  ),
			} ),
		];

		if ( advanced || pattern === 'custom' ) {
			children.push(
				el( TextControl, {
					key: 'raw',
					label: __( 'Recurrence rule', 'producerkit' ),
					value: rule,
					onChange: setRule,
					help: __(
						'An iCal RRULE. Supported: FREQ, INTERVAL, COUNT, UNTIL, BYDAY, BYMONTHDAY, BYMONTH.',
						'producerkit'
					),
				} )
			);
		}

		if ( busy ) {
			children.push( el( Spinner, { key: 'busy' } ) );
		}

		// The refusal, finally shown to the person who caused it.
		if ( preview && ! preview.valid ) {
			children.push(
				el(
					Notice,
					{ key: 'error', status: 'error', isDismissible: false },
					preview.message
				)
			);
		}

		if (
			preview &&
			preview.valid &&
			preview.dates &&
			preview.dates.length
		) {
			children.push(
				el(
					'div',
					{
						key: 'dates',
						style: { marginTop: '8px', fontSize: '12px' },
					},
					el( 'strong', null, __( 'Next dates', 'producerkit' ) ),
					el(
						'ul',
						{ style: { margin: '4px 0 0', paddingLeft: '18px' } },
						preview.dates.map( function ( d ) {
							return el( 'li', { key: d.iso }, d.label );
						} )
					),
					preview.more
						? el(
								'p',
								{ style: { opacity: 0.7, margin: '4px 0 0' } },
								__(
									'…and more. Dates are created about a year ahead and topped up daily.',
									'producerkit'
								)
						  )
						: null
				)
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-event-recurrence',
				title: __( 'Recurrence', 'producerkit' ),
				initialOpen: false,
				icon: 'update',
			},
			children
		);
	}

	/* ─────────────────────────────────────────────
	 * Register
	 * ───────────────────────────────────────────── */

	registerPlugin( 'pkit-event-details', {
		render: EventDetailsPanel,
		icon: 'calendar-alt',
	} );

	registerPlugin( 'pkit-event-rsvp', {
		render: EventRsvpPanel,
		icon: 'groups',
	} );

	registerPlugin( 'pkit-event-featured-products', {
		render: EventFeaturedProductsPanel,
		icon: 'tag',
	} );

	registerPlugin( 'pkit-event-recurrence', {
		render: EventRecurrencePanel,
		icon: 'update',
	} );

	registerPlugin( 'pkit-event-info', {
		render: EventInfoPanel,
		icon: 'info-outline',
	} );
} )();
