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

	registerPlugin( 'pkit-event-info', {
		render: EventInfoPanel,
		icon: 'info-outline',
	} );
} )();
