/**
 * Stand Quick Toggle — editor-only control block (no-build IIFE).
 *
 * Renders a toggle switch + message input that hit the REST API
 * directly from the editor. Renders nothing on the front end.
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
	const sprintf = i18n.sprintf;
	const useState = element.useState;
	const useEffect = element.useEffect;
	const useCallback = element.useCallback;
	const Fragment = element.Fragment;
	const registerBlockType = blocks.registerBlockType;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const PanelBody = components.PanelBody;
	const ComboboxControl = components.ComboboxControl;
	const ToggleControl = components.ToggleControl;
	const TextControl = components.TextControl;
	const Button = components.Button;
	const Spinner = components.Spinner;
	const Notice = components.Notice;
	const useSelect = data.useSelect;

	function getRestBase() {
		return (
			( window.pkitStandSettings || window.pkitSettings || {} )
				.restBase || '/wp-json/producerkit/v1'
		);
	}

	function getNonce() {
		return (
			( window.pkitStandSettings || window.pkitSettings || {} ).nonce ||
			''
		);
	}

	registerBlockType( 'producerkit/stand-toggle', {
		edit: function EditStandToggle( props ) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			const locationId = attributes.locationId;
			const blockProps = useBlockProps( {
				className: 'pkit-stand-toggle',
			} );

			const _state = useState( false );
			const isOpen = _state[ 0 ];
			const setIsOpen = _state[ 1 ];

			const _msg = useState( '' );
			const message = _msg[ 0 ];
			const setMessage = _msg[ 1 ];

			const _saving = useState( false );
			const saving = _saving[ 0 ];
			const setSaving = _saving[ 1 ];

			const _notice = useState( '' );
			const notice = _notice[ 0 ];
			const setNotice = _notice[ 1 ];

			const _loaded = useState( false );
			const loaded = _loaded[ 0 ];
			const setLoaded = _loaded[ 1 ];

			// Fetch locations for selector.
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

			const options = locations.map( function ( l ) {
				return {
					value: l.id,
					label:
						l.title?.rendered || __( '(untitled)', 'producerkit' ),
				};
			} );

			// Load current state from REST.
			useEffect(
				function () {
					if ( ! locationId ) {
						return;
					}
					setLoaded( false );
					fetch( getRestBase() + '/stand/' + locationId + '/info' )
						.then( function ( r ) {
							return r.json();
						} )
						.then( function ( payload ) {
							setIsOpen( !! payload.is_open );
							setMessage( payload.status_message || '' );
							setLoaded( true );
						} )
						.catch( function () {
							setLoaded( true );
						} );
				},
				[ locationId ]
			);

			// Save handler.
			const save = useCallback(
				function () {
					if ( ! locationId ) {
						return;
					}
					setSaving( true );
					setNotice( '' );
					fetch( getRestBase() + '/stand/' + locationId + '/status', {
						method: 'PATCH',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': getNonce(),
						},
						body: JSON.stringify( {
							is_open: isOpen,
							status_message: message,
						} ),
					} )
						.then( function ( r ) {
							return r.json();
						} )
						.then( function ( payload ) {
							setSaving( false );
							setNotice(
								sprintf(
									/* translators: %s: OPEN or CLOSED. */
									__( 'Stand is now %s.', 'producerkit' ),
									payload.is_open
										? __( 'OPEN', 'producerkit' )
										: __( 'CLOSED', 'producerkit' )
								)
							);
							setTimeout( function () {
								setNotice( '' );
							}, 4000 );
						} )
						.catch( function () {
							setSaving( false );
							setNotice(
								__(
									'Error updating stand status.',
									'producerkit'
								)
							);
						} );
				},
				[ locationId, isOpen, message ]
			);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Stand Selection', 'producerkit' ),
							initialOpen: true,
						},
						el( ComboboxControl, {
							label: __( 'Location', 'producerkit' ),
							value: locationId || '',
							options,
							onChange( val ) {
								setAttributes( {
									locationId: val ? Number( val ) : 0,
								} );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					! locationId
						? el( components.Placeholder, {
								icon: 'controls-repeat',
								label: __(
									'Stand Quick Toggle',
									'producerkit'
								),
								instructions: __(
									'Select a location in the sidebar.',
									'producerkit'
								),
						  } )
						: ! loaded
						? el(
								'div',
								{ className: 'pkit-stand-toggle__loading' },
								el( Spinner ),
								' Loading stand status…'
						  )
						: el(
								'div',
								{ className: 'pkit-stand-toggle__panel' },
								el(
									'div',
									{ className: 'pkit-stand-toggle__header' },
									el( 'span', {
										className:
											'pkit-stand-toggle__dot pkit-stand-toggle__dot--' +
											( isOpen ? 'open' : 'closed' ),
									} ),
									el(
										'strong',
										null,
										isOpen
											? __(
													'Stand is OPEN',
													'producerkit'
											  )
											: __(
													'Stand is CLOSED',
													'producerkit'
											  )
									)
								),
								el( ToggleControl, {
									label: isOpen
										? __( 'Open', 'producerkit' )
										: __( 'Closed', 'producerkit' ),
									checked: isOpen,
									onChange( val ) {
										setIsOpen( val );
									},
								} ),
								el( TextControl, {
									label: __(
										'Status message (optional)',
										'producerkit'
									),
									value: message,
									onChange( val ) {
										setMessage( val );
									},
									placeholder: __(
										'e.g. "Back at 2 PM" or "Sold out for today"',
										'producerkit'
									),
								} ),
								el(
									'div',
									{ className: 'pkit-stand-toggle__actions' },
									el(
										Button,
										{
											variant: 'primary',
											onClick: save,
											isBusy: saving,
											disabled: saving,
										},
										saving
											? __( 'Saving…', 'producerkit' )
											: __(
													'Update Stand Status',
													'producerkit'
											  )
									)
								),
								notice
									? el(
											Notice,
											{
												status: notice.includes(
													__( 'Error', 'producerkit' )
												)
													? 'error'
													: 'success',
												isDismissible: true,
												onDismiss() {
													setNotice( '' );
												},
											},
											notice
									  )
									: null
						  )
				)
			);
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
