/**
 * Event List — editor block (no-build IIFE).
 * @param blocks
 * @param element
 * @param blockEditor
 * @param components
 * @param i18n
 */
( function ( blocks, element, blockEditor, components, i18n ) {
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
	const ToggleControl = components.ToggleControl;
	const RangeControl = components.RangeControl;
	const TextControl = components.TextControl;
	const Placeholder = components.Placeholder;
	const Spinner = components.Spinner;

	function getRestBase() {
		return (
			( window.pkitSettings || {} ).restBase || '/wp-json/producerkit/v1'
		);
	}

	/**
	 * Format an ISO datetime as "Saturday, April 12" / "2:00 PM – 5:00 PM".
	 * @param start
	 * @param end
	 */
	function formatEventDate( start, end ) {
		if ( ! start ) {
			return {};
		}
		const s = new Date( start );
		if ( isNaN( s ) ) {
			return {};
		}
		const dateStr = s.toLocaleDateString( undefined, {
			weekday: 'long',
			month: 'long',
			day: 'numeric',
		} );
		let timeStr = s.toLocaleTimeString( undefined, {
			hour: 'numeric',
			minute: '2-digit',
		} );
		if ( end ) {
			const e = new Date( end );
			if ( ! isNaN( e ) ) {
				timeStr +=
					' \u2013 ' +
					e.toLocaleTimeString( undefined, {
						hour: 'numeric',
						minute: '2-digit',
					} );
			}
		}
		return { date: dateStr, time: timeStr };
	}

	registerBlockType( 'producerkit/event-list', {
		edit: function EditEventList( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const showPastEvents = attributes.showPastEvents;
			const perPage = attributes.perPage;
			const showImages = attributes.showImages;
			const showRsvp = attributes.showRsvp;
			const showLocation = attributes.showLocation;
			const showTypeFilters = attributes.showTypeFilters;

			// Event data from REST.
			const _upcoming = useState( null );
			const upcoming = _upcoming[ 0 ];
			const setUpcoming = _upcoming[ 1 ];

			const _past = useState( null );
			const past = _past[ 0 ];
			const setPast = _past[ 1 ];

			const _loading = useState( false );
			const loading = _loading[ 0 ];
			const setLoading = _loading[ 1 ];

			const _error = useState( '' );
			const error = _error[ 0 ];
			const setError = _error[ 1 ];

			// Fetch upcoming events (and past if enabled).
			useEffect(
				function () {
					setLoading( true );
					setError( '' );
					const fetches = [
						fetch(
							getRestBase() +
								'/events/upcoming?per_page=' +
								perPage
						).then( function ( r ) {
							if ( ! r.ok ) {
								throw new Error( r.status );
							}
							return r.json();
						} ),
					];
					if ( showPastEvents ) {
						fetches.push(
							fetch(
								getRestBase() +
									'/events/past?per_page=' +
									perPage
							).then( function ( r ) {
								if ( ! r.ok ) {
									throw new Error( r.status );
								}
								return r.json();
							} )
						);
					}
					Promise.all( fetches )
						.then( function ( results ) {
							setUpcoming( results[ 0 ] || [] );
							setPast( results[ 1 ] || [] );
							setLoading( false );
						} )
						.catch( function () {
							setError(
								__(
									'Could not load event data.',
									'producerkit'
								)
							);
							setUpcoming( [] );
							setPast( [] );
							setLoading( false );
						} );
				},
				[ perPage, showPastEvents ]
			);

			const blockProps = useBlockProps( {
				className: 'pkit-event-list',
			} );

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
							{ className: 'pkit-event-list__loading' },
							el( Spinner ),
							' Loading events\u2026'
						)
					)
				);
			}

			// Error.
			if ( error ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el( Placeholder, {
							icon: 'warning',
							label: __( 'Event List', 'producerkit' ),
							instructions: error,
						} )
					)
				);
			}

			const hasEvents =
				( upcoming && upcoming.length > 0 ) ||
				( past && past.length > 0 );

			// Empty.
			if ( ! hasEvents ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el(
							'p',
							{ className: 'pkit-event-list__empty' },
							attributes.emptyMessage
						)
					)
				);
			}

			// Collect unique event types for the filter toolbar preview.
			const typeMap = {};
			( upcoming || [] ).concat( past || [] ).forEach( function ( ev ) {
				if (
					ev.event_types &&
					ev.event_types[ 0 ] &&
					ev.event_slugs &&
					ev.event_slugs[ 0 ]
				) {
					typeMap[ ev.event_slugs[ 0 ] ] = ev.event_types[ 0 ];
				}
			} );
			const typeEntries = Object.keys( typeMap );

			// Live preview.
			return el(
				Fragment,
				null,
				renderInspector(),
				el(
					'div',
					blockProps,

					// Type filter toolbar.
					showTypeFilters && typeEntries.length > 1
						? el(
								'div',
								{ className: 'pkit-event-list__filters' },
								el(
									'span',
									{
										className:
											'pkit-event-list__filter-btn pkit-event-list__filter-btn--active',
									},
									__( 'All Events', 'producerkit' )
								),
								typeEntries.map( function ( slug ) {
									return el(
										'span',
										{
											key: slug,
											className:
												'pkit-event-list__filter-btn',
										},
										typeMap[ slug ]
									);
								} )
						  )
						: null,

					// Upcoming section.
					upcoming && upcoming.length > 0
						? renderSection(
								__( 'Upcoming', 'producerkit' ),
								upcoming,
								false
						  )
						: null,

					// Past section.
					showPastEvents && past && past.length > 0
						? renderSection(
								__( 'Past Events', 'producerkit' ),
								past,
								true
						  )
						: null
				)
			);

			/**
			 * Render a section of event cards.
			 * @param title
			 * @param events
			 * @param isPast
			 */
			function renderSection( title, events, isPast ) {
				return el(
					'div',
					{
						className:
							'pkit-event-list__section' +
							( isPast ? ' pkit-event-list__section--past' : '' ),
					},
					el(
						'h3',
						{ className: 'pkit-event-list__section-title' },
						title
					),
					events.map( function ( ev ) {
						return renderEventCard( ev, isPast );
					} )
				);
			}

			/**
			 * Render a single event card preview.
			 * @param ev
			 * @param isPast
			 */
			function renderEventCard( ev, isPast ) {
				const dt = formatEventDate( ev.start, ev.end );
				const rsvp = ev.rsvp;

				return el(
					'article',
					{
						key: ev.id,
						className:
							'pkit-event-card' +
							( ev.cancelled
								? ' pkit-event-card--cancelled'
								: '' ),
					},
					// Image.
					showImages && ev.thumbnail_url
						? el(
								'div',
								{ className: 'pkit-event-card__image' },
								el( 'img', {
									src: ev.thumbnail_url,
									alt: '',
									loading: 'lazy',
								} )
						  )
						: null,

					// Body.
					el(
						'div',
						{ className: 'pkit-event-card__body' },

						// Header (type badge + cancelled badge).
						el(
							'div',
							{ className: 'pkit-event-card__header' },
							ev.event_types && ev.event_types[ 0 ]
								? el(
										'span',
										{
											className:
												'pkit-event-card__type-badge',
										},
										ev.event_types[ 0 ]
								  )
								: null,
							ev.cancelled
								? el(
										'span',
										{
											className:
												'pkit-event-card__cancelled-badge',
										},
										__( 'Cancelled', 'producerkit' )
								  )
								: null
						),

						// Title.
						el(
							'h3',
							{ className: 'pkit-event-card__title' },
							el( 'span', null, ev.title )
						),

						// Date & time.
						dt.date
							? el(
									'p',
									{ className: 'pkit-event-card__datetime' },
									el(
										'span',
										{ className: 'pkit-event-card__date' },
										dt.date
									),
									dt.time
										? el(
												'span',
												{
													className:
														'pkit-event-card__time',
												},
												dt.time
										  )
										: null
							  )
							: null,

						// Location.
						showLocation && ev.location
							? el(
									'p',
									{ className: 'pkit-event-card__location' },
									'\uD83D\uDCCD ',
									ev.location.title,
									ev.location.address
										? el(
												'span',
												{
													className:
														'pkit-event-card__address',
												},
												' \u2014 ' + ev.location.address
										  )
										: null
							  )
							: null,

						// Excerpt.
						ev.excerpt
							? el(
									'p',
									{ className: 'pkit-event-card__excerpt' },
									ev.excerpt
							  )
							: null,

						// Details (cost, bring).
						ev.cost_note || ev.what_to_bring
							? el(
									'div',
									{ className: 'pkit-event-card__details' },
									ev.cost_note
										? el(
												'span',
												{
													className:
														'pkit-event-card__cost',
												},
												'\uD83D\uDCB8 ' + ev.cost_note
										  )
										: null,
									ev.what_to_bring
										? el(
												'span',
												{
													className:
														'pkit-event-card__bring',
												},
												'\uD83E\uDDFA ' +
													ev.what_to_bring
										  )
										: null
							  )
							: null,

						// RSVP summary (preview only — no form in editor).
						showRsvp &&
							! isPast &&
							rsvp &&
							rsvp.enabled &&
							! ev.cancelled
							? el(
									'div',
									{ className: 'pkit-event-card__rsvp' },
									el(
										'div',
										{
											className:
												'pkit-event-card__rsvp-summary',
										},
										rsvp.headcount +
											' people coming' +
											( rsvp.spots_left !== null
												? ' \u00b7 ' +
												  rsvp.spots_left +
												  ' spots left'
												: '' )
									),
									rsvp.is_full
										? el(
												'p',
												{
													className:
														'pkit-event-card__rsvp-full',
												},
												__(
													'This event is full!',
													'producerkit'
												)
										  )
										: rsvp.closed
										? el(
												'p',
												{
													className:
														'pkit-event-card__rsvp-closed',
												},
												__(
													'RSVPs are closed.',
													'producerkit'
												)
										  )
										: el(
												'div',
												{
													className:
														'pkit-event-card__rsvp-form',
												},
												el( 'input', {
													type: 'text',
													className:
														'pkit-event-card__rsvp-input',
													placeholder: __(
														'Your name',
														'producerkit'
													),
													disabled: true,
												} ),
												el( 'input', {
													type: 'number',
													className:
														'pkit-event-card__rsvp-size',
													value: 1,
													disabled: true,
												} ),
												el(
													'button',
													{
														type: 'button',
														className:
															'pkit-event-card__rsvp-btn',
														disabled: true,
													},
													"I'm coming!"
												)
										  )
							  )
							: null
					)
				);
			}

			/**
			 * Sidebar inspector — shared across all render branches.
			 */
			function renderInspector() {
				return el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Event List Settings', 'producerkit' ),
							initialOpen: true,
						},
						el( RangeControl, {
							label: __( 'Events to show', 'producerkit' ),
							value: perPage,
							onChange( val ) {
								setAttributes( { perPage: val } );
							},
							min: 1,
							max: 50,
						} ),
						el( ToggleControl, {
							label: __( 'Show past events', 'producerkit' ),
							checked: showPastEvents,
							onChange( val ) {
								setAttributes( { showPastEvents: val } );
							},
						} ),
						el( ToggleControl, {
							label: __(
								'Show event type filters',
								'producerkit'
							),
							checked: showTypeFilters,
							onChange( val ) {
								setAttributes( { showTypeFilters: val } );
							},
						} ),
						el( TextControl, {
							label: __( 'Empty state message', 'producerkit' ),
							value: attributes.emptyMessage,
							onChange( val ) {
								setAttributes( { emptyMessage: val } );
							},
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Display Options', 'producerkit' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __( 'Show event images', 'producerkit' ),
							checked: showImages,
							onChange( val ) {
								setAttributes( { showImages: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show RSVP forms', 'producerkit' ),
							checked: showRsvp,
							onChange( val ) {
								setAttributes( { showRsvp: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show location details', 'producerkit' ),
							checked: showLocation,
							onChange( val ) {
								setAttributes( { showLocation: val } );
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
	window.wp.i18n
);
