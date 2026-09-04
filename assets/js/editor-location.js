/**
 * Location Editor Sidebar — custom panels for pkit_location meta.
 *
 * Registers two sidebar panels:
 *   1. Location Details — address, type, hours, Venmo, coordinates
 *   2. Stand Schedule   — season dates, weekly schedule builder, auto-toggle
 *
 * Uses PluginDocumentSettingPanel + useEntityProp (no build step).
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
	const Button = wp.components.Button;
	/* ─────────────────────────────────────────────
	 * Panel 1: Location Details
	 * ───────────────────────────────────────────── */

	function LocationDetailsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'pkit_location' ) {
			return null;
		}

		const _address = useEntityProp( 'postType', 'pkit_location', 'meta' );
		const meta = _address[ 0 ];
		const setMeta = _address[ 1 ];

		function updateMeta( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-location-details',
				title: __( 'Location Details', 'producerkit' ),
				initialOpen: true,
				icon: 'store',
			},

			el( SelectControl, {
				label: __( 'Location Type', 'producerkit' ),
				value: meta._pkit_location_type || 'stand',
				options: [
					{
						label: __( 'Farm Stand', 'producerkit' ),
						value: 'stand',
					},
					{
						label: __( 'Farmers Market', 'producerkit' ),
						value: 'market',
					},
					{ label: __( 'On-Farm', 'producerkit' ), value: 'on-farm' },
					{
						label: __( 'Retailer', 'producerkit' ),
						value: 'retailer',
					},
					{ label: __( 'Other', 'producerkit' ), value: 'other' },
				],
				onChange( val ) {
					updateMeta( '_pkit_location_type', val );
				},
				help: __( 'What kind of location is this?', 'producerkit' ),
			} ),

			el( TextControl, {
				label: __( 'Address', 'producerkit' ),
				value: meta._pkit_address || '',
				onChange( val ) {
					updateMeta( '_pkit_address', val );
				},
				placeholder: __(
					'123 Farm Road, Yourtown, ST 00000',
					'producerkit'
				),
				help: __(
					'Full street address shown to visitors.',
					'producerkit'
				),
			} ),

			el( TextControl, {
				label: __( 'Hours', 'producerkit' ),
				value: meta._pkit_hours || '',
				onChange( val ) {
					updateMeta( '_pkit_hours', val );
				},
				placeholder: __(
					'Saturdays 1:00 – 4:00 PM, May – December',
					'producerkit'
				),
				help: __(
					'Displayed on the front end. Free-form text.',
					'producerkit'
				),
			} ),

			el( TextControl, {
				label: __( 'Venmo Handle', 'producerkit' ),
				value: meta._pkit_venmo_handle || '',
				onChange( val ) {
					updateMeta( '_pkit_venmo_handle', val.replace( /^@/, '' ) );
				},
				placeholder: __( 'examplefarm', 'producerkit' ),
				help: __(
					'Without the @. Used to generate the Venmo payment link.',
					'producerkit'
				),
			} ),

			el(
				'div',
				{ style: { display: 'flex', gap: '8px' } },
				el( TextControl, {
					label: __( 'Latitude', 'producerkit' ),
					type: 'number',
					step: 'any',
					value: meta._pkit_lat || '',
					onChange( val ) {
						updateMeta( '_pkit_lat', parseFloat( val ) || 0 );
					},
					style: { flex: 1 },
				} ),
				el( TextControl, {
					label: __( 'Longitude', 'producerkit' ),
					type: 'number',
					step: 'any',
					value: meta._pkit_lng || '',
					onChange( val ) {
						updateMeta( '_pkit_lng', parseFloat( val ) || 0 );
					},
					style: { flex: 1 },
				} )
			),

			el( ToggleControl, {
				label: __( 'Currently Open', 'producerkit' ),
				checked: !! meta._pkit_is_open,
				onChange( val ) {
					updateMeta( '_pkit_is_open', val );
				},
				help: __(
					'Toggle this location open or closed right now.',
					'producerkit'
				),
			} ),

			meta._pkit_ss_status_message !== undefined
				? el( TextControl, {
						label: __( 'Status Message', 'producerkit' ),
						value: meta._pkit_ss_status_message || '',
						onChange( val ) {
							updateMeta( '_pkit_ss_status_message', val );
						},
						placeholder: __( 'Back at 2 PM', 'producerkit' ),
						help: __(
							'Optional message shown alongside open/closed status.',
							'producerkit'
						),
				  } )
				: null
		);
	}

	/* ─────────────────────────────────────────────
	 * Panel 2: Stand Schedule & Season
	 * ───────────────────────────────────────────── */

	const DAY_LABELS = [
		'Sunday',
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
	];

	function StandSchedulePanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'pkit_location' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'pkit_location', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		function updateMeta( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		}

		// Parse schedule JSON into array.
		const scheduleRaw = meta._pkit_ss_schedule || '[]';
		let schedule;
		try {
			schedule = JSON.parse( scheduleRaw );
			if ( ! Array.isArray( schedule ) ) {
				schedule = [];
			}
		} catch ( e ) {
			schedule = [];
		}

		function updateSchedule( newSchedule ) {
			updateMeta( '_pkit_ss_schedule', JSON.stringify( newSchedule ) );
		}

		function addDay() {
			// Find first day not in schedule.
			const usedDays = schedule.map( function ( e ) {
				return e.day;
			} );
			let nextDay = 6; // Default to Saturday.
			for ( let d = 0; d <= 6; d++ ) {
				if ( usedDays.indexOf( d ) === -1 ) {
					nextDay = d;
					break;
				}
			}
			const updated = schedule.concat( [
				{ day: nextDay, open: '09:00', close: '16:00' },
			] );
			updateSchedule( updated );
		}

		function removeDay( index ) {
			const updated = schedule.filter( function ( _, i ) {
				return i !== index;
			} );
			updateSchedule( updated );
		}

		function updateDay( index, field, value ) {
			const updated = schedule.map( function ( entry, i ) {
				if ( i !== index ) {
					return entry;
				}
				const copy = Object.assign( {}, entry );
				copy[ field ] = field === 'day' ? parseInt( value, 10 ) : value;
				return copy;
			} );
			updateSchedule( updated );
		}

		// Season date handling.
		const seasonStart = meta._pkit_ss_season_start || '';
		const seasonEnd = meta._pkit_ss_season_end || '';

		// Pickup blackout dates (JSON array of YYYY-MM-DD in meta).
		const blackouts = parseBlackouts();

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-stand-schedule',
				title: __( 'Schedule & Season', 'producerkit' ),
				initialOpen: false,
				icon: 'clock',
			},

			// Season dates.
			el(
				'p',
				{ style: { fontWeight: 600, marginBottom: '4px' } },
				'Season Dates'
			),
			el(
				'p',
				{
					className: 'components-base-control__help',
					style: { marginTop: 0, marginBottom: '8px' },
				},
				'Leave blank if open year-round.'
			),

			el(
				'div',
				{
					style: {
						display: 'flex',
						gap: '8px',
						marginBottom: '16px',
					},
				},
				el( TextControl, {
					label: __( 'Start', 'producerkit' ),
					type: 'date',
					value: seasonStart,
					onChange( val ) {
						updateMeta( '_pkit_ss_season_start', val );
					},
					style: { flex: 1 },
				} ),
				el( TextControl, {
					label: __( 'End', 'producerkit' ),
					type: 'date',
					value: seasonEnd,
					onChange( val ) {
						updateMeta( '_pkit_ss_season_end', val );
					},
					style: { flex: 1 },
				} )
			),

			// Auto-toggle.
			el( ToggleControl, {
				label: __( 'Auto-toggle from schedule', 'producerkit' ),
				checked: !! meta._pkit_ss_auto_toggle,
				onChange( val ) {
					updateMeta( '_pkit_ss_auto_toggle', val );
				},
				help: __(
					'Automatically open/close based on the weekly schedule below.',
					'producerkit'
				),
			} ),

			// Weekly schedule entries.
			el(
				'p',
				{
					style: {
						fontWeight: 600,
						marginBottom: '4px',
						marginTop: '12px',
					},
				},
				'Weekly Schedule'
			),

			schedule.length === 0
				? el(
						'p',
						{
							style: {
								color: '#6b7280',
								fontStyle: 'italic',
								fontSize: '13px',
							},
						},
						'No schedule set. Add a day below.'
				  )
				: null,

			schedule.map( function ( entry, i ) {
				return el(
					'div',
					{
						key: i,
						style: {
							display: 'flex',
							gap: '6px',
							alignItems: 'flex-end',
							marginBottom: '8px',
							padding: '8px',
							background: '#f9fafb',
							borderRadius: '4px',
						},
					},
					el( SelectControl, {
						label: i === 0 ? 'Day' : '',
						value: entry.day,
						options: DAY_LABELS.map( function ( label, d ) {
							return { label, value: d };
						} ),
						onChange( val ) {
							updateDay( i, 'day', val );
						},
						style: { flex: 2 },
						__nextHasNoMarginBottom: true,
					} ),
					el( TextControl, {
						label: i === 0 ? 'Open' : '',
						type: 'time',
						value: entry.open || '09:00',
						onChange( val ) {
							updateDay( i, 'open', val );
						},
						style: { flex: 1 },
					} ),
					el( TextControl, {
						label: i === 0 ? 'Close' : '',
						type: 'time',
						value: entry.close || '16:00',
						onChange( val ) {
							updateDay( i, 'close', val );
						},
						style: { flex: 1 },
					} ),
					el( Button, {
						isDestructive: true,
						isSmall: true,
						icon: 'no-alt',
						label: __( 'Remove', 'producerkit' ),
						onClick() {
							removeDay( i );
						},
						style: { marginBottom: '8px' },
					} )
				);
			} ),

			schedule.length < 7
				? el(
						Button,
						{
							variant: 'secondary',
							isSmall: true,
							icon: 'plus-alt2',
							onClick: addDay,
							style: { marginTop: '4px' },
						},
						'Add Day'
				  )
				: null,

			// Pickup blackout dates (holidays, closures).
			el(
				'p',
				{
					style: {
						fontWeight: 600,
						marginBottom: '4px',
						marginTop: '16px',
					},
				},
				'Closed Dates'
			),
			el(
				'p',
				{
					className: 'components-base-control__help',
					style: { marginTop: 0, marginBottom: '8px' },
				},
				'Specific dates with no pickups — holidays, closures. Pre-orders for these dates are refused.'
			),

			blackouts.map( function ( date, i ) {
				return el(
					'div',
					{
						key: 'b' + i,
						style: {
							display: 'flex',
							gap: '6px',
							alignItems: 'center',
							marginBottom: '6px',
						},
					},
					el( TextControl, {
						type: 'date',
						value: date,
						onChange( val ) {
							updateBlackout( i, val );
						},
						style: { flex: 1 },
						__nextHasNoMarginBottom: true,
					} ),
					el( Button, {
						isDestructive: true,
						isSmall: true,
						icon: 'no-alt',
						label: __( 'Remove', 'producerkit' ),
						onClick() {
							removeBlackout( i );
						},
					} )
				);
			} ),

			el(
				Button,
				{
					variant: 'secondary',
					isSmall: true,
					icon: 'plus-alt2',
					onClick: addBlackout,
					style: { marginTop: '4px' },
				},
				'Add Closed Date'
			)
		);

		function parseBlackouts() {
			try {
				const parsed = JSON.parse(
					meta._pkit_pickup_blackouts || '[]'
				);
				return Array.isArray( parsed ) ? parsed : [];
			} catch ( e ) {
				return [];
			}
		}

		function saveBlackouts( next ) {
			// Empty rows stay while editing; the server-side sanitizer
			// drops anything that isn't a valid date on save.
			updateMeta( '_pkit_pickup_blackouts', JSON.stringify( next ) );
		}

		function addBlackout() {
			saveBlackouts( blackouts.concat( [ '' ] ) );
		}

		function removeBlackout( index ) {
			saveBlackouts(
				blackouts.filter( function ( _, i ) {
					return i !== index;
				} )
			);
		}

		function updateBlackout( index, value ) {
			saveBlackouts(
				blackouts.map( function ( d, i ) {
					return i === index ? value : d;
				} )
			);
		}
	}

	/* ─────────────────────────────────────────────
	 * Panel 3: Payment Options
	 * ───────────────────────────────────────────── */

	// Mirrors ProducerKit\Core\Payments\method_types(). kind: handle | url | badge.
	const PAYMENT_TYPES = [
		{ value: 'venmo', label: __( 'Venmo', 'producerkit' ), kind: 'handle' },
		{
			value: 'cashapp',
			label: __( 'Cash App', 'producerkit' ),
			kind: 'handle',
		},
		{
			value: 'paypal',
			label: __( 'PayPal', 'producerkit' ),
			kind: 'handle',
		},
		{
			value: 'link',
			label: __( 'Payment Link', 'producerkit' ),
			kind: 'url',
		},
		{ value: 'cash', label: __( 'Cash', 'producerkit' ), kind: 'badge' },
		{ value: 'check', label: __( 'Check', 'producerkit' ), kind: 'badge' },
		{
			value: 'snap_ebt',
			label: __( 'SNAP/EBT', 'producerkit' ),
			kind: 'badge',
		},
		{
			value: 'market_voucher',
			label: __( 'Market Vouchers (WIC/Sr FMNP)', 'producerkit' ),
			kind: 'badge',
		},
	];

	function paymentKind( type ) {
		const found = PAYMENT_TYPES.filter( function ( t ) {
			return t.value === type;
		} )[ 0 ];
		return found ? found.kind : 'badge';
	}

	function PaymentOptionsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'pkit_location' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'pkit_location', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		const methodsRaw = meta._pkit_payment_methods || '[]';
		let methods;
		try {
			methods = JSON.parse( methodsRaw );
			if ( ! Array.isArray( methods ) ) {
				methods = [];
			}
		} catch ( e ) {
			methods = [];
		}

		function updateMethods( next ) {
			const updated = {};
			updated._pkit_payment_methods = JSON.stringify( next );
			setMeta( Object.assign( {}, meta, updated ) );
		}

		function addMethod() {
			updateMethods(
				methods.concat( [ { type: 'cash', value: '', label: '' } ] )
			);
		}

		function removeMethod( index ) {
			updateMethods(
				methods.filter( function ( _, i ) {
					return i !== index;
				} )
			);
		}

		function updateMethod( index, field, value ) {
			updateMethods(
				methods.map( function ( entry, i ) {
					if ( i !== index ) {
						return entry;
					}
					const copy = Object.assign( {}, entry );
					copy[ field ] = value;
					// Reset the value when switching to a badge type.
					if (
						field === 'type' &&
						paymentKind( value ) === 'badge'
					) {
						copy.value = '';
					}
					return copy;
				} )
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-payment-options',
				title: __( 'Payment Options', 'producerkit' ),
				initialOpen: false,
				icon: 'money-alt',
			},

			el(
				'p',
				{
					className: 'components-base-control__help',
					style: { marginTop: 0 },
				},
				'Links (Venmo, Cash App, PayPal, custom) and accepted-payment badges (cash, SNAP/EBT, …) shown on the front end. The legacy Venmo Handle field still works if this list is empty.'
			),

			methods.length === 0
				? el(
						'p',
						{
							style: {
								color: '#6b7280',
								fontStyle: 'italic',
								fontSize: '13px',
							},
						},
						'No payment options set. Add one below.'
				  )
				: null,

			methods.map( function ( entry, i ) {
				const kind = paymentKind( entry.type );
				return el(
					'div',
					{
						key: i,
						style: {
							marginBottom: '8px',
							padding: '8px',
							background: '#f9fafb',
							borderRadius: '4px',
						},
					},
					el(
						'div',
						{
							style: {
								display: 'flex',
								gap: '6px',
								alignItems: 'flex-end',
							},
						},
						el( SelectControl, {
							label: __( 'Method', 'producerkit' ),
							value: entry.type,
							options: PAYMENT_TYPES.map( function ( t ) {
								return { label: t.label, value: t.value };
							} ),
							onChange( val ) {
								updateMethod( i, 'type', val );
							},
							style: { flex: 2 },
							__nextHasNoMarginBottom: true,
						} ),
						el( Button, {
							isDestructive: true,
							isSmall: true,
							icon: 'no-alt',
							label: __( 'Remove', 'producerkit' ),
							onClick() {
								removeMethod( i );
							},
							style: { marginBottom: '2px' },
						} )
					),
					kind === 'handle'
						? el( TextControl, {
								label: __( 'Handle', 'producerkit' ),
								value: entry.value || '',
								onChange( val ) {
									updateMethod(
										i,
										'value',
										val.replace( /^[@$]/, '' )
									);
								},
								placeholder: __( 'examplefarm', 'producerkit' ),
								help: __(
									'Username without the @ or $.',
									'producerkit'
								),
						  } )
						: null,
					kind === 'url'
						? el( TextControl, {
								label: __( 'URL', 'producerkit' ),
								type: 'url',
								value: entry.value || '',
								onChange( val ) {
									updateMethod( i, 'value', val );
								},
								placeholder: __(
									'https://squareup.com/…',
									'producerkit'
								),
						  } )
						: null,
					el( TextControl, {
						label: __( 'Display label (optional)', 'producerkit' ),
						value: entry.label || '',
						onChange( val ) {
							updateMethod( i, 'label', val );
						},
						placeholder: '',
					} )
				);
			} ),

			el(
				Button,
				{
					variant: 'secondary',
					isSmall: true,
					icon: 'plus-alt2',
					onClick: addMethod,
					style: { marginTop: '4px' },
				},
				'Add Payment Option'
			)
		);
	}

	/* ─────────────────────────────────────────────
	 * Register
	 * ───────────────────────────────────────────── */

	registerPlugin( 'pkit-location-details', {
		render: LocationDetailsPanel,
		icon: 'store',
	} );

	registerPlugin( 'pkit-stand-schedule', {
		render: StandSchedulePanel,
		icon: 'clock',
	} );

	registerPlugin( 'pkit-payment-options', {
		render: PaymentOptionsPanel,
		icon: 'money-alt',
	} );
} )();
