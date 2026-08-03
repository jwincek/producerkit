/**
 * Location Editor Sidebar — custom panels for lfuf_location meta.
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

		if ( postType !== 'lfuf_location' ) {
			return null;
		}

		const _address = useEntityProp( 'postType', 'lfuf_location', 'meta' );
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
				name: 'lfuf-location-details',
				title: 'Location Details',
				initialOpen: true,
				icon: 'store',
			},

			el( SelectControl, {
				label: 'Location Type',
				value: meta._lfuf_location_type || 'stand',
				options: [
					{ label: 'Farm Stand', value: 'stand' },
					{ label: 'Farmers Market', value: 'market' },
					{ label: 'On-Farm', value: 'on-farm' },
					{ label: 'Other', value: 'other' },
				],
				onChange( val ) {
					updateMeta( '_lfuf_location_type', val );
				},
				help: 'What kind of location is this?',
			} ),

			el( TextControl, {
				label: 'Address',
				value: meta._lfuf_address || '',
				onChange( val ) {
					updateMeta( '_lfuf_address', val );
				},
				placeholder: '123 Farm Road, Yourtown, ST 00000',
				help: 'Full street address shown to visitors.',
			} ),

			el( TextControl, {
				label: 'Hours',
				value: meta._lfuf_hours || '',
				onChange( val ) {
					updateMeta( '_lfuf_hours', val );
				},
				placeholder: 'Saturdays 1:00 – 4:00 PM, May – December',
				help: 'Displayed on the front end. Free-form text.',
			} ),

			el( TextControl, {
				label: 'Venmo Handle',
				value: meta._lfuf_venmo_handle || '',
				onChange( val ) {
					updateMeta( '_lfuf_venmo_handle', val.replace( /^@/, '' ) );
				},
				placeholder: 'examplefarm',
				help: 'Without the @. Used to generate the Venmo payment link.',
			} ),

			el(
				'div',
				{ style: { display: 'flex', gap: '8px' } },
				el( TextControl, {
					label: 'Latitude',
					type: 'number',
					step: 'any',
					value: meta._lfuf_lat || '',
					onChange( val ) {
						updateMeta( '_lfuf_lat', parseFloat( val ) || 0 );
					},
					style: { flex: 1 },
				} ),
				el( TextControl, {
					label: 'Longitude',
					type: 'number',
					step: 'any',
					value: meta._lfuf_lng || '',
					onChange( val ) {
						updateMeta( '_lfuf_lng', parseFloat( val ) || 0 );
					},
					style: { flex: 1 },
				} )
			),

			el( ToggleControl, {
				label: 'Currently Open',
				checked: !! meta._lfuf_is_open,
				onChange( val ) {
					updateMeta( '_lfuf_is_open', val );
				},
				help: 'Toggle this location open or closed right now.',
			} ),

			meta._lfuf_ss_status_message !== undefined
				? el( TextControl, {
						label: 'Status Message',
						value: meta._lfuf_ss_status_message || '',
						onChange( val ) {
							updateMeta( '_lfuf_ss_status_message', val );
						},
						placeholder: 'Back at 2 PM',
						help: 'Optional message shown alongside open/closed status.',
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

		if ( postType !== 'lfuf_location' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'lfuf_location', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		function updateMeta( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		}

		// Parse schedule JSON into array.
		const scheduleRaw = meta._lfuf_ss_schedule || '[]';
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
			updateMeta( '_lfuf_ss_schedule', JSON.stringify( newSchedule ) );
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
		const seasonStart = meta._lfuf_ss_season_start || '';
		const seasonEnd = meta._lfuf_ss_season_end || '';

		// Pickup blackout dates (JSON array of YYYY-MM-DD in meta).
		const blackouts = parseBlackouts();

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'lfuf-stand-schedule',
				title: 'Schedule & Season',
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
					label: 'Start',
					type: 'date',
					value: seasonStart,
					onChange( val ) {
						updateMeta( '_lfuf_ss_season_start', val );
					},
					style: { flex: 1 },
				} ),
				el( TextControl, {
					label: 'End',
					type: 'date',
					value: seasonEnd,
					onChange( val ) {
						updateMeta( '_lfuf_ss_season_end', val );
					},
					style: { flex: 1 },
				} )
			),

			// Auto-toggle.
			el( ToggleControl, {
				label: 'Auto-toggle from schedule',
				checked: !! meta._lfuf_ss_auto_toggle,
				onChange( val ) {
					updateMeta( '_lfuf_ss_auto_toggle', val );
				},
				help: 'Automatically open/close based on the weekly schedule below.',
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
						label: 'Remove',
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
						label: 'Remove',
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
					meta._lfuf_pickup_blackouts || '[]'
				);
				return Array.isArray( parsed ) ? parsed : [];
			} catch ( e ) {
				return [];
			}
		}

		function saveBlackouts( next ) {
			// Empty rows stay while editing; the server-side sanitizer
			// drops anything that isn't a valid date on save.
			updateMeta( '_lfuf_pickup_blackouts', JSON.stringify( next ) );
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

	// Mirrors Leftfield\Core\Payments\method_types(). kind: handle | url | badge.
	const PAYMENT_TYPES = [
		{ value: 'venmo', label: 'Venmo', kind: 'handle' },
		{ value: 'cashapp', label: 'Cash App', kind: 'handle' },
		{ value: 'paypal', label: 'PayPal', kind: 'handle' },
		{ value: 'link', label: 'Payment Link', kind: 'url' },
		{ value: 'cash', label: 'Cash', kind: 'badge' },
		{ value: 'check', label: 'Check', kind: 'badge' },
		{ value: 'snap_ebt', label: 'SNAP/EBT', kind: 'badge' },
		{
			value: 'market_voucher',
			label: 'Market Vouchers (WIC/Sr FMNP)',
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

		if ( postType !== 'lfuf_location' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'lfuf_location', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		const methodsRaw = meta._lfuf_payment_methods || '[]';
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
			updated._lfuf_payment_methods = JSON.stringify( next );
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
				name: 'lfuf-payment-options',
				title: 'Payment Options',
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
							label: 'Method',
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
							label: 'Remove',
							onClick() {
								removeMethod( i );
							},
							style: { marginBottom: '2px' },
						} )
					),
					kind === 'handle'
						? el( TextControl, {
								label: 'Handle',
								value: entry.value || '',
								onChange( val ) {
									updateMethod(
										i,
										'value',
										val.replace( /^[@$]/, '' )
									);
								},
								placeholder: 'examplefarm',
								help: 'Username without the @ or $.',
						  } )
						: null,
					kind === 'url'
						? el( TextControl, {
								label: 'URL',
								type: 'url',
								value: entry.value || '',
								onChange( val ) {
									updateMethod( i, 'value', val );
								},
								placeholder: 'https://squareup.com/…',
						  } )
						: null,
					el( TextControl, {
						label: 'Display label (optional)',
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

	registerPlugin( 'lfuf-location-details', {
		render: LocationDetailsPanel,
		icon: 'store',
	} );

	registerPlugin( 'lfuf-stand-schedule', {
		render: StandSchedulePanel,
		icon: 'clock',
	} );

	registerPlugin( 'lfuf-payment-options', {
		render: PaymentOptionsPanel,
		icon: 'money-alt',
	} );
} )();
