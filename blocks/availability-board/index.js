/**
 * Availability Board — editor block (no-build IIFE).
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
	const CheckboxControl = components.CheckboxControl;
	const SelectControl = components.SelectControl;
	const TextControl = components.TextControl;
	const ComboboxControl = components.ComboboxControl;
	const Placeholder = components.Placeholder;
	const Spinner = components.Spinner;
	const useSelect = data.useSelect;

	const ALL_STATUSES = [
		'abundant',
		'available',
		'limited',
		'sold_out',
		'unavailable',
	];

	const STATUS_LABELS = {
		abundant: 'Abundant',
		available: 'Available',
		limited: 'Limited',
		sold_out: 'Sold out',
		unavailable: 'Unavailable',
	};

	function getRestBase() {
		return ( window.lfufSettings || {} ).restBase || '/wp-json/lfuf/v1';
	}

	function statusLabel( slug ) {
		return STATUS_LABELS[ slug ] || slug;
	}

	/**
	 * Parse the comma-separated defaultStatusFilter into an array.
	 * @param str
	 */
	function parseActiveStatuses( str ) {
		return ( str || '' )
			.split( ',' )
			.map( function ( s ) {
				return s.trim();
			} )
			.filter( Boolean );
	}

	registerBlockType( 'lfuf/availability-board', {
		edit: function EditBoard( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const layout = attributes.layout;
			const showFilters = attributes.showFilters;
			const showImages = attributes.showImages;
			const showPrices = attributes.showPrices;
			const showQuantityNotes = attributes.showQuantityNotes;
			const locationId = attributes.locationId;

			// Board data from REST.
			const _board = useState( null );
			const board = _board[ 0 ];
			const setBoard = _board[ 1 ];

			const _loading = useState( false );
			const loading = _loading[ 0 ];
			const setLoading = _loading[ 1 ];

			const _error = useState( '' );
			const error = _error[ 0 ];
			const setError = _error[ 1 ];

			// Fetch board data when locationId changes.
			useEffect(
				function () {
					setLoading( true );
					setError( '' );
					let url = getRestBase() + '/board';
					if ( locationId ) {
						url += '?location=' + locationId;
					}
					fetch( url )
						.then( function ( r ) {
							if ( ! r.ok ) {
								throw new Error(
									r.status + ' ' + r.statusText
								);
							}
							return r.json();
						} )
						.then( function ( payload ) {
							setBoard( payload );
							setLoading( false );
						} )
						.catch( function () {
							setError( 'Could not load board data.' );
							setBoard( null );
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

			const locationOptions = [
				{ value: 0, label: '\u2014 All locations \u2014' },
			].concat(
				locations.map( function ( l ) {
					return {
						value: l.id,
						label: l.title?.rendered || '(untitled)',
					};
				} )
			);

			const blockProps = useBlockProps( {
				className: 'lfuf-avail-board lfuf-avail-board--' + layout,
			} );

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
							{ className: 'lfuf-avail-board__loading' },
							el( Spinner ),
							' Loading availability data\u2026'
						)
					)
				);
			}

			// Error state.
			if ( error || ! board ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el( Placeholder, {
							icon: 'warning',
							label: 'Availability Board',
							instructions: error || 'Board data unavailable.',
						} )
					)
				);
			}

			// Empty board.
			const groups = board.groups || [];
			const total = board.total_items || 0;

			if ( total === 0 ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'section',
						blockProps,
						el(
							'p',
							{ className: 'lfuf-avail-board__empty' },
							attributes.emptyMessage
						)
					)
				);
			}

			// Live preview.
			return el(
				Fragment,
				null,
				renderInspector(),
				el(
					'section',
					blockProps,

					// Filter toolbar preview.
					showFilters
						? el(
								'div',
								{ className: 'lfuf-avail-board__filters' },
								el(
									'div',
									{
										className:
											'lfuf-avail-board__filter-group',
										role: 'toolbar',
									},
									el(
										'span',
										{
											className:
												'lfuf-avail-board__filter-label',
										},
										'Show:'
									),
									( board.statuses || [] ).map(
										function ( s ) {
											const isActive =
												parseActiveStatuses(
													attributes.defaultStatusFilter
												).indexOf( s ) !== -1;
											return el(
												'span',
												{
													key: s,
													className:
														'lfuf-avail-board__filter-btn lfuf-availability-badge lfuf-availability-badge--' +
														s +
														( isActive
															? ' lfuf-avail-board__filter-btn--active'
															: '' ),
												},
												statusLabel( s )
											);
										}
									)
								),
								( board.filter_types || [] ).length > 1
									? el(
											'div',
											{
												className:
													'lfuf-avail-board__filter-group',
												role: 'toolbar',
											},
											el(
												'span',
												{
													className:
														'lfuf-avail-board__filter-label',
												},
												'Type:'
											),
											el(
												'span',
												{
													className:
														'lfuf-avail-board__filter-btn lfuf-avail-board__filter-btn--active',
												},
												'All'
											),
											( board.filter_types || [] ).map(
												function ( ft ) {
													return el(
														'span',
														{
															key: ft.slug,
															className:
																'lfuf-avail-board__filter-btn',
														},
														ft.label
													);
												}
											)
									  )
									: null
						  )
						: null,

					// Groups.
					groups.map( function ( group ) {
						return el(
							'div',
							{
								key: group.slug,
								className: 'lfuf-avail-board__group',
							},
							el(
								'h3',
								{ className: 'lfuf-avail-board__group-title' },
								group.label,
								el(
									'span',
									{
										className:
											'lfuf-avail-board__group-count',
									},
									group.items.length
								)
							),
							el(
								'div',
								{
									className:
										'lfuf-avail-board__items lfuf-avail-board__items--' +
										layout,
								},
								group.items.map( function ( item ) {
									return el(
										'article',
										{
											key:
												item.availability_id ||
												item.product_id,
											className: 'lfuf-avail-board__item',
										},
										// Thumbnail.
										showImages && item.thumbnail_url
											? el(
													'div',
													{
														className:
															'lfuf-avail-board__item-image',
													},
													el( 'img', {
														src: item.thumbnail_url,
														alt: '',
														loading: 'lazy',
														width: 80,
														height: 80,
													} )
											  )
											: null,
										// Body.
										el(
											'div',
											{
												className:
													'lfuf-avail-board__item-body',
											},
											el(
												'div',
												{
													className:
														'lfuf-avail-board__item-header',
												},
												el(
													'span',
													{
														className:
															'lfuf-avail-board__item-name',
													},
													item.product_name
												),
												el(
													'span',
													{
														className:
															'lfuf-availability-badge lfuf-availability-badge--' +
															item.status,
													},
													statusLabel( item.status )
												)
											),
											showPrices && item.price
												? el(
														'span',
														{
															className:
																'lfuf-avail-board__item-price',
														},
														item.price,
														item.unit
															? el(
																	'span',
																	{
																		className:
																			'lfuf-avail-board__item-unit',
																	},
																	' / ' +
																		item.unit
															  )
															: null
												  )
												: null,
											showQuantityNotes &&
												item.quantity_note
												? el(
														'span',
														{
															className:
																'lfuf-avail-board__item-note',
														},
														item.quantity_note
												  )
												: null,
											item.seasons && item.seasons.length
												? el(
														'div',
														{
															className:
																'lfuf-avail-board__item-seasons',
														},
														item.seasons.map(
															function ( s ) {
																return el(
																	'span',
																	{
																		key: s,
																		className:
																			'lfuf-avail-board__season-tag',
																	},
																	s
																);
															}
														)
												  )
												: null
										)
									);
								} )
							)
						);
					} ),

					// Footer.
					el(
						'p',
						{ className: 'lfuf-avail-board__footer' },
						'Showing ' + total + ' items',
						board.generated_at
							? el(
									'span',
									{
										className:
											'lfuf-avail-board__timestamp',
									},
									new Date(
										board.generated_at
									).toLocaleString( undefined, {
										month: 'short',
										day: 'numeric',
										hour: 'numeric',
										minute: '2-digit',
									} )
							  )
							: null
					)
				)
			);

			/**
			 * Sidebar inspector — shared across all render branches.
			 */
			function renderInspector() {
				const activeDefaults = parseActiveStatuses(
					attributes.defaultStatusFilter
				);

				return el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Board Settings', initialOpen: true },
						el( SelectControl, {
							label: 'Layout',
							value: layout,
							options: [
								{ label: 'Grid', value: 'grid' },
								{ label: 'List', value: 'list' },
							],
							onChange( val ) {
								setAttributes( { layout: val } );
							},
						} ),
						el( ComboboxControl, {
							label: 'Location filter',
							value: locationId || '',
							options: locationOptions,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
						} ),
						el( TextControl, {
							label: 'Empty state message',
							value: attributes.emptyMessage,
							onChange( val ) {
								setAttributes( { emptyMessage: val } );
							},
						} )
					),
					el(
						PanelBody,
						{ title: 'Default Visibility', initialOpen: true },
						ALL_STATUSES.map( function ( status ) {
							return el( CheckboxControl, {
								key: status,
								label: statusLabel( status ),
								checked:
									activeDefaults.indexOf( status ) !== -1,
								onChange( checked ) {
									let list = parseActiveStatuses(
										attributes.defaultStatusFilter
									);
									if ( checked ) {
										if ( list.indexOf( status ) === -1 ) {
											list.push( status );
										}
									} else {
										list = list.filter( function ( s ) {
											return s !== status;
										} );
									}
									setAttributes( {
										defaultStatusFilter: list.join( ',' ),
									} );
								},
							} );
						} )
					),
					el(
						PanelBody,
						{ title: 'Display Options', initialOpen: false },
						el( ToggleControl, {
							label: 'Show filter controls',
							checked: showFilters,
							onChange( val ) {
								setAttributes( { showFilters: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show product images',
							checked: showImages,
							onChange( val ) {
								setAttributes( { showImages: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show prices',
							checked: showPrices,
							onChange( val ) {
								setAttributes( { showPrices: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show quantity notes',
							checked: showQuantityNotes,
							onChange( val ) {
								setAttributes( { showQuantityNotes: val } );
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
