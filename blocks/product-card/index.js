/**
 * Product Card — editor block (no-build IIFE).
 *
 * Registers producerkit/product-card with a product selector
 * and toggle controls for availability / source display.
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
	const Fragment = element.Fragment;
	const useState = element.useState;
	const useEffect = element.useEffect;
	const registerBlockType = blocks.registerBlockType;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const PanelBody = components.PanelBody;
	const ToggleControl = components.ToggleControl;
	const ComboboxControl = components.ComboboxControl;
	const Spinner = components.Spinner;
	const Placeholder = components.Placeholder;
	const useSelect = data.useSelect;

	const STATUS_LABELS = {
		abundant: __( 'Abundant', 'producerkit' ),
		available: __( 'Available', 'producerkit' ),
		limited: __( 'Limited', 'producerkit' ),
		sold_out: __( 'Sold out', 'producerkit' ),
		unavailable: __( 'Unavailable', 'producerkit' ),
	};

	function getRestBase() {
		return (
			( window.pkitSettings || {} ).restBase || '/wp-json/producerkit/v1'
		);
	}

	registerBlockType( 'producerkit/product-card', {
		edit: function EditProductCard( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const productId = attributes.productId;
			const showAvailability = attributes.showAvailability;
			const showSource = attributes.showSource;

			// Product data from REST.
			const _product = useState( null );
			const product = _product[ 0 ];
			const setProduct = _product[ 1 ];

			// Availability from board endpoint.
			const _avail = useState( null );
			const avail = _avail[ 0 ];
			const setAvail = _avail[ 1 ];

			// Source posts.
			const _sources = useState( [] );
			const sources = _sources[ 0 ];
			const setSources = _sources[ 1 ];

			const _loading = useState( false );
			const loading = _loading[ 0 ];
			const setLoading = _loading[ 1 ];

			const _error = useState( '' );
			const error = _error[ 0 ];
			const setError = _error[ 1 ];

			// Fetch product + availability when productId changes.
			useEffect(
				function () {
					if ( ! productId ) {
						setProduct( null );
						setAvail( null );
						setSources( [] );
						return;
					}
					setLoading( true );
					setError( '' );

					const productFetch = fetch(
						getRestBase() + '/products/' + productId + '?_embed'
					).then( function ( r ) {
						if ( ! r.ok ) {
							throw new Error( r.status );
						}
						return r.json();
					} );

					const boardFetch = fetch( getRestBase() + '/board' )
						.then( function ( r ) {
							return r.json();
						} )
						.catch( function () {
							return { groups: [] };
						} );

					Promise.all( [ productFetch, boardFetch ] )
						.then( function ( results ) {
							const prod = results[ 0 ];
							setProduct( prod );

							// Find this product's availability in board data.
							let match = null;
							( results[ 1 ].groups || [] ).forEach(
								function ( g ) {
									g.items.forEach( function ( item ) {
										if ( item.product_id === productId ) {
											match = item;
										}
									} );
								}
							);
							setAvail( match );

							// Fetch sources if the product has source IDs.
							const sourceIds = ( prod.meta || {} )
								._pkit_source_ids;
							if ( sourceIds && sourceIds.length ) {
								const sourcePromises = sourceIds.map(
									function ( sid ) {
										return fetch(
											getRestBase() +
												'/sources/' +
												sid +
												'?_fields=id,title,meta'
										)
											.then( function ( r ) {
												return r.ok ? r.json() : null;
											} )
											.catch( function () {
												return null;
											} );
									}
								);
								Promise.all( sourcePromises ).then(
									function ( srcResults ) {
										setSources(
											srcResults.filter( Boolean )
										);
										setLoading( false );
									}
								);
							} else {
								setSources( [] );
								setLoading( false );
							}
						} )
						.catch( function () {
							setError(
								__(
									'Could not load product data.',
									'producerkit'
								)
							);
							setProduct( null );
							setLoading( false );
						} );
				},
				[ productId ]
			);

			// Product list for the picker.
			const products = useSelect( function ( select ) {
				return (
					select( 'core' ).getEntityRecords(
						'postType',
						'pkit_product',
						{
							per_page: 100,
							status: 'publish',
							_fields: 'id,title',
						}
					) || []
				);
			}, [] );

			const options = products.map( function ( p ) {
				return {
					value: p.id,
					label:
						p.title?.rendered ||
						p.title?.raw ||
						__( '(untitled)', 'producerkit' ),
				};
			} );

			const blockProps = useBlockProps( {
				className: 'pkit-product-card',
			} );

			// Extract display data from product response.
			const title = product ? product.title?.rendered || '' : '';
			const meta = product ? product.meta || {} : {};
			const price = meta._pkit_price || '';
			const unit = meta._pkit_unit || '';
			const growingNotes = meta._pkit_growing_notes || '';

			// Thumbnail from _embedded.
			let thumbnailUrl = '';
			if (
				product &&
				product._embedded &&
				product._embedded[ 'wp:featuredmedia' ]
			) {
				const fm = product._embedded[ 'wp:featuredmedia' ][ 0 ];
				if ( fm ) {
					thumbnailUrl =
						fm.media_details &&
						fm.media_details.sizes &&
						fm.media_details.sizes.medium
							? fm.media_details.sizes.medium.source_url
							: fm.source_url || '';
				}
			}

			// Taxonomy terms from _embedded.
			const productTypes = [];
			const seasons = [];
			if (
				product &&
				product._embedded &&
				product._embedded[ 'wp:term' ]
			) {
				product._embedded[ 'wp:term' ].forEach( function ( termGroup ) {
					if ( ! Array.isArray( termGroup ) ) {
						return;
					}
					termGroup.forEach( function ( term ) {
						if ( term.taxonomy === 'pkit_product_type' ) {
							productTypes.push( term.name );
						} else if ( term.taxonomy === 'pkit_season' ) {
							seasons.push( term.name );
						}
					} );
				} );
			}

			// No product selected — placeholder with inline picker.
			if ( ! productId ) {
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
								icon: 'tag',
								label: __( 'Product Card', 'producerkit' ),
							},
							el( ComboboxControl, {
								label: __( 'Select a product', 'producerkit' ),
								value: '',
								options,
								onChange( val ) {
									setAttributes( {
										productId: val ? Number( val ) : 0,
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
							{ className: 'pkit-product-card__loading' },
							el( Spinner ),
							' Loading product\u2026'
						)
					)
				);
			}

			// Error.
			if ( error || ! product ) {
				return el(
					Fragment,
					null,
					renderInspector(),
					el(
						'div',
						blockProps,
						el( Placeholder, {
							icon: 'warning',
							label: __( 'Product Card', 'producerkit' ),
							instructions:
								error ||
								__(
									'Product data unavailable.',
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
					'article',
					blockProps,

					// Thumbnail.
					thumbnailUrl
						? el(
								'div',
								{ className: 'pkit-product-card__image' },
								el( 'img', {
									src: thumbnailUrl,
									alt: '',
									loading: 'lazy',
								} )
						  )
						: null,

					// Body.
					el(
						'div',
						{ className: 'pkit-product-card__body' },

						// Title.
						el(
							'h3',
							{ className: 'pkit-product-card__title' },
							el( 'span', null, title )
						),

						// Product type.
						productTypes.length
							? el(
									'span',
									{ className: 'pkit-product-card__type' },
									productTypes.join( ', ' )
							  )
							: null,

						// Price.
						price
							? el(
									'span',
									{ className: 'pkit-product-card__price' },
									price,
									unit
										? el(
												'span',
												{
													className:
														'pkit-product-card__unit',
												},
												' / ' + unit
										  )
										: null
							  )
							: null,

						// Seasons.
						seasons.length
							? el(
									'div',
									{ className: 'pkit-product-card__seasons' },
									seasons.map( function ( s ) {
										return el(
											'span',
											{
												key: s,
												className:
													'pkit-product-card__season-badge',
											},
											s
										);
									} )
							  )
							: null,

						// Growing notes.
						growingNotes
							? el(
									'p',
									{ className: 'pkit-product-card__notes' },
									growingNotes
							  )
							: null,

						// Availability.
						showAvailability && avail
							? el(
									'div',
									{
										className:
											'pkit-product-card__availability',
									},
									el(
										'span',
										{
											className:
												'pkit-availability-badge pkit-availability-badge--' +
												avail.status,
										},
										STATUS_LABELS[ avail.status ] ||
											avail.status
									),
									avail.quantity_note
										? el(
												'span',
												{
													className:
														'pkit-product-card__quantity-note',
												},
												avail.quantity_note
										  )
										: null
							  )
							: showAvailability && ! avail
							? el(
									'div',
									{
										className:
											'pkit-product-card__availability',
									},
									el(
										'span',
										{
											className:
												'pkit-availability-badge pkit-availability-badge--unavailable',
										},
										__(
											'No availability set',
											'producerkit'
										)
									)
							  )
							: null,

						// Sources.
						showSource && sources.length
							? el(
									'div',
									{ className: 'pkit-product-card__sources' },
									el(
										'strong',
										null,
										__( 'Sourced from:', 'producerkit' )
									),
									sources.map( function ( src ) {
										const farmName =
											src.meta &&
											src.meta._pkit_source_farm_name
												? src.meta
														._pkit_source_farm_name
												: src.title?.rendered ||
												  src.title?.raw ||
												  '';
										const loc =
											( src.meta &&
												src.meta
													._pkit_source_location ) ||
											'';
										return el(
											'div',
											{
												key: src.id,
												className:
													'pkit-product-card__source',
											},
											el( 'span', null, farmName ),
											loc
												? el(
														'span',
														{
															className:
																'pkit-product-card__source-location',
														},
														' (' + loc + ')'
												  )
												: null
										);
									} )
							  )
							: null
					)
				)
			);

			function renderInspector() {
				return el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Product Settings', 'producerkit' ),
							initialOpen: true,
						},
						el( ComboboxControl, {
							label: __( 'Select Product', 'producerkit' ),
							value: productId || '',
							options,
							onChange( val ) {
								setAttributes( {
									productId: val ? Number( val ) : 0,
								} );
							},
						} ),
						el( ToggleControl, {
							label: __(
								'Show availability status',
								'producerkit'
							),
							checked: showAvailability,
							onChange( val ) {
								setAttributes( { showAvailability: val } );
							},
						} ),
						el( ToggleControl, {
							label: __(
								'Show source / grain info',
								'producerkit'
							),
							checked: showSource,
							onChange( val ) {
								setAttributes( { showSource: val } );
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
