/**
 * Event Card — editor block (no-build IIFE).
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
	const ComboboxControl = components.ComboboxControl;
	const Placeholder = components.Placeholder;
	const Spinner = components.Spinner;
	const useSelect = data.useSelect;

	function getRestBase() {
		return (
			( window.pkitSettings || {} ).restBase || '/wp-json/producerkit/v1'
		);
	}

	/**
	 * Format an ISO datetime for display.
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

	registerBlockType( 'producerkit/event-card', {
		edit: function EditEventCard( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const eventId = attributes.eventId;
			const showImage = attributes.showImage;
			const showRsvp = attributes.showRsvp;
			const showLocation = attributes.showLocation;

			// Event data from REST.
			const _ev = useState( null );
			const ev = _ev[ 0 ];
			const setEv = _ev[ 1 ];

			const _loading = useState( false );
			const loading = _loading[ 0 ];
			const setLoading = _loading[ 1 ];

			const _error = useState( '' );
			const error = _error[ 0 ];
			const setError = _error[ 1 ];

			// Fetch event data — search both upcoming and past.
			useEffect(
				function () {
					if ( ! eventId ) {
						setEv( null );
						return;
					}
					setLoading( true );
					setError( '' );

					Promise.all( [
						fetch( getRestBase() + '/events/upcoming?per_page=50' )
							.then( function ( r ) {
								return r.ok ? r.json() : [];
							} )
							.catch( function () {
								return [];
							} ),
						fetch( getRestBase() + '/events/past?per_page=50' )
							.then( function ( r ) {
								return r.ok ? r.json() : [];
							} )
							.catch( function () {
								return [];
							} ),
					] ).then( function ( results ) {
						const all = results[ 0 ].concat( results[ 1 ] );
						let match = null;
						for ( let i = 0; i < all.length; i++ ) {
							if ( all[ i ].id === eventId ) {
								match = all[ i ];
								break;
							}
						}
						setEv( match );
						if ( ! match ) {
							setError(
								'Event not found. It may be unpublished or not yet scheduled.'
							);
						}
						setLoading( false );
					} );
				},
				[ eventId ]
			);

			// Event list for the picker.
			const events = useSelect( function ( select ) {
				return (
					select( 'core' ).getEntityRecords(
						'postType',
						'pkit_event',
						{
							per_page: 100,
							status: 'publish',
							_fields: 'id,title',
						}
					) || []
				);
			}, [] );

			const options = events.map( function ( e ) {
				return {
					value: e.id,
					label: e.title?.rendered || '(untitled)',
				};
			} );

			const blockProps = useBlockProps( {
				className: 'pkit-event-card-wrapper',
			} );

			// No event selected — placeholder with inline picker.
			if ( ! eventId ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el(
							Placeholder,
							{ icon: 'calendar', label: 'Event Card' },
							el( ComboboxControl, {
								label: 'Select an event',
								value: '',
								options,
								onChange( val ) {
									setAttributes( {
										eventId: val ? Number( val ) : 0,
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
							{ className: 'pkit-event-card-wrapper__loading' },
							el( Spinner ),
							' Loading event\u2026'
						)
					)
				);
			}

			// Error.
			if ( error || ! ev ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el( Placeholder, {
							icon: 'warning',
							label: 'Event Card',
							instructions: error || 'Event data unavailable.',
						} )
					)
				);
			}

			// Live preview — mirrors render_event_card() structure.
			const dt = formatEventDate( ev.start, ev.end );
			const rsvp = ev.rsvp;

			return el(
				Fragment,
				null,
				renderInspector(),
				el(
					'div',
					blockProps,
					el(
						'article',
						{
							className:
								'pkit-event-card' +
								( ev.cancelled
									? ' pkit-event-card--cancelled'
									: '' ),
						},
						// Image.
						showImage && ev.thumbnail_url
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

							// Header.
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
											'Cancelled'
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
										{
											className:
												'pkit-event-card__datetime',
										},
										el(
											'span',
											{
												className:
													'pkit-event-card__date',
											},
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
										{
											className:
												'pkit-event-card__location',
										},
										'\uD83D\uDCCD ',
										ev.location.title,
										ev.location.address
											? el(
													'span',
													{
														className:
															'pkit-event-card__address',
													},
													' \u2014 ' +
														ev.location.address
											  )
											: null
								  )
								: null,

							// Excerpt.
							ev.excerpt
								? el(
										'p',
										{
											className:
												'pkit-event-card__excerpt',
										},
										ev.excerpt
								  )
								: null,

							// Details.
							ev.cost_note || ev.what_to_bring
								? el(
										'div',
										{
											className:
												'pkit-event-card__details',
										},
										ev.cost_note
											? el(
													'span',
													{
														className:
															'pkit-event-card__cost',
													},
													'\uD83D\uDCB8 ' +
														ev.cost_note
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

							// RSVP summary (preview only — form is disabled in editor).
							showRsvp && rsvp && rsvp.enabled && ! ev.cancelled
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
													'This event is full!'
											  )
											: rsvp.closed
											? el(
													'p',
													{
														className:
															'pkit-event-card__rsvp-closed',
													},
													'RSVPs are closed.'
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
														placeholder:
															'Your name',
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
					)
				)
			);

			function renderInspector() {
				return el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Event Settings', initialOpen: true },
						el( ComboboxControl, {
							label: 'Select Event',
							value: eventId || '',
							options,
							onChange( val ) {
								setAttributes( {
									eventId: val ? Number( val ) : 0,
								} );
							},
						} ),
						el( ToggleControl, {
							label: 'Show image',
							checked: showImage,
							onChange( val ) {
								setAttributes( { showImage: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show RSVP form',
							checked: showRsvp,
							onChange( val ) {
								setAttributes( { showRsvp: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show location',
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
	window.wp.data
);
