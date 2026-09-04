/**
 * Product Editor Sidebar — custom panels for pkit_product meta.
 *
 * Panels:
 *   1. Product Details — price, unit, growing notes
 *   2. Source Links    — search and link source posts
 */
( function () {
	'use strict';

	const el = wp.element.createElement;
	const __ = wp.i18n.__;
	const useState = wp.element.useState;
	const registerPlugin = wp.plugins.registerPlugin;
	const PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
	const useEntityProp = wp.coreData.useEntityProp;
	const useSelect = wp.data.useSelect;
	const TextControl = wp.components.TextControl;
	const TextareaControl = wp.components.TextareaControl;
	const SelectControl = wp.components.SelectControl;
	const Button = wp.components.Button;
	const FormTokenField = wp.components.FormTokenField;
	const useDispatch = wp.data.useDispatch;
	const useEffect = wp.element.useEffect;

	/**
	 * The trade fields a producer profile switches on.
	 *
	 * Which of these exist, and what they are called, depends on the profile
	 * the *current user* reads the admin in — so both come from the REST
	 * taxonomy index at runtime rather than being hardcoded here.
	 */
	const TRADE_TAXONOMIES = [
		'pkit_material',
		'pkit_finish',
		'pkit_component',
	];

	const COMMON_UNITS = [
		{ label: __( '— Select —', 'producerkit' ), value: '' },
		{ label: __( 'bunch', 'producerkit' ), value: 'bunch' },
		{ label: __( 'bag', 'producerkit' ), value: 'bag' },
		{ label: __( 'loaf', 'producerkit' ), value: 'loaf' },
		{ label: __( 'half dozen', 'producerkit' ), value: 'half dozen' },
		{ label: __( 'dozen', 'producerkit' ), value: 'dozen' },
		{ label: __( 'pint', 'producerkit' ), value: 'pint' },
		{ label: __( 'pint jar', 'producerkit' ), value: 'pint jar' },
		{ label: __( 'quart', 'producerkit' ), value: 'quart' },
		{ label: __( 'half pound', 'producerkit' ), value: 'half pound' },
		{ label: __( 'pound', 'producerkit' ), value: 'pound' },
		{ label: __( 'each', 'producerkit' ), value: 'each' },
		{ label: __( 'plant', 'producerkit' ), value: 'plant' },
		{ label: __( 'flat', 'producerkit' ), value: 'flat' },
		{ label: __( 'other (type below)', 'producerkit' ), value: '__custom' },
	];

	/* ─────────────────────────────────────────────
	 * Panel 1: Product Details
	 * ───────────────────────────────────────────── */

	function ProductDetailsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'pkit_product' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'pkit_product', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		const _customUnit = useState( false );
		const showCustomUnit = _customUnit[ 0 ];
		const setShowCustomUnit = _customUnit[ 1 ];

		function updateMeta( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		}

		// Check if current unit is in the common list.
		const currentUnit = meta._pkit_unit || '';
		const unitInList = COMMON_UNITS.some( function ( u ) {
			return u.value === currentUnit;
		} );
		const isCustom = showCustomUnit || ( currentUnit && ! unitInList );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-product-details',
				title: __( 'Product Details', 'producerkit' ),
				initialOpen: true,
				icon: 'tag',
			},

			el( TextControl, {
				label: __( 'Price', 'producerkit' ),
				value: meta._pkit_price || '',
				onChange( val ) {
					updateMeta( '_pkit_price', val );
				},
				placeholder: '$4',
				help: __(
					'Display price. Can be "$5", "Donation", "$3–5", etc.',
					'producerkit'
				),
			} ),

			el( SelectControl, {
				label: __( 'Unit of Sale', 'producerkit' ),
				value: isCustom ? '__custom' : currentUnit,
				options: COMMON_UNITS,
				onChange( val ) {
					if ( val === '__custom' ) {
						setShowCustomUnit( true );
					} else {
						setShowCustomUnit( false );
						updateMeta( '_pkit_unit', val );
					}
				},
				help: __( 'How this product is sold.', 'producerkit' ),
			} ),

			isCustom
				? el( TextControl, {
						label: __( 'Custom Unit', 'producerkit' ),
						value: currentUnit,
						onChange( val ) {
							updateMeta( '_pkit_unit', val );
						},
						placeholder: __( 'e.g. 4 oz bag', 'producerkit' ),
				  } )
				: null,

			el( TextareaControl, {
				label: __( 'Growing / Baking Notes', 'producerkit' ),
				value: meta._pkit_growing_notes || '',
				onChange( val ) {
					updateMeta( '_pkit_growing_notes', val );
				},
				placeholder: __(
					'Heirloom variety, cold-hardy. No-till grown.',
					'producerkit'
				),
				help: __(
					'Shown on the product card and single product page.',
					'producerkit'
				),
				rows: 3,
			} )
		);
	}

	/* ─────────────────────────────────────────────
	 * Panel 2: Source Links
	 * ───────────────────────────────────────────── */

	function ProductSourcesPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'pkit_product' ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'pkit_product', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		const sourceIds = meta._pkit_source_ids || [];

		// Fetch all sources.
		const allSources = useSelect( function ( select ) {
			return (
				select( 'core' ).getEntityRecords( 'postType', 'pkit_source', {
					per_page: 50,
					status: 'publish',
					_fields: 'id,title',
				} ) || []
			);
		}, [] );

		// Fetch linked source details.
		const linkedSources = useSelect(
			function ( select ) {
				if ( ! sourceIds.length ) {
					return [];
				}
				return sourceIds
					.map( function ( id ) {
						return select( 'core' ).getEntityRecord(
							'postType',
							'pkit_source',
							id
						);
					} )
					.filter( Boolean );
			},
			[ sourceIds.join( ',' ) ]
		);

		function addSource( id ) {
			if ( sourceIds.indexOf( id ) === -1 ) {
				const updated = {};
				updated._pkit_source_ids = sourceIds.concat( [ id ] );
				setMeta( Object.assign( {}, meta, updated ) );
			}
		}

		function removeSource( id ) {
			const updated = {};
			updated._pkit_source_ids = sourceIds.filter( function ( s ) {
				return s !== id;
			} );
			setMeta( Object.assign( {}, meta, updated ) );
		}

		// Sources not already linked.
		const availableSources = allSources.filter( function ( s ) {
			return sourceIds.indexOf( s.id ) === -1;
		} );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-product-sources',
				title: __( 'Sources', 'producerkit' ),
				initialOpen: false,
				icon: 'admin-site-alt3',
			},

			el(
				'p',
				{
					className: 'components-base-control__help',
					style: { marginTop: 0 },
				},
				'Link this product to grain origins or partner farms.'
			),

			// Linked sources.
			linkedSources.length > 0
				? el(
						'div',
						{ style: { marginBottom: '12px' } },
						linkedSources.map( function ( source ) {
							return el(
								'div',
								{
									key: source.id,
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
									source.title?.rendered || '(untitled)'
								),
								el( Button, {
									isSmall: true,
									isDestructive: true,
									icon: 'no-alt',
									label: __( 'Remove', 'producerkit' ),
									onClick() {
										removeSource( source.id );
									},
								} )
							);
						} )
				  )
				: null,

			// Add source dropdown.
			availableSources.length > 0
				? el( SelectControl, {
						value: '',
						options: [
							{
								label: __( '— Add a source —', 'producerkit' ),
								value: '',
							},
						].concat(
							availableSources.map( function ( s ) {
								return {
									label: s.title?.rendered || '(untitled)',
									value: s.id,
								};
							} )
						),
						onChange( val ) {
							if ( val ) {
								addSource( parseInt( val, 10 ) );
							}
						},
				  } )
				: sourceIds.length === 0
				? el(
						'p',
						{
							style: {
								color: '#6b7280',
								fontStyle: 'italic',
								fontSize: '13px',
							},
						},
						'No sources created yet. Add them under Sources in the sidebar.'
				  )
				: null
		);
	}

	/* ─────────────────────────────────────────────
	 * Trade fields
	 * ───────────────────────────────────────────── */

	/**
	 * One panel for whichever trade fields this site's profiles switched on.
	 *
	 * These worked before through WordPress's own taxonomy panels, so a maker
	 * filled in price and unit in the plugin's panel and Clay Body somewhere
	 * else entirely. This gathers them, and removes the default panels so the
	 * same field is not offered twice in two different places.
	 */
	function ProductTradeFieldsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		// Only the taxonomies that are actually registered right now, with the
		// labels this viewer's profile gives them.
		const taxonomies = useSelect( function ( select ) {
			const all = select( 'core' ).getTaxonomies( { per_page: -1 } );
			if ( ! all ) {
				return null;
			}

			return all.filter( function ( tax ) {
				return TRADE_TAXONOMIES.indexOf( tax.slug ) !== -1;
			} );
		}, [] );

		const removeEditorPanel =
			useDispatch( 'core/edit-post' ).removeEditorPanel;

		useEffect(
			function () {
				if ( ! removeEditorPanel ) {
					return;
				}

				TRADE_TAXONOMIES.forEach( function ( slug ) {
					removeEditorPanel( 'taxonomy-panel-' + slug );
				} );
			},
			[ removeEditorPanel ]
		);

		if (
			postType !== 'pkit_product' ||
			! taxonomies ||
			! taxonomies.length
		) {
			return null;
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-product-trade-fields',
				title: __( 'Trade Details', 'producerkit' ),
				className: 'pkit-product-trade-fields',
			},
			taxonomies.map( function ( tax ) {
				return el( TradeField, { key: tax.slug, taxonomy: tax } );
			} )
		);
	}

	/**
	 * One taxonomy, as a token field.
	 *
	 * Tokens rather than a select: these are open vocabularies a maker adds to
	 * — a new glaze, a new wood — and the profile only seeds a starting set.
	 * @param props
	 */
	function TradeField( props ) {
		const taxonomy = props.taxonomy;
		const restBase = taxonomy.rest_base || taxonomy.slug;

		const terms = useSelect(
			function ( select ) {
				return select( 'core' ).getEntityRecords(
					'taxonomy',
					taxonomy.slug,
					{
						per_page: -1,
						_fields: 'id,name',
					}
				);
			},
			[ taxonomy.slug ]
		);

		const [ assigned, setAssigned ] = useEntityProp(
			'postType',
			'pkit_product',
			restBase
		);

		const byName = {};
		( terms || [] ).forEach( function ( term ) {
			byName[ term.name ] = term.id;
		} );

		const idToName = {};
		( terms || [] ).forEach( function ( term ) {
			idToName[ term.id ] = term.name;
		} );

		const values = ( assigned || [] )
			.map( function ( id ) {
				return idToName[ id ];
			} )
			.filter( Boolean );

		return el( FormTokenField, {
			label: taxonomy.name,
			value: values,
			suggestions: Object.keys( byName ),
			onChange( next ) {
				// Only names that already resolve to a term. Creating terms
				// from here would need a separate request and a capability
				// check; the profile seeds the vocabulary and the taxonomy
				// screen adds to it.
				const ids = next
					.map( function ( name ) {
						return byName[ name ];
					} )
					.filter( Boolean );

				setAssigned( ids );
			},
			__experimentalExpandOnFocus: true,
			__next40pxDefaultSize: true,
		} );
	}

	/* ─────────────────────────────────────────────
	 * Panel 4: Pre-Order Payment
	 *
	 * Only rendered when the WooCommerce module is active. Without a store
	 * there is nothing to collect a deposit with, and a control that silently
	 * does nothing is worse than an absent one.
	 * ───────────────────────────────────────────── */

	function ProductPaymentPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		const settings = window.pkitSettings || {};
		const modules = settings.activeModules || [];
		const hasStore =
			settings.hasWooCommerce && modules.indexOf( 'woocommerce' ) !== -1;

		if ( postType !== 'pkit_product' || ! hasStore ) {
			return null;
		}

		const _meta = useEntityProp( 'postType', 'pkit_product', 'meta' );
		const meta = _meta[ 0 ];
		const setMeta = _meta[ 1 ];

		function updateMeta( key, value ) {
			const updated = {};
			updated[ key ] = value;
			setMeta( Object.assign( {}, meta, updated ) );
		}

		const mode = meta._pkit_payment_mode || 'none';
		const kind = meta._pkit_deposit_kind || 'fixed';

		const children = [
			el( SelectControl, {
				key: 'mode',
				label: __( 'When a customer pre-orders this', 'producerkit' ),
				value: mode,
				options: [
					{
						label: __(
							'Reserve only — pay at pickup',
							'producerkit'
						),
						value: 'none',
					},
					{
						label: __( 'Take a deposit now', 'producerkit' ),
						value: 'deposit',
					},
					{
						label: __( 'Take the full amount now', 'producerkit' ),
						value: 'full',
					},
				],
				onChange( value ) {
					updateMeta( '_pkit_payment_mode', value );
				},
				help:
					mode === 'none'
						? 'No money changes hands online. This is the default.'
						: 'Raises a pending WooCommerce order and emails the customer a payment link.',
			} ),
		];

		if ( mode === 'deposit' ) {
			children.push(
				el( SelectControl, {
					key: 'kind',
					label: __( 'Deposit is', 'producerkit' ),
					value: kind,
					options: [
						{
							label: __(
								'A fixed amount per item',
								'producerkit'
							),
							value: 'fixed',
						},
						{
							label: __(
								'A percentage of the line',
								'producerkit'
							),
							value: 'percent',
						},
					],
					onChange( value ) {
						updateMeta( '_pkit_deposit_kind', value );
					},
				} )
			);

			children.push(
				el( TextControl, {
					key: 'value',
					label:
						kind === 'percent'
							? 'Percent to take now'
							: 'Amount per item',
					type: 'number',
					min: 0,
					step: kind === 'percent' ? 1 : 0.01,
					value: meta._pkit_deposit_value || 0,
					onChange( value ) {
						updateMeta(
							'_pkit_deposit_value',
							parseFloat( value ) || 0
						);
					},
					help:
						kind === 'percent'
							? 'A deposit above 100% is charged as the full amount.'
							: 'Multiplied by quantity — $50 on two items takes $100. A deposit above the item price is charged as the full amount.',
				} )
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'pkit-product-payment',
				title: __( 'Pre-Order Payment', 'producerkit' ),
				initialOpen: false,
				icon: 'money-alt',
			},
			children
		);
	}

	/* ─────────────────────────────────────────────
	 * Register
	 * ───────────────────────────────────────────── */

	registerPlugin( 'pkit-product-details', {
		render: ProductDetailsPanel,
		icon: 'tag',
	} );

	registerPlugin( 'pkit-product-sources', {
		render: ProductSourcesPanel,
		icon: 'admin-site-alt3',
	} );

	registerPlugin( 'pkit-product-trade-fields', {
		render: ProductTradeFieldsPanel,
		icon: 'admin-appearance',
	} );

	registerPlugin( 'pkit-product-payment', {
		render: ProductPaymentPanel,
		icon: 'money-alt',
	} );
} )();
